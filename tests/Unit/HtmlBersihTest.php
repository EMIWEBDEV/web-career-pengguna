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
    public function test_format_yang_diizinkan_dipertahankan(): void
    {
        $this->assertSame(
            '<p>Halo <strong>dunia</strong></p>',
            HtmlBersih::saring('<p>Halo <strong>dunia</strong></p>')
        );

        $this->assertSame(
            '<ol><li>satu</li><li>dua</li></ol>',
            HtmlBersih::saring('<ol><li>satu</li><li>dua</li></ol>')
        );
    }

    public function test_atribut_kejadian_dibuang(): void
    {
        $hasil = HtmlBersih::saring('<p onmouseover="jahat()">hover</p>');

        $this->assertSame('<p>hover</p>', $hasil);
        $this->assertStringNotContainsString('onmouseover', (string) $hasil);
    }

    public function test_tag_berbahaya_dibuang_beserta_isinya(): void
    {
        // Isi <script> tidak boleh "diselamatkan" jadi teks — kalau di-unwrap,
        // kode skripnya justru tampil sebagai tulisan di halaman.
        $this->assertSame('<p>aman</p>', HtmlBersih::saring('<script>alert(1)</script><p>aman</p>'));

        $this->assertNull(HtmlBersih::saring('<iframe src="https://jahat.id"></iframe>'));
        $this->assertNull(HtmlBersih::saring('<img src=x onerror=alert(1)>'));
    }

    public function test_tautan_berskema_berbahaya_kehilangan_status_tautan(): void
    {
        // Teksnya tetap terbaca, tapi tidak lagi bisa diklik.
        $this->assertSame('klik', HtmlBersih::saring('<a href="javascript:alert(1)">klik</a>'));
        $this->assertSame('klik', HtmlBersih::saring('<a href="data:text/html,<script>1</script>">klik</a>'));
    }

    public function test_tautan_sah_dipaksa_aman(): void
    {
        $hasil = (string) HtmlBersih::saring('<a href="https://evo.co.id" onclick="alert(1)" class="x">situs</a>');

        $this->assertStringContainsString('href="https://evo.co.id"', $hasil);
        $this->assertStringContainsString('rel="noopener noreferrer"', $hasil);
        $this->assertStringContainsString('target="_blank"', $hasil);
        $this->assertStringNotContainsString('onclick', $hasil);
        $this->assertStringNotContainsString('class', $hasil);
    }

    public function test_tag_di_luar_daftar_izin_di_unwrap(): void
    {
        // Tulisan admin tidak boleh hilang hanya karena format tak didukung.
        $this->assertSame('tetap terbaca', HtmlBersih::saring('<div style="color:red"><span>tetap terbaca</span></div>'));
        $this->assertSame('terlalu besar<h3>boleh</h3>', HtmlBersih::saring('<h1>terlalu besar</h1><h3>boleh</h3>'));
    }

    public function test_editor_kosong_jadi_null(): void
    {
        // Quill mengirim "<p><br></p>" untuk editor yang dibiarkan kosong.
        $this->assertNull(HtmlBersih::saring('<p><br></p>'));
        $this->assertNull(HtmlBersih::saring('<p></p>'));
        $this->assertNull(HtmlBersih::saring(''));
        $this->assertNull(HtmlBersih::saring(null));
    }

    public function test_isi_dengan_jeda_baris_asli_dipertahankan(): void
    {
        $this->assertSame(
            '<p>Baris 1<br>Baris 2</p>',
            HtmlBersih::saring('<p>Baris 1<br>Baris 2</p>')
        );
    }

    /**
     * GAMBAR DALAM CATATAN PENILAIAN.
     *
     * Sejak catatan hasil wawancara & tes offline ditulis di editor berformat,
     * penilai bisa menempelkan foto lembar penilaian. Yang boleh bertahan hanya
     * gambar MILIK KITA yang disajikan lewat rute berwenang — sisanya dibuang
     * seluruhnya, bukan disisakan sebagai elemen tanpa src (ikon rusak di
     * tengah catatan).
     */
    public function test_gambar_internal_dipertahankan_dan_dijinakkan(): void
    {
        $hasil = (string) HtmlBersih::saring(
            '<p>Baik</p><img src="/api/v1/karir/lamaran/catatan/gambar/abc123" onerror="alert(1)" style="width:9999px">'
        );

        $this->assertStringContainsString('src="/api/v1/karir/lamaran/catatan/gambar/abc123"', $hasil);
        $this->assertStringContainsString('alt="Lampiran catatan"', $hasil);
        $this->assertStringContainsString('loading="lazy"', $hasil);
        $this->assertStringNotContainsString('onerror', $hasil);
        $this->assertStringNotContainsString('style', $hasil);
    }

    public function test_gambar_luar_dan_data_uri_dibuang(): void
    {
        // Host luar: setiap pembaca catatan akan mengirim jejak ke pemiliknya,
        // dan isi gambarnya bisa diganti setelah catatan disetujui.
        $this->assertSame('<p>x</p>', HtmlBersih::saring('<p>x</p><img src="https://jahat.example/lacak.png">'));

        // data: URI — satu potret ponsel jadi ~2,7 MB base64 di dalam kolom.
        $this->assertSame('<p>y</p>', HtmlBersih::saring('<p>y</p><img src="data:image/png;base64,AAAA">'));

        // Bentuk tautan yang MIRIP tapi bukan rute kita.
        $this->assertSame('<p>z</p>', HtmlBersih::saring('<p>z</p><img src="/api/v1/karir/lamaran/catatan/gambar/abc/../../rahasia">'));
    }

    public function test_catatan_berisi_gambar_saja_tidak_dianggap_kosong(): void
    {
        // Catatan wawancara yang isinya cuma foto lembar penilaian tidak punya
        // satu huruf pun — tapi jelas bukan catatan kosong. Sebelum ini ia
        // dibuang diam-diam karena penilaian kekosongan hanya melihat teks.
        $this->assertNotNull(HtmlBersih::saring('<img src="/api/v1/karir/lamaran/catatan/gambar/xyz">'));
    }

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
