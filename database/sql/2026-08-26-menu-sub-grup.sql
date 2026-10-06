/* ══════════════════════════════════════════════════════════════════════════
   WEB CAREERS — MENU SIDEBAR BERTINGKAT TIGA
   ══════════════════════════════════════════════════════════════════════════

   MASALAHNYA

   Sidebar admin memuat 50 menu aktif, dan 32 di antaranya tertumpuk di satu
   grup bernama "Master Data". Daftar 32 butir tanpa pengelompokan memaksa
   orang MEMBACA BERURUTAN setiap kali mencari sesuatu, karena tidak ada satu
   pun tanda yang bisa dipakai melompat. Master Template Skrining dan Master
   Pertanyaan Skrining saling berkaitan erat, tapi di layar mereka duduk di
   antara Master SLA MPP dan Master Jenis Verifikasi yang tak berhubungan.

   YANG DIKERJAKAN BERKAS INI

     1. Menambah tiga kolom (dua tabel, nol tabel baru).
     2. Mengisi susunan sub-grup untuk seluruh menu ADMIN.
     3. Menyusun ulang Urutan supaya urutan bacanya benar.
     4. Memindahkan Master Akun ke grup hak akses.

   TIDAK ADA SATU PUN Id_Menu YANG BERUBAH. Seluruh pembaruan menunjuk baris
   lewat Jenis_Page — kunci bisnis yang juga dipakai Page_Access, Role_Menu_
   Access, dan seluruh middleware izin. Menunjuk lewat Id_Menu berarti berkas
   ini hanya benar di satu basis data.

   KENAPA BUKAN Sub_Header YANG SUDAH ADA

   Namanya menjanjikan tingkat kedua, isinya ternyata DESKRIPSI: "Pantau semua
   program & pelamar", "Konten divisi landing page". Enam baris memakainya, dan
   Page_Access punya pasangannya sendiri (Sub_Header_Custom) untuk timpaan per
   akun. Memakai ulang kolom itu akan menghapus enam keterangan tersebut dan
   membuat satu kolom berarti dua hal berbeda tergantung barisnya — kerusakan
   yang tidak memunculkan galat apa pun, ia hanya membuat kolomnya berhenti
   bisa dipercaya.

   KENAPA TIDAK ADA INSERT

   Tidak ada menu baru. Yang berubah cuma penempatan menu yang sudah ada, jadi
   INSERT di sini hanya akan menghasilkan menu ganda saat berkas dijalankan
   ulang. Menu baru tetap ditambahkan lewat halaman Master Menu seperti biasa.

   AMAN DIJALANKAN ULANG. Kolom diperiksa dulu sebelum ditambah, dan seluruh
   UPDATE menetapkan nilai akhir — bukan menambah atau mengurangi dari nilai
   sekarang.

   SESUDAH DIJALANKAN: bersihkan cache menu (5 menit) —
       php artisan cache:clear
   ══════════════════════════════════════════════════════════════════════════ */

SET NOCOUNT ON;
GO

/* ═══ 1 · KOLOM ══════════════════════════════════════════════════════════ */

/* Nama_Grup — sub-grup di dalam Nama_Header.

   NULL berarti menu duduk langsung di bawah headernya, persis seperti
   sekarang. Itu yang membuat kolom ini bisa ditambahkan lebih dulu tanpa
   mengubah apa pun di layar: 50 baris yang sudah ada tetap menggambar sama
   sampai barisnya benar-benar diisi. */
IF COL_LENGTH('dbo.N_WEB_CAREERS_Menu', 'Nama_Grup') IS NULL
BEGIN
    ALTER TABLE dbo.N_WEB_CAREERS_Menu ADD Nama_Grup varchar(60) NULL;
    PRINT '  + N_WEB_CAREERS_Menu.Nama_Grup';
END
ELSE PRINT '  = N_WEB_CAREERS_Menu.Nama_Grup sudah ada';
GO

/* Urutan_Grup — urutan sub-grupnya sendiri.

   Tanpa ini, memindahkan "Phone Screening" ke atas "Konten Publik" menuntut
   penomoran ulang seluruh item di dalam keduanya. */
