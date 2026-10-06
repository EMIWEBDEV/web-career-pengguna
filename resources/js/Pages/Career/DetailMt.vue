<!-- WEB CAREER — Halaman Program Management Trainee (route: /karir/landing-page/mt/{id})
     BUKAN halaman detail satu posisi. Sebuah program MT menaungi banyak posisi,
     jadi halaman ini adalah /karir/lowongan versi satu program: hero + toolbar
     lengket + sidebar filter + grid kartu, memakai token visual yang SAMA
     (metrik .sl-* dari SemuaLowongan.vue) supaya keduanya terasa satu produk. -->
<template>
    <Head :title="mt.nama" />

    <CareerLayout :has-mt="hasMt" :offices="offices">
        <div class="mtd">
            <!-- ═══ HERO ═══
                 Sengaja TANPA kartu/panel — cuma badge kecil untuk status,
                 sisanya teks polos. Isinya dipusatkan dalam kolom nyaman
                 (bukan rata kiri selebar halaman): rata kiri di layar lebar
                 menyisakan separuh halaman kosong yang terlihat seperti
                 rusak, bukan lapang. Rata tengah membuat ruang kosong di
                 kiri-kanan terasa sengaja (margin), bukan kebetulan. -->
            <header class="mtd-hero">
                <div class="mtd-hero__glow mtd-hero__glow--a" aria-hidden="true"></div>
                <div class="mtd-hero__glow mtd-hero__glow--b" aria-hidden="true"></div>

                <div class="mtd-hero__in">
                    <button type="button" class="mtd-back" @click="goToSection('mt')">
                        <i class="bi bi-arrow-left"></i> Semua Program MT
                    </button>

                    <div class="mtd-hero__center">
                        <div class="mtd-hero__badges">
                            <span class="mtd-eyebrow"><i class="bi bi-gem"></i> Management Trainee</span>
                            <span v-if="mt.batch" class="mtd-badge mtd-badge--batch"><i class="bi bi-stars"></i> {{ mt.batch }}</span>
                            <span v-if="mt.penempatan" class="mtd-badge mtd-badge--loc"><i class="bi bi-geo-alt-fill"></i> {{ mt.penempatan }}</span>
                            <span class="mtd-badge mtd-badge--open">
                                <i class="bi bi-check-circle-fill"></i>
                                {{ statusLabel(mt.status) }}
                            </span>
                        </div>

                        <h1>{{ mt.nama }}</h1>
                        <p>{{ mt.deskripsi }}</p>

                        <!-- TANPA kuota sama sekali — di sini maupun di kartu posisi
                             di bawah. Jumlah kursi adalah angka perencanaan internal
                             dan sudah tidak dikirim backend. -->
                        <div class="mtd-hero__meta">
                            <span class="mtd-hero__meta-item">
                                <i class="bi bi-diagram-3-fill"></i> {{ posisi.length }} Posisi
                            </span>
                            <span class="mtd-hero__meta-item">
                                <i class="bi bi-person-lines-fill"></i> {{ mt.pelamar }} Pelamar
                            </span>
                            <span class="mtd-hero__meta-item" :class="{ 'is-urgent': daysLeft(mt.tanggalTutup) <= 7 }">
                                <i class="bi bi-clock-fill"></i> {{ deadlineLabel(mt.tanggalTutup) }}
                                <span v-if="daysLeft(mt.tanggalTutup) <= 7" class="mtd-hero__meta-pulse"></span>
                            </span>
                        </div>

                        <button v-if="posisi.length" type="button" class="mtd-cta" @click="keDaftarPosisi">
                            <i class="bi bi-list-check"></i> Lihat {{ posisi.length }} Posisi
                        </button>
                    </div>
                </div>
            </header>

            <!-- ═══ TENTANG PROGRAM ═══
                 Satu kolom penuh — Jadwal Kegiatan sengaja TIDAK lagi jadi
                 sidebar sempit yang menempel & digulir. Programnya bisa
                 punya sampai belasan agenda; kolom 380px lama memaksa
                 sebagian besar tersembunyi di balik scroll. Sekarang jadwal
                 dapat baris sendiri selebar halaman, sejajar Tahapan Seleksi
                 yang sudah lebih dulu memakai pola itu. -->
            <section class="mtd-about">
                <div class="mtd-sec__head">
                    <h2><i class="bi bi-info-circle-fill"></i> Tentang Program Ini</h2>
                    <span class="mtd-sec__note">Berlaku untuk seluruh posisi</span>
                </div>

                <div v-if="mt.catatanKegiatan" class="mtd-callout">
                    <i class="bi bi-megaphone-fill"></i><span>{{ mt.catatanKegiatan }}</span>
                </div>

                <!-- JENDELA PENDAFTARAN DIHAPUS.
                     Isinya mengulang apa yang sudah tertulis di hero: sisa hari
                     ("100 hari lagi") dan status pendaftaran sudah tampil di
                     baris meta paling atas halaman. Satu kartu selebar layar
                     hanya untuk mengatakannya kedua kali mendorong Tahapan
                     Seleksi — yang benar-benar dicari pelamar — turun jauh ke
                     bawah lipatan. -->

                <!-- KRITERIA & KAMPUS SASARAN -->
                <div class="mtd-subcards">
                    <!-- KRITERIA PESERTA -->
                    <article v-if="mt.kriteria && mt.kriteria.length" class="mtd-card">
                        <h3><i class="bi bi-clipboard-check-fill"></i> Kriteria Peserta</h3>
                        <ul class="mtd-list">
                            <li v-for="(t, i) in mt.kriteria" :key="i"><i class="bi bi-check-circle-fill"></i><span>{{ t }}</span></li>
                        </ul>
                    </article>

                    <!-- KAMPUS SASARAN -->
                    <article v-if="mt.targetKampus && mt.targetKampus.length" class="mtd-card">
                        <h3><i class="bi bi-mortarboard-fill"></i> Kampus Sasaran</h3>
                        <div class="mtd-tags"><span v-for="c in mt.targetKampus" :key="c">{{ c }}</span></div>
                    </article>
                </div>

                <!-- JADWAL KEGIATAN — Di-hide / dikomentar dulu sesuai permintaan -->
                <!-- <article v-if="agenda.length" class="mtd-card mtd-jadwal">
                    <div class="mtd-card__head">
                        <h3><i class="bi bi-calendar-range-fill"></i> Jadwal Kegiatan</h3>
                        <span class="mtd-card__tag">{{ agendaLewat }}/{{ agenda.length }} selesai</span>
                    </div>

                    <div v-if="agendaBerikutnya" class="mtd-jw__next" :class="{ 'is-kini': agendaBerikutnya.keadaan === 'kini' }">
                        <span class="mtd-jw__next-pulse"></span>
                        <div class="mtd-jw__next-body">
                            <small>{{ agendaBerikutnya.keadaan === 'kini' ? 'Sedang berlangsung' : 'Agenda berikutnya' }}</small>
                            <strong>{{ agendaBerikutnya.label }}</strong>
                        </div>
                        <span class="mtd-jw__next-chip">{{ agendaBerikutnyaRelatif }}</span>
                    </div>

                    <button
                        v-if="agendaBerikutnya && agendaLewat"
                        type="button"
                        class="mtd-jw__toggle"
                        :aria-expanded="riwayatBuka"
                        @click="riwayatBuka = !riwayatBuka"
                    >
                        <i class="bi" :class="riwayatBuka ? 'bi-chevron-up' : 'bi-chevron-down'"></i>
                        {{ riwayatBuka ? 'Sembunyikan' : 'Lihat' }} {{ agendaLewat }} kegiatan yang telah selesai
                    </button>

                    <button
                        v-else-if="!agendaBerikutnya"
                        type="button"
                        class="mtd-jw__kosong"
                        :aria-expanded="riwayatBuka"
                        @click="riwayatBuka = !riwayatBuka"
                    >
                        <i class="bi bi-check2-circle"></i>
                        <span>Seluruh {{ agenda.length }} agenda telah selesai</span>
                        <i class="bi mtd-jw__kosong-chev" :class="riwayatBuka ? 'bi-chevron-up' : 'bi-chevron-down'"></i>
                    </button>

                    <div v-if="agendaTampil.length" class="mtd-jcal">
                        <div v-for="grp in agendaKelompok" :key="grp.kunci" class="mtd-jcal__grp">
                            <div class="mtd-jcal__bulan">{{ grp.label }}</div>
                            <ol class="mtd-jcal__grid">
                                <li v-for="(j, i) in grp.item" :key="i" :class="`is-${j.keadaan}`">
                                    <span class="mtd-jcal__no">No. {{ String(j.urutan).padStart(agendaLebar, '0') }}</span>
                                    <span v-if="j.keadaan === 'kini'" class="mtd-jcal__kini">Kini</span>
                                    <span class="mtd-jcal__date">
                                        <b>{{ j.hariAngka ?? '–' }}</b>
                                        <small>{{ j.bulanSingkat }}</small>
                                    </span>
                                    <div class="mtd-jcal__body">
                                        <strong>{{ j.label }}</strong>
                                        <span class="mtd-jcal__range">{{ j.tanggal }}</span>
                                    </div>
                                    <i v-if="j.keadaan === 'lewat'" class="bi bi-check-circle-fill mtd-jcal__check"></i>
                                </li>
                            </ol>
                        </div>
                    </div>
                </article> -->

                <!-- TAHAPAN SELEKSI -->
                <article v-if="mt.pipeline && mt.pipeline.length" class="mtd-card mtd-card--tahapan">
                    <h3><i class="bi bi-signpost-split-fill"></i> Tahapan Seleksi</h3>
                    <ol class="mtd-flow">
                        <li v-for="(p, i) in mt.pipeline" :key="i">
                            <span class="mtd-flow__num">{{ i + 1 }}</span>
                            <div class="mtd-flow__body">
                                <strong>{{ p.label }}</strong>
                                <small>{{ stageTypeLabel(p.tipe) }}</small>
                            </div>
                        </li>
                    </ol>
                </article>
            </section>

            <!-- ═══ TOOLBAR PAPAN POSISI (lengket) ═══ -->
            <div class="mtd-toolbar-wrap">
                <div class="mtd-toolbar">
                    <div class="mtd-search">
                        <i class="bi bi-search"></i>
                        <input v-model="cari" type="text" placeholder="Cari posisi, departemen, atau skill..." aria-label="Cari posisi" />
                        <button v-if="cari" type="button" class="mtd-search__clear" title="Hapus pencarian" @click="cari = ''">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>

                    <div class="mtd-controls">
                        <button
                            v-if="adaFaset"
                            type="button"
                            class="mtd-tab"
                            :class="{ 'is-on': filterBuka }"
                            @click="filterBuka = !filterBuka"
                        >
                            <i class="bi bi-funnel-fill"></i> Filter
                            <span v-if="jumlahFilterAktif" class="mtd-tab__n">{{ jumlahFilterAktif }}</span>
                        </button>

                        <div class="mtd-sort">
                            <i class="bi bi-sort-down mtd-sort__ico"></i>
                            <span class="mtd-sort__lbl">Urutkan</span>
                            <select v-model="urut" class="mtd-sort__select" aria-label="Urutkan posisi">
                                <option value="nama">Nama Posisi (A-Z)</option>
                                <option value="departemen">Departemen (A-Z)</option>
                                <option value="lokasi">Lokasi (A-Z)</option>
                            </select>
                            <i class="bi bi-chevron-down mtd-sort__chev"></i>
                        </div>

                        <div class="mtd-view">
                            <button type="button" class="mtd-view__btn" :class="{ 'is-active': mode === 'grid' }" title="Tampilan grid" @click="mode = 'grid'">
                                <i class="bi bi-grid-3x3-gap-fill"></i>
                            </button>
                            <button type="button" class="mtd-view__btn" :class="{ 'is-active': mode === 'list' }" title="Tampilan list" @click="mode = 'list'">
                                <i class="bi bi-list-task"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ═══ PAPAN POSISI ═══ -->
            <section id="posisi" class="mtd-sec">
                <div class="mtd-layout" :class="{ 'is-filter-open': filterBuka && adaFaset }">
                    <!-- ── FILTER FASET ── -->
                    <aside v-show="filterBuka && adaFaset" class="mtd-aside">
                        <div class="mtd-aside__body">
                            <div class="mtd-aside__head">
                                <span><i class="bi bi-funnel-fill"></i> Filter Posisi</span>
                                <button v-if="jumlahFilterAktif" type="button" class="mtd-aside__reset" @click="resetFilter">
                                    <i class="bi bi-arrow-counterclockwise"></i> Reset
                                </button>
                            </div>

                            <div v-for="f in faset" :key="f.key" class="mtd-fgroup" :class="{ 'is-collapsed': lipat[f.key] }">
                                <div class="mtd-fgroup__head" @click="lipat[f.key] = !lipat[f.key]">
                                    <span class="mtd-fgroup__title">
                                        {{ f.label }}
                                        <b v-if="pilihan[f.key].length" class="mtd-fgroup__badge">{{ pilihan[f.key].length }}</b>
                                    </span>
                                    <i class="bi bi-chevron-down mtd-fgroup__chev"></i>
                                </div>
                                <div v-show="!lipat[f.key]" class="mtd-fgroup__list">
                                    <!-- Nama departemen bisa sangat panjang ("PRODUCTION SUPPORT ·
                                         HEALTH SAFETY ENVIRONMENT"); dipotong dengan elipsis dan
                                         teks penuhnya tetap tersedia lewat title. -->
                                    <label v-for="o in f.opsi" :key="o.nama" class="mtd-check" :title="o.nama">
                                        <input v-model="pilihan[f.key]" type="checkbox" :value="o.nama" />
                                        <span class="mtd-check__box"><i class="bi bi-check-lg"></i></span>
                                        <span class="mtd-check__lbl">{{ o.nama }}</span>
                                        <span class="mtd-check__n">{{ o.count }}</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </aside>

                    <!-- ── DAFTAR POSISI ── -->
                    <div class="mtd-main">
                        <div class="mtd-sec__head">
                            <h2><i class="bi bi-diagram-3-fill"></i> Posisi Tersedia</h2>
                            <span class="mtd-sec__count">{{ hasil.length }} dari {{ posisi.length }} posisi</span>
                        </div>

                        <!-- Chip filter aktif: apa yang sedang membatasi daftar, sekali klik untuk melepas. -->
                        <div v-if="jumlahFilterAktif || cari" class="mtd-aktif">
                            <button v-if="cari" type="button" class="mtd-chip" @click="cari = ''">
                                <i class="bi bi-search"></i> “{{ cari }}” <i class="bi bi-x-lg"></i>
                            </button>
                            <template v-for="f in faset" :key="f.key">
                                <button
                                    v-for="v in pilihan[f.key]"
                                    :key="f.key + v"
                                    type="button"
                                    class="mtd-chip"
                                    @click="lepas(f.key, v)"
                                >
                                    {{ v }} <i class="bi bi-x-lg"></i>
                                </button>
                            </template>
                            <button type="button" class="mtd-chip mtd-chip--reset" @click="resetSemua">
                                <i class="bi bi-arrow-counterclockwise"></i> Bersihkan
                            </button>
                        </div>

                        <div v-if="hasil.length" class="mtd-grid" :class="{ 'is-list-grid': mode === 'list' }">
                            <LowonganCard
                                v-for="p in hasil"
                                :key="p.id"
                                :job="p"
                                :view-mode="mode"
                                :show-save="false"
                                :show-induk="false"
                            />
                        </div>

                        <div v-else-if="posisi.length" class="mtd-empty mtd-empty--col">
                            <div class="mtd-empty__ico"><i class="bi bi-search"></i></div>
                            <h3>Tidak Ada Posisi yang Cocok</h3>
                            <p>Coba longgarkan filter atau ubah kata pencarianmu.</p>
                            <button type="button" class="mtd-empty__btn" @click="resetSemua">
                                <i class="bi bi-arrow-counterclockwise"></i> Tampilkan Semua Posisi
                            </button>
                        </div>

                        <div v-else class="mtd-empty mtd-empty--col">
                            <div class="mtd-empty__ico"><i class="bi bi-inbox"></i></div>
                            <h3>Belum Ada Posisi Dibuka</h3>
                            <p>Program ini belum membuka posisi. Pantau terus halaman ini.</p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- ═══ CTA PENUTUP ═══
                 Komponen yang SAMA dengan penutup landing (CtaSection), dipakai
                 ulang — bukan disalin — supaya kalau teks/tombolnya berubah,
                 semua halaman ikut berubah sekaligus. Pola penutup ini sama
                 dengan halaman detail lain (DetailTim). -->
            <CtaSection />
        </div>
    </CareerLayout>
</template>

<script setup>
import { Head } from '@inertiajs/vue3';
import { computed, nextTick, onMounted, onUnmounted, reactive, ref, watch } from 'vue';
import CareerLayout from './Layouts/CareerLayout.vue';
import LowonganCard from './components/LowonganCard.vue';
import CtaSection from './sections/CtaSection.vue';
import { daysLeft, deadlineLabel, formatDate, goToSection, observeReveal, stageTypeLabel, statusLabel } from '@utils/career/data';

defineOptions({ layout: null });

const props = defineProps({
    programMt: { type: Object, default: () => ({}) },
    hasMt: { type: Boolean, default: false },
    offices: { type: Array, default: () => [] },
});

const mt = computed(() => props.programMt || {});
const hasMt = computed(() => props.hasMt);
const offices = computed(() => props.offices || []);
const posisi = computed(() => mt.value.posisi || []);

// ── Bantu tanggal ──────────────────────────────────────────────────────────
// Dulu bagian ini milik kartu Jendela Pendaftaran. Kartunya sudah dihapus —
// status & sisa hari kini hanya dihitung sekali oleh deadlineLabel()/daysLeft()
// di hero — tapi waktuMs() tetap dipakai penyusun Jadwal Kegiatan di bawah.
/** 'Y-m-d H:i' / 'Y-m-d' → epoch ms. Spasi diganti 'T' agar Safari ikut baca. */
function waktuMs(s) {
    if (!s) return null;
    const t = Date.parse(String(s).replace(' ', 'T'));
    return Number.isNaN(t) ? null : t;
}

// ── Jadwal kegiatan ────────────────────────────────────────────────────────
const BULAN_PENDEK = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

/**
 * Agenda ditandai lewat / berlangsung / akan datang. Tanpa penanda ini seluruh
 * daftar terbaca sama rata, padahal separuhnya bisa saja sudah berlalu.
 */
const agenda = computed(() => {
    const hariIni = new Date();
    const kini = new Date(hariIni.getFullYear(), hariIni.getMonth(), hariIni.getDate()).getTime();

    return (mt.value.jadwal || []).map((j, i) => {
        const mulai = waktuMs(j.mulai);
        let selesai = waktuMs(j.selesai);

        // Tanggal selesai yang mendahului tanggal mulai adalah salah isi di
        // Master Jadwal. Rentangnya tidak dipajang ("27 Jul – 06 Jul" hanya
        // membingungkan pelamar); yang ditampilkan cukup tanggal mulainya.
        const rusak = mulai !== null && selesai !== null && selesai < mulai;
        if (rusak) selesai = null;

        const akhir = selesai ?? mulai;
        let keadaan = 'akan';
        if (akhir !== null && akhir < kini) keadaan = 'lewat';
        else if (mulai !== null && mulai <= kini && (akhir === null || akhir >= kini)) keadaan = 'kini';

        // Bagian tanggal 'Y-m-d' dibaca langsung sebagai teks (bukan lewat objek
        // Date) supaya tidak tergeser zona waktu — dipakai untuk blok tanggal
        // ala tiket ("01 / JUL") dan pengelompokan per bulan di bawah.
        const [tahunS, bulanS, hariS] = (j.mulai || '').split('-');
        const bulanIdx = bulanS ? Number(bulanS) - 1 : null;

        return {
            ...j,
            keadaan,
            tanggal: rusak ? formatDate(j.mulai) : j.tanggal,
            hariAngka: hariS ? Number(hariS) : null,
            bulanSingkat: bulanIdx !== null ? BULAN_PENDEK[bulanIdx] : null,
            kunciBulan: tahunS && bulanS ? `${tahunS}-${bulanS}` : null,
            labelBulan: tahunS && bulanIdx !== null ? `${BULAN_PENDEK[bulanIdx]} ${tahunS}` : 'Tanpa Tanggal',
            // Nomor urut ABSOLUT dari seluruh jadwal (bukan dari yang sedang
            // ditampilkan) — supaya saat riwayat disembunyikan, kartu yang
            // tersisa tetap menunjukkan angka aslinya (mis. mulai dari "04")
            // dan bukan diam-diam dihitung ulang jadi "01". Itu justru
            // menegaskan pesan tombol riwayat: ada beberapa nomor sebelumnya
            // yang disembunyikan, bukan hilang.
            urutan: i + 1,
        };
    });
});
const agendaLewat = computed(() => agenda.value.filter((j) => j.keadaan === 'lewat').length);
/** Lebar padding nomor urut — "01".."13" bila totalnya dua digit, "1".."7" bila cukup satu. */
const agendaLebar = computed(() => String(agenda.value.length).length);

/**
 * Kegiatan yang sudah lewat DISEMBUNYIKAN di balik satu tombol, bukan digulir
 * pelan-pelan. Alasannya sama dengan Jendela Pendaftaran: pertanyaan pelamar
 * bukan "apa saja agendanya" tapi "apa yang masih relevan buatku sekarang".
 * Tanpa ini, program yang sudah berjalan lama (7 dari 7 lewat) menampilkan
 * tembok baris abu-abu duluan sebelum apa pun yang berguna.
 */
const riwayatBuka = ref(false);
const agendaAktif = computed(() => agenda.value.filter((j) => j.keadaan !== 'lewat'));
const agendaTampil = computed(() => (riwayatBuka.value ? agenda.value : agendaAktif.value));

/**
 * Dikelompokkan per bulan — supaya jadwal terbaca sebagai KALENDER (kapan
 * sesuatu terjadi), bukan sekadar daftar bernomor seperti Tahapan Seleksi di
 * bawahnya. Agenda sudah datang terurut kronologis dari server, jadi
 * pengelompokan cukup mendeteksi pergantian bulan pada barisan yang berjalan.
 */
const agendaKelompok = computed(() => {
    const kelompok = [];
    let terakhir = null;
    for (const j of agendaTampil.value) {
        const kunci = j.kunciBulan ?? '-';
        if (!terakhir || terakhir.kunci !== kunci) {
            terakhir = { kunci, label: j.labelBulan, item: [] };
            kelompok.push(terakhir);
        }
        terakhir.item.push(j);
    }
    return kelompok;
});

/** Kegiatan paling relevan: yang sedang berlangsung, atau yang terdekat berikutnya. */
const agendaBerikutnya = computed(() => agendaAktif.value[0] ?? null);

function relatifHari(iso, { kalauSekarang, kalauBesok, kalauN }) {
    const d = daysLeft(iso);
    if (d <= 0) return kalauSekarang;
    if (d === 1) return kalauBesok;
    return kalauN(d);
}

/** Teks singkat di chip "Agenda berikutnya" — kapan mulainya, atau kapan berakhir bila sedang berlangsung. */
const agendaBerikutnyaRelatif = computed(() => {
    const a = agendaBerikutnya.value;
    if (!a) return '';
    if (a.keadaan === 'kini') {
        if (!a.selesai) return 'Berlangsung';
        return relatifHari(a.selesai, {
            kalauSekarang: 'Berakhir hari ini',
            kalauBesok: 'Berakhir besok',
            kalauN: (d) => `Berakhir ${d} hari lagi`,
        });
    }
    return relatifHari(a.mulai, {
        kalauSekarang: 'Hari ini',
        kalauBesok: 'Besok',
        kalauN: (d) => `${d} hari lagi`,
    });
});

// ── Papan posisi: cari, filter faset, urutkan, mode tampilan ───────────────
const cari = ref('');
const urut = ref('nama');
const mode = ref('grid');
const filterBuka = ref(true);

const KUNCI = [
    { key: 'departemen', label: 'Departemen', ambil: (p) => p.departemen },
    { key: 'lokasi', label: 'Lokasi Penempatan', ambil: (p) => p.lokasi },
    { key: 'level', label: 'Level Posisi', ambil: (p) => p.level },
    { key: 'tipeKerja', label: 'Tipe Kerja', ambil: (p) => p.tipeKerja },
];

const pilihan = reactive({ departemen: [], lokasi: [], level: [], tipeKerja: [] });
const lipat = reactive({ departemen: false, lokasi: false, level: false, tipeKerja: false });

/**
 * Faset hanya ditawarkan bila benar-benar MEMBEDAKAN. Satu program yang seluruh
 * posisinya di Palembang tidak perlu filter lokasi berisi satu pilihan — itu
 * kontrol yang tidak pernah mengubah apa pun.
 */
const faset = computed(() =>
    KUNCI.map((k) => {
        const peta = new Map();
        for (const p of posisi.value) {
            const v = (k.ambil(p) || '').trim();
            if (!v || v === '—') continue;
            peta.set(v, (peta.get(v) || 0) + 1);
        }
        return {
            ...k,
            opsi: [...peta.entries()].map(([nama, count]) => ({ nama, count })).sort((a, b) => b.count - a.count || a.nama.localeCompare(b.nama)),
        };
    }).filter((f) => f.opsi.length > 1),
);
const adaFaset = computed(() => faset.value.length > 0);

const jumlahFilterAktif = computed(() => KUNCI.reduce((n, k) => n + pilihan[k.key].length, 0));

function lepas(key, nilai) {
    pilihan[key] = pilihan[key].filter((v) => v !== nilai);
}
function resetFilter() {
    for (const k of KUNCI) pilihan[k.key] = [];
}
function resetSemua() {
    resetFilter();
    cari.value = '';
}

// Pilihan yang fasetnya hilang (mis. data berubah) ikut dibersihkan supaya
// daftar tidak pernah kosong karena filter yang tak terlihat lagi.
watch(faset, (f) => {
    const aktif = new Set(f.map((x) => x.key));
    for (const k of KUNCI) if (!aktif.has(k.key) && pilihan[k.key].length) pilihan[k.key] = [];
});

const hasil = computed(() => {
    const q = cari.value.trim().toLowerCase();
    const daftar = posisi.value.filter((p) => {
        for (const k of KUNCI) {
            const dipilih = pilihan[k.key];
            if (dipilih.length && !dipilih.includes((k.ambil(p) || '').trim())) return false;
        }
        if (!q) return true;
        return [p.posisi, p.departemen, p.lokasi, p.level, p.tipeKerja, p.ringkasan, ...(p.skill || []), ...(p.benefit || [])]
            .join(' ')
            .toLowerCase()
            .includes(q);
    });

    // Dulu posisi ber-kuota-penuh selalu ditenggelamkan ke bawah dan dua dari
    // tiga opsi urut berhitung kursi. Tanpa data kuota, urutannya kini murni
    // alfabetis pada kolom yang memang terdata — posisi bernama sama diurut
    // lanjutan lewat nama agar hasilnya stabil, bukan acak antar-render.
    const kunci = { departemen: 'departemen', lokasi: 'lokasi' }[urut.value];
    return [...daftar].sort((a, b) => {
        if (kunci) {
            const beda = (a[kunci] || '').localeCompare(b[kunci] || '');
            if (beda !== 0) return beda;
        }
        return (a.posisi || '').localeCompare(b.posisi || '');
    });
});

function keDaftarPosisi() {
    document.getElementById('posisi')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

let revealObs = null;
onMounted(() => {
    // Filter memakan ruang di layar sempit — di sana dimulai tertutup.
    if (typeof window !== 'undefined' && window.innerWidth < 992) filterBuka.value = false;
    nextTick(() => (revealObs = observeReveal()));
});
onUnmounted(() => revealObs?.disconnect());
</script>

<style scoped>
/* Metrik di berkas ini SENGAJA menyalin token /karir/lowongan (SemuaLowongan.vue)
   — radius 20px, border #e6e9f3, shadow 0 10px 30px rgba(15,23,42,.05/.06),
   kontrol 12-13px — supaya kedua halaman terasa satu produk, bukan dua. */
.bi {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    line-height: 1;
    vertical-align: middle;
}

.mtd {
    min-height: 100vh;
    background: linear-gradient(180deg, #f3f2fd 0%, #eef1fb 46%, #eaf0fb 100%);
}

/* ═══════════════════ HERO ═══════════════════
   Sengaja TANPA kartu/panel — cuma badge kecil untuk status, sisanya teks
   polos rata kiri dengan jarak yang lapang. Statistik ditulis sebagai satu
   baris teks dipisah titik (::after), bukan pil/kotak berwarna. */
.mtd-hero {
    position: relative;
    overflow: hidden;
    padding: 7rem 1.5rem 4rem;
}
.mtd-hero__glow {
    position: absolute;
    border-radius: 50%;
    filter: blur(10px);
    pointer-events: none;
}
/* Kedua bola cahaya sengaja MUAT SELURUHNYA di dalam kotak hero (tidak ada
   sisi yang menjorok keluar batas bawah/atas). `.mtd-hero` memakai
   overflow:hidden, dan gradasi radial ini baru benar-benar transparan di luar
   radius 70% — kalau boksnya sampai terpotong SEBELUM mencapai radius itu,
   yang terlihat bukan cahaya yang meredup lembut, tapi tepi lurus yang keras
   ("terpotong"), terutama pada bola bawah yang berbatasan langsung dengan
   konten polos di bawah hero. */
.mtd-hero__glow--a {
    top: 10px;
    right: 10%;
    width: 340px;
    height: 340px;
    background: radial-gradient(circle at 30% 30%, rgba(139, 92, 246, 0.16), rgba(139, 92, 246, 0) 70%);
}
.mtd-hero__glow--b {
    bottom: 10px;
    left: 5%;
    width: 340px;
    height: 340px;
    background: radial-gradient(circle at 60% 40%, rgba(99, 102, 241, 0.12), rgba(99, 102, 241, 0) 70%);
}
.mtd-hero__in {
    position: relative;
    max-width: 1200px;
    margin: 0 auto;
}

.mtd-back {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    appearance: none;
    cursor: pointer;
    font-family: inherit;
    font-size: 12.5px;
    font-weight: 800;
    padding: 8px 14px;
    margin-bottom: 28px;
    border-radius: 12px;
    border: 1px solid #e6e9f3;
    background: rgba(255, 255, 255, 0.9);
    color: #64748b;
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}
.mtd-back:hover {
    border-color: rgba(139, 92, 246, 0.3);
    color: #6366f1;
    background: #fff;
    transform: translateX(-3px);
}

/* Kolom nyaman di tengah — rata kiri selebar 1200px menyisakan separuh
   halaman kosong pada layar lebar dan terbaca sebagai "rusak", bukan lapang.
   Margin kiri-kanan yang simetris di sini terlihat sengaja. */
.mtd-hero__center {
    max-width: 90%;
    margin: 0 auto;
    text-align: center;
}

.mtd-hero__badges {
    display: flex;
    justify-content: center;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
}
.mtd-eyebrow {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 5px 13px;
    border-radius: 999px;
    background: rgba(245, 158, 11, 0.12);
    border: 1px solid rgba(245, 158, 11, 0.32);
    color: #b45309;
    font-size: 0.72rem;
    font-weight: 900;
    letter-spacing: 0.06em;
    text-transform: uppercase;
}
.mtd-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 4px 11px;
    border-radius: 999px;
    font-size: 0.7rem;
    font-weight: 800;
    white-space: nowrap;
    border: 1px solid transparent;
}
.mtd-badge--batch {
    color: #b45309;
    background: rgba(245, 158, 11, 0.14);
    border-color: rgba(245, 158, 11, 0.4);
}
.mtd-badge--loc {
    color: #0284c7;
    background: #f0f9ff;
    border-color: rgba(14, 165, 233, 0.25);
}
.mtd-badge--open {
    color: #047857;
    background: rgba(16, 185, 129, 0.12);
    border-color: rgba(16, 185, 129, 0.3);
}

.mtd-hero__center h1 {
    margin: 18px 0 0;
    font-size: clamp(1.9rem, 3.6vw, 2.7rem);
    font-weight: 800;
    color: #0f172a;
    letter-spacing: -0.03em;
    line-height: 1.16;
    text-wrap: balance;
}
.mtd-hero__center p {
    margin: 14px auto 0;
    font-size: 15px;
    color: #64748b;
    line-height: 1.68;
}

/* Statistik jadi satu baris teks dipisah titik — bukan pil/kartu. */
.mtd-hero__meta {
    display: flex;
    justify-content: center;
    flex-wrap: wrap;
    align-items: center;
    margin-top: 24px;
    font-size: 14px;
    font-weight: 600;
    color: #475569;
}
.mtd-hero__meta-item {
    position: relative;
    display: inline-flex;
    align-items: center;
    gap: 7px;
}
.mtd-hero__meta-item i {
    color: #8b5cf6;
    font-size: 14px;
}
.mtd-hero__meta-item:not(:last-child)::after {
    content: '·';
    margin: 0 14px;
    color: #cbd5e1;
    font-weight: 700;
}
.mtd-hero__meta-item.is-urgent {
    color: #be123c;
    font-weight: 800;
}
.mtd-hero__meta-item.is-urgent i {
    color: #e11d48;
}
.mtd-hero__meta-pulse {
    position: absolute;
    top: -1px;
    right: -11px;
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: #e11d48;
    box-shadow: 0 0 0 2px #fff;
    animation: mtdPulse 1.8s ease-in-out infinite;
}

.mtd-cta {
    appearance: none;
    cursor: pointer;
    font-family: inherit;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    margin-top: 26px;
    font-size: 13.5px;
    font-weight: 800;
    color: #fff;
    background: linear-gradient(135deg, #8b5cf6, #6366f1);
    border: none;
    border-radius: 12px;
    padding: 13px 24px;
    box-shadow: 0 10px 24px rgba(99, 102, 241, 0.3);
    transition: transform 0.18s ease, box-shadow 0.18s ease;
}
.mtd-cta:hover {
    transform: translateY(-2px);
    box-shadow: 0 14px 30px rgba(99, 102, 241, 0.4);
}

/* ═══════════════════ TENTANG PROGRAM ═══════════════════ */
.mtd-about {
    max-width: 1200px;
    margin: 0 auto;
    padding: 1.4rem 1.5rem 0;
}

.mtd-sec__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 1rem;
}
.mtd-sec__head h2 {
    margin: 0;
    font-size: 20px;
    font-weight: 800;
    color: #0f172a;
    letter-spacing: -0.02em;
    display: flex;
    align-items: center;
    gap: 10px;
}
.mtd-sec__head h2 .bi {
    color: #6366f1;
}
.mtd-sec__count {
    font-size: 12px;
    font-weight: 800;
    color: #6366f1;
    background: rgba(99, 102, 241, 0.1);
    border-radius: 999px;
    padding: 6px 14px;
    white-space: nowrap;
}
.mtd-sec__note {
    font-size: 12px;
    font-weight: 700;
    color: #94a3b8;
}

.mtd-subcards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(285px, 1fr));
    gap: 1.05rem;
    align-items: start;
}

