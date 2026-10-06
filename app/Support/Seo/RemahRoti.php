<?php

namespace App\Support\Seo;

/**
 * WEB CAREERS — schema.org/BreadcrumbList.
 *
 * Yang mengubah baris hijau di bawah judul hasil pencarian dari URL mentah
 *
 *     career.evopet.id › karir › landing-page › lowongan › 4kL9mZ
 *
 * menjadi jalur yang terbaca manusia
 *
 *     Karier EVO Group › Lowongan › Staff IT Support
 *
 * Ini bukan hiasan. Hashid di URL tidak berarti apa-apa bagi pencari kerja
 * yang sedang memilih satu dari sepuluh hasil, dan tampilan URL mentah
 * terbaca seperti halaman yang tidak terurus.
 */
class RemahRoti
{
    /**
     * @param  array<int, array{0: string, 1: ?string}>  $jalur  [judul, url]
     */
    public static function dari(array $jalur): ?array
    {
        $butir = [];
        $n = 0;

        foreach ($jalur as [$judul, $url]) {
            $judul = trim((string) $judul);
            if ($judul === '') {
                continue;
            }

            // Butir TERAKHIR sengaja tanpa 'item'. Halaman yang sedang dibuka
            // tidak menautkan ke dirinya sendiri — dan Google menandai
            // breadcrumb yang melakukannya sebagai rantai yang salah.
            $b = ['@type' => 'ListItem', 'position' => ++$n, 'name' => $judul];
            if ($url) {
                $b['item'] = $url;
            }
            $butir[] = $b;
        }

        // Satu butir bukan jalur — mencetaknya cuma menambah simpul tanpa
        // mengubah apa pun yang terlihat.
        return count($butir) < 2 ? null : [
            '@type' => 'BreadcrumbList',
            'itemListElement' => $butir,
        ];
    }
}
