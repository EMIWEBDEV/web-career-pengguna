<!--
  ZONA C — FUNNEL & KONVERSI (Redesigned)
-->
<template>
    <div v-if="!funnel.length">
        <KeadaanPanel keadaan="kosong" ikon="bi-funnel"
            teks="Belum ada funnel untuk kategori ini"
            ket="Funnel terbentuk dari tahap alur seleksi program. Pastikan programnya sudah punya alur." />
    </div>

    <div v-else class="fn">
        <!-- Kepala: pemilih alur, cakupan, dan penyempitan terbesar -->
        <div class="fn-atas">
            <!-- Pemilih Alur Seleksi: Selalu menggunakan Dropdown Select yang modern dan konsisten -->
            <div v-if="funnel.length > 1" class="fn-select-wrap">
                <i class="bi bi-funnel-fill fn-select-ic"></i>
                <select id="fn-select-alur" v-model="idx" class="fn-select-input">
                    <option v-for="(f, i) in funnel" :key="f.alurId" :value="i">
                        {{ f.alur }} ({{ f.program }} program · {{ angka(f.pelamar) }} pelamar)
                    </option>
                </select>
            </div>

            <button type="button" class="fn-cakup-btn" @click="lihatProgram = true"
                title="Klik untuk melihat rincian nama program yang masuk alur ini">
                <i class="bi bi-diagram-2-fill"></i>
                <span><b>{{ aktif.program }}</b> program · <b>{{ angka(aktif.pelamar) }}</b> pelamar</span>
                <i class="bi bi-info-circle-fill fn-cakup-info"></i>
            </button>

            <span v-if="aktif.penyempitan" class="fn-sempit"
                :title="`${angka(aktif.penyempitan.hilang)} kandidat berhenti di titik ini`">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <span>Penyempitan terbesar: <b>{{ aktif.penyempitan.dari }} → {{ aktif.penyempitan.ke }}</b></span>
                <em>{{ aktif.penyempitan.persen }}% lanjut (−{{ angka(aktif.penyempitan.hilang) }})</em>
            </span>

            <span class="fn-legenda">
                <i class="fn-swatch is-lanjut"></i> Lanjut
                <i class="fn-swatch is-henti"></i> Berhenti di tahap
            </span>
        </div>

        <!-- Modal Rincian Program dalam Alur -->
        <AdminModal
            :show="lihatProgram"
            title="Daftar Program Alur Seleksi"
            :subtitle="`Alur: ${aktif.alur} · Total ${aktif.program} Program`"
            icon="bi-diagram-2-fill"
            @close="lihatProgram = false"
        >
            <div class="fn-program-modal-list">
                <div v-for="(pNama, pIdx) in (aktif.programNama || [])" :key="pIdx" class="fn-program-chip">
                    <span class="fn-program-chip__num">{{ pIdx + 1 }}</span>
                    <i class="bi bi-journal-check"></i>
                    <span>{{ pNama }}</span>
                </div>
            </div>
            <template #footer>
                <button type="button" class="wca-btn wca-btn--ghost" @click="lihatProgram = false">
                    <i class="bi bi-x-lg"></i> Tutup
                </button>
            </template>
        </AdminModal>

        <!-- Daftar tahap: satu baris per tahap. -->
        <ul class="fn-list">
            <li v-for="t in aktif.tahap" :key="t.urutan">
                <div class="fn-row" :class="{ 'is-sempit': sorot(t), 'is-buka': buka.has(t.urutan) }">
                    <span class="fn-ur">{{ t.urutan }}</span>

                    <div class="fn-info">
                        <span class="fn-lbl" :title="t.label">
                            {{ t.label }}
                            <i v-if="t.provider === 'THIRD_PARTY'" class="bi bi-box-arrow-up-right"
                                title="Tahap oleh penyedia tes pihak ketiga"></i>
                        </span>
                        <span class="fn-sub">
                            {{ persenDariAwal(t) }}% dari total melamar
                        </span>
                    </div>

                    <div class="fn-rel" :title="tooltip(t)">
                        <div class="fn-bar" :style="{ width: lebar(t) + '%' }">
                            <div class="fn-bar__lanjut" :style="{ width: persenLanjut(t) + '%' }"></div>
                        </div>
                    </div>

                    <div class="fn-angka-col">
                        <span class="fn-capai"><b>{{ angka(t.capai) }}</b> <small>kandidat</small></span>
                        <span v-if="t.konversi !== null" class="wcd-lb fn-badge-konv" :style="nadaKonversi(t)">
                            <i class="bi" :class="sorot(t) ? 'bi-arrow-down-right' : 'bi-arrow-right-short'"></i>
                            {{ t.konversi }}% Lolos
                        </span>
                        <span v-else class="fn-konv__nol">Tahap Awal</span>
                    </div>

                    <div class="fn-mini-pills">
                        <span v-if="t.lanjut" class="fn-pill is-lanjut" title="Lanjut ke tahap berikutnya">
                            <i class="bi bi-check-circle-fill"></i> {{ angka(t.lanjut) }}
                        </span>
                        <span v-if="t.diSini" class="fn-pill is-proses" title="Sedang dalam proses di sini">
                            <i class="bi bi-clock-fill"></i> {{ angka(t.diSini) }}
                        </span>
                        <span v-if="t.gugur" class="fn-pill is-gugur" title="Gugur di tahap ini">
                            <i class="bi bi-x-circle-fill"></i> {{ angka(t.gugur) }}
                        </span>
                    </div>

                    <button type="button" class="fn-chev-btn" :aria-expanded="buka.has(t.urutan)"
                        :title="buka.has(t.urutan) ? 'Tutup rincian' : 'Lihat rincian detail'"
                        @click="alih(t.urutan)">
                        <i class="bi" :class="buka.has(t.urutan) ? 'bi-chevron-up' : 'bi-chevron-down'"></i>
                    </button>
                </div>

                <!-- Rincian baris (dilipat) -->
                <div v-show="buka.has(t.urutan)" class="fn-rinci">
                    <span><i class="bi bi-person-check-fill"></i> Lanjut: <b>{{ angka(t.lanjut) }}</b></span>
                    <span><i class="bi bi-people-fill"></i> Di tahap ini: <b :class="{ 'is-nol': !t.diSini }">{{ angka(t.diSini) }}</b></span>
                    <span><i class="bi bi-patch-check-fill"></i> Diterima: <b :class="{ 'is-nol': !t.diterima }">{{ angka(t.diterima) }}</b></span>
                    <span><i class="bi bi-person-x-fill"></i> Tidak lolos: <b :class="{ 'is-nol': !t.gugur }">{{ angka(t.gugur) }}</b></span>
                    <span><i class="bi bi-bookmark-star-fill"></i> Talent pool: <b :class="{ 'is-nol': !t.talent }">{{ angka(t.talent) }}</b></span>
                </div>
            </li>
        </ul>

        <!-- Padanan tabel lengkap (dilipat) -->
        <details class="fn-tabel">
            <summary><i class="bi bi-table"></i> Lihat matriks lengkap per tahap</summary>
            <div class="wcd-tw">
                <table class="wcd-tbl">
                    <thead>
                        <tr>
                            <th>#</th><th>Tahap</th><th class="wcd-num">Mencapai</th>
                            <th class="wcd-num">Lanjut</th><th class="wcd-num">Di sini</th>
                            <th class="wcd-num">Diterima</th><th class="wcd-num">Gugur</th>
                            <th class="wcd-num">Talent</th><th class="wcd-num">Konversi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="t in aktif.tahap" :key="t.urutan" :class="{ 'is-sempit': sorot(t) }">
                            <td>{{ t.urutan }}</td>
                            <td class="wcd-tbl__utama">{{ t.label }}</td>
                            <td class="wcd-num">{{ angka(t.capai) }}</td>
                            <td class="wcd-num">{{ angka(t.lanjut) }}</td>
                            <td class="wcd-num">{{ angka(t.diSini) }}</td>
                            <td class="wcd-num">{{ angka(t.diterima) }}</td>
                            <td class="wcd-num">{{ angka(t.gugur) }}</td>
                            <td class="wcd-num">{{ angka(t.talent) }}</td>
                            <td class="wcd-num">
                                <span v-if="t.konversi !== null" class="wcd-lb" :style="nadaKonversi(t)">
                                    {{ t.konversi }}%
                                </span>
                                <span v-else class="fn-konv__nol">—</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </details>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { angka, STATUS, INK } from '@utils/career/dashboard';
