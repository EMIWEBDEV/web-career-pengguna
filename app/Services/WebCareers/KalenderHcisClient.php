<?php

namespace App\Services\WebCareers;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * WEB CAREERS — KALENDER OPERASIONAL HEAD OFFICE, dari HCIS.
 *
 * Menjawab satu pertanyaan: "tanggal berapa saja kantor HO TIDAK beroperasi?"
 * Jawabannya dipakai SlaMpp untuk menghitung tenggat MPP dalam hari kerja.
 *
 * ── DUA DRIVER, SATU BENTUK JAWABAN ────────────────────────────────────────
 *
 *   http   memanggil HCIS sungguhan — INI YANG DIPAKAI
 *   dummy  membaca storage/app/hcis/kalender-{tahun}.json
 *
 * Bentuk keluarannya SAMA PERSIS, jadi bertukar driver tidak menuntut satu
 * baris pun perubahan di pemanggil.
 *
 * 'dummy' adalah jalan darurat untuk bekerja tanpa jaringan, BUKAN untuk
 * dipakai sehari-hari: tanggalnya perkiraan, dan sudah terbukti meleset dari
 * kalender HCIS yang sebenarnya (Idul Fitri 2026 jatuh 31 Maret, bukan 21 Mei
 * seperti tebakan awal). Menghitung SLA dengan kalender yang salah persis
 * sama merugikannya dengan tidak punya kalender sama sekali.
 *
 * ── KENAPA GAGAL TIDAK BOLEH BERARTI "TIDAK ADA LIBUR" ─────────────────────
 *
 * Kalau HCIS tak terjangkau lalu kelas ini memulangkan daftar kosong, seluruh
 * hari libur hilang dari perhitungan dan tenggat SLA jadi LEBIH KETAT daripada
 * seharusnya — rekruter dinilai terlambat atas hari-hari yang kantornya memang
 * tutup. Karena itu jawaban terakhir yang berhasil disimpan lama (30 hari) dan
 * dipakai kembali saat panggilan gagal. Lihat ambilTahun().
 */
class KalenderHcisClient
{
    /** Singgahan panjang untuk jawaban terakhir yang berhasil (menit). */
    private const TTL_CADANGAN = 60 * 24 * 30;

    /**
     * Tanggal HO tidak beroperasi sepanjang satu tahun.
     *
     * @return array<string, array{tanggal: string, jenis: string, nama: string, status?: string}>
     *         berkunci tanggal 'Y-m-d' — bentuk itu yang dipakai pemanggil untuk
     *         memeriksa satu hari tanpa menelusuri seluruh daftar.
     */
    public static function tahun(int $tahun): array
    {
        // ── KANAL DIMATIKAN ────────────────────────────────────────────────
        //
        // driver 'mati' berarti: JANGAN sentuh HCIS sama sekali. Bukan sekadar
        // "gagal lalu pakai cadangan" — tidak ada panggilan jaringan yang
        // pernah dibuat, tidak ada singgahan yang dibaca atau ditulis.
        //
        // Dipakai selama kanal HCIS belum disepakati: SLA dihitung murni dari
        // HRIS_Hari_Libur, tabel milik basis data ini sendiri. Mengembalikan
        // larik kosong di sini membuat SlaMpp::hariLibur() jatuh sepenuhnya ke
        // tabel lokal, tanpa satu pun cabang khusus di sisi pemanggil.
        //
        // Kenapa saklar, bukan kode yang dihapus: menghidupkannya kembali nanti
        // cukup mengganti satu nilai di .env, dan seluruh jalur HTTP-nya masih
        // utuh berikut penanganan galat & singgahannya.
        if (self::dimatikan()) {
            return [];
        }

        $kunci = 'hcis:kalender:'.self::unit().':'.$tahun;

        $hasil = Cache::remember(
            $kunci,
            (int) config('hcis.kalender.ttl', 1440),
            fn () => self::ambilTahun($tahun),
        );

        return is_array($hasil) ? $hasil : [];
    }

    /**
     * Tanggal HO tidak beroperasi dalam sebuah rentang.
     *
     * Dirakit dari kalender TAHUNAN, bukan dari endpoint rentang. Alasannya:
     * satu perhitungan SLA memeriksa puluhan tanggal, dan menanyakannya
     * per-rentang berarti satu panggilan jaringan untuk tiap perhitungan.
     * Kalender setahun cukup ditarik sekali lalu dipakai berulang.
     */
    public static function rentang(Carbon $dari, Carbon $sampai): array
    {
        if ($sampai->lt($dari)) {
            return [];
        }

        $hasil = [];

        for ($t = (int) $dari->year; $t <= (int) $sampai->year; $t++) {
            foreach (self::tahun($t) as $tgl => $baris) {
                if ($tgl >= $dari->toDateString() && $tgl <= $sampai->toDateString()) {
                    $hasil[$tgl] = $baris;
                }
            }
        }

        ksort($hasil);

        return $hasil;
    }

