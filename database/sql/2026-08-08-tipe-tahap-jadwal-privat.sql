/* ============================================================================
   WEB CAREERS — JADWAL PRIVAT (tahap yang dijadwalkan TAPI tidak diumumkan)
   ----------------------------------------------------------------------------
   Menambah satu penanda di N_WEB_CAREERS_Master_Tipe_Tahap:

     Flag_Jadwal_Privat = 'Y'
       → aktivitas bertipe ini BOLEH dijadwalkan seperti biasa oleh tim, tetapi
         jadwalnya TIDAK diumumkan kepada kandidat: tidak muncul di portal, dan
         tidak ada undangan email.

   KENAPA PERLU PENANDA, BUKAN `if (kode === 'NEGOTIATION')`
   Negosiasi memang yang pertama membutuhkannya, tapi ia bukan satu-satunya
   kegiatan yang dijadwalkan tim untuk dirinya sendiri — rapat kalibrasi
   penilai, diskusi panel, dan persetujuan atasan berperilaku sama. Ditulis
   sebagai perbandingan kode di dalam program, tiap kegiatan semacam itu menuntut
   satu baris `if` baru dan satu kali rilis. Sebagai kolom master, ia cukup
   dicentang.

   KENAPA NEGOSIASI PRIVAT
   Negosiasi gaji adalah pembicaraan internal tim sebelum angkanya diajukan.
   Undangan "Negosiasi Penawaran, Selasa 10.00" yang mendarat di email kandidat
   memberitahunya bahwa angkanya sedang dirundingkan — dan sejak saat itu tiap
   hari tanpa kabar terbaca sebagai penolakan yang tertunda. Yang perlu ia
   terima adalah HASILNYA (penawaran), bukan jadwal rapat tentang dirinya.

   Aman dijalankan berkali-kali (idempoten).
   Jalankan di basis data: Web_HRIS
   ============================================================================ */

IF COL_LENGTH('dbo.N_WEB_CAREERS_Master_Tipe_Tahap', 'Flag_Jadwal_Privat') IS NULL
    ALTER TABLE dbo.N_WEB_CAREERS_Master_Tipe_Tahap
        ADD Flag_Jadwal_Privat CHAR(1) NOT NULL CONSTRAINT DF_NWC_Tipe_Tahap_Jadwal_Privat DEFAULT 'T';
GO

/* Negosiasi Penawaran — dijadwalkan tim, tidak diumumkan ke kandidat. */
UPDATE dbo.N_WEB_CAREERS_Master_Tipe_Tahap
   SET Flag_Jadwal_Privat = 'Y',
       Updated_At = GETDATE(),
       Updated_By = 'SISTEM(jadwal-privat)'
 WHERE Kode = 'NEGOTIATION'
   AND Flag_Jadwal_Privat <> 'Y';
GO

/* Aktivitas negosiasi yang SUDAH terlanjur dibuat di alur — disembunyikan juga,
   supaya alur yang sudah tersusun tidak perlu disunting satu per satu.
   Aktivitas yang menuntut unggahan kandidat SENGAJA dilewati: menyembunyikan
   layar yang justru meminta berkas darinya adalah jalan buntu. */
UPDATE mt
   SET mt.Tampil_Kandidat = 'T',
       mt.Updated_At = GETDATE(),
       mt.Updated_By = 'SISTEM(jadwal-privat)'
  FROM dbo.N_WEB_CAREERS_Master_Alur_Tahap_Tes AS mt
  JOIN dbo.N_WEB_CAREERS_Master_Tipe_Tahap AS tt ON tt.Kode = mt.Tipe_Tahap_Kode
 WHERE tt.Flag_Jadwal_Privat = 'Y'
   AND ISNULL(mt.Unggah_Kandidat, 'T') <> 'Y'
   AND ISNULL(mt.Tampil_Kandidat, 'Y') <> 'T';
GO

/* Lamaran yang SEDANG BERJALAN ikut ditutup jadwalnya.
   Hanya yang belum selesai: aktivitas yang sudah tuntas biarkan apa adanya —
   mengubah tampilan riwayat yang sudah dibaca kandidat hanya membingungkan. */
UPDATE st
   SET st.Tampil_Kandidat = 'T',
       st.Updated_At = GETDATE(),
       st.Updated_By = 'SISTEM(jadwal-privat)'
  FROM dbo.N_WEB_CAREERS_Lamaran_Tahap_Tes AS st
  JOIN dbo.N_WEB_CAREERS_Master_Tipe_Tahap AS tt ON tt.Kode = st.Tipe_Tahap_Kode
 WHERE tt.Flag_Jadwal_Privat = 'Y'
   AND ISNULL(st.Unggah_Kandidat, 'T') <> 'Y'
   AND ISNULL(st.Flag_Selesai, 'N') <> 'Y'
   AND ISNULL(st.Tampil_Kandidat, 'Y') <> 'T';
GO
