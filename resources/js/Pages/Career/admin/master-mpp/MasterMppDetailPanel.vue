<!--
  Master MPP — panel detail off-canvas. Baca lengkap (termasuk Tanggung Jawab/
  Persyaratan/Skill/Benefit, baca-saja — dipindah dari Monitoring MPP) PLUS
  aksi: Ubah / Batalkan-Aktifkan / Tandai Selesai langsung dari panel.
  Fetch via /api/v1/master-mpp/{no}. Tutup: tombol X, klik overlay, Escape.
-->
<template>
    <teleport to="body">
        <transition name="mmp-drawer">
            <div v-if="no" class="wca-drawer-mask wca" @click.self="$emit('close')">
                <aside ref="panel" class="wca-drawer wca-drawer--wide" role="dialog" aria-modal="true" :aria-label="`Detail MPP ${no}`">
                    <div class="wca-drawer__head">
                        <span class="wca-avatar"><i class="bi bi-briefcase-fill"></i></span>
                        <div style="min-width: 0">
                            <h3>{{ detail ? (detail.jabatan.nama || 'Jabatan belum diisi') : 'Memuat…' }}</h3>
                            <p><i class="bi bi-hash"></i>{{ no }}</p>
                        </div>
                        <button ref="closeBtn" class="wca-drawer__close" aria-label="Tutup detail" @click="$emit('close')"><i class="bi bi-x-lg"></i></button>
                    </div>

                    <div class="wca-drawer__body">
                        <template v-if="loading">
                            <div class="mmp-sk mmp-sk--grid">
                                <div v-for="n in 8" :key="n" class="mmp-sk__box"></div>
                            </div>
                            <div v-for="n in 3" :key="'s' + n" class="mmp-sk__card"></div>
                        </template>

                        <div v-else-if="error" class="wca-empty">
                            <i class="bi bi-exclamation-triangle"></i>
                            <h4>Gagal memuat detail</h4>
                            <button class="wca-btn wca-btn--ghost wca-btn--sm" @click="load"><i class="bi bi-arrow-clockwise"></i> Coba lagi</button>
                        </div>

                        <template v-else-if="detail">
                            <div class="mmp-dactions">
                                <button class="wca-btn wca-btn--dark wca-btn--sm" @click="$emit('edit', detail)"><i class="bi bi-pencil"></i> Ubah</button>
                                <button
                                    class="wca-btn wca-btn--soft wca-btn--sm"
                                    @click="$emit('toggle-selesai', detail)"
                                ><i class="bi" :class="detail.selesai ? 'bi-arrow-counterclockwise' : 'bi-check2-circle'"></i> {{ detail.selesai ? 'Belum Selesai' : 'Tandai Selesai' }}</button>
                                <!-- Muncul selama MPP ini PUNYA tenggat — bukan hanya saat
                                     server sudah bilang boleh. Kalau ternyata belum bisa
                                     (kursi sudah penuh, jatah habis), modalnya yang
                                     menerangkan sebabnya. Tombol yang hilang diam-diam
                                     tidak memberi tahu apa pun. -->
                                <button
                                    v-if="detail.perpanjangan?.tersedia && detail.jenisProgram !== 'MT' && detail.sla?.hari"
                                    class="wca-btn wca-btn--soft wca-btn--sm mmp-btn-sla"
                                    @click="$emit('perpanjang', detail)"
                                ><i class="bi bi-calendar-plus"></i> Perpanjang SLA</button>
                                <button v-if="detail.status === 'AKTIF'" class="wca-btn wca-btn--ghost wca-btn--sm" @click="$emit('batalkan', detail)"><i class="bi bi-x-circle"></i> Batalkan</button>
                                <button v-else class="wca-btn wca-btn--ghost wca-btn--sm" @click="$emit('aktifkan', detail)"><i class="bi bi-arrow-counterclockwise"></i> Aktifkan Kembali</button>
                            </div>

                            <div class="wca-dsec">
                                <h4>Informasi</h4>
                                <div class="wca-dinfo">
                                    <div><small>Status</small><b><span class="wca-badge" :class="statusBadge(detail.status)">{{ statusLabel(detail.status) }}</span></b></div>
                                    <div><small>Flag Selesai</small><b><span class="mmp-flag" :class="{ 'is-done': detail.selesai }"><span class="mmp-flag__dot"></span>{{ detail.selesai ? 'Selesai' : 'Berjalan' }}</span></b></div>
                                    <div><small>Jenis Program</small><b><span class="mmp-chip-program" :class="{ 'is-mt': detail.jenisProgram === 'MT' }"><i class="bi" :class="detail.jenisProgram === 'MT' ? 'bi-mortarboard-fill' : 'bi-person-workspace'"></i> {{ jenisProgramLabel(detail.jenisProgram) }}</span></b></div>
                                    <div><small>Divisi</small><b>{{ detail.divisi.nama || '—' }}</b></div>
                                    <div><small>Departemen</small><b>{{ detail.subDivisi?.nama || '—' }}</b></div>
                                    <div><small>Level</small><b>{{ detail.level.nama || '—' }}</b></div>
                                    <div><small>Jumlah Rekrutmen</small><b>{{ detail.jumlahRekrutmen }} orang</b></div>
                                    <div><small>{{ detail.sla?.mulai ? 'Periode Target' : 'Tenggat' }}</small><b>{{ periodeTeks }}</b></div>
                                    <div><small>Ketentuan SLA</small><b>{{ slaHariTeks }}</b></div>
                                    <!-- Muncul HANYA bila tenggatnya pernah bergeser. Baris
                                         "Tenggat Asli" yang selalu ada dan selalu sama dengan
                                         tenggat berlaku cuma menambah yang harus dibaca. -->
                                    <div v-if="adaPerpanjangan">
                                        <small>Tenggat Asli</small>
                                        <b class="mmp-sla-asli">{{ formatTanggal(detail.sla?.batasAwal) }}</b>
                                    </div>
                                    <div v-if="adaPerpanjangan">
                                        <small>Tenggat Berlaku</small>
                                        <b class="mmp-sla-baru">{{ formatTanggal(detail.sla?.batas) }}</b>
                                    </div>
                                    <div><small>Lokasi</small><b>{{ detail.lokasi.nama || '—' }}</b></div>
                                    <div><small>Penanggung Jawab</small><b>{{ detail.penanggungJawab.nama }}</b></div>
                                </div>
                            </div>

                            <div v-if="detail.employmentType || detail.workplaceType || detail.experienceLevel" class="wca-seccard mmp-detail-row">
                                <div class="wca-seccard__top">
                                    <span class="wca-seccard__ico" style="background: rgba(139, 92, 246, 0.1); color: #7c3aed"><i class="bi bi-info-circle"></i></span>
                                    <strong>Detail Pekerjaan</strong>
                                </div>
                                <div class="mmp-dpills">
                                    <span v-if="detail.employmentType" class="mmp-dpill mmp-dpill--emp" title="Tipe Kerja">
                                        <span class="mmp-dpill__ico"><i class="bi bi-briefcase-fill"></i></span>
                                        <span class="mmp-dpill__body"><small>Tipe Kerja</small><strong>{{ detail.employmentType.nama }}</strong></span>
                                    </span>
                                    <span v-if="detail.workplaceType" class="mmp-dpill mmp-dpill--wp" title="Lokasi Kerja">
                                        <span class="mmp-dpill__ico"><i class="bi bi-geo-alt-fill"></i></span>
                                        <span class="mmp-dpill__body"><small>Lokasi Kerja</small><strong>{{ detail.workplaceType.nama }}</strong></span>
                                    </span>
                                    <span v-if="detail.experienceLevel" class="mmp-dpill mmp-dpill--exp" title="Level Pengalaman">
                                        <span class="mmp-dpill__ico"><i class="bi bi-stars"></i></span>
                                        <span class="mmp-dpill__body"><small>Level Pengalaman</small><strong>{{ detail.experienceLevel.nama }}</strong></span>
                                    </span>
                                </div>
                            </div>

                            <!-- ── RIWAYAT PERPANJANGAN SLA ──────────────────────────────
                                 Muncul hanya bila memang pernah diperpanjang. Yang ditampilkan
                                 bukan sekadar daftar tanggal: ALASAN-nya yang jadi isi utama
                                 tiap baris, sebab itulah satu-satunya keterangan yang tidak
                                 bisa direkonstruksi dari data lain mana pun. -->
                            <div v-if="riwayatPanjang.length" class="wca-seccard mmp-sla-card">
                                <div class="wca-seccard__top">
                                    <span class="wca-seccard__ico" style="background: rgba(124, 58, 237, 0.12); color: #7c3aed"><i class="bi bi-calendar-plus"></i></span>
                                    <strong>Riwayat Perpanjangan SLA</strong>
                                    <span class="wca-badge wca-b--slate">{{ riwayatPanjang.length }}&times;</span>
                                </div>

                                <ol class="mmp-tl">
                                    <li v-for="r in riwayatPanjang" :key="r.id" class="mmp-tl__item">
                                        <span class="mmp-tl__dot">{{ r.ke }}</span>

                                        <div class="mmp-tl__isi">
                                            <div class="mmp-tl__geser">
                                                <span class="mmp-tl__lama">{{ formatTanggal(r.batasLama) }}</span>
                                                <i class="bi bi-arrow-right"></i>
                                                <span class="mmp-tl__baru">{{ formatTanggal(r.batasBaru) }}</span>
                                                <span class="mmp-tl__hari">+{{ r.hari }} hari kerja</span>
                                            </div>

                                            <p class="mmp-tl__alasan">{{ r.alasan }}</p>

                                            <div class="mmp-tl__kaki">
                                                <span><i class="bi bi-person"></i> {{ r.oleh || '—' }}</span>
                                                <span><i class="bi bi-clock"></i> {{ waktu(r.pada) }}</span>
                                                <!-- Keadaan kursi SAAT ITU — pembenaran yang dipakai
                                                     ("3 dibutuhkan, baru 1 terisi"), dan angka itu sudah
                                                     berubah sejak saat itu. -->
                                                <span v-if="r.kuota !== null">
                                                    <i class="bi bi-people"></i> {{ r.terisi }}/{{ r.kuota }} terisi saat itu
                                                </span>
                                            </div>
                                        </div>
                                    </li>
                                </ol>
                            </div>

                            <div class="wca-seccard sec-data">
                                <div class="wca-seccard__top">
                                    <span class="wca-seccard__ico"><i class="bi bi-file-earmark-text"></i></span>
                                    <strong>Deskripsi Pekerjaan</strong>
                                </div>
                                <p class="mmp-desc">{{ detail.deskripsi || '—' }}</p>
                            </div>

                            <div class="wca-seccard sec-say">
                                <div class="wca-seccard__top">
                                    <span class="wca-seccard__ico" style="background: rgba(16, 185, 129, 0.12); color: #059669"><i class="bi bi-list-check"></i></span>
                                    <strong>Tanggung Jawab</strong>
                                    <span class="wca-badge wca-b--slate">{{ detail.tanggungJawab.length }}</span>
                                </div>
                                <ul class="mmp-checklist">
                                    <li v-for="(t, i) in detail.tanggungJawab" :key="i"><i class="bi bi-check-circle-fill"></i><span>{{ t }}</span></li>
                                    <li v-if="!detail.tanggungJawab.length" class="mmp-empty-line">Belum ada tanggung jawab.</li>
                                </ul>
                            </div>

                            <div class="wca-seccard sec-upl">
                                <div class="wca-seccard__top">
                                    <span class="wca-seccard__ico" style="background: rgba(245, 158, 11, 0.12); color: #d97706"><i class="bi bi-clipboard-check"></i></span>
                                    <strong>Persyaratan</strong>
                                    <span class="wca-badge wca-b--slate">{{ detail.persyaratan.length }}</span>
                                </div>
                                <ul class="mmp-reqlist">
                                    <li v-for="(p, i) in detail.persyaratan" :key="i"><i class="bi bi-dot"></i><span>{{ p }}</span></li>
                                    <li v-if="!detail.persyaratan.length" class="mmp-empty-line">Belum ada persyaratan.</li>
                                </ul>
                            </div>

                            <div class="wca-seccard sec-tpl">
                                <div class="wca-seccard__top">
                                    <span class="wca-seccard__ico" style="background: rgba(139, 92, 246, 0.12); color: #7c3aed"><i class="bi bi-stars"></i></span>
                                    <strong>Skill yang Dibutuhkan ({{ detail.skill?.length || 0 }})</strong>
                                </div>

                                <div v-if="categorizedSkills.length" class="mmp-sk-cat-list">
                                    <div v-for="cat in categorizedSkills" :key="cat.key" class="mmp-sk-cat-block">
                                        <div class="mmp-sk-cat-header">
                                            <span class="mmp-sk-cat-badge" :style="{ background: cat.warna + '18', color: cat.warna, borderColor: cat.warna + '33' }">
                                                <i class="bi" :class="cat.ikon"></i> {{ cat.nama }}
                                            </span>
                                        </div>
                                        <div class="mmp-tags">
                                            <span
                                                v-for="s in cat.items" :key="s.id" class="mmp-tag"
                                                :style="{ color: cat.warna, background: cat.warna + '12', borderColor: cat.warna + '30' }"
                                            >
                                                {{ s.nama }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <span v-else class="mmp-empty-line">Belum ada skill.</span>
                            </div>

                            <div class="wca-seccard">
                                <div class="wca-seccard__top">
                                    <span class="wca-seccard__ico" style="background: rgba(16, 185, 129, 0.12); color: #059669"><i class="bi bi-gift"></i></span>
                                    <strong>Benefit</strong>
                                </div>
                                <div class="mmp-benefits">
                                    <div v-for="b in detail.benefit" :key="b.id" class="mmp-benefit"><i class="bi bi-check-circle-fill"></i><span>{{ b.nama }}</span></div>
                                    <span v-if="!detail.benefit.length" class="mmp-empty-line">Belum ada benefit.</span>
                                </div>
                            </div>
                        </template>
                    </div>
                </aside>
            </div>
        </transition>
    </teleport>
</template>

<script setup>
import { ref, computed, watch, onBeforeUnmount, nextTick } from 'vue';
import axios from 'axios';
import { formatTanggal, statusBadge, statusLabel, jenisProgramLabel } from '@utils/career/masterMpp';
import { rentangPendek } from '@utils/rentangTanggal';

const props = defineProps({ no: { type: String, default: null } });
const emit = defineEmits(['close', 'edit', 'toggle-selesai', 'batalkan', 'aktifkan', 'perpanjang']);

const detail = ref(null);
const loading = ref(false);
const error = ref(false);
const panel = ref(null);
const closeBtn = ref(null);

/**
 * Periode target sebagai RENTANG bila tanggal mulainya memang tersimpan.
 *
 * Satu tanggal di bawah label "Tanggal Periode" tidak bisa dibaca — mulai atau
 * selesai? Yang dimaksud rentang kerjanya: dari hari MPP dibuat sampai tenggat
 * SLA-nya. MPP lama (lahir sebelum snapshot SLA ada) tidak punya tanggal mulai,
 * dan di situ tenggatnya ditulis sendirian, dengan label yang sesuai.
 */
const periodeTeks = computed(() => rentangPendek(detail.value?.sla?.mulai, detail.value?.tanggalPeriode));

/**
 * ANGKA HARI KERJA YANG DIJANJIKAN — bukan tanggalnya.
 *
 * Rentang di atasnya tidak bisa menjawab pertanyaan ini. "20 Agu – 1 Okt" bisa
 * berasal dari janji 30 hari kerja maupun 45, tergantung berapa akhir pekan dan
 * hari libur yang kebetulan jatuh di dalamnya — dan menghitungnya mundur dari
 * dua tanggal adalah pekerjaan yang seharusnya tidak dibebankan ke pembaca.
 *
 * Angkanya sudah dibekukan di N_WEB_CAREERS_Detail_MPP sejak MPP-nya dibuat;
 * yang kurang selama ini cuma menuliskannya.
 *
 * Tiga keadaan sengaja dibedakan, sebab ketiganya berarti hal yang berlainan:
 *   MT       — memang tidak punya SLA, periodenya mengikuti tanggal dibuat.
 *   ada hari — janji yang berlaku untuk MPP ini.
 *   kosong   — MPP lahir sebelum SLA dicatat. "Tidak tercatat", bukan "tidak ada":
 *              menyamakannya dengan MT akan mengarang kebijakan yang tak pernah
 *              diputuskan siapa pun.
 */
const slaHariTeks = computed(() => {
    if (detail.value?.jenisProgram === 'MT') return 'Tidak terikat SLA';

    const hari = detail.value?.sla?.hari;

    return hari ? `${hari} hari kerja` : 'Tidak tercatat';
});

const riwayatPanjang = computed(() => detail.value?.perpanjangan?.riwayat || []);

const adaPerpanjangan = computed(() => Number(detail.value?.sla?.perpanjanganKe || 0) > 0);

/** "02 Sep 2026, 14.30" — tanggal saja tidak cukup: dua perpanjangan bisa
 *  terjadi di hari yang sama, dan urutannya jadi tidak terbaca. */
function waktu(v) {
    if (!v) return '—';

    const d = new Date(String(v).replace(' ', 'T'));

    return Number.isNaN(d.getTime())
        ? v
        : d.toLocaleString('id-ID', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}

const categorizedSkills = computed(() => {
    if (!detail.value?.skill || !detail.value.skill.length) return [];
    const map = new Map();
    for (const s of detail.value.skill) {
        const key = s.kategori ? s.kategori.id : '__tanpa__';
        if (!map.has(key)) {
            map.set(key, {
                key,
                nama: s.kategori ? s.kategori.nama : 'Lainnya / Umum',
                warna: s.kategori ? (s.kategori.warna || '#6366f1') : '#94a3b8',
                ikon: s.kategori?.ikon || 'bi-stars',
                items: [],
            });
        }
        map.get(key).items.push(s);
    }
    return [...map.values()];
});

let triggerEl = null;

async function load() {
    if (!props.no) return;
    loading.value = true;
    error.value = false;
    try {
        const res = await axios.get(`/api/v1/master-mpp/${encodeURIComponent(props.no)}`, { headers: { Accept: 'application/json' } });
        detail.value = res.data.result || null;
        if (!detail.value) error.value = true;
    } catch (e) {
        error.value = true;
    } finally {
        loading.value = false;
    }
}

defineExpose({ load });

function focusables() {
    if (!panel.value) return [];
    return Array.from(
        panel.value.querySelectorAll('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])')
    ).filter((el) => !el.disabled && el.offsetParent !== null);
}

function onKeydown(e) {
    if (e.key === 'Escape') {
        emit('close');
        return;
    }
    if (e.key !== 'Tab') return;
    const items = focusables();
    if (!items.length) return;
    const first = items[0];
    const last = items[items.length - 1];
    if (e.shiftKey && document.activeElement === first) {
        e.preventDefault();
        last.focus();
    } else if (!e.shiftKey && document.activeElement === last) {
        e.preventDefault();
        first.focus();
    }
}

watch(
    () => props.no,
    async (val) => {
        if (val) {
            triggerEl = document.activeElement;
            detail.value = null;
            load();
            document.addEventListener('keydown', onKeydown);
            document.body.style.overflow = 'hidden';
            await nextTick();
            closeBtn.value?.focus();
        } else {
            document.removeEventListener('keydown', onKeydown);
            document.body.style.overflow = '';
            if (triggerEl && typeof triggerEl.focus === 'function') triggerEl.focus();
            triggerEl = null;
        }
    },
    { immediate: true }
);

onBeforeUnmount(() => {
    document.removeEventListener('keydown', onKeydown);
    document.body.style.overflow = '';
});
</script>

<style scoped>
.wca-drawer--wide { width: min(540px, 100%); }
.wca-drawer__head p { display: inline-flex; align-items: center; gap: 0.1rem; font-family: 'JetBrains Mono', monospace; }

.mmp-drawer-enter-active, .mmp-drawer-leave-active { transition: opacity 0.28s ease; }
.mmp-drawer-enter-active .wca-drawer, .mmp-drawer-leave-active .wca-drawer { transition: transform 0.3s cubic-bezier(0.22, 1, 0.36, 1); }
.mmp-drawer-enter-from, .mmp-drawer-leave-to { opacity: 0; }
.mmp-drawer-enter-from .wca-drawer, .mmp-drawer-leave-to .wca-drawer { transform: translateX(100%); }

.mmp-dactions { display: flex; flex-wrap: wrap; gap: 0.5rem; margin-bottom: 1.1rem; }

.mmp-flag { display: inline-flex; align-items: center; gap: 0.35rem; font-size: 0.78rem; font-weight: 800; color: #94a3b8; }
.mmp-flag__dot { width: 0.55rem; height: 0.55rem; border-radius: 50%; background: #cbd5e1; }
.mmp-flag.is-done { color: #15803d; }
.mmp-flag.is-done .mmp-flag__dot { background: #22c55e; box-shadow: 0 0 0 3px rgba(34, 197, 94, 0.18); }

.mmp-chip-program { display: inline-flex; align-items: center; gap: 0.35rem; font-size: 0.78rem; font-weight: 800; color: #475569; }
.mmp-chip-program.is-mt { color: #7c3aed; }

.mmp-desc { margin: 0; font-size: 0.83rem; line-height: 1.6; color: var(--slate); }

.mmp-checklist, .mmp-reqlist { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 0.5rem; }
.mmp-checklist li, .mmp-reqlist li { display: flex; gap: 0.5rem; font-size: 0.82rem; line-height: 1.5; color: var(--slate); }
.mmp-checklist i { color: #10b981; margin-top: 0.15rem; flex: none; }
.mmp-reqlist i { color: #94a3b8; font-size: 1.1rem; line-height: 1; flex: none; }

.mmp-tags { display: flex; flex-wrap: wrap; gap: 0.4rem; }
.mmp-tags .mmp-tag {
    display: inline-flex; align-items: center; gap: 0.35rem;
    padding: 0.3rem 0.7rem; border-radius: 999px; font-size: 0.75rem; font-weight: 800; color: #4338ca;
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.12), rgba(139, 92, 246, 0.14));
    border: 1px solid rgba(99, 102, 241, 0.18);
}

.mmp-sk-cat-list {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    margin-top: 0.4rem;
}

.mmp-sk-cat-block {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
}

.mmp-sk-cat-header {
    display: flex;
    align-items: center;
}

.mmp-sk-cat-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    font-size: 0.7rem;
    font-weight: 800;
    padding: 0.15rem 0.5rem;
    border-radius: 0.4rem;
    border: 1px solid transparent;
    text-transform: uppercase;
    letter-spacing: 0.03em;
}

/* ── Riwayat perpanjangan SLA ───────────────────────────────────────────── */
.mmp-btn-sla { color: #7c3aed; }

.mmp-sla-asli { color: #94a3b8; text-decoration: line-through; }
.mmp-sla-baru { color: #7c3aed; }

.mmp-sla-card .wca-seccard__top .wca-badge { margin-left: auto; }

.mmp-tl { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 0.9rem; }

.mmp-tl__item { display: flex; gap: 0.7rem; position: relative; }

/* Garis penyambung antar butir — tanpa itu ia cuma tumpukan kotak, dan yang
   membacanya tidak melihat bahwa tenggatnya bergeser BERUNTUN. */
.mmp-tl__item:not(:last-child)::before {
    content: '';
    position: absolute;
    left: 0.72rem;
    top: 1.6rem;
    bottom: -0.9rem;
    width: 2px;
    background: #ede9fe;
}

.mmp-tl__dot {
    flex: none;
    display: grid;
    place-items: center;
    width: 1.5rem;
    height: 1.5rem;
    border-radius: 50%;
    font-size: 0.7rem;
    font-weight: 800;
    color: #fff;
    background: #7c3aed;
    z-index: 1;
}

.mmp-tl__isi { min-width: 0; flex: 1; }

.mmp-tl__geser {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.35rem;
    font-size: 0.78rem;
    font-weight: 800;
}
.mmp-tl__lama { color: #94a3b8; text-decoration: line-through; }
.mmp-tl__geser i { color: #7c3aed; font-size: 0.72rem; }
.mmp-tl__baru { color: #0f172a; }
.mmp-tl__hari {
    padding: 0.1rem 0.4rem;
    border-radius: 999px;
    font-size: 0.66rem;
    color: #6d28d9;
    background: #ede9fe;
}

/* ALASAN — bagian yang paling harus terbaca di butir ini. Diberi latar dan
   garis tepi kiri supaya ia tidak terbaca sebagai keterangan tambahan. */
.mmp-tl__alasan {
    margin: 0.4rem 0 0;
    padding: 0.45rem 0.6rem;
    border-left: 3px solid #ddd6fe;
    border-radius: 0 0.4rem 0.4rem 0;
    font-size: 0.8rem;
    line-height: 1.6;
    color: #334155;
    background: #faf9ff;
    white-space: pre-line;
    overflow-wrap: anywhere;
}

.mmp-tl__kaki {
    display: flex;
    flex-wrap: wrap;
    gap: 0.75rem;
    margin-top: 0.35rem;
    font-size: 0.7rem;
    font-weight: 700;
    color: #94a3b8;
}
.mmp-tl__kaki span { display: inline-flex; align-items: center; gap: 0.25rem; }

.mmp-benefits { display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem; }
.mmp-benefit { display: flex; align-items: center; gap: 0.45rem; font-size: 0.8rem; font-weight: 700; color: var(--slate); }
.mmp-benefit i { color: #10b981; flex: none; }
.mmp-empty-line { font-size: 0.8rem; color: var(--muted); font-style: italic; }

.mmp-sk--grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0.6rem; }
.mmp-sk__box { height: 3rem; border-radius: 0.6rem; }
.mmp-sk__card { height: 5.5rem; border-radius: 0.85rem; }
.mmp-sk__box, .mmp-sk__card {
    background: linear-gradient(90deg, #f1f5f9 25%, #e9eef5 37%, #f1f5f9 63%);
    background-size: 400% 100%;
    animation: mmpShimmer 1.3s ease infinite;
}
@keyframes mmpShimmer {
    0% { background-position: 100% 0; }
    100% { background-position: -100% 0; }
}

@media (max-width: 640px) {
    .mmp-benefits { grid-template-columns: 1fr; }
}

.mmp-detail-row { border-left-color: #7c3aed; }
.mmp-dpills { display: flex; flex-wrap: wrap; gap: 0.6rem; }
.mmp-dpill { display: flex; align-items: flex-start; gap: 0.55rem; padding: 0.55rem 0.7rem; border-radius: 0.75rem; flex: 1 1 150px; min-width: 140px; transition: transform 0.12s ease; }
.mmp-dpill:hover { transform: translateY(-1px); }
.mmp-dpill--emp { background: linear-gradient(135deg, rgba(99, 102, 241, 0.08), rgba(139, 92, 246, 0.04)); border: 1px solid rgba(99, 102, 241, 0.15); }
.mmp-dpill--wp { background: linear-gradient(135deg, rgba(16, 185, 129, 0.08), rgba(6, 182, 212, 0.04)); border: 1px solid rgba(16, 185, 129, 0.15); }
.mmp-dpill--exp { background: linear-gradient(135deg, rgba(245, 158, 11, 0.08), rgba(251, 191, 36, 0.04)); border: 1px solid rgba(245, 158, 11, 0.18); }
.mmp-dpill__ico { width: 2rem; height: 2rem; border-radius: 0.55rem; display: grid; place-items: center; flex: none; font-size: 0.88rem; }
.mmp-dpill--emp .mmp-dpill__ico { background: rgba(99, 102, 241, 0.15); color: #4338ca; }
.mmp-dpill--wp .mmp-dpill__ico { background: rgba(16, 185, 129, 0.15); color: #0f766e; }
.mmp-dpill--exp .mmp-dpill__ico { background: rgba(245, 158, 11, 0.15); color: #b45309; }
.mmp-dpill__body { display: flex; flex-direction: column; gap: 0.1rem; min-width: 0; }
.mmp-dpill__body small { font-size: 0.68rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.03em; color: var(--muted); line-height: 1; }
.mmp-dpill__body strong { font-size: 0.84rem; font-weight: 900; color: var(--ink); line-height: 1.2; }
</style>
