/* ============================================================================
   WEB CAREERS — BERKAS DI DALAM BAGIAN BERULANG
   ----------------------------------------------------------------------------
   Menambah dua kolom di N_WEB_CAREERS_Formulir_Berkas:

     Bagian_Key   kunci bagian berulang tempat berkas ini berada
     Baris_Index  indeks baris berbasis 0 di dalam bagian itu

   Keduanya NULL untuk berkas biasa (dok_cv, dok_ktp, …) — dan itu memang
   mayoritas baris yang sudah ada, jadi TIDAK ADA satu pun baris lama yang
   perlu disentuh. Skrip ini murni penambahan kolom: tidak ada INSERT, UPDATE,
   maupun DELETE di dalamnya.

   KENAPA MASALAHNYA ADA
   Sebelum ini berkas hanya dikenali dari Field_Key. Setiap baris bagian
   berulang mengirim Field_Key yang sama persis, sehingga tiga sertifikat
   runtuh jadi satu baris — dan dua objeknya di GCS tertimpa lalu terhapus.
   Pengisian 61 mencatat tiga nama berkas di Jawaban_Json tapi hanya satu yang
   benar-benar ada di bucket.

   KENAPA PERLU DUA KOLOM, BUKAN MENYISIPKAN INDEKS KE Field_Key
   Field_Key dipakai untuk mencari LABEL pertanyaannya di skema formulir
   (LaporanKandidat::petaSkema). Begitu isinya jadi "sert_file#2", pencarian itu
   putus dan setiap konsumen — detail lamaran, PDF, Excel, monitoring — wajib
   mengurai suffix lebih dulu. Yang lupa akan diam-diam mencetak "Sert File#2"
   di dokumen resmi.

   Aman dijalankan berkali-kali (idempoten).
   Jalankan di basis data: Web_HRIS
   ============================================================================ */

IF COL_LENGTH('dbo.N_WEB_CAREERS_Formulir_Berkas', 'Bagian_Key') IS NULL
    ALTER TABLE dbo.N_WEB_CAREERS_Formulir_Berkas
        ADD Bagian_Key VARCHAR(60) NULL;
GO

IF COL_LENGTH('dbo.N_WEB_CAREERS_Formulir_Berkas', 'Baris_Index') IS NULL
    ALTER TABLE dbo.N_WEB_CAREERS_Formulir_Berkas
        ADD Baris_Index INT NULL;
GO

/* Verifikasi — jalankan setelah dua blok di atas.
   Harapan hasilnya:
     Bagian_Key   varchar  60    YES
     Baris_Index  int      NULL  YES */
SELECT COLUMN_NAME, DATA_TYPE, CHARACTER_MAXIMUM_LENGTH, IS_NULLABLE
  FROM INFORMATION_SCHEMA.COLUMNS
 WHERE TABLE_NAME = 'N_WEB_CAREERS_Formulir_Berkas'
   AND COLUMN_NAME IN ('Bagian_Key', 'Baris_Index');
GO
