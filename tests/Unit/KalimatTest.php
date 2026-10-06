<?php

namespace Tests\Unit;

use App\Support\Career\Kalimat;
use App\Support\Career\Kamus;
use PHPUnit\Framework\TestCase;

/**
 * Penjelasan "Lainnya" saat kandidat tidak melanjutkan seleksi harus berupa
 * KALIMAT (keputusan user 2 Okt 2026) — batas panjang saja diakali dengan
 * "aaaaaaaaaaaaaaa" atau ketikan acak — dan kata-katanya harus dikenal kamus
 * (nspell: hunspell-id + en_US, lihat Kamus). Cermin aturan:
 * utils/career/kalimat.js.
 */
class KalimatTest extends TestCase
{
    /** Penjelasan wajar yang harus lolos — termasuk gaya tulis santai, bahasa Inggris, dan salah ketik ringan. */
    public static function kalimatWajar(): array
    {
        return [
            ['Saya diterima bekerja di perusahaan lain.'],
            ['Orang tua saya sakit dan harus dirawat di rumah.'],
            ['Pindah ke luar kota mengikuti suami.'],
            ['Melanjutkan studi S2 di Bandung tahun ini'],
            ['sy dpt tawaran kerja yg lebih dekat rumah'],
            ['Got another job offer closer to my family.'],
            ['Lokasi penempatan terlalu jauh dari keluarga.'],
            // Salah ketik ringan (2 dari 5 kata isi) tetap lolos — 60%.
            ['Saya dterima kerja di prusahaan lain'],
            // Singkatan yang dikenal tambahan.txt tidak dihitung "ketikan acak".
            ['sblm nya sy mksh, tp skrg sy hrs fokus kluarga'],
            ['Saya harus pindah ke luar kota karena urusan keluarga yang mendesak.'],
        ];
    }

    /** Akal-akalan yang harus ditolak. */
    public static function bukanKalimat(): array
    {
        return [
            'terlalu pendek' => ['saya pindah'],
            'huruf diulang' => ['aaaaaaaaaaaaaaaaaaa'],
            'huruf diulang (tangkapan layar user)' => ['ssssssssssssssssssssss'],
            'tanda diulang' => ['karena!!!!!!!!!!!!!!'],
            'satu kata panjang' => ['pengunduranpengunduran'],
            'kata diulang' => ['saya saya saya saya saya'],
            'satu kata mendominasi' => ['kerja kerja kerja kerja di rumah saja'],
            'baris papan ketik' => ['asdf asdf qwer zxcv'],
            'ketikan acak' => ['sdfkjh wqrtpl mnbvcz'],
            'konsonan beruntun' => ['bcdfgh jklmnp qrstvw'],
            'angka & simbol' => ['123 456 789 !!! ### 000'],
            'kata acak tanpa kata umum' => ['lorem ipsum dolor amet consectetur'],
            'kata karangan berbalut kata umum' => ['saya blarg flomp zindo karena'],
            'kata pendek + karangan' => ['di ke di ke blarg flomp'],
            'kata karangan berbunyi wajar' => ['saya mau kelorantis bermandika suparnoko'],
        ];
    }

    /** @dataProvider kalimatWajar */
    public function test_kalimat_wajar_lolos(string $teks): void
    {
        $this->assertTrue(Kamus::siap(), 'Daftar kata kamus belum dirakit — jalankan `npm run kamus`.');
        $this->assertNull(Kalimat::periksa($teks, 15), $teks);
    }

    /** @dataProvider bukanKalimat */
    public function test_akal_akalan_ditolak(string $teks): void
    {
        $this->assertNotNull(Kalimat::periksa($teks, 15), $teks);
    }

    public function test_kata_asing_disebutkan(): void
    {
        $galat = Kalimat::periksa('saya blarg flomp zindo karena', 15);

        $this->assertStringContainsString('"blarg"', $galat);
        $this->assertStringContainsString('"flomp"', $galat);
        $this->assertStringContainsString('"zindo"', $galat);
    }

    public function test_tanpa_kamus_tetap_menjaga_tanda_dasar(): void
    {
        // Daftar kata belum terpasang → langkah kamus dilewati, sisanya tetap.
        $this->assertNull(Kalimat::nilai('saya blarg flomp zindo karena', 15, null));
        $this->assertNotNull(Kalimat::nilai('asdf asdf qwer zxcv', 15, null));
    }

    public function test_spasi_dirapikan_sebelum_diukur(): void
    {
        // 15 spasi + 2 kata tidak boleh lolos sebagai "15 karakter".
        $this->assertNotNull(Kalimat::periksa('               saya ok', 15));
    }

    public function test_tag_html_dibuang(): void
    {
        $this->assertNotNull(Kalimat::periksa('<b></b><i></i><u></u><s></s>', 15));
    }

    /** Pencarian biner Kamus menemukan SETIAP baris daftar — awal, akhir, dan tiap 997 baris. */
    public function test_pencarian_biner_menemukan_isi_daftar(): void
    {
        foreach (['id-kata.txt', 'en-kata.txt'] as $berkas) {
            $baris = file(dirname(__DIR__, 2).'/resources/kamus/'.$berkas, FILE_IGNORE_NEW_LINES);
            $this->assertNotEmpty($baris, $berkas);

            $contoh = [reset($baris), end($baris)];
            for ($i = 0; $i < count($baris); $i += 997) {
                $contoh[] = $baris[$i];
            }
            foreach ($contoh as $kata) {
                $this->assertTrue(Kamus::kenal($kata), "{$berkas}: {$kata}");
                // Hampir sama tapi tidak ada: tambah huruf aneh di depan/belakang.
                $this->assertFalse(Kamus::kenal($kata.'qxj'), "{$berkas}: {$kata}qxj");
            }
        }
    }

    public function test_kata_berimbuhan_dikenal(): void
    {
        foreach (['membatalkan', 'dibatalkan', 'pembatalan', 'kepindahan', 'dipindahkan', 'mengundurkan', 'perusahaan', 'bandung', 'resign', 'offer'] as $kata) {
            $this->assertTrue(Kamus::kenal($kata), $kata);
        }
        foreach (['blarg', 'flomp', 'zindo', 'asdf', 'kelorantis'] as $kata) {
            $this->assertFalse(Kamus::kenal($kata), $kata);
        }
    }
}
