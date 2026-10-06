<?php

use App\Http\Controllers\Career\Pendidikan\PendidikanController;
use Illuminate\Support\Facades\Route;

/*
| PENDIDIKAN (form kandidat) — cascade Jenjang → Jenis Institusi → Nama Kampus.
| Hanya perlu login (career.auth), BUKAN hak akses admin: dipakai pelamar.
*/
Route::middleware('career.auth')->prefix('api/v1/pendidikan')->name('career.api.pendidikan.')->group(function () {
    Route::get('/jenjang', [PendidikanController::class, 'jenjang'])->name('jenjang');
    Route::get('/jenis-institusi', [PendidikanController::class, 'jenisInstitusi'])->name('jenis');
    Route::get('/kampus', [PendidikanController::class, 'kampus'])->name('kampus');
    Route::get('/fakultas', [PendidikanController::class, 'fakultas'])->name('fakultas');
    Route::get('/prodi', [PendidikanController::class, 'prodi'])->name('prodi');
});
