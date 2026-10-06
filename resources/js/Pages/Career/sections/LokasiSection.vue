<!-- WEB CAREER — Section 5: Sebaran 12 Cabang Operasional (Shopee Careers City & Position Count Grid) -->
<template>
    <section id="lokasi" class="wc-section wc-loc">
        <!-- HEADER SEKSI -->
        <div class="wc-sec-head wc-reveal">
            <span class="wc-eyebrow"><span class="wc-dot"></span> Jaringan Operasional Nasional</span>
            <h2>Lokasi Penempatan &amp; <span class="wc-grad">Cabang Operasional</span></h2>
            <p>Penempatan kerja dan jaringan kantor operasional kami tersebar di 12 kota utama Indonesia.</p>
        </div>

        <div class="wc-loc-container wc-reveal">
            <!-- ══════════════ EXACT SHOPEE CAREERS CITY & POSITION COUNT GRID ══════════════ -->
            <div class="wc-shopee-grid">
                <div
                    v-for="c in cabangList"
                    :key="c.id"
                    class="wc-shopee-card"
                    :class="{ 
                        'is-headquarter': c.utama, 
                        'is-loading': loadingKota?.nama === c.nama 
                    }"
                    role="button"
                    tabindex="0"
                    :aria-label="`Lihat lowongan di ${c.nama}`"
                    @click="bukaLowongan(c)"
                    @keydown.enter.prevent="bukaLowongan(c)"
                    @keydown.space.prevent="bukaLowongan(c)"
                >
                    <!-- Rotating Glowing Perimeter Border Beam on Active Loading -->
                    <div v-if="loadingKota?.nama === c.nama" class="wc-card-border-beam" :class="{ 'is-hq': c.utama }"></div>

                    <!-- Ambient Card Aura Pulse -->
                    <div v-if="loadingKota?.nama === c.nama" class="wc-card-aura-pulse" :class="{ 'is-hq': c.utama }"></div>

                    <!-- 2D Landmark Container with Inline Radar Scanner -->
                    <div class="wc-shopee-landmark-wrap">
                        <!-- Radar Waves on Loading -->
                        <template v-if="loadingKota?.nama === c.nama">
                            <div class="wc-card-radar-ring ring-1" :class="{ 'is-hq': c.utama }"></div>
                            <div class="wc-card-radar-ring ring-2" :class="{ 'is-hq': c.utama }"></div>
                        </template>

                        <div class="wc-shopee-landmark">
                            <!-- 1. PALEMBANG (HEAD OFFICE - JEMBATAN AMPERA 2D) -->
                            <svg v-if="c.id === 'palembang'" viewBox="0 0 160 110" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <rect x="12" y="86" width="136" height="10" fill="#cbd5e1" rx="2"/>
                                <rect x="46" y="16" width="12" height="70" fill="currentColor" rx="2"/>
                                <rect x="102" y="16" width="12" height="70" fill="currentColor" rx="2"/>
                                <rect x="42" y="38" width="76" height="9" fill="currentColor" class="wc-sp-flame" rx="2"/>
                                <rect x="42" y="60" width="76" height="7" fill="currentColor" rx="1"/>
                                <rect x="22" y="74" width="116" height="12" fill="currentColor" rx="2"/>
                            </svg>

                            <!-- BANYUASIN (KAWASAN MANUFAKTUR / GUDANG OPERASIONAL) -->
                            <svg v-else-if="c.id === 'banyuasin'" viewBox="0 0 160 110" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <rect x="12" y="50" width="22" height="46" fill="#e2e8f0" rx="2"/>
                                <rect x="126" y="42" width="22" height="54" fill="#cbd5e1" rx="2"/>
                                <path d="M28 55L72 25L116 55V84H28V55Z" fill="currentColor"/>
                                <path d="M72 25L116 55H28L72 25Z" class="wc-sp-flame"/>
                                <rect x="42" y="61" width="16" height="23" fill="#ffffff" rx="2"/>
                                <rect x="67" y="61" width="16" height="23" fill="#ffffff" rx="2"/>
                                <rect x="92" y="61" width="16" height="23" fill="#ffffff" rx="2"/>
                                <rect x="18" y="84" width="124" height="12" fill="currentColor" rx="2"/>
                            </svg>

                            <!-- 2. JAKARTA (MONUMEN NASIONAL MONAS 2D) -->
                            <svg v-else-if="c.id === 'jakarta'" viewBox="0 0 160 110" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <rect x="22" y="42" width="16" height="54" fill="#e2e8f0" rx="2"/>
                                <rect x="42" y="28" width="18" height="68" fill="#cbd5e1" rx="2"/>
                                <rect x="100" y="24" width="20" height="72" fill="#cbd5e1" rx="2"/>
                                <rect x="124" y="38" width="16" height="58" fill="#e2e8f0" rx="2"/>
                                <path d="M 78 12 Q 80 4 82 12 Q 80 18 78 12 Z" class="wc-sp-flame"/>
                                <rect x="79.2" y="18" width="1.6" height="42" fill="currentColor"/>
                                <polygon points="73,60 87,60 83,18 77,18" fill="currentColor"/>
                                <rect x="64" y="60" width="32" height="10" fill="currentColor" rx="2"/>
                                <rect x="52" y="70" width="56" height="16" fill="currentColor" rx="2"/>
                                <rect x="36" y="86" width="88" height="10" fill="currentColor" rx="2"/>
                            </svg>

                            <!-- 3. PEKANBARU (SOEMAN HS 2D) -->
                            <svg v-else-if="c.id === 'pekanbaru'" viewBox="0 0 160 110" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <rect x="18" y="48" width="18" height="48" fill="#e2e8f0" rx="2"/>
                                <rect x="124" y="48" width="18" height="48" fill="#e2e8f0" rx="2"/>
                                <path d="M 32 60 Q 80 28 128 60 L 114 82 L 46 82 Z" fill="currentColor"/>
                                <path d="M 52 48 Q 80 30 108 48 Z" class="wc-sp-flame"/>
                                <rect x="36" y="82" width="88" height="14" fill="currentColor" rx="2"/>
                            </svg>

                            <!-- 4. MEDAN (ISTANA MAIMUN 2D) -->
                            <svg v-else-if="c.id === 'medan'" viewBox="0 0 160 110" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <rect x="16" y="48" width="18" height="48" fill="#cbd5e1" rx="2"/>
                                <rect x="126" y="48" width="18" height="48" fill="#cbd5e1" rx="2"/>
                                <path d="M 52 56 C 52 30, 108 30, 108 56 Z" fill="currentColor"/>
                                <path d="M 74 30 C 74 20, 86 20, 86 30 Z" class="wc-sp-flame"/>
                                <rect x="42" y="56" width="76" height="25" fill="currentColor" rx="2"/>
                                <rect x="28" y="81" width="104" height="15" fill="currentColor" rx="2"/>
                                <path d="M 60 69 A 6 6 0 0 1 72 69 L 72 81 L 60 81 Z" fill="#ffffff"/>
                                <path d="M 88 69 A 6 6 0 0 1 100 69 L 100 81 L 88 81 Z" fill="#ffffff"/>
                            </svg>

                            <!-- 5. BATAM (JEMBATAN BARELANG 2D) -->
                            <svg v-else-if="c.id === 'batam'" viewBox="0 0 160 110" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <polygon points="80,14 73,81 87,81" fill="currentColor"/>
                                <line x1="80" y1="24" x2="32" y2="74" stroke="currentColor" stroke-width="2.5"/>
                                <line x1="80" y1="24" x2="128" y2="74" stroke="currentColor" stroke-width="2.5"/>
                                <line x1="80" y1="40" x2="48" y2="74" stroke="currentColor" stroke-width="1.8"/>
                                <line x1="80" y1="40" x2="112" y2="74" stroke="currentColor" stroke-width="1.8"/>
                                <rect x="24" y="74" width="112" height="11" fill="currentColor" class="wc-sp-flame" rx="2"/>
                                <rect x="14" y="85" width="132" height="11" fill="currentColor" rx="2"/>
                            </svg>

                            <!-- 6. BANDUNG (GEDUNG SATE 2D) -->
                            <svg v-else-if="c.id === 'bandung'" viewBox="0 0 160 110" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <rect x="16" y="48" width="20" height="48" fill="#e2e8f0" rx="2"/>
                                <rect x="124" y="42" width="20" height="54" fill="#e2e8f0" rx="2"/>
                                <circle cx="80" cy="12" r="3.5" class="wc-sp-flame"/>
                                <line x1="80" y1="15" x2="80" y2="30" stroke="currentColor" stroke-width="2"/>
                                <polygon points="64,34 80,22 96,34" fill="currentColor"/>
                                <rect x="67" y="34" width="26" height="24" fill="currentColor" rx="2"/>
                                <rect x="46" y="58" width="68" height="23" fill="currentColor" rx="2"/>
                                <rect x="28" y="81" width="104" height="15" fill="currentColor" rx="2"/>
                                <circle cx="60" cy="70" r="4" fill="#ffffff"/>
                                <circle cx="80" cy="70" r="4" fill="#ffffff"/>
                                <circle cx="100" cy="70" r="4" fill="#ffffff"/>
                            </svg>

                            <!-- 7. SEMARANG (LAWANG SEWU 2D) -->
                            <svg v-else-if="c.id === 'semarang'" viewBox="0 0 160 110" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <rect x="16" y="48" width="18" height="48" fill="#e2e8f0" rx="2"/>
                                <rect x="126" y="48" width="18" height="48" fill="#e2e8f0" rx="2"/>
                                <polygon points="40,38 80,20 120,38" fill="currentColor"/>
                                <rect x="32" y="38" width="96" height="43" fill="currentColor" rx="2"/>
                                <path d="M 45 57 A 8 8 0 0 1 61 57 L 61 81 L 45 81 Z" fill="#ffffff"/>
                                <path d="M 72 57 A 8 8 0 0 1 88 57 L 88 81 L 72 81 Z" fill="#ffffff"/>
                                <path d="M 99 57 A 8 8 0 0 1 115 57 L 115 81 L 99 81 Z" fill="#ffffff"/>
                                <rect x="24" y="81" width="112" height="15" fill="currentColor" rx="2"/>
                            </svg>

                            <!-- 8. SURABAYA (TUGU PAHLAWAN 2D) -->
                            <svg v-else-if="c.id === 'surabaya'" viewBox="0 0 160 110" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <rect x="22" y="38" width="16" height="58" fill="#cbd5e1" rx="2"/>
                                <rect x="122" y="32" width="18" height="64" fill="#cbd5e1" rx="2"/>
                                <polygon points="80,12 69,83 91,83" fill="currentColor"/>
                                <polygon points="80,12 74,83 86,83" class="wc-sp-flame"/>
                                <rect x="54" y="83" width="52" height="13" fill="currentColor" rx="2"/>
                            </svg>

                            <!-- 9. SAMARINDA (JEMBATAN MAHAKAM 2D) -->
                            <svg v-else-if="c.id === 'samarinda'" viewBox="0 0 160 110" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M 20 64 Q 80 18 140 64" stroke="currentColor" stroke-width="8" fill="none"/>
                                <path d="M 32 64 Q 80 30 128 64" stroke="currentColor" stroke-width="3.5" fill="none" class="wc-sp-flame"/>
                                <line x1="20" y1="71" x2="140" y2="71" stroke="currentColor" stroke-width="5"/>
                                <rect x="12" y="83" width="136" height="13" fill="currentColor" rx="2"/>
                            </svg>

                            <!-- 10. MAKASSAR (BENTENG ROTTERDAM 2D) -->
                            <svg v-else-if="c.id === 'makassar'" viewBox="0 0 160 110" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <polygon points="36,44 80,20 124,44" fill="currentColor"/>
                                <rect x="30" y="44" width="100" height="39" fill="currentColor" rx="2"/>
                                <path d="M 56 44 L 80 26 L 104 44 Z" class="wc-sp-flame"/>
                                <rect x="20" y="83" width="120" height="13" fill="currentColor" rx="2"/>
                            </svg>

                            <!-- 11. BANJARMASIN (MENARA PANDANG 2D) -->
                            <svg v-else-if="c.id === 'banjarmasin'" viewBox="0 0 160 110" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <polygon points="52,38 80,16 108,38" fill="currentColor"/>
                                <rect x="64" y="38" width="32" height="45" fill="currentColor" rx="2"/>
                                <rect x="54" y="54" width="52" height="9" fill="currentColor" class="wc-sp-flame" rx="2"/>
                                <rect x="40" y="83" width="80" height="13" fill="currentColor" rx="2"/>
                            </svg>

                            <!-- 12. PONTIANAK (TUGU KHATULISTIWA 2D) -->
                            <svg v-else-if="c.id === 'pontianak'" viewBox="0 0 160 110" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <rect x="58" y="16" width="7" height="67" fill="currentColor" rx="1"/>
                                <rect x="95" y="16" width="7" height="67" fill="currentColor" rx="1"/>
                                <circle cx="80" cy="38" r="20" stroke="currentColor" stroke-width="4.5"/>
                                <circle cx="80" cy="38" r="9" fill="currentColor" class="wc-sp-flame"/>
                                <rect x="34" y="83" width="92" height="13" fill="currentColor" rx="2"/>
                            </svg>
                        </div>
                    </div>

                    <!-- City Name & Position Count -->
                    <div class="wc-shopee-card__body">
                        <strong class="wc-shopee-card__name">
                            {{ c.nama.toUpperCase() }}
                        </strong>

                        <span class="wc-shopee-card__sub" :class="{ 'is-hq': c.utama }">
                            <template v-if="loadingKota?.nama === c.nama">
                                <span class="wc-card-loading-tag">
                                    <i class="bi bi-arrow-repeat wc-spin"></i> Membuka...
                                </span>
                            </template>
                            <template v-else>{{ c.posisi }} Posisi</template>
                        </span>
                    </div>

                    <!-- Inline Card Bottom Shimmer Bar when loading -->
                    <div v-if="loadingKota?.nama === c.nama" class="wc-card-bottom-shimmer" :class="{ 'is-hq': c.utama }"></div>
                </div>
            </div>
        </div>
    </section>
