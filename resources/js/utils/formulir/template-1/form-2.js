/**
 * SKEMA — Form 2: Identitas Peserta (lanjutan, bertahap).
 *
 * Sesuai "Form Identitas Peserta Rekrutmen — Management Trainee EVO Group"
 * pada berkas referensi (sheet "Form 2"), termasuk pembagian Page 1-4.
 * Diisi SETELAH kandidat lolos tahap awal, bukan saat mendaftar.
 *
 *   Page 1  A. Validasi Data Peserta      (prefill dari profil + opsi koreksi)
 *   Page 2  B. Identitas Tambahan + C. Kontak Darurat
 *   Page 3  D. Kesiapan Penempatan & Kerja + E. Kelengkapan Dokumen
 *   Page 4  F. Pernyataan Persetujuan
 *
 * ══════════════════════════════════════════════════════════════════════
 *  DI SINILAH SELURUH ATURAN FORMULIR DITULIS.
 *  Bentuk field & daftar operator syarat: lihat form-1/skema.js.
 * ══════════════════════════════════════════════════════════════════════
 */
/**
 * Pilihan tahun lulus: 2018 sampai 4 tahun ke depan.
 *
 * Dihitung dari tanggal berjalan, bukan daftar tetap — kalau ditulis manual,
 * daftarnya diam-diam basi setiap pergantian tahun. Batas atas +4 memberi ruang
 * bagi mahasiswa tingkat awal yang mengisi "perkiraan lulus".
 */
const TAHUN_LULUS = (() => {
    const kini = new Date().getFullYear();
    const out = [];
    for (let t = kini + 4; t >= 2018; t--) out.push(String(t));
    return out;
})();

/** Syarat yang membuka kunci ketiga data akun di langkah Validasi. */
const BUKA_EDIT = { field: 'data_sesuai', operator: '=', nilai: 'Perlu diperbarui' };

