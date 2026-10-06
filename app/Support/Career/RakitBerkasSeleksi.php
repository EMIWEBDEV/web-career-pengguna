<?php

namespace App\Support\Career;

use App\Services\WebCareers\LaporanTesClient;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Vinkla\Hashids\Facades\Hashids;

/**
 * WEB CAREER — perakit akhir BERKAS SELEKSI KANDIDAT.
 *
 * Mengubah pilihan centang admin menjadi satu berkas PDF, dalam tiga langkah:
 *
 *   1. susun daftar halaman yang dicentang, dalam urutan dokumen;
 *   2. render halaman buatan sistem dengan dompdf;
 *   3. gabungkan dengan hasil psikotes & lampiran memakai FPDI.
 *
 * ── HASIL PSIKOTES & BERKAS PENILAIAN DIGABUNG POLOS ──────────────────────
 *
 * Laporan dari CAT sudah berupa PDF utuh dengan kop, tera air, dan bilah
 * kerahasiaannya sendiri; berkas FGD/wawancara adalah dokumen yang dipindai
 * asesor. Keduanya masuk APA ADANYA — tanpa kop, kaki, bingkai, maupun nomor
 * halaman dari kita. Membingkainya lagi hanya menghasilkan dua kerangka
 * bertumpuk, dan pada berkas pindaian justru menutupi isinya.
 *
 * ── URUTAN HALAMAN TIDAK BISA DIKETAHUI DOMPDF ────────────────────────────
 *
 * Karena bagian bawaan digabung SESUDAH dompdf selesai, counter(page) berhenti
 * di tengah dokumen. Maka penomoran dihitung di sini: seluruh halaman
 * dipetakan lebih dulu (termasuk berapa halaman tiap lampiran), barulah
 * nomornya disuntikkan ke tiap seksi. Itu pula sebabnya rendering berjalan
 * dua kali — lihat render().
 */
class RakitBerkasSeleksi
{
    /** Kode bab, urut bawaan dokumen. */
    private const BAB_SAMPUL = 'SAMPUL';

    private const BAB_DATA = 'DATA_KANDIDAT';

    private const BAB_HASIL = 'HASIL_SELEKSI';

    private const BAB_LAMPIRAN = 'LAMPIRAN';

    private const BAB_PENUTUP = 'PENUTUP';

    /**
     * Lebar isi tiap lajur CV — cermin SusunCv::LEBAR_LAJUR.
     *
     * Dipakai menaksir tinggi bagian: kolom sempit mencetak satu isian per
     * baris, jadi tingginya berbeda untuk isi yang sama.
     */
    public const LEBAR_KIRI = 206;

    public const LEBAR_UTAMA = 404;

    public const LEBAR_PENUH = 674;

    private const BAB_BAWAAN = [
        self::BAB_SAMPUL,
        self::BAB_DATA,
        self::BAB_HASIL,
        self::BAB_LAMPIRAN,
        self::BAB_PENUTUP,
    ];

    /**
     * Rakit berkas seleksi.
     *
     * @param  list<string>  $kunci  kunci seksi yang dicentang admin
     * @param  bool  $ringan  true = pratinjau: LAMPIRAN FORMULIR diganti halaman
     *                        penanda. Berkas penilaian FGD/wawancara tetap
     *                        digabung sungguhan — lihat blokHasil().
     * @param  list<string>  $bab  urutan bab yang digeser admin; kosong = bawaan
     * @return string byte PDF
     */
    public static function jalankan(
        int $lamaranId,
        array $kunci,
        bool $ringan = false,
        ?int $penggunaId = null,
        array $atur = [],
        array $bab = [],
    ): string {
        // Urutan array $kunci ADALAH susunan dokumen — layar mengirimnya sesuai
        // yang tampak di panel kiri, yang bisa digeser admin. `array_flip`
        // menjaga urutan itu sebagai nilai, sementara kuncinya dipakai untuk
        // pemeriksaan cepat "dicentang atau tidak".
        $pilih = array_flip($kunci);
        $urutKunci = array_flip(array_values($kunci));

        $d = LaporanKandidat::rakit($lamaranId, self::pengisianTerpilih($pilih));

        if (! $d) {
            throw new \RuntimeException('Data lamaran tidak ditemukan.');
        }

        $logo = LaporanKandidat::logoDataUri();

        // Blok hasil seleksi & lampiran disiapkan lebih dulu: keduanya
        // menyumbang halaman ke dokumen, dan sampul maupun indeks harus tahu
        // totalnya sebelum dirender.
        // $ringan diteruskan HANYA untuk laporan psikotes dari CAT (17 detik
        // per pratinjau, tata letaknya tidak bisa disetel). Berkas penilaian
        // FGD/wawancara tetap digabung sungguhan — lihat blokHasil().
        $hasil = self::blokHasil($lamaranId, $pilih, $penggunaId, $ringan);
        $lampiran = self::siapkanLampiran($lamaranId, $pilih, $ringan);

        $urutan = self::susun($pilih, $d, $hasil, $lampiran, $urutKunci, $atur, $bab);

        return self::terbitkan($d, $logo, $urutan);
    }

    /**
     * Daftar halaman yang tata letaknya bisa disetel admin.
     *
     * Dipanggil layar bersamaan dengan pratinjau, supaya panel kanan tahu
     * halaman apa saja yang ada di dokumen yang BARU SAJA dirender — bukan
     * yang mungkin ada.
     *
     * ── KENAPA TIDAK DITEBAK DI MUKA ──────────────────────────────────────
     * Berapa halaman yang dihasilkan satu formulir baru diketahui sesudah
     * potongFormulir() memotongnya, dan itu bergantung pada bagian mana saja
     * yang dicentang admin. Mendaftarnya di BerkasSeleksi::daftar() berarti
     * menebak — dan panel kanan akan menawarkan halaman 3 pada dokumen yang
     * hanya punya dua.
     *
     * Yang TIDAK masuk daftar: halaman hasil seleksi & lampiran — keduanya PDF
     * yang digabung apa adanya, tidak ada tata letak milik kita di sana.
     *
     * Sampul, penutup, dan pemisah bab IKUT terdaftar (sejak kunci halaman
     * diberikan ke seluruh seksi bergaya rancangan), tapi dilaporkan
     * `'tetap' => true`: komposisinya mutlak, jadi panel menyebutnya "tata
     * letak tetap" alih-alih menawarkan tombol yang tak berefek.
     *
     * @return list<array{kunci: string, label: string, induk: string, tata: array}>
     */
    public static function petaHalaman(
        int $lamaranId,
        array $kunci,
        array $atur = [],
        array $bab = [],
    ): array {
        $pilih = array_flip($kunci);
        $urutKunci = array_flip(array_values($kunci));

        $d = LaporanKandidat::rakit($lamaranId, self::pengisianTerpilih($pilih));

        if (! $d) {
            return [];
        }

        // Hasil & lampiran dilewati: keduanya memanggil CAT dan mengunduh
        // berkas, sementara satu pun halamannya tidak bisa disetel. Melewatinya
        // membuat pemanggilan ini murah — panel kanan tidak boleh menunggu
        // jaringan hanya untuk tahu ada halaman apa saja.
        $urutan = self::susun($pilih, $d, [], [], $urutKunci, $atur, $bab);

        $hasil = [];
        $no = 0;

        foreach ($urutan as $s) {
            // Seksi yang menumpang TIDAK menambah nomor halaman — ia berbagi
            // lembar dengan pendahulunya. Menghitungnya sebagai halaman baru
            // membuat nomor di panel kanan meleset dari yang tercetak, dan
            // admin menyetel halaman yang salah.
            if (empty($s['sambung'])) {
                $no++;
            }

            if (empty($s['kunciHalaman'])) {
                continue;
            }

            $hasil[] = [
                'kunci' => $s['kunciHalaman'],
                'label' => self::labelHalaman($s),
                'induk' => self::indukHalaman($s),
                'no' => $no,
                'jenis' => $s['jenis'],
                // Sedang berbagi lembar dengan halaman sebelumnya. Panel kanan
                // memakainya untuk menandai baris yang tergabung, dan untuk
                // menawarkan saklar "Mulai di halaman sendiri".
                'sambung' => ! empty($s['sambung']),
                // Hitungan sistem untuk halaman ini — panel kanan memakainya
                // sebagai angka bawaan yang ditampilkan sebelum admin menyentuh
                // apa pun, dan sebagai sasaran tombol "Kembalikan ke bawaan".
                'tata' => self::tataHalaman($s),
            ];
        }

        return $hasil;
    }

    /** Nama halaman sebagaimana dilihat admin di panel kanan. */
    private static function labelHalaman(array $s): string
    {
        if ($s['jenis'] === 'CV') {
            // Nama bagian yang benar-benar ada di halaman itu — jauh lebih
            // berguna daripada "CV (sambungan)" berulang kali, karena admin
            // memilih halaman untuk disetel berdasarkan isinya.
            $judul = collect(array_merge(
                $s['kiri'] ?? [],
                $s['utama'] ?? [],
                $s['bagian'] ?? [],
            ))->pluck('judul')->filter();

            if ($judul->isEmpty()) {
                return 'Data Kandidat';
            }

            return $judul->count() > 2
                ? $judul->take(2)->implode(', ') . ' +' . ($judul->count() - 2)
                : $judul->implode(', ');
        }

        if ($s['jenis'] === 'DATA_KANDIDAT') {
            return 'Data Kandidat';
        }

        if ($s['jenis'] === 'PENGALAMAN') {
            return 'Pengalaman & Riwayat';
        }

        // Seksi bergaya rancangan menamai dirinya sendiri. Tanpa ini semuanya
        // jatuh ke cabang formulir di bawah dan tercetak "Formulir" — tiga
        // baris berjudul sama di panel Tata letak, tak satu pun bisa dibedakan.
        if ($s['jenis'] === 'SAMPUL') {
            return 'Halaman Sampul';
        }

        if ($s['jenis'] === 'PEMISAH') {
            return (string) ($s['judul'] ?? 'Pembatas Bab');
        }

        if ($s['jenis'] === 'INDEKS_LAMPIRAN') {
            return 'Indeks Dokumen Terlampir';
        }

        if ($s['jenis'] === 'PENUTUP') {
            return 'Halaman Penutup';
        }

        $label = (string) ($s['judul'] ?? '') ?: (string) ($s['form']['label'] ?? 'Formulir');

        // Halaman sambungan diberi nomor: satu formulir panjang menghasilkan
        // beberapa baris berjudul sama, dan tanpa nomor admin tidak punya cara
        // menebak yang mana sedang ia setel.
        return empty($s['lanjutan']) ? $label : $label . ' (sambungan)';
    }

