<!--
  Drawer kanan detail SATU TAHAP (klik header kolom di papan Spotlight).
  Empat blok: Statistik tahap · Daftar orang (dari payload papan, urut aging)
  · Rapor sub-tes agregat + siapa belum submit · Sesi penjadwalan terkait.
-->
<template>
    <teleport to="body">
        <div class="wcm-dw">
            <aside class="wcm-dw__panel" role="dialog" aria-label="Detail tahap">
                <header class="wcm-dw__head">
                    <div>
                        <div class="wcm-dw__ttl"><i class="bi bi-layers"></i> Tahap {{ String(kolom.urutan).padStart(2, '0') }}</div>
                        <div class="wcm-dw__sub">{{ kolom.label }}<span v-if="kolom.provider === 'THIRD_PARTY'"> · otomatis pihak ke-3</span></div>
                    </div>
                    <button class="wca-iconbtn" title="Tutup (Esc)" @click="$emit('close')"><i class="bi bi-x-lg"></i></button>
                </header>

                <div class="wcm-dw__body">
                    <KeadaanPanel v-if="loading" keadaan="memuat" rapat teks="Memuat detail tahap…" />
                    <KeadaanPanel v-else-if="error" keadaan="galat" rapat
                        teks="Gagal memuat detail tahap"
                        ket="Statistik dan daftar peserta tahap ini tidak berhasil diambil."
                        @ulang="fetchDetail" />

                    <template v-else>
                        <!-- 1. STATISTIK -->
                        <section class="wcm-sd__sec">
                            <div class="wcm-sd__ttl"><i class="bi bi-bar-chart"></i> Statistik Tahap</div>
                            <div class="wcm-sd__stats">
                                <div class="wcm-sd__stat"><b>{{ stats.aktif }}</b><span>Sedang di sini</span></div>
                                <div class="wcm-sd__stat is-hold"><b>{{ stats.ditahan }}</b><span>Ditahan</span></div>
                                <div class="wcm-sd__stat is-green"><b>{{ stats.lulus }}</b><span>Lolos tahap</span></div>
                                <div class="wcm-sd__stat is-red"><b>{{ stats.gugur }}</b><span>Gugur di sini</span></div>
                                <div class="wcm-sd__stat is-sky"><b>{{ stats.talent }}</b><span>Talent Pool</span></div>
                                <div v-if="stats.keluar" class="wcm-sd__stat is-violet">
                                    <b>{{ stats.keluar }}</b>
                                    <span>Keluar</span>
                                </div>
                            </div>
                            <div class="wcm-sd__facts">
                                <span v-if="stats.konversi !== null" class="wcm-fact">
                                    <i class="bi bi-funnel"></i> Konversi <b>{{ stats.konversi }}%</b> yang lolos dari yang sudah diputus
                                </span>
                                <!-- "Keluar karena apa" — mundur dan menolak
                                     penawaran menuntut tindak lanjut berbeda,
                                     jadi angkanya tidak berhenti di gabungan. -->
                                <span v-for="k in rincianKeluar" :key="k.kode" class="wcm-fact">
                                    <i class="bi bi-box-arrow-left" :style="k.warna ? { color: k.warna } : null"></i>
                                    <b>{{ k.jml }}</b> {{ k.nama }}
                                </span>
                                <span v-if="stats.siapDiputus > 0" class="wcm-fact is-warn">
                                    <i class="bi bi-hammer"></i> <b>{{ stats.siapDiputus }}</b> siap diputus
                                </span>
                                <span v-if="stats.macet > 0" class="wcm-fact is-red">
                                    <i class="bi bi-exclamation-octagon"></i> <b>{{ stats.macet }}</b> tertahan &gt; {{ meta.macetHari }} hari
                                </span>
                                <span v-if="stats.avgAging !== null" class="wcm-fact">
                                    <i class="bi bi-clock-history"></i> Rata-rata di tahap ini <b>{{ stats.avgAging }} hari</b><template v-if="stats.maxAging !== null"> (terlama {{ stats.maxAging }})</template>
                                </span>
                                <span v-if="stats.avgSkor !== null" class="wcm-fact">
                                    <i class="bi bi-graph-up"></i> Rata-rata skor tahap <b>{{ stats.avgSkor }}</b>
                                </span>
                            </div>
                        </section>

                        <!-- 2. DAFTAR ORANG (dari papan — tanpa query tambahan) -->
                        <section class="wcm-sd__sec">
                            <div class="wcm-sd__ttl"><i class="bi bi-people"></i> Pelamar di Tahap Ini
                                <span class="wcm-sd__n">{{ orangUrut.length }}</span>
                            </div>
                            <KeadaanPanel v-if="!orangUrut.length" keadaan="kosong" rapat
                                ikon="bi-people"
                                teks="Belum ada pelamar di tahap ini"
                                ket="Belum ada kandidat yang sampai ke tahap ini, atau semuanya sudah melewatinya." />
                            <div v-else class="wcm-sd__orang">
                                <button v-for="o in orangUrut" :key="o.id" type="button" class="wcm-orang" @click="$emit('open-person', o.id)">
                                    <span class="wca-avatar wca-avatar--sm">{{ initials(o.nama) }}</span>
                                    <span class="wcm-orang__txt">
                                        <span class="wcm-orang__nama">{{ o.nama || '—' }}</span>
                                        <span class="wcm-orang__sub">{{ o.posisi || '—' }}<template v-if="o.agingHari !== null"> · {{ formatAging(o.agingHari) }}</template></span>
                                    </span>
                                    <span class="wca-badge" :class="toneClass(o.badge.tone)">{{ o.badge.teks }}</span>
                                </button>
                            </div>
                        </section>

                        <!-- 3. RAPOR SUB-TES -->
                        <section v-if="subtes.length" class="wcm-sd__sec">
                            <div class="wcm-sd__ttl"><i class="bi bi-clipboard-check"></i> Rapor Sub-Tes</div>
                            <div v-for="(t, i) in subtes" :key="i" class="wcm-tes">
                                <div class="wcm-tes__head">
                                    <b>{{ t.label || '—' }}</b>
                                    <span v-if="t.peran === 'INFORMATIF'" class="wcm-tes__tag">informatif</span>
                                    <span v-if="t.provider === 'THIRD_PARTY'" class="wcm-tes__tag is-sys"><i class="bi bi-robot"></i> sistem</span>
                                </div>
                                <div class="wcm-tes__bar" :title="`${t.selesai} selesai, ${t.belum} belum, ${t.tidakHadir} tidak hadir`">
                                    <span class="b-selesai" :style="{ flex: t.selesai || 0 }"></span>
                                    <span class="b-belum" :style="{ flex: t.belum || 0 }"></span>
                                    <span class="b-absen" :style="{ flex: t.tidakHadir || 0 }"></span>
                                </div>
                                <div class="wcm-tes__meta">
                                    {{ t.selesai }}/{{ t.jml }} selesai<template v-if="t.tidakHadir"> · {{ t.tidakHadir }} tidak hadir</template>
                                    <template v-if="t.hasilLulus || t.hasilGagal"> · {{ t.hasilLulus }} lulus / {{ t.hasilGagal }} gagal</template>
                                    <template v-if="t.avgNilai !== null"> · nilai rata-rata <b>{{ t.avgNilai }}</b> (min {{ t.minNilai }}, maks {{ t.maxNilai }})</template>
                                </div>
                            </div>

                            <div v-if="belumSubmit.length" class="wcm-belum">
                                <div class="wcm-belum__ttl"><i class="bi bi-hourglass"></i> Belum menuntaskan tes</div>
                                <button v-for="(b, i) in belumSubmit" :key="i" type="button" class="wcm-belum__row" @click="$emit('open-person', b.id)">
                                    <span>{{ b.nama || '—' }}</span>
                                    <span class="wcm-belum__tes">{{ b.tes }}</span>
                                    <span class="wca-badge" :class="b.status === 'TIDAK_HADIR' ? 'wca-b--red' : 'wca-b--slate'">{{ b.status }}</span>
                                </button>
                            </div>
                        </section>

                        <!-- 4. JADWAL -->
                        <section v-if="jadwal.length" class="wcm-sd__sec">
                            <div class="wcm-sd__ttl"><i class="bi bi-calendar-event"></i> Sesi Terjadwal</div>
                            <div v-for="(j, i) in jadwal" :key="i" class="wcm-jdw">
                                <div class="wcm-jdw__head">
                                    <b>{{ j.namaUjian || j.label || j.nama }}</b>
                                    <span class="wca-badge" :class="jadwalBadge(j.status)">{{ j.status }}</span>
                                </div>
                                <div class="wcm-jdw__meta">{{ j.nama }}<span v-if="j.kode"> · {{ j.kode }}</span></div>
                                <div class="wcm-jdw__meta">
                                    <template v-if="j.waktuMulai"><i class="bi bi-clock"></i> {{ formatTanggal(j.waktuMulai) }}<template v-if="j.waktuAkhir"> — {{ formatTanggal(j.waktuAkhir) }}</template></template>
                                    <template v-else-if="j.tanggalMulai"><i class="bi bi-calendar3"></i> {{ formatTanggal(j.tanggalMulai) }}<template v-if="j.tanggalSelesai"> — {{ formatTanggal(j.tanggalSelesai) }}</template></template>
                                    <template v-if="j.durasiMenit"> · {{ j.durasiMenit }} menit</template>
                                    <template v-if="j.ambangNilai !== null"> · ambang {{ j.ambangNilai }}</template>
                                </div>
                                <div class="wcm-jdw__peserta">
                                    <i class="bi bi-people-fill"></i> {{ j.pesertaSelesai }}/{{ j.peserta }} peserta selesai
                                    <div class="wcm-jdw__bar"><div :style="{ width: pctPeserta(j) + '%' }"></div></div>
                                </div>
                            </div>
                        </section>
                    </template>
                </div>
            </aside>
        </div>
    </teleport>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import axios from 'axios'
