<?php

namespace App\Http\Controllers\Career\MasterKampus;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Support\Career\KodeUnik;
use App\Support\Career\PencarianCepat;
use App\Support\CareerShell;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Vinkla\Hashids\Facades\Hashids;

/**
 * WEB CAREER — MASTER KAMPUS / INSTITUSI PENDIDIKAN.
 *
 * Tabel N_WEB_CAREERS_Master_Kampus kini memuat ratusan ribu baris (PT + sekolah
 * hasil impor), jadi list() WAJIB paginasi + filter server-side — TIDAK boleh
 * lagi menarik seluruh baris (dulu bikin SELECT * berjam-jam / macet).
 * Kolom binding: Jenis_Institusi_Kode, Jenjang_Kode + metadata (Kepemilikan,
 * Provinsi, Negara, NPSN, Kode_PT, Website, Sumber).
 */
class MasterKampusController extends Controller
{
    private const TABEL = 'N_WEB_CAREERS_Master_Kampus';

    public function index()
    {
        return Inertia::render('Career/admin/master-kampus/masterKampus', CareerShell::props('/master-kampus', 'Master Kampus'));
    }

    /**
     * Filter selain pencarian nama — dipisah karena query-nya perlu dibangun
     * ulang bila jalur pencarian cepat tidak menemukan apa pun.
     */
    private function dasarFilter(Request $request)
    {
        $base = DB::table(self::TABEL);

        foreach ([
            'jenis' => 'Jenis_Institusi_Kode',
            'jenjang' => 'Jenjang_Kode',
            'kepemilikan' => 'Kepemilikan',
            'negara' => 'Negara',
        ] as $param => $kolom) {
            $nilai = trim((string) $request->query($param, ''));
            if ($nilai !== '') {
                $base->where($kolom, $nilai);
            }
        }

        $status = trim((string) $request->query('status', ''));
        if ($status === 'AKTIF') {
            $base->where('Flag_Aktif', 'Y');
        } elseif ($status === 'NONAKTIF') {
            $base->where('Flag_Aktif', 'N');
        }

        return $base;
    }

    /** Data kampus — PAGINASI + FILTER server-side (indeks IX_MK_Cascade / IX_MK_Nama). */
    public function list(Request $request)
    {
        try {
            $q = trim((string) $request->query('q', ''));
            $perPage = min(max((int) $request->query('perPage', 25), 5), 100);
            $page = max((int) $request->query('page', 1), 1);

            $base = $this->dasarFilter($request);
            // Tabel ini 328 ribu baris. `LIKE '%kata%'` memaksa pemindaian
            // penuh — dan halaman ini juga menghitung total, jadi ongkosnya
            // dibayar dua kali tiap ketikan. Lihat PencarianCepat.
            $pakaiFullText = PencarianCepat::terapkan($base, self::TABEL, 'Nama', $q);

            $total = (clone $base)->count();

            // Full-text hanya menimbang sebagian kandidat teratas; bila tak ada
            // hasil sama sekali, ulangi memakai pencarian lengkap.
            if ($total === 0 && $pakaiFullText && $q !== ''
                && PencarianCepat::adaKecocokan(self::TABEL, 'Nama', $q)) {
                $base = $this->dasarFilter($request);
                PencarianCepat::terapkanLuas($base, 'Nama', $q);
                $total = (clone $base)->count();
            }
            $rows = $base->orderBy('Nama')->forPage($page, $perPage)
                ->get(['Id_Master_Kampus', 'Kode', 'Nama', 'Singkatan', 'Jenis_Institusi_Kode', 'Jenjang_Kode', 'Kepemilikan', 'Kota', 'Provinsi', 'Negara', 'Akreditasi', 'Website', 'NPSN', 'Flag_Aktif', 'Created_At', 'Created_By'])
                ->map(fn ($r) => [
                    'id' => Hashids::encode($r->Id_Master_Kampus),
                    'kode' => $r->Kode,
                    'nama' => $r->Nama,
                    'singkatan' => $r->Singkatan,
                    'jenis' => $r->Jenis_Institusi_Kode,
                    'jenjang' => $r->Jenjang_Kode,
                    'kepemilikan' => $r->Kepemilikan,
                    'kota' => $r->Kota,
                    'provinsi' => $r->Provinsi,
                    'negara' => $r->Negara,
                    'akreditasi' => $r->Akreditasi,
                    'website' => $r->Website,
                    'npsn' => $r->NPSN,
                    'status' => $r->Flag_Aktif === 'Y' ? 'AKTIF' : 'NONAKTIF',
                    'createdBy' => $r->Created_By,
                    'createdAt' => $r->Created_At,
                ])->values();

            return ResponseHelper::success([
                'rows' => $rows,
                'total' => $total,
                'page' => $page,
                'perPage' => $perPage,
            ], 'Data kampus dimuat');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('Gagal memuat kampus: ' . $e->getMessage());
            return ResponseHelper::error('Gagal memuat data kampus', 500);
        }
    }

