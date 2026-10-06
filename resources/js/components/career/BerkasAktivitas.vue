<!-- ══════════════════════════════════════════════════════════
     WEB CAREER — Lampiran hasil SATU AKTIVITAS seleksi.

     Dipakai di jendela "Tandai Hadir" dan "Catat Hasil": form wawancara yang
     dipindai, lembar jawaban tes offline, berita acara FGD.

     KENAPA MELEKAT PADA AKTIVITAS, BUKAN TAHAP
     Satu tahap bisa berisi Psikotes 2, DISC, dan Wawancara HR sekaligus.
     Lampiran setingkat tahap mencampur ketiganya tanpa penanda mana milik mana,
     dan penilai wawancara tidak punya cara memastikan berkas yang baru saja ia
     unggah sudah masuk ke sesi yang benar.

     OPSIONAL — tidak semua wawancara memakai lembar penilaian cetak. Komponen
     ini tidak pernah memblokir penyimpanan; ia hanya menyediakan tempatnya.
     ══════════════════════════════════════════════════════════ -->
<template>
    <div class="bka">
        <div class="bka-head">
            <span class="bka-title"><i class="bi bi-paperclip"></i> {{ label }}</span>
            <small class="bka-opsi">opsional</small>
        </div>

        <ul v-if="daftar.length" class="bka-list">
            <li v-for="b in daftar" :key="b.id" class="bka-item">
                <i class="bi" :class="b.isImage ? 'bi-file-earmark-image-fill is-img' : 'bi-file-earmark-pdf-fill is-pdf'"></i>
                <!-- DIBUKA DI MODAL, bukan tab baru.
                     Tab baru melempar admin keluar dari jendela penilaian yang
                     sedang ia isi — catatan yang belum disimpan tetap ada, tapi
                     alurnya terputus dan berkasnya tampil telanjang tanpa
                     konteks. Induknya punya lightbox yang sama dengan yang
                     dipakai dokumen lain di halaman ini. -->
                <button type="button" class="bka-nama" :title="b.nama" @click="$emit('lihat', b)">{{ b.nama }}</button>
                <span class="bka-size">{{ ukuran(b.ukuran) }}</span>
                <button
                    v-if="!disabled"
                    type="button" class="bka-del" title="Hapus berkas ini"
                    :disabled="sibuk" :onClick="sibuk ? null : () => hapus(b)"
                >
                    <i class="bi bi-trash3"></i>
                </button>
            </li>
        </ul>

        <p v-else class="bka-kosong">Belum ada berkas dilampirkan.</p>

        <div v-if="!disabled" class="bka-aksi">
            <button type="button" class="bka-btn" :disabled="sibuk" :onClick="sibuk ? null : pilih">
                <i class="bi" :class="sibuk ? 'bi-arrow-repeat bka-spin' : 'bi-upload'"></i>
                {{ sibuk ? 'Mengunggah…' : 'Unggah Berkas' }}
            </button>
            <small class="bka-ket">PDF atau JPG, maks 2 MB per berkas.</small>
        </div>

        <p v-if="galat" class="bka-galat"><i class="bi bi-exclamation-circle-fill"></i> {{ galat }}</p>
    </div>
</template>

<script>
import axios from 'axios';

