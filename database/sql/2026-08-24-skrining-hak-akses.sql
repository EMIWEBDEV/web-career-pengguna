/* ============================================================================
   WEB CAREERS — HAK AKSES PHONE SCREENING (menu + izin, berbasis PERAN)
   ----------------------------------------------------------------------------
   Jalankan SESUDAH:
     · 2026-08-24-phone-screening.sql
     · 2026-08-24-bank-pertanyaan.sql

   Jalankan di basis data: Web_HRIS
   Aman dijalankan berkali-kali (idempoten).

   ── KENAPA BERKAS INI ADA, PADAHAL DUA BERKAS SEBELUMNYA SUDAH MEMBERI AKSES ─

   Karena keduanya memberi akses dengan MENYALIN dari halaman lain
   (masterFormulirPage → masterSkriningPage → masterPertanyaanPage), dan
   penyalinan mewarisi lubangnya.

   Buktinya ada di data ini sendiri: akun SUPERADMIN 2 (#26) TIDAK punya
   masterFormulirPage, jadi ia juga tidak kebagian dua halaman baru itu —
   seorang superadmin yang tidak bisa membuka menu yang seharusnya ia kelola,
   tanpa satu pun pesan yang menjelaskan kenapa.

   Berkas ini memberi akses berdasarkan PERAN, bukan berdasarkan siapa yang
   kebetulan sudah punya halaman lain. Setiap akun ADMIN dan SUPERADMIN yang
   berstatus AKTIF dapat keduanya, lengkap dengan empat aksinya.

   ── APA YANG DIKERJAKAN ─────────────────────────────────────────────────────
     A. Pastikan kedua baris menu ada & aktif
     B. Beri Page_Access ke SELURUH akun ADMIN + SUPERADMIN
     C. Beri aksi VIEW / CREATE / EDIT / DELETE
     D. Tambal akun yang punya halaman tapi aksinya belum lengkap
     E. Samakan Lingkup_Pic ke SEMUA (master data tidak berlingkup PIC)
     F. Verifikasi — per akun, bukan cuma jumlah

   ── YANG SENGAJA TIDAK DIBERIKAN ────────────────────────────────────────────

   APPROVE, PRINT, EXPORT, ULANG, LINTAS_PIC, SERAH_TERIMA. Tidak satu pun
   punya arti di halaman master template; memberikannya cuma menambah baris
   izin yang tidak pernah dibaca satu baris kode pun, dan membuat layar
   Manajemen Hak Akses lebih sulit dibaca tanpa menambah kemampuan apa-apa.

   Aksi untuk MENJALANKAN skrining (buka sesi, isi, kunci) tidak diatur di
   sini: itu menumpang pelamarPage yang sudah ada, dan memang seharusnya —
   yang menjalankan skrining adalah orang yang sudah memegang worklist.
   ============================================================================ */


/* ============================================================================
   A. BARIS MENU

   Ditulis ulang di sini supaya berkas ini berdiri sendiri: kalau seseorang
   menjalankannya di lingkungan yang baru sebagian dimigrasi, menunya tetap
   ada dan tidak menghasilkan Page_Access yang menunjuk menu hantu.
   ============================================================================ */

IF NOT EXISTS (SELECT 1 FROM dbo.N_WEB_CAREERS_Menu WHERE Jenis_Page = 'masterSkriningPage')
    INSERT INTO dbo.N_WEB_CAREERS_Menu
        (Jenis_Page, Nama_Menu, Nama_Header, Sub_Header, Icon_Menu, Url_Menu,
         Untuk_Role, Urutan, Flag_Maintenance, Flag_Aktif,
         Created_At, Created_By, Updated_At, Updated_By)
    VALUES
        ('masterSkriningPage', 'Master Phone Screening', 'Master Data',
         'Template pertanyaan skrining', 'bi bi-telephone-inbound', '/master-skrining',
         'ADMIN', 26, 'T', 'Y',
         GETDATE(), 'SISTEM(hak-akses-skrining)', GETDATE(), 'SISTEM(hak-akses-skrining)');
GO