IF COL_LENGTH('dbo.N_WEB_CAREERS_Menu', 'Urutan_Grup') IS NULL
BEGIN
    ALTER TABLE dbo.N_WEB_CAREERS_Menu ADD Urutan_Grup smallint NULL;
    PRINT '  + N_WEB_CAREERS_Menu.Urutan_Grup';
END
ELSE PRINT '  = N_WEB_CAREERS_Menu.Urutan_Grup sudah ada';
GO

/* Nama_Grup_Custom — timpaan per akun, mengikuti pola Nama_Header_Custom yang
   sudah dipakai penyusun menu (/hak-akses/susun/{user}).

   Tanpa ini, penyusun menu bisa mengubah header tapi tidak sub-grupnya —
   setengah fitur yang justru lebih membingungkan daripada tidak ada. */
IF COL_LENGTH('dbo.N_WEB_CAREERS_Page_Access', 'Nama_Grup_Custom') IS NULL
BEGIN
    ALTER TABLE dbo.N_WEB_CAREERS_Page_Access ADD Nama_Grup_Custom varchar(60) NULL;
    PRINT '  + N_WEB_CAREERS_Page_Access.Nama_Grup_Custom';
END
ELSE PRINT '  = N_WEB_CAREERS_Page_Access.Nama_Grup_Custom sudah ada';
GO

/* ═══ 2 · SUSUNAN ════════════════════════════════════════════════════════

   Urutan dihitung (Urutan_Grup × 100) + posisi, lalu digeser per header:

       Beranda              1 –    9
       Master Data        101 –  699
       Operasional       1001 – 1099
       Seleksi           2101 – 2299
       Data              3001
       Hak Akses & Akun  4001 – 4099

   Header disusun mengikuti baris pertamanya, jadi rentang inilah yang
   menentukan urutan grup besar di sidebar. Renggangnya sengaja: menyisipkan
   menu baru di tengah tidak menuntut penomoran ulang tetangganya. */

DECLARE @susun TABLE (
    Jenis_Page  varchar(60) NOT NULL PRIMARY KEY,
    Nama_Header varchar(60) NOT NULL,
    Nama_Grup   varchar(60) NULL,
    Urutan_Grup smallint    NULL,
    Urutan      int         NOT NULL
);

INSERT INTO @susun (Jenis_Page, Nama_Header, Nama_Grup, Urutan_Grup, Urutan) VALUES
/* ── BERANDA ─────────────────────────────────────────────────────────────
   Tanpa sub-grup: tiga butir sudah terbaca dalam satu tatapan, dan lapis
   tambahan untuk isi sependek itu hanya menambah satu kotak untuk dibuka. */
 ('dashboardPage',            'Beranda',          NULL,                  NULL, 1)
,('hasilTesPage',             'Beranda',          NULL,                  NULL, 2)
,('feedbackDashboardPage',    'Beranda',          NULL,                  NULL, 3)

/* ── MASTER DATA › 1 · Klasifikasi Program ───────────────────────────────
   Dua master ini mendefinisikan REKRUTMEN / MT / INTERNSHIP yang jadi dasar
   hak akses dan hampir seluruh penyaringan di aplikasi. Layak duduk paling
   atas, bukan tercecer di urutan pertama secara kebetulan. */
,('masterTalentPage',         'Master Data',      'Klasifikasi Program', 1, 101)
,('masterKategoriPage',       'Master Data',      'Klasifikasi Program', 1, 102)

/* ── MASTER DATA › 2 · Lowongan & MPP ────────────────────────────────────
   Semua yang dirakit jadi satu iklan lowongan. */
