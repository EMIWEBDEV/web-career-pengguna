<?php

namespace App\Support\Career;

/**
 * WEB CAREER — menyusun CV kandidat dari SEMUA formulir yang ia isi.
 *
 * ── APA YANG DIKERJAKAN ────────────────────────────────────────────────────
 *
 * Berkas seleksi bukan arsip formulir, melainkan RESUME. Sumbernya bisa satu
 * formulir (Rekrutmen) atau dua (MT: Pendaftaran + Kelengkapan Data), dan
 * jumlahnya ditentukan program — bukan dipatok di sini. Kelas ini melebur
 * seluruhnya menjadi satu susunan CV: tiap fakta muncul SEKALI, di bagian yang
 * memang tempatnya.
 *
 * Sebelum ini tiap formulir dicetak apa adanya berurutan, sehingga alamat dan
 * nama tercetak dua kali — sekali di halaman profil, sekali lagi di halaman
 * formulirnya. Yang dibaca rekruter jadi tumpukan borang, bukan CV.
 *
 * ── KENAPA BERBASIS PERAN, BUKAN NAMA BAGIAN ───────────────────────────────
 *
 * Di produksi ada empat kategori formulir dengan susunan yang sama sekali
 * berbeda: MT punya "Data Diri / Status Pendidikan / Kesediaan", Rekrutmen
 * punya "Data Keluarga / Visi Misi / Referensi", Magang punya susunannya
 * sendiri. Memetakan nama bagian ke bagian CV berarti setiap formulir baru
 * yang dirancang tim rekrutmen hilang dari CV sampai ada yang menyunting
 * berkas ini.
 *
 * Karena itu tiap bagian formulir dinilai PERANNYA dari isinya sendiri —
 * apakah ia riwayat berulang, jawaban ya/tidak, daftar dokumen, atau isian
 * biasa — lalu ditempatkan pada bagian CV yang cocok. Bagian yang tidak
 * dikenali TIDAK dibuang: ia jatuh ke "Informasi Tambahan", karena jawaban
 * yang hilang diam-diam dari dokumen resmi jauh lebih berbahaya daripada
 * jawaban yang tampil di bagian yang kurang tepat.
 *
 * ── YANG TIDAK DIKERJAKAN DI SINI ──────────────────────────────────────────
 *
 * Menyaring isian yang tidak dicentang admin. Itu tugas perakit; kelas ini
 * menerima apa yang sudah lolos centang dan hanya menyusunnya.
 */
