-- ============================================================
-- EVO-Career — FEEDBACK FEATURE DDL
-- Branch: feat/feedback
-- Date: 2026-07-25
-- ============================================================
-- NOTE: Jalankan script ini di SQL Server Management Studio
-- pada database Web_HRIS. Pastikan foreign key target tables
-- (N_WEB_CAREERS_Lamaran, N_WEB_CAREERS_Program) sudah ada.
-- ============================================================

-- ============================================================
-- 1. MASTER FEEDBACK FORM
-- ============================================================
CREATE TABLE N_WEB_CAREERS_Master_Feedback_Form (
    Id_Master_Feedback_Form  INT IDENTITY(1,1) NOT NULL,
    Nama                     VARCHAR(100) NOT NULL,
    Deskripsi                VARCHAR(500) NULL,
    Durasi_Hari              INT NULL,                     -- NULL = unlimited
    Mode_Tampilan            VARCHAR(10) DEFAULT 'SCROLL', -- WIZARD / SCROLL
    Flag_Aktif               CHAR(1) DEFAULT 'T',         -- Y=aktif, T=tidak
    Created_At               DATETIME,
    Created_By               VARCHAR(100),
    Created_By_Id            INT NULL,
    Updated_At               DATETIME,
    Updated_By               VARCHAR(100),
    Updated_By_Id            INT NULL,
    Flag_Cancellation        CHAR(1) DEFAULT 'T',         -- Y=dibatalkan, T=tidak
    Cancelled_At             DATETIME NULL,
    Cancelled_By             VARCHAR(100) NULL,

    CONSTRAINT PK_Master_Feedback_Form PRIMARY KEY CLUSTERED (Id_Master_Feedback_Form)
);

CREATE NONCLUSTERED INDEX IX_Master_Feedback_Form_Aktif
    ON N_WEB_CAREERS_Master_Feedback_Form (Flag_Aktif, Flag_Cancellation)
    INCLUDE (Nama, Durasi_Hari);

GO

-- ============================================================
-- 2. MASTER FEEDBACK PERTANYAAN
-- ============================================================
CREATE TABLE N_WEB_CAREERS_Master_Feedback_Pertanyaan (
    Id_Master_Feedback_Pertanyaan INT IDENTITY(1,1) NOT NULL,
    Master_Feedback_Form_Id       INT NOT NULL,
    Urutan                        INT NOT NULL,
    Tipe                          VARCHAR(20) NOT NULL,    -- RATING/NPS/LIKERT/TEXTAREA/RADIO/CHECKBOX/DROPDOWN
    Label                         VARCHAR(500) NOT NULL,
    Opsi                          NVARCHAR(MAX) NULL,      -- JSON: ["opsi1","opsi2"]
    Skala_Min                     INT NULL,
    Skala_Max                     INT NULL,
    Created_At                    DATETIME,
    Created_By                    VARCHAR(100),
    Created_By_Id                 INT NULL,
    Updated_At                    DATETIME,
    Updated_By                    VARCHAR(100),
    Updated_By_Id                 INT NULL,
    Flag_Cancellation             CHAR(1) DEFAULT 'T',
    Cancelled_At                  DATETIME NULL,
    Cancelled_By                  VARCHAR(100) NULL,

    CONSTRAINT PK_Master_Feedback_Pertanyaan PRIMARY KEY CLUSTERED (Id_Master_Feedback_Pertanyaan),
    CONSTRAINT FK_FeedbackPertanyaan_Form
        FOREIGN KEY (Master_Feedback_Form_Id)
        REFERENCES N_WEB_CAREERS_Master_Feedback_Form (Id_Master_Feedback_Form)
);

-- Index: ambil semua pertanyaan per form, urut
CREATE NONCLUSTERED INDEX IX_FeedbackPertanyaan_Form_Urutan
    ON N_WEB_CAREERS_Master_Feedback_Pertanyaan (Master_Feedback_Form_Id, Urutan, Flag_Cancellation)
    INCLUDE (Tipe, Label);

-- Index: untuk query pertanyaan aktif saja
CREATE NONCLUSTERED INDEX IX_FeedbackPertanyaan_Form_Aktif
    ON N_WEB_CAREERS_Master_Feedback_Pertanyaan (Master_Feedback_Form_Id, Flag_Cancellation)
    WHERE Flag_Cancellation = 'T';

GO

-- ============================================================
-- 3. FEEDBACK ASSIGNMENT (penugasan form ke program)
-- ============================================================
CREATE TABLE N_WEB_CAREERS_Feedback_Assignment (
    Id_Feedback_Assignment  INT IDENTITY(1,1) NOT NULL,
    Master_Feedback_Form_Id INT NOT NULL,
    Program_Id              INT NULL,                    -- NULL = general (semua program)
    Flag_General            CHAR(1) DEFAULT 'T',         -- Y=semua program, T=spesifik
    Flag_Aktif              CHAR(1) DEFAULT 'T',
    Created_At              DATETIME,
    Created_By              VARCHAR(100),
    Created_By_Id           INT NULL,
    Updated_At              DATETIME,
    Updated_By              VARCHAR(100),
    Updated_By_Id           INT NULL,
    Flag_Cancellation       CHAR(1) DEFAULT 'T',
    Cancelled_At            DATETIME NULL,
    Cancelled_By            VARCHAR(100) NULL,

    CONSTRAINT PK_Feedback_Assignment PRIMARY KEY CLUSTERED (Id_Feedback_Assignment),
    CONSTRAINT FK_FeedbackAssignment_Form
        FOREIGN KEY (Master_Feedback_Form_Id)
        REFERENCES N_WEB_CAREERS_Master_Feedback_Form (Id_Master_Feedback_Form)
);

