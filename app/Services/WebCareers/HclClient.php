<?php

namespace App\Services\WebCareers;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * WEB CAREERS → HCLEARN/CAT — client HMAC-SHA512.
 *
 * SEMUA panggilan berjalan di sisi SERVER. Domain, Api_Public, dan Api_Secret tidak
 * pernah dikirim ke browser: Vue memanggil endpoint internal Web Careers, controller
 * internal itu yang memakai client ini. Jangan pernah menembak domain CAT dari axios.
 *
 * Tanda tangan mengikuti App\Http\Middleware\ApiCredentialValidator di project CAT:
 *   stringToSign = METHOD \n path \n query \n timestamp \n nonce \n bodyHash \n publicKey
 *   signature    = hash_hmac('sha512', stringToSign, Api_Secret)
 *
 * CATATAN timestamp: middleware memakai strtotime(), jadi WAJIB berupa tanggal yang
 * bisa diurai (ISO 8601) — bukan angka unix. Toleransi selisih 60 detik.
 */
class HclClient
{
    public function get(string $path, array $query = [], array $konteksLog = []): array
    {
        return $this->kirim('GET', $path, [], $query, $konteksLog);
    }

    public function post(string $path, array $body = [], array $konteksLog = []): array
    {
        return $this->kirim('POST', $path, $body, [], $konteksLog);
    }

    public function put(string $path, array $body = [], array $konteksLog = []): array
    {
        return $this->kirim('PUT', $path, $body, [], $konteksLog);
    }

    /**
     * PATCH — perubahan SEBAGIAN.
     *
     * Dibedakan dari PUT bukan demi kerapian istilah: pada sinkron biodata,
     * field yang TIDAK disebut harus dibiarkan apa adanya, sementara PUT
     * berarti "inilah seluruh isinya". Rutenya di CAT pun terdaftar per metode
     * (whitelist endpoint), jadi mengirim PUT ke rute PATCH akan ditolak 403 —
     * penolakan yang terbaca persis seperti kredensial salah.
     */
    public function patch(string $path, array $body = [], array $konteksLog = []): array
    {
        return $this->kirim('PATCH', $path, $body, [], $konteksLog);
    }

    public function delete(string $path, array $konteksLog = []): array
    {
        return $this->kirim('DELETE', $path, [], [], $konteksLog);
    }

    /**
     * Domain CAT untuk mode yang sedang dipakai (HCLEARN_ENV).
     *
     * Pesan galatnya menyebut mode yang salah DAN daftar mode yang tersedia.
     * Salah ketik satu huruf pada HCLEARN_ENV dulu hanya berujung "domain belum
     * diatur", yang membuat orang mencari-cari di config alih-alih di .env.
     */
    public function baseUrl(): string
    {
        $env = trim((string) config('hclearn.env', 'development'));
        $semua = array_filter((array) config('hclearn.domains', []));
        $domain = $semua[$env] ?? null;

        if (! $domain) {
            $tersedia = implode(' | ', array_keys($semua)) ?: '(tidak ada satu pun domain terisi)';

            throw new \RuntimeException(
                "HCLEARN_ENV='{$env}' tidak dikenali atau domainnya belum diisi. Mode yang tersedia: {$tersedia}."
            );
        }

        return rtrim($domain, '/');
    }

    /**
     * Admin yang atas namanya panggilan ini dilakukan — dipakai saat TIDAK ADA
     * SESI, yaitu di dalam antrean.
     */
    private ?int $penggunaId = null;

    /**
     * Bertindak atas nama seorang admin, tanpa bergantung pada sesi.
     *
     * ══ KENAPA PERLU ══
     *
     * Penjadwalan ujian dikerjakan di ANTREAN (WcPenjadwalanJob): menerbitkan
     * ratusan token bisa memakan menit, jadi permintaannya sengaja dilepas.
     * Tapi job berjalan jauh setelah permintaan aslinya selesai — tanpa sesi
     * siapa pun. Akibatnya kunci pengguna tak pernah ikut terkirim, dan CAT
     * menolak dengan "Akun Anda belum ditautkan" untuk akun yang SEBENARNYA
     * sudah ditautkan.
     *
     * Yang dibawa job cukup ID-nya, bukan kuncinya. Dua alasan: kunci tidak
     * ikut mengendap di tabel antrean, dan pencabutan tautan langsung berlaku
     * bahkan untuk job yang sudah telanjur mengantre.
     */
    public function sebagaiPengguna(?int $userId): static
    {
        $this->penggunaId = $userId && $userId > 0 ? $userId : null;

        return $this;
    }

