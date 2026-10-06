<?php

namespace App\Http\Controllers\Career\MasterJadwal;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Support\Career\AksesService;
use App\Support\Career\KodeUnik;
use App\Support\CareerShell;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Vinkla\Hashids\Facades\Hashids;

/**
 * WEB CAREER — MASTER JADWAL KEGIATAN (induk-detail: Jadwal + Agenda).
 * SPA + WEB (tanpa API controller). CRUD Query Builder langsung + ResponseHelper + Log channel.
 */
class MasterJadwalController extends Controller
{
    /** Kunci halaman untuk hak akses — sama dengan yang dipakai middleware rute. */
    private const PAGE = 'masterJadwalPage';

    public function index()
    {
        return Inertia::render('Career/admin/master-jadwal/masterJadwal', CareerShell::props('/master-jadwal', 'Master Jadwal Kegiatan'));
    }

    /**
     * List jadwal + agenda (anak) — Query Builder, join nama alur/pembuat.
     *
     * SELURUH PENYARINGAN DIKERJAKAN DI SINI, bukan di browser. Menyaring di layar
     * berarti seluruh jadwal tetap dikirim lebih dulu — termasuk milik kategori
     * yang tidak boleh dilihat pengguna, yang lalu "disembunyikan" oleh kode yang
     * bisa dibaca siapa pun di peramban.
     */
    public function list(Request $request)
    {
        try {
            $q = trim((string) $request->query('q', ''));
            $status = strtoupper(trim((string) $request->query('status', '')));
            $dari = $request->query('dari');
            $sampai = $request->query('sampai');

            // ── GERBANG KATEGORI ────────────────────────────────────────────
            // NULL = pengguna tidak dibatasi. Chip di layar hanya mengikuti; inilah
            // penjaga yang sesungguhnya, jadi ?kategori=MT yang diketik sendiri
            // tetap tidak membuka apa pun di luar jatah.
            $izin = AksesService::kategoriDiizinkan(self::PAGE);
            $kategori = AksesService::kategoriDiminta(self::PAGE, $request->query('kategori'));

            $jadwals = DB::table('N_WEB_CAREERS_Master_Jadwal as j')
                ->leftJoin('N_WEB_CAREERS_Users as u', 'u.Id_Users', '=', 'j.Created_By_Id')
                ->leftJoin('N_WEB_CAREERS_Master_Alur as a', 'a.Kode', '=', 'j.Alur_Kode')
                ->when($izin, fn ($w) => $w->whereIn('j.Kategori', $izin))
                ->when($q !== '', fn ($w) => $w->where(function ($x) use ($q) {
                    $x->where('j.Kegiatan', 'like', "%{$q}%")
                        ->orWhere('j.Kode', 'like', "%{$q}%")
                        ->orWhere('a.Nama', 'like', "%{$q}%");
                }))
                ->when($kategori !== '', fn ($w) => $w->where('j.Kategori', $kategori))
                ->when(in_array($status, ['AKTIF', 'NONAKTIF'], true), fn ($w) => $w->where('j.Status', $status))
                ->when($dari, fn ($w) => $w->whereDate('j.Created_At', '>=', $dari))
                ->when($sampai, fn ($w) => $w->whereDate('j.Created_At', '<=', $sampai))
                // TERBARU DI ATAS. Jadwal yang baru disusun adalah gelombang yang
                // sedang berjalan; menaruhnya di dasar daftar berarti ia digulir
                // dicari tiap kali, sementara gelombang tahun lalu menempati layar
                // pertama.
                ->orderByDesc('j.Id_Master_Jadwal')
                ->select('j.*', 'u.Nama as Pembuat', 'a.Nama as AlurNama')
                ->get();

            $agenda = DB::table('N_WEB_CAREERS_Master_Jadwal_Agenda')->orderBy('Urutan')->get()->groupBy('Master_Jadwal_Id');

            $rows = $jadwals->map(function ($j) use ($agenda) {
                return [
                    'id' => Hashids::encode($j->Id_Master_Jadwal),
                    'kode' => $j->Kode,
                    'kegiatan' => $j->Kegiatan,
                    'kategori' => $j->Kategori,
                    'alur' => $j->Alur_Kode,
                    'alurNama' => $j->AlurNama,
                    'status' => $j->Status === 'AKTIF' ? 'AKTIF' : 'NONAKTIF',
                    'createdBy' => $j->Pembuat ?: $j->Created_By,
                    'createdAt' => $j->Created_At,
                    'agenda' => collect($agenda->get($j->Id_Master_Jadwal, []))->map(fn ($g) => [
                        'jenis' => $g->Jenis,
                        'label' => $g->Label,
                        'mulai' => $g->Tanggal_Mulai,
                        'selesai' => $g->Tanggal_Selesai,
                    ])->values(),
                ];
            })->values();

            // Kategori yang boleh dilihat pengguna ini. Diambil dari master, bukan
            // dari data yang kebetulan ada: kategori yang belum punya satu pun
            // jadwal tetap harus bisa dipilih saat membuat yang pertama.
            return ResponseHelper::success([
                'data' => $rows,
                'kategori' => AksesService::tabKategori(self::PAGE),
            ], 'Data jadwal dimuat');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('Gagal memuat jadwal: ' . $e->getMessage());

            return ResponseHelper::error('Gagal memuat data jadwal', 500);
        }
    }