import KeadaanPanel from '../../monitoring/KeadaanPanel.vue';
import AdminModal from '@career/AdminModal.vue';

const props = defineProps({ funnel: { type: Array, default: () => [] } });

const idx = ref(0);
const buka = ref(new Set());
const lihatProgram = ref(false);

const aktif = computed(() => props.funnel[idx.value] || props.funnel[0] || { tahap: [], programNama: [] });

function lebar(t) {
    const pert = aktif.value.tahap[0]?.capai || 1;
    return Math.max(2, Math.round((t.capai / pert) * 100));
}

function persenLanjut(t) {
    if (!t.capai) return 0;
    return Math.round((t.lanjut / t.capai) * 100);
}

function persenDariAwal(t) {
    const pert = aktif.value.tahap[0]?.capai || 1;
    return Math.round((t.capai / pert) * 100);
}

function tooltip(t) {
    const pert = aktif.value.tahap[0]?.capai || 1;
    const pDarisemua = Math.round((t.capai / pert) * 100);
    const r = [`${t.urutan}. ${t.label}`, `${angka(t.capai)} kandidat (${pDarisemua}% dari total melamar)`];
    if (t.konversi !== null) r.push(`Konversi dari tahap sebelumnya: ${t.konversi}%`);
    r.push(`• ${angka(t.lanjut)} lanjut ke tahap berikutnya`);
    if (t.diSini) r.push(`• ${angka(t.diSini)} sedang berproses di sini`);
    if (t.diterima) r.push(`• ${angka(t.diterima)} diterima di sini`);
    if (t.gugur) r.push(`• ${angka(t.gugur)} tidak lolos`);
    if (t.talent) r.push(`• ${angka(t.talent)} ke talent pool`);
    return r.join('\n');
}

