/* =====================================================================
   WEB CAREER — Formulir Rekrutmen & Magang + penyambungan ke alur.

   KONTEKS
   Master pendidikan (Master_Jenjang, Master_Jenis_Institusi,
   Jenis_Institusi_Jenjang, Master_Kampus, Master_Prodi, Prodi_Jenjang)
   sudah terisi. Skrip ini TIDAK menyentuh tabel-tabel itu — hanya
   mendaftarkan dua formulir baru dan menyambungkannya ke tahap
   pendaftaran alur Rekrutmen & Magang, yang selama ini kosong.

   Pertanyaan formulir TIDAK disimpan di database. Ia hidup di
   resources/js/components/career/formulir/template-1/form-3 & form-4;
   yang tersimpan di sini hanya KODE komponennya.

   AMAN DIJALANKAN ULANG: semua perintah dijaga IF NOT EXISTS / WHERE.
   ===================================================================== */

SET NOCOUNT ON;

DECLARE @now DATETIME = GETDATE();
DECLARE @oleh NVARCHAR(100) = N'SISTEM';

/* ── 1. Katalog formulir ────────────────────────────────────────── */

IF NOT EXISTS (SELECT 1 FROM N_WEB_CAREERS_Master_Formulir WHERE Kode = 'FRM-DAFTAR-REKRUTMEN')
BEGIN
    INSERT INTO N_WEB_CAREERS_Master_Formulir
        (Kode, Nama, Kategori, Deskripsi, Komponen_Kode, Petunjuk, Flag_Aktif,
         Created_At, Created_By, Updated_At, Updated_By)
    VALUES
        ('FRM-DAFTAR-REKRUTMEN',
         N'Formulir Pendaftaran Rekrutmen',
         'REKRUTMEN',
         N'Formulir gerbang rekrutmen umum: data diri, pendidikan terakhir, riwayat pekerjaan, kesediaan & ekspektasi.',
         'FORMULIR_3',
         N'Terbuka untuk semua jenjang (SD sampai S3). Jenjang, jenis institusi, kampus/sekolah, dan jurusan dipilih berantai dari master pendidikan.',
         'Y', @now, @oleh, @now, @oleh);
END

IF NOT EXISTS (SELECT 1 FROM N_WEB_CAREERS_Master_Formulir WHERE Kode = 'FRM-DAFTAR-MAGANG')
BEGIN
    INSERT INTO N_WEB_CAREERS_Master_Formulir
        (Kode, Nama, Kategori, Deskripsi, Komponen_Kode, Petunjuk, Flag_Aktif,
         Created_At, Created_By, Updated_At, Updated_By)
    VALUES
        ('FRM-DAFTAR-MAGANG',
         N'Formulir Pendaftaran Magang',
         'INTERNSHIP',
         N'Formulir gerbang magang: data diri & pendidikan, skema + periode magang, pembimbing kampus, surat pengantar & pernyataan.',
         'FORMULIR_4',
         N'Tiga langkah. Blok pembimbing kampus otomatis disembunyikan bila skema yang dipilih Magang Mandiri.',
         'Y', @now, @oleh, @now, @oleh);
END

/* ── 2. Sambungkan ke tahap PENDAFTARAN masing-masing alur ───────
   Tahap #1 ketiga alur ini bertipe FORM dan berjudul "Pendaftaran &
   Berkas", tetapi Formulir_Kode-nya NULL — artinya tahap pendaftaran
   selama ini tidak menuntut isian apa pun. Diisi di sini.

   Hanya baris yang masih NULL yang disentuh: alur yang sudah dipasangi
   formulir lain oleh admin tidak ditimpa.                            */

UPDATE at
SET at.Formulir_Kode = 'FRM-DAFTAR-REKRUTMEN',
    at.Updated_At = @now,
    at.Updated_By = @oleh
FROM N_WEB_CAREERS_Master_Alur_Tahap at
JOIN N_WEB_CAREERS_Master_Alur a ON a.Id_Master_Alur = at.Master_Alur_Id
WHERE a.Kategori = 'REKRUTMEN'
  AND at.Tipe_Tahap_Kode = 'FORM'
  AND at.Formulir_Kode IS NULL;

UPDATE at
SET at.Formulir_Kode = 'FRM-DAFTAR-MAGANG',
    at.Updated_At = @now,
    at.Updated_By = @oleh
FROM N_WEB_CAREERS_Master_Alur_Tahap at
JOIN N_WEB_CAREERS_Master_Alur a ON a.Id_Master_Alur = at.Master_Alur_Id
WHERE a.Kategori = 'INTERNSHIP'
  AND at.Tipe_Tahap_Kode = 'FORM'
  AND at.Formulir_Kode IS NULL;

/* ── 2b. Alur MT bawaan ─────────────────────────────────────────
   ALR-MT punya lubang yang sama: tahap #1 "Pendaftaran & Berkas" dan
   tahap #4 "Lengkapi Biodata" bertipe FORM tapi tanpa Formulir_Kode,
   padahal formulir MT-nya sudah lama ada. Alur MT racikan admin
   (SELEKSI_MT_2026 dsb.) sudah menunjuk ke sana — yang bawaan belum.

   Tahap FORM PERTAMA dapat formulir pendaftaran, sisanya formulir
   identitas lanjutan.                                                */

;WITH urut AS (
    SELECT at.Id_Master_Alur_Tahap,
           ROW_NUMBER() OVER (PARTITION BY at.Master_Alur_Id ORDER BY at.Urutan) AS ke
    FROM N_WEB_CAREERS_Master_Alur_Tahap at
    JOIN N_WEB_CAREERS_Master_Alur a ON a.Id_Master_Alur = at.Master_Alur_Id
    WHERE a.Kategori = 'MT' AND at.Tipe_Tahap_Kode = 'FORM' AND at.Formulir_Kode IS NULL
)
UPDATE at
SET at.Formulir_Kode = CASE WHEN u.ke = 1 THEN 'FRM-DAFTAR-MT' ELSE 'FRM-IDENTITAS-MT' END,
    at.Updated_At = @now,
    at.Updated_By = @oleh
FROM N_WEB_CAREERS_Master_Alur_Tahap at
JOIN urut u ON u.Id_Master_Alur_Tahap = at.Id_Master_Alur_Tahap;

/* ── 3. Periksa hasil ───────────────────────────────────────────── */

SELECT Kode, Nama, Kategori, Komponen_Kode, Flag_Aktif
FROM N_WEB_CAREERS_Master_Formulir
ORDER BY Id_Master_Formulir;

SELECT a.Kode AS Alur, a.Kategori, at.Urutan, at.Label, at.Tipe_Tahap_Kode, at.Formulir_Kode
FROM N_WEB_CAREERS_Master_Alur_Tahap at
JOIN N_WEB_CAREERS_Master_Alur a ON a.Id_Master_Alur = at.Master_Alur_Id
WHERE at.Formulir_Kode IS NOT NULL
ORDER BY a.Kode, at.Urutan;
