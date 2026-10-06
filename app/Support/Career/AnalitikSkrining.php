<?php

namespace App\Support\Career;

use Illuminate\Support\Facades\DB;

/**
 * WEB CAREER — ANALITIK PHONE SCREENING per loker.
 *
 * Menjawab tiga hal yang benar-benar dipakai rekruter, bukan tiga hal yang
 * kebetulan mudah dihitung:
 *
 *   1. Berapa yang sudah diskrining, dan bagaimana sebaran rekomendasinya.
 *   2. PERTANYAAN MANA yang paling sering menggugurkan orang.
 *   3. Jawaban apa yang paling sering muncul di tiap pertanyaan berskor.
 *
 * Nomor 2 adalah alasan seluruh kelas ini ada. Kalau 40 dari 50 pelamar gugur
 * di "bersedia relokasi", yang salah bukan pelamarnya — yang salah adalah
 * lokernya diiklankan tanpa menyebut penempatannya. Angka itu tidak pernah
 * terlihat selama hasil skrining cuma tersimpan sebagai catatan bebas.
 *
 * ── KENAPA MEMBACA SALINAN, BUKAN MASTER ────────────────────────────────────
 *
 * Seluruh agregat di sini dibaca dari kolom `*_Snapshot` di baris jawaban,
 * bukan dari Master_Skrining_Pertanyaan. Template boleh sudah direvisi tujuh
 * kali sejak pelamar-pelamar ini diskrining; yang harus dihitung adalah
 * pertanyaan yang BENAR-BENAR ditanyakan kepada mereka.
 *
 * Penyatuan lintas versi tetap mungkin karena `Kode_Snapshot` stabil — itulah
 * gunanya Bank Pertanyaan memaksa kode yang seragam.
 */
class AnalitikSkrining
{
    /**
     * Ringkasan skrining satu loker pada satu pembukaan.
     *
     * Mengembalikan null bila loker ini memang tidak punya tahap skrining —
     * layar cukup memeriksa satu kunci untuk tahu perlu menggambar kartunya
     * atau tidak, sepola `Skrining::bentuk()`.
     */
    public static function loker(int $pembukaanId, int $posisiId): ?array
    {
        if (! Skrining::siap()) {
            return null;
        }

        $lamaranIds = DB::table('N_WEB_CAREERS_Lamaran')
            ->where('Pembukaan_Id', $pembukaanId)
            ->where('Program_Posisi_Id', $posisiId)
            ->pluck('Id_Lamaran');

        if ($lamaranIds->isEmpty()) {
            return null;
        }

        $sesi = DB::table(Skrining::T_SESI)
            ->whereIn('Lamaran_Id', $lamaranIds)
            ->whereNull('Arsip_At')
            ->get();

        if ($sesi->isEmpty()) {
            return null;
        }

        $selesai = $sesi->where('Status', 'SELESAI');

        return [
            'total' => $sesi->count(),
            'selesai' => $selesai->count(),
            'berjalan' => $sesi->where('Status', 'DRAF')->count(),
            'template' => self::template($sesi),
            'rekomendasi' => self::rekomendasi($sesi),
            'skor' => self::skor($selesai),
            'kontak' => self::kontak($sesi),
            'knockout' => self::knockout($sesi),
            'pertanyaan' => self::pertanyaan($sesi->pluck('Id_Lamaran_Skrining')),
        ];
    }

    /**
     * Template yang dipakai — bisa LEBIH DARI SATU.
     *
     * Pelamar yang masuk sebelum pengikatan diganti membawa template lamanya,
     * dan itu memang benar. Menampilkannya sebagai daftar, bukan satu nama,
     * mencegah orang menyimpulkan seluruh angka di kartu ini berasal dari
     * kuesioner yang sama.
     */
    private static function template($sesi): array
    {
        return $sesi->groupBy(fn ($s) => $s->Skrining_Kode.' v'.$s->Skrining_Versi)
            ->map(fn ($g, $k) => [
                'label' => ($g->first()->Nama_Snapshot ?: $g->first()->Skrining_Kode).' v'.$g->first()->Skrining_Versi,
                'kode' => $g->first()->Skrining_Kode,
                'versi' => (int) $g->first()->Skrining_Versi,
                'jml' => $g->count(),
            ])
            ->values()
            ->all();
    }

    /** Sebaran rekomendasi petugas. Yang belum memutuskan ikut dihitung. */
    private static function rekomendasi($sesi): array
    {
        $peta = [
            'LANJUT' => ['label' => 'Lanjut', 'warna' => '#059669'],
            'PERTIMBANGAN' => ['label' => 'Pertimbangan', 'warna' => '#f59e0b'],
            'TIDAK_LANJUT' => ['label' => 'Tidak dilanjutkan', 'warna' => '#dc2626'],
        ];

        $out = [];
        foreach ($peta as $kode => $m) {
            $n = $sesi->where('Rekomendasi', $kode)->count();
            $out[] = ['kode' => $kode, 'label' => $m['label'], 'warna' => $m['warna'], 'jml' => $n];
        }

        $belum = $sesi->whereNull('Rekomendasi')->count();
        if ($belum > 0) {
            $out[] = ['kode' => null, 'label' => 'Belum diputuskan', 'warna' => '#94a3b8', 'jml' => $belum];
        }

        return $out;
    }

