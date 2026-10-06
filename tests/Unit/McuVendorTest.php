<?php

namespace Tests\Unit;

use App\Support\Career\JejakJadwal;
use App\Support\Career\SuratJadwal;
use App\Support\Career\UndanganJadwal;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\TestCase;

/**
 * MCU VENDOR & MANDIRI — rentang tanggal, tautan peta, dan aturan unggah.
 *
 * Yang dikunci di sini adalah hal yang dibaca kandidat atau yang menutup
 * pintunya:
 *   - rentang tanggal di surel & portal ("30 September – 07 Oktober 2026");
 *   - tautan peta HANYA Google Maps — ia terkirim apa adanya lewat surel resmi;
 *   - unggahan MANDIRI lahir dari mode jadwal dan tertutup sesudah batasnya;
 *   - PERPANJANG hanya memundurkan batas unggah — rentang pemeriksaan tetap.
 *
 * Tanpa basis data: definisi mode dioper langsung, sama seperti barisnya di
 * N_WEB_CAREERS_Master_Mode_Jadwal.
 */
class McuVendorTest extends TestCase
{
    private const MANDIRI = [
        'Kode' => 'MANDIRI', 'Nama' => 'Mandiri', 'Flag_Batas_Waktu' => 'Y', 'Flag_Butuh_Tempat' => 'T',
        'Flag_Butuh_Surat' => 'Y', 'Flag_Unggah_Kandidat' => 'Y',
        'Petunjuk_Unggah' => 'Unggah hasil MCU dan foto kwitansi pembayarannya, lalu tekan Kirim.',
    ];

    private const VENDOR = [
        'Kode' => 'VENDOR', 'Nama' => 'Vendor', 'Flag_Batas_Waktu' => 'Y', 'Flag_Butuh_Tempat' => 'Y',
        'Flag_Butuh_Surat' => 'Y', 'Flag_Unggah_Kandidat' => 'T', 'Petunjuk_Unggah' => null,
    ];

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function aktivitas(array $isi = []): object
    {
        return (object) ($isi + [
            'Jadwal_Mode' => 'MANDIRI',
            'Jadwal_Mulai' => '2026-09-11 00:00:00',
            'Jadwal_Selesai' => '2026-09-14 23:59:00',
            'Jadwal_Lokasi_Id' => null,
            'Jadwal_Lokasi_Nama' => null,
            'Unggah_Kandidat' => 'T',
            'Unggah_Wajib' => 'T',
            'Unggah_Format' => null,
            'Unggah_Maks_Mb' => null,
            'Unggah_Petunjuk' => null,
            'Unggah_Kirim_At' => null,
        ]);
    }

    // ── RENTANG TANGGAL ────────────────────────────────────────────────────

    public function test_rentang_bulan_sama_tanpa_nama_hari(): void
    {
        $this->assertSame(
            '11 – 14 September 2026',
            UndanganJadwal::teksRentang('2026-09-11 00:00:00', '2026-09-14 23:59:00'),
        );
        $this->assertSame('11–14 Sep 2026', UndanganJadwal::rentangPendek('2026-09-11', '2026-09-14 23:59:00'));
        // Tanggal selalu dua angka.
        $this->assertSame('01 – 05 Oktober 2026', UndanganJadwal::teksRentang('2026-10-01', '2026-10-05 23:59:00'));
        $this->assertSame('01–05 Okt 2026', UndanganJadwal::rentangPendek('2026-10-01', '2026-10-05'));
    }

    public function test_rentang_lintas_bulan_dan_lintas_tahun(): void
    {
        $this->assertSame(
            '30 September – 07 Oktober 2026',
            UndanganJadwal::teksRentang('2026-09-30', '2026-10-07 23:59:00'),
        );
        $this->assertSame(
            '30 Desember 2026 – 04 Januari 2027',
            UndanganJadwal::teksRentang('2026-12-30', '2027-01-04 23:59:00'),
        );
        $this->assertSame('30 Sep – 07 Okt 2026', UndanganJadwal::rentangPendek('2026-09-30', '2026-10-07'));
        $this->assertSame('30 Des 2026 – 04 Jan 2027', UndanganJadwal::rentangPendek('2026-12-30', '2027-01-04'));
    }

    public function test_rentang_satu_hari_atau_tanpa_awal_disebut_sekali(): void
    {
        $this->assertSame('11 September 2026', UndanganJadwal::teksRentang('2026-09-11', '2026-09-11 23:59:00'));
        $this->assertSame('14 September 2026', UndanganJadwal::teksRentang(null, '2026-09-14 23:59:00'));
        $this->assertSame('—', UndanganJadwal::teksRentang('2026-09-11', null));
    }

