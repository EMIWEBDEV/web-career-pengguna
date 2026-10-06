<?php

namespace App\Http\Controllers\Career\MasterSkill;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Vinkla\Hashids\Facades\Hashids;

/**
 * WEB CAREER — MASTER SKILL KATEGORI (tab "Kategori" di halaman /master-skill).
 *
 * TIDAK punya index(): kategori bukan halaman berdiri sendiri. Bentuknya mengikuti
 * Master FAQ — satu halaman, dua tab — karena kategori cuma belasan baris yang
 * jarang berubah dan tidak layak menambah satu menu sidebar lagi.
 *
 * Tabel: N_WEB_CAREERS_Master_Skill_Kategori. Dirujuk
 * N_WEB_CAREERS_Master_Skill.Id_Master_Skill_Kategori lewat FOREIGN KEY
 * (FK_Master_Skill_Kategori) TANPA cascade — jadi kategori yang masih dipakai
 * tidak boleh dihapus, dan penolakannya dijelaskan di sini supaya admin tidak
 * ditodong galat SQL mentah.
 *
 * Kolom audit bertipe INT (id pengguna), mengikuti Master Skill.
 * Hak akses menumpang kunci halaman induknya: masterSkillPage.
 */
class MasterSkillKategoriController extends Controller
{
    private const TABEL = 'N_WEB_CAREERS_Master_Skill_Kategori';

    private const PK = 'Id_Master_Skill_Kategori';

    private const TABEL_SKILL = 'N_WEB_CAREERS_Master_Skill';

    /** Jumlah skill per kategori — badge di tabel SEKALIGUS alasan hapus dikunci. */
    private function pemakaian()
    {
        return DB::table(self::TABEL_SKILL)
            ->select('Id_Master_Skill_Kategori', DB::raw('COUNT(*) as Jumlah'))
            ->whereNotNull('Id_Master_Skill_Kategori')
            ->groupBy('Id_Master_Skill_Kategori');
    }

    /**
     * Query dasar + filter (q / status / pemakaian). Dipakai bersama oleh baris,
     * penghitung total, DAN penghitung cacah facet.
     *
     * $abaikan memungkinkan satu penyaring dilewati — itulah cara cacah facet
     * dihitung: cacah status mengabaikan filter status, cacah pemakaian
     * mengabaikan filter pemakaian. Tanpa itu, angka pada tiap kontrol selalu
     * sama dengan jumlah baris yang sedang tampil dan tidak memberi tahu apa pun.
     */
    private function dasarFilter(Request $request, array $abaikan = [])
    {
        $base = DB::table(self::TABEL . ' as k');

        $q = trim((string) $request->query('q', ''));
        if ($q !== '' && ! in_array('q', $abaikan, true)) {
            $base->where(function ($w) use ($q) {
                $w->where('k.Nama', 'like', "%{$q}%")
                    ->orWhere('k.Kode', 'like', "%{$q}%")
                    ->orWhere('k.Deskripsi', 'like', "%{$q}%");
            });
        }

        $status = strtoupper(trim((string) $request->query('status', '')));
        if (! in_array('status', $abaikan, true)) {
            if ($status === 'AKTIF') {
                $base->where('k.Flag_Aktif', 'Y');
            } elseif ($status === 'NONAKTIF') {
                $base->where('k.Flag_Aktif', '!=', 'Y');
            }
        }

        $pakai = strtoupper(trim((string) $request->query('pemakaian', '')));
        if (! in_array('pemakaian', $abaikan, true)) {
            if ($pakai === 'DIPAKAI') {
                $base->whereExists(fn ($x) => $x->select(DB::raw(1))->from(self::TABEL_SKILL . ' as s')
                    ->whereColumn('s.Id_Master_Skill_Kategori', 'k.' . self::PK));
            } elseif ($pakai === 'BELUM') {
                $base->whereNotExists(fn ($x) => $x->select(DB::raw(1))->from(self::TABEL_SKILL . ' as s')
                    ->whereColumn('s.Id_Master_Skill_Kategori', 'k.' . self::PK));
            }
        }

        return $base;
    }

