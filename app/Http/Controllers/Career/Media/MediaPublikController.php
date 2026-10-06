<?php

namespace App\Http\Controllers\Career\Media;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Vinkla\Hashids\Facades\Hashids;

/**
 * WEB CAREER — media halaman publik (gambar/video hero, gambar tim).
 *
 * Path SELALU dibaca dari kolom salinan master, tidak pernah dari URL; objeknya
 * di bucket PUBLIK (disalin Sync Worker dari zona dalam bersama barisnya).
 */
class MediaPublikController extends Controller
{
    private const DISK = 'publik';

    /** slot → kolom N_WEB_CAREERS_Master_Hero_Slide. */
    private const HERO = [
        'desktop' => 'Gambar_Desktop',
        'mobile' => 'Gambar_Mobile',
        'video_desktop' => 'Video_Desktop_Url',
        'video_mobile' => 'Video_Mobile_Url',
        'poster_desktop' => 'Video_Desktop_Poster',
        'poster_mobile' => 'Video_Mobile_Poster',
    ];

    /** slot → kolom info divisi / sub-divisi. */
    private const TIM = [
        'header' => 'Img_Path_Header',
        'utama' => 'Img_Path_Utama',
        'img2' => 'Img_Path_2',
        'img3' => 'Img_Path_3',
    ];

    private const MIME = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
        'mp4' => 'video/mp4',
        'webm' => 'video/webm',
    ];

    /** GET /karir/hero-media/{id}/{slot} */
    public function hero(string $id, string $slot)
    {
        $realId = Hashids::decode($id)[0] ?? null;
        $kolom = self::HERO[$slot] ?? null;
        abort_if(! $realId || ! $kolom, 404);

        return $this->alirkan(DB::table('N_WEB_CAREERS_Master_Hero_Slide')->where('Id_Master_Hero_Slide', $realId)->value($kolom));
    }

    /** GET /karir/tim-img/{jenis}/{id}/{slot} — jenis: divisi | sub */
    public function tim(string $jenis, string $id, string $slot)
    {
        $realId = Hashids::decode($id)[0] ?? null;
        $kolom = self::TIM[$slot] ?? null;
        abort_if(! $realId || ! $kolom, 404);

        [$tabel, $kunci] = $jenis === 'sub'
            ? ['N_WEB_CAREERS_Sub_Divisi_Informations', 'Id_Sub_Divisi']
            : ['N_WEB_CAREERS_Division_Informations', 'Id_Divisi'];

        return $this->alirkan(DB::table($tabel)->where($kunci, $realId)->value($kolom));
    }

    private function alirkan(?string $path)
    {
        abort_if(! $path, 404);

        $stream = Storage::disk(self::DISK)->readStream($path);
        abort_if(! $stream, 404);

        return response()->stream(function () use ($stream) {
            fpassthru($stream);
        }, 200, [
            'Content-Type' => self::MIME[strtolower(pathinfo($path, PATHINFO_EXTENSION))] ?? 'application/octet-stream',
            'Cache-Control' => 'public, max-age=86400, immutable',
        ]);
    }
}
