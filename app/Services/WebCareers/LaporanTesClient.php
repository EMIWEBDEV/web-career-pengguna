<?php

namespace App\Services\WebCareers;

use Illuminate\Support\Facades\Log;

/**
 * WEB CAREERS → CAT — ambil rincian hasil tes untuk dicetak.
 *
 * Callback hasil yang berjalan selama ini hanya membawa vonis agregat (nilai,
 * ambang, lulus/tidak) — cukup untuk menggerakkan alur seleksi, dan sengaja
 * tidak lebih. Berkas Seleksi Kandidat perlu rinciannya: skor TIU per domain,
 * profil DISC, 20 aspek PAPI, norma Kraeplin. Itu semua diambil DI SAAT CETAK
 * lewat kelas ini, bukan disalin ke basis data kita setiap ada hasil masuk.
 *
 * Kenapa tidak disalin: dua tempat menyimpan angka yang sama akan berbeda cepat
 * atau lambat, dan perbaikan aturan penilaian di CAT tidak akan pernah sampai
 * ke berkas yang tercetak dari salinan usang.
 *
 * ── SATU PESERTA BISA PULANG MEMBAWA BEBERAPA LAPORAN ──────────────────────
 *
 * Balasannya selalu daftar. "TIU + KRAEPLIN" dan "PAPI KOSTICK + DISC" adalah
 * satu ujian berisi dua instrumen, masing-masing dengan kode tipe hasilnya
 * sendiri. Kode itulah yang nanti memilih blade — lihat PetaTemplateTes.
 *
 * ── GAGAL DI SINI TIDAK BOLEH MENGGUGURKAN SELURUH BERKAS ──────────────────
 *
 * CAT bisa sedang tidak terjangkau. Berkas seleksi memuat jauh lebih banyak
 * daripada hasil psikotes — biodata, wawancara, lampiran — dan semuanya tetap
 * layak dicetak. Karena itu kegagalan dikembalikan sebagai NULL beserta
 * catatannya, bukan dilempar sebagai pengecualian.
 */
class LaporanTesClient
{
    /**
     * Rincian hasil tes satu peserta penjadwalan.
     *
     * @param  int  $idPenjadwalanPeserta  nomor peserta MILIK KITA — di CAT
     *                                     tersimpan sebagai Id_WC_Penjadwalan_Peserta.
     * @return array{peserta:array, ujian:array, laporan:array, logo:string}|null
     */
    /**
     * Rekaman tiap panggilan ke CAT dalam permintaan ini.
     *
     * ── KENAPA DIKUMPULKAN ────────────────────────────────────────────────
     *
     * Kegagalan mengambil laporan psikotes dulu hanya masuk log. Akibatnya
     * berkas tercetak tanpa hasil tes dan terlihat SAMA PERSIS dengan berkas
     * kandidat yang memang belum tes — admin tidak punya cara membedakan
     * "belum dikerjakan" dari "sambungan ke HCLearn putus", dan bisa memutus
     * kelulusan berdasarkan berkas yang isinya tidak lengkap.
     *
     * Statis per proses; dibaca layar sesudah render lalu dikosongkan.
     *
     * @var list<array{ok: bool, peserta?: int, status?: int, jenis?: string, pesan?: string}>
     */
    private static array $status = [];

    /** Ringkasan sambungan HCLearn untuk dilaporkan ke layar. */
    public static function ringkasStatus(): array
    {
        $gagal = array_values(array_filter(self::$status, fn ($s) => ! ($s['ok'] ?? false)));
        $putus = array_values(array_filter($gagal, fn ($s) => ($s['jenis'] ?? '') !== 'belum'));

        return [
            'dipanggil' => count(self::$status),
            'berhasil' => count(self::$status) - count($gagal),
            'belumTes' => count($gagal) - count($putus),
            'putus' => count($putus),
            // Pesan pertama saja: sepuluh baris galat yang sama tidak
            // menambah apa pun yang bisa ditindaklanjuti admin.
            'pesan' => $putus[0]['pesan'] ?? '',
            'jenis' => $putus[0]['jenis'] ?? '',
        ];
    }

    /** Kosongkan rekaman — dipanggil sebelum render baru dimulai. */
    public static function bersihkanStatus(): void
    {
        self::$status = [];
    }

