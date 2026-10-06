<?php

use App\Http\Controllers\Career\BatasIsi\BatasIsiController;
use Illuminate\Support\Facades\Route;

/*
| BATAS PENGISIAN FORMULIR TAHAP — dari worklist Pelamar
| JSON (ResponseHelper) di prefix api/v1. Aturannya di App\Support\Career\BatasIsi.
|   EDIT  jadwal kolom per program & perubahan per kandidat (satuan / massal);
|         mengedit (boleh mundur) hanya SUPERADMIN — dijaga di controller
|   VIEW  riwayat perubahan batas satu tahap kandidat
*/
Route::prefix('api/v1/karir/batas-isi')->name('career.api.batas-isi.')->group(function () {
    Route::post('/program', [BatasIsiController::class, 'program'])->name('program')->middleware('career.permission:pelamarPage,EDIT');
    Route::post('/program/perpanjang', [BatasIsiController::class, 'perpanjang'])->name('program.perpanjang')->middleware('career.permission:pelamarPage,EDIT');
    Route::post('/program/lepas', [BatasIsiController::class, 'lepas'])->name('program.lepas')->middleware('career.permission:pelamarPage,EDIT');
    // Edit jadwal (boleh mundur) — hanya SUPERADMIN, dijaga di controller.
    Route::post('/program/ubah', [BatasIsiController::class, 'ubah'])->name('program.ubah')->middleware('career.permission:pelamarPage,EDIT');
    Route::get('/program/riwayat', [BatasIsiController::class, 'riwayatKolom'])->name('program.riwayat')->middleware('career.permission:pelamarPage,VIEW');
    Route::post('/kandidat', [BatasIsiController::class, 'kandidat'])->name('kandidat')->middleware('career.permission:pelamarPage,EDIT');
    Route::get('/riwayat/{id}', [BatasIsiController::class, 'riwayat'])->name('riwayat')->middleware('career.permission:pelamarPage,VIEW');
});
