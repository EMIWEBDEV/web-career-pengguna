<?php

namespace App\Support\Career;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * WEB CAREERS — MEMPERPANJANG TENGGAT (SLA) SEBUAH MPP.
 *
 * ══ APA YANG DIPUTUSKAN DI SINI ═════════════════════════════════════════════
 *
 * Satu MPP punya tenggat yang dibekukan saat ia dibuat (lihat SlaMpp). Tenggat
 * itu lewat, kursinya belum terisi, dan lowongannya masih harus jalan. Kelas
 * ini satu-satunya tempat yang boleh menggeser tenggat tersebut — dan satu-
 * satunya yang tahu syaratnya.
 *
 * ══ TIGA ATURAN YANG TIDAK BOLEH DILANGGAR ══════════════════════════════════
 *
 * 1. PANJANGNYA MENGIKUTI SLA LEVEL YANG SAMA, BUKAN ANGKA KARANGAN.
 *
 *    Level STAFF ber-SLA 45 hari kerja diperpanjang 45 hari kerja lagi. Kalau
 *    angkanya bebas diketik per kejadian, ia akan diisi sebesar yang dibutuhkan
 *    supaya tidak pernah telat lagi — dan tenggatnya berhenti berarti apa pun.
 *
 *    Angkanya diambil dari SNAPSHOT MPP itu sendiri (Sla_Hari_Kerja), bukan
 *    dari master hari ini: alasan yang sama dengan kenapa snapshot ada. Master
 *    yang berubah tahun depan tidak boleh menulis ulang MPP tahun ini —
 *    termasuk lewat pintu perpanjangan.
 *
 * 2. DIHITUNG DARI TENGGAT LAMA, BUKAN DARI HARI PERPANJANGANNYA.
 *
 *    Kalau dihitung dari hari ini, MPP yang diperpanjang terlambat seminggu
 *    mendapat tujuh hari lebih banyak daripada yang diperpanjang tepat waktu.
 *    Menunda jadi menguntungkan — persis kebalikan dari yang dimaui. Tenggat
 *    baru selalu = tenggat lama + N hari kerja, siapa pun dan kapan pun yang
 *    menekan tombolnya.
 *
 * 3. ALASANNYA WAJIB, DAN DISIMPAN APA ADANYA.
 *
 *    Yang membaca laporan tiga bulan kemudian tidak sedang bertanya
 *    "diperpanjang atau tidak" — itu sudah terlihat dari tanggalnya. Yang ia
 *    tanyakan selalu KENAPA, dan itu satu-satunya hal yang tidak bisa
 *    direkonstruksi dari data mana pun bila tidak ditulis pada detik
 *    kejadiannya.
 *
 * ══ KENAPA SELURUH PEMERIKSAAN DI SINI, BUKAN DI CONTROLLER ═════════════════
 *
 * Karena jawabannya dibutuhkan DUA KALI dan harus sama persis: layar bertanya
 * "boleh diperpanjang?" untuk memutuskan tombolnya muncul atau tidak, dan
 * pintu simpan bertanya hal yang sama untuk memutuskan menerima atau menolak.
 * Dua tempat yang menjawab sendiri-sendiri cepat atau lambat berbeda — dan
 * bentuk kegagalannya selalu sama: tombol yang terlihat, ditekan, lalu ditolak.
 *
 * ══ YANG SENGAJA TIDAK DIKERJAKAN DI SINI ═══════════════════════════════════
 *
 * Kelas ini tidak menyentuh Tanggal_Periode di header MPP. Kolom itu milik
 * HRIS_Transaksi_GForm dan dibaca modul lain; yang berpindah karena
 * perpanjangan adalah TENGGAT SLA (Sla_Batas), dan keduanya memang beda
 * pertanyaan — lihat catatan di simpan().
 */
class PerpanjangSla
{
    private const TABEL_G = 'HRIS_Transaksi_GForm';

    private const TABEL_D = 'N_WEB_CAREERS_Detail_MPP';

    /**
     * Panjang alasan paling pendek yang masih dianggap alasan.
     *
     * Sama dengan CHECK di kolomnya. Ditegakkan di dua tempat karena keduanya
     * menjawab pertanyaan berbeda: yang di sini memberi kalimat yang bisa
     * dibaca admin, yang di database menutup jalan yang tidak lewat sini.
     */
    public const MIN_ALASAN = 10;

