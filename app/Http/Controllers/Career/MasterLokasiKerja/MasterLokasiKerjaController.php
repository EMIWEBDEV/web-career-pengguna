<?php

namespace App\Http\Controllers\Career\MasterLokasiKerja;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Support\CareerShell;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

/**
 * WEB CAREER — MASTER LOKASI KERJA (kantor pusat & cabang per kota).
 *
 * Tabel: N_HRIS_Master_Lokasi. JANGAN tertukar dengan Master Lokasi
 * (N_WEB_CAREERS_Master_Lokasi) yang isinya kantor & vendor untuk penjadwalan
 * wawancara/MCU — beda tabel, beda kegunaan, beda halaman.
 *
 * Kode_Lokasi dirujuk HRIS_Transaksi_GForm.Kode_Lokasi TANPA foreign key, dan
 * CareerLandingController membacanya untuk seksi kantor di landing page
 * (Status_Aktif = 'Y', kantor pusat diurutkan paling depan). Dua akibatnya:
 *   1. Kode DIKUNCI setelah dibuat — satu ketikan bisa memutus rujukan transaksi
 *      tanpa suara karena tidak ada FK yang menahannya.
 *   2. Baris yang sudah dipakai transaksi tidak boleh dihapus.
 *
 * CATATAN SKEMA: primary key-nya kunci alami varchar (Kode_Lokasi), bukan int
 * identity — jadi id TIDAK di-Hashids seperti master lain; kodenya memang sudah
 * dipertontonkan di UI dan dipakai lintas sistem. Tabel ini juga tidak punya
 * kolom Created_By/Updated_By, jadi jejak audit hanya berupa waktu.
 */
class MasterLokasiKerjaController extends Controller
{
    private const TABEL = 'N_HRIS_Master_Lokasi';

    private const PK = 'Kode_Lokasi';

    /** Flag di tabel ini memakai 'Y' / 'T' (Ya/Tidak), bukan 'Y' / 'N'. */
    private const YA = 'Y';

    private const TIDAK = 'T';

    public function index()
    {
        return Inertia::render(
            'Career/admin/master-lokasi-kerja/masterLokasiKerja',
            CareerShell::props('/master-lokasi-kerja', 'Master Lokasi Kerja')
        );
    }

    /** Jumlah transaksi GForm per kode lokasi — badge di tabel sekaligus alasan hapus dikunci. */
    private function pemakaian()
    {
        return DB::table('HRIS_Transaksi_GForm')
            ->select('Kode_Lokasi', DB::raw('COUNT(*) as Jumlah'))
            ->whereNotNull('Kode_Lokasi')
            ->groupBy('Kode_Lokasi');
    }

