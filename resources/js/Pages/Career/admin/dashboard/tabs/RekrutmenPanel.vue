<!--
  ZONA D — KHAS REKRUTMEN: PEMENUHAN MPP (Redesigned)
-->
<template>
    <div class="rk-container">
        <!-- ── Time-to-fill & ringkasan ── -->
        <div class="wcd-grid wcd-grid--3" style="margin-bottom: 18px">
            <div class="wcd-stat" :style="{ '--tone': '#6366f1' }">
                <div class="wcd-stat__top">
                    <span class="wcd-stat__ic"><i class="bi bi-briefcase-fill"></i></span>
                </div>
                <div class="wcd-stat__num">{{ angka(khas.posisi.length) }}</div>
                <div class="wcd-stat__lbl">Posisi Terdaftar</div>
                <div class="wcd-stat__ket">{{ angka(totalKuota) }} kursi · {{ angka(totalTerisi) }} terisi</div>
            </div>

            <div class="wcd-stat" :style="{ '--tone': khas.timeToFill ? '#10b981' : '#94a3b8' }">
                <div class="wcd-stat__top">
                    <span class="wcd-stat__ic"><i class="bi bi-stopwatch-fill"></i></span>
                </div>
                <div class="wcd-stat__num">
                    <template v-if="khas.timeToFill">{{ desimal(khas.timeToFill.rata, 1) }} <small>hari</small></template>
                    <template v-else>—</template>
                </div>
                <div class="wcd-stat__lbl">Rata-rata Time-to-Fill</div>
                <div class="wcd-stat__ket">
                    <template v-if="khas.timeToFill">
                        Dari {{ khas.timeToFill.jml }} kursi terisi · tercepat {{ khas.timeToFill.tercepat }}h, terlama {{ khas.timeToFill.terlama }}h
                    </template>
                    <template v-else>Belum ada kursi yang pernah terisi</template>
                </div>
            </div>

            <div class="wcd-stat" :style="{ '--tone': posisiKritis.length ? STATUS.critical.warna : '#10b981' }">
                <div class="wcd-stat__top">
                    <span class="wcd-stat__ic"><i class="bi bi-hourglass-bottom"></i></span>
                </div>
                <div class="wcd-stat__num">{{ angka(posisiKritis.length) }}</div>
                <div class="wcd-stat__lbl">Posisi Kritis Kosong</div>
                <div class="wcd-stat__ket">Terbuka &gt; 30 hari &amp; belum ada terisi</div>
            </div>
        </div>

        <!-- Toolbar Filter & Pencarian Pemenuhan MPP -->
        <div class="rk-toolbar">
            <div class="rk-search">
                <i class="bi bi-search"></i>
                <input v-model="cari" type="text" placeholder="Cari posisi, departemen, lokasi..." />
                <button v-if="cari" type="button" class="rk-search-clear" @click="cari = ''">
                    <i class="bi bi-x-circle-fill"></i>
                </button>
            </div>

            <div class="rk-filter-pills">
                <button type="button" class="rk-pill-btn" :class="{ 'is-on': filterPemenuhan === 'SEMUA' }" @click="filterPemenuhan = 'SEMUA'">
                    Semua ({{ khas.posisi.length }})
                </button>
                <button type="button" class="rk-pill-btn is-kritis" :class="{ 'is-on': filterPemenuhan === 'KRITIS' }" @click="filterPemenuhan = 'KRITIS'">
                    <i class="bi bi-hourglass-bottom"></i> Kritis ({{ posisiKritis.length }})
                </button>
                <button type="button" class="rk-pill-btn is-proses" :class="{ 'is-on': filterPemenuhan === 'BELUM_PENUH' }" @click="filterPemenuhan = 'BELUM_PENUH'">
                    <i class="bi bi-clock-history"></i> Belum Penuh
                </button>
                <button type="button" class="rk-pill-btn is-penuh" :class="{ 'is-on': filterPemenuhan === 'PENUH' }" @click="filterPemenuhan = 'PENUH'">
                    <i class="bi bi-check-circle-fill"></i> Kursi Penuh
                </button>
            </div>

            <div class="rk-per-hal">
                <span>Tampil:</span>
                <button v-for="opt in [8, 15, 30, 0]" :key="opt" type="button"
                    class="rk-per-hal__btn" :class="{ 'is-on': perHal === opt }"
                    @click="perHal = opt">
                    {{ opt === 0 ? 'Semua' : opt }}
                </button>
            </div>
        </div>

        <!-- ── Tabel posisi ── -->
        <div class="wcd-tw rk-tw">
            <table class="wcd-tbl">
                <thead>
                    <tr>
                        <th>Posisi</th><th>Departemen</th><th>Lokasi</th>
                        <th class="wcd-num">Kursi</th><th>Pemenuhan</th>
                        <th class="wcd-num">Pelamar</th><th class="wcd-num">Umur Buka</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(p, i) in barisPaginated" :key="i">
                        <td class="wcd-tbl__utama">
                            <strong>{{ p.posisi }}</strong>
                            <span class="wcd-tbl__sub">
                                {{ p.program }}<template v-if="p.mppRef"> · {{ p.mppRef }}</template>
                            </span>
                        </td>
                        <td><span class="rk-dept-tag">{{ p.departemen || '—' }}</span></td>
                        <td>
                            {{ p.lokasi || '—' }}
                            <span v-if="p.level" class="wcd-tbl__sub">{{ p.level }}</span>
                        </td>
                        <td class="wcd-num"><b>{{ angka(p.terisi) }}</b> <small>/ {{ angka(p.kuota) }}</small></td>
                        <td>
                            <div class="rk-fill">
                                <div class="wcd-meter">
                                    <span :style="{ width: pct(p) + '%', background: p.sisaKursi === 0 && p.kuota ? STATUS.good.warna : 'linear-gradient(90deg, #6366f1, #4f46e5)' }"></span>
                                </div>
                                <span class="rk-fill-txt">{{ p.kuota ? pct(p) + '%' : '—' }}</span>
                            </div>
                        </td>
                        <td class="wcd-num">
                            <b>{{ angka(p.pelamar) }}</b>
                            <small v-if="p.berjalan" :title="`${p.berjalan} masih berproses`"> ({{ p.berjalan }} aktif)</small>
                        </td>
                        <td class="wcd-num">
                            <span v-if="p.umurBukaHari === null">—</span>
                            <span v-else class="wcd-lb" :style="nadaUmurBuka(p)">
                                <i class="bi" :class="kritis(p) ? STATUS.critical.ikon : 'bi-calendar3'"></i>
                                {{ p.umurBukaHari }} hari
                            </span>
                        </td>
                    </tr>
                    <tr v-if="!barisTersaring.length">
                        <td colspan="7" class="rk-empty-td">
                            Tidak ada posisi yang cocok dengan filter atau pencarian "{{ cari }}".
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Bar Paginasi -->
        <div v-if="barisTersaring.length && (totalHal > 1 || perHal > 0)" class="rk-paginasi">
            <span class="rk-paginasi__info">
                Menampilkan <b>{{ perHal === 0 ? 1 : ((hal - 1) * perHal) + 1 }} - {{ perHal === 0 ? barisTersaring.length : Math.min(hal * perHal, barisTersaring.length) }}</b> dari <b>{{ barisTersaring.length }}</b> posisi
            </span>
            <div v-if="totalHal > 1" class="rk-paginasi__nav">
                <button type="button" class="rk-paginasi__btn" :disabled="hal <= 1" :onClick="hal <= 1 ? null : () => hal--">
                    <i class="bi bi-chevron-left"></i> Sebelum
                </button>
                <span class="rk-paginasi__page">{{ hal }} / {{ totalHal }}</span>
                <button type="button" class="rk-paginasi__btn" :disabled="hal >= totalHal" @click="hal++">
                    Lanjut <i class="bi bi-chevron-right"></i>
                </button>
            </div>
        </div>

        <!-- ── Sebaran ── -->
        <div class="wcd-grid wcd-grid--2" style="margin-top: 18px">
            <div class="wcd-card wcd-card--datar">
                <h3 class="wcd-card__hd"><i class="bi bi-diagram-3-fill"></i> Kebutuhan per Departemen</h3>
                <DaftarBatang :items="departemen" satuan="kursi" ikon="bi-diagram-3"
                    teks-kosong="Belum ada kuota per departemen" />
                <p v-if="departemen.length" class="rk-nota">
                    Angka menyatakan total kuota kursi per departemen.
                </p>
            </div>

            <div class="wcd-card wcd-card--datar">
                <h3 class="wcd-card__hd"><i class="bi bi-signpost-split-fill"></i> Sumber Kandidat</h3>
                <DaftarBatang :items="sumber" satuan="pelamar" ikon="bi-signpost-split"
                    teks-kosong="Sumber kandidat belum tercatat"
                    ket-kosong="Kolom Kode Sumber pada lamaran masih kosong." />
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import { angka, desimal, persen, STATUS } from '@utils/career/dashboard';
import DaftarBatang from '../panels/DaftarBatang.vue';
import KeadaanPanel from '../../monitoring/KeadaanPanel.vue';

