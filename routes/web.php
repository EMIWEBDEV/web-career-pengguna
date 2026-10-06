<?php

use App\Http\Controllers\Career\CareerLandingController;
use App\Http\Controllers\Career\Media\MediaPublikController;
use App\Http\Controllers\Career\ProfilController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| WEB ROUTES — PROJECT: WEB CAREERS PENGGUNA (zona luar)
|--------------------------------------------------------------------------
| Hanya halaman & API yang dipakai pengunjung dan kandidat. Panel admin,
| pemicu tugas terjadwal, dan integrasi server-ke-server (CAT, HRIS) tinggal
| di project admin — aplikasi ini tidak punya jalan ke zona dalam.
| Semua rute berjalan di grup middleware "web" (session, CSRF, Inertia).
*/

// Root = halaman utama situs karir (landing).
Route::get('/', [CareerLandingController::class, 'index'])->name('career.home');

// Turnstile hanya dipakai di LOGIN; sitekey dibagikan ke frontend (kosong = fitur mati).
Route::get('/login', fn() => Inertia::render('Career/Auth', ['mode' => 'login', 'turnstileSiteKey' => config('services.cloudflare.turnstile_sitekey')]))->name('login');
Route::get('/register', fn() => Inertia::render('Career/Auth', ['mode' => 'register']))->name('register');

// Ganti / reset kata sandi (form). Email opsional dari query (alur lupa sandi).
Route::get(
    '/ganti-sandi',
    fn(Request $request) => Inertia::render('Career/GantiSandi', ['email' => (string) $request->query('email', '')]),
)->name('ganti-sandi');

// Profil kandidat — wajib login; halaman milik pengguna itu sendiri.
Route::get('/profil', [ProfilController::class, 'profil'])
    ->middleware('career.auth')
    ->name('profil');

// Logout — akhiri sesi lalu kembali ke halaman masuk.
Route::get('/logout', function (Request $request) {
    Auth::logout();
    $request->session()->forget('career_auth');
    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return redirect('/login?loggedout=1');
})->name('logout');

// Situs karir publik: mesin pencari, landing, lowongan, tim, formulir lamar.
require __DIR__ . '/pengguna/PublikWeb.php';

// Akun kandidat: daftar, masuk, OTP, verifikasi surel, lupa sandi.
require __DIR__ . '/Auth/AuthWeb.php';

// Portal kandidat + API lamaran + surat pengantar jadwal.
require __DIR__ . '/pengguna/PortalWeb.php';

// Isian feedback kandidat (tautan bertanda tangan) + status.
require __DIR__ . '/pengguna/FeedbackWeb.php';

// Endpoint pendidikan untuk formulir kandidat (cascade jenjang→jenis→kampus).
require __DIR__ . '/career/Pendidikan/PendidikanWeb.php';

// KONFIRMASI KEHADIRAN (tautan bertanda tangan dari surel undangan + portal).
require __DIR__ . '/career/KonfirmasiJadwal/KonfirmasiJadwalWeb.php';

// FAQ publik — halaman baca selengkapnya.
require __DIR__ . '/career/MasterFaq/MasterFaqPublik.php';

// Media halaman publik — gambar/video hero (landing) dan gambar tim.
Route::get('/karir/hero-media/{id}/{slot}', [MediaPublikController::class, 'hero'])
    ->where(['slot' => 'desktop|mobile|video_desktop|video_mobile|poster_desktop|poster_mobile'])
    ->name('career.hero.media');
Route::get('/karir/tim-img/{jenis}/{id}/{slot}', [MediaPublikController::class, 'tim'])
    ->where(['jenis' => 'divisi|sub', 'slot' => 'header|utama|img2|img3'])
    ->name('career.tim.img');
