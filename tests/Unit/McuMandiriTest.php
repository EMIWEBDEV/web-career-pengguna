<?php

namespace Tests\Unit;

use App\Support\Career\BiayaAktivitas;
use App\Support\Career\JejakJadwal;
use App\Support\Career\UndanganJadwal;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\TestCase;

/**
 * MCU TANPA VENDOR (mode MANDIRI) + ATURAN BIAYA — aturan intinya.
 *
 * Tiga keputusan yang dikunci di sini, karena ketiganya menentukan apa yang
 * dibaca kandidat atau tercatat sebagai bukti:
 *   - jejak jadwal: jendela Atur Jadwal hanya mencatat BUAT/UBAH (PERPANJANG
 *     milik tombol Perpanjang batas unggah);
 *   - biaya: kapan biaya kandidat DIGANTI (lolos) dan kapan TIDAK;
 *   - batas waktu: "paling lambat Jumat" berarti sepanjang hari Jumat.
 *
 * Tanpa basis data: status MCU → lolos dioper sebagai peta, sama seperti
 * master Flag_Lolos yang sesungguhnya.
 */
class McuMandiriTest extends TestCase
{
    /** Master status MCU (Flag_Lolos) — padanan N_WEB_CAREERS_Master_Mcu_Status. */
    private const LOLOS = [
        'FIT' => true,
        'FIT_WITH_NOTE' => true,
        'TEMPORARY_UNFIT' => false,
        'UNFIT' => false,
    ];

    private function jadwalLama(array $isi = []): object
    {
        return (object) ($isi + [
            'Jadwal_Mulai' => '2026-10-01 09:00:00',
            'Jadwal_Selesai' => '2026-10-07 23:59:00',
            'Jadwal_Mode' => 'MANDIRI',
            'Jadwal_Lokasi_Id' => null,
            'Jadwal_Lokasi_Nama' => null,
        ]);
    }

    // ── JEJAK JADWAL ───────────────────────────────────────────────────────

    public function test_belum_pernah_dijadwalkan_adalah_buat(): void
    {
        $this->assertSame(JejakJadwal::BUAT, JejakJadwal::aksi((object) ['Jadwal_Mulai' => null]));
    }

    public function test_jadwal_yang_disimpan_ulang_adalah_ubah(): void
    {
        // Termasuk batas rentang yang mundur, pindah mode, dan janji temu yang
        // digeser — semuanya perubahan janji. PERPANJANG hanya milik tombol
        // Perpanjang (batas unggah), bukan jendela Atur Jadwal.
        $this->assertSame(JejakJadwal::UBAH, JejakJadwal::aksi($this->jadwalLama()));
        $this->assertSame(JejakJadwal::UBAH, JejakJadwal::aksi($this->jadwalLama(['Jadwal_Mode' => 'LURING', 'Jadwal_Lokasi_Id' => 12])));
    }

    // ── BIAYA ──────────────────────────────────────────────────────────────

    public function test_fit_dan_fit_dengan_catatan_diganti(): void
    {
        foreach (['FIT', 'FIT_WITH_NOTE'] as $status) {
            $x = (object) ['Status' => 'SELESAI', 'Flag_Selesai' => 'Y', 'Mcu_Status' => $status, 'Hasil' => 'LULUS'];

            $this->assertSame(BiayaAktivitas::DIGANTI, BiayaAktivitas::kode($x, self::LOLOS), $status);
        }
    }

    public function test_unfit_dan_belum_layak_sementara_tidak_diganti(): void
    {
        foreach (['UNFIT', 'TEMPORARY_UNFIT'] as $status) {
            $x = (object) ['Status' => 'SELESAI', 'Flag_Selesai' => 'Y', 'Mcu_Status' => $status, 'Hasil' => 'GAGAL'];

            $this->assertSame(BiayaAktivitas::TIDAK_DIGANTI, BiayaAktivitas::kode($x, self::LOLOS), $status);
        }
    }

    public function test_status_mcu_menang_atas_hasil_pada_aktivitas_informatif(): void
    {
        // Aktivitas INFORMATIF tidak diberi Hasil — biayanya tetap ditentukan statusnya.
        $x = (object) ['Status' => 'SELESAI', 'Flag_Selesai' => 'Y', 'Mcu_Status' => 'FIT', 'Hasil' => null];

        $this->assertSame(BiayaAktivitas::DIGANTI, BiayaAktivitas::kode($x, self::LOLOS));
    }

