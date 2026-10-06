<?php

namespace App\Support\Career;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * WEB CAREERS — HAK AKSES (RBAC). Meniru pola cat-evo-pembaharuan:
 *   permissions      : [jenisPage => [AKSI, ...]]        → dipakai middleware
 *   permission_label : [jenisPage => {menu, header, ...}] → dipakai sidebar
 *   permission_konten: [jenisPage => [KATEGORI, ...]]     → penyaring kategori
 *
 * BEDA POKOK dengan cat-evo: di sana setiap user diberi akses manual oleh admin.
 * Di sini KANDIDAT mendaftar sendiri dan jumlahnya tak terbatas, jadi aksesnya
 * DI-PROVISION OTOMATIS dari cetakan per Klasifikasi Akun
 * (N_WEB_CAREERS_Klasifikasi_Menu + _Menu_Aksi). Admin cukup mengatur cetakannya
 * sekali, bukan mencentang satu per satu kandidat.
 */
class AksesService
{
    /** Menit cache paket akses per user. Sengaja pendek — perubahan hak akses harus cepat terasa. */
    private const CACHE_MENIT = 10;

    private const TBL_MENU = 'N_WEB_CAREERS_Menu';

    private const TBL_AKSI = 'N_WEB_CAREERS_Aksi';

    private const TBL_PAGE = 'N_WEB_CAREERS_Page_Access';

    private const TBL_RMA = 'N_WEB_CAREERS_Role_Menu_Access';

    private const TBL_RKA = 'N_WEB_CAREERS_Role_Konten_Access';

    /** Lingkup penanggung jawab MPP. Nilainya sama dengan CHECK di database. */
    public const LINGKUP_SENDIRI = 'SENDIRI';

    public const LINGKUP_TIM = 'TIM';

    public const LINGKUP_SEMUA = 'SEMUA';

    private const TBL_KL_MENU = 'N_WEB_CAREERS_Klasifikasi_Menu';

    private const TBL_KL_AKSI = 'N_WEB_CAREERS_Klasifikasi_Menu_Aksi';

    private const TBL_KL_WL = 'N_WEB_CAREERS_Klasifikasi_Whitelist';

    /** Perusahaan yang dilayani modul ini — kunci komposit tabel Karyawan. */
    private const KODE_PERUSAHAAN = '001';

    public static function cacheKey(int $idUsers): string
    {
        return "wc_akses_{$idUsers}";
    }

    /** Buang cache akses seorang user (dipanggil setiap hak aksesnya diubah). */
    public static function lupakan(int $idUsers): void
    {
        Cache::forget(self::cacheKey($idUsers));
    }

    /** Buang cache SEMUA user — dipakai saat master menu/aksi berubah. */
    public static function lupakanSemua(): void
    {
        foreach (DB::table(self::TBL_PAGE)->distinct()->pluck('Id_Users') as $id) {
            Cache::forget(self::cacheKey((int) $id));
        }
    }

    /**
     * Paket akses lengkap seorang user (dengan cache).
     *
     * @return array{permissions:array, permission_label:array, permission_konten:array, menu:array}
     */
    public static function paket(int $idUsers, string $role, ?string $klasifikasi = null): array
    {
        return Cache::remember(self::cacheKey($idUsers), now()->addMinutes(self::CACHE_MENIT), function () use ($idUsers, $role, $klasifikasi) {
            // Kandidat yang belum punya baris akses → dibuatkan dari cetakan.
            if ($role === 'KANDIDAT' && $klasifikasi) {
                self::pastikanAksesKandidat($idUsers, $klasifikasi);
            }

            return self::bangun($idUsers);
        });
    }

