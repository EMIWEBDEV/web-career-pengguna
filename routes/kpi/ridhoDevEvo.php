<?php

use App\Http\Controllers\Career\CareerAdminController;
use App\Http\Controllers\Career\Dashboard\DashboardController;
use App\Http\Controllers\Career\Lamaran\FormulirDrafController;
use App\Http\Controllers\Career\Lamaran\LamaranController;
use App\Http\Controllers\Career\Monitoring\MonitoringController;
use App\Http\Controllers\Career\Lamaran\SkriningSesiController;
use App\Http\Controllers\Career\Pemeriksaan\PemeriksaanController;
use App\Http\Controllers\Career\TalentPool\TalentPoolController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| WEB CAREER — Admin Panel & Portal Kandidat  (induk route: developer Ridho)
|--------------------------------------------------------------------------
| KEAMANAN: seluruh grup di bawah WAJIB login (career.auth), dan panel admin
| dibatasi peran ADMIN/SUPERADMIN (career.role). Sebelumnya semua halaman ini
| terbuka tanpa sesi sama sekali.
*/

// ── Admin Panel: operasional & dashboard (prefix /karir) ──
Route::prefix('karir')
    ->middleware(['career.auth', 'career.role:ADMIN,SUPERADMIN'])
    ->name('career.admin.')
    ->group(function () {
        $c = CareerAdminController::class;
        // Dashboard bertab per kategori (Rekrutmen/Magang/MT). SENGAJA tanpa
        // career.permission: ini halaman beranda admin — yang dibatasi adalah
        // kategori yang tampil, ditegakkan di dalam controller lewat
        // AksesService::kategoriDiizinkan('dashboardPage'). Memasang gerbang
        // halaman di sini akan mengunci admin baru dari berandanya sendiri.
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
        // Program Kegiatan & Pembukaan DIPINDAH ke struktur per-modul (routes/career/*).
        // Lowongan lama (/karir/lowongan) DIHAPUS — sumber lowongan kini SATU-satunya
        // dari Monitoring MPP (routes/career/MppLowongan/MppLowonganWeb.php).
        // Seleksi — worklist pelamar ditangani modul Lamaran (mesin syarat + ketuk palu).
        Route::get('/pelamar', [LamaranController::class, 'worklist'])->name('pelamar')->middleware('career.permission:pelamarPage,VIEW');
        // Monitoring Rekrutmen (dulu "Hasil Tes") — pengawasan read-only untuk
        // atasan/super admin: Live View (funnel semua program) + Full Process.
        Route::get('/monitoring', [MonitoringController::class, 'index'])->name('monitoring')->middleware('career.permission:hasilTesPage,VIEW');
        Route::redirect('/hasil-tes', '/karir/monitoring', 301); // URL lama di bookmark/menu tetap hidup
        Route::get('/pengumuman', [$c, 'pengumuman_page'])->name('pengumuman')->middleware('career.permission:pengumumanPage,VIEW');
        // Talent Pool — kolam kandidat bagus yang belum terpakai (diisi dari Worklist).
        Route::get('/talent-pool', [TalentPoolController::class, 'index'])->name('talent-pool')->middleware('career.permission:talentPoolPage,VIEW');
        // Data
        Route::get('/kandidat', [$c, 'kandidat_page'])->name('kandidat')->middleware('career.permission:kandidatPage,VIEW');
    });

// Seluruh master DIPINDAH ke struktur per-modul (routes/career/Master*/*Web.php):
// talent, perilaku, mode, sumber, tipe, kampus, kriteria, kategori, kemitraan,
// formulir, tes, alur, jadwal, akun. (Siklus dihapus — Batch 11.)

