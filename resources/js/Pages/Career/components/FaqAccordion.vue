<!-- ══════════════════════════════════════════════════════════
     WEB CAREER — Accordion FAQ (dipakai bersama)
     Satu komponen untuk DUA tempat:
       - section FAQ di landing page  → mode ringkas, buka satu
       - halaman /karir/faq           → jawaban detail + boleh multi-buka
     Transisi tinggi memakai hook JS (bukan max-height) supaya tingginya
     presisi mengikuti isi, berapa pun panjang jawabannya.
     ══════════════════════════════════════════════════════════ -->
<template>
    <div class="wc-faq__container">
        <div
            v-for="item in items"
            :id="item.slug ? 'faq-' + item.slug : null"
            :key="item.slug || item.id"
            class="wc-faq__item"
            :class="{ 'is-open': terbuka(item) }"
        >
            <!-- Tombol, bukan div: pertanyaan harus bisa dicapai keyboard & pembaca layar. -->
            <button
                type="button"
                class="wc-faq__question"
                :aria-expanded="terbuka(item) ? 'true' : 'false'"
                @click="toggle(item)"
            >
                <span class="wc-faq__icon"><i class="bi" :class="item.ikon || 'bi-patch-question-fill'"></i></span>
                <div class="wc-faq__title-wrap">
                    <h3 class="wc-faq__title">{{ item.pertanyaan }}</h3>
                    <span v-if="item.kategoriNama" class="wc-faq__cat-badge">{{ item.kategoriNama }}</span>
                </div>
                <span class="wc-faq__toggle" :class="{ 'is-open': terbuka(item) }">
                    <i class="bi bi-chevron-down"></i>
                </span>
            </button>

            <transition
                name="wc-faq-anim"
                @before-enter="beforeEnter"
                @enter="enter"
                @after-enter="afterEnter"
                @before-leave="beforeLeave"
                @leave="leave"
            >
                <div v-show="terbuka(item)" class="wc-faq__answer-wrapper">
                    <div class="wc-faq__answer">
                        <p class="wc-faq__ringkas">{{ item.jawaban }}</p>

                        <!-- Jawaban panjang berformat. Sudah disaring HtmlBersih di
                             server; DOMPurify di sini adalah lapisan kedua supaya
                             data lama / dari jalur lain tetap tidak bisa menyuntik. -->
                        <div
                            v-if="showDetail && item.jawabanDetail"
                            class="wc-faq__detail"
                            v-html="bersih(item.jawabanDetail)"
                        ></div>

                        <slot name="aksi" :item="item"></slot>
                    </div>
                </div>
            </transition>
        </div>
    </div>
</template>

<script setup>
import DOMPurify from 'dompurify';
import { ref, watch } from 'vue';

const props = defineProps({
    items: { type: Array, default: () => [] },
    // Landing: satu terbuka sekaligus. Halaman FAQ: bebas beberapa sekaligus,
    // supaya pembaca bisa membandingkan dua jawaban tanpa yang satu menutup.
    single: { type: Boolean, default: false },
    showDetail: { type: Boolean, default: false },
    // Slug yang harus langsung terbuka saat pertama dirender (deep link / landing).
    initialOpen: { type: String, default: '' },
});

const emit = defineEmits(['open']);

const kunci = (item) => item.slug || item.id;
const dibuka = ref(new Set(props.initialOpen ? [props.initialOpen] : []));

watch(
    () => props.initialOpen,
    (slug) => {
        if (!slug) return;
        if (props.single) dibuka.value = new Set([slug]);
        else dibuka.value = new Set([...dibuka.value, slug]);
        emit('open', slug);
    },
);

function terbuka(item) {
    return dibuka.value.has(kunci(item));
}

function toggle(item) {
    const k = kunci(item);
    const sudah = dibuka.value.has(k);

    if (props.single) {
        dibuka.value = sudah ? new Set() : new Set([k]);
    } else {
        const next = new Set(dibuka.value);
        sudah ? next.delete(k) : next.add(k);
        dibuka.value = next;
    }

    // Hanya saat MEMBUKA — menutup bukan tanda ketertarikan.
    if (!sudah) emit('open', k);
}

