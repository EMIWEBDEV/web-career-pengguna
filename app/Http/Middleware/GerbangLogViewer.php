<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Symfony\Component\HttpFoundation\Response;

/**
 * LOG VIEWER — hanya terbuka bagi yang membawa KUNCI RAHASIA.
 *
 * ══ KENAPA ADA ══
 *
 * /log-viewer menyajikan ISI BERKAS LOG apa adanya: jejak galat lengkap
 * beserta nama berkas dan nomor barisnya, kueri SQL yang gagal berikut
 * nilainya, muatan permintaan, dan tak jarang email maupun token yang ikut
 * tercatat. Selama ini rutenya hanya bermiddleware 'web' — artinya siapa pun
 * yang menebak alamatnya bisa membacanya, tanpa perlu masuk sama sekali.
 *
 * ══ KENAPA 404, BUKAN 403 ══
 *
 * 403 mengaku: ia memberi tahu bahwa halamannya ADA dan hanya perlu kunci yang
 * benar — undangan untuk mencoba terus. 404 tidak mengaku apa-apa; bagi yang
 * tidak membawa kunci, halaman ini sama saja tidak pernah dibuat.
 *
 * ══ KENAPA TANDA MASUKNYA BUKAN SESI ══
 *
 * Log Viewer adalah aplikasi satu halaman: sesudah halaman pertama, isinya
 * ditarik lewat /log-viewer/api/* tanpa membawa query apa pun. Jadi izinnya
 * harus melekat pada sesuatu yang ikut terbawa sendiri.
 *
 * Pilihan yang wajar adalah sesi — tapi sesi pada rute API-nya baru menyala
 * bila header Referer kebetulan cocok dengan `api_stateful_domains`. Satu
 * domain yang lupa didaftarkan setelah pindah server, dan seluruh isi Log
 * Viewer jadi 404 tanpa satu pun petunjuk kenapa. Pengaman tidak boleh
 * bergantung pada setelan yang mudah terlupakan.
 *
 * Maka dipakai KUE (cookie) tersendiri yang dibaca-tulis middleware ini
 * sendiri, tanpa perantara. Isinya BUKAN kuncinya, melainkan sidik HMAC-nya:
 * bocornya kue tidak membocorkan kunci, dan kue palsu tak bisa dibuat tanpa
 * APP_KEY.
 */
class GerbangLogViewer
{
    /** Nama query yang membawa kunci: /log-viewer?secret-key=… */
    private const QUERY = 'secret-key';

    /**
     * Nama kue penanda. Terdaftar di EncryptCookies::$except supaya nilainya
     * terbaca apa adanya di rute halaman MAUPUN rute API — yang kedua tidak
     * selalu melewati pembongkar kue, dan kue yang di satu sisi terenkripsi
     * lalu di sisi lain terbaca mentah tidak akan pernah cocok.
     */
    public const KUE = 'lv_pass';

    /** Berapa lama tanda masuk berlaku (menit). */
    private const BERLAKU = 720;

    public function handle(Request $request, Closure $next): Response
    {
        $rahasia = (string) config('log-viewer.secret', '');

        // (1) GAGAL TERTUTUP. Kunci yang belum diatur di .env TIDAK berarti
        //     "bebas masuk", melainkan tertutup untuk semua. Pengaman yang mati
        //     sendiri saat lupa dipasang bukan pengaman — dan justru di server
        //     yang lupa disetel itulah lognya paling menarik bagi orang luar.
        if ($rahasia === '') {
            abort(404);
        }

        $dikirim = (string) ($request->query(self::QUERY) ?? '');

        if ($dikirim !== '') {
            // hash_equals: perbandingan yang waktunya TIDAK bergantung pada
            // berapa banyak karakter yang cocok. Perbandingan biasa berhenti di
            // huruf pertama yang berbeda, dan selisih waktunya — walau sangat
            // kecil — cukup untuk menebak kunci huruf demi huruf.
            if (! hash_equals($rahasia, $dikirim)) {
                abort(404);
            }

            $kue = Cookie::make(
                self::KUE,
                self::sidik($rahasia),
                self::BERLAKU,
                '/' . trim((string) config('log-viewer.route_path', 'log-viewer'), '/'),
                null,
                $request->isSecure(),
                true,      // httpOnly — tidak bisa dibaca JavaScript
                false,
                'Lax',
            );

            // ALAMATNYA DIBERSIHKAN DARI KUNCI. Sesudah diterima, pengunjung
            // dilempar ke alamat tanpa query — supaya kuncinya tidak mengendap
            // di riwayat peramban, di header Referer saat halaman memuat aset,
            // maupun di log akses server. Ironis bila kunci pembuka log justru
            // tercatat di dalam log.
            //
            // Hanya untuk permintaan halaman: pengalihan pada panggilan XHR
            // akan dibaca Log Viewer sebagai data, bukan perintah pindah.
            if ($request->isMethod('GET') && ! $request->expectsJson()) {
                $sisa = $request->except(self::QUERY);

                return redirect()
                    ->to($request->url() . ($sisa ? '?' . http_build_query($sisa) : ''))
                    ->withCookie($kue);
            }

            return $next($request)->withCookie($kue);
        }

        // (2) Tanpa kunci → hanya lolos bila membawa tanda masuk yang sah.
        //     Inilah yang membuat /log-viewer/api/* milik halaman yang sudah
        //     terbuka tetap berjalan tanpa kuncinya ikut disebut lagi.
        if (hash_equals(self::sidik($rahasia), (string) $request->cookie(self::KUE, ''))) {
            return $next($request);
        }

        abort(404);
    }

    /**
     * Sidik kunci yang disimpan di kue.
     *
     * BUKAN kuncinya sendiri: kue tersimpan di cakram peramban dan ikut
     * terkirim di tiap permintaan, jadi menaruh kunci mentah di sana sama saja
     * menyebarkannya. Sidik ini terikat pada APP_KEY, sehingga tak bisa
     * ditempa dari luar dan otomatis gugur begitu kunci atau APP_KEY diganti.
     */
    private static function sidik(string $rahasia): string
    {
        return hash_hmac('sha256', 'log-viewer|' . $rahasia, (string) config('app.key'));
    }
}
