<?php

namespace App\Providers;

use App\Support\Seo\SeoMeta;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Satu instansi per permintaan: controller menimpa nilainya, lalu view
        // root membaca instansi YANG SAMA saat mencetak <head>.
        $this->app->singleton(SeoMeta::class);
    }

    public function boot(): void
    {
        /*
         * Meta halaman disuntikkan ke SETIAP view yang punya <head>, bukan
         * ditempel satu per satu di tiap controller. Konsekuensinya: halaman
         * baru — termasuk modul admin yang belum ada saat ini — otomatis
         * membawa judul, favicon, dan kartu pratinjau yang benar tanpa ada
         * yang perlu diingat oleh siapa pun.
         *
         * Cukup dipasang pada 'components.seo' — satu-satunya view yang membaca
         * $seo. Ia di-@include dari components.header, yang di-@include dari
         * app.blade.php, jadi setiap halaman Inertia melewatinya.
         */
        View::composer('components.seo', function ($view) {
            $view->with('seo', app(SeoMeta::class)->resolve(request()));
        });
    }
}
