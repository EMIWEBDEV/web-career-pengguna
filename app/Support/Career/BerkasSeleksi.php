<?php

namespace App\Support\Career;

use App\Services\WebCareers\LaporanTesClient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Vinkla\Hashids\Facades\Hashids;

/**
 * WEB CAREER — perakit BERKAS SELEKSI KANDIDAT (read-only).
 *
 * Menyusun daftar SEKSI yang membentuk dokumen seleksi: sampul, data kandidat,
 * hasil tiap tahap, sampai lampiran. Dipakai dua kali dengan cara berbeda —
 * layar Export Studio memanggil daftar() untuk menampilkan pilihan centang,
 * dan job cetak memanggil rakit() untuk mengambil isinya.
 *
 * ── SUSUNANNYA DARI DATA, BUKAN DARI DAFTAR DI SINI ────────────────────────
 *
 * Tidak ada satu pun nama tahap yang ditulis di kelas ini. Alur seleksi
 * disusun lewat layar: satu lowongan berhenti di enam tahap, yang lain punya
 * sembilan; satu tahap bisa memuat tiga aktivitas sekaligus (di produksi ada
 * tahap "FGD Dan Wawancara HR" berisi Psikotes 2 + FGD + Wawancara). Menuliskan
 * daftarnya di sini berarti setiap tahap baru yang dirancang tim rekrutmen
 * hilang dari dokumen sampai ada yang ingat menyunting berkas ini.
 *
 * Yang dibaca: Lamaran_Tahap (babak) → Lamaran_Tahap_Tes (aktivitas). Tiap
 * aktivitas menentukan sendiri bentuk cetaknya dari TIPE-nya, bukan namanya.
 *
 * ── KUNCI SEKSI HARUS TAHAN PERUBAHAN ──────────────────────────────────────
 *
 * Kunci dibangun dari ID baris, bukan dari urutan atau label. Layar mengirim
 * balik kunci yang dicentang, dan job memakainya untuk memilih isi. Kunci
 * berbasis urutan akan menunjuk seksi yang berbeda begitu satu tahap
 * ditambahkan — dan admin menerima dokumen yang bukan ia minta.
 */
class BerkasSeleksi
{
    /** Bab besar dokumen — urutannya mengikuti rancangan e-book. */
    public const BAB_SAMPUL = 'SAMPUL';

    public const BAB_SURAT = 'SURAT_LAMARAN';

    public const BAB_DATA = 'DATA_KANDIDAT';

    public const BAB_HASIL = 'HASIL_SELEKSI';

    public const BAB_LAMPIRAN = 'LAMPIRAN';

    public const BAB_PENUTUP = 'PENUTUP';

    /**
     * Tipe tahap yang hasilnya dicetak sebagai penilaian naratif.
     *
     * Diambil dari master N_WEB_CAREERS_Master_Tipe_Tahap. Yang TIDAK termasuk
     * di sini (FORM, DOCUMENT) tidak punya halaman penilaian sendiri —
     * jawabannya sudah tercetak di bab data kandidat.
     */
    private const TIPE_BERNILAI = [
        'INTERVIEW', 'FGD', 'TES_OFFLINE_MANUAL', 'PHONE_SCREEN', 'MCU',
        'REFERENCE_CHECK', 'BACKGROUND_CHECK', 'NEGOTIATION', 'ADMIN_SCREENING',
    ];

    /**
     * Daftar seksi untuk DITAMPILKAN di layar pilihan.
     *
     * Ringan dengan sengaja: tidak memanggil CAT dan tidak mengunduh satu pun
     * berkas. Layar hanya perlu tahu ada seksi apa saja, bukan isinya — dan
     * membuka modal tidak boleh menunggu belasan panggilan jaringan.
     *
     * ── EMPAT TINGKAT ─────────────────────────────────────────────────────
     *   bab     Sampul, Data Kandidat, Hasil Seleksi, Lampiran, Penutup
     *   seksi   satu formulir / satu aktivitas / satu lampiran
     *   bagian  babak di dalam formulir ("B. Identitas")
     *   field   satu pertanyaan ("Nama Lengkap")
     *
     * Tingkat FIELD ada karena dua formulir kerap menanyakan hal yang sama —
     * "Nama Lengkap" muncul di validasi data peserta maupun di pendaftaran.
     * Tanpa centang setingkat itu, admin terpaksa memilih antara mencetak nama
     * yang sama dua kali atau membuang seluruh bagiannya.
     *
     * ── JUDUL YANG BISA DITULIS ULANG HANYA JUDUL BAB ─────────────────────
     * Bab bertanda `bisaUbahJudul` boleh diganti namanya di dokumen — "Hasil
     * Seleksi" jadi "Summary", "Lampiran Dokumen" jadi "Appendix". Judul seksi
     * & bagian TIDAK: keduanya datang dari master alur dan skema formulir,
     * yang sudah punya layar penyuntingnya sendiri — menyediakan jalan kedua
     * di sini hanya membuat dokumen menyebut hal yang tidak cocok dengan
     * layar mana pun.
     *
     * @return array{bab: list<array>, kandidat: array}|null
     */
    public static function daftar(int $lamaranId): ?array
    {
        $lamaran = self::lamaran($lamaranId);

        if (! $lamaran) {
            return null;
        }

        return [
            'kandidat' => [
                'nama' => $lamaran->NamaKandidat,
                'kode' => $lamaran->Kode,
                'posisi' => $lamaran->Posisi,
                'program' => $lamaran->ProgramNama,
            ],
            'bab' => [
                self::babSampul(),
                self::babSurat(),
                self::babData($lamaranId),
                self::babHasil($lamaranId),
                self::babLampiran($lamaranId),
                self::babPenutup(),
            ],
            // Isian yang menjawab hal sama di dua formulir — dilaporkan, bukan
            // diputuskan. Lihat DuplikatIsian.
            'duplikat' => self::duplikat($lamaranId),
        ];
    }

