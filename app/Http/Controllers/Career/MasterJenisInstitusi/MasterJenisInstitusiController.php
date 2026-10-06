<?php

namespace App\Http\Controllers\Career\MasterJenisInstitusi;

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
 * WEB CAREER — MASTER JENIS INSTITUSI PENDIDIKAN (SPA + WEB, pola Master Kampus).
 * Jenis institusi (Universitas, Politeknik, SMA, SMK, …) + Jenjang_Berlaku =
 * daftar jenjang yang menjadikan jenis ini muncul pada cascade formulir.
 * Kode dipakai sebagai binding ke N_WEB_CAREERS_Master_Kampus.Jenis_Institusi_Kode.
 * Tabel: N_WEB_CAREERS_Master_Jenis_Institusi (PK Id_Master_Jenis_Institusi).
 */
class MasterJenisInstitusiController extends Controller
{
    private const TABEL = 'N_WEB_CAREERS_Master_Jenis_Institusi';
    private const BINDING = 'N_WEB_CAREERS_Jenis_Institusi_Jenjang';

    public function index()
    {
        return Inertia::render('Career/admin/master-jenis-institusi/masterJenisInstitusi', CareerShell::props('/master-jenis-institusi', 'Master Jenis Institusi'));
    }

    public function list()
    {
        try {
            // jenjangBerlaku bersumber dari tabel BINDING (junction), bukan CSV.
            $binding = DB::table(self::BINDING)->get(['Kode_Jenis', 'Kode_Jenjang'])
                ->groupBy('Kode_Jenis')->map(fn ($g) => $g->pluck('Kode_Jenjang')->values()->all());

            $rows = DB::table(self::TABEL . ' as j')
                ->leftJoin('N_WEB_CAREERS_Users as u', 'u.Id_Users', '=', 'j.Created_By_Id')
                ->orderBy('j.Urutan')->orderBy('j.Id_Master_Jenis_Institusi')
                ->select('j.*', 'u.Nama as Pembuat')
                ->get()
                ->map(fn ($r) => [
                    'id' => Hashids::encode($r->Id_Master_Jenis_Institusi),
                    'kode' => $r->Kode,
                    'nama' => $r->Nama,
                    'kategori' => $r->Kategori,
                    'jenjangBerlaku' => $binding[$r->Kode] ?? [],
                    'urutan' => (int) $r->Urutan,
                    'status' => $r->Flag_Aktif === 'Y' ? 'AKTIF' : 'NONAKTIF',
                    'createdBy' => $r->Pembuat ?: $r->Created_By,
                    'createdAt' => $r->Created_At,
                ])->values();

            return ResponseHelper::success($rows, 'Data jenis institusi dimuat');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('Gagal memuat jenis institusi: ' . $e->getMessage());
            return ResponseHelper::error('Gagal memuat data jenis institusi', 500);
        }
    }

    /** Sinkron tabel binding Jenis↔Jenjang untuk satu jenis (delete + reinsert). */
    private function syncBinding(string $kode, $jenjang): void
    {
        if (is_string($jenjang)) {
            $jenjang = explode(',', $jenjang);
        }
        DB::table(self::BINDING)->where('Kode_Jenis', $kode)->delete();
        $now = now();
        $by = session('career_auth.nama', 'ADMIN');
        $rows = collect((array) $jenjang)->map(fn ($j) => trim((string) $j))->filter()->unique()
            ->map(fn ($j) => ['Kode_Jenis' => $kode, 'Kode_Jenjang' => $j, 'Created_At' => $now, 'Created_By' => $by])
            ->values()->all();
        if ($rows) {
            DB::table(self::BINDING)->insert($rows);
        }
        // Bersihkan cache cascade formulir agar perubahan langsung terlihat.
        \Illuminate\Support\Facades\Cache::forget('wc_pdk_jenis_all');
        foreach (DB::table('N_WEB_CAREERS_Master_Jenjang')->pluck('Kode') as $jk) {
            \Illuminate\Support\Facades\Cache::forget('wc_pdk_jenis_' . $jk);
        }
    }

    /** Normalisasi jenjangBerlaku (array|string) → CSV rapi. */
    private function csvJenjang($v): ?string
    {
        if (is_array($v)) {
            $v = implode(',', $v);
        }
        $parts = collect(explode(',', (string) $v))->map(fn ($x) => trim($x))->filter()->unique()->values();
        return $parts->isEmpty() ? null : $parts->implode(',');
    }

