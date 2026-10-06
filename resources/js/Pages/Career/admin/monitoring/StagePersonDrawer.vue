<!--
  Offcanvas LAPIS 3 — isi satu tahap milik satu pelamar.
  Menumpuk di atas offcanvas orang (lapis 2) dengan offset ke kiri, sehingga
  tepi lapis di bawahnya masih terlihat: jelas ini tumpukan, bukan pengganti.

  Blok muncul SESUAI DATA, bukan berdasarkan tipe tahap — di project ini tipe
  tahap tidak menentukan perilaku (lihat MonitoringController::tahapPelamar).
  Tahap yang belum dijalani tetap bisa dibuka: yang tampil blok "rencana".
-->
<template>
    <teleport to="body">
        <div class="wcm-dw3">
            <aside class="wcm-dw3__panel" role="dialog" aria-label="Detail tahap pelamar">
                <header class="wcm-dw3__head">
                    <button class="wca-iconbtn" title="Kembali ke perjalanan (Esc)" @click="$emit('close')">
                        <i class="bi bi-arrow-left"></i>
                    </button>
                    <div class="wcm-dw3__ttl">
                        <div class="wcm-dw3__crumb">{{ namaPelamar }} <i class="bi bi-chevron-right"></i></div>
                        <div class="wcm-dw3__label">
                            <i v-if="tahap.tipeIkon" class="bi" :class="tahap.tipeIkon"></i>
                            {{ String(urutan).padStart(2, '0') }} · {{ tahap.label || labelAwal || 'Tahap ' + urutan }}
                        </div>
                    </div>
                    <span v-if="tahap.tipeNama" class="wcm-dw3__tipe">{{ tahap.tipeNama }}</span>
                </header>

                <div class="wcm-dw3__body">
                    <KeadaanPanel v-if="loading" keadaan="memuat" rapat teks="Memuat detail tahap…" />
                    <KeadaanPanel v-else-if="error" keadaan="galat" rapat
                        teks="Gagal memuat detail tahap"
                        ket="Isian dan hasil tes pelamar di tahap ini tidak berhasil diambil."
                        @ulang="fetchDetail" />

                    <template v-else>
                        <p v-if="tahap.tipeDeskripsi" class="wcm-dw3__desc">{{ tahap.tipeDeskripsi }}</p>

                        <!-- Belum dijalani → tampilkan apa yang akan dijalani.
                             Tahap berstatus MENUNGGU pun dianggap belum dimulai:
                             barisnya sudah dibuat sistem, tapi belum ada yang berjalan. -->
                        <div v-if="belumDimulai" class="wcm-belum3">
                            <i class="bi bi-hourglass"></i>
                            <div>
                                <b>{{ tahap.sudahDijalani ? 'Tahap ini belum dimulai.' : 'Tahap ini belum dijalani.' }}</b>
                                <span v-if="rencana">Di bawah ini ketentuan tahap sesuai alur program.</span>
                                <span v-else>Tahap ini juga tidak ada pada alur program saat ini.</span>
                            </div>
                        </div>

                        <!-- 1. KEPUTUSAN -->
                        <section v-if="keputusan" class="wcm-sec">
                            <div class="wcm-sec__ttl"><i class="bi bi-hammer"></i> Keputusan</div>
                            <div class="wcm-kep">
                                <span class="wca-badge" :class="badgeHasil">{{ labelHasil }}</span>
                                <span v-if="keputusan.skor !== null" class="wcm-kep__skor">skor {{ keputusan.skor }}</span>
                                <span v-if="keputusan.siapDiputus" class="wca-badge wca-b--amber">Siap Diputus</span>
                            </div>
                            <!-- Waktu yang nilainya sama tidak diulang (tahap otomatis
                                 kerap mulai-selesai-diputus pada detik yang sama). -->
                            <dl class="wcm-fakta">
                                <template v-if="waktu.mulai"><dt>Mulai</dt><dd>{{ formatTanggal(waktu.mulai) }}</dd></template>
                                <template v-if="waktu.selesai"><dt>Selesai</dt><dd>{{ formatTanggal(waktu.selesai) }}</dd></template>
                                <template v-if="keputusan.diputusAt"><dt>Diputus</dt><dd>{{ formatTanggal(keputusan.diputusAt) }}<template v-if="keputusan.diputusBy"> oleh {{ keputusan.diputusBy }}</template></dd></template>
                                <template v-if="keputusan.modeKeputusan"><dt>Mode</dt><dd>{{ keputusan.modeKeputusan }}</dd></template>
                                <template v-if="keputusan.rekomendasi"><dt>Rekomendasi mesin</dt><dd>{{ keputusan.rekomendasi }}</dd></template>
                                <template v-if="keputusan.waktuDiumumkan"><dt>Diumumkan</dt><dd>{{ formatTanggal(keputusan.waktuDiumumkan) }}</dd></template>
                            </dl>
                            <p v-if="keputusan.rekomendasiAlasan || keputusan.catatan" class="wcm-catatan">{{ keputusan.rekomendasiAlasan || keputusan.catatan }}</p>
                        </section>

                        <!-- 2. RAPOR TES — hanya sub-tes yang bermakna.
                             Setiap tahap otomatis dapat satu sub-tes bawaan saat alur
                             dibuat; pada tahap non-tes isinya kosong dan justru
                             membingungkan kalau ditampilkan. -->
                        <section v-if="subtesTampil.length" class="wcm-sec">
                            <div class="wcm-sec__ttl"><i class="bi bi-clipboard-check"></i> Rapor Tes</div>
                            <div v-for="(x, i) in subtesTampil" :key="i" class="wcm-tes3">
                                <div class="wcm-tes3__head">
                                    <b>{{ x.label }}</b>
                                    <!-- Tipe aktivitas: ujian online, tes manual, atau wawancara. -->
                                    <span v-if="x.tipeNama" class="wcm-tag">{{ x.tipeNama }}</span>
                                    <span v-if="x.peran === 'INFORMATIF'" class="wcm-tag">informatif</span>
                                    <span v-if="!x.wajib" class="wcm-tag">opsional</span>
                                    <span class="wca-badge" :class="badgeTes(x)">{{ labelStatusTes(x) }}</span>
                                </div>
                                <div class="wcm-tes3__meta">
                                    <span v-if="x.nilai !== null">Nilai <b>{{ x.nilai }}</b><template v-if="x.ambang !== null"> / ambang {{ x.ambang }}</template></span>
                                    <span v-if="x.totalSoal">{{ x.totalSoal }} soal</span>
                                    <span v-if="x.percobaan > 1">percobaan ke-{{ x.percobaan }}</span>
                                    <span v-if="x.waktuSelesai">selesai {{ formatTanggal(x.waktuSelesai) }}</span>
                                </div>
                                <p v-if="x.catatan" class="wcm-catatan">{{ x.catatan }}</p>
                            </div>
                        </section>

                        <!-- 3. PENGERJAAN UJIAN (CAT) -->
                        <section v-if="ujian.length" class="wcm-sec">
                            <div class="wcm-sec__ttl"><i class="bi bi-robot"></i> Pengerjaan Ujian</div>
                            <div v-for="(u, i) in ujian" :key="i" class="wcm-ujian">
                                <div class="wcm-ujian__head">
                                    <b>{{ u.ujianNama || '—' }}</b>
                                    <span v-if="u.kelulusan" class="wca-badge" :class="/LULUS|LOLOS/i.test(u.kelulusan) ? 'wca-b--green' : 'wca-b--red'">{{ u.kelulusan }}</span>
                                    <span v-else-if="u.selesai" class="wca-badge wca-b--indigo">Selesai</span>
                                    <span v-else class="wca-badge wca-b--slate">{{ u.statusPengerjaan || u.statusKirim || 'Belum dikerjakan' }}</span>
                                </div>
                                <div v-if="u.totalNilai !== null" class="wcm-nilai">
                                    <div class="wcm-nilai__angka">{{ u.totalNilai }}<span v-if="u.ambang !== null"> / {{ u.ambang }}</span></div>
                                    <div v-if="u.ambang" class="wcm-nilai__bar">
                                        <div :class="{ 'is-lulus': u.totalNilai >= u.ambang }"
                                            :style="{ width: Math.min(100, Math.round((u.totalNilai / u.ambang) * 100)) + '%' }"></div>
                                    </div>
                                </div>
                                <dl class="wcm-fakta">
                                    <template v-if="u.penjadwalan"><dt>Sesi</dt><dd>{{ u.penjadwalan }}</dd></template>
                                    <template v-if="u.jadwalMulai"><dt>Jadwal</dt><dd>{{ formatTanggal(u.jadwalMulai) }}<template v-if="u.jadwalAkhir"> — {{ formatTanggal(u.jadwalAkhir) }}</template><template v-if="u.durasiMenit"> · {{ u.durasiMenit }} menit</template></dd></template>
                                    <template v-if="u.mulaiAkses"><dt>Mulai kerjakan</dt><dd>{{ formatTanggal(u.mulaiAkses) }}</dd></template>
                                    <template v-if="u.selesaiAkses"><dt>Selesai kerjakan</dt><dd>{{ formatTanggal(u.selesaiAkses) }}</dd></template>
                                    <template v-if="u.totalSoal"><dt>Jumlah soal</dt><dd>{{ u.totalSoal }}</dd></template>
                                </dl>
                                <div v-if="u.rincian.length" class="wcm-rincian">
                                    <div v-for="(r, j) in u.rincian" :key="j" class="wcm-rincian__row">
                                        <span>{{ r.label }}</span><b>{{ r.nilai }}</b>
                                    </div>
                                </div>
                            </div>
                        </section>

                        <!-- 4. FORMULIR KANDIDAT -->
                        <section v-if="formulir" class="wcm-sec">
                            <div class="wcm-sec__ttl"><i class="bi bi-ui-checks"></i> Formulir Kandidat
                                <span v-if="formulir.waktuKirim" class="wcm-sec__sub">dikirim {{ formatTanggal(formulir.waktuKirim) }}</span>
                            </div>

                            <div v-if="formulir.dokumen.length" class="wcm-dok">
                                <button v-for="(b, i) in formulir.dokumen" :key="i" type="button" class="wcm-dok__item" @click="pratinjau = b">
                                    <i class="bi" :class="ikonBerkas(b)"></i>
                                    <span class="wcm-dok__txt">
                                        <span class="wcm-dok__nama">{{ b.field }}</span>
                                        <span class="wcm-dok__sub">{{ b.nama }} · {{ formatUkuran(b.ukuran) }}</span>
                                    </span>
                                    <span v-if="b.verifikasi === 'TERVERIFIKASI'" class="wca-badge wca-b--green">Terverifikasi</span>
                                    <span v-else class="wcm-tag">belum diverifikasi</span>
                                </button>
                            </div>

                            <template v-if="formulir.jawaban.length">
                                <button type="button" class="wcm-isian__head" @click="isianTerbuka = !isianTerbuka">
                                    <i class="bi" :class="isianTerbuka ? 'bi-chevron-down' : 'bi-chevron-right'"></i>
                                    Isian formulir <span class="wcm-isian__n">{{ formulir.jawaban.length }}</span>
                                </button>
                                <dl v-show="isianTerbuka" class="wcm-isian">
                                    <template v-for="(j, i) in formulir.jawaban" :key="i">
                                        <dt>{{ j.label }}</dt><dd>{{ j.nilai ?? '—' }}</dd>
                                    </template>
                                </dl>
                            </template>
                            <KeadaanPanel v-else keadaan="kosong" rapat
                                ikon="bi-ui-checks"
                                teks="Formulir belum diisi"
                                ket="Pelamar belum mengirim jawaban untuk formulir tahap ini." />
                        </section>

                        <!-- 5. BERKAS HASIL TAHAP -->
                        <section v-if="berkasHasil.length" class="wcm-sec">
                            <div class="wcm-sec__ttl"><i class="bi bi-paperclip"></i> Berkas Hasil Tahap</div>
                            <div class="wcm-dok">
                                <button v-for="(b, i) in berkasHasil" :key="i" type="button" class="wcm-dok__item" @click="pratinjau = b">
                                    <i class="bi" :class="ikonBerkas(b)"></i>
                                    <span class="wcm-dok__txt">
                                        <span class="wcm-dok__nama">{{ b.nama }}</span>
                                        <span class="wcm-dok__sub">{{ formatUkuran(b.ukuran) }}<template v-if="b.diunggahOleh"> · {{ b.diunggahOleh }}</template><template v-if="b.diunggahAt"> · {{ formatTanggal(b.diunggahAt) }}</template></span>
                                    </span>
                                    <i class="bi bi-eye wcm-dok__go"></i>
                                </button>
                            </div>
                        </section>

                        <!-- 6. JEJAK KEPUTUSAN TAHAP INI -->
                        <section v-if="jejak.length" class="wcm-sec">
                            <div class="wcm-sec__ttl"><i class="bi bi-journal-text"></i> Jejak Keputusan Tahap Ini</div>
                            <div v-for="(j, i) in jejak" :key="i" class="wcm-jejak3">
                                <span class="wca-badge" :class="badgeVerdict(j.verdict)">{{ j.verdict }}</span>
                                <div>
                                    <div v-if="j.ringkasan">{{ j.ringkasan }}</div>
                                    <div class="wcm-jejak3__meta">{{ formatTanggal(j.pada) }}<template v-if="j.oleh"> · {{ j.oleh }}</template></div>
                                </div>
                            </div>
                        </section>

                        <!-- 7. RENCANA TAHAP (template alur) -->
                        <section v-if="rencana" class="wcm-sec">
                            <div class="wcm-sec__ttl"><i class="bi bi-map"></i> {{ tahap.sudahDijalani ? 'Ketentuan Tahap' : 'Rencana Tahap' }}</div>
                            <dl class="wcm-fakta">
                                <template v-if="rencana.provider"><dt>Pelaksanaan</dt><dd>{{ rencana.provider === 'THIRD_PARTY' ? 'Ujian online — dijadwalkan (HCLearn)' : 'Ditangani tim rekrutmen' }}</dd></template>
                                <template v-if="rencana.formulirKode"><dt>Formulir</dt><dd>{{ rencana.formulirKode }}</dd></template>
                                <template v-if="rencana.modeKeputusan"><dt>Mode keputusan</dt><dd>{{ rencana.modeKeputusan }}</dd></template>
                                <template v-if="rencana.sla"><dt>SLA</dt><dd>{{ rencana.sla }}</dd></template>
                            </dl>
                            <div class="wcm-flags">
                                <span v-if="rencana.uploadHasil" class="wcm-tag" :class="{ 'is-wajib': rencana.wajibUpload }">
                                    <i class="bi bi-paperclip"></i> {{ rencana.wajibUpload ? 'Wajib unggah berkas hasil' : 'Boleh unggah berkas hasil' }}
                                </span>
                                <span v-if="rencana.talentPool" class="wcm-tag"><i class="bi bi-droplet"></i> Bisa masuk Talent Pool</span>
                            </div>
                            <div v-if="rencana.tes.length" class="wcm-rencanates">
                                <div v-for="(x, i) in rencana.tes" :key="i" class="wcm-rencanates__row">
                                    <span>{{ x.label }}</span>
                                    <span class="wcm-rencanates__tag">{{ x.peran }}<template v-if="!x.wajib"> · opsional</template><template v-if="x.ambang !== null"> · ambang {{ x.ambang }}</template></span>
                                </div>
                            </div>
                        </section>
                    </template>
                </div>
            </aside>
        </div>
    </teleport>

    <!-- Di luar teleport di atas: BerkasLightbox meng-teleport dirinya sendiri
         ke <body>, dan teleport bersarang ke target yang sama membuat Vue
         gagal mem-patch DOM. -->
    <BerkasLightbox v-if="pratinjau" :berkas="pratinjau" @close="pratinjau = null" />
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import axios from 'axios'
import BerkasLightbox from './BerkasLightbox.vue'
import KeadaanPanel from './KeadaanPanel.vue'
import { formatTanggal, formatUkuran, jenisPratinjau, labelOutcome, labelStatusTes, tesBermakna, toneOutcome } from '@utils/career/monitoring'
import { useLapisEsc } from '../../../../composables/useLapisEsc'

