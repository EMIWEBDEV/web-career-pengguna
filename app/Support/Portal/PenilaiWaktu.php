<?php

namespace App\Support\Portal;

use Illuminate\Support\Carbon;

/**
 * Menyegarkan bagian potret yang bergantung pada WAKTU, setiap kali dibaca.
 *
 * Potret didorong saat datanya berubah, bukan saat jam berganti. Tombol
 * "Mulai ujian" yang dihitung zona dalam pukul 08.00 untuk jendela 09.00–11.00
 * akan tetap mati selamanya bila nilainya dibekukan di potret. Karena itu
 * potret membawa PENANDA waktu mentah, dan nilai turunannya dihitung di sini:
 *
 *   _jendela      {mulai, akhir}  → belumMulai, sudahLewat, bisaAkses
 *                 (bisaAkses = di dalam jendela, punya link, belum selesai)
 *   _lewatSetelah ISO-8601        → lewat     = lewat     || sekarang > t
 *   _tutupSetelah ISO-8601        → tertutup  = tertutup  || sekarang > t
 *   _konfirmasi   {batas, mulai, terbuka}  pada blok jawab konfirmasi
 *                 kehadiran → konfirmasi.batasLewat, konfirmasi.bolehJawab,
 *                 ubah.terkunci (batas ubah H-n), aturanUsulan.tanggalMin/Maks
 *
 * Penanda dibuang sesudah dipakai, jadi layar menerima bentuk yang sama
 * persis dengan sebelum dua zona.
 */
final class PenilaiWaktu
{
    public static function segarkan(mixed $node, ?Carbon $sekarang = null): mixed
    {
        if (! is_array($node)) {
            return $node;
        }
        $sekarang ??= now();

        if (array_key_exists('_jendela', $node)) {
            $j = (array) $node['_jendela'];
            $mulai = self::waktu($j['mulai'] ?? null);
            $akhir = self::waktu($j['akhir'] ?? null);
            $node['belumMulai'] = $mulai ? $sekarang->lt($mulai) : false;
            $node['sudahLewat'] = $akhir ? $sekarang->gt($akhir) : false;
            $node['bisaAkses'] = $mulai && $akhir && $sekarang->betweenIncluded($mulai, $akhir)
                && ! empty($node['link'])
                && ($node['statusPengerjaan'] ?? null) !== 'selesai';
            unset($node['_jendela']);
        }

        if (array_key_exists('_lewatSetelah', $node)) {
            $t = self::waktu($node['_lewatSetelah']);
            $node['lewat'] = (bool) ($node['lewat'] ?? false) || ($t && $sekarang->gt($t));
            unset($node['_lewatSetelah']);
        }

        if (array_key_exists('_tutupSetelah', $node)) {
            $t = self::waktu($node['_tutupSetelah']);
            $node['tertutup'] = (bool) ($node['tertutup'] ?? false) || ($t && $sekarang->gt($t));
            unset($node['_tutupSetelah']);
        }

        if (array_key_exists('_konfirmasi', $node)) {
            $node = self::konfirmasi($node, (array) $node['_konfirmasi'], $sekarang);
            unset($node['_konfirmasi']);
        }

        foreach ($node as $k => $v) {
            if (is_array($v)) {
                $node[$k] = self::segarkan($v, $sekarang);
            }
        }

        return $node;
    }

    /** Blok jawab konfirmasi kehadiran (bentuk KonfirmasiJadwal::bahanJawab zona dalam). */
    private static function konfirmasi(array $node, array $tanda, Carbon $sekarang): array
    {
        $batas = self::waktu($tanda['batas'] ?? null);
        $lewat = ! $batas || $sekarang->gte($batas);

        if (isset($node['konfirmasi']) && is_array($node['konfirmasi'])) {
            $final = (bool) ($node['konfirmasi']['final'] ?? false);
            $node['konfirmasi']['batasLewat'] = $lewat;
            $node['konfirmasi']['bolehJawab'] = (bool) ($tanda['terbuka'] ?? false) && ! $lewat && ! $final;
        }

        // Kunci "ubah jawaban": sebab tetap (sekali jawab, jatah habis, usulan
        // sendiri) tidak berubah oleh waktu; selain itu terkunci begitu batas
        // ubahnya (H-n sebelum mulai) terlewati.
        if (isset($node['ubah']) && is_array($node['ubah']) && in_array($node['ubah']['sebab'] ?? null, [null, 'WAKTU'], true)) {
            $batasUbah = self::waktu($node['ubah']['batas'] ?? null);
            $terkunci = ! $batasUbah || $sekarang->gte($batasUbah);
            $node['ubah']['terkunci'] = $terkunci;
            $node['ubah']['sebab'] = $terkunci ? 'WAKTU' : null;
            if ($terkunci && empty($node['ubah']['pesanKunci'])) {
                $node['ubah']['pesanKunci'] = 'Jawabanmu sudah tidak bisa diubah'
                    .(! empty($node['ubah']['batasTeks']) ? ' (batasnya '.$node['ubah']['batasTeks'].')' : '')
                    .'. Hubungi tim rekrutmen bila berhalangan.';
            }
        }

        if (isset($node['aturanUsulan']) && is_array($node['aturanUsulan'])) {
            $hariMaks = max(1, (int) config('konfirmasi.usulan_hari_maks', 14));
            $node['aturanUsulan']['tanggalMin'] = $sekarang->copy()->addDay()->format('Y-m-d');
            $node['aturanUsulan']['tanggalMaks'] = $sekarang->copy()->addDays($hariMaks)->format('Y-m-d');
        }

        return $node;
    }

    /** Sudah lewat batas? null/kosong = tidak berbatas. */
    public static function lewat(?string $batas, ?Carbon $sekarang = null): bool
    {
        $t = self::waktu($batas);

        return $t !== null && ($sekarang ?? now())->gt($t);
    }

    private static function waktu(mixed $v): ?Carbon
    {
        if (! is_string($v) || trim($v) === '') {
            return null;
        }

        try {
            return Carbon::parse($v);
        } catch (\Throwable) {
            return null;
        }
    }
}
