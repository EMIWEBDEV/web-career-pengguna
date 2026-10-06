<?php

namespace App\Providers;

use App\Support\Seo\SeoMeta;
use App\Support\Sinkron\PenjagaZona;
use Illuminate\Support\Facades\URL;
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
        // Zona luar menolak menyala bila memegang kredensial database admin.
        PenjagaZona::periksa();

        // Tautan bertanda tangan (konfirmasi kehadiran, surat pengantar) dibuat
        // zona dalam untuk surelnya → kuncinya TAUTAN_KUNCI yang dipegang kedua
        // zona, bukan APP_KEY aplikasi ini. Cadangan APP_KEY hanya untuk lokal.
        URL::setKeyResolver(fn () => config('sinkron.kunci_tautan') ?: config('app.key'));

        /*
         * Meta halaman disuntikkan ke view 'components.seo' — satu-satunya view
         * yang membaca $seo. Ia di-@include dari components.header, yang
         * di-@include dari app.blade.php, jadi setiap halaman Inertia
         * melewatinya.
         */
        View::composer('components.seo', function ($view) {
            $view->with('seo', app(SeoMeta::class)->resolve(request()));
        });
    }
}
