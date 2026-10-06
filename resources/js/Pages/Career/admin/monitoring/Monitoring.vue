<!-- WEB CAREER — MONITORING REKRUTMEN (NATIVE MODERN HEADER) -->
<template>
    <Head title="Monitoring Rekrutmen" />
    <div class="wca">
        <!-- 🏛️ NATIVE WEB CAREER MODERN PAGE HEADER -->
        <div class="wca-phead wca-phead--modern">
            <div class="wca-phead__title-group">
                <div class="wca-phead__icon-badge">
                    <i class="bi bi-speedometer2"></i>
                </div>
                <div>
                    <h1 class="wca-phead__title">Monitoring Rekrutmen</h1>
                    <p class="wca-phead__sub">Pantau seluruh program &amp; pelamar lintas tahap dalam satu layar — tanpa menyentuh proses.</p>
                </div>
            </div>
            <div class="wca-phead__actions wcm-actions">
                <span class="wcm-checkpoint" :title="checkpoint ? checkpoint.iso : ''">
                    <i class="bi" :class="busy ? 'bi-arrow-repeat wcm-spin' : 'bi-clock-history'"></i>
                    Data per <b>{{ checkpoint ? checkpoint.jam : '—' }}</b>
                </span>
                <div class="wcm-toggle" role="group" aria-label="Auto refresh">
                    <button v-for="opt in [0, 30, 60]" :key="opt" type="button"
                        :class="{ 'is-active': intervalSec === opt }"
                        :title="opt === 0 ? 'Auto refresh mati' : `Refresh tiap ${opt} detik`"
                        @click="intervalSec = opt">{{ opt === 0 ? 'Off' : opt + 's' }}</button>
                </div>
                <button class="wca-btn wca-btn--primary wca-btn--sm" title="Refresh sekarang" :disabled="busy" :onClick="busy ? null : refreshNow">
                    <i class="bi bi-arrow-clockwise"></i> Refresh
                </button>
            </div>
        </div>

        <!-- ══ DUA TAB ════════════════════════════════════════════════════════
             "Live" memantau proses yang sedang berjalan; "Serah Terima" memantau
             PERPINDAHAN TANGGUNG JAWABNYA. Keduanya pemantauan, tapi menjawab
             pertanyaan yang berbeda — dan yang kedua tidak bisa dijawab dari
             halaman program mana pun, sebab bentuknya lintas-program. -->
        <div class="wcm-tabs" role="tablist">
            <button
                type="button" class="wcm-tab" :class="{ on: tab === 'live' }"
                role="tab" :aria-selected="tab === 'live'"
                @click="tab = 'live'"
            ><i class="bi bi-broadcast"></i> Live View</button>
            <button
                type="button" class="wcm-tab" :class="{ on: tab === 'pic' }"
                role="tab" :aria-selected="tab === 'pic'"
                @click="tab = 'pic'"
            ><i class="bi bi-arrow-left-right"></i> Serah Terima PIC</button>
        </div>

        <!-- v-show, bukan v-if: berpindah tab tidak boleh membuang keadaan
             Live View — penyaring, program terpilih, dan penghitung auto-refresh
             semuanya harus tetap berdiri saat orang menengok tab sebelah. -->
        <LiveView v-show="tab === 'live'" ref="liveRef" @checkpoint="onCheckpoint" />
        <RiwayatPic v-if="tab === 'pic'" />
    </div>
</template>

<script setup>
import { ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import LiveView from './LiveView.vue';
import RiwayatPic from './RiwayatPic.vue';
import { useAutoRefresh } from '../../../../composables/useAutoRefresh';

const checkpoint = ref(null);
const liveRef = ref(null);
// 'live' | 'pic'. Auto-refresh sengaja TETAP berjalan walau tab PIC dibuka:
// penghitungnya menyegarkan Live View yang masih hidup di balik v-show, jadi
// kembali ke sana tidak menyajikan angka yang sudah basi beberapa menit.
const tab = ref('live');

function onCheckpoint(c) {
    checkpoint.value = c;
}

const { intervalSec, busy, refreshNow } = useAutoRefresh(
    async () => { await liveRef.value?.refresh?.(); },
    { initial: 30 },
);
</script>

<style scoped>
.wca-phead--modern {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 16px;
    padding: 20px 24px;
    border-radius: 20px;
    background: #ffffff;
    border: 1px solid rgba(226, 232, 240, 0.9);
    box-shadow: 0 4px 20px rgba(15, 23, 42, 0.04);
    margin-bottom: 20px;
    flex-wrap: wrap;
}
.wca-phead__title-group {
    display: flex;
    align-items: center;
    gap: 16px;
}
.wca-phead__icon-badge {
    width: 48px;
    height: 48px;
    border-radius: 14px;
    background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
    color: #ffffff;
    display: grid;
    place-items: center;
    font-size: 1.35rem;
    box-shadow: 0 8px 18px rgba(99, 102, 241, 0.28);
    flex-shrink: 0;
}
.wca-phead__title {
    margin: 0;
    font-size: 1.35rem;
    font-weight: 800;
    color: #0f172a;
    letter-spacing: -0.02em;
}
.wca-phead__sub {
    margin: 3px 0 0;
    font-size: 0.83rem;
    color: #64748b;
    font-weight: 500;
}
.wcm-actions { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
.wcm-checkpoint { display: inline-flex; align-items: center; gap: 7px; padding: 7px 14px; border-radius: 99px; background: #eef2ff; color: #4338ca; font-size: 12.5px; font-weight: 600; white-space: nowrap; }
.wcm-checkpoint b { font-weight: 800; font-variant-numeric: tabular-nums; }
.wcm-spin { animation: wcmSpin 0.9s linear infinite; }
@keyframes wcmSpin { to { transform: rotate(360deg); } }
.wcm-toggle { display: inline-flex; padding: 3px; border-radius: 99px; background: #f1f5f9; gap: 2px; border: 1px solid #e2e8f0; }
.wcm-toggle button { border: 0; background: transparent; padding: 6px 14px; border-radius: 99px; font-size: 12px; font-weight: 700; color: #64748b; cursor: pointer; transition: all 0.2s ease; }
.wcm-toggle button.is-active { background: #fff; color: #4f46e5; box-shadow: 0 2px 8px rgba(15, 23, 42, 0.12); }

/* ── BILAH TAB ── */
.wcm-tabs { display: flex; gap: .4rem; margin-bottom: 1rem; border-bottom: 1px solid #e2e8f0; }
.wcm-tab {
    appearance: none; border: none; background: none; cursor: pointer; font: inherit;
    display: inline-flex; align-items: center; gap: .45rem;
    padding: .6rem .95rem; margin-bottom: -1px;
    font-size: .84rem; font-weight: 700; color: #64748b;
    border-bottom: 2px solid transparent; transition: all .16s ease;
}
.wcm-tab:hover { color: #4338ca; }
.wcm-tab.on { color: #4338ca; border-bottom-color: #6366f1; }
</style>
