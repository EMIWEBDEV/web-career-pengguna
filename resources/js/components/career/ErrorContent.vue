<!--
  WEB CAREERS — Kartu status/error (port desain "EVO Career Error Pages").
  Dipakai dua halaman: Pages/Error.vue (standalone) & Pages/ErrorShell.vue (di dalam shell admin).
  Satu komponen agar tampilan error & mode pemeliharaan selalu identik.
-->
<template>
    <section class="ec-wrap">
        <div class="ec-card">
            <span class="ec-sheen" aria-hidden="true"></span>

            <div class="ec-row">
                <div class="ec-main">
                    <!-- Badge status -->
                    <div class="ec-badge" :style="{ background: p.tint, borderColor: p.border, color: p.accent }">
                        <span class="ec-badge__dot" :style="{ background: p.accent }"></span>
                        {{ e.badge }}
                    </div>

                    <!-- Kode + kicker -->
                    <div class="ec-code">
                        <svg viewBox="0 0 320 112" role="img" :aria-label="`Kode ${kode}`" class="ec-code__svg">
                            <defs>
                                <linearGradient :id="`numGrad-${uid}`" x1="0" y1="0" x2="1" y2="1">
                                    <stop offset="0%" :stop-color="p.c[0]" />
                                    <stop offset="55%" :stop-color="p.c[1]" />
                                    <stop offset="100%" :stop-color="p.c[2]" />
                                </linearGradient>
                            </defs>
                            <text x="0" y="93" font-family="Inter, sans-serif" font-size="108" font-weight="800" letter-spacing="-5" :fill="`url(#numGrad-${uid})`">{{ kode }}</text>
                        </svg>
                        <span class="ec-kicker">{{ e.kicker }}</span>
                    </div>

                    <h1 class="ec-title">{{ judul }}</h1>
                    <p class="ec-desc">{{ deskripsi }}</p>

                    <!-- Petunjuk -->
                    <div class="ec-hints" :style="{ background: p.tint, borderColor: p.border }">
                        <div class="ec-hints__lbl" :style="{ color: p.accent }">{{ e.hintLabel }}</div>
                        <div v-for="(h, i) in e.hints" :key="i" class="ec-hint">
                            <i class="bi bi-dot" :style="{ color: p.accent }"></i><span>{{ h }}</span>
                        </div>
                    </div>

                    <!-- Panel khusus mode pemeliharaan -->
                    <div v-if="e.progress" class="ec-panels">
                        <div class="ec-panel">
                            <div class="ec-panel__lbl">JENDELA PEMELIHARAAN</div>
                            <div class="ec-panel__val">{{ jendela }}</div>
                            <div class="ec-panel__sub">{{ tanggal }}</div>
                        </div>
                        <div class="ec-panel" :style="{ background: p.tint, borderColor: p.border }">
                            <div class="ec-panel__lbl" :style="{ color: p.accent }">STATUS SAAT INI</div>
                            <div class="ec-panel__row">
                                <span class="ec-panel__dot" :style="{ background: p.accent }"></span>
                                <span class="ec-panel__val">{{ maintenanceMenu || 'Peningkatan sistem' }}</span>
                            </div>
                            <div class="ec-panel__sub">Portal kembali otomatis</div>
                        </div>
                    </div>

                    <!-- Hitung mundur otomatis (429 / 503 / offline) -->
                    <p v-if="sisaDetik > 0" class="ec-count">
                        <i class="bi bi-clock-history"></i>
                        Mencoba otomatis dalam <b>{{ sisaDetik }} detik</b>…
                    </p>

                    <!-- Aksi -->
                    <div class="ec-actions">
                        <button type="button" class="ec-btn" :style="{ background: p.grad, boxShadow: `0 14px 30px ${p.ring}` }" @click="aksiUtama">
                            <span class="ec-btn__sheen" aria-hidden="true"></span>
                            <i class="bi" :class="e.ctaIcon"></i> {{ e.cta }}
                        </button>
                        <a :href="homeUrl" class="ec-btn2"><i class="bi bi-house-door"></i> Kembali ke Beranda</a>
                    </div>

                    <p v-if="referensi" class="ec-ref">Kode referensi: <code>{{ referensi }}</code></p>
                </div>

                <!-- Ilustrasi -->
                <div class="ec-art">
                    <svg viewBox="0 0 240 240" role="img" :aria-label="e.kicker" class="ec-art__svg">
                        <defs>
                            <linearGradient :id="`markGrad-${uid}`" x1="0" y1="0" x2="1" y2="1">
                                <stop offset="0%" :stop-color="p.c[0]" />
                                <stop offset="55%" :stop-color="p.c[1]" />
                                <stop offset="100%" :stop-color="p.c[2]" />
                            </linearGradient>
                            <radialGradient :id="`haloGrad-${uid}`">
                                <stop offset="55%" :stop-color="p.c[1]" stop-opacity="0.14" />
                                <stop offset="100%" :stop-color="p.c[1]" stop-opacity="0" />
                            </radialGradient>
                        </defs>
                        <circle cx="120" cy="120" r="118" :fill="`url(#haloGrad-${uid})`" />
                        <circle cx="120" cy="120" r="104" fill="none" :stroke="p.c[1]" stroke-opacity=".28" stroke-width="1.5" stroke-dasharray="5 9" class="ec-ring-slow" />
                        <circle cx="120" cy="120" r="86" fill="none" :stroke="p.c[1]" stroke-opacity=".18" stroke-width="10" />
                        <circle cx="120" cy="120" r="86" fill="none" :stroke="`url(#markGrad-${uid})`" stroke-width="10" stroke-linecap="round" stroke-dasharray="150 390" class="ec-ring-rev" />
                        <g class="ec-orbit"><circle cx="120" cy="20" r="7" :fill="p.c[2]" /></g>
                        <g class="ec-orbit2"><circle cx="120" cy="226" r="5" fill="#f59e0b" /></g>
                        <g transform="translate(66,66) scale(4.5)" fill="none" :stroke="`url(#markGrad-${uid})`" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                            <!-- glyph sesuai status -->
                            <template v-if="glyph === '400'"><path d="M7 3h7l4 4v14a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1z" /><path d="M14 3v5h5" /><path d="M9.6 12.6l4.8 4.8M14.4 12.6l-4.8 4.8" /></template>
                            <template v-else-if="glyph === '401'"><rect x="4.5" y="10" width="15" height="11" rx="2.6" /><path d="M8 10V7a4 4 0 0 1 8 0v3" /><circle cx="12" cy="14.8" r="1.5" /><path d="M12 16.4v2" /></template>
                            <template v-else-if="glyph === '403'"><path d="M12 2.6l7.4 3v6c0 4.6-3.1 8.6-7.4 9.9-4.3-1.3-7.4-5.3-7.4-9.9v-6z" /><path d="M9.2 9.2l5.6 5.6M14.8 9.2l-5.6 5.6" /></template>
                            <template v-else-if="glyph === '404'"><circle cx="12" cy="12" r="9" /><path d="M15.6 8.4l-2.1 4.9-4.9 2.1 2.1-4.9z" /><circle cx="12" cy="12" r="0.9" /></template>
                            <template v-else-if="glyph === '419'"><path d="M7 3h10M7 21h10" /><path d="M7.5 3v3.4c0 2.1 4.5 3.6 4.5 5.6s-4.5 3.5-4.5 5.6V21" /><path d="M16.5 3v3.4c0 2.1-4.5 3.6-4.5 5.6s4.5 3.5 4.5 5.6V21" /></template>
                            <template v-else-if="glyph === '429'"><path d="M3.5 17.5a9 9 0 1 1 17 0" /><path d="M12 12.5l4.2-3.4" /><circle cx="12" cy="13" r="1.4" /><path d="M5.8 11.2l.9.5M12 8.2v1M18.2 11.2l-.9.5" /></template>
                            <template v-else-if="glyph === '500'"><rect x="3.5" y="3.5" width="17" height="7" rx="2.2" /><path d="M20.5 13.5v5a2 2 0 0 1-2 2h-13a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h13a2 2 0 0 1 2 2z" /><circle cx="7" cy="7" r="0.9" /><path d="M13.6 15.2l3.4 2.6M17 15.2l-3.4 2.6" /></template>
                            <template v-else-if="glyph === '503'"><circle cx="12" cy="12" r="3.2" /><path d="M12 3.2v2.6M12 18.2v2.6M3.2 12h2.6M18.2 12h2.6M5.8 5.8l1.9 1.9M16.3 16.3l1.9 1.9M18.2 5.8l-1.9 1.9M7.7 16.3l-1.9 1.9" /></template>
                            <template v-else><path d="M2.6 3.4l18.8 17.2" /><path d="M4.6 10.6a13 13 0 0 1 5-3" /><path d="M15 8a13 13 0 0 1 4.4 2.6" /><path d="M8 14.4a7.6 7.6 0 0 1 3.2-1.5" /><path d="M16 14.4a7.6 7.6 0 0 0-1.4-1" /><circle cx="12" cy="18.6" r="1.2" fill="currentColor" stroke="none" /></template>
                        </g>
                    </svg>
                </div>
            </div>

            <div class="ec-foot">
                <span>EVO Career · Portal Kandidat EVO Group</span>
                <span>Butuh bantuan? <a :href="`mailto:${emailBantuan}`">Hubungi tim rekrutmen</a></span>
            </div>
        </div>
    </section>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