const props = defineProps({
    lamaranId: { type: String, required: true },
    urutan: { type: Number, required: true },
    namaPelamar: { type: String, default: '' },
    // Label yang sudah diketahui pemanggil — dipakai selama data belum tiba
    // agar judul tidak sempat berkedip jadi "Tahap N".
    labelAwal: { type: String, default: '' },
})
const emit = defineEmits(['close'])

const CFG = { headers: { Accept: 'application/json' } }
const loading = ref(false)
const error = ref(false)
const masterHasil = ref({})
const tahap = ref({})
const keputusan = ref(null)
const berkasHasil = ref([])
const formulir = ref(null)
const subtes = ref([])
const ujian = ref([])
const jejak = ref([])
const rencana = ref(null)
const pratinjau = ref(null)
const isianTerbuka = ref(false)

const subtesTampil = computed(() => tesBermakna(subtes.value))

// Waktu mulai/selesai disembunyikan bila sama dengan waktu setelahnya.
const waktu = computed(() => {
    const k = keputusan.value
    if (!k) return { mulai: null, selesai: null }
    const selesai = k.waktuSelesai && k.waktuSelesai !== k.diputusAt ? k.waktuSelesai : null
    const acuan = selesai || k.diputusAt
    return { mulai: k.waktuMulai && k.waktuMulai !== acuan ? k.waktuMulai : null, selesai }
})

