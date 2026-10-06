<?php

namespace App\Support\Career;

use App\Support\Career\KursiPosisi;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * WEB CAREER — Alur lamaran end-to-end.
 *
 * Menyatukan tiga hal yang harus terjadi berurutan dan tidak boleh terpisah:
 *   1. buatLamaran()      — kandidat melamar: lamaran dibuat, tahap seleksi
 *                           DISALIN dari alur (jadi catatan perjalanan yang beku).
 *   2. simpanPengisian()  — kandidat mengirim formulir sebuah tahap: jawaban
 *                           disimpan, field diproyeksikan, lalu mesin syarat
 *                           menilai dan MEREKOMENDASIKAN (bukan memutuskan).
 *   3. ketukPalu()        — admin memutuskan lolos/gugur; lamaran maju atau gugur.
 *
 * Mesin hanya merekomendasikan. Keputusan resmi tetap milik admin, kecuali
 * sebuah syarat sengaja disetel Aksi=GUGUR (mis. berkas wajib tak diunggah).
 */
class LamaranService
{
    /**
     * Data identitas kandidat — dipakai email, laporan PDF, dan Excel.
     *
     * ── KUNCINYA DARI MASTER, BUKAN DARI TEBAKAN ────────────────────────────
     *
     * Sebelumnya kunci formulir ditulis mati di sini (`lahir`, `kampus`,
     * `jkel`). Formulir dirancang lewat layar, dan formulir MT yang berlaku
     * sekarang menyimpannya sebagai `tanggal_lahir`, `nama_kampus`,
     * `jenis_kelamin` — sehingga SELURUH kandidat baru punya tanggal lahir dan
     * kampus kosong di email maupun laporan resmi, sementara kandidat lama
     * tetap terisi. Tidak ada galat, tidak ada log: hanya dokumen bolong,
     * bolong di kolom yang paling sering dibaca.
     *
     * Daftar kunci + urutan prioritasnya kini hidup di
     * N_WEB_CAREERS_Master_Kunci_Identitas — satu tempat, bisa ditambah tanpa
     * deploy saat formulir berikutnya memakai ejaan lain.
     *
     * ── SELURUH PENGISIAN DIBACA, YANG TERBARU MENANG ───────────────────────
     *
     * Dulu hanya pengisian ber-Sumber='PENDAFTARAN' yang PALING LAMA yang
     * dilihat. Data yang kandidat isi di formulir TAHAP (kelengkapan data diri
     * — tempat NIK, alamat, dan kerap pendidikan lengkap berada) tidak pernah
     * terbaca sama sekali. Sekarang semuanya dibaca, terbaru lebih dulu:
     * pembaruan yang kandidat kirim sendiri memang menggantikan yang lama.
     *
     * `institusi` SENGAJA TIDAK dipakai sebagai nama kampus — di data ia berisi
     * JENIS institusi ("Universitas", "SMK"). Fallback lama membuat laporan
     * berbunyi "Institusi: Universitas": terbaca benar sekilas, padahal sama
     * sekali bukan jawabannya.
     *
     * Yang dikembalikan PATH foto, bukan isinya: payload antrean harus kecil,
     * bytes-nya diambil dari GCS saat email benar-benar dikirim.
     */
    public static function dataKandidatEmail(int $lamaranId): array
    {
        $jawaban = self::jawabanGabungan($lamaranId);
        $ambil = fn (string $kode) => self::dariKunci($jawaban, $kode);

        // ── DUA CABANG MENEMUKAN BUG YANG SAMA ──────────────────────────────
        //
        // fix/formulir-mt-rekrutmen memperbaikinya dengan menambah ejaan baru
        // langsung di sini (`tanggal_lahir`, `nama_kampus`, `nama_institusi`).
        // Bentuk di bawah ini menyelesaikan hal yang sama lewat master
        // N_WEB_CAREERS_Master_Kunci_Identitas — dan KETIGA kunci itu sudah
        // terdaftar di sana, jadi tidak ada perilaku cabang itu yang hilang:
        //
        //     TGL_LAHIR : tanggal_lahir, lahir, tgl_lahir
        //     KAMPUS    : nama_kampus, nama_institusi, kampus, perguruan_tinggi
        //
        // SATU HAL SENGAJA TIDAK DIBAWA: `institusi` sebagai cadangan kampus.
        // Di data ia berisi JENIS institusi ("Universitas", "SMK"), bukan
        // namanya — sehingga laporan berbunyi "Institusi: Universitas". Terbaca
        // benar sekilas, padahal sama sekali bukan jawaban kandidatnya.
        return [
            'tglLahir' => $ambil('TGL_LAHIR'),
            'jkel' => $ambil('JKEL'),
            // Dicetak di kepala Biodata Kandidat. Lewat master juga, bukan
            // `$jawaban['nik']` langsung: kunci NIK sudah berbeda antar
            // formulir ('nik', 'no_ktp', 'nomor_ktp') dan menyebut satu saja
            // membuat kepala dokumen kosong pada sebagian angkatan.
            'nik' => $ambil('NIK'),
            'kampus' => $ambil('KAMPUS'),
            'tahunLulus' => $ambil('TAHUN_LULUS'),
            'jurusan' => $ambil('JURUSAN'),
            'jenjang' => $ambil('JENJANG'),
            'ipk' => $ambil('IPK'),
            // Dipakai saat tahun lulus memang tidak ditanyakan — formulir MT
            // hanya menanyakan status kemahasiswaan & semester berjalan.
            'statusStudi' => $ambil('STATUS_STUDI'),
            'semester' => $ambil('SEMESTER'),
            'hp' => $jawaban['hp'] ?? $jawaban['no_hp'] ?? null,
            'fotoPath' => DB::table('N_WEB_CAREERS_Formulir_Berkas as fb')
                ->join('N_WEB_CAREERS_Formulir_Pengisian as fp', 'fp.Id_Formulir_Pengisian', '=', 'fb.Formulir_Pengisian_Id')
                ->where('fp.Lamaran_Id', $lamaranId)
                ->where('fb.Field_Key', 'foto_verifikasi')
                ->orderByDesc('fb.Id_Formulir_Berkas')
                ->value('fb.Path_File'),
        ];
    }

    /**
     * Satu nilai identitas (mis. KAMPUS) untuk BANYAK lamaran sekaligus.
     *
     * Worklist menyaring kandidat per kampus, dan kampus tidak pernah menjadi
     * kolom tabel: ia jawaban formulir. Memanggil dataKandidatEmail() per
     * kandidat berarti dua kueri kali jumlah pelamar — pada program berisi 400
     * lamaran, papan seleksi berhenti terbuka. Di sini seluruhnya diambil dalam
     * SATU kueri, lalu dilebur dengan aturan yang sama persis dengan
     * jawabanGabungan(): urutan lama → baru, jawaban kosong tidak menimpa.
     *
     * @param  int[]  $lamaranIds
     * @return \Illuminate\Support\Collection<int,string> dikunci Id_Lamaran
     */
    public static function identitasPerLamaran(array $lamaranIds, string $kode, ?\Illuminate\Support\Collection $pengisian = null): \Illuminate\Support\Collection
    {
        if (! $lamaranIds) {
            return collect();
        }

        return ($pengisian ?? self::pengisianPerLamaran($lamaranIds))
            ->map(function ($rows) use ($kode) {
                $gabung = [];
                foreach ($rows as $r) {
                    foreach ($r->jawaban as $k => $v) {
                        if ($v === null || $v === '' || $v === []) {
                            continue;
                        }
                        $gabung[$k] = $v;
                    }
                }

                return self::dariKunci($gabung, $kode);
            })
            ->filter(fn ($v) => $v !== null && $v !== '');
    }

    /**
     * Pengisian formulir BANYAK lamaran sekaligus — [Lamaran_Id => baris…].
     *
     * Urut lama → baru (Waktu_Kirim, lalu Id), jawabannya sudah diurai di
     * `->jawaban`. Papan worklist membacanya SEKALI lalu membagikannya ke
     * penyaring kampus (identitasPerLamaran) dan nama resmi kandidat: dulu
     * keduanya membaca Jawaban_Json yang sama persis, masing-masing sekali —
     * dua kali isi seluruh formulir seluruh pelamar, setiap papan dibuka.
     *
     * BERPOTONG — lihat MetrikRekrutmen::potongIn(). Worklist memanggilnya
     * dengan SELURUH pelamar satu program; di atas 2100 id, SQL Server
     * menolak kuerinya bulat-bulat dan papannya gagal dimuat.
     *
     * @param  int[]  $lamaranIds
     */
    public static function pengisianPerLamaran(array $lamaranIds): \Illuminate\Support\Collection
    {
        if (! $lamaranIds) {
            return collect();
        }

        $kolom = ['Id_Formulir_Pengisian', 'Lamaran_Id', 'Jawaban_Json'];
        // Versi formulir ikut bila ada — pengambil nama resmi memakainya untuk
        // membaca snapshot skema sekali per versi, bukan sekali per pengisian.
        if (Skema::adaKolom('N_WEB_CAREERS_Formulir_Pengisian', 'Master_Formulir_Versi_Id')) {
            $kolom[] = 'Master_Formulir_Versi_Id';
        }

        return \App\Support\Career\MetrikRekrutmen::potongIn(
            fn () => DB::table('N_WEB_CAREERS_Formulir_Pengisian')
                ->orderBy('Waktu_Kirim')            // lama → baru
                ->orderBy('Id_Formulir_Pengisian')
                ->select($kolom),
            'Lamaran_Id',
            $lamaranIds,
        )->each(function ($r) {
            $r->jawaban = json_decode($r->Jawaban_Json ?: '{}', true) ?: [];
        })->groupBy('Lamaran_Id');
    }

    /**
     * Jawaban SELURUH formulir sebuah lamaran, dilebur jadi satu peta.
     *
     * Yang TERBARU ditumpuk paling akhir sehingga ia menang atas yang lama —
     * kandidat yang membetulkan nomor teleponnya di formulir tahap berarti yang
     * lama sudah tidak ia akui. Nilai kosong tidak ikut menimpa: jawaban
     * kosong pada formulir baru tidak boleh menghapus jawaban lama yang terisi.
     */
    private static function jawabanGabungan(int $lamaranId): array
    {
        $rows = DB::table('N_WEB_CAREERS_Formulir_Pengisian')
            ->where('Lamaran_Id', $lamaranId)
            ->orderBy('Waktu_Kirim')            // lama → baru
            ->orderBy('Id_Formulir_Pengisian')
            ->pluck('Jawaban_Json');

        $gabung = [];
        foreach ($rows as $json) {
            foreach ((json_decode($json ?: '{}', true) ?: []) as $k => $v) {
                if ($v === null || $v === '' || $v === []) {
                    continue;
                }
                $gabung[$k] = $v;
            }
        }

        return $gabung;
    }

    /**
     * ALAMAT EMAIL KANDIDAT — SATU SUMBER KEBENARAN, DARI DATABASE.
     *
     * Alamat tujuan TIDAK PERNAH boleh datang dari layar. Layar bisa basi,
     * bisa menampilkan kandidat lain yang baru saja dibuka, dan permintaannya
     * bisa disusun sendiri oleh siapa pun yang punya akses admin — sekali
     * alamat tujuan diterima mentah dari klien, surat berisi keputusan
     * seleksi orang lain bisa diarahkan ke mana saja. Layar boleh MEMERIKSA
     * (lihat emailHasilUlang), tidak boleh MENENTUKAN.
     *
     * ── KENAPA BUKAN CUKUP Users.Email ──────────────────────────────────
     *
     * Karena ada lamaran yang barisan akunnya sudah tidak ada. Log yang
     * melahirkan metode ini berbunyi:
     *
     *     [APPLYMAIL] user #185 / email tidak ada — dilewati.
     *
     * Kandidatnya nyata, lamarannya berjalan, dan alamat emailnya terpampang
     * di layar admin — dibaca dari jawaban formulirnya. Yang tidak ada cuma
     * baris akunnya. Job diam-diam melewatinya, dan tak ada satu pun surat
     * keputusan yang pernah sampai.
     *
     * Urutannya: akun dulu (di sanalah alamat yang ia pakai masuk dan
     * diverifikasi), baru jawaban formulir sebagai cadangan.
     *
     * ── KUNCI FORMULIRNYA ───────────────────────────────────────────────
     *
     * Dibaca dari Master Kunci Identitas dengan kode EMAIL, sama seperti
     * NAMA/KAMPUS/TGL_LAHIR. Selama kode itu belum ada isinya di master,
     * daftar cadangan di bawah yang dipakai — dan begitu barisnya ditambahkan,
     * masterlah yang menang tanpa menyentuh berkas ini.
     */
    public static function emailKandidat(?int $userId, ?int $lamaranId = null, ?string $kodeLamaran = null): ?string
    {
        $akun = $userId
            ? DB::table('N_WEB_CAREERS_Users')->where('Id_Users', $userId)->value('Email')
            : null;

        if (self::emailSah($akun)) {
            return trim((string) $akun);
        }

        // Lamaran mana yang jawabannya dibaca. Kode lamaran lebih tepat daripada
        // "punya user ini": satu orang bisa melamar dua lowongan, dan alamat yang
        // benar adalah yang ia tulis pada lamaran YANG SEDANG dikabari.
        $lamaranId ??= $kodeLamaran
            ? DB::table('N_WEB_CAREERS_Lamaran')->where('Kode', $kodeLamaran)->value('Id_Lamaran')
            : null;

        $lamaranId ??= $userId
            ? DB::table('N_WEB_CAREERS_Lamaran')->where('Id_Users', $userId)->orderByDesc('Id_Lamaran')->value('Id_Lamaran')
            : null;

        if (! $lamaranId) {
            return null;
        }

        $jawaban = self::jawabanGabungan((int) $lamaranId);
        $kunci = self::kunciIdentitas('EMAIL') ?: self::EMAIL_CADANGAN;

        foreach ($kunci as $k) {
            $v = $jawaban[$k] ?? null;
            if (self::emailSah($v)) {
                return trim((string) $v);
            }
        }

        return null;
    }

    /**
     * Kunci formulir yang menanyakan email — CADANGAN, dipakai hanya selama
     * Master Kunci Identitas belum punya baris berkode EMAIL.
     */
    public const EMAIL_CADANGAN = ['email', 'email_aktif', 'alamat_email', 'e_mail', 'email_pribadi'];

