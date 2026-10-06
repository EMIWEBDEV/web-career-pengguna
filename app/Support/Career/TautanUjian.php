<?php

namespace App\Support\Career;

/**
 * WEB CAREER — ALAMAT UJIAN YANG DIBUKA KANDIDAT.
 *
 * ══ MASALAHNYA ═════════════════════════════════════════════════════════════
 *
 * `Penjadwalan_Peserta.Link_Ujian` disimpan apa adanya dari balasan CAT, dan
 * CAT menyusunnya memakai alamat DIRINYA SENDIRI. Akibatnya host di dalam
 * tautan itu bukan fakta tentang kandidat, melainkan jejak konfigurasi mesin
 * yang kebetulan menekan tombol Generate.
 *
 * Menjadwalkan dari laptop yang `HCLEARN_ENV`-nya masih `development`
 * menuliskan `http://cat-evo-pembaharuan.test/...` ke baris peserta — di basis
 * data yang sama yang dibaca produksi. Tautan itu lalu dikirim ke kandidat,
 * disalin admin, dan dibuka berminggu-minggu kemudian dari komputer mana pun
 * di luar sana, tempat domain `.test` tidak pernah bisa dijangkau.
 *
 * Kesalahannya tidak berbunyi apa-apa saat terjadi. Ia baru muncul sebagai
 * "kandidat tidak bisa membuka ujian", jauh dari sebabnya.
 *
 * ══ PEMISAHAN YANG DIPAKAI DI SINI ═════════════════════════════════════════
 *
 * Ada DUA alamat CAT, dan keduanya tidak harus sama:
 *
 *   1. KANAL API (`hclearn.domains[env]`) — dipakai HclClient untuk bicara
 *      server-ke-server lewat HMAC. Ini yang berganti-ganti per lingkungan.
 *   2. ALAMAT UJIAN (`hclearn.exam_url`) — yang DIBUKA MANUSIA di peramban.
 *      Ini menghadap publik, jadi bawaannya selalu domain produksi.
 *
 *  Yang tersimpan di baris peserta tetap utuh; yang diganti cuma bagian
 *  alamatnya saat disajikan. Jalur (`/evo/rekrutmen`) dan kredensial di
 *  kueri (`?wo_aut=…&otp=…`) TIDAK PERNAH disentuh — keduanya milik CAT, dan
 *  menebak-nebaknya berarti menerbitkan tautan yang bentuknya benar tapi
 *  isinya salah.
 *
 * ══ YANG TIDAK DIJANJIKAN KELAS INI ════════════════════════════════════════
 *
 * Ia memperbaiki ALAMAT, bukan asal-usul token. Token yang diterbitkan
 * instance CAT lain tetap tidak akan dikenali di domain ini — tautannya jadi
 * rapi tapi tetap ditolak. Itu perkara konfigurasi kanal API, bukan perkara
 * tautan, dan memang tidak bisa diselesaikan di sini.
 */
final class TautanUjian
{
    /**
     * Kembalikan tautan dengan alamat yang MENGHADAP PUBLIK.
     *
     * Aman dipanggil pada nilai apa pun: kosong tetap kosong, dan tautan yang
     * tidak bisa diurai dikembalikan apa adanya. Tautan yang cacat lebih
     * berguna daripada tautan yang hilang — yang pertama bisa ditelusuri, yang
     * kedua tidak meninggalkan jejak sama sekali.
     */
    public static function publik(?string $link): ?string
    {
        $link = trim((string) $link);
        if ($link === '') {
            return null;
        }

        $dasar = rtrim((string) config('hclearn.exam_url', ''), '/');
        if ($dasar === '') {
            return $link;
        }

        $bagian = parse_url($link);
        if ($bagian === false) {
            return $link;
        }

        // Tautan relatif ('/evo/rekrutmen?...') — tinggal diberi alamat.
        if (empty($bagian['host'])) {
            return $dasar.'/'.ltrim($link, '/');
        }

        $ekor = ($bagian['path'] ?? '')
            .(isset($bagian['query']) ? '?'.$bagian['query'] : '')
            .(isset($bagian['fragment']) ? '#'.$bagian['fragment'] : '');

        return $dasar.$ekor;
    }

    /**
     * Host yang dipakai saat ini — untuk keterangan di layar & skrip SQL.
     */
    public static function host(): string
    {
        return (string) parse_url((string) config('hclearn.exam_url', ''), PHP_URL_HOST);
    }
}
