<?php

namespace App\Http\Controllers\Career\MasterModeKeputusan;

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
 * WEB CAREER — MASTER MODE KEPUTUSAN (bagaimana sebuah tahap MENYIMPULKAN).
 * Kolom perilaku (Tunggu/Auto_Lanjut/Syarat_Lulus/Auto_Gugur) dibaca mesin
 * keputusan (LamaranService::evaluasiTahap) — jadi mode baru = insert baris,
 * bukan ubah kode. CRUD Query Builder + ResponseHelper.
 */
class MasterModeKeputusanController extends Controller
{
    public function index()
    {
        return Inertia::render('Career/admin/master-mode-keputusan/masterModeKeputusan', CareerShell::props('/master-mode-keputusan', 'Master Mode Keputusan'));
    }

    public function list()
    {
        try {
            $rows = DB::table('N_WEB_CAREERS_Master_Mode_Keputusan as m')
                ->leftJoin('N_WEB_CAREERS_Users as u', 'u.Id_Users', '=', 'm.Created_By_Id')
                ->orderBy('m.Urutan')
                ->orderBy('m.Id_Master_Mode_Keputusan')
                ->select('m.*', 'u.Nama as Pembuat')
                ->get()
                ->map(fn ($r) => [
                    'id' => Hashids::encode($r->Id_Master_Mode_Keputusan),
                    'kode' => $r->Kode,
                    'nama' => $r->Nama,
                    'label' => $r->Label,
                    'ikon' => $r->Ikon,
                    'warna' => $r->Warna,
                    'deskripsi' => $r->Deskripsi,
                    'tunggu' => $r->Tunggu,
                    'autoLanjut' => $r->Auto_Lanjut === 'Y',
                    'syaratLulus' => $r->Syarat_Lulus,
                    'autoGugur' => $r->Auto_Gugur === 'Y',
                    'sifat' => $r->Flag_Sistem === 'Y' ? 'SISTEM' : 'KUSTOM',
                    'status' => $r->Flag_Aktif === 'Y' ? 'AKTIF' : 'NONAKTIF',
                    'createdBy' => $r->Pembuat ?: $r->Created_By,
                    'createdAt' => $r->Created_At,
                ])
                ->values();

            return ResponseHelper::success($rows, 'Data mode keputusan dimuat');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('Gagal memuat mode keputusan: ' . $e->getMessage());
            return ResponseHelper::error('Gagal memuat data mode keputusan', 500);
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
            'tunggu' => 'required|in:SEMUA,TERAKHIR,SEGERA',
            'autoLanjut' => 'nullable|boolean',
            'syaratLulus' => 'required|in:SEMUA_PENENTU,MANUAL',
            'autoGugur' => 'nullable|boolean',
        ];
    }

