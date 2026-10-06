<?php

namespace App\Http\Controllers\Career\MasterJenisVerifikasi;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Support\CareerShell;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;

/**
 * WEB CAREER — MASTER JENIS VERIFIKASI: komponen background check.
 *
 * Satu baris per hal yang diperiksa: identitas, ijazah, riwayat kerja, catatan
 * hukum, alamat. Daftar ini yang muncul sebagai daftar centang saat tim
 * mengerjakan Background Check di Worklist.
 *
 * ── MENGUBAH MASTER TIDAK MENGUBAH MASA LALU ────────────────────────────────
 *
 * Kode & nama komponen DIBEKUKAN ke barisnya sendiri saat pemeriksaan dicatat
 * (Verifikasi_Latar.Jenis_Kode / Jenis_Nama). Jadi mengganti nama "Catatan
 * Hukum / SKCK" tahun depan tidak menulis ulang pemeriksaan tahun ini — dan
 * menonaktifkan sebuah komponen hanya berarti ia tak lagi ditawarkan untuk
 * pemeriksaan BARU.
 *
 * ── DUA KOLOM YANG BUKAN SEKADAR KETERANGAN ─────────────────────────────────
 *
 * `Flag_Sensitif` menandai komponen yang isinya data pribadi bersifat spesifik
 * menurut UU PDP 27/2022 Pasal 4 ayat (2) — catatan kejahatan, data kesehatan.
 * Penanda itu yang membuat pembukaannya dicatat ke jejak akses.
 *
 * `Retensi_Hari` menentukan kapan isi temuannya diredaksi otomatis oleh
 * perintah `career:pemeriksaan-retensi`. Kosong berarti mengikuti kebijakan
 * umum perusahaan — yaitu tidak diredaksi oleh sistem ini.
 */
class MasterJenisVerifikasiController extends Controller
{
    private const TABEL = 'N_WEB_CAREERS_Master_Jenis_Verifikasi';

    public function index()
    {
        return Inertia::render(
            'Career/admin/master-jenis-verifikasi/masterJenisVerifikasi',
            CareerShell::props('/master-jenis-verifikasi', 'Master Jenis Verifikasi')
        );
    }

    /**
     * Daftar komponen + berapa kali masing-masing SUDAH DIPAKAI.
     *
     * Angka pemakaian dikirim bersama daftarnya karena ia yang menjawab
     * pertanyaan yang muncul tepat sebelum tombol hapus ditekan: "kalau saya
     * buang ini, ada berapa pemeriksaan yang kehilangan artinya?" — jawabannya:
     * nol, karena namanya sudah beku, tapi orang tetap berhak tahu angkanya.
     */
    public function list()
    {
        try {
            $dipakai = DB::table('N_WEB_CAREERS_Verifikasi_Latar')
                ->select('Jenis_Kode', DB::raw('COUNT(*) as n'))
                ->groupBy('Jenis_Kode')
                ->pluck('n', 'Jenis_Kode');

            $rows = DB::table(self::TABEL)
                ->orderBy('Urutan')
                ->orderBy('Nama')
                ->get()
                ->map(fn ($r) => [
                    'id' => (int) $r->Id_Master_Jenis_Verifikasi,
                    'kode' => $r->Kode,
                    'nama' => $r->Nama,
                    'deskripsi' => $r->Deskripsi,
                    'ikon' => $r->Ikon,
                    'warna' => $r->Warna,
                    'sensitif' => ($r->Flag_Sensitif ?? 'T') === 'Y',
                    'retensiHari' => $r->Retensi_Hari !== null ? (int) $r->Retensi_Hari : null,
                    'urutan' => (int) $r->Urutan,
                    'sistem' => ($r->Flag_Sistem ?? 'T') === 'Y',
                    'aktif' => ($r->Flag_Aktif ?? 'Y') === 'Y',
                    'dipakai' => (int) ($dipakai[$r->Kode] ?? 0),
                    'diperbaruiOleh' => $r->Updated_By ?? $r->Created_By,
                    'diperbaruiPada' => (string) ($r->Updated_At ?: $r->Created_At),
                ])
                ->values();

            return ResponseHelper::success($rows, 'Master jenis verifikasi');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('Gagal memuat jenis verifikasi: '.$e->getMessage());

            return ResponseHelper::error('Gagal memuat data.', 500);
        }
    }

