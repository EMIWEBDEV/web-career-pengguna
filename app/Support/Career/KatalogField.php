<?php

namespace App\Support\Career;

/**
 * WEB CAREER — Cerminan katalog tipe field untuk sisi server.
 *
 * Sumber kebenarannya tetap resources/js/components/career/formulir/inti/katalogField.js
 * — editor dan renderer membacanya langsung. Berkas ini ada karena penegakan
 * tidak boleh cuma di browser: skema bisa dikirim ke endpoint simpan/publish
 * tanpa lewat editor sama sekali.
 *
 * KEDUANYA DIJAGA TETAP SAMA oleh tests/Unit/KatalogFieldSinkronTest.php, yang
 * membaca berkas JS-nya dan membandingkan daftar tipe serta properti per tipe.
 * Mengubah salah satu tanpa yang lain akan memerahkan uji itu.
 */
class KatalogField
{
    /** Berlaku untuk semua tipe. Urutannya harus sama dengan PROPERTI_UNIVERSAL di JS. */
    public const UNIVERSAL = [
        'field_id',
        'key',
        'label',
        'tipe',
        'wajib',
        'bantuan',
        'penuh',
        'lebar_persen',
        'lebar_jika',
        'tampil_jika',
        // Wajib bersyarat. Universal seperti `tampil_jika` karena pertanyaannya
        // sama untuk tipe apa pun: "kapan isian ini mengikat?"
        'wajib_jika',
        // Penanda bahwa key-nya diketik sendiri admin, bukan turunan label.
        'key_manual',
    ];

    /** Properti per tipe. Urutan tipe DAN urutan properti harus sama dengan JS. */
    public const DEFINISI = [
        'text' => ['properti' => ['ph', 'maks_panjang', 'hanya_angka', 'beda_dengan', 'prefill', 'dapat_disaring']],
        'textarea' => ['properti' => ['ph', 'maks_panjang']],
        'number' => ['properti' => ['ph', 'min', 'maks', 'desimal', 'prefill', 'dapat_disaring']],
        // Tanpa min/maks: renderernya el-input biasa berformat Rupiah, bukan
        // el-input-number, jadi keduanya tidak punya pembaca.
        'currency' => ['properti' => ['ph', 'dapat_disaring']],
        'date' => ['properti' => ['ph', 'prefill', 'dapat_disaring']],
        'bulan' => ['properti' => ['ph', 'prefill', 'dapat_disaring']],
        // Tanpa min/maks: el-date-picker type="year" tidak membacanya.
        'tahun' => ['properti' => ['ph', 'prefill', 'dapat_disaring']],
        'select' => ['properti' => ['ph', 'opsi', 'sumber_opsi', 'bebas_ketik', 'reset_anak', 'prefill', 'dapat_disaring']],
        'radio' => ['properti' => ['opsi', 'prefill', 'dapat_disaring']],
        'checkbox' => ['properti' => ['opsi', 'dapat_disaring']],
        'file' => ['properti' => ['accept', 'maks_mb']],
        'daftar' => ['properti' => ['ph', 'min_butir', 'maks_butir']],
        'foto' => ['properti' => []],
        'phone' => ['properti' => ['ph', 'beda_dengan', 'prefill', 'dapat_disaring']],
        'email' => ['properti' => ['ph', 'prefill']],
        'consent' => ['properti' => []],
        'referensi' => ['properti' => ['sumber', 'bergantung', 'saring', 'ph', 'ph_terkunci', 'bebas_ketik', 'reset_anak', 'prefill', 'dapat_disaring']],
        'prefill' => ['properti' => ['prefill', 'tipe_buka', 'buka_jika']],
    ];

    public static function tipeValid(): array
    {
        return array_keys(self::DEFINISI);
    }

    public static function properti(string $tipe): array
    {
        return self::DEFINISI[$tipe]['properti'] ?? [];
    }

    public static function bolehPunya(string $tipe, string $prop): bool
    {
        return in_array($prop, self::UNIVERSAL, true) || in_array($prop, self::properti($tipe), true);
    }

    /**
     * Pemeriksaan per tipe. Pesannya sengaja sama persis dengan versi JS-nya —
     * admin yang menembus validasi browser tidak boleh mendapat kalimat berbeda
     * untuk kesalahan yang sama. Tipe yang tidak punya `periksa` di JS (currency,
     * tahun, dsb.) juga tidak punya di sini.
     */
    public static function galat(array $field): ?string
    {
        $tipe = (string) ($field['tipe'] ?? 'text');

        return match ($tipe) {
            'select', 'radio', 'checkbox' => self::galatOpsi($field),
            'file' => empty($field['accept']) ? 'Format berkas yang diterima belum diatur.' : null,
            'referensi' => empty($field['sumber']) ? 'Sumber master data referensi belum dipilih.' : null,
            'prefill' => empty($field['prefill']) ? 'Sumber isi otomatis belum dipilih.' : null,
            'number' => self::galatRentang($field),
            default => null,
        };
    }

    private static function galatOpsi(array $field): ?string
    {
        if (! empty($field['sumber_opsi'])) {
            return null;
        }

        $opsi = array_filter(is_array($field['opsi'] ?? null) ? $field['opsi'] : []);

        return count($opsi) >= 2 ? null : 'Minimal harus ada 2 opsi pilihan.';
    }

    private static function galatRentang(array $field): ?string
    {
        $min = $field['min'] ?? null;
        $maks = $field['maks'] ?? null;
        if (is_numeric($min) && is_numeric($maks) && $min > $maks) {
            return 'Nilai minimum tidak boleh lebih besar dari maksimum.';
        }

        return null;
    }
}
