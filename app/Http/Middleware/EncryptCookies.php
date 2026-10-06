<?php

namespace App\Http\Middleware;

use Illuminate\Cookie\Middleware\EncryptCookies as Middleware;

class EncryptCookies extends Middleware
{
    /**
     * The names of the cookies that should not be encrypted.
     *
     * @var array<int, string>
     */
    protected $except = [
        // Tanda masuk Log Viewer. DIKECUALIKAN dengan sengaja: rute
        // /log-viewer/api/* tidak selalu melewati pembongkar kue, sehingga kue
        // yang terenkripsi di satu sisi dan terbaca mentah di sisi lain tidak
        // akan pernah cocok — dan seluruh isi Log Viewer jadi 404.
        //
        // Aman tidak dienkripsi karena isinya BUKAN rahasia apa pun, melainkan
        // sidik HMAC yang terikat APP_KEY: tak bisa ditempa, dan bocornya tidak
        // membocorkan kuncinya. Lihat App\Http\Middleware\GerbangLogViewer.
        \App\Http\Middleware\GerbangLogViewer::KUE,
    ];
}
