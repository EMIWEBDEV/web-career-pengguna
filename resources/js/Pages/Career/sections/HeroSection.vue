<!-- WEB CAREER — Section 1: Hero (fade carousel dari DB — Master Hero). Slide bertanda
     "tampilkan konten" merender headline/search/CTA/kartu statistik EVO Group (tetap,
     tidak diedit dari DB); slide lain murni media (gambar desktop/mobile atau video). -->
<template>
    <section
        id="hero"
        class="wc-hero2"
        @touchstart.passive="onSwipeStart"
        @touchend.passive="onSwipeEnd"
    >
        <!-- Media latar: carousel fade video + foto -->
        <div class="wc-hero2__media" aria-hidden="true">
            <div
                v-for="(slide, i) in resolvedSlides"
                :key="slide.id"
                class="wc-hero2__slide"
                :class="{ 'is-active': i === activeIndex }"
            >
                <video
                    v-if="slide.type === 'video' && slide.video"
                    :ref="(el) => setVideoRef(el, i)"
                    class="wc-hero2__media-el"
                    :class="{ 'has-zoom': slide.zoomAnimation === 'Y' }"
                    :src="slide.video"
                    :poster="slide.poster"
                    muted
                    playsinline
                    autoplay
                    preload="metadata"
                    @timeupdate="onVideoProgress"
                    @ended="next"
                ></video>
                <picture v-else>
                    <source v-if="slide.imgMobile" media="(max-width: 1023px)" :srcset="slide.imgMobile" />
                    <img
                        class="wc-hero2__media-el"
                        :class="{ 'has-zoom': slide.zoomAnimation === 'Y' }"
                        :src="slide.image"
                        :alt="slide.label"
                        decoding="async"
                        :fetchpriority="i === 0 ? 'high' : 'low'"
                        :loading="i === 0 ? 'eager' : 'lazy'"
                    />
                </picture>
                <div class="wc-hero2__slide-scrim" :class="'wc-hero2__slide-scrim--' + slide.overlay"></div>
            </div>
            <div class="wc-hero2__fade"></div>
        </div>

        <!-- Konten tetap EVO Group (headline, search, CTA, kartu statistik).
             Di luar .wc-hero2__media (yang absolute + overflow:hidden) supaya tingginya
             ikut mendorong section — kalau di dalam, konten terpotong di layar pendek. -->
        <transition name="wc-hero2-fade">
            <div v-if="activeSlide && activeSlide.showContent" class="wc-hero2__inner">
                <div class="wc-hero2__content" aria-hidden="false">
                    <h1 class="wc-hero2__title">
                        Bangun Karirmu Bersama
                        <span class="wc-hero2__grad">EVO Group</span>
                    </h1>
                    <p class="wc-hero2__sub">
                        Bergabunglah dengan EVO FAMILY, perusahaan terdepan di bidang pet food industry di Indonesia. Temukan peran, tumbuh, dan wujudkan versi terbaik dirimu.
                    </p>

                    <div class="wc-hero2__search">
                        <form @submit.prevent="handleSearch" class="wc-hero2__search-form">
                            <i class="bi bi-search wc-hero2__search-icon"></i>
                            <input
                                v-model="searchQuery"
                                type="text"
                                class="wc-hero2__search-input"
                                placeholder="Cari posisi atau kata kunci..."
                            />
                            <button type="submit" class="wc-hero2__search-btn">
                                Cari <i class="bi bi-arrow-right"></i>
                            </button>
                        </form>
                    </div>

                    <div class="wc-hero2__actions">
                        <Link class="wc-hero2__btn wc-hero2__btn--primary" href="/karir/lowongan">
                            <i class="bi bi-briefcase"></i> Semua Lowongan
                        </Link>
                        <button v-if="hasMt" class="wc-hero2__btn wc-hero2__btn--glass" type="button" @click="goToSection('mt')">
                            <i class="bi bi-stars"></i> Management Trainee
                        </button>
                    </div>

                    <ul class="wc-hero2__chips">
                        <li v-for="b in benefits" :key="b.title"><i class="bi" :class="b.icon"></i> {{ b.title }}</li>
                    </ul>
                </div>

                <!-- Kartu glass mengambang -->
                <aside class="wc-hero2__card">
                    <div class="wc-hero2__card-shine" aria-hidden="true"></div>
                    <!-- Statistik ditulis sebagai DATA, bukan tiga blok markup
                         kembar: angkanya berubah (jumlah lokasi datang dari
                         database), dan tiga salinan berarti tiga tempat yang
                         bisa menyimpang. Kolomnya dibagi rata lewat grid
                         sehingga lebar angka — 2 digit atau 5 — tidak pernah
                         menggeser garis pemisahnya. -->
                    <div class="wc-hero2__stats">
                        <div v-for="s in statCards" :key="s.label" class="wc-hero2__stat">
                            <strong>{{ s.value }}<span v-if="s.suffix">{{ s.suffix }}</span></strong>
                            <small>{{ s.label }}</small>
                        </div>
                    </div>
                    <div class="wc-hero2__ribbon">
                        <span class="wc-hero2__ribbon-dot" aria-hidden="true"></span>
                        <span>Perusahaan terdepan di bidang pet food industry di Indonesia</span>
                    </div>
                </aside>
            </div>
        </transition>

        <!-- Pagination timeline ala pertamina.com -->
        <div v-if="resolvedSlides.length > 1" class="wc-hero2__pagination" role="tablist" aria-label="Sorotan EVO Group">
            <button
                v-for="(slide, i) in resolvedSlides"
                :key="'p-' + slide.id"
                type="button"
                class="wc-hero2__bullet"
                :class="{ 'is-active': i === activeIndex }"
                role="tab"
                :aria-selected="i === activeIndex"
                @click="goTo(i)"
            >
                <span class="wc-hero2__bullet-top">
                    <span class="wc-hero2__bullet-dot"></span>
                    <span class="wc-hero2__bullet-title">{{ slide.label }}</span>
                </span>
                <span class="wc-hero2__bullet-track">
                    <span
                        class="wc-hero2__bullet-progress"
                        :style="{ width: (i === activeIndex ? progress : 0) + '%' }"
                    ></span>
                </span>
            </button>
        </div>
    </section>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount, watch, nextTick } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import { goToSection } from '@utils/career/data';

