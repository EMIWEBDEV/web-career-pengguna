<?php

namespace Tests\Unit;

use App\Support\Career\HasilKeputusan;
use App\Support\Career\MetrikRekrutmen;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * sqlUrutanDisplay() menentukan di KOLOM MANA seorang pelamar muncul di papan
 * dan funnel. Sebelum perbaikan, CASE-nya hanya mengenal GUGUR/TALENT_POOL/
 * LULUS; status terminal lain jatuh ke cabang ELSE dan berakhir di
 * MIN(lt.Urutan) = tahap 1. Kandidat yang menolak penawaran di tahap 7
 * dilaporkan sebagai pelamar baru di Pendaftaran.
 *
 * SQL-nya murni ANSI (tanpa DATEDIFF/GETDATE), jadi berbeda dari
 * agregatSehat() ia bisa DIEKSEKUSI sungguhan di SQLite — bukan sekadar
 * diperiksa sebagai string lewat pretend().
 */
class MetrikRekrutmenUrutanDisplayTest extends TestCase
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
            ['Kode' => 'DITOLAK_KANDIDAT', 'Nama' => 'Ditolak Kandidat', 'Flag_Lolos' => 'T', 'Flag_Oleh_Kandidat' => 'Y', 'Flag_Talent_Pool' => 'Y', 'Flag_Potong_Kuota' => 'T', 'Flag_Aktif' => 'Y', 'Urutan' => 4],
            ['Kode' => 'MENGUNDURKAN_DIRI', 'Nama' => 'Mundur', 'Flag_Lolos' => 'T', 'Flag_Oleh_Kandidat' => 'Y', 'Flag_Talent_Pool' => 'Y', 'Flag_Potong_Kuota' => 'T', 'Flag_Aktif' => 'Y', 'Urutan' => 5],
        ]);
        HasilKeputusan::lupakanCache();

        Schema::create('N_WEB_CAREERS_Lamaran', function (Blueprint $t) {
            $t->increments('Id_Lamaran');
            $t->integer('Program_Id');
            $t->string('Status');
        });
        Schema::create('N_WEB_CAREERS_Lamaran_Tahap', function (Blueprint $t) {
            $t->increments('Id_Lamaran_Tahap');
            $t->integer('Lamaran_Id');
            $t->string('Status');
            $t->string('Hasil')->nullable();
            $t->integer('Urutan');
        });

        DB::table('N_WEB_CAREERS_Lamaran')->insert([
            ['Id_Lamaran' => 102, 'Program_Id' => 1, 'Status' => 'DITOLAK_KANDIDAT'],
            ['Id_Lamaran' => 118, 'Program_Id' => 1, 'Status' => 'MENGUNDURKAN_DIRI'],
            ['Id_Lamaran' => 200, 'Program_Id' => 1, 'Status' => 'GUGUR'],
            ['Id_Lamaran' => 201, 'Program_Id' => 1, 'Status' => 'TALENT_POOL'],
            ['Id_Lamaran' => 202, 'Program_Id' => 1, 'Status' => 'LULUS'],
            ['Id_Lamaran' => 203, 'Program_Id' => 1, 'Status' => 'BERJALAN'],
            ['Id_Lamaran' => 204, 'Program_Id' => 1, 'Status' => 'DRAFT'],
            ['Id_Lamaran' => 205, 'Program_Id' => 1, 'Status' => 'GUGUR'],
            ['Id_Lamaran' => 206, 'Program_Id' => 1, 'Status' => 'BERJALAN'],
        ]);

        $rows = [];
        // 102 — lolos sampai u6, MENOLAK PENAWARAN di u7 (tahap terakhir).
        foreach ([1, 2, 3, 4, 5, 6] as $u) {
            $rows[] = ['Lamaran_Id' => 102, 'Status' => 'SELESAI', 'Hasil' => 'LULUS', 'Urutan' => $u];
        }
        $rows[] = ['Lamaran_Id' => 102, 'Status' => 'SELESAI', 'Hasil' => 'DITOLAK_KANDIDAT', 'Urutan' => 7];

        // 118 — MUNDUR di u4; u5-u7 tidak pernah dijalani.
        foreach ([1, 2, 3] as $u) {
            $rows[] = ['Lamaran_Id' => 118, 'Status' => 'SELESAI', 'Hasil' => 'LULUS', 'Urutan' => $u];
        }
        $rows[] = ['Lamaran_Id' => 118, 'Status' => 'SELESAI', 'Hasil' => 'MENGUNDURKAN_DIRI', 'Urutan' => 4];
        foreach ([5, 6, 7] as $u) {
            $rows[] = ['Lamaran_Id' => 118, 'Status' => 'BELUM', 'Hasil' => null, 'Urutan' => $u];
        }

        $rows[] = ['Lamaran_Id' => 200, 'Status' => 'SELESAI', 'Hasil' => 'LULUS', 'Urutan' => 1];
        $rows[] = ['Lamaran_Id' => 200, 'Status' => 'SELESAI', 'Hasil' => 'GUGUR', 'Urutan' => 2];
        $rows[] = ['Lamaran_Id' => 200, 'Status' => 'BELUM', 'Hasil' => null, 'Urutan' => 3];

        $rows[] = ['Lamaran_Id' => 201, 'Status' => 'SELESAI', 'Hasil' => 'LULUS', 'Urutan' => 1];
        $rows[] = ['Lamaran_Id' => 201, 'Status' => 'SELESAI', 'Hasil' => 'TALENT_POOL', 'Urutan' => 3];

        foreach ([1, 2, 3, 4] as $u) {
            $rows[] = ['Lamaran_Id' => 202, 'Status' => 'SELESAI', 'Hasil' => 'LULUS', 'Urutan' => $u];
        }

        $rows[] = ['Lamaran_Id' => 203, 'Status' => 'SELESAI', 'Hasil' => 'LULUS', 'Urutan' => 1];
        $rows[] = ['Lamaran_Id' => 203, 'Status' => 'BERJALAN', 'Hasil' => null, 'Urutan' => 2];

        $rows[] = ['Lamaran_Id' => 204, 'Status' => 'BERJALAN', 'Hasil' => null, 'Urutan' => 1];

        // 205 — GUGUR tapi tak ada satu pun baris ber-Hasil: fallback ke tahap terakhir.
        $rows[] = ['Lamaran_Id' => 205, 'Status' => 'SELESAI', 'Hasil' => null, 'Urutan' => 1];
        $rows[] = ['Lamaran_Id' => 205, 'Status' => 'SELESAI', 'Hasil' => null, 'Urutan' => 3];

        DB::table('N_WEB_CAREERS_Lamaran_Tahap')->insert($rows);
        // 206 sengaja TANPA baris tahap sama sekali.
    }

    protected function tearDown(): void
    {
        HasilKeputusan::lupakanCache();
        Schema::dropIfExists('N_WEB_CAREERS_Lamaran_Tahap');
        Schema::dropIfExists('N_WEB_CAREERS_Lamaran');
        Schema::dropIfExists('N_WEB_CAREERS_Master_Hasil_Keputusan');
        parent::tearDown();
    }

    private function urutan(): array
    {
        $sql = MetrikRekrutmen::sqlUrutanDisplay('l.Program_Id = 1');
        $out = [];
        foreach (DB::select($sql) as $r) {
            $out[(int) $r->Id_Lamaran] = (int) $r->UrutanDisplay;
        }

        return $out;
    }

    /** Inti perbaikan: keluar di tahap tempat keputusannya dicatat, bukan tahap 1. */
    public function test_status_terminal_baru_ditempatkan_di_tahap_keluarnya(): void
    {
        $u = $this->urutan();

        $this->assertSame(7, $u[102], 'Menolak penawaran di tahap 7 harus tampil di tahap 7.');
        $this->assertSame(4, $u[118], 'Mundur di tahap 4 harus tampil di tahap 4.');
    }

    /**
     * Perbaikan ini TIDAK boleh menggeser satu pun kandidat yang selama ini
     * sudah benar. Cabang lama untuk GUGUR/TALENT_POOL digeneralisasi, jadi
     * angkanya wajib identik.
     */
    public function test_penempatan_lama_tidak_bergeser(): void
    {
        $u = $this->urutan();

        $this->assertSame(2, $u[200], 'GUGUR tetap di tahap tempat ia gugur.');
        $this->assertSame(3, $u[201], 'TALENT_POOL tetap di tahap tempat ia dimasukkan pool.');
        $this->assertSame(4, $u[202], 'LULUS tetap di tahap terakhir.');
        $this->assertSame(2, $u[203], 'BERJALAN tetap di tahap yang sedang berjalan.');
        $this->assertSame(3, $u[205], 'GUGUR tanpa jejak Hasil jatuh ke tahap terakhir.');
    }

    /**
     * Status yang TIDAK dikenal master (mis. DRAFT) harus tetap diperlakukan
     * sebagai masih berproses — bukan dilempar ke tahap terakhir. Ini yang
     * membedakan cabang ELSE baru dari sekadar "selain LULUS berarti terminal".
     */
    public function test_status_asing_tetap_diperlakukan_berjalan(): void
    {
        $this->assertSame(1, $this->urutan()[204]);
    }

    public function test_lamaran_tanpa_tahap_masuk_kolom_satu(): void
    {
        $this->assertSame(1, $this->urutan()[206]);
    }
}
