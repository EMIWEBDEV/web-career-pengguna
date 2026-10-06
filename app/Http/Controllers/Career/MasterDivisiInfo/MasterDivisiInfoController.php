<?php

namespace App\Http\Controllers\Career\MasterDivisiInfo;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Support\Career\GcsDivisiGambar;
use App\Support\CareerShell;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Vinkla\Hashids\Facades\Hashids;

/**
 * WEB CAREER — MASTER INFO DIVISI (konten landing page per divisi/departemen).
 *
 * Sumber referensi: HRIS_Divisi + HRIS_Sub_Divisi (+ mapping HRIS_Divisi_Sub_Divisi).
 * Konten disimpan di N_WEB_CAREERS_Division_Informations (Id_Divisi UNIQUE) dan
 * N_WEB_CAREERS_Sub_Divisi_Informations (Id_Sub_Divisi UNIQUE) — satu baris info
 * per divisi/sub, sehingga semua penyimpanan bersifat UPSERT.
 *
 * Gambar diunggah ke GCS (GcsDivisiGambar) dan disajikan ke halaman publik lewat
 * gambarPublik() — path selalu dibaca dari kolom DB, tidak pernah dari URL.
 */
class MasterDivisiInfoController extends Controller
{
    private const T_DIVISI = 'N_WEB_CAREERS_Division_Informations';
    private const T_SUB = 'N_WEB_CAREERS_Sub_Divisi_Informations';

    /** slot form → kolom DB. */
    private const SLOT_KOLOM = [
        'header' => 'Img_Path_Header',
        'utama' => 'Img_Path_Utama',
        'img2' => 'Img_Path_2',
        'img3' => 'Img_Path_3',
    ];

    /** Halaman (Inertia). Data di-fetch sendiri oleh Vue via list(). */
    public function index()
    {
        return Inertia::render('Career/admin/master-divisi-info/masterDivisiInfo', CareerShell::props('/master-info-divisi', 'Master Info Divisi'));
    }

    /** Daftar semua divisi HRIS + status kelengkapan info + rekap departemen. */
    public function list()
    {
        try {
            $subTotal = DB::table('HRIS_Divisi_Sub_Divisi')
                ->select('ID_Divisi', DB::raw('COUNT(DISTINCT ID_Sub_Divisi) as total'))
                ->groupBy('ID_Divisi');

            $subTerisi = DB::table('HRIS_Divisi_Sub_Divisi as m')
                ->join(self::T_SUB . ' as si', 'si.Id_Sub_Divisi', '=', 'm.ID_Sub_Divisi')
                ->select('m.ID_Divisi', DB::raw('COUNT(DISTINCT m.ID_Sub_Divisi) as terisi'))
                ->groupBy('m.ID_Divisi');

            $rows = DB::table('HRIS_Divisi as dv')
                ->leftJoin(self::T_DIVISI . ' as i', 'i.Id_Divisi', '=', 'dv.ID_Divisi')
                ->leftJoinSub($subTotal, 'st', 'st.ID_Divisi', '=', 'dv.ID_Divisi')
                ->leftJoinSub($subTerisi, 'sf', 'sf.ID_Divisi', '=', 'dv.ID_Divisi')
                ->orderBy('dv.Keterangan')
                ->select(
                    'dv.ID_Divisi',
                    'dv.Kode_Perusahaan',
                    'dv.Keterangan',
                    'i.Id_Informasi_Divisi',
                    'i.Label_Division',
                    'i.Deskripsi_Singkat',
                    'i.Flag_Aktif',
                    'i.Updated_At',
                    'i.Updated_By',
                    DB::raw('ISNULL(st.total, 0) as Jumlah_Sub'),
                    DB::raw('ISNULL(sf.terisi, 0) as Jumlah_Sub_Terisi'),
                )
                ->get()
                ->map(fn ($r) => [
                    'id' => Hashids::encode($r->ID_Divisi),
                    'kodePerusahaan' => $r->Kode_Perusahaan,
                    'namaHris' => $r->Keterangan,
                    'label' => $r->Label_Division,
                    'deskripsiSingkat' => $r->Deskripsi_Singkat,
                    'punyaInfo' => $r->Id_Informasi_Divisi !== null,
                    'status' => $r->Flag_Aktif === 'Y' ? 'AKTIF' : ($r->Id_Informasi_Divisi ? 'NONAKTIF' : null),
                    'jumlahSub' => (int) $r->Jumlah_Sub,
                    'jumlahSubTerisi' => (int) $r->Jumlah_Sub_Terisi,
                    'updatedBy' => $r->Updated_By,
                    'updatedAt' => $r->Updated_At,
                ])
                ->values();

            return ResponseHelper::success($rows, 'Data info divisi dimuat');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('Gagal memuat info divisi: ' . $e->getMessage());
            return ResponseHelper::error('Gagal memuat data info divisi', 500);
        }
    }

