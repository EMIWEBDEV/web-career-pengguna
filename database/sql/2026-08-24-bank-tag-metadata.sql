/* ============================================================================
   WEB CAREERS — BANK PERTANYAAN: TAG & METADATA REKRUTMEN
   ----------------------------------------------------------------------------
   Jalankan SESUDAH 2026-08-24-bank-pertanyaan.sql
   Jalankan di basis data: Web_HRIS
   Aman dijalankan berkali-kali (idempoten).

   ── APA YANG DITAMBAHKAN ────────────────────────────────────────────────────

   1. TAG BERDIMENSI + tabel pengikat (satu pertanyaan boleh banyak tag).
   2. METADATA rekrutmen pada tiap pertanyaan — jenis, prioritas, kriteria
      penilaian, red flag, pertanyaan lanjutan, dan tata kelola versinya.

   ── KENAPA TAG BERDIMENSI, BUKAN SEKADAR DAFTAR KATA ────────────────────────

   Kalau tag cuma daftar kata bebas, "IT", "Manager", dan "Fresh Graduate"
   duduk di satu keranjang yang sama. Layar tidak bisa menawarkan
   "pilih Job Family" tanpa ikut menawarkan level dan tipe kandidat di dropdown
   yang sama, dan penyaringan "IT + Manager" jadi mustahil dibedakan dari
   "IT atau Manager".

   Kolom `Dimensi` memisahkannya:

     JOB_FAMILY     IT, Finance, HC, Sales, Marketing, Operations, …
     LEVEL          Entry, Professional, Leadership, Senior Leadership
     CANDIDATE_TYPE Fresh Graduate, Experienced Hire, Internship, MT
     KOMPETENSI     Communication, Problem Solving, Ownership, …
     FASE           Opening, Verification, Motivation, Closing, …

   Penyaringan lalu bisa berbunyi: DALAM dimensi sama pakai ATAU
   (IT atau Finance), ANTAR dimensi pakai DAN (IT DAN Manager). Itu satu-satunya
   penafsiran yang masuk akal bagi orang yang menyusun template, dan ia mustahil
   dinyatakan tanpa dimensi.

   ── KENAPA PENGIKATNYA TABEL, BUKAN KOLOM JSON ──────────────────────────────

   Karena penyaringan "beri saya pertanyaan bertag IT" adalah kueri yang
   dijalankan setiap kali orang menyusun template. JSON di SQL Server bisa
   dicari, tapi tidak bisa diindeks untuk pola ini — dan sistem ini sudah pernah
   membayar harganya lewat Formulir_Jawaban_Index.

   ── URUTAN BLOK ─────────────────────────────────────────────────────────────
     A. Tabel tag
     B. Tabel pengikat
     C. Metadata rekrutmen pada Master_Pertanyaan
     D. Isi tag bawaan
     E. Verifikasi
   ============================================================================ */


/* ============================================================================
   A. MASTER TAG
   ============================================================================ */

