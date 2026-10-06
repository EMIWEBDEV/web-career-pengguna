<!-- ══════════════════════════════════════════════════════════
     WEB CAREER — FAQ Kandidat (route: /karir/faq)
     Tujuan "baca selengkapnya" dari accordion landing. Seluruh
     pertanyaan aktif dikelompokkan per kategori (Master FAQ), plus
     pencarian, deep link per pertanyaan (#slug), dan penilaian
     "membantu" yang jadi bahan HR memperbaiki jawaban.
     Data: FaqPublikController::index() → App\Support\Career\FaqPublik::semua()
     ══════════════════════════════════════════════════════════ -->
<template>
    <!-- Deskripsi halaman TIDAK dipasang di sini: meta description sudah
         dicetak server lewat config/seo.php (kunci rute career.faq). Memasangnya
         lagi dari Vue hanya melahirkan dua tag description di dokumen yang
         sama, dan perayap sosial pun tidak pernah melihat versi Vue-nya. -->
    <Head title="FAQ Kandidat" />

    <CareerLayout :has-mt="hasMt" :offices="offices">
        <div class="fq">
            <button type="button" class="wc-back" @click="goToSection('faq')">
                <i class="bi bi-arrow-left"></i> Kembali ke Beranda Karir
            </button>

            <!-- ── Hero ──────────────────────────────────────────── -->
            <header class="fq-hero wc-reveal">
                <div class="fq-hero__glass">
                    <span class="wc-eyebrow"><span class="wc-dot"></span> FAQ & Pusat Bantuan</span>
                    <h1>Pertanyaan yang sering <span class="wc-grad">diajukan.</span></h1>
                    <p>
                        Segala hal yang perlu Anda ketahui tentang cara melamar, alur seleksi, serta panduan karir di
                        EVO Group — semuanya terkumpul di satu halaman.
                    </p>
                    <div v-if="totalFaq" class="fq-hero__stats">
                        <div class="fq-stat-pill">
                            <i class="bi bi-patch-question-fill"></i>
                            <span
                                ><b>{{ totalFaq }}</b> Pertanyaan</span
                            >
                        </div>
                        <div class="fq-stat-pill">
                            <i class="bi bi-collection-fill"></i>
                            <span
                                ><b>{{ kategori.length }}</b> Kategori</span
                            >
                        </div>
                        <div class="fq-stat-pill fq-stat-pill--success">
                            <i class="bi bi-shield-check"></i>
                            <span><b>100% Gratis</b> Tanpa Biaya</span>
                        </div>
                    </div>
                </div>
            </header>

            <!-- ── Belum ada data ────────────────────────────────── -->
            <div v-if="!totalFaq" class="fq-empty">
                <i class="bi bi-patch-question"></i>
                <strong>Daftar pertanyaan sedang disiapkan</strong>
                <span>
                    Tim rekrutmen sedang menyusun jawaban untuk pertanyaan yang paling sering diajukan. Sementara itu,
                    lihat lowongan yang tersedia.
                </span>
                <Link href="/karir/lowongan" class="fq-empty__btn">
                    <i class="bi bi-briefcase-fill"></i> Lihat Lowongan Kerja
                </Link>
            </div>

            <template v-else>
                <!-- ── Pencarian & Keyword Quick Tags ───────────────── -->
                <div class="fq-search-container wc-reveal">
                    <div class="fq-search">
                        <i class="bi bi-search"></i>
                        <input
                            v-model="search"
                            type="text"
                            placeholder="Cari pertanyaan..."
                            aria-label="Cari pertanyaan"
                        />
                        <button
                            v-if="search"
                            type="button"
                            class="fq-search__clear"
                            aria-label="Hapus pencarian"
                            @click="search = ''"
                        >
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>

                    <!-- Quick Keyword Suggestions -->
                    <div class="fq-keywords">
                        <span class="fq-keywords__label"
                            ><i class="bi bi-lightning-charge-fill"></i> Sering Dicari:</span
                        >
                        <div class="fq-keywords__list">
                            <button
                                v-for="kw in saringanTop"
                                :key="kw"
                                type="button"
                                class="fq-kw-tag"
                                :class="{ 'is-active': search.toLowerCase() === kw.toLowerCase() }"
                                @click="isikanCari(kw)"
                            >
                                {{ kw }}
                            </button>
                        </div>
                    </div>
                </div>

                <!-- ── Navigation Bar Kategori ── -->
                <nav v-if="!search" class="fq-chips wc-reveal" aria-label="Kategori pertanyaan">
                    <button
                        type="button"
                        class="fq-chip"
                        :class="{ 'is-active': activeKat === 'all' }"
                        @click="pilihKat('all')"
                    >
                        <i class="bi bi-grid-fill"></i>
                        Semua Kategori
                        <span class="fq-chip__n">{{ totalFaq }}</span>
                    </button>
                    <button
                        v-for="k in kategori"
                        :key="k.id"
                        type="button"
                        class="fq-chip"
                        :class="{ 'is-active': activeKat === String(k.id) }"
                        @click="pilihKat(String(k.id))"
                    >
                        <i class="bi" :class="k.ikon || 'bi-collection-fill'"></i>
                        {{ k.nama }}
                        <span class="fq-chip__n">{{ k.jumlah }}</span>
                    </button>
                </nav>

                <p v-if="search" class="fq-result">
                    Menampilkan <b>{{ faqTersaring.length }}</b> hasil pertanyaan untuk "<b>{{ search }}</b
                    >"
                </p>

                <!-- ── Hasil pencarian ───────────────────────────────── -->
                <section v-if="search" class="fq-section">
                    <div v-if="!faqTersaring.length" class="fq-nihil">
                        <i class="bi bi-search"></i>
                        <strong>Tidak ada pertanyaan yang cocok</strong>
                        <span>Coba gunakan kata kunci lain, atau hubungi tim rekrutmen kami secara langsung.</span>
                        <button type="button" class="fq-empty__btn" @click="search = ''">Reset Pencarian</button>
                    </div>
                    <FaqAccordion v-else :items="faqTersaring" show-detail :initial-open="slugAwal" @open="saatDibuka">
                        <template #aksi="{ item }">
                            <FaqAksi
                                :item="item"
                                :status="statusVote[item.id]"
                                :tersalin="tersalin === item.slug"
                                @vote="kirimVote"
                                @salin="salinTautan"
                            />
                        </template>
                    </FaqAccordion>
                </section>

                <!-- ── Section per kategori ──────────────────────── -->
                <template v-else>
                    <section
                        v-for="k in kategoriTampil"
                        :id="'kategori-' + k.id"
                        :key="k.id"
                        class="fq-section wc-reveal"
                    >
                        <div class="fq-section__head">
                            <span class="fq-section__icon"
                                ><i class="bi" :class="k.ikon || 'bi-collection-fill'"></i
                            ></span>
                            <div class="fq-section__meta">
                                <div class="fq-section__title-row">
                                    <h2>{{ k.nama }}</h2>
                                    <span class="fq-section__count">{{ k.jumlah }} pertanyaan</span>
                                </div>
                                <p v-if="k.deskripsi">{{ k.deskripsi }}</p>
                            </div>
                        </div>

                        <FaqAccordion
                            :items="perKategori(k.id)"
                            show-detail
                            :initial-open="slugAwal"
                            @open="saatDibuka"
                        >
                            <template #aksi="{ item }">
                                <FaqAksi
                                    :item="item"
                                    :status="statusVote[item.id]"
                                    :tersalin="tersalin === item.slug"
                                    @vote="kirimVote"
                                    @salin="salinTautan"
                                />
                            </template>
                        </FaqAccordion>
                    </section>
                </template>

                <!-- ── CTA Penutup Premium ────────────────────────── -->
                <section class="fq-cta wc-reveal">
                    <div class="fq-cta__inner">
                        <div class="fq-cta__glow"></div>
                        <span class="fq-cta__icon"><i class="bi bi-headset"></i></span>
                        <h2>Masih memiliki pertanyaan lain?</h2>
                        <p>
                            Jika Anda belum menemukan jawaban yang dicari, tim rekrutmen EVO Group siap membantu
                            memberikan penjelasan langsung.
                        </p>
                        <div class="fq-cta__actions">
                            <a class="fq-cta__btn fq-cta__btn--primary" :href="'mailto:' + EMAIL_HR">
                                <i class="bi bi-envelope-paper-fill"></i> Hubungi Rekrutmen
                            </a>
                            <Link class="fq-cta__btn fq-cta__btn--ghost" href="/karir/lowongan">
                                <i class="bi bi-briefcase-fill"></i> Lihat Lowongan Kerja
                            </Link>
                        </div>
                        <div class="fq-cta__note">
                            <i class="bi bi-shield-check"></i>
                            <span>Seluruh proses seleksi & rekrutmen EVO Group <b>100% bebas biaya</b> (gratis).</span>
                        </div>
                    </div>
                </section>
            </template>
        </div>
    </CareerLayout>
</template>

<script setup>
import { Head, Link } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, nextTick, onMounted, onUnmounted, ref } from 'vue';
import CareerLayout from './Layouts/CareerLayout.vue';
import FaqAccordion from './components/FaqAccordion.vue';
import FaqAksi from './components/FaqAksi.vue';
import { goToSection, observeReveal, scrollToId } from '@utils/career/data';