    private static function indukHalaman(array $s): string
    {
        return match ($s['jenis']) {
            'CV' => 'Disusun dari ' . ($s['jumlahFormulir'] ?? 1) . ' formulir',
            'DATA_KANDIDAT', 'PENGALAMAN' => 'Dirakit dari jawaban formulir',
            'SAMPUL', 'PENUTUP' => 'Halaman bergaya rancangan',
            'PEMISAH' => 'Pembatas antar-bab',
            'INDEKS_LAMPIRAN' => 'Daftar dokumen terlampir',
            default => (string) ($s['form']['label'] ?? ''),
        };
    }

    /**
     * Hitungan tata letak sistem untuk satu halaman.
     *
     * Mengulang perkiraan yang dipakai blade-nya. Disengaja: yang dipakai
     * mencetak adalah hitungan di blade, dan panel kanan harus menunjukkan
     * angka yang SAMA — bukan angka lain yang kebetulan mirip.
     */
    private static function tataHalaman(array $s): array
    {
        $setel = $s['setelHalaman'] ?? [];

        if ($s['jenis'] === 'CV') {
            // ── HALAMAN CV TIDAK PERNAH DIPUSATKAN ────────────────────────
            //
            // Pemusatan vertikal cocok untuk halaman bergambar yang berdiri
            // sendiri. Halaman CV isinya DAFTAR — pendidikan, riwayat,
            // pernyataan — dan daftar dibaca dari atas. Dipusatkan, ia
            // menyisakan jurang ~120px antara kop dan baris pertamanya, yang
            // terbaca seperti ada bagian yang gagal dimuat.
            //
            // 'lanjutan' dipaksa true di sini: itulah saklar yang menahan
            // pemusatan di TataLetakHalaman::hitung().
            return TataLetakHalaman::hitung(
                self::blokUkur($s),
                $setel + ['lanjutan' => true],
            );
        }

        if ($s['jenis'] === 'DATA_KANDIDAT') {
            // Halaman profil tidak diregangkan selanya — blok-bloknya bertaut.
            return ['sela' => null, 'padat' => true, 'sisa' => 0, 'jumlahBlok' => 1];
        }

        // ── HALAMAN BERTATA-LETAK TETAP ───────────────────────────────────
        //
        // Sampul, pemisah bab, dan penutup adalah komposisi utuh: isinya
        // diletakkan mutlak terhadap kanvas (judul di sepertiga atas, kaki di
        // 945px), bukan mengalir dari atas. Meregangkan selanya tidak
        // mengubah apa pun, dan menggesernya merusak komposisinya.
        //
        // Dilaporkan `padat` supaya panel Tata letak menyebutnya apa adanya —
        // "tata letak tetap" — alih-alih menawarkan tombol yang tak berefek.
        // Menawarkan setelan yang diam-diam tidak bekerja jauh lebih buruk
        // daripada mengatakan halaman ini memang tidak bisa disetel.
        if (in_array($s['jenis'], ['SAMPUL', 'PEMISAH', 'PENUTUP'], true)) {
            return [
                'sela' => null,
                'padat' => true,
                'tetap' => true,
                'sisa' => 0,
                'jumlahBlok' => 1,
            ];
        }

        if ($s['jenis'] === 'INDEKS_LAMPIRAN') {
            // Indeks: satu baris per dokumen. Jumlahnya diketahui di sini,
            // jadi selanya benar-benar bisa diregangkan.
            return TataLetakHalaman::hitung(
                [
                    ['jenis' => 'judul', 'jumlah' => 1],
                    ['jenis' => 'tabel', 'jumlah' => max(1, count($s['lampiran'] ?? []))],
                ],
                $setel,
            );
        }

        if ($s['jenis'] === 'PENGALAMAN') {
            // Riwayat: jumlah bloknya baru diketahui blade dari isian berulang.
            // Perkiraan kasar sudah cukup untuk menampilkan sisa ruang.
            return TataLetakHalaman::hitung(
                [['jenis' => 'judul', 'jumlah' => 1], ['jenis' => 'timeline', 'jumlah' => 3]],
                $setel,
            );
        }

        $blok = collect($s['bagian'] ?? [])
            ->map(fn ($b) => [
                'jenis' => 'baris',
                'jumlah' => max(1, (int) ceil(count($b['isian'] ?? []) / 2)) + 1,
            ])
            ->all();

        if (empty($s['lanjutan'])) {
            array_unshift($blok, ['jenis' => 'judul', 'jumlah' => 1]);
        }

        return TataLetakHalaman::hitung($blok, $setel);
    }

    // ══ SUSUNAN ════════════════════════════════════════════════════════════

    /**
     * Urutan seluruh dokumen: seksi dompdf & sisipan PDF, berselang-seling.
     *
     * Tiap elemen bertanda `sisip` (byte PDF yang digabung apa adanya) atau
     * merupakan seksi yang akan dirender dompdf.
     *
     * ── URUTAN BAB MENGIKUTI ADMIN ────────────────────────────────────────
     * Tiap bab dirakit menjadi blok tersendiri, lalu blok-blok itu disambung
     * menurut $bab — susunan yang digeser admin di panel kiri. Sampul dan
     * Penutup dikembalikan ke ujungnya masing-masing oleh urutanBab(): keduanya
     * penanda batas dokumen, dan berkas yang sampulnya terselip di tengah bukan
     * berkas yang bisa diarsipkan.
     *
     * Urutan DI DALAM tiap bab tetap mengikuti $urutKunci.
     *
     * @param  list<string>  $bab  urutan kode bab; kosong = urutan bawaan
     */
    private static function susun(
        array $pilih,
        array $d,
        array $hasil,
        array $lampiran,
        array $urutKunci = [],
        array $atur = [],
        array $bab = [],
    ): array {
        $blok = [];
        $ada = fn (string $k) => isset($pilih[$k]);

        if ($ada('sampul')) {
            // JUDUL SAMPUL TIDAK MENGIKUTI ADMIN — sengaja.
            //
            // Sampul adalah muka dokumen resmi; judulnya seragam untuk seluruh
            // kandidat supaya setumpuk berkas bisa dikenali sebagai satu jenis
            // arsip. Layar tidak lagi menawarkan kolomnya, tapi penjagaannya
            // ada DI SINI juga: pengaturan disimpan di localStorage, jadi
            // `atur['sampul']['judul']` dari versi layar sebelumnya masih bisa
            // sampai ke sini berbulan-bulan kemudian. Diabaikan, bukan dipakai.
            // Kunci halaman diberikan ke SEMUA seksi bergaya rancangan,
            // bukan hanya profil & CV. Tanpa kunci, halaman itu tidak pernah
            // muncul di panel Tata letak — admin melihat 2 baris dari 6
            // halaman yang sebenarnya ada, dan mengira fiturnya rusak.
            $blok[self::BAB_SAMPUL][] = [
                'jenis' => 'SAMPUL',
                'judul' => null,
                'kunciHalaman' => 'sampul',
                'setelHalaman' => $atur['hal']['sampul'] ?? [],
            ];
        }

        // Surat lamaran sengaja TIDAK dirender walau kuncinya terkirim —
        // templatnya belum ada di Human Capital eVO Group. Penjagaan di sisi
        // server, bukan hanya checkbox yang dinonaktifkan di layar.

        $urut = [];

        // ── HALAMAN PROFIL & RIWAYAT DIRAKIT OTOMATIS ─────────────────────
        //
        // Keduanya TIDAK punya centang sendiri: isinya dipanen dari jawaban
        // formulir yang sama, jadi menawarkannya terpisah berarti satu sumber
        // muncul sebagai tiga pilihan — dan jawaban yang sama tercetak dua kali
        // bila admin mencentang semuanya.
        //
        // Muncul begitu ada satu formulir yang dicentang, dan hilang sendiri
        // bila tidak ada. Riwayat hanya ikut bila memang ada isian berulang
        // yang bisa diceritakan.
        $adaFormulir = collect($d['formulir'] ?? [])
            ->contains(fn ($f) => self::formulirDipilih($f, $pilih));

        if ($adaFormulir) {
            // HALAMAN PROFIL TETAP — ia kepala CV, bukan pengulangannya.
            //
            // Yang dimuatnya tidak ada di bagian CV mana pun: foto kandidat,
            // nama besar, posisi yang dilamar, dan status lamarannya. Itu
            // halaman muka sebuah resume, dan bagian-bagian CV di belakangnya
            // adalah isinya.
            $urut[] = [
                'jenis' => 'DATA_KANDIDAT',
                'kunciHalaman' => 'profil',
                'setelHalaman' => $atur['hal']['profil'] ?? [],
            ];

            // HALAMAN RIWAYAT DICABUT.
            //
            // Isinya — pengalaman, organisasi, sertifikasi — kini jadi bagian
            // CV tersendiri yang bisa dicentang dan diatur bentuknya satu per
            // satu. Membiarkan keduanya berarti riwayat yang sama tercetak
            // dua kali: sekali di halaman ini, sekali lagi di CV. Itulah
            // pengulangan yang membuat berkas terbaca sebagai tumpukan borang.
        }

        // ── HALAMAN CV — MENGGANTIKAN CETAK TIAP FORMULIR ─────────────────
        //
        // Dulu tiap formulir dicetak apa adanya berurutan, sehingga alamat dan
        // nama tercetak dua kali: sekali di halaman profil, sekali lagi di
        // halaman formulirnya. Yang dibaca rekruter jadi tumpukan borang,
        // bukan CV.
        //
        // SusunCv melebur seluruh formulir — satu untuk Rekrutmen, dua untuk
        // MT, berapa pun yang dipakai program — jadi bagian CV yang tiap
        // faktanya muncul sekali. Yang dicentang admin tetap yang menentukan
        // isinya: penyaringan dilakukan di sini, sebelum penyusunan.
        $dTersaring = self::saringUntukCv($d, $pilih);
        // `$adaFormulir` menandakan halaman profil ikut dicetak — CV lalu
        // membuang isian yang sudah tercetak di sana. Lihat SusunCv.
        $bagianCv = SusunCv::bagian($dTersaring, $adaFormulir);

        // Bagian CV yang dicentang admin di panel kiri (kunci 'cv.<peran>').
        $bagianCv = collect($bagianCv)
            ->filter(fn ($b) => isset($pilih[$b['kunci']]))
            ->sortBy(fn ($b) => $urutKunci[$b['kunci']] ?? 9999)
            ->values()
            ->all();

        if ($bagianCv) {
            $jumlahForm = count($dTersaring['formulir'] ?? []);

            foreach (self::potongCv($bagianCv, $atur) as $i => $potong) {
                $kh = 'cv#' . $i;

                $urut[] = [
                    'jenis' => 'CV',
                    'mode' => $potong['mode'],
                    // Halaman dua kolom membawa `kiri` & `utama`; halaman
                    // lajur penuh membawa `bagian`.
                    'kiri' => $potong['kiri'] ?? [],
                    'utama' => $potong['utama'] ?? [],
                    'bagian' => $potong['bagian'] ?? [],
                    // Bagian lajur penuh yang menumpang di kaki halaman dua
                    // kolom — lihat potongCv().
                    'ekor' => $potong['ekor'] ?? [],
                    'lanjutan' => $i > 0,
                    'jumlahFormulir' => $jumlahForm,
                    'judulBab' => $atur['bab.' . self::BAB_DATA]['judul'] ?? 'Data Kandidat',
                    'atur' => $atur,
                    'kunciHalaman' => $kh,
                    'setelHalaman' => $atur['hal'][$kh] ?? [],
                ];
            }
        }

        // Seluruh halaman formulir & profil adalah isi bab DATA KANDIDAT.
        $blok[self::BAB_DATA] = $urut;

        if ($hasil) {
            // Judul bab boleh ditulis ulang admin — "Hasil Seleksi" jadi
            // "Summary", dan seterusnya. Kuncinya memakai kode bab, bukan
            // kunci seksi: yang diganti judul BAB-nya.
            $blok[self::BAB_HASIL] = array_merge([[
                'jenis' => 'PEMISAH',
                'judul' => $atur['bab.HASIL_SELEKSI']['judul'] ?? 'Hasil Seleksi',
                'aksen' => '#a37a2c',
                'kunciHalaman' => 'pemisah.HASIL_SELEKSI',
                'setelHalaman' => $atur['hal']['pemisah.HASIL_SELEKSI'] ?? [],
            ]], $hasil);
        }

        $bLampiran = [];

        if ($lampiran || $ada('indeks-lampiran')) {
            $bLampiran[] = [
                'jenis' => 'PEMISAH',
                'judul' => $atur['bab.LAMPIRAN']['judul'] ?? 'Lampiran Dokumen',
                'aksen' => '#334155',
                'kunciHalaman' => 'pemisah.LAMPIRAN',
                'setelHalaman' => $atur['hal']['pemisah.LAMPIRAN'] ?? [],
            ];
        }

        if ($ada('indeks-lampiran')) {
            $bLampiran[] = [
                'jenis' => 'INDEKS_LAMPIRAN',
                'lampiran' => $lampiran,
                'kunciHalaman' => 'indeks-lampiran',
                'setelHalaman' => $atur['hal']['indeks-lampiran'] ?? [],
            ];
        }

        foreach ($lampiran as $l) {
            $bLampiran[] = ['jenis' => 'SISIP', 'sisip' => $l['bagian'], 'halaman' => $l['halaman'] ?: 1];
        }

        if ($bLampiran) {
            $blok[self::BAB_LAMPIRAN] = $bLampiran;
        }

        if ($ada('penutup')) {
            // Tema warna halaman penutup — 'navy' (bawaan) atau 'emas'.
            // Nilai asing diabaikan diam-diam: blade-nya sudah memilih navy
            // untuk apa pun selain 'emas', jadi tidak ada jalan menghasilkan
            // halaman tanpa warna.
            $blok[self::BAB_PENUTUP][] = [
                'jenis' => 'PENUTUP',
                'tema' => ($atur['penutup']['tema'] ?? null) === 'emas' ? 'emas' : 'navy',
                'kunciHalaman' => 'penutup',
                'setelHalaman' => $atur['hal']['penutup'] ?? [],
            ];
        }

        $akhir = [];

        foreach (self::urutanBab($bab) as $kode) {
            foreach ($blok[$kode] ?? [] as $seksi) {
                $akhir[] = $seksi;
            }
        }

        return self::sambungHalaman($akhir, $atur);
    }

