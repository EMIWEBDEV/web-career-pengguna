<?php

namespace App\Support\Career;

use Illuminate\Support\Facades\DB;

/**
 * WEB CAREER — METRIK REKRUTMEN (sumber kebenaran tunggal untuk hitungan pipeline).
 *
 * Ekspresi di sini dulunya `private static` di dalam MonitoringController, jadi
 * halaman lain yang butuh angka yang SAMA tidak punya pilihan selain menyalinnya.
 * Begitu disalin, dua halaman akan menjawab "berapa pelamar di tahap 3?" dengan
 * angka berbeda begitu salah satu salinan diperbaiki — dan tidak ada yang tahu
 * mana yang benar. Karena itu ekspresinya DIPINDAH ke sini (bukan digandakan):
 * MonitoringController dan DashboardController sama-sama memanggil kelas ini.
 *
 * Isinya sengaja hanya potongan SQL & aritmetika murni — tanpa kueri jadi —
 * supaya tiap pemanggil tetap bebas menyusun scope-nya sendiri (per program,
 * per kategori, per posisi) tanpa kelas ini ikut menebak kebutuhan mereka.
 */
class MetrikRekrutmen
{
    /**
     * Derived table: satu baris per lamaran + UrutanDisplay (tahap yang
     * mewakili) — mirror SQL dari PipelineProgress::tahapKini(). Aturannya
     * diturunkan dari FLAG master (lihat HasilKeputusan), bukan daftar kode:
     *  Flag_Lolos='Y' → tahap terakhir;
     *  terminal lain  → tahap tempat keputusannya dicatat (Hasil = Status),
     *                   fallback tahap terakhir;
     *  selain itu     → tahap BERJALAN (fallback tahap pertama).
     * LEFT JOIN + COALESCE(...,1): lamaran tanpa tahap masuk kolom 1.
     *
     * $kolomTambahan menambah kolom `l.*` ke SELECT sekaligus ke GROUP BY —
     * aman karena l.Id_Lamaran sudah jadi kunci grup (unik per baris), jadi
     * kolom lain milik lamaran yang sama tidak bisa memecah grupnya. Dipakai
     * dashboard MT untuk memecah funnel per Program_Posisi_Id.
     */
    /**
     * Sama seperti sqlUrutanDisplay(), TAPI juga membawa KODE tahap yang
     * mewakili — identitasnya, bukan cuma nomor urutnya.
     *
     * KENAPA PERLU
     * Funnel & papan menyusun kolomnya dari master alur, lalu memasangkan
     * angka lewat nomor urut. Itu benar hanya selama alur tak pernah berubah.
     * Begitu program diarahkan ke alur lain — atau alurnya disunting di tempat,
     * yang memakai ULANG baris per urutan — "tahap ke-3" milik kandidat dan
     * "kolom ke-3" di layar bisa dua hal yang sama sekali berbeda. Angkanya
     * tetap muncul, hanya menempel di tahap yang salah: kegagalan yang tidak
     * menimbulkan satu galat pun, dan justru karena itu tidak pernah ketahuan.
     *
     * Dibungkus, bukan diubah di tempat: agregat lain masih memakai bentuk
     * aslinya, dan menyisipkan JOIN ke dalamnya akan memaksa mereka ikut
     * menanggung biayanya tanpa memerlukan hasilnya.
     */
    public static function sqlUrutanDisplayBerkode(string $whereLamaran, array $kolomTambahan = []): string
    {
        $dalam = self::sqlUrutanDisplay($whereLamaran, $kolomTambahan);

        return "SELECT d.*, lt2.Kode AS KodeDisplay
                FROM ({$dalam}) d
                LEFT JOIN N_WEB_CAREERS_Lamaran_Tahap lt2
                       ON lt2.Lamaran_Id = d.Id_Lamaran
                      AND lt2.Urutan     = d.UrutanDisplay";
    }

