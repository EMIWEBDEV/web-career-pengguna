<?php

use App\Http\Controllers\Career\MasterTalent\MasterTalentController;
use Illuminate\Support\Facades\Route;

/*
| MASTER TALENT ACQUISITION
| - Halaman (Inertia / SPA): GET /master-talent
| - Data & CRUD (JSON, ResponseHelper) di GROUP prefix api/v1 — BUKAN halaman Inertia.
*/

// Halaman Inertia
Route::get('/master-talent', [MasterTalentController::class, 'index'])->name('career.master-talent')->middleware('career.permission:masterTalentPage,VIEW');

// JSON (bukan halaman Inertia) — prefix api/v1
Route::prefix('api/v1')->name('career.api.master-talent.')->group(function () {
    Route::get('/master-talent', [MasterTalentController::class, 'list'])->name('list')->middleware('career.permission:masterTalentPage,VIEW');
    Route::post('/master-talent', [MasterTalentController::class, 'store'])->name('store')->middleware('career.permission:masterTalentPage,CREATE');
    Route::put('/master-talent/{id}', [MasterTalentController::class, 'update'])->name('update')->middleware('career.permission:masterTalentPage,EDIT');
    Route::patch('/master-talent/{id}/toggle', [MasterTalentController::class, 'toggle'])->name('toggle')->middleware('career.permission:masterTalentPage,EDIT');
    Route::delete('/master-talent/{id}', [MasterTalentController::class, 'destroy'])->name('destroy')->middleware('career.permission:masterTalentPage,DELETE');
});
