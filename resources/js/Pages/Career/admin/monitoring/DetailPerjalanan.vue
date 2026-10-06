<!--
  Detail perjalanan penuh satu lamaran, isi PersonDrawer dari papan Spotlight.
  Isi: identitas + stepper vertikal per tahap (tanggal, keputusan, rapor
  sub-tes) + jejak keputusan + blok talent pool (masuk pool / asal tarikan).
  Judul tiap tahap dapat diklik → offcanvas detail tahap (lapis 3).
-->
<template>
    <div class="wcm-det">
        <KeadaanPanel v-if="loading" keadaan="memuat" rapat teks="Memuat perjalanan…" />
        <KeadaanPanel v-else-if="error" keadaan="galat" rapat
            teks="Gagal memuat perjalanan"
            ket="Detail lamaran ini tidak berhasil diambil. Periksa koneksi lalu coba lagi."
            @ulang="fetchDetail" />

        <template v-else-if="d">
            <div class="wcm-idn">
                <span class="wca-avatar">{{ initials(d.lamaran.nama) }}</span>
                <div class="wcm-idn__txt">
                    <div class="wcm-idn__nama">{{ d.lamaran.nama || '—' }}</div>
                    <div class="wcm-idn__sub">{{ d.lamaran.kode }} · {{ d.lamaran.posisi || '—' }}</div>
                    <div class="wcm-idn__sub">{{ d.lamaran.programNama || '—' }}<span v-if="d.lamaran.email"> · {{ d.lamaran.email }}</span></div>
                </div>
                <span class="wca-badge" :class="statusKelas(d.lamaran.status)">{{ statusTeks(d.lamaran.status) }}</span>
            </div>

            <div class="wcm-det__grid">
                <!-- Stepper tahap -->
                <section class="wcm-det__sec">
                    <div class="wcm-det__ttl"><i class="bi bi-signpost-split"></i> Perjalanan Tahap</div>
                    <div class="wcm-det__meta">
                        Melamar: <b>{{ formatTanggal(d.lamaran.waktuLamar) }}</b>
                        <template v-if="d.lamaran.waktuSelesai"> · Selesai: <b>{{ formatTanggal(d.lamaran.waktuSelesai) }}</b></template>
                        <template v-if="d.lamaran.mulaiDariUrutan && d.lamaran.mulaiDariUrutan > 1"> · Masuk langsung di tahap {{ d.lamaran.mulaiDariUrutan }} (tarikan talent pool)</template>
                    </div>

                    <KeadaanPanel v-if="!d.tahap.length" keadaan="kosong" rapat
                        ikon="bi-signpost-split"
                        teks="Belum ada tahap tercatat"
                        ket="Lamaran ini belum memiliki jejak tahap — kemungkinan alur seleksinya belum diatur saat pendaftaran." />

                    <div v-for="t in d.tahap" :key="t.urutan" class="wcm-step" :class="['st-' + stepState(t), { 'is-klik': bisaBuka }]">
                        <div class="wcm-step__rail"><span class="wcm-step__dot"></span></div>
                        <div class="wcm-step__body">
                            <component :is="bisaBuka ? 'button' : 'div'" class="wcm-step__head"
                                :type="bisaBuka ? 'button' : null"
                                :title="bisaBuka ? 'Lihat detail tahap ini' : null"
                                @click="bisaBuka && $emit('open-stage', { urutan: t.urutan, label: t.label })">
                                <b>{{ String(t.urutan).padStart(2, '0') }} · {{ t.label }}</b>
                                <i v-if="t.provider === 'THIRD_PARTY'" class="bi bi-robot" title="Tahap otomatis pihak ke-3"></i>
                                <span class="wca-badge" :class="hasilBadge(t)">{{ hasilLabel(t) }}</span>
                                <span v-if="t.skor !== null" class="wcm-step__skor">skor {{ t.skor }}</span>
                                <i v-if="bisaBuka" class="bi bi-chevron-right wcm-step__go"></i>
                            </component>
                            <div class="wcm-step__waktu">
                                <span v-if="t.waktuMulai">mulai {{ formatTanggal(t.waktuMulai) }}</span>
                                <span v-if="t.waktuSelesai"> · selesai {{ formatTanggal(t.waktuSelesai) }}</span>
                                <span v-if="t.diputusAt"> · diputus {{ formatTanggal(t.diputusAt) }}<template v-if="t.diputusBy"> oleh {{ t.diputusBy }}</template></span>
                            </div>
                            <div v-if="t.rekomendasiAlasan || t.catatan" class="wcm-step__note">{{ t.rekomendasiAlasan || t.catatan }}</div>

                            <div v-if="tesBermakna(t.tests).length" class="wcm-tests">
                                <div v-for="(x, i) in tesBermakna(t.tests)" :key="i" class="wcm-test">
                                    <span class="wcm-test__label">{{ x.label }}<span v-if="x.tipeNama" class="wcm-test__info">{{ x.tipeNama }}</span><span v-if="x.peran === 'INFORMATIF'" class="wcm-test__info">informatif</span></span>
                                    <span v-if="x.nilai !== null" class="wcm-test__nilai">{{ x.nilai }}</span>
                                    <span class="wca-badge" :class="testBadge(x)">{{ labelStatusTes(x) }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-if="d.lamaran.alasanGugur" class="wcm-gugur"><i class="bi bi-x-octagon"></i> {{ d.lamaran.alasanGugur }}</div>
                </section>

                <section v-if="d.asalTalentPool" class="wcm-det__sec wcm-pool wcm-pool--asal">
                    <div class="wcm-det__ttl"><i class="bi bi-box-arrow-in-right"></i> Asal Talent Pool</div>
                    <p>Ditarik dari pool <b>{{ d.asalTalentPool.programNama || '—' }}</b><template v-if="d.asalTalentPool.tahapAsal"> (tahap asal: {{ d.asalTalentPool.tahapAsal }})</template>, masuk pool {{ formatTanggal(d.asalTalentPool.tanggalMasuk) }}.</p>
                </section>

                <section v-if="d.talentPool" class="wcm-det__sec wcm-pool">
                    <div class="wcm-det__ttl"><i class="bi bi-droplet"></i> Talent Pool</div>
                    <p>Status <b>{{ d.talentPool.status }}</b><template v-if="d.talentPool.tahapAsal"> · dari tahap {{ d.talentPool.tahapAsal }}</template><template v-if="d.talentPool.tanggalMasuk"> · masuk {{ formatTanggal(d.talentPool.tanggalMasuk) }}</template><template v-if="d.talentPool.tanggalKedaluwarsa"> · kedaluwarsa {{ formatTanggal(d.talentPool.tanggalKedaluwarsa) }}</template>.</p>
                    <p v-if="d.talentPool.ditarikKeKode">Sudah ditarik ke lamaran <b>{{ d.talentPool.ditarikKeKode }}</b> pada {{ formatTanggal(d.talentPool.ditarikAt) }}.</p>
                    <p v-if="d.talentPool.catatan" class="wcm-pool__note">{{ d.talentPool.catatan }}</p>
                </section>

                <section class="wcm-det__sec">
                    <div class="wcm-det__ttl"><i class="bi bi-journal-text"></i> Jejak Keputusan
                        <span v-if="d.jejak.length" class="wcm-det__n">{{ d.jejak.length }}</span>
                    </div>
                    <KeadaanPanel v-if="!d.jejak.length" keadaan="kosong" rapat
                        ikon="bi-journal-text"
                        teks="Belum ada keputusan"
                        ket="Belum ada tahap yang diputus untuk lamaran ini." />
                    <div v-else class="wcm-jejak">
                        <div v-for="(j, i) in d.jejak" :key="i" class="wcm-jejak__row">
                            <span class="wca-badge" :class="verdictBadge(j.verdict)">{{ j.verdict }}</span>
                            <div class="wcm-jejak__txt">
                                <div v-if="j.ringkasan">{{ j.ringkasan }}</div>
                                <div class="wcm-jejak__meta">{{ formatTanggal(j.pada) }}<template v-if="j.oleh"> · {{ j.oleh }}</template><template v-if="j.mode"> · {{ j.mode }}</template></div>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </template>
    </div>
</template>

<script setup>
import { ref, watch, onMounted } from 'vue'
import axios from 'axios'
import { initials } from '@utils/career/admin'
import { formatTanggal, labelOutcome, labelStatusTes, tesBermakna, toneOutcome } from '@utils/career/monitoring'
import KeadaanPanel from './KeadaanPanel.vue'

const props = defineProps({
    lamaranId: { type: String, required: true },
    // Tahap dapat diklik untuk membuka offcanvas detail tahap (lapis 3).
    bisaBuka: { type: Boolean, default: false },
})
defineEmits(['open-stage'])

const CFG = { headers: { Accept: 'application/json' } }
const loading = ref(false)
const error = ref(false)
const d = ref(null)

/**
 * Penanda urutan permintaan. Drawer ini berganti isi tiap kali admin memilih
 * pelamar lain; bila balasan pelamar sebelumnya tiba belakangan, perjalanan
 * orang lama tergambar di bawah nama orang baru — dan tidak ada tanda apa pun
 * bahwa yang terbaca bukan miliknya.
 */
let permintaanKe = 0

async function fetchDetail() {
    const token = ++permintaanKe
    loading.value = true
    error.value = false
    try {
        const { data } = await axios.get(`/api/v1/karir/monitoring/pelamar/${props.lamaranId}`, CFG)
        if (token !== permintaanKe) return
        d.value = data.result || null
    } catch (e) {
        if (token !== permintaanKe) return
        error.value = true
    } finally {
        if (token === permintaanKe) loading.value = false
    }
}

/**
 * Peta outcome dari server. Semua fungsi di bawah dulu memakai peta literal
 * {LULUS, GUGUR, TALENT_POOL} dengan fallback ke indigo — warna yang dipakai
 * untuk "berjalan". Akibatnya setiap outcome baru tampil sebagai kode mentah
 * berwarna aktif, persis kebalikan dari keadaan orangnya.
 */
const mh = () => d.value?.masterHasil ?? {}

function stepState(t) {
    const b = mh()[t.hasil]?.bucket
    if (b === 'lulus') return 'done'
    if (b === 'gugur') return 'failed'
    if (b === 'talent') return 'talent'
    if (b === 'keluar') return 'keluar'
    if (t.status === 'BERJALAN') return 'current'
    return 'pending'
}

function hasilLabel(t) {
    if (t.hasil) return labelOutcome(t.hasil, mh())
    if (t.status === 'BERJALAN') return t.siapDiputus ? 'Siap Diputus' : 'Berjalan'
    return 'Menunggu'
}

function hasilBadge(t) {
    const s = stepState(t)
    return { done: 'wca-b--green', failed: 'wca-b--red', talent: 'wca-b--sky', keluar: 'wca-b--violet', current: t.siapDiputus ? 'wca-b--amber' : 'wca-b--indigo', pending: 'wca-b--slate' }[s]
}

function testBadge(x) {
    if (x.hasil === 'LULUS') return 'wca-b--green'
    if (x.hasil === 'GAGAL') return 'wca-b--red'
    if (x.status === 'SELESAI') return 'wca-b--indigo'
    if (x.status === 'TIDAK_HADIR') return 'wca-b--red'
    if (x.status === 'DIJADWALKAN') return 'wca-b--sky'
    return 'wca-b--slate'
}

function verdictBadge(v) {
    if (v === 'LOLOS') return 'wca-b--green'
    return mh()[v] ? toneOutcome(v, mh()) : 'wca-b--slate'
}

function statusKelas(s) {
    if (s === 'BERJALAN') return 'wca-b--indigo'
    return mh()[s] ? toneOutcome(s, mh()) : 'wca-b--indigo'
}

function statusTeks(s) {
    if (s === 'BERJALAN') return 'Berjalan'
    // LULUS tetap dibaca "Diterima" agar sebunyi dengan badge di papan; master
    // menamainya "Lolos", yang di layar ini terbaca sebagai lolos satu tahap.
    if (s === 'LULUS') return 'Diterima'
    return labelOutcome(s, mh())
}

// Drawer dipakai ulang untuk orang lain tanpa remount → muat ulang saat id ganti.
watch(() => props.lamaranId, fetchDetail)
onMounted(fetchDetail)
defineExpose({ refresh: fetchDetail })
</script>

<style scoped>
/* Isi laci disusun sebagai KARTU BERSEKAT di atas latar abu — bukan satu
   tumpukan panjang. Metriknya menyalin .wcm-sd__sec di StageDetailPanel supaya
   kedua laci terasa satu keluarga. */
.wcm-det__grid { display: flex; flex-direction: column; gap: 14px; }
.wcm-det__sec { background: #fff; border: 1px solid #f1f5f9; border-radius: 18px; padding: 16px; box-shadow: 0 2px 10px rgba(15, 23, 42, 0.02); }
.wcm-det__ttl { display: flex; align-items: center; gap: 8px; font-size: 0.75rem; font-weight: 800; color: #475569; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 12px; }
.wcm-det__n { background: #eef2ff; color: #4338ca; border-radius: 99px; padding: 2px 8px; font-size: 0.68rem; font-weight: 800; letter-spacing: 0; }
.wcm-det__meta { font-size: 0.75rem; color: #64748b; margin-bottom: 16px; font-weight: 500; }

/* Identitas MENEMPEL di atas: saat menggulir perjalanan yang panjang, "ini
   perjalanan siapa" tidak boleh ikut hilang dari layar. */
.wcm-idn { position: sticky; top: -20px; z-index: 2; display: flex; align-items: center; gap: 14px; padding: 14px 16px; margin: -20px -22px 14px; border-bottom: 1px solid rgba(99, 102, 241, 0.16); background: linear-gradient(135deg, #eef2ff, #f6f8ff); backdrop-filter: blur(6px); }
.wcm-idn__txt { flex: 1; min-width: 0; }
.wcm-idn__nama { font-size: 1rem; font-weight: 800; color: #0f172a; letter-spacing: -0.01em; }
.wcm-idn__sub { font-size: 0.75rem; color: #64748b; margin-top: 2px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-weight: 500; }

.wcm-step { display: flex; gap: 14px; position: relative; }
.wcm-step__rail { display: flex; flex-direction: column; align-items: center; width: 18px; flex-shrink: 0; }
.wcm-step__dot { width: 12px; height: 12px; border-radius: 50%; background: #cbd5e1; margin-top: 5px; flex-shrink: 0; transition: all 0.2s ease; }
.wcm-step__rail::after { content: ''; flex: 1; width: 2px; background: #e2e8f0; margin-top: 4px; }
.wcm-step:last-of-type .wcm-step__rail::after { display: none; }
.wcm-step.st-done .wcm-step__dot { background: #6366f1; box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2); }
.wcm-step.st-current .wcm-step__dot { background: #f59e0b; box-shadow: 0 0 0 4px rgba(245, 158, 11, 0.25); animation: wcmPulse 2s infinite; }
.wcm-step.st-failed .wcm-step__dot { background: #ef4444; box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.2); }
.wcm-step.st-talent .wcm-step__dot { background: #0ea5e9; box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.2); }
.wcm-step.st-keluar .wcm-step__dot { background: #8b5cf6; box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.2); }
@keyframes wcmPulse { 0%, 100% { transform: scale(1); } 50% { transform: scale(1.25); } }

.wcm-step__body { flex: 1; min-width: 0; padding-bottom: 18px; }
.wcm-step__head { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; font-size: 0.82rem; color: #0f172a; font-weight: 800; }
.wcm-step__head .bi-robot { color: #d97706; font-size: 0.78rem; }

/* Padding negatif dihapus: di dalam kartu selebar ±420px, `margin: -6px -10px`
   membuat tombol menjorok keluar tepi kartu saat di-hover. */
button.wcm-step__head { width: 100%; text-align: left; border: 1px solid transparent; background: transparent; padding: 6px 8px; margin-left: -8px; border-radius: 10px; cursor: pointer; font: inherit; transition: all 0.2s ease; }
button.wcm-step__head:hover { border-color: rgba(99, 102, 241, 0.3); background: #eef2ff; }
.wcm-step__go { margin-left: auto; color: #94a3b8; font-size: 0.75rem; transition: transform 0.2s ease; }
button.wcm-step__head:hover .wcm-step__go { color: #6366f1; transform: translateX(2px); }
.wcm-step__skor { font-size: 0.72rem; font-weight: 800; color: #4338ca; background: #eef2ff; padding: 2px 7px; border-radius: 6px; }
.wcm-step__waktu { font-size: 0.72rem; color: #64748b; margin-top: 4px; font-weight: 500; }
/* Elemen bersarang di dalam kartu putih diberi isian abu — kalau tetap putih
   dengan garis #f1f5f9, keduanya melebur dan tak terbaca sebagai kotak sendiri. */
.wcm-step__note { margin-top: 8px; font-size: 0.75rem; color: #475569; background: #f8fafc; border: 1px solid #eef1f7; border-left: 3px solid #cbd5e1; border-radius: 10px; padding: 9px 12px; font-weight: 500; line-height: 1.55; }
.wcm-tests { margin-top: 10px; display: flex; flex-direction: column; gap: 6px; }
.wcm-test { display: flex; align-items: center; gap: 10px; background: #f8fafc; border: 1px solid #eef1f7; border-radius: 10px; padding: 8px 12px; font-size: 0.75rem; }
.wcm-test__label { flex: 1; font-weight: 700; color: #0f172a; }
.wcm-test__info { margin-left: 8px; font-size: 0.64rem; font-weight: 800; color: #64748b; background: #e2e8f0; padding: 2px 7px; border-radius: 99px; }
.wcm-test__nilai { font-weight: 800; color: #4338ca; font-variant-numeric: tabular-nums; }
.wcm-gugur { display: flex; gap: 10px; align-items: center; margin-top: 6px; font-size: 0.78rem; color: #be123c; background: #fff1f2; border: 1px solid #fecdd3; border-radius: 12px; padding: 10px 14px; font-weight: 600; }

/* Blok talent pool tetap kartu bersekat, hanya diberi aksen biru agar
   terbedakan dari kartu perjalanan & jejak di sekitarnya. */
.wcm-pool { border-color: #bae6fd; }
.wcm-pool p { font-size: 0.78rem; color: #0f172a; margin: 0 0 4px; line-height: 1.55; font-weight: 600; }
.wcm-pool p:last-child { margin-bottom: 0; }
.wcm-pool--asal { border-color: #7dd3fc; background: #f0f9ff; }
.wcm-pool__note { color: #64748b; font-style: italic; font-weight: 500; }
.wcm-jejak { display: flex; flex-direction: column; gap: 8px; }
.wcm-jejak__row { display: flex; gap: 10px; align-items: flex-start; background: #f8fafc; border: 1px solid #eef1f7; border-radius: 10px; padding: 10px 12px; }
.wcm-jejak__txt { font-size: 0.78rem; color: #334155; min-width: 0; font-weight: 500; }
.wcm-jejak__meta { font-size: 0.70rem; color: #94a3b8; margin-top: 3px; font-weight: 500; }
/* Tahap terakhir tidak perlu jarak bawah — menyisakan celah kosong di kaki kartu. */
.wcm-step:last-of-type .wcm-step__body { padding-bottom: 0; }
</style>
