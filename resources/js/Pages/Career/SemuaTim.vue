<!-- ══════════════════════════════════════════════════════════
     WEB CAREER — Semua Tim / Fungsi Perusahaan (route: /karir/tim)
     Kartu ringkasan tiap tim: status hiring, jumlah lowongan,
     lokasi, benefit, skill, dan pelamar. TANPA kuota — jumlah kursi
     tidak lagi dipublikasi (lihat timCards di CareerLandingController).
     Data masih statis di file ini — sesuaikan sesuai kebutuhan.
     ══════════════════════════════════════════════════════════ -->
<template>
    <Head title="Fungsi Perusahaan" />

    <CareerLayout :has-mt="hasMt" :offices="offices">
        <div class="st">
            <button type="button" class="wc-back" @click="goToSection('tim')">
                <i class="bi bi-arrow-left"></i> Kembali ke Beranda Karir
            </button>

            

            <!-- ── Info tim belum diisi (Master Info Divisi kosong) ── -->
            <div v-if="!tim.length" class="st-empty">
                <i class="bi bi-diagram-3"></i>
                <strong>Informasi tim sedang disiapkan</strong>
                <span>Konten perkenalan tim akan segera hadir. Sementara itu, lihat lowongan yang tersedia.</span>
                <Link href="/karir/lowongan" class="st-empty__btn">Lihat semua lowongan</Link>
            </div>

            <!-- ── Pencarian & filter ────────────────────────────── -->
            <div v-if="tim.length" class="st-toolbar wc-reveal">
                <div class="st-search">
                    <i class="bi bi-search"></i>
                    <input
                        v-model="search"
                        type="text"
                        placeholder="Cari tim, keahlian, atau lokasi…"
                        aria-label="Cari tim"
                    />
                    <button v-if="search" type="button" class="st-search__clear" aria-label="Hapus pencarian" @click="search = ''">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>

                <div class="st-filter">
                    <button type="button" class="st-filter__btn" :class="{ 'is-on': filter === 'semua' }" @click="filter = 'semua'">
                        Semua <span>{{ tim.length }}</span>
                    </button>
                    <button type="button" class="st-filter__btn" :class="{ 'is-on': filter === 'buka' }" @click="filter = 'buka'">
                        Sedang membuka <span>{{ timMembuka }}</span>
                    </button>
                </div>
            </div>

            <p v-if="search || filter !== 'semua'" class="st-result">
                Menampilkan <b>{{ timTampil.length }}</b> dari {{ tim.length }} tim
                <template v-if="search"> untuk "<b>{{ search }}</b>"</template>
            </p>

            <div v-if="timTampil.length" class="st-grid">
                <Link
                    v-for="(t, i) in timTampil"
                    :key="t.slug"
                    class="st-card wc-reveal"
                    :class="{ 'is-closed': !t.lowongan }"
                    :style="{ '--d': i * 60 + 'ms' }"
                    :href="`/karir/tim/${t.slug}`"
                >
                    <div class="st-card__top">
                        <span class="st-status" :class="t.lowongan ? 'is-open' : 'is-closed'">
                            <span class="st-status__dot"></span>
                            {{ t.lowongan ? 'Active Hiring' : 'Belum Membuka' }}
                        </span>
                        <span class="st-open">{{ t.lowongan }} Lowongan</span>
                    </div>

                    <h2 class="st-card__title">{{ t.nama }}</h2>
                    <p class="st-card__desc">{{ t.deskripsi }}</p>

                    <div class="st-meta">
                        <span v-if="t.lokasi || t.tempatKerja">
                            <i class="bi bi-geo-alt"></i> {{ [t.lokasi, t.tempatKerja].filter(Boolean).join(' · ') }}
                        </span>
                        <span v-if="t.pengalaman"><i class="bi bi-briefcase"></i> {{ t.pengalaman }}</span>
                    </div>

                    <div v-if="t.benefit" class="st-benefit"><i class="bi bi-gift"></i> {{ t.benefit }}</div>

                    <div v-if="t.skill?.length" class="st-tags">
                        <span v-for="s in t.skill.slice(0, 4)" :key="s">{{ s }}</span>
                        <span v-if="t.skill.length > 4" class="st-tags--more">+{{ t.skill.length - 4 }}</span>
                    </div>

                    <div class="st-foot">
                        <!-- Tanpa "total kuota": jumlah kursi divisi tidak lagi
                             dikirim backend. Yang relevan bagi pelamar adalah
                             berapa posisi dibuka & berapa yang sudah melamar. -->
                        <span class="st-foot__stat">
                            <i class="bi bi-people"></i>
                            <b>{{ t.lowongan }}</b> posisi dibuka · {{ t.pelamar }} pelamar
                        </span>
                        <span class="st-foot__cta">Lihat posisi <i class="bi bi-arrow-right"></i></span>
                    </div>
                </Link>
            </div>

            <div v-else-if="tim.length" class="st-empty">
                <i class="bi bi-search"></i>
                <strong>Tim tidak ditemukan</strong>
                <span>Coba kata kunci lain, atau tampilkan kembali seluruh tim.</span>
                <button type="button" class="st-empty__btn" @click="resetPencarian">Tampilkan semua tim</button>
            </div>

            <div class="st-cta wc-reveal">
                <div class="st-cta__pattern" aria-hidden="true"></div>
                <h2>Sudah tahu posisi yang kamu cari?</h2>
                <p>Lihat seluruh lowongan dari semua tim dalam satu halaman.</p>
                <Link href="/karir/lowongan" class="st-btn"><i class="bi bi-search"></i> Lihat semua lowongan</Link>
            </div>
        </div>
    </CareerLayout>
