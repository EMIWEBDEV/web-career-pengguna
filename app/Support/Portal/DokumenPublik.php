<?php

namespace App\Support\Portal;

use Illuminate\Support\Facades\DB;

/**
 * Dokumen dari zona dalam untuk kandidat (surat pengantar, berkas hasil tahap)
 * — baris N_WEB_CAREERS_Pub_Dokumen, objeknya di bucket PUBLIK. Ditulis Sync
 * Worker (Dokumen.Tersedia); aplikasi ini hanya membaca.
 */
final class DokumenPublik
{
    public const TABEL = 'N_WEB_CAREERS_Pub_Dokumen';

    public const DISK = 'publik';

    /** Dokumen aktif berkunci $kunci, hanya bila lamarannya milik akun ini. */
    public static function milik(string $kunci, int $userId): ?object
    {
        return DB::table(self::TABEL.' as d')
            ->join('N_WEB_CAREERS_Lamaran as l', 'l.Kode', '=', 'd.Kode_Lamaran')
            ->where('d.Kunci', $kunci)
            ->where('d.Flag_Aktif', 'Y')
            ->where('l.Id_Users', $userId)
            ->first(['d.Kunci', 'd.Kode_Lamaran', 'd.Jenis', 'd.Nama_Berkas', 'd.Objek_Path', 'd.Mime']);
    }

    /** Dokumen aktif sebuah lamaran — untuk tautan surel bertanda tangan. */
    public static function untukLamaran(string $kunci, string $kodeLamaran): ?object
    {
        return DB::table(self::TABEL)
            ->where('Kunci', $kunci)
            ->where('Kode_Lamaran', $kodeLamaran)
            ->where('Flag_Aktif', 'Y')
            ->first(['Kunci', 'Kode_Lamaran', 'Jenis', 'Nama_Berkas', 'Objek_Path', 'Mime']);
    }

    public static function layani(?object $d, bool $unduh = false)
    {
        return SajianBerkas::layani(self::DISK, $d->Objek_Path ?? null, $d->Nama_Berkas ?? null, $unduh, $d->Mime ?? null);
    }

    public static function sajikanIsi(?object $d)
    {
        return SajianBerkas::sajikanIsi(self::DISK, $d->Objek_Path ?? null, $d->Nama_Berkas ?? null);
    }
}
