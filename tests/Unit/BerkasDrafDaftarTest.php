<?php

namespace Tests\Unit;

use App\Http\Controllers\Career\Lamaran\FormulirDrafController;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * jadikanPermanen() harus membaca Berkas_Json BERBENTUK DAFTAR, dan tetap
 * membaca bentuk peta lama milik draf yang dibuat sebelum perubahan ini.
 */
class BerkasDrafDaftarTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);

        Schema::create('N_WEB_CAREERS_Formulir_Draf', function (Blueprint $table) {
            $table->increments('Id_Formulir_Draf');
            $table->integer('Lamaran_Id')->nullable();
            $table->integer('Lamaran_Tahap_Id');
            $table->integer('Id_Users');
            $table->string('Formulir_Kode')->nullable();
            $table->text('Jawaban_Json')->nullable();
            $table->text('Berkas_Json')->nullable();
            $table->integer('Langkah_Terakhir')->default(0);
        });

        Schema::create('N_WEB_CAREERS_Formulir_Berkas', function (Blueprint $table) {
            $table->increments('Id_Formulir_Berkas');
            $table->integer('Formulir_Pengisian_Id');
            $table->integer('Id_Users')->nullable();
            $table->string('Bagian_Key')->nullable();
            $table->integer('Baris_Index')->nullable();
            $table->string('Field_Key');
            $table->integer('Urutan');
            $table->string('Nama_Asli');
            $table->string('Path_File')->nullable();
            $table->bigInteger('Ukuran_Byte')->nullable();
            $table->string('Mime')->nullable();
            $table->string('Ekstensi')->nullable();
            $table->string('Status_Verifikasi');
            $table->dateTime('Waktu_Unggah')->nullable();
            $table->dateTime('Created_At')->nullable();
            $table->string('Created_By')->nullable();
            $table->integer('Created_By_Id')->nullable();
            $table->dateTime('Updated_At')->nullable();
            $table->string('Updated_By')->nullable();
            $table->integer('Updated_By_Id')->nullable();
        });
    }

    private function draf(array $berkas, array $jawaban = []): void
    {
        DB::table('N_WEB_CAREERS_Formulir_Draf')->insert([
            'Lamaran_Tahap_Id' => 1,
            'Id_Users' => 9,
            'Jawaban_Json' => json_encode($jawaban),
            'Berkas_Json' => json_encode($berkas),
        ]);
    }

    public function test_membaca_berkas_json_berbentuk_daftar(): void
    {
        $this->draf([
            ['bagian' => null, 'baris' => null, 'field' => 'dok_cv',
                'nama' => 'cv.pdf', 'path' => 'a/cv.pdf', 'ukuran' => 10, 'mime' => 'application/pdf'],
        ]);

        $jumlah = FormulirDrafController::jadikanPermanen(1, 9, 77, []);

        $this->assertSame(1, $jumlah);
        $this->assertSame('dok_cv', DB::table('N_WEB_CAREERS_Formulir_Berkas')->value('Field_Key'));
    }

    public function test_masih_membaca_bentuk_peta_lama(): void
    {
        $this->draf([
            'dok_cv' => ['nama' => 'cv.pdf', 'path' => 'a/cv.pdf', 'ukuran' => 10, 'mime' => 'application/pdf'],
        ]);

        $jumlah = FormulirDrafController::jadikanPermanen(1, 9, 77, []);

        $this->assertSame(1, $jumlah);
        $this->assertSame('a/cv.pdf', DB::table('N_WEB_CAREERS_Formulir_Berkas')->value('Path_File'));
    }
}