,('masterMppPage',            'Master Data',      'Lowongan & MPP',      2, 201)
,('masterSlaMppPage',         'Master Data',      'Lowongan & MPP',      2, 202)
,('masterBenefitPage',        'Master Data',      'Lowongan & MPP',      2, 203)
,('masterSkillPage',          'Master Data',      'Lowongan & MPP',      2, 204)
,('masterEmploymentPage',     'Master Data',      'Lowongan & MPP',      2, 205)
,('masterExperienceLevelPage','Master Data',      'Lowongan & MPP',      2, 206)
,('masterWorkplacePage',      'Master Data',      'Lowongan & MPP',      2, 207)
,('masterLokasiKerjaPage',    'Master Data',      'Lowongan & MPP',      2, 208)
,('masterLokasiPage',         'Master Data',      'Lowongan & MPP',      2, 209)

/* ── MASTER DATA › 3 · Proses Seleksi ────────────────────────────────────
   Urutannya mengikuti cara orang menyusunnya: alur dulu, lalu isi tiap
   tahap, baru cara tahap itu diputus dan diumumkan.

   Master Perilaku ikut ke sini — isinya menentukan sebuah tahap dijalankan
   manual atau lewat CAT, dan itu perkara tahapan, bukan master berdiri
   sendiri seperti letaknya sekarang. */
,('masterAlurPage',           'Master Data',      'Proses Seleksi',      3, 301)
,('masterTipePage',           'Master Data',      'Proses Seleksi',      3, 302)
,('masterPerilakuPage',       'Master Data',      'Proses Seleksi',      3, 303)
,('masterTesPage',            'Master Data',      'Proses Seleksi',      3, 304)
,('masterFormulirPage',       'Master Data',      'Proses Seleksi',      3, 305)
,('masterJenisVerifikasiPage','Master Data',      'Proses Seleksi',      3, 306)
,('masterJadwalPage',         'Master Data',      'Proses Seleksi',      3, 307)
,('masterModePage',           'Master Data',      'Proses Seleksi',      3, 308)
,('masterModeKeputusanPage',  'Master Data',      'Proses Seleksi',      3, 309)
,('masterModePengumumanPage', 'Master Data',      'Proses Seleksi',      3, 310)

/* ── MASTER DATA › 4 · Phone Screening ───────────────────────────────────
   Template menarik isinya dari pustaka pertanyaan; keduanya tidak berguna
   sendirian. Sebelumnya mereka terpisah oleh master yang tak berhubungan. */
,('masterSkriningPage',       'Master Data',      'Phone Screening',     4, 401)
,('masterPertanyaanPage',     'Master Data',      'Phone Screening',     4, 402)

/* ── MASTER DATA › 5 · Sumber Kandidat ───────────────────────────────────
   Dari mana pelamar datang, dan dari institusi seperti apa. */
,('masterSumberPage',         'Master Data',      'Sumber Kandidat',     5, 501)
,('masterKampusPage',         'Master Data',      'Sumber Kandidat',     5, 502)
,('masterKemitraanPage',      'Master Data',      'Sumber Kandidat',     5, 503)
,('masterJenjangPage',        'Master Data',      'Sumber Kandidat',     5, 504)
,('masterJenisInstitusiPage', 'Master Data',      'Sumber Kandidat',     5, 505)

/* ── MASTER DATA › 6 · Konten Publik ─────────────────────────────────────
   Satu-satunya sub-grup yang isinya dilihat PELAMAR, bukan rekruter. */
,('masterHeroPage',           'Master Data',      'Konten Publik',       6, 601)
,('masterInfoDivisiPage',     'Master Data',      'Konten Publik',       6, 602)
,('masterFaqPage',            'Master Data',      'Konten Publik',       6, 603)
,('masterFeedbackPage',       'Master Data',      'Konten Publik',       6, 604)

/* ── OPERASIONAL ─────────────────────────────────────────────────────────
   Dua butir; sub-grup tidak menambah apa pun. */
,('programPage',              'Operasional',      NULL,                  NULL, 1001)
,('pembukaanPage',            'Operasional',      NULL,                  NULL, 1002)

/* ── SELEKSI › 1 · Proses Berjalan ───────────────────────────────────────
   Tiga layar yang dipakai setiap hari, urut mengikuti perjalanan pelamar:
   diperiksa, dijadwalkan, diumumkan. */
