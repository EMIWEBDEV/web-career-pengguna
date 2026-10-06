<?php

namespace Tests\Unit;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DashboardAntreanAksiHoldTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);

        Carbon::setTestNow('2026-08-04 10:00:00');

        Schema::create('N_WEB_CAREERS_Lamaran', function (Blueprint $table) {
            $table->increments('Id_Lamaran');
            $table->integer('Program_Id');
            $table->string('Status');
            $table->string('Created_By')->nullable();
        });

        Schema::create('N_WEB_CAREERS_Lamaran_Tahap', function (Blueprint $table) {
            $table->increments('Id_Lamaran_Tahap');
            $table->integer('Lamaran_Id');
            $table->string('Status');
            $table->string('Hold_Flag')->default('T')->nullable();
            $table->string('Siap_Diputus')->default('N');
            $table->string('Provider')->nullable();
            $table->string('Tipe_Tahap_Kode')->nullable();
            $table->integer('Urutan')->default(1);
            $table->string('Label')->nullable();
            $table->integer('Penjadwalan_Tahap_Id')->nullable();
            $table->dateTime('Created_At')->nullable();
        });

        DB::table('N_WEB_CAREERS_Lamaran')->insert([
            ['Id_Lamaran' => 1, 'Program_Id' => 1, 'Status' => 'BERJALAN', 'Created_By' => 'Budi'],
            ['Id_Lamaran' => 2, 'Program_Id' => 1, 'Status' => 'BERJALAN', 'Created_By' => 'Ani'],
        ]);

        DB::table('N_WEB_CAREERS_Lamaran_Tahap')->insert([
            // #1: DITAHAN, siap diputus — TIDAK boleh muncul di antrean SIAP_DIPUTUS.
            ['Lamaran_Id' => 1, 'Status' => 'BERJALAN', 'Hold_Flag' => 'Y', 'Siap_Diputus' => 'Y', 'Created_At' => Carbon::now()->subDays(10)],
            // #2: tidak ditahan, siap diputus — HARUS muncul.
            ['Lamaran_Id' => 2, 'Status' => 'BERJALAN', 'Hold_Flag' => 'T', 'Siap_Diputus' => 'Y', 'Created_At' => Carbon::now()->subDays(10)],
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        Schema::dropIfExists('N_WEB_CAREERS_Lamaran_Tahap');
        Schema::dropIfExists('N_WEB_CAREERS_Lamaran');
        parent::tearDown();
    }

    public function test_kandidat_ditahan_tidak_masuk_antrean_siap_diputus(): void
    {
        $rows = DB::table('N_WEB_CAREERS_Lamaran_Tahap as lt')
            ->join('N_WEB_CAREERS_Lamaran as l', 'l.Id_Lamaran', '=', 'lt.Lamaran_Id')
            ->where('lt.Status', 'BERJALAN')->where('l.Status', 'BERJALAN')
            ->where('lt.Siap_Diputus', 'Y')
            ->whereRaw("COALESCE(lt.Hold_Flag, 'T') <> 'Y'")
            ->pluck('l.Id_Lamaran');

        $this->assertCount(1, $rows);
        $this->assertSame(2, (int) $rows->first());
    }

    public function test_predikat_bare_tanpa_coalesce_akan_gagal_kalau_hold_flag_null(): void
    {
        // Baris pembanding: Hold_Flag NULL secara eksplisit (bukan default 'T').
        // Predikat bare `<> 'Y'` akan MENGECUALIKAN baris ini secara keliru
        // (NULL <> 'Y' = NULL, bukan TRUE) — inilah bug yang harus dicegah.
        DB::table('N_WEB_CAREERS_Lamaran')->insert(
            ['Id_Lamaran' => 3, 'Program_Id' => 1, 'Status' => 'BERJALAN', 'Created_By' => 'Citra']
        );
        DB::table('N_WEB_CAREERS_Lamaran_Tahap')->insert(
            ['Lamaran_Id' => 3, 'Status' => 'BERJALAN', 'Hold_Flag' => null, 'Siap_Diputus' => 'Y', 'Created_At' => Carbon::now()->subDays(10)]
        );

        $bare = DB::table('N_WEB_CAREERS_Lamaran_Tahap as lt')
            ->join('N_WEB_CAREERS_Lamaran as l', 'l.Id_Lamaran', '=', 'lt.Lamaran_Id')
            ->where('lt.Status', 'BERJALAN')->where('l.Status', 'BERJALAN')
            ->where('lt.Siap_Diputus', 'Y')
            ->where('lt.Hold_Flag', '<>', 'Y')
            ->pluck('l.Id_Lamaran');

        $nullSafe = DB::table('N_WEB_CAREERS_Lamaran_Tahap as lt')
            ->join('N_WEB_CAREERS_Lamaran as l', 'l.Id_Lamaran', '=', 'lt.Lamaran_Id')
            ->where('lt.Status', 'BERJALAN')->where('l.Status', 'BERJALAN')
            ->where('lt.Siap_Diputus', 'Y')
            ->whereRaw("COALESCE(lt.Hold_Flag, 'T') <> 'Y'")
            ->pluck('l.Id_Lamaran');

        // Bare predikat kehilangan Lamaran #3 (Hold_Flag NULL) — bug yang dihindari.
        $this->assertNotContains(3, $bare->all());
        // Predikat NULL-safe yang dipakai controller tetap menyertakan #3.
        $this->assertContains(3, $nullSafe->all());
        $this->assertCount(2, $nullSafe);
    }
}