IF NOT EXISTS (SELECT 1 FROM dbo.N_WEB_CAREERS_Menu WHERE Jenis_Page = 'masterPertanyaanPage')
    INSERT INTO dbo.N_WEB_CAREERS_Menu
        (Jenis_Page, Nama_Menu, Nama_Header, Sub_Header, Icon_Menu, Url_Menu,
         Untuk_Role, Urutan, Flag_Maintenance, Flag_Aktif,
         Created_At, Created_By, Updated_At, Updated_By)
    VALUES
        ('masterPertanyaanPage', 'Bank Pertanyaan', 'Master Data',
         'Pustaka pertanyaan lintas template', 'bi bi-patch-question', '/master-pertanyaan',
         'ADMIN', 27, 'T', 'Y',
         GETDATE(), 'SISTEM(hak-akses-skrining)', GETDATE(), 'SISTEM(hak-akses-skrining)');
GO

/* Menu yang pernah dinonaktifkan atau ditandai maintenance dinyalakan lagi —
   berkas ini dijalankan justru untuk membuat kedua halaman itu bisa dibuka. */
UPDATE dbo.N_WEB_CAREERS_Menu
   SET Flag_Aktif = 'Y',
       Flag_Maintenance = 'T',
       Updated_At = GETDATE(),
       Updated_By = 'SISTEM(hak-akses-skrining)'
 WHERE Jenis_Page IN ('masterSkriningPage', 'masterPertanyaanPage')
   AND (Flag_Aktif <> 'Y' OR Flag_Maintenance <> 'T');
GO


/* ============================================================================
   B. HAK HALAMAN — SELURUH ADMIN & SUPERADMIN AKTIF

   Urutan_Menu mengikuti urutan menunya (26 dan 27). Kolom itu mengatur posisi
   di sidebar milik akun tersebut, bukan wewenang — akun yang sudah menyusun
   ulang menunya sendiri boleh saja berbeda, dan itu sebabnya nilai ini hanya
   dipasang saat baris DIBUAT, tidak pernah ditimpa belakangan.
   ============================================================================ */

INSERT INTO dbo.N_WEB_CAREERS_Page_Access
    (Id_Users, Jenis_Page, Urutan_Menu, Lingkup_Pic,
     Created_At, Created_By, Updated_At, Updated_By)
SELECT u.Id_Users, m.Jenis_Page, m.Urutan, 'SEMUA',
       GETDATE(), 'SISTEM(hak-akses-skrining)', GETDATE(), 'SISTEM(hak-akses-skrining)'
  FROM dbo.N_WEB_CAREERS_Users AS u
 CROSS JOIN (VALUES ('masterSkriningPage', 26), ('masterPertanyaanPage', 27)) AS m (Jenis_Page, Urutan)
 WHERE u.Role IN ('ADMIN', 'SUPERADMIN')
   AND u.Status = 'AKTIF'
   AND NOT EXISTS (
       SELECT 1 FROM dbo.N_WEB_CAREERS_Page_Access AS x
        WHERE x.Id_Users = u.Id_Users AND x.Jenis_Page = m.Jenis_Page);
GO


/* ============================================================================
   C. AKSI — VIEW / CREATE / EDIT / DELETE

   Empat aksi, dua halaman, seluruh akun. NOT EXISTS di bawah membuat blok ini
   aman diulang DAN sekaligus menambal akun yang barusan dapat halamannya di
   blok B tapi belum punya satu pun aksi.
   ============================================================================ */

INSERT INTO dbo.N_WEB_CAREERS_Role_Menu_Access
    (Id_Page_Access, Id_Aksi, Flag_Diizinkan, Created_At, Created_By, Updated_At, Updated_By)
SELECT pa.Id_Page_Access, a.Id_Aksi, 'Y',
       GETDATE(), 'SISTEM(hak-akses-skrining)', GETDATE(), 'SISTEM(hak-akses-skrining)'
  FROM dbo.N_WEB_CAREERS_Page_Access AS pa
  JOIN dbo.N_WEB_CAREERS_Users AS u ON u.Id_Users = pa.Id_Users
 CROSS JOIN dbo.N_WEB_CAREERS_Aksi AS a
 WHERE pa.Jenis_Page IN ('masterSkriningPage', 'masterPertanyaanPage')
   AND u.Role IN ('ADMIN', 'SUPERADMIN')
   AND a.Flag_Aktif = 'Y'
   AND a.Nama_Aksi IN ('VIEW', 'CREATE', 'EDIT', 'DELETE')
   AND NOT EXISTS (
       SELECT 1 FROM dbo.N_WEB_CAREERS_Role_Menu_Access AS r
        WHERE r.Id_Page_Access = pa.Id_Page_Access AND r.Id_Aksi = a.Id_Aksi);