,('pelamarPage',              'Seleksi',          'Proses Berjalan',     1, 2101)
,('penjadwalanPage',          'Seleksi',          'Proses Berjalan',     1, 2102)
,('pengumumanPage',           'Seleksi',          'Proses Berjalan',     1, 2103)

/* ── SELEKSI › 2 · Talent Pool ───────────────────────────────────────────
   Masa Berlaku adalah SETELAN milik Talent Pool, bukan tahap seleksi
   tersendiri. Sekarang ia duduk sejajar dengan Worklist Pelamar seolah
   setara — dan orang mencarinya di dalam Talent Pool, bukan di sebelahnya. */
,('talentPoolPage',           'Seleksi',          'Talent Pool',         2, 2201)
,('masterMasaTalentPoolPage', 'Seleksi',          'Talent Pool',         2, 2202)

/* ── DATA ────────────────────────────────────────────────────────────────
   Dibiarkan berdiri sendiri. Ia memang satu menu dalam satu grup, tapi
   apakah "Kandidat" bagian dari alur seleksi atau data tersendiri adalah
   keputusan tim, bukan keputusan berkas ini. */
,('kandidatPage',             'Data',             NULL,                  NULL, 3001)

/* ── HAK AKSES & AKUN ────────────────────────────────────────────────────
   Master Akun pindah ke sini dari grup "Pengaturan" yang isinya cuma satu
   menu ini — dan grup berisi satu butir hanyalah butir itu sendiri dengan
   tambahan judul. Lagi pula di sinilah orang mencarinya: membuat akun dan
   memberi aksesnya adalah satu pekerjaan yang sama.

   Urutannya mengikuti cara kerja: buat akunnya, daftarkan menunya, beri
   izinnya, lalu batasi kategorinya. */
,('masterAkunPage',           'Hak Akses & Akun', NULL,                  NULL, 4001)
,('masterMenuPage',           'Hak Akses & Akun', NULL,                  NULL, 4002)
,('hakAksesPage',             'Hak Akses & Akun', NULL,                  NULL, 4003)
,('klasifikasiAksesPage',     'Hak Akses & Akun', NULL,                  NULL, 4004)

/* ── KANDIDAT: SENGAJA TIDAK DISENTUH ────────────────────────────────────
   Tiga menu dalam dua grup — sudah terbaca tanpa tingkat tambahan, jadi
   tidak ada sub-grup yang perlu dipasang.

   Dulu ketiganya ikut ditulis di sini "supaya berkas ini menyatakan seluruh
   susunan". Itu keliru, dan keliru dengan cara yang mahal: berkas
   2026-08-27-sidebar-kandidat-satu-dashboard.sql memindahkan lokerPage ke
   header "Lowongan", dan menjalankan ULANG berkas ini akan menariknya balik
   ke "Lamaran Saya" — membatalkan perbaikan itu tanpa satu pun galat.

   Satu baris hanya boleh punya satu berkas yang mengurusnya. */
;

/* Terapkan. Menunjuk lewat Jenis_Page, dan HANYA menyentuh baris yang
   susunannya memang berbeda — supaya Updated_At tidak berubah pada baris
   yang sebetulnya tidak diapa-apakan saat berkas ini dijalankan ulang. */
UPDATE m
SET m.Nama_Header  = s.Nama_Header,
    m.Nama_Grup    = s.Nama_Grup,
    m.Urutan_Grup  = s.Urutan_Grup,
    m.Urutan       = s.Urutan,
    m.Updated_At   = SYSDATETIME(),
    m.Updated_By   = 'SISTEM(menu-sub-grup)'
FROM dbo.N_WEB_CAREERS_Menu m
JOIN @susun s ON s.Jenis_Page = m.Jenis_Page
WHERE  m.Nama_Header <> s.Nama_Header
    OR ISNULL(m.Nama_Grup, '~')   <> ISNULL(s.Nama_Grup, '~')
    OR ISNULL(m.Urutan_Grup, -1)  <> ISNULL(s.Urutan_Grup, -1)
    OR ISNULL(m.Urutan, -1)       <> s.Urutan;

PRINT '  ~ menu disusun ulang: ' + CAST(@@ROWCOUNT AS varchar(10));
GO