</template>

<script setup>
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
    offices: { type: Array, default: () => [] },
});

const loadingKota = ref(null);

function bukaLowongan(kota) {
    if (loadingKota.value) return;
    loadingKota.value = kota;

    router.visit(`/karir/lowongan?lokasi=${encodeURIComponent(kota.nama)}`, {
        onFinish: () => {
            setTimeout(() => {
                loadingKota.value = null;
            }, 300);
        },
    });
}

const slugKota = (nama) => String(nama || '').toLowerCase().replace(/[^a-z0-9]/g, '');

const cabangList = computed(() => props.offices.map((office) => ({
    ...office,
    id: slugKota(office.kota),
    nama: office.kota,
    utama: office.statusHo === 'Y',
    posisi: Number(office.posisi ?? 0),
})));
</script>

<style scoped>
.wc-loc {
    padding: 4.5rem 1.5rem;
}

.wc-loc-container {
    max-width: 1200px;
    margin: 0 auto;
}

/* EXACT 2D SHOPEE CAREERS LANDMARK GRID */
.wc-shopee-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 24px;
    justify-content: center;
}

.wc-shopee-card {
    appearance: none;
    border: 1px solid transparent;
    background: transparent;
    padding: 24px 16px;
    border-radius: 24px;
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    cursor: pointer;
    position: relative;
    overflow: hidden;
    transition: all 0.28s cubic-bezier(0.16, 1, 0.3, 1);
    min-width: 0;
    width: 100%;
    box-sizing: border-box;
}