    public const MAKS_ALASAN = 1000;

    /**
     * Berapa kali sebuah MPP boleh diperpanjang.
     *
     * Ada batasnya, dan itu disengaja. Perpanjangan yang bisa diulang tanpa
     * henti bukan perpanjangan — ia tenggat yang dihapus dengan cara yang lebih
     * sopan. MPP yang sudah tiga kali gagal tuntas bukan lagi soal tenggat:
     * kuotanya, levelnya, atau paket lowongannya yang perlu ditinjau, dan itu
     * keputusan yang harus naik ke orang lain, bukan diulang di layar yang sama.
     */
    public const MAKS_PERPANJANGAN = 3;

    /**
     * Keadaan perpanjangan sebuah MPP — dipakai layar DAN pintu simpan.
     *
     * Selalu memulangkan larik, tidak pernah null: layar butuh tahu BEDA antara
     * "belum boleh, ini sebabnya" dan "tidak ada datanya". Yang kedua membuat
     * kartu diam tanpa penjelasan, dan admin menyimpulkan fiturnya rusak.
     *
     * @return array{
     *     tersedia:bool, boleh:bool, alasanTolak:?string, hari:?int,
     *     batas:?string, batasAwal:?string, batasBaru:?string,
     *     ke:int, sisaJatah:int, lewat:bool, hariTerlambat:int, riwayat:array
     * }
     */
    public static function keadaan(?string $no): array
    {
        $kosong = [
            'tersedia' => false,
            'boleh' => false,
            'alasanTolak' => null,
            'hari' => null,
            'batas' => null,
            'batasAwal' => null,
            'batasBaru' => null,
            'ke' => 0,
            'sisaJatah' => 0,
            'lewat' => false,
            'hariTerlambat' => 0,
            'riwayat' => [],
        ];

        if (! $no || ! SlaMpp::siapPanjang()) {
            return $kosong;
        }

        $mpp = self::baca($no);
        if (! $mpp) {
            return $kosong;
        }

        $sudah = (int) ($mpp->perpanjanganKe ?? 0);
        $ke = $sudah + 1;

        $keadaan = array_merge($kosong, [
            'tersedia' => true,
            'ke' => $ke,
            // SISA SESUDAH yang sedang dipertimbangkan ini terpakai — bukan
            // sebelumnya. Modal memakainya untuk memperingatkan "ini kesempatan
            // terakhir", dan peringatan itu harus muncul PADA perpanjangan
            // terakhir, bukan sesudahnya (saat sudah tidak ada yang bisa
            // diperingatkan). Sama artinya dengan yang dipulangkan simpan().
            'sisaJatah' => max(0, self::MAKS_PERPANJANGAN - $ke),
            'hari' => $mpp->hari ? (int) $mpp->hari : null,
            'batas' => $mpp->batas,
            'batasAwal' => $mpp->batasAwal ?: $mpp->batas,
            'riwayat' => self::riwayat($no),
        ]);

        // Terlambat berapa HARI KERJA — bukan hari kalender. Angka inilah yang
        // dibaca sebagai "seberapa parah", dan menghitungnya sebagai hari
        // kalender membuat MPP yang tenggatnya jatuh sebelum libur panjang
        // terlihat jauh lebih terlambat daripada yang sebenarnya.
        if ($mpp->batas) {
            $hariIni = Carbon::today()->toDateString();
            $keadaan['lewat'] = $mpp->batas < $hariIni;
            $keadaan['hariTerlambat'] = $keadaan['lewat']
                ? SlaMpp::selisihHariKerja($mpp->batas, $hariIni)
                : 0;
        }

        if ($tolak = self::alasanTolak($mpp, $sudah)) {
            $keadaan['alasanTolak'] = $tolak;

            return $keadaan;
        }

        // Tenggat barunya dihitung DI SINI juga, bukan hanya saat menyimpan.
        // Modal konfirmasi harus menyebut tanggal yang sama persis dengan yang
        // kelak tertulis — kalau tidak, admin menyetujui satu tanggal dan
        // mendapat tanggal lain, dan tidak ada cara baginya tahu mana yang salah.
        $keadaan['boleh'] = true;
        $keadaan['batasBaru'] = self::batasBaru($mpp->batas, (int) $mpp->hari);

        return $keadaan;
    }

