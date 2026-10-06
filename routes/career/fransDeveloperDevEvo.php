<?php

// Routes induk milik fransDeveloper (folder: career)

// MasterSiklus DIHAPUS (Batch 11) — siklus tidak menggerakkan logika apa pun.
// Hak akses (RBAC): master menu, manajemen hak akses, akses klasifikasi akun.
require base_path('routes/career/Akses/AksesWeb.php');
require base_path('routes/career/MasterTalent/MasterTalentWeb.php');
require base_path('routes/career/MasterTalent/MasterTalentApi.php');
require base_path('routes/career/MasterPerilaku/MasterPerilakuWeb.php');
require base_path('routes/career/MasterPerilaku/MasterPerilakuApi.php');
require base_path('routes/career/MasterModePengumuman/MasterModePengumumanWeb.php');
require base_path('routes/career/MasterModePengumuman/MasterModePengumumanApi.php');
require base_path('routes/career/MasterModeKeputusan/MasterModeKeputusanWeb.php');
require base_path('routes/career/MasterModeKeputusan/MasterModeKeputusanApi.php');
require base_path('routes/career/MasterMode/MasterModeWeb.php');
require base_path('routes/career/MasterMode/MasterModeApi.php');
require base_path('routes/career/MasterSumber/MasterSumberWeb.php');
require base_path('routes/career/MasterSumber/MasterSumberApi.php');
require base_path('routes/career/MasterTipe/MasterTipeWeb.php');
require base_path('routes/career/MasterTipe/MasterTipeApi.php');
require base_path('routes/career/MasterKampus/MasterKampusWeb.php');
require base_path('routes/career/MasterLokasi/MasterLokasiWeb.php');
require base_path('routes/career/MasterKampus/MasterKampusApi.php');
require base_path('routes/career/MasterJenjang/MasterJenjangWeb.php');
require base_path('routes/career/MasterJenisInstitusi/MasterJenisInstitusiWeb.php');
require base_path('routes/career/MasterKategori/MasterKategoriWeb.php');
require base_path('routes/career/MasterKategori/MasterKategoriApi.php');
require base_path('routes/career/MasterKemitraan/MasterKemitraanWeb.php');
require base_path('routes/career/MasterKemitraan/MasterKemitraanApi.php');
require base_path('routes/career/MasterJadwal/MasterJadwalWeb.php');
require base_path('routes/career/MasterJadwal/MasterJadwalApi.php');
require base_path('routes/career/MasterTes/MasterTesWeb.php');
require base_path('routes/career/MasterTes/MasterTesApi.php');
require base_path('routes/career/MasterFormulir/MasterFormulirWeb.php');
require base_path('routes/career/MasterFormulir/MasterFormulirApi.php');
require base_path('routes/career/MasterAlur/MasterAlurWeb.php');
require base_path('routes/career/MasterAlur/MasterAlurApi.php');
require base_path('routes/career/MasterAkun/MasterAkunWeb.php');
require base_path('routes/career/MasterAkun/MasterAkunApi.php');
require base_path('routes/career/ProgramKegiatan/ProgramKegiatanWeb.php');
require base_path('routes/career/ProgramKegiatan/ProgramKegiatanApi.php');
require base_path('routes/career/PembukaanProgram/PembukaanProgramWeb.php');
require base_path('routes/career/Penjadwalan/PenjadwalanWeb.php');
require base_path('routes/career/Penjadwalan/PenjadwalanApi.php');
require base_path('routes/career/MasterMasaTalentPool/MasterMasaTalentPoolWeb.php');
require base_path('routes/career/MasterHero/MasterHeroWeb.php');
require base_path('routes/career/MasterEmployment/MasterEmploymentWeb.php');
require base_path('routes/career/MasterExperienceLevel/MasterExperienceLevelWeb.php');
require base_path('routes/career/MasterMpp/MasterMppWeb.php');
require base_path('routes/career/MasterSlaMpp/MasterSlaMppWeb.php');
require base_path('routes/career/MasterJenisVerifikasi/MasterJenisVerifikasiWeb.php');
require base_path('routes/career/MasterSkrining/MasterSkriningWeb.php');
require base_path('routes/career/MasterPertanyaan/MasterPertanyaanWeb.php');
// Pemulihan berkas kandidat yang hilang (CV, KK, sertifikat…) oleh admin.
require base_path('routes/career/PemulihanBerkas/PemulihanBerkasWeb.php');
// Batas pengisian formulir tahap (tanggal kolom & perpanjangan kandidat).
require base_path('routes/career/BatasIsi/BatasIsiWeb.php');
// Agenda Seleksi — konfirmasi kehadiran, permintaan jadwal lain, pengingat manual.
require base_path('routes/career/AgendaSeleksi/AgendaSeleksiWeb.php');
