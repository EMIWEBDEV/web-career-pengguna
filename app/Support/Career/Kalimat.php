<?php

namespace App\Support\Career;

/**
 * "KALIMAT SUNGGUHAN" — penjaga isian penjelasan bebas dari kandidat.
 *
 * Dipakai untuk penjelasan alasan "Lainnya" saat kandidat tidak melanjutkan
 * seleksi (keputusan user 2 Okt 2026): minimal sekian karakter saja tidak
 * cukup — "aaaaaaaaaaaaaaa", "asdf asdf asdf", atau ketikan acak lolos dari
 * batas panjang. Yang diminta adalah kalimat yang bisa dibaca tim, seperti
 * deretan kata sungguhan di monkeytype.
 *
 * Urutan pemeriksaan:
 *   1. panjang minimal (setelah spasi dirapikan);
 *   2. tidak ada huruf/tanda yang diulang 4× berturut-turut;
 *   3. minimal 3 kata (≥ 2 huruf), dan katanya tidak itu-itu saja (tak ada
 *      satu kata yang muncul ≥ 3 kali sekaligus > 30% isi);
 *   4. sebagian besar berupa huruf, bukan angka/simbol;
 *   5. kata "acak" (tanpa vokal, 5+ konsonan beruntun, pola baris papan ketik
 *      seperti "asdf"/"qwer") tidak lebih dari seperempat — kata yang DIKENAL
 *      kamus (mis. singkatan "skrg", "sblm") tidak dihitung acak;
 *   6. memuat setidaknya satu kata umum (saya, karena, di, tidak, kerja, …);
 *   7. KAMUS (keputusan user 2 Okt 2026, nspell): minimal 60% kata isi
 *      (≥ 3 huruf) dikenal kamus Indonesia/Inggris/tambahan, dan minimal dua
 *      kata isi dikenal — kata yang tidak dikenal disebutkan, seperti
 *      monkeytype menandai salah ketik. Kamusnya lihat Kamus.
 * Bila daftar kata kamus belum terpasang, langkah 7 dilewati (dicatat di log)
 * — tanda-tanda 1–6 tetap menjaga.
 *
 * CERMIN: resources/js/utils/career/kalimat.js — layar memberi tahu lebih
 * awal, server ini yang memutuskan. Ubah keduanya bersamaan.
 */
