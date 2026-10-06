<?php

namespace App\Http\Controllers\Career\MasterTes;

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
 * WEB CAREER — MASTER JENIS TES (induk-detail: Jenis_Tes + Paket).
 * SPA + WEB. CRUD Query Builder + ResponseHelper + Log channel.
 */
class MasterTesController extends Controller
{
    public function index()
    {
        return Inertia::render('Career/admin/master-tes/masterTes', CareerShell::props('/master-tes', 'Master Jenis Tes'));
    }

    public function list()
    {
        try {
            $tes = DB::table('N_WEB_CAREERS_Master_Jenis_Tes as t')
                ->leftJoin('N_WEB_CAREERS_Users as u', 'u.Id_Users', '=', 't.Created_By_Id')
                ->orderBy('t.Id_Master_Jenis_Tes')
                ->select('t.*', 'u.Nama as Pembuat')
                ->get();

            $paket = DB::table('N_WEB_CAREERS_Master_Jenis_Tes_Paket')->orderBy('Id_Master_Jenis_Tes_Paket')->get()->groupBy('Master_Jenis_Tes_Id');

            $rows = $tes->map(function ($t) use ($paket) {
                return [
                    'id' => Hashids::encode($t->Id_Master_Jenis_Tes),
                    'kode' => $t->Kode,
                    'nama' => $t->Nama,
                    'kategori' => $t->Kategori,
                    'metode' => $t->Metode,
                    'pelaksana' => $t->Pelaksana,
                    'cat' => $t->Flag_Cat === 'Y',
                    // Masa berlaku nilai tes (bulan). Null/0 = selalu ambil tes baru.
                    'masaBerlakuBulan' => $t->Masa_Berlaku_Bulan !== null ? (int) $t->Masa_Berlaku_Bulan : null,
                    'status' => $t->Flag_Aktif === 'Y' ? 'AKTIF' : 'NONAKTIF',
                    'createdBy' => $t->Pembuat ?: $t->Created_By,
                    'createdAt' => $t->Created_At,
                    'paket' => collect($paket->get($t->Id_Master_Jenis_Tes, []))->map(fn ($p) => [
                        'nama' => $p->Nama,
                        'alat' => $p->Alat_List ? array_values(array_filter(array_map('trim', explode(',', $p->Alat_List)))) : [],
                        'durasi' => $p->Durasi_Menit,
                    ])->values(),
                ];
            })->values();

            return ResponseHelper::success($rows, 'Data jenis tes dimuat');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('Gagal memuat jenis tes: ' . $e->getMessage());

            return ResponseHelper::error('Gagal memuat data jenis tes', 500);
        }
    }

    private function rules(): array
    {
        return [
            'nama' => 'required|string|max:100',
            'kategori' => 'nullable|string|max:50',
            'metode' => 'required|string|max:20',
            'pelaksana' => 'nullable|string|max:100',
            'cat' => 'nullable|boolean',
            // Masa berlaku nilai (bulan): dalam rentang ini, hasil tes lama dipakai
            // ulang (kandidat tak perlu tes lagi). Kosong/0 = tak pernah reuse.
            'masaBerlakuBulan' => 'nullable|integer|min:0|max:120',
            'paket' => 'nullable|array',
            'paket.*.nama' => 'required|string|max:120',
            'paket.*.alat' => 'nullable|array',
            'paket.*.durasi' => 'nullable|integer|min:0',
        ];
    }

    private function simpanPaket(int $tesId, array $paket, ?int $userId, string $userName): void
    {
        $now = now();
        foreach ($paket as $p) {
            DB::table('N_WEB_CAREERS_Master_Jenis_Tes_Paket')->insert([
                'Master_Jenis_Tes_Id' => $tesId,
                'Nama' => $p['nama'],
                'Alat_List' => isset($p['alat']) ? implode(',', array_filter((array) $p['alat'], fn ($v) => $v !== '' && $v !== null)) : null,
                'Durasi_Menit' => $p['durasi'] ?? null,
                'Created_At' => $now, 'Created_By' => $userName, 'Created_By_Id' => $userId,
                'Updated_At' => $now, 'Updated_By' => $userName, 'Updated_By_Id' => $userId,
            ]);
        }
    }