const props = defineProps({
    status: { type: [Number, String], default: 500 },
    message: { type: String, default: '' },
    title: { type: String, default: '' },
    homeUrl: { type: String, default: '/' },
    referensi: { type: String, default: '' },
    maintenanceMenu: { type: String, default: '' },
    jendela: { type: String, default: '21.00 – 22.00 WIB' },
    tanggal: { type: String, default: '' },
    emailBantuan: { type: String, default: 'recruitment@evonusabersaudara.co.id' },
});

const uid = Math.random().toString(36).slice(2, 8);

/* Palet warna — sama persis dengan desain. */
const P = {
    indigo: { accent: '#4f46e5', c: ['#a5b4fc', '#6366f1', '#4338ca'], grad: 'linear-gradient(120deg,#818cf8,#6366f1 45%,#4f46e5)', tint: 'rgba(99,102,241,.09)', border: 'rgba(99,102,241,.24)', ring: 'rgba(79,70,229,.3)' },
    violet: { accent: '#7c3aed', c: ['#c4b5fd', '#a78bfa', '#6d28d9'], grad: 'linear-gradient(120deg,#c4b5fd,#a78bfa 45%,#7c3aed)', tint: 'rgba(139,92,246,.1)', border: 'rgba(139,92,246,.26)', ring: 'rgba(124,58,237,.28)' },
    amber: { accent: '#b45309', c: ['#fcd34d', '#f59e0b', '#b45309'], grad: 'linear-gradient(120deg,#fcd34d,#fbbf24 45%,#d97706)', tint: 'rgba(245,158,11,.12)', border: 'rgba(245,158,11,.3)', ring: 'rgba(217,119,6,.28)' },
    red: { accent: '#b91c1c', c: ['#fca5a5', '#ef4444', '#991b1b'], grad: 'linear-gradient(120deg,#fca5a5,#f87171 45%,#dc2626)', tint: 'rgba(220,38,38,.09)', border: 'rgba(220,38,38,.24)', ring: 'rgba(220,38,38,.26)' },
    slate: { accent: '#475569', c: ['#cbd5e1', '#94a3b8', '#334155'], grad: 'linear-gradient(120deg,#cbd5e1,#94a3b8 45%,#475569)', tint: 'rgba(100,116,139,.1)', border: 'rgba(100,116,139,.26)', ring: 'rgba(71,85,105,.24)' },
};