function bersih(html) {
    return DOMPurify.sanitize(html || '', {
        ALLOWED_TAGS: ['p', 'br', 'strong', 'b', 'em', 'i', 'u', 's', 'ul', 'ol', 'li', 'h3', 'h4', 'blockquote', 'a'],
        ALLOWED_ATTR: ['href', 'target', 'rel'],
    });
}

// ── Hook transisi tinggi (pixel presisi, bukan max-height tebakan) ──
function beforeEnter(el) {
    el.style.height = '0px';
    el.style.opacity = '0';
    el.style.overflow = 'hidden';
}

function enter(el) {
    el.style.transition = 'height 0.38s cubic-bezier(0.16, 1, 0.3, 1), opacity 0.3s ease';
    el.style.height = el.scrollHeight + 'px';
    el.style.opacity = '1';
}

function afterEnter(el) {
    el.style.height = 'auto';
    el.style.overflow = 'visible';
}

function beforeLeave(el) {
    el.style.height = el.scrollHeight + 'px';
    el.style.overflow = 'hidden';
}

function leave(el) {
    // Force reflow agar transisi dari height nyata ke 0px terpicu dengan mulus.
    void el.offsetHeight;
    el.style.transition = 'height 0.35s cubic-bezier(0.16, 1, 0.3, 1), opacity 0.25s ease';
    el.style.height = '0px';
    el.style.opacity = '0';
}
</script>

<style scoped>
.wc-faq__container {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}
.wc-faq__item {
    position: relative;
    /* Deep link menggulir ke elemen ini — sisakan ruang untuk navbar sticky. */
    scroll-margin-top: 96px;
    background: #ffffff;
    border: 1.5px solid rgba(226, 232, 240, 0.95);
    border-radius: 1.25rem;
    padding: 1.25rem 1.5rem;
    box-shadow: 0 4px 18px rgba(15, 23, 42, 0.03);
    overflow: hidden;
    transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
}
.wc-faq__item::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 4px;
    height: 100%;
    background: linear-gradient(180deg, #6366f1 0%, #a855f7 100%);
    opacity: 0;
    transition: opacity 0.25s ease;
}
.wc-faq__item:hover {
    border-color: rgba(99, 102, 241, 0.35);
    transform: translateY(-2px);
    box-shadow: 0 12px 28px rgba(99, 102, 241, 0.1);
}
.wc-faq__item.is-open {
    background: #ffffff;
    border-color: rgba(99, 102, 241, 0.5);
    box-shadow: 0 16px 36px rgba(99, 102, 241, 0.14);
}
.wc-faq__item.is-open::before {
    opacity: 1;
}
.wc-faq__question {
    display: flex;
    align-items: center;
    gap: 1rem;
    width: 100%;
    padding: 0;
    background: none;
    border: 0;
    text-align: left;
    cursor: pointer;
    font: inherit;
    color: inherit;
}
.wc-faq__question:focus-visible {
    outline: 2px solid #6366f1;
    outline-offset: 4px;
    border-radius: 0.75rem;
}
.wc-faq__icon {
    display: grid;
    place-items: center;
    width: 2.6rem;
    height: 2.6rem;
    border-radius: 0.85rem;
    background: rgba(99, 102, 241, 0.08);
    color: #6366f1;
    font-size: 1.15rem;
    flex: none;
    transition: all 0.25s ease;
}
.wc-faq__item.is-open .wc-faq__icon {
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    color: #ffffff;
    transform: scale(1.05);
    box-shadow: 0 6px 16px rgba(99, 102, 241, 0.25);
}
.wc-faq__title-wrap {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    min-width: 0;
}
.wc-faq__title {
    margin: 0;
    font-size: 1.05rem;
    font-weight: 800;
    color: #1e1b4b;
    letter-spacing: -0.01em;
    line-height: 1.4;
}
.wc-faq__cat-badge {
    display: inline-flex;
    align-self: flex-start;
    padding: 0.15rem 0.6rem;
    border-radius: 999px;
    background: rgba(99, 102, 241, 0.08);
    color: #4f46e5;
    font-size: 0.72rem;
    font-weight: 700;
}
.wc-faq__toggle {
    display: grid;
    place-items: center;
    width: 2.2rem;
    height: 2.2rem;
    border-radius: 50%;
    background: #f1f5f9;
    color: #6366f1;
    font-size: 1.1rem;
    flex: none;
    transition: all 0.35s cubic-bezier(0.16, 1, 0.3, 1);
}
.wc-faq__toggle i {
    transition: transform 0.35s cubic-bezier(0.16, 1, 0.3, 1);
}
.wc-faq__toggle.is-open {
    background: #6366f1;
    color: #ffffff;
    box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
}
.wc-faq__toggle.is-open i {
    transform: rotate(180deg);
}