// ── WEB CAREER — API data (route WEB, prefix api/v1, dipanggil via axios) ──
// Dropdown memuat seluruh master data, jadi ikut dikunci sebagai milik admin.
Route::prefix('api/v1/karir')
    ->middleware(['career.auth', 'career.role:ADMIN,SUPERADMIN'])
    ->name('career.api.')
    ->group(function () {
        $c = CareerAdminController::class;
        // OPTIONS dropdown (semua dari DB; icons dari bootstrap-icons) — dihit RefSelect/IconPicker
        Route::get('/options/{type}', [$c, 'options'])->name('options');

        // Dashboard — tiga endpoint terpisah supaya halaman terbit cepat dan
        // tiap seksi punya keadaan muat/galat sendiri. Kategori divalidasi di
        // controller terhadap Role_Konten_Access, bukan lewat middleware.
        Route::get('/dashboard/ringkas', [DashboardController::class, 'ringkas'])->name('dashboard.ringkas');
        Route::get('/dashboard/analitik', [DashboardController::class, 'analitik'])->name('dashboard.analitik');
        Route::get('/dashboard/kalender', [DashboardController::class, 'kalender'])->name('dashboard.kalender');
        Route::get('/dashboard/kalender/tes/{id}/peserta', [DashboardController::class, 'pesertaKalender'])->name('dashboard.kalender.peserta');
        Route::get('/dashboard/khas', [DashboardController::class, 'khas'])->name('dashboard.khas');
        // Satu keranjang antrean, satu halaman — cari & paginasi dikerjakan
        // server supaya kotak cari menjangkau seluruh antrean, bukan hanya
        // baris yang kebetulan ikut terkirim di /ringkas.
        Route::get('/dashboard/antrean', [DashboardController::class, 'antrean'])->name('dashboard.antrean');

        // Worklist admin: daftar program (panel kiri) + kanban seleksi (panel kanan) & ketuk palu.
        Route::get('/lamaran/worklist/program', [LamaranController::class, 'worklistProgram'])->name('lamaran.worklist.program')->middleware('career.permission:pelamarPage,VIEW');
        Route::get('/lamaran/worklist/program/{id}', [LamaranController::class, 'worklistDetail'])->name('lamaran.worklist.detail')->middleware('career.permission:pelamarPage,VIEW');
        // Cara kedua membaca populasi yang sama: per JOB VACANCY (MPP), lintas
        // program. Izinnya sama persis dengan mode program — yang berubah cuma
        // cara mengelompokkan, bukan siapa yang boleh melihat.
        //
        // Kunci job vacancy lewat QUERY, bukan segmen URL: nomor MPP boleh
        // memuat garis miring, dan garis miring di tengah path akan dibaca
        // router sebagai pemisah segmen — permintaannya tidak pernah sampai.
        Route::get('/lamaran/worklist/loker', [LamaranController::class, 'worklistLoker'])->name('lamaran.worklist.loker')->middleware('career.permission:pelamarPage,VIEW');
        Route::get('/lamaran/worklist/loker/detail', [LamaranController::class, 'worklistLokerDetail'])->name('lamaran.worklist.loker.detail')->middleware('career.permission:pelamarPage,VIEW');
        Route::get('/lamaran/pengisian/{id}', [LamaranController::class, 'lihatPengisian'])->name('lamaran.pengisian')->middleware('career.permission:pelamarPage,VIEW');
        Route::get('/lamaran/berkas/{id}', [LamaranController::class, 'worklistBerkas'])->name('lamaran.berkas')->middleware('career.permission:pelamarPage,VIEW');
        Route::get('/lamaran/berkas/file/{id}', [LamaranController::class, 'berkasFile'])->name('lamaran.berkas.file')->middleware('career.permission:pelamarPage,VIEW');
        // KIRIM ULANG EMAIL HASIL — pemadam kebakaran, bukan alat keputusan.
        //
        // Izinnya EDIT, bukan APPROVE: yang dilakukan bukan mengetuk palu,
        // melainkan mengirim ulang kabar atas palu yang sudah diketuk. Menuntut
        // APPROVE berarti email yang gagal terkirim menunggu orang yang berhak
        // memutus — padahal keputusannya sendiri sudah selesai.
        Route::get('/lamaran/{id}/email-hasil', [LamaranController::class, 'emailHasilDaftar'])->name('lamaran.email.daftar')->middleware('career.permission:pelamarPage,VIEW');
        Route::post('/lamaran/tahap/{id}/email-hasil', [LamaranController::class, 'emailHasilUlang'])->name('lamaran.email.ulang')->middleware('career.permission:pelamarPage,EDIT');
        Route::patch('/lamaran/tahap/{id}/putus', [LamaranController::class, 'putus'])->name('lamaran.putus')->middleware('career.permission:pelamarPage,APPROVE');
        // KETUK PALU BANYAK SEKALIGUS. Izinnya APPROVE, sama persis dengan
        // putus satuan — yang berubah jumlahnya, bukan wewenangnya. Polanya
        // beda ruas dari '{id}/putus', jadi urutan pendaftaran tidak penting.
        Route::patch('/lamaran/tahap/putus-massal', [LamaranController::class, 'putusMassal'])->name('lamaran.putus.massal')->middleware('career.permission:pelamarPage,APPROVE');
        // KEMAJUAN & PERCOBAAN ULANG GELOMBANG KEPUTUSAN.
        //
        // Progres cuma membaca — izinnya VIEW, supaya panel tetap hidup di layar
        // orang yang boleh melihat papan tapi tidak boleh mengetuk palu.
        // Mengantrekan ulang adalah keputusan itu sendiri, jadi APPROVE.
        Route::get('/lamaran/putus-massal/progres', [LamaranController::class, 'progresMassal'])->name('lamaran.putus.massal.progres')->middleware('career.permission:pelamarPage,VIEW');
        Route::post('/lamaran/putus-massal/ulang', [LamaranController::class, 'ulangMassal'])->name('lamaran.putus.massal.ulang')->middleware('career.permission:pelamarPage,APPROVE');
        // TAHAN / LEPAS (hold) — menunda keputusan tanpa memindahkan kandidat
        // dan tanpa mengirim pemberitahuan apa pun kepadanya. Izinnya EDIT,
        // bukan APPROVE: menahan bukan memutuskan nasib siapa pun.
        Route::patch('/lamaran/tahap/{id}/hold', [LamaranController::class, 'hold'])->name('lamaran.hold')->middleware('career.permission:pelamarPage,EDIT');
        // MENGULANG TAHAP — aksi ULANG, bukan APPROVE.
        // Mengetuk keputusan dan membatalkan keputusan yang sudah diketuk
        // adalah dua kewenangan berbeda; lihat 20-8-2026/2026-08-20-ulang-tahap.sql.
        Route::get('/lamaran/{id}/ulang/tahap', [LamaranController::class, 'ulangDaftarTahap'])->name('lamaran.ulang.tahap')->middleware('career.permission:pelamarPage,ULANG');
        Route::patch('/lamaran/tahap/{id}/ulang', [LamaranController::class, 'ulangTahap'])->name('lamaran.ulang')->middleware('career.permission:pelamarPage,ULANG');
        // TAHAN / LEPAS BANYAK sekaligus. Didaftarkan SEBELUM rute ber-{id} di
        // atas tidak perlu — polanya berbeda ruas ('hold-massal' vs '{id}/hold'),
        // jadi tidak ada yang saling menelan. Izinnya sama persis dengan hold
        // satuan: yang berubah jumlahnya, bukan wewenangnya.
        Route::patch('/lamaran/tahap/hold-massal', [LamaranController::class, 'holdMassal'])->name('lamaran.hold.massal')->middleware('career.permission:pelamarPage,EDIT');
        // Escape hatch multi-tes: tandai sub-tes tidak hadir → mesin evaluasi ulang.
        Route::patch('/lamaran/sub-tes/{id}/tidak-hadir', [LamaranController::class, 'subTesTidakHadir'])->name('lamaran.subtes.tidakhadir')->middleware('career.permission:pelamarPage,EDIT');
        // Catat hasil sub-tes MANUAL (wawancara/FGD di tahap campuran) → mesin yang sama.
        Route::patch('/lamaran/sub-tes/{id}/catat-hasil', [LamaranController::class, 'subTesCatatHasil'])->name('lamaran.subtes.catathasil')->middleware('career.permission:pelamarPage,EDIT');
        // ── PEMERIKSAAN (background / reference check) ──────────────────
        //
        // Terpisah dari catat-hasil dengan sengaja: catat-hasil MENUTUP
        // aktivitas, sedangkan pemeriksaan berlangsung berhari-hari dan tiap
        // potongannya harus bisa disimpan tanpa menutup apa pun. Izinnya sama
        // — yang mencatat temuan adalah orang yang sama yang menutup tahapnya.
        // Membuka isi temuan sensitif — dan mencatat pembukaannya. Izinnya VIEW:
        // membaca temuan bukan menyuntingnya.
        Route::get('/lamaran/sub-tes/{id}/pemeriksaan', [PemeriksaanController::class, 'buka'])->name('lamaran.subtes.pemeriksaan')->middleware('career.permission:pelamarPage,VIEW');
        Route::patch('/lamaran/sub-tes/{id}/persetujuan', [PemeriksaanController::class, 'simpanPersetujuan'])->name('lamaran.subtes.persetujuan')->middleware('career.permission:pelamarPage,EDIT');
        Route::patch('/lamaran/sub-tes/{id}/tanggapan', [PemeriksaanController::class, 'simpanTanggapan'])->name('lamaran.subtes.tanggapan')->middleware('career.permission:pelamarPage,EDIT');
        Route::patch('/lamaran/sub-tes/{id}/verifikasi', [PemeriksaanController::class, 'simpanKomponen'])->name('lamaran.subtes.verifikasi')->middleware('career.permission:pelamarPage,EDIT');
        Route::delete('/lamaran/sub-tes/{id}/verifikasi/{jenisKode}', [PemeriksaanController::class, 'hapusKomponen'])->name('lamaran.subtes.verifikasi.hapus')->middleware('career.permission:pelamarPage,EDIT');
        Route::post('/lamaran/sub-tes/{id}/referensi', [PemeriksaanController::class, 'simpanReferensi'])->name('lamaran.subtes.referensi')->middleware('career.permission:pelamarPage,EDIT');
        Route::delete('/lamaran/sub-tes/{id}/referensi/{refId}', [PemeriksaanController::class, 'hapusReferensi'])->name('lamaran.subtes.referensi.hapus')->middleware('career.permission:pelamarPage,EDIT');

        // ── PHONE SCREENING ─────────────────────────────────────────────
        //
        // Sepola pemeriksaan di atas: panel berdiri sendiri di dalam aktivitas,
        // bukan menempel pada endpoint "catat hasil" yang sekali tembak. Sesi
        // skrining berlangsung selama satu telepon dan sering putus di tengah,
        // jadi tiap potongannya harus bisa disimpan sendiri.
        //
        // Membuka kunci sesi yang sudah selesai memakai APPROVE, bukan EDIT:
        // yang diubahnya adalah angka yang mungkin sudah dipakai memutuskan
        // nasib seseorang, dan itu bukan sekadar menyunting isian.
        Route::get('/lamaran/sub-tes/{id}/skrining', [SkriningSesiController::class, 'show'])->name('lamaran.subtes.skrining')->middleware('career.permission:pelamarPage,VIEW');
        Route::post('/lamaran/sub-tes/{id}/skrining', [SkriningSesiController::class, 'mulai'])->name('lamaran.subtes.skrining.mulai')->middleware('career.permission:pelamarPage,EDIT');
        Route::put('/lamaran/skrining/{id}', [SkriningSesiController::class, 'simpan'])->name('lamaran.skrining.simpan')->middleware('career.permission:pelamarPage,EDIT');
        Route::post('/lamaran/skrining/{id}/selesai', [SkriningSesiController::class, 'selesaikan'])->name('lamaran.skrining.selesai')->middleware('career.permission:pelamarPage,EDIT');
        Route::post('/lamaran/skrining/{id}/buka-kunci', [SkriningSesiController::class, 'bukaKunci'])->name('lamaran.skrining.buka')->middleware('career.permission:pelamarPage,APPROVE');

        // Jadwal wawancara / tes tatap muka + undangan email ke kandidat.
        Route::patch('/lamaran/sub-tes/{id}/jadwal', [LamaranController::class, 'subTesJadwal'])->name('lamaran.subtes.jadwal')->middleware('career.permission:pelamarPage,EDIT');
        // Instruksi bawaan dari Master Alur + riwayat jadwal (jejak) — bahan jendela Atur Jadwal.
        Route::get('/lamaran/sub-tes/{id}/jadwal-info', [LamaranController::class, 'subTesJadwalInfo'])->name('lamaran.subtes.jadwalinfo')->middleware('career.permission:pelamarPage,VIEW');
        // SURAT PENGANTAR (MCU vendor / mandiri): unggah dulu → ref terenkripsi
        // yang dibawa permintaan jadwal satuan maupun massal; lalu dibuka dari
        // worklist lewat aktivitasnya.
        Route::post('/lamaran/jadwal/surat', [LamaranController::class, 'jadwalSurat'])->name('lamaran.jadwal.surat')->middleware('career.permission:pelamarPage,EDIT');
        Route::get('/lamaran/sub-tes/{id}/surat/{urutan?}', [LamaranController::class, 'subTesSurat'])->where('urutan', '[0-9]+')->name('lamaran.subtes.surat')->middleware('career.permission:pelamarPage,VIEW');
        // Perpanjang batas jadwal berrentang tanggal (alasan wajib, kandidat dikabari).
        Route::patch('/lamaran/sub-tes/{id}/perpanjang', [LamaranController::class, 'subTesPerpanjang'])->name('lamaran.subtes.perpanjang')->middleware('career.permission:pelamarPage,EDIT');
        // Kirim ulang surel jadwal — satuan dan massal (maks. BATAS_PUTUS_MASSAL).
        // Yang massal didaftarkan lebih dulu supaya tidak tertelan sebagai {id}.
        Route::post('/lamaran/sub-tes/kirim-ulang-massal', [LamaranController::class, 'kirimUlangMassal'])->name('lamaran.subtes.kirimulangmassal')->middleware('career.permission:pelamarPage,EDIT');
        Route::post('/lamaran/sub-tes/{id}/kirim-ulang', [LamaranController::class, 'subTesKirimUlang'])->name('lamaran.subtes.kirimulang')->middleware('career.permission:pelamarPage,EDIT');
        // JADWAL MASSAL — seratus kandidat sekaligus, serentak atau bergiliran.
        // Didaftarkan sebelum rute ber-{id} agar "jadwal-massal" tidak tertelan
        // sebagai id aktivitas.
        Route::post('/lamaran/sub-tes/jadwal-massal', [LamaranController::class, 'jadwalMassal'])->name('lamaran.subtes.jadwalmassal')->middleware('career.permission:pelamarPage,EDIT');
        // Kehadiran MCU/wawancara — gerbang sebelum hasil boleh dicatat.
        Route::patch('/lamaran/sub-tes/{id}/kehadiran', [LamaranController::class, 'subTesKehadiran'])->name('lamaran.subtes.kehadiran')->middleware('career.permission:pelamarPage,EDIT');
        // Buka aktivitas berikutnya pada tahap berurutan ber-mode MANUAL.
        Route::patch('/lamaran/sub-tes/{id}/lanjutkan', [LamaranController::class, 'subTesLanjutkan'])->name('lamaran.subtes.lanjutkan')->middleware('career.permission:pelamarPage,EDIT');
        // Tarik hasil ujian online dari HCLearn bila webhook-nya tak sampai.
        Route::post('/lamaran/sub-tes/{id}/sinkron', [LamaranController::class, 'subTesSinkron'])->name('lamaran.subtes.sinkron')->middleware('career.permission:pelamarPage,EDIT');

        // Berkas hasil SATU AKTIVITAS (form wawancara terpindai, lembar jawaban
        // tes offline). Berbeda dari berkas tingkat tahap di bawah: pada tahap
        // campuran, berkas yang hanya tahu "milik tahap 4" tak bisa dibedakan
        // antara hasil DISC dan hasil wawancara. Penghapusannya memakai rute
        // berkas tahap — tabelnya sama, dan idnya sudah cukup menunjuk barisnya.
        // Berkas yang DIUNGGAH KANDIDAT untuk sebuah aktivitas — dibuka dari
        // sisi admin. Rute portalnya memeriksa kepemilikan lewat Id_Users, jadi
        // selalu 404 bagi admin; tanpa pintu ini setelan "kandidat wajib
        // mengunggah" di Master Alur tak pernah bisa dibaca tim penilainya.
        // Didaftarkan SEBELUM rute ber-{id} di atasnya supaya "berkas-kandidat"
        // tidak tertelan sebagai id aktivitas.
        Route::get('/lamaran/sub-tes/berkas-kandidat/{id}', [LamaranController::class, 'berkasKandidatFile'])->name('lamaran.berkas.kandidat')->middleware('career.permission:pelamarPage,VIEW');
        Route::get('/lamaran/sub-tes/{id}/berkas', [LamaranController::class, 'subTesBerkas'])->name('lamaran.subtes.berkas')->middleware('career.permission:pelamarPage,VIEW');
        Route::post('/lamaran/sub-tes/{id}/berkas', [LamaranController::class, 'subTesBerkasUnggah'])->name('lamaran.subtes.berkas.unggah')->middleware('career.permission:pelamarPage,EDIT');

        // CETAK LAPORAN KANDIDAT (PDF/Excel) — dikerjakan di antrean.
        // Rute status & unduh didaftarkan SEBELUM yang ber-{id} lamaran supaya
        // "laporan" tidak tertelan sebagai id lamaran.
        Route::get('/lamaran/laporan/{id}', [LamaranController::class, 'laporanStatus'])->name('lamaran.laporan.status')->middleware('career.permission:pelamarPage,VIEW');
        Route::get('/lamaran/laporan/{id}/unduh', [LamaranController::class, 'laporanUnduh'])->name('lamaran.laporan.unduh')->middleware('career.permission:pelamarPage,VIEW');
        Route::get('/lamaran/{id}/laporan/opsi', [LamaranController::class, 'laporanOpsi'])->name('lamaran.laporan.opsi')->middleware('career.permission:pelamarPage,VIEW');
        Route::post('/lamaran/{id}/laporan', [LamaranController::class, 'laporanBuat'])->name('lamaran.laporan.buat')->middleware('career.permission:pelamarPage,VIEW');

        // EXPORT STUDIO — BERKAS SELEKSI KANDIDAT.
        // Status & unduhnya MEMAKAI ULANG rute laporan di atas: keduanya
        // menulis ke N_WEB_CAREERS_Export_Log dan dibedakan Export_Type, jadi
        // laporanStatus/laporanUnduh sudah melayani keduanya apa adanya.
        Route::get('/lamaran/{id}/berkas-seleksi/opsi', [LamaranController::class, 'berkasSeleksiOpsi'])->name('lamaran.berkas.opsi')->middleware('career.permission:pelamarPage,VIEW');
        Route::post('/lamaran/{id}/berkas-seleksi/pratinjau', [LamaranController::class, 'berkasSeleksiPratinjau'])->name('lamaran.berkas.pratinjau')->middleware('career.permission:pelamarPage,VIEW');
        Route::post('/lamaran/{id}/berkas-seleksi/halaman', [LamaranController::class, 'berkasSeleksiHalaman'])->name('lamaran.berkas.halaman')->middleware('career.permission:pelamarPage,VIEW');
        // Pratinjau disajikan INLINE lewat rute sendiri, bukan lewat
        // laporan.unduh: yang terakhir memasang Content-Disposition
        // attachment, dan bingkai pratinjau berakhir kosong.
        Route::get('/berkas-seleksi/pratinjau/{id}', [LamaranController::class, 'berkasSeleksiLihat'])->name('lamaran.berkas.lihat')->middleware('career.permission:pelamarPage,VIEW');
        Route::post('/lamaran/{id}/berkas-seleksi', [LamaranController::class, 'berkasSeleksiBuat'])->name('lamaran.berkas.buat')->middleware('career.permission:pelamarPage,VIEW');

        // Gambar yang ditanam DI DALAM catatan berformat (Quill). Bentuk URL-nya
        // dikunci App\Support\Career\HtmlBersih — mengubah pola rute ini akan
        // membuat seluruh gambar lama dibuang saat catatannya disunting ulang.
        Route::post('/lamaran/catatan/gambar', [LamaranController::class, 'catatanGambarUnggah'])->name('lamaran.catatan.gambar.unggah')->middleware('career.permission:pelamarPage,EDIT');
        Route::get('/lamaran/catatan/gambar/{id}', [LamaranController::class, 'catatanGambar'])->name('lamaran.catatan.gambar')->middleware('career.permission:pelamarPage,VIEW');

        // Berkas hasil tahap (MCU/Interview) — unggah PDF/JPG, daftar, preview, hapus.
        Route::get('/lamaran/tahap/{id}/berkas', [LamaranController::class, 'berkasTahap'])->name('lamaran.tahap.berkas')->middleware('career.permission:pelamarPage,VIEW');
        Route::get('/lamaran/tahap/{id}/detail', [LamaranController::class, 'tahapDetail'])->name('lamaran.tahap.detail')->middleware('career.permission:pelamarPage,VIEW');
        Route::post('/lamaran/tahap/{id}/berkas', [LamaranController::class, 'unggahBerkasTahap'])->name('lamaran.tahap.berkas.unggah')->middleware('career.permission:pelamarPage,EDIT');
        Route::get('/lamaran/tahap/berkas/file/{id}', [LamaranController::class, 'berkasTahapFile'])->name('lamaran.tahap.berkas.file')->middleware('career.permission:pelamarPage,VIEW');
        Route::delete('/lamaran/tahap/berkas/{id}', [LamaranController::class, 'hapusBerkasTahap'])->name('lamaran.tahap.berkas.hapus')->middleware('career.permission:pelamarPage,EDIT');

        // Monitoring Rekrutmen — read-only; permission ikut key lama hasilTesPage.
        Route::get('/monitoring/live', [MonitoringController::class, 'live'])->name('monitoring.live')->middleware('career.permission:hasilTesPage,VIEW');
        // Jejak serah terima PIC lintas program — kendali mutu, read-only.
        Route::get('/monitoring/riwayat-pic', [MonitoringController::class, 'riwayatPic'])->name('monitoring.riwayat.pic')->middleware('career.permission:hasilTesPage,VIEW');
        Route::get('/monitoring/program/{id}/papan', [MonitoringController::class, 'papan'])->name('monitoring.papan')->middleware('career.permission:hasilTesPage,VIEW');
        Route::get('/monitoring/program/{id}/tahap/{urutan}/detail', [MonitoringController::class, 'stageDetail'])->name('monitoring.tahap.detail')->middleware('career.permission:hasilTesPage,VIEW');
        Route::get('/monitoring/pelamar/{id}', [MonitoringController::class, 'detail'])->name('monitoring.pelamar.detail')->middleware('career.permission:hasilTesPage,VIEW');
        // Detail satu tahap milik satu pelamar + berkasnya (offcanvas tumpukan ke-3).
        Route::get('/monitoring/pelamar/{id}/tahap/{urutan}', [MonitoringController::class, 'tahapPelamar'])->name('monitoring.pelamar.tahap')->middleware('career.permission:hasilTesPage,VIEW');
        Route::get('/monitoring/pelamar/{lamaran}/berkas-tahap/{berkas}', [MonitoringController::class, 'berkasTahapFile'])->name('monitoring.berkas.tahap')->middleware('career.permission:hasilTesPage,VIEW');
        Route::get('/monitoring/pelamar/{lamaran}/berkas-formulir/{berkas}', [MonitoringController::class, 'berkasFormulirFile'])->name('monitoring.berkas.formulir')->middleware('career.permission:hasilTesPage,VIEW');

        // Talent Pool — data kartu + kelola status/tag/catatan.
        Route::get('/talent-pool', [TalentPoolController::class, 'list'])->name('talent-pool.list')->middleware('career.permission:talentPoolPage,VIEW');
        Route::get('/talent-pool/export', [TalentPoolController::class, 'export'])->name('talent-pool.export')->middleware('career.permission:talentPoolPage,VIEW');
        // Profil lengkap satu kartu: biodata, kelengkapan berkas, alur proses.
        // Didaftarkan SEBELUM rute ber-{id} lain agar tidak tertelan.
        Route::get('/talent-pool/{id}/detail', [TalentPoolController::class, 'detail'])->name('talent-pool.detail')->middleware('career.permission:talentPoolPage,VIEW');
        // BUKA BERKAS dari halaman Talent Pool — penangannya SAMA PERSIS dengan
        // milik Worklist (signed URL 15 menit ke bucket privat), hanya izinnya
        // yang berbeda: pemegang talentPoolPage belum tentu punya pelamarPage.
        // Dipakai ulang, bukan disalin — dua penyaji berkas berarti dua tempat
        // yang harus sama-sama benar saat aturan aksesnya berubah.
        Route::get('/talent-pool/berkas/file/{id}', [LamaranController::class, 'berkasFile'])->name('talent-pool.berkas.file')->middleware('career.permission:talentPoolPage,VIEW');
        Route::post('/talent-pool/bulk', [TalentPoolController::class, 'bulk'])->name('talent-pool.bulk')->middleware('career.permission:talentPoolPage,EDIT');
        Route::patch('/talent-pool/{id}', [TalentPoolController::class, 'ubah'])->name('talent-pool.ubah')->middleware('career.permission:talentPoolPage,EDIT');
        Route::patch('/talent-pool/{id}/perpanjang', [TalentPoolController::class, 'perpanjang'])->name('talent-pool.perpanjang')->middleware('career.permission:talentPoolPage,EDIT');
        // Tarik ke lowongan (lintas MPP + pilih titik masuk).
        Route::get('/talent-pool/{id}/lowongan', [TalentPoolController::class, 'lowongan'])->name('talent-pool.lowongan')->middleware('career.permission:talentPoolPage,VIEW');
        Route::get('/talent-pool/lowongan/{posisiId}/tahap', [TalentPoolController::class, 'tahapLowongan'])->name('talent-pool.lowongan.tahap')->middleware('career.permission:talentPoolPage,VIEW');
        Route::post('/talent-pool/{id}/tarik', [TalentPoolController::class, 'tarik'])->name('talent-pool.tarik')->middleware('career.permission:talentPoolPage,APPROVE');
        Route::delete('/talent-pool/{id}', [TalentPoolController::class, 'destroy'])->name('talent-pool.destroy')->middleware('career.permission:talentPoolPage,DELETE');

        // CRUD master generik (master/simple, master/rich, master/akun, master/kemitraan)
        // DIHAPUS: halaman gaya lama yang memakainya sudah tidak punya route —
        // seluruh master kini punya modulnya sendiri di routes/career/Master*/.
    });