const props = defineProps({
    benefits: { type: Array, default: () => [] },
    hasMt: { type: Boolean, default: false },
    heroSlides: { type: Array, default: () => [] },
    offices: { type: Array, default: () => [] },
});

const searchQuery = ref('');

/* Angka besar diformat gaya Indonesia (1.200), bukan ditulis tangan — begitu
   nilainya datang dari database, pemisah ribuannya ikut benar dengan sendirinya. */
const statCards = computed(() => [
    { value: (1200).toLocaleString('id-ID'), suffix: '++', label: 'Karyawan Aktif' },
    { value: '15', suffix: '++', label: 'Tahun Berkarya' },
    // Jumlah lokasi tanpa imbuhan: angkanya dihitung tepat dari master lokasi aktif.
    { value: props.offices.length.toLocaleString('id-ID'), suffix: '', label: 'Lokasi Operasional' },
]);

function handleSearch() {
    if (searchQuery.value.trim()) {
        router.visit(`/karir/lowongan?q=${encodeURIComponent(searchQuery.value.trim())}`);
    } else {
        router.visit('/karir/lowongan');
    }
}

/* Fallback statis — dipakai HANYA bila Master Hero belum berisi data (tabel baru/kosong),
   supaya hero tidak pernah tampil kosong. */
const FALLBACK_SLIDES = [
    {
        id: 'fallback-evo-group',
        type: 'image',
        imgMobile: null,
        video: null,
        poster: null,
        label: 'EVO Group',
        duration: 7000,
        showContent: true,
        overlay: 'brand',
        zoomAnimation: 'N',
    },
];

function pick(...values) {
    return values.find(Boolean) || null;
}