class SusunCv
{
    /**
     * Bagian CV, dalam urutan cetak.
     *
     * `kunci` dipakai panel Export Studio untuk mencentang dan mengatur
     * bentuknya; `bentuk` adalah bawaan yang bisa ditimpa admin.
     */
    public const BAGIAN = [
        // ── SUSUNAN MENGIKUTI CARA CV DIBACA ──────────────────────────────
        //
        // `lajur` menentukan penempatan pada halaman dua kolom:
        //   'kiri'  — kolom sempit (206px): fakta ringkas yang disapu cepat
        //             untuk memastikan kandidat memenuhi syarat.
        //   'utama' — kolom lebar (404px): riwayat yang benar-benar dibaca
        //             untuk menilai — pengalaman, organisasi, sertifikasi.
        //   'penuh' — selebar halaman: daftar panjang & deret pernyataan yang
        //             akan terhimpit bila dipaksa masuk kolom.
        //
        // Alasannya cara membaca CV: mata menyapu kolom kiri untuk verifikasi
        // (pendidikan, kemampuan, kontak), lalu berhenti di kolom utama untuk
        // menilai. Menaruh riwayat pengalaman di kolom sempit memaksa tiap
        // entri patah tiga baris dan menghapus keunggulan lini masa.
        //
        // `urutan` berlaku DI DALAM lajurnya masing-masing.
        'ringkas' => ['judul' => 'Profil Singkat', 'bentuk' => 'kartu', 'lajur' => 'kiri', 'urutan' => 1],
        'pendidikan' => ['judul' => 'Pendidikan', 'bentuk' => 'timeline', 'lajur' => 'kiri', 'urutan' => 2],
        'kemampuan' => ['judul' => 'Kemampuan', 'bentuk' => 'kisi', 'lajur' => 'kiri', 'urutan' => 3],
        'keluarga' => ['judul' => 'Keluarga', 'bentuk' => 'tabel', 'lajur' => 'kiri', 'urutan' => 4],

        'pengalaman' => ['judul' => 'Pengalaman', 'bentuk' => 'timeline', 'lajur' => 'utama', 'urutan' => 1],
        'organisasi' => ['judul' => 'Organisasi & Aktivitas', 'bentuk' => 'timeline', 'lajur' => 'utama', 'urutan' => 2],
        'sertifikasi' => ['judul' => 'Sertifikasi & Pelatihan', 'bentuk' => 'timeline', 'lajur' => 'utama', 'urutan' => 3],
        'referensi' => ['judul' => 'Referensi', 'bentuk' => 'tabel', 'lajur' => 'utama', 'urutan' => 4],

        // Deret pernyataan & daftar dokumen: satu pertanyaan satu baris,
        // dibaca dengan menyapu kolom jawaban di kanan. Dipaksa ke kolom
        // sempit, pertanyaannya patah dan kolom jawabannya hilang.
        // ── DERET YA/TIDAK KE KOLOM KIRI ──────────────────────────────────
        //
        // Di lajur penuh 674px, kolom jawabannya dipatok 52px di tepi kanan
        // sementara pertanyaannya berhenti di kiri — mata harus menyeberangi
        // ruang kosong selebar setengah halaman untuk tiap baris, dan
        // hasilnya terbaca renggang serta tidak presisi.
        //
        // Di kolom kiri 206px jawabannya menempel pada pertanyaannya: satu
        // ceklist rapat yang disapu sekali lihat — itu bentuk yang benar
        // untuk deret pernyataan.
        'kesiapan' => ['judul' => 'Kesiapan Kerja', 'bentuk' => 'ceklist', 'lajur' => 'kiri', 'urutan' => 5],
        'tambahan' => ['judul' => 'Informasi Tambahan', 'bentuk' => 'ceklist', 'lajur' => 'kiri', 'urutan' => 6],
    ];

    /**
     * Lebar ISI tiap lajur (px) — angka yang dioper ke partial penggambar.
     *
     * BUKAN lebar selnya. dompdf menambahkan padding & border DI LUAR `width`,
     * jadi sel kiri ditulis `width:206px` lalu diberi padding 16 + garis 1;
     * totalnya 223, dan bersama sel kanan (404 + padding 15) menghasilkan 642
     * — muat di bidang 674. Menulis 238 di sini lalu menambahkan padding di
     * blade membuat totalnya melebihi bidang, dan seluruh isi tabel terdorong
     * keluar sampai halamannya tercetak hanya berisi judul.
     *
     * cv.blade.php WAJIB memakai konstanta ini, bukan angka tersendiri:
     * dua sumber angka yang diam-diam berbeda adalah cara paling mudah
     * menghidupkan kembali bug itu.
     */
    public const LEBAR_LAJUR = ['kiri' => 206, 'utama' => 404, 'penuh' => 674];

