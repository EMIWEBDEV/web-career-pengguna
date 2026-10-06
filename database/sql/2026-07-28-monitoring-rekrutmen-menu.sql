-- User: RIDHO RAHMATULLAH
-- Module: webcareers
-- Tag: Feat : monitoring rekrutmen
-- Description: Rename menu "Hasil Tes" -> "Monitoring Rekrutmen" + URL baru.
--              Permission key (Jenis_Page = hasilTesPage) SENGAJA tidak diubah:
--              dipakai middleware career.permission & seluruh data Klasifikasi/
--              Page_Access — hak akses existing tetap berlaku tanpa migrasi.
-- Date: 2026-07-28
-- Catatan: setelah dijalankan, bust cache sidebar:
--          php artisan tinker --execute="App\Support\CareerShell::lupakanNav();"
--          (atau tunggu TTL cache menu ±5 menit)

UPDATE N_WEB_CAREERS_Menu
SET Nama_Menu  = 'Monitoring Rekrutmen',
    Sub_Header = 'Pantau semua program & pelamar',
    Icon_Menu  = 'bi bi-activity',
    Url_Menu   = '/karir/monitoring',
    Updated_At = GETDATE(),
    Updated_By = 'RIDHO'
WHERE Jenis_Page = 'hasilTesPage';
GO
