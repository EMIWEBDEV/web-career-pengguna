<?php

namespace App\Support\Career;

/**
 * WEB CAREER — Mesin evaluasi syarat (auto-gugur).
 *
 * Membaca pohon aturan (Program_Syarat.Aturan_Json) lalu menilainya terhadap
 * jawaban kandidat. Mesin ini SENGAJA tidak memutuskan apa pun sendiri:
 * hasilnya rekomendasi, dan pemanggilnya yang menentukan mau menggugurkan
 * atau sekadar menandai untuk diketuk palu admin.
 *
 * BENTUK POHON ATURAN
 *   simpul grup      : { "penghubung": "DAN"|"ATAU", "aturan": [ ...simpul ] }
 *   simpul kondisi   : { "field": "ipk", "operator": ">=", "nilai": "3.0" }
 *
 * Kenapa pohon, bukan daftar datar: syarat nyata hampir selalu bercampur —
 * "IPK >= 3.0 DAN (jenjang = S1 ATAU jenjang = D4)". Daftar datar tidak bisa
 * menyatakan tanda kurung itu tanpa menambah kolom tiap kali polanya berubah.
 */
class MesinSyarat
{
    public const OPERATOR = ['=', '!=', '>', '<', '>=', '<=', 'ANTARA', 'ADA_DI', 'TIDAK_ADA_DI'];
    public const PENGHUBUNG = ['DAN', 'ATAU'];

    /**
     * Nilai satu aturan terhadap jawaban.
     *
     * @param  array  $aturan   pohon aturan
     * @param  array  $nilai    [field => nilai] termasuk field turunan
     * @return array  ['lolos' => bool, 'jejak' => array]
     */
    public static function nilai(array $aturan, array $nilai): array
    {
        $jejak = [];
        $lolos = self::simpul($aturan, $nilai, $jejak);

        return ['lolos' => $lolos, 'jejak' => $jejak];
    }

    /** Evaluasi rekursif satu simpul; mencatat tiap kondisi ke $jejak. */
    private static function simpul(array $simpul, array $nilai, array &$jejak): bool
    {
        // ── Simpul grup ──
        if (isset($simpul['aturan']) && is_array($simpul['aturan'])) {
            $anak = $simpul['aturan'];
            if (! $anak) {
                // Grup kosong dianggap LOLOS. Aturan yang belum diisi tidak
                // boleh menggugurkan siapa pun.
                return true;
            }

            $penghubung = strtoupper($simpul['penghubung'] ?? 'DAN');

            $mulai = count($jejak);
            $hasil = [];
            foreach ($anak as $a) {
                $hasil[] = self::simpul((array) $a, $nilai, $jejak);
            }
            $lolosGrup = $penghubung === 'ATAU' ? in_array(true, $hasil, true) : ! in_array(false, $hasil, true);

            // Grup ATAU yang LOLOS lewat salah satu cabang: cabang lain yang
            // gagal tidak boleh muncul di penjelasan. Kandidat yang memenuhi
            // "S1 ATAU D4" lewat S1 tidak perlu diberi tahu "kamu bukan D4".
            if ($penghubung === 'ATAU' && $lolosGrup) {
                for ($i = $mulai; $i < count($jejak); $i++) {
                    $jejak[$i]['diabaikan'] = true;
                }
            }

            return $lolosGrup;
        }

        // ── Simpul kondisi ──
        $field = $simpul['field'] ?? null;
        if (! $field) {
            return true;
        }

        $punya = array_key_exists($field, $nilai);
        $kiri = $nilai[$field] ?? null;
        $lolos = $punya ? self::banding($kiri, $simpul['operator'] ?? '=', $simpul['nilai'] ?? null) : false;

        $jejak[] = [
            'field' => $field,
            'operator' => $simpul['operator'] ?? '=',
            'diharapkan' => $simpul['nilai'] ?? null,
            // Nilai kandidat SAAT dievaluasi ikut dicatat. Inilah yang dipakai
            // menjelaskan ke kandidat berbulan-bulan kemudian, sekalipun
            // ambang batasnya sudah diubah sejak saat itu.
            'nilai_kandidat' => $punya ? $kiri : null,
            'ada' => $punya,
            'lolos' => $lolos,
        ];

        return $lolos;
    }

