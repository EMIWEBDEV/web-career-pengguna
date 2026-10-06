<!--
  Penyaring pelamar di dalam papan Spotlight.

  Tiga kelompok, sengaja dipisah karena dipakai pada momen yang berbeda:

   - Baris tetap   : cari nama/kode, posisi, status lamaran, kondisi proses.
                     Ini yang dipakai tiap hari, jadi selalu terlihat.
   - Panel isian   : PENDIDIKAN (jenjang, jenis institusi, kampus/sekolah,
                     jurusan, fakultas, status pendidikan, tahun lulus) dan
                     isian lain milik program itu. Disembunyikan di balik satu
                     tombol supaya baris atas tidak jadi dinding dropdown —
                     tapi apa pun yang sedang aktif tetap muncul sebagai chip,
                     jadi tidak ada penyaring yang bekerja diam-diam.

  Daftar filternya datang dari server (`filterAtribut`), diturunkan dari isian
  formulir program itu sendiri — bukan daftar yang ditulis di sini.
-->
<template>
    <div class="wcm-fp">
        <div class="wcm-fp__baris wcm-fp__baris--top">
            <div class="wca-search2 wcm-fp__cari">
                <i class="bi bi-search"></i>
                <input :value="nilai.cari" type="text" placeholder="Cari nama atau kode lamaran…"
                    @input="ubah('cari', $event.target.value)" />
            </div>

            <span v-if="posisiOpsi.length > 1" class="wcm-fp__sel">
                <el-select :model-value="nilai.posisi" placeholder="Semua Posisi" clearable
                    @update:model-value="ubah('posisi', $event ?? '')">
                    <el-option v-for="p in posisiOpsi" :key="p" :label="p" :value="p" />
                </el-select>
            </span>

            <button v-if="filterAtribut.length" type="button" class="wcm-fp__toggle"
                :class="{ 'is-open': panelBuka, 'is-active': jumlahAtributAktif > 0 }"
                @click="panelBuka = !panelBuka">
                <i class="bi bi-mortarboard-fill"></i>
                Pendidikan &amp; Isian
                <span v-if="jumlahAtributAktif" class="wcm-fp__lencana">{{ jumlahAtributAktif }}</span>
                <i class="bi" :class="panelBuka ? 'bi-chevron-up' : 'bi-chevron-down'"></i>
            </button>

            <div v-if="adaFilter" class="wcm-fp__hasil">
                <span><b>{{ jumlahTampil }}</b>/{{ jumlahTotal }} pelamar</span>
                <button type="button" class="wcm-fp__reset" @click="$emit('reset')">
                    <i class="bi bi-arrow-counterclockwise"></i> Reset
                </button>
            </div>
        </div>

        <div class="wcm-fp__baris wcm-fp__baris--chips">
            <div class="wcm-fp__chip-group">
                <button v-for="s in STATUS" :key="s.val" type="button" class="wcm-fchip"
                    :class="[{ 'is-active': nilai.status === s.val }, `is-${s.val.toLowerCase()}`]"
                    @click="ubah('status', nilai.status === s.val ? '' : s.val)">{{ s.label }}</button>
            </div>

            <span class="wcm-fp__pisah"></span>

            <div class="wcm-fp__chip-group">
                <button v-for="k in KONDISI" :key="k.val" type="button" class="wcm-fchip"
                    :class="[{ 'is-active': nilai.kondisi === k.val }, `is-cond-${k.val.toLowerCase()}`]" :title="k.ket"
                    @click="ubah('kondisi', nilai.kondisi === k.val ? '' : k.val)">
                    <i class="bi" :class="k.ikon"></i> {{ k.label }}
                </button>
            </div>
        </div>

        <!-- Panel isian -->
        <transition name="wcm-fpanel">
            <div v-if="panelBuka" class="wcm-fp__panel">
                <template v-for="g in grup" :key="g.kunci">
                    <div v-if="g.daftar.length" class="wcm-fp__grup">
                        <span class="wcm-fp__grup-lbl"><i class="bi" :class="g.ikon"></i> {{ g.judul }}</span>
                        <div class="wcm-fp__grid">
                            <label v-for="f in g.daftar" :key="f.key" class="wcm-fp__item">
                                <span class="wcm-fp__item-lbl">
                                    <i v-if="f.ikon" class="bi" :class="f.ikon"></i> {{ f.label }}
                                </span>
                                <el-select :model-value="nilai.atribut[f.key] ?? ''" placeholder="Semua" clearable
                                    :filterable="f.cari" :title="f.label"
                                    @update:model-value="ubahAtribut(f.key, $event ?? '')">
                                    <el-option v-for="o in f.opsi" :key="o" :label="String(o)" :value="String(o)" />
                                </el-select>
                            </label>
                        </div>
                    </div>
                </template>
            </div>
        </transition>

        <!-- Ringkasan filter aktif -->
        <div v-if="atributAktif.length" class="wcm-fp__aktif">
            <span class="wcm-fp__aktif-lbl">Filter Aktif:</span>
            <button v-for="a in atributAktif" :key="a.key" type="button" class="wcm-fchip is-active is-hapus"
                :title="`Hapus filter ${a.label}`" @click="ubahAtribut(a.key, '')">
                <i v-if="a.ikon" class="bi" :class="a.ikon"></i>
                <span class="wcm-fp__aktif-key">{{ a.label }}:</span> {{ a.nilai }}
                <i class="bi bi-x"></i>
            </button>
        </div>
    </div>
