<?php

namespace App\Http\Controllers\Career\MasterWorkplace;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Support\CareerShell;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Vinkla\Hashids\Facades\Hashids;

/**
 * WEB CAREER — MASTER WORKPLACE (tipe LOKASI kerja: On-site / Hybrid / Remote).
 *
 * Dipakai N_WEB_CAREERS_Detail_MPP.Workplace_Type → tampil di form lowongan MPP,
 * kartu lowongan landing page, dan Monitoring MPP. Karena itu baris yang sudah
 * dipakai lowongan TIDAK boleh dihapus — hanya dinonaktifkan.
 *
 * CATATAN SKEMA — tabel ini lebih tua dari master lain:
 *   - tidak punya Kode / Urutan / Ikon / Warna / Flag_Sistem;
 *   - Created_By & Updated_By bertipe INT (Id_Users), bukan varchar nama +
 *     kolom *_By_Id terpisah seperti master yang lebih baru.
 * Jadi nama pembuat diambil lewat join ke N_WEB_CAREERS_Users, dan ikon/warna
 * kartu diturunkan di sisi Vue dari nama tipe (deterministik).
 */
class MasterWorkplaceController extends Controller
{
    private string $tbl = 'N_WEB_CAREERS_Master_Workplace';

    private string $pk = 'Id_Workplace';

    public function index()
    {
        return Inertia::render('Career/admin/master-workplace/masterWorkplace', CareerShell::props('/master-workplace', 'Master Workplace'));
    }

    /** Jumlah lowongan pemakai per tipe — jadi badge di tabel SEKALIGUS alasan tombol hapus dikunci. */
    private function pemakaian()
    {
        return DB::table('N_WEB_CAREERS_Detail_MPP')
            ->select('Workplace_Type', DB::raw('COUNT(*) as Jumlah'))
            ->whereNotNull('Workplace_Type')
            ->groupBy('Workplace_Type');
    }

    /**
     * Query dasar + filter (q / status). Dipakai bersama oleh baris, penghitung
     * total, DAN penghitung cacah facet.
     *
     * $abaikan memungkinkan satu penyaring dilewati — itulah cara cacah facet
     * dihitung: cacah status dihitung dengan mengabaikan filter status. Tanpa
     * itu, angka pada tab selalu sama dengan jumlah baris yang sedang tampil
     * dan tidak memberi tahu apa pun.
     */
    private function dasarFilter(Request $request, array $abaikan = [])
    {
        $base = DB::table($this->tbl . ' as w');

        $q = trim((string) $request->query('q', ''));
        if ($q !== '' && ! in_array('q', $abaikan, true)) {
            $base->where(function ($x) use ($q) {
                $x->where('w.Nama_Workplace', 'like', "%{$q}%")
                    ->orWhere('w.Keterangan', 'like', "%{$q}%");
            });
        }

        $status = strtoupper(trim((string) $request->query('status', '')));
        if (! in_array('status', $abaikan, true)) {
            if ($status === 'AKTIF') {
                $base->where('w.Flag_Aktif', 'Y');
            } elseif ($status === 'NONAKTIF') {
                $base->where('w.Flag_Aktif', '!=', 'Y');
            }
        }

        return $base;
    }

    /**
     * Angka untuk kartu statistik & cacah facet. Dihitung di server karena klien
     * cuma memegang satu halaman — menjumlahkan baris yang tampil akan salah.
     */
    private function ringkasan(Request $request): array
    {
        $statusBase = $this->dasarFilter($request, ['status']);
        $facetSemua = (clone $statusBase)->count();
        $facetAktif = (clone $statusBase)->where('w.Flag_Aktif', 'Y')->count();

        $total = (int) DB::table($this->tbl)->count();
        $aktif = (int) DB::table($this->tbl)->where('Flag_Aktif', 'Y')->count();

        return [
            'total' => $total,
            'aktif' => $aktif,
            'nonaktif' => $total - $aktif,
            'dipakai' => (int) DB::table('N_WEB_CAREERS_Detail_MPP')
                ->whereIn('Workplace_Type', DB::table($this->tbl)->select($this->pk))
                ->count(),
            'status' => [
                'semua' => $facetSemua,
                'aktif' => $facetAktif,
                'nonaktif' => $facetSemua - $facetAktif,
            ],
        ];
    }