GO


/* ============================================================================
   D. TAMBAL IZIN YANG PERNAH DIMATIKAN

   Baris yang sudah ada tapi Flag_Diizinkan-nya 'T' tidak tersentuh blok C —
   NOT EXISTS di sana melihat barisnya ADA, lalu melewatinya. Akibatnya akun
   yang izinnya pernah dicabut manual akan diam-diam tetap terkunci meski
   berkas ini dijalankan justru untuk membukanya.

   Hanya empat aksi itu yang dinyalakan. Aksi lain yang pernah diberikan
   seseorang dengan sengaja tidak diubah.
   ============================================================================ */

UPDATE r
   SET r.Flag_Diizinkan = 'Y',
       r.Updated_At = GETDATE(),
       r.Updated_By = 'SISTEM(hak-akses-skrining)'
  FROM dbo.N_WEB_CAREERS_Role_Menu_Access AS r
  JOIN dbo.N_WEB_CAREERS_Page_Access AS pa ON pa.Id_Page_Access = r.Id_Page_Access
  JOIN dbo.N_WEB_CAREERS_Users AS u ON u.Id_Users = pa.Id_Users
  JOIN dbo.N_WEB_CAREERS_Aksi AS a ON a.Id_Aksi = r.Id_Aksi
 WHERE pa.Jenis_Page IN ('masterSkriningPage', 'masterPertanyaanPage')
   AND u.Role IN ('ADMIN', 'SUPERADMIN')
   AND a.Nama_Aksi IN ('VIEW', 'CREATE', 'EDIT', 'DELETE')
   AND ISNULL(r.Flag_Diizinkan, 'T') <> 'Y';
GO


/* ============================================================================
   E. LINGKUP PIC = SEMUA

   Master template dan bank pertanyaan BUKAN data ber-PIC: tidak ada
   "pertanyaan milik rekruter A". Lingkup SENDIRI di sini akan menyaring
   berdasarkan kolom PIC yang memang tidak ada di kedua tabel itu, dan
   hasilnya halaman kosong tanpa sebab yang bisa dijelaskan ke penggunanya.

   Yang BERLINGKUP PIC adalah pengikatan template ke loker — dan itu ikut
   programPage, di mana lingkupnya memang sudah diatur per akun.
   ============================================================================ */

UPDATE pa
   SET pa.Lingkup_Pic = 'SEMUA',
       pa.Updated_At = GETDATE(),
       pa.Updated_By = 'SISTEM(hak-akses-skrining)'
  FROM dbo.N_WEB_CAREERS_Page_Access AS pa
 WHERE pa.Jenis_Page IN ('masterSkriningPage', 'masterPertanyaanPage')
   AND ISNULL(pa.Lingkup_Pic, '') <> 'SEMUA';
GO


/* ============================================================================
   F. VERIFIKASI

   Dua bagian. Yang pertama ringkasan; yang kedua PER AKUN — karena "6 akun
   diberi akses" terbaca sebagai berhasil sekalipun akun yang justru dicari
   ternyata bukan salah satunya. Itu persis kejadian yang membuat berkas ini
   perlu ditulis.
   ============================================================================ */

SELECT 'Akun ADMIN + SUPERADMIN aktif' AS Pemeriksaan,
       CAST(COUNT(*) AS VARCHAR(10)) AS Jumlah,
       '' AS Catatan
  FROM dbo.N_WEB_CAREERS_Users
 WHERE Role IN ('ADMIN', 'SUPERADMIN') AND Status = 'AKTIF'
UNION ALL
SELECT 'Punya masterSkriningPage',
       CAST(COUNT(*) AS VARCHAR(10)),
       CASE WHEN COUNT(*) = (SELECT COUNT(*) FROM dbo.N_WEB_CAREERS_Users
                              WHERE Role IN ('ADMIN','SUPERADMIN') AND Status = 'AKTIF')
            THEN 'OK - lengkap' ELSE 'GAGAL - ada yang terlewat' END
  FROM dbo.N_WEB_CAREERS_Page_Access AS pa
  JOIN dbo.N_WEB_CAREERS_Users AS u ON u.Id_Users = pa.Id_Users
 WHERE pa.Jenis_Page = 'masterSkriningPage' AND u.Role IN ('ADMIN','SUPERADMIN') AND u.Status = 'AKTIF'
