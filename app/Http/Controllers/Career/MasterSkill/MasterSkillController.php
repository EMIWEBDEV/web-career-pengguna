<?php

namespace App\Http\Controllers\Career\MasterSkill;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Support\CareerShell;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Vinkla\Hashids\Facades\Hashids;

/**
 * WEB CAREER — MASTER SKILL / KEAHLIAN (SPA + WEB, tanpa API terpisah).
 * - index()  : return Inertia (render halaman) — shell admin via CareerShell::props.
 * - list()   : data skill (Query Builder) -> ResponseHelper, PAGINASI server-side.
 * - store/update/toggle/destroy : CRUD Query Builder -> ResponseHelper, try/catch, Log channel.
 *
 * Tabel: N_WEB_CAREERS_Master_Skill (Id_Skill IDENTITY, Nama_Skill, Keterangan,
 * Flag_Aktif, Created_At/By, Updated_At/By). Kolom audit bertipe INT (id pengguna),
 * jadi nama pembuat diambil lewat join ke N_WEB_CAREERS_Users — sama persis
 * polanya dengan Master Benefit.
 *
 * PEMAKAIAN lewat tabel JEMBATAN N_WEB_CAREERS_Detail_Skill_MPP
 * (Id_Detail_MPP × Id_Skill, relasi banyak-ke-banyak dengan Detail MPP).
 * Karena itu "dipakai" di sini berarti JUMLAH LOWONGAN yang mensyaratkan skill ini,
 * dan baris yang masih terpasang TIDAK boleh dihapus — nonaktifkan saja.
 */
class MasterSkillController extends Controller
{
    private const TABEL = 'N_WEB_CAREERS_Master_Skill';

    private const TABEL_KATEGORI = 'N_WEB_CAREERS_Master_Skill_Kategori';

    private const JEMBATAN = 'N_WEB_CAREERS_Detail_Skill_MPP';

    /** Nilai penyaring kategori untuk baris yang belum punya kategori. */
    private const TANPA_KATEGORI = 'BELUM';

    /** Halaman (Inertia). Data TIDAK dikirim lewat props — halaman fetch sendiri ke list(). */
    public function index()
    {
        return Inertia::render(
            'Career/admin/master-skill/masterSkill',
            CareerShell::props('/master-skill', 'Master Skill')
        );
    }

    /** Jumlah lowongan yang mensyaratkan tiap skill — badge di tabel SEKALIGUS alasan hapus dikunci. */
    private function pemakaian()
    {
        return DB::table(self::JEMBATAN)
            ->select('Id_Skill', DB::raw('COUNT(*) as Jumlah'))
            ->groupBy('Id_Skill');
    }

