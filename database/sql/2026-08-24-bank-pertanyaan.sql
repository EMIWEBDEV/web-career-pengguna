/* ============================================================================
   WEB CAREERS — BANK PERTANYAAN (pustaka pertanyaan lintas template)
   ----------------------------------------------------------------------------
   Jalankan SESUDAH 2026-08-24-phone-screening.sql.
   Jalankan di basis data: Web_HRIS
   Aman dijalankan berkali-kali (idempoten).

   ── MASALAH YANG DIPECAHKAN ─────────────────────────────────────────────────

   Tanpa bank, tiap template disusun dari nol. "Bersedia ditempatkan di luar
   kota?" ditulis ulang di template HR Umum, template MT, template Teknis —
   tiga kali, dengan tiga redaksi yang sedikit berbeda dan tiga daftar opsi
   yang tidak persis sama. Analitik lintas program lalu mustahil: tiga
   pertanyaan yang sebenarnya satu, tercatat sebagai tiga hal berbeda.

   ── BENTUKNYA: SUMBER TULIS vs SALINAN BEKU ─────────────────────────────────

   Ini bagian yang paling mudah dirancang keliru, jadi ditulis terang-terangan:

     BANK      sumber penulisan. Boleh disunting kapan saja.
     TEMPLATE  memegang SALINANNYA SENDIRI (Master_Skrining_Pertanyaan),
               plus catatan asal-usul (Bank_Id / Bank_Kode).

   Template TIDAK merujuk bank saat sesi berjalan. Kalau merujuk, menyunting
   satu pertanyaan di bank akan diam-diam mengubah arti seluruh sesi yang
   pernah memakainya — persis hal yang seluruh disiplin snapshot di modul ini
   ada untuk cegah.

   Yang didapat dari bank karena itu adalah KEMUDAHAN MENULIS, bukan tautan
   hidup: pilih dari daftar, isinya tersalin, dan sejak saat itu template yang
   memilikinya. Draf boleh disegarkan ulang dari bank kapan saja; versi terbit
   tidak pernah.

   `Flag_Ubahan` menandai salinan yang sudah dirombak di template dan karena
   itu TIDAK ikut tersegarkan — mis. ambang knockout gaji yang memang harus
   berbeda antara loker staf dan loker manajer.

   ── URUTAN BLOK ─────────────────────────────────────────────────────────────
     A. Tabel bank
     B. Asal-usul di salinan template (3 kolom)
     C. Isi awal — 14 pertanyaan skrining yang lazim dipakai
     D. Menu & hak akses
     E. Verifikasi
   ============================================================================ */


/* ============================================================================
   A. TABEL BANK
   ============================================================================ */

/* Dinamai Master_Pertanyaan, BUKAN Master_Skrining_Pertanyaan_Bank.

   Kolom `Konteks` ada sejak awal karena pustaka ini memang diminta bisa
   dipakai di luar skrining. Hari ini seluruh isinya 'SKRINING'; saat feedback
   atau penilaian wawancara ikut memakainya, yang bertambah cuma nilai kolom —
   bukan tabel kedua yang isinya nyaris sama. */