    /** Apakah tanggal ini hari kerja HO (bukan Minggu, bukan libur)? */
    public static function hariKerja(Carbon $t): bool
    {
        if ($t->isSunday()) {
            return false;
        }

        return ! isset(self::tahun((int) $t->year)[$t->toDateString()]);
    }

    /** Kalender siap dipakai? Dipakai layar untuk menandai SLA yang belum pasti. */
    /**
     * Kanal HCIS sengaja dimatikan?
     *
     * Bawaannya 'mati'. Selama kanalnya belum disepakati, memanggil HCIS
     * berarti setiap perhitungan SLA menunggu jaringan yang pasti gagal —
     * dan kegagalan itu tidak menambah satu pun informasi, sebab hari
     * liburnya sudah lengkap di HRIS_Hari_Libur.
     */
    public static function dimatikan(): bool
    {
        return (string) config('hcis.kalender.driver', 'mati') === 'mati';
    }

    public static function siap(): bool
    {
        if (self::dimatikan()) {
            return false;
        }

        try {
            return self::tahun((int) now()->year) !== [];
        } catch (\Throwable $e) {
            return false;
        }
    }

    // ══ PENGAMBILAN ═══════════════════════════════════════════════════════

    private static function ambilTahun(int $tahun): array
    {
        $cadangan = 'hcis:kalender-cadangan:'.self::unit().':'.$tahun;

        try {
            $mentah = config('hcis.kalender.driver', 'dummy') === 'http'
                ? self::lewatHttp($tahun)
                : self::lewatDummy($tahun);

            $rapi = self::rapikan($mentah);

            // Jawaban yang BERHASIL disimpan terpisah & jauh lebih lama.
            // Inilah yang dipakai saat panggilan berikutnya gagal.
            Cache::put($cadangan, $rapi, self::TTL_CADANGAN);

            return $rapi;
        } catch (\Throwable $e) {
            Log::channel('web_career')->warning('[HCIS-KALENDER] gagal mengambil kalender', [
                'tahun' => $tahun,
                'driver' => config('hcis.kalender.driver'),
                'pesan' => $e->getMessage(),
            ]);

            // Salinan terakhir yang berhasil — lihat catatan kelas.
            return (array) Cache::get($cadangan, []);
        }
    }

    private static function lewatDummy(int $tahun): array
    {
        $path = trim((string) config('hcis.kalender.dummy_path', 'hcis'), '/')
            ."/kalender-{$tahun}.json";

        if (! Storage::disk('local')->exists($path)) {
            // Bukan galat: tahun yang belum disiapkan berkasnya berarti "tidak
            // ada libur yang diketahui", dan itu keadaan yang sah selama
            // endpoint HCIS belum ada.
            Log::channel('web_career')->info("[HCIS-KALENDER] berkas dummy belum ada: {$path}");

            return [];
        }

        $isi = json_decode((string) Storage::disk('local')->get($path), true);

        if (! is_array($isi)) {
            throw new \RuntimeException("Berkas dummy {$path} bukan JSON yang sah.");
        }

        return $isi;
    }

    private static function lewatHttp(int $tahun): array
    {
        $path = '/'.trim((string) config('hcis.prefix', 'api/v1/hcis'), '/')
            .'/'.trim((string) config('hcis.kalender.path_tahun', 'kalender-operasional/tahun'), '/')
            .'/'.$tahun;

        $query = ['unit' => self::unit()];

        $balasan = Http::timeout((int) config('hcis.kalender.timeout', 8))
            ->acceptJson()
            ->withHeaders(self::header('GET', $path, $query))
            ->get(self::baseUrl().$path, $query);

        if (! $balasan->successful()) {
            throw new \RuntimeException(sprintf(
                'HCIS menjawab %d untuk %s — %s',
                $balasan->status(),
                $path,
                mb_substr((string) $balasan->body(), 0, 180),
            ));
        }

        return (array) $balasan->json();
    }

    // ══ BENTUK JAWABAN ════════════════════════════════════════════════════

    /**
     * Ubah response HCIS jadi peta berkunci tanggal.
     *
     * Menerima dua bentuk pembungkus — `{sukses, result:{...}}` sebagaimana
     * kontrak, dan objek `result` telanjang — supaya berkas dummy boleh ditulis
     * ringkas tanpa membuat driver http jadi longgar.
     *
     * Tanggal yang tidak sah DILEWATI, bukan menggagalkan seluruh kalender:
     * satu baris cacat tidak boleh menghapus 30 hari libur lain yang benar.
     */
    private static function rapikan(array $mentah): array
    {
        $hasil = $mentah['result'] ?? $mentah;
        $daftar = $hasil['tanggal_tidak_operasional'] ?? [];

        if (! is_array($daftar)) {
            return [];
        }

        $peta = [];

        foreach ($daftar as $baris) {
            if (! is_array($baris) || empty($baris['tanggal'])) {
                continue;
            }

            try {
                $tgl = Carbon::parse($baris['tanggal'])->toDateString();
            } catch (\Throwable $e) {
                continue;
            }

            $peta[$tgl] = [
                'tanggal' => $tgl,
                'jenis' => (string) ($baris['jenis'] ?? 'LIBUR_NASIONAL'),
                'nama' => (string) ($baris['nama'] ?? 'Hari Libur'),
                'status' => $baris['status'] ?? null,
            ];
        }

        ksort($peta);

        return $peta;
    }

