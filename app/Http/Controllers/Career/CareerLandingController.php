<?php

namespace App\Http\Controllers\Career;

use App\Http\Controllers\Controller;
use App\Support\Career\KatalogPrefill;
use App\Support\Seo\Seo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Vinkla\Hashids\Facades\Hashids;

/**
 * WEB CAREER — LANDING PAGE (DB + dummy)
 * -------------------------------------------------------------
 * Halaman karir publik. Sumber UTAMA kini DATABASE (pembukaan program yang
 * terbit & dalam masa berlaku); data dummy lama tetap ditampilkan sebagai
 * pelengkap agar landing tidak kosong selama data nyata belum lengkap.
 * Lihat dbOpenings() / dbMtCards() / dbLowonganCards().
 *
 * Sengaja tanpa middleware/auth agar bisa dibuka bebas.
 * Struktur data mengikuti arsitektur final Web Career:
 *   KEGIATAN (MT|REKRUTMEN) -> LOWONGAN (dari MPP) -> PIPELINE (stage dinamis)
 *
 * Lokasi grup: Palembang (Head Office) & Banyuasin (Pabrik).
 *
 * Route: GET /karir/landing-page  (name: career.landing)
 */
class CareerLandingController extends Controller
{
    private \App\Support\Career\KelayakanLamaran $cekKelayakanLamaran;

    public function __construct()
    {
        $this->cekKelayakanLamaran = new \App\Support\Career\KelayakanLamaran();
    }
    /** Memoisasi pembukaan DB per-request (dipakai landing + detail + apply). */
    private $openingsCache = null;

    /** Memoisasi info divisi (konten landing per divisi) per-request. */
    private $timInfoCache = null;

    /**
     * Memoisasi konten MPP per-request. Kartu rekrutmen DAN kartu posisi MT
     * sama-sama diperkaya dari MPP, dan keduanya dibangun pada satu request
     * yang sama (`semuaLowongan`) — tanpa cache, mppPeta() jalan dua kali.
     */
    private $mppCache = null;

    /** Memoisasi offices() per-request — dipakai props halaman & achievements(). */
    private $officesCache = null;

    public function index(Request $request)
    {
        $lowongan = $this->visibleLowongan();
        $programMt = $this->programMt();

        // Halaman depan adalah halaman berperingkat tertinggi situs ini, dan
        // sebelumnya ia satu-satunya yang tidak membawa data terstruktur apa
        // pun di luar Organization/WebSite.
        //
        // ItemList-nya penting justru DI SINI: inilah halaman yang paling
        // sering dikunjungi perayap, jadi lowongan baru paling cepat
        // ditemukan lewat halaman ini.
        Seo::set([
            'jsonLd' => array_filter([
                $this->daftarLowonganLd($lowongan, $programMt),
            ]),
        ]);

        // Props lowongan/departments/locations DIHAPUS — LandingPage.vue tidak
        // lagi mendeklarasikannya sejak redesign (job list pindah ke /karir/lowongan).
        return Inertia::render('Career/LandingPage', [
            'meta' => $this->meta($lowongan, $programMt),
            'programMt' => $programMt,
            'achievements' => $this->achievements(),
            'offices' => $this->offices(),
            'benefits' => $this->benefits(),
            // HANYA divisi yang sedang membuka lowongan.
            //
            // Beranda adalah etalase, bukan bagan organisasi. Sembilan kartu
            // yang semuanya berbunyi "Belum ada lowongan" mengajari pengunjung
            // bahwa bagian ini tidak perlu dilihat — dan pelajaran itu melekat,
            // termasuk pada hari ketika ada lowongan yang benar-benar dibuka.
            //
            // Struktur organisasi lengkapnya tetap ada di /karir/tim, dan
            // tombol "Lihat semua tim" di kaki bagian ini mengantar ke sana.
            'tim' => $this->timCards(hanyaAdaLowongan: true),
            'heroSlides' => $this->heroSlides(),
            // FAQ dari Master FAQ — hanya yang ditandai admin untuk landing.
            // Seluruh pertanyaan ada di halaman /karir/faq.
            'faq' => (new \App\Support\Career\FaqPublik())->landing(6),
        ]);
    }

    /** /karir/lowongan — halaman KUMPULAN SELURUH lowongan (rekrutmen + MT). */
    public function semuaLowongan()
    {
        $lowongan = $this->visibleLowongan();
        $mt = $this->programMt();

        // ── ItemList: jalan pintas penemuan ──────────────────────────────
        //
        // Google menemukan halaman lewat tautan, dan daftar lowongan di sini
        // dirender Vue — perayap yang tidak menjalankan JavaScript tidak
        // melihat satu pun tautannya. ItemList menyebut seluruh URL detail
        // langsung di HTML mentah, jadi lowongan baru ditemukan dari SATU
        // kunjungan ke halaman ini alih-alih menunggu peta situs dibaca ulang.
        Seo::set([
            'jsonLd' => array_filter([
                $this->daftarLowonganLd($lowongan, $mt),
                \App\Support\Seo\RemahRoti::dari([
                    ['Karier EVO Group', url('/')],
                    ['Semua Lowongan', null],
                ]),
            ]),
        ]);

        return Inertia::render('Career/SemuaLowongan', [
            'lowongan' => $lowongan,
            'programMt' => $mt,
            'offices' => $this->offices(),
            // Kartu tim (Master Info Divisi + rekap lowongan) → sidebar filter
            // divisi & pengelompokan daftar posisi per tim.
            'tim' => $this->timCards(),
        ]);
    }

    /** Halaman auth kandidat — dummy (tanpa DB). Satu halaman, mode login/register. */
    public function login()
    {
        return Inertia::render('Career/Auth', ['mode' => 'login']);
    }

    public function register()
    {
        return Inertia::render('Career/Auth', ['mode' => 'register']);
    }

    /**
     * FORMULIR APPLY kandidat. Wizard STATIS multi-langkah (struktur = data predefined, bukan builder).
     * MT memakai **Form 1** (dari docs/refrences/List Identitas Form Pendaftaran MT.xlsx) saat apply +
     * verifikasi wajah wajib; **Form 2** (identitas tambahan/kontak darurat/kesiapan/dokumen/persetujuan)
     * diisi di tahap lanjut setelah lolos (akses `?form=2`). Rekrutmen umum memakai flow generik.
     */
    public function apply(Request $request, string $id)
    {
        $form = (int) $request->query('form', 1);

        // Id tingkat PROGRAM MT (tanpa posisi) tidak lagi cukup untuk melamar —
        // satu program MT bisa berisi banyak posisi dan lamaran harus tercatat
        // di posisi yang benar. Tautan lama tetap hidup: program berposisi
        // tunggal diteruskan apa adanya, sisanya dipulangkan ke halaman program
        // agar pelamar memilih posisinya lebih dulu.
        $programMt = collect($this->programMt())->firstWhere('id', $id);
        if ($programMt) {
            $posisiProgram = $programMt['posisi'] ?? [];
            if (count($posisiProgram) !== 1) {
                return redirect()->route('career.mt.detail', ['id' => $id]);
            }
            $id = $posisiProgram[0]['id'];
        }

        // Form 2 adalah formulir TAHAP milik lamaran yang sudah ada. Jalur lama
        // merender ApplyForm lalu hanya menyimpannya di sessionStorage, sehingga
        // admin tidak pernah menerima jawaban. Arahkan ke detail lamaran: halaman
        // itu membawa tahapId asli dan mengirim ke /lamaran/tahap/{id}/kirim.
        if ($form === 2) {
            $kartu = $this->cariKartuPosisi($id);
            abort_unless($kartu, 404);

            $userId = (int) session('career_auth.id');
            if (! $userId) {
                return redirect()->route('career.login');
            }

            $posisiId = Hashids::decode($kartu['posisiId'] ?? '')[0] ?? null;
            $lamaranId = $posisiId
                ? DB::table('N_WEB_CAREERS_Lamaran')
                    ->where('Id_Users', $userId)
                    ->where('Program_Posisi_Id', $posisiId)
                    ->orderByDesc('Id_Lamaran')
                    ->value('Id_Lamaran')
                : null;

            if (! $lamaranId) {
                return redirect()->route('career.portal.index')
                    ->with('error', 'Lamaran belum ditemukan. Kirim Form 1 terlebih dahulu.');
            }

            return redirect()->route('career.portal.detail', [
                'id' => Hashids::encode($lamaranId),
            ]);
        }

        // ID asing/kedaluwarsa tidak boleh membuka formulir generik yang seolah
        // berhasil tetapi tidak pernah mempunyai target lamaran di database.
        $kartu = $this->cariKartuPosisi($id);
        abort_unless($kartu, 404);

        // Bentuknya disamakan persis dengan judul di ApplyForm.vue supaya judul
        // tab tidak berganti begitu Vue selesai dimuat.
        Seo::set(['title' => 'Lamar — ' . $kartu['posisi']]);

        return Inertia::render(
            'Career/ApplyForm',
            array_merge($this->layoutShared(), [
                'flow' => $this->applyFlow($id, $form === 2 ? 2 : 1),
            ]),
        );
    }

    private function applyFlow(string $id, int $form = 1): array
    {
        // Ambil data NYATA dari kartu posisi (yang diklik dari landing). Satu
        // pintu untuk lowongan rekrutmen maupun posisi di dalam program MT —
        // yang membedakan hanya `induk`: ada berarti posisi itu milik program MT,
        // sehingga langkah formulir & aturan kelayakan MT yang berlaku.
        $kartu = $this->cariKartuPosisi($id);
        $induk = $kartu['induk'] ?? null;
        $job = [
            'posisi' => $kartu['posisi'],
            'program' => $induk
                ? trim(($induk['batch'] ? $induk['batch'] . ' · ' : '') . $induk['nama'])
                : ($kartu['perusahaan'] ?? 'EVO Group'),
            'kategori' => $kartu['kategori'] ?? ($induk ? 'MT' : 'REKRUTMEN'),
            'lokasi' => $this->lokasiLabel($kartu),
            'pembukaanId' => $kartu['pembukaanId'],
            'posisiId' => $kartu['posisiId'],
        ];
        $isMt = $job['kategori'] === 'MT';

        if ($isMt && $form === 2) {
            $steps = $this->mtForm2Steps();
        } elseif ($isMt) {
            $steps = $this->mtForm1Steps();
        } else {
            $steps = $this->rekrutmenSteps();
        }

        // ── KELAYAKAN (aturan jalur) + status SUDAH-MELAMAR (form read-only) ──
        $userId = (int) session('career_auth.id');

        // Identitas kandidat dari SESI LOGIN (bukan sessionStorage) untuk prefill.
        //
        // No. HP dan NIK dibaca dari tabel pengguna, BUKAN dari sesi: yang
        // disimpan saat login hanya id/nama/email/role/klasifikasi, sehingga
        // `session('career_auth.hp')` selalu null dan kolomnya tampak kosong
        // padahal datanya ada sejak kandidat mendaftar.
        //
        // Bentuknya disaring lewat KatalogPrefill supaya kunci yang ditawarkan
        // ke admin di Master Formulir dan kunci yang benar-benar dikirim ke sini
        // tidak bisa berbeda. `posisi` tidak diisi di sini — ApplyForm.vue
        // menambahkannya dari kartu lowongan yang sedang dibuka.
        $akun = $userId
            ? DB::table('N_WEB_CAREERS_Users')->where('Id_Users', $userId)->first(['No_Hp', 'NIK'])
            : null;

        $kandidat = KatalogPrefill::saring(KatalogPrefill::PENDAFTARAN, [
            'nama' => session('career_auth.nama'),
            'email' => session('career_auth.email'),
            'hp' => $akun->No_Hp ?? null,
            'nik' => $akun->NIK ?? null,
        ]);

        $kelayakan = ['boleh' => true, 'alasan' => null, 'kode' => null];
        $sudahLamar = null;
        if ($userId) {
            $kelayakan = $this->cekKelayakanLamaran->cek($userId, $job['kategori'] ?? null);
            $posEnc = $job['posisiId'] ?? null;
            $posId = $posEnc ? Hashids::decode($posEnc)[0] ?? null : null;
            if ($posId) {
                $lam = DB::table('N_WEB_CAREERS_Lamaran')
                    ->where('Id_Users', $userId)
                    ->where('Program_Posisi_Id', $posId)
                    ->first();
                if ($lam) {
                    $sudahLamar = [
                        'kode' => $lam->Kode,
                        'status' => $lam->Status,
                        'tanggal' => $lam->Waktu_Lamar
                            ? \Illuminate\Support\Carbon::parse($lam->Waktu_Lamar)->translatedFormat('d M Y')
                            : null,
                    ];
                }
            }
        }

        return [
            'lowongan' => array_merge(['id' => $id], $job),
            'form' => $form,
            'steps' => $steps,
            'kandidat' => $kandidat,
            'kelayakan' => $kelayakan,
            'sudahLamar' => $sudahLamar,
            // Tahapan seleksi NYATA milik alur program ini (Master Alur di DB).
            // Dulu halaman apply memakai daftar tahap yang ditulis di berkas JS,
            // jadi kandidat melihat alur karangan — bukan alur yang benar-benar
            // dijalankan. Kosong hanya bila program belum punya alur.
            'pipeline' => $kartu['pipeline'] ?? [],
            // Syarat asli tahap pertama, apa adanya dari Master Program → Syarat.
            'syarat' => $this->syaratTahapPertama($job['pembukaanId'] ?? null),
            // Schema formulir pendaftaran dari Master Formulir versi published.
            // Bila belum ada schema dinamis, frontend fallback ke Komponen_Kode lama.
            'formulir' => $this->formulirPendaftaranPayload($job),
        ];
    }

    private function formulirPendaftaranPayload(array $job): ?array
    {
        $pembukaanId = ! empty($job['pembukaanId'])
            ? (Hashids::decode($job['pembukaanId'])[0] ?? null)
            : null;

        if ($pembukaanId) {
            try {
                $pb = DB::table('N_WEB_CAREERS_Pembukaan')
                    ->where('Id_Pembukaan', $pembukaanId)
                    ->first(['Program_Id']);
                $program = $pb
                    ? DB::table('N_WEB_CAREERS_Program')->where('Id_Program', $pb->Program_Id)->first(['Alur_Kode', 'Kategori'])
                    : null;

                // Sumber yang SAMA dengan pemeriksa berkas di LamaranController::lamar().
                if ($program) {
                    return \App\Support\Career\FormulirSchema::pendaftaranProgram(
                        $program->Alur_Kode ?? null,
                        (string) ($job['kategori'] ?? ''),
                    );
                }
            } catch (\Throwable $e) {
                Log::channel('web_career')->warning('Gagal memuat schema formulir pendaftaran: ' . $e->getMessage());
            }
        }

        return \App\Support\Career\FormulirSchema::pendaftaranUntukKategori((string) ($job['kategori'] ?? ''));
    }