export const SKEMA = {
    template: 'TEMPLATE_1',
    layout: 'BERTAHAP',
    langkah: [
        // ── PAGE 1 ────────────────────────────────────────────────────
        {
            kode: 'VALIDASI',
            judul: 'Validasi Data',
            ikon: 'bi-person-check',
            deskripsi: 'Periksa data yang sudah kami miliki. Beri tahu bila ada yang perlu diperbarui.',
            bagian: [
                {
                    judul: 'A. Validasi Data Peserta',
                    deskripsi: 'Data di bawah terisi otomatis dari akun Anda — tidak perlu diketik ulang.',
                    field: [
                        // Data akun ditampilkan terkunci. Bila kandidat memilih
                        // "Perlu diperbarui", ketiganya TERBUKA untuk disunting
                        // langsung — bukan diketik ulang di kolom bebas. Kolom
                        // bebas memaksa tim menyalin manual dan mudah salah baca;
                        // memperbaiki di tempatnya membuat datanya langsung
                        // terstruktur dan bisa divalidasi.
                        {
                            key: 'v_nama',
                            label: 'Nama Lengkap',
                            tipe: 'prefill',
                            prefill: 'nama',
                            buka_jika: BUKA_EDIT,
                            tipe_buka: 'text',
                            wajib: true,
                        },
                        {
                            key: 'v_email',
                            label: 'Email Terdaftar',
                            tipe: 'prefill',
                            prefill: 'email',
                            buka_jika: BUKA_EDIT,
                            tipe_buka: 'email',
                            wajib: true,
                        },
                        {
                            key: 'v_wa',
                            label: 'No. WhatsApp Terdaftar',
                            tipe: 'prefill',
                            prefill: 'hp',
                            buka_jika: BUKA_EDIT,
                            tipe_buka: 'phone',
                            wajib: true,
                        },
                        {
                            key: 'data_sesuai',
                            label: 'Apakah data di atas sudah sesuai?',
                            tipe: 'radio',
                            wajib: true,
                            opsi: ['Sesuai', 'Perlu diperbarui'],
                            penuh: true,
                            bantuan: 'Pilih "Perlu diperbarui" untuk menyunting data di atas.',
                        },
                    ],
                },
            ],
        },

        // ── PAGE 2 ────────────────────────────────────────────────────
        {
            kode: 'IDENTITAS',
            judul: 'Identitas Tambahan',
            ikon: 'bi-house-heart',
            bagian: [
                {
                    judul: 'B. Identitas Tambahan',
                    field: [
                        { key: 'alamat_ktp', label: 'Alamat Lengkap (Sesuai KTP)', tipe: 'textarea', wajib: true, ph: 'Jalan, RT/RW, kelurahan, kecamatan, kota, provinsi' },
                        { key: 'alamat_domisili', label: 'Alamat Domisili Saat Ini', tipe: 'textarea', wajib: false, ph: 'Kosongkan jika sama dengan alamat KTP', bantuan: 'Tidak perlu diisi bila sama dengan alamat KTP.' },
                        {
                            // TERKUNCI. Kampus sudah dipilih kandidat dari Master
                            // Kampus saat melamar, jadi menanyakannya lagi hanya
                            // membuka peluang dua jawaban berbeda untuk orang yang
                            // sama. Tanpa `buka_jika`, field ini permanen baca-saja.
                            key: 'perguruan_tinggi',
                            label: 'Nama Perguruan Tinggi',
                            tipe: 'prefill',
                            prefill: 'kampus',
                            penuh: true,
                        },
                        {
                            // DISEMAI dari jawaban formulir pendaftaran, sepola
                            // dengan Nama Perguruan Tinggi di atas — kandidat
                            // sudah menjawabnya saat melamar, jadi menanyakannya
                            // dari nol berarti menyuruhnya mengetik ulang
                            // sesuatu yang sudah ada.
                            //
                            // Bedanya: yang ini TIDAK dikunci. "Perkiraan lulus"
                            // memang perkiraan — mahasiswa tingkat akhir kerap
                            // merevisinya — dan tidak semua formulir pendaftaran
                            // menanyakannya, sehingga mengunci akan membuat
                            // sebagian kandidat terjebak pada kolom kosong yang
                            // wajib diisi tapi mustahil diisi.
                            key: 'tahun_lulus',
                            label: 'Tahun Lulus / Perkiraan Lulus',
                            tipe: 'select',
                            prefill: 'tahunLulus',
                            wajib: true,
                            ph: 'Pilih tahun',
                            opsi: TAHUN_LULUS,
                            bantuan: 'Terisi dari data pendaftaran Anda — ubah bila perkiraannya berbeda.',
                        },
                        {
                            key: 'ketersediaan_proses',
                            label: 'Status Ketersediaan Mengikuti Proses Rekrutmen',
                            tipe: 'select',
                            wajib: true,
                            opsi: ['Siap mengikuti seluruh proses', 'Perlu penyesuaian jadwal'],
                            dapat_disaring: true,
                        },
                        {
                            key: 'mulai_bekerja',
                            label: 'Ketersediaan Mulai Bekerja',
                            tipe: 'select',
                            wajib: true,
                            opsi: ['Segera', '1 bulan', '2 bulan', '3 bulan'],
                            dapat_disaring: true,
                        },
                    ],
                },
                {
                    judul: 'C. Kontak Darurat',
                    deskripsi: 'Dihubungi hanya bila terjadi keadaan mendesak selama proses seleksi.',
                    field: [
                        { key: 'darurat_nama', label: 'Nama Kontak Darurat', tipe: 'text', wajib: true },
                        { key: 'darurat_hubungan', label: 'Hubungan dengan Peserta', tipe: 'text', wajib: true, ph: 'mis. Orang tua / Saudara' },
                        {
                            key: 'darurat_hp',
                            label: 'No. Handphone Kontak Darurat',
                            tipe: 'phone',
                            wajib: true,
                            ph: '81234567890',
                            bantuan: 'Pilih kode negara di sebelah kiri. Harus berbeda dari nomor WhatsApp Anda.',
                            beda_dengan: 'v_wa',
                        },
                    ],
                },
            ],
        },

        // ── PAGE 3 ────────────────────────────────────────────────────
        {
            kode: 'KESIAPAN',
            judul: 'Kesiapan & Dokumen',
            ikon: 'bi-clipboard-check',
            bagian: [
                {
                    judul: 'D. Kesiapan Penempatan & Kerja',
                    field: [
                        { key: 'siap_plant', label: 'Bersedia ditempatkan di area Plant / Pabrik', tipe: 'radio', wajib: true, opsi: ['Ya', 'Tidak'], penuh: true, dapat_disaring: true },
                        { key: 'siap_shift', label: 'Bersedia bekerja dengan sistem shift jika dibutuhkan', tipe: 'radio', wajib: true, opsi: ['Ya', 'Tidak'], penuh: true, dapat_disaring: true },
                        { key: 'siap_durasi_mt', label: 'Bersedia mengikuti program Management Trainee sesuai durasi dan ketentuan perusahaan', tipe: 'radio', wajib: true, opsi: ['Ya', 'Tidak'], penuh: true, dapat_disaring: true },
                        { key: 'siap_ikatan_dinas', label: 'Bersedia menjalani ikatan dinas selama 2 tahun jika lulus dari program MT', tipe: 'radio', wajib: true, opsi: ['Ya', 'Tidak'], penuh: true, dapat_disaring: true },
                        { key: 'punya_pengalaman', label: 'Memiliki pengalaman magang/kerja/praktik industri di area produksi/manufaktur', tipe: 'radio', wajib: true, opsi: ['Ya', 'Tidak'], penuh: true, dapat_disaring: true },
                        // Penjelasan hanya diminta bila menjawab Ya.
                        {
                            key: 'pengalaman_uraian',
                            label: 'Jelaskan secara singkat pengalaman tersebut',
                            tipe: 'textarea',
                            wajib: false,
                            ph: 'Nama perusahaan, posisi, durasi, dan tugas utama',
                            tampil_jika: { field: 'punya_pengalaman', operator: '=', nilai: 'Ya' },
                        },
                    ],
                },
                {
                    judul: 'E. Kelengkapan Dokumen',
                    deskripsi: 'Format PDF. Ukuran dibatasi agar penyimpanan tidak cepat penuh.',
                    field: [
                        { key: 'dok_cv', label: 'Upload CV Terbaru', tipe: 'file', wajib: true, accept: '.pdf', maks_mb: 2, penuh: true },
                        { key: 'dok_transkrip', label: 'Upload Transkrip Nilai', tipe: 'file', wajib: true, accept: '.pdf', maks_mb: 2, penuh: true },
                        { key: 'dok_ijazah', label: 'Upload Ijazah / Surat Keterangan Lulus', tipe: 'file', wajib: false, accept: '.pdf', maks_mb: 2, penuh: true },
                        { key: 'dok_sertifikat', label: 'Upload Sertifikat Pendukung (jika ada)', tipe: 'file', wajib: false, accept: '.pdf', maks_mb: 5, penuh: true },
                    ],
                },
            ],
        },

        // ── PAGE 4 ────────────────────────────────────────────────────
        {
            kode: 'PERSETUJUAN',
            judul: 'Pernyataan',
            ikon: 'bi-patch-check',
            deskripsi: 'Baca dan setujui seluruh pernyataan berikut sebelum mengirim.',
            bagian: [
                {
                    judul: 'F. Pernyataan Persetujuan',
                    field: [
                        { key: 'setuju_data_benar', label: 'Saya menyatakan bahwa seluruh data dan dokumen yang saya berikan adalah benar dan dapat dipertanggungjawabkan.', tipe: 'consent', wajib: true },
                        { key: 'setuju_ikut_seleksi', label: 'Saya bersedia mengikuti seluruh tahapan seleksi Management Trainee Production sesuai ketentuan EVO Group.', tipe: 'consent', wajib: true },
                        { key: 'setuju_data_pribadi', label: 'Saya memberikan persetujuan kepada EVO Group untuk menggunakan data pribadi saya hanya untuk keperluan proses rekrutmen dan seleksi karyawan.', tipe: 'consent', wajib: true },
                    ],
                },
            ],
        },
    ],
};

export default SKEMA;
