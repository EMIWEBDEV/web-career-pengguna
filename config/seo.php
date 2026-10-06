<?php

/*
|--------------------------------------------------------------------------
| SEO / META SOSIAL — WEB CAREERS EVO GROUP
|--------------------------------------------------------------------------
| Satu tempat untuk SEMUA teks yang muncul di tab peramban dan di kartu
| pratinjau saat tautan dibagikan (WhatsApp, Facebook, LinkedIn, X, Telegram).
|
| CATATAN PENTING — DOMAIN TIDAK PERNAH DITULIS DI SINI.
| Kanonik, og:url, dan URL gambar dibangun dari HOST PERMINTAAN yang sedang
| berjalan (lihat App\Support\Seo\SeoMeta). Jadi berkas ini sama persis di
| lokal (web-careers.test), staging, dan produksi (career.evopet.id) — tidak
| ada yang perlu diubah saat pindah lingkungan. Yang membedakan hanya .env.
*/

return [
    // Nama situs. Dipakai sebagai akhiran judul tab: "{Nama Halaman} | {site_name}"
    // dan sebagai og:site_name. Dibaca ulang oleh sisi Vue lewat
    // <meta name="app-site-name"> supaya cuma ada SATU sumber kebenaran.
    'site_name' => env('SEO_SITE_NAME', 'Careers Evo Group'),

    'title_separator' => ' | ',

    // Judul halaman depan / cadangan bila rute tidak punya entri di 'pages'.
    'default_title' => env('SEO_DEFAULT_TITLE', 'Karier Bersama EVO Group'),

    'default_description' => env(
        'SEO_DEFAULT_DESCRIPTION',
        'Portal karier resmi EVO Group. Temukan lowongan terbaru, program Management Trainee, '
        . 'dan magang di seluruh unit bisnis EVO Group — lalu lamar langsung secara daring.',
    ),

    // Kata kunci pencarian (opsional, dampak kecil tapi tidak merugikan).
    // Dampaknya kecil bagi Google modern, tapi masih dibaca Bing dan tidak
    // merugikan. Yang benar-benar menentukan tetap isi halaman + JSON-LD.
    'keywords' => env('SEO_KEYWORDS',
        'lowongan kerja palembang, loker palembang, lowongan kerja sumsel, '
        . 'lowongan kerja sumatera selatan, karier evo group, lowongan kerja evo group, '
        . 'lowongan kerja makanan minuman palembang, pabrik makanan palembang, '
        . 'evo group, pt evo group, evopet, emi, ect, gizi mitra nusantara, '
        . 'management trainee palembang, magang palembang, rekrutmen sumsel'),

    /*
    | GAMBAR PRATINJAU
    |--------------------------------------------------------------------------
    | Path RELATIF terhadap public/. Dijadikan URL absolut memakai host
    | permintaan. WhatsApp menolak gambar transparan (jadi hitam) dan gambar
    | besar, maka berkas ini dibuat 1200x630 JPEG solid lewat:
    |     php artisan seo:og-image
    */
    'image' => env('SEO_IMAGE', 'og/og-default.jpg'),
    'image_width' => 1200,
    'image_height' => 630,
    'image_type' => 'image/jpeg',
    'image_alt' => 'EVO Group — Portal Karier',

    'locale' => 'id_ID',
    'theme_color' => '#4f46e5',

    // Akun media sosial (kosongkan bila belum ada; tag-nya otomatis tidak dicetak).
    'twitter_site' => env('SEO_TWITTER_SITE', ''),

    // Profil resmi perusahaan untuk JSON-LD "sameAs".
    'social_profiles' => array_values(array_filter([
        env('SEO_SOCIAL_INSTAGRAM', ''),
        env('SEO_SOCIAL_LINKEDIN', ''),
        env('SEO_SOCIAL_FACEBOOK', ''),
        env('SEO_SOCIAL_YOUTUBE', ''),
    ])),

    /*
    | VERIFIKASI KEPEMILIKAN SITUS
    |--------------------------------------------------------------------------
    | Tempelkan kode dari Google Search Console (Setelan → Verifikasi
    | kepemilikan → Tag HTML) ke .env:
    |
    |     SEO_GOOGLE_VERIFICATION=abc123...
    |
    | Yang disalin CUKUP nilai content="..."-nya, bukan seluruh tag.
    |
    | Dibiarkan kosong = tag-nya tidak dicetak sama sekali. Itu disengaja:
    | meta verifikasi bernilai kosong dibaca Google sebagai klaim yang GAGAL,
    | bukan sebagai ketiadaan klaim.
    */
    'google_verification' => env('SEO_GOOGLE_VERIFICATION', ''),
    'bing_verification' => env('SEO_BING_VERIFICATION', ''),

    /*
    | JANGKAUAN GEOGRAFIS
    |--------------------------------------------------------------------------
    | Menegaskan bahwa lowongan di situs ini berada di Sumatera Selatan.
    | Dipakai meta geo.* dan JSON-LD Organization.address.
    */
    'geo_placename' => env('SEO_GEO_PLACE', 'Palembang'),
    'geo_region_name' => env('SEO_GEO_REGION', 'Sumatera Selatan'),
    // Alun-alun Palembang. Ketelitiannya tidak perlu tinggi — ini penanda
    // wilayah, bukan alamat pengiriman.
    'geo_position' => env('SEO_GEO_POSITION', '-2.976074, 104.775429'),

    /*
    | NAMA LAIN & WILAYAH LAYANAN
    |--------------------------------------------------------------------------
    | Google memakai alternateName untuk MENYATUKAN entitas: tanpa ini, orang
    | yang mencari "PT EVO Group", "EVO Palembang", dan "karir EMI" dianggap
    | mencari tiga perusahaan yang berbeda.
    */
    'alternate_names' => array_values(array_filter(array_map('trim', explode(',', env(
        'SEO_ALTERNATE_NAMES',
        'PT EVO Group, EVO, EVO Group Palembang, Karir EVO Group, Lowongan EVO Group',
    ))))),

    'area_served' => array_values(array_filter(array_map('trim', explode(',', env(
        'SEO_AREA_SERVED',
        'Palembang, Sumatera Selatan, Sumatera, Indonesia',
    ))))),

    /*
    | ANAK USAHA
    |--------------------------------------------------------------------------
    | Nama unit bisnis yang lowongannya dibuka lewat portal ini. Dicetak sebagai
    | subOrganization supaya pencarian "lowongan EMI" atau "karir ECT" mendarat
    | di sini, bukan berakhir tanpa hasil.
    |
    | Disamakan dengan daftar anak usaha di config/career_shell.php — kalau ada
    | unit baru, tambahkan di KEDUANYA.
    */
    'sub_organizations' => array_values(array_filter(array_map('trim', explode(',', env(
        'SEO_SUB_ORGANIZATIONS',
        'PT Evo Manufacturing Indonesia, PT Evo Nusa Bersaudara, PT Evo Cipta Teknologi, '
        . 'PT Gizi Mitra Nusantara, PT Evo Rasa',
    ))))),

    'organization_name' => env('SEO_ORGANIZATION', 'EVO Group'),
    // Logo persegi untuk JSON-LD (bukan kartu 1200x630).
    'organization_logo' => env('SEO_ORGANIZATION_LOGO', 'logo/EVOGROUP.png'),

    /*
    | MESIN PENCARI BOLEH MENGINDEKS?
    |--------------------------------------------------------------------------
    | Staging HARUS tidak terindeks supaya tidak bersaing dengan produksi di
    | Google. Ini TIDAK memengaruhi pratinjau WhatsApp/Facebook — perayap
    | media sosial tetap boleh mengambil halaman (lihat routes + robots).
    */
    'indexable' => (bool) env('SEO_INDEXABLE', env('APP_ENV') === 'production'),

    /*
    | PETA JUDUL & DESKRIPSI PER RUTE
    |--------------------------------------------------------------------------
    | Kunci = NAMA rute (bukan path), supaya path boleh berubah tanpa merusak
    | apa pun. Halaman yang isinya dinamis (detail lowongan / MT / tim) diisi
    | dari controller lewat SeoMeta::set(), jadi tidak ada di sini.
    |
    | 'noindex' => true untuk halaman yang tidak pantas muncul di Google
    | (portal kandidat, formulir bertoken) — pratinjau berbagi TETAP jalan,
    | hanya indeksnya yang ditahan.
    */
    'pages' => [
        // ── Publik ────────────────────────────────────────────────────────
        'career.home' => [
            'title' => 'Karier Bersama EVO Group',
            'description' => null, // pakai default_description
        ],
        'career.landing' => [
            'title' => 'Karier Bersama EVO Group',
        ],
        'career.lowongan.semua' => [
            'title' => 'Semua Lowongan',
            'description' => 'Daftar lengkap lowongan kerja, program Management Trainee, dan magang '
                . 'yang sedang dibuka di seluruh unit bisnis EVO Group.',
        ],
        'career.tim.semua' => [
            'title' => 'Fungsi Perusahaan',
            'description' => 'Kenali tim dan fungsi perusahaan di EVO Group — dari operasional, '
                . 'komersial, hingga teknologi — beserta lowongan yang sedang dibuka di tiap tim.',
        ],
        'career.faq' => [
            'title' => 'FAQ Kandidat',
            'description' => 'Pertanyaan yang sering diajukan kandidat tentang pendaftaran, seleksi, '
                . 'program Management Trainee, dan penempatan kerja di EVO Group.',
        ],

        // ── Auth (boleh dibagikan, tidak perlu diindeks) ───────────────────
        'login' => ['title' => 'Masuk', 'noindex' => true],
        'register' => ['title' => 'Daftar Akun', 'noindex' => true],
        'career.login' => ['title' => 'Masuk', 'noindex' => true],
        'career.register' => ['title' => 'Daftar Akun', 'noindex' => true],
        'ganti-sandi' => ['title' => 'Ganti Kata Sandi', 'noindex' => true],
        'career.auth.verifikasi-email' => ['title' => 'Verifikasi Email', 'noindex' => true],
        'career.auth.menunggu-verifikasi' => ['title' => 'Menunggu Verifikasi', 'noindex' => true],
        'profil' => ['title' => 'Profil Saya', 'noindex' => true],
        'career.apply' => ['title' => 'Formulir Lamaran', 'noindex' => true],
    ],

    /*
    | AWALAN NAMA RUTE YANG SELALU noindex
    |--------------------------------------------------------------------------
    | Jaring pengaman untuk rute yang belum/tidak akan didaftarkan di 'pages'.
    | Sengaja memakai NAMA rute, bukan path: halaman berakun dan halaman publik
    | sama-sama berada di bawah path /karir, jadi awalan path tidak bisa
    | membedakan keduanya.
    */
    'noindex_route_prefixes' => [
        'career.portal.',
        'career.api.',
        'career.lamaran.',
        'career.referensi.',
        'career.feedback.',
        'career.konfirmasi.',
        'career.surat.',
    ],
];
