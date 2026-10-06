<?php

namespace App\Services\Surat;

use App\Exceptions\Career\SuratGagal;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Klien HTTP ke EVO Mail Server.
 *
 * ══ SATU-SATUNYA JALAN KELUAR SURAT ═══════════════════════════════════════
 *
 * Web Careers sudah TIDAK punya kredensial SMTP, tidak punya berkas Blade
 * surat, dan tidak lagi memanggil Mail:: di mana pun. Seluruh surat kandidat —
 * verifikasi akun, OTP reset, pemberitahuan ganti sandi, hasil lamaran, dan
 * undangan jadwal — berangkat lewat kelas ini.
 *
 * Skema tanda tangannya SAMA PERSIS dengan App\Helpers\SignatureHelper milik
 * Web Careers (yang dipakai memanggil HCLearn) — header X-HC-*, HMAC-SHA512,
 * urutan payload yang sama. Kalau suatu saat SignatureHelper dirapikan supaya
 * bisa dipakai lintas kanal, berkas ini tinggal memanggilnya.
 *
 * ══ TIDAK ADA PILIHAN ALAMAT PENGIRIM DI SINI ═════════════════════════════
 *
 * Perhatikan bahwa tidak ada parameter "dari" atau "pengirim". Itu bukan
 * kelalaian: alamat pengirim ditentukan oleh KUNCI, di sisi server surat.
 *
 * Artinya kalau kunci ini bocor, yang bisa dilakukan penemunya cuma mengirim
 * template yang memang sudah diizinkan, dari alamat yang sudah dipakai Web
 * Careers — bukan surat sembarangan dari alamat direksi.
 */
class SuratClient
{
    public function __construct(
        private ?string $basis = null,
        private ?string $public = null,
        private ?string $secret = null,
    ) {
        $this->basis = rtrim($basis ?? (string) config('surat.basis'), '/');
        $this->public ??= (string) config('surat.api_public');
        $this->secret ??= (string) config('surat.api_secret');
    }

    /**
     * Kirim satu surat.
     *
     * Templatenya ada di JALUR, bukan di muatan — itu yang membuat izin
     * per-template di server surat bermakna.
     *
     * ══ ISI $kunciIdempotensi. SELALU. ═══════════════════════════════════
     *
     * Jaringan gagal di tempat paling merepotkan: SESUDAH server surat
     * mengirim, SEBELUM jawabannya sampai ke sini. Job ini menyimpulkan
     * gagal, Cloud Tasks mengulangnya, dan kandidat menerima surat penolakan
     * lamaran DUA KALI.
     *
     * Kunci yang STABIL untuk satu pekerjaan yang sama menutup itu: server
     * surat mengenali permintaan kembar dan mengembalikan jawaban yang
     * pertama tanpa mengirim apa pun lagi.
     *
     * "Stabil" berarti kunci yang sama lahir lagi saat job-nya diulang —
     * jadi susun dari pengenal yang memang tetap:
     *
     *     'verif:'.$kandidatId.':'.$tokenId
     *     'hasil:'.$lamaranTahapId
     *
     * JANGAN dari waktu, angka acak, atau uniqid(): kunci yang berbeda di
     * tiap percobaan sama saja dengan tidak memakai kunci sama sekali.
     *
     * @param  string|array  $kepada
     * @return array|null  null bila gagal (sudah dicatat ke log)
     */
    public function kirim(
        string|array $kepada,
        string $template,
        array $data = [],
        ?string $kunciIdempotensi = null,
    ): ?array {
        $muatan = [
            'kepada' => $kepada,
            'data' => $data,
        ];

        if ($kunciIdempotensi !== null && $kunciIdempotensi !== '') {
            $muatan['kunci_idempotensi'] = $kunciIdempotensi;
        }

        return $this->panggil('POST', '/api/v1/surat/kirim/'.$template, $muatan);
    }

    /**
     * Template apa saja yang dikenali server.
     *
     * Berguna saat menyiapkan, bukan saat mengirim — jangan dipanggil per
     * surat.
     */
    public function template(): ?array
    {
        return $this->panggil('GET', '/api/v1/surat/template');
    }

