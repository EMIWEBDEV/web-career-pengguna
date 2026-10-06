<?php

use App\Http\Controllers\Career\AgendaSeleksi\AgendaSeleksiController;
use Illuminate\Support\Facades\Route;

/*
| AGENDA SELEKSI — konfirmasi kehadiran dari sisi tim
| - Halaman (Inertia): GET /karir/agenda-seleksi        agendaSeleksiPage VIEW
| - Daftar (JSON):     api/v1/karir/agenda-seleksi/*     agendaSeleksiPage VIEW
| - Rincian & aksi:    api/v1/karir/konfirmasi-jadwal/*  pelamarPage VIEW/EDIT
|
| Rincian & aksi memakai hak WORKLIST: tombolnya juga ada di drawer worklist,
| kandidatnya sama, dan satu tindakan tidak boleh punya dua gerbang berbeda.
| Lingkup kandidat (kategori + PIC loker) ditegakkan di controller.
*/

Route::get('/karir/agenda-seleksi', [AgendaSeleksiController::class, 'index'])
    ->name('career.agenda-seleksi')
    ->middleware('career.permission:agendaSeleksiPage,VIEW');

Route::prefix('api/v1/karir/agenda-seleksi')->name('career.api.agenda-seleksi.')->group(function () {
    Route::get('/perlu-tindakan', [AgendaSeleksiController::class, 'perluTindakan'])->name('perlu-tindakan')->middleware('career.permission:agendaSeleksiPage,VIEW');
    Route::get('/agenda', [AgendaSeleksiController::class, 'agenda'])->name('agenda')->middleware('career.permission:agendaSeleksiPage,VIEW');
});

Route::prefix('api/v1/karir/konfirmasi-jadwal')->name('career.api.konfirmasi-jadwal.')->group(function () {
    // Massal dulu — '/pengingat' & '/opsi-tunda' tidak boleh tertangkap '/{id}'.
    Route::post('/pengingat', [AgendaSeleksiController::class, 'pengingat'])->name('pengingat')->middleware('career.permission:pelamarPage,EDIT');
    Route::get('/opsi-tunda', [AgendaSeleksiController::class, 'opsiTunda'])->name('opsi-tunda')->middleware('career.permission:pelamarPage,EDIT');

    Route::post('/permintaan/{id}/setujui', [AgendaSeleksiController::class, 'setujui'])->name('setujui')->middleware('career.permission:pelamarPage,EDIT');
    Route::post('/permintaan/{id}/tawarkan', [AgendaSeleksiController::class, 'tawarkan'])->name('tawarkan')->middleware('career.permission:pelamarPage,EDIT');
    Route::post('/permintaan/{id}/tolak', [AgendaSeleksiController::class, 'tolak'])->name('tolak')->middleware('career.permission:pelamarPage,EDIT');

    Route::get('/{id}', [AgendaSeleksiController::class, 'detail'])->name('detail')->middleware('career.permission:pelamarPage,VIEW');
    Route::post('/{id}/catat', [AgendaSeleksiController::class, 'catat'])->name('catat')->middleware('career.permission:pelamarPage,EDIT');
    Route::post('/{id}/tunda', [AgendaSeleksiController::class, 'tunda'])->name('tunda')->middleware('career.permission:pelamarPage,EDIT');
    // Perbarui info penundaan (perkiraan tanggal, alasan, pesan) + kabari kandidat.
    Route::post('/{id}/tunda/perbarui', [AgendaSeleksiController::class, 'perbaruiTunda'])->name('tunda.perbarui')->middleware('career.permission:pelamarPage,EDIT');
});
