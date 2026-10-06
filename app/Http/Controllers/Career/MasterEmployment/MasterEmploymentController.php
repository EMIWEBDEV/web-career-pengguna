<?php

namespace App\Http\Controllers\Career\MasterEmployment;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Support\CareerShell;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Vinkla\Hashids\Facades\Hashids;

/**
 * WEB CAREER — MASTER EMPLOYMENT / JENIS IKATAN KERJA (SPA + WEB, tanpa API terpisah).
 * - index()  : return Inertia (render halaman) — shell admin via CareerShell::props.
 * - list()   : data employment (Query Builder) -> ResponseHelper (dihit axios saat mount).
 * - store/update/toggle/destroy : CRUD Query Builder -> ResponseHelper, try/catch, Log channel.
 *
 * Tabel: N_WEB_CAREERS_Master_Employment (Id_Employment IDENTITY, Nama_Employment,
 * Keterangan, Flag_Aktif, Created_At/By, Updated_At/By). Kolom audit di tabel ini
 * bertipe INT (id pengguna) — beda dengan master lain yang menyimpan nama; karena
 * itu nama pembuat diambil lewat join ke N_WEB_CAREERS_Users.
 *
 * Nilai Id_Employment dipakai N_WEB_CAREERS_Detail_MPP.Employment_Type, sehingga
 * baris yang masih terpakai TIDAK boleh dihapus — nonaktifkan saja.
 */
class MasterEmploymentController extends Controller
{
    private const TABEL = 'N_WEB_CAREERS_Master_Employment';

