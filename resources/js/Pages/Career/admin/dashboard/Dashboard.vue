<!--
  WEB CAREER — DASHBOARD ADMIN (Redesigned & Fully Styled)
  Ber-tab per kategori (Rekrutmen / Magang / MT).
-->
<template>
    <Head title="Dashboard Admin" />

    <div class="wca wcd">
        <!-- ══════════════ KEPALA DOK / BANNER ══════════════ -->
        <header class="wcd-head">
            <div class="wcd-head__ambient" :style="{ '--aksen-bg': aksen }"></div>
            
            <div class="wcd-head__intro">
                <div class="wcd-head__avatar" :style="{ '--aksen': aksen }">
                    <i class="bi" :class="sapaanIkon"></i>
                </div>
                <div class="wcd-head__text">
                    <div class="wcd-head__badge">
                        <span class="wcd-pulse"></span>
                        <span>Admin Console</span>
                    </div>
                    <h1>{{ sapaan }}, {{ namaAdmin }}</h1>
                    <p>
                        Ringkasan rekrutmen <b v-if="tabAktif" class="wcd-head__highlight">{{ tabAktif.nama }}</b>
                        — pantau antrean aksi, alur konversi, dan kesehatan program hari ini.
                    </p>
                </div>
            </div>

            <div class="wcd-head__alat">
                <span class="wcd-cp" :title="checkpoint ? checkpoint.waktu : ''">
                    <i class="bi" :class="sibuk ? 'bi-arrow-repeat wcd-spin' : 'bi-clock-history'"></i>
                    <span class="wcd-cp__lbl">Terakhir diperbarui:</span>
                    <b>{{ checkpoint ? checkpoint.label : '—' }}</b>
                </span>

                <div class="wcd-seg" role="group" aria-label="Auto refresh">
                    <button v-for="o in [0, 60, 300]" :key="o" type="button" 
                        :class="{ 'is-on': intervalSec === o }"
                        :title="o === 0 ? 'Muat ulang otomatis mati' : `Muat ulang tiap ${o / 60} menit`"
                        @click="setInterval_(o)">
                        <i v-if="intervalSec === o && o > 0" class="bi bi-arrow-repeat wcd-spin"></i>
                        {{ o === 0 ? 'Off' : (o / 60) + 'm' }}
                    </button>
                </div>

                <button type="button" class="wca-btn wca-btn--primary wcd-btn-refresh" :disabled="sibuk" :onClick="sibuk ? null : refreshNow">
                    <i class="bi bi-arrow-clockwise" :class="{ 'wcd-spin': sibuk }"></i>
                    <span>Muat ulang</span>
                </button>
            </div>
        </header>

        <!-- ══════════════ TAB KATEGORI ══════════════ -->
        <nav v-if="tabs.length > 1" class="wcd-tabs" role="tablist" aria-label="Kategori program">
            <button v-for="t in tabs" :key="t.kode" type="button" role="tab" :aria-selected="kategori === t.kode"
                class="wcd-tab" :class="{ 'is-on': kategori === t.kode }" :style="{ '--aksen': t.warna }"
                @click="pilihTab(t.kode)">
                <span class="wcd-tab__ic">
                    <i class="bi" :class="t.ikon"></i>
                </span>
                <span class="wcd-tab__txt">{{ t.nama }}</span>
                <span v-if="kategori === t.kode" class="wcd-tab__indicator"></span>
            </button>
        </nav>
        <div v-else-if="tabs.length === 1" class="wcd-solo">
            <i class="bi" :class="tabs[0].ikon" :style="{ color: tabs[0].warna }"></i>
            <span>Hak akses Anda mencakup kategori <b>{{ tabs[0].nama }}</b> saja.</span>
        </div>
        <div v-else class="wcd-card">
            <KeadaanPanel keadaan="kosong" ikon="bi-shield-lock"
                teks="Belum ada kategori yang bisa Anda lihat"
                ket="Minta admin mengisi bagian KONTEN halaman Dashboard di Manajemen Hak Akses." />
        </div>

        <template v-if="tabs.length">
            <!-- ══════════════ QUICK-NAV FLOATING DOCK ══════════════ -->
            <div class="wcd-jump-wrapper">
                <nav class="wcd-jump" aria-label="Lompat ke seksi">
                    <a v-for="s in seksiTampil" :key="s.id" :href="`#wcd-${s.id}`"
                        :class="{ 'is-on': seksiAktif === s.id }" @click.prevent="lompat(s.id)">
                        <i class="bi" :class="s.ikon"></i>
                        <span>{{ s.label }}</span>
                        <em v-if="s.lencana" class="wcd-jump__badge">{{ s.lencana }}</em>
                    </a>
                </nav>
            </div>

            <!-- ══════════════ ZONA A — KPI STRIP ══════════════ -->
            <div :class="{ 'wcd-basi': zona.ringkas.segar }" class="wcd-zona-kpi">
                <KeadaanPanel v-if="zona.ringkas.keadaan === 'memuat' && !zona.ringkas.data" keadaan="memuat" />
                <KeadaanPanel v-else-if="zona.ringkas.keadaan === 'galat'" keadaan="galat"
                    :ket="zona.ringkas.pesan" @ulang="muatRingkas()" />
                <KpiStrip v-else-if="zona.ringkas.data" :kpi="zona.ringkas.data.kpi"
                    :periode="zona.ringkas.data.periode" :ambang="ambang" :kategori="kategori"
                    @lompat="lompat" />
            </div>

            <!-- ══════════════ ZONA C — KALENDER AGENDA ══════════════ -->
            <section :id="`wcd-agenda`" ref="refAgenda" class="wcd-sec" :class="{ 'is-tutup': tutup.has('agenda') }">
                <button type="button" class="wcd-sec__hd" @click="lipat('agenda')">
                    <div class="wcd-sec__ic wcd-sec__ic--blue">
                        <i class="bi bi-calendar-week-fill"></i>
                    </div>
                    <div class="wcd-sec__title">
                        <h2>Kalender Jadwal</h2>
                        <span class="wcd-sec__sub">Command center jadwal seleksi &amp; wawancara</span>
                    </div>
                    <i class="bi wcd-sec__chev" :class="tutup.has('agenda') ? 'bi-chevron-down' : 'bi-chevron-up'"></i>
                </button>
                <div v-show="!tutup.has('agenda')" class="wcd-sec__bd">
                    <CalendarCommandCenter ref="refKalender" :category="kategori"
                        :category-label="tabAktif?.nama" :accent="aksen" />
                </div>
            </section>

            <!-- ══════════════ ZONA B — BUTUH AKSI ══════════════ -->
            <section :id="`wcd-aksi`" ref="refAksi" class="wcd-sec" :class="{ 'is-tutup': tutup.has('aksi') }">
                <button type="button" class="wcd-sec__hd" @click="lipat('aksi')">
                    <div class="wcd-sec__ic wcd-sec__ic--amber">
                        <i class="bi bi-lightning-charge-fill"></i>
                    </div>
                    <div class="wcd-sec__title">
                        <h2>Butuh Aksi Kamu</h2>
                        <span class="wcd-sec__sub">Tindak lanjuti antrean keputusan &amp; proses pelamar</span>
                    </div>
                    <span v-if="totalAksi" class="wcd-sec__jml">{{ totalAksi }}</span>
                    <i class="bi wcd-sec__chev" :class="tutup.has('aksi') ? 'bi-chevron-down' : 'bi-chevron-up'"></i>
                </button>
                <div v-show="!tutup.has('aksi')" class="wcd-sec__bd" :class="{ 'wcd-basi': zona.ringkas.segar }">
                    <KeadaanPanel v-if="zona.ringkas.keadaan === 'memuat' && !zona.ringkas.data" keadaan="memuat" />
                    <KeadaanPanel v-else-if="zona.ringkas.keadaan === 'galat'" keadaan="galat"
                        :ket="zona.ringkas.pesan" @ulang="muatRingkas()" />
                    <AntreanAksi v-else-if="zona.ringkas.data" :aksi="zona.ringkas.data.aksi" :ambang="ambang" :kategori="kategori" />
                </div>
            </section>

            <!-- ══════════════ ZONA C1 — FUNNEL & KONVERSI ══════════════ -->
            <section :id="`wcd-funnel`" ref="refFunnel" class="wcd-sec" :class="{ 'is-tutup': tutup.has('funnel') }">
                <button type="button" class="wcd-sec__hd" @click="lipat('funnel')">
                    <div class="wcd-sec__ic wcd-sec__ic--indigo">
                        <i class="bi bi-funnel-fill"></i>
                    </div>
                    <div class="wcd-sec__title">
                        <h2>Funnel &amp; Konversi</h2>
                        <span class="wcd-sec__sub">Titik penyempitan terbesar per tahap seleksi</span>
                    </div>
                    <i class="bi wcd-sec__chev" :class="tutup.has('funnel') ? 'bi-chevron-down' : 'bi-chevron-up'"></i>
                </button>
                <div v-show="!tutup.has('funnel')" class="wcd-sec__bd" :class="{ 'wcd-basi': zona.analitik.segar }">
                    <KeadaanPanel v-if="zona.analitik.keadaan === 'memuat' && !zona.analitik.data" keadaan="memuat" />
                    <KeadaanPanel v-else-if="zona.analitik.keadaan === 'galat'" keadaan="galat"
                        :ket="zona.analitik.pesan" @ulang="muatAnalitik()" />
                    <FunnelPanel v-else-if="zona.analitik.data" :funnel="zona.analitik.data.funnel" />
                </div>
            </section>

            <!-- ══════════════ ZONA C2 — TREN LAMARAN ══════════════ -->
            <section :id="`wcd-tren`" ref="refTren" class="wcd-sec" :class="{ 'is-tutup': tutup.has('tren') }">
                <button type="button" class="wcd-sec__hd" @click="lipat('tren')">
                    <div class="wcd-sec__ic wcd-sec__ic--sky">
                        <i class="bi bi-graph-up"></i>
                    </div>
                    <div class="wcd-sec__title">
                        <h2>Tren Lamaran</h2>
                        <span class="wcd-sec__sub">Lamaran masuk vs diterima dari waktu ke waktu</span>
                    </div>
                    <i class="bi wcd-sec__chev" :class="tutup.has('tren') ? 'bi-chevron-down' : 'bi-chevron-up'"></i>
                </button>
                <div v-show="!tutup.has('tren')" class="wcd-sec__bd" :class="{ 'wcd-basi': zona.analitik.segar }">
                    <KeadaanPanel v-if="zona.analitik.keadaan === 'memuat' && !zona.analitik.data" keadaan="memuat" />
                    <KeadaanPanel v-else-if="zona.analitik.keadaan === 'galat'" keadaan="galat"
                        :ket="zona.analitik.pesan" @ulang="muatAnalitik()" />
                    <TrenPanel v-else-if="zona.analitik.data" :tren="zona.analitik.data.tren" />
                </div>
            </section>

            <!-- ══════════════ ZONA C3 — KESEHATAN & KUOTA PROGRAM ══════════════ -->
            <section :id="`wcd-program`" ref="refProgram" class="wcd-sec" :class="{ 'is-tutup': tutup.has('program') }">
                <button type="button" class="wcd-sec__hd" @click="lipat('program')">
                    <div class="wcd-sec__ic wcd-sec__ic--rose">
                        <i class="bi bi-heart-pulse-fill"></i>
                    </div>
                    <div class="wcd-sec__title">
                        <h2>Kesehatan &amp; Kuota Program</h2>
                        <span class="wcd-sec__sub">Skor kesehatan dan pemenuhan kursi tiap program</span>
                    </div>
                    <i class="bi wcd-sec__chev" :class="tutup.has('program') ? 'bi-chevron-down' : 'bi-chevron-up'"></i>
                </button>
                <div v-show="!tutup.has('program')" class="wcd-sec__bd" :class="{ 'wcd-basi': zona.analitik.segar }">
                    <KeadaanPanel v-if="zona.analitik.keadaan === 'memuat' && !zona.analitik.data" keadaan="memuat" />
                    <KeadaanPanel v-else-if="zona.analitik.keadaan === 'galat'" keadaan="galat"
                        :ket="zona.analitik.pesan" @ulang="muatAnalitik()" />
                    <KesehatanPanel v-else-if="zona.analitik.data" :baris="zona.analitik.data.kesehatan" />
                </div>
            </section>

            <!-- ══════════════ TENGGAT SLA MPP ══════════════
                 Hanya muncul untuk REKRUTMEN — server memulangkan daftar kosong
                 untuk MT, dan seksi ini ikut menghilang. MT direkrut seangkatan
                 mengikuti jadwal program, bukan mengejar tenggat pemenuhan
                 kursi per MPP. -->
            <section v-if="slaAda" :id="`wcd-sla`" class="wcd-sec" :class="{ 'is-tutup': tutup.has('sla') }">
                <button type="button" class="wcd-sec__hd" @click="lipat('sla')">
                    <div class="wcd-sec__ic wcd-sec__ic--rose">
                        <i class="bi bi-hourglass-split"></i>
                    </div>
                    <div class="wcd-sec__title">
                        <h2>Tenggat Pemenuhan MPP</h2>
                        <span class="wcd-sec__sub">Sisa waktu tiap MPP dalam hari kerja — libur nasional &amp; cuti bersama sudah dikecualikan</span>
                    </div>
                    <i class="bi wcd-sec__chev" :class="tutup.has('sla') ? 'bi-chevron-down' : 'bi-chevron-up'"></i>
                </button>
                <div v-show="!tutup.has('sla')" class="wcd-sec__bd" :class="{ 'wcd-basi': zona.analitik.segar }">
                    <SlaPanel
                        :ringkas="zona.analitik.data.sla.ringkas"
                        :baris="zona.analitik.data.sla.baris"
                    />
                </div>
            </section>

            <!-- ══════════════ ZONA D — SEKSI KHAS ══════════════ -->
            <section :id="`wcd-khas`" ref="refKhas" class="wcd-sec" :class="{ 'is-tutup': tutup.has('khas') }">
                <button type="button" class="wcd-sec__hd" @click="lipat('khas')">
                    <div class="wcd-sec__ic wcd-sec__ic--rose">
                        <i class="bi" :class="khasIkon"></i>
                    </div>
                    <div class="wcd-sec__title">
                        <h2>{{ khasJudul }}</h2>
                        <span class="wcd-sec__sub">Rincian spesifik modul kategori ini</span>
                    </div>
                    <i class="bi wcd-sec__chev" :class="tutup.has('khas') ? 'bi-chevron-down' : 'bi-chevron-up'"></i>
                </button>
                <div v-show="!tutup.has('khas')" class="wcd-sec__bd" :class="{ 'wcd-basi': zona.khas.segar }">
                    <KeadaanPanel v-if="zona.khas.keadaan === 'memuat' && !zona.khas.data" keadaan="memuat" />
                    <KeadaanPanel v-else-if="zona.khas.keadaan === 'galat'" keadaan="galat"
                        :ket="zona.khas.pesan" @ulang="muatKhas()" />
                    <template v-else-if="zona.khas.data">
                        <MtPanel v-if="zona.khas.data.bentuk === 'MT'" :khas="zona.khas.data.khas" />
                        <MagangPanel v-else-if="zona.khas.data.bentuk === 'MAGANG'" :khas="zona.khas.data.khas" />
                        <RekrutmenPanel v-else :khas="zona.khas.data.khas" />
                    </template>
                </div>
            </section>

            <!-- ══════════════ ZONA E — EKSTRA ══════════════ -->
            <section :id="`wcd-ekstra`" ref="refEkstra" class="wcd-sec" :class="{ 'is-tutup': tutup.has('ekstra') }">
                <button type="button" class="wcd-sec__hd" @click="lipat('ekstra')">
                    <div class="wcd-sec__ic wcd-sec__ic--violet">
                        <i class="bi bi-grid-1x2-fill"></i>
                    </div>
                    <div class="wcd-sec__title">
                        <h2>Tes, Operasional, Feedback &amp; Talent Pool</h2>
                        <span class="wcd-sec__sub">Performa tes, log operasional &amp; kepuasan kandidat</span>
                    </div>
                    <i class="bi wcd-sec__chev" :class="tutup.has('ekstra') ? 'bi-chevron-down' : 'bi-chevron-up'"></i>
                </button>
                <div v-show="!tutup.has('ekstra')" class="wcd-sec__bd" :class="{ 'wcd-basi': zona.khas.segar }">
                    <KeadaanPanel v-if="zona.khas.keadaan === 'memuat' && !zona.khas.data" keadaan="memuat" />
                    <KeadaanPanel v-else-if="zona.khas.keadaan === 'galat'" keadaan="galat"
                        :ket="zona.khas.pesan" @ulang="muatKhas()" />
                    <EkstraPanel v-else-if="zona.khas.data" :ekstra="zona.khas.data.ekstra" />
                </div>
            </section>
        </template>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, onUnmounted, nextTick } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { CFG } from '@utils/career/dashboard';
