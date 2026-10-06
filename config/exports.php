<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Registry Modul Export (Pusat Aktivitas)
    |--------------------------------------------------------------------------
    |
    | Memetakan kolom `Module` di tabel N_HRIS_Async_Tasks ke service export-nya.
    | ActivityCenterController memakai ini untuk meresolusi service (download,
    | retry, dismiss) tanpa hardcode per modul.
    |
    | Menambah modul export baru:
    |   1. Buat service `extends AbstractExportService` + implement dispatchJob().
    |   2. Buat Job-nya dengan onQueue(config('queue.names.async_export')).
    |   3. Daftarkan 1 baris di sini: '<module>' => <Service>::class.
    |
    */

    'modules' => [
        'kpi-export' => \App\Services\KPI\KpiExportService::class,
        'lms-training' => \App\Services\LMS\LmsTrainingExportService::class,
        // 'visitor-export' => \App\Services\Visitor\VisitorExportService::class,
        // 'ess-export'     => \App\Services\ESS\EssExportService::class,
    ],

];