</template>

<script setup>
import { computed, ref } from 'vue'
import { opsiStatus } from '@utils/career/monitoring'

const props = defineProps({
    nilai: { type: Object, required: true },
    pelamar: { type: Array, default: () => [] },
    filterAtribut: { type: Array, default: () => [] },
    jumlahTampil: { type: Number, default: 0 },
    jumlahTotal: { type: Number, default: 0 },
    adaFilter: { type: Boolean, default: false },
    masterHasil: { type: Object, default: () => ({}) },
})
const emit = defineEmits(['ubah', 'reset'])

const panelBuka = ref(false)

// Dibangun dari master, bukan daftar mati: dulu hanya empat opsi di sini,
// sehingga kandidat yang mundur atau menolak penawaran tidak bisa disaring
// sama sekali — dan "Ditahan" pun tak pernah ada opsinya.
const STATUS = computed(() => opsiStatus(props.masterHasil))

const KONDISI = [
    { val: 'SIAP', label: 'Siap Diputus', ikon: 'bi-hammer', ket: 'Semua hasil terkumpul, menunggu diketuk' },
    { val: 'MACET', label: 'Tertahan Lama', ikon: 'bi-exclamation-octagon', ket: 'Melewati ambang lama menunggu' },
    { val: 'NUNGGU_TES', label: 'Menunggu Tes', ikon: 'bi-hourglass-split', ket: 'Menunggu hasil tes pihak ke-3' },
    { val: 'TALENT', label: 'Dari Talent Pool', ikon: 'bi-droplet', ket: 'Lamaran hasil penarikan talent pool' },
]

const posisiOpsi = computed(() =>
    [...new Set(props.pelamar.map((p) => p.posisi).filter(Boolean))].sort((a, b) => a.localeCompare(b)),
)

const grup = computed(() => [
    {
        kunci: 'pendidikan',
        judul: 'Pendidikan',
        ikon: 'bi-mortarboard-fill',
        daftar: props.filterAtribut.filter((f) => f.grup === 'pendidikan'),
    },
    {
        kunci: 'lain',
        judul: 'Isian Lain',
        ikon: 'bi-ui-checks-grid',
        daftar: props.filterAtribut.filter((f) => f.grup !== 'pendidikan'),
    },
])

const atributAktif = computed(() =>
    props.filterAtribut
        .filter((f) => {
            const v = props.nilai.atribut?.[f.key]
            return v !== '' && v !== null && v !== undefined
        })
        .map((f) => ({ key: f.key, label: f.label, ikon: f.ikon, nilai: props.nilai.atribut[f.key] })),
)

const jumlahAtributAktif = computed(() => atributAktif.value.length)

function ubah(key, val) {
    emit('ubah', { key, val })
}

function ubahAtribut(key, val) {
    emit('ubah', { key: 'atribut', val: { ...props.nilai.atribut, [key]: val } })
}
</script>

