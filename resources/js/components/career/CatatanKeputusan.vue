<!-- WEB CAREER — Catatan keputusan dua arah: UNTUK KANDIDAT (eksternal) dan
     INTERNAL (tim).

     Dua editor, bukan satu editor dengan saklar "tampilkan ke kandidat". Satu
     kotak dengan saklar berarti satu klik salah membuat penilaian internal
     ("gugup, jawaban kurang meyakinkan") terbaca kandidat. Dengan dua kotak,
     yang ditulis di tab internal memang tidak punya jalan keluar.

     Kedua editor tetap hidup saat tab berpindah (v-show) — isi tab yang sedang
     tidak dilihat tidak boleh hilang. Titik kecil di tab menandai yang sudah
     terisi, supaya catatan di tab lain tidak terlupa sebelum menyimpan. -->
<template>
    <div class="ckp" :class="{ 'is-mungil': mungil }">
        <div v-if="eksternalSiap" class="ckp__tabs" role="tablist" aria-label="Jenis catatan keputusan">
            <button
                type="button"
                role="tab"
                class="ckp__tab is-eksternal"
                :class="{ on: tab === 'EKSTERNAL' }"
                :aria-selected="tab === 'EKSTERNAL'"
                @click="$emit('update:tab', 'EKSTERNAL')"
            >
                <i class="bi bi-megaphone-fill" aria-hidden="true"></i>
                <span class="ckp__tabtxt">
                    <b>Untuk Kandidat</b>
                    <small v-if="!mungil">eksternal · terlihat kandidat</small>
                </span>
                <span v-if="adaEksternal" class="ckp__dot" title="Sudah diisi"></span>
            </button>
            <button
                type="button"
                role="tab"
                class="ckp__tab is-internal"
                :class="{ on: tab === 'INTERNAL' }"
                :aria-selected="tab === 'INTERNAL'"
                @click="$emit('update:tab', 'INTERNAL')"
            >
                <i class="bi bi-lock-fill" aria-hidden="true"></i>
                <span class="ckp__tabtxt">
                    <b>{{ labelInternal }}<em v-if="wajibInternal" class="ckp__wajib">*</em></b>
                    <small v-if="!mungil">internal · hanya tim</small>
                </span>
                <span v-if="adaInternal" class="ckp__dot" title="Sudah diisi"></span>
            </button>
        </div>

        <div v-if="eksternalSiap" v-show="tab === 'EKSTERNAL'" class="ckp__panel is-eksternal" role="tabpanel">
            <EditorQuill
                :model-value="eksternal"
                ringkas
                :mungil="mungil"
                :placeholder="placeholderEksternal"
                :hint="mungil ? '' : 'Tampil di halaman lamaran kandidat — bila lolos, di kartu tahap berikutnya — & ikut di email hasil, setelah hasil tahap ini diumumkan. Kosongkan bila tidak ada yang perlu disampaikan.'"
                @update:model-value="$emit('update:eksternal', $event)"
            />
        </div>

        <div v-show="!eksternalSiap || tab === 'INTERNAL'" class="ckp__panel is-internal" role="tabpanel">
            <EditorQuill
                :model-value="internal"
                ringkas
                :mungil="mungil"
                :upload-url="uploadUrl"
                :upload-data="uploadData"
                :placeholder="placeholderInternal"
                :hint="mungil ? '' : 'Hanya untuk tim — tidak pernah ditampilkan ke kandidat. Bisa diberi format & gambar.'"
                @update:model-value="$emit('update:internal', $event)"
            />
        </div>
    </div>
</template>

<script>
import EditorQuill from './EditorQuill.vue';

/** Teks yang benar-benar terbaca dari HTML editor ("<p><br></p>" = kosong). */
const terisi = (html) => String(html || '').replace(/<[^>]*>/g, '').replace(/&nbsp;/g, ' ').trim() !== '' || /<img\b/i.test(String(html || ''));

