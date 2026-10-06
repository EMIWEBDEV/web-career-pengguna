/* ============================================================================
   WEB CAREERS — IZIN KATEGORI untuk halaman skrining
   ----------------------------------------------------------------------------
   Jalankan SESUDAH 2026-08-24-skrining-hak-akses.sql
   Jalankan di basis data: Web_HRIS
   Aman dijalankan berkali-kali (idempoten).

   ── MASALAH YANG DIPERBAIKI ─────────────────────────────────────────────────

   Halaman lain sudah membatasi kategori per akun lewat Role_Konten_Access:
   ADMIN MT hanya melihat kategori MT, ADMIN REKRUTMEN hanya REKRUTMEN.
   Dua halaman skrining yang baru dibuat TIDAK punya satu pun baris izin —
   dan `AksesService::kategoriDiizinkan()` memperlakukan "tidak ada baris"
   sebagai "tidak dibatasi".

   Akibatnya admin MT melihat pilihan kategori REKRUTMEN dan INTERNSHIP di
   layar Template Baru, padahal ia tidak berhak atas keduanya. Kalau ia
   memilihnya, ia membuat template untuk kategori yang tidak pernah bisa ia
   buka lagi sesudahnya.

   ── APA YANG DIKERJAKAN ─────────────────────────────────────────────────────

   Menyalin izin kategori dari masterFormulirPage — halaman yang paling dekat
   sifatnya, dan yang izinnya sudah dirawat tim. Akun yang belum punya
   masterFormulirPage (mis. SUPERADMIN 2) diberi seluruh kategori aktif.

   ── AKIBATNYA DI LAYAR ──────────────────────────────────────────────────────

   Akun yang cuma berhak SATU kategori tidak lagi ditawari memilih: kategorinya
   dipasang otomatis dan kotak pilihannya tidak digambar. Memilih dari daftar
   berisi satu pilihan yang sudah pasti hanya menyisakan pertanyaan apa gunanya.
   ============================================================================ */


/* ============================================================================
   A. SALIN DARI masterFormulirPage
   ============================================================================ */

INSERT INTO dbo.N_WEB_CAREERS_Role_Konten_Access
    (Id_Page_Access, Kategori, Flag_Diizinkan, Created_At, Created_By, Updated_At, Updated_By)
SELECT tujuan.Id_Page_Access, rk.Kategori, 'Y',
       GETDATE(), 'SISTEM(skrining-kategori)', GETDATE(), 'SISTEM(skrining-kategori)'
  FROM dbo.N_WEB_CAREERS_Page_Access AS acuan
  JOIN dbo.N_WEB_CAREERS_Role_Konten_Access AS rk
    ON rk.Id_Page_Access = acuan.Id_Page_Access AND rk.Flag_Diizinkan = 'Y'
  JOIN dbo.N_WEB_CAREERS_Page_Access AS tujuan
    ON tujuan.Id_Users = acuan.Id_Users
   AND tujuan.Jenis_Page IN ('masterSkriningPage', 'masterPertanyaanPage')
 WHERE acuan.Jenis_Page = 'masterFormulirPage'
   AND NOT EXISTS (
       SELECT 1 FROM dbo.N_WEB_CAREERS_Role_Konten_Access AS x
        WHERE x.Id_Page_Access = tujuan.Id_Page_Access AND x.Kategori = rk.Kategori);
GO


/* ============================================================================
   B. AKUN TANPA ACUAN — diberi seluruh kategori aktif

   SUPERADMIN 2 tidak punya masterFormulirPage, jadi blok A melewatinya. Tanpa
   blok ini ia berakhir tanpa satu pun baris izin — yang berarti "tidak
   dibatasi", dan itu memang benar untuk superadmin. Tapi menuliskannya
   eksplisit membuat layar Manajemen Hak Akses menampilkan kenyataannya, bukan
   kolom kosong yang harus ditebak artinya.
   ============================================================================ */

INSERT INTO dbo.N_WEB_CAREERS_Role_Konten_Access
    (Id_Page_Access, Kategori, Flag_Diizinkan, Created_At, Created_By, Updated_At, Updated_By)
SELECT pa.Id_Page_Access, t.Kode, 'Y',
       GETDATE(), 'SISTEM(skrining-kategori)', GETDATE(), 'SISTEM(skrining-kategori)'
  FROM dbo.N_WEB_CAREERS_Page_Access AS pa
 CROSS JOIN dbo.N_WEB_CAREERS_Master_Talent_Acquisition AS t
 WHERE pa.Jenis_Page IN ('masterSkriningPage', 'masterPertanyaanPage')
   AND t.Flag_Aktif = 'Y'
   AND NOT EXISTS (
       SELECT 1 FROM dbo.N_WEB_CAREERS_Role_Konten_Access AS ada
        WHERE ada.Id_Page_Access = pa.Id_Page_Access)
   AND NOT EXISTS (
       SELECT 1 FROM dbo.N_WEB_CAREERS_Role_Konten_Access AS x
        WHERE x.Id_Page_Access = pa.Id_Page_Access AND x.Kategori = t.Kode);
GO


/* ============================================================================
   C. VERIFIKASI — per akun, bukan cuma jumlah
   ============================================================================ */

SELECT u.Id_Users            AS Akun,
       u.Nama,
       u.Role,
       pa.Jenis_Page         AS Halaman,
       COUNT(rk.Id_Role_Konten_Access) AS Jml_Kategori,
       STRING_AGG(rk.Kategori, ', ')   AS Kategori
  FROM dbo.N_WEB_CAREERS_Page_Access AS pa
  JOIN dbo.N_WEB_CAREERS_Users AS u ON u.Id_Users = pa.Id_Users
  LEFT JOIN dbo.N_WEB_CAREERS_Role_Konten_Access AS rk
         ON rk.Id_Page_Access = pa.Id_Page_Access AND rk.Flag_Diizinkan = 'Y'
 WHERE pa.Jenis_Page IN ('masterSkriningPage', 'masterPertanyaanPage')
 GROUP BY u.Id_Users, u.Nama, u.Role, pa.Jenis_Page
 ORDER BY u.Id_Users, pa.Jenis_Page;
GO
