<?php

use App\Http\Controllers\Career\KonfirmasiJadwal\KonfirmasiJadwalController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| KONFIRMASI KEHADIRAN — halaman kandidat TANPA login
|--------------------------------------------------------------------------
|
| Dibuka dari tombol di surel undangan: surel dibaca di aplikasi surat tanpa
| sesi portal. Seluruh tautannya BERTANDA TANGAN (HMAC APP_KEY) + berumur, dan
| terikat VERSI jadwal — tidak bisa ditebak, disunting, atau dipakai untuk
| jadwal yang sudah diganti.
|
| GET halaman memeriksa tanda tangannya sendiri (tautan rusak/kedaluwarsa
| dijawab halaman ramah). POST memakai middleware `signed`.
| POST dikecualikan dari CSRF (VerifyCsrfToken): tanda tangan tautan sudah
| menjadi rahasianya, dan peramban dalam-aplikasi surel kerap kehilangan
| cookie sesi — 419 di sana hanya membuat kandidat tak bisa menjawab.
*/
Route::prefix('karir/konfirmasi/{id}/{versi}')
    ->where(['versi' => '[0-9]+'])
    ->name('career.konfirmasi.')
    ->group(function () {
        Route::get('/', [KonfirmasiJadwalController::class, 'halaman'])
            ->middleware('throttle:60,1')
            ->name('halaman');

        Route::post('/jawab', [KonfirmasiJadwalController::class, 'jawab'])
            ->middleware(['signed', 'throttle:20,1'])
            ->name('jawab');

        Route::post('/cabut', [KonfirmasiJadwalController::class, 'cabut'])
            ->middleware(['signed', 'throttle:20,1'])
            ->name('cabut');

        Route::post('/dibuka', [KonfirmasiJadwalController::class, 'dibuka'])
            ->middleware(['signed', 'throttle:20,1'])
            ->name('dibuka');
    });

/*
| PORTAL KANDIDAT — jawab langsung di kartu jadwal, tanpa pindah halaman.
| Wajib login; pengontrol memastikan jadwalnya MILIK akun yang masuk (admin
| yang menengok portal tidak bisa menjawab dari sini). CSRF berlaku seperti
| rute portal lainnya.
*/
Route::prefix('kandidat/konfirmasi/{id}/{versi}')
    ->where(['versi' => '[0-9]+'])
    ->middleware('career.auth')
    ->name('career.portal.konfirmasi.')
    ->group(function () {
        Route::post('/jawab', [KonfirmasiJadwalController::class, 'jawabPortal'])
            ->middleware('throttle:20,1')
            ->name('jawab');

        Route::post('/cabut', [KonfirmasiJadwalController::class, 'cabutPortal'])
            ->middleware('throttle:20,1')
            ->name('cabut');
    });
