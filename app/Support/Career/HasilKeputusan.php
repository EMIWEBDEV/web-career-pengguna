<?php

namespace App\Support\Career;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * WEB CAREER — DAFTAR HASIL KEPUTUSAN (read-only).
 *
 * Sumber tunggal "outcome apa saja yang ada dan apa artinya", dibaca dari
 * N_WEB_CAREERS_Master_Hasil_Keputusan. Dipakai MetrikRekrutmen (SQL),
 * PipelineProgress (PHP), dan MonitoringController (bucket + payload).
 *
 * KENAPA TIDAK MEMAKAI LamaranService::masterHasilKeputusan()
 * Method itu menyaring Flag_Aktif='Y' — benar untuk menawarkan pilihan kepada
 * admin, salah untuk MEMBACA. Kode yang dinonaktifkan HR tetap melekat pada
 * lamaran yang sudah terlanjur memakainya; menyaringnya membuat lamaran itu
 * tidak dikenali sebagai terminal, lalu jatuh ke cabang "masih berjalan" dan
 * muncul kembali di papan sebagai pelamar aktif. Di sini seluruh baris dibaca.
 *
 * PARTISI BUCKET (total, tanpa tumpang tindih, semuanya dari flag):
 *   Flag_Lolos='Y'                                        → lulus
 *   Flag_Lolos='T' + Flag_Oleh_Kandidat='Y'               → keluar
 *   Flag_Lolos='T' + Oleh='T' + Flag_Talent_Pool='Y'      → talent
 *   Flag_Lolos='T' + Oleh='T' + Flag_Talent_Pool='T'      → gugur
 *
 * Urutan pengujian TIDAK boleh dibalik: Flag_Talent_Pool bernilai 'Y' juga
 * untuk kedua kode keluar (artinya "boleh disimpan di Talent Pool", bukan
 * "bucket Talent Pool"), sehingga mengujinya lebih dulu akan menghitung ganda.
 */
class HasilKeputusan
{
    /**
     * Kode ikut disisipkan ke string SQL mentah (lihat sqlIn()), jadi tidak
     * boleh ada yang lolos tanpa diperiksa. Master memang hanya diisi orang
     * dalam, tapi "hanya orang dalam" bukan pengaman.
     */
    private const POLA_KODE = '/^[A-Z0-9_]+$/';

    private static ?Collection $cache = null;

    /** Seluruh baris master (TERMASUK yang non-aktif), sudah tersaring pola. */
    public static function semua(): Collection
    {
        return self::$cache ??= DB::table('N_WEB_CAREERS_Master_Hasil_Keputusan')
            ->orderBy('Urutan')
            ->get()
            ->keyBy('Kode')
            ->filter(function ($def, $kode) {
                if (preg_match(self::POLA_KODE, (string) $kode) === 1) {
                    return true;
                }
                Log::channel('web_career')->warning(
                    "Kode hasil keputusan diabaikan karena tidak memenuhi pola [A-Z0-9_]: {$kode}"
                );

                return false;
            });
    }

    /** Hanya untuk test — cache statis bertahan antar test dalam satu proses. */
    public static function lupakanCache(): void
    {
        self::$cache = null;
    }

    private static function ya($def, string $kolom): bool
    {
        return ($def->{$kolom} ?? 'T') === 'Y';
    }

    public static function kodeLolos(): array
    {
        return self::semua()->filter(fn ($d) => self::ya($d, 'Flag_Lolos'))->keys()->all();
    }

    public static function kodeTidakLolos(): array
    {
        return self::semua()->reject(fn ($d) => self::ya($d, 'Flag_Lolos'))->keys()->all();
    }

    public static function kodeKeluar(): array
    {
        return self::semua()
            ->reject(fn ($d) => self::ya($d, 'Flag_Lolos'))
            ->filter(fn ($d) => self::ya($d, 'Flag_Oleh_Kandidat'))
            ->keys()->all();
    }

    public static function kodeTalent(): array
    {
        return self::semua()
            ->reject(fn ($d) => self::ya($d, 'Flag_Lolos'))
            ->reject(fn ($d) => self::ya($d, 'Flag_Oleh_Kandidat'))
            ->filter(fn ($d) => self::ya($d, 'Flag_Talent_Pool'))
            ->keys()->all();
    }

    public static function kodeGugur(): array
    {
        return self::semua()
            ->reject(fn ($d) => self::ya($d, 'Flag_Lolos'))
            ->reject(fn ($d) => self::ya($d, 'Flag_Oleh_Kandidat'))
            ->reject(fn ($d) => self::ya($d, 'Flag_Talent_Pool'))
            ->keys()->all();
    }

    public static function kodePotongKuota(): array
    {
        return self::semua()->filter(fn ($d) => self::ya($d, 'Flag_Potong_Kuota'))->keys()->all();
    }

    /** Bucket funnel sebuah kode — partisi total, tanpa tumpang tindih. */
    public static function bucket(string $kode): ?string
    {
        $d = self::semua()->get($kode);
        if (! $d) {
            return null;
        }
        if (self::ya($d, 'Flag_Lolos')) {
            return 'lulus';
        }
        if (self::ya($d, 'Flag_Oleh_Kandidat')) {
            return 'keluar';
        }

        return self::ya($d, 'Flag_Talent_Pool') ? 'talent' : 'gugur';
    }

    /**
     * Daftar kode → potongan `IN (...)`. Daftar kosong menghasilkan "''":
     * `IN ()` adalah galat sintaks, sedangkan `IN ('')` sah dan tidak pernah
     * cocok — persis yang diinginkan saat master kosong.
     */
    public static function sqlIn(array $kode): string
    {
        $bersih = array_values(array_filter($kode, fn ($k) => preg_match(self::POLA_KODE, (string) $k) === 1));

        return $bersih ? "'".implode("','", $bersih)."'" : "''";
    }

    /** Atribut tampilan untuk frontend — supaya Vue tidak menyimpan peta literal. */
    public static function peta(): array
    {
        return self::semua()->map(fn ($d, $kode) => [
            'nama' => $d->Nama ?? $kode,
            'warna' => $d->Warna ?? null,
            'ikon' => $d->Ikon ?? null,
            'olehKandidat' => self::ya($d, 'Flag_Oleh_Kandidat'),
            'lolos' => self::ya($d, 'Flag_Lolos'),
            'bucket' => self::bucket($kode),
        ])->all();
    }
}
