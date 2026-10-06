<?php

namespace App\Http\Controllers\Career\MasterMpp;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Support\Career\KursiMpp;
use App\Support\Career\PerpanjangSla;
use App\Support\Career\SlaMpp;
use App\Support\CareerShell;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

/**
 * WEB CAREER — MASTER MPP (buat & kelola transaksi Manpower Planning).
 *
 * Beda dengan master lain: HRIS_Transaksi_GForm BUKAN tabel modul Web Careers
 * (tanpa prefix N_WEB_CAREERS_) — PK komposit Kode_Perusahaan+No_Transaksi,
 * field organisasi (Divisi/Departemen/Level/Jabatan/Lokasi/Penanggung Jawab)
 * semua FK ke tabel HRIS bersama. Detail (N_WEB_CAREERS_Detail_MPP) adalah
 * ekstensi 1:1 milik modul ini.
 *
 * Riwayatnya sudah dirujuk sistem lain (Lamaran, Program_Posisi, Points/Skill/
 * Benefit MPP, dan CareerAdminController::options_mpp() dipakai ProgramKegiatan)
 * — karena itu TIDAK ADA HAPUS PERMANEN, hanya "Batalkan" (Status='Y', semantik
 * yang sudah ada di kolom itu sejak awal).
 *
 * Dulu ada dua halaman terpisah: /karir/monitoring-mpp (baca-saja, memantau)
 * dan /master-mpp (CRUD, mengarang data). Keduanya sudah DIGABUNG ke sini —
 * lihat docs/superpowers/specs/2026-08-06-master-mpp-merge-monitoring-design.md.
 * list()/detail()/opsiFilter() memuat pola query & pagination dari controller
 * lama itu (MppLowonganController, sudah dihapus).
 *
 * Tanggung Jawab/Persyaratan (Points_MPP) dan Skill/Benefit (Detail_Skill_MPP,
 * Detail_Benefit_MPP) BISA DIEDIT lewat store()/update() — lihat
 * docs/superpowers/specs/2026-08-06-master-mpp-jobdesc-design.md. Master
 * Skill/Benefit belum punya halaman kelola sendiri (dibangun terpisah di
 * branch lain); di sini opsinya dibaca DAN dibuat otomatis kalau admin
 * mengetik nama baru di tag-picker — lihat resolveTagIds().
 */
class MasterMppController extends Controller
{
    private const KODE_PERUSAHAAN = '001';

    private const TABEL_G = 'HRIS_Transaksi_GForm';

    private const TABEL_D = 'N_WEB_CAREERS_Detail_MPP';

    /** Halaman (Inertia). Data TIDAK dikirim lewat props — halaman fetch sendiri. */
    public function index()
    {
        return Inertia::render(
            'Career/admin/master-mpp/masterMpp',
            CareerShell::props('/master-mpp', 'Master MPP')
        );
    }

    /**
     * Kolom sort yang diizinkan (whitelist) → cegah sort ke kolom sembarang.
     *
     * `terbaru` = urutan MPP DIBUAT, bukan tanggal periodenya. Keduanya sering
     * dikira sama padahal tidak: MPP yang dibuat hari ini bisa saja periodenya
     * lebih awal daripada MPP bulan lalu, dan daftar yang diurutkan periode
     * membuat transaksi yang baru saja disimpan muncul entah di halaman berapa.
     */
    private const SORTABLE = ['terbaru', 'tanggal_periode', 'status'];

    /** Kolom select dasar (list() & detail()) — method, bukan const: butuh DB::raw() di runtime. */
    private function selectList(): array
    {
        // Snapshot SLA ikut dibaca bila kolomnya sudah ada. Tanpa ini, borang
        // Ubah memperlihatkan tenggat hasil hitungan HARI INI untuk MPP yang
        // dibuat berbulan-bulan lalu — angka yang tidak pernah tersimpan di mana
        // pun dan tidak sama dengan tenggat MPP itu sendiri.
        $sla = SlaMpp::siapSnapshot()
            ? [
                DB::raw('CONVERT(varchar(10), d.Sla_Mulai, 23) as sla_mulai'),
                DB::raw('CONVERT(varchar(10), d.Sla_Batas, 23) as sla_batas'),
                'd.Sla_Hari_Kerja as sla_hari',
            ]
            : [];

        // Penanda perpanjangan — dibaca dari kolom turunan di Detail_MPP, BUKAN
        // dengan menggabung tabel riwayat. Daftar ini menggambar puluhan kartu
        // sekaligus; join ke riwayat hanya demi angka "2x" pada segelintir baris
        // membuat seluruh halaman membayar ongkos yang cuma dipakai sebagian.
        $panjang = SlaMpp::siapPanjang()
            ? [
                'd.Sla_Perpanjangan_Ke as sla_panjang_ke',
                DB::raw('CONVERT(varchar(10), d.Sla_Batas_Awal, 23) as sla_batas_awal'),
            ]
            : [];

        $sla = array_merge($sla, $panjang);

        return array_merge($sla, [
            'g.No_Transaksi as no_transaksi',
            'g.Status as status_raw',
            'g.Flag_Selesai as flag_selesai_raw',
            'g.Flag_MT as flag_mt_raw',
            DB::raw('CONVERT(varchar(10), g.Tanggal_Periode, 23) as tanggal_periode'),
            // Tanggal MPP DIBUAT. Dipakai borang Ubah: pada program MT, inilah
            // periodenya — dan pratinjau harus menyebut tanggal yang sama dengan
            // yang kelak ditulis server, bukan sisa tanggal jenis sebelumnya.
            DB::raw('CONVERT(varchar(10), g.Tanggal, 23) as tanggal_dibuat'),
            'g.Id_Divisi as id_divisi',
            'dv.Keterangan as divisi',
            'g.Id_Sub_Divisi as id_sub_divisi',
            'sd.Keterangan as sub_divisi',
            'g.Id_Level as id_level',
            'lv.Keterangan as level',
            'g.Id_Jabatan as id_jabatan',
            'jb.Keterangan as jabatan',
            'g.Jumlah_Rekruitmen as jumlah_rekruitmen',
            'g.Kode_Lokasi as kode_lokasi',
            'lok.Nama_Lokasi as lokasi',
            'g.User_Penganggung_Jawab as kode_karyawan',
            'k.Nama as penanggung_jawab',
            'd.Id_Detail_MPP as id_detail_mpp',
            'd.Deskripsi as deskripsi',
            'd.Employment_Type as employment_type_id',
            'me.Nama_Employment as employment_type',
            'd.Workplace_Type as workplace_type_id',
            'mw.Nama_Workplace as workplace_type',
            'd.Experience_Level as experience_level_id',
            'mx.Nama_Experience_Level as experience_level',
            // JEJAK PEMBUATAN — siapa yang MENGINPUT, dan kapan.
            //
            // Beda dengan Penanggung Jawab, dan perbedaan itu justru sering jadi
            // pertanyaan: PJ adalah orang yang memegang lowongannya (kode
            // karyawan HRIS), sedangkan ini akun yang benar-benar mengetik
            // barisnya. Keduanya kerap bukan orang yang sama — admin rekrutmen
            // menginput MPP atas nama PJ di divisi lain.
            DB::raw("CONVERT(varchar(19), d.Created_At, 120) as dibuat_pada"),
            DB::raw("CONVERT(varchar(19), d.Updated_At, 120) as diubah_pada"),
            'ub.Nama as dibuat_oleh',
            'uu.Nama as diubah_oleh',
        ]);
    }

    /** Daftar MPP — join header+detail+master organisasi, search/filter/sort + PAGINATION di server. */
    public function list(Request $request)
    {
        try {
            $page = max(1, (int) $request->query('page', 1));
            $perPage = min(50, max(1, (int) $request->query('per_page', 12)));
            $sort = (string) $request->query('sort', 'terbaru');
            $dir = strtolower((string) $request->query('dir', 'desc')) === 'asc' ? 'asc' : 'desc';
            if (!in_array($sort, self::SORTABLE, true)) {
                $sort = 'terbaru';
            }

            $result = DB::transaction(function () use ($request, $page, $perPage, $sort, $dir) {
                $total = $this->applyFilters($this->baseQuery(), $request)->count();

                $data = $this->applyFilters($this->baseQuery(), $request)->select($this->selectList());
                if ($sort === 'status') {
                    $data->orderByRaw("CASE WHEN g.Status = 'Y' THEN 1 ELSE 0 END " . $dir);
                } elseif ($sort === 'terbaru') {
                    // Id_Detail_MPP itu IDENTITY: naik terus, tidak pernah dipakai ulang.
                    // No_Transaksi tidak bisa dipakai — "MPP-2026-99" mengurut SESUDAH
                    // "MPP-2026-112" kalau dibandingkan sebagai teks.
                    $data->orderBy('d.Id_Detail_MPP', $dir);
                    $urutUtama = true;
                } else {
                    $data->orderBy('g.Tanggal_Periode', $dir);
                }

                // Tie-breaker → urutan & pagination stabil: dua MPP berperiode sama
                // tidak boleh bertukar tempat antar halaman.
                //
                // TIDAK dipasang saat urutan utamanya sudah kolom ini juga — SQL Server
                // menolak kolom yang muncul dua kali di ORDER BY ("A column has been
                // specified more than once in the order by list").
                if (empty($urutUtama)) {
                    $data->orderBy('d.Id_Detail_MPP', 'desc');
                }

                $rows = $data->offset(($page - 1) * $perPage)->limit($perPage)->get();

                return ['total' => $total, 'rows' => $rows];
            });

            $rows = collect($result['rows'])->map(fn ($r) => $this->baris($r))->values();

            // KEADAAN KURSI ditempel SEKALIGUS untuk seluruh halaman — satu kueri,
            // bukan satu per kartu. Panel split menampilkan bilah keterisian di
            // setiap baris daftar; menanyakannya per baris membuat halaman yang
            // seluruh gunanya "lihat semuanya" justru paling lambat saat paling
            // dipakai. Sepola KursiMpp::keadaanBanyak() di papan worklist.
            $rows = $this->tempelKursi($rows);

            return ResponseHelper::successWithPagination($rows, $page, $perPage, (int) $result['total'], 'Data MPP dimuat');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('Gagal memuat master MPP: ' . $e->getMessage());

            return ResponseHelper::error('Gagal memuat data MPP', 500);
        }
    }

