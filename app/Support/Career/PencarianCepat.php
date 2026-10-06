<?php

namespace App\Support\Career;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * WEB CAREER — PENCARIAN NAMA YANG TIDAK MEMINDAI SELURUH TABEL.
 *
 * Masalah awalnya `Nama LIKE '%kata%'` di tabel 328 ribu baris: wildcard di
 * depan membuat kolom itu tidak bisa dicari lewat indeks, dan autocomplete
 * memanggilnya berkali-kali sementara orang mengetik.
 *
 * ── Yang diukur, bukan diduga ────────────────────────────────────────────
 * Angka di bawah dari mesin ini (SQL Server 2022 Express, 328.998 baris),
 * rata-rata beberapa kali panggilan, sudah memakai indeks penutup:
 *
 *   cara                                    umum      langka
 *   LIKE '%kata%'                          102 ms    237 ms
 *   CONTAINS + ORDER BY Nama              3492 ms    280 ms   ← jangan
 *   CONTAINSTABLE(top 2.000) + ORDER BY     65 ms     99 ms   ← dipakai
 *   CONTAINSTABLE(top 10.000)             3500 ms      —      ← jangan
 *
 * Dua jebakan yang sengaja dihindari:
 *
 *   1. CONTAINS polos + ORDER BY. Full-text mengembalikan puluhan ribu kunci
 *      lalu semuanya diurutkan — 3,5 detik, lebih lambat dari LIKE.
 *   2. top_n_by_rank besar. Di atas ±2.000 rencana query berubah dan waktunya
 *      melonjak ke 3,5 detik.
 *
 * ── Harga dari top_n_by_rank ─────────────────────────────────────────────
 * Membatasi 2.000 kandidat teratas berarti hasil BISA terpotong bila katanya
 * sangat umum sekaligus filternya sangat sempit. Diuji: dengan top 50 kasus
 * "Akademi Komunitas" + "negeri" kehilangan seluruh 12 hasilnya; dengan 2.000
 * lengkap. Karena tetap tidak dijamin, ada jaring pengaman: bila pencarian
 * cepat tidak mengembalikan apa pun, pemanggil mengulang lewat
 * {@see terapkanLuas()} yang pasti lengkap walau lebih lambat.
 *
 * Siapkan indeksnya:  php artisan career:optimasi-pencarian
 */
class PencarianCepat
{
    /** Berapa lama status full-text di-cache (menit). */
    private const CACHE_MENIT = 60;

    /** Maksimal kata yang diproses — melindungi dari kalimat panjang. */
    private const MAKS_KATA = 5;

    /**
     * Kandidat teratas yang diambil full-text sebelum disaring & diurutkan.
     * JANGAN dinaikkan tanpa mengukur ulang: 10.000 membuat waktunya melonjak
     * dari 65 ms ke 3.500 ms di mesin uji.
     */
    private const KANDIDAT = 2000;