// ── Portal Kandidat (prefix /kandidat) ──
// Wajib login, TANPA gerbang peran: milik kandidat itu sendiri; admin juga
// boleh menengok untuk melihat tampilan kandidat.
Route::prefix('kandidat')
    ->middleware('career.auth')
    ->name('career.portal.')
    ->group(function () {
        Route::get('/loker', [LamaranController::class, 'loker'])->name('loker')->middleware('career.permission:lokerPage,VIEW');
        Route::get('/portal', [LamaranController::class, 'portalIndex'])->name('index')->middleware('career.permission:portalPage,VIEW');
        Route::get('/lamaran/{id}', [LamaranController::class, 'portalDetail'])->name('detail')->middleware('career.permission:portalPage,VIEW');
        // Pratinjau berkas milik kandidat sendiri (signed URL GCS).
        Route::get('/lamaran/berkas/file/{id}', [LamaranController::class, 'portalBerkasFile'])->name('berkas.file');
        // Berkas HASIL TAHAP (MCU, hasil wawancara) — boleh dilihat kandidat.
        // Nilai tetap ditahan di payload; yang dibuka hanya dokumennya.
        Route::get('/lamaran/tahap/berkas/{id}', [LamaranController::class, 'portalBerkasTahap'])->name('tahap.berkas');

        // ── BERKAS AKTIVITAS yang diunggah KANDIDAT (tes offline dsb.) ──
        // Aturan format & ukuran dibaca dari aktivitasnya sendiri, jadi tiap
        // aktivitas boleh menuntut hal yang berbeda.
        Route::get('/lamaran/tes/{id}/berkas', [LamaranController::class, 'tesBerkas'])->name('tes.berkas');
        Route::post('/lamaran/tes/{id}/berkas', [LamaranController::class, 'tesBerkasUnggah'])->name('tes.berkas.unggah');
        Route::delete('/lamaran/tes/berkas/{id}', [LamaranController::class, 'tesBerkasHapus'])->name('tes.berkas.hapus');
        // Kandidat menyatakan berkasnya SUDAH LENGKAP. Terpisah dari unggah:
        // "mengunggah" dan "selesai mengunggah" bukan hal yang sama, dan tanpa
        // pernyataan ini admin menilai tanpa tahu apakah masih ada susulan.
        Route::patch('/lamaran/tes/{id}/berkas/kirim', [LamaranController::class, 'tesBerkasKirim'])->name('tes.berkas.kirim');
        Route::get('/lamaran/tes/berkas/{id}/file', [LamaranController::class, 'tesBerkasFile'])->name('tes.berkas.file');
        // Surat pengantar jadwal (MCU) milik kandidat sendiri.
        Route::get('/lamaran/tes/{id}/surat/{urutan?}', [LamaranController::class, 'tesSurat'])->where('urutan', '[0-9]+')->name('tes.surat');

        // ── SIMPAN SEMENTARA (DRAF) FORMULIR TAHAP ──
        // Semua endpoint memeriksa kepemilikan tahap lewat Lamaran.Id_Users, jadi
        // id tahap milik orang lain tidak bisa dipakai membaca atau menimpa draf.
        // Berkas draf HANYA dibuka lewat endpoint berkas di bawah (signed URL
        // 15 menit) — front-end tidak pernah menyentuh API storage langsung.
        Route::get('/lamaran/tahap/{id}/draf', [FormulirDrafController::class, 'ambil'])->name('draf.ambil');
        Route::post('/lamaran/tahap/{id}/draf', [FormulirDrafController::class, 'simpan'])->name('draf.simpan');
        Route::post('/lamaran/tahap/{id}/draf/berkas', [FormulirDrafController::class, 'unggahBerkas'])->name('draf.berkas.unggah');
        Route::get('/lamaran/tahap/{id}/draf/berkas/{field}', [FormulirDrafController::class, 'berkas'])->name('draf.berkas');
        // Dipanggil saat kandidat menghapus SATU BARIS bagian berulang. Tanpa
        // ini berkas baris itu menetap di bucket selamanya, dan indeks berkas
        // di atasnya tidak pernah turun.
        Route::delete('/lamaran/tahap/{id}/draf/berkas', [FormulirDrafController::class, 'hapusBerkas'])->name('draf.berkas.hapus');

        // JAWABAN KANDIDAT ATAS PENAWARAN — DICABUT.
        //
        // Kandidat tidak lagi menyatakan "terima" atau "mundur" sendiri lewat
        // portal; keputusan itu dicatat tim di worklist (Keputusan dari
        // Kandidat). Rutenya ikut dihapus, bukan cuma tombolnya disembunyikan:
        // pintu yang masih terbuka tetap bisa diketuk langsung tanpa lewat
        // layar, dan lamaran bisa tertutup oleh permintaan yang tak seorang pun
        // tahu datang dari mana.
    });

