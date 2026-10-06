<!-- WEB CAREER — Section: Fungsi Perusahaan (Ultra 60FPS CSS Transform Carousel dengan Auto-Play) -->
<template>
    <section v-if="tim.length" id="tim" class="wc-section wc-tim">
        <div class="wc-sec-head wc-reveal">
            <span class="wc-eyebrow"><span class="wc-dot"></span> Fungsi Perusahaan</span>
            <h2>Tim yang <span class="wc-grad">menjalankan Evo.</span></h2>
            <p>Eksplorasi setiap fungsi dan departemen di EVO Group — temukan tim yang paling sesuai dengan passion Anda.</p>
        </div>

        <!-- Filter Category Tabs -->
        <div class="wc-tim__filter-tabs wc-reveal">
            <button
                v-for="cat in filterCategories"
                :key="cat.id"
                type="button"
                class="wc-tim__filter-btn"
                :class="{ 'is-active': activeFilter === cat.id }"
                @click="setFilter(cat.id, $event)"
            >
                <i class="bi" :class="cat.icon"></i>
                <span class="wc-tim__filter-lbl">{{ cat.label }}</span>
                <span class="wc-tim__filter-lbl--sm">{{ cat.short }}</span>
                <span v-if="cat.count !== null" class="wc-tim__filter-count">{{ cat.count }}</span>
            </button>
        </div>

        <!-- Viewport Slider dengan Smart Pause saat Mouse Hover -->
        <div
            ref="viewportRef"
            class="wc-tim__viewport wc-reveal"
            @mouseenter="pauseAutoPlay"
            @mouseleave="resumeAutoPlay"
            @pointerdown="onPointerDown"
        >
            <div
                ref="trackRef"
                class="wc-tim__track"
                :class="{ 'is-dragging': isDragging }"
                :style="trackStyle"
            >
                <article
                    v-for="(t, i) in filteredTim"
                    :key="t.nama"
                    class="wc-tim__card"
                    :class="{ 'is-active-card': i === activeIndex, 'is-nofoto': !t.img }"
                    :style="{ '--i': i }"
                    @mousemove="onCardMouseMove($event, i)"
                    @mouseleave="onCardMouseLeave(i)"
                    @click="onCardClick(t)"
                >
                    <!-- 3D Inner Wrapper -->
                    <div class="wc-tim__card-inner" :style="cardTiltStyles[i] || {}">

                        <!-- ══ VARIAN A — ADA FOTO SAMPUL ═════════════════════
                             Bentuk aslinya: foto memenuhi kartu, nama di kaki,
                             rincian muncul saat kursor menyentuhnya. -->
                        <template v-if="t.img">
                            <div
                                class="wc-tim__photo"
                                :style="{
                                    backgroundPosition: t.pos || '50% center',
                                    backgroundImage: `url('${t.img}')`,
                                }"
                            ></div>

                            <div class="wc-tim__scrim"></div>
                            <div class="wc-tim__sheen" :style="cardSheenStyles[i] || {}"></div>

                            <span class="wc-tim__lowongan" :class="{ 'is-empty': !t.lowongan, 'has-jobs': t.lowongan > 0 }">
                                <span v-if="t.lowongan > 0" class="wc-tim__pulse-dot"></span>
                                <i class="bi" :class="t.lowongan ? 'bi-briefcase-fill' : 'bi-briefcase'"></i>
                                {{ t.lowongan ? `${t.lowongan} lowongan` : 'Belum ada lowongan' }}
                            </span>

                            <div class="wc-tim__body">
                                <h3>{{ t.nama }}</h3>
                                <span class="wc-tim__subtag"><i class="bi bi-layers-fill"></i> EVO Department</span>
                            </div>

                            <div class="wc-tim__overlay">
                                <h3 class="wc-tim__overlay-title">{{ t.nama }}</h3>
                                <p class="wc-tim__overlay-desc">{{ t.deskripsi }}</p>

                                <div v-if="posisiTampil(t).length" class="wc-tim__posisi">
                                    <span class="wc-tim__posisi-lbl">
                                        <i class="bi bi-diagram-3"></i> {{ t.lowongan }} posisi
                                    </span>
                                    <span v-for="pos in posisiTampil(t)" :key="pos" class="wc-tim__posisi-chip">{{ pos }}</span>
                                    <span v-if="sisaPosisi(t) > 0" class="wc-tim__posisi-chip is-more">+{{ sisaPosisi(t) }}</span>
                                </div>

                                <button class="wc-tim__btn" type="button" @click.stop="onCardClick(t)">
                                    Lihat detail tim <i class="bi bi-arrow-right"></i>
                                </button>
                            </div>
                        </template>

                        <!-- ══ VARIAN B — TANPA FOTO SAMPUL ════════════════════
                             Kartu PUTIH, seluruh isinya terbaca langsung tanpa
                             perlu disentuh kursor.

                             Bukan versi gelap yang ditambal: kartu berfoto
                             menumpuk teks putih DI ATAS foto, dan begitu fotonya
                             tidak ada yang tersisa cuma kotak gelap kosong. Yang
                             benar adalah bentuk yang berbeda sejak awal — sepola
                             kartu lowongan, supaya keduanya terbaca satu keluarga.

                             Foto cadangan sengaja TIDAK dipakai: satu foto grup
                             yang sama untuk sembilan divisi membuat semuanya
                             tampak identik, dan memakai foto milik satu tim untuk
                             mewakili tim lain adalah keterangan yang keliru. -->
                        <div v-else class="wc-tim__polos">
                            <div class="wc-tim__polos-top">
                                <span class="wc-tim__polos-ikon" aria-hidden="true">
                                    <i class="bi bi-buildings"></i>
                                </span>
                                <span class="wc-tim__polos-status" :class="{ 'is-tutup': !t.lowongan }">
                                    <span class="wc-tim__polos-dot"></span>
                                    {{ t.lowongan ? 'Pendaftaran Dibuka' : 'Belum ada lowongan' }}
                                </span>
                            </div>

                            <h3 class="wc-tim__polos-nama">{{ t.nama }}</h3>
                            <p v-if="t.deskripsi" class="wc-tim__polos-desc">{{ t.deskripsi }}</p>

                            <div v-if="posisiTampil(t).length" class="wc-tim__polos-posisi">
                                <span class="wc-tim__polos-lbl">
                                    <i class="bi bi-bar-chart-fill"></i> {{ t.lowongan }} posisi
                                </span>
                                <span v-for="pos in posisiTampil(t)" :key="pos" class="wc-tim__polos-chip">{{ pos }}</span>
                                <span v-if="sisaPosisi(t) > 0" class="wc-tim__polos-chip is-more">+{{ sisaPosisi(t) }}</span>
                            </div>

                            <!-- Hanya fakta yang BENAR-BENAR ada datanya. "Jumlah
                                 anggota tim" tidak dimiliki basis data mana pun di
                                 sini, jadi ia tidak dikarang — barisnya cukup
                                 berisi lokasi bila lokasinya terdata. -->
                            <div v-if="t.lokasi" class="wc-tim__polos-meta">
                                <span><i class="bi bi-geo-alt"></i> {{ t.lokasi }}</span>
                            </div>

                            <div class="wc-tim__polos-kaki">
                                <span v-if="t.tanggalTutup" class="wc-tim__polos-tgl">
                                    <i class="bi bi-calendar-event"></i> Ditutup {{ tglSingkat(t.tanggalTutup) }}
                                </span>
                                <span v-else class="wc-tim__polos-tgl"></span>

                                <button class="wc-tim__polos-cta" type="button" @click.stop="onCardClick(t)">
                                    {{ t.lowongan ? `Lihat ${t.lowongan} posisi` : 'Lihat detail' }}
                                    <i class="bi bi-arrow-right"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </article>
            </div>
        </div>

        <!-- Controls, Auto-Play Toggle, Counter & Progress Bar -->
        <div class="wc-tim__nav wc-reveal">
            <button
                class="wc-tim__nav-btn"
                type="button"
                aria-label="Sebelumnya"
                :disabled="filteredTim.length <= 1"
                :onClick="filteredTim.length <= 1 ? null : prevSlide"
            >
                <i class="bi bi-arrow-left"></i>
            </button>

            <!-- Auto-Play Toggle Button -->
            <button
                class="wc-tim__play-btn"
                type="button"
                :class="{ 'is-paused': !isAutoPlaying }"
                :aria-label="isAutoPlaying ? 'Pause Auto Play' : 'Start Auto Play'"
                @click="toggleAutoPlay"
            >
                <i class="bi" :class="isAutoPlaying ? 'bi-pause-fill' : 'bi-play-fill'"></i>
            </button>

            <div class="wc-tim__bar-container">
                <div class="wc-tim__bar" role="presentation">
                    <span class="wc-tim__bar-fill" :style="{ transform: `scaleX(${progressRatio})` }"></span>
                </div>
                <span class="wc-tim__counter">
                    <strong>{{ String(activeIndex + 1).padStart(2, '0') }}</strong> / {{ String(filteredTim.length).padStart(2, '0') }}
                </span>
            </div>

            <button
                class="wc-tim__nav-btn"
                type="button"
                aria-label="Berikutnya"
                :disabled="filteredTim.length <= 1"
                :onClick="filteredTim.length <= 1 ? null : nextSlide"
            >
                <i class="bi bi-arrow-right"></i>
            </button>
        </div>

        <div class="wc-tim__more wc-reveal">
            <!-- Diarahkan ke daftar lowongan (sudah dikelompokkan per tim di sana),
                 bukan ke /karir/tim — pengunjung yang menekan tombol ini sedang
                 mencari posisi, bukan profil tim. -->
            <Link href="/karir/lowongan" class="wc-tim__more-btn">
                <i class="bi bi-grid-3x3-gap-fill"></i> Lihat Semua {{ totalLowongan }} Lowongan per Tim
                <i class="bi bi-arrow-right"></i>
            </Link>
        </div>
    </section>