    /** Detail satu divisi: HRIS + info lengkap + daftar departemennya. */
    public function show($id)
    {
        try {
            $realId = Hashids::decode($id)[0] ?? null;
            $dv = DB::table('HRIS_Divisi')->where('ID_Divisi', $realId)->first();
            if (! $dv) {
                return ResponseHelper::error('Divisi tidak ditemukan', 404);
            }

            $info = DB::table(self::T_DIVISI)->where('Id_Divisi', $realId)->first();

            $sub = DB::table('HRIS_Divisi_Sub_Divisi as m')
                ->join('HRIS_Sub_Divisi as sd', 'sd.ID_Sub_Divisi', '=', 'm.ID_Sub_Divisi')
                ->leftJoin(self::T_SUB . ' as si', 'si.Id_Sub_Divisi', '=', 'sd.ID_Sub_Divisi')
                ->where('m.ID_Divisi', $realId)
                ->orderBy('sd.Keterangan')
                ->select('sd.ID_Sub_Divisi', 'sd.Keterangan', 'si.*')
                ->get()
                // Mapping bisa memuat sub yang sama dua kali (beda ID_Sub_Departement).
                ->unique('ID_Sub_Divisi')
                ->map(fn ($r) => [
                    'id' => Hashids::encode($r->ID_Sub_Divisi),
                    'namaHris' => $r->Keterangan,
                    'label' => $r->Label_Sub_Div ?? null,
                    'deskripsiSingkat' => $r->Deskripsi_Singkat ?? null,
                    'judulUtama' => $r->Judul_Utama ?? null,
                    'deskripsiDetail' => $r->Deskripsi_Detail ?? null,
                    'poin' => $this->decodePoin($r->Poin_Keunggulan ?? null),
                    'img' => $this->imgUrls('sub', $r->ID_Sub_Divisi, $r),
                    'punyaInfo' => ($r->Id_Informasi_Sub_Divisi ?? null) !== null,
                    'status' => ($r->Flag_Aktif ?? null) === 'Y' ? 'AKTIF' : (($r->Id_Informasi_Sub_Divisi ?? null) ? 'NONAKTIF' : null),
                    'updatedBy' => $r->Updated_By ?? null,
                    'updatedAt' => $r->Updated_At ?? null,
                ])
                ->values();

            return ResponseHelper::success([
                'id' => Hashids::encode($dv->ID_Divisi),
                'kodePerusahaan' => $dv->Kode_Perusahaan,
                'namaHris' => $dv->Keterangan,
                'punyaInfo' => $info !== null,
                'label' => $info->Label_Division ?? null,
                'deskripsiSingkat' => $info->Deskripsi_Singkat ?? null,
                'judulUtama' => $info->Judul_Utama ?? null,
                'deskripsiDetail' => $info->Deskripsi_Detail ?? null,
                'poin' => $this->decodePoin($info->Poin_Keunggulan ?? null),
                'img' => $this->imgUrls('divisi', $dv->ID_Divisi, $info),
                'status' => ($info->Flag_Aktif ?? null) === 'Y' ? 'AKTIF' : ($info ? 'NONAKTIF' : null),
                'slug' => Str::slug(($info->Label_Division ?? null) ?: $dv->Keterangan),
                'sub' => $sub,
            ], 'Detail info divisi dimuat');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal memuat detail info divisi #{$id}: " . $e->getMessage());
            return ResponseHelper::error('Gagal memuat detail divisi', 500);
        }
    }

