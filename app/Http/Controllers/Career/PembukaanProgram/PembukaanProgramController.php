<?php

namespace App\Http\Controllers\Career\PembukaanProgram;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Support\Career\AksesService;
use App\Support\CareerShell;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Vinkla\Hashids\Facades\Hashids;

/**
 * WEB CAREER — PEMBUKAAN PROGRAM (publikasi 1 program ke landing + window pendaftaran).
 * Channel UMUM/KAMPUS SUDAH DIGABUNG: semua pembukaan bersifat publik (kolom Channel
 * dipertahankan sebagai 'UMUM' agar skema lama tetap valid). Pembatasan kampus mitra
 * diatur lewat SYARAT (operator ADA_DI) di Program Kegiatan, bukan whitelist di sini.
 * SPA + WEB. CRUD Query Builder + ResponseHelper + Log channel + Hashids.
 */
class PembukaanProgramController extends Controller
{
    /** Nilai channel tunggal setelah UMUM & KAMPUS digabung. */
    private const CHANNEL = 'UMUM';

    public function index()
    {
        return Inertia::render('Career/admin/pembukaan-program/pembukaanProgram', CareerShell::props('/karir/pembukaan', 'Pembukaan Program'));
    }

    public function list(Request $request)
    {
        try {
            // Pola & perilaku SAMA dengan Program Kegiatan: filter server-side,
            // urut terbaru di atas, dan tab kategori dari master + hak akses.
            $q = trim((string) $request->query('q', ''));
            $kategori = trim((string) $request->query('kategori', ''));
            $status = strtoupper(trim((string) $request->query('status', '')));
            $dari = $request->query('dari');
            $sampai = $request->query('sampai');

            $izin = AksesService::kategoriDiizinkan('pembukaanPage');

            /*
             * ══ LINGKUP PIC — GERBANG YANG SELAMA INI TIDAK ADA ══════════════
             *
             * Sama seperti Program Kegiatan: kategori sudah dijaga, lingkup PIC
             * belum pernah. Rekruter berlingkup SENDIRI tetap melihat SELURUH
             * terbitan — termasuk yang lokernya dipegang orang lain berikut
             * jumlah pelamarnya. Itu bukan haknya.
             *
             * Sebuah terbitan dianggap miliknya bila SALAH SATU benar:
             *   - ada posisi program itu yang PIC-nya tercakup lingkup, ATAU
             *   - PIC program induknya tercakup lingkup, ATAU
             *   - ia yang membuat terbitannya.
             */
            $bolehPic = AksesService::picDiizinkan('pembukaanPage');
            $sayaId = session('career_auth.id');

            $dasar = fn () => DB::table('N_WEB_CAREERS_Pembukaan as pb')
                ->leftJoin('N_WEB_CAREERS_Users as u', 'u.Id_Users', '=', 'pb.Created_By_Id')
                ->leftJoin('N_WEB_CAREERS_Program as p', 'p.Id_Program', '=', 'pb.Program_Id')
                ->leftJoin('N_WEB_CAREERS_Program_Batch as b', 'b.Id_Program_Batch', '=', 'pb.Program_Batch_Id')
                ->when($izin, fn ($w) => $w->whereIn('p.Kategori', $izin))
                ->when($q !== '', fn ($w) => $w->where(function ($x) use ($q) {
                    $x->where('pb.Kode', 'like', "%{$q}%")
                        ->orWhere('p.Nama', 'like', "%{$q}%")
                        ->orWhere('p.Kode', 'like', "%{$q}%")
                        ->orWhere('b.Nama', 'like', "%{$q}%");
                }))
                ->when(in_array($status, ['TERBIT', 'DRAFT'], true), fn ($w) => $w->where('pb.Status_Publish', $status))
                ->when($dari, fn ($w) => $w->whereDate('pb.Created_At', '>=', $dari))
                ->when($sampai, fn ($w) => $w->whereDate('pb.Created_At', '<=', $sampai))
                ->when($bolehPic !== null, function ($w) use ($bolehPic, $sayaId) {
                    // Larik KOSONG = "dibatasi, tapi tak ada yang cocok".
                    // Penanda mustahil menjaga whereIn memulangkan nol baris.
                    $kode = $bolehPic ?: ['__tidak_ada__'];

                    $w->where(function ($x) use ($kode, $sayaId) {
                        $x->whereIn('p.Pic_Kode_Karyawan', $kode)
                            ->orWhereExists(fn ($e) => $e
                                ->from('N_WEB_CAREERS_Program_Posisi as pp')
                                ->whereColumn('pp.Program_Id', 'p.Id_Program')
                                ->whereIn('pp.Pic_Kode_Karyawan', $kode))
                            ->when($sayaId, fn ($y) => $y->orWhere('pb.Created_By_Id', $sayaId));
                    });
                });

            $hitungKategori = (clone $dasar())
                ->select('p.Kategori', DB::raw('COUNT(*) as jml'))
                ->groupBy('p.Kategori')
                ->pluck('jml', 'Kategori');

            $rows = $dasar()
                ->when($kategori !== '', fn ($w) => $w->where('p.Kategori', $kategori))
                // Terbaru → terlama: berdasarkan waktu dibuat, ID sebagai cadangan.
                ->orderByDesc('pb.Created_At')
                ->orderByDesc('pb.Id_Pembukaan')
                // ── ISI PROGRAMNYA IKUT DIBACA ──────────────────────────────
                //
                // Halaman ini menjawab "apa yang sedang terbuka untuk umum".
                // Selama ini yang tampil hanya jendela waktunya — tanggal buka
                // dan tutup — sementara yang sebenarnya dipublikasikan adalah
                // LOWONGANNYA. Admin harus membuka Program Kegiatan di tab lain
                // hanya untuk tahu terbitan ini membawa berapa posisi dan MPP
                // mana, lalu kembali lagi ke sini.
                ->leftJoin('N_WEB_CAREERS_Master_Alur as a', 'a.Kode', '=', 'p.Alur_Kode')
                ->leftJoin('Karyawan as kp', function ($j) {
                    $j->on('kp.Kode_Karyawan', '=', 'p.Pic_Kode_Karyawan')
                        ->where('kp.Kode_Perusahaan', '=', '001');
                })
                ->select(
                    'pb.*',
                    'u.Nama as Pembuat',
                    'p.Id_Program as ProgramId',
                    'p.Nama as ProgramNama',
                    'p.Kode as ProgramKode',
                    'p.Kategori as ProgramKategori',
                    'p.Penyelenggara as ProgramPenyelenggara',
                    'p.Status as ProgramStatus',
                    'p.Pic_Kode_Karyawan as ProgramPicKode',
                    'a.Nama as AlurNama',
                    'kp.Nama as PicNama',
                    'b.Nama as BatchNama'
                )
                ->get();

            // Satu permintaan untuk seluruh baris, bukan satu per baris: daftar
            // pembukaan bisa berisi puluhan terbitan, dan menanyakan posisinya
            // satu-satu adalah puluhan perjalanan ke basis data untuk data yang
            // muat dalam satu.
            $posisiSemua = DB::table('N_WEB_CAREERS_Program_Posisi')
                ->whereIn('Program_Id', $rows->pluck('ProgramId')->filter()->unique()->all() ?: [0])
                ->orderBy('Id_Program_Posisi')
                ->get();

            // Nama pemegang loker — satu kueri untuk seluruh kode yang benar-benar
            // muncul, bukan seluruh isi tabel Karyawan. Saringan "Pemegang Loker"
            // di layar dirakit dari kode yang MEMANG ada di data, jadi setiap
            // pilihan pasti menghasilkan sedikitnya satu baris.
            $picNama = DB::table('Karyawan')
                ->where('Kode_Perusahaan', '001')
                ->whereIn('Kode_Karyawan', $posisiSemua->pluck('Pic_Kode_Karyawan')->filter()->unique()->all() ?: ['__'])
                ->pluck('Nama', 'Kode_Karyawan');

            $posisi = $posisiSemua->groupBy('Program_Id');

            // Jumlah pelamar per terbitan — angka besar di kanan tiap baris
            // daftar. Dihitung sekali secara agregat, bukan satu kueri per
            // baris: daftar 40 terbitan akan berarti 40 perjalanan ke basis
            // data untuk satu angka yang muat dalam satu.
            $pelamar = DB::table('N_WEB_CAREERS_Lamaran')
                ->whereIn('Pembukaan_Id', $rows->pluck('Id_Pembukaan')->all() ?: [0])
                ->selectRaw('Pembukaan_Id, COUNT(*) AS n')
                ->groupBy('Pembukaan_Id')
                ->pluck('n', 'Pembukaan_Id');

            $data = $rows->map(fn ($r) => [
                'id' => Hashids::encode($r->Id_Pembukaan),
                'kode' => $r->Kode,
                'program' => $r->ProgramKode,
                'programNama' => $r->ProgramNama,
                'kategori' => $r->ProgramKategori,
                'masaBerlaku' => $r->Masa_Berlaku,
                'buka' => $r->Tanggal_Buka,
                'tutup' => $r->Tanggal_Tutup,
                'batchNama' => $r->BatchNama,
                'statusPublish' => $r->Status_Publish,
                'createdBy' => $r->Pembuat ?: $r->Created_By,
                'createdAt' => $r->Created_At,
                'updatedBy' => $r->Updated_By,
                'updatedAt' => $r->Updated_At,
                'jmlPelamar' => (int) ($pelamar[$r->Id_Pembukaan] ?? 0),
                // ── Konteks program, untuk panel kanan ──────────────────────
                'alurNama' => $r->AlurNama,
                'penyelenggara' => $r->ProgramPenyelenggara,
                'programStatus' => $r->ProgramStatus,
                'picKode' => $r->ProgramPicKode,
                'picNama' => $r->PicNama,
                'posisi' => collect($posisi->get($r->ProgramId, []))->map(fn ($x) => [
                    // `id` ikut dikirim: sakelar per loker perlu menyebut baris
                    // mana yang dimatikan.
                    'id' => (int) $x->Id_Program_Posisi,
                    'posisi' => $x->Posisi,
                    'departemen' => $x->Departemen,
                    'lokasi' => $x->Lokasi,
                    'level' => $x->Level ?? null,
                    'kuota' => (int) $x->Kuota,
                    'terisi' => (int) ($x->Terisi ?? 0),
                    'status' => $x->Status,
                    'mppRef' => $x->Mpp_Ref ?? null,
                    // Siapa pemegang loker ini — dipakai saringan "Pemegang
                    // loker" di panel kiri, dan menjelaskan kenapa sebuah
                    // terbitan muncul pada lingkup TIM.
                    'picKode' => $x->Pic_Kode_Karyawan ?? null,
                    'picNama' => $x->Pic_Kode_Karyawan ? ($picNama[$x->Pic_Kode_Karyawan] ?? null) : null,
                    // NULL dibaca AKTIF — sama dengan aturan di seluruh aplikasi.
                    // Baris lama yang sempat lolos sebelum kolomnya ada tidak
                    // boleh diam-diam hilang dari landing.
                    'aktif' => ($x->Flag_Aktif ?? 'Y') !== 'N',
                    'nonaktifAt' => $x->Nonaktif_At ?? null,
                ])->values(),
            ])->values();

            $tabKategori = DB::table('N_WEB_CAREERS_Master_Talent_Acquisition')
                ->where('Flag_Aktif', 'Y')
                ->when($izin, fn ($w) => $w->whereIn('Kode', $izin))
                ->orderBy('Id_Master_Talent_Acquisition')
                ->get(['Kode', 'Nama'])
                ->map(fn ($k) => [
                    'kode' => $k->Kode,
                    'nama' => $k->Nama,
                    'jumlah' => (int) ($hitungKategori[$k->Kode] ?? 0),
                ])
                ->values();

            return ResponseHelper::success([
                'data' => $data,
                'kategori' => $tabKategori,
                'total' => (int) $hitungKategori->sum(),
                // Layar memakai ini untuk memutuskan apakah saringan "Pemegang
                // loker" ada gunanya digambar sama sekali — pada lingkup
                // SENDIRI tidak ada yang bisa dipilih selain diri sendiri, dan
                // kotak berisi satu pilihan yang sudah pasti hanya menyisakan
                // pertanyaan apa gunanya.
                'akses' => [
                    'lingkupPic' => AksesService::lingkupPic('pembukaanPage'),
                    'kodeSaya' => AksesService::kodeKaryawanSaya(),
                ],
            ], 'Data pembukaan dimuat');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('Gagal memuat pembukaan: ' . $e->getMessage());

            return ResponseHelper::error('Gagal memuat data pembukaan', 500);
        }
    }

