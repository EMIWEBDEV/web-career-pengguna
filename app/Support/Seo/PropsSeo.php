<?php

namespace App\Support\Seo;

/**
 * Menyambungkan judul modul shell admin ke meta halaman.
 *
 * Setiap controller admin sudah menyebutkan judul halamannya saat merakit
 * shell — `CareerShell::props('/karir/penjadwalan', 'Penjadwalan')`. Judul itu
 * dipungut di sini dan dijadikan judul <title> yang DICETAK SERVER, sehingga
 * cocok persis dengan judul yang nanti dipasang komponen <Head> Vue.
 *
 * Tanpa ini, ~50 halaman admin akan memuat dengan judul default lebih dulu
 * ("Karier Bersama EVO Group | Careers Evo Group") lalu berkedip berganti ke
 * judul aslinya begitu Vue hidup. Menaruhnya di satu titik sambung ini juga
 * berarti modul admin BARU ikut benar tanpa perlu didaftarkan di mana pun.
 *
 * Terdaftar lewat config('career_shell.props_tambahan') — titik ekstensi resmi
 * PropsShell, yang sengaja dipilih supaya CareerShell yang dibekukan itu tidak
 * perlu disunting.
 */
class PropsSeo
{
    /**
     * Dipanggil PropsShell untuk setiap halaman admin.
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
