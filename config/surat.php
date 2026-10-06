<?php

/*
|--------------------------------------------------------------------------
| SERVER SURAT EVO — kanal pengiriman email
|--------------------------------------------------------------------------
|
| Web Careers tidak lagi memegang kredensial SMTP. Ia menembak API ke server
| surat (proyek evo-mail), dan server itu yang mengirim.
|
| Yang TIDAK ada di sini, dan itu disengaja: host, port, nama pengguna, kata
| sandi, dan alamat pengirim. Semuanya milik server surat, terikat pada KUNCI
| yang dipakai — jadi kunci yang bocor tidak bisa berkirim surat dari alamat
| mana pun selain miliknya sendiri.
|
| ══ YANG TETAP MILIK WEB CAREERS ═══════════════════════════════════════════
|
| Antrean dan percobaan ulangnya. WcSyncEmailJob, WcApplyEmailJob, dan
| WcJadwalEmailJob tetap yang memutuskan KAPAN sebuah surat dikirim dan untuk
| kandidat mana — merekalah yang tahu konteksnya. Server surat cuma dipanggil
| dari dalam job itu, dan menjawab terkirim atau gagal apa adanya.
*/

return [

    // Alamat server surat, tanpa garis miring di ujung.
    'basis' => rtrim((string) env('SURAT_BASIS', ''), '/'),

    // Diterbitkan di sisi server surat: `php artisan surat:aplikasi buat ...`.
    // Kunci rahasianya hanya tampil sekali di sana.
    //
    // Api_Public dipakai MENCARI barisnya dan boleh terbaca di log;
    // Api_Secret dipakai MENANDATANGANI dan tidak pernah melintas jaringan.
    'api_public' => env('SURAT_API_PUBLIC'),
    'api_secret' => env('SURAT_API_SECRET'),

    /*
    | Batas waktu satu panggilan, dalam detik.
    |
    | Server surat mengirim SERENTAK di dalam permintaan — ia tidak mengantre
    | — jadi angkanya harus memberi ruang untuk satu jabat tangan SMTP penuh.
    |
    | Tapi jangan berlebihan: panggilan yang kehabisan waktu akan diulang oleh
    | job, sementara suratnya boleh jadi sudah telanjur berangkat. Surat
    | gandanya lahir dari kegagalan yang sebenarnya tidak pernah ada.
    */
    'timeout' => (int) env('SURAT_TIMEOUT', 25),

    /*
    | TIDAK ADA SAKELAR PERALIHAN DI SINI, DAN ITU DISENGAJA.
    |
    | Peralihannya sudah selesai. Web Careers tidak lagi memegang kredensial
    | SMTP, tidak lagi menyimpan berkas Blade surat, dan tidak lagi memanggil
    | Mail:: di mana pun — jadi tidak ada lagi jalur kedua yang bisa dituju
    | seandainya sakelarnya dimatikan.
    |
    | Sakelar yang satu-satunya akibatnya adalah "tidak ada surat yang
    | berangkat, tanpa satu pun galat" jauh lebih berbahaya daripada tidak
    | punya sakelar sama sekali. Yang menggantikannya: kegagalan memanggil
    | server surat DILEMPAR, diulang oleh antrean, lalu tercatat di
    | N_WEB_CAREERS_Failed_Jobs — terlihat, bukan senyap.
    */
];
