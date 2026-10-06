<?php

namespace Tests\Unit;

use App\Support\Career\BerkasBaris;
use PHPUnit\Framework\TestCase;

/**
 * Tabel dokumen di PDF tidak boleh berbunyi "Sertifikat" tiga kali beruntun.
 *
 * Penomorannya dibentuk BerkasBaris::label(), bukan dirangkai ulang di tiap
 * konsumen — empat konsumen yang masing-masing menulis "#" . ($i + 1) adalah
 * cara yang sama persis dengan bagaimana bug aslinya lahir.
 */
class LaporanBerkasBernomorTest extends TestCase
{
    public function test_tiga_sertifikat_bernomor_berurutan(): void
    {
        $baris = [0, 1, 2];

        $this->assertSame(
            ['Sertifikat #1', 'Sertifikat #2', 'Sertifikat #3'],
            array_map(fn ($i) => BerkasBaris::label('Sertifikat', $i), $baris),
        );
    }

    public function test_dokumen_biasa_tidak_bernomor(): void
    {
        $this->assertSame('Curriculum Vitae', BerkasBaris::label('Curriculum Vitae', null));
    }
}
