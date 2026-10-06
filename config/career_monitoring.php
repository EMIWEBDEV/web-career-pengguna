<?php

/*
|--------------------------------------------------------------------------
| WEB CAREER — Monitoring Rekrutmen
|--------------------------------------------------------------------------
| Ambang & batas halaman Monitoring. Nilai di-echo ke frontend lewat
| respons /monitoring/live (meta) — UI tidak pernah hardcode angka ini.
*/

return [
    // Tahap BERJALAN lebih lama dari ini (hari) dianggap MACET di panel
    // "Perlu Perhatian".
    'macet_hari' => (int) env('WC_MONITOR_MACET_HARI', 7),

    // Siap Diputus menggantung lebih lama dari ini (hari) disorot merah
    // (semua Siap Diputus tetap tampil berapapun umurnya).
    'siap_diputus_sorot_hari' => (int) env('WC_MONITOR_SIAP_SOROT', 2),

    // Batas baris panel Perlu Perhatian (urut aging terlama).
    'perhatian_maks' => 50,

    // Batas nama saat expand satu segmen funnel.
    'anggota_tahap_maks' => 100,
];
