<?php

namespace App\Support\Career;

/**
 * WEB CAREER — MESIN TATA LETAK HALAMAN BERKAS SELEKSI.
 *
 * Menjawab satu pertanyaan: sebuah halaman berisi sekian blok, kanvasnya
 * setinggi 947px — berapa jarak antar-blok supaya hasilnya enak dilihat?
 *
 * ── KENAPA INI PERLU ADA ───────────────────────────────────────────────────
 *
 * Kanvasnya BERUKURAN TETAP (794×1123, bidang isi 674×947) dan isinya mengalir
 * dari atas. Halaman yang isinya sedikit — dua baris sertifikasi, misalnya —
 * karena itu mencetak dua baris di seperempat atas dan menyisakan 60% kertas
 * kosong. Itu bukan salah tata letaknya: kanvasnya memang tidak menyusut.
 *
 * Yang salah adalah membiarkannya. Berkas seleksi dibaca berjajar dengan
 * berkas kandidat lain, dan halaman yang isinya menggantung di atas terbaca
 * seperti cetakan yang terpotong.
 *
 * ── BAWAANNYA DIHITUNG, BUKAN DITEBAK ──────────────────────────────────────
 *
 * Sistem mengukur sendiri: tinggi isi diperkirakan dari jumlah & jenis blok,
 * sisa ruang dibagi ke sela antar-blok. Halaman yang isinya memang penuh tidak
 * disentuh sama sekali — meregangkan halaman penuh justru mendorong barisnya
 * keluar kanvas.
 *
 * Admin boleh mengambil alih kapan saja lewat panel kanan; nilai yang ia
 * tetapkan selalu menang atas hitungan sistem. Itu sebabnya setiap keluaran
 * kelas ini membawa `sumber` — layar perlu tahu mana angka hitungan dan mana
 * angka pilihan orang, supaya bisa menawarkan "kembalikan ke bawaan".
 *
 * ── KENAPA TINGGI DIPERKIRAKAN, BUKAN DIUKUR ───────────────────────────────
 *
 * dompdf baru tahu tinggi sebenarnya SESUDAH merender, sementara jarak harus
 * ditentukan SEBELUMNYA. Mengukur berarti merender dua kali — dan pratinjau
 * yang sudah tiga detik jadi enam. Perkiraannya konservatif: kalau meleset, ia
 * meleset ke arah "terlalu rapat", yang aman. Meleset ke arah sebaliknya
 * mendorong isi keluar kanvas dan itu merusak halaman.
 */
class TataLetakHalaman
{
    /**
     * Tinggi bidang isi: 1123 − top 76 − bottom 84.
     *
     * HARUS sepakat dengan `.isi` di gaya.blade.php. Angka yang lebih besar
     * dari kenyataan membuat penggabungan halaman menghitung ruang yang tidak
     * ada, dan isinya terpotong di luar kanvas.
     */
    public const TINGGI_ISI = 963;

    /** Lebar bidang isi: 794 − left 60 − right 60. */
    public const LEBAR_ISI = 674;

    /**
     * Kerapatan yang bisa dipilih admin.
     *
     * `isi` = seberapa besar sisa ruang yang dipakai untuk meregangkan sela.
     * Bukan 100% pada PENUH: menyisakan sedikit ruang di bawah membuat halaman
     * terbaca sebagai selesai, sementara isi yang mepet ke tepi bawah terbaca
     * sebagai terpotong.
     */
    public const KERAPATAN = [
        'rapat' => ['label' => 'Rapat', 'isi' => 0.0],
        'seimbang' => ['label' => 'Seimbang', 'isi' => 0.55],
        'penuh' => ['label' => 'Penuh', 'isi' => 0.92],
    ];

    /**
     * Jarak pemisah antara dua seksi yang berbagi satu halaman.
     *
     * Lebih besar dari sela antar-bagian (22px): yang dipisahkan di sini
     * adalah dua BAGIAN DOKUMEN yang berbeda — "Pengalaman & Riwayat" lalu
     * "Seleksi Administrasi" — dan keduanya harus tetap terbaca sebagai dua
     * hal, bukan satu daftar panjang yang judulnya berganti di tengah.
     */
    public const SELA_SAMBUNG = 34;

