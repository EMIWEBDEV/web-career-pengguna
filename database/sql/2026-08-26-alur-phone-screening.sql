/* ══════════════════════════════════════════════════════════════════════════
   WEB CAREERS — MENYALAKAN PHONE SCREENING DI ALUR YANG SUDAH ADA
   ══════════════════════════════════════════════════════════════════════════

   MASALAHNYA

   Alur "Alur rekrutmen 2026" sudah punya tahap bernama PHONE SCREENING, tapi
   TIPE tahapnya dipilih "Screening" (ADMIN_SCREENING) — bukan "Phone /
   Recruiter Screening" (PHONE_SCREEN).

   Yang menentukan sebuah aktivitas punya kuesioner skrining adalah penanda
   Flag_Skrining di Master Tipe Tahap, bukan namanya. Karena ADMIN_SCREENING
   ber-Flag_Skrining='T', kartu "Kuesioner Phone Screening" di Program Kegiatan
   tidak pernah digambar — sengaja: alur tanpa skrining tidak perlu tahu fitur
   itu ada.

   Jadi bukan fiturnya yang belum tersambung, melainkan satu pilihan tipe yang
   meleset. Berkas ini membetulkannya.

   ── DUA PERUBAHAN, DAN YANG KEDUA PERLU PERSETUJUAN ANDA ────────────────────

     1. Aktivitas "PHONE SCREENING" dipindah ke tipe PHONE_SCREEN.
        Ini yang menyalakan kartu pengikatan template.

     2. PHONE_SCREEN.Flag_Jadwal diubah 'Y' → 'T'.

        Alasannya: aktivitas ber-Flag_Jadwal='Y' dianggap belum tuntas selama
        belum punya tanggal, dan skrining telepon tidak dijadwalkan — rekruter
        menelepon, lalu mencatat percobaan keberapa dan hasil kontaknya di
        panel kuesioner. Kolom Percobaan dan Hasil_Kontak ada persis untuk itu.

        BILA Anda memang ingin panggilannya dijadwalkan dulu, kembalikan lewat
        Master Tipe Tahap → Phone / Recruiter Screening → centang "Butuh
        jadwal". Tidak perlu menjalankan apa pun lagi.

   AMAN DIJALANKAN ULANG. Keduanya menetapkan nilai akhir, bukan menggeser
   dari nilai sekarang.

   SESUDAH DIJALANKAN: buang cache — php artisan cache:clear
   ══════════════════════════════════════════════════════════════════════════ */

SET NOCOUNT ON;
GO

/* ═══ 1 · AKTIVITAS PINDAH KE TIPE PHONE_SCREEN ══════════════════════════

   Ditunjuk lewat NAMA aktivitas + nama alurnya, bukan lewat Id: Id aktivitas
   berbeda di tiap basis data, dan berkas yang hanya benar di satu tempat
   bukan berkas yang layak masuk repositori.

   Yang disentuh hanya aktivitas yang tipenya masih ADMIN_SCREENING — kalau
   sudah pernah dibetulkan lewat layar Master Tahapan Seleksi, berkas ini
   tidak melakukan apa-apa. */
UPDATE x
SET x.Tipe_Tahap_Kode = 'PHONE_SCREEN',
    x.Updated_At      = SYSDATETIME(),
    x.Updated_By      = 'SISTEM(alur-phone-screening)'
FROM dbo.N_WEB_CAREERS_Master_Alur_Tahap_Tes x
JOIN dbo.N_WEB_CAREERS_Master_Alur_Tahap t ON t.Id_Master_Alur_Tahap = x.Master_Alur_Tahap_Id
WHERE x.Tipe_Tahap_Kode = 'ADMIN_SCREENING'
  AND (
        UPPER(REPLACE(x.Label, '_', ' ')) LIKE '%PHONE SCREEN%'
     OR UPPER(REPLACE(t.Label, '_', ' ')) LIKE '%PHONE SCREEN%'
     OR UPPER(REPLACE(t.Kode,  '_', ' ')) LIKE '%PHONE SCREEN%'
  );

PRINT '  ~ aktivitas dipindah ke PHONE_SCREEN: ' + CAST(@@ROWCOUNT AS varchar(10));
GO

