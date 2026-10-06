<!-- WEB CAREER — Pemulihan Berkas: PERINGATAN KERAS sebelum menambahkan berkas ke
     isian yang SUDAH berberkas (hanya pemegang hak TIMPA yang sampai ke sini).

     Sengaja BUKAN AdminModal. Dialog biasa berwarna ungu yang sama dengan dialog
     unggah di bawahnya terbaca sebagai "langkah berikutnya", lalu diklik lewat.
     Yang ini merah, berperan `alertdialog`, fokus awalnya di tombol BATAL, dan
     tombol lanjutnya terkunci sampai admin menyatakan sudah memeriksa berkas yang
     ada — tidak ada jalan pintas satu ketukan. -->
<template>
    <teleport to="body">
        <div class="pbk-al-mask wca" @click.self="goyang">
            <div
                ref="kotak"
                class="pbk-al"
                :class="{ 'is-goyang': sedangGoyang }"
                role="alertdialog"
                aria-modal="true"
                :aria-labelledby="idJudul"
                :aria-describedby="idIsi"
                tabindex="-1"
                @keydown.tab="trapFocusWithin"
            >
                <div class="pbk-al__band">
                    <span class="pbk-al__ic" aria-hidden="true"><i class="bi bi-exclamation-octagon-fill"></i></span>
                    <div class="pbk-al__judul">
                        <p class="pbk-al__eyebrow">Peringatan · berkas ganda</p>
                        <h2 :id="idJudul">Isian ini SUDAH berberkas</h2>
                    </div>
                </div>

                <div class="pbk-al__body">
                    <p :id="idIsi" class="pbk-al__lead">
                        Sistem mencatat <b>{{ sudahAda.length }} berkas</b> pada isian <b>“{{ label }}”</b> milik
                        <b>{{ kandidat }}</b>. Unggahan ganda dilarang untuk admin biasa. Akun Anda memegang hak
                        <b>Timpa</b>, jadi Anda bisa melanjutkan, tetapi pastikan dulu berkas yang ada memang keliru atau
                        kurang.
                    </p>

                    <ul class="pbk-al__files">
                        <li v-for="b in sudahAda" :key="b.id" class="pbk-al__file">
                            <i class="bi" :class="ikonBerkas(b.nama)" aria-hidden="true"></i>
                            <div class="pbk-al__fmain">
                                <span class="pbk-al__fnama">{{ b.nama }}</span>
                                <span class="pbk-al__fmeta">
                                    {{ ukuranBerkas(b.ukuran) }} · {{ tglJam(b.waktu) }}
                                    <span v-if="b.olehAdmin" class="pbk-al__admin">Diunggah admin · {{ b.oleh }}</span>
                                </span>
                            </div>
                            <a class="pbk-al__buka" :href="b.url" target="_blank" rel="noopener">
                                <i class="bi bi-box-arrow-up-right"></i><span>Buka</span>
                            </a>
                        </li>
                    </ul>

                    <ul class="pbk-al__akibat">
                        <li>
                            <i class="bi bi-plus-square-dotted" aria-hidden="true"></i>
                            <span>Berkas baru <b>ditambahkan di samping</b> berkas lama. Berkas lama <b>tidak diganti</b> dan tidak dihapus.</span>
                        </li>
                        <li>
                            <i class="bi bi-layout-text-sidebar" aria-hidden="true"></i>
                            <span>Worklist akan menampilkan <b>{{ sudahAda.length + 1 }} berkas</b> untuk isian ini.</span>
                        </li>
                        <li>
                            <i class="bi bi-person-badge" aria-hidden="true"></i>
                            <span>Unggahan tercatat <b>atas nama Anda</b> beserta alasannya, dan tidak bisa dibatalkan dari panel ini.</span>
                        </li>
                    </ul>

                    <label class="pbk-al__ack" :class="{ 'is-on': setuju }">
                        <input v-model="setuju" type="checkbox" :disabled="busy" />
                        <span>Saya sudah membuka berkas yang ada dan memastikan berkas baru ini <b>memang diperlukan</b>.</span>
                    </label>
                </div>

                <div class="pbk-al__foot">
                    <button ref="tombolBatal" type="button" class="wca-btn wca-btn--ghost" :disabled="busy" @click="$emit('batal')">
                        <i class="bi bi-x-lg"></i> Batal, jangan unggah
                    </button>
                    <button type="button" class="wca-btn pbk-al__lanjut" :disabled="!setuju || busy" @click="$emit('lanjut')">
                        <span v-if="busy" class="wca-spin" aria-hidden="true"></span>
                        <i v-else class="bi bi-exclamation-triangle-fill"></i>
                        {{ busy ? 'Mengunggah…' : 'Tetap Tambahkan Berkas' }}
                    </button>
                </div>
            </div>
        </div>
    </teleport>