    /**
     * Paket akses TERKINI milik pengguna sesi, sekalian menyegarkan salinannya
     * di `session('career_akses')`. Null bila belum login / gagal dibaca.
     *
     * ── KENAPA INI PERLU ADA ───────────────────────────────────────────────
     *
     * Paket akses dulu HANYA ditulis sekali, saat login, lalu dibaca dari sesi
     * selamanya. Akibatnya dua janji yang ditulis di kode ini sendiri tidak
     * pernah ditepati untuk orang yang sedang login:
     *
     *   · CACHE_MENIT = 10 "sengaja pendek — perubahan hak akses harus cepat
     *     terasa"
     *   · career_shell.cache_menu_menit = 5 "pendek — perubahan menu harus
     *     cepat terasa"
     *
     * Keduanya hanya mengatur cache DI ATAS database. Salinan di sesi duduk di
     * atas cache itu dan tidak punya masa berlaku sama sekali, jadi membuang
     * cache — termasuk lewat lupakan() yang dipanggil setiap hak akses diubah —
     * tidak mengubah apa pun sampai orangnya keluar lalu masuk lagi. Menu yang
     * dibetulkan admin pagi ini baru terlihat besok.
     *
     * Yang disegarkan SELURUH paket, bukan menunya saja. Kalau hanya menu yang
     * ikut terbarui, sidebar bisa menampilkan halaman yang gerbangnya (yang
     * membaca `permissions` dari sesi) masih menolak — pengguna melihat menu
     * yang selalu berkata "tidak boleh". Menu dan izin harus datang dari
     * potret yang sama.
     */
    public static function segarkanSesi(): ?array
    {
        $idUsers = (int) session('career_auth.id');
        if ($idUsers <= 0) {
            return null;
        }

        try {
            $paket = self::paket(
                $idUsers,
                (string) (session('career_auth.role') ?: 'KANDIDAT'),
                session('career_auth.klasifikasi')
            );
        } catch (\Throwable $e) {
            // Gagal menyegarkan BUKAN alasan menjatuhkan halaman: salinan sesi
            // yang lama masih sah dipakai sampai permintaan berikutnya.
            Log::channel('web_career')->warning("Segarkan akses sesi #{$idUsers} gagal: " . $e->getMessage());

            return null;
        }

        session(['career_akses' => $paket]);

        return $paket;
    }

    /** Bangun paket akses langsung dari DB (tanpa cache). */
    public static function bangun(int $idUsers): array
    {
        $rows = DB::table(self::TBL_PAGE . ' as pa')
            ->join(self::TBL_RMA . ' as rma', 'rma.Id_Page_Access', '=', 'pa.Id_Page_Access')
            ->join(self::TBL_AKSI . ' as a', 'a.Id_Aksi', '=', 'rma.Id_Aksi')
            ->leftJoin(self::TBL_MENU . ' as m', 'm.Jenis_Page', '=', 'pa.Jenis_Page')
            ->where('pa.Id_Users', $idUsers)
            ->where('rma.Flag_Diizinkan', 'Y')
            ->where('a.Flag_Aktif', 'Y')
            ->where(fn ($w) => $w->whereNull('m.Flag_Aktif')->orWhere('m.Flag_Aktif', 'Y'))
            ->orderBy('pa.Urutan_Menu')
            ->select(
                'pa.Id_Page_Access', 'pa.Jenis_Page', 'pa.Urutan_Menu', 'a.Nama_Aksi',
                'm.Nama_Menu', 'm.Nama_Header', 'm.Sub_Header', 'm.Icon_Menu', 'm.Url_Menu', 'm.Urutan',
                // Tingkat kedua sidebar. NULL = menu duduk langsung di bawah
                // headernya, persis seperti sebelum kolom ini ada.
                'm.Nama_Grup', 'm.Urutan_Grup',
                // Timpaan per akun dari penyusun menu (/hak-akses/susun/{user}).
                // NULL = ikut master, jadi menu yang belum pernah disusun tetap
                // otomatis mengikuti perubahan Master Menu.
                'pa.Nama_Header_Custom', 'pa.Sub_Header_Custom', 'pa.Nama_Menu_Custom', 'pa.Icon_Menu_Custom',
                'pa.Nama_Grup_Custom',
                // Lingkup penanggung jawab MPP untuk halaman ini.
                'pa.Lingkup_Pic'
            )
            ->get();

        $permissions = [];
        $label = [];
        $pageIds = [];

        foreach ($rows as $r) {
            $page = $r->Jenis_Page;
            $permissions[$page] ??= [];
            if (! in_array($r->Nama_Aksi, $permissions[$page], true)) {
                $permissions[$page][] = $r->Nama_Aksi;
            }
            $label[$page] ??= [
                'nama_menu' => $r->Nama_Menu_Custom ?: $r->Nama_Menu,
                'nama_header' => $r->Nama_Header_Custom ?: $r->Nama_Header,
                'nama_grup' => $r->Nama_Grup_Custom ?: $r->Nama_Grup,
                'urutan_grup' => $r->Urutan_Grup !== null ? (int) $r->Urutan_Grup : null,
                'sub_header' => $r->Sub_Header_Custom ?: $r->Sub_Header,
                'icon' => $r->Icon_Menu_Custom ?: $r->Icon_Menu,
                'url' => $r->Url_Menu,
                'urutan' => (int) ($r->Urutan_Menu ?: $r->Urutan ?: 0),
            ];
            $pageIds[$r->Id_Page_Access] = $page;
        }

        // Konten = KATEGORI program (REKRUTMEN / MT / INTERNSHIP ...).
        $konten = [];
        if ($pageIds) {
            $kontenRows = DB::table(self::TBL_RKA)
                ->whereIn('Id_Page_Access', array_keys($pageIds))
                ->where('Flag_Diizinkan', 'Y')
                ->get(['Id_Page_Access', 'Kategori']);

            foreach ($kontenRows as $k) {
                $page = $pageIds[$k->Id_Page_Access] ?? null;
                if (! $page) {
                    continue;
                }
                $konten[$page] ??= [];
                if (! in_array($k->Kategori, $konten[$page], true)) {
                    $konten[$page][] = $k->Kategori;
                }
            }
        }

        // LINGKUP PIC per halaman: 'SENDIRI' | 'TIM' | 'SEMUA'.
        //
        // Dibaca dari baris Page_Access yang SAMA dengan yang sudah diambil di
        // atas, jadi tidak ada kueri tambahan. NULL diperlakukan 'SEMUA' —
        // lihat catatan panjangnya di lingkupPic().
        $lingkup = [];
        foreach ($rows as $r) {
            $lingkup[$r->Jenis_Page] ??= $r->Lingkup_Pic ?: self::LINGKUP_SEMUA;
        }

        return [
            'permissions' => $permissions,
            'permission_label' => $label,
            'permission_konten' => $konten,
            'permission_lingkup' => $lingkup,
            'menu' => self::susunMenu($permissions, $label),
        ];
    }