    /**
     * Kunci HCLearn milik admin di balik panggilan ini — null bila tak ada.
     *
     * Null itu WAJAR untuk sebagian panggilan: pendaftaran kandidat dan sinkron
     * biodata memang tidak berbicara atas nama seorang admin, dan endpoint yang
     * dituju pun tidak menuntutnya.
     *
     * Yang MENUNTUT kunci ini — daftar paket ujian dan penjadwalan — akan
     * ditolak CAT bila kuncinya tidak ada. Ditolak di sana, bukan di sini:
     * pengaman yang hanya berlaku di sisi peminta bukan pengaman.
     */
    private function kunciPengguna(): ?string
    {
        // Yang disebut eksplisit menang atas sesi: di dalam antrean sesinya
        // memang tidak ada, dan di luar antrean keduanya selalu orang yang sama.
        $id = $this->penggunaId ?: (int) session('career_auth.id');

        if ($id < 1) {
            return null;
        }

        // Dibaca sekali per proses. Satu permintaan bisa memanggil CAT beberapa
        // kali (mis. penjadwalan massal), dan mengulang kueri yang sama untuk
        // orang yang sama tak menambah apa pun.
        static $cache = [];

        return $cache[$id] ??= (function () use ($id) {
            $kunci = DB::table('N_WEB_CAREERS_Users')->where('Id_Users', $id)->value('User_Key');

            return is_string($kunci) && trim($kunci) !== '' ? trim($kunci) : null;
        })();
    }