    /**
     * Tandai seksi yang muat menumpang halaman pendahulunya.
     *
     * ── MASALAH YANG DISELESAIKAN ─────────────────────────────────────────
     *
     * Tiap seksi dulu selalu memulai halaman baru, jadi dokumen berisi
     * lembar-lembar yang isinya seperempat kanvas: "Pengalaman & Riwayat" 72%
     * kosong lalu "Seleksi Administrasi" 42% kosong — dua lembar untuk isi
     * yang muat dalam satu.
     *
     * ── YANG TIDAK PERNAH DIGABUNG ────────────────────────────────────────
     *
     * Sampul, Penutup, dan halaman PEMISAH bab. Ketiganya penanda batas: yang
     * dua pertama batas dokumen, yang ketiga batas antar-bab. Menyambungkan
     * bagian lain ke halaman berjudul besar "HASIL SELEKSI" menghapus persis
     * fungsi halaman itu.
     *
     * Sisipan PDF (laporan psikotes, lampiran) juga tidak: keduanya bukan
     * halaman dompdf, jadi tidak ada kanvas yang bisa ditumpangi.
     *
     * ── ADMIN SELALU BISA MEMBATALKAN ─────────────────────────────────────
     *
     * `atur.hal.<kunci>.sendiri = true` memaksa satu halaman berdiri sendiri
     * walau muat digabung. Ada karena "muat" tidak sama dengan "pantas": satu
     * formulir yang secara resmi harus mulai di lembar baru tetap berhak
     * begitu, dan itu penilaian yang tidak bisa dihitung sistem.
     *
     * @param  list<array>  $urutan
     * @return list<array>
     */
    private static function sambungHalaman(array $urutan, array $atur): array
    {
        $tataSebelum = null;
        $indeksSebelum = null;

        foreach ($urutan as $i => $s) {
            $jenis = (string) ($s['jenis'] ?? '');

            // Bukan halaman dompdf, atau halaman yang haram digabung: rantai
            // sambungan diputus di sini — halaman berikutnya mulai dari nol.
            if (! self::bolehDisambung($jenis)) {
                $tataSebelum = null;
                $indeksSebelum = null;

                continue;
            }

            $blok = self::blokUkur($s);
            $sendiri = ! empty(($atur['hal'][$s['kunciHalaman'] ?? ''] ?? [])['sendiri']);

            if (
                $tataSebelum !== null
                && ! $sendiri
                && TataLetakHalaman::muatDisambung($tataSebelum, $blok)
            ) {
                $urutan[$i]['sambung'] = true;

                // Halaman induk kini menanggung dua isi: tinggi keduanya
                // dijumlahkan supaya seksi KETIGA diukur terhadap sisa yang
                // sebenarnya, bukan terhadap halaman yang seolah masih kosong.
                $tataSebelum['tinggiIsi'] += TataLetakHalaman::SELA_SAMBUNG
                    + array_sum(array_map(
                        fn ($b) => TataLetakHalaman::tinggiBlok($b),
                        $blok
                    ));
                $tataSebelum['jumlahBlok'] += count($blok);

                // Halaman induk yang sudah menampung sambungan TIDAK boleh
                // lagi meregangkan selanya — ruang itu sudah terpakai.
                $urutan[$indeksSebelum]['tanpaRegang'] = true;

                // INDUK JUGA HARUS TAHU. Tanpa penanda ini ia menutup `.isi`,
                // mencetak kaki, lalu menutup `.hal` — dan sambungan yang
                // menyusul jatuh DI LUAR kanvas, jadi dompdf menerbitkannya
                // sebagai halaman baru tanpa kop. Persis kegagalan yang
                // terlihat sebagai "halaman 4 tanpa kop".
                //
                // `berlanjut` menahan penutupnya sampai sambungan terakhir
                // selesai; yang menutup adalah sambungan PALING AKHIR.
                $urutan[$indeksSebelum]['berlanjut'] = true;

                continue;
            }

            $tataSebelum = TataLetakHalaman::hitung(
                $blok,
                $atur['hal'][$s['kunciHalaman'] ?? ''] ?? []
            );
            $indeksSebelum = $i;
        }

        // Sambungan TERAKHIR di tiap halaman yang menutup kanvasnya: ia yang
        // mencetak kaki dan </div>. Ditentukan di sini, sesudah seluruh rantai
        // diketahui — saat menandai `sambung` di atas, kita belum tahu apakah
        // masih ada sambungan berikutnya.
        foreach ($urutan as $i => $s) {
            if (empty($s['sambung'])) {
                continue;
            }

            $urutan[$i]['tutupHalaman'] = empty($urutan[$i + 1]['sambung']);
        }

        return $urutan;
    }

    /**
     * Jenis seksi yang boleh berbagi halaman.
     *
     * Daftar POSITIF, bukan daftar larangan: jenis baru yang belum
     * dipertimbangkan otomatis tidak ikut digabung, dan itu arah gagal yang
     * benar — dokumen jadi sedikit lebih panjang, bukan salah cetak.
     */
    private static function bolehDisambung(string $jenis): bool
    {
        // CV TIDAK ikut digabung. potongCv() sudah memotongnya per halaman
        // justru KARENA isinya tidak muat — menyambungnya lagi di sini
        // membatalkan pemotongan itu, dan isi halaman kedua & ketiga tercetak
        // di koordinat negatif, hilang dari cetakan.
        return in_array($jenis, ['DATA_KANDIDAT', 'PENGALAMAN', 'FORMULIR'], true);
    }

    /** Blok isi satu seksi, untuk menaksir tingginya. */
    private static function blokUkur(array $s): array
    {
        if ($s['jenis'] === 'CV') {
            // Satu bagian jadi satu blok ukur. Lebarnya menentukan tingginya:
            // kolom sempit mencetak satu isian per baris, kolom lebar dua.
            $blok = function (array $daftar, int $lebar) {
                return collect($daftar)
                    ->map(fn ($b) => ! empty($b['baris'])
                        ? [
                            'jenis' => 'timeline',
                            'jumlah' => max(1, collect($b['baris'])
                                ->sum(fn ($r) => count($r['isian']['baris'] ?? []))),
                        ]
                        : [
                            'jenis' => 'baris',
                            'jumlah' => max(1, (int) ceil(
                                count($b['isian'] ?? []) / ($lebar < 300 ? 1 : 2)
                            )) + 1,
                        ])
                    ->all();
            };

            if (($s['mode'] ?? 'penuh') !== 'dua-kolom') {
                return $blok($s['bagian'] ?? [], self::LEBAR_PENUH);
            }

            // ── KOLOM TERTINGGI, DIUKUR — BUKAN YANG BAGIANNYA TERBANYAK ──
            //
            // Keduanya berdiri berdampingan, jadi tinggi halaman ditentukan
            // yang lebih tinggi. Dulu yang dipilih kolom dengan bagian
            // TERBANYAK: empat bagian pendek di kolom kiri terbaca "lebih
            // tinggi" daripada dua riwayat panjang di kolom utama, dan tinggi
            // halaman ditaksir dari kolom yang keliru.
            $tKiri = collect($s['kiri'] ?? [])
                ->sum(fn ($b) => self::taksirBagianCv($b, self::LEBAR_KIRI));
            $tUtama = collect($s['utama'] ?? [])
                ->sum(fn ($b) => self::taksirBagianCv($b, self::LEBAR_UTAMA));

            $hasil = $tUtama >= $tKiri
                ? $blok($s['utama'] ?? [], self::LEBAR_UTAMA)
                : $blok($s['kiri'] ?? [], self::LEBAR_KIRI);

            // Ekor lajur penuh berdiri DI BAWAH kedua kolom — tingginya
            // menambah, bukan bersaing.
            return array_merge($hasil, $blok($s['ekor'] ?? [], self::LEBAR_PENUH));
        }

        if ($s['jenis'] === 'DATA_KANDIDAT') {
            // Halaman profil hampir selalu penuh — ditaksir setinggi kanvas
            // supaya tidak pernah dianggap muat menampung sambungan.
            return [['jenis' => 'lain', 'jumlah' => 24]];
        }

        if ($s['jenis'] === 'PENGALAMAN') {
            return [['jenis' => 'judul', 'jumlah' => 1], ['jenis' => 'timeline', 'jumlah' => 3]];
        }

        $blok = collect($s['bagian'] ?? [])
            ->map(fn ($b) => [
                'jenis' => 'baris',
                'jumlah' => self::tinggiBagian($b),
            ])
            ->all();

        if (empty($s['lanjutan'])) {
            array_unshift($blok, ['jenis' => 'judul', 'jumlah' => 1]);
        }

        return $blok;
    }

