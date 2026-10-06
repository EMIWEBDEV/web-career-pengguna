<?php

namespace Tests\Unit;

use App\Http\Controllers\Career\Lamaran\LamaranController;
use App\Http\Controllers\Career\Penjadwalan\PenjadwalanController;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Masukan user 2 Okt 2026: saat menjadwalkan, tanggal yang SUDAH LEWAT tidak
 * boleh dipilih ("sekarang tanggal 02, tanggal 01 tidak boleh"). Kalender di
 * layar memadamkannya; server menolaknya juga — layar basi & DevTools tetap
 * bisa mengirim. Jadwal yang sedang berjalan dan waktu mulainya TIDAK diubah
 * tetap boleh disunting (mis. membetulkan tautan / memperpanjang waktu berakhir).
 */
class JadwalLampauTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-10-02 10:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function waktuJadwal(array $data, bool $rentang = false, ?string $mulaiLama = null): array
    {
        $m = new \ReflectionMethod(LamaranController::class, 'waktuJadwal');

        return $m->invoke(null, $data, (object) ['Flag_Batas_Waktu' => $rentang ? 'Y' : 'T'], $mulaiLama);
    }

    private function jendela(string $mulai, string $akhir, ?string $mulaiLama = null): ?string
    {
        $m = new \ReflectionMethod(PenjadwalanController::class, 'galatJendelaLampau');

        return $m->invoke(null, $mulai, $akhir, $mulaiLama);
    }

    public function test_atur_jadwal_menolak_tanggal_kemarin(): void
    {
        [, , $galat] = $this->waktuJadwal(['mulai' => '2026-10-01 09:00:00']);
        $this->assertStringContainsString('sudah lewat', (string) $galat);
    }

    public function test_atur_jadwal_menolak_jam_yang_sudah_lewat_hari_ini(): void
    {
        [, , $galat] = $this->waktuJadwal(['mulai' => '2026-10-02 08:00:00']);
        $this->assertStringContainsString('sudah lewat', (string) $galat);
    }

    public function test_atur_jadwal_menerima_waktu_yang_akan_datang(): void
    {
        [$mulai, , $galat] = $this->waktuJadwal(['mulai' => '2026-10-02 13:00:00', 'selesai' => '2026-10-02 14:00:00']);
        $this->assertNull($galat);
        $this->assertSame('2026-10-02 13:00:00', $mulai);
    }

    public function test_jadwal_berjalan_yang_jamnya_tidak_diubah_tetap_boleh_disunting(): void
    {
        [, , $galat] = $this->waktuJadwal(['mulai' => '2026-10-02 08:00:00'], false, '2026-10-02 08:00:00.000');
        $this->assertNull($galat);
    }

    public function test_rentang_mcu_tidak_boleh_dimulai_kemarin(): void
    {
        [, , $galat] = $this->waktuJadwal(['mulai' => '2026-10-01', 'selesai' => '2026-10-05'], true);
        $this->assertStringContainsString('Tanggal pertama sudah lewat', (string) $galat);

        [, , $galat] = $this->waktuJadwal(['mulai' => '2026-10-02', 'selesai' => '2026-10-05'], true);
        $this->assertNull($galat, 'hari ini masih boleh');

        [, , $galat] = $this->waktuJadwal(['mulai' => '2026-10-01', 'selesai' => '2026-10-05'], true, '2026-10-01 00:00:00');
        $this->assertNull($galat, 'rentang berjalan yang tanggal pertamanya tidak diubah');
    }

    public function test_jendela_tes(): void
    {
        $this->assertNotNull($this->jendela('2026-10-01 08:00:00', '2026-10-03 17:00:00'), 'mulai kemarin');
        $this->assertNotNull($this->jendela('2026-10-02 08:00:00', '2026-10-02 09:00:00'), 'berakhir sudah lewat');
        $this->assertNull($this->jendela('2026-10-02 08:00:00', '2026-10-02 17:00:00'), 'jendela hari ini yang masih terbuka');
        $this->assertNull($this->jendela('2026-10-01 08:00:00', '2026-10-04 17:00:00', '2026-10-01 08:00:00'), 'diperpanjang, mulai tidak diubah');
    }

    private function statis(string $kelas, string $metode, ...$arg)
    {
        $m = new \ReflectionMethod($kelas, $metode);

        return $m->invoke(null, ...$arg);
    }

    public function test_formulir_dibuka_kemarin_ditolak_hari_ini_boleh(): void
    {
        $k = \App\Http\Controllers\Career\BatasIsi\BatasIsiController::class;
        $this->assertNotNull($this->statis($k, 'galatBukaLampau', Carbon::parse('2026-10-01 23:59:00')));
        $this->assertNull($this->statis($k, 'galatBukaLampau', Carbon::parse('2026-10-02 07:00:00')), 'jam lewat hari ini = "dibuka sekarang"');
    }

    public function test_pembukaan_program(): void
    {
        $k = \App\Http\Controllers\Career\PembukaanProgram\PembukaanProgramController::class;
        $baru = fn (array $x) => $x + ['masaBerlaku' => 'BERBATAS'];

        $this->assertStringContainsString('Tanggal buka', (string) $this->statis($k, 'galatHariLampau', $baru(['buka' => '2026-10-01 08:00:00'])));
        $this->assertStringContainsString('Tanggal tutup', (string) $this->statis($k, 'galatHariLampau', $baru(['buka' => '2026-10-02 08:00:00', 'tutup' => '2026-09-30 17:00:00'])));
        $this->assertNull($this->statis($k, 'galatHariLampau', $baru(['buka' => '2026-10-02 08:00:00', 'tutup' => '2026-10-30 17:00:00'])));
        $this->assertNull($this->statis($k, 'galatHariLampau', ['masaBerlaku' => 'EVERGREEN', 'buka' => '2026-10-03 08:00:00', 'tutup' => '2026-09-30 17:00:00']), 'EVERGREEN mengabaikan tutup');

        $lama = (object) ['Tanggal_Buka' => '2026-09-01 08:00:00.000', 'Tanggal_Tutup' => '2026-10-30 17:00:00.000'];
        $this->assertNull($this->statis($k, 'galatHariLampau', $baru(['buka' => '2026-09-01 08:00:00', 'tutup' => '2026-10-30 17:00:00']), $lama), 'program berjalan, buka tidak diubah');
        $this->assertNotNull($this->statis($k, 'galatHariLampau', $baru(['buka' => '2026-09-02 08:00:00', 'tutup' => '2026-10-30 17:00:00']), $lama), 'buka diubah ke hari lampau lain');
    }

    public function test_agenda_master_jadwal(): void
    {
        $k = \App\Http\Controllers\Career\MasterJadwal\MasterJadwalController::class;
        $agenda = [
            ['jenis' => 'TAHAP', 'label' => 'Registrasi', 'mulai' => '2026-09-01', 'selesai' => '2026-09-30'],
            ['jenis' => 'TAHAP', 'label' => 'Psikotes', 'mulai' => '2026-10-05', 'selesai' => null],
        ];

        $this->assertStringContainsString('"Registrasi"', (string) $this->statis($k, 'galatAgendaLampau', $agenda), 'jadwal baru dengan agenda lampau');
        $this->assertNull($this->statis($k, 'galatAgendaLampau', $agenda, ['2026-09-01', '2026-09-30']), 'agenda yang sudah tersimpan terkirim ulang');

        $agenda[1]['mulai'] = '2026-10-01';
        $this->assertStringContainsString('"Psikotes"', (string) $this->statis($k, 'galatAgendaLampau', $agenda, ['2026-09-01', '2026-09-30']), 'tanggal baru yang lampau');
    }
}