    /**
     * @return array{sukses:bool, status:int, message:string, result:mixed}
     */
    private function kirim(string $metode, string $path, array $body, array $query, array $konteksLog): array
    {
        $publicKey = config('hclearn.api_public');
        $secret = config('hclearn.api_secret');

        if (! $publicKey || ! $secret) {
            throw new \RuntimeException('Kredensial HCLearn (HCLEARN_WC_API_*) belum diatur di .env.');
        }

        $prefix = trim(config('hclearn.prefix', 'api/v1/web-careers'), '/');
        $jalur = '/' . $prefix . '/' . ltrim($path, '/');
        $url = $this->baseUrl() . $jalur;

        // Body harus string mentah yang SAMA persis dengan yang dikirim, karena
        // server menghitung ulang sha256 dari raw body.
        $rawBody = $body === [] ? '' : json_encode($body);

        // -- KUNCI QUERY WAJIB DIURUTKAN ALFABETIS ------------------------
        //
        // Yang menandatangani di sisi CAT adalah Request::getQueryString()
        // milik Symfony, dan method itu MENGURUTKAN parameternya. Kalau kita
        // menyusunnya menurut urutan array PHP, dua sisi menghitung tanda
        // tangan atas string berbeda untuk permintaan yang sama persis:
        //
        //   klien  : tahap=178&lamaran=594
        //   server : lamaran=594&tahap=178   -> 401 Invalid Signature
        //
        // Tidak pernah terlihat selama ini karena seluruh panggilan sebelumnya
        // berupa GET tanpa query atau POST berbadan JSON. Diurutkan di sini
        // supaya penambah parameter berikutnya tidak perlu tahu urusan ini.
        ksort($query);

        $queryString = $query === [] ? '' : http_build_query($query);
        $bodyHash = hash('sha256', $rawBody);

        $maksPercobaan = max(1, (int) config('hclearn.retry', 2));
        $nonce = '';
        $terakhir = null;

        // Retry ditangani MANUAL, bukan lewat Http::retry(). Setiap percobaan WAJIB
        // memakai nonce + timestamp + tanda tangan BARU; mengulang dengan nonce yang
        // sama akan ditolak server sebagai "Duplicate Request" (anti-replay).
        for ($percobaan = 1; $percobaan <= $maksPercobaan; $percobaan++) {
            $timestamp = now()->toIso8601String();
            $nonce = (string) Str::uuid();

            $stringToSign = implode("\n", [
                strtoupper($metode),
                $jalur,
                $queryString,
                $timestamp,
                $nonce,
                $bodyHash,
                $publicKey,
            ]);

            $headers = [
                'X-HC-Public' => $publicKey,
                'X-HC-Signature' => hash_hmac('sha512', $stringToSign, $secret),
                'X-HC-Timestamp' => $timestamp,
                'X-HC-Nonce' => $nonce,
                'X-HC-Body-Hash' => $bodyHash,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ];

            // ── SIAPA YANG SEDANG MEMINTA ────────────────────────────────────
            //
            // Kredensial di atas menjawab "aplikasi mana", bukan "orang mana".
            // Tanpa yang kedua, CAT tak punya dasar mempersempit apa pun:
            // daftar paket ujian keluar seluruhnya, dan ujian internal —
            // asesmen promosi karyawan — bisa dijadwalkan kepada pelamar luar.
            //
            // Kunci ini milik AKUN, bukan milik kanal. Yang berlaku adalah izin
            // orang yang sedang masuk, dan jejaknya menyebut namanya — bukan
            // "WEB_CAREERS" yang tak bisa ditanyai siapa pun.
            //
            // TIDAK ikut ditandatangani, dan itu disengaja: tanda tangan
            // menjaga keaslian permintaan, sedangkan kunci ini menjawab
            // pertanyaan berbeda — dan CAT tetap memeriksanya sendiri ke tabel
            // penggunanya, jadi kunci palsu tidak membuka apa pun.
            if ($kunciPengguna = $this->kunciPengguna()) {
                $headers['X-HC-User-Key'] = $kunciPengguna;
            }

            $mulai = microtime(true);

            try {
                $permintaan = Http::withHeaders($headers)->timeout((int) config('hclearn.timeout', 30));

                $respons = match (strtoupper($metode)) {
                    'GET' => $permintaan->get($url . ($queryString ? '?' . $queryString : '')),
                    'DELETE' => $permintaan->delete($url),
                    default => $permintaan->withBody($rawBody, 'application/json')->send(strtoupper($metode), $url),
                };

                $durasi = (int) ((microtime(true) - $mulai) * 1000);
                $json = $respons->json();
                $sukses = $respons->successful();

                $this->catat($metode, $jalur, $queryString, $rawBody, $respons->body(), $respons->status(), $nonce, $durasi, $sukses, $konteksLog);

                $pesan = $json['message'] ?? ($sukses ? 'Berhasil' : 'Permintaan ke HCLearn gagal');

                $terakhir = [
                    'sukses' => $sukses,
                    'status' => $respons->status(),
                    'message' => $sukses ? $pesan : $this->jelaskan($pesan, $timestamp, $respons),
                    'result' => $json['result'] ?? $json ?? null,
                ];

                // Ulangi HANYA untuk gangguan sementara (5xx). Balasan 4xx adalah
                // keputusan server — mengulanginya percuma dan menyesatkan.
                if ($sukses || $respons->status() < 500) {
                    return $terakhir;
                }
            } catch (\Throwable $e) {
                $durasi = (int) ((microtime(true) - $mulai) * 1000);

                $this->catat($metode, $jalur, $queryString, $rawBody, null, 0, $nonce, $durasi, false, $konteksLog, $e->getMessage());

                Log::channel('web_career')->error("[HCL] {$metode} {$jalur} percobaan {$percobaan} gagal: " . $e->getMessage());

                $terakhir = [
                    'sukses' => false,
                    'status' => 500,
                    'message' => 'Tidak dapat menghubungi HCLearn: ' . $e->getMessage(),
                    'result' => null,
                ];
            }

            if ($percobaan < $maksPercobaan) {
                usleep(500000);
            }
        }

        return $terakhir;
    }