</template>

<script setup>
import { Link, router } from '@inertiajs/vue3';
import { computed, nextTick, onMounted, onUnmounted, reactive, ref } from 'vue';

const props = defineProps({
    tim: { type: Array, default: () => [] },
});

const tim = computed(() => props.tim || []);
const totalLowongan = computed(() => tim.value.reduce((n, t) => n + (t.lowongan || 0), 0));

// Active Filter Category
const activeFilter = ref('ALL');

/**
 * Berapa chip nama posisi yang muat di kartu.
 *
 * BEDA per varian, dan itu bukan kerewelan — lebarnya memang beda:
 *
 *   berfoto   : 3 chip. Rinciannya tampil di hamparan selebar kartu penuh.
 *   tanpa foto: 1 chip. Kartunya lebih sempit (maks 19.5rem) dan nama jabatan
 *               di sini panjang-panjang ("SPV IT INFRASTRUCTURE" saja sudah
 *               21 huruf). Dua chip memaksa barisnya membungkus, dan baris
 *               kedua itulah yang mendorong isi kartu jadi tidak rata.
 *
 * Sisanya tidak hilang: ia dihitung jadi lencana "+N" di sebelahnya.
 */
const MAKS_CHIP_FOTO = 3;
const MAKS_CHIP_POLOS = 1;

function maksChip(t) {
    return t.img ? MAKS_CHIP_FOTO : MAKS_CHIP_POLOS;
}

/** Nama posisi yang benar-benar digambar sebagai chip. */
function posisiTampil(t) {
    return (t.posisi || []).slice(0, maksChip(t));
}

/**
 * Sisa posisi yang tidak muat — isi lencana "+N".
 *
 * Dihitung dari PANJANG DAFTAR posisi, bukan dari `t.lowongan`. Keduanya bisa
 * berbeda: satu lowongan bisa membuka posisi dengan nama yang sama persis, dan
 * daftar ini sudah di-unique di server. Memakai `t.lowongan` akan menghasilkan
 * "+1" yang tidak menunjuk posisi mana pun.
 */