// "Belum dimulai" mencakup dua hal: baris tahap belum ada sama sekali, ATAU
// sudah ada tapi masih MENUNGGU giliran.
const belumDimulai = computed(
    () => !tahap.value.sudahDijalani || keputusan.value?.status === 'MENUNGGU',
)

// Label & warna keputusan dari master. Versi lama hanya mengenal tiga kode,
// sehingga keputusan "mengundurkan diri" di tahap ini terbaca "Sedang berjalan".
const labelHasil = computed(() => {
    const k = keputusan.value
    if (!k) return ''
    if (k.hasil === 'LULUS') return 'Lulus tahap'
    if (k.hasil) return labelOutcome(k.hasil, masterHasil.value)
    return k.status === 'BERJALAN' ? 'Sedang berjalan' : 'Menunggu'
})
const badgeHasil = computed(() => {
    const k = keputusan.value
    if (!k) return 'wca-b--slate'
    if (k.hasil) return toneOutcome(k.hasil, masterHasil.value)
    return k.status === 'BERJALAN' ? 'wca-b--indigo' : 'wca-b--slate'
})

function badgeTes(x) {
    if (x.hasil === 'LULUS') return 'wca-b--green'
    if (x.hasil === 'GAGAL') return 'wca-b--red'
    if (x.status === 'SELESAI') return 'wca-b--indigo'
    if (x.status === 'TIDAK_HADIR') return 'wca-b--red'
    if (x.status === 'DIJADWALKAN') return 'wca-b--sky'
    return 'wca-b--slate'
}