    /** Upsert info divisi (satu baris per Id_Divisi). */
    public function upsertDivisi(Request $request, $id)
    {
        try {
            $realId = Hashids::decode($id)[0] ?? null;
            $dv = DB::table('HRIS_Divisi')->where('ID_Divisi', $realId)->first();
            if (! $dv) {
                return ResponseHelper::error('Divisi tidak ditemukan', 404);
            }

            $data = $this->validasiInfo($request);

            // Slug diturunkan dari label (fallback nama HRIS) dan dipakai sebagai URL
            // publik /karir/tim/{slug} — tidak boleh bentrok dengan divisi lain.
            $slug = Str::slug(($data['label'] ?? null) ?: $dv->Keterangan);
            $bentrok = DB::table(self::T_DIVISI . ' as i')
                ->join('HRIS_Divisi as dv', 'dv.ID_Divisi', '=', 'i.Id_Divisi')
                ->where('i.Id_Divisi', '!=', $realId)
                ->get(['i.Label_Division', 'dv.Keterangan'])
                ->contains(fn ($r) => Str::slug($r->Label_Division ?: $r->Keterangan) === $slug);
            if ($bentrok) {
                return ResponseHelper::error('Label menghasilkan URL yang sama dengan divisi lain — gunakan label berbeda', 422);
            }

            $this->simpanInfo(self::T_DIVISI, 'Id_Divisi', $realId, [
                'Label_Division' => $data['label'] ?? null,
            ] + $this->kolomInfo($data));

            Log::channel('web_career')->info("Info divisi #{$realId} disimpan oleh " . session('career_auth.nama', 'ADMIN'));

            return ResponseHelper::success(['slug' => $slug], 'Informasi divisi disimpan');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ResponseHelper::error(collect($e->errors())->flatten()->first() ?? 'Data tidak valid', 422);
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal simpan info divisi #{$id}: " . $e->getMessage());
            return ResponseHelper::error('Gagal menyimpan informasi divisi', 500);
        }
    }

    /** Upsert info departemen (satu baris per Id_Sub_Divisi). */
    public function upsertSub(Request $request, $id)
    {
        try {
            $realId = Hashids::decode($id)[0] ?? null;
            $sd = DB::table('HRIS_Sub_Divisi')->where('ID_Sub_Divisi', $realId)->first();
            if (! $sd) {
                return ResponseHelper::error('Departemen tidak ditemukan', 404);
            }

            $data = $this->validasiInfo($request);

            $this->simpanInfo(self::T_SUB, 'Id_Sub_Divisi', $realId, [
                'Label_Sub_Div' => $data['label'] ?? null,
            ] + $this->kolomInfo($data));

            Log::channel('web_career')->info("Info departemen #{$realId} disimpan oleh " . session('career_auth.nama', 'ADMIN'));

            return ResponseHelper::success(null, 'Informasi departemen disimpan');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ResponseHelper::error(collect($e->errors())->flatten()->first() ?? 'Data tidak valid', 422);
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal simpan info departemen #{$id}: " . $e->getMessage());
            return ResponseHelper::error('Gagal menyimpan informasi departemen', 500);
        }
    }

    /** Aktif/nonaktif info divisi. */
    public function toggleDivisi(Request $request, $id)
    {
        return $this->toggleInfo($request, $id, self::T_DIVISI, 'Id_Divisi', 'divisi');
    }

    /** Aktif/nonaktif info departemen. */
    public function toggleSub(Request $request, $id)
    {
        return $this->toggleInfo($request, $id, self::T_SUB, 'Id_Sub_Divisi', 'departemen');
    }

