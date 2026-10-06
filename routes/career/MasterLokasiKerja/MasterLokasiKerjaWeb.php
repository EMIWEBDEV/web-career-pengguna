<?php

use App\Http\Controllers\Career\MasterLokasiKerja\MasterLokasiKerjaController;
use Illuminate\Support\Facades\Route;

/*
| MASTER LOKASI KERJA — kantor pusat & cabang per kota (tabel N_HRIS_Master_Lokasi).
| Bukan Master Lokasi (/master-lokasi) yang isinya kantor & vendor penjadwalan.
|
| - Halaman (Inertia / SPA): GET /master-lokasi-kerja
| - Data & CRUD (JSON, ResponseHelper) di GROUP prefix api/v1.
| Kunci hak akses: masterLokasiKerjaPage.
|
| Parameter {kode} adalah Kode_Lokasi (primary key alami, bukan id ter-hash).
| Pola dibatasi supaya tidak menelan segmen lain seperti "/toggle".
*/

Route::get('/master-lokasi-kerja', [MasterLokasiKerjaController::class, 'index'])->name('career.master-lokasi-kerja')->middleware('career.permission:masterLokasiKerjaPage,VIEW');

Route::prefix('api/v1')->name('career.api.master-lokasi-kerja.')->group(function () {
    Route::get('/master-lokasi-kerja', [MasterLokasiKerjaController::class, 'list'])->name('list')->middleware('career.permission:masterLokasiKerjaPage,VIEW');
    Route::post('/master-lokasi-kerja', [MasterLokasiKerjaController::class, 'store'])->name('store')->middleware('career.permission:masterLokasiKerjaPage,CREATE');
    Route::put('/master-lokasi-kerja/{kode}', [MasterLokasiKerjaController::class, 'update'])->name('update')->where('kode', '[A-Za-z0-9_-]+')->middleware('career.permission:masterLokasiKerjaPage,EDIT');
    Route::patch('/master-lokasi-kerja/{kode}/toggle', [MasterLokasiKerjaController::class, 'toggle'])->name('toggle')->where('kode', '[A-Za-z0-9_-]+')->middleware('career.permission:masterLokasiKerjaPage,EDIT');
    Route::delete('/master-lokasi-kerja/{kode}', [MasterLokasiKerjaController::class, 'destroy'])->name('destroy')->where('kode', '[A-Za-z0-9_-]+')->middleware('career.permission:masterLokasiKerjaPage,DELETE');
});