final class Kalimat
{
    /** Kata yang hampir selalu muncul dalam penjelasan singkat (Indonesia + sedikit Inggris). */
    public const KATA_UMUM = [
        // kata ganti & sapaan
        'saya', 'aku', 'kami', 'kita', 'kamu', 'anda', 'dia', 'beliau', 'mereka', 'sy', 'gw', 'gue',
        // kata sambung & depan
        'dan', 'atau', 'yang', 'di', 'ke', 'dari', 'untuk', 'dengan', 'pada', 'dalam', 'oleh', 'karena', 'sebab',
        'karna', 'krn', 'jadi', 'sehingga', 'tapi', 'tetapi', 'namun', 'agar', 'supaya', 'kalau', 'jika', 'bila',
        'apabila', 'saat', 'ketika', 'sejak', 'setelah', 'sebelum', 'selama', 'sampai', 'hingga', 'bahwa', 'serta',
        'maka', 'lalu', 'kemudian', 'sedangkan', 'walaupun', 'meskipun', 'akibat', 'demi', 'tentang', 'terhadap',
        'antara', 'bagi', 'per', 'sama', 'seperti',
        // keterangan & kata kerja bantu
        'tidak', 'tak', 'belum', 'bukan', 'sudah', 'telah', 'akan', 'sedang', 'masih', 'bisa', 'dapat', 'harus',
        'wajib', 'perlu', 'ingin', 'mau', 'ada', 'ini', 'itu', 'juga', 'lagi', 'pun', 'saja', 'hanya', 'sangat',
        'lebih', 'kurang', 'sekali', 'mungkin', 'memang', 'baru', 'lama', 'segera', 'mendadak', 'tiba', 'nanti',
        'sekarang', 'besok', 'hari', 'minggu', 'bulan', 'tahun', 'waktu', 'jadwal', 'mohon', 'maaf', 'terima',
        'kasih', 'tolong', 'banyak', 'semua', 'beberapa', 'lain', 'lainnya', 'sendiri', 'baik', 'buruk', 'jauh',
        'dekat', 'tetap', 'pernah', 'selalu', 'sering', 'kembali', 'mulai', 'ikut', 'mengikuti', 'melanjutkan',
        'lanjut', 'berhenti', 'mundur', 'batal', 'membatalkan', 'memutuskan', 'keputusan', 'memilih', 'pilihan',
        // topik yang lazim pada alasan mundur
        'kerja', 'bekerja', 'pekerjaan', 'kantor', 'perusahaan', 'posisi', 'jabatan', 'tawaran', 'diterima',
        'menerima', 'gaji', 'penempatan', 'lokasi', 'tempat', 'kota', 'luar', 'daerah', 'rumah', 'pindah',
        'keluarga', 'orang', 'tua', 'ayah', 'ibu', 'anak', 'istri', 'suami', 'menikah', 'nikah', 'sakit',
        'kesehatan', 'rawat', 'kuliah', 'kampus', 'studi', 'sekolah', 'pendidikan', 'beasiswa', 'urusan',
        'pribadi', 'kondisi', 'keadaan', 'alasan', 'proses', 'seleksi', 'lamaran', 'rekrutmen', 'tim', 'bidang',
        'minat', 'sesuai', 'cocok', 'kontrak', 'izin', 'usaha', 'bisnis', 'jarak', 'transportasi', 'biaya',
        // Inggris dasar
        'i', 'my', 'me', 'am', 'is', 'are', 'was', 'have', 'has', 'got', 'because', 'the', 'and', 'to', 'for',
        'not', 'no', 'job', 'offer', 'family', 'work', 'moving', 'health', 'other', 'another', 'company', 'position',
        'personal', 'reason', 'study', 'with', 'from', 'can', 'cannot', 'will',
    ];

    /** Baris papan ketik — empat huruf berurutan dari sini adalah tanda ketikan asal. */
    private const BARIS_KIBOR = ['qwertyuiop', 'asdfghjkl', 'zxcvbnm'];

    public const GALAT_ACAK = 'Teksnya belum terbaca sebagai kalimat. Ceritakan dengan kata-kata biasa, misalnya: '
        .'"Saya diterima bekerja di perusahaan lain sehingga tidak bisa melanjutkan."';

    /**
     * null = lolos; selain itu kalimat galat yang bisa langsung dibaca kandidat.
     */
    public static function periksa(?string $teks, int $min = 15): ?string
    {
        if (! Kamus::siap()) {
            self::lapor('Daftar kata kamus belum terpasang (npm run kamus) — pemeriksaan kamus dilewati.');

            return self::nilai($teks, $min, null);
        }

        return self::nilai($teks, $min, fn (string $kata) => Kamus::kenal($kata));
    }

