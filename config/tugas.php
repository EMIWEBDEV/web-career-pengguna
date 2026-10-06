<?php

/*
|--------------------------------------------------------------------------
| PEMICU TUGAS TERJADWAL — dipanggil Cloud Scheduler
|--------------------------------------------------------------------------
| Cloud Run tidak punya cron dan tidak menjalankan `php artisan schedule:run`.
| Yang menekan tombolnya Cloud Scheduler, lewat:
|
|   POST https://<web-careers>/api/tugas/{tugas}
|   Header: X-Tugas-Token: <isi TUGAS_TOKEN>
|
| Pola sama dengan cat-evo-pembaharuan (config diagnostik.pemicu_token):
| jadwalnya hidup di Cloud Scheduler, bukan di basis data.
*/

return [

    // HANYA dari env — berkas config ikut masuk git, jadi kunci yang ditulis
    // di sini sama saja dengan membuka pemicunya ke siapa pun yang bisa
    // membaca repositori. Kosong / kurang dari 32 karakter = endpointnya
    // menjawab 404 (gagal-aman). Buat: php -r "echo bin2hex(random_bytes(32));"
    'token' => env('TUGAS_TOKEN', ''),

];