    public static function ambil(
        int $idPenjadwalanPeserta,
        ?int $penggunaId = null,
        ?int $idPenjadwalanTahap = null,
        ?int $lamaranId = null,
    ): ?array {
        if ($idPenjadwalanPeserta < 1) {
            return null;
        }

        $path = trim((string) config('hclearn.laporan_tes_path', 'laporan-tes'), '/')
            . '/' . $idPenjadwalanPeserta;

        // -- PETUNJUK CADANGAN: TAHAP + LAMARAN ---------------------------
        //
        // Nomor peserta penjadwalan TIDAK abadi. Tabelnya pernah dikosongkan
        // di produksi lalu terisi ulang dari IDENTITY yang dimulai dari awal,
        // sehingga peserta kini bernomor 228-231 sementara token yang sudah
        // dikerjakan di CAT masih menyimpan nomor lama (seri 1000xxx). Nol
        // dari 228 token yang cocok, dan seluruh hasil psikotes hilang dari
        // berkas seleksi dengan alasan "tesnya belum dikerjakan" -- padahal
        // ujian, nilai, dan laporannya utuh.
        //
        // Nomor TAHAP penjadwalan tidak ikut dimulai ulang, jadi ia dikirim
        // sebagai petunjuk cadangan bersama nomor lamaran. CAT memakainya
        // HANYA bila nomor peserta tidak menemukan apa pun, dan menuntut
        // keduanya ada: satu tahap menampung banyak kandidat, jadi tahap
        // sendirian akan memulangkan hasil psikotes milik orang lain.
        $query = array_filter([
            'tahap' => $idPenjadwalanTahap,
            'lamaran' => $lamaranId,
        ]);

        // Menggambar donat DISC, jaring PAPI, dan lajur Kraeplin memakan waktu
        // lebih lama daripada menerbitkan token, jadi anggaran waktunya
        // dinaikkan HANYA untuk panggilan ini lalu dikembalikan seperti semula
        // — supaya penjadwalan tidak diam-diam ikut menunggu selama itu.
        $semula = config('hclearn.timeout');
        config(['hclearn.timeout' => (int) config('hclearn.laporan_timeout', 60)]);

        try {
            $balasan = (new HclClient())
                ->sebagaiPengguna($penggunaId)
                ->get($path, $query, ['konteks' => 'LAPORAN_TES', 'peserta' => $idPenjadwalanPeserta]);
        } finally {
            config(['hclearn.timeout' => $semula]);
        }

        if (! ($balasan['sukses'] ?? false)) {
            // Status dicatat untuk dilaporkan ke layar — lihat catatan pada
            // self::$status.
            $st = (int) ($balasan['status'] ?? 0);

            self::$status[] = [
                'ok' => false,
                'peserta' => $idPenjadwalanPeserta,
                'status' => $st,
                // 404 = tesnya memang belum dikerjakan (wajar). Selain itu
                // sambungannya yang bermasalah, dan itu HARUS terlihat admin:
                // berkas yang tercetak tanpa hasil psikotes terlihat sama
                // persis dengan berkas kandidat yang belum tes.
                'jenis' => $st === 404 ? 'belum' : ($st === 0 ? 'jaringan' : 'galat'),
                'pesan' => (string) ($balasan['message'] ?? ''),
            ];

            // Peringatan, bukan galat: peserta yang tesnya memang belum
            // dikerjakan akan menjawab 404 di sini, dan itu keadaan wajar
            // sepanjang masa seleksi berlangsung.
            Log::channel('web_career')->warning('[LAPORAN-TES] gagal mengambil rincian', [
                'peserta' => $idPenjadwalanPeserta,
                'tahap' => $idPenjadwalanTahap,
                'lamaran' => $lamaranId,
                'status' => $balasan['status'] ?? 0,
                'pesan' => $balasan['message'] ?? '-',
            ]);

            return null;
        }

        self::$status[] = ['ok' => true, 'peserta' => $idPenjadwalanPeserta];

        $hasil = $balasan['result'] ?? null;

        return is_array($hasil) && isset($hasil['laporan']) ? $hasil : null;
    }
}