    /** Daftar program (BERJALAN) + detail untuk KARTU picker di modal "Buka Program". */
    public function programs()
    {
        try {
            $izin = AksesService::kategoriDiizinkan('pembukaanPage');

            $rows = DB::table('N_WEB_CAREERS_Program as p')
                ->leftJoin('N_WEB_CAREERS_Master_Alur as a', 'a.Kode', '=', 'p.Alur_Kode')
                ->leftJoin('N_WEB_CAREERS_Master_Jadwal as j', 'j.Kode', '=', 'p.Jadwal_Kode')
                ->where('p.Status', 'BERJALAN')
                ->when($izin, fn ($w) => $w->whereIn('p.Kategori', $izin))
                // Terbaru → terlama, disamakan dengan daftar Program Kegiatan:
                // waktu dibuat yang menentukan, Id hanya pemecah seri.
                ->orderByDesc('p.Created_At')
                ->orderByDesc('p.Id_Program')
                ->select('p.Kode', 'p.Nama', 'p.Kategori', 'p.Penyelenggara', 'p.Jadwal_Kode', 'a.Nama as AlurNama', 'j.Kegiatan as JadwalNama')
                ->get();

            // Jumlah posisi per program.
            $posisiCount = DB::table('N_WEB_CAREERS_Program_Posisi as x')
                ->join('N_WEB_CAREERS_Program as p', 'p.Id_Program', '=', 'x.Program_Id')
                ->whereIn('p.Kode', $rows->pluck('Kode')->all() ?: [''])
                ->select('p.Kode', DB::raw('COUNT(*) as J'))->groupBy('p.Kode')->pluck('J', 'Kode');

            // Rentang tanggal dari agenda jadwal (mulai paling awal – selesai paling akhir).
            $jadwalKodes = $rows->pluck('Jadwal_Kode')->filter()->unique()->all();
            $range = $jadwalKodes
                ? DB::table('N_WEB_CAREERS_Master_Jadwal_Agenda as ag')
                    ->join('N_WEB_CAREERS_Master_Jadwal as j', 'j.Id_Master_Jadwal', '=', 'ag.Master_Jadwal_Id')
                    ->whereIn('j.Kode', $jadwalKodes)
                    ->select('j.Kode', DB::raw('MIN(ag.Tanggal_Mulai) as mulai'), DB::raw('MAX(ag.Tanggal_Selesai) as selesai'))
                    ->groupBy('j.Kode')->get()->keyBy('Kode')
                : collect();

            $data = $rows->map(fn ($r) => [
                'kode' => $r->Kode,
                'nama' => $r->Nama,
                'kategori' => $r->Kategori,
                'penyelenggara' => $r->Penyelenggara ?: 'EVO Group',
                'alur' => $r->AlurNama,
                'jadwal' => $r->JadwalNama,
                'jumlahPosisi' => (int) ($posisiCount[$r->Kode] ?? 0),
                'tglMulai' => $r->Jadwal_Kode ? ($range[$r->Jadwal_Kode]->mulai ?? null) : null,
                'tglSelesai' => $r->Jadwal_Kode ? ($range[$r->Jadwal_Kode]->selesai ?? null) : null,
            ])->values();

            return ResponseHelper::success($data, 'Daftar program');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('Gagal memuat program picker: ' . $e->getMessage());

            return ResponseHelper::error('Gagal memuat program', 500);
        }
    }