defineOptions({ layout: null });

const EMAIL_HR = 'recruitment@evonusabersaudara.co.id';

const props = defineProps({
    hasMt: { type: Boolean, default: false },
    offices: { type: Array, default: () => [] },
    // Master FAQ: kategori aktif yang punya isi + seluruh pertanyaan aktif.
    kategori: { type: Array, default: () => [] },
    faq: { type: Array, default: () => [] },
});

const hasMt = computed(() => props.hasMt);
const offices = computed(() => props.offices || []);
const kategori = computed(() => props.kategori || []);
const faq = computed(() => props.faq || []);
const totalFaq = computed(() => faq.value.length);

const search = ref('');
const activeKat = ref('all');
const saringanTop = ['Pendaftaran', 'Formulir', 'Tahapan Seleksi', 'Persyaratan', 'Penempatan'];

function isikanCari(kw) {
    if (search.value.toLowerCase() === kw.toLowerCase()) {
        search.value = '';
    } else {
        search.value = kw;
    }
}

function pilihKat(id) {
    activeKat.value = id;
    if (id !== 'all') {
        scrollToId('kategori-' + id);
    }
}

const kategoriTampil = computed(() => {
    if (activeKat.value === 'all') return kategori.value;
    return kategori.value.filter((k) => String(k.id) === activeKat.value);
});

