<?php

namespace App\Http\Controllers\Career\MasterSumber;

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
 * WEB CAREER — MASTER SUMBER KANDIDAT (SPA + WEB, tanpa API).
 * - index()  : return Inertia (render halaman) — shell admin via CareerShell::props.
 * - list()   : data sumber (Query Builder) -> ResponseHelper (dihit axios saat mount).
 * - store/update/toggle/destroy : CRUD Query Builder -> ResponseHelper, try/catch, Log channel.
 * Semua penulisan DB::table('N_WEB_CAREERS_Master_Sumber_Kandidat') langsung (tanpa helper/konstanta).
 */
class MasterSumberController extends Controller
{
    /** Halaman (Inertia). Data TIDAK dikirim lewat props — halaman fetch sendiri ke API list(). */
    public function index()
    {
        return Inertia::render('Career/admin/master-sumber/masterSumber', CareerShell::props('/master-sumber', 'Master Sumber Kandidat'));
    }

    /** Data list Master Sumber (Query Builder + join nama pembuat) -> ResponseHelper. */
    public function list()
    {
        try {
            $rows = DB::table('N_WEB_CAREERS_Master_Sumber_Kandidat as s')
                ->leftJoin('N_WEB_CAREERS_Users as u', 'u.Id_Users', '=', 's.Created_By_Id')
                ->orderBy('s.Id_Master_Sumber')
                ->select('s.*', 'u.Nama as Pembuat')
                ->get()
                ->map(function ($r) {
                    return [
                        'id' => Hashids::encode($r->Id_Master_Sumber), // id di-hash (raw id tidak diekspos)
                        'kode' => $r->Kode,
                        'nama' => $r->Nama,
                        'ikon' => $r->Ikon,
                        'warna' => $r->Warna,
                        'deskripsi' => $r->Deskripsi,
                        'sifat' => $r->Flag_Sistem === 'Y' ? 'SISTEM' : 'KUSTOM',
                        'status' => $r->Flag_Aktif === 'Y' ? 'AKTIF' : 'NONAKTIF',
                        'createdBy' => $r->Pembuat ?: $r->Created_By,
                        'createdAt' => $r->Created_At,
                    ];
                })
                ->values();

            return ResponseHelper::success($rows, 'Data sumber dimuat');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('Gagal memuat sumber: ' . $e->getMessage());
            return ResponseHelper::error('Gagal memuat data sumber', 500);
        }
    }

    /** Tambah sumber. */
    public function store(Request $request)
    {
        try {
            $data = $request->validate([
                'nama' => 'required|string|max:80',
                'warna' => 'nullable|string|max:20',
                'deskripsi' => 'nullable|string|max:1000',
            ]);

            $userId = session('career_auth.id');
            $userName = session('career_auth.nama', 'ADMIN');
            $now = now();

            // Kode = NAMA di-uppercase (pola master: UMUM, KAMPUS, dst) — tanpa prefix.
            // Kode dibuat memakai lebar kolom penuh; sufiks _N hanya bila bentrok.
            $kode = KodeUnik::buat('N_WEB_CAREERS_Master_Sumber_Kandidat', 'Kode', $data['nama'], 20, 'SUMBER');
            DB::table('N_WEB_CAREERS_Master_Sumber_Kandidat')->insert([
                'Kode' => $kode,
                'Nama' => $data['nama'],
                'Ikon' => 'bi-broadcast',
                'Warna' => $data['warna'] ?? '#059669',
                'Deskripsi' => $data['deskripsi'] ?? null,
                'Flag_Sistem' => 'N',
                'Flag_Aktif' => 'Y',
                'Created_At' => $now,
                'Created_By' => $userName,
                'Created_By_Id' => $userId,
                'Updated_At' => $now,
                'Updated_By' => $userName,
                'Updated_By_Id' => $userId,
            ]);

            Log::channel('web_career')->info("Master sumber dibuat ({$kode}) oleh {$userName}");
            return ResponseHelper::success(null, 'Sumber berhasil ditambahkan', 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ResponseHelper::error(collect($e->errors())->flatten()->first() ?? 'Data tidak valid', 422);
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('Gagal membuat sumber: ' . $e->getMessage());
            return ResponseHelper::error('Gagal menyimpan data', 500);
        }
    }

    /** Ubah sumber. */
    public function update(Request $request, $id)
    {
        try {
            $realId = Hashids::decode($id)[0] ?? null;
            $row = DB::table('N_WEB_CAREERS_Master_Sumber_Kandidat')->where('Id_Master_Sumber', $realId)->first();
            if (!$row) {
                return ResponseHelper::error('Data tidak ditemukan', 404);
            }

            $data = $request->validate([
                'nama' => 'required|string|max:80',
                'warna' => 'nullable|string|max:20',
                'deskripsi' => 'nullable|string|max:1000',
            ]);

            DB::table('N_WEB_CAREERS_Master_Sumber_Kandidat')
                ->where('Id_Master_Sumber', $realId)
                ->update([
                    'Nama' => $data['nama'],
                    'Warna' => $data['warna'] ?? $row->Warna,
                    'Deskripsi' => $data['deskripsi'] ?? null,
                    'Updated_At' => now(),
                    'Updated_By' => session('career_auth.nama', 'ADMIN'),
                    'Updated_By_Id' => session('career_auth.id'),
                ]);

            Log::channel('web_career')->info("Master sumber #{$id} diperbarui");

            return ResponseHelper::success(null, 'Sumber diperbarui');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ResponseHelper::error(collect($e->errors())->flatten()->first() ?? 'Data tidak valid', 422);
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal update sumber #{$id}: " . $e->getMessage());

            return ResponseHelper::error('Gagal memperbarui data', 500);
        }
    }

    /** Aktif/Nonaktif sumber. */
    public function toggle(Request $request, $id)
    {
        try {
            $realId = Hashids::decode($id)[0] ?? null;
            $aktif = $request->boolean('aktif');
            $terpengaruh = DB::table('N_WEB_CAREERS_Master_Sumber_Kandidat')
                ->where('Id_Master_Sumber', $realId)
                ->update([
                    'Flag_Aktif' => $aktif ? 'Y' : 'N',
                    'Updated_At' => now(),
                    'Updated_By' => session('career_auth.nama', 'ADMIN'),
                    'Updated_By_Id' => session('career_auth.id'),
                ]);

            if (!$terpengaruh) {
                return ResponseHelper::error('Data tidak ditemukan', 404);
            }

            Log::channel('web_career')->info("Master sumber #{$id} status " . ($aktif ? 'AKTIF' : 'NONAKTIF'));

            return ResponseHelper::success(null, 'Status diperbarui');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal toggle sumber #{$id}: " . $e->getMessage());

            return ResponseHelper::error('Gagal mengubah status', 500);
        }
    }

    /** Hapus sumber. */
    public function destroy($id)
    {
        try {
            $realId = Hashids::decode($id)[0] ?? null;
            $terpengaruh = DB::table('N_WEB_CAREERS_Master_Sumber_Kandidat')->where('Id_Master_Sumber', $realId)->delete();
            if (!$terpengaruh) {
                return ResponseHelper::error('Data tidak ditemukan', 404);
            }

            Log::channel('web_career')->info("Master sumber #{$id} dihapus");

            return ResponseHelper::success(null, 'Sumber dihapus');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal hapus sumber #{$id}: " . $e->getMessage());

            return ResponseHelper::error('Gagal menghapus data', 500);
        }
    }
}