function sisaPosisi(t) {
    return Math.max(0, (t.posisi || []).length - maksChip(t));
}

/**
 * '2026-10-31' — '31 Okt 2026'.
 *
 * Tanggal mentah ISO tidak pernah pantas tampil di halaman publik: pengunjung
 * membacanya sebagai kode, bukan sebagai tenggat yang mendesak.
 */
function tglSingkat(v) {
    if (!v) return '';
    const d = new Date(String(v).replace(' ', 'T'));

    return Number.isNaN(d.getTime())
        ? ''
        : d.toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });
}

/** Ada divisi yang sedang TIDAK membuka lowongan di daftar ini? */
const adaYangKosong = computed(() => tim.value.some(t => !t.lowongan));

const filterCategories = computed(() => {
    const totalWithJobs = tim.value.filter(t => t.lowongan > 0).length;

    // `short` dipakai di layar sempit — label panjang membuat strip filter
    // harus digeser jauh sebelum kategori terakhir terlihat.
    const kategori = [
        { id: 'ALL', label: 'Semua Tim', short: 'Semua', icon: 'bi-grid-fill', count: tim.value.length },
    ];

    // Tab "Ada Lowongan" hanya berguna bila memang ADA yang tidak punya.
    //
    // Di beranda daftarnya sudah disaring server (hanya divisi yang membuka),
    // jadi tab ini akan memulangkan daftar yang sama persis dengan "Semua Tim"
    // — penyaring yang tidak menyaring apa pun mengajari orang bahwa tombol di
    // sini tidak melakukan apa-apa. Di halaman /karir/tim yang memuat seluruh
    // struktur, tab ini tetap muncul karena di sana ia benar-benar memilah.
    if (adaYangKosong.value) {
        kategori.push({ id: 'OPEN', label: 'Ada Lowongan', short: 'Ada Lowongan', icon: 'bi-fire', count: totalWithJobs });
    }

    kategori.push(
        { id: 'TECH', label: 'Teknologi & Digital', short: 'Teknologi', icon: 'bi-laptop', count: null },
        { id: 'OPS', label: 'Manufaktur & Operasional', short: 'Manufaktur', icon: 'bi-gear-wide-connected', count: null },
        { id: 'BIZ', label: 'Bisnis & Support', short: 'Bisnis', icon: 'bi-graph-up-arrow', count: null },
    );

    return kategori;
});

const filteredTim = computed(() => {
    if (activeFilter.value === 'OPEN') {
        return tim.value.filter(t => t.lowongan > 0);
    }
    if (activeFilter.value === 'TECH') {
        return tim.value.filter(t => {
            const s = (t.slug || t.nama || '').toLowerCase();
            return s.includes('it') || s.includes('tech') || s.includes('digital') || s.includes('system');
        });
    }
    if (activeFilter.value === 'OPS') {
        return tim.value.filter(t => {
            const s = (t.slug || t.nama || '').toLowerCase();
            return s.includes('prod') || s.includes('pabrik') || s.includes('qc') || s.includes('qa') || s.includes('logis') || s.includes('plant') || s.includes('teknik');
        });
    }
    if (activeFilter.value === 'BIZ') {
        return tim.value.filter(t => {
            const s = (t.slug || t.nama || '').toLowerCase();
            return s.includes('hr') || s.includes('hc') || s.includes('mark') || s.includes('finance') || s.includes('sales') || s.includes('adm');
        });
    }
    return tim.value;
});

function setFilter(id, ev) {
    activeFilter.value = id;
    activeIndex.value = 0;
    resetAutoPlayTimer();
    // Di mobile strip filter bisa digeser — tarik tab yang dipilih ke tengah
    // supaya tidak ada tab aktif yang terpotong di tepi layar.
    // `block: 'nearest'` menjaga posisi gulir halaman tetap di tempat.
    ev?.currentTarget?.scrollIntoView({ inline: 'center', block: 'nearest', behavior: 'smooth' });
}

// Slider Transform State
const activeIndex = ref(0);
const dragOffset = ref(0);
const isDragging = ref(false);
const viewportRef = ref(null);
const trackRef = ref(null);

/**
 * Penanda "lebar kartu mungkin berubah".
 *
 * Dinaikkan saat jendela diubah ukurannya. trackStyle membacanya semata-mata
 * agar ikut dihitung ulang — lebar kartu memakai clamp(vw), jadi mengubah
 * lebar jendela mengubah jarak geser, dan posisi lama akan meleset.
 */
const lebarUkur = ref(0);

const stepWidthPx = computed(() => {
    if (!trackRef.value) return 340;
    const cardEl = trackRef.value.querySelector('.wc-tim__card');
    if (!cardEl) return 340;
    const gap = parseFloat(getComputedStyle(trackRef.value).gap || '20') || 20;
    return cardEl.offsetWidth + gap;
});

const maxIndex = computed(() => Math.max(0, filteredTim.value.length - 1));

const progressRatio = computed(() => {
    if (filteredTim.value.length <= 1) return 1;
    return Math.min(1, Math.max(0.1, (activeIndex.value + 1) / filteredTim.value.length));
});

/**
 * Jarak geser sampai kartu ke-n — DIJUMLAHKAN, bukan dikalikan.
 *
 * Sejak kartu tanpa foto punya lebar sendiri (lebih sempit daripada kartu
 * berfoto), lebar kartu tidak lagi seragam. `activeIndex * lebarSatuKartu`
 * memakai lebar kartu PERTAMA untuk semua, sehingga begitu ada satu kartu yang
 * lebih sempit, geserannya meleset — makin jauh digeser makin melenceng, dan
 * kartunya berhenti di posisi yang tumpang tindih dengan tetangganya.
 *
 * Diukur dari elemen yang sebenarnya ada di layar; `stepWidthPx` tetap
 * dipertahankan sebagai cadangan saat DOM-nya belum terpasang.
 */
