<!--
  DAFTAR BATANG — sebaran satu dimensi (kampus, jurusan, jenjang, sumber,
  departemen). Dipakai panel Rekrutmen dan Magang; dibuat satu komponen karena
  kalau tidak, pola yang sama akan ditulis lima kali dengan lima gaya berbeda.

  SATU warna untuk semua batang, bukan gradasi "makin besar makin gelap":
  panjang batang sudah memikul magnitudo, jadi mewarnainya lagi menggandakan
  informasi yang sama sekaligus melanggar pemeriksaan kategorikal (ramp
  membentang melewati rentang lightness). Kategori di sini juga nominal —
  kampus dan jurusan tidak punya urutan alami.

  Angka pastinya SELALU tertulis di sebelah batang, jadi nilainya tidak pernah
  hanya bisa dibaca dari panjang atau warna.
-->
<template>
    <div>
        <KeadaanPanel v-if="!items.length" keadaan="kosong" rapat :ikon="ikon" :teks="teksKosong" :ket="ketKosong" />

        <div v-else class="db-wrapper">
            <ul class="db-list">
                <li v-for="(it, i) in itemsPaginated" :key="i">
                    <span class="db-nama" :title="it.nama">{{ it.nama }}</span>
                    <span class="db-rel">
                        <span class="db-bar" :style="{ width: lebar(it) + '%', background: warna }"></span>
                    </span>
                    <span class="db-jml">{{ angka(it.jml) }}<small v-if="satuan"> {{ satuan }}</small></span>
                </li>
            </ul>

            <!-- Bar Paginasi jika item > batas perHal -->
            <div v-if="items.length > perHal" class="db-paginasi">
                <span class="db-paginasi__info">
                    {{ (hal - 1) * perHal + 1 }}–{{ Math.min(hal * perHal, items.length) }} dari {{ items.length }}
                </span>
                <div class="db-paginasi__nav">
                    <button type="button" class="db-paginasi__btn" :disabled="hal <= 1" :onClick="hal <= 1 ? null : () => hal--">
                        <i class="bi bi-chevron-left"></i>
                    </button>
                    <span class="db-paginasi__page">{{ hal }}/{{ totalHal }}</span>
                    <button type="button" class="db-paginasi__btn" :disabled="hal >= totalHal" @click="hal++">
                        <i class="bi bi-chevron-right"></i>
                    </button>
                </div>
            </div>

            <p v-if="terpotong && items.length <= perHal" class="db-nota">Menampilkan {{ items.length }} teratas.</p>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import { angka } from '@utils/career/dashboard';
import KeadaanPanel from '../../monitoring/KeadaanPanel.vue';

const props = defineProps({
    items: { type: Array, default: () => [] },
    warna: { type: String, default: '#6366f1' },
    satuan: { type: String, default: '' },
    ikon: { type: String, default: 'bi-bar-chart' },
    teksKosong: { type: String, default: 'Belum ada data' },
    ketKosong: { type: String, default: '' },
    terpotong: { type: Boolean, default: false },
    perHal: { type: Number, default: 5 }, // Default 5 baris per halaman
});

const hal = ref(1);

watch(() => props.items, () => {
    hal.value = 1;
});

const maks = computed(() => Math.max(1, ...props.items.map((i) => i.jml || 0)));
const lebar = (it) => Math.max(it.jml > 0 ? 2 : 0, ((it.jml || 0) / maks.value) * 100);

const totalHal = computed(() => Math.max(1, Math.ceil(props.items.length / props.perHal)));
const itemsPaginated = computed(() => {
    if (!props.perHal) return props.items;
    const start = (hal.value - 1) * props.perHal;
    return props.items.slice(start, start + props.perHal);
});
</script>

<style scoped>
.db-list { list-style: none; margin: 0; padding: 0; display: grid; gap: 8px; }
.db-list > li { display: grid; grid-template-columns: minmax(90px, 1.1fr) minmax(60px, 2fr) auto; align-items: center; gap: 10px; }

.db-nama {
    font-size: 0.75rem;
    font-weight: 700;
    color: #334155;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
/* Batang tipis, ujung data membulat 4px, menempel ke garis dasar kiri. */
.db-rel { height: 9px; border-radius: 999px; background: #f1f5f9; overflow: hidden; }
.db-bar { display: block; height: 100%; border-radius: 0 4px 4px 0; }
.db-jml {
    font-size: 0.76rem;
    font-weight: 900;
    color: #0f172a;
    font-variant-numeric: tabular-nums;
    text-align: right;
    min-width: 34px;
}
.db-jml small { font-weight: 700; color: #94a3b8; }

.db-nota { margin: 9px 0 0; font-size: 0.7rem; color: #94a3b8; }

.db-paginasi {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    margin-top: 8px;
    padding-top: 6px;
    border-top: 1px dashed #f1f5f9;
    font-size: 0.7rem;
    color: #64748b;
}
.db-paginasi__info { font-weight: 600; }
.db-paginasi__nav {
    display: flex;
    align-items: center;
    gap: 5px;
}
.db-paginasi__btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 22px;
    height: 22px;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    background: #ffffff;
    color: #334155;
    font-size: 0.65rem;
    cursor: pointer;
    transition: all 0.15s ease;
}
.db-paginasi__btn:disabled {
    opacity: 0.4;
    cursor: not-allowed;
}
.db-paginasi__btn:hover:not(:disabled) {
    border-color: #6366f1;
    color: #6366f1;
}
.db-paginasi__page {
    font-weight: 800;
    color: #0f172a;
    font-size: 0.7rem;
}

@media (max-width: 520px) {
    .db-list > li { grid-template-columns: 1fr auto; }
    .db-rel { grid-column: 1 / -1; order: 3; }
}
</style>