    /**
     * Lakukan perpanjangannya. Memulangkan pesan galat, atau null bila berhasil.
     *
     * SATU TRANSAKSI untuk riwayat + penanda + tenggat barunya. Ketiganya harus
     * bergerak bersama: tenggat yang bergeser tanpa baris riwayat adalah persis
     * penyuntingan diam-diam yang fitur ini ada untuk menggantikan, dan riwayat
     * tanpa tenggat yang bergeser adalah janji yang tidak pernah ditepati.
     *
     * @return array{galat:?string, hasil:?array}
     */
    public static function simpan(string $no, string $alasan, ?string $olehNama = null, ?int $olehId = null): array
    {
        if (! SlaMpp::siapPanjang()) {
            return ['galat' => 'Fitur perpanjangan SLA belum dipasang di basis data ini.', 'hasil' => null];
        }

        $alasan = trim($alasan);
        if (mb_strlen($alasan) < self::MIN_ALASAN) {
            return [
                'galat' => sprintf(
                    'Alasan perpanjangan wajib diisi, minimal %d huruf. Tulis sebabnya — '
                    .'ini satu-satunya keterangan yang bisa dibaca kembali saat MPP ini ditinjau.',
                    self::MIN_ALASAN,
                ),
                'hasil' => null,
            ];
        }

        $mpp = self::baca($no);
        if (! $mpp) {
            return ['galat' => 'Transaksi MPP tidak ditemukan.', 'hasil' => null];
        }

        $sudah = (int) ($mpp->perpanjanganKe ?? 0);
        if ($tolak = self::alasanTolak($mpp, $sudah)) {
            return ['galat' => $tolak, 'hasil' => null];
        }

        $hari = (int) $mpp->hari;
        $batasLama = $mpp->batas;
        $batasBaru = self::batasBaru($batasLama, $hari);
        $urutan = $sudah + 1;

        // Keadaan kursi ikut dibekukan ke barisnya — inilah pembenaran yang
        // dipakai ("3 dibutuhkan, baru 1 terisi"), dan angka itu berubah terus.
        $kursi = KursiMpp::keadaan($no);

        try {
            DB::transaction(function () use ($no, $urutan, $batasLama, $batasBaru, $hari, $alasan, $kursi, $olehNama, $olehId, $mpp) {
                DB::table(SlaMpp::TABEL_PANJANG)->insert([
                    'No_Transaksi_MPP' => $no,
                    'Urutan' => $urutan,
                    'Batas_Lama' => $batasLama,
                    'Batas_Baru' => $batasBaru,
                    'Hari_Kerja' => $hari,
                    'Alasan' => $alasan,
                    'Kuota_Saat_Itu' => $kursi['kuota'] ?? null,
                    'Terisi_Saat_Itu' => $kursi['terisi'] ?? null,
                    'Created_At' => now(),
                    'Created_By' => $olehNama,
                    'Created_By_Id' => $olehId,
                ]);

                DB::table(self::TABEL_D)->where('No_Transaksi_MPP', $no)->update([
                    // Sla_Batas SELALU berisi tenggat yang BERLAKU. Seluruh
                    // gerbang dan laporan membacanya tanpa perlu tahu ada berapa
                    // perpanjangan di belakangnya.
                    'Sla_Batas' => $batasBaru,
                    // Tenggat ASLI dipertahankan. Diisi hanya sekali — pada
                    // perpanjangan pertama — dan sesudah itu tidak boleh
                    // tersentuh lagi, sebab "seharusnya kapan" cuma punya satu
                    // jawaban betapapun seringnya diperpanjang.
                    'Sla_Batas_Awal' => $mpp->batasAwal ?: $batasLama,
                    'Sla_Perpanjangan_Ke' => $urutan,
                    'Sla_Perpanjangan_At' => now(),
                    'Updated_At' => now(),
                    'Updated_By' => $olehId,
                ]);
            });
        } catch (\Throwable $e) {
            // Indeks unik (No_Transaksi_MPP, Urutan) yang menolak berarti ada
            // perpanjangan lain yang lebih dulu masuk — dua tab, atau tombol
            // yang tertekan dua kali. Itu bukan kegagalan sistem, dan kalimatnya
            // tidak boleh berbunyi seperti kegagalan sistem.
            if (self::bentrokUrutan($e)) {
                return [
                    'galat' => 'MPP ini baru saja diperpanjang dari layar lain. Muat ulang halaman '
                        .'untuk melihat tenggat terbarunya sebelum memperpanjang lagi.',
                    'hasil' => null,
                ];
            }

            throw $e;
        }

        return [
            'galat' => null,
            'hasil' => [
                'ke' => $urutan,
                'hari' => $hari,
                'batasLama' => $batasLama,
                'batasBaru' => $batasBaru,
                'sisaJatah' => max(0, self::MAKS_PERPANJANGAN - $urutan),
            ],
        ];
    }

