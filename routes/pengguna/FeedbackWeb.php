<?php

use App\Http\Controllers\Career\Feedback\FeedbackController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| WEB CAREERS PENGGUNA — feedback kandidat
|--------------------------------------------------------------------------
*/

// ── Halaman isi feedback (PUBLIK — tautan bertanda tangan, tanpa login) ──
// {kode} = kode lamaran; lihat App\Support\Portal\TautanFeedback.
Route::get('/feedback/{kode}/{hashids}/{signature}', [FeedbackController::class, 'show'])
    ->where('kode', '[A-Za-z0-9-]+')
    ->name('career.feedback.form');

Route::post('/feedback/{kode}/{hashids}/{signature}', [FeedbackController::class, 'submit'])
    ->where('kode', '[A-Za-z0-9-]+')
    ->name('career.feedback.submit')
    ->middleware('throttle:3,10'); // max 3 submit per 10 menit
