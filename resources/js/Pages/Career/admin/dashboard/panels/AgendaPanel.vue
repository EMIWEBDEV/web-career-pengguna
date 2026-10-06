<!--
  ZONA C — AGENDA & KALENDER INTERAKTIF 14 HARI (ULTRA MODERN UI/UX).

  Fitur Utama:
   1. Multi-View Switcher: Grid Kalender 14 Hari, Timeline Kronologis, & Distribusi Kategori.
   2. Filter Chips & Pencarian Real-time untuk navigasi cepat.
   3. Highlight "Hari Ini" dengan indikator animasi pulse & status relatif (Hari ini, Besok, X hari lagi).
   4. Popover / Modal Detail Agenda Interaktif dengan aksi & rincian lengkap.
   5. Estetika Glassmorphism, Micro-interactions, & Responsif.
-->
<template>
    <div v-if="!agenda.length" class="ag-empty-wrap">
        <KeadaanPanel keadaan="kosong" ikon="bi-calendar-week"
            teks="Tidak ada agenda dalam 14 hari ke depan"
            ket="Sesi tes, agenda program, dan penutupan pendaftaran akan muncul secara otomatis di sini." />
    </div>

    <div v-else class="ag-container">
        <!-- ══════════════ BILAH KONTROL UAT / FILTER ══════════════ -->
        <div class="ag-controls">
            <!-- Pilihan Mode Tampilan -->
            <div class="ag-mode-toggle" role="tablist" aria-label="Mode Tampilan Kalender">
                <button type="button" role="tab" :aria-selected="tampilanModus === 'timeline'"
                    class="ag-mode-btn" :class="{ 'is-active': tampilanModus === 'timeline' }"
                    @click="tampilanModus = 'timeline'" title="Tampilan Timeline Kronologis">
                    <i class="bi bi-view-list"></i>
                    <span>Timeline</span>
                </button>

                <button type="button" role="tab" :aria-selected="tampilanModus === 'kalender'"
                    class="ag-mode-btn" :class="{ 'is-active': tampilanModus === 'kalender' }"
                    @click="tampilanModus = 'kalender'" title="Tampilan Grid Kalender">
                    <i class="bi bi-calendar3-event"></i>
                    <span>Grid Kalender</span>
                </button>

                <button type="button" role="tab" :aria-selected="tampilanModus === 'ringkasan'"
                    class="ag-mode-btn" :class="{ 'is-active': tampilanModus === 'ringkasan' }"
                    @click="tampilanModus = 'ringkasan'" title="Ringkasan & Statistik Agenda">
                    <i class="bi bi-pie-chart"></i>
                    <span>Ringkasan</span>
                </button>
            </div>

            <!-- Pencarian & Reset -->
            <div class="ag-search-box">
                <i class="bi bi-search ag-search-icon"></i>
                <input v-model="pencarian" type="text" placeholder="Cari agenda, tes, atau program..."
                    class="ag-search-input" />
                <button v-if="pencarian" type="button" class="ag-search-clear" @click="pencarian = ''">
                    <i class="bi bi-x"></i>
                </button>
            </div>
        </div>

        <!-- ══════════════ CHIPS FILTER KATEGORI ══════════════ -->
        <div class="ag-filter-chips">
            <button type="button" class="ag-chip" :class="{ 'is-active': kategoriAktif === 'SEMUA' }"
                @click="kategoriAktif = 'SEMUA'">
                <span class="ag-chip__label">Semua</span>
                <span class="ag-chip__count">{{ agendaTerfilter.length }}</span>
            </button>

            <button v-for="(cfg, k) in JENIS" :key="k" type="button"
                class="ag-chip" :class="{ 'is-active': kategoriAktif === k }"
                :style="{ '--chip-color': cfg.warna }"
                @click="kategoriAktif = (kategoriAktif === k ? 'SEMUA' : k)">
                <i class="bi" :class="cfg.ikon"></i>
                <span class="ag-chip__label">{{ cfg.label }}</span>
                <span class="ag-chip__count">{{ hitungPerJenis[k] || 0 }}</span>
            </button>

            <!-- Reset Filter Tanggal jika ada -->
            <button v-if="tanggalTerpilih" type="button" class="ag-chip-reset" @click="tanggalTerpilih = null">
                <i class="bi bi-arrow-counterclockwise"></i> Reset Tanggal ({{ tanggal(tanggalTerpilih) }})
            </button>
        </div>

        <!-- ══════════════ MODE 1: GRID KALENDER 14 HARI ══════════════ -->
        <div v-if="tampilanModus === 'kalender'" class="ag-cal-grid-view">
            <div class="ag-grid-header">
                <span class="ag-grid-title">
                    <i class="bi bi-calendar2-range"></i> Kalender 14 Hari Ke Depan
                </span>
                <small class="ag-grid-sub">Pilih tanggal untuk memfilter agenda</small>
            </div>

            <div class="ag-calendar-days">
                <div v-for="d in matriksHari" :key="d.kunci"
                    class="ag-day-card"
                    :class="{
                        'is-today': d.isToday,
                        'is-selected': tanggalTerpilih === d.kunci,
                        'has-events': d.item.length > 0
                    }"
                    @click="pilihTanggal(d.kunci)">
                    <div class="ag-day-card__top">
                        <span class="ag-day-card__name">{{ d.namaHari }}</span>
                        <span v-if="d.isToday" class="ag-today-badge">HARI INI</span>
                    </div>
                    <div class="ag-day-card__num">{{ d.tglNum }}</div>
                    <div class="ag-day-card__month">{{ d.bulanNama }}</div>

                    <!-- Dots Indikator Agenda -->
                    <div class="ag-day-card__dots">
                        <span v-for="(it, idx) in d.item.slice(0, 4)" :key="idx"
                            class="ag-dot"
                            :style="{ background: JENIS[it.jenis]?.warna }"
                            :title="it.judul"></span>
                        <span v-if="d.item.length > 4" class="ag-dot-more">+{{ d.item.length - 4 }}</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- ══════════════ MODE 2: TIMELINE KRONOLOGIS ══════════════ -->
        <div v-if="tampilanModus === 'timeline' || tampilanModus === 'kalender'" class="ag-timeline-wrap">
            <div v-if="!perHariTerfilter.length" class="ag-no-match">
                <i class="bi bi-funnel"></i>
                <p>Tidak ada agenda yang cocok dengan filter atau pencarian Anda.</p>
                <button type="button" class="ag-btn-reset-all" @click="resetFilter">Reset Semua Filter</button>
            </div>

            <ol v-else class="ag-list">
                <li v-for="grup in perHariTerfilter" :key="grup.kunci" class="ag-group-li">
                    <div class="ag-hari">
                        <div class="ag-hari__left">
                            <span class="ag-hari__dot" :class="{ 'is-kini': grup.selisih === 0 }"></span>
                            <b>{{ grup.tanggal }}</b>
                        </div>
                        <span class="ag-hari__rel" :class="{ 'is-kini': grup.selisih === 0 }">
                            <i v-if="grup.selisih === 0" class="bi bi-record-fill ag-pulse"></i>
                            {{ grup.relatif }}
                        </span>
                    </div>

                    <div class="ag-isi">
                        <div v-for="(a, i) in grup.item" :key="i"
                            class="ag-item"
                            :style="{ '--tone': JENIS[a.jenis].warna }"
                            @click="bukaDetail(a)">
                            <span class="ag-ic">
                                <i class="bi" :class="JENIS[a.jenis].ikon"></i>
                            </span>

                            <div class="ag-teks">
                                <div class="ag-teks__head">
                                    <strong>{{ a.judul }}</strong>
                                    <span class="ag-jenis-pill">{{ JENIS[a.jenis].label }}</span>
                                </div>
                                <div class="ag-teks__meta">
                                    <span v-if="a.program" class="ag-meta-prog">
                                        <i class="bi bi-folder2-open"></i> {{ a.program }}
                                    </span>
                                    <span v-if="a.ket" class="ag-meta-ket">
                                        <i class="bi bi-info-circle"></i> {{ a.ket }}
                                    </span>
                                </div>
                            </div>

                            <div class="ag-item__right">
                                <span class="ag-jam">
                                    <i class="bi bi-clock"></i> {{ jamAtauRentang(a) }}
                                </span>
                                <i class="bi bi-chevron-right ag-arrow"></i>
                            </div>
                        </div>
                    </div>
                </li>
            </ol>
        </div>

        <!-- ══════════════ MODE 3: RINGKASAN & STATISTIK ══════════════ -->
        <div v-if="tampilanModus === 'ringkasan'" class="ag-stats-view">
            <div class="ag-stats-grid">
                <div v-for="(cfg, k) in JENIS" :key="k" class="ag-stat-card" :style="{ '--tone': cfg.warna }">
                    <div class="ag-stat-card__icon">
                        <i class="bi" :class="cfg.ikon"></i>
                    </div>
                    <div class="ag-stat-card__info">
                        <span class="ag-stat-card__val">{{ hitungPerJenis[k] || 0 }}</span>
                        <span class="ag-stat-card__lbl">{{ cfg.label }}</span>
                    </div>
                    <div class="ag-stat-card__bar">
                        <div class="ag-stat-card__fill"
                            :style="{ width: persenJenis(k) + '%' }"></div>
                    </div>
                    <small class="ag-stat-card__pct">{{ persenJenis(k) }}% dari total agenda</small>
                </div>
            </div>

            <!-- Kartu Agenda Terdekat -->
            <div class="ag-upcoming-box" v-if="agendaTerdekat">
                <div class="ag-upcoming-box__head">
                    <i class="bi bi-lightning-charge-fill"></i>
                    <span>Agenda Terdekat Berikutnya</span>
                </div>
                <div class="ag-upcoming-card" :style="{ '--tone': JENIS[agendaTerdekat.jenis].warna }" @click="bukaDetail(agendaTerdekat)">
                    <div class="ag-upcoming-card__badge">{{ JENIS[agendaTerdekat.jenis].label }}</div>
                    <div class="ag-upcoming-card__title">{{ agendaTerdekat.judul }}</div>
                    <div class="ag-upcoming-card__sub">
                        <span v-if="agendaTerdekat.program"><i class="bi bi-building"></i> {{ agendaTerdekat.program }}</span>
                        <span><i class="bi bi-calendar3"></i> {{ tanggal(agendaTerdekat.mulai) }}</span>
                        <span><i class="bi bi-clock"></i> {{ jamAtauRentang(agendaTerdekat) }}</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- ══════════════ MODAL DETAIL AGENDA ══════════════ -->
        <AdminModal
            :show="!!detailModal"
            :title="detailModal?.judul || 'Detail Agenda Operasional'"
            :subtitle="detailModal?.program ? `Program: ${detailModal.program}` : 'Informasi jadwal & agenda operasional'"
            :icon="JENIS[detailModal?.jenis]?.ikon || 'bi-calendar-event'"
            lg
            @close="detailModal = null"
        >
            <div v-if="detailModal" class="ag-modal-body" style="padding: 0;">
                <div class="ag-modal-row">
                    <span class="ag-modal-label"><i class="bi bi-calendar-event"></i> Tanggal &amp; Waktu</span>
                    <span class="ag-modal-val">{{ tanggal(detailModal.mulai) }} · {{ jamAtauRentang(detailModal) }}</span>
                </div>

                <div v-if="detailModal.program" class="ag-modal-row">
                    <span class="ag-modal-label"><i class="bi bi-folder2"></i> Program</span>
                    <span class="ag-modal-val">{{ detailModal.program }}</span>
                </div>

                <div v-if="detailModal.ket" class="ag-modal-row">
                    <span class="ag-modal-label"><i class="bi bi-text-paragraph"></i> Catatan</span>
                    <span class="ag-modal-val">{{ detailModal.ket }}</span>
                </div>
            </div>

            <template #footer>
                <button type="button" class="wca-btn wca-btn--ghost" @click="detailModal = null">
                    <i class="bi bi-x-lg"></i> Tutup
                </button>
                <a v-if="detailModal?.jenis === 'TES'" href="/karir/penjadwalan" class="wca-btn wca-btn--dark">
                    <i class="bi bi-box-arrow-up-right"></i> Kelola Penjadwalan Tes
                </a>
            </template>
        </AdminModal>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { tanggal } from '@utils/career/dashboard';