    /**
     * Inti pemeriksaan. `$kenal` = penilai "kata sungguhan" (Kamus::kenal),
     * atau null = tanpa kamus — dipisah supaya bisa diuji dengan kamus tiruan.
     */
    public static function nilai(?string $teks, int $min, ?callable $kenal): ?string
    {
        $t = trim((string) preg_replace('/\s+/u', ' ', strip_tags((string) $teks)));

        if (mb_strlen($t) < $min) {
            return "Tulis minimal {$min} karakter — ceritakan apa yang terjadi dan kenapa.";
        }
        if (preg_match('/(.)\1{3,}/u', $t)) {
            return 'Hindari huruf atau tanda yang diulang-ulang — tulis kalimat yang sebenarnya.';
        }

        preg_match_all('/\p{L}+/u', mb_strtolower($t), $m);
        $kata = array_values(array_filter($m[0], fn ($k) => mb_strlen($k) >= 2));
        if (count($kata) < 3) {
            return 'Tulis dalam bentuk kalimat, minimal 3 kata — bukan satu-dua kata saja.';
        }
        $unik = array_unique($kata);
        // Satu kata mendominasi ("kerja kerja kerja kerja di rumah") juga
        // diulang-ulang; dua kali (reduplikasi "akhir akhir") tetap wajar.
        $terbanyak = max(array_count_values($kata));
        if (count($unik) < 3 || count($unik) < count($kata) / 2 || ($terbanyak >= 3 && $terbanyak > count($kata) * 0.3)) {
            return 'Hindari kata yang diulang-ulang — tulis kalimat yang sebenarnya.';
        }

        $huruf = preg_match_all('/\p{L}/u', $t);
        $isi = mb_strlen((string) preg_replace('/\s/u', '', $t));
        if ($huruf < $isi * 0.7) {
            return 'Tulis dengan kata-kata, bukan deretan angka atau simbol.';
        }

        $acak = count(array_filter($kata, fn ($k) => self::acak($k) && ! ($kenal && $kenal($k))));
        if ($acak / count($kata) > 0.25) {
            return self::GALAT_ACAK;
        }
        if (! array_intersect($unik, self::KATA_UMUM)) {
            return self::GALAT_ACAK;
        }

        if ($kenal) {
            $kataIsi = array_values(array_filter($kata, fn ($k) => mb_strlen($k) >= 3));
            $dikenal = 0;
            $asing = [];
            foreach ($kataIsi as $k) {
                if ($kenal($k)) {
                    $dikenal++;
                } elseif (! in_array($k, $asing, true)) {
                    $asing[] = $k;
                }
            }
            // ≥ 60% dikenal — dihitung dengan bilangan bulat, sama persis dengan kalimat.js.
            if ($dikenal < 2 || $dikenal * 5 < count($kataIsi) * 3) {
                return self::galatAsing($asing);
            }
        }

        return null;
    }

    /** Kalimat galat kamus — menyebut sampai tiga kata yang tidak dikenali. */
    public static function galatAsing(array $asing): string
    {
        if (! $asing) {
            return 'Tulis kalimat yang lebih jelas — minimal dua kata bermakna tentang apa yang terjadi.';
        }
        $daftar = implode(', ', array_map(fn ($a) => '"'.$a.'"', array_slice($asing, 0, 3)));

        return "Ada kata yang tidak dikenali: {$daftar}. Periksa ejaannya, atau tulis dengan kata-kata biasa.";
    }

    /** Peringatan ke log aplikasi — senyap bila dijalankan di luar Laravel (uji unit). */
    private static function lapor(string $pesan): void
    {
        static $sudah = false;
        if ($sudah) {
            return;
        }
        $sudah = true;
        try {
            \Illuminate\Support\Facades\Log::warning('[KALIMAT] '.$pesan);
        } catch (\Throwable $e) {
            // Di luar aplikasi (uji unit) tidak ada log — pemeriksaan tetap jalan.
        }
    }

    /** Satu kata terlihat seperti ketikan acak. */
    private static function acak(string $kata): bool
    {
        if (mb_strlen($kata) >= 4 && ! preg_match('/[aiueo]/u', $kata)) {
            return true;
        }
        if (preg_match('/[bcdfghjklmnpqrstvwxyz]{5,}/u', $kata)) {
            return true;
        }
        foreach (self::BARIS_KIBOR as $baris) {
            foreach ([$baris, strrev($baris)] as $b) {
                for ($i = 0; $i + 4 <= strlen($b); $i++) {
                    if (str_contains($kata, substr($b, $i, 4))) {
                        return true;
                    }
                }
            }
        }

        return false;
    }
}
