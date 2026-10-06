<?php

namespace App\Support\Career;

use App\Services\WebCareers\KalenderHcisClient;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * WEB CAREERS — BATAS WAKTU (SLA) SEBUAH MPP, DIHITUNG DALAM HARI KERJA.
 *
 * Satu tempat untuk dua pertanyaan yang harus selalu dijawab sama:
 *
 *   "Level ini berapa hari kerja?"       → aturanLevel()
 *   "Kalau mulai hari ini, tenggatnya kapan?" → batas() / tambahHariKerja()
 *
 * Dipakai layar (untuk mengunci pemilih tanggal), pintu simpan (untuk menolak
 * tanggal di luar ketentuan), dan pembekuan snapshot. Ketiganya WAJIB memakai
 * hitungan yang sama — kalau tidak, kalender di layar menawarkan tanggal yang
 * lalu ditolak server, dan admin menyalahkan sistem untuk aturan yang memang
 * ada.
 *
 * ── KENAPA HARI KERJA ───────────────────────────────────────────────────────
 *
 * "45 hari" yang dijanjikan ke pemohon berarti 45 hari KERJA. Dihitung sebagai
 * hari kalender, tenggat yang jatuh di sekitar Lebaran atau Natal bisa memakan
 * dua pekan libur — dan tim rekrutmen dinilai telat atas hari-hari yang memang
 * kantornya tutup.
 *
 * Hari kerja = SENIN–SABTU, dikurangi tanggal di HRIS_Hari_Libur. Sabtu IKUT
 * hari kerja (kalender EVO Group); hanya Minggu yang libur. Tabel hari libur
 * sudah ada dan diurus modul HRIS; di sini ia hanya dibaca.
 *
 * ── KENAPA SELURUHNYA BERPENJAGA hasTable/hasColumn ─────────────────────────
 *
 * Skrip skemanya (docs/20-8-2026) dijalankan admin, bukan oleh kode. Selama
 * belum dijalankan, seluruh kelas ini menjawab "tidak ada aturan" dan MPP
 * berjalan persis seperti sebelumnya — tanpa satu pun galat di layar yang tidak
 * ada hubungannya dengan pekerjaan orang yang sedang memakainya.
 */
class SlaMpp
{
    public const TABEL = 'N_WEB_CAREERS_Master_Sla_Mpp';

    /** Riwayat perpanjangan — satu baris per kejadian, lihat PerpanjangSla. */
    public const TABEL_PANJANG = 'N_WEB_CAREERS_Mpp_Sla_Perpanjangan';

    private const KODE_PERUSAHAAN = '001';

    /** Batas jumlah hari kerja yang ditelusuri — penahan gelung tak berujung. */
    private const MAKS_LANGKAH = 4000;

    /** Masternya sudah dipasang? */
    public static function siap(): bool
    {
        static $ada = null;

        return $ada ??= Skema::adaTabel(self::TABEL);
    }

    /** Kolom snapshot sudah ada di transaksi MPP? */
    public static function siapSnapshot(): bool
    {
        static $ada = null;

        return $ada ??= Skema::adaKolom('N_WEB_CAREERS_Detail_MPP', 'Sla_Hari_Kerja');
    }

    /**
     * Perpanjangan sudah bisa dipakai di basis data ini?
     *
     * Menuntut KETIGANYA sekaligus — tabel riwayat, kolom penanda, dan snapshot
     * dasarnya. Perpanjangan menulis ke ketiga tempat itu dalam satu transaksi;
     * membuka tombolnya saat salah satu belum ada berarti menjanjikan tindakan
     * yang pasti gagal di tengah jalan.
     */
    public static function siapPanjang(): bool
    {
        static $ada = null;

        return $ada ??= self::siapSnapshot()
            && Skema::adaTabel(self::TABEL_PANJANG)
            && Skema::adaKolom('N_WEB_CAREERS_Detail_MPP', 'Sla_Perpanjangan_Ke');
    }

    /**
     * Aturan yang berlaku untuk sebuah level — null berarti level itu memang
     * tidak dikunci, dan MPP-nya bebas memilih tanggal seperti sebelumnya.
     */
    public static function aturanLevel(?int $idLevel): ?object
    {
        if (! $idLevel || ! self::siap()) {
            return null;
        }

        return DB::table(self::TABEL)
            ->where('Id_Level', $idLevel)
            ->where('Flag_Aktif', 'Y')
            ->first(['Id_Sla_Mpp', 'Id_Level', 'Nama_Level', 'Hari_Kerja', 'Keterangan']);
    }

