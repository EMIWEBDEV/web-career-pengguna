<?php

namespace App\Exceptions;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Inertia\Inertia;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->renderable(function (AuthenticationException $e, Request $request) {
            if ($request->header('X-Inertia')) {
                return Inertia::location(route('login'));
            }

            if ($request->expectsJson()) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }

            return redirect()->guest(route('login'));
        });

        $this->renderable(function (TokenMismatchException $e, Request $request) {
            if ($request->header('X-Inertia')) {
                return Inertia::location(route('login'));
            }

            if ($request->expectsJson()) {
                return response()->json(['message' => 'Session expired.'], 419);
            }

            return redirect()->guest(route('login'));
        });

        $this->reportable(function (Throwable $e) {
            //
        });
    }

    /**
     * Render an exception into an HTTP response.
     *
     * Saat APP_DEBUG=false (produksi/staging), semua HttpException (403/404/500/503,
     * dll.) yang sebelumnya jatuh ke Blade `errors/*` dialihkan ke komponen Vue
     * `Pages/Error.vue` lewat Inertia, agar tampilan error konsisten dengan aplikasi.
     *
     * Saat APP_DEBUG=true (development) kita TIDAK mengintervensi: biarkan Ignition /
     * Blade default tampil penuh agar developer tetap dapat stacktrace.
     */
    public function render($request, Throwable $e)
    {
        // Biarkan renderable() (Authentication 401 & TokenMismatch 419) menang lebih
        // dulu — keduanya sudah jadi redirect ke login sebelum sampai sini.
        $response = parent::render($request, $e);

        // ── Kapan halaman desain dipakai saat APP_DEBUG=true? ──
        // HttpException (abort 403/404/419/429/503, dsb.) adalah keadaan yang
        // MEMANG DIRANCANG — bukan bug — jadi selalu tampilkan halaman desain,
        // termasuk saat development. Kalau tidak, developer tidak pernah melihat
        // tampilan yang sebenarnya dilihat pengguna.
        //
        // Exception ASLI (bug → 500) tetap diserahkan ke Ignition saat debug,
        // supaya stacktrace tidak hilang. Paksa lewat ERROR_PAGE_FORCE=true bila
        // ingin melihat tampilan 500 versi pengguna.
        if (config('app.debug') === true) {
            $httpException = $e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface
                || $e instanceof \Illuminate\Session\TokenMismatchException;

            // config(), BUKAN env(): env() di luar folder config bernilai null
            // begitu `php artisan config:cache` dijalankan.
            if (! $httpException && ! config('app.error_page_force', false)) {
                return $response;
            }
        }

        // Jangan Vue-kan response file (download) atau streamed.
        if ($response instanceof \Symfony\Component\HttpFoundation\BinaryFileResponse
            || $response instanceof \Symfony\Component\HttpFoundation\StreamedResponse) {
            return $response;
        }

        // Request API/JSON tetap dapat response default (JSON), bukan halaman Vue.
        if ($request->expectsJson() || $request->is('api/*')) {
            return $response;
        }

        $status = method_exists($response, 'getStatusCode') ? $response->getStatusCode() : 200;

        // Hanya alihkan error 4xx/5xx. Selain itu (redirect 3xx, 200) biarkan apa adanya.
        if ($status < 400) {
            return $response;
        }

        try {
            // Kode referensi singkat — dicetak di halaman error & di log, supaya
            // kandidat bisa menyebutkannya saat menghubungi tim rekrutmen.
            $referensi = strtoupper(substr(md5($request->fullUrl() . microtime()), 0, 8));

            if ($status >= 500) {
                \Illuminate\Support\Facades\Log::error("[ERR-{$referensi}] {$status} {$request->fullUrl()} — " . $e->getMessage());
            }

            // Judul tab halaman galat disamakan dengan yang nanti dipasang
            // Error.vue, supaya tidak berkedip dari judul default ke judul
            // galat begitu Vue hidup.
            \App\Support\Seo\Seo::set(['title' => $this->errorTitleFor($status)]);

            return Inertia::render('Error', [
                'status' => $status,
                'message' => $this->errorMessageFor($status),
                'homeUrl' => \App\Support\Career\Shell\IdentitasShell::beranda(),
                'referensi' => $referensi,
            ])->toResponse($request)->setStatusCode($status);
        } catch (Throwable $inertiaError) {
            // Bila render Inertia gagal (mis. share() butuh DB yang sedang down),
            // jangan biarkan layar kosong — kembalikan response asli (Blade fallback).
            // Kegagalannya DICATAT: tanpa ini, halaman error desain bisa diam-diam
            // tidak pernah tampil dan tidak ada yang tahu sebabnya.
            try {
                \Illuminate\Support\Facades\Log::error(
                    '[ERROR-PAGE] gagal merender halaman status ' . $status . ': '
                    . get_class($inertiaError) . ' — ' . $inertiaError->getMessage()
                    . ' @ ' . $inertiaError->getFile() . ':' . $inertiaError->getLine()
                );
            } catch (Throwable $abaikan) {
                // logging pun gagal — jangan sampai menambah masalah.
            }

            return $response;
        }
    }

    /**
     * Pesan generik (Bahasa Indonesia) per status. TIDAK memakai $e->getMessage()
     * agar detail/stacktrace tidak bocor ke pengguna di produksi.
     */
    /**
     * Judul tab per status — cermin peta `judulTab` di resources/js/Pages/Error.vue.
     * Keduanya harus sama persis; kalau salah satu diubah, ubah juga yang lain.
     */
    private function errorTitleFor(int $status): string
    {
        return match ($status) {
            400 => 'Permintaan Tidak Valid',
            401 => 'Sesi Berakhir',
            403 => 'Akses Ditolak',
            404 => 'Halaman Tidak Ditemukan',
            419 => 'Halaman Kedaluwarsa',
            429 => 'Terlalu Banyak Permintaan',
            500 => 'Kesalahan Server',
            503 => 'Mode Pemeliharaan',
            default => 'Terjadi Kesalahan',
        };
    }

    private function errorMessageFor(int $status): string
    {
        return match ($status) {
            403 => 'Anda tidak memiliki izin untuk mengakses halaman ini.',
            404 => 'Maaf, halaman yang Anda cari tidak dapat ditemukan.',
            419 => 'Sesi Anda telah berakhir. Silakan muat ulang atau masuk kembali.',
            429 => 'Terlalu banyak permintaan. Silakan coba beberapa saat lagi.',
            500 => 'Terjadi kesalahan pada server. Tim kami sedang menanganinya.',
            503 => 'Sistem sedang dalam perbaikan. Silakan kembali beberapa saat lagi.',
            default => 'Terjadi kesalahan. Silakan coba lagi.',
        };
    }
}