    /**
     * Aturan syarat tahap PERTAMA sebuah pembukaan — dikirim ke formulir apply
     * supaya peringatan di layar memakai aturan yang sama dengan yang dinilai
     * server. Sebelumnya klien memakai daftar tetap di careerSession.js yang
     * tidak ada hubungannya dengan syarat program, sehingga bisa menyatakan
     * "tidak memenuhi syarat" untuk aturan yang sebenarnya tidak dipakai.
     *
     * Keputusan akhir tetap milik server (LamaranService::evaluasiSyarat).
     */
    private function syaratTahapPertama(?string $pembukaanEnc): array
    {
        $pembukaanId = $pembukaanEnc ? (Hashids::decode($pembukaanEnc)[0] ?? null) : null;
        if (! $pembukaanId) {
            return [];
        }

        try {
            $pb = DB::table('N_WEB_CAREERS_Pembukaan')->where('Id_Pembukaan', $pembukaanId)->first(['Program_Id']);
            if (! $pb) {
                return [];
            }

            $program = DB::table('N_WEB_CAREERS_Program')->where('Id_Program', $pb->Program_Id)->first(['Alur_Kode']);
            $tahap1 = DB::table('N_WEB_CAREERS_Master_Alur_Tahap as t')
                ->join('N_WEB_CAREERS_Master_Alur as a', 'a.Id_Master_Alur', '=', 't.Master_Alur_Id')
                ->where('a.Kode', $program->Alur_Kode ?? '')
                ->orderBy('t.Urutan')
                ->first(['t.Id_Master_Alur_Tahap']);
            if (! $tahap1) {
                return [];
            }

            return DB::table('N_WEB_CAREERS_Program_Syarat')
                ->where('Program_Id', $pb->Program_Id)
                ->where('Master_Alur_Tahap_Id', $tahap1->Id_Master_Alur_Tahap)
                ->where('Flag_Aktif', 'Y')
                ->orderBy('Urutan')
                ->get(['Nama', 'Aturan_Json', 'Aksi', 'Pesan_Gugur', 'Flag_Uji'])
                ->map(fn ($s) => [
                    'nama' => $s->Nama,
                    // Hanya GUGUR yang boleh memblokir; TANDAI cuma menandai untuk admin.
                    'aksi' => $s->Aksi,
                    // Mode uji: dihitung tapi tidak boleh memengaruhi kandidat.
                    'uji' => $s->Flag_Uji === 'Y',
                    'pesan' => $s->Pesan_Gugur,
                    'aturan' => json_decode($s->Aturan_Json ?: '{}', true) ?: [],
                ])
                ->values()->all();
        } catch (\Throwable $e) {
            Log::channel('web_career')->warning('Gagal memuat syarat tahap pertama: ' . $e->getMessage());

            return [];
        }
    }

    /**
     * Data katalog publik (tahapan pipeline + jadwal kegiatan + meta program) berdasarkan id.
     * Dipakai Portal Kandidat → halaman Detail Lamaran Terkirim agar tahapan & jadwal
     * SELARAS dengan sumber yang sama dipakai landing/detail (admin-aligned).
     */
    /**
     * "Palembang · On-site" — lokasi penempatan digabung tipe tempat kerja.
     *
     * Sebagian `Program_Posisi.Lokasi` memang diisi tipe tempat kerja (mis.
     * "On-site (WFO)"), sehingga penggabungan mentah menghasilkan
     * "On-site (WFO) · On-site (WFO)". Yang kembar cukup ditulis sekali.
     *
     * Kembaran dari `lokasiLabel()` di resources/js/Pages/Career/careerData.js —
     * kartu memakai versi JS, header formulir & Portal Kandidat memakai yang ini,
     * dan keduanya HARUS memberi hasil sama untuk data yang sama.
     */
    private function lokasiLabel(?array $item): string
    {
        $lokasi = trim((string) ($item['lokasi'] ?? ''));
        $tempat = trim((string) ($item['tempatKerja'] ?? ''));
        if ($lokasi === '' || $tempat === '') {
            return $lokasi ?: ($tempat ?: '—');
        }

        $sama = fn (string $s) => preg_replace('/[^a-z0-9]/', '', strtolower($s));
        if ($sama($lokasi) === $sama($tempat) || str_contains($sama($lokasi), $sama($tempat))) {
            return $lokasi;
        }

        return $lokasi . ' · ' . $tempat;
    }

    public function catalogItem(string $id): ?array
    {
        // Posisi di dalam program MT: namanya adalah nama POSISI yang dilamar,
        // sedangkan jadwal & tahapan tetap milik programnya.
        $kartu = $this->cariKartuPosisi($id);
        if ($kartu && ! empty($kartu['induk'])) {
            $program = collect($this->programMt())->firstWhere('id', $kartu['induk']['id']) ?? [];

            return [
                'id' => $id,
                'kategori' => 'MT',
                'jenis' => 'MT',
                'nama' => $kartu['posisi'],
                'program' => $kartu['induk']['nama'],
                'perusahaan' => $kartu['perusahaan'] ?? 'EVO Group',
                'batch' => $kartu['induk']['batch'] ?? null,
                'departemen' => $kartu['departemen'] ?? null,
                'level' => $kartu['level'] ?? null,
                'lokasi' => $this->lokasiLabel($kartu),
                'ringkasan' => $kartu['ringkasan'] ?? null,
                'deskripsi' => $kartu['deskripsi'] ?? null,
                'benefit' => $kartu['benefit'] ?? [],
                'kriteria' => $kartu['persyaratan'] ?? [],
                'skill' => $kartu['skill'] ?? [],
                'pipeline' => $kartu['pipeline'] ?? [],
                'jadwal' => $program['jadwal'] ?? [],
            ];
        }

        $mt = collect($this->programMt())->firstWhere('id', $id);
        if ($mt) {
            return [
                'id' => $id,
                'kategori' => 'MT',
                'jenis' => 'MT',
                'nama' => $mt['nama'],
                'perusahaan' => $mt['perusahaan'] ?? 'EVO Group',
                'batch' => $mt['batch'] ?? null,
                'lokasi' => $mt['lokasi'] ?? '—',
                'penempatan' => $mt['penempatan'] ?? null,
                // durasi / ikatan / tipeKegiatan / benefit / fasilitas sengaja
                // TIDAK dikirim — lihat catatan di dbMtCards(). Portal kandidat
                // sudah menyembunyikan chip yang nilainya kosong.
                'ringkasan' => $mt['ringkasan'] ?? null,
                'deskripsi' => $mt['deskripsi'] ?? null,
                'kriteria' => $mt['kriteria'] ?? [],
                'tanggalPengumuman' => $mt['tanggalPengumuman'] ?? null,
                'pipeline' => $mt['pipeline'] ?? [],
                'jadwal' => $mt['jadwal'] ?? [],
            ];
        }
        $lo = $kartu; // sisa kemungkinan: lowongan rekrutmen biasa
        if ($lo) {
            return [
                'id' => $id,
                'kategori' => 'REKRUTMEN',
                'jenis' => 'REKRUTMEN',
                'nama' => $lo['posisi'],
                'perusahaan' => $lo['perusahaan'] ?? 'EVO Group',
                'departemen' => $lo['departemen'] ?? null,
                'level' => $lo['level'] ?? null,
                'tipeKerja' => $lo['tipeKerja'] ?? null,
                'lokasi' => $this->lokasiLabel($lo),
                'ringkasan' => $lo['ringkasan'] ?? null,
                'deskripsi' => $lo['deskripsi'] ?? null,
                'benefit' => $lo['benefit'] ?? [],
                'kriteria' => $lo['persyaratan'] ?? [],
                'skill' => $lo['skill'] ?? [],
                'pipeline' => $lo['pipeline'] ?? [],
                'jadwal' => [],
            ];
        }
        return null;
    }

    private function faceStep(): array
    {
        return [
            'key' => 'FACE',
            'tipe' => 'FACE',
            'judul' => 'Verifikasi Wajah',
            'ikon' => 'bi-camera',
            'deskripsi' => 'Ambil satu foto wajah untuk verifikasi identitas — langkah terakhir sebelum finalisasi.',
        ];
    }

    private function reviewStep(): array
    {
        return ['key' => 'REVIEW', 'tipe' => 'REVIEW', 'judul' => 'Review & Finalisasi', 'ikon' => 'bi-send-check'];
    }

    /** Flow generik rekrutmen umum (bukan MT). */
    private function rekrutmenSteps(): array
    {
        return [
            [
                'key' => 'DIRI',
                'tipe' => 'FORM',
                'judul' => 'Data Diri',
                'ikon' => 'bi-person-vcard',
                'fields' => [
                    [
                        'key' => 'nama',
                        'label' => 'Nama Lengkap',
                        'tipe' => 'text',
                        'required' => true,
                        'ph' => 'Sesuai KTP',
                    ],
                    ['key' => 'nik', 'label' => 'NIK', 'tipe' => 'text', 'required' => true, 'ph' => '16 digit'],
                    [
                        'key' => 'jkel',
                        'label' => 'Jenis Kelamin',
                        'tipe' => 'select',
                        'required' => true,
                        'opsi' => ['Laki-laki', 'Perempuan'],
                    ],
                    ['key' => 'lahir', 'label' => 'Tanggal Lahir', 'tipe' => 'date', 'required' => true],
                    [
                        'key' => 'hp',
                        'label' => 'No. HP / WhatsApp',
                        'tipe' => 'phone',
                        'required' => true,
                        'ph' => '628xxxxxxxxx',
                    ],
                    [
                        'key' => 'email',
                        'label' => 'Email',
                        'tipe' => 'text',
                        'required' => true,
                        'ph' => 'nama@email.com',
                    ],
                    [
                        'key' => 'alamat',
                        'label' => 'Alamat Domisili',
                        'tipe' => 'textarea',
                        'required' => true,
                        'full' => true,
                    ],
                ],
            ],
            [
                'key' => 'DIDIK',
                'tipe' => 'FORM',
                'judul' => 'Pendidikan',
                'ikon' => 'bi-mortarboard',
                // CASCADE: Jenjang → Jenis Institusi → Nama Kampus/Sekolah (master).
                'fields' => [
                    ['key' => 'jenjang', 'label' => 'Jenjang Pendidikan', 'tipe' => 'select', 'required' => true, 'sumber_api' => 'jenjang'],
                    ['key' => 'institusi', 'label' => 'Jenis Institusi Pendidikan', 'tipe' => 'select', 'required' => true, 'sumber_api' => 'jenis_institusi', 'tergantung' => 'jenjang'],
                    ['key' => 'kampus', 'label' => 'Nama Kampus / Sekolah', 'tipe' => 'select', 'required' => true, 'sumber_api' => 'kampus', 'cari_async' => true, 'tergantung' => 'institusi', 'full' => true],
                    ['key' => 'jurusan', 'label' => 'Jurusan / Fakultas', 'tipe' => 'select', 'required' => true, 'sumber_api' => 'fakultas', 'tergantung' => 'kampus', 'boleh_ketik' => true],
                    ['key' => 'ipk', 'label' => 'IPK', 'tipe' => 'number', 'required' => true, 'ph' => '3.50', 'tergantung' => 'institusi'],
                    ['key' => 'lulus', 'label' => 'Tahun Lulus', 'tipe' => 'number', 'required' => true, 'ph' => '2024', 'tergantung' => 'institusi'],
                ],
            ],
            [
                'key' => 'KERJA',
                'tipe' => 'FORM',
                'judul' => 'Pengalaman',
                'ikon' => 'bi-briefcase',
                'opsional' => true,
                'repeat' => true,
                'itemLabel' => 'Pengalaman',
                'fields' => [
                    ['key' => 'perusahaan', 'label' => 'Perusahaan / Instansi', 'tipe' => 'text'],
                    ['key' => 'posisiKerja', 'label' => 'Posisi / Jabatan', 'tipe' => 'text'],
                    ['key' => 'mulai', 'label' => 'Tanggal Mulai', 'tipe' => 'date'],
                    ['key' => 'selesai', 'label' => 'Tanggal Selesai', 'tipe' => 'date', 'disableIf' => 'sekarang'],
                    [
                        'key' => 'sekarang',
                        'label' => 'Masih berlangsung sampai sekarang',
                        'tipe' => 'switch',
                        'full' => true,
                    ],
                    [
                        'key' => 'deskripsiKerja',
                        'label' => 'Deskripsi Tugas / Pencapaian',
                        'tipe' => 'textarea',
                        'full' => true,
                    ],
                ],
            ],
            [
                'key' => 'BERKAS',
                'tipe' => 'UPLOAD',
                'judul' => 'Unggah Berkas',
                'ikon' => 'bi-paperclip',
                'files' => [
                    ['key' => 'cv', 'label' => 'CV / Resume', 'required' => true, 'accept' => '.pdf', 'hint' => 'PDF'],
                    [
                        'key' => 'ktp',
                        'label' => 'KTP',
                        'required' => true,
                        'accept' => '.pdf,.jpg,.jpeg,.png',
                        // Petunjuknya menyebut SELURUH yang diterima. Menyebut
                        // sebagian membuat kandidat mengubah berkasnya tanpa
                        // perlu — atau lebih buruk, menyangka berkasnya salah.
                        'hint' => 'PDF / JPG / PNG',
                    ],
                    [
                        'key' => 'ijazah',
                        'label' => 'Ijazah / Transkrip',
                        'required' => true,
                        'accept' => '.pdf',
                        'hint' => 'PDF',
                    ],
                    [
                        'key' => 'pasfoto',
                        'label' => 'Pas Foto',
                        'required' => false,
                        'accept' => '.jpg,.jpeg,.png',
                        'hint' => 'JPG / PNG',
                    ],
                ],
            ],
            [
                'key' => 'SEDIA',
                'tipe' => 'PERNYATAAN',
                'judul' => 'Pernyataan & Kesediaan',
                'ikon' => 'bi-check2-square',
                'items' => [
                    'Data yang saya isi benar & dapat dipertanggungjawabkan',
                    'Bersedia ditempatkan di seluruh unit EVO Group',
                    'Bersedia mengikuti seluruh tahapan seleksi',
                ],
            ],
            $this->faceStep(),
            $this->reviewStep(),
        ];
    }

