<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Membuat kartu pratinjau 1200x630 (public/og/og-default.jpg) dari logo EVO.
 *
 * KENAPA DIBUAT, BUKAN MEMAKAI LOGO LANGSUNG?
 * WhatsApp menolak/merusak gambar berlatar transparan — area transparan
 * dirender HITAM, jadi logo emas EVO berubah jadi kotak hitam pekat di kartu
 * pratinjau. Selain itu logo aslinya persegi 3051x3051 dan berukuran ~240 KB;
 * WhatsApp memotongnya jadi kotak kecil dan sebagian klien melewatkan gambar
 * yang terlalu besar. Kartu ini menjawab keduanya: JPEG solid, rasio 1.91:1
 * (rasio yang memang dipakai kotak pratinjau WhatsApp/Facebook), di bawah
 * 150 KB.
 *
 * Berkas hasilnya IKUT DI-COMMIT — supaya staging dan produksi tidak perlu
 * menjalankan perintah ini saat deploy. Jalankan ulang hanya bila logo atau
 * teksnya berubah:
 *
 *     php artisan seo:og-image
 */
class BuatGambarOg extends Command
{
    protected $signature = 'seo:og-image
                            {--logo= : Path logo sumber relatif ke public/ (default: config seo.organization_logo)}
                            {--out= : Path keluaran relatif ke public/ (default: config seo.image)}
                            {--font= : Path berkas .ttf untuk teks kartu}';

    protected $description = 'Membuat kartu pratinjau Open Graph 1200x630 dari logo EVO Group';

    /** Emas EVO, diambil dari logo. */
    private const EMAS = [0xD9, 0xA5, 0x2B];

    private const EMAS_TUA = [0xB5, 0x82, 0x18];

    private const SLATE = [0x0F, 0x17, 0x2A];

    private const ABU = [0x64, 0x74, 0x8B];

    public function handle(): int
    {
        if (! extension_loaded('gd')) {
            $this->error('Ekstensi GD tidak aktif — perintah ini membutuhkannya.');

            return self::FAILURE;
        }

        $logoPath = public_path($this->option('logo') ?: config('seo.organization_logo', 'logo/EVOGROUP.png'));
        $outPath = public_path($this->option('out') ?: config('seo.image', 'og/og-default.jpg'));
        $font = $this->cariFont($this->option('font'));

        if (! is_file($logoPath)) {
            $this->error("Logo tidak ditemukan: {$logoPath}");

            return self::FAILURE;
        }

        $lebar = (int) config('seo.image_width', 1200);
        $tinggi = (int) config('seo.image_height', 630);

        $kanvas = imagecreatetruecolor($lebar, $tinggi);
        imagealphablending($kanvas, true);
        imageantialias($kanvas, true);

        $this->latar($kanvas, $lebar, $tinggi);

        // ── Logo ────────────────────────────────────────────────────────────
        $logo = $this->muatGambar($logoPath);
        if (! $logo) {
            imagedestroy($kanvas);
            $this->error('Logo gagal dibaca (format tidak didukung GD).');

            return self::FAILURE;
        }

        $logoTinggi = $font ? 250 : 330;
        $skala = $logoTinggi / imagesy($logo);
        $logoLebar = (int) round(imagesx($logo) * $skala);
        $logoX = (int) round(($lebar - $logoLebar) / 2);
        $logoY = $font ? 86 : (int) round(($tinggi - $logoTinggi) / 2) - 10;

        imagecopyresampled($kanvas, $logo, $logoX, $logoY, 0, 0, $logoLebar, $logoTinggi, imagesx($logo), imagesy($logo));
        imagedestroy($logo);

        // ── Teks ────────────────────────────────────────────────────────────
        // Tanpa font TTF, kartu tetap sah — hanya berisi logo di atas latar
        // bersih. Lebih baik begitu daripada teks bitmap GD yang buram.
        if ($font) {
            $this->teksTengah($kanvas, $font, 21, self::EMAS_TUA, 'PORTAL KARIER RESMI', $logoY + $logoTinggi + 58, $lebar, 7.0);
            $this->garisTengah($kanvas, $lebar, $logoY + $logoTinggi + 92, 96);
            $this->teksTengah($kanvas, $font, 44, self::SLATE, 'Bangun Karier Bersama Kami', $logoY + $logoTinggi + 152, $lebar);
            $this->teksTengah($kanvas, $font, 24, self::ABU, 'Lowongan · Management Trainee · Magang', $logoY + $logoTinggi + 200, $lebar, 1.0);
        }

        // ── Pita emas bawah ─────────────────────────────────────────────────
        $pita = imagecolorallocate($kanvas, ...self::EMAS);
        imagefilledrectangle($kanvas, 0, $tinggi - 14, $lebar, $tinggi, $pita);

        if (! is_dir(dirname($outPath))) {
            mkdir(dirname($outPath), 0755, true);
        }

        $berhasil = str_ends_with(strtolower($outPath), '.png')
            ? imagepng($kanvas, $outPath, 8)
            : imagejpeg($kanvas, $outPath, 88);

        imagedestroy($kanvas);

        if (! $berhasil) {
            $this->error("Gagal menulis {$outPath}");

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Kartu pratinjau dibuat: %s (%dx%d, %d KB)%s',
            $outPath,
            $lebar,
            $tinggi,
            (int) round(filesize($outPath) / 1024),
            $font ? '' : ' — tanpa teks, font .ttf tidak ditemukan',
        ));