    /**
     * Riwayat perpanjangan sebuah MPP — terbaru di atas.
     *
     * Dipakai panel detail. Alasannya ikut, dan memang itu isinya yang paling
     * penting: garis waktu tanpa sebab cuma memberi tahu bahwa tenggatnya
     * pernah bergeser, yang sudah terlihat dari angka "2×" di lencananya.
     */
    public static function riwayat(?string $no): array
    {
        if (! $no || ! SlaMpp::siapPanjang()) {
            return [];
        }

        return DB::table(SlaMpp::TABEL_PANJANG)
            ->where('No_Transaksi_MPP', $no)
            ->orderByDesc('Urutan')
            ->get([
                'Id_Perpanjangan', 'Urutan', 'Hari_Kerja', 'Alasan',
                'Kuota_Saat_Itu', 'Terisi_Saat_Itu', 'Created_At', 'Created_By',
                DB::raw('CONVERT(varchar(10), Batas_Lama, 23) as Batas_Lama'),
                DB::raw('CONVERT(varchar(10), Batas_Baru, 23) as Batas_Baru'),
            ])
            ->map(fn ($r) => [
                'id' => (int) $r->Id_Perpanjangan,
                'ke' => (int) $r->Urutan,
                'hari' => (int) $r->Hari_Kerja,
                'batasLama' => $r->Batas_Lama,
                'batasBaru' => $r->Batas_Baru,
                'alasan' => $r->Alasan,
                'kuota' => $r->Kuota_Saat_Itu !== null ? (int) $r->Kuota_Saat_Itu : null,
                'terisi' => $r->Terisi_Saat_Itu !== null ? (int) $r->Terisi_Saat_Itu : null,
                'pada' => $r->Created_At,
                'oleh' => $r->Created_By,
            ])
            ->values()
            ->all();
    }

    /**
     * Penanda ringkas untuk BANYAK MPP sekaligus — satu kueri.
     *
     * Daftar MPP menggambar puluhan kartu; menanyakannya satu per satu berarti
     * satu kueri per kartu, dan halaman yang seluruh gunanya "lihat semuanya"
     * justru jadi paling lambat saat paling dipakai. Sepola KursiMpp.
     *
     * @param  array<int, string|null>  $refs
     * @return array<string, array{ke:int, batasAwal:?string}>
     */
    public static function penandaBanyak(array $refs): array
    {
        if (! SlaMpp::siapPanjang()) {
            return [];
        }

        $bersih = collect($refs)->filter()->unique()->values();
        if ($bersih->isEmpty()) {
            return [];
        }

        return DB::table(self::TABEL_D)
            ->whereIn('No_Transaksi_MPP', $bersih->all())
            ->whereNotNull('Sla_Perpanjangan_Ke')
            ->get([
                'No_Transaksi_MPP',
                'Sla_Perpanjangan_Ke',
                DB::raw('CONVERT(varchar(10), Sla_Batas_Awal, 23) as Sla_Batas_Awal'),
            ])
            ->mapWithKeys(fn ($r) => [$r->No_Transaksi_MPP => [
                'ke' => (int) $r->Sla_Perpanjangan_Ke,
                'batasAwal' => $r->Sla_Batas_Awal,
            ]])
            ->all();
    }

    // ═══════════════════════ INTERNAL ═══════════════════════

