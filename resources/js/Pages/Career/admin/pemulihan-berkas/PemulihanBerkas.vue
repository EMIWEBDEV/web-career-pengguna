<!-- WEB CAREER — PEMULIHAN BERKAS: panel admin untuk mengunggah berkas kandidat
     yang hilang (CV, KTP, KK, sertifikat…).

     Bawaannya hanya REKOMENDASI SISTEM: isian yang di worklist tertulis "Belum
     diunggah" — wajib tapi kosong, atau namanya tercatat di jawaban tapi
     berkasnya tak pernah tersimpan. Pemegang hak Tambah Manual bisa memulihkan
     isian lain lewat tombol "Tambah Manual".

     Cakupan dibaca seperti worklist: per PROGRAM atau per LOWONGAN (MPP), lalu
     per ALUR. Bawaan alurnya "Semua alur" — berbeda dari papan worklist, sebab
     yang dicari di sini berkas yang hilang, dan menyembunyikan alur lama berarti
     menyembunyikan berkas yang justru harus dipulihkan. -->
<template>
    <Head title="Pemulihan Berkas" />
    <div class="wca pbk">
        <!-- ═══ KEPALA ═══ -->
        <header class="pbk-hero">
            <div class="pbk-hero__main">
                <span class="pbk-hero__ic" aria-hidden="true"><i class="bi bi-file-earmark-arrow-up-fill"></i></span>
                <div class="pbk-hero__txt">
                    <h1>Pemulihan Berkas</h1>
                    <p>
                        Unggah ulang berkas kandidat yang hilang: CV, KTP, KK, sertifikat. Sistem menandai isian yang di
                        worklist tertulis <b>“Belum diunggah”</b>.
                    </p>
                </div>
            </div>
            <div class="pbk-hero__acts">
                <button type="button" class="wca-btn wca-btn--ghost pbk-hero__btn" :disabled="memuat" @click="muat()">
                    <i class="bi bi-arrow-clockwise" :class="{ 'pbk-putar': memuat }"></i><span>Muat ulang</span>
                </button>
                <button v-if="hak.manual" type="button" class="wca-btn wca-btn--dark pbk-hero__btn" @click="bukaManual">
                    <i class="bi bi-plus-lg"></i><span>Tambah Manual</span>
                </button>
            </div>
        </header>

        <!-- ═══ CARA KERJA ═══ -->
        <ol class="pbk-alur" aria-label="Cara kerja">
            <li>
                <span class="pbk-alur__no">1</span>
                <div><b>Pilih cakupan</b><small>Per program atau per lowongan (MPP), lalu alurnya.</small></div>
            </li>
            <li>
                <span class="pbk-alur__no">2</span>
                <div><b>Periksa rekomendasi</b><small>Hanya isian yang benar-benar kosong di worklist.</small></div>
            </li>
            <li>
                <span class="pbk-alur__no">3</span>
                <div><b>Unggah + alasan</b><small>Tercatat atas nama kandidat. Berkas lama tidak pernah dihapus.</small></div>
            </li>
        </ol>

        <!-- ═══ CAKUPAN ═══ -->
        <section class="pbk-filter" aria-label="Cakupan">
            <div class="pbk-filter__row">
                <div class="wca-segt wca-segt--sm pbk-filter__seg" role="group" aria-label="Tampilkan per">
                    <button type="button" class="wca-segt__it" :class="{ on: mode === 'program' }" :aria-pressed="mode === 'program'" @click="gantiMode('program')">
                        <i class="bi bi-collection"></i> <span><span class="pbk-lebar">Per </span>Program</span>
                    </button>
                    <button type="button" class="wca-segt__it" :class="{ on: mode === 'loker' }" :aria-pressed="mode === 'loker'" @click="gantiMode('loker')">
                        <i class="bi bi-briefcase"></i> <span><span class="pbk-lebar">Per </span>Lowongan (MPP)</span>
                    </button>
                </div>
                <div class="pbk-filter__status" role="group" aria-label="Status lamaran">
                    <button type="button" class="pbk-pill" :class="{ on: status === 'AKTIF' }" :aria-pressed="status === 'AKTIF'" @click="gantiStatus('AKTIF')">
                        <i class="bi bi-lightning-charge-fill"></i> Berjalan &amp; diterima
                    </button>
                    <button type="button" class="pbk-pill" :class="{ on: status === 'SEMUA' }" :aria-pressed="status === 'SEMUA'" @click="gantiStatus('SEMUA')">
                        <i class="bi bi-archive"></i> Semua status
                    </button>
                </div>
            </div>

            <div class="pbk-filter__row">
                <div class="pbk-field">
                    <span class="pbk-field__ic" aria-hidden="true"><i class="bi" :class="mode === 'program' ? 'bi-collection-fill' : 'bi-briefcase-fill'"></i></span>
                    <span class="pbk-field__lbl">{{ mode === 'program' ? 'Program' : 'Lowongan' }}</span>
                    <el-select
                        v-if="mode === 'program'"
                        v-model="program"
                        class="pbk-field__sel"
                        filterable
                        placeholder="Semua program"
                        :aria-label="'Pilih program'"
                        @change="gantiCakupan"
                    >
                        <el-option value="" label="Semua program">
                            <span class="pbk-opt__nama">Semua program</span>
                            <span class="pbk-opt__n" :class="{ 'is-hot': totalPerlu('program') }">{{ totalPerlu('program') }} perlu</span>
                        </el-option>
                        <el-option v-for="p in data.program" :key="p.id" :value="p.id" :label="p.nama">
                            <span class="pbk-opt__dot" :style="{ background: p.warna || '#cbd5e1' }"></span>
                            <span class="pbk-opt__nama">{{ p.nama }}</span>
                            <span class="pbk-opt__n" :class="{ 'is-hot': p.perluPulih }">{{ p.perluPulih ? `${p.perluPulih} perlu` : 'lengkap' }}</span>
                        </el-option>
                    </el-select>
                    <el-select
                        v-else
                        v-model="loker"
                        class="pbk-field__sel"
                        filterable
                        placeholder="Semua lowongan"
                        :aria-label="'Pilih lowongan'"
                        @change="gantiCakupan"
                    >
                        <el-option value="" label="Semua lowongan">
                            <span class="pbk-opt__nama">Semua lowongan</span>
                            <span class="pbk-opt__n" :class="{ 'is-hot': totalPerlu('loker') }">{{ totalPerlu('loker') }} perlu</span>
                        </el-option>
                        <el-option v-for="l in data.loker" :key="l.kunci" :value="l.kunci" :label="labelLoker(l)">
                            <span class="pbk-opt__nama">{{ l.posisi || 'Tanpa posisi' }}</span>
                            <span class="pbk-opt__ref">{{ l.mppRef || 'tanpa MPP' }}</span>
                            <span class="pbk-opt__n" :class="{ 'is-hot': l.perluPulih }">{{ l.perluPulih ? `${l.perluPulih} perlu` : 'lengkap' }}</span>
                        </el-option>
                    </el-select>
                </div>

                <!-- Alur baru muncul bila cakupannya memang memuat lebih dari satu. -->
                <div v-if="data.alurOpsi.length > 1" class="pbk-field">
                    <span class="pbk-field__ic is-violet" aria-hidden="true"><i class="bi bi-signpost-split-fill"></i></span>
                    <span class="pbk-field__lbl">Alur</span>
                    <el-select v-model="alur" class="pbk-field__sel" :aria-label="'Pilih alur'" @change="muat()">
                        <el-option value="SEMUA" label="Semua alur">
                            <span class="pbk-opt__nama">Semua alur</span>
                            <span class="pbk-opt__n">digabung</span>
                        </el-option>
                        <el-option
                            v-for="a in data.alurOpsi"
                            :key="a.id"
                            :value="String(a.id)"
                            :label="`${a.nama}${versiGanda && a.versi ? ' · v' + a.versi : ''}`"
                        >
                            <span class="pbk-opt__nama">{{ a.nama }}</span>
                            <span v-if="versiGanda && a.versi" class="pbk-opt__versi">v{{ a.versi }}</span>
                            <span class="pbk-opt__n" :class="{ 'is-hot': a.perluPulih }">{{ a.perluPulih }}/{{ a.pelamar }}</span>
                        </el-option>
                    </el-select>
                </div>

                <label class="pbk-search">
                    <i class="bi bi-search" aria-hidden="true"></i>
                    <input
                        v-model="cari"
                        type="search"
                        placeholder="Cari kandidat atau nama berkas…"
                        aria-label="Cari di daftar rekomendasi"
                        enterkeyhint="search"
                        autocomplete="off"
                    />
                </label>
            </div>
        </section>

        <!-- ═══ RINGKASAN ═══ -->
        <section class="pbk-kpi" aria-label="Ringkasan" :aria-busy="memuat">
            <div class="pbk-kpi__it is-violet">
                <span class="pbk-kpi__ic" aria-hidden="true"><i class="bi bi-people-fill"></i></span>
                <div>
                    <b class="pbk-kpi__num">{{ angka(data.ringkas.kandidat) }}</b>
                    <span class="pbk-kpi__lbl">Kandidat perlu dipulihkan</span>
                </div>
            </div>
            <div class="pbk-kpi__it is-red">
                <span class="pbk-kpi__ic" aria-hidden="true"><i class="bi bi-file-earmark-x-fill"></i></span>
                <div>
                    <b class="pbk-kpi__num">{{ angka(data.ringkas.berkas) }}</b>
                    <span class="pbk-kpi__lbl">Berkas hilang<template v-if="data.ringkas.wajib"> · {{ data.ringkas.wajib }} wajib</template></span>
                </div>
            </div>
            <div class="pbk-kpi__it is-amber">
                <span class="pbk-kpi__ic" aria-hidden="true"><i class="bi bi-paperclip"></i></span>
                <div>
                    <b class="pbk-kpi__num">{{ angka(data.ringkas.namaTanpaBerkas) }}</b>
                    <span class="pbk-kpi__lbl">Nama tercatat, berkas tak ada</span>
                </div>
            </div>
            <div class="pbk-kpi__it is-slate">
                <span class="pbk-kpi__ic" aria-hidden="true"><i class="bi bi-radar"></i></span>
                <div>
                    <b class="pbk-kpi__num">{{ angka(data.ringkas.dipindai) }}</b>
                    <span class="pbk-kpi__lbl">Lamaran dipindai</span>
                </div>
            </div>
        </section>

        <div v-if="dimuat && !hak.unggah" class="wca-note wca-note--warn">
            <i class="bi bi-eye"></i>
            <span>Akun Anda hanya bisa <b>melihat</b> rekomendasi. Mengunggah berkas butuh hak <b>Tambah</b> di halaman ini.</span>
        </div>

        <!-- ═══ DAFTAR ═══ -->
        <div v-if="memuat" class="pbk-loadbar" aria-hidden="true"><span></span></div>

        <div v-if="!dimuat && memuat" class="pbk-grid" aria-busy="true">
            <div v-for="i in 4" :key="i" class="pbk-card pbk-sk">
                <div class="pbk-sk__hd"><span class="pbk-sk__av"></span><span class="pbk-sk__ln is-w60"></span></div>
                <span class="pbk-sk__ln"></span>
                <span class="pbk-sk__ln is-w80"></span>
                <span class="pbk-sk__blok"></span>
            </div>
        </div>

        <div v-else-if="galat && !dimuat" class="pbk-empty is-galat" role="alert">
            <span class="pbk-empty__ic" aria-hidden="true"><i class="bi bi-cloud-slash-fill"></i></span>
            <h3>Rekomendasi gagal dimuat</h3>
            <p>{{ galat }}</p>
            <button type="button" class="wca-btn wca-btn--dark" @click="muat()"><i class="bi bi-arrow-clockwise"></i> Coba lagi</button>
        </div>

        <div v-else-if="!data.kandidat.length" class="pbk-empty is-ok">
            <span class="pbk-empty__ic" aria-hidden="true"><i class="bi bi-patch-check-fill"></i></span>
            <h3>Semua berkas lengkap</h3>
            <p>
                Tidak ada berkas hilang pada <b>{{ angka(data.ringkas.dipindai) }} lamaran</b> di cakupan ini<template v-if="status === 'AKTIF'"> (berjalan &amp; diterima)</template>.
            </p>
            <p v-if="hak.manual" class="pbk-empty__cta">
                Menemukan berkas hilang yang tidak tertangkap sistem?
                <button type="button" class="wca-btn wca-btn--soft wca-btn--sm" @click="bukaManual"><i class="bi bi-plus-lg"></i> Tambah Manual</button>
            </p>
        </div>

        <div v-else-if="!tersaring.length" class="pbk-empty">
            <span class="pbk-empty__ic" aria-hidden="true"><i class="bi bi-search"></i></span>
            <h3>Tidak ada yang cocok</h3>
            <p>Tidak ada kandidat atau berkas yang cocok dengan <b>“{{ cari }}”</b>.</p>
            <button type="button" class="wca-btn wca-btn--soft wca-btn--sm" @click="cari = ''">Hapus pencarian</button>
        </div>

        <template v-else>
            <p v-if="cari" class="pbk-hasil">{{ tersaring.length }} dari {{ data.kandidat.length }} kandidat cocok.</p>
            <section v-for="g in kelompok" :key="g.kunci" class="pbk-group" :class="{ 'is-dim': memuat }">
                <header v-if="tampilKelompok" class="pbk-group__hd">
                    <span class="pbk-group__dot" :style="{ background: g.warna }" aria-hidden="true"></span>
                    <div class="pbk-group__txt">
                        <h2>{{ g.judul }}</h2>
                        <span>{{ g.sub }}</span>
                    </div>
                    <span class="pbk-group__n">{{ g.items.length }} kandidat · {{ g.berkas }} berkas</span>
                </header>

                <div class="pbk-grid">
                    <article v-for="k in g.items" :key="k.id" class="pbk-card">
                        <header class="pbk-card__hd">
                            <span class="pbk-card__av" :style="{ background: warnaOrang(k.nama) }" aria-hidden="true">{{ inisial(k.nama) }}</span>
                            <div class="pbk-card__who">
                                <h3>{{ k.nama }}</h3>
                                <p>{{ k.kode }}<template v-if="k.posisi"> · {{ k.posisi }}</template></p>
                            </div>
                            <span class="pbk-card__status" :class="`is-${(k.status || '').toLowerCase()}`">{{ labelStatus(k.status) }}</span>
                        </header>
                        <div class="pbk-card__meta">
                            <span v-if="k.totalTahap"><i class="bi bi-signpost-2"></i> Tahap {{ k.urutan }}/{{ k.totalTahap }}<template v-if="k.tahap"> · {{ k.tahap }}</template></span>
                            <span v-if="mode === 'program' && k.mppRef"><i class="bi bi-briefcase"></i> {{ k.mppRef }}</span>
                            <span v-if="mode === 'loker' || !tampilKelompok"><i class="bi bi-collection"></i> {{ k.program }}</span>
                            <span v-if="k.alur && alur === 'SEMUA' && data.alurOpsi.length > 1"><i class="bi bi-diagram-3"></i> {{ k.alur }}</span>
                        </div>

                        <ul class="pbk-card__items">
                            <li v-for="b in k.berkas" :key="b.kunci" class="pbk-item" :class="{ 'is-wajib': b.wajib }">
                                <span class="pbk-item__ic" aria-hidden="true">
                                    <i class="bi" :class="(b.aturan?.format || []).includes('PDF') ? 'bi-file-earmark-pdf' : 'bi-file-earmark-image'"></i>
                                </span>
                                <div class="pbk-item__main">
                                    <div class="pbk-item__top">
                                        <b>{{ b.label }}</b>
                                        <span v-if="b.wajib" class="pbk-item__tag is-red">Wajib</span>
                                        <span v-if="b.jenis === 'NAMA_TANPA_BERKAS'" class="pbk-item__tag is-amber" title="Kandidat sempat memilih berkas, tetapi berkasnya tidak pernah tersimpan di server">
                                            Nama tercatat
                                        </span>
                                    </div>
                                    <span v-if="lokasiIsian(b)" class="pbk-item__loc">{{ lokasiIsian(b) }}</span>
                                    <span class="pbk-item__src">{{ asalFormulir(b) }}</span>
                                    <span v-if="b.namaTercatat" class="pbk-item__nama"><i class="bi bi-paperclip"></i> {{ b.namaTercatat }}</span>
                                </div>
                                <button v-if="hak.unggah" type="button" class="pbk-item__btn" :aria-label="`Unggah ${b.label} untuk ${k.nama}`" @click="bukaUnggah(k, b)">
                                    <i class="bi bi-cloud-arrow-up-fill"></i><span>Unggah</span>
                                </button>
                            </li>
                        </ul>
                    </article>
                </div>
            </section>
        </template>

        <DialogUnggah
            :show="dlg.show"
            :konteks="dlg.konteks"
            :hak="hak"
            @close="tutupUnggah"
            @selesai="selesaiUnggah"
            @berubah="perluMuat = true"
        />

        <TambahManual
            v-if="hak.manual"
            ref="manual"
            :show="manualShow"
            :hak="hak"
            :opsi-program="data.program"
            :opsi-loker="data.loker"
            :awal="{ mode, program, loker }"
            @close="manualShow = false"
            @pilih="pilihManual"
        />

        <transition name="wca-toast">
            <div v-if="toast" class="wca-toast" :class="{ 'is-err': toastErr }" role="status">
                <i class="bi" :class="toastErr ? 'bi-exclamation-circle-fill' : 'bi-check-circle-fill'"></i> {{ toast }}
            </div>
        </transition>
    </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import DialogUnggah from './DialogUnggah.vue';
