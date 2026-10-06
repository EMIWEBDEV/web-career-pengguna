<!--
  Master MPP — MEMPERPANJANG TENGGAT (SLA) SEBUAH MPP.

  ══ YANG DIPUTUSKAN DI LAYAR INI: HANYA ALASANNYA ═══════════════════════════

  Panjang perpanjangannya TIDAK bisa diketik di sini, dan itu disengaja. Ia
  mengikuti SLA level MPP ini — 45 hari kerja diperpanjang 45 hari kerja lagi.
  Kalau angkanya bebas diisi admin, ia akan diisi sebesar yang dibutuhkan
  supaya tidak pernah telat lagi, dan tenggatnya berhenti berarti apa pun.

  Karena itu bentuk modal ini bukan borang berisi beberapa bidang, melainkan
  RINGKASAN yang sudah jadi + satu kotak alasan. Yang dibaca admin sebelum
  menekan tombol adalah kalimat yang sudah lengkap: dari tanggal berapa, ke
  tanggal berapa, berapa hari kerja, perpanjangan keberapa.

  ══ KENAPA TANGGAL BARUNYA DIHITUNG SERVER, BUKAN DI SINI ═══════════════════

  Hari kerja bukan hitungan yang bisa ditebak layar: ia melewati akhir pekan
  DAN daftar hari libur yang hidup di HRIS_Hari_Libur. Menghitungnya di sini
  berarti tanggal yang disetujui admin bisa berbeda dari yang kelak tertulis —
  dan tidak ada cara baginya tahu mana yang benar.

  Semua angka di modal ini datang dari detail MPP-nya (PerpanjangSla::keadaan),
  sumber yang sama persis dengan yang dipakai pintu simpan.
-->
<template>
    <ConfirmModal
        :show="show"
        :busy="busy"
        form-mode
        :danger="false"
        title="Perpanjang Tenggat SLA"
        :subtitle="`${no} — perpanjangan ke-${keadaan?.ke ?? 1}`"
        icon="bi-calendar-plus"
        size="lg"
        confirm-icon="bi-calendar-plus"
        confirm-label="Perpanjang Tenggat"
        busy-label="Memperpanjang…"
        :confirm-disabled="!bolehKirim"
        @cancel="$emit('close')"
        @confirm="$emit('confirm', alasan.trim())"
    >
        <!-- SEDANG MEMUAT — keadaannya ditanyakan ke server dulu (tenggat baru
             dihitung di sana). Tanpa blok ini, isi modal KOSONG selama beberapa
             ratus milidetik: yang terlihat cuma judul dan dua tombol menggantung,
             dan itu terbaca sebagai modal yang rusak, bukan modal yang menunggu. -->
        <div v-if="!keadaan" class="psl-muat">
            <span class="psl-muat__spin"></span>
            <p>Menghitung tenggat baru…</p>
        </div>

        <!-- TERTOLAK: sebabnya ditulis, bukan tombol yang diam-diam mati.
             Tombol mati tanpa keterangan membuat admin mengira layarnya rusak. -->
        <div v-else-if="!keadaan.boleh" class="psl-tolak">
            <i class="bi bi-shield-exclamation"></i>
            <p>{{ keadaan.alasanTolak || 'MPP ini tidak bisa diperpanjang.' }}</p>
        </div>

        <template v-else>
            <!-- ── RINGKASAN PERGESERAN ─────────────────────────────────────
                 Tenggat lama dan baru berdampingan dengan panah di antaranya.
                 Ini bagian yang paling harus terbaca sekali lihat: yang sedang
                 disetujui admin adalah PERPINDAHAN, bukan sebuah tanggal. -->
            <div class="psl-geser">
                <div class="psl-geser__sisi">
                    <small>Tenggat sekarang</small>
                    <b :class="{ 'is-lewat': keadaan.lewat }">{{ tgl(keadaan.batas) }}</b>
                    <span v-if="keadaan.lewat" class="psl-lewat">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                        Lewat {{ keadaan.hariTerlambat }} hari kerja
                    </span>
                </div>

                <div class="psl-geser__panah">
                    <i class="bi bi-arrow-right"></i>
                    <span>+{{ keadaan.hari }} hari kerja</span>
                </div>

                <div class="psl-geser__sisi psl-geser__sisi--baru">
                    <small>Tenggat baru</small>
                    <b>{{ tgl(keadaan.batasBaru) }}</b>
                </div>
            </div>

            <!-- ATURANNYA DITULIS, tidak diandaikan sudah diketahui. Yang membuka
                 modal ini mungkin baru pertama kali memperpanjang, dan pertanyaan
                 pertamanya selalu "kenapa 45, siapa yang menentukan". -->
            <p class="psl-aturan">
                <i class="bi bi-info-circle"></i>
                Panjangnya mengikuti ketentuan SLA level MPP ini
                (<strong>{{ keadaan.hari }} hari kerja</strong>) dan dihitung dari tenggat lama —
                bukan dari hari ini, supaya menunda perpanjangan tidak menambah jatah.
                <template v-if="keadaan.batasAwal && keadaan.batasAwal !== keadaan.batas">
                    Tenggat aslinya <strong>{{ tgl(keadaan.batasAwal) }}</strong>.
                </template>
            </p>

            <!-- ── ALASAN: INTI DARI SELURUH LAYAR INI ──────────────────────── -->
            <label class="psl-label" for="psl-alasan">
                Alasan perpanjangan <span class="psl-wajib">wajib</span>
            </label>
            <textarea
                id="psl-alasan"
                ref="kotak"
                v-model="alasan"
                class="wca-input psl-textarea"
                rows="4"
                :maxlength="MAKS"
                placeholder="Contoh: Kandidat final mengundurkan diri di tahap offering, sourcing diulang dari awal."
            ></textarea>

            <div class="psl-bawah">
                <!-- Kenapa alasannya dituntut — bukan sekadar "wajib diisi".
                     Kalimat yang menyebut SIAPA yang akan membacanya membuat
                     isian yang ditulis benar-benar menjawab sesuatu. -->
                <span class="psl-bantu">
                    Dibaca kembali saat MPP ini ditinjau — tulis sebabnya, bukan hanya "diperpanjang".
                </span>
                <span class="psl-hitung" :class="{ 'is-kurang': kurang > 0 }">
                    {{ kurang > 0 ? `kurang ${kurang} huruf` : `${alasan.trim().length}/${MAKS}` }}
                </span>
            </div>

            <p v-if="keadaan.sisaJatah <= 1" class="psl-sisa">
                <i class="bi bi-hourglass-split"></i>
                <template v-if="keadaan.sisaJatah === 1">
                    Ini kesempatan perpanjangan <strong>terakhir</strong> untuk MPP ini.
                </template>
                <template v-else>Jatah perpanjangan MPP ini sudah habis.</template>
            </p>
        </template>
    </ConfirmModal>
