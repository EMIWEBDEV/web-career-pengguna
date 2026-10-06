<?php

use App\Http\Controllers\Career\MasterLokasi\MasterLokasiController;
use Illuminate\Support\Facades\Route;

/*
| MASTER LOKASI (kantor & vendor: klinik MCU, tempat wawancara)
| - Halaman (Inertia): GET /master-lokasi
| - Data & CRUD (JSON, ResponseHelper) di GROUP prefix api/v1.
|
| Dipakai penjadwalan tatap muka — rekruter MEMILIH lokasi berikut petanya,
| bukan mengetik alamat bebas yang tak bisa dibuka kandidat.
*/

// Halaman Inertia
Route::get('/master-lokasi', [MasterLokasiController::class, 'index'])
    ->name('career.master-lokasi')
    ->middleware('career.permission:masterLokasiPage,VIEW');

// JSON (bukan halaman Inertia) — prefix api/v1
Route::prefix('api/v1')->name('career.api.master-lokasi.')->group(function () {
    Route::get('/master-lokasi', [MasterLokasiController::class, 'list'])
        ->name('list')->middleware('career.permission:masterLokasiPage,VIEW');
    // Peruntukan (kantor / medis / …) berikut labelnya — dibaca jendela jadwal
    // supaya ia tak perlu tahu sendiri bahwa MEDIS berarti rumah sakit.
    Route::get('/master-lokasi/peruntukan', [MasterLokasiController::class, 'peruntukan'])
        ->name('peruntukan')->middleware('career.permission:masterLokasiPage,VIEW');
    Route::post('/master-lokasi', [MasterLokasiController::class, 'store'])
        ->name('store')->middleware('career.permission:masterLokasiPage,CREATE');
    Route::put('/master-lokasi/{id}', [MasterLokasiController::class, 'update'])
        ->name('update')->middleware('career.permission:masterLokasiPage,EDIT');
    Route::patch('/master-lokasi/{id}/toggle', [MasterLokasiController::class, 'toggle'])
        ->name('toggle')->middleware('career.permission:masterLokasiPage,EDIT');
    Route::delete('/master-lokasi/{id}', [MasterLokasiController::class, 'destroy'])
        ->name('destroy')->middleware('career.permission:masterLokasiPage,DELETE');
});
