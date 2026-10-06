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

/**
 * WEB CAREERS — AKSES KLASIFIKASI AKUN (khas Web Careers, tidak ada di cat-evo).
 *
 * Kandidat mendaftar sendiri dan jumlahnya tak terbatas — mustahil admin
 * mencentang hak akses satu per satu. Maka yang diatur di sini adalah CETAKAN:
 *   1) MENU + AKSI yang otomatis diberikan ke kandidat sebuah Klasifikasi Akun.
 *   2) WHITELIST KATEGORI program yang boleh dilihat/dilamar klasifikasi itu.
 *
 * Saat kandidat login/registrasi, AksesService menyalin cetakan ini menjadi
 * hak akses nyata (Page_Access + Role_Menu_Access + Role_Konten_Access).
 * Mengubah cetakan → cache semua kandidat dibuang supaya langsung berlaku.
 */
class KlasifikasiAksesController extends Controller
{
    private string $tMenu = 'N_WEB_CAREERS_Klasifikasi_Menu';

    private string $tMenuAksi = 'N_WEB_CAREERS_Klasifikasi_Menu_Aksi';

    private string $tWl = 'N_WEB_CAREERS_Klasifikasi_Whitelist';

    public function index()
    {
        return Inertia::render('Career/admin/akses/klasifikasiAkses', CareerShell::props('/klasifikasi-akses', 'Akses Klasifikasi Akun'));
    }

    /** Semua klasifikasi + cetakan menu/aksi + whitelist kategori. */
    public function list()
    {
        try {
            $aksi = DB::table('N_WEB_CAREERS_Aksi')->where('Flag_Aktif', 'Y')->orderBy('Urutan')
                ->get(['Id_Aksi as id', 'Nama_Aksi as nama']);

            $menuKandidat = DB::table('N_WEB_CAREERS_Menu')
                ->where('Flag_Aktif', 'Y')
                ->whereIn('Untuk_Role', ['KANDIDAT'])
                ->orderBy('Urutan')
                ->get(['Jenis_Page as jenisPage', 'Nama_Menu as nama', 'Icon_Menu as ikon', 'Nama_Header as header']);

            $kategori = DB::table('N_WEB_CAREERS_Master_Talent_Acquisition')->where('Flag_Aktif', 'Y')->orderBy('Nama')
                ->get(['Kode as kode', 'Nama as nama']);

            $cetakan = DB::table($this->tMenu)->get();
            $cetakanAksi = DB::table($this->tMenuAksi)->get()->groupBy('Id_Klasifikasi_Menu');
            $whitelist = DB::table($this->tWl)->where('Flag_Diizinkan', 'Y')->get()->groupBy('Kode_Klasifikasi');

            $rows = DB::table('N_WEB_CAREERS_Klasifikasi_Akun')
                ->orderBy('Id_Klasifikasi_Akun')
                ->get()
                ->map(function ($k) use ($cetakan, $cetakanAksi, $whitelist) {
                    $menu = $cetakan->where('Kode_Klasifikasi', $k->Kode)->map(fn ($c) => [
                        'jenisPage' => $c->Jenis_Page,
                        'aktif' => $c->Flag_Aktif === 'Y',
                        'aksi' => collect($cetakanAksi->get($c->Id_Klasifikasi_Menu, []))
                            ->filter(fn ($a) => $a->Flag_Diizinkan === 'Y')->pluck('Id_Aksi')->values(),
                    ])->values();

                    return [
                        'kode' => $k->Kode,
                        'nama' => $k->Nama,
                        'durasiHari' => $k->Durasi_Hari,
                        'defaultRegister' => $k->Is_Default_Register === 'Y',
                        'status' => $k->Flag_Aktif === 'Y' ? 'AKTIF' : 'NONAKTIF',
                        'menu' => $menu,
                        'kategori' => collect($whitelist->get($k->Kode, []))->pluck('Kategori')->values(),
                        'jumlahKandidat' => DB::table('N_WEB_CAREERS_Users')->where('Klasifikasi', $k->Kode)->where('Role', 'KANDIDAT')->count(),
                    ];
                })->values();

            return ResponseHelper::success([
                'klasifikasi' => $rows,
                'aksi' => $aksi,
                'menu' => $menuKandidat,
                'kategori' => $kategori,
            ], 'Data akses klasifikasi dimuat');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('Gagal memuat akses klasifikasi: ' . $e->getMessage());

            return ResponseHelper::error('Gagal memuat data', 500);
        }
    }