/* Normalisasi slide dari API (Master Hero) ke bentuk internal komponen. */
const resolvedSlides = computed(() => {
    const rows = props.heroSlides || [];
    if (!rows.length) return FALLBACK_SLIDES;

    return rows.map((r) => {
        const desktopImage = r.gambarDesktop || null;
        const mobileImage = r.gambarMobile || null;
        const desktopPoster = r.videoDesktopPoster || null;
        const mobilePoster = r.videoMobilePoster || desktopPoster || null;
        const desktopVideo = r.videoDesktopUrl || null;
        const mobileVideo = r.videoMobileUrl || desktopVideo || null;
        const mobile = isMobile.value;

        return {
            id: r.id,
            type: r.tipe === 'VIDEO' ? 'video' : 'image',
            imgMobile: mobileImage,
            video: mobile ? pick(mobileVideo, desktopVideo) : pick(desktopVideo, mobileVideo),
            poster: mobile
                ? pick(mobilePoster, mobileImage, desktopPoster, desktopImage)
                : pick(desktopPoster, desktopImage, mobilePoster, mobileImage),
            image: mobile
                ? pick(mobileImage, desktopImage, mobilePoster, desktopPoster)
                : pick(desktopImage, mobileImage, desktopPoster, mobilePoster),
            label: r.label,
            duration: r.durasiMs || 5000,
            showContent: !!r.tampilkanKonten,
            overlay: r.overlay || 'dark',
            zoomAnimation: r.zoomAnimation || 'N',
        };
    });
});

const activeSlide = computed(() => resolvedSlides.value[activeIndex.value] || null);

/* Video tidak dimuat di layar <1024px (hemat bandwidth) — dipakai poster/gambar mobile sebagai gantinya. */
const isMobile = ref(false);
let mq = null;
function syncMobile() {
    isMobile.value = mq ? mq.matches : false;
}

const activeIndex = ref(0);
const progress = ref(0);
const videoRefs = {};

function setVideoRef(el, i) {
    if (el) videoRefs[i] = el;
    else delete videoRefs[i];
}

let timerId = null;
let elapsed = 0;

function clearTimer() {
    if (timerId) {
        clearInterval(timerId);
        timerId = null;
    }
}

function startTimer() {
    clearTimer();
    elapsed = 0;
    progress.value = 0;

    const slide = resolvedSlides.value[activeIndex.value];
    if (slide.type === 'video' && slide.video) return; // progres video ditangani oleh @timeupdate/@ended

    const step = 50;
    const duration = slide.duration;
    timerId = setInterval(() => {
        elapsed += step;
        progress.value = Math.min(100, (elapsed / duration) * 100);
        if (elapsed >= duration) next();
    }, step);
}

function onVideoProgress(e) {
    const v = e.target;
    if (v.duration) progress.value = Math.min(100, (v.currentTime / v.duration) * 100);
}

function playActiveVideo() {
    Object.entries(videoRefs).forEach(([i, el]) => {
        if (Number(i) === activeIndex.value) {
            el.currentTime = 0;
            el.play().catch(() => {});
        } else {
            el.pause();
        }
    });
}

function next() {
    goTo((activeIndex.value + 1) % resolvedSlides.value.length);
}

function prev() {
    const n = resolvedSlides.value.length;
    goTo((activeIndex.value - 1 + n) % n);
}

/* Geser jari untuk ganti slide — di ponsel strip pagination terlalu kecil untuk
   jadi satu-satunya cara pindah. Listener-nya passive: gulir vertikal halaman
   tidak boleh ikut tertahan. */
let swipeX = null;
let swipeY = null;

function onSwipeStart(e) {
    const t = e.changedTouches[0];
    swipeX = t.clientX;
    swipeY = t.clientY;
}

function onSwipeEnd(e) {
    if (swipeX === null || resolvedSlides.value.length < 2) return;
    const t = e.changedTouches[0];
    const dx = t.clientX - swipeX;
    const dy = t.clientY - swipeY;
    swipeX = null;
    // Ambang 45px menyaring ketukan; dominasi sumbu X menyaring gerak menggulir.
    if (Math.abs(dx) < 45 || Math.abs(dx) <= Math.abs(dy)) return;
    if (dx < 0) next();
    else prev();
}

function goTo(i) {
    if (i === activeIndex.value) return;
    activeIndex.value = i;
}

watch(activeIndex, () => {
    startTimer();
    nextTick(playActiveVideo);
});

watch(resolvedSlides, () => {
    activeIndex.value = 0;
    startTimer();
    nextTick(playActiveVideo);
});

watch(isMobile, () => {
    startTimer();
    nextTick(playActiveVideo);
});

onMounted(() => {
    mq = window.matchMedia('(max-width: 1023px)');
    syncMobile();
    mq.addEventListener('change', syncMobile);

    startTimer();
    nextTick(playActiveVideo);
});

onBeforeUnmount(() => {
    clearTimer();
    if (mq) mq.removeEventListener('change', syncMobile);
});
</script>

