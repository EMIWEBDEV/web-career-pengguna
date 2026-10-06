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
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * WEB CAREER — email HASIL LAMARAN (setelah apply) secara ASINKRON.
 *
 * QUEUE TERPISAH 'wc-applymail' — SENGAJA dipisah dari:
 *   - 'wc-syncemailjob' (email verifikasi akun), dan
 *   - 'wc-applyform'    (proses berat apply: tulis DB + berkas GCS).
 * Tujuannya anti-bottleneck: proses apply selesai cepat, dan pengiriman email
 * (server surat bisa lambat/timeout) tidak menahan antrean apply maupun
 * verifikasi. Ketiganya bisa diskalakan / retry sendiri-sendiri.
 *
 * Buat queue di Cloud Tasks: `gcloud tasks queues create wc-applymail`.
 *
 * Status: LOLOS (lolos syarat otomatis), GUGUR (auto-gugur), MENUNGGU (butuh admin).
 *
 * ══ SURATNYA DIKIRIM EVO MAIL SERVER ══════════════════════════════════════
 *
 * Yang berangkat lewat API bukan HTML, melainkan DATA — nama, status, kode,
 * tahap, dan kartu data kandidat. Template beserta judul suratnya ada di sisi
 * server surat, terikat pada kunci yang dipakai.
 *
 * Itu yang membuat kunci yang bocor tidak seberbahaya kredensial SMTP yang
 * bocor: penemunya cuma bisa memicu template yang memang sudah diizinkan, dari
 * alamat yang memang sudah dipakai Web Careers — bukan surat karangan sendiri
 * atas nama perusahaan.
 */
class WcApplyEmailJob implements ShouldQueue
{
    use AntreanWebCareers, CatatGagalWebCareers, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const QUEUE = 'wc-applymail';

    /**
     * Status yang sah. LOLOS/GUGUR = keputusan tahap; MENUNGGU = email saat
     * kandidat baru MELAMAR, bukan hasil keputusan.
     *
     * ══ DIJAGA DI SINI, BUKAN DI SERVER SURAT ═════════════════════════════
     *
     * Server surat memeriksa isian WAJIB, bukan NILAI isian — ia tidak tahu,
     * dan tidak seharusnya tahu, status apa saja yang masuk akal bagi proses
     * rekrutmen Web Careers.
     *
     * Penjagaannya ada karena pernah terjadi: status yang tak dikenali diam-
     * diam jatuh ke 'MENUNGGU', dan surat "Pendaftaranmu sedang ditinjau"
     * terkirim kepada kandidat yang BARU SAJA diloloskan. Salah ketik satu
     * huruf sudah cukup, dan kesalahannya baru ketahuan dari keluhan kandidat.
     *
     * Sekarang ia gagal di KONSTRUKTOR — sebelum job masuk antrean, saat
     * jejak tumpukannya masih menunjuk ke baris yang memanggilnya.
     */
    public const STATUS_SAH = ['LOLOS', 'GUGUR', 'MENUNGGU'];

    /**
     * Batas ukuran foto verifikasi yang ikut disematkan, dalam bytes.
     *
     * Sejalan dengan batas di sisi server surat. Diperiksa di SINI juga supaya
     * berkas yang kelewat besar tidak sempat melintas jaringan sama sekali —
     * mengirim satu megabita untuk kemudian ditolak adalah pemborosan yang
     * berulang di setiap percobaan.
     */
    private const BATAS_FOTO = 1048576;

    public $timeout = 120;

    public $tries = 3;

    public $backoff = 30;

    protected int $userId;

    protected string $status;

    /** ['kode' => .., 'posisi' => .., 'program' => ..]. */
    protected array $data;

    /**
     * Kunci idempotensi — disusun di KONSTRUKTOR, dan itu yang membuatnya
     * bekerja.
     *
     * Ia ikut ter-serialisasi bersama job, jadi percobaan ulang Cloud Tasks
     * membawa kunci yang sama persis dan server surat mengenali kembarannya.
     * Yang ditutup: jaringan putus sesudah suratnya berangkat tapi sebelum
     * jawabannya sampai — tanpa kunci ini kandidat menerima surat penolakan
     * lamaran dua sampai tiga kali.
     *
     * ══ KENAPA ACAK, BUKAN DITURUNKAN DARI KODE LAMARAN ═══════════════════
     *
     * Karena "kirim ulang" memang harus benar-benar mengirim ulang. Admin
     * menekan tombol kirim ulang email feedback justru ketika kandidat melapor
     * suratnya tidak sampai; kunci yang diturunkan dari kode lamaran akan
     * membuat penekanan itu dijawab "sudah pernah dikirim" tanpa satu surat
     * pun berangkat — persis kebalikan dari yang diminta.
     *
     * Acak-per-dispatch memberi keduanya: kebal terhadap percobaan ulang
     * OTOMATIS, tetap patuh pada permintaan kirim ulang yang DISENGAJA.
     */
    protected string $kunci;