.mtd-card {
    background: rgba(255, 255, 255, 0.94);
    border: 1px solid #e6e9f3;
    border-radius: 20px;
    padding: 18px;
    box-shadow: 0 10px 30px rgba(15, 23, 42, 0.05);
}
.mtd-jadwal,
.mtd-card--tahapan {
    margin-top: 1.05rem;
}
.mtd-card h3 {
    display: flex;
    align-items: center;
    gap: 8px;
    margin: 0 0 14px;
    font-size: 14px;
    font-weight: 800;
    color: #0f172a;
}
.mtd-card h3 .bi {
    color: #6366f1;
}
.mtd-card__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    padding-bottom: 10px;
    margin-bottom: 4px;
    border-bottom: 1px solid #f1f2f9;
}
.mtd-card__head h3 {
    margin: 0;
}
.mtd-card__tag {
    font-size: 10.5px;
    font-weight: 800;
    color: #94a3b8;
    background: #eef0f7;
    border-radius: 8px;
    padding: 2px 7px;
    white-space: nowrap;
}

/* ── Jadwal Kegiatan: sorotan "apa yang relevan sekarang" ──
   Gradasi ungu + chip kanan. Bahasa visual ini dulu dipinjam dari kartu
   Jendela Pendaftaran yang kini sudah dihapus; dipertahankan karena kartu
   Tahapan Seleksi memakai keluarga yang sama. */
