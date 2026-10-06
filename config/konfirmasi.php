<?php

/*
|--------------------------------------------------------------------------
| KONFIRMASI KEHADIRAN — angka kebijakan yang bukan pilihan pengguna
|--------------------------------------------------------------------------
| Pilihan yang dibaca kandidat & tim (status, alasan) ada di master basis
| data. Yang di sini adalah ATURAN SISTEM: berapa usulan waktu yang boleh
| diajukan, seberapa jauh ke depan, jeda kirim agar tidak dianggap spam.
| Diubah lewat .env tanpa menyentuh kode.
*/

return [

    // Komitmen jawaban — CADANGAN bila kolom Master_Tipe_Tahap.Ubah_Jawaban_*
    // belum dipasang. Yang berlaku per tipe ada di master itu.
    'ubah_maks' => (int) env('KONFIRMASI_UBAH_MAKS', 1),
    'ubah_batas_jam' => (int) env('KONFIRMASI_UBAH_BATAS_JAM', 72),

    // Perkiraan jadwal pengganti saat menunda: paling jauh sekian hari ke depan.
    'tunda_hari_maks' => (int) env('KONFIRMASI_TUNDA_HARI_MAKS', 180),

    // Usulan waktu dari kandidat: paling banyak sekian rentang, paling jauh
    // sekian hari ke depan, mulai besok.
    'usulan_maks' => (int) env('KONFIRMASI_USULAN_MAKS', 3),
    'usulan_hari_maks' => (int) env('KONFIRMASI_USULAN_HARI_MAKS', 14),

    // Bagian hari yang ditawarkan ke kandidat — mengikuti jam kerja kantor.
    'bagian_hari' => [
        ['kode' => 'PAGI', 'label' => 'Pagi', 'mulai' => '08:00', 'selesai' => '12:00'],
        ['kode' => 'SIANG', 'label' => 'Siang', 'mulai' => '12:00', 'selesai' => '15:00'],
        ['kode' => 'SORE', 'label' => 'Sore', 'mulai' => '15:00', 'selesai' => '17:00'],
    ],

    // Dua kiriman manual ke kandidat yang sama tidak boleh lebih rapat dari ini.
    'jeda_kirim_menit' => (int) env('KONFIRMASI_JEDA_KIRIM_MENIT', 10),

    // Di bawah jarak ini layar memperingatkan "kandidat baru saja menerima
    // email" saat tim mengirim pengingat MANUAL. Pengingat otomatis punya
    // aturannya sendiri (lihat 'pengingat' di bawah) — sampai empat kali sehari
    // atas keputusan user 3 Okt 2026.
    'jarak_sopan_jam' => (int) env('KONFIRMASI_JARAK_SOPAN_JAM', 16),

    // Pengingat massal diantrekan berselang sekian detik, supaya puluhan
    // surel tidak berangkat serentak dari satu alamat pengirim.
    'cicil_detik' => (int) env('KONFIRMASI_CICIL_DETIK', 2),

    // Paling banyak sekian kandidat dalam satu kiriman pengingat manual.
    'pengingat_maks' => (int) env('KONFIRMASI_PENGINGAT_MAKS', 300),

    // Tautan konfirmasi berlaku sampai sehari sesudah acara, tetapi tidak
    // pernah lebih dari sekian hari sejak dibuat.
    'tautan_hari_maks' => (int) env('KONFIRMASI_TAUTAN_HARI_MAKS', 60),

    /*
    |----------------------------------------------------------------------
    | PENGINGAT OTOMATIS — kandidat yang belum menjawab undangan
    |----------------------------------------------------------------------
    | Diingatkan pada jam-jam tetap setiap hari sampai jadwalnya dimulai
    | (hari-H ikut, sebelum jam mulai). Pemicunya Cloud Scheduler lewat
    | POST /api/tugas/pengingat-konfirmasi — lihat config/tugas.php dan
    | App\Support\Career\PengingatKonfirmasi. Tidak ada jadwal di basis data.
    */
    'pengingat' => [

        // Sakelar darurat. false = pemicu tetap menjawab, tapi tidak satu surel
        // pun dikirim (lebih cepat daripada menjeda job di Cloud Scheduler).
        'aktif' => (bool) env('KONFIRMASI_PENGINGAT_AKTIF', true),

        // Jam pengingat (WIB), dipisah koma. WAJIB sama dengan jadwal Cloud
        // Scheduler ("0 5,8,12,16 * * *") — pemicu di luar jam ini diabaikan,
        // jadi job yang salah setel tidak bisa menambah jumlah email.
        'jam' => env('KONFIRMASI_PENGINGAT_JAM', '05,08,12,16'),

        // Paling banyak sekian pengingat per kandidat per hari — dijaga juga
        // oleh kunci unik (kandidat, tanggal, slot) di buku pengingat.
        'maks_harian' => (int) env('KONFIRMASI_PENGINGAT_MAKS_HARIAN', 4),

        // Slot dilewati bila kandidat menerima email konfirmasi kurang dari
        // sekian jam sebelumnya (undangan yang baru terkirim, pengingat
        // manual tim) — tidak ada pengingat lima menit sesudah undangan.
        'jeda_jam' => (int) env('KONFIRMASI_PENGINGAT_JEDA_JAM', 2),

        // Kandidat per putaran. Sisanya dikerjakan putaran lanjutan yang
        // dijadwalkan job itu sendiri, berjarak sepanjang cicilan surelnya.
        'per_putaran' => (int) env('KONFIRMASI_PENGINGAT_PER_PUTARAN', 200),

        // Pengaman putaran lanjutan: 200 x 25 = 5.000 kandidat per slot.
        'putaran_maks' => (int) env('KONFIRMASI_PENGINGAT_PUTARAN_MAKS', 25),

        // Tugas yang tertahan di antrean lebih dari sekian menit sesudah jam
        // slotnya tidak dikirim lagi — "pengingat jam 05.00" yang baru
        // berangkat pukul 11.00 bukan lagi pengingat jam 05.00.
        'slot_berlaku_menit' => (int) env('KONFIRMASI_PENGINGAT_SLOT_BERLAKU_MENIT', 120),
    ],
];
