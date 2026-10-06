<?php

namespace Tests\Unit;

use App\Support\Career\CatatanEksternal;
use PHPUnit\Framework\TestCase;

/**
 * Catatan keputusan UNTUK KANDIDAT — tiga hal yang harus tetap benar tanpa
 * basis data: apa yang boleh disimpan, bagaimana ia dibacakan di email, dan
 * tab mana yang terbuka lebih dulu bagi tiap jenis akun.
 */
class CatatanEksternalTest extends TestCase
{
    public function test_gambar_dibuang_tautan_dipertahankan(): void
    {
        $html = '<p>Link Zoom: <a href="https://zoom.us/j/123">masuk di sini</a></p>'
            .'<p><img src="/api/v1/karir/lamaran/catatan/gambar/abc123"></p>';

        $hasil = CatatanEksternal::saring($html);

        $this->assertStringNotContainsString('<img', $hasil);
        $this->assertStringNotContainsString('<p></p>', $hasil);
        $this->assertStringContainsString('href="https://zoom.us/j/123"', $hasil);
    }

    public function test_hanya_gambar_atau_kosong_berarti_tidak_ada_catatan(): void
    {
        $this->assertNull(CatatanEksternal::saring('<p><img src="/api/v1/karir/lamaran/catatan/gambar/abc123"></p>'));
        $this->assertNull(CatatanEksternal::saring('<p><br></p>'));
        $this->assertNull(CatatanEksternal::saring(null));
    }

    public function test_skrip_tidak_pernah_lolos(): void
    {
        $hasil = (string) CatatanEksternal::saring('<p onclick="x()">Halo<script>alert(1)</script></p><a href="javascript:alert(1)">klik</a>');

        $this->assertStringNotContainsString('<script', $hasil);
        $this->assertStringNotContainsString('onclick', $hasil);
        $this->assertStringNotContainsString('javascript:', $hasil);
    }

    public function test_teks_surat_mempertahankan_paragraf_butir_dan_alamat_tautan(): void
    {
        $teks = CatatanEksternal::keTeksSurat(
            '<p>Psikotes online hari <strong>Senin</strong>.</p>'
            .'<p><a href="https://zoom.us/j/123?pwd=a&amp;b=1">Link Zoom</a></p>'
            .'<ul><li>Siapkan KTP</li><li>Masuk 10 menit lebih awal</li></ul>'
        );

        $this->assertSame(
            "Psikotes online hari Senin.\n\nLink Zoom (https://zoom.us/j/123?pwd=a&b=1)\n\n• Siapkan KTP\n• Masuk 10 menit lebih awal",
            $teks,
        );
    }

    public function test_tautan_telanjang_tidak_diulang(): void
    {
        $this->assertSame(
            'https://meet.google.com/abc',
            CatatanEksternal::keTeksSurat('<p><a href="https://meet.google.com/abc">https://meet.google.com/abc</a></p>'),
        );
        $this->assertNull(CatatanEksternal::keTeksSurat(''));
    }

    public function test_tab_bawaan_menurut_kategori_akun(): void
    {
        $this->assertSame('INTERNAL', CatatanEksternal::tabBawaan(['REKRUTMEN']));
        $this->assertSame('INTERNAL', CatatanEksternal::tabBawaan([' rekrutmen ', 'REKRUTMEN']));
        $this->assertSame('EKSTERNAL', CatatanEksternal::tabBawaan(['MT']));
        $this->assertSame('EKSTERNAL', CatatanEksternal::tabBawaan(['INTERNSHIP']));
        $this->assertSame('EKSTERNAL', CatatanEksternal::tabBawaan(['REKRUTMEN', 'MT']));
        $this->assertSame('EKSTERNAL', CatatanEksternal::tabBawaan(['INTERNSHIP', 'MT', 'REKRUTMEN']));
        // Tidak dibatasi = memegang seluruhnya.
        $this->assertSame('EKSTERNAL', CatatanEksternal::tabBawaan(null));
        $this->assertSame('EKSTERNAL', CatatanEksternal::tabBawaan([]));
    }
}