    /**
     * Detail satu MPP — dipakai panel off-canvas (baca) DAN prefill form Ubah.
     * Termasuk konten job description (Points/Skill/Benefit) baca-saja — dipindah
     * dari Monitoring MPP, tidak ada CRUD baru untuk sub-tabel itu di sini.
     */
    public function detail(string $no)
    {
        try {
            if (!preg_match('#^[A-Za-z0-9\-/]{1,50}$#', $no)) {
                return ResponseHelper::error('No transaksi tidak valid', 422);
            }

            $data = DB::transaction(function () use ($no) {
                $head = $this->baseQuery()->where('g.No_Transaksi', $no)->select($this->selectList())->first();
                if (!$head) {
                    return null;
                }
                $id = $head->id_detail_mpp;

                $points = DB::table('N_WEB_CAREERS_Points_MPP')
                    ->where('Id_Detail_MPP', $id)
                    ->orderBy('Urutan')->orderBy('Id_Points_MPP')
                    ->get(['Section', 'Content']);

                $skill = DB::table('N_WEB_CAREERS_Detail_Skill_MPP as sk')
                    ->join('N_WEB_CAREERS_Master_Skill as ms', 'ms.Id_Skill', '=', 'sk.Id_Skill')
                    ->leftJoin('N_WEB_CAREERS_Master_Skill_Kategori as msk', 'msk.Id_Master_Skill_Kategori', '=', 'ms.Id_Master_Skill_Kategori')
                    ->where('sk.Id_Detail_MPP', $id)
                    ->orderByRaw('ISNULL(msk.Urutan, 999999)')->orderBy('ms.Nama_Skill')
                    ->get(['ms.Id_Skill as id', 'ms.Nama_Skill as nama', 'msk.Id_Master_Skill_Kategori as kat_id', 'msk.Nama as kat_nama', 'msk.Ikon as kat_ikon', 'msk.Warna as kat_warna']);

                $benefit = DB::table('N_WEB_CAREERS_Detail_Benefit_MPP as bn')
                    ->join('N_WEB_CAREERS_Master_Benefit as mb', 'mb.Id_Benefit', '=', 'bn.Id_Benefit')
                    ->where('bn.Id_Detail_MPP', $id)
                    ->orderBy('mb.Nama_Benefit')
                    ->get(['mb.Id_Benefit as id', 'mb.Nama_Benefit as nama']);

                return array_merge($this->baris($head), [
                    // Keadaan perpanjangan: dipakai panel detail untuk memutuskan
                    // tombolnya muncul/terkunci, DAN untuk menggambar riwayatnya.
                    // Sumbernya sama persis dengan yang dipakai pintu simpan —
                    // lihat PerpanjangSla::keadaan().
                    'perpanjangan' => PerpanjangSla::keadaan($no),
                    // Disegarkan, bukan sekadar dibaca: membuka/menonaktifkan
                    // program tidak melewati jalur yang menulis ledger, jadi
                    // angka `Dialokasikan` bisa tertinggal. Lihat KursiMpp.
                    'kursi' => KursiMpp::segarkanBanyak([$no])[$no] ?? KursiMpp::keadaan($no),
                    'tanggungJawab' => $points->where('Section', 'responsibility')->pluck('Content')->values()->all(),
                    'persyaratan' => $points->where('Section', 'requirement')->pluck('Content')->values()->all(),
                    'skill' => $skill->map(fn ($r) => $this->barisSkill($r))->values()->all(),
                    'benefit' => $benefit->map(fn ($r) => ['id' => (int) $r->id, 'nama' => $r->nama])->values()->all(),
                ]);
            });

            if (!$data) {
                return ResponseHelper::error('Transaksi MPP tidak ditemukan', 404);
            }

            return ResponseHelper::success($data, 'Detail MPP dimuat');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal memuat detail master MPP {$no}: " . $e->getMessage());

            return ResponseHelper::error('Gagal memuat detail MPP', 500);
        }
    }

    /** Opsi periode (dari data yang ada) + statistik agregat — dipakai toolbar & stat cards. */
    public function opsiFilter()
    {
        try {
            $result = DB::transaction(function () {
                $periode = collect(
                    $this->baseQuery()
                        ->whereNotNull('g.Tanggal_Periode')
                        ->select(DB::raw('LEFT(CONVERT(varchar(10), g.Tanggal_Periode, 23), 7) as periode'))
                        ->distinct()
                        ->orderByDesc('periode')
                        ->get()
                )->pluck('periode')->values();

                $s = $this->baseQuery()
                    ->selectRaw('COUNT(*) as total')
                    ->selectRaw("SUM(CASE WHEN ISNULL(g.Status, '') <> 'Y' THEN 1 ELSE 0 END) as aktif")
                    ->selectRaw("SUM(CASE WHEN g.Flag_Selesai = 'Y' THEN 1 ELSE 0 END) as selesai")
                    ->selectRaw("SUM(CASE WHEN g.Status = 'Y' THEN 1 ELSE 0 END) as dibatalkan")
                    ->first();

                // ── PIC: opsi penyaring bertumpuk di panel split ──────────────
                //
                // Dibaca dari MPP yang ADA, bukan dari seluruh master karyawan:
                // daftar ratusan nama yang 99%-nya tidak pernah memegang MPP
                // membuat penyaringnya lebih lambat dipakai daripada tidak ada.
                $pic = collect(
                    $this->baseQuery()
                        ->whereNotNull('g.User_Penganggung_Jawab')
                        ->selectRaw('g.User_Penganggung_Jawab as kode, MAX(k.Nama) as nama')
                        ->groupBy('g.User_Penganggung_Jawab')
                        ->get()
                )->map(fn ($r) => ['value' => $r->kode, 'label' => $r->nama ?: $r->kode])
                    ->sortBy('label', SORT_NATURAL | SORT_FLAG_CASE)
                    ->values();

                // ── RINGKASAN KURSI & SLA untuk bilah atas ────────────────────
                //
                // Dihitung LINTAS SELURUH MPP, bukan dari halaman yang sedang
                // terlihat: bilah yang menyebut "3 lewat SLA" padahal maksudnya
                // "3 di halaman ini" adalah angka yang menyesatkan justru pada
                // hal yang paling dipakai untuk mengambil tindakan.
                $ring = $this->ringkasKursiSla();

                return [
                    'periode' => $periode->all(),
                    'pic' => $pic->all(),
                    'ringkas' => $ring,
                    'stats' => [
                        'total' => (int) ($s->total ?? 0),
                        'aktif' => (int) ($s->aktif ?? 0),
                        'selesai' => (int) ($s->selesai ?? 0),
                        'dibatalkan' => (int) ($s->dibatalkan ?? 0),
                    ],
                ];
            });

            return ResponseHelper::success($result, 'Opsi filter dimuat');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('Gagal memuat opsi filter master MPP: ' . $e->getMessage());

            return ResponseHelper::error('Gagal memuat opsi filter', 500);
        }
    }

    /**
     * Ringkasan kursi & SLA untuk bilah atas — kuota, terisi, sisa, lewat SLA.
     *
     * "Lewat SLA" dihitung DI SQL terhadap tanggal hari ini, bukan di PHP atas
     * seluruh baris: jumlah MPP boleh tumbuh, dan yang dibutuhkan bilah cuma
     * empat angka. Hanya MPP yang MASIH BERJALAN yang dihitung — tenggat lewat
     * pada MPP yang sudah selesai atau dibatalkan bukan kabar, cuma bunyi.
     *
     * @return array{kuota:int, terisi:int, sisa:int, lewat:int}
     */
    private function ringkasKursiSla(): array
    {
        $kosong = ['kuota' => 0, 'terisi' => 0, 'sisa' => 0, 'lewat' => 0];

        try {
            $q = $this->baseQuery()
                ->whereRaw("ISNULL(g.Status, '') <> 'Y'")
                ->where('g.Flag_Selesai', '<>', 'Y');

            $kuota = (int) (clone $q)->sum('g.Jumlah_Rekruitmen');

            $lewat = SlaMpp::siapSnapshot()
                ? (int) (clone $q)->whereNotNull('d.Sla_Batas')->whereRaw('d.Sla_Batas < CAST(GETDATE() AS DATE)')->count()
                : 0;

            // Terisi dibaca dari ledger kursi, sumber yang sama dengan kartu —
            // bukan dihitung ulang dengan aturan sendiri di sini.
            $terisi = KursiMpp::siap()
                ? (int) DB::table('N_WEB_CAREERS_Mpp_Kursi')
                    ->whereIn('No_Transaksi_MPP', (clone $q)->pluck('g.No_Transaksi'))
                    ->sum('Terisi')
                : 0;

            return [
                'kuota' => $kuota,
                'terisi' => $terisi,
                'sisa' => max(0, $kuota - $terisi),
                'lewat' => $lewat,
            ];
        } catch (\Throwable $e) {
            Log::channel('web_career')->warning('Ringkasan kursi/SLA MPP gagal: '.$e->getMessage());

            return $kosong;
        }
    }

    /** Bentuk satu baris MPP (dipakai list() & detail()) — id mentah + label, siap prefill form Ubah. */
    private function baris(object $r): array
    {
        return [
            'noTransaksi' => $r->no_transaksi,
            'status' => $r->status_raw === 'Y' ? 'DIBATALKAN' : 'AKTIF',
            'selesai' => $r->flag_selesai_raw === 'Y',
            // Flag_MT: 'Y' = Management Trainee, 'T' atau NULL = Rekrutmen biasa.
            'jenisProgram' => $r->flag_mt_raw === 'Y' ? 'MT' : 'REKRUTMEN',
            'tanggalPeriode' => $r->tanggal_periode,
            'tanggalDibuat' => $r->tanggal_dibuat ?? null,
            'divisi' => ['id' => $r->id_divisi, 'nama' => $r->divisi],
            'subDivisi' => $r->id_sub_divisi ? ['id' => $r->id_sub_divisi, 'nama' => $r->sub_divisi] : null,
            'level' => ['id' => $r->id_level, 'nama' => $r->level],
            'jabatan' => ['id' => $r->id_jabatan, 'nama' => $r->jabatan],
            'jumlahRekrutmen' => (int) $r->jumlah_rekruitmen,
            'lokasi' => ['kode' => $r->kode_lokasi, 'nama' => $r->lokasi],
            'penanggungJawab' => ['kode' => $r->kode_karyawan, 'nama' => $r->penanggung_jawab ?: $r->kode_karyawan],
            'deskripsi' => $r->deskripsi,
            'employmentType' => $r->employment_type_id ? ['id' => $r->employment_type_id, 'nama' => $r->employment_type] : null,
            'workplaceType' => $r->workplace_type_id ? ['id' => $r->workplace_type_id, 'nama' => $r->workplace_type] : null,
            'experienceLevel' => $r->experience_level_id ? ['id' => $r->experience_level_id, 'nama' => $r->experience_level] : null,
            // Ketentuan yang DIBEKUKAN pada MPP ini — null bila MPP-nya lahir
            // sebelum fitur SLA ada, dan borangnya memang harus tahu itu.
            // Jejak input — dipakai panel detail. Dibedakan dari penanggungJawab.
            'dibuat' => ['pada' => $r->dibuat_pada ?? null, 'oleh' => $r->dibuat_oleh ?? null],
            'diubah' => ['pada' => $r->diubah_pada ?? null, 'oleh' => $r->diubah_oleh ?? null],
            'sla' => isset($r->sla_hari) && $r->sla_hari
                ? [
                    'mulai' => $r->sla_mulai,
                    'hari' => (int) $r->sla_hari,
                    // Tenggat yang BERLAKU — sudah termasuk perpanjangan bila ada.
                    'batas' => $r->sla_batas ?? null,
                    // Tenggat ASLI. Dikirim terpisah supaya layar bisa menulis
                    // "seharusnya 02 Okt, diperpanjang jadi 13 Nov" — kalimat yang
                    // tidak bisa disusun kalau yang sampai ke layar cuma satu tanggal.
                    'batasAwal' => $r->sla_batas_awal ?? ($r->sla_batas ?? null),
                    'perpanjanganKe' => (int) ($r->sla_panjang_ke ?? 0),
                    // Sisa hari KERJA + nada peringatannya, dihitung di satu
                    // tempat (SlaMpp::keadaan) supaya kartu MPP, worklist, dan
                    // dashboard tidak pernah menyebut angka yang berbeda untuk
                    // MPP yang sama.
                    'keadaan' => \App\Support\Career\SlaMpp::keadaan($r->sla_batas ?? null),
                ]
                : null,
        ];
    }

