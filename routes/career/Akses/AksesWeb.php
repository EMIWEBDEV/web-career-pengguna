<?php

use App\Http\Controllers\Career\Akses\HakAksesController;
use App\Http\Controllers\Career\Akses\KlasifikasiAksesController;
use App\Http\Controllers\Career\Akses\MasterMenuController;
use App\Http\Controllers\Career\Akses\SusunMenuController;
use Illuminate\Support\Facades\Route;

/*
| HAK AKSES (RBAC) — pola cat-evo-pembaharuan
| - Master Menu            : /master-menu           (masterMenuPage)
| - Manajemen Hak Akses    : /hak-akses             (hakAksesPage)
| - Penyusun Menu Akun     : /hak-akses/susun/{user} (hakAksesPage, EDIT)
| - Akses Klasifikasi Akun : /klasifikasi-akses     (klasifikasiAksesPage)
|
| Seluruh route dikunci career.permission:{page},{AKSI} — termasuk modul ini
| sendiri, supaya hanya pemegang izinnya yang bisa mengubah hak akses.
*/

/*
| PRATINJAU HALAMAN STATUS — melihat seluruh desain error tanpa memicu error
| sungguhan. Contoh: /error-preview/404 , /error-preview/503 , /error-preview/offline
| Tambahkan ?shell=1 untuk melihat varian DI DALAM shell admin (ada navbar).
| Dibatasi: hanya environment lokal ATAU pengguna SUPERADMIN.
*/
Route::get('/error-preview/{status}', function (string $status, \Illuminate\Http\Request $request) {
    abort_unless(app()->environment('local') || session('career_auth.role') === 'SUPERADMIN', 404);

    $props = [
        'status' => is_numeric($status) ? (int) $status : $status,
        'homeUrl' => \App\Http\Middleware\CareerRole::beranda(session('career_auth.role')),
        'referensi' => 'PREVIEW-' . strtoupper($status),
        'tanggal' => now()->translatedFormat('l, d F Y'),
    ];

    if ($request->boolean('shell')) {
        $props['maintenanceMenu'] = 'Contoh Menu';

        return \Inertia\Inertia::render('ErrorShell', \App\Support\CareerShell::props('/error-preview', 'Pratinjau Status', $props));
    }

    return \Inertia\Inertia::render('Error', $props);
})->name('career.error-preview');

// ── MASTER MENU ──
Route::get('/master-menu', [MasterMenuController::class, 'index'])->name('career.master-menu')
    ->middleware('career.permission:masterMenuPage,VIEW');

Route::prefix('api/v1')->name('career.api.master-menu.')->group(function () {
    Route::get('/master-menu', [MasterMenuController::class, 'list'])->name('list')
        ->middleware('career.permission:masterMenuPage,VIEW');
    Route::post('/master-menu', [MasterMenuController::class, 'store'])->name('store')
        ->middleware('career.permission:masterMenuPage,CREATE');
    Route::put('/master-menu/{id}', [MasterMenuController::class, 'update'])->name('update')
        ->middleware('career.permission:masterMenuPage,EDIT');
    Route::patch('/master-menu/{id}/toggle', [MasterMenuController::class, 'toggle'])->name('toggle')
        ->middleware('career.permission:masterMenuPage,EDIT');
    Route::patch('/master-menu/{id}/maintenance', [MasterMenuController::class, 'toggleMaintenance'])->name('maintenance')
        ->middleware('career.permission:masterMenuPage,EDIT');
    Route::delete('/master-menu/{id}', [MasterMenuController::class, 'destroy'])->name('destroy')
        ->middleware('career.permission:masterMenuPage,DELETE');
});

// ── MANAJEMEN HAK AKSES ──
Route::get('/hak-akses', [HakAksesController::class, 'index'])->name('career.hak-akses')
    ->middleware('career.permission:hakAksesPage,VIEW');

// ── PENYUSUN MENU PER AKUN (gaya WordPress: panel kiri–kanan + drag & drop) ──
// Halaman sendiri, bukan modal: butuh ruang untuk dua panel dan geser-menggeser.
Route::get('/hak-akses/susun/{userId}', [SusunMenuController::class, 'index'])->name('career.hak-akses.susun')
    ->middleware('career.permission:hakAksesPage,EDIT');

