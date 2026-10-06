<?php

use App\Http\Controllers\Career\CareerAdminController;
use App\Http\Controllers\Career\CareerLandingController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| WEB ROUTES — PROJECT: WEB CAREER
|--------------------------------------------------------------------------
| Seluruh fitur legacy (KPI / LMS / HCIS / Visitor) telah dihapus.
| Rute dipecah ke dua file "induk" per developer:
|   - routes/kpi/fransDevEvo.php  → publik + auth kandidat
|   - routes/kpi/ridhoDevEvo.php  → admin panel + portal kandidat
| Semua rute berjalan di grup middleware "web" (session, CSRF, Inertia).
*/

// robots.txt DINAMIS — menggantikan berkas statis public/robots.txt yang dulu
// berisi "Disallow: /" dan ikut terbawa ke produksi. Lihat RobotsController.
Route::get('/robots.txt', \App\Http\Controllers\RobotsController::class)->name('robots');

// Root = halaman utama situs karir (landing).
Route::get('/', [CareerLandingController::class, 'index'])->name('career.home');

// Auth REAL (DB) — komponen SAMA dengan halaman test (Career/Auth), kini nyambung DB.
// Turnstile hanya dipakai di LOGIN; sitekey dibagikan ke frontend (kosong = fitur mati).
Route::get('/login', fn() => Inertia::render('Career/Auth', ['mode' => 'login', 'turnstileSiteKey' => config('services.cloudflare.turnstile_sitekey')]))->name('login');
Route::get('/register', fn() => Inertia::render('Career/Auth', ['mode' => 'register']))->name('register');

// Ganti / reset kata sandi (form). Email opsional dari query (alur lupa sandi).
Route::get(
    '/ganti-sandi',
    fn(Request $request) => Inertia::render('Career/GantiSandi', ['email' => (string) $request->query('email', '')]),
)->name('ganti-sandi');

// Profil — SATU route untuk semua akun (admin & kandidat); shell menyesuaikan role.
// Wajib login, tanpa gerbang peran: ini halaman milik pengguna itu sendiri.
Route::get('/profil', [CareerAdminController::class, 'profil'])
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

require __DIR__ . '/kpi/fransDevEvo.php';
require __DIR__ . '/kpi/ridhoDevEvo.php';

// Endpoint pendidikan untuk formulir kandidat (cascade jenjang→jenis→kampus).
require __DIR__ . '/career/Pendidikan/PendidikanWeb.php';

// KONFIRMASI KEHADIRAN (PUBLIK, tautan bertanda tangan dari surel undangan).
require __DIR__ . '/career/KonfirmasiJadwal/KonfirmasiJadwalWeb.php';

// Media hero slide (PUBLIK — dipakai landing page & preview admin).
Route::get('/karir/hero-media/{id}/{slot}', [\App\Http\Controllers\Career\MasterHero\MasterHeroController::class, 'mediaPublik'])
    ->where(['slot' => 'desktop|mobile|video_desktop|video_mobile|poster_desktop|poster_mobile'])
    ->name('career.hero.media');

// SELURUH modul master & operasional (17 modul) adalah milik ADMIN.
// Dikunci di satu tempat, bukan ditempel satu per satu di tiap berkas modul —
// supaya modul baru otomatis ikut terlindungi dan tidak ada yang kelewat.
Route::middleware(['career.auth', 'career.role:ADMIN,SUPERADMIN'])->group(function () {
    require __DIR__ . '/career/fransDeveloperDevEvo.php';
});

require __DIR__ . '/career/ridhoDeveloperEvoDevEvo.php';

// [feat/feedback] Modul Feedback — admin master + dashboard + kandidat + public
require __DIR__ . '/career/ridhoDeveloperEvoDevEvo-v2.php';

// Diagnostik jalur jaringan (SMTP) — dijalankan lewat peramban karena Cloud Run
// tidak menyediakan shell, dan pemeriksaannya HARUS dari mesin yang gagal.
require __DIR__ . '/career/Diagnostik/DiagnostikWeb.php';

// GEMBOK — halaman bypass penjadwalan (/ui/bypass/gembok).
//
// SENGAJA DI LUAR grup career.auth di atas: halaman ini dipakai justru ketika
// panel admin sedang tidak bisa dipakai. Yang menjaganya adalah GEMBOK_SECRET
// lewat middleware GerbangGembok — tanpa kunci yang cocok, seluruh rutenya
// membalas 404. Baca catatan panjang di berkasnya sebelum mengubah ini.
require __DIR__ . '/career/Gembok/GembokWeb.php';