</template>

<script setup>
import { ref, computed, watch, nextTick } from 'vue';
import ConfirmModal from '@career/ConfirmModal.vue';
import { formatTanggal } from '@utils/career/masterMpp';

// Sama dengan PerpanjangSla::MIN_ALASAN / MAKS_ALASAN di server. Layar menahan
// lebih dulu supaya galatnya terlihat sebelum tombol ditekan; server tetap
// menahan sendiri, sebab layar bisa dilewati.
const MIN = 10;
const MAKS = 1000;

const props = defineProps({
    show: { type: Boolean, default: false },
    busy: { type: Boolean, default: false },
    no: { type: String, default: '' },
    // Bentuknya = PerpanjangSla::keadaan(). null selagi detailnya dimuat.
    keadaan: { type: Object, default: null },
});

defineEmits(['close', 'confirm']);

const alasan = ref('');
const kotak = ref(null);

const kurang = computed(() => Math.max(0, MIN - alasan.value.trim().length));
const bolehKirim = computed(() => !!props.keadaan?.boleh && kurang.value === 0);

// Dikosongkan setiap kali dibuka — BUKAN dibiarkan. Alasan yang tertinggal dari
// MPP sebelumnya adalah alasan yang salah menempel pada MPP yang salah, dan
// tidak ada yang akan menyadarinya saat membaca laporan berbulan-bulan kemudian.
watch(
    () => props.show,
    async (buka) => {
        if (!buka) return;
        alasan.value = '';
        await nextTick();
        kotak.value?.focus();
    },
);

function tgl(v) {
    return formatTanggal(v);
}
</script>

