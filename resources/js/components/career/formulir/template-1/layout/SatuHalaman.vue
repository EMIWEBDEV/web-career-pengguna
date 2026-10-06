<!--
  WEB CAREER — TEMPLATE 1: Pendaftaran Ringkas.

  Satu halaman, pertanyaan datar, tanpa stepper. Dipakai formulir gerbang
  seperti "List Data Form Pendaftaran 1 MT" (varian Politeknik maupun
  Rekrutmen Umum) — keduanya template YANG SAMA, hanya beda skema.

  LAYOUT di-coding di sini; ISI (pertanyaan, opsi, syarat tampil) datang
  dari Skema_Json.
-->
<template>
    <div class="t1">
        <header class="t1__head">
            <div class="t1__badge"><i class="bi bi-card-checklist"></i></div>
            <div>
                <h3>{{ judul }}</h3>
                <p v-if="keterangan">{{ keterangan }}</p>
            </div>
        </header>

        <div v-if="!langkah.length" class="t1__kosong">
            <i class="bi bi-inbox"></i>
            <span>Skema belum punya pertanyaan.</span>
        </div>

        <div v-for="(L, iL) in langkah" :key="iL" class="t1__blok">
            <BagianRenderer
                v-for="(B, iB) in bagianTerlihat(L)"
                :key="iB"
                :bagian="B"
                :jawaban="jawaban"
                :disabled="disabled"
                :konteks-opsi="konteks"
                :galat="galatField"
                @ubah="setNilai"
                @ubah-baris="setNilaiBaris"
                @berkas="(e) => $emit('berkas', e)"
                @hapus-baris="(b, i) => $emit('hapus-baris', b, i)"
            />
        </div>

        <div v-if="galat.length" class="t1__galat">
            <strong><i class="bi bi-exclamation-triangle-fill"></i> Lengkapi dulu:</strong>
            <ul>
                <li v-for="(g, i) in galat" :key="i">{{ g }}</li>
            </ul>
        </div>

        <footer v-if="!disabled" class="t1__foot">
            <button class="t1__kirim" type="button" @click="kirim">
                <i class="bi bi-send-check-fill"></i> {{ labelKirim }}
            </button>
        </footer>
    </div>
</template>

<script setup>
import { computed, ref } from 'vue';
import BagianRenderer from '../../inti/BagianRenderer.vue';
import { bagianTampil, periksaLangkah } from '@utils/formulir/aturan';

const props = defineProps({
    konteks: { type: Object, default: () => ({}) }, // opsi dinamis pembukaan
    skema: { type: Object, required: true },
    modelValue: { type: Object, required: true },
    judul: { type: String, default: 'Formulir Pendaftaran' },
    keterangan: { type: String, default: '' },
    disabled: { type: Boolean, default: false },
    labelKirim: { type: String, default: 'Kirim Formulir' },
});

const emit = defineEmits(['update:modelValue', 'kirim', 'berkas', 'hapus-baris']);

const langkah = computed(() => props.skema?.langkah || []);
const jawaban = computed(() => props.modelValue);
const galat = ref([]);
const galatField = ref({});

function bagianTerlihat(L) {
    return bagianTampil(L.bagian, jawaban.value);
}

/** Cari definisi field berdasarkan key (untuk reset_anak cascade). */
function cariField(key) {
    for (const L of langkah.value) {
        for (const B of L.bagian || []) {
            for (const f of B.field || []) {
                if (f.key === key) return f;
            }
        }
    }
    return null;
}

function setNilai(key, nilai) {
    const patch = { [key]: nilai };
    // Field cascade menandai anak-anaknya di skema: saat induk berubah, anak
    // dikosongkan agar jenjang→jenis→kampus tidak menyimpan nilai basi.
    const f = cariField(key);
    if (f && Array.isArray(f.reset_anak)) {
        f.reset_anak.forEach((k) => { patch[k] = ''; });
    }
    emit('update:modelValue', { ...jawaban.value, ...patch });
}

function setNilaiBaris(kunciBagian, i, key, nilai) {
    const arr = [...(jawaban.value[kunciBagian] || [])];
    arr[i] = { ...arr[i], [key]: nilai };
    setNilai(kunciBagian, arr);
}

function kirim() {
    // Template 1 satu halaman -> semua langkah diperiksa sekaligus.
    galat.value = langkah.value.flatMap((L) => periksaLangkah(L, jawaban.value));
    if (galat.value.length) return;
    emit('kirim', jawaban.value);
}
</script>

<style scoped>
.t1 {
    max-width: 46rem;
    margin: 0 auto;
}

.t1__head {
    display: flex;
    align-items: flex-start;
    gap: 0.8rem;
    padding-bottom: 0.9rem;
    margin-bottom: 1.1rem;
    border-bottom: 1px solid rgba(11, 16, 51, 0.09);
}
.t1__badge {
    flex: none;
    width: 2.5rem;
    height: 2.5rem;
    display: grid;
    place-items: center;
    border-radius: 0.9rem;
    background: linear-gradient(140deg, #4f46e5, #7c3aed);
    color: #fff;
    font-size: 1.1rem;
}
.t1__head h3 {
    margin: 0;
    font-size: 1.1rem;
    font-weight: 800;
    color: #0f172a;
    letter-spacing: -0.015em;
}
.t1__head p {
    margin: 0.15rem 0 0;
    font-size: 12.5px;
    color: #64748b;
    line-height: 1.55;
}

.t1__kosong {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 2rem;
    justify-content: center;
    color: #94a3b8;
    font-size: 13px;
}
.t1__kosong .bi {
    font-size: 1.3rem;
}

.t1__galat {
    margin-top: 1rem;
    padding: 0.75rem 0.9rem;
    border: 1px solid rgba(239, 68, 68, 0.25);
    border-radius: 12px;
    background: rgba(239, 68, 68, 0.07);
    color: #b91c1c;
    font-size: 12.5px;
}
.t1__galat strong {
    display: flex;
    align-items: center;
    gap: 0.35rem;
}
.t1__galat ul {
    margin: 0.4rem 0 0;
    padding-left: 1.2rem;
    line-height: 1.7;
}

.t1__foot {
    margin-top: 1.2rem;
    display: flex;
    justify-content: flex-end;
}
.t1__kirim {
    border: 0;
    border-radius: 0.8rem;
    padding: 0.7rem 1.4rem;
    background: linear-gradient(140deg, #4f46e5, #7c3aed);
    color: #fff;
    font: inherit;
    font-weight: 700;
    font-size: 13px;
    cursor: pointer;
    box-shadow: 0 10px 22px -12px rgba(79, 70, 229, 0.9);
    transition:
        filter 160ms ease,
        transform 160ms ease;
}
.t1__kirim:hover {
    filter: brightness(1.06);
    transform: translateY(-1px);
}
</style>
