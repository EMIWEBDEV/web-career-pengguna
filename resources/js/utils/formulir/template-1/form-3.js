/**
 * SKEMA — Form 3: Pendaftaran Rekrutmen Umum (gerbang saat melamar).
 *
 * Bedanya dengan Form 1 (MT) bukan pada data diri atau pendidikan — keduanya
 * memakai blok yang sama dari inti/blok.js. Yang membedakan: rekrutmen umum
 * terbuka untuk semua jenjang (SMA/SMK sampai S2) dan yang ditimbang adalah
 * PENGALAMAN KERJA, bukan potensi kaderisasi.
 *
 * ══════════════════════════════════════════════════════════════════════
 *  DI SINILAH SELURUH ATURAN FORMULIR DITULIS.
 *  Bentuk field & daftar operator syarat: lihat form-1/skema.js.
 * ══════════════════════════════════════════════════════════════════════
 */
import { blokDataDiri, blokPendidikan } from '@utils/formulir/blok';

export const SKEMA = {
    template: 'TEMPLATE_1',
    layout: 'SATU_HALAMAN',
    langkah: [
        {
            kode: 'PENDAFTARAN',
            judul: 'Data Pendaftaran',
            ikon: 'bi-briefcase',
            bagian: [
                {
                    judul: 'A. Data Diri',
                    field: blokDataDiri({ tanyaDomisili: true }),
                },
                {
                    judul: 'B. Pendidikan Terakhir',
                    deskripsi: 'Pilih jenjang lebih dulu — institusi dan jurusan menyesuaikan.',
                    field: blokPendidikan({
                        judulKampus: 'Nama Kampus / Sekolah',
                        // Rekrutmen umum menerima lulusan lama; menuntut semester
                        // saat ini tidak masuk akal bagi mereka.
                        tanyaKemahasiswaan: false,
                    }),
                },
                {
                    judul: 'C. Pengalaman Kerja',
                    field: [
                        {
                            key: 'punya_pengalaman',
                            label: 'Sudah pernah bekerja?',
                            tipe: 'radio',
                            wajib: true,
                            opsi: ['Belum pernah', 'Sudah pernah'],
                            penuh: true,
                            dapat_disaring: true,
                        },
                        {
                            key: 'total_pengalaman_tahun',
                            label: 'Total Pengalaman Kerja (tahun)',
                            tipe: 'number',
                            wajib: true,
                            min: 0,
                            maks: 50,
                            desimal: 1,
                            ph: 'mis. 2.5',
                            dapat_disaring: true,
                            tampil_jika: { field: 'punya_pengalaman', operator: '=', nilai: 'Sudah pernah' },
                        },
                    ],
                },
                {
                    // Seluruh blok disembunyikan bagi yang belum pernah bekerja —
                    // bukan cuma fieldnya, supaya judul kosong tidak ikut muncul.
                    judul: 'D. Riwayat Pekerjaan',
                    deskripsi: 'Cukup tiga posisi terakhir, dimulai dari yang paling baru.',
                    key: 'riwayat_kerja',
                    berulang: true,
                    maks_baris: 3,
                    tampil_jika: { field: 'punya_pengalaman', operator: '=', nilai: 'Sudah pernah' },
                    field: [
                        { key: 'perusahaan', label: 'Nama Perusahaan', tipe: 'text', wajib: true },
                        { key: 'posisi', label: 'Posisi / Jabatan', tipe: 'text', wajib: true },
                        { key: 'mulai', label: 'Mulai', tipe: 'date', wajib: true },
                        { key: 'selesai', label: 'Selesai', tipe: 'date', wajib: false, bantuan: 'Kosongkan bila masih bekerja di sini.' },
                        { key: 'uraian', label: 'Tugas Utama', tipe: 'textarea', wajib: false, ph: 'Dua sampai tiga kalimat saja' },
                    ],
                },
                {
                    judul: 'E. Kesediaan & Ekspektasi',
                    field: [
                        {
                            key: 'bersedia_ditempatkan',
                            label: 'Bersedia ditempatkan di area Plant / Pabrik',
                            tipe: 'radio',
                            wajib: true,
                            opsi: ['Ya', 'Tidak'],
                            penuh: true,
                            dapat_disaring: true,
                        },
                        {
                            key: 'siap_shift',
                            label: 'Bersedia bekerja dengan sistem shift bila dibutuhkan',
                            tipe: 'radio',
                            wajib: true,
                            opsi: ['Ya', 'Tidak'],
                            penuh: true,
                            dapat_disaring: true,
                        },
                        {
                            key: 'mulai_bekerja',
                            label: 'Kesiapan Mulai Bekerja',
                            tipe: 'select',
                            wajib: true,
                            opsi: ['Segera', '1 bulan', '2 bulan', '3 bulan'],
                            dapat_disaring: true,
                        },
                        {
                            key: 'ekspektasi_gaji',
                            label: 'Ekspektasi Gaji per Bulan (Rp)',
                            tipe: 'number',
                            wajib: false,
                            min: 0,
                            maks: 500000000,
                            ph: 'mis. 6000000',
                            bantuan: 'Boleh dikosongkan bila ingin mengikuti ketentuan perusahaan.',
                        },
                        {
                            key: 'sumber_informasi',
                            label: 'Tahu lowongan ini dari mana?',
                            tipe: 'select',
                            wajib: true,
                            opsi: ['Website EVO Career', 'Instagram', 'LinkedIn', 'Job Portal', 'Teman / Karyawan', 'Job Fair', 'Lainnya'],
                            dapat_disaring: true,
                        },
                    ],
                },
                {
                    judul: 'F. Pernyataan',
                    field: [
                        {
                            key: 'setuju_data_benar',
                            label: 'Saya menyatakan seluruh data yang saya isi benar dan dapat dipertanggungjawabkan.',
                            tipe: 'consent',
                            wajib: true,
                        },
                        {
                            key: 'setuju_data_pribadi',
                            label: 'Saya menyetujui penggunaan data pribadi saya untuk keperluan proses rekrutmen EVO Group.',
                            tipe: 'consent',
                            wajib: true,
                        },
                    ],
                },
            ],
        },
    ],
};

export default SKEMA;