<style scoped>
/* HERO v2 — full-bleed video/foto fade carousel + scrim gelap diagonal ala pertamina.com */
.wc-hero2 {
    position: relative;
    min-height: 100vh;
    min-height: 100svh;
    display: flex;
    align-items: center;
    overflow: hidden;
    isolation: isolate;
    background: #14142b;
}
.wc-hero2__media {
    position: absolute;
    inset: 0;
    z-index: -1;
    overflow: hidden;
}
.wc-hero2__slide {
    position: absolute;
    inset: 0;
    opacity: 0;
    transition: opacity 1.1s ease;
    will-change: opacity;
    display: flex;
    align-items: center;
}
.wc-hero2__slide.is-active {
    opacity: 1;
    z-index: 1;
}
.wc-hero2__media-el {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    object-fit: cover;
    object-position: center 32%;
}
.wc-hero2__media-el.has-zoom {
    transform: scale(1.06);
    animation: wcHeroZoom 22s ease-in-out infinite alternate;
    will-change: transform;
}
@keyframes wcHeroZoom {
    from {
        transform: scale(1.06);
    }
    to {
        transform: scale(1.16);
    }
}
/* Scrim tipis default — hanya menegaskan gradasi bawah agar pagination tetap terbaca, foto/video tetap terang seperti pertamina.com. */
.wc-hero2__slide-scrim {
    position: absolute;
    inset: 0;
    background: linear-gradient(180deg, rgba(0, 0, 0, 0) 0%, rgba(0, 0, 0, 0) 68%, rgba(0, 0, 0, 0.55) 100%);
}
.wc-hero2__slide-scrim--none {
    background: none;
}
.wc-hero2__slide-scrim--light {
    background: linear-gradient(180deg, rgba(0, 0, 0, 0) 0%, rgba(0, 0, 0, 0) 60%, rgba(0, 0, 0, 0.3) 100%);
}
/* Scrim khusus slide konten (overlay="brand") — violet brand agar teks & kartu tetap kontras. */
.wc-hero2__slide-scrim--brand {
    background:
        linear-gradient(102deg, rgba(74, 66, 150, 0.68) 0%, rgba(103, 94, 190, 0.48) 44%, rgba(148, 136, 220, 0.28) 74%, rgba(196, 181, 253, 0.14) 100%),
        radial-gradient(120% 90% at 88% 8%, rgba(248, 247, 253, 0.3) 0%, rgba(248, 247, 253, 0) 46%),
        linear-gradient(180deg, rgba(48, 42, 104, 0.28) 0%, rgba(48, 42, 104, 0) 24%, rgba(48, 42, 104, 0) 56%, rgba(20, 18, 43, 0.75) 100%);
}
.wc-hero2__fade {
    position: absolute;
    left: 0;
    right: 0;
    bottom: -1px;
    height: 4rem;
    background: linear-gradient(180deg, transparent 0%, rgba(245, 244, 253, 0.4) 55%, #f8fafc 100%);
    pointer-events: none;
    z-index: 2;
}

/* Konten slide EVO Group (title, subtitle, search, CTA, chips, kartu statistik) */
.wc-hero2__inner {
    position: relative;
    z-index: 1;
    flex: 0 0 auto;
    width: min(1180px, calc(100vw - 2rem));
    margin: 0 auto;
    padding: clamp(7rem, 15vh, 10.5rem) 0 clamp(6.5rem, 16vh, 9.5rem);
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(280px, 330px);
    align-items: center;
    gap: 2.5rem;
    color: #f8fafc;
}
.wc-hero2__content {
    max-width: 640px;
}
.wc-hero2__title {
    margin: 0;
    font-size: clamp(2rem, 4.6vw, 3.5rem);
    font-weight: 800;
    line-height: 1.08;
    letter-spacing: -0.03em;
    color: #ffffff;
    text-wrap: pretty;
}
.wc-hero2__grad {
    color: #c4b5fd;
}
.wc-hero2__sub {
    max-width: 520px;
    margin: 18px 0 0;
    color: rgba(255, 255, 255, 0.86);
    font-size: 16px;
    font-weight: 400;
    line-height: 1.65;
    text-wrap: pretty;
}
.wc-hero2__search {
    margin: 24px 0 0;
    max-width: 520px;
}
.wc-hero2__search-form {
    position: relative;
    display: flex;
    align-items: center;
    background: rgba(255, 255, 255, 0.16);
    border: 1px solid rgba(255, 255, 255, 0.32);
    backdrop-filter: blur(16px);
    -webkit-backdrop-filter: blur(16px);
    border-radius: 16px;
    padding: 6px 6px 6px 18px;
    box-shadow: 0 12px 30px rgba(0, 0, 0, 0.2);
    transition: background 0.2s ease, border-color 0.2s ease;
}
.wc-hero2__search-form:focus-within {
    background: rgba(255, 255, 255, 0.24);
    border-color: rgba(255, 255, 255, 0.55);
    box-shadow: 0 16px 40px rgba(0, 0, 0, 0.28);
}
.wc-hero2__search-icon {
    font-size: 18px;
    color: rgba(255, 255, 255, 0.75);
    margin-right: 12px;
}
.wc-hero2__search-input {
    flex: 1;
    border: none;
    outline: none;
    background: transparent;
    color: #ffffff;
    font-size: 14.5px;
    font-weight: 500;
}
.wc-hero2__search-input::placeholder {
    color: rgba(255, 255, 255, 0.65);
}
.wc-hero2__search-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    border: none;
    padding: 11px 20px;
    border-radius: 12px;
    background: linear-gradient(135deg, #8b5cf6, #6366f1);
    color: #ffffff;
    font-size: 13.5px;
    font-weight: 800;
    cursor: pointer;
    transition: transform 0.16s ease, box-shadow 0.16s ease;
}
.wc-hero2__search-btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 8px 20px rgba(99, 102, 241, 0.45);
}
.wc-hero2__actions {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    margin: 20px 0 0;
}
.wc-hero2__btn {
    display: inline-flex;
    align-items: center;
    gap: 9px;
    border: none;
    padding: 15px 26px;
    border-radius: 14px;
    font: inherit;
    font-size: 14.5px;
    font-weight: 800;
    cursor: pointer;
    transition: transform 0.16s ease, box-shadow 0.16s ease, background 0.16s ease;
}
.wc-hero2__btn--primary {
    background: linear-gradient(135deg, #8b5cf6, #6366f1);
    color: #fff;
    box-shadow: 0 16px 40px rgba(99, 102, 241, 0.5);
}
.wc-hero2__btn--primary:hover {
    transform: translateY(-2px);
}
.wc-hero2__btn--glass {
    padding: 15px 24px;
    font-weight: 700;
    background: rgba(255, 255, 255, 0.14);
    border: 1px solid rgba(255, 255, 255, 0.3);
    color: #fff;
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
}
.wc-hero2__btn--glass:hover {
    background: rgba(255, 255, 255, 0.24);
}
.wc-hero2__btn--glass i {
    color: #c4b5fd;
}
.wc-hero2__chips {
    list-style: none;
    margin: 26px 0 0;
    padding: 0;
    display: flex;
    flex-wrap: wrap;
    gap: 9px;
}
.wc-hero2__chips li {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 8px 13px;
    border-radius: 11px;
    background: rgba(255, 255, 255, 0.11);
    border: 1px solid rgba(255, 255, 255, 0.2);
    color: rgba(255, 255, 255, 0.94);
    font-size: 12.5px;
    font-weight: 600;
}
.wc-hero2__chips i {
    color: rgba(255, 255, 255, 0.94);
}
.wc-hero2__card {
    position: relative;
    overflow: hidden;
    /* Ukuran angka statistik diukur terhadap LEBAR KARTU, bukan lebar layar —
       kartunya menyempit di tablet meski layarnya masih lebar. */
    container-type: inline-size;
    padding: 1.6rem 1.7rem;
    border-radius: 1.5rem;
    background: rgba(255, 255, 255, 0.09);
    border: 1px solid rgba(255, 255, 255, 0.2);
    backdrop-filter: blur(20px) saturate(1.3);
    -webkit-backdrop-filter: blur(20px) saturate(1.3);
    box-shadow: 0 30px 70px rgba(0, 0, 0, 0.4), inset 0 1px 0 rgba(255, 255, 255, 0.28);
    justify-self: end;
    width: 100%;
    max-width: 330px;
}
.wc-hero2__card-shine {
    position: absolute;
    top: -60%;
    right: -30%;
    width: 18rem;
    height: 18rem;
    background: radial-gradient(circle, rgba(255, 255, 255, 0.22), transparent 62%);
    pointer-events: none;
}
.wc-hero2__stats {
    position: relative;
    /* GRID KOLOM SAMA LEBAR, bukan flex mengikuti isi. Lebar angka tidak boleh
       menentukan tata letak: "2" hari ini bisa jadi "14" atau "1.200" besok,
       dan pada flex setiap perubahan itu menggeser garis pemisahnya. */
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    align-items: start;
}
.wc-hero2__stat {
    display: flex;
    flex-direction: column;
    gap: 4px;
    text-align: left;
    min-width: 0;
    padding: 0 14px;
}
/* GARIS PEMISAH = border kolomnya sendiri, bukan <span> terpisah. Sebagai
   elemen flex, jaraknya bergantung sisa ruang dan bisa tertimpa angka yang
   melebar; sebagai border kolom grid, letaknya selalu tepat di batas kolom. */
.wc-hero2__stat + .wc-hero2__stat {
    border-left: 1px solid rgba(255, 255, 255, 0.2);
}
.wc-hero2__stat:first-child {
    padding-left: 0;
}
.wc-hero2__stat:last-child {
    padding-right: 0;
}
.wc-hero2__stat strong {
    /* Menyusut sendiri bila angkanya panjang, sampai batas yang masih terbaca —
       jadi 5 digit pun tetap satu baris di dalam kolomnya. */
    font-size: 1.5rem; /* cadangan bila container query tak didukung */
    font-size: clamp(1.15rem, 7.5cqw, 1.6rem);
    font-weight: 800;
    letter-spacing: -0.02em;
    line-height: 1.1;
    color: #fff;
    white-space: nowrap;
    /* Digit selebar sama rata: angka yang berubah tidak membuat barisnya bergoyang. */
    font-variant-numeric: tabular-nums;
}
.wc-hero2__stat strong span {
    color: #c4b5fd;
}
.wc-hero2__stat small {
    color: rgba(255, 255, 255, 0.74);
    font-size: 11px;
    font-weight: 400;
    line-height: 1.3;
}
.wc-hero2__ribbon {
    position: relative;
    display: flex;
    align-items: center;
    gap: 8px;
    margin-top: 14px;
    padding-top: 14px;
    border-top: 1px solid rgba(255, 255, 255, 0.18);
    color: rgba(255, 255, 255, 0.82);
    font-size: 11.5px;
    font-weight: 400;
    line-height: 1.45;
}
.wc-hero2__ribbon-dot {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: #34d399;
    flex: none;
}

/* Pagination timeline bawah — meniru .swiper-pagination-line milik pertamina.com */
.wc-hero2__pagination {
    position: absolute;
    z-index: 3;
    left: 0;
    right: 0;
    bottom: 1.25rem;
    display: flex;
    gap: 0.6rem;
    width: min(1180px, calc(100vw - 2rem));
    margin: 0 auto;
    overflow-x: auto;
    scrollbar-width: none;
}
.wc-hero2__pagination::-webkit-scrollbar {
    display: none;
}
.wc-hero2__bullet {
    flex: 1 0 auto;
    min-width: 130px;
    background: transparent;
    border: none;
    cursor: pointer;
    padding: 0.5rem 0 0.7rem;
    text-align: left;
}
.wc-hero2__bullet-top {
    display: flex;
    align-items: center;
    gap: 8px;
    color: rgba(255, 255, 255, 0.55);
    transition: color 0.2s ease;
}
.wc-hero2__bullet.is-active .wc-hero2__bullet-top {
    color: #ffffff;
}
.wc-hero2__bullet-dot {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: currentColor;
    opacity: 0.35;
    flex: none;
    transition: opacity 0.2s ease, background 0.2s ease;
}
.wc-hero2__bullet.is-active .wc-hero2__bullet-dot {
    background: #c4b5fd;
    opacity: 1;
}
.wc-hero2__bullet-title {
    font-size: 12.5px;
    font-weight: 600;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.wc-hero2__bullet-track {
    display: block;
    margin-top: 8px;
    height: 3px;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.22);
    overflow: hidden;
}
.wc-hero2__bullet-progress {
    display: block;
    height: 100%;
    width: 0%;
    background: linear-gradient(90deg, #8b5cf6, #c4b5fd);
    border-radius: 999px;
    transition: width 0.05s linear;
}

/* Fade konten mengikuti fade slide (dulu ikut opacity slide induknya). */
.wc-hero2-fade-enter-active,
.wc-hero2-fade-leave-active {
    transition: opacity 0.6s ease;
}
.wc-hero2-fade-enter-from,
.wc-hero2-fade-leave-to {
    opacity: 0;
}
.wc-hero2-fade-leave-active {
    position: absolute;
    inset: 0;
}

@media (max-width: 1024px) {
    .wc-hero2__inner {
        gap: 1.75rem;
        padding: clamp(6.5rem, 13vh, 8.5rem) 0 clamp(3rem, 8vh, 5rem);
    }
}
/* TABLET TEGAK — tetap dua kolom.
   Runtuh jadi satu kolom di sini menyisakan setengah lebar layar kosong di
   kanan sekaligus membuat konten menumpuk di sepertiga atas; lebar 768px+
   masih cukup untuk teks + kartu statistik berdampingan. */
@media (min-width: 768px) and (max-width: 1024px) {
    .wc-hero2__inner {
        grid-template-columns: minmax(0, 1fr) minmax(250px, 300px);
        align-items: center;
    }
    .wc-hero2__card {
        justify-self: end;
        max-width: 300px;
        padding: 1.3rem 1.35rem;
    }
    /* Kartu lebih sempit dari desktop — jarak kolom ikut menyempit. Ukuran
       angkanya tidak diatur di sini: `cqw` sudah menyesuaikannya sendiri. */
    .wc-hero2__stat {
        padding: 0 10px;
    }
    .wc-hero2__stat small {
        font-size: 10.5px;
    }
    .wc-hero2__content {
        max-width: none;
    }
    .wc-hero2__sub,
    .wc-hero2__search {
        max-width: 440px;
    }
}
@media (max-width: 767.98px) {
    .wc-hero2__inner {
        /* minmax(0, …), BUKAN `1fr`. `1fr` = minmax(auto, 1fr): lantainya ukuran
           min-content, jadi baris terlebar di dalamnya (kartu statistik) memaksa
           kolomnya melebihi lebar layar dan SELURUH isi hero meleber ke kanan. */
        grid-template-columns: minmax(0, 1fr);
        gap: 2rem;
    }
    .wc-hero2__card {
        justify-self: start;
        max-width: 520px;
    }
    /* Kolom, bukan baris: pagination jadi baris terakhir hero.
       Rentang 641–767px dulu terlewat — layoutnya sudah satu kolom sementara
       pagination masih melayang absolute, sehingga ia menimpa kartu statistik. */
    .wc-hero2 {
        flex-direction: column;
        align-items: stretch;
    }
    .wc-hero2__inner,
    .wc-hero2__pagination {
        align-self: center;
        width: min(1180px, calc(100vw - 2rem));
    }
    .wc-hero2__pagination {
        position: relative;
        z-index: 3;
        bottom: auto;
        /* auto → menempel ke dasar hero, termasuk pada slide tanpa konten. */
        margin-top: auto;
        padding-bottom: 1.5rem;
        gap: 0.5rem;
    }
    /* Bullet dibagi rata selebar layar mengikuti jumlah slide — `min-width` tetap
       memaksa gulir samping dan memotong slide terakhir. */
    .wc-hero2__pagination {
        overflow-x: visible;
    }
    .wc-hero2__bullet {
        flex: 1 1 0;
        min-width: 0;
        padding: 0.35rem 0 0.5rem;
    }
    /* Label hanya untuk slide aktif: empat judul berdampingan di 360px sama-sama
       terpotong jadi dua kata dan tak satu pun terbaca. */
    .wc-hero2__bullet-title {
        display: none;
        font-size: 11.5px;
    }
    .wc-hero2__bullet.is-active .wc-hero2__bullet-title {
        display: block;
    }
    .wc-hero2__bullet-top {
        min-height: 1.1rem;
    }
}
@media (max-width: 640px) {
    .wc-hero2__inner {
        /* Bawah cukup 1,25rem — pagination di bawahnya sudah membawa jaraknya sendiri. */
        padding: 6.5rem 0 1.25rem;
        gap: 1.5rem;
    }
    .wc-hero2__title {
        font-size: clamp(1.75rem, 8.5vw, 2.4rem);
    }
    .wc-hero2__sub {
        font-size: 14.5px;
        line-height: 1.6;
    }
    .wc-hero2__search-form {
        padding: 5px 5px 5px 14px;
    }
    .wc-hero2__search-btn {
        padding: 10px 14px;
        min-height: 2.5rem;
    }
    /* 16px — di bawah itu Safari iOS men-zoom halaman saat field difokuskan. */
    .wc-hero2__search-input {
        font-size: 16px;
    }
    /* Satu tombol per baris: berdampingan di 360px labelnya terpotong. */
    .wc-hero2__actions .wc-hero2__btn {
        flex: 1 1 100%;
        justify-content: center;
        padding: 13px 16px;
        font-size: 13.5px;
        text-align: center;
    }
    .wc-hero2__card {
        padding: 1.25rem 1.25rem;
    }
    .wc-hero2__stat {
        padding: 0 10px;
    }
}

/* ═══════════ ROTASI MENDARAT — HERO DIRANCANG ULANG ═══════════
   Layar ~400px tinggi tidak muat menampung susunan potret/desktop apa pun:
   eyebrow + judul + paragraf + pencarian + 2 tombol + 4 chip + kartu statistik
   berbingkai. Merapatkan jaraknya hanya menunda masalah. Di sini hero disusun
   ulang jadi satu blok terpusat berisi yang benar-benar dipakai orang —
   judul, kolom pencarian, dua tombol — dengan statistik sebagai strip tipis
   tanpa bingkai. Yang dibuang: eyebrow, paragraf pengantar, chip benefit, dan
   bingkai kaca kartu; semuanya masih ada di orientasi tegak. */
@media (orientation: landscape) and (max-height: 560px) {
    .wc-hero2 {
        flex-direction: column;
        align-items: stretch;
    }
    .wc-hero2__inner {
        grid-template-columns: 1fr;
        justify-items: center;
        max-width: 40rem;
        gap: 0.85rem;
        /* 5.5rem: tepat di bawah navbar ramping (lihat blok landscape di evo-theme.css). */
        padding: 5.5rem 0 0.75rem;
    }
    .wc-hero2__content {
        max-width: 100%;
        text-align: center;
    }
    .wc-hero2__sub,
    .wc-hero2__chips,
    .wc-hero2__ribbon,
    .wc-hero2__card-shine {
        display: none;
    }
    .wc-hero2__title {
        margin: 0;
        font-size: clamp(1.4rem, 3.2vw, 1.9rem);
        line-height: 1.15;
    }
    .wc-hero2__search {
        margin: 0.85rem auto 0;
        max-width: 30rem;
        width: 100%;
    }
    .wc-hero2__actions {
        margin-top: 0.7rem;
        justify-content: center;
    }
    .wc-hero2__actions .wc-hero2__btn {
        flex: 0 1 auto;
        padding: 11px 18px;
        font-size: 13px;
    }
    /* Kartu kaca → strip statistik telanjang: bingkai, blur, dan bayangannya
       menuntut tinggi yang tidak ada, sementara angkanya tetap terbaca. */
    .wc-hero2__card {
        width: auto;
        max-width: none;
        padding: 0;
        border: none;
        background: none;
        box-shadow: none;
        backdrop-filter: none;
        -webkit-backdrop-filter: none;
        justify-self: center;
    }
    .wc-hero2__stat {
        align-items: center;
        text-align: center;
        padding: 0 0.75rem;
    }
    /* Kartu kehilangan padding-nya di sini (tampil polos di atas foto), jadi
       kolom tepi ikut dipadatkan supaya tidak menempel ke tepi layar. */
    .wc-hero2__stat:first-child,
    .wc-hero2__stat:last-child {
        padding: 0 0.75rem;
    }
    .wc-hero2__stat strong {
        font-size: 1.2rem;
    }
    .wc-hero2__stat small {
        font-size: 10px;
    }
    /* Pagination menempel ke dasar hero, tidak melayang menimpa konten. */
    .wc-hero2__pagination {
        position: relative;
        z-index: 3;
        bottom: auto;
        margin-top: auto;
        padding-bottom: 0.9rem;
    }
    .wc-hero2__bullet {
        flex: 1 1 0;
        min-width: 0;
        padding: 0.25rem 0 0.4rem;
    }
    .wc-hero2__pagination {
        overflow-x: visible;
    }
}
</style>
