<!--
  ZONA A — DERETAN KPI (Redesigned)
-->
<template>
    <div class="wcd-kpi-grid">
        <component v-for="k in kartu" :key="k.kunci"
            :is="k.tautan ? 'a' : (k.lompat ? 'button' : 'div')"
            :href="k.tautan || undefined" :type="k.lompat ? 'button' : undefined"
            class="wcd-kpi-card" :class="{ 'is-clickable': !!(k.tautan || k.lompat) }"
            :style="{ '--tone': k.warna }"
            :title="k.judul"
            @click="k.lompat ? $emit('lompat', k.lompat) : null">
            
            <div class="wcd-kpi-card__glow"></div>
            
            <div class="wcd-kpi-card__top">
                <span class="wcd-kpi-card__ic">
                    <i class="bi" :class="k.ikon"></i>
                </span>
                
                <span v-if="k.delta !== null && k.delta !== undefined" class="wcd-kpi-card__delta"
                    :class="{ 
                        'is-up': k.delta > 0, 
                        'is-down': k.delta < 0, 
                        'is-same': k.delta === 0 
                    }">
                    <i class="bi" :class="k.delta === 0 ? 'bi-dash' : (k.delta > 0 ? 'bi-arrow-up-short' : 'bi-arrow-down-short')"></i>
                    <span>{{ k.delta === 0 ? 'tetap' : angka(Math.abs(k.delta)) }}</span>
                </span>
            </div>

            <div class="wcd-kpi-card__num">{{ angka(k.nilai) }}</div>
            <div class="wcd-kpi-card__lbl">{{ k.label }}</div>
            <div v-if="k.ket" class="wcd-kpi-card__ket">{{ k.ket }}</div>

            <div v-if="k.tautan || k.lompat" class="wcd-kpi-card__arrow">
                <i class="bi bi-chevron-right"></i>
            </div>
        </component>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { angka, STATUS, INK } from '@utils/career/dashboard';

const props = defineProps({
    kpi: { type: Object, required: true },
    periode: { type: [Number, null], default: 30 },
    ambang: { type: Object, default: () => ({ macetHari: 7 }) },
    kategori: { type: String, default: '' },
});

defineEmits(['lompat']);

const labelPeriode = computed(() => (props.periode === null ? 'sejak awal' : `${props.periode} hari terakhir`));

const kartu = computed(() => {
    const k = props.kpi || {};
    const jenis = props.kategori ? `?jenis=${encodeURIComponent(props.kategori)}` : '';

    return [
        {
            kunci: 'aktif', nilai: k.aktif, label: 'Pelamar aktif', ket: 'sedang berproses',
            ikon: 'bi-person-walking', warna: '#6366f1', tautan: `/karir/pelamar${jenis}`,
            judul: 'Buka Worklist Pelamar',
        },
        {
            kunci: 'siap', nilai: k.siap, label: 'Siap diputus', ket: 'mesin selesai, menunggu Anda',
            ikon: 'bi-hourglass-split', warna: '#d97706', lompat: 'aksi',
            judul: 'Lihat daftar keputusan yang menggantung',
        },
        {
            kunci: 'nungguTes', nilai: k.nungguTes, label: 'Menunggu hasil tes', ket: 'di penyedia tes',
            ikon: 'bi-clipboard-check', warna: '#0284c7', lompat: 'aksi',
        },
        {
            kunci: 'macet', nilai: k.macet, label: `Macet > ${props.ambang.macetHari} hari`,
            ket: 'tahap berjalan terlalu lama', ikon: 'bi-cone-striped', warna: '#ef4444', lompat: 'aksi',
        },
        {
            kunci: 'lulus', nilai: k.lulus, label: 'Diterima', ket: 'kursi MPP terisi',
            ikon: 'bi-patch-check-fill', warna: '#10b981', lompat: 'program',
        },
        {
            kunci: 'gugur', nilai: k.gugur, label: 'Tidak lolos', ket: 'selesai, tidak lanjut',
            ikon: 'bi-x-circle-fill', warna: '#64748b',
        },
        {
            kunci: 'talent', nilai: k.talent, label: 'Talent pool', ket: 'disimpan untuk lain kali',
            ikon: 'bi-bookmark-star-fill', warna: '#8b5cf6', tautan: '/karir/talent-pool',
            judul: 'Buka Talent Pool',
        },
        {
            kunci: 'ditahan', nilai: k.ditahan, label: 'Ditahan', ket: 'tidak masuk antrean',
            ikon: 'bi-pause-circle-fill', warna: '#64748b', lompat: 'aksi',
        },
        {
            kunci: 'pascaPenerimaan', nilai: k.pascaPenerimaan, label: 'Proses administrasi', ket: 'kontrak / onboarding',
            ikon: 'bi-file-earmark-check-fill', warna: '#10b981', lompat: 'aksi',
        },
        {
            kunci: 'baru', nilai: k.baru, label: 'Lamaran baru', ket: labelPeriode.value,
            ikon: 'bi-plus-circle-fill', warna: '#06b6d4', lompat: 'tren',
            delta: k.baruSebelum === null || k.baruSebelum === undefined ? null : k.baru - k.baruSebelum,
            judul: k.baruSebelum === null || k.baruSebelum === undefined
                ? 'Lihat grafik tren'
                : `Periode sebelumnya: ${k.baruSebelum}`,
        },
    ];
});
</script>

