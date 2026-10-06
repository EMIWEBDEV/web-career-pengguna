/* ============================================================================
   WEB CAREERS — IDENTITAS AKTIVITAS PADA PENJADWALAN   ***OPSIONAL***
   ----------------------------------------------------------------------------
   ══ SKRIP INI TIDAK WAJIB DIJALANKAN ══════════════════════════════════════

   Perbaikan "Aktivitas 'FGD' bukan ujian online" SUDAH SELESAI di kode, tanpa
   satu pun perubahan skema. Seluruh pencocokan yang menyebabkan bug itu memakai
   kolom yang SUDAH ADA sejak dulu:

     N_WEB_CAREERS_Lamaran_Tahap_Tes.Master_Alur_Tahap_Tes_Id  (snapshot kandidat)
     N_WEB_CAREERS_Master_Alur_Tahap_Tes.Id_Master_Alur_Tahap_Tes  (master)
     N_WEB_CAREERS_Penjadwalan_Tahap.Master_Alur_Tahap_Id + .Tes_Urutan

   Tanpa kolom ini, PenjadwalanController::store() dan
   WcPenjadwalanJob::pasangTautanKandidat() SAMA-SAMA memakai Tes_Urutan untuk
   menandai snapshot kandidat. Sepasang, jadi tetap benar.

   LALU UNTUK APA KOLOM INI?
   Nomor urut itu diambil dari layar, dan layar disusun dari data kandidat —
   benar pada detik jadwal dibuat. Kolom ini MEMBEKUKAN identitas aktivitasnya
   di detik yang sama, sehingga jalur pemulihan job (yang bisa berjalan
   berjam-jam kemudian, setelah Master Alur mungkin disunting lagi) tidak perlu
   bersandar pada nomor yang artinya bisa sudah bergeser.

   Jalankan kapan pun Anda punya kesempatan mengubah skema. Sampai saat itu,
   penjadwalan berjalan normal tanpanya — kode di kedua sisi memeriksa
   keberadaan kolom ini lebih dulu (Schema::hasColumn) dan tidak pernah gagal
   karena ketiadaannya.
   ══════════════════════════════════════════════════════════════════════════

   Menambah satu kolom di N_WEB_CAREERS_Penjadwalan_Tahap:

     Master_Alur_Tahap_Tes_Id   aktivitas (sub-tes) mana di dalam tahap itu
                                yang dijadwalkan — sebagai IDENTITAS, bukan
                                nomor urut.

   Kolom ini mendampingi Tes_Urutan yang sudah ada; Tes_Urutan TIDAK dihapus dan
   tetap diisi. Skrip ini murni penambahan kolom + satu backfill yang hanya
   mengisi baris yang nilainya masih NULL.

   KENAPA MASALAHNYA ADA
   ---------------------
   Sampai sekarang "aktivitas ke berapa" hanya disimpan sebagai NOMOR URUT.
   Nomor itu dibaca dari master alur saat jadwal dibuat, lalu dicocokkan ke
   snapshot tahap milik kandidat (N_WEB_CAREERS_Lamaran_Tahap_Tes.Urutan) yang
   DIBEKUKAN saat ia melamar. Selama urutan aktivitas di Master Alur tak pernah
   disunting, keduanya memang sama. Begitu disunting — menyisipkan FGD sebelum
   Psikotes, misalnya — keduanya berselisih:

       Master "FGD dan Wawancara HR" hari ini : #1 FGD, #2 Psikotes (Tahap 2)
       Snapshot kandidat saat melamar         : #1 Psikotes (Tahap 2), #2 FGD

   Akibatnya berlapis, dan hanya yang pertama yang bersuara:

     1. Kandidat yang menunggu Psikotes tidak ketemu barisnya di master, lalu
        jatuh ke baris "alur lama" di layar Penjadwalan — padahal tahapnya
        jelas masih ada.
     2. Mengirim baris itu membuat server mengambil master #1 (FGD, tes manual)
        dan menolak: "Aktivitas 'FGD' bukan ujian online".
     3. YANG PALING BERBAHAYA — bila aktivitas bernomor sama itu kebetulan
        sama-sama ujian online, tidak ada penolakan sama sekali. Token terbit,
        tetapi tertaut ke aktivitas yang salah di snapshot kandidat. Tidak ada
        galat, tidak ada log; yang tersisa hanya nilai yang masuk ke kolom yang
        bukan miliknya.

   Id master tidak pernah berubah walau alurnya diurut ulang, jadi ia satu-
   satunya pegangan yang tetap benar. Sisi kandidat sudah menyimpannya sejak
   awal (Lamaran_Tahap_Tes.Master_Alur_Tahap_Tes_Id, diisi oleh
   LamaranService::snapshotSubTes); yang belum menyimpannya justru sisi jadwal.

   BARIS LAMA TETAP JALAN
   ----------------------
   Kolom NULL untuk penjadwalan yang dibuat sebelum skrip ini, dan untuk
   aktivitas pra-mesin yang memang tak punya baris master. Kode di kedua sisi
   (PenjadwalanController::store dan WcPenjadwalanJob::pasangTautanKandidat)
   jatuh kembali ke Tes_Urutan bila kolom ini kosong — perilakunya persis
   seperti sebelum skrip ini dijalankan.

   Aman dijalankan berkali-kali (idempoten).
   Jalankan di basis data: Web_HRIS
   ============================================================================ */

