<?php

namespace App\Http\Controllers\Career\MasterExperienceLevel;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Support\CareerShell;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Vinkla\Hashids\Facades\Hashids;

/**
 * WEB CAREER — MASTER EXPERIENCE LEVEL / TINGKAT PENGALAMAN (SPA + WEB, tanpa API terpisah).
 * - index()  : return Inertia (render halaman) — shell admin via CareerShell::props.
 * - list()   : data experience level (Query Builder) -> ResponseHelper (dihit axios saat mount).
 * - store/update/toggle/destroy : CRUD Query Builder -> ResponseHelper, try/catch, Log channel.
 *
 * Tabel: N_WEB_CAREERS_Master_Experience_Level (Id_Experience_Level IDENTITY,
 * Nama_Experience_Level, Keterangan, Flag_Aktif, Created_At/By, Updated_At/By).
 * Kolom audit di tabel ini bertipe INT (id pengguna) — nama pembuat diambil
 * lewat join ke N_WEB_CAREERS_Users. Sama persis polanya dengan Master Employment.
 *
 * Nilai Id_Experience_Level dipakai N_WEB_CAREERS_Detail_MPP.Experience_Level,
 * sehingga baris yang masih terpakai TIDAK boleh dihapus — nonaktifkan saja.
 */
class MasterExperienceLevelController extends Controller
{
    private const TABEL = 'N_WEB_CAREERS_Master_Experience_Level';

    /** Halaman (Inertia). Data TIDAK dikirim lewat props — halaman fetch sendiri ke list(). */
    public function index()
    {
        return Inertia::render(
            'Career/admin/master-experience-level/masterExperienceLevel',
            CareerShell::props('/master-experience-level', 'Master Experience Level')
        );
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
        $base = DB::table(self::TABEL . ' as x');

        $q = trim((string) $request->query('q', ''));
        if ($q !== '' && ! in_array('q', $abaikan, true)) {
            $base->where(function ($w) use ($q) {
                $w->where('x.Nama_Experience_Level', 'like', "%{$q}%")
                    ->orWhere('x.Keterangan', 'like', "%{$q}%");
            });
        }

        $status = strtoupper(trim((string) $request->query('status', '')));
        if (! in_array('status', $abaikan, true)) {
            if ($status === 'AKTIF') {
                $base->where('x.Flag_Aktif', 'Y');
            } elseif ($status === 'NONAKTIF') {
                $base->where('x.Flag_Aktif', '!=', 'Y');
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
        $facetAktif = (clone $statusBase)->where('x.Flag_Aktif', 'Y')->count();

        $total = (int) DB::table(self::TABEL)->count();
        $aktif = (int) DB::table(self::TABEL)->where('Flag_Aktif', 'Y')->count();

        return [
            'total' => $total,
            'aktif' => $aktif,
            'nonaktif' => $total - $aktif,
            'dipakai' => (int) DB::table('N_WEB_CAREERS_Detail_MPP')
                ->whereIn('Experience_Level', DB::table(self::TABEL)->select('Id_Experience_Level'))
                ->count(),
            'status' => [
                'semua' => $facetSemua,
                'aktif' => $facetAktif,
                'nonaktif' => $facetSemua - $facetAktif,
            ],
        ];
    }

    /**
     * Data list Master Experience Level + nama pembuat/pengubah + jumlah pemakaian di MPP.
     * PAGINASI + FILTER + URUT dikerjakan server (pola Master Kampus).
     */
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
                ->leftJoin('N_WEB_CAREERS_Users as u', 'u.Id_Users', '=', 'x.Created_By')
                ->leftJoin('N_WEB_CAREERS_Users as ux', 'ux.Id_Users', '=', 'x.Updated_By')
                ->leftJoinSub($this->pemakaian(), 'p', 'p.Experience_Level', '=', 'x.Id_Experience_Level')
                ->select(
                    'x.*',
                    'u.Nama as Pembuat',
                    'ux.Nama as Pengubah',
                    DB::raw('ISNULL(p.Jumlah, 0) as Dipakai')
                );

            // Id jadi pemecah seri di semua mode: data ini BERJENJANG (fresh graduate
            // → senior), dan Id menyimpan jenjang itu. Tanpa pemecah seri, nilai
            // Dipakai yang banyak bernilai 0 membuat urutan antarhalaman tidak
            // stabil dan satu baris bisa terlihat di dua halaman.
            if ($sortBy === 'dipakai') {
                $rows->orderBy('Dipakai', $sortDir)->orderBy('x.Id_Experience_Level');
            } elseif ($sortBy === 'nama') {
                $rows->orderBy('x.Nama_Experience_Level', $sortDir)->orderBy('x.Id_Experience_Level');
            } else {
                $rows->orderBy('x.Id_Experience_Level', $sortDir);
            }

            $rows = $rows->forPage($page, $perPage)
                ->get()
                ->map(function ($r) {
                    return [
                        'id' => Hashids::encode($r->Id_Experience_Level), // id di-hash (raw id tidak diekspos)
                        'nama' => $r->Nama_Experience_Level,
                        'keterangan' => $r->Keterangan,
                        'dipakai' => (int) $r->Dipakai,
                        'status' => $r->Flag_Aktif === 'Y' ? 'AKTIF' : 'NONAKTIF',
                        'createdBy' => $r->Pembuat,
                        'createdAt' => $r->Created_At,
                        'updatedBy' => $r->Pengubah,
                        'updatedAt' => $r->Updated_At,
                    ];
                })
                ->values();

            return ResponseHelper::success([
                'rows' => $rows,
                'total' => $total,
                'page' => $page,
                'perPage' => $perPage,
                'ringkasan' => $this->ringkasan($request),
            ], 'Data experience level dimuat');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('Gagal memuat experience level: ' . $e->getMessage());

            return ResponseHelper::error('Gagal memuat data experience level', 500);
        }
    }

