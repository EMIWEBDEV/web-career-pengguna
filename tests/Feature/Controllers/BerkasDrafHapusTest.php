<?php

namespace Tests\Feature\Controllers;

use App\Support\Career\BerkasBaris;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Menghapus baris harus MENGGESER indeks berkas di atasnya.
 *
 * Ini bagian paling mudah salah dari seluruh perubahan: tanpa penggeseran,
 * berkas baris ke-3 tetap mengaku baris ke-3 padahal barisnya kini ke-2, dan
 * sertifikat menempel ke baris yang salah. Diuji langsung di tingkat helper
 * karena di situlah aturannya tinggal — endpoint hanya membungkusnya.
 */
class BerkasDrafHapusTest extends TestCase
{
    private function daftar(): array
    {
        return [
            ['bagian' => 'riwayat_sertifikasi', 'baris' => 0, 'field' => 'sert_file', 'path' => 'p0'],
            ['bagian' => 'riwayat_sertifikasi', 'baris' => 1, 'field' => 'sert_file', 'path' => 'p1'],
            ['bagian' => 'riwayat_sertifikasi', 'baris' => 2, 'field' => 'sert_file', 'path' => 'p2'],
            ['bagian' => null, 'baris' => null, 'field' => 'dok_cv', 'path' => 'cv'],
        ];
    }

    public function test_hapus_baris_tengah_menurunkan_indeks_di_atasnya(): void
    {
        $hasil = BerkasBaris::geser($this->daftar(), 'riwayat_sertifikasi', 1);

        $this->assertSame(
            [['riwayat_sertifikasi', 0, 'p0'], ['riwayat_sertifikasi', 1, 'p2'], [null, null, 'cv']],
            array_map(fn ($e) => [$e['bagian'], $e['baris'], $e['path']], $hasil),
        );
    }

    public function test_hapus_baris_terakhir_tidak_menggeser_apa_pun(): void
    {
        $hasil = BerkasBaris::geser($this->daftar(), 'riwayat_sertifikasi', 2);

        $this->assertSame(
            [['riwayat_sertifikasi', 0, 'p0'], ['riwayat_sertifikasi', 1, 'p1'], [null, null, 'cv']],
            array_map(fn ($e) => [$e['bagian'], $e['baris'], $e['path']], $hasil),
        );
    }

    public function test_berkas_biasa_tidak_pernah_tersentuh(): void
    {
        foreach ([0, 1, 2] as $i) {
            $hasil = BerkasBaris::geser($this->daftar(), 'riwayat_sertifikasi', $i);

            $this->assertContains('cv', array_column($hasil, 'path'));
        }
    }

    public function test_rute_hapus_berkas_draf_terdaftar(): void
    {
        $this->assertTrue(Route::has('career.portal.draf.berkas.hapus'));
    }
}
