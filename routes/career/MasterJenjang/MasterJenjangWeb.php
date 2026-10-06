<?php

use App\Http\Controllers\Career\MasterJenjang\MasterJenjangController;
use Illuminate\Support\Facades\Route;

/*
| MASTER JENJANG PENDIDIKAN — halaman Inertia + CRUD JSON (prefix api/v1).
*/
Route::get('/master-jenjang', [MasterJenjangController::class, 'index'])->name('career.master-jenjang')->middleware('career.permission:masterJenjangPage,VIEW');

Route::prefix('api/v1')->name('career.api.master-jenjang.')->group(function () {
    Route::get('/master-jenjang', [MasterJenjangController::class, 'list'])->name('list')->middleware('career.permission:masterJenjangPage,VIEW');
    Route::post('/master-jenjang', [MasterJenjangController::class, 'store'])->name('store')->middleware('career.permission:masterJenjangPage,CREATE');
    Route::put('/master-jenjang/{id}', [MasterJenjangController::class, 'update'])->name('update')->middleware('career.permission:masterJenjangPage,EDIT');
    Route::patch('/master-jenjang/{id}/toggle', [MasterJenjangController::class, 'toggle'])->name('toggle')->middleware('career.permission:masterJenjangPage,EDIT');
    Route::delete('/master-jenjang/{id}', [MasterJenjangController::class, 'destroy'])->name('destroy')->middleware('career.permission:masterJenjangPage,DELETE');
});
