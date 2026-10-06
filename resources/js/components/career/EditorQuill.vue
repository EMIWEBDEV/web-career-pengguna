<!-- ══════════════════════════════════════════════════════════
     WEB CAREER — Editor teks berformat (Quill), pembungkus v-model.
     Toolbar SENGAJA dibatasi agar HTML yang dihasilkan tetap berada di
     dalam daftar-izin App\Support\Career\HtmlBersih. Menambah tombol di
     sini tanpa menambah tag di sana = format itu akan dibuang saat simpan.
     ══════════════════════════════════════════════════════════ -->
<template>
    <div class="eq" :class="{ 'is-disabled': disabled, 'is-ringkas': ringkas, 'is-mungil': mungil }">
        <div ref="wadah"></div>
        <!-- Keadaan unggah ditampilkan DI LUAR editor: menyisipkan baris status
             ke dalam isi editor akan ikut tersimpan sebagai teks catatan. -->
        <small v-if="unggahSibuk" class="eq-status"><i class="bi bi-arrow-repeat eq-spin"></i> Mengunggah gambar…</small>
        <small v-else-if="unggahGalat" class="eq-status is-err"><i class="bi bi-exclamation-circle-fill"></i> {{ unggahGalat }}</small>
        <small v-else-if="hint" class="eq-hint"><i class="bi bi-info-circle"></i> {{ hint }}</small>
    </div>
</template>

<script>
import axios from 'axios';
import Quill from 'quill';
import 'quill/dist/quill.snow.css';