    /** Centang/lepas MENU pada cetakan sebuah klasifikasi. */
    public function toggleMenu(Request $request)
    {
        try {
            $data = $request->validate([
                'kode' => 'required|string|max:30',
                'jenisPage' => 'required|string|max:60',
                'aktif' => 'required|boolean',
            ]);

            $nama = session('career_auth.nama', 'ADMIN');
            $row = DB::table($this->tMenu)->where('Kode_Klasifikasi', $data['kode'])->where('Jenis_Page', $data['jenisPage'])->first();

            if ($row) {
                DB::table($this->tMenu)->where('Id_Klasifikasi_Menu', $row->Id_Klasifikasi_Menu)
                    ->update(['Flag_Aktif' => $data['aktif'] ? 'Y' : 'N', 'Updated_At' => now(), 'Updated_By' => $nama]);
                $id = $row->Id_Klasifikasi_Menu;
            } else {
                $urutan = (int) DB::table('N_WEB_CAREERS_Menu')->where('Jenis_Page', $data['jenisPage'])->value('Urutan');
                $id = DB::table($this->tMenu)->insertGetId([
                    'Kode_Klasifikasi' => $data['kode'], 'Jenis_Page' => $data['jenisPage'],
                    'Urutan' => $urutan, 'Flag_Aktif' => $data['aktif'] ? 'Y' : 'N',
                    'Created_At' => now(), 'Created_By' => $nama, 'Updated_At' => now(), 'Updated_By' => $nama,
                ], 'Id_Klasifikasi_Menu');
            }

            // Menu baru dicentang tapi belum punya aksi → beri VIEW supaya berguna.
            if ($data['aktif'] && ! DB::table($this->tMenuAksi)->where('Id_Klasifikasi_Menu', $id)->exists()) {
                $idView = DB::table('N_WEB_CAREERS_Aksi')->where('Nama_Aksi', 'VIEW')->value('Id_Aksi');
                if ($idView) {
                    DB::table($this->tMenuAksi)->insert(['Id_Klasifikasi_Menu' => $id, 'Id_Aksi' => $idView, 'Flag_Diizinkan' => 'Y']);
                }
            }

            $this->terapkanUlang($data['kode']);

            return ResponseHelper::success(null, 'Cetakan menu diperbarui.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ResponseHelper::error(collect($e->errors())->flatten()->first() ?? 'Data tidak valid', 422);
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('Gagal toggle cetakan menu: ' . $e->getMessage());

            return ResponseHelper::error('Gagal memperbarui cetakan', 500);
        }
    }

    /** Centang/lepas AKSI pada sebuah menu cetakan. */
    public function toggleAksi(Request $request)
    {
        try {
            $data = $request->validate([
                'kode' => 'required|string|max:30',
                'jenisPage' => 'required|string|max:60',
                'idAksi' => 'required|integer',
                'aktif' => 'required|boolean',
            ]);

            $nama = session('career_auth.nama', 'ADMIN');
            $id = DB::table($this->tMenu)->where('Kode_Klasifikasi', $data['kode'])->where('Jenis_Page', $data['jenisPage'])->value('Id_Klasifikasi_Menu');
            if (! $id) {
                return ResponseHelper::error('Menu belum ada di cetakan klasifikasi ini — centang menunya dulu.', 422);
            }

            $ada = DB::table($this->tMenuAksi)->where('Id_Klasifikasi_Menu', $id)->where('Id_Aksi', $data['idAksi'])->exists();
            if ($ada) {
                DB::table($this->tMenuAksi)->where('Id_Klasifikasi_Menu', $id)->where('Id_Aksi', $data['idAksi'])
                    ->update(['Flag_Diizinkan' => $data['aktif'] ? 'Y' : 'N']);
            } else {
                DB::table($this->tMenuAksi)->insert([
                    'Id_Klasifikasi_Menu' => $id, 'Id_Aksi' => $data['idAksi'], 'Flag_Diizinkan' => $data['aktif'] ? 'Y' : 'N',
                ]);
            }

            $this->terapkanUlang($data['kode']);
            Log::channel('web_career')->info("Cetakan aksi klasifikasi {$data['kode']} diubah oleh {$nama}");

            return ResponseHelper::success(null, 'Cetakan aksi diperbarui.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ResponseHelper::error(collect($e->errors())->flatten()->first() ?? 'Data tidak valid', 422);
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('Gagal toggle cetakan aksi: ' . $e->getMessage());

            return ResponseHelper::error('Gagal memperbarui cetakan', 500);
        }
    }

    /** Centang/lepas KATEGORI program pada whitelist klasifikasi. */
    public function toggleWhitelist(Request $request)
    {
        try {
            $data = $request->validate([
                'kode' => 'required|string|max:30',
                'kategori' => 'required|string|max:30',
                'aktif' => 'required|boolean',
            ]);

            $nama = session('career_auth.nama', 'ADMIN');
            $ada = DB::table($this->tWl)->where('Kode_Klasifikasi', $data['kode'])->where('Kategori', $data['kategori'])->exists();

            if ($ada) {
                DB::table($this->tWl)->where('Kode_Klasifikasi', $data['kode'])->where('Kategori', $data['kategori'])
                    ->update(['Flag_Diizinkan' => $data['aktif'] ? 'Y' : 'N', 'Updated_At' => now(), 'Updated_By' => $nama]);
            } else {
                DB::table($this->tWl)->insert([
                    'Kode_Klasifikasi' => $data['kode'], 'Kategori' => $data['kategori'],
                    'Flag_Diizinkan' => $data['aktif'] ? 'Y' : 'N',
                    'Created_At' => now(), 'Created_By' => $nama, 'Updated_At' => now(), 'Updated_By' => $nama,
                ]);
            }

            $this->terapkanUlang($data['kode']);

            return ResponseHelper::success(null, 'Whitelist program diperbarui.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ResponseHelper::error(collect($e->errors())->flatten()->first() ?? 'Data tidak valid', 422);
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('Gagal toggle whitelist: ' . $e->getMessage());

            return ResponseHelper::error('Gagal memperbarui whitelist', 500);
        }
    }

    /**
     * Terapkan cetakan ke SELURUH kandidat berklasifikasi ini sekarang juga
     * (tanpa menunggu mereka login). Dipanggil setiap cetakan berubah.
     */
    public function terapkan(Request $request)
    {
        try {
            $data = $request->validate(['kode' => 'required|string|max:30']);
            $jumlah = $this->terapkanUlang($data['kode'], true);

            return ResponseHelper::success(['kandidat' => $jumlah], "Cetakan diterapkan ke {$jumlah} kandidat.");
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('Gagal menerapkan cetakan: ' . $e->getMessage());

            return ResponseHelper::error('Gagal menerapkan cetakan', 500);
        }
    }

    /**
     * Buang cache kandidat berklasifikasi ini; bila $provision=true, salin juga
     * cetakan ke hak akses nyata mereka saat ini (sinkron langsung).
     */
    private function terapkanUlang(string $kode, bool $provision = false): int
    {
        $kandidat = DB::table('N_WEB_CAREERS_Users')
            ->where('Klasifikasi', $kode)
            ->where('Role', 'KANDIDAT')
            ->pluck('Id_Users');

        foreach ($kandidat as $id) {
            AksesService::lupakan((int) $id);
            if ($provision) {
                AksesService::pastikanAksesKandidat((int) $id, $kode);
            }
        }

        return $kandidat->count();
    }
}
