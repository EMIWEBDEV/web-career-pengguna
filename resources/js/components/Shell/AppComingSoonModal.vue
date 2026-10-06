<template>
    <Teleport to="body">
        <transition name="cs-fade">
            <div
                v-if="modelValue"
                class="cs-backdrop"
                role="dialog"
                aria-modal="true"
                :aria-label="`${feature.title} — segera hadir`"
                @mousedown.self="close"
            >
                <transition name="cs-pop" appear>
                    <div v-if="modelValue" class="cs-dialog" @mousedown.stop>
                        <button class="cs-close" type="button" aria-label="Tutup" @click="close">
                            <i class="bi bi-x-lg"></i>
                        </button>

                        <!-- Panel atas: wash indigo→violet + ikon fitur -->
                        <div class="cs-stage">
                            <span class="cs-wash" aria-hidden="true"></span>
                            <span class="cs-ring cs-ring--1" aria-hidden="true"></span>
                            <span class="cs-ring cs-ring--2" aria-hidden="true"></span>

                            <span class="cs-tile">
                                <i :class="feature.icon"></i>
                            </span>
                        </div>

                        <div class="cs-body">
                            <span class="cs-eyebrow">Segera Hadir</span>
                            <h2 class="cs-title">{{ feature.title }}</h2>
                            <p class="cs-desc">{{ feature.desc }}</p>

                            <div class="cs-note">
                                <span class="cs-note__icon"><i class="bi bi-stars"></i></span>
                                <span class="cs-note__text">
                                    Terus dukung pengembangan <strong>Web Careers</strong> — setiap masukan Anda kami
                                    pakai untuk merapikan fitur berikutnya.
                                </span>
                            </div>
                        </div>

                        <div class="cs-foot">
                            <button class="cs-btn" type="button" @click="close">Mengerti</button>
                        </div>
                    </div>
                </transition>
            </div>
        </transition>
    </Teleport>
</template>

<script setup>
import { computed, onBeforeUnmount, watch } from 'vue';

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    // Kunci fitur yang diklik dari topbar: activity | notifications | help
    variant: { type: String, default: 'activity' },
});

const emit = defineEmits(['update:modelValue']);

const VARIANTS = {
    activity: {
        icon: 'bi bi-cloud-arrow-down',
        title: 'Pusat Unduhan',
        desc: 'Nantinya seluruh berkas ekspor dan unggahan Anda terkumpul di satu tempat, lengkap dengan progres dan riwayatnya.',
    },
    notifications: {
        icon: 'bi bi-bell',
        title: 'Notifikasi',
        desc: 'Kabar tahapan seleksi, jadwal tes, dan pengumuman akan masuk ke sini supaya tidak ada yang terlewat.',
    },
    help: {
        icon: 'bi bi-question-circle',
        title: 'Pusat Panduan',
        desc: 'Panduan langkah demi langkah dan kanal bantuan sedang kami susun agar setiap halaman punya penjelasannya sendiri.',
    },
};

const feature = computed(() => VARIANTS[props.variant] || VARIANTS.activity);

function close() {
    emit('update:modelValue', false);
}

function onKeydown(event) {
    if (event.key === 'Escape') close();
}

watch(
    () => props.modelValue,
    (open) => {
        if (open) {
            document.addEventListener('keydown', onKeydown);
        } else {
            document.removeEventListener('keydown', onKeydown);
        }
    },
);

onBeforeUnmount(() => document.removeEventListener('keydown', onKeydown));
</script>

<style scoped>
/* Token lokal — mengikuti palet EVO: indigo #4f46e5 + violet #7c3aed,
   dengan netral yang dibiaskan sedikit ke indigo agar menyatu. */
.cs-backdrop {
    --cs-indigo: #4f46e5;
    --cs-violet: #7c3aed;
    --cs-ink: #16143a;
    --cs-muted: #6b7192;
    --cs-line: #e6e6f2;
    --cs-surface: #ffffff;
    --cs-veil: #f6f5fd;

    position: fixed;
    inset: 0;
    z-index: 2000;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 1.25rem;
    background: rgba(30, 27, 75, 0.5);
    backdrop-filter: blur(7px);
}

.cs-dialog {
    position: relative;
    width: 100%;
    max-width: 26rem;
    background: var(--cs-surface);
    border-radius: 26px;
    overflow: hidden;
    box-shadow:
        0 1px 0 rgba(255, 255, 255, 0.6) inset,
        0 24px 60px -20px rgba(30, 27, 75, 0.45);
    color: var(--cs-ink);
    font-size: 13px;
}

/* ── Tombol tutup ─────────────────────────────────────────────── */
.cs-close {
    position: absolute;
    top: 0.75rem;
    right: 0.75rem;
    z-index: 2;
    width: 1.9rem;
    height: 1.9rem;
    display: grid;
    place-items: center;
    border: 0;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.55);
    color: #4b4a6a;
    font-size: 0.7rem;
    cursor: pointer;
    transition: background 160ms ease, color 160ms ease;
}

.cs-close:hover {
    background: #ffffff;
    color: var(--cs-indigo);
}

.cs-close:focus-visible {
    outline: 2px solid var(--cs-indigo);
    outline-offset: 2px;
}

