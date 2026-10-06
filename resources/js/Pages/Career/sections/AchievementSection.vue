<!-- WEB CAREER — Section 2: Pencapaian (animated counters) -->
<template>
    <section id="achievement" class="wc-section ach">
        <div class="wc-sec-head wc-reveal">
            <span class="ach__eyebrow">✦ Pencapaian Kami</span>
            <h2>Angka yang berbicara tentang <span class="wc-grad">pertumbuhan</span>.</h2>
            <p>Dampak nyata dari kolaborasi ribuan karyawan di seluruh ekosistem EVO Group.</p>
        </div>

        <div class="wc-ach-grid">
            <article
                v-for="(a, i) in achievements"
                :key="a.label"
                ref="cards"
                class="wc-ach-card wc-reveal"
                :style="{ '--d': i * 70 + 'ms' }"
            >
                <span class="wc-ach-card__ico"><i class="bi" :class="a.icon"></i></span>
                <div class="wc-ach-card__num"><b :data-target="a.value">0</b><span>{{ a.suffix }}</span></div>
                <strong>{{ a.label }}</strong>
                <small>{{ a.desc }}</small>
            </article>
        </div>
    </section>
</template>

<script setup>
import { onMounted, onUnmounted, ref } from 'vue';

defineProps({
    achievements: { type: Array, default: () => [] },
});

const cards = ref([]);
let observer = null;

function animateCounter(el) {
    if (!el) return;
    const target = Number(el.dataset.target || 0);
    const dur = 1400;
    const start = performance.now();
    const tick = (now) => {
        const p = Math.min((now - start) / dur, 1);
        el.textContent = String(Math.round(target * (1 - Math.pow(1 - p, 4))));
        if (p < 1) requestAnimationFrame(tick);
    };
    requestAnimationFrame(tick);
}

onMounted(() => {
    observer = new IntersectionObserver(
        (entries) =>
            entries.forEach((e) => {
                if (!e.isIntersecting) return;
                animateCounter(e.target.querySelector('[data-target]'));
                observer.unobserve(e.target);
            }),
        { threshold: 0.5 },
    );
    (cards.value || []).forEach((el) => el && observer.observe(el));
});
onUnmounted(() => observer?.disconnect());
</script>

<style scoped>
/* Selaras 1:1 desain "EVO Career Landing" — eyebrow teks polos, angka solid, ikon flat */
.ach__eyebrow {
    display: inline-block;
    font-size: 0.72rem;
    font-weight: 800;
    letter-spacing: 0.18em;
    text-transform: uppercase;
    color: #8b5cf6;
}
.ach .wc-ach-card {
    background: rgba(255, 255, 255, 0.88);
    border: 1px solid rgba(226, 232, 240, 0.9);
    border-radius: 20px;
    transition: transform 0.25s cubic-bezier(0.16, 1, 0.3, 1), box-shadow 0.25s ease, border-color 0.25s ease;
}
.ach .wc-ach-card:hover {
    transform: translateY(-4px);
    border-color: rgba(139, 92, 246, 0.4);
    box-shadow: 0 20px 40px rgba(99, 102, 241, 0.14);
}
.ach .wc-ach-card__ico {
    background: rgba(99, 102, 241, 0.1);
    color: #6366f1;
    border-radius: 14px;
    transition: background 0.2s ease, color 0.2s ease;
}
.ach .wc-ach-card:hover .wc-ach-card__ico {
    background: #6366f1;
    color: #ffffff;
}
.ach .wc-ach-card__num {
    background: none;
    -webkit-text-fill-color: currentColor;
    color: #4f46e5;
    font-size: 1.9rem;
}
.ach .wc-ach-card__num span {
    color: #4f46e5;
}

/* ═══ MOBILE — kartu digeser mendatar, bukan menumpuk ke bawah ═══
   Grid `auto-fit minmax(180px)` runtuh jadi satu kolom di ponsel, sehingga
   lima kartu pencapaian membuat section ini sangat panjang dan pengguna harus
   menggulir lama sebelum sampai ke section berikutnya. Sebagai carousel,
   tingginya tetap ringkas dan kartu berikutnya "mengintip" di tepi kanan
   sebagai penanda bahwa daftarnya masih bisa digeser. */
@media (max-width: 767.98px) {
    .ach .wc-ach-grid {
        display: flex;
        gap: 0.7rem;
        overflow-x: auto;
        overscroll-behavior-x: contain;
        scroll-snap-type: x mandatory;
        scroll-padding-left: 1rem;
        -webkit-overflow-scrolling: touch;
        /* Tembus margin section (100vw - 2rem) agar kartu menyentuh tepi layar;
           padding mengembalikan posisi kartu pertama & memberi ruang bayangan. */
        margin-inline: -1rem;
        padding: 0.3rem 1rem 0.6rem;
        scrollbar-width: none;
    }
    .ach .wc-ach-grid::-webkit-scrollbar {
        display: none;
    }
    .ach .wc-ach-card {
        flex: 0 0 auto;
        width: 68%;
        max-width: 15rem;
        scroll-snap-align: start;
        padding: 1.3rem 1.15rem;
    }
    /* Sentuhan layar tidak punya hover — angkat bahu kartu saat ditekan saja. */
    .ach .wc-ach-card:hover {
        transform: none;
        box-shadow: 0 12px 30px rgba(15, 23, 42, 0.05);
        border-color: rgba(226, 232, 240, 0.9);
    }
    .ach .wc-ach-card:hover .wc-ach-card__ico {
        background: rgba(99, 102, 241, 0.1);
        color: #6366f1;
    }
    .ach .wc-ach-card__num {
        font-size: 1.7rem;
    }
}
</style>
