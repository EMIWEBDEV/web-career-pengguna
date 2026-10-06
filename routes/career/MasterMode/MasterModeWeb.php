<?php

use App\Http\Controllers\Career\MasterMode\MasterModeController;
use Illuminate\Support\Facades\Route;

/*
| MASTER MODE PELAKSANAAN
| - Halaman (Inertia / SPA): GET /master-mode
| - Data & CRUD (JSON, ResponseHelper) di GROUP prefix api/v1 — BUKAN halaman Inertia.
*/

// Halaman Inertia
Route::get('/master-mode', [MasterModeController::class, 'index'])->name('career.master-mode')->middleware('career.permission:masterModePage,VIEW');

// JSON (bukan halaman Inertia) — prefix api/v1
Route::prefix('api/v1')->name('career.api.master-mode.')->group(function () {
    Route::get('/master-mode', [MasterModeController::class, 'list'])->name('list')->middleware('career.permission:masterModePage,VIEW');
    Route::post('/master-mode', [MasterModeController::class, 'store'])->name('store')->middleware('career.permission:masterModePage,CREATE');
    Route::put('/master-mode/{id}', [MasterModeController::class, 'update'])->name('update')->middleware('career.permission:masterModePage,EDIT');
    Route::patch('/master-mode/{id}/toggle', [MasterModeController::class, 'toggle'])->name('toggle')->middleware('career.permission:masterModePage,EDIT');
    Route::delete('/master-mode/{id}', [MasterModeController::class, 'destroy'])->name('destroy')->middleware('career.permission:masterModePage,DELETE');
});