    /**
     * Kelompok isian kembar antar-formulir, untuk ditawarkan di panel kanan.
     *
     * SISTEM TIDAK MEMILIH SENDIRI. "Mana yang benar" kadang bergantung pada
     * hal yang tidak tersimpan di mana pun — formulir mana yang lebih baru,
     * mana yang sudah diverifikasi petugas. Yang bisa dikerjakan sistem adalah
     * menunjukkan bahwa pengulangan itu ada, lengkap dengan asalnya, lalu
     * menyerahkan keputusannya lewat centang tingkat field yang sudah ada.
     */
    private static function duplikat(int $lamaranId): array
    {
        $d = self::rakitSemua($lamaranId);

        if (! $d) {
            return [];
        }

        // ── YANG DILAPORKAN HANYA YANG BENAR-BENAR TERCETAK ───────────────
        //
        // SusunCv sudah membuang isian kembar saat menyusun CV: dari dua
        // formulir MT, "Nama Lengkap Sesuai KTP" dan "Nama Lengkap" melebur
        // jadi satu. Melaporkannya lagi di sini berarti panel kanan menuduh
        // ada tiga pengulangan pada dokumen yang sama sekali tidak
        // mengulanginya — admin membuka tab itu, mematikan salah satu
        // centang, dan justru menghapus data yang tadinya benar.
        //
        // Karena itu laporan disaring terhadap hasil akhir: kelompok yang
        // seluruh anggotanya sudah tereliminasi tidak disebut. Angka bawaan
        // di tab Duplikat dengan begitu NOL, dan setiap angka yang muncul
        // sesudahnya benar-benar berarti "ini masih tercetak dua kali".
        $tercetak = self::nilaiTercetakCv($d);

        return collect(DuplikatIsian::cari($d['formulir'] ?? []))
            ->filter(function ($g) use ($tercetak) {
                // Kelompok masih berarti bila nilainya memang muncul lebih
                // dari sekali pada CV yang jadi.
                return ($tercetak[$g['kunciNilai'] ?? $g['nilai']] ?? 0) > 1;
            })
            ->values()
            ->all();
    }

    /**
     * Berapa kali tiap nilai benar-benar tercetak di CV.
     *
     * Dihitung dari hasil SusunCv — bukan dari formulir mentah — karena
     * itulah yang sampai ke kertas. Halaman profil ikut dihitung: ia mencetak
     * nama, kontak, dan alamat yang sama.
     *
     * @return array<string, int>
     */
    private static function nilaiTercetakCv(array $d): array
    {
        $hitung = [];

        $tambah = function (?string $v) use (&$hitung) {
            $s = DuplikatIsian::sidik((string) $v);

            if ($s !== '') {
                $hitung[$s] = ($hitung[$s] ?? 0) + 1;
            }
        };

        // Halaman profil — kepala CV.
        foreach (['nama', 'email', 'hp', 'nik', 'tglLahir', 'jkel',
            'kampus', 'jurusan', 'jenjang', 'statusStudi', 'ipk'] as $k) {
            $tambah($d['kandidat'][$k] ?? null);
        }

        foreach ($d['panen'] ?? [] as $v) {
            if (is_scalar($v)) {
                $tambah((string) $v);
            }
        }

        // Bagian CV — `true` supaya penyaring profil ikut berlaku, sama
        // persis dengan yang dipakai perakit.
        foreach (SusunCv::bagian($d, true) as $b) {
            foreach ($b['isian'] as $i) {
                $tambah((string) ($i['nilai'] ?? ''));
            }
        }

        return $hitung;
    }

    /**
     * Hasil rakit() SELURUH formulir, disinggahkan selama satu permintaan.
     *
     * babData() dan duplikat() sama-sama membutuhkannya, dan keduanya dipanggil
     * dalam satu daftar(). Tanpa singgahan ini basis data dibaca dua kali untuk
     * jawaban yang sama persis — 1,3 detik yang tidak menghasilkan apa pun,
     * pada layar yang dokumentasinya sendiri berjanji "sengaja ringan".
     *
     * Disimpan per LAMARAN, bukan satu variabel: satu permintaan bisa saja
     * memanggil dua lamaran berbeda, dan singgahan yang tidak memedulikan itu
     * akan memulangkan formulir milik kandidat lain — kesalahan yang jauh lebih
     * mahal daripada kueri yang diulang.
     *
     * Hidupnya hanya selama proses berjalan. Tidak ada cache lintas-permintaan
     * di sini dengan sengaja: jawaban formulir bisa berubah kapan saja, dan
     * layar yang menampilkan centang atas data usang membuat admin mencetak
     * dokumen yang bukan ia lihat.
     */
    private static function rakitSemua(int $lamaranId): ?array
    {
        static $singgah = [];

        if (array_key_exists($lamaranId, $singgah)) {
            return $singgah[$lamaranId];
        }

        $ids = [];

        foreach (LaporanKandidat::daftarFormulir($lamaranId) as $f) {
            $id = Hashids::decode($f['id'])[0] ?? null;

            if ($id) {
                $ids[] = (int) $id;
            }
        }

        return $singgah[$lamaranId] = $ids
            ? LaporanKandidat::rakit($lamaranId, $ids)
            : null;
    }

    // ══ BAB: SAMPUL & PENUTUP ══════════════════════════════════════════════

    /**
     * Sampul & Penutup TIDAK punya kepala bab sendiri di layar.
     *
     * Keduanya bab berisi tepat satu seksi yang namanya mengulang nama babnya
     * ("Sampul" → "Halaman Sampul"). Kepala babnya karena itu tidak
     * mengelompokkan apa pun: ia hanya baris kedua yang tidak bisa digeser
     * (posisinya terkunci di ujung), tidak bisa dilipat (isinya satu), dan
     * pada Sampul tidak bisa dicentang (wajib) — dua baris untuk satu hal
     * yang sama.
     *
     * Ditandai `tunggal` supaya panel merender seksinya langsung sebagai satu
     * baris. Babnya tetap ADA di struktur data: RakitBerkasSeleksi::urutanBab()
     * memakai kodenya untuk menempatkan sampul di depan dan penutup di
     * belakang, dan menghapusnya berarti menulis ulang penjagaan itu.
     */
    private static function babSampul(): array
    {
        return [
            'kode' => self::BAB_SAMPUL,
            'label' => 'Sampul',
            'ikon' => 'bi-file-earmark-richtext',
            'tunggal' => true,
            'seksi' => [[
                'kunci' => 'sampul',
                'label' => 'Halaman Sampul',
                'catatan' => 'Identitas kandidat & ringkasan alur seleksi',
                // Sampul memuat nomor lamaran dan nama kandidat — tanpanya
                // dokumen tercetak tak bisa ditelusuri pemiliknya.
                'wajib' => true,
                'terpilih' => true,
                // TIDAK ADA YANG BISA DIATUR DI SAMPUL.
                //
                // `bisaUbahJudul` sempat dipasang di sini, dan itu keliru:
                // sampul adalah muka dokumen resmi. Judulnya sengaja sama pada
                // berkas seluruh kandidat — itulah yang membuat setumpuk berkas
                // bisa dikenali sebagai satu jenis arsip. Membiarkannya diketik
                // ulang per kandidat menghapus keseragaman itu, sementara isinya
                // (nama, nomor lamaran, alur seleksi) toh datang dari data.
                //
                // Yang boleh diganti judulnya hanya halaman PEMISAH bab — lihat
                // babHasil() & babLampiran(): di sana judul memang penamaan
                // internal ("Hasil Seleksi" → "Summary"), bukan identitas berkas.
            ]],
        ];
    }