function alih(u) {
    const s = new Set(buka.value);
    s.has(u) ? s.delete(u) : s.add(u);
    buka.value = s;
}

function sorot(t) {
    const p = aktif.value.penyempitan;
    return !!p && t.label === p.ke && t.konversi === p.persen;
}

function nadaKonversi(t) {
    if (sorot(t)) return { background: `color-mix(in srgb, ${STATUS.critical.warna} 14%, #fff)`, color: STATUS.critical.warna };
    if (t.konversi >= 80) return { background: `color-mix(in srgb, ${STATUS.good.warna} 14%, #fff)`, color: STATUS.good.warna };
    return { background: '#f1f5f9', color: INK.kedua };
}
</script>

<style scoped>
.fn-atas {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
    margin-bottom: 14px;
}
.fn-alur { flex-wrap: wrap; }
.fn-alur em { font-style: normal; opacity: 0.7; }

/* DROPDOWN SELECT UNTUK BANYAK ALUR (>4) */
.fn-select-wrap {
    position: relative;
    display: inline-flex;
    align-items: center;
    min-width: 260px;
}
.fn-select-ic {
    position: absolute;
    left: 12px;
    color: #6366f1;
    font-size: 0.85rem;
    pointer-events: none;
}
.fn-select-input {
    width: 100%;
    padding: 7px 32px 7px 34px;
    border: 1.5px solid #cbd5e1;
    border-radius: 12px;
    background: #ffffff;
    color: #0f172a;
    font: inherit;
    font-size: 0.78rem;
    font-weight: 800;
    cursor: pointer;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);
    transition: all 0.15s ease;
}
.fn-select-input:focus {
    outline: 0;
    border-color: #6366f1;
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15);
}

