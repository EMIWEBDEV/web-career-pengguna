<?php

namespace App\Http\Controllers\Career\MasterKategori;

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
 * WEB CAREER — MASTER KATEGORI (SPA + WEB, tanpa API — pola Master Siklus).
 * - index()  : return Inertia (render halaman) — shell admin via CareerShell::props.
 * - list()   : data kategori (Query Builder) -> ResponseHelper (dihit axios saat mount).
 * - store/update/toggle/destroy : CRUD Query Builder -> ResponseHelper, try/catch, Log channel.
 * Semua penulisan DB::table('N_WEB_CAREERS_Master_Kategori') langsung (tanpa helper/konstanta).
 */
class MasterKategoriController extends Controller
{
    /** Halaman (Inertia). Data TIDAK dikirim lewat props — halaman fetch sendiri ke list(). */
    public function index()
    {
        return Inertia::render('Career/admin/master-kategori/masterKategori', CareerShell::props('/master-kategori', 'Master Kategori'));
    }

    /** Data list Master Kategori (Query Builder + join nama pembuat) -> ResponseHelper. */
    public function list()
    {
        try {
            $rows = DB::table('N_WEB_CAREERS_Master_Kategori as k')
                ->leftJoin('N_WEB_CAREERS_Users as u', 'u.Id_Users', '=', 'k.Created_By_Id')
                ->orderBy('k.Id_Master_Kategori')
                ->select('k.*', 'u.Nama as Pembuat')
                ->get()
                ->map(function ($r) {
                    return [
                        'id' => Hashids::encode($r->Id_Master_Kategori), // id di-hash (raw id tidak diekspos)
                        'kode' => $r->Kode,
                        'nama' => $r->Nama,
                        'kategori' => $r->Kategori,
                        'warna' => $r->Warna,
                        'modeDefault' => $r->Mode_Default,
                        'alurDefault' => $r->Alur_Default_Kode,
                        'alurNama' => DB::table('N_WEB_CAREERS_Master_Alur')->where('Kode', $r->Alur_Default_Kode)->value('Nama'),
                        'sifat' => $r->Flag_Sistem === 'Y' ? 'SISTEM' : 'KUSTOM',
                        'status' => $r->Flag_Aktif === 'Y' ? 'AKTIF' : 'NONAKTIF',
                        'deskripsi' => $r->Deskripsi,
                        'createdBy' => $r->Pembuat ?: $r->Created_By,
                        'createdAt' => $r->Created_At,
                    ];
                })
                ->values();

            return ResponseHelper::success($rows, 'Data kategori dimuat');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('Gagal memuat kategori: ' . $e->getMessage());
            return ResponseHelper::error('Gagal memuat data kategori', 500);
        }
    }

