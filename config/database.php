<?php

/*
|--------------------------------------------------------------------------
| Database — PROJECT: WEB CAREERS PENGGUNA (zona luar)
|--------------------------------------------------------------------------
|
| Hanya DATABASE PUBLIK (staging: emi_tm_demo). Kredensial database admin
| tidak pernah didefinisikan di sini, dan aplikasi menolak menyala bila ada
| koneksi yang menunjuk ke database admin (App\Support\Sinkron\PenjagaZona).
|
|   sqlsrv    aplikasi kandidat — peran wc_publik_app: baca salinan & potret,
|             tulis tabel milik, EXECUTE usp_PUB_Outbox_Tulis.
|   penerbit  penerbit Outbox — peran wc_publik_penerbit: baca Outbox dan
|             ubah kolom status terbitnya saja. DB_PENERBIT_* kosong = memakai
|             akun aplikasi (lokal / staging).
|   sqlite    hanya untuk pengujian (phpunit).
|
*/

$sqlsrv = fn (string $user, string $pass) => [
    'driver' => 'sqlsrv',
    'url' => env('DATABASE_URL'),
    'host' => env('DB_HOST', 'localhost'),
    'port' => env('DB_PORT', '1433'),
    'database' => env('DB_DATABASE', 'forge'),
    'username' => $user,
    'password' => $pass,
    'charset' => 'utf8',
    'prefix' => '',
    'prefix_indexes' => true,
    'encrypt' => env('DB_ENCRYPT', 'yes'),
    'trust_server_certificate' => env('DB_TRUST_SERVER_CERTIFICATE', 'false'),

    // Tahan cold start Cloud Run & gangguan jaringan sesaat.
    'options' => [
        'LoginTimeout' => 30,
        'ConnectRetryCount' => 3,
        'ConnectRetryInterval' => 10,
    ],
];

return [

    'default' => env('DB_CONNECTION', 'sqlsrv'),

    'connections' => [

        'sqlsrv' => $sqlsrv((string) env('DB_USERNAME', 'forge'), (string) env('DB_PASSWORD', '')),

        'penerbit' => $sqlsrv(
            (string) (env('DB_PENERBIT_USERNAME') ?: env('DB_USERNAME', 'forge')),
            (string) (env('DB_PENERBIT_PASSWORD') ?: env('DB_PASSWORD', '')),
        ),

        'sqlite' => [
            'driver' => 'sqlite',
            'url' => env('DATABASE_URL'),
            'database' => env('DB_DATABASE', database_path('database.sqlite')),
            'prefix' => '',
            'foreign_key_constraints' => env('DB_FOREIGN_KEYS', true),
        ],

    ],

    'migrations' => 'migrations',

];
