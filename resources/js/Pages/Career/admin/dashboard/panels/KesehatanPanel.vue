<template>
    <div v-if="!baris.length">
        <KeadaanPanel keadaan="kosong" ikon="bi-collection"
            teks="Belum ada program pada kategori ini"
            ket="Program dibuat di menu Program Kegiatan." />
    </div>

    <div v-else class="ks-wrapper">
        <!-- Toolbar Filter & Pencarian -->
        <div class="ks-toolbar">
            <div class="ks-search">
                <i class="bi bi-search"></i>
                <input v-model="cari" type="text" placeholder="Cari nama program atau alur..." />
                <button v-if="cari" type="button" class="ks-search-clear" @click="cari = ''">
                    <i class="bi bi-x-circle-fill"></i>
                </button>
            </div>

            <!-- Filter Status Kesehatan -->
            <div class="ks-filter-pills">
                <button type="button" class="ks-pill-btn" :class="{ 'is-on': filterStatus === 'SEMUA' }" @click="filterStatus = 'SEMUA'">
                    Semua ({{ baris.length }})
                </button>
                <button type="button" class="ks-pill-btn is-sehat" :class="{ 'is-on': filterStatus === 'SEHAT' }" @click="filterStatus = 'SEHAT'">
                    <i class="bi bi-check-circle-fill"></i> Sehat
                </button>
                <button type="button" class="ks-pill-btn is-perhatian" :class="{ 'is-on': filterStatus === 'PERHATIAN' }" @click="filterStatus = 'PERHATIAN'">
                    <i class="bi bi-exclamation-triangle-fill"></i> Perhatian
                </button>
                <button type="button" class="ks-pill-btn is-kritis" :class="{ 'is-on': filterStatus === 'KRITIS' }" @click="filterStatus = 'KRITIS'">
                    <i class="bi bi-x-circle-fill"></i> Kritis
                </button>
            </div>

            <!-- Per Page Selector -->
            <div class="ks-per-hal">
                <span>Tampil:</span>
                <button v-for="opt in [5, 10, 20, 0]" :key="opt" type="button"
                    class="ks-per-hal__btn" :class="{ 'is-on': perHal === opt }"
                    @click="perHal = opt">
                    {{ opt === 0 ? 'Semua' : opt }}
                </button>
            </div>
        </div>

        <!-- List Program -->
        <ul v-if="barisPaginated.length" class="ks-list">
            <li v-for="p in barisPaginated" :key="p.id" class="ks-card">
                <!-- Cincin skor: 0-100 -->
                <div class="ks-ring" :style="{ '--tone': nada(p).warna }">
                    <svg viewBox="0 0 44 44" aria-hidden="true">
                        <circle class="ks-ring__bg" cx="22" cy="22" r="19" />
                        <circle v-if="p.sehat.skor !== null" class="ks-ring__fg" cx="22" cy="22" r="19"
                            :stroke-dasharray="keliling" :stroke-dashoffset="offset(p.sehat.skor)" />
                    </svg>
                    <div class="ks-ring__inner">
                        <b>{{ p.sehat.skor === null ? '–' : p.sehat.skor }}</b>
                        <small>Skor</small>
                    </div>
                </div>

                <div class="ks-isi">
                    <div class="ks-judul">
                        <strong>{{ p.nama }}</strong>
                        <span class="wcd-lb" :style="{ background: `color-mix(in srgb, ${nada(p).warna} 12%, #fff)`, color: nada(p).warna }">
                            <i class="bi" :class="nada(p).ikon"></i> {{ nada(p).teks }}
                        </span>
                        <span v-if="p.status !== 'BERJALAN'" class="wcd-lb ks-status-tag">{{ p.status }}</span>
                    </div>

                    <div class="ks-meta">
                        <span><i class="bi bi-diagram-2"></i> Alur: <b>{{ p.alur || 'Tanpa alur' }}</b></span>
                        <span><i class="bi bi-people-fill"></i> <b>{{ angka(p.pelamar) }}</b> pelamar</span>
                        <span v-if="p.sehat.aktif"><i class="bi bi-person-walking"></i> <b>{{ angka(p.sehat.aktif) }}</b> aktif</span>
                        <span v-if="p.sehat.macet" class="is-buruk"><i class="bi bi-cone-striped"></i> <b>{{ angka(p.sehat.macet) }}</b> macet</span>
                        <span v-if="p.sehat.siapMenggantung" class="is-buruk"><i class="bi bi-hourglass-split"></i> <b>{{ angka(p.sehat.siapMenggantung) }}</b> menggantung</span>
                        <span v-if="p.sehat.maxAging !== null"><i class="bi bi-clock-history"></i> Terlama: {{ p.sehat.maxAging }} hari</span>
                    </div>

                    <!-- Kuota: meter + angka -->
                    <div class="ks-kuota">
                        <div class="wcd-meter" :title="`${p.terisi} dari ${p.kuota} kursi terisi`">
                            <span :style="{ width: persenKursi(p) + '%', background: p.sisaKursi === 0 ? STATUS.good.warna : 'linear-gradient(90deg, #6366f1, #4f46e5)' }"></span>
                        </div>
                        <span class="ks-kuota__txt">
                            <b>{{ angka(p.terisi) }}</b> / {{ angka(p.kuota) }} kursi
                            <template v-if="p.kuota">({{ persenKursi(p) }}%)</template>
                        </span>
                        <span v-if="p.kandidatPerKursi !== null" class="wcd-lb" :style="nadaRasio(p)">
                            <i class="bi" :class="p.kandidatPerKursi < 1 ? STATUS.critical.ikon : 'bi-check2'"></i>
                            {{ desimal(p.kandidatPerKursi, 1) }} kandidat / kursi sisa
                        </span>
                        <span v-else-if="p.kuota" class="wcd-lb" :style="{ background: '#f0fdf4', color: STATUS.good.warna }">
                            <i class="bi" :class="STATUS.good.ikon"></i> Kursi penuh
                        </span>
                        <span v-else class="wcd-lb">Kuota belum diisi</span>
                    </div>
                </div>
            </li>
        </ul>

        <KeadaanPanel v-else-if="cari || filterStatus !== 'SEMUA'" keadaan="kosong" rapat
            teks="Hasil filter program tidak ditemukan"
            ket="Coba ubah kata kunci pencarian atau sesuaikan status kesehatan." />

        <!-- Bar Paginasi -->
        <div v-if="barisTersaring.length && (totalHal > 1 || perHal > 0)" class="ks-paginasi">
            <span class="ks-paginasi__info">
                Menampilkan <b>{{ perHal === 0 ? 1 : ((hal - 1) * perHal) + 1 }} - {{ perHal === 0 ? barisTersaring.length : Math.min(hal * perHal, barisTersaring.length) }}</b> dari <b>{{ barisTersaring.length }}</b> program
            </span>
            <div v-if="totalHal > 1" class="ks-paginasi__nav">
                <button type="button" class="ks-paginasi__btn" :disabled="hal <= 1" :onClick="hal <= 1 ? null : () => hal--">
                    <i class="bi bi-chevron-left"></i> Sebelum
                </button>
                <span class="ks-paginasi__page">{{ hal }} / {{ totalHal }}</span>
                <button type="button" class="ks-paginasi__btn" :disabled="hal >= totalHal" @click="hal++">
                    Lanjut <i class="bi bi-chevron-right"></i>
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import { angka, desimal, nadaSehat, persen, STATUS } from '@utils/career/dashboard';
import KeadaanPanel from '../../monitoring/KeadaanPanel.vue';

