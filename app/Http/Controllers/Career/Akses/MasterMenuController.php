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
 * WEB CAREERS — MASTER MENU (daftar halaman yang bisa diberi hak akses).
 * Padanan HRIS_KANDIDAT_Menus di cat-evo-pembaharuan.
 *
 * Jenis_Page adalah KUNCI PERMISSION yang dipakai middleware
 * career.permission:{Jenis_Page},{AKSI} — karena itu tidak boleh diubah
 * sembarangan setelah dipakai route (diberi peringatan di UI).
 */
class MasterMenuController extends Controller
{
    private string $tbl = 'N_WEB_CAREERS_Menu';

    public function index()
    {
        return Inertia::render('Career/admin/akses/masterMenu', CareerShell::props('/master-menu', 'Master Menu'));
    }

    public function list(Request $request)
    {
        try {
            $q = trim((string) $request->query('q', ''));
            $role = strtoupper(trim((string) $request->query('role', '')));
            $status = strtoupper(trim((string) $request->query('status', '')));

            $rows = DB::table($this->tbl)
                ->when($q !== '', fn ($w) => $w->where(function ($x) use ($q) {
                    $x->where('Nama_Menu', 'like', "%{$q}%")
                        ->orWhere('Jenis_Page', 'like', "%{$q}%")
                        ->orWhere('Url_Menu', 'like', "%{$q}%")
                        ->orWhere('Nama_Header', 'like', "%{$q}%");
                }))
                ->when(in_array($role, ['ADMIN', 'KANDIDAT'], true), fn ($w) => $w->where('Untuk_Role', $role))
                ->when(in_array($status, ['AKTIF', 'NONAKTIF'], true), fn ($w) => $w->where('Flag_Aktif', $status === 'AKTIF' ? 'Y' : 'N'))
                ->orderBy('Untuk_Role')
                ->orderBy('Urutan')
                ->get()
                ->map(fn ($r) => [
                    'id' => Hashids::encode($r->Id_Menu),
                    'jenisPage' => $r->Jenis_Page,
                    'nama' => $r->Nama_Menu,
                    'header' => $r->Nama_Header,
                    'subHeader' => $r->Sub_Header,
                    'grup' => $r->Nama_Grup,
                    'urutanGrup' => $r->Urutan_Grup !== null ? (int) $r->Urutan_Grup : null,
                    'ikon' => $r->Icon_Menu,
                    'url' => $r->Url_Menu,
                    'role' => $r->Untuk_Role,
                    'urutan' => (int) $r->Urutan,
                    'status' => $r->Flag_Aktif === 'Y' ? 'AKTIF' : 'NONAKTIF',
                    'maintenance' => $r->Flag_Maintenance === 'Y',
                    'dipakai' => DB::table('N_WEB_CAREERS_Page_Access')->where('Jenis_Page', $r->Jenis_Page)->count(),
                    'createdBy' => $r->Created_By,
                    'createdAt' => $r->Created_At,
                ])
                ->values();

            return ResponseHelper::success($rows, 'Data menu dimuat');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('Gagal memuat menu: ' . $e->getMessage());

            return ResponseHelper::error('Gagal memuat data menu', 500);
        }
    }

    private function rules(bool $baru): array
    {
        return [
            'jenisPage' => ($baru ? 'required' : 'nullable') . '|string|max:60|regex:/^[A-Za-z][A-Za-z0-9_]*$/',
            'nama' => 'required|string|max:150',
            'header' => 'nullable|string|max:100',
            'subHeader' => 'nullable|string|max:100',
            // Sub-grup di dalam header. Kosong = menu duduk langsung di bawah
            // headernya — itu keadaan yang sah, bukan isian yang terlupa.
            'grup' => 'nullable|string|max:60',
            'urutanGrup' => 'nullable|integer|min:0|max:999',
            'ikon' => 'nullable|string|max:80',
            'url' => 'nullable|string|max:200',
            'role' => 'required|in:ADMIN,KANDIDAT',
            'urutan' => 'nullable|integer|min:0|max:9999',
        ];
    }

