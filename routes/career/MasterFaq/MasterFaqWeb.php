<?php

use App\Http\Controllers\Career\MasterFaq\MasterFaqController;
use Illuminate\Support\Facades\Route;

/*
| MASTER FAQ (admin) — pola Master Kategori
| - Halaman (Inertia / SPA): GET /master-faq
| - Data & CRUD (JSON, ResponseHelper) di prefix api/v1 — BUKAN halaman Inertia.
|
| Permission key: masterFaqPage
| CATATAN: menu & hak akses TIDAK didaftarkan di kode. Setelah deploy, jalankan
| database/sql/2026-07-30-master-faq-menu.sql (mendaftarkan menu "Master FAQ" →
| /master-faq beserta VIEW/CREATE/EDIT/DELETE untuk semua admin aktif), atau
| lakukan manual lewat /master-menu + /hak-akses. Tanpa itu middleware di bawah
| menolak semua orang.
*/

// Halaman Inertia
Route::get('/master-faq', [MasterFaqController::class, 'index'])
    ->name('career.master-faq')
    ->middleware('career.permission:masterFaqPage,VIEW');

// JSON (bukan halaman Inertia) — prefix api/v1
Route::prefix('api/v1')->name('career.api.master-faq.')->group(function () {
    $ctrl = MasterFaqController::class;

    // ── Pertanyaan ──
    Route::get('/master-faq', [$ctrl, 'list'])
        ->name('list')->middleware('career.permission:masterFaqPage,VIEW');
    Route::post('/master-faq', [$ctrl, 'store'])
        ->name('store')->middleware('career.permission:masterFaqPage,CREATE');
    Route::post('/master-faq/reorder', [$ctrl, 'reorder'])
        ->name('reorder')->middleware('career.permission:masterFaqPage,EDIT');
    Route::put('/master-faq/{id}', [$ctrl, 'update'])
        ->name('update')->middleware('career.permission:masterFaqPage,EDIT');
    Route::patch('/master-faq/{id}/toggle', [$ctrl, 'toggle'])
        ->name('toggle')->middleware('career.permission:masterFaqPage,EDIT');
    Route::patch('/master-faq/{id}/landing', [$ctrl, 'toggleLanding'])
        ->name('landing')->middleware('career.permission:masterFaqPage,EDIT');
    Route::patch('/master-faq/{id}/slug', [$ctrl, 'perbaruiSlug'])
        ->name('slug')->middleware('career.permission:masterFaqPage,EDIT');
    Route::delete('/master-faq/{id}', [$ctrl, 'destroy'])
        ->name('destroy')->middleware('career.permission:masterFaqPage,DELETE');

    // ── Kategori ──
    Route::get('/master-faq-kategori', [$ctrl, 'listKategori'])
        ->name('kategori.list')->middleware('career.permission:masterFaqPage,VIEW');
    Route::post('/master-faq-kategori', [$ctrl, 'storeKategori'])
        ->name('kategori.store')->middleware('career.permission:masterFaqPage,CREATE');
    Route::put('/master-faq-kategori/{id}', [$ctrl, 'updateKategori'])
        ->name('kategori.update')->middleware('career.permission:masterFaqPage,EDIT');
    Route::patch('/master-faq-kategori/{id}/toggle', [$ctrl, 'toggleKategori'])
        ->name('kategori.toggle')->middleware('career.permission:masterFaqPage,EDIT');
    Route::delete('/master-faq-kategori/{id}', [$ctrl, 'destroyKategori'])
        ->name('kategori.destroy')->middleware('career.permission:masterFaqPage,DELETE');
});