<style scoped>
.wcm-fp { display: flex; flex-direction: column; gap: 10px; padding: 12px 24px; background: #ffffff; border-bottom: 1px solid #f1f5f9; }
.wcm-fp__baris { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
.wcm-fp__baris--top { width: 100%; }
.wcm-fp__baris--chips { padding-top: 2px; }
.wcm-fp__cari { flex: 1 1 240px; min-width: 200px; max-width: 360px; }
.wcm-fp__sel { min-width: 170px; }
.wcm-fp__pisah { width: 1px; height: 20px; background: #e2e8f0; margin: 0 4px; }

.wcm-fp__chip-group { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
.wcm-fchip { display: inline-flex; align-items: center; gap: 6px; border: 1px solid #e2e8f0; background: #ffffff; padding: 5px 12px; border-radius: 99px; font-size: 0.73rem; font-weight: 700; color: #475569; cursor: pointer; transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1); }
.wcm-fchip:hover { border-color: #cbd5e1; background: #f8fafc; color: #0f172a; }
.wcm-fchip.is-active { border-color: rgba(99, 102, 241, 0.35); background: #eef2ff; color: #4338ca; }
.wcm-fchip.is-active.is-lulus { border-color: rgba(16, 185, 129, 0.35); background: #ecfdf5; color: #047857; }
.wcm-fchip.is-active.is-gugur { border-color: rgba(244, 63, 94, 0.35); background: #fff1f2; color: #be123c; }
.wcm-fchip.is-active.is-talent_pool { border-color: rgba(14, 165, 233, 0.35); background: #f0f9ff; color: #0369a1; }
.wcm-fchip.is-active.is-cond-siap { border-color: rgba(245, 158, 11, 0.35); background: #fffbeb; color: #d97706; }
.wcm-fchip.is-active.is-cond-macet { border-color: rgba(244, 63, 94, 0.35); background: #fff1f2; color: #be123c; }

.wcm-fp__hasil { margin-left: auto; display: inline-flex; align-items: center; gap: 10px; font-size: 0.73rem; color: #64748b; font-weight: 500; }
.wcm-fp__hasil b { color: #4338ca; font-weight: 800; }
.wcm-fp__reset { display: inline-flex; align-items: center; gap: 5px; border: 1px solid rgba(226, 232, 240, 0.8); background: #f8fafc; color: #4338ca; font-size: 0.71rem; font-weight: 800; cursor: pointer; padding: 4px 10px; border-radius: 99px; transition: all 0.2s ease; }
.wcm-fp__reset:hover { background: #eef2ff; border-color: rgba(99, 102, 241, 0.3); }

/* ── Tombol pembuka panel ── */
.wcm-fp__toggle { display: inline-flex; align-items: center; gap: 6px; border: 1px solid #e2e8f0; background: #ffffff; padding: 7px 14px; border-radius: 12px; font-size: 0.74rem; font-weight: 800; color: #475569; cursor: pointer; transition: all 0.2s ease; }
.wcm-fp__toggle:hover { border-color: #cbd5e1; background: #f8fafc; }
.wcm-fp__toggle.is-open { background: #eef2ff; border-color: rgba(99, 102, 241, 0.35); color: #4338ca; }
.wcm-fp__toggle.is-active { border-color: #6366f1; color: #4338ca; }
.wcm-fp__lencana { display: inline-grid; place-items: center; min-width: 18px; height: 18px; padding: 0 6px; border-radius: 99px; background: #4338ca; color: #ffffff; font-size: 0.65rem; font-weight: 900; }

/* ── Panel isian ── */
.wcm-fp__panel { display: flex; flex-direction: column; gap: 14px; padding: 14px 16px; border: 1px solid #f1f5f9; border-radius: 14px; background: #f8fafc; margin-top: 4px; }
.wcm-fp__grup { display: flex; flex-direction: column; gap: 8px; }
.wcm-fp__grup-lbl { display: inline-flex; align-items: center; gap: 6px; font-size: 0.68rem; font-weight: 800; letter-spacing: 0.05em; text-transform: uppercase; color: #94a3b8; }
.wcm-fp__grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 10px; }
.wcm-fp__item { display: flex; flex-direction: column; gap: 4px; min-width: 0; }
.wcm-fp__item-lbl { display: flex; align-items: center; gap: 5px; font-size: 0.72rem; font-weight: 700; color: #475569; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.wcm-fp__item-lbl .bi { color: #6366f1; }

.wcm-fpanel-enter-active, .wcm-fpanel-leave-active { transition: opacity 160ms ease, transform 160ms ease; }
.wcm-fpanel-enter-from, .wcm-fpanel-leave-to { opacity: 0; transform: translateY(-4px); }

/* ── Ringkasan filter aktif ── */
.wcm-fp__aktif { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; margin-top: 2px; }
.wcm-fp__aktif-lbl { font-size: 0.68rem; font-weight: 800; letter-spacing: 0.05em; text-transform: uppercase; color: #94a3b8; }
.wcm-fp__aktif-key { color: #4338ca; font-weight: 700; }
.wcm-fchip.is-hapus { max-width: 320px; }
.wcm-fchip.is-hapus > span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.wcm-fchip.is-hapus .bi-x { color: #6366f1; }
.wcm-fchip.is-hapus:hover { background: #e0e7ff; }

@media (max-width: 860px) {
    .wcm-fp { padding: 10px 16px; }
    .wcm-fp__cari, .wcm-fp__sel { min-width: 0; flex: 1 1 130px; max-width: none; }
    .wcm-fp__hasil { margin-left: 0; width: 100%; justify-content: space-between; }
    .wcm-fp__grid { grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); }
}
</style>
