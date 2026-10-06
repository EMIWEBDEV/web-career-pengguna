<?php

namespace Tests\Unit;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Memverifikasi bentuk query KPI baru di MonitoringController::live() —
 * "Ditahan" (kpiHold) dan "Proses Administrasi" (kpiPascaPenerimaan) — lewat
 * SQLite in-memory dengan Schema::create() manual, meniru pola
 * LamaranTargetValidationTest. Query-query ini tidak mencampur Hold_Flag
 * dengan DATEDIFF/GETDATE dalam satu selectRaw, jadi SQLite sungguhan aman
 * dipakai (tidak perlu DB::connection()->pretend()).
 */
class MonitoringLiveKpiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);

        Schema::create('N_WEB_CAREERS_Lamaran', function (Blueprint $table) {
            $table->increments('Id_Lamaran');
            $table->integer('Program_Id');
            $table->string('Status');
        });

        Schema::create('N_WEB_CAREERS_Lamaran_Tahap', function (Blueprint $table) {
            $table->increments('Id_Lamaran_Tahap');
            $table->integer('Lamaran_Id');
            $table->string('Status');
            // Nullable (tanpa default) SENGAJA: test NULL-safety di bawah
            // butuh baris yang Hold_Flag-nya betul-betul NULL, bukan 'T'.
            $table->string('Hold_Flag')->nullable();
            $table->string('Flag_Tuntas')->default('T');
            $table->string('Siap_Diputus')->nullable();
            $table->string('Provider')->nullable();
            $table->integer('Urutan')->default(1);
        });

        DB::table('N_WEB_CAREERS_Lamaran')->insert([
            ['Id_Lamaran' => 1, 'Program_Id' => 1, 'Status' => 'BERJALAN'],
            ['Id_Lamaran' => 2, 'Program_Id' => 1, 'Status' => 'LULUS'],
            ['Id_Lamaran' => 3, 'Program_Id' => 1, 'Status' => 'BERJALAN'],
            ['Id_Lamaran' => 4, 'Program_Id' => 1, 'Status' => 'LULUS'],
        ]);

        DB::table('N_WEB_CAREERS_Lamaran_Tahap')->insert([
            // #1: DITAHAN
            ['Lamaran_Id' => 1, 'Status' => 'BERJALAN', 'Hold_Flag' => 'Y', 'Flag_Tuntas' => 'T', 'Urutan' => 1],
            // #2: PASCAPENERIMAAN (LULUS tapi masih ada tahap administratif BERJALAN)
            ['Lamaran_Id' => 2, 'Status' => 'SELESAI', 'Hold_Flag' => 'T', 'Flag_Tuntas' => 'Y', 'Urutan' => 1],
            ['Lamaran_Id' => 2, 'Status' => 'BERJALAN', 'Hold_Flag' => 'T', 'Flag_Tuntas' => 'T', 'Urutan' => 2],
            // #3: berproses biasa
            ['Lamaran_Id' => 3, 'Status' => 'BERJALAN', 'Hold_Flag' => 'T', 'Flag_Tuntas' => 'T', 'Urutan' => 1],
            // #4: sudah LULUS, tetapi tahap administratif sedang HOLD. HOLD menang.
            ['Lamaran_Id' => 4, 'Status' => 'BERJALAN', 'Hold_Flag' => 'Y', 'Flag_Tuntas' => 'T', 'Urutan' => 2],
        ]);
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('N_WEB_CAREERS_Lamaran_Tahap');
        Schema::dropIfExists('N_WEB_CAREERS_Lamaran');
        parent::tearDown();
    }

    public function test_hitung_ditahan_dan_pascapenerimaan(): void
    {
        // HOLD selalu menang: kandidat LULUS dengan tahap administratif HOLD
        // masuk bucket Ditahan, bukan Proses Administrasi.
        $ditahan = DB::table('N_WEB_CAREERS_Lamaran_Tahap as lt')
            ->join('N_WEB_CAREERS_Lamaran as l', 'l.Id_Lamaran', '=', 'lt.Lamaran_Id')
            ->whereIn('l.Status', ['BERJALAN', 'LULUS'])->where('lt.Status', 'BERJALAN')
            ->where('lt.Hold_Flag', 'Y')
            ->count();

        $pascaPenerimaan = DB::table('N_WEB_CAREERS_Lamaran_Tahap as lt')
            ->join('N_WEB_CAREERS_Lamaran as l', 'l.Id_Lamaran', '=', 'lt.Lamaran_Id')
            ->where('l.Status', 'LULUS')->where('lt.Status', 'BERJALAN')
            ->whereRaw(\App\Support\Career\MetrikRekrutmen::sqlBukanDitahan('lt'))
            ->distinct('l.Id_Lamaran')
            ->count('l.Id_Lamaran');

        $this->assertSame(2, $ditahan);
        $this->assertSame(1, $pascaPenerimaan);
    }

    /**
     * kpiTahap (siapDiputus/menungguTes) HARUS mengecualikan HOLD dengan
     * predikat NULL-safe COALESCE(lt.Hold_Flag, 'T') <> 'Y' — bukan
     * `lt.Hold_Flag <> 'Y'` telanjang. Predikat telanjang membuat NULL <> 'Y'
     * bernilai NULL (bukan TRUE) di SQL, sehingga baris Hold_Flag NULL diam-
     * diam ikut terkecualikan padahal seharusnya dihitung TIDAK ditahan —
     * bug yang sama yang sudah diperbaiki di MetrikRekrutmen::agregatSehat()
     * pada Task 4 (lihat MetrikRekrutmenAgregatSehatTest).
     *
     * Query di sini SENGAJA disusun identik dengan yang ditempel ke
     * MonitoringController::live() ($kpiTahap), supaya test membuktikan
     * bentuk query itu sendiri sebelum ditempel — pola yang sama dengan
     * test_hitung_ditahan_dan_pascapenerimaan() di atas.
     */
    public function test_kpi_tahap_mengecualikan_hold_null_safe(): void
    {
        DB::table('N_WEB_CAREERS_Lamaran')->insert([
            ['Id_Lamaran' => 10, 'Program_Id' => 9, 'Status' => 'BERJALAN'], // Hold_Flag NULL, siap diputus
            ['Id_Lamaran' => 11, 'Program_Id' => 9, 'Status' => 'BERJALAN'], // Hold_Flag Y, siap diputus (harus dikecualikan)
            ['Id_Lamaran' => 12, 'Program_Id' => 9, 'Status' => 'BERJALAN'], // Hold_Flag NULL, menunggu tes
            ['Id_Lamaran' => 13, 'Program_Id' => 9, 'Status' => 'BERJALAN'], // Hold_Flag Y, menunggu tes (harus dikecualikan)
        ]);

        DB::table('N_WEB_CAREERS_Lamaran_Tahap')->insert([
            ['Lamaran_Id' => 10, 'Status' => 'BERJALAN', 'Hold_Flag' => null, 'Siap_Diputus' => 'Y', 'Provider' => null, 'Urutan' => 1],
            ['Lamaran_Id' => 11, 'Status' => 'BERJALAN', 'Hold_Flag' => 'Y', 'Siap_Diputus' => 'Y', 'Provider' => null, 'Urutan' => 1],
            ['Lamaran_Id' => 12, 'Status' => 'BERJALAN', 'Hold_Flag' => null, 'Siap_Diputus' => 'N', 'Provider' => 'THIRD_PARTY', 'Urutan' => 1],
            ['Lamaran_Id' => 13, 'Status' => 'BERJALAN', 'Hold_Flag' => 'Y', 'Siap_Diputus' => 'N', 'Provider' => 'THIRD_PARTY', 'Urutan' => 1],
        ]);

        $kpiTahap = DB::table('N_WEB_CAREERS_Lamaran_Tahap as lt')
            ->join('N_WEB_CAREERS_Lamaran as l', 'l.Id_Lamaran', '=', 'lt.Lamaran_Id')
            ->where('lt.Status', 'BERJALAN')->where('l.Status', 'BERJALAN')
            ->whereRaw("COALESCE(lt.Hold_Flag, 'T') <> 'Y'")
            ->whereIn('l.Program_Id', [9])
            ->selectRaw("SUM(CASE WHEN lt.Siap_Diputus = 'Y' THEN 1 ELSE 0 END) as siap,
                         SUM(CASE WHEN lt.Provider = 'THIRD_PARTY' AND lt.Siap_Diputus = 'N' THEN 1 ELSE 0 END) as nunggu")
            ->first();

        $this->assertSame(1, (int) $kpiTahap->siap, 'Hold_Flag NULL harus tetap terhitung siap diputus.');
        $this->assertSame(1, (int) $kpiTahap->nunggu, 'Hold_Flag NULL harus tetap terhitung menunggu tes.');
    }
}