// ── Pencarian ───────────────────────────────────────────────
const faqTersaring = computed(() => {
    const q = search.value.trim().toLowerCase();
    if (!q) return faq.value;

    return faq.value.filter((f) =>
        [f.pertanyaan, f.jawaban, f.cari, f.kategoriNama].filter(Boolean).join(' ').toLowerCase().includes(q),
    );
});

const perKategori = (id) => faq.value.filter((f) => f.kategoriId === id);

// ── Deep link ───────────────────────────────────────────────
const slugAwal = ref('');

function bacaHash() {
    const hash = decodeURIComponent((window.location.hash || '').replace(/^#/, ''));
    if (!hash) return;

    const target = faq.value.find((f) => f.slug === hash);
    if (!target) return;

    slugAwal.value = hash;
    nextTick(() => setTimeout(() => scrollToId('faq-' + hash), 220));
}

// ── Penghitung dilihat ──────────────────────────────────────
const sudahDicatat = new Set();

function saatDibuka(slug) {
    const item = faq.value.find((f) => f.slug === slug);
    if (!item || sudahDicatat.has(slug)) return;
    sudahDicatat.add(slug);

    try {
        window.history.replaceState(null, '', '#' + slug);
    } catch (e) {}

    axios.post(`/api/v1/karir/faq/${item.id}/dilihat`).catch(() => {});
}

// ── Penilaian "membantu" ────────────────────────────────────
const statusVote = ref({});
const tersalin = ref('');

async function kirimVote({ id, membantu }) {
    if (statusVote.value[id]) return;
    statusVote.value = { ...statusVote.value, [id]: 'mengirim' };

    try {
        await axios.post(`/api/v1/karir/faq/${id}/membantu`, { membantu });
        statusVote.value = { ...statusVote.value, [id]: 'selesai' };
    } catch (e) {
        statusVote.value = { ...statusVote.value, [id]: e?.response?.status === 409 ? 'selesai' : 'gagal' };
    }
}

async function salinTautan(slug) {
    const url = `${window.location.origin}/karir/faq#${slug}`;
    try {
        await navigator.clipboard.writeText(url);
        tersalin.value = slug;
        setTimeout(() => (tersalin.value = ''), 1800);
    } catch (e) {
        window.history.replaceState(null, '', '#' + slug);
    }
}

let revealObserver = null;
onMounted(() => {
    nextTick(() => {
        revealObserver = observeReveal();
        bacaHash();
    });
});
onUnmounted(() => revealObserver?.disconnect());
</script>

<style scoped>
.fq {
    width: min(960px, calc(100vw - 2rem));
    margin: 0 auto;
    padding: clamp(1.5rem, 4vw, 2.5rem) 0 clamp(3rem, 6vw, 4.5rem);
}

/* ── Hero Glass ── */
.fq-hero {
    margin: clamp(1rem, 3vw, 2rem) auto clamp(1.75rem, 4vw, 2.5rem);
}
.fq-hero__glass {
    position: relative;
    padding: clamp(2rem, 5vw, 3.25rem) clamp(1.5rem, 4vw, 2.75rem);
    border-radius: 1.75rem;
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.95) 0%, rgba(248, 250, 252, 0.9) 100%);
    border: 1.5px solid rgba(226, 232, 240, 0.95);
    box-shadow: 0 20px 45px rgba(15, 23, 42, 0.04);
    text-align: center;
    overflow: hidden;
}
.fq-hero__glass::before {
    content: '';
    position: absolute;
    top: -50%;
    left: 50%;
    transform: translateX(-50%);
    width: 60%;
    height: 100%;
    background: radial-gradient(circle, rgba(99, 102, 241, 0.12) 0%, rgba(168, 85, 247, 0) 70%);
    pointer-events: none;
}
.fq-hero h1 {
    margin: 0.85rem 0 0.75rem;
    font-size: clamp(1.85rem, 4.5vw, 2.75rem);
    font-weight: 900;
    letter-spacing: -0.025em;
    color: #1e1b4b;
    line-height: 1.25;
}
.fq-hero p {
    max-width: 650px;
    margin: 0 auto;
    color: #475569;
    font-size: 1.02rem;
    line-height: 1.75;
}

