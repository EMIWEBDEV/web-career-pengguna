<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Di lingkungan lokal, larang browser & proxy menyimpan respons apa pun.
 *
 * Tanpa ini, Chrome sering menyajikan ulang halaman Inertia / respons axios dari
 * memory cache sehingga perubahan Controller terlihat "belum masuk" padahal server
 * sudah mengirim data baru. Di produksi middleware ini tidak melakukan apa-apa.
 */
class NoCacheInLocal
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! app()->environment('local')) {
            return $response;
        }

        // BinaryFileResponse & StreamedResponse (unduhan) dibiarkan apa adanya.
        if (! $response instanceof \Illuminate\Http\Response && ! $response instanceof \Illuminate\Http\JsonResponse) {
            return $response;
        }

        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');

        return $response;
    }
}
