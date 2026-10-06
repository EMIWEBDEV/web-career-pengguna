<?php

use App\Http\Controllers\Career\PembukaanProgram\PembukaanProgramController;
use Illuminate\Support\Facades\Route;

/*
| PEMBUKAAN PROGRAM. Halaman Inertia /karir/pembukaan + JSON group api/v1.
*/
Route::get('/karir/pembukaan', [PembukaanProgramController::class, 'index'])->name('career.pembukaan')->middleware('career.permission:pembukaanPage,VIEW');

Route::prefix('api/v1')->name('career.api.pembukaan.')->group(function () {
    Route::get('/pembukaan', [PembukaanProgramController::class, 'list'])->name('list')->middleware('career.permission:pembukaanPage,VIEW');
    // Daftar program + detail untuk kartu picker di modal "Buka Program".
    Route::get('/pembukaan/programs', [PembukaanProgramController::class, 'programs'])->name('programs')->middleware('career.permission:pembukaanPage,VIEW');
    // Angka satu terbitan (kartu, deret harian, corong, kuota, sumber).
    Route::get('/pembukaan/{id}/analitik', [PembukaanProgramController::class, 'analitik'])->name('analitik')->middleware('career.permission:pembukaanPage,VIEW');
    // Isi MPP satu loker (panel detail). Izinnya pembukaanPage, bukan
    // masterMppPage — lihat App\Support\Career\DetailMppLoker.
    Route::get('/pembukaan/{id}/loker/{posisiId}', [PembukaanProgramController::class, 'loker'])->name('loker')->middleware('career.permission:pembukaanPage,VIEW')->whereNumber('posisiId');
    Route::post('/pembukaan', [PembukaanProgramController::class, 'store'])->name('store')->middleware('career.permission:pembukaanPage,CREATE');
    Route::put('/pembukaan/{id}', [PembukaanProgramController::class, 'update'])->name('update')->middleware('career.permission:pembukaanPage,EDIT');
    Route::patch('/pembukaan/{id}/toggle', [PembukaanProgramController::class, 'toggle'])->name('toggle')->middleware('career.permission:pembukaanPage,EDIT');
    // Sakelar per loker (satu atau sekaligus). Izinnya EDIT, sama dengan
    // sakelar induknya: keduanya keputusan publikasi yang sama besarnya.
    Route::patch('/pembukaan/{id}/posisi', [PembukaanProgramController::class, 'togglePosisi'])->name('posisi.toggle')->middleware('career.permission:pembukaanPage,EDIT');
    Route::delete('/pembukaan/{id}', [PembukaanProgramController::class, 'destroy'])->name('destroy')->middleware('career.permission:pembukaanPage,DELETE');
});