    /**
     * Tenggat sebuah MPP: mulai + N hari kerja.
     *
     * @return array{hari:?int, mulai:string, batas:?string, masterId:?int, nama:?string}
     *         `batas` null = level ini tidak dikunci.
     */
    public static function batas(?int $idLevel, ?string $mulai = null): array
    {
        $awal = $mulai ? Carbon::parse($mulai)->startOfDay() : Carbon::today();
        $aturan = self::aturanLevel($idLevel);

        if (! $aturan) {
            return ['hari' => null, 'mulai' => $awal->toDateString(), 'batas' => null, 'masterId' => null, 'nama' => null];
        }

        return [
            'hari' => (int) $aturan->Hari_Kerja,
            'mulai' => $awal->toDateString(),
            'batas' => self::tambahHariKerja($awal, (int) $aturan->Hari_Kerja)->toDateString(),
            'masterId' => (int) $aturan->Id_Sla_Mpp,
            'nama' => $aturan->Nama_Level,
        ];
    }

    /**
     * Tenggat dari ANGKA yang belum tersimpan di master.
     *
     * Dipakai borang master saat admin masih mengetik jumlah harinya: contoh
     * tanggalnya harus ikut bergerak sebelum ada satu baris pun tersimpan.
     * Lewat sini, bukan dihitung ulang di layar — supaya contoh yang dibaca
     * admin memakai penanggalan yang sama persis dengan yang kelak menegakkan
     * tenggatnya.
     *
     * @return array{hari:int, mulai:string, batas:string, masterId:null, nama:null}
     */
    public static function batasDariAngka(int $hari, ?string $mulai = null): array
    {
        $awal = $mulai ? Carbon::parse($mulai)->startOfDay() : Carbon::today();

        return [
            'hari' => $hari,
            'mulai' => $awal->toDateString(),
            'batas' => self::tambahHariKerja($awal, $hari)->toDateString(),
            'masterId' => null,
            'nama' => null,
        ];
    }

    /**
     * Maju N HARI KERJA dari sebuah tanggal.
     *
     * Hari mulainya sendiri TIDAK ikut dihitung — "45 hari kerja sejak
     * hari ini" berarti hari ini masih hari ke-0, dan hitungan mulai dari hari
     * kerja berikutnya. Menghitung hari pengajuan sebagai hari kerja penuh
     * berarti MPP yang diajukan pukul lima sore kehilangan satu hari yang
     * tak pernah bisa ia pakai.
     */
    public static function tambahHariKerja(Carbon $mulai, int $hari): Carbon
    {
        $tanggal = $mulai->copy()->startOfDay();

        if ($hari < 1) {
            return $tanggal;
        }

        $libur = self::hariLibur($tanggal, $tanggal->copy()->addDays(self::batasJangkauan($hari)));
        $sisa = $hari;
        $langkah = 0;

        while ($sisa > 0 && $langkah < self::MAKS_LANGKAH) {
            $tanggal->addDay();
            $langkah++;

            if (self::hariKerja($tanggal, $libur)) {
                $sisa--;
            }
        }

        return $tanggal;
    }

    /**
     * Berapa hari kerja antara dua tanggal (tidak termasuk hari mulai).
     *
     * Dipakai laporan: "tenggatnya 45, terpakai 51" — dan angka itu harus
     * dihitung dengan penanggalan yang sama dengan yang menetapkan tenggatnya.
     */
    public static function selisihHariKerja(string $dari, string $sampai): int
    {
        $a = Carbon::parse($dari)->startOfDay();
        $b = Carbon::parse($sampai)->startOfDay();

        if ($b->lte($a)) {
            return 0;
        }

        $libur = self::hariLibur($a, $b);
        $n = 0;
        $jalan = $a->copy();

        while ($jalan->lt($b)) {
            $jalan->addDay();
            if (self::hariKerja($jalan, $libur)) {
                $n++;
            }
        }

        return $n;
    }

    /**
     * Keadaan SLA sebuah tenggat — SATU sumber untuk seluruh layar.
     *
     * ── KENAPA TERPUSAT ───────────────────────────────────────────────────
     *
     * Sisa SLA ditampilkan di tiga tempat: kartu MPP, worklist pelamar, dan
     * dashboard monitoring. Kalau masing-masing menghitungnya sendiri, tiga
     * layar bisa menyebut angka berbeda untuk MPP yang sama — dan yang paling
     * berbahaya bukan selisihnya, melainkan bahwa tak seorang pun tahu yang
     * mana yang benar.
     *
     * Sisanya dihitung dalam HARI KERJA, bukan hari kalender: tenggatnya
     * dijanjikan dalam hari kerja, jadi "sisa 3 hari" harus berarti tiga hari
     * kerja — bukan tiga hari yang dua di antaranya akhir pekan.
     *
     * @param  string|null  $batas  tenggat 'Y-m-d' (yang BERLAKU, sudah termasuk perpanjangan)
     * @return array{sisa: int, lewat: bool, nada: string, label: string}|null
     */
    public static function keadaan(?string $batas): ?array
    {
        if (! $batas) {
            return null;
        }

        try {
            $tenggat = Carbon::parse($batas)->startOfDay();
        } catch (\Throwable $e) {
            return null;
        }

        $kini = Carbon::now()->startOfDay();

        // LEWAT — dihitung mundur supaya "telat 4 hari kerja" bisa disebut.
        if ($tenggat->lt($kini)) {
            return [
                'sisa' => -self::selisihHariKerja($tenggat->toDateString(), $kini->toDateString()),
                'lewat' => true,
                'nada' => 'lewat',
                'label' => 'Lewat tenggat',
            ];
        }

        $sisa = self::selisihHariKerja($kini->toDateString(), $tenggat->toDateString());

        // Ambang batasnya sengaja dalam HARI KERJA, bukan persentase sisa.
        // Persentase membuat MPP 90 hari baru dianggap genting saat tersisa
        // 9 hari, padahal merekrut level manajerial dalam 9 hari kerja sama
        // mustahilnya dengan level staf.
        [$nada, $label] = match (true) {
            $sisa === 0 => ['hari-ini', 'Jatuh tempo hari ini'],
            $sisa <= 3 => ['genting', 'Tersisa '.$sisa.' hari kerja'],
            $sisa <= 7 => ['waspada', 'Tersisa '.$sisa.' hari kerja'],
            default => ['aman', 'Tersisa '.$sisa.' hari kerja'],
        };

        return ['sisa' => $sisa, 'lewat' => false, 'nada' => $nada, 'label' => $label];
    }

