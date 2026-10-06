<?php

use App\Http\Controllers\Career\MasterExperienceLevel\MasterExperienceLevelController;
use Illuminate\Support\Facades\Route;

/*
| MASTER EXPERIENCE LEVEL (TINGKAT PENGALAMAN)
| - Halaman (Inertia / SPA): GET /master-experience-level
| - Data & CRUD (JSON, ResponseHelper) di GROUP prefix api/v1 — BUKAN halaman Inertia.
*/

// Halaman Inertia
Route::get('/master-experience-level', [MasterExperienceLevelController::class, 'index'])
    ->name('career.master-experience-level')
    ->middleware('career.permission:masterExperienceLevelPage,VIEW');

// JSON (bukan halaman Inertia) — prefix api/v1
Route::prefix('api/v1')->name('career.api.master-experience-level.')->group(function () {
    Route::get('/master-experience-level', [MasterExperienceLevelController::class, 'list'])->name('list')->middleware('career.permission:masterExperienceLevelPage,VIEW');
    Route::post('/master-experience-level', [MasterExperienceLevelController::class, 'store'])->name('store')->middleware('career.permission:masterExperienceLevelPage,CREATE');
    Route::put('/master-experience-level/{id}', [MasterExperienceLevelController::class, 'update'])->name('update')->middleware('career.permission:masterExperienceLevelPage,EDIT');
    Route::patch('/master-experience-level/{id}/toggle', [MasterExperienceLevelController::class, 'toggle'])->name('toggle')->middleware('career.permission:masterExperienceLevelPage,EDIT');
    Route::delete('/master-experience-level/{id}', [MasterExperienceLevelController::class, 'destroy'])->name('destroy')->middleware('career.permission:masterExperienceLevelPage,DELETE');
});