import TambahManual from './TambahManual.vue';
import { inisial, warnaOrang } from '@utils/orang';
import { asalFormulir, galatDari, labelStatus, lokasiIsian, muatDaftar } from '@utils/career/pemulihanBerkas';

const props = defineProps({
    hak: { type: Object, default: () => ({ unggah: false, manual: false, timpa: false }) },
});

const KOSONG = {
    program: [],
    loker: [],
    alurOpsi: [],
    ringkas: { dipindai: 0, kandidat: 0, berkas: 0, wajib: 0, namaTanpaBerkas: 0 },
    kandidat: [],
};

const hakKini = ref(props.hak || {});
const hak = computed(() => hakKini.value || {});

const mode = ref('program');
const program = ref('');
const loker = ref('');
const alur = ref('SEMUA');
const status = ref('AKTIF');
const cari = ref('');

const data = ref({ ...KOSONG });
const memuat = ref(false);
const dimuat = ref(false);
const galat = ref('');
let token = 0;

const dlg = reactive({ show: false, konteks: null });
const manualShow = ref(false);
const manual = ref(null);
const perluMuat = ref(false);
let dariManual = false;

const toast = ref('');
const toastErr = ref(false);
let tmToast = null;

// ── MUAT ────────────────────────────────────────────────────────────────────

