<?php

namespace App\Support\Portal;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * POTRET PORTAL — keadaan satu lamaran sebagaimana dilihat kandidatnya.
 *
 * Ditulis Sync Worker dari zona dalam ke N_WEB_CAREERS_Pub_Portal_Lamaran
 * (aplikasi ini hanya SELECT), satu baris per lamaran, UTUH — bukan potongan
 * perubahan: potret yang terlewat dipulihkan oleh potret berikutnya.
 *
 * KONTRAK 1 (kolom Muatan, JSON). Semua id di dalamnya adalah id ZONA DALAM;
 * kunci peta memakai id mentah (string angka), sedangkan id yang dipakai layar
 * sudah ter-hash dengan salt HASHIDS yang sama di kedua zona.
 *
 *   kontrak        1
 *   kode           Kode lamaran (kunci bersama kedua zona)
 *   lamaran        status, statusLabel, statusNada, urutanTahap, totalTahap,
 *                  hasilAkhir, gugurDi, alasanGugur, batch, penyelenggara
 *   tahapan        [{urutan, label, status, hasil}]   — stepper "Lamaran Saya"
 *   detail         props halaman Career/portal/LamaranDetail yang lahir di
 *                  dalam: {tahap, tugas, konteks, formulir}
 *   prefill        isian otomatis dari formulir pendaftaran: kampus, tahunLulus,
 *                  tglLahir, jkel, jurusan, jenjang, ipk, statusStudi, semester
 *   tahapFormulir  {"<id tahap>": {formulirKode, formulirVersi, kategori,
 *                   terbuka, alasanTutup, batasIsi}}  — gerbang draf & kirim
 *   aktivitas      {"<id aktivitas>": {label, terbuka, alasanTutup,
 *                   unggah: null|{format[], maksMb, batas},
 *                   surat: [{urutan, nama, ukuran, dokumen}]}}
 *   konfirmasi     {"<id aktivitas>": {versi, halaman, pilihan[], butuhAlasan[],
 *                   bolehCabut}}  — halaman & gerbang konfirmasi kehadiran
 *   feedback       [{id, status, batas, form}]
 *
 * Kontrak yang tidak dikenal dianggap belum ada potret (layar menampilkan
 * "sedang diproses"), bukan galat.
 */
final class Potret
{
    public const TABEL = 'N_WEB_CAREERS_Pub_Portal_Lamaran';

    public const KONTRAK = 1;

    /** @var array<string, ?array> potret per permintaan, dikunci kode lamaran */
    private static array $ingat = [];

    /** Potret satu lamaran MILIK akun ini — null bila belum ada / bukan miliknya. */
    public static function lamaran(string $kode, int $userId): ?array
    {
        $kunci = $userId.'|'.$kode;
        if (array_key_exists($kunci, self::$ingat)) {
            return self::$ingat[$kunci];
        }

        $baris = DB::table(self::TABEL)
            ->where('Kode_Lamaran', $kode)
            ->where('Id_Users', $userId)
            ->first(['Kode_Lamaran', 'Muatan', 'Versi', 'Diperbarui_At']);

        return self::$ingat[$kunci] = $baris ? self::urai($baris) : null;
    }

    /**
     * Seluruh potret milik akun ini.
     *
     * @return array<string, array> dikunci kode lamaran
     */
    public static function milikAkun(int $userId): array
    {
        $out = [];
        foreach (DB::table(self::TABEL)->where('Id_Users', $userId)->get(['Kode_Lamaran', 'Muatan', 'Versi', 'Diperbarui_At']) as $b) {
            if ($m = self::urai($b)) {
                $out[$b->Kode_Lamaran] = self::$ingat[$userId.'|'.$b->Kode_Lamaran] = $m;
            }
        }

        return $out;
    }

    /**
     * Cari bagian tertentu (`aktivitas`, `tahapFormulir`, `konfirmasi`) milik
     * akun ini. Kepemilikan dibuktikan oleh potretnya sendiri: id yang tidak
     * tercantum di potret akun ini tidak bisa dipakai, siapa pun pemiliknya.
     *
     * @return array{kode: string, potret: array, isi: array}|null
     */
    public static function cari(int $userId, string $bagian, int|string $id): ?array
    {
        $id = (string) $id;
        foreach (self::milikAkun($userId) as $kode => $potret) {
            $isi = $potret[$bagian][$id] ?? null;
            if (is_array($isi)) {
                return ['kode' => $kode, 'potret' => $potret, 'isi' => $isi];
            }
        }

        return null;
    }

    /**
     * Potret lewat TAUTAN SUREL (tanpa sesi). Pemanggil wajib sudah memeriksa
     * tanda tangan tautannya — kode lamaran itulah yang ditandatangani.
     */
    public static function lewatTautan(string $kode): ?array
    {
        $baris = DB::table(self::TABEL)->where('Kode_Lamaran', $kode)->first(['Kode_Lamaran', 'Id_Users', 'Muatan', 'Versi', 'Diperbarui_At']);
        if (! $baris || ! ($m = self::urai($baris))) {
            return null;
        }

        return $m + ['_idUsers' => (int) $baris->Id_Users];
    }

    private static function urai(object $baris): ?array
    {
        $m = json_decode((string) $baris->Muatan, true);
        if (! is_array($m) || (int) ($m['kontrak'] ?? 0) !== self::KONTRAK) {
            Log::warning('[POTRET] kontrak tidak dikenal untuk lamaran '.$baris->Kode_Lamaran);

            return null;
        }

        return $m + ['_versi' => (int) $baris->Versi, '_diperbarui' => (string) $baris->Diperbarui_At];
    }
}
