<!-- WEB CAREER — Section: Tentang Perusahaan (Visi, Misi, Brand & Core Values FAMILY) -->
<template>
    <section id="tentang" class="wc-section wc-about">
        <!-- Main Section Header -->
        <div class="wc-sec-head wc-reveal">
            <span class="wc-eyebrow"><span class="wc-dot"></span> Tentang Perusahaan</span>
            <h2>Tumbuh bersama industri <span class="wc-grad">pet care Indonesia</span>.</h2>
            <p>PT Evo Manufacturing Indonesia bergerak di bidang pet food dengan komitmen menghadirkan produk berkualitas tinggi, aman, dan terjangkau.</p>
        </div>

        <!-- Vision & Mission Grid -->
        <div class="wc-about__grid">
            <article class="wc-about__visi wc-reveal wc-reveal--left">
                <div class="wc-about__visi-shine" aria-hidden="true"></div>
                <i class="bi bi-compass-fill wc-about__visi-bg-icon" aria-hidden="true"></i>
                <span class="wc-about__visi-label"><i class="bi bi-compass"></i> Visi Kami</span>
                <p class="wc-about__visi-text">
                    Menjadi perusahaan unggul dan terpercaya di industri hewan peliharaan Indonesia.
                </p>
            </article>

            <article class="wc-about__misi wc-reveal wc-reveal--right" style="--d: 90ms">
                <div v-for="(m, i) in misi" :key="m.judul" class="wc-about__misi-item" :class="{ 'is-last': i === misi.length - 1 }">
                    <div class="wc-about__misi-no-badge">
                        <span>{{ String(i + 1).padStart(2, '0') }}</span>
                    </div>
                    <div>
                        <strong>{{ m.judul }}</strong>
                        <p>{{ m.deskripsi }}</p>
                    </div>
                </div>
            </article>
        </div>

        <!-- Brands Showcase Carousel / Grid -->
        <div class="wc-about__brands">
            <div
                v-for="(b, i) in brands"
                :key="b.nama"
                class="wc-about__brand wc-reveal"
                :style="{ '--d': i * 70 + 'ms' }"
            >
                <img class="wc-about__brand-logo" :src="`/brand-logos/${b.file}`" :alt="b.nama" loading="lazy" decoding="async" />
                <small class="wc-about__brand-name">{{ b.nama }}</small>
            </div>
        </div>

        <!-- ══════════════════════════════════════════════════════════════ -->
        <!-- SUB-SECTION UNIFIED: FAMILY VALUE (Fondasi Dalam Berkolaborasi) -->
        <!-- ══════════════════════════════════════════════════════════════ -->
        <div class="wc-about__family-wrapper">
            <!-- Family Header -->
            <div class="wc-about__family-head wc-reveal">
                <span class="wc-about__family-eyebrow">
                    <i class="bi bi-heart-fill"></i> Fondasi Dalam Berkolaborasi
                </span>
                <h3 class="wc-about__family-title">
                    FAMILY: <span class="wc-grad">Nilai yang Menyatukan Kita</span>
                </h3>
                <p class="wc-about__family-sub">
                    Di <strong>EVO Group</strong>, cara kita bekerja sama pentingnya dengan hasil yang kita capai.
                    Seluruh <strong>EVO Squad</strong> berpegang pada nilai-nilai <strong>FAMILY</strong> untuk bertumbuh dan memberikan yang terbaik setiap hari.
                </p>
            </div>

            <!-- Acronym Interactive Filter Ribbon -->
            <div class="wc-about__acronym-bar wc-reveal" style="--d: 80ms">
                <div class="wc-about__acronym-track">
                    <button
                        v-for="v in familyValues"
                        :key="'acronym-' + v.letter"
                        type="button"
                        class="wc-about__acronym-btn"
                        :class="{ 'is-active': activeLetter === v.letter }"
                        :style="{ '--btn-color': v.color, '--btn-grad': v.gradient }"
                        @click="selectLetter(v.letter)"
                    >
                        <span class="wc-about__acronym-char">{{ v.letter }}</span>
                        <span class="wc-about__acronym-label">{{ v.shortTitle }}</span>
                    </button>
                </div>
                <button
                    v-if="activeLetter"
                    type="button"
                    class="wc-about__acronym-reset"
                    @click="activeLetter = null"
                >
                    <i class="bi bi-x-circle-fill"></i> Tampilkan Semua
                </button>
            </div>

            <!-- FAMILY Values 6 Cards Grid (Ringkas, Compact, & Dynamic) -->
            <div class="wc-about__family-grid">
                <div
                    v-for="(value, i) in familyValues"
                    :key="value.letter"
                    :id="'family-card-' + value.letter"
                    class="wc-about__val-card wc-reveal"
                    :class="{
                        'is-dimmed': activeLetter && activeLetter !== value.letter,
                        'is-highlighted': activeLetter === value.letter
                    }"
                    :style="{
                        '--d': i * 50 + 100 + 'ms',
                        '--color': value.color,
                        '--gradient': value.gradient,
                        '--bg-soft': value.bgSoft
                    }"
                >
                    <div class="wc-about__val-head">
                        <div class="wc-about__val-avatar" :style="{ background: value.gradient }">
                            <span>{{ value.letter }}</span>
                        </div>
                        <span class="wc-about__val-tag" :style="{ color: value.color, borderColor: value.color + '35', background: value.bgSoft }">
                            <i :class="value.icon"></i> {{ value.tag }}
                        </span>
                    </div>

                    <div class="wc-about__val-body">
                        <h4 class="wc-about__val-title">{{ value.title }}</h4>
                        <p class="wc-about__val-desc">{{ value.desc }}</p>
                    </div>

                    <div class="wc-about__val-foot">
                        <span class="wc-about__val-pill" :style="{ color: value.color, background: value.bgSoft }">
                            <i class="bi bi-check2-circle"></i> {{ value.highlight }}
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>

