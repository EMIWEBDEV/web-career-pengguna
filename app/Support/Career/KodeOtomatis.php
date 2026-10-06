<?php

namespace App\Support\Career;

use Illuminate\Support\Facades\DB;

/**
 * WEB CAREER — KODE YANG DIBUATKAN SISTEM.
 *
 * ── KENAPA ORANG TIDAK LAGI MENGETIK KODE SENDIRI ───────────────────────────
 *
 * Kode di modul skrining bukan hiasan: ia yang menyambungkan sesi yang sudah
 * tercatat dengan pertanyaan yang ditanyakan waktu itu, dan ia pula yang dipakai
 * mengagregasi "gaji harapan" lintas template. Kode yang diketik tangan rusak
 * dengan dua cara yang sama-sama mahal:
 *
 *   1. Salah ketik yang tidak kelihatan. `gaji_harapn` tetap tersimpan, tetap
 *      jalan, dan baru ketahuan berbulan-bulan kemudian ketika laporannya
 *      memisahkan satu pertanyaan jadi dua kolom.
 *
 *   2. Beban yang salah tempat. Orang yang sedang menyusun pertanyaan wawancara
 *      dipaksa berhenti untuk memikirkan tata nama identifier basis data.
 *
 * ── KENAPA TIDAK ADA KUNCI SAMA SEKALI ──────────────────────────────────────
 *
 * Cara termudah menjamin kode unik adalah mengunci tabelnya, ambil nomor
 * terakhir, tambah satu. Itu benar, dan itu juga cara membuat dua rekruter yang
 * menyimpan bersamaan saling menunggu — di tabel yang dibaca setiap kali daftar
 * pertanyaan dibuka.
 *
 * Yang dipakai di sini adalah optimistic: tebak sekali lewat satu pembacaan
 * indeks, lalu biarkan INDEKS UNIK yang jadi wasitnya. Bila dua orang kebetulan
 * menebak kode yang sama pada saat yang sama, salah satunya kena galat kunci
 * ganda dan pemanggilnya tinggal minta kode berikutnya. Tabrakan seperti itu
 * langka; menunggu karena kunci tidak.
 *
 * Karena itu berkas ini TIDAK PERNAH memakai transaksi, `FOR UPDATE`, tabel
 * penghitung, atau `sp_getapplock`. Ia hanya membaca.
 *
 * ── KENAPA AWALANNYA DIAMBIL DARI DATA, BUKAN DARI PETA ─────────────────────
 *
 * Pustaka sudah memakai awalan per kelompok: `bhv_` untuk Perilaku, `it_` untuk
 * Teknis IT, `avl_` untuk Ketersediaan. Menyalin peta itu ke dalam kode program
 * berarti tiap kelompok baru menuntut satu baris tambahan di berkas ini —
 * dan cepat atau lambat ada yang lupa menambahkannya.
 *
 * Jadi awalannya DITANYAKAN kepada datanya sendiri: awalan mana yang paling
 * banyak dipakai pertanyaan lain di kelompok yang sama. Kelompok yang belum
 * pernah ada jatuh ke potongan namanya sendiri, dan begitu pertanyaan
 * pertamanya tersimpan, kelompok itu punya awalannya sendiri untuk seterusnya.
 */