.mtd-jw__next {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-top: 14px;
    padding: 15px 16px;
    border-radius: 15px;
    border: 1px solid rgba(99, 102, 241, 0.2);
    background: linear-gradient(135deg, rgba(139, 92, 246, 0.09), rgba(99, 102, 241, 0.04));
}
.mtd-jw__next.is-kini {
    border-color: rgba(16, 185, 129, 0.28);
    background: linear-gradient(135deg, rgba(16, 185, 129, 0.1), rgba(16, 185, 129, 0.03));
}
.mtd-jw__next-pulse {
    flex-shrink: 0;
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background: #6366f1;
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2);
}
.mtd-jw__next.is-kini .mtd-jw__next-pulse {
    background: #10b981;
    box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.22);
    animation: mtdPulse 1.8s ease-in-out infinite;
}
@keyframes mtdPulse {
    0%, 100% {
        box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.22);
    }
    50% {
        box-shadow: 0 0 0 6px rgba(16, 185, 129, 0.1);
    }
}
.mtd-jw__next-body {
    flex: 1;
    min-width: 0;
}
.mtd-jw__next-body small {
    display: block;
    font-size: 10.5px;
    font-weight: 800;
    letter-spacing: 0.03em;
    text-transform: uppercase;
    color: #6d28d9;
}
.mtd-jw__next.is-kini .mtd-jw__next-body small {
    color: #047857;
}
.mtd-jw__next-body strong {
    display: -webkit-box;
    margin-top: 3px;
    font-size: 14px;
    font-weight: 800;
    color: #0f172a;
    line-height: 1.32;
    /* Kolom kini cukup lebar untuk dua baris — nama agenda tak perlu lagi
       dipotong elipsis setelah beberapa kata. */
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
.mtd-jw__next-chip {
    flex-shrink: 0;
    font-size: 11.5px;
    font-weight: 800;
    color: #4338ca;
    background: rgba(255, 255, 255, 0.8);
    border-radius: 999px;
    padding: 5px 11px;
    white-space: nowrap;
}
.mtd-jw__next.is-kini .mtd-jw__next-chip {
    color: #047857;
}

/* Satu tombol yang sekaligus menyatakan keadaan ("selesai") DAN membuka
   riwayatnya — bukan lagi kotak keterangan + tombol terpisah yang berbicara
   dua kali tentang hal yang sama. */
.mtd-jw__kosong {
    appearance: none;
    cursor: pointer;
    font-family: inherit;
    width: 100%;
    display: flex;
    align-items: center;
    gap: 10px;
    margin-top: 14px;
    padding: 13px 15px;
    border-radius: 15px;
    background: #f8fafc;
    border: 1px solid #e6e9f3;
    font-size: 12.5px;
    font-weight: 700;
    color: #334155;
    text-align: left;
    transition: background 0.16s ease, border-color 0.16s ease;
}
.mtd-jw__kosong:hover {
    background: #f1f5f9;
    border-color: #d7dcec;
}
.mtd-jw__kosong[aria-expanded='true'] {
    border-color: rgba(99, 102, 241, 0.28);
    background: rgba(99, 102, 241, 0.05);
}
.mtd-jw__kosong > .bi:first-child {
    flex-shrink: 0;
    font-size: 16px;
    color: #10b981;
}
.mtd-jw__kosong span {
    flex: 1;
    min-width: 0;
}
.mtd-jw__kosong-chev {
    flex-shrink: 0;
    font-size: 11px;
    color: #94a3b8;
}

/* Versi ringkas: dipakai saat riwayat cuma pelengkap (masih ada agenda
   mendatang di atas), jadi cukup pil kecil — bukan tombol selebar kartu. */
.mtd-jw__toggle {
    appearance: none;
    cursor: pointer;
    font-family: inherit;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    margin-top: 12px;
    padding: 7px 12px;
    border-radius: 999px;
    border: 1px solid #e6e9f3;
    background: #f8fafc;
    font-size: 11px;
    font-weight: 700;
    color: #64748b;
    transition: all 0.16s ease;
}
.mtd-jw__toggle:hover {
    color: #6366f1;
    border-color: rgba(99, 102, 241, 0.3);
    background: rgba(99, 102, 241, 0.07);
}

.mtd-callout {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    background: rgba(99, 102, 241, 0.07);
    border: 1px solid rgba(99, 102, 241, 0.18);
    border-radius: 14px;
    padding: 12px 15px;
    margin-bottom: 1.05rem;
    font-size: 12.5px;
    font-weight: 600;
    color: #4338ca;
}
.mtd-callout .bi {
    margin-top: 1px;
}

.mtd-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}
.mtd-tags span {
    font-size: 11.5px;
    font-weight: 700;
    color: #4f46e5;
    background: rgba(99, 102, 241, 0.08);
    border: 1px solid rgba(99, 102, 241, 0.16);
    border-radius: 8px;
    padding: 5px 11px;
}

