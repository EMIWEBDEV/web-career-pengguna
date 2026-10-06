<?php

namespace App\Support\Sinkron;

use RuntimeException;

/**
 * Aplikasi publik MENOLAK MENYALA bila memegang jalan ke zona dalam.
 *
 * Aturan dua zona: tidak ada kredensial, rute, atau URL dari luar ke dalam.
 * Kesalahan konfigurasi (menyalin .env admin, menambah koneksi "sementara")
 * lebih baik membuat aplikasi gagal start daripada diam-diam bekerja dengan
 * akses yang seharusnya tidak pernah ada.
 */
final class PenjagaZona
{
    public static function periksa(): void
    {
        $terlarang = array_map('strtolower', (array) config('sinkron.database_terlarang', []));

        foreach ((array) config('database.connections', []) as $nama => $koneksi) {
            $db = strtolower(trim((string) ($koneksi['database'] ?? '')));
            if ($db !== '' && in_array($db, $terlarang, true)) {
                throw new RuntimeException(
                    "Aplikasi publik menolak menyala: koneksi '{$nama}' menunjuk database admin ({$koneksi['database']}). "
                    .'Zona luar tidak boleh memegang kredensial zona dalam.'
                );
            }
        }
    }
}