    /** Tambah tingkat pengalaman. */
    public function store(Request $request)
    {
        try {
            $data = $this->validasi($request);

            if ($this->namaDipakai($data['nama'])) {
                return ResponseHelper::error('Nama experience level sudah ada. Gunakan nama lain.', 422);
            }

            $userId = session('career_auth.id');
            DB::table(self::TABEL)->insert([
                'Nama_Experience_Level' => $data['nama'],
                'Keterangan' => $data['keterangan'],
                'Flag_Aktif' => 'Y',
                'Created_At' => now(),
                'Created_By' => $userId,
            ]);

            Log::channel('web_career')->info("Master experience level dibuat ({$data['nama']}) oleh " . session('career_auth.nama', 'ADMIN'));

            return ResponseHelper::success(null, 'Tingkat pengalaman berhasil ditambahkan', 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ResponseHelper::error(collect($e->errors())->flatten()->first() ?? 'Data tidak valid', 422);
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('Gagal membuat experience level: ' . $e->getMessage());

            return ResponseHelper::error('Gagal menyimpan data', 500);
        }
    }

    /** Ubah tingkat pengalaman. */
    public function update(Request $request, $id)
    {
        try {
            $realId = $this->realId($id);
            if (!$realId || !DB::table(self::TABEL)->where('Id_Experience_Level', $realId)->exists()) {
                return ResponseHelper::error('Data tidak ditemukan', 404);
            }

            $data = $this->validasi($request);

            if ($this->namaDipakai($data['nama'], $realId)) {
                return ResponseHelper::error('Nama experience level sudah ada. Gunakan nama lain.', 422);
            }

            DB::table(self::TABEL)
                ->where('Id_Experience_Level', $realId)
                ->update([
                    'Nama_Experience_Level' => $data['nama'],
                    'Keterangan' => $data['keterangan'],
                    'Updated_At' => now(),
                    'Updated_By' => session('career_auth.id'),
                ]);

            Log::channel('web_career')->info("Master experience level #{$id} diperbarui");

            return ResponseHelper::success(null, 'Tingkat pengalaman diperbarui');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ResponseHelper::error(collect($e->errors())->flatten()->first() ?? 'Data tidak valid', 422);
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal update experience level #{$id}: " . $e->getMessage());

            return ResponseHelper::error('Gagal memperbarui data', 500);
        }
    }

    /**
     * Aktif/Nonaktif tingkat pengalaman.
     * Menonaktifkan tetap diizinkan walau masih dipakai MPP lama: data historis
     * tidak berubah, level ini hanya berhenti muncul sebagai pilihan baru.
     */
    public function toggle(Request $request, $id)
    {
        try {
            $realId = $this->realId($id);
            $aktif = $request->boolean('aktif');

            $terpengaruh = DB::table(self::TABEL)
                ->where('Id_Experience_Level', $realId)
                ->update([
                    'Flag_Aktif' => $aktif ? 'Y' : 'N',
                    'Updated_At' => now(),
                    'Updated_By' => session('career_auth.id'),
                ]);

            if (!$terpengaruh) {
                return ResponseHelper::error('Data tidak ditemukan', 404);
            }

            Log::channel('web_career')->info("Master experience level #{$id} status " . ($aktif ? 'AKTIF' : 'NONAKTIF'));

            return ResponseHelper::success(null, 'Status diperbarui');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal toggle experience level #{$id}: " . $e->getMessage());

            return ResponseHelper::error('Gagal mengubah status', 500);
        }
    }

    /**
     * Hapus tingkat pengalaman.
     * Ditolak bila masih dirujuk Detail MPP — menghapusnya membuat MPP lama
     * kehilangan keterangan tingkat pengalaman tanpa jejak.
     */
    public function destroy($id)
    {
        try {
            $realId = $this->realId($id);
            $row = DB::table(self::TABEL)->where('Id_Experience_Level', $realId)->first();
            if (!$row) {
                return ResponseHelper::error('Data tidak ditemukan', 404);
            }

            $dipakai = (int) DB::table('N_WEB_CAREERS_Detail_MPP')->where('Experience_Level', $realId)->count();
            if ($dipakai > 0) {
                return ResponseHelper::error("Tidak bisa dihapus: masih dipakai {$dipakai} data MPP. Nonaktifkan saja.", 422);
            }

            DB::table(self::TABEL)->where('Id_Experience_Level', $realId)->delete();

            Log::channel('web_career')->info("Master experience level #{$id} dihapus");

            return ResponseHelper::success(null, 'Tingkat pengalaman dihapus');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal hapus experience level #{$id}: " . $e->getMessage());

            return ResponseHelper::error('Gagal menghapus data', 500);
        }
    }

    /** Aturan borang — batas panjang mengikuti lebar kolom tabel (100/100). */
    private function validasi(Request $request): array
    {
        $data = $request->validate([
            'nama' => 'required|string|max:100',
            'keterangan' => 'nullable|string|max:100',
        ], [
            'nama.required' => 'Nama experience level wajib diisi.',
            'nama.max' => 'Nama experience level maksimal 100 karakter.',
            'keterangan.max' => 'Keterangan maksimal 100 karakter.',
        ]);

        $keterangan = trim((string) ($data['keterangan'] ?? ''));

        return [
            'nama' => trim($data['nama']),
            'keterangan' => $keterangan === '' ? null : $keterangan,
        ];
    }

    /** Jumlah pemakaian tiap experience level di Detail MPP (subquery untuk join). */
    private function pemakaian()
    {
        return DB::table('N_WEB_CAREERS_Detail_MPP')
            ->select('Experience_Level', DB::raw('COUNT(*) as Jumlah'))
            ->whereNotNull('Experience_Level')
            ->groupBy('Experience_Level');
    }

    /** Nama harus unik — dicek tanpa memandang besar/kecil huruf. */
    private function namaDipakai(string $nama, ?int $kecualiId = null): bool
    {
        return DB::table(self::TABEL)
            ->whereRaw('LOWER(Nama_Experience_Level) = ?', [mb_strtolower($nama)])
            ->when($kecualiId, fn ($q) => $q->where('Id_Experience_Level', '<>', $kecualiId))
            ->exists();
    }

    /** Id asli dari hash pada URL. */
    private function realId($id): ?int
    {
        return Hashids::decode($id)[0] ?? null;
    }
}
