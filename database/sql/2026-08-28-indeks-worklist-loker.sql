/* ═══════════════════════════════════════════════════════════════════════════
   WEB CAREERS — INDEKS UNTUK WORKLIST & PENJADWALAN BERBASIS LOWONGAN
   Dijalankan: 28 Agustus 2026
   Aman diulang: setiap pembuatan dijaga pemeriksaan keberadaan.

   ── KENAPA ────────────────────────────────────────────────────────────────
   Sejak worklist (/karir/pelamar) dan Penjadwalan bisa disusun PER LOWONGAN,
   kueri pembukanya menyaring lewat `Lamaran.Program_Posisi_Id`:

       WHERE l.Program_Posisi_Id IN (...) AND l.Status = 'BERJALAN'

   Tabel N_WEB_CAREERS_Lamaran tidak punya satu pun indeks yang dipimpin kolom
   itu. Yang ada IX_..._Program (Program_Id, Status) dan IX_..._Alur
   (Program_Id, Master_Alur_Id) — keduanya dipimpin Program_Id, jadi tidak bisa
   dipakai untuk saringan per lowongan. Akibatnya SQL Server memindai seluruh
   tabel lamaran setiap kali seorang rekruter membuka satu lowongan.

   Selama tabelnya masih ribuan baris hal itu tidak terasa. Ia terasa persis
   pada saat paling merugikan: ketika pelamar menumpuk, semua rekruter membuka
   papan yang sama, dan pemindaian itu terjadi berkali-kali per menit.

   Kolom Status ikut disertakan (bukan sekadar Program_Posisi_Id) karena kedua
   pemanggil selalu memasangkannya, dan indeks komposit menghemat satu
   penyaringan baris tambahan.
   ═════════════════════════════════════════════════════════════════════════ */

SET NOCOUNT ON;

/* 1. Worklist & Penjadwalan: pelamar SATU lowongan. */
IF NOT EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE name = 'IX_N_WEB_CAREERS_Lamaran_Posisi'
      AND object_id = OBJECT_ID('dbo.N_WEB_CAREERS_Lamaran')
)
BEGIN
    CREATE NONCLUSTERED INDEX IX_N_WEB_CAREERS_Lamaran_Posisi
        ON dbo.N_WEB_CAREERS_Lamaran (Program_Posisi_Id, Status)
        INCLUDE (Id_Users, Master_Alur_Id, Urutan_Tahap, Total_Tahap);

    PRINT 'IX_N_WEB_CAREERS_Lamaran_Posisi dibuat.';
END
ELSE
    PRINT 'IX_N_WEB_CAREERS_Lamaran_Posisi sudah ada — dilewati.';

/* 2. Papan worklist mengambil SELURUH aktivitas milik tahap yang tampil.
      Indeks yang ada dipimpin (Lamaran_Tahap_Id, Unggah_Kirim_At) — sudah
      benar sebagai kolom pemimpin, tapi tidak memuat kolom yang dibaca papan,
      jadi tiap baris masih perlu lookup ke tabel. Ditambah indeks penutup
      untuk jalur baca yang paling ramai. */
IF NOT EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE name = 'IX_N_WEB_CAREERS_Lamaran_Tahap_Tes_Tahap'
      AND object_id = OBJECT_ID('dbo.N_WEB_CAREERS_Lamaran_Tahap_Tes')
)
BEGIN
    CREATE NONCLUSTERED INDEX IX_N_WEB_CAREERS_Lamaran_Tahap_Tes_Tahap
        ON dbo.N_WEB_CAREERS_Lamaran_Tahap_Tes (Lamaran_Tahap_Id)
        INCLUDE (Urutan, Label, Tipe_Tahap_Kode, Provider, Status, Hasil,
                 Flag_Selesai, Penjadwalan_Tahap_Id, Jadwal_Mulai);

    PRINT 'IX_N_WEB_CAREERS_Lamaran_Tahap_Tes_Tahap dibuat.';
END
ELSE
    PRINT 'IX_N_WEB_CAREERS_Lamaran_Tahap_Tes_Tahap sudah ada — dilewati.';

/* ── PERIKSA ───────────────────────────────────────────────────────────── */
SELECT  OBJECT_NAME(i.object_id) AS Tabel,
        i.name                   AS Indeks,
        i.type_desc              AS Jenis
FROM    sys.indexes i
WHERE   i.name IN ('IX_N_WEB_CAREERS_Lamaran_Posisi',
                   'IX_N_WEB_CAREERS_Lamaran_Tahap_Tes_Tahap')
ORDER BY Tabel, Indeks;
