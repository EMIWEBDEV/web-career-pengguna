<?php

namespace Tests\Unit;

use App\Support\Career\KonfirmasiJadwal;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\TestCase;

/**
 * KOMITMEN JAWABAN KONFIRMASI KEHADIRAN — keputusan user 1 Okt 2026.
 *
 * Kandidat tidak boleh bolak-balik: jawaban PERTAMA bebas sampai jadwal mulai,
 * MENGUBAH jawaban hanya Ubah_Jawaban_Maks kali (bawaan 1) dan paling lambat
 * Ubah_Jawaban_Batas_Jam sebelum jadwal mulai (bawaan 72 = H-3). Dikunci di
 * sini karena aturan inilah yang dibaca kandidat di email, halaman, dan portal.
 *
 * Tanpa basis data: tipe tahap dioper sebagai objek, sama seperti baris
 * Master_Tipe_Tahap yang sesungguhnya.
 */
class KonfirmasiKomitmenTest extends TestCase
{
    private function tipe(int $maks = 1, int $jam = 72): object
    {
        return (object) ['Ubah_Jawaban_Maks' => $maks, 'Ubah_Jawaban_Batas_Jam' => $jam];
    }

    private function sekarang(): Carbon
    {
        return Carbon::parse('2026-10-01 10:00:00');
    }

    public function test_bawaan_sekali_ubah_sampai_h3(): void
    {
        $a = KonfirmasiJadwal::aturanUbah($this->tipe(), '2026-10-08 09:00:00', 0, $this->sekarang());

        $this->assertFalse($a['terkunci']);
        $this->assertNull($a['sebab']);
        $this->assertSame(1, $a['sisa']);
        // H-3 dari jam mulai — bukan dari tengah malam.
        $this->assertSame('2026-10-05 09:00', $a['batas']->format('Y-m-d H:i'));
    }

    public function test_jatah_habis_sesudah_satu_kali_ubah(): void
    {
        $a = KonfirmasiJadwal::aturanUbah($this->tipe(), '2026-10-08 09:00:00', 1, $this->sekarang());

        $this->assertTrue($a['terkunci']);
        $this->assertSame('JATAH', $a['sebab']);
        $this->assertSame(0, $a['sisa']);
    }

    public function test_kurang_dari_h3_terkunci_walau_jatah_masih_ada(): void
    {
        // Dijadwalkan 1 minggu lalu, dibuka lagi H-2: tidak boleh berubah.
        $a = KonfirmasiJadwal::aturanUbah($this->tipe(), '2026-10-03 09:00:00', 0, $this->sekarang());

        $this->assertTrue($a['terkunci']);
        $this->assertSame('WAKTU', $a['sebab']);
    }

    public function test_tepat_di_batas_sudah_terkunci(): void
    {
        $a = KonfirmasiJadwal::aturanUbah($this->tipe(), '2026-10-04 10:00:00', 0, $this->sekarang());

        $this->assertTrue($a['terkunci']);
        $this->assertSame('WAKTU', $a['sebab']);
    }

    public function test_jatah_nol_berarti_sekali_jawab_langsung_final(): void
    {
        $a = KonfirmasiJadwal::aturanUbah($this->tipe(0), '2026-10-20 09:00:00', 0, $this->sekarang());

        $this->assertTrue($a['terkunci']);
        $this->assertSame('SEKALI', $a['sebab']);
    }

    public function test_aturan_per_tipe_dibaca_dari_master(): void
    {
        // Tipe lain boleh lebih longgar: 2 kali, sampai H-2 (48 jam).
        $a = KonfirmasiJadwal::aturanUbah($this->tipe(2, 48), '2026-10-04 09:00:00', 1, $this->sekarang());

        $this->assertFalse($a['terkunci']);
        $this->assertSame(1, $a['sisa']);
        $this->assertSame('2026-10-02 09:00', $a['batas']->format('Y-m-d H:i'));
    }

    public function test_kalimat_untuk_kandidat(): void
    {
        $bebas = KonfirmasiJadwal::aturanUbah($this->tipe(), '2026-10-08 09:00:00', 0, $this->sekarang());
        $this->assertStringContainsString('satu kali', KonfirmasiJadwal::teksUbah($bebas, false));
        $this->assertStringContainsString('3 hari sebelum jadwal', KonfirmasiJadwal::teksUbah($bebas, false));
        $this->assertStringContainsString('Senin, 05 Oktober 2026 pukul 09.00 WIB', KonfirmasiJadwal::teksUbah($bebas, true));

        $mepet = KonfirmasiJadwal::aturanUbah($this->tipe(), '2026-10-03 09:00:00', 0, $this->sekarang());
        $this->assertStringContainsString('langsung final', KonfirmasiJadwal::teksUbah($mepet, false));
        $this->assertStringContainsString('kini final', KonfirmasiJadwal::teksUbah($mepet, true));

        // Ditolak karena terkunci: jalan keluarnya selalu disebut.
        $habis = KonfirmasiJadwal::aturanUbah($this->tipe(), '2026-10-08 09:00:00', 1, $this->sekarang());
        $this->assertStringContainsString('tidak melanjutkan seleksi', KonfirmasiJadwal::pesanKunci($habis));
    }

    public function test_usulan_sendiri_yang_disetujui_langsung_final(): void
    {
        // Keputusan user 2 Okt 2026: waktu ini usulan kandidat yang disetujui
        // tim — jatah & waktu ubah masih ada pun, jawabannya tetap terkunci.
        $a = KonfirmasiJadwal::aturanUbah($this->tipe(), '2026-10-08 09:00:00', 0, $this->sekarang(), true);

        $this->assertTrue($a['terkunci']);
        $this->assertSame('USULAN', $a['sebab']);
        $this->assertStringContainsString('ajukan sendiri', KonfirmasiJadwal::teksUbah($a, true));
        $this->assertStringContainsString('tidak melanjutkan seleksi', KonfirmasiJadwal::pesanKunci($a));
    }

    public function test_jarak_teks(): void
    {
        $this->assertSame('3 hari', KonfirmasiJadwal::jarakTeks(72));
        $this->assertSame('36 jam', KonfirmasiJadwal::jarakTeks(36));
    }
}