Route::prefix('api/v1')->name('career.api.hak-akses.')->group(function () {
    Route::get('/hak-akses', [HakAksesController::class, 'list'])->name('list')
        ->middleware('career.permission:hakAksesPage,VIEW');
    Route::get('/hak-akses/summary', [HakAksesController::class, 'summary'])->name('summary')
        ->middleware('career.permission:hakAksesPage,VIEW');
    Route::get('/hak-akses/referensi', [HakAksesController::class, 'referensi'])->name('referensi')
        ->middleware('career.permission:hakAksesPage,VIEW');
    Route::post('/hak-akses', [HakAksesController::class, 'store'])->name('store')
        ->middleware('career.permission:hakAksesPage,CREATE');
    Route::post('/hak-akses/toggle-aksi', [HakAksesController::class, 'toggleAksi'])->name('toggle.aksi')
        ->middleware('career.permission:hakAksesPage,EDIT');
    Route::post('/hak-akses/ubah-lingkup', [HakAksesController::class, 'ubahLingkup'])->name('ubah.lingkup')
        ->middleware('career.permission:hakAksesPage,EDIT');
    Route::post('/hak-akses/toggle-konten', [HakAksesController::class, 'toggleKonten'])->name('toggle.konten')
        ->middleware('career.permission:hakAksesPage,EDIT');
    Route::post('/hak-akses/bulk-toggle', [HakAksesController::class, 'bulkToggle'])->name('bulk')
        ->middleware('career.permission:hakAksesPage,EDIT');
    Route::post('/hak-akses/duplikat', [HakAksesController::class, 'duplikat'])->name('duplikat')
        ->middleware('career.permission:hakAksesPage,CREATE');
    Route::delete('/hak-akses/{id}', [HakAksesController::class, 'destroy'])->name('destroy')
        ->middleware('career.permission:hakAksesPage,DELETE');
});

// ── API PENYUSUN MENU ──
// Didaftarkan di grup terpisah supaya '/hak-akses/susun/...' tidak tertelan
// route parameter '/hak-akses/{id}' di grup atas.
Route::prefix('api/v1')->name('career.api.susun-menu.')->group(function () {
    Route::get('/hak-akses/susun/{userId}', [SusunMenuController::class, 'data'])->name('data')
        ->middleware('career.permission:hakAksesPage,VIEW');
    Route::post('/hak-akses/susun/{userId}', [SusunMenuController::class, 'simpan'])->name('simpan')
        ->middleware('career.permission:hakAksesPage,EDIT');
});

// ── AKSES KLASIFIKASI AKUN (cetakan kandidat + whitelist program) ──
Route::get('/klasifikasi-akses', [KlasifikasiAksesController::class, 'index'])->name('career.klasifikasi-akses')
    ->middleware('career.permission:klasifikasiAksesPage,VIEW');

Route::prefix('api/v1')->name('career.api.klasifikasi-akses.')->group(function () {
    Route::get('/klasifikasi-akses', [KlasifikasiAksesController::class, 'list'])->name('list')
        ->middleware('career.permission:klasifikasiAksesPage,VIEW');
    Route::post('/klasifikasi-akses/toggle-menu', [KlasifikasiAksesController::class, 'toggleMenu'])->name('toggle.menu')
        ->middleware('career.permission:klasifikasiAksesPage,EDIT');
    Route::post('/klasifikasi-akses/toggle-aksi', [KlasifikasiAksesController::class, 'toggleAksi'])->name('toggle.aksi')
        ->middleware('career.permission:klasifikasiAksesPage,EDIT');
    Route::post('/klasifikasi-akses/toggle-whitelist', [KlasifikasiAksesController::class, 'toggleWhitelist'])->name('toggle.whitelist')
        ->middleware('career.permission:klasifikasiAksesPage,EDIT');
    Route::post('/klasifikasi-akses/terapkan', [KlasifikasiAksesController::class, 'terapkan'])->name('terapkan')
        ->middleware('career.permission:klasifikasiAksesPage,EDIT');
});