    /**
     * Hari kerja = SENIN–SABTU, dikurangi hari libur terdaftar.
     *
     * ── KENAPA BUKAN isWeekend() ────────────────────────────────────────────
     *
     * Dulu di sini `! $t->isWeekend()`, dan Carbon memasukkan SABTU ke dalam
     * akhir pekan. Di EVO Group Sabtu adalah hari kerja penuh; hanya Minggu yang
     * libur. Akibatnya setiap tenggat SLA melar kira-kira 20% dari yang
     * dijanjikan — "30 hari kerja" jatuh ±6 minggu kalender, bukan 5 — dan
     * seluruh penilaian tepat-waktu ikut salah ke arah yang sama.
     *
     * Ditulis eksplisit sebagai `isSunday()` supaya tidak lagi bergantung pada
     * tafsir "akhir pekan" milik pustaka, yang tidak tahu kalender perusahaan.
     */
    private static function hariKerja(Carbon $t, array $libur): bool
    {
        return ! $t->isSunday() && ! isset($libur[$t->toDateString()]);
    }

    /**
     * Hari libur dalam satu rentang — SATU kueri untuk seluruh perhitungan.
     *
     * Menanyakannya per hari berarti 45 kueri untuk satu tanggal target, dan
     * layar yang memanggil ini tiap kali level diganti akan terasa tersendat
     * tanpa sebab yang terlihat.
     */
    private static function hariLibur(Carbon $dari, Carbon $sampai): array
    {
        // ── SUMBER HARI LIBUR ─────────────────────────────────────────────
        //
        // KEADAAN SEKARANG: `HRIS_Hari_Libur` saja.
        //
        // Kanal HCIS DIMATIKAN secara bawaan (config/hcis.php: driver 'mati'),
        // jadi rentang() di bawah selalu memulangkan larik kosong dan tidak
        // pernah menyentuh jaringan. Selama kanalnya belum disepakati,
        // memanggilnya berarti menunggu permintaan yang pasti gagal pada
        // setiap perhitungan SLA — untuk data yang sudah lengkap di tabel
        // sendiri.
        //
        // Keduanya tetap DIGABUNG, bukan dipilih salah satu, supaya menyalakan
        // HCIS kelak cukup mengganti satu nilai di .env: begitu driver 'http'
        // dipasang, cuti bersama parsial dan aturan per unit yang hanya
        // diketahui HCIS langsung ikut terhitung, tanpa satu baris pun di sini
        // yang perlu disunting.
        //
        // Digabung, bukan saling menimpa: kehilangan satu hari libur membuat
        // tenggat lebih ketat daripada seharusnya, dan rekruter dinilai
        // terlambat atas hari yang kantornya memang tutup.
        $libur = [];

        foreach (KalenderHcisClient::rentang($dari, $sampai) as $tgl => $_) {
            $libur[$tgl] = true;
        }

        if (Skema::adaTabel('HRIS_Hari_Libur')) {
            $lokal = DB::table('HRIS_Hari_Libur')
                ->where('Kode_Perusahaan', self::KODE_PERUSAHAAN)
                ->whereBetween('Tanggal', [$dari->toDateString(), $sampai->toDateString()])
                ->pluck('Tanggal');

            foreach ($lokal as $t) {
                $libur[Carbon::parse($t)->toDateString()] = true;
            }
        }

        return $libur;
    }

    /**
     * Rentang kalender yang perlu diambil libur-nya untuk N hari kerja.
     *
     * Sepekan berisi ENAM hari kerja (Senin–Sabtu), jadi N hari kerja paling
     * banyak memakan sekitar N/6 pekan; ditambah bantalan 30 hari untuk
     * rentetan libur panjang.
     * Kelebihan mengambil beberapa baris libur jauh lebih murah daripada
     * kekurangan — yang akibatnya tenggat meleset diam-diam.
     */
    private static function batasJangkauan(int $hari): int
    {
        return (int) ceil($hari * 7 / 6) + 30;
    }
}
