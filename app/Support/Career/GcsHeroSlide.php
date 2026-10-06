<?php

namespace App\Support\Career;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * WEB CAREER — pengunggah media hero slide (gambar desktop/mobile/poster + video) ke Google Cloud Storage.
 *
 * Struktur folder:
 *   career/hero/{Id_Master_Hero_Slide}/{slot}-{rand8}.{ext}
 *
 * Sufiks acak membuat setiap penggantian jadi objek baru (tidak ada cache basi);
 * objek lama dihapus best-effort SETELAH update DB sukses. Bila DB gagal,
 * pemanggil wajib hapus() objek baru (tidak boleh ada berkas yatim).
 *
 * Slot gambar (desktop/mobile/poster): jpg (jpeg dinormalkan), png, webp — maks 2 MB.
 * Slot video: mp4, webm — maks 20 MB.
 */
class GcsHeroSlide
{
    public const DISK = 'gcs';

    public const SLOT_GAMBAR = ['desktop', 'mobile', 'poster_desktop', 'poster_mobile'];

    public const SLOT_VIDEO = ['video_desktop', 'video_mobile'];

    public const MAKS_BYTE_GAMBAR = 2 * 1024 * 1024; // 2 MB

    public const MAKS_BYTE_VIDEO = 20 * 1024 * 1024; // 20 MB

    public const EKSTENSI_GAMBAR = ['jpg', 'jpeg', 'png', 'webp'];

    public const EKSTENSI_VIDEO = ['mp4', 'webm'];

    /** Unggah satu berkas (gambar/video) untuk satu slide; kembalikan path GCS. */
    public function unggah(int $id, string $slot, string $ext, string $konten): string
    {
        $ext = $this->normalkanExt($ext);
        $path = "career/hero/{$id}/{$slot}-" . Str::lower(Str::random(8)) . ".{$ext}";

        // DITULIS LEWAT getDriver(), BUKAN Storage::put().
        //
        // Storage::put() menelan penyebabnya: disk 'gcs' tidak menyetel
        // 'throw', jadi kegagalan apa pun — kredensial tidak terbaca, bucket
        // salah, service account tanpa izin — sama-sama kembali sebagai
        // `false`. Yang sampai ke layar cuma "Gagal mengunggah berkas ke GCS",
        // kalimat yang tidak membedakan salah ketik nama bucket dari izin IAM
        // yang belum diberikan, sehingga tiap kejadian menuntut menebak.
        //
        // getDriver() memakai Flysystem langsung dan MELEMPAR pesan aslinya.
        try {
            Storage::disk(self::DISK)->getDriver()->write($path, $konten);
        } catch (\Throwable $e) {
            throw new \RuntimeException(
                'Gagal mengunggah berkas ke GCS (bucket "'
                . config('filesystems.disks.' . self::DISK . '.bucket') . '", path "' . $path . '"): '
                . $e->getMessage(),
                0,
                $e
            );
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
