<?php

namespace Tests\Unit;

use App\Support\Career\LamaranService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LamaranFormPersistenceTest extends TestCase
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
            $table->integer('Id_Users');
            $table->string('Kategori');
            $table->integer('Program_Id');
            $table->integer('Program_Batch_Id')->nullable();
        });

        Schema::create('N_WEB_CAREERS_Lamaran_Tahap', function (Blueprint $table) {
            $table->increments('Id_Lamaran_Tahap');
            $table->integer('Lamaran_Id');
            $table->integer('Master_Alur_Tahap_Id')->nullable();
            $table->integer('Urutan');
            $table->string('Formulir_Kode')->nullable();
            $table->string('Status');
            $table->integer('Formulir_Pengisian_Id')->nullable();
            $table->string('Rekomendasi')->nullable();
            $table->text('Rekomendasi_Alasan')->nullable();
            $table->dateTime('Rekomendasi_At')->nullable();
            $table->text('Jejak_Json')->nullable();
            $table->dateTime('Waktu_Mulai')->nullable();
            $table->dateTime('Updated_At')->nullable();
            $table->string('Updated_By')->nullable();
            $table->integer('Updated_By_Id')->nullable();
        });

        Schema::create('N_WEB_CAREERS_Master_Formulir', function (Blueprint $table) {
            $table->increments('Id_Master_Formulir');
            $table->string('Kode');
            $table->string('Komponen_Kode');
            $table->string('Flag_Aktif');
        });

        Schema::create('N_WEB_CAREERS_Program_Syarat', function (Blueprint $table) {
            $table->increments('Id_Program_Syarat');
            $table->integer('Program_Id');
            $table->integer('Master_Alur_Tahap_Id')->nullable();
            $table->string('Flag_Aktif');
            $table->integer('Urutan')->default(1);
        });

        Schema::create('N_WEB_CAREERS_Formulir_Pengisian', function (Blueprint $table) {
            $table->increments('Id_Formulir_Pengisian');
            $table->string('Kode');
            $table->integer('Id_Users');
            $table->integer('Lamaran_Id');
            $table->integer('Lamaran_Tahap_Id');
            $table->integer('Master_Formulir_Id')->nullable();
            $table->string('Komponen_Kode')->nullable();
            $table->string('Sumber');
            $table->integer('Master_Alur_Tahap_Id')->nullable();
            $table->integer('Program_Id');
            $table->integer('Program_Batch_Id')->nullable();
            $table->text('Jawaban_Json');
            $table->integer('Langkah_Terakhir');
            $table->string('Status');
            $table->dateTime('Waktu_Kirim');
            $table->string('Ip_Pengirim')->nullable();
            $table->dateTime('Created_At');
            $table->string('Created_By');
            $table->integer('Created_By_Id');
            $table->dateTime('Updated_At');
            $table->string('Updated_By');
            $table->integer('Updated_By_Id');
        });
    }

    public function test_formulir_pendaftaran_tetap_disimpan_saat_tahap_pertama_belum_memiliki_kode(): void
    {
        DB::table('N_WEB_CAREERS_Lamaran')->insert([
            'Id_Lamaran' => 10,
            'Id_Users' => 7,
            'Kategori' => 'MT',
            'Program_Id' => 3,
        ]);
        DB::table('N_WEB_CAREERS_Lamaran_Tahap')->insert([
            'Id_Lamaran_Tahap' => 20,
            'Lamaran_Id' => 10,
            'Master_Alur_Tahap_Id' => 30,
            'Urutan' => 1,
            'Formulir_Kode' => null,
            'Status' => 'BERJALAN',
        ]);
        DB::table('N_WEB_CAREERS_Master_Formulir')->insert([
            'Id_Master_Formulir' => 40,
            'Kode' => 'DAFTAR-MT',
            'Komponen_Kode' => 'FORMULIR_1',
            'Flag_Aktif' => 'Y',
        ]);

        $hasil = (new LamaranService())->simpanPengisian(20, 7, [
            'nama' => 'Kandidat Uji',
            'email' => 'kandidat@example.test',
        ]);

        $this->assertTrue($hasil['ok']);
        $this->assertDatabaseHas('N_WEB_CAREERS_Formulir_Pengisian', [
            'Lamaran_Id' => 10,
            'Lamaran_Tahap_Id' => 20,
            'Master_Formulir_Id' => 40,
            'Komponen_Kode' => 'FORMULIR_1',
            'Sumber' => 'PENDAFTARAN',
            'Status' => 'TERKIRIM',
        ]);

        $jawaban = json_decode(
            DB::table('N_WEB_CAREERS_Formulir_Pengisian')->value('Jawaban_Json'),
            true,
        );
        $this->assertSame('Kandidat Uji', $jawaban['nama']);
        $this->assertNotNull(
            DB::table('N_WEB_CAREERS_Lamaran_Tahap')
                ->where('Id_Lamaran_Tahap', 20)
                ->value('Formulir_Pengisian_Id'),
        );
    }

    public function test_tahap_lanjutan_tanpa_formulir_tetap_ditolak(): void
    {
        DB::table('N_WEB_CAREERS_Lamaran')->insert([
            'Id_Lamaran' => 11,
            'Id_Users' => 7,
            'Kategori' => 'MT',
            'Program_Id' => 3,
        ]);
        DB::table('N_WEB_CAREERS_Lamaran_Tahap')->insert([
            'Id_Lamaran_Tahap' => 21,
            'Lamaran_Id' => 11,
            'Master_Alur_Tahap_Id' => 31,
            'Urutan' => 2,
            'Formulir_Kode' => null,
            'Status' => 'BERJALAN',
        ]);

        $hasil = (new LamaranService())->simpanPengisian(21, 7, ['foo' => 'bar']);

        $this->assertFalse($hasil['ok']);
        $this->assertDatabaseCount('N_WEB_CAREERS_Formulir_Pengisian', 0);
    }
}