<script setup>
import { ref } from 'vue';

const misi = [
    { judul: 'Produk berkualitas', deskripsi: 'Mengembangkan pet food yang adaptif, inovatif, dan bernutrisi seimbang.' },
    { judul: 'Distribusi nasional', deskripsi: 'Memperluas akses produk melalui jaringan cabang dan distribusi di Indonesia.' },
    { judul: 'Kemitraan berkelanjutan', deskripsi: 'Membangun hubungan jangka panjang dengan pelanggan dan mitra bisnis.' },
];

const brands = [
    { nama: 'Life Cat', file: 'life-cat.webp' },
    { nama: 'Ori Cat', file: 'ori-cat.webp' },
    { nama: 'Bio', file: 'bio.webp' },
    { nama: 'Life Dog', file: 'life-dog.webp' },
    { nama: 'Taro', file: 'taro.webp' },
];

const activeLetter = ref(null);

function selectLetter(letter) {
    if (activeLetter.value === letter) {
        activeLetter.value = null;
    } else {
        activeLetter.value = letter;
        const cardEl = document.getElementById(`family-card-${letter}`);
        if (cardEl) {
            cardEl.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
    }
}

const familyValues = [
    {
        letter: 'F',
        title: 'Focus On Customer',
        shortTitle: 'Customer',
        tag: 'Customer First',
        highlight: 'Internal & External Customer',
        color: '#f59e0b',
        gradient: 'linear-gradient(135deg, #f59e0b 0%, #d97706 100%)',
        bgSoft: 'rgba(245, 158, 11, 0.08)',
        icon: 'bi bi-person-heart',
        desc: 'Orientasi pada kebutuhan & kepuasan pelanggan, baik internal (customer = next process) maupun eksternal customer.'
    },
    {
        letter: 'A',
        title: 'Agility & Improvement',
        shortTitle: 'Agility',
        tag: 'Adaptable & Kaizen',
        highlight: 'Continuous Improvement',
        color: '#8b5cf6',
        gradient: 'linear-gradient(135deg, #8b5cf6 0%, #6d28d9 100%)',
        bgSoft: 'rgba(139, 92, 246, 0.08)',
        icon: 'bi bi-lightning-charge-fill',
        desc: 'Ketangkasan menghadapi perubahan & komitmen perbaikan berkelanjutan demi pertumbuhan bisnis.'
    },
    {
        letter: 'M',
        title: 'Mutual Growth',
        shortTitle: 'Growth',
        tag: 'Sinergi & Harmoni',
        highlight: 'Shared Success',
        color: '#06b6d4',
        gradient: 'linear-gradient(135deg, #06b6d4 0%, #0369a1 100%)',
        bgSoft: 'rgba(6, 182, 212, 0.08)',
        icon: 'bi bi-graph-up-arrow',
        desc: 'Membangun sinergi yang mendorong pertumbuhan & pengembangan diri untuk sukses bersama secara harmonis.'
    },
    {
        letter: 'I',
        title: 'Integrity',
        shortTitle: 'Integrity',
        tag: 'Walk The Talk',
        highlight: 'Building Trust',
        color: '#10b981',
        gradient: 'linear-gradient(135deg, #10b981 0%, #047857 100%)',
        bgSoft: 'rgba(16, 185, 129, 0.08)',
        icon: 'bi bi-shield-check',
        desc: 'Prinsip kejujuran, konsistensi kata & perbuatan (walk the talk), keterbukaan, dan TRUST dalam melayani.'
    },
    {
        letter: 'L',
        title: 'Leadership',
        shortTitle: 'Leadership',
        tag: 'Self-Leadership',
        highlight: 'Leading with Purpose',
        color: '#ec4899',
        gradient: 'linear-gradient(135deg, #ec4899 0%, #be185d 100%)',
        bgSoft: 'rgba(236, 72, 153, 0.08)',
        icon: 'bi bi-compass-fill',
        desc: 'Berperan sebagai Leader untuk diri sendiri (Self-Leadership) maupun orang lain demi hasil kerja terbaik.'
    },
    {
        letter: 'Y',
        title: '"Yes, We Can!" Spirit',
        shortTitle: 'Spirit',
        tag: 'Can-Do Mindset',
        highlight: 'Strive for Excellence',
        color: '#f43f5e',
        gradient: 'linear-gradient(135deg, #f43f5e 0%, #be123c 100%)',
        bgSoft: 'rgba(244, 63, 94, 0.08)',
        icon: 'bi bi-rocket-takeoff-fill',
        desc: 'Semangat positif & keyakinan tinggi menyikapi setiap target dan tantangan (Strive for Excellence).'
    }
];
</script>

<style scoped>
/* Main Grid (Vision & Mission) */
.wc-about__grid {
    display: grid;
    grid-template-columns: minmax(0, 0.85fr) minmax(0, 1.15fr);
    gap: 1.4rem;
    align-items: stretch;
}
.wc-about__visi {
    position: relative;
    overflow: hidden;
    padding: clamp(2rem, 4vw, 2.5rem) clamp(1.8rem, 4vw, 2.2rem);
    border-radius: 1.75rem;
    background: linear-gradient(135deg, #6366f1 0%, #4f46e5 50%, #4338ca 100%);
    color: #fff;
    box-shadow: 0 20px 48px rgba(79, 70, 229, 0.32);
    display: flex;
    flex-direction: column;
    justify-content: center;
}
.wc-about__visi-shine {
    position: absolute;
    top: -50%;
    right: -40%;
    width: 250px;
    height: 250px;
    background: radial-gradient(circle, rgba(255, 255, 255, 0.28), transparent 70%);
    pointer-events: none;
}
.wc-about__visi-bg-icon {
    position: absolute;
    right: -1rem;
    bottom: -1.5rem;
    font-size: 11rem;
    color: #ffffff;
    opacity: 0.1;
    pointer-events: none;
    line-height: 1;
}
.wc-about__visi-label {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 6px 14px;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.18);
    border: 1px solid rgba(255, 255, 255, 0.3);
    -webkit-backdrop-filter: blur(8px);
    backdrop-filter: blur(8px);
    font-size: 0.7rem;
    font-weight: 800;
    letter-spacing: 0.16em;
    text-transform: uppercase;
    color: #ffffff;
    width: fit-content;
}
.wc-about__visi-text {
    margin: 1.2rem 0 0;
    font-size: clamp(1.35rem, 2.5vw, 1.75rem);
    font-weight: 800;
    line-height: 1.35;
    letter-spacing: -0.015em;
    text-shadow: 0 2px 10px rgba(15, 23, 42, 0.2);
    position: relative;
    z-index: 1;
}
.wc-about__misi {
    padding: 1rem 1.6rem;
    border-radius: 1.75rem;
    background: rgba(255, 255, 255, 0.95);
    border: 1px solid rgba(99, 102, 241, 0.16);
    box-shadow: 0 16px 40px rgba(99, 102, 241, 0.07);
    display: flex;
    flex-direction: column;
    justify-content: center;
}
.wc-about__misi-item {
    display: flex;
    align-items: flex-start;
    gap: 1.1rem;
    padding: 1.1rem 0.8rem;
    border-bottom: 1px solid rgba(226, 232, 240, 0.8);
    border-radius: 1rem;
    transition: transform 0.22s ease, background 0.22s ease;
}
.wc-about__misi-item:hover {
    background: rgba(99, 102, 241, 0.04);
    transform: translateX(5px);
}
.wc-about__misi-item.is-last {
    border-bottom: none;
}
.wc-about__misi-no-badge {
    flex: none;
    width: 2.35rem;
    height: 2.35rem;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.12), rgba(139, 92, 246, 0.18));
    border: 1px solid rgba(99, 102, 241, 0.25);
    color: #4f46e5;
    font-weight: 900;
    font-size: 0.82rem;
    transition: background 0.25s ease, color 0.25s ease, transform 0.25s ease, box-shadow 0.25s ease;
}
.wc-about__misi-item:hover .wc-about__misi-no-badge {
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    color: #ffffff;
    transform: scale(1.08);
    box-shadow: 0 6px 16px rgba(99, 102, 241, 0.32);
}
.wc-about__misi-item strong {
    display: block;
    margin-bottom: 0.25rem;
    font-size: 1rem;
    font-weight: 800;
    color: #1e1b4b;
}
.wc-about__misi-item p {
    margin: 0;
    color: #64748b;
    font-size: 0.86rem;
    font-weight: 600;
    line-height: 1.6;
}

