-- User: RIDHO RAHMATULLAH
-- Module: webcareers
-- Tag: Feat : dashboard admin bertab per kategori
-- Description: Mendaftarkan halaman 'dashboardPage' ke Master Menu supaya
--              /karir bisa DIBATASI PER KATEGORI di /hak-akses (Rekrutmen /
--              Magang / MT). Tab yang muncul di dashboard = kategori yang
--              dicentang pada baris KONTEN halaman ini.
--
--              Route /karir SENGAJA tidak dipasangi career.permission — itu
--              halaman beranda admin, jadi tidak boleh bisa terkunci. Baris
--              menu ini ada murni supaya halaman itu punya tempat di layar
--              Hak Akses; gerbang kategorinya ditegakkan DashboardController.
--
--              Item sidebar-nya tidak akan tampil dobel: LayoutShell membuang
--              item grup yang URL-nya sama dengan beranda, karena beranda
--              sudah punya tombolnya sendiri (navigation.home).
--
-- Date: 2026-07-30
-- Catatan: setelah dijalankan, bust cache sidebar + paket akses:
--          php artisan tinker --execute="App\Support\CareerShell::lupakanNav();"
--          Pengguna yang sedang login perlu login ulang (atau tunggu TTL 10
--          menit) supaya session career_akses ikut membawa konten baru.

/* ─── 1. Baris menu ───────────────────────────────────────────────────── */
IF NOT EXISTS (SELECT 1 FROM N_WEB_CAREERS_Menu WHERE Jenis_Page = 'dashboardPage')
BEGIN
    INSERT INTO N_WEB_CAREERS_Menu
        (Jenis_Page, Nama_Menu, Nama_Header, Sub_Header, Icon_Menu, Url_Menu,
         Untuk_Role, Urutan, Flag_Maintenance, Flag_Aktif, Created_At, Created_By)
    VALUES
        ('dashboardPage', 'Dashboard', 'Beranda', 'Ringkasan rekrutmen per kategori',
         'bi bi-speedometer2', '/karir', 'ADMIN', 0, 'T', 'Y', GETDATE(), 'RIDHO');
END
GO

/* ─── 2. Page_Access untuk setiap admin/superadmin aktif ──────────────── */
INSERT INTO N_WEB_CAREERS_Page_Access (Id_Users, Jenis_Page, Urutan_Menu, Created_At, Created_By)
SELECT u.Id_Users, 'dashboardPage', 0, GETDATE(), 'RIDHO'
FROM N_WEB_CAREERS_Users u
WHERE u.Role IN ('ADMIN', 'SUPERADMIN')
  AND u.Status = 'AKTIF'
  AND NOT EXISTS (
      SELECT 1 FROM N_WEB_CAREERS_Page_Access pa
      WHERE pa.Id_Users = u.Id_Users AND pa.Jenis_Page = 'dashboardPage'
  );
GO

/* ─── 3. Aksi VIEW ────────────────────────────────────────────────────── */
INSERT INTO N_WEB_CAREERS_Role_Menu_Access (Id_Page_Access, Id_Aksi, Flag_Diizinkan, Created_At, Created_By)
SELECT pa.Id_Page_Access, a.Id_Aksi, 'Y', GETDATE(), 'RIDHO'
FROM N_WEB_CAREERS_Page_Access pa
CROSS JOIN N_WEB_CAREERS_Aksi a
WHERE pa.Jenis_Page = 'dashboardPage'
  AND a.Nama_Aksi = 'VIEW'
  AND NOT EXISTS (
      SELECT 1 FROM N_WEB_CAREERS_Role_Menu_Access rma
      WHERE rma.Id_Page_Access = pa.Id_Page_Access AND rma.Id_Aksi = a.Id_Aksi
  );
GO

/* ─── 4. KONTEN: seluruh kategori aktif ───────────────────────────────────
   Dicentang eksplisit, bukan dibiarkan kosong. Keduanya sama-sama membuka
   semua tab hari ini (kosong = tidak dibatasi), tapi eksplisit dipilih karena:
     a. di layar /hak-akses centangnya terlihat, jadi admin yang mengatur akses
        tahu ada tiga kategori yang bisa dilepas satu-satu;
     b. seluruh halaman lain di DB ini juga tercatat eksplisit — dibiarkan
        kosong akan terlihat seperti barisnya belum selesai dibuat.
   Konsekuensinya: kalau nanti kategori baru diaktifkan di Master Talent
   Acquisition, tab-nya TIDAK otomatis muncul untuk admin lama — harus
   dicentang di /hak-akses. Untuk kontrol akses, itu memang perilaku yang benar.
*/
INSERT INTO N_WEB_CAREERS_Role_Konten_Access (Id_Page_Access, Kategori, Flag_Diizinkan, Created_At, Created_By)
SELECT pa.Id_Page_Access, ta.Kode, 'Y', GETDATE(), 'RIDHO'
FROM N_WEB_CAREERS_Page_Access pa
CROSS JOIN N_WEB_CAREERS_Master_Talent_Acquisition ta
WHERE pa.Jenis_Page = 'dashboardPage'
  AND ta.Flag_Aktif = 'Y'
  AND NOT EXISTS (
      SELECT 1 FROM N_WEB_CAREERS_Role_Konten_Access rka
      WHERE rka.Id_Page_Access = pa.Id_Page_Access AND rka.Kategori = ta.Kode
  );
GO
