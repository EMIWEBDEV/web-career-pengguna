<!--
  ZONA C — TREN LAMARAN (Redesigned)
-->
<template>
    <div v-if="!tren.titik.length">
        <KeadaanPanel keadaan="kosong" ikon="bi-graph-up"
            teks="Belum ada lamaran pada periode ini"
            ket="Coba perlebar periode, atau tunggu lamaran pertama masuk." />
    </div>

    <div v-else class="tr-container">
        <!-- Label langsung & Rincian Total -->
        <div class="tr-total">
            <div v-for="s in seri" :key="s.name" class="tr-stat-pill" :style="{ '--seri-color': s.color }">
                <span class="tr-stat-pill__dot"></span>
                <div class="tr-stat-pill__info">
                    <span class="tr-stat-pill__val">{{ angka(s.total) }}</span>
                    <span class="tr-stat-pill__lbl">{{ s.name }}</span>
                </div>
                <small class="tr-stat-pill__ket">{{ s.ket }}</small>
            </div>

            <button type="button" class="tr-alih" @click="tabel = !tabel">
                <i class="bi" :class="tabel ? 'bi-graph-up-arrow' : 'bi-table'"></i>
                <span>{{ tabel ? 'Tampilkan grafik' : 'Tampilkan tabel' }}</span>
            </button>
        </div>

        <!-- Tabel padanan setara -->
        <div v-if="tabel" class="tr-tabel-wrapper">
            <!-- Toolbar Tabel -->
            <div class="tr-toolbar">
                <div class="tr-search">
                    <i class="bi bi-search"></i>
                    <input v-model="cari" type="text" placeholder="Cari tanggal atau bulan..." />
                    <button v-if="cari" type="button" class="tr-search-clear" @click="cari = ''">
                        <i class="bi bi-x-circle-fill"></i>
                    </button>
                </div>
                <div class="tr-per-hal">
                    <span>Tampil:</span>
                    <button v-for="opt in [7, 14, 30, 0]" :key="opt" type="button"
                        class="tr-per-hal__btn" :class="{ 'is-on': perHal === opt }"
                        @click="perHal = opt">
                        {{ opt === 0 ? 'Semua' : `${opt} Hari` }}
                    </button>
                </div>
            </div>

            <div class="wcd-tw tr-tw">
                <table class="wcd-tbl">
                    <thead>
                        <tr>
                            <th>Tanggal</th>
                            <th class="wcd-num">Lamaran Masuk</th>
                            <th class="wcd-num">Diterima</th>
                            <th class="wcd-num">Konversi Harian</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="t in barisPaginated" :key="t.tgl">
                            <td class="wcd-tbl__utama">{{ tanggal(t.tgl) }}</td>
                            <td class="wcd-num"><b>{{ angka(t.masuk) }}</b></td>
                            <td class="wcd-num">
                                <span class="wcd-lb" style="background:#f0fdf4; color:#059669; font-weight:800;">
                                    {{ angka(t.diterima) }}
                                </span>
                            </td>
                            <td class="wcd-num">
                                <span v-if="t.masuk" class="wcd-lb" :style="nadaKonvHarian(t)">
                                    {{ Math.round((t.diterima / t.masuk) * 100) }}%
                                </span>
                                <span v-else class="tr-nol">—</span>
                            </td>
                        </tr>
                        <tr v-if="!barisTersaring.length">
                            <td colspan="4" class="tr-empty-td">
                                Tidak ada data tanggal yang cocok dengan "{{ cari }}".
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Bar Paginasi -->
            <div v-if="barisTersaring.length && (totalHal > 1 || perHal > 0)" class="tr-paginasi">
                <span class="tr-paginasi__info">
                    Menampilkan <b>{{ perHal === 0 ? 1 : ((hal - 1) * perHal) + 1 }} - {{ perHal === 0 ? barisTersaring.length : Math.min(hal * perHal, barisTersaring.length) }}</b> dari <b>{{ barisTersaring.length }}</b> hari beraktivitas
                </span>
                <div v-if="totalHal > 1" class="tr-paginasi__nav">
                    <button type="button" class="tr-paginasi__btn" :disabled="hal <= 1" :onClick="hal <= 1 ? null : () => hal--">
                        <i class="bi bi-chevron-left"></i> Sebelum
                    </button>
                    <span class="tr-paginasi__page">{{ hal }} / {{ totalHal }}</span>
                    <button type="button" class="tr-paginasi__btn" :disabled="hal >= totalHal" @click="hal++">
                        Lanjut <i class="bi bi-chevron-right"></i>
                    </button>
                </div>
            </div>

            <p v-if="titikTerpakai.length < tren.titik.length" class="tr-nota">
                Hanya hari yang memiliki aktivitas ditampilkan ({{ titikTerpakai.length }} dari {{ tren.titik.length }} hari).
            </p>
        </div>

        <div v-else class="tr-chart-wrapper">
            <apexchart type="area" height="300" :options="opsi" :series="seriChart" />
        </div>
    </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import apexchart from 'vue3-apexcharts';
