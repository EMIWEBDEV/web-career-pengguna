<?php

use App\Http\Controllers\Career\Faq\FaqPublikController;
use Illuminate\Support\Facades\Route;

/*
| FAQ PUBLIK — halaman "baca selengkapnya" untuk pertanyaan yang sering diajukan.
| Tanpa auth: ini halaman informasi untuk calon pelamar.
*/

Route::get('/karir/faq', [FaqPublikController::class, 'index'])->name('career.faq');
