<!-- WEB CAREER — FILTER PROGRAM (WCA NATIVE CARD) -->
<template>
    <div class="wca-card wca-filter-card">
        <div class="wca-card__head">
            <h3><i class="bi bi-funnel-fill"></i> Filter &amp; Penyaring Program</h3>
            <span class="wca-badge wca-b--indigo">
                Menampilkan <b>{{ jumlahTampil }}</b> dari {{ jumlahTotal }} program
            </span>
        </div>
        <div class="wca-card__body">
            <div class="wcm-filter__baris">
                <div class="wcm-search-input">
                    <i class="bi bi-search"></i>
                    <input :value="nilai.cari" type="text" placeholder="Cari nama, kode, atau penyelenggara program…"
                        @input="ubah('cari', $event.target.value)" />
                </div>

                <span v-for="s in selectors" :key="s.key" class="wcm-filter__sel">
                    <el-select :model-value="nilai[s.key]" :placeholder="s.label" clearable
                        :filterable="s.opsi.length > 6" @update:model-value="ubah(s.key, $event ?? '')">
                        <el-option v-for="o in s.opsi" :key="o.v" :label="o.t" :value="o.v" />
                    </el-select>
                </span>

                <span class="wcm-filter__sel wcm-filter__sel--urut">
                    <el-select :model-value="nilai.urut" @update:model-value="ubah('urut', $event)">
                        <el-option v-for="u in URUT" :key="u.val" :label="u.label" :value="u.val" />
                    </el-select>
                </span>
            </div>

            <div class="wcm-filter__kondisi-wrapper">
                <span class="wcm-filter__ket">Kondisi Cepat</span>
                <div class="wcm-filter__chips">
                    <button v-for="k in KONDISI" :key="k.val" type="button" class="wcm-chip"
                        :class="{ 'is-active': nilai.kondisi === k.val }"
                        :title="k.ket" @click="ubah('kondisi', nilai.kondisi === k.val ? '' : k.val)">
                        <i class="bi" :class="k.ikon"></i> {{ k.label }}
                    </button>
                </div>
                <button v-if="adaFilter" type="button" class="wca-btn wca-btn--ghost wca-btn--sm wcm-reset-btn" @click="$emit('reset')">
                    <i class="bi bi-x-circle"></i> Reset Filter
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    nilai: { type: Object, required: true },
    programs: { type: Array, default: () => [] },   // seluruh program (belum difilter)
    jumlahTampil: { type: Number, default: 0 },
    jumlahTotal: { type: Number, default: 0 },
    adaFilter: { type: Boolean, default: false },
});
const emit = defineEmits(['ubah', 'reset']);

const KONDISI = [
    { val: 'SIAP', label: 'Ada siap diputus', ikon: 'bi-hammer', ket: 'Program dengan keputusan menunggu diketuk' },
    { val: 'MACET', label: 'Ada yang tertahan', ikon: 'bi-exclamation-octagon', ket: 'Program dengan pelamar melewati ambang lama menunggu' },
    { val: 'NUNGGU_TES', label: 'Menunggu hasil tes', ikon: 'bi-hourglass-split', ket: 'Program menunggu hasil tes pihak ke-3' },
    { val: 'AKTIF', label: 'Ada pelamar aktif', ikon: 'bi-people', ket: 'Program yang sedang memproses pelamar' },
    { val: 'KUOTA_PENUH', label: 'Kuota penuh', ikon: 'bi-door-closed', ket: 'Kursi diterima sudah memenuhi kuota' },
    { val: 'KOSONG', label: 'Belum ada pelamar', ikon: 'bi-inbox', ket: 'Program yang belum kedatangan pelamar' },
    { val: 'DITAHAN', label: 'Ada yang ditahan', ikon: 'bi-pause-circle', ket: 'Program dengan kandidat sedang di-HOLD' },
    { val: 'PASCAPENERIMAAN', label: 'Proses administrasi', ikon: 'bi-file-earmark-check', ket: 'Program dengan kandidat diterima yang masih menjalani tahap administratif' },
];

const URUT = [
    { val: 'perhatian', label: 'Paling perlu perhatian' },
    { val: 'sibuk', label: 'Paling banyak pelamar aktif' },
    { val: 'terisi', label: 'Kuota paling terisi' },
    { val: 'nama', label: 'Nama A-Z' },
];

/** Kumpulkan nilai unik satu kolom dari data program. */
function opsiDari(ambil) {
    return [...new Set(props.programs.map(ambil).filter(Boolean))]
        .sort((a, b) => String(a).localeCompare(String(b)))
        .map((v) => ({ v, t: v }));
}

const selectors = computed(() => [
    { key: 'status', label: 'Status program', opsi: opsiDari((p) => p.status) },
    { key: 'kategori', label: 'Kategori', opsi: opsiDari((p) => p.kategori) },
    { key: 'alur', label: 'Alur', opsi: opsiDari((p) => p.alur) },
    { key: 'penyelenggara', label: 'Penyelenggara', opsi: opsiDari((p) => p.penyelenggara) },
].filter((s) => s.opsi.length > 1));

