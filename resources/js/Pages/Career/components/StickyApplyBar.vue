<!-- WEB CAREER — Floating Sticky Action Bar.
     Muncul setelah pengguna menggulir cukup jauh, TAPI: bisa ditutup, dan
     otomatis menyingkir saat mendekati bagian bawah halaman (section CTA &
     footer sudah memuat ajakan yang sama — tidak perlu menutupinya). -->
<template>
    <transition name="wc-sticky-fade">
        <aside v-if="visible" class="wc-sticky-bar">
            <div class="wc-sticky-bar__inner">
                <div class="wc-sticky-bar__info">
                    <span class="wc-sticky-bar__pulse"></span>
                    <div class="wc-sticky-bar__copy">
                        <strong>
                            <span class="wc-sticky-bar__txt--lg">Siap melamar?</span>
                            <span class="wc-sticky-bar__txt--sm">Siap melamar?</span>
                        </strong>
                        <small>Temukan peran yang sesuai dengan potensi dirimu hari ini.</small>
                    </div>
                </div>

                <div class="wc-sticky-bar__actions">
                    <Link class="wc-sticky-bar__btn wc-sticky-bar__btn--primary" href="/karir/lowongan">
                        <i class="bi bi-search"></i>
                        <span class="wc-sticky-bar__txt--lg">Jelajahi Lowongan</span>
                        <span class="wc-sticky-bar__txt--sm">Lowongan</span>
                    </Link>
                    <button
                        v-if="hasMt"
                        class="wc-sticky-bar__btn wc-sticky-bar__btn--accent"
                        type="button"
                        aria-label="Lihat Program Management Trainee"
                        @click="goToSection('mt')"
                    >
                        <i class="bi bi-stars"></i>
                        <span class="wc-sticky-bar__lbl">Program MT</span>
                    </button>
                    <button class="wc-sticky-bar__close" type="button" aria-label="Tutup ajakan ini" @click="dismiss">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
            </div>
        </aside>
    </transition>
</template>

<script setup>
import { computed, ref, onMounted, onUnmounted } from 'vue';
import { Link } from '@inertiajs/vue3';
import { goToSection } from '@utils/career/data';

defineProps({
    hasMt: { type: Boolean, default: false },
});

const KUNCI_TUTUP = 'wcStickyBarDismissed';

const lewatiAwal = ref(false); // sudah menggulir cukup jauh dari hero
const dekatBawah = ref(false); // sudah dekat CTA/footer
const ditutup = ref(bacaPenutupan());

const visible = computed(() => lewatiAwal.value && !dekatBawah.value && !ditutup.value);

function bacaPenutupan() {
    // Hanya berlaku untuk sesi ini — kunjungan berikutnya bar muncul lagi.
    try {
        return sessionStorage.getItem(KUNCI_TUTUP) === '1';
    } catch {
        return false;
    }
}

function dismiss() {
    ditutup.value = true;
    try {
        sessionStorage.setItem(KUNCI_TUTUP, '1');
    } catch {
        /* mode privat: cukup untuk halaman ini */
    }
}

function checkScroll() {
    const y = window.scrollY || window.pageYOffset;
    lewatiAwal.value = y > 480;
    // 360px sebelum ujung halaman: beri ruang untuk section CTA & footer.
    dekatBawah.value = y + window.innerHeight >= document.documentElement.scrollHeight - 360;
}

onMounted(() => {
    checkScroll();
    window.addEventListener('scroll', checkScroll, { passive: true });
    window.addEventListener('resize', checkScroll, { passive: true });
});

onUnmounted(() => {
    window.removeEventListener('scroll', checkScroll);
    window.removeEventListener('resize', checkScroll);
});
</script>

<style scoped>
.wc-sticky-bar {
    position: fixed;
    /* Ikuti area aman perangkat (mis. home indicator iPhone). */
    bottom: calc(1.25rem + env(safe-area-inset-bottom, 0px));
    left: 50%;
    transform: translateX(-50%);
    /* Di bawah navbar (z-50) supaya menu mobile yang terbuka tidak tertimpa. */
    z-index: 45;
    width: min(880px, calc(100vw - 2rem));
}
.wc-sticky-bar__copy {
    min-width: 0;
}
.wc-sticky-bar__txt--sm {
    display: none;
}
.wc-sticky-bar__inner {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1.25rem;
    padding: 0.85rem 1.35rem;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.92);
    border: 1px solid rgba(99, 102, 241, 0.22);
    backdrop-filter: blur(20px) saturate(1.4);
    -webkit-backdrop-filter: blur(20px) saturate(1.4);
    box-shadow:
        0 20px 50px rgba(99, 102, 241, 0.14),
        inset 0 1px 0 rgba(255, 255, 255, 0.9);
    color: #0f172a;
}
.wc-sticky-bar__info {
    display: flex;
    align-items: center;
    gap: 0.85rem;
}
.wc-sticky-bar__pulse {
    width: 0.65rem;
    height: 0.65rem;
    border-radius: 50%;
    background: #10b981;
    box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.22);
    flex: none;
    animation: wcPulseGlow 2s ease-in-out infinite;
}
@keyframes wcPulseGlow {
    0%,
    100% {
        transform: scale(1);
        opacity: 1;
    }
    50% {
        transform: scale(1.2);
        opacity: 0.7;
    }
}
.wc-sticky-bar__info strong {
    display: block;
    font-size: 0.88rem;
    font-weight: 800;
    line-height: 1.2;
    color: #0f172a;
}
.wc-sticky-bar__info small {
    display: block;
    font-size: 0.72rem;
    color: #64748b;
    margin-top: 2px;
}
.wc-sticky-bar__actions {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    flex: none;
}
.wc-sticky-bar__btn {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    padding: 0.55rem 1.15rem;
    border-radius: 999px;
    font-size: 0.78rem;
    font-weight: 800;
    text-decoration: none;
    border: none;
    cursor: pointer;
    transition:
        transform 0.18s ease,
        box-shadow 0.18s ease,
        background 0.18s ease;
}
.wc-sticky-bar__btn--primary {
    background: linear-gradient(135deg, #8b5cf6, #6366f1);
    color: #ffffff;
    box-shadow: 0 8px 20px rgba(99, 102, 241, 0.32);
}
.wc-sticky-bar__btn--primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 12px 24px rgba(99, 102, 241, 0.42);
    color: #ffffff;
}
.wc-sticky-bar__btn--accent {
    background: rgba(99, 102, 241, 0.08);
    border: 1px solid rgba(99, 102, 241, 0.22);
    color: #4f46e5;
}
.wc-sticky-bar__btn--accent:hover {
    background: rgba(99, 102, 241, 0.15);
    color: #4338ca;
}