final class KodeOtomatis
{
    /**
     * Kata yang dibuang dari kode karena tidak membedakan apa pun.
     *
     * "Apakah Anda bersedia ditempatkan di luar kota domisili?" tanpa daftar ini
     * jadi `apakah_anda_bersedia_ditempatkan_di_luar` — enam kata pertamanya
     * dipakai empat pertanyaan lain, dan yang membedakan justru terpotong di
     * ujung. Sengaja TIDAK memuat kata ingkar seperti "tidak" atau "belum":
     * membuangnya membalik arti kodenya.
     */
    private const BUANG = [
        'yang', 'dan', 'atau', 'di', 'ke', 'dari', 'untuk', 'dengan', 'pada',
        'apakah', 'apa', 'bagaimana', 'berapa', 'kapan', 'mengapa', 'kenapa',
        'anda', 'saya', 'kamu', 'ini', 'itu', 'adalah', 'akan', 'sudah', 'saat',
        'jika', 'kalau', 'agar', 'oleh', 'dalam', 'sebagai', 'secara', 'tentang',
        'ada', 'boleh', 'mohon', 'tolong', 'silakan', 'sebutkan', 'ceritakan',
        'jelaskan', 'seberapa', 'paling', 'lebih', 'juga', 'nya', 'per', 'se',
        'the', 'a', 'an', 'of', 'to', 'for', 'and', 'or', 'in', 'on', 'is',
    ];

    // ═══════════════════════════ PERTANYAAN ═══════════════════════════

    /**
     * Kode pertanyaan pustaka: huruf kecil, angka, garis bawah, maksimum 40.
     *
     *      pertanyaan('Bersedia ditempatkan di luar kota domisili?', 'Ketersediaan')
     *          -> 'avl_bersedia_ditempatkan_luar_kota'
     */
    public static function pertanyaan(string $label, ?string $kelompok = null): string
    {
        $awalan = self::awalanPertanyaan($kelompok);
        $isi = self::potongKata(self::kataPenting($label), 40 - strlen($awalan) - 1, '_');

        // Label yang seluruhnya kata umum ("Apakah Anda yakin?") menyisakan
        // kosong. Kode berupa awalan telanjang tidak salah, tapi juga tidak
        // memberi tahu apa pun — nomor urutnya yang akan membedakan.
        $dasar = $isi === '' ? $awalan : $awalan.'_'.$isi;

        return self::unik(BankPertanyaan::TABEL, 'Kode', $dasar, 40, '_');
    }

    /**
     * Awalan yang sudah dipakai kelompok ini. Satu pembacaan, tanpa kunci.
     */
    private static function awalanPertanyaan(?string $kelompok): string
    {
        $kelompok = trim((string) $kelompok);

        if ($kelompok !== '' && BankPertanyaan::siap()) {
            $pre = DB::table(BankPertanyaan::TABEL)
                ->where('Kelompok', $kelompok)
                ->whereRaw("CHARINDEX('_', Kode) > 1")
                ->selectRaw("LEFT(Kode, CHARINDEX('_', Kode) - 1) AS pre, COUNT(*) AS n")
                ->groupByRaw("LEFT(Kode, CHARINDEX('_', Kode) - 1)")
                ->orderByDesc('n')
                ->value('pre');

            if ($pre) {
                return $pre;
            }
        }

        // Kelompok baru: empat huruf pertama kata pentingnya. "Kecocokan
        // Budaya" -> "keco". Pendek karena ia harus menyisakan ruang untuk
        // bagian yang benar-benar membedakan.
        $kata = self::kataPenting($kelompok);

        return $kata === [] ? 'q' : substr($kata[0], 0, 4);
    }

    // ═══════════════════════════ TEMPLATE ═══════════════════════════

    /**
     * Kode template skrining: huruf besar, angka, strip, maksimum 30.
     *
     *      template('Skrining Awal HR Umum')  ->  'SKR-AWAL-HR-UMUM'
     *
     * Awalan SKR- dipakai supaya kode ini tetap dikenali sebagai milik modul
     * skrining ketika muncul di tempat yang jauh dari halamannya — di log
     * aktivitas, di kolom Skrining_Kode milik sesi, atau di ekspor.
     */
    public static function template(string $nama, ?string $kategori = null): string
    {
        $kata = self::kataPenting($nama);

        // Nama yang diawali "Skrining"/"Template" tidak perlu mengulangnya lagi
        // sesudah awalan SKR-.
        while ($kata !== [] && in_array($kata[0], ['skrining', 'screening', 'template', 'skr'], true)) {
            array_shift($kata);
        }

        if ($kata === [] && $kategori) {
            $kata = self::kataPenting($kategori);
        }

        $isi = self::potongKata($kata, 30 - 4, '-');
        $dasar = 'SKR-'.($isi === '' ? 'BARU' : strtoupper($isi));

        return self::unik(Skrining::T_MASTER, 'Kode', $dasar, 30, '-');
    }