    /**
     * Ubah label+permissions jadi struktur sidebar bergrup (dipakai CareerShell).
     *
     * ── KENAPA SUB-GRUP TETAP MENINGGALKAN `items` ─────────────────────────
     *
     * Tiap grup mengirim DUA hal: `items` berisi menu yang tidak punya
     * sub-grup, dan `subs` berisi sub-grupnya. Bukan salah satunya.
     *
     * Alasannya percampuran memang terjadi dan memang sah: "Seleksi" hari ini
     * seluruhnya bersub-grup, tapi menu baru yang ditambahkan lewat halaman
     * Master Menu tidak akan langsung diberi sub-grup — dan menu yang tidak
     * tergambar sama sekali karena belum diberi tempat adalah kegagalan yang
     * jauh lebih buruk daripada menu yang tergambar agak canggung.
     *
     * `items` digambar lebih dulu, di atas sub-grupnya. Menu tanpa tempat
     * berada tepat di bawah judul grupnya, bukan tersembunyi di ujung bawah
     * setelah semua sub-grup.
     */
    private static function susunMenu(array $permissions, array $label): array
    {
        $grup = [];
        foreach ($label as $page => $l) {
            // Hanya menu yang punya izin VIEW yang muncul di sidebar.
            if (! in_array('VIEW', $permissions[$page] ?? [], true)) {
                continue;
            }
            $header = $l['nama_header'] ?: 'Menu';
            $grup[$header] ??= [];
            $grup[$header][] = [
                'key' => $page,
                'label' => $l['nama_menu'] ?: $page,
                'icon' => $l['icon'] ?: 'bi bi-dot',
                'url' => $l['url'] ?: '#',
                'urutan' => $l['urutan'],
                'grup' => $l['nama_grup'] ?? null,
                'urutan_grup' => $l['urutan_grup'] ?? null,
            ];
        }

        $hasil = [];
        foreach ($grup as $header => $semua) {
            usort($semua, fn ($a, $b) => $a['urutan'] <=> $b['urutan']);

            $lepas = [];
            $subs = [];
            foreach ($semua as $it) {
                $nama = $it['grup'];
                if (! $nama) {
                    $lepas[] = $it;

                    continue;
                }
                $subs[$nama] ??= [
                    // Slug memuat headernya juga: dua header boleh punya
                    // sub-grup bernama sama, dan id yang bertabrakan membuat
                    // keduanya buka-tutup bersamaan di sidebar.
                    'id' => \Illuminate\Support\Str::slug($header.'-'.$nama),
                    'title' => $nama,
                    'items' => [],
                    'urutan' => $it['urutan_grup'] ?? $it['urutan'],
                ];
                $subs[$nama]['items'][] = $it;
            }

            usort($subs, fn ($a, $b) => $a['urutan'] <=> $b['urutan']);

            $hasil[] = [
                'id' => \Illuminate\Support\Str::slug($header),
                'title' => $header,
                'items' => $lepas,
                'subs' => array_values($subs),
                'urutan' => $semua[0]['urutan'] ?? 999,
            ];
        }
        usort($hasil, fn ($a, $b) => $a['urutan'] <=> $b['urutan']);

        return $hasil;
    }