/* Konfigurasi tiap status. */
const E = {
    400: { p: 'amber', badge: 'PERMINTAAN TIDAK VALID', kicker: 'Bad Request', title: 'Formulir tidak dapat dibaca sistem', desc: 'Data yang dikirim tidak sesuai format yang kami harapkan. Biasanya karena isian rusak atau sesi lama.', cta: 'Muat Ulang Halaman', ctaIcon: 'bi-arrow-clockwise', hintLabel: 'YANG BISA KAMU LAKUKAN', hints: ['Periksa kembali isian formulir, terutama tanggal dan unggahan berkas.', 'Bersihkan cache peramban lalu muat ulang.', 'Jika berulang, sertakan kode referensi saat menghubungi kami.'] },
    401: { p: 'indigo', badge: 'SESI BERAKHIR', kicker: 'Unauthorized', title: 'Kamu perlu masuk untuk melanjutkan', desc: 'Sesi login-mu sudah kedaluwarsa demi keamanan akun. Masuk kembali dan lanjutkan dari tempat kamu berhenti.', cta: 'Masuk Kembali', ctaIcon: 'bi-box-arrow-in-right', hintLabel: 'INFORMASI SESI', hints: ['Sesi otomatis berakhir setelah tidak ada aktivitas.', 'Draft lamaranmu tersimpan aman dan tidak hilang.', 'Gunakan "Lupa sandi?" bila kredensialmu tidak diterima.'] },
    403: { p: 'red', badge: 'AKSES DITOLAK', kicker: 'Forbidden', title: 'Halaman ini bukan untuk akunmu', desc: 'Akunmu tidak memiliki izin membuka area ini. Beberapa halaman hanya untuk tim rekrutmen internal.', cta: 'Ke Beranda Saya', ctaIcon: 'bi-speedometer2', hintLabel: 'KENAPA INI TERJADI', hints: ['Kamu membuka tautan area internal EVO Group.', 'Hak akses halaman ini belum diberikan untuk akunmu.', 'Merasa ini keliru? Hubungi tim rekrutmen dengan kode referensi.'] },
    404: { p: 'violet', badge: 'HALAMAN TIDAK DITEMUKAN', kicker: 'Not Found', title: 'Jalur karir ini belum terpetakan', desc: 'Halaman yang kamu tuju sudah dipindahkan atau lowongannya telah ditutup. Mari kembali dan temukan peluang lain.', cta: 'Jelajahi Lowongan', ctaIcon: 'bi-search', hintLabel: 'ARAH SELANJUTNYA', hints: ['Periksa kembali ejaan alamat halaman.', 'Lowongan yang ditutup akan hilang dari daftar.', 'Lihat posisi terbaru di halaman Karir kami.'] },
    419: { p: 'amber', badge: 'HALAMAN KEDALUWARSA', kicker: 'Page Expired', title: 'Halaman terlalu lama dibiarkan terbuka', desc: 'Token keamanan formulir sudah kedaluwarsa. Muat ulang halaman untuk mendapatkan token baru, lalu kirim ulang.', cta: 'Segarkan Halaman', ctaIcon: 'bi-arrow-clockwise', hintLabel: 'CATATAN KEAMANAN', hints: ['Token CSRF berlaku terbatas untuk melindungi datamu.', 'Isian yang sudah tersimpan otomatis tetap ada.', 'Hindari membuka formulir yang sama di banyak tab.'] },
    429: { p: 'amber', badge: 'TERLALU BANYAK PERMINTAAN', kicker: 'Too Many Requests', title: 'Tunggu sebentar sebelum mencoba lagi', desc: 'Kami menerima terlalu banyak percobaan dari perangkatmu. Batas ini menjaga portal tetap cepat untuk semua kandidat.', cta: 'Coba Lagi', ctaIcon: 'bi-clock-history', hintLabel: 'BATAS PERMINTAAN', hints: ['Ada batas percobaan per menit demi keamanan.', 'Berhenti menekan tombol berulang kali.', 'Batas akan pulih otomatis, tidak perlu buat akun baru.'] },
    500: { p: 'red', badge: 'KESALAHAN SERVER', kicker: 'Server Error', title: 'Ada yang tersendat di sisi kami', desc: 'Bukan salahmu — server kami mengalami kendala saat memproses permintaan. Tim teknis sudah menerima laporannya.', cta: 'Coba Lagi', ctaIcon: 'bi-arrow-clockwise', hintLabel: 'STATUS PENANGANAN', hints: ['Laporan otomatis sudah dikirim ke tim teknis EVO.', 'Data lamaran yang sudah tersimpan tetap aman.', 'Coba lagi beberapa menit lagi.'] },
    503: { p: 'indigo', badge: 'MODE PEMELIHARAAN', kicker: 'Maintenance', title: 'Portal sedang kami rapikan', desc: 'Kami sedang meningkatkan sistem rekrutmen agar prosesmu makin lancar. Halaman ini akan kembali sebentar lagi.', cta: 'Cek Status Terkini', ctaIcon: 'bi-arrow-repeat', progress: true, hintLabel: 'SELAMA PEMELIHARAAN', hints: ['Semua draft dan berkas lamaranmu tersimpan aman.', 'Jadwal interview yang sudah dikonfirmasi tidak berubah.', 'Ikuti kabar terbaru di kanal resmi EVO Group.'] },
    offline: { p: 'slate', badge: 'TIDAK ADA KONEKSI', kicker: 'Offline', title: 'Sepertinya kamu sedang luring', desc: 'Kami tidak bisa menjangkau server EVO Career. Periksa koneksi internetmu lalu coba sambungkan kembali.', cta: 'Sambungkan Ulang', ctaIcon: 'bi-arrow-repeat', hintLabel: 'PERIKSA INI DULU', hints: ['Pastikan Wi-Fi atau data seluler aktif.', 'Matikan VPN atau mode hemat data bila menyala.', 'Isian formulir tersimpan lokal sampai koneksi pulih.'] },
};

