<?php

namespace Tests\Unit;

use App\Http\Controllers\Career\MasterFormulir\MasterFormulirController;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Uji PENEGAKAN aturan skema di sisi server.
 *
 * Editor sudah memvalidasi sebelum mengirim, tapi endpoint simpan/publish bisa
 * dipanggil tanpa lewat editor sama sekali. Aturan yang sama harus berlaku di
 * kedua jalur — kalau tidak, validasi browser cuma saran.
 */
class MasterFormulirValidasiSchemaTest extends TestCase
{
    /**
     * Controller-nya diinstansiasi langsung, bukan lewat container: validasiSchema
     * dan seluruh helper yang dipakainya murni — tidak menyentuh DB, sesi, maupun
     * request. Uji ini karenanya tidak perlu membangunkan Laravel.
     */
    private function validasi(array $schema, string $konteks = 'KEDUANYA'): array
    {
        $m = new ReflectionMethod(MasterFormulirController::class, 'validasiSchema');
        $m->setAccessible(true);

        return $m->invoke(new MasterFormulirController(), ['konteks' => $konteks] + $schema);
    }

    private function skema(array $field): array
    {
        return ['langkah' => [['judul' => 'L', 'bagian' => [['judul' => 'B', 'field' => $field]]]]];
    }

    public function test_menerima_skema_paling_sederhana(): void
    {
        $h = $this->validasi($this->skema([['key' => 'nama', 'label' => 'Nama', 'tipe' => 'text']]));

        $this->assertTrue($h['ok'], $h['pesan'] ?? '');
    }

    public function test_menolak_referensi_tanpa_sumber(): void
    {
        $h = $this->validasi($this->skema([['key' => 'kampus', 'label' => 'Kampus', 'tipe' => 'referensi']]));

        $this->assertFalse($h['ok']);
        $this->assertStringContainsString('Sumber master data', $h['pesan']);
    }

    public function test_menolak_min_lebih_besar_dari_maks(): void
    {
        $h = $this->validasi($this->skema([
            ['key' => 'ipk', 'label' => 'IPK', 'tipe' => 'number', 'min' => 4, 'maks' => 0],
        ]));

        $this->assertFalse($h['ok']);
        $this->assertStringContainsString('minimum', $h['pesan']);
    }

    public function test_menolak_pilihan_dengan_satu_opsi(): void
    {
        $h = $this->validasi($this->skema([
            ['key' => 'setuju', 'label' => 'Setuju', 'tipe' => 'select', 'opsi' => ['Ya']],
        ]));

        $this->assertFalse($h['ok']);
        $this->assertStringContainsString('2 opsi', $h['pesan']);
    }

    public function test_menolak_rujukan_ke_key_yang_tidak_ada(): void
    {
        $h = $this->validasi($this->skema([
            ['key' => 'hp_darurat', 'label' => 'HP Darurat', 'tipe' => 'phone', 'beda_dengan' => 'hp_saya'],
        ]));

        $this->assertFalse($h['ok']);
        $this->assertStringContainsString('hp_saya', $h['pesan']);
    }

    public function test_menolak_tampil_jika_yang_mengacu_ke_depan(): void
    {
        $h = $this->validasi($this->skema([
            ['key' => 'a', 'label' => 'A', 'tipe' => 'text', 'tampil_jika' => ['field' => 'b', 'operator' => '=', 'nilai' => 'ya']],
            ['key' => 'b', 'label' => 'B', 'tipe' => 'text'],
        ]));

        $this->assertFalse($h['ok']);
        $this->assertStringContainsString('sesudah', $h['pesan']);
    }

    public function test_menolak_prefill_di_luar_konteks_formulir(): void
    {
        $h = $this->validasi(
            $this->skema([['key' => 'ipk', 'label' => 'IPK', 'tipe' => 'text', 'prefill' => 'ipk']]),
            'PENDAFTARAN',
        );

        $this->assertFalse($h['ok']);
        $this->assertStringContainsString('ipk', $h['pesan']);
    }

    public function test_menerima_prefill_yang_tersedia_di_konteksnya(): void
    {
        $h = $this->validasi(
            $this->skema([['key' => 'nik', 'label' => 'NIK', 'tipe' => 'text', 'prefill' => 'nik']]),
            'PENDAFTARAN',
        );

        $this->assertTrue($h['ok'], $h['pesan'] ?? '');
    }

    public function test_membuang_properti_yang_bukan_milik_tipenya(): void
    {
        $h = $this->validasi($this->skema([
            ['key' => 'kota', 'label' => 'Kota', 'tipe' => 'text', 'opsi' => ['A', 'B'], 'maks_mb' => 5],
        ]));

        $this->assertTrue($h['ok'], $h['pesan'] ?? '');
        $tersimpan = $h['schema']['langkah'][0]['bagian'][0]['field'][0];
        $this->assertArrayNotHasKey('opsi', $tersimpan);
        $this->assertArrayNotHasKey('maks_mb', $tersimpan);
    }

    public function test_konteks_ikut_tersimpan_di_schema(): void
    {
        $h = $this->validasi($this->skema([['key' => 'nama', 'label' => 'Nama', 'tipe' => 'text']]), 'PENDAFTARAN');

        $this->assertSame('PENDAFTARAN', $h['schema']['konteks']);
    }
}
