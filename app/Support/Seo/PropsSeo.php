<?php

namespace App\Support\Seo;

/**
 * Menyambungkan judul halaman ber-shell (portal kandidat, profil) ke meta halaman.
 *
 * Setiap controller ber-shell sudah menyebutkan judul halamannya saat merakit
 * shell — `CareerShell::props('/kandidat/portal', 'Lamaran Saya')`. Judul itu
 * dipungut di sini dan dijadikan judul <title> yang DICETAK SERVER, sehingga
 * cocok persis dengan judul yang nanti dipasang komponen <Head> Vue.
 *
 * Tanpa ini, halaman ber-shell memuat dengan judul default lebih dulu
 * ("Karier Bersama EVO Group | Careers Evo Group") lalu berkedip berganti ke
 * judul aslinya begitu Vue hidup.
 *
 * Terdaftar lewat config('career_shell.props_tambahan') — titik ekstensi resmi
 * PropsShell.
 */
class PropsSeo
{
    /**
     * Dipanggil PropsShell untuk setiap halaman ber-shell.
     *
     * Tidak menambah satu prop pun — efeknya murni ke meta di <head>. Nilai
     * balik sengaja array kosong agar payload Inertia tidak ikut membengkak.
     */
    public static function tambahan(string $url, string $judul): array
    {
        $judul = trim($judul);

        if ($judul !== '') {
            Seo::set(['title' => $judul]);
        }

        return [];
    }
}