async function muat({ diam = false } = {}) {
    const t = ++token;
    if (!diam) memuat.value = true;
    galat.value = '';
    try {
        const r = await muatDaftar({
            status: status.value,
            mode: mode.value,
            program: mode.value === 'program' ? program.value || undefined : undefined,
            loker: mode.value === 'loker' ? loker.value || undefined : undefined,
            alur: alur.value,
        });
        if (t !== token) return;

        // Pilihan dari tautan lama yang sudah tak ada di jangkauan akun ini:
        // dikembalikan ke "semua" alih-alih menampilkan kode acak di pemilih.
        const hilang = mode.value === 'program'
            ? program.value && !r.program.some((p) => p.id === program.value)
            : loker.value && !r.loker.some((l) => l.kunci === loker.value);
        if (hilang) {
            program.value = '';
            loker.value = '';
            return muat({ diam });
        }

        data.value = { ...KOSONG, ...r };
        alur.value = String(r.alurAktif ?? 'SEMUA');
        if (r.hak) hakKini.value = r.hak;
        dimuat.value = true;
        simpanUrl();
    } catch (e) {
        if (t !== token) return;
        galat.value = galatDari(e, 'Rekomendasi gagal dimuat.').pesan;
        if (dimuat.value) beritahu(galat.value, true);
    } finally {
        if (t === token) memuat.value = false;
    }
}

