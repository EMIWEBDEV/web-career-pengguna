<script>
// Opt-out dari AppShell: halaman error STANDALONE (tanpa sidebar/topbar).
// app.js hanya menimpa layout bila `undefined`, jadi `null` eksplisit dihormati.
export default { layout: null };
</script>

<script setup>
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import ErrorContent from '@career/ErrorContent.vue';

const props = defineProps({
    status: { type: [Number, String], default: 500 },
    message: { type: String, default: '' },
    title: { type: String, default: '' },
    homeUrl: { type: String, default: '/' },
    referensi: { type: String, default: '' },
    maintenanceMenu: { type: String, default: '' },
    jendela: { type: String, default: '21.00 – 22.00 WIB' },
    tanggal: { type: String, default: '' },
});

const judulTab = computed(() => {
    const peta = {
        400: 'Permintaan Tidak Valid', 401: 'Sesi Berakhir', 403: 'Akses Ditolak',
        404: 'Halaman Tidak Ditemukan', 419: 'Halaman Kedaluwarsa', 429: 'Terlalu Banyak Permintaan',
        500: 'Kesalahan Server', 503: 'Mode Pemeliharaan', offline: 'Tidak Ada Koneksi',
    };
    return peta[String(props.status)] || 'Terjadi Kesalahan';
});
</script>

<template>
    <Head :title="judulTab" />

    <div class="err-page">
        <!-- Latar: aurora, grid, awan, partikel (sesuai desain) -->
        <div class="err-bg" aria-hidden="true">
            <div class="err-blob err-blob--1"></div>
            <div class="err-blob err-blob--2"></div>
            <div class="err-blob err-blob--3"></div>
            <div class="err-grid"></div>
            <div class="err-cloud err-cloud--1"><div class="err-cloud__body"><span class="err-cloud__p1"></span><span class="err-cloud__p2"></span></div></div>
            <div class="err-cloud err-cloud--2"><div class="err-cloud__body"><span class="err-cloud__p1"></span><span class="err-cloud__p2"></span></div></div>
            <span class="err-dot err-dot--1"></span>
            <span class="err-dot err-dot--2"></span>
            <span class="err-dot err-dot--3"></span>
            <span class="err-dot err-dot--4"></span>
        </div>

        <nav class="err-nav">
            <a class="err-brand" :href="homeUrl">
                <img src="/logo/EVOGROUP.png" alt="EVO Group" />
                <span class="err-brand__txt">
                    <span class="err-brand__n">EVO Career</span>
                    <span class="err-brand__s">PORTAL KANDIDAT</span>
                </span>
            </a>
        </nav>

        <main class="err-main">
            <ErrorContent
                :status="status" :message="message" :title="title" :home-url="homeUrl"
                :referensi="referensi" :maintenance-menu="maintenanceMenu"
                :jendela="jendela" :tanggal="tanggal"
            />
        </main>
    </div>
</template>

