<?php

namespace Tests\Unit;

use App\Support\Career\JadwalPrivat;
use PHPUnit\Framework\TestCase;

/**
 * JADWAL PRIVAT — aktivitas yang dijadwalkan tim tapi tidak diumumkan.
 *
 * Dua aturan yang dulu tidak saling tahu, dan itulah bocornya:
 *   - portal menyaring lewat Tampil_Kandidat,
 *   - undangan email tidak memeriksa apa pun.
 * Aktivitas yang sengaja disembunyikan dari portal tetap mengumumkan dirinya
 * lewat email. Penyembunyian yang bocor di pintu sebelah bukan penyembunyian.
 *
 * Yang diuji di sini KEPUTUSANNYA, bukan pengiriman emailnya: dua predikat yang
 * dipakai LamaranController (boleh diundang?) dan MasterAlurController (tampil
 * ke kandidat?). Keduanya ditulis ulang persis di sini karena aslinya terkubur
 * di dalam metode privat controller yang menyentuh basis data — dan yang perlu
 * dikunci adalah ATURANNYA, bukan cara ia mengambil barisnya.
 */
class JadwalPrivatTest extends TestCase
{
    /**
     * Padanan penjaga di LamaranController::kirimUndanganJadwal().
     *
     * Keputusan intinya — "tipe ini privat?" — memanggil KODE ASLINYA. Yang
     * ditulis ulang cuma rangkanya, karena aslinya terkubur di metode privat
     * yang menyentuh basis data.
     *
     * Perhatikan `$tampilKandidat` TIDAK ikut menentukan. Lihat
     * test_aktivitas_tersembunyi_tetap_diundang() untuk alasannya.
     */
    private function bolehDiundang(string $kodeTipe, string $tampilKandidat): bool
    {
        return ! JadwalPrivat::untuk($kodeTipe);
    }

    /** Padanan perhitungan Tampil_Kandidat di MasterAlurController::simpanTahap(). */
    private function tampilKandidat(
        string $kodeTipe,
        bool $dicentangAdmin,
        string $flagWajibTampil = 'T',
        bool $unggahKandidat = false,
    ): string {
        if (JadwalPrivat::untuk($kodeTipe) && ! $unggahKandidat) {
            return 'T';
        }

        return ($dicentangAdmin || $flagWajibTampil === 'Y' || $unggahKandidat) ? 'Y' : 'T';
    }

    // ── Undangan email ────────────────────────────────────────────────────

    public function test_negosiasi_tidak_pernah_diundang_lewat_email(): void
    {
        // Sekalipun barisnya terlanjur bertanda tampil — misalnya alur lama yang
        // belum disunting ulang — tipe privat menutupnya lebih dulu.
        $this->assertFalse($this->bolehDiundang('NEGOTIATION', 'Y'));
        $this->assertFalse($this->bolehDiundang('NEGOTIATION', 'T'));
    }

    public function test_aktivitas_biasa_tetap_diundang(): void
    {
        // Perbaikan ini tidak boleh membungkam wawancara & MCU.
        $this->assertTrue($this->bolehDiundang('INTERVIEW', 'Y'));
    }

    public function test_aktivitas_tersembunyi_tetap_diundang(): void
    {
        // PENJAGA ARAH — jangan "dirapikan" jadi false.
        //
        // Aktivitas yang disembunyikan dari portal tapi tetap mengirim undangan
        // terlihat seperti kebocoran. Membungkamnya justru lebih merugikan:
        // aktivitas tersembunyi YANG DIJADWALKAN hampir selalu salah setelan,
        // bukan rahasia — MCU tersembunyi, misalnya. Kandidat tetap harus datang
        // ke pemeriksaan itu, dan emailnya adalah satu-satunya kabar yang
        // tersisa karena portalnya sudah tertutup. Dibungkam, ia tidak diberi
        // tahu sama sekali lalu dianggap mangkir.
        //
        // Yang didiamkan hanya yang MEMANG diniatkan diam (tipe privat).
        $this->assertTrue($this->bolehDiundang('MCU', 'T'));
    }

    // ── Visibilitas di portal ─────────────────────────────────────────────

    public function test_negosiasi_disembunyikan_walau_admin_mencentang_tampil(): void
    {
        $this->assertSame('T', $this->tampilKandidat('NEGOTIATION', dicentangAdmin: true));
    }

    public function test_negosiasi_tetap_tampil_bila_meminta_unggahan_kandidat(): void
    {
        // Menyembunyikan layar yang justru meminta berkas dari kandidat adalah
        // jalan buntu: ia diminta menyerahkan sesuatu lewat halaman yang tidak
        // pernah ada. Pengecualian ini sengaja menang atas privat.
        $this->assertSame('Y', $this->tampilKandidat('NEGOTIATION', dicentangAdmin: false, unggahKandidat: true));
    }

    public function test_tipe_biasa_tidak_ikut_berubah(): void
    {
        $this->assertSame('Y', $this->tampilKandidat('INTERVIEW', dicentangAdmin: true));
        $this->assertSame('T', $this->tampilKandidat('INTERVIEW', dicentangAdmin: false));
    }

    public function test_flag_wajib_tampil_kini_benar_benar_menyala(): void
    {
        // Penjaga ini sudah lama ada di kode tetapi tidak pernah menyala:
        // tipeSemua() tidak mengambil kolomnya, jadi `?? 'T'` selalu menang dan
        // tes online bisa tersimpan sebagai aktivitas tersembunyi — kandidat
        // tak pernah melihat tombol mengerjakannya.
        $this->assertSame('Y', $this->tampilKandidat('HCLEARN_TEST', dicentangAdmin: false, flagWajibTampil: 'Y'));
    }
}
