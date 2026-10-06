<?php

namespace Tests\Unit;

use App\Support\Career\BerkasBaris;
use PHPUnit\Framework\TestCase;

class BerkasBarisTest extends TestCase
{

    /**
     * Draf yang dibuat SEBELUM perubahan ini berbentuk peta berkunci field.
     * Satu draf aktif memakainya saat rilis, jadi bentuk lama wajib terbaca.
     */
    public function test_daftar_membaca_bentuk_peta_lama(): void
    {
        $json = json_encode([
            'dok_cv' => ['nama' => 'cv.pdf', 'path' => 'a/cv.pdf', 'ukuran' => 10, 'mime' => 'application/pdf'],
        ]);

        $this->assertSame([[
            'bagian' => null,
            'baris' => null,
            'field' => 'dok_cv',
            'nama' => 'cv.pdf',
            'path' => 'a/cv.pdf',
            'ukuran' => 10,
            'mime' => 'application/pdf',
        ]], BerkasBaris::daftar($json));
    }

    public function test_daftar_meneruskan_bentuk_daftar_baru(): void
    {
        $entri = [
            'bagian' => 'riwayat_sertifikasi',
            'baris' => 0,
            'field' => 'sert_file',
            'nama' => 's.pdf',
            'path' => 'a/s.pdf',
            'ukuran' => 20,
            'mime' => 'application/pdf',
        ];

        $this->assertSame([$entri], BerkasBaris::daftar(json_encode([$entri])));
    }

    public function test_daftar_kosong_untuk_json_kosong_atau_rusak(): void
    {
        $this->assertSame([], BerkasBaris::daftar(null));
        $this->assertSame([], BerkasBaris::daftar('{}'));
        $this->assertSame([], BerkasBaris::daftar('bukan json'));
    }

    public function test_kunci_bagian_memakai_key_bila_ada(): void
    {
        $this->assertSame(
            'riwayat_sertifikasi',
            BerkasBaris::kunciBagian(['key' => 'riwayat_sertifikasi', 'judul' => 'Riwayat Sertifikasi']),
        );
    }

    /**
     * Editor tidak pernah membuat kunci bagian otomatis (schema.js menyimpan
     * `key: B.key || ''`), jadi bentuk ini SAH dan browser menurunkan kuncinya
     * dari judul. Server wajib sampai ke kunci yang sama.
     */
    public function test_kunci_bagian_diturunkan_dari_judul_bila_key_kosong(): void
    {
        $this->assertSame(
            'riwayat_sertifikasi_pelatihan',
            BerkasBaris::kunciBagian(['key' => '', 'judul' => 'Riwayat Sertifikasi / Pelatihan']),
        );
        $this->assertSame(
            'daftar_kenalan_di_evo_group',
            BerkasBaris::kunciBagian(['judul' => 'Daftar Kenalan di EVO Group']),
        );
    }

    public function test_cocok_membandingkan_triplet_utuh(): void
    {
        $entri = ['bagian' => 'riwayat_sertifikasi', 'baris' => 1, 'field' => 'sert_file'];

        $this->assertTrue(BerkasBaris::cocok($entri, 'riwayat_sertifikasi', 1, 'sert_file'));
        $this->assertFalse(BerkasBaris::cocok($entri, 'riwayat_sertifikasi', 0, 'sert_file'));
        $this->assertFalse(BerkasBaris::cocok($entri, 'riwayat_organisasi', 1, 'sert_file'));
        $this->assertFalse(BerkasBaris::cocok($entri, 'riwayat_sertifikasi', 1, 'sert_nama'));
    }

    /**
     * INTI dari seluruh perbaikan ini. Baris tengah dihapus: entri di bawahnya
     * tak bergerak, entri di atasnya turun satu, entri bagian LAIN tidak boleh
     * ikut tergeser sama sekali.
     */
    public function test_geser_menurunkan_indeks_di_atas_baris_yang_dihapus(): void
    {
        $daftar = [
            ['bagian' => 'sertifikasi', 'baris' => 0, 'field' => 'f', 'path' => 'p0'],
            ['bagian' => 'sertifikasi', 'baris' => 1, 'field' => 'f', 'path' => 'p1'],
            ['bagian' => 'sertifikasi', 'baris' => 2, 'field' => 'f', 'path' => 'p2'],
            ['bagian' => 'organisasi', 'baris' => 2, 'field' => 'g', 'path' => 'q2'],
            ['bagian' => null, 'baris' => null, 'field' => 'dok_cv', 'path' => 'cv'],
        ];

        $hasil = BerkasBaris::geser($daftar, 'sertifikasi', 1);

        $this->assertSame(
            [['sertifikasi', 0, 'p0'], ['sertifikasi', 1, 'p2'], ['organisasi', 2, 'q2'], [null, null, 'cv']],
            array_map(fn ($e) => [$e['bagian'], $e['baris'], $e['path']], $hasil),
        );
    }
}