IF OBJECT_ID('dbo.N_WEB_CAREERS_Master_Pertanyaan', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.N_WEB_CAREERS_Master_Pertanyaan (
        Id_Master_Pertanyaan INT IDENTITY(1,1) NOT NULL,

        /* Kode GLOBAL dan stabil. Inilah yang menyatukan "gaji harapan" di
           template HR Umum dengan "gaji harapan" di template MT saat hasilnya
           diagregasi lintas program. Huruf kecil, sepola kode pertanyaan di
           template dan nama field yang dibaca MesinSyarat. */
        Kode                 VARCHAR(40)   NOT NULL,
        Konteks              VARCHAR(20)   NOT NULL CONSTRAINT DF_NWC_MPertanyaan_Konteks DEFAULT 'SKRINING',

        /* Pengelompokan di layar pemilih: Ketersediaan, Kompensasi,
           Pengalaman, Motivasi, Penilaian. Bukan master tersendiri — daftarnya
           tumbuh dari isi tabel ini, dan memaksanya jadi master hanya menambah
           satu halaman CRUD untuk sesuatu yang isinya lima baris. */
        Kelompok             VARCHAR(60)   NULL,

        Tipe                 VARCHAR(20)   NOT NULL,
        Label                VARCHAR(500)  NOT NULL,
        Bantuan              VARCHAR(500)  NULL,

        /* JSON: [{"nilai":"YA","label":"Ya","skor":10}, ...] */
        Opsi                 NVARCHAR(MAX) NULL,
        Skala_Min            INT           NULL,
        Skala_Max            INT           NULL,
        Label_Min            VARCHAR(100)  NULL,
        Label_Max            VARCHAR(100)  NULL,

        /* ── BAWAAN, BUKAN KETENTUAN ────────────────────────────────────────
           Empat kolom di bawah adalah NILAI AWAL yang tersalin saat pertanyaan
           ditarik ke sebuah template. Sesudah tersalin, templatelah yang
           memilikinya.

           Knockout khususnya HARUS bisa berbeda per template: ambang gaji
           yang menggugurkan pelamar staf tidak sama dengan ambang untuk
           manajer, padahal pertanyaannya persis sama. Menyimpannya sebagai
           ketentuan bank akan memaksa dua pertanyaan terpisah untuk satu hal —
           dan mengembalikan persis masalah yang bank ini dibuat untuk pecahkan. */
        Bobot_Bawaan         DECIMAL(6,2)  NULL,
        Knockout_Operator    VARCHAR(20)   NULL,
        Knockout_Nilai       VARCHAR(500)  NULL,
        Knockout_Pesan       VARCHAR(500)  NULL,

        Flag_Wajib_Bawaan    CHAR(1)       NOT NULL CONSTRAINT DF_NWC_MPertanyaan_Wajib DEFAULT 'Y',
        Flag_Catatan_Bawaan  CHAR(1)       NOT NULL CONSTRAINT DF_NWC_MPertanyaan_Catatan DEFAULT 'T',

        /* Bawaan sistem tidak bisa dihapus — hanya dinonaktifkan. Sepola
           Flag_Sistem di Master_Tipe_Tahap dan Master_Jenis_Verifikasi. */
        Flag_Sistem          CHAR(1)       NOT NULL CONSTRAINT DF_NWC_MPertanyaan_Sistem DEFAULT 'T',
        Flag_Aktif           CHAR(1)       NOT NULL CONSTRAINT DF_NWC_MPertanyaan_Aktif DEFAULT 'Y',
        Urutan               INT           NOT NULL CONSTRAINT DF_NWC_MPertanyaan_Urutan DEFAULT 0,

        Created_At           DATETIME      NULL,
        Created_By           VARCHAR(200)  NULL,
        Created_By_Id        INT           NULL,
        Updated_At           DATETIME      NULL,
        Updated_By           VARCHAR(200)  NULL,
        Updated_By_Id        INT           NULL,

        CONSTRAINT PK_NWC_Master_Pertanyaan PRIMARY KEY CLUSTERED (Id_Master_Pertanyaan),
        CONSTRAINT CK_NWC_MPertanyaan_Tipe
            CHECK (Tipe IN ('RADIO','CHECKBOX','SELECT','RATING','LIKERT','NPS','BOOLEAN',
                            'TEXT','TEXTAREA','EDITOR','NUMBER','CURRENCY','DATE'))
    );

    /* Unik per konteks, bukan global: kelak 'motivasi' boleh ada sekali di
       SKRINING dan sekali di FEEDBACK dengan redaksi yang berbeda. */
    CREATE UNIQUE NONCLUSTERED INDEX UX_NWC_MPertanyaan_Kode
        ON dbo.N_WEB_CAREERS_Master_Pertanyaan (Konteks, Kode);

    CREATE NONCLUSTERED INDEX IX_NWC_MPertanyaan_Pilih
        ON dbo.N_WEB_CAREERS_Master_Pertanyaan (Konteks, Flag_Aktif, Kelompok, Urutan)
        INCLUDE (Kode, Tipe, Label);
END
GO


/* ============================================================================
   B. ASAL-USUL DI SALINAN TEMPLATE

   Tiga kolom di Master_Skrining_Pertanyaan. Semuanya NULL-able: pertanyaan
   yang diketik langsung di template (tanpa lewat bank) tetap sah dan memang
   harus tetap bisa — bank itu jalan pintas, bukan gerbang wajib.
   ============================================================================ */

IF COL_LENGTH('dbo.N_WEB_CAREERS_Master_Skrining_Pertanyaan', 'Bank_Id') IS NULL
    ALTER TABLE dbo.N_WEB_CAREERS_Master_Skrining_Pertanyaan
        ADD Bank_Id INT NULL,
            /* Kode bank ikut disimpan, bukan cuma Id — sepola seluruh
               pembekuan di modul ini. Id patah kalau bank dipindah
               antar-lingkungan; kode bertahan, dan analitik lintas template
               memang menyatukan lewat kode. */
            Bank_Kode VARCHAR(40) NULL,
            /* 'Y' = salinan ini sudah dirombak di template dan TIDAK ikut
               tersegarkan saat draf ditarik ulang dari bank. */
            Flag_Ubahan CHAR(1) NOT NULL CONSTRAINT DF_NWC_SkrTanya_Ubahan DEFAULT 'T';
GO

/* Menelusuri "pertanyaan bank ini dipakai template mana saja" — pertanyaan
   pertama yang muncul sebelum seseorang menyuntingnya. */
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_NWC_SkrTanya_Bank')
    CREATE NONCLUSTERED INDEX IX_NWC_SkrTanya_Bank
        ON dbo.N_WEB_CAREERS_Master_Skrining_Pertanyaan (Bank_Kode)
        INCLUDE (Master_Skrining_Versi_Id, Flag_Ubahan);
GO


/* ============================================================================
   C. ISI AWAL — 14 pertanyaan skrining yang lazim dipakai

   Bukan contoh: ini pertanyaan yang benar-benar ditanyakan di skrining telepon
   dan bisa langsung dipakai. Semuanya Flag_Sistem = 'Y' sehingga tidak bisa
   dihapus, hanya dinonaktifkan — daftar bawaan yang bisa lenyap tanpa jejak
   membuat template lama kehilangan asal-usulnya.

   Knockout SENGAJA tidak diisi untuk pertanyaan gaji: ambangnya berbeda tiap
   MPP, dan bawaan yang salah lebih berbahaya daripada tidak ada bawaan.
   ============================================================================ */

MERGE dbo.N_WEB_CAREERS_Master_Pertanyaan AS t
USING (VALUES
    -- ── Ketersediaan ─────────────────────────────────────────────────────
    ('bersedia_relokasi', 'Ketersediaan', 'RADIO',
     'Bersedia ditempatkan di luar kota domisili?', NULL,
     N'[{"nilai":"YA","label":"Ya, bersedia","skor":10},{"nilai":"NEGO","label":"Bisa dirundingkan","skor":5},{"nilai":"TIDAK","label":"Tidak bersedia","skor":0}]',
     NULL, NULL, 2.00, '=', 'TIDAK', 'Tidak bersedia ditempatkan di luar kota.', 1),

    ('tanggal_siap_mulai', 'Ketersediaan', 'DATE',
     'Kapan paling cepat bisa mulai bekerja?',
     'Tanyakan tanggal pastinya, bukan "secepatnya".',
     NULL, NULL, NULL, NULL, NULL, NULL, NULL, 2),

    ('masa_notice', 'Ketersediaan', 'SELECT',
     'Berapa lama masa pemberitahuan di tempat kerja sekarang?', NULL,
     N'[{"nilai":"TIDAK_BEKERJA","label":"Sedang tidak bekerja","skor":10},{"nilai":"KURANG_1BLN","label":"Kurang dari 1 bulan","skor":8},{"nilai":"1BLN","label":"1 bulan","skor":6},{"nilai":"2BLN","label":"2 bulan","skor":3},{"nilai":"LEBIH_2BLN","label":"Lebih dari 2 bulan","skor":1}]',
     NULL, NULL, 1.00, NULL, NULL, NULL, 3),

    ('bersedia_shift', 'Ketersediaan', 'RADIO',
     'Bersedia bekerja dengan sistem giliran (shift)?', NULL,
     N'[{"nilai":"YA","label":"Ya","skor":10},{"nilai":"TIDAK","label":"Tidak","skor":0}]',
     NULL, NULL, 1.00, '=', 'TIDAK', 'Tidak bersedia bekerja dengan sistem giliran.', 4),

    -- ── Kompensasi ───────────────────────────────────────────────────────
    ('gaji_terakhir', 'Kompensasi', 'CURRENCY',
     'Berapa gaji pokok terakhir Anda?',
     'Gaji pokok saja, di luar tunjangan dan bonus.',
     NULL, NULL, NULL, NULL, NULL, NULL, NULL, 10),

    ('gaji_harapan', 'Kompensasi', 'CURRENCY',
     'Berapa gaji harapan Anda?',
     'Ambang gugurnya diisi per template — rentangnya berbeda tiap MPP.',
     NULL, NULL, NULL, NULL, NULL, NULL, NULL, 11),

    ('gaji_fleksibel', 'Kompensasi', 'RADIO',
     'Angka tersebut masih bisa dirundingkan?', NULL,
     N'[{"nilai":"YA","label":"Ya","skor":10},{"nilai":"TIDAK","label":"Tidak, harga mati","skor":0}]',
     NULL, NULL, 1.00, NULL, NULL, NULL, 12),

    -- ── Pengalaman ───────────────────────────────────────────────────────
    ('lama_pengalaman', 'Pengalaman', 'NUMBER',
     'Berapa tahun pengalaman di bidang yang dilamar?', NULL,
     NULL, NULL, NULL, 2.00, NULL, NULL, NULL, 20),

    ('ringkas_pekerjaan', 'Pengalaman', 'TEXTAREA',
     'Ceritakan singkat tanggung jawab di pekerjaan terakhir.', NULL,
     NULL, NULL, NULL, NULL, NULL, NULL, NULL, 21),

    ('alasan_pindah', 'Pengalaman', 'TEXTAREA',
     'Apa alasan mencari pekerjaan baru?',
     'Dengarkan apakah alasannya konsisten dengan riwayat kerjanya.',
     NULL, NULL, NULL, NULL, NULL, NULL, NULL, 22),

    ('sedang_proses_lain', 'Pengalaman', 'RADIO',
     'Sedang dalam proses seleksi di perusahaan lain?', NULL,
     N'[{"nilai":"TIDAK","label":"Tidak","skor":10},{"nilai":"YA_AWAL","label":"Ya, tahap awal","skor":6},{"nilai":"YA_AKHIR","label":"Ya, tahap akhir/penawaran","skor":2}]',
     NULL, NULL, NULL, NULL, NULL, NULL, 23),

    -- ── Penilaian petugas ────────────────────────────────────────────────
    ('nilai_komunikasi', 'Penilaian', 'RATING',
     'Kualitas komunikasi selama wawancara',
     'Kejelasan menjawab, runtut, dan mudah dipahami.',
     NULL, 1, 5, 3.00, NULL, NULL, NULL, 30),

    ('nilai_kecocokan', 'Penilaian', 'RATING',
     'Kecocokan dengan kebutuhan posisi',
     'Sejauh mana pengalamannya menjawab kebutuhan MPP.',
     NULL, 1, 5, 3.00, NULL, NULL, NULL, 31),

    ('catatan_petugas', 'Penilaian', 'EDITOR',
     'Catatan tambahan petugas',
     'Hal yang tidak tertangkap pertanyaan di atas.',
     NULL, NULL, NULL, NULL, NULL, NULL, NULL, 32)
) AS s (Kode, Kelompok, Tipe, Label, Bantuan, Opsi, Skala_Min, Skala_Max,
        Bobot_Bawaan, Knockout_Operator, Knockout_Nilai, Knockout_Pesan, Urutan)
   ON t.Konteks = 'SKRINING' AND t.Kode = s.Kode
 WHEN NOT MATCHED BY TARGET THEN
    INSERT (Kode, Konteks, Kelompok, Tipe, Label, Bantuan, Opsi, Skala_Min, Skala_Max,
            Bobot_Bawaan, Knockout_Operator, Knockout_Nilai, Knockout_Pesan,
            Flag_Wajib_Bawaan, Flag_Catatan_Bawaan, Flag_Sistem, Flag_Aktif, Urutan,
            Created_At, Created_By, Updated_At, Updated_By)
    VALUES (s.Kode, 'SKRINING', s.Kelompok, s.Tipe, s.Label, s.Bantuan, s.Opsi,
            s.Skala_Min, s.Skala_Max, s.Bobot_Bawaan,
            s.Knockout_Operator, s.Knockout_Nilai, s.Knockout_Pesan,
            'Y', 'T', 'Y', 'Y', s.Urutan,
            GETDATE(), 'SISTEM(bank-pertanyaan)', GETDATE(), 'SISTEM(bank-pertanyaan)');
GO


/* ============================================================================
   D. MENU & HAK AKSES

   Halaman /master-pertanyaan, berizin masterPertanyaanPage. Aksesnya
   diturunkan dari masterSkriningPage: orang yang merawat template kuesioner
   adalah orang yang sama yang merawat pustaka pertanyaannya.
   ============================================================================ */

IF NOT EXISTS (SELECT 1 FROM dbo.N_WEB_CAREERS_Menu WHERE Jenis_Page = 'masterPertanyaanPage')
    INSERT INTO dbo.N_WEB_CAREERS_Menu
        (Jenis_Page, Nama_Menu, Nama_Header, Sub_Header, Icon_Menu, Url_Menu,
         Untuk_Role, Urutan, Flag_Maintenance, Flag_Aktif,
         Created_At, Created_By, Updated_At, Updated_By)
    VALUES
        ('masterPertanyaanPage', 'Bank Pertanyaan', 'Master Data',
         'Pustaka pertanyaan lintas template', 'bi bi-patch-question', '/master-pertanyaan',
         'ADMIN', 27, 'T', 'Y',
         GETDATE(), 'SISTEM(bank-pertanyaan)', GETDATE(), 'SISTEM(bank-pertanyaan)');
GO

INSERT INTO dbo.N_WEB_CAREERS_Page_Access
    (Id_Users, Jenis_Page, Urutan_Menu, Created_At, Created_By, Updated_At, Updated_By)
SELECT DISTINCT pa.Id_Users, 'masterPertanyaanPage', 27,
       GETDATE(), 'SISTEM(bank-pertanyaan)', GETDATE(), 'SISTEM(bank-pertanyaan)'
  FROM dbo.N_WEB_CAREERS_Page_Access AS pa
 WHERE pa.Jenis_Page = 'masterSkriningPage'
   AND NOT EXISTS (
       SELECT 1 FROM dbo.N_WEB_CAREERS_Page_Access AS x
        WHERE x.Id_Users = pa.Id_Users AND x.Jenis_Page = 'masterPertanyaanPage');
GO

INSERT INTO dbo.N_WEB_CAREERS_Role_Menu_Access
    (Id_Page_Access, Id_Aksi, Flag_Diizinkan, Created_At, Created_By, Updated_At, Updated_By)
SELECT pa.Id_Page_Access, a.Id_Aksi, 'Y',
       GETDATE(), 'SISTEM(bank-pertanyaan)', GETDATE(), 'SISTEM(bank-pertanyaan)'
  FROM dbo.N_WEB_CAREERS_Page_Access AS pa
 CROSS JOIN dbo.N_WEB_CAREERS_Aksi AS a
 WHERE pa.Jenis_Page = 'masterPertanyaanPage'
   AND a.Flag_Aktif = 'Y'
   AND a.Nama_Aksi IN ('VIEW', 'CREATE', 'EDIT', 'DELETE')
   AND NOT EXISTS (
       SELECT 1 FROM dbo.N_WEB_CAREERS_Role_Menu_Access AS r
        WHERE r.Id_Page_Access = pa.Id_Page_Access AND r.Id_Aksi = a.Id_Aksi);
GO


/* ============================================================================
   E. VERIFIKASI
   ============================================================================ */

SELECT 'A. Tabel bank' AS Blok,
       CASE WHEN OBJECT_ID('dbo.N_WEB_CAREERS_Master_Pertanyaan','U') IS NOT NULL
            THEN 'OK' ELSE 'GAGAL' END AS Hasil
UNION ALL
SELECT 'B. Asal-usul',
       CASE WHEN COL_LENGTH('dbo.N_WEB_CAREERS_Master_Skrining_Pertanyaan','Bank_Id') IS NOT NULL
             AND COL_LENGTH('dbo.N_WEB_CAREERS_Master_Skrining_Pertanyaan','Bank_Kode') IS NOT NULL
             AND COL_LENGTH('dbo.N_WEB_CAREERS_Master_Skrining_Pertanyaan','Flag_Ubahan') IS NOT NULL
            THEN 'OK — 3 kolom' ELSE 'GAGAL' END
UNION ALL
SELECT 'C. Isi awal',
       'OK — ' + CAST((SELECT COUNT(*) FROM dbo.N_WEB_CAREERS_Master_Pertanyaan
                        WHERE Konteks = 'SKRINING') AS VARCHAR(10)) + ' pertanyaan'
UNION ALL
SELECT 'D. Menu & akses',
       CASE WHEN EXISTS (SELECT 1 FROM dbo.N_WEB_CAREERS_Menu WHERE Jenis_Page = 'masterPertanyaanPage')
            THEN 'OK — ' + CAST((SELECT COUNT(*) FROM dbo.N_WEB_CAREERS_Page_Access
                                  WHERE Jenis_Page = 'masterPertanyaanPage') AS VARCHAR(10))
                 + ' akun diberi akses'
            ELSE 'GAGAL' END;
GO