function badgeVerdict(v) {
    if (v === 'LOLOS') return 'wca-b--green'
    return masterHasil.value?.[v] ? toneOutcome(v, masterHasil.value) : 'wca-b--slate'
}

function ikonBerkas(b) {
    const j = jenisPratinjau(b.ext, b.mime)
    return j === 'gambar' ? 'bi-file-earmark-image' : j === 'pdf' ? 'bi-file-earmark-pdf' : 'bi-file-earmark'
}

/**
 * Penanda urutan permintaan — lihat alasan yang sama di StageDetailPanel.
 *
 * Di sini akibatnya lebih tajam: drawer ini menampilkan berkas, jawaban
 * formulir, dan keputusan MILIK SATU ORANG di satu tahap. Berpindah cepat
 * antar sel matriks membuat dua permintaan berjalan bersamaan, dan balasan
 * yang datang terlambat menaruh dokumen kandidat lain di bawah nama yang
 * sedang terbuka. Kekeliruan seperti itu tidak terlihat sebagai galat —
 * halamannya rapi, terisi, dan salah orang.
 */
let permintaanKe = 0

async function fetchDetail() {
    const token = ++permintaanKe
    loading.value = true
    error.value = false
    isianTerbuka.value = false // pindah tahap → daftar isian kembali terlipat
    try {
        const { data } = await axios.get(
            `/api/v1/karir/monitoring/pelamar/${props.lamaranId}/tahap/${props.urutan}`, CFG,
        )
        if (token !== permintaanKe) return
        const r = data.result || {}
        masterHasil.value = r.masterHasil || {}
        tahap.value = r.tahap || {}
        keputusan.value = r.keputusan || null
        berkasHasil.value = r.berkasHasil || []
        formulir.value = r.formulir || null
        subtes.value = r.subtes || []
        ujian.value = r.ujian || []
        jejak.value = r.jejak || []
        rencana.value = r.rencana || null
    } catch (e) {
        if (token !== permintaanKe) return
        error.value = true
    } finally {
        if (token === permintaanKe) loading.value = false
    }
}