    /**
     * Perkiraan tinggi satu bagian formulir, dalam satuan "baris".
     *
     * ── KENAPA TIDAK SEKADAR jumlah/2 ────────────────────────────────────
     *
     * Versi sebelumnya menaksir `ceil(jumlah isian / 2) + 1`, mengandaikan
     * seluruh isian tercetak sebagai kisi dua kolom. Kenyataannya
     * formulir.blade.php memecahnya jadi TIGA bentuk yang tingginya berbeda:
     *
     *   • isian pendek  → kisi dua kolom (dua per baris)
     *   • jawaban ya/tidak → tabel berbingkai, SATU per baris
     *   • uraian panjang   → kotak berbatang kiri, satu per baris + jarak
     *
     * Bagian berisi enam pertanyaan ya/tidak karena itu ditaksir 4 baris
     * padahal nyatanya 7. Pada halaman tunggal selisih itu tidak terlihat —
     * isinya toh mengalir ke bawah. Ia baru berakibat saat dua seksi digabung
     * dalam satu kanvas: gabungan yang "muat" di atas kertas ternyata meluber
     * sampai isinya terpotong di luar halaman.
     */
    private static function tinggiBagian(array $b): int
    {
        $biner = ['ya', 'y', 'true', '1', 'sudah', 'bersedia', 'setuju',
            'tidak', 't', 'false', '0', 'belum'];

        $pendek = 0;
        $penuh = 0;

        foreach ($b['isian'] ?? [] as $i) {
            // Bagian berulang punya bloknya sendiri — ditaksir terpisah.
            if (! empty($i['baris'])) {
                $penuh += 3;

                continue;
            }

            $v = trim((string) ($i['nilai'] ?? ''));

            if ($v === '' && empty($i['berkas'])) {
                continue;
            }

            if (in_array(mb_strtolower($v), $biner, true) || mb_strlen($v) > 60) {
                $penuh++;      // satu baris penuh
            } else {
                $pendek++;     // dua per baris
            }
        }

        // +1 untuk penanda judul bagiannya.
        return max(1, (int) ceil($pendek / 2) + $penuh) + 1;
    }

    /**
     * Urutan bab yang dipakai merakit dokumen.
     *
     * Yang dikirim layar DISARING, bukan dipercaya: kode asing dibuang, kode
     * kembar diambil sekali, dan bab yang tidak disebut tetap ikut di
     * belakang — hilangnya satu bab dari daftar tidak boleh berarti isinya
     * lenyap diam-diam dari berkas resmi.
     *
     * Sampul & Penutup dikembalikan ke ujungnya. Layar memang sudah menguncinya
     * (lihat BAB_TETAP di ExportStudio.vue), tapi permintaan bisa datang dari
     * mana saja — dan sampul yang tercetak di tengah adalah cacat yang tidak
     * bisa diperbaiki sesudah berkasnya dikirim ke direksi.
     *
     * @param  list<string>  $bab
     * @return list<string>
     */
    private static function urutanBab(array $bab): array
    {
        $sah = array_values(array_unique(array_filter(
            $bab,
            fn ($k) => in_array($k, self::BAB_BAWAAN, true),
        )));

        // Bab yang tidak disebut layar menyusul di belakang, urut bawaan.
        foreach (self::BAB_BAWAAN as $kode) {
            if (! in_array($kode, $sah, true)) {
                $sah[] = $kode;
            }
        }

        $tengah = array_values(array_filter(
            $sah,
            fn ($k) => $k !== self::BAB_SAMPUL && $k !== self::BAB_PENUTUP,
        ));

        return array_merge([self::BAB_SAMPUL], $tengah, [self::BAB_PENUTUP]);
    }

    /**
     * Potong isian satu formulir menjadi beberapa halaman.
     *
     * Kanvas rancangan bertinggi tetap sementara jumlah pertanyaan tidak bisa
     * ditebak, jadi tidak ada aliran antar-halaman yang bisa diandalkan.
     * Panjang tiap bagian ditaksir dari jumlah dan jenis isiannya, lalu
     * dipotong sebelum melewati tinggi bidang isi.
     *
     * Taksiran ini sengaja longgar: satu halaman yang setengah kosong jauh
     * lebih baik daripada isian yang terpotong di tepi kanvas dan hilang.
     */
    private static function potongFormulir(array $form, array $pilih, array $urutKunci = []): array
    {
        $dipilih = self::bagianDipilih($form, $pilih, $urutKunci);
        $halaman = [];
        $kini = [];
        $tinggi = 0;

        // Bidang isi setinggi 947px; sisakan ruang untuk judul halaman pertama.
        $batas = 860;

        foreach ($dipilih as $b) {
            $isi = collect($b['isian'] ?? [])->reject(fn ($f) => ! empty($f['berkas']));

            if ($isi->isEmpty()) {
                continue;
            }

            $taksir = 34; // penanda bagian

            foreach ($isi as $f) {
                if (! empty($f['baris'])) {
                    // Kartu bernomor: satu kartu ± 60px + 22px per pasang isian.
                    foreach ($f['baris'] as $baris) {
                        $taksir += 46 + (ceil(count($baris) / 2) * 30);
                    }

                    $taksir += 18;

                    continue;
                }

                $panjang = mb_strlen(trim((string) $f['nilai']));
                $taksir += $panjang > 60 ? (60 + (int) ($panjang / 3)) : 28;
            }

            // Bagian yang sendirian saja melebihi satu halaman tetap diberi
            // halamannya — memotongnya di tengah isian akan menghilangkan data.
            if ($kini && ($tinggi + $taksir) > $batas) {
                $halaman[] = $kini;
                $kini = [];
                $tinggi = 0;
            }

            $kini[] = $b;
            $tinggi += $taksir;
        }

        if ($kini) {
            $halaman[] = $kini;
        }

        return $halaman ?: [[]];
    }

    /**
     * Bagian formulir yang dicentang admin — kosong berarti seluruhnya.
     *
     * Urutannya mengikuti $urutKunci, yaitu susunan yang digeser admin. Kunci
     * bagian yang dikirim layar juga ikut membawa gaya tampilannya, jadi tiap
     * bagian dipulangkan lengkap dengan `kunci`-nya supaya blade bisa mencari
     * penyesuaian yang berlaku untuknya.
     */
    private static function bagianDipilih(array $form, array $pilih, array $urutKunci = []): array
    {
        $awalan = self::kunciFormulir($form) . '.';

        // Kunci yang dikirim layar dipisah dua tingkat: bagian ('<md5judul>')
        // dan field ('<md5judul>.<md5key>'). Titik pada kunci field itulah
        // pembedanya.
        $bagianTerpilih = [];
        $fieldTerpilih = [];

        foreach (array_keys($pilih) as $k) {
            if (! str_starts_with($k, $awalan)) {
                continue;
            }

            $sisa = substr($k, strlen($awalan));

            if (str_contains($sisa, '.')) {
                $fieldTerpilih[$sisa] = true;
            } else {
                $bagianTerpilih[$sisa] = true;
            }
        }

        // Kunci bagian sudah ditempelkan LaporanKandidat::bagi() — sama persis
        // dengan yang dipakai layar. DIPAKAI APA ADANYA, tidak dihitung ulang:
        // kuncinya memuat nomor urut bagian, dan menghitungnya ulang dari
        // judul saja akan menyamakan empat bagian yang sama-sama berjudul
        // "Bagian 1".
        $bagian = $form['bagian'] ?? [];

        if ($bagianTerpilih) {
            $bagian = array_values(array_filter(
                $bagian,
                fn ($b) => isset($bagianTerpilih[substr((string) $b['kunci'], strlen($awalan))])
            ));
        }

        // ── SARING FIELD YANG TIDAK DICENTANG ─────────────────────────────
        //
        // Hanya bila ADA kunci field yang dikirim untuk formulir ini. Muatan
        // dari layar versi lama tidak memuatnya sama sekali, dan menyaring
        // dengan daftar kosong akan mengosongkan seluruh formulir.
        //
        // Bagian BERULANG dilewati: isinya satu daftar riwayat yang tidak
        // dipecah per field di layar, jadi tidak ada kunci field untuknya.
        if ($fieldTerpilih) {
            $bagian = array_map(function ($b) use ($fieldTerpilih, $awalan) {
                if (! empty($b['berulang'])) {
                    return $b;
                }

                // Bagian dari kunci bagiannya sendiri, tanpa awalan formulir —
                // itulah bentuk yang dipakai kunci field di layar.
                $sisiBagian = substr((string) $b['kunci'], strlen($awalan));

                $b['isian'] = array_values(array_filter(
                    $b['isian'] ?? [],
                    fn ($i) => isset($fieldTerpilih[$sisiBagian . '.' . md5((string) ($i['key'] ?? ''))])
                ));

                return $b;
            }, $bagian);

            // Bagian yang seluruh fieldnya dimatikan tidak dicetak sebagai
            // judul kosong.
            $bagian = array_values(array_filter(
                $bagian,
                fn ($b) => ! empty($b['berulang']) || ! empty($b['isian'])
            ));
        }

        usort($bagian, fn ($a, $b) => ($urutKunci[$a['kunci']] ?? 9999) <=> ($urutKunci[$b['kunci']] ?? 9999));

        return $bagian;
    }

    /** Kunci layar untuk satu formulir — sama dengan yang dipakai BerkasSeleksi. */
    private static function kunciFormulir(array $form): string
    {
        return 'formulir.' . Hashids::encode($form['pengisianId'] ?? 0);
    }