.wc-shopee-card:hover {
    background: #ffffff;
    box-shadow: 0 18px 45px rgba(15, 23, 42, 0.1);
    transform: translateY(-6px);
}

.wc-shopee-card:active {
    transform: translateY(-2px) scale(0.98);
}

/* ACTIVE INLINE LOADING CARD STATE */
.wc-shopee-card.is-loading {
    background: #ffffff !important;
    box-shadow: 0 20px 50px rgba(79, 70, 229, 0.18) !important;
    transform: translateY(-8px) scale(1.02);
    border-color: rgba(99, 102, 241, 0.3);
}

.wc-shopee-card.is-headquarter.is-loading {
    box-shadow: 0 20px 50px rgba(225, 29, 72, 0.2) !important;
    border-color: rgba(244, 63, 94, 0.35);
}

/* ROTATING PERIMETER BORDER BEAM */
.wc-card-border-beam {
    position: absolute;
    inset: 0;
    border-radius: 24px;
    padding: 2px;
    background: conic-gradient(from 0deg, transparent 0%, #6366f1 50%, transparent 100%);
    -webkit-mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
    -webkit-mask-composite: xor;
    mask-composite: exclude;
    animation: rotateBeam 1.8s linear infinite;
    pointer-events: none;
    z-index: 5;
}

.wc-card-border-beam.is-hq {
    background: conic-gradient(from 0deg, transparent 0%, #e11d48 50%, transparent 100%);
}

@keyframes rotateBeam {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

/* AMBIENT AURA PULSE */
.wc-card-aura-pulse {
    position: absolute;
    inset: 0;
    background: radial-gradient(circle at center, rgba(99, 102, 241, 0.12) 0%, rgba(99, 102, 241, 0) 70%);
    animation: auraPulse 1.4s ease-in-out infinite alternate;
    pointer-events: none;
}

.wc-card-aura-pulse.is-hq {
    background: radial-gradient(circle at center, rgba(244, 63, 94, 0.14) 0%, rgba(244, 63, 94, 0) 70%);
}

@keyframes auraPulse {
    0% { opacity: 0.3; transform: scale(0.92); }
    100% { opacity: 0.9; transform: scale(1.05); }
}

/* LANDMARK WRAPPER & RADAR RINGS */
.wc-shopee-landmark-wrap {
    position: relative;
    width: 150px;
    max-width: 100%;
    height: 105px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 14px;
}

.wc-card-radar-ring {
    position: absolute;
    border-radius: 50%;
    border: 2px solid rgba(99, 102, 241, 0.35);
    animation: cardRadar 1.8s cubic-bezier(0, 0.2, 0.8, 1) infinite;
    pointer-events: none;
}

.wc-card-radar-ring.is-hq {
    border-color: rgba(244, 63, 94, 0.4);
}

.wc-card-radar-ring.ring-1 {
    width: 70px;
    height: 70px;
    animation-delay: 0s;
}

.wc-card-radar-ring.ring-2 {
    width: 100px;
    height: 100px;
    animation-delay: 0.5s;
}

@keyframes cardRadar {
    0% { transform: scale(0.5); opacity: 0.9; }
    100% { transform: scale(1.4); opacity: 0; }
}

/* 2D ARCHITECTURAL LANDMARK GRAPHIC CONTAINER */
.wc-shopee-landmark {
    width: 100%;
    height: 100%;
    color: #475569;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.28s ease;
    position: relative;
    z-index: 2;
}

.wc-shopee-landmark svg {
    width: 100%;
    height: 100%;
    max-width: 100%;
    max-height: 100%;
}

.wc-sp-flame {
    fill: #94a3b8;
    transition: fill 0.25s ease;
}

/* HOVER & LOADING LANDMARK ANIMATION */
.wc-shopee-card:hover .wc-shopee-landmark,
.wc-shopee-card.is-loading .wc-shopee-landmark {
    color: #4f46e5;
    transform: scale(1.06);
    filter: drop-shadow(0 6px 16px rgba(79, 70, 229, 0.25));
}

.wc-shopee-card:hover .wc-sp-flame,
.wc-shopee-card.is-loading .wc-sp-flame {
    fill: #818cf8;
}

/* KANTOR PUSAT (HO - PALEMBANG) STYLING */
.wc-shopee-card.is-headquarter:hover .wc-shopee-landmark,
.wc-shopee-card.is-headquarter.is-loading .wc-shopee-landmark {
    color: #e11d48;
    filter: drop-shadow(0 6px 16px rgba(225, 29, 72, 0.28));
}

.wc-shopee-card.is-headquarter:hover .wc-sp-flame,
.wc-shopee-card.is-headquarter.is-loading .wc-sp-flame {
    fill: #f43f5e;
}

/* TEXT STYLING */
.wc-shopee-card__body {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 4px;
    position: relative;
    z-index: 2;
    width: 100%;
}

.wc-shopee-card__name {
    font-size: 0.95rem;
    font-weight: 900;
    color: #0f172a;
    letter-spacing: 0.8px;
    transition: color 0.2s ease;
    word-break: break-word;
}

.wc-spin {
    animation: spin 0.8s linear infinite;
    display: inline-block;
}

@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

.wc-shopee-card:hover .wc-shopee-card__name,
.wc-shopee-card.is-loading .wc-shopee-card__name {
    color: #4f46e5;
}

.wc-shopee-card.is-headquarter:hover .wc-shopee-card__name,
.wc-shopee-card.is-headquarter.is-loading .wc-shopee-card__name {
    color: #e11d48;
}

.wc-shopee-card__sub {
    font-size: 0.82rem;
    font-weight: 600;
    color: #64748b;
    transition: color 0.2s ease;
}

.wc-shopee-card__sub.is-hq {
    color: #e11d48;
    font-weight: 800;
}

.wc-card-loading-tag {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 3px 10px;
    border-radius: 99px;
    background: #4f46e5;
    color: #ffffff;
    font-size: 0.75rem;
    font-weight: 800;
    box-shadow: 0 4px 12px rgba(79, 70, 229, 0.35);
    animation: tagBounce 0.5s ease;
}

.wc-shopee-card.is-headquarter .wc-card-loading-tag {
    background: #e11d48;
    box-shadow: 0 4px 12px rgba(225, 29, 72, 0.38);
}

@keyframes tagBounce {
    0% { transform: scale(0.8); opacity: 0; }
    100% { transform: scale(1); opacity: 1; }
}

/* BOTTOM SHIMMER BAR ON CARD */
.wc-card-bottom-shimmer {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, #4f46e5, #818cf8, #c084fc, #4f46e5);
    background-size: 200% 100%;
    animation: shimmerSlide 1.2s linear infinite;
    z-index: 5;
}

.wc-card-bottom-shimmer.is-hq {
    background: linear-gradient(90deg, #e11d48, #fb7185, #f43f5e, #e11d48);
    background-size: 200% 100%;
}

@keyframes shimmerSlide {
    0% { background-position: 0% 0%; }
    100% { background-position: 200% 0%; }
}

/* RESPONSIVE MOBILE: STRICT 3 COLUMNS PER ROW (INCLUDING 375x667) */
@media (max-width: 768px) {
    .wc-loc {
        padding: 2.5rem 0.75rem !important;
    }
    .wc-loc-container {
        padding: 0 !important;
    }
    .wc-shopee-grid {
        display: grid !important;
        grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
        gap: 8px !important;
        width: 100% !important;
    }
    .wc-shopee-card {
        padding: 10px 4px !important;
        border-radius: 14px !important;
        min-width: 0 !important;
        width: 100% !important;
        box-sizing: border-box !important;
    }
    .wc-shopee-landmark-wrap {
        width: 100% !important;
        max-width: 75px !important;
        height: 46px !important;
        margin-bottom: 6px !important;
    }
    .wc-card-radar-ring.ring-1 {
        width: 35px !important;
        height: 35px !important;
    }
    .wc-card-radar-ring.ring-2 {
        width: 55px !important;
        height: 55px !important;
    }
    .wc-shopee-card__body {
        gap: 2px !important;
    }
    .wc-shopee-card__name {
        font-size: 0.68rem !important;
        letter-spacing: 0px !important;
        line-height: 1.15 !important;
        word-break: break-word !important;
    }
    .wc-shopee-card__sub {
        font-size: 0.62rem !important;
        line-height: 1.1 !important;
        white-space: nowrap !important;
    }
    .wc-card-loading-tag {
        font-size: 0.58rem !important;
        padding: 1px 4px !important;
    }
}
</style>
