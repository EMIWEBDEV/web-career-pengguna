<?php

// Routes induk milik ridhoDeveloperEvo (folder: career)
// CATATAN: berkas ini di-require di LUAR gerbang admin di routes/web.php, jadi
// tiap modul memasang gerbangnya sendiri (self-gating) — jangan berasumsi
// terlindungi hanya karena berada di sini.
//
// Tiap master baru DITAMBAHKAN sebagai blok sendiri di bawah, bukan menggantikan
// blok yang sudah ada. Berkas ini bentrok tiap kali dua cabang menambah master
// berbarengan, dan penyelesaian yang memilih salah satu sisi diam-diam mencabut
// modul milik cabang lain — halamannya tetap ada, rutenya hilang, dan itu baru
// ketahuan saat ada yang membukanya.
//
// MPP TIDAK LAGI DI SINI. Modulnya dilebur jadi Master MPP dan pindah ke
// routes/career/fransDeveloperDevEvo.php; berkas MppLowonganWeb.php sudah
// dihapus, jadi mengembalikan require-nya di sini akan membuat SELURUH aplikasi
// gagal boot — bukan cuma halaman MPP-nya.

use Illuminate\Support\Facades\Route;

// ── Master Workplace (admin) — tipe lokasi kerja (On-site/Hybrid/Remote) untuk lowongan MPP ──
Route::middleware(['career.auth', 'career.role:ADMIN,SUPERADMIN'])->group(function () {
    require base_path('routes/career/MasterWorkplace/MasterWorkplaceWeb.php');
});
require base_path('routes/career/MasterWorkplace/MasterWorkplaceApi.php');

// ── Master Lokasi Kerja (admin) — kantor pusat & cabang per kota (N_HRIS_Master_Lokasi) ──
Route::middleware(['career.auth', 'career.role:ADMIN,SUPERADMIN'])->group(function () {
    require base_path('routes/career/MasterLokasiKerja/MasterLokasiKerjaWeb.php');
});
require base_path('routes/career/MasterLokasiKerja/MasterLokasiKerjaApi.php');

// ── Master Benefit (admin) — fasilitas & tunjangan yang dipasang pada lowongan MPP ──
Route::middleware(['career.auth', 'career.role:ADMIN,SUPERADMIN'])->group(function () {
    require base_path('routes/career/MasterBenefit/MasterBenefitWeb.php');
});
require base_path('routes/career/MasterBenefit/MasterBenefitApi.php');

// ── Master Skill (admin) — keahlian yang disyaratkan lowongan MPP ──
Route::middleware(['career.auth', 'career.role:ADMIN,SUPERADMIN'])->group(function () {
    require base_path('routes/career/MasterSkill/MasterSkillWeb.php');
});
require base_path('routes/career/MasterSkill/MasterSkillApi.php');
