<!-- WEB CAREER — AGENDA SELEKSI: konfirmasi kehadiran dari sisi tim.

     PERLU TINDAKAN  permintaan jadwal lain (urut tenggat tim), belum menjawab
                     (daftar PENGINGAT MANUAL — pilih banyak, kirim berselang),
                     menyatakan mundur, email gagal, dan jadwal yang sudah
                     dimulai tetapi kehadirannya belum dicatat. Batas konfirmasi
                     = waktu mulai jadwal, jadi "lewat batas" = "sudah dimulai".
     AGENDA          sesi per hari: siapa akan hadir, siapa belum menjawab.

     Seluruh daftar dibaca dari snapshot CRM (satu baris per undangan) — tanpa
     join ke tabel lamaran yang sibuk. Rincian & tindakan per kandidat memakai
     KonfirmasiPanel, panel yang sama dengan drawer worklist. -->
<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import KonfirmasiPanel from '@career/KonfirmasiPanel.vue';
import {
    galatDari,
    kirimPengingat,
    muatAgenda,
    muatPerluTindakan,
    sejak,
    sisaWaktu,
} from '@utils/career/konfirmasi';
import { hariIni } from '@utils/tanggalLokal';

const props = defineProps({
    siap: { type: Boolean, default: false },
    hak: { type: Object, default: () => ({ ubah: false }) },
    master: { type: Array, default: () => [] },
    aturan: { type: Object, default: () => ({ jedaKirimMenit: 10, jarakSopanJam: 16, pengingatMaks: 300 }) },
});

const tab = ref('TINDAKAN');
const cari = ref('');
const programPilih = ref('');
const sekarang = ref(new Date());

// ── PERLU TINDAKAN ──────────────────────────────────────────────────────────

const KOSONG = { permintaan: [], belumJawab: [], mundur: [], gagalKirim: [], belumHadir: [], ditunda: [] };
const data = ref({ ...KOSONG });
const memuat = ref(false);
const galat = ref('');
let token = 0;

async function muat({ diam = false } = {}) {
    const t = ++token;
    if (!diam) memuat.value = true;
    galat.value = '';
    try {
        const r = await muatPerluTindakan({ q: cari.value.trim() || undefined });
        if (t !== token) return;
        data.value = { ...KOSONG, ...r };
        sekarang.value = new Date();
        // Pilihan yang sudah tak ada di daftar dibuang diam-diam.
        const ada = new Set(data.value.belumJawab.map((x) => x.id));
        pilih.value = pilih.value.filter((id) => ada.has(id));
    } catch (e) {
        if (t !== token) return;
        galat.value = galatDari(e, 'Daftar gagal dimuat.').pesan;
    } finally {
        if (t === token) memuat.value = false;
    }
}

const program = computed(() => {
    const s = new Set();
    Object.values(data.value).forEach((v) => Array.isArray(v) && v.forEach((x) => x.program && s.add(x.program)));

    return [...s].sort();
});

const saring = (rows) => (programPilih.value ? rows.filter((x) => x.program === programPilih.value) : rows);

const KELOMPOK = [
    { kunci: 'permintaan', judul: 'Minta jadwal lain', ikon: 'bi-arrow-repeat', nada: 'kuning', kosong: 'Tidak ada permintaan yang menunggu.' },
    { kunci: 'belumJawab', judul: 'Belum menjawab (jadwal belum mulai)', ikon: 'bi-hourglass-split', nada: 'abu', kosong: 'Semua kandidat sudah menjawab.' },
    { kunci: 'mundur', judul: 'Menyatakan mundur', ikon: 'bi-x-circle-fill', nada: 'merah', kosong: 'Tidak ada.' },
    { kunci: 'gagalKirim', judul: 'Email gagal terkirim', ikon: 'bi-envelope-exclamation-fill', nada: 'merah', kosong: 'Tidak ada.' },
    { kunci: 'belumHadir', judul: 'Jadwal sudah mulai — catat kehadiran', ikon: 'bi-person-check', nada: 'ungu', kosong: 'Tidak ada.' },
    // Jadwal dikosongkan tim, penggantinya belum terbit — urut perkiraan tanggal.
    { kunci: 'ditunda', judul: 'Ditunda — perlu jadwal pengganti', ikon: 'bi-pause-circle-fill', nada: 'violet', kosong: 'Tidak ada jadwal yang ditunda.' },
];

const kelompok = computed(() => KELOMPOK.map((k) => ({ ...k, rows: saring(data.value[k.kunci] || []) })));
const totalTindakan = computed(() => kelompok.value.reduce((n, k) => n + k.rows.length, 0));
const bukaKelompok = reactive({ permintaan: true, belumJawab: true, mundur: true, gagalKirim: false, belumHadir: true, ditunda: true });