import KeadaanPanel from '../../monitoring/KeadaanPanel.vue';
import AdminModal from '@career/AdminModal.vue';

const props = defineProps({
    agenda: { type: Array, default: () => [] }
});

const JENIS = {
    TES: { label: 'Sesi Tes', ikon: 'bi-pencil-square', warna: '#0284c7' },
    AGENDA: { label: 'Agenda Program', ikon: 'bi-calendar-event-fill', warna: '#6366f1' },
    TUTUP: { label: 'Pendaftaran Ditutup', ikon: 'bi-calendar-x-fill', warna: '#d03b3b' },
};

const tampilanModus = ref('timeline'); // 'timeline' | 'kalender' | 'ringkasan'
const kategoriAktif = ref('SEMUA');
const pencarian = ref('');
const tanggalTerpilih = ref(null);
const detailModal = ref(null);

function keDate(v) {
    if (!v) return null;
    const d = new Date(String(v).replace(' ', 'T'));
    return Number.isNaN(d.getTime()) ? null : d;
}

/** Hitung agenda per jenis */
const hitungPerJenis = computed(() => {
    const counts = { TES: 0, AGENDA: 0, TUTUP: 0 };
    props.agenda.forEach(a => {
        if (counts[a.jenis] !== undefined) counts[a.jenis]++;
    });
    return counts;
});