<style scoped>
.err-page {
    position: relative; min-height: 100vh; overflow-x: hidden; isolation: isolate;
    display: flex; flex-direction: column;
    background: linear-gradient(180deg, #f8fafc 0%, #eef2ff 48%, #ffffff 100%);
    font-family: 'Inter', 'Plus Jakarta Sans', system-ui, sans-serif;
    -webkit-font-smoothing: antialiased; color: #0f172a;
}

.err-bg { position: absolute; inset: 0; z-index: 0; overflow: hidden; pointer-events: none; }
.err-blob { position: absolute; border-radius: 50%; }
.err-blob--1 { top: -14%; left: -8%; width: clamp(320px, 42vw, 640px); height: clamp(320px, 42vw, 640px); filter: blur(76px); opacity: .5; background: radial-gradient(circle, rgba(139, 92, 246, .5), transparent 68%); animation: errAurora 21s ease-in-out infinite; }
.err-blob--2 { top: 6%; right: -6%; width: clamp(320px, 38vw, 600px); height: clamp(320px, 38vw, 600px); filter: blur(80px); opacity: .44; background: radial-gradient(circle, rgba(99, 102, 241, .48), transparent 66%); animation: errAurora 25s ease-in-out 2s infinite reverse; }
.err-blob--3 { bottom: -16%; left: 36%; width: clamp(260px, 30vw, 480px); height: clamp(260px, 30vw, 480px); filter: blur(72px); opacity: .36; background: radial-gradient(circle, rgba(245, 158, 11, .4), transparent 68%); animation: errAurora 27s ease-in-out 1s infinite; }
.err-grid {
    position: absolute; inset: -20%;
    background-image: linear-gradient(rgba(99, 102, 241, .05) 1px, transparent 1px), linear-gradient(90deg, rgba(99, 102, 241, .05) 1px, transparent 1px);
    background-size: 54px 54px;
    -webkit-mask: radial-gradient(ellipse at 50% 45%, #000 32%, transparent 74%);
    mask: radial-gradient(ellipse at 50% 45%, #000 32%, transparent 74%);
    animation: errGridPan 42s linear infinite;
}
.err-cloud { position: absolute; left: 0; }
.err-cloud--1 { top: 11%; opacity: .55; animation: errCloud 64s linear infinite; }
.err-cloud--2 { top: 28%; opacity: .4; animation: errCloud 88s linear 10s infinite; }
.err-cloud__body { position: relative; width: 150px; height: 44px; border-radius: 44px; background: rgba(255, 255, 255, .85); }
.err-cloud__p1 { position: absolute; top: -20px; left: 34px; width: 56px; height: 56px; border-radius: 50%; background: rgba(255, 255, 255, .85); }
.err-cloud__p2 { position: absolute; top: -30px; left: 70px; width: 44px; height: 44px; border-radius: 50%; background: rgba(255, 255, 255, .85); }
.err-dot { position: absolute; bottom: -16px; border-radius: 50%; }
.err-dot--1 { left: 12%; width: 7px; height: 7px; background: #8b5cf6; box-shadow: 0 0 11px rgba(139, 92, 246, .65); animation: errFloatUp 18s linear 1s infinite; }
.err-dot--2 { left: 32%; width: 6px; height: 6px; background: #6366f1; box-shadow: 0 0 10px rgba(99, 102, 241, .6); animation: errFloatUp 22s linear 3.5s infinite; }
.err-dot--3 { left: 57%; width: 8px; height: 8px; background: #f59e0b; box-shadow: 0 0 11px rgba(245, 158, 11, .55); animation: errFloatUp 20s linear .5s infinite; }
.err-dot--4 { left: 78%; width: 6px; height: 6px; background: #a5b4fc; box-shadow: 0 0 9px rgba(165, 180, 252, .7); animation: errFloatUp 17s linear 5s infinite; }

.err-nav { position: relative; z-index: 2; display: flex; align-items: center; justify-content: space-between; gap: 18px; flex-wrap: wrap; padding: clamp(20px, 3vw, 36px) clamp(20px, 4vw, 60px); animation: errRise .7s cubic-bezier(.22, 1, .36, 1) both; }
.err-brand { display: flex; align-items: center; gap: 13px; text-decoration: none; color: inherit; }
.err-brand img { width: 52px; height: 52px; object-fit: contain; display: block; }
.err-brand__txt { line-height: 1.1; display: flex; flex-direction: column; }
.err-brand__n { font-weight: 800; font-size: 18px; letter-spacing: -.02em; }
.err-brand__s { font-size: 10.5px; font-weight: 600; letter-spacing: .22em; color: #94a3b8; }

.err-main { position: relative; z-index: 1; flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; width: 100%; max-width: 1080px; margin: 0 auto; padding: clamp(20px, 3vw, 40px) clamp(20px, 4vw, 60px) clamp(40px, 6vw, 72px); }

@keyframes errRise { from { opacity: 0; transform: translateY(24px) } to { opacity: 1; transform: translateY(0) } }
@keyframes errAurora { 0%, 100% { transform: translate(0, 0) scale(1) } 33% { transform: translate(6%, -5%) scale(1.09) } 66% { transform: translate(-5%, 4%) scale(.94) } }
@keyframes errGridPan { to { background-position: 54px 54px } }
@keyframes errCloud { from { transform: translateX(-42vw) } to { transform: translateX(122vw) } }
@keyframes errFloatUp { 0% { transform: translateY(0) scale(.5); opacity: 0 } 12% { opacity: .8 } 80% { opacity: .5 } 100% { transform: translateY(-88vh) scale(1); opacity: 0 } }

/*
  MOTION — lihat catatan di components/career/ErrorContent.vue.
  Animasi TIDAK dimatikan total saat prefers-reduced-motion menyala (di Windows
  flag itu ikut aktif hanya karena "Show animations" dimatikan demi performa).
  Yang ditenangkan hanya gerakan berjarak jauh: awan melintas layar & partikel
  yang naik setinggi viewport — keduanya diperlambat & dikurangi jaraknya.
*/
@media (prefers-reduced-motion: reduce) {
    .err-cloud { animation-duration: 160s !important; opacity: .25 !important; }
    .err-dot { animation-duration: 40s !important; }
}

/* Penonaktifan manual penuh (untuk pengguna yang benar-benar membutuhkannya). */
:global(html[data-motion='off']) .err-blob,
:global(html[data-motion='off']) .err-grid,
:global(html[data-motion='off']) .err-cloud,
:global(html[data-motion='off']) .err-dot,
:global(html[data-motion='off']) .err-nav { animation: none !important; }
</style>
