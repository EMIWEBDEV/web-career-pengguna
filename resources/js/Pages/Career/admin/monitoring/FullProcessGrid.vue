<!--
  FULL PROCESS — satu baris penuh per pelamar, satu sel per tahap alur.

  Bedanya dengan mode Live: Live menjawab "siapa ada di mana SEKARANG",
  mode ini menjawab "apa yang SUDAH dilalui orang ini, dan berapa lama".
  Mata bisa membaca dua arah — ke kanan untuk perjalanan satu orang, ke bawah
  untuk melihat di tahap mana orang menumpuk atau berguguran.

  BENTUKNYA MATRIKS, BUKAN KANBAN. Kalau tetap kartu di dalam kolom, tinggi
  kolom mengikuti jumlah orang dan 20 pelamar × 5 tahap tidak muat di satu
  layar. Sel kecil memuat jauh lebih banyak tanpa kehilangan informasi.

  YANG PALING PENTING SOAL WARNA: tahap sesudah orang gugur TIDAK dicat merah.
  Di database tahap itu memang tidak pernah dijalani (MENUNGGU, hasil kosong),
  jadi mengecatnya gagal berarti mengarang kegagalan yang tak pernah terjadi.
  Merah hanya muncul SEKALI per baris — di titik ia benar-benar berhenti.
-->
<template>
    <div class="wcm-fg">
        <!-- Kepala kolom: menempel saat digulir ke bawah. -->
        <div class="wcm-fg__head" :style="gridStyle">
            <div class="wcm-fg__hnama">Pelamar</div>
            <button v-for="k in kolom" :key="k.urutan" type="button" class="wcm-fg__hsel"
                :title="`Detail tahap: ${k.label}`" @click="$emit('open-stage', k)">
                <span class="wcm-fg__hno">{{ String(k.urutan).padStart(2, '0') }}</span>
                <span class="wcm-fg__hlbl">{{ k.label }}</span>
                <i v-if="k.provider === 'THIRD_PARTY'" class="bi bi-robot" title="Tahap otomatis pihak ke-3"></i>
            </button>
        </div>

        <!-- Kosong di sini hampir selalu akibat PENYARING, bukan program tanpa
             pelamar — maka pesannya menunjuk ke sana, bukan sekadar "tidak ada". -->
        <KeadaanPanel v-if="!pelamar.length" keadaan="kosong"
            ikon="bi-funnel"
            teks="Tidak ada pelamar yang cocok"
            ket="Tidak ada yang memenuhi penyaring saat ini. Longgarkan atau bersihkan penyaring untuk melihat pelamar lain." />

        <!-- Baris dikelompokkan per status lamaran: yang masih perlu tindakan
             selalu di atas, yang sudah selesai tidak mengganggu pandangan. -->
        <template v-for="g in grup" :key="g.status">
            <div v-if="g.orang.length" class="wcm-fg__grup">
                <span class="wcm-fg__glbl" :class="'st-' + g.status.toLowerCase()">
                    <i class="bi" :class="g.ikon"></i> {{ g.judul }}
                    <b>{{ g.orang.length }}</b>
                </span>
            </div>

            <div v-for="o in g.tampil" :key="o.id" class="wcm-fg__baris" :style="gridStyle"
                :class="{ 'is-open': orangTerbuka === o.id }">
                <button type="button" class="wcm-fg__nama" :title="`Perjalanan ${o.nama}`"
                    @click="$emit('open-person', o.id)">
                    <span class="wca-avatar wca-avatar--sm">{{ initials(o.nama) }}</span>
                    <span class="wcm-fg__ntxt">
                        <span class="wcm-fg__nn">{{ o.nama || '—' }}
                            <i v-if="o.dariTalentPool" class="bi bi-droplet-fill wcm-fg__tp" title="Dari Talent Pool"></i>
                        </span>
                        <span class="wcm-fg__np">{{ o.posisi || '—' }}</span>
                    </span>
                </button>

                <!-- Kunci pakai KODE kolom, bukan nomor: sesudah sel dicocokkan
                     per identitas tahap, dua kolom bisa menunjuk tahap kandidat
                     yang sama dan nomornya jadi kembar dalam satu baris. -->
                <button v-for="(s, si) in o.jejak" :key="s.kode || si" type="button"
                    class="wcm-sel" :class="[`k-${s.keadaan.toLowerCase()}`, { 'is-siap': menungguKetukan(s) }]"
                    :title="judulSel(o, s)" :disabled="!bisaDibuka(s)"
                    :onClick="!bisaDibuka(s) ? null : () => bisaDibuka(s) && $emit('open-cell', { id: o.id, urutan: s.urutan, label: s.label })">
                    <i class="bi" :class="IKON[s.keadaan]"></i>
                    <span v-if="lamaTeks(s)" class="wcm-sel__hari" :class="{ 'is-tua': terlaluLama(s) }">
                        {{ lamaTeks(s) }}
                    </span>
                </button>
            </div>

            <button v-if="g.orang.length > g.tampil.length" :key="g.status + '-more'" type="button"
                class="wcm-fg__lagi" @click="tambah(g.status)">
                <i class="bi bi-chevron-down"></i>
                Muat {{ Math.min(HALAMAN, g.orang.length - g.tampil.length) }} lagi
                <small>({{ g.tampil.length }} dari {{ g.orang.length }} {{ g.judul.toLowerCase() }})</small>
            </button>
        </template>

        <!-- Legenda: kode warna harus bisa dipelajari tanpa menebak. -->
        <div class="wcm-fg__legenda">
            <span v-for="l in LEGENDA" :key="l.k" class="wcm-fg__lg">
                <i class="wcm-sel wcm-sel--mini bi" :class="[IKON[l.k], `k-${l.k.toLowerCase()}`]"></i> {{ l.t }}
            </span>
            <span class="wcm-fg__lgnote">
                <i class="bi bi-clock-history"></i>
                Angka = lama di tahap itu. Tahap yang tuntas di hari yang sama tidak diberi angka —
                arahkan kursor untuk waktu persisnya.
            </span>
        </div>
    </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import { initials } from '@utils/career/admin'
