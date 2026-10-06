<?php

namespace App\Http\Controllers\Career\ProgramKegiatan;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Support\Career\AksesService;
use App\Support\Career\FieldTurunan;
use App\Support\Career\FormulirSchema;
use App\Support\Career\KodeUnik;
use App\Support\Career\MesinSyarat;
use App\Support\Career\SerahTerimaPic;
use App\Support\Career\Skrining;
use App\Support\Career\VersiAlur;
use App\Support\CareerShell;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Vinkla\Hashids\Facades\Hashids;

/**
 * WEB CAREER — PROGRAM KEGIATAN (induk-detail: Program + Batch + Posisi + Kriteria).
 * SPA + WEB. CRUD Query Builder + ResponseHelper + Log channel + Hashids.
 */
class ProgramKegiatanController extends Controller
{
    public function index()
    {
        return Inertia::render('Career/admin/program-kegiatan/programKegiatan', CareerShell::props('/karir/program-kegiatan', 'Program Kegiatan'));
    }

    public function list(Request $request)
    {
        try {
            // ── FILTER SERVER-SIDE (Filter Panel) ──
            $q = trim((string) $request->query('q', ''));
            $kategori = trim((string) $request->query('kategori', ''));
            $status = strtoupper(trim((string) $request->query('status', '')));
            $dari = $request->query('dari');
            $sampai = $request->query('sampai');

            // Kategori yang BOLEH dilihat pengguna ini (Role_Konten_Access).
            // NULL = tidak dibatasi. Ini gerbang sebenarnya — tab di layar hanya
            // mengikuti, jadi kategori terlarang tak bisa diintip lewat URL.
            $izin = AksesService::kategoriDiizinkan('programPage');

            /*
             * ══ LINGKUP PIC — GERBANG YANG SELAMA INI TIDAK ADA ══════════════
             *
             * Kategori sudah dijaga di atas, tapi lingkup PIC (SENDIRI / TIM /
             * SEMUA) belum pernah diterapkan di daftar ini. Akibatnya rekruter
             * berlingkup SENDIRI tetap melihat SELURUH program dalam kategori
             * yang boleh ia buka — termasuk yang lokernya dipegang orang lain,
             * yang jelas bukan haknya. Lingkupnya sudah ditegakkan di serah
             * terima, jadi ia tidak bisa MENGUBAH milik orang lain; tapi ia
             * tetap bisa MEMBACANYA, dan itu tetap kebocoran.
             *
             * Sebuah program dianggap miliknya bila SALAH SATU benar:
             *   - ada posisi yang PIC-nya tercakup lingkup, ATAU
             *   - PIC programnya sendiri tercakup lingkup, ATAU
             *   - ia yang membuat programnya.
             *
             * Syarat ketiga bukan kelonggaran: program yang baru dibuat belum
             * punya posisi sama sekali: tanpa itu, program hilang dari layar
             * pembuatnya sendiri satu detik setelah disimpan.
             */
            $bolehPic = AksesService::picDiizinkan('programPage');
            $sayaId = session('career_auth.id');

            $dasar = fn () => DB::table('N_WEB_CAREERS_Program as p')
                ->leftJoin('N_WEB_CAREERS_Users as u', 'u.Id_Users', '=', 'p.Created_By_Id')
                ->leftJoin('N_WEB_CAREERS_Master_Alur as a', 'a.Kode', '=', 'p.Alur_Kode')
                ->when($izin, fn ($w) => $w->whereIn('p.Kategori', $izin))
                ->when($q !== '', fn ($w) => $w->where(function ($x) use ($q) {
                    $x->where('p.Nama', 'like', "%{$q}%")
                        ->orWhere('p.Kode', 'like', "%{$q}%")
                        ->orWhere('p.Penyelenggara', 'like', "%{$q}%")
                        ->orWhere('a.Nama', 'like', "%{$q}%");
                }))
                ->when(in_array($status, ['BERJALAN', 'DRAFT', 'SELESAI', 'NONAKTIF'], true), fn ($w) => $w->where('p.Status', $status))
                ->when($dari, fn ($w) => $w->whereDate('p.Created_At', '>=', $dari))
                ->when($sampai, fn ($w) => $w->whereDate('p.Created_At', '<=', $sampai))
                ->when($bolehPic !== null, function ($w) use ($bolehPic, $sayaId) {
                    // Larik KOSONG berarti "dibatasi, tapi tak ada yang cocok" —
                    // penanda mustahil menjaga whereIn tetap memulangkan nol,
                    // bukan diam-diam melunak jadi seluruhnya.
                    $kode = $bolehPic ?: ['__tidak_ada__'];

                    $w->where(function ($x) use ($kode, $sayaId) {
                        $x->whereIn('p.Pic_Kode_Karyawan', $kode)
                            ->orWhereExists(fn ($e) => $e
                                ->from('N_WEB_CAREERS_Program_Posisi as pp')
                                ->whereColumn('pp.Program_Id', 'p.Id_Program')
                                ->whereIn('pp.Pic_Kode_Karyawan', $kode))
                            ->when($sayaId, fn ($y) => $y->orWhere('p.Created_By_Id', $sayaId));
                    });
                });

            // Hitungan per kategori DIHITUNG SEBELUM tab diterapkan, supaya angka
            // di tiap tab tetap benar saat salah satunya sedang dipilih.
            $hitungKategori = (clone $dasar())
                ->select('p.Kategori', DB::raw('COUNT(*) as jml'))
                ->groupBy('p.Kategori')
                ->pluck('jml', 'Kategori');

            $programs = $dasar()
                ->when($kategori !== '', fn ($w) => $w->where('p.Kategori', $kategori))
                /*
                 * TERBARU KE TERLAMA — diurutkan dari WAKTU DIBUAT, bukan dari Id.
                 *
                 * Id memang hampir selalu sejalan dengan waktu, dan selama ini
                 * hasilnya kebetulan benar. Tapi keduanya bisa berpisah: baris
                 * hasil impor atau pemindahan data lazim membawa Created_At lama
                 * dengan Id baru, dan begitu itu terjadi program lama menclok di
                 * paling atas tanpa ada yang bisa menjelaskan kenapa.
                 *
                 * Id tetap dipakai sebagai PEMECAH SERI, dan itu bukan hiasan:
                 * delapan program di basis data ini berbagi satu Created_At yang
                 * sama persis. Tanpa pemecah, urutan kedelapannya diserahkan pada
                 * SQL Server — bisa berbeda antar pemuatan, dan daftar yang
                 * berubah-ubah sendiri jauh lebih membingungkan daripada daftar
                 * yang urutannya kurang ideal.
                 */
                ->orderByDesc('p.Created_At')
                ->orderByDesc('p.Id_Program')
                // Nama PIC-nya ikut dibaca. Kartunya menampilkan siapa yang
                // MENGERJAKAN program ini; tanpa nama, kolomnya cuma berisi
                // kode ('A1') yang tak berarti apa-apa bagi pembacanya.
                ->leftJoin('Karyawan as kp', function ($j) {
                    $j->on('kp.Kode_Karyawan', '=', 'p.Pic_Kode_Karyawan')
                        ->where('kp.Kode_Perusahaan', '=', '001');
                })
                // NAMA jadwalnya ikut dibaca, bukan cuma kodenya. Sebelum ini
                // layar mencetak `Jadwal_Kode` mentah — sesuatu seperti
                // "JDW-2026-01" yang tidak berarti apa pun bagi pembacanya, dan
                // itulah yang membuat sel "Jadwal Kegiatan" terbaca seolah rusak.
                ->leftJoin('N_WEB_CAREERS_Master_Jadwal as j', 'j.Kode', '=', 'p.Jadwal_Kode')
                ->select('p.*', 'u.Nama as Pembuat', 'a.Nama as AlurNama', 'kp.Nama as PicNama', 'j.Kegiatan as JadwalNama')
                ->get();

            // Rentang tanggal agenda tiap jadwal — satu kueri agregat untuk
            // seluruh baris, bukan satu per program.
            $jadwalKode = $programs->pluck('Jadwal_Kode')->filter()->unique()->all();
            $jadwalRange = $jadwalKode
                ? DB::table('N_WEB_CAREERS_Master_Jadwal_Agenda as ag')
                    ->join('N_WEB_CAREERS_Master_Jadwal as mj', 'mj.Id_Master_Jadwal', '=', 'ag.Master_Jadwal_Id')
                    ->whereIn('mj.Kode', $jadwalKode)
                    ->select('mj.Kode', DB::raw('COUNT(*) as jml'), DB::raw('MIN(ag.Tanggal_Mulai) as mulai'), DB::raw('MAX(ag.Tanggal_Selesai) as selesai'))
                    ->groupBy('mj.Kode')
                    ->get()
                    ->keyBy('Kode')
                : collect();

            // Berapa tahap yang dimiliki tiap alur — dipakai sel "Alur Seleksi"
            // supaya ia menyebut ISINYA, bukan cuma namanya.
            $alurKode = $programs->pluck('Alur_Kode')->filter()->unique()->all();
            $alurTahap = $alurKode
                ? DB::table('N_WEB_CAREERS_Master_Alur_Tahap as t')
                    ->join('N_WEB_CAREERS_Master_Alur as ma', 'ma.Id_Master_Alur', '=', 't.Master_Alur_Id')
                    ->whereIn('ma.Kode', $alurKode)
                    ->select('ma.Kode', DB::raw('COUNT(*) as jml'))
                    ->groupBy('ma.Kode')
                    ->pluck('jml', 'Kode')
                : collect();

            $batch = DB::table('N_WEB_CAREERS_Program_Batch')->orderBy('Id_Program_Batch')->get()->groupBy('Program_Id');
            $posisiSemua = DB::table('N_WEB_CAREERS_Program_Posisi')->orderBy('Id_Program_Posisi')->get();
            $posisi = $posisiSemua->groupBy('Program_Id');

            // ── NAMA PEMEGANG LOKER ─────────────────────────────────────────
            //
            // Satu kueri untuk seluruh kode yang benar-benar muncul, bukan
            // seluruh isi tabel Karyawan. Saringan "Pemegang Loker" di layar
            // dirakit dari kode yang MEMANG ada di data — jadi setiap pilihan
            // pasti menghasilkan sedikitnya satu baris, dan daftarnya tidak
            // pernah membengkak mengikuti besarnya divisi.
            $picNama = DB::table('Karyawan')
                ->where('Kode_Perusahaan', '001')
                ->whereIn('Kode_Karyawan', $posisiSemua->pluck('Pic_Kode_Karyawan')->filter()->unique()->all() ?: ['__'])
                ->pluck('Nama', 'Kode_Karyawan');
            // ── SERAH TERIMA TERAKHIR TIAP LOKER ────────────────────────────
            //
            // Kolom Pic_Kode_Karyawan cuma menyebut SIAPA yang memegang
            // sekarang. Yang ditanyakan orang saat lokernya tidak lagi di
            // tangannya adalah "kenapa" dan "sejak kapan" — dan jawabannya
            // sudah tercatat di riwayat, tinggal dibawa ke layar.
            //
            // Satu baris terbaru per loker lewat ROW_NUMBER, bukan satu kueri
            // per kartu: halaman ini menggambar seluruh loker sekaligus.
            $serahTerakhir = collect();
            $posisiIds = $posisiSemua->pluck('Id_Program_Posisi')->all();
            if ($posisiIds) {
                $serahTerakhir = collect(\App\Support\Career\MetrikRekrutmen::potongIn(
                    fn () => DB::query()->fromSub(
                        DB::table('N_WEB_CAREERS_Posisi_Pic_Riwayat')
                            ->select('*', DB::raw('ROW_NUMBER() OVER (PARTITION BY Program_Posisi_Id ORDER BY Id_Posisi_Pic_Riwayat DESC) AS rn')),
                        'r'
                    )->where('r.rn', 1),
                    'r.Program_Posisi_Id',
                    $posisiIds,
                ))->keyBy('Program_Posisi_Id');
            }

            $syarat = DB::table('N_WEB_CAREERS_Program_Syarat')->orderBy('Urutan')->get()->groupBy('Program_Id');

            $rows = $programs->map(fn ($p) => [
                'id' => Hashids::encode($p->Id_Program),
                'kode' => $p->Kode,
                'nama' => $p->Nama,
                'kategori' => $p->Kategori,
                'warna' => $p->Warna,
                'mode' => $p->Mode,
                'alur' => $p->Alur_Kode,
                'alurNama' => $p->AlurNama,
                'jadwal' => $p->Jadwal_Kode,
                'jadwalNama' => $p->JadwalNama,
                'jadwalAgenda' => (int) ($jadwalRange[$p->Jadwal_Kode]->jml ?? 0),
                'jadwalMulai' => $jadwalRange[$p->Jadwal_Kode]->mulai ?? null,
                'jadwalSelesai' => $jadwalRange[$p->Jadwal_Kode]->selesai ?? null,
                'alurTahap' => (int) ($alurTahap[$p->Alur_Kode] ?? 0),
                'penyelenggara' => $p->Penyelenggara,
                'status' => $p->Status,
                'createdBy' => $p->Pembuat ?: $p->Created_By,
                'createdAt' => $p->Created_At,
                // Jejak perubahan terakhir. Tanpa ini kaki panel hanya bisa
                // menyebut kapan programnya DIBUAT — padahal yang biasanya
                // ditanyakan adalah kapan ia terakhir disentuh.
                'updatedBy' => $p->Updated_By,
                'updatedAt' => $p->Updated_At,
                'batch' => collect($batch->get($p->Id_Program, []))->map(fn ($b) => ['nama' => $b->Nama, 'kuota' => (int) $b->Kuota, 'status' => $b->Status])->values(),
                // PIC program: siapa yang MENGERJAKAN, belum tentu yang membuat.
                'picKodeKaryawan' => $p->Pic_Kode_Karyawan ?? null,
                'picNama' => $p->PicNama ?? null,
                // `id` dan `pic` ikut dibawa: kartu program perlu menampilkan
                // siapa pemegang tiap loker, dan panel serah terima perlu
                // id-nya untuk memindahkan. Keduanya sudah ada di baris yang
                // sama — mengambilnya lewat permintaan kedua hanya menambah
                // perjalanan tanpa menambah data.
                'posisi' => collect($posisi->get($p->Id_Program, []))->map(fn ($x) => [
                    'id' => $x->Id_Program_Posisi,
                    'posisi' => $x->Posisi, 'departemen' => $x->Departemen, 'lokasi' => $x->Lokasi,
                    // 'terisi' SENGAJA TIDAK DIKIRIM. Berapa kursi yang sudah
                    // terisi adalah angka BERJALAN milik sebuah terbitan, bukan
                    // milik program. Program Kegiatan itu master data: ia
                    // menetapkan berapa kursi yang DIBUKA, bukan menghitung
                    // berapa yang sudah terisi. Angka terisinya hidup di
                    // Pembukaan Program, dan hanya di sana.
                    'kuota' => (int) $x->Kuota,
                    'status' => $x->Status, 'mppRef' => $x->Mpp_Ref ?? null, 'level' => $x->Level ?? null,
                    'picKode' => $x->Pic_Kode_Karyawan ?? null,
                    'picNama' => $x->Pic_Kode_Karyawan ? ($picNama[$x->Pic_Kode_Karyawan] ?? null) : null,
                    'picSejak' => $x->Pic_Sejak ?? null,
                    // Perpindahan TERAKHIR loker ini — null bila belum pernah
                    // berpindah tangan sama sekali.
                    'serah' => ($sr = $serahTerakhir->get($x->Id_Program_Posisi)) ? [
                        'dariKode' => $sr->Dari_Kode,
                        'dari' => $sr->Dari_Nama ?: $sr->Dari_Kode,
                        'keKode' => $sr->Ke_Kode,
                        'ke' => $sr->Ke_Nama ?: $sr->Ke_Kode,
                        'alasan' => $sr->Alasan,
                        // SENDIRI = rekruternya sendiri yang melepas.
                        // ADMIN   = dipindahkan orang lain lewat Master Akun.
                        // Bedanya penting: yang kedua bisa terjadi tanpa
                        // sepengetahuan pemilik lamanya.
                        'sumber' => $sr->Sumber,
                        'kandidat' => (int) $sr->Jml_Kandidat,
                        'pada' => (string) $sr->Created_At,
                        'oleh' => $sr->Created_By,
                    ] : null,
                ])->values(),
                // Syarat auto-gugur: satu baris = satu aturan bertingkat, menempel
                // ke tahap alur tertentu (lihat Batch 10).
                'syarat' => collect($syarat->get($p->Id_Program, []))->map(fn ($s) => [
                    'id' => Hashids::encode($s->Id_Program_Syarat),
                    'tahapId' => $s->Master_Alur_Tahap_Id,
                    'formulir' => $s->Formulir_Kode,
                    'nama' => $s->Nama,
                    'aturan' => json_decode($s->Aturan_Json ?: '{}', true),
                    'aksi' => $s->Aksi,
                    'pesanGugur' => $s->Pesan_Gugur,
                    'uji' => $s->Flag_Uji === 'Y',
                    'aktif' => $s->Flag_Aktif === 'Y',
                ])->values(),
            ])->values();

            // Tab kategori DIAMBIL DARI MASTER (bukan hardcode) dan disaring ke
            // kategori yang boleh dilihat pengguna ini. Bila ia hanya berhak atas
            // satu kategori, hanya satu tab yang muncul.
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
                'data' => $rows,
                'kategori' => $tabKategori,
                'total' => (int) $hitungKategori->sum(),
                // Layar perlu tahu lingkupnya untuk memutuskan apakah bidang
                // "Penanggung Jawab Program" boleh DIUBAH — bukan lagi untuk
                // memutuskan apakah ia digambar.
                //
                // Dulu bidang itu disembunyikan pada lingkup SENDIRI, dengan
                // alasan "tidak ada siapa pun yang bisa dipilih selain diri
                // sendiri". Alasannya benar, kesimpulannya keliru: yang tidak
                // perlu di situ adalah PILIHANNYA, bukan JAWABANNYA. Program
                // selalu punya penanggung jawab, dan menyembunyikan namanya
                // membuat satu-satunya fakta yang orang cari — "ini nanti
                // tercatat atas nama siapa?" — justru tak terbaca di mana pun,
                // sampai ia menyimpan lalu memeriksanya di daftar.
                //
                // Sekarang bidangnya selalu tampil, terisi nama pemiliknya, dan
                // hanya dikunci saat memang tak ada wewenang menugaskan orang
                // lain. `namaSaya` dikirim supaya yang terbaca nama, bukan kode.
                'akses' => [
                    'lingkupPic' => AksesService::lingkupPic('programPage'),
                    'bolehSerahTerima' => AksesService::boleh('programPage', 'SERAH_TERIMA'),
                    'kodeSaya' => AksesService::kodeKaryawanSaya(),
                    'namaSaya' => AksesService::namaKaryawanSaya(),
                ],
            ], 'Data program dimuat');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('Gagal memuat program: ' . $e->getMessage());

