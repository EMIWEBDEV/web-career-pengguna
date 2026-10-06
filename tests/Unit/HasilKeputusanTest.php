<?php

namespace Tests\Unit;

use App\Support\Career\HasilKeputusan;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * HasilKeputusan adalah sumber tunggal "outcome apa saja yang ada dan apa
 * artinya". Sebelum kelas ini ada, monitoring hanya mengenal tiga kode yang
 * ditulis mati (GUGUR/TALENT_POOL/LULUS), sehingga DITOLAK_KANDIDAT dan
 * MENGUNDURKAN_DIRI tidak pernah terhitung di bucket mana pun.
 */
class HasilKeputusanTest extends TestCase
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
            ['Kode' => 'DITOLAK_KANDIDAT', 'Nama' => 'Ditolak Kandidat', 'Warna' => '#7c3aed', 'Ikon' => 'bi-hand-thumbs-down',
                'Flag_Lolos' => 'T', 'Flag_Oleh_Kandidat' => 'Y', 'Flag_Talent_Pool' => 'Y', 'Flag_Potong_Kuota' => 'T', 'Flag_Aktif' => 'Y', 'Urutan' => 4],
            ['Kode' => 'MENGUNDURKAN_DIRI', 'Nama' => 'Kandidat Mengundurkan Diri', 'Warna' => '#64748b', 'Ikon' => 'bi-box-arrow-left',
                'Flag_Lolos' => 'T', 'Flag_Oleh_Kandidat' => 'Y', 'Flag_Talent_Pool' => 'Y', 'Flag_Potong_Kuota' => 'T', 'Flag_Aktif' => 'Y', 'Urutan' => 5],
            // Sudah DINONAKTIFKAN, tapi lamaran lama masih memakainya.
            ['Kode' => 'BLACKLIST', 'Nama' => 'Blacklist', 'Warna' => '#111827', 'Ikon' => 'bi-slash-circle',
                'Flag_Lolos' => 'T', 'Flag_Oleh_Kandidat' => 'T', 'Flag_Talent_Pool' => 'T', 'Flag_Potong_Kuota' => 'T', 'Flag_Aktif' => 'T', 'Urutan' => 6],
            // Kode kotor — harus dibuang, tidak boleh masuk SQL.
            ['Kode' => "X'; DROP TABLE--", 'Nama' => 'Jahat', 'Warna' => null, 'Ikon' => null,
                'Flag_Lolos' => 'T', 'Flag_Oleh_Kandidat' => 'T', 'Flag_Talent_Pool' => 'T', 'Flag_Potong_Kuota' => 'T', 'Flag_Aktif' => 'Y', 'Urutan' => 7],
        ]);

        HasilKeputusan::lupakanCache();
    }

    protected function tearDown(): void
    {
        HasilKeputusan::lupakanCache();
        Schema::dropIfExists('N_WEB_CAREERS_Master_Hasil_Keputusan');
        parent::tearDown();
    }

    public function test_partisi_bucket_total_dan_tidak_tumpang_tindih(): void
    {
        $this->assertSame(['LULUS'], HasilKeputusan::kodeLolos());
        $this->assertEqualsCanonicalizing(['DITOLAK_KANDIDAT', 'MENGUNDURKAN_DIRI'], HasilKeputusan::kodeKeluar());
        $this->assertSame(['TALENT_POOL'], HasilKeputusan::kodeTalent());
        $this->assertEqualsCanonicalizing(['GUGUR', 'BLACKLIST'], HasilKeputusan::kodeGugur());

        // Total: setiap kode sah persis di satu bucket.
        $gabung = array_merge(
            HasilKeputusan::kodeLolos(),
            HasilKeputusan::kodeKeluar(),
            HasilKeputusan::kodeTalent(),
            HasilKeputusan::kodeGugur(),
        );
        $this->assertSame(count($gabung), count(array_unique($gabung)), 'Bucket tidak boleh tumpang tindih.');
        $this->assertEqualsCanonicalizing(HasilKeputusan::semua()->keys()->all(), $gabung, 'Setiap kode sah harus punya bucket.');
    }

    /**
     * Flag_Talent_Pool bernilai 'Y' juga untuk kedua kode keluar. Menguji flag
     * itu SEBELUM Flag_Oleh_Kandidat akan menyeret DITOLAK_KANDIDAT dan
     * MENGUNDURKAN_DIRI ke bucket talent — dobel hitung yang membuat kolom
     * funnel melebihi total pelamar.
     */
    public function test_kode_keluar_tidak_bocor_ke_bucket_talent(): void
    {
        $this->assertNotContains('DITOLAK_KANDIDAT', HasilKeputusan::kodeTalent());
        $this->assertNotContains('MENGUNDURKAN_DIRI', HasilKeputusan::kodeTalent());
    }

    /**
     * Flag_Aktif mengatur apa yang boleh DIPILIH admin ke depan, bukan cara
     * membaca sejarah. Kode yang dinonaktifkan masih melekat pada lamaran lama;
     * menyaringnya membuat lamaran itu jatuh ke cabang "masih berjalan".
     */
    public function test_kode_nonaktif_tetap_terbaca(): void
    {
        $this->assertArrayHasKey('BLACKLIST', HasilKeputusan::peta());
        $this->assertContains('BLACKLIST', HasilKeputusan::kodeGugur());
    }

    public function test_kode_tidak_memenuhi_pola_dibuang(): void
    {
        $this->assertArrayNotHasKey("X'; DROP TABLE--", HasilKeputusan::peta());
        $this->assertStringNotContainsString('DROP TABLE', HasilKeputusan::sqlIn(HasilKeputusan::kodeGugur()));
    }

    public function test_sql_in_kosong_aman(): void
    {
        $this->assertSame("''", HasilKeputusan::sqlIn([]));
    }

    public function test_peta_membawa_atribut_tampilan(): void
    {
        $peta = HasilKeputusan::peta();

        $this->assertSame('Kandidat Mengundurkan Diri', $peta['MENGUNDURKAN_DIRI']['nama']);
        $this->assertSame('#64748b', $peta['MENGUNDURKAN_DIRI']['warna']);
        $this->assertTrue($peta['MENGUNDURKAN_DIRI']['olehKandidat']);
        $this->assertFalse($peta['MENGUNDURKAN_DIRI']['lolos']);
        $this->assertSame('keluar', $peta['MENGUNDURKAN_DIRI']['bucket']);
        $this->assertSame('lulus', $peta['LULUS']['bucket']);
    }
}
