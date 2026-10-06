<?php

namespace App\Http\Controllers\Career\Akses;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Support\Career\AksesService;
use App\Support\CareerShell;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Vinkla\Hashids\Facades\Hashids;

/**
 * WEB CAREERS — MANAJEMEN HAK AKSES (padanan RoleMenuAccessController cat-evo).
 *
 * Tampilan "WordPress style": daftar pengguna sebagai kartu accordion; di dalam
 * tiap pengguna ada daftar halaman, dan tiap halaman punya dua baris centang:
 *   1) AKSI    → VIEW / CREATE / EDIT / DELETE / APPROVE / PRINT / EXPORT
 *   2) KONTEN  → KATEGORI program (REKRUTMEN / MT / INTERNSHIP ...)
 *
 * Setiap perubahan membuang cache akses pengguna terkait supaya berlaku
 * seketika tanpa ia perlu login ulang.
 */
class HakAksesController extends Controller
{
    private string $tPage = 'N_WEB_CAREERS_Page_Access';

    private string $tRma = 'N_WEB_CAREERS_Role_Menu_Access';

    private string $tRka = 'N_WEB_CAREERS_Role_Konten_Access';

    public function index()
    {
        return Inertia::render('Career/admin/akses/hakAkses', CareerShell::props('/hak-akses', 'Manajemen Hak Akses'));
    }

    /** Ringkasan kartu statistik. */
    public function summary()
    {
        try {
            return ResponseHelper::success([
                'total_user' => (int) DB::table($this->tPage)->distinct()->count('Id_Users'),
                'total_config' => (int) DB::table($this->tPage)->count(),
                'total_aksi' => (int) DB::table($this->tRma)->where('Flag_Diizinkan', 'Y')->count(),
                'total_konten' => (int) DB::table($this->tRka)->where('Flag_Diizinkan', 'Y')->count(),
            ], 'Ringkasan dimuat');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('Gagal ringkasan hak akses: ' . $e->getMessage());

            return ResponseHelper::error('Gagal memuat ringkasan', 500);
        }
    }

    /** Referensi: daftar aksi, menu, kategori, dan pengguna (untuk modal tambah). */
    public function referensi()
    {
        try {
            return ResponseHelper::success([
                'aksi' => DB::table('N_WEB_CAREERS_Aksi')->where('Flag_Aktif', 'Y')->orderBy('Urutan')
                    ->get(['Id_Aksi as id', 'Nama_Aksi as nama', 'Keterangan as ket']),
                'menu' => DB::table('N_WEB_CAREERS_Menu')->where('Flag_Aktif', 'Y')->orderBy('Untuk_Role')->orderBy('Urutan')
                    ->get(['Jenis_Page as jenisPage', 'Nama_Menu as nama', 'Nama_Header as header', 'Icon_Menu as ikon', 'Untuk_Role as role', 'Urutan as urutan']),
                'kategori' => DB::table('N_WEB_CAREERS_Master_Talent_Acquisition')->where('Flag_Aktif', 'Y')->orderBy('Nama')
                    ->get(['Kode as kode', 'Nama as nama']),
                'pengguna' => DB::table('N_WEB_CAREERS_Users')->where('Status', 'AKTIF')->orderBy('Nama')->limit(500)
                    ->get(['Id_Users', 'Nama', 'Email', 'Role'])
                    ->map(fn ($u) => ['id' => Hashids::encode($u->Id_Users), 'nama' => $u->Nama, 'email' => $u->Email, 'role' => $u->Role]),
            ], 'Referensi dimuat');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('Gagal referensi hak akses: ' . $e->getMessage());

            return ResponseHelper::error('Gagal memuat referensi', 500);
        }
    }