    /**
     * Terjemahkan galat HCLearn yang singkat menjadi sebab + tindakan.
     *
     * Pesan seperti "Request Expired" benar secara teknis tapi tidak memberi
     * tahu apa pun kepada orang yang membacanya di layar admin. Yang ditambahkan
     * di sini bukan tebakan: selisih jamnya dihitung dari jam server HCLearn
     * yang ikut dikirim pada balasan.
     */
    private function jelaskan(string $pesan, string $timestampKirim, Response $respons): string
    {
        $ringkas = strtolower(trim($pesan));

        if (str_contains($ringkas, 'request expired')) {
            $jamServer = $this->jamServer($respons);
            $selisih = $jamServer ? (strtotime($timestampKirim) - $jamServer) : null;
            $rinci = $selisih === null
                ? 'Jam mesin ini berbeda lebih dari 60 detik dari server HCLearn.'
                : sprintf('Jam mesin ini %s %d detik dari server HCLearn (batas toleransi 60 detik).',
                    $selisih > 0 ? 'LEBIH CEPAT' : 'LEBIH LAMBAT', abs($selisih));

            return "Request Expired — {$rinci} Permintaan ditandatangani dengan waktu, "
                . 'jadi selisih sebesar itu dianggap permintaan basi. Perbaiki jam server: '
                . 'Windows `w32tm /resync /force`, Linux `sudo chronyc makestep` atau `sudo ntpdate -u pool.ntp.org`.';
        }

        if (str_contains($ringkas, 'duplicate request')) {
            return "{$pesan} — permintaan dengan nonce yang sama pernah dikirim. Ulangi dari awal, jangan kirim ulang permintaan yang sama.";
        }

        // "Non Official" = tidak ada baris N_HRIS_KANDIDAT_Api_Aplikasi dengan
        // Api_Public ini DAN Flag_Aktif='Y'. Tiap lingkungan CAT punya database
        // sendiri, jadi kredensial yang jalan di lokal belum tentu ada di staging.
        if (str_contains($ringkas, 'non official')) {
            return "{$pesan} — HCLEARN_WC_API_PUBLIC tidak terdaftar/aktif di CAT lingkungan '"
                . config('hclearn.env') . "'. Minta tim CAT memastikan ada baris N_HRIS_KANDIDAT_Api_Aplikasi "
                . "dengan Api_Public & Api_Secret yang sama dan Flag_Aktif='Y' di database lingkungan itu.";
        }

        if (str_contains($ringkas, 'signature') && ! str_contains($ringkas, 'body hash')) {
            return "{$pesan} — tanda tangan HMAC tidak cocok. Api_Secret di sini berbeda dengan yang tersimpan di CAT lingkungan '"
                . config('hclearn.env') . "'.";
        }

        if (str_contains($ringkas, 'body hash')) {
            return "{$pesan} — isi permintaan berubah di tengah jalan (proxy/gateway mengubah body). "
                . 'Bukan salah kredensial.';
        }

        if (str_contains($ringkas, 'security incomplete')) {
            return "{$pesan} — ada header X-HC-* yang tidak sampai ke CAT. Biasanya proxy/CDN membuang header kustom.";
        }

        return $pesan;
    }

    /** Jam server HCLearn (epoch) dari header Date balasan; null bila tak ada. */
    private function jamServer(Response $respons): ?int
    {
        $date = $respons->header('Date');

        return $date ? (strtotime($date) ?: null) : null;
    }

    /**
     * Jejak panggilan ke N_WEB_CAREERS_Integrasi_Log.
     * Payload penuh HANYA disimpan saat gagal (atau bila sengaja dinyalakan) — panggilan
     * sukses cukup ringkasan + hash, supaya tabel tidak membengkak oleh data pribadi.
     */
    private function catat(
        string $metode,
        string $jalur,
        string $queryString,
        string $rawBody,
        ?string $responsBody,
        int $status,
        string $nonce,
        int $durasi,
        bool $sukses,
        array $konteksLog,
        ?string $pesanError = null,
    ): void {
        try {
            $simpanPayload = ! $sukses || config('hclearn.log_payload_sukses', false);

            DB::table('N_WEB_CAREERS_Integrasi_Log')->insert([
                'Arah' => 'KELUAR',
                'Jenis_Event' => $konteksLog['Jenis_Event'] ?? null,
                'Metode' => strtoupper($metode),
                'Endpoint' => $jalur . ($queryString ? '?' . $queryString : ''),
                'Penjadwalan_Id' => $konteksLog['Penjadwalan_Id'] ?? null,
                'Penjadwalan_Tahap_Id' => $konteksLog['Penjadwalan_Tahap_Id'] ?? null,
                'Penjadwalan_Peserta_Id' => $konteksLog['Penjadwalan_Peserta_Id'] ?? null,
                'Id_Ujian_Token' => $konteksLog['Id_Ujian_Token'] ?? null,
                'Http_Status' => $status,
                'Ringkasan' => Str::limit($pesanError ?? ($sukses ? 'OK' : 'Gagal'), 480),
                'Payload_Hash' => hash('sha256', $rawBody),
                'Request_Json' => $simpanPayload ? $rawBody : null,
                'Response_Json' => $simpanPayload ? Str::limit((string) $responsBody, 60000) : null,
                'Nonce' => $nonce,
                'Durasi_Ms' => $durasi,
                'Flag_Sukses' => $sukses ? 'Y' : 'T',
                'Pesan_Error' => $pesanError ? Str::limit($pesanError, 480) : null,
                'Kedaluwarsa_At' => now()->addDays((int) config('hclearn.log_retensi_hari', 30)),
                'Created_At' => now(),
                'Created_By_Id' => session('career_auth.id'),
            ]);
        } catch (\Throwable $e) {
            Log::channel('web_career')->warning('[HCL] gagal menulis Integrasi_Log: ' . $e->getMessage());
        }
    }
}
