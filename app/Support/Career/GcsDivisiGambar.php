<?php

namespace App\Support\Career;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * WEB CAREER — pengunggah gambar informasi divisi/sub-divisi ke Google Cloud Storage.
 *
 * Struktur folder:
 *   career/divisi/{Id_Divisi}/{slot}-{rand8}.{ext}
 *   career/sub-divisi/{Id_Sub_Divisi}/{slot}-{rand8}.{ext}
 *
 * Sufiks acak membuat setiap penggantian jadi objek baru (tidak ada cache basi);
 * objek lama dihapus best-effort SETELAH update DB sukses. Bila DB gagal,
 * pemanggil wajib hapus() objek baru (tidak boleh ada berkas yatim).
 *
 * Ekstensi diizinkan: jpg (jpeg dinormalkan), png, webp. Maks 2 MB.
 */
class GcsDivisiGambar
{
    public const DISK = 'gcs';

    public const MAKS_BYTE = 2 * 1024 * 1024; // 2 MB

    public const EKSTENSI_DIIZINKAN = ['jpg', 'jpeg', 'png', 'webp'];

    /** Unggah satu gambar; kembalikan path GCS. */
    public function unggah(string $jenis, int $id, string $slot, string $ext, string $konten): string
    {
        $folder = $jenis === 'sub' ? 'career/sub-divisi' : 'career/divisi';
        $ext = $this->normalkanExt($ext);
        $path = "{$folder}/{$id}/{$slot}-" . Str::lower(Str::random(8)) . ".{$ext}";

        if (! Storage::disk(self::DISK)->put($path, $konten)) {
            throw new \RuntimeException('Gagal mengunggah gambar ke GCS.');
        }

        return $path;
    }

    /** Hapus daftar path (kompensasi / bersih-bersih objek lama). */
    public function hapus(array $paths): void
    {
        foreach (array_filter($paths) as $p) {
            try {
                Storage::disk(self::DISK)->delete($p);
            } catch (\Throwable $e) {
                // Best-effort; jangan menggagalkan alur pembersihan.
            }
        }
    }

    public function normalkanExt(string $ext): string
    {
        $ext = strtolower(ltrim($ext, '.'));

        return $ext === 'jpeg' ? 'jpg' : $ext;
    }
}