.mtd-list {
    list-style: none;
    margin: 0;
    padding: 0;
    display: grid;
    gap: 8px;
}
.mtd-list li {
    display: flex;
    align-items: flex-start;
    gap: 9px;
    font-size: 12.5px;
    line-height: 1.6;
    color: #475569;
}
.mtd-list .bi {
    color: #10b981;
    margin-top: 3px;
}


/* ── Jadwal kegiatan — kartu "tiket" berkelompok per bulan ──
   Sengaja TIDAK dibuat seperti Tahapan Seleksi (badge nomor + urutan) meski
   keduanya sama-sama grid kartu selebar halaman — dua pertanyaan yang beda:
   Tahapan Seleksi menjawab "urutan apa", jadwal ini menjawab "kapan". Bentuk
   blok tanggal ala tiket/boarding-pass + pengelompokan per bulan menegaskan
   bahwa ini kalender, bukan sekadar daftar bernomor kedua kalinya. */
/* Grid kelompok-per-bulan. `.mtd-jcal__grp` SENGAJA tanpa aturan sendiri:
   dia cuma grid-item di sini, jaraknya sudah diatur `gap` ini. */
.mtd-jcal {
    margin-top: 18px;
    display: grid;
    gap: 22px;
}
.mtd-jcal__bulan {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 10px;
    font-size: 11px;
    font-weight: 800;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: #7c3aed;
}
.mtd-jcal__bulan::after {
    content: '';
    flex: 1;
    height: 1px;
    background: linear-gradient(90deg, rgba(139, 92, 246, 0.28), rgba(139, 92, 246, 0));
}

