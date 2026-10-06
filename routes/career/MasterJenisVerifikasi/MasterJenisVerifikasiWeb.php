<?php

use App\Http\Controllers\Career\MasterJenisVerifikasi\MasterJenisVerifikasiController;
use Illuminate\Support\Facades\Route;

/*
| MASTER JENIS VERIFIKASI — komponen background check (ijazah, riwayat kerja,
| catatan hukum, …).
|
| Halaman & CRUD-nya berizin masterJenisVerifikasiPage. Daftar komponen yang
| dipakai layar Worklist TIDAK lewat sini: ia ikut dalam payload aktivitas,
| supaya orang yang sedang mengerjakan pemeriksaan tidak perlu berhak menyunting
| kebijakan hanya untuk melihat komponen apa saja yang berlaku.
*/

Route::get('/master-jenis-verifikasi', [MasterJenisVerifikasiController::class, 'index'])
    ->name('career.master-jenis-verifikasi')
    ->middleware('career.permission:masterJenisVerifikasiPage,VIEW');

Route::prefix('api/v1')->name('career.api.master-jenis-verifikasi.')->group(function () {
    Route::get('/master-jenis-verifikasi', [MasterJenisVerifikasiController::class, 'list'])->name('list')->middleware('career.permission:masterJenisVerifikasiPage,VIEW');
    Route::post('/master-jenis-verifikasi', [MasterJenisVerifikasiController::class, 'store'])->name('store')->middleware('career.permission:masterJenisVerifikasiPage,CREATE');
    Route::put('/master-jenis-verifikasi/{id}', [MasterJenisVerifikasiController::class, 'update'])->name('update')->middleware('career.permission:masterJenisVerifikasiPage,EDIT');
    Route::patch('/master-jenis-verifikasi/{id}/toggle', [MasterJenisVerifikasiController::class, 'toggle'])->name('toggle')->middleware('career.permission:masterJenisVerifikasiPage,EDIT');
    Route::delete('/master-jenis-verifikasi/{id}', [MasterJenisVerifikasiController::class, 'destroy'])->name('destroy')->middleware('career.permission:masterJenisVerifikasiPage,DELETE');
});