    public function __construct(int $userId, string $status, array $data = [])
    {
        if (! in_array($status, self::STATUS_SAH, true)) {
            throw new \InvalidArgumentException(
                "Status email hasil tidak dikenali: '{$status}'. Yang sah: ".implode(', ', self::STATUS_SAH).'.'
            );
        }

        $this->userId = $userId;
        $this->status = $status;
        $this->data = $data;
        $this->kunci = 'hasil:'.$userId.':'.$status.':'.Str::random(16);

        $this->aturAntrean(self::QUEUE);
    }

    public function handle(): void
    {
        Log::info("[APPLYMAIL] job hasil '{$this->status}' MULAI untuk user #{$this->userId}.");

        $user = DB::table('N_WEB_CAREERS_Users')->where('Id_Users', $this->userId)->first();
        // ALAMAT TUJUAN DARI DATABASE, dengan cadangan jawaban formulir.
        //
        // Barisnya dulu berbunyi `if (! $user || ! $user->Email) return;` — dan
        // itu diam-diam membuang surat untuk kandidat yang barisan akunnya sudah
        // tidak ada, padahal lamarannya berjalan dan alamat emailnya terpampang
        // di layar admin (dibaca dari jawaban formulirnya). Yang tertinggal cuma
        // satu baris peringatan yang tak pernah dibaca siapa pun:
        //
        //     [APPLYMAIL] user #185 / email tidak ada — dilewati.
        $kepada = \App\Support\Career\LamaranService::emailKandidat(
            $this->userId,
            null,
            $this->data['kode'] ?? null,
        );

        if (! $kepada) {
            // Tetap dicatat — tapi sekarang ia benar-benar berarti "tak ada alamat
            // di mana pun", bukan sekadar "kolom akunnya kosong".
            Log::warning("[APPLYMAIL] user #{$this->userId} tidak punya alamat email di akun MAUPUN di jawaban formulir — dilewati.");
            Log::channel('web_career')->warning("[APPLYMAIL] tidak ada alamat email untuk user #{$this->userId} — surat '{$this->status}' tidak dikirim.");

            return;
        }

        // Nama pun tak boleh ikut hilang bersama akunnya: surat yang menyapa
        // ruang kosong lebih buruk daripada surat yang tidak jadi dikirim.
        $namaKandidat = $user->Nama ?? ($this->data['nama'] ?? 'Kandidat');

        try {
            app(SuratClient::class)->kirim(
                kepada: $kepada,
                template: 'hasil-lamaran',
                data: [
                    'nama' => $namaKandidat,
                    'status' => $this->status,
                    'kode' => $this->data['kode'] ?? null,
                    'posisi' => $this->data['posisi'] ?? null,
                    'program' => $this->data['program'] ?? null,

                    // Konteks tahap — membuat surat yang sama dipakai lintas
                    // tahap (Seleksi Administrasi, Psikotes, Wawancara, dst.).
                    'tahap_lolos' => $this->data['tahapLolos'] ?? null,
                    'tahap_berikut' => $this->data['tahapBerikut'] ?? null,
                    'urutan' => $this->data['urutan'] ?? null,
                    'total' => $this->data['total'] ?? null,
                    'diterima' => (bool) ($this->data['diterima'] ?? false),

                    // Kartu data kandidat.
                    'email' => $kepada,
                    'tgl_lahir' => self::tanggal($this->data['tglLahir'] ?? null),
                    'kampus' => $this->data['kampus'] ?? null,
                    'jenis_kelamin' => $this->data['jkel'] ?? null,
                    // Nomor dari formulir yang dipakai; bila kosong, jatuh ke
                    // nomor akun supaya kartu datanya tidak berlubang.
                    'hp' => self::rapikanHp($this->data['hp'] ?? $user?->No_Hp ?? null),
                    'foto_base64' => $this->foto(),

                    'feedback_url' => $this->data['feedbackUrl'] ?? null, // [feat/feedback]

                    // Catatan UNTUK KANDIDAT yang ditulis admin saat memutus tahap
                    // (mis. link Zoom psikotes). TEKS, bukan HTML — lihat
                    // CatatanEksternal::keTeksSurat(). Server surat versi lama
                    // mengabaikan isian yang belum ia kenal, jadi aman dikirim
                    // sebelum templatnya diperbarui.
                    'catatan' => $this->data['catatan'] ?? null,
                ],
                kunciIdempotensi: $this->kunci,
            );

            Log::info("[APPLYMAIL] hasil '{$this->status}' terkirim ke {$kepada} (user #{$this->userId}, kode ".($this->data['kode'] ?? '-').').');
            Log::channel('web_career')->info("[APPLYMAIL] hasil '{$this->status}' terkirim ke {$kepada}.");
        } catch (\Throwable $e) {
            Log::error("[APPLYMAIL] hasil '{$this->status}' ke {$kepada} GAGAL (percobaan {$this->attempts()}/{$this->tries}): ".$e->getMessage());

            // Percobaan terakhir → catat ke tabel WC & selesai (tak masuk N_LMS_Failed_Jobs).
            if ($this->attempts() >= $this->tries) {
                $this->catatGagalWc('APPLYMAIL:'.$this->status, json_encode(['userId' => $this->userId, 'kode' => $this->data['kode'] ?? null]), $e);

                return;
            }

            throw $e; // masih ada sisa percobaan → retry
        }
    }

