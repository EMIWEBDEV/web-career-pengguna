<?php

use App\Http\Controllers\Auth\AuthController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::prefix('api/v1')->name('career.auth.')->group(function () {
    // Langkah 1 register: cek ketersediaan KTP sebelum form identitas ditampilkan.
    Route::post('/cek-ktp', [AuthController::class, 'cekKtp'])->name('cek-ktp');
    Route::post('/register', [AuthController::class, 'register'])->name('register');
    Route::post('/login', [AuthController::class, 'login'])->name('login');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    // Reset kata sandi via OTP: minta kode (lupa-sandi) → verifikasi OTP (verifikasi-otp)
    // → kirim OTP+password baru (ganti-sandi).
    Route::post('/lupa-sandi', [AuthController::class, 'mintaOtpReset'])->name('lupa-sandi');
    Route::post('/verifikasi-otp', [AuthController::class, 'verifikasiOtp'])->name('verifikasi-otp');
    Route::post('/ganti-sandi', [AuthController::class, 'gantiSandi'])->name('ganti-sandi');
    Route::post('/kirim-verifikasi', [AuthController::class, 'kirimUlangVerifikasi'])->name('kirim-verifikasi');
    // Cek status verifikasi (polling halaman tunggu) + verifikasi via tempel token.
    Route::get('/status-verifikasi', [AuthController::class, 'statusVerifikasi'])->name('status-verifikasi');
    Route::post('/verifikasi-token', [AuthController::class, 'verifikasiToken'])->name('verifikasi-token');
});

// Halaman "menunggu verifikasi" — tujuan redirect setelah register berhasil.
Route::get('/menunggu-verifikasi', fn () => Inertia::render('Career/MenungguVerifikasi', [
    'email' => (string) request()->query('email', ''),
]))->name('career.auth.menunggu-verifikasi');

// Magic link dari email verifikasi — diakses langsung dari klien email (GET).
Route::get('/verifikasi-email', [AuthController::class, 'verifikasiEmail'])->name('career.auth.verifikasi-email');