    /** Halaman (Inertia). Data TIDAK dikirim lewat props — halaman fetch sendiri ke list(). */
    public function index()
    {
        return Inertia::render(
            'Career/admin/master-employment/masterEmployment',
            CareerShell::props('/master-employment', 'Master Employment')
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
        $base = DB::table(self::TABEL . ' as e');

        $q = trim((string) $request->query('q', ''));
        if ($q !== '' && ! in_array('q', $abaikan, true)) {
            $base->where(function ($x) use ($q) {
                $x->where('e.Nama_Employment', 'like', "%{$q}%")
                    ->orWhere('e.Keterangan', 'like', "%{$q}%");
            });
        }

        $status = strtoupper(trim((string) $request->query('status', '')));
        if (! in_array('status', $abaikan, true)) {
            if ($status === 'AKTIF') {
                $base->where('e.Flag_Aktif', 'Y');
            } elseif ($status === 'NONAKTIF') {
                $base->where('e.Flag_Aktif', '!=', 'Y');
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
        $facetAktif = (clone $statusBase)->where('e.Flag_Aktif', 'Y')->count();

        $total = (int) DB::table(self::TABEL)->count();
        $aktif = (int) DB::table(self::TABEL)->where('Flag_Aktif', 'Y')->count();

        return [
            'total' => $total,
            'aktif' => $aktif,
            'nonaktif' => $total - $aktif,
            'dipakai' => (int) DB::table('N_WEB_CAREERS_Detail_MPP')
                ->whereIn('Employment_Type', DB::table(self::TABEL)->select('Id_Employment'))
                ->count(),
            'status' => [
                'semua' => $facetSemua,
                'aktif' => $facetAktif,
                'nonaktif' => $facetSemua - $facetAktif,
            ],
        ];
    }

    /**
     * Data list Master Employment + nama pembuat/pengubah + jumlah pemakaian di MPP.
     * PAGINASI + FILTER + URUT dikerjakan server (pola Master Kampus).
     */
    public function list(Request $request)
    {
        try {
            $perPage = min(max((int) $request->query('perPage', 25), 5), 100);
            $page = max((int) $request->query('page', 1), 1);
            $sortBy = (string) $request->query('sortBy', 'nama');
            $sortDir = strtolower((string) $request->query('sortDir', 'asc')) === 'desc' ? 'desc' : 'asc';

            $base = $this->dasarFilter($request);
            $total = (clone $base)->count();

            $rows = (clone $base)
                ->leftJoin('N_WEB_CAREERS_Users as u', 'u.Id_Users', '=', 'e.Created_By')
                ->leftJoin('N_WEB_CAREERS_Users as ux', 'ux.Id_Users', '=', 'e.Updated_By')
                ->leftJoinSub($this->pemakaian(), 'p', 'p.Employment_Type', '=', 'e.Id_Employment')
                ->select(
                    'e.*',
                    'u.Nama as Pembuat',
                    'ux.Nama as Pengubah',
                    DB::raw('ISNULL(p.Jumlah, 0) as Dipakai')
                );

            if ($sortBy === 'dipakai') {
                // Nama jadi pemecah seri: tanpa itu, banyaknya nilai 0 membuat urutan
                // antarhalaman tidak stabil dan baris bisa terlihat dua kali.
                $rows->orderBy('Dipakai', $sortDir)->orderBy('e.Nama_Employment');
            } else {
                $rows->orderBy('e.Nama_Employment', $sortDir);
            }

            $rows = $rows->forPage($page, $perPage)
                ->get()
                ->map(function ($r) {
                    return [
                        'id' => Hashids::encode($r->Id_Employment), // id di-hash (raw id tidak diekspos)
                        'nama' => $r->Nama_Employment,
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
            ], 'Data employment dimuat');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('Gagal memuat employment: ' . $e->getMessage());

            return ResponseHelper::error('Gagal memuat data employment', 500);
        }
    }

    /** Tambah jenis employment. */
    public function store(Request $request)
    {
        try {
            $data = $this->validasi($request);

            if ($this->namaDipakai($data['nama'])) {
                return ResponseHelper::error('Nama employment sudah ada. Gunakan nama lain.', 422);
            }

            $userId = session('career_auth.id');
            DB::table(self::TABEL)->insert([
                'Nama_Employment' => $data['nama'],
                'Keterangan' => $data['keterangan'],
                'Flag_Aktif' => 'Y',
                'Created_At' => now(),
                'Created_By' => $userId,
            ]);

            Log::channel('web_career')->info("Master employment dibuat ({$data['nama']}) oleh " . session('career_auth.nama', 'ADMIN'));

            return ResponseHelper::success(null, 'Jenis employment berhasil ditambahkan', 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ResponseHelper::error(collect($e->errors())->flatten()->first() ?? 'Data tidak valid', 422);
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('Gagal membuat employment: ' . $e->getMessage());

            return ResponseHelper::error('Gagal menyimpan data', 500);
        }
    }

    /** Ubah jenis employment. */
    public function update(Request $request, $id)
    {
        try {
            $realId = $this->realId($id);
            if (!$realId || !DB::table(self::TABEL)->where('Id_Employment', $realId)->exists()) {
                return ResponseHelper::error('Data tidak ditemukan', 404);
            }

            $data = $this->validasi($request);

            if ($this->namaDipakai($data['nama'], $realId)) {
                return ResponseHelper::error('Nama employment sudah ada. Gunakan nama lain.', 422);
            }

            DB::table(self::TABEL)
                ->where('Id_Employment', $realId)
                ->update([
                    'Nama_Employment' => $data['nama'],
                    'Keterangan' => $data['keterangan'],
                    'Updated_At' => now(),
                    'Updated_By' => session('career_auth.id'),
                ]);

            Log::channel('web_career')->info("Master employment #{$id} diperbarui");

            return ResponseHelper::success(null, 'Jenis employment diperbarui');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ResponseHelper::error(collect($e->errors())->flatten()->first() ?? 'Data tidak valid', 422);
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal update employment #{$id}: " . $e->getMessage());

            return ResponseHelper::error('Gagal memperbarui data', 500);
        }
    }

    /**
     * Aktif/Nonaktif jenis employment.
     * Menonaktifkan tetap diizinkan walau masih dipakai MPP lama: data historis
     * tidak berubah, jenis ini hanya berhenti muncul sebagai pilihan baru.
     */
    public function toggle(Request $request, $id)
    {
        try {
            $realId = $this->realId($id);
            $aktif = $request->boolean('aktif');

            $terpengaruh = DB::table(self::TABEL)
                ->where('Id_Employment', $realId)
                ->update([
                    'Flag_Aktif' => $aktif ? 'Y' : 'N',
                    'Updated_At' => now(),
                    'Updated_By' => session('career_auth.id'),
                ]);

            if (!$terpengaruh) {
                return ResponseHelper::error('Data tidak ditemukan', 404);
            }

            Log::channel('web_career')->info("Master employment #{$id} status " . ($aktif ? 'AKTIF' : 'NONAKTIF'));

            return ResponseHelper::success(null, 'Status diperbarui');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal toggle employment #{$id}: " . $e->getMessage());

            return ResponseHelper::error('Gagal mengubah status', 500);
        }
    }

    /**
     * Hapus jenis employment.
     * Ditolak bila masih dirujuk Detail MPP — menghapusnya membuat MPP lama
     * kehilangan keterangan ikatan kerjanya tanpa jejak.
     */
    public function destroy($id)
    {
        try {
            $realId = $this->realId($id);
            $row = DB::table(self::TABEL)->where('Id_Employment', $realId)->first();
            if (!$row) {
                return ResponseHelper::error('Data tidak ditemukan', 404);
            }

            $dipakai = (int) DB::table('N_WEB_CAREERS_Detail_MPP')->where('Employment_Type', $realId)->count();
            if ($dipakai > 0) {
                return ResponseHelper::error("Tidak bisa dihapus: masih dipakai {$dipakai} data MPP. Nonaktifkan saja.", 422);
            }

            DB::table(self::TABEL)->where('Id_Employment', $realId)->delete();

            Log::channel('web_career')->info("Master employment #{$id} dihapus");

            return ResponseHelper::success(null, 'Jenis employment dihapus');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal hapus employment #{$id}: " . $e->getMessage());

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
            'nama.required' => 'Nama employment wajib diisi.',
            'nama.max' => 'Nama employment maksimal 100 karakter.',
            'keterangan.max' => 'Keterangan maksimal 100 karakter.',
        ]);

        $keterangan = trim((string) ($data['keterangan'] ?? ''));

        return [
            'nama' => trim($data['nama']),
            'keterangan' => $keterangan === '' ? null : $keterangan,
        ];
    }

    /** Jumlah pemakaian tiap employment di Detail MPP (subquery untuk join). */
    private function pemakaian()
    {
        return DB::table('N_WEB_CAREERS_Detail_MPP')
            ->select('Employment_Type', DB::raw('COUNT(*) as Jumlah'))
            ->whereNotNull('Employment_Type')
            ->groupBy('Employment_Type');
    }

    /** Nama harus unik — dicek tanpa memandang besar/kecil huruf. */
    private function namaDipakai(string $nama, ?int $kecualiId = null): bool
    {
        return DB::table(self::TABEL)
            ->whereRaw('LOWER(Nama_Employment) = ?', [mb_strtolower($nama)])
            ->when($kecualiId, fn ($q) => $q->where('Id_Employment', '<>', $kecualiId))
            ->exists();
    }

    /** Id asli dari hash pada URL. */
    private function realId($id): ?int
    {
        return Hashids::decode($id)[0] ?? null;
    }
}