/* ═══ 3 · TIMPAAN PER AKUN YANG SUDAH TERLANJUR ══════════════════════════

   Penyusun menu per akun menyimpan Nama_Header_Custom. Akun yang pernah
   disusun manual dan menyimpan 'Pengaturan' akan tetap melihat Master Akun
   di grup lama — timpaannya menang atas master.

   Yang dikosongkan HANYA yang isinya persis nama header lama. Timpaan yang
   benar-benar disengaja (orang menamainya sendiri) tidak disentuh: NULL
   berarti "ikut master", jadi mengosongkannya mengembalikan baris itu ke
   perilaku bawaan, bukan menghapus pilihan orang. */
UPDATE pa
SET pa.Nama_Header_Custom = NULL
FROM dbo.N_WEB_CAREERS_Page_Access pa
WHERE pa.Jenis_Page IN ('masterAkunPage', 'masterMenuPage', 'hakAksesPage', 'klasifikasiAksesPage')
  AND pa.Nama_Header_Custom IN ('Pengaturan', 'Hak Akses');

PRINT '  ~ timpaan header lama dilepas: ' + CAST(@@ROWCOUNT AS varchar(10));
GO

/* ═══ 4 · PERIKSA ════════════════════════════════════════════════════════ */

PRINT '';
PRINT '── SUSUNAN ADMIN ────────────────────────────────────────────────';

SELECT
    Urutan,
    Nama_Header,
    ISNULL(Nama_Grup, '·') AS Sub_Grup,
    Nama_Menu,
    Url_Menu
FROM dbo.N_WEB_CAREERS_Menu
WHERE Untuk_Role = 'ADMIN' AND Flag_Aktif = 'Y'
ORDER BY Urutan;

PRINT '';
PRINT '── JUMLAH PER GRUP ──────────────────────────────────────────────';

SELECT
    MIN(Urutan)                 AS Mulai,
    Nama_Header,
    ISNULL(Nama_Grup, '·')      AS Sub_Grup,
    COUNT(*)                    AS Jml
FROM dbo.N_WEB_CAREERS_Menu
WHERE Untuk_Role = 'ADMIN' AND Flag_Aktif = 'Y'
GROUP BY Nama_Header, Nama_Grup
ORDER BY MIN(Urutan);

PRINT '';
PRINT '── YANG PERLU DIPERHATIKAN ──────────────────────────────────────';

/* Menu ADMIN yang belum kebagian susunan. Kosong = seluruhnya sudah tertata;
   berisi = ada menu yang ditambahkan sesudah berkas ini ditulis, dan ia akan
   tampil tanpa sub-grup sampai diberi tempat lewat halaman Master Menu. */
SELECT Id_Menu, Jenis_Page, Nama_Menu, Nama_Header, Urutan
FROM dbo.N_WEB_CAREERS_Menu m
WHERE m.Untuk_Role = 'ADMIN'
  AND m.Flag_Aktif = 'Y'
  AND m.Nama_Grup IS NULL
  AND m.Nama_Header NOT IN ('Beranda', 'Operasional', 'Data', 'Hak Akses & Akun')
ORDER BY m.Urutan;

/* Urutan kembar di dalam satu header — dua menu bernomor sama berpindah
   tempat sendiri antar pemuatan, dan orang mengira menunya bergerak. */
SELECT Nama_Header, Urutan, COUNT(*) AS Kembar
FROM dbo.N_WEB_CAREERS_Menu
WHERE Untuk_Role = 'ADMIN' AND Flag_Aktif = 'Y'
GROUP BY Nama_Header, Urutan
HAVING COUNT(*) > 1;

/* Grup lama yang seharusnya sudah kosong. */
SELECT Nama_Header, COUNT(*) AS Sisa
FROM dbo.N_WEB_CAREERS_Menu
WHERE Untuk_Role = 'ADMIN' AND Flag_Aktif = 'Y' AND Nama_Header = 'Pengaturan'
GROUP BY Nama_Header;

PRINT '';
PRINT 'Selesai. Jalankan: php artisan cache:clear';
GO
