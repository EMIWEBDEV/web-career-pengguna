<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array<int, string>
     */
    protected $except = [
        // Penerima Cloud Tasks (stackkit) — diamankan token OIDC Cloud Tasks.
        '/handle-task',
        // Konfirmasi kehadiran kandidat — POST bertanda tangan (middleware
        // `signed`): tanda tangan tautan adalah rahasianya; peramban dalam
        // aplikasi surel kerap tanpa cookie sesi sehingga CSRF berujung 419.
        'karir/konfirmasi/*/*/jawab',
        'karir/konfirmasi/*/*/cabut',
    ];
}
