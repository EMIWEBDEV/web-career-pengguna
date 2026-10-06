<?php

namespace App\Support\Portal;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\HeaderUtils;

/**
 * Menyajikan berkas dari bucket ke kandidat — SESUDAH pemanggil memastikan
 * berkasnya milik kandidat itu.
 *
 *   layani()     alihkan ke signed URL 15 menit (V4, nama berkas ikut)
 *   sajikanIsi() alirkan isinya dari server ini — untuk pratinjau pdf.js,
 *                yang memakai fetch dan butuh berkas dari domain yang sama
 *                (bucket tidak membuka CORS)
 */
final class SajianBerkas
{
    public static function layani(string $disk, ?string $path, ?string $nama = null, bool $unduh = false, ?string $mime = null)
    {
        if ($path) {
            try {
                $d = Storage::disk($disk);
                if ($d->exists($path)) {
                    $opsi = [
                        // V4: tanda tangan V2 tidak menyandikan response-content-*,
                        // dan spasi di "attachment; filename=…" membuat GCS menjawab 400.
                        'version' => 'v4',
                        'responseDisposition' => self::disposisi($unduh ? 'attachment' : 'inline', $nama ?: basename($path)),
                    ];
                    $jenis = $mime ?: (str_ends_with(strtolower($path), '.pdf') ? 'application/pdf' : null);
                    if ($jenis) {
                        $opsi['responseType'] = $jenis;
                    }

                    return redirect()->away($d->temporaryUrl($path, now()->addMinutes(15), $opsi));
                }
            } catch (\Throwable $e) {
                Log::warning("[BERKAS] signed URL gagal ({$disk}): ".$e->getMessage());
            }
        }

        abort(404, 'Berkas tidak ditemukan.');
    }

    public static function sajikanIsi(string $disk, ?string $path, ?string $nama = null)
    {
        if ($path && str_ends_with(strtolower($path), '.pdf')) {
            try {
                $d = Storage::disk($disk);
                if ($d->exists($path)) {
                    $ukuran = (int) $d->size($path);

                    return response()->stream(function () use ($d, $path) {
                        $alir = $d->readStream($path);
                        if (is_resource($alir)) {
                            fpassthru($alir);
                            fclose($alir);
                        }
                    }, 200, array_filter([
                        'Content-Type' => 'application/pdf',
                        'Content-Length' => $ukuran ?: null,
                        'Content-Disposition' => self::disposisi('inline', $nama ?: basename($path)),
                        'Cache-Control' => 'private, max-age=300',
                        'X-Content-Type-Options' => 'nosniff',
                    ]));
                }
            } catch (\Throwable $e) {
                Log::warning("[BERKAS] isi berkas gagal dibaca ({$disk}): ".$e->getMessage());
            }
        }

        abort(404, 'Berkas tidak ditemukan.');
    }

    /**
     * Content-Disposition beserta nama berkasnya (RFC 6266): nama asli dalam
     * UTF-8 plus cadangan ASCII untuk peramban lama.
     */
    private static function disposisi(string $jenis, string $nama): string
    {
        $nama = trim(str_replace(['/', '\\', '"'], '-', $nama)) ?: 'berkas.pdf';
        $cadangan = trim(str_replace('%', '', (string) preg_replace('/[^\x20-\x7E]/', '', Str::ascii($nama)))) ?: 'berkas.pdf';

        try {
            return HeaderUtils::makeDisposition($jenis, $nama, $cadangan);
        } catch (\Throwable $e) {
            return $jenis.'; filename="berkas.pdf"';
        }
    }
}