    /**
     * Query dasar + filter (q / status / pemakaian). Dipakai bersama oleh baris,
     * penghitung total, DAN penghitung cacah facet.
     *
     * $abaikan memungkinkan satu penyaring dilewati — itulah cara cacah facet
     * dihitung: cacah status dihitung dengan mengabaikan filter status. Tanpa
     * itu, angka pada tab selalu sama dengan jumlah baris yang sedang tampil
     * dan tidak memberi tahu apa pun.
     */
    private function dasarFilter(Request $request, array $abaikan = [])
    {
        $base = DB::table(self::TABEL . ' as s');

        $q = trim((string) $request->query('q', ''));
        if ($q !== '' && ! in_array('q', $abaikan, true)) {
            $base->where(function ($w) use ($q) {
                $w->where('s.Nama_Skill', 'like', "%{$q}%")
                    ->orWhere('s.Keterangan', 'like', "%{$q}%");
            });
        }

        $status = strtoupper(trim((string) $request->query('status', '')));
        if (! in_array('status', $abaikan, true)) {
            if ($status === 'AKTIF') {
                $base->where('s.Flag_Aktif', 'Y');
            } elseif ($status === 'NONAKTIF') {
                $base->where('s.Flag_Aktif', '!=', 'Y');
            }
        }

        // Penyaring khusus halaman ini: sebagian besar skill belum pernah dipakai,
        // jadi "yang menganggur" adalah pertanyaan nyata saat merapikan daftar.
        $pakai = strtoupper(trim((string) $request->query('pemakaian', '')));
        if (! in_array('pemakaian', $abaikan, true)) {
            if ($pakai === 'DIPAKAI') {
                $base->whereExists(fn ($x) => $x->select(DB::raw(1))->from(self::JEMBATAN . ' as j')->whereColumn('j.Id_Skill', 's.Id_Skill'));
            } elseif ($pakai === self::TANPA_KATEGORI) {
                $base->whereNotExists(fn ($x) => $x->select(DB::raw(1))->from(self::JEMBATAN . ' as j')->whereColumn('j.Id_Skill', 's.Id_Skill'));
            }
        }

        // Kategori dikirim sebagai id ter-hash; nilai khusus 'BELUM' menyaring
        // baris yang kategorinya masih kosong (kolomnya memang nullable).
        $kategori = trim((string) $request->query('kategori', ''));
        if ($kategori !== '' && ! in_array('kategori', $abaikan, true)) {
            if (strtoupper($kategori) === self::TANPA_KATEGORI) {
                $base->whereNull('s.Id_Master_Skill_Kategori');
            } else {
                $idKategori = Hashids::decode($kategori)[0] ?? -1;
                $base->where('s.Id_Master_Skill_Kategori', $idKategori);
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
        $facetAktif = (clone $statusBase)->where('s.Flag_Aktif', 'Y')->count();

        $pakaiBase = $this->dasarFilter($request, ['pemakaian']);
        $facetPakaiSemua = (clone $pakaiBase)->count();
        $facetTerpakai = (clone $pakaiBase)
            ->whereExists(fn ($x) => $x->select(DB::raw(1))->from(self::JEMBATAN . ' as j')->whereColumn('j.Id_Skill', 's.Id_Skill'))
            ->count();

        // Cacah per kategori, dihitung dengan MENGABAIKAN filter kategori —
        // supaya angka pada tiap chip menjanjikan hasil kalau chip itu diklik.
        $katBase = $this->dasarFilter($request, ['kategori']);
        $cacahKategori = (clone $katBase)
            ->whereNotNull('s.Id_Master_Skill_Kategori')
            ->select('s.Id_Master_Skill_Kategori', DB::raw('COUNT(*) as Jumlah'))
            ->groupBy('s.Id_Master_Skill_Kategori')
            ->pluck('Jumlah', 'Id_Master_Skill_Kategori');

        // Daftar kategori diambil UTUH dari tabelnya, bukan dari hasil terfilter,
        // supaya chip yang sedang bernilai 0 tetap tampil dan bisa dibatalkan.
        $daftarKategori = DB::table(self::TABEL_KATEGORI)
            ->orderBy('Urutan')->orderBy('Nama')
            ->get(['Id_Master_Skill_Kategori', 'Kode', 'Nama', 'Ikon', 'Warna', 'Flag_Aktif'])
            ->map(fn ($k) => [
                'id' => Hashids::encode($k->Id_Master_Skill_Kategori),
                'kode' => $k->Kode,
                'nama' => $k->Nama,
                'ikon' => $k->Ikon,
                'warna' => $k->Warna,
                'status' => $k->Flag_Aktif === 'Y' ? 'AKTIF' : 'NONAKTIF',
                'jumlah' => (int) ($cacahKategori[$k->Id_Master_Skill_Kategori] ?? 0),
            ])
            ->values();

        $total = (int) DB::table(self::TABEL)->count();
        $aktif = (int) DB::table(self::TABEL)->where('Flag_Aktif', 'Y')->count();
        $terpasang = (int) DB::table(self::JEMBATAN)->distinct()->count('Id_Skill');

        return [
            'total' => $total,
            'aktif' => $aktif,
            'nonaktif' => $total - $aktif,
            'dipakai' => (int) DB::table(self::JEMBATAN)->count(),
            'belumDipakai' => $total - $terpasang,
            'jmlKategori' => (int) DB::table(self::TABEL_KATEGORI)->count(),
            'status' => [
                'semua' => $facetSemua,
                'aktif' => $facetAktif,
                'nonaktif' => $facetSemua - $facetAktif,
            ],
            'pemakaian' => [
                'semua' => $facetPakaiSemua,
                'dipakai' => $facetTerpakai,
                'belum' => $facetPakaiSemua - $facetTerpakai,
            ],
            'kategori' => $daftarKategori,
            'kategoriSemua' => (clone $katBase)->count(),
            'kategoriBelum' => (clone $katBase)->whereNull('s.Id_Master_Skill_Kategori')->count(),
        ];
    }

    /**
     * Data list Master Skill + nama pembuat/pengubah + jumlah pemasangan di MPP.
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
                ->leftJoin('N_WEB_CAREERS_Users as u', 'u.Id_Users', '=', 's.Created_By')
                ->leftJoin('N_WEB_CAREERS_Users as ux', 'ux.Id_Users', '=', 's.Updated_By')
                ->leftJoin(self::TABEL_KATEGORI . ' as k', 'k.Id_Master_Skill_Kategori', '=', 's.Id_Master_Skill_Kategori')
                ->leftJoinSub($this->pemakaian(), 'p', 'p.Id_Skill', '=', 's.Id_Skill')
                ->select(
                    's.*',
                    'u.Nama as Pembuat',
                    'ux.Nama as Pengubah',
                    'k.Kode as Kategori_Kode',
                    'k.Nama as Kategori_Nama',
                    'k.Ikon as Kategori_Ikon',
                    'k.Warna as Kategori_Warna',
                    'k.Urutan as Kategori_Urutan',
                    DB::raw('ISNULL(p.Jumlah, 0) as Dipakai')
                );

            // Nama jadi pemecah seri: mayoritas baris bernilai Dipakai = 0, dan
            // tanpa pemecah seri urutan antarhalaman tidak stabil di SQL Server —
            // satu baris bisa terlihat di dua halaman sementara yang lain hilang.
            if ($sortBy === 'dipakai') {
                $rows->orderBy('Dipakai', $sortDir)->orderBy('s.Nama_Skill');
            } elseif ($sortBy === 'baru') {
                $rows->orderBy('s.Created_At', $sortDir)->orderBy('s.Nama_Skill');
            } elseif ($sortBy === 'kategori') {
                // Urut menurut Urutan kategori (bukan abjad namanya) supaya
                // kelompoknya tampil sesuai susunan yang ditetapkan admin.
                $rows->orderBy('k.Urutan', $sortDir)->orderBy('s.Nama_Skill');
            } else {
                $rows->orderBy('s.Nama_Skill', $sortDir);
            }

            $rows = $rows->forPage($page, $perPage)
                ->get()
                ->map(function ($r) {
                    return [
                        'id' => Hashids::encode($r->Id_Skill), // id di-hash (raw id tidak diekspos)
                        'nama' => $r->Nama_Skill,
                        'keterangan' => $r->Keterangan,
                        'dipakai' => (int) $r->Dipakai,
                        'status' => $r->Flag_Aktif === 'Y' ? 'AKTIF' : 'NONAKTIF',
                        'kategori' => $r->Id_Master_Skill_Kategori ? [
                            'id' => Hashids::encode($r->Id_Master_Skill_Kategori),
                            'kode' => $r->Kategori_Kode,
                            'nama' => $r->Kategori_Nama,
                            'ikon' => $r->Kategori_Ikon,
                            'warna' => $r->Kategori_Warna,
                        ] : null,
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
            ], 'Data skill dimuat');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('Gagal memuat skill: ' . $e->getMessage());

            return ResponseHelper::error('Gagal memuat data skill', 500);
        }
    }

    /**
     * Batas panjang mengikuti lebar kolom asli (varchar 100) — bukan angka karangan.
     * kategoriId dikirim sebagai id ter-hash; boleh kosong karena kolomnya nullable
     * (skill baru bisa dibiarkan tanpa kategori lalu dirapikan belakangan).
     */
    private function validasi(Request $request): array
    {
        $data = $request->validate([
            'nama' => 'required|string|max:100',
            'keterangan' => 'nullable|string|max:100',
            'kategoriId' => 'nullable|string|max:50',
        ]);

        $idKategori = null;
        if (! empty($data['kategoriId'])) {
            $idKategori = Hashids::decode($data['kategoriId'])[0] ?? null;
            if (! $idKategori || ! DB::table(self::TABEL_KATEGORI)->where('Id_Master_Skill_Kategori', $idKategori)->exists()) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'kategoriId' => 'Kategori tidak ditemukan.',
                ]);
            }
        }