    private function aturan(Request $request, ?int $id = null): array
    {
        return $request->validate([
            'kode' => 'required|string|max:30|regex:/^[A-Z0-9_]+$/',
            'nama' => 'required|string|max:120',
            'deskripsi' => 'nullable|string|max:400',
            'ikon' => 'nullable|string|max:50',
            'warna' => 'nullable|string|max:20',
            'sensitif' => 'nullable|boolean',
            // 30 tahun. Batas atasnya bukan kesopanan: angka yang keliru satu
            // digit membuat data yang seharusnya dibuang bertahan tiga abad.
            'retensiHari' => 'nullable|integer|min:1|max:10950',
            'urutan' => 'nullable|integer|min:0|max:999',
        ], [
            'kode.regex' => 'Kode hanya boleh huruf besar, angka, dan garis bawah (mis. CATATAN_HUKUM).',
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->aturan($request);

        if (DB::table(self::TABEL)->where('Kode', $data['kode'])->exists()) {
            return ResponseHelper::error('Kode "'.$data['kode'].'" sudah dipakai komponen lain.', 422);
        }

        try {
            DB::table(self::TABEL)->insert($this->isi($data) + [
                'Kode' => $data['kode'],
                'Flag_Sistem' => 'T',
                'Flag_Aktif' => 'Y',
                'Created_At' => now(),
                'Created_By' => session('career_auth.nama', 'ADMIN'),
                'Created_By_Id' => session('career_auth.id'),
            ]);

            Log::channel('web_career')->info(sprintf(
                '[JENIS VERIFIKASI] ditambahkan: %s (%s), oleh %s.',
                $data['nama'], $data['kode'], session('career_auth.nama', 'ADMIN')
            ));

            return ResponseHelper::success(null, 'Komponen pemeriksaan ditambahkan.');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('Gagal menambah jenis verifikasi: '.$e->getMessage());

            return ResponseHelper::error('Gagal menyimpan.', 500);
        }
    }

    public function update(Request $request, int $id)
    {
        $baris = DB::table(self::TABEL)->where('Id_Master_Jenis_Verifikasi', $id)->first();
        if (! $baris) {
            return ResponseHelper::error('Komponen tidak ditemukan.', 404);
        }

        $data = $this->aturan($request, $id);

        // KODE BAWAAN SISTEM TIDAK BOLEH BERGANTI.
        //
        // Kode itulah yang menyambungkan baris master dengan pemeriksaan yang
        // sudah tercatat. Namanya boleh diperbaiki kapan saja — yang dibekukan
        // di riwayat memang salinannya, bukan tautannya.
        if (($baris->Flag_Sistem ?? 'T') === 'Y' && $data['kode'] !== $baris->Kode) {
            return ResponseHelper::error('Kode komponen bawaan sistem tidak bisa diubah. Namanya boleh.', 422);
        }

        if (DB::table(self::TABEL)->where('Kode', $data['kode'])->where('Id_Master_Jenis_Verifikasi', '<>', $id)->exists()) {
            return ResponseHelper::error('Kode "'.$data['kode'].'" sudah dipakai komponen lain.', 422);
        }

        try {
            DB::table(self::TABEL)->where('Id_Master_Jenis_Verifikasi', $id)->update($this->isi($data) + [
                'Kode' => $data['kode'],
                'Updated_At' => now(),
                'Updated_By' => session('career_auth.nama', 'ADMIN'),
                'Updated_By_Id' => session('career_auth.id'),
            ]);

            Log::channel('web_career')->info(sprintf(
                '[JENIS VERIFIKASI] disunting: %s (%s), oleh %s.',
                $data['nama'], $data['kode'], session('career_auth.nama', 'ADMIN')
            ));

            return ResponseHelper::success(null, 'Komponen diperbarui.');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('Gagal menyunting jenis verifikasi: '.$e->getMessage());

            return ResponseHelper::error('Gagal menyimpan.', 500);
        }
    }

    /** Aktif ⇄ nonaktif. Yang nonaktif tak lagi ditawarkan untuk pemeriksaan baru. */
    public function toggle(int $id)
    {
        $baris = DB::table(self::TABEL)->where('Id_Master_Jenis_Verifikasi', $id)->first();
        if (! $baris) {
            return ResponseHelper::error('Komponen tidak ditemukan.', 404);
        }

        $baru = ($baris->Flag_Aktif ?? 'Y') === 'Y' ? 'N' : 'Y';

        DB::table(self::TABEL)->where('Id_Master_Jenis_Verifikasi', $id)->update([
            'Flag_Aktif' => $baru,
            'Updated_At' => now(),
            'Updated_By' => session('career_auth.nama', 'ADMIN'),
            'Updated_By_Id' => session('career_auth.id'),
        ]);

        Log::channel('web_career')->info(sprintf(
            '[JENIS VERIFIKASI] %s: %s, oleh %s.',
            $baru === 'Y' ? 'diaktifkan' : 'dinonaktifkan', $baris->Nama, session('career_auth.nama', 'ADMIN')
        ));

        return ResponseHelper::success(
            ['aktif' => $baru === 'Y'],
            $baru === 'Y'
                ? 'Komponen diaktifkan — kembali ditawarkan untuk pemeriksaan baru.'
                : 'Komponen dinonaktifkan. Pemeriksaan yang sudah tercatat tidak berubah.'
        );
    }

    public function destroy(int $id)
    {
        $baris = DB::table(self::TABEL)->where('Id_Master_Jenis_Verifikasi', $id)->first();
        if (! $baris) {
            return ResponseHelper::error('Komponen tidak ditemukan.', 404);
        }

        // MENONAKTIFKAN, BUKAN MENGHAPUS, adalah cara membuang komponen bawaan.
        // Yang bawaan dirujuk kode program (mis. contoh isian di layar Worklist);
        // membuangnya membuat daftar centang kehilangan baris tanpa jejak alasan.
        if (($baris->Flag_Sistem ?? 'T') === 'Y') {
            return ResponseHelper::error('Komponen bawaan sistem tidak bisa dihapus — nonaktifkan saja.', 422);
        }

        try {
            DB::table(self::TABEL)->where('Id_Master_Jenis_Verifikasi', $id)->delete();

            Log::channel('web_career')->info(sprintf(
                '[JENIS VERIFIKASI] dihapus: %s (%s), oleh %s. Pemeriksaan lama tidak berubah.',
                $baris->Nama, $baris->Kode, session('career_auth.nama', 'ADMIN')
            ));

            return ResponseHelper::success(null, 'Komponen dihapus. Pemeriksaan yang sudah tercatat tetap utuh.');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('Gagal menghapus jenis verifikasi: '.$e->getMessage());

            return ResponseHelper::error('Gagal menghapus.', 500);
        }
    }

    /** Bagian isian yang sama untuk tambah & sunting. */
    private function isi(array $data): array
    {
        return [
            'Nama' => $data['nama'],
            'Deskripsi' => $data['deskripsi'] ?? null,
            // `?? null` dulu: bidang opsional yang tidak dikirim sama sekali tidak
            // ada di larik tervalidasi, dan `?:` saja melempar peringatan.
            'Ikon' => ($data['ikon'] ?? null) ?: 'bi-check2-square',
            'Warna' => ($data['warna'] ?? null) ?: '#6366f1',
            'Flag_Sensitif' => ! empty($data['sensitif']) ? 'Y' : 'T',
            'Retensi_Hari' => $data['retensiHari'] ?? null,
            'Urutan' => $data['urutan'] ?? 99,
        ];
    }
}
