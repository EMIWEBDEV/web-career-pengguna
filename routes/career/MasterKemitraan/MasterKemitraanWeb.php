<?php

use App\Http\Controllers\Career\MasterKemitraan\MasterKemitraanController;
use Illuminate\Support\Facades\Route;

/*
| MASTER KEMITRAAN / MoU
| - Halaman (Inertia / SPA): GET /master-kemitraan
| - Data & CRUD (JSON, ResponseHelper) di GROUP prefix api/v1 — BUKAN halaman Inertia.
| - TANPA toggle (status lifecycle diubah lewat modal).
*/

// Halaman Inertia
Route::get('/master-kemitraan', [MasterKemitraanController::class, 'index'])->name('career.master-kemitraan')->middleware('career.permission:masterKemitraanPage,VIEW');

// JSON (bukan halaman Inertia) — prefix api/v1
Route::prefix('api/v1')->name('career.api.master-kemitraan.')->group(function () {
    Route::get('/master-kemitraan', [MasterKemitraanController::class, 'list'])->name('list')->middleware('career.permission:masterKemitraanPage,VIEW');
    Route::post('/master-kemitraan', [MasterKemitraanController::class, 'store'])->name('store')->middleware('career.permission:masterKemitraanPage,CREATE');
    Route::put('/master-kemitraan/{id}', [MasterKemitraanController::class, 'update'])->name('update')->middleware('career.permission:masterKemitraanPage,EDIT');
    Route::delete('/master-kemitraan/{id}', [MasterKemitraanController::class, 'destroy'])->name('destroy')->middleware('career.permission:masterKemitraanPage,DELETE');
});
