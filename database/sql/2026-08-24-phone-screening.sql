/* ============================================================================
   WEB CAREERS — PHONE SCREENING (kuesioner skrining yang diisi rekruter)
   ----------------------------------------------------------------------------
   Jalankan di basis data: Web_HRIS
   Aman dijalankan berkali-kali (idempoten): setiap blok memeriksa dulu.

   ── APA YANG DIBANGUN ───────────────────────────────────────────────────────

   Tiga lapis yang sengaja dipisah tegas:

     LAPIS 1  MASTER      Master_Skrining + _Versi + _Pertanyaan
              Pustaka template yang dirawat tim rekrutmen. Berversi, seperti
              Master Formulir: menyunting template yang sudah terbit TIDAK
              menimpa — ia mengklon ke versi berikutnya.

     LAPIS 2  PENGIKATAN  Program_Skrining
              Jawaban atas "loker ini pakai template mana". Satu baris bisa
              berlaku untuk satu loker (Program_Posisi_Id terisi) atau untuk
              seluruh program (Program_Posisi_Id NULL) — supaya program berisi
              50 loker tidak perlu diatur 50 kali.

     LAPIS 3  RUNTIME     Lamaran_Skrining + _Jawaban
              Sesi wawancara yang benar-benar terjadi beserta jawabannya.

   ── KENAPA TIDAK MENUMPANG MASTER FEEDBACK ──────────────────────────────────

   Feedback_Jawaban mewajibkan Email_Token kandidat dan Dashboard Feedback
   mengagregasi lintas form. Instrumen internal rekruter yang dititipkan di
   sana akan muncul di grafik kepuasan kandidat. Kosakata tipe pertanyaannya
   dipinjam (RATING/LIKERT/NPS/RADIO/CHECKBOX/TEXTAREA), tabelnya tidak.

   ── PENANDA TIPE TAHAP, BUKAN PERBANDINGAN KODE ─────────────────────────────

   Flag_Skrining di Master_Tipe_Tahap yang menentukan tahap mana yang punya
   kuesioner — bukan `if (kode === 'PHONE_SCREEN')` di dalam program. Sepola
   dengan Flag_Pemeriksaan yang sudah dipakai background check. PHONE_SCREEN
   memang yang pertama membutuhkannya, tapi user screening dan walk-in
   screening berperilaku sama; sebagai kolom master, keduanya cukup dicentang.

   ── URUTAN BLOK ─────────────────────────────────────────────────────────────
     A. Master template (3 tabel)
     B. Pengikatan ke loker (1 tabel)
     C. Runtime & jawaban (2 tabel)
     D. Perubahan tabel lama (4 ALTER, 6 kolom)
     E. Penanda PHONE_SCREEN
     F. Menu & hak akses
     G. Verifikasi
   ============================================================================ */


/* ============================================================================
   A. LAPIS 1 — MASTER TEMPLATE
   ============================================================================ */

/* ── A1. Master_Skrining ─────────────────────────────────────────────────────
   Identitas template yang STABIL LINTAS VERSI. Kode-lah yang dibekukan di
   runtime, bukan Id — supaya template yang dipindah antar-lingkungan (lokal →
   staging → produksi) tetap tersambung dengan sesi yang sudah tercatat. */