function geserSampai(n) {
    const track = trackRef.value;
    if (!track) return n * stepWidthPx.value;

    const kartu = track.querySelectorAll('.wc-tim__card');
    if (!kartu.length) return n * stepWidthPx.value;

    const gap = parseFloat(getComputedStyle(track).gap || '20') || 20;
    let px = 0;
    for (let i = 0; i < n && i < kartu.length; i += 1) {
        px += kartu[i].offsetWidth + gap;
    }

    return px;
}

const trackStyle = computed(() => {
    // `filteredTim` & `lebarUkur` ikut dibaca supaya computed ini dihitung ulang
    // saat daftarnya berganti atau jendela diubah ukurannya — tanpa itu, posisi
    // geser memakai lebar kartu yang sudah tidak berlaku lagi.
    void filteredTim.value.length;
    void lebarUkur.value;

    const px = geserSampai(activeIndex.value) - dragOffset.value;
    // Saat OS meminta reduced-motion, slide tetap berpindah tapi tanpa animasi
    // panjang (bukan mematikan autoplay — lihat startAutoPlay).
    const transisi = reduceMotion() ? 'transform 0.15s ease' : 'transform 0.65s cubic-bezier(0.16, 1, 0.3, 1)';
    return {
        transform: `translate3d(${-px}px, 0, 0)`,
        transition: isDragging.value ? 'none' : transisi,
    };
});

function nextSlide() {
    if (filteredTim.value.length <= 1) return;
    if (activeIndex.value >= maxIndex.value) {
        activeIndex.value = 0;
    } else {
        activeIndex.value++;
    }
    resetAutoPlayTimer();
}

function prevSlide() {
    if (filteredTim.value.length <= 1) return;
    if (activeIndex.value <= 0) {
        activeIndex.value = maxIndex.value;
    } else {
        activeIndex.value--;
    }
    resetAutoPlayTimer();
}

// Auto-Play Engine
const isAutoPlaying = ref(true);
let autoPlayTimer = null;
const AUTO_PLAY_DELAY = 3500; // 3.5 detik

function startAutoPlay() {
    stopAutoPlay();
    // reduced-motion TIDAK mematikan autoplay (tombol pause tetap tersedia);
    // preferensi itu dihormati lewat transisi singkat di trackStyle.
    if (!isAutoPlaying.value) return;
    autoPlayTimer = setInterval(() => {
        nextSlide();
    }, AUTO_PLAY_DELAY);
}

function stopAutoPlay() {
    if (autoPlayTimer) {
        clearInterval(autoPlayTimer);
        autoPlayTimer = null;
    }
}

function resetAutoPlayTimer() {
    if (isAutoPlaying.value) {
        startAutoPlay();
    }
}

function toggleAutoPlay() {
    isAutoPlaying.value = !isAutoPlaying.value;
    if (isAutoPlaying.value) {
        startAutoPlay();
    } else {
        stopAutoPlay();
    }
}

function pauseAutoPlay() {
    stopAutoPlay();
}

function resumeAutoPlay() {
    if (isAutoPlaying.value) {
        startAutoPlay();
    }
}

function reduceMotion() {
    return typeof window !== 'undefined' && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
}

// 3D Tilt Card Reactive State
const cardTiltStyles = reactive({});
const cardSheenStyles = reactive({});

function onCardMouseMove(e, i) {
    const cardEl = e.currentTarget;
    if (!cardEl) return;
    const rect = cardEl.getBoundingClientRect();
    const x = e.clientX - rect.left;
    const y = e.clientY - rect.top;
    const centerX = rect.width / 2;
    const centerY = rect.height / 2;

    const rotateX = ((centerY - y) / centerY) * 10;
    const rotateY = ((x - centerX) / centerX) * 10;

    cardTiltStyles[i] = {
        transform: `perspective(1000px) rotateX(${rotateX.toFixed(2)}deg) rotateY(${rotateY.toFixed(2)}deg) scale3d(1.02, 1.02, 1.02)`,
    };

    const sheenX = ((x / rect.width) * 100).toFixed(1);
    const sheenY = ((y / rect.height) * 100).toFixed(1);
    cardSheenStyles[i] = {
        background: `radial-gradient(circle at ${sheenX}% ${sheenY}%, rgba(255, 255, 255, 0.22) 0%, transparent 60%)`,
        opacity: 1,
    };
}

function onCardMouseLeave(i) {
    cardTiltStyles[i] = {
        transform: 'perspective(1000px) rotateX(0deg) rotateY(0deg) scale3d(1, 1, 1)',
    };
    cardSheenStyles[i] = {
        opacity: 0,
    };
}

function bukaDetail(t) {
    router.visit(`/karir/tim/${t.slug}`);
}

// Mouse & Touch Drag Gesture Handling
let dragStartPos = 0;
let dragMoved = 0;
let suppressClick = false;
let clickGuard = null;

function onPointerDown(e) {
    if (e.pointerType === 'mouse' && e.button !== 0) return;
    pauseAutoPlay();

    dragStartPos = e.clientX;
    dragMoved = 0;
    isDragging.value = true;

    window.addEventListener('pointermove', onPointerMove);
    window.addEventListener('pointerup', onPointerUp);
    window.addEventListener('pointercancel', onPointerUp);
}

function onPointerMove(e) {
    if (!isDragging.value) return;
    const delta = e.clientX - dragStartPos;
    dragOffset.value = delta;
    dragMoved = Math.max(dragMoved, Math.abs(delta));
}

