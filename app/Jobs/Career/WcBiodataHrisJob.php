<?php

namespace App\Jobs\Career;

use App\Jobs\Career\Concerns\AntreanWebCareers;
use App\Services\WebCareers\HclClient;
use App\Support\Career\SinkronHrisService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * BIODATA KANDIDAT → HRIS REKRUTMEN (HCLearn), lewat antrean.
 *
 * Dikirim tiap kali data diri kandidat BERTAMBAH atau BERUBAH — bukan sekali
 * saat mendaftar akun. Itulah seluruh perbaikannya: dulu nomor telepon, jenis
 * kelamin, tanggal lahir, dan alamat tidak pernah sampai ke sana, sebab satu-
 * satunya pengiriman terjadi tepat pada saat kandidat belum mengisinya.
 *
 * LEWAT ANTREAN, dan alasannya bukan sekadar kecepatan. Panggilan ini menembak
 * server lain: bila HCLearn sedang lambat atau mati, kandidat yang menekan
 * "Kirim Formulir" akan menunggu sampai timeout lalu melihat kegagalan — untuk
 * formulir yang sebenarnya SUDAH tersimpan dengan selamat. Di antrean, ia
 * mencoba lagi sendiri sampai tiga kali.
 *
 * BEST-EFFORT, TIDAK PERNAH MENGGAGALKAN APA PUN. Biodata di HRIS adalah
 * SALINAN; sumber kebenarannya tetap di Web Careers. Kegagalan sinkron berarti
 * salinannya tertinggal sebentar, bukan data yang hilang.
 *
 * UNIK PER KANDIDAT, TAPI HANYA SAMPAI IA MULAI DIKERJAKAN.
 *
 * Satu kandidat bisa memicu beberapa sinkron beruntun — mengirim formulir lalu
 * langsung melamar posisi lain. Selama masih mengantre, permintaan kedua memang
 * tak perlu: job yang sudah antre akan membaca keadaan TERBARU saat gilirannya
 * tiba, jadi ia sudah mencakup keduanya.
 *
 * Yang tidak boleh diredam adalah permintaan yang datang SETELAH job berjalan.
 * Job itu sudah membaca datanya di awal; formulir yang tersimpan sedetik
 * kemudian tidak akan ikut terbawa. Dengan ShouldBeUnique biasa, permintaan
 * susulan itu DIBUANG dan datanya baru menyusul di pemicu berikutnya — bisa
 * berhari-hari kemudian, bisa tidak pernah kalau itu formulir terakhirnya.
 * ShouldBeUniqueUntilProcessing melepas kuncinya begitu job mulai, sehingga
 * susulan itu diterima dan dikerjakan sesudahnya.
 */
class WcBiodataHrisJob implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use AntreanWebCareers, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Antrean sendiri: kerjanya memanggil API, bukan mengirim SMTP — dan
     * kelambatan HCLearn tidak boleh menahan antrean email kandidat.
     *
     * Lokal (driver database) tidak perlu apa-apa: `php artisan queue:work`
     * sudah cukup. Untuk Cloud Tasks, antreannya HARUS dibuat lebih dulu —
     * job yang menunjuk antrean tak dikenal gagal seketika, dan gagalnya
     * terbaca seperti masalah kredensial:
     *
     *     gcloud tasks queues create wc-biodata-hris
     */
    public const QUEUE = 'wc-biodata-hris';

    public $tries = 3;

    public $backoff = 30;

    public $timeout = 60;

    /** Kunci unik dilepas setelah semenit — cukup untuk meredam beruntun. */
    public $uniqueFor = 60;

    protected int $userId;

    protected string $sebab;

    public function __construct(int $userId, string $sebab = 'PERUBAHAN_DATA')
    {
        $this->userId = $userId;
        $this->sebab = $sebab;

        $this->aturAntrean(self::QUEUE);
    }

    public function uniqueId(): string
    {
        return 'wc-biodata-' . $this->userId;
    }

    public function handle(): void
    {
        $payload = SinkronHrisService::bentukPayload($this->userId);

        if (! $payload) {
            Log::channel('web_career')->warning("[BIODATA-HRIS] akun #{$this->userId} tidak ditemukan — sinkron dilewati.");

            return;
        }

        if (! $payload['Biodata']) {
            Log::channel('web_career')->info("[BIODATA-HRIS] akun #{$this->userId} belum punya satu pun field terpetakan — tidak ada yang dikirim.");

            return;
        }

        $hasil = app(HclClient::class)->patch("kandidat/{$this->userId}/biodata", $payload, [
            'Jenis_Event' => 'SINKRON_BIODATA',
            'Sebab' => $this->sebab,
        ]);

        if (! $hasil['sukses']) {
            // DILEMPAR, bukan dicatat lalu diam: hanya lemparan yang membuat
            // antrean mencoba lagi. HCLearn yang sedang tumbang adalah alasan
            // paling lazim gagalnya panggilan ini, dan itu justru keadaan yang
            // pasti pulih sendiri.
            throw new \RuntimeException(
                "Sinkron biodata akun #{$this->userId} gagal (HTTP {$hasil['status']}): " . ($hasil['message'] ?? 'tanpa pesan')
            );
        }

        $kodeCalon = $hasil['result']['Kode_Calon'] ?? null;

        // KODE CALON YANG MENYUSUL.
        //
        // Pendaftaran ke HCLearn saat registrasi sengaja best-effort, jadi ada
        // akun yang belum punya Kode_Calon — dan kandidat itu tertahan begitu
        // hendak dijadwalkan tes, dengan pesan yang membingungkan siapa pun yang
        // membacanya. Bila sinkron ini membuatkan calonnya, kodenya dipungut di
        // sini sehingga kelalaian itu sembuh sendiri tanpa ada yang menyadarinya.
        if ($kodeCalon) {
            $lama = DB::table('N_WEB_CAREERS_Users')->where('Id_Users', $this->userId)->value('Kode_Calon');

            if ($lama !== $kodeCalon) {
                DB::table('N_WEB_CAREERS_Users')
                    ->where('Id_Users', $this->userId)
                    ->update(['Kode_Calon' => $kodeCalon]);

                Log::channel('web_career')->info(
                    "[BIODATA-HRIS] akun #{$this->userId} memperoleh Kode_Calon {$kodeCalon}" . ($lama ? " (sebelumnya {$lama})" : ' — menyusul')
                );
            }
        }

        $ditulis = $hasil['result']['ditulis'] ?? array_keys($payload['Biodata']);
        $diabaikan = $hasil['result']['diabaikan'] ?? [];

        Log::channel('web_career')->info(
            "[BIODATA-HRIS] akun #{$this->userId} tersinkron ({$this->sebab}): " . implode(', ', (array) $ditulis)
            . ($diabaikan ? ' | dilewati HCLearn: ' . implode('; ', (array) $diabaikan) : '')
        );
    }
}