    /**
     * Ruang nafas yang wajib tersisa di kaki halaman sesudah penggabungan.
     *
     * Perkiraan tinggi isi tidak pernah tepat — ia sengaja konservatif, tapi
     * satu bagian dengan jawaban panjang tetap bisa melebihi taksirannya.
     * Tanpa bantalan ini, gabungan yang "pas" di atas kertas menjadi baris
     * terakhir yang terpotong pada cetakan sebenarnya.
     *
     * 120px, dinaikkan dari 60: dengan 60 masih ditemukan halaman gabungan
     * yang isinya meluber sampai y negatif — enam potongan teks tercetak di
     * luar kanvas dan hilang dari cetakan. Empat baris isian adalah harga
     * yang murah untuk jaminan bahwa tidak ada isi yang raib diam-diam.
     */
    private const BANTALAN_SAMBUNG = 120;

    /** Sela bawaan antar-blok sebelum diregangkan (px). */
    private const SELA_DASAR = 22;

    /**
     * Batas atas sela.
     *
     * Sela yang lebih besar dari ini tidak lagi terbaca sebagai "jarak antar
     * bagian" melainkan sebagai bagian yang hilang di tengahnya. Halaman
     * dengan dua blok dan sisa 600px karena itu TIDAK direntangkan sampai
     * 300px per sela — sisanya dibiarkan di bawah, yang jauh lebih wajar.
     */
    private const SELA_MAKS = 72;

    /** Batas bawah — di bawah ini dua blok terbaca menyatu. */
    private const SELA_MIN = 10;

    /**
     * Tinggi perkiraan tiap jenis blok (px).
     *
     * Angkanya dari pengukuran halaman yang sudah dirender, dibulatkan ke atas.
     * Yang tidak dikenal memakai `lain` — perkiraan yang terlalu besar hanya
     * membuat halaman lebih rapat dari perlunya, dan itu tidak merusak apa pun.
     */
    private const TINGGI_BLOK = [
        'judul' => 52,      // judul halaman + garis bawahnya
        'tanda' => 24,      // penanda bagian (garis emas + label)
        'baris' => 34,      // satu baris isian label+nilai
        'timeline' => 64,   // satu entri lini masa
        'tabel' => 30,      // satu baris tabel
        'kartu' => 78,      // satu kartu riwayat
        'lain' => 40,
    ];