</template>

<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import CareerLayout from './Layouts/CareerLayout.vue';
import { goToSection, observeReveal } from '@utils/career/data';

defineOptions({ layout: null });

const props = defineProps({
    hasMt: { type: Boolean, default: false },
    offices: { type: Array, default: () => [] },
    // Data tim dari Master Info Divisi (CareerLandingController::timCards()).
    tim: { type: Array, default: () => [] },
});

const hasMt = computed(() => props.hasMt);
const offices = computed(() => props.offices || []);
const tim = computed(() => props.tim || []);

const totalLowongan = computed(() => tim.value.reduce((n, t) => n + (t.lowongan || 0), 0));
const totalPelamar = computed(() => tim.value.reduce((n, t) => n + (t.pelamar || 0), 0));
const timMembuka = computed(() => tim.value.filter((t) => t.lowongan > 0).length);

// ── Pencarian & filter ──────────────────────────────────────
const search = ref('');
const filter = ref('semua');

// Kata kunci dicocokkan ke nama tim, deskripsi, keahlian, dan lokasi.
const timTampil = computed(() => {
    const q = search.value.trim().toLowerCase();
    return tim.value.filter((t) => {
        if (filter.value === 'buka' && !t.lowongan) return false;
        if (!q) return true;
        return [t.nama, t.deskripsi, t.lokasi, t.tempatKerja, t.pengalaman, ...(t.skill || [])]
            .filter(Boolean)
            .join(' ')
            .toLowerCase()
            .includes(q);
    });
});

function resetPencarian() {
    search.value = '';
    filter.value = 'semua';
}

let revealObs = null;
onMounted(() => nextTick(() => (revealObs = observeReveal())));
onUnmounted(() => revealObs?.disconnect());

// Kartu hasil filter perlu diamati ulang agar animasi reveal-nya ikut jalan
watch(timTampil, () =>
    nextTick(() => {
        revealObs?.disconnect();
        revealObs = observeReveal();
    }),
);
</script>

<style scoped>
.st {
    max-width: 1140px;
    margin: 0 auto;
    padding: 1.5rem 1.25rem 4rem;
}