const props = defineProps({ khas: { type: Object, required: true } });

const cari = ref('');
const filterPemenuhan = ref('SEMUA');
const hal = ref(1);
const perHal = ref(8); // Default 8 posisi per halaman

const totalKuota = computed(() => props.khas.posisi.reduce((n, p) => n + (p.kuota || 0), 0));
const totalTerisi = computed(() => props.khas.posisi.reduce((n, p) => n + (p.terisi || 0), 0));

const pct = (p) => (p.kuota ? persen(p.terisi, p.kuota) : 0);
const kritis = (p) => (p.umurBukaHari || 0) > 30 && p.terisi === 0 && p.kuota > 0;

const posisiKritis = computed(() => props.khas.posisi.filter(kritis));

const posisiUrut = computed(() => [...props.khas.posisi].sort((a, b) => {
    const ka = kritis(a) ? 1 : 0;
    const kb = kritis(b) ? 1 : 0;
    if (ka !== kb) return kb - ka;
    if (b.sisaKursi !== a.sisaKursi) return b.sisaKursi - a.sisaKursi;
    return String(a.posisi).localeCompare(String(b.posisi));
}));

const barisTersaring = computed(() => {
    let list = posisiUrut.value;

    if (filterPemenuhan.value === 'KRITIS') {
        list = list.filter(kritis);
    } else if (filterPemenuhan.value === 'BELUM_PENUH') {
        list = list.filter((p) => p.kuota > 0 && p.terisi < p.kuota);
    } else if (filterPemenuhan.value === 'PENUH') {
        list = list.filter((p) => p.kuota > 0 && p.terisi >= p.kuota);
    }

    if (cari.value.trim()) {
        const q = cari.value.toLowerCase().trim();
        list = list.filter((p) => {
            const pos = (p.posisi || '').toLowerCase();
            const dept = (p.departemen || '').toLowerCase();
            const lok = (p.lokasi || '').toLowerCase();
            const prog = (p.program || '').toLowerCase();
            return pos.includes(q) || dept.includes(q) || lok.includes(q) || prog.includes(q);
        });
    }

    return list;
});

