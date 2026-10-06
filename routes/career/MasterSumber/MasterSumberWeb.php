<?php

use App\Http\Controllers\Career\MasterSumber\MasterSumberController;
use Illuminate\Support\Facades\Route;

/*
| MASTER SUMBER KANDIDAT
| - Halaman (Inertia / SPA): GET /master-sumber
| - Data & CRUD (JSON, ResponseHelper) di GROUP prefix api/v1 — BUKAN halaman Inertia.
*/

// Halaman Inertia
Route::get('/master-sumber', [MasterSumberController::class, 'index'])->name('career.master-sumber')->middleware('career.permission:masterSumberPage,VIEW');

// JSON (bukan halaman Inertia) — prefix api/v1
Route::prefix('api/v1')->name('career.api.master-sumber.')->group(function () {
    Route::get('/master-sumber', [MasterSumberController::class, 'list'])->name('list')->middleware('career.permission:masterSumberPage,VIEW');
    Route::post('/master-sumber', [MasterSumberController::class, 'store'])->name('store')->middleware('career.permission:masterSumberPage,CREATE');
    Route::put('/master-sumber/{id}', [MasterSumberController::class, 'update'])->name('update')->middleware('career.permission:masterSumberPage,EDIT');
    Route::patch('/master-sumber/{id}/toggle', [MasterSumberController::class, 'toggle'])->name('toggle')->middleware('career.permission:masterSumberPage,EDIT');
    Route::delete('/master-sumber/{id}', [MasterSumberController::class, 'destroy'])->name('destroy')->middleware('career.permission:masterSumberPage,DELETE');
});