const props = defineProps({ baris: { type: Array, default: () => [] } });

const cari = ref('');
const filterStatus = ref('SEMUA');
const hal = ref(1);
const perHal = ref(5); // Default 5 program per halaman

const keliling = 2 * Math.PI * 19;

const nada = (p) => nadaSehat(p.sehat?.label);
const offset = (skor) => keliling - (keliling * Math.max(0, Math.min(100, skor))) / 100;
const persenKursi = (p) => (p.kuota ? persen(p.terisi, p.kuota) : 0);

function nadaRasio(p) {
    const s = p.kandidatPerKursi < 1 ? STATUS.critical : (p.kandidatPerKursi < 2 ? STATUS.warning : STATUS.good);
    return { background: `color-mix(in srgb, ${s.warna} 12%, #fff)`, color: s.warna };
}

// Data tersaring
const barisTersaring = computed(() => {
    let list = props.baris;

    // Filter status kesehatan jika dipilih
    if (filterStatus.value !== 'SEMUA') {
        list = list.filter((p) => {
            const skor = p.sehat?.skor;

            // Program tanpa satu pun proses berjalan tidak punya skor (null —
            // labelnya KOSONG atau SELESAI) dan bukan anggota kategori mana pun.
            // Ia HARUS dibuang lebih dulu: di JavaScript `null < 50` bernilai
            // true, jadi saringan "Kritis" ikut menampilkan setiap program yang
            // belum punya pelamar. Sepuluh program yang justru tidak bermasalah
            // berdesakan dengan satu yang benar-benar kritis, dan saringan yang
            // gunanya memisahkan itu malah menyembunyikannya.
            if (skor === null || skor === undefined) return false;

            if (filterStatus.value === 'SEHAT') return skor >= 80;
            if (filterStatus.value === 'PERHATIAN') return skor >= 50 && skor < 80;
            if (filterStatus.value === 'KRITIS') return skor < 50;

            return true;
        });
    }

    // Filter pencarian teks
    if (cari.value.trim()) {
        const q = cari.value.toLowerCase().trim();
        list = list.filter((p) => {
            const nama = (p.nama || '').toLowerCase();
            const alur = (p.alur || '').toLowerCase();
            const status = (p.status || '').toLowerCase();
            return nama.includes(q) || alur.includes(q) || status.includes(q);
        });
    }

    return list;
});