function gantiMode(m) {
    if (mode.value === m) return;
    mode.value = m;
    program.value = '';
    loker.value = '';
    alur.value = 'SEMUA';
    muat();
}

function gantiCakupan() {
    alur.value = 'SEMUA';
    muat();
}

function gantiStatus(s) {
    if (status.value === s) return;
    status.value = s;
    muat();
}

// ── URL — pilihan cakupan selamat dari muat ulang & bisa dibagikan ─────────

function bacaUrl() {
    const u = new URLSearchParams(window.location.search);
    mode.value = u.get('mode') === 'loker' ? 'loker' : 'program';
    program.value = mode.value === 'program' ? u.get('program') || '' : '';
    loker.value = mode.value === 'loker' ? u.get('loker') || '' : '';
    alur.value = u.get('alur') || 'SEMUA';
    status.value = u.get('status') === 'SEMUA' ? 'SEMUA' : 'AKTIF';
}

function simpanUrl() {
    const u = new URLSearchParams();
    if (mode.value === 'loker') u.set('mode', 'loker');
    if (program.value) u.set('program', program.value);
    if (loker.value) u.set('loker', loker.value);
    if (alur.value !== 'SEMUA') u.set('alur', alur.value);
    if (status.value !== 'AKTIF') u.set('status', status.value);
    const qs = u.toString();
    const url = window.location.pathname + (qs ? `?${qs}` : '');
    if (url !== window.location.pathname + window.location.search) {
        // state Inertia dipertahankan — hanya alamatnya yang diganti.
        window.history.replaceState(window.history.state, '', url);
    }
}

// ── TAMPILAN ────────────────────────────────────────────────────────────────

function normal(s) {
    return String(s || '')
        .normalize('NFD')
        .replace(/\p{Mn}/gu, '')
        .toLowerCase();
}

const tersaring = computed(() => {
    const q = normal(cari.value.trim());
    if (!q) return data.value.kandidat;

    return data.value.kandidat.filter((k) =>
        normal(
            [k.nama, k.kode, k.email, k.posisi, k.mppRef, ...k.berkas.map((b) => `${b.label} ${b.namaTercatat || ''} ${b.labelBaris || ''}`)].join(' '),
        ).includes(q),
    );
});

/** Kelompok hanya digambar saat cakupannya "semua" — satu program tak perlu judul. */
const tampilKelompok = computed(() => (mode.value === 'program' ? !program.value : !loker.value));

const KATEGORI = { REKRUTMEN: 'Rekrutmen', MT: 'Management Trainee', MAGANG: 'Magang' };

const kelompok = computed(() => {
    const peta = new Map();
    for (const k of tersaring.value) {
        const kunci = mode.value === 'program' ? k.programId : k.loker;
        if (!peta.has(kunci)) {
            peta.set(
                kunci,
                mode.value === 'program'
                    ? { kunci, judul: k.program, sub: KATEGORI[k.kategori] || k.kategori || '', warna: k.warna || '#a5b4fc', items: [], berkas: 0 }
                    : {
                          kunci,
                          judul: k.posisi || 'Tanpa posisi',
                          sub: [k.mppRef || 'Tanpa nomor MPP', k.program].join(' · '),
                          warna: k.warna || '#a5b4fc',
                          items: [],
                          berkas: 0,
                      },
            );
        }
        const g = peta.get(kunci);
        g.items.push(k);
        g.berkas += k.berkas.length;
    }

    return [...peta.values()].sort((a, b) => b.items.length - a.items.length || String(a.judul).localeCompare(String(b.judul)));
});