            return ResponseHelper::error('Gagal memuat data program', 500);
        }
    }

    /**
     * KATEGORI DIBATASI HAK AKSES — di sini, bukan di layar.
     *
     * Layar memang sudah menyembunyikan kategori yang tidak boleh dipakai, dan
     * bila hak aksesnya cuma satu, pilihannya tidak ditampilkan sama sekali.
     * Tapi itu kenyamanan, bukan pengaman: isi <select> bisa disunting lewat
     * DevTools, dan permintaannya bisa disusun tanpa membuka halamannya sama
     * sekali. Yang benar-benar menahan adalah baris di bawah.
     *
     * Sebelumnya aturannya cuma 'required|string|max:20' — artinya admin yang
     * dijatah kategori MT bisa membuat program REKRUTMEN, dan program itu lalu
     * hilang dari daftarnya sendiri (daftarnya disaring hak akses) sambil tetap
     * hidup dan menerima pelamar. Kegagalan yang tidak pernah terlihat oleh yang
     * membuatnya.
     *
     * kategoriDiizinkan() mengembalikan null bila pengguna tidak dibatasi; dalam
     * hal itu yang berlaku cukup daftar kategori aktif di master.
     */
    private function kategoriSah(): array
    {
        $izin = AksesService::kategoriDiizinkan('programPage');

        return DB::table('N_WEB_CAREERS_Master_Talent_Acquisition')
            ->where('Flag_Aktif', 'Y')
            ->when($izin, fn ($q) => $q->whereIn('Kode', $izin))
            ->pluck('Kode')
            ->all();
    }

    /**
     * Kalimat penolakan yang menyebut sebabnya.
     *
     * Bawaan Laravel untuk Rule::in berbunyi "The selected kategori is invalid"
     * — benar, tapi tidak menjelaskan apa pun kepada admin yang memang tidak
     * pernah melihat kategori itu di layarnya.
     */
    private function pesanValidasi(): array
    {
        return [
            'kategori.in' => 'Kategori itu di luar hak akses Anda, jadi programnya tidak bisa dibuat di sana.',
        ];
    }

    private function rules(): array
    {
        return [
            'nama' => 'required|string|max:150',
            'kategori' => ['required', 'string', 'max:20', Rule::in($this->kategoriSah())],
            'warna' => 'nullable|string|max:20',
            'mode' => 'nullable|string|max:20',
            'alur' => 'nullable|string|max:30',
            'jadwal' => 'nullable|string|max:30',
            'penyelenggara' => 'nullable|string|max:120',
            // Opsional: saat dibuat status dipaksa BERJALAN (lihat store()), dan saat
            // diubah field ini boleh tidak dikirim -> status lama dipertahankan.
            'status' => 'nullable|in:DRAFT,BERJALAN,SELESAI',
            // Batch, posisi, dan kriteria semuanya OPSIONAL. Program rekrutmen/MT yang
            // jalan langsung tanpa angkatan & tanpa syarat auto-gugur tetap sah.
            'batch' => 'nullable|array',
            'batch.*.nama' => 'required|string|max:120',
            'batch.*.kuota' => 'nullable|integer|min:0',
            'batch.*.status' => 'nullable|string|max:20',
            'posisi' => 'nullable|array',
            'posisi.*.posisi' => 'required|string|max:120',
            'posisi.*.departemen' => 'nullable|string|max:120',
            'posisi.*.lokasi' => 'nullable|string|max:80',
            'posisi.*.kuota' => 'nullable|integer|min:0',
            // Sengaja nullable, bukan required: baris posisi lama (diinput manual
            // sebelum aturan "wajib dari MPP") tetap bisa disimpan ulang saat program
            // diedit. Pemaksaan pilih-dari-MPP ada di modal.
            'posisi.*.mppRef' => 'nullable|string|max:60',
            // PIC program — "dibuat sendiri" vs "dibuatkan". Kosong = ikut
            // pembuatnya, dan itu perilaku yang benar untuk sebagian besar
            // program yang memang dibuka sendiri oleh pemegangnya.
            'picKodeKaryawan' => 'nullable|string|max:20',
            'posisi.*.level' => 'nullable|string|max:40',
            'posisi.*.status' => 'nullable|in:BUKA,PENUH,TUTUP',
            // Syarat auto-gugur. `aturan` berupa pohon DAN/ATAU — strukturnya
            // divalidasi MesinSyarat::bersihkan(), bukan oleh aturan Laravel,
            // karena kedalamannya tidak terbatas.
            'syarat' => 'nullable|array',
            'syarat.*.nama' => 'required|string|max:120',
            'syarat.*.tahapId' => 'nullable|integer',
            'syarat.*.formulir' => 'nullable|string|max:30',
            'syarat.*.aturan' => 'required|array',
            'syarat.*.aksi' => 'nullable|in:TANDAI,GUGUR',
            'syarat.*.pesanGugur' => 'nullable|string|max:500',
            'syarat.*.uji' => 'nullable|boolean',
            'syarat.*.aktif' => 'nullable|boolean',

            // ── PENGIKATAN KUESIONER SKRINING ────────────────────────────
            //
            // Dikirim dari langkah "Phone Screening" di wizard. Bentuknya
            // menunjuk loker lewat MPP_REF, bukan Id — saat borang ini dikirim
            // lokernya belum tersimpan, jadi Id-nya memang belum ada.
            //
            // Seluruhnya nullable: alur tanpa tahap skrining tidak mengirim
            // apa pun, dan program yang sengaja belum memasang kuesioner tetap
            // harus bisa disimpan.
            'skrining' => 'nullable|array|max:30',
            'skrining.*.aktivitasId' => 'required|integer|min:1',
            'skrining.*.bawaan' => 'nullable|string|max:30',
            'skrining.*.perLoker' => 'nullable|array|max:200',
            'skrining.*.perLoker.*' => 'nullable|string|max:30',
        ];
    }

    /**
     * Total kursi batch tidak boleh melebihi pagu MPP (jumlah kuota seluruh posisi).
     * Kurang dari pagu boleh — program memang tidak harus mengisi semua kursi sekaligus.
     * Kalau program tidak punya posisi sama sekali, tidak ada pagu yang bisa dilanggar.
     *
     * @return string|null pesan galat, atau null bila lolos
     */
    private function cekKuotaBatch(array $data): ?string
    {
        $pagu = collect($data['posisi'] ?? [])->sum(fn ($p) => (int) ($p['kuota'] ?? 0));
        if ($pagu <= 0) {
            return null;
        }

        $kursi = collect($data['batch'] ?? [])->sum(fn ($b) => (int) ($b['kuota'] ?? 0));

        return $kursi > $pagu
            ? "Total kursi batch ({$kursi}) melebihi kuota MPP ({$pagu})."
            : null;
    }

    /**
     * SIAPA PEMILIK LOKER YANG BARU DIBUAT.
     *
     * Urutannya sengaja begini, dari yang paling disengaja ke yang paling dasar:
     *
     *   1. PIC program        penugasan eksplisit — atasan membukakan program
     *                         untuk seorang rekruter
     *   2. kode saya sendiri  ia membuat sendiri, jadi ia yang mengerjakannya
     *   3. PIC MPP-nya        tidak ada keduanya (mis. dibuat akun tanpa kode);
     *                         pemegang MPP adalah tebakan terbaik yang tersisa
     *
     * ── KENAPA INI PENTING ──────────────────────────────────────────────────
     *
     * Sebelum ini loker lahir tanpa pemilik sama sekali. Akibatnya paling terasa
     * justru pada alur yang paling biasa: rekruter berlingkup SENDIRI membuat
     * programnya sendiri, lalu TIDAK MELIHAT SATU PUN kandidatnya — sebab tak
     * ada loker yang tercatat miliknya. Ia akan menyimpulkan hak aksesnya rusak,
     * padahal datanya yang tidak pernah terisi.
     */
    private function picLokerBaru(array $data, ?string $mppRef): ?string
    {
        $eksplisit = trim((string) ($data['picKodeKaryawan'] ?? ''));
        if ($eksplisit !== '') {
            return $eksplisit;
        }

        if ($saya = AksesService::kodeKaryawanSaya()) {
            return $saya;
        }

        return $mppRef
            ? DB::table('HRIS_Transaksi_GForm')->where('No_Transaksi', $mppRef)->value('User_Penganggung_Jawab')
            : null;
    }

    /**
     * Pengikatan kuesioner skrining program ↔ template.
     *
     * ── KENAPA MPP_REF, BUKAN ID LOKER ─────────────────────────────────────
     *
     * Wizard menentukan pengikatan SEBELUM programnya tersimpan, jadi tidak
     * ada Id_Program_Posisi yang bisa dikirim. Mpp_Ref adalah satu-satunya
     * penciri loker yang sudah dipegang layar sejak kartu MPP-nya dipilih —
     * dan ia juga yang dipakai loker itu seumur hidupnya.
     *
     * ── KENAPA DIHAPUS DULU, BUKAN DIPERBARUI ──────────────────────────────
     *
     * Sepola simpanAnak() yang lain: update program menulis ulang seluruh
     * anaknya. Menyisakan baris lama berarti pengikatan yang DILEPAS di layar
     * tetap hidup di basis data — dan kuesioner yang mestinya sudah dicabut
     * tetap membeku ke pelamar berikutnya tanpa ada yang menyadarinya.
     *
     * ── YANG TIDAK DISENTUH ────────────────────────────────────────────────
     *
     * Sesi yang sudah berjalan. Pengikatan cuma menentukan template apa yang
     * DIBEKUKAN ke pelamar berikutnya; yang sudah telanjur membeku memakai
     * salinannya sendiri dan tidak pernah membaca tabel ini lagi.
     */
    private function simpanSkrining(int $programId, array $data, ?int $userId): void
    {
        if (! Skrining::siap()) {
            return;
        }

        DB::table(Skrining::T_IKAT)->where('Program_Id', $programId)->delete();

        $daftar = $data['skrining'] ?? [];
        if (! $daftar) {
            return;
        }

        // Mpp_Ref → Id_Program_Posisi untuk program ini saja. Satu kueri,
        // bukan satu per loker.
        $petaLoker = DB::table('N_WEB_CAREERS_Program_Posisi')
            ->where('Program_Id', $programId)
            ->whereNotNull('Mpp_Ref')
            ->pluck('Id_Program_Posisi', 'Mpp_Ref');

        // ── KATEGORI DIJAGA DI SINI JUGA ─────────────────────────────────
        //
        // Wizard sudah menyaring daftar pilihannya, tapi borangnya tetap bisa
        // membawa kode apa pun. Template MT pada program REKRUTMEN bukan
        // sekadar melanggar izin — pertanyaan yang disusun untuk pelamar
        // Management Trainee akan dibacakan kepada pelamar staf, dan hasilnya
        // diagregasi seolah keduanya satu populasi.
        //
        // Kategori KOSONG pada template berarti berlaku untuk semua.
        $kategoriProgram = $data['kategori'] ?? null;
        $sahKode = DB::table(Skrining::T_MASTER)
            ->where('Flag_Aktif', 'Y')
            ->when($kategoriProgram, fn ($q) => $q->where(fn ($w) => $w
                ->where('Kategori', $kategoriProgram)->orWhereNull('Kategori')))
            ->pluck('Kode')
            ->flip();

        $now = now();
        $userName = session('career_auth.nama', 'ADMIN');
        $cap = [
            'Flag_Aktif' => 'Y',
            'Created_At' => $now, 'Created_By' => $userName, 'Created_By_Id' => $userId,
            'Updated_At' => $now, 'Updated_By' => $userName, 'Updated_By_Id' => $userId,
        ];

        $baris = [];
        foreach ($daftar as $s) {
            $aktivitasId = (int) $s['aktivitasId'];

            $bawaan = trim((string) ($s['bawaan'] ?? ''));
            if ($bawaan !== '' && ! isset($sahKode[$bawaan])) {
                $bawaan = '';
            }

            if ($bawaan !== '') {
                $baris[] = [
                    'Program_Id' => $programId,
                    'Program_Posisi_Id' => null,   // null = berlaku untuk seluruh loker
                    'Master_Alur_Tahap_Tes_Id' => $aktivitasId,
                    'Skrining_Kode' => $bawaan,
                    'Skrining_Versi' => null,      // null = selalu ikut versi terbit
                ] + $cap;
            }

            foreach ($s['perLoker'] ?? [] as $mppRef => $kode) {
                $kode = trim((string) $kode);
                $posisiId = $petaLoker[$mppRef] ?? null;

                // Loker yang tidak dikenali dilewati diam-diam: borang bisa
                // membawa MPP yang dibuang dari daftar posisi sesudah
                // pengikatannya dipilih, dan itu bukan kesalahan yang perlu
                // membatalkan seluruh penyimpanan program.
                if ($kode === '' || ! $posisiId || $kode === $bawaan || ! isset($sahKode[$kode])) {
                    continue;
                }

                $baris[] = [
                    'Program_Id' => $programId,
                    'Program_Posisi_Id' => (int) $posisiId,
                    'Master_Alur_Tahap_Tes_Id' => $aktivitasId,
                    'Skrining_Kode' => $kode,
                    'Skrining_Versi' => null,
                ] + $cap;
            }
        }

        foreach (array_chunk($baris, 50) as $potong) {
            DB::table(Skrining::T_IKAT)->insert($potong);
        }
    }

    /**
     * @param  array<string, int>  $terisiLama  jumlah terisi batch sebelum ditulis
     *                                          ulang, dikunci nama batch
     */
    private function simpanAnak(int $programId, array $data, ?int $userId, array $terisiLama = []): void
    {
        /*
        |─────────────────────────────────────────────────────────────────────
        | BATCH & LOKER DIPAKAI ULANG, TIDAK DIHAPUS-LALU-DIBUAT
        |─────────────────────────────────────────────────────────────────────
        |
        | Sampai perbaikan ini, menyunting program memanggil hapusAnak() yang
        | MENGHAPUS seluruh N_WEB_CAREERS_Program_Posisi milik program itu, lalu
        | menyisipkannya kembali. Id_Program_Posisi karena itu berganti nomor
        | setiap kali seseorang menekan Simpan — sekadar mengganti nama program
        | sudah cukup.
        |
        | Yang rusak karenanya, dan tidak satu pun memunculkan galat:
        |
        |   · N_WEB_CAREERS_Lamaran.Program_Posisi_Id menunjuk baris yang sudah
        |     tidak ada. Tidak ada satu foreign key pun yang menahannya, jadi
        |     seluruh pelamar lowongan itu menjadi yatim diam-diam;
        |   · kolom Terisi kembali 0, dan seluruh pembukuan kuota — termasuk
        |     buku kursi MPP — berdiri di atas angka yang salah;
        |   · Pic_Kode_Karyawan dihitung ulang, sehingga loker yang sudah
        |     DISERAHTERIMAKAN kepada rekruter lain diam-diam kembali ke
        |     penyuntingnya, dan riwayat serah terimanya berbohong.
        |
        | Baris sekarang dicocokkan lewat identitasnya: Mpp_Ref untuk loker
        | (satu MPP dibuka sekali per program), nama untuk batch. Yang cocok
        | DIPERBARUI di tempat; yang tidak lagi ada di borang baru dibuang.
        */
        $batchLama = DB::table('N_WEB_CAREERS_Program_Batch')
            ->where('Program_Id', $programId)->get(['Id_Program_Batch', 'Nama', 'Terisi']);
        $idBatch = $batchLama->mapWithKeys(fn ($b) => [trim((string) $b->Nama) => (int) $b->Id_Program_Batch])->all();
        $batchDipakai = [];

        foreach ($data['batch'] ?? [] as $b) {
            $nama = trim((string) $b['nama']);
            $isi = [
                'Program_Id' => $programId,
                'Nama' => $b['nama'],
                'Kuota' => $b['kuota'] ?? 0,
                'Status' => $b['status'] ?? 'AKTIF',
                'Updated_By_Id' => $userId,
            ];

            if ($id = ($idBatch[$nama] ?? null)) {
                // Terisi TIDAK disentuh: ia hasil pembukuan, bukan isian borang.
                DB::table('N_WEB_CAREERS_Program_Batch')->where('Id_Program_Batch', $id)->update($isi);
                $batchDipakai[] = $id;

                continue;
            }

            $batchDipakai[] = (int) DB::table('N_WEB_CAREERS_Program_Batch')->insertGetId(
                $isi + [
                    // Cadangan untuk batch yang namanya diganti: angkanya
                    // dipungut sebelum penyimpanan (lihat update()).
                    'Terisi' => (int) ($terisiLama[$nama] ?? 0),
                    'Created_By_Id' => $userId,
                ],
                'Id_Program_Batch',
            );
        }

        DB::table('N_WEB_CAREERS_Program_Batch')
            ->where('Program_Id', $programId)
            ->when($batchDipakai, fn ($w) => $w->whereNotIn('Id_Program_Batch', $batchDipakai))
            ->delete();

        // ── LOKER ────────────────────────────────────────────────────────────
        $lokerLama = DB::table('N_WEB_CAREERS_Program_Posisi')
            ->where('Program_Id', $programId)->get(['Id_Program_Posisi', 'Mpp_Ref', 'Posisi']);

        $kunci = fn ($mpp, $posisi) => trim((string) $mpp) !== ''
            ? 'MPP:'.trim((string) $mpp)
            : 'POS:'.strtoupper(trim((string) $posisi));

        $idLoker = $lokerLama->mapWithKeys(fn ($x) => [
            $kunci($x->Mpp_Ref, $x->Posisi) => (int) $x->Id_Program_Posisi,
        ])->all();
        $lokerDipakai = [];

        foreach ($data['posisi'] ?? [] as $p) {
            $k = $kunci($p['mppRef'] ?? null, $p['posisi'] ?? '');
            $isi = [
                'Program_Id' => $programId, 'Posisi' => $p['posisi'],
                'Departemen' => $p['departemen'] ?? null, 'Lokasi' => $p['lokasi'] ?? null,
                'Kuota' => $p['kuota'] ?? 0, 'Status' => $p['status'] ?? 'BUKA',
                'Mpp_Ref' => $p['mppRef'] ?? null, 'Level' => $p['level'] ?? null,
                'Updated_By_Id' => $userId,
            ];

            if ($id = ($idLoker[$k] ?? null)) {
                // Pic_Kode_Karyawan & Pic_Sejak SENGAJA TIDAK ikut diperbarui.
                // Kepemilikan loker berpindah lewat panel Serah Terima, yang
                // mencatat peristiwanya; menghitungnya ulang di sini akan
                // mengembalikan loker kepada penyunting tanpa satu baris jejak
                // pun — dan membatalkan serah terima yang sah.
                DB::table('N_WEB_CAREERS_Program_Posisi')->where('Id_Program_Posisi', $id)->update($isi);
                $lokerDipakai[] = $id;

                continue;
            }

            $pic = $this->picLokerBaru($data, $p['mppRef'] ?? null);

            $lokerDipakai[] = (int) DB::table('N_WEB_CAREERS_Program_Posisi')->insertGetId(
                $isi + [
                    // Tanpa ini loker lahir yatim — lihat picLokerBaru().
                    'Pic_Kode_Karyawan' => $pic,
                    'Pic_Sejak' => $pic ? now() : null,
                    'Created_By_Id' => $userId,
                ],
                'Id_Program_Posisi',
            );
        }

        // Loker yang dicabut dari borang dibuang — TAPI hanya bila tidak ada
        // pelamar yang menempel padanya. Menghapus loker bermuatan berarti
        // membuat seluruh pelamarnya yatim, dan itu justru kerusakan yang
        // sedang ditutup di sini.
        $calonBuang = DB::table('N_WEB_CAREERS_Program_Posisi')
            ->where('Program_Id', $programId)
            ->when($lokerDipakai, fn ($w) => $w->whereNotIn('Id_Program_Posisi', $lokerDipakai))
            ->pluck('Id_Program_Posisi')->all();

        foreach ($calonBuang as $id) {
            $adaPelamar = DB::table('N_WEB_CAREERS_Lamaran')->where('Program_Posisi_Id', $id)->exists();
            if ($adaPelamar) {
                Log::channel('web_career')->warning(
                    "[LOKER] #{$id} dicabut dari borang program #{$programId} tapi masih punya pelamar — tidak dihapus."
                );

                continue;
            }
            DB::table('N_WEB_CAREERS_Program_Posisi')->where('Id_Program_Posisi', $id)->delete();
        }
        // Ditulis SESUDAH loker, dan itu bukan kebetulan: pengikatan per loker
        // menunjuk Id_Program_Posisi, dan Id itu baru ada begitu barisnya masuk.
        $this->simpanSkrining($programId, $data, $userId);

        $now = now();
        $userName = session('career_auth.nama', 'ADMIN');
        $urut = 1;
        $schemaTahap = []; // cache Id_Master_Alur_Tahap -> daftar key field, supaya tidak query berulang

        foreach ($data['syarat'] ?? [] as $s) {
            // Simpul setengah jadi dibuang di sini. Aturan yang tidak lengkap
            // berbahaya: bisa menggugurkan pelamar tanpa maksud siapa pun.
            $aturan = MesinSyarat::bersihkan($s['aturan'] ?? []);
            if (! $aturan) {
                continue;
            }

            // GERBANG FIELD TURUNAN TANPA SUMBER: syarat berbasis "usia"/"jenjang"/
            // dst. hanya berarti kalau formulir tahap itu punya field dengan salah
            // satu key yang dikenal FieldTurunan. Untuk formulir dinamis (bukan
            // FORMULIR_1..4 lama), key-nya bebas ditentukan admin builder — tanpa
            // gerbang ini, syarat GUGUR bisa diam-diam menggugurkan SEMUA kandidat
            // karena field turunannya tidak pernah terhitung.
            //
            // Formulir LAMA (Komponen_Kode terisi) dilewati saja — field-nya cuma
            // ada di skema JS, tidak tercatat di DB, jadi tidak bisa diperiksa dari
            // sini. Formulir lama ditulis dengan field yang sudah cocok dengan
            // FieldTurunan sejak awal, jadi tidak butuh gerbang ini.
            $tahapId = $s['tahapId'] ?? null;
            if ($tahapId) {
                $fieldDipakai = array_intersect(MesinSyarat::fieldDipakai($aturan), array_keys(FieldTurunan::DEFINISI));
                if ($fieldDipakai) {
                    if (! array_key_exists($tahapId, $schemaTahap)) {
                        $tahap = DB::table('N_WEB_CAREERS_Master_Alur_Tahap as t')
                            ->leftJoin('N_WEB_CAREERS_Master_Formulir as f', 'f.Kode', '=', 't.Formulir_Kode')
                            ->where('t.Id_Master_Alur_Tahap', $tahapId)
                            ->first(['t.Formulir_Kode', 'f.Komponen_Kode']);

                        // null = form lama / tidak ada form -> tidak bisa diperiksa, lewati.
                        // array = form dinamis -> daftar key field sungguhan, WAJIB diperiksa.
                        $schemaTahap[$tahapId] = ($tahap && $tahap->Formulir_Kode && ! $tahap->Komponen_Kode)
                            ? FormulirSchema::keyField(FormulirSchema::publishedByKode($tahap->Formulir_Kode)['schema'] ?? null)
                            : null;
                    }

                    if ($schemaTahap[$tahapId] !== null) {
                        foreach ($fieldDipakai as $turunanKey) {
                            if (! FieldTurunan::sumberTersedia($turunanKey, $schemaTahap[$tahapId])) {
                                throw \Illuminate\Validation\ValidationException::withMessages([
                                    'syarat' => "Syarat \"{$s['nama']}\" memakai field turunan \"{$turunanKey}\", tapi formulir tahap ini tidak punya field sumbernya — field turunan itu tidak akan pernah terhitung dan syarat ini akan menggugurkan semua kandidat.",
                                ]);
                            }
                        }
                    }
                }
            }

            DB::table('N_WEB_CAREERS_Program_Syarat')->insert([
                'Program_Id' => $programId,
                'Master_Alur_Tahap_Id' => $s['tahapId'] ?? null,
                'Formulir_Kode' => $s['formulir'] ?? null,
                'Nama' => $s['nama'],
                'Aturan_Json' => json_encode($aturan, JSON_UNESCAPED_UNICODE),
                'Aksi' => $s['aksi'] ?? 'TANDAI',
                'Pesan_Gugur' => $s['pesanGugur'] ?? null,
                'Urutan' => $urut++,
                'Flag_Uji' => ! empty($s['uji']) ? 'Y' : 'T',
                'Flag_Aktif' => array_key_exists('aktif', $s) && ! $s['aktif'] ? 'T' : 'Y',
                'Created_At' => $now, 'Created_By' => $userName, 'Created_By_Id' => $userId,
                'Updated_At' => $now, 'Updated_By' => $userName, 'Updated_By_Id' => $userId,
            ]);
        }
    }

    /**
     * ⛔ HANYA UNTUK MENGHAPUS PROGRAM SELURUHNYA (destroy()).
     *
     * JANGAN dipakai di jalur SIMPAN. Menghapus lalu membuat ulang seluruh
     * loker membuat Id_Program_Posisi berganti tiap kali program disunting —
     * dan setiap lamaran menyimpan Id itu tanpa satu foreign key pun yang
     * menahannya. Jalur simpan memakai ulang barisnya; lihat simpanAnak().
     */
    private function hapusAnak(int $programId): void
    {
        DB::table('N_WEB_CAREERS_Program_Batch')->where('Program_Id', $programId)->delete();
        DB::table('N_WEB_CAREERS_Program_Posisi')->where('Program_Id', $programId)->delete();
        DB::table('N_WEB_CAREERS_Program_Syarat')->where('Program_Id', $programId)->delete();
    }

    /**
     * GUARD INFO DIVISI — dari daftar MPP (mppRefs) posisi terpilih, kembalikan
     * divisi mana yang BELUM punya info di Master Info Divisi (baris belum dibuat).
     * Info divisi WAJIB terisi sebelum lanjut (dipakai landing page). Fail-open:
     * bila cek gagal, TIDAK memblokir admin (cegah bug menjebak).
     */
    public function cekInfoDivisi(Request $request)
    {
        if (! config('career_divisi_guard.enabled', true)) {
            return ResponseHelper::success(['belumLengkap' => []], 'Cek info divisi (nonaktif)');
        }

        try {
            $refs = collect($request->input('mppRefs', []))->map(fn ($r) => trim((string) $r))->filter()->unique()->values();
            if ($refs->isEmpty()) {
                return ResponseHelper::success(['belumLengkap' => []], 'Tidak ada MPP untuk dicek');
            }

            // MPP → divisi (Id_Divisi + nama).
            $divisi = DB::table('HRIS_Transaksi_GForm as g')
                ->leftJoin('HRIS_Divisi as dv', function ($j) {
                    $j->on('dv.ID_Divisi', '=', 'g.Id_Divisi')->on('dv.Kode_Perusahaan', '=', 'g.Kode_Perusahaan');
                })
                ->whereIn('g.No_Transaksi', $refs->all())
                ->whereNotNull('g.Id_Divisi')
                ->select('g.Id_Divisi', 'dv.Keterangan as Nama')
                ->distinct()->get();

            $idList = $divisi->pluck('Id_Divisi')->filter()->unique()->all() ?: [0];

            // 1) Ada baris info level-divisi?
            $adaInfo = array_flip(DB::table('N_WEB_CAREERS_Division_Informations')->whereIn('Id_Divisi', $idList)->pluck('Id_Divisi')->all());
            // 2) Total departemen per divisi (dari mapping HRIS).
            $subTotal = DB::table('HRIS_Divisi_Sub_Divisi')->whereIn('ID_Divisi', $idList)
                ->select('ID_Divisi', DB::raw('COUNT(DISTINCT ID_Sub_Divisi) as t'))->groupBy('ID_Divisi')->pluck('t', 'ID_Divisi');
            // 3) Departemen yang SUDAH terisi infonya.
            $subTerisi = DB::table('HRIS_Divisi_Sub_Divisi as m')
                ->join('N_WEB_CAREERS_Sub_Divisi_Informations as si', 'si.Id_Sub_Divisi', '=', 'm.ID_Sub_Divisi')
                ->whereIn('m.ID_Divisi', $idList)
                ->select('m.ID_Divisi', DB::raw('COUNT(DISTINCT m.ID_Sub_Divisi) as t'))->groupBy('m.ID_Divisi')->pluck('t', 'ID_Divisi');

            // LENGKAP = ada info divisi + MINIMAL SATU departemen terisi.
            // ACCOUNTING (0/0) → belum lengkap; begitu ada 1 sub terisi → boleh lanjut.
            $belum = [];
            foreach ($divisi as $d) {
                if (isset($belum[$d->Id_Divisi])) {
                    continue;
                }
                $adaRow = isset($adaInfo[$d->Id_Divisi]);
                $total = (int) ($subTotal[$d->Id_Divisi] ?? 0);
                $terisi = (int) ($subTerisi[$d->Id_Divisi] ?? 0);
                $lengkap = $adaRow && $terisi >= 1;
                if ($lengkap) {
                    continue;
                }
                $alasan = ! $adaRow ? 'info divisi belum dibuat'
                    : ($total === 0 ? 'belum ada departemen' : "departemen belum diisi (0/{$total})");
                $belum[$d->Id_Divisi] = [
                    'nama' => $d->Nama ?: ('Divisi #' . $d->Id_Divisi),
                    'id' => Hashids::encode($d->Id_Divisi),
                    'alasan' => $alasan,
                ];
            }

            return ResponseHelper::success(['belumLengkap' => array_values($belum)], 'Cek info divisi');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('Gagal cek info divisi: ' . $e->getMessage());

            return ResponseHelper::success(['belumLengkap' => []], 'Cek info divisi (fallback)');
        }
    }

    /**
     * PIC program yang dipilih masih di dalam lingkup pengguna? — galat, atau null.
     *
     * ── KENAPA INI HARUS DIJAGA ─────────────────────────────────────────────
     *
     * Menugaskan program ke orang lain berarti MENYERAHKAN PEKERJAAN — loker
     * yang lahir dari program ini akan jadi miliknya. Tanpa penjaga, rekruter
     * berlingkup SENDIRI bisa melempar pekerjaan ke siapa pun di perusahaan
     * hanya dengan mengetik kode di borang, dan penerimanya baru tahu saat
     * kandidat sudah menumpuk di daftarnya.
     *
     * Pemegang SERAH_TERIMA dilewatkan: memindahkan pekerjaan orang lain memang
     * kewenangannya.
     */
    private function galatPicProgram(?string $kode)
    {
        $kode = trim((string) $kode);
        if ($kode === '') {
            return null;
        }

        // SERAH_TERIMA SENGAJA TIDAK LAGI MELEPAS GERBANG INI.
        //
        // Dulu pemegangnya dilewatkan begitu saja, dan itu membuat rekruter
        // berlingkup SENDIRI — yang hanya berhak atas pekerjaannya sendiri —
        // benar-benar bisa MENYIMPAN program atas nama orang lain. Bukan cuma
        // melihat namanya di dropdown: tersimpan, dan lokernya lahir sebagai
        // milik orang itu.
        //
        // Dua wewenang yang berbeda tertukar di sini:
        //
        //   SERAH_TERIMA   menyerahkan pekerjaan YANG SUDAH ADA dan MEMANG
        //                  miliknya — sumbernya diperiksa serahTerima(), dan
        //                  peristiwanya tercatat sebagai serah terima.
        //   Lingkup PIC    membuat pekerjaan BARU atas nama orang lain.
        //
        // Yang pertama tidak pernah menyiratkan yang kedua. Menyamakannya juga
        // menghapus jejak: penugasan lewat wizard tidak meninggalkan riwayat
        // serah terima, jadi kepemilikan berpindah tanpa satu baris pun yang
        // bisa ditanya kembali.

        $boleh = AksesService::picDiizinkan('programPage');
        if ($boleh === null) {
            return null;   // lingkup SEMUA
        }

        if (in_array($kode, $boleh, true)) {
            return null;
        }

        return ResponseHelper::error(
            'Penanggung jawab yang dipilih di luar lingkup Anda. '
            .'Hanya orang di dalam lingkup akses Anda yang bisa ditugaskan.',
            403
        );
    }

    /**
     * Ada posisi yang MPP-nya di luar lingkup PIC pengguna? — galat, atau null.
     *
     * Pemegang aksi LINTAS_PIC dilewatkan: itulah gunanya aksi tersebut ada —
     * menerobos saat mendesak. Terobosannya dicatat ke log, bukan didiamkan,
     * supaya pertanyaan "kenapa lowongan ini dibuka orang yang tidak
     * memegangnya" punya jawaban berbulan-bulan kemudian.
     */
    private function galatLingkupPic(array $posisi)
    {
        $refs = collect($posisi)
            ->pluck('mppRef')
            ->map(fn ($r) => trim((string) $r))
            ->filter()
            ->unique()
            ->values();

        if ($refs->isEmpty()) {
            return null;
        }

        $boleh = AksesService::picDiizinkan('programPage');
        if ($boleh === null) {
            return null;   // lingkup SEMUA
        }

        $luar = DB::table('HRIS_Transaksi_GForm')
            ->whereIn('No_Transaksi', $refs)
            ->whereNotIn('User_Penganggung_Jawab', $boleh ?: ['__tidak_ada__'])
            ->pluck('No_Transaksi');

        if ($luar->isEmpty()) {
            return null;
        }

        if (AksesService::boleh('programPage', 'LINTAS_PIC')) {
            Log::channel('web_career')->info(sprintf(
                '[LINTAS_PIC] %s memakai MPP di luar lingkupnya: %s',
                session('career_auth.nama', 'ADMIN'),
                $luar->implode(', ')
            ));

            return null;
        }

        return ResponseHelper::error(
            'MPP berikut di luar lingkup penanggung jawab Anda: '.$luar->implode(', ')
            .'. Minta pemegangnya membuka program ini, atau minta izin Lintas PIC.',
            403
        );
    }

    /**
     * POST .../program/serah-terima — SERAHKAN LOKER DARI SISI PROGRAM.
     *
     * Pintu kedua ke layanan yang sama (SerahTerimaPic). Bedanya cuma siapa
     * yang berdiri di depannya:
     *
     *   di sini             rekruter melepas lokernya SENDIRI — tidak menuntut
     *                       aksi istimewa, karena melepaskan milik sendiri bukan
     *                       kewenangan yang perlu dijaga
     *   Master Akun         admin memindahkan milik ORANG LAIN — menuntut aksi
     *                       SERAH_TERIMA
     *
     * Loker yang bukan milik penyerah ditolak, KECUALI ia memegang SERAH_TERIMA.
     */
    /**
     * GET .../program-kegiatan/opsi/pemegang — calon PIC / penerima serah terima.
     *
     * ══ KENAPA PUNYA RUTE SENDIRI ═══════════════════════════════════════════
     *
     * Halaman ini dulu memanggil /api/v1/master-akun/opsi/penerima, yang dijaga
     * `masterAkunPage,VIEW`. Rekruter yang tidak memegang modul akun karena itu
     * menerima 403 saat membuka pemilih PIC atau panel serah terima — di
     * halaman yang jelas-jelas boleh ia buka, tanpa satu pun penjelasan di
     * layar. Menambal dengan memberi izin Master Akun kepada rekruter jauh
     * lebih berbahaya: itu membuka seluruh modul akun (membuat, menyunting,
     * menonaktifkan akun siapa pun) hanya agar satu dropdown terisi.
     *
     * Jadi rutenya dipisah dan dijaga `programPage` — izin halaman ini sendiri.
     * Kuerinya tetap satu, di App\Support\Career\PenerimaSerahTerima.
     *
     * `untuk=penerima` -> "siapa yang boleh MENERIMA pekerjaan saya"
     * tanpa itu        -> "siapa yang boleh SAYA TUGASKAN memegang program"
     */
    public function opsiPemegang(Request $request)
    {
        try {
            $untukPenerima = $request->query('untuk') === 'penerima';

            return ResponseHelper::success(
                \App\Support\Career\PenerimaSerahTerima::daftar(
                    $request->query('q'),
                    $untukPenerima,
                    // Halamannya TETAP, tidak dibaca dari parameter. Lingkup
                    // disimpan per halaman, dan membiarkan pemanggil menyebut
                    // halaman mana pun berarti ia bisa memilih lingkup yang
                    // paling longgar untuk dirinya sendiri.
                    \App\Support\Career\PenerimaSerahTerima::batas('programPage', $untukPenerima),
                ),
                'Opsi pemegang loker',
            );
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('Gagal memuat opsi pemegang loker: ' . $e->getMessage());

            return ResponseHelper::error('Gagal memuat daftar rekruter', 500);
        }
    }

    public function serahTerima(Request $request)
    {
        try {
            $data = $request->validate([
                'posisiIds' => 'required|array|min:1|max:200',
                'posisiIds.*' => 'required|integer',
                'keKode' => 'required|string|max:20',
                'alasan' => 'required|string|min:5|max:500',
            ], [
                'posisiIds.required' => 'Pilih dulu loker yang akan diserahkan.',
                'keKode.required' => 'Pilih penerima serah terima.',
                'alasan.required' => 'Alasan serah terima wajib diisi.',
                'alasan.min' => 'Alasan terlalu pendek — tuliskan sebabnya.',
            ]);

            $saya = AksesService::kodeKaryawanSaya();
            $bolehLintas = AksesService::boleh('programPage', 'SERAH_TERIMA');

            // Milik siapa loker yang sedang dipindahkan — dihitung SEBELUM
            // apa pun berpindah. Sesudahnya seluruhnya sudah bernama penerima,
            // dan pertanyaan "tadi punya siapa" tidak bisa dijawab lagi.
            $semuaMilikSaya = $saya && ! DB::table('N_WEB_CAREERS_Program_Posisi')
                ->whereIn('Id_Program_Posisi', $data['posisiIds'])
                ->where(fn ($w) => $w->whereNull('Pic_Kode_Karyawan')->orWhere('Pic_Kode_Karyawan', '!=', $saya))
                ->exists();

            if (! $bolehLintas) {
                if (! $saya) {
                    return ResponseHelper::error(
                        'Akun Anda belum ditautkan ke karyawan, jadi belum bisa menyerahkan loker. '
                        .'Minta admin mengisi Kode Karyawan di Master Akun.',
                        403
                    );
                }

                $bukanMilikSaya = DB::table('N_WEB_CAREERS_Program_Posisi')
                    ->whereIn('Id_Program_Posisi', $data['posisiIds'])
                    ->where(fn ($w) => $w->whereNull('Pic_Kode_Karyawan')->orWhere('Pic_Kode_Karyawan', '!=', $saya))
                    ->pluck('Posisi');

                if ($bukanMilikSaya->isNotEmpty()) {
                    return ResponseHelper::error(
                        'Loker berikut bukan tanggung jawab Anda: '.$bukanMilikSaya->take(5)->implode(', ')
                        .'. Hanya loker sendiri yang bisa Anda serahkan.',
                        403
                    );
                }
            }

            $hasil = SerahTerimaPic::jalankan(
                posisiIds: $data['posisiIds'],
                keKode: $data['keKode'],
                alasan: $data['alasan'],
                admin: [
                    'id' => session('career_auth.id'),
                    'nama' => session('career_auth.nama', 'ADMIN'),
                ],
                // Ditandai SENDIRI hanya bila ia benar-benar melepas MILIKNYA.
                //
                // Syaratnya kepemilikan, bukan sekadar punya kode karyawan.
                // Pemeriksaan lama berbunyi `! $bolehLintas || $saya`: begitu
                // seorang admin pemegang SERAH_TERIMA kebetulan juga punya kode
                // karyawan — dan hampir semua punya — perpindahan loker milik
                // ORANG LAIN ikut tercatat "diserahkan sendiri". Riwayat itu
                // dibaca untuk kendali mutu; mencatatnya terbalik membuat
                // tindakan admin menghilang di balik nama rekruternya.
                sumber: $semuaMilikSaya ? SerahTerimaPic::SUMBER_SENDIRI : SerahTerimaPic::SUMBER_ADMIN,
            );

            if (! $hasil['jml']) {
                return ResponseHelper::error(
                    $hasil['dilewati']
                        ? 'Loker itu sudah dipegang orang yang Anda tuju.'
                        : 'Tidak ada loker yang berpindah.',
                    422
                );
            }

            return ResponseHelper::success($hasil, sprintf(
                '%d loker (%d kandidat) diserahkan.', $hasil['jml'], $hasil['kandidat']
            ));
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ResponseHelper::error(collect($e->errors())->flatten()->first() ?? 'Data tidak valid', 422);
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('Gagal serah terima loker: '.$e->getMessage());

            return ResponseHelper::error('Gagal menyerahkan loker', 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $data = $request->validate($this->rules(), $this->pesanValidasi());
            // GERBANG LINGKUP PIC — INILAH PENJAGA YANG SEBENARNYA.
            //
            // Kartu terkunci di layar hanya rupa. Permintaan ini bisa datang
            // dari tab lama, dari layar yang elemennya sudah disunting sendiri,
            // atau langsung tanpa lewat layar sama sekali. Yang menentukan
            // sebuah MPP boleh dipakai adalah baris ini, bukan kartunya.
            if ($galat = $this->galatLingkupPic($data['posisi'] ?? [])) {
                return $galat;
            }

            if ($galat = $this->galatPicProgram($data['picKodeKaryawan'] ?? null)) {
                return $galat;
            }

            if ($galat = $this->galatPicProgram($data['picKodeKaryawan'] ?? null)) {
                return $galat;
            }

            if ($galat = $this->cekKuotaBatch($data)) {
                return ResponseHelper::error($galat, 422);
            }
            $userId = session('career_auth.id');
            $userName = session('career_auth.nama', 'ADMIN');
            $now = now();

            // Kode dibuat memakai lebar kolom penuh; sufiks _N hanya bila bentrok.
            $kode = KodeUnik::buat('N_WEB_CAREERS_Program', 'Kode', $data['nama'], 30, 'PROGRAM');
            DB::transaction(function () use ($data, $kode, $userId, $userName, $now) {
                $id = DB::table('N_WEB_CAREERS_Program')->insertGetId([
                    'Kode' => $kode, 'Nama' => $data['nama'], 'Kategori' => $data['kategori'], 'Warna' => $data['warna'] ?? '#4f46e5',
                    'Mode' => $data['mode'] ?? null, 'Alur_Kode' => $data['alur'] ?? null, 'Jadwal_Kode' => $data['jadwal'] ?? null,
                    // Program baru LANGSUNG berjalan. Admin tidak memilih status saat
                    // membuat — sebuah program yang baru didefinisikan memang dianggap
                    // aktif. DRAFT/SELESAI diatur belakangan lewat toggle / modal ubah.
                    'Penyelenggara' => $data['penyelenggara'] ?? null, 'Status' => 'BERJALAN',
                    'Pic_Kode_Karyawan' => trim((string) ($data['picKodeKaryawan'] ?? '')) ?: null,
                    'Created_At' => $now, 'Created_By' => $userName, 'Created_By_Id' => $userId, 'Updated_At' => $now, 'Updated_By' => $userName, 'Updated_By_Id' => $userId,
                ], 'Id_Program');
                $this->simpanAnak($id, $data, $userId);
            });

            Log::channel('web_career')->info("Program dibuat ({$kode}) oleh {$userName}");

            return ResponseHelper::success(null, 'Program berhasil ditambahkan', 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ResponseHelper::error(collect($e->errors())->flatten()->first() ?? 'Data tidak valid', 422);
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('Gagal membuat program: ' . $e->getMessage());

            return ResponseHelper::error('Gagal menyimpan data', 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $realId = Hashids::decode($id)[0] ?? null;
            $row = DB::table('N_WEB_CAREERS_Program')->where('Id_Program', $realId)->first();
            if (! $row) {
                return ResponseHelper::error('Data tidak ditemukan', 404);
            }
            $data = $request->validate($this->rules(), $this->pesanValidasi());

            // Gerbang yang SAMA dengan store(). Berbeda di antara keduanya berarti
            // apa yang tak bisa dibuat langsung, bisa dibuat dalam dua langkah:
            // simpan tanpa posisi, lalu tambahkan posisinya lewat Ubah.
            if ($galat = $this->galatLingkupPic($data['posisi'] ?? [])) {
                return $galat;
            }
            if ($galat = $this->cekKuotaBatch($data)) {
                return ResponseHelper::error($galat, 422);
            }
            $userId = session('career_auth.id');
            $userName = session('career_auth.nama', 'ADMIN');

            // $row WAJIB ikut di-use: dipakai sebagai nilai jatuhan untuk Warna & Status.
            DB::transaction(function () use ($data, $realId, $userId, $userName, $row) {
                DB::table('N_WEB_CAREERS_Program')->where('Id_Program', $realId)->update([
                    'Nama' => $data['nama'], 'Kategori' => $data['kategori'], 'Warna' => $data['warna'] ?? $row->Warna ?? '#4f46e5',
                    'Mode' => $data['mode'] ?? null, 'Alur_Kode' => $data['alur'] ?? null, 'Jadwal_Kode' => $data['jadwal'] ?? null,
                    // Status boleh tidak dikirim -> pertahankan yang lama.
                    'Penyelenggara' => $data['penyelenggara'] ?? null, 'Status' => $data['status'] ?? $row->Status,
                    'Pic_Kode_Karyawan' => trim((string) ($data['picKodeKaryawan'] ?? '')) ?: null,
                    'Updated_At' => now(), 'Updated_By' => $userName, 'Updated_By_Id' => $userId,
                ]);
                // Angka terisi batch dipungut DULU — dipakai sebagai cadangan
                // bagi batch yang NAMANYA diganti di borang. Batch yang namanya
                // tetap dicocokkan langsung barisnya oleh simpanAnak() dan
                // angkanya tidak pernah disentuh.
                $terisiLama = DB::table('N_WEB_CAREERS_Program_Batch')
                    ->where('Program_Id', $realId)
                    ->pluck('Terisi', 'Nama')
                    ->map(fn ($v) => (int) $v)
                    ->all();

                // hapusAnak() TIDAK dipanggil lagi di sini — lihat catatan
                // panjang di simpanAnak(). Yang masih perlu ditulis ulang utuh
                // hanya SYARAT: ia aturan murni, tidak dirujuk Id-nya dari mana
                // pun, dan menyisakan baris lama berarti syarat yang DICABUT di
                // layar tetap hidup di basis data.
                DB::table('N_WEB_CAREERS_Program_Syarat')->where('Program_Id', $realId)->delete();
                $this->simpanAnak($realId, $data, $userId, $terisiLama);
            });

            // ── ALUR PROGRAM BERGANTI: NASIB ROMBONGAN YANG BERJALAN ─────────
            //
            // Bawaannya TIDAK memindahkan siapa pun — yang sudah berjalan
            // menyelesaikan alur yang ia mulai, dan papannya tetap menggambarkan
            // keduanya (AlurKolom menggabungkan alur yang benar-benar dipakai).
            // Memindahkan adalah pilihan sadar, bukan efek samping menyimpan.
            //
            // DI LUAR transaksi di atas: migrasi menyentuh puluhan baris tahap
            // milik kandidat, dan menahannya di dalam transaksi yang sama dengan
            // penyimpanan program berarti satu kegagalan di sana ikut membatalkan
            // penyimpanan yang sudah benar.
            $migrasi = null;
            $alurBaruKode = trim((string) ($data['alur'] ?? ''));

            // Syarat "alurnya harus berubah" DICABUT — alasannya sama dengan
            // di dampakAlur(): tanpa itu, rombongan yang tertinggal pada
            // penyimpanan sebelumnya tidak akan pernah bisa menyusul.
            // migrasikanProgram() sendiri hanya menyentuh lamaran yang alurnya
            // belum sama, jadi menjalankannya pada alur yang tidak berubah aman
            // — dan pada program yang semua orangnya sudah sealur, ia tidak
            // menyentuh satu baris pun.
            if ($request->input('migrasi') === 'SEMUA'
                && VersiAlur::siap()
                && $alurBaruKode !== '') {
                $alurBaruId = (int) DB::table('N_WEB_CAREERS_Master_Alur')->where('Kode', $alurBaruKode)->value('Id_Master_Alur');
                if ($alurBaruId) {
                    $migrasi = VersiAlur::migrasikanProgram((int) $realId, $alurBaruId, $userId, $userName);
                }
            }

            if ($migrasi && ($migrasi['ikut'] + $migrasi['tidak']) > 0) {
                Log::channel('web_career')->info("Program #{$realId} ganti alur — migrasi {$migrasi['batch']}: {$migrasi['ikut']} ikut, {$migrasi['tidak']} tidak.");

                return ResponseHelper::success(
                    $migrasi,
                    "Program diperbarui. {$migrasi['ikut']} pelamar dipindahkan ke alur baru; {$migrasi['tidak']} tetap menyelesaikan alur lamanya."
                );
            }

            Log::channel('web_career')->info("Program #{$realId} diperbarui");

            return ResponseHelper::success(null, 'Program diperbarui');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ResponseHelper::error(collect($e->errors())->flatten()->first() ?? 'Data tidak valid', 422);
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal update program #{$id}: " . $e->getMessage());

            return ResponseHelper::error('Gagal memperbarui data', 500);
        }
    }

    /**
     * GET .../program-kegiatan/{id}/dampak-alur?alur=KODE
     *
     * Apa yang terjadi pada pelamar program ini bila alurnya diganti ke KODE.
     * Dipanggil layar SEBELUM menyimpan.
     *
     * Mengganti alur program bukan perbuatan kosmetik: pelamar yang sedang
     * berjalan memegang salinan tahap alur LAMA, dan papan seleksinya digambar
     * dari alur baru. Tanpa pratinjau, admin baru tahu berapa orang yang
     * terdampak sesudah menekan Simpan — saat sudah tidak bisa dibatalkan.
     */
    public function dampakAlur(Request $request, $id)
    {
        try {
            $realId = Hashids::decode($id)[0] ?? null;
            $program = $realId ? DB::table('N_WEB_CAREERS_Program')->where('Id_Program', $realId)->first() : null;
            if (! $program) {
                return ResponseHelper::error('Data tidak ditemukan', 404);
            }

            $kodeBaru = trim((string) $request->query('alur', ''));

            // Tanpa alur tujuan, atau tanpa mesin versi, tak ada yang bisa
            // ditanyakan; layar menyimpan langsung seperti biasa.
            if ($kodeBaru === '' || ! VersiAlur::siap()) {
                return ResponseHelper::success(['berdampak' => false], 'Alur tidak berubah');
            }

            // ══ ALUR SAMA BUKAN BERARTI TIDAK ADA APA-APA ═════════════════
            //
            // Dulu pemeriksaan berhenti begitu kode alurnya sama dengan yang
            // sudah tersimpan. Akibatnya satu keadaan menjadi jalan buntu: admin
            // mengganti alur lalu memilih TIDAK memindahkan siapa pun — sesudah
            // itu alur program sudah yang baru, jadi pemeriksaan ini selalu
            // menjawab "tidak berubah", modalnya tidak pernah muncul lagi, dan
            // rombongan yang tertinggal tak punya satu pun pintu untuk menyusul.
            // Mereka terjebak di alur lama selamanya, lengkap dengan tahap yang
            // sudah dihapus dari master.
            //
            // Yang menentukan ada tidaknya pekerjaan bukan berubah/tidaknya
            // kolom Alur_Kode, melainkan ada tidaknya pelamar yang alurnya belum
            // sama dengan alur tujuan — dan itu persis yang dihitung
            // pratinjauProgram() di bawah.
            $alurSama = $kodeBaru === (string) $program->Alur_Kode;

            $alurBaru = DB::table('N_WEB_CAREERS_Master_Alur')->where('Kode', $kodeBaru)->first();
            if (! $alurBaru) {
                return ResponseHelper::error('Alur tujuan tidak ditemukan', 404);
            }

            $hasil = VersiAlur::pratinjauProgram((int) $realId, (int) $alurBaru->Id_Master_Alur);

            // Nol terdampak = tidak ada rombongan berjalan yang perlu diputuskan
            // nasibnya. Menampilkan modal berisi angka nol semuanya hanya
            // menambah satu klik pada pekerjaan yang tidak menyangkut siapa pun.
            if (($hasil['ikut'] + $hasil['tidak']) === 0) {
                return ResponseHelper::success(['berdampak' => false], 'Tidak ada pelamar yang terdampak');
            }

            return ResponseHelper::success([
                'berdampak' => true,
                // Layar memakainya untuk memilih kalimat: mengganti alur, atau
                // menyusulkan rombongan yang tertinggal ke alur yang sudah
                // dipakai program ini.
                'alurSama' => $alurSama,
                'alurLama' => $program->Alur_Kode,
                'alurBaru' => $alurBaru->Nama,
            ] + $hasil, $alurSama ? 'Kandidat yang belum ikut alur ini' : 'Dampak penggantian alur');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal menghitung dampak alur program #{$id}: " . $e->getMessage());

            return ResponseHelper::error('Gagal menghitung dampak', 500);
        }
    }

    public function toggle(Request $request, $id)
    {
        try {
            $realId = Hashids::decode($id)[0] ?? null;
            $aktif = $request->boolean('aktif');
            $terpengaruh = DB::table('N_WEB_CAREERS_Program')->where('Id_Program', $realId)->update([
                'Status' => $aktif ? 'BERJALAN' : 'DRAFT',
                'Updated_At' => now(), 'Updated_By' => session('career_auth.nama', 'ADMIN'), 'Updated_By_Id' => session('career_auth.id'),
            ]);
            if (! $terpengaruh) {
                return ResponseHelper::error('Data tidak ditemukan', 404);
            }
            Log::channel('web_career')->info("Program #{$realId} status " . ($aktif ? 'BERJALAN' : 'DRAFT'));

            return ResponseHelper::success(null, 'Status diperbarui');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal toggle program #{$id}: " . $e->getMessage());

            return ResponseHelper::error('Gagal mengubah status', 500);
        }
    }

    /**
     * GET .../program-kegiatan/{id}/posisi/{posisiId} — isi MPP satu posisi.
     *
     * Panel detail posisi memperlihatkan uraian jabatannya persis seperti di
     * halaman Master MPP. Dibaca lewat izin programPage, bukan masterMppPage —
     * alasannya ada di App\Support\Career\DetailMppLoker.
     */
    public function posisi($id, $posisiId)
    {
        try {
            $realId = Hashids::decode($id)[0] ?? null;
            if (! $realId) {
                return ResponseHelper::error('Data tidak ditemukan', 404);
            }

            // Posisinya HARUS milik program ini. Tanpa pemeriksaan ini, id mana
            // pun yang ditempelkan ke URL akan terbaca isinya — termasuk milik
            // program di kategori yang pengirimnya tidak berhak melihatnya.
            $posisi = DB::table('N_WEB_CAREERS_Program_Posisi')
                ->where('Program_Id', $realId)
                ->where('Id_Program_Posisi', (int) $posisiId)
                ->first();

            if (! $posisi) {
                return ResponseHelper::error('Posisi bukan milik program ini.', 404);
            }

            return ResponseHelper::success([
                'posisi' => [
                    'id' => (int) $posisi->Id_Program_Posisi,
                    'posisi' => $posisi->Posisi,
                    'departemen' => $posisi->Departemen,
                    'lokasi' => $posisi->Lokasi,
                    'level' => $posisi->Level ?? null,
                    'kuota' => (int) $posisi->Kuota,
                    'status' => $posisi->Status,
                    'mppRef' => $posisi->Mpp_Ref ?? null,
                    'picKode' => $posisi->Pic_Kode_Karyawan ?? null,
                ],
                'mpp' => \App\Support\Career\DetailMppLoker::untuk($posisi->Mpp_Ref ?? null),
            ], 'Detail posisi dimuat');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal memuat detail posisi #{$posisiId}: " . $e->getMessage());

            return ResponseHelper::error('Gagal memuat detail posisi', 500);
        }
    }

    public function destroy($id)
    {
        try {
            $realId = Hashids::decode($id)[0] ?? null;
            $row = DB::table('N_WEB_CAREERS_Program')->where('Id_Program', $realId)->first();
            if (! $row) {
                return ResponseHelper::error('Data tidak ditemukan', 404);
            }
            DB::transaction(function () use ($realId) {
                $this->hapusAnak($realId);
                DB::table('N_WEB_CAREERS_Program')->where('Id_Program', $realId)->delete();
            });
            Log::channel('web_career')->info("Program #{$realId} dihapus");

            return ResponseHelper::success(null, 'Program dihapus');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal hapus program #{$id}: " . $e->getMessage());

            return ResponseHelper::error('Gagal menghapus data', 500);
        }
    }
}