    /** Angka kartu & cacah facet — dihitung server karena klien cuma memegang satu halaman. */
    private function ringkasan(Request $request): array
    {
        $statusBase = $this->dasarFilter($request, ['status']);
        $fStatusSemua = (clone $statusBase)->count();
        $fStatusAktif = (clone $statusBase)->where('k.Flag_Aktif', 'Y')->count();

        $pakaiBase = $this->dasarFilter($request, ['pemakaian']);
        $fPakaiSemua = (clone $pakaiBase)->count();
        $fPakaiIsi = (clone $pakaiBase)
            ->whereExists(fn ($x) => $x->select(DB::raw(1))->from(self::TABEL_SKILL . ' as s')
                ->whereColumn('s.Id_Master_Skill_Kategori', 'k.' . self::PK))
            ->count();

        $total = (int) DB::table(self::TABEL)->count();
        $aktif = (int) DB::table(self::TABEL)->where('Flag_Aktif', 'Y')->count();
        $terpakai = (int) DB::table(self::TABEL_SKILL)->whereNotNull('Id_Master_Skill_Kategori')->distinct()->count('Id_Master_Skill_Kategori');

        return [
            'total' => $total,
            'aktif' => $aktif,
            'nonaktif' => $total - $aktif,
            'kosong' => $total - $terpakai,
            // Dipakai form "Kategori Baru" untuk mengusulkan Urutan berikutnya.
            // Harus dari server: halaman yang tampil tidak tahu nilai terbesar
            // begitu daftarnya dipaginasi.
            'urutanMaks' => (int) DB::table(self::TABEL)->max('Urutan'),
            'status' => [
                'semua' => $fStatusSemua,
                'aktif' => $fStatusAktif,
                'nonaktif' => $fStatusSemua - $fStatusAktif,
            ],
            'pemakaian' => [
                'semua' => $fPakaiSemua,
                'dipakai' => $fPakaiIsi,
                'belum' => $fPakaiSemua - $fPakaiIsi,
            ],
        ];
    }