import KeadaanPanel from '../monitoring/KeadaanPanel.vue';
import KpiStrip from './panels/KpiStrip.vue';
import AntreanAksi from './panels/AntreanAksi.vue';
import FunnelPanel from './panels/FunnelPanel.vue';
import TrenPanel from './panels/TrenPanel.vue';
import KesehatanPanel from './panels/KesehatanPanel.vue';
import SlaPanel from './panels/SlaPanel.vue';
import CalendarCommandCenter from './panels/CalendarCommandCenter.vue';
import EkstraPanel from './panels/EkstraPanel.vue';
import RekrutmenPanel from './tabs/RekrutmenPanel.vue';
import MagangPanel from './tabs/MagangPanel.vue';
import MtPanel from './tabs/MtPanel.vue';
import { useAutoRefresh } from '../../../../composables/useAutoRefresh';

const props = defineProps({
    tabs: { type: Array, default: () => [] },
    tabAwal: { type: String, default: null },
    ambang: { type: Object, default: () => ({ macetHari: 7, sorotHari: 2 }) },
    checkpoint: { type: Object, default: null },
});

const PERIODE = [
    { nilai: '7', label: '7 hari' },
    { nilai: '30', label: '30 hari' },
    { nilai: '90', label: '90 hari' },
    { nilai: 'all', label: 'Semua' },
];