    /**
     * PROVISION OTOMATIS kandidat: salin cetakan Klasifikasi → Page_Access +
     * Role_Menu_Access. Idempoten & aman dipanggil berkali-kali.
     */
    public static function pastikanAksesKandidat(int $idUsers, string $kodeKlasifikasi): void
    {
        try {
            $cetakan = DB::table(self::TBL_KL_MENU)
                ->where('Kode_Klasifikasi', $kodeKlasifikasi)
                ->where('Flag_Aktif', 'Y')
                ->orderBy('Urutan')
                ->get();

            if ($cetakan->isEmpty()) {
                return;
            }

            foreach ($cetakan as $c) {
                $pageId = DB::table(self::TBL_PAGE)
                    ->where('Id_Users', $idUsers)
                    ->where('Jenis_Page', $c->Jenis_Page)
                    ->value('Id_Page_Access');

                if (! $pageId) {
                    $pageId = DB::table(self::TBL_PAGE)->insertGetId([
                        'Id_Users' => $idUsers,
                        'Jenis_Page' => $c->Jenis_Page,
                        'Urutan_Menu' => $c->Urutan,
                        'Created_At' => now(), 'Created_By' => 'SISTEM(cetakan)',
                        'Updated_At' => now(), 'Updated_By' => 'SISTEM(cetakan)',
                    ], 'Id_Page_Access');
                }

                $aksiCetakan = DB::table(self::TBL_KL_AKSI)
                    ->where('Id_Klasifikasi_Menu', $c->Id_Klasifikasi_Menu)
                    ->where('Flag_Diizinkan', 'Y')
                    ->pluck('Id_Aksi');

                foreach ($aksiCetakan as $idAksi) {
                    $ada = DB::table(self::TBL_RMA)
                        ->where('Id_Page_Access', $pageId)
                        ->where('Id_Aksi', $idAksi)
                        ->exists();
                    if (! $ada) {
                        DB::table(self::TBL_RMA)->insert([
                            'Id_Page_Access' => $pageId,
                            'Id_Aksi' => $idAksi,
                            'Flag_Diizinkan' => 'Y',
                            'Created_At' => now(), 'Created_By' => 'SISTEM(cetakan)',
                            'Updated_At' => now(), 'Updated_By' => 'SISTEM(cetakan)',
                        ]);
                    }
                }

                // Kategori yang boleh dilihat kandidat = whitelist klasifikasinya.
                foreach (self::whitelistKategori($kodeKlasifikasi) as $kat) {
                    $ada = DB::table(self::TBL_RKA)
                        ->where('Id_Page_Access', $pageId)
                        ->where('Kategori', $kat)
                        ->exists();
                    if (! $ada) {
                        DB::table(self::TBL_RKA)->insert([
                            'Id_Page_Access' => $pageId,
                            'Kategori' => $kat,
                            'Flag_Diizinkan' => 'Y',
                            'Created_At' => now(), 'Created_By' => 'SISTEM(cetakan)',
                            'Updated_At' => now(), 'Updated_By' => 'SISTEM(cetakan)',
                        ]);
                    }
                }
            }
        } catch (\Throwable $e) {
            // Gagal provisioning tidak boleh mengunci kandidat dari sistem.
            Log::channel('web_career')->error("Gagal provisioning akses kandidat #{$idUsers}: " . $e->getMessage());
        }
    }

