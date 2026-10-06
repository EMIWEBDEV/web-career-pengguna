<!-- WEB CAREER — PROGRAM TILE (EXECUTIVE COMMAND CENTER 2.0) -->
<template>
    <button type="button" class="wcm-tile" :class="w.kelas" @click="$emit('open')">
        <!-- TOP HEADER ROW -->
        <div class="wcm-tile__top">
            <div class="wcm-ring-box">
                <div class="wcm-ring" :style="{ '--w': w.warna }">
                    <svg viewBox="0 0 44 44" aria-hidden="true">
                        <defs>
                            <linearGradient :id="'ringGrad-' + program.id" x1="0%" y1="0%" x2="100%" y2="100%">
                                <stop offset="0%" :stop-color="w.warna" />
                                <stop offset="100%" :stop-color="w.warna" stop-opacity="0.65" />
                            </linearGradient>
                        </defs>
                        <circle class="wcm-ring__bg" cx="22" cy="22" r="19" />
                        <circle class="wcm-ring__fg" cx="22" cy="22" r="19"
                            :stroke="`url(#ringGrad-${program.id})`"
                            :stroke-dasharray="`${keliling} ${keliling}`"
                            :stroke-dashoffset="offset" />
                    </svg>
                    <!-- Angka di ring = JUMLAH ORANG yang sedang berproses, bukan
                         skor. Diberi keterangan di bawahnya supaya tidak pernah
                         lagi terbaca sebagai persentase atau nilai. -->
                    <span class="wcm-ring__num">{{ program.totalPelamar ? sehat.aktif : '—' }}</span>
                </div>
                <span class="wcm-ring__cap">{{ program.totalPelamar ? 'berproses' : 'pelamar' }}</span>
            </div>

            <div class="wcm-tile__id">
                <div class="wcm-tile__nama" :title="program.nama">{{ program.nama }}</div>
                <div class="wcm-tile__meta-row">
                    <span v-if="program.kategori" class="wca-badge wca-b--indigo wcm-tile__badge">{{ program.kategori }}</span>
                    <span v-if="program.status" class="wca-badge wcm-tile__status-badge" :class="statusBadge(program.status)">{{ program.status }}</span>
                    <span v-if="program.kode" class="wcm-tile__kode-pill">{{ program.kode }}</span>
                </div>
                <div class="wcm-tile__sub">
                    <span v-if="program.alur" class="wcm-tile__sub-item"><i class="bi bi-diagram-2"></i> {{ formatAlur(program.alur) }}</span>
                    <span v-if="program.penyelenggara" class="wcm-tile__sub-item"><i class="bi bi-building"></i> {{ program.penyelenggara }}</span>
                </div>
            </div>
        </div>

        <!-- PIPELINE STAGE MICRO-VISUALIZER BLOCK -->
        <div class="wcm-tile__funnel-container">
            <div class="wcm-tile__funnel-head">
                <span class="wcm-tile__funnel-ttl"><i class="bi bi-bar-chart-steps"></i> ALUR SELEKSI ({{ program.tahap?.length || 0 }} TAHAP)</span>
                <span class="wcm-tile__funnel-status" :style="{ color: w.warna }">{{ w.teks }}</span>
            </div>
            <div class="wcm-tile__funnel" :title="funnelTitle">
                <span v-for="t in program.tahap" :key="t.urutan" class="wcm-fseg"
                    :class="{ 'is-empty': t.aktif === 0, 'is-warn': t.siapDiputus > 0, 'is-red': t.macet > 0 }"
                    :style="{ height: tinggi(t) + '%' }"
                    :title="`${t.label}: ${t.aktif} aktif`"></span>
                <span v-if="!program.tahap || !program.tahap.length" class="wcm-fkosong">Alur belum diatur</span>
            </div>
        </div>

        <!-- QUOTA FILL RATE METER BLOCK -->
        <div v-if="program.kuota > 0" class="wcm-tile__quota">
            <div class="wcm-tile__quota-text">
                <span class="wcm-tile__quota-label"><i class="bi bi-person-check-fill text-indigo"></i> KUOTA TERISI</span>
                <b class="wcm-tile__quota-val">{{ program.terisi }}/{{ program.kuota }} Kursi ({{ kuotaPct }}%)</b>
            </div>
            <div class="wcm-tile__quota-bar">
                <div class="wcm-tile__quota-fill" :class="{ 'is-full': program.terisi >= program.kuota }" :style="{ width: kuotaPct + '%' }"></div>
            </div>
        </div>

        <!-- ACTION FOOTER -->
        <div class="wcm-tile__foot">
            <template v-if="sehat.label === 'SELESAI'">
                <span v-if="hasil.lulus" class="wcm-chip-stat is-green"><b>{{ hasil.lulus }}</b> Diterima</span>
                <span v-if="hasil.gugur" class="wcm-chip-stat is-red"><b>{{ hasil.gugur }}</b> Tidak Lolos</span>
                <span v-if="hasil.talent" class="wcm-chip-stat is-sky"><b>{{ hasil.talent }}</b> Pool</span>
                <span v-if="hasil.keluar" class="wcm-chip-stat is-violet"><b>{{ hasil.keluar }}</b> Keluar</span>
            </template>
            <template v-else>
                <span class="wcm-chip-stat is-indigo"><i class="bi bi-lightning-charge-fill"></i> <b>{{ sehat.aktif }}</b> Aktif</span>
                <span class="wcm-chip-stat is-slate"><i class="bi bi-people-fill"></i> <b>{{ program.totalPelamar }}</b> Total</span>
            </template>
            
            <div class="wcm-tile__right-actions">
                <span v-if="sehat.siapDiputus > 0" class="wcm-dot is-warn" :title="`${sehat.siapDiputus} siap diputus`"><i class="bi bi-hammer"></i> {{ sehat.siapDiputus }}</span>
                <span v-if="sehat.macet > 0" class="wcm-dot is-red" :title="`${sehat.macet} tertahan lama`"><i class="bi bi-exclamation-octagon"></i> {{ sehat.macet }}</span>
                <span class="wcm-tile__go-hint" title="Buka Detail Program"><i class="bi bi-chevron-right"></i></span>
            </div>
        </div>
    </button>
