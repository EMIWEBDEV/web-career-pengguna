<?php

/*
|--------------------------------------------------------------------------
| Sinkron dua zona — sisi PENGGUNA (zona luar)
|--------------------------------------------------------------------------
|
| Satu-satunya jalan data dari aplikasi ini ke zona dalam:
|
|   aksi kandidat ─┬─ tabel milik            (satu transaksi)
|                  └─ usp_PUB_Outbox_Tulis ──► penerbit (queue wcp-terbit
|                                              + penyapu tiap menit) ──► Pub/Sub wc-masuk
|
| Yang datang dari dalam (salinan master, potret portal, dokumen, status
| akun) ditulis Sync Worker langsung ke database publik — aplikasi ini hanya
| membacanya.
|
*/

return [

    'pubsub' => [
        'project' => env('PUBSUB_PROJECT', env('GOOGLE_CLOUD_PROJECT_ID')),
        'topik' => env('PUBSUB_TOPIK', 'wc-masuk'),
        // Endpoint REGIONAL: pesan berkunci urut (per akun) harus terbit ke
        // region yang sama agar urutannya terjaga.
        'endpoint' => env('PUBSUB_ENDPOINT', 'https://asia-southeast1-pubsub.googleapis.com'),
        'timeout' => (int) env('PUBSUB_TIMEOUT', 10),
    ],

    'penerbit' => [
        // Paling banyak baris yang diterbitkan satu putaran penyapu.
        'batas_sapu' => (int) env('PENERBIT_BATAS_SAPU', 200),
        // Jeda coba ulang: 30 dtk, 60, 120, ... maksimal 30 menit.
        'jeda_awal_detik' => 30,
        'jeda_maks_detik' => 1800,
    ],

    // Token Cloud Scheduler untuk POST /api/tugas/terbit-outbox (header
    // X-Tugas-Token). Kosong = rute penyapu menolak semua permintaan.
    'token_tugas' => env('TUGAS_TOKEN'),

    // Kunci tanda tangan TAUTAN dari surel (konfirmasi kehadiran, surat
    // pengantar, feedback). Dipegang juga oleh zona dalam yang membuat
    // tautannya — BUKAN APP_KEY, supaya kunci enkripsi aplikasi ini tidak
    // pernah dibagi ke zona mana pun.
    'kunci_tautan' => env('TAUTAN_KUNCI'),

    // Kunci untuk membungkus bagian RAHASIA muatan Outbox (token verifikasi,
    // kode reset sandi). Zona dalam memakainya untuk membuka bungkusnya lalu
    // mengirim surel. Format: base64:<32 byte>.
    'kunci_rahasia' => env('SINKRON_KUNCI_RAHASIA'),

    // Database yang TIDAK BOLEH dijangkau aplikasi ini (lihat PenjagaZona).
    'database_terlarang' => array_filter(array_map('trim', explode(',', (string) env('DB_ADMIN_TERLARANG', 'Web_HRIS')))),

];