    public function test_belum_selesai_menunggu_dan_tidak_melaksanakan_tanpa_biaya(): void
    {
        $this->assertSame(BiayaAktivitas::MENUNGGU, BiayaAktivitas::kode((object) ['Status' => 'DIJADWALKAN', 'Flag_Selesai' => 'N'], self::LOLOS));
        $this->assertSame(BiayaAktivitas::TANPA, BiayaAktivitas::kode((object) ['Status' => 'TIDAK_HADIR', 'Flag_Selesai' => 'Y', 'Hasil' => 'GAGAL'], self::LOLOS));
    }

    public function test_tipe_tanpa_ketentuan_biaya_tidak_menampilkan_apa_pun(): void
    {
        $x = (object) ['Status' => 'SELESAI', 'Flag_Selesai' => 'Y', 'Hasil' => 'LULUS'];

        $this->assertNull(BiayaAktivitas::status($x, (object) ['Kalimat_Biaya' => null]));
        $this->assertNull(BiayaAktivitas::status($x, null));

        $s = BiayaAktivitas::status($x, (object) ['Kalimat_Biaya' => 'Bayar langsung ke klinik, diganti bila lolos.']);
        $this->assertSame(BiayaAktivitas::DIGANTI, $s['kode']);
        $this->assertNotEmpty($s['hasilKalimat']);
    }

    // ── BATAS WAKTU ────────────────────────────────────────────────────────

    public function test_tanggal_saja_berarti_akhir_hari_itu(): void
    {
        $this->assertSame('2026-10-17 23:59:00', UndanganJadwal::akhirHari('2026-10-17')->format('Y-m-d H:i:s'));
        // Jam yang disebut tetap dihormati (detik dibulatkan).
        $this->assertSame('2026-10-17 10:30:00', UndanganJadwal::akhirHari('2026-10-17 10:30:45')->format('Y-m-d H:i:s'));
    }

    public function test_teks_batas_menyebut_hari_dan_jam_hanya_bila_bukan_akhir_hari(): void
    {
        $this->assertSame('Sabtu, 17 Oktober 2026', UndanganJadwal::teksBatas('2026-10-17 23:59:00'));
        $this->assertSame('Sabtu, 17 Oktober 2026 pukul 10.00 WIB', UndanganJadwal::teksBatas('2026-10-17 10:00:00'));
        $this->assertSame('17 Okt 2026', UndanganJadwal::tanggalPendek('2026-10-17 23:59:00'));
        // Tanggal selalu dua angka — sama dengan rentangnya ("30 September – 07 Oktober 2026").
        $this->assertSame('Rabu, 07 Oktober 2026', UndanganJadwal::teksBatas('2026-10-07 23:59:00'));
        $this->assertSame('07 Okt 2026', UndanganJadwal::tanggalPendek('2026-10-07 23:59:00'));
        $this->assertSame('—', UndanganJadwal::teksBatas(null));
    }

    public function test_batas_bawaan_dari_master_mode(): void
    {
        $sekarang = Carbon::parse('2026-09-30 10:15:00');

        $this->assertSame(
            '2026-10-07 23:59:00',
            UndanganJadwal::batasBawaan((object) ['Batas_Hari_Bawaan' => 7], $sekarang)->format('Y-m-d H:i:s'),
        );
        $this->assertNull(UndanganJadwal::batasBawaan((object) ['Batas_Hari_Bawaan' => null], $sekarang));
        $this->assertTrue(UndanganJadwal::berbatasWaktu((object) ['Flag_Batas_Waktu' => 'Y']));
        $this->assertFalse(UndanganJadwal::berbatasWaktu((object) ['Flag_Batas_Waktu' => 'T']));
        $this->assertFalse(UndanganJadwal::berbatasWaktu(null));
    }

    // ── INSTRUKSI ──────────────────────────────────────────────────────────

    public function test_instruksi_dibuang_gambarnya_dan_teksnya_berstruktur(): void
    {
        [$html, $teks] = UndanganJadwal::saringInstruksi(
            '<p>Pemeriksaan wajib:</p><ul><li>Darah lengkap</li><li>Rontgen thorax</li></ul>'
            .'<p><img src="/api/v1/karir/lamaran/catatan/gambar/abc"></p><script>alert(1)</script>',
        );

        $this->assertStringNotContainsString('<img', (string) $html);
        $this->assertStringNotContainsString('<script', (string) $html);
        $this->assertStringContainsString('• Darah lengkap', (string) $teks);
        $this->assertStringContainsString('• Rontgen thorax', (string) $teks);
    }

    public function test_tanpa_html_teks_polos_dipakai_apa_adanya(): void
    {
        $this->assertSame([null, 'Bawa KTP asli.'], UndanganJadwal::saringInstruksi(null, '  Bawa KTP asli. '));
        $this->assertSame([null, null], UndanganJadwal::saringInstruksi('', ''));
    }
}