    private static function babPenutup(): array
    {
        return [
            'kode' => self::BAB_PENUTUP,
            'label' => 'Penutup',
            'ikon' => 'bi-bookmark-check',
            'tunggal' => true,
            'seksi' => [[
                'kunci' => 'penutup',
                'label' => 'Halaman Penutup',
                'catatan' => 'Penanda akhir dokumen & pernyataan kerahasiaan',
                'wajib' => false,
                'terpilih' => true,
            ]],
        ];
    }

    // ══ BAB: SURAT LAMARAN (belum tersedia) ════════════════════════════════

    /**
     * Surat lamaran — DIDAFTARKAN, tapi belum bisa dicetak.
     *
     * Human Capital eVO Group belum memiliki templat resmi surat lamaran, jadi
     * seksinya dikunci. Tetap dicantumkan — dan tetap di urutan yang benar,
     * tepat sesudah sampul — supaya saat templatnya tersedia yang perlu
     * dilakukan hanya melepas kuncinya; posisinya di dokumen sudah tepat dan
     * tidak perlu ada yang menebak ulang harus disisipkan di mana.
     */
    private static function babSurat(): array
    {
        return [
            'kode' => self::BAB_SURAT,
            'label' => 'Surat Lamaran',
            'ikon' => 'bi-envelope-paper',
            // Sama seperti Sampul & Penutup: satu bab satu seksi, dengan nama
            // yang saling mengulang. Terkunci pula, jadi kepala babnya benar-
            // benar tidak menawarkan apa-apa.
            'tunggal' => true,
            'seksi' => [[
                'kunci' => 'surat-lamaran',
                'label' => 'Surat Lamaran Kerja',
                'catatan' => 'Pernyataan minat dan kesediaan kandidat',
                'wajib' => false,
                'terpilih' => false,
                'terkunci' => true,
                'alasanKunci' => 'Belum ada template surat lamaran di Human Capital eVO Group.',
            ]],
        ];
    }

    // ══ BAB: DATA KANDIDAT ═════════════════════════════════════════════════

    /**
     * Data diri + tiap formulir yang pernah dikirim, bisa dicentang per BAGIAN.
     *
     * Satu lamaran bisa punya beberapa pengisian: pendaftaran, lalu kelengkapan
     * data di tahap berikutnya. Semuanya ditawarkan — admin yang memutuskan
     * mana yang relevan bagi pembaca dokumen.
     *
     * Bagiannya dibaca dari skema yang DIBEKUKAN saat kandidat mengirim, bukan
     * dari skema yang berlaku sekarang: pertanyaan bisa diganti setelah
     * dijawab, dan mencetak jawaban lama di bawah pertanyaan baru adalah cara
     * paling halus menyampaikan hal yang keliru.
     */
    private static function babData(int $lamaranId): array
    {
        // ── SATU SEKSI PER FORMULIR, TIDAK LEBIH ──────────────────────────
        //
        // Bab ini dulu memuat dua seksi tambahan bikinan sendiri — "Data
        // Kandidat" dan "Pengalaman, Organisasi & Sertifikasi". Keduanya
        // DIHAPUS: isinya diambil dari jawaban formulir yang sama, jadi
        // mencetak ketiganya berarti kandidat yang punya dua formulir melihat
        // empat pilihan untuk dua sumber, dan jawaban yang sama tercetak dua
        // kali di halaman berbeda.
        //
        // Halaman profil & riwayat tetap ada di dokumen — keduanya dirakit
        // OTOMATIS dari formulir pertama yang dicentang (lihat
        // RakitBerkasSeleksi::susun), bukan dari centang tersendiri.
        $seksi = [];

        $daftar = LaporanKandidat::daftarFormulir($lamaranId);

        // Bagian & fieldnya dibaca dari hasil rakitan LaporanKandidat, bukan
        // dari skema mentah: yang ditawarkan harus yang BENAR-BENAR TERJAWAB.
        // Skema memuat seluruh pertanyaan termasuk yang tertutup syarat
        // "tampil_jika", dan menawarkannya berarti admin mencentang bagian
        // yang tidak akan pernah muncul di dokumen.
        //
        // SELURUH id pengisian ikut dirakit — rakitSemua() yang mengurusnya.
        // Tanpa itu rakit() hanya memulangkan pengisian TERBARU, sehingga
        // formulir pendaftaran tak pernah punya daftar field dan isiannya
        // tidak bisa disaring.
        //
        // Hasilnya disinggahkan dan dipakai bersama duplikat() — lihat
        // rakitSemua().
        $rakit = collect(self::rakitSemua($lamaranId)['formulir'] ?? [])
            ->keyBy('pengisianId');

        foreach ($daftar as $f) {
            $id = Hashids::decode($f['id'])[0] ?? null;

            if (! $id) {
                continue;
            }

            $seksi[] = [
                'kunci' => 'formulir.' . $f['id'],
                'label' => $f['label'],
                'catatan' => 'Dikirim ' . self::tanggal($f['waktuKirim']),
                'wajib' => false,
                'terpilih' => (bool) $f['utama'],
                // Formulir TIDAK lagi jadi halaman tersendiri — isinya melebur
                // ke CV. Seksi ini tinggal jadi SUMBER: mematikan centangnya
                // membuang seluruh jawabannya dari CV, dan centang bagian &
                // field di dalamnya memilih mana yang ikut.
                //
                // `bisaJarak` dicabut: jarak atas dibaca halaman, dan halaman
                // CV punya kuncinya sendiri ('cv#0', 'cv#1'). Slider di sini
                // tidak akan menggerakkan apa pun.
                'bagian' => self::bagianFormulir($rakit->get((int) $id)['bagian'] ?? []),
            ];
        }

        return [
            'kode' => self::BAB_DATA,
            'label' => 'Data Kandidat',
            'ikon' => 'bi-person-vcard',
            // Judul bab bisa ditulis ulang admin — lihat catatan di daftar().
            'bisaUbahJudul' => true,
            // Bagian CV lebih dulu: itulah yang benar-benar dicetak. Formulir
            // menyusul di bawahnya sebagai sumber datanya.
            'seksi' => array_merge(self::seksiCv($lamaranId), $seksi),
        ];
    }