    /**
     * Buang formulir & isian yang tidak dicentang, sebelum CV disusun.
     *
     * Penyaringan dilakukan pada DATA, bukan pada hasil susunan: sekali satu
     * isian ikut melebur ke bagian CV, asal-usulnya tidak lagi bisa dilacak
     * ke kunci centangnya. Menyaring lebih dulu membuat setiap centang di
     * panel kiri tetap berarti persis apa yang dijanjikannya.
     */
    private static function saringUntukCv(array $d, array $pilih): array
    {
        $formulir = [];

        foreach ($d['formulir'] ?? [] as $f) {
            if (! self::formulirDipilih($f, $pilih)) {
                continue;
            }

            $f['bagian'] = self::bagianDipilih($f, $pilih);
            $formulir[] = $f;
        }

        $d['formulir'] = $formulir;

        return $d;
    }

    /**
     * Potong bagian CV menjadi halaman-halaman.
     *
     * Taksirannya konservatif dengan sengaja: kalau meleset, ia meleset ke
     * arah "satu halaman lebih banyak" — yang hanya menambah kertas. Meleset
     * ke arah sebaliknya berarti isi terpotong di luar kanvas dan hilang dari
     * cetakan, dan itu tidak boleh terjadi pada dokumen resmi.
     *
     * @param  list<array>  $bagian
     * @return list<list<array>>
     */
    private static function potongCv(array $bagian, array $atur = []): array
    {
        // ── DUA KOLOM DULU, BARU LAJUR PENUH ──────────────────────────────
        //
        // Halaman pertama CV bersusun dua kolom: kiri untuk fakta ringkas
        // yang disapu cepat (pendidikan, kemampuan), utama untuk riwayat yang
        // benar-benar dinilai (pengalaman, organisasi). Lihat SusunCv::BAGIAN.
        //
        // Deret pernyataan dan daftar dokumen dipisahkan ke halaman lajur
        // penuh: satu pertanyaan satu baris, dibaca dengan menyapu kolom
        // jawaban di kanan, dan itu mustahil di kolom 238px.
        $kiri = [];
        $utama = [];
        $penuh = [];

        foreach ($bagian as $b) {
            // Pilihan admin menang atas rekomendasi sistem. Bawaannya sudah
            // disesuaikan per peran (SusunCv::BAGIAN), tapi posisi tertentu
            // menuntut penekanan berbeda — keahlian teknis layak naik ke kolom
            // utama, riwayat organisasi bisa turun ke kolom kiri.
            $pilihanAdmin = ($atur[$b['kunci']] ?? [])['lajur'] ?? null;
            $lajur = in_array($pilihanAdmin, ['kiri', 'utama', 'penuh'], true)
                ? $pilihanAdmin
                : ($b['lajur'] ?? 'penuh');

            match ($lajur) {
                'kiri' => $kiri[] = $b,
                'utama' => $utama[] = $b,
                default => $penuh[] = $b,
            };
        }

        $urutkan = fn (array $x) => collect($x)->sortBy('urutan')->values()->all();

        $halaman = [];

        // ── HALAMAN DUA KOLOM DIPOTONG PER KOLOM ──────────────────────────
        //
        // Kedua kolom berdiri berdampingan, jadi tinggi halamannya ditentukan
        // kolom TERTINGGI. Bagian yang tidak lagi muat berpindah ke halaman
        // dua kolom berikutnya — bukan dibiarkan meluber.
        //
        // Ini pernah luput: potongCv() dulu selalu menaruh SELURUH bagian
        // kiri & utama di satu halaman tanpa memeriksa tingginya sama sekali.
        // Pada Formulir Rekrutmen — 25 isian di bagian Profil Singkat, dan di
        // kolom 206px tiap isian memakan satu baris penuh — 53 potongan teks
        // tercetak di luar kanvas dan hilang dari cetakan.
        $kiri = $urutkan($kiri);
        $utama = $urutkan($utama);
        $batasKolom = 866; // bidang isi 963 dikurangi ruang judul halaman

        while ($kiri || $utama) {
            $isiKiri = [];
            $isiUtama = [];
            $tKiri = 0;
            $tUtama = 0;

            // Kolom diisi selama masih muat. Bagian yang SENDIRIAN melebihi
            // tinggi halaman dipecah isinya — lihat pecahBagian().
            while ($kiri) {
                $t = self::taksirBagianCv($kiri[0], self::LEBAR_KIRI);

                if ($isiKiri && ($tKiri + $t) > $batasKolom) {
                    break;
                }

                if ($t > $batasKolom - $tKiri) {
                    [$muat, $sisa] = self::pecahBagian(
                        $kiri[0],
                        self::LEBAR_KIRI,
                        $batasKolom - $tKiri,
                    );

                    // Tidak bisa dipecah — satu isian tunggal yang sendirian
                    // lebih tinggi dari halaman, atau riwayat berulang. Ambil
                    // utuh dan biarkan meluber sedikit.
                    //
                    // WAJIB tetap mengambil: kalau di sini hanya `break`,
                    // bagian itu tidak pernah keluar dari antrean dan
                    // `while ($kiri || $utama)` berputar selamanya —
                    // pekerja antrean menggantung sampai batas waktunya.
                    if ($muat === null) {
                        $isiKiri[] = array_shift($kiri);
                        $tKiri += $t;

                        break;
                    }

                    array_shift($kiri);

                    if ($sisa !== null) {
                        array_unshift($kiri, $sisa);
                    }

                    $isiKiri[] = $muat;

                    break;
                }

                $isiKiri[] = array_shift($kiri);
                $tKiri += $t;
            }

            while ($utama) {
                $t = self::taksirBagianCv($utama[0], self::LEBAR_UTAMA);

                if ($isiUtama && ($tUtama + $t) > $batasKolom) {
                    break;
                }

                if ($t > $batasKolom - $tUtama) {
                    [$muat, $sisa] = self::pecahBagian(
                        $utama[0],
                        self::LEBAR_UTAMA,
                        $batasKolom - $tUtama,
                    );

                    // Tidak bisa dipecah — satu isian tunggal yang sendirian
                    // lebih tinggi dari halaman, atau riwayat berulang. Ambil
                    // utuh dan biarkan meluber sedikit.
                    //
                    // WAJIB tetap mengambil: kalau di sini hanya `break`,
                    // bagian itu tidak pernah keluar dari antrean dan
                    // `while ($kiri || $utama)` berputar selamanya —
                    // pekerja antrean menggantung sampai batas waktunya.
                    if ($muat === null) {
                        $isiUtama[] = array_shift($utama);
                        $tUtama += $t;

                        break;
                    }

                    array_shift($utama);

                    if ($sisa !== null) {
                        array_unshift($utama, $sisa);
                    }

                    $isiUtama[] = $muat;

                    break;
                }

                $isiUtama[] = array_shift($utama);
                $tUtama += $t;
            }

            $halaman[] = [
                'mode' => 'dua-kolom',
                'kiri' => $isiKiri,
                'utama' => $isiUtama,
                'ekor' => [],
                // Tinggi kolom tertinggi — dipakai mengisi kaki halaman di
                // bawah ini.
                'tinggi' => max($tKiri, $tUtama),
            ];
        }

        $penuh = $urutkan($penuh);

        // ── KAKI HALAMAN DUA KOLOM DIISI DULU ─────────────────────────────
        //
        // Halaman dua kolom terakhir sering berhenti di tengah kertas: kedua
        // kolomnya habis, tapi kanvasnya masih setinggi 963px. Sebelumnya
        // bagian lajur penuh selalu dimulai di halaman BARU, jadi hasilnya
        // dua halaman setengah kosong berturut-turut — persis yang terlihat
        // sebagai "space kosong banyak" pada halaman Profil Kandidat.
        //
        // Meregangkan sela tidak menyelesaikannya: TataLetakHalaman sengaja
        // membatasi sela di 72px, karena sela yang lebih besar terbaca sebagai
        // bagian yang hilang di tengah, bukan sebagai jarak.
        //
        // Yang benar adalah mengisinya. Bagian lajur penuh yang masih muat di
        // kaki halaman dua kolom terakhir dipindahkan ke sana.
        if ($halaman && $penuh) {
            $akhir = array_key_last($halaman);
            $sisaRuang = $batasKolom - $halaman[$akhir]['tinggi'];

            // Ambang: di bawah ini yang tersisa hanya cukup untuk penanda
            // bagian tanpa isi yang berarti, dan bagian yang terbelah tepat
            // di bawah judulnya lebih buruk daripada halaman yang lapang.
            while ($penuh && $sisaRuang > 160) {
                $t = self::taksirBagianCv($penuh[0], self::LEBAR_PENUH);

                if ($t > $sisaRuang) {
                    break;
                }

                $halaman[$akhir]['ekor'][] = array_shift($penuh);
                $sisaRuang -= $t;
            }
        }

        // Halaman lajur penuh — dipotong seperti biasa.
        $kini = [];
        $tinggi = 0;
        $batas = 866;  // bidang isi 963 dikurangi ruang judul halaman

        foreach ($penuh as $b) {
            $taksir = self::taksirBagianCv($b);

            if ($kini && ($tinggi + $taksir) > $batas) {
                $halaman[] = ['mode' => 'penuh', 'bagian' => $kini];
                $kini = [];
                $tinggi = 0;
            }

            $kini[] = $b;
            $tinggi += $taksir;
        }

        if ($kini) {
            $halaman[] = ['mode' => 'penuh', 'bagian' => $kini];
        }

        return $halaman ?: [['mode' => 'penuh', 'bagian' => []]];
    }

    /**
     * Pecah satu bagian CV yang terlalu tinggi untuk satu kolom.
     *
     * ── KENAPA PERLU ──────────────────────────────────────────────────────
     *
     * Pada Formulir Rekrutmen, bagian "Profil Singkat" memuat 25 isian. Di
     * kolom 206px tiap isian memakan satu baris penuh dan banyak labelnya
     * patah dua baris — totalnya ~1100px, melebihi tinggi kolom mana pun.
     * Diambil utuh, 16 potongan teks tercetak di luar kanvas dan hilang dari
     * cetakan tanpa jejak.
     *
     * Yang dipecah HANYA isian datar. Riwayat berulang dibiarkan utuh:
     * memotong lini masa di tengah entri menghasilkan dua potongan yang tidak
     * satu pun mengaku sebagai sambungan.
     *
     * @return array{0: ?array, 1: ?array}  [yang muat, sisanya]
     */
    private static function pecahBagian(array $b, int $lebar, int $ruang): array
    {
        // Riwayat berulang & bagian kecil tidak dipecah.
        if ($b['baris'] || count($b['isian']) < 2 || $ruang < 120) {
            return [null, null];
        }

        $muat = [];
        $sisa = [];
        $tinggi = 34; // penanda bagian

        foreach ($b['isian'] as $i) {
            if ($sisa) {
                $sisa[] = $i;

                continue;
            }

            $t = self::taksirBagianCv(
                ['baris' => [], 'isian' => [$i]],
                $lebar,
            ) - 34; // tanpa penanda bagian

            if ($muat && ($tinggi + $t) > $ruang) {
                $sisa[] = $i;

                continue;
            }

            $muat[] = $i;
            $tinggi += $t;
        }

        if (! $sisa) {
            return [null, null];
        }

        return [
            array_merge($b, ['isian' => $muat]),
            array_merge($b, [
                'isian' => $sisa,
                // Sambungannya TIDAK mengulang judul: dua penanda bagian
                // berjudul sama berturut-turut terbaca sebagai dua bagian
                // berbeda yang kebetulan senama.
                'judul' => '',
                // Kuncinya juga diganti. Kalau tidak, penyesuaian judul dari
                // Export Studio — yang dicari berdasarkan kunci — ikut
                // terpasang di sambungan, dan judul yang baru saja dikosongkan
                // muncul lagi.
                'kunci' => $b['kunci'].'~sambung',
            ]),
        ];
    }

