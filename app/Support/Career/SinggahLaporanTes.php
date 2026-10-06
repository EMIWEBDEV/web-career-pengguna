<?php

namespace App\Support\Career;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * WEB CAREER — singgahan PDF laporan psikotes.
 *
 * ── MASALAH YANG DISELESAIKAN ──────────────────────────────────────────────
 *
 * Merender satu berkas seleksi memakan ~46 detik, dan 33 detik di antaranya
 * habis di dompdf mengolah tiga laporan psikotes (TIU 8,9s · DISC 11,2s ·
 * PAPI 13,1s). Angka itu berulang UTUH setiap kali berkas yang sama dicetak —
 * padahal isinya identik: nilai tes tidak pernah berubah setelah tes selesai.
 *
 * Sepuluh rekruter membuka kandidat yang sama berarti sepuluh kali merender
 * halaman yang byte-nya sama persis. Justru pada saat paling ramai — ketika
 * server paling perlu ringan — beban itu berlipat.
 *
 * Dengan singgahan ini render pertama tetap 33 detik; sesudahnya nyaris nol,
 * dan seluruh berkas selesai di ~13 detik. Itu yang membuatnya muat di bawah
 * batas 60 detik Cloud Run tanpa menyentuh satu pun setelan infrastruktur.
 *
 * ── KENAPA AMAN DISINGGAHKAN ───────────────────────────────────────────────
 *
 * Yang disimpan adalah PDF hasil render, dan bentuknya ditentukan tiga hal:
 * data nilai (final setelah tes ditutup), templat blade, dan logo. Kunci
 * singgahannya memuat sidik jari ketiganya — begitu salah satunya berubah,
 * kuncinya ikut berubah dan berkas lama ditinggalkan begitu saja. Tidak ada
 * jalan bagi laporan usang untuk tercetak diam-diam.
 *
 * ── KENAPA DI GCS, BUKAN CACHE APLIKASI ────────────────────────────────────
 *
 * Cloud Run menjalankan banyak instance, dan tiap instance punya memorinya
 * sendiri. Cache dalam proses berarti tiap instance merender ulang untuk
 * pertama kalinya — persis masalah yang ingin dihindari saat ramai. GCS
 * dibaca sama oleh semua instance, dan berkasnya memang sudah dipakai
 * menyimpan hasil ekspor.
 */
class SinggahLaporanTes
{
    /** Folder di disk GCS. Terpisah dari hasil ekspor supaya mudah dibersihkan. */
    private const FOLDER = 'berkas-seleksi/singgahan-psikotes';

    /**
     * Ambil dari singgahan, atau render lalu simpan.
     *
     * Kegagalan menyimpan TIDAK menggagalkan pencetakan: yang dikembalikan
     * tetap PDF hasil render. Singgahan adalah percepatan, bukan syarat —
     * penyimpanan yang sedang bermasalah tidak boleh berarti berkas seleksi
     * tidak bisa dicetak sama sekali.
     *
     * @param  array  $lap  satu elemen `laporan` dari payload CAT
     * @param  callable():?string  $render  perender, dipanggil hanya bila perlu
     */
    /**
     * Jumlah halaman laporan psikotes dari pencetakan terakhir.
     *
     * ── KENAPA DICATAT ────────────────────────────────────────────────────
     *
     * Pratinjau tidak merender laporan psikotes (17 detik per panggilan CAT),
     * jadi jumlah halamannya tidak bisa dihitung di sana. Tanpa angka ini
     * penandanya dihitung 1 halaman, dan seluruh nomor halaman sesudahnya
     * meleset — pratinjau 22 halaman untuk berkas yang tercetak 39.
     *
     * Angkanya dicatat saat berkas benar-benar diunduh, lalu dipakai
     * pratinjau berikutnya. Kandidat yang belum pernah dicetak memakai
     * taksiran umum di bawah.
     *
     * Disimpan di cache aplikasi, bukan GCS: yang disimpan satu bilangan,
     * dan kehilangannya hanya membuat penomoran kembali ke taksiran.
     */
    /**
     * Daftar laporan yang dipulangkan CAT untuk satu peserta, dari
     * pencetakan terakhir.
     *
     * ── KENAPA DAFTAR, BUKAN SATU ANGKA ───────────────────────────────────
     *
     * Satu peserta bisa memulangkan BEBERAPA laporan: ujian gabungan
     * menghasilkan TIU + Kraeplin sekaligus, dan pada lamaran 594 satu tahap
     * memulangkan TIU, DISC, dan PAPI Kostick — tiga dokumen terpisah.
     *
     * Pratinjau yang hanya membuat SATU penanda per peserta menampilkan dua
     * penanda untuk tiga laporan, dan tak satu pun menyebut tes apa. Bab Hasil
     * Seleksi lalu terlihat kosong — justru bagian yang paling perlu diperiksa
     * admin sebelum memutus kelulusan.
     *
     * @return list<array{nama: string, halaman: int}>
     */
    public static function laporanTersimpan(int $idPeserta, ?int $idTahap): array
    {
        $isi = self::bacaTeks(self::kunciDaftar($idPeserta, $idTahap));

        if ($isi === '') {
            return [];
        }

        $data = json_decode($isi, true);

        if (! is_array($data)) {
            return [];
        }

        $out = [];

        foreach ($data as $d) {
            if (! is_array($d)) {
                continue;
            }

            $out[] = [
                'nama' => (string) ($d['nama'] ?? 'Hasil Tes'),
                'halaman' => max(1, (int) ($d['halaman'] ?? 1)),
            ];
        }

        return $out;
    }

