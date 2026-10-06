<?php

namespace App\Support\Career;

/**
 * WEB CAREER — CATATAN EKSTERNAL KEPUTUSAN TAHAP (dibaca kandidat).
 *
 * Setiap keputusan tahap — Lolos, Tidak Lolos, Talent Pool, Mundur, satuan
 * maupun massal — membawa DUA catatan yang tidak boleh tertukar:
 *
 *   Catatan_Html            INTERNAL. Penilaian untuk tim; tidak pernah keluar
 *                           ke kandidat dalam bentuk apa pun.
 *   Catatan_Eksternal_Html  UNTUK KANDIDAT. Di halaman lamarannya ia menjadi
 *                           catatan TAHAP BERIKUTNYA ("kamu lolos, ini catatan
 *                           kami") — atau ikut kartu keputusan bila tidak
 *                           lanjut — setelah hasil tahap itu diumumkan, dan
 *                           ikut di email hasil. Kosong = tidak ada apa-apa.
 *
 * Contoh yang melahirkan fitur ini: dari Seleksi Berkas ke Psikotes, tim perlu
 * memberi tahu link Zoom — sesuatu yang ditujukan ke kandidat, bukan penilaian
 * tentang dia.
 *
 * Kolomnya dibuat skrip docs/28-09-2026/02-catatan-eksternal-tahap.sql. Sebelum
 * skrip dijalankan, siap() = false: tab eksternal tidak ditawarkan dan
 * keputusan tetap berjalan seperti biasa.
 */
final class CatatanEksternal
{
    public const TABEL = 'N_WEB_CAREERS_Lamaran_Tahap';

    public const KOLOM = 'Catatan_Eksternal_Html';

    /** Kolomnya sudah ada di basis data ini? */
    public static function siap(): bool
    {
        try {
            return Skema::adaKolom(self::TABEL, self::KOLOM);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Saring HTML editor untuk disimpan — daftar-izin HtmlBersih, ditambah satu
     * hal: GAMBAR DIBUANG.
     *
     * Gambar catatan disajikan lewat rute admin (/api/v1/karir/lamaran/catatan/
     * gambar/…) yang tidak bisa dibuka kandidat, dan email tidak bisa memuatnya
     * sama sekali. Gambar di catatan eksternal hanya akan jadi kotak rusak di
     * kedua tempat — lebih jujur ditolak sejak disimpan. Editor eksternal di
     * layar pun tidak menawarkan tombol gambar.
     */
    public static function saring(?string $html): ?string
    {
        $bersih = HtmlBersih::saring($html);
        if ($bersih === null) {
            return null;
        }

        // Paragraf yang tadinya hanya berisi gambar ikut dibuang — sisa "<p></p>"
        // tampil sebagai celah kosong di portal.
        $tanpaGambar = trim((string) preg_replace(
            ['/<img\b[^>]*>/i', '#<p>(\s|<br\s*/?>)*</p>#i'],
            '',
            $bersih,
        ));

        return HtmlBersih::keTeks($tanpaGambar) === '' ? null : $tanpaGambar;
    }

    /**
     * HTML catatan → teks untuk EMAIL.
     *
     * Server surat (EVO Mail) sengaja hanya menerima DATA, bukan HTML — kunci
     * API yang bocor tidak boleh bisa menyisipkan markup ke surat resmi. Jadi
     * yang dikirim teks yang strukturnya dipertahankan: paragraf jadi baris
     * kosong, butir daftar jadi "• ", dan tautan tetap membawa alamatnya
     * ("Link Zoom (https://…)") supaya server surat bisa menjadikannya tautan.
     */
    public static function keTeksSurat(?string $html): ?string
    {
        $html = trim((string) $html);
        if ($html === '') {
            return null;
        }

        // Tautan lebih dulu, selagi href-nya masih ada. Hasilnya tetap berupa
        // HTML ter-escape supaya decode di akhir menghasilkan teks yang benar.
        $html = (string) preg_replace_callback('#<a\b[^>]*\bhref="([^"]*)"[^>]*>(.*?)</a>#is', function ($m) {
            $url = html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $teks = trim(html_entity_decode(strip_tags($m[2]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

            return htmlspecialchars($teks === '' || $teks === $url ? $url : "{$teks} ({$url})", ENT_QUOTES, 'UTF-8');
        }, $html);

        $html = (string) preg_replace('#<br\s*/?>#i', "\n", $html);
        $html = (string) preg_replace('#<li\b[^>]*>#i', "\n• ", $html);
        $html = (string) preg_replace('#</(p|h3|h4|blockquote|ul|ol)>#i', "\n\n", $html);

        $teks = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $teks = implode("\n", array_map('trim', explode("\n", str_replace("\u{00A0}", ' ', $teks))));
        $teks = trim((string) preg_replace("/\n{3,}/", "\n\n", $teks));

        return $teks === '' ? null : $teks;
    }

    /**
     * Tab yang terbuka LEBIH DULU di jendela keputusan, menurut kategori yang
     * dipegang akun pada Worklist Pelamar (null = tidak dibatasi).
     *
     *   hanya REKRUTMEN                                  → INTERNAL
     *   MT, Internship, campuran, atau tidak dibatasi    → EKSTERNAL
     *
     * Keputusan user (28 Sep 2026): peserta MT & Internship diarahkan lewat
     * portal, jadi tim mereka paling sering menulis untuk kandidat; rekrutmen
     * lebih sering mencatat penilaian untuk tim. Tab yang lain tetap bisa
     * diklik — ini hanya titik awalnya.
     */
    public static function tabBawaan(?array $kategori): string
    {
        $kat = array_values(array_unique(array_filter(array_map(
            fn ($k) => strtoupper(trim((string) $k)),
            $kategori ?? [],
        ))));

        return $kat === ['REKRUTMEN'] ? 'INTERNAL' : 'EKSTERNAL';
    }
}