    /**
     * Bentuk satu baris skill (dipakai opsiSkill() & detail()) — sertakan
     * kategori (Master_Skill_Kategori: ikon+warna) kalau ada. $opsiShape=true
     * memakai bentuk {value,label,...} yang dipahami el-option; false memakai
     * {id,nama,...} yang dipahami frontend untuk chip baca-saja/prefill form.
     */
    private function barisSkill(object $r, bool $opsiShape = false): array
    {
        $kategori = $r->kat_id ? [
            'id' => (int) $r->kat_id,
            'nama' => $r->kat_nama,
            'ikon' => $r->kat_ikon,
            'warna' => $r->kat_warna,
        ] : null;

        return $opsiShape
            ? ['value' => (int) $r->id, 'label' => $r->nama, 'kategori' => $kategori]
            : ['id' => (int) $r->id, 'nama' => $r->nama, 'kategori' => $kategori];
    }

    /**
     * Terjemahkan jenisProgram (bahasa form) → Flag_MT (bahasa kolom HRIS).
     *
     * Non-MT ditulis 'T', BUKAN NULL: 'T' adalah DEFAULT kolomnya di
     * HRIS_Transaksi_GForm, jadi baris buatan Web Careers jadi seragam dengan
     * baris buatan HRIS. Dulu kolom ini diisi NULL — aman untuk pembacaan di
     * repo ini (semua filter memakai ISNULL), tapi query HRIS di luar aplikasi
     * yang mencari `Flag_MT = 'T'` tidak akan melihat baris tersebut.
     *
     * Sisi BACA tetap memperlakukan NULL sebagai non-MT — lihat baris() dan
     * filter jenis di applyFilters() — supaya baris NULL lama tetap terbaca benar.
     */
    private function flagMt(string $jenisProgram): string
    {
        return $jenisProgram === 'MT' ? 'Y' : 'T';
    }