const URL_TAB = 'k';
const URL_PERIODE = 'p';

const kueri = new URLSearchParams(window.location.search);

function setKueri(kunci, nilai) {
    const u = new URL(window.location.href);
    u.searchParams.set(kunci, nilai);
    window.history.replaceState({}, '', u);
}

const sahKode = (k) => props.tabs.some((t) => t.kode === k);
const dariUrl = kueri.get(URL_TAB);
const kategori = ref((sahKode(dariUrl) && dariUrl) || props.tabAwal);

const periodeUrl = kueri.get(URL_PERIODE);
const periode = ref(PERIODE.some((p) => p.nilai === periodeUrl) ? periodeUrl : '30');

const checkpoint = ref(props.checkpoint);

const tutup = ref(new Set());
const seksiAktif = ref('aksi');
const refKalender = ref(null);

const zona = reactive({
    ringkas: { keadaan: 'memuat', data: null, pesan: '', segar: false },
    analitik: { keadaan: 'memuat', data: null, pesan: '', segar: false },
    khas: { keadaan: 'memuat', data: null, pesan: '', segar: false },
});

const halaman = usePage();
const namaAdmin = computed(
    () => halaman.props?.auth?.user?.name || halaman.props?.careerAuth?.nama || 'Admin',
);
const tabAktif = computed(() => props.tabs.find((t) => t.kode === kategori.value) || props.tabs[0] || null);

