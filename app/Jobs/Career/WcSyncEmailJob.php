<?php

namespace App\Jobs\Career;

use App\Jobs\Career\Concerns\AntreanWebCareers;
use App\Jobs\Career\Concerns\CatatGagalWebCareers;
use App\Services\Surat\SuratClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * WEB CAREER — pengiriman email kandidat secara ASINKRON.
 *
 * Local → driver default (database) saat `php artisan queue:work` polos.
 * Non-local (Cloud Run) → connection 'cloudtasks', queue 'wc-syncemailjob'.
 * Queue itu HARUS sudah dibuat di Cloud Tasks: `gcloud tasks queues create wc-syncemailjob`.
 *
 * Jenis email dibuat extensible lewat $jenis; saat ini baru VERIFIKASI.
 * Token verifikasi ASLI hanya lewat payload job ini — DB cuma menyimpan hash.
 *
 * ══ SURATNYA DIKIRIM EVO MAIL SERVER, BUKAN OLEH LAYANAN INI ══════════════
 *
 * Web Careers tidak lagi memegang kredensial SMTP dan tidak lagi merender
 * berkas surat. Job ini tetap yang memutuskan KAPAN sebuah surat dikirim dan
 * untuk kandidat mana — ia yang tahu konteksnya — lalu menembak API ke server
 * surat, yang mengirim serentak dan menjawab terkirim atau gagal apa adanya.
 *
 * Yang tidak berubah: antrean, batas percobaan, dan pencatatan kegagalan
 * semuanya tetap milik job ini.
 */
class WcSyncEmailJob implements ShouldQueue
{
    use AntreanWebCareers, CatatGagalWebCareers, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const QUEUE = 'wc-syncemailjob';

    public const JENIS_VERIFIKASI = 'VERIFIKASI';

    public const JENIS_RESET_OTP = 'RESET_OTP';

    public const JENIS_RESET_SELESAI = 'RESET_SELESAI';

    public $timeout = 120;

    public $tries = 3;

    public $backoff = 30;

    protected string $jenis;

    protected int $userId;

    /** Data tambahan per jenis (VERIFIKASI: ['token' => tokenAsli]). */
    protected array $data;

    /**
     * Kunci idempotensi yang dibawa ke server surat.
     *
     * ══ DISUSUN DI KONSTRUKTOR, DAN ITU YANG MEMBUATNYA BEKERJA ═══════════
     *
     * Kunci ini ikut ter-serialisasi bersama job. Cloud Tasks mengulang job
     * dengan MUATAN YANG SAMA — konstruktor tidak dijalankan lagi — jadi
     * percobaan kedua membawa kunci yang persis sama, dan server surat
     * mengembalikan jawaban yang pertama tanpa mengirim apa pun lagi.
     *
     * Itu menutup lubang yang paling merepotkan: jaringan putus SESUDAH server
     * surat mengirim tapi SEBELUM jawabannya sampai kemari. Tanpa kunci ini,
     * kandidat menerima surat yang sama dua atau tiga kali.
     *
     * Menyusunnya di dalam handle() akan menghasilkan kunci baru di setiap
     * percobaan — sama saja dengan tidak memakai kunci sama sekali.
     */
    protected string $kunci;

    public function __construct(string $jenis, int $userId, array $data = [])
    {
        $this->jenis = $jenis;
        $this->userId = $userId;
        $this->data = $data;

        $this->kunci = $this->susunKunci();

        $this->aturAntrean(self::QUEUE);
    }

    /**
     * Kunci untuk satu pekerjaan yang sama.
     *
     * Verifikasi dan OTP diturunkan dari RAHASIANYA SENDIRI — token dan kode
     * yang bersangkutan — karena keduanya memang sekali pakai: satu token
     * verifikasi berhak atas tepat satu surat. Kalau kandidat menekan "kirim
     * ulang", token barunya menghasilkan kunci baru, dan suratnya berangkat
     * sebagaimana mestinya.
     *
     * Yang disimpan cuma potongan sidiknya, bukan nilai aslinya. Kunci ini
     * mendarat di tabel basis data milik server surat, dan token verifikasi
     * yang tersimpan di sana sama saja dengan menaruh kunci rumah di bawah
     * kesetnya.
     *
     * Pemberitahuan ganti sandi tidak punya rahasia semacam itu, jadi ia
     * memakai penanda acak — yang tetap stabil lintas percobaan karena
     * dibuat di konstruktor.
     */
    private function susunKunci(): string
    {
        $sidik = fn (string $nilai) => substr(hash('sha256', $nilai.'|'.$this->userId), 0, 24);

        return match ($this->jenis) {
            self::JENIS_VERIFIKASI => 'verif:'.$this->userId.':'.$sidik((string) ($this->data['token'] ?? '')),
            self::JENIS_RESET_OTP => 'otp:'.$this->userId.':'.$sidik((string) ($this->data['otp'] ?? '')),
            default => 'sandi:'.$this->userId.':'.Str::random(16),
        };
    }

    /**
     * Kirim email auth (verifikasi/reset) SECEPAT email apply.
     *  - cloudtasks (produksi) → antrean Cloud Tasks (andal + retry).
     *  - selain itu (lokal/database) → dispatch SETELAH RESPONSE: dikirim dalam
     *    proses yang sama begitu response terkirim ke browser, jadi TIDAK perlu
     *    menunggu worker `queue:work` yang mungkin idle/mati (dulu bisa ~3 menit).
     */
    public static function kirim(string $jenis, int $userId, array $data = []): void
    {
        if (config('queue.default') === 'cloudtasks') {
            self::dispatch($jenis, $userId, $data);

            return;
        }
        self::dispatch($jenis, $userId, $data)->afterResponse();
    }

