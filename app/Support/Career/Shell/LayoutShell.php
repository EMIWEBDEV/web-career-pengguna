<?php

namespace App\Support\Career\Shell;

/**
 * WEB CAREERS — PERAKIT PAYLOAD LAYOUT (brand + navigation + breadcrumb).
 *
 * Bentuk payload-nya mengikuti kontrak shell bersama (sama seperti
 * InertiaShellService di sistem lain), jadi berkas ini praktis tidak berubah:
 * menu datang dari NavigasiShell, merek dari MerekShell.
 */
class LayoutShell
{
    /**
     * Halaman yang SUDAH punya tempatnya sendiri di kerangka shell, sehingga
     * tidak boleh ikut digambar sebagai item menu di sidebar.
     *
     * /profil ada di menu profil pada footer sidebar — tombol yang selalu
     * terlihat, di setiap halaman, untuk setiap peran. Barisnya di Master Menu
     * tetap dipertahankan (halaman itu perlu punya tempat di layar Hak Akses,
     * sama seperti 'dashboardPage'), tapi item sidebar-nya disaring di sini.
     * Tanpa penyaring ini portal kandidat memuat satu grup penuh — "Akun" —
     * yang isinya cuma satu tautan menuju halaman yang tombolnya sudah ada
     * dua sentimeter di bawahnya.
     *
     * Grup yang jadi kosong ikut lenyap sendiri; lihat penyaring grup kosong
     * di akhir penyusunan $groups.
     */
    private const URL_SUDAH_DI_SHELL = ['/profil'];

