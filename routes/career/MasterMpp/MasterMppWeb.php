<?php

use App\Http\Controllers\Career\MasterMpp\MasterMppController;
use Illuminate\Support\Facades\Route;

/*
| MASTER MPP (Manpower Planning) — HRIS_Transaksi_GForm + N_WEB_CAREERS_Detail_MPP
| - Halaman (Inertia / SPA): GET /master-mpp
| - Data & CRUD (JSON, ResponseHelper) di GROUP prefix api/v1 — BUKAN halaman Inertia.
| - TIDAK ADA route hapus — lihat catatan kelas controller (Batalkan, bukan hapus).
*/

// Halaman Inertia
Route::get('/master-mpp', [MasterMppController::class, 'index'])
    ->name('career.master-mpp')
    ->middleware('career.permission:masterMppPage,VIEW');

// JSON (bukan halaman Inertia) — prefix api/v1
Route::prefix('api/v1')->name('career.api.master-mpp.')->group(function () {
    Route::get('/master-mpp', [MasterMppController::class, 'list'])->name('list')->middleware('career.permission:masterMppPage,VIEW');
    Route::post('/master-mpp', [MasterMppController::class, 'store'])->name('store')->middleware('career.permission:masterMppPage,CREATE');
    Route::put('/master-mpp/{no}', [MasterMppController::class, 'update'])->name('update')->middleware('career.permission:masterMppPage,EDIT');
    Route::patch('/master-mpp/{no}/batalkan', [MasterMppController::class, 'batalkan'])->name('batalkan')->middleware('career.permission:masterMppPage,EDIT');
    Route::patch('/master-mpp/{no}/selesai', [MasterMppController::class, 'selesai'])->name('selesai')->middleware('career.permission:masterMppPage,EDIT');

    // PERPANJANG SLA — menggeser tenggat, dengan alasan yang wajib ditulis.
    // Izinnya EDIT, sama dengan Batalkan/Selesai: ketiganya mengubah keadaan MPP
    // yang sudah ada, bukan membuat yang baru.
    Route::post('/master-mpp/{no}/perpanjang-sla', [MasterMppController::class, 'perpanjang'])->name('perpanjang-sla')->middleware('career.permission:masterMppPage,EDIT');

    // Kandidat yang sudah DITERIMA pada sebuah MPP — tab "Kandidat" di panel
    // kanan. Didefinisikan SEBELUM route {no} agar tidak tertangkap sebagai detail.
    Route::get('/master-mpp/{no}/kandidat', [MasterMppController::class, 'kandidat'])->name('kandidat')->middleware('career.permission:masterMppPage,VIEW');

    // Profil kandidat — BACA SAJA. Tanpa tombol aksi apa pun; keputusan
    // kandidat tetap milik worklist. Lihat MasterMppController::kandidatDetail().
    Route::get('/master-mpp/{no}/kandidat/{id}', [MasterMppController::class, 'kandidatDetail'])->name('kandidat.detail')->middleware('career.permission:masterMppPage,VIEW');
    Route::get('/master-mpp/{no}/berkas/{id}', [MasterMppController::class, 'berkasKandidat'])->name('kandidat.berkas')->middleware('career.permission:masterMppPage,VIEW');

    // Opsi dropdown — folder sendiri, tidak menyentuh CareerAdminController::options().
    // DIDEFINISIKAN SEBELUM route {no} agar segmen 'opsi' tidak tertangkap sebagai detail.
    Route::prefix('/master-mpp/opsi')->name('opsi.')->group(function () {
        Route::get('/divisi', [MasterMppController::class, 'opsiDivisi'])->name('divisi')->middleware('career.permission:masterMppPage,VIEW');
        Route::get('/sub-divisi', [MasterMppController::class, 'opsiSubDivisi'])->name('sub-divisi')->middleware('career.permission:masterMppPage,VIEW');
        Route::get('/level', [MasterMppController::class, 'opsiLevel'])->name('level')->middleware('career.permission:masterMppPage,VIEW');
        Route::get('/jabatan', [MasterMppController::class, 'opsiJabatan'])->name('jabatan')->middleware('career.permission:masterMppPage,VIEW');
        Route::get('/lokasi', [MasterMppController::class, 'opsiLokasi'])->name('lokasi')->middleware('career.permission:masterMppPage,VIEW');
        Route::get('/karyawan', [MasterMppController::class, 'opsiKaryawan'])->name('karyawan')->middleware('career.permission:masterMppPage,VIEW');
        Route::get('/klasifikasi', [MasterMppController::class, 'opsiKlasifikasi'])->name('klasifikasi')->middleware('career.permission:masterMppPage,VIEW');
        Route::get('/filter', [MasterMppController::class, 'opsiFilter'])->name('filter')->middleware('career.permission:masterMppPage,VIEW');
        Route::get('/skill', [MasterMppController::class, 'opsiSkill'])->name('skill')->middleware('career.permission:masterMppPage,VIEW');
        Route::get('/benefit', [MasterMppController::class, 'opsiBenefit'])->name('benefit')->middleware('career.permission:masterMppPage,VIEW');
    });

    // Detail satu MPP — panel off-canvas (baca) + prefill form Ubah (termasuk
    // Tanggung Jawab/Persyaratan/Skill/Benefit, sekarang bisa diedit lewat store()/update()).
    Route::get('/master-mpp/{no}', [MasterMppController::class, 'detail'])->name('detail')->middleware('career.permission:masterMppPage,VIEW');
});
