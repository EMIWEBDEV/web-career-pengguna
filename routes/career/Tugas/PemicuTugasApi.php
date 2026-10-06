<?php

use App\Http\Controllers\Career\Tugas\PemicuTugasController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Pemicu tugas terjadwal — dipanggil Cloud Scheduler
|--------------------------------------------------------------------------
| Cloud Tasks tidak punya cron, dan Cloud Run tidak punya tempat menjalankan
| `php artisan schedule:run`. Cloud Scheduler-lah yang menekan tombolnya pada
| jam yang ditentukan; berkas ini pintu masuknya (pola cat-evo-pembaharuan).
|
| Berada di grup 'api' (routes/api.php) sehingga BEBAS CSRF dan tanpa sesi —
| pemanggilnya layanan luar. Pengamanannya kunci rahasia di header, diperiksa
| di controller.
|
| Contoh:
|   POST https://<web-careers>/api/tugas/pengingat-konfirmasi
|   Header: X-Tugas-Token: <isi TUGAS_TOKEN>
*/

Route::post('/tugas/{tugas}', [PemicuTugasController::class, 'jalankan'])
    ->where('tugas', '[a-z-]+')
    ->name('tugas.jalankan');
