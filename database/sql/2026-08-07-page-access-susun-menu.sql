/* ============================================================================
   WEB CAREERS — PENYUSUN MENU PER AKUN (/hak-akses/susun/{user})
   ----------------------------------------------------------------------------
   Menambah 4 kolom TIMPAAN (override) per pengguna di N_WEB_CAREERS_Page_Access.

   ATURAN: NULL = IKUT MASTER MENU (N_WEB_CAREERS_Menu).
   Kolom ini hanya diisi bila admin sengaja menggeser/menamai ulang menu untuk
   akun tertentu, jadi mengubah master menu tetap otomatis ikut ke semua akun
   yang belum pernah ditimpa.

     Nama_Header_Custom  → grup sidebar khusus akun ini (mis. pindah dari
                           "Master Data" ke "Rekrutmen", atau grup baru)
     Sub_Header_Custom   → sub-grup khusus akun ini (metadata, mengikuti SOP
                           master menu; belum dirender sidebar — sama seperti
                           Sub_Header di master)
     Nama_Menu_Custom    → label menu khusus akun ini
     Icon_Menu_Custom    → ikon menu khusus akun ini

   URUTAN tidak butuh kolom baru — sudah ada Urutan_Menu, dan penyusun menu
   menulis ulang nilainya (10, 20, 30, ...) mengikuti hasil geser (drag & drop).

   ⚠ PENTING — kenapa hanya menambah kolom, bukan tabel baru:
   Id_Page_Access adalah induk dari N_WEB_CAREERS_Role_Menu_Access (aksi) dan
   N_WEB_CAREERS_Role_Konten_Access (kategori). Menyusun menu TIDAK BOLEH
   menghapus lalu menyisipkan ulang baris Page_Access, karena Id_Page_Access
   akan berganti dan seluruh centang aksi + kategori pengguna ikut lenyap.
   Karena itu penyimpanannya UPDATE di tempat (lihat SusunMenuController).

   Aman dijalankan berkali-kali (idempoten) — setiap ALTER dijaga IF.
   Jalankan di basis data: Web_HRIS
   ============================================================================ */

IF COL_LENGTH('dbo.N_WEB_CAREERS_Page_Access', 'Nama_Header_Custom') IS NULL
    ALTER TABLE dbo.N_WEB_CAREERS_Page_Access ADD Nama_Header_Custom VARCHAR(100) NULL;
GO

IF COL_LENGTH('dbo.N_WEB_CAREERS_Page_Access', 'Sub_Header_Custom') IS NULL
    ALTER TABLE dbo.N_WEB_CAREERS_Page_Access ADD Sub_Header_Custom VARCHAR(100) NULL;
GO

IF COL_LENGTH('dbo.N_WEB_CAREERS_Page_Access', 'Nama_Menu_Custom') IS NULL
    ALTER TABLE dbo.N_WEB_CAREERS_Page_Access ADD Nama_Menu_Custom VARCHAR(150) NULL;
GO

IF COL_LENGTH('dbo.N_WEB_CAREERS_Page_Access', 'Icon_Menu_Custom') IS NULL
    ALTER TABLE dbo.N_WEB_CAREERS_Page_Access ADD Icon_Menu_Custom VARCHAR(80) NULL;
GO

/* Penyusun menu selalu membaca "semua halaman milik satu pengguna, terurut". */
IF NOT EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE name = 'IX_N_WEB_CAREERS_Page_Access_User_Urutan'
      AND object_id = OBJECT_ID('dbo.N_WEB_CAREERS_Page_Access')
)
    CREATE INDEX IX_N_WEB_CAREERS_Page_Access_User_Urutan
        ON dbo.N_WEB_CAREERS_Page_Access (Id_Users, Urutan_Menu);
GO