    /**
     * Foto verifikasi dari GCS, sudah dalam base64 dan siap dikirim.
     *
     * Bytes-nya TIDAK PERNAH lewat antrean — yang dibawa payload cuma
     * path-nya, dan berkasnya baru diambil saat job benar-benar berjalan.
     *
     * Gagal ambil, terlalu besar, atau memang tidak ada → null, dan suratnya
     * tetap berangkat memakai inisial nama. Kartu datanya sudah dirancang
     * untuk itu; membatalkan surat pemberitahuan hasil lamaran hanya karena
     * hiasannya tidak terambil adalah pertukaran yang salah.
     */
    private function foto(): ?string
    {
        $path = $this->data['fotoPath'] ?? null;
        if (! $path) {
            return null;
        }

        try {
            $bytes = Storage::disk('gcs')->get($path);
        } catch (\Throwable $e) {
            Log::warning('[APPLYMAIL] foto verifikasi gagal diambil dari GCS: '.$e->getMessage());

            return null;
        }

        if (! $bytes) {
            return null;
        }

        if (strlen($bytes) > self::BATAS_FOTO) {
            Log::warning(sprintf(
                '[APPLYMAIL] foto verifikasi %d bytes melampaui batas %d — surat dikirim tanpa foto.',
                strlen($bytes),
                self::BATAS_FOTO,
            ));

            return null;
        }

        return base64_encode($bytes);
    }

    /**
     * "21 Jul 2026".
     *
     * Diformat DI SINI, bukan di server surat. Server surat merender template
     * dan tidak punya penapis tanggal — dan memang tidak seharusnya punya:
     * yang tahu bahwa kolom ini tanggal lahir, bukan sekadar teks, adalah
     * layanan yang menyimpannya.
     *
     * Nilai yang tak bisa diurai diteruskan apa adanya — lebih baik apa adanya
     * daripada hilang.
     */
    private static function tanggal(?string $nilai): ?string
    {
        if (empty($nilai)) {
            return null;
        }

        try {
            return Carbon::parse($nilai)->format('d M Y');
        } catch (\Throwable $e) {
            return $nilai;
        }
    }

    /**
     * Rapikan nomor telepon jadi "+62 812-3456-7890".
     *
     * Tersimpan apa adanya sesuai ketikan kandidat (mis. "6282269362834"),
     * yang kalau ditampilkan mentah terbaca seperti deretan angka acak.
     * Bentuk yang tak dikenali dibiarkan utuh — lebih baik apa adanya
     * daripada dipotong salah.
     */
    private static function rapikanHp(?string $hp): ?string
    {
        $angka = preg_replace('/\D+/', '', (string) $hp);
        if (! $angka) {
            return null;
        }

        if (str_starts_with($angka, '0')) {
            $angka = '62'.substr($angka, 1);
        }
        if (! str_starts_with($angka, '62')) {
            return $hp;
        }

        // Pola baca nomor seluler Indonesia: 3 digit kode operator, lalu
        // kelompok 4 — "+62 822-6936-2834", bukan "+62 8226-9362-834".
        $sisa = substr($angka, 2);
        $blok = rtrim(chunk_split(substr($sisa, 3), 4, '-'), '-');

        return trim('+62 '.substr($sisa, 0, 3).($blok ? '-'.$blok : ''));
    }
}
