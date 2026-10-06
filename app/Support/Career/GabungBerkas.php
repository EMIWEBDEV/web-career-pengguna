<?php

namespace App\Support\Career;

use Illuminate\Support\Facades\Log;
use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfParser\PdfParserException;

/**
 * WEB CAREER — penggabung PDF untuk BERKAS SELEKSI KANDIDAT.
 *
 * Berkas seleksi terdiri dari dua jenis halaman yang tidak bisa dirender oleh
 * satu mesin yang sama:
 *
 *   1. Halaman BUATAN SISTEM — sampul, data kandidat, hasil wawancara, indeks
 *      lampiran. Dirender dompdf dari blade bergaya rancangan.
 *   2. Halaman BAWAAN — hasil psikotes dari CAT dan lampiran yang diunggah
 *      (KTP, ijazah, sertifikat, lembar penilaian FGD). Sebagian sudah berupa
 *      PDF, sebagian gambar.
 *
 * Yang kedua digabungkan APA ADANYA: tidak diberi kop, kaki, maupun nomor
 * halaman. Menempelkan bingkai rancangan di atas dokumen yang sudah punya
 * tata letaknya sendiri hanya menghasilkan dua kerangka yang saling tumpang
 * tindih — dan untuk berkas hasil pindaian, menutupi isinya.
 *
 * ── BERKAS YANG TIDAK TERBACA DIGANTI PENANDA, BUKAN DIHILANGKAN ───────────
 *
 * FPDI tidak bisa membuka PDF terenkripsi maupun PDF berversi lebih baru dari
 * 1.7 — dan berkas semacam itu memang beredar: hasil pindaian aplikasi ponsel,
 * ijazah ber-watermark dari kampus, surat berpengaman dari rumah sakit.
 *
 * Berkas seperti itu diganti SATU HALAMAN PENANDA yang menyebut namanya dan
 * alasannya. Melewatinya diam-diam jauh lebih berbahaya: pembaca berkas —
 * biasanya direksi yang memutuskan — akan menyimpulkan dokumen itu memang
 * tidak pernah diserahkan kandidat, padahal ia ada dan hanya gagal diproses.
 */
class GabungBerkas
{
    /** Ukuran A4 dalam milimeter — satuan yang dipakai FPDF. */
    private const A4_LEBAR = 210.0;

    private const A4_TINGGI = 297.0;

    /** Sisa tepi halaman penanda & halaman gambar. */
    private const TEPI = 12.0;

    /** Gambar yang bisa ditanam langsung sebagai halaman. */
    private const GAMBAR = ['jpg', 'jpeg', 'png'];

    /**
     * Gabungkan beberapa dokumen PDF menjadi satu.
     *
     * @param  list<array{isi?:string, path?:string, jenis?:string, nama?:string, label?:string}>  $bagian
     *         `isi` = byte PDF/gambar di memori; `path` = berkas di cakram.
     *         `jenis` = 'pdf' | 'gambar' | 'penanda'.
     * @return string byte PDF hasil gabungan
     */
    public static function rakit(array $bagian): string
    {
        $pdf = new Fpdi();
        $pdf->SetAutoPageBreak(false);
        $pdf->SetCreator('EVO Group — Web Careers');
        $pdf->SetTitle('Berkas Seleksi Kandidat');

        foreach ($bagian as $b) {
            $jenis = $b['jenis'] ?? 'pdf';

            try {
                match ($jenis) {
                    'gambar' => self::halamanGambar($pdf, $b),
                    'penanda' => self::halamanPenanda($pdf, $b['label'] ?? 'Dokumen', $b['nama'] ?? '', $b['alasan'] ?? ''),
                    default => self::halamanPdf($pdf, $b),
                };
            } catch (\Throwable $e) {
                // Satu berkas rusak tidak boleh menggugurkan seluruh dokumen —
                // kandidat bisa punya belasan lampiran dan hanya satu yang
                // bermasalah.
                Log::channel('web_career')->warning('[BERKAS-SELEKSI] bagian gagal digabung', [
                    'label' => $b['label'] ?? '-',
                    'nama' => $b['nama'] ?? '-',
                    'pesan' => $e->getMessage(),
                ]);

                self::halamanPenanda(
                    $pdf,
                    $b['label'] ?? 'Dokumen',
                    $b['nama'] ?? '',
                    self::alasanRamah($e),
                );
            }
        }

        // Dokumen tanpa satu halaman pun membuat FPDF melempar galat saat
        // ditutup. Bisa terjadi bila seluruh bagian gagal — halaman
        // pemberitahuan lebih baik daripada berkas nol byte.
        if ($pdf->PageNo() === 0) {
            self::halamanPenanda($pdf, 'Berkas Seleksi', '', 'Tidak ada dokumen yang dapat ditampilkan.');
        }

        return $pdf->Output('S');
    }