/** Versi alur disebut hanya bila dua alur bernama sama ikut tampil. */
const versiGanda = computed(() => {
    const nama = data.value.alurOpsi.map((a) => a.nama);

    return new Set(nama).size !== nama.length;
});

function totalPerlu(jenis) {
    return (jenis === 'program' ? data.value.program : data.value.loker).reduce((n, x) => n + (x.perluPulih || 0), 0);
}

function labelLoker(l) {
    return [l.posisi || 'Tanpa posisi', l.mppRef].filter(Boolean).join(' · ');
}

function angka(n) {
    return new Intl.NumberFormat('id-ID').format(Number(n) || 0);
}

// ── DIALOG ──────────────────────────────────────────────────────────────────

function bukaUnggah(k, b) {
    dariManual = false;
    dlg.konteks = { kandidat: { nama: k.nama, kode: k.kode, posisi: k.posisi }, butir: b, asal: 'rekomendasi' };
    dlg.show = true;
}

function bukaManual() {
    manualShow.value = true;
}

/** Langkah 3 Tambah Manual: dialog manual DITUKAR dengan dialog unggah. */
function pilihManual({ kandidat, butir }) {
    dariManual = true;
    manualShow.value = false;
    dlg.konteks = { kandidat, butir, asal: 'manual' };
    dlg.show = true;
}

function tutupUnggah() {
    dlg.show = false;
    if (perluMuat.value) {
        perluMuat.value = false;
        muat({ diam: true });
    }
    if (dariManual) {
        manualShow.value = true;
        manual.value?.segarkan();
    }
}

function selesaiUnggah({ pesan }) {
    const butir = dlg.konteks?.butir;
    dlg.show = false;
    perluMuat.value = false;
    beritahu(pesan);

    // Butir yang baru dipulihkan langsung hilang dari daftar; angka pastinya
    // menyusul dari server tanpa mengosongkan layar.
    if (butir && !dariManual) {
        data.value.kandidat = data.value.kandidat
            .map((k) => ({ ...k, berkas: k.berkas.filter((x) => x.kunci !== butir.kunci) }))
            .filter((k) => k.berkas.length);
    }
    muat({ diam: true });

    if (dariManual) {
        manualShow.value = true;
        manual.value?.segarkan();
    }
}

function beritahu(pesan, err = false) {
    if (tmToast) clearTimeout(tmToast);
    toast.value = pesan;
    toastErr.value = err;
    tmToast = setTimeout(() => (toast.value = ''), err ? 6000 : 4000);
}

onMounted(() => {
    bacaUrl();
    muat();
});

onBeforeUnmount(() => tmToast && clearTimeout(tmToast));
</script>

<style scoped>
.pbk {
    --pbk-ink: #0f172a;
    --pbk-muted: #64748b;
    --pbk-line: #e7e3fb;
    --pbk-soft: #f8f7ff;
    display: flex;
    flex-direction: column;
    gap: 16px;
}

