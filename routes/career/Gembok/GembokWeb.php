<?php

use App\Http\Controllers\Career\Gembok\GembokController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| GEMBOK — halaman bypass operasional  /ui/bypass/gembok
|--------------------------------------------------------------------------
|
| Dibuka sekali dengan kuncinya:
|
|     /ui/bypass/gembok?gembok-key=<GEMBOK_SECRET>
|
| Sesudah itu kuncinya dibuang dari bilah alamat dan diganti kue tanda masuk
| yang berumur pendek — lihat App\Http\Middleware\GerbangGembok.
|
| ══ TIDAK ADA career.auth DI SINI, DAN ITU DISENGAJA ══
|
| Seluruh modul admin dikunci career.auth + career.role di routes/web.php.
| Berkas ini SENGAJA didaftarkan DI LUAR grup itu: halaman ini dipakai justru
| ketika panel admin tidak bisa dipakai — akun terkunci, peran kacau, atau
| sesi yang tidak mau jalan. Menaruhnya di dalam gerbang yang sama membuatnya
| ikut mati bersama yang mau diperbaiki.
|
| Yang menjaganya cuma GEMBOK_SECRET. Kalau kunci itu bocor, bocor pula
| seluruh isi kartu di halaman ini — termasuk tombol hapus massal.
|
| ══ KENAPA IKUT GRUP 'web' ══
|
| Berkas ini di-require dari routes/web.php, yang seluruh isinya sudah
| berjalan di grup middleware 'web' (lihat RouteServiceProvider) — jadi
| grupnya TIDAK perlu (dan tidak boleh) disebut lagi di sini.
|
| Yang penting dari grup itu: perlindungan CSRF tetap berlaku pada aksi
| hapusnya. Tanpa itu, siapa pun yang tahu alamatnya bisa membuat halaman
| lain yang diam-diam mengirim permintaan hapus dari peramban orang yang
| tanda masuknya masih hidup — dan kuenya akan ikut terkirim dengan patuh.
*/

Route::middleware([\App\Http\Middleware\GerbangGembok::class])
    ->prefix('ui/bypass/gembok')
    ->name('gembok.')
    ->group(function () {

        // Halaman kartu-kartu aksi.
        Route::get('/', [GembokController::class, 'index'])->name('index');

        /*
         * Panggilan XHR milik halaman itu sendiri. Tidak membawa ?gembok-key=;
         * yang meloloskannya adalah kue tanda masuk yang dipasang saat halaman
         * pertama dibuka.
         *
         * Dibatasi lajunya. Bukan karena takut dibanjiri — gerbangnya sudah
         * menutup pintu bagi yang tak berkunci — melainkan karena aksi di
         * baliknya menghapus, dan permintaan hapus yang terkirim berkali-kali
         * karena tombol diklik gemas adalah cara yang mudah untuk menghapus
         * lebih banyak dari yang dimaksud.
         */
        Route::prefix('api')->middleware('throttle:60,1')->name('api.')->group(function () {

            // ── KARTU 1 — periksa penjadwalan satu kandidat ──────────────
            Route::get('/kandidat', [GembokController::class, 'kandidat'])->name('kandidat');

            // ── KARTU 2 — batalkan penjadwalan satu kandidat ─────────────
            Route::post('/batal-peserta', [GembokController::class, 'batalPeserta'])->name('batal-peserta');

            // ── KARTU 3 — hapus total per program / per hari ─────────────
            Route::get('/penjadwalan', [GembokController::class, 'penjadwalan'])->name('penjadwalan');
            // Pratinjau DULU, baru hapus. Dipisah jadi dua permintaan dengan
            // sengaja: yang pertama menghitung dan tidak mengubah apa pun,
            // yang kedua baru menjalankan.
            Route::post('/pratinjau', [GembokController::class, 'pratinjau'])->name('pratinjau');
            Route::post('/hapus-total', [GembokController::class, 'hapusTotal'])->name('hapus-total');

            // ── KARTU 4 — baris yatim ────────────────────────────────────
            Route::get('/kesehatan', [GembokController::class, 'kesehatan'])->name('kesehatan');
            Route::post('/bersihkan-yatim', [GembokController::class, 'bersihkanYatim'])->name('bersihkan-yatim');
        });
    });
