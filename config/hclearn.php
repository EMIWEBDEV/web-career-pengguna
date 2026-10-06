<?php

/*
|--------------------------------------------------------------------------
| INTEGRASI HCLEARN / CAT — konfigurasi kanal Web Careers
|--------------------------------------------------------------------------
| Kredensial TIDAK PERNAH dikirim ke browser. Semua panggilan ke HCLearn
| dilakukan dari sisi server (HclClient), lalu hasilnya diteruskan ke Vue
| lewat endpoint internal Web Careers sendiri.
*/

return [

    // development | staging | production — dipilih lewat .env HCLEARN_ENV.
    'env' => env('HCLEARN_ENV', 'development'),

    'domains' => [
        'development' => env('HCLEARN_URL_DEVELOPMENT', 'http://cat-evo-pembaharuan.test'),
        'staging' => env('HCLEARN_URL_STAGING', 'https://cat-evo-stagging-595840247695.asia-southeast1.run.app'),
        'production' => env('HCLEARN_URL_PRODUCTION', 'https://hclearn.evonusabersaudara.co.id'),
    ],

    /*
    |--------------------------------------------------------------------------
    | ALAMAT UJIAN YANG DIBUKA KANDIDAT — bukan alamat kanal API
    |--------------------------------------------------------------------------
    |
    | `domains` di atas adalah tempat HclClient bicara server-ke-server; ia
    | berganti-ganti mengikuti HCLEARN_ENV. Yang ini alamat yang DIKETIK MANUSIA
    | di peramban, dan ia menghadap publik — jadi bawaannya selalu domain
    | produksi, apa pun lingkungan yang sedang dipakai.
    |
    | Pemisahan ini bukan kemewahan. Tanpa itu, menjadwalkan tes dari laptop
    | yang masih ber-HCLEARN_ENV=development akan menuliskan
    | `http://cat-evo-pembaharuan.test/...` ke baris peserta — di basis data
    | yang sama yang dibaca produksi — lalu tautan itu dikirim ke kandidat yang
    | tidak akan pernah bisa menjangkau domain `.test`.
    |
    | Isi HCLEARN_EXAM_URL hanya bila alamat ujian memang berbeda dari domain
    | produksi (mis. saat menguji seluruh alur di staging).
    */
    'exam_url' => rtrim(env(
        'HCLEARN_EXAM_URL',
        env('HCLEARN_URL_PRODUCTION', 'https://hclearn.evonusabersaudara.co.id')
    ), '/'),

    // Kredensial KANAL WEB CAREERS — sengaja beda nama dari HCLEARN_API_* yang
    // dipakai config/dev/fransDev.php (itu milik HCIS, jangan dipakai ulang).
    'api_public' => env('HCLEARN_WC_API_PUBLIC'),
    'api_secret' => env('HCLEARN_WC_API_SECRET'),

    'prefix' => 'api/v1/web-careers',

    /*
    |--------------------------------------------------------------------------
    | LAPORAN HASIL TES — jalan untuk mencetak, bukan untuk memutuskan
    |--------------------------------------------------------------------------
    |
    | Callback hasil (callback_path di bawah) membawa vonis agregat: nilai,
    | ambang, lulus/tidak. Itu cukup untuk menggerakkan alur seleksi, dan
    | sengaja tidak lebih — mesin keputusan tidak perlu tahu skor per aspek.
    |
    | Berkas Seleksi Kandidat perlu tahu. Rincian TIU per domain, profil DISC,
    | 20 aspek PAPI, dan norma Kraeplin hanya ada di CAT, jadi diambil saat
    | dicetak lewat jalan ini — bukan disalin ke sini setiap kali ada hasil
    | masuk. Salinan berarti dua tempat menyimpan angka yang sama, dan
    | perbaikan penilaian di CAT tidak akan pernah sampai ke berkas yang
    | terlanjur tercetak dari salinan.
    |
    | Nomor yang dikirim adalah Id_Penjadwalan_Peserta MILIK KITA — CAT
    | menyimpannya di kolom Id_WC_Penjadwalan_Peserta. Bukan nomor token CAT:
    | nomor token pernah terpakai ulang setelah reset, dan itu persis kelas
    | kesalahan yang tidak boleh terulang di jalur cetak.
    */
    'laporan_tes_path' => env('HCLEARN_LAPORAN_TES_PATH', 'laporan-tes'),

    // Anggaran waktu satu panggilan laporan. Lebih longgar dari `timeout` di
    // bawah: CAT menggambar grafik (donat DISC, jaring PAPI, lajur Kraeplin)
    // sebelum menjawab, dan itu memang butuh waktu lebih lama daripada
    // sekadar menerbitkan token.
    'laporan_timeout' => (int) env('HCLEARN_LAPORAN_TIMEOUT', 60),

    // ── CALLBACK HASIL (CAT → Web Careers) ──
    // URL publik Web Careers yang bisa dijangkau CAT untuk push hasil tes.
    // Dikirim sebagai Url_Callback saat penjadwalan; CAT memanggilnya saat tes
    // difinalisasi. Guard: header X-WC-Secret dicocokkan ke callback_secret.
    'public_url' => rtrim(env('WC_PUBLIC_URL', env('APP_URL', 'http://localhost')), '/'),
    'callback_path' => 'api/v1/webhook/hclearn-hasil',
    'callback_secret' => env('WC_CALLBACK_SECRET', env('HCLEARN_WC_API_SECRET')),

    'timeout' => (int) env('HCLEARN_TIMEOUT', 30),
    'retry' => (int) env('HCLEARN_RETRY', 2),

    /*
    |--------------------------------------------------------------------------
    | PENERBITAN TOKEN MASSAL — dipotong-potong, bukan sekali kirim
    |--------------------------------------------------------------------------
    |
    | CAT punya BATAS_SINKRON (25). Di ATAS batas itu ia tidak lagi menjawab
    | dengan token, melainkan menitipkan pekerjaannya ke antreannya sendiri dan
    | membalas `mode: ANTRIAN` + Url_Status. Hasilnya cuma mendarat di CACHE
    | CAT — dan CAT berjalan di Cloud Run dengan CACHE_DRIVER=file, jadi cache
    | itu milik SATU instance. Instance lain yang melayani Url_Status membalas
    | "batch tidak ditemukan". Token terbit di CAT, kita tak pernah menerimanya,
    | dan kandidatnya menggantung selamanya berbunyi "menunggu token HCLearn".
    | Persis itu yang terjadi pada JDW-0010 (96 peserta).
    |
    | Jadi jalur itu TIDAK DIPAKAI SAMA SEKALI. Kirimannya dipecah menjadi
    | potongan di bawah batas, sehingga CAT selalu menjawab lewat jalur sinkron
    | — jalur yang mengembalikan token, OTP, dan pengenal ujian secara lengkap.
    | Berapa pun jumlah kandidatnya, yang berubah hanya banyaknya potongan.
    |
    | 20, bukan 25: margin sengaja, supaya penurunan batas di sisi CAT tidak
    | langsung melempar kita ke jalur antrean tanpa ada yang sadar.
    */
    'chunk_peserta' => max(1, (int) env('HCLEARN_CHUNK_PESERTA', 20)),

    // Jeda antar potongan — menahan laju supaya lonjakan rekrutmen tidak
    // terbaca CAT sebagai serbuan. 0 berarti tanpa jeda.
    'chunk_jeda_ms' => max(0, (int) env('HCLEARN_CHUNK_JEDA_MS', 150)),

    // Anggaran waktu satu job. Lewat dari ini, sisanya diserahkan ke job
    // lanjutan alih-alih menunggu `$timeout` membunuh pekerjaan di tengah
    // jalan. Inilah yang membuat 1000 kandidat pun tidak pernah kehabisan
    // waktu: yang tumbuh jumlah jobnya, bukan durasi satu job.
    'chunk_batas_detik' => max(30, (int) env('HCLEARN_CHUNK_BATAS_DETIK', 600)),

    // Batas peserta dalam SATU penjadwalan — dipakai validasi maupun daftar
    // kandidat, supaya keduanya tidak pernah menjanjikan angka yang berbeda.
    'maks_peserta' => max(1, (int) env('HCLEARN_MAKS_PESERTA', 1000)),

    /*
    | Koneksi database tempat tabel token CAT (HRIS_KANDIDAT_Ujian_Token)
    | bisa dibaca — HANYA untuk pemeriksaan kepemilikan token.
    |
    | KOSONG = JANGAN DIKUERI. Tabel itu milik CAT, dan CAT punya databasenya
    | sendiri di staging/production. Dulu kueri ini berjalan di koneksi default
    | kita: kebetulan lolos saat CAT lokal (sedatabase), lalu melempar
    | "Invalid object name" begitu terpisah. Menyebutkan koneksinya secara
    | eksplisit menghapus seluruh kelas kesalahan itu — tidak ada lagi kueri
    | yang mendarat di database yang salah karena kebetulan.
    */
    'db_token_connection' => env('HCLEARN_DB_TOKEN_CONNECTION') ?: null,

    // ── KAMERA / PROCTORING WAJIB ──
    // Saat penjadwalan ujian dikirim ke CAT (cat-evo-pembaharuan), Web Careers
    // MEMAKSA kamera aktif supaya tak ada sesi tes tanpa kamera. Kontrak CAT
    // (PenjadwalanWebCareersController) mengharap field 'Flag_Camera' bertipe
    // BOOLEAN — CAT mengubahnya jadi 'Y' pada token bila truthy.
    'wajib_kamera' => (bool) env('HCLEARN_WAJIB_KAMERA', true),
    'kamera_field' => env('HCLEARN_KAMERA_FIELD', 'Flag_Camera'),

    // Simpan payload penuh di N_WEB_CAREERS_Integrasi_Log hanya saat gagal.
    'log_payload_sukses' => (bool) env('HCLEARN_LOG_PAYLOAD_SUKSES', false),
    'log_retensi_hari' => (int) env('HCLEARN_LOG_RETENSI_HARI', 30),
];