// ── Referensi pendidikan untuk formulir (login saja) ──
// Dipakai field ber-tipe `referensi`: jenjang, jenis institusi, kampus, prodi.
// Kandidat memakainya saat mengisi formulir; admin memakainya di pratinjau
// Master Formulir — jadi cukup career.auth, tanpa gerbang peran.
Route::prefix('api/v1/referensi')
    ->middleware('career.auth')
    ->name('career.referensi.')
    ->group(function () {
        Route::get('/{sumber}', [\App\Http\Controllers\Career\Referensi\ReferensiController::class, 'opsi'])
            ->where('sumber', 'jenjang|jenis_institusi|kampus|prodi')
            ->name('opsi');
    });

// ── WEBHOOK hasil tes dari CAT/HCLearn (server-to-server, TANPA login) ──
// Guard: header X-WC-Secret dicocokkan di controller. CAT memanggil ini saat
// tes pihak ke-3 difinalisasi → WC auto gerakkan tahap (lulus/gugur) real-time.
Route::post('/api/v1/webhook/hclearn-hasil', [LamaranController::class, 'hasilUjianCallback'])
    ->name('career.webhook.hclearn-hasil');

// ── MASTER KOLOM SINKRON BIODATA, dibaca CAT (server-to-server, TANPA login) ──
// Guard sama: header X-WC-Secret. CAT memeriksa kolom biodata masuk terhadap
// master ini; masternya cuma ada di database Web Careers, jadi tanpa endpoint
// ini kueri CAT selalu kosong dan SETIAP kolom ditolak "belum terdaftar".
Route::get('/api/v1/webhook/master-sinkron-hris', [\App\Http\Controllers\Career\Integrasi\SinkronHrisController::class, 'index'])
    ->name('career.webhook.master-sinkron-hris');

