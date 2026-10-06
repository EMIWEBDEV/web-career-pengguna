<?php

namespace App\Support\Career;

use Carbon\Carbon;

/**
 * WEB CAREER — Field turunan.
 *
 * Field yang TIDAK ditanyakan ke kandidat, tapi dihitung sistem dari jawaban
 * lain. Ada dua alasan kenapa ini perlu:
 *
 *   1. Data yang basi kalau diketik.
 *      Menyuruh kandidat mengetik "usia" berarti angkanya salah tahun depan.
 *      Yang benar disimpan tanggal lahir, usianya dihitung saat dievaluasi.
 *
 *   2. Field yang di formulir sengaja bercabang.
 *      Pertanyaan pendidikan berbeda per jalur: pelamar perguruan tinggi
 *      ditanya fakultas, pelamar SMK ditanya jurusan sekolah. Syarat
 *      "jurusan = Teknik Mesin" tidak akan kena keduanya. Field turunan
 *      menyatukannya jadi satu nama yang bisa dirujuk admin.
 *
 *      Sumber lama `jenjang_politeknik` & `jenjang_universitas` tetap
 *      terdaftar walau formulir sekarang memakai `jenjang_pendidikan` —
 *      pengisian yang sudah tersimpan sebelum penyatuan itu harus tetap
 *      bisa dievaluasi ulang tanpa ditulis ulang.
 *
 * Bagi admin, hasil turunan terlihat seperti field biasa di daftar pilihan
 * syarat — bedanya ditandai "otomatis" supaya jelas ini bukan ketikan kandidat.
 */
class FieldTurunan
{
    /**
     * Definisi field turunan.
     *
     * sumber : field yang harus ada di jawaban agar turunan ini dihitung
     * label  : nama yang dibaca admin saat menyusun syarat
     * tipe   : ANGKA | TEKS | TANGGAL  (menentukan cara membandingkan)
     */
    public const DEFINISI = [
        'usia' => [
            // Formulir memakai key 'lahir'; canonical 'tanggal_lahir' & 'tgl_lahir'
            // tetap didukung supaya syarat lama tak perlu diubah.
            'sumber' => ['tanggal_lahir', 'lahir', 'tgl_lahir'],
            'label' => 'Usia (otomatis dari tanggal lahir)',
            'tipe' => 'ANGKA',
            'satuan' => 'tahun',
        ],
        'jenjang' => [
            // Dua nama field baru didahulukan — 'jenjang_pendidikan' dari blok
            // pendidikan bersama, 'jenjang' dari cascade formulir apply. Dua
            // nama lama disisakan agar lamaran yang sudah tersimpan tetap terbaca.
            'sumber' => ['jenjang_pendidikan', 'jenjang', 'jenjang_politeknik', 'jenjang_universitas'],
            'label' => 'Jenjang Pendidikan (gabungan semua jalur)',
            'tipe' => 'TEKS',
            'satuan' => null,
        ],
        'jurusan_gabungan' => [
            'sumber' => ['jurusan', 'fakultas'],
            'label' => 'Jurusan / Fakultas (gabungan)',
            'tipe' => 'TEKS',
            'satuan' => null,
        ],
    ];

    /**
     * Hitung seluruh field turunan dari satu set jawaban.
     *
     * @param  array  $jawaban  isi Jawaban_Json
     * @return array  [key => nilai]  hanya yang berhasil dihitung
     */
    public static function hitung(array $jawaban): array
    {
        $out = [];

        foreach (self::DEFINISI as $key => $def) {
            $nilai = self::hitungSatu($key, $jawaban);
            if ($nilai !== null && $nilai !== '') {
                $out[$key] = $nilai;
            }
        }

        return $out;
    }

    /** @return string|int|float|null */
    private static function hitungSatu(string $key, array $jawaban)
    {
        switch ($key) {
            case 'usia':
                foreach (self::DEFINISI['usia']['sumber'] as $s) {
                    $v = $jawaban[$s] ?? null;
                    if ($v !== null && trim((string) $v) !== '') {
                        return self::usia($v);
                    }
                }

                return null;

            case 'jenjang':
            case 'jurusan_gabungan':
                // Ambil isian pertama yang tidak kosong dari daftar sumbernya.
                // Field bercabang memang cuma satu yang terisi.
                foreach (self::DEFINISI[$key]['sumber'] as $s) {
                    $v = $jawaban[$s] ?? null;
                    if ($v !== null && trim((string) $v) !== '') {
                        return trim((string) $v);
                    }
                }

                return null;

            default:
                return null;
        }
    }

    /** Usia penuh dalam tahun pada saat dihitung. */
    private static function usia($tanggalLahir): ?int
    {
        if (! $tanggalLahir) {
            return null;
        }

        try {
            $lahir = Carbon::parse($tanggalLahir);
        } catch (\Throwable $e) {
            return null;
        }

        // Tanggal di masa depan jelas salah input — jangan mengarang angka.
        if ($lahir->isFuture()) {
            return null;
        }

        return $lahir->age;
    }

    /**
     * Apakah sebuah formulir (lewat daftar key field-nya) punya sumber yang
     * dibutuhkan field turunan tertentu? Dipakai mencegah admin menyusun
     * syarat berbasis field turunan (mis. "usia") untuk formulir yang field
     * pertanyaannya tidak memakai salah satu nama key yang dikenali —
     * turunannya tidak akan pernah terhitung, dan syarat itu diam-diam
     * menggugurkan semua orang.
     */
    public static function sumberTersedia(string $turunanKey, array $keyTersedia): bool
    {
        $sumber = self::DEFINISI[$turunanKey]['sumber'] ?? null;
        if ($sumber === null) {
            // Bukan field turunan yang dikenal — tidak ada yang bisa diperiksa.
            return true;
        }

        return (bool) array_intersect($sumber, $keyTersedia);
    }

    /** Daftar untuk dropdown penyusun syarat. */
    public static function daftar(): array
    {
        $out = [];
        foreach (self::DEFINISI as $key => $def) {
            $out[] = [
                'key' => $key,
                'label' => $def['label'],
                'tipe' => $def['tipe'],
                'satuan' => $def['satuan'],
                'turunan' => true,
            ];
        }

        return $out;
    }
}