import { angka, tanggal, SERI, INK } from '@utils/career/dashboard';
import KeadaanPanel from '../../monitoring/KeadaanPanel.vue';

const props = defineProps({ tren: { type: Object, default: () => ({ titik: [], totalMasuk: 0, totalDiterima: 0 }) } });

const tabel = ref(false);
const cari = ref('');
const hal = ref(1);
const perHal = ref(7); // Default 7 hari per halaman

const seri = computed(() => [
    { name: 'Masuk', color: SERI.masuk, total: props.tren.totalMasuk, ket: 'per tanggal melamar' },
    { name: 'Diterima', color: SERI.diterima, total: props.tren.totalDiterima, ket: 'per tanggal keputusan' },
]);

const titikTerpakai = computed(() => props.tren.titik.filter((t) => t.masuk || t.diterima));

const barisTersaring = computed(() => {
    const list = titikTerpakai.value;
    if (!cari.value.trim()) return list;
    const q = cari.value.toLowerCase().trim();
    return list.filter((t) => {
        const tglStr = tanggal(t.tgl).toLowerCase();
        return tglStr.includes(q) || t.tgl.includes(q);
    });
});

watch([cari, perHal, tabel], () => {
    hal.value = 1;
});

const totalHal = computed(() => {
    if (perHal.value === 0) return 1;
    return Math.max(1, Math.ceil(barisTersaring.value.length / perHal.value));
});

const barisPaginated = computed(() => {
    if (perHal.value === 0) return barisTersaring.value;
    const start = (hal.value - 1) * perHal.value;
    return barisTersaring.value.slice(start, start + perHal.value);
});

function nadaKonvHarian(t) {
    if (!t.masuk) return { background: '#f1f5f9', color: '#64748b' };
    const pct = Math.round((t.diterima / t.masuk) * 100);
    if (pct >= 50) return { background: '#ecfdf5', color: '#047857' };
    if (pct >= 20) return { background: '#f0f9ff', color: '#0369a1' };
    return { background: '#fef2f2', color: '#b91c1c' };
}

const seriChart = computed(() => [
    { name: 'Masuk', data: props.tren.titik.map((t) => [new Date(t.tgl).getTime(), t.masuk]) },
    { name: 'Diterima', data: props.tren.titik.map((t) => [new Date(t.tgl).getTime(), t.diterima]) },
]);

const opsi = computed(() => ({
    chart: {
        type: 'area',
        fontFamily: 'Inter, system-ui, -apple-system, sans-serif',
        toolbar: { show: false },
        zoom: { enabled: false },
        animations: { enabled: true, speed: 350 },
    },
    colors: [SERI.masuk, SERI.diterima],
    stroke: { curve: 'smooth', width: 3.5 },
    markers: { size: 0, strokeWidth: 2, strokeColors: '#fff', hover: { size: 6 } },
    fill: {
        type: 'gradient',
        gradient: { shadeIntensity: 0, opacityFrom: 0.28, opacityTo: 0.03, stops: [0, 100] },
    },
    dataLabels: { enabled: false },
    legend: {
        show: true,
        position: 'top',
        horizontalAlign: 'left',
        fontSize: '13px',
        fontWeight: 700,
        markers: { width: 10, height: 10, radius: 10 },
        labels: { colors: INK.kedua },
        itemMargin: { horizontal: 12 },
    },
    grid: {
        borderColor: INK.grid,
        strokeDashArray: 0,
        xaxis: { lines: { show: false } },
        padding: { left: 8, right: 14, top: 0 },
    },
    xaxis: {
        type: 'datetime',
        axisBorder: { color: INK.sumbu },
        axisTicks: { color: INK.sumbu },
        labels: {
            style: { colors: INK.redup, fontSize: '11px', fontWeight: 600 },
            datetimeFormatter: { day: 'dd MMM', month: 'MMM yy' },
        },
        tooltip: { enabled: false },
    },
    yaxis: {
        min: 0,
        forceNiceScale: true,
        labels: {
            style: { colors: INK.redup, fontSize: '11px', fontWeight: 600 },
            formatter: (v) => (Number.isInteger(v) ? v : ''),
        },
    },
    tooltip: {
        shared: true,
        intersect: false,
        x: { format: 'dd MMM yyyy' },
        style: { fontSize: '12px' },
    },
}));
</script>

<style scoped>
.tr-container {
    padding-top: 4px;
}

.tr-total {
    display: flex;
    align-items: center;
    gap: 16px;
    flex-wrap: wrap;
    margin-bottom: 14px;
}