    /**
     * Query dasar + filter (q / pulau / status). Dipakai bersama oleh baris,
     * penghitung total, DAN penghitung cacah facet.
     *
     * $abaikan memungkinkan satu penyaring dilewati — itulah cara cacah facet
     * dihitung: cacah pulau mengabaikan filter pulau, cacah status mengabaikan
     * filter status. Tanpa itu, angka pada chip selalu sama dengan jumlah baris
     * yang sedang tampil dan tidak memberi tahu apa pun.
     */
    private function dasarFilter(Request $request, array $abaikan = [])
    {
        $base = DB::table(self::TABEL . ' as m');

        $q = trim((string) $request->query('q', ''));
        if ($q !== '' && ! in_array('q', $abaikan, true)) {
            $base->where(function ($w) use ($q) {
                $w->where('m.Kode_Lokasi', 'like', "%{$q}%")
                    ->orWhere('m.Nama_Lokasi', 'like', "%{$q}%")
                    ->orWhere('m.Provinsi', 'like', "%{$q}%")
                    ->orWhere('m.Keterangan', 'like', "%{$q}%");
            });
        }

        $pulau = trim((string) $request->query('pulau', ''));
        if ($pulau !== '' && ! in_array('pulau', $abaikan, true)) {
            $base->where('m.Pulau', $pulau);
        }

        $status = strtoupper(trim((string) $request->query('status', '')));
        if (! in_array('status', $abaikan, true)) {
            if ($status === 'AKTIF') {
                $base->where('m.Status_Aktif', self::YA);
            } elseif ($status === 'NONAKTIF') {
                $base->where('m.Status_Aktif', '!=', self::YA);
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
        $facetAktif = (clone $statusBase)->where('m.Status_Aktif', self::YA)->count();

        $pulauBase = $this->dasarFilter($request, ['pulau']);
        $cacahPulau = (clone $pulauBase)
            ->select('m.Pulau', DB::raw('COUNT(*) as Jumlah'))
            ->groupBy('m.Pulau')
            ->pluck('Jumlah', 'Pulau');

        // Daftar pulau diambil dari SELURUH tabel, bukan dari hasil terfilter —
        // supaya chip yang sedang bernilai 0 tetap tampil dan bisa dibatalkan.
        $semuaPulau = DB::table(self::TABEL)->distinct()->orderBy('Pulau')->pluck('Pulau');

        $total = (int) DB::table(self::TABEL)->count();
        $aktif = (int) DB::table(self::TABEL)->where('Status_Aktif', self::YA)->count();

        return [
            'total' => $total,
            'aktif' => $aktif,
            'nonaktif' => $total - $aktif,
            'dipakai' => (int) DB::table('HRIS_Transaksi_GForm')
                ->whereIn('Kode_Lokasi', DB::table(self::TABEL)->select(self::PK))
                ->count(),
            'status' => [
                'semua' => $facetSemua,
                'aktif' => $facetAktif,
                'nonaktif' => $facetSemua - $facetAktif,
            ],
            'pulau' => $semuaPulau
                ->map(fn ($p) => ['nama' => $p, 'jumlah' => (int) ($cacahPulau[$p] ?? 0)])
                ->values(),
            'pulauSemua' => (clone $pulauBase)->count(),
        ];
    }

    /** Data lokasi kerja — PAGINASI + FILTER + URUT server-side. */
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
                ->leftJoinSub($this->pemakaian(), 'g', 'g.Kode_Lokasi', '=', 'm.Kode_Lokasi')
                ->select('m.*', DB::raw('ISNULL(g.Jumlah, 0) as Dipakai'));

            if ($sortBy === 'dipakai') {
                // Nama jadi pemecah seri: tanpa itu, banyaknya nilai 0 membuat
                // urutan antarhalaman tidak stabil dan baris bisa terlihat dua kali.
                $rows->orderBy('Dipakai', $sortDir)->orderBy('m.Nama_Lokasi');
            } else {
                // Urutan bawaan sama dengan landing page: kantor pusat dulu, lalu abjad.
                $rows->orderByRaw("CASE WHEN m.Status_HO = 'Y' THEN 0 ELSE 1 END")->orderBy('m.Nama_Lokasi');
            }

            $rows = $rows->forPage($page, $perPage)
                ->get()
                ->map(fn ($r) => [
                    'kode' => $r->Kode_Lokasi,
                    'nama' => $r->Nama_Lokasi,
                    'provinsi' => $r->Provinsi,
                    'pulau' => $r->Pulau,
                    'kantorPusat' => $r->Status_HO === self::YA,
                    'status' => $r->Status_Aktif === self::YA ? 'AKTIF' : 'NONAKTIF',
                    'keterangan' => $r->Keterangan,
                    'dipakai' => (int) $r->Dipakai,
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
            ], 'Data lokasi kerja dimuat');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('Gagal memuat lokasi kerja: ' . $e->getMessage());

            return ResponseHelper::error('Gagal memuat data lokasi kerja', 500);
        }
    }

    /** Batas panjang mengikuti lebar kolom asli. Kode hanya divalidasi saat tambah. */
    private function rules(bool $baru): array
    {
        return array_filter([
            'kode' => $baru ? 'required|string|max:20|regex:/^[A-Za-z0-9_-]+$/' : null,
            'nama' => 'required|string|max:100',
            'provinsi' => 'required|string|max:100',
            'pulau' => 'required|string|max:50',
            'keterangan' => 'nullable|string|max:255',
            'kantorPusat' => 'nullable|boolean',
        ]);
    }

    /**
     * Kantor pusat hanya boleh satu: menandai yang baru menurunkan yang lama.
     * Dipanggil DI DALAM transaksi bersama insert/update-nya, supaya tidak pernah
     * ada keadaan antara dengan dua kantor pusat atau nol kantor pusat.
     */
    private function turunkanKantorPusatLain(?string $kecualiKode, $now): void
    {
        DB::table(self::TABEL)
            ->where('Status_HO', self::YA)
            ->when($kecualiKode, fn ($q) => $q->where(self::PK, '!=', $kecualiKode))
            ->update(['Status_HO' => self::TIDAK, 'Updated_At' => $now]);
    }