export default {
    props: {
        /** Hashid aktivitas (Lamaran_Tahap_Tes). Kosong = komponen tak berguna. */
        subTesId: { type: String, default: '' },
        /** Berkas yang sudah dikirim server bersama rapor tes — hemat satu kueri. */
        awal: { type: Array, default: () => [] },
        label: { type: String, default: 'Berkas hasil' },
        disabled: { type: Boolean, default: false },
    },
    emits: ['berubah', 'lihat'],
    data() {
        return { daftar: [...(this.awal || [])], sibuk: false, galat: '' };
    },
    watch: {
        // Drawer memakai ulang komponen ini untuk aktivitas yang berbeda; tanpa
        // ini, daftar milik wawancara sebelumnya ikut terbawa ke sesi berikutnya.
        subTesId() {
            this.daftar = [...(this.awal || [])];
            this.galat = '';
        },
        awal(v) {
            this.daftar = [...(v || [])];
        },
    },
    methods: {
        pilih() {
            const input = document.createElement('input');
            input.type = 'file';
            input.accept = '.pdf,.jpg,.jpeg';
            input.onchange = () => {
                const f = input.files?.[0];
                if (f) {
                    this.unggah(f);
                }
            };
            input.click();
        },

        async unggah(file) {
            if (!this.subTesId) {
                this.galat = 'Aktivitas belum dikenali — muat ulang halaman.';
                return;
            }
            this.galat = '';
            this.sibuk = true;
            try {
                const fd = new FormData();
                fd.append('file', file);
                const { data } = await axios.post(
                    `/api/v1/karir/lamaran/sub-tes/${this.subTesId}/berkas`,
                    fd,
                    { headers: { Accept: 'application/json' } },
                );
                // Server mengembalikan daftar UTUH sesudah unggah, bukan satu
                // baris baru: dua penilai yang mengunggah bersamaan tetap
                // melihat kenyataan yang sama tanpa perlu memuat ulang.
                this.daftar = data?.result || [];
                this.$emit('berubah', this.daftar);
            } catch (e) {
                this.galat = e?.response?.data?.message || 'Berkas gagal diunggah.';
            } finally {
                this.sibuk = false;
            }
        },

        async hapus(b) {
            this.galat = '';
            this.sibuk = true;
            try {
                await axios.delete(`/api/v1/karir/lamaran/tahap/berkas/${b.id}`, { headers: { Accept: 'application/json' } });
                this.daftar = this.daftar.filter((x) => x.id !== b.id);
                this.$emit('berubah', this.daftar);
            } catch (e) {
                this.galat = e?.response?.data?.message || 'Berkas gagal dihapus.';
            } finally {
                this.sibuk = false;
            }
        },

        ukuran(b) {
            const n = Number(b) || 0;
            if (n < 1024) return `${n} B`;
            if (n < 1024 * 1024) return `${(n / 1024).toFixed(0)} KB`;
            return `${(n / 1024 / 1024).toFixed(1)} MB`;
        },
    },
};
</script>

<style scoped>
.bka {
    border: 1px dashed #d8dbe4;
    border-radius: 0.7rem;
    padding: 0.7rem 0.8rem;
    background: #fafbfd;
}
.bka-head {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    margin-bottom: 0.5rem;
}
.bka-title {
    font-size: 0.8rem;
    font-weight: 800;
    color: #475569;
    letter-spacing: 0.02em;
}
.bka-opsi {
    font-size: 0.7rem;
    font-weight: 700;
    color: #94a3b8;
    background: #eef1f6;
    border-radius: 999px;
    padding: 0.1rem 0.45rem;
}
.bka-list {
    list-style: none;
    margin: 0 0 0.5rem;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: 0.3rem;
}
.bka-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    background: #fff;
    border: 1px solid #e8ebf1;
    border-radius: 0.55rem;
    padding: 0.4rem 0.55rem;
}
.bka-item .is-img {
    color: #6366f1;
}
.bka-item .is-pdf {
    color: #ef4444;
}
.bka-nama {
    /* Tombol, bukan tautan — tapi tetap tampil seperti tautan. */
    appearance: none;
    border: 0;
    background: transparent;
    font: inherit;
    text-align: left;
    cursor: pointer;
    flex: 1;
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    font-size: 0.82rem;
    font-weight: 700;
    color: #334155;
    text-decoration: none;
}
.bka-nama:hover {
    color: #4f46e5;
    text-decoration: underline;
}
.bka-size {
    font-size: 0.72rem;
    color: #94a3b8;
    font-weight: 700;
}
.bka-del {
    border: 0;
    background: transparent;
    color: #cbd5e1;
    cursor: pointer;
    padding: 0.15rem 0.25rem;
    border-radius: 0.35rem;
}
.bka-del:hover:not(:disabled) {
    color: #dc2626;
    background: #fef2f2;
}
.bka-kosong {
    margin: 0 0 0.5rem;
    font-size: 0.78rem;
    color: #94a3b8;
}
.bka-aksi {
    display: flex;
    align-items: center;
    gap: 0.55rem;
    flex-wrap: wrap;
}
.bka-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    border: 1px solid #c7d2fe;
    background: #eef2ff;
    color: #4338ca;
    font-size: 0.78rem;
    font-weight: 800;
    border-radius: 0.55rem;
    padding: 0.35rem 0.7rem;
    cursor: pointer;
}
.bka-btn:hover:not(:disabled) {
    background: #e0e7ff;
}
.bka-btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}
.bka-ket {
    font-size: 0.72rem;
    color: #94a3b8;
    font-weight: 600;
}
.bka-galat {
    margin: 0.45rem 0 0;
    font-size: 0.76rem;
    font-weight: 700;
    color: #dc2626;
    display: flex;
    align-items: center;
    gap: 0.3rem;
}
.bka-spin {
    animation: bka-putar 0.9s linear infinite;
}
@keyframes bka-putar {
    to {
        transform: rotate(360deg);
    }
}
</style>
