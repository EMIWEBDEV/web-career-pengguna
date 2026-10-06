<?php

use App\Http\Controllers\Career\MasterSkill\MasterSkillController;
use App\Http\Controllers\Career\MasterSkill\MasterSkillKategoriController;
use Illuminate\Support\Facades\Route;

/*
| MASTER SKILL — keahlian yang disyaratkan lowongan MPP.
| - Halaman (Inertia / SPA): GET /master-skill  (dua tab: Skill & Kategori)
| - Data & CRUD (JSON, ResponseHelper) di GROUP prefix api/v1.
| Kunci hak akses: masterSkillPage (N_WEB_CAREERS_Menu.Jenis_Page) — kategori
| MENUMPANG kunci yang sama karena dikelola di halaman yang sama.
|
| Endpoint kategori sengaja memakai prefix TERPISAH (master-skill-kategori),
| bukan /master-skill/kategori — kalau menumpang di bawahnya, segmen "kategori"
| akan ditelan pola {id} milik route update/destroy.
*/

Route::get('/master-skill', [MasterSkillController::class, 'index'])->name('career.master-skill')->middleware('career.permission:masterSkillPage,VIEW');

Route::prefix('api/v1')->name('career.api.master-skill.')->group(function () {
    Route::get('/master-skill', [MasterSkillController::class, 'list'])->name('list')->middleware('career.permission:masterSkillPage,VIEW');
    Route::post('/master-skill', [MasterSkillController::class, 'store'])->name('store')->middleware('career.permission:masterSkillPage,CREATE');
    Route::put('/master-skill/{id}', [MasterSkillController::class, 'update'])->name('update')->middleware('career.permission:masterSkillPage,EDIT');
    Route::patch('/master-skill/{id}/toggle', [MasterSkillController::class, 'toggle'])->name('toggle')->middleware('career.permission:masterSkillPage,EDIT');
    Route::delete('/master-skill/{id}', [MasterSkillController::class, 'destroy'])->name('destroy')->middleware('career.permission:masterSkillPage,DELETE');
});

Route::prefix('api/v1')->name('career.api.master-skill-kategori.')->group(function () {
    Route::get('/master-skill-kategori', [MasterSkillKategoriController::class, 'list'])->name('list')->middleware('career.permission:masterSkillPage,VIEW');
    Route::post('/master-skill-kategori', [MasterSkillKategoriController::class, 'store'])->name('store')->middleware('career.permission:masterSkillPage,CREATE');
    Route::put('/master-skill-kategori/{id}', [MasterSkillKategoriController::class, 'update'])->name('update')->middleware('career.permission:masterSkillPage,EDIT');
    Route::patch('/master-skill-kategori/{id}/toggle', [MasterSkillKategoriController::class, 'toggle'])->name('toggle')->middleware('career.permission:masterSkillPage,EDIT');
    Route::delete('/master-skill-kategori/{id}', [MasterSkillKategoriController::class, 'destroy'])->name('destroy')->middleware('career.permission:masterSkillPage,DELETE');
});