/* Brands Showcase */
.wc-about__brands {
    display: grid;
    grid-template-columns: repeat(5, minmax(0, 1fr));
    gap: 1.1rem;
    margin-top: 1.5rem;
}
.wc-about__brand {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 0.65rem;
    padding: 1.35rem 0.9rem;
    border-radius: 1.25rem;
    background: #ffffff;
    border: 1px solid rgba(226, 232, 240, 0.95);
    box-shadow: 0 10px 24px rgba(15, 23, 42, 0.04);
    text-align: center;
    transition: transform 0.25s cubic-bezier(0.16, 1, 0.3, 1), box-shadow 0.25s ease, border-color 0.25s ease;
}
.wc-about__brand:hover {
    transform: translateY(-5px);
    border-color: rgba(99, 102, 241, 0.35);
    box-shadow: 0 18px 36px rgba(99, 102, 241, 0.14);
}
.wc-about__brand-logo {
    height: 2.75rem;
    width: auto;
    max-width: 100%;
    object-fit: contain;
    filter: drop-shadow(0 2px 6px rgba(0,0,0,0.06));
    transition: transform 0.25s ease;
}
.wc-about__brand:hover .wc-about__brand-logo {
    transform: scale(1.08);
}
.wc-about__brand-name {
    color: #64748b;
    font-size: 0.78rem;
    font-weight: 700;
    margin-top: 2px;
}