    /** Data tipe lokasi kerja — PAGINASI + FILTER + URUT server-side. */
    public function list(Request $request)
    {
        try {
            $perPage = min(max((int) $request->query('perPage', 25), 5), 100);
            $page = max((int) $request->query('page', 1), 1);
            $sortBy = (string) $request->query('sortBy', 'urutan');
            $sortDir = strtolower((string) $request->query('sortDir', 'asc')) === 'desc' ? 'desc' : 'asc';

            $base = $this->dasarFilter($request);
            $total = (clone $base)->count();

            $rows = (clone $base)
                ->leftJoin('N_WEB_CAREERS_Users as uc', 'uc.Id_Users', '=', 'w.Created_By')
                ->leftJoin('N_WEB_CAREERS_Users as uu', 'uu.Id_Users', '=', 'w.Updated_By')
                ->leftJoinSub($this->pemakaian(), 'p', 'p.Workplace_Type', '=', 'w.Id_Workplace')
                ->select('w.*', 'uc.Nama as Pembuat', 'uu.Nama as Pengubah', DB::raw('ISNULL(p.Jumlah, 0) as Dipakai'));

            if ($sortBy === 'dipakai') {
                // Id jadi pemecah seri: tanpa itu, banyaknya nilai 0 membuat urutan
                // antarhalaman tidak stabil dan baris bisa terlihat dua kali.
                $rows->orderBy('Dipakai', $sortDir)->orderBy('w.' . $this->pk);
            } else {
                // Urut menurut Id, bukan abjad: urutan seed sudah berjenjang dari
                // paling "di kantor" ke paling "jauh dari kantor" (WFO → Hybrid → WFH),
                // dan itu urutan yang diharapkan muncul di pilihan MPP.
                $rows->orderBy('w.' . $this->pk);
            }

            $rows = $rows->forPage($page, $perPage)
                ->get()
                ->map(fn ($r) => [
                    'id' => Hashids::encode($r->Id_Workplace),
                    'nama' => $r->Nama_Workplace,
                    'keterangan' => $r->Keterangan,
                    'status' => $r->Flag_Aktif === 'Y' ? 'AKTIF' : 'NONAKTIF',
                    'dipakai' => (int) $r->Dipakai,
                    'createdBy' => $r->Pembuat,
                    'createdAt' => $r->Created_At,
                    'updatedBy' => $r->Pengubah,
                    'updatedAt' => $r->Updated_At,
                ])
                ->values();

            return ResponseHelper::success([
                'rows' => $rows,
                'total' => $total,
                'page' => $page,
                'perPage' => $perPage,
                'ringkasan' => $this->ringkasan($request),
            ], 'Data tipe lokasi kerja dimuat');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('Gagal memuat master workplace: ' . $e->getMessage());

            return ResponseHelper::error('Gagal memuat data tipe lokasi kerja', 500);
        }
    }

    /** Batas panjang mengikuti lebar kolom asli (varchar 100) — bukan angka karangan. */
    private function rules(): array
    {
        return [
            'nama' => 'required|string|max:100',
            'keterangan' => 'nullable|string|max:100',
        ];
    }

    /**
     * Nama tipe harus unik. Collation SQL Server di sini case-insensitive, tapi
     * LOWER+TRIM dipakai eksplisit supaya "hybrid " dan "Hybrid" tetap tertangkap
     * seandainya database dipindah ke collation case-sensitive.
     */
    private function namaBentrok(string $nama, ?int $kecualiId = null): bool
    {
        return DB::table($this->tbl)
            ->whereRaw('LOWER(LTRIM(RTRIM(Nama_Workplace))) = ?', [mb_strtolower(trim($nama))])
            ->when($kecualiId, fn ($q) => $q->where($this->pk, '!=', $kecualiId))
            ->exists();
    }

    /** Jumlah lowongan yang memakai tipe ini. */
    private function jumlahPemakai(int $id): int
    {
        return DB::table('N_WEB_CAREERS_Detail_MPP')->where('Workplace_Type', $id)->count();
    }