    public function store(Request $request)
    {
        try {
            $data = $request->validate([
                'nama' => 'required|string|max:100',
                'kode' => 'nullable|string|max:50',
                'kategori' => 'nullable|string|max:20',
                'jenjangBerlaku' => 'nullable',
                'urutan' => 'nullable|integer|min:0',
            ]);
            $userId = session('career_auth.id');
            $userName = session('career_auth.nama', 'ADMIN');
            $now = now();
            $sumber = !empty($data['kode']) ? $data['kode'] : $data['nama'];
            $kode = KodeUnik::buat(self::TABEL, 'Kode', $sumber, 50, 'JIN');

            DB::table(self::TABEL)->insert([
                'Kode' => $kode,
                'Nama' => $data['nama'],
                'Kategori' => $data['kategori'] ?? null,
                'Jenjang_Berlaku' => $this->csvJenjang($data['jenjangBerlaku'] ?? null),
                'Urutan' => $data['urutan'] ?? 0,
                'Flag_Aktif' => 'Y',
                'Created_At' => $now, 'Created_By' => $userName, 'Created_By_Id' => $userId,
                'Updated_At' => $now, 'Updated_By' => $userName, 'Updated_By_Id' => $userId,
            ]);
            $this->syncBinding($kode, $data['jenjangBerlaku'] ?? []);
            Log::channel('web_career')->info("Master jenis institusi dibuat ({$kode}) oleh {$userName}");
            return ResponseHelper::success(null, 'Jenis institusi berhasil ditambahkan', 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ResponseHelper::error(collect($e->errors())->flatten()->first() ?? 'Data tidak valid', 422);
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('Gagal membuat jenis institusi: ' . $e->getMessage());
            return ResponseHelper::error('Gagal menyimpan data', 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $realId = Hashids::decode($id)[0] ?? null;
            $row = DB::table(self::TABEL)->where('Id_Master_Jenis_Institusi', $realId)->first();
            if (!$row) {
                return ResponseHelper::error('Data tidak ditemukan', 404);
            }
            $data = $request->validate([
                'nama' => 'required|string|max:100',
                'kategori' => 'nullable|string|max:20',
                'jenjangBerlaku' => 'nullable',
                'urutan' => 'nullable|integer|min:0',
            ]);
            DB::table(self::TABEL)->where('Id_Master_Jenis_Institusi', $realId)->update([
                'Nama' => $data['nama'],
                'Kategori' => $data['kategori'] ?? $row->Kategori,
                'Jenjang_Berlaku' => $this->csvJenjang($data['jenjangBerlaku'] ?? null),
                'Urutan' => $data['urutan'] ?? $row->Urutan,
                'Updated_At' => now(), 'Updated_By' => session('career_auth.nama', 'ADMIN'), 'Updated_By_Id' => session('career_auth.id'),
            ]);
            $this->syncBinding($row->Kode, $data['jenjangBerlaku'] ?? []);
            return ResponseHelper::success(null, 'Jenis institusi diperbarui');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ResponseHelper::error(collect($e->errors())->flatten()->first() ?? 'Data tidak valid', 422);
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal update jenis institusi #{$id}: " . $e->getMessage());
            return ResponseHelper::error('Gagal memperbarui data', 500);
        }
    }

    public function toggle(Request $request, $id)
    {
        try {
            $realId = Hashids::decode($id)[0] ?? null;
            $aktif = $request->boolean('aktif');
            $ok = DB::table(self::TABEL)->where('Id_Master_Jenis_Institusi', $realId)->update([
                'Flag_Aktif' => $aktif ? 'Y' : 'N',
                'Updated_At' => now(), 'Updated_By' => session('career_auth.nama', 'ADMIN'), 'Updated_By_Id' => session('career_auth.id'),
            ]);
            if (!$ok) {
                return ResponseHelper::error('Data tidak ditemukan', 404);
            }
            return ResponseHelper::success(null, 'Status diperbarui');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal toggle jenis institusi #{$id}: " . $e->getMessage());
            return ResponseHelper::error('Gagal mengubah status', 500);
        }
    }

    public function destroy($id)
    {
        try {
            $realId = Hashids::decode($id)[0] ?? null;
            $ok = DB::table(self::TABEL)->where('Id_Master_Jenis_Institusi', $realId)->delete();
            if (!$ok) {
                return ResponseHelper::error('Data tidak ditemukan', 404);
            }
            return ResponseHelper::success(null, 'Jenis institusi dihapus');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal hapus jenis institusi #{$id}: " . $e->getMessage());
            return ResponseHelper::error('Gagal menghapus data', 500);
        }
    }
}
