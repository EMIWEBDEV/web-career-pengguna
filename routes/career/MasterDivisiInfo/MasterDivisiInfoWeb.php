<?php

use App\Http\Controllers\Career\MasterDivisiInfo\MasterDivisiInfoController;
use Illuminate\Support\Facades\Route;

/*
| MASTER INFO DIVISI — konten landing page per divisi/sub-divisi (HRIS).
| - Halaman (Inertia / SPA): GET /master-info-divisi
| - Data & upsert (JSON, ResponseHelper) di GROUP prefix api/v1.
| Catatan: tidak ada aksi DELETE data (satu baris per divisi, upsert) —
| hanya gambar per-slot yang bisa dihapus (aksi EDIT).
*/

// Halaman Inertia
Route::get('/master-info-divisi', [MasterDivisiInfoController::class, 'index'])->name('career.master-info-divisi')->middleware('career.permission:masterInfoDivisiPage,VIEW');

// JSON (bukan halaman Inertia) — prefix api/v1
Route::prefix('api/v1')->name('career.api.master-info-divisi.')->group(function () {
    Route::get('/master-info-divisi', [MasterDivisiInfoController::class, 'list'])->name('list')->middleware('career.permission:masterInfoDivisiPage,VIEW');
    Route::get('/master-info-divisi/{id}', [MasterDivisiInfoController::class, 'show'])->name('show')->middleware('career.permission:masterInfoDivisiPage,VIEW');
    Route::put('/master-info-divisi/sub/{id}', [MasterDivisiInfoController::class, 'upsertSub'])->name('sub.upsert')->middleware('career.permission:masterInfoDivisiPage,EDIT');
    Route::patch('/master-info-divisi/sub/{id}/toggle', [MasterDivisiInfoController::class, 'toggleSub'])->name('sub.toggle')->middleware('career.permission:masterInfoDivisiPage,EDIT');
    Route::put('/master-info-divisi/{id}', [MasterDivisiInfoController::class, 'upsertDivisi'])->name('upsert')->middleware('career.permission:masterInfoDivisiPage,EDIT');
    Route::patch('/master-info-divisi/{id}/toggle', [MasterDivisiInfoController::class, 'toggleDivisi'])->name('toggle')->middleware('career.permission:masterInfoDivisiPage,EDIT');
    Route::post('/master-info-divisi/{id}/gambar', [MasterDivisiInfoController::class, 'uploadImage'])->name('gambar.upload')->middleware('career.permission:masterInfoDivisiPage,EDIT');
    Route::delete('/master-info-divisi/{id}/gambar', [MasterDivisiInfoController::class, 'deleteImage'])->name('gambar.delete')->middleware('career.permission:masterInfoDivisiPage,EDIT');
});
