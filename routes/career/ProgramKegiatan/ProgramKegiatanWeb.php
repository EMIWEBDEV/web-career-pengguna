<?php

use App\Http\Controllers\Career\ProgramKegiatan\ProgramKegiatanController;
use App\Http\Controllers\Career\ProgramKegiatan\SkriningIkatController;
use Illuminate\Support\Facades\Route;

/*
| PROGRAM KEGIATAN (induk-detail). Halaman Inertia /karir/program-kegiatan + JSON group api/v1.
*/
Route::get('/karir/program-kegiatan', [ProgramKegiatanController::class, 'index'])->name('career.program-kegiatan')->middleware('career.permission:programPage,VIEW');

Route::prefix('api/v1')->name('career.api.program-kegiatan.')->group(function () {
    Route::get('/program-kegiatan', [ProgramKegiatanController::class, 'list'])->name('list')->middleware('career.permission:programPage,VIEW');
    // Guard: cek kelengkapan Info Divisi untuk MPP posisi terpilih (wajib sebelum lanjut).
    Route::post('/program-kegiatan/cek-info-divisi', [ProgramKegiatanController::class, 'cekInfoDivisi'])->name('cek-info-divisi')->middleware('career.permission:programPage,VIEW');
    // Isi MPP satu posisi (panel detail). Izinnya programPage, bukan
    // masterMppPage — lihat App\Support\Career\DetailMppLoker.
    Route::get('/program-kegiatan/{id}/posisi/{posisiId}', [ProgramKegiatanController::class, 'posisi'])->name('posisi')->middleware('career.permission:programPage,VIEW')->whereNumber('posisiId');
    Route::post('/program-kegiatan', [ProgramKegiatanController::class, 'store'])->name('store')->middleware('career.permission:programPage,CREATE');
    // Serah terima loker dari sisi program — rekruter melepas lokernya sendiri.
    // Gerbangnya EDIT, bukan SERAH_TERIMA: melepaskan milik sendiri bukan
    // kewenangan istimewa. Yang memindahkan milik orang lain dijaga di dalam.
    // Calon PIC / penerima serah terima — RUTE SENDIRI, dijaga programPage.
    // Sebelumnya halaman ini meminjam rute Master Akun dan rekruter tanpa modul
    // akun kena 403. Lihat ProgramKegiatanController::opsiPemegang.
    Route::get('/program-kegiatan/opsi/pemegang', [ProgramKegiatanController::class, 'opsiPemegang'])->name('opsi.pemegang')->middleware('career.permission:programPage,VIEW');
    Route::post('/program-kegiatan/serah-terima', [ProgramKegiatanController::class, 'serahTerima'])->name('serah.terima')->middleware('career.permission:programPage,EDIT');
    Route::put('/program-kegiatan/{id}', [ProgramKegiatanController::class, 'update'])->name('update')->middleware('career.permission:programPage,EDIT');
    // Pratinjau akibat penggantian alur — dipanggil SEBELUM menyimpan, jadi
    // izinnya EDIT: yang tidak boleh menyimpan tidak perlu tahu dampaknya.
    Route::get('/program-kegiatan/{id}/dampak-alur', [ProgramKegiatanController::class, 'dampakAlur'])->name('dampak.alur')->middleware('career.permission:programPage,EDIT');
    Route::patch('/program-kegiatan/{id}/toggle', [ProgramKegiatanController::class, 'toggle'])->name('toggle')->middleware('career.permission:programPage,EDIT');
    Route::delete('/program-kegiatan/{id}', [ProgramKegiatanController::class, 'destroy'])->name('destroy')->middleware('career.permission:programPage,DELETE');

    /*
    | PENGIKATAN TEMPLATE PHONE SCREENING — "loker ini pakai template mana".
    |
    | Berizin programPage, bukan masterSkriningPage: memilih template yang
    | berlaku untuk lokernya sendiri adalah pekerjaan rekruter. Merawat isi
    | templatenya urusan lain, dan dijaga izin lain.
    */
    // Tanpa program: wizard pembuatan program perlu tahu apakah alur yang
    // dipilih memuat tahap skrining, dan itu ditanyakan sebelum programnya ada.
    Route::get('/program-kegiatan/skrining-alur', [SkriningIkatController::class, 'alur'])->name('skrining.alur')->middleware('career.permission:programPage,VIEW');
    Route::get('/program-kegiatan/{id}/skrining', [SkriningIkatController::class, 'index'])->name('skrining.index')->middleware('career.permission:programPage,VIEW');
    Route::put('/program-kegiatan/{id}/skrining', [SkriningIkatController::class, 'simpan'])->name('skrining.simpan')->middleware('career.permission:programPage,EDIT');
    Route::post('/program-kegiatan/{id}/skrining/semua', [SkriningIkatController::class, 'terapkanSemua'])->name('skrining.semua')->middleware('career.permission:programPage,EDIT');
});