/**
 * Seksi tenggat SLA ditampilkan?
 *
 * Server memulangkan daftar kosong untuk MT (tenggat pemenuhan kursi bukan
 * ukuran di sana) dan untuk kategori yang belum punya MPP ber-SLA. Seksi yang
 * isinya pasti kosong lebih baik tidak muncul daripada muncul kosong — yang
 * kedua terbaca seperti data gagal dimuat.
 */
const slaAda = computed(() => (zona.analitik.data?.sla?.baris?.length ?? 0) > 0);
const aksen = computed(() => tabAktif.value?.warna || '#6366f1');

const sapaan = computed(() => {
    const j = new Date().getHours();
    if (j < 11) return 'Selamat pagi';
    if (j < 15) return 'Selamat siang';
    if (j < 19) return 'Selamat sore';
    return 'Selamat malam';
});

const sapaanIkon = computed(() => {
    const j = new Date().getHours();
    if (j < 11) return 'bi-sun-fill';
    if (j < 15) return 'bi-sun';
    if (j < 19) return 'bi-sunset-fill';
    return 'bi-moon-stars-fill';
});

const labelPeriode = computed(() => PERIODE.find((p) => p.nilai === periode.value)?.label || '30 hari');

const totalAksi = computed(() => {
    const a = zona.ringkas.data?.aksi;
    if (!a) return 0;
    return Object.values(a).reduce((n, b) => n + (b.total || 0), 0);
});

const khasJudul = computed(() => ({
    MT: 'Persaingan Antar-Posisi (MT)',
    MAGANG: 'Kampus, Kemitraan & Batch',
}[zona.khas.data?.bentuk] || 'Pemenuhan MPP & Posisi'));

const khasIkon = computed(() => ({
    MT: 'bi-diagram-3-fill',
    MAGANG: 'bi-building-fill',
}[zona.khas.data?.bentuk] || 'bi-clipboard-data-fill'));

const seksiTampil = computed(() => [
    { id: 'agenda', label: 'Kalender', ikon: 'bi-calendar-week-fill' },
    { id: 'aksi', label: 'Butuh Aksi', ikon: 'bi-lightning-charge-fill', lencana: totalAksi.value || null },
    { id: 'funnel', label: 'Funnel', ikon: 'bi-funnel-fill' },
    { id: 'tren', label: 'Tren', ikon: 'bi-graph-up' },
    { id: 'program', label: 'Program', ikon: 'bi-heart-pulse-fill' },
    { id: 'khas', label: khasJudul.value.split(' ')[0], ikon: khasIkon.value },
    { id: 'ekstra', label: 'Ekstra', ikon: 'bi-grid-1x2-fill' },
]);

