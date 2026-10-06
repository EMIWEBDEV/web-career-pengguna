<?php

use App\Http\Controllers\Career\Lamaran\FormulirDrafController;
use App\Http\Controllers\Career\Lamaran\LamaranController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| WEB CAREERS PENGGUNA — portal kandidat & API lamaran
|--------------------------------------------------------------------------
| Wajib login (career.auth). Kepemilikan lamaran/tahap diperiksa di
| controller lewat Lamaran.Id_Users.
*/

// ── Portal Kandidat (prefix /kandidat) ──
Route::prefix('kandidat')
    ->middleware('career.auth')
    ->name('career.portal.')
    ->group(function () {
        Route::get('/loker', [LamaranController::class, 'loker'])->name('loker')->middleware('career.permission:lokerPage,VIEW');
        Route::get('/portal', [LamaranController::class, 'portalIndex'])->name('index')->middleware('career.permission:portalPage,VIEW');
        Route::get('/lamaran/{id}', [LamaranController::class, 'portalDetail'])->name('detail')->middleware('career.permission:portalPage,VIEW');
        // Tautan surel dari zona dalam: hanya mengenal KODE lamaran.
        Route::get('/lamaran/kode/{kode}', [LamaranController::class, 'bukaKode'])->where('kode', '[A-Za-z0-9-]+')->name('kode')->middleware('career.permission:portalPage,VIEW');
        // Pratinjau berkas milik kandidat sendiri (signed URL GCS).
        Route::get('/lamaran/berkas/file/{id}', [LamaranController::class, 'portalBerkasFile'])->name('berkas.file');
        // Berkas HASIL TAHAP (MCU, hasil wawancara) — boleh dilihat kandidat.
        // Nilai tetap ditahan di payload; yang dibuka hanya dokumennya.
        Route::get('/lamaran/tahap/berkas/{id}', [LamaranController::class, 'portalBerkasTahap'])->name('tahap.berkas');

        // ── BERKAS AKTIVITAS yang diunggah KANDIDAT (tes offline dsb.) ──
        // Aturan format & ukuran dibaca dari aktivitasnya sendiri, jadi tiap
        // aktivitas boleh menuntut hal yang berbeda.
        Route::get('/lamaran/tes/{id}/berkas', [LamaranController::class, 'tesBerkas'])->name('tes.berkas');
        Route::post('/lamaran/tes/{id}/berkas', [LamaranController::class, 'tesBerkasUnggah'])->name('tes.berkas.unggah');
        Route::delete('/lamaran/tes/berkas/{id}', [LamaranController::class, 'tesBerkasHapus'])->name('tes.berkas.hapus');
        // Kandidat menyatakan berkasnya SUDAH LENGKAP. Terpisah dari unggah:
        // "mengunggah" dan "selesai mengunggah" bukan hal yang sama, dan tanpa
        // pernyataan ini admin menilai tanpa tahu apakah masih ada susulan.
        Route::patch('/lamaran/tes/{id}/berkas/kirim', [LamaranController::class, 'tesBerkasKirim'])->name('tes.berkas.kirim');
        Route::get('/lamaran/tes/berkas/{id}/file', [LamaranController::class, 'tesBerkasFile'])->name('tes.berkas.file');
        // Surat pengantar jadwal (MCU) milik kandidat sendiri.
        Route::get('/lamaran/tes/{id}/surat/{urutan?}', [LamaranController::class, 'tesSurat'])->where('urutan', '[0-9]+')->name('tes.surat');

        // ── SIMPAN SEMENTARA (DRAF) FORMULIR TAHAP ──
        // Semua endpoint memeriksa kepemilikan tahap lewat Lamaran.Id_Users, jadi
        // id tahap milik orang lain tidak bisa dipakai membaca atau menimpa draf.
        // Berkas draf HANYA dibuka lewat endpoint berkas di bawah (signed URL
        // 15 menit) — front-end tidak pernah menyentuh API storage langsung.
        Route::get('/lamaran/tahap/{id}/draf', [FormulirDrafController::class, 'ambil'])->name('draf.ambil');
        Route::post('/lamaran/tahap/{id}/draf', [FormulirDrafController::class, 'simpan'])->name('draf.simpan');
        Route::post('/lamaran/tahap/{id}/draf/berkas', [FormulirDrafController::class, 'unggahBerkas'])->name('draf.berkas.unggah');
        Route::get('/lamaran/tahap/{id}/draf/berkas/{field}', [FormulirDrafController::class, 'berkas'])->name('draf.berkas');
        // Dipanggil saat kandidat menghapus SATU BARIS bagian berulang. Tanpa
        // ini berkas baris itu menetap di bucket selamanya, dan indeks berkas
        // di atasnya tidak pernah turun.
        Route::delete('/lamaran/tahap/{id}/draf/berkas', [FormulirDrafController::class, 'hapusBerkas'])->name('draf.berkas.hapus');
    });

// ── Referensi pendidikan untuk formulir (login saja) ──
// Dipakai field ber-tipe `referensi`: jenjang, jenis institusi, kampus, prodi.
Route::prefix('api/v1/referensi')
    ->middleware('career.auth')
    ->name('career.referensi.')
    ->group(function () {
        Route::get('/{sumber}', [\App\Http\Controllers\Career\Referensi\ReferensiController::class, 'opsi'])
            ->where('sumber', 'jenjang|jenis_institusi|kampus|prodi')
            ->name('opsi');
    });

// ── API Lamaran kandidat (login saja) ──
Route::prefix('api/v1/lamaran')
    ->middleware('career.auth')
    ->name('career.lamaran.')
    ->group(function () {
        Route::get('/loker', [LamaranController::class, 'lokerList'])->name('loker')->middleware('career.permission:lokerPage,VIEW');
        Route::post('/', [LamaranController::class, 'lamar'])->name('lamar')->middleware('career.permission:portalPage,CREATE');
        Route::get('/apply-status/{processId}', [LamaranController::class, 'applyStatus'])->name('apply.status');
        Route::post('/tahap/{id}/kirim', [LamaranController::class, 'kirimFormulir'])->name('kirim')->middleware('career.permission:portalPage,EDIT');
        Route::delete('/{id}', [LamaranController::class, 'batalkan'])->name('batal');
    });

// SURAT PENGANTAR JADWAL dari tautan SUREL — surel dibuka di aplikasi surat
// tanpa sesi portal. Bertanda tangan & berumur (lihat SuratJadwal::tautanEmail);
// tanpa login, tanpa bisa ditebak.
Route::get('/karir/surat-jadwal/{id}/{urutan?}', [LamaranController::class, 'suratJadwalPublik'])
    ->where('urutan', '[0-9]+')
    ->middleware('signed')
    ->name('career.surat.jadwal');
