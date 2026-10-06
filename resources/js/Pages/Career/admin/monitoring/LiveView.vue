<!--
  Tab LIVE VIEW — lapis 1 "Overview": denyut rekrutmen (narasi + sinyal),
  panel Perlu Perhatian, dan grid kartu program berskor kesehatan.
  Klik kartu (atau item perhatian) → lapis 2 SpotlightBoard: papan proses
  per program berisi siapa ada di tahap mana.
-->
<template>
    <div>
        <!-- Skeleton load pertama -->
        <div v-if="loading && !ready" class="wca-card">
            <KeadaanPanel keadaan="memuat" teks="Memuat data monitoring…" />
        </div>

        <div v-else-if="error" class="wca-card">
            <KeadaanPanel keadaan="galat"
                teks="Gagal memuat data monitoring"
                ket="Data program dan pelamar tidak berhasil diambil. Periksa koneksi lalu coba lagi."
                @ulang="fetchLive" />
        </div>

        <template v-else>
            <!-- 🏛️ NATIVE WEB CAREER KPI CARDS GRID -->
            <div class="wca-kpi-grid mb-4" :class="{ 'wcm-dim': loading }">
                <div class="wca-kpi-card wca-kpi-card--indigo">
                    <div class="wca-kpi-card__top">
                        <span class="wca-kpi-card__icon"><i class="bi bi-lightning-charge-fill"></i></span>
                        <span class="wca-kpi-card__label">Berproses</span>
                    </div>
                    <div class="wca-kpi-card__value">{{ kpi.aktif }}</div>
                    <div class="wca-kpi-card__sub">Pelamar aktif berproses</div>
                </div>

                <div class="wca-kpi-card wca-kpi-card--amber">
                    <div class="wca-kpi-card__top">
                        <span class="wca-kpi-card__icon"><i class="bi bi-hammer"></i></span>
                        <span class="wca-kpi-card__label">Siap Diputus</span>
                    </div>
                    <div class="wca-kpi-card__value text-amber">{{ kpi.siapDiputus }}</div>
                    <div class="wca-kpi-card__sub">Butuh keputusan atasan</div>
                </div>

                <div class="wca-kpi-card wca-kpi-card--purple">
                    <div class="wca-kpi-card__top">
                        <span class="wca-kpi-card__icon"><i class="bi bi-hourglass-split"></i></span>
                        <span class="wca-kpi-card__label">Menunggu Tes</span>
                    </div>
                    <div class="wca-kpi-card__value text-purple">{{ kpi.menungguTes }}</div>
                    <div class="wca-kpi-card__sub">Menunggu hasil pihak 3</div>
                </div>

                <div class="wca-kpi-card wca-kpi-card--success">
                    <div class="wca-kpi-card__top">
                        <span class="wca-kpi-card__icon"><i class="bi bi-check-circle-fill"></i></span>
                        <span class="wca-kpi-card__label">Diterima</span>
                    </div>
                    <div class="wca-kpi-card__value text-emerald">{{ kpi.lulus }}</div>
                    <div class="wca-kpi-card__sub">Lolos seleksi akhir</div>
                </div>

                <div class="wca-kpi-card wca-kpi-card--danger">
                    <div class="wca-kpi-card__top">
                        <span class="wca-kpi-card__icon"><i class="bi bi-x-circle-fill"></i></span>
                        <span class="wca-kpi-card__label">Tidak Lolos</span>
                    </div>
                    <div class="wca-kpi-card__value text-rose">{{ kpi.gugur }}</div>
                    <div class="wca-kpi-card__sub">Gugur dalam alur</div>
                </div>

                <div class="wca-kpi-card wca-kpi-card--sky">
                    <div class="wca-kpi-card__top">
                        <span class="wca-kpi-card__icon"><i class="bi bi-droplet-fill"></i></span>
                        <span class="wca-kpi-card__label">Talent Pool</span>
                    </div>
                    <div class="wca-kpi-card__value text-sky">{{ kpi.talentPool }}</div>
                    <div class="wca-kpi-card__sub">Masuk talent pool</div>
                </div>

                <!-- Diakhiri KANDIDAT, bukan oleh kita. Dipisah dari "Tidak
                     Lolos" karena menuntut tindak lanjut yang berbeda: yang satu
                     soal kualifikasi, yang satu soal daya saing tawaran. -->
                <div class="wca-kpi-card wca-kpi-card--slate">
                    <div class="wca-kpi-card__top">
                        <span class="wca-kpi-card__icon"><i class="bi bi-box-arrow-left"></i></span>
                        <span class="wca-kpi-card__label">Keluar</span>
                    </div>
                    <div class="wca-kpi-card__value text-purple">{{ kpi.keluar }}</div>
                    <div class="wca-kpi-card__sub">Mundur / menolak penawaran</div>
                </div>

                <div class="wca-kpi-card wca-kpi-card--slate">
                    <div class="wca-kpi-card__top">
                        <span class="wca-kpi-card__icon"><i class="bi bi-pause-circle-fill"></i></span>
                        <span class="wca-kpi-card__label">Ditahan</span>
                    </div>
                    <div class="wca-kpi-card__value">{{ kpi.ditahan }}</div>
                    <div class="wca-kpi-card__sub">Sedang di-hold, tidak masuk antrean</div>
                </div>

                <div class="wca-kpi-card wca-kpi-card--emerald">
                    <div class="wca-kpi-card__top">
                        <span class="wca-kpi-card__icon"><i class="bi bi-file-earmark-check-fill"></i></span>
                        <span class="wca-kpi-card__label">Proses Administrasi</span>
                    </div>
                    <div class="wca-kpi-card__value">{{ kpi.pascaPenerimaan }}</div>
                    <div class="wca-kpi-card__sub">Diterima, tahap kontrak/onboarding</div>
                </div>
            </div>

            <PerluPerhatian :items="perhatianTampil" :meta="meta" :class="{ 'wcm-dim': loading }" @open="bukaDariPerhatian" />

            <FilterProgram :nilai="filter" :programs="programs"
                :jumlah-tampil="programsUrut.length" :jumlah-total="programs.length" :ada-filter="adaFilter"
                @ubah="filter[$event.key] = $event.val" @reset="resetFilter" />

            <!-- Dua sebab kosong yang BERBEDA: tersaring habis (bisa dibersihkan)
                 versus memang belum ada program sama sekali (tak ada yang bisa
                 dilakukan di sini). Pesan & tombolnya ikut berbeda. -->
            <div v-if="!programsUrut.length" class="wca-card">
                <KeadaanPanel keadaan="kosong"
                    :ikon="programs.length ? 'bi-funnel' : 'bi-inbox'"
                    :teks="programs.length ? 'Tidak ada program yang cocok' : 'Belum ada program rekrutmen'"
                    :ket="programs.length
                        ? 'Tidak ada program yang memenuhi penyaring saat ini.'
                        : 'Belum ada program yang berjalan. Program baru akan muncul di sini begitu dibuat dan dijalankan.'" />
                <div v-if="adaFilter" class="wcm-kosong__aksi">
                    <button class="wca-btn wca-btn--ghost wca-btn--sm" @click="resetFilter">
                        <i class="bi bi-x-circle"></i> Bersihkan filter
                    </button>
                </div>
            </div>

            <div v-else class="wcm-grid" :class="{ 'wcm-dim': loading }">
                <ProgramTile v-for="p in programsUrut" :key="p.id" :program="p" @open="bukaSpotlight(p.id)" />
            </div>
        </template>

        <SpotlightBoard v-if="spotlightId" ref="spotRef" :key="spotlightId"
            :program-id="spotlightId" :fokus-lamaran-id="fokusLamaranId"
            @close="tutupSpotlight" />
    </div>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import axios from 'axios'
