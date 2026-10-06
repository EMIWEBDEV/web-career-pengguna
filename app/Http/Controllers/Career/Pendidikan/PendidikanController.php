<?php

namespace App\Http\Controllers\Career\Pendidikan;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Support\Career\PencarianCepat;
use App\Support\Career\ProdiKampus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * WEB CAREER — endpoint PENDIDIKAN untuk FORMULIR kandidat (bukan admin).
 *
 * Menyediakan data cascade Jenjang → Jenis Institusi → Nama Kampus:
 *   - jenjang()        : daftar jenjang aktif (dipilih pertama).
 *   - jenisInstitusi() : jenis institusi yang berlaku untuk jenjang terpilih.
 *   - kampus()         : PENCARIAN server-side nama kampus/sekolah (autocomplete),
 *                        difilter jenis institusi — supaya payload form tetap ringan
 *                        walau master berisi ratusan ribu baris.
 *   - fakultas()       : fakultas/rumpun ilmu yang tersedia di kampus terpilih.
 *   - prodi()          : prodi/jurusan, dari binding resmi kampus bila ada,
 *                        selain itu dari aturan cadangan (lihat ProdiKampus).
 */
class PendidikanController extends Controller
{
    public function jenjang()
    {
        try {
            // TERTINGGI DULU (S3 -> SD). Urutan di master menanjak dari SD,
            // jadi dibalik di sini. Alasannya bukan selera: pelamar posisi kantor
            // hampir selalu memilih jenjang atas, dan menaruhnya di dasar daftar
            // memaksa hampir semua orang menggulung dropdown lebih dulu.
            $rows = Cache::remember('wc_pdk_jenjang_desc', now()->addMinutes(10), fn () => DB::table('N_WEB_CAREERS_Master_Jenjang')
                ->where('Flag_Aktif', 'Y')->orderByDesc('Urutan')->orderByDesc('Id_Master_Jenjang')
                ->get(['Kode', 'Nama'])->map(fn ($r) => ['kode' => $r->Kode, 'nama' => $r->Nama])->values());
            return ResponseHelper::success($rows, 'Jenjang dimuat');
        } catch (\Throwable $e) {
            return ResponseHelper::error('Gagal memuat jenjang', 500);
        }
    }

    public function jenisInstitusi(Request $request)
    {
        try {
            $jenjang = trim((string) $request->query('jenjang', ''));
            $key = 'wc_pdk_jenis_' . ($jenjang !== '' ? $jenjang : 'all');

            $out = Cache::remember($key, now()->addMinutes(10), function () use ($jenjang) {
                // Sumber kebenaran = tabel BINDING (junction) Jenis ↔ Jenjang.
                $q = DB::table('N_WEB_CAREERS_Master_Jenis_Institusi as ji')->where('ji.Flag_Aktif', 'Y');
                if ($jenjang !== '') {
                    $q->join('N_WEB_CAREERS_Jenis_Institusi_Jenjang as b', 'b.Kode_Jenis', '=', 'ji.Kode')
                        ->where('b.Kode_Jenjang', $jenjang);
                }
                // Tanpa DISTINCT: index unik UX_JIJ + filter satu jenjang menjamin
                // maksimum satu baris binding per jenis (SQL Server juga menolak
                // DISTINCT bila kolom ORDER BY tak ada di SELECT).
                return $q->orderBy('ji.Urutan')->orderBy('ji.Id_Master_Jenis_Institusi')
                    ->get(['ji.Kode', 'ji.Nama', 'ji.Kategori'])
                    ->map(fn ($r) => ['kode' => $r->Kode, 'nama' => $r->Nama, 'kategori' => $r->Kategori])
                    ->values();
            });

            return ResponseHelper::success($out, 'Jenis institusi dimuat');
        } catch (\Throwable $e) {
            return ResponseHelper::error('Gagal memuat jenis institusi', 500);
        }
    }