.mtd-jcal__grid {
    list-style: none;
    margin: 0;
    padding: 0;
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(255px, 1fr));
    gap: 10px;
}
.mtd-jcal__grid li {
    position: relative;
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 14px 10px 10px;
    border-radius: 15px;
    border: 1px solid #eef1f8;
    background: #f8fafc;
    transition: border-color 0.16s ease, background 0.16s ease, box-shadow 0.16s ease;
}

/* Blok tanggal ala boarding-pass — angka besar sebagai jangkar visual utama,
   karena di kartu ini "kapan" jauh lebih penting daripada urutan ke berapa. */
.mtd-jcal__date {
    flex-shrink: 0;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    width: 48px;
    height: 48px;
    border-radius: 12px;
    background: #fff;
    border: 1.5px solid #e2e8f0;
}
.mtd-jcal__date b {
    font-size: 16px;
    font-weight: 800;
    color: #1e293b;
    line-height: 1.05;
}
.mtd-jcal__date small {
    margin-top: 1px;
    font-size: 8.5px;
    font-weight: 800;
    letter-spacing: 0.05em;
    text-transform: uppercase;
    color: #94a3b8;
}

.mtd-jcal__body {
    min-width: 0;
    flex: 1;
}
.mtd-jcal__grid strong {
    display: block;
    font-size: 12.5px;
    font-weight: 800;
    color: #0f172a;
    line-height: 1.32;
}
.mtd-jcal__range {
    display: block;
    margin-top: 2px;
    font-size: 10.5px;
    color: #94a3b8;
}
.mtd-jcal__check {
    flex-shrink: 0;
    font-size: 14px;
    color: #10b981;
}

