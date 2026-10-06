<?php

use App\Http\Controllers\Career\MasterWorkplace\MasterWorkplaceController;
use Illuminate\Support\Facades\Route;

/*
| MASTER WORKPLACE — tipe LOKASI kerja (On-site / Hybrid / Remote).
| - Halaman (Inertia / SPA): GET /master-workplace
| - Data & CRUD (JSON, ResponseHelper) di GROUP prefix api/v1.
| Kunci hak akses: masterWorkplacePage (N_WEB_CAREERS_Menu.Jenis_Page).
*/

Route::get('/master-workplace', [MasterWorkplaceController::class, 'index'])->name('career.master-workplace')->middleware('career.permission:masterWorkplacePage,VIEW');

Route::prefix('api/v1')->name('career.api.master-workplace.')->group(function () {
    Route::get('/master-workplace', [MasterWorkplaceController::class, 'list'])->name('list')->middleware('career.permission:masterWorkplacePage,VIEW');
    Route::post('/master-workplace', [MasterWorkplaceController::class, 'store'])->name('store')->middleware('career.permission:masterWorkplacePage,CREATE');
    Route::put('/master-workplace/{id}', [MasterWorkplaceController::class, 'update'])->name('update')->middleware('career.permission:masterWorkplacePage,EDIT');
    Route::patch('/master-workplace/{id}/toggle', [MasterWorkplaceController::class, 'toggle'])->name('toggle')->middleware('career.permission:masterWorkplacePage,EDIT');
    Route::delete('/master-workplace/{id}', [MasterWorkplaceController::class, 'destroy'])->name('destroy')->middleware('career.permission:masterWorkplacePage,DELETE');
});