.fq-hero__stats {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: center;
    gap: 0.75rem;
    margin-top: 1.6rem;
}
.fq-stat-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    padding: 0.5rem 1.1rem;
    border-radius: 999px;
    background: #ffffff;
    border: 1px solid rgba(226, 232, 240, 0.9);
    color: #475569;
    font-size: 0.85rem;
    font-weight: 600;
    box-shadow: 0 4px 12px rgba(15, 23, 42, 0.03);
}
.fq-stat-pill i {
    color: #6366f1;
    font-size: 0.95rem;
}
.fq-stat-pill b {
    color: #4f46e5;
}
.fq-stat-pill--success {
    background: rgba(16, 185, 129, 0.08);
    border-color: rgba(16, 185, 129, 0.25);
    color: #059669;
}
.fq-stat-pill--success i {
    color: #10b981;
}
.fq-stat-pill--success b {
    color: #047857;
}

/* ── Pencarian & Keywords ── */
.fq-search-container {
    margin-bottom: 1.4rem;
}
.fq-search {
    position: relative;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.9rem 1.25rem;
    border-radius: 999px;
    background: #ffffff;
    border: 1.5px solid rgba(226, 232, 240, 0.95);
    box-shadow: 0 12px 32px rgba(15, 23, 42, 0.05);
    transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
}
.fq-search:focus-within {
    border-color: #6366f1;
    box-shadow: 0 16px 40px rgba(99, 102, 241, 0.18);
}
.fq-search i {
    color: #6366f1;
    font-size: 1.1rem;
}
.fq-search input {
    flex: 1;
    border: 0;
    outline: 0;
    background: none;
    font-size: 0.98rem;
    color: #1e1b4b;
}
.fq-search__clear {
    display: grid;
    place-items: center;
    width: 1.8rem;
    height: 1.8rem;
    border: 0;
    border-radius: 50%;
    background: #f1f5f9;
    color: #64748b;
    cursor: pointer;
    transition: background 0.18s ease;
}
.fq-search__clear:hover {
    background: #e2e8f0;
    color: #1e293b;
}