import KeadaanPanel from './KeadaanPanel.vue'

const props = defineProps({
    kolom: { type: Array, default: () => [] },
    pelamar: { type: Array, default: () => [] },   // sudah difilter oleh papan
    orangTerbuka: { type: String, default: null },
    masterHasil: { type: Object, default: () => ({}) },
})
defineEmits(['open-person', 'open-stage', 'open-cell'])

const HALAMAN = 50

const IKON = {
    LULUS: 'bi-check-lg',
    GUGUR: 'bi-x-lg',
    TALENT: 'bi-droplet-fill',
    SEKARANG: 'bi-record-circle',
    SELESAI: 'bi-dash-lg',
    BELUM: 'bi-dot',
    TIDAK_DIJALANI: 'bi-dash',
    TIDAK_ADA: 'bi-question-lg',
}

const LEGENDA = [
    { k: 'LULUS', t: 'Lolos tahap ini' },
    { k: 'SEKARANG', t: 'Sedang di sini' },
    { k: 'GUGUR', t: 'Berhenti di sini' },
    { k: 'TALENT', t: 'Masuk talent pool' },
    { k: 'BELUM', t: 'Belum sampai' },
    { k: 'TIDAK_DIJALANI', t: 'Tidak dijalani' },
]

/**
 * Kelompok baris. Dulu daftar ini mati berisi empat status, dan pengelompokan
 * memakai filter(o.status === g.status) — artinya siapa pun yang statusnya di
 * luar keempatnya TIDAK MASUK kelompok mana pun dan hilang sama sekali dari
 * layar Full Process. Kandidat yang mengundurkan diri lenyap tanpa jejak.
 *
 * Sekarang: empat kelompok dasar tetap, sisanya diturunkan dari master, dan
 * ada jaring pengaman "Status lain" supaya tidak ada satu baris pun yang bisa
 * jatuh di antara celah.
 */
const GRUP_DASAR = [
    { status: 'BERJALAN', judul: 'Masih berproses', ikon: 'bi-play-circle' },
    { status: 'LULUS', judul: 'Diterima', ikon: 'bi-check-circle' },
    { status: 'TALENT_POOL', judul: 'Talent pool', ikon: 'bi-droplet' },
    { status: 'GUGUR', judul: 'Tidak lolos', ikon: 'bi-x-circle' },
]
const STATUS_LAIN = '__LAIN__'

/** Berapa baris yang sudah dibuka per kelompok. */
const batas = ref({})