/* ══════════════════════════════════════════════════════════════ */
/* UNIFIED SUB-SECTION: FAMILY VALUE */
/* ══════════════════════════════════════════════════════════════ */
.wc-about__family-wrapper {
    margin-top: clamp(3rem, 5vw, 4.5rem);
    padding-top: clamp(2.5rem, 4vw, 3.5rem);
    border-top: 1px dashed rgba(203, 213, 225, 0.8);
}

.wc-about__family-head {
    text-align: center;
    max-width: 760px;
    margin: 0 auto 1.8rem;
}

.wc-about__family-eyebrow {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    padding: 0.35rem 0.9rem;
    border-radius: 999px;
    background: rgba(99, 102, 241, 0.08);
    border: 1px solid rgba(99, 102, 241, 0.2);
    color: #4f46e5;
    font-size: 0.75rem;
    font-weight: 800;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    margin-bottom: 0.6rem;
}

.wc-about__family-title {
    margin: 0;
    font-size: clamp(1.5rem, 3vw, 2.1rem);
    font-weight: 800;
    color: #0f172a;
    letter-spacing: -0.02em;
}

.wc-about__family-sub {
    margin: 0.7rem 0 0;
    font-size: clamp(0.9rem, 1.5vw, 1.025rem);
    line-height: 1.6;
    color: #475569;
}

