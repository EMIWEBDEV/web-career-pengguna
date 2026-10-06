<?php

use App\Http\Controllers\Career\MasterModeKeputusan\MasterModeKeputusanController;
use Illuminate\Support\Facades\Route;

/*
| MASTER MODE KEPUTUSAN
| - Halaman (Inertia / SPA): GET /master-mode-keputusan
| - Data & CRUD (JSON, ResponseHelper) di GROUP prefix api/v1.
*/

Route::get('/master-mode-keputusan', [MasterModeKeputusanController::class, 'index'])->name('career.master-mode-keputusan')->middleware('career.permission:masterModeKeputusanPage,VIEW');

Route::prefix('api/v1')->name('career.api.master-mode-keputusan.')->group(function () {
    Route::get('/master-mode-keputusan', [MasterModeKeputusanController::class, 'list'])->name('list')->middleware('career.permission:masterModeKeputusanPage,VIEW');
    Route::post('/master-mode-keputusan', [MasterModeKeputusanController::class, 'store'])->name('store')->middleware('career.permission:masterModeKeputusanPage,CREATE');
    Route::put('/master-mode-keputusan/{id}', [MasterModeKeputusanController::class, 'update'])->name('update')->middleware('career.permission:masterModeKeputusanPage,EDIT');
    Route::patch('/master-mode-keputusan/{id}/toggle', [MasterModeKeputusanController::class, 'toggle'])->name('toggle')->middleware('career.permission:masterModeKeputusanPage,EDIT');
    Route::delete('/master-mode-keputusan/{id}', [MasterModeKeputusanController::class, 'destroy'])->name('destroy')->middleware('career.permission:masterModeKeputusanPage,DELETE');
});
