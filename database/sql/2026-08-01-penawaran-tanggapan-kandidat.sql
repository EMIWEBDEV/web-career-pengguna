-- ============================================================
-- EVO-Career — TANGGAPAN KANDIDAT ATAS PENAWARAN
-- Branch: bearbrand
-- Date: 2026-08-01
-- ============================================================
-- NOTE: Jalankan di SQL Server Management Studio pada database
-- Web_HRIS. Script ini AMAN diulang (idempoten).
--
-- LATAR:
-- Tahap penawaran berakhir pada satu hal yang TIDAK diketahui
-- perusahaan sampai kandidat menjawab: ia menerima atau mundur.
-- Sebelumnya pipeline hanya punya keputusan dari sisi admin,
-- sehingga kandidat yang menggantung tidak bisa dibedakan dari
-- kandidat yang sedang dipertimbangkan — keduanya terlihat
-- "berjalan" selamanya.
--
-- Master keputusan sendiri SUDAH memuat DITOLAK_KANDIDAT dan
-- MENGUNDURKAN_DIRI (Flag_Oleh_Kandidat='Y') sejak awal; yang
-- belum ada hanya penanda tipe tahap mana yang membawa
-- penawaran, dan tempat menyimpan jawaban kandidatnya.
-- ============================================================

-- ============================================================
-- 1. PENANDA TIPE TAHAP YANG MEMBAWA PENAWARAN
-- ------------------------------------------------------------
-- Dibaca dari master, BUKAN daftar kode di dalam kode program:
-- perusahaan yang memakai tahap penawaran bernama lain cukup
-- menyalakan flag ini tanpa perlu deploy.
-- ============================================================
IF NOT EXISTS (
    SELECT 1 FROM sys.columns
    WHERE object_id = OBJECT_ID('N_WEB_CAREERS_Master_Tipe_Tahap')
      AND name = 'Flag_Penawaran'
)
BEGIN
    ALTER TABLE N_WEB_CAREERS_Master_Tipe_Tahap
        ADD Flag_Penawaran CHAR(1) NOT NULL CONSTRAINT DF_MTT_Flag_Penawaran DEFAULT 'T';
END
GO

-- Tahap yang memang menuntut jawaban kandidat.
UPDATE N_WEB_CAREERS_Master_Tipe_Tahap
   SET Flag_Penawaran = 'Y'
 WHERE Kode IN ('OFFERING', 'NEGOTIATION', 'CONTRACT_SIGNING')
   AND Flag_Penawaran <> 'Y';
GO

-- ============================================================
-- 2. JAWABAN KANDIDAT PADA TAHAP PENAWARAN
-- ------------------------------------------------------------
-- Disimpan di tahapnya, bukan di lamaran: satu lamaran bisa
-- melewati beberapa tahap berpenawaran (negosiasi lalu kontrak),
-- dan jawaban pada masing-masing perlu terbaca terpisah.
--
-- Jawaban kandidat TIDAK memutus tahap dengan sendirinya —
-- palu tetap di tangan admin. Kolom ini merekam apa yang
-- dikatakan kandidat beserta waktunya, sehingga "belum
-- menjawab" bisa dibedakan dari "menjawab dan sedang diproses".
-- ============================================================
IF NOT EXISTS (
    SELECT 1 FROM sys.columns
    WHERE object_id = OBJECT_ID('N_WEB_CAREERS_Lamaran_Tahap')
      AND name = 'Tanggapan_Kandidat'
)
BEGIN
    ALTER TABLE N_WEB_CAREERS_Lamaran_Tahap
        ADD Tanggapan_Kandidat  VARCHAR(20)  NULL,  -- TERIMA / MUNDUR
            Tanggapan_Catatan   VARCHAR(500) NULL,
            Tanggapan_At        DATETIME     NULL;
END
GO