function persenJenis(jenis) {
    if (!props.agenda.length) return 0;
    return Math.round(((hitungPerJenis.value[jenis] || 0) / props.agenda.length) * 100);
}

/** Filter agenda berdasarkan kategori & pencarian */
const agendaTerfilter = computed(() => {
    return props.agenda.filter(a => {
        // Filter Kategori
        if (kategoriAktif.value !== 'SEMUA' && a.jenis !== kategoriAktif.value) {
            return false;
        }
        // Filter Tanggal Terpilih (jika ada)
        if (tanggalTerpilih.value) {
            const d = keDate(a.mulai);
            if (!d) return false;
            const kunci = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
            if (kunci !== tanggalTerpilih.value) return false;
        }
        // Filter Pencarian Teks
        if (pencarian.value.trim()) {
            const q = pencarian.value.toLowerCase();
            const judulMatch = a.judul && a.judul.toLowerCase().includes(q);
            const progMatch = a.program && a.program.toLowerCase().includes(q);
            const ketMatch = a.ket && a.ket.toLowerCase().includes(q);
            return judulMatch || progMatch || ketMatch;
        }
        return true;
    });
});

/** Pengelompokkan agenda per hari */
const perHariTerfilter = computed(() => {
    const hariIni = new Date();
    hariIni.setHours(0, 0, 0, 0);
    const peta = new Map();

    agendaTerfilter.value.forEach((a) => {
        const d = keDate(a.mulai);
        if (!d) return;
        const kunci = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
        if (!peta.has(kunci)) {
            const nol = new Date(d);
            nol.setHours(0, 0, 0, 0);
            const selisih = Math.round((nol - hariIni) / 86400000);
            peta.set(kunci, {
                kunci,
                tanggal: tanggal(kunci),
                selisih,
                relatif: selisih === 0 ? 'Hari ini' : (selisih === 1 ? 'Besok' : (selisih < 0 ? `${Math.abs(selisih)} hari lalu` : `${selisih} hari lagi`)),
                item: [],
            });
        }
        peta.get(kunci).item.push(a);
    });

    return [...peta.values()].sort((a, b) => a.selisih - b.selisih);
});