    public function store(Request $request)
    {
        try {
            $data = $request->validate($this->rules(true));
            $kode = strtoupper(trim($data['kode']));

            if (DB::table(self::TABEL)->where(self::PK, $kode)->exists()) {
                return ResponseHelper::error("Kode lokasi \"{$kode}\" sudah dipakai.", 422);
            }

            $ho = (bool) ($data['kantorPusat'] ?? false);
            $now = now();

            DB::transaction(function () use ($data, $kode, $ho, $now) {
                if ($ho) {
                    $this->turunkanKantorPusatLain(null, $now);
                }

                DB::table(self::TABEL)->insert([
                    self::PK => $kode,
                    'Nama_Lokasi' => trim($data['nama']),
                    'Provinsi' => trim($data['provinsi']),
                    'Pulau' => trim($data['pulau']),
                    'Status_HO' => $ho ? self::YA : self::TIDAK,
                    'Status_Aktif' => self::YA,
                    'Keterangan' => isset($data['keterangan']) ? trim($data['keterangan']) : null,
                    'Created_At' => $now,
                    'Updated_At' => $now,
                ]);
            });

            Log::channel('web_career')->info("Lokasi kerja dibuat ({$kode}) oleh " . session('career_auth.nama', 'ADMIN'));

            return ResponseHelper::success(null, 'Lokasi kerja berhasil ditambahkan', 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ResponseHelper::error(collect($e->errors())->flatten()->first() ?? 'Data tidak valid', 422);
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('Gagal membuat lokasi kerja: ' . $e->getMessage());

            return ResponseHelper::error('Gagal menyimpan data', 500);
        }
    }

    /** Kode SENGAJA tidak ikut diperbarui — lihat catatan kelas. */
    public function update(Request $request, string $kode)
    {
        try {
            $row = DB::table(self::TABEL)->where(self::PK, $kode)->first();
            if (! $row) {
                return ResponseHelper::error('Data tidak ditemukan', 404);
            }

            $data = $request->validate($this->rules(false));
            $ho = (bool) ($data['kantorPusat'] ?? false);
            $now = now();

            DB::transaction(function () use ($data, $kode, $ho, $now) {
                if ($ho) {
                    $this->turunkanKantorPusatLain($kode, $now);
                }

                DB::table(self::TABEL)->where(self::PK, $kode)->update([
                    'Nama_Lokasi' => trim($data['nama']),
                    'Provinsi' => trim($data['provinsi']),
                    'Pulau' => trim($data['pulau']),
                    'Status_HO' => $ho ? self::YA : self::TIDAK,
                    'Keterangan' => isset($data['keterangan']) ? trim($data['keterangan']) : null,
                    'Updated_At' => $now,
                ]);
            });

            Log::channel('web_career')->info("Lokasi kerja {$kode} diperbarui");

            return ResponseHelper::success(null, 'Lokasi kerja diperbarui');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ResponseHelper::error(collect($e->errors())->flatten()->first() ?? 'Data tidak valid', 422);
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal update lokasi kerja {$kode}: " . $e->getMessage());

            return ResponseHelper::error('Gagal memperbarui data', 500);
        }
    }

    public function toggle(Request $request, string $kode)
    {
        try {
            $aktif = $request->boolean('aktif');

            $terpengaruh = DB::table(self::TABEL)->where(self::PK, $kode)->update([
                'Status_Aktif' => $aktif ? self::YA : self::TIDAK,
                'Updated_At' => now(),
            ]);

            if (! $terpengaruh) {
                return ResponseHelper::error('Data tidak ditemukan', 404);
            }

            Log::channel('web_career')->info("Lokasi kerja {$kode} status " . ($aktif ? 'AKTIF' : 'NONAKTIF'));

            return ResponseHelper::success(null, 'Status diperbarui');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal toggle lokasi kerja {$kode}: " . $e->getMessage());

            return ResponseHelper::error('Gagal mengubah status', 500);
        }
    }

    public function destroy(string $kode)
    {
        try {
            $row = DB::table(self::TABEL)->where(self::PK, $kode)->first();
            if (! $row) {
                return ResponseHelper::error('Data tidak ditemukan', 404);
            }

            // Dihitung ulang di server — UI sudah mengunci tombolnya, tapi
            // permintaan bisa datang dari mana saja.
            $dipakai = DB::table('HRIS_Transaksi_GForm')->where('Kode_Lokasi', $kode)->count();
            if ($dipakai > 0) {
                return ResponseHelper::error("Lokasi ini dipakai {$dipakai} transaksi GForm — nonaktifkan saja.", 422);
            }

            DB::table(self::TABEL)->where(self::PK, $kode)->delete();
            Log::channel('web_career')->info("Lokasi kerja {$kode} dihapus");

            return ResponseHelper::success(null, 'Lokasi kerja dihapus');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal hapus lokasi kerja {$kode}: " . $e->getMessage());

            return ResponseHelper::error('Gagal menghapus data', 500);
        }
    }
}