    /** MT — FORM 1 (pendaftaran awal saat apply). Field mengikuti "Form 1" (varian umum) di Excel. */
    private function mtForm1Steps(): array
    {
        $kampus = [
            'Universitas Sriwijaya',
            'Politeknik Negeri Sriwijaya',
            'Universitas Indonesia',
            'Institut Teknologi Bandung',
            'Universitas Gadjah Mada',
            'IPB University',
            'Universitas Padjadjaran',
            'Institut Teknologi Sepuluh Nopember',
            'Universitas Bina Darma',
            'Lainnya',
        ];

        return [
            [
                'key' => 'DIRI',
                'tipe' => 'FORM',
                'judul' => 'Data Diri',
                'ikon' => 'bi-person-vcard',
                'fields' => [
                    [
                        'key' => 'nama',
                        'label' => 'Nama Lengkap Sesuai KTP',
                        'tipe' => 'text',
                        'required' => true,
                        'ph' => 'Tulis persis seperti tertera di KTP',
                    ],
                    ['key' => 'lahir', 'label' => 'Tanggal Lahir', 'tipe' => 'date', 'required' => true],
                    [
                        'key' => 'jkel',
                        'label' => 'Jenis Kelamin',
                        'tipe' => 'select',
                        'required' => true,
                        'opsi' => ['Laki-Laki', 'Perempuan'],
                    ],
                    [
                        'key' => 'hp',
                        'label' => 'No. Handphone Aktif (WA)',
                        'tipe' => 'phone',
                        'required' => true,
                        'ph' => '628xxxxxxxxx',
                    ],
                    [
                        'key' => 'email',
                        'label' => 'Email',
                        'tipe' => 'text',
                        'required' => true,
                        'ph' => 'nama@email.com',
                    ],
                    [
                        'key' => 'statusMhs',
                        'label' => 'Status Kemahasiswaan',
                        'tipe' => 'select',
                        'required' => true,
                        'opsi' => ['Mahasiswa', 'Sudah Lulus'],
                    ],
                    [
                        'key' => 'semester',
                        'label' => 'Semester saat ini',
                        'tipe' => 'number',
                        'required' => true,
                        'ph' => 'mis. 6',
                        'showIf' => ['key' => 'statusMhs', 'value' => 'Mahasiswa'],
                    ],
                ],
            ],
            [
                'key' => 'DIDIK',
                'tipe' => 'FORM',
                'judul' => 'Pendidikan',
                'ikon' => 'bi-mortarboard',
                // CASCADE: Jenjang → Jenis Institusi → Nama Kampus (opsi dari master
                // via sumber_api; kampus dicari server-side terfilter jenis). Field
                // di bawah kampus baru muncul setelah jenis institusi dipilih.
                'fields' => [
                    ['key' => 'jenjang', 'label' => 'Jenjang Pendidikan', 'tipe' => 'select', 'required' => true, 'sumber_api' => 'jenjang'],
                    ['key' => 'institusi', 'label' => 'Jenis Institusi Pendidikan', 'tipe' => 'select', 'required' => true, 'sumber_api' => 'jenis_institusi', 'tergantung' => 'jenjang'],
                    ['key' => 'kampus', 'label' => 'Nama Kampus / Sekolah', 'tipe' => 'select', 'required' => true, 'sumber_api' => 'kampus', 'cari_async' => true, 'tergantung' => 'institusi', 'full' => true],
                    // Jurusan & Prodi menyusul kampus (bukan jenis institusi):
                    // daftarnya milik kampus itu. 'boleh_ketik' membiarkan
                    // pelamar mengetik sendiri — master prodi tidak akan pernah
                    // lengkap, dan mengunci pilihan bikin orang mentok.
                    ['key' => 'jurusan', 'label' => 'Jurusan / Fakultas', 'tipe' => 'select', 'required' => true, 'sumber_api' => 'fakultas', 'tergantung' => 'kampus', 'boleh_ketik' => true],
                    ['key' => 'prodi', 'label' => 'Program Studi', 'tipe' => 'select', 'required' => true, 'sumber_api' => 'prodi', 'cari_async' => true, 'tergantung' => 'kampus', 'saring_dari' => 'jurusan', 'boleh_ketik' => true],
                    ['key' => 'ipk', 'label' => 'IPK', 'tipe' => 'number', 'required' => true, 'ph' => '3.50', 'tergantung' => 'institusi'],
                    // TAHUN LULUS — kuncinya `lulus`, SAMA dengan formulir
                    // pendaftaran reguler di atas. Formulir MT tidak pernah
                    // menanyakannya, sehingga "Tahun Lulus / Perkiraan Lulus"
                    // di formulir tahap berikutnya tidak punya sumber untuk
                    // diisi otomatis — kandidat diminta mengetik sesuatu yang
                    // seharusnya sudah kita punya.
                    //
                    // Memakai kunci yang sama, bukan kunci baru: dua nama untuk
                    // satu jawaban berarti setiap pembacanya harus hafal
                    // keduanya, dan yang lupa akan membaca kosong.
                    [
                        'key' => 'lulus',
                        'label' => 'Tahun Lulus / Perkiraan Lulus',
                        'tipe' => 'number',
                        'required' => true,
                        'ph' => 'mis. 2026',
                        'tergantung' => 'institusi',
                    ],
                    [
                        // Tanpa 'full' → sebaris dengan IPK (kiri-kanan).
                        'key' => 'bersediaBanyuasin',
                        'label' => 'Bersedia ditempatkan di Pabrik Banyuasin?',
                        'tipe' => 'select',
                        'required' => true,
                        'opsi' => ['Ya', 'Tidak'],
                    ],
                ],
            ],
            $this->faceStep(),
            $this->reviewStep(),
        ];
    }

    /** MT — FORM 2 (identitas tambahan, diisi setelah LOLOS ke tahap berikutnya). Field mengikuti "Form 2" di Excel. */
    private function mtForm2Steps(): array
    {
        $yn = ['Ya', 'Tidak'];

        return [
            [
                'key' => 'VALIDASI',
                'tipe' => 'FORM',
                'judul' => 'Validasi Data Peserta',
                'ikon' => 'bi-clipboard-check',
                'deskripsi' => 'Data ini terisi otomatis dari pendaftaran (Form 1). Konfirmasi kebenarannya.',
                'fields' => [
                    [
                        'key' => 'namaPre',
                        'label' => 'Nama Lengkap',
                        'tipe' => 'text',
                        'readonly' => true,
                        'ph' => '(otomatis dari pendaftaran)',
                    ],
                    [
                        'key' => 'emailPre',
                        'label' => 'Email Terdaftar',
                        'tipe' => 'text',
                        'readonly' => true,
                        'ph' => '(otomatis dari pendaftaran)',
                    ],
                    [
                        'key' => 'waPre',
                        'label' => 'No. WhatsApp Terdaftar',
                        'tipe' => 'text',
                        'readonly' => true,
                        'ph' => '(otomatis dari pendaftaran)',
                    ],
                    [
                        'key' => 'dataSesuai',
                        'label' => 'Apakah data di atas sudah sesuai?',
                        'tipe' => 'select',
                        'required' => true,
                        'opsi' => ['Sesuai', 'Perlu diperbarui'],
                        'full' => true,
                    ],
                    [
                        'key' => 'dataBaru',
                        'label' => 'Tuliskan data yang benar',
                        'tipe' => 'textarea',
                        'full' => true,
                        'showIf' => ['key' => 'dataSesuai', 'value' => 'Perlu diperbarui'],
                    ],
                ],
            ],
            [
                'key' => 'IDENTITAS',
                'tipe' => 'FORM',
                'judul' => 'Identitas Tambahan',
                'ikon' => 'bi-person-lines-fill',
                'fields' => [
                    [
                        'key' => 'alamatKtp',
                        'label' => 'Alamat Lengkap (Sesuai KTP)',
                        'tipe' => 'textarea',
                        'required' => true,
                        'full' => true,
                    ],
                    [
                        'key' => 'alamatDomisili',
                        'label' => 'Alamat Domisili Saat Ini (kosongkan jika sama dengan KTP)',
                        'tipe' => 'textarea',
                        'full' => true,
                    ],
                    [
                        'key' => 'perguruanTinggi',
                        'label' => 'Nama Perguruan Tinggi',
                        'tipe' => 'text',
                        'required' => true,
                    ],
                    [
                        'key' => 'tahunLulus',
                        'label' => 'Tahun Lulus / Perkiraan Lulus',
                        'tipe' => 'text',
                        'required' => true,
                        'ph' => 'mis. 2025',
                    ],
                    [
                        'key' => 'statusKetersediaan',
                        'label' => 'Status Ketersediaan Mengikuti Proses',
                        'tipe' => 'select',
                        'required' => true,
                        'opsi' => ['Siap mengikuti seluruh proses', 'Perlu penyesuaian jadwal'],
                    ],
                    [
                        'key' => 'mulaiKerja',
                        'label' => 'Ketersediaan Mulai Bekerja',
                        'tipe' => 'select',
                        'required' => true,
                        'opsi' => ['Segera', '1 bulan', '2 bulan', '3 bulan'],
                    ],
                ],
            ],
            [
                'key' => 'DARURAT',
                'tipe' => 'FORM',
                'judul' => 'Kontak Darurat',
                'ikon' => 'bi-telephone-plus',
                'fields' => [
                    ['key' => 'namaDarurat', 'label' => 'Nama Kontak Darurat', 'tipe' => 'text', 'required' => true],
                    [
                        'key' => 'hubunganDarurat',
                        'label' => 'Hubungan dengan Peserta',
                        'tipe' => 'text',
                        'required' => true,
                    ],
                    [
                        'key' => 'hpDarurat',
                        'label' => 'No. Handphone Kontak Darurat',
                        'tipe' => 'phone',
                        'required' => true,
                        'ph' => '628xxxxxxxxx',
                    ],
                ],
            ],
            [
                'key' => 'KESIAPAN',
                'tipe' => 'FORM',
                'judul' => 'Kesiapan Penempatan & Kerja',
                'ikon' => 'bi-briefcase',
                'fields' => [
                    [
                        'key' => 'plant',
                        'label' => 'Bersedia ditempatkan di area Plant / Pabrik',
                        'tipe' => 'select',
                        'required' => true,
                        'opsi' => $yn,
                        'full' => true,
                    ],
                    [
                        'key' => 'shift',
                        'label' => 'Bersedia bekerja dengan sistem shift jika dibutuhkan',
                        'tipe' => 'select',
                        'required' => true,
                        'opsi' => $yn,
                        'full' => true,
                    ],
                    [
                        'key' => 'durasiMt',
                        'label' => 'Bersedia mengikuti program MT sesuai durasi & ketentuan',
                        'tipe' => 'select',
                        'required' => true,
                        'opsi' => $yn,
                        'full' => true,
                    ],
                    [
                        'key' => 'ikatanDinas',
                        'label' => 'Bersedia menjalani ikatan dinas 2 tahun jika lulus',
                        'tipe' => 'select',
                        'required' => true,
                        'opsi' => $yn,
                        'full' => true,
                    ],
                    [
                        'key' => 'pengalamanProduksi',
                        'label' => 'Punya pengalaman magang/kerja/praktik di produksi/manufaktur',
                        'tipe' => 'select',
                        'required' => true,
                        'opsi' => $yn,
                        'full' => true,
                    ],
                    [
                        'key' => 'pengalamanJelas',
                        'label' => 'Jika Ya, jelaskan singkat pengalaman tersebut',
                        'tipe' => 'textarea',
                        'full' => true,
                        'showIf' => ['key' => 'pengalamanProduksi', 'value' => 'Ya'],
                    ],
                ],
            ],
            [
                'key' => 'DOKUMEN',
                'tipe' => 'UPLOAD',
                'judul' => 'Kelengkapan Dokumen',
                'ikon' => 'bi-paperclip',
                'files' => [
                    ['key' => 'cv', 'label' => 'CV Terbaru', 'required' => true, 'accept' => '.pdf', 'hint' => 'PDF'],
                    [
                        'key' => 'transkrip',
                        'label' => 'Transkrip Nilai',
                        'required' => true,
                        'accept' => '.pdf',
                        'hint' => 'PDF',
                    ],
                    [
                        'key' => 'ijazah',
                        'label' => 'Ijazah / Surat Keterangan Lulus',
                        'required' => false,
                        'accept' => '.pdf',
                        'hint' => 'PDF',
                    ],
                    [
                        'key' => 'sertifikat',
                        'label' => 'Sertifikat Pendukung (jika ada)',
                        'required' => false,
                        'accept' => '.pdf,.jpg,.jpeg,.png',
                        'hint' => 'PDF / JPG',
                    ],
                ],
            ],
            [
                'key' => 'PERSETUJUAN',
                'tipe' => 'PERNYATAAN',
                'judul' => 'Pernyataan Persetujuan',
                'ikon' => 'bi-check2-square',
                'items' => [
                    'Saya menyatakan seluruh data & dokumen yang saya berikan benar dan dapat dipertanggungjawabkan.',
                    'Saya bersedia mengikuti seluruh tahapan seleksi Management Trainee sesuai ketentuan EVO Group.',
                    'Saya menyetujui penggunaan data pribadi hanya untuk keperluan proses rekrutmen & seleksi.',
                ],
            ],
            $this->reviewStep(),
        ];
    }

    /**
     * schema.org/ItemList untuk halaman daftar lowongan.
     *
     * Dibatasi 100 butir: ItemList raksasa tidak menaikkan apa pun, dan yang
     * ke-101 tetap ditemukan lewat sitemap.xml.
     */
    private function daftarLowonganLd(array $lowongan, array $mt): ?array
    {
        $butir = [];
        $n = 0;

        foreach ($lowongan as $l) {
            if (empty($l['id'])) {
                continue;
            }
            $butir[] = [
                '@type' => 'ListItem',
                'position' => ++$n,
                'url' => route('career.lowongan.detail', ['id' => $l['id']]),
                'name' => (string) ($l['posisi'] ?? ''),
            ];
        }

        foreach ($mt as $m) {
            if (empty($m['id'])) {
                continue;
            }
            $butir[] = [
                '@type' => 'ListItem',
                'position' => ++$n,
                'url' => route('career.mt.detail', ['id' => $m['id']]),
                'name' => (string) ($m['nama'] ?? ''),
            ];
        }

        if (! $butir) {
            return null;
        }

        $butir = array_slice($butir, 0, 100);

        return [
            '@type' => 'ItemList',
            'name' => 'Lowongan Kerja EVO Group',
            'numberOfItems' => count($butir),
            'itemListElement' => $butir,
        ];
    }

    /**
     * Halaman detail lowongan (punya route sendiri, memakai CareerLayout).
     * Melayani lowongan rekrutmen DAN posisi di dalam program MT — keduanya
     * kartu posisi yang sama, hanya yang MT membawa `induk` untuk remah-roti.
     */
    public function showLowongan(string $id)
    {
        $job = $this->cariKartuPosisi($id);
        abort_unless($job, 404);

        // Kartu pratinjau WhatsApp untuk tautan lowongan menyebut POSISI-nya,
        // bukan nama situs. Inilah tautan yang paling sering dibagikan kandidat
        // ke grup teman, jadi ia yang paling layak dapat judul spesifik.
        Seo::set([
            'title' => $job['posisi'],
            'description' => $this->ringkasUntukBagikan($job),
            'type' => 'article',
            // schema.org/JobPosting — yang membuat lowongan ini masuk GOOGLE
            // JOBS, bukan sekadar satu baris biru. Gaji sengaja tidak ikut;
            // lihat alasannya di JobPostingLd.
            'jsonLd' => array_filter([
                \App\Support\Seo\JobPostingLd::dari(
                    $job,
                    url()->current(),
                    (string) config('seo.organization_name', 'EVO Group'),
                    asset((string) config('seo.organization_logo', 'logo/EVOGROUP.png')),
                ),
                // Baris jalur di bawah judul hasil Google. Tanpa ini yang
                // tampil adalah URL mentah berisi hashid — tidak berarti apa
                // pun bagi orang yang sedang memilih satu dari sepuluh hasil.
                \App\Support\Seo\RemahRoti::dari([
                    ['Karier EVO Group', url('/')],
                    ['Lowongan', route('career.lowongan.semua')],
                    [$job['posisi'] ?? 'Lowongan', null],
                ]),
            ]),
        ]);

        return Inertia::render(
            'Career/DetailLowongan',
            array_merge($this->layoutShared(), [
                'lowongan' => $job,
            ]),
        );
    }

    /** Halaman detail Management Trainee (punya route sendiri, memakai CareerLayout). */
    public function showMt(string $id)
    {
        $mt = collect($this->programMt())->firstWhere('id', $id);
        abort_unless($mt, 404);

        Seo::set([
            'title' => $mt['nama'],
            'description' => trim(
                ($mt['tagline'] ?: 'Program Management Trainee EVO Group.')
                . ($mt['batch'] ? ' Batch ' . $mt['batch'] . '.' : '')
                . ($mt['penempatan'] ? ' Penempatan: ' . $mt['penempatan'] . '.' : '')
                . (($mt['jumlahPosisi'] ?? 0) > 0 ? ' ' . $mt['jumlahPosisi'] . ' posisi dibuka.' : ''),
            ),
            'type' => 'article',
            // Program MT dipetakan ke bentuk kartu lowongan lebih dulu: field
            // MT bernama lain (nama/kriteria/penempatan), dan JobPostingLd
            // sengaja cuma mengenal SATU bentuk supaya tidak ada dua aturan
            // yang harus dijaga tetap sama.
            'jsonLd' => array_filter([
                \App\Support\Seo\JobPostingLd::dari(
                [
                    'id' => $mt['id'] ?? null,
                    'posisi' => $mt['nama'] ?? null,
                    'deskripsi' => $mt['deskripsi'] ?? $mt['ringkasan'] ?? $mt['tagline'] ?? null,
                    // Penempatan lebih tepat daripada lokasi kantor: itulah
                    // kota yang dicari pelamar, dan itu pula yang tertulis di
                    // kontraknya kelak.
                    'lokasi' => $mt['penempatan'] ?? $mt['lokasi'] ?? null,
                    'departemen' => 'Management Trainee',
                    'level' => 'Management Trainee',
                    'tipeKerja' => 'Full-time',
                    'tempatKerja' => 'On-site',
                    'persyaratan' => $mt['kriteria'] ?? [],
                    'benefit' => $mt['benefit'] ?? [],
                    'dibuka' => $mt['tanggalBuka'] ?? null,
                    'tanggalTutup' => $mt['tanggalTutup'] ?? null,
                ],
                url()->current(),
                (string) config('seo.organization_name', 'EVO Group'),
                asset((string) config('seo.organization_logo', 'logo/EVOGROUP.png')),
                ),
                \App\Support\Seo\RemahRoti::dari([
                    ['Karier EVO Group', url('/')],
                    ['Management Trainee', route('career.lowongan.semua')],
                    [$mt['nama'] ?? 'Management Trainee', null],
                ]),
            ]),
        ]);

        return Inertia::render(
            'Career/DetailMt',
            array_merge($this->layoutShared(), [
                'programMt' => $mt,
            ]),
        );
    }