const kunci = computed(() => (E[props.status] ? String(props.status) : (String(props.status) === 'offline' ? 'offline' : '500')));
const e = computed(() => E[kunci.value] ?? E[500]);
const p = computed(() => P[e.value.p]);
const glyph = computed(() => kunci.value);
const kode = computed(() => (kunci.value === 'offline' ? '000' : kunci.value));
const judul = computed(() => props.title || e.value.title);
const deskripsi = computed(() => props.message || e.value.desc);

/* Hitung mundur otomatis untuk status yang memang pulih sendiri. */
const jeda = computed(() => ({ 429: 60, 503: 60, offline: 15 })[kunci.value] ?? 0);
const sisaDetik = ref(0);
let timer = null;

function aksiUtama() {
    const k = kunci.value;
    if (k === '401') { window.location.href = '/login'; return; }
    if (k === '403') { window.location.href = props.homeUrl; return; }
    if (k === '404') { window.location.href = '/karir/lowongan'; return; }
    window.location.reload();
}

onMounted(() => {
    if (!jeda.value) return;
    sisaDetik.value = jeda.value;
    timer = setInterval(() => {
        sisaDetik.value -= 1;
        if (sisaDetik.value <= 0) { clearInterval(timer); window.location.reload(); }
    }, 1000);
});
onBeforeUnmount(() => timer && clearInterval(timer));
</script>