/* ── Header ──────────────────────────────────────────────── */
.st-head {
    max-width: 46rem;
    margin: 1rem 0 2.25rem;
}
.st-eyebrow {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.4rem 0.9rem;
    border-radius: 999px;
    background: rgba(99, 102, 241, 0.1);
    color: var(--indigo);
    font-size: 0.7rem;
    font-weight: 800;
    letter-spacing: 0.16em;
    text-transform: uppercase;
}
.st-dot {
    width: 0.42rem;
    height: 0.42rem;
    border-radius: 50%;
    background: currentColor;
}
.st-head h1 {
    margin: 1rem 0 0.7rem;
    font-size: clamp(1.75rem, 4vw, 2.6rem);
    font-weight: 900;
    letter-spacing: -0.03em;
    line-height: 1.15;
    color: var(--ink);
}
.st-head > p {
    margin: 0;
    color: var(--slate);
    font-size: 0.95rem;
    font-weight: 600;
    line-height: 1.7;
}
.st-summary {
    display: flex;
    align-items: center;
    gap: 1.3rem;
    margin-top: 1.5rem;
    padding-top: 1.3rem;
    border-top: 1px solid rgba(226, 232, 240, 0.9);
}
.st-summary strong {
    display: block;
    font-size: 1.45rem;
    font-weight: 900;
    line-height: 1.1;
    color: var(--indigo);
}
.st-summary small {
    font-size: 0.75rem;
    font-weight: 700;
    color: var(--muted);
}
.st-summary__sep {
    width: 1px;
    height: 2rem;
    background: rgba(226, 232, 240, 0.9);
}

/* ── Toolbar: pencarian + filter ─────────────────────────── */
.st-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 0.85rem;
    margin-bottom: 1.4rem;
}
.st-search {
    position: relative;
    display: flex;
    align-items: center;
    flex: 1 1 20rem;
    padding: 0.7rem 1rem;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.92);
    border: 1px solid rgba(226, 232, 240, 0.9);
    box-shadow: 0 10px 26px rgba(15, 23, 42, 0.05);
    transition: border-color 0.2s ease, box-shadow 0.2s ease;
}
.st-search:focus-within {
    border-color: rgba(139, 92, 246, 0.5);
    box-shadow: 0 12px 30px rgba(99, 102, 241, 0.16);
}
.st-search > i {
    color: var(--indigo);
    font-size: 0.95rem;
}
.st-search input {
    flex: 1;
    min-width: 0;
    margin-left: 0.65rem;
    border: 0;
    outline: none;
    background: transparent;
    color: var(--ink);
    font-size: 0.88rem;
    font-weight: 600;
}
.st-search input::placeholder {
    color: #94a3b8;
    font-weight: 600;
}
.st-search__clear {
    display: grid;
    place-items: center;
    width: 1.55rem;
    height: 1.55rem;
    border: 0;
    border-radius: 50%;
    background: #f1f5f9;
    color: #64748b;
    font-size: 0.7rem;
    cursor: pointer;
    transition: background 0.18s ease, color 0.18s ease;
}
.st-search__clear:hover {
    background: rgba(99, 102, 241, 0.12);
    color: var(--indigo);
}

