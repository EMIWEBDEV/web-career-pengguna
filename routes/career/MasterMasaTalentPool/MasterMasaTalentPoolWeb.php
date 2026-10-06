<?php

use App\Http\Controllers\Career\MasterMasaTalentPool\MasterMasaTalentPoolController;
use Illuminate\Support\Facades\Route;

/*
| MASTER MASA BERLAKU TALENT POOL
| - Halaman (Inertia): GET /master-masa-talent-pool
| - Data & CRUD (JSON) di GROUP prefix api/v1.
*/

Route::get('/master-masa-talent-pool', [MasterMasaTalentPoolController::class, 'index'])->name('career.master-masa-talent-pool')->middleware('career.permission:masterMasaTalentPoolPage,VIEW');

Route::prefix('api/v1')->name('career.api.master-masa-talent-pool.')->group(function () {
    Route::get('/master-masa-talent-pool', [MasterMasaTalentPoolController::class, 'list'])->name('list')->middleware('career.permission:masterMasaTalentPoolPage,VIEW');
    Route::post('/master-masa-talent-pool', [MasterMasaTalentPoolController::class, 'store'])->name('store')->middleware('career.permission:masterMasaTalentPoolPage,CREATE');
    Route::put('/master-masa-talent-pool/{id}', [MasterMasaTalentPoolController::class, 'update'])->name('update')->middleware('career.permission:masterMasaTalentPoolPage,EDIT');
    Route::patch('/master-masa-talent-pool/{id}/toggle', [MasterMasaTalentPoolController::class, 'toggle'])->name('toggle')->middleware('career.permission:masterMasaTalentPoolPage,EDIT');
    Route::delete('/master-masa-talent-pool/{id}', [MasterMasaTalentPoolController::class, 'destroy'])->name('destroy')->middleware('career.permission:masterMasaTalentPoolPage,DELETE');
});