    /** Daftar seluruh tim / fungsi perusahaan — data dari Master Info Divisi. */
    public function semuaTim()
    {
        Seo::set([
            'jsonLd' => array_filter([
                \App\Support\Seo\RemahRoti::dari([
                    ['Karier EVO Group', url('/')],
                    ['Fungsi Perusahaan', null],
                ]),
            ]),
        ]);

        return Inertia::render(
            'Career/SemuaTim',
            array_merge($this->layoutShared(), [
                'tim' => $this->timCards(),
            ]),
        );
    }

    /**
     * Halaman perkenalan tim / fungsi perusahaan (bukan lowongan).
     * Konten dari Master Info Divisi (+ sub-divisi & lowongan real per divisi).
     * Bila tabel info masih kosong total, prop `tim` tidak dikirim dan komponen
     * Vue menampilkan konten statis lamanya (fallback agar link tidak mati).
     */
    public function showTim(string $slug = 'it')
    {
        if (! config('career_divisi_guard.enabled', true)) {
            abort(404);
        }

        $info = $this->timInfoRows();

        $divisiId = null;
        $tim = null;
        foreach ($info as $idDiv => $t) {
            if ($t['slug'] === $slug) {
                $divisiId = (int) $idDiv;
                $tim = $t;
                break;
            }
        }

        // Data sudah ada tapi slug tak dikenal → 404. Tabel kosong → fallback statis.
        if ($info && ! $tim) {
            abort(404);
        }

        $props = array_merge($this->layoutShared(), ['slug' => $slug]);

        if ($tim) {
            Seo::set([
                'title' => 'Tim ' . $tim['nama'],
                'description' => $tim['deskripsi'] ?: $tim['deskripsiDetail'] ?: null,
                'type' => 'article',
            ]);

            $jobs = collect($this->dbLowonganCards())
                ->filter(fn ($c) => ($c['timSlug'] ?? null) === $slug)
                ->values();

            $sub = $this->subFungsiDivisi($divisiId);

            $props['tim'] = [
                'slug' => $tim['slug'],
                'nama' => $tim['nama'],
                'deskripsiSingkat' => $tim['deskripsi'],
                'judulUtama' => $tim['judulUtama'],
                'deskripsiDetail' => $tim['deskripsiDetail'],
                'poin' => $tim['poin'],
                'img' => $tim['img'],
                'stats' => [
                    'subFungsi' => count($sub),
                    'lowongan' => $jobs->count(),
                ],
            ];
            $props['subFungsi'] = $sub;
            $props['lowonganTim'] = $jobs->all();

            // Halaman tim adalah pintu masuk untuk pencarian "kerja di bagian
            // produksi Palembang" — ia perlu jalurnya sendiri, dan daftar
            // lowongan timnya supaya perayap menemukan tiap detail dari sini.
            Seo::set([
                'jsonLd' => array_filter([
                    $this->daftarLowonganLd($jobs->all(), []),
                    \App\Support\Seo\RemahRoti::dari([
                        ['Karier EVO Group', url('/')],
                        ['Fungsi Perusahaan', route('career.tim.semua')],
                        ['Tim '.$tim['nama'], null],
                    ]),
                ]),
            ]);
        }

        return Inertia::render('Career/DetailTim', $props);
    }

    /** Sub-divisi milik satu divisi (nama HRIS + info aktif bila sudah diisi). */
    private function subFungsiDivisi(int $divisiId): array
    {
        try {
            return DB::table('HRIS_Divisi_Sub_Divisi as m')
                ->join('HRIS_Sub_Divisi as sd', 'sd.ID_Sub_Divisi', '=', 'm.ID_Sub_Divisi')
                ->leftJoin('N_WEB_CAREERS_Sub_Divisi_Informations as si', function ($j) {
                    $j->on('si.Id_Sub_Divisi', '=', 'sd.ID_Sub_Divisi')->where('si.Flag_Aktif', '=', 'Y');
                })
                ->where('m.ID_Divisi', $divisiId)
                ->orderBy('sd.Keterangan')
                ->select(
                    'sd.ID_Sub_Divisi',
                    'sd.Keterangan',
                    'si.Label_Sub_Div',
                    'si.Deskripsi_Singkat',
                    'si.Img_Path_Header',
                    'si.Updated_At',
                )
                ->get()
                // Mapping bisa memuat sub yang sama dua kali (beda sub-departement).
                ->unique('ID_Sub_Divisi')
                ->map(fn ($r) => [
                    'nama' => $r->Label_Sub_Div ?: $r->Keterangan,
                    'deskripsi' => $r->Deskripsi_Singkat,
                    'img' => $r->Img_Path_Header
                        ? '/karir/tim-img/sub/' . Hashids::encode($r->ID_Sub_Divisi) . '/header?v=' .
                            ($r->Updated_At ? strtotime($r->Updated_At) : 0)
                        : null,
                ])
                ->values()
                ->all();
        } catch (\Throwable $e) {
            // Halaman publik tidak boleh tumbang karena data sub-divisi bermasalah.
            return [];
        }
    }

    /**
     * Info divisi AKTIF (Master Info Divisi) → peta per Id_Divisi.
     * Slug DITURUNKAN dari label (fallback nama HRIS), tidak disimpan di DB —
     * resolusi cukup scan peta ini (±22 divisi). Dimemoisasi per-request.
     */
    private function timInfoRows(): array
    {
        if ($this->timInfoCache !== null) {
            return $this->timInfoCache;
        }

        if (! config('career_divisi_guard.enabled', true)) {
            return $this->timInfoCache = [];
        }

        try {
            $rows = DB::table('N_WEB_CAREERS_Division_Informations as i')
                ->join('HRIS_Divisi as dv', 'dv.ID_Divisi', '=', 'i.Id_Divisi')
                ->where('i.Flag_Aktif', 'Y')
                ->select('i.*', 'dv.Keterangan')
                ->get();

            $this->timInfoCache = $rows
                ->mapWithKeys(function ($r) {
                    $v = $r->Updated_At ? strtotime($r->Updated_At) : 0;
                    $img = fn (string $slot, ?string $path) => $path
                        ? '/karir/tim-img/divisi/' . Hashids::encode($r->Id_Divisi) . '/' . $slot . '?v=' . $v
                        : null;

                    return [
                        (int) $r->Id_Divisi => [
                            'slug' => Str::slug($r->Label_Division ?: $r->Keterangan),
                            'nama' => $r->Label_Division ?: $r->Keterangan,
                            'deskripsi' => $r->Deskripsi_Singkat,
                            'judulUtama' => $r->Judul_Utama,
                            'deskripsiDetail' => $r->Deskripsi_Detail,
                            'poin' => is_string($r->Poin_Keunggulan)
                                ? (json_decode($r->Poin_Keunggulan, true) ?: [])
                                : [],
                            'img' => [
                                'header' => $img('header', $r->Img_Path_Header),
                                'utama' => $img('utama', $r->Img_Path_Utama),
                                'img2' => $img('img2', $r->Img_Path_2),
                                'img3' => $img('img3', $r->Img_Path_3),
                            ],
                        ],
                    ];
                })
                ->all();
        } catch (\Throwable $e) {
            // Landing tidak boleh tumbang karena tabel info divisi bermasalah.
            $this->timInfoCache = [];
        }

        return $this->timInfoCache;
    }

    /**
     * Kartu tim untuk landing (TimSection) & halaman Semua Tim.
     * Rekap lowongan dihitung dari kartu lowongan yang SAMA dengan yang tampil
     * di /karir/lowongan (dbLowonganCards) sehingga angkanya selalu konsisten.
     */
    /**
     * Kartu tim/divisi berikut rekap lowongannya.
     *
     * @param  bool  $hanyaAdaLowongan  Buang divisi yang sedang tidak membuka
     *                                  lowongan sama sekali.
     *
     * Bawaannya FALSE — halaman yang memang bertugas menampilkan seluruh
     * struktur organisasi (SemuaTim, sidebar filter di daftar lowongan) tetap
     * butuh divisi yang sedang kosong: di sanalah pengunjung menelusuri
     * "departemen apa saja yang ada di EVO", bukan "apa yang sedang dibuka".
     *
     * LANDING PAGE memakai TRUE. Alasannya beda tujuan: bagian "Tim yang
     * menjalankan Evo" di beranda adalah etalase, dan etalase yang sembilan
     * dari sembilan kartunya berbunyi "Belum ada lowongan" justru mengajari
     * pengunjung bahwa tidak ada yang perlu dilihat — padahal mungkin ada satu
     * yang sedang membuka.
     */
    private function timCards(bool $hanyaAdaLowongan = false): array
    {
        $info = $this->timInfoRows();
        if (! $info) {
            return [];
        }

        $perSlug = collect($this->dbLowonganCards())
            ->filter(fn ($c) => ! empty($c['timSlug']))
            ->groupBy('timSlug');

        return collect($info)
            ->map(function ($t) use ($perSlug) {
                $jobs = collect($perSlug->get($t['slug'], []));

                return [
                    'slug' => $t['slug'],
                    'nama' => $t['nama'],
                    'deskripsi' => $t['deskripsi'],
                    'img' => $t['img']['header'],
                    'lowongan' => $jobs->count(),
                    // NAMA POSISI yang sedang dibuka — bukan cuma cacahnya.
                    //
                    // "2 lowongan" tidak memberi tahu apa pun tentang apakah
                    // lowongannya relevan bagi yang membaca; "STAFF IT SUPPORT"
                    // memberi tahu seketika. Kartu MT sudah lama memakai pola
                    // ini (lihat MtSection), dan kartu tim ikut menyamakannya.
                    //
                    // Dikirim UTUH, tidak dipotong di sini: layar yang tahu
                    // berapa chip yang muat, dan ia perlu jumlah seluruhnya
                    // untuk menghitung lencana "+N".
                    'posisi' => $jobs->pluck('posisi')->filter()->unique()->values()->all(),
                    // TANPA 'kuota'/'kuotaTerisi' — kartu tim cukup memberi tahu
                    // BERAPA lowongan yang dibuka, bukan berapa kursi tersedia.
                    'pelamar' => (int) $jobs->sum('pelamar'),
                    'skill' => $jobs->flatMap(fn ($j) => $j['skill'] ?? [])->unique()->values()->all(),
                    'lokasi' => $jobs->pluck('lokasi')->filter(fn ($l) => $l && $l !== '—')->unique()->implode(' / ') ?: null,
                    // Tenggat TERDEKAT di antara lowongan divisi ini.
                    //
                    // Yang paling awal ditutup, bukan yang paling akhir: kartu
                    // memberi tahu "kapan kesempatan mulai hilang", dan tanggal
                    // terjauh akan membuat orang mengira masih punya waktu untuk
                    // lowongan yang sebenarnya tutup pekan depan.
                    'tanggalTutup' => $jobs->pluck('tanggalTutup')->filter()->sort()->first(),
                    'tempatKerja' => $jobs->pluck('tempatKerja')->filter()->unique()->implode(' / ') ?: null,
                    'pengalaman' => $jobs->pluck('pengalaman')->filter()->first(),
                    'benefit' => $jobs->flatMap(fn ($j) => $j['benefit'] ?? [])->filter()->unique()->take(3)->implode(' + ') ?: null,
                ];
            })
            // Disaring SESUDAH dipetakan, bukan sebelumnya: jumlah lowongan
            // baru diketahui setelah kartunya dirakit.
            ->when($hanyaAdaLowongan, fn ($c) => $c->filter(fn ($t) => $t['lowongan'] > 0))
            // Divisi yang sedang membuka lowongan tampil lebih dulu.
            ->sortBy([['lowongan', 'desc'], ['nama', 'asc']])
            ->values()
            ->all();
    }

    /**
     * Payload bersama yang dibutuhkan CareerLayout (navbar + footer) di semua halaman.
     * PUBLIC karena halaman publik yang tinggal di controller lain (mis.
     * FaqPublikController) harus memakai sumber yang sama — kalau disalin,
     * navbar/footer antar halaman bisa berbeda isi.
     */
    public function layoutShared(): array
    {
        return [
            'hasMt' => count($this->programMt()) > 0,
            'offices' => $this->offices(),
        ];
    }

    private function meta(array $lowongan, array $programMt): array
    {
        return [
            'brand' => 'EVO Group Career',
            'tagline' => 'Naik level bersama ekosistem people, pet, & manufacturing.',
            'totalLowongan' => count($lowongan),
            'totalDepartemen' => count($this->departments()),
            'totalKota' => count($this->locations()),
            'totalProgramMt' => count($programMt),
        ];
    }

    private function departments(): array
    {
        return [
            'Sales & Distribution',
            'Marketing',
            'Supply Chain & Warehouse',
            'Production',
            'Technology',
            'People & Culture (HR)',
            'Finance & Accounting',
        ];
    }

    /** Hanya 2 lokasi operasional grup. */
    private function locations(): array
    {
        return ['Palembang', 'Banyuasin'];
    }

    /**
     * REKRUTMEN — daftar lowongan (disimulasikan tarikan dari MPP).
     * Head Office (Palembang) untuk fungsi korporat; Pabrik (Banyuasin) untuk produksi.
     */
    /**
     * ATURAN TAMPIL LANDING:
     *  - Lewat tanggal tutup  → HILANG otomatis (di-filter di sini).
     *  - EVERGREEN (tanggalTutup kosong) → tampil terus.
     *
     * Kuota TIDAK ikut menentukan apa pun di sini: landing tidak lagi mengirim
     * angka kursi, jadi tanggal tutup adalah satu-satunya yang menutup lowongan
     * di mata publik.
     */
    /**
     * Cari KARTU POSISI berdasarkan id kartu, di mana pun posisi itu berada.
     *
     * Posisi hidup di dua tempat: langsung sebagai lowongan rekrutmen, atau di
     * dalam `posisi[]` sebuah program MT. Keduanya memakai konvensi id yang sama
     * (`PB-<kode>-<Id_Program_Posisi>`) dan tidak mungkin bertabrakan karena
     * `dbLowonganCards()` melewati program MT. Satu pintu ini dipakai halaman
     * detail, alur lamar, dan katalog portal supaya ketiganya tidak pernah
     * berbeda pendapat tentang posisi mana yang dimaksud.
     */
    /**
     * Deskripsi satu kalimat untuk kartu pratinjau tautan lowongan.
     *
     * Fakta yang paling dicari kandidat ditaruh di DEPAN (tipe kerja, lokasi),
     * karena WhatsApp memotong deskripsi di sekitar dua baris — kalimat
     * pemasaran yang panjang akan terpotong sebelum sampai ke informasinya.
     */
    private function ringkasUntukBagikan(array $job): string
    {
        $fakta = array_values(array_filter([
            $job['tipeKerja'] ?? null,
            ($job['lokasi'] ?? '—') !== '—' ? $job['lokasi'] : null,
            ($job['departemen'] ?? '—') !== '—' ? $job['departemen'] : null,
        ]));

        $depan = $fakta ? implode(' · ', $fakta) . ' di EVO Group.' : 'Lowongan di EVO Group.';
        $isi = trim((string) ($job['ringkasan'] ?? $job['deskripsi'] ?? ''));

        return trim($depan . ' ' . $isi);
    }

