<?php

namespace App\Http\Controllers\Career\PemulihanBerkas;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Support\Career\BerkasFormulir;
use App\Support\Career\GcsBerkas;
use App\Support\Career\PemulihanBerkas;
use App\Support\CareerShell;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Vinkla\Hashids\Facades\Hashids;

/**
 * WEB CAREER — PANEL PEMULIHAN BERKAS.
 *
 * Tempat admin mengunggah berkas kandidat yang hilang atau tertinggal (CV, KTP,
 * KK, sertifikat…) — dicatat atas nama kandidat, dengan jejak admin yang
 * mengunggah beserta alasannya. Apa yang dianggap "hilang", siapa yang boleh
 * apa, dan jangkauan datanya dijelaskan di App\Support\Career\PemulihanBerkas.
 *
 * Aturan yang ditegakkan DI SINI (bukan cuma di layar):
 *   1. Tanpa hak TAMBAH_MANUAL, yang boleh diunggah hanya isian yang memang
 *      direkomendasikan sistem.
 *   2. Isian yang SUDAH berberkas ditolak (409) — kecuali pemegang hak TIMPA
 *      yang mengirim `timpa=1` setelah dialog peringatan. Berkas lama TIDAK
 *      dihapus: yang baru ditambahkan di sampingnya (keputusan user 28 Sep 2026).
 *   3. Pemeriksaan "sudah ada" diulang DI BAWAH KUNCI baris pengisian, supaya
 *      dua admin yang memulihkan isian yang sama bersamaan tidak sama-sama lolos.
 */
class PemulihanBerkasController extends Controller
{
    private const URL = '/karir/pemulihan-berkas';

    public function index()
    {
        return Inertia::render(
            'Career/admin/pemulihan-berkas/PemulihanBerkas',
            CareerShell::props(self::URL, 'Pemulihan Berkas', ['hak' => PemulihanBerkas::hak()])
        );
    }

