/* ============================================================================
   WEB CAREERS — PENAMAAN MENU MODUL SKRINING
   ----------------------------------------------------------------------------
   Jalankan di basis data: Web_HRIS
   Aman dijalankan berkali-kali (idempoten).

   ── MASALAH ─────────────────────────────────────────────────────────────────

   "Bank Pertanyaan" ambigu, dan ambigunya bersifat RELASIONAL: ia berdiri di
   sidebar tepat di sebelah "Master Phone Screening", dan tidak satu pun dari
   keduanya menyebutkan apa isinya sebenarnya.

       Master Phone Screening   → berisi TEMPLATE (susunan pertanyaan)
       Bank Pertanyaan          → berisi PERTANYAAN satuan

   Orang yang baru membuka sidebar tidak punya cara menebak mana yang mana.
   "Bank" juga terbaca sebagai istilah keuangan sebelum terbaca sebagai
   "pustaka" — dan di modul HRIS yang bersebelahan dengan penggajian, itu
   bukan salah baca yang jauh.

   ── PERBAIKAN ───────────────────────────────────────────────────────────────

       Master Pertanyaan Skrining   pustaka pertanyaan, ditandai per departemen
       Master Template Skrining     susunan pertanyaan yang dipasang per loker

   Sepasang nama yang membedakan dirinya sendiri: PERTANYAAN vs TEMPLATE.
   Keduanya berawalan "Master", sepola seluruh menu Master Data lainnya.

   Kata "Phone Screening" sengaja diganti "Skrining": instrumen yang sama sudah
   dipakai untuk user screening dan walk-in screening, dan namanya tidak boleh
   mengunci fitur pada satu cara pelaksanaan. Kolom `Metode` di sesi yang
   mencatat "lewat apa"-nya.

   Jenis_Page TIDAK diubah. Nilai itu dirujuk rute, middleware izin, dan
   Page_Access milik tujuh akun; menggantinya berarti memutus semuanya demi
   perbaikan yang hanya menyangkut kata di layar.
   ============================================================================ */

UPDATE dbo.N_WEB_CAREERS_Menu
   SET Nama_Menu = N'Master Pertanyaan Skrining',
       Sub_Header = N'Pustaka pertanyaan, ditandai per departemen',
       Icon_Menu = 'bi bi-collection',
       Updated_At = GETDATE(),
       Updated_By = 'SISTEM(nama-menu-skrining)'
 WHERE Jenis_Page = 'masterPertanyaanPage';
GO

UPDATE dbo.N_WEB_CAREERS_Menu
   SET Nama_Menu = N'Master Template Skrining',
       Sub_Header = N'Susunan pertanyaan yang dipasang per loker',
       Icon_Menu = 'bi bi-telephone-inbound',
       Updated_At = GETDATE(),
       Updated_By = 'SISTEM(nama-menu-skrining)'
 WHERE Jenis_Page = 'masterSkriningPage';
GO

/* Urutan disandingkan: pertanyaan lebih dulu, template sesudahnya — sesuai
   urutan kerjanya. Menyusun template menuntut pertanyaannya sudah ada. */
UPDATE dbo.N_WEB_CAREERS_Menu SET Urutan = 26 WHERE Jenis_Page = 'masterPertanyaanPage';
UPDATE dbo.N_WEB_CAREERS_Menu SET Urutan = 27 WHERE Jenis_Page = 'masterSkriningPage';
GO

SELECT Urutan, Jenis_Page, Nama_Menu, Sub_Header, Icon_Menu
  FROM dbo.N_WEB_CAREERS_Menu
 WHERE Jenis_Page IN ('masterPertanyaanPage', 'masterSkriningPage')
 ORDER BY Urutan;
GO