    public function test_awal_rentang_dari_tanggal_saja_adalah_awal_hari(): void
    {
        $this->assertSame('2026-09-11 00:00:00', UndanganJadwal::awalHari('2026-09-11')->format('Y-m-d H:i:s'));
        $this->assertSame('2026-09-14 23:59:00', UndanganJadwal::akhirHari('2026-09-14')->format('Y-m-d H:i:s'));
    }

    public function test_sisa_waktu_dibaca_manusia(): void
    {
        $dari = Carbon::parse('2026-09-12 10:00:00');
        $this->assertSame('2 hari lagi', UndanganJadwal::sisaTeks($dari, Carbon::parse('2026-09-14 23:59:00')));
        $this->assertSame('sekitar 5 jam lagi', UndanganJadwal::sisaTeks($dari, Carbon::parse('2026-09-12 15:30:00')));
        $this->assertSame('kurang dari 1 jam lagi', UndanganJadwal::sisaTeks($dari, Carbon::parse('2026-09-12 10:40:00')));
    }

    // ── TAUTAN PETA ────────────────────────────────────────────────────────

    public function test_tautan_google_maps_diterima_dan_dibakukan(): void
    {
        $this->assertSame('https://maps.app.goo.gl/AbCd123', UndanganJadwal::normalkanMaps('https://maps.app.goo.gl/AbCd123'));
        $this->assertSame('https://maps.app.goo.gl/AbCd123', UndanganJadwal::normalkanMaps('  maps.app.goo.gl/AbCd123 '));
        $this->assertSame('https://maps.google.com/?q=RS+Charitas', UndanganJadwal::normalkanMaps('http://maps.google.com/?q=RS+Charitas'));
        $this->assertSame('https://goo.gl/maps/xYz', UndanganJadwal::normalkanMaps('https://goo.gl/maps/xYz'));
        foreach ([
            'https://www.google.com/maps/place/RS+Charitas/@-2.97,104.74,17z',
            'https://www.google.co.id/maps?q=Pramita',
            'https://google.com/maps/search/?api=1&query=Pramita',
        ] as $sah) {
            $this->assertSame($sah, UndanganJadwal::normalkanMaps($sah), $sah);
        }
    }

    public function test_tautan_selain_google_maps_ditolak(): void
    {
        foreach ([
            'https://evil.example/maps/abc',
            'https://google.com.evil.example/maps/x',
            'https://maps.app.goo.gl.evil.example/x',
            'https://www.google.com/search?q=rs',
            'https://goo.gl/abc',
            'javascript:alert(1)',
            'https://maps.app.goo.gl/a b',
        ] as $salah) {
            $this->assertFalse(UndanganJadwal::normalkanMaps($salah), $salah);
        }
        $this->assertNull(UndanganJadwal::normalkanMaps(''));
        $this->assertNull(UndanganJadwal::normalkanMaps(null));
    }

    // ── ATURAN UNGGAH ──────────────────────────────────────────────────────

    public function test_mandiri_meminta_unggahan_dari_modenya(): void
    {
        $a = UndanganJadwal::aturanUnggah($this->aktivitas(), (object) self::MANDIRI, Carbon::parse('2026-09-12 08:00:00'));

        $this->assertSame('MODE', $a['sumber']);
        $this->assertTrue($a['wajib']);
        $this->assertSame(UndanganJadwal::UNGGAH_FORMAT, $a['format']);
        $this->assertSame(UndanganJadwal::UNGGAH_MAKS_MB, $a['maksMb']);
        $this->assertSame(self::MANDIRI['Petunjuk_Unggah'], $a['petunjuk']);
        $this->assertSame('Senin, 14 September 2026', $a['batasTeks']);
        $this->assertFalse($a['tertutup']);
    }

    public function test_vendor_tidak_meminta_apa_pun_dari_kandidat(): void
    {
        $this->assertNull(UndanganJadwal::aturanUnggah($this->aktivitas(['Jadwal_Mode' => 'VENDOR']), (object) self::VENDOR));
    }

    public function test_tanpa_jadwal_mode_tidak_berlaku(): void
    {
        $this->assertNull(UndanganJadwal::aturanUnggah($this->aktivitas(['Jadwal_Mulai' => null]), (object) self::MANDIRI));
    }

