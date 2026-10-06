<?php

/*
|--------------------------------------------------------------------------
| Autentikasi — PROJECT: WEB CAREERS PENGGUNA
|--------------------------------------------------------------------------
|
| Login kandidat TIDAK memakai guard Laravel: AuthController menulis sesi
| `career_auth` dan middleware `career.auth` memeriksanya ulang ke tabel akun
| setiap permintaan. Guard di bawah hanya kerangka minimal yang dituntut
| framework (mis. middleware `guest`), menunjuk tabel akun publik.
|
*/

return [

    'defaults' => [
        'guard' => 'web',
    ],

    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'kandidat',
        ],
    ],

    'providers' => [
        'kandidat' => [
            'driver' => 'database',
            'table' => 'N_WEB_CAREERS_Users',
        ],
    ],

    'password_timeout' => 10800,

];