    /**
     * Terbaca sebagai alamat email? Bukan sekadar "tidak kosong".
     *
     * Jawaban formulir diketik kandidat sendiri, dan kolom email kerap berisi
     * "-", "tidak punya", atau nomor telepon. Mengirim ke isian semacam itu
     * berakhir sebagai pentalan yang menumpuk di reputasi domain pengirim —
     * ongkos yang ditanggung SELURUH kandidat lain.
     */
    private static function emailSah($v): bool
    {
        return is_string($v) && filter_var(trim($v), FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Nilai pertama yang berisi menurut urutan kunci di master.
     *
     * ── KENAPA IKUT MELIHAT KE DALAM REPEATER ──────────────────────────────
     *
     * Formulir rekrutmen tidak menyimpan kampus sebagai isian datar. Ia ada di
     * dalam `pendidikan_formal`, sebuah daftar berulang:
     *
     *     "pendidikan_formal": [
     *         { "jenjang": "S1", "nama_institusi": "…", "jurusan": "…", … }
     *     ]
     *
     * `nama_institusi` memang sudah terdaftar sebagai kunci KAMPUS, tapi
     * pencarian yang cuma melihat tingkat teratas tidak akan pernah
     * menemukannya — dan kolom Kampus di worklist kosong untuk SETIAP pelamar
     * yang mengisi formulir rekrutmen, tanpa satu pun galat yang menjelaskan
     * kenapa. Hal yang sama berlaku untuk jurusan, jenjang, IPK, dan tahun
     * lulus; keempatnya tinggal di daftar yang sama.
     *
     * ── KENAPA BARIS PERTAMA ───────────────────────────────────────────────
     *
     * Formulirnya sendiri meminta "mulai dari jenjang terakhir", jadi baris
     * pertama adalah pendidikan tertinggi — persis yang dimaksud orang saat
     * bertanya "kampusnya mana".
     */
    private static function dariKunci(array $jawaban, string $kode): ?string
    {
        $kunci = self::kunciIdentitas($kode);

        // 1 · Isian datar. Didahulukan: kalau formulirnya memang menyediakan
        //     isian tersendiri, itulah jawaban yang paling disengaja.
        foreach ($kunci as $k) {
            $v = $jawaban[$k] ?? null;
            if (is_array($v)) {
                $v = implode(', ', array_filter($v, 'is_scalar'));
            }
            if ($v !== null && trim((string) $v) !== '') {
                return trim((string) $v);
            }
        }

        // 2 · Di dalam daftar berulang. Hanya daftar yang benar-benar memuat
        //     kuncinya yang cocok — baris keluarga memakai kel_utama_nama,
        //     riwayat kerja memakai nama_perusahaan, jadi tidak ada yang
        //     saling tertukar.
        foreach ($jawaban as $isi) {
            if (! is_array($isi)) {
                continue;
            }

            foreach ($isi as $baris) {
                if (! is_array($baris)) {
                    continue;
                }

                foreach ($kunci as $k) {
                    $v = $baris[$k] ?? null;
                    if ($v !== null && ! is_array($v) && trim((string) $v) !== '') {
                        return trim((string) $v);
                    }
                }
            }
        }

        return null;
    }

    /**
     * Daftar kunci formulir untuk satu jenis identitas — dari master, di-cache.
     * Satu kueri per permintaan untuk SEMUA jenis; IdentitasKandidat membaca
     * kunci NAMA dari sini juga, bukan dari kuerinya sendiri.
     */
    public static function kunciIdentitas(string $kode): array
    {
        static $cache = null;

        $cache ??= DB::table('N_WEB_CAREERS_Master_Kunci_Identitas')
            ->where('Flag_Aktif', 'Y')
            ->orderBy('Urutan')
            ->get(['Kode', 'Field_Key'])
            ->groupBy('Kode')
            ->map(fn ($g) => $g->pluck('Field_Key')->all());

        return $cache[$kode] ?? [];
    }

    /**
     * Kandidat melamar sebuah posisi.
     *
     * @param  string|null  $gugurAlasan  bila diisi: lamaran langsung ditandai
     *                                    TIDAK LOLOS pada tahap pertama (mis. knock-out saat finalisasi ApplyForm).
     *                                    Lamaran TETAP tercatat agar muncul di "Lamaran Saya" berstatus Gugur.
     * @return array{ok:bool, pesan:string, lamaranId?:int}
     */
    public function buatLamaran(int $userId, int $pembukaanId, int $posisiId, ?int $userAdminId = null, ?string $gugurAlasan = null, ?array $jawaban = null): array
    {
        // Validasi dipisah agar controller dan job memakai aturan yang sama tanpa
        // terus menambah ukuran LamaranService yang sudah menjadi hot spot konflik.
        $target = app(LamaranTargetValidator::class)->validasi($pembukaanId, $posisiId);
        if (! $target['ok']) {
            return $target;
        }

        $pembukaan = $target['pembukaan'];
        $program = $target['program'];
        $posisi = $target['posisi'];
        $programId = $program->Id_Program;

        // Satu kandidat tidak boleh melamar posisi yang sama dua kali — apa pun
        // channel-nya (constraint DB: Users + Program + Posisi).
        $sudah = DB::table('N_WEB_CAREERS_Lamaran')
            ->where('Id_Users', $userId)
            ->where('Program_Id', $programId)
            ->where('Program_Posisi_Id', $posisiId)
            ->first();
        if ($sudah) {
            return ['ok' => true, 'pesan' => 'Anda sudah melamar posisi ini.', 'lamaranId' => $sudah->Id_Lamaran];
        }

        // ── KELAYAKAN (aturan jalur MT/REKRUTMEN + cooldown, master DB) ──
        // Dinilai berdasarkan RIWAYAT akun. Blok sebelum lamaran dibuat.
        $kelayakan = (new KelayakanLamaran)->cek($userId, $program->Kategori);
        if (! $kelayakan['boleh']) {
            return ['ok' => false, 'pesan' => $kelayakan['alasan'], 'kode' => $kelayakan['kode'] ?? 'TIDAK_LAYAK'];
        }

        // Alur seleksi yang BERLAKU SAAT MELAMAR — di-snapshot supaya perubahan
        // alur program tidak mengacak proses kandidat yang sudah berjalan.
        $alur = DB::table('N_WEB_CAREERS_Master_Alur')->where('Kode', $program->Alur_Kode)->first();
        $tahap = $alur
            ? DB::table('N_WEB_CAREERS_Master_Alur_Tahap')
                ->where('Master_Alur_Id', $alur->Id_Master_Alur)
                ->orderBy('Urutan')
                ->get()
            : collect();

        $kode = 'LMR-'.strtoupper(Str::random(8));
        $now = now();
        $nama = session('career_auth.nama', 'KANDIDAT');

        $tahapPertama = $tahap->values()->first();
        $labelTahap1 = $tahapPertama->Label ?? 'Seleksi Administrasi';

        // Jangan pernah membuat lamaran tanpa tempat menyimpan formulirnya.
        // Kegagalan konfigurasi harus terlihat oleh admin/kandidat, bukan berubah
        // menjadi lamaran kosong yang tampak sukses.
        if ($jawaban !== null && ! $tahapPertama) {
            return [
                'ok' => false,
                'pesan' => 'Alur seleksi program belum memiliki tahap. Hubungi administrator.',
                'kode' => 'ALUR_TANPA_TAHAP',
            ];
        }

        // Form apply publik selalu mengirim jawaban pendaftaran. Master Alur lama
        // kadang belum menempelkan Formulir_Kode pada tahap pertama; sebelumnya
        // kondisi itu membuat lamaran tercipta tetapi seluruh jawaban dibuang.
        // Isi kekosongan tersebut dari katalog formulir sesuai kategori. Jika
        // katalog juga belum tersedia, simpanPengisian() tetap menyimpan snapshot
        // pendaftaran tanpa master agar data kandidat tidak hilang.
        $formulirPendaftaran = $jawaban !== null
            ? $this->formulirPendaftaranUntukKategori((string) $program->Kategori)
            : null;
        $formulirPendaftaranKode = $tahapPertama->Formulir_Kode
            ?? $formulirPendaftaran->Kode
            ?? null;

        // Setiap jawaban pendaftaran wajib dinilai dan disimpan SERVER. Keberadaan
        // Formulir_Kode bukan lagi gerbang penyimpanan karena itu konfigurasi admin,
        // bukan alasan yang sah untuk kehilangan data kandidat.
        $pakaiSyaratServer = $jawaban !== null && $tahapPertama;
        if ($pakaiSyaratServer) {
            $gugurAlasan = null;
        }

        $lamaranId = DB::transaction(function () use ($pembukaan, $program, $posisi, $alur, $tahap, $userId, $userAdminId, $kode, $now, $nama, $gugurAlasan, $labelTahap1, $formulirPendaftaranKode) {
            $gugur = $gugurAlasan !== null && $gugurAlasan !== '';
            $id = DB::table('N_WEB_CAREERS_Lamaran')->insertGetId([
                'Kode' => $kode,
                'Id_Users' => $userId,
                'Kategori' => $program->Kategori,
                'Program_Id' => $program->Id_Program,
                'Program_Posisi_Id' => $posisi->Id_Program_Posisi,
                'Mpp_Ref' => $posisi->Mpp_Ref ?? null,
                // Jejak channel apply: dari pembukaan mana kandidat masuk.
                'Pembukaan_Id' => $pembukaan->Id_Pembukaan,
                'Program_Batch_Id' => $pembukaan->Program_Batch_Id ?? null,
                'Master_Alur_Id' => $alur->Id_Master_Alur ?? null,
                'Urutan_Tahap' => 1,
                'Total_Tahap' => $tahap->count(),
                // Tidak lolos saat finalisasi → langsung GUGUR; selain itu BERJALAN.
                'Status' => $gugur ? 'GUGUR' : 'BERJALAN',
                'Hasil_Akhir' => $gugur ? 'TIDAK_LOLOS' : null,
                'Gugur_Di_Tahap' => $gugur ? $labelTahap1 : null,
                'Waktu_Lamar' => $now,
                'Created_At' => $now, 'Created_By' => $nama, 'Created_By_Id' => $userAdminId,
                'Updated_At' => $now, 'Updated_By' => $nama, 'Updated_By_Id' => $userAdminId,
            ], 'Id_Lamaran');

            // Salin tiap tahap alur jadi baris perjalanan. Tahap pertama langsung
            // BERJALAN; sisanya MENUNGGU sampai tahap sebelumnya diputus.
            foreach ($tahap->values() as $i => $t) {
                $lamaranTahapId = DB::table('N_WEB_CAREERS_Lamaran_Tahap')->insertGetId([
                    'Lamaran_Id' => $id,
                    'Master_Alur_Tahap_Id' => $t->Id_Master_Alur_Tahap,
                    'Urutan' => $t->Urutan,
                    'Kode' => $t->Kode,
                    'Label' => $t->Label,
                    'Tipe_Tahap_Kode' => $t->Tipe_Tahap_Kode,
                    // Penanda titik tuntas DIBEKUKAN saat lamaran dibuat: alur
                    // boleh disunting kemudian, tapi perjalanan yang sudah
                    // berlangsung tidak boleh berubah maknanya di tengah jalan.
                    'Flag_Tuntas' => $t->Flag_Tuntas ?? 'T',
                    // Cut-off Talent Pool ikut dibekukan: alur boleh disunting
                    // kapan saja, tapi kandidat yang sudah berjalan tidak boleh
                    // berubah aturannya di tengah jalan.
                    'Flag_Talent_Pool' => $t->Flag_Talent_Pool ?? 'T',
                    // Aktivitas tahap ini dikerjakan bersamaan atau berurutan —
                    // ikut dibekukan, sepola dengan aturan lainnya. Nilainya
                    // disalin apa adanya dari master (Kode dari Master Mode
                    // Urutan); tidak ada mode bawaan yang ditulis di sini.
                    'Urutan_Aktivitas' => $t->Urutan_Aktivitas,
                    'Provider' => $t->Provider,
                    // DARI Mode_Keputusan_Kode, BUKAN Keputusan.
                    //
                    // Keduanya ada di master dan namanya mirip, tapi hanya
                    // Mode_Keputusan_Kode yang berisi kode yang dikenali
                    // Master_Mode_Keputusan (MANUAL_REVIEW / AUTO_SEMUA_LULUS /
                    // AUTO_TERAKHIR / HYBRID). Kolom `Keputusan` peninggalan
                    // lama hanya berisi 'MANUAL'/'SYSTEM' — kode yang tak
                    // pernah cocok, sehingga snapshot-nya diam-diam jatuh ke
                    // MANUAL_REVIEW dan tahap ber-mode otomatis berhenti
                    // meloloskan sendiri.
                    'Keputusan_Mode' => $t->Mode_Keputusan_Kode ?? null,
                    // Perilaku berkas hasil ikut dibekukan. Tanpa ini,
                    // menyalakan "wajib unggah" di master mengunci kandidat
                    // yang tahapnya sudah selesai dinilai — menuntut berkas
                    // yang saat mereka menjalaninya memang tidak diminta.
                    'Flag_Upload_Hasil' => $t->Flag_Upload_Hasil ?? 'T',
                    'Flag_Wajib_Upload' => $t->Flag_Wajib_Upload ?? 'T',
                    // Tahap pertama mewarisi formulir pendaftaran bawaan bila
                    // Master Alur lama belum menautkannya.
                    'Formulir_Kode' => $i === 0
                        ? ($t->Formulir_Kode ?? $formulirPendaftaranKode)
                        : $t->Formulir_Kode,
                    // ISI formulirnya ikut dibekukan, bukan cuma kodenya.
                    //
                    // Formulir_Kode hanya penunjuk; skema sesungguhnya dicari
                    // lewat Master_Formulir.Komponen_Kode saat halaman dibuka.
                    // Sambungan tengah itu yang bocor: begitu admin mengarahkan
                    // Komponen_Kode ke versi baru, seluruh kandidat yang belum
                    // mengisi — termasuk yang sudah berjalan berminggu-minggu —
                    // langsung mendapat formulir baru tanpa peringatan apa pun.
                    ...self::bekukanFormulir(
                        $i === 0 ? ($t->Formulir_Kode ?? $formulirPendaftaranKode) : $t->Formulir_Kode
                    ),
                    'Jenis_Tes_Kode' => $t->Jenis_Tes_Kode,
                    // Tahap pertama: GUGUR bila knock-out, kalau tidak BERJALAN.
                    'Status' => $i === 0 ? ($gugur ? 'GUGUR' : 'BERJALAN') : 'MENUNGGU',
                    'Rekomendasi' => $i === 0 && $gugur ? 'GUGUR' : null,
                    'Rekomendasi_Alasan' => $i === 0 && $gugur ? $gugurAlasan : null,
                    'Rekomendasi_At' => $i === 0 && $gugur ? $now : null,
                    // Aturan pengumuman ikut dibekukan dari cetakan (Batch 6).
                    'Mode_Pengumuman' => $t->Mode_Pengumuman ?? 'OTOMATIS',
                    'Flag_Notifikasi' => $t->Flag_Notifikasi ?? 'Y',
                    // Aturan batas pengisian formulir ikut dibekukan — lihat BatasIsi.
                    ...BatasIsi::snapshot($t),
                    'Created_At' => $now, 'Created_By' => $nama, 'Created_By_Id' => $userAdminId,
                    'Updated_At' => $now, 'Updated_By' => $nama, 'Updated_By_Id' => $userAdminId,
                ], 'Id_Lamaran_Tahap');

                // Bekukan sub-tes tahap (snapshot) — dipakai mesin keputusan.
                $this->snapshotSubTes($lamaranTahapId, $t->Id_Master_Alur_Tahap, $now, $nama, $userAdminId);
            }

            return $id;
        });

        // ── Snapshot jawaban pendaftaran ke tahap 1 ──
        //
        // Syarat AUTO-GUGUR selalu dinilai server saat jawaban masuk (mis. IPK
        // minimal): tidak memenuhi → GUGUR di sini juga.
        //
        // Yang TIDAK boleh otomatis adalah MELOLOSKAN. Dulu tahap 1 selalu
        // diloloskan begitu formulir terkirim, mengabaikan mode keputusan yang
        // diatur di Master Alur — akibatnya tahap yang jelas-jelas disetel
        // "Manual — admin memutuskan" tetap dilewati sendiri oleh sistem, dan
        // kandidat sudah berada di tahap berikutnya sebelum admin sempat melihat.
        // Sekarang yang menentukan adalah Keputusan_Mode tahap itu sendiri.
        if ($pakaiSyaratServer) {
            $stage1 = DB::table('N_WEB_CAREERS_Lamaran_Tahap')
                ->where('Lamaran_Id', $lamaranId)
                ->where('Urutan', 1)
                ->first();

            if ($stage1 && $stage1->Status === 'BERJALAN') {
                $hasilIsi = $this->simpanPengisian($stage1->Id_Lamaran_Tahap, $userId, $jawaban);

                $stage1Kini = DB::table('N_WEB_CAREERS_Lamaran_Tahap')
                    ->where('Id_Lamaran_Tahap', $stage1->Id_Lamaran_Tahap)->first();

                // Sudah gugur otomatis oleh syarat wajib → selesai di sini.
                if (! $stage1Kini || $stage1Kini->Status !== 'BERJALAN') {
                    return ['ok' => true, 'pesan' => $hasilIsi['pesan'] ?? 'Lamaran tercatat (tidak lolos).', 'lamaranId' => $lamaranId];
                }

                // Hanya tahap ber-mode SYSTEM yang boleh maju tanpa admin.
                if (($stage1Kini->Keputusan_Mode ?? 'MANUAL') === 'SYSTEM') {
                    $this->tetapkanTahap($stage1->Id_Lamaran_Tahap, 'LULUS', 'Lolos seleksi administrasi otomatis — seluruh syarat terpenuhi.', null, now());

                    return ['ok' => true, 'pesan' => 'Lamaran terkirim. Anda lolos seleksi administrasi dan lanjut ke tahap berikutnya.', 'lamaranId' => $lamaranId];
                }

                // MANUAL → tetap di tahap 1, menunggu keputusan admin.
                return [
                    'ok' => true,
                    'pesan' => 'Lamaran terkirim. Berkas Anda sedang diperiksa tim rekrutmen — hasilnya akan diumumkan.',
                    'lamaranId' => $lamaranId,
                ];
            }
        }

        return ['ok' => true, 'pesan' => $gugurAlasan ? 'Lamaran tercatat (tidak lolos).' : 'Lamaran dibuat.', 'lamaranId' => $lamaranId];
    }

    /**
     * Kandidat mengirim formulir untuk sebuah tahap.
     *
     * @param  array  $jawaban  isi Jawaban_Json (key => nilai)
     * @return array{ok:bool, pesan:string, rekomendasi?:string}
     */
    public function simpanPengisian(int $lamaranTahapId, int $userId, array $jawaban, ?string $ip = null): array
    {
        $tahap = DB::table('N_WEB_CAREERS_Lamaran_Tahap')->where('Id_Lamaran_Tahap', $lamaranTahapId)->first();
        if (! $tahap) {
            return ['ok' => false, 'pesan' => 'Tahap tidak ditemukan.'];
        }
        $lamaran = DB::table('N_WEB_CAREERS_Lamaran')->where('Id_Lamaran', $tahap->Lamaran_Id)->first();
        if (! $lamaran || (int) $lamaran->Id_Users !== $userId) {
            return ['ok' => false, 'pesan' => 'Lamaran bukan milik Anda.'];
        }
        // Tahap pertama adalah formulir pendaftaran publik. Data ini tetap wajib
        // disimpan walaupun Master Alur lama belum memiliki Formulir_Kode.
        if (! $tahap->Formulir_Kode && (int) $tahap->Urutan !== 1) {
            return ['ok' => false, 'pesan' => 'Tahap ini tidak menuntut pengisian formulir.'];
        }
        if ($tahap->Status !== 'BERJALAN') {
            return ['ok' => false, 'pesan' => 'Tahap ini tidak sedang berjalan.'];
        }

        $formulir = $tahap->Formulir_Kode
            ? DB::table('N_WEB_CAREERS_Master_Formulir')->where('Kode', $tahap->Formulir_Kode)->first()
            : $this->formulirPendaftaranUntukKategori((string) ($lamaran->Kategori ?? ''));
        // Versi yang DIBEKUKAN saat tahap dibuat, bukan versi PUBLISHED terkini —
        // kalau tidak, Formulir_Versi yang tersimpan di baris Pengisian bisa beda
        // dengan Schema_Snapshot_Json-nya sendiri jika admin sempat menerbitkan
        // versi baru di antara tahap dibuat dan kandidat mengirim jawaban.
        $schemaPayload = $tahap->Formulir_Kode
            ? FormulirSchema::byKodeDanVersi($tahap->Formulir_Kode, $tahap->Formulir_Versi ?? null)
            : FormulirSchema::pendaftaranUntukKategori((string) ($lamaran->Kategori ?? ''));
        $now = now();
        $nama = session('career_auth.nama', 'KANDIDAT');

        // Field turunan (usia dari tanggal lahir, jenjang gabungan) — dihitung
        // sistem, lalu digabung ke jawaban untuk dinilai & diproyeksikan.
        $turunan = FieldTurunan::hitung($jawaban);
        $nilai = array_merge($jawaban, $turunan);

        // Syarat milik tahap ini. Yang mode uji tetap dihitung tapi tidak
        // memengaruhi rekomendasi.
        $syaratRows = DB::table('N_WEB_CAREERS_Program_Syarat')
            ->where('Program_Id', $lamaran->Program_Id)
            ->where('Master_Alur_Tahap_Id', $tahap->Master_Alur_Tahap_Id)
            ->where('Flag_Aktif', 'Y')
            ->orderBy('Urutan')
            ->get();

        $evaluasi = $this->evaluasiSyarat($syaratRows, $nilai);

        $pengisianId = DB::transaction(function () use ($tahap, $lamaran, $formulir, $schemaPayload, $jawaban, $nilai, $turunan, $evaluasi, $userId, $ip, $now, $nama, $syaratRows) {
            $kode = 'FLL-'.strtoupper(Str::random(8));

            $pengisianId = DB::table('N_WEB_CAREERS_Formulir_Pengisian')->insertGetId([
                'Kode' => $kode,
                'Id_Users' => $userId,
                'Lamaran_Id' => $lamaran->Id_Lamaran,
                'Lamaran_Tahap_Id' => $tahap->Id_Lamaran_Tahap,
                'Master_Formulir_Id' => $formulir?->Id_Master_Formulir,
                // KOMPONEN YANG DIBEKUKAN, bukan yang sedang menempel di master.
                //
                // Inilah yang menentukan label pertanyaan saat pengisian ini
                // dibuka lagi berbulan-bulan kemudian. Kalau diambil dari master
                // yang hidup, jawaban lama akan dibacakan memakai daftar
                // pertanyaan versi baru: field yang berganti nama jadi kosong,
                // dan admin menyimpulkan kandidatnya tidak mengisi — padahal
                // mengisi lengkap.
                'Komponen_Kode' => $tahap->Formulir_Komponen ?: $formulir?->Komponen_Kode,
                'Formulir_Versi' => $tahap->Formulir_Versi ?? null,
                'Sumber' => $tahap->Urutan == 1 ? 'PENDAFTARAN' : 'TAHAP',
                'Master_Alur_Tahap_Id' => $tahap->Master_Alur_Tahap_Id,
                'Program_Id' => $lamaran->Program_Id,
                'Program_Batch_Id' => $lamaran->Program_Batch_Id,
                'Jawaban_Json' => json_encode($jawaban, JSON_UNESCAPED_UNICODE),
                'Langkah_Terakhir' => 0,
                'Status' => 'TERKIRIM',
                'Waktu_Kirim' => $now,
                'Ip_Pengirim' => $ip,
                'Created_At' => $now, 'Created_By' => $nama, 'Created_By_Id' => $userId,
                'Updated_At' => $now, 'Updated_By' => $nama, 'Updated_By_Id' => $userId,
            ] + FormulirSchema::kolomSnapshotInsert($schemaPayload), 'Id_Formulir_Pengisian');

            // Proyeksi field yang dipakai syarat + field turunan ke index —
            // supaya bisa dipakai penyaringan massal & audit belakangan.
            $this->proyeksikan($pengisianId, $lamaran, $syaratRows, $nilai, $turunan, $userId);

            // Tempelkan pengisian & rekomendasi mesin ke tahap.
            DB::table('N_WEB_CAREERS_Lamaran_Tahap')
                ->where('Id_Lamaran_Tahap', $tahap->Id_Lamaran_Tahap)
                ->update([
                    'Formulir_Pengisian_Id' => $pengisianId,
                    'Rekomendasi' => $evaluasi['rekomendasi'],
                    'Rekomendasi_Alasan' => $evaluasi['alasan'],
                    'Rekomendasi_At' => $now,
                    'Jejak_Json' => json_encode($evaluasi['jejak'], JSON_UNESCAPED_UNICODE),
                    'Waktu_Mulai' => $tahap->Waktu_Mulai ?? $now,
                    'Updated_At' => $now, 'Updated_By' => $nama, 'Updated_By_Id' => $userId,
                ]);

            // Syarat ber-Aksi GUGUR & bukan mode uji: langsung gugurkan tanpa
            // menunggu admin. Sisanya menunggu ketuk palu.
            // Auto-gugur: yang tersimpan sebagai alasan (dilihat kandidat) adalah
            // pesan HANGAT, bukan detail teknis. Detailnya sudah ada di Jejak_Json
            // untuk ditelaah admin.
            if ($evaluasi['gugurOtomatis']) {
                $this->tetapkanTahap($tahap->Id_Lamaran_Tahap, 'GUGUR', $evaluasi['pesanKandidat'], null, $now);
            }

            return $pengisianId;
        });

        return [
            'ok' => true,
            'pesan' => $evaluasi['gugurOtomatis'] ? ($evaluasi['pesanKandidat'] ?: self::PESAN_GUGUR_BAWAAN) : 'Formulir berhasil dikirim.',
            'rekomendasi' => $evaluasi['rekomendasi'],
            'pengisianId' => $pengisianId,
        ];
    }

    /**
     * Formulir gerbang bawaan per jalur rekrutmen.
     *
     * Pencarian memakai Komponen_Kode karena Kode master dibuat admin dan dapat
     * berbeda antar instalasi. Baris aktif yang paling awal menjadi acuan.
     */
    private function formulirPendaftaranUntukKategori(string $kategori): ?object
    {
        $komponen = match (strtoupper($kategori)) {
            'MT' => 'FORMULIR_1',
            'INTERNSHIP', 'MAGANG' => 'FORMULIR_4',
            default => 'FORMULIR_3',
        };

        return DB::table('N_WEB_CAREERS_Master_Formulir')
            ->where('Komponen_Kode', $komponen)
            ->where('Flag_Aktif', 'Y')
            ->orderBy('Id_Master_Formulir')
            ->first();
    }

    /**
     * Admin memutuskan sebuah tahap (ketuk palu). Lamaran maju ke tahap
     * berikutnya bila LULUS, atau ditutup GUGUR.
     *
     * @return array{ok:bool, pesan:string}
     */
    private static ?\Illuminate\Support\Collection $hasilCache = null;

    /**
     * Master hasil keputusan tahap, di-cache per permintaan.
     *
     * Flagnya yang menentukan konsekuensi: maju/tidak, kirim email/tidak,
     * potong kuota/tidak, masuk Talent Pool/tidak. Menambah hasil baru cukup
     * satu baris di master — tanpa menyentuh kode ini.
     */
    public static function masterHasilKeputusan(): \Illuminate\Support\Collection
    {
        return self::$hasilCache ??= DB::table('N_WEB_CAREERS_Master_Hasil_Keputusan')
            ->where('Flag_Aktif', 'Y')
            ->orderBy('Urutan')
            ->get()
            ->keyBy('Kode');
    }

    /**
     * @param  bool|null  $talentPool  Pilihan admin: kandidat disimpan di Talent
     *                                 Pool atau tidak. Hanya berarti untuk hasil
     *                                 ber-`Flag_Pilih_Talent_Pool='Y'` (mundur /
     *                                 menolak penawaran). NULL = ikut master,
     *                                 yang juga berlaku bagi seluruh pemanggil
     *                                 lama dan bagi mesin auto-gugur.
     */
    public function ketukPalu(int $lamaranTahapId, string $hasil, ?string $catatan, ?int $adminId, ?bool $talentPool = null): array
    {
        $hasil = strtoupper($hasil);

        // Hasil keputusan dibaca dari MASTER, bukan daftar mati di sini.
        //
        // Dulu hanya tiga: LULUS / GUGUR / TALENT_POOL. Akibatnya kandidat yang
        // MENOLAK penawaran terpaksa dicatat GUGUR — di data terbaca perusahaan
        // yang menolak dia, dan funnel melaporkan "gagal di penawaran" untuk
        // sesuatu yang sebenarnya berarti penawaran kita kalah bersaing. Dua
        // kesimpulan itu menuntut tindakan yang sama sekali berbeda.
        $master = self::masterHasilKeputusan();
        $def = $master->get($hasil);

        if (! $def) {
            $sah = $master->keys()->implode(', ');

            return ['ok' => false, 'pesan' => "Hasil '{$hasil}' tidak dikenali. Yang sah: {$sah}."];
        }

        if (($def->Butuh_Alasan ?? 'T') === 'Y' && trim((string) $catatan) === '') {
            return ['ok' => false, 'pesan' => "Alasan wajib diisi untuk keputusan \"{$def->Nama}\"."];
        }

        // ── SATU TRANSAKSI, SATU PALU ────────────────────────────────────────
        //
        // Seluruh blok di bawah dulu berjalan TANPA transaksi. Padahal
        // tetapkanTahap() menulis ke empat tabel berbeda — tahap, lamaran,
        // Talent Pool, dan tahap berikutnya. Kegagalan di tengahnya (koneksi
        // putus, deadlock, penyimpanan Talent Pool menolak) meninggalkan
        // keadaan yang tak bisa dibaca siapa pun: tahapnya SELESAI/LULUS
        // sementara lamarannya masih BERJALAN, atau kandidat diputus tanpa
        // tahap berikutnya pernah dibuka — menggantung selamanya menunggu
        // peristiwa yang tidak akan datang lagi.
        //
        // BARIS TAHAPNYA DIKUNCI, lalu statusnya diperiksa ULANG SESUDAH kunci
        // didapat. Pemeriksaan sebelum kunci tidak menjamin apa pun: dua klik
        // "Loloskan" yang berselisih sepersekian detik sama-sama membaca
        // 'BERJALAN', dan keduanya mengetuk palu atas tahap yang sama.
        // evaluasiTahap() sudah memakai pengaman ini sejak awal; jalur ADMIN —
        // yang justru paling sering ditekan dua kali — tidak pernah
        // mendapatkannya.
        return DB::transaction(function () use ($lamaranTahapId, $hasil, $def, $catatan, $adminId, $talentPool) {
            $tahap = DB::table('N_WEB_CAREERS_Lamaran_Tahap')
                ->where('Id_Lamaran_Tahap', $lamaranTahapId)
                ->lockForUpdate()
                ->first();
            if (! $tahap) {
                return ['ok' => false, 'pesan' => 'Tahap tidak ditemukan.'];
            }
            if ($tahap->Status === 'SELESAI') {
                return ['ok' => false, 'pesan' => 'Tahap ini sudah diputus.'];
            }

            // ── GERBANG HOLD ─────────────────────────────────────────────────
            // Kandidat yang sedang DITAHAN tidak bisa diputus tanpa melepas
            // tahannya lebih dulu. Kalau boleh, hold jadi sekadar hiasan:
            // seseorang yang tidak melihat penandanya tetap bisa mengetuk palu
            // atas kandidat yang justru sedang ditunggu — dan keputusannya
            // tidak bisa ditarik kembali.
            //
            // Melepas tahan itu satu klik, dan klik itulah yang memaksa admin
            // sadar bahwa ada alasan kenapa kandidat ini sengaja belum diputus.
            if (($tahap->Hold_Flag ?? 'T') === 'Y') {
                return ['ok' => false, 'pesan' => 'Kandidat ini sedang DITAHAN ('.($tahap->Hold_Alasan_Kode ?: 'tanpa alasan').'). Lepaskan penahanannya dulu sebelum memutuskan.'];
            }

            // ── GERBANG MODE KEPUTUSAN (dari Master Alur) ────────────────────
            // Tahap ber-mode OTOMATIS diputus mesin begitu aktivitasnya selesai.
            // Kalau admin masih bisa mengetuk palu di sini, mode otomatis yang
            // disetel di Master Alur jadi tak berarti dan hasilnya bisa berbeda
            // dari yang dihitung mesin. Diblokir di server, bukan hanya di layar.
            if (strtoupper((string) ($tahap->Keputusan_Mode ?? 'MANUAL')) === 'SYSTEM') {
                return ['ok' => false, 'pesan' => 'Tahap ini disetel OTOMATIS di Master Alur — keputusannya ditentukan sistem setelah seluruh aktivitas selesai, bukan oleh admin.'];
            }

            // ── GERBANG HASIL AKTIVITAS ──────────────────────────────────────
            // Aktivitas penentu yang berupa TES (punya jenis tes / dari pihak
            // ke-3) wajib punya hasil sebelum tahapnya diputus. Meloloskan tes
            // yang nilainya belum tercatat berarti memutus tanpa dasar.
            $belum = DB::table('N_WEB_CAREERS_Lamaran_Tahap_Tes')
                ->where('Lamaran_Tahap_Id', $lamaranTahapId)
                ->where('Peran', 'PENENTU')
                ->where('Flag_Selesai', '<>', 'Y')
                ->where(fn ($q) => $q->whereNotNull('Jenis_Tes_Kode')->orWhere('Provider', 'THIRD_PARTY'))
                ->get(['Label']);
            if ($belum->isNotEmpty() && ($tahap->Siap_Diputus ?? 'N') !== 'Y') {
                $nama = $belum->pluck('Label')->filter()->implode(', ');

                return ['ok' => false, 'pesan' => 'Hasil aktivitas berikut belum dicatat: '.($nama ?: $belum->count().' aktivitas').'. Catat hasilnya dulu sebelum memutuskan.'];
            }

            // Dulu di sini ada GATE WAJIB UPLOAD: tahap tak bisa diloloskan
            // sebelum berkas hasil diunggah. Gate itu dicabut karena titik
            // unggahnya — "Berkas Pendukung" di modal keputusan — sudah
            // dihapus: syarat yang tak punya cara dipenuhi bukan pengaman,
            // melainkan jalan buntu.
            //
            // Kewajiban berkas kini melekat pada SUB-AKTIVITAS (Unggah_Wajib di
            // Lamaran_Tahap_Tes), tempat berkasnya benar-benar diunggah, dan
            // ditegakkan lewat gate "hasil aktivitas belum dicatat" di atas.

            // GERBANG KUOTA: LULUS di tahap TERAKHIR = kandidat DITERIMA →
            // menempati kursi. Bila kuota MPP posisi sudah penuh, tolak —
            // arahkan ke Tidak Lolos atau Masuk Talent Pool. Tahap antara
            // (masih ada tahap berikut) tak dibatasi.
            if ($hasil === 'LULUS') {
                $adaBerikut = DB::table('N_WEB_CAREERS_Lamaran_Tahap')
                    ->where('Lamaran_Id', $tahap->Lamaran_Id)
                    ->where('Urutan', '>', $tahap->Urutan)
                    ->exists();
                if (! $adaBerikut) {
                    $lam = DB::table('N_WEB_CAREERS_Lamaran')->where('Id_Lamaran', $tahap->Lamaran_Id)->first();
                    if ($lam && $lam->Program_Posisi_Id) {
                        // ── GERBANG 1: JATAH LOKER INI ──────────────────────
                        //
                        // BARIS POSISINYA DIKUNCI, bukan sekadar dibaca.
                        //
                        // Hitung-lalu-putuskan tanpa kunci adalah lomba yang
                        // pasti kalah pada kuota terakhir: dua rekruter yang
                        // meloloskan kandidat berbeda di detik yang sama
                        // sama-sama membaca "9 dari 10 terisi", dan keduanya
                        // lolos gerbang ini. Kursinya jadi sebelas. Kunci di
                        // sini membuat yang kedua menunggu sampai yang pertama
                        // selesai, lalu membaca angka yang sudah benar.
                        $posisi = DB::table('N_WEB_CAREERS_Program_Posisi')
                            ->where('Id_Program_Posisi', $lam->Program_Posisi_Id)
                            ->lockForUpdate()
                            ->first(['Kuota', 'Mpp_Ref']);

                        $potong = HasilKeputusan::kodePotongKuota() ?: ['LULUS'];
                        $kuota = (int) ($posisi->Kuota ?? 0);

                        if ($kuota > 0) {
                            $terisi = DB::table('N_WEB_CAREERS_Lamaran')
                                ->where('Program_Posisi_Id', $lam->Program_Posisi_Id)
                                ->whereIn('Status', $potong)->count();
                            if ($terisi >= $kuota) {
                                return ['ok' => false, 'pesan' => "Kuota posisi sudah penuh ({$terisi}/{$kuota}). Pilih \"Tidak Lolos\" atau \"Masuk Talent Pool\"."];
                            }
                        }

                        // ── GERBANG 2: RENCANA MPP, LINTAS PROGRAM ──────────
                        //
                        // Gerbang di atas hanya menjaga JATAH SATU LOKER. Satu
                        // MPP boleh dibuka di beberapa program — dan memang
                        // harus, karena satu program cuma sanggup memegang satu
                        // alur. Tanpa gerbang kedua, MPP berencana 15 yang
                        // dibuka di dua program membolehkan 15 penerimaan di
                        // masing-masing: tiga puluh orang diterima atas
                        // persetujuan yang menyebut lima belas, tanpa satu
                        // peringatan pun.
                        //
                        // Kuncinya SATU BARIS ledger MPP, diambil SESUDAH baris
                        // loker. Urutan itu tetap sama di seluruh jalur, jadi
                        // dua rekruter di dua program berbeda antre — bukan
                        // saling tunggu. Mengunci seluruh baris loker MPP ini
                        // sebagai gantinya justru membuka pintu deadlock.
                        $mpp = KursiMpp::kunciDanHitung($posisi->Mpp_Ref ?? null);
                        if ($mpp && $mpp['penuh']) {
                            return ['ok' => false, 'pesan' => sprintf(
                                'Kuota MPP %s sudah penuh (%d dari %d disetujui) — termasuk penerimaan di program lain '
                                .'yang memakai MPP yang sama. Pilih "Tidak Lolos" atau "Masuk Talent Pool", '
                                .'atau minta rencana MPP-nya ditambah lebih dulu.',
                                $posisi->Mpp_Ref, $mpp['terisi'], $mpp['kuota']
                            )];
                        }
                    }
                }
            }

            $this->tetapkanTahap($lamaranTahapId, $hasil, $catatan, $adminId, now(), $talentPool);

            // Pesannya DARI MASTER untuk hasil di luar tiga yang lama. Peta
            // literal di bawah tidak pernah memuat MENGUNDURKAN_DIRI /
            // DITOLAK_KANDIDAT, sehingga keduanya mengembalikan pesan kosong —
            // layar menampilkan notifikasi hampa untuk keputusan yang justru
            // menutup lamaran orang.
            $pesan = [
                'LULUS' => 'Kandidat diloloskan ke tahap berikutnya.',
                'GUGUR' => 'Kandidat digugurkan.',
                'TALENT_POOL' => 'Kandidat dialihkan ke Talent Pool.',
            ][$hasil] ?? ('Keputusan dicatat: '.($def->Nama ?? $hasil).'.');

            // Nasib Talent Pool ikut disebut — itu satu-satunya bagian keputusan
            // yang tidak terbaca dari nama hasilnya.
            if ($talentPool !== null && ($def->Flag_Pilih_Talent_Pool ?? 'T') === 'Y') {
                $pesan .= $talentPool
                    ? ' Kandidat disimpan di Talent Pool.'
                    : ' Kandidat tidak disimpan di Talent Pool.';
            }

            return ['ok' => true, 'pesan' => $pesan];
        });
    }

    // ═══════════════════════ INTERNAL ═══════════════════════

    /**
     * Nilai seluruh syarat sebuah tahap.
     *
     * @return array{rekomendasi:string, alasan:string, jejak:array, gugurOtomatis:bool}
     */
    /** Pesan penolakan bawaan bila admin tidak menuliskannya sendiri. */
    private const PESAN_GUGUR_BAWAAN = 'Terima kasih sudah mendaftar di EVO Group. Setelah kami tinjau, untuk kesempatan kali ini Anda belum memenuhi kualifikasi yang dibutuhkan. Kami sangat menghargai minat & waktu Anda, dan berharap dapat bertemu kembali di kesempatan berikutnya.';

    private function evaluasiSyarat($syaratRows, array $nilai): array
    {
        $jejak = [];
        $teknis = [];          // ringkasan TEKNIS — untuk admin (Rekomendasi_Alasan)
        $pesanKandidat = null; // pesan HANGAT — untuk kandidat (Alasan_Gugur)
        $gugurOtomatis = false;

        foreach ($syaratRows as $s) {
            $aturan = json_decode($s->Aturan_Json ?: '{}', true) ?: [];
            $hasil = MesinSyarat::nilai($aturan, $nilai);
            $uji = $s->Flag_Uji === 'Y';

            $jejak[] = [
                'syarat' => $s->Nama,
                'lolos' => $hasil['lolos'],
                'uji' => $uji,
                'aksi' => $s->Aksi,
                'rincian' => $hasil['jejak'],
            ];

            // Mode uji tidak memengaruhi kandidat sama sekali.
            if ($uji || $hasil['lolos']) {
                continue;
            }

            // Admin melihat alasan teknis (usia = 30, diminta <= 25). Kandidat
            // TIDAK — mereka hanya melihat pesan hangat. Dipisah agar nada ke
            // kandidat tetap sopan dan tidak membocorkan ambang batas.
            $teknis[] = MesinSyarat::ringkas($hasil['jejak']);
            if ($pesanKandidat === null && ! empty($s->Pesan_Gugur)) {
                $pesanKandidat = $s->Pesan_Gugur;
            }
            if ($s->Aksi === 'GUGUR') {
                $gugurOtomatis = true;
            }
        }

        $rekomendasi = $teknis ? 'GUGUR' : 'LOLOS';

        return [
            'rekomendasi' => $rekomendasi,
            'alasan' => $teknis ? implode(' | ', $teknis) : 'Seluruh syarat terpenuhi.',
            'pesanKandidat' => $teknis ? ($pesanKandidat ?: self::PESAN_GUGUR_BAWAAN) : null,
            'jejak' => $jejak,
            'gugurOtomatis' => $gugurOtomatis,
        ];
    }

    /** Tulis nilai field (yang dipakai syarat + turunan) ke index penyaringan. */
    private function proyeksikan(int $pengisianId, $lamaran, $syaratRows, array $nilai, array $turunan, int $userId): void
    {
        // Kumpulkan field yang perlu diindeks: yang dirujuk syarat + semua turunan.
        $dipakai = [];
        foreach ($syaratRows as $s) {
            $aturan = json_decode($s->Aturan_Json ?: '{}', true) ?: [];
            $dipakai = array_merge($dipakai, MesinSyarat::fieldDipakai($aturan));
        }
        $keys = array_values(array_unique(array_merge($dipakai, array_keys($turunan))));

        $now = now();
        foreach ($keys as $key) {
            if (! array_key_exists($key, $nilai)) {
                continue;
            }
            $v = $nilai[$key];
            if (is_array($v)) {
                $v = implode(', ', $v);
            }

            DB::table('N_WEB_CAREERS_Formulir_Jawaban_Index')->insert([
                'Formulir_Pengisian_Id' => $pengisianId,
                'Lamaran_Id' => $lamaran->Id_Lamaran,
                'Id_Users' => $userId,
                'Program_Id' => $lamaran->Program_Id,
                'Field_Key' => $key,
                'Tipe_Nilai' => is_numeric($v) ? 'ANGKA' : 'TEKS',
                'Nilai_Teks' => (string) $v,
                'Nilai_Angka' => is_numeric($v) ? (float) $v : null,
                'Flag_Turunan' => array_key_exists($key, $turunan) ? 'Y' : 'T',
                'Created_At' => $now,
                'Created_By_Id' => $userId,
            ]);
        }
    }

    /**
     * Tetapkan hasil sebuah tahap dan gerakkan lamaran. Dipakai baik oleh
     * auto-gugur mesin maupun ketuk palu admin.
     */
    /**
     * WAJIB DIPANGGIL DI DALAM TRANSAKSI.
     *
     * Metode ini menulis ke empat tabel — tahap, lamaran, Talent Pool, dan
     * tahap berikutnya — dan pada jalur "nilai lama masih berlaku" ia memanggil
     * dirinya sendiri untuk tahap sesudahnya. Tidak ada satu pun titik di
     * tengahnya yang aman untuk berhenti: berhenti di sana berarti tahapnya
     * sudah diputus sementara lamarannya belum, atau sebaliknya.
     *
     * Kedua pemanggilnya sudah memenuhi syarat ini — ketukPalu() dan
     * evaluasiTahap() sama-sama membuka transaksi lebih dulu. Catatan ini ada
     * supaya pemanggil KETIGA tidak pernah lahir tanpa transaksinya.
     */
    private function tetapkanTahap(int $lamaranTahapId, string $hasil, ?string $catatan, ?int $adminId, $now, ?bool $talentPool = null): void
    {
        $tahap = DB::table('N_WEB_CAREERS_Lamaran_Tahap')->where('Id_Lamaran_Tahap', $lamaranTahapId)->first();
        $nama = session('career_auth.nama', 'SISTEM');
        $def = self::masterHasilKeputusan()->get($hasil);

        // APAKAH KANDIDAT DISIMPAN.
        //
        // Master menyimpan dua hal berbeda yang mudah tertukar: Flag_Talent_Pool
        // (jawabannya) dan Flag_Pilih_Talent_Pool (siapa yang menjawab). Bila
        // yang kedua 'Y', jawaban admin-lah yang berlaku dan flag pertama turun
        // pangkat jadi sekadar pilihan awal yang disodorkan di layar.
        $simpanTalent = ($def->Flag_Pilih_Talent_Pool ?? 'T') === 'Y' && $talentPool !== null
            ? $talentPool
            : ($def->Flag_Talent_Pool ?? 'T') === 'Y';

        DB::table('N_WEB_CAREERS_Lamaran_Tahap')->where('Id_Lamaran_Tahap', $lamaranTahapId)->update([
            'Status' => 'SELESAI',
            'Hasil' => $hasil,
            'Catatan' => $catatan,
            // Jejak pilihannya, bukan hanya akibatnya. Tanpa kolom ini, "tidak
            // dipilih" dan "gagal tersimpan" sama-sama terbaca sebagai tidak
            // adanya baris di Talent_Pool.
            'Masuk_Talent_Pool' => $simpanTalent ? 'Y' : 'T',
            'Waktu_Selesai' => $now,
            'Diputus_By' => $nama,
            'Diputus_By_Id' => $adminId,
            'Diputus_At' => $now,
            'Updated_At' => $now, 'Updated_By' => $nama, 'Updated_By_Id' => $adminId,
        ]);

        // Tahap diputus: undangan konfirmasi & permintaan jadwal lain yang masih
        // terbuka di tahap ini selesai — tidak ada lagi surel untuknya.
        KonfirmasiJadwal::tutupTahap($lamaranTahapId, 'DIPUTUS');

        // KEPUTUSAN DARI KANDIDAT (menolak penawaran / mengundurkan diri).
        //
        // Dipisah dari GUGUR bukan demi kerapian: keduanya menutup lamaran, tapi
        // sebabnya berlawanan. Menyamakannya membuat laporan berbunyi "kandidat
        // gagal di tahap penawaran" untuk orang yang justru lolos dan memilih
        // pergi — dan itu menuntun ke perbaikan yang salah sasaran.
        //
        // Talent Pool-nya BUKAN keharusan. Kandidat yang mundur karena dapat
        // tempat lebih dekat rumah memang layak disimpan; yang mundur setelah
        // tak pernah membalas undangan tidak. Dulu keduanya ikut tersimpan,
        // dan admin yang tidak ingin menyimpan terpaksa mencatatnya GUGUR —
        // memperbaiki isi Talent Pool dengan merusak arti datanya.
        if (($def->Flag_Oleh_Kandidat ?? 'T') === 'Y') {
            DB::table('N_WEB_CAREERS_Lamaran')->where('Id_Lamaran', $tahap->Lamaran_Id)->update([
                'Status' => $hasil,
                'Hasil_Akhir' => $hasil,
                'Gugur_Di_Tahap' => $tahap->Label,
                'Alasan_Gugur' => $catatan,
                'Waktu_Selesai' => $now,
                'Updated_At' => $now, 'Updated_By' => $nama, 'Updated_By_Id' => $adminId,
            ]);

            if ($simpanTalent) {
                $this->simpanKeTalentPool($tahap, $catatan, $adminId, $nama, $now);
            }

            Log::channel('web_career')->info(
                "Lamaran #{$tahap->Lamaran_Id} ditutup oleh KANDIDAT ({$def->Nama}) di tahap '{$tahap->Label}'"
                .($simpanTalent ? ' — disimpan di Talent Pool.' : ' — TIDAK disimpan di Talent Pool.')
            );

            return;
        }

        if ($hasil === 'GUGUR') {
            DB::table('N_WEB_CAREERS_Lamaran')->where('Id_Lamaran', $tahap->Lamaran_Id)->update([
                'Status' => 'GUGUR',
                'Hasil_Akhir' => 'DITOLAK',
                'Gugur_Di_Tahap' => $tahap->Label,
                'Alasan_Gugur' => $catatan,
                'Waktu_Selesai' => $now,
                'Updated_At' => $now, 'Updated_By' => $nama, 'Updated_By_Id' => $adminId,
            ]);

            return;
        }

        // TALENT POOL: kandidat tidak lolos di lowongan ini, tapi cukup baik untuk
        // disimpan. Lamaran ditutup dengan status khusus (bukan GUGUR biasa) dan
        // sebuah kartu Talent Pool dibuat (idempoten — tak menduplikasi lamaran sama).
        if ($hasil === 'TALENT_POOL') {
            DB::table('N_WEB_CAREERS_Lamaran')->where('Id_Lamaran', $tahap->Lamaran_Id)->update([
                'Status' => 'TALENT_POOL',
                'Hasil_Akhir' => 'TALENT_POOL',
                'Gugur_Di_Tahap' => $tahap->Label,
                'Alasan_Gugur' => $catatan,
                'Waktu_Selesai' => $now,
                'Updated_At' => $now, 'Updated_By' => $nama, 'Updated_By_Id' => $adminId,
            ]);

            $this->simpanKeTalentPool($tahap, $catatan, $adminId, $nama, $now);

            return;
        }

        // LULUS: buka tahap berikutnya, atau tutup lamaran bila ini tahap terakhir.
        $berikut = DB::table('N_WEB_CAREERS_Lamaran_Tahap')
            ->where('Lamaran_Id', $tahap->Lamaran_Id)
            ->where('Urutan', '>', $tahap->Urutan)
            ->orderBy('Urutan')
            ->first();

        // TITIK TUNTAS.
        //
        // Alur kerap memuat tahap administratif SESUDAH kandidat sebenarnya
        // sudah diterima — Tanda Tangan Kontrak, Onboarding. Tanpa penanda ini
        // kandidat yang sudah memegang surat penawaran tetap berstatus
        // "Berjalan" dan kuota belum terpotong, padahal kursinya sudah terisi.
        //
        // Tahap sesudahnya TETAP dibuka dan tetap dikerjakan — yang berubah
        // hanya: ia tidak lagi menentukan diterima atau tidaknya kandidat.
        $tuntasDiSini = ($tahap->Flag_Tuntas ?? 'T') === 'Y';

        if ($tuntasDiSini) {
            DB::table('N_WEB_CAREERS_Lamaran')->where('Id_Lamaran', $tahap->Lamaran_Id)->update([
                'Status' => 'LULUS',
                'Hasil_Akhir' => 'DITERIMA',
                'Waktu_Selesai' => $now,
                'Updated_At' => $now, 'Updated_By' => $nama, 'Updated_By_Id' => $adminId,
            ]);

            Log::channel('web_career')->info(
                "Lamaran #{$tahap->Lamaran_Id} DITERIMA di tahap tuntas '{$tahap->Label}' (urutan {$tahap->Urutan})."
            );

            // KURSINYA DIBUKUKAN SERENTAK. Bukan diantrekan: posisi yang kursinya
            // sudah terisi tapi masih tertulis "BUKA" akan menerima pendaftaran
            // untuk kursi yang tak ada, dan jendela itu tidak boleh pernah
            // terbuka walau sedetik. Penutupan MPP-nya yang diantrekan — lihat
            // KursiPosisi::untukLamaran().
            KursiPosisi::untukLamaran((int) $tahap->Lamaran_Id);
        }

        if ($berikut) {
            DB::table('N_WEB_CAREERS_Lamaran_Tahap')->where('Id_Lamaran_Tahap', $berikut->Id_Lamaran_Tahap)->update([
                'Status' => 'BERJALAN',
                'Updated_At' => $now,
            ]);

            // Tahap berformulir berbatas: jamnya mulai berjalan SEKARANG —
            // tanggal program kolomnya, atau aturan otomatis tahapnya.
            BatasIsi::buka((int) $berikut->Id_Lamaran_Tahap, $now, $nama, $adminId);
            DB::table('N_WEB_CAREERS_Lamaran')->where('Id_Lamaran', $tahap->Lamaran_Id)->update([
                'Urutan_Tahap' => $berikut->Urutan,
                'Updated_At' => $now, 'Updated_By' => $nama, 'Updated_By_Id' => $adminId,
            ]);

            // REUSE nilai tes yang masih berlaku: bila tahap berikut adalah tes
            // pihak ke-3 (mis. psikotes CAT) & kandidat punya nilai valid dalam masa
            // berlaku (Master Jenis Tes → Masa_Berlaku_Bulan), pakai ulang — tak
            // perlu tes lagi. Nonaktif otomatis bila masa berlaku tak diset (null/0).
            //
            // ══ HANYA UNTUK TAHAP YANG BENAR-BENAR CUMA SATU TES ══
            //
            // Cabang ini MEMUTUSKAN tahapnya (tetapkanTahap LULUS/GUGUR) tanpa
            // menjalankan apa pun. Pada tahap satu-aktivitas itu memang yang
            // dimaksud. Pada tahap campuran — "FGD + Psikotes + Wawancara" —
            // gerbang lamanya (`Provider = 'THIRD_PARTY'`, ringkasan tingkat
            // tahap) ikut menyala, dan nilai psikotes lama akan MELOMPATI FGD
            // dan wawancara yang tak pernah dijalankan. Kandidat melaju atau
            // gugur atas dasar ujian yang dikerjakannya berbulan-bulan lalu.
            //
            // Karena itu dipagari `tahapCumaSatuTesDaring()`: hanya lolos bila
            // aktivitas penentu tahap itu tepat satu dan memang ujian online.
            if (
                ! empty($berikut->Jenis_Tes_Kode)
                && $this->tahapCumaSatuTesDaring($berikut)
            ) {
                $userId = (int) DB::table('N_WEB_CAREERS_Lamaran')->where('Id_Lamaran', $tahap->Lamaran_Id)->value('Id_Users');
                $lama = $this->nilaiTesBerlaku($userId, $berikut->Jenis_Tes_Kode);
                if ($lama) {
                    $tgl = $lama->Waktu_Callback ? \Illuminate\Support\Carbon::parse($lama->Waktu_Callback)->format('d M Y') : '-';
                    $lulusLama = $this->tentukanLulusTes($lama);
                    DB::table('N_WEB_CAREERS_Lamaran_Tahap')->where('Id_Lamaran_Tahap', $berikut->Id_Lamaran_Tahap)
                        ->update(['Skor' => $lama->Total_Nilai, 'Updated_At' => $now]);
                    Log::channel('web_career')->info("Reuse nilai {$berikut->Jenis_Tes_Kode} lamaran #{$tahap->Lamaran_Id} → ".($lulusLama ? 'LULUS' : 'GUGUR')." (nilai {$lama->Total_Nilai}, {$tgl}).");
                    // Pakai mekanisme yang sama: tetapkan tahap ini otomatis lalu maju.
                    $this->tetapkanTahap((int) $berikut->Id_Lamaran_Tahap, $lulusLama ? 'LULUS' : 'GUGUR',
                        "Nilai {$berikut->Jenis_Tes_Kode} sebelumnya ({$lama->Total_Nilai}, {$tgl}) masih berlaku — dipakai ulang, kandidat tak tes lagi.", $adminId, $now);
                }
            }
        } elseif (! $tuntasDiSini) {
            // Tahap terakhir dan belum ditutup di titik tuntas mana pun.
            DB::table('N_WEB_CAREERS_Lamaran')->where('Id_Lamaran', $tahap->Lamaran_Id)->update([
                'Status' => 'LULUS',
                'Hasil_Akhir' => 'DITERIMA',
                'Waktu_Selesai' => $now,
                'Updated_At' => $now, 'Updated_By' => $nama, 'Updated_By_Id' => $adminId,
            ]);

            // Jalur kedua menuju DITERIMA — pembukuannya harus sama. Kalau hanya
            // salah satu yang membukukan, kursi yang terisi lewat jalur yang
            // terlupa tidak pernah terhitung, dan kuotanya bocor diam-diam.
            KursiPosisi::untukLamaran((int) $tahap->Lamaran_Id);
        }
    }

    /**
     * Nilai tes pihak ke-3 milik kandidat yang MASIH BERLAKU untuk jenis tes ini.
     * Berlaku = ada hasil selesai dalam N bulan terakhir (Master Jenis Tes →
     * Masa_Berlaku_Bulan). Null bila masa berlaku tak diset atau tak ada hasil.
     */
    /**
     * Tahap ini isinya BENAR-BENAR cuma satu ujian online?
     *
     * Dibaca dari aktivitasnya (`Lamaran_Tahap_Tes`), bukan dari kolom
     * ringkasan `Lamaran_Tahap.Provider` — kolom itu bernilai THIRD_PARTY
     * begitu ADA SATU aktivitas online di dalamnya, walau dua aktivitas
     * lainnya dikerjakan tim.
     *
     * Tahap yang aktivitasnya belum sempat disalin (baru saja dibuka, atau
     * lamaran pra-mesin multi-tes) jatuh ke kolom ringkasannya — di situ
     * memang tidak ada informasi yang lebih baik, dan perilakunya sama persis
     * dengan sebelum pemeriksaan ini ada.
     */
    private function tahapCumaSatuTesDaring(object $tahap): bool
    {
        $penentu = DB::table('N_WEB_CAREERS_Lamaran_Tahap_Tes')
            ->where('Lamaran_Tahap_Id', $tahap->Id_Lamaran_Tahap)
            ->where('Peran', 'PENENTU')
            ->get(['Provider']);

        if ($penentu->isEmpty()) {
            return ($tahap->Provider ?? null) === 'THIRD_PARTY';
        }

        return $penentu->count() === 1 && ($penentu->first()->Provider ?? null) === 'THIRD_PARTY';
    }

    public function nilaiTesBerlaku(int $userId, string $jenisTesKode): ?object
    {
        if (! $userId || $jenisTesKode === '') {
            return null;
        }
        $bulan = (int) DB::table('N_WEB_CAREERS_Master_Jenis_Tes')->where('Kode', $jenisTesKode)->value('Masa_Berlaku_Bulan');
        if ($bulan <= 0) {
            return null; // masa berlaku tak diset → selalu tes baru (tak reuse)
        }
        $batas = now()->copy()->subMonths($bulan);

        return DB::table('N_WEB_CAREERS_Penjadwalan_Peserta as pp')
            ->join('N_WEB_CAREERS_Penjadwalan_Tahap as pt', 'pt.Id_Penjadwalan_Tahap', '=', 'pp.Penjadwalan_Tahap_Id')
            ->where('pp.Users_Id', $userId)
            ->where('pt.Jenis_Tes_Kode', $jenisTesKode)
            ->where('pp.Flag_Selesai', 'Y')
            ->whereNotNull('pp.Waktu_Callback')
            ->where('pp.Waktu_Callback', '>=', $batas)
            ->orderByDesc('pp.Waktu_Callback')
            ->select('pp.Total_Nilai', 'pp.Total_Soal', 'pp.Ambang_Batas_Nilai', 'pp.Status_Kelulusan', 'pp.Waktu_Callback')
            ->first();
    }

    /** Simpulkan LULUS/GUGUR dari sebuah hasil tes lama (status teks → nilai vs ambang). */
    private function tentukanLulusTes(object $r): bool
    {
        $teks = strtoupper(trim((string) ($r->Status_Kelulusan ?? '')));
        if (in_array($teks, ['LULUS', 'LOLOS', 'PASS', 'ACCEPT'], true)) {
            return true;
        }
        if (in_array($teks, ['TIDAK LULUS', 'TIDAK_LULUS', 'GAGAL', 'FAIL', 'GUGUR'], true)) {
            return false;
        }
        if ($r->Total_Nilai !== null && $r->Ambang_Batas_Nilai !== null) {
            return (float) $r->Total_Nilai >= (float) $r->Ambang_Batas_Nilai;
        }

        return true; // ada hasil selesai tapi status tak jelas → konservatif: lolos
    }

    /**
     * Simpan kandidat ke Talent Pool saat admin memilih "Masuk Talent Pool".
     * Snapshot ringan (posisi, program, tahap, skor) diambil live agar kartu pool
     * tetap terbaca walau lamaran/posisi berubah. Idempoten per lamaran aktif.
     */
    private function simpanKeTalentPool($tahap, ?string $catatan, ?int $adminId, string $nama, $now): void
    {
        $lam = DB::table('N_WEB_CAREERS_Lamaran as l')
            ->leftJoin('N_WEB_CAREERS_Program as p', 'p.Id_Program', '=', 'l.Program_Id')
            ->leftJoin('N_WEB_CAREERS_Program_Posisi as x', 'x.Id_Program_Posisi', '=', 'l.Program_Posisi_Id')
            ->where('l.Id_Lamaran', $tahap->Lamaran_Id)
            ->select('l.Id_Lamaran', 'l.Id_Users', 'l.Program_Id', 'l.Program_Posisi_Id', 'p.Nama as ProgramNama', 'x.Posisi', 'x.Departemen')
            ->first();
        if (! $lam) {
            return;
        }

        // Jangan menduplikasi kartu AKTIF untuk lamaran yang sama.
        $sudahAda = DB::table('N_WEB_CAREERS_Talent_Pool')
            ->where('Lamaran_Id', $tahap->Lamaran_Id)
            ->where('Status', 'AKTIF')
            ->exists();
        if ($sudahAda) {
            return;
        }

        DB::table('N_WEB_CAREERS_Talent_Pool')->insert([
            'Lamaran_Id' => $lam->Id_Lamaran,
            'Id_Users' => $lam->Id_Users,
            'Program_Id' => $lam->Program_Id,
            'Program_Posisi_Id' => $lam->Program_Posisi_Id,
            'Posisi' => $lam->Posisi,
            'Program_Nama' => $lam->ProgramNama,
            'Tahap_Asal' => $tahap->Label,
            'Departemen' => $lam->Departemen ?? null,
            'Skor' => $tahap->Skor ?? null,
            'Tag' => null,
            'Catatan' => $catatan,
            'Status' => 'AKTIF',
            // Masa berlaku dihitung dari Master Masa Talent Pool yang AKTIF (mis. 6 bulan).
            'Tanggal_Masuk' => $now,
            'Tanggal_Kedaluwarsa' => self::hitungKedaluwarsa($now),
            'Created_At' => $now, 'Created_By' => $nama, 'Created_By_Id' => $adminId,
            'Updated_At' => $now, 'Updated_By' => $nama, 'Updated_By_Id' => $adminId,
        ]);
    }

    /**
     * Tanggal kedaluwarsa kartu Talent Pool = sekarang + durasi master AKTIF.
     * Master data-driven (HARI/BULAN/TAHUN); fallback 6 bulan bila belum diset.
     */
    public static function hitungKedaluwarsa($now)
    {
        $dasar = $now instanceof \Carbon\CarbonInterface ? $now->copy() : \Illuminate\Support\Carbon::parse($now);

        $masa = DB::table('N_WEB_CAREERS_Master_Masa_Talent_Pool')
            ->where('Flag_Aktif', 'Y')
            ->orderByDesc('Id_Master_Masa_Talent_Pool')
            ->first();

        $angka = (int) ($masa->Durasi_Angka ?? 6);
        $satuan = strtoupper($masa->Durasi_Satuan ?? 'BULAN');

        return match ($satuan) {
            'HARI' => $dasar->addDays($angka),
            'TAHUN' => $dasar->addYears($angka),
            default => $dasar->addMonths($angka),
        };
    }

    /**
     * Tarik kandidat dari Talent Pool ke lowongan lain (BISA lintas MPP).
     * Membuat lamaran baru di posisi tujuan mengikuti alur program tujuan; tahap
     * sebelum $mulaiDariUrutan ditandai LULUS + bypass (fast-track). Kartu Talent
     * Pool ditandai DITARIK beserta jejak tujuannya.
     *
     * @return array{ok:bool, pesan:string, lamaranId?:int}
     */
    public function tarikDariTalentPool(int $talentPoolId, int $posisiId, int $mulaiDariUrutan, ?int $adminId): array
    {
        $kartu = DB::table('N_WEB_CAREERS_Talent_Pool')->where('Id_Talent_Pool', $talentPoolId)->first();
        if (! $kartu) {
            return ['ok' => false, 'pesan' => 'Kartu Talent Pool tidak ditemukan.'];
        }
        if ($kartu->Status === 'DITARIK') {
            return ['ok' => false, 'pesan' => 'Kandidat sudah pernah ditarik dari kartu ini.'];
        }
        if (! $kartu->Id_Users) {
            return ['ok' => false, 'pesan' => 'Data kandidat tidak valid.'];
        }

        $posisi = DB::table('N_WEB_CAREERS_Program_Posisi')->where('Id_Program_Posisi', $posisiId)->first();
        if (! $posisi) {
            return ['ok' => false, 'pesan' => 'Posisi tujuan tidak ditemukan.'];
        }
        $program = DB::table('N_WEB_CAREERS_Program')->where('Id_Program', $posisi->Program_Id)->first();
        if (! $program) {
            return ['ok' => false, 'pesan' => 'Program tujuan tidak ditemukan.'];
        }

        // Cegah tarik ke posisi yang kandidatnya sudah punya lamaran aktif di sana.
        $sudah = DB::table('N_WEB_CAREERS_Lamaran')
            ->where('Id_Users', $kartu->Id_Users)
            ->where('Program_Posisi_Id', $posisiId)
            ->where('Status', '!=', 'GUGUR')
            ->exists();
        if ($sudah) {
            return ['ok' => false, 'pesan' => 'Kandidat sudah punya lamaran aktif di posisi tujuan.'];
        }

        $alur = DB::table('N_WEB_CAREERS_Master_Alur')->where('Kode', $program->Alur_Kode)->first();
        $tahap = $alur
            ? DB::table('N_WEB_CAREERS_Master_Alur_Tahap')->where('Master_Alur_Id', $alur->Id_Master_Alur)->orderBy('Urutan')->get()
            : collect();
        if ($tahap->isEmpty()) {
            return ['ok' => false, 'pesan' => 'Program tujuan belum memiliki alur/tahap seleksi.'];
        }

        // Normalisasi titik masuk ke urutan tahap yang valid.
        $urutanValid = $tahap->pluck('Urutan')->map(fn ($u) => (int) $u)->all();
        $mulai = in_array((int) $mulaiDariUrutan, $urutanValid, true) ? (int) $mulaiDariUrutan : min($urutanValid);

        $pembukaan = DB::table('N_WEB_CAREERS_Pembukaan')
            ->where('Program_Id', $program->Id_Program)->where('Status_Publish', 'TERBIT')
            ->orderByDesc('Id_Pembukaan')->first();

        $now = now();
        $nama = session('career_auth.nama', 'ADMIN');
        $kode = 'LMR-'.strtoupper(Str::random(8));

        $lamaranId = DB::transaction(function () use ($kartu, $posisi, $program, $pembukaan, $alur, $tahap, $mulai, $kode, $now, $nama, $adminId, $talentPoolId) {
            $id = DB::table('N_WEB_CAREERS_Lamaran')->insertGetId([
                'Kode' => $kode,
                'Id_Users' => $kartu->Id_Users,
                'Kategori' => $program->Kategori,
                'Program_Id' => $program->Id_Program,
                'Program_Posisi_Id' => $posisi->Id_Program_Posisi,
                'Mpp_Ref' => $posisi->Mpp_Ref ?? null,
                'Pembukaan_Id' => $pembukaan->Id_Pembukaan ?? null,
                'Program_Batch_Id' => $pembukaan->Program_Batch_Id ?? null,
                'Master_Alur_Id' => $alur->Id_Master_Alur ?? null,
                'Urutan_Tahap' => $mulai,
                'Total_Tahap' => $tahap->count(),
                'Status' => 'BERJALAN',
                'Asal_Talent_Pool_Id' => $talentPoolId,
                'Mulai_Dari_Urutan' => $mulai,
                'Waktu_Lamar' => $now,
                'Created_At' => $now, 'Created_By' => $nama, 'Created_By_Id' => $adminId,
                'Updated_At' => $now, 'Updated_By' => $nama, 'Updated_By_Id' => $adminId,
            ], 'Id_Lamaran');

            foreach ($tahap->values() as $t) {
                $bypass = (int) $t->Urutan < $mulai;
                $isEntry = (int) $t->Urutan === $mulai;
                $lamaranTahapId = DB::table('N_WEB_CAREERS_Lamaran_Tahap')->insertGetId([
                    'Lamaran_Id' => $id,
                    'Master_Alur_Tahap_Id' => $t->Id_Master_Alur_Tahap,
                    'Urutan' => $t->Urutan,
                    'Kode' => $t->Kode,
                    'Label' => $t->Label,
                    'Tipe_Tahap_Kode' => $t->Tipe_Tahap_Kode,
                    // Penanda titik tuntas DIBEKUKAN saat lamaran dibuat: alur
                    // boleh disunting kemudian, tapi perjalanan yang sudah
                    // berlangsung tidak boleh berubah maknanya di tengah jalan.
                    'Flag_Tuntas' => $t->Flag_Tuntas ?? 'T',
                    // Cut-off Talent Pool ikut dibekukan: alur boleh disunting
                    // kapan saja, tapi kandidat yang sudah berjalan tidak boleh
                    // berubah aturannya di tengah jalan.
                    'Flag_Talent_Pool' => $t->Flag_Talent_Pool ?? 'T',
                    // Aktivitas tahap ini dikerjakan bersamaan atau berurutan —
                    // ikut dibekukan, sepola dengan aturan lainnya. Nilainya
                    // disalin apa adanya dari master (Kode dari Master Mode
                    // Urutan); tidak ada mode bawaan yang ditulis di sini.
                    'Urutan_Aktivitas' => $t->Urutan_Aktivitas,
                    'Provider' => $t->Provider,
                    // Lihat penjelasan panjang di jalur pendaftaran biasa:
                    // Mode_Keputusan_Kode, bukan kolom `Keputusan` peninggalan.
                    'Keputusan_Mode' => $t->Mode_Keputusan_Kode ?? null,
                    'Flag_Upload_Hasil' => $t->Flag_Upload_Hasil ?? 'T',
                    'Flag_Wajib_Upload' => $t->Flag_Wajib_Upload ?? 'T',
                    'Formulir_Kode' => $t->Formulir_Kode,
                    // Lihat penjelasan di jalur pendaftaran biasa.
                    ...self::bekukanFormulir($t->Formulir_Kode),
                    'Jenis_Tes_Kode' => $t->Jenis_Tes_Kode,
                    'Status' => $bypass ? 'SELESAI' : ($isEntry ? 'BERJALAN' : 'MENUNGGU'),
                    'Hasil' => $bypass ? 'LULUS' : null,
                    'Catatan' => $bypass ? 'Dilewati — fast-track dari Talent Pool.' : null,
                    'Waktu_Selesai' => $bypass ? $now : null,
                    'Flag_Bypass' => $bypass ? 'Y' : 'T',
                    'Mode_Pengumuman' => $t->Mode_Pengumuman ?? 'OTOMATIS',
                    'Flag_Notifikasi' => $t->Flag_Notifikasi ?? 'Y',
                    ...BatasIsi::snapshot($t),
                    'Created_At' => $now, 'Created_By' => $nama, 'Created_By_Id' => $adminId,
                    'Updated_At' => $now, 'Updated_By' => $nama, 'Updated_By_Id' => $adminId,
                ], 'Id_Lamaran_Tahap');

                // Sub-tes hanya dibekukan untuk tahap yang benar-benar dijalani.
                if (! $bypass) {
                    $this->snapshotSubTes($lamaranTahapId, $t->Id_Master_Alur_Tahap, $now, $nama, $adminId);
                }

                // Titik masuk fast-track bisa langsung tahap Kelengkapan —
                // batas pengisiannya dihitung sejak ia dibuka di sini.
                if ($isEntry) {
                    BatasIsi::buka((int) $lamaranTahapId, $now, $nama, $adminId);
                }
            }

            DB::table('N_WEB_CAREERS_Talent_Pool')->where('Id_Talent_Pool', $talentPoolId)->update([
                'Status' => 'DITARIK',
                'Ditarik_Ke_Lamaran_Id' => $id,
                'Ditarik_Ke_Posisi_Id' => $posisi->Id_Program_Posisi,
                'Ditarik_At' => $now,
                'Ditarik_By_Id' => $adminId,
                'Updated_At' => $now, 'Updated_By' => $nama, 'Updated_By_Id' => $adminId,
            ]);

            return $id;
        });

        Log::channel('web_career')->info("Talent Pool #{$talentPoolId} ditarik ke posisi #{$posisiId} (lamaran #{$lamaranId}) mulai tahap {$mulai}.");

        return ['ok' => true, 'pesan' => 'Kandidat ditarik ke lowongan tujuan.', 'lamaranId' => $lamaranId];
    }

    // ═══════════════════ MESIN KEPUTUSAN TAHAP (multi-tes) ═══════════════════
    // Satu tahap bisa punya banyak sub-tes. Cara tahap MENYIMPULKAN dibaca dari
    // Master_Mode_Keputusan (kolom perilaku), bukan hardcode. Untuk tahap 1-tes
    // hasilnya identik dengan perilaku lama.

    /** Bekukan sub-tes master → runtime (dipakai saat lamaran dibuat). */
    private function snapshotSubTes(int $lamaranTahapId, int $masterAlurTahapId, $now, string $nama, ?int $adminId): void
    {
        $tes = DB::table('N_WEB_CAREERS_Master_Alur_Tahap_Tes')
            ->where('Master_Alur_Tahap_Id', $masterAlurTahapId)
            ->orderBy('Urutan')
            ->get();

        // ── ASAL KUESIONER SKRINING ─────────────────────────────────────────
        //
        // Template phone screening menempel PER LOKER, jadi pembekuannya perlu
        // tahu lamaran ini melamar ke posisi mana. Ditelusuri di sini, bukan
        // dioper lewat tanda tangan metode, supaya ketiga pemanggilnya (daftar
        // biasa, tarik dari talent pool, dan penyelarasan sistem) tidak perlu
        // disentuh satu per satu.
        //
        // Kuerinya hanya dijalankan bila tahap ini MEMANG memuat aktivitas
        // skrining — pada alur biasa yang tidak memakainya, tidak ada satu pun
        // kueri tambahan.
        $adaSkrining = Skrining::siap() && $tes->contains(
            fn ($x) => Skrining::untuk($x->Tipe_Tahap_Kode ?? null)
        );

        $asal = $adaSkrining
            ? DB::table('N_WEB_CAREERS_Lamaran_Tahap as lt')
                ->join('N_WEB_CAREERS_Lamaran as l', 'l.Id_Lamaran', '=', 'lt.Lamaran_Id')
                ->where('lt.Id_Lamaran_Tahap', $lamaranTahapId)
                ->first(['l.Program_Id', 'l.Program_Posisi_Id'])
            : null;

        foreach ($tes as $x) {
            DB::table('N_WEB_CAREERS_Lamaran_Tahap_Tes')->insert([
                'Lamaran_Tahap_Id' => $lamaranTahapId,
                'Master_Alur_Tahap_Tes_Id' => $x->Id_Master_Alur_Tahap_Tes,
                'Urutan' => $x->Urutan,
                'Jenis_Tes_Kode' => $x->Jenis_Tes_Kode,
                // Template skrining DIBEKUKAN di sini — kode + versinya, sepola
                // Formulir_Kode + Formulir_Versi. Mengubah pengikatan besok
                // tidak menggeser kandidat yang sudah berjalan.
                ...Skrining::bekukan(
                    $asal->Program_Id ?? null,
                    $asal->Program_Posisi_Id ?? null,
                    (int) $x->Id_Master_Alur_Tahap_Tes,
                    $x->Tipe_Tahap_Kode ?? null
                ),
                // Tipe aktivitas ikut dibekukan: alur boleh berubah nanti, tapi
                // yang dijalani kandidat ini harus tetap seperti saat ia melamar.
                'Tipe_Tahap_Kode' => $x->Tipe_Tahap_Kode,
                'Provider' => $x->Provider,
                'Peran' => $x->Peran,
                'Wajib' => $x->Wajib,
                'Ambang_Dipakai' => $x->Ambang_Batas,
                // Aturan unggahan DIBEKUKAN saat lamaran dibuat: alur boleh
                // disunting kemudian, tapi kandidat yang sudah berjalan tidak
                // boleh tiba-tiba dituntut mengunggah sesuatu yang tak pernah
                // diminta saat ia melamar.
                'Unggah_Kandidat' => $x->Unggah_Kandidat ?? 'T',
                'Unggah_Wajib' => $x->Unggah_Wajib ?? 'T',
                'Unggah_Format' => $x->Unggah_Format ?? null,
                'Unggah_Maks_Mb' => $x->Unggah_Maks_Mb ?? null,
                'Unggah_Petunjuk' => $x->Unggah_Petunjuk ?? null,
                // Visibilitas ikut dibekukan bersama aturan lainnya: alur boleh
                // disunting kemudian, tapi aktivitas internal tidak boleh
                // tiba-tiba muncul di portal kandidat yang sedang berjalan —
                // apalagi kalau isinya cek referensi yang belum selesai.
                'Tampil_Kandidat' => $x->Tampil_Kandidat ?? 'Y',
                // Cara aktivitas ini dinilai (tanpa nilai / angka / kategori),
                // ikut dibekukan: mengubah alur tidak boleh mengubah bentuk
                // penilaian yang sedang dijalani kandidat.
                // Cara perpindahan ke aktivitas berikutnya (otomatis / dipicu
                // admin) ikut dibekukan bersama aturan penilaiannya.
                'Lanjut_Mode' => $x->Lanjut_Mode ?? null,
                'Penilaian_Mode' => $x->Penilaian_Mode ?? null,
                'Penilaian_Opsi' => $x->Penilaian_Opsi ?? null,
                'Nilai_Maks' => $x->Nilai_Maks ?? null,
                'Label' => $x->Label,
                'Status' => 'BELUM',
                'Created_At' => $now, 'Created_By' => $nama, 'Created_By_Id' => $adminId,
                'Updated_At' => $now, 'Updated_By' => $nama, 'Updated_By_Id' => $adminId,
            ]);
        }
    }

    /**
     * Selaraskan ATURAN PENGUMPULAN master → lamaran yang SEDANG BERJALAN.
     *
     * DUA JENIS ATURAN, DUA PERLAKUAN BERBEDA.
     *
     * 1. ATURAN PENILAIAN — ambang batas, peran, mode & skala nilai, mode
     *    lanjut, jenis/tipe aktivitas. Inilah SYARAT KANDIDAT DINILAI. Menggeser
     *    ambang di tengah jalan berarti mengubah aturan main orang yang sudah
     *    mengerjakannya, dan mengubah Nilai_Maks membuat angka 75 yang tercatat
     *    kemarin berarti lain hari ini. Tetap BEKU pada snapshot — jangan
     *    disentuh di sini.
     *
     * 2. ATURAN PENGUMPULAN — apakah kandidat diminta mengunggah, formatnya,
     *    batas ukurannya, petunjuknya, dan apakah aktivitasnya tampil di portal.
     *    Ini bukan syarat penilaian, melainkan PERMINTAAN DOKUMEN. Membekukannya
     *    berarti admin tidak pernah bisa meminta berkas kepada kandidat yang
     *    sudah berjalan — setelan "kandidat harus mengunggah" cuma berlaku bagi
     *    orang yang melamar SESUDAHNYA, sementara 50 orang yang sedang diproses
     *    tidak pernah melihat kotak unggahnya. Itu bukan perlindungan, itu
     *    jalan buntu: tim menunggu berkas yang portalnya tidak pernah minta.
     *
     * PEMERIKSAAN IDENTITAS (bukan sekadar ikut Id).
     *
     * MasterAlurController sengaja MEMAKAI ULANG baris aktivitas per Urutan saat
     * alur disunting, supaya lamaran berjalan tidak kehilangan rujukannya. Efek
     * sampingnya: Master_Alur_Tahap_Tes_Id yang tersimpan di lamaran bisa hari
     * ini menggambarkan aktivitas yang sama sekali lain — "DISC" milik kandidat
     * berubah jadi "Wawancara User" milik alur yang sudah dirombak. Karena itu
     * penyelarasan hanya dilakukan bila LABELNYA masih sama. Kalau sudah tidak,
     * masternya diabaikan dan snapshot kandidat dibiarkan apa adanya.
     *
     * Yang tidak ikut disentuh sama sekali:
     *   - aktivitas yang SUDAH SELESAI — pekerjaan yang sudah ditutup tidak
     *     dibuka lagi cuma karena alurnya disunting;
     *   - tahap yang sudah SELESAI — sama alasannya;
     *   - berkas yang terlanjur diunggah — tidak pernah dihapus, apa pun
     *     setelan barunya.
     *
     * @return int jumlah aktivitas kandidat yang ikut berubah
     */
    public static function selaraskanPengumpulan(int $alurId, ?string $nama = null, ?int $adminId = null): int
    {
        // Hanya baris yang benar-benar BERBEDA yang ditulis: supaya jumlah yang
        // dilaporkan ke admin berarti "sekian kandidat ikut berubah", bukan
        // "sekian baris tersentuh", dan supaya menyimpan alur tanpa mengubah
        // apa pun tidak meninggalkan jejak Updated_At palsu di data kandidat.
        $berbeda = collect([
            ['Unggah_Kandidat', "''"],
            ['Unggah_Wajib', "''"],
            ['Unggah_Format', "''"],
            ['Unggah_Maks_Mb', '-1'],
            ['Unggah_Petunjuk', "''"],
            ['Tampil_Kandidat', "''"],
        ])->map(fn ($k) => "ISNULL(st.{$k[0]}, {$k[1]}) <> ISNULL(mt.{$k[0]}, {$k[1]})")
            ->implode(' OR ');

        return DB::table('N_WEB_CAREERS_Lamaran_Tahap_Tes as st')
            ->join('N_WEB_CAREERS_Lamaran_Tahap as lt', 'lt.Id_Lamaran_Tahap', '=', 'st.Lamaran_Tahap_Id')
            ->join('N_WEB_CAREERS_Master_Alur_Tahap_Tes as mt', 'mt.Id_Master_Alur_Tahap_Tes', '=', 'st.Master_Alur_Tahap_Tes_Id')
            ->join('N_WEB_CAREERS_Master_Alur_Tahap as m', 'm.Id_Master_Alur_Tahap', '=', 'mt.Master_Alur_Tahap_Id')
            ->where('m.Master_Alur_Id', $alurId)
            ->where('st.Flag_Selesai', '<>', 'Y')
            ->where('lt.Status', '<>', 'SELESAI')
            // Identitas, bukan sekadar Id — lihat penjelasan di atas.
            ->whereRaw('LTRIM(RTRIM(st.Label)) = LTRIM(RTRIM(mt.Label))')
            ->whereRaw("({$berbeda})")
            ->update([
                'st.Unggah_Kandidat' => DB::raw('mt.Unggah_Kandidat'),
                'st.Unggah_Wajib' => DB::raw('mt.Unggah_Wajib'),
                'st.Unggah_Format' => DB::raw('mt.Unggah_Format'),
                'st.Unggah_Maks_Mb' => DB::raw('mt.Unggah_Maks_Mb'),
                'st.Unggah_Petunjuk' => DB::raw('mt.Unggah_Petunjuk'),
                'st.Tampil_Kandidat' => DB::raw('mt.Tampil_Kandidat'),
                'st.Updated_At' => now(),
                'st.Updated_By' => $nama ?: 'SISTEM',
                'st.Updated_By_Id' => $adminId,
            ]);
    }

    /**
     * Pastikan tahap punya sub-tes (self-heal lamaran lama pra-mesin).
     *
     * SATU-SATUNYA JALUR YANG MASIH MEMUNGUT DARI MASTER, dan karena itu ia
     * memeriksa dulu apakah masternya masih menggambarkan tahap yang sama.
     *
     * MasterAlurController sengaja MEMAKAI ULANG baris tahap per Urutan saat
     * alur disunting, supaya lamaran berjalan tidak kehilangan rujukan. Efek
     * sampingnya: Id_Master_Alur_Tahap yang tersimpan di lamaran bisa hari ini
     * menggambarkan tahap yang sama sekali lain. Memungut aktivitas dari sana
     * berarti menanam "Wawancara User" ke dalam tahap yang menurut kandidat —
     * dan menurut seluruh layar — bernama "Psikotes".
     *
     * Kalau kodenya sudah tidak cocok, master diabaikan dan tahapnya diberi
     * satu aktivitas yang dibentuk dari dirinya sendiri (blok di bawah). Lebih
     * baik sederhana dan benar daripada lengkap tapi milik tahap lain.
     *
     * PUBLIC karena VersiAlur ikut memakainya: pemindahan kandidat ke alur baru
     * menyisipkan baris TAHAP tanpa satu pun baris AKTIVITAS, dan tahap tanpa
     * aktivitas tidak pernah muncul di Worklist maupun Penjadwalan — keduanya
     * meng-INNER JOIN Lamaran_Tahap_Tes. Kandidatnya hilang dari papan tanpa
     * satu pun galat. Aman dipanggil berkali-kali: baris yang sudah punya
     * aktivitas langsung dilewati.
     */
    public function pastikanSubTes(int $lamaranTahapId): void
    {
        if (DB::table('N_WEB_CAREERS_Lamaran_Tahap_Tes')->where('Lamaran_Tahap_Id', $lamaranTahapId)->exists()) {
            return;
        }
        $t = DB::table('N_WEB_CAREERS_Lamaran_Tahap')->where('Id_Lamaran_Tahap', $lamaranTahapId)->first();
        if (! $t) {
            return;
        }
        if ($t->Master_Alur_Tahap_Id && $this->masterMasihTahapYangSama($t)) {
            $this->snapshotSubTes($lamaranTahapId, (int) $t->Master_Alur_Tahap_Id, now(), 'SISTEM', null);
        }
        // Master tak punya sub-tes (data lama) → buat 1 dari tahap itu sendiri.
        if (! DB::table('N_WEB_CAREERS_Lamaran_Tahap_Tes')->where('Lamaran_Tahap_Id', $lamaranTahapId)->exists()) {
            DB::table('N_WEB_CAREERS_Lamaran_Tahap_Tes')->insert([
                'Lamaran_Tahap_Id' => $lamaranTahapId, 'Urutan' => 1,
                'Jenis_Tes_Kode' => $t->Jenis_Tes_Kode, 'Provider' => $t->Provider ?? 'INTERNAL',
                'Tipe_Tahap_Kode' => $t->Tipe_Tahap_Kode,
                'Peran' => 'PENENTU', 'Wajib' => 'Y', 'Label' => $t->Label, 'Status' => 'BELUM',
                'Created_At' => now(), 'Created_By' => 'SISTEM', 'Updated_At' => now(), 'Updated_By' => 'SISTEM',
            ]);
        }
    }

    /**
     * Rekam hasil satu tes pihak ke-3 (dipanggil callback HCLearn) lalu evaluasi
     * mode tahap. Idempoten via Flag_Selesai per sub-tes.
     *
     * @return array{outcome:string}
     */
    public function rekamHasilTesEksternal(int $lamaranTahapId, ?string $jenisTesKode, string $hasil, ?float $nilai, ?int $totalSoal, ?int $penjadwalanTahapId = null): array
    {
        $this->pastikanSubTes($lamaranTahapId);

        $base = DB::table('N_WEB_CAREERS_Lamaran_Tahap_Tes')
            ->where('Lamaran_Tahap_Id', $lamaranTahapId)
            ->where('Flag_Selesai', 'N');

        $belumSelesai = (clone $base)->orderBy('Urutan')->get();

        if ($belumSelesai->isEmpty()) {
            return ['outcome' => 'NOOP']; // semua sub-tes sudah final (idempoten)
        }

        // ── KE AKTIVITAS MANA HASIL INI MENDARAT ─────────────────────────────
        //
        // Satu tahap bisa memuat FGD, psikotes online, dan wawancara sekaligus.
        // Salah alamat di sini bukan tampilan yang keliru, melainkan DATA yang
        // rusak diam-diam: aktivitas ditandai SELESAI dengan nilai milik ujian
        // lain, dan evaluasiTahap() menyimpulkan tahap dari angka palsu. Tidak
        // ada galat yang muncul di mana pun.
        //
        // Tiga pencarian, dari yang paling pasti ke yang paling lemah:
        //
        //   1. Penjadwalan_Tahap_Id — ikatan pasti sesi ujian ↔ aktivitas,
        //      dibuat saat penjadwalan. Inilah yang seharusnya selalu dipakai.
        //   2. Jenis tes — hanya untuk data lama; alur baru tak menyimpannya.
        //   3. Satu-satunya aktivitas online yang belum selesai.
        //
        // Nomor 2 dan 3 HANYA berlaku bila calonnya TEPAT SATU. Begitu ada dua
        // yang sama-sama mungkin, tidak ada dasar untuk memilih — dan memilih
        // dengan `orderBy('Urutan')->first()` bukan pemilihan, melainkan
        // tebakan yang kebetulan konsisten.
        $sub = $penjadwalanTahapId
            ? $belumSelesai->firstWhere('Penjadwalan_Tahap_Id', $penjadwalanTahapId)
            : null;

        if (! $sub && $jenisTesKode) {
            $cocok = $belumSelesai->where('Jenis_Tes_Kode', $jenisTesKode)->values();
            $sub = $cocok->count() === 1 ? $cocok->first() : null;
        }

        if (! $sub) {
            $online = $belumSelesai->filter(fn ($s) => ($s->Provider ?? '') === 'THIRD_PARTY')->values();
            $sub = $online->count() === 1 ? $online->first() : null;
        }

        // ── TIDAK ADA YANG COCOK: BERHENTI, JANGAN MENEBAK ───────────────────
        //
        // Dulu di sini berdiri `(clone $base)->orderBy('Urutan')->first()` —
        // penyaring KOSONG, jadi hasil ujian online bisa mendarat di aktivitas
        // MANUAL. Pada tahap "FGD + Psikotes + Wawancara", yang tertulis paling
        // sering FGD: kandidat ditandai sudah menjalani FGD yang tak pernah ia
        // hadiri, lengkap dengan nilai psikotesnya. Jalur itu bukan teori — ia
        // terbuka setiap kali Penjadwalan_Tahap_Id menjadi yatim, persis yang
        // terjadi pada JDW-0010.
        //
        // Menolak menulis jauh lebih murah daripada menulis di baris yang
        // salah: yang pertama meninggalkan pekerjaan yang bisa dilihat dan
        // diperbaiki admin, yang kedua meninggalkan catatan yang tampak sah.
        if (! $sub) {
            Log::channel('web_career')->error(sprintf(
                '[HASIL-TES] Hasil ujian tidak bisa dipetakan ke aktivitas mana pun — TIDAK DITULIS. '
                .'Lamaran_Tahap #%d, Penjadwalan_Tahap #%s, jenis tes %s. '
                .'Kandidat aktivitas belum selesai: %s. '
                .'Periksa tautan Penjadwalan_Tahap_Id pada N_WEB_CAREERS_Lamaran_Tahap_Tes.',
                $lamaranTahapId,
                $penjadwalanTahapId ?: '-',
                $jenisTesKode ?: '-',
                $belumSelesai->map(fn ($s) => "#{$s->Id_Lamaran_Tahap_Tes} {$s->Label} ({$s->Provider})")->implode(', ')
            ));

            return ['outcome' => 'ANOMALI'];
        }

        // SATU TRANSAKSI untuk "rekam hasilnya" + "simpulkan tahapnya".
        //
        // Dulu keduanya terpisah: nilai tes ditulis, lalu evaluasiTahap()
        // membuka transaksinya sendiri. Bila evaluasi gagal di tengah — deadlock,
        // koneksi putus — nilainya sudah telanjur tersimpan dan aktivitasnya
        // ditandai selesai, sementara tahapnya tak pernah menyimpulkan apa pun.
        // Hasil ujian dari CAT tidak dikirim dua kali, jadi tahap itu diam
        // selamanya: gerbang idempoten di pemanggil membaca "sudah selesai" dan
        // menolak memprosesnya lagi.
        return DB::transaction(function () use ($sub, $hasil, $nilai, $totalSoal, $penjadwalanTahapId, $lamaranTahapId) {
            // ── SIAPA YANG MENYATAKAN LULUS ──────────────────────────────────
            //
            // PENENTU  → alat tesnya sendiri yang menyatakan. Nilai keluar dari
            //            HCLearn berikut ambang batasnya, jadi verdict-nya
            //            objektif dan langsung final.
            //
            // INFORMATIF → ADMIN yang menyatakan. Alat tes seperti PAPI Kostick,
            //            DISC, dan Kraeplin memang TIDAK berbunyi lulus/gagal —
            //            keluarannya profil kepribadian atau ketelitian, dan
            //            layak-tidaknya seseorang baru muncul saat penilai
            //            membacanya bersama hasil lain di tahap yang sama.
            //
            // Dulu keduanya sama-sama ditutup di sini, dan INFORMATIF ditutup
            // TANPA verdict apa pun. Akibatnya penilaian alat tes kepribadian
            // tidak punya tempat untuk dinyatakan: nilainya masuk, aktivitasnya
            // final, dan tak seorang pun pernah bisa bilang orang ini lolos.
            $adminYangMemutuskan = $sub->Peran === 'INFORMATIF';

            DB::table('N_WEB_CAREERS_Lamaran_Tahap_Tes')->where('Id_Lamaran_Tahap_Tes', $sub->Id_Lamaran_Tahap_Tes)->update([
                // MENUNGGU_KEPUTUSAN bukan sekadar label: seluruh gerbang
                // membaca Flag_Selesai, jadi selama masih 'N' tahapnya belum
                // bisa diloloskan dan kandidat tidak berpindah diam-diam.
                'Status' => $adminYangMemutuskan ? 'MENUNGGU_KEPUTUSAN' : 'SELESAI',
                'Hasil' => $adminYangMemutuskan ? null : ($hasil === 'LULUS' ? 'LULUS' : 'GAGAL'),
                'Nilai' => $nilai,
                'Total_Soal' => $totalSoal,
                'Penjadwalan_Tahap_Id' => $penjadwalanTahapId,
                'Flag_Selesai' => $adminYangMemutuskan ? 'N' : 'Y',
                // Waktu_Selesai = kapan AKTIVITASNYA tuntas, bukan kapan tesnya
                // dikerjakan. Untuk yang menunggu admin, ia belum tuntas.
                'Waktu_Selesai' => $adminYangMemutuskan ? null : now(),
                'Updated_At' => now(),
            ]);

            return $this->evaluasiTahap($lamaranTahapId, null);
        });
    }

    /**
     * TIDAK HADIR — satu-satunya implementasi, dipakai dua pintu:
     *   • tombol "Tidak Hadir" tim (LamaranController::subTesKehadiran);
     *   • pernyataan "tidak melanjutkan seleksi" — dari kandidat (portal /
     *     tautan email) atau dicatat tim (KonfirmasiJadwal::jawab). Keputusan
     *     user 2 Okt 2026: pernyataan itu berlaku PERSIS seperti tim menekan
     *     "Tidak Hadir", supaya tim tak perlu mengekliknya lagi.
     *
     * Aktivitas ditandai tidak hadir berikut catatannya, ditutup (GAGAL,
     * kecuali INFORMATIF yang memang tanpa verdict), lalu mesin keputusan
     * menilai tahapnya — bisa gugur, bisa menunggu aktivitas lain.
     *
     * Transaksi & gerbangnya (jadwal ada, kunciUrutan) milik pemanggil.
     *
     * @return array{outcome: ?string}
     */
    public function catatTidakHadir(object $sub, ?string $html, ?string $ringkas, ?string $oleh, ?int $olehId): array
    {
        $now = now();

        DB::table('N_WEB_CAREERS_Lamaran_Tahap_Tes')->where('Id_Lamaran_Tahap_Tes', $sub->Id_Lamaran_Tahap_Tes)->update([
            'Jadwal_Hadir' => 'T',
            'Jadwal_Hadir_At' => $now,
            'Jadwal_Hadir_By' => $oleh,
            // Catatan lama TIDAK ditimpa dengan kosong: menandai kehadiran sering
            // dilakukan dua kali (salah klik, lalu dibetulkan), dan pembetulan
            // yang catatannya kosong akan menghapus catatan percobaan pertama.
            'Catatan' => $ringkas ?: $sub->Catatan,
            'Catatan_Html' => $html ?: $sub->Catatan_Html,
            'Status' => 'TIDAK_HADIR',
            'Hasil' => $sub->Peran === 'INFORMATIF' ? null : 'GAGAL',
            'Flag_Selesai' => 'Y',
            'Waktu_Selesai' => $now,
            'Updated_At' => $now,
            'Updated_By' => $oleh,
        ]);

        return $this->evaluasiTahap((int) $sub->Lamaran_Tahap_Id, $olehId);
    }

    /**
     * MESIN: evaluasi apakah tahap gugur / maju / menunggu / siap diputus,
     * berdasarkan sub-tes yang sudah masuk + Mode_Keputusan. Transisi ATOMIK
     * (lock baris tahap + compare-and-set) supaya callback bersamaan tak maju dobel.
     *
     * @return array{outcome:string}
     */
    public function evaluasiTahap(int $lamaranTahapId, ?int $adminId = null): array
    {
        return DB::transaction(function () use ($lamaranTahapId, $adminId) {
            $tahap = DB::table('N_WEB_CAREERS_Lamaran_Tahap')
                ->where('Id_Lamaran_Tahap', $lamaranTahapId)
                ->lockForUpdate()
                ->first();
            if (! $tahap || $tahap->Status !== 'BERJALAN') {
                return ['outcome' => 'NOOP'];
            }

            $subs = DB::table('N_WEB_CAREERS_Lamaran_Tahap_Tes')->where('Lamaran_Tahap_Id', $lamaranTahapId)->get();
            if ($subs->isEmpty()) {
                return ['outcome' => 'NOOP'];
            }

            $mode = $this->modeKeputusan($tahap);

            // ── HOLD MENGHENTIKAN MESIN, BUKAN HANYA TOMBOL ADMIN ────────────
            // Tanpa ini, hold tak berarti apa-apa pada tahap ber-mode otomatis:
            // hasil tes terakhir masuk, mesin meloloskan atau menggugurkan
            // sendiri, dan kandidat yang sengaja ditahan sudah telanjur pindah
            // tahap — lengkap dengan emailnya.
            //
            // Hasil aktivitasnya TETAP tersimpan (pemanggil sudah menyimpannya
            // sebelum sampai sini); yang ditahan hanya kesimpulannya. Begitu
            // hold dilepas, evaluasi dijalankan lagi dan hasilnya sama saja —
            // hanya tertunda.
            if (($tahap->Hold_Flag ?? 'T') === 'Y') {
                $this->tandaiSiapDiputus($lamaranTahapId);
                $this->tulisJejak($tahap, $mode->Kode, 'TUNGGU', 'Tahap sedang DITAHAN (hold) — kesimpulan ditunda sampai penahanan dilepas.');

                return ['outcome' => 'HOLD'];
            }

            $selesai = fn ($x) => in_array($x->Status, ['SELESAI', 'TIDAK_HADIR'], true);
            $penentu = $subs->where('Peran', 'PENENTU');
            $wajib = $subs->where('Wajib', 'Y');

            // 1) Auto-gugur — ada tes penentu yang selesai & GAGAL.
            $gagal = $penentu->first(fn ($x) => $selesai($x) && $x->Hasil === 'GAGAL');
            if ($mode->Auto_Gugur === 'Y' && $gagal) {
                $this->tetapkanTahap($lamaranTahapId, 'GUGUR', 'Gugur otomatis — gagal pada tes "'.($gagal->Label ?? '').'".', $adminId, now());
                $this->tulisJejak($tahap, $mode->Kode, 'GUGUR', 'Auto-gugur: tes penentu gagal.');

                return ['outcome' => 'GUGUR'];
            }

            // 2) Kondisi tunggu.
            $wajibTerakhir = $wajib->sortByDesc('Urutan')->first();
            $tungguOk = $mode->Tunggu === 'SEGERA'
                || ($mode->Tunggu === 'SEMUA' && $wajib->every(fn ($x) => $selesai($x)))
                || ($mode->Tunggu === 'TERAKHIR' && (! $wajibTerakhir || $selesai($wajibTerakhir)));
            if (! $tungguOk) {
                $this->tulisJejak($tahap, $mode->Kode, 'TUNGGU', 'Menunggu sub-tes wajib lain selesai.');

                return ['outcome' => 'TUNGGU'];
            }

            // 3) Syarat lulus.
            if ($mode->Syarat_Lulus === 'SEMUA_PENENTU') {
                if (! $penentu->every(fn ($x) => $selesai($x))) {
                    $this->tulisJejak($tahap, $mode->Kode, 'TUNGGU', 'Menunggu seluruh tes penentu selesai.');

                    return ['outcome' => 'TUNGGU'];
                }
                $semuaLulus = $penentu->every(fn ($x) => $x->Hasil === 'LULUS');
                if ($semuaLulus && $mode->Auto_Lanjut === 'Y') {
                    $this->tetapkanTahap($lamaranTahapId, 'LULUS', 'Lolos otomatis — semua tes penentu lulus.', $adminId, now());
                    $this->tulisJejak($tahap, $mode->Kode, 'LANJUT', 'Semua penentu lulus → maju otomatis.');

                    return ['outcome' => 'LANJUT'];
                }
                $this->tandaiSiapDiputus($lamaranTahapId);
                $this->tulisJejak($tahap, $mode->Kode, 'SIAP_DIPUTUS', $semuaLulus ? 'Semua lulus — menunggu keputusan admin.' : 'Ada tes tidak lulus — menunggu keputusan admin.');

                return ['outcome' => 'SIAP_DIPUTUS'];
            }

            // MANUAL — semua sub-tes selesai, admin yang memutuskan.
            $this->tandaiSiapDiputus($lamaranTahapId);
            $this->tulisJejak($tahap, $mode->Kode, 'SIAP_DIPUTUS', 'Semua sub-tes selesai — menunggu keputusan admin.');

            return ['outcome' => 'SIAP_DIPUTUS'];
        });
    }

    /**
     * Mode keputusan tahap — DARI SNAPSHOT LAMARAN, master hanya cadangan.
     *
     * KENAPA SNAPSHOT DULU
     * Dulu ini membaca Master_Alur_Tahap.Mode_Keputusan_Kode hidup-hidup lewat
     * Master_Alur_Tahap_Id, padahal Lamaran_Tahap.Keputusan_Mode sudah menyimpan
     * salinannya sejak orang melamar. Akibatnya dua hal yang sama-sama sunyi:
     *
     *   1. Menyunting mode tahap mengubah aturan orang yang SEDANG menjalaninya.
     *      Tahap yang dimulai sebagai MANUAL bisa tiba-tiba meloloskan sendiri.
     *   2. MasterAlurController memakai ULANG baris tahap per Urutan supaya
     *      lamaran berjalan tak kehilangan rujukan. Efek sampingnya, Id yang
     *      sama bisa berganti arti — dan pembacaan lewat Id itu ikut berganti
     *      arti tanpa ada yang memintanya.
     *
     * Master tetap dipakai sebagai cadangan untuk lamaran lama yang dibuat
     * sebelum kolom snapshot ada; itu satu-satunya kasus yang tersisa.
     */
    /**
     * Apakah baris master yang dirujuk tahap ini MASIH tahap yang sama?
     *
     * Dibandingkan lewat KODE — identitasnya — bukan lewat Id, karena Id-nya
     * memang sengaja dipertahankan saat alur disunting. Tahap lamaran tanpa
     * Kode (data pra-mesin) dianggap masih cocok: di situ tak ada apa pun yang
     * bisa dibandingkan, dan menolak memungut hanya membuat tahapnya kosong.
     */
    private function masterMasihTahapYangSama(object $tahap): bool
    {
        $kodeLamaran = trim((string) ($tahap->Kode ?? ''));
        if ($kodeLamaran === '') {
            return true;
        }

        $kodeMaster = DB::table('N_WEB_CAREERS_Master_Alur_Tahap')
            ->where('Id_Master_Alur_Tahap', $tahap->Master_Alur_Tahap_Id)
            ->value('Kode');

        return trim((string) $kodeMaster) === $kodeLamaran;
    }

    /**
     * Bekukan formulir sebuah tahap: komponen + nomor versi terbit saat ini.
     *
     * KENAPA KOMPONEN, BUKAN CUMA KODE
     * `Formulir_Kode` sudah dibekukan sejak dulu, tapi ia hanya penunjuk. Isi
     * pertanyaannya baru dicari saat halaman dibuka lewat
     * Master_Formulir.Komponen_Kode — dan sambungan itulah yang bocor. Admin
     * mengarahkan komponen ke versi baru; detik itu juga kandidat yang belum
     * mengisi mendapat formulir yang berbeda dari yang dijanjikan saat mereka
     * melamar.
     *
     * BATASNYA JUJUR: ini mengunci formulir MANA yang dipakai. Bila berkas
     * skema di frontend itu sendiri disunting di tempat, tidak ada apa pun di
     * basis data yang bisa menolong. Versi formulir baru wajib komponen baru.
     *
     * @return array{Formulir_Komponen: ?string, Formulir_Versi: ?int}
     */
    private static function bekukanFormulir(?string $formulirKode): array
    {
        if (! $formulirKode) {
            return ['Formulir_Komponen' => null, 'Formulir_Versi' => null];
        }

        $master = DB::table('N_WEB_CAREERS_Master_Formulir')
            ->where('Kode', $formulirKode)
            ->first(['Id_Master_Formulir', 'Komponen_Kode']);

        if (! $master) {
            return ['Formulir_Komponen' => null, 'Formulir_Versi' => null];
        }

        // MAX, bukan sembarang baris terbit: satu formulir bisa punya beberapa
        // baris PUBLISHED dari rilis berturut-turut, dan yang berlaku adalah
        // yang terakhir.
        $versi = DB::table('N_WEB_CAREERS_Master_Formulir_Versi')
            ->where('Master_Formulir_Id', $master->Id_Master_Formulir)
            ->where('Status', 'PUBLISHED')
            ->max('Versi');

        return [
            'Formulir_Komponen' => $master->Komponen_Kode,
            'Formulir_Versi' => $versi !== null ? (int) $versi : null,
        ];
    }

    private function modeKeputusan(object $tahap): object
    {
        // Snapshot dipakai hanya bila kodenya BENAR-BENAR DIKENALI master.
        //
        // Baris lama menyimpan 'MANUAL'/'SYSTEM' — warisan kolom `Keputusan`
        // yang dulu keliru dijadikan sumber snapshot. Kode itu tidak pernah
        // cocok dengan Master_Mode_Keputusan. Kalau snapshot diterima mentah,
        // tahap ber-mode AUTO_SEMUA_LULUS akan diam-diam turun jadi manual dan
        // kandidatnya menggantung menunggu keputusan yang seharusnya otomatis.
        //
        // Jadi: kode tak dikenal DIPERLAKUKAN SEPERTI KOSONG — jatuh ke master,
        // bukan ke bawaan.
        $mode = null;
        $kodeSnapshot = $tahap->Keputusan_Mode ?? null;

        if ($kodeSnapshot) {
            $mode = DB::table('N_WEB_CAREERS_Master_Mode_Keputusan')->where('Kode', $kodeSnapshot)->first();
        }

        if (! $mode && ! empty($tahap->Master_Alur_Tahap_Id)) {
            $kode = DB::table('N_WEB_CAREERS_Master_Alur_Tahap')
                ->where('Id_Master_Alur_Tahap', $tahap->Master_Alur_Tahap_Id)
                ->value('Mode_Keputusan_Kode');

            $mode = $kode ? DB::table('N_WEB_CAREERS_Master_Mode_Keputusan')->where('Kode', $kode)->first() : null;
        }

        return $mode ?: (object) ['Kode' => 'MANUAL_REVIEW', 'Tunggu' => 'SEMUA', 'Auto_Lanjut' => 'N', 'Syarat_Lulus' => 'MANUAL', 'Auto_Gugur' => 'N'];
    }

    /** Tandai tahap siap diputus admin (dibaca worklist di Fase 3). */
    private function tandaiSiapDiputus(int $lamaranTahapId): void
    {
        DB::table('N_WEB_CAREERS_Lamaran_Tahap')->where('Id_Lamaran_Tahap', $lamaranTahapId)->update(['Siap_Diputus' => 'Y', 'Updated_At' => now()]);
    }

    /** Jejak keputusan — audit "kenapa" tiap evaluasi. Tak boleh menggagalkan keputusan. */
    private function tulisJejak(object $tahap, string $modeKode, string $verdict, string $ringkasan): void
    {
        try {
            DB::table('N_WEB_CAREERS_Lamaran_Keputusan_Jejak')->insert([
                'Lamaran_Id' => $tahap->Lamaran_Id ?? null,
                'Lamaran_Tahap_Id' => $tahap->Id_Lamaran_Tahap,
                'Mode_Kode' => $modeKode,
                'Verdict' => $verdict,
                'Ringkasan' => mb_substr($ringkasan, 0, 500),
                'Created_At' => now(),
                'Created_By' => session('career_auth.nama', 'SISTEM'),
            ]);
        } catch (\Throwable $e) {
            Log::channel('web_career')->warning('Gagal tulis jejak keputusan: '.$e->getMessage());
        }
    }
}