    /**
     * Daftar kategori — PAGINASI + FILTER + URUT server-side.
     *
     * CATATAN: memaginasi daftar ini AMAN meski kategori juga jadi pilihan
     * dropdown di tab Skill — dropdown itu membaca ringkasan.kategori dari
     * endpoint Master Skill (yang selalu utuh), BUKAN dari endpoint ini.
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
                ->leftJoinSub($this->pemakaian(), 'p', 'p.Id_Master_Skill_Kategori', '=', 'k.' . self::PK)
                ->select('k.*', DB::raw('ISNULL(p.Jumlah, 0) as Dipakai'));

            // Nama jadi pemecah seri di semua mode: Urutan boleh kembar dan
            // Dipakai banyak bernilai 0 — tanpa pemecah seri, urutan antarhalaman
            // tidak stabil di SQL Server dan baris bisa terlihat dua kali.
            if ($sortBy === 'dipakai') {
                $rows->orderBy('Dipakai', $sortDir)->orderBy('k.Nama');
            } elseif ($sortBy === 'nama') {
                $rows->orderBy('k.Nama', $sortDir);
            } elseif ($sortBy === 'kode') {
                $rows->orderBy('k.Kode', $sortDir);
            } else {
                $rows->orderBy('k.Urutan', $sortDir)->orderBy('k.Nama');
            }

            $rows = $rows->forPage($page, $perPage)
                ->get()
                ->map(fn ($r) => [
                    'id' => Hashids::encode($r->{self::PK}),
                    'kode' => $r->Kode,
                    'nama' => $r->Nama,
                    'deskripsi' => $r->Deskripsi,
                    'ikon' => $r->Ikon,
                    'warna' => $r->Warna,
                    'urutan' => (int) $r->Urutan,
                    'dipakai' => (int) $r->Dipakai,
                    'status' => $r->Flag_Aktif === 'Y' ? 'AKTIF' : 'NONAKTIF',
                    'createdAt' => $r->Created_At,
                    'updatedAt' => $r->Updated_At,
                ])
                ->values();

            return ResponseHelper::success([
                'rows' => $rows,
                'total' => $total,
                'page' => $page,
                'perPage' => $perPage,
                'ringkasan' => $this->ringkasan($request),
            ], 'Data kategori skill dimuat');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('Gagal memuat kategori skill: ' . $e->getMessage());

            return ResponseHelper::error('Gagal memuat data kategori skill', 500);
        }
    }

    /** Batas panjang mengikuti lebar kolom asli. Kode hanya divalidasi saat tambah. */
    private function validasi(Request $request, bool $baru): array
    {
        $aturan = [
            'nama' => 'required|string|max:100',
            'deskripsi' => 'nullable|string|max:300',
            'ikon' => 'nullable|string|max:50',
            'warna' => 'nullable|string|max:20',
            'urutan' => 'nullable|integer|min:0|max:9999',
        ];
        if ($baru) {
            $aturan['kode'] = 'required|string|max:30|regex:/^[A-Za-z0-9_-]+$/';
        }

        $data = $request->validate($aturan);

        return [
            'kode' => $baru ? strtoupper(trim($data['kode'])) : null,
            'nama' => trim($data['nama']),
            'deskripsi' => isset($data['deskripsi']) && trim($data['deskripsi']) !== '' ? trim($data['deskripsi']) : null,
            'ikon' => isset($data['ikon']) && trim($data['ikon']) !== '' ? trim($data['ikon']) : null,
            'warna' => isset($data['warna']) && trim($data['warna']) !== '' ? trim($data['warna']) : null,
            'urutan' => (int) ($data['urutan'] ?? 0),
        ];
    }

    /** Kode & Nama sama-sama UNIQUE di DB — dicek di sini supaya pesannya jelas. */
    private function bentrok(string $kolom, string $nilai, ?int $kecualiId = null): bool
    {
        return DB::table(self::TABEL)
            ->whereRaw("LOWER(LTRIM(RTRIM({$kolom}))) = ?", [mb_strtolower(trim($nilai))])
            ->when($kecualiId, fn ($q) => $q->where(self::PK, '!=', $kecualiId))
            ->exists();
    }

    private function realId($hash): ?int
    {
        return Hashids::decode($hash)[0] ?? null;
    }

