<?php

namespace Tests\Unit;

use App\Http\Controllers\Career\Lamaran\FormulirDrafController;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * REPRODUKSI pengisian 61: tiga sertifikat diisi, satu tersimpan.
 *
 * Penyebabnya penjaga idempotensi yang memakai (pengisian, field) — dan karena
 * tiap baris berulang memakai field yang sama persis, dua berkas berikutnya
 * dianggap duplikat lalu dilewati.
 */
class BerkasBarisPermanenTest extends TestCase
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

    /** Tiga entri sertifikat, satu per baris. */
    private function tigaSertifikat(): array
    {
        return [
            ['bagian' => 'riwayat_sertifikasi', 'baris' => 0, 'field' => 'sert_file',
                'nama' => 'satu.pdf', 'path' => 'x/satu-abc.pdf', 'ukuran' => 1, 'mime' => 'application/pdf'],
            ['bagian' => 'riwayat_sertifikasi', 'baris' => 1, 'field' => 'sert_file',
                'nama' => 'dua.pdf', 'path' => 'x/dua-def.pdf', 'ukuran' => 2, 'mime' => 'application/pdf'],
            ['bagian' => 'riwayat_sertifikasi', 'baris' => 2, 'field' => 'sert_file',
                'nama' => 'tiga.pdf', 'path' => 'x/tiga-ghi.pdf', 'ukuran' => 3, 'mime' => 'application/pdf'],
        ];
    }

    private function jawabanTigaBaris(): array
    {
        return ['riwayat_sertifikasi' => [
            ['sert_nama' => 'A', 'sert_file' => 'satu.pdf'],
            ['sert_nama' => 'B', 'sert_file' => 'dua.pdf'],
            ['sert_nama' => 'C', 'sert_file' => 'tiga.pdf'],
        ]];
    }

    public function test_tiga_sertifikat_tersimpan_sebagai_tiga_baris(): void
    {
        $this->draf($this->tigaSertifikat(), $this->jawabanTigaBaris());

        $jumlah = FormulirDrafController::jadikanPermanen(1, 9, 77, $this->jawabanTigaBaris());

        $this->assertSame(3, $jumlah);

        $rows = DB::table('N_WEB_CAREERS_Formulir_Berkas')->orderBy('Baris_Index')->get();

        $this->assertCount(3, $rows);
        $this->assertSame([0, 1, 2], $rows->pluck('Baris_Index')->map(fn ($v) => (int) $v)->all());
        $this->assertSame(
            ['riwayat_sertifikasi', 'riwayat_sertifikasi', 'riwayat_sertifikasi'],
            $rows->pluck('Bagian_Key')->all(),
        );
        $this->assertSame(
            ['x/satu-abc.pdf', 'x/dua-def.pdf', 'x/tiga-ghi.pdf'],
            $rows->pluck('Path_File')->all(),
        );
    }

    public function test_berkas_biasa_tetap_null_di_kedua_kolom(): void
    {
        $this->draf([
            ['bagian' => null, 'baris' => null, 'field' => 'dok_cv',
                'nama' => 'cv.pdf', 'path' => 'a/cv.pdf', 'ukuran' => 10, 'mime' => 'application/pdf'],
        ]);

        FormulirDrafController::jadikanPermanen(1, 9, 77, []);

        $row = DB::table('N_WEB_CAREERS_Formulir_Berkas')->first();

        $this->assertNull($row->Bagian_Key);
        $this->assertNull($row->Baris_Index);
    }

    /** Kirim ulang untuk pengisian yang sama tidak boleh menggandakan. */
    public function test_pengesahan_ulang_tidak_menggandakan(): void
    {
        $this->draf($this->tigaSertifikat(), $this->jawabanTigaBaris());

        FormulirDrafController::jadikanPermanen(1, 9, 77, $this->jawabanTigaBaris());
        FormulirDrafController::jadikanPermanen(1, 9, 77, $this->jawabanTigaBaris());

        $this->assertSame(3, DB::table('N_WEB_CAREERS_Formulir_Berkas')->count());
    }

    /**
     * Kandidat mengunggah untuk baris ke-3 lalu menghapus barisnya sebelum
     * mengirim. Berkas itu tidak boleh ikut disahkan — laporan akan memuat
     * sertifikat yang barisnya tidak dimiliki siapa pun.
     */
    public function test_baris_yatim_tidak_disahkan(): void
    {
        $jawabanDuaBaris = ['riwayat_sertifikasi' => [
            ['sert_nama' => 'A', 'sert_file' => 'satu.pdf'],
            ['sert_nama' => 'B', 'sert_file' => 'dua.pdf'],
        ]];

        $this->draf($this->tigaSertifikat(), $jawabanDuaBaris);

        $jumlah = FormulirDrafController::jadikanPermanen(1, 9, 77, $jawabanDuaBaris);

        $this->assertSame(2, $jumlah);
        $this->assertSame(
            ['x/satu-abc.pdf', 'x/dua-def.pdf'],
            DB::table('N_WEB_CAREERS_Formulir_Berkas')->orderBy('Baris_Index')->pluck('Path_File')->all(),
        );
    }
}
