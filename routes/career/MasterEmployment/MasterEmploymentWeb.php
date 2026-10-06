<?php

use App\Http\Controllers\Career\MasterEmployment\MasterEmploymentController;
use Illuminate\Support\Facades\Route;

/*
| MASTER EMPLOYMENT (JENIS IKATAN KERJA)
| - Halaman (Inertia / SPA): GET /master-employment
| - Data & CRUD (JSON, ResponseHelper) di GROUP prefix api/v1 — BUKAN halaman Inertia.
*/

// Halaman Inertia
Route::get('/master-employment', [MasterEmploymentController::class, 'index'])
    ->name('career.master-employment')
    ->middleware('career.permission:masterEmploymentPage,VIEW');

// JSON (bukan halaman Inertia) — prefix api/v1
Route::prefix('api/v1')->name('career.api.master-employment.')->group(function () {
    Route::get('/master-employment', [MasterEmploymentController::class, 'list'])->name('list')->middleware('career.permission:masterEmploymentPage,VIEW');
    Route::post('/master-employment', [MasterEmploymentController::class, 'store'])->name('store')->middleware('career.permission:masterEmploymentPage,CREATE');
    Route::put('/master-employment/{id}', [MasterEmploymentController::class, 'update'])->name('update')->middleware('career.permission:masterEmploymentPage,EDIT');
    Route::patch('/master-employment/{id}/toggle', [MasterEmploymentController::class, 'toggle'])->name('toggle')->middleware('career.permission:masterEmploymentPage,EDIT');
    Route::delete('/master-employment/{id}', [MasterEmploymentController::class, 'destroy'])->name('destroy')->middleware('career.permission:masterEmploymentPage,DELETE');
});
