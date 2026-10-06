<?php

/*
|------------------------------------------------------------------------------
| WEB CAREERS — IDENTITAS SHELL
|------------------------------------------------------------------------------
| Isi shell (nama aplikasi, logo, anak usaha, judul modul) ditaruh di sini
| sebagai DATA, bukan ditulis di dalam kode PHP shell. Kalau suatu saat brand
| berubah, yang disunting cuma berkas ini — bukan kelas yang dipakai 50 halaman.
|
| Menu TIDAK ada di sini: sumbernya tabel N_WEB_CAREERS_Menu (lihat Master Menu).
*/

return [
    // Kode & label modul di sidebar/breadcrumb.
    'kode' => 'CAREER',
    'label' => 'Web Career',
    'subtitle' => 'Rekrutmen & MT',
    'ikon' => 'bi bi-briefcase-fill',

    'merek' => [
        'nama_aplikasi' => 'EVO Group Unified Platform',
        'nama_singkat' => 'EVO',
        'logo' => 'logo/EVOGROUP.png',
        'logo_alt' => 'EVO Group',
        'anak_usaha' => [
            ['id' => 'emi', 'nama' => 'PT Evo Manufacturing Indonesia', 'berkas' => 'logo/EMI.png', 'inisial' => 'EMI'],
            ['id' => 'enb', 'nama' => 'PT Evo Nusa Bersaudara', 'berkas' => 'logo/ENB.png', 'inisial' => 'ENB'],
            ['id' => 'gmn', 'nama' => 'PT Graha Maju Nusantara', 'berkas' => 'logo/GMN.png', 'inisial' => 'GMN'],
        ],
    ],

    /*
    | Props global tambahan — pengait supaya fitur baru TIDAK perlu menyunting
    | PropsShell. Isi dengan nama kelas yang punya method statik:
    |
    |     public static function tambahan(string $url, string $judul): array
    |
    | Hasilnya digabung ke props setiap halaman admin. Tambah di BARIS BARU di
    | akhir daftar supaya git bisa menggabungkannya sendiri saat merge.
    */
    'props_tambahan' => [
        // App\Support\Career\Shell\Tambahan\ContohProps::class,

        // Menyalin judul modul ke <title> yang dicetak server, supaya judul
        // tab halaman admin tidak berkedip dari default ke judul aslinya saat
        // Vue selesai dimuat. Lihat App\Support\Seo\PropsSeo.
        App\Support\Seo\PropsSeo::class,
    ],

    /** Menit cache daftar menu master. Pendek — perubahan menu harus cepat terasa. */
    'cache_menu_menit' => 5,
];