import PerluPerhatian from './PerluPerhatian.vue'
import ProgramTile from './ProgramTile.vue'
import SpotlightBoard from './SpotlightBoard.vue'
import FilterProgram from './FilterProgram.vue'
import KeadaanPanel from './KeadaanPanel.vue'

const emit = defineEmits(['checkpoint'])

const CFG = { headers: { Accept: 'application/json' } }
const FILTER_KOSONG = { cari: '', status: '', kategori: '', alur: '', penyelenggara: '', kondisi: '', urut: 'perhatian' }

const filter = ref({ ...FILTER_KOSONG })
const loading = ref(false)
const ready = ref(false)
const error = ref(false)
const perhatian = ref([])
const programs = ref([])
const meta = ref({ macetHari: 7, sorotHari: 2 })
const spotlightId = ref(null)
const fokusLamaranId = ref(null)
const spotRef = ref(null)

const adaFilter = computed(() =>
    Object.keys(FILTER_KOSONG).some((k) => k !== 'urut' && filter.value[k] !== FILTER_KOSONG[k]),
)

/** Jumlah pelamar per hasil akhir, dibaca dari funnel program. */
function hasilProgram(p) {
    return (p.tahap || []).reduce(
        (a, t) => ({
            aktif: a.aktif + t.aktif, lulus: a.lulus + t.lulus,
            gugur: a.gugur + t.gugur, talent: a.talent + t.talent,
            // Tanpa ini, KPI header menjumlah lebih kecil daripada total
            // pelamar dan tidak ada yang bisa menjelaskan selisihnya.
            keluar: a.keluar + (t.keluar ?? 0),
        }),
        { aktif: 0, lulus: 0, gugur: 0, talent: 0, keluar: 0 },
    )
}

