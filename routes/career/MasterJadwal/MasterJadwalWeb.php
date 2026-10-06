<?php

use App\Http\Controllers\Career\MasterJadwal\MasterJadwalController;
use Illuminate\Support\Facades\Route;

/*
| MASTER JADWAL KEGIATAN (induk-detail). Halaman Inertia + JSON group api/v1.
*/
Route::get('/master-jadwal', [MasterJadwalController::class, 'index'])->name('career.master-jadwal')->middleware('career.permission:masterJadwalPage,VIEW');

Route::prefix('api/v1')->name('career.api.master-jadwal.')->group(function () {
    Route::get('/master-jadwal', [MasterJadwalController::class, 'list'])->name('list')->middleware('career.permission:masterJadwalPage,VIEW');
    Route::post('/master-jadwal', [MasterJadwalController::class, 'store'])->name('store')->middleware('career.permission:masterJadwalPage,CREATE');
    Route::post('/master-jadwal/{id}/duplikat', [MasterJadwalController::class, 'duplikat'])->name('duplikat')->middleware('career.permission:masterJadwalPage,CREATE');
    Route::put('/master-jadwal/{id}', [MasterJadwalController::class, 'update'])->name('update')->middleware('career.permission:masterJadwalPage,EDIT');
    Route::patch('/master-jadwal/{id}/toggle', [MasterJadwalController::class, 'toggle'])->name('toggle')->middleware('career.permission:masterJadwalPage,EDIT');
    Route::delete('/master-jadwal/{id}', [MasterJadwalController::class, 'destroy'])->name('destroy')->middleware('career.permission:masterJadwalPage,DELETE');
});