/* ── Panggung ikon ────────────────────────────────────────────── */
.cs-stage {
    position: relative;
    height: 9.5rem;
    display: grid;
    place-items: center;
    overflow: hidden;
    border-bottom: 1px solid var(--cs-line);
}

.cs-wash {
    position: absolute;
    inset: 0;
    background:
        radial-gradient(circle at 30% 25%, rgba(79, 70, 229, 0.28), transparent 58%),
        radial-gradient(circle at 74% 72%, rgba(124, 58, 237, 0.26), transparent 60%),
        var(--cs-veil);
    animation: cs-breathe 9s ease-in-out infinite;
}

.cs-ring {
    position: absolute;
    border-radius: 50%;
    border: 1px solid rgba(79, 70, 229, 0.18);
}

.cs-ring--1 {
    width: 7.5rem;
    height: 7.5rem;
}

.cs-ring--2 {
    width: 11rem;
    height: 11rem;
    border-color: rgba(124, 58, 237, 0.12);
}

.cs-tile {
    position: relative;
    width: 3.75rem;
    height: 3.75rem;
    display: grid;
    place-items: center;
    border-radius: 1.1rem;
    background: linear-gradient(140deg, var(--cs-indigo), var(--cs-violet));
    color: #fff;
    font-size: 1.5rem;
    box-shadow: 0 14px 28px -12px rgba(79, 70, 229, 0.75);
}

.cs-tile .bi {
    display: block;
    line-height: 1;
}

/* ── Isi ──────────────────────────────────────────────────────── */
.cs-body {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    padding: 1.375rem 1.5rem 0.25rem;
    text-align: center;
}

.cs-eyebrow {
    font-size: 0.625rem;
    font-weight: 700;
    letter-spacing: 0.16em;
    text-transform: uppercase;
    color: var(--cs-indigo);
}

.cs-title {
    margin: 0;
    font-size: 1.3rem;
    font-weight: 800;
    letter-spacing: -0.015em;
    text-wrap: balance;
    color: var(--cs-ink);
}

.cs-desc {
    margin: 0;
    max-width: 30ch;
    margin-inline: auto;
    font-size: 0.8125rem;
    line-height: 1.6;
    color: var(--cs-muted);
    text-wrap: pretty;
}

.cs-note {
    display: flex;
    align-items: flex-start;
    gap: 0.625rem;
    margin-top: 0.75rem;
    padding: 0.75rem 0.875rem;
    border: 1px solid rgba(79, 70, 229, 0.16);
    border-radius: 0.875rem;
    background: linear-gradient(180deg, rgba(79, 70, 229, 0.06), rgba(124, 58, 237, 0.05));
    text-align: left;
}

.cs-note__icon {
    flex: none;
    width: 1.5rem;
    height: 1.5rem;
    display: grid;
    place-items: center;
    border-radius: 0.5rem;
    background: #fff;
    color: var(--cs-violet);
    font-size: 0.75rem;
    box-shadow: 0 2px 6px -2px rgba(79, 70, 229, 0.4);
}

.cs-note__text {
    font-size: 0.75rem;
    line-height: 1.55;
    color: #4a4a6b;
}

.cs-note__text strong {
    font-weight: 700;
    color: var(--cs-ink);
}

/* ── Aksi ─────────────────────────────────────────────────────── */
.cs-foot {
    padding: 1rem 1.5rem 1.375rem;
}

.cs-btn {
    width: 100%;
    padding: 0.6875rem 1rem;
    border: 0;
    border-radius: 0.75rem;
    background: linear-gradient(140deg, var(--cs-indigo), var(--cs-violet));
    color: #fff;
    font: inherit;
    font-weight: 700;
    font-size: 0.8125rem;
    letter-spacing: 0.01em;
    cursor: pointer;
    transition: transform 160ms ease, box-shadow 160ms ease, filter 160ms ease;
    box-shadow: 0 10px 22px -12px rgba(79, 70, 229, 0.9);
}

.cs-btn:hover {
    filter: brightness(1.06);
    transform: translateY(-1px);
}

.cs-btn:active {
    transform: translateY(0);
}

.cs-btn:focus-visible {
    outline: 2px solid var(--cs-violet);
    outline-offset: 2px;
}

/* ── Transisi ─────────────────────────────────────────────────── */
.cs-fade-enter-active,
.cs-fade-leave-active {
    transition: opacity 200ms ease;
}

.cs-fade-enter-from,
.cs-fade-leave-to {
    opacity: 0;
}

.cs-pop-enter-active {
    transition: transform 280ms cubic-bezier(0.2, 0.9, 0.25, 1), opacity 220ms ease;
}

.cs-pop-leave-active {
    transition: transform 160ms ease, opacity 160ms ease;
}

.cs-pop-enter-from,
.cs-pop-leave-to {
    opacity: 0;
    transform: translateY(12px) scale(0.97);
}

@keyframes cs-breathe {
    0%,
    100% {
        transform: scale(1);
    }
    50% {
        transform: scale(1.08);
    }
}

@media (prefers-reduced-motion: reduce) {
    .cs-wash {
        animation: none;
    }

    .cs-pop-enter-active,
    .cs-pop-leave-active,
    .cs-fade-enter-active,
    .cs-fade-leave-active,
    .cs-btn {
        transition: none;
    }
}
</style>