    /**
     * Kata kunci penentu peran, diperiksa pada JUDUL bagian.
     *
     * Urutannya berarti: yang lebih khusus lebih dulu. "pengalaman organisasi"
     * harus tertangkap `organisasi`, bukan `pengalaman` — kalau tidak, riwayat
     * organisasi tercetak di bawah judul Pengalaman Kerja.
     */
    private const PETA_JUDUL = [
        // Dokumen lebih dulu dari apa pun: "F. Kelengkapan Dokumen" memuat
        // kata "kelengkapan" yang bisa tertangkap peran lain.
        'dokumen' => ['dokumen', 'berkas', 'lampiran', 'unggah'],
        'sertifikasi' => ['sertifik', 'pelatihan', 'training', 'kursus', 'non formal'],
        'organisasi' => ['organisasi', 'kepanitiaan', 'volunt'],
        'pengalaman' => ['pengalaman kerja', 'pengalaman', 'magang', 'riwayat kerja', 'pekerjaan', 'aktivitas'],
        'pendidikan' => ['pendidikan', 'sekolah', 'kampus', 'studi', 'akademik'],
        'keluarga' => ['keluarga', 'anggota keluarga', 'saudara'],
        'kemampuan' => ['kemampuan', 'keahlian', 'skill', 'bahasa', 'komputer'],
        'referensi' => ['referensi', 'kenalan', 'kontak yang', 'rujukan'],
        'kesiapan' => ['kesiapan', 'kesediaan', 'bersedia', 'penempatan'],
        // "Informasi Tambahan" & "Pernyataan" bukan kesiapan kerja: yang
        // pertama pertanyaan kesehatan/latar, yang kedua persetujuan hukum.
        // Keduanya berhak berdiri sendiri di akhir CV.
        'tambahan' => ['informasi tambahan', 'lain-lain'],
    ];

    /**
     * Bagian yang TIDAK PERNAH masuk CV, dikenali dari judulnya.
     *
     * ── KENAPA DIBUANG DI HULU, BUKAN CUMA DIMATIKAN CENTANGNYA ───────────
     *
     * Halaman Data Kandidat adalah RESUME — disusun dari formulir, tapi yang
     * dicetak adalah fakta tentang orangnya. Tiga hal ini bukan fakta itu:
     *
     *   Kelengkapan Dokumen  daftar NAMA BERKAS unggahan
     *                        ("LMR-PTPVSC3G-frans-bachtiar-28.pdf"). Nama file
     *                        tidak mengatakan apa pun tentang kandidat, dan
     *                        berkasnya sendiri tercetak UTUH di bab Lampiran.
     *   Verifikasi           "Apakah data di atas sudah sesuai? → Sesuai" dan
     *                        foto verifikasi wajah: alat kontrol pengisian
     *                        formulir, bukan riwayat kandidat.
     *   Pernyataan Persetujuan  persetujuan hukum ("Saya menyatakan bahwa
     *                        seluruh data…"). Tempatnya berkas administrasi,
     *                        bukan resume.
     *
     * Dibuang di sini supaya tidak ada jalan lain yang bisa memunculkannya
     * kembali — mematikan centangnya saja tidak cukup, karena centang bisa
     * dinyalakan lagi dan tersimpan per kandidat.
     */
    private const BUKAN_CV = [
        'kelengkapan dokumen', 'kelengkapan berkas', 'unggah dokumen',
        'dokumen pendukung', 'lampiran dokumen', 'upload',
        'verifikasi', 'validasi data',
        'pernyataan persetujuan', 'persetujuan', 'pernyataan',
    ];