    /** Daftar pengguna + halaman + aksi + konten (accordion), tersaring & berhalaman. */
    public function list(Request $request)
    {
        try {
            $q = trim((string) $request->query('q', ''));
            $role = strtoupper(trim((string) $request->query('role', '')));
            $page = max(1, (int) $request->query('page', 1));
            $limit = min(50, max(5, (int) $request->query('limit', 10)));

            $userQ = DB::table('N_WEB_CAREERS_Users as u')
                ->whereExists(fn ($x) => $x->select(DB::raw(1))->from($this->tPage . ' as pa')->whereColumn('pa.Id_Users', 'u.Id_Users'))
                ->when($q !== '', fn ($w) => $w->where(function ($x) use ($q) {
                    $x->where('u.Nama', 'like', "%{$q}%")->orWhere('u.Email', 'like', "%{$q}%");
                }))
                ->when(in_array($role, ['ADMIN', 'SUPERADMIN', 'KANDIDAT'], true), fn ($w) => $w->where('u.Role', $role));

            $total = (clone $userQ)->count();
            $users = $userQ->orderBy('u.Nama')->forPage($page, $limit)->get(['u.Id_Users', 'u.Nama', 'u.Email', 'u.Role', 'u.Klasifikasi', 'u.Kode_Karyawan']);

            $ids = $users->pluck('Id_Users')->all() ?: [0];

            $pages = DB::table($this->tPage . ' as pa')
                ->leftJoin('N_WEB_CAREERS_Menu as m', 'm.Jenis_Page', '=', 'pa.Jenis_Page')
                ->whereIn('pa.Id_Users', $ids)
                ->orderBy('pa.Urutan_Menu')
                ->get(['pa.Id_Page_Access', 'pa.Id_Users', 'pa.Jenis_Page', 'pa.Urutan_Menu', 'pa.Lingkup_Pic', 'm.Nama_Menu', 'm.Nama_Header', 'm.Icon_Menu', 'm.Untuk_Role'])
                ->groupBy('Id_Users');

            $pageIds = $pages->flatten(1)->pluck('Id_Page_Access')->all() ?: [0];
            $aksi = DB::table($this->tRma)->whereIn('Id_Page_Access', $pageIds)->get()->groupBy('Id_Page_Access');
            $konten = DB::table($this->tRka)->whereIn('Id_Page_Access', $pageIds)->get()->groupBy('Id_Page_Access');

            $data = $users->map(function ($u) use ($pages, $aksi, $konten) {
                $hal = collect($pages->get($u->Id_Users, []))->map(function ($p) use ($aksi, $konten) {
                    return [
                        'idPageAccess' => Hashids::encode($p->Id_Page_Access),
                        'jenisPage' => $p->Jenis_Page,
                        'namaMenu' => $p->Nama_Menu ?: $p->Jenis_Page,
                        'header' => $p->Nama_Header,
                        'ikon' => $p->Icon_Menu ?: 'bi bi-dot',
                        'role' => $p->Untuk_Role,
                        'urutan' => (int) $p->Urutan_Menu,
                        'aksi' => collect($aksi->get($p->Id_Page_Access, []))
                            ->filter(fn ($a) => $a->Flag_Diizinkan === 'Y')
                            ->pluck('Id_Aksi')->values(),
                        'kategori' => collect($konten->get($p->Id_Page_Access, []))
                            ->filter(fn ($k) => $k->Flag_Diizinkan === 'Y')
                            ->pluck('Kategori')->values(),
                        // Lingkup penanggung jawab MPP untuk halaman ini.
                        // Kosong dibaca 'SEMUA' — sama dengan yang ditegakkan
                        // AksesService::lingkupPic(), supaya layar dan gerbangnya
                        // tidak bisa menampilkan dua jawaban berbeda.
                        'lingkupPic' => $p->Lingkup_Pic ?: 'SEMUA',
                    ];
                })->values();

                return [
                    'id' => Hashids::encode($u->Id_Users),
                    // Dipakai layar untuk memperingatkan: lingkup SENDIRI/TIM pada
                    // akun yang belum ditautkan ke karyawan berarti nol MPP, dan
                    // itu harus terbaca SEBELUM lingkupnya diubah — bukan sebagai
                    // laporan "daftar MPP saya kosong" beberapa hari kemudian.
                    'kodeKaryawan' => $u->Kode_Karyawan ?? null,
                    'nama' => $u->Nama,
                    'email' => $u->Email,
                    'role' => $u->Role,
                    'klasifikasi' => $u->Klasifikasi,
                    'jumlahHalaman' => $hal->count(),
                    'halaman' => $hal,
                ];
            })->values();

            return ResponseHelper::success([
                'data' => $data,
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'totalPage' => (int) ceil($total / $limit),
            ], 'Data hak akses dimuat');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('Gagal memuat hak akses: ' . $e->getMessage());

            return ResponseHelper::error('Gagal memuat data hak akses', 500);
        }
    }