async function muat(nama, jalur, ulang = false) {
    const z = zona[nama];
    if (!ulang || !z.data) z.keadaan = 'memuat';
    z.segar = true;
    try {
        const { data } = await axios.get(jalur, {
            params: { kategori: kategori.value, periode: periode.value },
            ...CFG,
        });
        z.data = data.result || null;
        z.keadaan = 'siap';
        z.pesan = '';
        if (z.data?.checkpoint) checkpoint.value = z.data.checkpoint;
    } catch (e) {
        z.pesan = e?.response?.data?.message || 'Sambungan ke server gagal.';
        if (!z.data) z.keadaan = 'galat';
    } finally {
        z.segar = false;
    }
}

const muatRingkas = (u = false) => muat('ringkas', '/api/v1/karir/dashboard/ringkas', u);
const muatAnalitik = (u = false) => muat('analitik', '/api/v1/karir/dashboard/analitik', u);
const muatKhas = (u = false) => muat('khas', '/api/v1/karir/dashboard/khas', u);

async function muatSemua(ulang = false) {
    if (!kategori.value) return;
    await muatRingkas(ulang);
    await Promise.all([muatAnalitik(ulang), muatKhas(ulang)]);
    if (ulang) refKalender.value?.refresh();
}

const { intervalSec, busy: sibuk, refreshNow } = useAutoRefresh(() => muatSemua(true), { initial: 0 });

function setInterval_(n) {
    intervalSec.value = n;
}

function setPeriode(p) {
    if (periode.value === p) return;
    periode.value = p;
    setKueri(URL_PERIODE, p);
    muatRingkas(true);
    muatAnalitik(true);
}

function pilihTab(kode) {
    if (kategori.value === kode) return;
    kategori.value = kode;
    setKueri(URL_TAB, kode);
    muatSemua(false);
}

function lipat(id) {
    const t = new Set(tutup.value);
    if (t.has(id)) t.delete(id);
    else t.add(id);
    tutup.value = t;
}

function lompat(id) {
    if (tutup.value.has(id)) {
        const t = new Set(tutup.value);
        t.delete(id);
        tutup.value = t;
    }
    seksiAktif.value = id;
    nextTick(() => {
        const el = document.getElementById(`wcd-${id}`);
        if (el) {
            const top = el.getBoundingClientRect().top + window.scrollY - 110;
            window.scrollTo({ top, behavior: 'smooth' });
        }
    });
}

const refAksi = ref(null);
const refFunnel = ref(null);
const refTren = ref(null);
const refProgram = ref(null);
const refAgenda = ref(null);
const refKhas = ref(null);
const refEkstra = ref(null);

function padaScroll() {
    const peta = [
        { id: 'agenda', ref: refAgenda },
        { id: 'aksi', ref: refAksi },
        { id: 'funnel', ref: refFunnel },
        { id: 'tren', ref: refTren },
        { id: 'program', ref: refProgram },
        { id: 'khas', ref: refKhas },
        { id: 'ekstra', ref: refEkstra },
    ];

    const garisMata = 220;
    for (let i = peta.length - 1; i >= 0; i--) {
        const el = peta[i].ref.value;
        if (el) {
            const r = el.getBoundingClientRect();
            if (r.top <= garisMata) {
                seksiAktif.value = peta[i].id;
                break;
            }
        }
    }
}

onMounted(() => {
    muatSemua(false);
    window.addEventListener('scroll', padaScroll, { passive: true });
});

onUnmounted(() => {
    window.removeEventListener('scroll', padaScroll);
});
</script>

<style>
/* ═════════════════════════════════════════════════════════════════════
   GLOBAL DASHBOARD DESIGN SYSTEM & UTILITY CLASSES (Unscoped for subcomponents)
   ═════════════════════════════════════════════════════════════════════ */
:root {
    --wcd-ink: #0f172a;
    --wcd-ink2: #334155;
    --wcd-redup: #64748b;
    --wcd-line: rgba(226, 232, 240, 0.85);
    --wcd-line-hover: rgba(203, 213, 225, 1);
    --wcd-bg: #f8fafc;
    --wcd-card-bg: #ffffff;
    --wcd-accent: #6366f1;
    --wcd-shadow-sm: 0 2px 8px -2px rgba(15, 23, 42, 0.05);
    --wcd-shadow-md: 0 10px 30px -10px rgba(15, 23, 42, 0.08);
    --wcd-shadow-lg: 0 20px 40px -15px rgba(15, 23, 42, 0.12);
    --wcd-radius-lg: 20px;
    --wcd-radius-md: 14px;
    --wcd-radius-sm: 10px;
}

.wcd {
    font-family: 'Inter', system-ui, -apple-system, sans-serif;
    color: var(--wcd-ink);
    padding-bottom: 50px;
    width: 100%;
    max-width: none;
    margin: 0;
}

/* ══════════ KEPALA DASHBOARD (BANNER) ══════════ */
.wcd-head {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
    padding: 24px 28px;
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.95), rgba(248, 250, 252, 0.95));
    backdrop-filter: blur(16px);
    border: 1px solid var(--wcd-line);
    border-radius: var(--wcd-radius-lg);
    box-shadow: var(--wcd-shadow-md);
    margin-bottom: 20px;
    overflow: hidden;
}