</template>

<script setup>
import { computed } from 'vue';
import { keadaanProgram } from '@utils/career/monitoring';

const props = defineProps({ program: { type: Object, required: true } });
defineEmits(['open']);

const KELILING = 2 * Math.PI * 19;
const keliling = KELILING.toFixed(1);

const sehat = computed(() => props.program.sehat || { label: 'KOSONG', aktif: 0, macet: 0, siapDiputus: 0 });
const w = computed(() => keadaanProgram(sehat.value, props.program.totalPelamar || 0));

/**
 * Isi ring = porsi pelamar yang MASIH berproses dari seluruh pelamar program.
 * Penuh berarti belum ada yang tuntas, kosong berarti semuanya sudah selesai —
 * proporsi dari dua angka nyata, bukan skor bentukan.
 */
const porsiAktif = computed(() => {
    const total = props.program.totalPelamar || 0;
    if (!total) return 0;
    return Math.min(1, (sehat.value.aktif || 0) / total);
});
const offset = computed(() => (KELILING * (1 - porsiAktif.value)).toFixed(1));

const maxAktif = computed(() => Math.max(1, ...props.program.tahap.map((t) => t.aktif)));

/** Hasil akhir program (dijumlahkan dari funnel) — dipakai saat proses tuntas. */
const hasil = computed(() =>
    props.program.tahap.reduce(
        (a, t) => ({
            lulus: a.lulus + t.lulus, gugur: a.gugur + t.gugur, talent: a.talent + t.talent,
            // Program yang sudah tuntas menampilkan rincian hasilnya di sini.
            // Tanpa `keluar`, kandidat yang mundur lenyap dari ringkasan akhir
            // program — justru di layar yang paling sering dijadikan laporan.
            keluar: a.keluar + (t.keluar ?? 0),
        }),
        { lulus: 0, gugur: 0, talent: 0, keluar: 0 },
    ),
);

const kuotaPct = computed(() => {
    if (!props.program.kuota || props.program.kuota <= 0) return 0;
    return Math.min(100, Math.round((props.program.terisi / props.program.kuota) * 100));
});

function formatAlur(val) {
    if (!val) return '';
    const str = String(val).trim();
    if (str.toLowerCase().startsWith('alur ')) return str;
    return `Alur ${str}`;
}

function statusBadge(st) {
    if (!st) return 'wca-b--slate';
    const s = st.toUpperCase();
    if (s.includes('BERJALAN') || s.includes('AKTIF') || s.includes('BUKA')) return 'wca-b--green';
    if (s.includes('SELESAI') || s.includes('TUTUP')) return 'wca-b--slate';
    if (s.includes('DRAFT')) return 'wca-b--amber';
    return 'wca-b--sky';
}

function tinggi(t) {
    return t.aktif === 0 ? 10 : Math.max(18, Math.round((t.aktif / maxAktif.value) * 100));
}

const funnelTitle = computed(() =>
    props.program.tahap.map((t) => `${t.label}: ${t.aktif} aktif`).join('\n'),
);
</script>