    /** Aturan validasi bersama store/update. */
    private function rules(): array
    {
        return [
            'nama' => 'required|string|max:255',
            'singkatan' => 'nullable|string|max:30',
            'jenis' => 'nullable|string|max:50',
            'jenjang' => 'nullable|string|max:20',
            'kepemilikan' => 'nullable|string|max:20',
            'kota' => 'nullable|string|max:120',
            'provinsi' => 'nullable|string|max:120',
            'negara' => 'nullable|string|max:100',
            'akreditasi' => 'nullable|string|max:30',
            'website' => 'nullable|string|max:255',
            'npsn' => 'nullable|string|max:20',
        ];
    }

    private function kolom(array $data): array
    {
        return [
            'Nama' => $data['nama'],
            'Singkatan' => $data['singkatan'] ?? null,
            'Jenis_Institusi_Kode' => $data['jenis'] ?? null,
            'Jenjang_Kode' => $data['jenjang'] ?? null,
            'Kepemilikan' => $data['kepemilikan'] ?? null,
            'Kota' => $data['kota'] ?? null,
            'Provinsi' => $data['provinsi'] ?? null,
            'Negara' => $data['negara'] ?? null,
            'Akreditasi' => $data['akreditasi'] ?? null,
            'Website' => $data['website'] ?? null,
            'NPSN' => $data['npsn'] ?? null,
        ];
    }

    public function store(Request $request)
    {
        try {
            $data = $request->validate($this->rules());
            $userId = session('career_auth.id');
            $userName = session('career_auth.nama', 'ADMIN');
            $now = now();
            $sumber = !empty($data['singkatan']) ? $data['singkatan'] : $data['nama'];
            $kode = KodeUnik::buat(self::TABEL, 'Kode', $sumber, 20, 'KAMPUS');

            DB::table(self::TABEL)->insert(array_merge($this->kolom($data), [
                'Kode' => $kode,
                'Sumber' => 'manual',
                'Flag_Aktif' => 'Y',
                'Created_At' => $now, 'Created_By' => $userName, 'Created_By_Id' => $userId,
                'Updated_At' => $now, 'Updated_By' => $userName, 'Updated_By_Id' => $userId,
            ]));

            Log::channel('web_career')->info("Master kampus dibuat ({$kode}) oleh {$userName}");
            return ResponseHelper::success(null, 'Kampus berhasil ditambahkan', 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ResponseHelper::error(collect($e->errors())->flatten()->first() ?? 'Data tidak valid', 422);
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('Gagal membuat kampus: ' . $e->getMessage());
            return ResponseHelper::error('Gagal menyimpan data', 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $realId = Hashids::decode($id)[0] ?? null;
            $row = DB::table(self::TABEL)->where('Id_Master_Kampus', $realId)->first();
            if (!$row) {
                return ResponseHelper::error('Data tidak ditemukan', 404);
            }
            $data = $request->validate($this->rules());
            DB::table(self::TABEL)->where('Id_Master_Kampus', $realId)->update(array_merge($this->kolom($data), [
                'Updated_At' => now(), 'Updated_By' => session('career_auth.nama', 'ADMIN'), 'Updated_By_Id' => session('career_auth.id'),
            ]));

            Log::channel('web_career')->info("Master kampus #{$id} diperbarui");
            return ResponseHelper::success(null, 'Kampus diperbarui');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ResponseHelper::error(collect($e->errors())->flatten()->first() ?? 'Data tidak valid', 422);
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal update kampus #{$id}: " . $e->getMessage());
            return ResponseHelper::error('Gagal memperbarui data', 500);
        }
    }

    public function toggle(Request $request, $id)
    {
        try {
            $realId = Hashids::decode($id)[0] ?? null;
            $aktif = $request->boolean('aktif');
            $terpengaruh = DB::table(self::TABEL)->where('Id_Master_Kampus', $realId)->update([
                'Flag_Aktif' => $aktif ? 'Y' : 'N',
                'Updated_At' => now(), 'Updated_By' => session('career_auth.nama', 'ADMIN'), 'Updated_By_Id' => session('career_auth.id'),
            ]);
            if (!$terpengaruh) {
                return ResponseHelper::error('Data tidak ditemukan', 404);
            }
            Log::channel('web_career')->info("Master kampus #{$id} status " . ($aktif ? 'AKTIF' : 'NONAKTIF'));
            return ResponseHelper::success(null, 'Status diperbarui');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal toggle kampus #{$id}: " . $e->getMessage());
            return ResponseHelper::error('Gagal mengubah status', 500);
        }
    }

    public function destroy($id)
    {
        try {
            $realId = Hashids::decode($id)[0] ?? null;
            $terpengaruh = DB::table(self::TABEL)->where('Id_Master_Kampus', $realId)->delete();
            if (!$terpengaruh) {
                return ResponseHelper::error('Data tidak ditemukan', 404);
            }
            Log::channel('web_career')->info("Master kampus #{$id} dihapus");
            return ResponseHelper::success(null, 'Kampus dihapus');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal hapus kampus #{$id}: " . $e->getMessage());
            return ResponseHelper::error('Gagal menghapus data', 500);
        }
    }
}
