<?php

namespace Tests\Unit;

use App\Support\Career\LamaranTargetValidator;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LamaranTargetValidationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);

        Carbon::setTestNow('2026-08-01 10:00:00');

        Schema::create('N_WEB_CAREERS_Program', function (Blueprint $table) {
            $table->increments('Id_Program');
            $table->string('Kategori');
            $table->string('Status');
        });

        Schema::create('N_WEB_CAREERS_Program_Posisi', function (Blueprint $table) {
            $table->increments('Id_Program_Posisi');
            $table->integer('Program_Id');
            $table->string('Status');
        });

        Schema::create('N_WEB_CAREERS_Pembukaan', function (Blueprint $table) {
            $table->increments('Id_Pembukaan');
            $table->integer('Program_Id');
            $table->string('Status_Publish');
            $table->string('Masa_Berlaku');
            $table->dateTime('Tanggal_Buka')->nullable();
            $table->dateTime('Tanggal_Tutup')->nullable();
        });
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        Schema::dropIfExists('N_WEB_CAREERS_Pembukaan');
        Schema::dropIfExists('N_WEB_CAREERS_Program_Posisi');
        Schema::dropIfExists('N_WEB_CAREERS_Program');

        parent::tearDown();
    }

    public function test_target_rekrutmen_magang_dan_mt_mengembalikan_pasangan_yang_tepat(): void
    {
        foreach (['REKRUTMEN', 'INTERNSHIP', 'MT'] as $index => $kategori) {
            $programId = $index + 1;
            $posisiId = $index + 11;
            $pembukaanId = $index + 21;

            DB::table('N_WEB_CAREERS_Program')->insert([
                'Id_Program' => $programId,
                'Kategori' => $kategori,
                'Status' => 'BERJALAN',
            ]);
            DB::table('N_WEB_CAREERS_Program_Posisi')->insert([
                'Id_Program_Posisi' => $posisiId,
                'Program_Id' => $programId,
                'Status' => 'BUKA',
            ]);
            DB::table('N_WEB_CAREERS_Pembukaan')->insert([
                'Id_Pembukaan' => $pembukaanId,
                'Program_Id' => $programId,
                'Status_Publish' => 'TERBIT',
                'Masa_Berlaku' => 'EVERGREEN',
            ]);

            $hasil = (new LamaranTargetValidator())->validasi($pembukaanId, $posisiId);

            $this->assertTrue($hasil['ok']);
            $this->assertSame($kategori, $hasil['program']->Kategori);
            $this->assertSame($programId, (int) $hasil['posisi']->Program_Id);
            $this->assertSame($programId, (int) $hasil['pembukaan']->Program_Id);
        }
    }

    public function test_posisi_dari_program_lain_ditolak(): void
    {
        DB::table('N_WEB_CAREERS_Program')->insert([
            ['Id_Program' => 1, 'Kategori' => 'MT', 'Status' => 'BERJALAN'],
            ['Id_Program' => 2, 'Kategori' => 'REKRUTMEN', 'Status' => 'BERJALAN'],
        ]);
        DB::table('N_WEB_CAREERS_Program_Posisi')->insert([
            'Id_Program_Posisi' => 12,
            'Program_Id' => 2,
            'Status' => 'BUKA',
        ]);
        DB::table('N_WEB_CAREERS_Pembukaan')->insert([
            'Id_Pembukaan' => 21,
            'Program_Id' => 1,
            'Status_Publish' => 'TERBIT',
            'Masa_Berlaku' => 'EVERGREEN',
        ]);

        $hasil = (new LamaranTargetValidator())->validasi(21, 12);

        $this->assertFalse($hasil['ok']);
        $this->assertSame('Posisi tidak ditemukan pada program ini.', $hasil['pesan']);
    }

    public function test_pembukaan_yang_sudah_ditutup_ditolak(): void
    {
        DB::table('N_WEB_CAREERS_Program')->insert([
            'Id_Program' => 1,
            'Kategori' => 'INTERNSHIP',
            'Status' => 'BERJALAN',
        ]);
        DB::table('N_WEB_CAREERS_Program_Posisi')->insert([
            'Id_Program_Posisi' => 11,
            'Program_Id' => 1,
            'Status' => 'BUKA',
        ]);
        DB::table('N_WEB_CAREERS_Pembukaan')->insert([
            'Id_Pembukaan' => 21,
            'Program_Id' => 1,
            'Status_Publish' => 'TERBIT',
            'Masa_Berlaku' => 'BERBATAS',
            'Tanggal_Buka' => '2026-07-01 00:00:00',
            'Tanggal_Tutup' => '2026-07-31 23:59:59',
        ]);

        $hasil = (new LamaranTargetValidator())->validasi(21, 11);

        $this->assertFalse($hasil['ok']);
        $this->assertSame('Pendaftaran sudah ditutup.', $hasil['pesan']);
    }
}
