<!-- ══════════════════════════════════════════════════════════════════
     WEB CAREER — AuthShell: kerangka SATU-satunya untuk SELURUH halaman auth
     (login, register, cek KTP, lupa/reset sandi, konfirmasi, menunggu email,
     hasil verifikasi). 1:1 desain "EVO Career Login Redesign":
     bg terang + aurora + scene ambient (balon, komet, bintang, glyph karir,
     awan), panel pernyataan + chip fitur, kartu kaca mengambang, footer
     anak perusahaan (ENB → GMN → EMI), toast. Font Inter + Playfair Display.

     Semua kelas form (.field/.input-wrap/.btn-login/.otp-*/…) didefinisikan di
     sini sebagai style GLOBAL ber-namespace `.authx` agar mengastyle konten slot
     halaman mana pun. Halaman cukup mengisi markup.
     ══════════════════════════════════════════════════════════════════ -->
<template>
    <Head :title="pageTitle">
        <meta name="robots" content="noindex, nofollow" />
        <link rel="preconnect" href="https://fonts.googleapis.com" />
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
        <link
            href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Playfair+Display:ital,wght@1,500;1,600;1,700&display=swap"
            rel="stylesheet"
        />
    </Head>

    <div class="authx">
        <!-- ═══ BACKDROP: aurora + grid + scene ambient ═══ -->
        <div class="ambient" aria-hidden="true">
            <span class="aurora aurora--a"></span>
            <span class="aurora aurora--b"></span>
            <span class="aurora aurora--c"></span>
            <div class="grid"></div>

            <!-- partikel naik -->
            <div class="particles">
                <span class="pt pt--1"></span><span class="pt pt--2"></span><span class="pt pt--3"></span>
                <span class="pt pt--4"></span><span class="pt pt--5"></span><span class="pt pt--6"></span>
                <span class="pt pt--7"></span><span class="pt pt--8"></span><span class="pt pt--9"></span>
                <span class="pt pt--10"></span><span class="pt pt--11"></span><span class="pt pt--12"></span><span class="pt pt--13"></span>
            </div>

            <!-- balon aspirasi -->
            <div class="balloon balloon--1"><div class="balloon__sway"><span class="balloon__body"></span><span class="balloon__string"></span></div></div>
            <div class="balloon balloon--2"><div class="balloon__sway"><span class="balloon__body"></span><span class="balloon__string"></span></div></div>
            <div class="balloon balloon--3"><div class="balloon__sway"><span class="balloon__body"></span><span class="balloon__string"></span></div></div>

            <!-- bokeh peluang -->
            <span class="bokeh bokeh--a"></span>
            <span class="bokeh bokeh--b"></span>

            <!-- anak tangga karir -->
            <div class="steps">
                <span class="step step--1"></span><span class="step step--2"></span><span class="step step--3"></span><span class="step step--4"></span><span class="step step--5"></span>
            </div>

            <!-- komet -->
            <div class="comet"><span class="comet__line"><span class="comet__head"></span></span></div>

            <!-- bintang berkelip -->
            <span class="twinkle twinkle--1"></span>
            <span class="twinkle twinkle--2"></span>
            <span class="twinkle twinkle--3"></span>
            <span class="twinkle twinkle--4"></span>

            <!-- konstelasi mengorbit -->
            <div class="orbit"><span class="orbit__a"></span><span class="orbit__b"></span></div>

            <!-- glyph karir melayang (di tengah panggung) -->
            <i class="bi bi-briefcase-fill glyph glyph--1"></i>
            <i class="bi bi-mortarboard-fill glyph glyph--2"></i>
            <i class="bi bi-stars glyph glyph--3"></i>

            <!-- roket terbang + jejak asap -->
            <div class="rocket">
                <span class="rocket__smoke rocket__smoke--1"></span>
                <span class="rocket__smoke rocket__smoke--2"></span>
                <span class="rocket__smoke rocket__smoke--3"></span>
                <span class="rocket__smoke rocket__smoke--4"></span>
                <i class="bi bi-rocket-takeoff-fill rocket__ico"></i>
            </div>

            <!-- halo pertumbuhan -->
            <span class="halo halo--1"></span>
            <span class="halo halo--2"></span>

            <!-- awan hanyut -->
            <div class="cloud cloud--1"><span class="cloud__body"><span class="cloud__p1"></span><span class="cloud__p2"></span></span></div>
            <div class="cloud cloud--2"><span class="cloud__body"><span class="cloud__p1"></span><span class="cloud__p2"></span></span></div>
            <div class="cloud cloud--3"><span class="cloud__body"><span class="cloud__p1"></span><span class="cloud__p2"></span></span></div>

            <!-- kabut mimpi -->
            <span class="mist"></span>
        </div>

        <!-- TOAST -->
        <transition name="v3-toast">
            <div v-if="notice && notice.visible" class="v3-toast" :class="`v3-toast--${notice.type}`" role="alert">
                <i :class="notice.type === 'error' ? 'bi bi-exclamation-triangle-fill' : 'bi bi-info-circle-fill'"></i>
                <span>{{ notice.message }}</span>
                <button class="v3-toast__close" type="button" aria-label="Tutup" @click="$emit('close-notice')"><i class="bi bi-x-lg"></i></button>
            </div>
        </transition>

        <!-- TOPBAR -->
        <nav class="topbar">
            <Link href="/" class="mark-login">
                <span class="mark-login__logo"><img src="/logo/EVOGROUP.png" alt="EVO Group" class="brand-logo" /></span>
                <span class="mark-login-text">
                    <b>EVO Career</b>
                    <small>PORTAL KANDIDAT</small>
                </span>
            </Link>
            <div class="top-right">
                <Link v-if="showBack" :href="backHref" class="top-link"><i class="bi bi-arrow-left"></i> {{ backLabel }}</Link>
                <span v-if="showPill" class="v-pill">
                    <i class="bi bi-shield-check pill-ico"></i>
                    <span class="pill-txt">Terenkripsi</span>
                    <span class="pill-div">|</span>
                    <b class="pill-brand">EVO Group</b>
                </span>
            </div>
        </nav>

        <!-- MAIN -->
        <main class="stage-grid">
            <section class="statement">
                <span class="eyebrow"><span class="dot"></span>{{ eyebrow }}</span>
                <h1 class="display">
                    <span class="display__l1">{{ title }}</span>
                    <span class="display__l2">{{ titleAccent }}</span>
                </h1>
                <p class="lede">{{ lede }}</p>
                <div v-if="chips.length" class="hero-chips">
                    <span v-for="c in chips" :key="c.text" class="hero-chip"><i class="bi" :class="c.icon" :style="{ color: c.color }"></i> {{ c.text }}</span>
                </div>
                <p v-if="subMobile" class="sub-mobile">{{ subMobile }}</p>
            </section>

            <section class="stage-side">
                <div class="float-card" :class="{ 'float-card--center': centerCard }">
                    <div v-if="cardTitle" class="card-head">
                        <div class="card-head__l">
                            <h3>{{ cardTitle }}</h3>
                            <p v-if="cardSubtitle" class="card-head__sub">{{ cardSubtitle }}</p>
                        </div>
                        <span v-if="cardTag" class="tag">{{ cardTag }}</span>
                    </div>
                    <slot />
                    <div v-if="showVer" class="ver-row">
                        <span>EVO <b>Career</b></span>
                        <span>© {{ tahun }} EVO Group</span>
                    </div>
                </div>
            </section>
        </main>

        <!-- FOOTER anak perusahaan — ENB → GMN → EMI -->
        <footer class="subs">
            <span class="lbl">Supported By</span>
            <div class="logos">
                <template v-for="(co, i) in subsidiaries" :key="co.alt">
                    <span class="logo-wrap" :class="'logo-wrap--' + (i + 1)">
                        <img :src="co.src" :alt="co.alt" loading="lazy" decoding="async" />
                    </span>
                    <span v-if="i < subsidiaries.length - 1" class="dv"></span>
                </template>
            </div>
            <span class="lbl">Group of Companies</span>
        </footer>
    </div>
</template>