<style scoped>
.ec-wrap { width: 100%; }

.ec-card {
    position: relative; overflow: hidden; border-radius: 32px;
    padding: clamp(30px, 4.5vw, 60px);
    background: rgba(255, 255, 255, .76);
    backdrop-filter: blur(22px) saturate(160%); -webkit-backdrop-filter: blur(22px) saturate(160%);
    border: 1.5px solid rgba(255, 255, 255, .92);
    box-shadow: 0 34px 84px rgba(15, 23, 42, .14), 0 6px 22px rgba(99, 102, 241, .07), inset 0 1px 0 rgba(255, 255, 255, .85);
    animation: ecRiseScale .8s cubic-bezier(.22, 1, .36, 1) .1s both, ecFloatCard 8s ease-in-out 1.2s infinite;
}
.ec-sheen { position: absolute; top: 0; left: 0; height: 100%; width: 45%; background: linear-gradient(90deg, transparent, rgba(255, 255, 255, .4), transparent); transform: translateX(-170%) skewX(-18deg); animation: ecSheen 7s ease-in-out 2s infinite; pointer-events: none; }

.ec-row { display: flex; flex-wrap: wrap; align-items: center; gap: clamp(28px, 4vw, 54px); position: relative; }
.ec-main { flex: 1 1 300px; min-width: 260px; }

