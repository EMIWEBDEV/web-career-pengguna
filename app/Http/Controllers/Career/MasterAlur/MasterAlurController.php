<?php

namespace App\Http\Controllers\Career\MasterAlur;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Support\Career\AksesService;
use App\Support\Career\BatasIsi;
use App\Support\Career\KodeUnik;
use App\Support\Career\LamaranService;
use App\Support\Career\VersiAlur;
use App\Support\CareerShell;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Vinkla\Hashids\Facades\Hashids;

/**
 * WEB CAREER — MASTER TAHAPAN SELEKSI / ALUR (induk-detail: Alur + Alur_Tahap).
 * SPA + WEB. CRUD Query Builder + ResponseHelper + Log channel.
 */
class MasterAlurController extends Controller
{
    /** Kunci halaman untuk hak akses — sama dengan yang dipakai middleware rute. */
    private const PAGE = 'masterAlurPage';

    public function index()
    {
        return Inertia::render('Career/admin/master-alur/masterAlur', CareerShell::props('/master-alur', 'Master Tahapan Seleksi'));
    }

    public function list(Request $request)
    {
        try {
            // FILTER SERVER-SIDE — dipanggil Filter Panel di halaman (bukan saring
            // di browser): q (nama/kode/deskripsi), kategori, status, rentang tanggal.
            $q = trim((string) $request->query('q', ''));
            $status = strtoupper(trim((string) $request->query('status', '')));
            $dari = $request->query('dari');
            $sampai = $request->query('sampai');

            // ── GERBANG KATEGORI ────────────────────────────────────────────
            //
            // NULL = pengguna tidak dibatasi. Ini penjaga yang sebenarnya; chip di
            // layar cuma mengikutinya. Tanpa baris ini admin yang dijatah MT saja
            // tetap bisa membaca — dan menyunting — alur Rekrutmen hanya dengan
            // memanggil ?kategori=REKRUTMEN sendiri.
            $izin = AksesService::kategoriDiizinkan(self::PAGE);

            // Kategori yang DIMINTA disaring ke jatahnya: permintaan di luar jatah
            // jatuh kembali ke "semua yang boleh", bukan ditolak dengan galat —
            // yang mengetiknya umumnya tautan lama, bukan penyerang.
            $kategori = AksesService::kategoriDiminta(self::PAGE, $request->query('kategori'));

            $alur = DB::table('N_WEB_CAREERS_Master_Alur as a')
                ->leftJoin('N_WEB_CAREERS_Users as u', 'u.Id_Users', '=', 'a.Created_By_Id')
                ->when($izin, fn ($w) => $w->whereIn('a.Kategori', $izin))
                ->when($q !== '', fn ($w) => $w->where(function ($x) use ($q) {
                    $x->where('a.Nama', 'like', "%{$q}%")
                        ->orWhere('a.Kode', 'like', "%{$q}%")
                        ->orWhere('a.Deskripsi', 'like', "%{$q}%");
                }))
                ->when($kategori !== '', fn ($w) => $w->where('a.Kategori', $kategori))
                ->when(in_array($status, ['AKTIF', 'NONAKTIF'], true), fn ($w) => $w->where('a.Flag_Aktif', $status === 'AKTIF' ? 'Y' : 'N'))
                ->when($dari, fn ($w) => $w->whereDate('a.Created_At', '>=', $dari))
                ->when($sampai, fn ($w) => $w->whereDate('a.Created_At', '<=', $sampai))
                // TERBARU DI ATAS. Alur yang baru disusun adalah yang sedang
                // dikerjakan orang; menaruhnya di dasar daftar berarti ia harus
                // digulir dicari setiap kali, sementara alur lama yang jarang
                // disentuh menempati layar pertama.
                ->orderByDesc('a.Id_Master_Alur')
                ->select('a.*', 'u.Nama as Pembuat')
                ->get();

            $tahap = DB::table('N_WEB_CAREERS_Master_Alur_Tahap')->orderBy('Urutan')->get()->groupBy('Master_Alur_Id');
            // Sub-tes tiap tahap (1 tahap → N tes) — dipakai builder & mesin keputusan.
            $subTes = DB::table('N_WEB_CAREERS_Master_Alur_Tahap_Tes')->orderBy('Urutan')->get()->groupBy('Master_Alur_Tahap_Id');

            $rows = $alur->map(function ($a) use ($tahap, $subTes) {
                return [
                    'id' => Hashids::encode($a->Id_Master_Alur),
                    'kode' => $a->Kode,
                    'nama' => $a->Nama,
                    'kategori' => $a->Kategori,
                    'deskripsi' => $a->Deskripsi,
                    'status' => $a->Flag_Aktif === 'Y' ? 'AKTIF' : 'NONAKTIF',
                    'createdBy' => $a->Pembuat ?: $a->Created_By,
                    'createdAt' => $a->Created_At,
                    'stages' => collect($tahap->get($a->Id_Master_Alur, []))->map(fn ($t) => [
                        'kode' => $t->Kode,
                        'label' => $t->Label,
                        'tipe' => $t->Tipe_Tahap_Kode,
                        'provider' => $t->Provider,
                        'keputusan' => $t->Keputusan,
                        'formulirId' => $t->Formulir_Kode,
                        'sla' => $t->SLA,
                        // Mode keputusan tahap + daftar sub-tes (arsitektur multi-tes).
                        'mode' => $t->Mode_Keputusan_Kode ?? 'MANUAL_REVIEW',
                        // Aktivitas dalam tahap ini dikerjakan bersamaan atau
                        // harus berurutan. PARALEL = perilaku lama.
                        'urutanAktivitas' => $t->Urutan_Aktivitas ?? 'PARALEL',
                        'tests' => collect($subTes->get($t->Id_Master_Alur_Tahap, []))->map(fn ($x) => [
                            'label' => $x->Label,
                            // Tipe milik aktivitas ini APA ADANYA — tidak diisi
                            // diam-diam dengan tipe tahapnya. Builder memakai
                            // nilai ini sebagai isi kotak "Tipe Aktivitas";
                            // menggantinya di sini membuat layar tidak lagi
                            // menampilkan apa yang benar-benar tersimpan.
                            // Semua pemakai lain sudah punya cadangannya sendiri
                            // (`?: tipe tahap`), jadi baris ini aman apa adanya.
                            'tipe' => $x->Tipe_Tahap_Kode,
                            'provider' => $x->Provider,
                            'peran' => $x->Peran,
                            'wajib' => $x->Wajib === 'Y',
                            'ambang' => $x->Ambang_Batas,
                            // Unggahan oleh KANDIDAT (beda dari berkas hasil yang
                            // diunggah tim). Diatur per aktivitas: satu tahap bisa
                            // memuat tes offline yang menuntut unggahan dan
                            // wawancara yang tidak.
                            'unggahKandidat' => ($x->Unggah_Kandidat ?? 'T') === 'Y',
                            'unggahWajib' => ($x->Unggah_Wajib ?? 'T') === 'Y',
                            'unggahFormat' => $x->Unggah_Format,
                            'unggahMaksMb' => $x->Unggah_Maks_Mb !== null ? (int) $x->Unggah_Maks_Mb : null,
                            'unggahPetunjuk' => $x->Unggah_Petunjuk,
                            // Aktivitas internal (background check, cek referensi)
                            // yang dicatat tim tapi TIDAK ditampilkan ke kandidat.
                            'tampilKandidat' => ($x->Tampil_Kandidat ?? 'Y') === 'Y',
                            // Cara aktivitas ini dinilai — dari Master Mode Penilaian.
                            'lanjutMode' => $x->Lanjut_Mode,
                            'penilaianMode' => $x->Penilaian_Mode,
                            'penilaianOpsi' => $x->Penilaian_Opsi,
                            'nilaiMaks' => $x->Nilai_Maks !== null ? (float) $x->Nilai_Maks : null,
                            // Instruksi untuk kandidat — ditulis sekali di sini,
                            // disalin ke undangan tiap kandidat saat dijadwalkan.
                            'instruksiHtml' => $x->Instruksi_Html ?? null,
                            // Informasi biaya ditampilkan ke kandidat (bawaan
                            // per alur; kosong = tampil).
                            'tampilBiaya' => ($x->Tampil_Biaya ?? 'Y') !== 'T',
                            // Kalimat biaya alur ini (kosong = kalimat tipe).
                            'kalimatBiaya' => $x->Kalimat_Biaya ?? null,
                        ])->values(),
                        // Aturan pengumuman hasil tahap ini (lihat Batch 6).
                        'pengumuman' => $t->Mode_Pengumuman ?? 'OTOMATIS',
                        'jedaHari' => isset($t->Jeda_Pengumuman_Hari) ? $t->Jeda_Pengumuman_Hari : null,
                        'notifikasi' => ($t->Flag_Notifikasi ?? 'Y') === 'Y',
                        // Cut-off Talent Pool: bila 'Y', kandidat yang TIDAK lolos di tahap
                        // ini boleh dialihkan admin ke Talent Pool (bukan sekadar gugur).
                        'talentPool' => ($t->Flag_Talent_Pool ?? 'T') === 'Y',
                        // Upload berkas hasil tahap (MCU/Interview) — aktif & wajib/tidak.
                        'uploadHasil' => ($t->Flag_Upload_Hasil ?? 'T') === 'Y',
                        'wajibUpload' => ($t->Flag_Wajib_Upload ?? 'T') === 'Y',
                        // TITIK TUNTAS: tahap yang menutup proses seleksi.
                        'tuntas' => ($t->Flag_Tuntas ?? 'T') === 'Y',
                        // BATAS PENGISIAN formulir — lihat BatasIsi. NULL di
                        // basis data berarti tanpa batas.
                        'batasMode' => $t->Batas_Mode ?? BatasIsi::TANPA,
                        'batasHari' => isset($t->Batas_Hari) ? (int) $t->Batas_Hari : null,
                    ])->values(),
                ];
            })->values();

            // Kategori yang boleh dilihat pengguna ini — dipakai layar untuk
            // memutuskan apakah penyaring kategori perlu digambar sama sekali.
            // Diambil dari master, bukan dari data yang kebetulan ada: kategori
            // yang belum punya satu pun alur tetap harus bisa dipilih.
            return ResponseHelper::success([
                'data' => $rows,
                'kategori' => AksesService::tabKategori(self::PAGE),
            ], 'Data alur dimuat');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('Gagal memuat alur: '.$e->getMessage());

            return ResponseHelper::error('Gagal memuat data alur', 500);
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

    /** Mode pengumuman AKTIF: [Kode => butuhJeda(bool)]. Sumber tunggal master. */
    private function modeAktif(): array
    {
        return DB::table('N_WEB_CAREERS_Master_Mode_Pengumuman')
            ->where('Flag_Aktif', 'Y')
            ->pluck('Butuh_Jeda', 'Kode')
            ->map(fn ($v) => $v === 'Y')
            ->all();
    }

    /** Kode mode KEPUTUSAN yang aktif (bagaimana tahap menyimpulkan). */
    private function modeKeputusanAktif(): array
    {
        return DB::table('N_WEB_CAREERS_Master_Mode_Keputusan')->where('Flag_Aktif', 'Y')->pluck('Kode')->all();
    }

    /**
     * Mode urutan yang dipakai bila tahap hanya punya SATU aktivitas, atau
     * bila masternya belum terisi.
     *
     * Dipilih dari master: mode ber-`Flag_Berurutan='T'` pertama — "tidak ada
     * urutan yang mengunci" adalah keadaan paling aman, dan itulah perilaku
     * yang berlaku sebelum fitur ini ada. Konstanta di bawah hanya jaring
     * terakhir bila master benar-benar kosong.
     */
    private const URUTAN_BAWAAN = 'PARALEL';

    /** Kode mode LANJUT yang aktif (otomatis / dipicu admin). */
    private function modeLanjutAktif(): array
    {
        return DB::table('N_WEB_CAREERS_Master_Mode_Lanjut')->where('Flag_Aktif', 'Y')->orderBy('Urutan')->pluck('Kode')->all();
    }

    /**
     * Batas ukuran unggahan yang BOLEH DIPILIH admin — dari master.
     *
     * Termasuk nilai yang sudah dipakai alur mana pun walau kini nonaktif:
     * menyunting alur lama tidak boleh ditolak hanya karena pilihannya sudah
     * disempitkan sesudah alur itu dibuat. Yang berubah cukup pilihan BARU.
     *
     * @return int[]
     */
    private function batasUnggahSah(): array
    {
        $aktif = DB::table('N_WEB_CAREERS_Master_Batas_Unggah')
            ->where('Flag_Aktif', 'Y')->pluck('Maks_Mb');

        $terpakai = DB::table('N_WEB_CAREERS_Master_Alur_Tahap_Tes')
            ->whereNotNull('Unggah_Maks_Mb')->distinct()->pluck('Unggah_Maks_Mb');

        return $aktif->merge($terpakai)->map(fn ($v) => (int) $v)->unique()->values()->all();
    }

    /**
     * Batas bawaan bila permintaan tidak menyebutkannya: pilihan aktif TERKECIL.
     *
     * Angka terkecil, bukan terbesar — batas yang kelewat longgar baru
     * ketahuan saat berkas raksasa sudah masuk penyimpanan.
     */
    private static function batasUnggahBawaan(): int
    {
        $mb = DB::table('N_WEB_CAREERS_Master_Batas_Unggah')
            ->where('Flag_Aktif', 'Y')->min('Maks_Mb');

        return $mb !== null ? (int) $mb : 2;
    }

    /** Kode mode PENILAIAN yang aktif + perilakunya (Tipe_Nilai, Butuh_Opsi). */
    private function modePenilaian(): \Illuminate\Support\Collection
    {
        return DB::table('N_WEB_CAREERS_Master_Mode_Penilaian')->where('Flag_Aktif', 'Y')->get()->keyBy('Kode');
    }

    private function modePenilaianAktif(): array
    {
        return $this->modePenilaian()->keys()->all();
    }

    /** Kode mode URUTAN AKTIVITAS yang aktif. */
    private function modeUrutanAktif(): array
    {
        return DB::table('N_WEB_CAREERS_Master_Mode_Urutan')->where('Flag_Aktif', 'Y')->orderBy('Urutan')->pluck('Kode')->all();
    }

    /** Mode urutan yang TIDAK mengunci — dipakai sebagai nilai bawaan. */
    private function urutanBawaan(): string
    {
        return DB::table('N_WEB_CAREERS_Master_Mode_Urutan')
            ->where('Flag_Aktif', 'Y')
            ->where('Flag_Berurutan', 'T')
            ->orderBy('Urutan')
            ->value('Kode') ?: self::URUTAN_BAWAAN;
    }

    /** Tipe tahap AKTIF beserta kolom perilakunya — satu-satunya sumber. */
    private function tipeAktif()
    {
        return DB::table('N_WEB_CAREERS_Master_Tipe_Tahap')
            ->where('Flag_Aktif', 'Y')
            ->get(['Kode', 'Nama', 'Perilaku_Kode', 'Flag_Formulir', 'Flag_Upload_Hasil'])
            ->keyBy('Kode');
    }

    /**
     * SELURUH tipe beserta perilakunya — termasuk yang sudah dinonaktifkan.
     *
     * Dipakai untuk MENJALANKAN tahap, bukan untuk menawarkan pilihan. Perilaku
     * sebuah tipe tidak berubah hanya karena ia berhenti ditawarkan: tahap
     * bertipe DOCUMENT tetap tahap berbasis berkas. Dulu peta ini hanya berisi
     * tipe aktif, sehingga tahap yang tipenya sudah dipensiunkan diam-diam
     * jatuh ke perilaku bawaan — Flag_Upload_Hasil-nya hilang dan kotak unggah
     * hasilnya lenyap begitu alurnya disimpan ulang, tanpa satu pun galat.
     *
     * Kolomnya SENGAJA sama persis dengan tipeAktif(). Flag_Wajib_Tampil
     * memang dibaca di simpanTahap() tapi tidak pernah ikut terambil — jadi
     * gerbang "ujian online tak boleh disembunyikan" belum pernah menyala.
     * Menambahkannya di sini akan memperbaikinya sekaligus mengubah hasil
     * penyimpanan SETIAP alur, jauh di luar perkara duplikat; perbaikan itu
     * pantas berdiri sendiri, bukan menumpang perubahan ini.
     */
    /**
     * SELURUH KOLOM, bukan daftar pilih.
     *
     * Daftar kolom yang ditulis manual di sini sudah diam-diam membuang penanda
     * yang justru dipakai beberapa baris di bawahnya: `Flag_Wajib_Tampil` dibaca
     * saat menghitung Tampil_Kandidat, tetapi tidak pernah ikut terambil —
     * sehingga `?? 'T'` dengan patuh menghasilkan "tidak wajib tampil" untuk
     * SEMUA tipe, dan penjaganya tidak pernah sekali pun menyala. Akibatnya tes
     * online bisa tersimpan sebagai aktivitas tersembunyi: kandidat tidak pernah
     * melihat tombol mengerjakannya, dan tak ada galat apa pun yang memberitahu.
     *
     * Masternya belasan baris; mengambil semua kolomnya tidak lebih mahal, dan
     * menutup kelas kekeliruan yang tak terlihat sampai ada yang bertanya kenapa
     * setelannya "tidak berfungsi". Sama persis alasannya dengan
     * LamaranController::masterTipeTahap().
     */
    private function tipeSemua()
    {
        return DB::table('N_WEB_CAREERS_Master_Tipe_Tahap')
            ->get()
            ->keyBy('Kode');
    }

    /**
     * Tipe yang BOLEH tersimpan: yang aktif, ditambah yang sudah terpakai alur
     * mana pun walau kini nonaktif.
     *
     * Sepola dengan batasUnggahSah(). Menonaktifkan sebuah tipe berarti "jangan
     * ditawarkan lagi", bukan "alur yang sudah memakainya haram disentuh".
     * Tanpa ini, menyunting — apalagi menduplikat — alur lama ditolak dengan
     * "The selected stages.7.tipe is invalid": pesan yang tidak menyebut tahap
     * mana, tidak menyebut tipe apa, dan tidak bisa diperbaiki dari layar
     * karena pilihannya memang sudah tidak ada di daftar.
     *
     * Yang menyempit cukup pilihan BARU — daftar di layar tetap berisi tipe
     * aktif saja.
     *
     * @return string[]
     */
    private function tipeSah(): array
    {
        $aktif = DB::table('N_WEB_CAREERS_Master_Tipe_Tahap')->where('Flag_Aktif', 'Y')->pluck('Kode');

        $terpakai = DB::table('N_WEB_CAREERS_Master_Alur_Tahap')
            ->whereNotNull('Tipe_Tahap_Kode')->distinct()->pluck('Tipe_Tahap_Kode')
            ->merge(
                DB::table('N_WEB_CAREERS_Master_Alur_Tahap_Tes')
                    ->whereNotNull('Tipe_Tahap_Kode')->distinct()->pluck('Tipe_Tahap_Kode')
            );

        return $aktif->merge($terpakai)->map(fn ($v) => (string) $v)->unique()->values()->all();
    }

    /**
     * Formulir sudah bisa dirender ke kandidat? Form lama lewat Komponen_Kode,
     * form dinamis lewat versi PUBLISHED di Master_Formulir_Versi. Dipakai
     * gerbang simpanTahap supaya tahap tidak bisa menempel formulir yang masih
     * DRAFT — kandidat akan mentok di layar kosong kalau lolos.
     */
    private function formulirBisaDirender(string $kode): bool
    {
        $form = DB::table('N_WEB_CAREERS_Master_Formulir')->where('Kode', $kode)->first();
        if (! $form) {
            return false;
        }
        if (! empty($form->Komponen_Kode)) {
            return true;
        }

        return \App\Support\Career\FormulirSchema::punyaTabelVersi()
            && DB::table('N_WEB_CAREERS_Master_Formulir_Versi')
                ->where('Master_Formulir_Id', $form->Id_Master_Formulir)
                ->where('Status', 'PUBLISHED')
                ->exists();
    }

    private function rules(): array
    {
        $kodeModeAktif = array_keys($this->modeAktif());
        $kodeKeputusan = $this->modeKeputusanAktif();
        // Aktif + yang sudah terpakai — lihat tipeSah(). Alur lama tetap bisa
        // disunting & diduplikat walau salah satu tipenya sudah dipensiunkan.
        $kodeTipe = $this->tipeSah();

        return [
            'nama' => 'required|string|max:120',
            'kategori' => 'required|string|max:20',
            'deskripsi' => 'nullable|string|max:500',
            'stages' => 'nullable|array',
            // IDENTITAS tahap yang sudah ada. Dikirim balik oleh builder supaya
            // baris bisa dicocokkan lewat kode, bukan lewat nomor urut — lihat
            // simpanTahap(). Kosong / tidak dikenal = tahap baru.
            'stages.*.kode' => 'nullable|string|max:30',
            'stages.*.label' => 'required|string|max:120',
            // Tipe divalidasi terhadap Master Tipe Tahap yang AKTIF. Bila master
            // kosong (belum di-seed), jangan menolak semuanya — cukup batasi
            // panjangnya, supaya tak ada kode tipe yang dianggap istimewa di sini.
            'stages.*.tipe' => $kodeTipe ? ['required', Rule::in($kodeTipe)] : ['required', 'string', 'max:30'],
            // Provider TIDAK dikirim klien — diturunkan dari TIPE tiap aktivitas.
            'stages.*.formulirId' => 'nullable|string|max:30',
            // Mode keputusan tahap — dari master (aktif saja).
            'stages.*.mode' => ['nullable', Rule::in($kodeKeputusan ?: ['MANUAL_REVIEW'])],
            // Cara aktivitas tahap ini dikerjakan — dari Master Mode Urutan
            // (aktif saja), bukan daftar kode yang ditulis di sini.
            'stages.*.urutanAktivitas' => ['nullable', Rule::in($this->modeUrutanAktif() ?: [self::URUTAN_BAWAAN])],
            // Sub-tes tahap (1 tahap → N tes). Kosong = dibuatkan 1 default.
            'stages.*.tests' => 'nullable|array|max:20',
            'stages.*.tests.*.label' => 'required|string|max:120',
            // TIPE PER AKTIVITAS: satu tahap boleh mencampur ujian online, tes
            // manual, dan wawancara. Kosong = ikut tipe tahapnya.
            'stages.*.tests.*.tipe' => $kodeTipe ? ['nullable', Rule::in($kodeTipe)] : ['nullable', 'string', 'max:30'],
            'stages.*.tests.*.peran' => 'nullable|in:PENENTU,INFORMATIF',
            'stages.*.tests.*.ambang' => 'nullable|integer|min:0|max:1000',
            'stages.*.tests.*.unggahKandidat' => 'nullable|boolean',
            'stages.*.tests.*.unggahWajib' => 'nullable|boolean',
            // Daftar ekstensi dipisah koma. Dibatasi panjangnya, bukan isinya —
            // format yang sah berubah seiring kebutuhan, dan mengunci daftarnya
            // di sini berarti tiap format baru menuntut deploy.
            'stages.*.tests.*.unggahFormat' => 'nullable|string|max:120',
            // Dari MASTER, bukan rentang bebas 1–50. Rentang longgar membuat
            // admin bisa menyimpan batas yang tidak pernah ditawarkan layar,
            // dan kandidat menerima aturan yang tak seorang pun pernah pilih.
            'stages.*.tests.*.unggahMaksMb' => ['nullable', 'integer', Rule::in($this->batasUnggahSah() ?: [2])],
            'stages.*.tests.*.unggahPetunjuk' => 'nullable|string|max:500',
            // Aktivitas internal yang tidak ditampilkan di portal kandidat.
            'stages.*.tests.*.tampilKandidat' => 'nullable|boolean',
            // Cara aktivitas dinilai — dari Master Mode Penilaian (aktif saja).
            // Perpindahan ke aktivitas berikutnya — dari Master Mode Lanjut.
            'stages.*.tests.*.lanjutMode' => ['nullable', Rule::in($this->modeLanjutAktif() ?: ['OTOMATIS'])],
            'stages.*.tests.*.penilaianMode' => ['nullable', Rule::in($this->modePenilaianAktif() ?: ['TANPA_NILAI'])],
            'stages.*.tests.*.penilaianOpsi' => 'nullable|string|max:500',
            'stages.*.tests.*.nilaiMaks' => 'nullable|numeric|min:1|max:10000',
            // Instruksi untuk kandidat (paket pemeriksaan, persiapan, dokumen) —
            // HTML editor, disaring sebelum disimpan.
            'stages.*.tests.*.instruksiHtml' => 'nullable|string|max:30000',
            'stages.*.tests.*.tampilBiaya' => 'nullable|boolean',
            'stages.*.tests.*.kalimatBiaya' => 'nullable|string|max:1000',
            // Pengumuman hasil tahap — hanya mode AKTIF dari master (bukan hardcode).
            'stages.*.pengumuman' => ['nullable', Rule::in($kodeModeAktif ?: ['OTOMATIS'])],
            'stages.*.jedaHari' => 'nullable|integer|min:0|max:3650',
            'stages.*.notifikasi' => 'nullable|boolean',
            // Cut-off Talent Pool per tahap (fleksibel: bisa di tahap mana pun).
            'stages.*.talentPool' => 'nullable|boolean',
            // Upload berkas hasil tahap (MCU/Interview) + wajib/opsional.
            'stages.*.uploadHasil' => 'nullable|boolean',
            'stages.*.wajibUpload' => 'nullable|boolean',
            // TITIK TUNTAS — "tahap ini menutup proses seleksi".
            //
            // Sempat hilang dari daftar ini. validate() hanya mengembalikan key
            // yang PUNYA aturan, jadi `tuntas` yang dikirim layar ikut terbuang
            // sebelum sampai ke simpanTahap(): saklarnya bisa dinyalakan,
            // disimpan, dan Flag_Tuntas tetap 'T' tanpa satu pun pesan galat.
            'stages.*.tuntas' => 'nullable|boolean',
            // BATAS PENGISIAN formulir tahap — lihat BatasIsi & isiBatas().
            'stages.*.batasMode' => 'nullable|in:TANPA,OTOMATIS,MANUAL',
            'stages.*.batasHari' => 'nullable|integer|min:1|max:90',
        ];
    }

    /**
     * Aturan JADWAL PENGISIAN satu tahap (lihat BatasIsi).
     *
     * Berlaku untuk tahap bertipe Formulir MANA PUN — termasuk tahap 1, sesuai
     * keputusan user (dikendalikan dari Master Alur, bukan posisi tahap). Tahap
     * tanpa formulir disimpan NULL (= tanpa jadwal), bukan mode yang tak punya
     * akibat apa pun.
     */
    private function isiBatas(array $s, bool $berformulir): array
    {
        $mode = $s['batasMode'] ?? BatasIsi::TANPA;

        if (! $berformulir || ! BatasIsi::berjadwal($mode)) {
            return ['Batas_Mode' => null, 'Batas_Hari' => null, 'Batas_Aksi' => null];
        }

        if ($mode === BatasIsi::OTOMATIS && empty($s['batasHari'])) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'stages' => "Tahap \"{$s['label']}\" berbatas otomatis — isi berapa hari batasnya.",
            ]);
        }