    // ═══════════════════════════ DAPUR ═══════════════════════════

    /** Kata yang tersisa sesudah tanda baca dan kata umum dibuang. */
    private static function kataPenting(string $teks): array
    {
        $teks = strtolower(trim($teks));
        $teks = preg_replace('/[^a-z0-9]+/', ' ', $teks) ?? '';

        $kata = array_values(array_filter(
            preg_split('/\s+/', trim($teks)) ?: [],
            fn ($k) => $k !== '' && ! in_array($k, self::BUANG, true),
        ));

        // Kalau semuanya terbuang, lebih baik memakai kata aslinya daripada
        // mengembalikan kosong: "Apa kabar?" tetap perlu jadi sesuatu.
        if ($kata === []) {
            $kata = array_values(array_filter(preg_split('/\s+/', trim($teks)) ?: []));
        }

        return $kata;
    }

    /**
     * Sambung kata sampai batas panjang — di BATAS KATA, bukan di tengahnya.
     *
     * Memotong di tengah menghasilkan `bersedia_ditempatkan_di_luar_ko`, dan
     * potongan seperti itu membuat orang ragu apakah kodenya benar atau rusak.
     */
    private static function potongKata(array $kata, int $maks, string $pisah): string
    {
        $out = '';
        foreach ($kata as $k) {
            $calon = $out === '' ? $k : $out.$pisah.$k;
            if (strlen($calon) > $maks) {
                // Kata pertama saja sudah kepanjangan: tidak ada batas kata yang
                // bisa dipakai, jadi terpaksa dipotong.
                if ($out === '') {
                    return substr($k, 0, $maks);
                }
                break;
            }
            $out = $calon;
        }

        return $out;
    }

    /**
     * Kode bebas pertama dari satu keluarga awalan.
     *
     * Satu pembacaan indeks (`LIKE 'dasar%'` = index seek pada indeks unik
     * kodenya), lalu nomornya dicari di memori. Tidak ada kunci, tidak ada
     * transaksi, dan tidak ada perulangan kueri.
     *
     * Nilai kembalinya adalah TEBAKAN TERBAIK, bukan jaminan. Yang menjamin
     * adalah indeks uniknya; pemanggil yang kena galat kunci ganda cukup
     * memanggil ulang.
     */
    private static function unik(string $tabel, string $kolom, string $dasar, int $maks, string $pisah): string
    {
        // `_` dan `%` adalah wildcard LIKE, dan kode pertanyaan penuh `_`.
        // Tanpa ini `avl_kerja` juga cocok dengan `avlxkerja`.
        $pola = str_replace(['[', '%', '_'], ['[[]', '[%]', '[_]'], $dasar).'%';

        $ada = [];
        foreach (DB::table($tabel)->where($kolom, 'like', $pola)->pluck($kolom) as $k) {
            $ada[strtolower((string) $k)] = true;
        }

        if (! isset($ada[strtolower($dasar)])) {
            return $dasar;
        }

        for ($i = 2; $i <= 999; $i++) {
            $ekor = $pisah.$i;
            $calon = substr($dasar, 0, $maks - strlen($ekor)).$ekor;
            if (! isset($ada[strtolower($calon)])) {
                return $calon;
            }
        }

        // Seribu kode serupa dalam satu keluarga praktis mustahil, tapi
        // mengembalikan null di jalur ini berarti menyimpan gagal tanpa sebab
        // yang bisa dijelaskan. Acak enam huruf jauh lebih baik daripada itu.
        $acak = $pisah.substr(bin2hex(random_bytes(4)), 0, 6);

        return substr($dasar, 0, $maks - strlen($acak)).$acak;
    }
}