SET NOCOUNT ON;
GO

/* ── 1. KOLOM ─────────────────────────────────────────────────────────────── */
IF COL_LENGTH('dbo.N_WEB_CAREERS_Penjadwalan_Tahap', 'Master_Alur_Tahap_Tes_Id') IS NULL
BEGIN
    ALTER TABLE dbo.N_WEB_CAREERS_Penjadwalan_Tahap
        ADD Master_Alur_Tahap_Tes_Id INT NULL;

    PRINT 'Kolom Master_Alur_Tahap_Tes_Id ditambahkan.';
END
ELSE
    PRINT 'Kolom Master_Alur_Tahap_Tes_Id sudah ada — dilewati.';
GO

/* ── 2. BACKFILL ──────────────────────────────────────────────────────────────
   Hanya baris yang (a) masih NULL, (b) punya Tes_Urutan, dan (c) nomor urut itu
   menunjuk TEPAT SATU aktivitas di tahap masternya. Yang ambigu sengaja
   dibiarkan NULL: menebak di sini akan menautkan token ke aktivitas yang salah
   secara permanen — persis kerusakan yang skrip ini tutup. Baris NULL tetap
   dilayani jalur cadangan (Tes_Urutan), sama seperti sebelumnya.            */
UPDATE pt
   SET pt.Master_Alur_Tahap_Tes_Id = (
        SELECT MIN(mt.Id_Master_Alur_Tahap_Tes)
          FROM dbo.N_WEB_CAREERS_Master_Alur_Tahap_Tes AS mt
         WHERE mt.Master_Alur_Tahap_Id = pt.Master_Alur_Tahap_Id
           AND mt.Urutan               = pt.Tes_Urutan
   )
  FROM dbo.N_WEB_CAREERS_Penjadwalan_Tahap AS pt
 WHERE pt.Master_Alur_Tahap_Tes_Id IS NULL
   AND pt.Tes_Urutan              IS NOT NULL
   AND pt.Master_Alur_Tahap_Id    IS NOT NULL
   AND (
        SELECT COUNT(*)
          FROM dbo.N_WEB_CAREERS_Master_Alur_Tahap_Tes AS mt
         WHERE mt.Master_Alur_Tahap_Id = pt.Master_Alur_Tahap_Id
           AND mt.Urutan               = pt.Tes_Urutan
   ) = 1;

PRINT CONCAT('Backfill selesai: ', @@ROWCOUNT, ' baris terisi.');
GO

/* ── 3. INDEKS ────────────────────────────────────────────────────────────────
   Dipakai job penerbit token saat memasang ulang tautan ke snapshot kandidat. */
IF NOT EXISTS (
    SELECT 1 FROM sys.indexes
     WHERE name = 'IX_PJT_Master_Alur_Tahap_Tes'
       AND object_id = OBJECT_ID('dbo.N_WEB_CAREERS_Penjadwalan_Tahap')
)
BEGIN
    CREATE INDEX IX_PJT_Master_Alur_Tahap_Tes
        ON dbo.N_WEB_CAREERS_Penjadwalan_Tahap (Master_Alur_Tahap_Tes_Id)
        WHERE Master_Alur_Tahap_Tes_Id IS NOT NULL;

    PRINT 'Indeks IX_PJT_Master_Alur_Tahap_Tes dibuat.';
END
ELSE
    PRINT 'Indeks IX_PJT_Master_Alur_Tahap_Tes sudah ada — dilewati.';
GO

/* ── 4. PERIKSA ───────────────────────────────────────────────────────────── */
SELECT
    COUNT(*)                                                            AS Total_Tahap_Jadwal,
    SUM(CASE WHEN Tes_Urutan IS NOT NULL THEN 1 ELSE 0 END)             AS Punya_Tes_Urutan,
    SUM(CASE WHEN Master_Alur_Tahap_Tes_Id IS NOT NULL THEN 1 ELSE 0 END) AS Punya_Identitas
FROM dbo.N_WEB_CAREERS_Penjadwalan_Tahap;
GO