<style scoped>
.wcd-kpi-grid {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 16px;
}

@media (max-width: 1200px) {
    .wcd-kpi-grid {
        grid-template-columns: repeat(5, 1fr);
        gap: 12px;
    }
}

@media (max-width: 1023px) {
    .wcd-kpi-grid {
        grid-template-columns: repeat(3, 1fr);
    }
}

@media (max-width: 640px) {
    .wcd-kpi-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 10px;
    }
}

.wcd-kpi-card {
    position: relative;
    display: flex;
    flex-direction: column;
    padding: 16px 18px;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-top: 3.5px solid var(--tone, #6366f1);
    border-radius: 16px;
    text-align: left;
    text-decoration: none;
    font: inherit;
    transition: all 0.22s cubic-bezier(0.16, 1, 0.3, 1);
    box-shadow: 0 2px 8px -2px rgba(15, 23, 42, 0.04);
    overflow: hidden;
    cursor: default;
    height: 100%;
}

.wcd-kpi-card.is-clickable {
    cursor: pointer;
}

.wcd-kpi-card.is-clickable:hover {
    transform: translateY(-3px);
    border-color: color-mix(in srgb, var(--tone, #6366f1) 45%, transparent);
    border-top-color: var(--tone, #6366f1);
    box-shadow: 0 12px 28px -6px color-mix(in srgb, var(--tone, #6366f1) 22%, transparent);
}

.wcd-kpi-card__glow {
    position: absolute;
    top: 0;
    right: 0;
    width: 90px;
    height: 90px;
    background: radial-gradient(circle at top right, color-mix(in srgb, var(--tone, #6366f1) 14%, transparent) 0%, transparent 70%);
    pointer-events: none;
}

.wcd-kpi-card__top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 12px;
}

.wcd-kpi-card__ic {
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    width: 36px;
    height: 36px;
    border-radius: 10px;
    font-size: 1.1rem;
    color: var(--tone, #6366f1);
    background: color-mix(in srgb, var(--tone, #6366f1) 12%, #ffffff);
    border: 1px solid color-mix(in srgb, var(--tone, #6366f1) 24%, transparent);
    flex-shrink: 0;
    line-height: 1 !important;
    text-align: center;
}

.wcd-kpi-card__ic i {
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    width: 1em !important;
    height: 1em !important;
    line-height: 1 !important;
    margin: 0 !important;
}

.wcd-kpi-card__ic i::before {
    vertical-align: 0 !important;
    display: block !important;
    line-height: 1 !important;
    margin: 0 !important;
}

.wcd-kpi-card__delta {
    display: inline-flex;
    align-items: center;
    gap: 2px;
    padding: 3px 8px;
    border-radius: 999px;
    font-size: 0.72rem;
    font-weight: 800;
}

.wcd-kpi-card__delta.is-up {
    background: #ecfdf5;
    color: #059669;
}

.wcd-kpi-card__delta.is-down {
    background: #fef2f2;
    color: #dc2626;
}

.wcd-kpi-card__delta.is-same {
    background: #f1f5f9;
    color: #64748b;
}

.wcd-kpi-card__num {
    font-size: 1.75rem;
    font-weight: 900;
    line-height: 1.1;
    letter-spacing: -0.02em;
    color: #0f172a;
    margin-bottom: 4px;
}

.wcd-kpi-card__lbl {
    font-size: 0.82rem;
    font-weight: 800;
    color: #1e293b;
    line-height: 1.3;
}

.wcd-kpi-card__ket {
    margin-top: 4px;
    font-size: 0.72rem;
    color: #64748b;
    font-weight: 500;
    line-height: 1.25;
}

.wcd-kpi-card__arrow {
    position: absolute;
    bottom: 12px;
    right: 14px;
    font-size: 12px;
    color: #cbd5e1;
    transition: transform 0.2s ease, color 0.2s ease;
}

.wcd-kpi-card.is-clickable:hover .wcd-kpi-card__arrow {
    transform: translateX(3px);
    color: var(--tone, #6366f1);
}
</style>
