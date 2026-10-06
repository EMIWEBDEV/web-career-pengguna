<?php

namespace App\Support\Career;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * WEB CAREERS — PEMBUKUAN KURSI SEBUAH MPP.
 *
 * ══ KENAPA KURSI PUNYA PEMILIK BARU ═════════════════════════════════════════
 *
 * Satu MPP boleh dibuka di beberapa program — dan memang harus, karena satu
 * program cuma sanggup memegang satu alur seleksi. Tapi pembukuan kursinya
 * selama ini hidup di N_WEB_CAREERS_Program_Posisi: satu baris per program.
 *
 * Akibatnya MPP berencana 15 yang dibuka di dua program punya DUA pembukuan
 * yang tidak saling kenal. Program pertama boleh menerima 15; program kedua
 * boleh menerima 15 lagi. Tiga puluh orang diterima atas persetujuan yang
 * menyebut lima belas — tanpa satu peringatan pun, sebab gerbangnya mengunci
 * dan menghitung per baris loker.
 *
 * Kelas ini memberi kuota satu pemilik: N_WEB_CAREERS_Mpp_Kursi, satu baris
 * per MPP.
 *
 * ══ KENAPA SATU BARIS LEDGER, BUKAN SEKADAR SUM() SAAT MEMUTUSKAN ═══════════
 *
 * Karena KUNCINYA. Menghitung lalu memutuskan tanpa kunci adalah lomba yang
 * pasti kalah pada kursi terakhir. Tapi mengunci SELURUH baris loker milik MPP
 * itu — yang tersebar di beberapa program — membuat dua rekruter yang mengambil
 * barisnya dalam urutan berbeda saling tunggu (deadlock), bukan antre.
 *
 * Satu baris = satu titik serialisasi. Itu pola baku, dan satu-satunya cara
 * benar untuk "kursi terakhir diperebutkan dua program".
 *
 * ══ TIGA ANGKA, TIGA ARTI YANG BERBEDA ══════════════════════════════════════
 *
 *   Kuota         rencana yang DISETUJUI (HRIS_Transaksi_GForm). Inilah yang
 *                 berwenang — bukan jumlah kuota loker, yang bisa dikarang
 *                 lebih besar oleh siapa pun yang membuka program.
 *
 *   Dialokasikan  Σ kuota loker yang MASIH AKTIF pada program yang berjalan.
 *                 Loker milik program yang dinonaktifkan tidak dihitung, jadi
 *                 jatahnya kembali sendiri ke MPP.
 *
 *   Terisi        yang benar-benar diterima, LINTAS SELURUH program — termasuk
 *                 program yang sudah dinonaktifkan. Sengaja beda aturannya
 *                 dengan Dialokasikan: kursi yang dijanjikan boleh ditarik
 *                 kembali, orang yang sudah diterima tidak.
 *
 * ══ DIHITUNG ULANG, TIDAK PERNAH DITAMBAH SATU ══════════════════════════════
 *
 * Sepola KursiPosisi. `Terisi + 1` salah secara permanen begitu dijalankan dua
 * kali — dan dua kali itu pasti terjadi: job yang diulang antrean, tombol yang
 * diklik dua kali karena layar terasa lambat, admin yang membuka dua tab.
 *
 * Menghitung ulang juga membuat JALUR BALIK bekerja tanpa kode tambahan:
 * kandidat yang mengundurkan diri sesudah diterima berhenti terhitung, angkanya
 * turun sendiri, dan MPP-nya terbuka lagi.
 */
class KursiMpp
{
    public const STATUS_BUKA = 'BUKA';

    public const STATUS_PENUH = 'PENUH';

    private const TABEL = 'N_WEB_CAREERS_Mpp_Kursi';

    /** Nomor MPP yang bentuknya sah — sama dengan pola di DetailMppLoker. */
    private static function sah(?string $ref): ?string
    {
        $ref = trim((string) $ref);

        return ($ref !== '' && preg_match('#^[A-Za-z0-9\-/]{1,50}$#', $ref)) ? $ref : null;
    }

    /** Ledger sudah dipasang di basis data ini? Selama belum, seluruh gerbang diam. */
    public static function siap(): bool
    {
        static $siap = null;

        if ($siap === null) {
            try {
                $siap = Skema::adaTabel(self::TABEL);
            } catch (\Throwable $e) {
                $siap = false;
            }
        }

        return $siap;
    }

    /**
     * Keadaan kursi satu MPP — dibaca apa adanya, tanpa mengunci.
     *
     * Null bila nomornya tak sah / ledger belum ada: pemanggil memperlakukannya
     * sebagai "tidak dibatasi", persis seperti sebelum fitur ini ada.
     *
     * @return array{kuota:int, dialokasikan:int, terisi:int, sisa:int|null, penuh:bool}|null
     */
    public static function keadaan(?string $mppRef): ?array
    {
        $ref = self::sah($mppRef);
        if (! $ref || ! self::siap()) {
            return null;
        }

        return self::keadaanBanyak([$ref])[$ref] ?? null;
    }

