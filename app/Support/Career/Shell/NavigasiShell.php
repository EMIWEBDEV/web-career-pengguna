<?php

namespace App\Support\Career\Shell;

use App\Support\Career\AksesService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * WEB CAREERS — SUMBER MENU SIDEBAR.
 *
 * ⛔ JANGAN MENAMBAH MENU DI BERKAS INI.
 *
 * Menu bukan array di kode. Sumbernya tabel N_WEB_CAREERS_Menu — salinan
 * Master Menu yang diatur di zona dalam dan didorong Sync Worker ke sini.
 *
 * KENAPA: menu yang ditulis di kode dulu jadi titik bentrok nomor satu. Setiap
 * fitur baru menyisipkan satu baris di array yang sama, sehingga dua orang yang
 * bekerja paralel menabrak baris yang sama saat merge — dan yang kalah merge
 * kehilangan menunya diam-diam (route & halaman utuh, tapi menu lenyap).
 *
 * Daftar DARURAT di bawah hanya dipakai bila tabel menu belum ada / kosong
 * (mis. salinan belum pernah didorong). Sengaja minimal.
 */
class NavigasiShell
{
    private const TABEL = 'N_WEB_CAREERS_Menu';

    /** Menu portal kandidat yang sedang masuk. */
    public static function untukPenggunaSaatIni(): array
    {
        // SUMBER UTAMA: paket hak akses (dibangun dari master menu +
        // Page_Access user). Hanya menu yang ia punya izin VIEW-nya yang muncul.
        //
        // Disegarkan lebih dulu — lihat AksesService::segarkanSesi(). Membaca
        // `session('career_akses.menu')` langsung membuat susunan menu membeku
        // pada keadaan saat login: perbaikan menu apa pun baru terlihat setelah
        // pemiliknya keluar-masuk, dan sampai itu terjadi ia melihat sidebar
        // yang sudah tidak lagi ada padanannya di Master Menu.
        //
        // Gagal menyegarkan memulangkan null, dan salinan lama di sesi dipakai
        // apa adanya — sidebar tidak pernah kosong hanya karena DB sedang sibuk.
        $dariAkses = AksesService::segarkanSesi()['menu'] ?? session('career_akses.menu');
        if (is_array($dariAkses) && $dariAkses) {
            $bersih = fn ($it) => [
                'key' => $it['key'],
                'label' => $it['label'],
                'icon' => $it['icon'],
                'url' => $it['url'],
            ];

            return array_map(fn ($g) => [
                'id' => $g['id'],
                'title' => $g['title'],
                'items' => array_map($bersih, $g['items']),
                // Sesi lama (dibuat sebelum kolom sub-grup ada) tidak punya
                // kunci ini. Dibiarkan kosong, bukan dianggap galat: menunya
                // tetap tergambar rata seperti sebelumnya sampai sesinya
                // diperbarui sendiri saat login berikutnya.
                'subs' => array_map(fn ($s) => [
                    'id' => $s['id'],
                    'title' => $s['title'],
                    'items' => array_map($bersih, $s['items']),
                ], $g['subs'] ?? []),
            ], $dariAkses);
        }

        // Cadangan: sesi lama / paket akses belum terbentuk → daftar dari master,
        // supaya panel tidak pernah tampil tanpa menu sama sekali.
        return self::kandidat();
    }

    /** Menu portal kandidat — dari tabel master; daftar darurat bila master kosong. */
    public static function kandidat(): array
    {
        return self::dariMaster('KANDIDAT') ?: [
            ['id' => 'lamaran', 'title' => 'Lamaran Saya', 'items' => [
                ['key' => 'portalPage', 'label' => 'Lamaran Saya', 'icon' => 'bi bi-file-earmark-text', 'url' => '/kandidat/portal'],
            ]],
        ];
    }

    /**
     * Susun menu dari tabel master.
     * Dikelompokkan per Nama_Header, urut mengikuti kolom Urutan.
     */
    private static function dariMaster(string $role): array
    {
        try {
            $rows = Cache::remember(
                "wc_nav_master_{$role}",
                now()->addMinutes((int) config('career_shell.cache_menu_menit', 5)),
                fn () => DB::table(self::TABEL)
                    ->where('Untuk_Role', $role)
                    ->where('Flag_Aktif', 'Y')
                    ->orderBy('Urutan')
                    ->get([
                        'Jenis_Page', 'Nama_Menu', 'Nama_Header', 'Icon_Menu', 'Url_Menu', 'Urutan',
                        'Nama_Grup', 'Urutan_Grup',
                    ])
            );
        } catch (\Throwable $e) {
            return []; // tabel belum ada / DB bermasalah → pakai daftar darurat
        }

        // Dua tingkat: header → sub-grup → menu. Menu tanpa sub-grup tetap
        // duduk langsung di bawah headernya, jadi baris lama yang Nama_Grup-nya
        // masih NULL tergambar persis seperti sebelum kolom itu ada.
        $grup = [];
        foreach ($rows as $r) {
            $header = $r->Nama_Header ?: 'Menu';
            $grup[$header] ??= ['items' => [], 'subs' => []];

            $item = [
                'key' => $r->Jenis_Page,
                'label' => $r->Nama_Menu,
                'icon' => $r->Icon_Menu ?: 'bi bi-dot',
                'url' => $r->Url_Menu ?: '#',
            ];

            $sub = $r->Nama_Grup ?: null;
            if (! $sub) {
                $grup[$header]['items'][] = $item;

                continue;
            }

            $grup[$header]['subs'][$sub] ??= [
                'id' => Str::slug($header.'-'.$sub),
                'title' => $sub,
                'items' => [],
                'urutan' => $r->Urutan_Grup !== null ? (int) $r->Urutan_Grup : (int) $r->Urutan,
            ];
            $grup[$header]['subs'][$sub]['items'][] = $item;
        }

        return array_map(function ($header, $isi) {
            $subs = array_values($isi['subs']);
            usort($subs, fn ($a, $b) => $a['urutan'] <=> $b['urutan']);

            return [
                'id' => Str::slug($header),
                'title' => $header,
                'items' => $isi['items'],
                'subs' => $subs,
            ];
        }, array_keys($grup), $grup);
    }
}