    public function handle(): void
    {
        // Log ke channel default (stack → stderr) supaya TERLIHAT di Cloud Run /
        // log-viewer. File 'web_career' bersifat ephemeral di Cloud Run.
        Log::info("[EMAIL] job {$this->jenis} MULAI diproses untuk user #{$this->userId}.");

        $user = DB::table('N_WEB_CAREERS_Users')->where('Id_Users', $this->userId)->first();
        if (! $user) {
            Log::warning("[EMAIL] user #{$this->userId} tidak ditemukan — email {$this->jenis} dilewati.");

            return;
        }

        try {
            match ($this->jenis) {
                self::JENIS_VERIFIKASI => $this->kirimVerifikasi($user),
                self::JENIS_RESET_OTP => $this->kirimResetOtp($user),
                self::JENIS_RESET_SELESAI => $this->kirimResetSelesai($user),
                default => Log::warning("[EMAIL] jenis '{$this->jenis}' belum dikenal — dilewati."),
            };
        } catch (\Throwable $e) {
            Log::error("[EMAIL] {$this->jenis} ke {$user->Email} GAGAL (percobaan {$this->attempts()}/{$this->tries}): " . $e->getMessage());
            Log::channel('web_career')->error("[EMAIL] {$this->jenis} ke {$user->Email} GAGAL: " . $e->getMessage());

            // Percobaan terakhir → catat ke N_WEB_CAREERS_Failed_Jobs & SELESAI.
            // Sengaja TIDAK throw agar kegagalan tak masuk N_LMS_Failed_Jobs global.
            if ($this->attempts() >= $this->tries) {
                $this->catatGagalWc('EMAIL:' . $this->jenis, json_encode(['userId' => $this->userId, 'jenis' => $this->jenis]), $e);

                return;
            }

            throw $e; // masih ada sisa percobaan → retry
        }
    }

    protected function kirimVerifikasi(object $user): void
    {
        // Sudah terverifikasi (mis. klik tautan sebelum retry) → tak perlu kirim.
        if (($user->Flag_Email_Verified ?? 'T') === 'Y') {
            return;
        }

        $token = (string) ($this->data['token'] ?? '');
        if ($token === '') {
            Log::channel('web_career')->warning("[EMAIL] verifikasi #{$this->userId} tanpa token — dilewati.");

            return;
        }

        $verifUrl = rtrim(config('app.url'), '/') . '/verifikasi-email?' . http_build_query([
            'email' => $user->Email,
            'token' => $token,
        ]);

        app(SuratClient::class)->kirim(
            kepada: $user->Email,
            template: 'verifikasi-email',
            data: [
                'nama' => $user->Nama,
                'verif_url' => $verifUrl,
                'berlaku_menit' => (int) ($this->data['menit'] ?? 30),
            ],
            kunciIdempotensi: $this->kunci,
        );

        DB::table('N_WEB_CAREERS_Users')->where('Id_Users', $this->userId)->update([
            'Email_Verif_Sent_At' => now(),
            'Updated_At' => now(),
        ]);

        Log::info("[EMAIL] verifikasi terkirim ke {$user->Email} (user #{$this->userId}).");
        Log::channel('web_career')->info("[EMAIL] verifikasi terkirim ke {$user->Email} (user #{$this->userId}).");
    }

    /**
     * Kirim email OTP reset kata sandi. OTP ASLI hanya lewat payload job ini —
     * DB cuma menyimpan hash. Guard: bila OTP sudah terpakai (hash di-null-kan
     * saat sukses reset), jangan kirim OTP basi. `Reset_Otp_Sent_At` diset di
     * SINI (setelah kirim) agar cooldown baru berjalan begitu email keluar.
     */
    protected function kirimResetOtp(object $user): void
    {
        // OTP sudah dipakai/dihanguskan sebelum job jalan → tak perlu kirim.
        if (empty($user->Reset_Otp_Hash)) {
            return;
        }

        $otp = (string) ($this->data['otp'] ?? '');
        if ($otp === '') {
            Log::channel('web_career')->warning("[EMAIL] reset OTP #{$this->userId} tanpa kode — dilewati.");

            return;
        }

        $menit = (int) ($this->data['menit'] ?? 10);

        app(SuratClient::class)->kirim(
            kepada: $user->Email,
            template: 'reset-sandi-otp',
            data: [
                'nama' => $user->Nama,
                'otp' => $otp,
                'berlaku_menit' => $menit,
            ],
            kunciIdempotensi: $this->kunci,
        );

        DB::table('N_WEB_CAREERS_Users')->where('Id_Users', $this->userId)->update([
            'Reset_Otp_Sent_At' => now(),
            'Updated_At' => now(),
        ]);

        // OTP TIDAK PERNAH di-log.
        Log::info("[EMAIL] OTP reset terkirim ke {$user->Email} (user #{$this->userId}).");
        Log::channel('web_career')->info("[EMAIL] OTP reset terkirim ke {$user->Email} (user #{$this->userId}).");
    }

    /** Kirim email pemberitahuan bahwa kata sandi berhasil diubah (tanpa rahasia). */
    protected function kirimResetSelesai(object $user): void
    {
        app(SuratClient::class)->kirim(
            kepada: $user->Email,
            template: 'reset-sandi-selesai',
            data: ['nama' => $user->Nama],
            kunciIdempotensi: $this->kunci,
        );

        Log::info("[EMAIL] notifikasi ganti sandi terkirim ke {$user->Email} (user #{$this->userId}).");
        Log::channel('web_career')->info("[EMAIL] notifikasi ganti sandi terkirim ke {$user->Email} (user #{$this->userId}).");
    }
}