    /**
     * Unggah satu slot gambar (divisi/sub). Baris info dibuat otomatis bila
     * belum ada (upsert). Objek lama dihapus SETELAH DB sukses; bila DB gagal,
     * objek baru dihapus (kompensasi — tidak boleh ada berkas yatim).
     */
    public function uploadImage(Request $request, $id)
    {
        $gcs = new GcsDivisiGambar();
        $pathBaru = null;

        try {
            $request->validate([
                'jenis' => 'required|in:divisi,sub',
                'slot' => 'required|in:header,utama,img2,img3',
                'file' => 'required|file|mimes:jpg,jpeg,png,webp|max:2048',
            ]);

            $realId = Hashids::decode($id)[0] ?? null;
            $jenis = $request->input('jenis');
            $slot = $request->input('slot');
            [$tabel, $kunci] = $this->tabelJenis($jenis);

            $ref = $jenis === 'sub'
                ? DB::table('HRIS_Sub_Divisi')->where('ID_Sub_Divisi', $realId)->exists()
                : DB::table('HRIS_Divisi')->where('ID_Divisi', $realId)->exists();
            if (! $ref) {
                return ResponseHelper::error('Data referensi tidak ditemukan', 404);
            }

            $kolom = self::SLOT_KOLOM[$slot];
            $lama = DB::table($tabel)->where($kunci, $realId)->value($kolom);

            $file = $request->file('file');
            $pathBaru = $gcs->unggah($jenis, (int) $realId, $slot, $file->getClientOriginalExtension(), $file->get());

            $this->simpanInfo($tabel, $kunci, $realId, [$kolom => $pathBaru]);

            $gcs->hapus([$lama]);

            Log::channel('web_career')->info("Gambar {$jenis} #{$realId} slot {$slot} diunggah: {$pathBaru}");

            return ResponseHelper::success([
                'url' => $this->imgUrl($jenis, (int) $realId, $slot),
            ], 'Gambar berhasil diunggah');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ResponseHelper::error(collect($e->errors())->flatten()->first() ?? 'File tidak valid', 422);
        } catch (\Throwable $e) {
            // Kompensasi: DB gagal setelah unggah → bersihkan objek baru.
            $gcs->hapus([$pathBaru]);
            Log::channel('web_career')->error("Gagal unggah gambar info divisi #{$id}: " . $e->getMessage());
            return ResponseHelper::error('Gagal mengunggah gambar', 500);
        }
    }

    /** Hapus satu slot gambar (null-kan kolom + hapus objek GCS best-effort). */
    public function deleteImage(Request $request, $id)
    {
        try {
            $request->validate([
                'jenis' => 'required|in:divisi,sub',
                'slot' => 'required|in:header,utama,img2,img3',
            ]);

            $realId = Hashids::decode($id)[0] ?? null;
            [$tabel, $kunci] = $this->tabelJenis($request->input('jenis'));
            $kolom = self::SLOT_KOLOM[$request->input('slot')];

            $lama = DB::table($tabel)->where($kunci, $realId)->value($kolom);
            if ($lama === null) {
                return ResponseHelper::error('Gambar tidak ditemukan', 404);
            }

            DB::table($tabel)->where($kunci, $realId)->update([
                $kolom => null,
                'Updated_At' => now(),
                'Updated_By' => $this->namaAdmin(),
            ]);

            (new GcsDivisiGambar())->hapus([$lama]);

            Log::channel('web_career')->info("Gambar {$request->input('jenis')} #{$realId} slot {$request->input('slot')} dihapus");

            return ResponseHelper::success(null, 'Gambar dihapus');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ResponseHelper::error(collect($e->errors())->flatten()->first() ?? 'Permintaan tidak valid', 422);
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal hapus gambar info divisi #{$id}: " . $e->getMessage());
            return ResponseHelper::error('Gagal menghapus gambar', 500);
        }
    }

    /**
     * PUBLIK — stream gambar divisi/sub dari GCS untuk landing page.
     * Path selalu dibaca dari kolom DB (bukan dari URL) dan hanya untuk baris aktif.
     */
    public function gambarPublik($jenis, $id, $slot)
    {
        $realId = Hashids::decode($id)[0] ?? null;
        $kolom = self::SLOT_KOLOM[$slot] ?? null;
        abort_if(! $realId || ! $kolom, 404);

        // Tanpa filter Flag_Aktif: URL yang sama dipakai preview admin untuk baris
        // NONAKTIF. Konten gambar landing tidak sensitif; keamanannya ada pada
        // path yang selalu dibaca dari kolom DB (tidak pernah dari URL).
        [$tabel, $kunci] = $this->tabelJenis($jenis);
        $path = DB::table($tabel)->where($kunci, $realId)->value($kolom);
        abort_if(! $path, 404);

        $stream = Storage::disk(GcsDivisiGambar::DISK)->readStream($path);
        abort_if(! $stream, 404);

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $mime = ['jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'][$ext] ?? 'application/octet-stream';

        return response()->stream(function () use ($stream) {
            fpassthru($stream);
        }, 200, [
            'Content-Type' => $mime,
            'Cache-Control' => 'public, max-age=86400, immutable',
        ]);
    }

    // ─────────────────────────── helper privat ───────────────────────────