    /**
     * Hitung tata letak satu halaman.
     *
     * @param  list<array{jenis?: string, jumlah?: int}>  $blok  isi halaman
     * @param  array  $setel  pengaturan admin untuk halaman ini
     * @return array{sela: int, selaSumber: string, kerapatan: string,
     *               sisa: int, tinggiIsi: int, padat: bool, jumlahBlok: int}
     */
    public static function hitung(array $blok, array $setel = []): array
    {
        $jumlah = count($blok);
        $tinggi = self::perkiraanTinggi($blok);

        // Sela hanya ada DI ANTARA blok: dua blok punya satu sela, bukan dua.
        $sela = max(0, $jumlah - 1);

        $dasar = $tinggi + ($sela * self::SELA_DASAR);
        $sisa = self::TINGGI_ISI - $dasar;

        // ── HALAMAN PADAT TIDAK DIUTAK-ATIK ───────────────────────────────
        //
        // Isinya sudah memenuhi kanvas (atau melebihinya — perakit memotongnya
        // jadi beberapa halaman, dan halaman terakhir bisa saja pas). Menambah
        // sela di sini mendorong baris terakhir keluar kanvas, dan yang hilang
        // justru isi yang sebenarnya ada.
        //
        // Diukur dari SISA RUANG saja, bukan dari jumlah sela. Halaman berisi
        // satu blok tidak punya sela sama sekali — dan itu justru halaman
        // paling kosong yang ada. Menyebutnya "padat" karena tidak ada yang
        // bisa direnggangkan berarti menutup satu-satunya perbaikan yang
        // masih mungkin baginya: menurunkan isinya supaya tidak menggantung
        // di pucuk halaman.
        $padat = $sisa < 40;

        $kerapatan = self::kerapatanSah($setel['kerapatan'] ?? null);

        // ── SISA SELALU DIHITUNG ULANG TERHADAP SELA YANG BENAR-BENAR DIPAKAI
        //
        // `$sisa` di atas mengandaikan sela masih SELA_DASAR. Begitu selanya
        // membesar — entah oleh kerapatan atau oleh angka admin — ruang itu
        // sudah terpakai, dan memakainya lagi sebagai batas geser menghabiskan
        // ruang yang sama dua kali. Akibatnya isi terdorong keluar kanvas dan
        // dompdf memotongnya diam-diam. Ditemukan lewat uji nilai ekstrem:
        // sela 72 + geser 681 menurunkan judul dari y=518 ke y=197.
        $sisaSetelah = fn (int $selaAkhir) => max(
            0,
            self::TINGGI_ISI - ($tinggi + ($sela * $selaAkhir)),
        );

        // Angka yang diketik admin selalu menang atas hitungan sistem —
        // itu inti dari "bawaan dari sistem, kendali penuh di pengguna".
        if (isset($setel['sela']) && $setel['sela'] !== '' && $setel['sela'] !== null) {
            $selaPengguna = self::jepit((int) $setel['sela'], self::SELA_MIN, self::SELA_MAKS);

            return [
                'sela' => $selaPengguna,
                'selaSumber' => 'pengguna',
                'kerapatan' => $kerapatan ?? 'seimbang',
                // Admin yang menetapkan selanya sendiri tidak ikut digeser
                // sistem: ia sudah menyatakan tata letak yang ia mau, dan
                // menurunkannya lagi berarti menimpa keputusan itu.
                'geserSistem' => 0,
                'sisa' => $sisaSetelah($selaPengguna),
                'tinggiIsi' => $tinggi,
                'padat' => $padat,
                'jumlahBlok' => $jumlah,
            ];
        }

        if ($padat) {
            return [
                'sela' => self::SELA_DASAR,
                'selaSumber' => 'padat',
                'kerapatan' => $kerapatan ?? 'rapat',
                'geserSistem' => 0,
                'sisa' => max(0, $sisa),
                'tinggiIsi' => $tinggi,
                'padat' => true,
                'jumlahBlok' => $jumlah,
            ];
        }

        // Bawaan sistem: SEIMBANG. Bukan "penuh" — halaman yang isinya
        // direntangkan habis terbaca seperti daftar yang dijarangkan dengan
        // paksa, dan itu justru lebih mencolok daripada ruang kosong di bawah.
        $pakai = $kerapatan ?? 'seimbang';
        $porsi = self::KERAPATAN[$pakai]['isi'];

        $anggaran = (int) floor($sisa * $porsi);
        $tambahan = (int) floor($anggaran / max(1, $sela));
        $selaAkhir = self::jepit(self::SELA_DASAR + $tambahan, self::SELA_MIN, self::SELA_MAKS);

        // ── SISA YANG TIDAK TERTAMPUNG DI SELA JADI GESER TURUN ───────────
        //
        // Halaman sepi punya sedikit sela — dua blok hanya punya SATU. Sisa
        // 745px dibagi satu sela langsung melampaui SELA_MAKS, sehingga
        // "Seimbang" dan "Penuh" sama-sama mentok di 72 dan saklarnya tidak
        // membedakan apa pun. Yang tidak muat dipakai menurunkan seluruh isi
        // sebagai gantinya, jadi tiap tingkat kerapatan benar-benar terlihat
        // berbeda: isi yang tidak bisa direnggangkan tetap bisa DITURUNKAN
        // supaya ruang kosongnya terbagi atas-bawah, bukan menumpuk di bawah.
        $terpakai = ($selaAkhir - self::SELA_DASAR) * $sela;
        $luber = max(0, $anggaran - $terpakai);

        // Separuh saja: sisanya dibiarkan di bawah. Isi yang dipusatkan penuh
        // menempel ke tepi bawah kanvas, dan halaman tanpa ruang nafas di kaki
        // terbaca seperti cetakan yang terpotong.
        $geserSistem = (int) floor($luber / 2);

        // ── HALAMAN SAMBUNGAN TIDAK DIPUSATKAN ────────────────────────────
        //
        // Memusatkan isi hanya benar untuk halaman yang berdiri sendiri. Pada
        // halaman SAMBUNGAN — "Profil Kandidat" yang berlanjut karena tidak
        // muat — isinya harus mulai dari atas, tepat di bawah kop: pembaca
        // sedang melanjutkan bacaan dari halaman sebelumnya, dan isi yang
        // melayang di tengah membuat jeda 300px yang terbaca seperti ada
        // bagian yang hilang di antaranya.
        if (! empty($setel['lanjutan'])) {
            $geserSistem = 0;
        }

        return [
            'sela' => $selaAkhir,
            'selaSumber' => $kerapatan === null ? 'sistem' : 'kerapatan',
            'kerapatan' => $pakai,
            'geserSistem' => $geserSistem,
            // Ruang yang MASIH tersisa sesudah sela diregangkan — inilah batas
            // sah untuk geser. Lihat catatan pada $sisaSetelah.
            'sisa' => $sisaSetelah($selaAkhir),
            'tinggiIsi' => $tinggi,
            'padat' => false,
            'jumlahBlok' => $jumlah,
        ];
    }