    /**
     * Keadaan banyak MPP sekaligus — satu kueri.
     *
     * Papan worklist menggambar puluhan kartu sekaligus; menanyakannya satu per
     * satu berarti satu kueri per kandidat, dan halaman yang seluruh gunanya
     * adalah "lihat semuanya" justru jadi paling lambat saat paling dipakai.
     *
     * @param  array<int, string|null>  $refs
     * @return array<string, array{kuota:int, dialokasikan:int, terisi:int, sisa:int|null, penuh:bool}>
     */
    public static function keadaanBanyak(array $refs): array
    {
        if (! self::siap()) {
            return [];
        }

        $bersih = collect($refs)->map(fn ($r) => self::sah($r))->filter()->unique()->values();
        if ($bersih->isEmpty()) {
            return [];
        }

        try {
            $rows = DB::table(self::TABEL)->whereIn('No_Transaksi_MPP', $bersih->all())->get();
        } catch (\Throwable $e) {
            Log::channel('web_career')->warning('Baca kursi MPP gagal: '.$e->getMessage());

            return [];
        }

        $hasil = [];
        foreach ($rows as $r) {
            $hasil[$r->No_Transaksi_MPP] = self::bentuk((int) $r->Kuota, (int) $r->Dialokasikan, (int) $r->Terisi);
        }

        return $hasil;
    }

    /**
     * Selaraskan BANYAK MPP sekaligus, lalu pulangkan keadaannya.
     *
     * ══ KENAPA PAPAN TIDAK BOLEH MEMBACA NILAI TERSIMPAN SAJA ══════════════
     *
     * Ledger hanya ditulis ulang pada peristiwa yang menyentuhnya — penerimaan
     * dan jalur baliknya. Membuka program baru atas MPP yang sama, menonaktifkan
     * program, atau mengubah kuota loker TIDAK melewati jalur itu, jadi angka
     * `Dialokasikan` di baris ledger bisa tertinggal.
     *
     * Gerbang keputusan tetap benar tanpa ini — ia menghitung ulang di balik
     * kunci. Yang salah justru LAYARNYA, dan itu bentuk kesalahan yang lebih
     * buruk: kartu menyebut angka yang tidak dipakai gerbang mana pun, dan yang
     * membacanya tidak punya cara tahu mana yang berlaku.
     *
     * Tiga kueri agregat untuk berapa pun jumlah MPP-nya — bukan satu putaran
     * per MPP, sebab papan basis program bisa memuat belasan lowongan sekaligus.
     * Baris yang angkanya memang sudah sama tidak ditulis ulang.
     *
     * @param  array<int, string|null>  $refs
     * @return array<string, array{kuota:int, dialokasikan:int, terisi:int, sisa:int|null, penuh:bool}>
     */
    public static function segarkanBanyak(array $refs): array
    {
        if (! self::siap()) {
            return [];
        }

        $bersih = collect($refs)->map(fn ($r) => self::sah($r))->filter()->unique()->values();
        if ($bersih->isEmpty()) {
            return [];
        }

        try {
            $daftar = $bersih->all();
            $potong = HasilKeputusan::kodePotongKuota() ?: ['LULUS'];

            $rencana = DB::table('HRIS_Transaksi_GForm')
                ->whereIn('No_Transaksi', $daftar)
                ->pluck('Jumlah_Rekruitmen', 'No_Transaksi');

            $alokasi = self::lokerAktifBanyak($daftar)
                ->select('x.Mpp_Ref', DB::raw('SUM(ISNULL(x.Kuota, 0)) as J'))
                ->groupBy('x.Mpp_Ref')->pluck('J', 'Mpp_Ref');

            $terisi = DB::table('N_WEB_CAREERS_Lamaran as l')
                ->join('N_WEB_CAREERS_Program_Posisi as x', 'x.Id_Program_Posisi', '=', 'l.Program_Posisi_Id')
                ->whereIn('x.Mpp_Ref', $daftar)
                ->whereIn('l.Status', $potong)
                ->select('x.Mpp_Ref', DB::raw('COUNT(*) as J'))
                ->groupBy('x.Mpp_Ref')->pluck('J', 'Mpp_Ref');

            $tersimpan = DB::table(self::TABEL)->whereIn('No_Transaksi_MPP', $daftar)->get()->keyBy('No_Transaksi_MPP');

            $hasil = [];
            foreach ($daftar as $ref) {
                $h = [
                    'kuota' => (int) ($rencana[$ref] ?? 0),
                    'dialokasikan' => (int) ($alokasi[$ref] ?? 0),
                    'terisi' => (int) ($terisi[$ref] ?? 0),
                ];

                $lama = $tersimpan[$ref] ?? null;
                $berubah = ! $lama
                    || (int) $lama->Kuota !== $h['kuota']
                    || (int) $lama->Dialokasikan !== $h['dialokasikan']
                    || (int) $lama->Terisi !== $h['terisi'];

                if ($berubah) {
                    self::tulis($ref, $h);
                }

                $hasil[$ref] = self::bentuk($h['kuota'], $h['dialokasikan'], $h['terisi']);
            }

            return $hasil;
        } catch (\Throwable $e) {
            Log::channel('web_career')->warning('Segarkan kursi MPP gagal: '.$e->getMessage());

            // Jatuh ke nilai tersimpan — usang lebih baik daripada kosong, dan
            // gerbang keputusan tetap menghitungnya sendiri.
            return self::keadaanBanyak($refs);
        }
    }