    /**
     * Bagian CV yang bisa dicentang & diatur bentuknya.
     *
     * ── KENAPA DIDAHULUKAN DARI DAFTAR FORMULIR ───────────────────────────
     *
     * Inilah yang benar-benar tercetak. Formulir di bawahnya bukan halaman
     * lagi — ia sumber data: mematikan centangnya membuang seluruh jawabannya
     * dari CV, dan centang field di dalamnya memilih mana yang ikut.
     *
     * Bagian yang muncul mengikuti isi formulir kandidat ini, bukan daftar
     * tetap: kandidat tanpa riwayat organisasi tidak melihat baris
     * "Organisasi & Aktivitas" sama sekali. Lihat SusunCv.
     */
    private static function seksiCv(int $lamaranId): array
    {
        $d = self::rakitSemua($lamaranId);

        if (! $d) {
            return [];
        }

        $seksi = [];

        foreach (SusunCv::bagian($d) as $b) {
            $jumlah = count($b['isian']) + collect($b['baris'])
                ->sum(fn ($r) => count($r['isian']['baris'] ?? []));

            // ── DAFTAR NAMA BERKAS TIDAK DICENTANG DARI SANA ──────────
            //
            // Bagian "Kelengkapan Dokumen" isinya nama berkas
            // ("menaya.pdf", "…-20260404_131248-XiIR.pdf") — nama unggahan,
            // bukan fakta tentang kandidat. Berkasnya sendiri tercetak utuh
            // di bab Lampiran, jadi mencetak daftar namanya di CV hanya
            // mengulang apa yang sudah ada beberapa halaman kemudian, dengan
            // nama berkas mentah yang tidak berarti bagi pembaca.
            //
            // Ditawarkan, tidak dibuang: admin yang memang ingin daftar itu
            // tinggal mencentangnya.
            $bawaanMati = $b['kunci'] === 'cv.dokumen';

            $seksi[] = [
                'kunci' => $b['kunci'],
                'label' => $b['judul'],
                'catatan' => $jumlah . ' isian · dari ' . implode(' + ', $b['sumber'])
                    . ($bawaanMati ? ' · berkasnya tercetak di bab Lampiran' : ''),
                'wajib' => false,
                'terpilih' => ! $bawaanMati,
                // Bentuk tampilan bisa diganti admin — timeline / tabel /
                // kartu untuk riwayat, kisi / kartu / baris untuk isian biasa.
                'bisaBentuk' => empty($b['baris']),
                'berulang' => ! empty($b['baris']),
                'gaya' => $b['bentuk'],
                'bisaJarak' => true,
                // Penempatan pada halaman dua kolom — hanya bagian CV yang
                // punya ini. Bawaannya rekomendasi sistem; admin boleh
                // memindahkannya lewat panel kanan.
                'bisaLajur' => true,
                'lajur' => $b['lajur'],
            ];
        }

        return $seksi;
    }

    /**
     * Judul bagian sebuah pengisian, untuk centang tingkat ketiga.
     *
     * Kuncinya BERAWALAN kunci formulirnya sendiri ('formulir.<hash>.<md5>')
     * supaya dua formulir yang kebetulan punya bagian berjudul sama — MT
     * menanyakan "Identitas" dua kali — tidak saling mematikan centang.
     */
    private static function bagianFormulir(array $bagianRakit): array
    {
        $hasil = [];

        foreach ($bagianRakit as $b) {
            $judul = trim((string) ($b['judul'] ?? ''));

            if ($judul === '') {
                continue;
            }

            $berulang = ! empty($b['berulang']);

            // ── FIELD IKUT DITAWARKAN, SATU PER SATU ──────────────────────
            //
            // Satu lamaran bisa punya dua formulir yang menanyakan hal yang
            // sama — "Nama Lengkap" ada di validasi data peserta maupun di
            // formulir pendaftaran. Tanpa centang setingkat field, admin
            // terpaksa memilih antara mencetak keduanya (nama yang sama dua
            // kali) atau membuang seluruh bagiannya.
            //
            // Bagian BERULANG tidak dipecah: isinya satu daftar riwayat, dan
            // memilih sebagian kolomnya bukan pilihan yang berarti — yang
            // diatur di sana bentuk tampilannya, bukan isinya.
            // Kunci bagian datang dari LaporanKandidat::bagi() — ia memuat
            // nomor urut, jadi empat bagian yang sama-sama berjudul "Bagian 1"
            // tetap punya kunci berbeda. Jangan dihitung ulang dari judul.
            $kunciBagian = (string) ($b['kunci'] ?? '');

            if ($kunciBagian === '') {
                continue;
            }

            $field = [];

            if (! $berulang) {
                foreach ($b['isian'] ?? [] as $i) {
                    $key = (string) ($i['key'] ?? '');

                    if ($key === '') {
                        continue;
                    }

                    $field[] = [
                        'kunci' => $kunciBagian . '.' . md5($key),
                        'label' => $i['label'] ?: $key,
                        'terpilih' => true,
                        // Isian berupa berkas ditandai: di halaman formulir ia
                        // dicetak sebagai NAMA BERKAS saja (dokumennya sendiri
                        // ada di bab Lampiran), dan admin perlu tahu itu
                        // sebelum memutuskan mencentangnya.
                        'berkas' => ! empty($i['berkas']),
                    ];
                }
            }

            $hasil[] = [
                'kunci' => $kunciBagian,
                'label' => $judul,
                'terpilih' => true,
                'berulang' => $berulang,
                // Bagian formulir ikut membaca jarakAtas — lihat
                // formulir.blade.php, yang mencetaknya sebagai padding-top di
                // atas judul bagian.
                'bisaJarak' => true,
                // Bentuk isian pendek (kisi/kartu/baris) hanya berarti bila
                // bagian ini PUNYA isian pendek. Bagian berulang dan bagian
                // berisi jawaban ya/tidak saja tidak terpengaruh, jadi
                // menawarkan pilihannya di sana hanya saklar mati.
                'bisaBentuk' => ! $berulang && self::punyaIsianPendek($b),
                'gaya' => $berulang ? self::gayaBawaanIsian($b) : null,
                'field' => $field,
                // Cuplikan isi — dipakai layar untuk membedakan bagian yang
                // judulnya kembar. Lihat catatan pada tandaiKembar().
                'cuplikan' => self::cuplikanIsi($b, $field),
            ];
        }

        return self::tandaiKembar($hasil);
    }