.wc-about__family-sub strong {
    color: #1e1b4b;
    font-weight: 700;
}

/* Acronym Interactive Bar */
.wc-about__acronym-bar {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.7rem;
    margin-bottom: 2rem;
}

.wc-about__acronym-track {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    flex-wrap: wrap;
    padding: 0.4rem;
    background: #ffffff;
    border: 1px solid rgba(226, 232, 240, 0.95);
    border-radius: 999px;
    box-shadow: 0 8px 24px rgba(15, 23, 42, 0.04);
}

.wc-about__acronym-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.35rem 0.85rem 0.35rem 0.45rem;
    border-radius: 999px;
    border: 1.5px solid transparent;
    background: transparent;
    cursor: pointer;
    transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
    outline: none;
}

.wc-about__acronym-char {
    width: 2rem;
    height: 2rem;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    background: var(--btn-grad);
    color: #ffffff;
    font-weight: 900;
    font-size: 0.95rem;
    box-shadow: 0 3px 8px rgba(15, 23, 42, 0.12);
    transition: transform 0.25s ease;
}

.wc-about__acronym-label {
    font-size: 0.82rem;
    font-weight: 700;
    color: #475569;
}

.wc-about__acronym-btn:hover {
    background: rgba(241, 245, 249, 0.8);
    border-color: var(--btn-color);
}

.wc-about__acronym-btn:hover .wc-about__acronym-char {
    transform: scale(1.1);
}

.wc-about__acronym-btn.is-active {
    background: #ffffff;
    border-color: var(--btn-color);
    box-shadow: 0 4px 14px var(--btn-color) + '25';
}

.wc-about__acronym-btn.is-active .wc-about__acronym-label {
    color: #0f172a;
    font-weight: 800;
}

.wc-about__acronym-reset {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.3rem 0.85rem;
    border-radius: 999px;
    background: #e2e8f0;
    border: none;
    color: #475569;
    font-size: 0.75rem;
    font-weight: 700;
    cursor: pointer;
    transition: background 0.2s ease;
}

.wc-about__acronym-reset:hover {
    background: #cbd5e1;
    color: #0f172a;
}

/* 6 Ringkas Values Cards Grid */
.wc-about__family-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 1.25rem;
    align-items: stretch;
}

.wc-about__val-card {
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    padding: 1.4rem 1.35rem;
    border-radius: 1.5rem;
    background: #ffffff;
    border: 1.5px solid rgba(226, 232, 240, 0.9);
    box-shadow: 0 8px 24px rgba(15, 23, 42, 0.03);
    transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
    position: relative;
}

.wc-about__val-card:hover {
    transform: translateY(-6px);
    border-color: var(--color);
    box-shadow: 0 16px 36px rgba(15, 23, 42, 0.08), 0 0 0 1px var(--color);
}