    public function store(Request $request)
    {
        try {
            $data = $request->validate($this->rules());

            if ($this->namaBentrok($data['nama'])) {
                return ResponseHelper::error('Nama tipe lokasi kerja sudah dipakai.', 422);
            }

            $userId = session('career_auth.id');
            $userName = session('career_auth.nama', 'ADMIN');
            $now = now();

            DB::table($this->tbl)->insert([
                'Nama_Workplace' => trim($data['nama']),
                'Keterangan' => $data['keterangan'] ? trim($data['keterangan']) : null,
                'Flag_Aktif' => 'Y',
                'Created_At' => $now, 'Created_By' => $userId,
                'Updated_At' => $now, 'Updated_By' => $userId,
            ]);

            Log::channel('web_career')->info("Master workplace dibuat ({$data['nama']}) oleh {$userName}");

            return ResponseHelper::success(null, 'Tipe lokasi kerja berhasil ditambahkan', 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ResponseHelper::error(collect($e->errors())->flatten()->first() ?? 'Data tidak valid', 422);
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('Gagal membuat master workplace: ' . $e->getMessage());

            return ResponseHelper::error('Gagal menyimpan data', 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $realId = Hashids::decode($id)[0] ?? null;
            $row = DB::table($this->tbl)->where($this->pk, $realId)->first();
            if (! $row) {
                return ResponseHelper::error('Data tidak ditemukan', 404);
            }

            $data = $request->validate($this->rules());

            if ($this->namaBentrok($data['nama'], (int) $realId)) {
                return ResponseHelper::error('Nama tipe lokasi kerja sudah dipakai.', 422);
            }

            DB::table($this->tbl)->where($this->pk, $realId)->update([
                'Nama_Workplace' => trim($data['nama']),
                'Keterangan' => $data['keterangan'] ? trim($data['keterangan']) : null,
                'Updated_At' => now(),
                'Updated_By' => session('career_auth.id'),
            ]);

            Log::channel('web_career')->info("Master workplace #{$id} diperbarui");

            return ResponseHelper::success(null, 'Tipe lokasi kerja diperbarui');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ResponseHelper::error(collect($e->errors())->flatten()->first() ?? 'Data tidak valid', 422);
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal update master workplace #{$id}: " . $e->getMessage());

            return ResponseHelper::error('Gagal memperbarui data', 500);
        }
    }

    /**
     * Nonaktifkan / aktifkan. Tipe nonaktif hilang dari pilihan lowongan BARU,
     * tapi lowongan lama yang sudah memakainya tetap utuh — inilah jalan keluar
     * yang benar untuk tipe yang tidak boleh dihapus.
     */
    public function toggle(Request $request, $id)
    {
        try {
            $realId = Hashids::decode($id)[0] ?? null;
            $aktif = $request->boolean('aktif');

            $terpengaruh = DB::table($this->tbl)->where($this->pk, $realId)->update([
                'Flag_Aktif' => $aktif ? 'Y' : 'N',
                'Updated_At' => now(),
                'Updated_By' => session('career_auth.id'),
            ]);

            if (! $terpengaruh) {
                return ResponseHelper::error('Data tidak ditemukan', 404);
            }

            Log::channel('web_career')->info("Master workplace #{$id} status " . ($aktif ? 'AKTIF' : 'NONAKTIF'));

            return ResponseHelper::success(null, 'Status diperbarui');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal toggle master workplace #{$id}: " . $e->getMessage());

            return ResponseHelper::error('Gagal mengubah status', 500);
        }
    }

    public function destroy($id)
    {
        try {
            $realId = Hashids::decode($id)[0] ?? null;
            $row = DB::table($this->tbl)->where($this->pk, $realId)->first();
            if (! $row) {
                return ResponseHelper::error('Data tidak ditemukan', 404);
            }

            // Penjaga terakhir: UI sudah menonaktifkan tombolnya, tapi permintaan
            // bisa datang dari mana saja — hitung ulang di sini, jangan percaya klien.
            $dipakai = $this->jumlahPemakai((int) $realId);
            if ($dipakai > 0) {
                return ResponseHelper::error("Tipe ini dipakai {$dipakai} lowongan — nonaktifkan saja.", 422);
            }

            DB::table($this->tbl)->where($this->pk, $realId)->delete();
            Log::channel('web_career')->info("Master workplace #{$id} dihapus");

            return ResponseHelper::success(null, 'Tipe lokasi kerja dihapus');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal hapus master workplace #{$id}: " . $e->getMessage());

            return ResponseHelper::error('Gagal menghapus data', 500);
        }
    }
}