</template>

<script setup>
import { nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import { useLapisEsc } from '../../../../composables/useLapisEsc';
import { trapFocusWithin } from '@utils/dialogFocus';
import { tglJam } from '@utils/tanggal';
import { ikonBerkas, ukuranBerkas } from '@utils/career/pemulihanBerkas';

const props = defineProps({
    kandidat: { type: String, default: '' },
    label: { type: String, default: '' },
    sudahAda: { type: Array, default: () => [] },
    busy: { type: Boolean, default: false },
});

const emit = defineEmits(['batal', 'lanjut']);

const uid = Math.random().toString(36).slice(2, 8);
const idJudul = `pbk-al-judul-${uid}`;
const idIsi = `pbk-al-isi-${uid}`;

const setuju = ref(false);
const kotak = ref(null);
const tombolBatal = ref(null);
const sedangGoyang = ref(false);
let fokusSebelum = null;
let tm = null;

// Esc = batal, tetapi tidak selagi unggahan berjalan (hasilnya harus ditunggu).
useLapisEsc(() => {
    if (!props.busy) emit('batal');
});

/** Klik di luar TIDAK menutup: dialog ini harus dijawab, bukan dilewati. */
function goyang() {
    if (tm) clearTimeout(tm);
    sedangGoyang.value = true;
    tm = setTimeout(() => (sedangGoyang.value = false), 450);
}

onMounted(async () => {
    fokusSebelum = document.activeElement;
    await nextTick();
    (tombolBatal.value || kotak.value)?.focus();
});

onBeforeUnmount(() => {
    if (tm) clearTimeout(tm);
    if (fokusSebelum && typeof fokusSebelum.focus === 'function') fokusSebelum.focus();
});
</script>

<style scoped>
.pbk-al-mask {
    position: fixed;
    inset: 0;
    /* Di atas AdminModal (1200) yang memanggilnya, di bawah toast. */
    z-index: 1400;
    display: grid;
    place-items: center;
    padding: 16px;
    background: rgba(69, 10, 10, 0.55);
    backdrop-filter: blur(6px);
    -webkit-backdrop-filter: blur(6px);
    animation: pbkAlFade 0.18s ease both;
}
.pbk-al {
    width: min(560px, 100%);
    max-height: calc(100dvh - 32px);
    display: flex;
    flex-direction: column;
    overflow: hidden;
    background: #fff;
    border-radius: 22px;
    border: 2px solid #fca5a5;
    box-shadow:
        0 40px 90px rgba(127, 29, 29, 0.45),
        0 0 0 6px rgba(239, 68, 68, 0.14);
    outline: none;
    animation: pbkAlMasuk 0.36s cubic-bezier(0.22, 1, 0.36, 1) both;
}
.pbk-al.is-goyang {
    animation: pbkAlGoyang 0.42s ease both;
}
.pbk-al__band {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 18px 22px;
    color: #fff;
    background:
        repeating-linear-gradient(-45deg, rgba(255, 255, 255, 0.07) 0 12px, transparent 12px 24px),
        linear-gradient(135deg, #b91c1c, #dc2626 55%, #ef4444);
}
.pbk-al__ic {
    flex: none;
    width: 52px;
    height: 52px;
    border-radius: 16px;
    display: grid;
    place-items: center;
    font-size: 1.6rem;
    background: rgba(255, 255, 255, 0.18);
    box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.35);
    animation: pbkAlDenyut 1.6s ease-in-out infinite;
}
.pbk-al__judul {
    min-width: 0;
}
.pbk-al__eyebrow {
    margin: 0 0 2px;
    font-size: 0.68rem;
    font-weight: 900;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    opacity: 0.85;
}
.pbk-al__judul h2 {
    margin: 0;
    font-size: 1.22rem;
    font-weight: 900;
    letter-spacing: -0.01em;
    line-height: 1.25;
}
.pbk-al__body {
    padding: 18px 22px 6px;
    overflow-y: auto;
    overscroll-behavior: contain;
}
.pbk-al__lead {
    margin: 0 0 14px;
    font-size: 0.9rem;
    line-height: 1.6;
    color: #3f1d1d;
}
.pbk-al__files {
    list-style: none;
    margin: 0 0 14px;
    padding: 0;
    display: grid;
    gap: 8px;
}
.pbk-al__file {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 12px;
    border-radius: 14px;
    background: #fef2f2;
    border: 1px solid #fecaca;
}
.pbk-al__file > i {
    flex: none;
    font-size: 1.5rem;
    color: #dc2626;
}
.pbk-al__fmain {
    min-width: 0;
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 2px;
}
.pbk-al__fnama {
    font-weight: 800;
    font-size: 0.86rem;
    color: #450a0a;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.pbk-al__fmeta {
    font-size: 0.74rem;
    color: #7f1d1d;
    display: flex;
    flex-wrap: wrap;
    gap: 4px 8px;
    align-items: center;
}
.pbk-al__admin {
    padding: 1px 8px;
    border-radius: 999px;
    background: #fee2e2;
    color: #991b1b;
    font-weight: 800;
}
.pbk-al__buka {
    flex: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    min-height: 40px;
    padding: 0 12px;
    border-radius: 10px;
    font-size: 0.8rem;
    font-weight: 800;
    color: #b91c1c;
    background: #fff;
    border: 1px solid #fca5a5;
    text-decoration: none;
}
.pbk-al__buka:hover {
    background: #fff5f5;
}
.pbk-al__akibat {
    list-style: none;
    margin: 0 0 14px;
    padding: 0;
    display: grid;
    gap: 8px;
}
.pbk-al__akibat li {
    display: flex;
    gap: 10px;
    font-size: 0.84rem;
    line-height: 1.5;
    color: #334155;
}
.pbk-al__akibat i {
    flex: none;
    margin-top: 2px;
    color: #dc2626;
}
.pbk-al__ack {
    display: flex;
    gap: 12px;
    align-items: flex-start;
    padding: 12px 14px;
    border-radius: 14px;
    border: 2px dashed #fca5a5;
    background: #fffafa;
    font-size: 0.85rem;
    line-height: 1.5;
    color: #450a0a;
    cursor: pointer;
    transition:
        border-color 0.15s ease,
        background 0.15s ease;
}
.pbk-al__ack.is-on {
    border-style: solid;
    border-color: #dc2626;
    background: #fef2f2;
}
.pbk-al__ack input {
    flex: none;
    width: 20px;
    height: 20px;
    margin-top: 1px;
    accent-color: #dc2626;
    cursor: pointer;
}
.pbk-al__foot {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    padding: 14px 22px 18px;
    border-top: 1px solid #fee2e2;
    background: #fff;
}
.pbk-al__foot .wca-btn {
    min-height: 44px;
}
.pbk-al__lanjut {
    background: linear-gradient(120deg, #b91c1c, #dc2626);
    color: #fff;
    box-shadow: 0 10px 22px rgba(220, 38, 38, 0.35);
}
.pbk-al__lanjut:disabled {
    background: #fca5a5;
    box-shadow: none;
    cursor: not-allowed;
}
@keyframes pbkAlFade {
    from { opacity: 0; }
}
@keyframes pbkAlMasuk {
    from { opacity: 0; transform: translateY(18px) scale(0.96); }
}
@keyframes pbkAlGoyang {
    20% { transform: translateX(-8px); }
    40% { transform: translateX(7px); }
    60% { transform: translateX(-5px); }
    80% { transform: translateX(3px); }
}
@keyframes pbkAlDenyut {
    50% { box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.35), 0 0 0 8px rgba(255, 255, 255, 0.12); }
}
@media (prefers-reduced-motion: reduce) {
    .pbk-al,
    .pbk-al.is-goyang,
    .pbk-al__ic,
    .pbk-al-mask {
        animation: none;
    }
}
/* Ponsel: menjadi lembar dari bawah, tombol selebar layar. */
@media (max-width: 560px) {
    .pbk-al-mask {
        place-items: end stretch;
        padding: 0;
    }
    .pbk-al {
        width: 100%;
        max-height: 94dvh;
        border-radius: 22px 22px 0 0;
        border-bottom: none;
    }
    .pbk-al__band {
        padding: 16px 18px;
    }
    .pbk-al__body {
        padding: 16px 18px 4px;
    }
    .pbk-al__foot {
        flex-direction: column-reverse;
        padding: 12px 18px calc(14px + env(safe-area-inset-bottom));
    }
    .pbk-al__foot .wca-btn {
        width: 100%;
    }
    .pbk-al__buka span {
        display: none;
    }
}
</style>
