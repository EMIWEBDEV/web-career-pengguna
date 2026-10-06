<?php

namespace Tests\Unit;

use App\Support\Career\GcsBerkas;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Dua unggahan untuk FIELD YANG SAMA tidak boleh mendarat di path yang sama.
 *
 * Path deterministik membuat sertifikat kedua menimpa yang pertama — dan dua
 * kandidat bernama sama di hari yang sama saling menimpa CV. Karena itu
 * berkas kandidat SELALU lewat unggahUnik() ke bucket karantina.
 */
class GcsBerkasBarisTest extends TestCase
{
    public function test_dua_unggahan_field_sama_menghasilkan_path_berbeda(): void
    {
        Storage::fake(GcsBerkas::DISK);
        $gcs = app(GcsBerkas::class);

        $a = $gcs->unggahUnik('formulir-tahap/2026/08/12/budi', 'sert_file', 'pdf', 'isi-a');
        $b = $gcs->unggahUnik('formulir-tahap/2026/08/12/budi', 'sert_file', 'pdf', 'isi-b');

        $this->assertNotSame($a, $b);
        $this->assertSame('isi-a', Storage::disk(GcsBerkas::DISK)->get($a));
        $this->assertSame('isi-b', Storage::disk(GcsBerkas::DISK)->get($b));
    }

    public function test_path_tetap_berada_di_folder_dan_ekstensi_yang_benar(): void
    {
        Storage::fake(GcsBerkas::DISK);

        $p = app(GcsBerkas::class)->unggahUnik('formulir-tahap/2026/08/12/budi', 'sert_file', 'JPEG', 'isi');

        $this->assertStringStartsWith('formulir-tahap/2026/08/12/budi/sert-file/sert-file-', $p);
        $this->assertStringEndsWith('.jpg', $p);
    }

    public function test_berkas_kandidat_masuk_bucket_karantina(): void
    {
        $this->assertSame('karantina', GcsBerkas::DISK);
    }
}
