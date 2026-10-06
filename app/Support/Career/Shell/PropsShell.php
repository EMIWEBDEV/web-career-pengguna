<?php

namespace App\Support\Career\Shell;

use Illuminate\Support\Facades\Log;

/**
 * WEB CAREERS — PERAKIT PROPS halaman admin (layout + auth + hak akses).
 *
 * Kalau suatu fitur butuh props yang ada di SEMUA halaman, JANGAN menyunting
 * berkas ini. Buat kelas sendiri di folder fiturmu:
 *
 *     class PropsAnu {
 *         public static function tambahan(string $url, string $judul): array
 *         {
 *             return ['anu' => [...]];
 *         }
 *     }
 *
 * lalu daftarkan satu baris di akhir 'props_tambahan' pada
 * config/career_shell.php. Karena hanya menambah baris di akhir daftar, git
 * hampir selalu bisa menggabungkannya sendiri saat merge.
 */
class PropsShell
{
    public static function bangun(string $url, string $judul, array $extra = []): array
    {
        // Urutan sengaja: props tambahan global dulu, lalu $extra milik halaman
        // (halaman berhak menimpa), terakhir kunci inti yang tak boleh ditimpa.
        return array_merge(self::tambahanTerdaftar($url, $judul), $extra, [
            'layout' => LayoutShell::bangun($url, $judul),
            'auth' => ['user' => IdentitasShell::pengguna()],
            // Hak akses halaman ini → dipakai Vue menyembunyikan tombol yang
            // tidak diizinkan (gerbang sebenarnya tetap di middleware server).
            'akses' => [
                'permissions' => session('career_akses.permissions', []),
                'konten' => session('career_akses.permission_konten', []),
            ],
        ]);
    }

    /**
     * Props tambahan dari config. Satu penyedia yang bermasalah tidak boleh
     * menjatuhkan seluruh halaman — dicatat ke log lalu dilewati.
     */
    private static function tambahanTerdaftar(string $url, string $judul): array
    {
        $hasil = [];

        foreach ((array) config('career_shell.props_tambahan', []) as $kelas) {
            try {
                if (is_string($kelas) && method_exists($kelas, 'tambahan')) {
                    $hasil = array_merge($hasil, (array) $kelas::tambahan($url, $judul));
                }
            } catch (\Throwable $e) {
                Log::warning("Props tambahan {$kelas} gagal: " . $e->getMessage());
            }
        }

        return $hasil;
    }
}
