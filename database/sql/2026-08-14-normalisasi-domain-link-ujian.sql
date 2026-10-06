/* ═══════════════════════════════════════════════════════════════════════════
   WEB CAREER — PERBAIKAN DOMAIN Link_Ujian PADA 9 BARIS PESERTA
   ═══════════════════════════════════════════════════════════════════════════

   MASALAHNYA
   CAT menyusun Link_Ujian memakai alamat DIRINYA SENDIRI. Sembilan baris ini
   dijadwalkan dari mesin yang HCLEARN_ENV-nya masih `development`, sehingga
   yang tertulis adalah `http://cat-evo-pembaharuan.test/...` — domain yang
   tidak akan pernah bisa dijangkau kandidat.

   RUANG LINGKUP
   HANYA sembilan Id_Penjadwalan_Peserta di @Sasaran. Baris lain — termasuk
   yang mungkin punya masalah sama — TIDAK disentuh. Daftar id yang eksplisit
   dipilih di atas penyaringan berdasarkan pola host: yang kedua bisa ikut
   menyapu baris yang belum diperiksa siapa pun.

   YANG DIGANTI DAN YANG TIDAK
   Hanya SKEMA + HOST. Jalur (`/evo/rekrutmen`) dan kredensial di kueri
   (`?wo_aut=…&otp=…`) TIDAK DISENTUH sama sekali — keduanya milik CAT, dan
   menyusun ulang berarti menerbitkan tautan yang bentuknya benar tapi isinya
   salah.

       http://cat-evo-pembaharuan.test/evo/rekrutmen?wo_aut=EVO4GBJU&otp=D5G8KA6K
       └──────────── diganti ────────────┘└──────────── dibiarkan ────────────┘
       https://hclearn.evonusabersaudara.co.id/evo/rekrutmen?wo_aut=EVO4GBJU&otp=D5G8KA6K

   YANG TIDAK DIJANJIKAN SKRIP INI
   Ia memperbaiki ALAMAT, bukan asal-usul token. Token yang diterbitkan
   instance CAT lain tetap tidak akan dikenali di domain ini — tautannya jadi
   rapi tapi tetap ditolak saat dibuka. Itu perkara konfigurasi kanal API
   (HCLEARN_ENV / HCLEARN_WC_API_*), bukan perkara tautan.

   Cerminan PHP-nya: App\Support\Career\TautanUjian::publik().
   Nilai @Dasar harus SAMA dengan config('hclearn.exam_url').
   ═══════════════════════════════════════════════════════════════════════════ */

SET NOCOUNT ON;

DECLARE @Dasar nvarchar(200) = N'https://hclearn.evonusabersaudara.co.id';

-- Sembilan baris yang diminta, dan hanya itu.
DECLARE @Sasaran TABLE (Id int PRIMARY KEY);
INSERT INTO @Sasaran (Id) VALUES (316), (317), (318), (319), (320), (321), (322), (323), (324);


/* ── LANGKAH 1 · Tinjau SEBELUM menulis apa pun ────────────────────────────
   Baris harus tepat 9. Perhatikan kolom Sesudah: bagian `?wo_aut=…&otp=…`
   wajib persis sama dengan kolom Sebelum. Kalau ada satu saja yang berubah,
   JANGAN lanjut. */

SELECT
    p.Id_Penjadwalan_Peserta,
    p.Kode_Peserta,
    p.Nama,
    Sebelum = p.Link_Ujian,
    Sesudah = @Dasar + SUBSTRING(
        p.Link_Ujian,
        CHARINDEX('/', p.Link_Ujian, CHARINDEX('://', p.Link_Ujian) + 3),
        4000
    )
FROM N_WEB_CAREERS_Penjadwalan_Peserta AS p
INNER JOIN @Sasaran AS s ON s.Id = p.Id_Penjadwalan_Peserta
WHERE p.Link_Ujian IS NOT NULL
  -- Tautan tanpa '://' atau tanpa path bukan bentuk yang bisa diurai dengan
  -- aman; ia dilewati, bukan ditebak. Kalau ada yang hilang dari 9 baris ini,
  -- di situlah sebabnya — periksa isinya sebelum menyalahkan skripnya.
  AND CHARINDEX('://', p.Link_Ujian) > 0
  AND CHARINDEX('/', p.Link_Ujian, CHARINDEX('://', p.Link_Ujian) + 3) > 0
ORDER BY p.Id_Penjadwalan_Peserta;