// ── PILIH BANYAK: pengingat manual ──────────────────────────────────────────

const pilih = ref([]);
const bisaIngat = computed(() => saring(data.value.belumJawab).filter((x) => x.bolehIngat));

function tglPilih(id) {
    const i = pilih.value.indexOf(id);
    if (i >= 0) pilih.value.splice(i, 1);
    else pilih.value.push(id);
}

function pilihSemua(rows, syarat) {
    const ids = rows.filter(syarat).map((x) => x.id);
    const semua = ids.length && ids.every((id) => pilih.value.includes(id));
    pilih.value = semua ? pilih.value.filter((id) => !ids.includes(id)) : [...new Set([...pilih.value, ...ids])];
}

const terpilihIngat = computed(() => bisaIngat.value.filter((x) => pilih.value.includes(x.id)));
const terpilihBaruDikirim = computed(() => terpilihIngat.value.filter((x) => x.baruDikirim));

const dlgIngat = ref(false);
const sibuk = ref(false);
const hasilBanyak = ref(null);

async function kirimIngatBanyak() {
    if (sibuk.value || !terpilihIngat.value.length) return;
    sibuk.value = true;
    try {
        const res = await kirimPengingat(terpilihIngat.value.map((x) => x.id));
        hasilBanyak.value = { judul: 'Pengingat manual', pesan: res.message, gagal: res.result?.gagal || [], berhasil: res.result?.berhasil || [] };
        dlgIngat.value = false;
        pilih.value = [];
        beritahu(res.message || 'Pengingat diantrekan.');
        await muat({ diam: true });
    } catch (e) {
        beritahu(galatDari(e, 'Pengingat gagal dikirim.').pesan, true);
    } finally {
        sibuk.value = false;
    }
}

// ── AGENDA PER HARI ─────────────────────────────────────────────────────────

const rentang = ref([hariIni(), hariIni(new Date(Date.now() + 6 * 86400000))]);
const agenda = ref({ hari: [] });
const memuatAgenda = ref(false);
const galatAgenda = ref('');
const sesiBuka = reactive({});

async function muatHari() {
    if (!rentang.value || rentang.value.length !== 2) return;
    memuatAgenda.value = true;
    galatAgenda.value = '';
    try {
        agenda.value = await muatAgenda({ dari: rentang.value[0], sampai: rentang.value[1] });
        sekarang.value = new Date();
    } catch (e) {
        galatAgenda.value = galatDari(e, 'Agenda gagal dimuat.').pesan;
    } finally {
        memuatAgenda.value = false;
    }
}

function gantiTab(t) {
    tab.value = t;
    if (t === 'AGENDA' && !agenda.value.hari.length) muatHari();
}

const STATUS_URUT = ['AKAN_HADIR', 'MENUNGGU', 'JADWAL_LAIN', 'TANPA_JAWABAN', 'MUNDUR'];
const masterPeta = computed(() => Object.fromEntries((props.master || []).map((m) => [m.kode, m])));

function hitungan(h) {
    return STATUS_URUT.filter((k) => h?.[k]).map((k) => ({ kode: k, n: h[k], label: masterPeta.value[k]?.label || k, warna: masterPeta.value[k]?.warna || '#94a3b8', ikon: masterPeta.value[k]?.ikon || 'bi-circle' }));
}

// ── DRAWER RINCIAN ──────────────────────────────────────────────────────────

const drawer = reactive({ show: false, baris: null });

function bukaRincian(x) {
    drawer.baris = x;
    drawer.show = true;
}

async function sesudahUbah() {
    await muat({ diam: true });
    if (tab.value === 'AGENDA') muatHari();
}

// ── PESAN ───────────────────────────────────────────────────────────────────

const toast = ref('');
const toastErr = ref(false);
let tmToast = null;

function beritahu(teks, err = false) {
    toast.value = teks;
    toastErr.value = err;
    clearTimeout(tmToast);
    tmToast = setTimeout(() => { toast.value = ''; }, err ? 7000 : 4500);
}

let tmCari = null;
function ketikCari() {
    clearTimeout(tmCari);
    tmCari = setTimeout(() => muat(), 350);
}

onMounted(() => {
    if (props.siap) muat();
    setInterval(() => { sekarang.value = new Date(); }, 60000);
});

const JENIS_KIRIM = { UNDANGAN: 'undangan', PENGINGAT: 'pengingat', KIRIM_ULANG: 'kirim ulang' };
const URL_WORKLIST = '/karir/pelamar';

/** Worklist tersaring ke kandidat ini — jendela Atur Jadwal ada di sana. */
function urlWorklistKandidat(x) {
    return `${URL_WORKLIST}?q=${encodeURIComponent(x?.kode || x?.nama || x?.program || '')}`;
}