    public function store(Request $request)
    {
        try {
            $data = $request->validate($this->rules());
            $userId = session('career_auth.id');
            $userName = session('career_auth.nama', 'ADMIN');
            $now = now();

            // Kode dibuat memakai lebar kolom penuh; sufiks _N hanya bila bentrok.
            $kode = KodeUnik::buat('N_WEB_CAREERS_Master_Jenis_Tes', 'Kode', $data['nama'], 30, 'TES');
            DB::transaction(function () use ($data, $kode, $userId, $userName, $now) {
                $id = DB::table('N_WEB_CAREERS_Master_Jenis_Tes')->insertGetId([
                    'Kode' => $kode,
                    'Nama' => $data['nama'],
                    'Kategori' => $data['kategori'] ?? null,
                    'Metode' => $data['metode'],
                    'Pelaksana' => $data['pelaksana'] ?? null,
                    'Flag_Cat' => ! empty($data['cat']) ? 'Y' : 'N',
                    'Masa_Berlaku_Bulan' => $data['masaBerlakuBulan'] ?? null,
                    'Flag_Aktif' => 'Y',
                    'Created_At' => $now, 'Created_By' => $userName, 'Created_By_Id' => $userId,
                    'Updated_At' => $now, 'Updated_By' => $userName, 'Updated_By_Id' => $userId,
                ], 'Id_Master_Jenis_Tes');

                $this->simpanPaket($id, $data['paket'] ?? [], $userId, $userName);
            });

            Log::channel('web_career')->info("Master jenis tes dibuat ({$kode}) oleh {$userName}");

            return ResponseHelper::success(null, 'Jenis tes berhasil ditambahkan', 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ResponseHelper::error(collect($e->errors())->flatten()->first() ?? 'Data tidak valid', 422);
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('Gagal membuat jenis tes: ' . $e->getMessage());

            return ResponseHelper::error('Gagal menyimpan data', 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $realId = Hashids::decode($id)[0] ?? null;
            $row = DB::table('N_WEB_CAREERS_Master_Jenis_Tes')->where('Id_Master_Jenis_Tes', $realId)->first();
            if (! $row) {
                return ResponseHelper::error('Data tidak ditemukan', 404);
            }
            $data = $request->validate($this->rules());
            $userId = session('career_auth.id');
            $userName = session('career_auth.nama', 'ADMIN');

            DB::transaction(function () use ($data, $realId, $userId, $userName) {
                DB::table('N_WEB_CAREERS_Master_Jenis_Tes')->where('Id_Master_Jenis_Tes', $realId)->update([
                    'Nama' => $data['nama'],
                    'Kategori' => $data['kategori'] ?? null,
                    'Metode' => $data['metode'],
                    'Pelaksana' => $data['pelaksana'] ?? null,
                    'Flag_Cat' => ! empty($data['cat']) ? 'Y' : 'N',
                    'Masa_Berlaku_Bulan' => $data['masaBerlakuBulan'] ?? null,
                    'Updated_At' => now(), 'Updated_By' => $userName, 'Updated_By_Id' => $userId,
                ]);
                DB::table('N_WEB_CAREERS_Master_Jenis_Tes_Paket')->where('Master_Jenis_Tes_Id', $realId)->delete();
                $this->simpanPaket($realId, $data['paket'] ?? [], $userId, $userName);
            });

            Log::channel('web_career')->info("Master jenis tes #{$realId} diperbarui");

            return ResponseHelper::success(null, 'Jenis tes diperbarui');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ResponseHelper::error(collect($e->errors())->flatten()->first() ?? 'Data tidak valid', 422);
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal update jenis tes #{$id}: " . $e->getMessage());

            return ResponseHelper::error('Gagal memperbarui data', 500);
        }
    }

    public function toggle(Request $request, $id)
    {
        try {
            $realId = Hashids::decode($id)[0] ?? null;
            $aktif = $request->boolean('aktif');
            $terpengaruh = DB::table('N_WEB_CAREERS_Master_Jenis_Tes')->where('Id_Master_Jenis_Tes', $realId)->update([
                'Flag_Aktif' => $aktif ? 'Y' : 'N',
                'Updated_At' => now(), 'Updated_By' => session('career_auth.nama', 'ADMIN'), 'Updated_By_Id' => session('career_auth.id'),
            ]);
            if (! $terpengaruh) {
                return ResponseHelper::error('Data tidak ditemukan', 404);
            }
            Log::channel('web_career')->info("Master jenis tes #{$realId} status " . ($aktif ? 'AKTIF' : 'NONAKTIF'));

            return ResponseHelper::success(null, 'Status diperbarui');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal toggle jenis tes #{$id}: " . $e->getMessage());

            return ResponseHelper::error('Gagal mengubah status', 500);
        }
    }

    public function destroy($id)
    {
        try {
            $realId = Hashids::decode($id)[0] ?? null;
            $row = DB::table('N_WEB_CAREERS_Master_Jenis_Tes')->where('Id_Master_Jenis_Tes', $realId)->first();
            if (! $row) {
                return ResponseHelper::error('Data tidak ditemukan', 404);
            }
            DB::transaction(function () use ($realId) {
                DB::table('N_WEB_CAREERS_Master_Jenis_Tes_Paket')->where('Master_Jenis_Tes_Id', $realId)->delete();
                DB::table('N_WEB_CAREERS_Master_Jenis_Tes')->where('Id_Master_Jenis_Tes', $realId)->delete();
            });
            Log::channel('web_career')->info("Master jenis tes #{$realId} dihapus");

            return ResponseHelper::success(null, 'Jenis tes dihapus');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal hapus jenis tes #{$id}: " . $e->getMessage());

            return ResponseHelper::error('Gagal menghapus data', 500);
        }
    }
}
