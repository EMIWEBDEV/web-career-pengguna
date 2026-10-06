<?php

namespace App\Support\Career;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * WEB CAREER — pengunggah berkas KANDIDAT ke bucket KARANTINA.
 *
 * Bucket karantina adalah satu-satunya bucket yang ditulis aplikasi publik.
 * Isinya belum dipercaya: zona dalam mengambilnya untuk dipindai sebelum
 * dipakai, dan bucket ini menghapus objek lama secara otomatis.
 *
 * Struktur folder:
 *   apply-form/{tahun}/{bulan}/{tanggal}/{nama-kandidat}/{nama-berkas}/{nama-berkas}-{acak}.{ext}
 *   formulir-tahap/…   berkas formulir tahap & berkas aktivitas (tes, MCU mandiri)
 *
 * Nama objek SELALU membawa akhiran acak (unggahUnik): path deterministik
 * membuat dua kandidat bernama sama di hari yang sama saling menimpa CV.
 */
class GcsBerkas
{
    public const DISK = 'karantina';

    /** Path folder dasar berkas lamaran kandidat pada tanggal tertentu. */
    public function folderKandidat(string $tahun, string $bulan, string $tanggal, string $namaKandidat): string
    {
        return 'apply-form/'.$tahun.'/'.$bulan.'/'.$tanggal.'/'.$this->slug($namaKandidat);
    }

    /** Path folder berkas formulir tahap & berkas aktivitas. */
    public function folderTahap(string $tahun, string $bulan, string $tanggal, string $namaKandidat): string
    {
        return 'formulir-tahap/'.$tahun.'/'.$bulan.'/'.$tanggal.'/'.$this->slug($namaKandidat);
    }

    /**
     * Unggah berkas kandidat dengan nama objek yang pasti UNIK.
     *
     * @return string path objek bila sukses
     *
     * @throws \RuntimeException bila gagal unggah
     */
    public function unggahUnik(string $folderKandidat, string $namaBerkas, string $ext, string $konten): string
    {
        $slug = $this->slug($namaBerkas);
        $ext = $this->normalkanExt($ext);
        $path = "{$folderKandidat}/{$slug}/{$slug}-".Str::lower(Str::random(10)).".{$ext}";

        if (! Storage::disk(self::DISK)->put($path, $konten)) {
            throw new \RuntimeException("Gagal mengunggah berkas {$namaBerkas}.");
        }

        return $path;
    }

    /** Unggah foto verifikasi (nama acak, boleh lebih dari satu). */
    public function unggahFoto(string $folderKandidat, string $konten): string
    {
        $path = "{$folderKandidat}/foto-verifikasi/verifikasi-".Str::lower(Str::random(10)).'.jpg';

        if (! Storage::disk(self::DISK)->put($path, $konten)) {
            throw new \RuntimeException('Gagal mengunggah foto verifikasi.');
        }

        return $path;
    }

    /** Hapus daftar path (kompensasi bila transaksi DB gagal). */
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

    /** Slug ramah folder: huruf kecil, spasi→strip, buang karakter aneh. */
    public function slug(string $teks): string
    {
        $teks = strtolower(trim($teks));
        // buang prefiks umum field berkas (dok_, file_, upload_)
        $teks = preg_replace('/^(dok|file|upload|berkas)[_\-\s]+/', '', $teks);
        $teks = preg_replace('/[^a-z0-9]+/', '-', $teks);

        return trim($teks, '-') ?: 'berkas';
    }
}