<style scoped>
.wcm-tile {
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    gap: 12px;
    width: 100%;
    height: 100%;
    text-align: left;
    border: 1px solid rgba(226, 232, 240, 0.95);
    border-radius: 20px;
    background: #ffffff;
    padding: 16px 18px;
    cursor: pointer;
    box-shadow: 0 4px 20px rgba(15, 23, 42, 0.04);
    transition: transform 0.25s cubic-bezier(0.16, 1, 0.3, 1), box-shadow 0.25s ease, border-color 0.25s ease;
    position: relative;
    overflow: hidden;
}
.wcm-tile:hover {
    transform: translateY(-5px);
    border-color: rgba(99, 102, 241, 0.4);
    box-shadow: 0 16px 36px rgba(99, 102, 241, 0.12);
}
.wcm-tile.is-kritis { border-color: rgba(244, 63, 94, 0.4); }
.wcm-tile.is-warn { border-color: rgba(245, 158, 11, 0.4); }

.wcm-tile i {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    line-height: 1;
    vertical-align: middle;
    flex-shrink: 0;
}

.wcm-tile__top { display: flex; gap: 12px; align-items: flex-start; }
.wcm-ring-box {
    background: #f8fafc;
    border: 1px solid #f1f5f9;
    border-radius: 14px;
    padding: 5px 5px 3px;
    flex-shrink: 0;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 1px;
}
/* Keterangan satuan di bawah ring — inilah yang mencegah angkanya terbaca
   sebagai persentase atau skor seperti sebelumnya. */
