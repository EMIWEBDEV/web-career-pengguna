<?php

namespace App\Support\Career;

/**
 * WEB CAREER — Kosakata isi-otomatis (prefill).
 *
 * Field formulir boleh menandai `prefill: '<kunci>'` untuk terisi sendiri dari
 * data yang sudah dimiliki sistem. Kuncinya bukan sembarang nama: ia harus ada
 * di array profil yang dikirim controller ke halaman formulir.
 *
 * Sebelum katalog ini, daftar kunci ditulis lepas di dua tempat yang berbeda —
 * CareerLandingController untuk formulir pendaftaran, LamaranController untuk
 * formulir tahap — dan keduanya TIDAK SAMA: `nik` tidak sampai ke formulir
 * tahap, `kampus` dan `tahunLulus` tidak ada saat mendaftar karena kandidat
 * memang belum menjawabnya. Admin yang memilih kunci salah tidak mendapat
 * peringatan apa pun: kolomnya sekadar tampil kosong.
 *
 * KONTEKS adalah jawabannya. Formulir menyatakan dipakai di mana (schema.konteks),
 * dan katalog ini menentukan kunci apa yang tersedia di sana.
 */
class KatalogPrefill
{
    public const PENDAFTARAN = 'PENDAFTARAN';

    public const TAHAP = 'TAHAP';

    public const KEDUANYA = 'KEDUANYA';

    /**
     * kunci   : nama yang ditulis di `prefill` pada field
     * label   : yang dibaca admin di dropdown
     * konteks : di formulir mana kunci ini benar-benar terisi
     * ket     : dari mana nilainya diambil — supaya admin tahu apa yang ia pilih
     */
    public const DEFINISI = [
        'nama' => [
            'label' => 'Nama Lengkap',
            'konteks' => [self::PENDAFTARAN, self::TAHAP],
            'ket' => 'Nama pada akun kandidat.',
        ],
        'email' => [
            'label' => 'Email',
            'konteks' => [self::PENDAFTARAN, self::TAHAP],
            'ket' => 'Email akun kandidat.',
        ],
        'hp' => [
            'label' => 'No. Handphone',
            'konteks' => [self::PENDAFTARAN, self::TAHAP],
            'ket' => 'Nomor pada tabel pengguna, bukan sesi login.',
        ],
        'nik' => [
            'label' => 'NIK',
            // Tersedia di kedua konteks: NIK sudah terisi sejak pendaftaran,
            // jadi formulir tahap tidak perlu menanyakannya lagi. Kandidat
            // hanya melihat NIK miliknya sendiri, bukan NIK orang lain.
            'konteks' => [self::PENDAFTARAN, self::TAHAP],
            'ket' => 'NIK pada tabel pengguna.',
        ],
        'posisi' => [
            'label' => 'Posisi yang Dilamar',
            // Di pendaftaran dari kartu lowongan yang diklik kandidat; di tahap
            // dari data lamaran. Nilainya posisi yang sama, jadi aman untuk KEDUANYA.
            'konteks' => [self::PENDAFTARAN, self::TAHAP],
            'ket' => 'Posisi yang dilamar kandidat.',
        ],
        'tglLahir' => [
            'label' => 'Tanggal Lahir',
            'konteks' => [self::TAHAP],
            'ket' => 'Diambil dari jawaban formulir pendaftaran lamaran ini.',
        ],
        'jkel' => [
            'label' => 'Jenis Kelamin',
            'konteks' => [self::TAHAP],
            'ket' => 'Diambil dari jawaban formulir pendaftaran lamaran ini.',
        ],
        'kampus' => [
            'label' => 'Nama Kampus / Sekolah',
            // Belum ada saat mendaftar — pertanyaannya justru diajukan di
            // formulir pendaftaran itu sendiri.
            'konteks' => [self::TAHAP],
            'ket' => 'Diambil dari jawaban formulir pendaftaran lamaran ini.',
        ],
        'tahunLulus' => [
            'label' => 'Tahun Lulus / Perkiraan Lulus',
            'konteks' => [self::TAHAP],
            'ket' => 'Diambil dari jawaban formulir pendaftaran lamaran ini.',
        ],
        'jurusan' => [
            'label' => 'Jurusan / Program Studi',
            'konteks' => [self::TAHAP],
            'ket' => 'Diambil dari jawaban formulir pendaftaran lamaran ini.',
        ],
        'jenjang' => [
            'label' => 'Jenjang Pendidikan',
            'konteks' => [self::TAHAP],
            'ket' => 'Diambil dari jawaban formulir pendaftaran lamaran ini.',
        ],
        'ipk' => [
            'label' => 'IPK',
            'konteks' => [self::TAHAP],
            'ket' => 'Diambil dari jawaban formulir pendaftaran lamaran ini.',
        ],
        'statusStudi' => [
            'label' => 'Status Studi',
            'konteks' => [self::TAHAP],
            'ket' => 'Diambil dari jawaban formulir pendaftaran lamaran ini.',
        ],
        'semester' => [
            'label' => 'Semester Berjalan',
            'konteks' => [self::TAHAP],
            'ket' => 'Diambil dari jawaban formulir pendaftaran lamaran ini.',
        ],
    ];

    /**
     * Kunci yang tersedia untuk sebuah konteks formulir.
     *
     * KEDUANYA mengembalikan IRISAN, bukan gabungan: formulir yang dipakai di
     * dua tempat tidak boleh memakai kunci yang di salah satunya selalu kosong.
     */
    public static function kunciUntuk(string $konteksFormulir): array
    {
        $konteks = strtoupper($konteksFormulir);

        $butuh = match ($konteks) {
            self::PENDAFTARAN => [self::PENDAFTARAN],
            self::TAHAP => [self::TAHAP],
            default => [self::PENDAFTARAN, self::TAHAP],
        };

        $out = [];
        foreach (self::DEFINISI as $kunci => $def) {
            if (! array_diff($butuh, $def['konteks'])) {
                $out[] = $kunci;
            }
        }

        return $out;
    }

    /**
     * Bentuk array profil yang dikirim ke halaman formulir.
     *
     * Hanya memuat kunci yang sah untuk konteksnya, dan SELALU memuat semuanya —
     * kunci yang nilainya belum ada diisi null, bukan dihilangkan, supaya bentuk
     * profil tidak berubah-ubah tergantung isi database.
     */
    public static function saring(string $konteks, array $nilai): array
    {
        $out = [];
        foreach (self::kunciUntuk($konteks) as $kunci) {
            $out[$kunci] = $nilai[$kunci] ?? null;
        }

        return $out;
    }
}