    /**
     * Menu yang sedang DALAM PEMELIHARAAN (Flag_Maintenance = 'Y'), atau null.
     * Dibaca middleware untuk menutup halaman tanpa perlu deploy.
     * Di-cache singkat karena dipanggil pada hampir setiap request.
     */
    public static function menuMaintenance(string $jenisPage): ?object
    {
        $peta = Cache::remember('wc_menu_maintenance', now()->addMinutes(2), function () {
            return DB::table(self::TBL_MENU)
                ->where('Flag_Maintenance', 'Y')
                ->get(['Jenis_Page', 'Nama_Menu', 'Untuk_Role'])
                ->keyBy('Jenis_Page');
        });

        return $peta->get($jenisPage);
    }

    /** Buang cache daftar menu dalam pemeliharaan (dipanggil saat flag diubah). */
    public static function lupakanMaintenance(): void
    {
        Cache::forget('wc_menu_maintenance');
    }

    /** Kategori program yang boleh diakses sebuah klasifikasi akun (whitelist). */
    public static function whitelistKategori(string $kodeKlasifikasi): array
    {
        return DB::table(self::TBL_KL_WL)
            ->where('Kode_Klasifikasi', $kodeKlasifikasi)
            ->where('Flag_Diizinkan', 'Y')
            ->pluck('Kategori')
            ->all();
    }

    // ═══════════════ PEMBACA SESI (dipakai controller & middleware) ═══════════════

    /** Semua aksi yang dimiliki user pada sebuah halaman. */
    public static function aksi(string $jenisPage): array
    {
        return session("career_akses.permissions.{$jenisPage}", []);
    }

    public static function boleh(string $jenisPage, string $aksi): bool
    {
        return in_array(strtoupper($aksi), self::aksi($jenisPage), true);
    }

    /**
     * Kategori yang boleh dilihat user pada sebuah halaman. NULL = tak dibatasi
     * (halaman itu memang tidak peduli kategori).
     */
    public static function kategoriDiizinkan(string $jenisPage): ?array
    {
        $k = session("career_akses.permission_konten.{$jenisPage}");

        return is_array($k) && $k ? $k : null;
    }

    /** Terapkan penyaring kategori ke query — dipakai halaman yang peduli kategori. */
    public static function saringKategori($query, string $jenisPage, string $kolom = 'Kategori')
    {
        $izin = self::kategoriDiizinkan($jenisPage);

        return $izin ? $query->whereIn($kolom, $izin) : $query;
    }

    /**
     * TAB/CHIP KATEGORI yang boleh dilihat pengguna pada sebuah halaman.
     *
     * KENAPA DI SINI, BUKAN DI TIAP CONTROLLER
     * Pola "ambil master talent → saring pakai kategoriDiizinkan()" sudah tersalin
     * di beberapa halaman, dan yang belum menyalinnya menampilkan SELURUH kategori
     * kepada admin yang cuma dijatah satu. Akibatnya admin MT melihat chip
     * "Rekrutmen", menekannya, lalu mendapat daftar kosong — atau lebih buruk,
     * mendapat isinya karena daftarnya sendiri juga lupa disaring. Satu salinan
     * yang benar lebih murah daripada mengejar salinan yang tertinggal.
     *
     * @return array<int, array{kode:string, label:string, nama:string}>
     */
    public static function tabKategori(string $jenisPage): array
    {
        $izin = self::kategoriDiizinkan($jenisPage);   // null = tidak dibatasi

        return DB::table('N_WEB_CAREERS_Master_Talent_Acquisition')
            ->where('Flag_Aktif', 'Y')
            ->when($izin, fn ($q) => $q->whereIn('Kode', $izin))
            ->orderBy('Id_Master_Talent_Acquisition')
            ->get(['Kode', 'Nama'])
            ->map(fn ($r) => ['kode' => $r->Kode, 'label' => $r->Nama, 'nama' => $r->Nama])
            ->all();
    }

