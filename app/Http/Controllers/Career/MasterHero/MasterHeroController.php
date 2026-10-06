<?php

namespace App\Http\Controllers\Career\MasterHero;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Support\Career\GcsHeroSlide;
use App\Support\CareerShell;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Vinkla\Hashids\Facades\Hashids;

/**
 * WEB CAREER — MASTER HERO (slide media hero landing page: gambar desktop/mobile + video).
 *
 * - index()  : render Inertia shell — halaman fetch datanya sendiri lewat list().
 * - list()   : Query Builder + URL media streaming (GCS) -> ResponseHelper.
 * - store/update/toggle/destroy : CRUD Query Builder -> ResponseHelper.
 * - uploadMedia/deleteMedia     : slot desktop/mobile/poster/video (GCS via GcsHeroSlide).
 * - mediaPublik()               : PUBLIK — stream media dari GCS untuk landing page (& preview admin).
 */
class MasterHeroController extends Controller
{
    private const TBL = 'N_WEB_CAREERS_Master_Hero_Slide';

    /** slot form → kolom DB. */
    private const SLOT_KOLOM = [
        'desktop' => 'Gambar_Desktop',
        'mobile' => 'Gambar_Mobile',
        'video_desktop' => 'Video_Desktop_Url',
        'video_mobile' => 'Video_Mobile_Url',
        'poster_desktop' => 'Video_Desktop_Poster',
        'poster_mobile' => 'Video_Mobile_Poster',
    ];

    /** Halaman (Inertia). Data TIDAK dikirim lewat props — halaman fetch sendiri ke API list(). */
    public function index()
    {
        return Inertia::render('Career/admin/master-hero/masterHero', CareerShell::props('/master-hero', 'Master Hero'));
    }

    /** Data list slide hero, terurut sesuai Urutan. */
    public function list()
    {
        try {
            $rows = DB::table(self::TBL)
                ->orderBy('Urutan')
                ->orderBy('Id_Master_Hero_Slide')
                ->get()
                ->map(fn($r) => $this->shape($r))
                ->values();

            return ResponseHelper::success($rows, 'Data hero slide dimuat');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('Gagal memuat hero slide: ' . $e->getMessage());

            return ResponseHelper::error('Gagal memuat data hero slide', 500);
        }
    }

