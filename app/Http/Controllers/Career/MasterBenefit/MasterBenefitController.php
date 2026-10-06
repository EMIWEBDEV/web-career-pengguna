<?php

namespace App\Http\Controllers\Career\MasterBenefit;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Support\CareerShell;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Vinkla\Hashids\Facades\Hashids;

/**
 * WEB CAREER — MASTER BENEFIT / FASILITAS & TUNJANGAN (SPA + WEB, tanpa API terpisah).
 * - index()  : return Inertia (render halaman) — shell admin via CareerShell::props.
 * - list()   : data benefit (Query Builder) -> ResponseHelper, PAGINASI server-side.
 * - store/update/toggle/destroy : CRUD Query Builder -> ResponseHelper, try/catch, Log channel.
 *
 * Tabel: N_WEB_CAREERS_Master_Benefit (Id_Benefit IDENTITY, Nama_Benefit,
 * Keterangan, Flag_Aktif, Created_At/By, Updated_At/By). Kolom audit bertipe INT
 * (id pengguna), jadi nama pembuat diambil lewat join ke N_WEB_CAREERS_Users —
 * sama persis polanya dengan Master Employment / Experience Level.
 *
 * PEMAKAIAN lewat tabel JEMBATAN N_WEB_CAREERS_Detail_Benefit_MPP
 * (Id_Detail_MPP × Id_Benefit, relasi banyak-ke-banyak dengan Detail MPP).
 * Karena itu "dipakai" di sini berarti JUMLAH LOWONGAN yang memasang benefit ini,
 * dan baris yang masih terpasang TIDAK boleh dihapus — nonaktifkan saja.
 */
class MasterBenefitController extends Controller
{
    private const TABEL = 'N_WEB_CAREERS_Master_Benefit';

    private const JEMBATAN = 'N_WEB_CAREERS_Detail_Benefit_MPP';

    /** Halaman (Inertia). Data TIDAK dikirim lewat props — halaman fetch sendiri ke list(). */
    public function index()
    {
        return Inertia::render(
            'Career/admin/master-benefit/masterBenefit',
            CareerShell::props('/master-benefit', 'Master Benefit')
        );
    }

