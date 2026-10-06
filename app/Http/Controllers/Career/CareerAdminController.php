<?php

namespace App\Http\Controllers\Career;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Support\Career\AksesService;
use App\Support\Career\FieldTurunan;
use App\Support\Career\ProfilPengguna;
use App\Support\CareerShell;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

/**
 * WEB CAREER — Controller operasional + OPTIONS dropdown.
 *
 * CATATAN
 *   Berkas ini DIBANGUN ULANG 2026-07-21. Versi sebelumnya memuat banyak data
 *   dummy untuk halaman master gaya lama yang SUDAH TIDAK DIROUTE — seluruh
 *   master kini punya modulnya sendiri di folder Career/Master...
 *
 *   Yang dibangun ulang hanya yang masih dipakai route:
 *     - options()        : sumber SEMUA dropdown RefSelect di modul baru
 *     - halaman /karir/* dan portal kandidat
 *     - lowonganAdmin()  : daftar MPP dummy, dipakai halaman Lowongan DAN
 *                          picker posisi di Program Kegiatan
 *
 *   Endpoint CRUD master generik (master_simple_*, master_rich_*, master_akun_*,
 *   master_kemitraan_*) TIDAK dibangun ulang: halaman pemakainya sudah tidak
 *   punya route. Route-nya ikut dihapus.
 */
class CareerAdminController extends Controller
{
    // ═══════════════════════ HALAMAN OPERASIONAL ═══════════════════════

    // dashboard() DIHAPUS — /karir kini ditangani
    // App\Http\Controllers\Career\Dashboard\DashboardController.
    //
    // Versi lama mengirim 'funnel' => [] dan 'recent' => [] secara hardcode,
    // jadi dua kartunya permanen kosong tanpa empty-state; kartu statistiknya
    // pun tak pernah tampil angkanya karena controller mengirim
    // label/nilai/ikon sementara template membaca value/icon/trend. Tidak ada
    // yang bisa diselamatkan dari sini, jadi diganti utuh, bukan ditambal.

    /** /karir/pelamar — worklist seleksi. Diisi mesin lamaran (Batch 9-10). */
    public function pelamar()
    {
        return Inertia::render(
            'Career/admin/Pelamar',
            CareerShell::props('/karir/pelamar', 'Pelamar', [
                'worklist' => [],
                'lastUpdate' => ['waktu' => now()->format('d M Y H:i')],
            ]),
        );
    }

    // hasil_page() DIHAPUS — /karir/hasil-tes kini 301 ke /karir/monitoring
    // (MonitoringController), halaman Monitoring Rekrutmen.

    /** /karir/kandidat — basis data kandidat terdaftar. */
    public function kandidat_page()
    {
        $kandidat = DB::table('N_WEB_CAREERS_Users')
            ->orderByDesc('Id_Users')
            ->limit(200)
            ->get()
            ->map(
                fn($u) => [
                    'id' => $u->Id_Users,
                    'nama' => $u->Nama ?? '—',
                    'email' => $u->Email ?? null,
                    'status' => ($u->Flag_Aktif ?? 'Y') === 'Y' ? 'AKTIF' : 'NONAKTIF',
                ],
            )
            ->values();

        return Inertia::render(
            'Career/admin/Kandidat',
            CareerShell::props('/karir/kandidat', 'Kandidat', [
                'kandidat' => $kandidat,
            ]),
        );
    }

    /** /karir/pengumuman — pengumuman hasil ke kandidat. */
    public function pengumuman_page()
    {
        $kegiatanOptions = DB::table('N_WEB_CAREERS_Program')
            ->orderBy('Nama')
            ->get()
            ->map(fn($p) => ['id' => $p->Kode, 'nama' => $p->Nama, 'kategori' => $p->Kategori])
            ->values();

        return Inertia::render(
            'Career/admin/Pengumuman',
            CareerShell::props('/karir/pengumuman', 'Pengumuman', [
                'pengumuman' => [],
                'kegiatanOptions' => $kegiatanOptions,
            ]),
        );
    }

    // ═══════════════════════ PORTAL KANDIDAT ═══════════════════════

    /**
     * /profil — SATU route untuk semua akun; isinya menyesuaikan peran.
     *
     * Dulu diisi CareerShell::adminUser(), yaitu identitas SHELL (name /
     * username / department) — bentuk yang benar untuk footer sidebar, tapi
     * bukan yang dibaca layar profil, sehingga kartunya tampil kosong tanpa
     * galat. Sumbernya sekarang ProfilPengguna: dibaca ulang dari DB, lengkap,
     * dan berlaku untuk kandidat maupun admin/superadmin.
     */
    public function profil()
    {
        return Inertia::render(
            'Career/portal/Profil',
            CareerShell::props('/profil', 'Profil Saya', [
                'user' => ProfilPengguna::payload(),
            ]),
        );
    }

    public function portalIndex()
    {
        return Inertia::render('Career/portal/LamaranSaya', CareerShell::props('/kandidat/portal', 'Lamaran Saya'));
    }

    public function portalDetail(string $id)
    {
        return Inertia::render(
            'Career/portal/LamaranDetail',
            CareerShell::props('/kandidat/portal', 'Detail Lamaran', [
                'kandidat' => CareerShell::adminUser(),
                'lamaran' => ['id' => $id],
            ]),
        );
    }

    public function portalTerkirim(string $id)
    {
        return Inertia::render(
            'Career/portal/LamaranTerkirim',
            CareerShell::props('/kandidat/portal', 'Lamaran Terkirim', [
                'programId' => $id,
                'catalog' => (object) [],
                'stages' => [],
            ]),
        );
    }

    // ═══════════════════════ OPTIONS (dropdown) ═══════════════════════