UNION ALL
SELECT 'Punya masterPertanyaanPage',
       CAST(COUNT(*) AS VARCHAR(10)),
       CASE WHEN COUNT(*) = (SELECT COUNT(*) FROM dbo.N_WEB_CAREERS_Users
                              WHERE Role IN ('ADMIN','SUPERADMIN') AND Status = 'AKTIF')
            THEN 'OK - lengkap' ELSE 'GAGAL - ada yang terlewat' END
  FROM dbo.N_WEB_CAREERS_Page_Access AS pa
  JOIN dbo.N_WEB_CAREERS_Users AS u ON u.Id_Users = pa.Id_Users
 WHERE pa.Jenis_Page = 'masterPertanyaanPage' AND u.Role IN ('ADMIN','SUPERADMIN') AND u.Status = 'AKTIF'
UNION ALL
/* Halaman yang aksinya belum genap empat.
   Ditulis sebagai LEFT JOIN + HAVING, bukan EXISTS bersarang di dalam SUM:
   SQL Server menolak agregat yang membungkus subkueri, dan versi pertama
   berkas ini gagal persis di situ. */
SELECT 'Halaman dgn aksi belum genap',
       CAST(COUNT(*) AS VARCHAR(10)),
       CASE WHEN COUNT(*) = 0 THEN 'OK - semuanya 4 aksi' ELSE 'GAGAL - lihat rincian di bawah' END
  FROM (
      SELECT pa.Id_Page_Access
        FROM dbo.N_WEB_CAREERS_Page_Access AS pa
        JOIN dbo.N_WEB_CAREERS_Users AS u ON u.Id_Users = pa.Id_Users
        LEFT JOIN dbo.N_WEB_CAREERS_Role_Menu_Access AS r
               ON r.Id_Page_Access = pa.Id_Page_Access AND r.Flag_Diizinkan = 'Y'
        LEFT JOIN dbo.N_WEB_CAREERS_Aksi AS a
               ON a.Id_Aksi = r.Id_Aksi AND a.Nama_Aksi IN ('VIEW','CREATE','EDIT','DELETE')
       WHERE pa.Jenis_Page IN ('masterSkriningPage','masterPertanyaanPage')
         AND u.Role IN ('ADMIN','SUPERADMIN') AND u.Status = 'AKTIF'
       GROUP BY pa.Id_Page_Access
      HAVING COUNT(a.Id_Aksi) < 4
  ) AS kurang;
GO

/* Rincian per akun — daftar inilah yang dibaca, bukan angkanya. */
SELECT u.Id_Users        AS Akun,
       u.Nama,
       u.Role,
       MAX(CASE WHEN pa.Jenis_Page = 'masterSkriningPage'   THEN 'YA' ELSE '' END) AS Template,
       MAX(CASE WHEN pa.Jenis_Page = 'masterPertanyaanPage' THEN 'YA' ELSE '' END) AS Bank,
       (SELECT COUNT(*)
          FROM dbo.N_WEB_CAREERS_Role_Menu_Access AS r
          JOIN dbo.N_WEB_CAREERS_Page_Access AS p2 ON p2.Id_Page_Access = r.Id_Page_Access
          JOIN dbo.N_WEB_CAREERS_Aksi AS a ON a.Id_Aksi = r.Id_Aksi
         WHERE p2.Id_Users = u.Id_Users
           AND p2.Jenis_Page IN ('masterSkriningPage','masterPertanyaanPage')
           AND r.Flag_Diizinkan = 'Y'
           AND a.Nama_Aksi IN ('VIEW','CREATE','EDIT','DELETE')) AS Aksi_Dari_8
  FROM dbo.N_WEB_CAREERS_Users AS u
  LEFT JOIN dbo.N_WEB_CAREERS_Page_Access AS pa
         ON pa.Id_Users = u.Id_Users
        AND pa.Jenis_Page IN ('masterSkriningPage','masterPertanyaanPage')
 WHERE u.Role IN ('ADMIN','SUPERADMIN') AND u.Status = 'AKTIF'
 GROUP BY u.Id_Users, u.Nama, u.Role
 ORDER BY u.Role DESC, u.Id_Users;
GO