// ── API Lamaran kandidat (login saja, TANPA gerbang peran admin) ──
// Dipisah dari api/v1/karir yang khusus admin, supaya kandidat bisa melamar
// & mengirim formulir tanpa dianggap admin.
Route::prefix('api/v1/lamaran')
    ->middleware('career.auth')
    ->name('career.lamaran.')
    ->group(function () {
        Route::get('/loker', [LamaranController::class, 'lokerList'])->name('loker')->middleware('career.permission:lokerPage,VIEW');
        Route::post('/', [LamaranController::class, 'lamar'])->name('lamar')->middleware('career.permission:portalPage,CREATE');
        Route::get('/apply-status/{processId}', [LamaranController::class, 'applyStatus'])->name('apply.status');
        Route::post('/tahap/{id}/kirim', [LamaranController::class, 'kirimFormulir'])->name('kirim')->middleware('career.permission:portalPage,EDIT');
        Route::delete('/{id}', [LamaranController::class, 'batalkan'])->name('batal');
    });

// ── BERKAS YANG DITAUTKAN DARI DALAM LAPORAN PDF (tanpa sesi) ──────────────
//
// PDF laporan dibuka di aplikasi pembaca PDF, dan aplikasi itu TIDAK membawa
// cookie sesi. Tautan yang menunjuk rute admin biasa akan mendarat di halaman
// login — tautan yang selalu gagal lebih buruk daripada tidak ada tautan.
//
// Karena itu satu rute tersendiri bertanda tangan Laravel (`signed`): URL-nya
// memuat tanda tangan HMAC dari APP_KEY berikut waktu kedaluwarsanya, jadi ia
// tidak bisa ditebak, tidak bisa disunting, dan mati dengan sendirinya.
// Gerbang kepemilikan tetap ditegakkan di controller — berkas WAJIB milik
// lamaran yang disebut di URL, sehingga satu tautan sah tidak bisa dipelintir
// jadi kunci ke berkas kandidat lain.
Route::get('/karir/laporan/{lamaran}/berkas/{berkas}', [LamaranController::class, 'laporanBerkas'])
    ->middleware('signed')
    ->name('career.laporan.berkas');

// SURAT PENGANTAR JADWAL dari tautan SUREL — alasan yang sama: surel dibuka di
// aplikasi surat tanpa sesi portal. Bertanda tangan & berumur (lihat
// SuratJadwal::tautanEmail); tanpa login, tanpa bisa ditebak.
Route::get('/karir/surat-jadwal/{id}/{urutan?}', [LamaranController::class, 'suratJadwalPublik'])
    ->where('urutan', '[0-9]+')
    ->middleware('signed')
    ->name('career.surat.jadwal');