.fn-cakup-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    border: 1.5px solid #cbd5e1;
    border-radius: 12px;
    background: #ffffff;
    font: inherit;
    font-size: 0.78rem;
    color: #475569;
    font-weight: 600;
    cursor: pointer;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);
    transition: all 0.15s ease;
}
.fn-cakup-btn:hover {
    border-color: #6366f1;
    color: #4338ca;
    background: #eef2ff;
}
.fn-cakup-btn .bi { color: #6366f1; }
.fn-cakup-btn b { color: #0f172a; font-weight: 800; }
.fn-cakup-info { font-size: 0.72rem; color: #94a3b8; margin-left: 2px; }

/* MODAL DAFTAR PROGRAM */
.fn-program-modal-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
    padding: 4px 0;
}

.fn-program-chip {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 14px;
    border-radius: 12px;
    background: #f8fafc;
    border: 1.5px solid #e2e8f0;
    font-size: 0.82rem;
    font-weight: 800;
    color: #0f172a;
}
.fn-program-chip .bi { color: #6366f1; font-size: 1rem; }

.fn-program-chip__num {
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    width: 22px;
    height: 22px;
    border-radius: 6px;
    background: #eef2ff;
    color: #4f46e5;
    font-size: 0.7rem;
    font-weight: 900;
    line-height: 1 !important;
}

.fn-sempit {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 6px 14px;
    border-radius: 999px;
    background: #fff7ed;
    border: 1px solid #ffedd5;
    font-size: 0.76rem;
    color: #9a3412;
    box-shadow: 0 2px 6px rgba(234, 88, 12, 0.06);
}
.fn-sempit > .bi { font-size: 14px; color: #ea580c; flex: none; }
.fn-sempit b { font-weight: 900; }
.fn-sempit em { font-style: normal; font-weight: 800; opacity: 0.85; }

.fn-legenda {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    margin-left: auto;
    font-size: 0.74rem;
    font-weight: 600;
    color: #64748b;
    white-space: nowrap;
}
.fn-swatch { display: inline-block; width: 12px; height: 10px; border-radius: 4px; }
.fn-swatch.is-lanjut { background: #6366f1; }
.fn-swatch.is-henti { background: #c7d2fe; margin-left: 8px; }

/* ══════════ BARIS TAHAP ══════════ */
.fn-list { list-style: none; margin: 0; padding: 0; border: 1.5px solid #e2e8f0; border-radius: 16px; overflow: hidden; background: #fff; }
.fn-list > li + li { border-top: 1px solid #f1f5f9; }

.fn-row {
    display: grid;
    grid-template-columns: 24px minmax(130px, 1.2fr) minmax(120px, 2fr) minmax(110px, auto) auto 28px;
    align-items: center;
    gap: 14px;
    width: 100%;
    padding: 12px 18px;
    border-left: 4px solid transparent;
    background: transparent;
    font: inherit;
    text-align: left;
    transition: background 0.16s ease;
}
.fn-row:hover { background: #f8fafc; }
.fn-row.is-buka { background: #f8fafc; }
.fn-row.is-sempit { border-left-color: #ea580c; background: #fffdfb; }

.fn-ur {
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    width: 24px;
    height: 24px;
    border-radius: 8px;
    background: #eef2ff;
    color: #4f46e5;
    font-size: 0.75rem;
    font-weight: 900;
    line-height: 1 !important;
    text-align: center;
}

.fn-info {
    display: flex;
    flex-direction: column;
    min-width: 0;
}
.fn-lbl {
    font-size: 0.82rem;
    font-weight: 800;
    color: #0f172a;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.fn-lbl .bi { font-size: 11px; color: #94a3b8; margin-left: 4px; }
.fn-sub {
    font-size: 0.7rem;
    color: #64748b;
    font-weight: 600;
    margin-top: 2px;
}

.fn-rel { display: block; height: 12px; border-radius: 999px; background: #f1f5f9; overflow: hidden; padding: 1px; }
.fn-bar-bg { width: 100%; height: 100%; border-radius: 999px; }
.fn-bar {
    display: block;
    height: 100%;
    border-radius: 999px;
    background: #c7d2fe;
    transition: width 0.4s cubic-bezier(0.16, 1, 0.3, 1);
}
.fn-bar__lanjut {
    display: block;
    height: 100%;
    border-radius: 999px;
    background: linear-gradient(90deg, #6366f1, #4f46e5);
    transition: width 0.4s cubic-bezier(0.16, 1, 0.3, 1);
}
.is-sempit .fn-bar { background: #fed7aa; }

.fn-angka-col {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 3px;
}
.fn-capai {
    font-size: 0.84rem;
    color: #475569;
    font-weight: 600;
}
.fn-capai b {
    color: #0f172a;
    font-size: 0.95rem;
    font-weight: 900;
}
.fn-capai small {
    font-size: 0.7rem;
    color: #64748b;
}

.fn-badge-konv {
    font-size: 0.68rem;
    font-weight: 800;
    padding: 2px 7px;
    border-radius: 6px;
}
.fn-konv__nol { font-size: 0.68rem; color: #94a3b8; font-weight: 700; }

.fn-mini-pills {
    display: flex;
    align-items: center;
    gap: 5px;
    flex-wrap: wrap;
}
.fn-pill {
    display: inline-flex;
    align-items: center;
    gap: 3px;
    padding: 2px 7px;
    border-radius: 6px;
    font-size: 0.68rem;
    font-weight: 800;
}
.fn-pill.is-lanjut { background: #ecfdf5; color: #047857; }
.fn-pill.is-proses { background: #f0f9ff; color: #0369a1; }
.fn-pill.is-gugur { background: #fef2f2; color: #b91c1c; }

.fn-chev-btn {
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    width: 26px;
    height: 26px;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    background: #ffffff;
    color: #64748b;
    cursor: pointer;
    transition: all 0.15s ease;
}
.fn-chev-btn:hover {
    background: #f1f5f9;
    color: #0f172a;
}

.fn-rinci {
    display: flex;
    flex-wrap: wrap;
    gap: 8px 18px;
    padding: 10px 18px 14px 56px;
    background: #f8fafc;
    border-top: 1px dashed #e2e8f0;
    font-size: 0.76rem;
    color: #475569;
}
.fn-rinci .bi { font-size: 12px; color: #6366f1; margin-right: 4px; }
.fn-rinci b { color: #0f172a; font-weight: 800; }
.fn-rinci .is-nol { color: #cbd5e1; font-weight: 500; }

.fn-tabel { margin-top: 16px; }
.fn-tabel summary {
    cursor: pointer;
    font-size: 0.78rem;
    font-weight: 800;
    color: #4f46e5;
    padding: 6px 0;
}

@media (max-width: 768px) {
    .fn-row {
        grid-template-columns: 24px 1fr auto 26px;
        row-gap: 8px;
    }
    .fn-rel { grid-column: 1 / -1; }
    .fn-mini-pills { display: none; }
}
</style>
