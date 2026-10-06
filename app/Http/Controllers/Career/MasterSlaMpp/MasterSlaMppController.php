<?php

namespace App\Http\Controllers\Career\MasterSlaMpp;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Support\Career\SlaMpp;
use App\Support\CareerShell;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * WEB CAREER — MASTER SLA MPP: "level ini harus tuntas dalam berapa hari kerja".
 *
 * Satu baris per Level HRIS. STAFF 30 hari kerja, SENIOR OFFICER 60, dan
 * seterusnya — angkanya milik master supaya kebijakan bisa berubah tanpa
 * menyentuh kode.
 *
 * ── YANG PALING PENTING DI SINI: MENGUBAH MASTER TIDAK MENGUBAH MASA LALU ───
 *
 * Angka yang berlaku dibekukan ke barisnya sendiri pada detik MPP dibuat (lihat
 * SlaMpp + kolom Sla_* di N_WEB_CAREERS_Detail_MPP). Jadi menaikkan STAFF dari
 * 45 ke 25 hari tahun depan HANYA berlaku untuk MPP baru; MPP tahun ini tetap
 * dinilai dengan 45, dan laporan tahun lalu tetap cocok dengan dirinya sendiri.
 *
 * Karena itu halaman ini boleh disunting dengan tenang — dan karena itu pula
 * setiap penyuntingan tetap dicatat ke log: yang berubah adalah kebijakan untuk
 * MPP yang BELUM ada, dan pertanyaan "sejak kapan angkanya jadi 25" harus punya
 * jawaban.
 *
 * Level yang TIDAK punya baris di sini berarti tidak dikunci: MPP-nya bebas
 * memilih tanggal, persis seperti perilaku sebelum fitur ini ada.
 */
class MasterSlaMppController extends Controller
{
    private const TABEL = SlaMpp::TABEL;

    private const KODE_PERUSAHAAN = '001';

    /** Halaman (Inertia). Datanya diambil layar sendiri lewat list(). */
    public function index()
    {
        return Inertia::render(
            'Career/admin/master-sla-mpp/masterSlaMpp',
            CareerShell::props('/master-sla-mpp', 'Master SLA MPP')
        );
    }