<script setup>
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    // Akhiran " | Careers Evo Group" ditempel otomatis oleh createInertiaApp
    // (lihat utils/judulHalaman.js) — jangan ditulis ulang di sini.
    pageTitle: { type: String, default: 'Portal Kandidat' },
    eyebrow: { type: String, default: 'Portal Career' },
    title: { type: String, default: 'Karier Impian' },
    titleAccent: { type: String, default: 'Dimulai di Sini.' },
    lede: { type: String, default: '' },
    subMobile: { type: String, default: '' },
    backHref: { type: String, default: '/' },
    backLabel: { type: String, default: 'Kembali ke Karir' },
    showBack: { type: Boolean, default: true },
    showPill: { type: Boolean, default: true },
    cardTitle: { type: String, default: '' },
    cardSubtitle: { type: String, default: '' },
    cardTag: { type: String, default: '' },
    centerCard: { type: Boolean, default: false },
    showVer: { type: Boolean, default: true },
    notice: { type: Object, default: null },
    chips: {
        type: Array,
        default: () => [
            { icon: 'bi-briefcase-fill', color: '#6366f1', text: 'Lamar Lowongan' },
            { icon: 'bi-mortarboard-fill', color: '#8b5cf6', text: 'Program MT' },
            { icon: 'bi-graph-up-arrow', color: '#f59e0b', text: 'Pantau Seleksi' },
        ],
    },
});
defineEmits(['close-notice']);

const tahun = new Date().getFullYear();
// Urutan resmi: ER → ECT → ENB → GMN → EMI.
const subsidiaries = [
    { src: '/logo/ENB.png', alt: 'PT EVO Nusa Bersaudara' },
    { src: '/logo/GMN.png', alt: 'PT Graha Maju Nusantara' },
    { src: '/logo/EMI.png', alt: 'PT EVO Manufacturing Indonesia' },
    { src: '/logo/ER.webp', alt: 'ER' },
    { src: '/logo/ECT.webp', alt: 'ECT' },
];
</script>