/** Matriks 14 hari ke depan untuk tampilan Grid Kalender */
const matriksHari = computed(() => {
    const hasil = [];
    const hariIni = new Date();
    hariIni.setHours(0, 0, 0, 0);

    const namaHariList = ['Ming', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];
    const namaBulanList = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

    for (let i = 0; i < 14; i++) {
        const curr = new Date(hariIni);
        curr.setDate(hariIni.getDate() + i);

        const kunci = `${curr.getFullYear()}-${String(curr.getMonth() + 1).padStart(2, '0')}-${String(curr.getDate()).padStart(2, '0')}`;

        // Cari item agenda untuk tanggal ini
        const itemHari = props.agenda.filter(a => {
            const d = keDate(a.mulai);
            if (!d) return false;
            const k = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
            return k === kunci;
        });

        hasil.push({
            kunci,
            isToday: i === 0,
            namaHari: namaHariList[curr.getDay()],
            tglNum: curr.getDate(),
            bulanNama: namaBulanList[curr.getMonth()],
            item: itemHari
        });
    }

    return hasil;
});

/** Agenda paling terdekat */
const agendaTerdekat = computed(() => {
    if (!props.agenda.length) return null;
    const sorted = [...props.agenda].sort((a, b) => {
        const da = keDate(a.mulai);
        const db = keDate(b.mulai);
        return (da || 0) - (db || 0);
    });
    return sorted[0];
});