    /**
     * Daftar aturan + SELURUH level HRIS yang belum punya aturan.
     *
     * Keduanya dikirim bersama, dan itu disengaja: yang ingin diketahui admin
     * saat membuka halaman ini bukan "aturan apa saja yang sudah saya buat",
     * melainkan "level mana yang masih bebas tanpa tenggat". Daftar yang hanya
     * memuat baris tersimpan menyembunyikan justru pertanyaan itu.
     */
    public function list(Request $request)
    {
        if (! SlaMpp::siap()) {
            return ResponseHelper::error(
                'Tabel master SLA MPP belum dipasang. Jalankan docs/20-8-2026/2026-08-20-sla-mpp-per-level.sql lebih dulu.',
                503,
            );
        }

        try {
            $q = trim((string) $request->query('q', ''));

            $rows = DB::table(self::TABEL.' as s')
                ->leftJoin('HRIS_Level as l', fn ($j) => $j
                    ->on('l.ID_Level', '=', 's.Id_Level')
                    ->where('l.Kode_Perusahaan', self::KODE_PERUSAHAAN))
                ->when($q !== '', fn ($w) => $w->where(function ($x) use ($q) {
                    $x->where('l.Keterangan', 'like', "%{$q}%")
                        ->orWhere('s.Nama_Level', 'like', "%{$q}%")
                        ->orWhere('s.Keterangan', 'like', "%{$q}%");
                }))
                ->orderBy('l.Level_Hierarchy')
                ->get([
                    's.Id_Sla_Mpp', 's.Id_Level', 's.Hari_Kerja', 's.Keterangan', 's.Flag_Aktif',
                    's.Nama_Level as NamaBeku', 's.Updated_At', 's.Updated_By',
                    'l.Keterangan as NamaLevel', 'l.Level_Hierarchy',
                ])
                ->map(fn ($r) => [
                    'id' => (int) $r->Id_Sla_Mpp,
                    'idLevel' => (int) $r->Id_Level,
                    // Nama level yang HIDUP dari HRIS; nama beku hanya cadangan
                    // bila levelnya sudah dihapus di sana.
                    'level' => $r->NamaLevel ?: $r->NamaBeku,
                    'hierarki' => (int) ($r->Level_Hierarchy ?? 0),
                    'hariKerja' => (int) $r->Hari_Kerja,
                    'keterangan' => $r->Keterangan,
                    'aktif' => $r->Flag_Aktif === 'Y',
                    'diubahPada' => $r->Updated_At,
                    'diubahOleh' => $r->Updated_By,
                ])
                ->values();

            $terpakai = $rows->pluck('idLevel')->all();

            $bebas = DB::table('HRIS_Level')
                ->where('Kode_Perusahaan', self::KODE_PERUSAHAAN)
                ->when($terpakai, fn ($w) => $w->whereNotIn('ID_Level', $terpakai))
                ->orderBy('Level_Hierarchy')
                ->get(['ID_Level', 'Keterangan', 'Level_Hierarchy'])
                ->map(fn ($r) => [
                    'idLevel' => (int) $r->ID_Level,
                    'level' => $r->Keterangan,
                    'hierarki' => (int) ($r->Level_Hierarchy ?? 0),
                ])
                ->values();

            return ResponseHelper::success(['data' => $rows, 'belumDiatur' => $bebas], 'Master SLA MPP');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('Gagal memuat master SLA MPP: '.$e->getMessage());

            return ResponseHelper::error('Gagal memuat data.', 500);
        }
    }

    /**
     * Simpan SATU aturan, atau BANYAK SEKALIGUS lewat `items`.
     *
     * Kebijakan SLA datang sebagai tabel — "staf 30, officer 45, manajer 90" —
     * bukan satu baris per hari. Menuntut admin membuka borang belasan kali
     * untuk menyalin satu tabel adalah pekerjaan yang tidak menambah apa pun.
     *
     * SATU TRANSAKSI untuk seluruh borongan. Sepuluh permintaan berturut-turut
     * bisa separuh berhasil, dan separuh-berhasil pada aturan kebijakan adalah
     * keadaan yang paling sulit dirapikan kembali: sebagian level terkunci,
     * sebagian bebas, tanpa seorang pun tahu mana yang mana.
     */
    public function store(Request $request)
    {
        $borongan = $request->input('items');
        if (! is_array($borongan)) {
            return $this->simpan($request, null);
        }

        if (! SlaMpp::siap()) {
            return ResponseHelper::error('Tabel master SLA MPP belum dipasang.', 503);
        }

        $data = $request->validate([
            'items' => 'required|array|min:1|max:100',
            'items.*.idLevel' => 'required|integer|distinct',
            'items.*.hariKerja' => 'required|integer|min:1|max:400',
            'items.*.keterangan' => 'nullable|string|max:400',
        ], [
            'items.*.idLevel.distinct' => 'Ada level yang terpilih dua kali.',
            'items.*.hariKerja.required' => 'Setiap level harus punya jumlah hari kerja.',
        ]);

        $idLevel = array_column($data['items'], 'idLevel');

        // Ditolak DI MUKA, bukan setelah separuh tersimpan: admin memilih level
        // dari daftar yang bisa saja sudah basi (aturannya baru dibuat di tab lain).
        $bentrok = DB::table(self::TABEL)->whereIn('Id_Level', $idLevel)->pluck('Id_Level')->all();
        if ($bentrok) {
            $nama = DB::table('HRIS_Level')->whereIn('ID_Level', $bentrok)->pluck('Keterangan')->implode(', ');

            return ResponseHelper::error("Level berikut sudah punya aturan SLA: {$nama}. Muat ulang halaman lalu sunting yang sudah ada.", 422);
        }

        $level = DB::table('HRIS_Level')
            ->where('Kode_Perusahaan', self::KODE_PERUSAHAAN)
            ->whereIn('ID_Level', $idLevel)
            ->pluck('Keterangan', 'ID_Level');

        if ($level->count() !== count($idLevel)) {
            return ResponseHelper::error('Ada level HRIS yang tidak dikenali.', 422);
        }

        try {
            $nama = session('career_auth.nama', 'ADMIN');
            $userId = session('career_auth.id');

            DB::transaction(function () use ($data, $level, $nama, $userId) {
                DB::table(self::TABEL)->insert(array_map(fn ($i) => [
                    'Id_Level' => (int) $i['idLevel'],
                    'Nama_Level' => $level[$i['idLevel']] ?? null,
                    'Hari_Kerja' => (int) $i['hariKerja'],
                    'Keterangan' => $i['keterangan'] ?? null,
                    'Flag_Aktif' => 'Y',
                    'Created_At' => now(),
                    'Created_By' => $nama,
                    'Created_By_Id' => $userId,
                ], $data['items']));
            });

            Log::channel('web_career')->info(sprintf(
                '[SLA MPP] %d aturan baru sekaligus (%s), oleh %s.',
                count($data['items']),
                collect($data['items'])->map(fn ($i) => ($level[$i['idLevel']] ?? $i['idLevel']).' = '.$i['hariKerja'].'hk')->implode('; '),
                $nama,
            ));

            return ResponseHelper::success(['jumlah' => count($data['items'])], count($data['items']).' aturan SLA disimpan.');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('Gagal menyimpan SLA MPP borongan: '.$e->getMessage());

            return ResponseHelper::error('Gagal menyimpan.', 500);
        }
    }

