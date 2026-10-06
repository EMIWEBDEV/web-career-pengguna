<?php

namespace App\Support\Career;

use Dompdf\Canvas;
use Dompdf\Dompdf;
use Dompdf\FontMetrics;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * WEB CAREER — kop & kaki berjalan BERKAS SELEKSI KANDIDAT.
 *
 * Digambar langsung di atas kanvas dompdf lewat page_script, bukan lewat
 * elemen `position: fixed` di blade. Dua alasan:
 *
 *   1. dompdf tidak bisa memberi halaman pertama kop yang berbeda dari
 *      halaman lainnya tanpa menyalakan eksekusi PHP di dalam berkas — dan
 *      berkas ini dirakit dari isian kandidat, jadi menyalakannya tidak
 *      terbayangkan. Di sini pembedaannya cukup satu `if`.
 *   2. Nomor halaman sejati (counter dompdf) hanya tersedia di page_script.
 *
 * Ditulis sebagai KELAS TERSENDIRI, bukan menumpang KopKakiLaporan: yang itu
 * milik laporan biodata — teksnya berbunyi "BIODATA KANDIDAT", marginnya 38px,
 * dan hurufnya Inter. Berkas seleksi memakai Helvetica dengan margin 42px, dan
 * menumpangkan keduanya berarti mengubah satu laporan akan diam-diam mengubah
 * yang lain.
 *
 * SAMPUL & PENUTUP TIDAK DIBERI KOP — keduanya halaman penuh pada rancangan.
 */
class KopKakiBerkas
{
    /** Piksel CSS → poin PDF. */
    private const SKALA = 0.75;

    // Palet rancangan.
    private const EMAS = [0.831, 0.663, 0.227];      // #D4A93A
    private const EMAS_TUA = [0.639, 0.478, 0.173];  // #A37A2C
    private const TINTA = [0.059, 0.090, 0.165];     // #0F172A
    private const REDUP = [0.580, 0.639, 0.722];     // #94A3B8
    private const TEPI = [0.886, 0.910, 0.941];      // #E2E8F0

    /**
     * @param  int  $lewati  banyaknya halaman awal tanpa kop (sampul).
     */
    public static function pasang(Dompdf $dompdf, string $nama, string $kode, int $lewati = 1): void
    {
        try {
            $dompdf->getCanvas()->page_script(
                function (int $halaman, int $total, Canvas $kanvas, FontMetrics $fm) use ($nama, $kode, $lewati) {
                    if ($halaman <= $lewati) {
                        return;
                    }

                    self::kop($halaman, $kanvas, $fm, $nama, $kode);
                    self::kaki($kanvas, $fm, $nama, $kode);
                }
            );
        } catch (\Throwable $e) {
            // Dokumen tanpa kop masih terbaca; dokumen yang gagal terbit tidak.
            Log::channel('web_career')->warning('[BERKAS-SELEKSI] kop/kaki gagal digambar: ' . $e->getMessage());
        }
    }

    private static function px(float $px): float
    {
        return $px * self::SKALA;
    }

    /** Tepi kiri & kanan area cetak — sama dengan margin @page di blade. */
    private static function batas(Canvas $kanvas): array
    {
        return [self::px(42), $kanvas->get_width() - self::px(42)];
    }

    /**
     * KOP — garis emas pendek, nama kandidat, lalu penanda bab di kanan.
     *
     * Nomor halaman SENGAJA TIDAK DICETAK. Berkas ini digabung dengan lampiran
     * dan hasil tes yang halamannya tidak melewati dompdf sama sekali, jadi
     * nomor apa pun yang dicetak di sini akan berhenti di tengah dokumen dan
     * menyesatkan pembacanya. Jumlah halaman keseluruhan diumumkan pada
     * halaman Indeks Dokumen.
     */
    private static function kop(int $halaman, Canvas $kanvas, FontMetrics $fm, string $nama, string $kode): void
    {
        [$kiri, $kanan] = self::batas($kanvas);
        $tebal = $fm->getFont('Helvetica', 'bold');
        $biasa = $fm->getFont('Helvetica', 'normal');
        $y = self::px(34);

        // Garis emas pendek — penanda visual yang sama dengan sampul.
        $kanvas->line($kiri, $y + self::px(5), $kiri + self::px(16), $y + self::px(5), self::EMAS, self::px(2));

        $x = $kiri + self::px(24);
        $kanvas->text($x, $y, 'EVO GROUP', $tebal, self::px(7.5), self::EMAS_TUA, 0, self::px(7.5) * 0.22);

        $x += $fm->getTextWidth('EVO GROUP', $tebal, self::px(7.5), 0, self::px(7.5) * 0.22) + self::px(8);
        $kanvas->text($x, $y, '· BERKAS SELEKSI KANDIDAT', $biasa, self::px(7.5), self::REDUP, 0, self::px(7.5) * 0.18);

        // Kanan: identitas kandidat, supaya halaman yang tercecer dari
        // jilidan tetap bisa dikembalikan ke berkas yang benar.
        $teks = mb_strtoupper(Str::limit($nama, 30) . ' · ' . $kode);
        $ruang = self::px(7.5) * 0.16;
        $lebar = $fm->getTextWidth($teks, $tebal, self::px(7.5), 0, $ruang);
        $kanvas->text($kanan - $lebar, $y, $teks, $tebal, self::px(7.5), self::TINTA, 0, $ruang);

        $kanvas->line($kiri, self::px(56), $kanan, self::px(56), self::TEPI, 0.75);
    }

    /** KAKI — penanda kerahasiaan, sama di semua halaman. */
    private static function kaki(Canvas $kanvas, FontMetrics $fm, string $nama, string $kode): void
    {
        [$kiri, $kanan] = self::batas($kanvas);
        $biasa = $fm->getFont('Helvetica', 'normal');
        $ukuran = self::px(7.5);
        $ruang = $ukuran * 0.14;

        $garis = $kanvas->get_height() - self::px(44);
        $kanvas->line($kiri, $garis, $kanan, $garis, self::TEPI, 0.75);

        $y = $garis + self::px(11);

        $kanvas->text($kiri, $y, mb_strtoupper($kode), $biasa, $ukuran, self::REDUP, 0, $ruang);

        $teks = 'DOKUMEN RAHASIA · EVO GROUP';
        $lebar = $fm->getTextWidth($teks, $biasa, $ukuran, 0, $ruang);
        $kanvas->text($kanan - $lebar, $y, $teks, $biasa, $ukuran, self::REDUP, 0, $ruang);
    }
}
