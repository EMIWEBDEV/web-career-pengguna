<?php

/*
|--------------------------------------------------------------------------
| Penyimpanan berkas — PROJECT: WEB CAREERS PENGGUNA
|--------------------------------------------------------------------------
|
|   karantina  bucket KARANTINA — satu-satunya bucket yang ditulis aplikasi
|              ini: CV & dokumen lamaran, berkas draf formulir, berkas tes.
|              Zona dalam membacanya untuk dipindai; isinya belum dipercaya.
|   publik     bucket PUBLIK — hanya dibaca aplikasi ini: dokumen untuk
|              kandidat (N_WEB_CAREERS_Pub_Dokumen, mis. surat pengantar),
|              gambar hero & tim. Diisi Sync Worker dari zona dalam.
|
| Bucket admin (bucket bersih) tidak pernah dikenal project ini.
|
*/

$gcs = fn (?string $bucket, ?string $awalan) => [
    'driver' => 'gcs',
    'key_file_path' => env('GOOGLE_CLOUD_KEY_FILE_PATH'), // lokal; di Cloud Run memakai akun layanan
    'project_id' => env('GOOGLE_CLOUD_PROJECT_ID'),
    'bucket' => $bucket,
    'path_prefix' => $awalan,
    'visibility' => 'private',
    'visibility_handler' => \League\Flysystem\GoogleCloudStorage\UniformBucketLevelAccessVisibility::class,
];

return [

    'default' => env('FILESYSTEM_DISK', 'local'),

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app'),
            'throw' => false,
        ],

        'karantina' => $gcs(env('GCS_BUCKET_KARANTINA'), env('GCS_KARANTINA_PREFIX')),

        'publik' => $gcs(env('GCS_BUCKET_PUBLIK'), env('GCS_PUBLIK_PREFIX')),

    ],

];