export default {
    props: {
        modelValue: { type: String, default: '' },
        placeholder: { type: String, default: 'Tulis penjelasan lengkap di sini…' },
        disabled: { type: Boolean, default: false },
        hint: { type: String, default: '' },
        // Editor di dalam modal tidak punya ruang setinggi halaman penuh.
        ringkas: { type: Boolean, default: false },
        /**
         * Satu editor PER BARIS daftar — dua puluh baris keputusan massal,
         * masing-masing punya catatannya sendiri. Setinggi `ringkas` pun,
         * dua puluh kali 120px berarti daftar sepanjang dua setengah ribu
         * piksel. Tingginya dipangkas ke kira-kira dua baris teks dan tumbuh
         * sendiri saat benar-benar diisi.
         */
        mungil: { type: Boolean, default: false },
        /**
         * Alamat penerima gambar. KOSONG = tombol gambar tidak dipasang sama
         * sekali — bukan dipasang lalu gagal saat diklik. Editor yang tidak
         * punya tempat menyimpan gambar memang tidak boleh menjanjikannya.
         */
        uploadUrl: { type: String, default: '' },
        /** Field tambahan yang ikut dikirim (mis. subTesId) sebagai konteks. */
        uploadData: { type: Object, default: () => ({}) },
    },
    emits: ['update:modelValue'],
    data() {
        return { quill: null, dariDalam: false, unggahSibuk: false, unggahGalat: '', rangeTersimpan: null };
    },
    watch: {
        modelValue(nilai) {
            // Jangan tulis ulang isi editor karena perubahan yang berasal dari
            // editor itu sendiri — kursor akan melompat ke akhir setiap ketikan.
            if (this.dariDalam) {
                this.dariDalam = false;
                return;
            }
            if (this.quill && (nilai || '') !== this.quill.root.innerHTML) {
                this.pasangIsi(nilai || '');
            }
        },
        disabled(v) {
            this.quill?.enable(!v);
        },
    },
    mounted() {
        const baris = [
            [{ header: [3, 4, false] }],
            ['bold', 'italic', 'underline', 'strike'],
            [{ list: 'ordered' }, { list: 'bullet' }],
            ['blockquote', 'link'],
            // Perataan dipakai untuk menengahkan GAMBAR — Quill meratakan blok,
            // jadi tanpa tombol ini gambar selalu menempel di kiri.
            [{ align: '' }, { align: 'center' }, { align: 'right' }],
        ];
        if (this.uploadUrl) {
            baris.push(['image']);
        }
        baris.push(['clean']);

        this.quill = new Quill(this.$refs.wadah, {
            theme: 'snow',
            placeholder: this.placeholder,
            modules: {
                toolbar: {
                    container: baris,
                    // Penangan bawaan Quill menyisipkan gambar sebagai data URI
                    // di dalam HTML. Satu potret ponsel jadi ~2,7 MB base64 yang
                    // ikut terbawa setiap kali catatan itu dibaca — dan penyaring
                    // di server memang membuangnya, jadi gambarnya akan hilang
                    // diam-diam saat disimpan. Diganti: unggah dulu, sisipkan
                    // tautannya.
                    handlers: this.uploadUrl ? { image: this.pilihGambar } : {},
                },
            },
        });

        if (this.modelValue) {
            this.pasangIsi(this.modelValue);
        }
        if (this.disabled) {
            this.quill.enable(false);
        }

        // ── POSISI KURSOR DIREKAM TERUS-MENERUS, BUKAN DITANYAKAN SAAT DIBUTUHKAN ──
        //
        // Menekan tombol gambar memindahkan seleksi DOM ke tombol itu sendiri.
        // Sesudah itu `getSelection()` bukan cuma mengembalikan null — ia
        // MELEDAK: Quill mencari blot pemilik simpul seleksi, tidak menemukannya,
        // lalu memanggil `.offset()` pada null. Galat itu terjadi SEBELUM satu
        // baris pun kode kita sempat berjalan, jadi `?.` maupun nilai cadangan
        // di belakangnya tak pernah kebagian giliran — dan seluruh editor,
        // di mana pun ia dipakai, kehilangan kemampuan menyisipkan gambar.
        //
        // `selection-change` dipancarkan Quill selagi kursornya MASIH di dalam
        // editor, saat jawabannya masih sah. Itulah yang direkam.
        this.quill.on('selection-change', (range) => {
            if (range) {
                this.rangeTersimpan = range;
            }
        });

        this.quill.on('text-change', () => {
            // Catatan yang isinya HANYA gambar tetap punya isi. Menilai kosong
            // dari teksnya saja akan mengosongkan catatan berupa potret lembar
            // penilaian — persis yang paling sering ditempel penilai.
            const kosong = this.quill.getText().trim() === '' && !this.quill.root.querySelector('img');
            this.dariDalam = true;
            this.$emit('update:modelValue', kosong ? '' : this.quill.root.innerHTML);
        });

        // Menempel gambar dari papan klip (Ctrl+V tangkapan layar) lewat jalur
        // yang sama. Tanpa ini, tempelan tetap masuk sebagai data URI dan
        // hilang saat disimpan — kegagalan paling membingungkan karena
        // gambarnya TERLIHAT sampai halaman dimuat ulang.
        if (this.uploadUrl) {
            this.quill.root.addEventListener('paste', this.tangkapTempel, true);
        }
    },
    beforeUnmount() {
        this.quill?.root?.removeEventListener('paste', this.tangkapTempel, true);
        // Quill menaruh toolbar sebagai SIBLING wadah, di luar jangkauan Vue —
        // tanpa dibuang manual, toolbar tertinggal saat modal dibuka-tutup.
        this.quill?.getModule('toolbar')?.container?.remove();
        this.quill = null;
    },
    methods: {
        /**
         * Pasang isi lewat JALUR QUILL, bukan lewat innerHTML.
         *
         * ── KENAPA INI PENTING ─────────────────────────────────────────────
         *
         * Menulis langsung ke `quill.root.innerHTML` mengubah DOM tapi TIDAK
         * memberi tahu Quill. Modelnya tetap mengira editornya kosong, jadi
         * kelas `ql-blank` tidak pernah dilepas — dan `.ql-blank::before`,
         * tempat placeholder digambar, terus melukis teks abu-abu DI ATAS
         * tulisan yang sebenarnya. Itulah tumpang tindih yang terlihat:
         * "Tulis penjelasan…" dan kalimat penilai saling menimpa di baris
         * yang sama.
         *
         * HTML diubah ke Delta lalu dipasang lewat `setContents` — lewat
         * model, jadi ql-blank ikut benar. Mode 'silent' dipakai supaya ia
         * tidak memancing text-change — kalau tidak, memuat nilai dari server
         * langsung dihitung sebagai "diketik orang" dan memancarkan
         * update:modelValue kembali.
         *
         * SENGAJA BUKAN `dangerouslyPasteHTML(html)`: bentuk itu menutup
         * dengan `setSelection(0)` yang MEREBUT FOKUS ke editor. Editor yang
         * dipasang berisi di dalam jendela (catatan cabang vendor, catatan
         * penilaian) lalu memegang kursor di simpul yang sesaat kemudian
         * dipindah jendelanya — dan setiap `selectionchange` sesudahnya
         * membuat Quill melempar "Cannot read properties of null (reading
         * 'offset')".
         */
        pasangIsi(html) {
            if (! this.quill) {
                return;
            }

            if (html) {
                this.quill.setContents(this.quill.clipboard.convert({ html, text: '' }), 'silent');
            } else {
                this.quill.setText('', 'silent');
            }
        },
        /**
         * Tanya Quill di mana kursornya — TANPA bisa menjatuhkan editor.
         *
         * Quill melempar bila seleksi DOM menunjuk simpul yang bukan miliknya
         * (mis. tombol toolbar yang baru ditekan, atau editor yang wadahnya
         * sudah dilepas Vue). Di sini jawaban "tidak tahu" sepenuhnya wajar —
         * pemanggilnya punya cadangan — sedangkan lemparannya membatalkan
         * seluruh tindakan.
         */
        rangeAman() {
            try {
                return this.quill?.getSelection() || null;
            } catch (e) {
                return null;
            }
        },

        /** Tombol gambar → buka pemilih berkas. */
        pilihGambar() {
            // Yang dipakai adalah posisi TERAKHIR YANG DIREKAM saat kursor masih
            // di dalam editor (lihat 'selection-change' di mounted). Menanyakan
            // ulang di sini percuma: menekan tombolnya sendiri sudah memindahkan
            // seleksi keluar. Tetap dicoba — kalau kebetulan masih sah, itu yang
            // paling mutakhir — tapi jawabannya tidak pernah boleh menimpa
            // rekaman lama dengan kosong.
            this.rangeTersimpan = this.rangeAman() || this.rangeTersimpan;

            const input = document.createElement('input');
            input.type = 'file';
            input.accept = 'image/jpeg,image/png,image/webp';
            input.onchange = () => {
                const file = input.files?.[0];
                if (file) {
                    this.unggah(file);
                }
            };
            input.click();
        },

        tangkapTempel(e) {
            this.rangeTersimpan = this.rangeAman() || this.rangeTersimpan;
            const file = Array.from(e.clipboardData?.items || [])
                .find((i) => i.type?.startsWith('image/'))
                ?.getAsFile();
            if (!file) {
                return;
            }
            e.preventDefault();
            this.unggah(file);
        },

        /**
         * Unggah lalu sisipkan tautannya di posisi kursor.
         *
         * Kegagalan ditampilkan, TIDAK ditelan: gambar yang gagal naik tanpa
         * kabar membuat penilai mengira catatannya lengkap, dan baru sadar
         * setelah keputusan diambil.
         */
        async unggah(file) {
            this.unggahGalat = '';
            this.unggahSibuk = true;
            try {
                const fd = new FormData();
                fd.append('file', file);
                Object.entries(this.uploadData || {}).forEach(([k, v]) => {
                    if (v) {
                        fd.append(k, v);
                    }
                });

                const { data } = await axios.post(this.uploadUrl, fd, { headers: { Accept: 'application/json' } });
                const url = data?.result?.url;
                if (!url) {
                    throw new Error('Tautan gambar tidak diterima dari server.');
                }

                // EDITORNYA MASIH ADA? Unggahan berjalan asinkron; modal yang
                // ditutup di tengah jalan sudah melepas Quill di beforeUnmount.
                // Tanpa penjagaan ini, yang muncul adalah "Gambar gagal
                // diunggah" — padahal gambarnya SUDAH naik dengan selamat, dan
                // penilai lalu mengunggahnya berulang kali.
                if (!this.quill) {
                    return;
                }

                // Pakai posisi yang DIREKAM saat kursor masih di dalam editor.
                // Menanyakannya sekarang tidak bisa diandalkan — fokusnya baru
                // saja kembali dari dialog berkas.
                const panjang = this.quill.getLength();
                const posisi = Math.min(
                    this.rangeTersimpan?.index ?? this.rangeAman()?.index ?? panjang,
                    panjang,
                );

                this.quill.insertEmbed(posisi, 'image', url, 'user');
                // Gambar diberi barisnya sendiri supaya perataan (kiri/tengah)
                // bisa dipilih: perataan Quill berlaku pada BLOK, jadi gambar
                // yang menempel di tengah paragraf tak pernah bisa ditengahkan.
                this.quill.setSelection(posisi + 1, 0, 'user');
                this.rangeTersimpan = null;
            } catch (e) {
                this.unggahGalat =
                    e?.response?.data?.message || e?.message || 'Gambar gagal diunggah.';
            } finally {
                this.unggahSibuk = false;
            }
        },
    },
};
</script>

