<?php

namespace App\Support\Career;

/**
 * WEB CAREER — IDENTITAS BERKAS DI DALAM BAGIAN BERULANG.
 *
 * Sebuah berkas formulir dikenali dari triplet (bagian, baris, field). Sebelum
 * berkas ini ada, ia hanya dikenali dari `field` — dan karena setiap baris
 * berulang mengirim `field` yang sama persis, berkas baris kedua menimpa yang
 * pertama di empat lapis sekaligus: memori tab, path GCS, Berkas_Json draf, dan
 * baris Formulir_Berkas. Tiga sertifikat masuk, satu keluar.
 *
 * Pengetahuan itu dipusatkan DI SINI, bukan disalin ke tiap pemanggil. Empat
 * salinan aturan yang sama persis adalah cara bug tadi lahir.
 *
 * Cerminan JS-nya: resources/js/utils/formulir/berkasBaris.js,
 * dijaga tetap sama oleh tests/Unit/BerkasBarisSinkronTest.php.
 */
class BerkasBaris
{
    /** Susunan kunci komposit. HARUS sama dengan FORMAT_KUNCI di berkasBaris.js. */
    public const FORMAT_KUNCI = '{bagian}[{baris}].{field}';

    /** Susunan label bernomor. HARUS sama dengan FORMAT_LABEL di berkasBaris.js. */
    public const FORMAT_LABEL = '{label} #{nomor}';

    /**
     * Kunci komposit sebuah berkas.
     *
     * Bagian TANPA baris (atau sebaliknya) sengaja jatuh ke `field` saja: itu
     * bukan identitas berulang yang sah, dan membentuk kunci setengah jadi hanya
     * memindahkan kesalahannya ke tempat yang lebih sulit dilacak.
     */
    public static function kunci(?string $bagian, ?int $baris, string $field): string
    {
        if ($bagian === null || $bagian === '' || $baris === null) {
            return $field;
        }

        return strtr(self::FORMAT_KUNCI, [
            '{bagian}' => $bagian,
            '{baris}' => (string) $baris,
            '{field}' => $field,
        ]);
    }

    /** Label bernomor untuk tampilan. Nomornya berbasis 1 — yang dibaca manusia. */
    public static function label(string $label, ?int $baris): string
    {
        if ($baris === null) {
            return $label;
        }

        return strtr(self::FORMAT_LABEL, [
            '{label}' => $label,
            '{nomor}' => (string) ($baris + 1),
        ]);
    }

    /**
     * Kunci sebuah BAGIAN, persis seperti yang dihitung browser.
     *
     * Cerminan dari kunciBagian() di utils/formulir/aturan.js. Editor tidak pernah
     * membuat kunci bagian otomatis — schema.js menyimpan `key: B.key || ''` —
     * jadi bagian berulang tanpa `key` adalah bentuk yang SAH dan benar-benar
     * bisa tersimpan. Untuk bagian semacam itu browser menurunkan kuncinya dari
     * judul, dan server WAJIB sampai pada kunci yang sama; kalau tidak, gerbang
     * pemeriksaan akan menolak unggahan yang sah dan mematikan formulir itu
     * sepenuhnya.
     *
     * Judulnya dinormalkan dulu seperti normalisasiBagian() di schema.js: judul
     * yang TIDAK diisi menjadi "Bagian N" (N = urutan bagian di langkahnya,
     * dikirim lewat $indeks), sedangkan judul yang sengaja dikosongkan jatuh ke
     * 'bagian'. Tanpa $indeks perilakunya sama seperti sebelumnya.
     */
    public static function kunciBagian(array $bagian, ?int $indeks = null): string
    {
        $key = (string) ($bagian['key'] ?? '');
        if ($key !== '') {
            return $key;
        }

        $judul = $bagian['judul'] ?? null;
        $judul = $judul === null
            ? ($indeks !== null ? 'Bagian ' . ($indeks + 1) : 'bagian')
            : trim((string) $judul);

        return trim(preg_replace('/[^a-z0-9]+/', '_', mb_strtolower($judul !== '' ? $judul : 'bagian')) ?: '', '_');
    }

    /** Apakah satu entri daftar berkas adalah triplet yang dicari. */
    public static function cocok(array $entri, ?string $bagian, ?int $baris, string $field): bool
    {
        return ($entri['bagian'] ?? null) === $bagian
            && ($entri['baris'] ?? null) === $baris
            && ($entri['field'] ?? null) === $field;
    }

    /**
     * Normalkan isi kolom Berkas_Json menjadi DAFTAR entri.
     *
     * Menerima dua bentuk: daftar baru, dan peta berkunci field yang dipakai draf
     * lama. Bentuk lama tidak dimigrasikan lewat skrip karena ia hidup di kolom
     * JSON milik draf yang berumur pendek — menerjemahkannya saat dibaca jauh
     * lebih murah daripada satu skrip yang menyentuh baris yang besok sudah
     * terhapus sendiri.
     */
    public static function daftar(?string $json): array
    {
        $isi = json_decode($json ?: '[]', true);
        if (! is_array($isi) || ! $isi) {
            return [];
        }

        if (array_is_list($isi)) {
            return array_values(array_filter($isi, 'is_array'));
        }

        $out = [];
        foreach ($isi as $field => $meta) {
            if (! is_array($meta)) {
                continue;
            }

            $out[] = [
                'bagian' => null,
                'baris' => null,
                'field' => (string) $field,
                'nama' => $meta['nama'] ?? null,
                'path' => $meta['path'] ?? null,
                'ukuran' => $meta['ukuran'] ?? null,
                'mime' => $meta['mime'] ?? null,
            ];
        }

        return $out;
    }

    /**
     * Buang entri milik satu baris, lalu TURUNKAN indeks entri di atasnya.
     *
     * Dipanggil saat kandidat menghapus sebuah baris. Tanpa penurunan indeks,
     * berkas baris ke-3 tetap mengaku baris ke-3 padahal barisnya kini ke-2, dan
     * sertifikat menempel ke baris yang salah. Bagian lain tidak tersentuh.
     */
    public static function geser(array $daftar, string $bagian, int $baris): array
    {
        $out = [];

        foreach ($daftar as $e) {
            if (($e['bagian'] ?? null) !== $bagian) {
                $out[] = $e;
                continue;
            }

            $i = $e['baris'] ?? null;
            if ($i === $baris) {
                continue;
            }

            if (is_int($i) && $i > $baris) {
                $e['baris'] = $i - 1;
            }

            $out[] = $e;
        }

        return $out;
    }
}