    /**
     * OPTIONS generik — semua daftar pilihan DIAMBIL DARI DB (bukan hardcode).
     * GET /api/v1/karir/options/{type} -> [{value,label}].
     *
     * Dipakai komponen RefSelect di SELURUH modul. Ini endpoint paling sering
     * dipanggil di aplikasi: kalau mati, semua dropdown ikut kosong.
     */
    public function options(string $type)
    {
        if ($type === 'icons') {
            return $this->options_icons();
        }
        if ($type === 'mpp') {
            return $this->options_mpp();
        }
        if ($type === 'kategori-preset') {
            return $this->options_kategori_preset();
        }
        if ($type === 'tahap-formulir') {
            return $this->options_tahap_formulir();
        }
        // [feat/feedback] Opsi program untuk assignment form feedback.
        //
        // Cabang ini PULANG DULUAN, sebelum $map di bawah — jadi entri 'program'
        // di sana tidak pernah terpakai, dan gerbang kategori yang dipasang di
        // sana pun tidak pernah menyentuhnya. Karena itu gerbangnya diulang di
        // sini. Bukan pengulangan yang bisa dihindari tanpa menyatukan kedua
        // cabang, dan menyatukannya berarti mengubah bentuk value-nya
        // (Id_Program di sini, Kode di $map) yang dipakai layar lain.
        if ($type === 'program') {
            $izinProgram = AksesService::kategoriSemuaHalaman();

            $rows = DB::table('N_WEB_CAREERS_Program')
                ->when($izinProgram, fn ($w) => $w->whereIn('Kategori', $izinProgram))
                ->select('Id_Program', 'Nama')
                ->orderBy('Nama')
                ->get()
                ->map(fn($r) => ['value' => $r->Id_Program, 'label' => $r->Nama])
                ->values();
            return ResponseHelper::success($rows, 'Opsi program');
        }
        if ($type === 'field-turunan') {
            return ResponseHelper::success(FieldTurunan::daftar(), 'Field turunan');
        }
        if ($type === 'jenis-agenda') {
            return ResponseHelper::success($this->jenisAgenda(), 'Jenis agenda');
        }
        // Tipe tahap membawa 'perilaku' (aksi) yang sudah di-bind di masternya —
        // builder Alur memakainya untuk menurunkan Provider/Keputusan otomatis
        // (tanpa memilih Penyedia lagi). Sumber tunggal: kolom Perilaku_Kode.
        if ($type === 'tipe') {
            $rows = DB::table('N_WEB_CAREERS_Master_Tipe_Tahap')
                ->where('Flag_Aktif', 'Y')
                ->orderBy('Nama')
                ->get()
                ->map(fn ($r) => [
                    'value' => $r->Kode,
                    'label' => $r->Nama,
                    'perilaku' => $r->Perilaku_Kode,
                    // FLAG PERILAKU ikut dikirim. Tanpa ini butuhFormulir() di
                    // builder selalu bernilai false, dan penyimpanan MEMAKSA
                    // Formulir_Kode jadi NULL — sehingga alur yang sudah benar
                    // kehilangan formulirnya begitu disunting ulang, tanpa galat
                    // apa pun. Itulah yang terjadi pada "EVO MANAGEMENT 2026".
                    'formulir' => ($r->Flag_Formulir ?? 'T') === 'Y',
                    'uploadHasil' => ($r->Flag_Upload_Hasil ?? 'T') === 'Y',
                    // Sakelar "Upload berkas hasil" per tahap ditawarkan di
                    // Master Alur? Bawaan ya; 'T' = disembunyikan untuk tipe
                    // ini (docs/29-09-2026/01). Sebelum kolomnya ada: ya.
                    'opsiUpload' => ($r->Flag_Opsi_Upload ?? 'Y') !== 'T',
                    'jadwal' => ($r->Flag_Jadwal ?? 'T') === 'Y',
                    'wajibLuring' => ($r->Flag_Wajib_Luring ?? 'T') === 'Y',
                    'tuntas' => ($r->Flag_Tuntas ?? 'T') === 'Y',
                    // Tipe yang MENUNTUT tindakan kandidat (ujian online), jadi
                    // tak boleh disembunyikan dari portalnya — kalau
                    // disembunyikan, ia tak pernah melihat tombol mengerjakan.
                    'wajibTampil' => ($r->Flag_Wajib_Tampil ?? 'T') === 'Y',
                    // Tipe yang jadwalnya TIDAK diumumkan ke kandidat (negosiasi
                    // penawaran). Dikirim supaya layar Alur bisa membedakan
                    // "berjadwal, jadi wajib terlihat" dari "berjadwal, tapi
                    // rapat internal" — tanpa ini ia harus menghafal kodenya.
                    'jadwalPrivat' => \App\Support\Career\JadwalPrivat::untuk($r->Kode),
                    // PEMERIKSAAN (reference check, background check): bukan tes,
                    // jadi tidak dinilai dengan angka maupun predikat — hasilnya
                    // berupa temuan per komponen/narasumber lalu satu adjudikasi.
                    // Selama kolomnya belum ada di basis data, nilainya false dan
                    // layar Alur memakai daftar kode cadangannya sendiri.
                    'pemeriksaan' => ($r->Flag_Pemeriksaan ?? 'T') === 'Y',
                    // Kalimat aturan biaya tipe ini (MCU) — builder Alur
                    // menawarkan sakelar "tampilkan informasi biaya" hanya
                    // untuk tipe yang memang punya kalimatnya.
                    'biaya' => trim((string) ($r->Kalimat_Biaya ?? '')) ?: null,
                    'ikon' => $r->Ikon,
                ])
                ->values();

            return ResponseHelper::success($rows, 'Opsi tipe');
        }
        // Mode pengumuman hasil tahap — hanya yang AKTIF (mis. TERJADWAL bisa
        // dinonaktifkan). Bawa ikon/deskripsi/butuhJeda supaya builder Alur tak
        // perlu memetakan label/aturan secara hardcode.
        if ($type === 'mode-pengumuman') {
            $rows = DB::table('N_WEB_CAREERS_Master_Mode_Pengumuman')
                ->where('Flag_Aktif', 'Y')
                ->orderBy('Urutan')
                ->get()
                ->map(
                    fn($r) => [
                        'value' => $r->Kode,
                        'label' => $r->Label,
                        'nama' => $r->Nama,
                        'ikon' => $r->Ikon,
                        'warna' => $r->Warna,
                        'deskripsi' => $r->Deskripsi,
                        'butuhJeda' => $r->Butuh_Jeda === 'Y',
                    ],
                )
                ->values();

            return ResponseHelper::success($rows, 'Opsi mode pengumuman');
        }
        // Mode keputusan tahap (bagaimana tahap menyimpulkan) — hanya yang AKTIF.
        // Bawa flag perilaku supaya builder bisa menjelaskan efeknya ke admin.
        if ($type === 'mode-keputusan') {
            $rows = DB::table('N_WEB_CAREERS_Master_Mode_Keputusan')
                ->where('Flag_Aktif', 'Y')
                ->orderBy('Urutan')
                ->get()
                ->map(
                    fn($r) => [
                        'value' => $r->Kode,
                        'label' => $r->Label,
                        'nama' => $r->Nama,
                        'ikon' => $r->Ikon,
                        'warna' => $r->Warna,
                        'deskripsi' => $r->Deskripsi,
                        'tunggu' => $r->Tunggu,
                        'autoLanjut' => $r->Auto_Lanjut === 'Y',
                        'syaratLulus' => $r->Syarat_Lulus,
                        'autoGugur' => $r->Auto_Gugur === 'Y',
                    ],
                )
                ->values();

            return ResponseHelper::success($rows, 'Opsi mode keputusan');
        }

        // Cara aktivitas dalam satu tahap dikerjakan: bersamaan atau berurutan.
        // Perilakunya (`berurutan`) ikut dikirim supaya builder Alur tidak
        // membandingkan Kode — menambah mode baru cukup lewat master.
        if ($type === 'mode-urutan') {
            $rows = DB::table('N_WEB_CAREERS_Master_Mode_Urutan')
                ->where('Flag_Aktif', 'Y')
                ->orderBy('Urutan')
                ->get()
                ->map(fn ($r) => [
                    'value' => $r->Kode,
                    'label' => $r->Label ?: $r->Nama,
                    'nama' => $r->Nama,
                    'ikon' => $r->Ikon,
                    'warna' => $r->Warna,
                    'deskripsi' => $r->Deskripsi,
                    'berurutan' => $r->Flag_Berurutan === 'Y',
                ])
                ->values();

            return ResponseHelper::success($rows, 'Opsi mode urutan aktivitas');
        }
        // Alasan menahan (HOLD) kandidat di tahapnya. Dipilih dari daftar, bukan
        // diketik bebas: inilah yang dihitung saat menjelaskan kenapa satu
        // lowongan lama terisi, dan teks bebas membuat sebab yang sama tak
        // pernah terkelompokkan.
        if ($type === 'alasan-hold') {
            $rows = DB::table('N_WEB_CAREERS_Master_Alasan_Hold')
                ->where('Flag_Aktif', 'Y')
                ->orderBy('Urutan')
                ->get()
                ->map(fn ($r) => [
                    'value' => $r->Kode,
                    'label' => $r->Nama,
                    'nama' => $r->Nama,
                    'ikon' => $r->Ikon,
                    'warna' => $r->Warna,
                    'deskripsi' => $r->Deskripsi,
                    'butuhCatatan' => $r->Butuh_Catatan === 'Y',
                ])
                ->values();

            return ResponseHelper::success($rows, 'Opsi alasan hold');
        }

        // Cara sebuah aktivitas dinilai: tanpa nilai / angka / kategori.
        // `tipe` (perilaku) ikut dikirim supaya builder tahu bidang apa yang
        // harus diminta — daftar pilihan hanya relevan untuk mode berkategori.
        if ($type === 'mode-penilaian') {
            $rows = DB::table('N_WEB_CAREERS_Master_Mode_Penilaian')
                ->where('Flag_Aktif', 'Y')
                ->orderBy('Urutan')
                ->get()
                ->map(fn ($r) => [
                    'value' => $r->Kode,
                    'label' => $r->Nama,
                    'nama' => $r->Nama,
                    'ikon' => $r->Ikon,
                    'warna' => $r->Warna,
                    'deskripsi' => $r->Deskripsi,
                    'tipe' => $r->Tipe_Nilai,
                    'butuhOpsi' => $r->Butuh_Opsi === 'Y',
                ])
                ->values();

            return ResponseHelper::success($rows, 'Opsi mode penilaian');
        }

        // Batas ukuran unggahan kandidat — DARI MASTER, bukan daftar angka di
        // layar. Menyempitkan atau melebarkan pilihan cukup lewat data, dan
        // batas yang ditawarkan admin dijamin sama dengan yang diberlakukan
        // saat kandidat benar-benar mengunggah.
        if ($type === 'batas-unggah') {
            $rows = DB::table('N_WEB_CAREERS_Master_Batas_Unggah')
                ->where('Flag_Aktif', 'Y')
                ->orderBy('Urutan')
                ->get()
                ->map(fn ($r) => [
                    'value' => (int) $r->Maks_Mb,
                    'label' => $r->Label,
                    'deskripsi' => $r->Keterangan,
                ])
                ->values();

            return ResponseHelper::success($rows, 'Opsi batas unggah');
        }

        // Perpindahan antar sub-aktivitas: otomatis atau menunggu admin.
        if ($type === 'mode-lanjut') {
            $rows = DB::table('N_WEB_CAREERS_Master_Mode_Lanjut')
                ->where('Flag_Aktif', 'Y')
                ->orderBy('Urutan')
                ->get()
                ->map(fn ($r) => [
                    'value' => $r->Kode,
                    'label' => $r->Label ?: $r->Nama,
                    'nama' => $r->Nama,
                    'ikon' => $r->Ikon,
                    'warna' => $r->Warna,
                    'deskripsi' => $r->Deskripsi,
                    'butuhTrigger' => $r->Flag_Butuh_Trigger === 'Y',
                ])
                ->values();

            return ResponseHelper::success($rows, 'Opsi mode lanjut');
        }

        // type => [tabel, kolom value, kolom label, kolom flag aktif (atau null)]
        $map = [
            'talent' => ['N_WEB_CAREERS_Master_Talent_Acquisition', 'Kode', 'Nama', 'Flag_Aktif'],
            'perilaku' => ['N_WEB_CAREERS_Master_Perilaku', 'Kode', 'Nama', 'Flag_Aktif'],
            'mode' => ['N_WEB_CAREERS_Master_Mode_Pelaksanaan', 'Kode', 'Nama', 'Flag_Aktif'],
            'sumber' => ['N_WEB_CAREERS_Master_Sumber_Kandidat', 'Kode', 'Nama', 'Flag_Aktif'],
            'tipe' => ['N_WEB_CAREERS_Master_Tipe_Tahap', 'Kode', 'Nama', 'Flag_Aktif'],
            'kategori' => ['N_WEB_CAREERS_Master_Kategori', 'Kode', 'Nama', 'Flag_Aktif'],
            'kampus' => ['N_WEB_CAREERS_Master_Kampus', 'Id_Master_Kampus', 'Nama', 'Flag_Aktif'],
            'alur' => ['N_WEB_CAREERS_Master_Alur', 'Kode', 'Nama', 'Flag_Aktif'],
            'tes' => ['N_WEB_CAREERS_Master_Jenis_Tes', 'Kode', 'Nama', 'Flag_Aktif'],
            'klasifikasi-akun' => ['N_WEB_CAREERS_Klasifikasi_Akun', 'Kode', 'Nama', 'Flag_Aktif'],
            'metode' => ['N_WEB_CAREERS_Master_Metode_Tes', 'Kode', 'Nama', 'Flag_Aktif'],
            'akreditasi' => ['N_WEB_CAREERS_Master_Akreditasi', 'Nama', 'Nama', 'Flag_Aktif'],
            'provider' => ['N_WEB_CAREERS_Master_Provider', 'Kode', 'Nama', 'Flag_Aktif'],
            'formulir' => ['N_WEB_CAREERS_Master_Formulir', 'Kode', 'Nama', 'Flag_Aktif'],
            'program' => ['N_WEB_CAREERS_Program', 'Kode', 'Nama', null],
            'jadwal' => ['N_WEB_CAREERS_Master_Jadwal', 'Kode', 'Kegiatan', null],
        ];
        abort_unless(isset($map[$type]), 404);
        [$table, $valCol, $labCol, $aktifCol] = $map[$type];

        $q = DB::table($table);
        if ($aktifCol) {
            $q->where($aktifCol, 'Y');
        }

        // ── GERBANG HAK AKSES KATEGORI ──────────────────────────────────────
        //
        // Rutenya cuma dijaga peran (career.auth + role ADMIN/SUPERADMIN), jadi
        // tanpa blok ini SETIAP admin bisa membaca seluruh alur, jadwal, dan
        // program lewat satu URL — termasuk milik kategori yang layarnya sendiri
        // sudah menyembunyikannya. Menyaring di layar saja berarti datanya tetap
        // dikirim lebih dulu, lalu "disembunyikan" oleh kode yang bisa dibaca
        // siapa pun di peramban.
        //
        // Akibat nyatanya bukan cuma soal intip: jadwal MT bisa berakhir menunjuk
        // alur Rekrutmen karena daftarnya menawarkan alur itu, dan tidak ada satu
        // pun peringatan yang muncul sesudahnya.
        $izin = AksesService::kategoriSemuaHalaman();
        if ($izin) {
            if (in_array($type, ['alur', 'jadwal', 'program'], true)) {
                $q->whereIn('Kategori', $izin);
            } elseif ($type === 'talent') {
                // Master kategori itu sendiri: yang disaring KODEnya, bukan kolom
                // Kategori — tabel ini tidak punya kolom itu, ia adalah kolom itu.
                $q->whereIn('Kode', $izin);
            }
        }

        // Filter kontekstual — dipakai modal Program Kegiatan: admin memilih
        // Kategori lebih dulu, lalu pilihan Alur & Jadwal ikut menyempit.
        //
        // Berlapis DI ATAS gerbang, bukan menggantikannya: yang ini penyempit
        // pilihan yang diminta layar, yang di atas penjaga yang tidak bisa diminta.
        $kategori = request()->query('kategori');
        if ($kategori && in_array($type, ['alur', 'jadwal'], true)) {
            $q->where('Kategori', $kategori);
        }
        $alur = request()->query('alur');
        if ($alur && $type === 'jadwal') {
            $q->where('Alur_Kode', $alur);
        }

        // Formulir hanya boleh dipilih di tahap alur bila sudah bisa dirender
        // kandidat — form lama lewat Komponen_Kode, form dinamis lewat versi
        // PUBLISHED di Master_Formulir_Versi. Tanpa salah satunya, menawarkan
        // formulir di sini cuma jadi jebakan. (Kolom dari Batch 9; versi dinamis
        // ditambahkan saat Master Formulir berhenti mengisi Komponen_Kode.)
        if ($type === 'formulir') {
            $punyaVersi = \App\Support\Career\FormulirSchema::punyaTabelVersi();
            $q->where(function ($sub) use ($punyaVersi) {
                $sub->whereNotNull('Komponen_Kode');
                if ($punyaVersi) {
                    $sub->orWhereExists(function ($versi) {
                        $versi->select(DB::raw(1))
                            ->from('N_WEB_CAREERS_Master_Formulir_Versi as v')
                            ->whereColumn('v.Master_Formulir_Id', 'N_WEB_CAREERS_Master_Formulir.Id_Master_Formulir')
                            ->where('v.Status', 'PUBLISHED');
                    });
                }
            });
        }

        $rows = $q
            ->orderBy($labCol)
            ->get()
            // Tipe tahap membawa PERILAKU & FLAG-nya. Builder Alur Seleksi
            // memakainya untuk tahu sebuah aktivitas itu ujian online (CAT) atau
            // ditangani tim, dan apakah tipe itu menempelkan formulir — hal-hal
            // yang dulu ditulis sebagai daftar kode di dalam file Vue.
            ->map(function ($r) use ($valCol, $labCol, $type) {
                $baris = ['value' => $r->{$valCol}, 'label' => $r->{$labCol}];
                if ($type === 'tipe') {
                    $baris['perilaku'] = $r->Perilaku_Kode ?? null;
                    $baris['ikon'] = $r->Ikon ?? null;
                    $baris['formulir'] = ($r->Flag_Formulir ?? 'T') === 'Y';
                    $baris['uploadHasil'] = ($r->Flag_Upload_Hasil ?? 'T') === 'Y';
                }

                return $baris;
            })
            ->values();

        return ResponseHelper::success($rows, 'Opsi ' . $type);
    }