    /**
     * Taksiran tinggi satu bagian CV (px).
     *
     * Konservatif dengan sengaja: kalau meleset, ia meleset ke arah "satu
     * halaman lebih banyak" — hanya menambah kertas. Meleset ke arah
     * sebaliknya berarti isi terpotong di luar kanvas dan hilang dari
     * cetakan, dan itu tidak boleh terjadi pada dokumen resmi.
     */
    public static function taksirBagianCv(array $b, int $lebar = self::LEBAR_PENUH): int
    {
        $taksir = 34; // penanda bagian

        // Kolom sempit mencetak SATU isian per baris, bukan dua — lihat
        // `$sempit` di partial-cv-bagian.blade.php. Menaksirnya seolah dua
        // per baris membuat kolom kiri dikira separuh tingginya, dan isinya
        // meluber keluar kanvas.
        $sempit = $lebar < 300;

        if ($b['baris']) {
            foreach ($b['baris'] as $r) {
                foreach ($r['isian']['baris'] ?? [] as $baris) {
                    $perBaris = $sempit ? 1 : 2;
                    $taksir += 46 + ((int) ceil(count($baris) / $perBaris) * 30);
                }

                $taksir += 18;
            }

            return $taksir;
        }

        // Teks yang sama memakan lebih banyak baris di kolom sempit: ambang
        // "panjang" turun sebanding lebarnya.
        $ambang = $sempit ? 30 : 60;

        // Berapa karakter yang muat dalam satu baris pada lebar ini. Dipakai
        // menghitung berapa baris yang dimakan LABEL — bukan hanya nilainya.
        $perBaris = max(12, (int) ($lebar / 5.2));

        // ── HANYA ISIAN RINGKAS YANG BERBAGI BARIS ────────────────────────
        //
        // partial-cv-bagian memilah isian jadi tiga: jawaban ya/tidak
        // ($binerIsi) dan teks panjang ($panjang) masing-masing memakai SATU
        // BARIS PENUH berapa pun lebar kolomnya; hanya isian ringkas
        // ($ringkas) yang dijejerkan dua-dua di kolom lebar.
        //
        // Membagi dua tinggi SEMUA isian karena itu meleset jauh pada bagian
        // yang isinya deret pernyataan: "Kesiapan & Pernyataan" ditaksir
        // 129px padahal nyatanya 162px, dan "Kelengkapan Dokumen" 148px
        // padahal 202px. Selisih itu cukup untuk menarik satu bagian
        // tambahan ke kaki halaman yang sudah penuh — sebelas potongan teks
        // tercetak di luar kanvas dan hilang dari cetakan.
        $biner = static function (string $v): bool {
            $t = mb_strtolower(trim($v));

            return in_array($t, ['ya', 'y', 'true', '1', 'sudah', 'bersedia', 'setuju',
                'tidak', 't', 'false', '0', 'belum'], true);
        };

        foreach ($b['isian'] as $f) {
            $nilai = trim((string) ($f['nilai'] ?? ''));
            $label = trim((string) ($f['label'] ?? ''));

            // LABEL IKUT DIHITUNG, bukan cuma nilainya.
            //
            // Di kolom 206px, "Darimana Anda Tahu Lowongan Kami?" patah jadi
            // dua baris — dan bagian berisi 25 isian seperti itu ditaksir
            // 884px padahal nyatanya 1054px. Selisih 170px itu cukup untuk
            // membuat isinya tercetak di luar kanvas dan hilang.
            $barisLabel = max(1, (int) ceil(mb_strlen($label) / $perBaris));
            $barisNilai = max(1, (int) ceil(mb_strlen($nilai) / $perBaris));

            // Label kecil (7px) + nilai (11px) + jarak antar-isian.
            $tinggi = ($barisLabel * 12) + ($barisNilai * 16) + 10;

            // Sebaris penuh: jawaban ya/tidak, berkas, dan teks panjang.
            $sebarisPenuh = $sempit
                || ! empty($f['berkas'])
                || $biner($nilai)
                || mb_strlen($nilai) > $ambang;

            $taksir += $sebarisPenuh ? $tinggi : (int) ceil($tinggi / 2);
        }

        return $taksir;
    }

    /**
     * Kunci penyimpanan tata letak satu halaman.
     *
     * Dibangun dari IDENTITAS ISINYA — formulir mana, potongan ke berapa —
     * bukan dari nomor halaman di dokumen jadi. Nomor halaman bergeser setiap
     * kali admin mencentang satu lampiran atau menggeser satu bab, dan setelan
     * yang ditambatkan padanya akan pindah ke halaman lain tanpa disentuh
     * siapa pun: admin merapikan halaman 4, mencentang satu berkas, lalu
     * mendapati halaman 4 yang sekarang berisi hal berbeda ikut merenggang.
     *
     * Potongan ke berapa TETAP ikut: satu formulir panjang jadi tiga halaman
     * yang isinya berbeda-beda, dan masing-masing berhak punya setelannya
     * sendiri. Kalau kandidat kemudian menambah jawaban sehingga potongannya
     * bergeser, setelan potongan terakhir memang bisa tidak lagi pas — tapi
     * itu jauh lebih jarang, dan tetap lebih baik daripada satu setelan yang
     * dipaksakan ke seluruh halaman formulir.
     */
    private static function kunciHalaman(string $kunciForm, int $potongan): string
    {
        return $kunciForm . '#' . $potongan;
    }

