-- User: RIDHO RAHMATULLAH
-- Module: webcareers
-- Tag: Feat : div/dept information
-- Description: Registrasi menu + hak akses Master Info Divisi (RBAC)
-- Date: 2026-07-27

-- 1) Menu baru
IF NOT EXISTS (SELECT 1 FROM N_WEB_CAREERS_Menu WHERE Jenis_Page = 'masterInfoDivisiPage')
INSERT INTO N_WEB_CAREERS_Menu
    (Jenis_Page, Nama_Menu, Nama_Header, Sub_Header, Icon_Menu, Url_Menu,
     Untuk_Role, Urutan, Flag_Maintenance, Flag_Aktif,
     Created_At, Created_By, Updated_At, Updated_By)
VALUES
    ('masterInfoDivisiPage', 'Master Info Divisi', 'Master Data', 'Konten divisi landing page',
     'bi bi-diagram-3', '/master-info-divisi',
     'ADMIN', 16, 'T', 'Y',
     GETDATE(), 'RIDHO', GETDATE(), 'RIDHO');

-- 2) Page access untuk semua user yang sudah punya akses master kampus
INSERT INTO N_WEB_CAREERS_Page_Access
    (Id_Users, Jenis_Page, Urutan_Menu, Created_At, Created_By, Updated_At, Updated_By)
SELECT DISTINCT pa.Id_Users, 'masterInfoDivisiPage', 16, GETDATE(), 'RIDHO', GETDATE(), 'RIDHO'
FROM N_WEB_CAREERS_Page_Access pa
WHERE pa.Jenis_Page = 'masterKampusPage'
  AND NOT EXISTS (
      SELECT 1 FROM N_WEB_CAREERS_Page_Access x
      WHERE x.Id_Users = pa.Id_Users AND x.Jenis_Page = 'masterInfoDivisiPage');

-- 3) Grant semua aksi aktif (VIEW/CREATE/EDIT/DELETE/APPROVE)
INSERT INTO N_WEB_CAREERS_Role_Menu_Access
    (Id_Page_Access, Id_Aksi, Flag_Diizinkan, Created_At, Created_By, Updated_At, Updated_By)
SELECT pa.Id_Page_Access, a.Id_Aksi, 'Y', GETDATE(), 'RIDHO', GETDATE(), 'RIDHO'
FROM N_WEB_CAREERS_Page_Access pa
CROSS JOIN N_WEB_CAREERS_Aksi a
WHERE pa.Jenis_Page = 'masterInfoDivisiPage'
  AND a.Flag_Aktif = 'Y'
  AND NOT EXISTS (
      SELECT 1 FROM N_WEB_CAREERS_Role_Menu_Access r
      WHERE r.Id_Page_Access = pa.Id_Page_Access AND r.Id_Aksi = a.Id_Aksi);
GO
