<?php

namespace App\Http\Controllers\Career\MasterTalent;

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
 * WEB CAREER — MASTER TALENT ACQUISITION (SPA + WEB, tanpa API).
 * - index()  : return Inertia (render halaman) — shell admin via CareerShell.
 * - list()   : data talent (Query Builder) -> ResponseHelper (dihit axios saat mount).
 * - store/update/toggle/destroy : CRUD Query Builder -> ResponseHelper, try/catch, Log channel.
 * Semua penulisan DB::table('N_WEB_CAREERS_Master_Talent_Acquisition') langsung (tanpa helper/konstanta).
 */
class MasterTalentController extends Controller
{
    /** Halaman (Inertia). Data TIDAK dikirim lewat props — halaman fetch sendiri ke API list(). */
    public function index()
    {
        return Inertia::render('Career/admin/master-talent/masterTalent', CareerShell::props('/master-talent', 'Master Talent Acquisition'));
    }

    /** Data list Master Talent Acquisition (Query Builder + join nama pembuat) -> ResponseHelper. */
    public function list()
    {
        try {
            $rows = DB::table('N_WEB_CAREERS_Master_Talent_Acquisition as t')
                ->leftJoin('N_WEB_CAREERS_Users as u', 'u.Id_Users', '=', 't.Created_By_Id')
                ->orderBy('t.Id_Master_Talent_Acquisition')
                ->select('t.*', 'u.Nama as Pembuat')
                ->get()
                ->map(function ($r) {
                    return [
                        'id' => Hashids::encode($r->Id_Master_Talent_Acquisition), // id di-hash (raw id tidak diekspos)
                        'kode' => $r->Kode,
                        'nama' => $r->Nama,
                        'deskripsi' => $r->Deskripsi,
                        'status' => $r->Flag_Aktif === 'Y' ? 'AKTIF' : 'NONAKTIF',
                        'createdBy' => $r->Pembuat ?: $r->Created_By,
                        'createdAt' => $r->Created_At,
                    ];
                })
                ->values();

            return ResponseHelper::success($rows, 'Data talent dimuat');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('Gagal memuat talent: ' . $e->getMessage());
            return ResponseHelper::error('Gagal memuat data talent', 500);
        }
    }

    /** Tambah kategori talent. */
    public function store(Request $request)
    {
        try {
            $data = $request->validate([
                'nama' => 'required|string|max:100',
                'deskripsi' => 'nullable|string|max:500',
            ]);

            $userId = session('career_auth.id');
            $userName = session('career_auth.nama', 'ADMIN');
            $now = now();

            // Kode = NAMA di-uppercase (pola master: REKRUTMEN, MANAGEMENT_TRAINEE, dst) — tanpa prefix.
            // Kode dibuat memakai lebar kolom penuh; sufiks _N hanya bila bentrok.
            $kode = KodeUnik::buat('N_WEB_CAREERS_Master_Talent_Acquisition', 'Kode', $data['nama'], 30, 'KATEGORI');
            DB::table('N_WEB_CAREERS_Master_Talent_Acquisition')->insert([
                'Kode' => $kode,
                'Nama' => $data['nama'],
                'Deskripsi' => $data['deskripsi'] ?? null,
                'Flag_Aktif' => 'Y',
                'Created_At' => $now,
                'Created_By' => $userName,
                'Created_By_Id' => $userId,
                'Updated_At' => $now,
                'Updated_By' => $userName,
                'Updated_By_Id' => $userId,
            ]);

            Log::channel('web_career')->info("Master talent dibuat ({$kode}) oleh {$userName}");
            return ResponseHelper::success(null, 'Kategori berhasil ditambahkan', 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ResponseHelper::error(collect($e->errors())->flatten()->first() ?? 'Data tidak valid', 422);
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('Gagal membuat talent: ' . $e->getMessage());
            return ResponseHelper::error('Gagal menyimpan data', 500);
        }
    }

    /** Ubah kategori talent. */
    public function update(Request $request, $id)
    {
        try {
            $realId = Hashids::decode($id)[0] ?? null;
            $row = DB::table('N_WEB_CAREERS_Master_Talent_Acquisition')->where('Id_Master_Talent_Acquisition', $realId)->first();
            if (!$row) {
                return ResponseHelper::error('Data tidak ditemukan', 404);
            }

            $data = $request->validate([
                'nama' => 'required|string|max:100',
                'deskripsi' => 'nullable|string|max:500',
            ]);

            DB::table('N_WEB_CAREERS_Master_Talent_Acquisition')
                ->where('Id_Master_Talent_Acquisition', $realId)
                ->update([
                    'Nama' => $data['nama'],
                    'Deskripsi' => $data['deskripsi'] ?? null,
                    'Updated_At' => now(),
                    'Updated_By' => session('career_auth.nama', 'ADMIN'),
                    'Updated_By_Id' => session('career_auth.id'),
                ]);

            Log::channel('web_career')->info("Master talent #{$id} diperbarui");

            return ResponseHelper::success(null, 'Kategori diperbarui');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ResponseHelper::error(collect($e->errors())->flatten()->first() ?? 'Data tidak valid', 422);
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal update talent #{$id}: " . $e->getMessage());

            return ResponseHelper::error('Gagal memperbarui data', 500);
        }
    }

    /** Aktif/Nonaktif kategori talent. */
    public function toggle(Request $request, $id)
    {
        try {
            $realId = Hashids::decode($id)[0] ?? null;
            $aktif = $request->boolean('aktif');
            $terpengaruh = DB::table('N_WEB_CAREERS_Master_Talent_Acquisition')
                ->where('Id_Master_Talent_Acquisition', $realId)
                ->update([
                    'Flag_Aktif' => $aktif ? 'Y' : 'N',
                    'Updated_At' => now(),
                    'Updated_By' => session('career_auth.nama', 'ADMIN'),
                    'Updated_By_Id' => session('career_auth.id'),
                ]);

            if (!$terpengaruh) {
                return ResponseHelper::error('Data tidak ditemukan', 404);
            }

            Log::channel('web_career')->info("Master talent #{$id} status " . ($aktif ? 'AKTIF' : 'NONAKTIF'));

            return ResponseHelper::success(null, 'Status diperbarui');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal toggle talent #{$id}: " . $e->getMessage());

            return ResponseHelper::error('Gagal mengubah status', 500);
        }
    }

    /** Hapus kategori talent. */
    public function destroy($id)
    {
        try {
            $realId = Hashids::decode($id)[0] ?? null;
            $terpengaruh = DB::table('N_WEB_CAREERS_Master_Talent_Acquisition')->where('Id_Master_Talent_Acquisition', $realId)->delete();
            if (!$terpengaruh) {
                return ResponseHelper::error('Data tidak ditemukan', 404);
            }

            Log::channel('web_career')->info("Master talent #{$id} dihapus");

            return ResponseHelper::success(null, 'Kategori dihapus');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal hapus talent #{$id}: " . $e->getMessage());

            return ResponseHelper::error('Gagal menghapus data', 500);
        }
    }
}