        return self::SUCCESS;
    }

    /** Latar putih hangat dengan sapuan emas lembut di dua sudut. */
    private function latar($kanvas, int $lebar, int $tinggi): void
    {
        $putih = imagecolorallocate($kanvas, 0xFF, 0xFF, 0xFF);
        imagefilledrectangle($kanvas, 0, 0, $lebar, $tinggi, $putih);

        // Gradien vertikal sangat tipis (putih -> krem) supaya kartu tidak
        // terlihat datar total di dalam gelembung chat yang juga putih.
        for ($y = 0; $y < $tinggi; $y++) {
            $t = $y / max(1, $tinggi - 1);
            $warna = imagecolorallocate(
                $kanvas,
                (int) round(255 - 6 * $t),
                (int) round(255 - 10 * $t),
                (int) round(255 - 18 * $t),
            );
            imageline($kanvas, 0, $y, $lebar, $y, $warna);
        }

        $this->sapuan($kanvas, $lebar, $tinggi, [
            [-0.05, -0.10, 0.46, 0.30],
            [1.06, 0.92, 0.40, 0.24],
        ]);
    }

    /**
     * Sapuan emas lembut di sudut — pengganti radial-gradient CSS.
     *
     * Digambar per piksel pada buffer KECIL lalu diperbesar dengan resampling.
     * Cara "tumpuk lingkaran transparan" yang lebih naif tidak bisa dipakai:
     * ratusan elips semitransparan saling menumpuk sehingga pusatnya menjadi
     * emas pekat berbatas tegas, bukan sapuan yang memudar. Buffer kecil +
     * imagecopyresampled memberi gradasi mulus dan sekaligus jauh lebih cepat.
     *
     * @param  array<int, array{0: float, 1: float, 2: float, 3: float}>  $noda
     *                                                                          Tiap noda: [cx, cy, radius, kuat] — cx/cy/radius dalam pecahan lebar kanvas.
     */
    private function sapuan($kanvas, int $lebar, int $tinggi, array $noda): void
    {
        $bw = 160;
        $bh = (int) round($bw * $tinggi / $lebar);

        $buffer = imagecreatetruecolor($bw, $bh);
        imagealphablending($buffer, false);
        imagesavealpha($buffer, true);
        imagefilledrectangle($buffer, 0, 0, $bw, $bh, imagecolorallocatealpha($buffer, 0, 0, 0, 127));

        for ($y = 0; $y < $bh; $y++) {
            for ($x = 0; $x < $bw; $x++) {
                $opasitas = 0.0;
                foreach ($noda as [$cx, $cy, $radius, $kuat]) {
                    $dx = ($x / $bw) - $cx;
                    $dy = (($y / $bh) - $cy) * ($tinggi / $lebar);
                    $jarak = sqrt($dx * $dx + $dy * $dy) / $radius;
                    if ($jarak < 1.0) {
                        // Falloff kuadratik: pekat di pusat, hilang mulus di tepi.
                        $opasitas += $kuat * (1.0 - $jarak) ** 2;
                    }
                }

                if ($opasitas <= 0.0) {
                    continue;
                }

                $alpha = (int) round(127 * (1.0 - min(1.0, $opasitas)));
                imagesetpixel($buffer, $x, $y, imagecolorallocatealpha($buffer, ...array_merge(self::EMAS, [$alpha])));
            }
        }

        imagealphablending($kanvas, true);
        imagecopyresampled($kanvas, $buffer, 0, 0, 0, 0, $lebar, $tinggi, $bw, $bh);
        imagedestroy($buffer);
    }

    private function garisTengah($kanvas, int $lebar, int $y, int $panjang): void
    {
        $warna = imagecolorallocate($kanvas, ...self::EMAS);
        $x = (int) round(($lebar - $panjang) / 2);
        imagefilledrectangle($kanvas, $x, $y, $x + $panjang, $y + 3, $warna);
    }

    /**
     * Tulis teks rata tengah. $spasi menambah jarak antarhuruf (letter-spacing)
     * dengan menggambar per karakter — imagettftext tidak punya opsi itu.
     */
    private function teksTengah($kanvas, string $font, int $ukuran, array $rgb, string $teks, int $y, int $lebar, float $spasi = 0.0): void
    {
        $warna = imagecolorallocate($kanvas, ...$rgb);

        if ($spasi <= 0.0) {
            $kotak = imagettfbbox($ukuran, 0, $font, $teks);
            $x = (int) round(($lebar - ($kotak[2] - $kotak[0])) / 2);
            imagettftext($kanvas, $ukuran, 0, $x, $y, $warna, $font, $teks);

            return;
        }

        $huruf = preg_split('//u', $teks, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $total = 0.0;
        foreach ($huruf as $h) {
            $kotak = imagettfbbox($ukuran, 0, $font, $h);
            $total += ($kotak[2] - $kotak[0]) + $spasi;
        }

        $x = ($lebar - $total + $spasi) / 2;
        foreach ($huruf as $h) {
            imagettftext($kanvas, $ukuran, 0, (int) round($x), $y, $warna, $font, $h);
            $kotak = imagettfbbox($ukuran, 0, $font, $h);
            $x += ($kotak[2] - $kotak[0]) + $spasi;
        }
    }

    /**
     * Cari berkas .ttf yang pasti ada di semua lingkungan.
     *
     * DejaVu ikut terpasang bersama dompdf (dependensi composer proyek ini),
     * jadi tersedia juga di Cloud Run — tidak seperti font sistem Windows yang
     * hanya ada di mesin lokal.
     */
    private function cariFont(?string $pilihan): ?string
    {
        $kandidat = array_filter([
            $pilihan,
            base_path('vendor/dompdf/dompdf/lib/fonts/DejaVuSans-Bold.ttf'),
            base_path('node_modules/pdfjs-dist/standard_fonts/LiberationSans-Bold.ttf'),
        ]);

        foreach ($kandidat as $path) {
            if (is_file($path) && function_exists('imagettftext')) {
                return $path;
            }
        }

        return null;
    }

    private function muatGambar(string $path)
    {
        $info = @getimagesize($path);
        $gambar = match ($info['mime'] ?? '') {
            'image/png' => @imagecreatefrompng($path),
            'image/jpeg' => @imagecreatefromjpeg($path),
            'image/webp' => @imagecreatefromwebp($path),
            'image/gif' => @imagecreatefromgif($path),
            default => null,
        };

        if ($gambar) {
            imagealphablending($gambar, true);
            imagesavealpha($gambar, true);
        }

        return $gambar ?: null;
    }
}