    public static function bangun(string $activeUrl, string $judul): array
    {
        $kode = (string) config('career_shell.kode', 'CAREER');
        $label = (string) config('career_shell.label', 'Web Career');
        $beranda = IdentitasShell::beranda();

        $semua = NavigasiShell::untukPenggunaSaatIni();

        // ── GRUP BERANDA = KUMPULAN DASHBOARD ────────────────────────────────
        //
        // Dikenali dari ISINYA, bukan dari namanya: grup mana pun yang memuat
        // item ber-URL beranda adalah grup dashboard. Judulnya boleh apa saja
        // ("Beranda", "Dashboard", "Ringkasan") dan boleh berbeda per akun,
        // sebab penyusun menu (/hak-akses/susun) mengizinkan tiap akun menimpa
        // Nama_Header sendiri — mencocokkan nama di sini akan gagal diam-diam
        // begitu satu admin menamainya lain.
        //
        // Anggota grup inilah yang jadi isi collapse "Dashboard". Admin yang
        // hanya punya satu dashboard tidak punya anggota lain, jadi tombol
        // berandanya tetap tautan tunggal seperti sebelumnya — tidak ada
        // collapse berisi satu baris.
        $idGrupBeranda = null;
        $itemBeranda = null;
        foreach ($semua as $g) {
            $isi = array_merge(
                $g['items'] ?? [],
                ...array_map(fn ($sb) => $sb['items'] ?? [], $g['subs'] ?? [])
            );
            foreach ($isi as $it) {
                if (($it['url'] ?? '') === $beranda) {
                    $idGrupBeranda = $g['id'];
                    $itemBeranda = $it;
                    break 2;
                }
            }
        }

        $petakan = fn ($it) => [
            'id' => $it['key'],
            'jenisPage' => strtolower($kode) . '-' . $it['key'],
            'title' => $it['label'],
            'subtitle' => '',
            'url' => $it['url'],
            'icon' => $it['icon'],
            'target' => 'self',
            'isActive' => $it['url'] === $activeUrl,
        ];

        // SATU penyaring untuk semua tempat: item yang URL-nya = beranda (sudah
        // jadi tombol navigation.home) dan item yang tempatnya memang bukan di
        // menu (URL_SUDAH_DI_SHELL). Ditulis sekali, dipakai tiga kali — dulu
        // syaratnya disalin tiga kali, dan menambah pengecualian berarti harus
        // ingat menyunting ketiganya.
        $bukanItemMenu = fn ($it) => ($it['url'] ?? '') === $beranda
            || in_array($it['url'] ?? '', self::URL_SUDAH_DI_SHELL, true);

        // Dashboard SELAIN beranda — jadi anak collapse berandanya.
        $grupBeranda = collect($semua)->firstWhere('id', $idGrupBeranda);
        $dashboard = array_merge(
            $grupBeranda['items'] ?? [],
            ...array_map(fn ($sb) => $sb['items'] ?? [], $grupBeranda['subs'] ?? [])
        );
        $dashboard = collect($dashboard)
            ->reject($bukanItemMenu)
            ->map($petakan)
            ->values()
            ->all();

        // Item yang URL-nya = beranda DIBUANG dari grup: beranda sudah punya
        // tombolnya sendiri di navigation.home. Ini bukan kosmetik semata —
        // halaman dashboard perlu baris N_WEB_CAREERS_Menu ('dashboardPage')
        // supaya bisa diberi batasan kategori di /hak-akses, dan baris itu
        // otomatis ikut jadi item sidebar. Tanpa penyaring ini, "Dashboard"
        // muncul dua kali: sebagai tombol beranda dan sebagai item grup.
        //
        // Seluruh GRUP beranda pun dikeluarkan — isinya sudah pindah ke
        // navigation.home di atas, dan membiarkannya membuat tiap dashboard
        // tampil dua kali: sekali di collapse Dashboard, sekali lagi di grup
        // asalnya.
        $groups = collect($semua)
            ->reject(fn ($g) => $idGrupBeranda !== null && $g['id'] === $idGrupBeranda)
            ->map(fn ($g) => [
                'id' => $g['id'],
                'title' => $g['title'],
                'items' => collect($g['items'] ?? [])
                    ->reject($bukanItemMenu)
                    ->map($petakan)
                    ->values()
                    ->all(),
                'subs' => collect($g['subs'] ?? [])
                    ->map(fn ($sb) => [
                        'id' => $sb['id'],
                        'title' => $sb['title'],
                        'items' => collect($sb['items'] ?? [])
                            ->reject($bukanItemMenu)
                            ->map($petakan)
                            ->values()
                            ->all(),
                    ])
                    ->reject(fn ($sb) => ! $sb['items'])
                    ->values()
                    ->all(),
            ])
            ->reject(fn ($g) => ! $g['items'] && ! $g['subs'])   // grup yang jadi kosong ikut hilang
            ->values()
            ->all();

        // Dashboard ikut dicari: sejak isinya pindah ke navigation.home, item
        // aktif bisa berada di luar $groups — dan tanpa ini judul halaman &
        // breadcrumb untuk Dashboard Kandidat/Feedback jatuh ke null.
        $isiGrup = fn ($g) => array_merge(
            $g['items'],
            ...array_map(fn ($sb) => $sb['items'], $g['subs'])
        );

        $activeItem = collect($groups)->flatMap($isiGrup)->firstWhere('isActive', true)
            ?: collect($dashboard)->firstWhere('isActive', true);
        $activeGroup = collect($groups)->first(fn ($g) => collect($isiGrup($g))->firstWhere('isActive', true));
        // Sub-grup yang memuat halaman aktif — dipakai sidebar untuk membukanya
        // sendiri saat halaman dimuat, supaya menu yang sedang dibuka tidak
        // tersembunyi di balik sub-grup yang tertutup.
        $activeSub = collect($groups)
            ->flatMap(fn ($g) => $g['subs'])
            ->first(fn ($sb) => collect($sb['items'])->firstWhere('isActive', true));

        // Beranda sedang dibuka, ATAU salah satu dashboard di dalamnya.
        // Dipakai sidebar untuk menyalakan tombol Dashboard dan membuka
        // collapse-nya sendiri saat halaman dimuat.
        $berandaAktif = $beranda === $activeUrl
            || (bool) collect($dashboard)->firstWhere('isActive', true);

        $module = [
            'id' => $kode,
            'code' => $kode,
            'name' => $label,
            'label' => $label,
            'subtitle' => (string) config('career_shell.subtitle', ''),
            'icon' => (string) config('career_shell.ikon', 'bi bi-briefcase-fill'),
            'sortOrder' => 1,
            'landingJenisPage' => '',
            'landingUrl' => $beranda,
            'landingTarget' => 'self',
            'isActive' => true,
            'activeGroupId' => $activeGroup['id'] ?? '',
            'activeSubId' => $activeSub['id'] ?? '',
            'groups' => $groups,
        ];

        return [
            'brand' => MerekShell::payload(),
            'navigation' => [
                'sectionLabel' => $label,
                'home' => [
                    // Namanya diambil dari MASTER MENU bila barisnya ada —
                    // admin yang menamainya "Dashboard Utama" di /master-menu
                    // berhak melihat nama itu di sidebarnya, bukan kata lain
                    // yang ditulis di sini.
                    'title' => $itemBeranda['label'] ?? 'Dashboard',
                    'subtitle' => 'Halaman Utama',
                    'url' => $beranda,
                    'icon' => $itemBeranda['icon'] ?? 'bi bi-house-door-fill',
                    'isActive' => $beranda === $activeUrl,
                    // Dashboard lain yang boleh dilihat akun ini. Kosong =
                    // sidebar menggambar tautan tunggal, persis seperti dulu.
                    'items' => $dashboard,
                    'hasActive' => $berandaAktif,
                ],
                'modules' => [$module],
            ],
            'shell' => [
                'currentUrl' => $activeUrl,
                'activeModule' => $kode,
                'activeModuleLabel' => $label,
                'activeModuleUrl' => $beranda,
                'activeSubMenu' => $activeItem['jenisPage'] ?? null,
                'activeSubMenuLabel' => $activeItem['title'] ?? null,
                'currentPageTitle' => $judul,
                'breadcrumbs' => [
                    ['label' => 'HOME', 'url' => $beranda, 'active' => false],
                    ['label' => $kode, 'url' => $beranda, 'active' => false],
                    ['label' => $judul, 'url' => $activeUrl, 'active' => true],
                ],
            ],
            'notifications' => [],
            'unreadNotificationsCount' => 0,
            'messages' => [],
            'help' => [],
            'appVersion' => config('services.project_config.app_version', '3.0.0'),
        ];
    }
}