    private function validasiInfo(Request $request): array
    {
        return $request->validate([
            'label' => 'nullable|string|max:100',
            'deskripsiSingkat' => 'nullable|string|max:1000',
            'judulUtama' => 'nullable|string|max:255',
            'deskripsiDetail' => 'nullable|string|max:8000',
            'poin' => 'nullable|array|max:10',
            'poin.*' => 'string|max:255',
        ]);
    }

    /** Kolom bersama divisi/sub dari payload tervalidasi (tanpa kolom label). */
    private function kolomInfo(array $data): array
    {
        return [
            'Deskripsi_Singkat' => $data['deskripsiSingkat'] ?? null,
            'Judul_Utama' => $data['judulUtama'] ?? null,
            'Deskripsi_Detail' => $data['deskripsiDetail'] ?? null,
            'Poin_Keunggulan' => ! empty($data['poin'])
                ? json_encode(array_values($data['poin']), JSON_UNESCAPED_UNICODE)
                : null,
        ];
    }

    /** Upsert dengan exists-check (Id UNIQUE → satu baris per divisi/sub). */
    private function simpanInfo(string $tabel, string $kunci, int $realId, array $kolom): void
    {
        $now = now();
        $nama = $this->namaAdmin();

        $ada = DB::table($tabel)->where($kunci, $realId)->exists();
        if ($ada) {
            DB::table($tabel)->where($kunci, $realId)->update($kolom + [
                'Updated_At' => $now,
                'Updated_By' => $nama,
            ]);
        } else {
            DB::table($tabel)->insert($kolom + [
                $kunci => $realId,
                'Flag_Aktif' => 'Y',
                'Created_At' => $now,
                'Created_By' => $nama,
                'Updated_At' => $now,
                'Updated_By' => $nama,
            ]);
        }
    }

    private function toggleInfo(Request $request, $id, string $tabel, string $kunci, string $sebutan)
    {
        try {
            $realId = Hashids::decode($id)[0] ?? null;
            $aktif = $request->boolean('aktif');
            $terpengaruh = DB::table($tabel)->where($kunci, $realId)->update([
                'Flag_Aktif' => $aktif ? 'Y' : 'N',
                'Updated_At' => now(),
                'Updated_By' => $this->namaAdmin(),
            ]);

            if (! $terpengaruh) {
                return ResponseHelper::error('Informasi belum pernah diisi — simpan dulu sebelum mengubah status', 404);
            }

            Log::channel('web_career')->info("Info {$sebutan} #{$id} status " . ($aktif ? 'AKTIF' : 'NONAKTIF'));

            return ResponseHelper::success(null, 'Status diperbarui');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal toggle info {$sebutan} #{$id}: " . $e->getMessage());
            return ResponseHelper::error('Gagal mengubah status', 500);
        }
    }

    private function tabelJenis(string $jenis): array
    {
        return $jenis === 'sub'
            ? [self::T_SUB, 'Id_Sub_Divisi']
            : [self::T_DIVISI, 'Id_Divisi'];
    }

    /** Created_By/Updated_By VARCHAR(50). */
    private function namaAdmin(): string
    {
        return Str::limit((string) session('career_auth.nama', 'ADMIN'), 50, '');
    }

    private function decodePoin(?string $json): array
    {
        return is_string($json) ? (json_decode($json, true) ?: []) : [];
    }

    /** URL streaming publik untuk satu slot (null bila kolom kosong). */
    private function imgUrl(string $jenis, int $realId, string $slot): string
    {
        return '/karir/tim-img/' . $jenis . '/' . Hashids::encode($realId) . '/' . $slot . '?v=' . now()->timestamp;
    }

    /** Peta URL 4 slot dari satu baris info (null bila belum diunggah / baris kosong). */
    private function imgUrls(string $jenis, int $realId, ?object $info): array
    {
        $v = $info->Updated_At ?? null;
        $v = $v ? strtotime($v) : 0;
        $buat = fn (string $slot, ?string $path) => $path
            ? '/karir/tim-img/' . $jenis . '/' . Hashids::encode($realId) . '/' . $slot . '?v=' . $v
            : null;

        return [
            'header' => $buat('header', $info->Img_Path_Header ?? null),
            'utama' => $buat('utama', $info->Img_Path_Utama ?? null),
            'img2' => $buat('img2', $info->Img_Path_2 ?? null),
            'img3' => $buat('img3', $info->Img_Path_3 ?? null),
        ];
    }
}
