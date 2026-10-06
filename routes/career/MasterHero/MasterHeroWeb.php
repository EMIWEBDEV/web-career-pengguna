<?php

use App\Http\Controllers\Career\MasterHero\MasterHeroController;
use Illuminate\Support\Facades\Route;

/*
| MASTER HERO — slide media hero landing page (gambar desktop/mobile + video).
| - Halaman (Inertia / SPA): GET /master-hero
| - Data & CRUD (JSON, ResponseHelper) di GROUP prefix api/v1 — BUKAN halaman Inertia.
| - Upload/hapus media per slot (desktop/mobile/poster/video) ke GCS.
| Rute PUBLIK untuk stream media (dipakai landing page) ada di MasterHeroPublicWeb.php.
*/

// Halaman Inertia
Route::get('/master-hero', [MasterHeroController::class, 'index'])->name('career.master-hero')->middleware('career.permission:masterHeroPage,VIEW');

// JSON (bukan halaman Inertia) — prefix api/v1
Route::prefix('api/v1')->name('career.api.master-hero.')->group(function () {
    Route::get('/master-hero', [MasterHeroController::class, 'list'])->name('list')->middleware('career.permission:masterHeroPage,VIEW');
    Route::post('/master-hero', [MasterHeroController::class, 'store'])->name('store')->middleware('career.permission:masterHeroPage,CREATE');
    Route::put('/master-hero/{id}', [MasterHeroController::class, 'update'])->name('update')->middleware('career.permission:masterHeroPage,EDIT');
    Route::patch('/master-hero/{id}/toggle', [MasterHeroController::class, 'toggle'])->name('toggle')->middleware('career.permission:masterHeroPage,EDIT');
    Route::delete('/master-hero/{id}', [MasterHeroController::class, 'destroy'])->name('destroy')->middleware('career.permission:masterHeroPage,DELETE');
    Route::post('/master-hero/{id}/media', [MasterHeroController::class, 'uploadMedia'])->name('media.upload')->middleware('career.permission:masterHeroPage,EDIT');
    Route::delete('/master-hero/{id}/media', [MasterHeroController::class, 'deleteMedia'])->name('media.delete')->middleware('career.permission:masterHeroPage,EDIT');
});