    /**
     * Kategori ini di luar jatah pengguna? — balasan galat, atau null.
     *
     * Dipakai store() dan update(). Ditulis sekali karena keduanya harus menjawab
     * sama: gerbang yang berbeda antara "buat" dan "ubah" berarti apa yang tidak
     * bisa dibuat langsung, bisa dibuat dalam dua langkah.
     */
    private function galatKategori(?string $kategori)
    {
        $izin = AksesService::kategoriDiizinkan(self::PAGE);

        if ($izin && ! in_array((string) $kategori, $izin, true)) {
            return ResponseHelper::error('Kategori ini di luar jatah akses Anda.', 403);
        }

        return null;
    }

    /** Aturan validasi jadwal + agenda. */
    private function rules(): array
    {
        return [
            'kegiatan' => 'required|string|max:120',
            'kategori' => 'required|string|max:20',
            'alur' => 'nullable|string|max:30',
            // Opsional: saat dibuat dipaksa AKTIF (lihat store()); saat diubah boleh
            // tidak dikirim -> status lama dipertahankan.
            'status' => 'nullable|in:AKTIF,NONAKTIF',
            'agenda' => 'nullable|array',
            'agenda.*.jenis' => 'required|string|max:20',
            'agenda.*.label' => 'required|string|max:150',
            'agenda.*.mulai' => 'nullable|date',
            'agenda.*.selesai' => 'nullable|date',
        ];
    }

    /**
     * Hari yang sudah lewat tidak bisa dipilih untuk agenda (masukan user 2 Okt
     * 2026) — cermin kalendernya (hariLampau / sebelumMulai di masterJadwal.vue).
     * Tanggal yang SUDAH tersimpan tetap boleh: agenda yang sudah berjalan (mis.
     * registrasi bulan lalu) ikut terkirim ulang setiap kali jadwalnya disunting.
     *
     * @param  string[]  $tanggalLama  'Y-m-d' agenda yang tersimpan sekarang.
     */
    private static function galatAgendaLampau(array $agenda, array $tanggalLama = []): ?string
    {
        $hariIni = now()->startOfDay();
        foreach (array_values($agenda) as $i => $g) {
            foreach (['mulai', 'selesai'] as $k) {
                if (empty($g[$k])) {
                    continue;
                }
                $t = Carbon::parse($g[$k]);
                if ($t->gte($hariIni) || in_array($t->format('Y-m-d'), $tanggalLama, true)) {
                    continue;
                }
                $label = trim((string) ($g['label'] ?? '')) ?: 'agenda ke-'.($i + 1);

                return "Tanggal {$k} \"{$label}\" sudah lewat — pilih hari ini atau sesudahnya.";
            }
        }

        return null;
    }