function pilihTanggal(kunci) {
    if (tanggalTerpilih.value === kunci) {
        tanggalTerpilih.value = null; // Toggle off
    } else {
        tanggalTerpilih.value = kunci;
    }
}

function bukaDetail(item) {
    detailModal.value = item;
}

function resetFilter() {
    kategoriAktif.value = 'SEMUA';
    pencarian.value = '';
    tanggalTerpilih.value = null;
}

function jamAtauRentang(a) {
    const m = keDate(a.mulai);
    if (!m) return '—';
    const punyaJam = /\d{2}:\d{2}/.test(String(a.mulai)) && !String(a.mulai).includes('00:00:00');
    if (!punyaJam) return 'Sepanjang hari';

    const jam = (d) => d.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
    const s = keDate(a.akhir);
    return s && s > m ? `${jam(m)}–${jam(s)}` : jam(m);
}
</script>

<style scoped>
.ag-container {
    display: flex;
    flex-direction: column;
    gap: 16px;
    font-family: inherit;
}

/* ══════════════ KONTROL & MODE SWITCHER ══════════════ */
.ag-controls {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    flex-wrap: wrap;
}

.ag-mode-toggle {
    display: inline-flex;
    padding: 3px;
    background: #f1f5f9;
    border-radius: 12px;
    border: 1px solid #e2e8f0;
}

.ag-mode-btn {
    appearance: none;
    border: none;
    background: transparent;
    padding: 6px 14px;
    border-radius: 9px;
    font-size: 0.78rem;
    font-weight: 700;
    color: #64748b;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: all 0.18s ease;
}

.ag-mode-btn:hover {
    color: #0f172a;
}

.ag-mode-btn.is-active {
    background: #ffffff;
    color: #4338ca;
    box-shadow: 0 2px 6px rgba(15, 23, 42, 0.08);
}

/* SEARCH BOX */
.ag-search-box {
    position: relative;
    display: flex;
    align-items: center;
    min-width: 220px;
    flex: 1;
    max-width: 340px;
}

.ag-search-icon {
    position: absolute;
    left: 11px;
    font-size: 0.8rem;
    color: #94a3b8;
    pointer-events: none;
}

.ag-search-input {
    width: 100%;
    padding: 7px 30px 7px 32px;
    font-size: 0.78rem;
    border-radius: 10px;
    border: 1px solid #e2e8f0;
    background: #ffffff;
    color: #0f172a;
    outline: none;
    transition: border-color 0.15s, box-shadow 0.15s;
}

.ag-search-input:focus {
    border-color: #6366f1;
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.12);
}

.ag-search-clear {
    position: absolute;
    right: 8px;
    background: transparent;
    border: none;
    color: #94a3b8;
    cursor: pointer;
    font-size: 1rem;
    padding: 0;
    display: grid;
    place-items: center;
}

/* ══════════════ FILTER CHIPS ══════════════ */
.ag-filter-chips {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}

.ag-chip {
    appearance: none;
    border: 1px solid #e2e8f0;
    background: #ffffff;
    padding: 5px 11px;
    border-radius: 999px;
    font-size: 0.74rem;
    font-weight: 700;
    color: #475569;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: all 0.15s ease;
}

.ag-chip:hover {
    border-color: #cbd5e1;
    background: #f8fafc;
}

