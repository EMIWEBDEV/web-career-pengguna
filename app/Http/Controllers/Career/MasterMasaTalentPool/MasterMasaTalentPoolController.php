<?php

namespace App\Http\Controllers\Career\MasterMasaTalentPool;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Support\CareerShell;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Vinkla\Hashids\Facades\Hashids;

/**
 * WEB CAREER — MASTER MASA BERLAKU TALENT POOL.
 *
 * Menentukan berapa lama kartu Talent Pool berlaku sebelum kedaluwarsa
 * (HARI/BULAN/TAHUN). Data-driven: LamaranService::hitungKedaluwarsa membaca
 * baris yang AKTIF. Hanya SATU baris boleh aktif pada satu waktu.
 */
class MasterMasaTalentPoolController extends Controller
{
    private const TBL = 'N_WEB_CAREERS_Master_Masa_Talent_Pool';

    private const SATUAN = ['HARI', 'BULAN', 'TAHUN'];

    public function index()
    {
        return Inertia::render('Career/admin/master-masa-talent-pool/masterMasaTalentPool', CareerShell::props('/master-masa-talent-pool', 'Master Masa Berlaku Talent Pool'));
    }

    public function list()
    {
        try {
            $rows = DB::table(self::TBL . ' as m')
                ->leftJoin('N_WEB_CAREERS_Users as u', 'u.Id_Users', '=', 'm.Created_By_Id')
                ->orderByDesc('m.Flag_Aktif')
                ->orderBy('m.Id_Master_Masa_Talent_Pool')
                ->select('m.*', 'u.Nama as Pembuat')
                ->get()
                ->map(fn ($r) => [
                    'id' => Hashids::encode($r->Id_Master_Masa_Talent_Pool),
                    'kode' => $r->Kode,
                    'nama' => $r->Nama,
                    'angka' => (int) $r->Durasi_Angka,
                    'satuan' => $r->Durasi_Satuan,
                    'ringkas' => $r->Durasi_Angka . ' ' . ucfirst(strtolower($r->Durasi_Satuan)),
                    'status' => $r->Flag_Aktif === 'Y' ? 'AKTIF' : 'NONAKTIF',
                    'createdBy' => $r->Pembuat ?: $r->Created_By,
                    'createdAt' => $r->Created_At,
                ])
                ->values();

            return ResponseHelper::success($rows, 'Data masa berlaku dimuat');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('Gagal memuat masa talent pool: ' . $e->getMessage());

            return ResponseHelper::error('Gagal memuat data', 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $data = $this->validasi($request);
            $userId = session('career_auth.id');
            $userName = session('career_auth.nama', 'ADMIN');
            $now = now();

            $base = trim(preg_replace('/[^A-Z0-9]+/', '_', strtoupper($data['nama'])), '_') ?: 'MASA';
            $base = substr($base, 0, 26);
            $kode = $base;
            $n = 2;
            while (DB::table(self::TBL)->where('Kode', $kode)->exists()) {
                $kode = $base . '_' . $n++;
            }

            DB::transaction(function () use ($data, $kode, $userId, $userName, $now) {
                $aktif = $data['aktif'] ?? true;
                if ($aktif) {
                    // Hanya satu yang aktif — matikan lainnya.
                    DB::table(self::TBL)->update(['Flag_Aktif' => 'N', 'Updated_At' => $now]);
                }
                DB::table(self::TBL)->insert([
                    'Kode' => $kode,
                    'Nama' => $data['nama'],
                    'Durasi_Angka' => $data['angka'],
                    'Durasi_Satuan' => $data['satuan'],
                    'Flag_Aktif' => $aktif ? 'Y' : 'N',
                    'Created_At' => $now, 'Created_By' => $userName, 'Created_By_Id' => $userId,
                    'Updated_At' => $now, 'Updated_By' => $userName, 'Updated_By_Id' => $userId,
                ]);
            });

            Log::channel('web_career')->info("Master masa talent pool dibuat ({$kode}) oleh {$userName}");

            return ResponseHelper::success(null, 'Masa berlaku ditambahkan', 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ResponseHelper::error(collect($e->errors())->flatten()->first() ?? 'Data tidak valid', 422);
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('Gagal membuat masa talent pool: ' . $e->getMessage());

            return ResponseHelper::error('Gagal menyimpan data', 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $realId = Hashids::decode($id)[0] ?? null;
            if (! DB::table(self::TBL)->where('Id_Master_Masa_Talent_Pool', $realId)->exists()) {
                return ResponseHelper::error('Data tidak ditemukan', 404);
            }
            $data = $this->validasi($request);

            DB::table(self::TBL)->where('Id_Master_Masa_Talent_Pool', $realId)->update([
                'Nama' => $data['nama'],
                'Durasi_Angka' => $data['angka'],
                'Durasi_Satuan' => $data['satuan'],
                'Updated_At' => now(),
                'Updated_By' => session('career_auth.nama', 'ADMIN'),
                'Updated_By_Id' => session('career_auth.id'),
            ]);

            Log::channel('web_career')->info("Master masa talent pool #{$realId} diperbarui");

            return ResponseHelper::success(null, 'Masa berlaku diperbarui');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ResponseHelper::error(collect($e->errors())->flatten()->first() ?? 'Data tidak valid', 422);
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal update masa talent pool #{$id}: " . $e->getMessage());

            return ResponseHelper::error('Gagal memperbarui data', 500);
        }
    }

    /** Aktifkan satu baris → otomatis menonaktifkan yang lain (single-active). */
    public function toggle(Request $request, $id)
    {
        try {
            $realId = Hashids::decode($id)[0] ?? null;
            $aktif = $request->boolean('aktif');
            $now = now();

            DB::transaction(function () use ($realId, $aktif, $now) {
                if ($aktif) {
                    DB::table(self::TBL)->update(['Flag_Aktif' => 'N', 'Updated_At' => $now]);
                }
                DB::table(self::TBL)->where('Id_Master_Masa_Talent_Pool', $realId)->update([
                    'Flag_Aktif' => $aktif ? 'Y' : 'N',
                    'Updated_At' => $now,
                    'Updated_By' => session('career_auth.nama', 'ADMIN'),
                    'Updated_By_Id' => session('career_auth.id'),
                ]);
            });

            Log::channel('web_career')->info("Master masa talent pool #{$realId} status " . ($aktif ? 'AKTIF' : 'NONAKTIF'));

            return ResponseHelper::success(null, 'Status diperbarui');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal toggle masa talent pool #{$id}: " . $e->getMessage());

            return ResponseHelper::error('Gagal mengubah status', 500);
        }
    }

    public function destroy($id)
    {
        try {
            $realId = Hashids::decode($id)[0] ?? null;
            $row = DB::table(self::TBL)->where('Id_Master_Masa_Talent_Pool', $realId)->first();
            if (! $row) {
                return ResponseHelper::error('Data tidak ditemukan', 404);
            }
            if ($row->Flag_Aktif === 'Y') {
                return ResponseHelper::error('Tidak bisa menghapus masa yang sedang AKTIF. Aktifkan yang lain dulu.', 422);
            }
            DB::table(self::TBL)->where('Id_Master_Masa_Talent_Pool', $realId)->delete();

            Log::channel('web_career')->info("Master masa talent pool #{$realId} dihapus");

            return ResponseHelper::success(null, 'Masa berlaku dihapus');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal hapus masa talent pool #{$id}: " . $e->getMessage());

            return ResponseHelper::error('Gagal menghapus data', 500);
        }
    }

    private function validasi(Request $request): array
    {
        return $request->validate([
            'nama' => 'required|string|max:100',
            'angka' => 'required|integer|min:1|max:3650',
            'satuan' => ['required', 'string', \Illuminate\Validation\Rule::in(self::SATUAN)],
            'aktif' => 'nullable|boolean',
        ]);
    }
}