    /**
     * Selaraskan ledger satu MPP dengan keadaan sebenarnya.
     *
     * Dipanggil dari titik penerimaan DAN dari jalur balik. Aman dipanggil
     * berulang: seluruhnya dihitung ulang.
     *
     * @return array{kuota:int, dialokasikan:int, terisi:int, sisa:int|null, penuh:bool}|null
     */
    public static function sinkron(?string $mppRef): ?array
    {
        $ref = self::sah($mppRef);
        if (! $ref || ! self::siap()) {
            return null;
        }

        try {
            return DB::transaction(function () use ($ref) {
                $hitung = self::hitungDariSumber($ref);
                self::tulis($ref, $hitung);

                return self::bentuk($hitung['kuota'], $hitung['dialokasikan'], $hitung['terisi']);
            });
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Sinkron kursi MPP {$ref} gagal: ".$e->getMessage());

            return null;
        }
    }

    /**
     * KUNCI baris ledger MPP ini, lalu hitung ulang di balik kunci itu.
     *
     * HARUS dipanggil di dalam transaksi pemanggilnya — gerbang keputusan
     * memakainya untuk menyerialkan dua rekruter yang memperebutkan kursi
     * terakhir dari dua program berbeda.
     *
     * Barisnya dipastikan ada lebih dulu: `lockForUpdate` pada baris yang tidak
     * ada tidak mengunci apa pun, dan dua permintaan pertama untuk sebuah MPP
     * baru akan sama-sama lolos.
     *
     * @return array{kuota:int, dialokasikan:int, terisi:int, sisa:int|null, penuh:bool}|null
     */
    public static function kunciDanHitung(?string $mppRef): ?array
    {
        $ref = self::sah($mppRef);
        if (! $ref || ! self::siap()) {
            return null;
        }

        self::pastikanBaris($ref);

        DB::table(self::TABEL)->where('No_Transaksi_MPP', $ref)->lockForUpdate()->first();

        $hitung = self::hitungDariSumber($ref);
        self::tulis($ref, $hitung);

        return self::bentuk($hitung['kuota'], $hitung['dialokasikan'], $hitung['terisi']);
    }

    /**
     * Σ kuota loker aktif MPP ini, TIDAK termasuk satu loker yang dikecualikan.
     *
     * Dipakai gerbang alokasi saat membuka / menyunting loker: yang ditanyakan
     * adalah "kalau baris ini diisi sekian, apakah totalnya masih muat" — dan
     * baris itu sendiri tidak boleh ikut dihitung dua kali.
     */
    public static function alokasiLain(?string $mppRef, ?int $kecualiPosisiId = null): int
    {
        $ref = self::sah($mppRef);
        if (! $ref) {
            return 0;
        }

        return (int) self::lokerAktif($ref)
            ->when($kecualiPosisiId, fn ($w) => $w->where('x.Id_Program_Posisi', '!=', $kecualiPosisiId))
            ->sum('x.Kuota');
    }

    /** Rencana MPP — angka persetujuan. 0 = tidak diketahui, diperlakukan tanpa batas. */
    public static function rencana(?string $mppRef): int
    {
        $ref = self::sah($mppRef);
        if (! $ref) {
            return 0;
        }

        try {
            return (int) DB::table('HRIS_Transaksi_GForm')->where('No_Transaksi', $ref)->value('Jumlah_Rekruitmen');
        } catch (\Throwable $e) {
            return 0;
        }
    }

    // ─────────────────────────────────────────────────────────────────────────

    /** Loker AKTIF milik MPP ini, pada program yang berjalan. */
    private static function lokerAktif(string $ref)
    {
        return self::lokerAktifBanyak([$ref]);
    }