function onPointerUp() {
    window.removeEventListener('pointermove', onPointerMove);
    window.removeEventListener('pointerup', onPointerUp);
    window.removeEventListener('pointercancel', onPointerUp);

    if (!isDragging.value) return;
    isDragging.value = false;

    const threshold = 60; // 60px swipe threshold
    if (dragOffset.value < -threshold) {
        nextSlide();
    } else if (dragOffset.value > threshold) {
        prevSlide();
    }

    dragOffset.value = 0;

    if (dragMoved > 8) {
        suppressClick = true;
        if (clickGuard) clearTimeout(clickGuard);
        clickGuard = setTimeout(() => (suppressClick = false), 300);
    }

    resumeAutoPlay();
}

function onCardClick(t) {
    if (suppressClick) {
        suppressClick = false;
        return;
    }
    bukaDetail(t);
}

let resizeTimer = null;

function onResize() {
    // Ditunda sedikit: peristiwa resize datang berpuluh kali per detik saat
    // jendela diseret, dan menghitung ulang tiap kali membuat geserannya patah.
    clearTimeout(resizeTimer);
    resizeTimer = setTimeout(() => { lebarUkur.value += 1; }, 120);
}

onMounted(() => {
    nextTick(() => {
        startAutoPlay();
        // Sekali di awal: lebar kartu baru terukur setelah DOM terpasang.
        lebarUkur.value += 1;
    });
    window.addEventListener('resize', onResize);
});
onUnmounted(() => {
    stopAutoPlay();
    window.removeEventListener('pointermove', onPointerMove);
    window.removeEventListener('pointerup', onPointerUp);
    window.removeEventListener('pointercancel', onPointerUp);
    window.removeEventListener('resize', onResize);
    clearTimeout(resizeTimer);
    if (clickGuard) clearTimeout(clickGuard);
});
</script>

<style scoped>
/* ── Filter Tabs ─────────────────────────────────────────── */
.wc-tim__filter-tabs {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: center;
    gap: 0.65rem;
    margin-top: 1.75rem;
}
.wc-tim__filter-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    padding: 0.55rem 1.15rem;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.82);
    border: 1px solid rgba(226, 232, 240, 0.9);
    color: #475569;
    font-size: 0.8rem;
    font-weight: 700;
    cursor: pointer;
    box-shadow: 0 4px 14px rgba(15, 23, 42, 0.03);
    transition: all 0.22s cubic-bezier(0.16, 1, 0.3, 1);
}
.wc-tim__filter-btn:hover {
    background: #ffffff;
    border-color: rgba(139, 92, 246, 0.4);
    color: #6366f1;
    transform: translateY(-1px);
}
.wc-tim__filter-btn.is-active {
    background: linear-gradient(135deg, #8b5cf6, #6366f1);
    border-color: transparent;
    color: #ffffff;
    box-shadow: 0 10px 24px rgba(99, 102, 241, 0.32);
}
.wc-tim__filter-count {
    display: inline-grid;
    place-items: center;
    padding: 2px 7px;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.25);
    font-size: 0.7rem;
    font-weight: 800;
}
.wc-tim__filter-btn:not(.is-active) .wc-tim__filter-count {
    background: #f1f5f9;
    color: #6366f1;
}
.wc-tim__filter-lbl--sm {
    display: none;
}

/* ── Viewport Slider ─────────────────────────────────────── */
.wc-tim__viewport {
    position: relative;
    margin-top: clamp(1.5rem, 3.5vw, 2.25rem);
    overflow: hidden;
    padding: 1rem 0.25rem 1.75rem;
    cursor: grab;
    touch-action: pan-y;
}
.wc-tim__viewport:active {
    cursor: grabbing;
}

/* ── Track ───────────────────────────────────────────────── */
.wc-tim__track {
    display: flex;
    gap: 1.25rem;
    will-change: transform;
    /* Kartu diratakan ke ATAS, bukan diregangkan.
       Kartu berfoto tetap tinggi (potret), kartu tanpa foto lebih pendek
       — keduanya berdampingan dengan tepi atas sejajar, dan tak satu pun
       dipaksa mengisi tinggi yang bukan miliknya. */
    align-items: flex-start;
}
.wc-tim__track.is-dragging {
    cursor: grabbing;
    user-select: none;
}

/* ── Kartu 3D ────────────────────────────────────────────── */
.wc-tim__card {
    position: relative;
    flex: 0 0 clamp(17.5rem, 30vw, 22.5rem);
    /* Batas svh: saat layar dirotasi (tinggi ~400px) kartu 25rem lebih tinggi
       dari viewport dan nama tim/tombol nav ikut terdorong keluar layar. */
    height: min(25rem, 68svh);
    min-height: 15rem;
    perspective: 1000px;
    cursor: pointer;
    user-select: none;
    transition: transform 0.4s ease, opacity 0.4s ease;
}

/* KARTU TANPA FOTO: lebih kecil, dan tingginya MENGIKUTI ISINYA.
   Tinggi tetap 25rem hanya masuk akal untuk kartu berfoto — fotonya yang
   mengisi ruang itu. Tanpa foto, tinggi yang sama menyisakan lubang kosong
   di tengah kartu, persis yang terlihat sebelum perbaikan ini. */
.wc-tim__card.is-nofoto {
    flex: 0 0 clamp(16rem, 24vw, 19.5rem);
    height: auto;
    min-height: 0;
}
.wc-tim__card-inner {
    position: relative;
    width: 100%;
    height: 100%;
    border-radius: 1.5rem;
    overflow: hidden;
    background: #1e1b4b;
    box-shadow: 0 16px 40px rgba(15, 23, 42, 0.12);
    transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1), box-shadow 0.3s ease, border-color 0.3s ease;
    will-change: transform;
    border: 1px solid rgba(255, 255, 255, 0.12);
}

/* Active Card Highlight */
.wc-tim__card.is-active-card .wc-tim__card-inner {
    border-color: rgba(139, 92, 246, 0.6);
    box-shadow: 0 24px 50px rgba(99, 102, 241, 0.28), 0 0 0 2px rgba(139, 92, 246, 0.4);
    transform: scale(1.02);
}
.wc-tim__card:hover .wc-tim__card-inner {
    box-shadow: 0 28px 60px rgba(99, 102, 241, 0.35), 0 0 0 2px rgba(139, 92, 246, 0.6);
}