    /** Beri seorang pengguna satu/lebih halaman (default: aksi VIEW). */
    public function store(Request $request)
    {
        try {
            $data = $request->validate([
                'userId' => 'required|string',
                'pages' => 'required|array|min:1',
                'pages.*' => 'required|string|max:60',
            ]);

            $idUsers = Hashids::decode($data['userId'])[0] ?? null;
            if (! $idUsers || ! DB::table('N_WEB_CAREERS_Users')->where('Id_Users', $idUsers)->exists()) {
                return ResponseHelper::error('Pengguna tidak ditemukan.', 404);
            }

            $idView = DB::table('N_WEB_CAREERS_Aksi')->where('Nama_Aksi', 'VIEW')->value('Id_Aksi');
            $nama = session('career_auth.nama', 'ADMIN');
            $dibuat = 0;

            DB::transaction(function () use ($data, $idUsers, $idView, $nama, &$dibuat) {
                foreach ($data['pages'] as $jenisPage) {
                    $menu = DB::table('N_WEB_CAREERS_Menu')->where('Jenis_Page', $jenisPage)->first();
                    if (! $menu) {
                        continue;
                    }
                    $ada = DB::table($this->tPage)->where('Id_Users', $idUsers)->where('Jenis_Page', $jenisPage)->exists();
                    if ($ada) {
                        continue;
                    }

                    $pageId = DB::table($this->tPage)->insertGetId([
                        'Id_Users' => $idUsers,
                        'Jenis_Page' => $jenisPage,
                        'Urutan_Menu' => $menu->Urutan,
                        'Created_At' => now(), 'Created_By' => $nama, 'Created_By_Id' => session('career_auth.id'),
                        'Updated_At' => now(), 'Updated_By' => $nama, 'Updated_By_Id' => session('career_auth.id'),
                    ], 'Id_Page_Access');

                    if ($idView) {
                        DB::table($this->tRma)->insert([
                            'Id_Page_Access' => $pageId, 'Id_Aksi' => $idView, 'Flag_Diizinkan' => 'Y',
                            'Created_At' => now(), 'Created_By' => $nama, 'Updated_At' => now(), 'Updated_By' => $nama,
                        ]);
                    }
                    $dibuat++;
                }
            });

            AksesService::lupakan((int) $idUsers);
            Log::channel('web_career')->info("Hak akses: {$dibuat} halaman diberikan ke user #{$idUsers} oleh {$nama}");

            return ResponseHelper::success(['dibuat' => $dibuat], "{$dibuat} halaman ditambahkan (aksi awal: VIEW).", 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ResponseHelper::error(collect($e->errors())->flatten()->first() ?? 'Data tidak valid', 422);
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('Gagal menambah hak akses: ' . $e->getMessage());

            return ResponseHelper::error('Gagal menyimpan data', 500);
        }
    }

    /** Centang/lepas satu AKSI pada satu halaman. */
    public function toggleAksi(Request $request)
    {
        try {
            $data = $request->validate([
                'idPageAccess' => 'required|string',
                'idAksi' => 'required|integer',
                'aktif' => 'required|boolean',
            ]);

            $pageId = Hashids::decode($data['idPageAccess'])[0] ?? null;
            $pa = $pageId ? DB::table($this->tPage)->where('Id_Page_Access', $pageId)->first() : null;
            if (! $pa) {
                return ResponseHelper::error('Konfigurasi halaman tidak ditemukan.', 404);
            }

            $nama = session('career_auth.nama', 'ADMIN');
            $ada = DB::table($this->tRma)->where('Id_Page_Access', $pageId)->where('Id_Aksi', $data['idAksi'])->first();

            if ($ada) {
                DB::table($this->tRma)->where('Id_Role_Menu_Access', $ada->Id_Role_Menu_Access)
                    ->update(['Flag_Diizinkan' => $data['aktif'] ? 'Y' : 'N', 'Updated_At' => now(), 'Updated_By' => $nama]);
            } else {
                DB::table($this->tRma)->insert([
                    'Id_Page_Access' => $pageId, 'Id_Aksi' => $data['idAksi'],
                    'Flag_Diizinkan' => $data['aktif'] ? 'Y' : 'N',
                    'Created_At' => now(), 'Created_By' => $nama, 'Updated_At' => now(), 'Updated_By' => $nama,
                ]);
            }

            AksesService::lupakan((int) $pa->Id_Users);

            return ResponseHelper::success(null, 'Hak akses diperbarui.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ResponseHelper::error(collect($e->errors())->flatten()->first() ?? 'Data tidak valid', 422);
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('Gagal toggle aksi: ' . $e->getMessage());

            return ResponseHelper::error('Gagal memperbarui hak akses', 500);
        }
    }

    /**
     * Ubah LINGKUP PIC satu halaman untuk satu pengguna.
     *
     * Terpisah dari toggleAksi/toggleKonten karena bentuknya memang berbeda:
     * aksi dan kategori itu centang (banyak boleh menyala), lingkup itu pilihan
     * tunggal — tiga nilai yang saling meniadakan.
     */
    public function ubahLingkup(Request $request)
    {
        try {
            $data = $request->validate([
                'idPageAccess' => 'required|string',
                'lingkup' => 'required|in:SENDIRI,TIM,SEMUA',
            ]);

            $pageId = Hashids::decode($data['idPageAccess'])[0] ?? null;
            if (! $pageId) {
                return ResponseHelper::error('Halaman tidak valid.', 422);
            }

            $terpengaruh = DB::table($this->tPage)
                ->where('Id_Page_Access', $pageId)
                ->update(['Lingkup_Pic' => $data['lingkup']]);

            if (! $terpengaruh) {
                return ResponseHelper::error('Baris akses tidak ditemukan.', 404);
            }

            // Paket akses dibuang dari cache supaya perubahannya terasa pada
            // permintaan berikutnya, bukan sepuluh menit lagi. Tanpa ini admin
            // mengubah lingkup, mencobanya, dan menyimpulkan tombolnya rusak.
            $idUsers = (int) DB::table($this->tPage)->where('Id_Page_Access', $pageId)->value('Id_Users');
            if ($idUsers) {
                AksesService::lupakan($idUsers);
            }

            return ResponseHelper::success(null, 'Lingkup PIC diperbarui.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ResponseHelper::error(collect($e->errors())->flatten()->first() ?? 'Data tidak valid', 422);
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('Gagal mengubah lingkup PIC: ' . $e->getMessage());

            return ResponseHelper::error('Gagal mengubah lingkup PIC', 500);
        }
    }

    /** Centang/lepas satu KATEGORI (konten) pada satu halaman. */
    public function toggleKonten(Request $request)
    {
        try {
            $data = $request->validate([
                'idPageAccess' => 'required|string',
                'kategori' => 'required|string|max:30',
                'aktif' => 'required|boolean',
            ]);

            $pageId = Hashids::decode($data['idPageAccess'])[0] ?? null;
            $pa = $pageId ? DB::table($this->tPage)->where('Id_Page_Access', $pageId)->first() : null;
            if (! $pa) {
                return ResponseHelper::error('Konfigurasi halaman tidak ditemukan.', 404);
            }

            $nama = session('career_auth.nama', 'ADMIN');
            $ada = DB::table($this->tRka)->where('Id_Page_Access', $pageId)->where('Kategori', $data['kategori'])->first();

            if ($ada) {
                DB::table($this->tRka)->where('Id_Role_Konten_Access', $ada->Id_Role_Konten_Access)
                    ->update(['Flag_Diizinkan' => $data['aktif'] ? 'Y' : 'N', 'Updated_At' => now(), 'Updated_By' => $nama]);
            } else {
                DB::table($this->tRka)->insert([
                    'Id_Page_Access' => $pageId, 'Kategori' => $data['kategori'],
                    'Flag_Diizinkan' => $data['aktif'] ? 'Y' : 'N',
                    'Created_At' => now(), 'Created_By' => $nama, 'Updated_At' => now(), 'Updated_By' => $nama,
                ]);
            }

            AksesService::lupakan((int) $pa->Id_Users);

            return ResponseHelper::success(null, 'Akses kategori diperbarui.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ResponseHelper::error(collect($e->errors())->flatten()->first() ?? 'Data tidak valid', 422);
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('Gagal toggle konten: ' . $e->getMessage());

            return ResponseHelper::error('Gagal memperbarui akses kategori', 500);
        }
    }

    /** Centang/lepas SEMUA aksi (atau semua kategori) sekaligus pada satu halaman. */
    public function bulkToggle(Request $request)
    {
        try {
            $data = $request->validate([
                'idPageAccess' => 'required|string',
                'jenis' => 'required|in:AKSI,KONTEN',
                'aktif' => 'required|boolean',
            ]);

            $pageId = Hashids::decode($data['idPageAccess'])[0] ?? null;
            $pa = $pageId ? DB::table($this->tPage)->where('Id_Page_Access', $pageId)->first() : null;
            if (! $pa) {
                return ResponseHelper::error('Konfigurasi halaman tidak ditemukan.', 404);
            }

            $nama = session('career_auth.nama', 'ADMIN');
            $flag = $data['aktif'] ? 'Y' : 'N';

            DB::transaction(function () use ($data, $pageId, $flag, $nama) {
                if ($data['jenis'] === 'AKSI') {
                    foreach (DB::table('N_WEB_CAREERS_Aksi')->where('Flag_Aktif', 'Y')->pluck('Id_Aksi') as $idAksi) {
                        $ada = DB::table($this->tRma)->where('Id_Page_Access', $pageId)->where('Id_Aksi', $idAksi)->exists();
                        if ($ada) {
                            DB::table($this->tRma)->where('Id_Page_Access', $pageId)->where('Id_Aksi', $idAksi)
                                ->update(['Flag_Diizinkan' => $flag, 'Updated_At' => now(), 'Updated_By' => $nama]);
                        } else {
                            DB::table($this->tRma)->insert([
                                'Id_Page_Access' => $pageId, 'Id_Aksi' => $idAksi, 'Flag_Diizinkan' => $flag,
                                'Created_At' => now(), 'Created_By' => $nama, 'Updated_At' => now(), 'Updated_By' => $nama,
                            ]);
                        }
                    }
                } else {
                    foreach (DB::table('N_WEB_CAREERS_Master_Talent_Acquisition')->where('Flag_Aktif', 'Y')->pluck('Kode') as $kat) {
                        $ada = DB::table($this->tRka)->where('Id_Page_Access', $pageId)->where('Kategori', $kat)->exists();
                        if ($ada) {
                            DB::table($this->tRka)->where('Id_Page_Access', $pageId)->where('Kategori', $kat)
                                ->update(['Flag_Diizinkan' => $flag, 'Updated_At' => now(), 'Updated_By' => $nama]);
                        } else {
                            DB::table($this->tRka)->insert([
                                'Id_Page_Access' => $pageId, 'Kategori' => $kat, 'Flag_Diizinkan' => $flag,
                                'Created_At' => now(), 'Created_By' => $nama, 'Updated_At' => now(), 'Updated_By' => $nama,
                            ]);
                        }
                    }
                }
            });

            AksesService::lupakan((int) $pa->Id_Users);

            return ResponseHelper::success(null, 'Perubahan massal disimpan.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ResponseHelper::error(collect($e->errors())->flatten()->first() ?? 'Data tidak valid', 422);
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('Gagal bulk toggle: ' . $e->getMessage());

            return ResponseHelper::error('Gagal menyimpan perubahan', 500);
        }
    }

    /** Cabut satu halaman dari seorang pengguna (beserta aksi & kontennya). */
    public function destroy($id)
    {
        try {
            $pageId = Hashids::decode($id)[0] ?? null;
            $pa = $pageId ? DB::table($this->tPage)->where('Id_Page_Access', $pageId)->first() : null;
            if (! $pa) {
                return ResponseHelper::error('Konfigurasi tidak ditemukan.', 404);
            }

            DB::transaction(function () use ($pageId) {
                DB::table($this->tRma)->where('Id_Page_Access', $pageId)->delete();
                DB::table($this->tRka)->where('Id_Page_Access', $pageId)->delete();
                DB::table($this->tPage)->where('Id_Page_Access', $pageId)->delete();
            });

            AksesService::lupakan((int) $pa->Id_Users);
            Log::channel('web_career')->info("Hak akses halaman {$pa->Jenis_Page} dicabut dari user #{$pa->Id_Users}");

            return ResponseHelper::success(null, 'Akses halaman dicabut.');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal cabut akses #{$id}: " . $e->getMessage());

            return ResponseHelper::error('Gagal mencabut akses', 500);
        }
    }

    /** Duplikat seluruh hak akses seorang pengguna ke pengguna lain. */
    public function duplikat(Request $request)
    {
        try {
            $data = $request->validate([
                'dariUserId' => 'required|string',
                'keUserId' => 'required|string|different:dariUserId',
                'timpa' => 'nullable|boolean',
            ]);

            $dari = Hashids::decode($data['dariUserId'])[0] ?? null;
            $ke = Hashids::decode($data['keUserId'])[0] ?? null;
            if (! $dari || ! $ke) {
                return ResponseHelper::error('Pengguna tidak valid.', 422);
            }

            $sumber = DB::table($this->tPage)->where('Id_Users', $dari)->get();
            if ($sumber->isEmpty()) {
                return ResponseHelper::error('Pengguna sumber belum punya hak akses.', 422);
            }

            $nama = session('career_auth.nama', 'ADMIN');
            $disalin = 0;

            DB::transaction(function () use ($sumber, $ke, $data, $nama, &$disalin) {
                if ($data['timpa'] ?? false) {
                    $lama = DB::table($this->tPage)->where('Id_Users', $ke)->pluck('Id_Page_Access')->all();
                    if ($lama) {
                        DB::table($this->tRma)->whereIn('Id_Page_Access', $lama)->delete();
                        DB::table($this->tRka)->whereIn('Id_Page_Access', $lama)->delete();
                        DB::table($this->tPage)->whereIn('Id_Page_Access', $lama)->delete();
                    }
                }

                foreach ($sumber as $s) {
                    $adaId = DB::table($this->tPage)->where('Id_Users', $ke)->where('Jenis_Page', $s->Jenis_Page)->value('Id_Page_Access');
                    if ($adaId) {
                        $baruId = $adaId;
                    } else {
                        $baruId = DB::table($this->tPage)->insertGetId([
                            'Id_Users' => $ke, 'Jenis_Page' => $s->Jenis_Page, 'Urutan_Menu' => $s->Urutan_Menu,
                            // Susunan menu (urutan, grup, label, ikon) ikut tersalin —
                            // kalau tidak, hasil duplikat kehilangan tata letak sidebar
                            // yang sudah disusun di /hak-akses/susun/{user}.
                            'Nama_Header_Custom' => $s->Nama_Header_Custom,
                            'Nama_Grup_Custom' => $s->Nama_Grup_Custom,
                            'Sub_Header_Custom' => $s->Sub_Header_Custom,
                            'Nama_Menu_Custom' => $s->Nama_Menu_Custom,
                            'Icon_Menu_Custom' => $s->Icon_Menu_Custom,
                            'Created_At' => now(), 'Created_By' => $nama . ' (duplikat)', 'Created_By_Id' => session('career_auth.id'),
                            'Updated_At' => now(), 'Updated_By' => $nama,
                        ], 'Id_Page_Access');
                        $disalin++;
                    }

                    foreach (DB::table($this->tRma)->where('Id_Page_Access', $s->Id_Page_Access)->get() as $a) {
                        $ex = DB::table($this->tRma)->where('Id_Page_Access', $baruId)->where('Id_Aksi', $a->Id_Aksi)->exists();
                        if ($ex) {
                            DB::table($this->tRma)->where('Id_Page_Access', $baruId)->where('Id_Aksi', $a->Id_Aksi)
                                ->update(['Flag_Diizinkan' => $a->Flag_Diizinkan, 'Updated_At' => now(), 'Updated_By' => $nama]);
                        } else {
                            DB::table($this->tRma)->insert([
                                'Id_Page_Access' => $baruId, 'Id_Aksi' => $a->Id_Aksi, 'Flag_Diizinkan' => $a->Flag_Diizinkan,
                                'Created_At' => now(), 'Created_By' => $nama, 'Updated_At' => now(), 'Updated_By' => $nama,
                            ]);
                        }
                    }

                    foreach (DB::table($this->tRka)->where('Id_Page_Access', $s->Id_Page_Access)->get() as $k) {
                        $ex = DB::table($this->tRka)->where('Id_Page_Access', $baruId)->where('Kategori', $k->Kategori)->exists();
                        if ($ex) {
                            DB::table($this->tRka)->where('Id_Page_Access', $baruId)->where('Kategori', $k->Kategori)
                                ->update(['Flag_Diizinkan' => $k->Flag_Diizinkan, 'Updated_At' => now(), 'Updated_By' => $nama]);
                        } else {
                            DB::table($this->tRka)->insert([
                                'Id_Page_Access' => $baruId, 'Kategori' => $k->Kategori, 'Flag_Diizinkan' => $k->Flag_Diizinkan,
                                'Created_At' => now(), 'Created_By' => $nama, 'Updated_At' => now(), 'Updated_By' => $nama,
                            ]);
                        }
                    }
                }
            });

            AksesService::lupakan((int) $ke);
            Log::channel('web_career')->info("Hak akses user #{$dari} diduplikat ke #{$ke} oleh {$nama}");

            return ResponseHelper::success(['disalin' => $disalin], 'Hak akses berhasil diduplikat.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ResponseHelper::error(collect($e->errors())->flatten()->first() ?? 'Data tidak valid', 422);
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('Gagal duplikat hak akses: ' . $e->getMessage());

            return ResponseHelper::error('Gagal menduplikat hak akses', 500);
        }
    }
}