    public function kampus(Request $request)
    {
        try {
            $q = trim((string) $request->query('q', ''));
            $jenis = trim((string) $request->query('jenis', ''));
            $limit = min((int) $request->query('limit', 30) ?: 30, 50);

            // Kolom yang diambil HARUS sama dengan isi indeks IX_MK_Cascade.
            // Menambah kolom di luar itu (apalagi select *) membuat SQL Server
            // bolak-balik ke tabel dan waktunya naik berlipat.
            $kolom = ['Nama', 'Kota', 'Provinsi', 'Negara', 'Negara_Kode', 'Kepemilikan', 'Akreditasi'];

            $bangun = function () use ($jenis) {
                $b = DB::table('N_WEB_CAREERS_Master_Kampus')->where('Flag_Aktif', 'Y');
                if ($jenis !== '') {
                    $b->where('Jenis_Institusi_Kode', $jenis);
                }

                return $b;
            };

            // Jalur cepat: indeks kata (full-text), bukan LIKE '%…%' yang
            // memaksa memindai 328 ribu baris tiap ketikan.
            $query = $bangun();
            $pakaiFullText = PencarianCepat::terapkan($query, 'N_WEB_CAREERS_Master_Kampus', 'Nama', $q);
            $hasil = $query->orderBy('Nama')->limit($limit)->get($kolom);

            // Jaring pengaman: full-text hanya menimbang 2.000 kandidat teratas,
            // jadi kata yang sangat umum + filter sempit bisa tak menyisakan apa
            // pun. Kalau kosong, ulangi dengan pencarian lengkap.
            if ($hasil->isEmpty() && $pakaiFullText && $q !== ''
                && PencarianCepat::adaKecocokan('N_WEB_CAREERS_Master_Kampus', 'Nama', $q)) {
                $ulang = $bangun();
                PencarianCepat::terapkanLuas($ulang, 'Nama', $q);
                $hasil = $ulang->orderBy('Nama')->limit($limit)->get($kolom);
            }

            $rows = $hasil
                ->map(fn ($r) => [
                    'value' => $r->Nama,
                    'label' => $r->Nama,
                    'kota' => $r->Kota,
                    'provinsi' => $r->Provinsi,
                    'negara' => $r->Negara,
                    // ISO-2 utk bendera (fallback ID untuk Indonesia).
                    'negaraKode' => $r->Negara_Kode ?: ($r->Negara === 'Indonesia' ? 'ID' : null),
                    'kepemilikan' => $r->Kepemilikan,
                    'akreditasi' => $r->Akreditasi,
                ])->values();

            return ResponseHelper::success($rows, 'Kampus dimuat');
        } catch (\Throwable $e) {
            return ResponseHelper::error('Gagal memuat kampus', 500);
        }
    }

    /** Fakultas / rumpun ilmu yang tersedia di kampus + jenjang terpilih. */
    public function fakultas(Request $request)
    {
        try {
            $rows = ProdiKampus::fakultas(
                trim((string) $request->query('kampus', '')),
                trim((string) $request->query('jenjang', ''))
            );

            return ResponseHelper::success($rows, 'Fakultas dimuat');
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::channel('web_career')->error('Gagal memuat fakultas: ' . $e->getMessage());

            return ResponseHelper::error('Gagal memuat fakultas', 500);
        }
    }

    /**
     * Prodi/jurusan untuk kampus + jenjang terpilih.
     * Kosong bila kampus belum dipilih — daftar prodi mengikuti kampus.
     */
    public function prodi(Request $request)
    {
        try {
            $hasil = ProdiKampus::prodi(
                trim((string) $request->query('kampus', '')),
                trim((string) $request->query('jenjang', '')),
                trim((string) $request->query('bidang', '')),
                trim((string) $request->query('q', '')),
                (int) $request->query('limit', 50)
            );

            return ResponseHelper::success($hasil, 'Prodi dimuat');
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::channel('web_career')->error('Gagal memuat prodi: ' . $e->getMessage());

            return ResponseHelper::error('Gagal memuat prodi', 500);
        }
    }
}