    /** Bandingkan satu nilai. */
    private static function banding($kiri, string $operator, $kanan): bool
    {
        // Jawaban jamak (checkbox) — "=" berarti mengandung.
        if (is_array($kiri)) {
            $ada = in_array((string) $kanan, array_map('strval', $kiri), true);

            return $operator === '!=' ? ! $ada : $ada;
        }

        if ($operator === 'ADA_DI' || $operator === 'TIDAK_ADA_DI') {
            $daftar = array_map(fn ($x) => mb_strtolower(trim((string) $x)), explode(',', (string) $kanan));
            $ada = in_array(mb_strtolower(trim((string) $kiri)), $daftar, true);

            return $operator === 'ADA_DI' ? $ada : ! $ada;
        }

        if ($operator === 'ANTARA') {
            // "20,25" -> 20 <= x <= 25
            [$a, $b] = array_pad(array_map('trim', explode(',', (string) $kanan)), 2, null);
            if (! is_numeric($a) || ! is_numeric($b) || ! is_numeric($kiri)) {
                return false;
            }

            return (float) $kiri >= (float) $a && (float) $kiri <= (float) $b;
        }

        // Bandingkan sebagai ANGKA hanya bila kedua sisi memang angka.
        // Kalau dipaksa jadi teks, '10' < '3' dan syarat IPK/usia jadi salah.
        $angka = is_numeric($kiri) && is_numeric($kanan);
        $a = $angka ? (float) $kiri : mb_strtolower(trim((string) $kiri));
        $b = $angka ? (float) $kanan : mb_strtolower(trim((string) $kanan));

        switch ($operator) {
            case '!=': return $a != $b;
            case '>': return $a > $b;
            case '<': return $a < $b;
            case '>=': return $a >= $b;
            case '<=': return $a <= $b;
            default: return $a == $b;
        }
    }

    /**
     * Rangkum jejak jadi kalimat yang bisa dibaca admin & kandidat.
     * Hanya kondisi yang GAGAL yang disebut — itu yang menjelaskan kenapa gugur.
     */
    public static function ringkas(array $jejak): string
    {
        // Kondisi yang ditandai "diabaikan" berada di dalam grup ATAU yang
        // sudah lolos lewat cabang lain — tidak relevan sebagai alasan gugur.
        $gagal = array_filter($jejak, fn ($j) => ! $j['lolos'] && empty($j['diabaikan']));
        if (! $gagal) {
            return 'Seluruh syarat terpenuhi.';
        }

        $pesan = array_map(function ($j) {
            if (! $j['ada']) {
                return "\"{$j['field']}\" tidak terisi di formulir";
            }
            $punya = is_array($j['nilai_kandidat']) ? implode('/', $j['nilai_kandidat']) : $j['nilai_kandidat'];

            return "{$j['field']} = {$punya} (diminta {$j['operator']} {$j['diharapkan']})";
        }, $gagal);

        return implode('; ', $pesan);
    }

    /**
     * Bersihkan pohon aturan sebelum disimpan: buang simpul kosong dan
     * operator asing. Aturan setengah jadi berbahaya — bisa menggugurkan
     * orang tanpa maksud.
     *
     * @return array|null null bila tidak ada kondisi sah sama sekali
     */
    public static function bersihkan($simpul): ?array
    {
        if (! is_array($simpul)) {
            return null;
        }

        if (isset($simpul['aturan']) && is_array($simpul['aturan'])) {
            $anak = array_values(array_filter(array_map(
                fn ($a) => self::bersihkan($a),
                $simpul['aturan']
            )));

            if (! $anak) {
                return null;
            }

            return [
                'penghubung' => in_array(strtoupper($simpul['penghubung'] ?? 'DAN'), self::PENGHUBUNG, true)
                    ? strtoupper($simpul['penghubung']) : 'DAN',
                'aturan' => $anak,
            ];
        }

        $field = trim((string) ($simpul['field'] ?? ''));
        $operator = (string) ($simpul['operator'] ?? '=');
        $nilai = $simpul['nilai'] ?? null;

        if ($field === '' || ! in_array($operator, self::OPERATOR, true)) {
            return null;
        }
        if ($nilai === null || trim((string) $nilai) === '') {
            return null;
        }

        return ['field' => $field, 'operator' => $operator, 'nilai' => (string) $nilai];
    }

    /** Semua field yang dirujuk sebuah pohon — dipakai memeriksa keterkaitan. */
    public static function fieldDipakai($simpul): array
    {
        if (! is_array($simpul)) {
            return [];
        }
        if (isset($simpul['aturan']) && is_array($simpul['aturan'])) {
            return array_values(array_unique(array_merge(
                ...array_map(fn ($a) => self::fieldDipakai($a), $simpul['aturan'])
            )));
        }

        return isset($simpul['field']) ? [$simpul['field']] : [];
    }
}