    // ══ UTILITAS ══════════════════════════════════════════════════════════

    private static function unit(): string
    {
        return (string) config('hcis.kalender.unit', 'HEAD_OFFICE');
    }

    private static function baseUrl(): string
    {
        $env = trim((string) config('hcis.env', 'development'));
        $semua = array_filter((array) config('hcis.domains', []));
        $domain = $semua[$env] ?? null;

        if (! $domain) {
            $tersedia = implode(' | ', array_keys($semua)) ?: '(tidak ada satu pun domain terisi)';

            throw new \RuntimeException(
                "HCIS_ENV='{$env}' tidak dikenali atau domainnya belum diisi. Mode yang tersedia: {$tersedia}."
            );
        }

        return rtrim($domain, '/');
    }

    /**
     * Header penandatanganan HCIS.
     *
     * ── TUJUH BARIS KANONIK, URUTANNYA MENGIKAT ───────────────────────────
     *
     *     METHOD 
 PATH 
 QUERY 
 TIMESTAMP 
 NONCE 
 BODY_HASH 
 PUBLIC
     *
     * Ditandatangani HMAC-SHA512 dengan secret key, lalu dikirim lewat lima
     * header X-HC-*. Satu baris tertukar = 401 "Invalid signature", tanpa
     * petunjuk baris mana yang salah — karena itu urutannya ditulis eksplisit
     * di sini dan tidak boleh disusun ulang "supaya rapi".
     *
     * QUERY memakai urutan yang SAMA dengan yang benar-benar dikirim. Query
     * di-ksort() lebih dulu oleh pemanggil, sebab Laravel/Symfony menyusun
     * ulang parameter saat membentuk URL — dan tanda tangan atas urutan yang
     * berbeda dari yang diterima server pasti ditolak. Ini persis jebakan yang
     * pernah menjatuhkan kanal HCLearn.
     *
     * BODY_HASH untuk GET adalah sha256 dari string kosong, bukan string
     * kosong itu sendiri.
     */
    private static function header(string $method, string $path, array $query = []): array
    {
        $public = (string) config('hcis.api_public');
        $secret = (string) config('hcis.api_secret');

        if ($public === '' || $secret === '') {
            throw new \RuntimeException(
                'Kredensial HCIS belum diisi. Set HCIS_WC_API_PUBLIC & HCIS_WC_API_SECRET di .env.'
            );
        }

        ksort($query);

        $timestamp = gmdate('Y-m-d\TH:i:s\Z', time() + self::selisihJam());
        $nonce = bin2hex(random_bytes(16));
        $bodyHash = hash('sha256', '');

        $kanonik = implode("
", [
            strtoupper($method),
            $path,
            $query ? http_build_query($query) : '',
            $timestamp,
            $nonce,
            $bodyHash,
            $public,
        ]);

        return [
            'X-HC-Public' => $public,
            'X-HC-Timestamp' => $timestamp,
            'X-HC-Nonce' => $nonce,
            'X-HC-Body-Hash' => $bodyHash,
            'X-HC-Signature' => hash_hmac('sha512', $kanonik, $secret),
        ];
    }

    /**
     * Selisih jam mesin ini terhadap jam HCIS (detik).
     *
     * ── KENAPA PERLU ──────────────────────────────────────────────────────
     *
     * HCIS menolak permintaan yang timestamp-nya melenceng lebih dari 60 detik
     * dengan 401 "Request expired". Mesin yang jamnya tidak tersinkron — dan
     * itu sudah pernah terjadi di sini, w32time mati, melenceng 78 detik —
     * ditolak SELURUH permintaannya, dan pesannya tidak menyebut jam sama
     * sekali sehingga terbaca seperti kredensial yang salah.
     *
     * Selisihnya diambil dari header `Date` milik HCIS sendiri. Disinggahkan
     * sejam: yang diukur adalah kesalahan jam mesin, bukan sesuatu yang
     * berubah tiap menit.
     *
     * Mesin yang jamnya benar menghasilkan 0 dan tidak terpengaruh apa pun.
     */
    private static function selisihJam(): int
    {
        return (int) Cache::remember('hcis:selisih-jam', 3600, function () {
            try {
                $balasan = Http::timeout(6)->head(self::baseUrl().'/api/docs');
                $date = $balasan->header('Date');

                if (! $date) {
                    return 0;
                }

                $selisih = strtotime($date) - time();

                // Selisih ekstrem berarti jam server yang aneh, bukan jam kita.
                // Mengikutinya justru merusak permintaan yang tadinya benar.
                return abs($selisih) > 86400 ? 0 : $selisih;
            } catch (\Throwable $e) {
                return 0;
            }
        });
    }
}