function ubah(key, val) {
    emit('ubah', { key, val });
}
</script>

<style scoped>
.wca-filter-card {
    margin-bottom: 20px;
    border-radius: 20px;
    background: #ffffff;
    border: 1px solid rgba(226, 232, 240, 0.95);
    box-shadow: 0 4px 20px rgba(15, 23, 42, 0.04);
}
.wca-card__head {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 16px 20px;
    border-bottom: 1px solid #f1f5f9;
}
.wca-card__head h3 {
    margin: 0;
    font-size: 0.95rem;
    font-weight: 800;
    color: #0f172a;
    display: flex;
    align-items: center;
    gap: 8px;
}
.wca-card__head h3 i {
    color: #6366f1;
}
.wca-badge.wca-b--indigo {
    background: #eef2ff;
    color: #4338ca;
    border: 1px solid rgba(99, 102, 241, 0.2);
    font-weight: 700;
    padding: 4px 12px;
    border-radius: 999px;
    font-size: 0.76rem;
}
.wca-card__body {
    padding: 16px 20px;
    display: flex;
    flex-direction: column;
    gap: 14px;
}
.wcm-filter__baris {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}
/* 🏛️ UNIFORM ELEMENT PLUS SELECT DROPDOWNS */
:deep(.el-select) {
    width: 100%;
}
:deep(.el-input__wrapper) {
    border-radius: 12px !important;
    background-color: #f8fafc !important;
    box-shadow: 0 0 0 1px #e2e8f0 inset !important;
    height: 40px !important;
    padding: 0 14px !important;
    transition: all 0.2s ease !important;
}
:deep(.el-input__wrapper:hover) {
    box-shadow: 0 0 0 1px #cbd5e1 inset !important;
}
:deep(.el-input__wrapper.is-focus) {
    background-color: #ffffff !important;
    box-shadow: 0 0 0 1px #6366f1 inset, 0 0 0 3px rgba(99, 102, 241, 0.15) !important;
}
:deep(.el-input__inner) {
    font-size: 0.82rem !important;
    font-weight: 600 !important;
    color: #0f172a !important;
}

/* 🏛️ UNIFORM SEARCH INPUT */
.wcm-search-input {
    flex: 1 1 260px;
    min-width: 220px;
    height: 40px;
    border-radius: 12px;
    border: 1px solid #e2e8f0;
    background: #f8fafc;
    padding: 0 14px;
    display: flex;
    align-items: center;
    gap: 8px;
    transition: all 0.2s ease;
}
.wcm-search-input:hover {
    border-color: #cbd5e1;
}
.wcm-search-input:focus-within {
    border-color: #6366f1;
    background: #ffffff;
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15);
}
.wcm-search-input i {
    color: #6366f1;
    font-size: 0.9rem;
    flex-shrink: 0;
}
.wcm-search-input input {
    border: none !important;
    outline: none !important;
    background: transparent !important;
    font-size: 0.82rem;
    font-weight: 600;
    color: #0f172a;
    width: 100%;
    padding: 0 !important;
    box-shadow: none !important;
}
.wcm-filter__sel {
    min-width: 155px;
}
.wcm-filter__sel--urut {
    min-width: 200px;
    margin-left: auto;
}
.wcm-filter__kondisi-wrapper {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
    background: #f8fafc;
    padding: 10px 14px;
    border-radius: 14px;
    border: 1px solid #f1f5f9;
}
.wcm-filter__ket {
    font-size: 0.72rem;
    color: #64748b;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    white-space: nowrap;
}
.wcm-filter__chips {
    display: flex;
    align-items: center;
    gap: 6px;
    flex-wrap: wrap;
    flex: 1;
}
.wcm-chip {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    border: 1px solid rgba(226, 232, 240, 0.95);
    background: #ffffff;
    padding: 5px 12px;
    border-radius: 999px;
    font-size: 0.75rem;
    font-weight: 700;
    color: #64748b;
    cursor: pointer;
    transition: all 0.2s ease;
}
.wcm-chip:hover {
    border-color: rgba(99, 102, 241, 0.35);
    color: #4f46e5;
    transform: translateY(-1px);
}
.wcm-chip.is-active {
    border-color: rgba(99, 102, 241, 0.4);
    background: #eef2ff;
    color: #4f46e5;
    font-weight: 800;
    box-shadow: 0 2px 8px rgba(99, 102, 241, 0.12);
}
.wcm-reset-btn {
    margin-left: auto;
    color: #ef4444;
}
@media (max-width: 720px) {
    .wcm-filter__sel--urut { margin-left: 0; }
    .wcm-reset-btn { margin-left: 0; }
}
</style>