    /** Tambah kategori. */
    public function store(Request $request)
    {
        try {
            $data = $request->validate([
                'nama' => 'required|string|max:100',
                'kategori' => 'required|string|max:20',
                'warna' => 'nullable|string|max:20',
                'modeDefault' => 'nullable|string|max:20',
                'alurDefault' => 'nullable|string|max:30', // selebar Master_Alur.Kode (Batch 13)
                'deskripsi' => 'nullable|string|max:500',
            ]);

            $userId = session('career_auth.id');
            $userName = session('career_auth.nama', 'ADMIN');
            $now = now();

            // Kode = NAMA di-uppercase, unik (pola master siklus) — tanpa prefix.
            // Kode dibuat memakai lebar kolom penuh; sufiks _N hanya bila bentrok.
            $kode = KodeUnik::buat('N_WEB_CAREERS_Master_Kategori', 'Kode', $data['nama'], 20, 'KATEGORI');
            DB::table('N_WEB_CAREERS_Master_Kategori')->insert([
                'Kode' => $kode,
                'Nama' => $data['nama'],
                'Kategori' => $data['kategori'],
                'Warna' => $data['warna'] ?? '#4f46e5',
                'Mode_Default' => $data['modeDefault'] ?? null,
                'Alur_Default_Kode' => $data['alurDefault'] ?? null,
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

            Log::channel('web_career')->info("Master kategori dibuat ({$kode}) oleh {$userName}");
            return ResponseHelper::success(null, 'Kategori berhasil ditambahkan', 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ResponseHelper::error(collect($e->errors())->flatten()->first() ?? 'Data tidak valid', 422);
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('Gagal membuat kategori: ' . $e->getMessage());
            return ResponseHelper::error('Gagal menyimpan data', 500);
        }
    }

    /** Ubah kategori. */
    public function update(Request $request, $id)
    {
        try {
            $realId = Hashids::decode($id)[0] ?? null;
            $row = DB::table('N_WEB_CAREERS_Master_Kategori')->where('Id_Master_Kategori', $realId)->first();
            if (!$row) {
                return ResponseHelper::error('Data tidak ditemukan', 404);
            }

            $data = $request->validate([
                'nama' => 'required|string|max:100',
                'kategori' => 'required|string|max:20',
                'warna' => 'nullable|string|max:20',
                'modeDefault' => 'nullable|string|max:20',
                'alurDefault' => 'nullable|string|max:30', // selebar Master_Alur.Kode (Batch 13)
                'deskripsi' => 'nullable|string|max:500',
            ]);

            DB::table('N_WEB_CAREERS_Master_Kategori')
                ->where('Id_Master_Kategori', $realId)
                ->update([
                    'Nama' => $data['nama'],
                    'Kategori' => $data['kategori'],
                    'Warna' => $data['warna'] ?? $row->Warna,
                    'Mode_Default' => $data['modeDefault'] ?? null,
                    'Alur_Default_Kode' => $data['alurDefault'] ?? null,
                    'Deskripsi' => $data['deskripsi'] ?? null,
                    'Updated_At' => now(),
                    'Updated_By' => session('career_auth.nama', 'ADMIN'),
                    'Updated_By_Id' => session('career_auth.id'),
                ]);

            Log::channel('web_career')->info("Master kategori #{$id} diperbarui");

            return ResponseHelper::success(null, 'Kategori diperbarui');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ResponseHelper::error(collect($e->errors())->flatten()->first() ?? 'Data tidak valid', 422);
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal update kategori #{$id}: " . $e->getMessage());

            return ResponseHelper::error('Gagal memperbarui data', 500);
        }
    }

    /** Aktif/Nonaktif kategori. */
    public function toggle(Request $request, $id)
    {
        try {
            $realId = Hashids::decode($id)[0] ?? null;
            $aktif = $request->boolean('aktif');
            $terpengaruh = DB::table('N_WEB_CAREERS_Master_Kategori')
                ->where('Id_Master_Kategori', $realId)
                ->update([
                    'Flag_Aktif' => $aktif ? 'Y' : 'N',
                    'Updated_At' => now(),
                    'Updated_By' => session('career_auth.nama', 'ADMIN'),
                    'Updated_By_Id' => session('career_auth.id'),
                ]);

            if (!$terpengaruh) {
                return ResponseHelper::error('Data tidak ditemukan', 404);
            }

            Log::channel('web_career')->info("Master kategori #{$id} status " . ($aktif ? 'AKTIF' : 'NONAKTIF'));

            return ResponseHelper::success(null, 'Status diperbarui');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal toggle kategori #{$id}: " . $e->getMessage());

            return ResponseHelper::error('Gagal mengubah status', 500);
        }
    }

    /** Hapus kategori. */
    public function destroy($id)
    {
        try {
            $realId = Hashids::decode($id)[0] ?? null;
            $terpengaruh = DB::table('N_WEB_CAREERS_Master_Kategori')->where('Id_Master_Kategori', $realId)->delete();
            if (!$terpengaruh) {
                return ResponseHelper::error('Data tidak ditemukan', 404);
            }

            Log::channel('web_career')->info("Master kategori #{$id} dihapus");

            return ResponseHelper::success(null, 'Kategori dihapus');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal hapus kategori #{$id}: " . $e->getMessage());

            return ResponseHelper::error('Gagal menghapus data', 500);
        }
    }
}
