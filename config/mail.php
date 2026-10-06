<?php

/*
|--------------------------------------------------------------------------
| SURAT SUDAH BUKAN URUSAN LAYANAN INI
|--------------------------------------------------------------------------
|
| Seluruh email kandidat berangkat lewat EVO Mail Server. Pengaturannya ada
| di config/surat.php, dan pemanggilnya App\Services\Surat\SuratClient.
|
| Berkas ini disisakan dalam bentuk paling kecil yang masih sah, BUKAN dihapus:
| Laravel tetap menyalakan mail manager-nya, dan sebagian paket pihak ketiga
| membaca config('mail.*') tanpa bertanya lebih dulu apakah aplikasinya memang
| mengirim surat. Menghapus berkasnya membuat mereka meledak dengan galat yang
| menunjuk ke arah yang sama sekali salah.
|
| ══ KENAPA BAWAANNYA 'log', BUKAN 'smtp' ═════════════════════════════════
|
| Karena tidak ada lagi kredensial SMTP di layanan ini — MAIL_HOST, MAIL_USERNAME,
| dan MAIL_PASSWORD sudah dibuang dari .env, dan tempatnya sekarang di basis data
| server surat, terikat pada kunci yang dipakai.
|
| Kalau bawaannya tetap 'smtp', panggilan Mail:: yang kelak tersisip tanpa
| sengaja akan mencoba menyambung ke host yang tidak ada, lalu menggantung
| sampai habis waktu — di dalam permintaan HTTP yang sedang melayani kandidat.
| Dengan 'log', panggilan seperti itu mendarat di berkas log: terlihat saat
| ditelusuri, dan tidak menahan siapa pun.
|
| ══ TIDAK ADA SURAT YANG BOLEH BERANGKAT DARI SINI ═══════════════════════
|
| Kalau suatu saat ada yang perlu dikirim, tambahkan templatenya di server
| surat lalu panggil SuratClient — jangan menghidupkan kembali mailer di sini.
| Alamat pengirim ditentukan oleh KUNCI di sisi server surat, dan itulah yang
| membuat kunci yang bocor tidak bisa berkirim surat atas nama siapa pun
| selain dirinya sendiri.
*/

return [

    'default' => env('MAIL_MAILER', 'log'),

    'mailers' => [

        'log' => [
            'transport' => 'log',
            'channel' => env('MAIL_LOG_CHANNEL'),
        ],

        // Dipakai pengujian: surat ditampung di memori, tidak ke mana-mana.
        'array' => [
            'transport' => 'array',
        ],
    ],

    /*
    | Ada semata karena Laravel membacanya saat menyusun sebuah Mailable.
    | Alamat pengirim yang SEBENARNYA datang dari baris aplikasi di basis data
    | server surat — nilai di sini tidak pernah muncul di surat mana pun.
    */
    'from' => [
        'address' => 'no-reply@evopet.id',
        'name' => 'EVO Group Career',
    ],

];