.ec-badge { display: inline-flex; align-items: center; gap: 9px; padding: 7px 15px; border-radius: 999px; border: 1px solid; font-size: 12.5px; font-weight: 800; letter-spacing: .04em; }
.ec-badge__dot { width: 8px; height: 8px; border-radius: 50%; animation: ecPulseDot 2.2s ease-out infinite; }

.ec-code { display: flex; align-items: center; gap: 16px; margin-top: 16px; flex-wrap: wrap; }
.ec-code__svg { width: clamp(150px, 24vw, 260px); height: auto; display: block; overflow: visible; }
.ec-kicker { font-family: 'Playfair Display', Georgia, serif; font-style: italic; font-weight: 600; font-size: clamp(1.15rem, 2.6vw, 1.9rem); color: #334155; }

.ec-title { margin: 14px 0 0; font-weight: 800; letter-spacing: -.03em; line-height: 1.1; font-size: clamp(1.4rem, 3.4vw, 2.3rem); color: #0f172a; text-wrap: pretty; }
.ec-desc { margin: 12px 0 0; max-width: 460px; font-size: clamp(14px, 1.4vw, 16px); line-height: 1.7; color: #475569; text-wrap: pretty; }

.ec-hints { display: flex; flex-direction: column; gap: 9px; margin-top: 20px; padding: 16px 18px; border-radius: 18px; border: 1px solid; }
.ec-hints__lbl { font-size: 11px; font-weight: 800; letter-spacing: .16em; }
.ec-hint { display: flex; align-items: flex-start; gap: 9px; font-size: 13.2px; line-height: 1.55; color: #475569; }
.ec-hint i { font-size: 20px; line-height: 1; }

.ec-panels { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 18px; }
.ec-panel { flex: 1 1 150px; padding: 14px 16px; border-radius: 16px; background: rgba(255, 255, 255, .75); border: 1px solid rgba(226, 232, 240, .95); }
.ec-panel__lbl { font-size: 10.5px; font-weight: 800; letter-spacing: .16em; color: #94a3b8; }
.ec-panel__val { margin-top: 6px; font-size: 15px; font-weight: 800; color: #0f172a; }
.ec-panel__sub { margin-top: 2px; font-size: 12.5px; color: #64748b; }
.ec-panel__row { display: flex; align-items: center; gap: 9px; margin-top: 8px; }
.ec-panel__dot { width: 9px; height: 9px; border-radius: 50%; animation: ecPulseDot 2s ease-out infinite; }

.ec-count { margin: 16px 0 0; display: inline-flex; align-items: center; gap: 7px; font-size: 12.5px; color: #64748b; }

.ec-actions { display: flex; flex-wrap: wrap; gap: 11px; margin-top: 24px; }
.ec-btn { position: relative; overflow: hidden; display: inline-flex; align-items: center; gap: 9px; padding: 14px 22px; border: none; border-radius: 15px; cursor: pointer; font: inherit; font-weight: 700; font-size: 14.5px; color: #fff; transition: transform .28s cubic-bezier(.22, 1, .36, 1), filter .28s; }
.ec-btn:hover { transform: translateY(-2px); filter: brightness(1.05); }
.ec-btn:active { transform: translateY(0); }
.ec-btn__sheen { position: absolute; top: 0; left: 0; height: 100%; width: 55%; background: linear-gradient(90deg, transparent, rgba(255, 255, 255, .5), transparent); transform: translateX(-170%) skewX(-18deg); animation: ecSheen 5s ease-in-out 1.5s infinite; pointer-events: none; }
.ec-btn2 { display: inline-flex; align-items: center; gap: 9px; padding: 14px 22px; border: 1.5px solid rgba(99, 102, 241, .32); border-radius: 15px; font-weight: 700; font-size: 14.5px; color: #4f46e5; background: rgba(99, 102, 241, .07); text-decoration: none; transition: all .28s cubic-bezier(.22, 1, .36, 1); }
.ec-btn2:hover { background: rgba(99, 102, 241, .13); transform: translateY(-2px); }

.ec-ref { margin: 14px 0 0; font-size: 11.5px; color: #94a3b8; }
.ec-ref code { background: #f1f5f9; border-radius: 5px; padding: 1px 6px; }

.ec-art { flex: 0 1 300px; min-width: 200px; display: flex; align-items: center; justify-content: center; }
.ec-art__svg { width: clamp(180px, 24vw, 270px); height: auto; display: block; animation: ecBob 6s ease-in-out infinite; }
.ec-ring-slow { transform-origin: 120px 120px; animation: ecSpin 40s linear infinite; }
.ec-ring-rev { transform-origin: 120px 120px; animation: ecSpinRev 12s linear infinite; }
.ec-orbit { transform-origin: 120px 120px; animation: ecSpin 16s linear infinite; }
.ec-orbit2 { transform-origin: 120px 120px; animation: ecSpin 24s linear infinite reverse; }

.ec-foot { display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap; margin-top: clamp(24px, 3vw, 34px); padding-top: 20px; border-top: 1px solid rgba(226, 232, 240, .9); font-size: 12.5px; color: #94a3b8; }
.ec-foot a { color: #4f46e5; font-weight: 700; text-decoration: none; }
.ec-foot a:hover { color: #4338ca; }

@keyframes ecRiseScale { from { opacity: 0; transform: translateY(30px) scale(.96) } to { opacity: 1; transform: translateY(0) scale(1) } }
@keyframes ecFloatCard { 0%, 100% { transform: translateY(0) } 50% { transform: translateY(-10px) } }
@keyframes ecSheen { 0% { transform: translateX(-170%) skewX(-18deg) } 28% { transform: translateX(340%) skewX(-18deg) } 100% { transform: translateX(340%) skewX(-18deg) } }
@keyframes ecPulseDot { 0%, 100% { box-shadow: 0 0 0 0 rgba(99, 102, 241, .5) } 70% { box-shadow: 0 0 0 9px rgba(99, 102, 241, 0) } }
@keyframes ecBob { 0%, 100% { transform: translateY(0) } 50% { transform: translateY(-9px) } }
@keyframes ecSpin { to { transform: rotate(360deg) } }
@keyframes ecSpinRev { to { transform: rotate(-360deg) } }

@media (max-width: 720px) {
    .ec-art { order: -1; flex-basis: 100%; }
    .ec-art__svg { width: clamp(150px, 44vw, 200px); }
    .ec-foot { justify-content: flex-start; }
}
/*
  CATATAN MOTION — sengaja TIDAK memakai @media (prefers-reduced-motion: reduce)
  untuk mematikan seluruh animasi.

  Alasannya sama dengan `animateScroll()` di Pages/Career/careerData.js: di Windows,
  flag ini menyala begitu opsi "Show animations in Windows" dimatikan — yang oleh
  banyak pengguna dipakai sebagai pengaturan PERFORMA, bukan pernyataan kebutuhan
  aksesibilitas. Mematikan semuanya membuat halaman tampak beku/rusak.

  Yang dilakukan: animasi tetap jalan, TAPI gerakan paling besar (awan melintas
  layar & partikel naik penuh viewport, diatur di Pages/Error.vue) dibuat jauh
  lebih tenang saat flag menyala — cukup untuk menghindari pemicu vestibular
  tanpa membuat halaman mati gaya. Hormati juga pengguna yang benar-benar
  membutuhkannya lewat penonaktifan manual di bawah.
*/
:global(html[data-motion='off']) .ec-card,
:global(html[data-motion='off']) .ec-sheen,
:global(html[data-motion='off']) .ec-btn__sheen,
:global(html[data-motion='off']) .ec-art__svg,
:global(html[data-motion='off']) .ec-ring-slow,
:global(html[data-motion='off']) .ec-ring-rev,
:global(html[data-motion='off']) .ec-orbit,
:global(html[data-motion='off']) .ec-orbit2 { animation: none !important; }
</style>