-- Index: resolusi form per program (specific overrides general) — query paling kritis
CREATE NONCLUSTERED INDEX IX_FeedbackAssignment_Resolve
    ON N_WEB_CAREERS_Feedback_Assignment (Program_Id, Flag_Aktif, Flag_Cancellation)
    INCLUDE (Master_Feedback_Form_Id, Flag_General);

-- Index: cek general assignment aktif
CREATE NONCLUSTERED INDEX IX_FeedbackAssignment_General
    ON N_WEB_CAREERS_Feedback_Assignment (Flag_General, Flag_Aktif, Flag_Cancellation)
    INCLUDE (Master_Feedback_Form_Id)
    WHERE Flag_General = 'Y' AND Flag_Aktif = 'Y' AND Flag_Cancellation = 'T';

-- Unique: hanya 1 assignment general yang aktif
CREATE UNIQUE NONCLUSTERED INDEX UX_FeedbackAssignment_General_Active
    ON N_WEB_CAREERS_Feedback_Assignment (Flag_General)
    WHERE Flag_General = 'Y' AND Flag_Aktif = 'Y' AND Flag_Cancellation = 'T';

-- Unique: hanya 1 assignment aktif per program spesifik
CREATE UNIQUE NONCLUSTERED INDEX UX_FeedbackAssignment_Program_Active
    ON N_WEB_CAREERS_Feedback_Assignment (Program_Id)
    WHERE Program_Id IS NOT NULL AND Flag_Aktif = 'Y' AND Flag_Cancellation = 'T';

GO

-- ============================================================
-- 4. FEEDBACK JAWABAN (header respon)
-- ============================================================
CREATE TABLE N_WEB_CAREERS_Feedback_Jawaban (
    Id_Feedback_Jawaban     INT IDENTITY(1,1) NOT NULL,
    Lamaran_Id              INT NOT NULL,
    Master_Feedback_Form_Id INT NOT NULL,
    Email_Token             VARCHAR(100) NOT NULL,           -- email kandidat untuk verifikasi token
    Token_Hash              VARCHAR(255) NULL,               -- token untuk direct link (tanpa login)
    Status_Pengisian        VARCHAR(20) DEFAULT 'MENUNGGU',  -- MENUNGGU / TERISI
    Submitted_At            DATETIME NULL,
    Created_At              DATETIME,
    Updated_At              DATETIME,
    Flag_Cancellation       CHAR(1) DEFAULT 'T',
    Cancelled_At            DATETIME NULL,
    Cancelled_By            VARCHAR(100) NULL,

    CONSTRAINT PK_Feedback_Jawaban PRIMARY KEY CLUSTERED (Id_Feedback_Jawaban),
    CONSTRAINT FK_FeedbackJawaban_Lamaran
        FOREIGN KEY (Lamaran_Id)
        REFERENCES N_WEB_CAREERS_Lamaran (Id_Lamaran),
    CONSTRAINT FK_FeedbackJawaban_Form
        FOREIGN KEY (Master_Feedback_Form_Id)
        REFERENCES N_WEB_CAREERS_Master_Feedback_Form (Id_Master_Feedback_Form)
);

-- Index: 1:1 lookup per lamaran (paling sering dipakai)
CREATE UNIQUE NONCLUSTERED INDEX UX_FeedbackJawaban_Lamaran
    ON N_WEB_CAREERS_Feedback_Jawaban (Lamaran_Id)
    WHERE Flag_Cancellation = 'T';

-- Index: lookup by token (untuk validasi direct link)
CREATE NONCLUSTERED INDEX IX_FeedbackJawaban_Token
    ON N_WEB_CAREERS_Feedback_Jawaban (Token_Hash)
    INCLUDE (Lamaran_Id, Status_Pengisian, Master_Feedback_Form_Id)
    WHERE Token_Hash IS NOT NULL AND Flag_Cancellation = 'T';

-- Index: filter status pengisian + form (untuk admin dashboard aggregate)
CREATE NONCLUSTERED INDEX IX_FeedbackJawaban_Form_Status
    ON N_WEB_CAREERS_Feedback_Jawaban (Master_Feedback_Form_Id, Status_Pengisian, Flag_Cancellation)
    INCLUDE (Lamaran_Id, Submitted_At, Created_At);

GO

