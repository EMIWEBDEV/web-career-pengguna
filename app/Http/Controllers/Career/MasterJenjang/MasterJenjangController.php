<?php

namespace App\Http\Controllers\Career\MasterJenjang;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Support\Career\KodeUnik;
use App\Support\CareerShell;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Vinkla\Hashids\Facades\Hashids;

/**
 * WEB CAREER — MASTER JENJANG PENDIDIKAN (SPA + WEB, pola Master Kampus).
 * Jenjang = tingkat pendidikan (SD, SMP, SMA, SMK, D3, D4, S1, S2, S3, …) yang
 * dipakai kandidat di formulir & untuk cascade Jenjang → Jenis Institusi → Kampus.
 * Tabel: N_WEB_CAREERS_Master_Jenjang (PK Id_Master_Jenjang).
 */
class MasterJenjangController extends Controller
{
    private const TABEL = 'N_WEB_CAREERS_Master_Jenjang';

    public function index()
    {
        return Inertia::render('Career/admin/master-jenjang/masterJenjang', CareerShell::props('/master-jenjang', 'Master Jenjang Pendidikan'));
    }

    public function list()
    {
        try {
            $rows = DB::table(self::TABEL . ' as j')
                ->leftJoin('N_WEB_CAREERS_Users as u', 'u.Id_Users', '=', 'j.Created_By_Id')
                ->orderBy('j.Urutan')->orderBy('j.Id_Master_Jenjang')
                ->select('j.*', 'u.Nama as Pembuat')
                ->get()
                ->map(fn ($r) => [
                    'id' => Hashids::encode($r->Id_Master_Jenjang),
                    'kode' => $r->Kode,
                    'nama' => $r->Nama,
                    'urutan' => (int) $r->Urutan,
                    'status' => $r->Flag_Aktif === 'Y' ? 'AKTIF' : 'NONAKTIF',
                    'createdBy' => $r->Pembuat ?: $r->Created_By,
                    'createdAt' => $r->Created_At,
                ])->values();

            return ResponseHelper::success($rows, 'Data jenjang dimuat');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('Gagal memuat jenjang: ' . $e->getMessage());
            return ResponseHelper::error('Gagal memuat data jenjang', 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $data = $request->validate([
                'nama' => 'required|string|max:100',
                'kode' => 'nullable|string|max:20',
                'urutan' => 'nullable|integer|min:0',
            ]);
            $userId = session('career_auth.id');
            $userName = session('career_auth.nama', 'ADMIN');
            $now = now();
            $sumber = !empty($data['kode']) ? $data['kode'] : $data['nama'];
            $kode = KodeUnik::buat(self::TABEL, 'Kode', $sumber, 20, 'JJG');

            DB::table(self::TABEL)->insert([
                'Kode' => $kode,
                'Nama' => $data['nama'],
                'Urutan' => $data['urutan'] ?? 0,
                'Flag_Aktif' => 'Y',
                'Created_At' => $now, 'Created_By' => $userName, 'Created_By_Id' => $userId,
                'Updated_At' => $now, 'Updated_By' => $userName, 'Updated_By_Id' => $userId,
            ]);
            Log::channel('web_career')->info("Master jenjang dibuat ({$kode}) oleh {$userName}");
            return ResponseHelper::success(null, 'Jenjang berhasil ditambahkan', 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ResponseHelper::error(collect($e->errors())->flatten()->first() ?? 'Data tidak valid', 422);
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('Gagal membuat jenjang: ' . $e->getMessage());
            return ResponseHelper::error('Gagal menyimpan data', 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $realId = Hashids::decode($id)[0] ?? null;
            $row = DB::table(self::TABEL)->where('Id_Master_Jenjang', $realId)->first();
            if (!$row) {
                return ResponseHelper::error('Data tidak ditemukan', 404);
            }
            $data = $request->validate([
                'nama' => 'required|string|max:100',
                'urutan' => 'nullable|integer|min:0',
            ]);
            DB::table(self::TABEL)->where('Id_Master_Jenjang', $realId)->update([
                'Nama' => $data['nama'],
                'Urutan' => $data['urutan'] ?? $row->Urutan,
                'Updated_At' => now(), 'Updated_By' => session('career_auth.nama', 'ADMIN'), 'Updated_By_Id' => session('career_auth.id'),
            ]);
            return ResponseHelper::success(null, 'Jenjang diperbarui');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ResponseHelper::error(collect($e->errors())->flatten()->first() ?? 'Data tidak valid', 422);
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal update jenjang #{$id}: " . $e->getMessage());
            return ResponseHelper::error('Gagal memperbarui data', 500);
        }
    }

    public function toggle(Request $request, $id)
    {
        try {
            $realId = Hashids::decode($id)[0] ?? null;
            $aktif = $request->boolean('aktif');
            $ok = DB::table(self::TABEL)->where('Id_Master_Jenjang', $realId)->update([
                'Flag_Aktif' => $aktif ? 'Y' : 'N',
                'Updated_At' => now(), 'Updated_By' => session('career_auth.nama', 'ADMIN'), 'Updated_By_Id' => session('career_auth.id'),
            ]);
            if (!$ok) {
                return ResponseHelper::error('Data tidak ditemukan', 404);
            }
            return ResponseHelper::success(null, 'Status diperbarui');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal toggle jenjang #{$id}: " . $e->getMessage());
            return ResponseHelper::error('Gagal mengubah status', 500);
        }
    }

    public function destroy($id)
    {
        try {
            $realId = Hashids::decode($id)[0] ?? null;
            $ok = DB::table(self::TABEL)->where('Id_Master_Jenjang', $realId)->delete();
            if (!$ok) {
                return ResponseHelper::error('Data tidak ditemukan', 404);
            }
            return ResponseHelper::success(null, 'Jenjang dihapus');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal hapus jenjang #{$id}: " . $e->getMessage());
            return ResponseHelper::error('Gagal menghapus data', 500);
        }
    }
}