    private function cariKartuPosisi(string $id): ?array
    {
        $lo = collect($this->lowongan())->firstWhere('id', $id);
        if ($lo) {
            return $lo;
        }

        foreach ($this->programMt() as $mt) {
            foreach ($mt['posisi'] ?? [] as $p) {
                if ($p['id'] === $id) {
                    return $p;
                }
            }
        }

        return null;
    }

    /**
     * Kartu lowongan + program MT yang layak masuk peta situs.
     *
     * SeoPublikController memakai ini alih-alih menyusun kueri & id-nya
     * sendiri. Sempat begitu, dan akibatnya persis yang bisa diduga: peta
     * situs membangun id dengan Hashids sementara halaman detail memakai
     * bentuk "PB-{kode}-{id}" — ketujuh URL lowongan di sitemap menjawab 404,
     * dan tidak ada yang tahu sampai ada yang benar-benar membukanya satu per
     * satu. Selama id-nya lahir dari SATU tempat, hal itu tidak bisa terulang.
     *
     * @return array<int, array{0: string, 1: string}> [url-relatif, jenis]
     */
    public function petaLowongan(): array
    {
        $out = [];

        foreach ($this->visibleLowongan() as $l) {
            if (! empty($l['id'])) {
                $out[] = [route('career.lowongan.detail', ['id' => $l['id']], false), 'LOWONGAN'];
            }
        }

        foreach ($this->programMt() as $m) {
            if (! empty($m['id'])) {
                $out[] = [route('career.mt.detail', ['id' => $m['id']], false), 'MT'];
            }
        }

        return $out;
    }

    private function visibleLowongan(): array
    {
        $today = now()->toDateString();

        return array_values(
            array_filter($this->lowongan(), function ($l) use ($today) {
                $tutup = $l['tanggalTutup'] ?? null;
                return empty($tutup) || $tutup >= $today; // evergreen ATAU belum lewat tanggal
            }),
        );
    }

    // ═══════════════════════ SUMBER DB (pembukaan nyata) ═══════════════════════

    /**
     * Pembukaan yang TERBIT & masih dalam masa berlaku, plus data programnya.
     * Dimemoisasi per-request supaya landing tidak query berulang.
     */
    private function dbOpenings()
    {
        if ($this->openingsCache !== null) {
            return $this->openingsCache;
        }

        try {
            // Window pendaftaran presisi sampai JAM (kolom kini datetime).
            $kini = now();
            $pembukaan = DB::table('N_WEB_CAREERS_Pembukaan as pb')
                ->join('N_WEB_CAREERS_Program as p', 'p.Id_Program', '=', 'pb.Program_Id')
                ->leftJoin('N_WEB_CAREERS_Program_Batch as b', 'b.Id_Program_Batch', '=', 'pb.Program_Batch_Id')
                ->where('pb.Status_Publish', 'TERBIT')
                ->where('p.Status', 'BERJALAN')
                ->where(function ($q) use ($kini) {
                    $q->where('pb.Masa_Berlaku', 'EVERGREEN')->orWhere(function ($w) use ($kini) {
                        $w->where('pb.Masa_Berlaku', 'BERBATAS')
                            ->where(function ($a) use ($kini) {
                                $a->whereNull('pb.Tanggal_Buka')->orWhere('pb.Tanggal_Buka', '<=', $kini);
                            })
                            ->where(function ($c) use ($kini) {
                                $c->whereNull('pb.Tanggal_Tutup')->orWhere('pb.Tanggal_Tutup', '>=', $kini);
                            });
                    });
                })
                ->orderByDesc('pb.Id_Pembukaan')
                ->select(
                    'pb.*',
                    'p.Nama as ProgramNama',
                    'p.Kategori',
                    'p.Penyelenggara',
                    'p.Alur_Kode',
                    'p.Jadwal_Kode',
                    'b.Nama as BatchNama',
                )
                ->get();

            $ids = $pembukaan->pluck('Program_Id')->unique();
            $posisi = $ids->isEmpty()
                ? collect()
                : DB::table('N_WEB_CAREERS_Program_Posisi')
                    ->whereIn('Program_Id', $ids)
                    ->where('Status', 'BUKA')
                    // ── LOKER YANG SENGAJA DIMATIKAN TIDAK IKUT TERBIT ──────
                    //
                    // Status = kursinya masih ada (dihitung mesin).
                    // Flag_Aktif = kami masih mau memasangnya (diputuskan orang).
                    // Keduanya harus benar; satu saja tidak cukup.
                    //
                    // ISNULL, bukan = 'Y': baris yang dibuat sebelum kolomnya ada
                    // — atau lewat jalur yang belum mengisinya — tidak boleh
                    // menghilang dari landing tanpa ada yang memutuskannya.
                    ->whereRaw("ISNULL(Flag_Aktif, 'Y') = 'Y'")
                    ->get()
                    ->groupBy('Program_Id');

            // JADWAL KEGIATAN nyata: agenda dari Master_Jadwal yang dirujuk program.
            // Wajib DB (bukan dummy) — dikelompokkan per Kode jadwal.
            $jadwalKode = $pembukaan->pluck('Jadwal_Kode')->filter()->unique();
            $agenda = collect();
            if ($jadwalKode->isNotEmpty()) {
                $agenda = DB::table('N_WEB_CAREERS_Master_Jadwal_Agenda as a')
                    ->join('N_WEB_CAREERS_Master_Jadwal as j', 'j.Id_Master_Jadwal', '=', 'a.Master_Jadwal_Id')
                    ->whereIn('j.Kode', $jadwalKode)
                    ->orderBy('a.Urutan')
                    ->select('j.Kode as JadwalKode', 'a.Jenis', 'a.Label', 'a.Tanggal_Mulai', 'a.Tanggal_Selesai')
                    ->get()
                    ->groupBy('JadwalKode');
            }

            // TAHAPAN SELEKSI nyata: tahap dari alur yang dipakai program.
            $alurKode = $pembukaan->pluck('Alur_Kode')->filter()->unique();
            $tahap = collect();
            if ($alurKode->isNotEmpty()) {
                // TAHAP YANG DISEMBUNYIKAN DARI KANDIDAT TIDAK IKUT TERBIT.
                //
                // Penandanya `Tampil_Kandidat` di Master_Alur_Tahap_Tes — admin
                // memakainya untuk tahap yang memang bukan urusan pelamar:
                // Background Check, Reference Check, Negosiasi internal.
                //
                // Portal kandidat sudah lama menghormatinya (lihat
                // LamaranController baris ~8130), tapi HALAMAN LOWONGAN PUBLIK
                // belum: ia menerbitkan seluruh tahap apa adanya. Akibatnya
                // pelamar yang belum melamar pun membaca "5. Background Check"
                // di daftar tahapan — padahal begitu ia melamar, tahap itu tidak
                // pernah muncul di portalnya. Dua layar menceritakan proses
                // seleksi yang berbeda untuk lowongan yang sama.
                //
                // NOT EXISTS, bukan JOIN: satu tahap bisa punya beberapa
                // aktivitas. Tahap disembunyikan hanya bila TIDAK ADA SATU PUN
                // aktivitasnya yang boleh dilihat kandidat; selama masih ada
                // satu yang tampil, tahapnya tetap terbit.
                //
                // Tahap TANPA aktivitas sama sekali tetap terbit (bawaannya
                // tampil) — alur lama banyak yang begitu, dan menyembunyikannya
                // akan mengosongkan daftar tahapan tanpa ada yang meminta.
                $tahap = DB::table('N_WEB_CAREERS_Master_Alur_Tahap as t')
                    ->join('N_WEB_CAREERS_Master_Alur as al', 'al.Id_Master_Alur', '=', 't.Master_Alur_Id')
                    ->whereIn('al.Kode', $alurKode)
                    ->where(function ($q) {
                        $q->whereNotExists(fn ($x) => $x->from('N_WEB_CAREERS_Master_Alur_Tahap_Tes as st')
                            ->whereColumn('st.Master_Alur_Tahap_Id', 't.Id_Master_Alur_Tahap'))
                            ->orWhereExists(fn ($x) => $x->from('N_WEB_CAREERS_Master_Alur_Tahap_Tes as st')
                                ->whereColumn('st.Master_Alur_Tahap_Id', 't.Id_Master_Alur_Tahap')
                                ->where(fn ($w) => $w->whereNull('st.Tampil_Kandidat')->orWhere('st.Tampil_Kandidat', 'Y')));
                    })
                    ->orderBy('t.Urutan')
                    ->select('al.Kode as AlurKode', 't.Label', 't.Tipe_Tahap_Kode', 't.Provider')
                    ->get()
                    ->groupBy('AlurKode');
            }

            // Jumlah pelamar per TERBITAN dan per LOKER di dalamnya — SEMUA
            // lamaran, termasuk yang gugur dan yang mundur. Definisinya sama
            // persis dengan admin (angka di daftar Pembukaan Program dan "Pelamar
            // Masuk" di AnalitikPembukaan): untuk terbitan yang sama, landing dan
            // admin tidak boleh menyebut dua angka berbeda. Dulu landing membuang
            // yang gugur — 608 pelamar MT tampil sebagai 442, seolah programnya
            // kurang diminati. Yang gugur tetap pernah melamar.
            //
            // Kartu POSISI dihitung per posisinya sendiri, bukan per program:
            // program berisi dua loker dulu memajang total program yang sama di
            // kedua kartu, lalu kartu tim menjumlahkannya — terhitung dua kali.
            //
            // TIDAK ADA hitungan kursi terisi di sini. Landing publik tidak lagi
            // memajang kuota apa pun (lihat kartuPosisi & dbMtCards), jadi dua
            // agregat "terisi" yang dulu dihitung di sini ikut hilang — salah
            // satunya bahkan kueri kembar persis dari $pelamar dan tidak pernah
            // dibaca siapa pun. Kuota tetap hidup di sisi internal: gerbangnya
            // ada di LamaranService saat kandidat DITERIMA di tahap terakhir.
            $pelamar = ['terbitan' => collect(), 'posisi' => collect()];
            try {
                $idTerbitan = $pembukaan->pluck('Id_Pembukaan')->all();
                if ($idTerbitan) {
                    $jml = DB::table('N_WEB_CAREERS_Lamaran')
                        ->whereIn('Pembukaan_Id', $idTerbitan)
                        ->select('Pembukaan_Id', 'Program_Posisi_Id', DB::raw('COUNT(*) as Jml'))
                        ->groupBy('Pembukaan_Id', 'Program_Posisi_Id')
                        ->get();
                    $pelamar = [
                        'terbitan' => $jml->groupBy('Pembukaan_Id')->map(fn ($g) => (int) $g->sum('Jml')),
                        'posisi' => $jml->mapWithKeys(fn ($r) => [$r->Pembukaan_Id . ':' . $r->Program_Posisi_Id => (int) $r->Jml]),
                    ];
                }
            } catch (\Throwable $e) {
                $pelamar = ['terbitan' => collect(), 'posisi' => collect()];
            }

            $this->openingsCache = compact(
                'pembukaan',
                'posisi',
                'pelamar',
                'agenda',
                'tahap',
            );
        } catch (\Throwable $e) {
            // Landing publik tidak boleh tumbang hanya karena data DB bermasalah.
            $this->openingsCache = [
                'pembukaan' => collect(),
                'posisi' => collect(),
                'pelamar' => collect(),
                'agenda' => collect(),
                'tahap' => collect(),
            ];
        }

        return $this->openingsCache;
    }

    /**
     * Pembukaan REKRUTMEN/INTERNSHIP -> kartu lowongan (per posisi).
     * Posisi ber-Mpp_Ref DIPERKAYA dari Monitoring MPP (deskripsi, tanggung jawab,
     * persyaratan, skill, benefit, exp level, employment & workplace) — informasi
     * kartu landing = informasi MPP. Nama perusahaan TIDAK ditampilkan (EVO Group).
     */
    public function dbLowonganCards(): array
    {
        $o = $this->dbOpenings();
        $mpp = $this->mppPeta($o['posisi']);
        $out = [];

        foreach ($o['pembukaan'] as $pb) {
            if ($pb->Kategori === 'MT') {
                continue; // MT tampil di section-nya sendiri
            }
            // Tahapan seleksi WAJIB dari DB (alur program). Dipakai kartu detail lowongan.
            $pipeline = $this->shapeTahapan($o['tahap']->get($pb->Alur_Kode, []));

            foreach ($o['posisi']->get($pb->Program_Id, []) as $x) {
                $out[] = $this->kartuPosisi($pb, $x, $mpp[$x->Mpp_Ref ?? ''] ?? null, $pipeline);
            }
        }

        return $out;
    }

    /**
     * Satu baris `Program_Posisi` -> KARTU POSISI.
     *
     * Dipakai kartu rekrutmen (dbLowonganCards) DAN daftar posisi di dalam
     * program MT (dbMtCards). Bentuknya sengaja satu supaya `LowonganCard.vue`
     * dan halaman `DetailLowongan.vue` bisa melayani keduanya tanpa cabang
     * khusus MT — pelamar MT memilih posisi persis seperti pelamar rekrutmen.
     *
     * @param  array|null  $m      konten MPP posisi ini (null bila tak tertaut)
     * @param  array|null  $induk  program MT pemilik posisi (null untuk rekrutmen)
     */
    private function kartuPosisi($pb, $x, ?array $m, array $pipeline, ?array $induk = null): array
    {
        $o = $this->dbOpenings();

        // Employment MPP → label kartu ("Full-time", "Contract / PKWT" → "Contract").
        // Tanpa MPP, label jatuh ke sifat programnya.
        $tipeKerja = $m && $m['employment']
            ? trim(explode('/', $m['employment'])[0])
            : match ($pb->Kategori) {
                'INTERNSHIP' => 'Internship',
                'MT' => 'Management Trainee',
                default => 'Full-time',
            };

        return [
            'id' => 'PB-' . $pb->Kode . '-' . $x->Id_Program_Posisi,
            // ID nyata untuk alur lamaran DB (tombol "Lamar Sekarang").
            'sumberDb' => true,
            'pembukaanId' => Hashids::encode($pb->Id_Pembukaan),
            'posisiId' => Hashids::encode($x->Id_Program_Posisi),
            'kategori' => $pb->Kategori,
            'posisi' => $x->Posisi,
            // Nama perusahaan internal DISEMBUNYIKAN — cukup grup.
            'perusahaan' => 'EVO Group',
            'departemen' => $x->Departemen ?: '—',
            'lokasi' => $x->Lokasi ?: '—',
            'tempatKerja' => $m['workplace'] ?? 'On-site',
            'tipeKerja' => $tipeKerja,
            'level' => $x->Level ?: 'Staff',
            // Exp level MPP ("Min. 1 - 2 Tahun" / "Fresh Graduate"); null → baris disembunyikan.
            'pengalaman' => $m['pengalaman'] ?? null,
            // TANPA 'kuota'/'kuotaTerisi'. Jumlah kursi adalah angka perencanaan
            // internal (MPP): pelamar tidak perlu — dan tidak boleh — menakar
            // peluangnya dari sana, dan angka itu juga bergerak sepanjang seleksi.
            // Kuota tetap ditegakkan di tempat yang memang menentukan, yaitu
            // LamaranService saat kandidat DITERIMA di tahap terakhir.
            'pelamar' => (int) ($o['pelamar']['posisi'][$pb->Id_Pembukaan . ':' . $x->Id_Program_Posisi] ?? 0),
            'tanggalTutup' =>
                $pb->Masa_Berlaku === 'BERBATAS'
                    ? ($pb->Tanggal_Tutup
                        ? substr($pb->Tanggal_Tutup, 0, 16)
                        : null)
                    : null,
            // Kapan lowongan mulai dibuka — dipakai urutan "Terbaru" di
            // halaman daftar. Program_Posisi tidak punya kolom waktu,
            // jadi acuannya tanggal buka pembukaan (fallback: dibuat).
            'dibuka' => $pb->Tanggal_Buka ?: ($pb->Created_At ?: null),
            'deskripsi' =>
                $m['deskripsi'] ??
                'Lowongan ' . $x->Posisi . ' pada program ' . $pb->ProgramNama . ' di EVO Group.',
            'ringkasan' => $m
                ? Str::limit($m['deskripsi'] ?: 'Lowongan ' . $x->Posisi . ' di EVO Group.', 130)
                : 'Lowongan ' . $pb->ProgramNama . ' di EVO Group.',
            // Konten kaya dari MPP; kosong bila posisi tak tertaut MPP.
            'tanggungJawab' => $m['tanggungJawab'] ?? [],
            'persyaratan' => $m['persyaratan'] ?? [],
            'skill' => $m['skill'] ?? [],
            'benefit' => $m['benefit'] ?? [],
            'pipeline' => $pipeline,
            'unggulan' => false,
            // Slug tim/divisi (Master Info Divisi) — Id_Divisi mentah tidak
            // pernah dikirim ke frontend. Null bila posisi tak tertaut MPP
            // atau divisinya belum punya info aktif.
            'timSlug' => $m && ! empty($m['divisiId'])
                ? ($this->timInfoRows()[$m['divisiId']]['slug'] ?? null)
                : null,
            // Program MT pemilik posisi — dipakai remah-roti halaman detail dan
            // penanda kategori pada alur lamar. Null = lowongan rekrutmen biasa.
            'induk' => $induk,
        ];
    }