.tr-stat-pill {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    padding: 10px 16px;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    box-shadow: 0 2px 6px rgba(15, 23, 42, 0.04);
}

.tr-stat-pill__dot {
    width: 10px;
    height: 10px;
    border-radius: 999px;
    background: var(--seri-color, #6366f1);
    flex: none;
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--seri-color, #6366f1) 25%, transparent);
}

.tr-stat-pill__info {
    display: flex;
    align-items: baseline;
    gap: 6px;
}

.tr-stat-pill__val {
    font-size: 1.2rem;
    font-weight: 900;
    color: #0f172a;
    line-height: 1;
}

.tr-stat-pill__lbl {
    font-size: 0.8rem;
    font-weight: 800;
    color: #334155;
}

.tr-stat-pill__ket {
    font-size: 0.72rem;
    color: #94a3b8;
    font-weight: 500;
}

.tr-alih {
    margin-left: auto;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    border: 1px solid #c7d2fe;
    background: #eef2ff;
    padding: 8px 16px;
    border-radius: 12px;
    font: inherit;
    font-size: 0.78rem;
    font-weight: 800;
    color: #4f46e5;
    cursor: pointer;
    transition: all 0.2s ease;
}
.tr-alih:hover { background: #4f46e5; color: #ffffff; border-color: #4f46e5; transform: translateY(-1px); }

/* ══════════ TOOLBAR PENCARIAN & PAGINASI TABEL ══════════ */
.tr-tabel-wrapper {
    display: flex;
    flex-direction: column;
    gap: 8px;
    margin-top: 6px;
}

.tr-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 4px;
    flex-wrap: wrap;
}

.tr-search {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 6px 12px;
    border: 1.5px solid #cbd5e1;
    border-radius: 10px;
    background: #ffffff;
    flex: 1;
    max-width: 280px;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);
}
.tr-search i { color: #94a3b8; font-size: 0.85rem; }
.tr-search input {
    width: 100%;
    border: 0;
    outline: 0;
    font: inherit;
    font-size: 0.76rem;
    font-weight: 700;
    color: #0f172a;
    background: transparent;
}
.tr-search-clear {
    border: 0;
    background: transparent;
    color: #cbd5e1;
    cursor: pointer;
    padding: 0;
}
.tr-search-clear:hover { color: #ef4444; }

.tr-per-hal {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 0.72rem;
    font-weight: 700;
    color: #64748b;
}
.tr-per-hal__btn {
    padding: 4px 9px;
    border: 1.5px solid #cbd5e1;
    border-radius: 8px;
    background: #ffffff;
    color: #334155;
    font: inherit;
    font-size: 0.7rem;
    font-weight: 800;
    cursor: pointer;
    transition: all 0.15s ease;
}
.tr-per-hal__btn.is-on {
    background: #4f46e5;
    border-color: #4f46e5;
    color: #ffffff;
}

.tr-tw {
    margin-top: 0;
    max-height: 380px;
    overflow-y: auto;
    border-radius: 14px;
    border: 1.5px solid #e2e8f0;
    position: relative;
}
.tr-tw::-webkit-scrollbar {
    width: 6px;
}
.tr-tw::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 99px;
}
.tr-tw standard thead th,
.tr-tw .wcd-tbl thead th {
    position: sticky;
    top: 0;
    z-index: 2;
    background: #f8fafc !important;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
}

.tr-nol { font-size: 0.72rem; color: #cbd5e1; font-weight: 700; }
.tr-empty-td { text-align: center; color: #64748b; padding: 24px 12px !important; font-size: 0.78rem; font-weight: 600; }

.tr-paginasi {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-top: 4px;
    padding: 8px 14px;
    background: #f8fafc;
    border: 1.5px solid #e2e8f0;
    border-radius: 10px;
    font-size: 0.74rem;
    color: #475569;
}
.tr-paginasi__info b { color: #0f172a; font-weight: 800; }
.tr-paginasi__nav {
    display: flex;
    align-items: center;
    gap: 8px;
}
.tr-paginasi__btn {
    display: flex;
    align-items: center;
    gap: 4px;
    padding: 4px 10px;
    border: 1.5px solid #cbd5e1;
    border-radius: 8px;
    background: #ffffff;
    color: #1e293b;
    font: inherit;
    font-size: 0.72rem;
    font-weight: 800;
    cursor: pointer;
    transition: all 0.15s ease;
}
.tr-paginasi__btn:disabled {
    opacity: 0.4;
    cursor: not-allowed;
}
.tr-paginasi__btn:hover:not(:disabled) {
    border-color: #4f46e5;
    color: #4f46e5;
}
.tr-paginasi__page {
    font-weight: 800;
    color: #0f172a;
}

.tr-nota { margin: 6px 4px 0; font-size: 0.75rem; color: #94a3b8; }
.tr-chart-wrapper { padding: 8px 0; }
</style>
