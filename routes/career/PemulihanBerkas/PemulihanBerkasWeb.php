<?php

use App\Http\Controllers\Career\PemulihanBerkas\PemulihanBerkasController;
use Illuminate\Support\Facades\Route;

/*
| PEMULIHAN BERKAS — admin mengunggah berkas kandidat yang hilang
| - Halaman (Inertia / SPA): GET /karir/pemulihan-berkas
| - Data & unggah (JSON, ResponseHelper) di GROUP prefix api/v1.
|
| Pembagian hak (aksi khusus TAMBAH_MANUAL & TIMPA diberikan lewat Manajemen
| Hak Akses — bawaannya ke SUPERADMIN, lihat docs/28-09-2026):
|   VIEW           daftar rekomendasi sistem + membuka berkas yang sudah ada
|   CREATE         mengunggah untuk isian yang direkomendasikan sistem
|   TAMBAH_MANUAL  mencari kandidat & isian di luar rekomendasi
|   TIMPA          diperiksa DI DALAM controller: ia hanya membuka jalan untuk
|                  isian yang sudah berberkas, bukan endpoint tersendiri.
*/

// Halaman Inertia
Route::get('/karir/pemulihan-berkas', [PemulihanBerkasController::class, 'index'])
    ->name('career.pemulihan-berkas')
    ->middleware('career.permission:pemulihanBerkasPage,VIEW');

// JSON (bukan halaman Inertia) — prefix api/v1
Route::prefix('api/v1/karir/pemulihan-berkas')->name('career.api.pemulihan-berkas.')->group(function () {
    Route::get('/daftar', [PemulihanBerkasController::class, 'daftar'])->name('daftar')->middleware('career.permission:pemulihanBerkasPage,VIEW');
    Route::get('/berkas/{id}', [PemulihanBerkasController::class, 'berkas'])->name('berkas')->middleware('career.permission:pemulihanBerkasPage,VIEW');
    Route::post('/unggah', [PemulihanBerkasController::class, 'unggah'])->name('unggah')->middleware('career.permission:pemulihanBerkasPage,CREATE');

    Route::get('/kandidat', [PemulihanBerkasController::class, 'kandidat'])->name('kandidat')->middleware('career.permission:pemulihanBerkasPage,TAMBAH_MANUAL');
    Route::get('/kandidat/{id}/isian', [PemulihanBerkasController::class, 'isian'])->name('isian')->middleware('career.permission:pemulihanBerkasPage,TAMBAH_MANUAL');
});