.wc-tim__photo {
    position: absolute;
    inset: 0;
    background-size: cover;
    transition: transform 0.6s cubic-bezier(0.16, 1, 0.3, 1);
}
.wc-tim__card:hover .wc-tim__photo,
.wc-tim__card.is-active-card .wc-tim__photo {
    transform: scale(1.06);
}
.wc-tim__scrim {
    position: absolute;
    inset: 0;
    background: linear-gradient(180deg, rgba(30, 27, 75, 0.1) 0%, rgba(30, 27, 75, 0.4) 65%, rgba(15, 23, 42, 0.88) 100%);
    transition: background 0.35s ease;
}
.wc-tim__sheen {
    position: absolute;
    inset: 0;
    pointer-events: none;
    transition: opacity 0.3s ease;
    opacity: 0;
    z-index: 2;
}

/* ── Lowongan Badge ──────────────────────────────────────── */
.wc-tim__lowongan {
    position: absolute;
    top: 1.15rem;
    left: 1.15rem;
    z-index: 4;
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    padding: 0.4rem 0.85rem;
    border-radius: 999px;
    background: rgba(15, 23, 42, 0.75);
    border: 1px solid rgba(255, 255, 255, 0.25);
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
    color: #ffffff;
    font-size: 0.72rem;
    font-weight: 800;
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.3);
}
.wc-tim__lowongan.has-jobs {
    background: rgba(99, 102, 241, 0.92);
    border-color: rgba(255, 255, 255, 0.4);
}
.wc-tim__pulse-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: #34d399;
    box-shadow: 0 0 0 3px rgba(52, 211, 153, 0.35);
    animation: wcPulseGlow 1.8s ease-in-out infinite;
}

/* ── Body State Normal ───────────────────────────────────── */
.wc-tim__body {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    z-index: 3;
    padding: 1.35rem 1.45rem 1.5rem;
    background: linear-gradient(180deg, transparent 0%, rgba(15, 23, 42, 0.85) 100%);
    backdrop-filter: blur(4px);
    -webkit-backdrop-filter: blur(4px);
    transition: opacity 0.3s ease, transform 0.35s ease;
}
.wc-tim__body h3 {
    margin: 0;
    font-size: 1.15rem;
    font-weight: 800;
    color: #ffffff;
    line-height: 1.25;
    letter-spacing: -0.01em;
}
.wc-tim__subtag {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    margin-top: 4px;
    font-size: 0.72rem;
    font-weight: 600;
    color: #c4b5fd;
}
.wc-tim__card:hover .wc-tim__body {
    opacity: 0;
    transform: translateY(14px);
}

/* ── Hover Overlay ───────────────────────────────────────── */
.wc-tim__overlay {
    position: absolute;
    inset: 0;
    z-index: 4;
    display: flex;
    flex-direction: column;
    justify-content: flex-end;
    padding: 1.75rem 1.5rem;
    background: linear-gradient(180deg, rgba(24, 22, 58, 0.65) 0%, rgba(15, 23, 42, 0.94) 100%);
    backdrop-filter: blur(6px);
    -webkit-backdrop-filter: blur(6px);
    opacity: 0;
    transition: opacity 0.35s ease;
}
.wc-tim__card:hover .wc-tim__overlay {
    opacity: 1;
}
.wc-tim__overlay-title {
    margin: 0;
    font-size: 1.2rem;
    font-weight: 800;
    color: #ffffff;
}
.wc-tim__overlay-desc {
    margin: 8px 0 0;
    color: rgba(241, 245, 249, 0.92);
    font-size: 0.86rem;
    font-weight: 500;
    line-height: 1.55;
    display: -webkit-box;
    -webkit-line-clamp: 3;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
.wc-tim__skills {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin-top: 12px;
}
.wc-tim__skill-pill {
    padding: 3px 8px;
    border-radius: 6px;
    background: rgba(255, 255, 255, 0.14);
    border: 1px solid rgba(255, 255, 255, 0.2);
    color: #e2e8f0;
    font-size: 0.68rem;
    font-weight: 700;
}
/* ══ KARTU TANPA FOTO SAMPUL ═════════════════════════════
   Kartu PUTIH yang berdiri sendiri — bukan kartu berfoto yang ditambal.

   Kartu berfoto menumpuk teks putih di atas gambar; begitu gambarnya tidak ada,
   yang tersisa cuma kotak gelap. Karena itu varian ini punya struktur dan gaya
   sendiri, sepola kartu lowongan supaya keduanya terbaca satu keluarga. */
.wc-tim__card.is-nofoto .wc-tim__card-inner {
    /* height:auto — menimpa `height:100%` milik kartu berfoto. Tanpa ini
       kartunya runtuh setinggi nol, sebab induknya kini tidak lagi bertinggi
       tetap. */
    height: auto;
    background: #ffffff;
    border: 1px solid #ecebf8;
    box-shadow: 0 10px 30px rgba(15, 23, 42, 0.06);
}

.wc-tim__card.is-nofoto:hover .wc-tim__card-inner {
    border-color: #c7d2fe;
    box-shadow: 0 18px 44px rgba(99, 102, 241, 0.15);
}

.wc-tim__polos {
    display: flex;
    flex-direction: column;
    height: 100%;
    padding: 1.5rem 1.5rem 1.35rem;
}

.wc-tim__polos-top {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 0.75rem;
    margin-bottom: 1.15rem;
}

.wc-tim__polos-ikon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex: 0 0 auto;
    width: 46px;
    height: 46px;
    border-radius: 14px;
    background: linear-gradient(135deg, #eef2ff, #f5f3ff);
    border: 1px solid #e3e6fd;
    color: #6366f1;
    font-size: 1.2rem;
}

.wc-tim__polos-status {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.32rem 0.7rem;
    border-radius: 999px;
    background: #ecfdf5;
    color: #047857;
    font-size: 0.7rem;
    font-weight: 800;
    white-space: nowrap;
}

.wc-tim__polos-status.is-tutup {
    background: #f1f5f9;
    color: #64748b;
}

.wc-tim__polos-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: #10b981;
}

