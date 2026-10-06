<?php

namespace App\Support\Career;

/**
 * KAMUS — "apakah ini kata sungguhan?" untuk pemeriksa kalimat (Kalimat).
 *
 * Peramban memeriksa dengan nspell langsung dari kamus Hunspell di
 * resources/kamus (id_ID = hunspell-id, en_US = SCOWL). PHP tidak bisa
 * menjalankan nspell, jadi server membaca DAFTAR KATA hasil urai nspell yang
 * sama (`npm run kamus` → resources/kamus/id-kata.txt & en-kata.txt; aturan
 * huruf besar-kecilnya di utils/career/ejaanAturan.js) ditambah
 * resources/kamus/tambahan.txt — kata yang sama dinilai sama di kedua sisi.
 *
 * Daftar terurut byte (strcmp), satu kata per baris: dicari dengan pencarian
 * biner langsung di teks berkas — tanpa memecahnya jadi larik. Berkas hanya
 * dibaca saat sebuah penjelasan benar-benar diperiksa (jarang), sekali per
 * permintaan.
 */
final class Kamus
{
    private const DAFTAR = ['id-kata.txt', 'en-kata.txt'];

    /** @var array<string, string> */
    private static array $isi = [];

    /** @var ?array<string, true> */
    private static ?array $tambahan = null;

    private static function folder(): string
    {
        return dirname(__DIR__, 3).DIRECTORY_SEPARATOR.'resources'.DIRECTORY_SEPARATOR.'kamus'.DIRECTORY_SEPARATOR;
    }

    /** Daftar kata server sudah dirakit (`npm run kamus`) dan ikut terpasang. */
    public static function siap(): bool
    {
        foreach (self::DAFTAR as $berkas) {
            if (! is_file(self::folder().$berkas) || filesize(self::folder().$berkas) < 1024) {
                return false;
            }
        }

        return true;
    }

    /** Kata (huruf kecil, satu deret huruf) dikenal kamus id/en atau tambahan.txt. */
    public static function kenal(string $kata): bool
    {
        if ($kata === '') {
            return false;
        }
        if (isset(self::tambahan()[$kata])) {
            return true;
        }
        foreach (self::DAFTAR as $berkas) {
            if (self::cari(self::isi($berkas), $kata)) {
                return true;
            }
        }

        return false;
    }

    private static function isi(string $berkas): string
    {
        return self::$isi[$berkas] ??= (string) @file_get_contents(self::folder().$berkas);
    }

    /** @return array<string, true> */
    private static function tambahan(): array
    {
        if (self::$tambahan === null) {
            self::$tambahan = [];
            $teks = (string) @file_get_contents(self::folder().'tambahan.txt');
            foreach (preg_split('/\r?\n/', $teks) ?: [] as $baris) {
                $baris = mb_strtolower(trim($baris));
                if ($baris !== '' && ! str_starts_with($baris, '#')) {
                    self::$tambahan[$baris] = true;
                }
            }
        }

        return self::$tambahan;
    }

    /**
     * Pencarian biner satu baris di teks terurut byte. `$lo` selalu awal
     * sebuah baris, `$hi` awal baris berikutnya (atau akhir teks).
     */
    private static function cari(string $teks, string $kata): bool
    {
        $panjang = strlen($teks);
        $lo = 0;
        $hi = $panjang;

        while ($lo < $hi) {
            $tengah = intdiv($lo + $hi, 2);
            // Awal baris yang memuat $tengah: sesudah "\n" terakhir SEBELUM
            // $tengah (offset negatif = cari mundur dari posisi $tengah - 1).
            $nl = $tengah > 0 ? strrpos($teks, "\n", $tengah - 1 - $panjang) : false;
            $awal = $nl === false ? 0 : $nl + 1;
            if ($awal < $lo) {
                $awal = $lo;
            }
            $akhir = strpos($teks, "\n", $awal);
            if ($akhir === false) {
                $akhir = $panjang;
            }

            $banding = strcmp($kata, rtrim(substr($teks, $awal, $akhir - $awal), "\r"));
            if ($banding === 0) {
                return true;
            }
            if ($banding < 0) {
                $hi = $awal;
            } else {
                $lo = $akhir + 1;
            }
        }

        return false;
    }
}
