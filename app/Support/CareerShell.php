<?php

namespace App\Support;

use App\Support\Career\Shell\PropsShell;

/**
 * WEB CAREERS — PINTU MASUK SHELL portal kandidat (sidebar + brand + auth).
 * Dipakai di controller: `return Inertia::render($komponen, CareerShell::props($url, $title));`
 *
 * Isinya cuma penerus; yang bekerja ada di app/Support/Career/Shell/:
 *
 *   Mau apa?                            Sunting di
 *   ----------------------------------  --------------------------------------
 *   Menu sidebar                        JANGAN di kode → Master Menu di zona
 *                                       dalam (disalin ke tabel Menu publik)
 *   Logo, nama aplikasi, anak usaha     config/career_shell.php
 *   Props global untuk semua halaman    config/career_shell.php → props_tambahan
 *   Identitas pengguna                  Shell/IdentitasShell.php
 *   Bentuk payload layout & breadcrumb  Shell/LayoutShell.php
 *   Cadangan menu saat tabel kosong     Shell/NavigasiShell.php
 */
class CareerShell
{
    /** Props shell (layout + auth + hak akses) untuk halaman portal kandidat. */
    public static function props(string $url, string $title, array $extra = []): array
    {
        return PropsShell::bangun($url, $title, $extra);
    }
}