    /**
     * Peta konten MPP per No_Transaksi untuk seluruh posisi ber-Mpp_Ref —
     * jumlah kueri TETAP (head + points + skill + benefit), bukan per-posisi.
     */
    private function mppPeta($posisiPerProgram): array
    {
        if ($this->mppCache !== null) {
            return $this->mppCache;
        }

        $refs = collect($posisiPerProgram)->flatten(1)->pluck('Mpp_Ref')->filter()->unique()->values();
        if ($refs->isEmpty()) {
            return $this->mppCache = [];
        }

        try {
            $head = DB::table('N_WEB_CAREERS_Detail_MPP as d')
                ->leftJoin('N_WEB_CAREERS_Master_Employment as me', 'me.Id_Employment', '=', 'd.Employment_Type')
                ->leftJoin('N_WEB_CAREERS_Master_Workplace as mw', 'mw.Id_Workplace', '=', 'd.Workplace_Type')
                ->leftJoin(
                    'N_WEB_CAREERS_Master_Experience_Level as mx',
                    'mx.Id_Experience_Level',
                    '=',
                    'd.Experience_Level',
                )
                // Divisi asal posisi (untuk pengelompokan kartu tim di landing).
                ->leftJoin('HRIS_Transaksi_GForm as g', 'g.No_Transaksi', '=', 'd.No_Transaksi_MPP')
                ->whereIn('d.No_Transaksi_MPP', $refs)
                ->select(
                    'd.Id_Detail_MPP',
                    'd.No_Transaksi_MPP',
                    'd.Deskripsi',
                    'me.Nama_Employment',
                    'mw.Nama_Workplace',
                    'mx.Nama_Experience_Level',
                    'g.Id_Divisi',
                )
                ->get();

            $ids = $head->pluck('Id_Detail_MPP');
            $points = DB::table('N_WEB_CAREERS_Points_MPP')
                ->whereIn('Id_Detail_MPP', $ids)
                ->orderBy('Urutan')
                ->get()
                ->groupBy('Id_Detail_MPP');
            $skill = DB::table('N_WEB_CAREERS_Detail_Skill_MPP as sk')
                ->join('N_WEB_CAREERS_Master_Skill as ms', 'ms.Id_Skill', '=', 'sk.Id_Skill')
                ->whereIn('sk.Id_Detail_MPP', $ids)
                ->select('sk.Id_Detail_MPP', 'ms.Nama_Skill')
                ->get()
                ->groupBy('Id_Detail_MPP');
            $benefit = DB::table('N_WEB_CAREERS_Detail_Benefit_MPP as bn')
                ->join('N_WEB_CAREERS_Master_Benefit as mb', 'mb.Id_Benefit', '=', 'bn.Id_Benefit')
                ->whereIn('bn.Id_Detail_MPP', $ids)
                ->select('bn.Id_Detail_MPP', 'mb.Nama_Benefit')
                ->get()
                ->groupBy('Id_Detail_MPP');

            return $this->mppCache = $head
                ->mapWithKeys(function ($h) use ($points, $skill, $benefit) {
                    $p = collect($points->get($h->Id_Detail_MPP, []));

                    return [
                        $h->No_Transaksi_MPP => [
                            'deskripsi' => $h->Deskripsi,
                            'employment' => $h->Nama_Employment,
                            'workplace' => $h->Nama_Workplace,
                            'pengalaman' => $h->Nama_Experience_Level,
                            'divisiId' => $h->Id_Divisi ? (int) $h->Id_Divisi : null,
                            'tanggungJawab' => $p
                                ->where('Section', 'responsibility')
                                ->pluck('Content')
                                ->values()
                                ->all(),
                            'persyaratan' => $p->where('Section', 'requirement')->pluck('Content')->values()->all(),
                            'skill' => collect($skill->get($h->Id_Detail_MPP, []))
                                ->pluck('Nama_Skill')
                                ->values()
                                ->all(),
                            'benefit' => collect($benefit->get($h->Id_Detail_MPP, []))
                                ->pluck('Nama_Benefit')
                                ->values()
                                ->all(),
                        ],
                    ];
                })
                ->all();
        } catch (\Throwable $e) {
            // Landing tidak boleh tumbang karena pengayaan MPP gagal.
            return $this->mppCache = [];
        }
    }

    /**
     * Ubah agenda jadwal DB -> bentuk {label, tanggal} untuk kartu landing.
     * Tanggal MENTAH ikut dikirim (mulai/selesai) supaya sisi Vue bisa menandai
     * agenda yang sudah lewat / sedang berlangsung — string yang sudah diformat
     * tidak bisa dibandingkan.
     */
    private function shapeJadwal($rows): array
    {
        return collect($rows)
            ->map(
                fn($a) => [
                    'label' => $a->Label,
                    'jenis' => $a->Jenis,
                    'tanggal' => $this->rentangTanggal($a->Tanggal_Mulai, $a->Tanggal_Selesai),
                    'mulai' => $a->Tanggal_Mulai ? substr($a->Tanggal_Mulai, 0, 10) : null,
                    'selesai' => $a->Tanggal_Selesai ? substr($a->Tanggal_Selesai, 0, 10) : null,
                ],
            )
            ->values()
            ->all();
    }

    /** Ubah tahap alur DB -> bentuk {label, tipe} untuk pipeline seleksi. */
    private function shapeTahapan($rows): array
    {
        return collect($rows)
            ->map(
                fn($t) => [
                    'label' => $t->Label,
                    'tipe' => $t->Tipe_Tahap_Kode,
                    'provider' => $t->Provider,
                ],
            )
            ->values()
            ->all();
    }

    private function rentangTanggal($mulai, $selesai): string
    {
        $f = fn($d) => $d ? \Illuminate\Support\Carbon::parse($d)->translatedFormat('d M Y') : null;
        $a = $f($mulai);
        $b = $f($selesai);
        if ($a && $b && $a !== $b) {
            return $a . ' – ' . $b;
        }

        return $a ?: ($b ?: '—');
    }

    /**
     * Pembukaan MT -> kartu program MT (satu kartu per pembukaan; channel sudah digabung).
     *
     * Satu program MT menaungi BANYAK posisi dengan MPP, departemen, level, dan
     * kuota masing-masing. Kartu program hanya sampul: isi sebenarnya ada di
     * `posisi[]`, yang bentuknya identik dengan kartu lowongan rekrutmen supaya
     * pelamar memilih posisi lewat komponen & halaman detail yang sama.
     *
     * Kartu program SENGAJA tidak lagi membawa `posisiId`. Dulu kolom itu diisi
     * posisi PERTAMA program, sehingga setiap pelamar MT tercatat di posisi itu
     * apa pun yang sebenarnya dia lamar.
     */
    public function dbMtCards(): array
    {
        $o = $this->dbOpenings();
        $mpp = $this->mppPeta($o['posisi']);
        $out = [];

        foreach ($o['pembukaan'] as $pb) {
            if ($pb->Kategori !== 'MT') {
                continue;
            }
            $listPosisi = collect($o['posisi']->get($pb->Program_Id, []));
            // Jadwal & tahapan WAJIB dari DB. Kosong -> tetap kosong (kartu disembunyikan Vue).
            $jadwal = $this->shapeJadwal($o['agenda']->get($pb->Jadwal_Kode, []));
            $pipeline = $this->shapeTahapan($o['tahap']->get($pb->Alur_Kode, []));

            $induk = [
                'id' => 'PB-' . $pb->Kode,
                'nama' => $pb->ProgramNama,
                'batch' => $pb->BatchNama ?: null,
            ];
            $posisi = $listPosisi
                ->map(fn ($x) => $this->kartuPosisi($pb, $x, $mpp[$x->Mpp_Ref ?? ''] ?? null, $pipeline, $induk))
                ->values()
                ->all();

            $out[] = [
                'id' => 'PB-' . $pb->Kode,
                // ID nyata untuk alur lamaran DB (tombol "Daftar Program").
                'sumberDb' => true,
                'pembukaanId' => Hashids::encode($pb->Id_Pembukaan),
                // Isi program: daftar posisi lengkap + ringkasan jumlahnya.
                'posisi' => $posisi,
                'jumlahPosisi' => count($posisi),
                'nama' => $pb->ProgramNama,
                'tagline' => null ?: 'Program Management Trainee EVO Group.',
                'jenis' => 'MT',
                'batch' => $pb->BatchNama ?: '',
                // Nama perusahaan/tim internal disembunyikan — cukup grup.
                'perusahaan' => 'EVO Group',
                'status' => 'BUKA',
                // TIDAK ADA "Jenis Kegiatan", "Durasi Program", "Ikatan Dinas",
                // "Benefit", dan "Fasilitas" di sini. Dulu kelimanya berupa teks
                // mati yang SAMA untuk seluruh program MT — pelamar membaca janji
                // yang tidak pernah ditetapkan siapa pun dan tidak bisa ditelusuri
                // ke satu baris data pun. Selama belum ada sumbernya di database,
                // halaman program hanya menampilkan yang benar-benar terdata.
                'lokasi' => optional($listPosisi->first())->Lokasi ?: null,
                'penempatan' => $listPosisi->pluck('Lokasi')->filter()->unique()->implode(' & ') ?: null,
                // TANPA 'kuota'/'kuotaTerisi' — sama alasannya dengan kartuPosisi().
                'pelamar' => (int) ($o['pelamar']['terbitan'][$pb->Id_Pembukaan] ?? 0),
                'tanggalBuka' => $pb->Tanggal_Buka ? substr($pb->Tanggal_Buka, 0, 16) : null,
                'tanggalTutup' =>
                    $pb->Masa_Berlaku === 'BERBATAS'
                        ? ($pb->Tanggal_Tutup
                            ? substr($pb->Tanggal_Tutup, 0, 16)
                            : null)
                        : null,
                'tanggalPengumuman' => null,
                // Kampus sasaran tak lagi whitelist per pembukaan — kelayakan kampus
                // ditentukan lewat SYARAT auto-gugur. Kartu "Kampus Sasaran" disembunyikan.
                'targetKampus' => [],
                'ringkasan' => 'Program ' . $pb->ProgramNama . ' untuk calon pemimpin masa depan EVO Group.',
                'deskripsi' => 'Program Management Trainee ' . $pb->ProgramNama . '.',
                'catatanKegiatan' => 'Dibuka untuk umum — pendaftar memilih kampus dari daftar resmi.',
                'kriteria' => [],
                // Jadwal & tahapan WAJIB dari DB — Vue menyembunyikan kartunya bila kosong.
                'jadwal' => $jadwal,
                'pipeline' => $pipeline,
            ];
        }

        return $out;
    }

    /**
     * Lowongan REKRUTMEN yang tampil di landing = DB (pembukaan terbit) + dummy lama.
     * Sumber utama DB; dummy dibiarkan agar landing tetap berisi selagi data nyata
     * belum lengkap. Semua konsumen (landing, detail, apply) membaca dari sini.
     */
    private function lowongan(): array
    {
        // SUMBER TUNGGAL: pembukaan program yang terbit & berlaku. TANPA dummy —
        // kalau tidak ada pembukaan, landing menampilkan kosong (apa adanya).
        return $this->dbLowonganCards();
    }