    /** Catat daftar laporan sesudah pencetakan sungguhan. */
    public static function catatDaftar(int $idPeserta, ?int $idTahap, array $daftar): void
    {
        if (! $daftar) {
            return;
        }

        self::tulisTeks(
            self::kunciDaftar($idPeserta, $idTahap),
            (string) json_encode(array_values($daftar), JSON_UNESCAPED_UNICODE),
        );
    }

    private static function kunciDaftar(int $idPeserta, ?int $idTahap): string
    {
        return self::FOLDER . '/daftar/' . $idPeserta . '-' . (int) $idTahap . '.json';
    }

    public static function halamanTersimpan(int $idPeserta, ?int $idTahap): int
    {
        // GCS, bukan cache aplikasi. `cache.default` di mesin pengembangan
        // adalah `array` — mati begitu prosesnya selesai, sehingga angka yang
        // dicatat saat unduhan tidak pernah sampai ke pratinjau berikutnya.
        // Membangun penomoran di atas cache yang mungkin tidak persisten
        // berarti fiturnya bekerja di produksi dan diam-diam tidak bekerja di
        // tempat lain.
        $n = (int) self::bacaAngka(self::kunciHalaman($idPeserta, $idTahap));

        // Taksiran umum bila belum pernah dicetak: laporan psikotes EVO Group
        // berkisar 8-16 halaman (TIU 4, Kraeplin 3, DISC 6, PAPI 5, dan satu
        // ujian bisa memuat dua instrumen). Delapan dipilih supaya melesetnya
        // ke arah "pratinjau lebih pendek", yang lebih jujur daripada
        // menjanjikan halaman yang ternyata tidak ada.
        return $n > 0 ? $n : 8;
    }

    /** Catat jumlah halaman sesudah laporan benar-benar dirender. */
    public static function catatHalaman(int $idPeserta, ?int $idTahap, int $jumlah): void
    {
        if ($jumlah < 1) {
            return;
        }

        self::tulisAngka(self::kunciHalaman($idPeserta, $idTahap), $jumlah);
    }

    private static function kunciHalaman(int $idPeserta, ?int $idTahap): string
    {
        return self::FOLDER . '/halaman/' . $idPeserta . '-' . (int) $idTahap . '.txt';
    }

    /** Baca satu bilangan dari GCS; 0 bila belum ada atau tak terbaca. */
    private static function bacaAngka(string $path): int
    {
        return (int) trim(self::bacaTeks($path));
    }

    /** Baca berkas teks kecil dari GCS; '' bila belum ada atau tak terbaca. */
    private static function bacaTeks(string $path): string
    {
        try {
            $disk = Storage::disk('gcs');

            return $disk->exists($path) ? (string) $disk->get($path) : '';
        } catch (\Throwable $e) {
            // Penyimpanan tak terjangkau bukan alasan menggagalkan pratinjau —
            // penomoran cukup kembali ke taksiran.
            return '';
        }
    }

