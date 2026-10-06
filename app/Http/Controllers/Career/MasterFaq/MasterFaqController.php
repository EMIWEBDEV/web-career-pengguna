<?php

namespace App\Http\Controllers\Career\MasterFaq;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Support\Career\HtmlBersih;
use App\Support\Career\KodeUnik;
use App\Support\CareerShell;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Vinkla\Hashids\Facades\Hashids;

/**
 * WEB CAREER — MASTER FAQ (SPA + WEB, pola Master Kategori).
 *
 * Mengurus DUA entitas dalam satu controller (seperti MasterFeedbackController
 * yang mengurus form + pertanyaannya): KATEGORI FAQ dan PERTANYAAN FAQ. Keduanya
 * dikelola di satu halaman admin, jadi memecahnya jadi dua controller hanya
 * menambah berkas tanpa menambah kejelasan.
 *
 * - index()  : render halaman admin (shell via CareerShell::props)
 * - list*()  : data → ResponseHelper (dipanggil axios saat mount)
 * - CRUD     : Query Builder langsung, try/catch, Log channel web_career
 *
 * Hapus = SOFT DELETE (Flag_Cancellation = 'Y'). FAQ menyimpan penghitung
 * dilihat/membantu yang jadi bahan keputusan HR — data itu tidak boleh lenyap
 * hanya karena satu pertanyaan dipensiunkan.
 */
class MasterFaqController extends Controller
{
    private const TABEL = 'N_WEB_CAREERS_Master_Faq';

    private const TABEL_KATEGORI = 'N_WEB_CAREERS_Master_Faq_Kategori';

    /** Halaman admin (Inertia). Data diambil sendiri oleh halaman lewat list(). */
    public function index()
    {
        return Inertia::render(
            'Career/admin/master-faq/masterFaq',
            CareerShell::props('/master-faq', 'Master FAQ')
        );
    }

    // ════════════════════════════════════════════════════════════
    // PERTANYAAN
    // ════════════════════════════════════════════════════════════

    /** Daftar seluruh FAQ (termasuk nonaktif) untuk tabel admin. */
    public function list()
    {
        try {
            $rows = DB::table(self::TABEL . ' as f')
                ->leftJoin(self::TABEL_KATEGORI . ' as k', 'k.Id_Master_Faq_Kategori', '=', 'f.Faq_Kategori_Id')
                ->where('f.Flag_Cancellation', 'T')
                ->orderBy('k.Urutan')
                ->orderBy('f.Urutan')
                ->orderBy('f.Id_Master_Faq')
                ->select('f.*', 'k.Nama as Kategori_Nama', 'k.Flag_Aktif as Kategori_Aktif')
                ->get()
                ->map(fn ($r) => [
                    'id' => Hashids::encode($r->Id_Master_Faq),
                    'kategoriId' => $r->Faq_Kategori_Id ? Hashids::encode($r->Faq_Kategori_Id) : null,
                    'kategoriNama' => $r->Kategori_Nama,
                    // Kategori nonaktif = pertanyaannya ikut tidak tampil di publik;
                    // admin perlu melihat peringatan ini di tabel.
                    'kategoriNonaktif' => $r->Faq_Kategori_Id !== null && $r->Kategori_Aktif !== 'Y',
                    'slug' => $r->Slug,
                    'ikon' => $r->Ikon,
                    'pertanyaan' => $r->Pertanyaan,
                    'jawabanRingkas' => $r->Jawaban_Ringkas,
                    'jawabanDetail' => $r->Jawaban_Detail,
                    'urutan' => (int) $r->Urutan,
                    'aktif' => $r->Flag_Aktif === 'Y',
                    'tampilLanding' => $r->Flag_Tampil_Landing === 'Y',
                    'dilihat' => (int) $r->Jumlah_Dilihat,
                    'membantu' => (int) $r->Jumlah_Membantu,
                    'tidakMembantu' => (int) $r->Jumlah_Tidak_Membantu,
                    'updatedAt' => $r->Updated_At,
                ])
                ->values();

            return ResponseHelper::success($rows, 'Data FAQ dimuat');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('Gagal memuat FAQ: ' . $e->getMessage());

            return ResponseHelper::error('Gagal memuat data FAQ', 500);
        }
    }