    /**
     * Apakah ada isian berulang yang layak diceritakan di halaman riwayat.
     *
     * Halaman "Pengalaman, Organisasi & Sertifikasi" tidak dicetak bila
     * kandidat tidak mengisi satu pun riwayat — halaman berjudul besar dengan
     * satu kalimat "tidak mencantumkan" hanya menambah tebal berkas.
     */
    private static function punyaRiwayat(array $d, array $pilih): bool
    {
        foreach ($d['formulir'] ?? [] as $form) {
            if (! self::formulirDipilih($form, $pilih)) {
                continue;
            }

            foreach ($form['bagian'] ?? [] as $b) {
                foreach ($b['isian'] ?? [] as $i) {
                    if (! empty($i['baris'])) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    // ══ HASIL SELEKSI ══════════════════════════════════════════════════════

    /**
     * Blok hasil seleksi: laporan psikotes & berkas penilaian, urut sesuai alur.
     *
     * Keduanya berupa SISIPAN — PDF yang digabung apa adanya. Ringkasan
     * bergaya rancangan hanya ikut bila kunci `.ringkasan` aktivitas itu
     * dicentang; bawaannya tidak.
     */
    private static function blokHasil(int $lamaranId, array $pilih, ?int $penggunaId, bool $ringan = false): array
    {
        $terpilih = [];

        foreach (array_keys($pilih) as $k) {
            if (str_starts_with($k, 'hasil.') && ! str_contains($k, '.ringkasan')) {
                $terpilih[] = (int) substr($k, 6);
            }
        }

        if (! $terpilih) {
            return [];
        }

        $aktivitas = DB::table('N_WEB_CAREERS_Lamaran_Tahap_Tes as s')
            ->join('N_WEB_CAREERS_Lamaran_Tahap as t', 't.Id_Lamaran_Tahap', '=', 's.Lamaran_Tahap_Id')
            ->whereIn('s.Id_Lamaran_Tahap_Tes', $terpilih)
            ->where('t.Lamaran_Id', $lamaranId)
            ->orderBy('t.Urutan')->orderBy('s.Urutan')
            ->select('s.*', 't.Label as TahapLabel')
            ->get();

        $berkas = DB::table('N_WEB_CAREERS_Lamaran_Tahap_Berkas')
            ->whereIn('Lamaran_Tahap_Tes_Id', $terpilih)
            ->orderBy('Id_Lamaran_Tahap_Berkas')
            ->get()
            ->groupBy('Lamaran_Tahap_Tes_Id');

        $cat = self::laporanCat($lamaranId, $aktivitas, $penggunaId, $ringan);
        $blok = [];

        foreach ($aktivitas as $a) {
            $id = (int) $a->Id_Lamaran_Tahap_Tes;

            // Ringkasan bergaya rancangan — hanya bila diminta.
            if (isset($pilih['hasil.' . $id . '.ringkasan'])) {
                $blok[] = [
                    'jenis' => 'HASIL_MANUAL',
                    'aktivitas' => $a,
                    'induk' => $a->TahapLabel,
                    'berkas' => $berkas->get($id, collect())->all(),
                    'aksen' => self::aksenTipe((string) $a->Tipe_Tahap_Kode),
                ];
            }

            // Laporan psikotes dari CAT — digabung apa adanya.
            foreach ($cat[$id] ?? [] as $lap) {
                $blok[] = $lap;
            }

            // ── BERKAS PENILAIAN SELALU DIGABUNG SUNGGUHAN ────────────
            //
            // Berbeda dari lampiran formulir, berkas FGD/wawancara TIDAK
            // pernah diganti halaman penanda — bahkan di pratinjau. Alasannya
            // dua:
            //
            // 1. Ia BUKAN lampiran, melainkan isi bab Hasil Seleksi. Lembar
            //    penilaian asesor adalah satu-satunya bukti hasil tahap itu;
            //    pratinjau yang menggantinya dengan kotak "Ditampilkan utuh
            //    pada berkas yang diunduh" memperlihatkan bab hasil seleksi
            //    yang kosong — dan justru bagian itulah yang paling perlu
            //    diperiksa admin sebelum mencetak.
            //
            // 2. Ongkosnya kecil. Diukur di produksi: 2 berkas, ~1 detik
            //    total. Yang berat adalah lampiran formulir (9 berkas, 3 MB),
            //    dan itu tetap diganti penanda.
            //
            // `false` yang dipatok di sini adalah inti perbaikannya; jangan
            // dikembalikan jadi $ringan.
            foreach ($berkas->get($id, collect()) as $b) {
                $s = self::sisipanBerkas([
                    'label' => 'Berkas ' . str_replace('_', ' ', (string) $b->Jenis),
                    'nama' => $b->Nama_File,
                    'path' => $b->Path_File,
                    'ext' => strtolower((string) $b->Ext),
                ], false);

                $blok[] = ['jenis' => 'SISIP', 'sisip' => $s['sisip'], 'halaman' => $s['halaman']];
            }
        }

        return $blok;
    }

    /**
     * Laporan psikotes tiap aktivitas, sudah berupa sisipan PDF.
     *
     * Satu aktivitas bisa memulangkan BEBERAPA laporan: ujian gabungan
     * menghasilkan TIU dan Kraeplin sekaligus, masing-masing dengan kode tipe
     * hasilnya sendiri — dan kode itulah yang memilih bladenya, bukan nama
     * tahap. Lihat PetaTemplateTes.
     */
    private static function laporanCat(int $lamaranId, $aktivitas, ?int $penggunaId, bool $ringan = false): array
    {
        $berjadwal = $aktivitas->filter(fn ($a) => ! empty($a->Penjadwalan_Tahap_Id));

        if ($berjadwal->isEmpty()) {
            return [];
        }

        $peserta = DB::table('N_WEB_CAREERS_Penjadwalan_Peserta')
            ->where('Lamaran_Id', $lamaranId)
            ->whereIn('Penjadwalan_Tahap_Id', $berjadwal->pluck('Penjadwalan_Tahap_Id')->all())
            ->orderByDesc('Id_Penjadwalan_Peserta')
            ->get(['Id_Penjadwalan_Peserta', 'Penjadwalan_Tahap_Id'])
            // Dijadwalkan ulang → beberapa baris untuk satu tahap. Yang
            // TERBARU yang dipakai; percobaan lama sudah digantikan.
            ->unique('Penjadwalan_Tahap_Id')
            ->keyBy('Penjadwalan_Tahap_Id');

        $peta = [];
        $singgah = [];

        foreach ($berjadwal as $a) {
            $p = $peserta->get($a->Penjadwalan_Tahap_Id);

            if (! $p) {
                continue;
            }

            $idPeserta = (int) $p->Id_Penjadwalan_Peserta;
            $idTahap = (int) $a->Penjadwalan_Tahap_Id;

            // Dua aktivitas bisa menunjuk peserta yang sama (paket gabungan);
            // CAT cukup ditanya sekali.
            //
            // Kunci singgahannya memuat TAHAP juga, bukan peserta saja: nomor
            // tahap ikut dikirim sebagai petunjuk cadangan, jadi dua tahap
            // berbeda pada peserta yang sama adalah dua pertanyaan berbeda.
            // Menyinggahkannya pada nomor peserta saja akan memulangkan
            // laporan tahap pertama untuk seluruh tahap berikutnya.
            $kunciSinggah = $idPeserta . ':' . $idTahap;

            // ── PRATINJAU TIDAK MENANYAI CAT SAMA SEKALI ──────────────────
            //
            // Penjagaan ini WAJIB di atas panggilan HTTP, bukan di bawahnya.
            // Sempat ditaruh setelah balasannya diterima: laporannya memang
            // tidak jadi dirender, tapi permintaan HTTP-nya sudah terlanjur
            // jalan — dan justru permintaan itulah yang memakan 17 detik.
            //
            // Yang dilewati hanya PENGAMBILANNYA; halaman penandanya tetap
            // dibuat di bawah, jadi admin tetap melihat laporan itu ada dan
            // di urutan mana.
            if ($ringan) {
                // ── SATU PENANDA PER LAPORAN, BUKAN PER PESERTA ───────────
                //
                // Satu peserta bisa memulangkan BEBERAPA laporan: pada lamaran
                // 594 satu tahap memulangkan TIU, DISC, dan PAPI Kostick.
                // Membuat satu penanda per peserta menghasilkan dua penanda
                // untuk tiga laporan, tak satu pun menyebut tes apa — bab
                // Hasil Seleksi terlihat kosong di pratinjau.
                //
                // Daftarnya dicatat saat unduhan sungguhan; belum pernah
                // dicetak → satu penanda umum tanpa mengarang nama tes.
                $daftar = SinggahLaporanTes::laporanTersimpan($idPeserta, $idTahap);

                if (! $daftar) {
                    // Belum pernah dicetak: nama tesnya hanya diketahui CAT,
                    // dan menanyakannya adalah hal yang justru dihindari di
                    // sini. Penandanya menyebut TAHAP-nya dan mengakui
                    // jumlahnya belum pasti — jauh lebih jujur daripada
                    // mengarang "TIU" untuk tes yang mungkin DISC.
                    $daftar = [[
                        'nama' => 'Laporan Psikotes · ' . (string) ($a->TahapLabel ?? 'Hasil Tes'),
                        'halaman' => SinggahLaporanTes::halamanTersimpan($idPeserta, $idTahap),
                        'belumPasti' => true,
                    ]];
                }

                foreach ($daftar as $lap) {
                    $n = max(1, (int) ($lap['halaman'] ?? 1));
                    $namaTes = (string) ($lap['nama'] ?? 'Hasil Tes');

                    $peta[(int) $a->Id_Lamaran_Tahap_Tes][] = [
                        'jenis' => 'SISIP',
                        'sisip' => [
                            'jenis' => 'penanda',
                            'label' => $namaTes,
                            'nama' => (string) ($a->TahapLabel ?? 'Hasil Tes'),
                            'alasan' => ! empty($lap['belumPasti'])
                                ? 'Laporan psikotes diambil dari HCLearn saat berkas diunduh. Rincian tes & jumlah halamannya baru diketahui setelah pencetakan pertama.'
                                : ($n > 1
                                    ? "Laporan psikotes ditampilkan utuh ({$n} halaman) pada berkas yang diunduh."
                                    : 'Laporan psikotes ditampilkan utuh pada berkas yang diunduh.'),
                            'mewakili' => $n,
                        ],
                        'halaman' => $n,
                    ];
                }

                continue;
            }

            $singgah[$kunciSinggah] ??= LaporanTesClient::ambil(
                $idPeserta,
                $penggunaId,
                $idTahap,
                $lamaranId,
            );
            $balasan = $singgah[$kunciSinggah];

            if (! $balasan) {
                continue;
            }

            $rekamDaftar = [];

            foreach ($balasan['laporan'] ?? [] as $lap) {
                if (empty($lap['data'])) {
                    continue;
                }

                // ── PRATINJAU TIDAK MENGAMBIL LAPORAN PSIKOTES ────
                //
                // Singgahan hanya mencegah RENDER ulang; panggilan HTTP ke
                // CAT tetap jalan tiap kali. Diukur pada lamaran 594: 17-18
                // detik per panggilan, sama saja di panggilan kedua dan
                // ketiga — dan itu terbakar SETIAP KALI admin menekan
                // "Terapkan".
                //
                // Halaman laporan psikotes memakai tata letak CAT apa adanya
                // dan TIDAK terpengaruh satu pun setelan di Export Studio.
                // Jadi tidak ada yang bisa dinilai admin dari merendernya
                // ulang di pratinjau — yang perlu ia lihat hanyalah bahwa
                // laporan itu ada dan urutannya di mana.
                //
                // Berbeda dari berkas penilaian FGD/wawancara di bawah, yang
                // TETAP digabung sungguhan: ongkosnya ~1 detik dan isinya
                // satu-satunya bukti hasil tahap itu.
                // Lewat SINGGAHAN: laporan psikotes yang sama tidak
                // dirender ulang tiap kali berkas dicetak. Lihat
                // SinggahLaporanTes.
                $pdf = SinggahLaporanTes::ambil(
                    $lap,
                    $balasan['logo'] ?? null,
                    fn () => self::renderLaporanTes($lap, $balasan['logo'] ?? null),
                );

                if ($pdf === null) {
                    continue;
                }

                $nHal = GabungBerkas::jumlahHalaman($pdf) ?: 1;
                $namaTes = (string) ($lap['nama_tes'] ?? 'Hasil Tes');

                // Dicatat untuk penomoran pratinjau berikutnya — lihat
                // penjagaan $ringan di atas.
                SinggahLaporanTes::catatHalaman($idPeserta, $idTahap, $nHal);
                $rekamDaftar[] = ['nama' => $namaTes, 'halaman' => $nHal];

                $peta[(int) $a->Id_Lamaran_Tahap_Tes][] = [
                    'jenis' => 'SISIP',
                    'sisip' => ['jenis' => 'pdf', 'isi' => $pdf, 'label' => $namaTes],
                    'halaman' => $nHal,
                ];
            }

            // Daftar laporan peserta ini — dipakai pratinjau berikutnya untuk
            // membuat penanda sebanyak & senama laporan aslinya.
            SinggahLaporanTes::catatDaftar($idPeserta, $idTahap, $rekamDaftar);
        }

        return $peta;
    }

    /**
     * Render satu laporan psikotes memakai templat salinan dari CAT.
     *
     * ── DOKUMEN BERDIRI SENDIRI, PERSIS SEPERTI DI CAT ────────────────────
     *
     * Blade-nya salinan UTUH (lihat berkas:salin-templat-tes): kerangka HTML,
     * kop berlogo, kaki "DOKUMEN RAHASIA", tera air, dan seluruh CSS-nya ikut.
     * Karena itu ia dirender jadi PDF-nya sendiri di sini, lalu digabungkan di
     * tingkat PDF oleh FPDI — bukan disisipkan ke dalam dokumen rancangan.
     *
     * Setelan rendernya sengaja disamakan dengan controller CAT
     * (UjianNilaiAkhirController): A4 portrait, tanpa opsi tambahan. Menambah
     * atau mengurangi satu opsi di sini membuat halaman yang sama tercetak
     * berbeda di dua sistem — dan itulah yang justru harus dihindari.
     *
     * Null bila gagal; satu laporan bermasalah tidak boleh menggugurkan
     * seluruh berkas.
     */
    private static function renderLaporanTes(array $lap, ?string $logo): ?string
    {
        try {
            $blade = PetaTemplateTes::blade($lap['kode_tipe_hasil'] ?? null);

            // `watermark` = logo EVO Group ber-data-URI, dipakai kop laporan.
            // Dikirim CAT bersama payload supaya lambangnya tidak pernah
            // ketinggalan versi di sini.
            $pdf = Pdf::loadView($blade, ['lap' => $lap, 'watermark' => $logo]);
            $pdf->setPaper('A4', 'portrait');

            return $pdf->output();
        } catch (\Throwable $e) {
            Log::channel('web_career')->warning('[BERKAS-SELEKSI] laporan tes gagal dirender', [
                'kode' => $lap['kode_tipe_hasil'] ?? '-',
                'pesan' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /** Warna aksen bab menurut tipe tahap — mengikuti rancangan. */
    private static function aksenTipe(string $tipe): string
    {
        return match ($tipe) {
            'TES_OFFLINE_MANUAL' => '#f59e0b',
            'INTERVIEW', 'PHONE_SCREEN', 'FGD' => '#334155',
            default => '#a37a2c',
        };
    }

    // ══ LAMPIRAN ═══════════════════════════════════════════════════════════

    /**
     * Siapkan lampiran: unduh, hitung halaman, tandai yang gagal.
     *
     * Pada mode ringan (pratinjau) berkas TIDAK diunduh — hanya halaman
     * penandanya yang dibuat. Mengunduh belasan berkas dari penyimpanan awan
     * setiap kali admin menekan "Terapkan" membuat pratinjau berat tanpa
     * menambah apa pun yang dinilainya.
     */
    private static function siapkanLampiran(int $lamaranId, array $pilih, bool $ringan): array
    {
        $hasil = [];

        foreach (BerkasSeleksi::daftarLampiran($lamaranId) as $l) {
            if (! isset($pilih[$l['kunci']])) {
                continue;
            }

            $sisip = self::sisipanBerkas($l, $ringan);

            $hasil[] = [
                'label' => $l['label'],
                'nama' => $l['nama'],
                'halaman' => $sisip['halaman'],
                'gagal' => $sisip['gagal'],
                'bagian' => $sisip['sisip'],
            ];
        }

        return $hasil;
    }

    /**
     * Ubah satu berkas jadi sisipan siap gabung.
     *
     * Hanya GAMBAR dan PDF yang ditangani — keduanya satu-satunya format yang
     * boleh diunggah (lihat GcsBerkas::EKSTENSI_DIIZINKAN). Apa pun yang tidak
     * terbaca digantikan halaman penanda, bukan dilewati diam-diam: pembaca
     * berkas akan menyimpulkan dokumennya memang tidak pernah diserahkan,
     * padahal ia ada dan hanya gagal diproses.
     *
     * @return array{sisip: array, halaman: int, gagal: bool}
     */
    private static function sisipanBerkas(array $l, bool $ringan): array
    {
        // `$n` = jumlah halaman yang DIWAKILI penanda. Bukan jumlah halaman
        // penandanya sendiri (selalu 1) — lihat catatan di bawah.
        $penanda = fn (string $alasan, bool $gagal, int $n = 1) => [
            'sisip' => [
                'jenis' => 'penanda',
                'label' => $l['label'],
                'nama' => $l['nama'],
                'alasan' => $alasan,
                'mewakili' => $n,
            ],
            'halaman' => $n,
            'gagal' => $gagal,
        ];

        if ($ringan) {
            // ── PENANDA MEWAKILI JUMLAH HALAMAN ASLINYA ───────────────────
            //
            // Penandanya sendiri satu lembar, tapi 'halaman' diisi jumlah
            // halaman berkas SUNGGUHAN. Angka itulah yang dipakai penomoran,
            // sehingga nomor halaman di pratinjau sama persis dengan yang
            // tercetak pada unduhan — dan admin yang menyetel tata letak
            // halaman 12 menyetel halaman 12 yang sama.
            //
            // Sebelumnya penanda selalu dihitung 1: pratinjau 22 halaman
            // sementara unduhannya 39, dan seluruh nomor sesudah lampiran
            // pertama meleset.
            //
            // Ongkosnya hanya membaca metadata berkas, bukan menanam isinya.
            $n = self::taksirHalamanBerkas($l);

            return $penanda(
                $n > 1
                    ? "Ditampilkan utuh ({$n} halaman) pada berkas yang diunduh."
                    : 'Ditampilkan utuh pada berkas yang diunduh.',
                false,
                $n,
            );
        }

        $isi = BerkasSeleksi::isiBerkas($l['path'] ?? null);

        if ($isi === null) {
            return $penanda('Berkas tidak ditemukan pada penyimpanan.', true);
        }

        if (GabungBerkas::berupaGambar($l['ext'] ?? null)) {
            return [
                'sisip' => ['jenis' => 'gambar', 'isi' => $isi, 'label' => $l['label'], 'nama' => $l['nama']],
                'halaman' => 1,
                'gagal' => false,
            ];
        }

        $halaman = GabungBerkas::jumlahHalaman($isi);

        if ($halaman < 1) {
            return $penanda('Format dokumen ini belum didukung penggabung berkas.', true);
        }

        return [
            'sisip' => ['jenis' => 'pdf', 'isi' => $isi, 'label' => $l['label'], 'nama' => $l['nama']],
            'halaman' => $halaman,
            'gagal' => false,
        ];
    }

    /**
     * Jumlah halaman sebuah lampiran, untuk penomoran pratinjau.
     *
     * Berkasnya tetap dibaca — tapi HANYA untuk dihitung halamannya, tidak
     * ditanam ke dokumen. Itu bedanya dengan mode unduhan: yang mahal adalah
     * menggabungkan isinya (3 MB masuk ke PDF keluaran), bukan membacanya.
     *
     * Gagal baca → 1. Penomoran yang meleset satu halaman jauh lebih ringan
     * akibatnya daripada pratinjau yang gagal seluruhnya.
     */
    private static function taksirHalamanBerkas(array $l): int
    {
        if (GabungBerkas::berupaGambar($l['ext'] ?? null)) {
            return 1;
        }

        $isi = BerkasSeleksi::isiBerkas($l['path'] ?? null);

        if ($isi === null) {
            return 1;
        }

        return max(1, GabungBerkas::jumlahHalaman($isi));
    }

    // ══ PENERBITAN ═════════════════════════════════════════════════════════

    /**
     * Render halaman sistem, sisipkan bagian bawaan, gabungkan jadi satu PDF.
     *
     * Dijalankan DUA KALI: hitungan total halaman baru diketahui setelah
     * seluruh urutan dipetakan, sementara angka itu harus tercetak di sampul
     * dan indeks lampiran. Sekali jalan berarti sampul yang menjanjikan jumlah
     * halaman yang tidak sesuai isinya.
     */
    private static function terbitkan(array $d, ?string $logo, array $urutan): string
    {
        // Putaran pertama: hitung berapa halaman tiap seksi dompdf.
        $peta = self::petakan($d, $logo, $urutan, null);

        // Putaran kedua: nomor & total halaman sudah diketahui.
        $peta = self::petakan($d, $logo, $urutan, $peta['total']);

        $bagian = [];

        foreach ($peta['potongan'] as $p) {
            $bagian[] = $p;
        }

        if (! $bagian) {
            throw new \RuntimeException('Tidak ada bagian yang dipilih untuk dicetak.');
        }

        return count($bagian) === 1 && $bagian[0]['jenis'] === 'pdf'
            ? $bagian[0]['isi']
            : GabungBerkas::rakit($bagian);
    }

    /**
     * Petakan seluruh dokumen jadi potongan PDF berurutan.
     *
     * Seksi dompdf yang bersebelahan dirender BERSAMA dalam satu panggilan —
     * bukan satu per satu. Merender per halaman berarti puluhan panggilan
     * dompdf untuk satu berkas, dan tiap panggilan menanggung biaya
     * penguraian font & CSS dari awal.
     *
     * @return array{potongan: list<array>, total: int}
     */
    private static function petakan(array $d, ?string $logo, array $urutan, ?int $total): array
    {
        $potongan = [];
        $kumpul = [];
        $no = 0;
        $hitung = 0;

        $lepas = function () use (&$kumpul, &$potongan, $d, $logo, $total) {
            if (! $kumpul) {
                return 0;
            }

            $isi = self::render($d, $kumpul, $logo, $total);
            $potongan[] = ['jenis' => 'pdf', 'isi' => $isi, 'label' => 'Dokumen'];
            $kumpul = [];

            return GabungBerkas::jumlahHalaman($isi) ?: 1;
        };

        foreach ($urutan as $s) {
            if ($s['jenis'] === 'SISIP') {
                $hitung += $lepas();
                $potongan[] = $s['sisip'];
                $hitung += $s['halaman'];
                $no += $s['halaman'];

                continue;
            }

            // Penutup dikeluarkan dari rentetan supaya ia selalu jadi halaman
            // terakhir, sesudah seluruh sisipan.
            $s['no'] = ++$no;
            $kumpul[] = $s;
        }

        $hitung += $lepas();

        return ['potongan' => $potongan, 'total' => $hitung];
    }

    /** Render sekumpulan seksi jadi satu PDF. */
    private static function render(array $d, array $seksi, ?string $logo, ?int $total): string
    {
        $pdf = Pdf::loadView('career.berkas.dokumen', [
            'd' => $d,
            'seksi' => $seksi,
            'logo' => $logo,
            'tglLamar' => self::tanggal($d['lamaran']['waktuLamar'] ?? null),
            'dicetak' => $d['dicetak'] ?? now()->format('d M Y H:i'),
            'totalHalaman' => $total,
        ]);

        // Kanvas rancangan: A4 pada 96 dpi, margin nol. Ukuran dalam poin
        // (72 dpi) supaya dompdf memetakan 794×1123 px ke selembar A4 utuh.
        $pdf->setPaper([0, 0, 595.28, 841.89], 'portrait');
        $pdf->setOptions([
            'isHtml5ParserEnabled' => true,
            'isFontSubsettingEnabled' => true,
            // Gambar seluruhnya data URI. Menyalakan ini berarti dompdf boleh
            // menembak URL yang datang dari isian kandidat.
            'isRemoteEnabled' => false,
            'defaultFont' => 'Helvetica',
            // 794 px pada 96 dpi = 595.28 pt. Tanpa ini dompdf memakai 96 dpi
            // untuk panjang CSS dan seluruh koordinat mutlak meleset ~33%.
            'dpi' => 96,
        ]);

        return $pdf->output();
    }

    // ══ UTILITAS ═══════════════════════════════════════════════════════════

    /** Formulir dicocokkan lewat kunci hashid yang dipakai perakit daftar. */
    private static function formulirDipilih(array $form, array $pilih): bool
    {
        return isset($pilih['formulir.' . Hashids::encode($form['pengisianId'] ?? 0)]);
    }

    /** Id pengisian yang dicentang — dipakai membatasi apa yang dirakit. */
    private static function pengisianTerpilih(array $pilih): array
    {
        $ids = [];

        foreach (array_keys($pilih) as $k) {
            if (! str_starts_with($k, 'formulir.')) {
                continue;
            }

            // Kunci bagian ('formulir.XX.<md5>') tidak ikut — hanya kunci
            // formulirnya sendiri yang menyebut id pengisian.
            $sisa = substr($k, 9);

            if (str_contains($sisa, '.')) {
                continue;
            }

            $id = Hashids::decode($sisa)[0] ?? null;

            if ($id) {
                $ids[] = (int) $id;
            }
        }

        return $ids;
    }

    private static function tanggal(?string $waktu): string
    {
        if (! $waktu) {
            return '—';
        }

        try {
            return \Carbon\Carbon::parse($waktu)->translatedFormat('d M Y H:i');
        } catch (\Throwable $e) {
            return (string) $waktu;
        }
    }
}
