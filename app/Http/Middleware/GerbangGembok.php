<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * GEMBOK — gerbang halaman bypass /ui/bypass/gembok.
 *
 * ══ KENAPA ADA ══
 *
 * Di balik gerbang ini ada aksi yang MENGHAPUS penjadwalan beserta seluruh
 * runtunnya, tanpa satu pun gerbang panel admin. Tidak ada login, tidak ada
 * peran, tidak ada career.permission. Itu bukan kelalaian — halaman ini
 * dipakai justru ketika jalur normal sedang tidak bisa dipakai.
 *
 * Maka satu-satunya yang memisahkan ia dari seluruh internet adalah kunci di
 * GEMBOK_SECRET.
 *
 * ══ POLANYA SAMA DENGAN LOG VIEWER, ALASANNYA JUGA ══
 *
 * Lihat App\Http\Middleware\GerbangLogViewer untuk uraian panjangnya:
 * kenapa 404 dan bukan 403 (403 mengaku halamannya ada, dan itu undangan
 * untuk terus mencoba), kenapa tanda masuknya kue dan bukan sesi (halaman ini
 * memanggil /ui/bypass/gembok/api/* lewat XHR yang tidak membawa query), dan
 * kenapa yang disimpan di kue adalah SIDIK HMAC dan bukan kuncinya.
 *
 * Yang BERBEDA dari Log Viewer, dan sengaja:
 *
 *   1. Kuenya dienkripsi seperti kue biasa — TIDAK didaftarkan di
 *      EncryptCookies::$except. Pengecualian itu ada di Log Viewer karena
 *      rute API-nya milik pustaka pihak ketiga yang lewat jalur lain. Di sini
 *      seluruh rute — halaman maupun API — ada di grup 'web' yang sama, jadi
 *      kuenya dibongkar oleh pembongkar yang sama. Tidak ada alasan
 *      melemahkannya.
 *
 *   2. Masa berlakunya pendek (bawaan 2 jam, bukan 12). Halaman yang
 *      menghapus tidak boleh menganggur terbuka semalaman.
 *
 *   3. Setiap penolakan DICATAT. Log Viewer hanya dibaca; di sini percobaan
 *      masuk yang gagal adalah orang yang menebak-nebak jalan menuju tombol
 *      hapus massal, dan itu perlu terlihat di log.
 */
class GerbangGembok
{
    /** Nama query yang membawa kunci: /ui/bypass/gembok?gembok-key=… */
    private const QUERY = 'gembok-key';

    /** Nama kue penanda masuk. Dienkripsi seperti kue lain — lihat catatan di atas. */
    public const KUE = 'gembok_pass';

    /**
     * Cakupan kue. Dipersempit ke prefix halaman ini saja supaya tanda
     * masuknya TIDAK ikut terkirim ke seluruh permintaan situs — termasuk ke
     * halaman publik yang memuat aset pihak ketiga.
     */
    private const JALUR = '/ui/bypass';

    public function handle(Request $request, Closure $next): Response
    {
        $rahasia = (string) config('gembok.secret', '');

        // GAGAL TERTUTUP. Kunci yang belum diatur BUKAN berarti bebas masuk.
        // Server yang lupa disetel adalah server yang paling perlu dilindungi,
        // dan halaman ini menghapus data — bukan sekadar menampilkannya.
        if ($rahasia === '') {
            abort(404);
        }

        // Kunci pendek membuat penebakan jadi masuk akal. Menolaknya di sini
        // lebih baik daripada memberi rasa aman palsu kepada yang mengisi
        // GEMBOK_SECRET=123 lalu menganggap halamannya terkunci.
        if (strlen($rahasia) < 32) {
            Log::channel('web_career')->error(
                'GEMBOK ditutup: GEMBOK_SECRET terlalu pendek (minimal 32 karakter).'
            );

            abort(404);
        }

        $dikirim = (string) ($request->query(self::QUERY) ?? '');

        if ($dikirim !== '') {
            // hash_equals: waktunya tidak bergantung pada berapa karakter yang
            // cocok, sehingga kunci tidak bisa ditebak huruf demi huruf dari
            // selisih waktu jawaban.
            if (! hash_equals($rahasia, $dikirim)) {
                $this->catatTolak($request, 'kunci salah');

                abort(404);
            }

            $kue = Cookie::make(
                self::KUE,
                self::sidik($rahasia),
                (int) config('gembok.berlaku', 120),
                self::JALUR,
                null,
                $request->isSecure(),
                true,      // httpOnly — tak terbaca JavaScript
                false,
                'Lax',
            );

            Log::channel('web_career')->warning(
                'GEMBOK dibuka dari IP '.$request->ip().' — '.$request->userAgent()
            );

            // ALAMATNYA DIBERSIHKAN DARI KUNCI. Sesudah kuncinya diterima,
            // pengunjung dilempar ke alamat tanpa query supaya kunci tidak
            // mengendap di riwayat peramban, di header Referer, maupun di log
            // akses server.
            if ($request->isMethod('GET') && ! $request->expectsJson()) {
                $sisa = $request->except(self::QUERY);

                return redirect()
                    ->to($request->url().($sisa ? '?'.http_build_query($sisa) : ''))
                    ->withCookie($kue);
            }

            return $next($request)->withCookie($kue);
        }

        // Tanpa kunci → hanya lolos bila membawa tanda masuk yang sah. Inilah
        // yang membuat panggilan XHR halaman ini berjalan tanpa kuncinya ikut
        // disebut lagi di tiap permintaan.
        if (hash_equals(self::sidik($rahasia), (string) $request->cookie(self::KUE, ''))) {
            return $next($request);
        }

        $this->catatTolak($request, 'tanpa kunci / tanda masuk kedaluwarsa');

        abort(404);
    }

    /**
     * Sidik kunci yang disimpan di kue — BUKAN kuncinya sendiri.
     *
     * Terikat APP_KEY, sehingga tak bisa ditempa dari luar dan otomatis gugur
     * begitu kunci atau APP_KEY diganti.
     */
    private static function sidik(string $rahasia): string
    {
        return hash_hmac('sha256', 'gembok|'.$rahasia, (string) config('app.key'));
    }

    /**
     * Percobaan masuk yang gagal dicatat.
     *
     * Kuncinya sendiri TIDAK PERNAH ikut tercatat — menuliskannya ke log
     * berarti membocorkannya ke tempat yang justru dibaca banyak orang.
     */
    private function catatTolak(Request $request, string $sebab): void
    {
        Log::channel('web_career')->warning(
            'GEMBOK menolak ('.$sebab.') — IP '.$request->ip()
            .' → '.$request->path()
        );
    }
}
