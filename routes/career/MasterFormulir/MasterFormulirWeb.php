<?php

use App\Http\Controllers\Career\MasterFormulir\MasterFormulirController;
use Illuminate\Support\Facades\Route;

/*
| MASTER FORMULIR (katalog). Halaman Inertia /master-formulir + JSON group api/v1.
|
| Skema pertanyaan formulir dinamis DISIMPAN di database, versi per versi, di
| N_WEB_CAREERS_Master_Formulir_Versi (kolom Schema_Json) — store/update
| menerima payload schema, publish menaikkan status versi jadi PUBLISHED.
| Komponen_Kode lama (Formulir1.vue, Formulir2.vue, dst.) hanya dibaca untuk
| kompatibilitas data legacy, tidak lagi ditulis oleh master baru.
*/
Route::get('/master-formulir', [MasterFormulirController::class, 'index'])->name('career.master-formulir')->middleware('career.permission:masterFormulirPage,VIEW');

Route::prefix('api/v1')->name('career.api.master-formulir.')->group(function () {
    Route::get('/master-formulir', [MasterFormulirController::class, 'list'])->name('list')->middleware('career.permission:masterFormulirPage,VIEW');
    Route::post('/master-formulir', [MasterFormulirController::class, 'store'])->name('store')->middleware('career.permission:masterFormulirPage,CREATE');
    Route::put('/master-formulir/{id}', [MasterFormulirController::class, 'update'])->name('update')->middleware('career.permission:masterFormulirPage,EDIT');
    Route::post('/master-formulir/{id}/publish', [MasterFormulirController::class, 'publish'])->name('publish')->middleware('career.permission:masterFormulirPage,EDIT');
    Route::post('/master-formulir/{id}/duplicate', [MasterFormulirController::class, 'duplicate'])->name('duplicate')->middleware('career.permission:masterFormulirPage,CREATE');
    Route::patch('/master-formulir/{id}/toggle', [MasterFormulirController::class, 'toggle'])->name('toggle')->middleware('career.permission:masterFormulirPage,EDIT');
    Route::delete('/master-formulir/{id}', [MasterFormulirController::class, 'destroy'])->name('destroy')->middleware('career.permission:masterFormulirPage,DELETE');
});