    /**
     * LINGKUP PENANGGUNG JAWAB MPP pada sebuah halaman.
     *
     *   SENDIRI  MPP yang PIC-nya dirinya sendiri
     *   TIM      PIC-nya satu divisi/sub-divisi dengannya
     *   SEMUA    seluruhnya
     *
     * ── KENAPA TIDAK ADA / NULL BERARTI 'SEMUA' ─────────────────────────────
     *
     * Kebalikan dari kebiasaan "bila ragu, tutup" — dan itu disengaja.
     *
     * Lingkup ini hanya bisa ditegakkan bila akunnya punya Kode_Karyawan, dan
     * pengisian kolom itu manual serta menyusul. Kalau tidak-tahu berarti
     * SENDIRI, maka setiap akun yang belum diisi melihat NOL MPP — modul Program
     * Kegiatan berhenti bekerja untuk semua orang, karena sebuah kolom belum
     * sempat diisi. Pengetatan dilakukan sadar, satu akun demi satu.
     *
     * Yang menjaga data tetap ada di tempat lain dan tidak melunak: kategori
     * (Role_Konten_Access) dan aksi (Role_Menu_Access) tetap berlaku penuh.
     */
    public static function lingkupPic(string $jenisPage): string
    {
        $l = session("career_akses.permission_lingkup.{$jenisPage}");

        return in_array($l, [self::LINGKUP_SENDIRI, self::LINGKUP_TIM, self::LINGKUP_SEMUA], true)
            ? $l
            : self::LINGKUP_SEMUA;
    }

    /** Kode karyawan pengguna yang sedang masuk — null bila akunnya belum ditautkan. */
    public static function kodeKaryawanSaya(): ?string
    {
        $kode = session('career_auth.kode_karyawan');

        return is_string($kode) && $kode !== '' ? $kode : null;
    }

    /**
     * Nama karyawan pengguna yang sedang masuk.
     *
     * Dipakai layar untuk MENYEBUTKAN siapa penanggung jawab bawaannya, bukan
     * sekadar mengosongkan kotaknya. "Kosongkan bila Anda sendiri yang
     * mengerjakan" menuntut orang mempercayai aturan yang tak terlihat; nama
     * yang tertulis di kotaknya tidak menuntut apa-apa.
     *
     * Dibungkus try: tabel Karyawan milik HRIS dan bisa saja belum ada di
     * basis data pengembangan. Wizard program tidak boleh ikut mati hanya
     * karena satu nama pelengkap tak terbaca — kode karyawannya sudah cukup
     * untuk dipakai sebagai nilai.
     */
    public static function namaKaryawanSaya(): ?string
    {
        $kode = self::kodeKaryawanSaya();
        if (! $kode) {
            return null;
        }

        try {
            return DB::table('Karyawan')
                ->where('Kode_Perusahaan', self::KODE_PERUSAHAAN)
                ->where('Kode_Karyawan', $kode)
                ->value('Nama');
        } catch (\Throwable $e) {
            Log::channel('web_career')->warning('Nama karyawan sesi gagal dibaca: '.$e->getMessage());

            return null;
        }
    }