    /**
     * Loker AKTIF milik beberapa MPP sekaligus.
     *
     * @param  array<int, string>  $refs
     */
    private static function lokerAktifBanyak(array $refs)
    {
        $q = DB::table('N_WEB_CAREERS_Program_Posisi as x')
            ->join('N_WEB_CAREERS_Program as p', 'p.Id_Program', '=', 'x.Program_Id')
            ->whereIn('x.Mpp_Ref', $refs ?: ['__tidak_ada__'])
            ->where('p.Status', 'BERJALAN');

        // Kolom Flag_Aktif lahir belakangan (01-struktur.sql). Diperiksa dulu
        // supaya kelas ini tidak menjatuhkan halaman di basis data yang belum
        // dinaikkan — di sana seluruh loker memang dianggap aktif.
        if (Skema::adaKolom('N_WEB_CAREERS_Program_Posisi', 'Flag_Aktif')) {
            $q->where(fn ($w) => $w->whereNull('x.Flag_Aktif')->orWhere('x.Flag_Aktif', 'Y'));
        }

        return $q;
    }

    /**
     * Hitung ketiganya dari sumbernya masing-masing.
     *
     * Status mana yang memotong kuota dibaca dari MASTER (Flag_Potong_Kuota),
     * bukan ditulis 'LULUS' di sini. Sampai perbaikan ini ada DUA definisi yang
     * berbeda di sistem — Monitoring membaca master, gerbang menulisnya mati —
     * dan keduanya hanya kebetulan sepakat selama cuma LULUS yang bercentang.
     *
     * @return array{kuota:int, dialokasikan:int, terisi:int}
     */
    private static function hitungDariSumber(string $ref): array
    {
        $potong = HasilKeputusan::kodePotongKuota() ?: ['LULUS'];

        // TERISI: seluruh loker MPP ini, tanpa memandang program masih berjalan
        // atau tidak. Orang yang sudah diterima tidak bisa di-tidak-diterima
        // hanya karena programnya ditutup.
        $terisi = (int) DB::table('N_WEB_CAREERS_Lamaran as l')
            ->join('N_WEB_CAREERS_Program_Posisi as x', 'x.Id_Program_Posisi', '=', 'l.Program_Posisi_Id')
            ->where('x.Mpp_Ref', $ref)
            ->whereIn('l.Status', $potong)
            ->count();

        return [
            'kuota' => self::rencana($ref),
            'dialokasikan' => (int) self::lokerAktif($ref)->sum('x.Kuota'),
            'terisi' => $terisi,
        ];
    }

    /** @param array{kuota:int, dialokasikan:int, terisi:int} $h */
    private static function tulis(string $ref, array $h): void
    {
        $penuh = $h['kuota'] > 0 && $h['terisi'] >= $h['kuota'];

        self::pastikanBaris($ref);

        DB::table(self::TABEL)->where('No_Transaksi_MPP', $ref)->update([
            'Kuota' => $h['kuota'],
            'Dialokasikan' => $h['dialokasikan'],
            'Terisi' => $h['terisi'],
            'Status' => $penuh ? self::STATUS_PENUH : self::STATUS_BUKA,
            // Dicatat hanya saat BERALIH ke penuh. Menimpanya setiap kali akan
            // menghapus kapan sebenarnya kursi terakhir terisi.
            'Ditutup_At' => $penuh ? DB::raw('ISNULL(Ditutup_At, GETDATE())') : null,
            'Disegarkan_At' => now(),
            'Updated_At' => now(),
            'Updated_By' => session('career_auth.nama', 'SISTEM'),
            'Updated_By_Id' => session('career_auth.id'),
        ]);
    }

    private static function pastikanBaris(string $ref): void
    {
        if (DB::table(self::TABEL)->where('No_Transaksi_MPP', $ref)->exists()) {
            return;
        }

        try {
            DB::table(self::TABEL)->insert([
                'No_Transaksi_MPP' => $ref,
                'Kuota' => 0, 'Dialokasikan' => 0, 'Terisi' => 0,
                'Status' => self::STATUS_BUKA,
                'Created_At' => now(), 'Created_By' => 'SISTEM',
                'Updated_At' => now(), 'Updated_By' => 'SISTEM',
            ]);
        } catch (\Throwable $e) {
            // Dua permintaan berbarengan untuk MPP yang sama: yang kalah
            // menabrak kunci utama. Barisnya sudah ada — itu yang diinginkan.
        }
    }

    /** @return array{kuota:int, dialokasikan:int, terisi:int, sisa:int|null, penuh:bool} */
    private static function bentuk(int $kuota, int $dialokasikan, int $terisi): array
    {
        return [
            'kuota' => $kuota,
            'dialokasikan' => $dialokasikan,
            'terisi' => $terisi,
            // Kuota 0 = TANPA BATAS, sama seperti gerbang per-loker yang sudah
            // ada. `sisa` null berarti "tak terhingga", bukan "habis".
            'sisa' => $kuota > 0 ? max(0, $kuota - $terisi) : null,
            'penuh' => $kuota > 0 && $terisi >= $kuota,
        ];
    }
}