    /**
     * GET — rekomendasi sistem: kandidat yang berkasnya hilang, dikelompokkan
     * menurut cakupan (per program ATAU per lowongan/MPP) dan alur.
     */
    public function daftar(Request $request)
    {
        $status = $request->query('status') === 'SEMUA' ? 'SEMUA' : 'AKTIF';
        $mode = $request->query('mode') === 'loker' ? 'loker' : 'program';
        $programId = self::idAtauNull((string) $request->query('program', ''));
        $lokerDipilih = trim((string) $request->query('loker', ''));
        $alur = (string) $request->query('alur', 'SEMUA');
        $q = mb_strtolower(trim((string) $request->query('q', '')));

        $lamaran = PemulihanBerkas::lamaran($status);
        $temuan = PemulihanBerkas::temukanHilang($lamaran->pluck('Id_Lamaran')->all());
        $kena = fn ($l) => ! empty($temuan[$l->Id_Lamaran]);
        $jumlahHilang = fn ($g) => $g->sum(fn ($l) => count($temuan[$l->Id_Lamaran] ?? []));

        // ── PEMILIH PROGRAM & LOWONGAN — hitungannya dari pindaian yang sama ──
        $program = $lamaran->groupBy('Program_Id')->map(function ($g) use ($kena, $jumlahHilang) {
            $a = $g->first();

            return [
                'id' => Hashids::encode($a->Program_Id),
                'nama' => $a->ProgramNama,
                'kategori' => $a->Kategori,
                'warna' => $a->Warna,
                'status' => $a->ProgramStatus,
                'pelamar' => $g->count(),
                'perluPulih' => $g->filter($kena)->count(),
                'berkasHilang' => $jumlahHilang($g),
            ];
        })->sortBy([['perluPulih', 'desc'], ['nama', 'asc']])->values();

        $loker = $lamaran->groupBy(fn ($l) => PemulihanBerkas::kunciLoker($l))->map(function ($g, $kunci) use ($kena, $jumlahHilang) {
            $a = $g->first();

            return [
                'kunci' => (string) $kunci,
                'mppRef' => trim((string) ($a->Mpp_Ref ?? '')) ?: null,
                'posisi' => $a->Posisi,
                'departemen' => $a->Departemen,
                'lokasi' => $a->Lokasi,
                'program' => $a->ProgramNama,
                'kategori' => $a->Kategori,
                'pelamar' => $g->count(),
                'perluPulih' => $g->filter($kena)->count(),
                'berkasHilang' => $jumlahHilang($g),
            ];
        })->sortBy([['perluPulih', 'desc'], ['posisi', 'asc']])->values();

        // ── CAKUPAN TERPILIH ────────────────────────────────────────────────
        $cakupan = $lamaran->filter(fn ($l) => $mode === 'program'
            ? ($programId === null || (int) $l->Program_Id === $programId)
            : ($lokerDipilih === '' || PemulihanBerkas::kunciLoker($l) === $lokerDipilih));

        // ── ALUR — pilihan dari cakupan, bawaannya SEMUA ──────────────────
        // Berbeda dari papan lowongan worklist yang membuka alur terbaru: di
        // sini yang dicari berkas hilang, dan menyembunyikan kandidat alur
        // lama berarti menyembunyikan berkas yang justru harus dipulihkan.
        $alurOpsi = $cakupan->filter(fn ($l) => $l->Master_Alur_Id !== null)
            ->groupBy('Master_Alur_Id')
            ->map(fn ($g) => [
                'id' => (int) $g->first()->Master_Alur_Id,
                'nama' => $g->first()->AlurNama ?: 'Alur #' . $g->first()->Master_Alur_Id,
                'versi' => property_exists($g->first(), 'AlurVersi') ? (int) ($g->first()->AlurVersi ?: 1) : null,
                'pelamar' => $g->count(),
                'perluPulih' => $g->filter($kena)->count(),
            ])
            ->sortByDesc('id')
            ->values();

        $alurAktif = $alur !== 'SEMUA' && $alurOpsi->contains('id', (int) $alur) ? (int) $alur : 'SEMUA';
        if ($alurAktif !== 'SEMUA') {
            $cakupan = $cakupan->filter(fn ($l) => (int) $l->Master_Alur_Id === $alurAktif);
        }

        // ── KANDIDAT — hanya yang berkasnya hilang (rekomendasi sistem) ─────
        $kandidat = $cakupan->filter($kena)
            ->filter(fn ($l) => $q === '' || str_contains(
                mb_strtolower(implode(' ', [$l->KandidatNama, $l->Kode, $l->Email, $l->Posisi])),
                $q,
            ))
            ->map(fn ($l) => [
                'id' => Hashids::encode($l->Id_Lamaran),
                'kode' => $l->Kode,
                'nama' => $l->KandidatNama ?: '(tanpa nama)',
                'email' => $l->Email,
                'posisi' => $l->Posisi,
                'mppRef' => trim((string) ($l->Mpp_Ref ?? '')) ?: null,
                // Kunci kelompok layar — per program atau per lowongan (MPP).
                'programId' => Hashids::encode($l->Program_Id),
                'loker' => PemulihanBerkas::kunciLoker($l),
                'program' => $l->ProgramNama,
                'warna' => $l->Warna,
                'kategori' => $l->Kategori,
                'status' => $l->Status,
                'tahap' => $l->TahapLabel,
                'urutan' => (int) $l->Urutan_Tahap,
                'totalTahap' => (int) $l->Total_Tahap,
                'alur' => $l->AlurNama,
                'berkas' => $temuan[$l->Id_Lamaran],
            ])
            // Yang paling mendesak di atas: lebih banyak berkas WAJIB yang
            // hilang, lalu lebih banyak berkas hilang, lalu abjad.
            ->sort(function ($a, $b) {
                $wa = count(array_filter($a['berkas'], fn ($x) => $x['wajib']));
                $wb = count(array_filter($b['berkas'], fn ($x) => $x['wajib']));

                return [$wb, count($b['berkas']), $a['nama']] <=> [$wa, count($a['berkas']), $b['nama']];
            })
            ->values();

        $semuaButir = $kandidat->flatMap(fn ($k) => $k['berkas']);

        return ResponseHelper::success([
            'hak' => PemulihanBerkas::hak(),
            'status' => $status,
            'mode' => $mode,
            'program' => $program,
            'loker' => $loker,
            'alurOpsi' => $alurOpsi,
            'alurAktif' => $alurAktif,
            'ringkas' => [
                'dipindai' => $cakupan->count(),
                'kandidat' => $kandidat->count(),
                'berkas' => $semuaButir->count(),
                'wajib' => $semuaButir->where('wajib', true)->count(),
                'namaTanpaBerkas' => $semuaButir->where('jenis', 'NAMA_TANPA_BERKAS')->count(),
            ],
            'kandidat' => $kandidat,
        ], 'Rekomendasi pemulihan berkas');
    }