function cocokKondisi(p, kondisi) {
    const s = p.sehat || {}
    const h = hasilProgram(p)
    return {
        SIAP: s.siapDiputus > 0,
        MACET: s.macet > 0,
        NUNGGU_TES: s.menungguTes > 0,
        AKTIF: h.aktif > 0,
        KUOTA_PENUH: p.kuota > 0 && p.terisi >= p.kuota,
        KOSONG: (p.totalPelamar ?? 0) === 0,
        DITAHAN: (p.ditahan ?? 0) > 0,
        PASCAPENERIMAAN: (p.pascaPenerimaan ?? 0) > 0,
    }[kondisi] ?? true
}

const programsTerfilter = computed(() => {
    const f = filter.value
    const cari = f.cari.trim().toLowerCase()

    return programs.value.filter((p) => {
        if (cari && ![p.nama, p.kode, p.penyelenggara].some((v) => (v || '').toLowerCase().includes(cari))) return false
        if (f.status && p.status !== f.status) return false
        if (f.kategori && p.kategori !== f.kategori) return false
        if (f.alur && p.alur !== f.alur) return false
        if (f.penyelenggara && p.penyelenggara !== f.penyelenggara) return false
        if (f.kondisi && !cocokKondisi(p, f.kondisi)) return false

        return true
    })
})

const programsUrut = computed(() => {
    const arr = [...programsTerfilter.value]
    const u = filter.value.urut
    if (u === 'nama') return arr.sort((a, b) => a.nama.localeCompare(b.nama))
    if (u === 'sibuk') return arr.sort((a, b) => hasilProgram(b).aktif - hasilProgram(a).aktif)
    if (u === 'terisi') {
        const pct = (p) => (p.kuota > 0 ? p.terisi / p.kuota : -1)
        return arr.sort((a, b) => pct(b) - pct(a))
    }
    // "perhatian": skor terendah dulu, program tanpa proses (skor null) di belakang.
    return arr.sort((a, b) => {
        const sa = a.sehat?.skor, sb = b.sehat?.skor
        if (sa === null && sb === null) return (b.totalPelamar ?? 0) - (a.totalPelamar ?? 0)
        if (sa === null) return 1
        if (sb === null) return -1
        return sa - sb
    })
})

/** Angka ringkasan dihitung ULANG dari program yang lolos filter, supaya
 *  header selalu selaras dengan apa yang sedang dilihat. */
const kpi = computed(() =>
    programsTerfilter.value.reduce((a, p) => {
        const h = hasilProgram(p)
        const s = p.sehat || {}

        return {
            aktif: a.aktif + h.aktif,
            lulus: a.lulus + h.lulus,
            gugur: a.gugur + h.gugur,
            talentPool: a.talentPool + h.talent,
            keluar: a.keluar + h.keluar,
            siapDiputus: a.siapDiputus + (s.siapDiputus ?? 0),
            menungguTes: a.menungguTes + (s.menungguTes ?? 0),
            ditahan: a.ditahan + (p.ditahan ?? 0),
            pascaPenerimaan: a.pascaPenerimaan + (p.pascaPenerimaan ?? 0),
        }
    }, { aktif: 0, lulus: 0, gugur: 0, talentPool: 0, keluar: 0, siapDiputus: 0, menungguTes: 0, ditahan: 0, pascaPenerimaan: 0 }),
)

/** Panel atensi ikut menyempit mengikuti program yang sedang ditampilkan. */
const perhatianTampil = computed(() => {
    if (!adaFilter.value) return perhatian.value
    const id = new Set(programsTerfilter.value.map((p) => p.id))

    return perhatian.value.filter((x) => id.has(x.programId))
})

function resetFilter() {
    filter.value = { ...FILTER_KOSONG, urut: filter.value.urut }
}