watch([cari, filterPemenuhan, perHal], () => {
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

const departemen = computed(() => props.khas.departemen.map((d) => ({ nama: d.departemen, jml: d.kuota })));
const sumber = computed(() => props.khas.sumber.map((s) => ({ nama: s.nama, jml: s.jml })));

function nadaUmurBuka(p) {
    if (kritis(p)) return { background: `color-mix(in srgb, ${STATUS.critical.warna} 14%, #fff)`, color: STATUS.critical.warna };
    if ((p.umurBukaHari || 0) > 30) return { background: `color-mix(in srgb, ${STATUS.warning.warna} 16%, #fff)`, color: '#92400e' };
    return {};
}
</script>

<style scoped>
.rk-container { padding-top: 4px; }
.rk-fill { display: flex; align-items: center; gap: 10px; min-width: 130px; }
.rk-fill-txt { font-size: 0.76rem; font-weight: 800; color: #334155; font-variant-numeric: tabular-nums; }
.rk-nota { margin: 12px 0 0; font-size: 0.74rem; color: #94a3b8; line-height: 1.5; }
.rk-dept-tag {
    display: inline-block;
    padding: 3px 9px;
    border-radius: 6px;
    background: #f1f5f9;
    color: #475569;
    font-size: 0.74rem;
    font-weight: 700;
}

/* ══════════ TOOLBAR FILTER & SEARCH ══════════ */
.rk-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 12px;
    flex-wrap: wrap;
}

.rk-search {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 6px 12px;
    border: 1.5px solid #cbd5e1;
    border-radius: 10px;
    background: #ffffff;
    flex: 1;
    max-width: 320px;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);
}
.rk-search i { color: #94a3b8; font-size: 0.85rem; }
.rk-search input {
    width: 100%;
    border: 0;
    outline: 0;
    font: inherit;
    font-size: 0.76rem;
    font-weight: 700;
    color: #0f172a;
    background: transparent;
}
.rk-search-clear {
    border: 0;
    background: transparent;
    color: #cbd5e1;
    cursor: pointer;
    padding: 0;
}
.rk-search-clear:hover { color: #ef4444; }

.rk-filter-pills {
    display: flex;
    align-items: center;
    gap: 6px;
    flex-wrap: wrap;
}

.rk-pill-btn {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 5px 12px;
    border: 1.5px solid #e2e8f0;
    border-radius: 999px;
    background: #ffffff;
    color: #475569;
    font: inherit;
    font-size: 0.74rem;
    font-weight: 800;
    cursor: pointer;
    transition: all 0.15s ease;
}
.rk-pill-btn:hover { background: #f8fafc; border-color: #cbd5e1; }
.rk-pill-btn.is-on { background: #4f46e5; border-color: #4f46e5; color: #ffffff; }

.rk-pill-btn.is-kritis.is-on { background: #dc2626; border-color: #dc2626; color: #ffffff; }
.rk-pill-btn.is-proses.is-on { background: #d97706; border-color: #d97706; color: #ffffff; }
.rk-pill-btn.is-penuh.is-on { background: #10b981; border-color: #10b981; color: #ffffff; }

.rk-per-hal {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 0.72rem;
    font-weight: 700;
    color: #64748b;
    margin-left: auto;
}
.rk-per-hal__btn {
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
.rk-per-hal__btn.is-on {
    background: #4f46e5;
    border-color: #4f46e5;
    color: #ffffff;
}

.rk-tw {
    max-height: 420px;
    overflow-y: auto;
    border-radius: 14px;
    border: 1.5px solid #e2e8f0;
    position: relative;
}
.rk-tw::-webkit-scrollbar {
    width: 6px;
}
.rk-tw::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 99px;
}
.rk-tw .wcd-tbl thead th {
    position: sticky;
    top: 0;
    z-index: 2;
    background: #f8fafc !important;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
}

.rk-empty-td { text-align: center; color: #64748b; padding: 28px 14px !important; font-size: 0.8rem; font-weight: 700; }

.rk-paginasi {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-top: 10px;
    padding: 8px 14px;
    background: #f8fafc;
    border: 1.5px solid #e2e8f0;
    border-radius: 10px;
    font-size: 0.74rem;
    color: #475569;
}
.rk-paginasi__info b { color: #0f172a; font-weight: 800; }
.rk-paginasi__nav {
    display: flex;
    align-items: center;
    gap: 8px;
}
.rk-paginasi__btn {
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
.rk-paginasi__btn:disabled {
    opacity: 0.4;
    cursor: not-allowed;
}
.rk-paginasi__btn:hover:not(:disabled) {
    border-color: #4f46e5;
    color: #4f46e5;
}
.rk-paginasi__page {
    font-weight: 800;
    color: #0f172a;
}
</style>
