/**
 * SKEMA — Form 1: Pendaftaran MT (gerbang saat melamar).
 *
 * Sesuai "List Data Form Pendaftaran 1 MT" pada berkas referensi
 * (docs/refrences/List Identitas Form Pendaftaran MT.xlsx, sheet "Form 1").
 *
 * ══════════════════════════════════════════════════════════════════════
 *  DI SINILAH SELURUH ATURAN FORMULIR DITULIS.
 *  Menambah pertanyaan, mengubah opsi, atau menambah syarat tampil
 *  cukup menyunting berkas ini. Tabel database TIDAK perlu di-ALTER
 *  karena jawaban tersimpan sebagai JSON.
 * ══════════════════════════════════════════════════════════════════════
 *
 * BENTUK SATU FIELD:
 *   key            wajib, unik se-formulir — jadi nama kolom di Jawaban_Json
 *   label          pertanyaan yang dibaca kandidat
 *   tipe           text | textarea | number | date | select | radio |
 *                  checkbox | file | consent | prefill | phone | referensi
 *   wajib          true/false
 *   opsi           daftar pilihan (untuk select/radio/checkbox)
 *   sumber         untuk tipe `referensi`: jenjang | jenis_institusi |
 *                  kampus | prodi — opsinya dicari ke server sambil mengetik
 *   bergantung     induk yang WAJIB terisi dulu  { paramApi: 'key_field' }
 *   saring         penyempit opsional             { paramApi: 'key_field' }
 *   ph             placeholder di dalam kolom
 *   bantuan        keterangan kecil di bawah kolom
 *   penuh          true = kolom memakan lebar penuh
 *   dapat_disaring true = nilainya bisa dipakai syarat auto-gugur
 *   tampil_jika    { field, operator, nilai } — syarat kemunculan
 *                  operator: = != > < >= <= ADA_DI TIDAK_ADA_DI
 *
 * BLOK PENDIDIKAN tidak ditulis di sini — dipanggil dari inti/blok.js supaya
 * MT, Rekrutmen, dan Magang memakai aturan pendidikan yang sama persis.
 *
 * CATATAN PERUBAHAN (2026-07-29): dua field lama `jenjang_politeknik` dan
 * `jenjang_universitas` menyatu jadi `jenjang_pendidikan` — nilainya tetap
 * kode yang sama ('D3', 'S1'), dan FieldTurunan sudah mengenalinya sebagai
 * sumber `jenjang`. Field `program_studi` menyatu ke `jurusan`, yang kini
 * dipilih dari Master Prodi alih-alih diketik bebas.
 */
import { blokDataDiri, blokPendidikan } from '@utils/formulir/blok';

export const SKEMA = {
    template: 'TEMPLATE_1',
    layout: 'SATU_HALAMAN',
    langkah: [
        {
            kode: 'PENDAFTARAN',
            judul: 'Data Pendaftaran',
            ikon: 'bi-person-vcard',
            bagian: [
                {
                    judul: 'A. Data Diri',
                    field: blokDataDiri(),
                },
                {
                    judul: 'B. Pendidikan',
                    deskripsi: 'Pilih jenjang lebih dulu — institusi dan jurusan menyesuaikan.',
                    field: blokPendidikan({ judulKampus: 'Nama Kampus / Universitas' }),
                },
                {
                    judul: 'C. Kesediaan',
                    field: [
                        {
                            key: 'bersedia_ditempatkan',
                            label: 'Apakah bersedia ditempatkan di Pabrik Banyuasin?',
                            tipe: 'select',
                            wajib: true,
                            opsi: ['Ya', 'Tidak'],
                            penuh: true,
                            dapat_disaring: true,
                        },
                    ],
                },
            ],
        },
    ],
};

export default SKEMA;