/* ═══ KEPALA ═══ */
.pbk-hero {
    position: relative;
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    flex-wrap: wrap;
    padding: 20px 22px;
    border-radius: 22px;
    border: 1px solid var(--pbk-line);
    background:
        radial-gradient(circle at 92% -20%, rgba(139, 92, 246, 0.2), transparent 45%),
        radial-gradient(circle at 0% 120%, rgba(99, 102, 241, 0.12), transparent 40%),
        linear-gradient(135deg, #ffffff, #f7f5ff);
    box-shadow: 0 18px 40px -30px rgba(79, 70, 229, 0.55);
}
.pbk-hero__main {
    display: flex;
    align-items: center;
    gap: 16px;
    min-width: 0;
    flex: 1 1 420px;
}
.pbk-hero__ic {
    flex: none;
    width: 56px;
    height: 56px;
    border-radius: 18px;
    display: grid;
    place-items: center;
    font-size: 1.55rem;
    color: #fff;
    background: linear-gradient(135deg, #8b5cf6, #6366f1);
    box-shadow: 0 14px 30px rgba(99, 102, 241, 0.35);
}
.pbk-hero__txt {
    min-width: 0;
}
.pbk-hero h1 {
    margin: 0;
    font-size: 1.45rem;
    font-weight: 900;
    letter-spacing: -0.02em;
    color: #1e1b4b;
}
.pbk-hero p {
    margin: 4px 0 0;
    font-size: 0.87rem;
    line-height: 1.55;
    font-weight: 600;
    color: var(--pbk-muted);
    max-width: 640px;
}
.pbk-hero p b {
    color: #4338ca;
}
.pbk-hero__acts {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}
.pbk-hero__btn {
    min-height: 44px;
}
.pbk-putar {
    display: inline-block;
    animation: pbkPutar 0.8s linear infinite;
}
@keyframes pbkPutar {
    to { transform: rotate(360deg); }
}

/* ═══ CARA KERJA ═══ */
.pbk-alur {
    list-style: none;
    margin: 0;
    padding: 0;
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 10px;
}
.pbk-alur li {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    padding: 12px 14px;
    border-radius: 16px;
    background: #fff;
    border: 1px solid #eef2f7;
}
.pbk-alur__no {
    flex: none;
    width: 28px;
    height: 28px;
    border-radius: 50%;
    display: grid;
    place-items: center;
    font-size: 0.78rem;
    font-weight: 900;
    color: #4338ca;
    background: #eef2ff;
}
.pbk-alur b {
    display: block;
    font-size: 0.84rem;
    color: #1e293b;
}
.pbk-alur small {
    display: block;
    margin-top: 2px;
    font-size: 0.75rem;
    line-height: 1.45;
    color: var(--pbk-muted);
    font-weight: 600;
}

/* ═══ CAKUPAN ═══ */
.pbk-filter {
    display: flex;
    flex-direction: column;
    gap: 12px;
    padding: 14px;
    border-radius: 20px;
    background: #fff;
    border: 1px solid var(--pbk-line);
    box-shadow: 0 6px 18px rgba(99, 102, 241, 0.06);
}
.pbk-filter__row {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 10px;
}
.pbk-filter__seg {
    margin-bottom: 0;
}
.pbk-filter__status {
    display: flex;
    gap: 6px;
    margin-left: auto;
}
.pbk-pill {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    min-height: 38px;
    padding: 0 14px;
    border-radius: 999px;
    border: 1px solid #e2e8f0;
    background: #fff;
    font: inherit;
    font-size: 0.8rem;
    font-weight: 800;
    color: #475569;
    cursor: pointer;
    transition: all 0.15s ease;
}
.pbk-pill:hover {
    border-color: #a5b4fc;
    color: #4338ca;
}
.pbk-pill.on {
    background: #1e1b4b;
    border-color: #1e1b4b;
    color: #fff;
}
.pbk-field {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    min-width: 0;
    padding: 4px 6px 4px 5px;
    border-radius: 14px;
    background: #fff;
    border: 1px solid var(--pbk-line);
}
.pbk-field__ic {
    flex: none;
    width: 32px;
    height: 32px;
    border-radius: 10px;
    display: grid;
    place-items: center;
    font-size: 0.9rem;
    color: #4338ca;
    background: linear-gradient(135deg, #e0e7ff, #eef2ff);
}
.pbk-field__ic.is-violet {
    color: #6d28d9;
    background: linear-gradient(135deg, #ede9fe, #e0e7ff);
}
.pbk-field__lbl {
    flex: none;
    font-size: 0.68rem;
    font-weight: 900;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    color: #94a3b8;
}
/* !important: evo-theme.css memaksa SEMUA .el-select `width: 100% !important`
   (lebar penuh di borang modal). Di dalam baris flex, 100% dari lebar yang belum
   pasti jatuh ke lebar isi — pemilih tanpa `filterable` menciut sampai tinggal
   panahnya saja. */
.pbk-field__sel {
    width: 250px !important;
    min-width: 0;
}
.pbk-field__sel :deep(.el-select__wrapper) {
    box-shadow: none !important;
    background: transparent;
    min-height: 34px;
    padding: 4px 6px;
    font-weight: 800;
    font-size: 0.83rem;
    color: #4f46e5;
}
.pbk-field__sel :deep(.el-select__placeholder) {
    color: #4f46e5;
    font-weight: 800;
}
.pbk-opt__dot {
    display: inline-block;
    width: 8px;
    height: 8px;
    border-radius: 50%;
    margin-right: 8px;
    vertical-align: middle;
}
.pbk-opt__nama {
    font-weight: 700;
}
.pbk-opt__ref {
    margin-left: 8px;
    font-size: 11px;
    color: #94a3b8;
}
.pbk-opt__versi {
    margin-left: 6px;
    font-size: 10.5px;
    font-weight: 800;
    padding: 1px 6px;
    border-radius: 999px;
    background: #ede9fe;
    color: #6d28d9;
}
.pbk-opt__n {
    float: right;
    margin-left: 14px;
    font-size: 11.5px;
    font-weight: 700;
    color: #94a3b8;
}
.pbk-opt__n.is-hot {
    color: #dc2626;
}
.pbk-search {
    flex: 1 1 240px;
    display: flex;
    align-items: center;
    gap: 8px;
    min-height: 44px;
    padding: 0 14px;
    border-radius: 14px;
    border: 1.5px solid #e2e8f0;
    background: #f8fafc;
    color: #94a3b8;
    transition:
        border-color 0.15s ease,
        box-shadow 0.15s ease,
        background 0.15s ease;
}
.pbk-search:focus-within {
    border-color: #818cf8;
    background: #fff;
    box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.12);
}
.pbk-search input {
    flex: 1;
    min-width: 0;
    border: none;
    outline: none;
    background: transparent;
    font: inherit;
    font-size: 0.87rem;
    color: var(--pbk-ink);
}

/* ═══ RINGKASAN ═══ */
.pbk-kpi {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 12px;
}
.pbk-kpi__it {
    display: flex;
    align-items: center;
    gap: 12px;
    min-width: 0;
    padding: 14px 16px;
    border-radius: 18px;
    background: #fff;
    border: 1px solid #eef2f7;
    box-shadow: 0 6px 18px rgba(15, 23, 42, 0.04);
}
.pbk-kpi__ic {
    flex: none;
    width: 44px;
    height: 44px;
    border-radius: 14px;
    display: grid;
    place-items: center;
    font-size: 1.15rem;
}
.pbk-kpi__it.is-violet .pbk-kpi__ic {
    color: #6d28d9;
    background: #ede9fe;
}
.pbk-kpi__it.is-red .pbk-kpi__ic {
    color: #dc2626;
    background: #fee2e2;
}
.pbk-kpi__it.is-amber .pbk-kpi__ic {
    color: #d97706;
    background: #fef3c7;
}
.pbk-kpi__it.is-slate .pbk-kpi__ic {
    color: #475569;
    background: #f1f5f9;
}
.pbk-kpi__num {
    display: block;
    font-size: 1.5rem;
    font-weight: 900;
    line-height: 1.1;
    letter-spacing: -0.02em;
    color: var(--pbk-ink);
    font-variant-numeric: tabular-nums;
}
.pbk-kpi__lbl {
    display: block;
    margin-top: 2px;
    font-size: 0.75rem;
    font-weight: 700;
    color: var(--pbk-muted);
}

/* ═══ DAFTAR ═══ */
.pbk-loadbar {
    position: relative;
    height: 3px;
    margin: -10px 0 -9px;
    border-radius: 999px;
    overflow: hidden;
    background: #eef2ff;
}
.pbk-loadbar span {
    position: absolute;
    inset: 0 auto 0 0;
    width: 35%;
    border-radius: inherit;
    background: linear-gradient(90deg, #8b5cf6, #6366f1);
    animation: pbkLoad 1.1s ease-in-out infinite;
}
@keyframes pbkLoad {
    from { transform: translateX(-100%); }
    to { transform: translateX(300%); }
}
.pbk-hasil {
    margin: 0;
    font-size: 0.8rem;
    font-weight: 700;
    color: var(--pbk-muted);
}
.pbk-group {
    display: flex;
    flex-direction: column;
    gap: 10px;
    transition: opacity 0.2s ease;
}
.pbk-group.is-dim {
    opacity: 0.6;
}
.pbk-group__hd {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 2px 4px;
}
.pbk-group__dot {
    flex: none;
    width: 12px;
    height: 12px;
    border-radius: 4px;
    box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.08);
}
.pbk-group__txt {
    min-width: 0;
    display: flex;
    align-items: baseline;
    flex-wrap: wrap;
    gap: 2px 10px;
}
.pbk-group__txt h2 {
    margin: 0;
    font-size: 1rem;
    font-weight: 900;
    color: #1e1b4b;
}
.pbk-group__txt span {
    font-size: 0.76rem;
    font-weight: 700;
    color: #94a3b8;
}
.pbk-group__n {
    margin-left: auto;
    flex: none;
    padding: 4px 10px;
    border-radius: 999px;
    font-size: 0.72rem;
    font-weight: 800;
    color: #b91c1c;
    background: #fef2f2;
}
.pbk-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(min(100%, 480px), 1fr));
    align-items: start;
    gap: 12px;
}
.pbk-card {
    display: flex;
    flex-direction: column;
    gap: 10px;
    min-width: 0;
    padding: 16px;
    border-radius: 20px;
    background: #fff;
    border: 1px solid #eef2f7;
    box-shadow: 0 10px 28px -22px rgba(15, 23, 42, 0.45);
    transition:
        box-shadow 0.18s ease,
        border-color 0.18s ease;
}
.pbk-card:hover {
    border-color: var(--pbk-line);
    box-shadow: 0 18px 36px -24px rgba(79, 70, 229, 0.5);
}
.pbk-card__hd {
    display: flex;
    align-items: center;
    gap: 12px;
    min-width: 0;
}
.pbk-card__av {
    flex: none;
    width: 44px;
    height: 44px;
    border-radius: 14px;
    display: grid;
    place-items: center;
    color: #fff;
    font-weight: 900;
    font-size: 0.85rem;
}
.pbk-card__who {
    min-width: 0;
    flex: 1;
}
.pbk-card__who h3 {
    margin: 0;
    font-size: 0.98rem;
    font-weight: 900;
    color: var(--pbk-ink);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.pbk-card__who p {
    margin: 1px 0 0;
    font-size: 0.78rem;
    font-weight: 600;
    color: var(--pbk-muted);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.pbk-card__status {
    flex: none;
    padding: 3px 10px;
    border-radius: 999px;
    font-size: 0.7rem;
    font-weight: 900;
    background: #f1f5f9;
    color: #475569;
}
.pbk-card__status.is-berjalan {
    background: #e0e7ff;
    color: #4338ca;
}
.pbk-card__status.is-lulus {
    background: #d1fae5;
    color: #047857;
}
.pbk-card__status.is-gugur,
.pbk-card__status.is-mundur {
    background: #fee2e2;
    color: #b91c1c;
}
.pbk-card__meta {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}
.pbk-card__meta span {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    max-width: 100%;
    padding: 3px 9px;
    border-radius: 8px;
    font-size: 0.72rem;
    font-weight: 700;
    color: #475569;
    background: #f8fafc;
    border: 1px solid #f1f5f9;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.pbk-card__meta i {
    color: #94a3b8;
}
.pbk-card__items {
    list-style: none;
    margin: 0;
    padding: 0;
    display: grid;
    /* minmax(0, …): tanpa ini kolom grid melebar mengikuti teks nowrap yang
       terpanjang, dan tombol Unggah terdorong keluar kartu. */
    grid-template-columns: minmax(0, 1fr);
    gap: 8px;
}
.pbk-item {
    min-width: 0;
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 10px 10px 12px;
    border-radius: 14px;
    background: var(--pbk-soft);
    border: 1px solid #efeafe;
}
.pbk-item.is-wajib {
    background: #fff7f7;
    border-color: #fde2e2;
}
.pbk-item__ic {
    flex: none;
    width: 38px;
    height: 38px;
    border-radius: 12px;
    display: grid;
    place-items: center;
    font-size: 1.15rem;
    color: #6d28d9;
    background: #fff;
    border: 1px solid #ede9fe;
}
.pbk-item.is-wajib .pbk-item__ic {
    color: #dc2626;
    border-color: #fecaca;
}
.pbk-item__main {
    min-width: 0;
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 2px;
}
.pbk-item__top {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 4px 8px;
}
.pbk-item__top b {
    font-size: 0.88rem;
    color: var(--pbk-ink);
}
.pbk-item__tag {
    padding: 1px 8px;
    border-radius: 999px;
    font-size: 0.64rem;
    font-weight: 900;
}
.pbk-item__tag.is-red {
    background: #fee2e2;
    color: #b91c1c;
}
.pbk-item__tag.is-amber {
    background: #fef3c7;
    color: #92400e;
    cursor: help;
}
.pbk-item__loc,
.pbk-item__src {
    font-size: 0.74rem;
    font-weight: 600;
    color: var(--pbk-muted);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.pbk-item__src {
    color: #94a3b8;
}
.pbk-item__nama {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 0.74rem;
    font-weight: 700;
    color: #b45309;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.pbk-item__btn {
    flex: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    min-height: 42px;
    padding: 0 16px;
    border: none;
    border-radius: 12px;
    font: inherit;
    font-size: 0.82rem;
    font-weight: 800;
    color: #fff;
    background: linear-gradient(120deg, #4f46e5, #7c3aed);
    box-shadow: 0 10px 20px rgba(124, 58, 237, 0.28);
    cursor: pointer;
    transition:
        transform 0.15s ease,
        box-shadow 0.15s ease;
}
.pbk-item__btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 14px 26px rgba(124, 58, 237, 0.38);
}
.pbk-item__btn:focus-visible {
    outline: 3px solid rgba(99, 102, 241, 0.45);
    outline-offset: 2px;
}

/* ═══ KOSONG / GALAT / MEMUAT ═══ */
.pbk-empty {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    gap: 8px;
    padding: 48px 20px;
    border-radius: 22px;
    background: #fff;
    border: 1px solid #eef2f7;
    box-shadow: 0 18px 40px -30px rgba(15, 23, 42, 0.5);
}
.pbk-empty__ic {
    width: 72px;
    height: 72px;
    border-radius: 24px;
    display: grid;
    place-items: center;
    font-size: 2rem;
    color: #6366f1;
    background: #eef2ff;
    margin-bottom: 4px;
}
.pbk-empty.is-ok .pbk-empty__ic {
    color: #059669;
    background: linear-gradient(135deg, #d1fae5, #ecfdf5);
    box-shadow: 0 14px 30px -12px rgba(5, 150, 105, 0.45);
}
.pbk-empty.is-galat .pbk-empty__ic {
    color: #dc2626;
    background: #fee2e2;
}
.pbk-empty h3 {
    margin: 0;
    font-size: 1.1rem;
    font-weight: 900;
    color: #1e1b4b;
}
.pbk-empty p {
    margin: 0;
    max-width: 480px;
    font-size: 0.86rem;
    line-height: 1.55;
    color: var(--pbk-muted);
}
.pbk-empty__cta {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: center;
    gap: 10px;
    margin-top: 8px !important;
}
.pbk-sk {
    pointer-events: none;
}
.pbk-sk__hd {
    display: flex;
    align-items: center;
    gap: 12px;
}
.pbk-sk__av,
.pbk-sk__ln,
.pbk-sk__blok {
    display: block;
    border-radius: 10px;
    background: linear-gradient(90deg, #f1f5f9 25%, #e2e8f0 37%, #f1f5f9 63%);
    background-size: 400% 100%;
    animation: pbkKilau 1.3s ease infinite;
}
.pbk-sk__av {
    width: 44px;
    height: 44px;
    border-radius: 14px;
}
.pbk-sk__ln {
    height: 12px;
    width: 100%;
}
.pbk-sk__ln.is-w60 {
    width: 60%;
}
.pbk-sk__ln.is-w80 {
    width: 80%;
}
.pbk-sk__blok {
    height: 64px;
    border-radius: 14px;
}
@keyframes pbkKilau {
    0% { background-position: 100% 50%; }
    100% { background-position: 0 50%; }
}
.wca-toast.is-err i {
    color: #f87171;
}

@media (prefers-reduced-motion: reduce) {
    .pbk-putar,
    .pbk-loadbar span,
    .pbk-sk__av,
    .pbk-sk__ln,
    .pbk-sk__blok {
        animation: none;
    }
}

/* ═══ RESPONSIF ═══ */
@media (max-width: 1100px) {
    .pbk-kpi {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}
@media (max-width: 880px) {
    .pbk-filter__status {
        margin-left: 0;
    }
    .pbk-field {
        flex: 1 1 100%;
    }
    .pbk-field__sel {
        width: auto !important;
        flex: 1 1 auto;
    }
    .pbk-search {
        flex-basis: 100%;
    }
}
@media (max-width: 720px) {
    .pbk {
        gap: 12px;
    }
    .pbk-hero {
        padding: 16px;
        border-radius: 18px;
    }
    .pbk-hero__main {
        flex-basis: 100%;
        align-items: flex-start;
    }
    .pbk-hero__ic {
        width: 46px;
        height: 46px;
        border-radius: 14px;
        font-size: 1.25rem;
    }
    .pbk-hero h1 {
        font-size: 1.25rem;
    }
    .pbk-hero__acts {
        width: 100%;
    }
    .pbk-hero__btn {
        flex: 1;
    }
    .pbk-alur li {
        flex-direction: column;
        gap: 6px;
        padding: 10px;
    }
    .pbk-alur small {
        display: none;
    }
    .pbk-alur b {
        font-size: 0.76rem;
        line-height: 1.3;
    }
    .pbk-filter {
        padding: 12px;
        border-radius: 18px;
    }
    .pbk-filter__seg,
    .pbk-filter__status {
        width: 100%;
    }
    .pbk-filter__seg .wca-segt__it {
        flex: 1;
        justify-content: center;
        min-height: 40px;
        padding: 0.5rem 0.6rem;
    }
    .pbk-pill {
        flex: 1;
        justify-content: center;
        min-height: 42px;
        padding: 0 10px;
        white-space: nowrap;
    }
    .pbk-pill i,
    .pbk-lebar {
        display: none;
    }
    .pbk-card {
        padding: 14px;
        border-radius: 18px;
    }
    .pbk-group__hd {
        display: grid;
        grid-template-columns: 12px minmax(0, 1fr);
        column-gap: 10px;
        row-gap: 6px;
    }
    .pbk-group__n {
        grid-column: 2;
        justify-self: start;
        margin-left: 0;
    }
}
@media (max-width: 520px) {
    .pbk-kpi {
        gap: 8px;
    }
    .pbk-kpi__it {
        flex-direction: column;
        align-items: flex-start;
        gap: 8px;
        padding: 12px;
    }
    .pbk-kpi__ic {
        width: 36px;
        height: 36px;
        border-radius: 11px;
        font-size: 1rem;
    }
    .pbk-kpi__num {
        font-size: 1.3rem;
    }
    .pbk-item {
        flex-wrap: wrap;
    }
    .pbk-item__btn {
        width: 100%;
        min-height: 46px;
    }
    .pbk-card__status {
        align-self: flex-start;
    }
}
</style>
