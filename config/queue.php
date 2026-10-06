<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Queue — PROJECT: WEB CAREERS PENGGUNA
    |--------------------------------------------------------------------------
    |
    | Project ini hanya punya SATU job: penerbit Outbox (App\Jobs\TerbitkanOutbox)
    | di queue `wcp-terbit`. Tidak ada tabel jobs di database publik — queue-nya
    | Cloud Tasks, dan kemajuannya disimpan di Sinkron_Outbox sendiri, bukan di
    | queue. Penyapu (POST /api/tugas/terbit-outbox, dipicu Cloud Scheduler)
    | menyusul apa pun yang tertinggal.
    |
    |     cloudtasks  Cloud Run (produksi/staging)
    |     sync        lokal: penerbit langsung jalan sesudah request selesai
    */
    'default' => env('QUEUE_CONNECTION', 'sync'),

    'connections' => [
        'sync' => [
            'driver' => 'sync',
        ],

        'cloudtasks' => [
            'driver' => 'cloudtasks',
            'project' => env('CLOUD_TASKS_PROJECT', ''),
            'location' => env('CLOUD_TASKS_LOCATION', 'asia-southeast1'),
            'queue' => env('CLOUD_TASKS_QUEUE', 'wcp-terbit'),
            'service_account_email' => env('CLOUD_TASKS_SERVICE_EMAIL', ''),
            'handler' => env('CLOUD_TASKS_HANDLER_URL', ''),
            // Job diantrekan SESUDAH transaksi Outbox-nya commit; kalaupun
            // terlewat, penyapu yang menerbitkannya.
            'after_commit' => true,
        ],
    ],

    /*
    | Job penerbit tidak pernah "gagal" ke tabel: galatnya dicatat di baris
    | Outbox (Galat_Terakhir + Coba_Lagi_At), dan penyapu mengulangnya.
    */
    'failed' => [
        'driver' => env('QUEUE_FAILED_DRIVER', 'null'),
    ],
];