.ag-chip.is-active {
    background: color-mix(in srgb, var(--chip-color, #4338ca) 12%, #ffffff);
    border-color: var(--chip-color, #4338ca);
    color: var(--chip-color, #4338ca);
}

.ag-chip__count {
    font-size: 0.68rem;
    padding: 1px 6px;
    border-radius: 999px;
    background: #f1f5f9;
    color: #64748b;
}

.ag-chip.is-active .ag-chip__count {
    background: var(--chip-color, #4338ca);
    color: #ffffff;
}

.ag-chip-reset {
    background: #fef2f2;
    border: 1px solid #fecaca;
    color: #dc2626;
    font-size: 0.72rem;
    font-weight: 700;
    padding: 5px 11px;
    border-radius: 999px;
    cursor: pointer;
}

/* ══════════════ GRID KALENDER 14 HARI ══════════════ */
.ag-cal-grid-view {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    padding: 16px;
    box-shadow: 0 4px 12px rgba(15, 23, 42, 0.03);
}

.ag-grid-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 16px;
    background: linear-gradient(135deg, #1e1b4b 0%, #312e81 60%, #4f46e5 100%);
    border-radius: 12px;
    color: #ffffff;
    margin-bottom: 14px;
    box-shadow: 0 4px 15px rgba(30, 27, 75, 0.2);
}

.ag-grid-title {
    font-size: 0.84rem;
    font-weight: 900;
    color: #ffffff !important;
    display: flex;
    align-items: center;
    gap: 7px;
}

.ag-grid-sub {
    font-size: 0.72rem;
    color: rgba(255, 255, 255, 0.85) !important;
}

.ag-calendar-days {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 8px;
}

.ag-day-card {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 8px;
    display: flex;
    flex-direction: column;
    align-items: center;
    cursor: pointer;
    transition: all 0.18s ease;
    position: relative;
    user-select: none;
}

.ag-day-card:hover {
    border-color: #6366f1;
    transform: translateY(-2px);
    background: #ffffff;
    box-shadow: 0 4px 12px rgba(99, 102, 241, 0.12);
}

.ag-day-card.is-today {
    background: linear-gradient(135deg, #eef2ff, #f5f3ff);
    border-color: #818cf8;
}

.ag-day-card.is-selected {
    border-color: #4338ca;
    background: #eef2ff;
    box-shadow: 0 0 0 2px #4338ca;
}

.ag-day-card__top {
    display: flex;
    align-items: center;
    gap: 4px;
    margin-bottom: 2px;
}

.ag-day-card__name {
    font-size: 0.68rem;
    font-weight: 700;
    color: #64748b;
    text-transform: uppercase;
}

.ag-today-badge {
    font-size: 0.58rem;
    font-weight: 900;
    color: #4338ca;
    background: #dbeafe;
    padding: 0 4px;
    border-radius: 4px;
}

.ag-day-card__num {
    font-size: 1.1rem;
    font-weight: 900;
    color: #0f172a;
    line-height: 1.2;
}

.ag-day-card__month {
    font-size: 0.65rem;
    font-weight: 600;
    color: #94a3b8;
}

.ag-day-card__dots {
    display: flex;
    align-items: center;
    gap: 3px;
    margin-top: 6px;
    min-height: 8px;
}

.ag-dot {
    width: 6px;
    height: 6px;
    border-radius: 999px;
}

.ag-dot-more {
    font-size: 0.58rem;
    font-weight: 800;
    color: #64748b;
}

/* ══════════════ LIST TIMELINE KRONOLOGIS ══════════════ */
.ag-timeline-wrap {
    margin-top: 4px;
}

.ag-list {
    list-style: none;
    margin: 0;
    padding: 0;
    display: grid;
    gap: 16px;
}

.ag-group-li {
    position: relative;
}

.ag-hari {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 9px;
}

.ag-hari__left {
    display: flex;
    align-items: center;
    gap: 8px;
}

.ag-hari__dot {
    width: 8px;
    height: 8px;
    border-radius: 999px;
    background: #cbd5e1;
}

.ag-hari__dot.is-kini {
    background: #6366f1;
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.25);
}

.ag-hari b {
    font-size: 0.82rem;
    font-weight: 900;
    color: #0f172a;
}

.ag-hari__rel {
    font-size: 0.71rem;
    font-weight: 800;
    color: #64748b;
    padding: 2px 10px;
    border-radius: 999px;
    background: #f1f5f9;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.ag-hari__rel.is-kini {
    background: linear-gradient(135deg, #6366f1, #4f46e5);
    color: #ffffff;
    box-shadow: 0 2px 8px rgba(99, 102, 241, 0.3);
}

.ag-pulse {
    animation: agPulse 1.5s infinite;
    font-size: 0.6rem;
}

@keyframes agPulse {
    0% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.4; transform: scale(1.3); }
    100% { opacity: 1; transform: scale(1); }
}

.ag-isi {
    display: grid;
    gap: 9px;
}

.ag-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 14px;
    border-radius: 14px;
    border: 1px solid #eef2f7;
    border-left: 4px solid var(--tone);
    background: #ffffff;
    cursor: pointer;
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.03);
}

.ag-item:hover {
    transform: translateX(4px);
    border-color: #cbd5e1;
    border-left-color: var(--tone);
    box-shadow: 0 4px 16px rgba(15, 23, 42, 0.07);
}

.ag-ic {
    display: grid;
    place-items: center;
    width: 34px;
    height: 34px;
    flex: none;
    border-radius: 11px;
    font-size: 15px;
    color: var(--tone);
    background: color-mix(in srgb, var(--tone) 12%, #ffffff);
    transition: transform 0.2s;
}

.ag-item:hover .ag-ic {
    transform: scale(1.08);
}

.ag-teks {
    flex: 1;
    min-width: 0;
}

.ag-teks__head {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}

.ag-teks strong {
    font-size: 0.82rem;
    font-weight: 800;
    color: #0f172a;
    line-height: 1.3;
}

.ag-jenis-pill {
    font-size: 0.65rem;
    font-weight: 800;
    color: var(--tone);
    background: color-mix(in srgb, var(--tone) 10%, #ffffff);
    padding: 1px 7px;
    border-radius: 999px;
}

.ag-teks__meta {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-top: 3px;
    font-size: 0.72rem;
    color: #64748b;
    flex-wrap: wrap;
}

.ag-meta-prog, .ag-meta-ket {
    display: inline-flex;
    align-items: center;
    gap: 4px;
}

.ag-item__right {
    display: flex;
    align-items: center;
    gap: 10px;
    flex: none;
}

.ag-jam {
    font-size: 0.75rem;
    font-weight: 800;
    color: #334155;
    background: #f8fafc;
    padding: 4px 9px;
    border-radius: 8px;
    border: 1px solid #f1f5f9;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    font-variant-numeric: tabular-nums;
}

.ag-arrow {
    font-size: 0.8rem;
    color: #cbd5e1;
    transition: color 0.15s, transform 0.15s;
}

.ag-item:hover .ag-arrow {
    color: #4338ca;
    transform: translateX(2px);
}

/* EMPTY FILTER MATCH */
.ag-no-match {
    text-align: center;
    padding: 32px 16px;
    background: #ffffff;
    border: 1px dashed #cbd5e1;
    border-radius: 16px;
    color: #64748b;
}

.ag-no-match i {
    font-size: 1.8rem;
    color: #94a3b8;
}

.ag-no-match p {
    font-size: 0.82rem;
    margin: 8px 0 14px;
}

.ag-btn-reset-all {
    appearance: none;
    border: none;
    background: #6366f1;
    color: #ffffff;
    font-size: 0.75rem;
    font-weight: 700;
    padding: 6px 14px;
    border-radius: 999px;
    cursor: pointer;
}

/* ══════════════ MODE RINGKASAN & STATS ══════════════ */
.ag-stats-view {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.ag-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 12px;
}

.ag-stat-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    padding: 14px;
    display: flex;
    flex-direction: column;
    gap: 8px;
    box-shadow: 0 2px 8px rgba(15, 23, 42, 0.02);
}

.ag-stat-card__icon {
    width: 32px;
    height: 32px;
    border-radius: 10px;
    display: grid;
    place-items: center;
    font-size: 1rem;
    color: var(--tone);
    background: color-mix(in srgb, var(--tone) 12%, #ffffff);
}

.ag-stat-card__val {
    font-size: 1.5rem;
    font-weight: 900;
    color: #0f172a;
    line-height: 1;
}

.ag-stat-card__lbl {
    font-size: 0.76rem;
    font-weight: 700;
    color: #64748b;
}

.ag-stat-card__bar {
    height: 6px;
    background: #f1f5f9;
    border-radius: 999px;
    overflow: hidden;
}

.ag-stat-card__fill {
    height: 100%;
    background: var(--tone);
    border-radius: 999px;
    transition: width 0.4s ease;
}

.ag-stat-card__pct {
    font-size: 0.68rem;
    color: #94a3b8;
    font-weight: 600;
}

/* UPCOMING BOX */
.ag-upcoming-box {
    background: linear-gradient(135deg, #1e1b4b, #312e81);
    color: #ffffff;
    border-radius: 16px;
    padding: 16px;
}

.ag-upcoming-box__head {
    display: flex;
    align-items: center;
    gap: 7px;
    font-size: 0.78rem;
    font-weight: 800;
    color: #c7d2fe;
    margin-bottom: 10px;
}

.ag-upcoming-card {
    background: rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(8px);
    border: 1px solid rgba(255, 255, 255, 0.15);
    border-radius: 12px;
    padding: 12px 14px;
    cursor: pointer;
    transition: background 0.18s;
}

.ag-upcoming-card:hover {
    background: rgba(255, 255, 255, 0.16);
}

.ag-upcoming-card__badge {
    font-size: 0.66rem;
    font-weight: 800;
    color: #a5b4fc;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.ag-upcoming-card__title {
    font-size: 0.9rem;
    font-weight: 900;
    color: #ffffff;
    margin: 3px 0 6px;
}

.ag-upcoming-card__sub {
    display: flex;
    gap: 12px;
    font-size: 0.73rem;
    color: #e0e7ff;
    flex-wrap: wrap;
}

/* ══════════════ MODAL DETAIL AGENDA ══════════════ */
.ag-modal-backdrop {
    position: fixed;
    inset: 0;
    z-index: 9999;
    background: rgba(15, 23, 42, 0.45);
    backdrop-filter: blur(4px);
    display: grid;
    place-items: center;
    padding: 16px;
}

.ag-modal-card {
    width: 100%;
    max-width: 440px;
    background: #ffffff;
    border-radius: 20px;
    padding: 20px;
    box-shadow: 0 20px 40px rgba(15, 23, 42, 0.2);
    border-top: 5px solid var(--tone, #6366f1);
    animation: agModalIn 0.22s cubic-bezier(0.16, 1, 0.3, 1);
}

@keyframes agModalIn {
    from { opacity: 0; transform: scale(0.94) translateY(10px); }
    to { opacity: 1; transform: scale(1) translateY(0); }
}

.ag-modal-card__header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
}

.ag-modal-tag {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 0.74rem;
    font-weight: 800;
    color: var(--tone);
    background: color-mix(in srgb, var(--tone) 12%, #ffffff);
    padding: 3px 10px;
    border-radius: 999px;
}

.ag-modal-close {
    background: transparent;
    border: none;
    font-size: 1rem;
    color: #94a3b8;
    cursor: pointer;
    padding: 4px;
    border-radius: 8px;
}

.ag-modal-close:hover {
    color: #0f172a;
    background: #f1f5f9;
}

.ag-modal-title {
    font-size: 1.05rem;
    font-weight: 900;
    color: #0f172a;
    margin: 0 0 14px;
    line-height: 1.35;
}

.ag-modal-body {
    display: grid;
    gap: 10px;
    padding: 12px;
    background: #f8fafc;
    border-radius: 12px;
    margin-bottom: 16px;
}

.ag-modal-row {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.ag-modal-label {
    font-size: 0.7rem;
    font-weight: 800;
    color: #64748b;
    display: flex;
    align-items: center;
    gap: 5px;
}

.ag-modal-val {
    font-size: 0.8rem;
    font-weight: 700;
    color: #0f172a;
}

.ag-modal-footer {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
}

.ag-modal-action-btn {
    text-decoration: none;
    background: linear-gradient(135deg, #6366f1, #4f46e5);
    color: #ffffff;
    font-size: 0.78rem;
    font-weight: 800;
    padding: 8px 14px;
    border-radius: 10px;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    box-shadow: 0 4px 12px rgba(99, 102, 241, 0.25);
    transition: transform 0.15s;
}

.ag-modal-action-btn:hover {
    transform: translateY(-1px);
}

.ag-modal-sec-btn {
    appearance: none;
    border: 1px solid #cbd5e1;
    background: #ffffff;
    color: #475569;
    font-size: 0.78rem;
    font-weight: 700;
    padding: 8px 14px;
    border-radius: 10px;
    cursor: pointer;
}

/* RESPONSIVE */
@media (max-width: 640px) {
    .ag-controls { flex-direction: column; align-items: stretch; }
    .ag-search-box { max-width: 100%; }
    .ag-calendar-days { grid-template-columns: repeat(4, 1fr); }
    .ag-item { flex-wrap: wrap; }
    .ag-item__right { width: 100%; justify-content: space-between; margin-top: 4px; }
}
</style>