    public function test_setelan_alur_tetap_berlaku_dan_didahulukan(): void
    {
        $a = UndanganJadwal::aturanUnggah($this->aktivitas([
            'Jadwal_Mode' => 'VENDOR',
            'Unggah_Kandidat' => 'Y', 'Unggah_Wajib' => 'T', 'Unggah_Format' => 'pdf', 'Unggah_Maks_Mb' => 3,
            'Unggah_Petunjuk' => 'Unggah kwitansi.',
        ]), (object) self::VENDOR, Carbon::parse('2026-09-12 08:00:00'));

        $this->assertSame('ALUR', $a['sumber']);
        $this->assertFalse($a['wajib']);
        $this->assertSame(['pdf'], $a['format']);
        $this->assertSame(3, $a['maksMb']);
        $this->assertSame('Unggah kwitansi.', $a['petunjuk']);
    }

    public function test_kotak_unggah_tertutup_sesudah_batas_kecuali_sudah_dikirim(): void
    {
        $lewat = Carbon::parse('2026-09-15 00:00:00');

        $this->assertTrue(UndanganJadwal::aturanUnggah($this->aktivitas(), (object) self::MANDIRI, $lewat)['tertutup']);
        $this->assertFalse(UndanganJadwal::aturanUnggah(
            $this->aktivitas(['Unggah_Kirim_At' => '2026-09-13 10:00:00']),
            (object) self::MANDIRI,
            $lewat,
        )['tertutup']);
        // Sepanjang hari terakhir masih terbuka.
        $this->assertFalse(UndanganJadwal::aturanUnggah($this->aktivitas(), (object) self::MANDIRI, Carbon::parse('2026-09-14 23:30:00'))['tertutup']);
    }

    // ── SURAT PENGANTAR: BANYAK BERKAS, BATAS JUMLAH UKURAN ─────────────────

    private function surat(int $jumlah, int $byte): array
    {
        return array_map(fn ($i) => ['path' => "surat-jadwal/2026/09/30/s{$i}.pdf", 'nama' => "s{$i}.pdf", 'ukuran' => $byte], range(1, $jumlah));
    }

    public function test_batas_surat_adalah_jumlah_ukuran_bukan_banyaknya(): void
    {
        $mb = 1024 * 1024;

        $this->assertNull(SuratJadwal::galatBatas($this->surat(5, $mb)), 'lima surat @1 MB = 5 MB → boleh');
        $this->assertStringContainsString('melebihi batas 5 MB', (string) SuratJadwal::galatBatas($this->surat(6, $mb)));
        $this->assertStringContainsString('6 MB', (string) SuratJadwal::galatBatas($this->surat(6, $mb)));
        $this->assertNull(SuratJadwal::galatBatas($this->surat(2, 2 * $mb)));
        $this->assertStringContainsString('paling banyak', (string) SuratJadwal::galatBatas($this->surat(SuratJadwal::MAKS_BERKAS + 1, 1024)));
    }

    public function test_daftar_surat_tersimpan_sebagai_json_dan_terbaca_kembali(): void
    {
        $json = SuratJadwal::json($this->surat(2, 1500));
        $baris = (object) ['Jadwal_Surat_Json' => $json];

        $this->assertSame($this->surat(2, 1500), SuratJadwal::daftar($baris));
        $this->assertSame(3000, SuratJadwal::totalByte(SuratJadwal::daftar($baris)));
        $this->assertSame('surat-jadwal/2026/09/30/s2.pdf', SuratJadwal::path($baris, 1));
        $this->assertNull(SuratJadwal::path($baris, 5));
        $this->assertNull(SuratJadwal::json([]));
        $this->assertSame([], SuratJadwal::daftar((object) ['Jadwal_Surat_Json' => null]));
    }

    public function test_path_di_luar_folder_surat_diabaikan(): void
    {
        $baris = (object) ['Jadwal_Surat_Json' => json_encode([
            ['path' => 'apply-form/2026/cv-orang-lain.pdf', 'nama' => 'cv.pdf', 'ukuran' => 10],
            ['path' => 'surat-jadwal/2026/09/30/sah.pdf', 'nama' => 'sah.pdf', 'ukuran' => 10],
        ])];

        $this->assertSame(['surat-jadwal/2026/09/30/sah.pdf'], array_column(SuratJadwal::daftar($baris), 'path'));
    }

    public function test_ukuran_ditulis_manusiawi(): void
    {
        $this->assertSame('1,5 MB', SuratJadwal::teksUkuran(1572864));
        $this->assertSame('350 KB', SuratJadwal::teksUkuran(358400));
        $this->assertSame('1 KB', SuratJadwal::teksUkuran(10));
    }

