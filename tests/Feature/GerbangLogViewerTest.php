<?php

namespace Tests\Feature;

use App\Http\Middleware\GerbangLogViewer;
use Tests\TestCase;

/**
 * /log-viewer hanya terbuka bagi yang membawa kunci rahasia.
 *
 * Diuji lewat permintaan HTTP sungguhan, bukan memanggil middleware-nya
 * langsung: yang ingin dijamin adalah bahwa RUTENYA memang terjaga — termasuk
 * /log-viewer/api/*, yang justru menyajikan isi berkas lognya.
 */
class GerbangLogViewerTest extends TestCase
{
    private const KUNCI = 'c0ffee00d15ea5e5c0ffee00d15ea5e5c0ffee00d15ea5e5c0ffee00d15ea5e5';

    protected function setUp(): void
    {
        parent::setUp();
        config(['log-viewer.secret' => self::KUNCI]);
    }

    public function test_tanpa_kunci_dianggap_tidak_ada(): void
    {
        $this->get('/log-viewer')->assertNotFound();
    }

    public function test_kunci_salah_dianggap_tidak_ada(): void
    {
        $this->get('/log-viewer?secret-key=salah')->assertNotFound();
    }

    /**
     * Kunci yang benar hanya berbeda SATU karakter di ujung — memastikan
     * perbandingannya utuh, bukan sekadar mencocokkan awalannya.
     */
    public function test_kunci_hampir_benar_tetap_ditolak(): void
    {
        $this->get('/log-viewer?secret-key=' . substr(self::KUNCI, 0, -1) . 'f')->assertNotFound();
    }

    /**
     * Buka kunci sekali, lalu kembalikan nilai kue tanda masuknya.
     *
     * Kue-nya dipindahkan TANGAN: klien uji Laravel tidak menyimpan kue
     * balasan seperti peramban sungguhan.
     */
    private function bukaKunci(): string
    {
        $resp = $this->get('/log-viewer?secret-key=' . self::KUNCI);

        foreach ($resp->headers->getCookies() as $kue) {
            if ($kue->getName() === GerbangLogViewer::KUE) {
                return (string) $kue->getValue();
            }
        }

        $this->fail('Kue tanda masuk tidak dikirim setelah kunci diterima.');
    }

    public function test_kunci_benar_dialihkan_ke_alamat_tanpa_kunci(): void
    {
        // Kuncinya dibuang dari alamat supaya tidak mengendap di riwayat
        // peramban, header Referer, maupun log akses server.
        $this->get('/log-viewer?secret-key=' . self::KUNCI)
            ->assertRedirect(url('/log-viewer'))
            ->assertCookie(GerbangLogViewer::KUE);
    }

    public function test_kue_tanda_masuk_tidak_memuat_kuncinya(): void
    {
        // Kue ikut terkirim di TIAP permintaan dan tersimpan di cakram
        // peramban. Menaruh kunci mentah di sana sama saja menyebarkannya.
        $this->assertStringNotContainsString(self::KUNCI, $this->bukaKunci());
    }

    public function test_sesudah_kunci_diterima_halamannya_terbuka_tanpa_kunci(): void
    {
        $kue = $this->bukaKunci();

        // Permintaan berikutnya TIDAK membawa kunci — dan harus tetap lolos,
        // kalau tidak Log Viewer (aplikasi satu halaman) tak pernah bisa
        // memuat isinya sendiri.
        $this->withUnencryptedCookie(GerbangLogViewer::KUE, $kue)
            ->get('/log-viewer')
            ->assertOk();
    }

    public function test_api_tertutup_tanpa_kunci(): void
    {
        // BAGIAN TERPENTING: yang benar-benar menyajikan isi log adalah API-nya.
        // Menjaga halamannya saja = mengunci pintu depan, membiarkan jendela.
        $this->getJson('/log-viewer/api/files')->assertNotFound();
    }

    /**
     * withCredentials(): klien uji Laravel TIDAK mengirim kue pada permintaan
     * JSON kecuali diminta (lihat prepareCookiesForJsonRequest — tanpa itu ia
     * mengembalikan array kosong). Peramban sungguhan mengirimnya sendiri pada
     * XHR se-asal, jadi ini murni urusan alat uji, bukan perilaku produksi.
     */
    public function test_api_terbuka_sesudah_kunci_diterima(): void
    {
        $kue = $this->bukaKunci();

        $this->withCredentials()
            ->withUnencryptedCookie(GerbangLogViewer::KUE, $kue)
            ->getJson('/log-viewer/api/files')
            ->assertOk();
    }

    public function test_kue_palsu_ditolak(): void
    {
        $this->withCredentials()
            ->withUnencryptedCookie(GerbangLogViewer::KUE, str_repeat('a', 64))
            ->getJson('/log-viewer/api/files')
            ->assertNotFound();
    }

    public function test_kue_lama_gugur_saat_kunci_diganti(): void
    {
        $kue = $this->bukaKunci();

        // Mengganti kunci di .env harus MENCABUT seluruh tanda masuk yang
        // sudah beredar — kalau tidak, mengganti kunci tidak menutup apa pun.
        config(['log-viewer.secret' => str_repeat('b', 64)]);

        $this->withUnencryptedCookie(GerbangLogViewer::KUE, $kue)
            ->get('/log-viewer')
            ->assertNotFound();
    }

    public function test_kunci_belum_diatur_berarti_tertutup_untuk_semua(): void
    {
        // GAGAL TERTUTUP. Server yang lupa menyetel kuncinya tidak boleh
        // berubah jadi log yang terbuka untuk umum.
        config(['log-viewer.secret' => '']);

        $this->get('/log-viewer')->assertNotFound();
        $this->get('/log-viewer?secret-key=' . self::KUNCI)->assertNotFound();
        $this->getJson('/log-viewer/api/files')->assertNotFound();
    }

    public function test_kunci_kosong_di_query_tidak_membuka_apa_pun(): void
    {
        $this->get('/log-viewer?secret-key=')->assertNotFound();
    }
}
