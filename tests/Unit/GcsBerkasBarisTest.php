<?php

namespace Tests\Unit;

use App\Support\Career\GcsBerkas;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Dua unggahan untuk FIELD YANG SAMA tidak boleh mendarat di path yang sama.
 *
 * unggah() sengaja deterministik dan tetap begitu untuk berkas biasa. Untuk
 * baris berulang, determinisme itulah yang membuat sertifikat kedua menimpa
 * yang pertama di bucket — jebakan yang komentarnya sudah tertulis di
 * unggahGambarCatatan() tapi tidak pernah diterapkan ke jalur ini.
 */
class GcsBerkasBarisTest extends TestCase
{
    public function test_dua_unggahan_field_sama_menghasilkan_path_berbeda(): void
    {
        Storage::fake(GcsBerkas::DISK);
        $gcs = app(GcsBerkas::class);

        $a = $gcs->unggahBaris('formulir-tahap/2026/08/12/budi', 'sert_file', 'pdf', 'isi-a');
        $b = $gcs->unggahBaris('formulir-tahap/2026/08/12/budi', 'sert_file', 'pdf', 'isi-b');

        $this->assertNotSame($a, $b);
        $this->assertSame('isi-a', Storage::disk(GcsBerkas::DISK)->get($a));
        $this->assertSame('isi-b', Storage::disk(GcsBerkas::DISK)->get($b));
    }

    public function test_path_tetap_berada_di_folder_dan_ekstensi_yang_benar(): void
    {
        Storage::fake(GcsBerkas::DISK);
        $gcs = app(GcsBerkas::class);

        $p = $gcs->unggahBaris('formulir-tahap/2026/08/12/budi', 'sert_file', 'PDF', 'isi');

        $this->assertStringStartsWith('formulir-tahap/2026/08/12/budi/sert-file/', $p);
        $this->assertStringEndsWith('.pdf', $p);
    }

    /** unggah() yang lama TIDAK boleh berubah — berkas biasa bergantung padanya. */
    public function test_unggah_biasa_tetap_deterministik(): void
    {
        Storage::fake(GcsBerkas::DISK);
        $gcs = app(GcsBerkas::class);

        $a = $gcs->unggah('formulir-tahap/2026/08/12/budi', 'dok_cv', 'pdf', 'isi-a');
        $b = $gcs->unggah('formulir-tahap/2026/08/12/budi', 'dok_cv', 'pdf', 'isi-b');

        // slug() memangkas awalan dok_/file_/upload_ (GcsBerkas.php:191) — itu
        // sebabnya berkas dok_cv mendarat di folder "cv", bukan "dok-cv".
        $this->assertSame($a, $b);
        $this->assertSame('formulir-tahap/2026/08/12/budi/cv/cv.pdf', $a);
    }
}
