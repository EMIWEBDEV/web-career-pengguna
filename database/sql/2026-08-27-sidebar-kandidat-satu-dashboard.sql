/* ══════════════════════════════════════════════════════════════════════════
   WEB CAREERS — SIDEBAR KANDIDAT: SATU DASHBOARD, BUKAN DUA
   ══════════════════════════════════════════════════════════════════════════

   MASALAHNYA

   Portal kandidat menulis "Lamaran Saya — 2 dashboard" lengkap dengan tanda
   panah buka-tutup, padahal kandidat hanya punya SATU dashboard.

   Sebabnya data, bukan kode. LayoutShell mengenali "grup dashboard" dari
   isinya: grup mana pun yang memuat item ber-URL beranda dianggap kumpulan
   dashboard, dan seluruh anggotanya jadi isi collapse-nya. Aturan itu benar
   untuk admin — headernya "Beranda" dan isinya memang Dashboard Utama,
   Dashboard Kandidat, dan Dashboard Feedback.

   Tapi pada menu KANDIDAT, "Cari Lowongan" (/karir/landing-page) terlanjur
   didaftarkan di header yang SAMA dengan berandanya, yaitu "Lamaran Saya".
   Ia karena itu ikut terhitung sebagai dashboard kedua — padahal ia halaman
   daftar lowongan, bukan dashboard. Akibatnya kandidat melihat hitungan yang
   salah, DAN satu-satunya jalan ke Cari Lowongan tersembunyi di balik collapse
   yang tidak ada alasannya untuk ada.

   YANG DIKERJAKAN BERKAS INI

   Memindahkan 'lokerPage' ke headernya sendiri, "Lowongan". Tidak ada menu
   yang dihapus dan tidak ada kolom yang diubah bentuknya — hanya satu nilai
   Nama_Header yang dibetulkan.

   HASILNYA di sidebar kandidat:
     · "Lamaran Saya"  → tautan tunggal, tanpa hitungan, tanpa collapse
     · MODUL ▸ Web Career ▸ Lowongan ▸ Cari Lowongan

   CATATAN: "Profil Saya" (profilPage) SENGAJA TIDAK disentuh di sini. Barisnya
   tetap perlu ada supaya halaman /profil punya tempat di layar Hak Akses;
   yang menyembunyikannya dari sidebar adalah LayoutShell::URL_SUDAH_DI_SHELL,
   sebab tombolnya memang sudah ada di menu profil pada footer sidebar.

   User: FRANS BACHTIAR
   Module: webcareers
   Tag: fix : sidebar kandidat satu dashboard
   Date: 2026-08-27

   SETELAH DIJALANKAN — buang cache menu:
       php artisan tinker --execute="App\Support\CareerShell::lupakanNav();"
       php artisan tinker --execute="App\Support\Career\AksesService::lupakanSemua();"

   TIDAK perlu login ulang. Sebelumnya perlu: susunan menu ikut dibekukan di
   sesi saat login, jadi sesi lama membawa header lama sampai pemiliknya keluar
   dan masuk lagi. Itu diperbaiki bersamaan dengan berkas ini —
   AksesService::segarkanSesi() membaca ulang paket akses (lewat cache 10 menit)
   pada setiap halaman shell, sehingga perubahan di sini langsung terasa begitu
   cache dibuang. Tanpa perbaikan itu, UPDATE di bawah tidak akan terlihat oleh
   kandidat mana pun yang sedang membuka portalnya.
   ══════════════════════════════════════════════════════════════════════════ */

UPDATE N_WEB_CAREERS_Menu
SET Nama_Header = 'Lowongan',
    Updated_At  = GETDATE(),
    Updated_By  = 'SISTEM(sidebar kandidat satu dashboard)'
WHERE Jenis_Page = 'lokerPage'
  AND Untuk_Role = 'KANDIDAT'
  AND Nama_Header = 'Lamaran Saya';
GO

/* Timpaan per akun dari penyusun menu (/hak-akses/susun/{user}) menang atas
   master. Baris yang masih menunjuk header lama dikembalikan ke NULL supaya
   akun itu ikut master lagi — bukan diberi nilai baru, karena "ikut master"
   memang keadaan asalnya. */
UPDATE pa
SET pa.Nama_Header_Custom = NULL,
    pa.Updated_At = GETDATE(),
    pa.Updated_By = 'SISTEM(sidebar kandidat satu dashboard)'
FROM N_WEB_CAREERS_Page_Access pa
JOIN N_WEB_CAREERS_Users u ON u.Id_Users = pa.Id_Users
WHERE pa.Jenis_Page = 'lokerPage'
  AND u.Role = 'KANDIDAT'
  AND pa.Nama_Header_Custom = 'Lamaran Saya';
GO

/* Pemeriksaan: header menu kandidat setelah perubahan. */
SELECT Jenis_Page, Nama_Menu, Nama_Header, Url_Menu, Urutan, Flag_Aktif
FROM N_WEB_CAREERS_Menu
WHERE Untuk_Role = 'KANDIDAT'
ORDER BY Urutan;
GO
