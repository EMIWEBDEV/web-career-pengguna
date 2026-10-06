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
 * badge() dulu tidak punya cabang untuk outcome di luar tiga yang lama.
 * Seluruh kondisi di atasnya gagal (tahapAktif() mengembalikan null untuk
 * status terminal, sehingga ditahan/siap/nungguSistem semuanya false), lalu
 * jatuh ke baris terakhir: ['tone' => 'berjalan', 'teks' => 'Berjalan'].
 * Digabung dengan penempatan di tahap 1, kandidat yang sudah mengundurkan
 * diri dilaporkan sebagai pelamar baru yang aktif di Pendaftaran.
 */
class PipelineProgressTerminalTest extends TestCase
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
            $t->string('Warna')->nullable();
            $t->string('Ikon')->nullable();
            $t->string('Flag_Lolos')->default('T');
            $t->string('Flag_Oleh_Kandidat')->default('T');
            $t->string('Flag_Talent_Pool')->default('T');
            $t->string('Flag_Potong_Kuota')->default('T');
            $t->string('Flag_Aktif')->default('Y');
            $t->integer('Urutan')->default(1);
        });

        DB::table('N_WEB_CAREERS_Master_Hasil_Keputusan')->insert([
            ['Kode' => 'LULUS', 'Nama' => 'Lolos', 'Warna' => '#059669', 'Ikon' => 'bi-check-lg',
                'Flag_Lolos' => 'Y', 'Flag_Oleh_Kandidat' => 'T', 'Flag_Talent_Pool' => 'T', 'Flag_Potong_Kuota' => 'Y', 'Flag_Aktif' => 'Y', 'Urutan' => 1],
            ['Kode' => 'GUGUR', 'Nama' => 'Tidak Lolos', 'Warna' => '#dc2626', 'Ikon' => 'bi-x-lg',
                'Flag_Lolos' => 'T', 'Flag_Oleh_Kandidat' => 'T', 'Flag_Talent_Pool' => 'T', 'Flag_Potong_Kuota' => 'T', 'Flag_Aktif' => 'Y', 'Urutan' => 2],
            ['Kode' => 'TALENT_POOL', 'Nama' => 'Talent Pool', 'Warna' => '#d97706', 'Ikon' => 'bi-stars',
                'Flag_Lolos' => 'T', 'Flag_Oleh_Kandidat' => 'T', 'Flag_Talent_Pool' => 'Y', 'Flag_Potong_Kuota' => 'T', 'Flag_Aktif' => 'Y', 'Urutan' => 3],
            ['Kode' => 'MENGUNDURKAN_DIRI', 'Nama' => 'Kandidat Mengundurkan Diri', 'Warna' => '#64748b', 'Ikon' => 'bi-box-arrow-left',
                'Flag_Lolos' => 'T', 'Flag_Oleh_Kandidat' => 'Y', 'Flag_Talent_Pool' => 'Y', 'Flag_Potong_Kuota' => 'T', 'Flag_Aktif' => 'Y', 'Urutan' => 5],
        ]);
        HasilKeputusan::lupakanCache();
    }

    protected function tearDown(): void
    {
        HasilKeputusan::lupakanCache();
        Schema::dropIfExists('N_WEB_CAREERS_Master_Hasil_Keputusan');
        parent::tearDown();
    }

    private function tahapList(): Collection
    {
        return new Collection([
            (object) ['Urutan' => 1, 'Status' => 'SELESAI', 'Hasil' => 'LULUS'],
            (object) ['Urutan' => 4, 'Status' => 'SELESAI', 'Hasil' => 'MENGUNDURKAN_DIRI'],
            (object) ['Urutan' => 7, 'Status' => 'BELUM', 'Hasil' => null],
        ]);
    }

    /** State netral: seluruh kondisi mati, supaya badge() jatuh ke fallback. */
    private function stateNetral(array $ubah = []): array
    {
        return array_merge([
            'ditahan' => false,
            'holdNama' => null,
            'siap' => false,
            'isTes' => false,
            'skor' => null,
            'nungguSistem' => false,
            'butuhKeputusan' => false,
            'hasilData' => null,
        ], $ubah);
    }

    public function test_tahap_kini_menunjuk_tahap_keluar(): void
    {
        $l = (object) ['Status' => 'MENGUNDURKAN_DIRI'];

        $this->assertSame(4, (int) PipelineProgress::tahapKini($l, $this->tahapList())->Urutan);
    }

    public function test_badge_tidak_lagi_berbunyi_berjalan(): void
    {
        $l = (object) ['Status' => 'MENGUNDURKAN_DIRI'];

        $badge = PipelineProgress::badge($l, $this->stateNetral(), null);

        $this->assertNotSame('Berjalan', $badge['teks']);
        $this->assertSame('Kandidat Mengundurkan Diri', $badge['teks']);
        $this->assertSame('keluar', $badge['tone']);
    }

    /** Badge lama tidak boleh berubah bunyinya. */
    public function test_badge_lama_tidak_berubah(): void
    {
        $s = $this->stateNetral();

        $gugur = PipelineProgress::badge((object) ['Status' => 'GUGUR'], $s, null);
        $this->assertSame('Tidak Lolos', $gugur['teks']);
        $this->assertSame('gugur', $gugur['tone']);

        $talent = PipelineProgress::badge((object) ['Status' => 'TALENT_POOL'], $s, null);
        $this->assertSame('Talent Pool', $talent['teks']);
        $this->assertSame('talent', $talent['tone']);

        $lulus = PipelineProgress::badge((object) ['Status' => 'LULUS'], $s, null);
        $this->assertSame('Diterima', $lulus['teks']);
        $this->assertSame('lolos', $lulus['tone']);
    }

    /** HOLD tetap mendahului segalanya, termasuk LULUS/pasca-penerimaan. */
    public function test_hold_tetap_menang_atas_lulus(): void
    {
        $s = $this->stateNetral(['ditahan' => true, 'holdNama' => 'Menunggu kuota']);

        $badge = PipelineProgress::badge((object) ['Status' => 'LULUS'], $s, (object) ['Status' => 'BERJALAN']);

        $this->assertSame('hold', $badge['tone']);
        $this->assertSame('Ditahan — Menunggu kuota', $badge['teks']);
    }

    /**
     * Status yang tidak dikenal master harus tetap jatuh ke perilaku lama —
     * cabang terminal tidak boleh menelan status proses.
     */
    public function test_status_asing_tetap_berjalan(): void
    {
        $badge = PipelineProgress::badge((object) ['Status' => 'DRAFT'], $this->stateNetral(), null);

        $this->assertSame('Berjalan', $badge['teks']);
        $this->assertSame('berjalan', $badge['tone']);
    }
}