    private function lowonganDummy(): array
    {
        return [
            [
                'id' => 'RC-2026-001',
                'posisi' => 'Sales Executive (Pet Retail)',
                'perusahaan' => 'PT Evo Nusa Bersaudara',
                'departemen' => 'Sales & Distribution',
                'lokasi' => 'Palembang',
                'tempatKerja' => 'Head Office',
                'tipeKerja' => 'Full-time',
                'level' => 'Staff',
                'pengalaman' => 'Min. 1 tahun',
                'kuota' => 4,
                'kuotaTerisi' => 1,
                'pelamar' => 37,
                'tanggalTutup' => '2026-08-15',
                'unggulan' => true,
                'ringkasan' =>
                    'Menjadi ujung tombak penjualan produk pet food premium ke jaringan retail dan pet shop modern.',
                'skill' => ['Negosiasi', 'Relationship', 'Target Oriented', 'MS Office'],
                'deskripsi' =>
                    'Sebagai Sales Executive, Anda bertanggung jawab mengembangkan penjualan produk Evopet (Life Cat, Ori Cat, Life Dog) di area yang ditentukan, membangun hubungan dengan mitra retail, serta memastikan pencapaian target penjualan bulanan.',
                'tanggungJawab' => [
                    'Mencapai target penjualan bulanan sesuai area yang ditetapkan.',
                    'Membangun & memelihara hubungan baik dengan pet shop dan retailer.',
                    'Melakukan kunjungan rutin serta merchandising produk di toko.',
                    'Menyusun laporan penjualan dan aktivitas kompetitor.',
                ],
                'persyaratan' => [
                    'Pendidikan min. D3/S1 semua jurusan.',
                    'Pengalaman min. 1 tahun di bidang sales (fresh graduate berprestasi dipertimbangkan).',
                    'Memiliki SIM C dan bersedia mobilitas tinggi.',
                    'Komunikatif, ulet, dan berorientasi target.',
                ],
                'benefit' => [
                    'Gaji pokok + komisi',
                    'Tunjangan transport',
                    'BPJS Kesehatan & Ketenagakerjaan',
                    'Jenjang karir jelas',
                ],
                'pipeline' => [
                    ['tipe' => 'FORM', 'label' => 'Lamaran & Screening CV'],
                    ['tipe' => 'HCLEARN_TEST', 'label' => 'Psikotes Online'],
                    ['tipe' => 'INTERVIEW', 'label' => 'Interview HR'],
                    ['tipe' => 'INTERVIEW', 'label' => 'Interview User'],
                    ['tipe' => 'OFFERING', 'label' => 'Penawaran & Onboarding'],
                ],
            ],
            [
                'id' => 'RC-2026-002',
                'posisi' => 'Fullstack Web Developer',
                'perusahaan' => 'PT Evo Nusa Bersaudara',
                'departemen' => 'Technology',
                'lokasi' => 'Palembang',
                'tempatKerja' => 'Head Office',
                'tipeKerja' => 'Full-time',
                'level' => 'Mid',
                'pengalaman' => 'Min. 2 tahun',
                'kuota' => 2,
                'kuotaTerisi' => 0,
                'pelamar' => 58,
                'tanggalTutup' => '2026-08-30',
                'unggulan' => true,
                'ringkasan' =>
                    'Membangun & memelihara platform internal HCIS serta sistem operasional grup berbasis Laravel + Vue.',
                'skill' => ['Laravel', 'Vue.js', 'MySQL/MSSQL', 'REST API', 'Git'],
                'deskripsi' =>
                    'Bergabung dengan tim Technology untuk mengembangkan produk digital internal, mulai dari HCIS, sistem KPI, hingga platform karir. Anda akan bekerja end-to-end dari perancangan hingga deployment.',
                'tanggungJawab' => [
                    'Mengembangkan fitur baru pada aplikasi internal (Laravel + Inertia + Vue).',
                    'Menulis kode yang bersih, teruji, dan mudah dipelihara.',
                    'Berkolaborasi dengan tim produk & QA dalam siklus pengembangan.',
                    'Melakukan optimasi performa dan perbaikan bug.',
                ],
                'persyaratan' => [
                    'S1 Teknik Informatika / setara.',
                    'Pengalaman min. 2 tahun dengan PHP (Laravel) dan JavaScript framework.',
                    'Paham konsep REST API, database relasional, dan Git flow.',
                    'Mampu bekerja mandiri maupun tim.',
                ],
                'benefit' => [
                    'Gaji kompetitif',
                    'Remote/Hybrid friendly',
                    'Perangkat kerja disediakan',
                    'Budget pengembangan skill',
                ],
                'pipeline' => [
                    ['tipe' => 'FORM', 'label' => 'Lamaran & Screening CV'],
                    ['tipe' => 'HCLEARN_TEST', 'label' => 'Technical Test'],
                    ['tipe' => 'INTERVIEW', 'label' => 'Interview Teknis'],
                    ['tipe' => 'INTERVIEW', 'label' => 'Interview Culture-fit'],
                    ['tipe' => 'OFFERING', 'label' => 'Penawaran & Onboarding'],
                ],
            ],
            [
                'id' => 'RC-2026-003',
                'posisi' => 'Digital Marketing Specialist',
                'perusahaan' => 'PT Evo Nusa Bersaudara',
                'departemen' => 'Marketing',
                'lokasi' => 'Palembang',
                'tempatKerja' => 'Head Office',
                'tipeKerja' => 'Full-time',
                'level' => 'Staff',
                'pengalaman' => 'Min. 1 tahun',
                'kuota' => 2,
                'kuotaTerisi' => 1,
                'pelamar' => 44,
                'tanggalTutup' => '2026-08-20',
                'unggulan' => false,
                'ringkasan' =>
                    'Merancang & mengeksekusi kampanye digital untuk brand pet food Evopet di berbagai kanal.',
                'skill' => ['Meta Ads', 'Google Ads', 'Copywriting', 'Analytics', 'Content Planning'],
                'deskripsi' =>
                    'Anda akan mengelola performa kampanye digital, meningkatkan brand awareness, dan mendorong penjualan online untuk portofolio brand Evopet.',
                'tanggungJawab' => [
                    'Merencanakan & mengeksekusi kampanye di Meta, Google, dan marketplace.',
                    'Menganalisis performa kampanye dan menyusun laporan.',
                    'Berkolaborasi dengan tim konten dan desain.',
                    'Mengelola anggaran iklan agar efisien.',
                ],
                'persyaratan' => [
                    'S1 Marketing / Komunikasi / setara.',
                    'Pengalaman mengelola paid ads min. 1 tahun.',
                    'Menguasai tools analytics dan reporting.',
                    'Kreatif, data-driven, dan up-to-date dengan tren digital.',
                ],
                'benefit' => ['Gaji + bonus performa', 'BPJS lengkap', 'Lingkungan kreatif', 'Pelatihan digital rutin'],
                'pipeline' => [
                    ['tipe' => 'FORM', 'label' => 'Lamaran & Screening CV'],
                    ['tipe' => 'HCLEARN_TEST', 'label' => 'Studi Kasus Marketing'],
                    ['tipe' => 'INTERVIEW', 'label' => 'Interview User'],
                    ['tipe' => 'OFFERING', 'label' => 'Penawaran & Onboarding'],
                ],
            ],
            [
                'id' => 'RC-2026-004',
                'posisi' => 'Warehouse Supervisor',
                'perusahaan' => 'PT Evo Manufacturing Indonesia',
                'departemen' => 'Supply Chain & Warehouse',
                'lokasi' => 'Banyuasin',
                'tempatKerja' => 'Pabrik',
                'tipeKerja' => 'Full-time',
                'level' => 'Supervisor',
                'pengalaman' => 'Min. 3 tahun',
                'kuota' => 1,
                'kuotaTerisi' => 1,
                'pelamar' => 21,
                'tanggalTutup' => '2026-09-05',
                'unggulan' => false,
                'ringkasan' =>
                    'Memimpin operasional gudang, memastikan akurasi stok, dan efisiensi alur keluar-masuk barang.',
                'skill' => ['WMS', 'Inventory Control', 'Leadership', 'K3', 'Reporting'],
                'deskripsi' =>
                    'Mengelola tim gudang untuk memastikan penerimaan, penyimpanan, dan pengiriman barang berjalan akurat, aman, dan tepat waktu.',
                'tanggungJawab' => [
                    'Mengawasi operasional harian gudang dan tim.',
                    'Menjaga akurasi stok melalui stock opname berkala.',
                    'Memastikan penerapan standar K3 di area gudang.',
                    'Menyusun laporan operasional gudang.',
                ],
                'persyaratan' => [
                    'D3/S1 semua jurusan.',
                    'Pengalaman min. 3 tahun di operasional gudang, min. 1 tahun sebagai supervisor.',
                    'Menguasai sistem WMS dan Ms. Excel.',
                    'Tegas, teliti, dan mampu memimpin tim.',
                ],
                'benefit' => ['Tunjangan jabatan', 'BPJS lengkap', 'Uang makan & shift', 'Jenjang karir'],
                'pipeline' => [
                    ['tipe' => 'FORM', 'label' => 'Lamaran & Screening CV'],
                    ['tipe' => 'HCLEARN_TEST', 'label' => 'Psikotes Online'],
                    ['tipe' => 'INTERVIEW', 'label' => 'Interview User'],
                    ['tipe' => 'INTERVIEW', 'label' => 'Interview Manajemen'],
                    ['tipe' => 'OFFERING', 'label' => 'Penawaran & Onboarding'],
                ],
            ],
            [
                'id' => 'RC-2026-005',
                'posisi' => 'Production Quality Analyst',
                'perusahaan' => 'PT Evo Manufacturing Indonesia',
                'departemen' => 'Production',
                'lokasi' => 'Banyuasin',
                'tempatKerja' => 'Pabrik',
                'tipeKerja' => 'Full-time',
                'level' => 'Staff',
                'pengalaman' => 'Fresh graduate welcome',
                'kuota' => 3,
                'kuotaTerisi' => 2,
                'pelamar' => 29,
                'tanggalTutup' => '2026-08-25',
                'unggulan' => false,
                'ringkasan' =>
                    'Menjaga standar mutu produk pet food sepanjang proses produksi melalui pengujian & kontrol kualitas.',
                'skill' => ['QC/QA', 'GMP', 'Analisis Lab', 'HACCP', 'Dokumentasi'],
                'deskripsi' =>
                    'Bertanggung jawab memastikan setiap batch produksi memenuhi standar mutu dan keamanan pangan sebelum didistribusikan.',
                'tanggungJawab' => [
                    'Melakukan pengujian mutu bahan baku dan produk jadi.',
                    'Menerapkan standar GMP dan HACCP di lini produksi.',
                    'Mendokumentasikan hasil pengujian dan tindakan korektif.',
                    'Berkoordinasi dengan tim produksi terkait temuan mutu.',
                ],
                'persyaratan' => [
                    'S1 Teknologi Pangan / Kimia / Biologi.',
                    'Fresh graduate dipersilakan; pengalaman QC nilai plus.',
                    'Memahami dasar GMP, HACCP, dan analisis laboratorium.',
                    'Teliti, jujur, dan disiplin.',
                ],
                'benefit' => ['Gaji pokok + tunjangan', 'BPJS lengkap', 'Uang shift', 'Pelatihan mutu'],
                'pipeline' => [
                    ['tipe' => 'FORM', 'label' => 'Lamaran & Screening CV'],
                    ['tipe' => 'HCLEARN_TEST', 'label' => 'Tes Teknis & Psikotes'],
                    ['tipe' => 'INTERVIEW', 'label' => 'Interview User'],
                    ['tipe' => 'OFFERING', 'label' => 'Penawaran & Onboarding'],
                ],
            ],
            [
                'id' => 'RC-2026-006',
                'posisi' => 'HR Generalist',
                'perusahaan' => 'PT Graha Maju Nusantara',
                'departemen' => 'People & Culture (HR)',
                'lokasi' => 'Palembang',
                'tempatKerja' => 'Head Office',
                'tipeKerja' => 'Full-time',
                'level' => 'Staff',
                'pengalaman' => 'Min. 2 tahun',
                'kuota' => 1,
                'kuotaTerisi' => 0,
                'pelamar' => 33,
                'tanggalTutup' => null, // EVERGREEN — tanpa batas waktu, di-share terus
                'unggulan' => false,
                'ringkasan' => 'Menangani siklus HR end-to-end: rekrutmen, administrasi, hingga employee engagement.',
                'skill' => ['Recruitment', 'Payroll', 'UU Ketenagakerjaan', 'People Skills', 'HRIS'],
                'deskripsi' =>
                    'Menjadi mitra bisnis HR yang mendukung operasional people di entitas grup, dari hiring hingga pengembangan karyawan.',
                'tanggungJawab' => [
                    'Mengelola proses rekrutmen dan onboarding karyawan.',
                    'Menangani administrasi kepegawaian dan payroll dasar.',
                    'Mendukung program engagement dan pengembangan karyawan.',
                    'Memastikan kepatuhan terhadap regulasi ketenagakerjaan.',
                ],
                'persyaratan' => [
                    'S1 Psikologi / Manajemen SDM / Hukum.',
                    'Pengalaman min. 2 tahun sebagai HR generalist.',
                    'Memahami UU Ketenagakerjaan terbaru.',
                    'Empatik, rapi, dan dapat menjaga kerahasiaan.',
                ],
                'benefit' => ['Gaji kompetitif', 'BPJS lengkap', 'Cuti sesuai regulasi', 'Lingkungan suportif'],
                'pipeline' => [
                    ['tipe' => 'FORM', 'label' => 'Lamaran & Screening CV'],
                    ['tipe' => 'HCLEARN_TEST', 'label' => 'Psikotes Online'],
                    ['tipe' => 'INTERVIEW', 'label' => 'Interview HR'],
                    ['tipe' => 'INTERVIEW', 'label' => 'Interview Manajemen'],
                    ['tipe' => 'OFFERING', 'label' => 'Penawaran & Onboarding'],
                ],
            ],
            [
                'id' => 'RC-2026-007',
                'posisi' => 'Finance & Accounting Staff',
                'perusahaan' => 'PT Evo Nusa Bersaudara',
                'departemen' => 'Finance & Accounting',
                'lokasi' => 'Palembang',
                'tempatKerja' => 'Head Office',
                'tipeKerja' => 'Full-time',
                'level' => 'Staff',
                'pengalaman' => 'Min. 1 tahun',
                'kuota' => 2,
                'kuotaTerisi' => 1,
                'pelamar' => 40,
                'tanggalTutup' => '2026-09-01',
                'unggulan' => false,
                'ringkasan' => 'Mengelola pencatatan transaksi keuangan, rekonsiliasi, dan pelaporan pajak dasar.',
                'skill' => ['Accounting', 'Pajak', 'Excel', 'Accurate/SAP', 'Ketelitian'],
                'deskripsi' =>
                    'Mendukung operasional keuangan perusahaan dengan memastikan pencatatan yang akurat dan pelaporan yang tepat waktu.',
                'tanggungJawab' => [
                    'Mencatat dan memverifikasi transaksi keuangan harian.',
                    'Melakukan rekonsiliasi bank dan buku besar.',
                    'Menyiapkan dokumen perpajakan bulanan.',
                    'Mendukung penyusunan laporan keuangan.',
                ],
                'persyaratan' => [
                    'S1 Akuntansi.',
                    'Pengalaman min. 1 tahun di bidang accounting/tax.',
                    'Menguasai Ms. Excel dan software akuntansi.',
                    'Teliti, jujur, dan disiplin waktu.',
                ],
                'benefit' => ['Gaji + THR', 'BPJS lengkap', 'Pelatihan pajak', 'Jenjang karir'],
                'pipeline' => [
                    ['tipe' => 'FORM', 'label' => 'Lamaran & Screening CV'],
                    ['tipe' => 'HCLEARN_TEST', 'label' => 'Tes Teknis Akuntansi'],
                    ['tipe' => 'INTERVIEW', 'label' => 'Interview User'],
                    ['tipe' => 'OFFERING', 'label' => 'Penawaran & Onboarding'],
                ],
            ],
            [
                'id' => 'RC-2026-008',
                'posisi' => 'Content Creator & Social Media (Internship)',
                'perusahaan' => 'PT Evo Nusa Bersaudara',
                'departemen' => 'Marketing',
                'lokasi' => 'Palembang',
                'tempatKerja' => 'Head Office',
                'tipeKerja' => 'Internship',
                'level' => 'Internship',
                'pengalaman' => 'Fresh graduate / Mahasiswa',
                'kuota' => 3,
                'kuotaTerisi' => 3,
                'pelamar' => 62,
                'tanggalTutup' => '2026-06-25', // sudah lewat tanggal → otomatis hilang dari landing
                'unggulan' => false,
                'ringkasan' => 'Membuat konten kreatif seputar dunia pet untuk media sosial brand Evopet.',
                'skill' => ['Content Creation', 'Video Editing', 'Canva', 'Storytelling', 'Kreativitas'],
                'deskripsi' =>
                    'Program magang untuk kamu yang suka dunia hewan peliharaan dan kreatif membuat konten. Kamu akan terlibat langsung dalam produksi konten harian.',
                'tanggungJawab' => [
                    'Membuat konten foto/video untuk Instagram & TikTok.',
                    'Menyusun ide kampanye konten mingguan.',
                    'Membantu menjawab interaksi audiens.',
                    'Riset tren konten pet & kompetitor.',
                ],
                'persyaratan' => [
                    'Mahasiswa tingkat akhir / fresh graduate.',
                    'Menguasai dasar editing video & desain (Canva/CapCut).',
                    'Aktif di media sosial dan paham tren.',
                    'Menyukai hewan peliharaan jadi nilai plus.',
                ],
                'benefit' => [
                    'Uang saku magang',
                    'Sertifikat magang',
                    'Mentoring langsung',
                    'Peluang jadi karyawan tetap',
                ],
                'pipeline' => [
                    ['tipe' => 'FORM', 'label' => 'Lamaran & Portfolio'],
                    ['tipe' => 'HCLEARN_TEST', 'label' => 'Tugas Kreatif'],
                    ['tipe' => 'INTERVIEW', 'label' => 'Interview User'],
                    ['tipe' => 'OFFERING', 'label' => 'Penawaran Magang'],
                ],
            ],
        ];
    }