.wc-about__val-card.is-dimmed {
    opacity: 0.45;
    filter: grayscale(0.2);
    transform: scale(0.98);
}

.wc-about__val-card.is-highlighted {
    border-color: var(--color);
    box-shadow: 0 18px 40px rgba(15, 23, 42, 0.1), 0 0 0 2px var(--color);
    transform: translateY(-6px) scale(1.02);
}

/* Card Head */
.wc-about__val-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.6rem;
    margin-bottom: 0.9rem;
}

.wc-about__val-avatar {
    width: 2.5rem;
    height: 2.5rem;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 0.85rem;
    color: #ffffff;
    font-size: 1.35rem;
    font-weight: 900;
    box-shadow: 0 6px 14px rgba(15, 23, 42, 0.12);
    transition: transform 0.3s ease;
}

.wc-about__val-card:hover .wc-about__val-avatar {
    transform: scale(1.08) rotate(-4deg);
}

.wc-about__val-tag {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.28rem 0.7rem;
    border-radius: 999px;
    border: 1px solid;
    font-size: 0.7rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.02em;
}

/* Card Body */
.wc-about__val-body {
    flex: 1;
    margin-bottom: 0.9rem;
}

.wc-about__val-title {
    margin: 0 0 0.45rem;
    font-size: 1.05rem;
    font-weight: 800;
    color: #0f172a;
    letter-spacing: -0.01em;
}

.wc-about__val-desc {
    margin: 0;
    font-size: 0.835rem;
    line-height: 1.58;
    color: #475569;
    font-weight: 500;
}

/* Card Foot */
.wc-about__val-foot {
    padding-top: 0.75rem;
    border-top: 1px dashed rgba(226, 232, 240, 0.9);
}

.wc-about__val-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.35rem 0.75rem;
    border-radius: 0.65rem;
    font-size: 0.74rem;
    font-weight: 700;
    width: 100%;
    justify-content: center;
}

/* Responsive */
@media (max-width: 1040px) {
    .wc-about__family-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 900px) {
    .wc-about__grid {
        grid-template-columns: 1fr;
    }
    .wc-about__brands {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }
}

@media (max-width: 640px) {
    .wc-about__family-grid {
        grid-template-columns: 1fr;
        gap: 1rem;
    }
    /* Label tetap tampil; 6 pil berlabel tidak muat satu baris, jadi ditata
       2 kolom rapi (bukan flex-wrap yang menyisakan satu pil sendirian) dan
       wadahnya jadi kotak membulat — bentuk pill 999px cuma pas untuk 1 baris. */
    .wc-about__acronym-track {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 0.3rem;
        width: 100%;
        max-width: 24rem;
        padding: 0.45rem;
        border-radius: 1.35rem;
    }
    .wc-about__acronym-btn {
        justify-content: flex-start;
        width: 100%;
        min-height: 2.75rem;
        padding: 0.3rem 0.6rem 0.3rem 0.3rem;
        border-radius: 999px;
    }
    .wc-about__acronym-char {
        width: 2rem;
        height: 2rem;
        font-size: 0.88rem;
        flex: none;
    }
    .wc-about__acronym-label {
        font-size: 0.78rem;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .wc-about__brands {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 0.75rem;
    }
    .wc-about__visi,
    .wc-about__misi {
        border-radius: 1.35rem;
    }
    .wc-about__misi {
        padding: 0.5rem 1rem;
    }
    .wc-about__misi-item {
        gap: 0.8rem;
        padding: 0.95rem 0.35rem;
    }
    /* Ikon dekoratif 11rem memakan hampir seluruh kartu di layar kecil. */
    .wc-about__visi-bg-icon {
        font-size: 7rem;
    }
    .wc-about__val-card {
        padding: 1.15rem 1.1rem;
        border-radius: 1.25rem;
    }
    .wc-about__val-tag {
        font-size: 0.65rem;
        padding: 0.25rem 0.55rem;
    }
}
</style>