.wcd-head__ambient {
    position: absolute;
    top: -80px;
    right: -80px;
    width: 320px;
    height: 320px;
    background: radial-gradient(circle, color-mix(in srgb, var(--aksen-bg, #6366f1) 18%, transparent) 0%, transparent 70%);
    pointer-events: none;
    z-index: 0;
}

.wcd-head__intro {
    position: relative;
    z-index: 1;
    display: flex;
    align-items: center;
    gap: 18px;
}

.wcd-head__avatar {
    display: grid;
    place-items: center;
    width: 54px;
    height: 54px;
    border-radius: 16px;
    font-size: 24px;
    color: var(--aksen, #6366f1);
    background: linear-gradient(135deg, color-mix(in srgb, var(--aksen, #6366f1) 16%, #fff), color-mix(in srgb, var(--aksen, #6366f1) 6%, #fff));
    border: 1px solid color-mix(in srgb, var(--aksen, #6366f1) 25%, transparent);
    box-shadow: 0 4px 12px color-mix(in srgb, var(--aksen, #6366f1) 15%, transparent);
    flex: none;
}

.wcd-head__badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 3px 10px;
    border-radius: 999px;
    background: color-mix(in srgb, #6366f1 10%, #fff);
    border: 1px solid color-mix(in srgb, #6366f1 20%, transparent);
    font-size: 0.7rem;
    font-weight: 800;
    color: #4f46e5;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    margin-bottom: 6px;
}

.wcd-pulse {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: #10b981;
    box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7);
    animation: wcd-pulse-anim 2s infinite;
}

@keyframes wcd-pulse-anim {
    0% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7); }
    70% { box-shadow: 0 0 0 8px rgba(16, 185, 129, 0); }
    100% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
}

.wcd-head__text h1 {
    margin: 0 0 4px;
    font-size: 1.45rem;
    font-weight: 900;
    letter-spacing: -0.02em;
    color: var(--wcd-ink);
    line-height: 1.2;
}

.wcd-head__text p {
    margin: 0;
    font-size: 0.83rem;
    color: var(--wcd-redup);
    font-weight: 500;
}

.wcd-head__highlight {
    color: #4f46e5;
}

.wcd-head__alat {
    position: relative;
    z-index: 1;
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
}

.wcd-cp {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 14px;
    background: #ffffff;
    border: 1px solid var(--wcd-line);
    border-radius: var(--wcd-radius-sm);
    font-size: 0.75rem;
    color: var(--wcd-redup);
    box-shadow: var(--wcd-shadow-sm);
}

.wcd-cp b {
    color: var(--wcd-ink);
    font-weight: 800;
}

.wcd-spin {
    animation: wcd-spin-anim 1s linear infinite;
}

@keyframes wcd-spin-anim {
    to { transform: rotate(360deg); }
}

.wcd-btn-refresh {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 8px 16px;
    border-radius: var(--wcd-radius-sm);
    font-weight: 800;
    box-shadow: 0 4px 14px rgba(99, 102, 241, 0.3);
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}

.wcd-btn-refresh:hover {
    transform: translateY(-1px);
    box-shadow: 0 6px 20px rgba(99, 102, 241, 0.4);
}

/* ══════════ SEGMENTED BUTTONS ══════════ */
.wcd-seg {
    display: inline-flex;
    padding: 4px;
    background: #f1f5f9;
    border-radius: 12px;
    gap: 2px;
    border: 1px solid rgba(226, 232, 240, 0.8);
}

.wcd-seg button {
    border: 0;
    background: transparent;
    padding: 5px 12px;
    border-radius: 8px;
    font-size: 0.75rem;
    font-weight: 700;
    color: var(--wcd-redup);
    cursor: pointer;
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}

.wcd-seg button:hover {
    color: var(--wcd-ink);
}

.wcd-seg button.is-on {
    background: #ffffff;
    color: #4338ca;
    box-shadow: 0 2px 6px rgba(15, 23, 42, 0.08);
    font-weight: 900;
}

/* ══════════ TAB KATEGORI ══════════ */
.wcd-tabs {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 20px;
    overflow-x: auto;
    padding: 4px 2px;
}

.wcd-tab {
    position: relative;
    display: inline-flex;
    align-items: center;
    gap: 10px;
    padding: 12px 20px;
    background: #ffffff;
    border: 1px solid var(--wcd-line);
    border-radius: var(--wcd-radius-md);
    font: inherit;
    font-size: 0.88rem;
    font-weight: 700;
    color: var(--wcd-redup);
    cursor: pointer;
    transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
    box-shadow: var(--wcd-shadow-sm);
}

.wcd-tab:hover {
    color: var(--wcd-ink);
    border-color: color-mix(in srgb, var(--aksen, #6366f1) 40%, var(--wcd-line));
    transform: translateY(-2px);
    box-shadow: var(--wcd-shadow-md);
}

.wcd-tab.is-on {
    color: var(--wcd-ink);
    background: #ffffff;
    border-color: color-mix(in srgb, var(--aksen, #6366f1) 60%, transparent);
    box-shadow: 0 8px 24px color-mix(in srgb, var(--aksen, #6366f1) 15%, transparent);
    font-weight: 900;
}

.wcd-tab__ic {
    display: grid;
    place-items: center;
    width: 28px;
    height: 28px;
    border-radius: 8px;
    font-size: 14px;
    color: var(--aksen, #6366f1);
    background: color-mix(in srgb, var(--aksen, #6366f1) 12%, #fff);
}

.wcd-tab.is-on .wcd-tab__ic {
    background: var(--aksen, #6366f1);
    color: #ffffff;
    box-shadow: 0 3px 8px color-mix(in srgb, var(--aksen, #6366f1) 40%, transparent);
}

.wcd-tab__indicator {
    position: absolute;
    bottom: -1px;
    left: 20%;
    right: 20%;
    height: 3px;
    border-radius: 999px;
    background: var(--aksen, #6366f1);
}

.wcd-solo {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px 18px;
    background: #ffffff;
    border: 1px solid var(--wcd-line);
    border-radius: var(--wcd-radius-md);
    font-size: 0.83rem;
    color: var(--wcd-ink2);
    margin-bottom: 20px;
    box-shadow: var(--wcd-shadow-sm);
}

/* ══════════ FLOATING QUICK-NAV DOCK ══════════ */
.wcd-jump-wrapper {
    position: sticky;
    top: 90px;
    z-index: 40;
    margin-bottom: 26px;
    pointer-events: none;
}

.wcd-jump {
    pointer-events: auto;
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 7px 10px;
    background: linear-gradient(135deg, rgba(248, 250, 252, 0.96), rgba(238, 242, 255, 0.96));
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border: 1px solid color-mix(in srgb, #6366f1 28%, transparent);
    border-radius: 999px;
    box-shadow: 0 12px 32px -6px rgba(15, 23, 42, 0.12), 0 0 0 1px rgba(255, 255, 255, 0.9) inset;
    overflow-x: auto;
    width: fit-content;
    max-width: 100%;
    margin: 0 auto;
}

.wcd-jump a {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 8px 16px;
    border-radius: 999px;
    font-size: 0.78rem;
    font-weight: 800;
    color: #475569;
    text-decoration: none;
    white-space: nowrap;
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}

.wcd-jump a:hover {
    color: #4f46e5;
    background: rgba(255, 255, 255, 0.9);
    box-shadow: 0 2px 8px rgba(99, 102, 241, 0.1);
}

.wcd-jump a.is-on {
    background: linear-gradient(135deg, #4f46e5, #6366f1);
    color: #ffffff;
    font-weight: 800;
    box-shadow: 0 4px 14px rgba(79, 70, 229, 0.4);
}

.wcd-jump a.is-on i {
    color: #ffffff;
}

.wcd-jump__badge {
    font-style: normal;
    padding: 2px 8px;
    border-radius: 999px;
    background: #ef4444;
    color: #ffffff;
    font-size: 0.68rem;
    font-weight: 900;
    line-height: 1.2;
}

.wcd-jump a.is-on .wcd-jump__badge {
    background: #ffffff;
    color: #4f46e5;
}

/* ══════════ ZONA CONTAINER (SEKSI) ══════════ */
.wcd-zona-kpi {
    margin-bottom: 24px;
}

.wcd-sec {
    background: #ffffff;
    border: 1px solid var(--wcd-line);
    border-radius: var(--wcd-radius-lg);
    box-shadow: var(--wcd-shadow-sm);
    margin-bottom: 24px;
    transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
    overflow: hidden;
}

.wcd-sec:hover {
    border-color: var(--wcd-line-hover);
    box-shadow: var(--wcd-shadow-md);
}

.wcd-sec.is-tutup {
    margin-bottom: 16px;
}

.wcd-sec__hd {
    display: flex;
    align-items: center;
    gap: 14px;
    width: 100%;
    padding: 18px 24px;
    border: 0;
    background: transparent;
    text-align: left;
    cursor: pointer;
    transition: background 0.2s ease;
}

.wcd-sec__hd:hover {
    background: rgba(248, 250, 252, 0.8);
}

.wcd-sec__ic {
    display: grid;
    place-items: center;
    width: 38px;
    height: 38px;
    border-radius: 12px;
    font-size: 17px;
    flex: none;
}

.wcd-sec__ic--amber { background: #fef3c7; color: #d97706; }
.wcd-sec__ic--indigo { background: #e0e7ff; color: #4338ca; }
.wcd-sec__ic--purple { background: #f3e8ff; color: #7e22ce; }
.wcd-sec__ic--emerald { background: #d1fae5; color: #047857; }
.wcd-sec__ic--blue { background: #e0f2fe; color: #0369a1; }
.wcd-sec__ic--rose { background: #ffe4e6; color: #be123c; }
.wcd-sec__ic--violet { background: #ede9fe; color: #6d28d9; }

.wcd-sec__title {
    flex: 1;
    min-width: 0;
}

.wcd-sec__title h2 {
    margin: 0;
    font-size: 1.02rem;
    font-weight: 900;
    letter-spacing: -0.01em;
    color: var(--wcd-ink);
}

.wcd-sec__sub {
    display: block;
    margin-top: 2px;
    font-size: 0.74rem;
    font-weight: 500;
    color: var(--wcd-redup);
}

.wcd-sec__jml {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 24px;
    height: 24px;
    padding: 0 8px;
    border-radius: 999px;
    background: #fef2f2;
    border: 1px solid #fecaca;
    color: #dc2626;
    font-size: 0.74rem;
    font-weight: 900;
}

.wcd-sec__chev {
    font-size: 16px;
    color: var(--wcd-redup);
    transition: transform 0.25s ease;
}

.wcd-sec__bd {
    padding: 4px 24px 24px;
}

.wcd-filter {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 18px;
    padding: 10px 14px;
    background: #f8fafc;
    border-radius: var(--wcd-radius-sm);
    border: 1px solid var(--wcd-line);
    flex-wrap: wrap;
}

.wcd-filter__lbl {
    font-size: 0.78rem;
    font-weight: 800;
    color: var(--wcd-ink2);
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.wcd-filter__note {
    font-size: 0.72rem;
    color: var(--wcd-redup);
    margin-left: auto;
}

.wcd-basi {
    opacity: 0.5;
    transition: opacity 0.25s ease;
    pointer-events: none;
}

/* ══════════ KARTU & KISI UTILITY ══════════ */
.wcd-card {
    background: #ffffff;
    border: 1px solid var(--wcd-line);
    border-radius: 18px;
    padding: 20px;
    box-shadow: var(--wcd-shadow-sm);
}

.wcd-card--datar {
    background: #ffffff;
    border-color: rgba(226, 232, 240, 0.9);
}

.wcd-card__hd {
    display: flex;
    align-items: center;
    gap: 10px;
    margin: 0 0 16px;
    font-size: 0.9rem;
    font-weight: 900;
    color: var(--wcd-ink);
}

.wcd-card__hd .bi {
    color: #6366f1;
    font-size: 1.1rem;
}

.wcd-card__hd small {
    margin-left: auto;
    font-weight: 700;
    color: var(--wcd-redup);
    font-size: 0.76rem;
}

.wcd-grid { display: grid; gap: 16px; }
.wcd-grid--2 { grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); }
.wcd-grid--3 { grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); }
.wcd-grid--4 { grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); }

/* ══════════ KARTU ANGKA & STAT TILE ══════════ */
.wcd-kpi { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 14px; }
.wcd-kpi--rapat { grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); }

.wcd-stat {
    display: block;
    width: 100%;
    padding: 14px 16px;
    background: #ffffff;
    border: 1px solid var(--wcd-line);
    border-radius: 16px;
    text-align: left;
    text-decoration: none;
    font: inherit;
    transition: transform 0.25s cubic-bezier(0.16, 1, 0.3, 1), box-shadow 0.25s cubic-bezier(0.16, 1, 0.3, 1), border-color 0.25s cubic-bezier(0.16, 1, 0.3, 1);
    box-shadow: 0 2px 8px -2px rgba(15, 23, 42, 0.04);
}

.wcd-stat.is-klik { cursor: pointer; }
.wcd-stat.is-klik:hover {
    transform: translateY(-3px);
    border-color: color-mix(in srgb, var(--tone, #6366f1) 45%, var(--wcd-line));
    box-shadow: 0 12px 28px -6px color-mix(in srgb, var(--tone, #6366f1) 18%, transparent);
}

.wcd-stat__top { display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px; }
.wcd-stat__ic {
    display: grid;
    place-items: center;
    width: 32px;
    height: 32px;
    flex: none;
    border-radius: 10px;
    font-size: 15px;
    color: var(--tone, #6366f1);
    background: color-mix(in srgb, var(--tone, #6366f1) 13%, #fff);
    border: 1px solid color-mix(in srgb, var(--tone, #6366f1) 20%, transparent);
}
.wcd-stat__num { font-size: 1.55rem; font-weight: 900; line-height: 1.1; color: var(--wcd-ink); letter-spacing: -0.02em; }
.wcd-stat__num small { font-size: 0.8rem; font-weight: 800; color: var(--wcd-redup); }
.wcd-stat__lbl { margin-top: 5px; font-size: 0.78rem; font-weight: 800; color: var(--wcd-ink2); }
.wcd-stat__ket { margin-top: 3px; font-size: 0.72rem; color: var(--wcd-redup); }
.wcd-stat__delta { margin-left: auto; display: inline-flex; align-items: center; gap: 3px; font-size: 0.73rem; font-weight: 800; }

/* ══════════ TABEL & CONTAINER UTILITY ══════════ */
.wcd-tw {
    overflow-x: auto;
    border: 1px solid var(--wcd-line);
    border-radius: 16px;
    background: #ffffff;
    box-shadow: var(--wcd-shadow-sm);
}

.wcd-tbl { width: 100%; border-collapse: collapse; font-size: 0.8rem; }
.wcd-tbl th,
.wcd-tbl td { padding: 12px 14px; text-align: left; border-bottom: 1px solid #f1f5f9; white-space: nowrap; }
.wcd-tbl th {
    background: #f8fafc;
    font-size: 0.72rem;
    font-weight: 900;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: #475569;
    position: sticky;
    top: 0;
    z-index: 1;
}
.wcd-tbl tbody tr:last-child td { border-bottom: 0; }
.wcd-tbl tbody tr:hover td { background: #f8fafc; }
.wcd-num { text-align: right; font-variant-numeric: tabular-nums; }
.wcd-tbl__utama { font-weight: 800; color: var(--wcd-ink); white-space: normal; min-width: 180px; }
.wcd-tbl__sub { display: block; font-weight: 600; color: var(--wcd-redup); font-size: 0.73rem; margin-top: 2px; }

/* ══════════ LENCANA & METER ══════════ */
.wcd-lb {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 3px 10px;
    border-radius: 999px;
    font-size: 0.74rem;
    font-weight: 800;
    background: #f1f5f9;
    color: var(--wcd-ink2);
    white-space: nowrap;
}
.wcd-lb .bi { font-size: 12px; }

.wcd-meter { height: 7px; border-radius: 999px; background: #f1f5f9; overflow: hidden; min-width: 80px; }
.wcd-meter span { display: block; height: 100%; border-radius: 999px; background: #6366f1; transition: width 0.35s ease; }

/* ══════════ RESPONSIVE ══════════ */
@media (max-width: 768px) {
    .wcd-head {
        flex-direction: column;
        align-items: flex-start;
        padding: 18px;
    }
    .wcd-head__alat {
        width: 100%;
        justify-content: space-between;
    }
    .wcd-sec__hd {
        padding: 14px 16px;
    }
    .wcd-sec__bd {
        padding: 4px 16px 16px;
    }
    .wcd-jump-wrapper {
        top: 60px;
    }
}
</style>