.fq-keywords {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.6rem;
    margin-top: 0.9rem;
    padding: 0 0.5rem;
}
.fq-keywords__label {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.8rem;
    font-weight: 700;
    color: #64748b;
}
.fq-keywords__label i {
    color: #f59e0b;
}
.fq-keywords__list {
    display: flex;
    flex-wrap: wrap;
    gap: 0.4rem;
}
.fq-kw-tag {
    padding: 0.25rem 0.75rem;
    border-radius: 999px;
    border: 1px solid #e2e8f0;
    background: #ffffff;
    color: #475569;
    font-size: 0.78rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.18s ease;
}
.fq-kw-tag:hover,
.fq-kw-tag.is-active {
    background: rgba(99, 102, 241, 0.1);
    border-color: #6366f1;
    color: #4f46e5;
}

/* ── Chip Kategori Filter ── */
.fq-chips {
    display: flex;
    flex-wrap: wrap;
    gap: 0.65rem;
    margin-bottom: 2rem;
}
.fq-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.55rem;
    padding: 0.6rem 1.15rem;
    border-radius: 999px;
    border: 1.5px solid rgba(226, 232, 240, 0.95);
    background: #ffffff;
    color: #334155;
    font-size: 0.88rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.22s cubic-bezier(0.16, 1, 0.3, 1);
}
.fq-chip:hover {
    border-color: #6366f1;
    color: #4f46e5;
    transform: translateY(-2px);
    box-shadow: 0 10px 22px rgba(99, 102, 241, 0.12);
}
.fq-chip.is-active {
    background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
    border-color: transparent;
    color: #ffffff;
    box-shadow: 0 10px 24px rgba(99, 102, 241, 0.28);
}
.fq-chip.is-active i {
    color: #ffffff;
}
.fq-chip i {
    color: #6366f1;
}
.fq-chip__n {
    padding: 0.08rem 0.5rem;
    border-radius: 999px;
    background: rgba(99, 102, 241, 0.12);
    color: #4f46e5;
    font-size: 0.75rem;
    font-weight: 800;
}
.fq-chip.is-active .fq-chip__n {
    background: rgba(255, 255, 255, 0.22);
    color: #ffffff;
}

.fq-result {
    margin: 0 0 1.3rem;
    color: #475569;
    font-size: 0.92rem;
}

/* ── Section Kategori ── */
.fq-section {
    margin-bottom: clamp(2.25rem, 5vw, 3rem);
    scroll-margin-top: 96px;
}
.fq-section__head {
    display: flex;
    align-items: flex-start;
    gap: 1rem;
    margin-bottom: 1.25rem;
}
.fq-section__icon {
    display: grid;
    place-items: center;
    width: 3rem;
    height: 3rem;
    flex: none;
    border-radius: 1rem;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    color: #ffffff;
    font-size: 1.25rem;
    box-shadow: 0 10px 24px rgba(99, 102, 241, 0.28);
}
.fq-section__meta {
    flex: 1;
}
.fq-section__title-row {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    flex-wrap: wrap;
}
.fq-section__title-row h2 {
    margin: 0;
    font-size: 1.3rem;
    font-weight: 900;
    letter-spacing: -0.015em;
    color: #1e1b4b;
}
.fq-section__count {
    padding: 0.15rem 0.65rem;
    border-radius: 999px;
    background: rgba(99, 102, 241, 0.08);
    color: #4f46e5;
    font-size: 0.76rem;
    font-weight: 700;
}
.fq-section__meta p {
    margin: 0.25rem 0 0;
    color: #64748b;
    font-size: 0.9rem;
    line-height: 1.6;
}