        return [
            'nama' => trim($data['nama']),
            'keterangan' => isset($data['keterangan']) && trim($data['keterangan']) !== ''
                ? trim($data['keterangan'])
                : null,
            'kategoriId' => $idKategori,
        ];
    }

    /**
     * Nama harus unik tanpa memandang besar/kecil huruf.
     * Daftar skill tumbuh dari isian bebas form MPP, sehingga sudah terlanjur
     * berisi pasangan nyaris kembar ("QA/QC" vs "QC/QA", "Excel" vs "MS Excel").
     * Yang lama tidak diutak-atik, tapi yang baru tidak boleh menambah
     * kekacauan yang sama.
     */
    private function namaDipakai(string $nama, ?int $kecualiId = null): bool
    {
        return DB::table(self::TABEL)
            ->whereRaw('LOWER(LTRIM(RTRIM(Nama_Skill))) = ?', [mb_strtolower(trim($nama))])
            ->when($kecualiId, fn ($q) => $q->where('Id_Skill', '!=', $kecualiId))
            ->exists();
    }

    private function realId($hash): ?int
    {
        return Hashids::decode($hash)[0] ?? null;
    }

    /** Jumlah lowongan yang mensyaratkan skill ini. */
    private function jumlahPemasangan(int $id): int
    {
        return DB::table(self::JEMBATAN)->where('Id_Skill', $id)->count();
    }

    /** Tambah skill. */
    public function store(Request $request)
    {
        try {
            $data = $this->validasi($request);

            if ($this->namaDipakai($data['nama'])) {
                return ResponseHelper::error('Nama skill sudah ada. Gunakan nama lain.', 422);
            }

            DB::table(self::TABEL)->insert([
                'Nama_Skill' => $data['nama'],
                'Keterangan' => $data['keterangan'],
                'Id_Master_Skill_Kategori' => $data['kategoriId'],
                'Flag_Aktif' => 'Y',
                'Created_At' => now(),
                'Created_By' => session('career_auth.id'),
            ]);

            Log::channel('web_career')->info("Master skill dibuat ({$data['nama']}) oleh " . session('career_auth.nama', 'ADMIN'));

            return ResponseHelper::success(null, 'Skill berhasil ditambahkan', 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ResponseHelper::error(collect($e->errors())->flatten()->first() ?? 'Data tidak valid', 422);
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('Gagal membuat skill: ' . $e->getMessage());

            return ResponseHelper::error('Gagal menyimpan data', 500);
        }
    }

    /** Ubah skill. */
    public function update(Request $request, $id)
    {
        try {
            $realId = $this->realId($id);
            if (! $realId || ! DB::table(self::TABEL)->where('Id_Skill', $realId)->exists()) {
                return ResponseHelper::error('Data tidak ditemukan', 404);
            }

            $data = $this->validasi($request);

            if ($this->namaDipakai($data['nama'], $realId)) {
                return ResponseHelper::error('Nama skill sudah ada. Gunakan nama lain.', 422);
            }

            DB::table(self::TABEL)
                ->where('Id_Skill', $realId)
                ->update([
                    'Nama_Skill' => $data['nama'],
                    'Keterangan' => $data['keterangan'],
                    'Id_Master_Skill_Kategori' => $data['kategoriId'],
                    'Updated_At' => now(),
                    'Updated_By' => session('career_auth.id'),
                ]);

            Log::channel('web_career')->info("Master skill #{$id} diperbarui");

            return ResponseHelper::success(null, 'Skill diperbarui');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ResponseHelper::error(collect($e->errors())->flatten()->first() ?? 'Data tidak valid', 422);
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal update skill #{$id}: " . $e->getMessage());

            return ResponseHelper::error('Gagal memperbarui data', 500);
        }
    }

    /**
     * Aktif/Nonaktif skill.
     * Menonaktifkan tetap diizinkan walau masih dipakai lowongan lama: data
     * historis tidak berubah, skill ini hanya berhenti muncul sebagai pilihan baru.
     */
    public function toggle(Request $request, $id)
    {
        try {
            $realId = $this->realId($id);
            $aktif = $request->boolean('aktif');

            $terpengaruh = DB::table(self::TABEL)
                ->where('Id_Skill', $realId)
                ->update([
                    'Flag_Aktif' => $aktif ? 'Y' : 'N',
                    'Updated_At' => now(),
                    'Updated_By' => session('career_auth.id'),
                ]);

            if (! $terpengaruh) {
                return ResponseHelper::error('Data tidak ditemukan', 404);
            }

            Log::channel('web_career')->info("Master skill #{$id} status " . ($aktif ? 'AKTIF' : 'NONAKTIF'));

            return ResponseHelper::success(null, 'Status diperbarui');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal toggle skill #{$id}: " . $e->getMessage());

            return ResponseHelper::error('Gagal mengubah status', 500);
        }
    }

    /**
     * Hapus skill.
     * Ditolak bila masih dipakai lowongan — tabel jembatan merujuk Id_Skill,
     * jadi tanpa penjagaan ini yang muncul adalah galat SQL mentah, bukan
     * penjelasan yang bisa ditindaklanjuti admin.
     */
    public function destroy($id)
    {
        try {
            $realId = $this->realId($id);
            if (! $realId || ! DB::table(self::TABEL)->where('Id_Skill', $realId)->exists()) {
                return ResponseHelper::error('Data tidak ditemukan', 404);
            }

            // Dihitung ulang di server — UI sudah mengunci tombolnya, tapi
            // permintaan bisa datang dari mana saja.
            $dipakai = $this->jumlahPemasangan($realId);
            if ($dipakai > 0) {
                return ResponseHelper::error("Skill ini dipakai {$dipakai} lowongan — nonaktifkan saja.", 422);
            }

            DB::table(self::TABEL)->where('Id_Skill', $realId)->delete();
            Log::channel('web_career')->info("Master skill #{$id} dihapus");

            return ResponseHelper::success(null, 'Skill dihapus');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal hapus skill #{$id}: " . $e->getMessage());

            return ResponseHelper::error('Gagal menghapus data', 500);
        }
    }
}