    public function update(Request $request, int $id)
    {
        return $this->simpan($request, $id);
    }

    /**
     * Aktif / nonaktif.
     *
     * MENONAKTIFKAN, BUKAN MENGHAPUS, adalah cara membebaskan sebuah level.
     * Menghapus barisnya membuang jawaban atas "kenapa MPP bulan lalu ditolak
     * karena tenggat" — sementara MPP-nya sendiri tetap membawa angka bekunya.
     */
    public function toggle(int $id)
    {
        if (! SlaMpp::siap()) {
            return ResponseHelper::error('Tabel master SLA MPP belum dipasang.', 503);
        }

        try {
            $row = DB::table(self::TABEL)->where('Id_Sla_Mpp', $id)->first(['Flag_Aktif']);
            if (! $row) {
                return ResponseHelper::error('Aturan tidak ditemukan.', 404);
            }

            $baru = $row->Flag_Aktif === 'Y' ? 'T' : 'Y';
            DB::table(self::TABEL)->where('Id_Sla_Mpp', $id)->update([
                'Flag_Aktif' => $baru,
                'Updated_At' => now(),
                'Updated_By' => session('career_auth.nama', 'ADMIN'),
                'Updated_By_Id' => session('career_auth.id'),
            ]);

            Log::channel('web_career')->info(
                "[SLA MPP] aturan #{$id} di-".($baru === 'Y' ? 'aktifkan' : 'nonaktifkan').' oleh '.session('career_auth.nama', 'ADMIN').'.'
            );

            return ResponseHelper::success(['aktif' => $baru === 'Y'], 'Status aturan diperbarui.');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal mengubah status SLA MPP #{$id}: ".$e->getMessage());

            return ResponseHelper::error('Gagal memproses.', 500);
        }
    }

