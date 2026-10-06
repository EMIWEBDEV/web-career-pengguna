<?php

namespace App\Support\Career;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * WEB CAREER — MENENTUKAN PRODI/JURUSAN YANG BOLEH DIPILIH untuk sebuah kampus.
 *
 * Daftarnya disusun dari aturan: kampus → jenjang → prodi yang lazim untuk
 * jenjang itu, dengan nama Indonesia untuk kampus dalam negeri dan nama
 * internasional untuk kampus luar negeri.
 *
 * CATATAN: ini daftar PILIHAN, bukan pernyataan bahwa kampus tersebut memang
 * membuka semuanya. Data prodi per kampus hanya ada di PDDIKTI dan sampai
 * sekarang tidak bisa diambil, jadi daftarnya sengaja lebih luas daripada
 * kenyataan — dan pelamar tetap boleh mengetik prodinya sendiri.
 */
class ProdiKampus
{
    private const T_KAMPUS = 'N_WEB_CAREERS_Master_Kampus';

    private const T_PRODI = 'N_WEB_CAREERS_Master_Prodi';

    private const T_PJ = 'N_WEB_CAREERS_Prodi_Jenjang';

    private const T_BIDANG = 'N_WEB_CAREERS_Master_Bidang_Ilmu';

    /**
     * Data kampus seperlunya (negara menentukan daftar prodi mana yang dipakai).
     *
     * Menerima Kode maupun Nama. Formulir menyimpan NAMA kampus supaya jawaban
     * terbaca manusia, jadi yang sampai ke sini biasanya nama. Nama sekolah
     * tidak selalu unik ("SD NEGERI 1" ada di banyak daerah), tapi yang dibaca
     * dari baris ini cuma negara & jenisnya — sama untuk semua yang senama.
     */
    public static function kampus(string $kunci): ?object
    {
        $kunci = trim($kunci);
        if ($kunci === '') {
            return null;
        }

        return Cache::remember('wc_kampus_' . md5($kunci), now()->addMinutes(30), function () use ($kunci) {
            $kolom = ['Kode', 'Nama', 'Negara', 'Jenis_Institusi_Kode', 'Jenjang_Kode'];

            return DB::table(self::T_KAMPUS)->where('Kode', $kunci)->first($kolom)
                ?: DB::table(self::T_KAMPUS)->where('Nama', $kunci)->orderBy('Id_Master_Kampus')->first($kolom);
        });
    }

    /**
     * Cakupan daftar prodi yang dipakai: kampus dalam negeri memakai nama
     * Indonesia, kampus luar negeri memakai nama internasional (CIP).
     */
    private static function cakupan(?object $kampus): string
    {
        $negara = trim((string) ($kampus->Negara ?? 'Indonesia'));

        return ($negara === '' || $negara === 'Indonesia') ? 'ID' : 'GLOBAL';
    }

    /**
     * Daftar prodi untuk satu kampus + jenjang.
     *
     * @return array daftar prodi siap pakai (kosong bila kampus belum dipilih)
     */
    public static function prodi(string $kodeKampus, string $jenjang, string $bidang = '', string $cari = '', int $limit = 50): array
    {
        // Kampus WAJIB diisi — daftar prodi mengikuti kampus. Kalau kosong,
        // kembalikan kosong supaya prodi tidak bisa dipilih lebih dulu.
        // Kampus yang DIKETIK SENDIRI (tidak ada di master) tetap dilayani.
        if (trim($kodeKampus) === '') {
            return [];
        }

        $kampus = self::kampus($kodeKampus);
        $limit = max(1, min($limit, 200));

        $kolom = ['p.Kode', 'p.Nama', 'p.Nama_En', 'p.Bidang_Kode', 'p.Gelar', 'p.Kelompok'];

        $bangun = fn () => DB::table(self::T_PRODI . ' as p')
            ->join(self::T_PJ . ' as pj', 'pj.Kode_Prodi', '=', 'p.Kode')
            ->where('p.Flag_Aktif', 'Y')
            ->where('p.Cakupan', self::cakupan($kampus))
            ->when($jenjang !== '', fn ($w) => $w->where('pj.Kode_Jenjang', $jenjang));

        $q = $bangun();
        $pakaiFullText = self::saring($q, $bidang, $cari);
        $rows = $q->orderBy('p.Nama')->limit($limit)->get($kolom);

        // Sama seperti pencarian kampus: full-text hanya menimbang sebagian
        // kandidat, jadi hasil kosong diulang lewat pencarian lengkap.
        if ($rows->isEmpty() && $pakaiFullText && $cari !== ''
            && PencarianCepat::adaKecocokan(self::T_PRODI, 'Nama', $cari)) {
            $ulang = $bangun();
            self::saringBidang($ulang, $bidang);
            PencarianCepat::terapkanLuas($ulang, 'Nama', $cari, 'p.');
            $rows = $ulang->orderBy('p.Nama')->limit($limit)->get($kolom);
        }

        return self::rapikan($rows);
    }

    /**
     * Fakultas / rumpun ilmu yang tersedia untuk kampus + jenjang tersebut,
     * berikut jumlah prodi di dalamnya (untuk cascade dua langkah).
     */
    public static function fakultas(string $kodeKampus, string $jenjang): array
    {
        if (trim($kodeKampus) === '') {
            return []; // sama seperti prodi(): fakultas mengikuti kampus
        }

        $kampus = self::kampus($kodeKampus);

        // Daftar fakultas hanya bergantung pada negara + jenjang — sama untuk
        // ratusan ribu kampus. Dihitung sekali, lalu dipakai bersama.
        $kunci = 'wc_fak_' . self::cakupan($kampus) . "_{$jenjang}";

        return Cache::remember($kunci, now()->addMinutes(30), fn () => self::hitungFakultas($kampus, $jenjang));
    }

