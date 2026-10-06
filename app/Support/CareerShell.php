<?php

namespace App\Support;

use App\Support\Career\Shell\IdentitasShell;
use App\Support\Career\Shell\LayoutShell;
use App\Support\Career\Shell\NavigasiShell;
use App\Support\Career\Shell\PropsShell;

/**
 * WEB CAREERS — PINTU MASUK SHELL admin (sidebar + brand + auth).
 * Dipakai di controller: `return Inertia::render($komponen, CareerShell::props($url, $title));`
 *
 *===============================================================================
 * ⛔ BERKAS INI DIBEKUKAN — JANGAN DISUNTING
 *===============================================================================
 * Berkas ini dipakai ~50 halaman, jadi dulu jadi titik bentrok nomor satu:
 * dua orang yang bekerja paralel selalu menabrak baris yang sama saat merge.
 *
 * Sekarang isinya cuma penerus (delegasi). Yang sebenarnya bekerja ada di
 * app/Support/Career/Shell/ — sunting di sana sesuai keperluan:
 *
 *   Mau apa?                            Sunting di
 *   ----------------------------------  --------------------------------------
 *   Menambah / mengubah menu            JANGAN di kode → halaman Master Menu
 *                                       (/master-menu), lalu /hak-akses
 *   Logo, nama aplikasi, anak usaha     config/career_shell.php
 *   Judul modul / subtitle / ikon       config/career_shell.php
 *   Props global untuk semua halaman    daftarkan kelas sendiri di
 *                                       config/career_shell.php → props_tambahan
 *   Aturan peran / identitas pengguna   Shell/IdentitasShell.php
 *   Bentuk payload layout & breadcrumb  Shell/LayoutShell.php
 *   Cadangan menu saat DB kosong        Shell/NavigasiShell.php
 *
 * Kalau git melaporkan konflik di berkas ini, berarti ada yang menambah sesuatu
 * yang seharusnya masuk salah satu berkas di atas. Ambil versi terbaru, lalu
 * pindahkan perubahannya ke tempat yang benar.
 */
class CareerShell
{
    /** Peran yang boleh membuka panel admin. */
    public const PERAN_ADMIN = IdentitasShell::PERAN_ADMIN;

    /** Props shell (layout + auth + hak akses) untuk halaman admin. */
    public static function props(string $url, string $title, array $extra = []): array
    {
        return PropsShell::bangun($url, $title, $extra);
    }

    /** Payload layout (brand + navigation + breadcrumb). */
    public static function layout(string $activeUrl, string $title): array
    {
        return LayoutShell::bangun($activeUrl, $title);
    }

    /** Identitas admin dari sesi login. */
    public static function adminUser(): array
    {
        return IdentitasShell::pengguna();
    }

    /** Peran pengguna saat ini (dari sesi). */
    public static function peran(): string
    {
        return IdentitasShell::peran();
    }

    public static function adalahAdmin(): bool
    {
        return IdentitasShell::adalahAdmin();
    }

    /** Menu sesuai peran & hak akses pengguna saat ini. */
    public static function nav(): array
    {
        return NavigasiShell::untukPenggunaSaatIni();
    }

    public static function adminNav(): array
    {
        return NavigasiShell::admin();
    }

    public static function kandidatNav(): array
    {
        return NavigasiShell::kandidat();
    }

    /** Buang cache menu master — dipanggil setiap Master Menu berubah. */
    public static function lupakanNav(): void
    {
        NavigasiShell::lupakan();
    }
}
