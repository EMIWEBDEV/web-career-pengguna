<?php

namespace Tests\Unit;

use App\Support\Career\BatasIsi;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\TestCase;

/**
 * Batas pengisian formulir tahap — hitungan yang harus tetap benar tanpa basis
 * data: kapan batas otomatis jatuh, jendela minimum bagi yang masuk terlambat,
 * dan keadaan yang dibaca worklist & portal (terkunci atau cuma terlambat).
 */
class BatasIsiTest extends TestCase
{
    private function tahap(array $isi = []): object
    {
        return (object) ($isi + [
            'Status' => 'BERJALAN',
            'Formulir_Kode' => 'FRM-MT-KELENGKAPAN-DATA',
            'Formulir_Pengisian_Id' => null,
            'Batas_Mode' => BatasIsi::MANUAL,
            'Batas_Aksi' => BatasIsi::KUNCI,
            'Batas_Sumber' => BatasIsi::SUMBER_PROGRAM,
            'Batas_At' => null,
        ]);
    }

    public function test_otomatis_jatuh_pukul_2359_pada_hari_ke_n(): void
    {
        $batas = BatasIsi::jatuhTempo(Carbon::parse('2026-09-28 10:15:00'), 3);

        $this->assertSame('2026-10-01 23:59:59', $batas->format('Y-m-d H:i:s'));
    }

    public function test_masuk_terlambat_diberi_jendela_minimum(): void
    {
        $dibuka = Carbon::parse('2026-10-14 10:00:00');

        // Tanggal program besok malam — kurang dari 2 hari: digeser.
        $this->assertSame(
            '2026-10-16 23:59:59',
            BatasIsi::denganJendelaMinimum(Carbon::parse('2026-10-15 23:59:59'), $dibuka)->format('Y-m-d H:i:s'),
        );
        // Masih jauh: tanggal program dipakai apa adanya.
        $this->assertSame(
            '2026-10-20 23:59:59',
            BatasIsi::denganJendelaMinimum(Carbon::parse('2026-10-20 23:59:59'), $dibuka)->format('Y-m-d H:i:s'),
        );
    }

    public function test_bukan_tahap_formulir_berjalan_tidak_berlaku(): void
    {
        $this->assertNull(BatasIsi::status(null));
        $this->assertNull(BatasIsi::status($this->tahap(['Formulir_Kode' => null])));
        $this->assertNull(BatasIsi::status($this->tahap(['Status' => 'MENUNGGU'])));
        // Kolomnya belum ada (skrip belum dijalankan) — diam.
        $this->assertNull(BatasIsi::status((object) ['Status' => 'BERJALAN', 'Formulir_Kode' => 'X']));
    }

    public function test_manual_belum_diatur_berarti_terkunci(): void
    {
        // Keputusan user: formulir yang jadwalnya belum di-setup TERKUNCI.
        $s = BatasIsi::status($this->tahap());

        $this->assertNull($s['batas']);
        $this->assertSame(BatasIsi::BELUM_DIATUR, $s['alasan']);
        $this->assertTrue($s['belumDiatur']);
        $this->assertTrue($s['terkunci']);

        // Tanpa jadwal di Master Alur: terbuka, dan tidak ada jadwal yang dibaca
        // (lihat test_tahap_tanpa_jadwal_di_master_alur_tidak_mengenal_jadwal).
        $this->assertNull(BatasIsi::status($this->tahap(['Batas_Mode' => null, 'Batas_Sumber' => null])));
    }

    public function test_sebelum_waktu_dibuka_terkunci_lalu_terbuka(): void
    {
        $t = $this->tahap(['Buka_At' => '2026-10-10 08:00:00', 'Batas_At' => '2026-10-15 23:59:59']);

        $sebelum = BatasIsi::status($t, Carbon::parse('2026-10-10 07:59:00'));
        $this->assertSame(BatasIsi::BELUM_BUKA, $sebelum['alasan']);
        $this->assertTrue($sebelum['terkunci']);
        $this->assertSame('Sabtu, 10 Okt 2026 pukul 08.00 WIB', $sebelum['bukaTeks']);

        $sesudah = BatasIsi::status($t, Carbon::parse('2026-10-10 08:00:01'));
        $this->assertNull($sesudah['alasan']);
        $this->assertFalse($sesudah['terkunci']);
    }

    public function test_lewat_batas_selalu_terkunci(): void
    {
        // Keputusan user: tidak ada pilihan — lewat batas, formulir terkunci.
        $kini = Carbon::parse('2026-10-16 08:00:00');

        $kunci = BatasIsi::status($this->tahap(['Batas_At' => '2026-10-15 23:59:59']), $kini);
        $this->assertTrue($kunci['lewat']);
        $this->assertTrue($kunci['terkunci']);
        $this->assertLessThan(0, $kunci['sisaDetik']);

        // Nilai lama "TANDAI" di data (sebelum pilihannya dicabut) tidak membuka kunci.
        $lama = BatasIsi::status($this->tahap(['Batas_At' => '2026-10-15 23:59:59', 'Batas_Aksi' => 'TANDAI']), $kini);
        $this->assertTrue($lama['terkunci']);
    }

