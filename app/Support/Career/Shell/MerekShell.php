<?php

namespace App\Support\Career\Shell;

/**
 * WEB CAREERS — MEREK (logo & nama aplikasi) untuk topbar shell.
 *
 * Isinya dibaca dari config/career_shell.php, jadi pergantian logo/nama tidak
 * perlu menyunting kode sama sekali.
 */
class MerekShell
{
    public static function payload(): array
    {
        $merek = (array) config('career_shell.merek', []);

        return [
            'appName' => $merek['nama_aplikasi'] ?? 'EVO Group Unified Platform',
            'appShortName' => $merek['nama_singkat'] ?? 'EVO',
            'mainLogo' => asset($merek['logo'] ?? 'logo/EVOGROUP.png'),
            'mainLogoAlt' => $merek['logo_alt'] ?? 'EVO Group',
            'subsidiaries' => array_map(fn ($a) => [
                'id' => $a['id'] ?? '',
                'name' => $a['nama'] ?? '',
                'src' => asset($a['berkas'] ?? ''),
                'fallback' => $a['inisial'] ?? '',
            ], (array) ($merek['anak_usaha'] ?? [])),
        ];
    }
}