    /** Simpan baris agenda (anak) untuk sebuah jadwal. */
    private function simpanAgenda(int $jadwalId, array $agenda, ?int $userId): void
    {
        foreach (array_values($agenda) as $i => $g) {
            DB::table('N_WEB_CAREERS_Master_Jadwal_Agenda')->insert([
                'Master_Jadwal_Id' => $jadwalId,
                'Urutan' => $i + 1,
                'Jenis' => $g['jenis'],
                'Label' => $g['label'],
                'Tanggal_Mulai' => $g['mulai'] ?? null,
                'Tanggal_Selesai' => $g['selesai'] ?? null,
                'Alur_Idx' => null,
                'Created_By_Id' => $userId,
                'Updated_By_Id' => $userId,
            ]);
        }
    }

    /**
     * DUPLIKAT jadwal: seluruh agenda (label & tanggal) disalin apa adanya,
     * yang diganti hanya NAMA KEGIATAN dan ALUR SELEKSI.
     *
     * Alasannya: satu angkatan biasanya memakai susunan agenda yang sama persis
     * dengan angkatan sebelumnya — mengetik ulang belasan baris agenda cuma
     * mengundang salah ketik. Kategori ikut sumber karena alur terikat kategori.
     */
    public function duplikat(Request $request, $id)
    {
        try {
            $realId = Hashids::decode($id)[0] ?? null;
            $sumber = $realId ? DB::table('N_WEB_CAREERS_Master_Jadwal')->where('Id_Master_Jadwal', $realId)->first() : null;
            if (! $sumber) {
                return ResponseHelper::error('Jadwal sumber tidak ditemukan.', 404);
            }

            // Duplikat MEWARISI kategori sumbernya (lihat catatan di bawah), jadi
            // penjaganya membaca kategori SUMBER — bukan kiriman borang, yang di
            // sini memang tidak memuat kategori sama sekali. Tanpa ini, menyalin
            // adalah celah untuk membuat jadwal di kategori yang tak boleh dibuat
            // langsung.
            if ($galat = $this->galatKategori($sumber->Kategori)) {
                return $galat;
            }

            $data = $request->validate([
                'kegiatan' => 'required|string|max:120',
                'alur' => 'nullable|string|max:30',
            ], [
                'kegiatan.required' => 'Nama kegiatan baru wajib diisi.',
            ]);

            $userId = session('career_auth.id');
            $userName = session('career_auth.nama', 'ADMIN');
            $now = now();

            // Kode dibuat memakai lebar kolom penuh; sufiks _N hanya bila bentrok.
            $kode = KodeUnik::buat('N_WEB_CAREERS_Master_Jadwal', 'Kode', $data['kegiatan'], 30, 'JADWAL');
            $jumlahAgenda = 0;

            DB::transaction(function () use ($sumber, $data, $kode, $userId, $userName, $now, &$jumlahAgenda) {
                $baruId = DB::table('N_WEB_CAREERS_Master_Jadwal')->insertGetId([
                    'Kode' => $kode,
                    'Kegiatan' => $data['kegiatan'],
                    // Kategori ikut sumber: alur seleksi terikat kategori, jadi
                    // mengubahnya di sini justru membuat pasangannya tidak cocok.
                    'Kategori' => $sumber->Kategori,
                    'Alur_Kode' => $data['alur'] ?? $sumber->Alur_Kode,
                    'Status' => 'AKTIF',
                    'Created_At' => $now, 'Created_By' => $userName, 'Created_By_Id' => $userId,
                    'Updated_At' => $now, 'Updated_By' => $userName, 'Updated_By_Id' => $userId,
                ], 'Id_Master_Jadwal');

                $agenda = DB::table('N_WEB_CAREERS_Master_Jadwal_Agenda')
                    ->where('Master_Jadwal_Id', $sumber->Id_Master_Jadwal)
                    ->orderBy('Urutan')
                    ->get();

                foreach ($agenda as $i => $g) {
                    DB::table('N_WEB_CAREERS_Master_Jadwal_Agenda')->insert([
                        'Master_Jadwal_Id' => $baruId,
                        'Urutan' => $i + 1,
                        'Jenis' => $g->Jenis,
                        'Label' => $g->Label,
                        'Tanggal_Mulai' => $g->Tanggal_Mulai,
                        'Tanggal_Selesai' => $g->Tanggal_Selesai,
                        'Alur_Idx' => null,
                        'Created_By_Id' => $userId,
                        'Updated_By_Id' => $userId,
                    ]);
                }

                $jumlahAgenda = $agenda->count();
            });

            Log::channel('web_career')->info("Master jadwal {$sumber->Kode} diduplikat ke {$kode} oleh {$userName}");

            return ResponseHelper::success(
                ['kode' => $kode, 'agenda' => $jumlahAgenda],
                "Jadwal diduplikat ({$jumlahAgenda} agenda disalin). Periksa kembali tanggalnya.",
                201
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ResponseHelper::error(collect($e->errors())->flatten()->first() ?? 'Data tidak valid', 422);
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal duplikat jadwal #{$id}: " . $e->getMessage());

            return ResponseHelper::error('Gagal menduplikat jadwal', 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $data = $request->validate($this->rules());

            if ($galat = $this->galatKategori($data['kategori'] ?? null)) {
                return $galat;
            }
            if ($galat = self::galatAgendaLampau($data['agenda'] ?? [])) {
                return ResponseHelper::error($galat, 422);
            }
            $userId = session('career_auth.id');
            $userName = session('career_auth.nama', 'ADMIN');
            $now = now();

            // Kode = KEGIATAN di-uppercase (tanpa prefix), unik.
            // Kolom Kode varchar(30): potong ke 26 agar masih ada ruang untuk
            // sufiks "_N" saat harus dibuat unik. Nama panjang seperti
            // "TESTING - KEGIATAN MT 2026 BY FRANS" tadinya meluber & gagal insert.
            // Kode dibuat memakai lebar kolom penuh; sufiks _N hanya bila bentrok.
            $kode = KodeUnik::buat('N_WEB_CAREERS_Master_Jadwal', 'Kode', $data['kegiatan'], 30, 'JADWAL');
            DB::transaction(function () use ($data, $kode, $userId, $userName, $now) {
                $id = DB::table('N_WEB_CAREERS_Master_Jadwal')->insertGetId([
                    'Kode' => $kode,
                    'Kegiatan' => $data['kegiatan'],
                    'Kategori' => $data['kategori'],
                    'Alur_Kode' => $data['alur'] ?? null,
                    // Jadwal baru selalu aktif — admin tidak ditanya hal yang sudah pasti.
                    'Status' => 'AKTIF',
                    'Created_At' => $now, 'Created_By' => $userName, 'Created_By_Id' => $userId,
                    'Updated_At' => $now, 'Updated_By' => $userName, 'Updated_By_Id' => $userId,
                ], 'Id_Master_Jadwal');

                $this->simpanAgenda($id, $data['agenda'] ?? [], $userId);
            });

            Log::channel('web_career')->info("Master jadwal dibuat ({$kode}) oleh {$userName}");

            return ResponseHelper::success(null, 'Jadwal berhasil ditambahkan', 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ResponseHelper::error(collect($e->errors())->flatten()->first() ?? 'Data tidak valid', 422);
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('Gagal membuat jadwal: ' . $e->getMessage());

            return ResponseHelper::error('Gagal menyimpan data', 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $realId = Hashids::decode($id)[0] ?? null;
            $row = DB::table('N_WEB_CAREERS_Master_Jadwal')->where('Id_Master_Jadwal', $realId)->first();
            if (! $row) {
                return ResponseHelper::error('Data tidak ditemukan', 404);
            }
            $data = $request->validate($this->rules());

            if ($galat = $this->galatKategori($data['kategori'] ?? null)) {
                return $galat;
            }
            $tanggalLama = DB::table('N_WEB_CAREERS_Master_Jadwal_Agenda')
                ->where('Master_Jadwal_Id', $realId)
                ->get(['Tanggal_Mulai', 'Tanggal_Selesai'])
                ->flatMap(fn ($g) => [$g->Tanggal_Mulai, $g->Tanggal_Selesai])
                ->filter()
                ->map(fn ($t) => Carbon::parse($t)->format('Y-m-d'))
                ->unique()->values()->all();
            if ($galat = self::galatAgendaLampau($data['agenda'] ?? [], $tanggalLama)) {
                return ResponseHelper::error($galat, 422);
            }
            $userId = session('career_auth.id');
            $userName = session('career_auth.nama', 'ADMIN');

            // $row WAJIB ikut di-use: dipakai sebagai nilai jatuhan untuk Status.
            DB::transaction(function () use ($data, $realId, $userId, $userName, $row) {
                DB::table('N_WEB_CAREERS_Master_Jadwal')->where('Id_Master_Jadwal', $realId)->update([
                    'Kegiatan' => $data['kegiatan'],
                    'Kategori' => $data['kategori'],
                    'Alur_Kode' => $data['alur'] ?? null,
                    // Boleh tidak dikirim -> pertahankan status lama.
                    'Status' => $data['status'] ?? $row->Status,
                    'Updated_At' => now(), 'Updated_By' => $userName, 'Updated_By_Id' => $userId,
                ]);
                // Ganti seluruh agenda anak (hapus lama, simpan baru).
                DB::table('N_WEB_CAREERS_Master_Jadwal_Agenda')->where('Master_Jadwal_Id', $realId)->delete();
                $this->simpanAgenda($realId, $data['agenda'] ?? [], $userId);
            });

            Log::channel('web_career')->info("Master jadwal #{$realId} diperbarui");

            return ResponseHelper::success(null, 'Jadwal diperbarui');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ResponseHelper::error(collect($e->errors())->flatten()->first() ?? 'Data tidak valid', 422);
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal update jadwal #{$id}: " . $e->getMessage());

            return ResponseHelper::error('Gagal memperbarui data', 500);
        }
    }

    public function toggle(Request $request, $id)
    {
        try {
            $realId = Hashids::decode($id)[0] ?? null;
            $aktif = $request->boolean('aktif');
            $terpengaruh = DB::table('N_WEB_CAREERS_Master_Jadwal')->where('Id_Master_Jadwal', $realId)->update([
                'Status' => $aktif ? 'AKTIF' : 'NONAKTIF',
                'Updated_At' => now(), 'Updated_By' => session('career_auth.nama', 'ADMIN'), 'Updated_By_Id' => session('career_auth.id'),
            ]);
            if (! $terpengaruh) {
                return ResponseHelper::error('Data tidak ditemukan', 404);
            }
            Log::channel('web_career')->info("Master jadwal #{$realId} status " . ($aktif ? 'AKTIF' : 'NONAKTIF'));

            return ResponseHelper::success(null, 'Status diperbarui');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal toggle jadwal #{$id}: " . $e->getMessage());

            return ResponseHelper::error('Gagal mengubah status', 500);
        }
    }

    public function destroy($id)
    {
        try {
            $realId = Hashids::decode($id)[0] ?? null;
            $row = DB::table('N_WEB_CAREERS_Master_Jadwal')->where('Id_Master_Jadwal', $realId)->first();
            if (! $row) {
                return ResponseHelper::error('Data tidak ditemukan', 404);
            }
            DB::transaction(function () use ($realId) {
                DB::table('N_WEB_CAREERS_Master_Jadwal_Agenda')->where('Master_Jadwal_Id', $realId)->delete();
                DB::table('N_WEB_CAREERS_Master_Jadwal')->where('Id_Master_Jadwal', $realId)->delete();
            });
            Log::channel('web_career')->info("Master jadwal #{$realId} dihapus");

            return ResponseHelper::success(null, 'Jadwal dihapus');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal hapus jadwal #{$id}: " . $e->getMessage());

            return ResponseHelper::error('Gagal menghapus data', 500);
        }
    }
}
