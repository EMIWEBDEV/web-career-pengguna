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
    | Props global tambahan untuk setiap halaman portal — kelas dengan method
    | statik `tambahan(string $url, string $judul): array`. PropsSeo menyalin
    | judul halaman ke <title> yang dicetak server, supaya judul tab tidak
    | berkedip dari bawaan ke judul aslinya saat Vue selesai dimuat.
    */
    'props_tambahan' => [
        App\Support\Seo\PropsSeo::class,
    ],

    /** Menit cache daftar menu master. Pendek — perubahan menu harus cepat terasa. */
    'cache_menu_menit' => 5,
];