async function fetchLive() {
    loading.value = true
    error.value = false
    try {
        // Seluruh program diambil sekali; penyaringan dikerjakan di layar.
        const { data } = await axios.get('/api/v1/karir/monitoring/live', { ...CFG })
        const r = data.result || {}
        perhatian.value = r.perhatian || []
        programs.value = r.programs || []
        meta.value = r.meta || meta.value
        if (r.checkpoint) emit('checkpoint', r.checkpoint)
        ready.value = true
    } catch (e) {
        if (!ready.value) error.value = true
    } finally {
        loading.value = false
    }
    // Papan yang sedang terbuka ikut disegarkan agar checkpoint-nya sejalan.
    await spotRef.value?.refresh?.()
}

function bukaSpotlight(id) {
    fokusLamaranId.value = null
    spotlightId.value = id
}

/** Klik item Perlu Perhatian → langsung papan program + drawer orangnya. */
function bukaDariPerhatian(item) {
    if (!item.programId) return
    fokusLamaranId.value = item.lamaranId
    spotlightId.value = item.programId
}

function tutupSpotlight() {
    spotlightId.value = null
    fokusLamaranId.value = null
}

onMounted(fetchLive)
defineExpose({ refresh: fetchLive })
</script>

<style scoped>
/* 🏛️ NATIVE WEB CAREER KPI GRID & CARDS */
.wca-kpi-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
    gap: 14px;
    margin-bottom: 20px;
}
.wca-kpi-card {
    padding: 16px 18px;
    border-radius: 16px;
    background: #ffffff;
    border: 1px solid rgba(226, 232, 240, 0.95);
    box-shadow: 0 4px 16px rgba(15, 23, 42, 0.04);
    transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
}
.wca-kpi-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 24px rgba(15, 23, 42, 0.08);
}
.wca-kpi-card__top {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 8px;
}
.wca-kpi-card__icon {
    width: 32px;
    height: 32px;
    border-radius: 10px;
    display: grid;
    place-items: center;
    font-size: 0.9rem;
    flex-shrink: 0;
}
.wca-kpi-card--indigo .wca-kpi-card__icon { background: rgba(99, 102, 241, 0.12); color: #6366f1; }
.wca-kpi-card--amber .wca-kpi-card__icon { background: rgba(245, 158, 11, 0.14); color: #f59e0b; }
.wca-kpi-card--purple .wca-kpi-card__icon { background: rgba(168, 85, 247, 0.14); color: #a855f7; }
.wca-kpi-card--success .wca-kpi-card__icon { background: rgba(16, 185, 129, 0.14); color: #10b981; }
.wca-kpi-card--danger .wca-kpi-card__icon { background: rgba(244, 63, 94, 0.14); color: #f43f5e; }
.wca-kpi-card--sky .wca-kpi-card__icon { background: rgba(14, 165, 233, 0.14); color: #0ea5e9; }
.wca-kpi-card--slate .wca-kpi-card__icon { background: rgba(100, 116, 139, 0.14); color: #64748b; }
.wca-kpi-card--emerald .wca-kpi-card__icon { background: rgba(16, 185, 129, 0.14); color: #10b981; }

.wca-kpi-card__label {
    font-size: 0.76rem;
    font-weight: 700;
    color: #64748b;
}
.wca-kpi-card__value {
    font-size: 1.65rem;
    font-weight: 900;
    color: #0f172a;
    line-height: 1.1;
    font-variant-numeric: tabular-nums;
}
.text-amber { color: #d97706; }
.text-purple { color: #9333ea; }
.text-emerald { color: #059669; }
.text-rose { color: #e11d48; }
.text-sky { color: #0284c7; }

.wca-kpi-card__sub {
    font-size: 0.7rem;
    color: #94a3b8;
    margin-top: 4px;
    font-weight: 600;
}

.wcm-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 16px; }
/* Penyegaran otomatis: konten diredupkan seperlunya saja — cukup untuk
   menandakan "sedang diperbarui" tanpa membuat angka jadi sulit dibaca.
   0,55 terlalu pucat untuk data yang justru sedang dipelototi atasan. */
.wcm-dim { opacity: 0.72; pointer-events: none; transition: opacity 0.15s; }

.wcm-kosong__aksi { display: flex; justify-content: center; padding: 0 1rem 2rem; margin-top: -1.4rem; }

@media (max-width: 720px) {
    .wca-kpi-grid { grid-template-columns: repeat(2, 1fr); }
    .wca-kpi-card__value { font-size: 1.35rem; }
}
</style>