/* Sudah lewat: blok tanggal & teks diredupkan, tanda centang jadi satu-satunya
   aksen — mata langsung tahu ini riwayat, bukan sesuatu yang perlu disiapkan. */
.mtd-jcal__grid li.is-lewat {
    background: #f8fafc;
}
.mtd-jcal__grid li.is-lewat .mtd-jcal__date {
    background: #f1f5f9;
    border-color: #e6e9f3;
}
.mtd-jcal__grid li.is-lewat .mtd-jcal__date b,
.mtd-jcal__grid li.is-lewat .mtd-jcal__date small {
    color: #b0b9c9;
}
.mtd-jcal__grid li.is-lewat strong {
    color: #94a3b8;
    font-weight: 700;
}
.mtd-jcal__grid li.is-lewat .mtd-jcal__range {
    color: #c1c8d6;
}

/* Sedang berlangsung: blok tanggalnya sendiri yang menyala, bukan cuma kartu
   di sekitarnya — supaya yang pertama tertangkap mata adalah TANGGALNYA. */
.mtd-jcal__grid li.is-kini {
    border-color: rgba(99, 102, 241, 0.3);
    box-shadow: 0 8px 20px rgba(99, 102, 241, 0.14);
}
.mtd-jcal__grid li.is-kini .mtd-jcal__date {
    background: linear-gradient(135deg, #8b5cf6, #6366f1);
    border-color: transparent;
    box-shadow: 0 4px 12px rgba(99, 102, 241, 0.35);
}
.mtd-jcal__grid li.is-kini .mtd-jcal__date b,
.mtd-jcal__grid li.is-kini .mtd-jcal__date small {
    color: #fff;
}
.mtd-jcal__grid li.is-kini strong {
    color: #4338ca;
}
.mtd-jcal__kini {
    position: absolute;
    top: -7px;
    right: 12px;
    font-size: 9px;
    font-weight: 800;
    letter-spacing: 0.05em;
    text-transform: uppercase;
    color: #fff;
    background: linear-gradient(135deg, #8b5cf6, #6366f1);
    border-radius: 999px;
    padding: 2px 9px;
    box-shadow: 0 3px 8px rgba(99, 102, 241, 0.35);
}

/* Nomor seri ala tiket — kecil & di sudut, menjawab "kegiatan keberapa ini"
   tanpa merebut perhatian dari blok tanggal, dan sengaja tidak memakai gaya
   badge bulat besar Tahapan Seleksi. */
.mtd-jcal__no {
    position: absolute;
    top: -7px;
    left: 12px;
    font-family: 'JetBrains Mono', ui-monospace, monospace;
    font-size: 9px;
    font-weight: 700;
    letter-spacing: 0.01em;
    color: #94a3b8;
    background: #fff;
    border: 1px solid #e6e9f3;
    border-radius: 999px;
    padding: 2px 8px;
}
.mtd-jcal__grid li.is-lewat .mtd-jcal__no {
    color: #c3cadb;
    border-color: #eef1f8;
}
.mtd-jcal__grid li.is-kini .mtd-jcal__no {
    color: #4338ca;
    border-color: rgba(99, 102, 241, 0.3);
}

.mtd-jcal__grid li.is-akan .mtd-jcal__date {
    border-color: rgba(139, 92, 246, 0.45);
}

/* ── Tahapan seleksi ── */
.mtd-flow {
    list-style: none;
    margin: 0;
    padding: 0;
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
    gap: 10px;
}
.mtd-flow li {
    position: relative;
    display: flex;
    align-items: center;
    gap: 11px;
    background: #f8fafc;
    border: 1px solid #eef1f8;
    border-radius: 14px;
    padding: 12px 14px;
}
/* Penghubung hanya digambar di layar lebar, saat kartunya benar-benar berjajar.
   Jumlah kolom dipatok (bukan auto-fit) supaya posisi akhir baris bisa
   dipastikan — tanpa itu, langkah terakhir tiap baris menyisakan garis
   menggantung ke ruang kosong. */
@media (min-width: 992px) {
    .mtd-flow {
        grid-template-columns: repeat(4, minmax(0, 1fr));
    }
    .mtd-flow li:not(:last-child)::after {
        content: '';
        position: absolute;
        right: -10px;
        top: 50%;
        width: 10px;
        height: 2px;
        transform: translateY(-50%);
        background: linear-gradient(90deg, rgba(139, 92, 246, 0.45), rgba(139, 92, 246, 0.12));
    }
    .mtd-flow li:nth-child(4n)::after {
        content: none;
    }
}
@media (min-width: 1200px) {
    .mtd-flow {
        grid-template-columns: repeat(5, minmax(0, 1fr));
    }
    .mtd-flow li:nth-child(4n):not(:last-child)::after {
        content: '';
    }
    .mtd-flow li:nth-child(5n)::after {
        content: none;
    }
}
.mtd-flow__num {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    width: 28px;
    height: 28px;
    border-radius: 10px;
    font-size: 12px;
    font-weight: 800;
    color: #fff;
    background: linear-gradient(135deg, #8b5cf6, #6366f1);
    box-shadow: 0 6px 14px rgba(99, 102, 241, 0.28);
}
.mtd-flow__body {
    min-width: 0;
}
.mtd-flow strong {
    display: block;
    font-size: 12.5px;
    font-weight: 800;
    color: #0f172a;
    line-height: 1.32;
}
.mtd-flow small {
    font-size: 11px;
    color: #94a3b8;
}

/* ═══════════════════ TOOLBAR PAPAN POSISI ═══════════════════ */
.mtd-toolbar-wrap {
    position: sticky;
    top: 64px;
    z-index: 20;
    max-width: 1200px;
    margin: 0 auto;
    padding: 1.4rem 1.5rem 6px;
}
.mtd-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 14px;
    padding: 12px 16px;
    border-radius: 20px;
    background: rgba(255, 255, 255, 0.9);
    border: 1px solid rgba(226, 232, 240, 0.9);
    backdrop-filter: blur(16px);
    -webkit-backdrop-filter: blur(16px);
    box-shadow: 0 10px 30px rgba(15, 23, 42, 0.06);
    box-sizing: border-box;
}

.mtd-search {
    position: relative;
    display: flex;
    align-items: center;
    flex: 1;
    min-width: 240px;
    max-width: 420px;
}
.mtd-search .bi-search {
    position: absolute;
    left: 15px;
    color: #94a3b8;
    font-size: 14px;
    pointer-events: none;
}
.mtd-search input {
    width: 100%;
    padding: 10px 36px 10px 40px;
    border-radius: 12px;
    border: 1px solid #e6e9f3;
    background: rgba(255, 255, 255, 0.95);
    font-family: inherit;
    font-size: 13px;
    color: #334155;
    outline: none;
    transition: all 0.18s;
}
.mtd-search input:focus {
    border-color: #a5b4fc;
    background: #fff;
    box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.14);
}
.mtd-search__clear {
    position: absolute;
    right: 10px;
    appearance: none;
    border: none;
    background: #eef0f7;
    width: 22px;
    height: 22px;
    border-radius: 7px;
    color: #64748b;
    cursor: pointer;
    font-size: 10px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}
.mtd-search__clear:hover {
    background: rgba(139, 92, 246, 0.14);
    color: #6366f1;
}

.mtd-controls {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}
.mtd-tab {
    appearance: none;
    cursor: pointer;
    font-family: inherit;
    font-size: 13px;
    font-weight: 700;
    padding: 9px 15px;
    border-radius: 12px;
    border: 1px solid #e6e9f3;
    background: rgba(255, 255, 255, 0.9);
    color: #64748b;
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
    display: inline-flex;
    align-items: center;
    gap: 8px;
    white-space: nowrap;
}
.mtd-tab:hover:not(.is-on) {
    border-color: rgba(139, 92, 246, 0.3);
    color: #6366f1;
    background: #fff;
}
.mtd-tab.is-on {
    border-color: transparent;
    background: linear-gradient(135deg, #8b5cf6, #6366f1);
    color: #fff;
    box-shadow: 0 10px 24px rgba(99, 102, 241, 0.28);
}
.mtd-tab__n {
    font-size: 11px;
    font-weight: 800;
    padding: 2px 8px;
    border-radius: 8px;
    background: #eef0f7;
    color: #94a3b8;
}
.mtd-tab.is-on .mtd-tab__n {
    background: rgba(255, 255, 255, 0.24);
    color: #fff;
}

.mtd-sort {
    position: relative;
    display: inline-flex;
    align-items: center;
}
.mtd-sort__ico {
    position: absolute;
    left: 11px;
    color: #6366f1;
    font-size: 13px;
    pointer-events: none;
    z-index: 1;
}
.mtd-sort__lbl {
    position: absolute;
    left: 30px;
    font-size: 12px;
    font-weight: 700;
    color: #94a3b8;
    pointer-events: none;
    z-index: 1;
}
.mtd-sort__lbl::after {
    content: ':';
}
.mtd-sort__chev {
    position: absolute;
    right: 11px;
    color: #94a3b8;
    font-size: 10px;
    pointer-events: none;
    z-index: 1;
    transition: color 0.18s ease;
}
.mtd-sort__select {
    appearance: none;
    cursor: pointer;
    font-family: inherit;
    font-size: 12px;
    font-weight: 700;
    color: #334155;
    padding: 8px 30px 8px 90px;
    border-radius: 10px;
    border: 1px solid #e6e9f3;
    background: #fff;
    outline: none;
    transition: border-color 0.18s ease, box-shadow 0.18s ease, background 0.18s ease;
}
.mtd-sort:hover .mtd-sort__select {
    border-color: #a5b4fc;
    background: #fbfcff;
}
.mtd-sort:hover .mtd-sort__chev {
    color: #6366f1;
}
.mtd-sort__select:focus-visible {
    border-color: #818cf8;
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.16);
}
.mtd-sort__select option {
    color: #334155;
    font-weight: 600;
}

.mtd-view {
    display: inline-flex;
    padding: 3px;
    border-radius: 10px;
    background: #f1f5f9;
    border: 1px solid #e2e8f0;
}
.mtd-view__btn {
    appearance: none;
    border: none;
    background: none;
    cursor: pointer;
    padding: 5px 9px;
    border-radius: 7px;
    color: #94a3b8;
    font-size: 13px;
    transition: all 0.18s ease;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}
.mtd-view__btn.is-active {
    background: #fff;
    color: #6366f1;
    box-shadow: 0 4px 10px rgba(15, 23, 42, 0.08);
}

/* ═══════════════════ PAPAN POSISI ═══════════════════ */
.mtd-sec {
    max-width: 1200px;
    margin: 0 auto;
    padding: 1.6rem 1.5rem 3rem;
}
.mtd-layout {
    display: grid;
    grid-template-columns: minmax(0, 1fr);
    gap: 1.7rem;
    align-items: start;
}
@media (min-width: 992px) {
    .mtd-layout.is-filter-open {
        grid-template-columns: 268px minmax(0, 1fr);
    }
}
.mtd-aside {
    position: sticky;
    top: 168px;
    z-index: 15;
}
.mtd-aside__body {
    background: rgba(255, 255, 255, 0.94);
    border: 1px solid #e6e9f3;
    border-radius: 20px;
    padding: 18px;
    box-shadow: 0 10px 30px rgba(15, 23, 42, 0.05);
    height: fit-content;
    display: flex;
    flex-direction: column;
    gap: 12px;
}
.mtd-aside__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding-bottom: 10px;
    border-bottom: 1px solid #f1f2f9;
}
.mtd-aside__head > span {
    font-size: 14px;
    font-weight: 800;
    color: #0f172a;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}
.mtd-aside__head .bi-funnel-fill {
    color: #6366f1;
}
.mtd-aside__reset {
    appearance: none;
    border: none;
    background: none;
    cursor: pointer;
    font-family: inherit;
    font-size: 11.5px;
    font-weight: 800;
    color: #e11d48;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 4px 8px;
    border-radius: 8px;
    transition: background 0.16s ease;
}
.mtd-aside__reset:hover {
    background: rgba(225, 29, 72, 0.08);
}

.mtd-fgroup {
    border-bottom: 1px solid #f1f2f9;
    padding-bottom: 10px;
}
.mtd-fgroup:last-of-type {
    border-bottom: none;
    padding-bottom: 0;
}
.mtd-fgroup__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    cursor: pointer;
    padding: 6px 0;
    user-select: none;
}
.mtd-fgroup__title {
    font-size: 11.5px;
    font-weight: 800;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    color: #64748b;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}
.mtd-fgroup__badge {
    font-size: 10px;
    font-weight: 800;
    color: #fff;
    background: #6366f1;
    border-radius: 999px;
    padding: 1px 6px;
}
.mtd-fgroup__chev {
    font-size: 11px;
    color: #94a3b8;
    transition: transform 0.25s ease;
}
.mtd-fgroup.is-collapsed .mtd-fgroup__chev {
    transform: rotate(-90deg);
}
.mtd-fgroup__list {
    display: flex;
    flex-direction: column;
    gap: 4px;
    margin-top: 6px;
    max-height: 15rem;
    overflow-y: auto;
    overflow-x: hidden;
}

.mtd-check {
    display: flex;
    align-items: center;
    gap: 9px;
    min-width: 0;
    padding: 6px 8px;
    border-radius: 10px;
    cursor: pointer;
    transition: background 0.14s;
}
.mtd-check:hover {
    background: #f6f7fd;
}
.mtd-check input {
    position: absolute;
    opacity: 0;
    pointer-events: none;
}
.mtd-check__box {
    flex: 0 0 auto;
    width: 17px;
    height: 17px;
    border-radius: 5px;
    border: 1.5px solid #cbd5e1;
    background: #fff;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    font-size: 11px;
    transition: all 0.15s;
}
.mtd-check input:checked + .mtd-check__box {
    background: linear-gradient(135deg, #8b5cf6, #6366f1);
    border-color: transparent;
}
.mtd-check__lbl {
    flex: 1;
    min-width: 0;
    font-size: 12.5px;
    font-weight: 600;
    color: #475569;
    line-height: 1.35;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.mtd-check input:checked ~ .mtd-check__lbl {
    color: #4338ca;
    font-weight: 700;
}
.mtd-check__n {
    flex: 0 0 auto;
    font-size: 10.5px;
    font-weight: 800;
    color: #94a3b8;
    background: #eef0f7;
    border-radius: 8px;
    padding: 2px 7px;
}

.mtd-main {
    min-width: 0;
}
.mtd-aktif {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin: -0.6rem 0 1.1rem;
}
.mtd-chip {
    appearance: none;
    cursor: pointer;
    font-family: inherit;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    max-width: 16rem;
    overflow: hidden;
    white-space: nowrap;
    border: 1px solid rgba(139, 92, 246, 0.3);
    background: rgba(139, 92, 246, 0.1);
    border-radius: 999px;
    padding: 5px 12px;
    font-size: 11.5px;
    font-weight: 800;
    color: #6d28d9;
    transition: background 0.14s ease;
}
.mtd-chip:hover {
    background: rgba(139, 92, 246, 0.2);
}
.mtd-chip .bi:last-child {
    font-size: 9px;
    opacity: 0.7;
}
.mtd-chip--reset {
    color: #64748b;
    background: #eef0f7;
    border-color: #e2e8f0;
}
.mtd-chip--reset:hover {
    background: #e2e8f0;
}

.mtd-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 1.05rem;
}
.mtd-grid.is-list-grid {
    grid-template-columns: 1fr;
}

.mtd-empty {
    padding: 3.5rem 1rem;
    text-align: center;
    color: #64748b;
    font-size: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
}
.mtd-empty--col {
    flex-direction: column;
    gap: 8px;
}
.mtd-empty__ico {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 4.5rem;
    height: 4.5rem;
    border-radius: 1.25rem;
    background: rgba(99, 102, 241, 0.08);
    color: #6366f1;
    font-size: 2rem;
    margin-bottom: 6px;
}
.mtd-empty h3 {
    margin: 0;
    font-size: 1.15rem;
    font-weight: 800;
    color: #1e293b;
}
.mtd-empty p {
    margin: 0;
    max-width: 420px;
    font-size: 0.88rem;
    color: #64748b;
    line-height: 1.5;
}
.mtd-empty__btn {
    appearance: none;
    cursor: pointer;
    font-family: inherit;
    font-size: 13px;
    font-weight: 800;
    color: #fff;
    background: linear-gradient(135deg, #8b5cf6, #6366f1);
    border: none;
    border-radius: 12px;
    padding: 12px 24px;
    box-shadow: 0 10px 24px rgba(99, 102, 241, 0.3);
    transition: transform 0.18s ease, box-shadow 0.18s ease;
    margin-top: 8px;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}
.mtd-empty__btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 14px 30px rgba(99, 102, 241, 0.4);
}

/* ═══════════════════ RESPONSIF ═══════════════════ */
@media (max-width: 991.98px) {
    .mtd-aside {
        position: static;
    }
}

@media (max-width: 767.98px) {
    .mtd-hero {
        padding: 6.2rem 1rem 1.6rem;
    }
    .mtd-back {
        margin-bottom: 16px;
    }
    .mtd-hero__center {
        text-align: left;
    }
    .mtd-hero__badges,
    .mtd-hero__meta {
        justify-content: flex-start;
    }
    .mtd-hero__center p {
        margin: 14px 0 0;
        font-size: 13.5px;
    }
    .mtd-hero__meta {
        margin-top: 18px;
        font-size: 12.5px;
    }
    .mtd-hero__meta-item:not(:last-child)::after {
        margin: 0 10px;
    }
    .mtd-about {
        padding: 0.4rem 1rem 0;
    }
    .mtd-jcal {
        gap: 16px;
    }
    .mtd-jcal__grid {
        grid-template-columns: minmax(0, 1fr);
        gap: 0.6rem;
    }
    .mtd-card {
        padding: 15px;
        border-radius: 16px;
    }
    .mtd-toolbar-wrap {
        top: 58px;
        padding: 1rem 1rem 6px;
    }
    .mtd-toolbar {
        padding: 10px 12px;
        gap: 10px;
        border-radius: 16px;
    }
    .mtd-search {
        flex: 1 1 100%;
        max-width: none;
    }
    .mtd-controls {
        width: 100%;
        justify-content: space-between;
        gap: 8px;
    }
    .mtd-sec {
        padding: 1.2rem 1rem 2.4rem;
    }
    .mtd-grid {
        grid-template-columns: minmax(0, 1fr);
        gap: 0.7rem;
    }
}
</style>