    /** Perhitungan sebenarnya untuk {@see fakultas()} — dipisah agar bisa di-cache. */
    private static function hitungFakultas(?object $kampus, string $jenjang): array
    {
        $q = DB::table(self::T_PRODI . ' as p')
            ->join(self::T_PJ . ' as pj', 'pj.Kode_Prodi', '=', 'p.Kode')
            ->where('p.Cakupan', self::cakupan($kampus))
            ->when($jenjang !== '', fn ($w) => $w->where('pj.Kode_Jenjang', $jenjang));

        // Bidang luas = 2 digit pertama kode ISCED → itulah padanan "fakultas".
        $rows = $q->where('p.Flag_Aktif', 'Y')
            ->whereNotNull('p.Bidang_Kode')
            ->select(DB::raw('LEFT(p.Bidang_Kode, 2) as Broad'), DB::raw('COUNT(*) as Jumlah'))
            ->groupBy(DB::raw('LEFT(p.Bidang_Kode, 2)'))
            ->get();

        if ($rows->isEmpty()) {
            return [];
        }

        $bidang = DB::table(self::T_BIDANG)
            ->whereIn('Kode', $rows->pluck('Broad')->all())
            ->get(['Kode', 'Nama', 'Nama_En', 'Nama_Fakultas', 'Urutan'])
            ->keyBy('Kode');

        return $rows->map(fn ($r) => [
            'kode' => $r->Broad,
            // value = nama, sama seperti prodi: yang tersimpan di jawaban harus
            // terbaca manusia dan seragam dengan isian yang diketik sendiri.
            'value' => $bidang[$r->Broad]->Nama_Fakultas ?: ($bidang[$r->Broad]->Nama ?? $r->Broad),
            'nama' => $bidang[$r->Broad]->Nama_Fakultas ?: ($bidang[$r->Broad]->Nama ?? $r->Broad),
            'rumpun' => $bidang[$r->Broad]->Nama ?? '',
            'jumlah' => (int) $r->Jumlah,
            'urutan' => (int) ($bidang[$r->Broad]->Urutan ?? 0),
        ])->filter(fn ($x) => isset($bidang[$x['kode']]))
            ->sortBy('urutan')->values()->all();
    }

    /**
     * Ubah pilihan fakultas menjadi kode bidang ISCED.
     *
     * Isian bisa berupa kode ('07'), nama fakultas ('Fakultas Teknik'), atau
     * ketikan bebas pelamar. Yang tidak dikenali dikembalikan kosong — lebih
     * baik menampilkan semua prodi daripada tidak menampilkan apa pun.
     */
    public static function kodeBidang(string $isian): string
    {
        $isian = trim($isian);
        if ($isian === '') {
            return '';
        }
        if (ctype_digit($isian)) {
            return $isian;
        }

        $kode = Cache::remember('wc_bidang_nama_' . md5(mb_strtolower($isian)), now()->addHours(6), function () use ($isian) {
            $r = DB::table(self::T_BIDANG)
                ->where(fn ($w) => $w->where('Nama_Fakultas', $isian)->orWhere('Nama', $isian))
                ->orderBy('Urutan')
                ->first(['Kode']);

            return $r->Kode ?? '';
        });

        return (string) $kode;
    }

    /** Persempit ke satu fakultas/rumpun. */
    private static function saringBidang($q, string $bidang): void
    {
        $kode = self::kodeBidang($bidang);
        if ($kode !== '') {
            // Awalan kode ISCED: '07' mencakup 071x dan 0711. Wildcard hanya di
            // belakang, jadi tetap bisa memakai indeks.
            $q->where('p.Bidang_Kode', 'like', PencarianCepat::amanLike($kode) . '%');
        }
    }

    /**
     * Filter bidang + pencarian nama.
     *
     * @return bool true bila memakai jalur full-text (hasil bisa terpotong)
     */
    private static function saring($q, string $bidang, string $cari): bool
    {
        self::saringBidang($q, $bidang);

        // Nama prodi dicari lewat indeks kata; tanpa full-text turun otomatis
        // ke pencocokan awalan — dua-duanya tidak memindai tabel.
        return PencarianCepat::terapkan($q, self::T_PRODI, 'Nama', $cari, 'p.');
    }

    /**
     * `value` sengaja memakai NAMA, bukan kode.
     *
     * Jawaban formulir disimpan apa adanya dan dibaca manusia (HR, ekspor
     * Excel). Kalau yang tersimpan "ID-TEKNIK-INFORMATIKA", laporannya tidak
     * terbaca. Nama juga menyamakan bentuk dengan isian yang diketik sendiri
     * saat prodinya belum terdaftar.
     */
    private static function rapikan($rows): array
    {
        return $rows->map(fn ($r) => [
            'value' => $r->Nama,
            'kode' => $r->Kode,
            'label' => $r->Nama,
            'namaEn' => $r->Nama_En,
            'bidang' => $r->Bidang_Kode,
            'gelar' => $r->Gelar,
            'kelompok' => $r->Kelompok,
        ])->values()->all();
    }
}
