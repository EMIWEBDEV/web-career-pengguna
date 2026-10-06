<?php

namespace Tests\Unit;

use App\Support\Career\HasilKeputusan;
use App\Support\Career\PipelineProgress;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Dulu ini test murni (PHPUnit\Framework\TestCase, tanpa Laravel). badge()
 * kini membaca daftar outcome dari master lewat HasilKeputusan, jadi facade DB
 * harus hidup. Master sengaja TIDAK di-mock: yang diuji justru bahwa aturannya
 * benar-benar mengikuti baris master, bukan daftar kode yang ditulis mati.
 */
class PipelineProgressTahapAktifTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);

        Schema::create('N_WEB_CAREERS_Master_Hasil_Keputusan', function (Blueprint $t) {
            $t->increments('Id_Master_Hasil_Keputusan');
            $t->string('Kode');
            $t->string('Nama');
            $t->string('Flag_Lolos')->default('T');
            $t->string('Flag_Oleh_Kandidat')->default('T');
            $t->string('Flag_Talent_Pool')->default('T');
            $t->string('Flag_Potong_Kuota')->default('T');
            $t->string('Flag_Aktif')->default('Y');
            $t->integer('Urutan')->default(1);
        });

        DB::table('N_WEB_CAREERS_Master_Hasil_Keputusan')->insert([
            ['Kode' => 'LULUS', 'Nama' => 'Lolos', 'Flag_Lolos' => 'Y', 'Flag_Oleh_Kandidat' => 'T', 'Flag_Talent_Pool' => 'T', 'Flag_Potong_Kuota' => 'Y', 'Flag_Aktif' => 'Y', 'Urutan' => 1],
            ['Kode' => 'GUGUR', 'Nama' => 'Tidak Lolos', 'Flag_Lolos' => 'T', 'Flag_Oleh_Kandidat' => 'T', 'Flag_Talent_Pool' => 'T', 'Flag_Potong_Kuota' => 'T', 'Flag_Aktif' => 'Y', 'Urutan' => 2],
            ['Kode' => 'TALENT_POOL', 'Nama' => 'Talent Pool', 'Flag_Lolos' => 'T', 'Flag_Oleh_Kandidat' => 'T', 'Flag_Talent_Pool' => 'Y', 'Flag_Potong_Kuota' => 'T', 'Flag_Aktif' => 'Y', 'Urutan' => 3],
        ]);
        HasilKeputusan::lupakanCache();
    }

    protected function tearDown(): void
    {
        HasilKeputusan::lupakanCache();
        Schema::dropIfExists('N_WEB_CAREERS_Master_Hasil_Keputusan');
        parent::tearDown();
    }

    private function tahap(int $urutan, string $status, string $flagTuntas = 'T'): object
    {
        return (object) ['Id_Lamaran_Tahap' => $urutan, 'Urutan' => $urutan, 'Status' => $status, 'Flag_Tuntas' => $flagTuntas];
    }

    public function test_status_berjalan_biasa_tetap_mengembalikan_tahap_berjalan(): void
    {
        $l = (object) ['Status' => 'BERJALAN'];
        $tahapList = new Collection([
            $this->tahap(1, 'SELESAI'),
            $this->tahap(2, 'BERJALAN'),
        ]);

        $hasil = PipelineProgress::tahapAktif($l, $tahapList);

        $this->assertSame(2, $hasil->Urutan);
    }

    public function test_status_gugur_tetap_mengembalikan_null(): void
    {
        $l = (object) ['Status' => 'GUGUR'];
        $tahapList = new Collection([$this->tahap(1, 'SELESAI')]);

        $this->assertNull(PipelineProgress::tahapAktif($l, $tahapList));
    }

    public function test_status_talent_pool_tetap_mengembalikan_null(): void
    {
        $l = (object) ['Status' => 'TALENT_POOL'];
        $tahapList = new Collection([$this->tahap(1, 'SELESAI')]);

        $this->assertNull(PipelineProgress::tahapAktif($l, $tahapList));
    }

    public function test_status_lulus_dengan_tahap_pascatuntas_berjalan_mengembalikan_tahap_itu(): void
    {
        $l = (object) ['Status' => 'LULUS'];
        $tahapList = new Collection([
            $this->tahap(1, 'SELESAI', 'Y'),   // titik tuntas, sudah selesai
            $this->tahap(2, 'BERJALAN', 'T'),  // tahap administratif (kontrak) — masih berjalan
        ]);

        $hasil = PipelineProgress::tahapAktif($l, $tahapList);

        $this->assertNotNull($hasil);
        $this->assertSame(2, $hasil->Urutan);
    }

    public function test_status_lulus_tanpa_tahap_berjalan_mengembalikan_null(): void
    {
        $l = (object) ['Status' => 'LULUS'];
        $tahapList = new Collection([
            $this->tahap(1, 'SELESAI', 'Y'),
            $this->tahap(2, 'SELESAI', 'T'),
        ]);

        $this->assertNull(PipelineProgress::tahapAktif($l, $tahapList));
    }

    public function test_badge_pascapenerimaan_untuk_lulus_dengan_tahap_administratif_berjalan(): void
    {
        $l = (object) ['Status' => 'LULUS'];
        $tAktif = $this->tahap(2, 'BERJALAN', 'T');
        $state = ['ditahan' => false, 'holdNama' => null, 'siap' => false, 'isTes' => false, 'skor' => null, 'nungguSistem' => false, 'butuhKeputusan' => false];

        $badge = \App\Support\Career\PipelineProgress::badge($l, $state, $tAktif);

        $this->assertSame('pascaPenerimaan', $badge['tone']);
    }

    /**
     * Keputusan produk: HOLD selalu menang atas LULUS/pasca-penerimaan.
     * Kandidat yang sudah LULUS tapi tahap administratifnya (kontrak,
     * onboarding) sedang DITAHAN harus tampil sebagai "Ditahan", BUKAN
     * "Diterima — Proses Administrasi" — sampai penahanannya dilepas.
     */
    public function test_badge_hold_menang_atas_lulus_pascapenerimaan(): void
    {
        $l = (object) ['Status' => 'LULUS'];
        $tAktif = $this->tahap(2, 'BERJALAN', 'T');
        $tAktif->Hold_Flag = 'Y';
        $state = ['ditahan' => true, 'holdNama' => null, 'siap' => false, 'isTes' => false, 'skor' => null, 'nungguSistem' => false, 'butuhKeputusan' => false];

        $badge = \App\Support\Career\PipelineProgress::badge($l, $state, $tAktif);

        $this->assertSame('hold', $badge['tone']);
    }
}
