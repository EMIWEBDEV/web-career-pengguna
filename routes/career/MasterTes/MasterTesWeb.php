<?php

use App\Http\Controllers\Career\MasterTes\MasterTesController;
use Illuminate\Support\Facades\Route;

/*
| MASTER JENIS TES (induk-detail). Halaman Inertia + JSON group api/v1.
*/
Route::get('/master-tes', [MasterTesController::class, 'index'])->name('career.master-tes')->middleware('career.permission:masterTesPage,VIEW');

Route::prefix('api/v1')->name('career.api.master-tes.')->group(function () {
    Route::get('/master-tes', [MasterTesController::class, 'list'])->name('list')->middleware('career.permission:masterTesPage,VIEW');
    Route::post('/master-tes', [MasterTesController::class, 'store'])->name('store')->middleware('career.permission:masterTesPage,CREATE');
    Route::put('/master-tes/{id}', [MasterTesController::class, 'update'])->name('update')->middleware('career.permission:masterTesPage,EDIT');
    Route::patch('/master-tes/{id}/toggle', [MasterTesController::class, 'toggle'])->name('toggle')->middleware('career.permission:masterTesPage,EDIT');
    Route::delete('/master-tes/{id}', [MasterTesController::class, 'destroy'])->name('destroy')->middleware('career.permission:masterTesPage,DELETE');
});