<style scoped>
/* ── Pergeseran tenggat ─────────────────────────────────────────────────── */
.psl-geser {
    display: grid;
    grid-template-columns: 1fr auto 1fr;
    align-items: center;
    gap: 0.75rem;
    padding: 0.9rem 1rem;
    border: 1px solid var(--line, #e2e8f0);
    border-radius: 0.75rem;
    background: var(--soft, #f8fafc);
}
.psl-geser__sisi { display: flex; flex-direction: column; gap: 0.2rem; min-width: 0; }
.psl-geser__sisi small { font-size: 0.7rem; font-weight: 700; letter-spacing: 0.02em; text-transform: uppercase; color: var(--slate, #64748b); }
.psl-geser__sisi b { font-size: 0.95rem; font-weight: 800; color: #0f172a; }
.psl-geser__sisi b.is-lewat { color: #dc2626; }
.psl-geser__sisi--baru b { color: #059669; }

.psl-geser__panah { display: flex; flex-direction: column; align-items: center; gap: 0.15rem; color: #6366f1; }
.psl-geser__panah i { font-size: 1.1rem; }
.psl-geser__panah span { font-size: 0.68rem; font-weight: 800; white-space: nowrap; }

.psl-lewat { display: inline-flex; align-items: center; gap: 0.3rem; font-size: 0.7rem; font-weight: 700; color: #dc2626; }

/* Ponsel: panah berputar jadi menunjuk ke bawah — panah ke kanan di atas
   tumpukan vertikal menunjuk ke tempat yang tidak ada isinya. */
@media (max-width: 30rem) {
    .psl-geser { grid-template-columns: 1fr; text-align: center; }
    .psl-geser__panah i { transform: rotate(90deg); }
}

/* ── Aturan & bantuan ───────────────────────────────────────────────────── */
/* BUKAN flex. Dulu di sini `display:flex`, dan itu membuat setiap <strong> di
   dalamnya ikut jadi flex ITEM — angka "30 hari kerja" dan tanggalnya terlempar
   menjadi kolom-kolom sempit sendiri, memotong kalimatnya di tengah. Ikonnya
   cukup mengambang di kiri lewat float. */
.psl-aturan {
    margin: 0.85rem 0 0;
    font-size: 0.76rem;
    line-height: 1.55;
    color: var(--slate, #64748b);
}
.psl-aturan i { float: left; margin: 0.15rem 0.45rem 0 0; color: #6366f1; }
.psl-aturan strong { color: #0f172a; }

/* ── Alasan ─────────────────────────────────────────────────────────────── */
.psl-label {
    display: block;
    margin: 1rem 0 0.4rem;
    font-size: 0.78rem;
    font-weight: 800;
    color: #0f172a;
}
.psl-wajib {
    margin-left: 0.3rem;
    padding: 0.1rem 0.4rem;
    border-radius: 0.35rem;
    font-size: 0.64rem;
    font-weight: 800;
    text-transform: uppercase;
    color: #b91c1c;
    background: rgba(220, 38, 38, 0.1);
}
.psl-textarea { width: 100%; resize: vertical; line-height: 1.55; }

.psl-bawah {
    display: flex;
    justify-content: space-between;
    align-items: baseline;
    gap: 0.75rem;
    margin-top: 0.35rem;
}
.psl-bantu { font-size: 0.72rem; color: var(--slate, #64748b); }
.psl-hitung { flex: none; font-size: 0.72rem; font-weight: 700; color: var(--slate, #94a3b8); }
.psl-hitung.is-kurang { color: #dc2626; }

.psl-sisa {
    display: flex;
    gap: 0.45rem;
    margin: 0.85rem 0 0;
    padding: 0.6rem 0.75rem;
    border-radius: 0.6rem;
    font-size: 0.75rem;
    line-height: 1.5;
    color: #92400e;
    background: rgba(245, 158, 11, 0.1);
}
.psl-sisa i { flex: none; margin-top: 0.1rem; }

/* ── Sedang memuat ──────────────────────────────────────────────────────── */
.psl-muat {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 0.7rem;
    min-height: 132px;
    padding: 1rem;
}
.psl-muat p { margin: 0; font-size: 0.78rem; font-weight: 700; color: var(--slate, #64748b); }
.psl-muat__spin {
    width: 26px;
    height: 26px;
    border-radius: 50%;
    border: 2.5px solid #e2e8f0;
    border-top-color: #6366f1;
    animation: pslSpin 0.7s linear infinite;
}
@keyframes pslSpin { to { transform: rotate(360deg); } }

/* ── Tertolak ───────────────────────────────────────────────────────────── */
.psl-tolak {
    display: flex;
    gap: 0.6rem;
    padding: 0.9rem 1rem;
    border-radius: 0.7rem;
    background: rgba(220, 38, 38, 0.08);
}
.psl-tolak i { flex: none; margin-top: 0.1rem; font-size: 1.05rem; color: #dc2626; }
.psl-tolak p { margin: 0; font-size: 0.82rem; line-height: 1.6; color: #7f1d1d; }
</style>