    /** OPTIONS — seluruh Bootstrap Icons yang ter-ship. Dipakai IconPicker. */
    public function options_icons()
    {
        // Di lokal jangan di-cache: TTL 24 jam bikin daftar ikon "nyangkut"
        // saat paket bootstrap-icons diperbarui.
        $ttl = app()->environment('local') ? 0 : 86400;

        $icons = Cache::remember('web_career_bi_icons', $ttl, function () {
            $json = public_path('assets/extensions/bootstrap-icons/font/bootstrap-icons.json');
            if (is_file($json)) {
                $map = json_decode(file_get_contents($json), true) ?: [];

                return array_values(array_map(fn($k) => 'bi-' . $k, array_keys($map)));
            }
            $css = public_path('assets/extensions/bootstrap-icons/font/bootstrap-icons.css');
            if (is_file($css)) {
                preg_match_all('/\.(bi-[a-z0-9-]+)::before/', file_get_contents($css), $m);

                return array_values(array_unique($m[1] ?? []));
            }

            return [];
        });

        return ResponseHelper::success($icons, 'Daftar ikon');
    }

    /**
     * OPTIONS — daftar MPP (Manpower Planning) yang sudah disetujui.
     * Posisi/lowongan di Program Kegiatan WAJIB dipilih dari sini, tidak boleh diketik.
     *
     * ══ DISARING MENURUT KATEGORI PROGRAM ══
     *
     * MPP membedakan dirinya sendiri lewat `HRIS_Transaksi_GForm.Flag_MT`:
     * 'Y' berarti permintaan itu memang untuk Management Trainee, NULL/'T'
     * berarti pengisian posisi biasa. Pembagian itu dibuat di Master MPP saat
     * permintaannya diajukan, dan MasterMppController sudah menyaring dengan
     * aturan yang sama.
     *
     * Layar ini dulu MENGABAIKANNYA: `kategori` sudah dikirim modal Program
     * Kegiatan, tapi tidak pernah dibaca, dan tiap baris malah dipulangkan
     * ber-`'kategori' => null`. Akibatnya program MT disodori seluruh MPP
     * rekrutmen biasa, dan program rekrutmen disodori MPP kaderisasi — dua
     * daftar yang tidak boleh bertukar, karena posisi yang telanjur dipilih
     * ikut menentukan pagu kuota batch dan divisi yang divalidasi setelahnya.
     *
     * Yang dipakai KODE KATEGORI dari masternya sendiri (Master_Kategori.
     * Kategori = 'MT'), bukan istilah baru yang ditemukan di sini. Selain MT —
     * REKRUTMEN, INTERNSHIP, dan kategori lain yang menyusul — semuanya
     * memakai MPP non-MT, jadi aturannya cukup dua cabang dan tidak perlu
     * diperbarui tiap kategori baru ditambahkan.
     */
    public function options_mpp()
    {
        // Kategori program yang sedang disusun. Kosong = belum memilih apa pun;
        // seluruh MPP dipulangkan, sama seperti perilaku sebelumnya, supaya
        // pemanggil lama tidak mendadak menerima daftar kosong.
        $kategori = strtoupper(trim((string) request()->query('kategori', '')));

        // ── PENYARING PIC YANG DIMINTA LAYAR ────────────────────────────────
        //
        // Program yang ditugaskan kepada seseorang harus dibangun dari MPP
        // ORANG ITU. Loker yang lahir darinya akan jadi miliknya; mengambilnya
        // dari MPP orang lain berarti ia mengerjakan permintaan tenaga kerja
        // yang bukan tanggung jawabnya, dan pemilik MPP-nya tidak pernah tahu
        // permintaannya sudah dibuka.
        //
        // Berlapis DI ATAS gerbang lingkup, bukan menggantikannya: yang ini
        // penyempit yang diminta layar, yang di atas penjaga yang tidak bisa
        // diminta. Permintaan `pic` di luar lingkup karena itu tetap memulangkan
        // kartu terkunci, bukan kartu yang bisa dipilih.
        $picDiminta = trim((string) request()->query('pic', ''));

        // REAL (2026-07-23): sumber = Monitoring MPP (HRIS_Transaksi_GForm ⋈ N_WEB_CAREERS_Detail_MPP),
        // bukan dummy lowonganAdmin() lagi. Hanya MPP AKTIF & BELUM SELESAI yang bisa ditautkan program.
        // Catatan: MPP tidak menyimpan kota — kolom 'lokasi' diisi tempat kerja (Onsite/Hybrid/...).
        $rows = DB::table('HRIS_Transaksi_GForm as g')
            ->join('N_WEB_CAREERS_Detail_MPP as d', 'd.No_Transaksi_MPP', '=', 'g.No_Transaksi')
            ->leftJoin('HRIS_Divisi as dv', function ($j) {
                $j->on('dv.ID_Divisi', '=', 'g.Id_Divisi')->on('dv.Kode_Perusahaan', '=', 'g.Kode_Perusahaan');
            })
            ->leftJoin('HRIS_Sub_Divisi as sd', function ($j) {
                $j->on('sd.ID_Sub_Divisi', '=', 'g.Id_Sub_Divisi')->on('sd.Kode_Perusahaan', '=', 'g.Kode_Perusahaan');
            })
            ->leftJoin('HRIS_Level as lv', function ($j) {
                $j->on('lv.ID_Level', '=', 'g.Id_Level')->on('lv.Kode_Perusahaan', '=', 'g.Kode_Perusahaan');
            })
            ->leftJoin('HRIS_Jabatan as jb', function ($j) {
                $j->on('jb.ID_Jabatan', '=', 'g.Id_Jabatan')->on('jb.Kode_Perusahaan', '=', 'g.Kode_Perusahaan');
            })
            // Nama penanggung jawabnya ikut dibaca. Kartu yang terkunci harus
            // menyebut SIAPA pemegangnya — "di luar lingkup Anda" tanpa nama
            // memindahkan pencarian ke orang yang membacanya, dan ia akan
            // menelepon satu per satu untuk mencari tahu.
            ->leftJoin('Karyawan as pic', function ($j) {
                $j->on('pic.Kode_Karyawan', '=', 'g.User_Penganggung_Jawab')
                    ->on('pic.Kode_Perusahaan', '=', 'g.Kode_Perusahaan');
            })
            ->leftJoin('N_WEB_CAREERS_Master_Employment as me', 'me.Id_Employment', '=', 'd.Employment_Type')
            ->leftJoin('N_WEB_CAREERS_Master_Workplace as mw', 'mw.Id_Workplace', '=', 'd.Workplace_Type')
            ->leftJoin(
                'N_WEB_CAREERS_Master_Experience_Level as mx',
                'mx.Id_Experience_Level',
                '=',
                'd.Experience_Level',
            )
            ->whereRaw("ISNULL(g.Status, '') <> 'Y'")
            ->whereRaw("ISNULL(g.Flag_Selesai, '') <> 'Y'")
            // MT hanya melihat MPP ber-Flag_MT='Y'; kategori lain melihat
            // sisanya. ISNULL dipakai karena kolomnya NULL untuk MPP biasa —
            // `<> 'Y'` sendirian tidak pernah benar terhadap NULL di SQL
            // Server, dan seluruh daftar akan terbaca kosong.
            ->when($picDiminta !== '', fn ($q) => $q->where('g.User_Penganggung_Jawab', $picDiminta))
            ->when($kategori === 'MT', fn ($q) => $q->where('g.Flag_MT', 'Y'))
            ->when($kategori !== '' && $kategori !== 'MT', fn ($q) => $q->whereRaw("ISNULL(g.Flag_MT, '') <> 'Y'"))
            ->orderByDesc('g.Tanggal_Periode')
            ->orderBy('g.No_Transaksi')
            ->get([
                'g.No_Transaksi as no',
                'jb.Keterangan as jabatan',
                'dv.Id_Divisi as Id_Divisi',
                'dv.Keterangan as divisi',
                'sd.Id_Sub_Divisi as Id_Sub',
                'sd.Keterangan as sub',
                'lv.Keterangan as level',
                'g.Jumlah_Rekruitmen as kuota',
                'g.Flag_MT as flag_mt',
                'g.User_Penganggung_Jawab as pic_kode',
                'pic.Nama as pic_nama',
                'me.Nama_Employment as employment',
                'mw.Nama_Workplace as workplace',
                'mx.Nama_Experience_Level as experience',
            ]);

        // ── LINGKUP PIC ─────────────────────────────────────────────────────
        //
        // TIDAK dipakai sebagai WHERE. MPP di luar lingkup tetap dipulangkan,
        // hanya ditandai `boleh => false` berikut nama pemegangnya.
        //
        // Menyembunyikannya terasa lebih aman, tapi akibatnya justru menghambat:
        // rekruter yang tidak menemukan sebuah MPP tidak tahu apakah MPP itu
        // belum dibuat, sudah selesai, atau sekadar milik orang lain — dan pada
        // pukul 22.00 ketika pemegangnya cuti, ia berhenti bekerja tanpa tahu
        // harus minta ke siapa. Terlihat-tapi-terkunci menjawab keduanya
        // sekaligus.
        //
        // Yang menjaga data tetap gerbang di sisi SIMPAN (ProgramKegiatan),
        // bukan daftar ini: daftar apa pun yang dikirim ke peramban harus
        // dianggap bisa dibaca seluruhnya.
        $picBoleh = AksesService::picDiizinkan('programPage');


        $rows = collect($rows)
            ->map(function ($r) use ($picBoleh) {
                $dept = trim(implode(' · ', array_filter([trim((string) $r->divisi), trim((string) $r->sub)])));
                // Label sebelum "/" saja (mis. "Full-time / Purnawaktu" -> "Full-time").
                $emp = trim(explode('/', (string) $r->employment)[0]);

                return [
                    'value' => $r->no,
                    'label' => ($r->jabatan ?: $r->no) . ' — ' . ($dept ?: '—'),
                    'posisi' => $r->jabatan ?: 'Posisi ' . $r->no,
                    'Id_Divisi' => $r->Id_Divisi,
                    'divisi' => trim((string) $r->divisi),
                    'sub' => trim((string) $r->sub),
                    'Id_Sub_Divisi' => $r->Id_Sub,
                    'departemen' => $dept,
                    'lokasi' => $r->workplace ?: '',
                    'level' => $r->level,
                    'kuota' => (int) ($r->kuota ?? 0),
                    // Penanggung jawab MPP ini + apakah pengguna berhak memakainya.
                    // `boleh` false = kartunya digambar tanpa kendali sama sekali,
                    // bukan digambar lalu dimatikan — lihat catatannya di layar.
                    'picKode' => $r->pic_kode,
                    'picNama' => $r->pic_nama ?: $r->pic_kode,
                    'boleh' => $picBoleh === null || in_array((string) $r->pic_kode, $picBoleh, true),
                    'employment' => $emp,
                    'workplace' => $r->workplace,
                    'experience' => $r->experience,
                    // Jenis MPP-nya, bukan lagi null mati. Layar memakainya
                    // untuk menandai kartu — dan tanpa itu tidak ada cara
                    // memastikan daftar yang tampil memang sudah tersaring.
                    'mt' => ($r->flag_mt ?? '') === 'Y',
                    'kategori' => ($r->flag_mt ?? '') === 'Y' ? 'MT' : 'REKRUTMEN',
                ];
            })
            ->values();

        return ResponseHelper::success($rows, 'Opsi MPP (Monitoring MPP)');
    }