    /**
     * Apakah seksi BERIKUTNYA muat disambung ke halaman ini?
     *
     * ── KENAPA PENGGABUNGAN PERLU ADA ─────────────────────────────────────
     *
     * Tiap seksi dulu SELALU memulai halaman baru (page-break-before pada
     * pembungkusnya). Akibatnya dokumen berisi halaman-halaman yang isinya
     * seperempat kanvas: "Pengalaman & Riwayat" 72% kosong, disusul "Seleksi
     * Administrasi" 42% kosong — dua lembar untuk isi yang muat dalam satu.
     *
     * Kendali tata letak per halaman tidak bisa menolong di sini: semuanya
     * bekerja DI DALAM satu halaman. Menaikkan isi Seleksi Administrasi
     * membawanya ke pucuk halamannya sendiri, bukan ke halaman sebelumnya.
     *
     * ── YANG DIPERIKSA ────────────────────────────────────────────────────
     *
     * Tinggi kedua isi + sela penyambung + bantalan harus muat di bidang isi
     * (947px). Perkiraan tingginya sama dengan yang dipakai hitung(), jadi
     * keputusan di sini dan tata letak yang dicetak berangkat dari angka yang
     * sama.
     *
     * @param  array  $tataIni       hasil hitung() halaman ini
     * @param  list<array>  $blokBerikut  blok isi seksi berikutnya
     */
    public static function muatDisambung(array $tataIni, array $blokBerikut): bool
    {
        if (! $blokBerikut) {
            return false;
        }

        $tinggiIni = (int) ($tataIni['tinggiIsi'] ?? 0);
        $selaIni = (int) ($tataIni['sela'] ?? self::SELA_DASAR);
        $jumlahIni = max(0, (int) ($tataIni['jumlahBlok'] ?? 1) - 1);

        // Halaman ini dihitung ulang dengan sela DASAR, bukan sela hasil
        // peregangan: begitu dua seksi berbagi halaman, ruang kosong yang tadi
        // dipakai merenggangkan justru dibutuhkan untuk menampung seksi kedua.
        // Memakai sela yang sudah terlanjur melar membuat gabungan yang
        // sebenarnya muat ditolak.
        $terpakai = $tinggiIni + ($jumlahIni * min($selaIni, self::SELA_DASAR));

        $tinggiBerikut = self::perkiraanTinggi($blokBerikut)
            + (max(0, count($blokBerikut) - 1) * self::SELA_DASAR);

        return ($terpakai + self::SELA_SAMBUNG + $tinggiBerikut + self::BANTALAN_SAMBUNG)
            <= self::TINGGI_ISI;
    }

    /**
     * Perkiraan tinggi SATU blok isi (px).
     *
     * Publik karena perakit memakainya saat menjumlahkan tinggi seksi yang
     * disambung; angka yang dipakai memutuskan dan angka yang dipakai
     * mencetak harus berasal dari tabel yang sama.
     */
    public static function tinggiBlok(array $b): int
    {
        $jenis = (string) ($b['jenis'] ?? 'lain');
        $satuan = self::TINGGI_BLOK[$jenis] ?? self::TINGGI_BLOK['lain'];

        return $satuan * max(1, (int) ($b['jumlah'] ?? 1));
    }

