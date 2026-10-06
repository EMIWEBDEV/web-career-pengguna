<?php

namespace App\Http\Controllers\Career\MasterTipe;

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
 * WEB CAREER — MASTER TIPE TAHAP (SPA + WEB, tanpa API).
 * - index()  : return Inertia (render halaman) — shell admin via CareerShell::props.
 * - list()   : data tipe tahap (Query Builder) -> ResponseHelper (dihit axios saat mount).
 * - store/update/toggle/destroy : CRUD Query Builder -> ResponseHelper, try/catch, Log channel.
 * Semua penulisan DB::table('N_WEB_CAREERS_Master_Tipe_Tahap') langsung (tanpa helper/konstanta).
 */
class MasterTipeController extends Controller
{
    /** Halaman (Inertia). Data TIDAK dikirim lewat props — halaman fetch sendiri ke API list(). */
    public function index()
    {
        return Inertia::render('Career/admin/master-tipe/masterTipe', CareerShell::props('/master-tipe', 'Master Tipe Tahap'));
    }

    /** Data list Master Tipe Tahap (Query Builder + join nama pembuat) -> ResponseHelper. */
    public function list()
    {
        try {
            $rows = DB::table('N_WEB_CAREERS_Master_Tipe_Tahap as t')
                ->leftJoin('N_WEB_CAREERS_Users as u', 'u.Id_Users', '=', 't.Created_By_Id')
                ->orderBy('t.Id_Master_Tipe_Tahap')
                ->select('t.*', 'u.Nama as Pembuat')
                ->get()
                ->map(function ($r) {
                    return [
                        'id' => Hashids::encode($r->Id_Master_Tipe_Tahap), // id di-hash (raw id tidak diekspos)
                        'kode' => $r->Kode,
                        'nama' => $r->Nama,
                        'ikon' => $r->Ikon,
                        'perilaku' => $r->Perilaku_Kode,
                        'deskripsi' => $r->Deskripsi,
                        'sifat' => $r->Flag_Sistem === 'Y' ? 'SISTEM' : 'KUSTOM',
                        'status' => $r->Flag_Aktif === 'Y' ? 'AKTIF' : 'NONAKTIF',
                        'createdBy' => $r->Pembuat ?: $r->Created_By,
                        'createdAt' => $r->Created_At,
                    ];
                })
                ->values();

            return ResponseHelper::success($rows, 'Data tipe tahap dimuat');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('Gagal memuat tipe tahap: ' . $e->getMessage());
            return ResponseHelper::error('Gagal memuat data tipe tahap', 500);
        }
    }

    /** Tambah tipe tahap. */
    public function store(Request $request)
    {
        try {
            $data = $request->validate([
                'nama' => 'required|string|max:80',
                'perilaku' => 'required|string|max:20',
                'ikon' => 'nullable|string|max:50',
                'deskripsi' => 'nullable|string|max:1000',
            ]);

            $userId = session('career_auth.id');
            $userName = session('career_auth.nama', 'ADMIN');
            $now = now();

            // Kode = NAMA di-uppercase (pola master siklus) — tanpa prefix, dijamin unik.
            // Kode dibuat memakai lebar kolom penuh; sufiks _N hanya bila bentrok.
            $kode = KodeUnik::buat('N_WEB_CAREERS_Master_Tipe_Tahap', 'Kode', $data['nama'], 30, 'TIPE');
            DB::table('N_WEB_CAREERS_Master_Tipe_Tahap')->insert([
                'Kode' => $kode,
                'Nama' => $data['nama'],
                'Ikon' => $data['ikon'] ?: 'bi-diagram-2',
                'Perilaku_Kode' => $data['perilaku'],
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

            Log::channel('web_career')->info("Master tipe tahap dibuat ({$kode}) oleh {$userName}");
            return ResponseHelper::success(null, 'Tipe tahap berhasil ditambahkan', 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ResponseHelper::error(collect($e->errors())->flatten()->first() ?? 'Data tidak valid', 422);
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('Gagal membuat tipe tahap: ' . $e->getMessage());
            return ResponseHelper::error('Gagal menyimpan data', 500);
        }
    }

    /** Ubah tipe tahap. */
    public function update(Request $request, $id)
    {
        try {
            $realId = Hashids::decode($id)[0] ?? null;
            $row = DB::table('N_WEB_CAREERS_Master_Tipe_Tahap')->where('Id_Master_Tipe_Tahap', $realId)->first();
            if (!$row) {
                return ResponseHelper::error('Data tidak ditemukan', 404);
            }

            $data = $request->validate([
                'nama' => 'required|string|max:80',
                'perilaku' => 'required|string|max:20',
                'ikon' => 'nullable|string|max:50',
                'deskripsi' => 'nullable|string|max:1000',
            ]);

            DB::table('N_WEB_CAREERS_Master_Tipe_Tahap')
                ->where('Id_Master_Tipe_Tahap', $realId)
                ->update([
                    'Nama' => $data['nama'],
                    'Perilaku_Kode' => $data['perilaku'],
                    'Ikon' => $data['ikon'] ?: $row->Ikon,
                    'Deskripsi' => $data['deskripsi'] ?? null,
                    'Updated_At' => now(),
                    'Updated_By' => session('career_auth.nama', 'ADMIN'),
                    'Updated_By_Id' => session('career_auth.id'),
                ]);

            Log::channel('web_career')->info("Master tipe tahap #{$id} diperbarui");

            return ResponseHelper::success(null, 'Tipe tahap diperbarui');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ResponseHelper::error(collect($e->errors())->flatten()->first() ?? 'Data tidak valid', 422);
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal update tipe tahap #{$id}: " . $e->getMessage());

            return ResponseHelper::error('Gagal memperbarui data', 500);
        }
    }

    /** Aktif/Nonaktif tipe tahap. */
    public function toggle(Request $request, $id)
    {
        try {
            $realId = Hashids::decode($id)[0] ?? null;
            $aktif = $request->boolean('aktif');
            $terpengaruh = DB::table('N_WEB_CAREERS_Master_Tipe_Tahap')
                ->where('Id_Master_Tipe_Tahap', $realId)
                ->update([
                    'Flag_Aktif' => $aktif ? 'Y' : 'N',
                    'Updated_At' => now(),
                    'Updated_By' => session('career_auth.nama', 'ADMIN'),
                    'Updated_By_Id' => session('career_auth.id'),
                ]);

            if (!$terpengaruh) {
                return ResponseHelper::error('Data tidak ditemukan', 404);
            }

            Log::channel('web_career')->info("Master tipe tahap #{$id} status " . ($aktif ? 'AKTIF' : 'NONAKTIF'));

            return ResponseHelper::success(null, 'Status diperbarui');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal toggle tipe tahap #{$id}: " . $e->getMessage());

            return ResponseHelper::error('Gagal mengubah status', 500);
        }
    }

    /** Hapus tipe tahap. */
    public function destroy($id)
    {
        try {
            $realId = Hashids::decode($id)[0] ?? null;
            $terpengaruh = DB::table('N_WEB_CAREERS_Master_Tipe_Tahap')->where('Id_Master_Tipe_Tahap', $realId)->delete();
            if (!$terpengaruh) {
                return ResponseHelper::error('Data tidak ditemukan', 404);
            }

            Log::channel('web_career')->info("Master tipe tahap #{$id} dihapus");

            return ResponseHelper::success(null, 'Tipe tahap dihapus');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal hapus tipe tahap #{$id}: " . $e->getMessage());

            return ResponseHelper::error('Gagal menghapus data', 500);
        }
    }
}