    /** Buat transaksi MPP baru (header + detail, satu transaksi DB). */
    public function store(Request $request)
    {
        try {
            $data = $this->validasi($request);

            // GERBANG SLA — tanggal target tidak boleh melewati ketentuan levelnya.
            //
            // Ditegakkan DI SINI, bukan cukup dengan mengunci kalender di layar:
            // layar bisa basi (aturannya baru saja diubah di tab lain) dan pintu
            // ini tetap bisa diketuk langsung tanpa lewat layar sama sekali.
            if ($galat = $this->galatSla($data, null, true)) {
                return ResponseHelper::error($galat, 422);
            }

            // DIAGNOSTIK SEMENTARA (fix/mpp-mt) — lacak Flag_MT dari request s/d insert.
            Log::channel('web_career')->info('[DIAG-MT store] mentah=' . var_export($request->input('jenisProgram'), true)
                . ' tervalidasi=' . var_export($data['jenisProgram'], true)
                . ' akanDitulis=' . var_export($this->flagMt($data['jenisProgram']), true));

            $userId = session('career_auth.id');
            $noTransaksi = DB::transaction(function () use ($data, $userId) {
                $no = $this->nomorBaru();

                DB::table(self::TABEL_G)->insert([
                    'Kode_Perusahaan' => self::KODE_PERUSAHAAN,
                    'No_Transaksi' => $no,
                    'Status' => null,
                    'Tanggal' => now(),
                    'Jam' => now()->format('H:i:s'),
                    'Id_Divisi' => $data['idDivisi'],
                    'Id_Sub_Divisi' => $data['idSubDivisi'],
                    'Id_Level' => $data['idLevel'],
                    'Jumlah_Rekruitmen' => $data['jumlahRekrutmen'],
                    'Id_Jabatan' => $data['idJabatan'],
                    'Flag_Selesai' => 'T',
                    // REKRUTMEN: hasil hitungan SLA. MT: tanggal hari ini.
                    'Tanggal_Periode' => $this->periodeMpp($data),
                    'User_Penganggung_Jawab' => $data['kodeKaryawan'],
                    'Kode_Lokasi' => $data['kodeLokasi'],
                    'Flag_MT' => $this->flagMt($data['jenisProgram']),
                ]);

                $idDetail = DB::table(self::TABEL_D)->insertGetId([
                    'No_Transaksi_MPP' => $no,
                    'Deskripsi' => $data['deskripsi'],
                    'Employment_Type' => $data['employmentType'],
                    'Workplace_Type' => $data['workplaceType'],
                    'Experience_Level' => $data['experienceLevel'],
                    'Created_At' => now(),
                    'Created_By' => $userId,
                ]
                // SNAPSHOT SLA — lihat snapshotSla(). Inilah yang membuat perubahan
                // master tahun depan tidak menulis ulang penilaian tahun ini.
                + $this->snapshotSla(
                    $data['idLevel'],
                    now()->toDateString(),
                    $data['jenisProgram'] === 'MT'
                ), 'Id_Detail_MPP');

                $this->simpanPoints($idDetail, $data['tanggungJawab'], $data['persyaratan']);
                $this->simpanRelasi($idDetail, 'N_WEB_CAREERS_Detail_Skill_MPP', 'Id_Skill', $this->resolveTagIds($data['skills'], 'N_WEB_CAREERS_Master_Skill', 'Id_Skill', 'Nama_Skill', $userId), $userId);
                $this->simpanRelasi($idDetail, 'N_WEB_CAREERS_Detail_Benefit_MPP', 'Id_Benefit', $this->resolveTagIds($data['benefits'], 'N_WEB_CAREERS_Master_Benefit', 'Id_Benefit', 'Nama_Benefit', $userId), $userId);

                return $no;
            });

            Log::channel('web_career')->info("Master MPP dibuat ({$noTransaksi}) oleh " . session('career_auth.nama', 'ADMIN'));

            return ResponseHelper::success(['noTransaksi' => $noTransaksi], 'Transaksi MPP berhasil dibuat', 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ResponseHelper::error(collect($e->errors())->flatten()->first() ?? 'Data tidak valid', 422);
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('Gagal membuat master MPP: ' . $e->getMessage());

            return ResponseHelper::error('Gagal menyimpan transaksi MPP', 500);
        }
    }

    /** Ubah transaksi MPP (header + detail). No_Transaksi tidak berubah. */
    public function update(Request $request, string $no)
    {
        try {
            $row = DB::table(self::TABEL_G)->where('No_Transaksi', $no)->first();
            if (!$row) {
                return ResponseHelper::error('Transaksi MPP tidak ditemukan', 404);
            }

            $data = $this->validasi($request);

            // Gerbang yang sama dengan store(). Dihitung dari tanggal MULAI yang
            // sudah beku di barisnya, bukan dari hari ini: kalau tidak, menyunting
            // deskripsi sebuah MPP lama akan memperpanjang tenggatnya sendiri.
            $bekuLama = $this->slaTersimpan($no);

            // Dihitung SEKALI di sini, lalu dipakai tiga kali di bawah: gerbang
            // tanggal, Tanggal_Periode, dan Sla_Batas. Ketiganya harus menjawab
            // dari penanda yang sama — kalau salah satunya menyimpulkan sendiri,
            // ia akan berbeda pendapat dengan dua yang lain pada MPP yang sama.
            $diperpanjang = $this->pernahDiperpanjang($no);

            if ($galat = $this->galatSla($data, $bekuLama->mulai ?? null, false, $diperpanjang)) {
                return ResponseHelper::error($galat, 422);
            }

            // DIAGNOSTIK SEMENTARA (fix/mpp-mt) — lacak Flag_MT dari request s/d update.
            Log::channel('web_career')->info("[DIAG-MT update {$no}] sebelum=" . var_export($row->Flag_MT, true)
                . ' mentah=' . var_export($request->input('jenisProgram'), true)
                . ' tervalidasi=' . var_export($data['jenisProgram'], true)
                . ' akanDitulis=' . var_export($this->flagMt($data['jenisProgram']), true));

            $userId = session('career_auth.id');
            $idDetail = (int) DB::table(self::TABEL_D)->where('No_Transaksi_MPP', $no)->value('Id_Detail_MPP');

            $mtBaru = $data['jenisProgram'] === 'MT';
            $mtLama = $row->Flag_MT === 'Y';

            // MT: periodenya tanggal baris ini DIBUAT, bukan hari penyuntingan.
            $periode = $this->periodeMpp($data, $row->Tanggal ?? null);

            // Alasan yang sama dengan Sla_Batas di bawah: pada MPP yang sudah
            // diperpanjang, Tanggal_Periode-nya sudah mengikuti tenggat hasil
            // perpanjangan. Borang Ubah mengirim tanggal hasil hitungan polos —
            // dan menerimanya berarti menarik mundur tenggat yang sudah
            // disetujui, lewat layar yang tidak pernah menyebut kata
            // "perpanjangan" sama sekali.
            if ($diperpanjang) {
                $periode = substr((string) ($row->Tanggal_Periode ?? $periode), 0, 10);
            }

            // SNAPSHOT DIBEKUKAN ULANG BILA LEVELNYA BERGANTI — ATAU BILA
            // JENIS PROGRAMNYA BERGANTI.
            //
            // Yang kedua sama pentingnya: rekrutmen yang diubah menjadi MT harus
            // kehilangan angka SLA-nya, dan MT yang dikembalikan menjadi rekrutmen
            // harus mendapatkannya. Tanpa syarat itu, satu-satunya yang berubah
            // adalah labelnya, sementara tenggat lamanya diam-diam masih menempel.
            $levelSama = (int) ($bekuLama->level ?? 0) === (int) $data['idLevel'];
            $kolomSla = ($levelSama && $mtBaru === $mtLama)
                ? []
                : $this->snapshotSla(
                    $data['idLevel'],
                    $bekuLama->mulai ?? now()->toDateString(),
                    $mtBaru
                );

            // ══ TENGGAT YANG SUDAH DIPERPANJANG TIDAK BOLEH DIHITUNG ULANG ═══
            //
            // Di atas, berganti level membekukan ulang seluruh kolom Sla_* —
            // termasuk Sla_Batas. Pada MPP yang PERNAH DIPERPANJANG, itu diam-
            // diam mengembalikan tenggatnya ke hasil hitungan polos dan
            // MENGHAPUS perpanjangan yang sudah disetujui, sementara baris
            // riwayatnya tetap ada. Yang tersisa: riwayat yang menjanjikan
            // tenggat 28 Des, dan kolom yang menyebut 23 Okt — dan tidak ada
            // yang tahu mana yang berlaku.
            //
            // Tenggat HANYA boleh bergeser lewat pintu perpanjangan, yang
            // menuntut alasan tertulis. Menyunting level MPP bukan pintu itu.
            //
            // Yang lain tetap dibekukan ulang: angka hari & master-nya memang
            // harus mengikuti level yang baru, sebab itulah yang dinilai
            // laporan. Yang ditahan cuma TANGGAL BATASnya.
            if ($kolomSla && $diperpanjang) {
                unset($kolomSla['Sla_Batas']);
            }

            DB::transaction(function () use ($data, $no, $userId, $idDetail, $periode, $kolomSla) {
                DB::table(self::TABEL_G)->where('No_Transaksi', $no)->update([
                    'Id_Divisi' => $data['idDivisi'],
                    'Id_Sub_Divisi' => $data['idSubDivisi'],
                    'Id_Level' => $data['idLevel'],
                    'Jumlah_Rekruitmen' => $data['jumlahRekrutmen'],
                    'Id_Jabatan' => $data['idJabatan'],
                    'Tanggal_Periode' => $periode,
                    'User_Penganggung_Jawab' => $data['kodeKaryawan'],
                    'Kode_Lokasi' => $data['kodeLokasi'],
                    'Flag_MT' => $this->flagMt($data['jenisProgram']),
                ]);

                DB::table(self::TABEL_D)->where('No_Transaksi_MPP', $no)->update([
                    'Deskripsi' => $data['deskripsi'],
                    'Employment_Type' => $data['employmentType'],
                    'Workplace_Type' => $data['workplaceType'],
                    'Experience_Level' => $data['experienceLevel'],
                    'Updated_At' => now(),
                    'Updated_By' => $userId,
                ]
                // Selama level DAN jenis programnya sama, angkanya dibiarkan apa
                // adanya — termasuk bila master sudah diubah sejak MPP ini dibuat.
                // Menyunting satu kata di deskripsi tidak boleh diam-diam
                // memindahkan tenggat. Lihat $kolomSla di atas.
                + $kolomSla);

                $this->simpanPoints($idDetail, $data['tanggungJawab'], $data['persyaratan']);
                $this->simpanRelasi($idDetail, 'N_WEB_CAREERS_Detail_Skill_MPP', 'Id_Skill', $this->resolveTagIds($data['skills'], 'N_WEB_CAREERS_Master_Skill', 'Id_Skill', 'Nama_Skill', $userId), $userId);
                $this->simpanRelasi($idDetail, 'N_WEB_CAREERS_Detail_Benefit_MPP', 'Id_Benefit', $this->resolveTagIds($data['benefits'], 'N_WEB_CAREERS_Master_Benefit', 'Id_Benefit', 'Nama_Benefit', $userId), $userId);
            });

            Log::channel('web_career')->info("Master MPP {$no} diperbarui");

            return ResponseHelper::success(null, 'Transaksi MPP diperbarui');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ResponseHelper::error(collect($e->errors())->flatten()->first() ?? 'Data tidak valid', 422);
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal update master MPP {$no}: " . $e->getMessage());

            return ResponseHelper::error('Gagal memperbarui transaksi MPP', 500);
        }
    }

    /**
     * Batalkan / aktifkan kembali transaksi MPP (Status).
     * TIDAK ADA hapus permanen — lihat catatan kelas. Membatalkan membuat MPP
     * ini berhenti muncul sebagai opsi di ProgramKegiatan (options_mpp), tapi
     * riwayat lamaran/program yang sudah menempel tetap utuh.
     */
    public function batalkan(Request $request, string $no)
    {
        try {
            $batalkan = $request->boolean('batalkan');

            $terpengaruh = DB::table(self::TABEL_G)->where('No_Transaksi', $no)->update([
                'Status' => $batalkan ? 'Y' : null,
            ]);

            if (!$terpengaruh) {
                return ResponseHelper::error('Transaksi MPP tidak ditemukan', 404);
            }

            Log::channel('web_career')->info("Master MPP {$no} " . ($batalkan ? 'dibatalkan' : 'diaktifkan kembali'));

            return ResponseHelper::success(null, $batalkan ? 'Transaksi dibatalkan' : 'Transaksi diaktifkan kembali');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal mengubah status batal MPP {$no}: " . $e->getMessage());

            return ResponseHelper::error('Gagal mengubah status transaksi', 500);
        }
    }

    /** Tandai selesai / belum selesai (Flag_Selesai) — reversibel, risiko rendah. */
    public function selesai(Request $request, string $no)
    {
        try {
            $selesai = $request->boolean('selesai');

            $terpengaruh = DB::table(self::TABEL_G)->where('No_Transaksi', $no)->update([
                'Flag_Selesai' => $selesai ? 'Y' : 'T',
            ]);

            if (!$terpengaruh) {
                return ResponseHelper::error('Transaksi MPP tidak ditemukan', 404);
            }

            Log::channel('web_career')->info("Master MPP {$no} flag selesai = " . ($selesai ? 'Y' : 'T'));

            return ResponseHelper::success(null, $selesai ? 'Ditandai selesai' : 'Ditandai belum selesai');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal mengubah flag selesai MPP {$no}: " . $e->getMessage());

            return ResponseHelper::error('Gagal mengubah status selesai', 500);
        }
    }

    /**
     * PERPANJANG SLA sebuah MPP — tenggatnya digeser, alasannya dicatat.
     *
     * ── KENAPA ENDPOINT SENDIRI, BUKAN LEWAT update() ───────────────────────
     *
     * update() menyimpan ISI MPP: deskripsi, skill, kuota. Perpanjangan tidak
     * mengubah satu pun dari itu — yang bergerak cuma tenggatnya, dan ia
     * bergerak menurut aturan yang tidak boleh bisa disetir borang (panjangnya
     * dari snapshot, titik mulainya dari tenggat lama).
     *
     * Menggabungkannya ke update() berarti "tanggal periode" jadi bidang yang
     * bisa dikirim bebas lagi — persis penyuntingan diam-diam yang fitur ini ada
     * untuk menggantikan.
     *
     * ── YANG DIPUTUSKAN DI SINI: TIDAK ADA ──────────────────────────────────
     *
     * Seluruh syarat, hitungan, dan penulisannya milik PerpanjangSla. Controller
     * ini hanya memeriksa bentuk permintaannya, memanggilnya, lalu mencatat.
     * Alasannya sama dengan gerbang SLA: jawaban yang sama dibutuhkan layar
     * (untuk memutuskan tombolnya hidup) dan pintu ini — dua tempat yang
     * menjawab sendiri-sendiri cepat atau lambat berbeda.
     */
    public function perpanjang(Request $request, string $no)
    {
        if (! SlaMpp::siapPanjang()) {
            return ResponseHelper::error(
                'Fitur perpanjangan SLA belum dipasang. Jalankan docs/02-09-2026/01-memperpanjangsql.sql lebih dulu.',
                503,
            );
        }

        try {
            $data = $request->validate([
                'alasan' => 'required|string|min:'.PerpanjangSla::MIN_ALASAN.'|max:'.PerpanjangSla::MAKS_ALASAN,
            ], [
                'alasan.required' => 'Alasan perpanjangan wajib diisi.',
                'alasan.min' => 'Alasan terlalu pendek (minimal '.PerpanjangSla::MIN_ALASAN.' huruf) — tulis sebabnya, '
                    .'ini satu-satunya keterangan yang bisa dibaca kembali saat MPP ini ditinjau.',
                'alasan.max' => 'Alasan terlalu panjang (maksimal '.PerpanjangSla::MAKS_ALASAN.' huruf).',
            ]);

            $nama = session('career_auth.nama', 'ADMIN');

            $hasil = PerpanjangSla::simpan($no, $data['alasan'], $nama, session('career_auth.id'));

            if ($hasil['galat']) {
                return ResponseHelper::error($hasil['galat'], 422);
            }

            $r = $hasil['hasil'];

            // ALASANNYA IKUT DICATAT KE LOG, bukan cuma ke tabelnya.
            //
            // Bukan penggandaan yang sia-sia: log inilah yang dibaca saat yang
            // dipertanyakan bukan MPP-nya melainkan ORANGNYA ("siapa yang
            // memperpanjang apa bulan lalu"), dan pertanyaan itu tidak punya
            // baris untuk ditelusuri di tabel yang tersusun per MPP.
            Log::channel('web_career')->info(sprintf(
                '[SLA MPP] %s diperpanjang ke-%d: %s → %s (+%d hari kerja), oleh %s. Alasan: %s',
                $no, $r['ke'], $r['batasLama'], $r['batasBaru'], $r['hari'], $nama, $data['alasan'],
            ));

            return ResponseHelper::success($r, sprintf(
                'SLA diperpanjang %d hari kerja — tenggat baru %s.',
                $r['hari'], $r['batasBaru'],
            ));
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ResponseHelper::error(collect($e->errors())->flatten()->first() ?? 'Data tidak valid', 422);
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal memperpanjang SLA MPP {$no}: ".$e->getMessage());

            return ResponseHelper::error('Gagal memperpanjang SLA', 500);
        }
    }

    /**
     * KANDIDAT YANG SUDAH DITERIMA pada sebuah MPP.
     *
     * ── KENAPA DIBACA DARI LAMARAN, BUKAN DARI ANGKA `Terisi` ───────────────
     *
     * Ledger kursi (KursiMpp) menyimpan JUMLAHnya, dan itu cukup untuk gerbang
     * keputusan. Yang ditanyakan layar ini berbeda: SIAPA saja orangnya. Angka
     * tidak bisa dipecah kembali menjadi nama, jadi daftarnya harus dibaca dari
     * sumber yang sama dengan yang menghasilkan angka itu — kalau tidak, kartu
     * bisa menyebut "3 terisi" sementara daftarnya memuat empat baris, dan tidak
     * ada yang tahu mana yang benar.
     *
     * Karena itu penyaringnya PERSIS sama dengan KursiMpp::hitungDariSumber():
     * status yang memotong kuota, lewat Program_Posisi.Mpp_Ref — termasuk
     * program yang sudah dinonaktifkan. Orang yang sudah diterima tidak berhenti
     * diterima hanya karena programnya ditutup.
     */
    /**
     * GET /api/v1/karir/master-mpp/{no}/kandidat/{id} — PROFIL KANDIDAT, BACA SAJA.
     *
     * ── KENAPA ADA DI SINI, BUKAN MEMAKAI ENDPOINT WORKLIST ───────────────
     *
     * Worklist punya endpoint serupa (LamaranController::worklistBerkas), tapi
     * ia berpagar izin `pelamarPage`. Orang yang memegang halaman MPP belum
     * tentu memegang worklist — dan memaksa mereka lewat sana berarti membuka
     * seluruh papan pelamar hanya untuk melihat satu profil.
     *
     * ── PENJAGAAN KEPEMILIKAN ─────────────────────────────────────────────
     *
     * Nomor MPP-nya ikut diperiksa, bukan cuma id lamarannya. Tanpa itu siapa
     * pun yang boleh membuka SATU MPP bisa menebak id lamaran lain dan membaca
     * profil kandidat dari MPP yang bukan haknya — dan datanya berisi NIK,
     * alamat, serta berkas pribadi.
     *
     * ── BACA SAJA, TANPA SATU PUN TOMBOL AKSI ─────────────────────────────
     *
     * Tidak ada tahapan, tidak ada kirim email, tidak ada unggah. Halaman MPP
     * menjawab "kursi ini diisi siapa" — memutuskan nasib kandidat adalah
     * pekerjaan worklist, dan menaruh tombolnya di dua tempat membuat dua orang
     * bisa memutuskan hal yang sama tanpa saling tahu.
     */
    public function kandidatDetail(string $no, string $id)
    {
        if (! preg_match('#^[A-Za-z0-9\-/]{1,50}$#', $no)) {
            return ResponseHelper::error('No transaksi tidak valid', 422);
        }

        $realId = \Vinkla\Hashids\Facades\Hashids::decode($id)[0] ?? null;

        if (! $realId) {
            return ResponseHelper::error('Kandidat tidak valid.', 422);
        }

        $lamaran = DB::table('N_WEB_CAREERS_Lamaran as l')
            ->join('N_WEB_CAREERS_Program_Posisi as x', 'x.Id_Program_Posisi', '=', 'l.Program_Posisi_Id')
            ->leftJoin('N_WEB_CAREERS_Program as p', 'p.Id_Program', '=', 'l.Program_Id')
            ->leftJoin('N_WEB_CAREERS_Users as u', 'u.Id_Users', '=', 'l.Id_Users')
            ->where('l.Id_Lamaran', (int) $realId)
            // Inilah pagarnya: lamaran yang bukan milik MPP ini tidak pernah
            // terbaca, sekalipun id-nya benar.
            ->where('x.Mpp_Ref', $no)
            ->select(
                'l.Id_Lamaran', 'l.Kode', 'l.Status', 'l.Urutan_Tahap', 'l.Total_Tahap',
                'l.Waktu_Selesai', 'l.Created_At',
                'p.Nama as ProgramNama', 'p.Kategori',
                'u.Nama as Pelamar', 'u.Email as EmailAkun', 'u.No_Hp as HpAkun',
                'x.Posisi', 'x.Lokasi', 'x.Departemen', 'x.Level',
            )
            ->first();

        if (! $lamaran) {
            return ResponseHelper::error('Kandidat tidak ditemukan pada MPP ini.', 404);
        }

        try {
            $profil = \App\Support\Career\LaporanKandidat::rakit((int) $realId);
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal merakit profil kandidat #{$realId}: ".$e->getMessage());
            $profil = null;
        }

        return ResponseHelper::success([
            'lamaran' => [
                'kode' => $lamaran->Kode,
                'pelamar' => $lamaran->Pelamar ?: '—',
                'email' => $lamaran->EmailAkun,
                'hp' => $lamaran->HpAkun,
                'posisi' => $lamaran->Posisi,
                'departemen' => $lamaran->Departemen,
                'lokasi' => $lamaran->Lokasi,
                'level' => $lamaran->Level,
                'program' => $lamaran->ProgramNama,
                'kategori' => $lamaran->Kategori,
                'status' => $lamaran->Status,
                'melamarPada' => $lamaran->Created_At ? Carbon::parse($lamaran->Created_At)->toDateString() : null,
                'diterimaPada' => $lamaran->Waktu_Selesai ? Carbon::parse($lamaran->Waktu_Selesai)->toDateString() : null,
            ],
            // Seluruh formulir yang diisi kandidat, lengkap dengan berkasnya.
            // Bentuknya sama dengan yang dipakai Export Studio — satu perakit,
            // jadi profil di layar dan di PDF mustahil berbeda isinya.
            'formulir' => $profil['formulir'] ?? [],
            'kandidat' => $profil['kandidat'] ?? null,
            // ── PROGRES SELEKSI, TANPA RAPOR TES ──────────────────────────
            //
            // Yang dikirim hanya URUTAN tahap dan statusnya: "sudah dilewati /
            // sedang berjalan / belum". Nilai tes, catatan penilai, dan
            // keputusan per aktivitas TIDAK ikut — itu bahan untuk memutuskan,
            // dan memutuskan adalah pekerjaan worklist.
            //
            // Tahapnya tetap dikirim karena menjawab pertanyaan yang wajar di
            // halaman MPP: "kursi ini diisi lewat proses seperti apa".
            'tahap' => $this->tahapRingkas((int) $realId),
        ], 'Profil kandidat MPP');
    }

    /**
     * Urutan tahap seleksi + statusnya — TANPA nilai & catatan penilaian.
     *
     * Sengaja ringkas. Halaman MPP menjelaskan bagaimana kursi terisi, bukan
     * menyediakan bahan untuk menilai ulang orangnya; skor dan catatan asesor
     * tetap tinggal di worklist bersama tombol keputusannya.
     */
    private function tahapRingkas(int $lamaranId): array
    {
        return DB::table('N_WEB_CAREERS_Lamaran_Tahap')
            ->where('Lamaran_Id', $lamaranId)
            ->orderBy('Urutan')
            ->get(['Urutan', 'Label', 'Status', 'Hasil', 'Diputus_At'])
            ->map(fn ($t) => [
                'urutan' => (int) $t->Urutan,
                'label' => $t->Label,
                'status' => $t->Status,
                'hasil' => $t->Hasil,
                'selesai' => $t->Diputus_At !== null,
                'diputusPada' => $t->Diputus_At ? Carbon::parse($t->Diputus_At)->toDateString() : null,
            ])
            ->values()
            ->all();
    }

    /**
     * GET /api/v1/karir/master-mpp/{no}/berkas/{id} — buka/unduh satu berkas.
     *
     * Pagarnya sama dengan kandidatDetail(): berkas hanya bisa dibuka bila
     * pengisiannya benar-benar milik lamaran pada MPP ini. Menebak id berkas
     * milik MPP lain berujung 404, bukan berkas orang lain.
     *
     * Berkas apply-form tersimpan di GCS (bucket PRIVAT), jadi yang dikirim
     * adalah SIGNED URL berumur pendek — bukan berkasnya yang dialirkan lewat
     * server ini. Cadangan lokal disediakan untuk data lama & lingkungan
     * pengembangan tanpa GCS.
     */
    public function berkasKandidat(string $no, string $id)
    {
        if (! preg_match('#^[A-Za-z0-9\-/]{1,50}$#', $no)) {
            abort(422);
        }

        $realId = \Vinkla\Hashids\Facades\Hashids::decode($id)[0] ?? null;

        if (! $realId) {
            abort(404);
        }

        $b = DB::table('N_WEB_CAREERS_Formulir_Berkas as b')
            ->join('N_WEB_CAREERS_Formulir_Pengisian as fp', 'fp.Id_Formulir_Pengisian', '=', 'b.Formulir_Pengisian_Id')
            ->join('N_WEB_CAREERS_Lamaran as l', 'l.Id_Lamaran', '=', 'fp.Lamaran_Id')
            ->join('N_WEB_CAREERS_Program_Posisi as x', 'x.Id_Program_Posisi', '=', 'l.Program_Posisi_Id')
            ->where('b.Id_Formulir_Berkas', (int) $realId)
            ->where('x.Mpp_Ref', $no)
            // Nama_Asli, BUKAN Nama_File — kolom itu tidak ada di tabel ini,
            // dan menyebutnya membuat setiap permintaan berkas gagal 500.
            ->select('b.Id_Formulir_Berkas', 'b.Path_File', 'b.Mime', 'b.Nama_Asli')
            ->first();

        if (! $b) {
            abort(404, 'Berkas tidak ditemukan pada MPP ini.');
        }

        if ($b->Path_File) {
            try {
                $gcs = \Illuminate\Support\Facades\Storage::disk(\App\Support\Career\GcsBerkas::DISK);

                if ($gcs->exists($b->Path_File)) {
                    return redirect()->away($gcs->temporaryUrl($b->Path_File, now()->addMinutes(15)));
                }
            } catch (\Throwable $e) {
                Log::channel('web_career')->warning(
                    'Signed URL GCS gagal untuk berkas '.$b->Id_Formulir_Berkas.': '.$e->getMessage()
                );
            }
        }

        foreach ([storage_path('app/'.$b->Path_File), public_path($b->Path_File), $b->Path_File] as $kandidat) {
            if ($kandidat && is_file($kandidat)) {
                return response()->file($kandidat, ['Content-Type' => $b->Mime ?: 'application/octet-stream']);
            }
        }

        abort(404, 'File tidak ditemukan di penyimpanan.');
    }

    public function kandidat(Request $request, string $no)
    {
        if (! preg_match('#^[A-Za-z0-9\-/]{1,50}$#', $no)) {
            return ResponseHelper::error('No transaksi tidak valid', 422);
        }

        try {
            $page = max(1, (int) $request->query('page', 1));
            $perPage = min(50, max(1, (int) $request->query('per_page', 6)));
            $q = trim((string) $request->query('q', ''));

            $potong = \App\Support\Career\HasilKeputusan::kodePotongKuota() ?: ['LULUS'];

            $dasar = fn () => DB::table('N_WEB_CAREERS_Lamaran as l')
                ->join('N_WEB_CAREERS_Program_Posisi as x', 'x.Id_Program_Posisi', '=', 'l.Program_Posisi_Id')
                ->join('N_WEB_CAREERS_Users as u', 'u.Id_Users', '=', 'l.Id_Users')
                ->leftJoin('N_WEB_CAREERS_Program as p', 'p.Id_Program', '=', 'x.Program_Id')
                ->where('x.Mpp_Ref', $no)
                ->whereIn('l.Status', $potong)
                ->when($q !== '', fn ($w) => $w->where(function ($x) use ($q) {
                    $x->where('u.Nama', 'like', "%{$q}%")->orWhere('l.Kode', 'like', "%{$q}%");
                }));

            $total = $dasar()->count();

            $rows = $dasar()
                // Yang terakhir diterima di atas: itu yang paling mungkin sedang
                // ditanyakan, dan Id_Lamaran naik terus jadi urutannya stabil.
                ->orderByDesc('l.Waktu_Selesai')
                ->orderByDesc('l.Id_Lamaran')
                ->forPage($page, $perPage)
                ->get([
                    'l.Id_Lamaran as id',
                    'l.Kode as kode', 'u.Nama as nama', 'p.Nama as program',
                    'x.Posisi as posisi', 'x.Lokasi as lokasi',
                    DB::raw('CONVERT(varchar(10), l.Waktu_Selesai, 23) as diterima_pada'),
                ])
                ->map(fn ($r) => [
                    // Diacak: id mentah tidak pernah keluar ke peramban.
                    'id' => \Vinkla\Hashids\Facades\Hashids::encode($r->id),
                    'kode' => $r->kode,
                    'nama' => $r->nama ?: '—',
                    'program' => $r->program ?: '—',
                    'posisi' => $r->posisi,
                    'lokasi' => $r->lokasi,
                    'diterimaPada' => $r->diterima_pada,
                ])
                ->values();

            return ResponseHelper::success([
                'data' => $rows,
                'total' => $total,
                'page' => $page,
                'perPage' => $perPage,
                'totalPages' => max(1, (int) ceil($total / $perPage)),
            ], 'Kandidat diterima');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal memuat kandidat MPP {$no}: ".$e->getMessage());

            return ResponseHelper::error('Gagal memuat kandidat', 500);
        }
    }

    // ═══════════════════════ OPSI DROPDOWN (folder sendiri — lihat spec) ═══════════════════════

    public function opsiDivisi()
    {
        $rows = DB::table('HRIS_Divisi')
            ->where('Kode_Perusahaan', self::KODE_PERUSAHAAN)
            ->orderBy('Keterangan')
            ->get(['ID_Divisi', 'Keterangan'])
            ->map(fn ($r) => ['value' => (int) $r->ID_Divisi, 'label' => $r->Keterangan])
            ->values();

        return ResponseHelper::success($rows, 'Opsi divisi');
    }

    public function opsiSubDivisi(Request $request)
    {
        $idDivisi = (int) $request->query('divisi');
        if (!$idDivisi) {
            return ResponseHelper::success([], 'Opsi departemen');
        }

        $rows = DB::table('HRIS_Divisi_Sub_Divisi as rel')
            ->join('HRIS_Sub_Divisi as sd', function ($j) {
                $j->on('sd.ID_Sub_Divisi', '=', 'rel.ID_Sub_Divisi')
                    ->where('sd.Kode_Perusahaan', self::KODE_PERUSAHAAN);
            })
            ->where('rel.ID_Divisi', $idDivisi)
            ->orderBy('sd.Keterangan')
            ->get(['sd.ID_Sub_Divisi', 'sd.Keterangan'])
            ->map(fn ($r) => ['value' => (int) $r->ID_Sub_Divisi, 'label' => $r->Keterangan])
            ->unique('value')
            ->values();

        return ResponseHelper::success($rows, 'Opsi departemen');
    }

    public function opsiLevel()
    {
        $rows = DB::table('HRIS_Level')
            ->where('Kode_Perusahaan', self::KODE_PERUSAHAAN)
            ->orderBy('Level_Hierarchy')
            ->get(['ID_Level', 'Keterangan'])
            ->map(fn ($r) => ['value' => (int) $r->ID_Level, 'label' => $r->Keterangan])
            ->values();

        return ResponseHelper::success($rows, 'Opsi level');
    }

    public function opsiJabatan()
    {
        $rows = DB::table('HRIS_Jabatan')
            ->where('Kode_Perusahaan', self::KODE_PERUSAHAAN)
            ->orderBy('Keterangan')
            ->get(['ID_Jabatan', 'Keterangan'])
            ->map(fn ($r) => ['value' => (int) $r->ID_Jabatan, 'label' => $r->Keterangan])
            ->values();

        return ResponseHelper::success($rows, 'Opsi jabatan');
    }

    public function opsiLokasi()
    {
        $rows = DB::table('N_HRIS_Master_Lokasi')
            ->where('Status_Aktif', 'Y')
            ->orderBy('Nama_Lokasi')
            ->get(['Kode_Lokasi', 'Nama_Lokasi'])
            ->map(fn ($r) => ['value' => $r->Kode_Lokasi, 'label' => $r->Nama_Lokasi])
            ->values();

        return ResponseHelper::success($rows, 'Opsi lokasi');
    }

    /** Cari karyawan aktif by nama/kode — dibatasi 20 hasil (bukan dump semua). */
    public function opsiKaryawan(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        $query = DB::table('Karyawan')
            ->where('Kode_Perusahaan', self::KODE_PERUSAHAAN)
            ->where('Aktif', 'Y');

        if ($q !== '') {
            $esc = str_replace(['[', '%', '_'], ['[[]', '[%]', '[_]'], $q);
            $like = '%' . $esc . '%';
            $query->where(fn ($w) => $w->where('Nama', 'like', $like)->orWhere('Kode_Karyawan', 'like', $like));
        }

        $rows = $query->orderBy('Nama')->limit(20)
            ->get(['Kode_Karyawan', 'Nama'])
            ->map(fn ($r) => ['value' => $r->Kode_Karyawan, 'label' => "{$r->Nama} ({$r->Kode_Karyawan})"])
            ->values();

        return ResponseHelper::success($rows, 'Opsi karyawan');
    }

    /**
     * Opsi Skill/Benefit — dipakai tag-picker (allow-create) di form MPP.
     * Master Skill/Benefit belum punya halaman kelola sendiri (sedang dibangun
     * terpisah); di sini hanya dibaca untuk sumber opsi + dibuat otomatis kalau
     * admin mengetik nama baru — lihat resolveTagIds().
     *
     * Skill dikelompokkan per N_WEB_CAREERS_Master_Skill_Kategori (ikon+warna) —
     * kategori disertakan per baris supaya frontend bisa mengelompokkan tampilan
     * tag-picker-nya (skill baru dari allow-create tidak berkategori, wajar:
     * kurasi kategori tetap tugas halaman Master Skill yang sedang dibangun).
     */
    public function opsiSkill()
    {
        $rows = DB::table('N_WEB_CAREERS_Master_Skill as s')
            ->leftJoin('N_WEB_CAREERS_Master_Skill_Kategori as k', 'k.Id_Master_Skill_Kategori', '=', 's.Id_Master_Skill_Kategori')
            ->where('s.Flag_Aktif', 'Y')
            ->orderByRaw('ISNULL(k.Urutan, 999999)')->orderBy('s.Nama_Skill')
            ->get(['s.Id_Skill', 's.Nama_Skill', 'k.Id_Master_Skill_Kategori as kat_id', 'k.Nama as kat_nama', 'k.Ikon as kat_ikon', 'k.Warna as kat_warna'])
            ->map(fn ($r) => $this->barisSkill((object) [
                'id' => $r->Id_Skill, 'nama' => $r->Nama_Skill,
                'kat_id' => $r->kat_id, 'kat_nama' => $r->kat_nama, 'kat_ikon' => $r->kat_ikon, 'kat_warna' => $r->kat_warna,
            ], true))
            ->values();

        return ResponseHelper::success($rows, 'Opsi skill');
    }

    public function opsiBenefit()
    {
        $rows = DB::table('N_WEB_CAREERS_Master_Benefit')
            ->where('Flag_Aktif', 'Y')
            ->orderBy('Nama_Benefit')
            ->get(['Id_Benefit', 'Nama_Benefit'])
            ->map(fn ($r) => ['value' => (int) $r->Id_Benefit, 'label' => $r->Nama_Benefit])
            ->values();

        return ResponseHelper::success($rows, 'Opsi benefit');
    }

    /** Opsi klasifikasi lowongan — 3 master yang sudah ada, hanya baris aktif. */
    public function opsiKlasifikasi()
    {
        $ambil = fn ($tabel, $idKol, $namaKol) => DB::table($tabel)
            ->where('Flag_Aktif', 'Y')
            ->orderBy($namaKol)
            ->get([$idKol . ' as id', $namaKol . ' as nama'])
            ->map(fn ($r) => ['value' => (int) $r->id, 'label' => $r->nama])
            ->values();

        return ResponseHelper::success([
            'employment' => $ambil('N_WEB_CAREERS_Master_Employment', 'Id_Employment', 'Nama_Employment'),
            'workplace' => $ambil('N_WEB_CAREERS_Master_Workplace', 'Id_Workplace', 'Nama_Workplace'),
            'experience' => $ambil('N_WEB_CAREERS_Master_Experience_Level', 'Id_Experience_Level', 'Nama_Experience_Level'),
        ], 'Opsi klasifikasi');
    }

    // ═══════════════════════ INTERNAL ═══════════════════════

    /** Skeleton join dasar — sama pola dengan MppLowonganController::baseQuery(). */
    private function baseQuery()
    {
        return DB::table(self::TABEL_G . ' as g')
            ->join(self::TABEL_D . ' as d', 'd.No_Transaksi_MPP', '=', 'g.No_Transaksi')
            ->leftJoin('HRIS_Divisi as dv', fn ($j) => $j
                ->on('dv.ID_Divisi', '=', 'g.Id_Divisi')
                ->where('dv.Kode_Perusahaan', self::KODE_PERUSAHAAN))
            ->leftJoin('HRIS_Sub_Divisi as sd', fn ($j) => $j
                ->on('sd.ID_Sub_Divisi', '=', 'g.Id_Sub_Divisi')
                ->where('sd.Kode_Perusahaan', self::KODE_PERUSAHAAN))
            ->leftJoin('HRIS_Level as lv', fn ($j) => $j
                ->on('lv.ID_Level', '=', 'g.Id_Level')
                ->where('lv.Kode_Perusahaan', self::KODE_PERUSAHAAN))
            ->leftJoin('HRIS_Jabatan as jb', fn ($j) => $j
                ->on('jb.ID_Jabatan', '=', 'g.Id_Jabatan')
                ->where('jb.Kode_Perusahaan', self::KODE_PERUSAHAAN))
            ->leftJoin('N_HRIS_Master_Lokasi as lok', 'lok.Kode_Lokasi', '=', 'g.Kode_Lokasi')
            ->leftJoin('Karyawan as k', fn ($j) => $j
                ->on('k.Kode_Karyawan', '=', 'g.User_Penganggung_Jawab')
                ->where('k.Kode_Perusahaan', self::KODE_PERUSAHAAN))
            // Akun yang MENGINPUT & yang terakhir menyunting. leftJoin, bukan
            // join: baris lama bisa saja tidak menyimpan idnya, dan MPP-nya tetap
            // harus tampil — tanpa nama, bukan hilang sama sekali.
            ->leftJoin('N_WEB_CAREERS_Users as ub', 'ub.Id_Users', '=', 'd.Created_By')
            ->leftJoin('N_WEB_CAREERS_Users as uu', 'uu.Id_Users', '=', 'd.Updated_By')
            ->leftJoin('N_WEB_CAREERS_Master_Employment as me', 'me.Id_Employment', '=', 'd.Employment_Type')
            ->leftJoin('N_WEB_CAREERS_Master_Workplace as mw', 'mw.Id_Workplace', '=', 'd.Workplace_Type')
            ->leftJoin('N_WEB_CAREERS_Master_Experience_Level as mx', 'mx.Id_Experience_Level', '=', 'd.Experience_Level');
    }

    /** Search + filter (status/periode/divisi) — dipakai list(). */
    private function applyFilters($q, Request $request)
    {
        $search = trim((string) $request->query('search', ''));
        if ($search !== '') {
            $esc = str_replace(['[', '%', '_'], ['[[]', '[%]', '[_]'], $search);
            $like = '%' . $esc . '%';
            $q->where(fn ($w) => $w
                ->where('g.No_Transaksi', 'like', $like)
                ->orWhere('jb.Keterangan', 'like', $like)
                ->orWhere('dv.Keterangan', 'like', $like));
        }

        $status = trim((string) $request->query('status', ''));
        if ($status === 'AKTIF') {
            $q->whereRaw("ISNULL(g.Status, '') <> 'Y'");
        } elseif ($status === 'DIBATALKAN') {
            $q->where('g.Status', 'Y');
        }

        $selesai = trim((string) $request->query('selesai', ''));
        if ($selesai === '1') {
            $q->where('g.Flag_Selesai', 'Y');
        } elseif ($selesai === '0') {
            $q->whereRaw("ISNULL(g.Flag_Selesai, '') <> 'Y'");
        }

        $divisi = (int) $request->query('divisi', 0);
        if ($divisi) {
            $q->where('g.Id_Divisi', $divisi);
        }

        $periode = trim((string) $request->query('periode', ''));
        if (preg_match('/^\d{4}-\d{2}$/', $periode)) {
            $start = \Carbon\Carbon::createFromFormat('Y-m-d', $periode . '-01')->startOfDay();
            $end = (clone $start)->addMonthNoOverflow();
            $q->where('g.Tanggal_Periode', '>=', $start)->where('g.Tanggal_Periode', '<', $end);
        }

        // ── RENTANG TANGGAL PERIODE — diketik admin ─────────────────────────
        //
        // Terpisah dari penyaring `periode` di atas, dan keduanya memang beda
        // pertanyaan: yang itu memilih SATU bulan dari daftar yang sudah ada,
        // yang ini menjawab "MPP yang tenggatnya jatuh antara tanggal A dan B" —
        // rentang yang hampir tidak pernah pas satu bulan penuh.
        //
        // Kedua ujungnya BOLEH DIISI SENDIRI-SENDIRI: "sejak 1 Sep" tanpa batas
        // akhir, atau "sampai 31 Des" tanpa batas awal. Menuntut keduanya
        // membuat pertanyaan yang paling sering diajukan — "apa yang jatuh tempo
        // sebelum akhir tahun" — tidak bisa ditanyakan sama sekali.
        //
        // Batas akhirnya INKLUSIF (< hari berikutnya): kolomnya datetime, dan
        // `<= 31 Des` akan membuang baris yang jamnya bukan 00:00.
        $dari = trim((string) $request->query('dari', ''));
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dari)) {
            $q->where('g.Tanggal_Periode', '>=', \Carbon\Carbon::parse($dari)->startOfDay());
        }

