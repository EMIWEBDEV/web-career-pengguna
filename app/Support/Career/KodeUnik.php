<?php

namespace App\Support\Career;

use Illuminate\Support\Facades\DB;

/**
 * WEB CAREERS — pembuat KODE unik dari sebuah nama.
 *
 * MASALAH YANG DIPERBAIKI
 * Sebelumnya tiap modul menulis sendiri: `substr($base, 0, 26)` untuk kolom
 * varchar(30) — memangkas 4 karakter "berjaga-jaga" untuk sufiks `_N`, PADAHAL
 * sufiks hanya dibutuhkan bila kodenya bentrok. Akibatnya nama yang panjangnya
 * pas malah kehilangan huruf terakhirnya:
 *
 *   "EVO MANAGEMENT 2026 Batch 1"  →  EVO_MANAGEMENT_2026_BATCH_1 (27 char, muat)
 *   tetapi tersimpan sebagai       →  EVO_MANAGEMENT_2026_BATCH_  ← angka "1" hilang
 *
 * Selain jelek dibaca, ini berbahaya: "Batch 1" dan "Batch 2" sama-sama terpotong
 * jadi kode dasar yang identik, dan relasi antar-modul (jadwal ↔ alur) jadi
 * membingungkan karena kodenya tidak lagi mencerminkan namanya.
 *
 * CARA KERJA SEKARANG
 * Pakai lebar kolom SEPENUHNYA. Potong hanya bila memang perlu ruang untuk
 * sufiks, dan hanya sebanyak panjang sufiks itu sendiri.
 */
class KodeUnik
{
    /**
     * @param  string  $tabel     tabel yang kodenya harus unik
     * @param  string  $kolom     nama kolom kode
     * @param  string  $nama      teks sumber (nama/kegiatan)
     * @param  int     $maks      lebar kolom (mis. 30 untuk varchar(30))
     * @param  string  $cadangan  dipakai bila nama tidak menyisakan karakter apa pun
     * @param  int|null $kecualiId  id yang diabaikan saat cek bentrok (untuk update)
     * @param  string|null $kolomId nama kolom primary key, wajib bila $kecualiId diisi
     */
    public static function buat(
        string $tabel,
        string $kolom,
        string $nama,
        int $maks,
        string $cadangan = 'KODE',
        ?int $kecualiId = null,
        ?string $kolomId = null
    ): string {
        $base = trim(preg_replace('/[^A-Z0-9]+/', '_', strtoupper($nama)), '_');
        $base = $base !== '' ? $base : $cadangan;

        $ada = function (string $kode) use ($tabel, $kolom, $kecualiId, $kolomId): bool {
            $q = DB::table($tabel)->where($kolom, $kode);
            if ($kecualiId !== null && $kolomId !== null) {
                $q->where($kolomId, '!=', $kecualiId);
            }

            return $q->exists();
        };

        // Percobaan pertama: pakai lebar kolom penuh, tanpa memangkas cadangan.
        $kode = substr($base, 0, $maks);
        if (! $ada($kode)) {
            return $kode;
        }

        // Bentrok → baru sisakan ruang, sebanyak panjang sufiksnya saja.
        for ($n = 2; $n < 10000; $n++) {
            $sufiks = '_' . $n;
            $kode = substr($base, 0, max(1, $maks - strlen($sufiks))) . $sufiks;
            if (! $ada($kode)) {
                return $kode;
            }
        }

        // Sangat tidak mungkin tercapai; jaring pengaman agar tidak mengembalikan
        // kode yang sudah dipakai.
        return substr($base, 0, max(1, $maks - 7)) . '_' . substr((string) time(), -6);
    }
}