    /**
     * Apakah bagian ini memuat isian pendek (label + nilai singkat)?
     *
     * Yang TIDAK dihitung: jawaban ya/tidak (dicetak sebagai tabel berbingkai
     * sendiri), isian berkas (namanya saja), dan uraian panjang (kotak
     * berbatang kiri). Ketiganya punya bentuknya masing-masing yang tidak
     * terpengaruh pilihan kisi/kartu/baris.
     */
    private static function punyaIsianPendek(array $b): bool
    {
        $biner = ['ya', 'y', 'true', '1', 'sudah', 'bersedia', 'setuju',
            'tidak', 't', 'false', '0', 'belum'];

        foreach ($b['isian'] ?? [] as $i) {
            if (! empty($i['baris']) || ! empty($i['berkas'])) {
                continue;
            }

            $v = trim((string) ($i['nilai'] ?? ''));

            if ($v === '' || mb_strlen($v) > 60) {
                continue;
            }

            if (! in_array(mb_strtolower($v), $biner, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Beri nomor urut pada bagian yang judulnya KEMBAR.
     *
     * Perancang formulir tidak wajib memberi judul unik: formulir pendaftaran
     * di produksi punya EMPAT bagian yang semuanya berjudul "Bagian 1". Kunci
     * keempatnya memang sudah berbeda (LaporanKandidat::bagi() memasukkan
     * nomor urut ke dalam kunci), jadi centangnya tidak saling mematikan —
     * tapi di layar keempatnya terbaca sebagai satu baris yang sama diulang
     * empat kali, dan admin tidak punya cara menebak mana yang mana.
     *
     * Nomornya HANYA dipasang pada judul yang benar-benar muncul lebih dari
     * sekali. Bagian yang judulnya sudah unik dibiarkan apa adanya: menomori
     * "B. Identitas" jadi "B. Identitas (1)" hanya menambah derau.
     *
     * Yang dinomori hanya LABEL DI LAYAR (`label`); judul yang tercetak di
     * dokumen tetap judul aslinya — nomor urut itu alat bantu memilih, bukan
     * bagian dari dokumen resmi.
     *
     * @param  list<array>  $bagian
     * @return list<array>
     */
    private static function tandaiKembar(array $bagian): array
    {
        $jumlah = [];

        foreach ($bagian as $b) {
            $j = (string) $b['label'];
            $jumlah[$j] = ($jumlah[$j] ?? 0) + 1;
        }

        $ke = [];

        foreach ($bagian as $i => $b) {
            $j = (string) $b['label'];

            if (($jumlah[$j] ?? 0) < 2) {
                continue;
            }

            $ke[$j] = ($ke[$j] ?? 0) + 1;
            $bagian[$i]['urutKembar'] = $ke[$j];
        }

        return $bagian;
    }

    /**
     * Beberapa nama isian pertama sebuah bagian, untuk ditampilkan di layar.
     *
     * Inilah yang benar-benar membedakan empat "Bagian 1": yang satu berisi
     * nama & tanggal lahir, yang lain alamat. Judulnya boleh kembar, isinya
     * tidak pernah.
     *
     * Dibatasi tiga label supaya tetap muat satu baris di panel yang lebarnya
     * 320px; sisanya tidak disebut karena baris yang terpotong di tengah kata
     * tidak membantu siapa pun.
     *
     * @param  list<array>  $field  isian yang sudah disaring bagianFormulir()
     */
    private static function cuplikanIsi(array $bagian, array $field): string
    {
        // Bagian berulang tidak punya daftar $field (isinya satu daftar
        // riwayat), jadi labelnya diambil dari sub-isian baris pertama.
        $label = $field
            ? array_map(fn ($f) => (string) $f['label'], $field)
            : self::labelBerulang($bagian);

        $label = array_values(array_filter($label, fn ($l) => trim($l) !== ''));

        if (! $label) {
            return '';
        }

        $ambil = array_slice($label, 0, 3);
        $teks = implode(', ', $ambil);

        return count($label) > count($ambil) ? $teks . ', …' : $teks;
    }

    /** Label sub-isian baris pertama sebuah bagian berulang. */
    private static function labelBerulang(array $bagian): array
    {
        foreach ($bagian['isian'] ?? [] as $i) {
            foreach ($i['baris'] ?? [] as $baris) {
                $label = [];

                foreach ($baris as $sub) {
                    $label[] = (string) ($sub['label'] ?? '');
                }

                if ($label) {
                    return $label;
                }
            }
        }

        return [];
    }

    /**
     * Bentuk tampilan bawaan untuk satu bagian berulang.
     *
     * Timeline dipakai bila bagian itu punya isian bertanggal — riwayat kerja,
     * organisasi, sertifikasi semuanya berurutan waktu, dan garis waktu adalah
     * bentuk yang paling terbaca untuknya. Daftar tanpa tanggal (mis. daftar
     * kenalan di perusahaan) jatuh ke tabel: timeline tanpa sumbu waktu hanya
     * menyisakan titik-titik yang tidak menerangkan apa pun.
     *
     * Admin tetap bisa menggantinya lewat panel kanan; ini hanya tebakan awal
     * yang benar untuk sebagian besar kasus.
     */
    private static function gayaBawaanIsian(array $bagian): string
    {
        // Label sub-isian tiap baris — itulah yang menyatakan bagian ini
        // punya sumbu waktu atau tidak.
        foreach ($bagian['isian'] ?? [] as $i) {
            foreach ($i['baris'] ?? [] as $baris) {
                foreach ($baris as $sub) {
                    $label = mb_strtolower((string) ($sub['label'] ?? ''));

                    foreach (['periode', 'tahun', 'tanggal', 'mulai', 'selesai'] as $kata) {
                        if (str_contains($label, $kata)) {
                            return 'timeline';
                        }
                    }
                }
            }
        }

        return 'tabel';
    }

    // ══ BAB: HASIL SELEKSI ═════════════════════════════════════════════════

    /**
     * Hasil tiap aktivitas di tiap tahap — babak paling dinamis.
     *
     * Satu tahap bisa memuat beberapa aktivitas dengan sifat berbeda: tes
     * daring dari CAT, FGD yang dinilai asesor, wawancara. Masing-masing jadi
     * seksi sendiri supaya admin bisa memilih — mis. mencetak hasil psikotes
     * tanpa membuka catatan wawancara internal.
     *
     * Aktivitas dari CAT ditandai `sumber = CAT`. Isinya TIDAK diambil di sini
     * (daftar ini harus ringan); templatnya baru ditentukan saat merakit,
     * setelah kode tipe hasil yang sebenarnya diketahui dari CAT.
     *
     * ── APA YANG DICETAK BAWAANNYA: BERKASNYA, BUKAN RINGKASANNYA ─────────
     *
     * Mencentang sebuah aktivitas berarti menyertakan DOKUMEN RESMINYA —
     * laporan psikotes dari CAT, atau lembar penilaian yang diunggah asesor —
     * digabung apa adanya.
     *
     * Ringkasan bergaya rancangan (nilai, kategori, catatan yang diketik di
     * sistem) ditawarkan sebagai centang TERSENDIRI di bawahnya. Sebagian tim
     * hanya ingin lembar aslinya, sebagian lagi perlu catatan asesornya juga;
     * memilihkan salah satunya berarti separuh pemakai mencetak halaman yang
     * tidak mereka inginkan.
     */
    private static function babHasil(int $lamaranId): array
    {
        $tahap = DB::table('N_WEB_CAREERS_Lamaran_Tahap')
            ->where('Lamaran_Id', $lamaranId)
            ->orderBy('Urutan')
            ->get();

        $aktivitas = DB::table('N_WEB_CAREERS_Lamaran_Tahap_Tes')
            ->whereIn('Lamaran_Tahap_Id', $tahap->pluck('Id_Lamaran_Tahap')->all() ?: [0])
            ->orderBy('Urutan')
            ->get()
            ->groupBy('Lamaran_Tahap_Id');

        $berkas = self::petaBerkasHasil($lamaranId);
        $seksi = [];

        foreach ($tahap as $t) {
            foreach ($aktivitas->get($t->Id_Lamaran_Tahap, []) as $a) {
                $dariCat = self::dariCat($a);
                $lampiran = $berkas->get($a->Id_Lamaran_Tahap_Tes, collect());

                // Aktivitas yang belum berjalan tidak ditawarkan: mencetak
                // "Offering — BELUM" hanya menambah halaman kosong.
                if (! $dariCat && ! self::adaIsi($a, $lampiran)) {
                    continue;
                }

                $seksi[] = [
                    'kunci' => 'hasil.' . $a->Id_Lamaran_Tahap_Tes,
                    'label' => $a->Label ?: $t->Label,
                    'induk' => $t->Label,
                    'catatan' => self::catatanAktivitas($a, $lampiran->count()),
                    'sumber' => $dariCat ? 'CAT' : 'INTERNAL',
                    'wajib' => false,
                    'terpilih' => true,
                    'berkas' => $lampiran->count(),
                ];

                // Ringkasan penilaian — hanya ditawarkan bila memang ADA yang
                // bisa diringkas. Aktivitas CAT tidak punya catatan asesor;
                // menawarkan halaman kosong untuknya cuma membingungkan.
                if (! $dariCat && self::adaRingkasan($a)) {
                    $seksi[] = [
                        'kunci' => 'hasil.' . $a->Id_Lamaran_Tahap_Tes . '.ringkasan',
                        'label' => 'Ringkasan penilaian',
                        'induk' => ($a->Label ?: $t->Label),
                        'catatan' => 'Nilai, kategori, dan catatan penilai',
                        'anak' => true,
                        'wajib' => false,
                        // Bawaannya TIDAK dicentang: yang diminta bawaan adalah
                        // berkas resminya, bukan ringkasan buatan sistem.
                        'terpilih' => false,
                    ];
                }
            }
        }

        return [
            'kode' => self::BAB_HASIL,
            'label' => 'Hasil Seleksi',
            'bisaUbahJudul' => true,
            'ikon' => 'bi-clipboard-data',
            'seksi' => $seksi,
        ];
    }

    /**
     * Apakah aktivitas ini hasilnya datang dari CAT.
     *
     * Ditentukan dari ADANYA jadwal ujian, bukan dari label. Provider
     * THIRD_PARTY saja tidak cukup — tanpa Penjadwalan_Tahap_Id tidak ada
     * peserta yang bisa ditanyakan hasilnya ke CAT.
     */
    private static function dariCat(object $a): bool
    {
        return ! empty($a->Penjadwalan_Tahap_Id);
    }

    /**
     * Apakah aktivitas ini punya sesuatu untuk diringkas.
     *
     * Nilai, kategori, hasil, atau catatan penilai — salah satunya cukup.
     * Berkas unggahan TIDAK dihitung: berkas dicetak lewat jalurnya sendiri,
     * dan halaman ringkasan yang isinya cuma judul tidak berguna bagi siapa pun.
     */
    private static function adaRingkasan(object $a): bool
    {
        return $a->Nilai !== null
            || trim((string) ($a->Nilai_Teks ?? '')) !== ''
            || trim(strip_tags((string) ($a->Catatan_Html ?? $a->Catatan ?? ''))) !== ''
            || in_array((string) $a->Hasil, ['LULUS', 'GAGAL'], true);
    }

    /** Aktivitas internal baru layak dicetak bila ada nilai, catatan, atau berkas. */
    private static function adaIsi(object $a, $lampiran): bool
    {
        return $a->Nilai !== null
            || trim((string) ($a->Nilai_Teks ?? '')) !== ''
            || trim(strip_tags((string) ($a->Catatan_Html ?? $a->Catatan ?? ''))) !== ''
            || $lampiran->isNotEmpty()
            || in_array((string) $a->Hasil, ['LULUS', 'GAGAL'], true);
    }

    private static function catatanAktivitas(object $a, int $jumlahBerkas): string
    {
        $bagian = array_filter([
            $a->Nilai !== null ? 'Nilai ' . rtrim(rtrim(number_format((float) $a->Nilai, 2, ',', '.'), '0'), ',') : null,
            $a->Nilai_Teks ?: null,
            $a->Hasil ?: null,
            $jumlahBerkas > 0 ? $jumlahBerkas . ' berkas' : null,
        ]);

        return $bagian ? implode(' · ', $bagian) : (string) $a->Status;
    }

    /** Berkas hasil yang diunggah tim, dikelompokkan per aktivitas. */
    private static function petaBerkasHasil(int $lamaranId)
    {
        return DB::table('N_WEB_CAREERS_Lamaran_Tahap_Berkas')
            ->where('Lamaran_Id', $lamaranId)
            ->whereNotNull('Lamaran_Tahap_Tes_Id')
            ->orderBy('Id_Lamaran_Tahap_Berkas')
            ->get()
            ->groupBy('Lamaran_Tahap_Tes_Id');
    }

    // ══ BAB: LAMPIRAN ══════════════════════════════════════════════════════

    /**
     * Seluruh dokumen kandidat — dari formulir maupun unggahan per aktivitas.
     *
     * Dikumpulkan dari dua sumber yang berbeda asalnya tetapi sama perannya di
     * mata pembaca dokumen: berkas yang diunggah lewat formulir (KTP, ijazah,
     * sertifikat) dan berkas yang diunggah kandidat untuk suatu aktivitas.
     */
    private static function babLampiran(int $lamaranId): array
    {
        $seksi = [[
            'kunci' => 'indeks-lampiran',
            'label' => 'Indeks Dokumen',
            'catatan' => 'Daftar seluruh dokumen yang dilampirkan',
            'wajib' => false,
            'terpilih' => true,
        ]];

        foreach (self::daftarLampiran($lamaranId) as $l) {
            $seksi[] = [
                'kunci' => $l['kunci'],
                'label' => $l['label'],
                'catatan' => $l['nama'] . ' · ' . strtoupper($l['ext']),
                'wajib' => false,
                'terpilih' => true,
            ];
        }

        return [
            'kode' => self::BAB_LAMPIRAN,
            'label' => 'Lampiran Dokumen',
            'bisaUbahJudul' => true,
            'ikon' => 'bi-paperclip',
            'seksi' => $seksi,
        ];
    }

    /**
     * Daftar berkas lampiran beserta jalur penyimpanannya.
     *
     * Label dibuat terbaca manusia dari Field_Key ('dok_ktp' → 'Dok Ktp'):
     * skema formulir tidak selalu menyimpan label di tempat yang bisa
     * dijangkau dari sini, dan kunci mentah tidak layak dicetak di dokumen
     * yang dibaca direksi.
     */
    public static function daftarLampiran(int $lamaranId): array
    {
        $hasil = [];

        $formulir = DB::table('N_WEB_CAREERS_Formulir_Berkas as fb')
            ->join('N_WEB_CAREERS_Formulir_Pengisian as fp', 'fp.Id_Formulir_Pengisian', '=', 'fb.Formulir_Pengisian_Id')
            ->where('fp.Lamaran_Id', $lamaranId)
            ->orderBy('fb.Id_Formulir_Berkas')
            ->select('fb.Id_Formulir_Berkas', 'fb.Field_Key', 'fb.Nama_Asli', 'fb.Path_File',
                'fb.Ekstensi', 'fb.Ukuran_Byte', 'fb.Bagian_Key', 'fb.Baris_Index',
                'fp.Id_Formulir_Pengisian', 'fp.Schema_Snapshot_Json', 'fp.Jawaban_Json')
            ->get();

        // Peta label dibangun sekali per pengisian: satu lamaran bisa punya
        // sepuluh berkas dari formulir yang sama, dan mengurai JSON snapshot
        // untuk tiap berkas berarti mengurai dokumen yang sama sepuluh kali.
        $petaLabel = [];

        foreach ($formulir as $b) {
            $idp = (int) $b->Id_Formulir_Pengisian;
            $petaLabel[$idp] ??= self::petaLabelBerkas($b->Schema_Snapshot_Json, $b->Jawaban_Json);

            $hasil[] = [
                'kunci' => 'lampiran.form.' . $b->Id_Formulir_Berkas,
                'label' => self::labelLampiran($petaLabel[$idp], $b),
                'nama' => $b->Nama_Asli,
                'path' => $b->Path_File,
                'ext' => strtolower((string) $b->Ekstensi),
                'ukuran' => (int) $b->Ukuran_Byte,
            ];
        }

        $aktivitas = DB::table('N_WEB_CAREERS_Lamaran_Tes_Berkas')
            ->where('Lamaran_Id', $lamaranId)
            ->orderBy('Id_Lamaran_Tes_Berkas')
            ->get();

        foreach ($aktivitas as $b) {
            $hasil[] = [
                'kunci' => 'lampiran.tes.' . $b->Id_Lamaran_Tes_Berkas,
                'label' => 'Unggahan Kandidat',
                'nama' => $b->Nama_File,
                'path' => $b->Path_File,
                'ext' => strtolower((string) $b->Ext),
                'ukuran' => (int) $b->Ukuran,
            ];
        }

        return $hasil;
    }

    /**
     * Nama lampiran sebagaimana ditanyakan formulirnya.
     *
     * -- KENAPA BUKAN DARI Field_Key ---------------------------------------
     *
     * Kunci teknis yang dipercantik hanya menghasilkan "Dok Ktp", "Sert File"
     * -- potongan nama kolom, bukan pertanyaan yang dijawab kandidat. Yang
     * lebih buruk: DUA sertifikat yang diunggah pada dua baris riwayat berbeda
     * sama-sama berlabel "Sert File", sehingga tidak ada cara tahu yang mana
     * milik pelatihan mana.
     *
     * Snapshot skema menyimpan labelnya ("CV", "KTP", "Kartu Keluarga",
     * "Ijazah / Surat Keterangan Lulus"), dan untuk berkas di dalam bagian
     * BERULANG, jawaban baris itu menyimpan nama entrinya -- itulah yang
     * membedakan satu sertifikat dari yang lain.
     */
    private static function labelLampiran(array $peta, object $b): string
    {
        $field = (string) ($b->Field_Key ?? '');
        $label = $peta['label'][$field] ?? self::labelField($field);

        $baris = $b->Baris_Index;
        $bagian = (string) ($b->Bagian_Key ?? '');

        if ($baris === null || $bagian === '') {
            return $label;
        }

        // Nama entri riwayatnya -- "sertifikat 1", "PT Maju Jaya". Jauh lebih
        // berguna daripada nomor urut: nomor hanya memberi tahu bahwa ada dua,
        // sementara namanya memberi tahu yang mana.
        $judul = $peta['baris'][$bagian][(int) $baris] ?? null;

        if ($judul !== null && trim($judul) !== '') {
            return $label . ' - ' . trim($judul);
        }

        return BerkasBaris::label($label, (int) $baris);
    }

    /**
     * Peta label field & nama tiap baris berulang, dari snapshot + jawaban.
     *
     * @return array{label: array<string,string>, baris: array<string, array<int,string>>}
     */
    private static function petaLabelBerkas(?string $snapshot, ?string $jawaban): array
    {
        $peta = ['label' => [], 'baris' => []];

        $skema = json_decode($snapshot ?: '', true);

        if (! is_array($skema)) {
            return $peta;
        }

        // Kolom mana di tiap bagian berulang yang layak jadi nama barisnya.
        $kolomNama = [];

        foreach ($skema['langkah'] ?? [] as $langkah) {
            foreach ($langkah['bagian'] ?? [] as $bagian) {
                $kunciBagian = (string) ($bagian['key'] ?? '');

                foreach ($bagian['field'] ?? [] as $f) {
                    $key = (string) ($f['key'] ?? '');
                    $label = trim((string) ($f['label'] ?? ''));

                    if ($key === '' || $label === '') {
                        continue;
                    }

                    $peta['label'][$key] ??= $label;

                    // Kolom pertama yang BUKAN berkas dipakai menamai baris --
                    // pada riwayat sertifikasi itu "Nama Pelatihan/Sertifikasi",
                    // pada riwayat kerja "Nama Perusahaan". Mengandalkan nama
                    // kolomnya sendiri akan gagal begitu perancang menamainya
                    // lain; urutan lebih tahan.
                    if (
                        $kunciBagian !== ''
                        && ! empty($bagian['berulang'])
                        && ! isset($kolomNama[$kunciBagian])
                        && ($f['tipe'] ?? '') !== 'file'
                    ) {
                        $kolomNama[$kunciBagian] = $key;
                    }
                }
            }
        }

        $isi = json_decode($jawaban ?: '', true);

        if (! is_array($isi)) {
            return $peta;
        }

        foreach ($kolomNama as $kunciBagian => $kolom) {
            foreach ($isi[$kunciBagian] ?? [] as $i => $baris) {
                if (is_array($baris) && isset($baris[$kolom])) {
                    $peta['baris'][$kunciBagian][(int) $i] = (string) $baris[$kolom];
                }
            }
        }

        return $peta;
    }

    /** Cadangan bila skema tidak menyebut labelnya: dok_ktp jadi "Dok Ktp". */
    private static function labelField(?string $key): string
    {
        $bersih = trim(str_replace(['_', '-'], ' ', (string) $key));

        return $bersih === '' ? 'Dokumen' : ucwords($bersih);
    }

    // ══ PERAKITAN ISI ══════════════════════════════════════════════════════

    /**
     * Ambil hasil tes CAT untuk seluruh aktivitas terpilih.
     *
     * Dipanggil job cetak, BUKAN layar. Satu panggilan jaringan per peserta
     * penjadwalan, dan hasilnya bisa memuat beberapa laporan sekaligus (satu
     * ujian gabungan menghasilkan TIU dan Kraeplin sekaligus).
     *
     * @param  list<int>  $aktivitasIds  id Lamaran_Tahap_Tes yang dicentang
     * @return array<int, list<array>>  dipetakan per id aktivitas
     */
    public static function hasilCat(int $lamaranId, array $aktivitasIds, ?int $penggunaId = null): array
    {
        if (! $aktivitasIds) {
            return [];
        }

        $aktivitas = DB::table('N_WEB_CAREERS_Lamaran_Tahap_Tes')
            ->whereIn('Id_Lamaran_Tahap_Tes', $aktivitasIds)
            ->whereNotNull('Penjadwalan_Tahap_Id')
            ->get(['Id_Lamaran_Tahap_Tes', 'Penjadwalan_Tahap_Id']);

        if ($aktivitas->isEmpty()) {
            return [];
        }

        // Peserta penjadwalan milik lamaran ini — nomor inilah yang dikenali
        // CAT sebagai Id_WC_Penjadwalan_Peserta.
        $peserta = DB::table('N_WEB_CAREERS_Penjadwalan_Peserta')
            ->where('Lamaran_Id', $lamaranId)
            ->whereIn('Penjadwalan_Tahap_Id', $aktivitas->pluck('Penjadwalan_Tahap_Id')->all())
            ->orderByDesc('Id_Penjadwalan_Peserta')
            ->get(['Id_Penjadwalan_Peserta', 'Penjadwalan_Tahap_Id'])
            // Dijadwalkan ulang → beberapa baris untuk satu tahap. Yang
            // TERBARU yang dipakai; percobaan lama sudah digantikan.
            ->unique('Penjadwalan_Tahap_Id')
            ->keyBy('Penjadwalan_Tahap_Id');

        $peta = [];
        $singgah = [];

        foreach ($aktivitas as $a) {
            $p = $peserta->get($a->Penjadwalan_Tahap_Id);

            if (! $p) {
                continue;
            }

            $id = (int) $p->Id_Penjadwalan_Peserta;

            // Dua aktivitas bisa menunjuk peserta yang sama (paket gabungan);
            // CAT cukup ditanya sekali.
            $singgah[$id] ??= LaporanTesClient::ambil($id, $penggunaId);

            if (! $singgah[$id]) {
                continue;
            }

            $peta[(int) $a->Id_Lamaran_Tahap_Tes] = $singgah[$id];
        }

        return $peta;
    }

    /**
     * Unduh isi satu berkas dari penyimpanan.
     *
     * Null bila gagal — pemanggil menggantinya dengan halaman penanda. Berkas
     * yang hilang dari penyimpanan tidak boleh menggugurkan seluruh cetakan.
     */
    public static function isiBerkas(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        try {
            $disk = Storage::disk(GcsBerkas::DISK);

            return $disk->exists($path) ? $disk->get($path) : null;
        } catch (\Throwable $e) {
            Log::channel('web_career')->warning('[BERKAS-SELEKSI] berkas gagal diunduh', [
                'path' => $path,
                'pesan' => $e->getMessage(),
            ]);

            return null;
        }
    }

    // ══ UTILITAS ═══════════════════════════════════════════════════════════

    private static function lamaran(int $lamaranId): ?object
    {
        return DB::table('N_WEB_CAREERS_Lamaran as l')
            ->leftJoin('N_WEB_CAREERS_Users as u', 'u.Id_Users', '=', 'l.Id_Users')
            ->leftJoin('N_WEB_CAREERS_Program as p', 'p.Id_Program', '=', 'l.Program_Id')
            ->leftJoin('N_WEB_CAREERS_Program_Posisi as x', 'x.Id_Program_Posisi', '=', 'l.Program_Posisi_Id')
            ->where('l.Id_Lamaran', $lamaranId)
            ->select('l.Kode', 'u.Nama as NamaKandidat', 'p.Nama as ProgramNama', 'x.Posisi')
            ->first();
    }

    private static function tanggal(?string $waktu): string
    {
        if (! $waktu) {
            return '-';
        }

        try {
            return \Carbon\Carbon::parse($waktu)->translatedFormat('d M Y H:i');
        } catch (\Throwable $e) {
            return (string) $waktu;
        }
    }
}