/* ── Kosong / Nihil ── */
.fq-empty,
.fq-nihil {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.65rem;
    padding: clamp(2.25rem, 6vw, 3.5rem) 1.5rem;
    text-align: center;
    border-radius: 1.5rem;
    background: #ffffff;
    border: 1.5px dashed rgba(148, 163, 184, 0.5);
    box-shadow: 0 10px 30px rgba(15, 23, 42, 0.03);
}
.fq-empty i,
.fq-nihil i {
    font-size: 2.5rem;
    color: #818cf8;
}
.fq-empty strong,
.fq-nihil strong {
    font-size: 1.1rem;
    color: #1e1b4b;
}
.fq-empty span,
.fq-nihil span {
    max-width: 480px;
    color: #64748b;
    font-size: 0.93rem;
    line-height: 1.65;
}
.fq-empty__btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    margin-top: 0.6rem;
    padding: 0.75rem 1.5rem;
    border-radius: 999px;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    border: none;
    color: #ffffff;
    font-size: 0.92rem;
    font-weight: 800;
    text-decoration: none;
    cursor: pointer;
    box-shadow: 0 10px 24px rgba(99, 102, 241, 0.25);
    transition: all 0.22s ease;
}
.fq-empty__btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 14px 30px rgba(99, 102, 241, 0.35);
}

/* ── CTA Penutup Premium ── */
.fq-cta {
    margin-top: clamp(2.5rem, 6vw, 3.5rem);
}
.fq-cta__inner {
    position: relative;
    padding: clamp(2.25rem, 5vw, 3.25rem) clamp(1.5rem, 4vw, 2.5rem);
    border-radius: 1.75rem;
    text-align: center;
    background: linear-gradient(135deg, #1e1b4b 0%, #312e81 60%, #4338ca 100%);
    color: #ffffff;
    box-shadow: 0 25px 50px -12px rgba(49, 46, 129, 0.4);
    overflow: hidden;
}
.fq-cta__glow {
    position: absolute;
    top: -50%;
    left: 50%;
    transform: translateX(-50%);
    width: 70%;
    height: 120%;
    background: radial-gradient(circle, rgba(168, 85, 247, 0.25) 0%, rgba(99, 102, 241, 0) 70%);
    pointer-events: none;
}
.fq-cta__icon {
    display: grid;
    place-items: center;
    width: 3.4rem;
    height: 3.4rem;
    margin: 0 auto 1.1rem;
    border-radius: 1.1rem;
    background: rgba(255, 255, 255, 0.15);
    backdrop-filter: blur(8px);
    color: #ffffff;
    font-size: 1.4rem;
    box-shadow: 0 10px 24px rgba(0, 0, 0, 0.18);
}
.fq-cta__inner h2 {
    margin: 0 0 0.6rem;
    font-size: clamp(1.35rem, 3.5vw, 1.75rem);
    font-weight: 900;
    letter-spacing: -0.02em;
    color: #ffffff;
}
.fq-cta__inner p {
    max-width: 540px;
    margin: 0 auto 1.6rem;
    color: rgba(255, 255, 255, 0.85);
    font-size: 0.98rem;
    line-height: 1.7;
}
.fq-cta__actions {
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    gap: 0.75rem;
}
.fq-cta__btn {
    display: inline-flex;
    align-items: center;
    gap: 0.55rem;
    padding: 0.85rem 1.65rem;
    border-radius: 999px;
    font-size: 0.93rem;
    font-weight: 800;
    text-decoration: none;
    transition: all 0.22s cubic-bezier(0.16, 1, 0.3, 1);
}
.fq-cta__btn--primary {
    background: #ffffff;
    color: #4f46e5;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
}
.fq-cta__btn--primary:hover {
    background: #f8fafc;
    transform: translateY(-2px);
    box-shadow: 0 14px 30px rgba(0, 0, 0, 0.3);
}
.fq-cta__btn--ghost {
    background: rgba(255, 255, 255, 0.12);
    border: 1px solid rgba(255, 255, 255, 0.25);
    color: #ffffff;
    backdrop-filter: blur(4px);
}
.fq-cta__btn--ghost:hover {
    background: rgba(255, 255, 255, 0.22);
    transform: translateY(-2px);
}
.fq-cta__note {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    margin-top: 1.5rem;
    color: rgba(255, 255, 255, 0.8);
    font-size: 0.83rem;
}
.fq-cta__note i {
    color: #34d399;
    font-size: 1rem;
}

@media (max-width: 640px) {
    .fq-cta__actions {
        flex-direction: column;
    }
    .fq-cta__btn {
        justify-content: center;
    }
}
</style>