    public static function sqlUrutanDisplay(string $whereLamaran, array $kolomTambahan = []): string
    {
        $extra = $kolomTambahan ? ', '.implode(', ', $kolomTambahan) : '';

        // Pembagiannya dari FLAG master, bukan daftar kode mati. Dulu CASE di
        // sini hanya mengenal GUGUR/TALENT_POOL/LULUS, sehingga outcome lain
        // (kandidat mundur, menolak penawaran) jatuh ke cabang ELSE dan —
        // karena tak punya tahap BERJALAN — berakhir di MIN(lt.Urutan), yaitu
        // tahap 1. Orang yang menolak penawaran di tahap akhir muncul di papan
        // sebagai pelamar baru di Pendaftaran: kegagalan yang tidak menimbulkan
        // satu galat pun, dan justru karena itu tidak pernah ketahuan.
        //
        // Cabang ELSE sengaja tetap berarti "masih berproses": status yang
        // belum dikenal master (mis. DRAFT) harus berperilaku seperti dulu,
        // bukan dilempar ke tahap terakhir.
        $lolos = HasilKeputusan::sqlIn(HasilKeputusan::kodeLolos());
        $tidakLolos = HasilKeputusan::sqlIn(HasilKeputusan::kodeTidakLolos());

        return "SELECT l.Id_Lamaran, l.Program_Id, l.Status{$extra},
                       COALESCE(CASE
                           WHEN l.Status IN ({$lolos})      THEN MAX(lt.Urutan)
                           WHEN l.Status IN ({$tidakLolos}) THEN COALESCE(
                                    MIN(CASE WHEN lt.Hasil = l.Status THEN lt.Urutan END),
                                    MAX(lt.Urutan))
                           ELSE COALESCE(MIN(CASE WHEN lt.Status = 'BERJALAN' THEN lt.Urutan END), MIN(lt.Urutan))
                       END, 1) AS UrutanDisplay
                FROM N_WEB_CAREERS_Lamaran l
                LEFT JOIN N_WEB_CAREERS_Lamaran_Tahap lt ON lt.Lamaran_Id = l.Id_Lamaran
                WHERE {$whereLamaran}
                GROUP BY l.Id_Lamaran, l.Program_Id, l.Status{$extra}";
    }

    /**
     * UMUR TAHAP — hari sejak tahap mulai dijalani.
     *
     * PENTING: Lamaran_Tahap.Waktu_Mulai TIDAK ditulis saat tahap maju
     * (LamaranService::tetapkanTahap hanya menyetel Status + Updated_At; yang
     * mengisi Waktu_Mulai cuma simpanPengisian()). Jadi untuk sebagian besar
     * tahap kolom itu NULL dan COALESCE ke Created_At-lah yang bekerja —
     * Created_At = saat lamaran dibuat, karena seluruh baris tahap dicetak
     * sekaligus di awal. Artinya angka ini adalah "umur sejak melamar" untuk
     * tahap yang belum pernah diisi formulir, bukan "umur di tahap ini".
     * Jangan diperbaiki di sini: perbaikannya ada di penulisan Waktu_Mulai.
     */
    public static function sqlUmurTahap(string $alias = 'lt'): string
    {
        return "DATEDIFF(day, COALESCE({$alias}.Waktu_Mulai, {$alias}.Created_At), GETDATE())";
    }

    /**
     * AGING — dua jam berbeda, dipilih menurut siapa yang sedang ditunggu:
     *  - Siap_Diputus = 'Y' → mesin sudah selesai, yang menggantung adalah
     *    KEPUTUSAN MANUSIA. Jamnya mulai dari Rekomendasi_At.
     *  - selain itu       → yang ditunggu prosesnya sendiri (kandidat mengisi,
     *    penyedia tes menilai). Jamnya mulai dari tahap mulai dijalani.
     *
     * Menyatukan keduanya jadi satu DATEDIFF akan menyalahkan admin atas
     * tunggu yang bukan urusannya, atau sebaliknya menyembunyikan keputusan
     * yang sudah seminggu didiamkan di balik tahap yang baru dimulai.
     */
    public static function sqlAging(string $alias = 'lt'): string
    {
        return "CASE WHEN {$alias}.Siap_Diputus = 'Y'
                     THEN DATEDIFF(day, COALESCE({$alias}.Rekomendasi_At, {$alias}.Updated_At, {$alias}.Created_At), GETDATE())
                     ELSE ".self::sqlUmurTahap($alias).' END';
    }

