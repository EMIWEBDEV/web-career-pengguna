<?php

/*
|--------------------------------------------------------------------------
| KONFIRMASI KEHADIRAN — angka yang dipakai sisi kandidat
|--------------------------------------------------------------------------
| Aturan jawabannya (jatah ubah, batas H-n, pilihan, alasan) datang dari
| potret portal yang dibuat zona dalam. Yang di sini hanya yang dihitung
| ulang saat halaman dibuka.
*/

return [
    // Usulan waktu pengganti paling jauh N hari dari hari ini. Samakan dengan
    // nilai di zona dalam (KONFIRMASI_USULAN_HARI_MAKS).
    'usulan_hari_maks' => (int) env('KONFIRMASI_USULAN_HARI_MAKS', 14),

    // Umur tautan jawab/cabut yang diterbitkan halaman konfirmasi (hari).
    'tautan_hari_maks' => (int) env('KONFIRMASI_TAUTAN_HARI_MAKS', 60),
];