/* Tombol tutup — jalan keluar bagi pengguna yang tidak membutuhkan ajakan ini. */
.wc-sticky-bar__close {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex: none;
    width: 1.85rem;
    height: 1.85rem;
    border-radius: 50%;
    border: 1px solid rgba(226, 232, 240, 0.95);
    background: rgba(100, 116, 139, 0.08);
    color: #64748b;
    font-size: 0.66rem;
    cursor: pointer;
    transition:
        background 0.18s ease,
        color 0.18s ease;
}
.wc-sticky-bar__close:hover {
    background: rgba(99, 102, 241, 0.12);
    color: #4f46e5;
}

/* Transition */
.wc-sticky-fade-enter-active,
.wc-sticky-fade-leave-active {
    transition: all 0.35s cubic-bezier(0.16, 1, 0.3, 1);
}
.wc-sticky-fade-enter-from,
.wc-sticky-fade-leave-to {
    opacity: 0;
    transform: translate(-50%, 20px);
}

/* ═══ MOBILE — satu baris ringkas: status → aksi utama → MT (ikon) → tutup ═══
   Ponsel mendarat ikut dihitung mobile: lebarnya bisa >768px tapi tingginya
   hanya ~400px, dan bar versi desktop memakan terlalu banyak dari itu. */
@media (max-width: 768px), (orientation: landscape) and (max-height: 560px) {
    .wc-sticky-bar {
        bottom: calc(0.7rem + env(safe-area-inset-bottom, 0px));
        width: calc(100vw - 1.5rem);
    }
    .wc-sticky-bar__inner {
        gap: 0.6rem;
        padding: 0.5rem 0.5rem 0.5rem 0.9rem;
        border-radius: 1.15rem;
        /* Blur berat mahal di ponsel — cukup dikurangi, latar dipekatkan. */
        background: rgba(255, 255, 255, 0.96);
        border: 1px solid rgba(99, 102, 241, 0.2);
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
        box-shadow: 0 10px 28px rgba(99, 102, 241, 0.12);
    }
    .wc-sticky-bar__info {
        gap: 0.55rem;
        min-width: 0;
    }
    .wc-sticky-bar__info small {
        display: none;
    }
    /* Teks panjang diganti versi pendek agar tidak berebut ruang dengan tombol. */
    .wc-sticky-bar__txt--lg {
        display: none;
    }
    .wc-sticky-bar__txt--sm {
        display: inline;
    }
    .wc-sticky-bar__info strong {
        font-size: 0.79rem;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .wc-sticky-bar__pulse {
        width: 0.5rem;
        height: 0.5rem;
        box-shadow: 0 0 0 3px rgba(52, 211, 153, 0.22);
    }
    .wc-sticky-bar__actions {
        gap: 0.4rem;
    }
    .wc-sticky-bar__btn {
        padding: 0.5rem 0.85rem;
        font-size: 0.73rem;
        gap: 0.35rem;
    }
    /* Program MT jadi tombol ikon — labelnya sudah diwakili aria-label. */
    .wc-sticky-bar__btn--accent {
        width: 2.1rem;
        height: 2.1rem;
        padding: 0;
        justify-content: center;
        font-size: 0.85rem;
    }
    .wc-sticky-bar__lbl {
        display: none;
    }
}

/* Layar sangat sempit: sisakan hanya aksi, teks ajakan disembunyikan. */
@media (max-width: 380px) {
    .wc-sticky-bar__info strong {
        font-size: 0.74rem;
    }
    .wc-sticky-bar__btn--primary {
        padding: 0.5rem 0.7rem;
    }
}

/* Hormati preferensi gerak minimal: denyut & animasi masuk dinonaktifkan. */
@media (prefers-reduced-motion: reduce) {
    .wc-sticky-bar__pulse {
        animation: none;
    }
}
</style>