/* Wrapper & Content */
.wc-faq__answer-wrapper {
    will-change: height, opacity;
}
.wc-faq__answer {
    margin-top: 1rem;
    padding-top: 1rem;
    padding-left: 3.6rem;
    border-top: 1px dashed rgba(226, 232, 240, 0.95);
}
.wc-faq__ringkas {
    margin: 0;
    color: #334155;
    font-size: 0.95rem;
    line-height: 1.7;
    font-weight: 500;
}
.wc-faq__detail {
    margin-top: 1rem;
    padding: 1.1rem 1.25rem;
    border-radius: 0.9rem;
    background: #f8fafc;
    border: 1px solid #f1f5f9;
    color: #334155;
    font-size: 0.94rem;
    line-height: 1.75;
}
/* :deep — isi dari v-html tidak terjangkau scoped style biasa. */
.wc-faq__detail :deep(p) {
    margin: 0 0 0.85rem;
}
.wc-faq__detail :deep(p:last-child) {
    margin-bottom: 0;
}
.wc-faq__detail :deep(h3),
.wc-faq__detail :deep(h4) {
    margin: 1.2rem 0 0.6rem;
    font-size: 1.02rem;
    font-weight: 800;
    color: #1e1b4b;
}
.wc-faq__detail :deep(ul),
.wc-faq__detail :deep(ol) {
    margin: 0 0 0.85rem;
    padding-left: 1.4rem;
}
.wc-faq__detail :deep(li) {
    margin-bottom: 0.4rem;
}
.wc-faq__detail :deep(a) {
    color: #4f46e5;
    font-weight: 700;
    text-decoration: underline;
    text-underline-offset: 3px;
    transition: color 0.15s ease;
}
.wc-faq__detail :deep(a:hover) {
    color: #7c3aed;
}
.wc-faq__detail :deep(blockquote) {
    margin: 0 0 0.85rem;
    padding: 0.75rem 1.1rem;
    border-left: 4px solid #6366f1;
    background: rgba(99, 102, 241, 0.06);
    border-radius: 0 0.75rem 0.75rem 0;
}
.wc-faq__detail :deep(strong) {
    color: #1e1b4b;
    font-weight: 700;
}
/* Isi jawaban datang dari editor admin — URL/tabel/kode panjang tidak boleh
   melebarkan halaman di ponsel. */
.wc-faq__ringkas,
.wc-faq__detail {
    overflow-wrap: anywhere;
}
.wc-faq__detail :deep(img) {
    max-width: 100%;
    height: auto;
}
.wc-faq__detail :deep(pre) {
    overflow-x: auto;
}
.wc-faq__detail :deep(table) {
    display: block;
    max-width: 100%;
    overflow-x: auto;
}

@media (max-width: 640px) {
    .wc-faq__answer {
        padding-left: 0;
    }
    .wc-faq__item {
        padding: 1rem 1.1rem;
        border-radius: 1rem;
    }
    .wc-faq__question {
        gap: 0.75rem;
    }
    .wc-faq__icon {
        width: 2.2rem;
        height: 2.2rem;
        font-size: 1rem;
        border-radius: 0.7rem;
    }
    .wc-faq__title {
        font-size: 0.95rem;
    }
    .wc-faq__detail {
        padding: 0.9rem 1rem;
    }
}
</style>