    /**
     * Berapa halaman sebuah PDF — dipakai menghitung total di sampul.
     *
     * Nol berarti tidak terbaca; pemanggil memperlakukannya sebagai satu
     * halaman penanda.
     */
    public static function jumlahHalaman(string $isi): int
    {
        $tmp = null;

        try {
            $tmp = self::keBerkasSementara($isi);
            $pdf = new Fpdi();

            return $pdf->setSourceFile($tmp);
        } catch (\Throwable $e) {
            return 0;
        } finally {
            if ($tmp && is_file($tmp)) {
                @unlink($tmp);
            }
        }
    }

    /** Apakah ekstensi ini berupa gambar yang bisa ditanam langsung. */
    public static function berupaGambar(?string $ext): bool
    {
        return in_array(strtolower(trim((string) $ext, '. ')), self::GAMBAR, true);
    }

    // ══ PENYUSUN HALAMAN ═══════════════════════════════════════════════════

    /**
     * Impor seluruh halaman sebuah PDF apa adanya.
     *
     * Ukuran tiap halaman diambil dari halaman sumbernya, bukan dipaksa A4:
     * lembar F4 dari CAT dan hasil pindaian berukuran ganjil akan terpotong
     * kalau dijejalkan ke ukuran yang bukan miliknya.
     */
    private static function halamanPdf(Fpdi $pdf, array $b): void
    {
        $tmp = null;

        try {
            $tmp = isset($b['path']) && is_file($b['path'])
                ? $b['path']
                : self::keBerkasSementara((string) ($b['isi'] ?? ''));

            $jumlah = $pdf->setSourceFile($tmp);

            for ($i = 1; $i <= $jumlah; $i++) {
                $tpl = $pdf->importPage($i, '/MediaBox');
                $ukuran = $pdf->getTemplateSize($tpl);

                $pdf->AddPage(
                    $ukuran['orientation'] ?? ($ukuran['width'] > $ukuran['height'] ? 'L' : 'P'),
                    [$ukuran['width'], $ukuran['height']],
                );
                $pdf->useTemplate($tpl);
            }
        } finally {
            // Hanya berkas sementara buatan sendiri yang dihapus; berkas milik
            // pemanggil dibiarkan.
            if ($tmp && ! isset($b['path']) && is_file($tmp)) {
                @unlink($tmp);
            }
        }
    }

    /**
     * Gambar sebagai satu halaman penuh.
     *
     * Diperkecil proporsional agar muat utuh — foto KTP yang terpotong
     * separuh tidak bisa dipakai memverifikasi apa pun.
     */
    private static function halamanGambar(Fpdi $pdf, array $b): void
    {
        $tmp = null;

        try {
            $tmp = isset($b['path']) && is_file($b['path'])
                ? $b['path']
                : self::keBerkasSementara((string) ($b['isi'] ?? ''), 'img');

            $info = @getimagesize($tmp);

            if (! $info || $info[0] < 1 || $info[1] < 1) {
                throw new \RuntimeException('Berkas gambar tidak terbaca.');
            }

            [$lebarPx, $tinggiPx] = $info;

            // Tipe diambil dari ISI berkas, bukan dari namanya. Berkas
            // sementara buatan tempnam() selalu berakhiran '.tmp', dan FPDF
            // menolak dengan "Unsupported image type: tmp" — padahal gambarnya
            // baik-baik saja. Nama unggahan pun tak bisa dipercaya: berkas PNG
            // kerap diberi nama .jpg oleh kandidat.
            $tipe = match ($info[2] ?? 0) {
                IMAGETYPE_PNG => 'PNG',
                IMAGETYPE_GIF => 'GIF',
                IMAGETYPE_JPEG => 'JPG',
                default => throw new \RuntimeException('Jenis gambar tidak didukung.'),
            };

            // Gambar melintang dicetak melintang — memutar halaman jauh lebih
            // baik daripada mengecilkan gambar sampai tak terbaca.
            $melintang = $lebarPx > $tinggiPx;
            $halLebar = $melintang ? self::A4_TINGGI : self::A4_LEBAR;
            $halTinggi = $melintang ? self::A4_LEBAR : self::A4_TINGGI;

            $muatLebar = $halLebar - (2 * self::TEPI);
            $muatTinggi = $halTinggi - (2 * self::TEPI);

            $skala = min($muatLebar / $lebarPx, $muatTinggi / $tinggiPx);
            $lebar = $lebarPx * $skala;
            $tinggi = $tinggiPx * $skala;

            $pdf->AddPage($melintang ? 'L' : 'P', [$halLebar, $halTinggi]);
            $pdf->Image(
                $tmp,
                ($halLebar - $lebar) / 2,
                ($halTinggi - $tinggi) / 2,
                $lebar,
                $tinggi,
                $tipe,
            );
        } finally {
            if ($tmp && ! isset($b['path']) && is_file($tmp)) {
                @unlink($tmp);
            }
        }
    }