    /**
     * Kenapa MPP ini TIDAK boleh diperpanjang — null berarti boleh.
     *
     * Urutannya disengaja: dari yang paling menjelaskan ke yang paling umum.
     * "MPP-nya dibatalkan" lebih berguna daripada "tidak punya tenggat", dan
     * yang membacanya berhenti di kalimat pertama.
     */
    private static function alasanTolak(object $mpp, int $sudah): ?string
    {
        if (($mpp->status ?? null) === 'Y') {
            return 'MPP ini sudah dibatalkan. Aktifkan kembali lebih dulu bila memang masih berjalan.';
        }

        if (($mpp->selesai ?? null) === 'Y') {
            return 'MPP ini sudah ditandai selesai — tenggatnya tidak perlu diperpanjang. '
                .'Batalkan tanda selesai lebih dulu bila rekrutmennya ternyata masih berjalan.';
        }

        // MT memang tidak punya SLA sama sekali (lihat MasterMppController::galatSla).
        // Kalimatnya menyebut itu, bukan "tenggat tidak ditemukan" — yang
        // terdengar seperti data hilang padahal memang tidak pernah ada.
        if (($mpp->mt ?? null) === 'Y') {
            return 'Program Management Trainee tidak terikat SLA level, jadi tidak ada tenggat yang bisa diperpanjang.';
        }

        if (! $mpp->batas || ! $mpp->hari) {
            return 'MPP ini tidak menyimpan tenggat SLA (dibuat sebelum ketentuan SLA berlaku), '
                .'jadi tidak ada tenggat yang bisa diperpanjang.';
        }

        if ($sudah >= self::MAKS_PERPANJANGAN) {
            return sprintf(
                'MPP ini sudah diperpanjang %d kali — batas maksimalnya. Yang perlu ditinjau '
                .'bukan lagi tenggatnya: periksa kembali kuota, level, atau paket lowongannya.',
                $sudah,
            );
        }

        // KURSINYA SUDAH PENUH — tidak ada yang perlu diperpanjang.
        //
        // Ini pemeriksaan yang paling mudah terlupa dan paling merepotkan bila
        // terlewat: MPP yang kursinya sudah terisi penuh lalu diperpanjang akan
        // muncul di laporan sebagai lowongan yang masih berjalan berbulan-bulan
        // sesudah orangnya diterima.
        $kursi = KursiMpp::keadaan($mpp->no);
        if ($kursi && ($kursi['penuh'] ?? false)) {
            return sprintf(
                'Kuota MPP ini sudah terpenuhi (%d dari %d terisi), jadi tenggatnya tidak perlu '
                .'diperpanjang. Tandai MPP-nya selesai.',
                $kursi['terisi'], $kursi['kuota'],
            );
        }

        return null;
    }

    /** Tenggat lama + N hari kerja — lewat SlaMpp, penanggalan yang sama dengan penetapnya. */
    private static function batasBaru(string $batasLama, int $hari): string
    {
        return SlaMpp::tambahHariKerja(Carbon::parse($batasLama)->startOfDay(), $hari)->toDateString();
    }

    /** Keadaan satu MPP yang dibutuhkan seluruh keputusan di kelas ini. */
    private static function baca(string $no): ?object
    {
        return DB::table(self::TABEL_G.' as g')
            ->join(self::TABEL_D.' as d', 'd.No_Transaksi_MPP', '=', 'g.No_Transaksi')
            ->where('g.No_Transaksi', $no)
            ->selectRaw(
                'g.No_Transaksi as no, g.Status as status, g.Flag_Selesai as selesai, g.Flag_MT as mt, '
                .'d.Sla_Hari_Kerja as hari, d.Sla_Perpanjangan_Ke as perpanjanganKe, '
                .'CONVERT(varchar(10), d.Sla_Batas, 23) as batas, '
                .'CONVERT(varchar(10), d.Sla_Batas_Awal, 23) as batasAwal'
            )
            ->first();
    }

    /**
     * Galatnya karena indeks unik urutan, atau sesuatu yang lain?
     *
     * Dibedakan karena keduanya minta perlakuan berbeda: yang ini kalimat biasa
     * untuk admin, yang lain harus naik sebagai galat sungguhan supaya tercatat
     * dan tidak tersamar sebagai "coba lagi".
     */
    private static function bentrokUrutan(\Throwable $e): bool
    {
        $pesan = $e->getMessage();

        return str_contains($pesan, 'UX_NWCSLAP_Urutan')
            || (str_contains($pesan, 'duplicate') && str_contains($pesan, 'Perpanjangan'));
    }
}
