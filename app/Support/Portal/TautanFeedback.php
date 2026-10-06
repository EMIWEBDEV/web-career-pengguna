<?php

namespace App\Support\Portal;

use Vinkla\Hashids\Facades\Hashids;

/**
 * Tautan isian FEEDBACK: /feedback/{kode}/{hashids}/{signature}
 *
 *   kode      = kode lamaran — kunci yang dikenal KEDUA zona (id lamaran publik
 *               tidak pernah diketahui zona dalam, dan lamaran lama tidak punya)
 *   hashids   = Hashids(id feedback zona dalam)
 *   signature = 32 karakter pertama HMAC-SHA256("{id feedback}|{kode}", TAUTAN_KUNCI)
 *
 * Bentuknya berbasis path (bukan query bertanda tangan) karena halaman
 * mengirim jawabannya ke `window.location.pathname` — query akan hilang.
 * Zona dalam membuat tautan yang sama untuk surelnya dengan kunci yang sama
 * (App\Support\Sinkron\TautanPengguna::feedback di project admin).
 */
final class TautanFeedback
{
    public static function url(int $feedbackId, string $kode): string
    {
        return url('/feedback/'.rawurlencode($kode).'/'.Hashids::encode($feedbackId).'/'.self::tanda($feedbackId, $kode));
    }

    /**
     * @return array{0: int, 1: string}|null [id feedback, kode lamaran] bila sah
     */
    public static function urai(string $kode, string $hashids, string $signature): ?array
    {
        $angka = Hashids::decode($hashids);
        if (count($angka) !== 1 || $kode === '' || (string) config('sinkron.kunci_tautan') === '') {
            return null;
        }
        $feedbackId = (int) $angka[0];

        return hash_equals(self::tanda($feedbackId, $kode), $signature) ? [$feedbackId, $kode] : null;
    }

    private static function tanda(int $feedbackId, string $kode): string
    {
        return substr(hash_hmac('sha256', $feedbackId.'|'.$kode, (string) config('sinkron.kunci_tautan')), 0, 32);
    }
}
