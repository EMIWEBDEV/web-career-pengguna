<?php

namespace App\Http\Controllers\Career\TalentPool;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Support\CareerShell;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Vinkla\Hashids\Facades\Hashids;

/**
 * WEB CAREER — TALENT POOL.
 *
 * Kolam kandidat "bagus tapi belum terpakai": diisi otomatis saat admin menekan
 * "Masuk Talent Pool" di Worklist (lihat LamaranService::simpanKeTalentPool).
 * Halaman ini hanya MEMBACA + mengelola status kartu (aktif/ditarik/arsip) —
 * tidak menyentuh alur seleksi. Semua data dari N_WEB_CAREERS_Talent_Pool.
 */
class TalentPoolController extends Controller
{
    public function __construct(private \App\Support\Career\LamaranService $svc)
    {
    }

    /** Halaman Inertia (data di-fetch sendiri ke list()). */
    public function index()
    {
        return Inertia::render('Career/admin/talent-pool/talentPool', CareerShell::props('/karir/talent-pool', 'Talent Pool'));
    }

    /** Data kartu Talent Pool + ringkasan + paginasi (server-side). */
    public function list(Request $request)
    {
        try {
            $q = trim((string) $request->query('q', ''));
            $status = strtoupper(trim((string) $request->query('status', '')));
            $tag = trim((string) $request->query('tag', ''));
            $program = trim((string) $request->query('program', ''));
            $divisi = trim((string) $request->query('divisi', ''));
            $sort = (string) $request->query('sort', 'terbaru');
            $page = max(1, (int) $request->query('page', 1));
            $perPage = min(60, max(6, (int) $request->query('perPage', 12)));
            $now = now();

            // Query dasar (search + tag + program + divisi), dipakai ulang.
            $base = fn () => DB::table('N_WEB_CAREERS_Talent_Pool as tp')
                ->leftJoin('N_WEB_CAREERS_Users as u', 'u.Id_Users', '=', 'tp.Id_Users')
                ->when($q !== '', fn ($w) => $w->where(function ($x) use ($q) {
                    $x->where('u.Nama', 'like', "%{$q}%")
                        ->orWhere('tp.Posisi', 'like', "%{$q}%")
                        ->orWhere('tp.Program_Nama', 'like', "%{$q}%")
                        ->orWhere('tp.Tag', 'like', "%{$q}%");
                }))
                ->when($tag !== '', fn ($w) => $w->where('tp.Tag', 'like', "%{$tag}%"))
                ->when($program !== '', fn ($w) => $w->where('tp.Program_Nama', $program))
                ->when($divisi !== '', fn ($w) => $w->where('tp.Departemen', $divisi));

            // Opsi dropdown (seluruh pool, bukan hasil filter) — program & divisi.
            $opsiProgram = DB::table('N_WEB_CAREERS_Talent_Pool')->whereNotNull('Program_Nama')->where('Program_Nama', '!=', '')->distinct()->orderBy('Program_Nama')->pluck('Program_Nama')->values();
            $opsiDivisi = DB::table('N_WEB_CAREERS_Talent_Pool')->whereNotNull('Departemen')->where('Departemen', '!=', '')->distinct()->orderBy('Departemen')->pluck('Departemen')->values();

            // Ringkasan atas set ter-search (TANPA filter status) — status efektif.
            $ringkas = [
                'total' => $base()->count(),
                'aktif' => $base()->where('tp.Status', 'AKTIF')->where(fn ($w) => $w->whereNull('tp.Tanggal_Kedaluwarsa')->orWhere('tp.Tanggal_Kedaluwarsa', '>=', $now))->count(),
                'ditarik' => $base()->where('tp.Status', 'DITARIK')->count(),
                'arsip' => $base()->where('tp.Status', 'ARSIP')->count(),
                'kedaluwarsa' => $base()->where(fn ($w) => $w->where('tp.Status', 'KEDALUWARSA')->orWhere(fn ($z) => $z->where('tp.Status', 'AKTIF')->whereNotNull('tp.Tanggal_Kedaluwarsa')->where('tp.Tanggal_Kedaluwarsa', '<', $now)))->count(),
            ];

            // Query data + filter status efektif.
            $data = $base();
            $this->filterStatus($data, $status, $now);

            $total = (clone $data)->count();

            // Urutan.
            match ($sort) {
                'lama' => $data->orderBy('tp.Id_Talent_Pool'),
                'skor' => $data->orderByDesc('tp.Skor'),
                'kedaluwarsa' => $data->orderBy('tp.Tanggal_Kedaluwarsa'),
                'nama' => $data->orderBy('u.Nama'),
                // BERKAS TERLENGKAP — diurutkan di SERVER, bukan di layar.
                // Mengurutkannya di klien hanya menata ulang 12 kartu yang
                // kebetulan sedang terbuka, lalu menyebut hasilnya "terlengkap"
                // — padahal yang paling lengkap bisa saja ada di halaman 7.
                'berkas' => $data->orderByDesc(DB::raw(
                    '(SELECT COUNT(*) FROM N_WEB_CAREERS_Formulir_Berkas fb
                        JOIN N_WEB_CAREERS_Formulir_Pengisian fp
                          ON fp.Id_Formulir_Pengisian = fb.Formulir_Pengisian_Id
                       WHERE fp.Lamaran_Id = tp.Lamaran_Id)'
                )),
                default => $data->orderByDesc('tp.Id_Talent_Pool'),
            };

            $rows = $data->offset(($page - 1) * $perPage)->limit($perPage)
                ->select('tp.*', 'u.Nama as Kandidat', 'u.Email as KandidatEmail')->get();

            return ResponseHelper::success([
                'data' => $rows->map(fn ($r) => $this->bentukKartu($r, $now))->values(),
                'ringkas' => $ringkas,
                'opsi' => ['program' => $opsiProgram, 'divisi' => $opsiDivisi],
                'page' => $page,
                'perPage' => $perPage,
                'total' => $total,
                'totalPage' => (int) ceil($total / $perPage),
            ], 'Data talent pool dimuat');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('Gagal memuat talent pool: ' . $e->getMessage());

            return ResponseHelper::error('Gagal memuat data talent pool', 500);
        }
    }

    /** Terapkan filter STATUS EFEKTIF (KEDALUWARSA dihitung dari tanggal). */
    private function filterStatus($query, string $status, $now): void
    {
        match ($status) {
            'AKTIF' => $query->where('tp.Status', 'AKTIF')->where(fn ($w) => $w->whereNull('tp.Tanggal_Kedaluwarsa')->orWhere('tp.Tanggal_Kedaluwarsa', '>=', $now)),
            'KEDALUWARSA' => $query->where(fn ($w) => $w->where('tp.Status', 'KEDALUWARSA')->orWhere(fn ($z) => $z->where('tp.Status', 'AKTIF')->whereNotNull('tp.Tanggal_Kedaluwarsa')->where('tp.Tanggal_Kedaluwarsa', '<', $now))),
            'DITARIK' => $query->where('tp.Status', 'DITARIK'),
            'ARSIP' => $query->where('tp.Status', 'ARSIP'),
            default => null,
        };
    }

    /** Bentuk satu baris DB → kartu untuk frontend. */
    private function bentukKartu($r, $now): array
    {
        $exp = $r->Tanggal_Kedaluwarsa ? \Illuminate\Support\Carbon::parse($r->Tanggal_Kedaluwarsa) : null;
        $habis = $exp ? $exp->isPast() : false;
        $status = ($r->Status === 'AKTIF' && $habis) ? 'KEDALUWARSA' : $r->Status;

        return [
            'id' => Hashids::encode($r->Id_Talent_Pool),
            'kandidat' => $r->Kandidat ?: '—',
            'email' => $r->KandidatEmail,
            'posisi' => $r->Posisi ?: '—',
            'program' => $r->Program_Nama ?: '—',
            'departemen' => $r->Departemen ?: null,
            'tahapAsal' => $r->Tahap_Asal,
            'skor' => $r->Skor !== null ? (float) $r->Skor : null,
            'tag' => $r->Tag,
            'catatan' => $r->Catatan,
            'status' => $status,
            'tanggalKedaluwarsa' => $exp ? $exp->format('d M Y') : null,
            'sisaHari' => $exp ? (int) $now->diffInDays($exp, false) : null,
            'kedaluwarsa' => $habis,
            'createdBy' => $r->Created_By ?: 'Sistem',
            // DIRAPIKAN DI SINI, bukan di layar. Nilai mentah SQL Server ikut
            // membawa pecahan detik ("2026-08-07 11:51:21.337") dan terbaca
            // seperti data yang bocor dari dalam mesin.
            'createdAt' => $r->Created_At
                ? \Illuminate\Support\Carbon::parse($r->Created_At)->format('d M Y H:i')
                : null,
            // Bentuk mentah tetap ikut — dipakai layar untuk menghitung berapa
            // lama kartu ini sudah menunggu di kolam.
            'createdAtRaw' => $r->Created_At,
        ];
    }

    /** Aksi massal: ubah status banyak kartu sekaligus (arsip/aktif/ditarik). */
    public function bulk(Request $request)
    {
        try {
            $data = $request->validate([
                'ids' => 'required|array|min:1',
                'ids.*' => 'string',
                'aksi' => 'required|in:ARSIP,AKTIF,DITARIK,HAPUS,PERPANJANG',
            ]);

            $realIds = collect($data['ids'])->map(fn ($h) => Hashids::decode($h)[0] ?? null)->filter()->values()->all();
            if (! $realIds) {
                return ResponseHelper::error('Tidak ada kartu valid.', 422);
            }

            $now = now();
            $nama = session('career_auth.nama', 'ADMIN');
            $uid = session('career_auth.id');
            $tabel = DB::table('N_WEB_CAREERS_Talent_Pool')->whereIn('Id_Talent_Pool', $realIds);

            if ($data['aksi'] === 'HAPUS') {
                $n = $tabel->delete();
            } elseif ($data['aksi'] === 'PERPANJANG') {
                $n = $tabel->update(['Status' => 'AKTIF', 'Tanggal_Kedaluwarsa' => \App\Support\Career\LamaranService::hitungKedaluwarsa($now), 'Updated_At' => $now, 'Updated_By' => $nama, 'Updated_By_Id' => $uid]);
            } else {
                $n = $tabel->update(['Status' => $data['aksi'], 'Updated_At' => $now, 'Updated_By' => $nama, 'Updated_By_Id' => $uid]);
            }

            Log::channel('web_career')->info("Talent Pool bulk {$data['aksi']}: {$n} kartu oleh {$nama}");

            return ResponseHelper::success(['jumlah' => $n], "{$n} kartu diproses.");
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ResponseHelper::error(collect($e->errors())->flatten()->first() ?? 'Data tidak valid', 422);
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('Gagal bulk talent pool: ' . $e->getMessage());

            return ResponseHelper::error('Gagal memproses aksi massal', 500);
        }
    }

    /** Ekspor CSV daftar Talent Pool (mengikuti filter search/tag/status). */
    public function export(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $status = strtoupper(trim((string) $request->query('status', '')));
        $tag = trim((string) $request->query('tag', ''));
        $now = now();

        $query = DB::table('N_WEB_CAREERS_Talent_Pool as tp')
            ->leftJoin('N_WEB_CAREERS_Users as u', 'u.Id_Users', '=', 'tp.Id_Users')
            ->when($q !== '', fn ($w) => $w->where(function ($x) use ($q) {
                $x->where('u.Nama', 'like', "%{$q}%")->orWhere('tp.Posisi', 'like', "%{$q}%")->orWhere('tp.Program_Nama', 'like', "%{$q}%")->orWhere('tp.Tag', 'like', "%{$q}%");
            }))
            ->when($tag !== '', fn ($w) => $w->where('tp.Tag', 'like', "%{$tag}%"));
        $this->filterStatus($query, $status, $now);

        $rows = $query->orderByDesc('tp.Id_Talent_Pool')->select('tp.*', 'u.Nama as Kandidat', 'u.Email as KandidatEmail')->get();

        $nama = 'talent-pool-' . $now->format('Ymd-His') . '.csv';
        $header = ['Kandidat', 'Email', 'Posisi', 'Program', 'Tahap Asal', 'Skor', 'Tag', 'Status', 'Masuk', 'Kedaluwarsa'];

        return response()->streamDownload(function () use ($rows, $now, $header) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $header);
            foreach ($rows as $r) {
                $exp = $r->Tanggal_Kedaluwarsa ? \Illuminate\Support\Carbon::parse($r->Tanggal_Kedaluwarsa) : null;
                $st = ($r->Status === 'AKTIF' && $exp && $exp->isPast()) ? 'KEDALUWARSA' : $r->Status;
                fputcsv($out, [
                    $r->Kandidat, $r->KandidatEmail, $r->Posisi, $r->Program_Nama, $r->Tahap_Asal,
                    $r->Skor, $r->Tag, $st,
                    $r->Tanggal_Masuk ? \Illuminate\Support\Carbon::parse($r->Tanggal_Masuk)->format('Y-m-d') : '',
                    $exp ? $exp->format('Y-m-d') : '',
                ]);
            }
            fclose($out);
        }, $nama, ['Content-Type' => 'text/csv']);
    }

    /** Ubah kartu: status (AKTIF/DITARIK/ARSIP), tag, catatan. */
    public function ubah(Request $request, $id)
    {
        try {
            $realId = Hashids::decode($id)[0] ?? null;
            if (! $realId) {
                return ResponseHelper::error('Data tidak valid.', 422);
            }

            $data = $request->validate([
                'status' => 'required|in:AKTIF,DITARIK,ARSIP',
                'tag' => 'nullable|string|max:150',
                'catatan' => 'nullable|string',
            ]);

            $ubah = [
                'Status' => $data['status'],
                'Updated_At' => now(),
                'Updated_By' => session('career_auth.nama', 'ADMIN'),
                'Updated_By_Id' => session('career_auth.id'),
            ];
            if ($request->has('tag')) {
                $ubah['Tag'] = $data['tag'] ?? null;
            }
            if ($request->has('catatan')) {
                $ubah['Catatan'] = $data['catatan'] ?? null;
            }

            $terpengaruh = DB::table('N_WEB_CAREERS_Talent_Pool')->where('Id_Talent_Pool', $realId)->update($ubah);
            if (! $terpengaruh) {
                return ResponseHelper::error('Data tidak ditemukan', 404);
            }

            Log::channel('web_career')->info("Talent Pool #{$realId} diperbarui → {$data['status']}");

            return ResponseHelper::success(null, 'Kartu talent pool diperbarui');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ResponseHelper::error(collect($e->errors())->flatten()->first() ?? 'Data tidak valid', 422);
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal ubah talent pool #{$id}: " . $e->getMessage());

            return ResponseHelper::error('Gagal memperbarui data', 500);
        }
    }

    /**
     * GET /talent-pool/{id}/detail — PROFIL LENGKAP satu kartu.
     *
     * Kartu di daftar sengaja tipis. Yang dibutuhkan rekruter sebelum menekan
     * "Tarik" justru tiga hal yang tidak muat di sana: siapa orangnya, berkasnya
     * lengkap atau belum, dan apa saja yang pernah ia lalui. Dibaca saat dibuka
     * — bukan disalin ke kartu — supaya tidak ada data yang membeku.
     */
    public function detail($id)
    {
        try {
            $realId = Hashids::decode($id)[0] ?? null;
            if (! $realId) {
                return ResponseHelper::error('Data tidak valid.', 422);
            }

            $kartu = DB::table('N_WEB_CAREERS_Talent_Pool as tp')
                ->leftJoin('N_WEB_CAREERS_Users as u', 'u.Id_Users', '=', 'tp.Id_Users')
                ->where('tp.Id_Talent_Pool', $realId)
                ->select('tp.*', 'u.Nama as Kandidat', 'u.Email as KandidatEmail')
                ->first();

            if (! $kartu) {
                return ResponseHelper::error('Kartu tidak ditemukan', 404);
            }

            $profil = \App\Support\Career\ProfilTalenta::rakit(
                (int) $kartu->Lamaran_Id,
                $kartu->Id_Users ? (int) $kartu->Id_Users : null
            );

            return ResponseHelper::success(array_merge(
                $this->bentukKartu($kartu, now()),
                $profil
            ), 'Detail talenta dimuat');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal memuat detail talent pool #{$id}: " . $e->getMessage());

            return ResponseHelper::error('Gagal memuat detail talenta', 500);
        }
    }

    /** Perpanjang masa berlaku: reset kedaluwarsa = sekarang + durasi master aktif. */
    public function perpanjang($id)
    {
        try {
            $realId = Hashids::decode($id)[0] ?? null;
            if (! $realId) {
                return ResponseHelper::error('Data tidak valid.', 422);
            }
            $now = now();
            $terpengaruh = DB::table('N_WEB_CAREERS_Talent_Pool')->where('Id_Talent_Pool', $realId)->update([
                'Status' => 'AKTIF',
                'Tanggal_Kedaluwarsa' => \App\Support\Career\LamaranService::hitungKedaluwarsa($now),
                'Updated_At' => $now,
                'Updated_By' => session('career_auth.nama', 'ADMIN'),
                'Updated_By_Id' => session('career_auth.id'),
            ]);
            if (! $terpengaruh) {
                return ResponseHelper::error('Data tidak ditemukan', 404);
            }

            Log::channel('web_career')->info("Talent Pool #{$realId} diperpanjang");

            return ResponseHelper::success(null, 'Masa berlaku diperpanjang');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal perpanjang talent pool #{$id}: " . $e->getMessage());

            return ResponseHelper::error('Gagal memperpanjang', 500);
        }
    }

    /** Kata kunci signifikan dari nama posisi (untuk skor kecocokan rumpun). */
    private function kataKunci(?string $s): array
    {
        $stop = ['dan', 'staff', 'senior', 'junior', 'officer', 'the', 'of', 'for'];

        return collect(preg_split('/[^a-z0-9]+/i', strtolower((string) $s)))
            ->filter(fn ($w) => strlen($w) >= 3 && ! in_array($w, $stop, true))
            ->unique()->values()->all();
    }

    /** Daftar lowongan BUKA (lintas MPP) untuk menarik kandidat, diurut kecocokan. */
    public function lowongan($id)
    {
        try {
            $realId = Hashids::decode($id)[0] ?? null;
            $kartu = DB::table('N_WEB_CAREERS_Talent_Pool')->where('Id_Talent_Pool', $realId)->first();
            if (! $kartu) {
                return ResponseHelper::error('Kartu tidak ditemukan', 404);
            }
            $asalMpp = $kartu->Program_Posisi_Id
                ? DB::table('N_WEB_CAREERS_Program_Posisi')->where('Id_Program_Posisi', $kartu->Program_Posisi_Id)->value('Mpp_Ref')
                : null;
            $asal = $this->kataKunci($kartu->Posisi);

            $rows = DB::table('N_WEB_CAREERS_Program_Posisi as x')
                ->join('N_WEB_CAREERS_Program as p', 'p.Id_Program', '=', 'x.Program_Id')
                ->where('p.Status', 'BERJALAN')
                ->where('x.Status', 'BUKA')
                ->when($kartu->Program_Posisi_Id, fn ($w) => $w->where('x.Id_Program_Posisi', '!=', $kartu->Program_Posisi_Id))
                ->orderBy('p.Nama')
                ->select('x.Id_Program_Posisi', 'x.Posisi', 'x.Departemen', 'x.Level', 'x.Kuota', 'x.Mpp_Ref', 'p.Nama as ProgramNama', 'p.Kategori')
                ->get();

            $data = $rows->map(function ($r) use ($asal, $asalMpp) {
                $skor = count(array_intersect($asal, $this->kataKunci($r->Posisi)));

                return [
                    'posisiId' => Hashids::encode($r->Id_Program_Posisi),
                    'posisi' => $r->Posisi,
                    'departemen' => $r->Departemen ?: '—',
                    'level' => $r->Level,
                    'kuota' => (int) $r->Kuota,
                    'program' => $r->ProgramNama,
                    'kategori' => $r->Kategori,
                    'lintasMpp' => ($r->Mpp_Ref ?? null) !== $asalMpp,
                    'serumpun' => $skor > 0,
                    'skorCocok' => $skor,
                ];
            })->sortByDesc('skorCocok')->values();

            return ResponseHelper::success($data, 'Lowongan tujuan');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('Gagal memuat lowongan tarik: ' . $e->getMessage());

            return ResponseHelper::error('Gagal memuat lowongan', 500);
        }
    }

    /** Tahap alur pada posisi tujuan — untuk memilih titik masuk (entry step). */
    public function tahapLowongan($posisiId)
    {
        try {
            $realId = Hashids::decode($posisiId)[0] ?? null;
            $posisi = DB::table('N_WEB_CAREERS_Program_Posisi')->where('Id_Program_Posisi', $realId)->first();
            if (! $posisi) {
                return ResponseHelper::error('Posisi tidak ditemukan', 404);
            }
            $program = DB::table('N_WEB_CAREERS_Program')->where('Id_Program', $posisi->Program_Id)->first();
            $alur = $program ? DB::table('N_WEB_CAREERS_Master_Alur')->where('Kode', $program->Alur_Kode)->first() : null;
            $tahap = $alur
                ? DB::table('N_WEB_CAREERS_Master_Alur_Tahap')->where('Master_Alur_Id', $alur->Id_Master_Alur)->orderBy('Urutan')->get()
                : collect();

            return ResponseHelper::success([
                'program' => $program->Nama ?? null,
                'tahap' => $tahap->map(fn ($t) => ['urutan' => (int) $t->Urutan, 'label' => $t->Label, 'tipe' => $t->Tipe_Tahap_Kode])->values(),
            ], 'Tahap lowongan');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('Gagal memuat tahap lowongan: ' . $e->getMessage());

            return ResponseHelper::error('Gagal memuat tahap', 500);
        }
    }

    /** Eksekusi tarik kandidat ke lowongan tujuan pada tahap terpilih. */
    public function tarik(Request $request, $id)
    {
        try {
            $realId = Hashids::decode($id)[0] ?? null;
            if (! $realId) {
                return ResponseHelper::error('Kartu tidak valid.', 422);
            }
            $data = $request->validate([
                'posisiId' => 'required|string',
                'mulaiDariUrutan' => 'required|integer|min:1',
            ]);
            $posisiRealId = Hashids::decode($data['posisiId'])[0] ?? null;
            if (! $posisiRealId) {
                return ResponseHelper::error('Posisi tujuan tidak valid.', 422);
            }

            $res = $this->svc->tarikDariTalentPool((int) $realId, (int) $posisiRealId, (int) $data['mulaiDariUrutan'], (int) session('career_auth.id'));
            if (! $res['ok']) {
                return ResponseHelper::error($res['pesan'], 422);
            }

            return ResponseHelper::success(null, $res['pesan']);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ResponseHelper::error(collect($e->errors())->flatten()->first() ?? 'Data tidak valid', 422);
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal tarik talent pool #{$id}: " . $e->getMessage());

            return ResponseHelper::error('Gagal menarik kandidat', 500);
        }
    }

    /** Hapus kartu dari kolam (permanen). */
    public function destroy($id)
    {
        try {
            $realId = Hashids::decode($id)[0] ?? null;
            $terpengaruh = DB::table('N_WEB_CAREERS_Talent_Pool')->where('Id_Talent_Pool', $realId)->delete();
            if (! $terpengaruh) {
                return ResponseHelper::error('Data tidak ditemukan', 404);
            }

            Log::channel('web_career')->info("Talent Pool #{$realId} dihapus");

            return ResponseHelper::success(null, 'Kartu dihapus dari Talent Pool');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal hapus talent pool #{$id}: " . $e->getMessage());

            return ResponseHelper::error('Gagal menghapus data', 500);
        }
    }
}