/* Tahap induknya ikut, supaya aktivitas yang ditambahkan ke tahap ini nanti
   mewarisi tipe yang benar. Aktivitas mewarisi tipe tahap saat tipenya sendiri
   kosong; tahap yang tetap ADMIN_SCREENING akan diam-diam melahirkan aktivitas
   tanpa kuesioner. */
UPDATE t
SET t.Tipe_Tahap_Kode = 'PHONE_SCREEN',
    t.Updated_At      = SYSDATETIME(),
    t.Updated_By      = 'SISTEM(alur-phone-screening)'
FROM dbo.N_WEB_CAREERS_Master_Alur_Tahap t
WHERE t.Tipe_Tahap_Kode = 'ADMIN_SCREENING'
  AND (
        UPPER(REPLACE(t.Label, '_', ' ')) LIKE '%PHONE SCREEN%'
     OR UPPER(REPLACE(t.Kode,  '_', ' ')) LIKE '%PHONE SCREEN%'
  );

PRINT '  ~ tahap dipindah ke PHONE_SCREEN: ' + CAST(@@ROWCOUNT AS varchar(10));
GO

/* ═══ 2 · SKRINING TELEPON TIDAK DIJADWALKAN ═════════════════════════════ */

UPDATE dbo.N_WEB_CAREERS_Master_Tipe_Tahap
SET Flag_Jadwal = 'T',
    Updated_At  = SYSDATETIME(),
    Updated_By  = 'SISTEM(alur-phone-screening)'
WHERE Kode = 'PHONE_SCREEN' AND Flag_Jadwal <> 'T';

PRINT '  ~ PHONE_SCREEN.Flag_Jadwal -> T: ' + CAST(@@ROWCOUNT AS varchar(10));
GO

/* ═══ 3 · PERIKSA ════════════════════════════════════════════════════════ */

PRINT '';
PRINT '── AKTIVITAS SKRINING DI SELURUH ALUR ───────────────────────────';

SELECT
    a.Kode              AS Alur,
    t.Urutan            AS UrutTahap,
    t.Label             AS Tahap,
    x.Label             AS Aktivitas,
    COALESCE(x.Tipe_Tahap_Kode, t.Tipe_Tahap_Kode) AS Tipe,
    ISNULL(x.Skrining_Kode, '(belum diikat)')      AS Template_Alur
FROM dbo.N_WEB_CAREERS_Master_Alur_Tahap_Tes x
JOIN dbo.N_WEB_CAREERS_Master_Alur_Tahap t ON t.Id_Master_Alur_Tahap = x.Master_Alur_Tahap_Id
JOIN dbo.N_WEB_CAREERS_Master_Alur a       ON a.Id_Master_Alur       = t.Master_Alur_Id
JOIN dbo.N_WEB_CAREERS_Master_Tipe_Tahap tt
     ON tt.Kode = COALESCE(x.Tipe_Tahap_Kode, t.Tipe_Tahap_Kode)
WHERE tt.Flag_Skrining = 'Y'
ORDER BY a.Kode, t.Urutan, x.Urutan;

PRINT '';
PRINT '── TAHAP YANG NAMANYA SKRINING TAPI TIPENYA BUKAN ───────────────';
PRINT '   (kosong = seluruhnya sudah benar)';

SELECT
    a.Kode  AS Alur,
    t.Label AS Tahap,
    t.Tipe_Tahap_Kode
FROM dbo.N_WEB_CAREERS_Master_Alur_Tahap t
JOIN dbo.N_WEB_CAREERS_Master_Alur a ON a.Id_Master_Alur = t.Master_Alur_Id
JOIN dbo.N_WEB_CAREERS_Master_Tipe_Tahap tt ON tt.Kode = t.Tipe_Tahap_Kode
WHERE tt.Flag_Skrining <> 'Y'
  AND (
        UPPER(REPLACE(t.Label, '_', ' ')) LIKE '%SCREEN%'
     OR UPPER(REPLACE(t.Label, '_', ' ')) LIKE '%SKRINING%'
  );

PRINT '';
PRINT 'Selesai. Jalankan: php artisan cache:clear';
GO
