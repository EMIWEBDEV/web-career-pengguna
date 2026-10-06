<?php

namespace App\Http\Controllers\Career\MasterKemitraan;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Support\CareerShell;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Vinkla\Hashids\Facades\Hashids;

/**
 * WEB CAREER — MASTER KEMITRAAN / MoU (SPA + WEB, tanpa API).
 * Bespoke: FK ke Master Kampus, tanggal periode, status lifecycle (AKTIF/BERAKHIR/DRAFT) — TANPA toggle.
 * - index()  : return Inertia (render halaman) — shell admin via CareerShell::props.
 * - list()   : data kemitraan (Query Builder + join Users & Master Kampus) -> ResponseHelper.
 * - store/update/destroy : CRUD Query Builder -> ResponseHelper, try/catch, Log channel.
 */
class MasterKemitraanController extends Controller
{
    /** Halaman (Inertia). Data TIDAK dikirim lewat props — halaman fetch sendiri ke API list(). */
    public function index()
    {
        return Inertia::render('Career/admin/master-kemitraan/masterKemitraan', CareerShell::props('/master-kemitraan', 'Master Kemitraan / MoU'));
    }

    /** Data list Master Kemitraan (Query Builder + join pembuat & kampus) -> ResponseHelper. */
    public function list()
    {
        try {
            $rows = DB::table('N_WEB_CAREERS_Master_Kemitraan as m')
                ->leftJoin('N_WEB_CAREERS_Users as u', 'u.Id_Users', '=', 'm.Created_By_Id')
                ->leftJoin('N_WEB_CAREERS_Master_Kampus as k', 'k.Id_Master_Kampus', '=', 'm.Master_Kampus_Id')
                ->orderBy('m.Id_Master_Kemitraan')
                ->select('m.*', 'u.Nama as Pembuat', 'k.Nama as Kampus_Nama')
                ->get()
                ->map(function ($r) {
                    return [
                        'id' => Hashids::encode($r->Id_Master_Kemitraan), // id di-hash (raw id tidak diekspos)
                        'nomor' => $r->Nomor,
                        'mitra' => $r->Mitra,
                        'kampusId' => $r->Master_Kampus_Id,
                        'kampusNama' => $r->Kampus_Nama,
                        'jenis' => $r->Jenis,
                        'kuota' => (int) $r->Kuota,
                        'terisi' => (int) $r->Terisi,
                        'pic' => $r->PIC,
                        'picKontak' => $r->PIC_Kontak,
                        'mulai' => $r->Tanggal_Mulai,
                        'selesai' => $r->Tanggal_Selesai,
                        'status' => $r->Status,
                        'catatan' => $r->Catatan,
                        'createdBy' => $r->Pembuat ?: $r->Created_By,
                        'createdAt' => $r->Created_At,
                    ];
                })
                ->values();

            return ResponseHelper::success($rows, 'Data kemitraan dimuat');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('Gagal memuat kemitraan: ' . $e->getMessage());

            return ResponseHelper::error('Gagal memuat data kemitraan', 500);
        }
    }

    /** Aturan validasi (dipakai store & update). */
    private function rules(): array
    {
        return [
            'nomor' => 'nullable|string|max:60',
            'mitra' => 'required|string|max:150',
            'kampusId' => 'nullable|integer',
            'jenis' => 'required|in:MSIB,MANDIRI,PKL,PENELITIAN',
            'kuota' => 'nullable|integer|min:0',
            'terisi' => 'nullable|integer|min:0',
            'pic' => 'nullable|string|max:120',
            'picKontak' => 'nullable|string|max:120',
            'mulai' => 'nullable|date',
            'selesai' => 'nullable|date',
            'status' => 'required|in:AKTIF,BERAKHIR,DRAFT',
            'catatan' => 'nullable|string',
        ];
    }