import { initials } from '@utils/career/admin'
import { formatAging, formatTanggal, toneClass } from '@utils/career/monitoring'
import KeadaanPanel from './KeadaanPanel.vue'
import { useLapisEsc } from '../../../../composables/useLapisEsc'

const props = defineProps({
    programId: { type: String, required: true },
    kolom: { type: Object, required: true },
    orang: { type: Array, default: () => [] }, // dari payload papan (sudah difilter kolom)
})
const emit = defineEmits(['close', 'open-person'])

const CFG = { headers: { Accept: 'application/json' } }
const loading = ref(false)
const error = ref(false)
const stats = ref({})
const rincianKeluar = ref([])
const subtes = ref([])
const belumSubmit = ref([])
const jadwal = ref([])
const meta = ref({ macetHari: 7 })

// Yang menunggu paling lama tampil lebih dulu — itu yang perlu diputuskan.
const orangUrut = computed(() =>
    [...props.orang].sort((a, b) => (b.agingHari ?? -1) - (a.agingHari ?? -1)),
)

/**
 * Penanda urutan permintaan.
 *
 * Panel ini dimuat ulang tiap kali admin mengklik kolom lain, dan balasan
 * tidak dijamin tiba berurutan: klik kolom 2 lalu cepat pindah ke kolom 5
 * membuat dua permintaan berjalan bersamaan, dan bila balasan kolom 2 tiba
 * belakangan ia menimpa isi kolom 5. Judul panel dibaca dari props (tetap
 * "Tahap 05") sedangkan isinya dari balasan — jadi yang terlihat adalah
 * angka tahap lain di bawah judul yang benar, tanpa satu pun tanda bahwa
 * itu keliru. Balasan yang bukan milik permintaan terakhir dibuang.
 */