    // ── INFORMASI BIAYA: DISEMBUNYIKAN / DISUNTING ─────────────────────────

    public function test_kalimat_biaya_mengikuti_pilihan_admin(): void
    {
        $tipe = (object) ['Kalimat_Biaya' => 'Kalimat bawaan tipe.'];

        $this->assertSame('Kalimat bawaan tipe.', UndanganJadwal::kalimatBiaya((object) [], $tipe), 'jadwal lama tanpa salinan');
        $this->assertSame('Disunting admin.', UndanganJadwal::kalimatBiaya((object) ['Jadwal_Kalimat_Biaya' => 'Disunting admin.', 'Jadwal_Tampil_Biaya' => 'Y'], $tipe));
        $this->assertNull(UndanganJadwal::kalimatBiaya((object) ['Jadwal_Kalimat_Biaya' => 'Disunting admin.', 'Jadwal_Tampil_Biaya' => 'T'], $tipe), 'disembunyikan');
        $this->assertNull(UndanganJadwal::kalimatBiaya((object) ['Jadwal_Kalimat_Biaya' => 'x'], (object) ['Kalimat_Biaya' => null]), 'tipe tanpa ketentuan biaya');
    }

    // ── BATAS UNGGAH: DIPERPANJANG TANPA MENGGESER RENTANG ─────────────────

    public function test_batas_unggah_awalnya_akhir_rentang(): void
    {
        $s = $this->aktivitas();

        $this->assertSame('2026-09-14 23:59:00', UndanganJadwal::batasUnggah($s)->format('Y-m-d H:i:s'));
        $this->assertFalse(UndanganJadwal::batasDiperpanjang($s));
        $this->assertNull(UndanganJadwal::batasUnggah($this->aktivitas(['Jadwal_Selesai' => null])));
    }

    public function test_perpanjangan_memundurkan_batas_unggah_bukan_rentang(): void
    {
        $s = $this->aktivitas(['Jadwal_Batas_Unggah' => '2026-09-17 23:59:00']);
        // Rentang pemeriksaan sudah lewat, batas unggahnya belum.
        $a = UndanganJadwal::aturanUnggah($s, (object) self::MANDIRI, Carbon::parse('2026-09-16 09:00:00'));

        $this->assertSame('2026-09-17 23:59:00', $a['batas']);
        $this->assertSame('Kamis, 17 September 2026', $a['batasTeks']);
        $this->assertTrue($a['diperpanjang']);
        $this->assertFalse($a['tertutup']);
        $this->assertSame('11 – 14 September 2026', UndanganJadwal::teksRentang($s->Jadwal_Mulai, $s->Jadwal_Selesai));
        // Sesudah batas perpanjangannya, kotak unggah tetap tertutup.
        $this->assertTrue(UndanganJadwal::aturanUnggah($s, (object) self::MANDIRI, Carbon::parse('2026-09-18 00:00:00'))['tertutup']);
    }

    public function test_perpanjangan_yang_tidak_lebih_lambat_dari_rentang_diabaikan(): void
    {
        // Rentang diubah sesudah perpanjangan menjadi lebih lambat → rentangnya yang berlaku.
        $s = $this->aktivitas(['Jadwal_Selesai' => '2026-09-20 23:59:00', 'Jadwal_Batas_Unggah' => '2026-09-17 23:59:00']);

        $this->assertSame('2026-09-20 23:59:00', UndanganJadwal::batasUnggah($s)->format('Y-m-d H:i:s'));
        $this->assertFalse(UndanganJadwal::batasDiperpanjang($s));
        $this->assertFalse(UndanganJadwal::aturanUnggah($s, (object) self::MANDIRI, Carbon::parse('2026-09-12 08:00:00'))['diperpanjang']);
    }

    // ── JEJAK ──────────────────────────────────────────────────────────────

    public function test_menyimpan_ulang_jadwal_selalu_ubah_bukan_perpanjang(): void
    {
        // Memundurkan rentang lewat Ubah Jadwal adalah perubahan janji. PERPANJANG
        // hanya dicatat tombol Perpanjang (batas unggah).
        $this->assertSame(JejakJadwal::UBAH, JejakJadwal::aksi($this->aktivitas()));
        $this->assertSame(JejakJadwal::UBAH, JejakJadwal::aksi($this->aktivitas(['Jadwal_Mode' => 'VENDOR'])));
        $this->assertSame(JejakJadwal::BUAT, JejakJadwal::aksi($this->aktivitas(['Jadwal_Mulai' => null])));
    }
}