    /**
     * Halaman pengganti untuk berkas yang tidak dapat digabungkan.
     *
     * Digambar dengan primitif FPDF, bukan dompdf: kelas ini dipanggil di
     * tengah penggabungan, saat dompdf sudah selesai bekerja. Warnanya
     * mengikuti palet rancangan (navy #0F172A, emas #A37A2C) supaya halaman ini
     * terbaca sebagai bagian dokumen, bukan sebagai galat sistem.
     */
    private static function halamanPenanda(Fpdi $pdf, string $label, string $nama, string $alasan): void
    {
        $pdf->AddPage('P', [self::A4_LEBAR, self::A4_TINGGI]);

        // Garis emas di kepala halaman — penanda visual yang sama dengan sampul.
        $pdf->SetFillColor(163, 122, 44);
        $pdf->Rect(0, 0, self::A4_LEBAR, 3, 'F');

        $tengah = self::A4_TINGGI / 2 - 40;

        $pdf->SetFont('Helvetica', 'B', 9);
        $pdf->SetTextColor(163, 122, 44);
        $pdf->SetXY(self::TEPI, $tengah);
        $pdf->Cell(self::A4_LEBAR - 2 * self::TEPI, 6, self::teks('LAMPIRAN TIDAK DAPAT DITAMPILKAN'), 0, 1, 'C');

        $pdf->SetFont('Helvetica', 'B', 16);
        $pdf->SetTextColor(15, 23, 42);
        $pdf->SetX(self::TEPI);
        $pdf->Cell(self::A4_LEBAR - 2 * self::TEPI, 12, self::teks($label), 0, 1, 'C');

        if (trim($nama) !== '') {
            $pdf->SetFont('Helvetica', '', 10);
            $pdf->SetTextColor(100, 116, 139);
            $pdf->SetX(self::TEPI);
            $pdf->Cell(self::A4_LEBAR - 2 * self::TEPI, 7, self::teks($nama), 0, 1, 'C');
        }

        // Kotak alasan — supaya yang membaca tahu apa yang harus dilakukan.
        $pdf->Ln(6);
        $pdf->SetFillColor(248, 250, 252);
        $pdf->SetDrawColor(226, 232, 240);
        $kotakY = $pdf->GetY();
        $pdf->Rect(self::TEPI + 15, $kotakY, self::A4_LEBAR - 2 * (self::TEPI + 15), 34, 'FD');

        $pdf->SetFont('Helvetica', '', 9.5);
        $pdf->SetTextColor(71, 85, 105);
        $pdf->SetXY(self::TEPI + 21, $kotakY + 6);
        $pdf->MultiCell(
            self::A4_LEBAR - 2 * (self::TEPI + 21),
            5,
            self::teks(
                ($alasan !== '' ? $alasan . ' ' : '')
                . 'Berkas aslinya tetap tersimpan pada sistem rekrutmen dan dapat dibuka '
                . 'langsung melalui halaman kandidat.'
            ),
            0,
            'C',
        );

        $pdf->SetTextColor(0, 0, 0);
    }

    // ══ UTILITAS ═══════════════════════════════════════════════════════════

    /**
     * Tulis byte ke berkas sementara.
     *
     * FPDI menuntut jalur berkas, bukan aliran di memori: pembaca rujukan
     * silangnya melompat maju-mundur di dalam berkas, dan itu butuh berkas
     * yang bisa dicari posisinya.
     */
    private static function keBerkasSementara(string $isi, string $awalan = 'pdf'): string
    {
        if ($isi === '') {
            throw new \RuntimeException('Isi berkas kosong.');
        }

        $path = tempnam(sys_get_temp_dir(), 'wc-' . $awalan);

        if ($path === false || file_put_contents($path, $isi) === false) {
            throw new \RuntimeException('Gagal menulis berkas sementara.');
        }

        return $path;
    }

    /**
     * Ubah galat teknis menjadi kalimat yang berguna bagi pembaca dokumen.
     *
     * "PdfParserException: unsupported compression" tidak menolong siapa pun
     * yang sedang memutuskan kelanjutan seorang pelamar.
     */
    private static function alasanRamah(\Throwable $e): string
    {
        $pesan = strtolower($e->getMessage());

        return match (true) {
            $e instanceof PdfParserException && str_contains($pesan, 'encrypt') => 'Dokumen ini terkunci sandi sehingga tidak dapat digabungkan.',
            str_contains($pesan, 'encrypt') || str_contains($pesan, 'password') => 'Dokumen ini terkunci sandi sehingga tidak dapat digabungkan.',
            str_contains($pesan, 'compression') || str_contains($pesan, 'version') => 'Format dokumen ini belum didukung penggabung berkas.',
            str_contains($pesan, 'kosong') || str_contains($pesan, 'not found') => 'Berkas tidak ditemukan pada penyimpanan.',
            default => 'Dokumen tidak dapat dibaca oleh penggabung berkas.',
        };
    }

    /**
     * Siapkan teks untuk font bawaan FPDF.
     *
     * Font inti FPDF memakai CP1252, sedangkan data kandidat datang sebagai
     * UTF-8. Tanpa konversi, nama berkas ber-aksen tercetak sebagai simbol
     * acak. Karakter di luar CP1252 diganti '?' — itu perilaku iconv, dan
     * jauh lebih baik daripada halaman yang gagal tercetak.
     */
    private static function teks(string $nilai): string
    {
        $hasil = @iconv('UTF-8', 'CP1252//TRANSLIT', $nilai);

        return $hasil === false ? $nilai : $hasil;
    }
}
