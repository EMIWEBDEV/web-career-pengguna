<?php

/*
|--------------------------------------------------------------------------
| Cache — PROJECT: WEB CAREERS PENGGUNA
|--------------------------------------------------------------------------
| Berkas (lokal per instans): katalog skema, menu, token Pub/Sub. Tidak ada
| yang wajib dibagi antar-instans Cloud Run.
*/

return [

    'default' => env('CACHE_DRIVER', 'file'),

    'stores' => [
        'array' => [
            'driver' => 'array',
            'serialize' => false,
        ],

        'file' => [
            'driver' => 'file',
            'path' => storage_path('framework/cache/data'),
            'lock_path' => storage_path('framework/cache/data'),
        ],
    ],

    'prefix' => env('CACHE_PREFIX', 'wc_pengguna_cache_'),

];