// Esc menutup lapis ini saja — antrean lapisan diatur useLapisEsc.
useLapisEsc(() => emit('close'))

watch(() => [props.lamaranId, props.urutan], fetchDetail)
onMounted(fetchDetail)
</script>

<style scoped>
/* Lapis 3: offset 44px dari kanan supaya tepi lapis 2 tetap terlihat →
   terbaca sebagai tumpukan. Backdrop tidak menangkap klik, jadi papan di
   belakang tetap bisa dipakai. */
.wcm-dw3 { position: fixed; inset: 0; z-index: 1300; display: flex; justify-content: flex-end; padding-right: 44px; pointer-events: none; }
/* Metrik disamakan dengan dua laci lain — hanya lebarnya sedikit lebih besar
   karena laci ini bertumpuk di atasnya (offset 44px agar tepi laci di bawah
   tetap terlihat). */
.wcm-dw3__panel { pointer-events: auto; width: min(500px, calc(100vw - 44px)); height: 100vh; background: #fff; box-shadow: -20px 0 60px rgba(15, 23, 42, 0.26); border-left: 1px solid rgba(226, 232, 240, 0.9); display: flex; flex-direction: column; animation: wcmSlide3 0.25s cubic-bezier(0.16, 1, 0.3, 1); }
@keyframes wcmSlide3 { from { transform: translateX(40px); opacity: 0.3; } to { transform: none; opacity: 1; } }

.wcm-dw3__head { flex: none; display: flex; align-items: center; gap: 11px; padding: 15px 22px; border-bottom: 1px solid #f1f5f9; background: #fff; }
.wcm-dw3__ttl { flex: 1; min-width: 0; }
.wcm-dw3__crumb { font-size: 11px; color: #9ca3af; font-weight: 700; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.wcm-dw3__crumb .bi { font-size: 9px; }
.wcm-dw3__label { display: flex; align-items: center; gap: 8px; font-size: 14px; font-weight: 800; color: #111827; margin-top: 1px; }
.wcm-dw3__label .bi { color: #6366f1; }
.wcm-dw3__tipe { flex: none; font-size: 10.5px; font-weight: 800; color: #4338ca; background: #eef2ff; border-radius: 99px; padding: 3px 10px; }
.wcm-dw3__body { flex: 1; overflow-y: auto; padding: 20px 22px 34px; background: #f8fafc; }
.wcm-dw3__desc { margin: 0 0 15px; font-size: 12px; line-height: 1.6; color: #6b7280; background: #fff; border: 1px solid #eef0f7; border-radius: 11px; padding: 9px 12px; }

.wcm-belum3 { display: flex; gap: 10px; align-items: flex-start; margin-bottom: 16px; padding: 11px 13px; border-radius: 12px; background: #fffbeb; border: 1px solid #fde68a; font-size: 12.5px; color: #92660a; line-height: 1.55; }
.wcm-belum3 b { display: block; }

/* Seksi jadi kartu bersekat di atas latar abu laci — metrik sama dengan
   .wcm-det__sec (DetailPerjalanan) & .wcm-sd__sec (StageDetailPanel). */
.wcm-sec { margin-bottom: 14px; background: #fff; border: 1px solid #f1f5f9; border-radius: 18px; padding: 16px; box-shadow: 0 2px 10px rgba(15, 23, 42, 0.02); }
.wcm-sec__ttl { display: flex; align-items: center; gap: 7px; font-size: 11.5px; font-weight: 800; color: #4b5563; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 9px; }
.wcm-sec__sub { margin-left: auto; text-transform: none; letter-spacing: 0; font-weight: 600; color: #9ca3af; font-size: 11px; }

.wcm-kep { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; margin-bottom: 9px; }
.wcm-kep__skor { font-size: 12px; font-weight: 800; color: #4f46e5; }
.wcm-catatan { margin: 7px 0 0; font-size: 12px; line-height: 1.55; color: #6b7280; background: #f8fafc; border: 1px solid #eef0f7; border-radius: 10px; padding: 8px 11px; }

.wcm-fakta { display: grid; grid-template-columns: minmax(96px, auto) 1fr; gap: 4px 12px; margin: 0; font-size: 12px; }
.wcm-fakta dt { color: #9ca3af; }
.wcm-fakta dd { margin: 0; color: #374151; font-weight: 600; overflow-wrap: anywhere; }

/* Isian formulir bisa puluhan baris — dilipat, dan tiap baris diberi
   pemisah halus supaya tidak terbaca sebagai dinding teks. */
.wcm-isian__head { display: flex; align-items: center; gap: 8px; width: 100%; text-align: left; border: 1px solid #eef0f7; border-radius: 11px; background: #f8fafc; padding: 8px 11px; font-size: 12.5px; font-weight: 700; color: #4b5563; cursor: pointer; }
.wcm-isian__head:hover { border-color: #c7d2fe; background: #f8f9ff; }
.wcm-isian__n { margin-left: auto; font-size: 11px; font-weight: 800; color: #4f46e5; background: #eef2ff; border-radius: 99px; padding: 1px 8px; }
.wcm-isian { display: grid; grid-template-columns: minmax(110px, 38%) 1fr; margin: 7px 0 0; font-size: 12px; border: 1px solid #eef0f7; border-radius: 11px; overflow: hidden; }
.wcm-isian dt, .wcm-isian dd { padding: 7px 11px; border-bottom: 1px solid #f4f5fa; }
.wcm-isian dt { color: #6b7280; background: #f8fafc; }
.wcm-isian dd { margin: 0; color: #1f2937; font-weight: 600; overflow-wrap: anywhere; }
.wcm-isian dt:nth-last-of-type(1), .wcm-isian dd:nth-last-of-type(1) { border-bottom: 0; }

.wcm-tes3, .wcm-ujian { border: 1px solid #eef0f7; border-radius: 12px; padding: 10px 12px; margin-bottom: 8px; background: #f8fafc; }
.wcm-tes3__head, .wcm-ujian__head { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; font-size: 12.5px; color: #111827; margin-bottom: 5px; }
.wcm-tes3__meta { display: flex; gap: 11px; flex-wrap: wrap; font-size: 11.5px; color: #6b7280; }
.wcm-tes3__meta b { color: #4f46e5; }
.wcm-tag { font-size: 10px; font-weight: 800; color: #64748b; background: #eef0f7; padding: 2px 8px; border-radius: 99px; display: inline-flex; align-items: center; gap: 5px; }
.wcm-tag.is-wajib { color: #b45309; background: rgba(245, 158, 11, 0.15); }

.wcm-nilai { margin: 6px 0 8px; }
.wcm-nilai__angka { font-size: 22px; font-weight: 800; color: #4f46e5; font-variant-numeric: tabular-nums; }
.wcm-nilai__angka span { font-size: 13px; color: #9ca3af; font-weight: 700; }
.wcm-nilai__bar { margin-top: 4px; height: 6px; border-radius: 99px; background: #eef0f7; overflow: hidden; }
.wcm-nilai__bar div { height: 100%; background: linear-gradient(90deg, #f87171, #ef4444); }
.wcm-nilai__bar div.is-lulus { background: linear-gradient(90deg, #34d399, #10b981); }
.wcm-rincian { margin-top: 8px; border-top: 1px solid #f1f3f9; padding-top: 7px; }
.wcm-rincian__row { display: flex; justify-content: space-between; gap: 12px; font-size: 11.5px; color: #6b7280; padding: 2px 0; }
.wcm-rincian__row b { color: #374151; }

.wcm-dok { display: flex; flex-direction: column; gap: 6px; margin-bottom: 10px; }
.wcm-dok__item { display: flex; align-items: center; gap: 10px; width: 100%; text-align: left; border: 1px solid #eef0f7; border-radius: 11px; background: #f8fafc; padding: 8px 11px; cursor: pointer; }
.wcm-dok__item:hover { border-color: #c7d2fe; background: #f8f9ff; }
.wcm-dok__item > .bi { font-size: 18px; color: #6366f1; flex: none; }
.wcm-dok__txt { flex: 1; min-width: 0; }
.wcm-dok__nama { display: block; font-size: 12.5px; font-weight: 700; color: #1f2937; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.wcm-dok__sub { display: block; font-size: 11px; color: #6b7280; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.wcm-dok__go { color: #c7cbd8; }

.wcm-jejak3 { display: flex; gap: 9px; align-items: flex-start; border: 1px solid #eef0f7; border-radius: 11px; padding: 8px 11px; margin-bottom: 6px; font-size: 12.5px; color: #374151; }
.wcm-jejak3__meta { font-size: 11px; color: #9ca3af; margin-top: 2px; }

.wcm-flags { display: flex; gap: 6px; flex-wrap: wrap; margin-top: 9px; }
.wcm-rencanates { margin-top: 10px; border-top: 1px solid #f1f3f9; padding-top: 8px; }
.wcm-rencanates__row { display: flex; justify-content: space-between; gap: 12px; font-size: 12px; color: #374151; padding: 3px 0; }
.wcm-rencanates__tag { font-size: 11px; color: #9ca3af; white-space: nowrap; }

@media (max-width: 860px) {
    .wcm-dw3 { padding-right: 0; }
    .wcm-dw3__panel { width: 100vw; }
}
</style>