.wc-tim__polos-status.is-tutup .wc-tim__polos-dot {
    background: #94a3b8;
}

.wc-tim__polos-nama {
    margin: 0 0 0.55rem;
    font-size: 1.05rem;
    font-weight: 800;
    line-height: 1.3;
    letter-spacing: -0.01em;
    color: #14134a;
    text-transform: uppercase;
}

/* Deskripsi dipotong tiga baris. Divisi menulis panjangnya sesuka hati, dan
   satu kartu yang jauh lebih tinggi membuat seluruh baris carousel ikut
   melar. */
/* Deskripsi dipotong DUA baris, dengan "..." yang benar-benar terlihat.
   Divisi menulis panjangnya sesuka hati; tanpa potongan ini satu kartu bisa
   dua kali lebih tinggi daripada tetangganya. */
.wc-tim__polos-desc {
    margin: 0 0 1rem;
    font-size: 0.82rem;
    line-height: 1.6;
    color: #6b7280;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    text-overflow: ellipsis;
    /* Tinggi dikunci 2 baris supaya kartu dengan deskripsi pendek dan panjang
       tetap sejajar barisan chip di bawahnya. */
    min-height: calc(0.82rem * 1.6 * 2);
}

/* SATU BARIS, tidak pernah membungkus.
   Chip yang membungkus ke baris kedua menggeser lokasi & kaki kartu ke bawah,
   dan dua kartu berdampingan langsung jadi tidak sejajar. Yang tidak muat
   diwakili lencana "+N", bukan dipaksa turun. */
.wc-tim__polos-posisi {
    display: flex;
    flex-wrap: nowrap;
    align-items: center;
    gap: 6px;
    margin-bottom: 0.9rem;
    min-width: 0;
}

.wc-tim__polos-lbl {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    flex: 0 0 auto;
    font-size: 0.78rem;
    font-weight: 800;
    color: #6366f1;
    white-space: nowrap;
}

.wc-tim__polos-chip {
    padding: 3px 9px;
    border-radius: 7px;
    background: #f4f4fd;
    border: 1px solid #e6e6f8;
    color: #4b5563;
    font-size: 0.68rem;
    font-weight: 800;
    letter-spacing: 0.02em;
    white-space: nowrap;
    /* min-width:0 + overflow: chip boleh MENGKERUT dan namanya dipotong "...",
       bukan mendorong lencana "+N" keluar kartu. */
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* "+N" lebih redup: ia penanda ada sisa, bukan nama jabatan yang bisa dibaca. */
.wc-tim__polos-chip.is-more {
    /* flex-shrink:0 — lencana sisa TIDAK boleh ikut mengkerut. Justru dialah
       satu-satunya penanda bahwa masih ada posisi lain yang tidak terlihat. */
    flex: 0 0 auto;
    background: #eef2ff;
    border-color: #c7d2fe;
    color: #4338ca;
    font-variant-numeric: tabular-nums;
}

.wc-tim__polos-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 0.35rem 1.1rem;
    font-size: 0.78rem;
    color: #6b7280;
}

.wc-tim__polos-meta span {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    min-width: 0;
}

.wc-tim__polos-meta .bi {
    color: #a5b4fc;
}

/* margin-top:auto menahan kaki tetap di dasar kartu, berapa pun panjang
   deskripsinya — sehingga kartu berdampingan tetap sejajar garis bawahnya. */
.wc-tim__polos-kaki {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    margin-top: auto;
    padding-top: 0.9rem;
    border-top: 1px solid #f1f2f9;
}

.wc-tim__polos-tgl {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.74rem;
    color: #9ca3af;
    white-space: nowrap;
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
}

.wc-tim__polos-cta {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    flex: 0 0 auto;
    border: none;
    background: none;
    padding: 0;
    font-family: inherit;
    font-size: 0.8rem;
    font-weight: 800;
    color: #6366f1;
    cursor: pointer;
    transition: gap 0.2s ease, color 0.2s ease;
}

.wc-tim__polos-cta:hover {
    gap: 0.6rem;
    color: #4338ca;
}

/* ── POSISI YANG DIBUKA ──────────────────────────────────────────────────
   Sepola kartu Management Trainee (MtSection): cacah di depan, nama posisi
   sebagai chip, lalu "+N" untuk yang tidak muat. */
.wc-tim__posisi {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 6px;
    margin-top: 10px;
}

/* Label cacah dibedakan dari chip-nya: ia keterangan, bukan salah satu posisi.
   Warnanya mengikuti aksen kartu supaya angka itu yang lebih dulu terbaca. */
.wc-tim__posisi-lbl {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    font-size: 0.7rem;
    font-weight: 800;
    color: #c4b5fd;
    white-space: nowrap;
}

.wc-tim__posisi-lbl .bi {
    font-size: 0.75rem;
}

.wc-tim__posisi-chip {
    padding: 3px 8px;
    border-radius: 6px;
    background: rgba(139, 92, 246, 0.22);
    border: 1px solid rgba(167, 139, 250, 0.38);
    color: #ede9fe;
    font-size: 0.68rem;
    font-weight: 700;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 100%;
}

/* "+N" sengaja lebih redup: ia penanda ADA SISA, bukan nama posisi yang bisa
   dibaca. Menyamakan tampilannya membuat orang mengira "+2" sebuah jabatan. */
.wc-tim__posisi-chip.is-more {
    background: rgba(255, 255, 255, 0.12);
    border-color: rgba(255, 255, 255, 0.22);
    color: #cbd5e1;
    font-variant-numeric: tabular-nums;
}