        return [
            'Batas_Mode' => $mode,
            'Batas_Hari' => $mode === BatasIsi::OTOMATIS ? (int) $s['batasHari'] : null,
            // Lewat batas SELALU terkunci — tidak ada pilihan (keputusan user).
            'Batas_Aksi' => BatasIsi::KUNCI,
        ];
    }

    /**
     * Buang tahap SISA — yang urutannya melampaui jumlah tahap versi baru.
     *
     * Dulu seluruh tahap dihapus lalu dibuat ulang setiap kali alur disimpan.
     * Akibatnya Id_Master_Alur_Tahap selalu berganti, sementara setiap lamaran
     * yang sedang berjalan menyimpan id itu di Lamaran_Tahap.Master_Alur_Tahap_Id.
     * Sekali alurnya disunting, seluruh lamaran berjalan kehilangan rujukan ke
     * definisi tahapnya — tahap yang belum dimulai tidak lagi bisa mengambil
     * daftar aktivitasnya, dan yang muncul cuma satu aktivitas darurat bikinan
     * pastikanSubTes(). Sekarang barisnya DIPAKAI ULANG per urutan; yang
     * benar-benar dihapus hanya tahap yang memang dibuang dari alur.
     */
    private function hapusTahapSisa(int $alurId, int $jumlahBaru): void
    {
        $sisa = DB::table('N_WEB_CAREERS_Master_Alur_Tahap')
            ->where('Master_Alur_Id', $alurId)
            ->where('Urutan', '>', $jumlahBaru)
            ->pluck('Id_Master_Alur_Tahap')
            ->all();

        $this->buangTahap($sisa);
    }

    /**
     * Buang tahap yang TIDAK dipakai penyimpanan barusan.
     *
     * Menggantikan penghapusan berbasis nomor urut pada jalur simpan. Sejak
     * baris dicocokkan lewat KODE, sebuah tahap boleh berpindah posisi tanpa
     * berganti baris — dan "hapus semua yang urutannya melampaui jumlah baru"
     * akan ikut membuang baris yang barusan dipakai ulang, memutus rujukan
     * lamaran yang sedang berjalan di tahap itu.
     *
     * Daftar kosong sengaja TIDAK diartikan "hapus semua": alur yang disimpan
     * tanpa satu tahap pun ditolak lebih dulu oleh validasi, jadi daftar kosong
     * di sini hanya bisa berarti ada yang tidak beres — dan menghapus seluruh
     * tahap atas dasar itu jauh lebih mahal daripada tidak menghapus apa pun.
     *
     * @param  int[]  $idTerpakai
     */
    private function hapusTahapTakTerpakai(int $alurId, array $idTerpakai): void
    {
        if (! $idTerpakai) {
            return;
        }

        $sisa = DB::table('N_WEB_CAREERS_Master_Alur_Tahap')
            ->where('Master_Alur_Id', $alurId)
            ->whereNotIn('Id_Master_Alur_Tahap', $idTerpakai)
            ->pluck('Id_Master_Alur_Tahap')
            ->all();

        $this->buangTahap($sisa);
    }

    /** @param  int[]  $ids */
    private function buangTahap(array $ids): void
    {
        if (! $ids) {
            return;
        }

        DB::table('N_WEB_CAREERS_Master_Alur_Tahap_Tes')->whereIn('Master_Alur_Tahap_Id', $ids)->delete();
        DB::table('N_WEB_CAREERS_Master_Alur_Tahap')->whereIn('Id_Master_Alur_Tahap', $ids)->delete();
    }

    /**
     * Kode identitas untuk satu tahap yang sedang disimpan.
     *
     * URUTAN KEPUTUSANNYA, dari yang paling mengikat:
     *
     *   1. Kode kiriman yang MEMANG ADA di alur ini  → dipakai, barisnya juga
     *      dipakai ulang. Inilah yang membuat mengganti label tidak memindahkan
     *      kandidat yang sedang menjalaninya.
     *
     *   2. Kode kiriman yang belum ada di alur ini   → tetap dipakai, sebagai
     *      baris baru. Dua hal bergantung pada ini:
     *        · MENDUPLIKASI alur — dua alur yang sama-sama punya "PSIKOTES"
     *          sengaja digambar sebagai satu kolom di papan (AlurKolom::susun);
     *        · MELAHIRKAN VERSI BARU — v2 harus memakai kode yang sama dengan
     *          v1, sebab pemetaan migrasi kandidat berdiri di atas kode itu.
     *      Tetap dinormalkan dan tetap dijamin tidak kembar: kode adalah
     *      pengenal internal, bukan teks bebas.
     *
     *   3. Tidak ada kiriman → diturunkan dari labelnya, sekali seumur hidup.
     *
     * Dijamin tidak kembar di dalam satu alur: indeks UNIQUE (Master_Alur_Id,
     * Kode) menolak yang kedua, dan tanpa penomoran di sini yang terjadi bukan
     * pesan yang bisa dibaca admin melainkan galat SQL mentah.
     *
     * @param  array<string, bool>  $terpakai  kode yang diklaim pada simpan ini
     * @param  array<string, int>   $lama      kode yang sudah ada di alur ini
     */
    private static function kodeTahap(?string $kiriman, string $label, int $i, array $terpakai, array $lama): string
    {
        $kiriman = trim((string) $kiriman);

        if ($kiriman !== '' && isset($lama[$kiriman]) && ! isset($terpakai[$kiriman])) {
            return $kiriman;
        }

        if ($kiriman !== '') {
            $bersih = substr(trim(preg_replace('/[^A-Z0-9]+/', '_', strtoupper($kiriman)), '_'), 0, 30);
            if ($bersih !== '' && ! isset($terpakai[$bersih]) && ! isset($lama[$bersih])) {
                return $bersih;
            }
        }

        return self::kodeTahapBaru($label, $i, $terpakai, $lama);
    }

    /**
     * Kode yang diturunkan dari label — untuk tahap yang benar-benar baru.
     *
     * @param  array<string, bool>  $terpakai
     * @param  array<string, int>   $lama
     */
    private static function kodeTahapBaru(string $label, int $i, array $terpakai, array $lama): string
    {
        $dasar = trim(preg_replace('/[^A-Z0-9]+/', '_', strtoupper($label)), '_') ?: ('TAHAP_'.($i + 1));
        $dasar = substr($dasar, 0, 30);

        $bentrok = fn ($k) => isset($terpakai[$k]) || isset($lama[$k]);

        if (! $bentrok($dasar)) {
            return $dasar;
        }

        // Akhiran dipotong DARI DASARNYA, bukan ditambahkan di ujung: kolomnya
        // VARCHAR(30), dan menambah "_2" pada kode yang sudah 30 karakter akan
        // terpotong diam-diam kembali menjadi kode yang sama.
        for ($n = 2; $n <= 99; $n++) {
            $akhiran = '_'.$n;
            $kode = substr($dasar, 0, 30 - strlen($akhiran)).$akhiran;
            if (! $bentrok($kode)) {
                return $kode;
            }
        }

        // Sembilan puluh delapan tahap berlabel sama dalam satu alur bukan
        // keadaan yang perlu dilayani — tapi juga tidak boleh menabrak indeks.
        return substr($dasar, 0, 22).'_'.substr(strtoupper(bin2hex(random_bytes(4))), 0, 7);
    }

    /**
     * @return int jumlah aktivitas kandidat berjalan yang ikut menyesuaikan
     */
    private function simpanTahap(int $alurId, array $stages, ?int $userId, string $userName): int
    {
        // Tipe mana yang menuntut formulir — dari Master Tipe Tahap, supaya
        // menambah tipe baru tidak menuntut menyunting kode ini.
        $perilakuFormulir = DB::table('N_WEB_CAREERS_Master_Tipe_Tahap')
            ->pluck('Flag_Formulir', 'Kode')
            ->map(fn ($v) => $v === 'Y')
            ->all();

        $now = now();

        // Seluruh perilaku tipe dibaca dari master — tak ada kode tipe yang
        // ditulis di sini, jadi tipe baru cukup ditambah lewat Master Tipe Tahap.
        // Peta ini sengaja memuat tipe NONAKTIF juga: yang dijalankan di sini
        // adalah tahap yang sudah ada, dan perilakunya tidak boleh berubah
        // hanya karena tipenya berhenti ditawarkan. Lihat tipeSemua().
        $tipe = $this->tipeSemua();
        $adalahCat = fn (?string $kode) => ($tipe[$kode]->Perilaku_Kode ?? 'MANUAL') === 'CAT';

        // Peta mode -> butuhJeda: jeda hari hanya disimpan untuk mode ber-flag.
        $modeButuhJeda = $this->modeAktif();
        // Perilaku maju-otomatis tiap mode. Dibaca dari master supaya guard di
        // bawah tak perlu menyebut satu pun kode mode.
        $autoLanjut = DB::table('N_WEB_CAREERS_Master_Mode_Keputusan')
            ->where('Flag_Aktif', 'Y')->pluck('Auto_Lanjut', 'Kode');
        // Mode urutan aktivitas yang dipakai bila tahapnya cuma 1 aktivitas —
        // dibaca dari master, bukan kode 'PARALEL' yang ditulis di sini.
        $urutanBawaan = $this->urutanBawaan();
        // Perilaku tiap mode penilaian — dipakai memutuskan kolom mana yang
        // layak diisi (daftar pilihan vs batas angka).
        $modePenilaian = $this->modePenilaian();

        // ── TITIK TUNTAS: WAJIB ADA, DAN HANYA SATU ─────────────────────────
        //
        // WAJIB karena alur tanpa penanda ini tidak pernah bisa menyatakan
        // kandidat DITERIMA: lamaran berhenti di "Berjalan" selamanya dan kuota
        // posisi tak pernah terpotong. Kesalahannya tidak menimbulkan galat apa
        // pun — ia baru ketahuan berbulan-bulan kemudian saat ada yang
        // menghitung kursi terisi, dan saat itu seluruh angkatan sudah telanjur
        // berjalan dengan data yang salah.
        //
        // HANYA SATU karena dua titik tuntas berarti dua momen "kandidat
        // diterima" yang saling bertentangan — kuota terpotong pada yang mana
        // pun lebih dulu dilewati, dan laporan penerimaan tak bisa dipercaya.
        //
        // Keduanya dijaga DI SINI juga, bukan cuma di layar: permintaan bisa
        // datang tanpa lewat layar sama sekali.
        if ($stages && ! collect($stages)->contains(fn ($s) => ! empty($s['tuntas']))) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'stages' => 'Alur wajib punya satu tahap yang menutup proses seleksi. Tanpa itu kandidat tidak pernah dinyatakan DITERIMA dan kuota tidak pernah terpotong.',
            ]);
        }

        $tuntasTerpakai = false;

        /*
        |─────────────────────────────────────────────────────────────────────
        | KODE TAHAP = IDENTITAS, BUKAN TURUNAN LABEL
        |─────────────────────────────────────────────────────────────────────
        |
        | Dulu Kode dibangkitkan ULANG dari label pada SETIAP penyimpanan.
        | Akibatnya memperbaiki satu kata di judul tahap diam-diam mengganti
        | identitasnya: snapshot kandidat (Lamaran_Tahap.Kode) tidak lagi cocok
        | kolom mana pun, dan seluruh rombongan yang sedang berjalan di tahap
        | itu jatuh ke kolom cadangan "Tahap di luar alur" — di papan yang
        | dipakai mengambil keputusan, tanpa satu galat pun.
        |
        | Sekarang: kode yang SUDAH ADA dipertahankan, dan barisnya dicari
        | LEWAT KODE ITU — bukan lewat nomor urut. Bedanya terasa saat tahap
        | dipindah urutannya: identitas ikut pindah bersama tahapnya, sehingga
        | kandidat tetap berdiri di kolom yang benar. Pencocokan lewat nomor
        | (perilaku lama) akan menukar arti dua baris sekaligus.
        |
        | Kode baru hanya dibangkitkan untuk tahap yang memang baru, dan
        | dijamin tidak kembar DI DALAM SATU ALUR — indeks UNIQUE
        | (Master_Alur_Id, Kode) menegakkannya di basis data, dan menabraknya
        | akan memunculkan galat SQL mentah di hadapan admin. Dijaga di sini
        | supaya yang terjadi bukan galat, melainkan kode kedua yang sah.
        */
        $barisLama = DB::table('N_WEB_CAREERS_Master_Alur_Tahap')
            ->where('Master_Alur_Id', $alurId)
            ->get(['Id_Master_Alur_Tahap', 'Urutan', 'Kode']);

        $idPerKode = $barisLama->filter(fn ($r) => (string) $r->Kode !== '')
            ->pluck('Id_Master_Alur_Tahap', 'Kode')->all();
        $idPerUrutan = $barisLama->pluck('Id_Master_Alur_Tahap', 'Urutan')->all();

        $kodeTerpakai = [];   // kode yang sudah diklaim pada penyimpanan ini
        $idTerpakai = [];     // baris yang dipakai ulang / baru dibuat

        foreach (array_values($stages) as $i => $s) {
            $kode = self::kodeTahap($s['kode'] ?? null, $s['label'] ?? '', $i, $kodeTerpakai, $idPerKode);

            $kodeTerpakai[$kode] = true;

            // ── TIPE MELEKAT PADA AKTIVITAS, BUKAN PADA TAHAP ──────────────────
            //
            // Satu tahap boleh mencampur sifat: "FGD Dan Wawancara HR" bisa
            // berisi Psikotes (ujian online), Tes Menggambar (manual), dan
            // Wawancara. Kalau tipe hanya ada di level tahap, ketiganya ikut
            // dianggap ujian online — Wawancara pun diminta token & OTP HCLearn
            // yang tak pernah ada, dan kandidat diberi tahu "menunggu token"
            // untuk sesuatu yang sebenarnya sesi tatap muka.
            //
            // Maka tiap aktivitas membawa Tipe-nya sendiri; tipe tahap hanya
            // menjadi bawaan bila aktivitas tak menyebutkannya. Yang menentukan
            // CARA AKTIVITAS DIJALANKAN adalah Perilaku tipe aktivitas itu:
            // 'CAT' → ujian online yang dijadwalkan; selain itu → dikerjakan tim.
            $tests = array_values($s['tests'] ?? []);
            if (! $tests) {
                $tests = [[
                    'label' => $s['label'],
                    'peran' => 'PENENTU',
                    'ambang' => null,
                ]];
            }

            $tests = array_map(function ($t) use ($s, $adalahCat) {
                $t['tipe'] = $t['tipe'] ?? $s['tipe'];
                $t['provider'] = $adalahCat($t['tipe']) ? 'THIRD_PARTY' : 'INTERNAL';

                return $t;
            }, $tests);

            // Provider TAHAP = ada aktivitas ujian online di dalamnya. Dipakai
            // modul yang masih menyaring di level tahap (worklist, monitoring).
            $adaOnline = collect($tests)->contains(fn ($t) => $t['provider'] === 'THIRD_PARTY');
            $provider = $adaOnline ? 'THIRD_PARTY' : 'INTERNAL';

            $mode = $s['mode'] ?? ($adaOnline ? 'AUTO_SEMUA_LULUS' : 'MANUAL_REVIEW');

            // GUARD nol-PENENTU: mode auto-lanjut tanpa satu pun tes PENENTU akan
            // "lulus hampa" (maju tanpa ada yang menilai) — paksa ke MANUAL_REVIEW.
            $adaPenentu = collect($tests)->contains(fn ($t) => ($t['peran'] ?? 'PENENTU') === 'PENENTU');
            if (! $adaPenentu && ($autoLanjut[$mode] ?? 'N') === 'Y') {
                $mode = 'MANUAL_REVIEW';
            }

            // ── MODE YANG DIPILIH ADMIN DISIMPAN APA ADANYA ──────────────────
            //
            // Di sini dulu berdiri "GUARD GAGAL-MENGGANTUNG": tahap yang memuat
            // ujian online PENENTU dipaksa memakai mode ber-Auto_Gugur, dengan
            // alasan hasil CAT sudah final dan objektif sehingga kegagalannya
            // tak perlu menunggu admin.
            //
            // Alasannya masuk akal untuk ujian itu sendiri, tapi penerapannya
            // salah sasaran: gerbangnya diuji di level TAHAP (`contains`), jadi
            // kehadiran SATU psikotes online mencabut mode manual dari seluruh
            // tahap — termasuk dari FGD dan wawancara di dalamnya, dua hal yang
            // justru mustahil disimpulkan mesin.
            //
            // Lebih dari itu, ia menyangkal kenyataan bahwa kelulusan adalah
            // KEBIJAKAN, bukan aritmetika. Kandidat yang nilainya di bawah
            // ambang bisa saja tetap diloloskan setelah ditinjau ulang — dan
            // itu keputusan yang memang milik manusia. Mode yang dipaksa naik
            // membuat sistem menggugurkan lebih dulu, sebelum siapa pun sempat
            // menimbang.
            //
            // Maka penimpaannya DICABUT. Yang admin pilih di "Cara tahap ini
            // menyimpulkan" itulah yang berlaku, dan `evaluasiTahap()` sudah
            // membaca seluruh perilakunya dari master (Auto_Gugur, Auto_Lanjut,
            // Tunggu, Syarat_Lulus) tanpa satu pun kode mode yang ditulis di
            // dalamnya — jadi MANUAL benar-benar berarti manual.
            //
            // Yang hilang — kegagalan CAT tidak lagi menggugurkan sendiri pada
            // mode manual — tidak menjadi kandidat yang menggantung tanpa jejak:
            // tahapnya masuk SIAP_DIPUTUS, dan itu justru barisan kerja yang
            // memang ditampilkan worklist sebagai "perlu keputusan".
            //
            // Yang TIDAK dicabut adalah guard nol-PENENTU di atas: itu bukan
            // kebijakan melainkan keutuhan data — mode auto tanpa satu pun tes
            // penentu akan meloloskan orang tanpa ada yang menilainya.

            $pengumuman = $s['pengumuman'] ?? 'OTOMATIS';
            $jeda = ($modeButuhJeda[$pengumuman] ?? false) ? ($s['jedaHari'] ?? null) : null;

            // GERBANG: tipe yang menuntut formulir tidak boleh tersimpan TANPA
            // formulir. Tanpa ini, tahap bertipe FORM bisa disimpan kosong dan
            // kandidat berhenti selamanya di layar "menunggu formulir" — tidak
            // ada yang bisa diisi, dan tidak ada galat yang muncul di mana pun.
            // Persis yang terjadi pada alur "EVO MANAGEMENT 2026 Batch 1".
            if (($perilakuFormulir[$s['tipe'] ?? ''] ?? false) && empty($s['formulirId'])) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'stages' => "Tahap \"{$s['label']}\" bertipe formulir, jadi WAJIB memilih formulir yang harus diisi kandidat.",
                ]);
            }

            // GERBANG FORM BELUM PUBLISH: form dinamis yang masih DRAFT tidak
            // boleh menempel ke tahap — kandidat akan mentok di layar kosong
            // karena schema-nya belum ada yang berstatus PUBLISHED.
            if (! empty($s['formulirId']) && ! $this->formulirBisaDirender($s['formulirId'])) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'stages' => "Formulir pada tahap \"{$s['label']}\" belum punya versi yang dipublikasikan, jadi belum bisa diisi kandidat.",
                ]);
            }

            $tuntas = ! $tuntasTerpakai && ($s['tuntas'] ?? false);
            $tuntasTerpakai = $tuntasTerpakai || $tuntas;

            // Sakelar upload per tahap ditawarkan untuk tipe ini? (Master Tipe
            // Tahap.Flag_Opsi_Upload — sebelum kolomnya ada: ya.)
            $opsiUpload = ($tipe[$s['tipe']]->Flag_Opsi_Upload ?? 'Y') !== 'T';

            $isi = [
                'Master_Alur_Id' => $alurId,
                'Urutan' => $i + 1,
                'Kode' => substr($kode, 0, 30),
                'Label' => $s['label'],
                'Tipe_Tahap_Kode' => $s['tipe'],
                'Provider' => $provider,
                'Keputusan' => ($autoLanjut[$mode] ?? 'N') === 'Y' ? 'SYSTEM' : 'MANUAL',
                'Formulir_Kode' => $s['formulirId'] ?? null,
                // Jenis_Tes_Kode tidak lagi dipakai — kolomnya dibiarkan ada demi
                // riwayat, tapi alur baru tidak menulisinya. Lihat catatan Provider.
                'Jenis_Tes_Kode' => null,
                'SLA' => null, // field SLA dihapus dari builder
                'Mode_Keputusan_Kode' => $mode,
                // Urutan pengerjaan aktivitas di dalam tahap ini. Hanya berarti
                // bila aktivitasnya lebih dari satu; tahap 1-aktivitas dipaksa
                // ke mode yang tidak mengunci, supaya tidak menyimpan aturan
                // yang tak punya akibat apa pun lalu membingungkan saat alurnya
                // dibaca ulang.
                'Urutan_Aktivitas' => count($tests) > 1
                    ? ($s['urutanAktivitas'] ?: $urutanBawaan)
                    : $urutanBawaan,
                'Mode_Pengumuman' => $pengumuman,
                'Jeda_Pengumuman_Hari' => $jeda,
                // Konvensi proyek: 'Y' = ya, 'T' = tidak.
                'Flag_Notifikasi' => ($s['notifikasi'] ?? true) ? 'Y' : 'T',
                // Cut-off Talent Pool: aktif → worklist menampilkan tombol
                // "Masuk Talent Pool" saat memutus tahap ini.
                'Flag_Talent_Pool' => ($s['talentPool'] ?? false) ? 'Y' : 'T',
                // Upload berkas hasil — aktif bila admin menyalakannya, ATAU bila
                // tipe tahapnya memang berbasis berkas (flag dari Master Tipe
                // Tahap; dulu kode 'MCU' ditulis langsung di beberapa file).
                // Tipe yang sakelarnya disembunyikan (Flag_Opsi_Upload = 'T')
                // hanya mengikuti sifat tipenya — kiriman layar diabaikan.
                'Flag_Upload_Hasil' => (($opsiUpload && ($s['uploadHasil'] ?? false)) || ($tipe[$s['tipe']]->Flag_Upload_Hasil ?? 'T') === 'Y') ? 'Y' : 'T',
                'Flag_Wajib_Upload' => ($opsiUpload && ($s['wajibUpload'] ?? false)) ? 'Y' : 'T',
                'Flag_Tuntas' => $tuntas ? 'Y' : 'T',
                'Updated_At' => $now, 'Updated_By' => $userName, 'Updated_By_Id' => $userId,
            ];

            // Aturan batas pengisian — hanya bila kolomnya sudah dibuat
            // (docs/28-09-2026/04); sebelum itu menulisinya menggagalkan simpan.
            if (BatasIsi::siap()) {
                $isi += $this->isiBatas($s, (bool) ($perilakuFormulir[$s['tipe'] ?? ''] ?? false));
            }

            // PAKAI ULANG baris yang sudah ada — id-nya dipegang lamaran yang
            // sedang berjalan (Lamaran_Tahap.Master_Alur_Tahap_Id), jadi tidak
            // boleh berganti.
            //
            // Dicari LEWAT KODE lebih dulu; nomor urut hanya cadangan untuk
            // baris lama yang kodenya tak dikenali. Urutan sebagai kunci utama
            // adalah sumber kekeliruan yang lama: memindahkan tahap ke posisi
            // lain menukar arti dua baris sekaligus, dan snapshot kandidat ikut
            // salah tempat tanpa ada yang menyadarinya.
            $tahapId = $idPerKode[$kode] ?? ($idPerUrutan[$i + 1] ?? null);

            // Baris cadangan-per-urutan bisa saja sudah diklaim tahap lain pada
            // penyimpanan yang sama. Menimpanya dua kali akan membuat dua tahap
            // berbagi satu baris — yang kedua menghapus yang pertama.
            if ($tahapId && in_array($tahapId, $idTerpakai, true)) {
                $tahapId = null;
            }

            if ($tahapId) {
                DB::table('N_WEB_CAREERS_Master_Alur_Tahap')->where('Id_Master_Alur_Tahap', $tahapId)->update($isi);
            } else {
                $tahapId = DB::table('N_WEB_CAREERS_Master_Alur_Tahap')->insertGetId(
                    $isi + ['Created_At' => $now, 'Created_By' => $userName, 'Created_By_Id' => $userId],
                    'Id_Master_Alur_Tahap',
                );
            }

            $idTerpakai[] = (int) $tahapId;

            // Sub-tes diperlakukan sama: dipakai ulang per urutan, sisanya dibuang.
            $tesLama = DB::table('N_WEB_CAREERS_Master_Alur_Tahap_Tes')
                ->where('Master_Alur_Tahap_Id', $tahapId)
                ->pluck('Id_Master_Alur_Tahap_Tes', 'Urutan');

            foreach ($tests as $j => $t) {
                $isiTes = [
                    'Master_Alur_Tahap_Id' => $tahapId,
                    'Urutan' => $j + 1,
                    'Jenis_Tes_Kode' => null,
                    // Tipe & cara pelaksanaan MILIK AKTIVITAS INI sendiri.
                    'Tipe_Tahap_Kode' => $t['tipe'],
                    'Provider' => $t['provider'],
                    'Peran' => $t['peran'] ?? 'PENENTU',
                    // Default kebijakan: SEMUA tes wajib selesai. Kolom Wajib tetap
                    // ada di DB — bila nanti butuh opsional, buka lagi dari sini.
                    'Wajib' => 'Y',
                    'Ambang_Batas' => $t['ambang'] ?? null,
                    'Label' => $t['label'],
                    // Aturan unggahan kandidat. Format & ukuran ikut disimpan
                    // karena tes menggambar butuh gambar, tes tertulis butuh PDF,
                    // dan batas ukurannya berbeda — menyeragamkannya di kode
                    // berarti tiap kebutuhan baru menuntut deploy.
                    'Unggah_Kandidat' => ! empty($t['unggahKandidat']) ? 'Y' : 'T',
                    'Unggah_Wajib' => ! empty($t['unggahKandidat']) && ! empty($t['unggahWajib']) ? 'Y' : 'T',
                    'Unggah_Format' => ! empty($t['unggahKandidat']) ? (($t['unggahFormat'] ?? null) ?: 'pdf,jpg,jpeg,png') : null,
                    'Unggah_Maks_Mb' => ! empty($t['unggahKandidat']) ? (($t['unggahMaksMb'] ?? null) ?: self::batasUnggahBawaan()) : null,
                    'Unggah_Petunjuk' => ! empty($t['unggahKandidat']) ? (($t['unggahPetunjuk'] ?? null) ?: null) : null,
                    // AKTIVITAS INTERNAL — dicatat tim, tidak pernah tampil di
                    // portal kandidat (background check, cek referensi).
                    //
                    // Aktivitas yang MENUNTUT SESUATU dari kandidat dipaksa
                    // tetap terlihat: menyembunyikan ujian online berarti
                    // kandidat tak pernah melihat tombol mengerjakannya, dan
                    // menyembunyikan aktivitas yang meminta unggahan berarti ia
                    // diminta menyerahkan berkas lewat layar yang tidak ada.
                    // Keduanya jalan buntu yang tidak akan terlihat siapa pun
                    // sampai kandidat menelepon.
                    //
                    // Penandanya dari MASTER TIPE TAHAP (Flag_Wajib_Tampil),
                    // bukan perbandingan provider di sini — tipe baru yang
                    // menuntut tindakan kandidat cukup dinyalakan lewat master.
                    //
                    // JADWAL PRIVAT MENUTUPNYA — kecuali kandidat memang diminta
                    // mengunggah sesuatu di situ. Negosiasi gaji dijadwalkan tim
                    // untuk dirinya sendiri; memunculkannya di portal berarti
                    // memberitahu kandidat bahwa angkanya sedang dirundingkan,
                    // lalu membuatnya membaca tiap hari tanpa kabar sebagai
                    // penolakan yang tertunda. Pengecualian unggahan tetap
                    // dihormati: menyembunyikan layar yang justru meminta berkas
                    // darinya adalah jalan buntu yang sama seperti di atas.
                    //
                    // AKTIVITAS BERJADWAL JUGA TIDAK BOLEH DISEMBUNYIKAN.
                    //
                    // Flag_Jadwal='Y' berarti kandidat harus HADIR — MCU, wawancara,
                    // tes offline, tanda tangan kontrak. Menyembunyikannya membuat
                    // undangan tanggal dan tempatnya tidak punya tempat berlabuh di
                    // portal: kandidat menerima emailnya, membuka halamannya untuk
                    // memastikan, lalu membaca "menunggu dijadwalkan". Itu persis
                    // yang terjadi pada MCU, dan tidak seorang pun menyadarinya
                    // sampai kandidatnya bertanya.
                    'Tampil_Kandidat' => (
                        \App\Support\Career\JadwalPrivat::untuk($t['tipe'] ?? null)
                        && empty($t['unggahKandidat'])
                    ) ? 'T' : ((
                        ! empty($t['tampilKandidat'])
                        || ($tipe[$t['tipe'] ?? '']->Flag_Wajib_Tampil ?? 'T') === 'Y'
                        || ($tipe[$t['tipe'] ?? '']->Flag_Jadwal ?? 'T') === 'Y'
                        || ! empty($t['unggahKandidat'])
                    ) ? 'Y' : 'T'),
                    // ── MODE PENILAIAN ─────────────────────────────────────
                    // Hanya berarti untuk aktivitas yang HASILNYA DICATAT TIM.
                    // Ujian online nilainya datang dari HCLearn — menyetel mode
                    // penilaian di sana hanya menyimpan aturan yang tak pernah
                    // dipakai, lalu membingungkan saat alurnya dibaca ulang.
                    // Mode lanjut hanya berarti pada tahap BERURUTAN; disimpan
                    // apa adanya supaya setelannya tidak hilang bila tahapnya
                    // sempat dipindah ke paralel lalu dikembalikan lagi.
                    // `??` sebelum `?:` — permintaan yang tidak menyertakan
                    // kunci ini sama sekali (mis. dari klien lama atau muatan
                    // yang dirakit ulang) dulu melempar "Undefined array key".
                    'Lanjut_Mode' => ($t['lanjutMode'] ?? null) ?: null,
                    'Penilaian_Mode' => $adalahCat($t['tipe'] ?? null) ? null : (($t['penilaianMode'] ?? null) ?: null),
                    // Daftar pilihan hanya disimpan bila modenya memang menuntut
                    // (Butuh_Opsi='Y') — sisa daftar pada mode angka membuat
                    // layar menampilkan pilihan yang tidak berlaku.
                    'Penilaian_Opsi' => ($modePenilaian[$t['penilaianMode'] ?? '']->Butuh_Opsi ?? 'T') === 'Y'
                        ? (($t['penilaianOpsi'] ?? null) ?: null)
                        : null,
                    'Nilai_Maks' => ($modePenilaian[$t['penilaianMode'] ?? '']->Tipe_Nilai ?? 'NONE') === 'ANGKA'
                        ? (($t['nilaiMaks'] ?? null) ?: null)
                        : null,
                    'Updated_At' => $now, 'Updated_By' => $userName, 'Updated_By_Id' => $userId,
                ];

                // INSTRUKSI UNTUK KANDIDAT — hanya bila layar memang mengirimnya
                // dan kolomnya sudah ada (docs/30-09-2026/01). Klien lama yang
                // tidak mengenal kunci ini tidak boleh diam-diam menghapus
                // instruksi yang sudah ditulis. Gambar dibuang: kandidat tak bisa
                // membuka rute gambar admin, dan surel tidak memuatnya.
                if (array_key_exists('instruksiHtml', $t) && \App\Support\Career\UndanganJadwal::siapInstruksiAlur()) {
                    $isiTes['Instruksi_Html'] = \App\Support\Career\CatatanEksternal::saring($t['instruksiHtml'] ?? null);
                }
                // Tampilkan informasi biaya ke kandidat — sama: hanya bila
                // layar mengirimnya (klien lama tidak boleh mematikannya diam-diam).
                if (array_key_exists('tampilBiaya', $t) && $t['tampilBiaya'] !== null
                    && \App\Support\Career\UndanganJadwal::siapBiayaAlur()) {
                    $isiTes['Tampil_Biaya'] = filter_var($t['tampilBiaya'], FILTER_VALIDATE_BOOLEAN) ? 'Y' : 'T';
                }
                // Kalimat biaya alur ini — kosong berarti kalimat bawaan tipenya.
                if (array_key_exists('kalimatBiaya', $t) && \App\Support\Career\UndanganJadwal::siapKalimatBiayaAlur()) {
                    $isiTes['Kalimat_Biaya'] = trim((string) ($t['kalimatBiaya'] ?? '')) ?: null;
                }

                $tesId = $tesLama[$j + 1] ?? null;
                if ($tesId) {
                    DB::table('N_WEB_CAREERS_Master_Alur_Tahap_Tes')->where('Id_Master_Alur_Tahap_Tes', $tesId)->update($isiTes);
                } else {
                    DB::table('N_WEB_CAREERS_Master_Alur_Tahap_Tes')->insert(
                        $isiTes + ['Created_At' => $now, 'Created_By' => $userName, 'Created_By_Id' => $userId],
                    );
                }
            }

            DB::table('N_WEB_CAREERS_Master_Alur_Tahap_Tes')
                ->where('Master_Alur_Tahap_Id', $tahapId)
                ->where('Urutan', '>', count($tests))
                ->delete();
        }

        // Sisa dibuang menurut BARIS YANG TIDAK TERPAKAI, bukan menurut nomor
        // urut. Sejak baris dicocokkan lewat kode, tahap yang dipindah ke posisi
        // lebih awal tetap memakai barisnya yang lama — dan penghapusan
        // "Urutan > jumlah" akan ikut membuang baris yang barusan dipakai ulang.
        $this->hapusTahapTakTerpakai($alurId, $idTerpakai);

        // ATURAN PENGUMPULAN ikut berlaku bagi yang SEDANG BERJALAN.
        //
        // Tanpa ini, menyalakan "kandidat harus mengunggah" hanya berdampak pada
        // orang yang melamar SESUDAH alur disimpan. Yang sedang diproses tidak
        // pernah melihat kotak unggahnya, tim menunggu berkas yang portalnya tak
        // pernah minta, dan tak seorang pun tahu sampai kandidat menelepon.
        //
        // Yang ikut hanya aturan pengumpulan; syarat penilaian tetap beku pada
        // snapshot masing-masing. Lihat LamaranService::selaraskanPengumpulan().
        return LamaranService::selaraskanPengumpulan($alurId, $userName, $userId);
    }

    public function store(Request $request)
    {
        try {
            $data = $request->validate($this->rules());

            // Menyimpan BUKAN membaca: di sini kategori di luar jatah ditolak
            // tegas, tidak dialihkan diam-diam seperti pada penyaring. Menyimpan
            // ke kategori yang salah membuat alur muncul pada program orang lain,
            // dan pemiliknya tidak akan pernah tahu dari mana ia datang.
            if ($galat = $this->galatKategori($data['kategori'])) {
                return $galat;
            }
            $userId = session('career_auth.id');
            $userName = session('career_auth.nama', 'ADMIN');
            $now = now();

            // Kode dibuat memakai lebar kolom penuh; sufiks _N hanya bila bentrok.
            $kode = KodeUnik::buat('N_WEB_CAREERS_Master_Alur', 'Kode', $data['nama'], 30, 'ALUR');
            DB::transaction(function () use ($data, $kode, $userId, $userName, $now) {
                $id = DB::table('N_WEB_CAREERS_Master_Alur')->insertGetId([
                    'Kode' => $kode,
                    'Nama' => $data['nama'],
                    'Kategori' => $data['kategori'],
                    'Deskripsi' => $data['deskripsi'] ?? null,
                    'Flag_Aktif' => 'Y',
                    'Created_At' => $now, 'Created_By' => $userName, 'Created_By_Id' => $userId,
                    'Updated_At' => $now, 'Updated_By' => $userName, 'Updated_By_Id' => $userId,
                ], 'Id_Master_Alur');

                $this->simpanTahap($id, $data['stages'] ?? [], $userId, $userName);
            });

            Log::channel('web_career')->info("Master alur dibuat ({$kode}) oleh {$userName}");

            return ResponseHelper::success(null, 'Alur berhasil ditambahkan', 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ResponseHelper::error(collect($e->errors())->flatten()->first() ?? 'Data tidak valid', 422);
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('Gagal membuat alur: '.$e->getMessage());

            return ResponseHelper::error('Gagal menyimpan data', 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $realId = Hashids::decode($id)[0] ?? null;
            $row = DB::table('N_WEB_CAREERS_Master_Alur')->where('Id_Master_Alur', $realId)->first();
            if (! $row) {
                return ResponseHelper::error('Data tidak ditemukan', 404);
            }
            $data = $request->validate($this->rules());

            // Menyimpan BUKAN membaca: di sini kategori di luar jatah ditolak
            // tegas, tidak dialihkan diam-diam seperti pada penyaring. Menyimpan
            // ke kategori yang salah membuat alur muncul pada program orang lain,
            // dan pemiliknya tidak akan pernah tahu dari mana ia datang.
            if ($galat = $this->galatKategori($data['kategori'])) {
                return $galat;
            }
            $userId = session('career_auth.id');
            $userName = session('career_auth.nama', 'ADMIN');

            // ── MENYUNTING ALUR YANG SEDANG DIPAKAI = MELAHIRKAN VERSI ──────
            //
            // Selama alur belum pernah dijalani siapa pun, ia ditimpa apa adanya
            // (perilaku lama, dan memang benar: tidak ada rombongan untuk
            // dilindungi). Begitu ada satu lamaran saja di atasnya, menimpanya
            // berarti mengubah papan yang sedang dipakai mengambil keputusan.
            //
            // `migrasi` menentukan nasib yang SEDANG BERJALAN:
            //   'TIDAK' (bawaan) → tetap di versi lama sampai selesai
            //   'SEMUA'          → yang layak dipindahkan ke versi baru
            //
            // Keputusannya per-kandidat; lihat VersiAlur::nilaiKelayakan().
            $berversi = VersiAlur::siap() && VersiAlur::terpakai((int) $realId);
            $migrasi = strtoupper((string) $request->input('migrasi', 'TIDAK')) === 'SEMUA';

            $hasil = DB::transaction(function () use ($data, $realId, $userId, $userName, $berversi, $migrasi) {
                if (! $berversi) {
                    DB::table('N_WEB_CAREERS_Master_Alur')->where('Id_Master_Alur', $realId)->update([
                        'Nama' => $data['nama'],
                        'Kategori' => $data['kategori'],
                        'Deskripsi' => $data['deskripsi'] ?? null,
                        'Updated_At' => now(), 'Updated_By' => $userName, 'Updated_By_Id' => $userId,
                    ]);

                    // TIDAK dihapus lebih dulu: simpanTahap() memakai ulang baris
                    // lewat kodenya dan membuang sisanya sendiri, supaya id tahap
                    // tetap sama bagi lamaran yang sedang berjalan di alur ini.
                    return ['versi' => false, 'ikut' => $this->simpanTahap($realId, $data['stages'] ?? [], $userId, $userName)];
                }

                $baruId = VersiAlur::lahirkan((int) $realId, $data, $userId, $userName);

                // Tahap versi baru disusun dari kiriman layar — yang membawa
                // KODE tahap lama, sehingga identitasnya terjaga dan pemetaan
                // migrasi berdiri di atasnya.
                $this->simpanTahap($baruId, $data['stages'] ?? [], $userId, $userName);

                // Program, syarat gugur, jadwal — dibawa serta. Tanpa ini
                // syarat gugur berhenti berlaku tanpa satu galat pun.
                $rujukan = VersiAlur::pindahkanRujukan((int) $realId, $baruId, $userId, $userName);

                // Tanpa migrasi, yang "tidak ikut" adalah SELURUH lamaran yang
                // masih berjalan di versi lama — dihitung supaya pesannya
                // menyebut angka sebenarnya, bukan nol yang menyesatkan.
                $pindah = $migrasi
                    ? VersiAlur::migrasikan((int) $realId, $baruId, $userId, $userName)
                    : [
                        'ikut' => 0,
                        'tidak' => DB::table('N_WEB_CAREERS_Lamaran')
                            ->where('Master_Alur_Id', $realId)->where('Status', 'BERJALAN')->count(),
                    ];

                return ['versi' => true, 'baruId' => $baruId, 'rujukan' => $rujukan, 'pindah' => $pindah];
            });

            // Akibatnya DIKATAKAN, tidak diam-diam: menyunting alur yang sedang
            // dipakai orang bukan perbuatan sepele, dan admin berhak tahu
            // seberapa jauh dampaknya sebelum menutup halaman.
            if (empty($hasil['versi'])) {
                $ikut = (int) $hasil['ikut'];
                Log::channel('web_career')->info("Master alur #{$realId} diperbarui"
                    .($ikut ? " — {$ikut} aktivitas kandidat berjalan ikut menyesuaikan aturan pengumpulannya" : ''));

                return ResponseHelper::success(null, $ikut
                    ? "Alur diperbarui — {$ikut} aktivitas kandidat yang sedang berjalan ikut menyesuaikan aturan unggahannya."
                    : 'Alur diperbarui');
            }

            $p = $hasil['pindah'];
            $r = $hasil['rujukan'];
            Log::channel('web_career')->info(
                "Master alur #{$realId} melahirkan versi #{$hasil['baruId']} — "
                ."{$r['program']} program diarahkan, {$r['syarat']} syarat & {$r['jadwal']} jadwal dibawa, "
                ."migrasi: {$p['ikut']} ikut / {$p['tidak']} tidak."
            );

            return ResponseHelper::success(
                ['versiBaruId' => Hashids::encode($hasil['baruId'])] + $p,
                $migrasi
                    ? "Versi baru dibuat. {$p['ikut']} kandidat berjalan dipindahkan; {$p['tidak']} tetap di versi lama."
                    : "Versi baru dibuat dan dipakai program mulai sekarang. {$p['tidak']} kandidat yang sedang berjalan tetap menyelesaikan versi lama."
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ResponseHelper::error(collect($e->errors())->flatten()->first() ?? 'Data tidak valid', 422);
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal update alur #{$id}: ".$e->getMessage());

            return ResponseHelper::error('Gagal memperbarui data', 500);
        }
    }

    /**
     * GET .../master-alur/{id}/dampak — apa yang terjadi bila alur ini disimpan.
     *
     * Dipanggil layar SEBELUM menyimpan, supaya tombol "terapkan ke yang sedang
     * berjalan" bisa menyebutkan akibatnya lebih dulu — bukan sesudahnya, saat
     * sudah tidak bisa dibatalkan.
     *
     * Kode tahap versi baru dikirim layar apa adanya (`kode[]`): itulah yang
     * menentukan siapa masih punya tempat di versi baru dan siapa tidak.
     */
    public function dampak(Request $request, $id)
    {
        try {
            $realId = Hashids::decode($id)[0] ?? null;
            if (! $realId || ! DB::table('N_WEB_CAREERS_Master_Alur')->where('Id_Master_Alur', $realId)->exists()) {
                return ResponseHelper::error('Data tidak ditemukan', 404);
            }

            if (! VersiAlur::siap()) {
                return ResponseHelper::success(['berversi' => false], 'Versi alur belum aktif di basis data ini');
            }

            $terpakai = VersiAlur::terpakai((int) $realId);
            if (! $terpakai) {
                return ResponseHelper::success(
                    ['berversi' => false, 'ikut' => 0, 'tidak' => 0, 'rincian' => []],
                    'Alur ini belum dipakai lamaran mana pun — disimpan apa adanya.'
                );
            }

            $kode = collect((array) $request->input('kode', []))
                ->map(fn ($k) => trim((string) $k))->filter()->values()->all();

            return ResponseHelper::success(
                ['berversi' => true] + VersiAlur::pratinjau((int) $realId, $kode),
                'Dampak penyimpanan'
            );
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal menghitung dampak alur #{$id}: ".$e->getMessage());

            return ResponseHelper::error('Gagal menghitung dampak', 500);
        }
    }

    public function toggle(Request $request, $id)
    {
        try {
            $realId = Hashids::decode($id)[0] ?? null;
            $aktif = $request->boolean('aktif');
            $terpengaruh = DB::table('N_WEB_CAREERS_Master_Alur')->where('Id_Master_Alur', $realId)->update([
                'Flag_Aktif' => $aktif ? 'Y' : 'N',
                'Updated_At' => now(), 'Updated_By' => session('career_auth.nama', 'ADMIN'), 'Updated_By_Id' => session('career_auth.id'),
            ]);
            if (! $terpengaruh) {
                return ResponseHelper::error('Data tidak ditemukan', 404);
            }
            Log::channel('web_career')->info("Master alur #{$realId} status ".($aktif ? 'AKTIF' : 'NONAKTIF'));

            return ResponseHelper::success(null, 'Status diperbarui');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal toggle alur #{$id}: ".$e->getMessage());

            return ResponseHelper::error('Gagal mengubah status', 500);
        }
    }

    public function destroy($id)
    {
        try {
            $realId = Hashids::decode($id)[0] ?? null;
            $row = DB::table('N_WEB_CAREERS_Master_Alur')->where('Id_Master_Alur', $realId)->first();
            if (! $row) {
                return ResponseHelper::error('Data tidak ditemukan', 404);
            }
            DB::transaction(function () use ($realId) {
                // Alurnya memang dibuang seluruhnya di sini — jumlah tahap baru 0.
                $this->hapusTahapSisa($realId, 0);
                DB::table('N_WEB_CAREERS_Master_Alur')->where('Id_Master_Alur', $realId)->delete();
            });
            Log::channel('web_career')->info("Master alur #{$realId} dihapus");

            return ResponseHelper::success(null, 'Alur dihapus');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal hapus alur #{$id}: ".$e->getMessage());

            return ResponseHelper::error('Gagal menghapus data', 500);
        }
    }
}
