<?php

namespace App\Http\Controllers\Career\MasterModePengumuman;

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
 * WEB CAREER — MASTER MODE PENGUMUMAN (SPA + WEB, tanpa API).
 * Kapan hasil sebuah tahap terlihat kandidat. Menggantikan opsi hardcode di
 * builder Alur (OTOMATIS/TERJADWAL/MANUAL) — kini satu sumber + Flag_Aktif.
 * Semua penulisan DB::table('N_WEB_CAREERS_Master_Mode_Pengumuman') langsung.
 */
class MasterModePengumumanController extends Controller
{
    /** Halaman (Inertia). Data di-fetch sendiri oleh halaman ke list(). */
    public function index()
    {
        return Inertia::render('Career/admin/master-mode-pengumuman/masterModePengumuman', CareerShell::props('/master-mode-pengumuman', 'Master Mode Pengumuman'));
    }

    /** Data list Master Mode Pengumuman -> ResponseHelper. */
    public function list()
    {
        try {
            $rows = DB::table('N_WEB_CAREERS_Master_Mode_Pengumuman as m')
                ->leftJoin('N_WEB_CAREERS_Users as u', 'u.Id_Users', '=', 'm.Created_By_Id')
                ->orderBy('m.Urutan')
                ->orderBy('m.Id_Master_Mode_Pengumuman')
                ->select('m.*', 'u.Nama as Pembuat')
                ->get()
                ->map(function ($r) {
                    return [
                        'id' => Hashids::encode($r->Id_Master_Mode_Pengumuman),
                        'kode' => $r->Kode,
                        'nama' => $r->Nama,
                        'label' => $r->Label,
                        'ikon' => $r->Ikon,
                        'warna' => $r->Warna,
                        'deskripsi' => $r->Deskripsi,
                        'butuhJeda' => $r->Butuh_Jeda === 'Y',
                        'sifat' => $r->Flag_Sistem === 'Y' ? 'SISTEM' : 'KUSTOM',
                        'status' => $r->Flag_Aktif === 'Y' ? 'AKTIF' : 'NONAKTIF',
                        'createdBy' => $r->Pembuat ?: $r->Created_By,
                        'createdAt' => $r->Created_At,
                    ];
                })
                ->values();

            return ResponseHelper::success($rows, 'Data mode pengumuman dimuat');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('Gagal memuat mode pengumuman: ' . $e->getMessage());
            return ResponseHelper::error('Gagal memuat data mode pengumuman', 500);
        }
    }

    private function rules(): array
    {
        return [
            'nama' => 'required|string|max:80',
            'label' => 'required|string|max:150',
            'ikon' => 'nullable|string|max:50',
            'warna' => 'nullable|string|max:20',
            'deskripsi' => 'nullable|string|max:500',
            'butuhJeda' => 'nullable|boolean',
        ];
    }

