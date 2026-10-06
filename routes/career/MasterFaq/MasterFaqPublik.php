<?php

use App\Http\Controllers\Career\Faq\FaqPublikController;
use Illuminate\Support\Facades\Route;

/*
| FAQ PUBLIK — halaman "baca selengkapnya" untuk pertanyaan yang sering diajukan.
| Tanpa auth: ini halaman informasi untuk calon pelamar.
|
| Penghitung dilihat/membantu di-throttle karena endpoint-nya terbuka; dedup
| sebenarnya dilakukan per SESI di controller, throttle hanya jaring pengaman
| terhadap penyalahgunaan.
*/

Route::get('/karir/faq', [FaqPublikController::class, 'index'])->name('career.faq');

Route::prefix('api/v1/karir/faq')->name('career.api.faq.')->group(function () {
    Route::post('/{id}/dilihat', [FaqPublikController::class, 'catatDilihat'])
        ->name('dilihat')
        ->middleware('throttle:60,1');

    Route::post('/{id}/membantu', [FaqPublikController::class, 'catatMembantu'])
        ->name('membantu')
        ->middleware('throttle:20,1');
});
