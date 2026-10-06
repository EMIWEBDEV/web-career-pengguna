/**
 * SKEMA — Form 4: Pendaftaran Magang / Internship (gerbang saat melamar).
 *
 * Data diri & pendidikan memakai blok bersama dari inti/blok.js. Yang khas
 * magang ada tiga: pelamar hampir selalu MASIH menempuh pendidikan, magangnya
 * terikat PERIODE dan skema kerja sama (MSIB/PKL/Mandiri), dan kampus menuntut
 * SURAT PENGANTAR — dokumen yang tidak ada di alur MT maupun rekrutmen.
 *
 * Memakai layout BERTAHAP, bukan satu halaman: ada unggahan berkas di
 * dalamnya, dan meminta kandidat mengunggah PDF sambil menggulir formulir
 * panjang membuat kegagalan unggah sulit disadari.
 *
 * ══════════════════════════════════════════════════════════════════════
 *  DI SINILAH SELURUH ATURAN FORMULIR DITULIS.
 *  Bentuk field & daftar operator syarat: lihat form-1/skema.js.
 * ══════════════════════════════════════════════════════════════════════
 */
import { blokDataDiri, blokPendidikan } from '@utils/formulir/blok';

export const SKEMA = {
    template: 'TEMPLATE_1',
    layout: 'BERTAHAP',
    langkah: [
        // ── LANGKAH 1 ─────────────────────────────────────────────────
        {
            kode: 'IDENTITAS',
            judul: 'Data Diri & Pendidikan',
            ikon: 'bi-person-vcard',
            deskripsi: 'Pilih jenjang lebih dulu — pilihan institusi dan jurusan menyesuaikan.',
            bagian: [
                {
                    judul: 'A. Data Diri',
                    field: blokDataDiri({ tanyaDomisili: true }),
                },
                {
                    judul: 'B. Pendidikan',
                    field: blokPendidikan({
                        judulKampus: 'Nama Kampus / Sekolah Asal',
                        // Peserta magang umumnya belum lulus, jadi nilai akhir
                        // belum tentu ada. Diminta bila ada, tidak diwajibkan.
                        wajibNilai: false,
                    }),
                },
            ],
        },

        // ── LANGKAH 2 ─────────────────────────────────────────────────
        {
            kode: 'RENCANA',
            judul: 'Rencana Magang',
            ikon: 'bi-calendar-range',
            deskripsi: 'Periode dan skema magang menentukan penempatan serta pembimbing Anda.',
            bagian: [
                {
                    judul: 'C. Skema & Periode',
                    field: [
                        {
                            key: 'jenis_magang',
                            label: 'Skema Magang',
                            tipe: 'select',
                            wajib: true,
                            opsi: [
                                'MSIB (Magang Bersertifikat Kampus Merdeka)',
                                'PKL (Praktik Kerja Lapangan)',
                                'Magang Mandiri',
                                'Penelitian Skripsi / Tugas Akhir',
                            ],
                            penuh: true,
                            dapat_disaring: true,
                        },
                        { key: 'periode_mulai', label: 'Rencana Mulai Magang', tipe: 'date', wajib: true },
                        { key: 'periode_selesai', label: 'Rencana Selesai Magang', tipe: 'date', wajib: true },
                        {
                            key: 'durasi_bulan',
                            label: 'Durasi (bulan)',
                            tipe: 'number',
                            wajib: true,
                            min: 1,
                            maks: 12,
                            ph: 'mis. 6',
                            dapat_disaring: true,
                        },
                        {
                            key: 'bidang_diminati',
                            label: 'Bidang / Divisi yang Diminati',
                            tipe: 'text',
                            wajib: true,
                            penuh: true,
                            ph: 'mis. Quality Control, IT, HR',
                            bantuan: 'Penempatan akhir tetap menyesuaikan kebutuhan perusahaan.',
                        },
                    ],
                },
                {
                    judul: 'D. Pembimbing Kampus',
                    deskripsi: 'Dihubungi hanya untuk konfirmasi keabsahan pengajuan magang.',
                    // Magang mandiri sering tidak punya dosen pembimbing — blok
                    // ini disembunyikan seluruhnya, bukan sekadar dibuat opsional.
                    tampil_jika: {
                        field: 'jenis_magang',
                        operator: 'TIDAK_ADA_DI',
                        nilai: ['Magang Mandiri'],
                    },
                    field: [
                        { key: 'dosen_pembimbing', label: 'Nama Dosen Pembimbing', tipe: 'text', wajib: true },
                        {
                            key: 'kontak_pembimbing',
                            label: 'No. HP / WA Dosen Pembimbing',
                            tipe: 'phone',
                            wajib: true,
                            ph: '628xxxxxxxxx',
                            bantuan: 'Wajib berawalan 62. Ketik 08… otomatis jadi 628…',
                        },
                        { key: 'email_pembimbing', label: 'Email Dosen Pembimbing', tipe: 'text', wajib: false, ph: 'dosen@kampus.ac.id' },
                    ],
                },
                {
                    judul: 'E. Kontak Darurat',
                    field: [
                        { key: 'darurat_nama', label: 'Nama Kontak Darurat', tipe: 'text', wajib: true },
                        { key: 'darurat_hubungan', label: 'Hubungan dengan Peserta', tipe: 'text', wajib: true, ph: 'mis. Orang tua / Saudara' },
                        {
                            key: 'darurat_hp',
                            label: 'No. Handphone Kontak Darurat',
                            tipe: 'phone',
                            wajib: true,
                            ph: '628xxxxxxxxx',
                            bantuan: 'Wajib berawalan 62. Ketik 08… otomatis jadi 628…',
                        },
                    ],
                },
            ],
        },

        // ── LANGKAH 3 ─────────────────────────────────────────────────
        {
            kode: 'DOKUMEN',
            judul: 'Dokumen & Pernyataan',
            ikon: 'bi-paperclip',
            deskripsi: 'Format PDF. Ukuran dibatasi agar penyimpanan tidak cepat penuh.',
            bagian: [
                {
                    judul: 'F. Kelengkapan Dokumen',
                    field: [
                        {
                            key: 'dok_surat_pengantar',
                            label: 'Surat Pengantar Magang dari Kampus / Sekolah',
                            tipe: 'file',
                            wajib: true,
                            accept: '.pdf',
                            maks_mb: 2,
                        },
                        { key: 'dok_cv', label: 'Upload CV Terbaru', tipe: 'file', wajib: true, accept: '.pdf', maks_mb: 2 },
                        { key: 'dok_transkrip', label: 'Upload Transkrip / Rapor Terakhir', tipe: 'file', wajib: false, accept: '.pdf', maks_mb: 2 },
                        { key: 'dok_proposal', label: 'Proposal Magang (bila diminta kampus)', tipe: 'file', wajib: false, accept: '.pdf', maks_mb: 5 },
                    ],
                },
                {
                    judul: 'G. Pernyataan',
                    field: [
                        {
                            key: 'setuju_data_benar',
                            label: 'Saya menyatakan seluruh data dan dokumen yang saya berikan benar dan dapat dipertanggungjawabkan.',
                            tipe: 'consent',
                            wajib: true,
                        },
                        {
                            key: 'setuju_tata_tertib',
                            label: 'Saya bersedia mematuhi tata tertib, jam kerja, dan ketentuan keselamatan kerja yang berlaku di EVO Group selama magang.',
                            tipe: 'consent',
                            wajib: true,
                        },
                        {
                            key: 'setuju_data_pribadi',
                            label: 'Saya menyetujui penggunaan data pribadi saya untuk keperluan proses seleksi dan administrasi magang.',
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