    /** Tulis satu bilangan ke GCS. Gagal tulis diabaikan. */
    private static function tulisAngka(string $path, int $nilai): void
    {
        self::tulisTeks($path, (string) $nilai);
    }

    /** Tulis berkas teks kecil ke GCS. Gagal tulis diabaikan. */
    private static function tulisTeks(string $path, string $isi): void
    {
        try {
            Storage::disk('gcs')->put($path, $isi);
        } catch (\Throwable $e) {
            Log::channel('web_career')->warning('[SINGGAH-PSIKOTES] gagal mencatat', [
                'path' => $path,
                'pesan' => $e->getMessage(),
            ]);
        }
    }

    public static function ambil(array $lap, ?string $logo, callable $render): ?string
    {
        $kunci = self::kunci($lap, $logo);

        if ($kunci === null) {
            // Tanpa penanda yang bisa dipercaya, lebih baik merender ulang
            // daripada menyimpan berkas yang tidak bisa dikenali lagi nanti.
            return $render();
        }

        $path = self::FOLDER . '/' . $kunci . '.pdf';

        try {
            $disk = Storage::disk('gcs');

            if ($disk->exists($path)) {
                $isi = $disk->get($path);

                // Berkas kosong berarti penyimpanan sebelumnya terputus di
                // tengah jalan. Diperlakukan seperti tidak ada.
                if ($isi !== null && $isi !== '') {
                    return $isi;
                }
            }
        } catch (\Throwable $e) {
            Log::channel('web_career')->warning(
                '[SINGGAH-PSIKOTES] gagal membaca: ' . $e->getMessage(),
                ['kunci' => $kunci],
            );
        }

        $isi = $render();

        if ($isi === null || $isi === '') {
            return $isi;
        }

        try {
            Storage::disk('gcs')->put($path, $isi);
        } catch (\Throwable $e) {
            Log::channel('web_career')->warning(
                '[SINGGAH-PSIKOTES] gagal menyimpan: ' . $e->getMessage(),
                ['kunci' => $kunci],
            );
        }

        return $isi;
    }

    /**
     * Sidik jari satu laporan — berubah begitu apa pun yang membentuknya berubah.
     *
     * Yang ikut dihitung:
     *   • id_jenis_indikator + kode_tipe_hasil — instrumen mana;
     *   • seluruh isi `data` — nilai, grafik, interpretasi. Inilah yang
     *     benar-benar menentukan halamannya, dan menghitungnya utuh membuat
     *     perbaikan nilai di CAT langsung menghasilkan kunci baru;
     *   • berkas blade + waktu ubahnya — perbaikan templat ikut membatalkan
     *     singgahan tanpa perlu ada yang ingat membersihkannya;
     *   • sidik logo — lambang yang diperbarui ikut terbawa.
     *
     * Nomor peserta penjadwalan SENGAJA TIDAK dipakai: nomor itu pernah
     * dimulai ulang di produksi (lihat catatan pada LaporanTesClient), dan
     * kunci yang bertumpu padanya akan menyamakan dua laporan berbeda begitu
     * hal itu terulang.
     */
    private static function kunci(array $lap, ?string $logo): ?string
    {
        $indikator = $lap['id_jenis_indikator'] ?? null;
        $kode = (string) ($lap['kode_tipe_hasil'] ?? '');
        $data = $lap['data'] ?? null;

        if (! $indikator || $kode === '' || ! $data) {
            return null;
        }

        $blade = PetaTemplateTes::blade($kode);
        $berkasBlade = resource_path('views/' . str_replace('.', '/', $blade) . '.blade.php');

        $bahan = [
            'i' => $indikator,
            'k' => $kode,
            // json_encode atas array bersarang: yang dibandingkan isinya,
            // bukan alamat objeknya.
            'd' => json_encode($data),
            'b' => is_file($berkasBlade) ? filemtime($berkasBlade) : 0,
            'l' => $logo ? md5($logo) : '',
        ];

        return hash('sha256', json_encode($bahan));
    }
}