let permintaanKe = 0

async function fetchDetail() {
    const token = ++permintaanKe
    loading.value = true
    error.value = false
    try {
        // KODE tahap ikut dikirim. Nomor di URL adalah nomor KOLOM papan,
        // sedangkan server mencarinya di Lamaran_Tahap dengan penomoran
        // KANDIDAT. Sejak kolom disusun dari gabungan alur yang benar-benar
        // dipakai, dua penomoran itu tidak lagi selalu sama — tanpa kode,
        // panel ini menghitung statistik tahap yang bukan yang diklik.
        const { data } = await axios.get(
            `/api/v1/karir/monitoring/program/${props.programId}/tahap/${props.kolom.urutan}/detail`,
            { ...CFG, params: props.kolom.kode ? { kode: props.kolom.kode } : {} },
        )
        if (token !== permintaanKe) return
        const r = data.result || {}
        stats.value = r.stats || {}
        rincianKeluar.value = r.rincianKeluar || []
        subtes.value = r.subtes || []
        belumSubmit.value = r.belumSubmit || []
        jadwal.value = r.jadwal || []
        meta.value = r.meta || meta.value
    } catch (e) {
        if (token !== permintaanKe) return
        error.value = true
    } finally {
        if (token === permintaanKe) loading.value = false
    }
}