IF OBJECT_ID('dbo.N_WEB_CAREERS_Master_Tag', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.N_WEB_CAREERS_Master_Tag (
        Id_Master_Tag INT IDENTITY(1,1) NOT NULL,

        /* Dimensi menentukan ARTI tag, dan cara menyaringnya.
           JOB_FAMILY | LEVEL | CANDIDATE_TYPE | KOMPETENSI | FASE */
        Dimensi       VARCHAR(20)  NOT NULL,
        Kode          VARCHAR(40)  NOT NULL,
        Nama          VARCHAR(120) NOT NULL,
        Deskripsi     VARCHAR(400) NULL,

        /* Warna & ikon dipakai chip di layar. Tag tanpa warna terbaca sebagai
           daftar teks abu-abu yang tidak bisa dipindai mata saat isinya
           empat puluh baris. */
        Warna         VARCHAR(20)  NULL,
        Ikon          VARCHAR(50)  NULL,

        /* Padanan di HRIS. Terisi hanya untuk JOB_FAMILY yang benar-benar
           punya divisi tandingannya — inilah yang membuat "ambil pertanyaan
           untuk departemen loker ini" bisa dijawab tanpa orang memetakannya
           sendiri setiap kali. Kosong bukan kesalahan: banyak job family
           bersifat lintas divisi. */
        Ref_Divisi_Id INT          NULL,

        Flag_Sistem   CHAR(1)      NOT NULL CONSTRAINT DF_NWC_Tag_Sistem DEFAULT 'T',
        Flag_Aktif    CHAR(1)      NOT NULL CONSTRAINT DF_NWC_Tag_Aktif DEFAULT 'Y',
        Urutan        INT          NOT NULL CONSTRAINT DF_NWC_Tag_Urutan DEFAULT 0,

        Created_At    DATETIME     NULL,
        Created_By    VARCHAR(200) NULL,
        Created_By_Id INT          NULL,
        Updated_At    DATETIME     NULL,
        Updated_By    VARCHAR(200) NULL,
        Updated_By_Id INT          NULL,

        CONSTRAINT PK_NWC_Master_Tag PRIMARY KEY CLUSTERED (Id_Master_Tag),
        CONSTRAINT CK_NWC_Tag_Dimensi
            CHECK (Dimensi IN ('JOB_FAMILY', 'LEVEL', 'CANDIDATE_TYPE', 'KOMPETENSI', 'FASE'))
    );

    /* Unik PER DIMENSI: 'MANAGER' boleh ada sebagai LEVEL sekaligus sebagai
       JOB_FAMILY kelak, dan keduanya memang hal yang berbeda. */
    CREATE UNIQUE NONCLUSTERED INDEX UX_NWC_Tag_Kode
        ON dbo.N_WEB_CAREERS_Master_Tag (Dimensi, Kode);

    CREATE NONCLUSTERED INDEX IX_NWC_Tag_Pilih
        ON dbo.N_WEB_CAREERS_Master_Tag (Dimensi, Flag_Aktif, Urutan)
        INCLUDE (Kode, Nama, Warna, Ikon);
END
GO


/* ============================================================================
   B. PENGIKAT PERTANYAAN ↔ TAG
   ============================================================================ */

IF OBJECT_ID('dbo.N_WEB_CAREERS_Master_Pertanyaan_Tag', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.N_WEB_CAREERS_Master_Pertanyaan_Tag (
        Id_Master_Pertanyaan_Tag INT IDENTITY(1,1) NOT NULL,
        Master_Pertanyaan_Id     INT          NOT NULL,
        Master_Tag_Id            INT          NOT NULL,

        /* Dimensi & kode DISALIN dari tag. Denormalisasi sengaja: kueri
           penyaringan yang paling sering dijalankan berbunyi "pertanyaan
           bertag JOB_FAMILY=IT", dan menyalinnya ke sini membuat kueri itu
           selesai di satu tabel dengan satu indeks — tanpa join ke master tag
           untuk setiap baris pertanyaan yang diperiksa. */
        Dimensi                  VARCHAR(20)  NOT NULL,
        Tag_Kode                 VARCHAR(40)  NOT NULL,

        Created_At               DATETIME     NULL,
        Created_By               VARCHAR(200) NULL,
        Created_By_Id            INT          NULL,

        CONSTRAINT PK_NWC_Pertanyaan_Tag PRIMARY KEY CLUSTERED (Id_Master_Pertanyaan_Tag),
        CONSTRAINT FK_NWC_PTag_Pertanyaan
            FOREIGN KEY (Master_Pertanyaan_Id)
            REFERENCES dbo.N_WEB_CAREERS_Master_Pertanyaan (Id_Master_Pertanyaan)
            ON DELETE CASCADE,
        CONSTRAINT FK_NWC_PTag_Tag
            FOREIGN KEY (Master_Tag_Id)
            REFERENCES dbo.N_WEB_CAREERS_Master_Tag (Id_Master_Tag)
    );

    CREATE UNIQUE NONCLUSTERED INDEX UX_NWC_PTag_Pasangan
        ON dbo.N_WEB_CAREERS_Master_Pertanyaan_Tag (Master_Pertanyaan_Id, Master_Tag_Id);

    /* Kueri penyaringan: "beri saya pertanyaan bertag ini". */
    CREATE NONCLUSTERED INDEX IX_NWC_PTag_Cari
        ON dbo.N_WEB_CAREERS_Master_Pertanyaan_Tag (Dimensi, Tag_Kode)
        INCLUDE (Master_Pertanyaan_Id);
END
GO


/* ============================================================================
   C. METADATA REKRUTMEN

   Semuanya NULL-able atau berdefault. Pertanyaan lama tetap sah tanpa satu pun
   kolom ini terisi — metadata memperkaya, bukan mensyaratkan.
   ============================================================================ */

IF COL_LENGTH('dbo.N_WEB_CAREERS_Master_Pertanyaan', 'Sub_Kelompok') IS NULL
    ALTER TABLE dbo.N_WEB_CAREERS_Master_Pertanyaan
        ADD Sub_Kelompok VARCHAR(60) NULL,

            /* JENIS PERTANYAAN — kosakata Talent Acquisition, bukan kosakata
               teknis formulir. `Tipe` yang sudah ada menjawab "bentuk isiannya
               apa" (RADIO/RATING/TEXTAREA); kolom ini menjawab "pertanyaan ini
               untuk apa" (BEHAVIORAL/TECHNICAL/RED_FLAG). Dua hal yang berbeda,
               dan menyatukannya memaksa salah satunya hilang. */
            Jenis VARCHAR(24) NULL,

            /* MANDATORY | RECOMMENDED | OPTIONAL | CONDITIONAL */
            Prioritas VARCHAR(16) NULL,

            /* Perkiraan waktu menjawab. Dipakai penyusun template menghitung
               panjang sesi — phone screening yang melewati 20 menit kehilangan
               perhatian kandidat, dan tanpa angka ini panjangnya baru ketahuan
               saat teleponnya sudah berjalan. */
            Durasi_Detik INT NULL,

            /* Panduan untuk PETUGAS, bukan untuk kandidat. */
            Jawaban_Diharapkan VARCHAR(1000) NULL,
            Red_Flag VARCHAR(1000) NULL,
            Pertanyaan_Lanjutan VARCHAR(1000) NULL,

            /* Rubrik 1–5 sebagai JSON: {"1":"…","2":"…",…}.
               JSON di sini justru tepat — isinya dibaca utuh untuk ditampilkan,
               tidak pernah diagregasi, dan bentuknya memang lima teks bebas. */
            Kriteria_Nilai NVARCHAR(MAX) NULL;
GO

IF COL_LENGTH('dbo.N_WEB_CAREERS_Master_Pertanyaan', 'Versi') IS NULL
    ALTER TABLE dbo.N_WEB_CAREERS_Master_Pertanyaan
        /* ── TATA KELOLA ────────────────────────────────────────────────────
           Bank pertanyaan perusahaan bukan catatan pribadi: ia dipakai
           memutuskan nasib orang, dan harus bisa dijawab "siapa yang
           menyetujui redaksi ini, dan kapan terakhir ditinjau".

           Status_Tinjau: DRAFT | REVIEW | APPROVED | ARCHIVED */
        ADD Versi INT NOT NULL CONSTRAINT DF_NWC_MPertanyaan_Versi DEFAULT 1,
            Status_Tinjau VARCHAR(16) NOT NULL CONSTRAINT DF_NWC_MPertanyaan_Tinjau DEFAULT 'APPROVED',
            Ditinjau_Oleh VARCHAR(200) NULL,
            Ditinjau_Pada DATETIME NULL,
            Pemilik VARCHAR(200) NULL;
GO

/* Indeks penyaringan gabungan — dipakai layar pemilih pertanyaan. */
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_NWC_MPertanyaan_Saring')
    CREATE NONCLUSTERED INDEX IX_NWC_MPertanyaan_Saring
        ON dbo.N_WEB_CAREERS_Master_Pertanyaan (Konteks, Flag_Aktif, Jenis, Prioritas)
        INCLUDE (Kode, Kelompok, Sub_Kelompok, Tipe, Label, Urutan);
GO


/* ============================================================================
   D. TAG BAWAAN

   Lima dimensi, terisi lengkap. Semuanya Flag_Sistem='Y' — tidak bisa dihapus,
   hanya dinonaktifkan, karena kodenya dirujuk seluruh pengikatan pertanyaan.
   ============================================================================ */

MERGE dbo.N_WEB_CAREERS_Master_Tag AS t
USING (VALUES
    -- ══ JOB FAMILY ══════════════════════════════════════════════════════════
    ('JOB_FAMILY', 'GENERAL',       'Umum / Semua Posisi',      'Berlaku untuk hampir seluruh kandidat', '#6366f1', 'bi-globe2',              10),
    ('JOB_FAMILY', 'HC',            'Human Capital',            'Rekrutmen, L&D, C&B, Employee Relations', '#8b5cf6', 'bi-people',            20),
    ('JOB_FAMILY', 'IT',            'IT / Technology',          'Software, infrastruktur, data, keamanan', '#0ea5e9', 'bi-cpu',               30),
    ('JOB_FAMILY', 'FINANCE',       'Finance',                  'Pelaporan, anggaran, analisis keuangan', '#059669', 'bi-graph-up-arrow',     40),
    ('JOB_FAMILY', 'ACCOUNTING',    'Accounting',               'Pembukuan, rekonsiliasi, perpajakan',    '#10b981', 'bi-journal-check',      50),
    ('JOB_FAMILY', 'SALES',         'Sales',                    'Akuisisi, target, pipeline, negosiasi',  '#f59e0b', 'bi-bag-check',          60),
    ('JOB_FAMILY', 'MARKETING',     'Marketing',                'Brand, digital, riset pasar, kampanye',  '#ec4899', 'bi-megaphone',          70),
    ('JOB_FAMILY', 'OPERATIONS',    'Operations',               'Produksi, kualitas, efisiensi proses',   '#f97316', 'bi-gear-wide-connected',80),
    ('JOB_FAMILY', 'ENGINEERING',   'Engineering',              'Teknik, pemeliharaan, proyek',           '#64748b', 'bi-tools',              90),
    ('JOB_FAMILY', 'LEGAL',         'Legal',                    'Kontrak, kepatuhan, litigasi',           '#7c3aed', 'bi-bank',              100),
    ('JOB_FAMILY', 'PROCUREMENT',   'Procurement',              'Pengadaan, vendor, negosiasi harga',     '#0891b2', 'bi-cart-check',        110),
    ('JOB_FAMILY', 'SUPPLY_CHAIN',  'Supply Chain',             'Perencanaan, gudang, distribusi',        '#0d9488', 'bi-truck',             120),
    ('JOB_FAMILY', 'CUSTOMER_SVC',  'Customer Service',         'Layanan pelanggan, penanganan keluhan',  '#d946ef', 'bi-headset',           130),
    ('JOB_FAMILY', 'PRODUCTION',    'Production / Manufaktur',  'Lini produksi, target output, K3',       '#a16207', 'bi-boxes',             140),
    ('JOB_FAMILY', 'QA_QC',         'Quality Assurance / QC',   'Standar mutu, inspeksi, audit internal', '#4d7c0f', 'bi-clipboard-check',   150),
    ('JOB_FAMILY', 'HSE',           'HSE / K3',                 'Keselamatan, kesehatan kerja, lingkungan','#dc2626', 'bi-shield-check',      160),
    ('JOB_FAMILY', 'GA',            'General Affairs',          'Aset, fasilitas, perizinan, kendaraan',  '#78716c', 'bi-building-gear',     170),
    ('JOB_FAMILY', 'AUDIT',         'Internal Audit',           'Audit internal, kontrol, kepatuhan',     '#b45309', 'bi-search',            180),
    ('JOB_FAMILY', 'RND',           'Research & Development',   'Pengembangan produk, formulasi, uji',    '#2563eb', 'bi-lightbulb',         190),
    ('JOB_FAMILY', 'DATA',          'Data & Analytics',         'Analisis data, BI, pelaporan',           '#3b82f6', 'bi-bar-chart-line',    200),

    -- ══ LEVEL ═══════════════════════════════════════════════════════════════
    ('LEVEL', 'ENTRY',        'Entry Level',        'Fresh graduate, magang, staf awal',         '#94a3b8', 'bi-1-circle',  10),
    ('LEVEL', 'PROFESSIONAL', 'Professional',       'Senior staff, specialist, individual contributor', '#6366f1', 'bi-2-circle', 20),
    ('LEVEL', 'LEADERSHIP',   'Leadership',         'Supervisor, assistant manager, manager',    '#8b5cf6', 'bi-3-circle',  30),
    ('LEVEL', 'SENIOR_LEAD',  'Senior Leadership',  'Senior manager, head, department head',     '#7c3aed', 'bi-4-circle',  40),

    -- ══ TIPE KANDIDAT ═══════════════════════════════════════════════════════
    ('CANDIDATE_TYPE', 'FRESH_GRAD',   'Fresh Graduate',    'Belum punya pengalaman kerja penuh waktu', '#22c55e', 'bi-mortarboard', 10),
    ('CANDIDATE_TYPE', 'EXPERIENCED',  'Experienced Hire',  'Sudah bekerja, pindah dari perusahaan lain','#0ea5e9', 'bi-briefcase',  20),
    ('CANDIDATE_TYPE', 'INTERNSHIP',   'Internship',        'Magang, masih menempuh pendidikan',        '#f59e0b', 'bi-backpack',   30),
    ('CANDIDATE_TYPE', 'MT',           'Management Trainee','Program kader manajemen',                  '#8b5cf6', 'bi-stars',      40),
    ('CANDIDATE_TYPE', 'REHIRE',       'Rehire / Alumni',   'Pernah bekerja di perusahaan ini',         '#64748b', 'bi-arrow-repeat',50),
    ('CANDIDATE_TYPE', 'INTERNAL',     'Internal Mobility', 'Karyawan internal yang pindah posisi',     '#0d9488', 'bi-shuffle',    60),

    -- ══ KOMPETENSI ══════════════════════════════════════════════════════════
    ('KOMPETENSI', 'COMMUNICATION',  'Communication',       'Menyampaikan gagasan dengan jelas',        '#0ea5e9', 'bi-chat-dots',       10),
    ('KOMPETENSI', 'TEAMWORK',       'Teamwork',            'Bekerja efektif dalam tim',                '#6366f1', 'bi-people-fill',     20),
    ('KOMPETENSI', 'PROBLEM_SOLVING','Problem Solving',     'Mengurai masalah dan menemukan solusi',    '#f59e0b', 'bi-puzzle',          30),
    ('KOMPETENSI', 'OWNERSHIP',      'Ownership',           'Bertanggung jawab atas hasil',             '#059669', 'bi-hand-thumbs-up',  40),
    ('KOMPETENSI', 'ADAPTABILITY',   'Adaptability',        'Menyesuaikan diri pada perubahan',         '#8b5cf6', 'bi-arrow-left-right',50),
    ('KOMPETENSI', 'LEARNING',       'Learning Agility',    'Cepat mempelajari hal baru',               '#22c55e', 'bi-book',            60),
    ('KOMPETENSI', 'INITIATIVE',     'Initiative',          'Bertindak tanpa menunggu diminta',         '#f97316', 'bi-lightning',       70),
    ('KOMPETENSI', 'INTEGRITY',      'Integrity',           'Jujur dan konsisten pada prinsip',         '#dc2626', 'bi-shield-lock',     80),
    ('KOMPETENSI', 'RESILIENCE',     'Resilience',          'Bertahan menghadapi tekanan',              '#b45309', 'bi-life-preserver',  90),
    ('KOMPETENSI', 'CUSTOMER_ORI',   'Customer Orientation','Berorientasi pada kebutuhan pelanggan',    '#d946ef', 'bi-heart',          100),
    ('KOMPETENSI', 'LEADERSHIP_C',   'Leadership',          'Mengarahkan dan mengembangkan orang',      '#7c3aed', 'bi-person-badge',   110),
    ('KOMPETENSI', 'COLLABORATION',  'Collaboration',       'Bekerja lintas fungsi',                    '#0d9488', 'bi-diagram-3',      120),
    ('KOMPETENSI', 'ANALYTICAL',     'Analytical Thinking', 'Berpikir runtut berbasis data',            '#3b82f6', 'bi-graph-up',       130),
    ('KOMPETENSI', 'PLANNING',       'Planning & Organizing','Merencanakan dan menata prioritas',       '#64748b', 'bi-calendar-check', 140),
    ('KOMPETENSI', 'DETAIL',         'Attention to Detail', 'Teliti pada hal kecil yang penting',       '#a16207', 'bi-zoom-in',        150),
    ('KOMPETENSI', 'NEGOTIATION',    'Negotiation',         'Mencapai kesepakatan yang saling untung',  '#ec4899', 'bi-handshake',      160),

    -- ══ FASE SESI ═══════════════════════════════════════════════════════════
    ('FASE', 'OPENING',        'Opening',              'Pembuka dan perkenalan',              '#94a3b8', 'bi-play-circle',   10),
    ('FASE', 'VERIFICATION',   'Candidate Verification','Verifikasi data dasar kandidat',     '#64748b', 'bi-person-check',  20),
    ('FASE', 'BACKGROUND',     'Career Background',    'Riwayat karier dan pekerjaan',        '#6366f1', 'bi-clock-history', 30),
    ('FASE', 'MOTIVATION',     'Motivation',           'Motivasi melamar dan berpindah',      '#8b5cf6', 'bi-fire',          40),
    ('FASE', 'EXPERIENCE',     'Experience',           'Pengalaman relevan dengan posisi',    '#0ea5e9', 'bi-briefcase',     50),
    ('FASE', 'BEHAVIORAL',     'Behavioral',           'Perilaku berbasis pengalaman nyata',  '#f59e0b', 'bi-person-lines-fill', 60),
    ('FASE', 'TECHNICAL',      'Technical / Functional','Kemampuan teknis sesuai job family', '#059669', 'bi-wrench',        70),
    ('FASE', 'AVAILABILITY',   'Availability',         'Kesiapan mulai dan penempatan',       '#f97316', 'bi-calendar-week', 80),
    ('FASE', 'COMPENSATION',   'Compensation',         'Ekspektasi gaji dan tunjangan',       '#a16207', 'bi-cash-stack',    90),
    ('FASE', 'CULTURE_FIT',    'Culture Fit',          'Kesesuaian dengan cara kerja',        '#d946ef', 'bi-diagram-2',    100),
    ('FASE', 'CANDIDATE_Q',    'Candidate Questions',  'Pertanyaan dari kandidat',            '#0d9488', 'bi-question-circle',110),
    ('FASE', 'CLOSING',        'Closing',              'Penutup dan langkah berikutnya',      '#94a3b8', 'bi-flag',         120)
) AS s (Dimensi, Kode, Nama, Deskripsi, Warna, Ikon, Urutan)
   ON t.Dimensi = s.Dimensi AND t.Kode = s.Kode
 WHEN NOT MATCHED BY TARGET THEN
    INSERT (Dimensi, Kode, Nama, Deskripsi, Warna, Ikon, Flag_Sistem, Flag_Aktif, Urutan,
            Created_At, Created_By, Updated_At, Updated_By)
    VALUES (s.Dimensi, s.Kode, s.Nama, s.Deskripsi, s.Warna, s.Ikon, 'Y', 'Y', s.Urutan,
            GETDATE(), 'SISTEM(bank-tag)', GETDATE(), 'SISTEM(bank-tag)')
 WHEN MATCHED AND (t.Nama <> s.Nama OR ISNULL(t.Warna,'') <> s.Warna OR ISNULL(t.Ikon,'') <> s.Ikon) THEN
    UPDATE SET t.Nama = s.Nama, t.Deskripsi = s.Deskripsi, t.Warna = s.Warna, t.Ikon = s.Ikon,
               t.Updated_At = GETDATE(), t.Updated_By = 'SISTEM(bank-tag)';
GO


/* ============================================================================
   E. VERIFIKASI
   ============================================================================ */

SELECT 'A. Tabel tag' AS Pemeriksaan,
       CASE WHEN OBJECT_ID('dbo.N_WEB_CAREERS_Master_Tag','U') IS NOT NULL THEN 'OK' ELSE 'GAGAL' END AS Hasil
UNION ALL
SELECT 'B. Tabel pengikat',
       CASE WHEN OBJECT_ID('dbo.N_WEB_CAREERS_Master_Pertanyaan_Tag','U') IS NOT NULL THEN 'OK' ELSE 'GAGAL' END
UNION ALL
SELECT 'C. Metadata rekrutmen',
       CASE WHEN COL_LENGTH('dbo.N_WEB_CAREERS_Master_Pertanyaan','Jenis') IS NOT NULL
             AND COL_LENGTH('dbo.N_WEB_CAREERS_Master_Pertanyaan','Prioritas') IS NOT NULL
             AND COL_LENGTH('dbo.N_WEB_CAREERS_Master_Pertanyaan','Kriteria_Nilai') IS NOT NULL
             AND COL_LENGTH('dbo.N_WEB_CAREERS_Master_Pertanyaan','Status_Tinjau') IS NOT NULL
            THEN 'OK - 12 kolom' ELSE 'GAGAL' END;
GO

SELECT Dimensi, COUNT(*) AS Jumlah
  FROM dbo.N_WEB_CAREERS_Master_Tag
 GROUP BY Dimensi
 ORDER BY Dimensi;
GO