    /**
     * GET — cari kandidat untuk TAMBAH MANUAL (hak TAMBAH_MANUAL, dijaga rute).
     * Mengikuti pemilih program → lowongan (MPP) → nama/kode/email.
     */
    public function kandidat(Request $request)
    {
        $programId = self::idAtauNull((string) $request->query('program', ''));
        $lokerDipilih = trim((string) $request->query('loker', ''));
        $q = trim((string) $request->query('q', ''));

        $rows = PemulihanBerkas::kueriLamaran()
            ->when($programId !== null, fn ($w) => $w->where('l.Program_Id', $programId))
            ->when($lokerDipilih !== '', function ($w) use ($lokerDipilih) {
                // Kunci lowongan = nomor MPP, atau POS-<id> bila MPP-nya kosong
                // (lihat LamaranController::kunciLoker).
                return str_starts_with($lokerDipilih, 'POS-')
                    ? $w->where('l.Program_Posisi_Id', (int) substr($lokerDipilih, 4))
                    : $w->whereRaw('LTRIM(RTRIM(x.Mpp_Ref)) = ?', [$lokerDipilih]);
            })
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x
                ->where('u.Nama', 'like', "%{$q}%")
                ->orWhere('l.Kode', 'like', "%{$q}%")
                ->orWhere('u.Email', 'like', "%{$q}%")))
            ->orderByDesc('l.Id_Lamaran')
            ->limit(60)
            ->get(['l.Id_Lamaran', 'l.Kode', 'l.Status', 'x.Posisi', 'x.Mpp_Ref', 'p.Nama as ProgramNama', 'u.Nama as KandidatNama', 'u.Email']);

        $terkirim = $rows->isEmpty() ? collect() : DB::table('N_WEB_CAREERS_Formulir_Pengisian')
            ->whereIn('Lamaran_Id', $rows->pluck('Id_Lamaran')->all())
            ->where('Status', 'TERKIRIM')
            ->groupBy('Lamaran_Id')
            ->select('Lamaran_Id', DB::raw('COUNT(*) as n'))
            ->pluck('n', 'Lamaran_Id');

        return ResponseHelper::success($rows->map(fn ($l) => [
            'id' => Hashids::encode($l->Id_Lamaran),
            'kode' => $l->Kode,
            'nama' => $l->KandidatNama ?: '(tanpa nama)',
            'email' => $l->Email,
            'posisi' => $l->Posisi,
            'mppRef' => trim((string) ($l->Mpp_Ref ?? '')) ?: null,
            'program' => $l->ProgramNama,
            'status' => $l->Status,
            'formulir' => (int) ($terkirim[$l->Id_Lamaran] ?? 0),
        ])->values(), 'Kandidat');
    }

    /** GET — seluruh formulir terkirim seorang kandidat + semua isian berkasnya (TAMBAH MANUAL). */
    public function isian(string $id)
    {
        $lamaranId = self::idAtauNull($id);
        if ($lamaranId === null || ! PemulihanBerkas::terjangkau($lamaranId)) {
            return ResponseHelper::error('Kandidat tidak ditemukan.', 404);
        }

        return ResponseHelper::success([
            'formulir' => PemulihanBerkas::isianLamaran($lamaranId),
        ], 'Isian berkas kandidat');
    }

    /**
     * POST — unggah berkas pemulihan (hak CREATE, dijaga rute).
     *
     * Multipart: pengisian, [bagian, baris], field, berkas, alasan, [timpa].
     */
    public function unggah(Request $request)
    {
        // Batas PHP lebih kecil daripada berkasnya: $_FILES kosong sebelum
        // validator sempat berbicara, dan pesannya jadi membingungkan.
        if ($request->file('berkas') === null && $request->server('CONTENT_LENGTH') > 0) {
            return ResponseHelper::error('Berkas terlalu besar untuk diterima server. Perkecil ukurannya lalu coba lagi.', 413);
        }

        $data = $request->validate([
            'pengisian' => 'required|string|max:64',
            'bagian' => 'nullable|string|max:60',
            'baris' => 'nullable|integer|min:0|max:99',
            'field' => 'required|string|max:60',
            'alasan' => 'required|string|min:5|max:500',
            'timpa' => 'nullable|boolean',
            'berkas' => 'required|file',
        ], [
            'alasan.required' => 'Tuliskan alasan / asal berkas ini.',
            'alasan.min' => 'Alasan terlalu singkat — sebutkan asal berkasnya (mis. dikirim kandidat lewat email).',
        ]);

        $pengisianId = self::idAtauNull($data['pengisian']);
        $ctx = $pengisianId !== null ? PemulihanBerkas::konteksPengisian($pengisianId) : null;
        if (! $ctx) {
            return ResponseHelper::error('Formulir kandidat tidak ditemukan.', 404);
        }

        $bagian = $data['bagian'] ?? null;
        $baris = isset($data['baris']) ? (int) $data['baris'] : null;
        if (($bagian === null) !== ($baris === null)) {
            return ResponseHelper::error('Posisi baris berkas tidak lengkap.', 422);
        }

        $slot = PemulihanBerkas::cariSlot($ctx, $bagian, $baris, $data['field']);
        if (! $slot) {
            return ResponseHelper::error('Isian berkas ini tidak ada di formulir kandidat.', 422);
        }

        $hak = PemulihanBerkas::hak();
        $timpa = $request->boolean('timpa');
        $ada = PemulihanBerkas::berkasSlot($ctx['berkas'], $slot);
        $rekomendasi = ! $ada && BerkasFormulir::diharapkan($slot);

        // ATURAN 1 — di luar rekomendasi sistem hanya untuk pemegang TAMBAH_MANUAL.
        if (! $rekomendasi && ! $ada && ! $hak['manual']) {
            return ResponseHelper::error('Isian ini bukan rekomendasi sistem. Menambahkan di luar rekomendasi butuh hak Tambah Manual.', 403);
        }

        // ATURAN 2 — isian yang sudah berberkas: ditolak, kecuali TIMPA + konfirmasi.
        if ($ada && ! ($hak['timpa'] && $timpa)) {
            return $this->tolakSudahAda($ada, $hak['timpa']);
        }

        $aturan = BerkasFormulir::aturanUnggah($ctx['skema'], $bagian, $slot['field'], PemulihanBerkas::MAKS_MB_BAWAAN);
        $file = $request->file('berkas');
        if ($galat = BerkasFormulir::periksaBerkas($file, $aturan)) {
            return ResponseHelper::error($galat, 422);
        }

        $admin = (string) (session('career_auth.nama') ?: 'Admin');
        $adminId = (int) session('career_auth.id');
        $gcs = app(GcsBerkas::class);
        $now = now();
        $ext = $gcs->normalkanExt(strtolower($file->getClientOriginalExtension() ?: (string) $file->extension()));

        try {
            $konten = $file->getContent(); // bukan getRealPath — bisa kosong di Apache
            $folder = $gcs->folderPemulihan($now->format('Y'), $now->format('m'), $now->format('d'), (string) ($ctx['kandidat'] ?: 'kandidat'));
            $path = $gcs->unggahUnik($folder, $slot['field'], $ext, $konten);
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('[PEMULIHAN] unggah GCS gagal: ' . $e->getMessage(), ['pengisian' => $ctx['id'], 'field' => $slot['field']]);

            return ResponseHelper::error('Berkas gagal diunggah ke penyimpanan. Coba lagi.', 500);
        }

        $cara = $ada ? 'ditambahkan walau sudah ada ' . count($ada) . ' berkas' : ($rekomendasi ? 'rekomendasi sistem' : 'tambah manual');

        try {
            $hasil = DB::transaction(function () use ($ctx, $slot, $hak, $timpa, $file, $path, $konten, $ext, $admin, $adminId, $now, $cara, $data) {
                // Kunci baris pengisian, lalu periksa ULANG: admin lain bisa saja
                // memulihkan isian yang sama di sela-sela pemeriksaan di atas.
                DB::table('N_WEB_CAREERS_Formulir_Pengisian')
                    ->where('Id_Formulir_Pengisian', $ctx['id'])
                    ->lockForUpdate()
                    ->first(['Id_Formulir_Pengisian']);

                $kini = PemulihanBerkas::berkasPengisian($ctx['id']);
                $adaKini = PemulihanBerkas::berkasSlot($kini, $slot);
                if ($adaKini && ! ($hak['timpa'] && $timpa)) {
                    return ['sudahAda' => $adaKini];
                }

                $urutan = (int) DB::table('N_WEB_CAREERS_Formulir_Berkas')
                    ->where('Formulir_Pengisian_Id', $ctx['id'])
                    ->max('Urutan');

                $id = DB::table('N_WEB_CAREERS_Formulir_Berkas')->insertGetId([
                    'Formulir_Pengisian_Id' => $ctx['id'],
                    // Pemilik tetap KANDIDAT; admin tercatat sebagai pengunggah.
                    'Id_Users' => $ctx['idUsers'],
                    'Field_Key' => $slot['field'],
                    'Bagian_Key' => $slot['bagian'],
                    'Baris_Index' => $slot['baris'],
                    'Urutan' => $urutan + 1,
                    'Nama_Asli' => Str::limit($file->getClientOriginalName(), 250, ''),
                    'Path_File' => $path,
                    'Ukuran_Byte' => strlen($konten),
                    'Mime' => Str::limit((string) $file->getMimeType(), 120, ''),
                    'Ekstensi' => Str::limit($ext, 10, ''),
                    'Hash_File' => hash('sha256', $konten),
                    'Status_Verifikasi' => 'BELUM',
                    'Catatan' => "Pemulihan berkas oleh {$admin} ({$cara}): " . trim($data['alasan']),
                    'Waktu_Unggah' => $now,
                    'Created_At' => $now,
                    'Created_By' => Str::limit($admin, 200, ''),
                    'Created_By_Id' => $adminId,
                    'Updated_At' => $now,
                    'Updated_By' => Str::limit($admin, 200, ''),
                    'Updated_By_Id' => $adminId,
                ], 'Id_Formulir_Berkas');

                return ['id' => (int) $id];
            });
        } catch (\Throwable $e) {
            $gcs->hapus([$path]);
            Log::channel('web_career')->error('[PEMULIHAN] berkas gagal dicatat: ' . $e->getMessage(), ['pengisian' => $ctx['id'], 'field' => $slot['field']]);

            return ResponseHelper::error('Berkas belum tercatat. Coba unggah ulang.', 500);
        }

        if (isset($hasil['sudahAda'])) {
            $gcs->hapus([$path]);

            return $this->tolakSudahAda($hasil['sudahAda'], $hak['timpa']);
        }

        Log::channel('web_career')->info("[PEMULIHAN] {$admin} (#{$adminId}) memulihkan '{$slot['field']}' pengisian #{$ctx['id']} lamaran #{$ctx['lamaranId']} — {$cara}.");

        $baru = PemulihanBerkas::berkasPengisian($ctx['id']);
        $b = collect($baru)->firstWhere('Id_Formulir_Berkas', $hasil['id']);

        return ResponseHelper::success([
            'berkas' => $b ? PemulihanBerkas::berkasPublik($b) : null,
        ], "Berkas \"{$slot['label']}\" tersimpan untuk {$ctx['kandidat']}.");
    }

    /** GET — buka berkas yang sudah ada (tautan GCS bertanda tangan, 15 menit). */
    public function berkas(string $id)
    {
        $bid = self::idAtauNull($id);
        $b = $bid === null ? null : DB::table('N_WEB_CAREERS_Formulir_Berkas as b')
            ->join('N_WEB_CAREERS_Formulir_Pengisian as fp', 'fp.Id_Formulir_Pengisian', '=', 'b.Formulir_Pengisian_Id')
            ->where('b.Id_Formulir_Berkas', $bid)
            ->first(['b.Path_File', 'fp.Lamaran_Id']);

        if (! $b || ! $b->Path_File || ! PemulihanBerkas::terjangkau((int) $b->Lamaran_Id)) {
            abort(404);
        }

        try {
            $disk = Storage::disk(GcsBerkas::DISK);
            if ($disk->exists($b->Path_File)) {
                return redirect()->away($disk->temporaryUrl($b->Path_File, now()->addMinutes(15)));
            }
        } catch (\Throwable $e) {
            Log::channel('web_career')->warning('[PEMULIHAN] tautan berkas gagal: ' . $e->getMessage());
        }

        abort(404);
    }

    /**
     * 409 yang MEMBAWA berkas yang sudah ada — layar memakainya untuk dialog
     * peringatan (pemegang TIMPA) atau pemberitahuan penolakan (selain itu).
     */
    private function tolakSudahAda(array $ada, bool $bolehTimpa)
    {
        return response()->json([
            'success' => false,
            'status' => 409,
            'message' => 'Sistem mencatat isian ini SUDAH berberkas (' . count($ada) . ' berkas). Unggahan ganda ditolak.',
            'result' => [
                'sudahAda' => array_map([PemulihanBerkas::class, 'berkasPublik'], $ada),
                'bolehTimpa' => $bolehTimpa,
            ],
        ], 409);
    }

    private static function idAtauNull(string $hash): ?int
    {
        if ($hash === '') {
            return null;
        }
        $id = Hashids::decode($hash)[0] ?? null;

        return $id !== null ? (int) $id : -1;
    }
}