        $sampai = trim((string) $request->query('sampai', ''));
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $sampai)) {
            $q->where('g.Tanggal_Periode', '<', \Carbon\Carbon::parse($sampai)->startOfDay()->addDay());
        }

        $employment = (int) $request->query('employment', 0);
        if ($employment) {
            $q->where('d.Employment_Type', $employment);
        }

        $workplace = (int) $request->query('workplace', 0);
        if ($workplace) {
            $q->where('d.Workplace_Type', $workplace);
        }

        $experience = (int) $request->query('experience', 0);
        if ($experience) {
            $q->where('d.Experience_Level', $experience);
        }

        // ── PIC, BOLEH LEBIH DARI SATU ──────────────────────────────────────
        //
        // Dikirim sebagai daftar dipisah koma. Ditegakkan DI SERVER supaya
        // penyaringnya benar lintas halaman: menyaring di layar hanya
        // menyembunyikan baris pada halaman yang sedang terlihat, sementara MPP
        // milik PIC itu di halaman berikutnya tetap tak ikut terbawa.
        //
        // Dibatasi 50 nilai — cukup untuk seluruh PIC yang mungkin ada, dan
        // menahan permintaan yang mengirim ribuan nilai ke dalam klausa IN.
        $pic = collect(explode(',', (string) $request->query('pic', '')))
            ->map(fn ($v) => trim($v))
            ->filter()
            ->unique()
            ->take(50)
            ->values();

        if ($pic->isNotEmpty()) {
            $q->whereIn('g.User_Penganggung_Jawab', $pic->all());
        }

        $jenis = trim((string) $request->query('jenis', ''));
        if ($jenis === 'MT') {
            $q->where('g.Flag_MT', 'Y');
        } elseif ($jenis === 'REKRUTMEN') {
            $q->whereRaw("ISNULL(g.Flag_MT, '') <> 'Y'");
        }

        return $q;
    }

    /**
     * Tanggal target sesuai ketentuan SLA levelnya? — kalimat galat, atau null.
     *
     * Tiga hal yang ditolaknya, semua hanya untuk MPP BARU kecuali yang kedua:
     *   1. level yang belum punya ketentuan SLA sama sekali;
     *   2. tanggal target yang melewati batas ketentuan levelnya;
     *   3. tanggal target yang sudah lewat dari hari ini.
     *
     * MPP LAMA sengaja lebih longgar: menyunting deskripsi sebuah MPP tidak
     * boleh ditolak karena kebijakan yang berubah setelah MPP itu dibuat.
     * Lingkungan yang skrip skemanya belum dijalankan berjalan seperti sebelum
     * fitur ini ada.
     */
    private function galatSla(array $data, ?string $mulai = null, bool $baru = false, bool $diperpanjang = false): ?string
    {
        // ── MPP YANG SUDAH DIPERPANJANG: TANGGALNYA TIDAK DIPERIKSA ─────────
        //
        // Tenggatnya memang SEHARUSNYA melewati batas SLA polos — itu justru
        // hasil perpanjangan yang sudah disetujui berikut alasan tertulisnya.
        // Mengukurnya lagi terhadap hitungan polos berarti setiap penyuntingan
        // berikutnya ditolak dengan kalimat yang menyuruh admin "mundur ke
        // 02 Okt", membatalkan keputusan yang sudah diambil lewat pintu yang
        // benar — dan MPP-nya jadi tidak bisa disunting sama sekali.
        //
        // Tidak ada yang perlu dijaga di sini: pada MPP semacam itu update()
        // mengabaikan tanggal kiriman borang dan mempertahankan yang tersimpan.
        // Lihat pernahDiperpanjang() di update().
        if ($diperpanjang) {
            return null;
        }

        // ── MT TIDAK TERIKAT SLA LEVEL ──────────────────────────────────────
        //
        // SLA menjawab "berapa lama satu lowongan boleh terbuka", dan itu
        // pertanyaan rekrutmen reguler. Program MT berjalan per angkatan dengan
        // kalendernya sendiri; menyeretnya ke SLA level berarti MT tidak bisa
        // dibuat sama sekali pada level yang belum punya aturan — padahal aturan
        // itu memang tidak pernah dimaksudkan untuknya.
        //
        // Tak ada yang perlu diperiksa di sini: tanggal periode MT ditetapkan
        // server, bukan dikirim borang. Lihat periodeMpp().
        if (($data['jenisProgram'] ?? null) === 'MT') {
            return null;
        }

        $target = substr((string) ($data['tanggalPeriode'] ?? ''), 0, 10);

        // TARGET DI MASA LALU — ditolak untuk MPP BARU, apa pun levelnya.
        //
        // Kalender lama hanya mematikan tanggal SESUDAH batas SLA, sehingga
        // tanggal SEBELUM hari ini tetap bisa dipilih: MPP yang baru dibuat
        // sudah lahir dalam keadaan telat, dan tidak ada laporan yang bisa
        // membacanya sebagai apa pun selain kelalaian tim rekrutmen.
        //
        // MPP LAMA dikecualikan: menyunting deskripsi sebuah MPP yang tenggatnya
        // memang sudah lewat tidak boleh ditolak karena hal yang tak ada
        // hubungannya dengan suntingan itu.
        if ($baru && $target !== '' && $target < now()->toDateString()) {
            return sprintf(
                'Tanggal target sudah lewat (%s, sementara hari ini %s). '
                .'Pilih tanggal hari ini atau sesudahnya.',
                $target, now()->toDateString(),
            );
        }
        $sla = SlaMpp::batas((int) $data['idLevel'], $mulai);
        if (! $sla['batas']) {
            // LEVEL TANPA ATURAN: MPP BARU DITOLAK.
            //
            // Tanggal periode target bukan lagi isian bebas — ia hasil hitungan dari
            // ketentuan level. Tanpa ketentuan itu, tanggal apa pun yang dikirim
            // borang tidak berdasar pada apa-apa, dan MPP-nya jadi MPP yang tidak
            // pernah bisa dinilai telat.
            //
            // Layar sudah menahannya, tapi layar bukan penjaganya: permintaan bisa
            // datang dari tab lama yang sudah terbuka sebelum aturannya dihapus.
            //
            // Dua pengecualian, keduanya disengaja:
            //   - MPP LAMA (bukan $baru) tetap boleh disunting; aturannya mungkin
            //     memang dihapus lama setelah MPP-nya dibuat.
            //   - Lingkungan yang skrip masternya BELUM dijalankan (! siap())
            //     berjalan persis seperti sebelum fitur ini ada.
            if ($baru && SlaMpp::siap()) {
                return sprintf(
                    'Level yang dipilih belum punya ketentuan SLA, sehingga tanggal periode '
                    .'target tidak bisa dihitung. Tambahkan aturannya dulu di menu Master SLA MPP.',
                );
            }

            return null;
        }

        if ($target <= $sla['batas']) {
            return null;
        }

        // Kalimatnya menyebut ANGKA, TANGGAL BATAS, dan HARI MULAInya sekaligus.
        // "Melebihi SLA" tanpa ketiganya memindahkan tebakan ke orang berikutnya:
        // ia tidak tahu ketentuannya berapa hari, dihitung dari kapan, dan harus mundur ke
        // tanggal berapa supaya diterima.
        return sprintf(
            'Tanggal target melewati batas SLA level ini: %d hari kerja sejak %s, '
            .'jadi paling lambat %s. Pilih tanggal itu atau sebelumnya.',
            $sla['hari'], $sla['mulai'], $sla['batas'],
        );
    }

    /**
     * TANGGAL_PERIODE YANG BENAR-BENAR DISIMPAN.
     *
     *   REKRUTMEN → hasil hitungan SLA level, dikirim borang dan sudah lolos
     *               galatSla().
     *   MT        → tanggal MPP itu DIBUAT. Ditetapkan di sini, bukan oleh
     *               borang: apa pun yang dikirim layar diabaikan, sehingga
     *               permintaan yang datang langsung ke pintu ini pun tidak bisa
     *               menyelundupkan tanggal karangan.
     *
     * $dibuat diisi saat MENYUNTING — tanggal lahir baris itu sendiri. Tanpa
     * itu, membuka lalu menyimpan MPP MT bulan depan akan memindahkan
     * periodenya ke bulan depan, dan MPP-nya seolah baru dibuat hari itu.
     */
    private function periodeMpp(array $data, ?string $dibuat = null): string
    {
        if (($data['jenisProgram'] ?? null) === 'MT') {
            return $dibuat
                ? Carbon::parse($dibuat)->toDateString()
                : now()->toDateString();
        }

        return substr((string) ($data['tanggalPeriode'] ?? ''), 0, 10);
    }

    /**
     * Kolom Sla_* yang akan ditulis — kosong bila skema/masternya belum ada.
     *
     * Dikembalikan sebagai larik yang digabung ke payload insert/update,
     * sehingga MPP tetap tersimpan normal di lingkungan yang skrip skemanya
     * belum dijalankan — tanpa satu pun galat tentang kolom yang tidak ada.
     */
    private function snapshotSla(int $idLevel, string $mulai, bool $mt = false): array
    {
        if (! SlaMpp::siapSnapshot()) {
            return [];
        }

        // MT tidak punya SLA. Kolomnya DIKOSONGKAN, bukan dilewati begitu saja:
        // MPP yang tadinya rekrutmen lalu diubah menjadi MT akan tetap membawa
        // angka lamanya kalau tidak ditulis ulang — dan panel detailnya
        // menampilkan rentang tenggat untuk program yang tidak punya tenggat.
        if ($mt) {
            return [
                'Sla_Master_Id' => null,
                'Sla_Hari_Kerja' => null,
                'Sla_Mulai' => null,
                'Sla_Batas' => null,
                'Sla_Dikunci_At' => null,
            ];
        }

        $sla = SlaMpp::batas($idLevel, $mulai);

        return [
            'Sla_Master_Id' => $sla['masterId'],
            'Sla_Hari_Kerja' => $sla['hari'],
            'Sla_Mulai' => $sla['mulai'],
            'Sla_Batas' => $sla['batas'],
            'Sla_Dikunci_At' => now(),
        ];
    }

    /**
     * MPP ini pernah diperpanjang?
     *
     * Dibaca dari kolom penanda, bukan dengan menghitung baris riwayat: satu
     * nilai pada baris yang memang sudah diambil update(), bukan kueri kedua ke
     * tabel lain untuk pertanyaan berjawab ya/tidak.
     *
     * Menjawab `false` di lingkungan yang skrip perpanjangannya belum
     * dijalankan — di sana memang belum ada perpanjangan yang bisa terhapus,
     * dan update() berjalan persis seperti sebelumnya.
     */
    private function pernahDiperpanjang(string $no): bool
    {
        if (! SlaMpp::siapPanjang()) {
            return false;
        }

        return (int) DB::table(self::TABEL_D)
            ->where('No_Transaksi_MPP', $no)
            ->value('Sla_Perpanjangan_Ke') > 0;
    }

    /**
     * Tempelkan keadaan kursi ke tiap baris daftar — SATU kueri untuk semuanya.
     *
     * Memulangkan barisnya apa adanya bila ledger belum dipasang: layar sudah
     * punya cadangan (jumlahRekrutmen) dan tidak boleh kosong hanya karena
     * fitur kuota belum aktif di lingkungan itu.
     */
    private function tempelKursi(\Illuminate\Support\Collection $rows): \Illuminate\Support\Collection
    {
        if ($rows->isEmpty() || ! KursiMpp::siap()) {
            return $rows;
        }

        $keadaan = KursiMpp::keadaanBanyak($rows->pluck('noTransaksi')->all());

        return $rows->map(function ($r) use ($keadaan) {
            $r['kursi'] = $keadaan[$r['noTransaksi']] ?? null;

            return $r;
        })->values();
    }

    /** Snapshot yang sudah tersimpan pada sebuah MPP — untuk update(). */
    private function slaTersimpan(string $no): ?object
    {
        if (! SlaMpp::siapSnapshot()) {
            return null;
        }

        return DB::table(self::TABEL_G.' as g')
            ->join(self::TABEL_D.' as d', 'd.No_Transaksi_MPP', '=', 'g.No_Transaksi')
            ->where('g.No_Transaksi', $no)
            ->selectRaw('g.Id_Level as level, CONVERT(varchar(10), d.Sla_Mulai, 23) as mulai, d.Sla_Hari_Kerja as hari')
            ->first();
    }

    /** Aturan borang — header + detail dalam satu payload. */
    private function validasi(Request $request): array
    {
        $data = $request->validate([
            'jenisProgram' => 'required|in:REKRUTMEN,MT',
            'idDivisi' => 'required|integer',
            'idSubDivisi' => 'nullable|integer',
            'idLevel' => 'required|integer',
            'idJabatan' => 'required|integer',
            'jumlahRekrutmen' => 'required|integer|min:1',
            // Wajib hanya untuk REKRUTMEN — di sanalah tanggalnya memang datang
            // dari borang (hasil hitungan SLA). Untuk MT tanggalnya ditetapkan
            // server, jadi menuntutnya di sini berarti menolak permintaan yang
            // isinya justru akan diabaikan.
            'tanggalPeriode' => 'nullable|required_unless:jenisProgram,MT|date',
            'kodeLokasi' => 'required|string|max:20',
            'kodeKaryawan' => 'required|string|max:25',
            'deskripsi' => 'required|string|max:1000',
            'employmentType' => 'required|integer',
            'workplaceType' => 'required|integer',
            'experienceLevel' => 'required|integer',
            'tanggungJawab' => 'nullable|array',
            'tanggungJawab.*' => 'string|max:300',
            'persyaratan' => 'nullable|array',
            'persyaratan.*' => 'string|max:300',
            // skills/benefits: elemen bisa int (id skill terpilih) ATAU string (nama baru
            // dari tag-picker allow-create) — sengaja tidak dibatasi tipe di sini, semua
            // di-cast string & dipangkas di baris setelah validate().
            'skills' => 'nullable|array',
            'benefits' => 'nullable|array',
        ], [
            'jenisProgram.required' => 'Jenis program wajib dipilih.',
            'jenisProgram.in' => 'Jenis program tidak valid.',
            'idDivisi.required' => 'Divisi wajib dipilih.',
            'idLevel.required' => 'Level wajib dipilih.',
            'idJabatan.required' => 'Jabatan wajib dipilih.',
            'jumlahRekrutmen.required' => 'Jumlah rekrutmen wajib diisi.',
            'jumlahRekrutmen.min' => 'Jumlah rekrutmen minimal 1.',
            'tanggalPeriode.required_unless' => 'Tanggal periode wajib diisi.',
            'kodeLokasi.required' => 'Lokasi wajib dipilih.',
            'kodeKaryawan.required' => 'Penanggung jawab wajib dipilih.',
            'deskripsi.required' => 'Deskripsi lowongan wajib diisi.',
            'employmentType.required' => 'Tipe kerja wajib dipilih.',
            'workplaceType.required' => 'Lokasi kerja wajib dipilih.',
            'experienceLevel.required' => 'Tingkat pengalaman wajib dipilih.',
        ]);
        $data['idSubDivisi'] = $data['idSubDivisi'] ?? null;
        // Baris kosong (mis. ditambah lalu dibatalkan tanpa diisi) tidak ikut disimpan.
        $bersihkan = fn ($arr) => array_values(array_filter(array_map(fn ($v) => trim((string) $v), $arr ?? [])));
        $data['tanggungJawab'] = $bersihkan($data['tanggungJawab'] ?? []);
        $data['persyaratan'] = $bersihkan($data['persyaratan'] ?? []);
        $data['skills'] = $bersihkan($data['skills'] ?? []);
        $data['benefits'] = $bersihkan($data['benefits'] ?? []);

        return $data;
    }

    /**
     * Nomor baru: MP{bulan 2 digit}{tahun 2 digit}{urut 4 digit}, reset tiap bulan.
     * lockForUpdate menyerialkan pembuatan bersamaan; PK komposit tetap jaring
     * pengaman terakhir kalau ada proses lain yang menulis di luar controller ini.
     */
    private function nomorBaru(): string
    {
        $prefix = 'MP' . now()->format('my');

        $max = DB::table(self::TABEL_G)
            ->where('No_Transaksi', 'like', $prefix . '%')
            ->lockForUpdate()
            ->max('No_Transaksi');

        $urut = $max ? ((int) substr($max, -4)) + 1 : 1;

        return $prefix . str_pad((string) $urut, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Ganti seluruh Tanggung Jawab & Persyaratan (N_WEB_CAREERS_Points_MPP) satu
     * MPP — hapus semua baris lama lalu tulis ulang. Volumenya kecil per MPP
     * (belasan baris), jadi "ganti semua" lebih sederhana & aman daripada diff.
     */
    private function simpanPoints(int $idDetail, array $tanggungJawab, array $persyaratan): void
    {
        DB::table('N_WEB_CAREERS_Points_MPP')->where('Id_Detail_MPP', $idDetail)->delete();

        $baris = [];
        foreach ($tanggungJawab as $i => $isi) {
            $baris[] = ['Id_Detail_MPP' => $idDetail, 'Section' => 'responsibility', 'Content' => $isi, 'Urutan' => $i + 1];
        }
        foreach ($persyaratan as $i => $isi) {
            $baris[] = ['Id_Detail_MPP' => $idDetail, 'Section' => 'requirement', 'Content' => $isi, 'Urutan' => $i + 1];
        }
        if ($baris) {
            DB::table('N_WEB_CAREERS_Points_MPP')->insert($baris);
        }
    }

    /**
     * Ganti seluruh baris tabel relasi (Detail_Skill_MPP / Detail_Benefit_MPP)
     * satu MPP dengan daftar id master yang sudah di-resolve (lihat resolveTagIds).
     */
    private function simpanRelasi(int $idDetail, string $tabelRelasi, string $kolomId, array $ids, ?int $userId): void
    {
        DB::table($tabelRelasi)->where('Id_Detail_MPP', $idDetail)->delete();

        if (!$ids) {
            return;
        }

        $now = now();
        DB::table($tabelRelasi)->insert(array_map(fn ($id) => [
            'Id_Detail_MPP' => $idDetail,
            $kolomId => $id,
            'Created_At' => $now,
            'Created_By' => $userId,
        ], $ids));
    }

    /**
     * Ubah nilai tag-picker (mix of existing id & nama baru) jadi daftar id master.
     * - Angka → dipakai langsung sebagai id (kalau memang ada di tabel).
     * - Teks → dicari dulu (case-insensitive, supaya "php" tidak dobel dengan "PHP"
     *   yang sudah ada), kalau belum ada baru dibuat baris master baru saat itu juga.
     * Master Skill/Benefit belum punya halaman kelola sendiri — inilah satu-satunya
     * jalur penambahan datanya untuk saat ini.
     */
    private function resolveTagIds(array $values, string $tabel, string $kolomId, string $kolomNama, ?int $userId): array
    {
        $ids = [];
        foreach ($values as $v) {
            if (is_numeric($v)) {
                $id = (int) $v;
                if (DB::table($tabel)->where($kolomId, $id)->exists()) {
                    $ids[] = $id;
                }
                continue;
            }

            $nama = trim((string) $v);
            if ($nama === '') {
                continue;
            }

            $ada = DB::table($tabel)->whereRaw("LOWER({$kolomNama}) = ?", [mb_strtolower($nama)])->value($kolomId);
            if ($ada) {
                $ids[] = (int) $ada;
                continue;
            }

            $ids[] = DB::table($tabel)->insertGetId([
                $kolomNama => mb_substr($nama, 0, 100),
                'Flag_Aktif' => 'Y',
                'Created_At' => now(),
                'Created_By' => $userId,
            ], $kolomId);
        }

        return array_values(array_unique($ids));
    }
}