watch([cari, filterStatus, perHal], () => {
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
</script>

<style scoped>
.ks-wrapper {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

/* ══════════ TOOLBAR FILTER & SEARCH ══════════ */
.ks-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 4px;
    flex-wrap: wrap;
}

.ks-search {
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
.ks-search i { color: #94a3b8; font-size: 0.85rem; }
.ks-search input {
    width: 100%;
    border: 0;
    outline: 0;
    font: inherit;
    font-size: 0.76rem;
    font-weight: 700;
    color: #0f172a;
    background: transparent;
}
.ks-search-clear {
    border: 0;
    background: transparent;
    color: #cbd5e1;
    cursor: pointer;
    padding: 0;
}
.ks-search-clear:hover { color: #ef4444; }

.ks-filter-pills {
    display: flex;
    align-items: center;
    gap: 6px;
    flex-wrap: wrap;
}

.ks-pill-btn {
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
.ks-pill-btn:hover { background: #f8fafc; border-color: #cbd5e1; }
.ks-pill-btn.is-on { background: #4f46e5; border-color: #4f46e5; color: #ffffff; }

.ks-pill-btn.is-sehat.is-on { background: #10b981; border-color: #10b981; color: #ffffff; }
.ks-pill-btn.is-perhatian.is-on { background: #f59e0b; border-color: #f59e0b; color: #ffffff; }
.ks-pill-btn.is-kritis.is-on { background: #ef4444; border-color: #ef4444; color: #ffffff; }

.ks-per-hal {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 0.72rem;
    font-weight: 700;
    color: #64748b;
    margin-left: auto;
}
.ks-per-hal__btn {
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
.ks-per-hal__btn.is-on {
    background: #4f46e5;
    border-color: #4f46e5;
    color: #ffffff;
}

.ks-paginasi {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-top: 6px;
    padding: 8px 14px;
    background: #f8fafc;
    border: 1.5px solid #e2e8f0;
    border-radius: 10px;
    font-size: 0.74rem;
    color: #475569;
}
.ks-paginasi__info b { color: #0f172a; font-weight: 800; }
.ks-paginasi__nav {
    display: flex;
    align-items: center;
    gap: 8px;
}
.ks-paginasi__btn {
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
.ks-paginasi__btn:disabled {
    opacity: 0.4;
    cursor: not-allowed;
}
.ks-paginasi__btn:hover:not(:disabled) {
    border-color: #4f46e5;
    color: #4f46e5;
}
.ks-paginasi__page {
    font-weight: 800;
    color: #0f172a;
}

.ks-list { list-style: none; margin: 0; padding: 0; display: grid; gap: 12px; }
.ks-card {
    display: flex;
    gap: 18px;
    align-items: flex-start;
    padding: 18px;
    border: 1px solid rgba(226, 232, 240, 0.9);
    border-radius: 16px;
    background: #ffffff;
    transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
    box-shadow: 0 2px 8px -2px rgba(15, 23, 42, 0.04);
}
.ks-card:hover { border-color: #c7d2fe; transform: translateY(-2px); box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.08); }

.ks-ring { position: relative; width: 54px; height: 54px; flex: none; }
.ks-ring svg { width: 100%; height: 100%; transform: rotate(-90deg); }
.ks-ring__bg { fill: none; stroke: #f1f5f9; stroke-width: 4; }
.ks-ring__fg {
    fill: none;
    stroke: var(--tone);
    stroke-width: 4;
    stroke-linecap: round;
    transition: stroke-dashoffset 0.6s cubic-bezier(0.16, 1, 0.3, 1);
}

.ks-ring__inner {
    position: absolute;
    inset: 0;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-align: center;
}

.ks-ring__inner b {
    font-size: 0.9rem;
    font-weight: 900;
    color: #0f172a;
    line-height: 1;
}

.ks-ring__inner small {
    font-size: 0.58rem;
    color: #94a3b8;
    text-transform: uppercase;
    font-weight: 800;
    margin-top: 1px;
}

.ks-isi { flex: 1; min-width: 0; }
.ks-judul { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
.ks-judul strong { font-size: 0.92rem; font-weight: 900; color: #0f172a; }
.ks-status-tag { background: #f1f5f9; color: #475569; }

.ks-meta { display: flex; flex-wrap: wrap; gap: 14px; margin-top: 8px; font-size: 0.76rem; color: #64748b; }
.ks-meta .bi { font-size: 13px; color: #6366f1; }
.ks-meta b { color: #0f172a; font-weight: 800; }
.ks-meta .is-buruk { color: #dc2626; font-weight: 700; }
.ks-meta .is-buruk .bi { color: #ef4444; }

.ks-kuota { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; margin-top: 12px; }
.wcd-meter { height: 8px; border-radius: 999px; background: #f1f5f9; overflow: hidden; min-width: 100px; max-width: 220px; flex: 1; }
.wcd-meter span { display: block; height: 100%; border-radius: 999px; transition: width 0.4s ease; }
.ks-kuota__txt { font-size: 0.78rem; color: #475569; font-variant-numeric: tabular-nums; }
.ks-kuota__txt b { color: #0f172a; font-weight: 900; }

@media (max-width: 640px) {
    .ks-card { flex-direction: column; }
}
</style>