.wc-tim__btn {
    display: inline-flex;
    align-items: center;
    align-self: flex-start;
    gap: 0.5rem;
    margin-top: 1.25rem;
    padding: 0.6rem 1.2rem;
    border-radius: 999px;
    border: none;
    background: linear-gradient(135deg, #8b5cf6, #6366f1);
    color: #ffffff;
    font-size: 0.8rem;
    font-weight: 800;
    cursor: pointer;
    box-shadow: 0 10px 24px rgba(99, 102, 241, 0.4);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}
.wc-tim__btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 14px 30px rgba(99, 102, 241, 0.5);
}

/* ── Nav, Progress Bar & Counter ─────────────────────────── */
.wc-tim__nav {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.85rem;
    margin-top: 0.5rem;
}
.wc-tim__play-btn {
    display: grid;
    place-items: center;
    width: 2.5rem;
    height: 2.5rem;
    border-radius: 50%;
    border: 1px solid rgba(99, 102, 241, 0.3);
    background: rgba(99, 102, 241, 0.1);
    color: #6366f1;
    font-size: 1.15rem;
    cursor: pointer;
    transition: all 0.2s ease;
}
.wc-tim__play-btn:hover {
    background: #6366f1;
    color: #ffffff;
    border-color: transparent;
    transform: scale(1.05);
}
.wc-tim__play-btn.is-paused {
    background: #f1f5f9;
    color: #94a3b8;
    border-color: #cbd5e1;
}

.wc-tim__bar-container {
    display: flex;
    align-items: center;
    gap: 1rem;
}
.wc-tim__bar {
    position: relative;
    width: min(14rem, 35vw);
    height: 4px;
    border-radius: 999px;
    background: rgba(203, 213, 225, 0.7);
    overflow: hidden;
}
.wc-tim__bar-fill {
    position: absolute;
    inset: 0;
    border-radius: inherit;
    background: linear-gradient(90deg, #8b5cf6, #6366f1);
    transform-origin: left;
    transition: transform 0.35s ease;
}
.wc-tim__counter {
    font-size: 0.8rem;
    color: #64748b;
    font-weight: 600;
}
.wc-tim__counter strong {
    color: #6366f1;
    font-weight: 800;
}
.wc-tim__nav-btn {
    display: grid;
    place-items: center;
    width: 2.75rem;
    height: 2.75rem;
    border-radius: 50%;
    border: 1px solid rgba(203, 213, 225, 0.9);
    background: #ffffff;
    color: #6366f1;
    font-size: 1.05rem;
    cursor: pointer;
    box-shadow: 0 4px 12px rgba(15, 23, 42, 0.04);
    transition: all 0.2s ease;
}
.wc-tim__nav-btn:hover:not(:disabled) {
    background: linear-gradient(135deg, #8b5cf6, #6366f1);
    border-color: transparent;
    color: #ffffff;
    box-shadow: 0 12px 26px rgba(99, 102, 241, 0.32);
    transform: translateY(-2px);
}
.wc-tim__nav-btn:disabled {
    opacity: 0.35;
    cursor: default;
}

/* ── More Button ─────────────────────────────────────────── */
.wc-tim__more {
    display: flex;
    justify-content: center;
    margin-top: 1.75rem;
}
.wc-tim__more-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.6rem;
    padding: 0.85rem 1.75rem;
    border-radius: 999px;
    background: linear-gradient(135deg, #8b5cf6, #6366f1);
    color: #ffffff;
    font-size: 0.85rem;
    font-weight: 800;
    text-decoration: none;
    box-shadow: 0 14px 30px rgba(99, 102, 241, 0.3);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}
.wc-tim__more-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 20px 40px rgba(99, 102, 241, 0.4);
}

@media (max-width: 640px) {
    .wc-tim__card {
        flex-basis: 16.5rem;
        height: 22.5rem;
    }
    /* ═══ Strip filter — satu baris yang digeser, bukan pil bertumpuk ═══
       `flex-wrap: wrap` bawaan membuat pil membungkus jadi 2-3 baris dan
       `overflow-x: auto` tidak pernah aktif (kontainer tak pernah meluap).
       Karena itu wrap WAJIB dimatikan di sini. */
    .wc-tim__filter-tabs {
        flex-wrap: nowrap;
        justify-content: flex-start;
        gap: 0.45rem;
        margin-top: 1.25rem;
        overflow-x: auto;
        overscroll-behavior-x: contain;
        scroll-snap-type: x proximity;
        scroll-padding-left: 1rem;
        -webkit-overflow-scrolling: touch;
        /* Tembus margin section (100vw - 2rem) agar strip menyentuh tepi layar. */
        margin-inline: -1rem;
        padding: 0.25rem 1rem 0.5rem;
        scrollbar-width: none;
    }
    .wc-tim__filter-tabs::-webkit-scrollbar {
        display: none;
    }
    .wc-tim__filter-btn {
        flex: 0 0 auto;
        scroll-snap-align: start;
        padding: 0.5rem 0.85rem;
        font-size: 0.75rem;
        gap: 0.35rem;
        /* Label tidak boleh patah di dalam pil. */
        white-space: nowrap;
    }
    /* Layar sentuh tidak punya hover, tapi state-nya sering menyangkut setelah
       disentuh — satu pil bisa terlihat terangkat permanen. */
    .wc-tim__filter-btn:hover {
        transform: none;
        background: rgba(255, 255, 255, 0.82);
        border-color: rgba(226, 232, 240, 0.9);
        color: #475569;
    }
    .wc-tim__filter-btn.is-active:hover {
        background: linear-gradient(135deg, #8b5cf6, #6366f1);
        border-color: transparent;
        color: #ffffff;
    }
    .wc-tim__filter-btn:active {
        transform: scale(0.96);
    }
    /* Label panjang ditukar versi pendek agar strip tidak perlu digeser jauh. */
    .wc-tim__filter-lbl {
        display: none;
    }
    .wc-tim__filter-lbl--sm {
        display: inline;
    }
    .wc-tim__filter-count {
        font-size: 0.65rem;
        padding: 1px 6px;
    }
}
</style>
