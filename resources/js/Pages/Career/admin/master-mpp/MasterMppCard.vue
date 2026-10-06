<!-- Kartu ringkas MPP (Card Grid). Klik/Enter/Space → emit 'open' (panel detail).
     Ikon aksi cepat (Ubah/Selesai/Batalkan) di footer kartu. -->
<template>
    <article
        class="mmp-card"
        :class="{ 'is-off': mpp.status !== 'AKTIF', 'is-done': mpp.selesai, 'is-mt': mpp.jenisProgram === 'MT' }"
        role="button"
        tabindex="0"
        @click="$emit('open', mpp.noTransaksi)"
        @keydown.enter="$emit('open', mpp.noTransaksi)"
        @keydown.space.prevent="$emit('open', mpp.noTransaksi)"
    >
        <!-- 1. Header: Title & Badges Row -->
        <div class="mmp-card__head">
            <div class="mmp-card__title-row">
                <h3 class="mmp-card__title" :title="mpp.jabatan?.nama || 'Jabatan belum diisi'">
                    {{ mpp.jabatan?.nama || 'Jabatan belum diisi' }}
                </h3>
                <span class="wca-badge" :class="statusBadge(mpp.status)">
                    {{ statusLabel(mpp.status) }}
                </span>
            </div>

            <div class="mmp-card__sub-row">
                <span class="mmp-card__no" :title="'No Transaksi: ' + mpp.noTransaksi">
                    <i class="bi bi-hash"></i>{{ mpp.noTransaksi }}
                </span>
                <span
                    v-if="mpp.jenisProgram"
                    class="mmp-card__prog"
                    :class="{ 'is-mt': mpp.jenisProgram === 'MT' }"
                    :title="'Jenis Program: ' + jenisProgramLabel(mpp.jenisProgram)"
                >
                    <i class="bi" :class="mpp.jenisProgram === 'MT' ? 'bi-mortarboard-fill' : 'bi-person-workspace'"></i>
                    {{ jenisProgramLabel(mpp.jenisProgram) }}
                </span>
                <!-- "Berjalan" tidak ditulis: itu keadaan hampir semua MPP, dan
                     penanda yang muncul di setiap kartu tidak membedakan apa pun —
                     ia cuma memenuhi baris yang sama dengan nomor transaksi dan
                     jenis program. Yang tersisa "Selesai", yang memang kabar. -->
                <span v-if="mpp.selesai" class="mmp-status-flag is-done">
                    <span class="mmp-status-flag__dot"></span>
                    Selesai
                </span>
                <!-- Lencana SLA — lihat catatan di <script>. -->
                <span
                    v-if="diperpanjang"
                    class="mmp-sla-tag is-panjang"
                    :title="`Tenggat SLA sudah diperpanjang ${diperpanjang} kali. Tenggat asli ${formatTanggal(mpp.sla?.batasAwal)}.`"
                >
                    <i class="bi bi-calendar-plus"></i> Diperpanjang {{ diperpanjang }}&times;
                </span>
                <span
                    v-if="slaLewat"
                    class="mmp-sla-tag is-lewat"
                    :title="`Tenggat SLA (${formatTanggal(slaBatas)}) sudah lewat ${Math.abs(sisaHari)} hari.`"
                >
                    <i class="bi bi-exclamation-triangle-fill"></i> Lewat SLA
                </span>
                <span
                    v-else-if="slaSegera"
                    class="mmp-sla-tag is-segera"
                    :title="`Tenggat SLA ${formatTanggal(slaBatas)} — tinggal ${sisaHari} hari lagi.`"
                >
                    <i class="bi bi-hourglass-split"></i>
                    {{ sisaHari === 0 ? 'Jatuh tempo hari ini' : `${sisaHari} hari lagi` }}
                </span>
                <!-- MASIH LONGGAR pun tetap menyebut sisanya. Kartu yang diam
                     saat semuanya aman membuat "tidak ada lencana" punya dua
                     arti sekaligus: aman, atau tidak punya tenggat sama sekali. -->
                <span
                    v-else-if="sisaHari !== null"
                    class="mmp-sla-tag is-aman"
                    :title="`Tenggat SLA ${formatTanggal(slaBatas)} — ${sisaHari} hari lagi.`"
                >
                    <i class="bi bi-calendar3"></i> {{ sisaHari }} hari lagi
                </span>
            </div>
        </div>

        <!-- 2. Body: Division, Quota/Period Bar, Chips -->
        <div class="mmp-card__body">
            <div class="mmp-card__dept" :title="(mpp.divisi?.nama || '—') + (mpp.subDivisi ? ' / ' + mpp.subDivisi.nama : '')">
                <i class="bi bi-diagram-3-fill"></i>
                <span class="mmp-card__div-name">{{ mpp.divisi?.nama || '—' }}</span>
                <span v-if="mpp.subDivisi" class="mmp-card__sub-name">/ {{ mpp.subDivisi.nama }}</span>
            </div>

            <div class="mmp-card__meta-bar">
                <span class="mmp-meta-pill" title="Kuota Rekrutmen Target">
                    <i class="bi bi-people-fill"></i>
                    <strong>{{ mpp.jumlahRekrutmen }}</strong> orang
                </span>
                <!-- PERIODE, bukan satu tanggal: dari hari MPP dibuat sampai tenggat
                     SLA-nya. Bentuk pendek ("20 Agu – 01 Okt 2026") supaya muat di
                     kartu; MPP lama yang tidak menyimpan tanggal mulai jatuh ke
                     tenggatnya saja, berikut judul & ikon yang menyesuaikan. -->
                <span class="mmp-meta-pill" :title="judulPeriode">
                    <i class="bi" :class="adaRentang ? 'bi-calendar-range' : 'bi-calendar-check'"></i>
                    {{ rentangPendek(mpp.sla?.mulai, mpp.tanggalPeriode) }}
                </span>
            </div>

            <!-- ── BILAH SLA ─────────────────────────────────────────────────
                 SELALU ada selama MPP-nya punya tenggat — bukan hanya saat
                 hampir/lewat. Inilah yang menjawab "kapan harus diperpanjang"
                 tanpa perlu membuka panel detail: ketentuan levelnya berapa hari
                 kerja, tenggatnya kapan, dan tinggal berapa lama lagi.

                 Bilah kemajuannya memakai PORSI WAKTU YANG SUDAH TERPAKAI, bukan
                 sisa: yang dibaca sekali lihat adalah seberapa jauh MPP ini sudah
                 berjalan terhadap janjinya. -->
            <div v-if="adaSla" class="mmp-slabar" :class="kelasSla">
                <div class="mmp-slabar__top">
                    <span class="mmp-slabar__ket">
                        <i class="bi bi-stopwatch"></i>
                        SLA {{ mpp.sla.hari }} hari kerja
                    </span>
                    <span class="mmp-slabar__tgl" :title="judulSla">
                        <template v-if="diperpanjang">
                            <s>{{ formatTanggal(mpp.sla.batasAwal) }}</s>
                        </template>
                        {{ formatTanggal(slaBatas) }}
                    </span>
                </div>

                <div class="mmp-slabar__rel"><span :style="{ width: pakaiPersen + '%' }"></span></div>

                <div class="mmp-slabar__kaki">
                    <span>{{ tekstSisa }}</span>
                    <button class="mmp-slabar__btn" type="button" @click.stop="$emit('perpanjang', mpp)">
                        <i class="bi bi-calendar-plus"></i> Perpanjang
                    </button>
                </div>
            </div>

            <div class="mmp-card__tags" v-if="allTags.length">
                <span
                    v-for="t in allTags.slice(0, 2)"
                    :key="t.key"
                    class="mmp-chip"
                    :title="t.title"
                >
                    <i class="bi" :class="t.icon"></i> {{ t.label }}
                </span>

                <!-- Hover Popover untuk sisa tag (+N) -->
                <div v-if="allTags.length > 2" class="mmp-more-wrap" @click.stop>
                    <span class="mmp-chip mmp-chip--more">
                        +{{ allTags.length - 2 }}
                    </span>
                    <div class="mmp-more-popover">
                        <div class="mmp-more-popover__head">Atribut Lainnya ({{ allTags.length - 2 }})</div>
                        <div v-for="t in allTags.slice(2)" :key="t.key" class="mmp-more-popover__item">
                            <i class="bi" :class="t.icon"></i>
                            <span>{{ t.title }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 3. Footer: PIC & Actions -->
        <div class="mmp-card__foot">
            <div class="mmp-card__pj">
                <span class="wca-avatar wca-avatar--sm">{{ initials(mpp.penanggungJawab?.nama) }}</span>
                <span class="mmp-card__pj-name" :title="'Penanggung Jawab: ' + (mpp.penanggungJawab?.nama || '—')">{{ mpp.penanggungJawab?.nama || '—' }}</span>
            </div>

            <div class="mmp-card__actions">
                <button
                    class="wca-iconbtn"
                    title="Ubah Transaksi MPP"
                    @click.stop="$emit('edit', mpp)"
                >
                    <i class="bi bi-pencil"></i>
                </button>
                <button
                    class="wca-iconbtn"
                    :class="{ 'wca-iconbtn--success': !mpp.selesai }"
                    :title="mpp.selesai ? 'Tandai belum selesai' : 'Tandai selesai'"
                    @click.stop="$emit('toggle-selesai', mpp)"
                >
                    <i class="bi" :class="mpp.selesai ? 'bi-arrow-counterclockwise' : 'bi-check2-circle'"></i>
                </button>
                <button
                    v-if="bolehPerpanjang"
                    class="wca-iconbtn mmp-iconbtn--sla"
                    :title="slaLewat ? 'Perpanjang tenggat SLA (sudah lewat)' : 'Perpanjang tenggat SLA'"
                    @click.stop="$emit('perpanjang', mpp)"
                >
                    <i class="bi bi-calendar-plus"></i>
                </button>
                <button
                    v-if="mpp.status === 'AKTIF'"
                    class="wca-iconbtn wca-iconbtn--danger"
                    title="Batalkan transaksi"
                    @click.stop="$emit('batalkan', mpp)"
                >
                    <i class="bi bi-x-circle"></i>
                </button>
                <button
                    v-else
                    class="wca-iconbtn wca-iconbtn--success"
                    title="Aktifkan kembali"
                    @click.stop="$emit('aktifkan', mpp)"
                >
                    <i class="bi bi-arrow-counterclockwise"></i>
                </button>
            </div>
        </div>
    </article>
</template>

<script setup>
import { computed } from 'vue';
import { formatTanggal, initials, statusBadge, statusLabel, jenisProgramLabel } from '@utils/career/masterMpp';
import { punyaRentang, rentangPendek } from '@utils/rentangTanggal';

const props = defineProps({ mpp: { type: Object, required: true } });
defineEmits(['open', 'edit', 'toggle-selesai', 'batalkan', 'aktifkan', 'perpanjang']);

const adaRentang = computed(() => punyaRentang(props.mpp.sla?.mulai, props.mpp.tanggalPeriode));

/* ── PENANDA SLA DI KARTU ────────────────────────────────────────────────────
 *
 * Tiga keadaan yang perlu terlihat TANPA membuka panel, sebab ketiganya menuntut
 * tindakan yang berbeda dari orang yang sedang memindai daftar:
 *
 *   diperpanjang  tenggatnya sudah pernah digeser — angkanya ("2x") ikut, sebab
 *                 "pernah diperpanjang" dan "diperpanjang tiga kali" adalah dua
 *                 kabar yang sama sekali berbeda.
 *   lewat         tenggatnya sudah terlampaui dan MPP-nya masih berjalan. Inilah
 *                 yang mencari tombol Perpanjang.
 *   segera        tenggatnya tinggal seminggu kerja lagi — peringatan dini,
 *                 supaya perpanjangan tidak selalu terjadi sesudah terlambat.
 *
 * MPP yang sudah selesai/dibatalkan tidak ikut ditandai: tenggat yang lewat pada
 * pekerjaan yang sudah tuntas bukan kabar, cuma bunyi.
 */
/* TENGGAT SLA — null berarti MPP ini memang TIDAK PUNYA tenggat.
 *
 * Dulu di sini ada jatuh-tempo ke `tanggalPeriode`, dan itu salah pada satu
 * kasus yang justru paling sering terlihat: program MT. MT tidak terikat SLA
 * sama sekali, `sla`-nya null, dan `tanggalPeriode`-nya adalah TANGGAL MPP
 * DIBUAT — yang menurut definisinya selalu sudah lewat. Akibatnya setiap kartu
 * MT memakai lencana merah "Lewat SLA" untuk tenggat yang tidak pernah ada.
 *
 * MPP lama (lahir sebelum snapshot SLA ada) ikut tersaring lewat syarat yang
 * sama, dan itu memang benar: tanpa angka hari kerja yang tersimpan, tidak ada
 * ketentuan yang bisa dinyatakan terlampaui.
 */
const slaBatas = computed(() => {
    if (props.mpp.jenisProgram === 'MT' || !props.mpp.sla?.hari) return null;

    return props.mpp.sla.batas || props.mpp.tanggalPeriode || null;
});

const diperpanjang = computed(() => Number(props.mpp.sla?.perpanjanganKe || 0));

const sisaHari = computed(() => {
    if (!slaBatas.value || props.mpp.selesai || props.mpp.status !== 'AKTIF') return null;

    const b = new Date(`${slaBatas.value}T00:00:00`);
    if (Number.isNaN(b.getTime())) return null;

    const kini = new Date();
    kini.setHours(0, 0, 0, 0);

    return Math.round((b - kini) / 86400000);
});

const slaLewat = computed(() => sisaHari.value !== null && sisaHari.value < 0);
const slaSegera = computed(() => sisaHari.value !== null && sisaHari.value >= 0 && sisaHari.value <= 7);

/* Bilah SLA ditampilkan? — hanya butuh tenggat yang memang ada. Sengaja TIDAK
 * menuntut MPP-nya masih berjalan: pada MPP yang sudah selesai, bilah ini justru
 * jadi catatan "tuntas 12 hari sebelum tenggat", dan itu kabar yang berguna. */
const adaSla = computed(() => props.mpp.jenisProgram !== 'MT' && !!props.mpp.sla?.hari && !!slaBatas.value);

/* Porsi waktu yang SUDAH TERPAKAI, dari hari MPP dibuat sampai tenggatnya.
 * Dipatok 0–100 supaya yang sudah lewat tidak menggambar bilah melewati kotaknya. */
const pakaiPersen = computed(() => {
    const mulai = props.mpp.sla?.mulai;
    if (!mulai || !slaBatas.value) return 0;

    const a = new Date(`${mulai}T00:00:00`).getTime();
    const b = new Date(`${slaBatas.value}T00:00:00`).getTime();
    if (Number.isNaN(a) || Number.isNaN(b) || b <= a) return 0;

    const kini = new Date().setHours(0, 0, 0, 0);

    return Math.min(100, Math.max(0, Math.round(((kini - a) / (b - a)) * 100)));
});

const tekstSisa = computed(() => {
    if (props.mpp.selesai) return 'Sudah ditandai selesai';
    if (sisaHari.value === null) return formatTanggal(slaBatas.value);
    if (sisaHari.value < 0) return `Lewat ${Math.abs(sisaHari.value)} hari`;
    if (sisaHari.value === 0) return 'Jatuh tempo hari ini';

    return `Tinggal ${sisaHari.value} hari`;
});

const kelasSla = computed(() => ({
    'is-lewat': slaLewat.value,
    'is-segera': slaSegera.value,
    'is-panjang': !!diperpanjang.value,
}));

const judulSla = computed(() =>
    diperpanjang.value
        ? `Tenggat asli ${formatTanggal(props.mpp.sla?.batasAwal)}, diperpanjang ${diperpanjang.value}x menjadi ${formatTanggal(slaBatas.value)}.`
        : `Tenggat SLA: ${formatTanggal(slaBatas.value)}`,
);


/* Tombol Perpanjang ADA SELAMA MPP-nya PUNYA TENGGAT.
 *
 * Dulu di sini ada syarat tambahan "hanya bila sudah lewat atau tinggal ≤7
 * hari". Niatnya menjaga perpanjangan tetap terasa sebagai pengecualian —
 * tapi akibatnya tombolnya tidak pernah terlihat pada MPP yang tenggatnya
 * masih sebulan lagi, dan admin yang memang perlu memperpanjang lebih awal
 * tidak punya jalan sama sekali. Menyembunyikan tombol bukan cara menegakkan
 * kebijakan; yang menegakkannya adalah alasan tertulis yang dituntut modalnya.
 *
 * Yang tersisa cuma syarat yang memang membuat tombolnya mustahil berguna:
 *   MT             tidak terikat SLA — tidak ada tenggat untuk digeser.
 *   dibatalkan     bukan MPP berjalan.
 *   selesai        pekerjaannya sudah tuntas.
 *   tanpa sla.hari lahir sebelum SLA dicatat; tak ada angka untuk menambah.
 *
 * Sisanya — penuh/tidak, jatah habis/belum — diputuskan server dan dijelaskan
 * di dalam modal. Lebih baik tombolnya terlihat lalu modalnya menerangkan
 * kenapa belum bisa, daripada tombol yang hilang tanpa sebab yang bisa dibaca.
 */
const bolehPerpanjang = computed(
    () => props.mpp.jenisProgram !== 'MT'
        && props.mpp.status === 'AKTIF'
        && !props.mpp.selesai
        && !!props.mpp.sla?.hari,
);

const judulPeriode = computed(() =>
    adaRentang.value
        ? `Periode target: ${rentangPendek(props.mpp.sla.mulai, props.mpp.tanggalPeriode)} (tenggat ${formatTanggal(props.mpp.tanggalPeriode)})`
        : 'Tenggat penyelesaian MPP',
);

const allTags = computed(() => {
    const list = [];
    if (props.mpp.employmentType?.nama) {
        list.push({ key: 'emp', icon: 'bi-briefcase', label: props.mpp.employmentType.nama, title: 'Tipe Kerja: ' + props.mpp.employmentType.nama });
    }
    if (props.mpp.workplaceType?.nama) {
        list.push({ key: 'wp', icon: 'bi-laptop', label: props.mpp.workplaceType.nama, title: 'Lokasi Kerja: ' + props.mpp.workplaceType.nama });
    }
    if (props.mpp.experienceLevel?.nama) {
        list.push({ key: 'exp', icon: 'bi-stars', label: props.mpp.experienceLevel.nama, title: 'Tingkat Pengalaman: ' + props.mpp.experienceLevel.nama });
    }
    if (props.mpp.level?.nama) {
        list.push({ key: 'lvl', icon: 'bi-bar-chart-steps', label: props.mpp.level.nama, title: 'Level HRIS: ' + props.mpp.level.nama });
    }
    if (props.mpp.lokasi?.nama) {
        list.push({ key: 'loc', icon: 'bi-geo-alt', label: props.mpp.lokasi.nama, title: 'Lokasi Penempatan: ' + props.mpp.lokasi.nama });
    }
    return list;
});
</script>

<style scoped>
.mmp-card {
    display: flex;
    flex-direction: column;
    gap: 0.6rem;
    background: #ffffff;
    border: 1px solid var(--line, #e2e8f0);
    border-top: 2.5px solid transparent;
    border-radius: 0.9rem;
    padding: 1rem 1.1rem;
    cursor: pointer;
    position: relative;
    overflow: visible;
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
    box-shadow: 0 2px 8px rgba(15, 23, 42, 0.03);
}

.mmp-card:hover,
.mmp-card:focus-visible {
    transform: translateY(-2px);
    box-shadow: 0 12px 28px rgba(99, 102, 241, 0.12);
    border-color: #c7d2fe;
    border-top-color: #6366f1;
    outline: none;
}

.mmp-card.is-mt:hover,
.mmp-card.is-mt:focus-visible {
    border-color: #ddd6fe;
    border-top-color: #8b5cf6;
    box-shadow: 0 12px 28px rgba(139, 92, 246, 0.12);
}

.mmp-card.is-off {
    opacity: 0.75;
    background: #f8fafc;
}

/* 1. Head */
.mmp-card__head {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
}

.mmp-card__title-row {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 0.5rem;
}

.mmp-card__title {
    margin: 0;
    font-size: 1rem;
    font-weight: 800;
    line-height: 1.3;
    color: #0f172a;
    letter-spacing: -0.01em;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    text-overflow: ellipsis;
    word-break: break-word;
}

.mmp-card__sub-row {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.35rem;
}

.mmp-card__no {
    font-family: 'JetBrains Mono', monospace;
    font-size: 0.72rem;
    font-weight: 700;
    color: #64748b;
    background: #f1f5f9;
    padding: 0.15rem 0.45rem;
    border-radius: 0.35rem;
    border: 1px solid #e2e8f0;
    display: inline-flex;
    align-items: center;
    gap: 0.15rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 140px;
}

.mmp-card__prog {
    font-size: 0.68rem;
    font-weight: 800;
    color: #4338ca;
    background: #eef2ff;
    border: 1px solid #c7d2fe;
    padding: 0.15rem 0.45rem;
    border-radius: 0.35rem;
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 140px;
}

.mmp-card__prog.is-mt {
    color: #6d28d9;
    background: #f5f3ff;
    border-color: #ddd6fe;
}

.mmp-status-flag {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    font-size: 0.7rem;
    font-weight: 800;
    color: #64748b;
    background: #f1f5f9;
    padding: 0.15rem 0.45rem;
    border-radius: 999px;
    margin-left: auto;
}

.mmp-status-flag__dot {
    width: 0.4rem;
    height: 0.4rem;
    border-radius: 50%;
    background: #94a3b8;
}

.mmp-status-flag.is-done {
    color: #15803d;
    background: #dcfce7;
}

.mmp-status-flag.is-done .mmp-status-flag__dot {
    background: #22c55e;
}

/* ── Lencana SLA ────────────────────────────────────────────────────────────
 *
 * Tiga warna untuk tiga tingkat mendesak, dan urutannya disengaja: merah untuk
 * yang sudah lewat, kuning untuk yang hampir, ungu untuk yang sudah pernah
 * diperpanjang. Yang terakhir sengaja TIDAK merah — ia bukan masalah yang
 * menuntut tindakan, melainkan keterangan tentang bagaimana tenggat ini sampai
 * di tanggalnya sekarang.
 */
.mmp-sla-tag {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.15rem 0.45rem;
    border-radius: 999px;
    font-size: 0.68rem;
    font-weight: 800;
    white-space: nowrap;
}

.mmp-sla-tag.is-lewat {
    color: #b91c1c;
    background: #fee2e2;
}

.mmp-sla-tag.is-segera {
    color: #b45309;
    background: #fef3c7;
}

.mmp-sla-tag.is-panjang {
    color: #6d28d9;
    background: #ede9fe;
}

.mmp-sla-tag.is-aman {
    color: #475569;
    background: #f1f5f9;
}

/* ── Bilah SLA ──────────────────────────────────────────────────────────────
 *
 * Netral secara bawaan (abu), berubah warna hanya saat memang perlu dilihat.
 * Kartu yang seluruh MPP-nya berwarna sama dengan yang mendesak membuat warna
 * berhenti berarti apa pun.
 */
.mmp-slabar {
    margin-top: 0.55rem;
    padding: 0.5rem 0.6rem;
    border: 1px solid #e2e8f0;
    border-radius: 0.6rem;
    background: #f8fafc;
}

.mmp-slabar__top {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    gap: 0.5rem;
}

.mmp-slabar__ket {
    display: inline-flex;
    align-items: center;
    gap: 0.28rem;
    font-size: 0.68rem;
    font-weight: 800;
    color: #475569;
}

.mmp-slabar__tgl {
    font-size: 0.7rem;
    font-weight: 800;
    color: #0f172a;
    white-space: nowrap;
}

/* Tenggat asli dicoret dan dikecilkan — ia keterangan, bukan yang berlaku. */
.mmp-slabar__tgl s { margin-right: 0.2rem; font-weight: 700; font-size: 0.64rem; color: #94a3b8; }

.mmp-slabar__rel {
    height: 0.3rem;
    margin: 0.4rem 0 0.35rem;
    border-radius: 999px;
    background: #e2e8f0;
    overflow: hidden;
}

.mmp-slabar__rel > span {
    display: block;
    height: 100%;
    border-radius: inherit;
    background: #64748b;
    transition: width 0.3s ease;
}

.mmp-slabar__kaki {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    font-size: 0.68rem;
    font-weight: 700;
    color: #64748b;
}

/* Tombol perpanjang di dalam bilahnya sendiri — di sinilah orang berada saat
   pertanyaannya muncul, jadi jawabannya tidak perlu dicari di tempat lain. */
.mmp-slabar__btn {
    flex: none;
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.18rem 0.45rem;
    border: 1px solid rgba(124, 58, 237, 0.3);
    border-radius: 0.4rem;
    font-size: 0.66rem;
    font-weight: 800;
    color: #7c3aed;
    background: rgba(124, 58, 237, 0.08);
    cursor: pointer;
    transition: background 0.15s ease, color 0.15s ease;
}

.mmp-slabar__btn:hover { color: #fff; background: #7c3aed; border-color: #7c3aed; }

.mmp-slabar.is-segera { border-color: #fde68a; background: #fffbeb; }
.mmp-slabar.is-segera .mmp-slabar__rel > span { background: #f59e0b; }
.mmp-slabar.is-segera .mmp-slabar__kaki { color: #b45309; }

.mmp-slabar.is-lewat { border-color: #fecaca; background: #fef2f2; }
.mmp-slabar.is-lewat .mmp-slabar__rel > span { background: #dc2626; }
.mmp-slabar.is-lewat .mmp-slabar__kaki { color: #b91c1c; }

.mmp-slabar.is-panjang { border-color: #ddd6fe; background: #faf9ff; }
.mmp-slabar.is-panjang .mmp-slabar__rel > span { background: #7c3aed; }

/* Tombol perpanjang: dibedakan dari Ubah/Selesai/Batalkan yang selalu ada.
   Ia muncul hanya saat memang dibutuhkan, jadi ia harus terlihat sebagai
   sesuatu yang BARU muncul — bukan ikon keempat yang seragam. */
.mmp-iconbtn--sla {
    color: #7c3aed;
    border-color: rgba(124, 58, 237, 0.35);
    background: rgba(124, 58, 237, 0.08);
}

.mmp-iconbtn--sla:hover {
    color: #fff;
    background: #7c3aed;
    border-color: #7c3aed;
}

/* 2. Body */
.mmp-card__body {
    display: flex;
    flex-direction: column;
    gap: 0.45rem;
}

.mmp-card__dept {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.25rem 0.35rem;
    font-size: 0.78rem;
    font-weight: 700;
    color: #475569;
    min-width: 0;
}

.mmp-card__dept i {
    color: #6366f1;
    flex-shrink: 0;
}

.mmp-card__div-name {
    color: #1e293b;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 160px;
}

.mmp-card__sub-name {
    color: #94a3b8;
    font-weight: 600;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 160px;
}

.mmp-card__meta-bar {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.4rem 0.8rem;
    padding: 0.45rem 0.65rem;
    background: #f8fafc;
    border: 1px solid #f1f5f9;
    border-radius: 0.55rem;
}

.mmp-meta-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    font-size: 0.75rem;
    font-weight: 700;
    color: #334155;
}

.mmp-meta-pill i {
    color: #6366f1;
    font-size: 0.8rem;
}

.mmp-meta-pill strong {
    color: #0f172a;
    font-weight: 900;
}

.mmp-card__tags {
    display: flex;
    flex-wrap: wrap;
    gap: 0.3rem;
}

.mmp-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    font-size: 0.7rem;
    font-weight: 700;
    color: #475569;
    background: #f1f5f9;
    border: 1px solid #e2e8f0;
    padding: 0.18rem 0.45rem;
    border-radius: 0.35rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 160px;
}

.mmp-chip i {
    font-size: 0.72rem;
    color: #6366f1;
    flex-shrink: 0;
}

.mmp-chip--more {
    color: #4338ca;
    background: #eef2ff;
    border-color: #c7d2fe;
    font-weight: 800;
}

/* Hover Popover untuk +N Tag */
.mmp-more-wrap {
    position: relative;
    display: inline-flex;
}

.mmp-more-wrap .mmp-chip--more {
    cursor: pointer;
    transition: all 0.15s ease;
}

.mmp-more-wrap:hover .mmp-chip--more {
    background: #6366f1;
    color: #ffffff;
    border-color: #6366f1;
}

.mmp-more-popover {
    position: absolute;
    bottom: calc(100% + 7px);
    left: 50%;
    transform: translateX(-50%) translateY(4px);
    background: #0f172a;
    color: #ffffff;
    border-radius: 0.65rem;
    padding: 0.55rem 0.75rem;
    box-shadow: 0 12px 28px rgba(15, 23, 42, 0.28);
    white-space: nowrap;
    opacity: 0;
    visibility: hidden;
    pointer-events: none;
    transition: opacity 0.18s cubic-bezier(0.16, 1, 0.3, 1), transform 0.18s cubic-bezier(0.16, 1, 0.3, 1), visibility 0.18s ease;
    z-index: 99;
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
}

.mmp-more-popover::after {
    content: '';
    position: absolute;
    top: 100%;
    left: 50%;
    transform: translateX(-50%);
    border-width: 5px;
    border-style: solid;
    border-color: #0f172a transparent transparent transparent;
}

.mmp-more-wrap:hover .mmp-more-popover {
    opacity: 1;
    visibility: visible;
    pointer-events: auto;
    transform: translateX(-50%) translateY(0);
}

.mmp-more-popover__head {
    font-size: 0.64rem;
    font-weight: 800;
    color: #94a3b8;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    padding-bottom: 0.25rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.12);
}

.mmp-more-popover__item {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.74rem;
    font-weight: 700;
    color: #f1f5f9;
}

.mmp-more-popover__item i {
    color: #a5b4fc;
    font-size: 0.8rem;
}

/* 3. Footer */
.mmp-card__foot {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    padding-top: 0.5rem;
    border-top: 1px solid #f1f5f9;
}

.mmp-card__pj {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    min-width: 0;
    flex: 1;
}

.mmp-card__pj-name {
    font-size: 0.76rem;
    font-weight: 800;
    color: #334155;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 150px;
}

.mmp-card__actions {
    display: flex;
    align-items: center;
    gap: 0.3rem;
    flex-shrink: 0;
}

.mmp-card__actions .wca-iconbtn--success:hover:not(:disabled) {
    color: #059669;
    border-color: rgba(16, 185, 129, 0.35);
    background: #ecfdf5;
}
</style>