function pindahKeWorklist(x) {
    window.location.href = urlWorklistKandidat(x);
}
</script>

<template>
    <Head title="Agenda Seleksi" />
    <div class="wca ags">
        <!-- ═══ KEPALA ═══ -->
        <header class="ags-hero">
            <div class="ags-hero__main">
                <span class="ags-hero__ic" aria-hidden="true"><i class="bi bi-calendar2-week-fill"></i></span>
                <div>
                    <h1>Agenda Seleksi</h1>
                    <p>Konfirmasi kehadiran kandidat: siapa akan hadir, siapa minta jadwal lain, siapa belum menjawab — dan pengingat manualnya.</p>
                </div>
            </div>
            <button type="button" class="wca-btn wca-btn--ghost" :disabled="memuat || memuatAgenda" @click="tab === 'AGENDA' ? muatHari() : muat()">
                <i class="bi bi-arrow-clockwise" :class="{ 'ags-putar': memuat || memuatAgenda }"></i><span>Muat ulang</span>
            </button>
        </header>

        <div v-if="!siap" class="ags-kosong">
            <i class="bi bi-database-exclamation"></i>
            <b>Fitur konfirmasi kehadiran belum aktif di basis data ini.</b>
            <span>Jalankan skrip docs/01-10-2026/01-konfirmasi-kehadiran.sql, lalu <code>php artisan cache:clear file</code>.</span>
        </div>

        <template v-else>
            <!-- ═══ TAB & SARINGAN ═══ -->
            <section class="ags-bilah">
                <div class="wca-segt wca-segt--sm" role="tablist">
                    <button type="button" role="tab" class="wca-segt__it" :class="{ on: tab === 'TINDAKAN' }" :aria-selected="tab === 'TINDAKAN'" @click="gantiTab('TINDAKAN')">
                        <i class="bi bi-lightning-charge-fill"></i> Perlu tindakan
                        <span v-if="totalTindakan" class="ags-n">{{ totalTindakan }}</span>
                    </button>
                    <button type="button" role="tab" class="wca-segt__it" :class="{ on: tab === 'AGENDA' }" :aria-selected="tab === 'AGENDA'" @click="gantiTab('AGENDA')">
                        <i class="bi bi-calendar3"></i> Agenda per hari
                    </button>
                </div>
                <div class="ags-saring">
                    <el-input v-if="tab === 'TINDAKAN'" v-model="cari" clearable placeholder="Cari nama, email, aktivitas…" class="ags-cari" @input="ketikCari" @clear="muat()">
                        <template #prefix><i class="bi bi-search"></i></template>
                    </el-input>
                    <el-select v-if="tab === 'TINDAKAN' && program.length > 1" v-model="programPilih" clearable placeholder="Semua program" class="ags-prog">
                        <el-option v-for="p in program" :key="p" :value="p" :label="p" />
                    </el-select>
                    <el-date-picker
                        v-if="tab === 'AGENDA'"
                        v-model="rentang" type="daterange" unlink-panels
                        value-format="YYYY-MM-DD" format="DD MMM YYYY"
                        start-placeholder="Dari" end-placeholder="Sampai" range-separator="→"
                        class="ags-rentang"
                        @change="muatHari"
                    />
                </div>
            </section>

            <!-- ═══ PERLU TINDAKAN ═══ -->
            <template v-if="tab === 'TINDAKAN'">
                <p v-if="galat" class="ags-galat"><i class="bi bi-exclamation-triangle-fill"></i> {{ galat }}</p>

                <!-- Bilah pilih-banyak -->
                <div v-if="hak.ubah && pilih.length" class="ags-pilihbar" role="region" aria-label="Tindakan untuk kandidat terpilih">
                    <span><b>{{ pilih.length }}</b> kandidat dipilih</span>
                    <button type="button" class="wca-btn wca-btn--dark" :disabled="!terpilihIngat.length" @click="dlgIngat = true">
                        <i class="bi bi-bell-fill"></i> Kirim pengingat ({{ terpilihIngat.length }})
                    </button>
                    <button type="button" class="ags-link" @click="pilih = []">Batalkan pilihan</button>
                </div>

                <section v-for="k in kelompok" :key="k.kunci" class="ags-grup" :class="`is-${k.nada}`">
                    <button type="button" class="ags-grup__kepala" :aria-expanded="!!bukaKelompok[k.kunci]" @click="bukaKelompok[k.kunci] = !bukaKelompok[k.kunci]">
                        <span class="ags-grup__ic"><i class="bi" :class="k.ikon"></i></span>
                        <b>{{ k.judul }}</b>
                        <span class="ags-grup__n">{{ k.rows.length }}</span>
                        <i class="bi ags-grup__caret" :class="bukaKelompok[k.kunci] ? 'bi-chevron-up' : 'bi-chevron-down'"></i>
                    </button>

                    <div v-if="bukaKelompok[k.kunci]" class="ags-grup__isi">
                        <p v-if="memuat && !k.rows.length" class="ags-pelan">Memuat…</p>
                        <p v-else-if="!k.rows.length" class="ags-pelan">{{ k.kosong }}</p>

                        <!-- Daftar pengingat manual: pilih-semua yang boleh diingatkan -->
                        <div v-if="k.kunci === 'belumJawab' && hak.ubah && k.rows.length" class="ags-grup__alat">
                            <button type="button" class="ags-link" @click="pilihSemua(k.rows, (x) => x.bolehIngat)">
                                <i class="bi bi-check2-square"></i> Pilih semua yang bisa diingatkan ({{ k.rows.filter((x) => x.bolehIngat).length }})
                            </button>
                            <span class="ags-pelan">Email dikirim berselang beberapa detik lewat antrean <code>wc-konfirmasimail</code>; jeda minimal {{ aturan.jedaKirimMenit }} menit per kandidat.</span>
                        </div>
                        <ul v-if="k.rows.length" class="ags-daftar">
                            <li v-for="x in k.rows" :key="k.kunci + x.id" class="ags-baris" :class="{ 'is-pilih': pilih.includes(x.id) }">
                                <label v-if="hak.ubah && k.kunci === 'belumJawab'" class="ags-cek" :title="!x.bolehIngat ? `Tunggu ${x.tungguMenit} menit lagi` : ''">
                                    <input
                                        type="checkbox"
                                        :checked="pilih.includes(x.id)"
                                        :disabled="!x.bolehIngat"
                                        :aria-label="`Pilih ${x.nama}`"
                                        @change="tglPilih(x.id)"
                                    />
                                </label>

                                <div class="ags-baris__orang">
                                    <b>{{ x.nama }}</b>
                                    <small>{{ x.email }}</small>
                                    <small class="ags-pelan">{{ [x.posisi || x.program, x.kode].filter(Boolean).join(' · ') }}</small>
                                </div>

                                <div class="ags-baris__jadwal">
                                    <span class="ags-chip" :style="{ '--nada': x.warna || '#94a3b8' }"><i class="bi" :class="x.ikon"></i> {{ x.statusLabel }}</span>
                                    <b>{{ x.aktivitas }}</b>
                                    <small v-if="x.mulaiTeks"><i class="bi bi-calendar-event"></i> {{ x.mulaiTeks }}</small>
                                    <small v-else-if="x.tunda?.jadwalSemula" class="ags-pelan"><i class="bi bi-calendar-x"></i> semula <s>{{ x.tunda.jadwalSemula }}</s></small>
                                    <small v-if="k.kunci === 'belumJawab'">
                                        <i class="bi bi-alarm"></i> bisa dijawab sampai jadwal mulai · {{ sisaWaktu(x.batas, sekarang) }}
                                    </small>
                                </div>

                                <!-- Permintaan jadwal lain: alasan, usulan, tenggat tim -->
                                <div v-if="x.permintaan" class="ags-baris__minta">
                                    <span class="ags-sla" :class="`is-${x.permintaan.slaNada}`"><i class="bi bi-stopwatch"></i> tenggat {{ x.permintaan.slaTeks }}</span>
                                    <small><b>{{ x.permintaan.alasan }}</b><template v-if="x.permintaan.catatan"> — “{{ x.permintaan.catatan }}”</template></small>
                                    <small v-for="(u, i) in x.permintaan.usulan" :key="i" class="ags-pelan">{{ i + 1 }}. {{ u.teks }}</small>
                                </div>

                                <!-- Ditunda: perkiraan pengganti + alasan -->
                                <div v-else-if="k.kunci === 'ditunda'" class="ags-baris__minta">
                                    <span class="ags-tunda" :class="{ 'is-belum': !x.tunda?.perkiraanTeks }">
                                        <i class="bi bi-calendar-event"></i>
                                        {{ x.tunda?.perkiraanTeks ? `perkiraan ${x.tunda.perkiraanTeks}` : 'pengganti belum ditetapkan' }}
                                    </span>
                                    <small v-if="x.tunda?.alasanTeks || x.tunda?.alasan"><b>{{ x.tunda.alasanTeks || x.tunda.alasan }}</b></small>
                                    <small v-if="x.tunda?.ditundaAt" class="ags-pelan">
                                        ditunda {{ sejak(x.tunda.ditundaAt, sekarang) }}<template v-if="x.tunda.ditundaOleh"> · {{ x.tunda.ditundaOleh }}</template>
                                    </small>
                                </div>

                                <!-- Hitungan CRM -->
                                <div v-else class="ags-baris__crm">
                                    <small><i class="bi bi-bell"></i> pengingat <b>{{ x.jumlahPengingat }}×</b> · manual <b>{{ x.jumlahKirimManual }}×</b><template v-if="x.jumlahGagal"> · <span class="ags-merah">gagal {{ x.jumlahGagal }}×</span></template></small>
                                    <small v-if="x.terakhirKirimAt" class="ags-pelan">
                                        terakhir {{ JENIS_KIRIM[x.terakhirKirimJenis] || x.terakhirKirimJenis }} {{ sejak(x.terakhirKirimAt, sekarang) }}<template v-if="x.terakhirKirimOleh"> · {{ x.terakhirKirimOleh }}</template>
                                    </small>
                                    <small v-if="k.kunci === 'belumJawab' && x.baruDikirim" class="ags-oranye"><i class="bi bi-envelope-check"></i> baru menerima email</small>
                                </div>

                                <div class="ags-baris__aksi">
                                    <button type="button" class="wca-btn wca-btn--ghost ags-kecil" @click="bukaRincian(x)">
                                        <i class="bi" :class="k.kunci === 'permintaan' && hak.ubah ? 'bi-gear-fill' : 'bi-layout-sidebar-reverse'"></i>
                                        {{ k.kunci === 'permintaan' && hak.ubah ? 'Proses' : 'Rincian' }}
                                    </button>
                                    <a v-if="k.kunci === 'mundur' || k.kunci === 'belumHadir'" :href="`${URL_WORKLIST}?q=${encodeURIComponent(x.program || '')}`" class="ags-link">
                                        <i class="bi bi-kanban"></i> Worklist
                                    </a>
                                    <!-- Jadwal pengganti diatur dari jendela Atur Jadwal di worklist. -->
                                    <a v-if="k.kunci === 'ditunda'" :href="urlWorklistKandidat(x)" class="ags-link">
                                        <i class="bi bi-calendar-plus"></i> Atur jadwal pengganti
                                    </a>
                                </div>
                            </li>
                        </ul>
                    </div>
                </section>
            </template>

            <!-- ═══ AGENDA PER HARI ═══ -->
            <template v-else>
                <p v-if="galatAgenda" class="ags-galat"><i class="bi bi-exclamation-triangle-fill"></i> {{ galatAgenda }}</p>
                <p v-if="memuatAgenda && !agenda.hari.length" class="ags-pelan">Memuat agenda…</p>
                <p v-else-if="!agenda.hari.length" class="ags-kosong-kecil">Tidak ada jadwal ber-konfirmasi pada rentang ini.</p>

                <section v-for="h in agenda.hari" :key="h.tanggal" class="ags-hari">
                    <header class="ags-hari__kepala">
                        <b>{{ h.judul }}</b>
                        <span class="ags-pelan">{{ h.jumlah }} kandidat</span>
                        <span class="ags-hitung">
                            <span v-for="c in hitungan(h.hitung)" :key="c.kode" class="ags-chip" :style="{ '--nada': c.warna }"><i class="bi" :class="c.ikon"></i> {{ c.n }} {{ c.label.toLowerCase() }}</span>
                        </span>
                    </header>
                    <div v-for="s in h.sesi" :key="h.tanggal + s.jam + s.aktivitas" class="ags-sesi">
                        <button type="button" class="ags-sesi__kepala" :aria-expanded="!!sesiBuka[h.tanggal + s.jam + s.aktivitas]" @click="sesiBuka[h.tanggal + s.jam + s.aktivitas] = !sesiBuka[h.tanggal + s.jam + s.aktivitas]">
                            <span class="ags-sesi__jam">{{ s.jam }}</span>
                            <b>{{ s.aktivitas }}</b>
                            <span class="ags-pelan">{{ s.jumlah }} orang</span>
                            <span class="ags-hitung">
                                <span v-for="c in hitungan(s.hitung)" :key="c.kode" class="ags-titik" :style="{ '--nada': c.warna }" :title="c.label">{{ c.n }}</span>
                            </span>
                            <i class="bi" :class="sesiBuka[h.tanggal + s.jam + s.aktivitas] ? 'bi-chevron-up' : 'bi-chevron-down'"></i>
                        </button>
                        <ul v-if="sesiBuka[h.tanggal + s.jam + s.aktivitas]" class="ags-orang">
                            <li v-for="o in s.orang" :key="o.id">
                                <span class="ags-chip" :style="{ '--nada': o.warna || '#94a3b8' }"><i class="bi" :class="o.ikon"></i> {{ o.statusLabel }}</span>
                                <b>{{ o.nama }}</b>
                                <small class="ags-pelan">{{ o.posisi || o.program }}</small>
                                <small v-if="o.hadir" :class="o.hadir === 'Y' ? 'ags-hijau' : 'ags-merah'">
                                    <i class="bi" :class="o.hadir === 'Y' ? 'bi-person-check-fill' : 'bi-person-dash-fill'"></i> {{ o.hadir === 'Y' ? 'hadir' : 'tidak hadir' }}
                                </small>
                                <button type="button" class="ags-link" @click="bukaRincian(o)">Rincian</button>
                            </li>
                        </ul>
                    </div>
                </section>
            </template>
        </template>

        <!-- ═══ DRAWER RINCIAN ═══ -->
        <el-drawer v-model="drawer.show" :size="'min(520px, 100vw)'" :title="drawer.baris ? drawer.baris.nama : 'Rincian'" destroy-on-close>
            <div v-if="drawer.baris" class="ags-drawer">
                <p class="ags-drawer__sub">
                    <b>{{ drawer.baris.aktivitas }}</b><template v-if="drawer.baris.mulaiTeks"> · {{ drawer.baris.mulaiTeks }}</template><br />
                    <span class="ags-pelan">{{ [drawer.baris.posisi || drawer.baris.program, drawer.baris.tahap, drawer.baris.kode].filter(Boolean).join(' · ') }}</span>
                </p>
                <KonfirmasiPanel
                    :id-aktivitas="drawer.baris.id"
                    :boleh-ubah="hak.ubah"
                    @berubah="sesudahUbah"
                    @pesan="beritahu"
                    @atur-jadwal="pindahKeWorklist(drawer.baris)"
                />
            </div>
        </el-drawer>

        <!-- ═══ DIALOG PENGINGAT MASSAL ═══ -->
        <el-dialog v-model="dlgIngat" title="Kirim pengingat konfirmasi" width="min(520px, 94vw)">
            <p>Email <b>“mohon segera konfirmasi kehadiran”</b> dikirim ke <b>{{ terpilihIngat.length }}</b> kandidat, berselang beberapa detik. Tercatat sebagai pengingat manual atas nama Anda.</p>
            <p v-if="terpilihBaruDikirim.length" class="ags-catat is-kuning">
                <i class="bi bi-exclamation-triangle-fill"></i>
                {{ terpilihBaruDikirim.length }} kandidat baru menerima email kurang dari {{ aturan.jarakSopanJam }} jam lalu. Satu email sehari sudah cukup — pertimbangkan menelepon mereka.
            </p>
            <ul class="ags-ringkas">
                <li v-for="x in terpilihIngat.slice(0, 8)" :key="x.id">{{ x.nama }} — {{ x.aktivitas }}, batas {{ x.batasTeks }}</li>
                <li v-if="terpilihIngat.length > 8" class="ags-pelan">dan {{ terpilihIngat.length - 8 }} lainnya</li>
            </ul>
            <template #footer>
                <button type="button" class="wca-btn wca-btn--ghost" @click="dlgIngat = false">Batal</button>
                <button type="button" class="wca-btn wca-btn--dark" :disabled="sibuk" @click="kirimIngatBanyak"><i class="bi bi-send-fill"></i> Kirim {{ terpilihIngat.length }} pengingat</button>
            </template>
        </el-dialog>

        <!-- ═══ HASIL TINDAKAN BANYAK ═══ -->
        <el-dialog :model-value="!!hasilBanyak && hasilBanyak.gagal.length > 0" :title="hasilBanyak?.judul || ''" width="min(520px, 94vw)" @close="hasilBanyak = null">
            <p>{{ hasilBanyak?.pesan }}</p>
            <ul class="ags-ringkas">
                <li v-for="(g, i) in hasilBanyak?.gagal || []" :key="i"><b>{{ g.nama }}</b> — {{ g.alasan }}</li>
            </ul>
            <template #footer>
                <button type="button" class="wca-btn wca-btn--dark" @click="hasilBanyak = null">Mengerti</button>
            </template>
        </el-dialog>

        <div v-if="toast" class="ags-toast" :class="{ 'is-err': toastErr }" role="status">
            <i class="bi" :class="toastErr ? 'bi-exclamation-triangle-fill' : 'bi-check-circle-fill'"></i> {{ toast }}
        </div>
    </div>