    /**
     * GILIRAN ADMIN — apakah tahap ini tidak akan bergerak sampai admin bertindak.
     *
     * Ini bukan tebakan per kode tahap melainkan turunan dari master, supaya
     * tipe tahap baru ikut terbaca sendiri tanpa menyentuh kode:
     *
     *   Perilaku_Kode = 'CAT'    → ujian online. Yang menilai sistem, TAPI
     *       jadwalnya dibuat admin. Jadi giliran admin hanya selama
     *       Penjadwalan_Tahap_Id masih NULL; begitu terjadwal, yang ditunggu
     *       kandidat mengerjakan & penyedia menilai.
     *   Perilaku_Kode = 'MANUAL' + Flag_Formulir <> 'Y' → wawancara, MCU,
     *       screening, penawaran. Tidak ada mesin yang akan menyelesaikannya;
     *       giliran admin sejak detik tahap itu berjalan.
     *   Perilaku_Kode = 'MANUAL' + Flag_Formulir = 'Y' → tahap berformulir.
     *       Selama isian kandidat belum masuk (Formulir_Pengisian_Id NULL) yang
     *       ditunggu KANDIDAT, bukan admin. Setelah masuk, giliran admin
     *       memverifikasi.
     *
     * Pengecualian terakhir itulah yang membuat angkanya layak dipercaya:
     * tanpa itu setiap tahap formulir yang baru dibuka akan tampil sebagai
     * "kamu belum mengerjakan ini", dan admin berhenti mempercayai lencananya.
     *
     * Tahap yang Siap_Diputus = 'Y' TIDAK dikecualikan di sini — pemanggil yang
     * memutuskan, karena "siap diputus" punya keranjangnya sendiri.
     *
     * @param  string  $lt  alias N_WEB_CAREERS_Lamaran_Tahap
     * @param  string  $mtt  alias N_WEB_CAREERS_Master_Tipe_Tahap (LEFT JOIN via Kode)
     */
    public static function sqlGiliranAdmin(string $lt = 'lt', string $mtt = 'mtt'): string
    {
        // COALESCE: tahap lama bisa punya Tipe_Tahap_Kode NULL sehingga tidak
        // ketemu barisnya di master. Diperlakukan MANUAL non-formulir — lebih
        // baik muncul dan diabaikan daripada hilang tanpa ada yang tahu.
        $perilaku = "COALESCE({$mtt}.Perilaku_Kode, 'MANUAL')";
        $formulir = "COALESCE({$mtt}.Flag_Formulir, 'T')";

        return "(
            ({$perilaku} = 'CAT' AND {$lt}.Penjadwalan_Tahap_Id IS NULL)
         OR ({$perilaku} = 'MANUAL' AND {$formulir} <> 'Y')
         OR ({$perilaku} = 'MANUAL' AND {$formulir} = 'Y' AND {$lt}.Formulir_Pengisian_Id IS NOT NULL)
        )";
    }

    /**
     * Predikat SQL "kandidat TIDAK sedang ditahan" — NULL-safe.
     *
     * HOLD_Flag NULL diperlakukan sebagai 'T' (tidak ditahan): tanpa COALESCE,
     * `Hold_Flag <> 'Y'` bernilai NULL (bukan TRUE) untuk baris NULL dalam logika
     * tiga-nilai SQL, sehingga baris itu diam-diam berhenti terhitung sebagai
     * "aktif" — kebalikan dari yang diinginkan. Bug ini sudah ditemukan berulang
     * kali di berbagai tempat; helper ini mencegahnya terjadi lagi.
     *
     * @param  string  $lt  alias tabel N_WEB_CAREERS_Lamaran_Tahap
     */
    public static function sqlBukanDitahan(string $lt = 'lt'): string
    {
        return "COALESCE({$lt}.Hold_Flag, 'T') <> 'Y'";
    }

    /*
    |--------------------------------------------------------------------------
    | SIAPA YANG DITUNGGU — DIBACA DARI AKTIVITAS, BUKAN DARI KOLOM TAHAP
    |--------------------------------------------------------------------------
    |
    | `Lamaran_Tahap.Provider` adalah RINGKASAN yang membuang informasi. Ia
    | disusun MasterAlurController dengan aturan "ada satu aktivitas online →
    | seluruh tahap THIRD_PARTY". Untuk tahap satu-aktivitas itu benar; untuk
    | tahap "FGD + Psikotes + Wawancara" ia berbohong: dua aktivitas yang
    | dikerjakan tim ikut tercap ujian online.
    |
    | Akibatnya seluruh papan operasional salah menghitung. Tahap yang FGD-nya
    | sudah berlangsung dan tinggal dicatat hasilnya masuk keranjang "Menunggu
    | Tes" — keranjang yang artinya "tidak ada yang bisa kita lakukan, tunggu
    | kandidat" — sehingga pekerjaan yang sudah menumpuk tidak pernah muncul di
    | antrean siapa pun. Tidak ada galat, hanya angka yang keliru diam-diam.
    |
    | Predikat di bawah membaca `Lamaran_Tahap_Tes` langsung, dengan urutan
    | kepentingan YANG SAMA PERSIS dengan PipelineReadModel::bucket():
    | aktivitas tim menang atas aktivitas online. Keduanya wajib sepakat —
    | papan yang menghitung berbeda dari worklist adalah cacat yang paling
    | mahal dicari, karena kedua angka sama-sama terlihat masuk akal.
    */

    /**
     * BATAS 2100 PARAMETER SQL SERVER — dan kenapa ia selalu ditemukan
     * PALING TERLAMBAT.
     *
     * `whereIn` menerbitkan satu parameter per nilai. Program berisi 300
     * pelamar sudah menghasilkan 2100 id tahap, dan pada baris ke-2101 driver
     * menolak SELURUH kueri:
     *
     *     SQLSTATE[IMSSP]: Tried to bind parameter number 2101.
     *
     * Yang membuatnya berbahaya: ia lolos di semua program kecil, lolos di
     * seluruh pengujian, lalu meledak justru pada program terbesar — yang
     * paling ramai dibuka dan paling mahal kalau tidak bisa dibuka.
     *
     * Dipotong 1000, bukan 2100: kueri yang sama masih membawa parameter lain
     * (saringan status, kategori, tanggal), dan batas itu berlaku untuk
     * SELURUH parameter dalam satu perintah, bukan untuk whereIn-nya saja.
     * Sisanya ruang bernapas supaya penambahan satu saringan kelak tidak
     * menghidupkan kembali kesalahan yang sama.
     *
     * `$bangun` sebuah closure, bukan builder jadi: builder yang sama tidak
     * boleh dipakai ulang antar potongan — whereIn-nya akan menumpuk dan
     * potongan kedua justru meminta gabungan keduanya.
     *
     * @param  \Closure(): \Illuminate\Database\Query\Builder  $bangun
     * @param  array  $nilai  isi klausa IN — boleh berapa pun banyaknya
     */
    public static function potongIn(\Closure $bangun, string $kolom, array $nilai, int $perPotong = self::MAKS_IN): \Illuminate\Support\Collection
    {
        $nilai = array_values(array_unique($nilai));
        if (! $nilai) {
            return collect();
        }

        $hasil = collect();
        foreach (array_chunk($nilai, max(1, $perPotong)) as $potong) {
            $hasil = $hasil->concat($bangun()->whereIn($kolom, $potong)->get());
        }

        return $hasil;
    }

    /** Alias bawaan derived table aktivitas — lihat denganAktivitas(). */
    public const ALIAS_AKTIVITAS = 'ak';

    /** Sekali kirim ke SQL Server, dengan ruang untuk parameter lain. */
    public const MAKS_IN = 1000;

    /**
     * Aktivitas tahap + kolom `Token_Terbit` — kueri baku untuk worklist,
     * papan Monitoring, dan siapa pun yang memanggil PipelineReadModel::bucket()
     * atau PipelineProgress::state().
     *
     * ══ KENAPA TOKEN, BUKAN CUKUP TAUTAN JADWAL ══
     *
     * `Lamaran_Tahap_Tes.Penjadwalan_Tahap_Id` berarti "aktivitas ini sudah
     * DIIKUTKAN ke sebuah sesi", bukan "sesinya sudah jadi". Di antara keduanya
     * ada jeda nyata: penerbitan token ke HCLearn berjalan di antrean, dan bisa
     * gagal. Selama jeda itu tautannya sudah ada tapi kandidat belum punya apa
     * pun untuk dibuka.
     *
     * Portal kandidat sudah memakai ukuran yang benar — `ujian.terjadwal`
     * dihitung dari ada-tidaknya Short_Token/Link_Ujian pada baris pesertanya.
     * Sisi admin dulu memakai ukuran yang lebih longgar, sehingga saat token
     * gagal terbit admin membaca "menunggu hasil" (seolah kandidat sedang
     * ujian) padahal kandidat membaca "menunggu dijadwalkan". Dua layar,
     * dua cerita, tanpa satu pun galat.
     *
     * Baris peserta unik per (Penjadwalan_Tahap_Id, Lamaran_Id) — sudah
     * diperiksa terhadap data — jadi LEFT JOIN ini tidak menggandakan baris.
     *
     * TIDAK ADA padanannya di sqlAktivitasTahap(), dan itu disengaja: sisi SQL
     * hanya memilah "menunggu tes" dari "giliran tim", dan kedua keadaan
     * (menunggu jadwal / menunggu hasil) sama-sama jatuh ke "menunggu tes".
     * Menambahkan join token di sana hanya memperberat kueri agregat tanpa
     * mengubah satu pun angkanya.
     *
     * @param  array  $lamaranTahapIds  id N_WEB_CAREERS_Lamaran_Tahap
     */
    public static function aktivitasDenganToken(array $lamaranTahapIds)
    {
        return self::dasarAktivitasToken()->whereIn('st.Lamaran_Tahap_Id', $lamaranTahapIds ?: [0]);
    }

    /**
     * Aktivitas SELURUH tahap yang diminta, DIKELOMPOKKAN per tahap — aman
     * untuk berapa pun banyaknya id.
     *
     * Inilah bentuk yang dipakai worklist dan Monitoring. Keduanya dulu
     * memanggil aktivitasDenganToken() lalu ->get() sendiri, dan program
     * berisi ratusan pelamar menerbitkan lebih dari 2100 id tahap — batas
     * parameter SQL Server. Papannya tidak melambat, melainkan gagal dimuat
     * sama sekali.
     */
    public static function aktivitasDenganTokenPer(array $lamaranTahapIds): \Illuminate\Support\Collection
    {
        return self::potongIn(
            fn () => self::dasarAktivitasToken(),
            'st.Lamaran_Tahap_Id',
            $lamaranTahapIds,
        )->groupBy('Lamaran_Tahap_Id');
    }

    /** Kueri dasarnya — dibangun ulang tiap potongan, tanpa saringan id. */
    private static function dasarAktivitasToken()
    {
        return DB::table('N_WEB_CAREERS_Lamaran_Tahap_Tes as st')
            ->join('N_WEB_CAREERS_Lamaran_Tahap as lth', 'lth.Id_Lamaran_Tahap', '=', 'st.Lamaran_Tahap_Id')
            ->leftJoin('N_WEB_CAREERS_Penjadwalan_Peserta as pp', function ($j) {
                $j->on('pp.Penjadwalan_Tahap_Id', '=', 'st.Penjadwalan_Tahap_Id')
                    ->on('pp.Lamaran_Id', '=', 'lth.Lamaran_Id');
            })
            ->orderBy('st.Urutan')
            ->selectRaw("st.*, CASE WHEN pp.Short_Token IS NOT NULL OR pp.Link_Ujian IS NOT NULL THEN 'Y' ELSE 'T' END AS Token_Terbit");
    }

    /**
     * Derived table: satu baris per TAHAP, meringkas aktivitas yang sedang
     * berarti di dalamnya.
     *
     *   Semua      berapa baris aktivitas dimiliki tahap ini (0 = data
     *              pra-mesin multi-tes, dan itu dibedakan dari "ada tapi
     *              semuanya sudah selesai");
     *   AdaDaring  ada ujian online yang sedang berarti;
     *   AdaTim     ada aktivitas yang dikerjakan/dicatat tim, sedang berarti.
     *
     * ══ KENAPA DERIVED TABLE, BUKAN EXISTS ══
     *
     * Versi pertama menulisnya sebagai `EXISTS (...)` yang disisipkan langsung
     * ke dalam `SUM(CASE WHEN ...)`. SQL Server MENOLAKNYA: "Cannot perform an
     * aggregate function on an expression containing an aggregate or a
     * subquery." Ditangkap saat verifikasi, bukan di production.
     *
     * ══ MODE URUTAN IKUT DIHORMATI ══
     *
     * Pada tahap yang aktivitasnya dikunci berurutan, hanya aktivitas TERDEPAN
     * yang sedang berarti — wawancara di posisi ketiga belum boleh dikerjakan
     * siapa pun selama FGD belum selesai, jadi ia tidak boleh ikut menentukan
     * keranjang. Aturannya dibaca dari `Master_Mode_Urutan.Flag_Berurutan`,
     * bukan dari membandingkan kode dengan 'BERURUTAN', supaya mode ketiga yang
     * ditambahkan lewat master ikut terbaca tanpa menyentuh berkas ini.
     *
     * Yang terdepan dicari lewat WINDOW FUNCTION, bukan subkueri berkorelasi —
     * selain lebih murah (satu pemindaian), ia juga satu-satunya bentuk yang
     * boleh berdiri di dalam agregat.
     *
     * Terdepan dihitung dari SELURUH aktivitas — termasuk yang disembunyikan
     * dari kandidat seperti background check — karena yang menahan giliran
     * adalah kenyataan alurnya, bukan apa yang terlihat.
     */
    public static function sqlAktivitasTahap(): string
    {
        // PERAN TIDAK IKUT MENYARING — persis seperti PipelineReadModel::bucket().
        // Peran menjawab "apakah aktivitas ini menentukan lulus/gagal", bukan
        // "apakah ada yang harus dikerjakan"; psikotes INFORMATIF tetap harus
        // dijadwalkan dan dicatat. Kedua sisi WAJIB memakai aturan yang sama —
        // begitu salah satu menyaring dan yang lain tidak, papan dan worklist
        // menjawab berbeda untuk kandidat yang sama.
        $berarti = "COALESCE(w.Flag_Selesai, 'N') <> 'Y'
                AND (w.Berurutan <> 'Y' OR w.Urutan = w.UrutanTerdepan)";

        return "SELECT w.Lamaran_Tahap_Id,
                       COUNT(*) AS Semua,
                       MAX(CASE WHEN {$berarti} AND w.Provider = 'THIRD_PARTY' THEN 1 ELSE 0 END) AS AdaDaring,
                       MAX(CASE WHEN {$berarti} AND COALESCE(w.Provider, 'INTERNAL') <> 'THIRD_PARTY' THEN 1 ELSE 0 END) AS AdaTim
                  FROM (
                        SELECT st.Lamaran_Tahap_Id, st.Provider, st.Peran, st.Urutan, st.Flag_Selesai,
                               COALESCE(mu.Flag_Berurutan, 'T') AS Berurutan,
                               MIN(CASE WHEN COALESCE(st.Flag_Selesai, 'N') <> 'Y' THEN st.Urutan END)
                                   OVER (PARTITION BY st.Lamaran_Tahap_Id) AS UrutanTerdepan
                          FROM N_WEB_CAREERS_Lamaran_Tahap_Tes st
                          JOIN N_WEB_CAREERS_Lamaran_Tahap lt2
                            ON lt2.Id_Lamaran_Tahap = st.Lamaran_Tahap_Id
                          LEFT JOIN N_WEB_CAREERS_Master_Mode_Urutan mu
                            ON mu.Kode = lt2.Urutan_Aktivitas
                       ) w
                 GROUP BY w.Lamaran_Tahap_Id";
    }

    /**
     * Pasang derived table aktivitas ke sebuah kueri.
     *
     * Disediakan sebagai helper, bukan diserahkan ke tiap pemanggil menulis
     * JOIN-nya sendiri: predikat di bawah TIDAK BERARTI tanpa join ini, dan
     * lupa memasangnya menghasilkan galat "invalid column name" — bukan diam,
     * tapi tetap saja lebih baik tidak bisa lupa.
     *
     * @param  \Illuminate\Database\Query\Builder  $q
     */
    public static function denganAktivitas($q, string $lt = 'lt', string $alias = self::ALIAS_AKTIVITAS)
    {
        return $q->leftJoin(
            DB::raw('('.self::sqlAktivitasTahap().') AS '.$alias),
            $alias.'.Lamaran_Tahap_Id',
            '=',
            $lt.'.Id_Lamaran_Tahap'
        );
    }

    /**
     * MENUNGGU TES: yang tersisa untuk tahap ini hanya ujian online kandidat.
     *
     * Aktivitas tim MENANG — kalau ada yang bisa dikerjakan tim sekarang,
     * itulah yang harus muncul di antrean, bukan penantian atas ujian kandidat.
     * Urutan kepentingan ini sama persis dengan PipelineReadModel::bucket().
     *
     * Tahap tanpa satu pun baris aktivitas (lamaran pra-mesin multi-tes) jatuh
     * ke kolom ringkasan `Lamaran_Tahap.Provider`: di situ memang tidak ada
     * informasi yang lebih baik, dan perilakunya sama persis seperti sebelumnya.
     */
    public static function sqlMenungguTes(string $lt = 'lt', string $alias = self::ALIAS_AKTIVITAS): string
    {
        return "(
            (COALESCE({$alias}.AdaDaring, 0) = 1 AND COALESCE({$alias}.AdaTim, 0) = 0)
            OR ({$alias}.Lamaran_Tahap_Id IS NULL AND {$lt}.Provider = 'THIRD_PARTY')
        )";
    }

    /**
     * GILIRAN TIM — kebalikan tepat dari sqlMenungguTes().
     *
     * Ditulis sebagai NEGASI, bukan sebagai daftar syaratnya sendiri. Dengan
     * begitu keduanya dijamin SALING MENIADAKAN sekaligus MENUTUPI seluruhnya:
     * tiap tahap BERJALAN jatuh ke tepat satu keranjang.
     *
     * Itu bukan kerapian belaka. Dashboard menghitung lencana lewat CASE
     * ber-`ELSE 'MACET'` dan mengisi keranjangnya lewat WHERE; begitu keduanya
     * tidak persis berkebalikan, layar menampilkan "0 baris" di sebelah lencana
     * bertuliskan angka besar — dan tak ada yang tahu mana yang benar. Versi
     * pertama helper ini menyisakan celah tepat seperti itu untuk tahap yang
     * seluruh aktivitasnya sudah selesai.
     */
    public static function sqlGiliranTim(string $lt = 'lt', string $alias = self::ALIAS_AKTIVITAS): string
    {
        return '(NOT '.self::sqlMenungguTes($lt, $alias).')';
    }

    /** Ambang "macet" (hari) — tahap BERJALAN lebih lama dari ini dianggap tersendat. */
    public static function macetHari(): int
    {
        return (int) config('career_monitoring.macet_hari', 7);
    }

    /** Ambang sorot merah untuk keputusan yang menggantung (hari). */
    public static function sorotHari(): int
    {
        return (int) config('career_monitoring.siap_diputus_sorot_hari', 2);
    }

    /**
     * SKOR KESEHATAN 0-100 dari agregat tahap BERJALAN satu program.
     *
     * Dihukum oleh dua hal saja, dan keduanya memang salah admin:
     *  - macet   : tahap berjalan yang lewat ambang tanpa siap diputus;
     *  - siapTua : keputusan yang sudah siap tapi didiamkan.
     * Sengaja TIDAK menghukum "menunggu tes pihak ke-3": sebabnya di luar
     * kendali (kandidat/penyedia tes belum menuntaskan).
     *
     * Tanpa proses berjalan skor null (netral, bukan 0), dengan DUA label
     * berbeda — keduanya sering tertukar dan menyesatkan kalau disamakan:
     *  - KOSONG  : belum ada pelamar sama sekali;
     *  - SELESAI : pernah ada pelamar, tapi semuanya sudah tuntas.
     */
    public static function skorSehat(?object $agg, int $totalPelamar = 0): array
    {
        $aktif = (int) ($agg->aktif ?? 0);
        $macet = (int) ($agg->macet ?? 0);
        $siapTua = (int) ($agg->siapTua ?? 0);

        if ($aktif < 1) {
            return ['skor' => null, 'label' => $totalPelamar > 0 ? 'SELESAI' : 'KOSONG',
                'aktif' => 0, 'macet' => 0, 'siapMenggantung' => 0,
                'siapDiputus' => (int) ($agg->siap ?? 0),
                'menungguTes' => (int) ($agg->nungguTes ?? 0), 'maxAging' => null];
        }

        $skor = (int) max(5, round(100 - 60 * ($macet / $aktif) - 80 * ($siapTua / $aktif)));

        return [
            'skor' => $skor,
            'label' => $skor >= 80 ? 'SEHAT' : ($skor >= 50 ? 'PERLU_AKSI' : 'KRITIS'),
            'aktif' => $aktif,
            'macet' => $macet,
            'siapMenggantung' => $siapTua,
            'siapDiputus' => (int) ($agg->siap ?? 0),
            'menungguTes' => (int) ($agg->nungguTes ?? 0),
            'maxAging' => $agg->maxAging !== null ? max(0, (int) $agg->maxAging) : null,
        ];
    }

    /**
     * AGREGAT KESEHATAN per program — enam ukuran dalam satu GROUP BY.
     * Keluarannya adalah objek yang diharapkan skorSehat() di atas.
     */
    public static function agregatSehat(array $programIds)
    {
        if (! $programIds) {
            return collect();
        }

        $macet = self::macetHari();
        $sorot = self::sorotHari();
        $umur = self::sqlUmurTahap('lt');
        $aging = self::sqlAging('lt');
        $bukanDitahan = self::sqlBukanDitahan('lt');
        $menungguTes = self::sqlMenungguTes('lt');

        $dasar = DB::table('N_WEB_CAREERS_Lamaran_Tahap as lt')
            ->join('N_WEB_CAREERS_Lamaran as l', 'l.Id_Lamaran', '=', 'lt.Lamaran_Id');

        return self::denganAktivitas($dasar)
            ->where('lt.Status', 'BERJALAN')->where('l.Status', 'BERJALAN')
            ->whereIn('l.Program_Id', $programIds)
            ->groupBy('l.Program_Id')
            ->selectRaw("l.Program_Id,
                         COUNT(*) as aktif,
                         SUM(CASE WHEN {$bukanDitahan} AND lt.Siap_Diputus <> 'Y' AND {$umur} > {$macet} THEN 1 ELSE 0 END) as macet,
                         SUM(CASE WHEN {$bukanDitahan} AND lt.Siap_Diputus = 'Y'
                                   AND DATEDIFF(day, COALESCE(lt.Rekomendasi_At, lt.Updated_At, lt.Created_At), GETDATE()) > {$sorot}
                                  THEN 1 ELSE 0 END) as siapTua,
                         SUM(CASE WHEN {$bukanDitahan} AND lt.Siap_Diputus = 'Y' THEN 1 ELSE 0 END) as siap,
                         SUM(CASE WHEN {$bukanDitahan} AND {$menungguTes} AND lt.Siap_Diputus = 'N' THEN 1 ELSE 0 END) as nungguTes,
                         MAX({$aging}) as maxAging")
            ->get()
            ->keyBy('Program_Id');
    }

    /** Jam server, dikirim di setiap respons supaya user tahu data per kapan. */
    public static function checkpoint(): array
    {
        $now = now();

        return ['waktu' => $now->format('Y-m-d H:i:s'), 'label' => $now->format('d M Y H:i')];
    }
}
