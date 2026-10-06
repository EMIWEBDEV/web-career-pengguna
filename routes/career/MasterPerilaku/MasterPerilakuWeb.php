<?php

use App\Http\Controllers\Career\MasterPerilaku\MasterPerilakuController;
use Illuminate\Support\Facades\Route;

/*
| MASTER PERILAKU
| - Halaman (Inertia / SPA): GET /master-perilaku
| - Data & CRUD (JSON, ResponseHelper) di GROUP prefix api/v1 — BUKAN halaman Inertia.
*/

// Halaman Inertia
Route::get('/master-perilaku', [MasterPerilakuController::class, 'index'])->name('career.master-perilaku')->middleware('career.permission:masterPerilakuPage,VIEW');

// JSON (bukan halaman Inertia) — prefix api/v1
Route::prefix('api/v1')->name('career.api.master-perilaku.')->group(function () {
    Route::get('/master-perilaku', [MasterPerilakuController::class, 'list'])->name('list')->middleware('career.permission:masterPerilakuPage,VIEW');
    Route::post('/master-perilaku', [MasterPerilakuController::class, 'store'])->name('store')->middleware('career.permission:masterPerilakuPage,CREATE');
    Route::put('/master-perilaku/{id}', [MasterPerilakuController::class, 'update'])->name('update')->middleware('career.permission:masterPerilakuPage,EDIT');
    Route::patch('/master-perilaku/{id}/toggle', [MasterPerilakuController::class, 'toggle'])->name('toggle')->middleware('career.permission:masterPerilakuPage,EDIT');
    Route::delete('/master-perilaku/{id}', [MasterPerilakuController::class, 'destroy'])->name('destroy')->middleware('career.permission:masterPerilakuPage,DELETE');
});