</template>

<style scoped>
.ags {
    display: flex;
    flex-direction: column;
    gap: 14px;
    color: #0f172a;
}
.ags-hero {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 14px;
    flex-wrap: wrap;
    padding: 18px 20px;
    border-radius: 20px;
    border: 1px solid #e7e3fb;
    background:
        radial-gradient(circle at 92% -20%, rgba(139, 92, 246, 0.2), transparent 45%),
        linear-gradient(135deg, #ffffff, #f7f5ff);
}
.ags-hero__main {
    display: flex;
    gap: 14px;
    align-items: center;
    flex: 1 1 420px;
    min-width: 0;
}
.ags-hero__ic {
    flex: none;
    width: 52px;
    height: 52px;
    border-radius: 16px;
    display: grid;
    place-items: center;
    color: #fff;
    font-size: 1.4rem;
    background: linear-gradient(135deg, #8b5cf6, #6366f1);
    box-shadow: 0 12px 26px rgba(99, 102, 241, 0.32);
}
.ags-hero h1 {
    margin: 0;
    font-size: 1.35rem;
    font-weight: 900;
    color: #1e1b4b;
}
.ags-hero p {
    margin: 3px 0 0;
    font-size: 0.86rem;
    color: #64748b;
}
.ags-bilah {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}
.ags-saring {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}
.ags-cari {
    width: 280px;
}
.ags-prog {
    width: 220px;
}
.ags-rentang {
    max-width: 320px;
}
.ags-n {
    margin-left: 4px;
    min-width: 20px;
    padding: 0 6px;
    border-radius: 999px;
    background: #ef4444;
    color: #fff;
    font-size: 0.72rem;
    font-weight: 800;
}
.ags-pilihbar {
    position: sticky;
    top: 8px;
    z-index: 5;
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
    padding: 10px 14px;
    border-radius: 14px;
    background: #1e1b4b;
    color: #fff;
    box-shadow: 0 14px 30px -16px rgba(30, 27, 75, 0.8);
}
.ags-pilihbar .ags-link {
    color: #c7d2fe;
}
.ags-grup {
    border: 1px solid #e7e3fb;
    border-radius: 16px;
    background: #fff;
    overflow: hidden;
}
.ags-grup__kepala {
    width: 100%;
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px 14px;
    border: none;
    background: #fbfaff;
    font: inherit;
    cursor: pointer;
    text-align: left;
}
.ags-grup__ic {
    width: 30px;
    height: 30px;
    border-radius: 10px;
    display: grid;
    place-items: center;
    color: #fff;
    background: #94a3b8;
}
.ags-grup.is-kuning .ags-grup__ic { background: #d97706; }
.ags-grup.is-oranye .ags-grup__ic { background: #ea580c; }
.ags-grup.is-merah .ags-grup__ic { background: #dc2626; }
.ags-grup.is-ungu .ags-grup__ic { background: #6366f1; }
.ags-grup.is-violet .ags-grup__ic { background: #7c3aed; }
.ags-grup__n {
    padding: 1px 9px;
    border-radius: 999px;
    background: #eef2ff;
    color: #3730a3;
    font-weight: 800;
    font-size: 0.78rem;
}
.ags-grup__caret {
    margin-left: auto;
    color: #64748b;
}
.ags-grup__isi {
    padding: 4px 14px 14px;
}
.ags-grup__alat {
    display: flex;
    gap: 10px;
    align-items: center;
    flex-wrap: wrap;
    padding: 8px 0;
    font-size: 0.8rem;
}
.ags-daftar {
    list-style: none;
    margin: 0;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: 8px;
}
.ags-baris {
    display: grid;
    grid-template-columns: auto minmax(160px, 1.1fr) minmax(200px, 1.3fr) minmax(180px, 1.2fr) auto;
    gap: 10px 14px;
    align-items: start;
    padding: 10px 12px;
    border: 1px solid #eef0f7;
    border-radius: 12px;
    background: #fff;
}
.ags-baris.is-pilih {
    border-color: #818cf8;
    background: #f7f7ff;
}
.ags-baris > div {
    display: flex;
    flex-direction: column;
    gap: 2px;
    min-width: 0;
    font-size: 0.82rem;
}
.ags-baris small {
    font-size: 0.76rem;
    overflow-wrap: anywhere;
}
.ags-cek input {
    width: 17px;
    height: 17px;
    margin-top: 2px;
}
.ags-baris__aksi {
    flex-direction: row !important;
    gap: 8px !important;
    align-items: center;
    flex-wrap: wrap;
}
.ags-kecil {
    padding: 6px 10px !important;
    font-size: 0.78rem !important;
}
.ags-chip {
    align-self: flex-start;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 1px 8px;
    border-radius: 999px;
    font-size: 0.72rem;
    font-weight: 800;
    color: var(--nada, #64748b);
    background: color-mix(in srgb, var(--nada, #94a3b8) 12%, #fff);
}
.ags-sla {
    align-self: flex-start;
    display: inline-flex;
    gap: 4px;
    padding: 1px 8px;
    border-radius: 999px;
    font-size: 0.72rem;
    font-weight: 800;
}
.ags-sla.is-hijau { background: #dcfce7; color: #166534; }
.ags-sla.is-kuning { background: #fef3c7; color: #92400e; }
.ags-sla.is-merah { background: #fee2e2; color: #991b1b; }
/* Ditunda — ungu master status DITUNDA. */
.ags-tunda {
    align-self: flex-start;
    display: inline-flex;
    gap: 4px;
    padding: 1px 8px;
    border-radius: 999px;
    font-size: 0.72rem;
    font-weight: 800;
    background: #ede9fe;
    color: #5b21b6;
}
.ags-tunda.is-belum { background: #fff; color: #64748b; border: 1px dashed #c4b5fd; }
.ags-pelan { color: #64748b; }
.ags-oranye { color: #c2410c; font-weight: 700; }
.ags-merah { color: #b91c1c; font-weight: 700; }
.ags-hijau { color: #15803d; font-weight: 700; }
.ags-link {
    border: none;
    background: none;
    padding: 0;
    font: inherit;
    font-size: 0.8rem;
    font-weight: 800;
    color: #4f46e5;
    cursor: pointer;
    text-decoration: none;
    display: inline-flex;
    gap: 4px;
    align-items: center;
}
.ags-galat {
    color: #b91c1c;
    font-weight: 700;
    margin: 0;
}
.ags-kosong {
    display: flex;
    flex-direction: column;
    gap: 6px;
    align-items: center;
    text-align: center;
    padding: 40px 20px;
    border: 1px dashed #c7d2fe;
    border-radius: 16px;
    background: #fbfaff;
}
.ags-kosong i {
    font-size: 2rem;
    color: #6366f1;
}
.ags-kosong-kecil {
    padding: 24px;
    text-align: center;
    color: #64748b;
    border: 1px dashed #e2e8f0;
    border-radius: 14px;
}
.ags-hari {
    border: 1px solid #e7e3fb;
    border-radius: 16px;
    background: #fff;
    padding: 12px 14px;
    display: flex;
    flex-direction: column;
    gap: 8px;
}
.ags-hari__kepala {
    display: flex;
    gap: 10px;
    align-items: center;
    flex-wrap: wrap;
}
.ags-hitung {
    display: inline-flex;
    gap: 6px;
    flex-wrap: wrap;
}
.ags-sesi {
    border: 1px solid #eef0f7;
    border-radius: 12px;
}
.ags-sesi__kepala {
    width: 100%;
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 9px 12px;
    border: none;
    background: transparent;
    font: inherit;
    cursor: pointer;
    text-align: left;
    flex-wrap: wrap;
}
.ags-sesi__jam {
    font-weight: 900;
    color: #4f46e5;
    min-width: 46px;
}
.ags-titik {
    min-width: 22px;
    padding: 0 6px;
    border-radius: 999px;
    color: #fff;
    background: var(--nada);
    font-size: 0.72rem;
    font-weight: 800;
    text-align: center;
}
.ags-orang {
    list-style: none;
    margin: 0;
    padding: 4px 12px 10px;
    display: flex;
    flex-direction: column;
    gap: 6px;
}
.ags-orang li {
    display: flex;
    gap: 8px;
    align-items: center;
    flex-wrap: wrap;
    font-size: 0.82rem;
}
.ags-drawer {
    display: flex;
    flex-direction: column;
    gap: 12px;
}
.ags-drawer__sub {
    margin: 0;
    font-size: 0.85rem;
    line-height: 1.5;
}
.ags-catat {
    display: flex;
    gap: 8px;
    align-items: flex-start;
    padding: 8px 10px;
    border-radius: 10px;
    font-size: 0.82rem;
}
.ags-catat.is-kuning {
    background: #fffbeb;
    color: #92400e;
}
.ags-ringkas {
    margin: 8px 0 0;
    padding-left: 18px;
    font-size: 0.82rem;
    max-height: 240px;
    overflow: auto;
}
.ags-toast {
    position: fixed;
    right: 18px;
    bottom: 18px;
    z-index: 3000;
    max-width: min(460px, calc(100vw - 36px));
    padding: 11px 14px;
    border-radius: 12px;
    background: #065f46;
    color: #fff;
    font-weight: 700;
    font-size: 0.85rem;
    box-shadow: 0 16px 34px -18px rgba(0, 0, 0, 0.6);
    display: flex;
    gap: 8px;
}
.ags-toast.is-err {
    background: #991b1b;
}
.ags-putar {
    display: inline-block;
    animation: ags-putar 1s linear infinite;
}
@keyframes ags-putar {
    to {
        transform: rotate(360deg);
    }
}
@media (max-width: 980px) {
    .ags-baris {
        grid-template-columns: auto 1fr;
    }
    .ags-baris > div:not(.ags-baris__orang) {
        grid-column: 2;
    }
    .ags-cari,
    .ags-prog {
        width: 100%;
    }
}
</style>
