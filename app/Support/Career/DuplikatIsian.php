<?php

namespace App\Support\Career;

/**
 * WEB CAREER — pendeteksi isian kembar antar-formulir.
 *
 * Satu lamaran kerap punya dua formulir yang menanyakan hal yang sama dengan
 * kata berbeda. Di produksi:
 *
 *   Formulir Pendaftaran          Kelengkapan Data Diri
 *   ─────────────────────         ─────────────────────
 *   Nama Lengkap Sesuai KTP   →   Nama Lengkap
 *   Email                     →   Email Terdaftar
 *   No Handphone Aktif (WA)   →   No. WhatsApp Terdaftar
 *
 * Ketiganya tercetak dua kali di berkas seleksi, dan pembaca tidak punya cara
 * tahu apakah itu memang dua jawaban berbeda atau satu jawaban yang diulang.
 *
 * ── DICOCOKKAN DARI NILAI, BUKAN DARI LABEL ────────────────────────────────
 *
 * Label tidak bisa diandalkan: "Email" dan "Email Terdaftar" mirip, tapi
 * "No Handphone Aktif (WA)" dan "No. WhatsApp Terdaftar" nyaris tidak berbagi
 * satu kata pun. Sebaliknya, NILAI yang sama persis adalah bukti kuat bahwa
 * dua isian menjawab hal yang sama — dan bila nilainya BERBEDA, keduanya
 * memang layak dicetak dua-duanya.
 *
 * Kesamaan label tetap dipakai sebagai penguat, tapi tidak pernah sendirian.
 *
 * ── YANG TIDAK DIANGGAP KEMBAR ─────────────────────────────────────────────
 *
 * Jawaban ya/tidak dan nilai yang sangat pendek dibuang dari pencocokan:
 * belasan pertanyaan berjawab "Ya" bukan berarti belasan isian kembar. Sama
 * halnya dengan isian kosong.
 *
 * ── SISTEM TIDAK MEMILIH SENDIRI ───────────────────────────────────────────
 *
 * Kelas ini hanya MELAPORKAN. Yang memutuskan mana yang dicetak adalah admin,
 * lewat centang tingkat field yang sudah ada — karena "mana yang benar" kadang
 * bergantung pada hal yang tidak tersimpan di mana pun: formulir mana yang
 * lebih baru, mana yang sudah diverifikasi, mana yang dipakai kontrak.
 */
class DuplikatIsian
{
    /** Nilai sependek ini tidak dipakai mencocokkan — terlalu mudah kebetulan sama. */
    private const PANJANG_MIN = 4;

    /** Jawaban baku yang berulang di banyak pertanyaan; bukan penanda kembar. */
    private const NILAI_UMUM = [
        'ya', 'tidak', 'y', 't', 'true', 'false', 'sudah', 'belum',
        'sesuai', 'bersedia', 'setuju', 'ada', 'lainnya', 'tidak ada',
    ];

    /**
     * Cari kelompok isian yang menjawab hal yang sama.
     *
     * @param  list<array>  $formulir  hasil LaporanKandidat::rakit()['formulir']
     * @return list<array{
     *     kunci: string,
     *     nilai: string,
     *     anggota: list<array{kunci:string, label:string, formulir:string, bagian:string}>
     * }>
     */
    public static function cari(array $formulir): array
    {
        $peta = [];

        foreach ($formulir as $f) {
            $kunciForm = 'formulir.' . \Vinkla\Hashids\Facades\Hashids::encode($f['pengisianId'] ?? 0);

            foreach ($f['bagian'] ?? [] as $b) {
                foreach ($b['isian'] ?? [] as $i) {
                    // Bagian berulang tidak ikut: isinya daftar, dan dua daftar
                    // yang kebetulan berisi hal sama bukan isian kembar
                    // melainkan riwayat yang memang berulang.
                    if (! empty($i['baris'])) {
                        continue;
                    }

                    $nilai = self::normalNilai($i['nilai'] ?? null);

                    if ($nilai === null) {
                        continue;
                    }

                    $key = (string) ($i['key'] ?? '');

                    if ($key === '') {
                        continue;
                    }

                    $peta[$nilai][] = [
                        // Kunci field yang sama dengan yang dipakai centang di
                        // Export Studio — lihat BerkasSeleksi::bagianFormulir().
                        'kunci' => ($b['kunci'] ?? $kunciForm) . '.' . md5($key),
                        'label' => (string) ($i['label'] ?? $key),
                        'formulir' => (string) ($f['label'] ?? ''),
                        'bagian' => (string) ($b['judul'] ?? ''),
                    ];
                }
            }
        }

        $hasil = [];

        foreach ($peta as $nilai => $anggota) {
            if (count($anggota) < 2) {
                continue;
            }

            // Kembar DALAM SATU formulir dibiarkan: perancang formulir boleh
            // menanyakan hal sama dua kali dengan maksud (mis. konfirmasi
            // email), dan itu bukan pengulangan yang perlu dilaporkan.
            if (count(array_unique(array_column($anggota, 'formulir'))) < 2) {
                continue;
            }

            $hasil[] = [
                'kunci' => md5($nilai),
                // Nilai yang SUDAH dinormalkan — dipakai penyaring di
                // BerkasSeleksi tanpa harus menormalkannya ulang.
                'kunciNilai' => $nilai,
                'nilai' => $nilai,
                'anggota' => $anggota,
            ];
        }

        // Yang anggotanya paling banyak lebih dulu — itu yang paling mencolok
        // di dokumen, dan paling layak diputuskan admin lebih awal.
        usort($hasil, fn ($a, $b) => count($b['anggota']) <=> count($a['anggota']));

        return $hasil;
    }

    /**
     * Sidik jari nilai untuk pencocokan — dipakai bersama BerkasSeleksi.
     *
     * Publik karena laporan duplikat harus disaring terhadap nilai yang
     * BENAR-BENAR tercetak, dan penyaringan itu wajib memakai aturan
     * normalisasi yang sama persis. Dua aturan yang mirip-tapi-beda akan
     * menghasilkan kelompok yang lolos saring padahal seharusnya tidak.
     */
    public static function sidik(mixed $v): string
    {
        return self::normalNilai($v) ?? '';
    }

    /**
     * Nilai yang dipakai mencocokkan, atau null bila tidak layak dicocokkan.
     *
     * Dinormalkan seperlunya: beda spasi dan huruf besar-kecil bukan beda
     * jawaban. Angka dilucuti pemisah ribuan supaya "6.700.000" dan "6700000"
     * dikenali sama — dua formulir kerap memformatnya berbeda.
     */
    private static function normalNilai(mixed $v): ?string
    {
        $t = trim((string) ($v ?? ''));

        if ($t === '') {
            return null;
        }

        $n = mb_strtolower(preg_replace('/\s+/u', ' ', $t));

        if (in_array($n, self::NILAI_UMUM, true)) {
            return null;
        }

        // Angka: buang titik/koma/spasi pemisah supaya format tidak memisahkan
        // dua jawaban yang sebenarnya sama.
        $angka = preg_replace('/[.,\s]/u', '', $n);

        if ($angka !== '' && ctype_digit($angka)) {
            $n = $angka;
        }

        return mb_strlen($n) < self::PANJANG_MIN ? null : $n;
    }
}