    public function store(Request $request)
    {
        try {
            $data = $request->validate($this->rules(true));

            if (DB::table($this->tbl)->where('Jenis_Page', $data['jenisPage'])->exists()) {
                return ResponseHelper::error('Kunci halaman (Jenis Page) sudah dipakai menu lain.', 422);
            }

            $nama = session('career_auth.nama', 'ADMIN');
            DB::table($this->tbl)->insert([
                'Jenis_Page' => $data['jenisPage'],
                'Nama_Menu' => $data['nama'],
                'Nama_Header' => $data['header'] ?? null,
                'Sub_Header' => $data['subHeader'] ?? null,
                'Nama_Grup' => $data['grup'] ?? null,
                'Urutan_Grup' => $data['urutanGrup'] ?? null,
                'Icon_Menu' => $data['ikon'] ?: 'bi bi-dot',
                'Url_Menu' => $data['url'] ?? null,
                'Untuk_Role' => $data['role'],
                'Urutan' => $data['urutan'] ?? 0,
                'Flag_Aktif' => 'Y',
                'Flag_Maintenance' => 'T',
                'Created_At' => now(), 'Created_By' => $nama, 'Created_By_Id' => session('career_auth.id'),
                'Updated_At' => now(), 'Updated_By' => $nama, 'Updated_By_Id' => session('career_auth.id'),
            ]);

            AksesService::lupakanSemua();
            CareerShell::lupakanNav();
            Log::channel('web_career')->info("Master menu dibuat ({$data['jenisPage']}) oleh {$nama}");

            return ResponseHelper::success(null, 'Menu berhasil ditambahkan', 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ResponseHelper::error(collect($e->errors())->flatten()->first() ?? 'Data tidak valid', 422);
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('Gagal membuat menu: ' . $e->getMessage());

            return ResponseHelper::error('Gagal menyimpan data', 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $realId = Hashids::decode($id)[0] ?? null;
            $row = DB::table($this->tbl)->where('Id_Menu', $realId)->first();
            if (! $row) {
                return ResponseHelper::error('Data tidak ditemukan', 404);
            }

            $data = $request->validate($this->rules(false));
            $nama = session('career_auth.nama', 'ADMIN');

            DB::table($this->tbl)->where('Id_Menu', $realId)->update([
                'Nama_Menu' => $data['nama'],
                'Nama_Header' => $data['header'] ?? null,
                'Sub_Header' => $data['subHeader'] ?? null,
                'Nama_Grup' => $data['grup'] ?? null,
                'Urutan_Grup' => $data['urutanGrup'] ?? null,
                'Icon_Menu' => $data['ikon'] ?: $row->Icon_Menu,
                'Url_Menu' => $data['url'] ?? null,
                'Untuk_Role' => $data['role'],
                'Urutan' => $data['urutan'] ?? $row->Urutan,
                'Updated_At' => now(), 'Updated_By' => $nama, 'Updated_By_Id' => session('career_auth.id'),
            ]);

            AksesService::lupakanSemua();
            CareerShell::lupakanNav();

            return ResponseHelper::success(null, 'Menu diperbarui');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ResponseHelper::error(collect($e->errors())->flatten()->first() ?? 'Data tidak valid', 422);
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal update menu #{$id}: " . $e->getMessage());

            return ResponseHelper::error('Gagal memperbarui data', 500);
        }
    }

    public function toggle(Request $request, $id)
    {
        try {
            $realId = Hashids::decode($id)[0] ?? null;
            $aktif = $request->boolean('aktif');
            $ok = DB::table($this->tbl)->where('Id_Menu', $realId)->update([
                'Flag_Aktif' => $aktif ? 'Y' : 'N',
                'Updated_At' => now(), 'Updated_By' => session('career_auth.nama', 'ADMIN'), 'Updated_By_Id' => session('career_auth.id'),
            ]);
            if (! $ok) {
                return ResponseHelper::error('Data tidak ditemukan', 404);
            }

            AksesService::lupakanSemua();
            CareerShell::lupakanNav();

            return ResponseHelper::success(null, 'Status diperbarui');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal toggle menu #{$id}: " . $e->getMessage());

            return ResponseHelper::error('Gagal mengubah status', 500);
        }
    }

    /**
     * Nyalakan/matikan MODE PEMELIHARAAN sebuah menu.
     * Saat 'Y', middleware career.permission menutup seluruh route menu itu dan
     * menampilkan halaman pemeliharaan — in-shell untuk menu ADMIN, standalone
     * untuk menu KANDIDAT. SUPERADMIN tetap bisa masuk (jalan keluar).
     */
    public function toggleMaintenance(Request $request, $id)
    {
        try {
            $realId = Hashids::decode($id)[0] ?? null;
            $aktif = $request->boolean('maintenance');
            $row = DB::table($this->tbl)->where('Id_Menu', $realId)->first();
            if (! $row) {
                return ResponseHelper::error('Data tidak ditemukan', 404);
            }

            DB::table($this->tbl)->where('Id_Menu', $realId)->update([
                'Flag_Maintenance' => $aktif ? 'Y' : 'T',
                'Updated_At' => now(),
                'Updated_By' => session('career_auth.nama', 'ADMIN'),
                'Updated_By_Id' => session('career_auth.id'),
            ]);

            AksesService::lupakanMaintenance();
            Log::channel('web_career')->info(
                "Menu {$row->Jenis_Page} mode pemeliharaan " . ($aktif ? 'DINYALAKAN' : 'DIMATIKAN')
                . ' oleh ' . session('career_auth.nama', 'ADMIN')
            );

            return ResponseHelper::success(null, $aktif
                ? 'Mode pemeliharaan dinyalakan — halaman ini ditutup untuk pengguna.'
                : 'Mode pemeliharaan dimatikan — halaman kembali normal.');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal toggle maintenance #{$id}: " . $e->getMessage());

            return ResponseHelper::error('Gagal mengubah mode pemeliharaan', 500);
        }
    }

    public function destroy($id)
    {
        try {
            $realId = Hashids::decode($id)[0] ?? null;
            $row = DB::table($this->tbl)->where('Id_Menu', $realId)->first();
            if (! $row) {
                return ResponseHelper::error('Data tidak ditemukan', 404);
            }

            // Menu yang masih dipegang user tidak boleh dihapus — hak aksesnya
            // akan jadi yatim dan pengguna kehilangan menu tanpa jejak.
            $dipakai = DB::table('N_WEB_CAREERS_Page_Access')->where('Jenis_Page', $row->Jenis_Page)->count();
            if ($dipakai > 0) {
                return ResponseHelper::error("Menu ini masih dipakai {$dipakai} pengguna. Cabut aksesnya dulu, atau cukup nonaktifkan.", 422);
            }

            DB::transaction(function () use ($realId, $row) {
                DB::table('N_WEB_CAREERS_Klasifikasi_Menu')->where('Jenis_Page', $row->Jenis_Page)->delete();
                DB::table($this->tbl)->where('Id_Menu', $realId)->delete();
            });

            AksesService::lupakanSemua();
            CareerShell::lupakanNav();

            return ResponseHelper::success(null, 'Menu dihapus');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal hapus menu #{$id}: " . $e->getMessage());

            return ResponseHelper::error('Gagal menghapus data', 500);
        }
    }
}
