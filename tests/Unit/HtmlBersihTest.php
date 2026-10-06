<?php

namespace Tests\Unit;

use App\Support\Career\HtmlBersih;
use PHPUnit\Framework\TestCase;

/**
 * Penyaring HTML jawaban detail FAQ.
 *
 * Ini gerbang tulis untuk konten yang nantinya dirender dengan v-html di
 * halaman PUBLIK /karir/faq. Tanpa uji, satu perubahan kecil pada daftar-izin
 * bisa membuka jalan XSS tersimpan tanpa ada yang menyadarinya.
 */
class HtmlBersihTest extends TestCase
{

    public function test_ke_teks_memisahkan_blok_dengan_spasi(): void
    {
        // Dipakai untuk pencarian di halaman FAQ: kalau tag dihapus tanpa spasi,
        // "MT:</p><p>Form" jadi "MT:Form" dan kata "Form" tak lagi ditemukan.
        $this->assertSame(
            'Alur MT : Form 1',
            HtmlBersih::keTeks('<p>Alur <b>MT</b>:</p><ul><li>Form 1</li></ul>')
        );

        $this->assertSame('', HtmlBersih::keTeks('<p><br></p>'));
        $this->assertSame('', HtmlBersih::keTeks(null));
    }
}