// Filter berubah → jumlah baris kembali ke halaman pertama, supaya hasil
// penyaringan tidak tertutup sisa "muat lagi" dari daftar sebelumnya.
watch(() => props.pelamar, () => { batas.value = {} })

const gridStyle = computed(() => ({
    gridTemplateColumns: `minmax(190px, 240px) repeat(${props.kolom.length}, minmax(84px, 1fr))`,
}))

/**
 * Di dalam tiap kelompok: yang paling lama menunggu di atas, lalu yang paling
 * jauh perjalanannya. Untuk yang sudah selesai, aging tidak relevan lagi —
 * yang terbaru diputus naik ke atas lewat urutan tahapnya.
 */
function urutkan(a, b) {
    const agingA = a.agingHari ?? -1
    const agingB = b.agingHari ?? -1
    if (agingA !== agingB) return agingB - agingA
    return (b.kolomUrutan || 0) - (a.kolomUrutan || 0)
}

const grup = computed(() => {
    const sudah = new Set(GRUP_DASAR.map((g) => g.status))
    const tambahan = Object.entries(props.masterHasil || {})
        .filter(([kode]) => !sudah.has(kode))
        .map(([kode, m]) => ({ status: kode, judul: m.nama, ikon: m.ikon || 'bi-box-arrow-left' }))

    const daftar = [...GRUP_DASAR, ...tambahan]
    const dikenal = new Set(daftar.map((g) => g.status))
    const sisa = props.pelamar.filter((o) => !dikenal.has(o.status))
    if (sisa.length) {
        daftar.push({ status: STATUS_LAIN, judul: 'Status lain', ikon: 'bi-question-circle' })
    }

    return daftar.map((g) => {
        const orang = (g.status === STATUS_LAIN ? sisa : props.pelamar.filter((o) => o.status === g.status)).sort(urutkan)

        return { ...g, orang, tampil: orang.slice(0, batas.value[g.status] || HALAMAN) }
    })
})

function tambah(status) {
    batas.value = { ...batas.value, [status]: (batas.value[status] || HALAMAN) + HALAMAN }
}

/** Sel yang pernah dijalani saja yang punya detail untuk dibuka. */
function bisaDibuka(s) {
    return !['BELUM', 'TIDAK_DIJALANI', 'TIDAK_ADA'].includes(s.keadaan)
}

/** Di atas ambang ini sebuah tahap layak dilirik, baik masih jalan maupun sudah lewat. */
const AMBANG_LAMA = 7

/**
 * TULISAN LAMA WAKTU DI SEL.
 *
 * Dua hal yang dulu membuatnya sulit dibaca sudah dibereskan di sini:
 *
 * 1. Singkatan "h" dibuang. Di mata pembaca Indonesia "3h" gampang terbaca
 *    "3 jam", dan bagaimanapun ia memakai kosakata yang berbeda dari chip
 *    aging di mode Live ("3 hari"). Sekarang keduanya berkata sama.
 *
 * 2. "0h" tidak ditulis sama sekali untuk tahap yang sudah lewat. Angka itu
 *    tidak menjawab apa pun — tahap yang diputus di hari yang sama memang
 *    tidak tertahan, dan tanda centangnya sudah bercerita. Membiarkannya
 *    justru membuat mata berhenti di sel yang tidak bermasalah. Yang tersisa
 *    hanya angka yang layak dilirik, jadi kolom yang menghambat langsung
 *    menonjol sendiri.
 *
 * Tahap yang SEDANG berjalan tetap selalu menulis angkanya — "sudah berapa
 * lama orang ini menunggu" justru pertanyaan utama atasan, termasuk saat
 * jawabannya "hari ini".
 */
function lamaTeks(s) {
    if (s.hari === null || s.hari === undefined) return ''
    if (s.keadaan === 'SEKARANG') return s.hari <= 0 ? 'hari ini' : `${s.hari} hari`

    return s.hari >= 1 ? `${s.hari} hari` : ''
}

/** Sorot amber: tertahan lama, entah masih berjalan atau ternyata dulu lama. */
function terlaluLama(s) {
    return s.hari !== null && s.hari > AMBANG_LAMA
}

/**
 * Siap_Diputus tidak dibersihkan setelah palu diketuk, jadi bendera itu masih
 * menyala di tahap yang sudah selesai. Menandainya "menunggu keputusan" di sana
 * akan menyuruh atasan mengurus sesuatu yang sudah beres — hanya tahap yang
 * benar-benar sedang berjalan yang diberi cincin.
 */
