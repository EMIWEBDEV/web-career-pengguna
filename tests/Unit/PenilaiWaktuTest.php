<?php

namespace Tests\Unit;

use App\Support\Portal\PenilaiWaktu;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Bagian potret yang bergantung waktu dihitung ULANG saat dibaca — potret
 * yang didorong pukul 08.00 tidak boleh membekukan tombol ujian 09.00.
 */
class PenilaiWaktuTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_jendela_ujian_dihitung_dari_waktu_sekarang(): void
    {
        $ujian = ['link' => 'https://cat.example/u/1', 'statusPengerjaan' => null, 'bisaAkses' => false,
            '_jendela' => ['mulai' => '2026-10-06 09:00:00', 'akhir' => '2026-10-06 11:00:00']];

        Carbon::setTestNow('2026-10-06 08:00:00');
        $pagi = PenilaiWaktu::segarkan(['tahap' => [['ujian' => $ujian]]]);
        $this->assertTrue($pagi['tahap'][0]['ujian']['belumMulai']);
        $this->assertFalse($pagi['tahap'][0]['ujian']['bisaAkses']);

        Carbon::setTestNow('2026-10-06 09:30:00');
        $tengah = PenilaiWaktu::segarkan(['tahap' => [['ujian' => $ujian]]]);
        $this->assertTrue($tengah['tahap'][0]['ujian']['bisaAkses']);
        $this->assertArrayNotHasKey('_jendela', $tengah['tahap'][0]['ujian']);

        Carbon::setTestNow('2026-10-06 11:30:00');
        $lewat = PenilaiWaktu::segarkan(['ujian' => $ujian]);
        $this->assertTrue($lewat['ujian']['sudahLewat']);
        $this->assertFalse($lewat['ujian']['bisaAkses']);
    }

    public function test_ujian_yang_sudah_selesai_dikerjakan_tidak_bisa_diakses_lagi(): void
    {
        Carbon::setTestNow('2026-10-06 09:30:00');
        $u = PenilaiWaktu::segarkan(['link' => 'x', 'statusPengerjaan' => 'selesai',
            '_jendela' => ['mulai' => '2026-10-06 09:00:00', 'akhir' => '2026-10-06 11:00:00']]);

        $this->assertFalse($u['bisaAkses']);
    }

    public function test_penanda_lewat_dan_tutup_tidak_membuka_yang_sudah_ditutup(): void
    {
        Carbon::setTestNow('2026-10-06 10:00:00');

        $n = PenilaiWaktu::segarkan([
            'jadwal' => ['lewat' => false, '_lewatSetelah' => '2026-10-05 23:59:00'],
            'unggah' => ['tertutup' => true, '_tutupSetelah' => '2026-10-10 23:59:00'],
            'masih' => ['tertutup' => false, '_tutupSetelah' => '2026-10-10 23:59:00'],
        ]);

        $this->assertTrue($n['jadwal']['lewat']);
        $this->assertTrue($n['unggah']['tertutup'], 'tutup karena sebab lain tetap tutup');
        $this->assertFalse($n['masih']['tertutup']);
    }

    public function test_blok_konfirmasi_disegarkan(): void
    {
        Carbon::setTestNow('2026-10-06 10:00:00');
        config(['konfirmasi.usulan_hari_maks' => 14]);

        $blok = PenilaiWaktu::segarkan([
            '_konfirmasi' => ['batas' => '2026-10-08 09:00:00', 'terbuka' => true],
            'konfirmasi' => ['final' => false, 'bolehJawab' => false, 'batasLewat' => true],
            'ubah' => ['sebab' => null, 'terkunci' => false, 'batas' => '2026-10-06 08:00:00', 'batasTeks' => 'Senin 08.00'],
            'aturanUsulan' => ['maks' => 3, 'tanggalMin' => '2000-01-01', 'tanggalMaks' => '2000-01-02'],
        ]);

        $this->assertTrue($blok['konfirmasi']['bolehJawab']);
        $this->assertFalse($blok['konfirmasi']['batasLewat']);
        $this->assertTrue($blok['ubah']['terkunci'], 'batas ubah H-n sudah lewat');
        $this->assertSame('WAKTU', $blok['ubah']['sebab']);
        $this->assertNotEmpty($blok['ubah']['pesanKunci']);
        $this->assertSame('2026-10-07', $blok['aturanUsulan']['tanggalMin']);
        $this->assertSame('2026-10-20', $blok['aturanUsulan']['tanggalMaks']);
    }

    public function test_jatah_habis_tidak_dibuka_oleh_waktu(): void
    {
        Carbon::setTestNow('2026-10-01 10:00:00');

        $blok = PenilaiWaktu::segarkan([
            '_konfirmasi' => ['batas' => '2026-10-08 09:00:00', 'terbuka' => true],
            'ubah' => ['sebab' => 'JATAH', 'terkunci' => true, 'batas' => '2026-10-07 08:00:00'],
        ]);

        $this->assertTrue($blok['ubah']['terkunci']);
        $this->assertSame('JATAH', $blok['ubah']['sebab']);
    }

    public function test_lewat_tanpa_batas_berarti_tidak_berbatas(): void
    {
        $this->assertFalse(PenilaiWaktu::lewat(null));
        $this->assertFalse(PenilaiWaktu::lewat(''));
        $this->assertTrue(PenilaiWaktu::lewat('2000-01-01 00:00:00'));
    }
}