    /**
     * Sebaran skor sesi yang SUDAH SELESAI.
     *
     * Yang masih berjalan sengaja tidak ikut: persentasenya dihitung dari
     * pertanyaan yang baru sebagian terjawab, dan memasukkannya akan menarik
     * rata-rata ke bawah dengan angka yang belum jadi.
     */
    private static function skor($selesai): array
    {
        $nilai = $selesai->pluck('Skor_Persen')->filter(fn ($x) => $x !== null)->map(fn ($x) => (float) $x)->values();

        if ($nilai->isEmpty()) {
            return ['ada' => false, 'rata' => null, 'min' => null, 'maks' => null, 'sebaran' => []];
        }

        // Lima keranjang 20%. Cukup untuk melihat bentuk sebarannya tanpa
        // membuat batang-batang setinggi satu piksel di data 12 orang.
        $keranjang = [
            ['label' => '0–20%', 'jml' => 0], ['label' => '21–40%', 'jml' => 0],
            ['label' => '41–60%', 'jml' => 0], ['label' => '61–80%', 'jml' => 0],
            ['label' => '81–100%', 'jml' => 0],
        ];

        foreach ($nilai as $n) {
            $i = min(4, (int) floor(max(0, $n - 0.01) / 20));
            $keranjang[$i]['jml']++;
        }

        return [
            'ada' => true,
            'rata' => round($nilai->avg(), 1),
            'min' => round($nilai->min(), 1),
            'maks' => round($nilai->max(), 1),
            'sebaran' => $keranjang,
        ];
    }

    /**
     * Seberapa sering panggilannya nyambung.
     *
     * Angka yang jarang dicari sampai seseorang bertanya kenapa skrining satu
     * loker berjalan lambat — dan jawabannya ternyata separuh pelamarnya tidak
     * pernah mengangkat telepon.
     */
    private static function kontak($sesi): array
    {
        $peta = [
            'TERHUBUNG' => 'Terhubung',
            'TIDAK_TERHUBUNG' => 'Tidak terhubung',
            'DIJADWAL_ULANG' => 'Dijadwalkan ulang',
            'MENOLAK' => 'Menolak',
        ];

        $out = [];
        foreach ($peta as $kode => $label) {
            $n = $sesi->where('Hasil_Kontak', $kode)->count();
            if ($n > 0) {
                $out[] = ['kode' => $kode, 'label' => $label, 'jml' => $n];
            }
        }

        $percobaan = $sesi->pluck('Percobaan')->filter(fn ($x) => $x !== null)->map(fn ($x) => (int) $x);

        return [
            'sebaran' => $out,
            'rataPercobaan' => $percobaan->isEmpty() ? null : round($percobaan->avg(), 1),
        ];
    }

    /** Berapa yang ditandai gugur, dan oleh pertanyaan apa. */
    private static function knockout($sesi): array
    {
        $kena = $sesi->where('Knockout_Flag', 'Y');

        return [
            'jml' => $kena->count(),
            'persen' => $sesi->count() > 0 ? round($kena->count() / $sesi->count() * 100, 1) : 0,
            'perPertanyaan' => $kena->whereNotNull('Knockout_Kode')
                ->groupBy('Knockout_Kode')
                ->map(fn ($g, $kode) => [
                    'kode' => $kode,
                    'pesan' => $g->first()->Knockout_Pesan,
                    'jml' => $g->count(),
                ])
                ->sortByDesc('jml')
                ->values()
                ->all(),
        ];
    }

    /**
     * Sebaran jawaban per pertanyaan.
     *
     * Hanya pertanyaan BEROPSI dan BERSKALA — jawaban esai tidak punya sebaran
     * yang berarti, dan menampilkan "12 jawaban unik dari 12 orang" cuma
     * memenuhi layar dengan angka yang tidak menjawab apa pun.
     *
     * Dibaca dari Kode_Snapshot, jadi pertanyaan yang sama tetap menyatu
     * sekalipun pelamarnya diskrining dengan versi template yang berbeda.
     */
    private static function pertanyaan($sesiIds): array
    {
        if ($sesiIds->isEmpty()) {
            return [];
        }

        $rows = DB::table(Skrining::T_JAWAB)
            ->whereIn('Lamaran_Skrining_Id', $sesiIds)
            ->whereNotNull('Jawaban')
            ->where('Jawaban', '<>', '')
            ->whereIn('Tipe_Snapshot', ['RADIO', 'SELECT', 'BOOLEAN', 'RATING', 'LIKERT', 'NPS'])
            ->get([
                'Kode_Snapshot', 'Label_Snapshot', 'Tipe_Snapshot', 'Seksi_Snapshot',
                'Urutan', 'Jawaban_Teks', 'Nilai', 'Knockout_Snapshot',
            ]);

        return $rows->groupBy('Kode_Snapshot')
            ->map(function ($g) {
                $p = $g->first();

                $sebaran = $g->groupBy(fn ($x) => $x->Jawaban_Teks ?: '(kosong)')
                    ->map(fn ($k, $label) => ['label' => (string) $label, 'jml' => $k->count()])
                    ->sortByDesc('jml')
                    ->values()
                    ->all();

                $nilai = $g->pluck('Nilai')->filter(fn ($x) => $x !== null)->map(fn ($x) => (float) $x);

                return [
                    'kode' => $p->Kode_Snapshot,
                    'label' => $p->Label_Snapshot,
                    'seksi' => $p->Seksi_Snapshot,
                    'tipe' => $p->Tipe_Snapshot,
                    'urutan' => (int) $p->Urutan,
                    'knockout' => ($p->Knockout_Snapshot ?? 'T') === 'Y',
                    'jml' => $g->count(),
                    'rataNilai' => $nilai->isEmpty() ? null : round($nilai->avg(), 2),
                    'sebaran' => $sebaran,
                ];
            })
            ->sortBy('urutan')
            ->values()
            ->all();
    }
}