    /** Tambah kemitraan / MoU. */
    public function store(Request $request)
    {
        try {
            $data = $request->validate($this->rules());

            $userId = session('career_auth.id');
            $userName = session('career_auth.nama', 'ADMIN');
            $now = now();

            DB::table('N_WEB_CAREERS_Master_Kemitraan')->insert([
                'Nomor' => $data['nomor'] ?? null,
                'Mitra' => $data['mitra'],
                'Master_Kampus_Id' => $data['kampusId'] ?? null,
                'Jenis' => $data['jenis'],
                'Kuota' => $data['kuota'] ?? 0,
                'Terisi' => $data['terisi'] ?? 0,
                'PIC' => $data['pic'] ?? null,
                'PIC_Kontak' => $data['picKontak'] ?? null,
                'Tanggal_Mulai' => $data['mulai'] ?? null,
                'Tanggal_Selesai' => $data['selesai'] ?? null,
                'Status' => $data['status'],
                'Catatan' => $data['catatan'] ?? null,
                'Created_At' => $now,
                'Created_By' => $userName,
                'Created_By_Id' => $userId,
                'Updated_At' => $now,
                'Updated_By' => $userName,
                'Updated_By_Id' => $userId,
            ]);

            Log::channel('web_career')->info("Master kemitraan dibuat oleh {$userName}");

            return ResponseHelper::success(null, 'MoU berhasil ditambahkan', 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ResponseHelper::error(collect($e->errors())->flatten()->first() ?? 'Data tidak valid', 422);
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('Gagal membuat kemitraan: ' . $e->getMessage());

            return ResponseHelper::error('Gagal menyimpan data', 500);
        }
    }

    /** Ubah kemitraan / MoU. */
    public function update(Request $request, $id)
    {
        try {
            $realId = Hashids::decode($id)[0] ?? null;
            $row = DB::table('N_WEB_CAREERS_Master_Kemitraan')->where('Id_Master_Kemitraan', $realId)->first();
            if (!$row) {
                return ResponseHelper::error('Data tidak ditemukan', 404);
            }

            $data = $request->validate($this->rules());

            DB::table('N_WEB_CAREERS_Master_Kemitraan')
                ->where('Id_Master_Kemitraan', $realId)
                ->update([
                    'Nomor' => $data['nomor'] ?? null,
                    'Mitra' => $data['mitra'],
                    'Master_Kampus_Id' => $data['kampusId'] ?? null,
                    'Jenis' => $data['jenis'],
                    'Kuota' => $data['kuota'] ?? 0,
                    'Terisi' => $data['terisi'] ?? 0,
                    'PIC' => $data['pic'] ?? null,
                    'PIC_Kontak' => $data['picKontak'] ?? null,
                    'Tanggal_Mulai' => $data['mulai'] ?? null,
                    'Tanggal_Selesai' => $data['selesai'] ?? null,
                    'Status' => $data['status'],
                    'Catatan' => $data['catatan'] ?? null,
                    'Updated_At' => now(),
                    'Updated_By' => session('career_auth.nama', 'ADMIN'),
                    'Updated_By_Id' => session('career_auth.id'),
                ]);

            Log::channel('web_career')->info("Master kemitraan #{$id} diperbarui");

            return ResponseHelper::success(null, 'MoU diperbarui');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ResponseHelper::error(collect($e->errors())->flatten()->first() ?? 'Data tidak valid', 422);
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal update kemitraan #{$id}: " . $e->getMessage());

            return ResponseHelper::error('Gagal memperbarui data', 500);
        }
    }

    /** Hapus kemitraan / MoU. */
    public function destroy($id)
    {
        try {
            $realId = Hashids::decode($id)[0] ?? null;
            $terpengaruh = DB::table('N_WEB_CAREERS_Master_Kemitraan')->where('Id_Master_Kemitraan', $realId)->delete();
            if (!$terpengaruh) {
                return ResponseHelper::error('Data tidak ditemukan', 404);
            }

            Log::channel('web_career')->info("Master kemitraan #{$id} dihapus");

            return ResponseHelper::success(null, 'MoU dihapus');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal hapus kemitraan #{$id}: " . $e->getMessage());

            return ResponseHelper::error('Gagal menghapus data', 500);
        }
    }
}