/* ── LANGKAH 2 · Perbaiki, di dalam transaksi ──────────────────────────────
   BEGIN TRAN dijalankan TERPISAH dari COMMIT: periksa dulu hasil LANGKAH 3,
   baru putuskan. Jangan sorot semuanya lalu tekan Execute sekali. */

BEGIN TRAN;

UPDATE p
SET Link_Ujian = @Dasar + SUBSTRING(
        p.Link_Ujian,
        CHARINDEX('/', p.Link_Ujian, CHARINDEX('://', p.Link_Ujian) + 3),
        4000
    )
FROM N_WEB_CAREERS_Penjadwalan_Peserta AS p
INNER JOIN @Sasaran AS s ON s.Id = p.Id_Penjadwalan_Peserta
WHERE p.Link_Ujian IS NOT NULL
  AND CHARINDEX('://', p.Link_Ujian) > 0
  AND CHARINDEX('/', p.Link_Ujian, CHARINDEX('://', p.Link_Ujian) + 3) > 0
  -- Latin1_General_BIN2 supaya perbandingannya PEKA HURUF BESAR-KECIL. Tanpa
  -- ini, collation bawaan menganggap `HTTP://…` sudah sama dengan `https://…`
  -- dan baris yang cuma beda kapitalisasi lolos tanpa diperbaiki.
  AND p.Link_Ujian COLLATE Latin1_General_BIN2
   <> (@Dasar + SUBSTRING(
        p.Link_Ujian,
        CHARINDEX('/', p.Link_Ujian, CHARINDEX('://', p.Link_Ujian) + 3),
        4000
      )) COLLATE Latin1_General_BIN2;

PRINT CONCAT('Baris diperbaiki: ', @@ROWCOUNT);   -- diharapkan 9


/* ── LANGKAH 3 · Buktikan ──────────────────────────────────────────────────
   Sisa harus 0 DAN kesembilan tautan harus terbaca benar. Kalau bukan,
   ROLLBACK. */

SELECT Sisa = COUNT(*)
FROM N_WEB_CAREERS_Penjadwalan_Peserta AS p
INNER JOIN @Sasaran AS s ON s.Id = p.Id_Penjadwalan_Peserta
WHERE p.Link_Ujian IS NOT NULL
  AND p.Link_Ujian COLLATE Latin1_General_BIN2 NOT LIKE (@Dasar + '%') COLLATE Latin1_General_BIN2;

SELECT p.Id_Penjadwalan_Peserta, p.Kode_Peserta, p.Nama, p.Link_Ujian
FROM N_WEB_CAREERS_Penjadwalan_Peserta AS p
INNER JOIN @Sasaran AS s ON s.Id = p.Id_Penjadwalan_Peserta
ORDER BY p.Id_Penjadwalan_Peserta;


/* ── LANGKAH 4 · Pilih salah satu ──────────────────────────────────────────

COMMIT;     -- Sisa = 0, 9 baris diperbaiki, dan tautannya terbaca benar
ROLLBACK;   -- selain itu

   ═══════════════════════════════════════════════════════════════════════════
   AGAR TIDAK TERULANG
   Sumbernya bukan data, melainkan mesin yang menekan Generate. Di .env
   PRODUKSI pastikan HCLEARN_ENV=production. Di .env LOKAL, biarkan
   HCLEARN_EXAM_URL kosong — config/hclearn.php sudah jatuh ke domain produksi
   dengan sendirinya, dan TautanUjian::publik() menambal tautan lama saat
   disajikan sehingga baris yang telanjur salah tidak sampai ke kandidat.
   Skrip ini membereskan yang TERSIMPAN; keduanya saling melengkapi.

   MENCARI YANG LAIN
   Bila suatu saat perlu tahu baris mana lagi yang bermasalah — tanpa mengubah
   apa pun:

       SELECT Host = LEFT(Link_Ujian, CHARINDEX('/', Link_Ujian, CHARINDEX('://', Link_Ujian) + 3) - 1),
              Jumlah = COUNT(*)
       FROM N_WEB_CAREERS_Penjadwalan_Peserta
       WHERE Link_Ujian IS NOT NULL AND CHARINDEX('://', Link_Ujian) > 0
         AND CHARINDEX('/', Link_Ujian, CHARINDEX('://', Link_Ujian) + 3) > 0
       GROUP BY LEFT(Link_Ujian, CHARINDEX('/', Link_Ujian, CHARINDEX('://', Link_Ujian) + 3) - 1)
       ORDER BY Jumlah DESC;
   ═══════════════════════════════════════════════════════════════════════════ */