    /** Tambah FAQ. */
    public function store(Request $request)
    {
        try {
            $data = $this->validasiFaq($request);
            $now = now();
            $oleh = $this->oleh();

            DB::table(self::TABEL)->insert([
                'Faq_Kategori_Id' => $this->kategoriId($data['kategoriId'] ?? null),
                'Slug' => $this->slugUnik($data['pertanyaan']),
                'Ikon' => $data['ikon'] ?? null,
                'Pertanyaan' => $data['pertanyaan'],
                'Jawaban_Ringkas' => $data['jawabanRingkas'],
                'Jawaban_Detail' => HtmlBersih::saring($data['jawabanDetail'] ?? null),
                'Urutan' => $data['urutan'] ?? $this->urutanBerikutnya($this->kategoriId($data['kategoriId'] ?? null)),
                'Flag_Aktif' => ($data['aktif'] ?? true) ? 'Y' : 'N',
                'Flag_Tampil_Landing' => ($data['tampilLanding'] ?? false) ? 'Y' : 'T',
                'Jumlah_Dilihat' => 0,
                'Jumlah_Membantu' => 0,
                'Jumlah_Tidak_Membantu' => 0,
                'Created_At' => $now,
                'Created_By' => $oleh,
                'Updated_At' => $now,
                'Updated_By' => $oleh,
                'Flag_Cancellation' => 'T',
            ]);

            Log::channel('web_career')->info("FAQ dibuat ({$data['pertanyaan']}) oleh {$oleh}");

            return ResponseHelper::success(null, 'FAQ berhasil ditambahkan', 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ResponseHelper::error(collect($e->errors())->flatten()->first() ?? 'Data tidak valid', 422);
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('Gagal membuat FAQ: ' . $e->getMessage());

            return ResponseHelper::error('Gagal menyimpan data', 500);
        }
    }

    /**
     * Ubah FAQ.
     *
     * SLUG TIDAK IKUT BERUBAH walau pertanyaan diedit: tautan
     * /karir/faq#<slug> yang sudah dibagikan HR ke kandidat lewat WA/email
     * harus tetap hidup. Perubahan slug hanya lewat aksi eksplisit
     * (`perbaruiSlug`), sehingga admin sadar sedang mematikan tautan lama.
     */
    public function update(Request $request, $id)
    {
        try {
            $realId = $this->realId($id);
            if (! $realId || ! $this->faqAda($realId)) {
                return ResponseHelper::error('Data tidak ditemukan', 404);
            }

            $data = $this->validasiFaq($request);

            DB::table(self::TABEL)
                ->where('Id_Master_Faq', $realId)
                ->update([
                    'Faq_Kategori_Id' => $this->kategoriId($data['kategoriId'] ?? null),
                    'Ikon' => $data['ikon'] ?? null,
                    'Pertanyaan' => $data['pertanyaan'],
                    'Jawaban_Ringkas' => $data['jawabanRingkas'],
                    'Jawaban_Detail' => HtmlBersih::saring($data['jawabanDetail'] ?? null),
                    'Urutan' => $data['urutan'] ?? 0,
                    'Flag_Aktif' => ($data['aktif'] ?? true) ? 'Y' : 'N',
                    'Flag_Tampil_Landing' => ($data['tampilLanding'] ?? false) ? 'Y' : 'T',
                    'Updated_At' => now(),
                    'Updated_By' => $this->oleh(),
                ]);

            Log::channel('web_career')->info("FAQ #{$id} diperbarui");

            return ResponseHelper::success(null, 'FAQ diperbarui');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ResponseHelper::error(collect($e->errors())->flatten()->first() ?? 'Data tidak valid', 422);
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal update FAQ #{$id}: " . $e->getMessage());

            return ResponseHelper::error('Gagal memperbarui data', 500);
        }
    }

    /**
     * Selaraskan slug dengan pertanyaan saat ini — aksi TERPISAH & sadar-risiko.
     * Tautan lama (#slug lama) akan berhenti membuka pertanyaan ini.
     */
    public function perbaruiSlug($id)
    {
        try {
            $realId = $this->realId($id);
            $row = $realId ? $this->faqAda($realId) : null;
            if (! $row) {
                return ResponseHelper::error('Data tidak ditemukan', 404);
            }

            $slugBaru = $this->slugUnik($row->Pertanyaan, $realId);
            DB::table(self::TABEL)
                ->where('Id_Master_Faq', $realId)
                ->update([
                    'Slug' => $slugBaru,
                    'Updated_At' => now(),
                    'Updated_By' => $this->oleh(),
                ]);

            Log::channel('web_career')->info("Slug FAQ #{$id} diperbarui: {$row->Slug} → {$slugBaru}");

            return ResponseHelper::success(['slug' => $slugBaru], 'Slug diperbarui');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal perbarui slug FAQ #{$id}: " . $e->getMessage());

            return ResponseHelper::error('Gagal memperbarui slug', 500);
        }
    }

    /** Aktif / nonaktif FAQ. */
    public function toggle(Request $request, $id)
    {
        return $this->ubahFlag($id, 'Flag_Aktif', $request->boolean('aktif') ? 'Y' : 'N', 'Status diperbarui');
    }

    /** Tampilkan / sembunyikan FAQ dari accordion landing. */
    public function toggleLanding(Request $request, $id)
    {
        return $this->ubahFlag(
            $id,
            'Flag_Tampil_Landing',
            $request->boolean('tampil') ? 'Y' : 'T',
            'Tampilan landing diperbarui'
        );
    }

    /** Simpan ulang urutan sesuai susunan yang dikirim halaman admin. */
    public function reorder(Request $request)
    {
        try {
            $ids = $request->input('ids', []);
            if (! is_array($ids) || ! $ids) {
                return ResponseHelper::error('Urutan tidak dikirim', 422);
            }

            $now = now();
            $oleh = $this->oleh();

            DB::transaction(function () use ($ids, $now, $oleh) {
                foreach (array_values($ids) as $i => $idHash) {
                    $realId = $this->realId($idHash);
                    if (! $realId) {
                        continue;
                    }

                    DB::table(self::TABEL)
                        ->where('Id_Master_Faq', $realId)
                        ->update([
                            // Kelipatan 10 supaya penyisipan manual di antara dua
                            // baris masih mungkin tanpa menata ulang semuanya.
                            'Urutan' => ($i + 1) * 10,
                            'Updated_At' => $now,
                            'Updated_By' => $oleh,
                        ]);
                }
            });

            return ResponseHelper::success(null, 'Urutan disimpan');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('Gagal menyimpan urutan FAQ: ' . $e->getMessage());

            return ResponseHelper::error('Gagal menyimpan urutan', 500);
        }
    }

    /** Hapus FAQ (soft delete). */
    public function destroy($id)
    {
        try {
            $realId = $this->realId($id);
            $terpengaruh = $realId
                ? DB::table(self::TABEL)
                    ->where('Id_Master_Faq', $realId)
                    ->where('Flag_Cancellation', 'T')
                    ->update([
                        'Flag_Cancellation' => 'Y',
                        'Cancelled_At' => now(),
                        'Cancelled_By' => $this->oleh(),
                    ])
                : 0;

            if (! $terpengaruh) {
                return ResponseHelper::error('Data tidak ditemukan', 404);
            }

            Log::channel('web_career')->info("FAQ #{$id} dihapus");

            return ResponseHelper::success(null, 'FAQ dihapus');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal hapus FAQ #{$id}: " . $e->getMessage());

            return ResponseHelper::error('Gagal menghapus data', 500);
        }
    }

    // ════════════════════════════════════════════════════════════
    // KATEGORI
    // ════════════════════════════════════════════════════════════

    public function listKategori()
    {
        try {
            $rows = DB::table(self::TABEL_KATEGORI . ' as k')
                ->leftJoin(self::TABEL . ' as f', function ($j) {
                    $j->on('f.Faq_Kategori_Id', '=', 'k.Id_Master_Faq_Kategori')
                        ->where('f.Flag_Cancellation', '=', 'T');
                })
                ->where('k.Flag_Cancellation', 'T')
                ->groupBy(
                    'k.Id_Master_Faq_Kategori', 'k.Kode', 'k.Nama', 'k.Deskripsi',
                    'k.Ikon', 'k.Urutan', 'k.Flag_Aktif', 'k.Updated_At'
                )
                ->orderBy('k.Urutan')
                ->orderBy('k.Id_Master_Faq_Kategori')
                ->select(
                    'k.Id_Master_Faq_Kategori', 'k.Kode', 'k.Nama', 'k.Deskripsi',
                    'k.Ikon', 'k.Urutan', 'k.Flag_Aktif', 'k.Updated_At',
                    DB::raw('COUNT(f.Id_Master_Faq) as Jumlah_Faq')
                )
                ->get()
                ->map(fn ($r) => [
                    'id' => Hashids::encode($r->Id_Master_Faq_Kategori),
                    'kode' => $r->Kode,
                    'nama' => $r->Nama,
                    'deskripsi' => $r->Deskripsi,
                    'ikon' => $r->Ikon,
                    'urutan' => (int) $r->Urutan,
                    'aktif' => $r->Flag_Aktif === 'Y',
                    'jumlahFaq' => (int) $r->Jumlah_Faq,
                    'updatedAt' => $r->Updated_At,
                ])
                ->values();

            return ResponseHelper::success($rows, 'Data kategori FAQ dimuat');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('Gagal memuat kategori FAQ: ' . $e->getMessage());

            return ResponseHelper::error('Gagal memuat kategori FAQ', 500);
        }
    }

    public function storeKategori(Request $request)
    {
        try {
            $data = $this->validasiKategori($request);
            $now = now();
            $oleh = $this->oleh();

            $kode = KodeUnik::buat(self::TABEL_KATEGORI, 'Kode', $data['nama'], 30, 'KATEGORI');

            DB::table(self::TABEL_KATEGORI)->insert([
                'Kode' => $kode,
                'Nama' => $data['nama'],
                'Deskripsi' => $data['deskripsi'] ?? null,
                'Ikon' => $data['ikon'] ?? null,
                'Urutan' => $data['urutan'] ?? $this->urutanKategoriBerikutnya(),
                'Flag_Aktif' => ($data['aktif'] ?? true) ? 'Y' : 'N',
                'Created_At' => $now,
                'Created_By' => $oleh,
                'Updated_At' => $now,
                'Updated_By' => $oleh,
                'Flag_Cancellation' => 'T',
            ]);

            Log::channel('web_career')->info("Kategori FAQ dibuat ({$kode}) oleh {$oleh}");

            return ResponseHelper::success(null, 'Kategori berhasil ditambahkan', 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ResponseHelper::error(collect($e->errors())->flatten()->first() ?? 'Data tidak valid', 422);
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('Gagal membuat kategori FAQ: ' . $e->getMessage());

            return ResponseHelper::error('Gagal menyimpan data', 500);
        }
    }

    public function updateKategori(Request $request, $id)
    {
        try {
            $realId = $this->realId($id);
            $ada = $realId && DB::table(self::TABEL_KATEGORI)
                ->where('Id_Master_Faq_Kategori', $realId)
                ->where('Flag_Cancellation', 'T')
                ->exists();

            if (! $ada) {
                return ResponseHelper::error('Data tidak ditemukan', 404);
            }

            $data = $this->validasiKategori($request);

            DB::table(self::TABEL_KATEGORI)
                ->where('Id_Master_Faq_Kategori', $realId)
                ->update([
                    'Nama' => $data['nama'],
                    'Deskripsi' => $data['deskripsi'] ?? null,
                    'Ikon' => $data['ikon'] ?? null,
                    'Urutan' => $data['urutan'] ?? 0,
                    'Flag_Aktif' => ($data['aktif'] ?? true) ? 'Y' : 'N',
                    'Updated_At' => now(),
                    'Updated_By' => $this->oleh(),
                ]);

            Log::channel('web_career')->info("Kategori FAQ #{$id} diperbarui");

            return ResponseHelper::success(null, 'Kategori diperbarui');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ResponseHelper::error(collect($e->errors())->flatten()->first() ?? 'Data tidak valid', 422);
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal update kategori FAQ #{$id}: " . $e->getMessage());

            return ResponseHelper::error('Gagal memperbarui data', 500);
        }
    }

    public function toggleKategori(Request $request, $id)
    {
        try {
            $realId = $this->realId($id);
            $terpengaruh = $realId
                ? DB::table(self::TABEL_KATEGORI)
                    ->where('Id_Master_Faq_Kategori', $realId)
                    ->where('Flag_Cancellation', 'T')
                    ->update([
                        'Flag_Aktif' => $request->boolean('aktif') ? 'Y' : 'N',
                        'Updated_At' => now(),
                        'Updated_By' => $this->oleh(),
                    ])
                : 0;

            if (! $terpengaruh) {
                return ResponseHelper::error('Data tidak ditemukan', 404);
            }

            return ResponseHelper::success(null, 'Status kategori diperbarui');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal toggle kategori FAQ #{$id}: " . $e->getMessage());

            return ResponseHelper::error('Gagal mengubah status', 500);
        }
    }

    /**
     * Hapus kategori (soft delete). DITOLAK bila masih ada FAQ yang memakainya —
     * menghapusnya diam-diam akan melempar pertanyaan-pertanyaan itu ke section
     * "Lainnya" tanpa admin tahu.
     */
    public function destroyKategori($id)
    {
        try {
            $realId = $this->realId($id);
            if (! $realId) {
                return ResponseHelper::error('Data tidak ditemukan', 404);
            }

            $dipakai = DB::table(self::TABEL)
                ->where('Faq_Kategori_Id', $realId)
                ->where('Flag_Cancellation', 'T')
                ->count();

            if ($dipakai > 0) {
                return ResponseHelper::error(
                    "Kategori masih dipakai {$dipakai} pertanyaan. Pindahkan dulu pertanyaannya.",
                    422
                );
            }

            $terpengaruh = DB::table(self::TABEL_KATEGORI)
                ->where('Id_Master_Faq_Kategori', $realId)
                ->where('Flag_Cancellation', 'T')
                ->update([
                    'Flag_Cancellation' => 'Y',
                    'Cancelled_At' => now(),
                    'Cancelled_By' => $this->oleh(),
                ]);

            if (! $terpengaruh) {
                return ResponseHelper::error('Data tidak ditemukan', 404);
            }

            Log::channel('web_career')->info("Kategori FAQ #{$id} dihapus");

            return ResponseHelper::success(null, 'Kategori dihapus');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal hapus kategori FAQ #{$id}: " . $e->getMessage());

            return ResponseHelper::error('Gagal menghapus data', 500);
        }
    }

    // ════════════════════════════════════════════════════════════
    // PEMBANTU
    // ════════════════════════════════════════════════════════════

    private function validasiFaq(Request $request): array
    {
        return $request->validate([
            'kategoriId' => 'nullable|string',
            'ikon' => 'nullable|string|max:50',
            'pertanyaan' => 'required|string|max:300',
            'jawabanRingkas' => 'required|string|max:1000',
            'jawabanDetail' => 'nullable|string',
            'urutan' => 'nullable|integer|min:0|max:999999',
            'aktif' => 'nullable|boolean',
            'tampilLanding' => 'nullable|boolean',
        ]);
    }

    private function validasiKategori(Request $request): array
    {
        return $request->validate([
            'nama' => 'required|string|max:100',
            'deskripsi' => 'nullable|string|max:300',
            'ikon' => 'nullable|string|max:50',
            'urutan' => 'nullable|integer|min:0|max:999999',
            'aktif' => 'nullable|boolean',
        ]);
    }

    /** Id_Users pengelola — dipakai untuk kolom *_By (lihat feedback DDL no.9). */
    private function oleh(): string
    {
        return (string) (session('career_auth.id') ?: session('career_auth.nama', 'ADMIN'));
    }

    private function realId(?string $hash): ?int
    {
        if (! $hash) {
            return null;
        }

        return Hashids::decode($hash)[0] ?? null;
    }

    private function faqAda(int $realId): ?object
    {
        return DB::table(self::TABEL)
            ->where('Id_Master_Faq', $realId)
            ->where('Flag_Cancellation', 'T')
            ->first();
    }

    /** Hash kategori dari klien → id nyata; null bila kosong / tidak dikenal. */
    private function kategoriId(?string $hash): ?int
    {
        $realId = $this->realId($hash);
        if (! $realId) {
            return null;
        }

        $ada = DB::table(self::TABEL_KATEGORI)
            ->where('Id_Master_Faq_Kategori', $realId)
            ->where('Flag_Cancellation', 'T')
            ->exists();

        return $ada ? $realId : null;
    }

    /**
     * Slug dari pertanyaan, unik di antara baris hidup. Sufiks -2, -3, …
     * hanya ditambah bila bentrok.
     */
    private function slugUnik(string $pertanyaan, ?int $kecualiId = null): string
    {
        $dasar = Str::slug(Str::limit($pertanyaan, 110, '')) ?: 'pertanyaan';
        $slug = $dasar;

        for ($n = 2; $n < 1000; $n++) {
            $q = DB::table(self::TABEL)
                ->where('Slug', $slug)
                ->where('Flag_Cancellation', 'T');

            if ($kecualiId !== null) {
                $q->where('Id_Master_Faq', '!=', $kecualiId);
            }

            if (! $q->exists()) {
                return $slug;
            }

            $slug = $dasar . '-' . $n;
        }

        return $dasar . '-' . substr((string) time(), -6);
    }

    private function urutanBerikutnya(?int $kategoriId): int
    {
        $q = DB::table(self::TABEL)->where('Flag_Cancellation', 'T');
        $kategoriId ? $q->where('Faq_Kategori_Id', $kategoriId) : $q->whereNull('Faq_Kategori_Id');

        return ((int) $q->max('Urutan')) + 10;
    }

    private function urutanKategoriBerikutnya(): int
    {
        return ((int) DB::table(self::TABEL_KATEGORI)->where('Flag_Cancellation', 'T')->max('Urutan')) + 10;
    }

    private function ubahFlag($id, string $kolom, string $nilai, string $pesan)
    {
        try {
            $realId = $this->realId($id);
            $terpengaruh = $realId
                ? DB::table(self::TABEL)
                    ->where('Id_Master_Faq', $realId)
                    ->where('Flag_Cancellation', 'T')
                    ->update([
                        $kolom => $nilai,
                        'Updated_At' => now(),
                        'Updated_By' => $this->oleh(),
                    ])
                : 0;

            if (! $terpengaruh) {
                return ResponseHelper::error('Data tidak ditemukan', 404);
            }

            Log::channel('web_career')->info("FAQ #{$id} {$kolom} = {$nilai}");

            return ResponseHelper::success(null, $pesan);
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal ubah {$kolom} FAQ #{$id}: " . $e->getMessage());

            return ResponseHelper::error('Gagal mengubah status', 500);
        }
    }
}