function jadwalBadge(s) {
    return { SELESAI: 'wca-b--green', BERJALAN: 'wca-b--indigo', TERKIRIM: 'wca-b--sky', GAGAL: 'wca-b--red' }[s] || 'wca-b--slate'
}

function pctPeserta(j) {
    return j.peserta > 0 ? Math.round((j.pesertaSelesai / j.peserta) * 100) : 0
}

useLapisEsc(() => emit('close'))

// Ikut mengamati kode: dua kolom berbeda bisa bernomor sama setelah kolom
// digabung dari beberapa alur, dan tanpa ini berpindah antar keduanya tidak
// memicu muat ulang — panelnya diam menampilkan angka kolom sebelumnya.
watch(() => [props.kolom.urutan, props.kolom.kode], fetchDetail)
onMounted(fetchDetail)
defineExpose({ refresh: fetchDetail })
</script>

<style scoped>
.wcm-dw { position: fixed; inset: 0; z-index: 1200; display: flex; justify-content: flex-end; pointer-events: none; }
.wcm-dw__panel {
    pointer-events: auto;
    width: min(500px, 100vw);
    height: 100vh;
    background: #ffffff;
    box-shadow: -20px 0 60px rgba(15, 23, 42, 0.22);
    display: flex;
    flex-direction: column;
    animation: wcmSlide 0.25s cubic-bezier(0.16, 1, 0.3, 1);
    border-left: 1px solid rgba(226, 232, 240, 0.9);
}
@keyframes wcmSlide { from { transform: translateX(40px); opacity: 0.3; } to { transform: translateX(0); opacity: 1; } }

