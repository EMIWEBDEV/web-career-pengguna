<?php

use App\Http\Controllers\Career\MasterJenisInstitusi\MasterJenisInstitusiController;
use Illuminate\Support\Facades\Route;

/*
| MASTER JENIS INSTITUSI PENDIDIKAN — halaman Inertia + CRUD JSON (prefix api/v1).
*/
Route::get('/master-jenis-institusi', [MasterJenisInstitusiController::class, 'index'])->name('career.master-jenis-institusi')->middleware('career.permission:masterJenisInstitusiPage,VIEW');

Route::prefix('api/v1')->name('career.api.master-jenis-institusi.')->group(function () {
    Route::get('/master-jenis-institusi', [MasterJenisInstitusiController::class, 'list'])->name('list')->middleware('career.permission:masterJenisInstitusiPage,VIEW');
    Route::post('/master-jenis-institusi', [MasterJenisInstitusiController::class, 'store'])->name('store')->middleware('career.permission:masterJenisInstitusiPage,CREATE');
    Route::put('/master-jenis-institusi/{id}', [MasterJenisInstitusiController::class, 'update'])->name('update')->middleware('career.permission:masterJenisInstitusiPage,EDIT');
    Route::patch('/master-jenis-institusi/{id}/toggle', [MasterJenisInstitusiController::class, 'toggle'])->name('toggle')->middleware('career.permission:masterJenisInstitusiPage,EDIT');
    Route::delete('/master-jenis-institusi/{id}', [MasterJenisInstitusiController::class, 'destroy'])->name('destroy')->middleware('career.permission:masterJenisInstitusiPage,DELETE');
});
