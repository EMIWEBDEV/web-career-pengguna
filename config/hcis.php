<?php

/*
|--------------------------------------------------------------------------
| INTEGRASI HCIS — kanal kalender operasional untuk perhitungan SLA
|--------------------------------------------------------------------------
|
| Web Careers menghitung SLA pemenuhan MPP dalam HARI KERJA. Yang menentukan
| sebuah tanggal terhitung atau tidak bukan kalender masehi, melainkan apakah
| kantor Head Office beroperasi hari itu — dan itu hanya HCIS yang tahu.
|
| ── KENAPA BUKAN MEMBACA TABELNYA LANGSUNG ─────────────────────────────────
|
| Hari libur & cuti bersama tersebar di empat tabel HRIS (HRIS_Hari_Libur,
| N_HRIS_Cuti_Bersama, _Detail, _Exclusion) dengan aturan include/exclude per
| divisi dan per karyawan. Menyalin logika penyaringannya ke sini berarti dua
| sistem menghitung hal yang sama dengan dua salinan aturan — dan begitu HCIS
| mengubah aturannya, SLA rekruter salah tanpa ada yang menyadarinya.
|
| Karena itu yang diminta ke HCIS adalah KEPUTUSAN ("tanggal ini HO beroperasi
| atau tidak"), bukan data mentah. Lihat:
| docs/02-09-2026/Spesifikasi-API-Kalender-Operasional-HO.pdf
|
| ── STATUS ─────────────────────────────────────────────────────────────────
|
| Endpoint HCIS SUDAH TERSEDIA dan terverifikasi (staging, 5 Sep 2026):
|   GET /api/v1/hcis/kalender-operasional/tahun/{tahun}?unit=HEAD_OFFICE
|   GET /api/v1/hcis/kalender-operasional?dari=&sampai=&unit=
| Dokumentasi: {domain}/api/docs
*/

return [

    /*
    |--------------------------------------------------------------------------
    | LINGKUNGAN & DOMAIN
    |--------------------------------------------------------------------------
    |
    | Mengikuti pola config/hclearn.php: satu saklar lingkungan, tiga domain.
    | Nilai HCIS_ENV yang tidak dikenal jatuh ke 'production' — lihat domain().
    */

    'env' => env('HCIS_ENV', 'development'),

    'domains' => [
        'development' => env('HCIS_URL_DEVELOPMENT', 'http://hcis.test'),
        'staging' => env('HCIS_URL_STAGING', 'https://hcisxkpi-595840247695.asia-southeast1.run.app'),
        'production' => env('HCIS_URL_PRODUCTION', 'https://hcis.evonusabersaudara.co.id'),
    ],

    /*
    |--------------------------------------------------------------------------
    | KREDENSIAL KANAL
    |--------------------------------------------------------------------------
    |
    | Namanya sengaja HCIS_WC_* — BUKAN HCIS_API_*, yang sudah dipakai
    | config/dev/fransDev.php untuk keperluan lain. Dua kanal berbagi nama env
    | adalah cara paling mudah membuat kredensial saling menimpa diam-diam.
    |
    | Penandatanganan: HMAC-SHA512 atas 7 baris kanonik, dikirim lewat lima
    | header X-HC-*. Lihat KalenderHcisClient::header() — di sanalah
    | urutan barisnya ditulis, dan urutan itu tidak boleh ditebak ulang.
    |
    | Kunci staging & produksi BERBEDA. Isi lewat .env, jangan ditulis di sini.
    */

    'api_public' => env('HCIS_WC_API_PUBLIC'),
    'api_secret' => env('HCIS_WC_API_SECRET'),

    'prefix' => env('HCIS_PREFIX', 'api/v1/hcis'),

    /*
    |--------------------------------------------------------------------------
    | KALENDER OPERASIONAL
    |--------------------------------------------------------------------------
    */

    'kalender' => [

        /*
        | mati | http | dummy
        |
        | ── BAWAANNYA 'mati', DAN ITU DISENGAJA ──────────────────────────
        |
        | 'mati' berarti kanal HCIS TIDAK PERNAH dipanggil. Bukan "dicoba lalu
        | gagal": tidak ada permintaan HTTP yang dibuat sama sekali. SLA
        | dihitung murni dari HRIS_Hari_Libur — tabel milik basis data ini
        | sendiri, yang sudah berisi libur nasional & cuti bersama.
        |
        | Dipilih karena kanal HCIS belum disepakati untuk produksi. Selama
        | belum, memanggilnya hanya menambah satu ketergantungan jaringan pada
        | setiap perhitungan SLA — untuk data yang sudah kita punya sendiri.
        |
        | 'http' memanggil endpoint HCIS sungguhan. Nyalakan HANYA setelah
        | kredensial produksi ada dan endpointnya disepakati; seluruh jalur
        | HTTP-nya masih utuh berikut singgahan & penanganan galatnya.
        |
        | 'dummy' membaca storage/app/hcis/kalender-*.json. JANGAN dipakai di
        | produksi: tanggalnya perkiraan dan sudah terbukti meleset — Idul
        | Fitri 2026 tercatat 21 Mei, padahal yang benar 31 Maret.
        */
        'driver' => env('HCIS_KALENDER_DRIVER', 'mati'),

        /*
        | Unit yang ditanyakan. SLA rekrutmen milik TIM di Head Office, bukan
        | milik satu karyawan — jadi yang ditanyakan status operasional unit,
        | bukan cuti perorangan.
        */
        'unit' => env('HCIS_KALENDER_UNIT', 'HEAD_OFFICE'),

        'path_tahun' => 'kalender-operasional/tahun',
        'path_rentang' => 'kalender-operasional',

        /*
        | Berapa lama jawaban HCIS disimpan (menit). Kalender jarang berubah,
        | dan perhitungan SLA memanggilnya berkali-kali dalam satu layar.
        | 1440 menit = sehari.
        */
        'ttl' => (int) env('HCIS_KALENDER_TTL', 1440),

        /*
        | Batas tunggu panggilan HCIS (detik). Sengaja pendek: kalender adalah
        | data pendukung, dan menahan layar MPP 30 detik demi hari libur jauh
        | lebih buruk daripada memakai salinan terakhir.
        */
        'timeout' => (int) env('HCIS_KALENDER_TIMEOUT', 8),

        /*
        | Rentang terlebar yang boleh diminta sekali panggil (hari). Sepakat
        | dengan batas yang diminta ke HCIS di dokumen spesifikasi.
        */
        'rentang_maks' => 400,

        /*
        |----------------------------------------------------------------------
        | BERKAS DUMMY
        |----------------------------------------------------------------------
        |
        | Relatif terhadap storage/app. Satu berkas per tahun:
        | kalender-2026.json, kalender-2027.json, dan seterusnya.
        |
        | Bentuk isinya PERSIS sama dengan response HCIS sungguhan — itu yang
        | membuat pertukaran driver tidak menuntut perubahan kode sama sekali,
        | dan sekaligus menguji bahwa bentuk yang kita minta memang bisa
        | dipakai.
        */
        'dummy_path' => env('HCIS_KALENDER_DUMMY_PATH', 'hcis'),
    ],

];