    /** Apakah kolom ini sudah punya indeks full-text yang siap dipakai? */
    public static function fullTextSiap(string $tabel, string $kolom): bool
    {
        return Cache::remember("wc_ft_{$tabel}_{$kolom}", now()->addMinutes(self::CACHE_MENIT), function () use ($tabel, $kolom) {
            try {
                $r = DB::selectOne('
                    SELECT COUNT(*) AS n
                    FROM sys.fulltext_index_columns fic
                    JOIN sys.columns c ON c.object_id = fic.object_id AND c.column_id = fic.column_id
                    JOIN sys.fulltext_indexes fi ON fi.object_id = fic.object_id
                    WHERE fic.object_id = OBJECT_ID(?) AND c.name = ? AND fi.is_enabled = 1', [$tabel, $kolom]);

                return (int) ($r->n ?? 0) > 0;
            } catch (\Throwable $e) {
                return false;
            }
        });
    }

    /** Nama kolom kunci primer sebuah tabel (dipakai menyambung ke CONTAINSTABLE). */
    public static function kunciPrimer(string $tabel): ?string
    {
        return Cache::remember("wc_pk_{$tabel}", now()->addHours(12), function () use ($tabel) {
            try {
                $r = DB::selectOne('
                    SELECT TOP 1 c.name
                    FROM sys.indexes i
                    JOIN sys.index_columns ic ON ic.object_id = i.object_id AND ic.index_id = i.index_id
                    JOIN sys.columns c ON c.object_id = ic.object_id AND c.column_id = ic.column_id
                    WHERE i.object_id = OBJECT_ID(?) AND i.is_primary_key = 1', [$tabel]);

                return $r->name ?? null;
            } catch (\Throwable $e) {
                return null;
            }
        });
    }

    /**
     * Pecah ketikan pengguna jadi kata yang aman.
     * Hanya huruf/angka yang dipertahankan — mencegah sintaks CONTAINSTABLE
     * rusak sekaligus menutup celah injeksi lewat isi string pencarian.
     */
    public static function kata(string $mentah): array
    {
        $bersih = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $mentah);

        return array_slice(array_values(array_filter(
            array_map('trim', explode(' ', (string) $bersih)),
            fn ($k) => $k !== ''
        )), 0, self::MAKS_KATA);
    }

    /**
     * Pasang syarat pencarian CEPAT pada query builder.
     *
     * @param  string  $prefiks  alias tabel bila query memakai alias (mis. 'p.')
     * @return bool true bila jalur full-text yang dipakai (artinya hasil bisa
     *              terpotong, jadi pemanggil perlu jaring pengaman)
     */
    public static function terapkan($query, string $tabel, string $kolom, string $cari, string $prefiks = ''): bool
    {
        $kata = self::kata($cari);
        if (! $kata) {
            return false;
        }

        $pk = self::kunciPrimer($tabel);
        if ($pk && self::fullTextSiap($tabel, $kolom)) {
            // Tiap kata jadi awalan dan semuanya harus cocok:
            //   "negeri*" AND "jakarta*"
            $ekspresi = implode(' AND ', array_map(fn ($k) => '"' . $k . '*"', $kata));
            $query->whereRaw(
                "{$prefiks}{$pk} IN (SELECT [KEY] FROM CONTAINSTABLE({$tabel}, {$kolom}, ?, " . self::KANDIDAT . '))',
                [$ekspresi]
            );

            return true;
        }

        // Tanpa full-text: pencocokan awalan. Masih memakai indeks, tapi hanya
        // cocok dari awal nama — karena itu 'career:optimasi-pencarian' penting.
        $query->where(function ($w) use ($kata, $kolom, $prefiks) {
            foreach ($kata as $k) {
                $w->orWhere("{$prefiks}{$kolom}", 'like', self::amanLike($k) . '%');
            }
        });

        return false;
    }

    /**
     * Apakah kata itu ada di indeks — tanpa filter apa pun.
     *
     * Dipakai sebagai penyaring murah sebelum menjalankan jaring pengaman:
     * kalau katanya memang tidak ada di seluruh tabel (salah ketik), hasil
     * kosong itu sudah benar dan tidak perlu pemindaian penuh yang mahal.
     * Terukur: menekan kasus salah ketik dari ±2.000 ms ke ±60 ms.
     */
    public static function adaKecocokan(string $tabel, string $kolom, string $cari): bool
    {
        $kata = self::kata($cari);
        if (! $kata || ! self::fullTextSiap($tabel, $kolom)) {
            return true; // tidak bisa memastikan → biarkan jaring pengaman jalan
        }

        try {
            $ekspresi = implode(' AND ', array_map(fn ($k) => '"' . $k . '*"', $kata));
            $r = DB::selectOne("SELECT TOP 1 1 AS ada FROM CONTAINSTABLE({$tabel}, {$kolom}, ?, 1)", [$ekspresi]);

            return $r !== null;
        } catch (\Throwable $e) {
            return true;
        }
    }

    /**
     * Pencarian LENGKAP (pasti menemukan semua) tapi lebih lambat.
     * Dipakai sebagai jaring pengaman saat jalur cepat tidak menemukan apa pun.
     *
     * Catatan penting: jalur cepat mencocokkan AWAL KATA ("negeri" menemukan
     * "Universitas Negeri"), sedangkan ini mencocokkan potongan di mana pun
     * ("geri" pun menemukan "Negeri"). Itulah gunanya dijalankan belakangan.
     */
    public static function terapkanLuas($query, string $kolom, string $cari, string $prefiks = ''): bool
    {
        $kata = self::kata($cari);
        if (! $kata) {
            return false;
        }

        foreach ($kata as $k) {
            $query->where("{$prefiks}{$kolom}", 'like', '%' . self::amanLike($k) . '%');
        }

        return true;
    }

    /** Netralkan wildcard LIKE agar %, _ dan [ diperlakukan sebagai huruf biasa. */
    public static function amanLike(string $s): string
    {
        return addcslashes($s, '%_[');
    }
}