    public function test_belum_lewat_dan_sudah_terkirim_tidak_terkunci(): void
    {
        $kini = Carbon::parse('2026-10-15 12:00:00');

        $jalan = BatasIsi::status($this->tahap(['Batas_At' => '2026-10-15 23:59:59']), $kini);
        $this->assertFalse($jalan['lewat']);
        $this->assertGreaterThan(0, $jalan['sisaDetik']);

        // Terkirim sebelum batas: tidak pernah terkunci, walau batasnya kini lewat.
        $kirim = BatasIsi::status($this->tahap(['Batas_At' => '2026-10-15 23:59:59', 'Formulir_Pengisian_Id' => 99]), Carbon::parse('2026-10-20'));
        $this->assertTrue($kirim['terkirim']);
        $this->assertFalse($kirim['terkunci']);
        $this->assertNull($kirim['sisaDetik']);
    }

    public function test_teks_berbahasa_indonesia(): void
    {
        $this->assertSame('Kamis, 15 Okt 2026 pukul 23.59 WIB', BatasIsi::teks('2026-10-15 23:59:59'));
        $this->assertSame('—', BatasIsi::teks(null));
    }

    public function test_tahap_tanpa_jadwal_di_master_alur_tidak_mengenal_jadwal(): void
    {
        // TANPA (atau belum diatur sama sekali) dan belum pernah dijadwalkan:
        // tidak ada panel, tombol, maupun kunci.
        $this->assertNull(BatasIsi::status($this->tahap(['Batas_Mode' => BatasIsi::TANPA, 'Batas_Sumber' => null])));
        $this->assertNull(BatasIsi::status($this->tahap(['Batas_Mode' => null, 'Batas_Sumber' => null])));

        // Sudah dijadwalkan lewat kolom (kandidat alur versi lama): tetap terbaca.
        $s = BatasIsi::status($this->tahap(['Batas_Mode' => BatasIsi::TANPA, 'Batas_At' => '2026-10-15 23:59:59']), Carbon::parse('2026-10-10 09:00:00'));
        $this->assertNotNull($s);
        $this->assertFalse($s['terkunci']);
    }

    public function test_kolom_boleh_dijadwal_ditentukan_master_alur_bukan_posisi(): void
    {
        $kolom = fn (array $isi) => $isi + ['formulir' => true, 'cadangan' => false, 'batasMode' => BatasIsi::MANUAL, 'urutan' => 1];

        // Tahap 1 pun boleh, asal Master Alur memberinya jadwal.
        $this->assertTrue(BatasIsi::kolomBolehDijadwal($kolom([]), false, false));
        $this->assertTrue(BatasIsi::kolomBolehDijadwal($kolom(['batasMode' => BatasIsi::OTOMATIS]), false, false));
        $this->assertFalse(BatasIsi::kolomBolehDijadwal($kolom(['batasMode' => BatasIsi::TANPA]), false, false));
        // Alur disunting jadi TANPA, tapi kolomnya sudah berjadwal / masih berisi
        // kandidat berjadwal: tetap bisa diurus, jangan ada yang terkunci buntu.
        $this->assertTrue(BatasIsi::kolomBolehDijadwal($kolom(['batasMode' => BatasIsi::TANPA]), true, false));
        $this->assertTrue(BatasIsi::kolomBolehDijadwal($kolom(['batasMode' => null]), false, true));
        // Bukan formulir / kolom cadangan: tidak pernah.
        $this->assertFalse(BatasIsi::kolomBolehDijadwal($kolom(['formulir' => false]), true, true));
        $this->assertFalse(BatasIsi::kolomBolehDijadwal($kolom(['cadangan' => true]), true, true));
    }

    public function test_perpanjang_dari_batas_yang_belum_lewat(): void
    {
        $lama = Carbon::parse('2026-10-15 23:59:59');
        $kini = Carbon::parse('2026-10-10 09:00:00');

        $this->assertSame('2026-10-17 23:59:59', BatasIsi::batasBaru($lama, 'HARI', 2, null, $kini)->format('Y-m-d H:i:s'));
        $this->assertSame('2026-10-16 05:59:59', BatasIsi::batasBaru($lama, 'JAM', 6, null, $kini)->format('Y-m-d H:i:s'));
        $this->assertSame('2026-10-20 12:00:00', BatasIsi::batasBaru($lama, 'SAMPAI', null, Carbon::parse('2026-10-20 12:00:00'), $kini)->format('Y-m-d H:i:s'));
        // Isian tak lengkap / cara tak dikenal.
        $this->assertNull(BatasIsi::batasBaru($lama, 'HARI', 0, null, $kini));
        $this->assertNull(BatasIsi::batasBaru($lama, 'MUNDUR', 2, null, $kini));
    }

    public function test_perpanjang_batas_yang_sudah_lewat_dihitung_dari_hari_ini(): void
    {
        // Batas Kamis 23.59 terlewat; diperpanjang Senin pagi.
        $lama = Carbon::parse('2026-10-15 23:59:59');
        $kini = Carbon::parse('2026-10-19 09:30:00');

        // "+2 hari" = Rabu 23.59 (jam batas lamanya), bukan Sabtu yang juga sudah lewat.
        $this->assertSame('2026-10-21 23:59:59', BatasIsi::batasBaru($lama, 'HARI', 2, null, $kini)->format('Y-m-d H:i:s'));
        // "+5 jam" = sekarang + 5 jam.
        $this->assertSame('2026-10-19 14:30:00', BatasIsi::batasBaru($lama, 'JAM', 5, null, $kini)->format('Y-m-d H:i:s'));
        // Lewat HARI INI, jamnya sudah terlewat: +1 hari tetap jatuh besok.
        $this->assertSame(
            '2026-10-20 08:00:00',
            BatasIsi::batasBaru(Carbon::parse('2026-10-19 08:00:00'), 'HARI', 1, null, $kini)->format('Y-m-d H:i:s'),
        );
    }
}