function menungguKetukan(s) {
    return s.siapDiputus && s.keadaan === 'SEKARANG'
}

const KATA = {
    LULUS: 'lolos',
    GUGUR: 'berhenti di sini',
    TALENT: 'masuk talent pool di sini',
    SEKARANG: 'sedang di tahap ini',
    SELESAI: 'selesai tanpa hasil tercatat',
    BELUM: 'belum sampai ke tahap ini',
    TIDAK_DIJALANI: 'tidak dijalani — proses sudah berhenti sebelumnya',
    TIDAK_ADA: 'tahap ini tidak ada pada lamaran tsb (alur berubah setelah melamar)',
}

function judulSel(o, s) {
    const bagian = [`${o.nama} · ${s.label}`, KATA[s.keadaan] || s.keadaan]
    // Tooltip TETAP menyebut lamanya walau selnya tidak menuliskannya —
    // yang disembunyikan di sel adalah kebisingannya, bukan datanya.
    if (s.hari !== null && s.hari !== undefined) {
        const lama = s.hari >= 1 ? `${s.hari} hari` : 'kurang dari sehari'
        bagian.push(s.keadaan === 'SEKARANG' ? `${lama} di tahap ini` : lama)
    }
    if (s.skor !== null && s.skor !== undefined) bagian.push(`skor ${s.skor}`)
    if (menungguKetukan(s)) bagian.push('siap diputus')
    if (s.selesai) bagian.push(`diputus ${s.selesai}`)

    return bagian.join(' — ')
}
</script>

<style scoped>
/* Wadah gulir sendiri: kepala kolom menempel ke atas dan kolom nama menempel
   ke kiri, jadi alur 9 tahap tetap terbaca sambil digeser ke mana pun. */
.wcm-fg { height: 100%; overflow: auto; display: flex; flex-direction: column; gap: 2px; padding-bottom: 18px; }

