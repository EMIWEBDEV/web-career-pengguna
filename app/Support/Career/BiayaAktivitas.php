<?php

namespace App\Support\Career;

use Illuminate\Support\Facades\DB;

/**
 * WEB CAREERS — ATURAN BIAYA SEBUAH AKTIVITAS (saat ini: MCU).
 *
 * Aturannya satu dan berlaku untuk semua kandidat: biaya dibayar kandidat
 * lebih dulu, lalu DIGANTI bila ia dinyatakan lolos dan TIDAK diganti bila
 * tidak. Karena itu tidak ada isian apa pun — tidak untuk rekruter, tidak untuk
 * kandidat. Yang disimpan hanya dua hal yang memang sudah ada:
 *
 *   Master_Tipe_Tahap.Kalimat_Biaya  kalimat aturannya, dibaca kandidat sejak
 *                                    undangan. Kosong = tipe itu tak punya
 *                                    ketentuan biaya, dan tak ada yang tampil.
 *   hasil aktivitasnya               LULUS/GAGAL yang diturunkan dari status
 *                                    MCU (Flag_Lolos di Master Status MCU).
 *
 * Penggantiannya TIDAK disimpan di kolom sendiri: ia turunan dari hasil yang
 * sudah terkunci saat hasil dicatat, jadi mustahil berselisih dengannya.
 * Pembayarannya sendiri tetap urusan Finance, di luar Web Careers.
 */
final class BiayaAktivitas
{
    public const MENUNGGU = 'MENUNGGU';

    public const DIGANTI = 'DIGANTI';

    public const TIDAK_DIGANTI = 'TIDAK_DIGANTI';

    /** Kandidat tidak melaksanakannya — tak ada biaya yang dikeluarkan. */
    public const TANPA = 'TANPA';

    private const LABEL = [
        self::MENUNGGU => 'Menunggu hasil',
        self::DIGANTI => 'Biaya diganti',
        self::TIDAK_DIGANTI => 'Biaya tidak diganti',
        self::TANPA => 'Tanpa biaya',
    ];

    /** Kalimat untuk KANDIDAT sesudah hasilnya keluar. */
    private const KALIMAT_KANDIDAT = [
        self::DIGANTI => 'Hasilmu memenuhi syarat, jadi biaya pemeriksaan akan diganti perusahaan. Simpan kwitansi aslinya.',
        self::TIDAK_DIGANTI => 'Hasilmu belum memenuhi syarat, sehingga biaya pemeriksaan tidak diganti.',
    ];

    public static function siap(): bool
    {
        try {
            return Skema::adaKolom('N_WEB_CAREERS_Master_Tipe_Tahap', 'Kalimat_Biaya');
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Keadaan biaya satu aktivitas — null bila tipenya tak punya ketentuan biaya.
     *
     * @param  object       $x     baris Lamaran_Tahap_Tes
     * @param  object|null  $tipe  baris Master_Tipe_Tahap milik aktivitas itu
     */
    public static function status(object $x, ?object $tipe): ?array
    {
        $kalimat = trim((string) ($tipe->Kalimat_Biaya ?? ''));
        if ($kalimat === '') {
            return null;
        }

        $kode = self::kode($x);

        return [
            'kode' => $kode,
            'label' => self::LABEL[$kode],
            // Aturannya — tampil di undangan & kartu jadwal sebelum MCU. Kalimat
            // yang disunting admin untuk jadwal ini (Jadwal_Kalimat_Biaya)
            // menang atas kalimat tipe.
            'kalimat' => trim((string) ($x->Jadwal_Kalimat_Biaya ?? '')) ?: $kalimat,
            // Kesimpulannya — tampil di kartu hasil sesudah MCU.
            'hasilKalimat' => self::KALIMAT_KANDIDAT[$kode] ?? null,
        ];
    }

    /**
     * Aturan penentuannya, tanpa basis data selain master status MCU.
     *
     * @param  array<string,bool>|null  $lolosMcu  [Kode status MCU => lolos?];
     *                                             null = dibaca dari master
     */
    public static function kode(object $x, ?array $lolosMcu = null): string
    {
        if (($x->Status ?? '') === 'TIDAK_HADIR') {
            return self::TANPA;
        }

        if (($x->Flag_Selesai ?? 'N') !== 'Y') {
            return self::MENUNGGU;
        }

        // Status MCU lebih dulu: ia yang menentukan verdict-nya, dan tetap
        // berlaku pada aktivitas berperan INFORMATIF yang tak diberi Hasil.
        if (! empty($x->Mcu_Status)) {
            $lolos = ($lolosMcu ?? self::lolosMcu())[$x->Mcu_Status] ?? null;
            if ($lolos !== null) {
                return $lolos ? self::DIGANTI : self::TIDAK_DIGANTI;
            }
        }

        return match ($x->Hasil ?? null) {
            'LULUS' => self::DIGANTI,
            'GAGAL' => self::TIDAK_DIGANTI,
            default => self::MENUNGGU,
        };
    }

    /** [Kode status MCU => lolos?] dari master, sekali per proses. */
    private static function lolosMcu(): array
    {
        static $peta = null;

        return $peta ??= DB::table('N_WEB_CAREERS_Master_Mcu_Status')
            ->pluck('Flag_Lolos', 'Kode')
            ->map(fn ($f) => $f === 'Y')
            ->all();
    }
}