    /**
     * HAPUS sebuah aturan.
     *
     * Dibedakan dari NONAKTIF, dan keduanya memang untuk hal yang berbeda:
     *
     *   nonaktif  aturannya tetap tercatat, cuma berhenti mengunci. Dipakai
     *             saat kebijakan sedang ditinjau dan riwayatnya masih dibaca.
     *   hapus     barisnya dibuang sama sekali. Dipakai saat aturannya memang
     *             tidak pernah relevan — level yang tak pernah direkrut lewat
     *             MPP, atau baris isian awal yang salah sasaran.
     *
     * MPP YANG SUDAH ADA TIDAK IKUT TERPENGARUH. Angka SLA-nya beku di
     * barisnya sendiri (kolom Sla_*), jadi menghapus master tidak membatalkan
     * satu pun tenggat yang sudah ditetapkan — juga tidak mengubah penilaian
     * yang sudah terjadi. Yang berubah cuma: MPP BARU di level itu tidak lagi
     * dikunci.
     */
    public function destroy(int $id)
    {
        if (! SlaMpp::siap()) {
            return ResponseHelper::error('Tabel master SLA MPP belum dipasang.', 503);
        }

        try {
            $row = DB::table(self::TABEL)->where('Id_Sla_Mpp', $id)->first(['Id_Level', 'Nama_Level', 'Hari_Kerja']);
            if (! $row) {
                return ResponseHelper::error('Aturan tidak ditemukan.', 404);
            }

            DB::table(self::TABEL)->where('Id_Sla_Mpp', $id)->delete();

            // Angkanya ikut dicatat: aturan yang dihapus tidak punya baris lagi di
            // mana pun, jadi log inilah satu-satunya jejak bahwa ia pernah ada.
            Log::channel('web_career')->info(sprintf(
                '[SLA MPP] aturan dihapus: %s (%d hari kerja), oleh %s. MPP yang sudah ada tidak berubah.',
                $row->Nama_Level ?: ('level #'.$row->Id_Level), (int) $row->Hari_Kerja, session('career_auth.nama', 'ADMIN')
            ));

            return ResponseHelper::success(null, 'Aturan SLA dihapus. MPP baru di level ini tertahan sampai aturannya diisi ulang.');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal menghapus SLA MPP #{$id}: ".$e->getMessage());

            return ResponseHelper::error('Gagal menghapus.', 500);
        }
    }

    /**
     * GET /api/v1/karir/master-sla-mpp/hitung?idLevel=..&mulai=..
     *
     * DIPANGGIL LAYAR SAAT LEVEL DIPILIH, dan inilah yang mengunci pemilih
     * tanggalnya. Perhitungannya SlaMpp — sumber yang sama persis dengan yang
     * dipakai pintu simpan. Kalau layar menghitung sendiri, cepat atau lambat
     * kalendernya menawarkan tanggal yang lalu ditolak server, dan admin
     * menyalahkan sistem untuk aturan yang memang ada.
     */
    public function hitung(Request $request)
    {
        $data = $request->validate([
            'idLevel' => 'required|integer',
            'mulai' => 'nullable|date',
            // Angka yang SEDANG DIKETIK di borang master — belum tersimpan, jadi
            // tidak bisa dibaca dari master. Tanpa ini, contoh tenggat di modal
            // tidak pernah muncul untuk level yang baru mau diatur.
            'hari' => 'nullable|integer|min:1|max:400',
        ]);

        $hasil = isset($data['hari'])
            ? SlaMpp::batasDariAngka((int) $data['hari'], $data['mulai'] ?? null)
            : SlaMpp::batas((int) $data['idLevel'], $data['mulai'] ?? null);

        return ResponseHelper::success(
            $hasil,
            $hasil['batas']
                ? "Batas {$hasil['hari']} hari kerja — paling lambat {$hasil['batas']}."
                : 'Level ini belum punya ketentuan SLA, jadi tanggal periode target belum bisa dihitung.',
        );
    }

    // ═══════════════════════ INTERNAL ═══════════════════════

