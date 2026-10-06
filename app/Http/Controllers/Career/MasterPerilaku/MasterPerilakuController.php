<?php

namespace App\Http\Controllers\Career\MasterPerilaku;

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
 * WEB CAREER — MASTER PERILAKU (SPA + WEB, tanpa API).
 * - index()  : return Inertia (render halaman) — shell admin via CareerShell.
 * - list()   : data perilaku (Query Builder) -> ResponseHelper (dihit axios saat mount).
 * - store/update/toggle/destroy : CRUD Query Builder -> ResponseHelper, try/catch, Log channel.
 * Semua penulisan DB::table('N_WEB_CAREERS_Master_Perilaku') langsung (tanpa helper/konstanta).
 */
class MasterPerilakuController extends Controller
{
    /** Halaman (Inertia). Data TIDAK dikirim lewat props — halaman fetch sendiri ke API list(). */
    public function index()
    {
        return Inertia::render('Career/admin/master-perilaku/masterPerilaku', CareerShell::props('/master-perilaku', 'Master Perilaku'));
    }

    /** Data list Master Perilaku (Query Builder + join nama pembuat) -> ResponseHelper. */
    public function list()
    {
        try {
            $rows = DB::table('N_WEB_CAREERS_Master_Perilaku as p')
                ->leftJoin('N_WEB_CAREERS_Users as u', 'u.Id_Users', '=', 'p.Created_By_Id')
                ->orderBy('p.Id_Master_Perilaku')
                ->select('p.*', 'u.Nama as Pembuat')
                ->get()
                ->map(function ($r) {
                    return [
                        'id' => Hashids::encode($r->Id_Master_Perilaku), // id di-hash (raw id tidak diekspos)
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

            return ResponseHelper::success($rows, 'Data perilaku dimuat');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('Gagal memuat perilaku: ' . $e->getMessage());
            return ResponseHelper::error('Gagal memuat data perilaku', 500);
        }
    }

    /** Tambah perilaku. */
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

            // Kode = NAMA di-uppercase (pola master perilaku) — tanpa prefix.
            // Kode dibuat memakai lebar kolom penuh; sufiks _N hanya bila bentrok.
            $kode = KodeUnik::buat('N_WEB_CAREERS_Master_Perilaku', 'Kode', $data['nama'], 20, 'PERILAKU');
            DB::table('N_WEB_CAREERS_Master_Perilaku')->insert([
                'Kode' => $kode,
                'Nama' => $data['nama'],
                'Ikon' => 'bi-lightning-charge',
                'Warna' => $data['warna'] ?? '#4f46e5',
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

            Log::channel('web_career')->info("Master perilaku dibuat ({$kode}) oleh {$userName}");
            return ResponseHelper::success(null, 'Perilaku berhasil ditambahkan', 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ResponseHelper::error(collect($e->errors())->flatten()->first() ?? 'Data tidak valid', 422);
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('Gagal membuat perilaku: ' . $e->getMessage());
            return ResponseHelper::error('Gagal menyimpan data', 500);
        }
    }

    /** Ubah perilaku. */
    public function update(Request $request, $id)
    {
        try {
            $realId = Hashids::decode($id)[0] ?? null;
            $row = DB::table('N_WEB_CAREERS_Master_Perilaku')->where('Id_Master_Perilaku', $realId)->first();
            if (!$row) {
                return ResponseHelper::error('Data tidak ditemukan', 404);
            }

            $data = $request->validate([
                'nama' => 'required|string|max:80',
                'warna' => 'nullable|string|max:20',
                'deskripsi' => 'nullable|string|max:1000',
            ]);

            DB::table('N_WEB_CAREERS_Master_Perilaku')
                ->where('Id_Master_Perilaku', $realId)
                ->update([
                    'Nama' => $data['nama'],
                    'Warna' => $data['warna'] ?? $row->Warna,
                    'Deskripsi' => $data['deskripsi'] ?? null,
                    'Updated_At' => now(),
                    'Updated_By' => session('career_auth.nama', 'ADMIN'),
                    'Updated_By_Id' => session('career_auth.id'),
                ]);

            Log::channel('web_career')->info("Master perilaku #{$id} diperbarui");

            return ResponseHelper::success(null, 'Perilaku diperbarui');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ResponseHelper::error(collect($e->errors())->flatten()->first() ?? 'Data tidak valid', 422);
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal update perilaku #{$id}: " . $e->getMessage());

            return ResponseHelper::error('Gagal memperbarui data', 500);
        }
    }

    /** Aktif/Nonaktif perilaku. */
    public function toggle(Request $request, $id)
    {
        try {
            $realId = Hashids::decode($id)[0] ?? null;
            $aktif = $request->boolean('aktif');
            $terpengaruh = DB::table('N_WEB_CAREERS_Master_Perilaku')
                ->where('Id_Master_Perilaku', $realId)
                ->update([
                    'Flag_Aktif' => $aktif ? 'Y' : 'N',
                    'Updated_At' => now(),
                    'Updated_By' => session('career_auth.nama', 'ADMIN'),
                    'Updated_By_Id' => session('career_auth.id'),
                ]);

            if (!$terpengaruh) {
                return ResponseHelper::error('Data tidak ditemukan', 404);
            }

            Log::channel('web_career')->info("Master perilaku #{$id} status " . ($aktif ? 'AKTIF' : 'NONAKTIF'));

            return ResponseHelper::success(null, 'Status diperbarui');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal toggle perilaku #{$id}: " . $e->getMessage());

            return ResponseHelper::error('Gagal mengubah status', 500);
        }
    }

    /** Hapus perilaku. */
    public function destroy($id)
    {
        try {
            $realId = Hashids::decode($id)[0] ?? null;
            $terpengaruh = DB::table('N_WEB_CAREERS_Master_Perilaku')->where('Id_Master_Perilaku', $realId)->delete();
            if (!$terpengaruh) {
                return ResponseHelper::error('Data tidak ditemukan', 404);
            }

            Log::channel('web_career')->info("Master perilaku #{$id} dihapus");

            return ResponseHelper::success(null, 'Perilaku dihapus');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal hapus perilaku #{$id}: " . $e->getMessage());

            return ResponseHelper::error('Gagal menghapus data', 500);
        }
    }
}