    /**
     * OPTIONS — preset per kategori: mode, alur, dan warna bawaan.
     * Dipakai modal Program Kegiatan untuk mengisi otomatis begitu Kategori
     * dipilih, sehingga admin tidak menebak kombinasi yang sudah ditetapkan
     * di Master Kategori.
     */
    public function options_kategori_preset()
    {
        $rows = DB::table('N_WEB_CAREERS_Master_Kategori')
            ->where('Flag_Aktif', 'Y')
            ->orderBy('Nama')
            ->get()
            ->map(
                fn($r) => [
                    'value' => $r->Kategori,
                    'label' => $r->Nama,
                    'mode' => $r->Mode_Default,
                    'alur' => $r->Alur_Default_Kode,
                    'warna' => $r->Warna,
                ],
            )
            ->values();

        return ResponseHelper::success($rows, 'Preset kategori');
    }

    /**
     * OPTIONS — tahap sebuah alur yang MENUNTUT FORMULIR, beserta komponennya.
     * Dipakai penyusun syarat auto-gugur di Program Kegiatan.
     *
     * Kenapa perlu: syarat hanya bisa memeriksa field yang memang ditanyakan
     * formulir. Tanpa ini admin mengetik nama field bebas — dan aturan yang
     * salah ketik tidak pernah cocok dengan apa pun, alias mati diam-diam.
     */
    public function options_tahap_formulir()
    {
        $alur = request()->query('alur');
        if (!$alur) {
            return ResponseHelper::success([], 'Alur belum dipilih');
        }

        $rows = DB::table('N_WEB_CAREERS_Master_Alur_Tahap as t')
            ->join('N_WEB_CAREERS_Master_Alur as a', 'a.Id_Master_Alur', '=', 't.Master_Alur_Id')
            ->leftJoin('N_WEB_CAREERS_Master_Formulir as f', 'f.Kode', '=', 't.Formulir_Kode')
            ->where('a.Kode', $alur)
            ->whereNotNull('t.Formulir_Kode')
            ->orderBy('t.Urutan')
            ->select(
                't.Id_Master_Alur_Tahap',
                't.Urutan',
                't.Label',
                't.Formulir_Kode',
                'f.Nama as FormulirNama',
                'f.Komponen_Kode',
            )
            ->get()
            ->map(
                fn($t) => [
                    'tahapId' => (int) $t->Id_Master_Alur_Tahap,
                    'urutan' => (int) $t->Urutan,
                    'label' => $t->Label,
                    'formulir' => $t->Formulir_Kode,
                    'formulirNama' => $t->FormulirNama,
                    'komponen' => $t->Komponen_Kode,
                    // Form dinamis tidak punya peta JS statis seperti FORMULIR_1..4
                    // (skemaFormulir() di frontend cuma tahu 4 komponen lama) —
                    // kirim schema-nya langsung supaya builder Syarat tetap bisa
                    // menurunkan daftar field dari sini.
                    'schema' => $t->Komponen_Kode
                        ? null
                        : (\App\Support\Career\FormulirSchema::publishedByKode($t->Formulir_Kode)['schema'] ?? null),
                ],
            )
            ->values();

        return ResponseHelper::success($rows, 'Tahap berformulir');
    }