    /** Tambah slide. */
    public function store(Request $request)
    {
        try {
            $data = $request->validate($this->rules());
            $nama = session('career_auth.nama', 'ADMIN');
            $now = now();

            $id = DB::table(self::TBL)->insertGetId([
                'Label' => $data['label'],
                'Tipe' => $data['tipe'],
                'Flag_Tampilkan_Konten' => $this->yn($data['tampilkanKonten'] ?? false),
                'Overlay' => $data['overlay'],
                'Zoom_Animation' => $this->yn($data['zoomAnimation'] ?? 'N'),
                'Durasi_Ms' => $data['durasiMs'] ?? 5000,
                'Urutan' => $data['urutan'] ?? 0,
                'Flag_Aktif' => 'Y',
                'Created_At' => $now,
                'Created_By' => $this->namaAdmin($nama),
                'Updated_At' => $now,
                'Updated_By' => $this->namaAdmin($nama),
            ], 'Id_Master_Hero_Slide');

            Log::channel('web_career')->info("Hero slide #{$id} dibuat oleh {$nama}");

            return ResponseHelper::success(['id' => Hashids::encode($id)], 'Slide berhasil ditambahkan', 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ResponseHelper::error(collect($e->errors())->flatten()->first() ?? 'Data tidak valid', 422);
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('Gagal membuat hero slide: ' . $e->getMessage());

            return ResponseHelper::error('Gagal menyimpan data', 500);
        }
    }

    /** Ubah slide. */
    public function update(Request $request, $id)
    {
        try {
            $realId = Hashids::decode($id)[0] ?? null;
            $row = DB::table(self::TBL)->where('Id_Master_Hero_Slide', $realId)->first();
            if (! $row) {
                return ResponseHelper::error('Slide tidak ditemukan', 404);
            }

            $data = $request->validate($this->rules());

            DB::table(self::TBL)->where('Id_Master_Hero_Slide', $realId)->update([
                'Label' => $data['label'],
                'Tipe' => $data['tipe'],
                'Flag_Tampilkan_Konten' => $this->yn($data['tampilkanKonten'] ?? false),
                'Overlay' => $data['overlay'],
                'Zoom_Animation' => $this->yn($data['zoomAnimation'] ?? 'N'),
                'Durasi_Ms' => $data['durasiMs'] ?? 5000,
                'Urutan' => $data['urutan'] ?? $row->Urutan,
                'Updated_At' => now(),
                'Updated_By' => $this->namaAdmin(session('career_auth.nama', 'ADMIN')),
            ]);

            Log::channel('web_career')->info("Hero slide #{$id} diperbarui");

            return ResponseHelper::success(null, 'Slide diperbarui');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ResponseHelper::error(collect($e->errors())->flatten()->first() ?? 'Data tidak valid', 422);
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal update hero slide #{$id}: " . $e->getMessage());

            return ResponseHelper::error('Gagal memperbarui data', 500);
        }
    }

    /** Aktif/Nonaktif slide (slide NONAKTIF tidak ikut tampil di landing). */
    public function toggle(Request $request, $id)
    {
        try {
            $realId = Hashids::decode($id)[0] ?? null;
            $aktif = $request->boolean('aktif');
            $terpengaruh = DB::table(self::TBL)->where('Id_Master_Hero_Slide', $realId)->update([
                'Flag_Aktif' => $aktif ? 'Y' : 'N',
                'Updated_At' => now(),
                'Updated_By' => $this->namaAdmin(session('career_auth.nama', 'ADMIN')),
            ]);

            if (! $terpengaruh) {
                return ResponseHelper::error('Slide tidak ditemukan', 404);
            }

            Log::channel('web_career')->info("Hero slide #{$id} status " . ($aktif ? 'AKTIF' : 'NONAKTIF'));

            return ResponseHelper::success(null, 'Status diperbarui');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal toggle hero slide #{$id}: " . $e->getMessage());

            return ResponseHelper::error('Gagal mengubah status', 500);
        }
    }

    /** Hapus slide (sekaligus bersihkan seluruh berkas media di GCS). */
    public function destroy($id)
    {
        try {
            $realId = Hashids::decode($id)[0] ?? null;
            $row = DB::table(self::TBL)->where('Id_Master_Hero_Slide', $realId)->first();
            if (! $row) {
                return ResponseHelper::error('Slide tidak ditemukan', 404);
            }

            DB::table(self::TBL)->where('Id_Master_Hero_Slide', $realId)->delete();

            (new GcsHeroSlide())->hapus([
                $row->Gambar_Desktop,
                $row->Gambar_Mobile,
                $row->Video_Desktop_Poster,
                $row->Video_Mobile_Poster,
                $row->Video_Desktop_Url,
                $row->Video_Mobile_Url,
            ]);

            Log::channel('web_career')->info("Hero slide #{$id} dihapus");

            return ResponseHelper::success(null, 'Slide dihapus');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal hapus hero slide #{$id}: " . $e->getMessage());

            return ResponseHelper::error('Gagal menghapus data', 500);
        }
    }

    /**
     * Unggah satu slot media (desktop/mobile/poster/video). Objek lama dihapus
     * SETELAH DB sukses; bila DB gagal, objek baru dihapus (kompensasi).
     */
    public function uploadMedia(Request $request, $id)
    {
        $gcs = new GcsHeroSlide();
        $pathBaru = null;

        try {
            $slot = (string) $request->input('slot');
            $isVideo = str_starts_with($slot, 'video_');
            $request->validate([
                'slot' => 'required|in:desktop,mobile,video_desktop,video_mobile,poster_desktop,poster_mobile',
                'file' => $isVideo
                    ? 'required|file|mimes:mp4,webm'
                    : 'required|file|mimes:jpg,jpeg,png,webp|max:' . (GcsHeroSlide::MAKS_BYTE_GAMBAR / 1024),
            ]);

            $realId = Hashids::decode($id)[0] ?? null;
            $row = DB::table(self::TBL)->where('Id_Master_Hero_Slide', $realId)->first();
            if (! $row) {
                return ResponseHelper::error('Slide tidak ditemukan', 404);
            }

            $kolom = self::SLOT_KOLOM[$slot];
            $lama = $row->{$kolom};

            $file = $request->file('file');
            $pathBaru = $gcs->unggah((int) $realId, $slot, $file->getClientOriginalExtension(), $file->get());
            DB::table(self::TBL)->where('Id_Master_Hero_Slide', $realId)->update([
                $kolom => $pathBaru,
                'Updated_At' => now(),
                'Updated_By' => $this->namaAdmin(session('career_auth.nama', 'ADMIN')),
            ]);

            $gcs->hapus([$lama]);

            Log::channel('web_career')->info("Media hero slide #{$realId} slot {$slot} diunggah: {$pathBaru}");

            return ResponseHelper::success([
                'url' => $this->mediaUrl((int) $realId, $slot),
            ], 'Berkas berhasil diunggah');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ResponseHelper::error(collect($e->errors())->flatten()->first() ?? 'Berkas tidak valid', 422);
        } catch (\Throwable $e) {
            // Kompensasi: DB gagal setelah unggah → bersihkan objek baru.
            $gcs->hapus([$pathBaru]);
            Log::channel('web_career')->error("Gagal unggah media hero slide #{$id}: " . $e->getMessage());

            return ResponseHelper::error($e, 500);
        }
    }

    /** Hapus satu slot media (null-kan kolom + hapus objek GCS best-effort). */
    public function deleteMedia(Request $request, $id)
    {
        try {
            $request->validate(['slot' => 'required|in:desktop,mobile,video_desktop,video_mobile,poster_desktop,poster_mobile']);

            $realId = Hashids::decode($id)[0] ?? null;
            $kolom = self::SLOT_KOLOM[$request->input('slot')];

            $lama = DB::table(self::TBL)->where('Id_Master_Hero_Slide', $realId)->value($kolom);
            if ($lama === null) {
                return ResponseHelper::error('Berkas tidak ditemukan', 404);
            }

            DB::table(self::TBL)->where('Id_Master_Hero_Slide', $realId)->update([
                $kolom => null,
                'Updated_At' => now(),
                'Updated_By' => $this->namaAdmin(session('career_auth.nama', 'ADMIN')),
            ]);

            (new GcsHeroSlide())->hapus([$lama]);

            Log::channel('web_career')->info("Media hero slide #{$realId} slot {$request->input('slot')} dihapus");

            return ResponseHelper::success(null, 'Berkas dihapus');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ResponseHelper::error(collect($e->errors())->flatten()->first() ?? 'Permintaan tidak valid', 422);
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal hapus media hero slide #{$id}: " . $e->getMessage());

            return ResponseHelper::error('Gagal menghapus berkas', 500);
        }
    }

    /**
     * PUBLIK — stream media (gambar/video) hero slide dari GCS untuk landing page.
     * Path selalu dibaca dari kolom DB, tidak pernah dari URL. Tanpa filter
     * Flag_Aktif: URL yang sama dipakai preview admin untuk slide NONAKTIF.
     */
    public function mediaPublik($id, $slot)
    {
        $realId = Hashids::decode($id)[0] ?? null;
        $kolom = self::SLOT_KOLOM[$slot] ?? null;
        abort_if(! $realId || ! $kolom, 404);

        $path = DB::table(self::TBL)->where('Id_Master_Hero_Slide', $realId)->value($kolom);
        abort_if(! $path, 404);

        $stream = Storage::disk(GcsHeroSlide::DISK)->readStream($path);
        abort_if(! $stream, 404);

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $mime = [
            'jpg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            'mp4' => 'video/mp4',
            'webm' => 'video/webm',
        ][$ext] ?? 'application/octet-stream';

        return response()->stream(function () use ($stream) {
            fpassthru($stream);
        }, 200, [
            'Content-Type' => $mime,
            'Cache-Control' => 'public, max-age=86400, immutable',
        ]);
    }

    // ─────────────────────────── helper privat ───────────────────────────

    private function rules(): array
    {
        return [
            'label' => 'required|string|max:60',
            'tipe' => 'required|in:IMAGE,VIDEO',
            'tampilkanKonten' => 'nullable|boolean',
            'overlay' => 'required|in:BRAND,DARK,LIGHT,NONE',
            'zoomAnimation' => 'nullable|in:Y,N',
            'durasiMs' => 'nullable|integer|min:1000|max:60000',
            'urutan' => 'nullable|integer|min:0|max:9999',
        ];
    }

    private function shape(object $r): array
    {
        $id = (int) $r->Id_Master_Hero_Slide;

        return [
            'id' => Hashids::encode($id),
            'label' => $r->Label,
            'tipe' => $r->Tipe,
            'tampilkanKonten' => $this->yn($r->Flag_Tampilkan_Konten) === 'Y',
            'overlay' => $r->Overlay,
            'zoomAnimation' => $this->yn($r->Zoom_Animation),
            'durasiMs' => (int) $r->Durasi_Ms,
            'urutan' => (int) $r->Urutan,
            'status' => $r->Flag_Aktif === 'Y' ? 'AKTIF' : 'NONAKTIF',
            'gambarDesktop' => $r->Gambar_Desktop ? $this->mediaUrl($id, 'desktop', $r->Updated_At) : null,
            'gambarMobile' => $r->Gambar_Mobile ? $this->mediaUrl($id, 'mobile', $r->Updated_At) : null,
            'videoDesktopUrl' => $r->Video_Desktop_Url ? $this->mediaUrl($id, 'video_desktop', $r->Updated_At) : null,
            'videoMobileUrl' => $r->Video_Mobile_Url ? $this->mediaUrl($id, 'video_mobile', $r->Updated_At) : null,
            'videoDesktopPoster' => $r->Video_Desktop_Poster ? $this->mediaUrl($id, 'poster_desktop', $r->Updated_At) : null,
            'videoMobilePoster' => $r->Video_Mobile_Poster ? $this->mediaUrl($id, 'poster_mobile', $r->Updated_At) : null,
            'updatedBy' => $r->Updated_By,
            'updatedAt' => $r->Updated_At,
        ];
    }

    private function mediaUrl(int $id, string $slot, ?string $updatedAt = null): string
    {
        $v = $updatedAt ? strtotime($updatedAt) : now()->timestamp;

        return '/karir/hero-media/' . Hashids::encode($id) . '/' . $slot . '?v=' . $v;
    }

    /** Created_By/Updated_By NVARCHAR(100). */
    private function namaAdmin(string $nama): string
    {
        return Str::limit($nama, 100, '');
    }

    private function yn(mixed $value): string
    {
        return in_array($value, [true, 1, '1', 'Y', 'y', 'true', 'TRUE'], true) ? 'Y' : 'N';
    }
}