    private function rules(): array
    {
        return [
            'program' => 'required|string|max:40',
            'masaBerlaku' => 'required|in:BERBATAS,EVERGREEN',
            'buka' => 'nullable|date',
            'tutup' => 'nullable|date',
            // Status publish tak lagi diisi di form CREATE (otomatis TERBIT).
            // Tetap diterima untuk UPDATE / toggle dari daftar.
            'statusPublish' => 'nullable|in:DRAFT,TERBIT',
        ];
    }

    /**
     * Hari yang sudah lewat tidak bisa dipilih sebagai tanggal buka / tutup
     * (masukan user 2 Okt 2026) — cermin kalendernya (hariLampau / sebelumMulai
     * di pembukaanProgram.vue). Tanggal lama yang TIDAK diubah tetap boleh:
     * pembukaan yang sudah berjalan masih bisa disunting.
     */
    private static function galatHariLampau(array $data, ?object $lama = null): ?string
    {
        $cek = [
            ['buka', 'Tanggal buka', $lama->Tanggal_Buka ?? null],
            ['tutup', 'Tanggal tutup', $lama->Tanggal_Tutup ?? null],
        ];
        foreach ($cek as [$kunci, $label, $nilaiLama]) {
            if (empty($data[$kunci]) || ($kunci === 'tutup' && $data['masaBerlaku'] === 'EVERGREEN')) {
                continue;
            }
            $baru = Carbon::parse($data[$kunci]);
            if (! $baru->lt(now()->startOfDay())) {
                continue;
            }
            if ($nilaiLama && Carbon::parse($nilaiLama)->format('Y-m-d H:i') === $baru->format('Y-m-d H:i')) {
                continue;
            }

            return "{$label} tidak boleh di hari yang sudah lewat — pilih hari ini atau sesudahnya.";
        }

        return null;
    }