.wcm-dw__head { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; padding: 18px 22px; border-bottom: 1px solid #f1f5f9; flex: none; background: #ffffff; }
.wcm-dw__ttl { display: inline-flex; align-items: center; gap: 8px; font-size: 0.92rem; font-weight: 800; color: #0f172a; }
.wcm-dw__ttl .bi { color: #6366f1; }
.wcm-dw__sub { font-size: 0.78rem; color: #64748b; margin-top: 3px; font-weight: 500; }
.wcm-dw__body { flex: 1; overflow-y: auto; padding: 20px 22px 34px; background: #f8fafc; }

.wcm-sd__sec { margin-bottom: 24px; background: #ffffff; border: 1px solid #f1f5f9; border-radius: 18px; padding: 16px; box-shadow: 0 2px 10px rgba(15, 23, 42, 0.02); }
.wcm-sd__ttl { display: flex; align-items: center; gap: 8px; font-size: 0.75rem; font-weight: 800; color: #475569; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 12px; }
.wcm-sd__n { background: #eef2ff; color: #4338ca; border-radius: 99px; padding: 2px 8px; font-size: 0.68rem; font-weight: 800; }

.wcm-sd__stats { display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px; }
.wcm-sd__stat { border: 1px solid #f1f5f9; border-radius: 14px; padding: 12px 10px; text-align: center; background: #f8fafc; transition: all 0.2s ease; }
.wcm-sd__stat:hover { border-color: rgba(99, 102, 241, 0.3); transform: translateY(-2px); }
.wcm-sd__stat b { display: block; font-size: 1.35rem; font-weight: 900; color: #4338ca; font-variant-numeric: tabular-nums; line-height: 1.1; }
.wcm-sd__stat span { font-size: 0.70rem; color: #64748b; font-weight: 600; margin-top: 4px; display: block; }
.wcm-sd__stat.is-hold b { color: #64748b; }
.wcm-sd__stat.is-green b { color: #059669; }
.wcm-sd__stat.is-red b { color: #e11d48; }
.wcm-sd__stat.is-sky b { color: #0284c7; }
.wcm-sd__stat.is-violet b { color: #7c3aed; }

.wcm-sd__facts { display: flex; flex-direction: column; gap: 7px; margin-top: 12px; }
.wcm-fact { display: flex; align-items: center; gap: 8px; font-size: 0.75rem; color: #475569; font-weight: 500; }
.wcm-fact.is-warn { color: #d97706; }
.wcm-fact.is-red { color: #e11d48; }

.wcm-sd__orang { display: flex; flex-direction: column; gap: 6px; }
.wcm-orang { display: flex; align-items: center; gap: 10px; width: 100%; text-align: left; border: 1px solid #f1f5f9; border-radius: 12px; background: #ffffff; padding: 8px 11px; cursor: pointer; transition: all 0.2s ease; }
.wcm-orang:hover { border-color: rgba(99, 102, 241, 0.35); background: #eef2ff; transform: translateX(2px); }
.wcm-orang__txt { flex: 1; min-width: 0; }
.wcm-orang__nama { display: block; font-size: 0.78rem; font-weight: 800; color: #0f172a; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.wcm-orang__sub { display: block; font-size: 0.70rem; color: #64748b; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-weight: 500; }

.wcm-tes { border: 1px solid #f1f5f9; border-radius: 14px; padding: 12px; margin-bottom: 10px; background: #fafafa; }
.wcm-tes__head { display: flex; align-items: center; gap: 8px; font-size: 0.78rem; color: #0f172a; flex-wrap: wrap; font-weight: 700; }
.wcm-tes__tag { font-size: 0.64rem; font-weight: 800; color: #64748b; background: #e2e8f0; padding: 2px 8px; border-radius: 99px; }
.wcm-tes__tag.is-sys { color: #d97706; background: rgba(245, 158, 11, 0.14); }
.wcm-tes__bar { display: flex; gap: 3px; height: 7px; margin: 8px 0 6px; border-radius: 99px; overflow: hidden; background: #e2e8f0; }
.wcm-tes__bar .b-selesai { background: linear-gradient(90deg, #6366f1, #10b981); }
.wcm-tes__bar .b-belum { background: #cbd5e1; }
.wcm-tes__bar .b-absen { background: #ef4444; }
.wcm-tes__meta { font-size: 0.72rem; color: #64748b; font-weight: 500; }

.wcm-belum { margin-top: 12px; border: 1px solid rgba(245, 158, 11, 0.3); border-radius: 14px; background: #fffbeb; padding: 10px 12px; }
.wcm-belum__ttl { display: flex; align-items: center; gap: 6px; font-size: 0.73rem; font-weight: 800; color: #b45309; margin-bottom: 8px; }
.wcm-belum__row { display: flex; align-items: center; gap: 8px; width: 100%; text-align: left; border: 0; background: transparent; padding: 5px 4px; font-size: 0.75rem; color: #374151; cursor: pointer; border-radius: 8px; transition: background 0.15s ease; }
.wcm-belum__row:hover { background: rgba(255, 255, 255, 0.85); }
.wcm-belum__row > span:first-child { font-weight: 800; }
.wcm-belum__tes { flex: 1; color: #64748b; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

.wcm-jdw { border: 1px solid rgba(99, 102, 241, 0.2); border-radius: 14px; padding: 12px 14px; margin-bottom: 10px; background: #eef2ff; }
.wcm-jdw__head { display: flex; align-items: center; gap: 8px; justify-content: space-between; font-size: 0.78rem; color: #0f172a; font-weight: 800; }
.wcm-jdw__meta { font-size: 0.72rem; color: #475569; margin-top: 4px; font-weight: 500; }
.wcm-jdw__peserta { font-size: 0.72rem; color: #4338ca; margin-top: 8px; font-weight: 700; }
.wcm-jdw__bar { margin-top: 5px; height: 5px; border-radius: 99px; background: #cbd5e1; overflow: hidden; }
.wcm-jdw__bar div { height: 100%; border-radius: 99px; background: linear-gradient(90deg, #6366f1, #10b981); }
</style>
