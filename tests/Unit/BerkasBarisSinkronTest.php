<?php

namespace Tests\Unit;

use App\Support\Career\BerkasBaris;
use PHPUnit\Framework\TestCase;

/**
 * Uji SINKRONISASI format kunci PHP dengan cerminan JS-nya.
 *
 * Polanya sama dengan KatalogFieldSinkronTest: membaca berkas JS langsung dan
 * membandingkan konstantanya. Kalau format kuncinya diubah di satu sisi saja,
 * browser dan server akan membentuk kunci berbeda untuk berkas yang sama — dan
 * gejalanya (berkas "hilang" tanpa galat) jauh lebih mahal dilacak daripada
 * uji ini.
 */
class BerkasBarisSinkronTest extends TestCase
{
    private const JS = __DIR__ . '/../../resources/js/utils/formulir/berkasBaris.js';

    public function test_format_kunci_sama_dengan_js(): void
    {
        $this->assertSame(BerkasBaris::FORMAT_KUNCI, $this->konstantaJs('FORMAT_KUNCI'));
    }

    public function test_format_label_sama_dengan_js(): void
    {
        $this->assertSame(BerkasBaris::FORMAT_LABEL, $this->konstantaJs('FORMAT_LABEL'));
    }

    /**
     * kunciBagian() PHP harus mengikuti kunciBagian() di aturan.js.
     *
     * Diperiksa dari SUMBERNYA, bukan dari ingatan: kalau pola slug di JS
     * diubah tanpa yang di PHP, browser dan server akan memakai kunci berbeda
     * untuk bagian yang sama — dan gerbang periksaBaris() akan menolak unggahan
     * yang sah, mematikan formulir itu sepenuhnya.
     */
    public function test_pola_slug_kunci_bagian_sama_dengan_aturan_js(): void
    {
        $isi = file_get_contents(__DIR__ . '/../../resources/js/utils/formulir/aturan.js');

        preg_match('/export function kunciBagian\(B\) \{(.*?)\n\}/s', $isi, $m);
        $this->assertNotEmpty($m, 'kunciBagian tidak ditemukan di aturan.js');

        $badan = $m[1];
        $this->assertStringContainsString('B.key ||', $badan, 'aturan.js tidak lagi mengutamakan B.key');
        $this->assertStringContainsString('.toLowerCase()', $badan);
        $this->assertStringContainsString("replace(/[^a-z0-9]+/g, '_')", $badan);
        $this->assertStringContainsString("replace(/^_+|_+$/g, '')", $badan);
    }

    private function konstantaJs(string $nama): string
    {
        $isi = file_get_contents(self::JS);
        preg_match('/export const ' . $nama . " = '(.*?)';/", $isi, $m);
        $this->assertNotEmpty($m, "{$nama} tidak ditemukan di berkasBaris.js");

        return $m[1];
    }
}