    /**
     * OPTIONS — jenis agenda di Master Jadwal (rapat / campaign / seleksi / …).
     *
     * Ini kosakata SISTEM yang tetap, jadi disimpan sebagai konstanta di sini —
     * satu sumber untuk value, label, deskripsi, ikon, dan warna — bukan
     * tersebar sebagai peta hardcode di halaman Vue. Karena isinya tidak
     * pernah diubah admin, tidak perlu tabel master tersendiri; kalau suatu
     * saat perlu bisa diedit admin, tinggal dipromosikan jadi tabel tanpa
     * mengubah bentuk balasan ini.
     */
    public function jenisAgenda(): array
    {
        return [
            [
                'value' => 'RAPAT',
                'label' => 'Rapat',
                'deskripsi' => 'Rapat koordinasi panitia rekrutmen.',
                'ikon' => 'bi-people-fill',
                'warna' => '#4f46e5',
            ],
            [
                'value' => 'CAMPAIGN',
                'label' => 'Campaign / Publikasi',
                'deskripsi' => 'Publikasi lowongan ke kanal media & sosial.',
                'ikon' => 'bi-megaphone',
                'warna' => '#d97706',
            ],
            [
                'value' => 'SOSIALISASI',
                'label' => 'Sosialisasi',
                'deskripsi' => 'Sosialisasi program ke kampus / komunitas.',
                'ikon' => 'bi-broadcast',
                'warna' => '#0891b2',
            ],
            [
                'value' => 'SELEKSI',
                'label' => 'Tahap Seleksi',
                'deskripsi' => 'Pelaksanaan tahap seleksi (tes, wawancara, dsb).',
                'ikon' => 'bi-people',
                'warna' => '#059669',
            ],
            [
                'value' => 'EVALUASI',
                'label' => 'Evaluasi / Laporan',
                'deskripsi' => 'Evaluasi hasil & penyusunan laporan gelombang.',
                'ikon' => 'bi-clipboard-check',
                'warna' => '#7c3aed',
            ],
        ];
    }