-- ============================================================
-- 5. FEEDBACK JAWABAN DETAIL (jawaban per pertanyaan)
-- ============================================================
CREATE TABLE N_WEB_CAREERS_Feedback_Jawaban_Detail (
    Id_Feedback_Jawaban_Detail       INT IDENTITY(1,1) NOT NULL,
    Feedback_Jawaban_Id              INT NOT NULL,
    Master_Feedback_Pertanyaan_Id    INT NOT NULL,
    Jawaban                          NVARCHAR(MAX) NULL,
    Created_At                       DATETIME,

    CONSTRAINT PK_Feedback_Jawaban_Detail PRIMARY KEY CLUSTERED (Id_Feedback_Jawaban_Detail),
    CONSTRAINT FK_FeedbackDetail_Jawaban
        FOREIGN KEY (Feedback_Jawaban_Id)
        REFERENCES N_WEB_CAREERS_Feedback_Jawaban (Id_Feedback_Jawaban),
    CONSTRAINT FK_FeedbackDetail_Pertanyaan
        FOREIGN KEY (Master_Feedback_Pertanyaan_Id)
        REFERENCES N_WEB_CAREERS_Master_Feedback_Pertanyaan (Id_Master_Feedback_Pertanyaan)
);

-- Index: ambil semua detail untuk 1 jawaban (join ke pertanyaan)
CREATE NONCLUSTERED INDEX IX_FeedbackDetail_Jawaban
    ON N_WEB_CAREERS_Feedback_Jawaban_Detail (Feedback_Jawaban_Id, Master_Feedback_Pertanyaan_Id)
    INCLUDE (Jawaban);

-- Index: untuk aggregate per pertanyaan (dashboard analitik)
CREATE NONCLUSTERED INDEX IX_FeedbackDetail_Pertanyaan
    ON N_WEB_CAREERS_Feedback_Jawaban_Detail (Master_Feedback_Pertanyaan_Id)
    INCLUDE (Jawaban, Feedback_Jawaban_Id);

GO

-- ============================================================
-- 6. EXPORT LOG (tracking export async)
-- ============================================================
CREATE TABLE N_WEB_CAREERS_Export_Log (
    Id_Export           INT IDENTITY(1,1) NOT NULL,
    Export_Type         VARCHAR(50) NOT NULL,            -- 'FEEDBACK'
    Id_Users            INT NOT NULL,
    Keterangan          VARCHAR(200) NULL,               -- label di UI
    Filters_Json        NVARCHAR(MAX) NULL,
    Status_Export       VARCHAR(20) DEFAULT 'DIPROSES',  -- DIPROSES / SELESAI / GAGAL
    Progress_Chunk      INT DEFAULT 0,
    Progress_Total      INT DEFAULT 0,
    File_Url            VARCHAR(500) NULL,               -- GCS signed URL
    File_Path           VARCHAR(500) NULL,               -- GCS object path (untuk hapus)
    Error_Message       VARCHAR(500) NULL,
    Created_At          DATETIME,
    Completed_At        DATETIME NULL,
    Flag_Cancellation   CHAR(1) DEFAULT 'T',
    Cancelled_At        DATETIME NULL,
    Cancelled_By        VARCHAR(100) NULL,

    CONSTRAINT PK_Export_Log PRIMARY KEY CLUSTERED (Id_Export)
);

-- Index: polling notifikasi per user
CREATE NONCLUSTERED INDEX IX_ExportLog_User_Status
    ON N_WEB_CAREERS_Export_Log (Id_Users, Status_Export, Flag_Cancellation)
    INCLUDE (Export_Type, Keterangan, Progress_Chunk, Progress_Total, File_Url, Created_At);

GO

-- ============================================================
-- 7. ALTER: Snapshot kolom — data integrity jawaban historis
-- ============================================================
ALTER TABLE N_WEB_CAREERS_Feedback_Jawaban_Detail
ADD Label_Snapshot     VARCHAR(500) NULL,
    Tipe_Snapshot      VARCHAR(20)  NULL,
    Opsi_Snapshot      NVARCHAR(MAX) NULL,
    Skala_Min_Snapshot INT NULL,
    Skala_Max_Snapshot INT NULL;

GO

-- ============================================================
-- 8. ALTER: Label ujung skala kustom (NPS & Likert)
-- ============================================================
ALTER TABLE N_WEB_CAREERS_Master_Feedback_Pertanyaan
ADD Label_Min VARCHAR(100) NULL,
    Label_Max VARCHAR(100) NULL;

GO

-- ============================================================
-- 9. ALTER: Drop redundant Created_By_Id / Updated_By_Id
-- ============================================================
-- Created_By / Updated_By / Cancelled_By sekarang menyimpan Id_Users.
-- JOIN ke N_WEB_CAREERS_Users untuk mendapatkan nama saat display.
ALTER TABLE N_WEB_CAREERS_Master_Feedback_Form
DROP COLUMN Created_By_Id, Updated_By_Id;

ALTER TABLE N_WEB_CAREERS_Master_Feedback_Pertanyaan
DROP COLUMN Created_By_Id, Updated_By_Id;

ALTER TABLE N_WEB_CAREERS_Feedback_Assignment
DROP COLUMN Created_By_Id, Updated_By_Id;

GO