    private function simpan(Request $request, ?int $id)
    {
        if (! SlaMpp::siap()) {
            return ResponseHelper::error('Tabel master SLA MPP belum dipasang.', 503);
        }

        $data = $request->validate([
            'idLevel' => [
                'required', 'integer',
                // SATU ATURAN PER LEVEL — ditegakkan di sini DAN oleh indeks unik
                // di database. Yang di sini memberi kalimat yang bisa dibaca;
                // yang di database menutup jalan yang tidak lewat sini.
                Rule::unique(self::TABEL, 'Id_Level')->ignore($id, 'Id_Sla_Mpp'),
            ],
            'hariKerja' => 'required|integer|min:1|max:400',
            'keterangan' => 'nullable|string|max:400',
        ], [
            'idLevel.required' => 'Level wajib dipilih.',
            'idLevel.unique' => 'Level ini sudah punya aturan SLA — sunting yang sudah ada, jangan dibuat dua.',
            'hariKerja.required' => 'Jumlah hari kerja wajib diisi.',
            'hariKerja.min' => 'Jumlah hari kerja minimal 1.',
            'hariKerja.max' => 'Jumlah hari kerja terlalu besar — periksa kembali angkanya.',
        ]);

        try {
            $nama = session('career_auth.nama', 'ADMIN');
            $level = DB::table('HRIS_Level')
                ->where('Kode_Perusahaan', self::KODE_PERUSAHAAN)
                ->where('ID_Level', $data['idLevel'])
                ->first(['Keterangan']);

            if (! $level) {
                return ResponseHelper::error('Level HRIS tidak dikenali.', 422);
            }

            $isi = [
                'Id_Level' => (int) $data['idLevel'],
                // Nama level ikut dibekukan — jaring pengaman untuk mata manusia
                // bila HRIS kelak mengganti namanya.
                'Nama_Level' => $level->Keterangan,
                'Hari_Kerja' => (int) $data['hariKerja'],
                'Keterangan' => $data['keterangan'] ?? null,
                'Updated_At' => now(),
                'Updated_By' => $nama,
                'Updated_By_Id' => session('career_auth.id'),
            ];

            if ($id) {
                $lama = DB::table(self::TABEL)->where('Id_Sla_Mpp', $id)->first(['Hari_Kerja']);
                if (! $lama) {
                    return ResponseHelper::error('Aturan tidak ditemukan.', 404);
                }

                DB::table(self::TABEL)->where('Id_Sla_Mpp', $id)->update($isi);

                // ANGKA LAMA IKUT DICATAT. "Sejak kapan STAFF jadi 25 hari" adalah
                // pertanyaan yang pasti ditanyakan begitu ada MPP yang dinilai
                // telat, dan jawabannya tidak boleh cuma ingatan orang.
                Log::channel('web_career')->info(sprintf(
                    '[SLA MPP] %s: %d → %d hari kerja, oleh %s. MPP yang sudah ada TIDAK berubah (angkanya beku di masing-masing barisnya).',
                    $level->Keterangan, (int) $lama->Hari_Kerja, (int) $data['hariKerja'], $nama
                ));

                return ResponseHelper::success(['id' => $id], 'Aturan SLA diperbarui.');
            }

            $isi['Flag_Aktif'] = 'Y';
            $isi['Created_At'] = now();
            $isi['Created_By'] = $nama;
            $isi['Created_By_Id'] = session('career_auth.id');

            $baru = DB::table(self::TABEL)->insertGetId($isi, 'Id_Sla_Mpp');

            Log::channel('web_career')->info(sprintf(
                '[SLA MPP] aturan baru: %s = %d hari kerja, oleh %s.',
                $level->Keterangan, (int) $data['hariKerja'], $nama
            ));

            return ResponseHelper::success(['id' => (int) $baru], 'Aturan SLA disimpan.');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('Gagal menyimpan SLA MPP: '.$e->getMessage());

            return ResponseHelper::error('Gagal menyimpan.', 500);
        }
    }
}
