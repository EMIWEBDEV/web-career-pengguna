<?php

use App\Http\Controllers\Career\MasterSlaMpp\MasterSlaMppController;
use Illuminate\Support\Facades\Route;

/*
| MASTER SLA MPP — "level ini harus tuntas dalam berapa hari kerja"
| - Halaman (Inertia / SPA): GET /master-sla-mpp
| - Data & CRUD (JSON, ResponseHelper) di GROUP prefix api/v1.
|
| `hitung` dipakai borang MPP saat level dipilih: ia yang mengunci pemilih
| tanggalnya. Izinnya VIEW dan menempel pada halaman MPP — bukan pada halaman
| master ini — sebab yang memanggilnya orang yang sedang MEMBUAT MPP, dan ia
| tidak harus punya hak menyunting kebijakan SLA untuk sekadar tahu tenggatnya.
*/

// Halaman Inertia
Route::get('/master-sla-mpp', [MasterSlaMppController::class, 'index'])
    ->name('career.master-sla-mpp')
    ->middleware('career.permission:masterSlaMppPage,VIEW');

// JSON (bukan halaman Inertia) — prefix api/v1
Route::prefix('api/v1')->name('career.api.master-sla-mpp.')->group(function () {
    Route::get('/master-sla-mpp', [MasterSlaMppController::class, 'list'])->name('list')->middleware('career.permission:masterSlaMppPage,VIEW');
    Route::post('/master-sla-mpp', [MasterSlaMppController::class, 'store'])->name('store')->middleware('career.permission:masterSlaMppPage,CREATE');
    Route::put('/master-sla-mpp/{id}', [MasterSlaMppController::class, 'update'])->name('update')->middleware('career.permission:masterSlaMppPage,EDIT');
    Route::patch('/master-sla-mpp/{id}/toggle', [MasterSlaMppController::class, 'toggle'])->name('toggle')->middleware('career.permission:masterSlaMppPage,EDIT');
    Route::delete('/master-sla-mpp/{id}', [MasterSlaMppController::class, 'destroy'])->name('destroy')->middleware('career.permission:masterSlaMppPage,DELETE');

    // Dipakai borang MPP — lihat catatan di atas.
    Route::get('/master-sla-mpp/hitung', [MasterSlaMppController::class, 'hitung'])->name('hitung')->middleware('career.permission:masterMppPage,VIEW');
});
