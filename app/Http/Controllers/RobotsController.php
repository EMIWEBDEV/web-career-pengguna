<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * robots.txt yang menyesuaikan lingkungan.
 *
 * DULU berkas statis public/robots.txt berisi "Disallow: /" — benar untuk
 * lokal, tapi ikut terbawa ke produksi sehingga Google tidak pernah boleh
 * mengindeks portal karier sama sekali. Sekarang isinya ditentukan
 * config('seo.indexable'): produksi mengizinkan, staging/lokal menolak, tanpa
 * ada berkas yang perlu diedit manual saat deploy.
 *
 * Perayap media sosial DIIZINKAN SECARA EKSPLISIT walau mesin pencari ditolak.
 * Itu yang membuat pratinjau WhatsApp/Facebook/LinkedIn tetap muncul saat
 * tautan staging dibagikan untuk diuji — sementara halaman staging-nya sendiri
 * tetap tidak masuk hasil pencarian.
 *
 * Catatan: berkas statis public/robots.txt SENGAJA dihapus. Selama berkas itu
 * ada, peladen web menyajikannya lebih dulu dan rute ini tidak pernah jalan.
 */
class RobotsController extends Controller
{
    /** Perayap pratinjau tautan — bukan mesin pencari, selalu diizinkan. */
    private const PERAYAP_SOSIAL = [
        'WhatsApp',
        'facebookexternalhit',
        'Facebot',
        'Twitterbot',
        'LinkedInBot',
        'TelegramBot',
        'Slackbot-LinkExpanding',
        'Discordbot',
        'Pinterestbot',
        'SkypeUriPreview',
    ];

    public function __invoke(Request $request): Response
    {
        $baris = [];

        foreach (self::PERAYAP_SOSIAL as $agen) {
            $baris[] = "User-agent: {$agen}";
            $baris[] = 'Allow: /';
            $baris[] = '';
        }

        $baris[] = 'User-agent: *';

        if (config('seo.indexable', false)) {
            // Yang ditutup hanyalah area yang memang bukan untuk publik.
            //
            // Panel admin TIDAK bisa dicantumkan di sini: ia berbagi awalan
            // path /karir dengan halaman publik (/karir/lowongan, /karir/tim),
            // jadi "Disallow: /karir" akan ikut mengunci halaman yang justru
            // paling ingin diindeks. Penjaganya ada dua lapis lain yang lebih
            // tepat sasaran: seluruh rute admin butuh sesi (perayap dipulangkan
            // ke halaman masuk), dan SeoMeta mencetak "noindex" untuk setiap
            // rute bernama career.admin.*.
            $baris[] = 'Disallow: /kandidat/';
            $baris[] = 'Disallow: /api/';
            $baris[] = 'Disallow: /feedback/';
            $baris[] = 'Disallow: /karir/diagnostik';
            $baris[] = 'Disallow: /profil';
            $baris[] = 'Disallow: /logout';
            $baris[] = 'Disallow: /ganti-sandi';
            $baris[] = 'Allow: /';
        } else {
            $baris[] = 'Disallow: /';
        }

        // Host diambil dari permintaan, bukan dari APP_URL — supaya benar di
        // lokal, staging, dan produksi tanpa perubahan apa pun.
        $baris[] = '';
        $baris[] = 'Host: ' . $request->getHost();

        return response(implode("\n", $baris) . "\n", 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
