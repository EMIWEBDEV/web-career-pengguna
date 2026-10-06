<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Queue Connection Name
    |--------------------------------------------------------------------------
    |
    | Laravel's queue API supports an assortment of back-ends via a single
    | API, giving you convenient access to each back-end using the same
    | syntax for every one. Here you may define a default connection.
    |
    */

    /*
    | Bawaan proyek ini 'webcareers' — antreannya sendiri (N_WEB_CAREERS_Jobs),
    | terpisah dari N_LMS_Jobs milik modul tetangga di basis data yang sama.
    |
    | Karena job TIDAK lagi memaksa koneksinya sendiri, satu perintah cukup:
    |
    |     php artisan queue:work
    |
    | Ganti lewat QUEUE_CONNECTION saja:
    |     webcareers  → worker lokal / VM
    |     cloudtasks  → Cloud Run; task dikirim & dibaca otomatis, tanpa worker
    |     sync        → dijalankan langsung dalam request (uji cepat)
    */
    'default' => env('QUEUE_CONNECTION', 'webcareers'),

    /*
    |--------------------------------------------------------------------------
    | Queue Connections
    |--------------------------------------------------------------------------
    |
    | Here you may configure the connection information for each server that
    | is used by your application. A default configuration has been added
    | for each back-end shipped with Laravel. You are free to add more.
    |
    | Drivers: "sync", "database", "beanstalkd", "sqs", "redis", "null"
    |
    */

    'connections' => [
        'sync' => [
            'driver' => 'sync',
        ],
        'cloudtasks' => [
            'driver' => 'cloudtasks',
            'project' => env('CLOUD_TASKS_PROJECT', ''),
            'location' => env('CLOUD_TASKS_LOCATION', ''),
            'queue' => env('CLOUD_TASKS_QUEUE', 'default'),
            'queue_kpi_notif' => env('CLOUD_TASKS_QUEUE_KPI_NOTIF', 'general-notif'),
            'service_account_email' => env('CLOUD_TASKS_SERVICE_EMAIL', ''),
            'handler' => env('CLOUD_TASKS_HANDLER_URL', 'https://hcis.evonusabersaudara.co.id/handle-task'),
        ],
        'database' => [
            'driver' => 'database',
            'table' => 'N_LMS_Jobs',
            'queue' => 'default',
            'retry_after' => 90,
            'after_commit' => false,
        ],

        // Antrean modul WEB CAREERS — tabelnya sendiri, tidak menyentuh
        // N_LMS_Jobs milik modul tetangga di basis data yang sama.
        // Ini koneksi BAWAAN proyek ini, jadi cukup: php artisan queue:work
        'webcareers' => [
            'driver' => 'database',
            'connection' => env('DB_CONNECTION', 'sqlsrv'),
            'table' => 'N_WEB_CAREERS_Jobs',
            'queue' => 'default',
            'retry_after' => 300,

            // ── JOB BARU DIDAFTARKAN SETELAH TRANSAKSINYA COMMIT ─────────────
            //
            // Job modul ini mengabarkan KEPUTUSAN kepada kandidat: lolos,
            // gugur, undangan wawancara. Dengan 'false', job yang didaftarkan di
            // dalam sebuah transaksi langsung masuk antrean — dan worker yang
            // menjemputnya sepersekian detik kemudian bisa mendahului commit-nya.
            // Bila transaksi itu ternyata digulung balik, suratnya sudah telanjur
            // terkirim atas keputusan yang tidak pernah terjadi. Surat tidak bisa
            // ditarik kembali; barisnya bisa.
            //
            // Ini JARING PENGAMAN, bukan jaminan utamanya: pemanggil di
            // LamaranController sudah sengaja memindahkan seluruh dispatch-nya
            // ke luar transaksi, sebab koneksi 'cloudtasks' yang dipakai di
            // produksi belum tentu menghormati tanda ini.
            'after_commit' => true,
        ],

        'beanstalkd' => [
            'driver' => 'beanstalkd',
            'host' => 'localhost',
            'queue' => 'default',
            'retry_after' => 90,
            'block_for' => 0,
            'after_commit' => false,
        ],

        'sqs' => [
            'driver' => 'sqs',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'prefix' => env('SQS_PREFIX', 'https://sqs.us-east-1.amazonaws.com/your-account-id'),
            'queue' => env('SQS_QUEUE', 'default'),
            'suffix' => env('SQS_SUFFIX'),
            'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
            'after_commit' => false,
        ],

        'redis' => [
            'driver' => 'redis',
            'connection' => 'default',
            'queue' => env('REDIS_QUEUE', 'default'),
            'retry_after' => 90,
            'block_for' => null,
            'after_commit' => false,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Job Batching
    |--------------------------------------------------------------------------
    |
    | The following options configure the database and table that store job
    | batching information. These options can be updated to any database
    | connection and table which has been defined by your application.
    |
    */

    'batching' => [
        'database' => env('DB_CONNECTION', 'mysql'),
        'table' => 'job_batches',
    ],

    /*
    |--------------------------------------------------------------------------
    | Failed Queue Jobs
    |--------------------------------------------------------------------------
    |
    | These options configure the behavior of failed queue job logging so you
    | can control which database and table are used to store the jobs that
    | have failed. You may change them to any database / table you wish.
    |
    */

    'failed' => [
        'driver' => env('QUEUE_FAILED_DRIVER', 'database-uuids'),
        'database' => env('DB_CONNECTION', 'sqlsrv'),
        'table' => 'N_LMS_Failed_Jobs',
    ],

    /*
    |--------------------------------------------------------------------------
    | Named Queues (per-feature isolation)
    |--------------------------------------------------------------------------
    |
    | Nama queue khusus agar job berat tidak bersaing dengan job lain di queue
    | "default". Untuk Cloud Tasks, nama ini harus cocok dengan nama queue yang
    | dibuat di Cloud Tasks console (mis. `gcloud tasks queues create leader-dashboard`).
    |
    */

    'names' => [
        'leader_dashboard' => env('CLOUD_TASKS_QUEUE_LEADER_DASHBOARD', 'leader-dashboard'),
        'kpi_enforcement' => env('CLOUD_TASKS_QUEUE_KPI_ENFORCEMENT', 'kpi-enforcement'),

        // Queue terpusat untuk SEMUA export + cleanup-temp lintas modul (KPI, LMS,
        // dan modul export berikutnya). Render PDF tetap di queue tersendiri.
        'async_export' => env('CLOUD_TASKS_QUEUE_ASYNC_EXPORT', 'async-export'),

        // Queue khusus penjadwalan training LMS (bulk assign). Harus dibuat di
        // Cloud Tasks: `gcloud tasks queues create penjadwalan-queue`.
        'penjadwalan_training' => env('CLOUD_TASKS_QUEUE_PENJADWALAN', 'penjadwalans-queue'),

        // CATATAN: job modul Web Careers TIDAK didaftarkan di sini. Nama antreannya
        // melekat sebagai konstanta di job masing-masing (mis. WcApplyEmailJob,
        // WcPenjadwalanJob) supaya tidak ada dua tempat yang bisa berselisih.
        // Lihat App\Jobs\Career\Concerns\AntreanWebCareers.

        // Deprecated: disisakan untuk kompatibilitas; job export kini pakai
        // 'async_export'. Hapus setelah dipastikan tak ada task tertinggal.
        'kpi_export' => env('CLOUD_TASKS_QUEUE_KPI_EXPORT', 'kpi-export'),
        'lms_export' => env('CLOUD_TASKS_QUEUE_LMS_EXPORT', 'lms-export'),
    ],
];