    /**
     * Perkiraan tinggi seluruh blok, tanpa sela.
     *
     * @param  list<array{jenis?: string, jumlah?: int}>  $blok
     */
    private static function perkiraanTinggi(array $blok): int
    {
        $t = 0;

        // `jumlah` di tiap blok = berapa baris/entri di dalamnya. Satu blok
        // "timeline" berisi enam sertifikat setinggi enam entri, bukan satu —
        // lihat tinggiBlok().
        foreach ($blok as $b) {
            $t += self::tinggiBlok($b);
        }

        return $t;
    }

    /** Kerapatan yang dikirim layar — ditolak bila bukan salah satu yang sah. */
    private static function kerapatanSah(mixed $v): ?string
    {
        $v = is_string($v) ? strtolower(trim($v)) : '';

        return isset(self::KERAPATAN[$v]) ? $v : null;
    }

    private static function jepit(int $v, int $min, int $maks): int
    {
        return max($min, min($maks, $v));
    }

    /**
     * Ukuran teks isi halaman, dalam px.
     *
     * Dibatasi 9–13: di bawah 9px isian tidak lagi terbaca pada cetakan
     * kertas, di atas 13px satu halaman memuat terlalu sedikit sehingga
     * dokumen justru bertambah panjang — kebalikan dari yang dicari admin
     * ketika ia menaikkan ukuran huruf.
     */
    public static function ukuranTeks(array $setel): array
    {
        $bawaan = 11;
        $v = $setel['teks'] ?? null;

        if ($v === null || $v === '') {
            return ['nilai' => $bawaan, 'sumber' => 'sistem'];
        }

        return ['nilai' => self::jepit((int) $v, 9, 13), 'sumber' => 'pengguna'];
    }

    /**
     * Geser seluruh isi halaman ke bawah (px).
     *
     * Untuk halaman yang isinya sedikit dan lebih enak dilihat bila mengambang
     * di tengah alih-alih menggantung di atas.
     *
     * Bawaannya BUKAN nol: hitung() menitipkan `geserSistem` — sisa ruang yang
     * tidak tertampung di sela. Tanpa itu halaman berisi satu blok tidak punya
     * perbaikan apa pun (tak ada sela untuk direnggangkan) dan tetap
     * menggantung di pucuk kanvas. Angka dari admin, bila ada, menang.
     *
     * Dibatasi sisa ruang yang benar-benar ada: menggeser lebih jauh dari itu
     * mendorong isi keluar kanvas, dan dompdf memotongnya tanpa memberi tahu
     * siapa pun.
     *
     * @param  array  $tata  hasil hitung() halaman ini
     */
    public static function geserAtas(array $setel, array|int $tata): int
    {
        // Bentuk lama (int sisa) masih diterima: halaman profil memanggilnya
        // dengan angka tetap karena tidak melewati hitung() sama sekali.
        $sisa = is_array($tata) ? (int) ($tata['sisa'] ?? 0) : (int) $tata;
        $bawaan = is_array($tata) ? (int) ($tata['geserSistem'] ?? 0) : 0;

        $v = $setel['geser'] ?? null;

        if ($v === null || $v === '') {
            return self::jepit($bawaan, self::NAIK_MAKS, max(0, $sisa));
        }

        return self::jepit((int) $v, self::NAIK_MAKS, max(0, $sisa));
    }

    /**
     * Batas MENAIKKAN isi (nilai negatif, px).
     *
     * Halaman yang isinya sedikit tidak selalu ingin diturunkan — sering yang
     * dicari justru sebaliknya: isinya dirapatkan ke atas supaya seluruh ruang
     * kosongnya berkumpul jadi satu di kaki halaman, bukan terbelah tipis di
     * atas dan bawah. Itu sebabnya geser boleh negatif.
     *
     * -30px, tidak lebih: bidang isi mulai di y=92 sementara kop berakhir di
     * sekitar y=58 (top 40 + tinggi barisnya + padding 9). Menaikkan lebih
     * jauh dari itu membuat judul halaman menabrak garis bawah kop, dan
     * dokumen resmi yang judulnya menempel di kopnya terbaca seperti salah
     * cetak.
     */
    public const NAIK_MAKS = -30;
}
