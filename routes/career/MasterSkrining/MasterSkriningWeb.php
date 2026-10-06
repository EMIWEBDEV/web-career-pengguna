<?php

use App\Http\Controllers\Career\MasterSkrining\MasterSkriningController;
use Illuminate\Support\Facades\Route;

/*
| MASTER PHONE SCREENING — pustaka template pertanyaan skrining.
|
| Halaman Inertia /master-skrining + grup JSON api/v1, seizin masterSkriningPage.
|
| ── DUA JENIS ID DI JALUR YANG BERBEDA ──────────────────────────────────────
| {id}      → Id_Master_Skrining, kepala template (lintas versi)
| {versiId} → Id_Master_Skrining_Versi, satu versi tertentu
|
| Dibedakan namanya karena keduanya integer dan tertukar diam-diam: menyimpan
| pertanyaan ke id template alih-alih id versi akan mengenai versi yang salah
| tanpa satu pun pesan galat.
|
| ── KENAPA TIDAK ADA ROUTE UNTUK MENYUNTING VERSI TERBIT ────────────────────
| Karena memang tidak boleh ada. Menyunting template yang sudah dipakai selalu
| lewat `draf-baru` → sunting → `terbitkan`. Tidak menyediakan jalannya di
| lapisan rute adalah pengaman terakhir kalau lapisan lain kelak kebobolan.
*/

Route::get('/master-skrining', [MasterSkriningController::class, 'index'])
    ->name('career.master-skrining')
    ->middleware('career.permission:masterSkriningPage,VIEW');

Route::prefix('api/v1')->name('career.api.master-skrining.')->group(function () {
    // ── Baca ──
    Route::get('/master-skrining', [MasterSkriningController::class, 'list'])
        ->name('list')->middleware('career.permission:masterSkriningPage,VIEW');
    Route::get('/master-skrining/versi/{versiId}', [MasterSkriningController::class, 'pertanyaan'])
        ->name('pertanyaan')->middleware('career.permission:masterSkriningPage,VIEW');

    // ── Template ──
    Route::post('/master-skrining', [MasterSkriningController::class, 'store'])
        ->name('store')->middleware('career.permission:masterSkriningPage,CREATE');
    Route::put('/master-skrining/{id}', [MasterSkriningController::class, 'update'])
        ->name('update')->middleware('career.permission:masterSkriningPage,EDIT');
    Route::patch('/master-skrining/{id}/toggle', [MasterSkriningController::class, 'toggle'])
        ->name('toggle')->middleware('career.permission:masterSkriningPage,EDIT');
    Route::post('/master-skrining/{id}/duplikat', [MasterSkriningController::class, 'duplikat'])
        ->name('duplikat')->middleware('career.permission:masterSkriningPage,CREATE');
    Route::delete('/master-skrining/{id}', [MasterSkriningController::class, 'destroy'])
        ->name('destroy')->middleware('career.permission:masterSkriningPage,DELETE');

    // ── Versi ──
    //
    // `draf-baru` memakai izin CREATE, bukan EDIT: ia benar-benar membuat versi
    // baru, dan orang yang cuma boleh menyunting draf yang sudah ada tidak
    // otomatis boleh membuka revisi baru atas template yang sedang dipakai.
    Route::post('/master-skrining/{id}/draf-baru', [MasterSkriningController::class, 'drafBaru'])
        ->name('draf-baru')->middleware('career.permission:masterSkriningPage,CREATE');
    Route::put('/master-skrining/versi/{versiId}/pertanyaan', [MasterSkriningController::class, 'simpanPertanyaan'])
        ->name('simpan-pertanyaan')->middleware('career.permission:masterSkriningPage,EDIT');
    Route::post('/master-skrining/versi/{versiId}/terbitkan', [MasterSkriningController::class, 'terbitkan'])
        ->name('terbitkan')->middleware('career.permission:masterSkriningPage,EDIT');
    Route::delete('/master-skrining/versi/{versiId}', [MasterSkriningController::class, 'buangDraf'])
        ->name('buang-draf')->middleware('career.permission:masterSkriningPage,DELETE');

    // -- Bank Pertanyaan --
    //
    // Izinnya masterSkriningPage EDIT, bukan masterPertanyaanPage: MENARIK
    // pertanyaan dari pustaka adalah menyunting template, bukan menyunting
    // pustaka. Orang yang menyusun kuesioner tidak perlu berhak merombak
    // pertanyaan yang dipakai seluruh template hanya untuk memakainya.
    // Pencarian bank untuk pemilih di penyunting template. VIEW, bukan EDIT:
    // melihat pustaka bukan menyuntingnya.
    Route::get('/master-skrining/bank/cari', [MasterSkriningController::class, 'cariBank'])
        ->name('bank-cari')->middleware('career.permission:masterSkriningPage,VIEW');
    Route::post('/master-skrining/versi/{versiId}/dari-bank', [MasterSkriningController::class, 'dariBank'])
        ->name('dari-bank')->middleware('career.permission:masterSkriningPage,EDIT');
    Route::post('/master-skrining/versi/{versiId}/segarkan-bank', [MasterSkriningController::class, 'segarkanBank'])
        ->name('segarkan-bank')->middleware('career.permission:masterSkriningPage,EDIT');
});
