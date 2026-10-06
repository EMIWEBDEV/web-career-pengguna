<?php

namespace App\Support\Career;

use Dompdf\Canvas;
use Dompdf\Dompdf;
use Dompdf\FontMetrics;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * WEB CAREER — KOP & KAKI halaman Biodata Kandidat, digambar ke KANVAS.
 *
 * ── KENAPA TIDAK DI DALAM HTML ─────────────────────────────────────────────
 * Rancangannya memberi HALAMAN PERTAMA kop yang berbeda: halaman 1 memakai
 * strip merek ("EVO GROUP · BIODATA KANDIDAT · DATA PELAMAR"), halaman
 * berikutnya memakai identitas kandidat ("SALNI · LMR-…"). Elemen
 * `position: fixed` di dompdf tercetak SAMA di setiap halaman — tidak ada
 * pemilih CSS yang bisa membedakan halaman pertama.
 *
 * Nomor halamannya pun tidak bisa dari CSS: `counter(pages)` selalu 0 di
 * dompdf karena jumlah halaman baru diketahui setelah dokumen tersusun, jadi
 * yang tercetak "01 / 00".
 *
 * Kanvas menyelesaikan keduanya sekaligus. `page_script()` menerima closure
 * yang dipanggil per halaman dengan nomor DAN total halaman yang sudah pasti.
 *
 * ── DAN KENAPA INI TIDAK MENYALAKAN EKSEKUSI PHP ───────────────────────────
 * Cara lain yang lazim adalah menyisipkan `<script type="text/php">` ke dalam
 * HTML, dan itu menuntut opsi `isPhpEnabled`. Dokumen ini dirakit dari isian
 * kandidat, jadi menyalakan penafsir PHP di dalam mesin cetaknya adalah harga
 * yang tidak sepadan. Closure di bawah dipanggil dari kode kita sendiri —
 * `isPhpEnabled` tetap mati.
 *
 * ── SATUAN ─────────────────────────────────────────────────────────────────
 * Kanvas dompdf memakai POIN (1/72 inci), sedangkan rancangannya dalam piksel
 * CSS (1/96 inci). Semua ukuran rancangan dikalikan 0,75 lewat px().
 */
class KopKakiLaporan
{
    /** Piksel CSS → poin PDF. */
    private const SKALA = 0.75;

    // Palet rancangan.
    private const EMAS = [0.788, 0.635, 0.153];       // #c9a227
    private const EMAS_TUA = [0.659, 0.502, 0.118];   // #a8801e
    private const TINTA = [0.078, 0.090, 0.122];      // #14171f
    private const REDUP = [0.486, 0.518, 0.588];      // #7c8496
    private const TEPI = [0.894, 0.886, 0.855];       // #e4e2da

    public static function pasang(Dompdf $dompdf, string $nama, string $kode): void
    {
        try {
            $dompdf->getCanvas()->page_script(
                function (int $halaman, int $total, Canvas $kanvas, FontMetrics $fm) use ($nama, $kode) {
                    self::kop($halaman, $total, $kanvas, $fm, $nama, $kode);
                    self::kaki($halaman, $total, $kanvas, $fm, $nama, $kode);
                }
            );
        } catch (\Throwable $e) {
            // Dokumen tanpa kop masih terbaca; dokumen yang gagal terbit tidak.
            Log::channel('web_career')->warning('[LAPORAN] kop/kaki gagal digambar: ' . $e->getMessage());
        }
    }

    private static function px(float $px): float
    {
        return $px * self::SKALA;
    }

    /** Tepi kiri & kanan area cetak — sama dengan margin @page di blade. */
    private static function batas(Canvas $kanvas): array
    {
        return [self::px(38), $kanvas->get_width() - self::px(38)];
    }

    /**
     * KOP.
     *
     * Halaman 1 memakai strip merek; sesudahnya identitas kandidat. Persis
     * pembagian yang dipakai rancangan.
     */
    private static function kop(int $halaman, int $total, Canvas $kanvas, FontMetrics $fm, string $nama, string $kode): void
    {
        [$kiri, $kanan] = self::batas($kanvas);
        $monoT = $fm->getFont('JetBrains Mono', 'bold');
        $interT = $fm->getFont('Inter', 'bold');
        $y = self::px(34);

        if ($halaman === 1) {
            // ▬ EVO GROUP · BIODATA KANDIDAT · DATA PELAMAR
            $kanvas->line($kiri, $y + self::px(5), $kiri + self::px(18), $y + self::px(5), self::EMAS, self::px(2));

            $x = $kiri + self::px(27);
            $kanvas->text($x, $y, 'EVO GROUP', $monoT, self::px(8), self::EMAS_TUA, 0, self::px(8) * 0.24);
            $x += $fm->getTextWidth('EVO GROUP', $monoT, self::px(8), 0, self::px(8) * 0.24) + self::px(9);

            $sisa = '· BIODATA KANDIDAT · DATA PELAMAR';
            $kanvas->text($x, $y, $sisa, $fm->getFont('JetBrains Mono', 'normal'), self::px(8), self::REDUP, 0, self::px(8) * 0.2);
        } else {
            // SALNI  LMR-VF965OE5
            $nm = Str::limit($nama, 38);
            $kanvas->text($kiri, $y - self::px(3), $nm, $interT, self::px(16), self::TINTA, 0, self::px(16) * -0.02);

            $x = $kiri + $fm->getTextWidth($nm, $interT, self::px(16), 0, self::px(16) * -0.02) + self::px(11);
            $kanvas->text($x, $y + self::px(1), $kode, $monoT, self::px(9), self::EMAS_TUA, 0, self::px(9) * 0.14);
        }

        // Kanan: BIODATA KANDIDAT · 02 / 04
        $teks = 'BIODATA KANDIDAT · ' . self::nomor($halaman) . ' / ' . self::nomor($total);
        $ruang = self::px(8) * 0.2;
        $lebar = $fm->getTextWidth($teks, $monoT, self::px(8), 0, $ruang);
        $kanvas->text($kanan - $lebar, $y + self::px(3), $teks, $monoT, self::px(8), self::REDUP, 0, $ruang);

        $kanvas->line($kiri, self::px(58), $kanan, self::px(58), self::TEPI, 0.75);
    }

    /** KAKI — sama di semua halaman. */
    private static function kaki(int $halaman, int $total, Canvas $kanvas, FontMetrics $fm, string $nama, string $kode): void
    {
        [$kiri, $kanan] = self::batas($kanvas);
        $mono = $fm->getFont('JetBrains Mono', 'normal');
        $ukuran = self::px(8);
        $ruang = $ukuran * 0.12;

        $garis = $kanvas->get_height() - self::px(26 + 23);
        $kanvas->line($kiri, $garis, $kanan, $garis, self::TEPI, 0.75);

        $y = $garis + self::px(12);

        $kanvas->text($kiri, $y, mb_strtoupper($kode . ' · ' . Str::limit($nama, 40)), $mono, $ukuran, self::REDUP, 0, $ruang);

        $teks = 'BIODATA KANDIDAT · EVO GROUP · ' . self::nomor($halaman) . ' / ' . self::nomor($total);
        $lebar = $fm->getTextWidth($teks, $mono, $ukuran, 0, $ruang);
        $kanvas->text($kanan - $lebar, $y, $teks, $mono, $ukuran, self::REDUP, 0, $ruang);
    }

    /** "3" → "03". Rancangan menomori halaman dengan dua digit. */
    private static function nomor(int $n): string
    {
        return str_pad((string) max(0, $n), 2, '0', STR_PAD_LEFT);
    }
}