    private function programId(string $kode): ?int
    {
        return DB::table('N_WEB_CAREERS_Program')->where('Kode', $kode)->value('Id_Program');
    }

    public function store(Request $request)
    {
        try {
            $data = $request->validate($this->rules());
            if ($galat = self::galatHariLampau($data)) {
                return ResponseHelper::error($galat, 422);
            }
            $programId = $this->programId($data['program']);
            if (! $programId) {
                return ResponseHelper::error('Program tidak ditemukan.', 422);
            }
            $userId = session('career_auth.id');
            $userName = session('career_auth.nama', 'ADMIN');
            $now = now();

            $kode = 'PUB-' . str_pad((string) (DB::table('N_WEB_CAREERS_Pembukaan')->max('Id_Pembukaan') + 1), 3, '0', STR_PAD_LEFT);

            DB::table('N_WEB_CAREERS_Pembukaan')->insert([
                'Kode' => $kode, 'Program_Id' => $programId, 'Channel' => self::CHANNEL, 'Masa_Berlaku' => $data['masaBerlaku'],
                'Tanggal_Buka' => $data['buka'] ?? null, 'Tanggal_Tutup' => $data['masaBerlaku'] === 'EVERGREEN' ? null : ($data['tutup'] ?? null),
                // Buka Program = langsung TERBIT. Draft diatur belakangan lewat toggle di daftar.
                'Program_Batch_Id' => null, 'Status_Publish' => 'TERBIT',
                'Created_At' => $now, 'Created_By' => $userName, 'Created_By_Id' => $userId, 'Updated_At' => $now, 'Updated_By' => $userName, 'Updated_By_Id' => $userId,
            ]);

            Log::channel('web_career')->info("Pembukaan dibuat ({$kode}) oleh {$userName}");

            return ResponseHelper::success(null, 'Pembukaan berhasil ditambahkan', 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ResponseHelper::error(collect($e->errors())->flatten()->first() ?? 'Data tidak valid', 422);
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('Gagal membuat pembukaan: ' . $e->getMessage());

            return ResponseHelper::error('Gagal menyimpan data', 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $realId = Hashids::decode($id)[0] ?? null;
            $row = DB::table('N_WEB_CAREERS_Pembukaan')->where('Id_Pembukaan', $realId)->first();
            if (! $row) {
                return ResponseHelper::error('Data tidak ditemukan', 404);
            }
            $data = $request->validate($this->rules());
            if ($galat = self::galatHariLampau($data, $row)) {
                return ResponseHelper::error($galat, 422);
            }
            $programId = $this->programId($data['program']);
            if (! $programId) {
                return ResponseHelper::error('Program tidak ditemukan.', 422);
            }
            $userId = session('career_auth.id');
            $userName = session('career_auth.nama', 'ADMIN');

            DB::table('N_WEB_CAREERS_Pembukaan')->where('Id_Pembukaan', $realId)->update([
                'Program_Id' => $programId, 'Channel' => self::CHANNEL, 'Masa_Berlaku' => $data['masaBerlaku'],
                'Tanggal_Buka' => $data['buka'] ?? null, 'Tanggal_Tutup' => $data['masaBerlaku'] === 'EVERGREEN' ? null : ($data['tutup'] ?? null),
                // Pertahankan status lama bila tak dikirim (field disembunyikan di form).
                'Status_Publish' => $data['statusPublish'] ?? $row->Status_Publish,
                'Updated_At' => now(), 'Updated_By' => $userName, 'Updated_By_Id' => $userId,
            ]);

            Log::channel('web_career')->info("Pembukaan #{$realId} diperbarui");

            return ResponseHelper::success(null, 'Pembukaan diperbarui');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ResponseHelper::error(collect($e->errors())->flatten()->first() ?? 'Data tidak valid', 422);
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal update pembukaan #{$id}: " . $e->getMessage());

            return ResponseHelper::error('Gagal memperbarui data', 500);
        }
    }

    public function toggle(Request $request, $id)
    {
        try {
            $realId = Hashids::decode($id)[0] ?? null;
            $aktif = $request->boolean('aktif');
            $terpengaruh = DB::table('N_WEB_CAREERS_Pembukaan')->where('Id_Pembukaan', $realId)->update([
                'Status_Publish' => $aktif ? 'TERBIT' : 'DRAFT',
                'Updated_At' => now(), 'Updated_By' => session('career_auth.nama', 'ADMIN'), 'Updated_By_Id' => session('career_auth.id'),
            ]);
            if (! $terpengaruh) {
                return ResponseHelper::error('Data tidak ditemukan', 404);
            }
            Log::channel('web_career')->info("Pembukaan #{$realId} status " . ($aktif ? 'TERBIT' : 'DRAFT'));

            return ResponseHelper::success(null, 'Status diperbarui');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal toggle pembukaan #{$id}: " . $e->getMessage());

            return ResponseHelper::error('Gagal mengubah status', 500);
        }
    }

    /**
     * PATCH .../pembukaan/{id}/posisi — NYALAKAN / MATIKAN LOKER SATU PER SATU.
     *
     * ══ KENAPA ADA ══
     *
     * Satu program bisa membawa sepuluh MPP, dan sampai sekarang publikasinya
     * hanya punya satu sakelar. Menutup MPP nomor 5 berarti menutup kesepuluhnya
     * — atau, yang selama ini benar-benar dilakukan orang, menghapus barisnya.
     * Yang kedua jauh lebih mahal: bersama baris itu hilang MPP-nya, PIC-nya,
     * dan tautannya ke lamaran yang sudah masuk.
     *
     * Menerima BANYAK id sekaligus, jadi satu endpoint melayani dua perintah
     * yang di layar terasa berbeda: "matikan yang ini" dan "matikan semua".
     * Dua endpoint untuk satu tulisan yang sama hanya melahirkan dua tempat
     * yang bisa berselisih soal siapa yang boleh melakukannya.
     */
    public function togglePosisi(Request $request, $id)
    {
        try {
            $realId = Hashids::decode($id)[0] ?? null;
            if (! $realId) {
                return ResponseHelper::error('Data tidak ditemukan', 404);
            }

            $data = $request->validate([
                'posisiIds' => 'required|array|min:1|max:200',
                'posisiIds.*' => 'required|integer',
                'aktif' => 'required|boolean',
            ], [
                'posisiIds.required' => 'Pilih dulu loker yang akan diubah.',
            ]);

            $pembukaan = DB::table('N_WEB_CAREERS_Pembukaan')->where('Id_Pembukaan', $realId)->first();
            if (! $pembukaan) {
                return ResponseHelper::error('Pembukaan tidak ditemukan', 404);
            }

            // ── LOKERNYA HARUS MILIK PROGRAM INI ────────────────────────────
            //
            // Tanpa pemeriksaan ini, id loker mana pun yang ditempelkan ke
            // permintaan akan dimatikan — termasuk milik program lain yang
            // pengirimnya tidak berhak menyentuhnya sama sekali.
            $sah = DB::table('N_WEB_CAREERS_Program_Posisi')
                ->where('Program_Id', $pembukaan->Program_Id)
                ->whereIn('Id_Program_Posisi', $data['posisiIds'])
                ->pluck('Id_Program_Posisi');

            if ($sah->count() !== count(array_unique($data['posisiIds']))) {
                return ResponseHelper::error('Ada loker yang bukan milik program ini.', 422);
            }

            $aktif = (bool) $data['aktif'];

            DB::table('N_WEB_CAREERS_Program_Posisi')
                ->whereIn('Id_Program_Posisi', $sah)
                ->update([
                    'Flag_Aktif' => $aktif ? 'Y' : 'N',
                    // Jejaknya DIBERSIHKAN saat dinyalakan lagi. Meninggalkan
                    // tanggal nonaktif pada loker yang sudah hidup membuat
                    // laporan berikutnya membacanya sebagai masih mati.
                    'Nonaktif_At' => $aktif ? null : now(),
                    'Nonaktif_By_Id' => $aktif ? null : session('career_auth.id'),
                ]);

            Log::channel('web_career')->info(sprintf(
                'Pembukaan #%d: %d loker di-%s oleh %s',
                $realId, $sah->count(), $aktif ? 'aktifkan' : 'nonaktifkan',
                session('career_auth.nama', 'ADMIN')
            ));

            return ResponseHelper::success(
                ['jml' => $sah->count()],
                sprintf('%d loker %s.', $sah->count(), $aktif ? 'diaktifkan' : 'dinonaktifkan')
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ResponseHelper::error(collect($e->errors())->flatten()->first() ?? 'Data tidak valid', 422);
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal toggle loker pembukaan #{$id}: " . $e->getMessage());

            return ResponseHelper::error('Gagal mengubah status loker', 500);
        }
    }

    /**
     * GET .../pembukaan/{id}/analitik — angka satu terbitan.
     *
     * Perhitungannya di AnalitikPembukaan, termasuk catatan tentang apa yang
     * TIDAK bisa diukur dari basis data ini dan kenapa.
     */
    public function analitik(Request $request, $id)
    {
        try {
            $realId = Hashids::decode($id)[0] ?? null;
            if (! $realId) {
                return ResponseHelper::error('Data tidak ditemukan', 404);
            }

            return ResponseHelper::success(
                \App\Support\Career\AnalitikPembukaan::untuk((int) $realId, (int) $request->query('hari', 7)),
                'Analitik dimuat'
            );
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal memuat analitik pembukaan #{$id}: " . $e->getMessage());

            return ResponseHelper::error('Gagal memuat analitik', 500);
        }
    }

    /**
     * GET .../pembukaan/{id}/loker/{posisiId} — isi MPP satu loker.
     *
     * Panel detail loker menampilkan isi MPP-nya persis seperti di halaman
     * Master MPP. Dibaca lewat izin pembukaanPage, bukan masterMppPage —
     * alasannya ada di App\Support\Career\DetailMppLoker.
     */
    public function loker($id, $posisiId)
    {
        try {
            $realId = Hashids::decode($id)[0] ?? null;
            if (! $realId) {
                return ResponseHelper::error('Data tidak ditemukan', 404);
            }

            $pb = DB::table('N_WEB_CAREERS_Pembukaan')->where('Id_Pembukaan', $realId)->first();
            if (! $pb) {
                return ResponseHelper::error('Pembukaan tidak ditemukan', 404);
            }

            // Lokernya HARUS milik program terbitan ini. Tanpa pemeriksaan ini,
            // id posisi mana pun yang ditempelkan ke URL akan terbaca isinya —
            // termasuk milik program yang pengirimnya tidak berhak melihatnya.
            $posisi = DB::table('N_WEB_CAREERS_Program_Posisi')
                ->where('Program_Id', $pb->Program_Id)
                ->where('Id_Program_Posisi', (int) $posisiId)
                ->first();

            if (! $posisi) {
                return ResponseHelper::error('Loker bukan milik terbitan ini.', 404);
            }

            return ResponseHelper::success([
                'posisi' => [
                    'id' => (int) $posisi->Id_Program_Posisi,
                    'posisi' => $posisi->Posisi,
                    'departemen' => $posisi->Departemen,
                    'lokasi' => $posisi->Lokasi,
                    'level' => $posisi->Level ?? null,
                    'kuota' => (int) $posisi->Kuota,
                    'terisi' => (int) ($posisi->Terisi ?? 0),
                    'status' => $posisi->Status,
                    'mppRef' => $posisi->Mpp_Ref ?? null,
                    'aktif' => ($posisi->Flag_Aktif ?? 'Y') !== 'N',
                ],
                // Tautan publik loker ini — dirakit di SERVER dari host
                // permintaan, jadi benar di lokal, staging, dan produksi tanpa
                // domain yang ditulis di mana pun. Ini yang disalin tombol
                // "Salin tautan" untuk dibagikan ke LinkedIn.
                'tautan' => rtrim(request()->getSchemeAndHttpHost(), '/')
                    . '/karir/landing-page/lowongan/PB-' . $pb->Kode . '-' . $posisi->Id_Program_Posisi,
                'mpp' => \App\Support\Career\DetailMppLoker::untuk($posisi->Mpp_Ref ?? null),
                // Piramida seleksi + rincian hasil akhir LOKER INI. Dihitung
                // di sini, bukan ikut payload analitik terbitan: satu terbitan
                // bisa punya belasan loker, dan menghitung semuanya di muka
                // berarti belasan kueri untuk panel yang mungkin tidak dibuka.
                'corong' => \App\Support\Career\AnalitikPembukaan::corongLoker($realId, (int) $posisiId),
                // Ringkasan phone screening loker ini. Null bila lokernya
                // memang tidak punya sesi skrining — layar cukup memeriksa satu
                // kunci untuk tahu perlu menggambar kartunya atau tidak.
                'skrining' => \App\Support\Career\AnalitikSkrining::loker($realId, (int) $posisiId),
            ], 'Detail loker dimuat');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal memuat detail loker #{$posisiId}: " . $e->getMessage());

            return ResponseHelper::error('Gagal memuat detail loker', 500);
        }
    }

    public function destroy($id)
    {
        try {
            $realId = Hashids::decode($id)[0] ?? null;
            $row = DB::table('N_WEB_CAREERS_Pembukaan')->where('Id_Pembukaan', $realId)->first();
            if (! $row) {
                return ResponseHelper::error('Data tidak ditemukan', 404);
            }
            DB::transaction(function () use ($realId) {
                DB::table('N_WEB_CAREERS_Pembukaan_Kampus')->where('Pembukaan_Id', $realId)->delete();
                DB::table('N_WEB_CAREERS_Pembukaan')->where('Id_Pembukaan', $realId)->delete();
            });
            Log::channel('web_career')->info("Pembukaan #{$realId} dihapus");

            return ResponseHelper::success(null, 'Pembukaan dihapus');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal hapus pembukaan #{$id}: " . $e->getMessage());

            return ResponseHelper::error('Gagal menghapus data', 500);
        }
    }
}