/* ── Kepala kolom ── */
.wcm-fg__head { display: grid; gap: 6px; position: sticky; top: 0; z-index: 3; padding: 6px 0 8px; background: linear-gradient(180deg, #fff 78%, rgba(255, 255, 255, 0)); }
.wcm-fg__hnama { position: sticky; left: 0; z-index: 2; background: #fff; display: flex; align-items: flex-end; font-size: 10.5px; font-weight: 800; letter-spacing: 0.06em; text-transform: uppercase; color: #94a3b8; padding-bottom: 3px; }
.wcm-fg__hsel { display: flex; flex-direction: column; align-items: flex-start; gap: 1px; border: 0; border-bottom: 2px solid #eef0f7; background: transparent; padding: 2px 4px 5px; cursor: pointer; text-align: left; min-width: 0; }
.wcm-fg__hsel:hover { border-bottom-color: #a5b4fc; }
.wcm-fg__hno { font-size: 9.5px; font-weight: 800; color: #c7d2fe; letter-spacing: 0.08em; }
.wcm-fg__hlbl { font-size: 11px; font-weight: 700; color: #334155; line-height: 1.25; overflow: hidden; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; }
.wcm-fg__hsel .bi-robot { font-size: 10px; color: #94a3b8; }

/* ── Kelompok status ── */
.wcm-fg__grup { padding: 12px 0 4px; position: sticky; left: 0; }
.wcm-fg__glbl { display: inline-flex; align-items: center; gap: 6px; font-size: 10.5px; font-weight: 800; letter-spacing: 0.05em; text-transform: uppercase; color: #94a3b8; }
.wcm-fg__glbl b { color: #475569; font-size: 11.5px; }
.wcm-fg__glbl.st-berjalan { color: #4338ca; }
.wcm-fg__glbl.st-lulus { color: #059669; }
.wcm-fg__glbl.st-gugur { color: #b91c1c; }
.wcm-fg__glbl.st-talent_pool { color: #0369a1; }

/* ── Baris ── */
.wcm-fg__baris { display: grid; gap: 6px; align-items: stretch; border-radius: 10px; padding: 3px 0; }
.wcm-fg__baris:hover { background: #f8faff; }
.wcm-fg__baris.is-open { background: #eef2ff; }

.wcm-fg__nama { position: sticky; left: 0; z-index: 1; display: flex; align-items: center; gap: 8px; border: 0; background: inherit; padding: 4px 8px 4px 4px; cursor: pointer; text-align: left; min-width: 0; border-radius: 9px; }
.wcm-fg__nama:hover { background: #eef2ff; }
.wcm-fg__ntxt { display: flex; flex-direction: column; min-width: 0; }
.wcm-fg__nn { font-size: 12px; font-weight: 700; color: #0f172a; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.wcm-fg__np { font-size: 10.5px; color: #94a3b8; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.wcm-fg__tp { color: #38bdf8; font-size: 9.5px; }

/* ── Sel ── */
.wcm-sel { display: flex; align-items: center; justify-content: center; gap: 4px; min-height: 34px; border: 1px solid transparent; border-radius: 9px; background: #f6f7fb; color: #94a3b8; font-size: 12px; cursor: pointer; padding: 0 6px; transition: transform 120ms ease, box-shadow 120ms ease; }
.wcm-sel:disabled { cursor: default; }
.wcm-sel:not(:disabled):hover { transform: translateY(-1px); box-shadow: 0 4px 12px -6px rgba(15, 23, 42, 0.35); }
.wcm-sel__hari { font-size: 10.5px; font-weight: 700; opacity: 0.85; white-space: nowrap; }
.wcm-sel__hari.is-tua { color: #b45309; opacity: 1; }

.wcm-sel.k-lulus { background: rgba(16, 185, 129, 0.12); color: #047857; border-color: rgba(16, 185, 129, 0.25); }
.wcm-sel.k-gugur { background: rgba(239, 68, 68, 0.12); color: #b91c1c; border-color: rgba(239, 68, 68, 0.28); }
.wcm-sel.k-talent { background: rgba(56, 189, 248, 0.14); color: #0369a1; border-color: rgba(56, 189, 248, 0.3); }
.wcm-sel.k-sekarang { background: rgba(79, 70, 229, 0.12); color: #4338ca; border-color: rgba(79, 70, 229, 0.35); box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.08); }
.wcm-sel.k-selesai { background: #eef0f7; color: #64748b; }
/* Belum sampai vs tidak dijalani: sama-sama pucat karena sama-sama BUKAN
   kegagalan. Dibedakan garis putus supaya tetap bisa ditelusuri. */
.wcm-sel.k-belum { background: #fbfcfe; color: #cbd5e1; border-style: dashed; border-color: #eef0f7; }
.wcm-sel.k-tidak_dijalani { background: repeating-linear-gradient(135deg, #fbfcfe, #fbfcfe 5px, #f4f6fa 5px, #f4f6fa 10px); color: #cbd5e1; border-style: dashed; border-color: #eef0f7; }
.wcm-sel.k-tidak_ada { background: #fffbeb; color: #d97706; border-style: dashed; border-color: #fde68a; }
.wcm-sel.is-siap { box-shadow: inset 0 0 0 2px rgba(245, 158, 11, 0.5); }

/* ── Muat lagi & legenda ── */
.wcm-fg__lagi { position: sticky; left: 0; align-self: flex-start; display: inline-flex; align-items: center; gap: 6px; margin: 6px 0 2px; border: 1px dashed #c7d2fe; border-radius: 10px; background: #fff; padding: 7px 13px; font-size: 11.5px; font-weight: 700; color: #4338ca; cursor: pointer; }
.wcm-fg__lagi:hover { background: #eef2ff; }
.wcm-fg__lagi small { font-weight: 500; color: #94a3b8; }


.wcm-fg__legenda { position: sticky; left: 0; display: flex; flex-wrap: wrap; align-items: center; gap: 14px; margin-top: 16px; padding-top: 12px; border-top: 1px solid #eef0f7; font-size: 11px; color: #94a3b8; }
.wcm-fg__lg { display: inline-flex; align-items: center; gap: 6px; }
.wcm-fg__lgnote { display: inline-flex; align-items: center; gap: 6px; padding-left: 14px; border-left: 1px solid #eef0f7; color: #b6bfcd; }
.wcm-sel--mini { min-height: 20px; min-width: 24px; font-size: 10px; padding: 0; }

@media (max-width: 860px) {
    .wcm-fg__hlbl { -webkit-line-clamp: 3; }
}
</style>
