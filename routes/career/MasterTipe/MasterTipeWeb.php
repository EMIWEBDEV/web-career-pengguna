<?php

use App\Http\Controllers\Career\MasterTipe\MasterTipeController;
use Illuminate\Support\Facades\Route;

/*
| MASTER TIPE TAHAP
| - Halaman (Inertia / SPA): GET /master-tipe
| - Data & CRUD (JSON, ResponseHelper) di GROUP prefix api/v1 — BUKAN halaman Inertia.
*/

// Halaman Inertia
Route::get('/master-tipe', [MasterTipeController::class, 'index'])->name('career.master-tipe')->middleware('career.permission:masterTipePage,VIEW');

// JSON (bukan halaman Inertia) — prefix api/v1
Route::prefix('api/v1')->name('career.api.master-tipe.')->group(function () {
    Route::get('/master-tipe', [MasterTipeController::class, 'list'])->name('list')->middleware('career.permission:masterTipePage,VIEW');
    Route::post('/master-tipe', [MasterTipeController::class, 'store'])->name('store')->middleware('career.permission:masterTipePage,CREATE');
    Route::put('/master-tipe/{id}', [MasterTipeController::class, 'update'])->name('update')->middleware('career.permission:masterTipePage,EDIT');
    Route::patch('/master-tipe/{id}/toggle', [MasterTipeController::class, 'toggle'])->name('toggle')->middleware('career.permission:masterTipePage,EDIT');
    Route::delete('/master-tipe/{id}', [MasterTipeController::class, 'destroy'])->name('destroy')->middleware('career.permission:masterTipePage,DELETE');
});
