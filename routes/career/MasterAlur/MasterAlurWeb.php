<?php

use App\Http\Controllers\Career\MasterAlur\MasterAlurController;
use Illuminate\Support\Facades\Route;

/*
| MASTER TAHAPAN SELEKSI / ALUR (induk-detail). Halaman Inertia + JSON group api/v1.
*/
Route::get('/master-alur', [MasterAlurController::class, 'index'])->name('career.master-alur')->middleware('career.permission:masterAlurPage,VIEW');

Route::prefix('api/v1')->name('career.api.master-alur.')->group(function () {
    Route::get('/master-alur', [MasterAlurController::class, 'list'])->name('list')->middleware('career.permission:masterAlurPage,VIEW');
    Route::post('/master-alur', [MasterAlurController::class, 'store'])->name('store')->middleware('career.permission:masterAlurPage,CREATE');
    Route::put('/master-alur/{id}', [MasterAlurController::class, 'update'])->name('update')->middleware('career.permission:masterAlurPage,EDIT');
    // Pratinjau akibat penyimpanan — dipanggil SEBELUM menyimpan, jadi izinnya
    // EDIT (yang tidak boleh menyimpan tidak perlu tahu dampaknya menyimpan).
    Route::get('/master-alur/{id}/dampak', [MasterAlurController::class, 'dampak'])->name('dampak')->middleware('career.permission:masterAlurPage,EDIT');
    Route::patch('/master-alur/{id}/toggle', [MasterAlurController::class, 'toggle'])->name('toggle')->middleware('career.permission:masterAlurPage,EDIT');
    Route::delete('/master-alur/{id}', [MasterAlurController::class, 'destroy'])->name('destroy')->middleware('career.permission:masterAlurPage,DELETE');
});