IF OBJECT_ID('dbo.N_WEB_CAREERS_Master_Skrining', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.N_WEB_CAREERS_Master_Skrining (
        Id_Master_Skrining  INT IDENTITY(1,1) NOT NULL,
        Kode                VARCHAR(30)   NOT NULL,
        Nama                VARCHAR(120)  NOT NULL,
        Deskripsi           VARCHAR(500)  NULL,
        /* Ditampilkan di atas kuesioner saat rekruter membukanya — tempat
           menuliskan hal yang harus disampaikan sebelum pertanyaan dimulai
           (perkenalan, izin merekam, perkiraan durasi). */
        Petunjuk            VARCHAR(1000) NULL,
        /* Sepola Master_Formulir.Kategori: REKRUTMEN / MT / INTERNSHIP.
           Dipakai menyaring pilihan template saat mengikat ke program. */
        Kategori            VARCHAR(20)   NULL,
        Flag_Aktif          CHAR(1)       NOT NULL CONSTRAINT DF_NWC_Skrining_Aktif DEFAULT 'Y',
        Created_At          DATETIME      NULL,
        Created_By          VARCHAR(200)  NULL,
        Created_By_Id       INT           NULL,
        Updated_At          DATETIME      NULL,
        Updated_By          VARCHAR(200)  NULL,
        Updated_By_Id       INT           NULL,

        CONSTRAINT PK_NWC_Master_Skrining PRIMARY KEY CLUSTERED (Id_Master_Skrining)
    );

    CREATE UNIQUE NONCLUSTERED INDEX UX_NWC_Master_Skrining_Kode
        ON dbo.N_WEB_CAREERS_Master_Skrining (Kode);

    CREATE NONCLUSTERED INDEX IX_NWC_Master_Skrining_Aktif
        ON dbo.N_WEB_CAREERS_Master_Skrining (Flag_Aktif, Kategori)
        INCLUDE (Kode, Nama);
END
GO

/* ── A2. Master_Skrining_Versi ───────────────────────────────────────────────
   Cerminan Master_Formulir_Versi yang sudah terbukti — TANPA Schema_Json,
   karena pertanyaannya relasional (lihat A3 dan alasannya di sana).

   DUA INDEKS UNIK BERSARING adalah aturan yang ditegakkan basis data, bukan
   sekadar kesepakatan di kode:
     · satu template hanya boleh punya SATU versi PUBLISHED;
     · dan hanya SATU draf yang sedang disunting.
   Tanpa keduanya, dua penyunting yang bekerja bersamaan bisa menerbitkan dua
   versi sekaligus, dan resolusi "versi terbit terbaru" jadi tak tentu. */
IF OBJECT_ID('dbo.N_WEB_CAREERS_Master_Skrining_Versi', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.N_WEB_CAREERS_Master_Skrining_Versi (
        Id_Master_Skrining_Versi INT IDENTITY(1,1) NOT NULL,
        Master_Skrining_Id       INT           NOT NULL,
        Versi                    INT           NOT NULL,
        /* DRAFT → PUBLISHED → ARCHIVED. Tidak pernah dihapus: versi lama
           adalah satu-satunya cara membaca ulang sesi yang memakainya. */
        Status                   VARCHAR(20)   NOT NULL CONSTRAINT DF_NWC_SkrVersi_Status DEFAULT 'DRAFT',
        Catatan                  VARCHAR(MAX)  NULL,
        Published_At             DATETIME      NULL,
        Created_At               DATETIME      NULL,
        Created_By               VARCHAR(200)  NULL,
        Created_By_Id            INT           NULL,
        Updated_At               DATETIME      NULL,
        Updated_By               VARCHAR(200)  NULL,
        Updated_By_Id            INT           NULL,

        CONSTRAINT PK_NWC_Master_Skrining_Versi PRIMARY KEY CLUSTERED (Id_Master_Skrining_Versi),
        CONSTRAINT FK_NWC_SkrVersi_Skrining
            FOREIGN KEY (Master_Skrining_Id)
            REFERENCES dbo.N_WEB_CAREERS_Master_Skrining (Id_Master_Skrining),
        CONSTRAINT CK_NWC_SkrVersi_Status
            CHECK (Status IN ('DRAFT', 'PUBLISHED', 'ARCHIVED'))
    );

    CREATE UNIQUE NONCLUSTERED INDEX UX_NWC_SkrVersi_Nomor
        ON dbo.N_WEB_CAREERS_Master_Skrining_Versi (Master_Skrining_Id, Versi);

    CREATE UNIQUE NONCLUSTERED INDEX UX_NWC_SkrVersi_Terbit
        ON dbo.N_WEB_CAREERS_Master_Skrining_Versi (Master_Skrining_Id)
        WHERE Status = 'PUBLISHED';

    CREATE UNIQUE NONCLUSTERED INDEX UX_NWC_SkrVersi_Draf
        ON dbo.N_WEB_CAREERS_Master_Skrining_Versi (Master_Skrining_Id)
        WHERE Status = 'DRAFT';
END
GO

/* ── A3. Master_Skrining_Pertanyaan ──────────────────────────────────────────
   Menempel ke VERSI, bukan ke template. Inilah yang membuat versinya nyata:
   kandidat yang diskrining dengan v3 tetap terbaca dengan pertanyaan v3,
   meski templatenya sudah sampai v7.

   Konsekuensinya menyunting template terbit mengklon seluruh pertanyaannya.
   Template 50 pertanyaan yang direvisi 10 kali meninggalkan 500 baris — murah
   untuk basis data ini, dan imbalannya besar.

   ── KOLOM YANG BUKAN SEKADAR KETERANGAN ────────────────────────────────────

   `Kode` STABIL LINTAS VERSI dan itu disengaja. Ia yang dipakai MesinSyarat
   sebagai nama field saat jawaban skrining disuapkan ke aturan auto-gugur,
   dan yang menyambungkan pertanyaan "gaji harapan" di v3 dengan pertanyaan
   yang sama di v7 untuk keperluan analitik lintas periode.

   `Bobot` NULL berarti pertanyaan ini TIDAK MASUK SKOR. Bukan nol: nol
   berarti "masuk hitungan tapi tidak bernilai", dan itu menggeser rata-rata.

   `Knockout_Operator` memakai kosakata yang SAMA PERSIS dengan
   MesinSyarat::OPERATOR — '=', '!=', '>', '<', '>=', '<=', 'ANTARA',
   'ADA_DI', 'TIDAK_ADA_DI'. Satu kosakata, bukan dua yang mirip. */
IF OBJECT_ID('dbo.N_WEB_CAREERS_Master_Skrining_Pertanyaan', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.N_WEB_CAREERS_Master_Skrining_Pertanyaan (
        Id_Master_Skrining_Pertanyaan INT IDENTITY(1,1) NOT NULL,
        Master_Skrining_Versi_Id      INT           NOT NULL,
        /* Pengelompokan di layar ("Ketersediaan", "Kompensasi", "Motivasi").
           Kosong = tanpa kelompok. */
        Seksi                         VARCHAR(120)  NULL,
        Urutan                        INT           NOT NULL,
        Kode                          VARCHAR(40)   NOT NULL,
        /* RADIO CHECKBOX SELECT RATING LIKERT NPS BOOLEAN
           TEXT TEXTAREA EDITOR NUMBER CURRENCY DATE
           Enam yang pertama dipinjam dari Master Feedback; sisanya dibutuhkan
           skrining tapi tidak dibutuhkan feedback. */
        Tipe                          VARCHAR(20)   NOT NULL,
        Label                         VARCHAR(500)  NOT NULL,
        Bantuan                       VARCHAR(500)  NULL,
        /* JSON: [{"nilai":"YA","label":"Ya","skor":10}, ...]
           `skor` opsional — dipakai hanya bila Bobot pertanyaannya terisi. */
        Opsi                          NVARCHAR(MAX) NULL,
        Skala_Min                     INT           NULL,
        Skala_Max                     INT           NULL,
        Label_Min                     VARCHAR(100)  NULL,
        Label_Max                     VARCHAR(100)  NULL,
        Flag_Wajib                    CHAR(1)       NOT NULL CONSTRAINT DF_NWC_SkrTanya_Wajib DEFAULT 'T',
        Bobot                         DECIMAL(6,2)  NULL,
        Flag_Knockout                 CHAR(1)       NOT NULL CONSTRAINT DF_NWC_SkrTanya_Knockout DEFAULT 'T',
        Knockout_Operator             VARCHAR(20)   NULL,
        Knockout_Nilai                VARCHAR(500)  NULL,
        Knockout_Pesan                VARCHAR(500)  NULL,
        /* Sediakan kotak catatan kecil di bawah pertanyaan ini — untuk hal
           yang tidak muat di pilihan, tanpa memaksa semuanya jadi esai. */
        Flag_Catatan                  CHAR(1)       NOT NULL CONSTRAINT DF_NWC_SkrTanya_Catatan DEFAULT 'T',
        /* JSON, sepola `tampil_jika` di katalog field formulir:
           {"kode":"bersedia_relokasi","operator":"=","nilai":"YA"} */
        Tampil_Jika                   NVARCHAR(MAX) NULL,
        Created_At                    DATETIME      NULL,
        Created_By                    VARCHAR(200)  NULL,
        Created_By_Id                 INT           NULL,
        Updated_At                    DATETIME      NULL,
        Updated_By                    VARCHAR(200)  NULL,
        Updated_By_Id                 INT           NULL,

        CONSTRAINT PK_NWC_Master_Skrining_Pertanyaan PRIMARY KEY CLUSTERED (Id_Master_Skrining_Pertanyaan),
        CONSTRAINT FK_NWC_SkrTanya_Versi
            FOREIGN KEY (Master_Skrining_Versi_Id)
            REFERENCES dbo.N_WEB_CAREERS_Master_Skrining_Versi (Id_Master_Skrining_Versi),
        CONSTRAINT CK_NWC_SkrTanya_Tipe
            CHECK (Tipe IN ('RADIO','CHECKBOX','SELECT','RATING','LIKERT','NPS','BOOLEAN',
                            'TEXT','TEXTAREA','EDITOR','NUMBER','CURRENCY','DATE'))
    );

    /* Kode unik PER VERSI, bukan per template: versi berikutnya memakai kode
       yang sama persis, dan itu memang tujuannya. */
    CREATE UNIQUE NONCLUSTERED INDEX UX_NWC_SkrTanya_Kode
        ON dbo.N_WEB_CAREERS_Master_Skrining_Pertanyaan (Master_Skrining_Versi_Id, Kode);

    CREATE NONCLUSTERED INDEX IX_NWC_SkrTanya_Versi_Urutan
        ON dbo.N_WEB_CAREERS_Master_Skrining_Pertanyaan (Master_Skrining_Versi_Id, Urutan)
        INCLUDE (Tipe, Label, Seksi);
END
GO


/* ============================================================================
   B. LAPIS 2 — PENGIKATAN KE LOKER
   ============================================================================ */

/* ── B1. Program_Skrining ────────────────────────────────────────────────────
   Satu-satunya tempat yang bisa menjawab "loker ke-50 pakai template C".
   Bentuknya meniru Program_Syarat yang sudah ada, diturunkan satu tingkat
   dari program ke posisi.

   ── KENAPA BUKAN KOLOM DI Program_Posisi ───────────────────────────────────

   Karena satu alur bisa punya LEBIH DARI SATU tahap skrining — HR screening
   lalu user screening, hal biasa di rekrutmen level manajerial. Satu kolom
   tidak bisa menyatakan "template A untuk tahap 2, template B untuk tahap 5".

   ── KENAPA MENGGANTUNG DI Master_Alur_Tahap_Tes, BUKAN _Tahap ──────────────

   Karena Mode_Keputusan_Kode menyimpulkan hasil tahap dari SUB-TES-nya.
   Skrining yang bukan sub-tes harus di-special-case di dalam mesin keputusan,
   dan pengecualian semacam itu yang bikin mesin generik lama-lama berlubang.

   ── DUA INDEKS UNIK BERSARING ──────────────────────────────────────────────

   Program_Posisi_Id NULL dan NOT NULL diperlakukan berbeda karena artinya
   memang berbeda: yang NULL adalah bawaan seluruh program (boleh satu saja
   per aktivitas), yang terisi adalah penimpaan untuk satu loker. SQL Server
   memperlakukan NULL sebagai tidak sama dengan NULL di indeks unik biasa —
   karena itu keduanya ditulis sebagai indeks bersaring terpisah. */
IF OBJECT_ID('dbo.N_WEB_CAREERS_Program_Skrining', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.N_WEB_CAREERS_Program_Skrining (
        Id_Program_Skrining      INT IDENTITY(1,1) NOT NULL,
        Program_Id               INT           NOT NULL,
        /* NULL = berlaku untuk SELURUH loker di program ini. */
        Program_Posisi_Id        INT           NULL,
        Master_Alur_Tahap_Tes_Id INT           NOT NULL,
        Skrining_Kode            VARCHAR(30)   NOT NULL,
        /* NULL = ikut versi PUBLISHED terbaru saat lamaran dibuat.
           Terisi = dipaku ke versi itu, apa pun yang terjadi di master. */
        Skrining_Versi           INT           NULL,
        Flag_Aktif               CHAR(1)       NOT NULL CONSTRAINT DF_NWC_PrgSkr_Aktif DEFAULT 'Y',
        Created_At               DATETIME      NULL,
        Created_By               VARCHAR(200)  NULL,
        Created_By_Id            INT           NULL,
        Updated_At               DATETIME      NULL,
        Updated_By               VARCHAR(200)  NULL,
        Updated_By_Id            INT           NULL,

        CONSTRAINT PK_NWC_Program_Skrining PRIMARY KEY CLUSTERED (Id_Program_Skrining)
    );

    CREATE UNIQUE NONCLUSTERED INDEX UX_NWC_PrgSkr_Bawaan
        ON dbo.N_WEB_CAREERS_Program_Skrining (Program_Id, Master_Alur_Tahap_Tes_Id)
        WHERE Program_Posisi_Id IS NULL;

    CREATE UNIQUE NONCLUSTERED INDEX UX_NWC_PrgSkr_Loker
        ON dbo.N_WEB_CAREERS_Program_Skrining (Program_Posisi_Id, Master_Alur_Tahap_Tes_Id)
        WHERE Program_Posisi_Id IS NOT NULL;

    /* Kueri resolusi — dijalankan sekali per lamaran, saat aktivitasnya dibuat. */
    CREATE NONCLUSTERED INDEX IX_NWC_PrgSkr_Resolusi
        ON dbo.N_WEB_CAREERS_Program_Skrining (Program_Id, Master_Alur_Tahap_Tes_Id, Flag_Aktif)
        INCLUDE (Program_Posisi_Id, Skrining_Kode, Skrining_Versi);
END
GO


/* ============================================================================
   C. LAPIS 3 — RUNTIME & JAWABAN
   ============================================================================ */

/* ── C1. Lamaran_Skrining ────────────────────────────────────────────────────
   Kepala sesi. Persis pola Referensi_Kandidat dan Verifikasi_Latar, yang
   keduanya juga menggantung di (Lamaran_Id, Lamaran_Tahap_Id,
   Lamaran_Tahap_Tes_Id).

   ── KENAPA TABEL SENDIRI, BUKAN KOLOM DI Lamaran_Tahap ─────────────────────

   Lamaran_Tahap sudah 61 kolom, dan tiap kolom yang ditambahkan di sana wajib
   dicerminkan ke Lamaran_Tahap_Riwayat. Sesi skrining membawa audit sendiri
   (petugas, percobaan menelepon, hasil kontak, durasi) yang tidak punya rumah
   di sana dan akan berarti delapan ALTER di tabel terlebar dalam skema ini.

   ── ARSIP MEMAKAI STEMPEL, BUKAN TABEL CERMIN ──────────────────────────────

   Ulang_Id + Putaran + Arsip_At dicap langsung di baris ini; sesi aktif adalah
   yang Arsip_At IS NULL. Ini menghemat dua tabel dibanding pola _Riwayat, dan
   BUKAN penyimpangan: Lamaran_Tahap_Berkas sudah memakai cara yang sama.

   Yang memaksa Lamaran_Tahap memakai tabel cermin adalah indeks uniknya
   (Lamaran_Id, Urutan) — baris hidupnya harus dikosongkan supaya putaran
   berikutnya bisa menempatinya. Tabel ini tidak punya kendala seperti itu. */
IF OBJECT_ID('dbo.N_WEB_CAREERS_Lamaran_Skrining', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.N_WEB_CAREERS_Lamaran_Skrining (
        Id_Lamaran_Skrining   INT IDENTITY(1,1) NOT NULL,
        Lamaran_Id            INT           NOT NULL,
        Lamaran_Tahap_Id      INT           NOT NULL,
        Lamaran_Tahap_Tes_Id  INT           NULL,

        /* ── PEMBEKUAN ──────────────────────────────────────────────────────
           Kode + Versi, bukan Id. Nama_Snapshot ikut disalin supaya laporan
           lama tetap terbaca meski templatenya kelak berganti nama. */
        Skrining_Kode         VARCHAR(30)   NOT NULL,
        Skrining_Versi        INT           NOT NULL,
        Nama_Snapshot         VARCHAR(120)  NULL,

        /* DRAF = sedang diisi · SELESAI = dikunci · BATAL = tidak jadi. */
        Status                VARCHAR(20)   NOT NULL CONSTRAINT DF_NWC_LmrSkr_Status DEFAULT 'DRAF',

        /* TELEPON / VIDEO / TATAP_MUKA / CHAT.
           Sepola Referensi_Kandidat.Metode. Kolom inilah yang membuat modul
           ini tidak terkunci pada telepon — user screening dan walk-in
           screening memakai tabel yang sama. */
        Metode                VARCHAR(20)   NULL,
        Kontak_Nomor          VARCHAR(40)   NULL,
        /* Berapa kali ditelepon. Skrining telepon memang sering gagal
           tersambung, dan itu data yang dibutuhkan rekruter — bukan kegagalan
           yang perlu disembunyikan. */
        Percobaan             INT           NOT NULL CONSTRAINT DF_NWC_LmrSkr_Percobaan DEFAULT 0,
        /* TERHUBUNG / TIDAK_TERHUBUNG / DIJADWAL_ULANG / MENOLAK */
        Hasil_Kontak          VARCHAR(25)   NULL,

        Waktu_Mulai           DATETIME      NULL,
        Waktu_Selesai         DATETIME      NULL,
        Durasi_Menit          INT           NULL,

        /* Skor_Maks ikut disimpan, bukan dihitung ulang saat dibaca:
           mengubah bobot di master besok tidak boleh diam-diam mengubah arti
           skor yang sudah tercatat hari ini. */
        Skor                  DECIMAL(7,2)  NULL,
        Skor_Maks             DECIMAL(7,2)  NULL,
        Skor_Persen           DECIMAL(5,2)  NULL,

        /* LANJUT / PERTIMBANGAN / TIDAK_LANJUT — rekomendasi petugas.
           BUKAN keputusan tahap: palu tetap diketuk lewat mesin keputusan. */
        Rekomendasi           VARCHAR(20)   NULL,

        Knockout_Flag         CHAR(1)       NOT NULL CONSTRAINT DF_NWC_LmrSkr_Knockout DEFAULT 'T',
        Knockout_Kode         VARCHAR(40)   NULL,
        Knockout_Pesan        VARCHAR(500)  NULL,

        /* Ringkasan ganda: Html untuk dibaca orang (Quill), teks polos untuk
           dicari dan diekspor. Sepola Catatan / Catatan_Html di Lamaran_Tahap. */
        Ringkasan             VARCHAR(MAX)  NULL,
        Ringkasan_Html        NVARCHAR(MAX) NULL,

        Petugas               VARCHAR(150)  NULL,
        Petugas_Id            INT           NULL,
        /* Terisi saat Status jadi SELESAI. Menyunting sesudah itu menuntut
           buka kunci — dan bukanya tercatat di log. */
        Dikunci_At            DATETIME      NULL,

        /* ── STEMPEL ARSIP (lihat penjelasan di kepala tabel) ─────────────── */
        Ulang_Id              INT           NULL,
        Putaran               INT           NULL,
        Arsip_At              DATETIME      NULL,

        Created_At            DATETIME      NULL,
        Created_By            VARCHAR(200)  NULL,
        Created_By_Id         INT           NULL,
        Updated_At            DATETIME      NULL,
        Updated_By            VARCHAR(200)  NULL,
        Updated_By_Id         INT           NULL,

        CONSTRAINT PK_NWC_Lamaran_Skrining PRIMARY KEY CLUSTERED (Id_Lamaran_Skrining),
        CONSTRAINT CK_NWC_LmrSkr_Status
            CHECK (Status IN ('DRAF', 'SELESAI', 'BATAL'))
    );

    /* SATU SESI AKTIF PER AKTIVITAS.
       Ini keputusan yang sengaja ditegakkan basis data: satu rekruter per
       sesi. Kalau kelak dua rekruter harus menskrining kandidat yang sama dan
       skornya dirata-rata, indeks INI SATU-SATUNYA yang perlu dibuang —
       strukturnya sudah menampung beberapa baris per Lamaran_Tahap_Tes_Id. */
    CREATE UNIQUE NONCLUSTERED INDEX UX_NWC_LmrSkr_Aktif
        ON dbo.N_WEB_CAREERS_Lamaran_Skrining (Lamaran_Tahap_Tes_Id)
        WHERE Arsip_At IS NULL AND Lamaran_Tahap_Tes_Id IS NOT NULL;

    CREATE NONCLUSTERED INDEX IX_NWC_LmrSkr_Lamaran
        ON dbo.N_WEB_CAREERS_Lamaran_Skrining (Lamaran_Id, Arsip_At)
        INCLUDE (Lamaran_Tahap_Id, Status, Rekomendasi, Skor_Persen);

    CREATE NONCLUSTERED INDEX IX_NWC_LmrSkr_Tahap
        ON dbo.N_WEB_CAREERS_Lamaran_Skrining (Lamaran_Tahap_Id, Arsip_At)
        INCLUDE (Status, Skor, Skor_Maks);

    /* Analitik per template — "berapa % kandidat lolos template A". */
    CREATE NONCLUSTERED INDEX IX_NWC_LmrSkr_Template
        ON dbo.N_WEB_CAREERS_Lamaran_Skrining (Skrining_Kode, Skrining_Versi, Status)
        INCLUDE (Rekomendasi, Skor_Persen, Knockout_Flag);
END
GO

/* ── C2. Lamaran_Skrining_Jawaban ────────────────────────────────────────────
   Satu baris per pertanyaan — BUKAN satu blob JSON.

   Sistem ini sudah pernah menempuh jalan JSON dan harus menambalnya:
   Formulir_Jawaban_Index lahir justru karena jawaban formulir berbentuk JSON
   tidak bisa dicari. Analitik yang sudah ada (AnalitikPembukaan,
   MetrikRekrutmen) seluruhnya agregasi SQL. Langsung relasional menghindari
   tabel indeks kedua.

   ── KENAPA SNAPSHOT LAGI, PADAHAL VERSINYA SUDAH DIBEKUKAN DI C1 ───────────

   Versi menjawab "template apa". Salinan per-jawaban menjawab "apa yang
   BENAR-BENAR ditanyakan" — dan yang kedua tetap terbaca bahkan bila baris
   masternya kelak terhapus. Pola ini sudah ada di Feedback_Jawaban_Detail.

   Bobot_Snapshot ikut dibekukan karena tanpa itu, mengubah bobot di master
   akan diam-diam mengubah arti skor seluruh kandidat lama: "80" tahun lalu
   jadi tidak sebanding dengan "80" tahun ini, tanpa satu pun jejak. */
IF OBJECT_ID('dbo.N_WEB_CAREERS_Lamaran_Skrining_Jawaban', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.N_WEB_CAREERS_Lamaran_Skrining_Jawaban (
        Id_Lamaran_Skrining_Jawaban   INT IDENTITY(1,1) NOT NULL,
        Lamaran_Skrining_Id           INT           NOT NULL,
        /* Boleh NULL: kalau baris masternya kelak terhapus, jawabannya tetap
           utuh dan terbaca lewat kolom _Snapshot di bawah. */
        Master_Skrining_Pertanyaan_Id INT           NULL,
        Urutan                        INT           NOT NULL,

        /* ── SALINAN PERTANYAAN ─────────────────────────────────────────── */
        Kode_Snapshot                 VARCHAR(40)   NOT NULL,
        Label_Snapshot                VARCHAR(500)  NOT NULL,
        Tipe_Snapshot                 VARCHAR(20)   NOT NULL,
        Seksi_Snapshot                VARCHAR(120)  NULL,
        Opsi_Snapshot                 NVARCHAR(MAX) NULL,
        Skala_Min_Snapshot            INT           NULL,
        Skala_Max_Snapshot            INT           NULL,
        Bobot_Snapshot                DECIMAL(6,2)  NULL,
        Knockout_Snapshot             CHAR(1)       NOT NULL CONSTRAINT DF_NWC_SkrJwb_Knockout DEFAULT 'T',

        /* ── JAWABAN ────────────────────────────────────────────────────────
           `Jawaban` menyimpan nilai mentah (JSON untuk CHECKBOX).
           `Jawaban_Teks` menyimpan label terbacanya, supaya laporan dan ekspor
           tidak perlu menerjemahkan ulang lewat Opsi_Snapshot. */
        Jawaban                       NVARCHAR(MAX) NULL,
        Jawaban_Teks                  VARCHAR(1000) NULL,
        Nilai                         DECIMAL(7,2)  NULL,
        Catatan                       VARCHAR(1000) NULL,

        Created_At                    DATETIME      NULL,
        Created_By                    VARCHAR(200)  NULL,
        Created_By_Id                 INT           NULL,
        Updated_At                    DATETIME      NULL,
        Updated_By                    VARCHAR(200)  NULL,
        Updated_By_Id                 INT           NULL,

        CONSTRAINT PK_NWC_Lamaran_Skrining_Jawaban PRIMARY KEY CLUSTERED (Id_Lamaran_Skrining_Jawaban),
        CONSTRAINT FK_NWC_SkrJwb_Sesi
            FOREIGN KEY (Lamaran_Skrining_Id)
            REFERENCES dbo.N_WEB_CAREERS_Lamaran_Skrining (Id_Lamaran_Skrining)
            ON DELETE CASCADE
    );

    /* Satu jawaban per pertanyaan per sesi — menyimpan ulang MEMPERBARUI,
       bukan menumpuk. */
    CREATE UNIQUE NONCLUSTERED INDEX UX_NWC_SkrJwb_Pertanyaan
        ON dbo.N_WEB_CAREERS_Lamaran_Skrining_Jawaban (Lamaran_Skrining_Id, Kode_Snapshot);

    CREATE NONCLUSTERED INDEX IX_NWC_SkrJwb_Urutan
        ON dbo.N_WEB_CAREERS_Lamaran_Skrining_Jawaban (Lamaran_Skrining_Id, Urutan);

    /* Analitik lintas kandidat: "jawaban apa yang paling sering menggugurkan". */
    CREATE NONCLUSTERED INDEX IX_NWC_SkrJwb_Analitik
        ON dbo.N_WEB_CAREERS_Lamaran_Skrining_Jawaban (Kode_Snapshot)
        INCLUDE (Jawaban_Teks, Nilai, Knockout_Snapshot);
END
GO


/* ============================================================================
   D. PERUBAHAN TABEL LAMA — 4 ALTER, 6 KOLOM

   Semuanya penambahan kolom kosong (atau berdefault). Tidak ada ALTER COLUMN,
   tidak ada kolom yang dilebarkan, tidak ada NOT NULL tanpa DEFAULT.
   SQL Server mengerjakannya sebagai perubahan metadata — tanpa menulis ulang
   tabel dan tanpa backfill.
   ============================================================================ */

/* ── D1. Penanda tipe tahap ──────────────────────────────────────────────────
   Supaya "tahap ini punya kuesioner skrining" dijawab MASTER, bukan kode yang
   menghafal string 'PHONE_SCREEN'. Sepola Flag_Pemeriksaan. */
IF COL_LENGTH('dbo.N_WEB_CAREERS_Master_Tipe_Tahap', 'Flag_Skrining') IS NULL
    ALTER TABLE dbo.N_WEB_CAREERS_Master_Tipe_Tahap
        ADD Flag_Skrining CHAR(1) NOT NULL CONSTRAINT DF_NWC_Tipe_Tahap_Skrining DEFAULT 'T';
GO

/* ── D2. Bawaan alur ─────────────────────────────────────────────────────────
   Lapis terakhir rantai resolusi: alur yang belum diikat ke program mana pun
   tetap punya jawaban. */
IF COL_LENGTH('dbo.N_WEB_CAREERS_Master_Alur_Tahap_Tes', 'Skrining_Kode') IS NULL
    ALTER TABLE dbo.N_WEB_CAREERS_Master_Alur_Tahap_Tes
        ADD Skrining_Kode VARCHAR(30) NULL;
GO

/* ── D3. PEMBEKUAN ───────────────────────────────────────────────────────────
   Sepasang kolom ini KEMBAR dengan Formulir_Kode + Formulir_Versi yang sudah
   ada di Lamaran_Tahap — ditulis sekali saat aktivitas dibuat, tidak pernah
   diperbarui lagi. Mengubah pengikatan besok tidak menggeser seorang pun yang
   sudah berjalan. */
IF COL_LENGTH('dbo.N_WEB_CAREERS_Lamaran_Tahap_Tes', 'Skrining_Kode') IS NULL
    ALTER TABLE dbo.N_WEB_CAREERS_Lamaran_Tahap_Tes
        ADD Skrining_Kode VARCHAR(30) NULL, Skrining_Versi INT NULL;
GO

/* ── D4. CERMIN ARSIP — BUKAN PILIHAN ────────────────────────────────────────
   Lamaran_Tahap_Tes (78 kolom) dan _Tes_Riwayat (82) berselisih PERSIS empat
   kolom arsip: Id_..._Riwayat, Ulang_Id, Putaran, Arsip_At. Nol penyimpangan.

   Itu bukan kebetulan — UlangTahap menyalin kolom-per-kolom saat kandidat
   mengulang tahap. Menambah kolom di tabel hidup tanpa mencerminkannya ke
   tabel arsip akan mematahkan pengulangan tahap, dan gagalnya baru terasa
   berbulan-bulan kemudian saat ada kandidat pertama yang diulang. */
IF COL_LENGTH('dbo.N_WEB_CAREERS_Lamaran_Tahap_Tes_Riwayat', 'Skrining_Kode') IS NULL
    ALTER TABLE dbo.N_WEB_CAREERS_Lamaran_Tahap_Tes_Riwayat
        ADD Skrining_Kode VARCHAR(30) NULL, Skrining_Versi INT NULL;
GO


/* ============================================================================
   E. PENANDA PHONE_SCREEN

   Tipe tahapnya SUDAH ADA sejak lama — 'Phone / Recruiter Screening',
   Flag_Sistem = 'Y', Flag_Aktif = 'Y' — dan sampai hari ini nol rujukan di
   kode. Yang belum ada cuma badan kuesionernya. Di sini ia dinyalakan.
   ============================================================================ */

UPDATE dbo.N_WEB_CAREERS_Master_Tipe_Tahap
   SET Flag_Skrining = 'Y',
       Updated_At = GETDATE(),
       Updated_By = 'SISTEM(phone-screening)'
 WHERE Kode = 'PHONE_SCREEN'
   AND ISNULL(Flag_Skrining, 'T') <> 'Y';
GO


/* ============================================================================
   F. MENU & HAK AKSES

   Halaman baru /master-skrining, berizin masterSkriningPage.
   Aksesnya diturunkan dari masterFormulirPage — orang yang sudah dipercaya
   merawat formulir adalah orang yang sama yang merawat kuesioner skrining.
   ============================================================================ */

/* ── F1. Baris menu ──────────────────────────────────────────────────────── */
IF NOT EXISTS (SELECT 1 FROM dbo.N_WEB_CAREERS_Menu WHERE Jenis_Page = 'masterSkriningPage')
    INSERT INTO dbo.N_WEB_CAREERS_Menu
        (Jenis_Page, Nama_Menu, Nama_Header, Sub_Header, Icon_Menu, Url_Menu,
         Untuk_Role, Urutan, Flag_Maintenance, Flag_Aktif,
         Created_At, Created_By, Updated_At, Updated_By)
    VALUES
        ('masterSkriningPage', 'Master Phone Screening', 'Master Data',
         'Template pertanyaan skrining', 'bi bi-telephone-inbound', '/master-skrining',
         'ADMIN', 26, 'T', 'Y',
         GETDATE(), 'SISTEM(phone-screening)', GETDATE(), 'SISTEM(phone-screening)');
GO

/* ── F2. Hak halaman, diturunkan dari Master Formulir ────────────────────── */
INSERT INTO dbo.N_WEB_CAREERS_Page_Access
    (Id_Users, Jenis_Page, Urutan_Menu, Created_At, Created_By, Updated_At, Updated_By)
SELECT DISTINCT pa.Id_Users, 'masterSkriningPage', 26,
       GETDATE(), 'SISTEM(phone-screening)', GETDATE(), 'SISTEM(phone-screening)'
  FROM dbo.N_WEB_CAREERS_Page_Access AS pa
 WHERE pa.Jenis_Page = 'masterFormulirPage'
   AND NOT EXISTS (
       SELECT 1 FROM dbo.N_WEB_CAREERS_Page_Access AS x
        WHERE x.Id_Users = pa.Id_Users AND x.Jenis_Page = 'masterSkriningPage');
GO

/* ── F3. Aksi yang diizinkan ─────────────────────────────────────────────────
   VIEW / CREATE / EDIT / DELETE saja. APPROVE, PRINT, EXPORT, ULANG,
   LINTAS_PIC, dan SERAH_TERIMA tidak punya arti di halaman master template —
   memberikannya hanya menambah baris izin yang tidak pernah dibaca. */
INSERT INTO dbo.N_WEB_CAREERS_Role_Menu_Access
    (Id_Page_Access, Id_Aksi, Flag_Diizinkan, Created_At, Created_By, Updated_At, Updated_By)
SELECT pa.Id_Page_Access, a.Id_Aksi, 'Y',
       GETDATE(), 'SISTEM(phone-screening)', GETDATE(), 'SISTEM(phone-screening)'
  FROM dbo.N_WEB_CAREERS_Page_Access AS pa
 CROSS JOIN dbo.N_WEB_CAREERS_Aksi AS a
 WHERE pa.Jenis_Page = 'masterSkriningPage'
   AND a.Flag_Aktif = 'Y'
   AND a.Nama_Aksi IN ('VIEW', 'CREATE', 'EDIT', 'DELETE')
   AND NOT EXISTS (
       SELECT 1 FROM dbo.N_WEB_CAREERS_Role_Menu_Access AS r
        WHERE r.Id_Page_Access = pa.Id_Page_Access AND r.Id_Aksi = a.Id_Aksi);
GO


/* ============================================================================
   G. VERIFIKASI

   Jalankan setelah blok di atas selesai. Semua baris harus berbunyi OK.
   ============================================================================ */

SELECT 'A. Tabel master'   AS Blok,
       CASE WHEN OBJECT_ID('dbo.N_WEB_CAREERS_Master_Skrining','U') IS NOT NULL
             AND OBJECT_ID('dbo.N_WEB_CAREERS_Master_Skrining_Versi','U') IS NOT NULL
             AND OBJECT_ID('dbo.N_WEB_CAREERS_Master_Skrining_Pertanyaan','U') IS NOT NULL
            THEN 'OK — 3 tabel' ELSE 'GAGAL' END AS Hasil
UNION ALL
SELECT 'B. Pengikatan',
       CASE WHEN OBJECT_ID('dbo.N_WEB_CAREERS_Program_Skrining','U') IS NOT NULL
            THEN 'OK — 1 tabel' ELSE 'GAGAL' END
UNION ALL
SELECT 'C. Runtime',
       CASE WHEN OBJECT_ID('dbo.N_WEB_CAREERS_Lamaran_Skrining','U') IS NOT NULL
             AND OBJECT_ID('dbo.N_WEB_CAREERS_Lamaran_Skrining_Jawaban','U') IS NOT NULL
            THEN 'OK — 2 tabel' ELSE 'GAGAL' END
UNION ALL
SELECT 'D. Kolom baru',
       CASE WHEN COL_LENGTH('dbo.N_WEB_CAREERS_Master_Tipe_Tahap','Flag_Skrining') IS NOT NULL
             AND COL_LENGTH('dbo.N_WEB_CAREERS_Master_Alur_Tahap_Tes','Skrining_Kode') IS NOT NULL
             AND COL_LENGTH('dbo.N_WEB_CAREERS_Lamaran_Tahap_Tes','Skrining_Kode') IS NOT NULL
             AND COL_LENGTH('dbo.N_WEB_CAREERS_Lamaran_Tahap_Tes','Skrining_Versi') IS NOT NULL
            THEN 'OK — 4 kolom' ELSE 'GAGAL' END
UNION ALL
/* Pemeriksaan paling penting di berkas ini. Selisih kolom antara tabel hidup
   dan tabel arsipnya HARUS tepat empat — kalau bukan, pengulangan tahap
   sudah patah dan belum ada yang menyadarinya. */
SELECT 'D4. Cermin arsip',
       CASE WHEN (
           (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_NAME = 'N_WEB_CAREERS_Lamaran_Tahap_Tes_Riwayat')
         - (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_NAME = 'N_WEB_CAREERS_Lamaran_Tahap_Tes')
       ) = 4 THEN 'OK — selisih tepat 4 kolom arsip'
         ELSE 'GAGAL — cermin arsip menyimpang, JANGAN lanjut' END
UNION ALL
SELECT 'E. PHONE_SCREEN',
       CASE WHEN EXISTS (SELECT 1 FROM dbo.N_WEB_CAREERS_Master_Tipe_Tahap
                          WHERE Kode = 'PHONE_SCREEN' AND Flag_Skrining = 'Y')
            THEN 'OK — penanda menyala' ELSE 'GAGAL' END
UNION ALL
SELECT 'F. Menu & akses',
       CASE WHEN EXISTS (SELECT 1 FROM dbo.N_WEB_CAREERS_Menu WHERE Jenis_Page = 'masterSkriningPage')
            THEN 'OK — ' + CAST((SELECT COUNT(*) FROM dbo.N_WEB_CAREERS_Page_Access
                                  WHERE Jenis_Page = 'masterSkriningPage') AS VARCHAR(10))
                 + ' akun diberi akses'
            ELSE 'GAGAL' END;
GO
