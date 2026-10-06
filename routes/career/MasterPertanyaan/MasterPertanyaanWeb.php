<?php

use App\Http\Controllers\Career\MasterPertanyaan\MasterPertanyaanController;
use Illuminate\Support\Facades\Route;

/*
| BANK PERTANYAAN — pustaka pertanyaan yang bisa ditarik ke template mana pun.
|
| Halaman Inertia /master-pertanyaan + grup JSON api/v1, seizin
| masterPertanyaanPage.
|
| ── KENAPA IZIN SENDIRI, TERPISAH DARI masterSkriningPage ───────────────────
| Karena keduanya pekerjaan yang berbeda. Menyusun template dari pertanyaan
| yang sudah ada adalah pekerjaan sehari-hari rekruter senior; merumuskan
| pertanyaan yang dipakai SELURUH template adalah keputusan yang lebih jarang
| dan lebih luas akibatnya. Skrip SQL-nya memberi keduanya ke orang yang sama
| hari ini — tapi memisahkannya di sini membuat pemisahan itu mungkin nanti
| tanpa menyentuh kode.
|
| Daftar bank yang dibaca PENYUNTING TEMPLATE tidak lewat sini: ia ikut dalam
| payload master-skrining, supaya orang yang sedang menyusun template tidak
| perlu berhak menyunting pustaka hanya untuk melihat isinya.
*/

Route::get('/master-pertanyaan', [MasterPertanyaanController::class, 'index'])
    ->name('career.master-pertanyaan')
    ->middleware('career.permission:masterPertanyaanPage,VIEW');

Route::prefix('api/v1')->name('career.api.master-pertanyaan.')->group(function () {
    Route::get('/master-pertanyaan', [MasterPertanyaanController::class, 'list'])
        ->name('list')->middleware('career.permission:masterPertanyaanPage,VIEW');
    Route::post('/master-pertanyaan', [MasterPertanyaanController::class, 'store'])
        ->name('store')->middleware('career.permission:masterPertanyaanPage,CREATE');
    // Membaca saja: menghitung kode yang akan dipakai, tanpa menyimpan apa pun.
    Route::post('/master-pertanyaan/pratinjau-kode', [MasterPertanyaanController::class, 'pratinjauKode'])
        ->name('pratinjau-kode')->middleware('career.permission:masterPertanyaanPage,VIEW');
    Route::post('/master-pertanyaan/{id}/duplikat', [MasterPertanyaanController::class, 'duplikat'])
        ->name('duplikat')->middleware('career.permission:masterPertanyaanPage,CREATE');
    Route::put('/master-pertanyaan/{id}', [MasterPertanyaanController::class, 'update'])
        ->name('update')->middleware('career.permission:masterPertanyaanPage,EDIT');
    Route::patch('/master-pertanyaan/{id}/toggle', [MasterPertanyaanController::class, 'toggle'])
        ->name('toggle')->middleware('career.permission:masterPertanyaanPage,EDIT');
    Route::delete('/master-pertanyaan/{id}', [MasterPertanyaanController::class, 'destroy'])
        ->name('destroy')->middleware('career.permission:masterPertanyaanPage,DELETE');

    // -- Tag --
    //
    // Penandaan massal didaftarkan SEBELUM rute ber-{id} tidak perlu: polanya
    // berbeda ruas ('tag-massal' vs '{id}/tag'), jadi tidak ada yang saling
    // menelan. Keduanya EDIT: menandai bukan membuat pertanyaan baru.
    Route::put('/master-pertanyaan/{id}/tag', [MasterPertanyaanController::class, 'simpanTag'])
        ->name('tag')->middleware('career.permission:masterPertanyaanPage,EDIT');
    Route::post('/master-pertanyaan/tag-massal', [MasterPertanyaanController::class, 'tagMassal'])
        ->name('tag-massal')->middleware('career.permission:masterPertanyaanPage,EDIT');
});