    /**
     * Panggilan mentah, bertanda tangan.
     *
     * ══ MELEMPAR SAAT GAGAL — DAN ITU DISENGAJA ═══════════════════════════
     *
     * Seluruh pemanggilnya adalah job antrean yang sudah punya mesin percobaan
     * ulang beserta pencatatan kegagalannya sendiri. Melempar berarti
     * kegagalan itu MASUK ke jalur tersebut: diulang beberapa kali, lalu
     * tercatat di N_WEB_CAREERS_Failed_Jobs kalau tetap gagal.
     *
     * Mengembalikan null diam-diam terlihat lebih sopan, tapi akibatnya jauh
     * lebih buruk: kunci yang salah ketik atau izin template yang belum
     * diberikan menghasilkan surat yang tidak pernah berangkat, TANPA satu pun
     * jejak kegagalan. Yang pertama tahu kandidatnya sendiri, berminggu-minggu
     * kemudian.
     *
     * Yang TIDAK dilempar cuma satu: 409, yang artinya permintaan kembar
     * sedang dikerjakan saat itu juga. Itu bukan kegagalan.
     *
     * @return array|null  null hanya bila panggilannya memang sengaja dilewati
     *
     * @throws SuratGagal
     */
    public function panggil(string $metode, string $jalur, array $muatan = []): ?array
    {
        if (blank($this->basis) || blank($this->public) || blank($this->secret)) {
            /*
             * Konfigurasi yang belum lengkap ikut dilempar.
             *
             * Ia satu-satunya kegagalan yang PASTI mengenai seluruh surat,
             * bukan satu-dua. Melewatinya diam-diam berarti sebuah deploy yang
             * lupa mengisi SURAT_API_SECRET berjalan mulus, melayani
             * pendaftaran seperti biasa, dan tidak mengirim apa pun.
             */
            throw new SuratGagal('Konfigurasi server surat belum lengkap — SURAT_BASIS / SURAT_API_PUBLIC / SURAT_API_SECRET.');
        }

        $metode = strtoupper($metode);

        /*
         * Badan permintaan di-encode SEKALI, lalu string yang sama itu
         * di-hash DAN dikirim mentah.
         *
         * Godaannya besar untuk memakai Http::post($url, $array) dan meng-hash
         * json_encode($array) secara terpisah. Itu bekerja — sampai Guzzle
         * meng-encode dengan opsi yang sedikit berbeda (escape garis miring,
         * urutan kunci, unicode). Hasilnya 401 "Body Hash Invalid", galat yang
         * menunjuk ke arah sama sekali salah.
         */
        $body = $muatan === [] ? '' : json_encode($muatan);
        $bodyHash = hash('sha256', $body);

        // date('c') — ISO-8601 LENGKAP DENGAN OFFSET, bukan 'Y-m-d H:i:s'.
        // Cap waktu tanpa zona dibaca server memakai zona waktu MESINNYA:
        // Cloud Run di UTC, server surat di Asia/Jakarta, selisih tujuh jam,
        // dan jawabannya selalu "Request Expired".
        $timestamp = date('c');
        $nonce = Str::random(32);

        $payload = implode("\n", [
            $metode,
            $jalur,
            '',            // query — kanal ini tidak memakainya
            $timestamp,
            $nonce,
            $bodyHash,
            $this->public,
        ]);

        try {
            $permintaan = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'X-HC-Public' => $this->public,
                'X-HC-Timestamp' => $timestamp,
                'X-HC-Nonce' => $nonce,
                'X-HC-Body-Hash' => $bodyHash,
                'X-HC-Signature' => hash_hmac('sha512', $payload, $this->secret),
            ])
                // Server surat mengirim SERENTAK, jadi batas waktunya harus
                // memberi ruang untuk satu jabat tangan SMTP penuh.
                ->timeout((int) config('surat.timeout', 25))
                ->connectTimeout(5)
                ->withBody($body, 'application/json');

            $jawab = $permintaan->send($metode, $this->basis.$jalur);
        } catch (\Throwable $e) {
            /*
             * Jaringan putus, DNS gagal, batas waktu habis.
             *
             * Justru di sini kunci idempotensi membuktikan gunanya: suratnya
             * BOLEH JADI sudah berangkat — putusnya bisa terjadi sesudah
             * server surat mengirim, sebelum jawabannya sampai. Percobaan
             * ulang berikutnya membawa kunci yang sama, dan server surat
             * mengembalikan jawaban yang pertama tanpa mengirim apa pun lagi.
             */
            throw new SuratGagal('Tidak bisa menghubungi server surat: '.$e->getMessage(), previous: $e);
        }

        $isi = $jawab->json();

        /*
         * 409 = permintaan kembar SEDANG diproses server surat saat ini juga.
         *
         * Bukan kegagalan, dan bukan pula alasan mengulang: yang kembar itu
         * akan menyelesaikan pekerjaannya sendiri. Dicatat sebagai info supaya
         * tidak ikut terhitung sebagai galat di papan pemantauan.
         */
        if ($jawab->status() === 409) {
            Log::info('[SURAT] permintaan kembar sedang diproses — dilewati', [
                'jalur' => $jalur,
                'pesan' => $isi['message'] ?? null,
            ]);

            return null;
        }

        if (! $jawab->successful() || ! ($isi['success'] ?? false)) {
            /*
             * Kalimat aslinya diteruskan apa adanya. Ia yang membedakan
             * "Domain penerima tidak ada dalam daftar putih" dari "Endpoint
             * ini tidak diizinkan untuk aplikasi Anda" dari "535
             * authentication failed" — dan ketiganya menuntut tindakan yang
             * sama sekali berbeda.
             */
            throw new SuratGagal(sprintf(
                'Server surat menolak (HTTP %d): %s',
                $jawab->status(),
                $isi['message'] ?? Str::limit((string) $jawab->body(), 200),
            ));
        }

        /*
         * Header ini menyatakan bahwa suratnya TIDAK dikirim ulang — yang
         * dikembalikan jawaban pengiriman sebelumnya. Dicatat supaya saat
         * menelusuri nanti terlihat bedanya antara "terkirim" dan "sudah
         * pernah terkirim".
         */
        if ($jawab->header('X-Idempotent-Replay') === 'true') {
            Log::info('[SURAT] ulangan — surat sudah pernah dikirim sebelumnya', [
                'jalur' => $jalur,
                'message_id' => $isi['result']['message_id'] ?? null,
            ]);
        }

        return $isi['result'] ?? [];
    }
}