    public function store(Request $request)
    {
        try {
            $data = $request->validate($this->rules());
            $userId = session('career_auth.id');
            $userName = session('career_auth.nama', 'ADMIN');
            $now = now();

            // Kode dibuat memakai lebar kolom penuh; sufiks _N hanya bila bentrok.
            $kode = KodeUnik::buat('N_WEB_CAREERS_Master_Mode_Keputusan', 'Kode', $data['nama'], 30, 'MODE');
            $urutan = (int) DB::table('N_WEB_CAREERS_Master_Mode_Keputusan')->max('Urutan') + 1;

            DB::table('N_WEB_CAREERS_Master_Mode_Keputusan')->insert([
                'Kode' => $kode,
                'Nama' => $data['nama'],
                'Label' => $data['label'],
                'Ikon' => $data['ikon'] ?: 'bi-diagram-3',
                'Warna' => $data['warna'] ?: '#4f46e5',
                'Deskripsi' => $data['deskripsi'] ?? null,
                'Tunggu' => $data['tunggu'],
                'Auto_Lanjut' => ($data['autoLanjut'] ?? false) ? 'Y' : 'N',
                'Syarat_Lulus' => $data['syaratLulus'],
                'Auto_Gugur' => ($data['autoGugur'] ?? false) ? 'Y' : 'N',
                'Urutan' => $urutan,
                'Flag_Sistem' => 'N',
                'Flag_Aktif' => 'Y',
                'Created_At' => $now, 'Created_By' => $userName, 'Created_By_Id' => $userId,
                'Updated_At' => $now, 'Updated_By' => $userName, 'Updated_By_Id' => $userId,
            ]);

            Log::channel('web_career')->info("Master mode keputusan dibuat ({$kode}) oleh {$userName}");
            return ResponseHelper::success(null, 'Mode keputusan berhasil ditambahkan', 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ResponseHelper::error(collect($e->errors())->flatten()->first() ?? 'Data tidak valid', 422);
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('Gagal membuat mode keputusan: ' . $e->getMessage());
            return ResponseHelper::error('Gagal menyimpan data', 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $realId = Hashids::decode($id)[0] ?? null;
            $row = DB::table('N_WEB_CAREERS_Master_Mode_Keputusan')->where('Id_Master_Mode_Keputusan', $realId)->first();
            if (!$row) {
                return ResponseHelper::error('Data tidak ditemukan', 404);
            }
            $data = $request->validate($this->rules());

            DB::table('N_WEB_CAREERS_Master_Mode_Keputusan')->where('Id_Master_Mode_Keputusan', $realId)->update([
                'Nama' => $data['nama'],
                'Label' => $data['label'],
                'Ikon' => $data['ikon'] ?: $row->Ikon,
                'Warna' => $data['warna'] ?: $row->Warna,
                'Deskripsi' => $data['deskripsi'] ?? null,
                'Tunggu' => $data['tunggu'],
                'Auto_Lanjut' => ($data['autoLanjut'] ?? false) ? 'Y' : 'N',
                'Syarat_Lulus' => $data['syaratLulus'],
                'Auto_Gugur' => ($data['autoGugur'] ?? false) ? 'Y' : 'N',
                'Updated_At' => now(),
                'Updated_By' => session('career_auth.nama', 'ADMIN'),
                'Updated_By_Id' => session('career_auth.id'),
            ]);

            Log::channel('web_career')->info("Master mode keputusan #{$id} diperbarui");
            return ResponseHelper::success(null, 'Mode keputusan diperbarui');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ResponseHelper::error(collect($e->errors())->flatten()->first() ?? 'Data tidak valid', 422);
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal update mode keputusan #{$id}: " . $e->getMessage());
            return ResponseHelper::error('Gagal memperbarui data', 500);
        }
    }

    public function toggle(Request $request, $id)
    {
        try {
            $realId = Hashids::decode($id)[0] ?? null;
            $aktif = $request->boolean('aktif');
            $terpengaruh = DB::table('N_WEB_CAREERS_Master_Mode_Keputusan')->where('Id_Master_Mode_Keputusan', $realId)->update([
                'Flag_Aktif' => $aktif ? 'Y' : 'N',
                'Updated_At' => now(),
                'Updated_By' => session('career_auth.nama', 'ADMIN'),
                'Updated_By_Id' => session('career_auth.id'),
            ]);
            if (!$terpengaruh) {
                return ResponseHelper::error('Data tidak ditemukan', 404);
            }
            Log::channel('web_career')->info("Master mode keputusan #{$id} status " . ($aktif ? 'AKTIF' : 'NONAKTIF'));
            return ResponseHelper::success(null, 'Status diperbarui');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal toggle mode keputusan #{$id}: " . $e->getMessage());
            return ResponseHelper::error('Gagal mengubah status', 500);
        }
    }

    public function destroy($id)
    {
        try {
            $realId = Hashids::decode($id)[0] ?? null;
            $row = DB::table('N_WEB_CAREERS_Master_Mode_Keputusan')->where('Id_Master_Mode_Keputusan', $realId)->first();
            if (!$row) {
                return ResponseHelper::error('Data tidak ditemukan', 404);
            }
            if ($row->Flag_Sistem === 'Y') {
                return ResponseHelper::error('Mode bawaan sistem tidak boleh dihapus — nonaktifkan saja.', 422);
            }
            DB::table('N_WEB_CAREERS_Master_Mode_Keputusan')->where('Id_Master_Mode_Keputusan', $realId)->delete();
            Log::channel('web_career')->info("Master mode keputusan #{$id} dihapus");
            return ResponseHelper::success(null, 'Mode keputusan dihapus');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal hapus mode keputusan #{$id}: " . $e->getMessage());
            return ResponseHelper::error('Gagal menghapus data', 500);
        }
    }
}