    public function store(Request $request)
    {
        try {
            $data = $this->validasi($request, true);

            if ($this->bentrok('Kode', $data['kode'])) {
                return ResponseHelper::error("Kode kategori \"{$data['kode']}\" sudah dipakai.", 422);
            }
            if ($this->bentrok('Nama', $data['nama'])) {
                return ResponseHelper::error('Nama kategori sudah ada. Gunakan nama lain.', 422);
            }

            DB::table(self::TABEL)->insert([
                'Kode' => $data['kode'],
                'Nama' => $data['nama'],
                'Deskripsi' => $data['deskripsi'],
                'Ikon' => $data['ikon'],
                'Warna' => $data['warna'],
                'Urutan' => $data['urutan'],
                'Flag_Aktif' => 'Y',
                'Created_At' => now(),
                'Created_By' => session('career_auth.id'),
            ]);

            Log::channel('web_career')->info("Kategori skill dibuat ({$data['kode']}) oleh " . session('career_auth.nama', 'ADMIN'));

            return ResponseHelper::success(null, 'Kategori skill berhasil ditambahkan', 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ResponseHelper::error(collect($e->errors())->flatten()->first() ?? 'Data tidak valid', 422);
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('Gagal membuat kategori skill: ' . $e->getMessage());

            return ResponseHelper::error('Gagal menyimpan data', 500);
        }
    }

    /** Kode SENGAJA tidak ikut diperbarui — dipakai skrip seed & rujukan lintas sistem. */
    public function update(Request $request, $id)
    {
        try {
            $realId = $this->realId($id);
            if (! $realId || ! DB::table(self::TABEL)->where(self::PK, $realId)->exists()) {
                return ResponseHelper::error('Data tidak ditemukan', 404);
            }

            $data = $this->validasi($request, false);

            if ($this->bentrok('Nama', $data['nama'], $realId)) {
                return ResponseHelper::error('Nama kategori sudah ada. Gunakan nama lain.', 422);
            }

            DB::table(self::TABEL)->where(self::PK, $realId)->update([
                'Nama' => $data['nama'],
                'Deskripsi' => $data['deskripsi'],
                'Ikon' => $data['ikon'],
                'Warna' => $data['warna'],
                'Urutan' => $data['urutan'],
                'Updated_At' => now(),
                'Updated_By' => session('career_auth.id'),
            ]);

            Log::channel('web_career')->info("Kategori skill #{$id} diperbarui");

            return ResponseHelper::success(null, 'Kategori skill diperbarui');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ResponseHelper::error(collect($e->errors())->flatten()->first() ?? 'Data tidak valid', 422);
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal update kategori skill #{$id}: " . $e->getMessage());

            return ResponseHelper::error('Gagal memperbarui data', 500);
        }
    }

    /**
     * Aktif/Nonaktif kategori.
     * Skill yang sudah memakai kategori nonaktif TIDAK diubah — kategori itu
     * hanya berhenti muncul sebagai pilihan baru.
     */
    public function toggle(Request $request, $id)
    {
        try {
            $realId = $this->realId($id);
            $aktif = $request->boolean('aktif');

            $terpengaruh = DB::table(self::TABEL)->where(self::PK, $realId)->update([
                'Flag_Aktif' => $aktif ? 'Y' : 'N',
                'Updated_At' => now(),
                'Updated_By' => session('career_auth.id'),
            ]);

            if (! $terpengaruh) {
                return ResponseHelper::error('Data tidak ditemukan', 404);
            }

            Log::channel('web_career')->info("Kategori skill #{$id} status " . ($aktif ? 'AKTIF' : 'NONAKTIF'));

            return ResponseHelper::success(null, 'Status diperbarui');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal toggle kategori skill #{$id}: " . $e->getMessage());

            return ResponseHelper::error('Gagal mengubah status', 500);
        }
    }

    /**
     * Hapus kategori.
     * Ditolak bila masih dipakai skill — FK-nya sengaja dibuat tanpa cascade,
     * jadi tanpa penjagaan ini yang muncul adalah galat constraint mentah.
     */
    public function destroy($id)
    {
        try {
            $realId = $this->realId($id);
            $row = DB::table(self::TABEL)->where(self::PK, $realId)->first();
            if (! $row) {
                return ResponseHelper::error('Data tidak ditemukan', 404);
            }

            // Dihitung ulang di server — UI sudah mengunci tombolnya, tapi
            // permintaan bisa datang dari mana saja.
            $dipakai = DB::table(self::TABEL_SKILL)->where('Id_Master_Skill_Kategori', $realId)->count();
            if ($dipakai > 0) {
                return ResponseHelper::error("Kategori ini dipakai {$dipakai} skill — pindahkan skill-nya dulu, atau nonaktifkan saja.", 422);
            }

            DB::table(self::TABEL)->where(self::PK, $realId)->delete();
            Log::channel('web_career')->info("Kategori skill #{$id} dihapus");

            return ResponseHelper::success(null, 'Kategori skill dihapus');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal hapus kategori skill #{$id}: " . $e->getMessage());

            return ResponseHelper::error('Gagal menghapus data', 500);
        }
    }
}