.st-filter {
    display: flex;
    gap: 0.4rem;
    padding: 0.3rem;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.92);
    border: 1px solid rgba(226, 232, 240, 0.9);
}
.st-filter__btn {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    padding: 0.5rem 1rem;
    border: 0;
    border-radius: 999px;
    background: transparent;
    color: var(--slate);
    font-size: 0.8rem;
    font-weight: 700;
    cursor: pointer;
    transition: background 0.2s ease, color 0.2s ease;
}
.st-filter__btn span {
    padding: 0.1rem 0.45rem;
    border-radius: 999px;
    background: rgba(148, 163, 184, 0.18);
    font-size: 0.7rem;
    font-weight: 800;
}
.st-filter__btn:hover {
    color: var(--indigo);
}
.st-filter__btn.is-on {
    background: linear-gradient(135deg, #8b5cf6, #6366f1);
    color: #fff;
    box-shadow: 0 10px 22px rgba(99, 102, 241, 0.28);
}
.st-filter__btn.is-on span {
    background: rgba(255, 255, 255, 0.24);
}

.st-result {
    margin: 0 0 1rem;
    color: var(--muted);
    font-size: 0.82rem;
    font-weight: 600;
}
.st-result b {
    color: var(--indigo);
}

/* ── Kartu tim ───────────────────────────────────────────── */
.st-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(21rem, 1fr));
    gap: 1.1rem;
}
.st-card {
    position: relative;
    display: flex;
    flex-direction: column;
    padding: 1.4rem 1.35rem;
    border-radius: 1.25rem;
    background: rgba(255, 255, 255, 0.9);
    border: 1px solid rgba(226, 232, 240, 0.9);
    box-shadow: 0 12px 30px rgba(15, 23, 42, 0.05);
    text-decoration: none;
    overflow: hidden;
    transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
}
.st-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 3px;
    background: linear-gradient(90deg, #8b5cf6, #6366f1);
    transform: scaleX(0);
    transform-origin: left;
    transition: transform 0.3s ease;
}
.st-card:hover {
    transform: translateY(-5px);
    border-color: rgba(139, 92, 246, 0.35);
    box-shadow: 0 22px 46px rgba(79, 70, 229, 0.16);
}
.st-card:hover::before {
    transform: scaleX(1);
}
.st-card.is-closed {
    opacity: 0.9;
}

.st-card__top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.6rem;
}
.st-status {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.35rem 0.75rem;
    border-radius: 8px;
    font-size: 0.68rem;
    font-weight: 800;
    letter-spacing: 0.02em;
}
.st-status.is-open {
    color: #047857;
    background: rgba(16, 185, 129, 0.12);
}
.st-status.is-closed {
    color: #64748b;
    background: rgba(148, 163, 184, 0.16);
}
.st-status__dot {
    width: 0.4rem;
    height: 0.4rem;
    border-radius: 50%;
    background: currentColor;
}
.st-open {
    padding: 0.35rem 0.75rem;
    border-radius: 8px;
    background: rgba(99, 102, 241, 0.1);
    color: #4f46e5;
    font-size: 0.68rem;
    font-weight: 800;
}
.st-card.is-closed .st-open {
    background: rgba(148, 163, 184, 0.14);
    color: #94a3b8;
}

.st-card__title {
    margin: 0.9rem 0 0;
    font-size: 1.12rem;
    font-weight: 800;
    letter-spacing: -0.015em;
    line-height: 1.25;
    color: #1e293b;
}
.st-card__desc {
    display: -webkit-box;
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 2;
    line-clamp: 2;
    overflow: hidden;
    margin: 0.5rem 0 0;
    color: #64748b;
    font-size: 0.82rem;
    font-weight: 600;
    line-height: 1.6;
}

.st-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 0.4rem 1.1rem;
    margin-top: 0.9rem;
}
.st-meta span {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    color: #64748b;
    font-size: 0.78rem;
    font-weight: 600;
}
.st-meta i {
    color: #8b5cf6;
    font-size: 0.85rem;
}
.st-benefit {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    margin-top: 0.6rem;
    color: #b45309;
    font-size: 0.79rem;
    font-weight: 700;
}

.st-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 0.4rem;
    margin-top: 0.9rem;
}
.st-tags span {
    padding: 0.28rem 0.6rem;
    border-radius: 7px;
    background: rgba(99, 102, 241, 0.08);
    color: #4f46e5;
    font-size: 0.7rem;
    font-weight: 700;
}
.st-tags .st-tags--more {
    background: #f1f5f9;
    color: #94a3b8;
}

