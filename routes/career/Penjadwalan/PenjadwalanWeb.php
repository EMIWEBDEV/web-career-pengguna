<?php

use App\Http\Controllers\Career\Penjadwalan\PenjadwalanController;
use Illuminate\Support\Facades\Route;

/*
| PENJADWALAN. Halaman Inertia /karir/penjadwalan + JSON group api/v1.
| Semua opsi dari DB (tanpa hardcode); paket ujian diteruskan server dari HCLearn.
*/
Route::get('/karir/penjadwalan', [PenjadwalanController::class, 'index'])->name('career.penjadwalan')->middleware('career.permission:penjadwalanPage,VIEW');

Route::prefix('api/v1')->name('career.api.penjadwalan.')->group(function () {
    Route::get('/penjadwalan', [PenjadwalanController::class, 'list'])->name('list')->middleware('career.permission:penjadwalanPage,VIEW');
    // DENYUT PANEL ANTREAN. Didaftarkan SEBELUM pola {id} — tanpa itu 'progres'
    // tertangkap sebagai id penjadwalan dan dijawab 404 oleh Hashids.
    Route::get('/penjadwalan/progres', [PenjadwalanController::class, 'progres'])->name('progres')->middleware('career.permission:penjadwalanPage,VIEW');
    // Peserta SATU PROGRAM, lintas gelombang — isi akordion di layar daftar.
    // Terpaginasi & tersaring di server (tanggal, kampus, sesi, pencarian),
    // karena satu program bisa berisi ribuan pelamar.
    //
    // Didaftarkan SEBELUM pola {id} supaya 'program' tidak pernah tertangkap
    // sebagai id penjadwalan.
    Route::get('/penjadwalan/program/{id}/peserta', [PenjadwalanController::class, 'pesertaProgram'])->name('peserta.program')->middleware('career.permission:penjadwalanPage,VIEW');
    // Peserta satu penjadwalan — dipertahankan untuk pemanggil lain.
    Route::get('/penjadwalan/{id}/peserta', [PenjadwalanController::class, 'peserta'])->name('peserta')->middleware('career.permission:penjadwalanPage,VIEW');
    Route::post('/penjadwalan', [PenjadwalanController::class, 'store'])->name('store')->middleware('career.permission:penjadwalanPage,CREATE');
    // COBA LAGI penjadwalan yang gagal — mengantrekan ulang HANYA peserta yang
    // tokennya belum terbit. Izinnya EDIT: ini memperbaiki jadwal yang sudah
    // ada, bukan membuat yang baru.
    Route::post('/penjadwalan/{id}/ulang', [PenjadwalanController::class, 'ulang'])->name('ulang')->middleware('career.permission:penjadwalanPage,EDIT');
    // COBA LAGI SATU ORANG — dipakai tombol di panel antrean. Kegagalan
    // penerbitan token hampir selalu perorangan, dan mengirim ulang 500 orang
    // demi satu yang gagal berarti menunggu lama untuk pekerjaan yang sudah
    // selesai.
    Route::post('/penjadwalan/peserta/{id}/ulang', [PenjadwalanController::class, 'ulangPeserta'])->name('peserta.ulang')->middleware('career.permission:penjadwalanPage,EDIT');
    // Geser jadwal SATU kandidat (yang dipakai layar Penjadwalan).
    Route::put('/penjadwalan/peserta/{id}', [PenjadwalanController::class, 'updatePeserta'])->name('peserta.update')->middleware('career.permission:penjadwalanPage,EDIT');
    Route::put('/penjadwalan/{id}', [PenjadwalanController::class, 'update'])->name('update')->middleware('career.permission:penjadwalanPage,EDIT');
    Route::delete('/penjadwalan/{id}', [PenjadwalanController::class, 'destroy'])->name('destroy')->middleware('career.permission:penjadwalanPage,DELETE');

    Route::get('/penjadwalan/opsi', [PenjadwalanController::class, 'opsi'])->name('opsi')->middleware('career.permission:penjadwalanPage,VIEW');
    // Tahap/aktivitas tes milik ALUR program terpilih — pengganti daftar jenis tes global.
    Route::get('/penjadwalan/tes', [PenjadwalanController::class, 'tesAlur'])->name('tes')->middleware('career.permission:penjadwalanPage,VIEW');
    Route::get('/penjadwalan/kandidat', [PenjadwalanController::class, 'kandidat'])->name('kandidat')->middleware('career.permission:penjadwalanPage,VIEW');
    Route::get('/penjadwalan/paket-ujian', [PenjadwalanController::class, 'paketUjian'])->name('paket')->middleware('career.permission:penjadwalanPage,VIEW');
});