    /** Apakah bagian ini sama sekali bukan bahan CV. */
    private static function bukanCv(array $b): bool
    {
        $judul = mb_strtolower(trim((string) ($b['judul'] ?? '')));

        // Awalan penomoran dibuang: "F. Kelengkapan Dokumen" harus dikenali
        // sama dengan "Kelengkapan Dokumen".
        $judul = preg_replace('/^[a-z0-9]{1,3}[.)]\s*/u', '', $judul);

        foreach (self::BUKAN_CV as $kata) {
            if (str_contains($judul, $kata)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Susun CV dari hasil LaporanKandidat::rakit().
     *
     * @param  array  $d  hasil rakit() — kandidat, lamaran, panen, formulir
     * @return list<array{kunci: string, judul: string, bentuk: string,
     *                    isian: list<array>, baris: list<array>, sumber: list<string>}>
     */
    public static function bagian(array $d, bool $adaHalamanProfil = false): array
    {
        // Nilai yang SUDAH tercetak di halaman profil — nama, kontak, NIK,
        // alamat, kampus, dan seterusnya. Halaman itu adalah muka CV; mencetak
        // ulang isinya di bagian "Profil Singkat" tepat di halaman berikutnya
        // membuat dokumen terbaca seperti dua salinan data yang sama.
        //
        // Dicocokkan dari NILAI, bukan label: halaman profil memanen lewat
        // label ("Nama Lengkap Sesuai KTP" → nama), jadi label aslinya tidak
        // pernah sama persis.
        $sudahDiProfil = $adaHalamanProfil ? self::nilaiProfil($d) : [];

        $peta = [];

        foreach ($d['formulir'] ?? [] as $f) {
            $namaForm = (string) ($f['label'] ?? '');

            foreach ($f['bagian'] ?? [] as $b) {
                // Daftar berkas, verifikasi pengisian, dan persetujuan hukum
                // tidak pernah jadi bahan resume — lihat BUKAN_CV.
                if (self::bukanCv($b)) {
                    continue;
                }

                $peran = self::peran($b);

                $peta[$peran] ??= ['isian' => [], 'baris' => [], 'sumber' => []];

                if (! in_array($namaForm, $peta[$peran]['sumber'], true)) {
                    $peta[$peran]['sumber'][] = $namaForm;
                }

                foreach ($b['isian'] ?? [] as $i) {
                    if (! empty($i['baris'])) {
                        // Riwayat berulang: barisnya digabung apa adanya,
                        // lengkap dengan judul asalnya supaya dua riwayat
                        // berbeda dalam satu peran tetap bisa dibedakan.
                        $peta[$peran]['baris'][] = [
                            'judul' => (string) ($b['judul'] ?? $i['label'] ?? ''),
                            'kunci' => (string) ($b['kunci'] ?? ''),
                            'isian' => $i,
                        ];

                        continue;
                    }

                    // ── PERTANYAAN PENYARING TIDAK IKUT ───────────────
                    //
                    // "Apakah memiliki pengalaman kerja/magang? → Tidak"
                    // adalah SAKLAR formulir: jawabannya menentukan apakah
                    // tabel riwayat di bawahnya ditampilkan. Riwayatnya
                    // sendiri yang jadi isi CV.
                    //
                    // Dicetak di resume, hasilnya bagian "Pengalaman" yang
                    // isinya tiga pertanyaan ya/tidak dan bukan satu pun
                    // riwayat — persis kebalikan dari gunanya.
                    if (self::pertanyaanSaklar((string) ($i['label'] ?? ''), (string) ($i['nilai'] ?? ''))) {
                        continue;
                    }

                    $peta[$peran]['isian'][] = $i;
                }
            }
        }

        // Isian yang sama dari dua formulir hanya dicetak sekali — inilah
        // pengulangan yang membuat berkas terbaca sebagai tumpukan borang.
        foreach ($peta as $peran => $isi) {
            $bersih = self::tanpaUlangan($isi['isian']);

            if ($sudahDiProfil) {
                $bersih = array_values(array_filter(
                    $bersih,
                    function ($i) use ($sudahDiProfil) {
                        $nilai = (string) ($i['nilai'] ?? '');
                        $sidik = self::sidik($nilai);

                        // Sidik kosong berarti "tidak layak dicocokkan" —
                        // BUKAN "cocok dengan yang kosong". Tanpa penjagaan
                        // ini, satu nilai kosong di halaman profil membuang
                        // setiap isian pendek di seluruh CV.
                        if ($sidik !== '' && isset($sudahDiProfil[$sidik])) {
                            return false;
                        }

                        // Nilai pendek (S1, 3.78, 8) dicocokkan lewat peran
                        // labelnya — lihat catatan di nilaiProfil().
                        $peran = self::peranLabel((string) ($i['label'] ?? ''));

                        if ($peran !== '') {
                            $kunci = '#' . $peran . '=' . mb_strtolower(trim($nilai));

                            if (isset($sudahDiProfil[$kunci])) {
                                return false;
                            }
                        }

                        return true;
                    },
                ));
            }

            $peta[$peran]['isian'] = $bersih;
        }

        $hasil = [];

        foreach (self::BAGIAN as $kunci => $def) {
            $isi = $peta[$kunci] ?? null;

            if (! $isi) {
                continue;
            }

            // Pendidikan sudah dicetak utuh di halaman biodata — lihat
            // catatan '#peran=pendidikan' di nilaiProfil().
            if ($kunci === 'pendidikan' && isset($sudahDiProfil['#peran=pendidikan'])) {
                continue;
            }

            // ── BAGIAN TANPA ISI YANG TERCETAK DIBUANG ────────────────────
            //
            // Punya isian tidak berarti punya JAWABAN. Kandidat yang
            // melewatkan "Daftar Kenalan di EVO Group" tetap membawa
            // fieldnya, hanya nilainya kosong — dan partial-cv-bagian
            // menyaring nilai kosong saat mencetak. Hasilnya dulu: penanda
            // "REFERENSI" tercetak sendirian tanpa satu baris pun di
            // bawahnya, terbaca seperti bagian yang isinya gagal dimuat.
            //
            // Disaring di sini, bukan di blade: bagian yang tidak tercetak
            // juga tidak boleh ikut menghitung tinggi halaman, dan tidak
            // boleh muncul sebagai centang di panel kiri.
            $adaIsi = collect($isi['isian'])->contains(
                fn ($f) => trim((string) ($f['nilai'] ?? '')) !== '' || ! empty($f['berkas']),
            );

            if (! $adaIsi && ! $isi['baris']) {
                continue;
            }

            $hasil[] = [
                'kunci' => 'cv.' . $kunci,
                'peran' => $kunci,
                'judul' => $def['judul'],
                'bentuk' => $def['bentuk'],
                // Penempatan bawaan — bisa ditimpa admin lewat Export Studio.
                'lajur' => $def['lajur'],
                'urutan' => $def['urutan'],
                'isian' => $isi['isian'],
                'baris' => $isi['baris'],
                // Dari formulir mana bagian ini disusun — dicetak kecil di
                // bawah judulnya supaya asal-usul tiap fakta bisa ditelusuri.
                'sumber' => $isi['sumber'],
            ];
        }

        return $hasil;
    }

    /**
     * Peran sebuah bagian formulir dalam CV.
     *
     * Dinilai dari judulnya lebih dulu — itu yang paling menerangkan maksud
     * perancang. Bila judulnya tidak menjelaskan apa-apa ("Bagian 1"), isinya
     * yang bicara: bagian berulang bertanggal kemungkinan riwayat, deret
     * jawaban ya/tidak kemungkinan pernyataan kesiapan.
     */
    private static function peran(array $b): string
    {
        $judul = mb_strtolower(trim((string) ($b['judul'] ?? '')));

        foreach (self::PETA_JUDUL as $peran => $kata) {
            foreach ($kata as $k) {
                if ($judul !== '' && str_contains($judul, $k)) {
                    return $peran;
                }
            }
        }

        return self::peranDariIsi($b);
    }

    /** Tebakan peran ketika judulnya tidak menerangkan apa pun. */
    private static function peranDariIsi(array $b): string
    {
        $isian = $b['isian'] ?? [];

        // Bagian berulang tanpa judul yang jelas paling sering riwayat kerja.
        foreach ($isian as $i) {
            if (! empty($i['baris'])) {
                return 'pengalaman';
            }
        }

        $biner = 0;
        $total = 0;

        foreach ($isian as $i) {
            $v = trim((string) ($i['nilai'] ?? ''));

            if ($v === '') {
                continue;
            }

            $total++;

            if (self::biner($v)) {
                $biner++;
            }
        }

        // Deret pertanyaan ya/tidak = pernyataan kesiapan/kesediaan.
        if ($total > 0 && $biner / $total >= 0.6) {
            return 'kesiapan';
        }

        // Sisanya jadi profil singkat: identitas, kontak, data pribadi.
        return $total > 0 ? 'ringkas' : 'tambahan';
    }

    private static function biner(string $v): bool
    {
        return in_array(mb_strtolower($v), [
            'ya', 'y', 'true', '1', 'sudah', 'bersedia', 'setuju',
            'tidak', 't', 'false', '0', 'belum',
        ], true);
    }

    /**
     * Nilai-nilai yang sudah tercetak di halaman profil.
     *
     * Sumbernya `panen` — hasil LaporanKandidat memanen jawaban formulir untuk
     * halaman profil — ditambah kolom kandidat & lamaran yang halaman itu
     * cetak langsung. Semuanya disidik dengan aturan yang sama seperti
     * pencocokan duplikat, supaya "6.700.000" dan "6700000" dikenali sama.
     *
     * @return array<string, true>
     */
    private static function nilaiProfil(array $d): array
    {
        $nilai = [];

        $kumpul = function ($v) use (&$nilai, &$kumpul) {
            if (is_array($v)) {
                foreach ($v as $x) {
                    $kumpul($x);
                }

                return;
            }

            $s = self::sidik((string) $v);

            if ($s !== '') {
                $nilai[$s] = true;
            }
        };

        // Jawaban yang dipanen halaman profil: NIK, agama, alamat, kontak
        // darurat, kesiapan, ekspektasi gaji.
        $kumpul($d['panen'] ?? []);

        // Kolom yang dicetak halaman profil langsung dari data kandidat.
        foreach (['nama', 'email', 'hp', 'nik', 'tglLahir', 'jkel',
            'kampus', 'jurusan', 'jenjang', 'statusStudi', 'ipk'] as $k) {
            $kumpul($d['kandidat'][$k] ?? null);
        }

        // ── NILAI PENDEK DICOCOKKAN LEWAT PERANNYA ────────────────────────
        //
        // sidik() sengaja membuang nilai di bawah 4 karakter: "8" atau "S1"
        // yang berdiri sendiri terlalu mudah cocok dengan angka lain yang
        // kebetulan sama, dan salah-buang jauh lebih berbahaya daripada
        // salah-cetak.
        //
        // Tapi justru nilai pendidikan berbentuk begitu — "S1", "3.78", "8" —
        // sehingga jenjang, IPK, dan semester tidak pernah tertangkap dan
        // TERCETAK DUA KALI: sekali di halaman biodata, sekali lagi di bagian
        // Pendidikan halaman berikutnya.
        //
        // Untuk nilai-nilai ini kecocokan ditandai per PERAN, bukan per nilai
        // telanjang: yang dibandingkan "jenjang=s1", bukan "s1".
        foreach (['jenjang', 'ipk', 'statusStudi', 'semester'] as $k) {
            $v = trim((string) ($d['kandidat'][$k] ?? ''));

            if ($v !== '') {
                $nilai['#' . $k . '=' . mb_strtolower($v)] = true;
            }
        }

        // ── PENDIDIKAN HANYA DI SATU TEMPAT ───────────────────────────────
        //
        // Halaman biodata sudah mencetak blok "Pendidikan" lengkap: nama
        // kampus, jenjang, jurusan, IPK, status kemahasiswaan. Sisa isian
        // pendidikan di formulir — "Program Studi", "Jenis Institusi
        // Pendidikan" — kalau ikut tercetak di CV menghasilkan penanda
        // PENDIDIKAN kedua berisi dua remah, sementara isi sebenarnya ada di
        // halaman sebelumnya. Pembaca melihat pendidikan dua kali, terpecah,
        // dan tak satu pun lengkap.
        //
        // Ditandai per PERAN supaya berlaku untuk seluruh isian pendidikan
        // apa pun labelnya di formulir mana pun — bukan daftar label tetap.
        $nilai['#peran=pendidikan'] = true;

        foreach (['posisi', 'program', 'departemen', 'level', 'lokasi'] as $k) {
            $kumpul($d['lamaran'][$k] ?? null);
        }

        return $nilai;
    }

    /**
     * Apakah sebuah isian hanya saklar penampil riwayat.
     *
     * Bentuknya khas: pertanyaan "apakah memiliki/punya <sesuatu>" yang
     * dijawab ya/tidak, dan <sesuatu> itu justru bagian riwayat yang berdiri
     * sendiri di CV. Yang bernilai bagi pembaca adalah riwayatnya.
     *
     * Pertanyaan kesehatan & latar ("buta warna", "riwayat asma", "pengalaman
     * di area produksi") TIDAK termasuk: tidak ada tabel riwayat di baliknya,
     * dan jawabannya sendiri yang jadi informasi.
     */
    private static function pertanyaanSaklar(string $label, string $nilai): bool
    {
        if (! self::biner($nilai)) {
            return false;
        }

        $l = mb_strtolower(trim($label));

        if (! str_starts_with($l, 'apakah memiliki') && ! str_starts_with($l, 'apakah punya')) {
            return false;
        }

        // Hanya yang riwayatnya benar-benar berdiri sendiri sebagai bagian CV.
        foreach (['pengalaman kerja', 'pengalaman organisasi', 'magang',
            'sertifikasi', 'pelatihan', 'organisasi'] as $kata) {
            if (str_contains($l, $kata)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Peran sebuah label untuk nilai pendek yang tak bisa disidik.
     *
     * Hanya untuk isian yang nilainya terlalu pendek buat dicocokkan sendiri
     * ("S1", "3.78", "8"). Dikenali dari labelnya, dan labelnya stabil karena
     * dibaca kandidat saat mengisi.
     */
    private static function peranLabel(string $label): string
    {
        $l = mb_strtolower(trim($label));

        if ($l === '') {
            return '';
        }

        // IPK lebih dulu: "IPK" tidak memuat kata lain, tapi "Jenjang
        // Pendidikan" dan "Semester" bisa saling menyerempet.
        if (str_contains($l, 'ipk') || str_contains($l, 'ip kumulatif')) {
            return 'ipk';
        }

        if (str_contains($l, 'jenjang')) {
            return 'jenjang';
        }

        if (str_contains($l, 'semester')) {
            return 'semester';
        }

        if (str_contains($l, 'status kemahasiswaan') || str_contains($l, 'status studi')) {
            return 'statusStudi';
        }

        return '';
    }

    /**
     * Sidik jari sebuah nilai untuk pencocokan.
     *
     * Kosong berarti nilainya tidak layak dicocokkan — terlalu pendek, atau
     * jawaban ya/tidak yang berulang di banyak pertanyaan.
     */
    private static function sidik(string $v): string
    {
        $t = trim($v);

        if ($t === '' || self::biner($t)) {
            return '';
        }

        $n = mb_strtolower(preg_replace('/\s+/u', ' ', $t));
        $angka = preg_replace('/[.,\s]/u', '', $n);

        if ($angka !== '' && ctype_digit($angka)) {
            $n = $angka;
        }

        return mb_strlen($n) < 4 ? '' : $n;
    }

    /**
     * Buang isian yang menjawab hal yang sama.
     *
     * Dicocokkan dari NILAI, bukan label — sebab yang sama dengan
     * DuplikatIsian: "Email" dan "Email Terdaftar" mirip, tapi "No Handphone
     * Aktif (WA)" dan "No. WhatsApp Terdaftar" nyaris tak berbagi kata.
     *
     * Yang PERTAMA dipertahankan: formulir diurutkan dari yang terlama, dan
     * jawaban pertama adalah yang dipakai kandidat mendaftar.
     *
     * Jawaban ya/tidak dikecualikan — belasan "Ya" bukan belasan duplikat.
     *
     * @param  list<array>  $isian
     * @return list<array>
     */
    private static function tanpaUlangan(array $isian): array
    {
        $terlihat = [];
        $hasil = [];

        foreach ($isian as $i) {
            $v = trim((string) ($i['nilai'] ?? ''));

            if ($v === '' || self::biner($v)) {
                $hasil[] = $i;

                continue;
            }

            $n = mb_strtolower(preg_replace('/\s+/u', ' ', $v));
            $angka = preg_replace('/[.,\s]/u', '', $n);

            if ($angka !== '' && ctype_digit($angka)) {
                $n = $angka;
            }

            // Nilai sangat pendek tidak dianggap penanda — terlalu mudah
            // kebetulan sama antar-pertanyaan yang berbeda.
            if (mb_strlen($n) < 4) {
                $hasil[] = $i;

                continue;
            }

            if (isset($terlihat[$n])) {
                continue;
            }

            $terlihat[$n] = true;
            $hasil[] = $i;
        }

        return $hasil;
    }
}
