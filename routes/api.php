<?php

use App\Http\Controllers\TugasController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — PROJECT: WEB CAREERS PENGGUNA
|--------------------------------------------------------------------------
| API kandidat memakai sesi web (routes/pengguna/*.php). Di sini hanya pemicu
| tugas berkala dari Cloud Scheduler — bertoken, tanpa sesi.
*/

// Penyapu Outbox tiap menit: terbitkan peristiwa yang tertinggal ke Pub/Sub.
Route::post('/tugas/terbit-outbox', [TugasController::class, 'terbitOutbox'])
    ->middleware('throttle:30,1')
    ->name('tugas.terbit-outbox');