    /**
     * KODE KARYAWAN YANG BOLEH DIPEGANG PIC-nya, sesuai lingkup halaman ini.
     *
     * @return array<int, string>|null  null = tidak dibatasi (lingkup SEMUA)
     *
     * Larik KOSONG berbeda artinya dari null, dan bedanya penting: kosong berarti
     * "dibatasi, dan tidak ada satu pun yang cocok" — pemanggilnya harus
     * memulangkan nol baris, bukan seluruhnya.
     */
    public static function picDiizinkan(string $jenisPage): ?array
    {
        $lingkup = self::lingkupPic($jenisPage);
        if ($lingkup === self::LINGKUP_SEMUA) {
            return null;
        }

        $saya = self::kodeKaryawanSaya();
        if (! $saya) {
            // Dibatasi tapi tak punya identitas karyawan: tidak ada MPP yang bisa
            // diakui miliknya. Ini keadaan yang HARUS terlihat kosong, bukan
            // diam-diam dilonggarkan — kalau tidak, lingkup SENDIRI yang salah
            // pasang akan terbaca seperti SEMUA dan tak seorang pun menyadarinya.
            return [];
        }

        if ($lingkup === self::LINGKUP_SENDIRI) {
            return [$saya];
        }

        // TIM — SATU DIVISI/SUB-DIVISI, BUKAN SATU LEVEL JABATAN.
        //
        // Karyawan membawa keduanya (ID_Divisi_Sub_Divisi dan ID_Level_Jabatan).
        // Memakai kesetaraan level akan membuat seorang Staff di Finance
        // "sejajar" dengan Staff di Produksi dan berhak mengambil MPP-nya. Yang
        // membentuk tim adalah divisi, bukan pangkat.
        $divisi = DB::table('Karyawan')
            ->where('Kode_Perusahaan', self::KODE_PERUSAHAAN)
            ->where('Kode_Karyawan', $saya)
            ->value('ID_Divisi_Sub_Divisi');

        if (! $divisi) {
            return [$saya];
        }

        return DB::table('Karyawan')
            ->where('Kode_Perusahaan', self::KODE_PERUSAHAAN)
            ->where('ID_Divisi_Sub_Divisi', $divisi)
            ->where('Aktif', 'Y')
            ->pluck('Kode_Karyawan')
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Terapkan penyaring PIC LOKER ke sebuah query.
     *
     * Kembaran saringKategori() untuk sumbu yang berbeda. Ditulis sekali karena
     * pola "ambil picDiizinkan lalu whereIn" sudah muncul di worklist,
     * penjadwalan, dan hasil tes — dan salinan keempat yang lupa menangani
     * larik KOSONG (dibatasi, tapi tak ada yang cocok) akan memulangkan SELURUH
     * baris, bukan nol.
     */
    public static function saringPicLoker($query, string $jenisPage, string $kolom)
    {
        $boleh = self::picDiizinkan($jenisPage);

        return $boleh === null
            ? $query
            : $query->whereIn($kolom, $boleh ?: ['__tidak_ada__']);
    }

    /**
     * KATEGORI YANG BOLEH DILIHAT PENGGUNA DI SELURUH HALAMANNYA.
     *
     * Dipakai endpoint yang tidak tahu — dan tidak bisa tahu — halaman mana yang
     * sedang bertanya: /options/{type} melayani puluhan layar sekaligus lewat satu
     * jalur, jadi ia tak punya `jenisPage` untuk ditanyakan ke kategoriDiizinkan().
     *
     * GABUNGAN, bukan irisan. Yang dijaga di sini adalah daftar pilihan sebuah
     * dropdown, sementara gerbang sesungguhnya tetap ada di halaman masing-masing
     * (saringan kueri + penolakan saat menyimpan). Memakai irisan akan membuat
     * pengguna yang dijatah MT pada satu halaman kehilangan pilihan REKRUTMEN pada
     * halaman lain yang sebenarnya boleh ia kerjakan — menutup pekerjaan yang sah
     * demi celah yang sudah tertutup di tempat lain.
     *
     * Satu halaman TANPA catatan kategori berarti "tidak dibatasi", dan itu
     * menular ke hasil: pengguna seperti itu memang tidak dibatasi di mana pun.
     *
     * @return array<int, string>|null  null = tidak dibatasi
     */
    public static function kategoriSemuaHalaman(): ?array
    {
        $konten = session('career_akses.permission_konten');

        if (! is_array($konten) || ! $konten) {
            return null;
        }

        $gabungan = [];
        foreach ($konten as $daftar) {
            if (! is_array($daftar) || ! $daftar) {
                return null;
            }
            foreach ($daftar as $kategori) {
                $gabungan[$kategori] = true;
            }
        }

        return $gabungan ? array_keys($gabungan) : null;
    }

    /**
     * Saring pilihan kategori yang DIMINTA agar tak melampaui izin.
     *
     * Chip di layar hanya rupa; permintaannya tetap bisa dikarang sendiri
     * (`?jenis=REKRUTMEN`). Kategori yang tidak diizinkan dikembalikan sebagai
     * string kosong = "semua yang boleh", bukan ditolak dengan galat: yang
     * mengetiknya bukan penyerang, umumnya cuma tautan lama atau tab tersimpan.
     */
    public static function kategoriDiminta(string $jenisPage, ?string $diminta): string
    {
        $diminta = trim((string) $diminta);
        if ($diminta === '') {
            return '';
        }

        $izin = self::kategoriDiizinkan($jenisPage);

        return ($izin === null || in_array($diminta, $izin, true)) ? $diminta : '';
    }
}