    // ═══════════════════════ SUMBER MPP (DUMMY) ═══════════════════════

    /**
     * Daftar MPP / lowongan — disimulasikan sudah ditarik dari HRIS desktop.
     * READ ONLY di web: penambahan posisi terjadi di MPP desktop.
     *
     * Dipakai DUA tempat: halaman /karir/lowongan dan picker posisi di Program
     * Kegiatan (lewat options_mpp()). Saat integrasi HRIS siap, cukup ganti
     * isi method ini — pemanggilnya tidak perlu diubah.
     */
    private function lowonganAdmin(): array
    {
        return [
            [
                'id' => 'RC-001',
                'mppRef' => 'MPP/2026/SLS/011',
                'posisi' => 'Sales Executive (Pet Retail)',
                'departemen' => 'Sales & Distribution',
                'lokasi' => 'Palembang',
                'level' => 'Staff',
                'tempatKerja' => 'On-site',
                'tipeKerja' => 'Full-time',
                'kuota' => 4,
                'pelamar' => 37,
                'status' => 'BUKA',
                'tutup' => '15 Agu 2026',
                'kategori' => 'REKRUTMEN',
                'program' => 'Rekrutmen Reguler Q3 2026',
                'alurId' => 'ALR-REK',
                'alurNama' => 'Alur Rekrutmen Standar',
                'deskripsi' =>
                    'Menjalankan aktivitas penjualan produk pet retail ke jaringan mitra & pelanggan di area Palembang.',
                'tanggungJawab' => [
                    'Mencapai target penjualan bulanan',
                    'Membina hubungan dengan mitra retail',
                    'Melaporkan aktivitas penjualan mingguan',
                ],
                'persyaratan' => [
                    'Min. D3/S1 semua jurusan',
                    'Memiliki SIM C & kendaraan',
                    'Pengalaman sales min. 1 tahun (nilai plus)',
                ],
                'skill' => ['Negosiasi', 'Komunikasi', 'Product Knowledge'],
            ],
            [
                'id' => 'RC-002',
                'mppRef' => 'MPP/2026/TCH/002',
                'posisi' => 'Fullstack Web Developer',
                'departemen' => 'Technology',
                'lokasi' => 'Palembang',
                'level' => 'Mid',
                'tempatKerja' => 'Hybrid',
                'tipeKerja' => 'Full-time',
                'kuota' => 2,
                'pelamar' => 58,
                'status' => 'BUKA',
                'tutup' => '30 Agu 2026',
                'kategori' => 'REKRUTMEN',
                'program' => 'Rekrutmen Reguler Q3 2026',
                'alurId' => 'ALR-REK-TECH',
                'alurNama' => 'Alur Rekrutmen Teknis',
                'deskripsi' => 'Membangun dan memelihara aplikasi web internal EVO Group (Laravel + Vue).',
                'tanggungJawab' => [
                    'Mengembangkan fitur end-to-end',
                    'Menjaga kualitas & keamanan kode',
                    'Berkolaborasi dengan tim produk',
                ],
                'persyaratan' => ['S1 Informatika/sederajat', 'Menguasai PHP/Laravel & Vue', 'Paham REST API & Git'],
                'skill' => ['Laravel', 'Vue.js', 'MySQL', 'Git'],
            ],
            [
                'id' => 'RC-003',
                'mppRef' => 'MPP/2026/MKT/006',
                'posisi' => 'Digital Marketing Specialist',
                'departemen' => 'Marketing',
                'lokasi' => 'Palembang',
                'level' => 'Staff',
                'tempatKerja' => 'Hybrid',
                'tipeKerja' => 'Full-time',
                'kuota' => 2,
                'pelamar' => 44,
                'status' => 'BUKA',
                'tutup' => '20 Agu 2026',
                'kategori' => 'REKRUTMEN',
                'program' => 'Rekrutmen Reguler Q3 2026',
                'alurId' => 'ALR-REK',
                'alurNama' => 'Alur Rekrutmen Standar',
                'deskripsi' => 'Merancang & menjalankan kampanye pemasaran digital untuk brand EVO Group.',
                'tanggungJawab' => [
                    'Kelola kampanye ads & sosial media',
                    'Analisis performa kampanye',
                    'Buat konten pemasaran',
                ],
                'persyaratan' => ['S1 Marketing/Komunikasi', 'Menguasai Meta & Google Ads', 'Paham analitik digital'],
                'skill' => ['Meta Ads', 'Google Ads', 'Copywriting', 'Analytics'],
            ],
            [
                'id' => 'RC-004',
                'mppRef' => 'MPP/2026/SCM/003',
                'posisi' => 'Warehouse Supervisor',
                'departemen' => 'Supply Chain',
                'lokasi' => 'Banyuasin',
                'level' => 'Supervisor',
                'tempatKerja' => 'On-site',
                'tipeKerja' => 'Full-time',
                'kuota' => 1,
                'pelamar' => 21,
                'status' => 'PENUH',
                'tutup' => '05 Sep 2026',
                'kategori' => 'REKRUTMEN',
                'program' => 'Rekrutmen Reguler Q3 2026',
                'alurId' => 'ALR-REK',
                'alurNama' => 'Alur Rekrutmen Standar',
                'deskripsi' => 'Mengawasi operasional gudang pabrik Banyuasin & memastikan akurasi stok.',
                'tanggungJawab' => ['Supervisi tim gudang', 'Kontrol inventori & FIFO', 'Laporan operasional harian'],
                'persyaratan' => ['Min. D3 Logistik/sederajat', 'Pengalaman gudang min. 3 tahun', 'Menguasai WMS'],
                'skill' => ['Inventory', 'Leadership', 'WMS'],
            ],
            [
                'id' => 'RC-005',
                'mppRef' => 'MPP/2026/PRD/008',
                'posisi' => 'Production Quality Analyst',
                'departemen' => 'Production',
                'lokasi' => 'Banyuasin',
                'level' => 'Staff',
                'tempatKerja' => 'On-site',
                'tipeKerja' => 'Full-time',
                'kuota' => 3,
                'pelamar' => 29,
                'status' => 'BUKA',
                'tutup' => '25 Agu 2026',
                'kategori' => 'REKRUTMEN',
                'program' => 'Rekrutmen Reguler Q3 2026',
                'alurId' => 'ALR-REK-TECH',
                'alurNama' => 'Alur Rekrutmen Teknis',
                'deskripsi' => 'Mengawasi mutu produk manufaktur sesuai standar QA/QC.',
                'tanggungJawab' => [
                    'Inspeksi kualitas produk',
                    'Dokumentasi temuan mutu',
                    'Rekomendasi perbaikan proses',
                ],
                'persyaratan' => ['S1 Teknik Industri/Kimia', 'Paham QA/QC & GMP', 'Teliti & analitis'],
                'skill' => ['QA/QC', 'GMP', 'Analisis Data'],
            ],
            [
                'id' => 'RC-007',
                'mppRef' => 'MPP/2026/FIN/005',
                'posisi' => 'Finance & Accounting Staff',
                'departemen' => 'Finance',
                'lokasi' => 'Palembang',
                'level' => 'Staff',
                'tempatKerja' => 'On-site',
                'tipeKerja' => 'Full-time',
                'kuota' => 2,
                'pelamar' => 40,
                'status' => 'BUKA',
                'tutup' => '01 Sep 2026',
                'kategori' => 'REKRUTMEN',
                'program' => 'Rekrutmen Reguler Q3 2026',
                'alurId' => 'ALR-REK',
                'alurNama' => 'Alur Rekrutmen Standar',
                'deskripsi' => 'Mengelola pencatatan keuangan & pelaporan akuntansi harian.',
                'tanggungJawab' => ['Jurnal & rekonsiliasi', 'Laporan keuangan bulanan', 'Administrasi pajak dasar'],
                'persyaratan' => ['S1 Akuntansi', 'Menguasai Excel & software akuntansi', 'Paham perpajakan dasar'],
                'skill' => ['Akuntansi', 'Excel', 'Perpajakan'],
            ],
            [
                'id' => 'RC-008',
                'mppRef' => 'MPP/2026/MT/001',
                'posisi' => 'Management Trainee (EDP)',
                'departemen' => 'Lintas Divisi',
                'lokasi' => 'Palembang',
                'level' => 'Trainee',
                'tempatKerja' => 'On-site',
                'tipeKerja' => 'Full-time',
                'kuota' => 10,
                'pelamar' => 112,
                'status' => 'BUKA',
                'tutup' => '30 Sep 2026',
                'kategori' => 'MT',
                'program' => 'EVO Development Program (EDP) 2026',
                'alurId' => 'ALR-MT',
                'alurNama' => 'Alur Management Trainee',
                'deskripsi' =>
                    'Program kaderisasi calon pemimpin EVO Group melalui rotasi lintas divisi selama 12 bulan.',
                'tanggungJawab' => [
                    'Menjalani rotasi antar divisi',
                    'Mengerjakan capstone project',
                    'Mengikuti kelas kepemimpinan',
                ],
                'persyaratan' => [
                    'S1 semua jurusan, IPK min. 3.00',
                    'Usia maks. 25 tahun',
                    'Bersedia ditempatkan di seluruh area',
                ],
                'skill' => ['Leadership', 'Analitis', 'Adaptabilitas'],
            ],
            [
                'id' => 'RC-009',
                'mppRef' => 'MPP/2026/MT/002',
                'posisi' => 'Management Trainee (Operations)',
                'departemen' => 'Operations',
                'lokasi' => 'Banyuasin',
                'level' => 'Trainee',
                'tempatKerja' => 'On-site',
                'tipeKerja' => 'Full-time',
                'kuota' => 6,
                'pelamar' => 74,
                'status' => 'BUKA',
                'tutup' => '30 Sep 2026',
                'kategori' => 'MT',
                'program' => 'EVO Development Program (EDP) 2026',
                'alurId' => 'ALR-MT',
                'alurNama' => 'Alur Management Trainee',
                'deskripsi' => 'Jalur MT khusus operasional pabrik: produksi, mutu, dan rantai pasok.',
                'tanggungJawab' => [
                    'Rotasi lini produksi & gudang',
                    'Analisis efisiensi proses',
                    'Presentasi hasil improvement',
                ],
                'persyaratan' => [
                    'S1 Teknik Industri/Mesin/Kimia',
                    'IPK min. 3.00',
                    'Bersedia ditempatkan di Banyuasin',
                ],
                'skill' => ['Problem Solving', 'Lean', 'Komunikasi'],
            ],
            [
                'id' => 'RC-012',
                'mppRef' => 'MPP/2026/MT/003',
                'posisi' => 'Management Trainee (Sales & Marketing)',
                'departemen' => 'Sales & Distribution',
                'lokasi' => 'Palembang',
                'level' => 'Trainee',
                'tempatKerja' => 'On-site',
                'tipeKerja' => 'Full-time',
                'kuota' => 8,
                'pelamar' => 96,
                'status' => 'BUKA',
                'tutup' => '30 Sep 2026',
                'kategori' => 'MT',
                'program' => 'EVO Development Program (EDP) 2026',
                'alurId' => 'ALR-MT',
                'alurNama' => 'Alur Management Trainee',
                'deskripsi' => 'Jalur MT komersial: penjualan, distribusi, dan pengembangan pasar.',
                'tanggungJawab' => [
                    'Rotasi tim sales area',
                    'Analisis pasar & kompetitor',
                    'Menjalankan program penjualan',
                ],
                'persyaratan' => ['S1 semua jurusan, IPK min. 3.00', 'Memiliki SIM A/C', 'Bersedia perjalanan dinas'],
                'skill' => ['Negosiasi', 'Analisis Pasar', 'Presentasi'],
            ],
            [
                'id' => 'RC-013',
                'mppRef' => 'MPP/2026/MT/004',
                'posisi' => 'Management Trainee (Finance)',
                'departemen' => 'Finance & Accounting',
                'lokasi' => 'Palembang',
                'level' => 'Trainee',
                'tempatKerja' => 'On-site',
                'tipeKerja' => 'Full-time',
                'kuota' => 4,
                'pelamar' => 58,
                'status' => 'BUKA',
                'tutup' => '30 Sep 2026',
                'kategori' => 'MT',
                'program' => 'EVO Development Program (EDP) 2026',
                'alurId' => 'ALR-MT',
                'alurNama' => 'Alur Management Trainee',
                'deskripsi' => 'Jalur MT keuangan: akuntansi, anggaran, dan pengendalian internal.',
                'tanggungJawab' => [
                    'Rotasi tim akuntansi & anggaran',
                    'Menyusun analisis biaya',
                    'Membantu audit internal',
                ],
                'persyaratan' => ['S1 Akuntansi/Manajemen Keuangan', 'IPK min. 3.20', 'Menguasai Excel tingkat lanjut'],
                'skill' => ['Akuntansi', 'Analisis Biaya', 'Excel'],
            ],
            [
                'id' => 'RC-010',
                'mppRef' => 'MPP/2026/INT/001',
                'posisi' => 'Internship — Technology',
                'departemen' => 'Technology',
                'lokasi' => 'Palembang',
                'level' => 'Magang',
                'tempatKerja' => 'Hybrid',
                'tipeKerja' => 'Internship',
                'kuota' => 5,
                'pelamar' => 63,
                'status' => 'BUKA',
                'tutup' => '15 Sep 2026',
                'kategori' => 'INTERNSHIP',
                'program' => 'Internship Batch 2026',
                'alurId' => 'ALR-INT',
                'alurNama' => 'Alur Magang (Internship)',
                'deskripsi' => 'Magang bersertifikat di tim Technology: pengembangan aplikasi internal.',
                'tanggungJawab' => [
                    'Membantu pengembangan fitur',
                    'Menulis dokumentasi teknis',
                    'Mengikuti sprint tim',
                ],
                'persyaratan' => [
                    'Mahasiswa aktif semester 5+',
                    'Paham dasar pemrograman web',
                    'Durasi magang min. 3 bulan',
                ],
                'skill' => ['HTML/CSS', 'JavaScript', 'Git'],
            ],
            [
                'id' => 'RC-011',
                'mppRef' => 'MPP/2026/INT/002',
                'posisi' => 'Internship — Human Capital',
                'departemen' => 'Human Capital',
                'lokasi' => 'Palembang',
                'level' => 'Magang',
                'tempatKerja' => 'On-site',
                'tipeKerja' => 'Internship',
                'kuota' => 3,
                'pelamar' => 48,
                'status' => 'BUKA',
                'tutup' => '15 Sep 2026',
                'kategori' => 'INTERNSHIP',
                'program' => 'Internship Batch 2026',
                'alurId' => 'ALR-INT',
                'alurNama' => 'Alur Magang (Internship)',
                'deskripsi' => 'Magang di tim Human Capital: administrasi rekrutmen & employer branding.',
                'tanggungJawab' => [
                    'Bantu proses seleksi administrasi',
                    'Kelola arsip kandidat',
                    'Bantu konten employer branding',
                ],
                'persyaratan' => [
                    'Mahasiswa Psikologi/Manajemen semester 5+',
                    'Teliti & komunikatif',
                    'Durasi magang min. 3 bulan',
                ],
                'skill' => ['Administrasi', 'Komunikasi', 'Microsoft Office'],
            ],
            [
                'id' => 'RC-014',
                'mppRef' => 'MPP/2026/INT/003',
                'posisi' => 'Internship — Marketing',
                'departemen' => 'Marketing',
                'lokasi' => 'Palembang',
                'level' => 'Magang',
                'tempatKerja' => 'Hybrid',
                'tipeKerja' => 'Internship',
                'kuota' => 4,
                'pelamar' => 55,
                'status' => 'BUKA',
                'tutup' => '15 Sep 2026',
                'kategori' => 'INTERNSHIP',
                'program' => 'Internship Batch 2026',
                'alurId' => 'ALR-INT',
                'alurNama' => 'Alur Magang (Internship)',
                'deskripsi' => 'Magang di tim Marketing: produksi konten & riset kecil pasar.',
                'tanggungJawab' => [
                    'Membuat konten sosial media',
                    'Bantu riset kompetitor',
                    'Dokumentasi kegiatan brand',
                ],
                'persyaratan' => [
                    'Mahasiswa Komunikasi/DKV/Manajemen semester 5+',
                    'Menguasai Canva/Adobe dasar',
                    'Durasi magang min. 3 bulan',
                ],
                'skill' => ['Copywriting', 'Desain Dasar', 'Riset'],
            ],
            [
                'id' => 'RC-015',
                'mppRef' => 'MPP/2026/INT/004',
                'posisi' => 'Internship — Production',
                'departemen' => 'Production',
                'lokasi' => 'Banyuasin',
                'level' => 'Magang',
                'tempatKerja' => 'On-site',
                'tipeKerja' => 'Internship',
                'kuota' => 6,
                'pelamar' => 41,
                'status' => 'BUKA',
                'tutup' => '15 Sep 2026',
                'kategori' => 'INTERNSHIP',
                'program' => 'Internship Batch 2026',
                'alurId' => 'ALR-INT',
                'alurNama' => 'Alur Magang (Internship)',
                'deskripsi' => 'Magang di lini produksi pabrik Banyuasin: proses, mutu, dan K3.',
                'tanggungJawab' => ['Mengamati proses produksi', 'Bantu pencatatan mutu', 'Menyusun laporan magang'],
                'persyaratan' => ['Mahasiswa Teknik semester 5+', 'Bersedia kerja shift', 'Durasi magang min. 3 bulan'],
                'skill' => ['K3', 'Pencatatan Mutu', 'Kedisiplinan'],
            ],
        ];
    }
}