    /** Tambah mode pengumuman kustom. */
    public function store(Request $request)
    {
        try {
            $data = $request->validate($this->rules());
            $userId = session('career_auth.id');
            $userName = session('career_auth.nama', 'ADMIN');
            $now = now();

            // Kode dibuat memakai lebar kolom penuh; sufiks _N hanya bila bentrok.
            $kode = KodeUnik::buat('N_WEB_CAREERS_Master_Mode_Pengumuman', 'Kode', $data['nama'], 20, 'MODE');
            $urutanBerikut = (int) DB::table('N_WEB_CAREERS_Master_Mode_Pengumuman')->max('Urutan') + 1;

            DB::table('N_WEB_CAREERS_Master_Mode_Pengumuman')->insert([
                'Kode' => $kode,
                'Nama' => $data['nama'],
                'Label' => $data['label'],
                'Ikon' => $data['ikon'] ?: 'bi-megaphone',
                'Warna' => $data['warna'] ?: '#4f46e5',
                'Deskripsi' => $data['deskripsi'] ?? null,
                'Butuh_Jeda' => ($data['butuhJeda'] ?? false) ? 'Y' : 'N',
                'Urutan' => $urutanBerikut,
                'Flag_Sistem' => 'N',
                'Flag_Aktif' => 'Y',
                'Created_At' => $now, 'Created_By' => $userName, 'Created_By_Id' => $userId,
                'Updated_At' => $now, 'Updated_By' => $userName, 'Updated_By_Id' => $userId,
            ]);

            Log::channel('web_career')->info("Master mode pengumuman dibuat ({$kode}) oleh {$userName}");
            return ResponseHelper::success(null, 'Mode pengumuman berhasil ditambahkan', 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ResponseHelper::error(collect($e->errors())->flatten()->first() ?? 'Data tidak valid', 422);
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('Gagal membuat mode pengumuman: ' . $e->getMessage());
            return ResponseHelper::error('Gagal menyimpan data', 500);
        }
    }

    /** Ubah mode pengumuman. */
    public function update(Request $request, $id)
    {
        try {
            $realId = Hashids::decode($id)[0] ?? null;
            $row = DB::table('N_WEB_CAREERS_Master_Mode_Pengumuman')->where('Id_Master_Mode_Pengumuman', $realId)->first();
            if (!$row) {
                return ResponseHelper::error('Data tidak ditemukan', 404);
            }

            $data = $request->validate($this->rules());

            DB::table('N_WEB_CAREERS_Master_Mode_Pengumuman')
                ->where('Id_Master_Mode_Pengumuman', $realId)
                ->update([
                    'Nama' => $data['nama'],
                    'Label' => $data['label'],
                    'Ikon' => $data['ikon'] ?: $row->Ikon,
                    'Warna' => $data['warna'] ?: $row->Warna,
                    'Deskripsi' => $data['deskripsi'] ?? null,
                    'Butuh_Jeda' => ($data['butuhJeda'] ?? false) ? 'Y' : 'N',
                    'Updated_At' => now(),
                    'Updated_By' => session('career_auth.nama', 'ADMIN'),
                    'Updated_By_Id' => session('career_auth.id'),
                ]);

            Log::channel('web_career')->info("Master mode pengumuman #{$id} diperbarui");
            return ResponseHelper::success(null, 'Mode pengumuman diperbarui');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ResponseHelper::error(collect($e->errors())->flatten()->first() ?? 'Data tidak valid', 422);
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal update mode pengumuman #{$id}: " . $e->getMessage());
            return ResponseHelper::error('Gagal memperbarui data', 500);
        }
    }

    /** Aktif/Nonaktif mode pengumuman. Ini mekanisme "menyembunyikan" TERJADWAL. */
    public function toggle(Request $request, $id)
    {
        try {
            $realId = Hashids::decode($id)[0] ?? null;
            $aktif = $request->boolean('aktif');
            $terpengaruh = DB::table('N_WEB_CAREERS_Master_Mode_Pengumuman')
                ->where('Id_Master_Mode_Pengumuman', $realId)
                ->update([
                    'Flag_Aktif' => $aktif ? 'Y' : 'N',
                    'Updated_At' => now(),
                    'Updated_By' => session('career_auth.nama', 'ADMIN'),
                    'Updated_By_Id' => session('career_auth.id'),
                ]);

            if (!$terpengaruh) {
                return ResponseHelper::error('Data tidak ditemukan', 404);
            }

            Log::channel('web_career')->info("Master mode pengumuman #{$id} status " . ($aktif ? 'AKTIF' : 'NONAKTIF'));
            return ResponseHelper::success(null, 'Status diperbarui');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal toggle mode pengumuman #{$id}: " . $e->getMessage());
            return ResponseHelper::error('Gagal mengubah status', 500);
        }
    }

    /** Hapus mode pengumuman kustom (mode SISTEM sebaiknya dinonaktifkan, bukan dihapus). */
    public function destroy($id)
    {
        try {
            $realId = Hashids::decode($id)[0] ?? null;
            $row = DB::table('N_WEB_CAREERS_Master_Mode_Pengumuman')->where('Id_Master_Mode_Pengumuman', $realId)->first();
            if (!$row) {
                return ResponseHelper::error('Data tidak ditemukan', 404);
            }
            if ($row->Flag_Sistem === 'Y') {
                return ResponseHelper::error('Mode bawaan sistem tidak boleh dihapus — nonaktifkan saja.', 422);
            }

            DB::table('N_WEB_CAREERS_Master_Mode_Pengumuman')->where('Id_Master_Mode_Pengumuman', $realId)->delete();
            Log::channel('web_career')->info("Master mode pengumuman #{$id} dihapus");
            return ResponseHelper::success(null, 'Mode pengumuman dihapus');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal hapus mode pengumuman #{$id}: " . $e->getMessage());
            return ResponseHelper::error('Gagal menghapus data', 500);
        }
    }
}