<style scoped>
.eq :deep(.ql-toolbar) {
    border-radius: 0.7rem 0.7rem 0 0;
    border-color: #dcdfe6;
    background: #f8fafc;
}
.eq :deep(.ql-container) {
    border-radius: 0 0 0.7rem 0.7rem;
    border-color: #dcdfe6;
    font-family: inherit;
    font-size: 0.92rem;
}
.eq :deep(.ql-editor) {
    min-height: 170px;
    line-height: 1.7;
}
/* Di dalam modal, editor setinggi halaman penuh mendorong tombol simpan keluar
   layar — yang membaca layarnya justru mengira modalnya belum selesai dimuat. */
.eq.is-ringkas :deep(.ql-editor) {
    min-height: 120px;
    max-height: 260px;
    overflow-y: auto;
}
/* Sebaris daftar, bukan sehalaman borang. Toolbarnya ikut dirapatkan —
   pada tinggi ini toolbar bawaan Quill lebih tinggi daripada isinya. */
.eq.is-mungil :deep(.ql-editor) {
    min-height: 56px;
    max-height: 150px;
    padding: 7px 10px;
    font-size: 0.86rem;
    line-height: 1.55;
}
.eq.is-mungil :deep(.ql-toolbar) {
    padding: 3px 5px;
}
.eq.is-mungil :deep(.ql-toolbar .ql-formats) {
    margin-right: 7px;
}
/* Potret ponsel beresolusi penuh akan menjebol lebar kolom catatan. */
.eq :deep(.ql-editor img) {
    max-width: 100%;
    height: auto;
    border-radius: 0.5rem;
    margin: 0.35rem 0;
}
.eq :deep(.ql-editor.ql-blank::before) {
    font-style: normal;
    color: #a8abb2;
}
.eq.is-disabled {
    opacity: 0.65;
}
.eq-hint,
.eq-status {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    margin-top: 0.4rem;
    color: var(--muted, #64748b);
    font-size: 0.78rem;
    font-weight: 600;
}
.eq-status.is-err {
    color: #dc2626;
}
.eq-spin {
    animation: eq-putar 0.9s linear infinite;
}
@keyframes eq-putar {
    to {
        transform: rotate(360deg);
    }
}
</style>
