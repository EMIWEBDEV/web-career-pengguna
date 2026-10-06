<?php

namespace App\Support\Career;

/**
 * WEB CAREER — NAMA RESMI kandidat, diambil dari FORMULIR.
 *
 * Nama AKUN diketik saat mendaftar dan kerap seadanya ("salni", "andi123",
 * "09wdoijadjoiajsdo"); yang dipakai seluruh dokumen resmi justru yang ditulis
 * kandidat di formulir. Satu tempat untuk kartu worklist, drawer, undangan,
 * dan PDF biodata — kalau masing-masing menebak sendiri, satu orang akan
 * disebut dengan nama berbeda di tiga layar.
 *
 * ── KENAPA BERJENJANG ──────────────────────────────────────────────────────
 * Formulirnya DIRANCANG LEWAT LAYAR. Master kunci identitas hanya berisi apa
 * yang sempat didaftarkan admin — di data sekarang baru `nama` dan
 * `nama_lengkap`, sementara formulir MT yang berjalan justru memakai `v_nama`.
 * Mengandalkan master saja membuat kandidat yang formulirnya memakai kunci
 * baru diam-diam kembali disebut dengan nama akunnya, tanpa satu pun galat.
 *
 *   1. MASTER (Kode='NAMA') — yang memang diakui admin, urut Urutan.
 *   2. SKEMA yang dibekukan saat formulir dikirim — field teks yang
 *      pertanyaannya memang menanyakan nama kandidat.
 *   3. KUNCI JAWABAN yang berbunyi nama, untuk pengisian lama yang tidak
 *      menyimpan snapshot skema.
 *   4. Tidak ada → null, dan pemanggil memakai nama akun sebagai cadangan.
 */
class IdentitasKandidat
{
    /**
     * Nama kandidat dari satu pengisian formulir.
     *
     * @param  array  $jawaban  isi Jawaban_Json yang sudah di-decode
     * @param  ?string  $snapshotJson  isi Schema_Snapshot_Json, bila ada
     */
    /**
     * Langkah PERTAMA nama() saja — kunci dari master, tanpa snapshot skema.
     * Dipakai pemuat massal: kandidat yang namanya sudah ketemu di sini tidak
     * perlu snapshot skemanya dibaca sama sekali (JSON 5–22 KB per pengisian).
     */
    public static function namaDariMaster(array $jawaban): ?string
    {
        foreach (self::kunciMaster() as $k) {
            if ($v = self::teks($jawaban[$k] ?? null)) {
                return $v;
            }
        }

        return null;
    }

    public static function nama(array $jawaban, ?string $snapshotJson = null): ?string
    {
        foreach (self::kunciMaster() as $k) {
            if ($v = self::teks($jawaban[$k] ?? null)) {
                return $v;
            }
        }

        foreach (self::kunciSkema($snapshotJson) as $k) {
            if ($v = self::teks($jawaban[$k] ?? null)) {
                return $v;
            }
        }

        foreach ($jawaban as $k => $isi) {
            if (self::berbunyiNama((string) $k) && ($v = self::teks($isi))) {
                return $v;
            }
        }

        return null;
    }

    /**
     * Daftar kunci nama dari master — lewat pemuat LamaranService, yang membaca
     * seluruh jenis identitas dalam satu kueri per permintaan. Papan worklist
     * memakai keduanya; dua pemuat terpisah berarti tabel yang sama dua kali.
     */
    private static function kunciMaster(): array
    {
        return LamaranService::kunciIdentitas('NAMA');
    }

    /**
     * Kunci bertipe teks di skema yang pertanyaannya menanyakan NAMA KANDIDAT.
     *
     * Dibaca dari SNAPSHOT, bukan skema yang berlaku sekarang: pertanyaannya
     * bisa sudah diganti nama setelah kandidat menjawab.
     */
    private static function kunciSkema(?string $json): array
    {
        $skema = json_decode($json ?: '', true);
        if (! is_array($skema)) {
            return [];
        }

        $keys = [];
        foreach ($skema['langkah'] ?? [] as $langkah) {
            foreach ($langkah['bagian'] ?? [] as $bagian) {
                // Bagian BERULANG dilewati seluruhnya: "Nama Sertifikasi" dan
                // "Nama Kontak Darurat" hidup di sana, dan keduanya nama
                // sesuatu yang lain — bukan nama kandidat.
                if (! empty($bagian['berulang'])) {
                    continue;
                }

                foreach ($bagian['field'] ?? [] as $f) {
                    $key = (string) ($f['key'] ?? '');
                    $tipe = mb_strtolower((string) ($f['tipe'] ?? 'text'));

                    if ($key === '' || ! in_array($tipe, ['text', 'prefill', ''], true)) {
                        continue;
                    }
                    if (self::berbunyiNama($key . ' ' . ($f['label'] ?? ''))) {
                        $keys[] = $key;
                    }
                }
            }
        }

        return array_values(array_unique($keys));
    }

    /**
     * Pertanyaan/kunci ini menanyakan NAMA KANDIDAT — bukan nama benda lain.
     *
     * Penolakannya lebih penting daripada penerimaannya: formulir penuh berisi
     * "Nama Kampus", "Nama Kontak Darurat", "Nama Sertifikasi", dan salah satu
     * saja yang lolos akan membuat kartu worklist menyebut kandidat dengan
     * nama universitasnya.
     */
    private static function berbunyiNama(string $teks): bool
    {
        $t = mb_strtolower(trim($teks));

        if (preg_match('/kampus|institusi|sekolah|universitas|jurusan|prodi|fakultas'
            . '|darurat|ibu|ayah|wali|orang\s*tua|saudara|keluarga|kenalan|relasi'
            . '|bank|rekening|perusahaan|atasan|kantor|instansi'
            . '|sertifik|pelatihan|organisasi|jabatan|posisi|program|dokumen|berkas|file'
            . '|kota|provinsi|tempat|pengguna|akun/i', $t)) {
            return false;
        }

        // `[\s_-]*` bukan `\s*`: kuncinya ditulis `full_name`, bukan "full name".
        return (bool) preg_match('/(^|[^a-z])nama([^a-z]|$)|full[\s_-]*name/i', $t);
    }

    /** Nilai yang layak dipakai sebagai nama. */
    private static function teks(mixed $v): ?string
    {
        if (! is_string($v)) {
            return null;
        }

        $v = trim(preg_replace('/\s+/', ' ', $v) ?? '');

        return $v !== '' ? $v : null;
    }
}
