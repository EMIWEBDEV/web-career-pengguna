<?php

namespace App\Support\Career;

/**
 * WEB CAREER — pemetaan JENIS TES → TEMPLAT CETAK.
 *
 * Satu-satunya tempat yang memutuskan blade mana yang mencetak hasil sebuah
 * tes. Menambah instrumen baru = menambah satu baris di PETA di bawah.
 *
 * ── KENAPA DIPETAKAN DARI KODE, BUKAN DARI NAMA TAHAP ──────────────────────
 *
 * Alur seleksi disusun lewat layar, dan labelnya diketik manusia. Di produksi
 * hari ini ada "Psikotes Tahap 1", "PSIKOTES TAHAP 2", "Psikotes 2",
 * "PSIKOTES ONLINE", dan "Personality Test" — semuanya menunjuk tes yang
 * berbeda-beda, dan semuanya bebas diganti kapan saja tanpa memberi tahu
 * siapa pun. Memetakan templat dari label berarti berkas resmi kandidat
 * mencetak laporan DISC memakai kerangka PAPI begitu ada yang menyunting satu
 * kata di master alur.
 *
 * Nama UJIAN pun tidak bisa dipakai: "TES INTELEGENSI UMUM + KRAEPLIN" dan
 * "PAPI KOSTICK + DISC" adalah satu ujian berisi DUA instrumen sekaligus.
 *
 * Yang dipakai adalah `Kode_Tipe_Hasil` dari CAT — ditulis mesin penilaian,
 * berasal dari master indikator, dan ikut melekat pada hasilnya walau tesnya
 * diganti nama. Satu baris hasil, satu kode, satu templat.
 *
 * ── KOLOM Jenis_Tes_Kode TIDAK DIPAKAI, DAN ITU DISENGAJA ──────────────────
 *
 * N_WEB_CAREERS_Lamaran_Tahap_Tes punya kolom Jenis_Tes_Kode yang sekilas
 * tampak cocok. Kolom itu NULL pada seluruh baris di produksi — tidak pernah
 * terisi sejak alur dinamis dipakai. Memetakan darinya berarti setiap tes
 * jatuh ke templat generik tanpa ada yang menyadarinya.
 */
class PetaTemplateTes
{
    /**
     * Kode tipe hasil CAT → blade + judul yang dicetak di kepala halaman.
     *
     * Kodenya berasal dari HRIS_KANDIDAT_Jenis_Indikator (Kode_Hak_Akses /
     * Kode_Tes_Khusus) dan ikut tersimpan di Ujian_Nilai_Akhir.Kode_Tipe_Hasil.
     */
    private const PETA = [
        'TIU' => ['blade' => 'tiu', 'judul' => 'TIU — General Reasoning Test'],
        // TIU1 adalah TIU juga — indikator terpisah dengan ambang batas 50
        // (yang satunya 75), dipakai untuk kelas jabatan yang berbeda. Nama
        // indikatornya di CAT memang "TIU", kategorinya "ujian", dan bentuk
        // datanya sama persis: `domain[]` berkolom identik. Tanpa baris ini ia
        // jatuh ke templat generik dan mencetak tabel skor mentah, padahal
        // laporan TIU yang benar sudah tersedia.
        'TIU1' => ['blade' => 'tiu', 'judul' => 'TIU — General Reasoning Test'],
        'KRP' => ['blade' => 'kraeplin', 'judul' => 'Result Test Kraepelin'],
        'PPK' => ['blade' => 'papikostick', 'judul' => 'Result Test PAPI Kostick'],
        'DSC' => ['blade' => 'disc', 'judul' => 'DISC Assessment Report'],
        // TIDAK dipetakan, dan itu disengaja: PRWEB & SHE05 berkategori
        // "training" (Kode_Tes = TNG) — pre-test pelatihan karyawan, bukan tes
        // seleksi kandidat. Keduanya tidak akan pernah muncul di berkas
        // seleksi; kalaupun muncul, templat generik adalah yang benar.
    ];

    /** Blade generik — dipakai tes yang belum punya templat sendiri. */
    private const BLADE_GENERIK = 'generik';

    /** Awalan folder blade hasil tes. */
    private const AKAR = 'career.berkas.tes.';

    /**
     * Nama blade lengkap untuk satu kode tipe hasil.
     *
     * Kode yang tak dikenal TIDAK menggugurkan cetak: ia jatuh ke templat
     * generik yang menampilkan skor per jenis soal apa adanya. Tes baru yang
     * dibuat di CAT karena itu langsung ikut tercetak — dengan tampilan
     * sederhana, bukan dengan halaman kosong — sampai ada yang menambahkan
     * templat khususnya di sini.
     */
    public static function blade(?string $kodeTipeHasil): string
    {
        $kode = self::normalkan($kodeTipeHasil);

        return self::AKAR . (self::PETA[$kode]['blade'] ?? self::BLADE_GENERIK);
    }

    /**
     * Judul laporan menurut kodenya.
     *
     * `$cadangan` dipakai bila kodenya tak dikenal — biasanya nama indikator
     * dari CAT, yang untuk tes tanpa templat khusus justru lebih informatif
     * daripada sebutan umum apa pun yang bisa dituliskan di sini.
     */
    public static function judul(?string $kodeTipeHasil, ?string $cadangan = null): string
    {
        $kode = self::normalkan($kodeTipeHasil);

        return self::PETA[$kode]['judul']
            ?? (trim((string) $cadangan) !== '' ? trim((string) $cadangan) : 'Hasil Tes');
    }

    /** Apakah kode ini punya templat khusus (bukan jatuh ke generik). */
    public static function dikenal(?string $kodeTipeHasil): bool
    {
        return isset(self::PETA[self::normalkan($kodeTipeHasil)]);
    }

    /**
     * Rapikan kode sebelum dicocokkan.
     *
     * CAT menulisnya huruf besar, tapi kode yang datang dari baris lama pernah
     * membawa spasi di ujung — dan pencocokan yang gagal karena satu spasi
     * berujung pada laporan yang tercetak dengan templat yang salah.
     */
    private static function normalkan(?string $kode): string
    {
        return strtoupper(trim((string) $kode));
    }
}
