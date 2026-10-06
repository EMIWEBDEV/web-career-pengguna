<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Career\MasterFeedback\MasterFeedbackController;
use App\Http\Controllers\Career\FeedbackAssignment\FeedbackAssignmentController;
use App\Http\Controllers\Career\Feedback\FeedbackController;
use App\Http\Controllers\Career\Feedback\FeedbackAdminController;

/*
|--------------------------------------------------------------------------
| WEB CAREER — FEEDBACK (branch: feat/feedback)
|--------------------------------------------------------------------------
| 1. Master Feedback Form + Pertanyaan (CRUD)          → admin
| 2. Assignment form ke program (specific/general)     → admin
| 3. Dashboard agregat + export                        → admin
| 4. Halaman isi feedback (token-based, tanpa login)   → publik
| 5. Cek status feedback per lamaran                   → kandidat (auth)
*/

// ── Master Feedback Form + Pertanyaan (admin) ──
Route::middleware(['career.auth', 'career.role:ADMIN,SUPERADMIN'])->group(function () {
    $ctrl = MasterFeedbackController::class;

    Route::get('/karir/master-feedback', [$ctrl, 'index'])
        ->name('career.master-feedback');

    Route::prefix('api/v1/karir/master-feedback')
        ->name('career.api.master-feedback.')
        ->group(function () use ($ctrl) {
            Route::get('/', [$ctrl, 'list'])->name('list');
            Route::get('/{id}', [$ctrl, 'show'])->name('show');
            Route::post('/', [$ctrl, 'store'])->name('store');
            Route::put('/{id}', [$ctrl, 'update'])->name('update');
            Route::delete('/{id}', [$ctrl, 'destroy'])->name('destroy');
            Route::post('/{id}/pertanyaan', [$ctrl, 'storePertanyaan'])->name('pertanyaan.store');
            Route::put('/pertanyaan/{id}', [$ctrl, 'updatePertanyaan'])->name('pertanyaan.update');
            Route::delete('/pertanyaan/{id}', [$ctrl, 'destroyPertanyaan'])->name('pertanyaan.destroy');
        });
});

// ── Feedback Assignment (admin) ──
Route::middleware(['career.auth', 'career.role:ADMIN,SUPERADMIN'])
    ->prefix('api/v1/karir/feedback-assignment')
    ->name('career.api.feedback.assignment.')
    ->group(function () {
        $assign = FeedbackAssignmentController::class;
        Route::get('/', [$assign, 'list'])->name('list');
        Route::post('/', [$assign, 'store'])->name('store');
        Route::put('/{id}', [$assign, 'update'])->name('update');
    });

// ── Dashboard Feedback (admin) ──
Route::middleware(['career.auth', 'career.role:ADMIN,SUPERADMIN'])->group(function () {
    $dash = FeedbackAdminController::class;

    Route::get('/karir/feedback-dashboard', [$dash, 'dashboard'])
        ->name('career.feedback-dashboard');

    Route::prefix('api/v1/karir/feedback')
        ->name('career.api.feedback.')
        ->group(function () use ($dash) {
            Route::get('/chart', [$dash, 'chartData'])->name('chart');
            Route::get('/detail/{id}', [$dash, 'detail'])->name('detail');
            // Monitoring
            Route::get('/monitoring-kpi', [$dash, 'monitoringKpi'])->name('monitoring.kpi');
            Route::get('/monitoring', [$dash, 'monitoringData'])->name('monitoring.data');
            Route::post('/reassign', [$dash, 'reassignForm'])->name('reassign');
            Route::post('/resend', [$dash, 'resendEmail'])->name('resend');
        });

    // Export
    Route::prefix('api/v1/karir')
        ->name('career.api.')
        ->group(function () use ($dash) {
            Route::post('/feedback/export', [$dash, 'requestExport'])->name('feedback.export.request');
            Route::get('/export/poll', [$dash, 'pollExport'])->name('export.poll');
            Route::get('/export/download/{id}', [$dash, 'downloadExport'])->name('export.download');
            Route::delete('/export/{id}', [$dash, 'dismissExport'])->name('export.dismiss');
        });
});

// ── Halaman isi feedback (PUBLIK — token-based, tanpa login) ──
Route::get('/feedback/{hashids}/{signature}', [FeedbackController::class, 'show'])
    ->name('career.feedback.form');

Route::post('/feedback/{hashids}/{signature}', [FeedbackController::class, 'submit'])
    ->name('career.feedback.submit')
    ->middleware('throttle:3,10'); // max 3 submit per 10 menit

// ── Master Info Divisi (admin) — konten landing page divisi/sub-divisi ──
Route::middleware(['career.auth', 'career.role:ADMIN,SUPERADMIN'])->group(function () {
    require base_path('routes/career/MasterDivisiInfo/MasterDivisiInfoWeb.php');
});
require base_path('routes/career/MasterDivisiInfo/MasterDivisiInfoApi.php');

// ── Gambar divisi/sub-divisi (PUBLIK — dipakai landing page & preview admin) ──
Route::get('/karir/tim-img/{jenis}/{id}/{slot}', [\App\Http\Controllers\Career\MasterDivisiInfo\MasterDivisiInfoController::class, 'gambarPublik'])
    ->where(['jenis' => 'divisi|sub', 'slot' => 'header|utama|img2|img3'])
    ->name('career.tim.img');

// ── Master FAQ (admin) — konten accordion landing + halaman /karir/faq ──
Route::middleware(['career.auth', 'career.role:ADMIN,SUPERADMIN'])->group(function () {
    require base_path('routes/career/MasterFaq/MasterFaqWeb.php');
});

// ── FAQ (PUBLIK — halaman baca selengkapnya + penghitung dilihat/membantu) ──
require base_path('routes/career/MasterFaq/MasterFaqPublik.php');

// ── Cek status feedback (kandidat auth) ──
Route::middleware('career.auth')
    ->prefix('api/v1/kandidat/feedback')
    ->name('career.api.kandidat.feedback.')
    ->group(function () {
        Route::get('/status/{lamaranId}', [FeedbackController::class, 'status'])
            ->name('status');
    });
