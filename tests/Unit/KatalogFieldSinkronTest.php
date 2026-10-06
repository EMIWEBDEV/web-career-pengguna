<?php

namespace Tests\Unit;

use App\Support\Career\KatalogField;
use PHPUnit\Framework\TestCase;

/**
 * Uji SINKRONISASI katalog PHP dengan katalog JS.
 *
 * Dua salinan aturan yang sama adalah utang: cepat atau lambat keduanya
 * berbeda, dan yang lebih longgar jadi lubang. Uji ini membaca katalogField.js
 * langsung dari berkasnya dan membandingkan daftar tipe serta daftar properti
 * per tipe. Kalau salah satu diubah tanpa yang lain, uji ini merah.
 */
class KatalogFieldSinkronTest extends TestCase
{
    private const JS = __DIR__ . '/../../resources/js/components/career/formulir/inti/katalogField.js';

    public function test_daftar_tipe_sama_dengan_katalog_js(): void
    {
        $this->assertSame($this->tipeJs(), array_values(KatalogField::tipeValid()));
    }

    public function test_properti_tiap_tipe_sama_dengan_katalog_js(): void
    {
        foreach ($this->propertiJs() as $tipe => $properti) {
            $this->assertSame(
                $properti,
                KatalogField::properti($tipe),
                "Properti tipe \"{$tipe}\" berbeda antara katalogField.js dan KatalogField.php",
            );
        }
    }

    public function test_properti_universal_sama_dengan_katalog_js(): void
    {
        $isi = file_get_contents(self::JS);
        preg_match('/export const PROPERTI_UNIVERSAL = \[(.*?)\];/s', $isi, $m);
        $this->assertNotEmpty($m, 'PROPERTI_UNIVERSAL tidak ditemukan di katalogField.js');

        preg_match_all("/'([a-z_]+)'/", $m[1], $nama);
        $this->assertSame($nama[1], KatalogField::UNIVERSAL);
    }

    /** Nama tipe, urut sesuai kemunculannya di katalogField.js. */
    private function tipeJs(): array
    {
        return array_keys($this->propertiJs());
    }

    /**
     * Membaca blok KATALOG_FIELD dari katalogField.js.
     *
     * Sengaja regex, bukan parser JS: yang perlu dibandingkan hanya nama tipe
     * dan nama properti, dan menambah ketergantungan Node ke suite PHP demi itu
     * jauh lebih mahal daripada pola yang harus ikut berubah kalau format
     * katalognya diubah drastis.
     */
    private function propertiJs(): array
    {
        $isi = file_get_contents(self::JS);
        $blok = substr($isi, strpos($isi, 'export const KATALOG_FIELD = {'));

        preg_match_all(
            '/^    ([a-z]+): \{.*?^        properti: \[(.*?)\],/ms',
            $blok,
            $cocok,
            PREG_SET_ORDER,
        );

        $out = [];
        foreach ($cocok as $c) {
            preg_match_all("/'([a-z_]+)'/", $c[2], $nama);
            $out[$c[1]] = $nama[1];
        }

        return $out;
    }
}
