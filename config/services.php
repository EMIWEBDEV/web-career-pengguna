<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Layanan pihak ketiga — PROJECT: WEB CAREERS PENGGUNA
    |--------------------------------------------------------------------------
    |
    | Hanya yang dipakai situs kandidat. Integrasi zona dalam (HCIS, KPI, LMS,
    | Visitor, token operasional & scheduler, kunci penampil log, GCP admin)
    | tinggal di project admin — tidak ada nilai bawaan rahasia di sini.
    |
    */

    'project_config' => [
        // Versi aplikasi yang ditampilkan di shell portal kandidat.
        'app_version' => env('APP_VERSION', '2.13.0'),
    ],

    // Cloudflare Turnstile (CAPTCHA halaman login Web Careers).
    'cloudflare' => [
        'turnstile_sitekey' => env('CLOUDFLARE_TURNSTILE_SITEKEY'),
        'turnstile_secret' => env('CLOUDFLARE_TURNSTILE_SECRET'),
    ],
];
