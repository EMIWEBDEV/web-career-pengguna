<?php

namespace Tests\Unit;

use App\Http\Controllers\Career\Dashboard\DashboardController;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ReflectionMethod;
use Tests\TestCase;

class DashboardRekapPerPosisiHoldTest extends TestCase
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
            $table->integer('Program_Posisi_Id')->nullable();
            $table->string('Status');
        });

        Schema::create('N_WEB_CAREERS_Lamaran_Tahap', function (Blueprint $table) {
            $table->increments('Id_Lamaran_Tahap');
            $table->integer('Lamaran_Id');
            $table->string('Status');
            $table->string('Hold_Flag')->default('T');
        });

        DB::table('N_WEB_CAREERS_Lamaran')->insert([
            ['Id_Lamaran' => 1, 'Program_Id' => 1, 'Program_Posisi_Id' => 10, 'Status' => 'BERJALAN'],
            ['Id_Lamaran' => 2, 'Program_Id' => 1, 'Program_Posisi_Id' => 10, 'Status' => 'BERJALAN'],
            // Program lain: membuktikan query kandidat HOLD tetap terscope ke $ids.
            ['Id_Lamaran' => 3, 'Program_Id' => 2, 'Program_Posisi_Id' => 20, 'Status' => 'BERJALAN'],
        ]);

        DB::table('N_WEB_CAREERS_Lamaran_Tahap')->insert([
            ['Lamaran_Id' => 1, 'Status' => 'BERJALAN', 'Hold_Flag' => 'Y'],  // ditahan
            ['Lamaran_Id' => 2, 'Status' => 'BERJALAN', 'Hold_Flag' => 'T'],  // tidak ditahan
            ['Lamaran_Id' => 3, 'Status' => 'BERJALAN', 'Hold_Flag' => 'Y'],  // program lain
        ]);
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('N_WEB_CAREERS_Lamaran_Tahap');
        Schema::dropIfExists('N_WEB_CAREERS_Lamaran');
        parent::tearDown();
    }

    public function test_rekap_berjalan_mengecualikan_kandidat_ditahan(): void
    {
        $controller = new DashboardController;
        $reflection = new ReflectionMethod($controller, 'rekapPerPosisi');
        $reflection->setAccessible(true);

        $rekap = $reflection->invoke($controller, [1]);
        $baris = $rekap->get(10);

        $this->assertSame(2, (int) $baris->total);
        $this->assertSame(1, (int) $baris->berjalan);
    }
}