    /**
     * MANAGEMENT TRAINEE — "Kegiatan" ber-branding (Career_Kegiatan jenis MT).
     * Bersifat DINAMIS: bila array kosong, section MT + menu navbar otomatis hilang.
     *
     * Contoh dua model kegiatan:
     *  - EDP  : kegiatan khusus KAMPUS TERPILIH (by invitation), status BUKA.
     *  - STP  : kegiatan UMUM/terbuka, status PENUH (kuota sudah terisi) — contoh state penuh.
     */
    /**
     * Program MT yang tampil di landing = DB (pembukaan MT terbit) + dummy lama.
     */
    private function programMt(): array
    {
        // SUMBER TUNGGAL: pembukaan program MT yang terbit & berlaku. TANPA dummy.
        return $this->dbMtCards();
    }

    private function programMtDummy(): array
    {
        return [
            [
                'id' => 'MT-2026-EDP',
                'nama' => 'EVO Development Program (EDP) 2026',
                'tagline' => 'Kaderisasi calon pemimpin masa depan EVO Group.',
                'jenis' => 'MT',
                'batch' => 'Batch 5',
                'perusahaan' => 'EVO Group',
                'status' => 'BUKA', // BUKA | PENUH | SEGERA
                'tipeKegiatan' => 'Kampus Terpilih (By Invitation)',
                'lokasi' => 'Palembang (Head Office)',
                'penempatan' => 'Palembang & Banyuasin',
                'durasi' => '12 bulan program akselerasi',
                'ikatan' => 'Ikatan dinas 2 tahun',
                'kuota' => 10,
                'kuotaTerisi' => 3,
                'pelamar' => 214,
                'tanggalBuka' => '2026-07-01',
                'tanggalTutup' => '2026-08-31',
                'tanggalPengumuman' => '2026-09-20',
                'targetKampus' => ['ITB', 'UI', 'UGM', 'IPB', 'Unpad', 'ITS', 'Unsri'],
                'ringkasan' =>
                    'Kegiatan pengembangan intensif 12 bulan untuk lulusan terbaik dari kampus mitra, disiapkan menjadi future leader di lini bisnis EVO Group.',
                'deskripsi' =>
                    'EVO Development Program (EDP) adalah kegiatan Management Trainee unggulan yang dibuka secara khusus untuk kampus mitra terpilih. Peserta menjalani rotasi lintas divisi, mentoring langsung dari BOD, serta proyek nyata berdampak bisnis sebelum ditempatkan pada posisi manajerial.',
                'catatanKegiatan' =>
                    'Kegiatan ini dibuka melalui jalur undangan ke kampus mitra. Pendaftaran umum akan diverifikasi terhadap daftar kampus terpilih.',
                'benefit' => [
                    'Gaji & tunjangan kompetitif sejak hari pertama',
                    'Rotasi lintas divisi & lintas entitas grup',
                    'Mentoring langsung dari jajaran Direksi',
                    'Fast-track ke posisi manajerial',
                    'Sertifikat program kepemimpinan',
                ],
                'kriteria' => [
                    'Fresh graduate S1/S2, maks. 2 tahun kelulusan.',
                    'IPK minimal 3.25 dari 4.00.',
                    'Usia maksimal 26 tahun.',
                    'Berasal dari kampus mitra terpilih.',
                    'Aktif berorganisasi & memiliki jiwa kepemimpinan.',
                    'Bersedia ditempatkan di seluruh area operasional grup.',
                ],
                'fasilitas' => ['Mess/akomodasi', 'Asuransi kesehatan', 'Laptop kerja', 'Coaching berkala'],
                'jadwal' => [
                    ['label' => 'Registrasi & Seleksi Administrasi', 'tanggal' => '1 Jul – 7 Sep 2026'],
                    ['label' => 'Tes Potensi Akademik & Psikotes', 'tanggal' => '12 Sep 2026'],
                    ['label' => 'Pengisian Biodata Lanjutan', 'tanggal' => '13 – 14 Sep 2026'],
                    ['label' => 'Tes Potensi Akademik & Psikotes 2', 'tanggal' => '15 Sep 2026'],
                    ['label' => 'Wawancara 1 (HR & Psikolog)', 'tanggal' => '17 Sep 2026'],
                    ['label' => 'Wawancara 2 (Direksi)', 'tanggal' => '19 Sep 2026'],
                    ['label' => 'Onboarding & Program Dimulai', 'tanggal' => '1 Okt 2026'],
                ],
                'pipeline' => [
                    ['tipe' => 'FORM', 'label' => 'Registrasi & Seleksi Administrasi'],
                    ['tipe' => 'HCLEARN_TEST', 'label' => 'Tes Potensi Akademik & Psikotes'],
                    ['tipe' => 'FORM2', 'label' => 'Pengisian Biodata Lanjutan'],
                    ['tipe' => 'HCLEARN_TEST', 'label' => 'Tes Potensi Akademik & Psikotes 2'],
                    ['tipe' => 'INTERVIEW', 'label' => 'Wawancara 1'],
                    ['tipe' => 'INTERVIEW', 'label' => 'Wawancara 2'],
                    ['tipe' => 'ONBOARDING', 'label' => 'Onboarding'],
                ],
            ],
            [
                'id' => 'MT-2026-STP',
                'nama' => 'Sales Trainee Program (STP) 2026',
                'tagline' => 'Jalur cepat menjadi Sales Leader profesional.',
                'jenis' => 'MT',
                'batch' => 'Batch 3',
                'perusahaan' => 'PT Evo Nusa Bersaudara',
                'status' => 'PENUH', // contoh: kuota sudah penuh
                'tipeKegiatan' => 'Umum / Terbuka',
                'lokasi' => 'Palembang (Head Office)',
                'penempatan' => 'Sumatera Selatan',
                'durasi' => '9 bulan program terstruktur',
                'ikatan' => 'Ikatan dinas 1 tahun',
                'kuota' => 8,
                'kuotaTerisi' => 8,
                'pelamar' => 138,
                'tanggalBuka' => '2026-06-01',
                'tanggalTutup' => '2026-07-31',
                'tanggalPengumuman' => '2026-08-20',
                'targetKampus' => ['Semua Universitas'],
                'ringkasan' =>
                    'Kegiatan percepatan karir bagi lulusan yang bercita-cita membangun karir di dunia sales & distribusi FMCG pet food.',
                'deskripsi' =>
                    'Sales Trainee Program membekali peserta dengan kemampuan sales, leadership, dan analisis pasar melalui kombinasi kelas, coaching lapangan, dan penugasan area nyata hingga siap memimpin tim penjualan.',
                'catatanKegiatan' => 'Kuota batch ini telah terpenuhi. Pantau terus untuk pembukaan batch berikutnya.',
                'benefit' => [
                    'Gaji pokok + insentif penjualan',
                    'Sertifikasi program sales profesional',
                    'Coaching lapangan intensif',
                    'Promosi ke Sales Supervisor setelah lulus program',
                ],
                'kriteria' => [
                    'Fresh graduate D3/S1 semua jurusan.',
                    'IPK minimal 3.00 dari 4.00.',
                    'Memiliki SIM C & bersedia mobilitas tinggi.',
                    'Berorientasi target & menyukai tantangan lapangan.',
                    'Bersedia ditempatkan di area Sumatera Selatan.',
                ],
                'fasilitas' => ['Uang transport lapangan', 'Asuransi kesehatan', 'Seragam kerja', 'Coaching mingguan'],
                'jadwal' => [
                    ['label' => 'Registrasi & Seleksi Administrasi', 'tanggal' => '1 Jun – 31 Jul 2026'],
                    ['label' => 'Tes Potensi Akademik & Psikotes', 'tanggal' => '5 Agu 2026'],
                    ['label' => 'Pengisian Biodata Lanjutan', 'tanggal' => '6 – 7 Agu 2026'],
                    ['label' => 'Tes Potensi Akademik & Psikotes 2', 'tanggal' => '8 Agu 2026'],
                    ['label' => 'Wawancara 1 (HR)', 'tanggal' => '10 Agu 2026'],
                    ['label' => 'Wawancara 2 (Sales Manager)', 'tanggal' => '12 Agu 2026'],
                    ['label' => 'Onboarding & Training Kelas', 'tanggal' => '1 Sep 2026'],
                ],
                'pipeline' => [
                    ['tipe' => 'FORM', 'label' => 'Registrasi & Seleksi Administrasi'],
                    ['tipe' => 'HCLEARN_TEST', 'label' => 'Tes Potensi Akademik & Psikotes'],
                    ['tipe' => 'FORM2', 'label' => 'Pengisian Biodata Lanjutan'],
                    ['tipe' => 'HCLEARN_TEST', 'label' => 'Tes Potensi Akademik & Psikotes 2'],
                    ['tipe' => 'INTERVIEW', 'label' => 'Wawancara 1'],
                    ['tipe' => 'INTERVIEW', 'label' => 'Wawancara 2'],
                    ['tipe' => 'ONBOARDING', 'label' => 'Onboarding'],
                ],
            ],
        ];
    }

    /** Pencapaian perusahaan — angka untuk section achievement (animated counter). */
    private function achievements(): array
    {
        // Total titik lokasi dari Master Lokasi (bukan angka statis Palembang &
        // Banyuasin lagi) — ikut naik/turun mengikuti data master yang sebenarnya.
        $totalLokasi = count($this->offices());

        return [
            [
                'icon' => 'bi-people-fill',
                'value' => 1200,
                'suffix' => '+',
                'label' => 'Karyawan Aktif',
                'desc' => 'Tersebar di seluruh entitas grup',
            ],
            [
                'icon' => 'bi-calendar2-heart-fill',
                'value' => 15,
                'suffix' => '+',
                'label' => 'Tahun Berkarya',
                'desc' => 'Tumbuh sejak 2011',
            ],
            [
                'icon' => 'bi-geo-alt-fill',
                'value' => $totalLokasi,
                'suffix' => ' Lokasi',
                'label' => 'Sebaran Lokasi',
                'desc' => 'Kantor pusat & cabang operasional',
            ],
            [
                'icon' => 'bi-box-seam-fill',
                'value' => 20,
                'suffix' => '+',
                'label' => 'Brand Produk',
                'desc' => 'Life Cat, Ori Dog, dll.',
            ],
        ];
    }

    /** Lokasi operasional + jumlah posisi aktif dari MPP. */
    private function offices(): array
    {
        if ($this->officesCache !== null) {
            return $this->officesCache;
        }

        try {
            // Hanya MPP yang benar-benar tertaut ke posisi berstatus BUKA pada
            // pembukaan yang TERBIT/BERJALAN (dbOpenings), bukan seluruh MPP HRIS.
            $mppRefs = $this->dbOpenings()['posisi']->flatten(1)->pluck('Mpp_Ref')->filter()->unique()->values();

            return $this->officesCache = DB::table('N_HRIS_Master_Lokasi as m')
                ->leftJoin('HRIS_Transaksi_GForm as g', function ($join) use ($mppRefs) {
                    $join->on('g.Kode_Lokasi', '=', 'm.Kode_Lokasi')
                        ->whereIn('g.No_Transaksi', $mppRefs);
                })
                ->where('m.Status_Aktif', 'Y')
                ->groupBy('m.Kode_Lokasi', 'm.Nama_Lokasi', 'm.Provinsi', 'm.Pulau', 'm.Status_HO', 'm.Keterangan')
                ->orderByRaw("CASE WHEN m.Status_HO = 'Y' THEN 0 ELSE 1 END")
                ->orderBy('m.Nama_Lokasi')
                ->get([
                    'm.Kode_Lokasi as kode',
                    'm.Nama_Lokasi as kota',
                    'm.Provinsi as provinsi',
                    'm.Pulau as pulau',
                    'm.Status_HO as statusHo',
                    'm.Keterangan as keterangan',
                    // Satu MPP yang tertaut pada satu Program_Posisi = satu
                    // lowongan. Jangan jumlahkan Jumlah_Rekruitmen di sini:
                    // itu kuota/kebutuhan kandidat, bukan jumlah posisi.
                    DB::raw('COUNT(DISTINCT g.No_Transaksi) as posisi'),
                ])
                ->map(fn ($r) => [
                    'kode' => $r->kode,
                    'kota' => $r->kota,
                    'nama' => $r->statusHo === 'Y' ? 'EVO Group Head Office' : 'EVO Group Branch Office',
                    'tipe' => $r->statusHo === 'Y' ? 'Kantor Pusat' : 'Kantor Cabang',
                    'alamat' => $r->provinsi,
                    'provinsi' => $r->provinsi,
                    'pulau' => $r->pulau,
                    'statusHo' => $r->statusHo,
                    'keterangan' => $r->keterangan,
                    'posisi' => (int) $r->posisi,
                    'unggulan' => $r->statusHo === 'Y',
                ])->values()->all();
        } catch (\Throwable $e) {
            // Master lokasi adalah sumber kebenaran; jangan tampilkan data kantor
            // hardcoded jika tabel/query tidak tersedia.
            return [];
        }
    }

    /** Alasan bergabung — highlight chip di hero. */
    /**
     * Slide hero landing (Master Hero) — hanya yang AKTIF, terurut sesuai Urutan.
     * Landing publik tidak boleh tumbang karena tabel/berkas media bermasalah.
     */
    private function heroSlides(): array
    {
        try {
            return DB::table('N_WEB_CAREERS_Master_Hero_Slide')
                ->where('Flag_Aktif', 'Y')
                ->orderBy('Urutan')
                ->orderBy('Id_Master_Hero_Slide')
                ->get()
                ->map(function ($r) {
                    $id = (int) $r->Id_Master_Hero_Slide;
                    $v = $r->Updated_At ? strtotime($r->Updated_At) : 0;
                    $media = fn (string $slot, ?string $path) => $path
                        ? '/karir/hero-media/' . Hashids::encode($id) . '/' . $slot . '?v=' . $v
                        : null;

                    return [
                        'id' => Hashids::encode($id),
                        'label' => $r->Label,
                        'tipe' => $r->Tipe,
                        'tampilkanKonten' => $r->Flag_Tampilkan_Konten === 'Y',
                        'overlay' => strtolower($r->Overlay),
                        'zoomAnimation' => $r->Zoom_Animation === 'Y' ? 'Y' : 'N',
                        'durasiMs' => (int) $r->Durasi_Ms,
                        'gambarDesktop' => $media('desktop', $r->Gambar_Desktop),
                        'gambarMobile' => $media('mobile', $r->Gambar_Mobile),
                        'videoDesktopUrl' => $media('video_desktop', $r->Video_Desktop_Url),
                        'videoMobileUrl' => $media('video_mobile', $r->Video_Mobile_Url),
                        'videoDesktopPoster' => $media('poster_desktop', $r->Video_Desktop_Poster),
                        'videoMobilePoster' => $media('poster_mobile', $r->Video_Mobile_Poster),
                    ];
                })
                ->values()
                ->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function benefits(): array
    {
        return [
            ['icon' => 'bi-graph-up-arrow', 'title' => 'Jenjang Karier Jelas'],
            ['icon' => 'bi-mortarboard', 'title' => 'Budaya Belajar'],
            ['icon' => 'bi-heart-pulse', 'title' => 'Benefit Kompetitif'],
            ['icon' => 'bi-people', 'title' => 'Tim Suportif'],
        ];
    }
}