export default {
    components: { EditorQuill },
    props: {
        /** 'EKSTERNAL' | 'INTERNAL' — v-model:tab. */
        tab: { type: String, default: 'EKSTERNAL' },
        eksternal: { type: String, default: '' },
        internal: { type: String, default: '' },
        /** Kolom catatan eksternal sudah ada di server? Bila belum, hanya editor internal. */
        eksternalSiap: { type: Boolean, default: false },
        labelInternal: { type: String, default: 'Catatan Internal' },
        wajibInternal: { type: Boolean, default: false },
        placeholderInternal: { type: String, default: 'mis. sesuai rekomendasi sistem / alasan khusus' },
        placeholderEksternal: { type: String, default: 'mis. Psikotes online Senin, 6 Okt pukul 09.00 WIB. Link Zoom: https://…' },
        /** Unggah gambar hanya untuk catatan INTERNAL — kandidat & email tidak bisa memuat gambar catatan. */
        uploadUrl: { type: String, default: '' },
        uploadData: { type: Object, default: () => ({}) },
        /** Versi rapat untuk baris keputusan massal. */
        mungil: { type: Boolean, default: false },
    },
    emits: ['update:tab', 'update:eksternal', 'update:internal'],
    computed: {
        adaEksternal() { return terisi(this.eksternal); },
        adaInternal() { return terisi(this.internal); },
    },
};
</script>

<style scoped>
.ckp {
    display: flex;
    flex-direction: column;
    gap: 8px;
    min-width: 0;
}
.ckp__tabs {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 6px;
    padding: 4px;
    border-radius: 14px;
    background: #f2f4fb;
    border: 1px solid #e6e9f3;
}
.ckp__tab {
    position: relative;
    display: flex;
    align-items: center;
    gap: 9px;
    min-width: 0;
    min-height: 44px;
    padding: 7px 12px;
    border: 1px solid transparent;
    border-radius: 11px;
    background: transparent;
    font: inherit;
    text-align: left;
    color: #64748b;
    cursor: pointer;
    transition: all 0.16s ease;
}
.ckp__tab > i {
    flex: none;
    font-size: 15px;
}
.ckp__tab:hover {
    color: #334155;
}
.ckp__tab.on {
    background: #fff;
    box-shadow: 0 3px 10px rgba(15, 23, 42, 0.08);
}
.ckp__tab.is-eksternal.on {
    color: #4338ca;
    border-color: #c7d2fe;
}
.ckp__tab.is-internal.on {
    color: #334155;
    border-color: #cbd5e1;
}
.ckp__tab:focus-visible {
    outline: 3px solid rgba(99, 102, 241, 0.35);
    outline-offset: 1px;
}
.ckp__tabtxt {
    min-width: 0;
    display: flex;
    flex-direction: column;
    line-height: 1.25;
}
.ckp__tabtxt b {
    font-size: 12.5px;
    font-weight: 800;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.ckp__tabtxt small {
    font-size: 10.5px;
    font-weight: 600;
    color: #94a3b8;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.ckp__wajib {
    font-style: normal;
    color: #dc2626;
    margin-left: 2px;
}
.ckp__dot {
    position: absolute;
    top: 7px;
    right: 8px;
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #10b981;
    box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.18);
}
.ckp__panel.is-eksternal :deep(.ql-toolbar),
.ckp__panel.is-eksternal :deep(.ql-container) {
    border-color: #c7d2fe !important;
}
.ckp__panel.is-eksternal :deep(.ql-container) {
    background: #fbfbff;
}

.ckp.is-mungil .ckp__tabs {
    padding: 3px;
    border-radius: 11px;
}
.ckp.is-mungil .ckp__tab {
    min-height: 34px;
    padding: 4px 9px;
    gap: 6px;
}
.ckp.is-mungil .ckp__tabtxt b {
    font-size: 11.5px;
}
.ckp.is-mungil .ckp__dot {
    top: 5px;
    right: 6px;
}

@media (max-width: 560px) {
    .ckp__tabtxt small {
        display: none;
    }
}
</style>
