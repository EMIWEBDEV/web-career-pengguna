<?php

namespace Tests\Feature\Controllers;

use App\Http\Controllers\Career\Lamaran\FormulirDrafController;
use Tests\TestCase;

/**
 * maks_baris selama ini HANYA hidup di browser (BagianRenderer.vue:48).
 * unggahBerkas() menerima `field` sebagai string bebas tanpa pernah
 * mencocokkannya ke skema — dan begitu tiap baris bisa membawa berkas 5 MB,
 * endpoint itu bisa dibanjiri tanpa batas.
 */
class BerkasDrafValidasiTest extends TestCase
{
    private function skema(): array
    {
        return ['langkah' => [[
            'judul' => 'Pengalaman',
            'bagian' => [
                [
                    'key' => 'riwayat_sertifikasi',
                    'judul' => 'Riwayat Sertifikasi',
                    'berulang' => true,
                    'maks_baris' => 3,
                    'field' => [
                        ['key' => 'sert_nama', 'tipe' => 'text'],
                        ['key' => 'sert_file', 'tipe' => 'file'],
                    ],
                ],
                [
                    'key' => 'identitas',
                    'judul' => 'Identitas',
                    'berulang' => false,
                    'field' => [['key' => 'dok_cv', 'tipe' => 'file']],
                ],
            ],
        ]]];
    }

    public function test_baris_dalam_batas_diterima(): void
    {
        $this->assertNull(
            FormulirDrafController::periksaBaris($this->skema(), 'riwayat_sertifikasi', 2, 'sert_file'),
        );
    }

    public function test_baris_melebihi_maks_ditolak_dengan_judul_bagiannya(): void
    {
        $galat = FormulirDrafController::periksaBaris($this->skema(), 'riwayat_sertifikasi', 3, 'sert_file');

        $this->assertSame('Riwayat Sertifikasi hanya menerima 3 baris.', $galat);
    }

    public function test_baris_negatif_ditolak(): void
    {
        $this->assertNotNull(
            FormulirDrafController::periksaBaris($this->skema(), 'riwayat_sertifikasi', -1, 'sert_file'),
        );
    }

    public function test_bagian_yang_tidak_berulang_ditolak(): void
    {
        $this->assertSame(
            'Bagian "identitas" tidak menerima baris berulang.',
            FormulirDrafController::periksaBaris($this->skema(), 'identitas', 0, 'dok_cv'),
        );
    }

    public function test_bagian_yang_tidak_ada_ditolak(): void
    {
        $this->assertSame(
            'Bagian "hantu" tidak ada di formulir ini.',
            FormulirDrafController::periksaBaris($this->skema(), 'hantu', 0, 'sert_file'),
        );
    }

    public function test_field_yang_tidak_ada_di_bagian_itu_ditolak(): void
    {
        $this->assertSame(
            'Field "org_file" tidak ada di bagian "riwayat_sertifikasi".',
            FormulirDrafController::periksaBaris($this->skema(), 'riwayat_sertifikasi', 0, 'org_file'),
        );
    }

    public function test_field_yang_bukan_berkas_ditolak(): void
    {
        $this->assertSame(
            'Field "sert_nama" bukan field berkas.',
            FormulirDrafController::periksaBaris($this->skema(), 'riwayat_sertifikasi', 0, 'sert_nama'),
        );
    }

    /** Tanpa bagian/baris = berkas biasa. Tidak ada yang perlu diperiksa. */
    public function test_berkas_biasa_selalu_lolos(): void
    {
        $this->assertNull(FormulirDrafController::periksaBaris($this->skema(), null, null, 'dok_cv'));
    }

    /** Skema tak terbaca tidak boleh MENOLAK — hanya melewatkan pemeriksaan. */
    public function test_tanpa_skema_tidak_menolak(): void
    {
        $this->assertNull(FormulirDrafController::periksaBaris(null, 'riwayat_sertifikasi', 99, 'sert_file'));
    }

    /**
     * Bagian TANPA `key`.
     *
     * schema.js:48 menyimpan `key: B.key || ''` — editor tidak pernah membuat
     * kunci bagian otomatis, jadi bagian berulang tanpa key adalah bentuk yang
     * sah dan bisa tersimpan. Browser menurunkan kuncinya dari judul
     * (aturan.js:114). Kalau pemeriksaan di sini hanya mencocokkan `key`,
     * gerbang ini akan MENOLAK unggahan yang benar-benar sah dan mematikan
     * formulir itu sepenuhnya.
     */
    private function skemaTanpaKey(): array
    {
        return ['langkah' => [[
            'bagian' => [[
                'judul' => 'Riwayat Sertifikasi / Pelatihan',
                'berulang' => true,
                'maks_baris' => 3,
                'field' => [['key' => 'sert_file', 'tipe' => 'file']],
            ]],
        ]]];
    }

    public function test_bagian_tanpa_key_dikenali_dari_judulnya(): void
    {
        $this->assertNull(
            FormulirDrafController::periksaBaris(
                $this->skemaTanpaKey(),
                'riwayat_sertifikasi_pelatihan',
                1,
                'sert_file',
            ),
        );
    }

    public function test_bagian_tanpa_key_tetap_menegakkan_maks_baris(): void
    {
        $this->assertSame(
            'Riwayat Sertifikasi / Pelatihan hanya menerima 3 baris.',
            FormulirDrafController::periksaBaris(
                $this->skemaTanpaKey(),
                'riwayat_sertifikasi_pelatihan',
                3,
                'sert_file',
            ),
        );
    }
}