.wcm-ring__cap { font-size: 8.5px; font-weight: 800; letter-spacing: 0.04em; text-transform: uppercase; color: #94a3b8; line-height: 1; }
.wcm-ring { position: relative; width: 44px; height: 44px; }
.wcm-ring svg { width: 100%; height: 100%; transform: rotate(-90deg); }
.wcm-ring__bg { fill: none; stroke: #e2e8f0; stroke-width: 4.5; }
.wcm-ring__fg { fill: none; stroke-width: 4.5; stroke-linecap: round; transition: stroke-dashoffset 0.6s cubic-bezier(0.22, 1, 0.36, 1); }
.wcm-ring__num { position: absolute; inset: 0; display: grid; place-items: center; font-size: 13px; font-weight: 900; color: var(--w); font-variant-numeric: tabular-nums; }

.wcm-tile__id { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 3px; }
.wcm-tile__nama {
    font-size: 0.88rem;
    font-weight: 800;
    color: #0f172a;
    line-height: 1.35;
    overflow: hidden;
    text-overflow: ellipsis;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
}
.wcm-tile__meta-row { display: flex; align-items: center; gap: 5px; flex-wrap: wrap; margin-top: 1px; }
.wcm-tile__badge { font-size: 0.63rem; padding: 2px 7px; border-radius: 999px; font-weight: 700; max-width: 140px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.wcm-tile__status-badge { font-size: 0.63rem; padding: 2px 7px; border-radius: 999px; font-weight: 800; max-width: 110px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.wcm-tile__kode-pill {
    font-size: 0.64rem;
    font-weight: 800;
    color: #4338ca;
    background: #eef2ff;
    padding: 1px 6px;
    border-radius: 4px;
    border: 1px solid rgba(99, 102, 241, 0.15);
    max-width: 120px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.wcm-tile__sub {
    font-size: 0.71rem;
    color: #64748b;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}
.wcm-tile__sub-item { display: inline-flex; align-items: center; gap: 3px; max-width: 180px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.wcm-tile__sub-item i { color: #94a3b8; font-size: 0.75rem; flex-shrink: 0; }

.wcm-tile__funnel-container {
    background: #f8fafc;
    border: 1px solid #f1f5f9;
    border-radius: 14px;
    padding: 10px 12px;
    display: flex;
    flex-direction: column;
    gap: 8px;
}
.wcm-tile__funnel-head {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}
.wcm-tile__funnel-ttl {
    font-size: 0.65rem;
    font-weight: 800;
    color: #475569;
    letter-spacing: 0.02em;
    white-space: nowrap;
    display: flex;
    align-items: center;
    gap: 4px;
    min-width: 0;
    flex: 1 1 auto;
}
.wcm-tile__funnel-ttl i { color: #6366f1; }
.wcm-tile__funnel-status {
    font-size: 0.64rem;
    font-weight: 800;
    letter-spacing: 0.02em;
    padding: 2px 8px;
    border-radius: 99px;
    background: #f1f5f9;
    border: 1px solid #e2e8f0;
    color: #475569;
    flex-shrink: 0;
    line-height: 1.2;
    transition: all 0.2s ease;
    max-width: 170px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.wcm-tile__funnel-status.is-kritis {
    background: rgba(244, 63, 94, 0.1);
    border-color: rgba(244, 63, 94, 0.3);
    color: #e11d48 !important;
}
.wcm-tile__funnel-status.is-warn {
    background: rgba(245, 158, 11, 0.1);
    border-color: rgba(245, 158, 11, 0.3);
    color: #d97706 !important;
}
.wcm-tile__funnel-status.is-aktif {
    background: #eef2ff;
    border-color: rgba(99, 102, 241, 0.25);
    color: #4338ca !important;
}
.wcm-tile__funnel-status.is-selesai {
    background: #f1f5f9;
    border-color: #e2e8f0;
    color: #475569 !important;
}
.wcm-tile__funnel-status.is-idle {
    background: #f8fafc;
    border-color: #f1f5f9;
    color: #94a3b8 !important;
}

.wcm-tile__funnel { display: flex; align-items: flex-end; gap: 4px; height: 28px; padding: 0 2px; }
.wcm-fseg { flex: 1; border-radius: 4px 4px 2px 2px; background: linear-gradient(180deg, #818cf8, #4f46e5); min-width: 4px; transition: height 0.3s ease; }
.wcm-fseg.is-empty { background: #e2e8f0; }
.wcm-fseg.is-warn { background: linear-gradient(180deg, #fbbf24, #d97706); }
.wcm-fseg.is-red { background: linear-gradient(180deg, #fb7185, #e11d48); }
.wcm-fkosong { font-size: 0.70rem; color: #94a3b8; align-self: center; font-weight: 600; }

.wcm-tile__quota {
    background: #f8fafc;
    border: 1px solid #f1f5f9;
    border-radius: 12px;
    padding: 8px 12px;
    display: flex;
    flex-direction: column;
    gap: 6px;
}
.wcm-tile__quota-text {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 0.70rem;
    color: #64748b;
}
.wcm-tile__quota-label {
    font-size: 0.66rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: #475569;
    display: flex;
    align-items: center;
    gap: 4px;
}
.wcm-tile__quota-val { color: #0f172a; font-weight: 800; font-variant-numeric: tabular-nums; }
.wcm-tile__quota-bar {
    width: 100%;
    height: 6px;
    border-radius: 999px;
    background: #e2e8f0;
    overflow: hidden;
}
.wcm-tile__quota-fill {
    height: 100%;
    border-radius: 999px;
    background: linear-gradient(90deg, #6366f1 0%, #10b981 100%);
    transition: width 0.5s ease;
}
.wcm-tile__quota-fill.is-full { background: #10b981; }

.wcm-tile__foot { display: flex; align-items: center; gap: 8px; border-top: 1px solid #f1f5f9; padding-top: 10px; flex-wrap: wrap; min-height: 32px; }
.wcm-chip-stat {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 0.70rem;
    font-weight: 700;
    padding: 3px 9px;
    border-radius: 999px;
    font-variant-numeric: tabular-nums;
}
.wcm-chip-stat.is-indigo { background: #eef2ff; color: #4338ca; }
.wcm-chip-stat.is-indigo i { color: #6366f1; }
.wcm-chip-stat.is-slate { background: #f1f5f9; color: #475569; }
.wcm-chip-stat.is-slate i { color: #94a3b8; }
.wcm-chip-stat.is-green { background: #ecfdf5; color: #047857; }
.wcm-chip-stat.is-red { background: #fff1f2; color: #be123c; }
.wcm-chip-stat.is-sky { background: #f0f9ff; color: #0369a1; }
.wcm-chip-stat.is-violet { background: #f5f3ff; color: #6d28d9; }
.wcm-chip-stat b { font-weight: 800; font-size: 0.80rem; }

.wcm-tile__right-actions { margin-left: auto; display: flex; align-items: center; gap: 6px; }
.wcm-dot { min-width: 22px; text-align: center; font-size: 0.70rem; font-weight: 800; border-radius: 99px; padding: 2px 8px; display: inline-flex; align-items: center; gap: 4px; font-variant-numeric: tabular-nums; }
.wcm-dot.is-warn { color: #d97706; background: rgba(245, 158, 11, 0.14); border: 1px solid rgba(245, 158, 11, 0.25); }
.wcm-dot.is-red { color: #e11d48; background: rgba(244, 63, 94, 0.14); border: 1px solid rgba(244, 63, 94, 0.25); }

.wcm-tile__go-hint {
    width: 24px;
    height: 24px;
    border-radius: 50%;
    background: #f1f5f9;
    color: #6366f1;
    display: grid;
    place-items: center;
    font-size: 0.90rem;
    transition: all 0.2s ease;
}
.wcm-tile:hover .wcm-tile__go-hint {
    background: #6366f1;
    color: #ffffff;
    transform: translateX(3px);
}
</style>
