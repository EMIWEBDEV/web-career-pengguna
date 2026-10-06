<?php

use App\Http\Controllers\Career\MasterBenefit\MasterBenefitController;
use Illuminate\Support\Facades\Route;

/*
| MASTER BENEFIT — fasilitas & tunjangan yang dipasang pada lowongan MPP.
| - Halaman (Inertia / SPA): GET /master-benefit
| - Data & CRUD (JSON, ResponseHelper) di GROUP prefix api/v1.
| Kunci hak akses: masterBenefitPage (N_WEB_CAREERS_Menu.Jenis_Page).
*/

Route::get('/master-benefit', [MasterBenefitController::class, 'index'])->name('career.master-benefit')->middleware('career.permission:masterBenefitPage,VIEW');

Route::prefix('api/v1')->name('career.api.master-benefit.')->group(function () {
    Route::get('/master-benefit', [MasterBenefitController::class, 'list'])->name('list')->middleware('career.permission:masterBenefitPage,VIEW');
    Route::post('/master-benefit', [MasterBenefitController::class, 'store'])->name('store')->middleware('career.permission:masterBenefitPage,CREATE');
    Route::put('/master-benefit/{id}', [MasterBenefitController::class, 'update'])->name('update')->middleware('career.permission:masterBenefitPage,EDIT');
    Route::patch('/master-benefit/{id}/toggle', [MasterBenefitController::class, 'toggle'])->name('toggle')->middleware('career.permission:masterBenefitPage,EDIT');
    Route::delete('/master-benefit/{id}', [MasterBenefitController::class, 'destroy'])->name('destroy')->middleware('career.permission:masterBenefitPage,DELETE');
});