<!-- Style GLOBAL ber-namespace `.authx` (bukan scoped) supaya konten slot ikut terstyle. -->
<style>
.authx * { box-sizing: border-box; }
.authx .bi { display: contents; }
.authx {
    --ink: #0f172a; --indigo: #4f46e5; --indigo-2: #6366f1; --violet: #8b5cf6; --gold: #f59e0b;
    --text: #0f172a; --text-soft: #475569; --line: #e2e8f0;
    position: relative; min-height: 100vh; min-height: 100dvh; width: 100%; overflow-x: hidden; overflow-y: auto; isolation: isolate;
    display: flex; flex-direction: column;
    font-family: 'Inter', system-ui, sans-serif; color: var(--text);
    background: linear-gradient(180deg, #f8fafc 0%, #eef2ff 46%, #ffffff 100%);
    -webkit-font-smoothing: antialiased;
}

/* ═══ AMBIENT SCENE ═══ */
.authx .ambient { position: absolute; inset: 0; z-index: 0; overflow: hidden; pointer-events: none; }
.authx .aurora { position: absolute; border-radius: 50%; }
.authx .aurora--a { top: -14%; left: -8%; width: clamp(340px, 44vw, 680px); height: clamp(340px, 44vw, 680px); filter: blur(74px); opacity: .6; background: radial-gradient(circle, rgba(139, 92, 246, .55), transparent 68%); animation: authAurora 20s ease-in-out infinite; }
.authx .aurora--b { top: 8%; right: -6%; width: clamp(360px, 40vw, 640px); height: clamp(360px, 40vw, 640px); filter: blur(80px); opacity: .5; background: radial-gradient(circle, rgba(99, 102, 241, .5), transparent 66%); animation: authAurora 24s ease-in-out 2s infinite reverse; }
.authx .aurora--c { bottom: -16%; left: 34%; width: clamp(280px, 32vw, 520px); height: clamp(280px, 32vw, 520px); filter: blur(72px); opacity: .42; background: radial-gradient(circle, rgba(245, 158, 11, .4), transparent 68%); animation: authAurora 26s ease-in-out 1s infinite; }
.authx .grid { position: absolute; inset: -20%; background-image: linear-gradient(rgba(99, 102, 241, .055) 1px, transparent 1px), linear-gradient(90deg, rgba(99, 102, 241, .055) 1px, transparent 1px); background-size: 54px 54px; -webkit-mask: radial-gradient(ellipse at 42% 42%, #000 34%, transparent 74%); mask: radial-gradient(ellipse at 42% 42%, #000 34%, transparent 74%); animation: authGridPan 42s linear infinite; }
.authx .particles span { position: absolute; bottom: -16px; border-radius: 50%; animation-name: authFloatUp; animation-timing-function: linear; animation-iteration-count: infinite; }
.authx .balloon { position: absolute; bottom: 0; animation-name: authBalloonUp; animation-timing-function: linear; animation-iteration-count: infinite; }
.authx .balloon__sway { animation-name: authSway; animation-timing-function: ease-in-out; animation-iteration-count: infinite; }
.authx .balloon__body { display: block; border-radius: 50% 50% 50% 50% / 56% 56% 44% 44%; }
.authx .balloon__string { display: block; width: 1px; height: 44px; margin: 0 auto; background: rgba(148, 163, 184, .4); }
.authx .bokeh { position: absolute; border-radius: 50%; filter: blur(6px); }
.authx .bokeh--a { top: 16%; left: 40%; width: 190px; height: 190px; background: radial-gradient(circle, rgba(139, 92, 246, .14), transparent 70%); animation: authDriftX 22s ease-in-out infinite; }
.authx .bokeh--b { top: 58%; left: 24%; width: 130px; height: 130px; background: radial-gradient(circle, rgba(99, 102, 241, .12), transparent 70%); animation: authDriftX 27s ease-in-out -8s infinite reverse; }
.authx .steps { position: absolute; bottom: 8%; left: 4%; width: 130px; height: 110px; }
.authx .steps span { position: absolute; width: 22px; height: 22px; border-radius: 6px; background: #7c6cf5; opacity: .2; animation: authStepGlow 3.2s ease-in-out infinite; }
.authx .comet { position: absolute; top: 0; left: 0; animation: authComet 15s ease-in 4s infinite; }
.authx .comet__line { position: relative; display: block; width: 150px; height: 2px; background: linear-gradient(90deg, transparent, rgba(139, 92, 246, .85)); border-radius: 2px; }
.authx .comet__head { position: absolute; right: -3px; top: -3px; width: 8px; height: 8px; border-radius: 50%; background: #fff; box-shadow: 0 0 14px 3px rgba(139, 92, 246, .8); }
.authx .twinkle { position: absolute; border-radius: 50%; animation: authTwinkle 3.8s ease-in-out infinite; }
.authx .orbit { position: absolute; top: 30%; left: 44%; width: 120px; height: 120px; animation: authOrbit 24s linear infinite; }
.authx .orbit__a { position: absolute; top: 0; left: 50%; width: 7px; height: 7px; border-radius: 50%; background: #8b5cf6; box-shadow: 0 0 10px rgba(139, 92, 246, .7); opacity: .55; }
.authx .orbit__b { position: absolute; bottom: 0; left: 50%; width: 5px; height: 5px; border-radius: 50%; background: #f59e0b; box-shadow: 0 0 9px rgba(245, 158, 11, .6); opacity: .5; }
/* Glyph karir DIKELOMPOKKAN di tengah panggung. display:block WAJIB —
   menimpa `.authx .bi { display:contents }` yang kalau tidak akan mematikan
   position:absolute sehingga ikon nyangkut di pojok kiri-atas. */
.authx .glyph { position: absolute; display: block; line-height: 1; }
/* ── Roket terbang + jejak asap (responsif via vw/vh + clamp) ── */
.authx .rocket { position: absolute; top: 0; left: 0; width: clamp(22px, 2.8vw, 34px); height: clamp(22px, 2.8vw, 34px); animation: authRocketFly 16s linear -5s infinite; will-change: transform; pointer-events: none; }
.authx .rocket__ico { display: block; font-size: clamp(22px, 2.8vw, 34px); line-height: 1; color: #7c3aed; filter: drop-shadow(0 3px 9px rgba(124, 58, 237, .4)); transform: scaleX(-1); } /* hidung menghadap kiri-atas (arah terbang dari kanan) */
.authx .rocket__smoke { position: absolute; bottom: 2px; right: 2px; width: 9px; height: 9px; border-radius: 50%; background: radial-gradient(circle, rgba(199, 187, 253, .8), rgba(199, 187, 253, 0) 70%); animation: authSmoke 1.6s ease-out infinite; }
.authx .rocket__smoke--1 { animation-delay: -.1s; }
.authx .rocket__smoke--2 { animation-delay: -.5s; }
.authx .rocket__smoke--3 { animation-delay: -.9s; }
.authx .rocket__smoke--4 { animation-delay: -1.3s; }
.authx .glyph--1 { top: 30%; left: 42%; color: rgba(99, 102, 241, .28); font-size: 26px; animation: authGlyph 8s ease-in-out -2s infinite; }
.authx .glyph--2 { top: 52%; left: 48%; color: rgba(139, 92, 246, .26); font-size: 30px; animation: authGlyph 9.5s ease-in-out -5s infinite; }
.authx .glyph--3 { top: 40%; left: 45%; color: rgba(245, 158, 11, .28); font-size: 22px; animation: authGlyph 7.5s ease-in-out -3.5s infinite; }
.authx .halo { position: absolute; top: 38%; left: 33%; width: 220px; height: 220px; border-radius: 50%; }
.authx .halo--1 { border: 1.5px solid rgba(99, 102, 241, .3); animation: authRingGrow 5s ease-out infinite; }
.authx .halo--2 { border: 1.5px solid rgba(139, 92, 246, .28); animation: authRingGrow 5s ease-out 2.5s infinite; }
.authx .cloud { position: absolute; left: 0; animation-name: authCloudDrift; animation-timing-function: linear; animation-iteration-count: infinite; }
.authx .cloud__body { position: relative; display: block; width: 150px; height: 44px; border-radius: 44px; background: rgba(255, 255, 255, .82); filter: blur(1px); box-shadow: 0 8px 26px rgba(99, 102, 241, .08); }
.authx .cloud__p1 { position: absolute; top: -20px; left: 34px; width: 56px; height: 56px; border-radius: 50%; background: rgba(255, 255, 255, .82); }
.authx .cloud__p2 { position: absolute; top: -30px; left: 70px; width: 44px; height: 44px; border-radius: 50%; background: rgba(255, 255, 255, .82); }
.authx .mist { position: absolute; top: 70%; left: 20%; width: 44vw; height: 180px; border-radius: 50%; background: radial-gradient(ellipse, rgba(165, 180, 252, .16), transparent 70%); filter: blur(30px); animation: authDriftX 34s ease-in-out infinite; }

/* ═══ TOPBAR ═══ */
.authx .topbar { position: relative; z-index: 3; padding: clamp(12px, 2.4vh, 34px) clamp(20px, 4vw, 64px); display: flex; align-items: center; justify-content: space-between; gap: 20px; flex-wrap: wrap; flex-shrink: 0; animation: authRise .7s cubic-bezier(0.22, 1, 0.36, 1) .05s both; }
.authx .mark-login { display: flex; align-items: center; gap: 14px; text-decoration: none; color: inherit; }
.authx .mark-login__logo { position: relative; overflow: hidden; display: grid; place-items: center; width: 52px; height: 52px; border-radius: 15px; background: linear-gradient(150deg, #fff, #f1f0ff); border: 1px solid rgba(255, 255, 255, 0.9); box-shadow: 0 10px 24px -8px rgba(99, 102, 241, 0.32), inset 0 1px 0 rgba(255, 255, 255, 0.9); transition: transform 0.35s cubic-bezier(0.34, 1.56, 0.64, 1); }
.authx .mark-login__logo::after { content: ''; position: absolute; top: 0; left: 0; width: 60%; height: 100%; background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.85), transparent); transform: translateX(-180%) skewX(-18deg); animation: authSheen 4.5s ease-in-out 0.5s infinite; pointer-events: none; }
.authx .brand-logo { position: relative; z-index: 1; }
.authx .mark-login:hover .mark-login__logo { transform: translateY(-2px) rotate(-3deg) scale(1.05); }
.authx .brand-logo { height: 34px; width: auto; object-fit: contain; }
.authx .mark-login-text { line-height: 1.1; }
.authx .mark-login b { display: block; font-weight: 800; font-size: 19px; letter-spacing: -0.02em; color: #0f172a; }
.authx .mark-login small { display: block; font-size: 11px; font-weight: 600; color: #94a3b8; letter-spacing: 0.22em; margin-top: 2px; }
.authx .top-right { display: flex; align-items: center; gap: clamp(16px, 2vw, 28px); flex-wrap: wrap; }
.authx .top-link { font-size: 14px; color: #475569; text-decoration: none; font-weight: 600; display: inline-flex; align-items: center; gap: 8px; transition: color 0.25s ease, transform 0.25s ease; }
.authx .top-link:hover { color: var(--indigo); transform: translateY(-1px); }
.authx .v-pill { display: inline-flex; align-items: center; gap: 9px; padding: 11px 18px; border-radius: 14px; background: rgba(255, 255, 255, 0.72); backdrop-filter: blur(14px); -webkit-backdrop-filter: blur(14px); border: 1px solid rgba(255, 255, 255, 0.85); box-shadow: 0 8px 24px rgba(15, 23, 42, 0.06); font-size: 13px; }
.authx .pill-ico { color: var(--indigo-2); font-size: 15px; }
.authx .pill-txt { color: #475569; font-weight: 600; }
.authx .pill-div { color: #cbd5e1; }
.authx .pill-brand { color: var(--indigo); font-weight: 800; }

/* ═══ STAGE ═══ */
.authx .stage-grid { position: relative; z-index: 1; flex: 1 0 auto; display: flex; flex-wrap: wrap; align-items: center; justify-content: center; gap: clamp(24px, 4vw, 76px); width: 100%; max-width: 1400px; margin: 0 auto; padding: clamp(12px, 2vh, 40px) clamp(20px, 4vw, 64px); }
.authx .statement { flex: 1 1 420px; max-width: 640px; min-width: 280px; }
.authx .eyebrow { display: inline-flex; align-items: center; gap: 9px; padding: 8px 16px; border-radius: 999px; background: rgba(99, 102, 241, 0.1); border: 1px solid rgba(99, 102, 241, 0.22); color: var(--indigo); font-size: 13px; font-weight: 700; animation: authRise .8s cubic-bezier(0.22, 1, 0.36, 1) .18s both; }
.authx .eyebrow .dot { width: 9px; height: 9px; border-radius: 50%; background: var(--indigo-2); animation: authPulseDot 2.2s ease-out infinite; }
.authx .display { margin: clamp(10px, 1.8vh, 18px) 0 0; font-weight: 800; line-height: 1.04; letter-spacing: -0.03em; font-size: clamp(1.9rem, min(4.6vw, 6.4vh), 3.75rem); }
.authx .display__l1 { display: block; color: #0f172a; animation: authRise .85s cubic-bezier(0.22, 1, 0.36, 1) .28s both; }
.authx .display__l2 { display: block; margin-top: 2px; font-family: 'Playfair Display', Georgia, serif; font-style: italic; font-weight: 600; background: linear-gradient(100deg, #6366f1, #8b5cf6 34%, #3b82f6 62%, #64748b); -webkit-background-clip: text; background-clip: text; color: transparent; background-size: 220% auto; animation: authRise .85s cubic-bezier(0.22, 1, 0.36, 1) .42s both, authShimmer 6s linear .9s infinite; }
.authx .lede { margin: clamp(12px, 2vh, 26px) 0 0; max-width: 470px; color: #475569; font-size: clamp(14px, 1.4vw, 17px); line-height: 1.65; text-wrap: pretty; animation: authRise .85s cubic-bezier(0.22, 1, 0.36, 1) .56s both; }
.authx .hero-chips { display: flex; flex-wrap: wrap; gap: 10px; margin-top: clamp(14px, 2vh, 26px); animation: authRise .85s cubic-bezier(0.22, 1, 0.36, 1) .68s both; }
.authx .hero-chip { display: inline-flex; align-items: center; gap: 8px; padding: 9px 15px; border-radius: 12px; background: rgba(255, 255, 255, 0.66); border: 1px solid rgba(226, 232, 240, 0.9); font-size: 13px; font-weight: 600; color: #334155; }
.authx .sub-mobile { display: none; }

/* ═══ FLOATING GLASS CARD ═══ */
.authx .stage-side { flex: 0 1 460px; width: 100%; min-width: 280px; animation: authRiseScale .95s cubic-bezier(0.22, 1, 0.36, 1) .32s both; }
/* Kartu tinggi natural — ukuran isi mengecil sendiri (bukan di-scroll/clip). */
.authx .float-card { position: relative; border-radius: 30px; padding: clamp(20px, 2.4vw, 38px); background: rgba(255, 255, 255, 0.74); backdrop-filter: blur(20px) saturate(155%); -webkit-backdrop-filter: blur(20px) saturate(155%); border: 1.5px solid rgba(255, 255, 255, 0.9); box-shadow: 0 34px 84px rgba(15, 23, 42, 0.15), 0 6px 22px rgba(99, 102, 241, 0.08), inset 0 1px 0 rgba(255, 255, 255, 0.85); animation: authFloatCard 7s ease-in-out 1.3s infinite; }
.authx .float-card--center { text-align: center; }
.authx .card-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 14px; margin-bottom: 26px; }
.authx .card-head__l { min-width: 0; }
.authx .card-head h3 { margin: 0; font-weight: 800; letter-spacing: -0.02em; font-size: clamp(1.55rem, 2.4vw, 1.95rem); color: #0f172a; }
.authx .card-head__sub { margin: 6px 0 0; font-size: 13.5px; color: #64748b; }
.authx .card-head .tag { flex-shrink: 0; padding: 8px 12px; border-radius: 11px; background: rgba(99, 102, 241, 0.12); color: var(--indigo); font-size: 10.5px; font-weight: 800; letter-spacing: 0.13em; }

/* FORM CONTROLS */
.authx .field { margin-bottom: 16px; }
.authx .field label { display: flex; align-items: center; justify-content: space-between; font-size: 13px; font-weight: 600; color: #475569; margin-bottom: 9px; }
.authx .field label .lbl-text { display: flex; align-items: center; gap: 8px; }
.authx .field label .lbl-text i { font-size: 14px; color: var(--violet); }
.authx .input-wrap { position: relative; display: flex; align-items: center; background: rgba(255, 255, 255, 0.7); border: 1.5px solid rgba(226, 232, 240, 0.95); border-radius: 16px; transition: all 0.28s cubic-bezier(0.22, 1, 0.36, 1); overflow: hidden; }
.authx .input-wrap:focus-within { border-color: var(--indigo-2); background: #fff; box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.14), 0 10px 26px rgba(99, 102, 241, 0.12); transform: translateY(-1px); }
.authx .input-wrap.is-error { border-color: #ef4444; box-shadow: 0 0 0 4px rgba(239, 68, 68, 0.12); }
.authx .input-wrap input { flex: 1; border: 0; background: transparent; padding: 15px 16px; font: inherit; font-size: 15px; color: #0f172a; outline: none; border-radius: inherit; }
.authx .input-wrap input::placeholder { color: #94a3b8; }
.authx .input-wrap input:-webkit-autofill, .authx .input-wrap input:-webkit-autofill:hover, .authx .input-wrap input:-webkit-autofill:focus { -webkit-box-shadow: 0 0 0 1000px #fff inset !important; -webkit-text-fill-color: #0f172a !important; caret-color: #0f172a; transition: background-color 50000s ease-in-out 0s; }
.authx .nik-count { flex: 0 0 auto; margin-right: 0.9rem; font-size: 0.72rem; font-weight: 800; color: #94a3b8; font-variant-numeric: tabular-nums; }
.authx .nik-count.is-ok { color: #059669; }
.authx .toggle-eye { background: none; border: 0; padding: 7px; margin-right: 6px; color: #64748b; cursor: pointer; font-size: 18px; border-radius: 10px; display: flex; align-items: center; transition: color 0.2s ease, background 0.2s ease; }
.authx .toggle-eye:hover { color: var(--indigo); background: rgba(99, 102, 241, 0.1); }
.authx .help { margin: 6px 2px 0; font-size: 12px; color: #dc2626; }
.authx .help.help--muted { color: #94a3b8; }
.authx .help-center { text-align: center; }
.authx .forgot-link { border: none; background: none; padding: 0; color: var(--indigo); font: inherit; font-weight: 700; font-size: 13px; cursor: pointer; }
.authx .forgot-link:hover { color: var(--violet); }
.authx .step-hint { margin: 0 0 16px; font-size: 13.5px; line-height: 1.6; color: #64748b; }
.authx .step-hint b { color: #0f172a; font-weight: 700; }

/* KTP intro / ok */
.authx .ktp-intro { display: flex; align-items: flex-start; gap: 0.85rem; padding: 0.9rem 1rem; margin-bottom: 1.15rem; border-radius: 14px; background: linear-gradient(135deg, rgba(139, 92, 246, 0.08), rgba(99, 102, 241, 0.06)); border: 1px solid rgba(99, 102, 241, 0.16); }
.authx .ktp-intro__ico { flex: 0 0 auto; width: 2.4rem; height: 2.4rem; border-radius: 12px; display: grid; place-items: center; font-size: 1.15rem; color: #fff; background: linear-gradient(135deg, #8b5cf6, #6366f1); box-shadow: 0 8px 18px rgba(99, 102, 241, 0.3); }
.authx .ktp-intro__title { font-size: 0.9rem; font-weight: 800; color: #1e1b4b; }
.authx .ktp-intro__sub { font-size: 0.76rem; line-height: 1.5; color: #6b6597; margin-top: 0.15rem; }
.authx .ktp-ok { display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem 0.9rem; margin-bottom: 1.15rem; border-radius: 14px; background: rgba(16, 185, 129, 0.08); border: 1px solid rgba(16, 185, 129, 0.22); }
.authx .ktp-ok__ico { flex: 0 0 auto; font-size: 1.35rem; color: #10b981; display: grid; place-items: center; }
.authx .ktp-ok__body { flex: 1; min-width: 0; display: flex; flex-direction: column; }
.authx .ktp-ok__label { font-size: 0.68rem; font-weight: 800; letter-spacing: 0.08em; text-transform: uppercase; color: #059669; }
.authx .ktp-ok__nik { font-size: 0.92rem; font-weight: 800; color: #0f172a; font-variant-numeric: tabular-nums; letter-spacing: 0.04em; }
.authx .ktp-ok__edit { flex: 0 0 auto; appearance: none; border: 1px solid rgba(16, 185, 129, 0.3); background: #fff; color: #059669; font-size: 0.74rem; font-weight: 700; padding: 0.4rem 0.7rem; border-radius: 9px; cursor: pointer; display: inline-flex; align-items: center; gap: 0.3rem; transition: background 0.15s ease; }
.authx .ktp-ok__edit:hover { background: rgba(16, 185, 129, 0.1); }

/* Tombol utama — dengan sheen sweep */
.authx .btn-login { position: relative; overflow: hidden; width: 100%; margin-top: 6px; padding: 16px; border: 0; cursor: pointer; border-radius: 16px; background: linear-gradient(135deg, #8b5cf6 0%, #6366f1 55%, #4f46e5 100%); color: #fff; font: inherit; font-weight: 700; font-size: 15px; display: flex; align-items: center; justify-content: center; gap: 10px; box-shadow: 0 16px 32px rgba(99, 102, 241, 0.36); transition: all 0.3s cubic-bezier(0.22, 1, 0.36, 1); }
.authx .btn-login::after { content: ''; position: absolute; top: 0; left: 0; height: 100%; width: 55%; background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.5), transparent); transform: translateX(-170%) skewX(-18deg); animation: authSheen 5s ease-in-out 1.6s infinite; pointer-events: none; }
.authx .btn-login:hover:not(:disabled) { transform: translateY(-2px); box-shadow: 0 22px 44px rgba(99, 102, 241, 0.44); filter: brightness(1.05); }
.authx .btn-login:active:not(:disabled) { transform: translateY(0); }
.authx .btn-login:disabled { opacity: 0.55; cursor: not-allowed; box-shadow: none; }
.authx .spinner { width: 18px; height: 18px; border: 2.5px solid rgba(255, 255, 255, 0.45); border-top-color: #fff; border-radius: 50%; animation: authSpin 0.7s linear infinite; }
.authx .spinner--indigo { border-color: rgba(99, 102, 241, 0.25); border-top-color: var(--indigo); }

/* Switch / footer kartu */
.authx .switch-row { margin: 20px 0 0; text-align: center; font-size: 14px; font-weight: 500; color: #64748b; }
.authx .switch-link { border: none; background: transparent; padding: 0; color: var(--indigo); font: inherit; font-weight: 800; font-size: 14px; cursor: pointer; text-decoration: none; }
.authx .switch-link:hover { color: var(--violet); text-decoration: underline; }
.authx .switch-link.as-btn:disabled { opacity: 0.5; cursor: not-allowed; }
.authx .switch-muted { color: #94a3b8; font-weight: 600; }
.authx .verif-pending { margin: 12px 0 0; padding: 10px 14px; text-align: center; font-size: 12.5px; font-weight: 500; color: #64748b; background: rgba(99, 102, 241, 0.08); border: 1px solid rgba(99, 102, 241, 0.18); border-radius: 12px; }
.authx .verif-pending i { color: var(--indigo); }
.authx .verif-pending .switch-link:disabled { opacity: 0.6; cursor: not-allowed; }
.authx .ver-row { margin-top: 22px; padding-top: 18px; border-top: 1px solid rgba(226, 232, 240, 0.85); display: flex; justify-content: space-between; align-items: center; gap: 10px; font-size: 12px; color: #94a3b8; }

/* LENCANA VERSI — kecil, monospace, tidak menarik perhatian.
   Angka ini dibaca ketika ada yang salah, bukan saat semuanya baik-baik saja;
   jadi ia harus mudah DITEMUKAN tanpa pernah ikut meminta dibaca. */
.authx .ver-tag {
    flex: 0 0 auto;
    padding: 2px 8px;
    border-radius: 999px;
    border: 1px solid rgba(226, 232, 240, 0.9);
    background: rgba(248, 250, 252, 0.9);
    font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
    font-size: 10.5px;
    font-weight: 700;
    letter-spacing: 0.02em;
    color: #94a3b8;
    white-space: nowrap;
}
.authx .ver-row b { color: #0f172a; font-weight: 700; }
.authx .ver-row span b { color: var(--indigo); }

/* OTP */
.authx .otp-boxes { display: flex; gap: 10px; justify-content: center; margin: 8px 0 4px; }
.authx .otp-box { width: 48px; height: 56px; text-align: center; font: inherit; font-weight: 800; font-size: 24px; color: #0f172a; background: rgba(255, 255, 255, 0.7); border: 1.5px solid rgba(226, 232, 240, 0.95); border-radius: 14px; outline: none; transition: border-color 0.2s ease, box-shadow 0.2s ease, transform 0.15s ease; }
.authx .otp-box:focus { border-color: var(--indigo-2); box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.14); transform: translateY(-1px); }
.authx .otp-boxes.is-error .otp-box { border-color: #ef4444; box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.1); }
.authx .otp-meta { margin: 14px 0 4px; text-align: center; font-size: 12.5px; font-weight: 500; color: #64748b; }
.authx .otp-meta i { color: var(--indigo); margin-right: 4px; }
.authx .otp-expired { color: #dc2626; }
.authx .otp-expired i { color: #dc2626; }

/* Catatan tunggu */
.authx .wait-note { display: flex; align-items: flex-start; gap: 9px; margin: 12px auto 4px; max-width: 440px; padding: 11px 14px; border-radius: 13px; background: rgba(245, 158, 11, 0.09); border: 1px solid rgba(245, 158, 11, 0.24); font-size: 12.5px; line-height: 1.55; color: #92660a; text-align: left; }
.authx .wait-note .bi { flex: 0 0 auto; margin-top: 1px; color: #d97706; font-size: 15px; }
.authx .wait-note b { color: #7a5408; }

/* Konten hasil/menunggu verifikasi */
.authx .icon-badge { position: relative; width: 74px; height: 74px; border-radius: 22px; display: grid; place-items: center; margin: 0 auto 18px; }
.authx .icon-badge--wait { background: linear-gradient(135deg, #8b5cf6, #6366f1); box-shadow: 0 16px 36px -8px rgba(99, 102, 241, 0.5); }
.authx .icon-badge--ok { background: linear-gradient(135deg, #10b981, #059669); box-shadow: 0 16px 36px -8px rgba(16, 185, 129, 0.55); animation: authPop 0.5s cubic-bezier(0.34, 1.56, 0.64, 1) both; }
.authx .icon-badge--warn { background: linear-gradient(135deg, #f59e0b, #d97706); box-shadow: 0 16px 36px -8px rgba(245, 158, 11, 0.5); }
.authx .icon-badge--err { background: linear-gradient(135deg, #ef4444, #dc2626); box-shadow: 0 16px 36px -8px rgba(239, 68, 68, 0.5); }
.authx .ping { position: absolute; inset: -6px; border-radius: 26px; border: 2px solid rgba(139, 92, 246, 0.5); animation: authPing 1.8s cubic-bezier(0, 0, 0.2, 1) infinite; }
.authx .eyebrow-txt { margin: 0; font-size: 11px; font-weight: 800; letter-spacing: 0.16em; color: var(--violet); }
.authx .float-card--center h1 { margin: 8px 0 0; font-size: 25px; font-weight: 800; letter-spacing: -0.02em; color: #0f172a; }
.authx .desc { margin: 12px 0 0; font-size: 14.5px; line-height: 1.7; color: #64748b; }
.authx .desc b { color: #0f172a; }
.authx .email-chip { display: inline-block; margin-top: 6px; padding: 5px 14px; background: rgba(99, 102, 241, 0.1); border: 1px solid rgba(99, 102, 241, 0.2); border-radius: 999px; color: var(--indigo) !important; font-weight: 700; font-size: 13.5px; }
.authx .desc-sub { margin: 14px 0 0; font-size: 13px; line-height: 1.65; color: #64748b; }
.authx .desc-sub b { color: #0f172a; }
.authx .live-row { margin: 20px 0 0; display: inline-flex; align-items: center; gap: 8px; flex-wrap: wrap; justify-content: center; padding: 10px 16px; background: rgba(16, 185, 129, 0.08); border: 1px solid rgba(16, 185, 129, 0.2); border-radius: 999px; font-size: 12.5px; color: #0f766e; font-weight: 600; }
.authx .live-row b { color: #0f766e; }
.authx .live-row b.danger { color: #dc2626; }
.authx .live-sep { color: rgba(15, 118, 110, 0.4); }
.authx .dot-live { width: 8px; height: 8px; border-radius: 50%; background: #10b981; animation: authPulse 1.5s infinite; }
.authx .notice { margin: 16px 0 0; display: flex; align-items: center; gap: 8px; padding: 11px 14px; border-radius: 12px; font-size: 12.5px; text-align: left; }
.authx .notice--ok { background: rgba(16, 185, 129, 0.1); color: #059669; }
.authx .notice--err { background: rgba(239, 68, 68, 0.1); color: #dc2626; }
.authx .notice span { flex: 1; }
.authx .btn-main { position: relative; overflow: hidden; width: 100%; margin-top: 18px; padding: 15px 20px; border: 0; cursor: pointer; border-radius: 16px; background: linear-gradient(135deg, #8b5cf6, #6366f1 55%, #4f46e5); color: #fff; font: inherit; font-weight: 700; font-size: 14.5px; text-decoration: none; display: flex; align-items: center; justify-content: center; gap: 10px; box-shadow: 0 16px 32px rgba(99, 102, 241, 0.36); transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1), box-shadow 0.3s ease, opacity 0.3s ease; }
.authx .btn-main:hover:not(:disabled) { transform: translateY(-2px); box-shadow: 0 22px 44px rgba(99, 102, 241, 0.44); }
.authx .btn-main:disabled { opacity: 0.55; cursor: not-allowed; box-shadow: none; }
.authx .paste-toggle { margin: 14px 0 0; width: 100%; background: none; border: 0; cursor: pointer; font: inherit; font-weight: 600; font-size: 12.5px; color: var(--indigo); display: flex; align-items: center; justify-content: center; gap: 6px; }
.authx .paste-toggle:hover { color: var(--violet); }
.authx .paste-box { margin-top: 14px; padding: 16px; background: rgba(99, 102, 241, 0.05); border: 1px solid rgba(99, 102, 241, 0.14); border-radius: 16px; text-align: left; }
.authx .paste-hint { margin: 0 0 10px; font-size: 12px; line-height: 1.55; color: #64748b; }
.authx .paste-hint code { background: rgba(99, 102, 241, 0.12); padding: 1px 5px; border-radius: 5px; font-size: 11px; color: var(--indigo); }
.authx .paste-field { display: flex; align-items: center; gap: 8px; padding: 12px 14px; background: #fff; border: 1.5px solid rgba(226, 232, 240, 0.95); border-radius: 13px; transition: border-color 0.2s ease, box-shadow 0.2s ease; }
.authx .paste-field:focus-within { border-color: var(--indigo-2); box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.12); }
.authx .paste-field.err { border-color: #ef4444; box-shadow: 0 0 0 4px rgba(239, 68, 68, 0.12); }
.authx .paste-field i { color: #8b5cf6; font-size: 16px; }
.authx .paste-field input { flex: 1; border: 0; outline: none; background: none; font: inherit; font-size: 13px; color: #0f172a; }
.authx .paste-field input::placeholder { color: #94a3b8; }
.authx .paste-err { margin: 8px 0 0; font-size: 12px; color: #dc2626; }
.authx .btn-ghost { width: 100%; margin-top: 12px; padding: 12px; cursor: pointer; border-radius: 13px; border: 1.5px solid var(--indigo); background: #fff; color: var(--indigo); font: inherit; font-weight: 700; font-size: 13px; display: flex; align-items: center; justify-content: center; gap: 8px; transition: background 0.2s ease, color 0.2s ease; }
.authx .btn-ghost:hover:not(:disabled) { background: var(--indigo); color: #fff; }
.authx .btn-ghost:disabled { opacity: 0.6; cursor: not-allowed; }
.authx .redir-row { margin: 20px 0 0; display: flex; align-items: center; justify-content: center; gap: 10px; font-size: 13px; color: #64748b; font-weight: 600; }
.authx .countdown { margin: 18px 0 0; display: flex; align-items: center; gap: 12px; padding: 12px 16px; border-radius: 14px; background: rgba(99, 102, 241, 0.07); border: 1px solid rgba(99, 102, 241, 0.16); text-align: left; font-size: 12.5px; line-height: 1.5; color: #64748b; }
.authx .countdown-num { flex: 0 0 auto; width: 40px; height: 40px; border-radius: 12px; display: grid; place-items: center; font-weight: 800; font-size: 18px; color: #fff; background: linear-gradient(135deg, var(--indigo-2), var(--violet)); font-variant-numeric: tabular-nums; }
.authx .countdown b { color: #0f172a; }
.authx .feedback { margin: 12px 0 0; display: inline-flex; align-items: center; gap: 7px; font-size: 12.5px; font-weight: 600; }
.authx .feedback--ok { color: #059669; }
.authx .feedback--err { color: #dc2626; }
.authx .alt-row { margin: 16px 0 0; text-align: center; font-size: 13px; color: #64748b; }
.authx .alt-link { color: var(--indigo); font-weight: 700; text-decoration: none; }
.authx .alt-link:hover { color: var(--violet); text-decoration: underline; }
.authx .collapse-enter-active, .authx .collapse-leave-active { transition: opacity 0.25s ease, transform 0.25s ease; }
.authx .collapse-enter-from, .authx .collapse-leave-to { opacity: 0; transform: translateY(-6px); }

/* ═══ FOOTER ═══ */
.authx .subs { flex-shrink: 0; position: relative; z-index: 1; margin: clamp(10px, 1.6vw, 30px); margin-top: clamp(6px, 1vh, 16px); padding: clamp(12px, 1.6vh, 24px) clamp(22px, 4vw, 48px); border-radius: 24px; background: rgba(255, 255, 255, 0.68); backdrop-filter: blur(16px); -webkit-backdrop-filter: blur(16px); border: 1px solid rgba(255, 255, 255, 0.85); box-shadow: 0 14px 40px rgba(15, 23, 42, 0.06); display: flex; align-items: center; justify-content: space-between; gap: 24px; flex-wrap: wrap; animation: authRise .8s cubic-bezier(0.22, 1, 0.36, 1) .8s both; }
.authx .subs .lbl { font-size: 12px; font-weight: 700; letter-spacing: 0.18em; color: #94a3b8; text-transform: uppercase; }
.authx .subs .logos { display: flex; align-items: center; gap: clamp(16px, 2.5vw, 32px); flex-wrap: wrap; }
/* Logo anak perusahaan: kinclong (warna penuh) + sheen sweep seperti tombol. */
.authx .logo-wrap { position: relative; overflow: hidden; display: inline-flex; align-items: center; justify-content: center; height: 34px; border-radius: 10px; padding: 4px 6px; transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1); }
.authx .logo-wrap::after { content: ''; position: absolute; top: 0; left: 0; width: 55%; height: 100%; background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.9), transparent); transform: translateX(-190%) skewX(-18deg); animation: authSheen 5s ease-in-out infinite; pointer-events: none; }
.authx .logo-wrap--3 img { width: 100px !important; }
.authx .logo-wrap--5 img { width: 83px !important; }
.authx .logo-wrap--4 img { width: 130px !important; }
.authx .logo-wrap--1::after { animation-delay: 0.4s; }
.authx .logo-wrap--2::after { animation-delay: 1.9s; }
.authx .logo-wrap--3::after { animation-delay: 3.4s; }
.authx .logo-wrap--4::after { animation-delay: 4.9s; }
.authx .logo-wrap--5::after { animation-delay: 6.4s; }
.authx .logo-wrap:hover { transform: scale(1.08) translateY(-2px); }
.authx .subs .logos img { position: relative; z-index: 1; height: auto; max-height: 30px; width: 80px; filter: none; opacity: 1; object-fit: contain; }
.authx .subs .logos .dv { width: 1px; height: 26px; background: #e2e8f0; }

/* TOAST */
/* Lapis toast bersama — lihat --wca-z-toast di evo-theme.css. Halaman auth
   punya lembar OTP & dialog sesi kedaluwarsa; pesan "kata sandi salah" tidak
   boleh kalah oleh salah satunya. */
.authx .v3-toast { position: fixed; top: 20px; right: 20px; z-index: var(--wca-z-toast, 100000); display: flex; align-items: center; gap: 12px; max-width: min(92vw, 380px); padding: 14px 16px; border-radius: 14px; font-size: 0.9rem; font-weight: 500; color: #fff; box-shadow: 0 18px 40px -12px rgba(0, 0, 0, 0.35); backdrop-filter: blur(10px); }
.authx .v3-toast--error { background: linear-gradient(120deg, #ef4444, #dc2626); }
.authx .v3-toast--info { background: linear-gradient(120deg, var(--indigo-2), var(--violet)); }
.authx .v3-toast span { flex: 1; }
.authx .v3-toast__close { border: none; background: rgba(255, 255, 255, 0.18); color: #fff; width: 26px; height: 26px; border-radius: 8px; cursor: pointer; display: grid; place-items: center; }
.v3-toast-enter-active, .v3-toast-leave-active { transition: opacity 0.3s ease, transform 0.3s ease; }
.v3-toast-enter-from, .v3-toast-leave-to { opacity: 0; transform: translateX(40px); }

/* ═══ ANIMASI ═══ */
@keyframes authRise { from { opacity: 0; transform: translateY(24px); } to { opacity: 1; transform: translateY(0); } }
@keyframes authRiseScale { from { opacity: 0; transform: translateY(34px) scale(.96); } to { opacity: 1; transform: translateY(0) scale(1); } }
@keyframes authFloatCard { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-10px); } }
@keyframes authAurora { 0%, 100% { transform: translate(0, 0) scale(1); } 33% { transform: translate(6%, -5%) scale(1.09); } 66% { transform: translate(-5%, 4%) scale(.94); } }
@keyframes authFloatUp { 0% { transform: translateY(0) scale(.5); opacity: 0; } 12% { opacity: .85; } 80% { opacity: .55; } 100% { transform: translateY(-90vh) scale(1); opacity: 0; } }
@keyframes authShimmer { to { background-position: 220% center; } }
@keyframes authGridPan { to { background-position: 54px 54px; } }
@keyframes authSpin { to { transform: rotate(360deg); } }
@keyframes authSheen { 0% { transform: translateX(-170%) skewX(-18deg); } 24% { transform: translateX(340%) skewX(-18deg); } 100% { transform: translateX(340%) skewX(-18deg); } }
@keyframes authPulseDot { 0%, 100% { box-shadow: 0 0 0 0 rgba(99, 102, 241, .55); } 70% { box-shadow: 0 0 0 8px rgba(99, 102, 241, 0); } }
@keyframes authRingGrow { 0% { transform: scale(.85); opacity: .55; } 100% { transform: scale(1.6); opacity: 0; } }
@keyframes authBalloonUp { 0% { transform: translateY(114vh); } 100% { transform: translateY(-26vh); } }
@keyframes authSway { 0%, 100% { transform: translateX(-11px) rotate(-4deg); } 50% { transform: translateX(11px) rotate(4deg); } }
@keyframes authTwinkle { 0%, 100% { opacity: .12; transform: scale(.7); } 50% { opacity: .95; transform: scale(1.2); } }
@keyframes authOrbit { to { transform: rotate(360deg); } }
@keyframes authDriftX { 0%, 100% { transform: translateX(-7%); } 50% { transform: translateX(7%); } }
@keyframes authGlyph { 0%, 100% { transform: translateY(0) rotate(-6deg); } 50% { transform: translateY(-18px) rotate(6deg); } }
@keyframes authStepGlow { 0%, 100% { opacity: .18; box-shadow: 0 0 0 rgba(99, 102, 241, 0); } 50% { opacity: .9; box-shadow: 0 0 18px rgba(99, 102, 241, .55); } }
@keyframes authComet { 0% { transform: translate(-14vw, 14vh) rotate(26deg); opacity: 0; } 5% { opacity: .95; } 20% { opacity: 0; } 100% { transform: translate(96vw, -34vh) rotate(26deg); opacity: 0; } }
@keyframes authPathFlow { to { background-position: 0 -400px; } }
@keyframes authCloudDrift { from { transform: translateX(-42vw); } to { transform: translateX(122vw); } }
@keyframes authPop { from { transform: scale(0.6); opacity: 0; } to { transform: scale(1); opacity: 1; } }
@keyframes authPing { 0% { transform: scale(0.9); opacity: 0.8; } 100% { transform: scale(1.25); opacity: 0; } }
@keyframes authPulse { 0% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.5); } 70% { box-shadow: 0 0 0 7px rgba(16, 185, 129, 0); } 100% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); } }
/* Terbang dari KANAN-bawah, MELENGKUNG naik, ke KIRI-atas (titik tengah membusur ke atas). */
@keyframes authRocketFly {
    0% { transform: translate(96vw, 96vh) rotate(8deg); opacity: 0; }
    8% { opacity: 1; }
    30% { transform: translate(66vw, 48vh) rotate(2deg); }
    55% { transform: translate(40vw, 18vh) rotate(-2deg); }
    78% { transform: translate(16vw, 3vh) rotate(-6deg); }
    90% { opacity: 1; }
    100% { transform: translate(-16vw, -18vh) rotate(-10deg); opacity: 0; }
}
@keyframes authSmoke { 0% { transform: translate(0, 0) scale(.4); opacity: .85; } 100% { transform: translate(18px, 18px) scale(2.1); opacity: 0; } }

/* ═══ POSISI/TEMPO ELEMEN AMBIENT PER BUTIR (semua di CSS, tanpa inline style) ═══ */
/* Partikel naik */
/* Delay NEGATIF → seluruh elemen sudah bergerak sejak halaman dibuka (tak ada jeda). */
.authx .pt--1 { left: 6%; width: 8px; height: 8px; background: #8b5cf6; box-shadow: 0 0 12px rgba(139, 92, 246, .7); animation-duration: 15s; animation-delay: -2s; }
.authx .pt--2 { left: 13%; width: 6px; height: 6px; background: #6366f1; box-shadow: 0 0 10px rgba(99, 102, 241, .7); animation-duration: 19s; animation-delay: -8s; }
.authx .pt--3 { left: 19%; width: 10px; height: 10px; background: #f59e0b; box-shadow: 0 0 12px rgba(245, 158, 11, .6); animation-duration: 22s; animation-delay: -14s; }
.authx .pt--4 { left: 27%; width: 6px; height: 6px; background: #8b5cf6; box-shadow: 0 0 10px rgba(139, 92, 246, .6); animation-duration: 17s; animation-delay: -5s; }
.authx .pt--5 { left: 34%; width: 7px; height: 7px; background: #6366f1; box-shadow: 0 0 11px rgba(99, 102, 241, .6); animation-duration: 21s; animation-delay: -11s; }
.authx .pt--6 { left: 42%; width: 5px; height: 5px; background: #a5b4fc; box-shadow: 0 0 8px rgba(165, 180, 252, .7); animation-duration: 16s; animation-delay: -3s; }
.authx .pt--7 { left: 49%; width: 9px; height: 9px; background: #8b5cf6; box-shadow: 0 0 12px rgba(139, 92, 246, .55); animation-duration: 24s; animation-delay: -18s; }
.authx .pt--8 { left: 55%; width: 6px; height: 6px; background: #f59e0b; box-shadow: 0 0 10px rgba(245, 158, 11, .6); animation-duration: 18s; animation-delay: -7s; }
.authx .pt--9 { left: 60%; width: 7px; height: 7px; background: #6366f1; box-shadow: 0 0 10px rgba(99, 102, 241, .6); animation-duration: 20s; animation-delay: -13s; }
.authx .pt--10 { left: 15%; width: 5px; height: 5px; background: #a5b4fc; box-shadow: 0 0 8px rgba(165, 180, 252, .7); animation-duration: 15s; animation-delay: -4s; }
.authx .pt--11 { left: 38%; width: 8px; height: 8px; background: #8b5cf6; box-shadow: 0 0 12px rgba(139, 92, 246, .6); animation-duration: 23s; animation-delay: -16s; }
.authx .pt--12 { left: 23%; width: 6px; height: 6px; background: #6366f1; box-shadow: 0 0 10px rgba(99, 102, 241, .6); animation-duration: 19s; animation-delay: -9s; }
.authx .pt--13 { left: 47%; width: 7px; height: 7px; background: #f59e0b; box-shadow: 0 0 10px rgba(245, 158, 11, .55); animation-duration: 17s; animation-delay: -6s; }
/* Balon */
.authx .balloon--1 { left: 9%; animation-duration: 15s; animation-delay: -6s; }
.authx .balloon--1 .balloon__sway { animation-duration: 6s; }
.authx .balloon--1 .balloon__body { width: 48px; height: 58px; opacity: .5; background: radial-gradient(circle at 34% 28%, rgba(255, 255, 255, .8), #a78bfa 52%, #7c3aed); }
.authx .balloon--2 { left: 21%; animation-duration: 18s; animation-delay: -11s; }
.authx .balloon--2 .balloon__sway { animation-duration: 7.5s; }
.authx .balloon--2 .balloon__body { width: 38px; height: 46px; opacity: .45; background: radial-gradient(circle at 34% 28%, rgba(255, 255, 255, .8), #818cf8 52%, #4f46e5); }
.authx .balloon--3 { left: 30%; animation-duration: 16s; animation-delay: -9s; }
.authx .balloon--3 .balloon__sway { animation-duration: 6.8s; }
.authx .balloon--3 .balloon__body { width: 42px; height: 52px; opacity: .42; background: radial-gradient(circle at 34% 28%, rgba(255, 255, 255, .85), #fbbf24 52%, #d97706); }
/* Anak tangga karir */
.authx .step--1 { left: 0; bottom: 0; background: #6366f1; animation-delay: -3s; }
.authx .step--2 { left: 26px; bottom: 20px; background: #7c6cf5; animation-delay: -2.6s; }
.authx .step--3 { left: 52px; bottom: 40px; background: #8b5cf6; animation-delay: -2.2s; }
.authx .step--4 { left: 78px; bottom: 60px; background: #a78bfa; animation-delay: -1.8s; }
.authx .step--5 { left: 104px; bottom: 80px; background: #f59e0b; animation-delay: -1.4s; }
/* Bintang berkelip */
.authx .twinkle--1 { top: 22%; left: 46%; width: 5px; height: 5px; background: #c4b5fd; box-shadow: 0 0 8px #a78bfa; animation-delay: -.2s; }
.authx .twinkle--2 { top: 34%; left: 52%; width: 4px; height: 4px; background: #fcd34d; box-shadow: 0 0 8px #f59e0b; animation-delay: -1.1s; }
.authx .twinkle--3 { top: 14%; left: 38%; width: 6px; height: 6px; background: #a5b4fc; box-shadow: 0 0 9px #6366f1; animation-delay: -2s; }
.authx .twinkle--4 { top: 44%; left: 30%; width: 4px; height: 4px; background: #c4b5fd; box-shadow: 0 0 7px #8b5cf6; animation-delay: -.7s; }
/* Awan — mulai sudah di dalam layar (delay negatif) */
.authx .cloud--1 { top: 12%; opacity: .6; animation-duration: 60s; animation-delay: -20s; }
.authx .cloud--2 { top: 30%; opacity: .42; animation-duration: 84s; animation-delay: -50s; }
.authx .cloud--2 .cloud__body { transform: scale(.7); }
.authx .cloud--3 { top: 6%; opacity: .5; animation-duration: 100s; animation-delay: -75s; }
.authx .cloud--3 .cloud__body { transform: scale(1.25); }

/* ═══ LAYAR PENDEK (laptop/landscape) — KECILKAN komponen agar muat tanpa scroll ═══ */
@media (max-height: 880px) {
    .authx .card-head { margin-bottom: 18px; }
    .authx .field { margin-bottom: 13px; }
    .authx .field label { margin-bottom: 7px; }
    .authx .input-wrap input { padding: 13px 15px; font-size: 14.5px; }
    .authx .ktp-intro { margin-bottom: .8rem; padding: .7rem .85rem; }
    .authx .ktp-ok { margin-bottom: .8rem; }
    .authx .btn-login { padding: 14px; }
    .authx .ver-row { margin-top: 16px; padding-top: 14px; }
    .authx .step-hint { margin-bottom: 13px; }
}
@media (max-height: 760px) {
    .authx .card-head { margin-bottom: 14px; }
    .authx .card-head h3 { font-size: clamp(1.4rem, 2.2vw, 1.7rem); }
    .authx .card-head__sub { font-size: 12.5px; margin-top: 4px; }
    .authx .field { margin-bottom: 11px; }
    .authx .field label { margin-bottom: 6px; font-size: 12px; }
    .authx .input-wrap input { padding: 12px 14px; font-size: 14px; }
    .authx .btn-login { padding: 13px; font-size: 14.5px; }
    .authx .switch-row { margin-top: 14px; }
    .authx .ver-row { margin-top: 13px; padding-top: 12px; }
    .authx .otp-box { height: 50px; }
    .authx .balloon, .authx .cloud { display: none; }
}
@media (max-height: 660px) {
    .authx .display { font-size: clamp(1.8rem, 6vh, 2.6rem); }
    .authx .lede { font-size: 13.5px; line-height: 1.5; }
    .authx .hero-chips { gap: 7px; margin-top: 12px; }
    .authx .hero-chip { padding: 7px 12px; font-size: 12px; }
    .authx .card-head__sub { display: none; }
    .authx .field { margin-bottom: 9px; }
    .authx .input-wrap input { padding: 11px 14px; }
    .authx .btn-login { padding: 12px; }
    .authx .topbar { padding-top: 12px; padding-bottom: 12px; }
}
/* Hanya di layar SANGAT pendek (≤540px) statement diringkas — jaga agar tak overflow. */
@media (max-height: 540px) {
    .authx .lede, .authx .hero-chips { display: none; }
}

/* ═══ MOBILE — FOKUS KARTU: branding statement disembunyikan, tak semuanya tampil ═══ */
@media (max-width: 767px) {
    .authx { min-height: 100svh; }
    .authx .topbar { padding: 12px 16px; }
    .authx .top-link { display: none; }
    .authx .mark-login__logo { width: 42px; height: 42px; border-radius: 12px; }
    .authx .brand-logo { height: 26px; }
    .authx .mark-login b { font-size: 16px; }
    .authx .mark-login small { font-size: 9.5px; letter-spacing: 0.16em; }
    .authx .v-pill { padding: 7px 11px; font-size: 10.5px; gap: 6px; }
    /* Statement (judul besar + lede + chip) TIDAK ditampilkan di mobile — hanya kartu form. */
    .authx .statement { display: none; }
    .authx .stage-grid { flex-direction: column; flex-wrap: nowrap; gap: 0; padding: 6px 14px 14px; justify-content: center; }
    .authx .stage-side { flex: 0 1 auto; width: 100%; max-width: 440px; margin: 0 auto; }
    .authx .float-card { border-radius: 22px; padding: 22px 18px; }
    .authx .card-head { margin-bottom: 16px; }
    .authx .card-head h3 { font-size: 1.55rem; }
    .authx .subs { margin: 0 12px 12px; padding: 10px 16px; flex-direction: row; flex-wrap: wrap; gap: 4px 12px; justify-content: center; }
    .authx .subs .lbl { font-size: 9.5px; letter-spacing: 0.16em; }
    .authx .subs .logos { justify-content: center; gap: 14px; width: 100%; }
    .authx .subs .logos img { height: 22px; }
    .authx .subs .logos .dv { display: none; }
    .authx .sub-mobile { display: none; }
    /* Mobile TETAP beranimasi: aurora + grid + partikel + bintang + roket dipertahankan.
       Hanya elemen besar yang menutupi kartu yang disembunyikan. */
    .authx .balloon, .authx .cloud, .authx .comet, .authx .steps, .authx .orbit, .authx .bokeh, .authx .glyph, .authx .halo { display: none; }
    .authx .rocket { width: 24px; height: 24px; }
    .authx .rocket__ico { font-size: 24px; }
}
/* Layar sangat kecil (≤360px) — rapatkan lagi */
@media (max-width: 360px) {
    .authx .float-card { padding: 18px 15px; }
    .authx .card-head h3 { font-size: 1.4rem; }
    .authx .input-wrap input { padding: 12px 14px; font-size: 14px; }
}
/* Layar besar / 4K — batasi lebar agar tetap proporsional (tidak berlebihan) */
@media (min-width: 1800px) {
    .authx .stage-grid { max-width: 1560px; }
    .authx .display { font-size: clamp(2.8rem, 3vw, 4.2rem); }
    .authx .stage-side { flex-basis: 480px; }
}
html, body, #app { background: #f8fafc !important; }
</style>