    /** Jumlah lowongan yang memasang tiap benefit — badge di tabel SEKALIGUS alasan hapus dikunci. */
    private function pemakaian()
    {
        return DB::table(self::JEMBATAN)
            ->select('Id_Benefit', DB::raw('COUNT(*) as Jumlah'))
            ->groupBy('Id_Benefit');
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
        $base = DB::table(self::TABEL . ' as b');

        $q = trim((string) $request->query('q', ''));
        if ($q !== '' && ! in_array('q', $abaikan, true)) {
            $base->where(function ($w) use ($q) {
                $w->where('b.Nama_Benefit', 'like', "%{$q}%")
                    ->orWhere('b.Keterangan', 'like', "%{$q}%");
            });
        }

        $status = strtoupper(trim((string) $request->query('status', '')));
        if (! in_array('status', $abaikan, true)) {
            if ($status === 'AKTIF') {
                $base->where('b.Flag_Aktif', 'Y');
            } elseif ($status === 'NONAKTIF') {
                $base->where('b.Flag_Aktif', '!=', 'Y');
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
        $facetAktif = (clone $statusBase)->where('b.Flag_Aktif', 'Y')->count();

        $total = (int) DB::table(self::TABEL)->count();
        $aktif = (int) DB::table(self::TABEL)->where('Flag_Aktif', 'Y')->count();

        // Benefit yang belum pernah dipasang lowongan mana pun — penanda kandidat
        // untuk dirapikan, karena daftar ini tumbuh dari isian bebas form MPP.
        $terpasang = (int) DB::table(self::JEMBATAN)->distinct()->count('Id_Benefit');

        return [
            'total' => $total,
            'aktif' => $aktif,
            'nonaktif' => $total - $aktif,
            'dipakai' => (int) DB::table(self::JEMBATAN)->count(),
            'belumDipakai' => $total - $terpasang,
            'status' => [
                'semua' => $facetSemua,
                'aktif' => $facetAktif,
                'nonaktif' => $facetSemua - $facetAktif,
            ],
        ];
    }

    /**
     * Data list Master Benefit + nama pembuat/pengubah + jumlah pemasangan di MPP.
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
                ->leftJoin('N_WEB_CAREERS_Users as u', 'u.Id_Users', '=', 'b.Created_By')
                ->leftJoin('N_WEB_CAREERS_Users as ux', 'ux.Id_Users', '=', 'b.Updated_By')
                ->leftJoinSub($this->pemakaian(), 'p', 'p.Id_Benefit', '=', 'b.Id_Benefit')
                ->select(
                    'b.*',
                    'u.Nama as Pembuat',
                    'ux.Nama as Pengubah',
                    DB::raw('ISNULL(p.Jumlah, 0) as Dipakai')
                );

            // Nama jadi pemecah seri: banyak baris bernilai Dipakai = 0, dan tanpa
            // pemecah seri urutan antarhalaman tidak stabil di SQL Server —
            // satu baris bisa terlihat di dua halaman.
            if ($sortBy === 'dipakai') {
                $rows->orderBy('Dipakai', $sortDir)->orderBy('b.Nama_Benefit');
            } elseif ($sortBy === 'baru') {
                $rows->orderBy('b.Created_At', $sortDir)->orderBy('b.Nama_Benefit');
            } else {
                $rows->orderBy('b.Nama_Benefit', $sortDir);
            }

            $rows = $rows->forPage($page, $perPage)
                ->get()
                ->map(function ($r) {
                    return [
                        'id' => Hashids::encode($r->Id_Benefit), // id di-hash (raw id tidak diekspos)
                        'nama' => $r->Nama_Benefit,
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
            ], 'Data benefit dimuat');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('Gagal memuat benefit: ' . $e->getMessage());

            return ResponseHelper::error('Gagal memuat data benefit', 500);
        }
    }

    /** Batas panjang mengikuti lebar kolom asli (varchar 100) — bukan angka karangan. */
    private function validasi(Request $request): array
    {
        $data = $request->validate([
            'nama' => 'required|string|max:100',
            'keterangan' => 'nullable|string|max:100',
        ]);

        return [
            'nama' => trim($data['nama']),
            'keterangan' => isset($data['keterangan']) && trim($data['keterangan']) !== ''
                ? trim($data['keterangan'])
                : null,
        ];
    }

    /**
     * Nama harus unik tanpa memandang besar/kecil huruf.
     * Penjagaan ini penting di sini: daftar benefit tumbuh dari isian bebas form
     * MPP, sehingga sudah terlanjur berisi pasangan nyaris kembar
     * ("Jenjang karir" vs "Jenjang karier"). Yang lama tidak diutak-atik, tapi
     * yang baru tidak boleh menambah kekacauan yang sama.
     */
    private function namaDipakai(string $nama, ?int $kecualiId = null): bool
    {
        return DB::table(self::TABEL)
            ->whereRaw('LOWER(LTRIM(RTRIM(Nama_Benefit))) = ?', [mb_strtolower(trim($nama))])
            ->when($kecualiId, fn ($q) => $q->where('Id_Benefit', '!=', $kecualiId))
            ->exists();
    }

    private function realId($hash): ?int
    {
        return Hashids::decode($hash)[0] ?? null;
    }

    /** Jumlah lowongan yang memasang benefit ini. */
    private function jumlahPemasangan(int $id): int
    {
        return DB::table(self::JEMBATAN)->where('Id_Benefit', $id)->count();
    }

    /** Tambah benefit. */
    public function store(Request $request)
    {
        try {
            $data = $this->validasi($request);

            if ($this->namaDipakai($data['nama'])) {
                return ResponseHelper::error('Nama benefit sudah ada. Gunakan nama lain.', 422);
            }

            DB::table(self::TABEL)->insert([
                'Nama_Benefit' => $data['nama'],
                'Keterangan' => $data['keterangan'],
                'Flag_Aktif' => 'Y',
                'Created_At' => now(),
                'Created_By' => session('career_auth.id'),
            ]);

            Log::channel('web_career')->info("Master benefit dibuat ({$data['nama']}) oleh " . session('career_auth.nama', 'ADMIN'));

            return ResponseHelper::success(null, 'Benefit berhasil ditambahkan', 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ResponseHelper::error(collect($e->errors())->flatten()->first() ?? 'Data tidak valid', 422);
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('Gagal membuat benefit: ' . $e->getMessage());

            return ResponseHelper::error('Gagal menyimpan data', 500);
        }
    }

    /** Ubah benefit. */
    public function update(Request $request, $id)
    {
        try {
            $realId = $this->realId($id);
            if (! $realId || ! DB::table(self::TABEL)->where('Id_Benefit', $realId)->exists()) {
                return ResponseHelper::error('Data tidak ditemukan', 404);
            }

            $data = $this->validasi($request);

            if ($this->namaDipakai($data['nama'], $realId)) {
                return ResponseHelper::error('Nama benefit sudah ada. Gunakan nama lain.', 422);
            }

            DB::table(self::TABEL)
                ->where('Id_Benefit', $realId)
                ->update([
                    'Nama_Benefit' => $data['nama'],
                    'Keterangan' => $data['keterangan'],
                    'Updated_At' => now(),
                    'Updated_By' => session('career_auth.id'),
                ]);

            Log::channel('web_career')->info("Master benefit #{$id} diperbarui");

            return ResponseHelper::success(null, 'Benefit diperbarui');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ResponseHelper::error(collect($e->errors())->flatten()->first() ?? 'Data tidak valid', 422);
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal update benefit #{$id}: " . $e->getMessage());

            return ResponseHelper::error('Gagal memperbarui data', 500);
        }
    }

    /**
     * Aktif/Nonaktif benefit.
     * Menonaktifkan tetap diizinkan walau masih terpasang di lowongan lama: data
     * historis tidak berubah, benefit ini hanya berhenti muncul sebagai pilihan baru.
     */
    public function toggle(Request $request, $id)
    {
        try {
            $realId = $this->realId($id);
            $aktif = $request->boolean('aktif');

            $terpengaruh = DB::table(self::TABEL)
                ->where('Id_Benefit', $realId)
                ->update([
                    'Flag_Aktif' => $aktif ? 'Y' : 'N',
                    'Updated_At' => now(),
                    'Updated_By' => session('career_auth.id'),
                ]);

            if (! $terpengaruh) {
                return ResponseHelper::error('Data tidak ditemukan', 404);
            }

            Log::channel('web_career')->info("Master benefit #{$id} status " . ($aktif ? 'AKTIF' : 'NONAKTIF'));

            return ResponseHelper::success(null, 'Status diperbarui');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal toggle benefit #{$id}: " . $e->getMessage());

            return ResponseHelper::error('Gagal mengubah status', 500);
        }
    }

    /**
     * Hapus benefit.
     * Ditolak bila masih terpasang di lowongan — ada FOREIGN KEY di tabel
     * jembatan, jadi tanpa penjagaan ini yang muncul adalah galat SQL mentah,
     * bukan penjelasan yang bisa ditindaklanjuti admin.
     */
    public function destroy($id)
    {
        try {
            $realId = $this->realId($id);
            if (! $realId || ! DB::table(self::TABEL)->where('Id_Benefit', $realId)->exists()) {
                return ResponseHelper::error('Data tidak ditemukan', 404);
            }

            // Dihitung ulang di server — UI sudah mengunci tombolnya, tapi
            // permintaan bisa datang dari mana saja.
            $dipakai = $this->jumlahPemasangan($realId);
            if ($dipakai > 0) {
                return ResponseHelper::error("Benefit ini terpasang di {$dipakai} lowongan — nonaktifkan saja.", 422);
            }

            DB::table(self::TABEL)->where('Id_Benefit', $realId)->delete();
            Log::channel('web_career')->info("Master benefit #{$id} dihapus");

            return ResponseHelper::success(null, 'Benefit dihapus');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal hapus benefit #{$id}: " . $e->getMessage());

            return ResponseHelper::error('Gagal menghapus data', 500);
        }
    }
}