.st-foot {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    margin-top: auto;
    padding-top: 0.95rem;
    border-top: 1px solid #f1f2f9;
}
.st-foot__stat {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    color: #94a3b8;
    font-size: 0.7rem;
    font-weight: 600;
}
.st-foot__stat b {
    color: #6366f1;
}
.st-foot__cta {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    color: #4f46e5;
    font-size: 0.76rem;
    font-weight: 800;
    white-space: nowrap;
}
.st-foot__cta i {
    transition: transform 0.18s ease;
}
.st-card:hover .st-foot__cta i {
    transform: translateX(4px);
}

/* ── Hasil kosong ────────────────────────────────────────── */
.st-empty {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.45rem;
    padding: 3.25rem 1.25rem;
    border-radius: 1.25rem;
    background: rgba(255, 255, 255, 0.85);
    border: 1px dashed rgba(203, 213, 225, 0.9);
    text-align: center;
}
.st-empty > i {
    font-size: 1.6rem;
    color: #cbd5e1;
}
.st-empty strong {
    font-size: 0.98rem;
    font-weight: 800;
    color: var(--ink);
}
.st-empty span {
    color: var(--muted);
    font-size: 0.84rem;
    font-weight: 600;
}
.st-empty__btn {
    margin-top: 0.9rem;
    padding: 0.6rem 1.25rem;
    border: 0;
    border-radius: 999px;
    background: linear-gradient(135deg, #8b5cf6, #6366f1);
    color: #fff;
    font-size: 0.82rem;
    font-weight: 800;
    cursor: pointer;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}
.st-empty__btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 14px 30px rgba(99, 102, 241, 0.35);
}

/* ── CTA bawah ───────────────────────────────────────────── */
.st-cta {
    position: relative;
    overflow: hidden;
    margin-top: clamp(2.5rem, 6vw, 4rem);
    padding: clamp(2rem, 5vw, 2.75rem);
    border-radius: 1.5rem;
    background: linear-gradient(140deg, #1e1b4b 0%, #4338ca 100%);
    color: #fff;
    text-align: center;
    box-shadow: 0 24px 50px rgba(30, 27, 75, 0.3);
}
.st-cta__pattern {
    position: absolute;
    inset: 0;
    background: radial-gradient(circle at 15% 20%, rgba(167, 139, 250, 0.35), transparent 45%),
        radial-gradient(circle at 85% 80%, rgba(99, 102, 241, 0.4), transparent 45%);
}
.st-cta h2 {
    position: relative;
    z-index: 1;
    margin: 0 0 0.5rem;
    font-size: clamp(1.35rem, 3vw, 1.8rem);
    font-weight: 900;
    letter-spacing: -0.02em;
    color: #fff;
}
.st-cta p {
    position: relative;
    z-index: 1;
    margin: 0;
    color: rgba(226, 232, 240, 0.88);
    font-size: 0.9rem;
    font-weight: 600;
}
.st-btn {
    position: relative;
    z-index: 1;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    margin-top: 1.4rem;
    padding: 0.75rem 1.5rem;
    border-radius: 999px;
    background: #fff;
    color: #312e81;
    font-size: 0.86rem;
    font-weight: 800;
    text-decoration: none;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}
.st-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 14px 30px rgba(15, 23, 42, 0.28);
}

@media (max-width: 760px) {
    .st-toolbar {
        flex-direction: column;
        align-items: stretch;
    }
    .st-filter {
        justify-content: center;
    }
    .st-filter__btn {
        flex: 1;
        justify-content: center;
    }
}
@media (max-width: 560px) {
    .st-grid {
        grid-template-columns: 1fr;
    }
    .st-summary {
        gap: 0.9rem;
    }
}
@media (prefers-reduced-motion: reduce) {
    .st-card,
    .st-btn,
    .st-foot__cta i {
        transition: none;
    }
}
</style>
