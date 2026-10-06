<!-- WEB CAREER — Portal Kandidat: Lamaran Saya, 1:1 desain "Lamaran Saya" (Claude
     Design). Layout FLUID (tanpa container), berjalan di dalam AppShell (rail +
     navbar shell). DATA NYATA: strip statistik (props.stats dari DB) dan kartu
     LAMARAN AKTIF + stepper PROGRES SELEKSI (props.lamaran[].tahapan dari DB).
     Riwayat Lamaran juga REAL (props.lamaran) + filter status; rekomendasi & drawer dummy dibuang. -->
<template>
    <Head title="Lamaran Saya" />

    <div class="lms">
        <div class="lms-blob lms-blob--a"></div>
        <div class="lms-blob lms-blob--b"></div>

        <div class="lms-wrap">
            <!-- ═══ HEADING ═══ -->
            <div class="lms-head">
                <div style="min-width: 0">
                    <h1 class="lms-h1">Lamaran Saya</h1>
                    <p class="lms-sub">Pantau progres seleksimu di EVO Group. Klik lamaran untuk melihat detail &amp; mengisi formulir tahap.</p>
                </div>
                <!-- Kandidat yang lamarannya masih BERJALAN tidak diajak mencari
                     lowongan lain — fokusnya proses yang sedang dijalani. -->
                <Link v-if="!adaLamaranBerjalan" href="/karir/landing-page" class="lms-btn-cari">
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><circle cx="11" cy="11" r="7" /><path d="M21 21l-4.3-4.3" /></svg>
                    Cari Lowongan
                </Link>
            </div>

            <!-- ═══ STAT STRIP (REAL) ═══ -->
            <div class="lms-stats">
                <div v-for="st in statTampil" :key="st.label" class="lms-stat">
                    <span class="lms-stat__ico" :style="{ background: st.bg, color: st.c }" v-html="st.icon"></span>
                    <span style="display: flex; flex-direction: column; min-width: 0">
                        <span class="lms-stat__val">{{ st.value }}</span>
                        <span class="lms-stat__lbl">{{ st.label }}</span>
                    </span>
                </div>
            </div>

            <!-- ═══ EMPTY STATE ═══ -->
            <div v-if="!lamaran.length" class="lms-hero" style="margin-top: 30px; text-align: center; padding: 52px 28px">
                <div style="width: 62px; height: 62px; border-radius: 18px; margin: 0 auto; background: rgba(99, 102, 241, 0.1); color: #6366f1; display: flex; align-items: center; justify-content: center">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="7" width="18" height="13" rx="2" /><path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" /></svg>
                </div>
                <div style="font-size: 18px; font-weight: 800; color: #0f172a; margin-top: 16px">Belum ada lamaran</div>
                <div style="font-size: 13.5px; color: #8792a6; margin-top: 6px">Mulai dengan mencari lowongan yang cocok untukmu.</div>
                <Link href="/karir/landing-page" class="lms-btn-cari" style="margin-top: 18px">
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><circle cx="11" cy="11" r="7" /><path d="M21 21l-4.3-4.3" /></svg>
                    Cari Lowongan
                </Link>
            </div>

            <!-- ═══ LAMARAN AKTIF (REAL) ═══ -->
            <template v-if="heroes.length">
                <div class="lms-sec-label" style="margin: 30px 2px 12px">LAMARAN AKTIF</div>
                <div v-for="(l, hi) in heroes" :key="l.id" class="lms-hero" :style="{ marginBottom: hi < heroes.length - 1 ? '16px' : '0' }">
                    <span class="lms-hero__bar"></span>
                    <div class="lms-hero__glow"></div>
                    <div class="lms-hero__in">
                        <div class="lms-hero__toprow">
                            <div style="display: flex; align-items: center; gap: 9px; flex-wrap: wrap">
                                <span class="lms-chip" :style="katChipStyle(l.kategori)">
                                    <svg v-if="l.kategori === 'MT'" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 10v6M2 10l10-5 10 5-10 5z" /><path d="M6 12v5c3 3 9 3 12 0v-5" /></svg>
                                    <svg v-else width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="7" width="18" height="13" rx="2" /><path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" /></svg>
                                    {{ katLabel(l.kategori) }}
                                </span>
                                <span class="lms-chip" :style="stChipStyle(l)">
                                    <span v-if="l.status === 'BERJALAN'" class="lms-pulse"></span>
                                    <svg v-else-if="l.status === 'LULUS'" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round"><path d="M20 6L9 17l-5-5" /></svg>
                                    <!-- Kandidat yang MUNDUR tidak diberi tanda silang.
                                         Ikon itu berarti "ditolak"; yang terjadi di sini
                                         justru sebaliknya — ia yang melangkah keluar. -->
                                    <svg v-else-if="stKey(l) === 'netral'" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" /><path d="M16 17l5-5-5-5" /><path d="M21 12H9" /></svg>
                                    <svg v-else width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><circle cx="12" cy="12" r="9" /><path d="M15 9l-6 6M9 9l6 6" /></svg>
                                    {{ stLabel(l) }}
                                </span>
                            </div>
                            <div class="lms-hero__stamp">
                                <div style="font-weight: 700; color: #334155">Dilamar {{ tglLamar(l.waktuLamar) }}</div>
                                <div style="margin-top: 2px">Kode: <span class="lms-mono">{{ l.kode }}</span></div>
                            </div>
                        </div>

                        <h2 class="lms-hero__title">{{ heroJudul(l) }}</h2>
                        <p class="lms-hero__desc">{{ heroDesc(l) }}</p>

                        <div class="lms-metas">
                            <span v-for="(m, mi) in heroMeta(l)" :key="mi" class="lms-meta">
                                <span v-html="m.icon"></span>{{ m.text }}
                            </span>
                        </div>

                        <!-- PROGRES SELEKSI (stepper real dari tahapan DB).
                             <details>, bukan accordion buatan sendiri: buka-tutup,
                             keyboard, dan pencarian dalam halaman sudah ditangani
                             browser. Ringkasannya tetap menampilkan bar & "Tahap X
                             dari Y" saat tertutup. -->
                        <details class="lms-prog" :open="!kompak">
                            <summary class="lms-prog__sum">
                                <span class="lms-prog__head">
                                    <span class="lms-sec-label">PROGRES SELEKSI</span>
                                    <span class="lms-prog__count">
                                        Tahap {{ l.urutanTahap }} dari {{ l.totalTahap }}
                                        <svg class="lms-prog__chev" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6" /></svg>
                                    </span>
                                </span>
                                <span class="lms-prog__bar"><span :style="{ width: heroPct(l) + '%' }"></span></span>
                                <!-- Nama tahap yang sedang berjalan ikut di ringkasan:
                                     "Tahap 1 dari 7" saja tidak memberi tahu apa pun
                                     tentang APA yang sedang berlangsung. -->
                                <span v-if="tahapAktif(l)" class="lms-prog__now" :class="'is-' + tahapAktif(l).st">
                                    <span class="lms-prog__now-dot"></span>
                                    <span class="lms-prog__now-lbl">{{ tahapAktif(l).teks }}</span>
                                    <strong>{{ tahapAktif(l).name }}</strong>
                                </span>
                            </summary>
                            <div class="lms-steps">
                                <div v-for="(sg, i) in heroSteps(l)" :key="i" class="lms-step">
                                    <div v-if="i > 0" class="lms-step__line" :style="{ background: sg.line }"></div>
                                    <div class="lms-step__node" :class="'is-' + sg.st">
                                        <svg v-if="sg.st === 'done'" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5" /></svg>
                                        <svg v-else-if="sg.st === 'fail'" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round"><path d="M18 6L6 18M6 6l12 12" /></svg>
                                        <template v-else>{{ i + 1 }}</template>
                                    </div>
                                    <div class="lms-step__lbl" :style="{ color: sg.lbl }">{{ sg.name }}</div>
                                </div>
                            </div>
                        </details>

                        <!-- Kalimatnya mengikuti SEBAB berhentinya, bukan satu
                             kalimat untuk semua. "Tidak lolos di tahap X" pada
                             lamaran yang justru ditutup kandidatnya sendiri
                             adalah keterangan yang salah — dan itu keterangan
                             yang ia baca tentang dirinya sendiri. -->
                        <div v-if="l.status !== 'BERJALAN' && l.status !== 'LULUS' && l.gugurDi" class="lms-gugurnote" :class="{ 'is-netral': stKey(l) === 'netral' }">
                            <svg v-if="stKey(l) === 'netral'" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" /><path d="M16 17l5-5-5-5" /><path d="M21 12H9" /></svg>
                            <svg v-else width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><circle cx="12" cy="12" r="9" /><path d="M15 9l-6 6M9 9l6 6" /></svg>
                            {{ stKey(l) === 'netral' ? `${stLabel(l)} di tahap: ${l.gugurDi}` : `Tidak lolos di tahap: ${l.gugurDi}` }}
                        </div>

                        <div style="margin-top: 18px; display: flex; align-items: center; gap: 10px; flex-wrap: wrap">
                            <Link :href="`/kandidat/lamaran/${l.id}`" class="lms-btn-detail">
                                Detail Lamaran
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6" /></svg>
                            </Link>
                            <a v-if="fbCta(l)" :href="fbCta(l)" class="lms-btn-feedback" title="Bantu kami evaluasi proses rekrutmen ini, masukan Anda sangat berharga!">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/><path d="M12 8v4M12 16h.01"/></svg>
                                <span>Bantu Kami Berbenah ✨</span>
                            </a>
                            <button type="button" class="lms-btn-hapus" :disabled="menghapus === l.id" title="Hapus / batalkan lamaran ini" :onClick="menghapus === l.id ? null : () => minta(l)">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18" /><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" /><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6" /><path d="M10 11v6M14 11v6" /></svg>
                            </button>
                        </div>
                    </div>
                </div>
            </template>

            <!-- ═══ RIWAYAT LAMARAN (REAL) ═══ -->
            <div v-if="lamaran.length" class="lms-histrow">
                <div class="lms-sec-label">RIWAYAT LAMARAN</div>
                <div style="display: flex; gap: 7px; flex-wrap: wrap">
                    <button v-for="f in FILTERS" :key="f.key" type="button" class="lms-fbtn" :class="{ 'is-on': filter === f.key }" @click="filter = f.key">{{ f.label }}</button>
                </div>
            </div>

            <div v-if="lamaran.length" class="lms-tl">
                <div class="lms-tl__line"></div>
                <div style="display: flex; flex-direction: column; gap: 14px">
                    <div v-for="h in histTampil" :key="h.id" class="lms-tl__item">
                        <span class="lms-tl__dot" :style="{ borderColor: ST[h.status].dot }"></span>
                        <button type="button" class="lms-hcard" @click="bukaDetail(h)">
                            <span class="lms-hcard__row">
                                <span style="display: flex; flex-direction: column; min-width: 0; text-align: left">
                                    <span style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap">
                                        <span class="lms-type" :style="typeStyle(h.type)">{{ h.type }}</span>
                                        <span class="lms-mono" style="font-size: 11px; color: #a2a9ba">{{ h.code }}</span>
                                    </span>
                                    <span class="lms-hcard__title">{{ h.title }}</span>
                                    <span class="lms-hcard__meta">
                                        <span><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" /><path d="M3 10h18M8 2v4M16 2v4" /></svg>Dilamar {{ h.applied }}</span>
                                        <span><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 11l3 3 8-8" /><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11" /></svg>{{ h.lastStage }}</span>
                                    </span>
                                </span>
                                <span class="lms-hcard__side">
                                    <span class="lms-pill" :style="pillStyle(h.status)">{{ h.statusLabel }}</span>
                                    <a
                                        v-if="isFinalStatus(h) && fbCta(h)"
                                        :href="fbCta(h)"
                                        class="lms-btn-feedback-sm"
                                        @click.stop
                                        title="Bantu kami evaluasi proses rekrutmen ini, masukan Anda sangat berharga!"
                                    >
                                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/><path d="M12 8v4M12 16h.01"/></svg>
                                        <span>Bantu Kami Berbenah ✨</span>
                                    </a>
                                    <span v-else style="display: inline-flex; align-items: center; gap: 5px; font-size: 12px; font-weight: 700; color: #6366f1">
                                        Lihat detail
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M9 6l6 6-6 6" /></svg>
                                    </span>
                                </span>
                            </span>
                        </button>
                    </div>
                    <div v-if="!histTampil.length" style="padding: 30px; text-align: center; color: #a2a9ba; font-size: 13px">Tidak ada lamaran pada filter ini.</div>
                </div>
            </div>

            <div style="height: 20px"></div>
        </div>

        <!-- ═══ KONFIRMASI HAPUS (fitur lama dipertahankan) ═══ -->
        <div v-if="target" class="lms-modal" @click.self="target = null">
            <div class="lms-modal__box">
                <div class="lms-modal__ic">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.3 3.8L1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.8a2 2 0 0 0-3.4 0z" /><path d="M12 9v4M12 17h.01" /></svg>
                </div>
                <h3>Hapus lamaran ini?</h3>
                <p>Lamaran <strong>{{ target.posisi || target.program }}</strong> ({{ target.kode }}) beserta isian formulirnya akan dihapus permanen. Tindakan ini tidak bisa dibatalkan.</p>
                <div class="lms-modal__act">
                    <button type="button" class="lms-modal__soft" @click="target = null">Batal</button>
                    <button type="button" class="lms-modal__danger" :disabled="!!menghapus" :onClick="!!menghapus ? null : hapus">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18" /><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" /><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6" /></svg>
                        {{ menghapus ? 'Menghapus…' : 'Ya, Hapus' }}
                    </button>
                </div>
            </div>
        </div>

        <transition name="lms-toast"><div v-if="toast" class="lms-toast" :class="{ 'is-err': toastErr }"><i class="bi" :class="toastErr ? 'bi-exclamation-circle-fill' : 'bi-check-circle-fill'"></i> {{ toast }}</div></transition>
    </div>
</template>

<script>
import axios from 'axios';
import { Head, Link, router } from '@inertiajs/vue3';

const CFG = { headers: { Accept: 'application/json' } };
const BULAN = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

// Ikon stroke (dipakai strip statistik & chip meta hero) — persis desain.
const SVG = (path, w = 21) => `<svg width="${w}" height="${w}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">${path}</svg>`;
const MSVG = (path) => `<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#8b5cf6" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">${path}</svg>`;
const P = {
    koper: '<rect x="3" y="7" width="18" height="13" rx="2"/><path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>',
    jam: '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
    cek: '<path d="M20 6L9 17l-5-5"/>',
    silang: '<circle cx="12" cy="12" r="9"/><path d="M15 9l-6 6M9 9l6 6"/>',
    topi: '<path d="M22 10L12 5 2 10l10 5 10-5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/>',
    pin: '<path d="M12 21s-7-6-7-11a7 7 0 0 1 14 0c0 5-7 11-7 11z"/><circle cx="12" cy="10" r="2.5"/>',
    orang: '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/>',
    level: '<path d="M4 20h4V10H4zM10 20h4V4h-4zM16 20h4v-8h-4z"/>',
};

// Warna status (persis desain: berjalan/menunggu/lolos/gugur).
const ST = {
    berjalan: { c: '#b45309', bg: 'rgba(245,158,11,.14)', dot: '#f59e0b' },
    menunggu: { c: '#1d4ed8', bg: 'rgba(59,130,246,.12)', dot: '#3b82f6' },
    lolos: { c: '#059669', bg: 'rgba(16,185,129,.12)', dot: '#10b981' },
    gugur: { c: '#dc2626', bg: 'rgba(239,68,68,.1)', dot: '#ef4444' },
    // KEPUTUSAN DARI KANDIDAT (mundur / menolak penawaran): prosesnya berhenti,
    // tapi bukan karena ditolak. Merah akan mengatakan hal yang salah kepada
    // orang yang justru memilih pergi sendiri.
    netral: { c: '#475569', bg: 'rgba(100,116,139,.12)', dot: '#64748b' },
};

export default {
    components: { Head, Link },
    props: {
        lamaran: { type: Array, default: () => [] },
        stats: { type: Object, default: () => ({ total: 0, berjalan: 0, lulus: 0, gugur: 0 }) },
    },
    data() {
        return {
            ST,
            FILTERS: [
                { key: 'semua', label: 'Semua' },
                { key: 'berjalan', label: 'Berjalan' },
                { key: 'lolos', label: 'Lolos' },
                { key: 'gugur', label: 'Tidak Lolos' },
            ],
            filter: 'semua',
            target: null,
            menghapus: null,
            toast: '',
            toastErr: false,
            tm: null,
            // Stepper baru dilipat jadi dropdown di layar sempit. Di tablet ke
            // atas ruangnya cukup, jadi ia dibiarkan terbuka seperti semula.
            kompak: false,
            mqKompak: null,
        };
    },
    mounted() {
        this.mqKompak = window.matchMedia('(max-width: 767.98px)');
        this.syncKompak();
        this.mqKompak.addEventListener('change', this.syncKompak);
    },
    beforeUnmount() {
        this.mqKompak?.removeEventListener('change', this.syncKompak);
        if (this.tm) clearTimeout(this.tm);
    },
    computed: {
        // Strip statistik — NILAI REAL dari controller (props.stats).
        statTampil() {
            return [
                { value: this.stats.total, label: 'Total Lamaran', bg: 'rgba(99,102,241,.12)', c: '#6366f1', icon: SVG(P.koper) },
                { value: this.stats.berjalan, label: 'Berjalan', bg: 'rgba(245,158,11,.14)', c: '#f59e0b', icon: SVG(P.jam) },
                { value: this.stats.lulus, label: 'Diterima', bg: 'rgba(16,185,129,.12)', c: '#10b981', icon: SVG(P.cek) },
                { value: this.stats.gugur, label: 'Tidak Lolos', bg: 'rgba(239,68,68,.1)', c: '#ef4444', icon: SVG(P.silang) },
            ];
        },
        // Kartu hero = lamaran BERJALAN (real). Bila tak ada yang berjalan,
        // tampilkan lamaran terbaru agar kandidat tetap melihat status akhirnya.
        adaLamaranBerjalan() {
            return this.lamaran.some((l) => l.status === 'BERJALAN');
        },
        heroes() {
            const jalan = this.lamaran.filter((l) => l.status === 'BERJALAN');
            return jalan.length ? jalan : this.lamaran.slice(0, 1);
        },
        // RIWAYAT = seluruh lamaran nyata (props.lamaran) dipetakan ke kartu
        // ringkas, lalu difilter chip status (semua/berjalan/lolos/gugur).
        histTampil() {
            const rows = this.lamaran.map((l) => ({
                id: l.id,
                code: l.kode,
                type: this.katLabel(l.kategori),
                title: l.posisi || l.program,
                applied: this.tglLamar(l.waktuLamar),
                status: this.stKey(l),
                statusLabel: this.stLabel(l),
                lastStage: this.riwayatTahap(l),
            }));
            if (this.filter === 'semua') return rows;
            return rows.filter((h) => h.status === this.filter);
        },
    },
    methods: {
        syncKompak() { this.kompak = !!this.mqKompak?.matches; },
        // [feat/feedback]
        isFinalStatus(item) {
            if (!item || !item.status) return false;
            return ['lolos', 'gugur', 'LULUS', 'GUGUR', 'DITERIMA', 'DITOLAK'].includes(item.status);
        },
        fbCta(l) {
            const fp = this.$page.props.feedbackPending;
            if (!fp || !fp.feedback_url) return null;
            if (l && (l.id || l.code)) {
                const matches = (fp.lamaran_hashid && fp.lamaran_hashid === l.id)
                    || (fp.lamaran_id && String(fp.lamaran_id) === String(l.id))
                    || (fp.Id_Lamaran && String(fp.Id_Lamaran) === String(l.id))
                    || (fp.kode_lamaran && fp.kode_lamaran === l.code);
                if (matches) {
                    return fp.feedback_url;
                }
                return null;
            }
            return null;
        },
        k(l) { return l.kartu || {}; },
        katLabel(k) { return { REKRUTMEN: 'Rekrutmen', MT: 'Management Trainee', INTERNSHIP: 'Internship' }[k] || k || '—'; },
        /**
         * Kata & nada status — DARI SERVER bila lamarannya ikut diberikan.
         *
         * Peta di bawah tinggal jaring pengaman. Ia pernah jadi satu-satunya
         * sumber, dan isinya sudah salah: kode yang benar-benar dipakai adalah
         * MENGUNDURKAN_DIRI / DITOLAK_KANDIDAT, bukan "MUNDUR" — sehingga
         * kandidat yang mundur membaca kode mentah di kartunya sendiri, dan
         * kartunya diwarnai seperti lamaran yang masih berjalan.
         *
         * Menerima objek lamaran ATAU string status supaya pemanggil lama tetap
         * bekerja.
         */
        stLabel(l) {
            if (l && typeof l === 'object') {
                return l.statusLabel || this.stLabel(l.status);
            }

            return { BERJALAN: 'Berjalan', LULUS: 'Diterima', GUGUR: 'Tidak Lolos', TALENT_POOL: 'Cadangan (Talent Pool)' }[l] || l;
        },
        stKey(l) {
            if (l && typeof l === 'object') {
                return ST[l.statusNada] ? l.statusNada : this.stKey(l.status);
            }

            return { BERJALAN: 'berjalan', LULUS: 'lolos', GUGUR: 'gugur', TALENT_POOL: 'menunggu' }[l] || 'berjalan';
        },
        katChipStyle(kat) {
            return kat === 'MT'
                ? { background: 'rgba(245,158,11,.14)', color: '#b45309' }
                : kat === 'INTERNSHIP'
                    ? { background: 'rgba(16,185,129,.12)', color: '#059669' }
                    : { background: 'rgba(99,102,241,.12)', color: '#4f46e5' };
        },
        stChipStyle(l) { const t = ST[this.stKey(l)] || ST.berjalan; return { background: t.bg, color: t.c }; },
        typeStyle(t) {
            return t === 'Management Trainee'
                ? { background: 'rgba(245,158,11,.14)', color: '#b45309' }
                : t === 'Internship'
                    ? { background: 'rgba(16,185,129,.12)', color: '#059669' }
                    : { background: 'rgba(99,102,241,.12)', color: '#4f46e5' };
        },
        pillStyle(st) { return { background: ST[st].bg, color: ST[st].c }; },
        tglLamar(iso) {
            if (!iso) return '—';
            const d = new Date(String(iso).replace(' ', 'T'));
            if (Number.isNaN(d.getTime())) return '—';
            return `${d.getDate()} ${BULAN[d.getMonth()]} ${d.getFullYear()}`;
        },
        // ── HERO (real) ──
        heroJudul(l) { const c = this.k(l); return (l.kategori === 'MT' ? c.nama : c.posisi) || l.posisi || l.program; },
        heroDesc(l) {
            const c = this.k(l);
            return c.ringkasan || (l.kategori === 'MT' ? `Program ${l.program} — akselerasi calon pemimpin masa depan EVO Group.` : `Lowongan ${l.posisi || l.program} di EVO Group.`);
        },
        heroMeta(l) {
            const c = this.k(l);
            const m = [];
            if (l.kategori === 'MT') {
                if (c.tipeKegiatan || true) m.push({ icon: MSVG(P.topi), text: c.tipeKegiatan || 'Management Trainee' });
                if (c.penempatan || l.lokasi) m.push({ icon: MSVG(P.pin), text: c.penempatan || l.lokasi });
                if (c.durasi) m.push({ icon: MSVG(P.jam), text: c.durasi });
                if (c.kuota != null) m.push({ icon: MSVG(P.orang), text: `${c.kuota} kursi` });
            } else {
                m.push({ icon: MSVG(P.koper), text: c.tipeKerja || 'Full-time' });
                if (c.lokasi || l.lokasi) m.push({ icon: MSVG(P.pin), text: (c.lokasi || l.lokasi) + (c.tempatKerja ? ` · ${c.tempatKerja}` : '') });
                if (c.level) m.push({ icon: MSVG(P.level), text: c.level });
                if (c.pengalaman) m.push({ icon: MSVG(P.jam), text: c.pengalaman });
            }
            return m;
        },
        // Stepper real dari l.tahapan (DB); fallback nomor bila tahapan kosong.
        heroSteps(l) {
            const rows = l.tahapan?.length
                ? l.tahapan.map((t) => ({
                    name: t.label,
                    st: t.status === 'GUGUR' || t.hasil === 'GUGUR' ? 'fail'
                        : t.status === 'SELESAI' ? 'done'
                            : t.status === 'BERJALAN' ? 'current' : 'todo',
                }))
                : Array.from({ length: l.totalTahap || 0 }, (_, i) => ({
                    name: `Tahap ${i + 1}`,
                    // Tahap terakhir hanya bertanda silang bila kandidat memang
                    // GAGAL di sana. Lamaran yang berhenti karena kandidat
                    // mundur ditinggalkan sebagai belum tuntas, bukan gagal.
                    st: i + 1 < l.urutanTahap
                        ? 'done'
                        : i + 1 === l.urutanTahap
                            ? (l.status === 'BERJALAN' ? 'current' : (this.stKey(l) === 'gugur' ? 'fail' : 'todo'))
                            : 'todo',
                }));
            return rows.map((r) => ({
                ...r,
                line: r.st === 'done' || r.st === 'current' ? '#a5b4fc' : r.st === 'fail' ? '#fca5a5' : '#e2e8f0',
                lbl: r.st === 'todo' ? '#94a3b8' : r.st === 'fail' ? '#dc2626' : '#334155',
            }));
        },
        /**
         * Tahap yang sedang dijalani — untuk ringkasan progres (terlihat tanpa
         * membuka stepper). Kata pengantarnya mengikuti keadaan: yang berhenti
         * di suatu tahap bukan sedang "berjalan" di sana.
         */
        tahapAktif(l) {
            const steps = this.heroSteps(l);
            if (!steps.length) return null;
            const jalan = steps.find((s) => s.st === 'current');
            if (jalan) return { ...jalan, teks: 'Sedang berjalan:' };
            const gagal = steps.find((s) => s.st === 'fail');
            if (gagal) return { ...gagal, teks: 'Berhenti di:' };
            const selesai = [...steps].reverse().find((s) => s.st === 'done');
            if (selesai) return { ...selesai, teks: 'Tahap terakhir:' };
            return { ...steps[0], teks: 'Tahap berikutnya:' };
        },
        heroPct(l) {
            const steps = this.heroSteps(l);
            if (!steps.length) return 0;
            const done = steps.filter((s) => s.st === 'done').length;
            const cur = steps.some((s) => s.st === 'current' || s.st === 'fail') ? 0.5 : 0;
            return Math.round(((done + cur) / steps.length) * 100);
        },
        // ── RIWAYAT: klik kartu -> halaman detail lamaran nyata ──
        bukaDetail(h) { router.visit('/kandidat/lamaran/' + h.id); },
        // Ringkasan tahap terakhir/aktif untuk baris kartu riwayat.
        riwayatTahap(l) {
            if (l.status === 'GUGUR' && l.gugurDi) return l.gugurDi;
            const tahapan = Array.isArray(l.tahapan) ? l.tahapan : [];
            if (tahapan.length) {
                const jalan = tahapan.find((t) => t.status === 'BERJALAN');
                if (jalan) return jalan.label;
                const selesai = [...tahapan].reverse().find((t) => t.status === 'SELESAI');
                return (selesai || tahapan[tahapan.length - 1]).label;
            }
            if (l.urutanTahap && l.totalTahap) return 'Tahap ' + l.urutanTahap + ' dari ' + l.totalTahap;
            return 'Seleksi';
        },
        // ── Hapus lamaran (fitur lama, tetap ada) ──
        minta(l) { this.target = l; },
        async hapus() {
            if (this.menghapus || !this.target) return;
            const l = this.target;
            this.menghapus = l.id;
            try {
                await axios.delete(`/api/v1/lamaran/${l.id}`, CFG);
                this.notice('Lamaran dihapus.');
                this.target = null;
                router.reload({ only: ['lamaran', 'stats'] });
            } catch (e) {
                this.notice(e.response?.data?.message || 'Gagal menghapus lamaran.', true);
            } finally {
                this.menghapus = null;
            }
        },
        notice(x, err = false) { this.toast = x; this.toastErr = err; if (this.tm) clearTimeout(this.tm); this.tm = setTimeout(() => (this.toast = ''), 3200); },
    },
};
</script>

<style scoped>
.lms-mono { font-family: 'JetBrains Mono', 'Courier New', monospace; color: #8b93a7; }
.lms-sec-label { font-size: 12px; font-weight: 800; letter-spacing: 0.14em; color: #8b93a7; }

/* ═══ HALAMAN: fluid penuh (container-fluid), latar dari shell ═══ */
/* clip (bukan hidden) agar tidak jadi scroll container → hindari scrollbar ganda. */
/* margin bawah 0 & TANPA min-height sendiri: '-1rem' di bawah menarik kotak ini
   melewati batas .app-shell, dan 'calc(100vh - 68px)' menebak tinggi topbar yang
   di ponsel tidak 68px — keduanya membuat halaman menjulur keluar gradasi shell
   sehingga tersisa pita putih body di bawah. Tinggi penuh sudah dijamin
   .shell-content (min-height: calc(100vh - 4rem)).
   overflow: clip (dua sumbu) menahan blob dekoratif yang ditempatkan di
   bottom: -160px; clip tidak membuat elemen ini jadi kontainer gulir. */
.lms { position: relative; margin: -1rem -1rem 0; padding: 28px 34px 48px; overflow: clip; }
.lms-blob { position: absolute; border-radius: 50%; filter: blur(8px); pointer-events: none; z-index: 0; }
.lms-blob--a { top: -120px; right: 12%; width: 440px; height: 440px; background: radial-gradient(circle at 30% 30%, rgba(139, 92, 246, 0.14), rgba(139, 92, 246, 0) 70%); animation: lmsFloatA 16s ease-in-out infinite; }
.lms-blob--b { bottom: -160px; left: 6%; width: 460px; height: 460px; background: radial-gradient(circle at 60% 40%, rgba(99, 102, 241, 0.1), rgba(99, 102, 241, 0) 70%); animation: lmsFloatB 19s ease-in-out infinite; }
@keyframes lmsFloatA { 0%, 100% { transform: translate(0, 0); } 50% { transform: translate(30px, -24px); } }
@keyframes lmsFloatB { 0%, 100% { transform: translate(0, 0); } 50% { transform: translate(-26px, 22px); } }
.lms-wrap { position: relative; z-index: 2; }

/* ═══ HEADING ═══ */
.lms-head { display: flex; align-items: flex-end; justify-content: space-between; gap: 16px; flex-wrap: wrap; margin-bottom: 22px; }
.lms-h1 { margin: 0; font-size: 28px; font-weight: 800; color: #0f172a; letter-spacing: -0.025em; }
.lms-sub { margin: 7px 0 0; font-size: 14px; color: #64748b; }
.lms-btn-cari { appearance: none; cursor: pointer; font-family: inherit; font-size: 13.5px; font-weight: 800; color: #fff; padding: 12px 20px; border-radius: 14px; border: none; background: linear-gradient(135deg, #8b5cf6, #6366f1); box-shadow: 0 12px 28px rgba(99, 102, 241, 0.32); display: inline-flex; align-items: center; gap: 9px; flex: 0 0 auto; transition: transform 0.16s; text-decoration: none; }
.lms-btn-cari:hover { transform: translateY(-2px); color: #fff; }

/* ═══ STAT STRIP ═══ */
.lms-stats { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; background: rgba(255, 255, 255, 0.7); border: 1px solid rgba(226, 232, 240, 0.7); border-radius: 20px; padding: 16px 22px; box-shadow: 0 6px 20px rgba(15, 23, 42, 0.04); }
.lms-stat { display: flex; align-items: center; gap: 13px; padding: 6px 4px; }
.lms-stat__ico { width: 44px; height: 44px; border-radius: 13px; flex: 0 0 auto; display: flex; align-items: center; justify-content: center; }
.lms-stat__val { font-size: 24px; font-weight: 800; color: #0f172a; letter-spacing: -0.02em; line-height: 1; }
.lms-stat__lbl { font-size: 12px; color: #8792a6; font-weight: 600; margin-top: 3px; white-space: nowrap; }

/* ═══ HERO LAMARAN AKTIF ═══ */
.lms-hero { position: relative; border-radius: 24px; overflow: hidden; background: #fff; border: 1px solid #eef0f7; box-shadow: 0 12px 34px rgba(15, 23, 42, 0.07); animation: lmsRiseIn 0.5s cubic-bezier(0.22, 1, 0.36, 1) both; }
@keyframes lmsRiseIn { from { opacity: 0; transform: translateY(16px); } to { opacity: 1; transform: translateY(0); } }
.lms-hero__bar { position: absolute; left: 0; top: 0; bottom: 0; width: 5px; background: linear-gradient(180deg, #8b5cf6, #6366f1); }
.lms-hero__glow { position: absolute; top: -70px; right: -40px; width: 240px; height: 240px; border-radius: 50%; background: radial-gradient(circle at 50% 50%, rgba(99, 102, 241, 0.08), rgba(99, 102, 241, 0) 70%); pointer-events: none; }
.lms-hero__in { position: relative; padding: 24px 28px 24px 30px; }
.lms-hero__toprow { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; flex-wrap: wrap; }
.lms-chip { display: inline-flex; align-items: center; gap: 7px; padding: 6px 12px; border-radius: 999px; font-size: 11.5px; font-weight: 800; }
.lms-pulse { width: 8px; height: 8px; border-radius: 50%; background: #f59e0b; animation: lmsPulse 2s infinite; }
@keyframes lmsPulse { 0%, 100% { box-shadow: 0 0 0 0 rgba(245, 158, 11, 0.5); } 70% { box-shadow: 0 0 0 8px rgba(245, 158, 11, 0); } }
.lms-hero__title { margin: 16px 0 0; font-size: 23px; font-weight: 800; color: #1e293b; letter-spacing: -0.02em; }
.lms-hero__desc { margin: 7px 0 0; font-size: 14px; line-height: 1.6; color: #64748b; max-width: 640px; }
.lms-metas { display: flex; flex-wrap: wrap; gap: 9px; margin-top: 16px; }
.lms-meta { display: inline-flex; align-items: center; gap: 7px; padding: 8px 13px; border-radius: 12px; background: #f6f7fb; color: #64748b; font-size: 12.5px; font-weight: 600; }
.lms-meta :deep(svg) { flex: 0 0 auto; }

.lms-prog { margin-top: 20px; background: #f8f9fc; border: 1px solid #eef0f7; border-radius: 18px; padding: 16px 18px; }
/* Segitiga bawaan <summary> diganti chevron sendiri agar sebaris dengan teks. */
.lms-prog__sum { cursor: pointer; list-style: none; }
.lms-prog__sum::-webkit-details-marker { display: none; }
.lms-prog__sum:focus-visible { outline: 2px solid #6366f1; outline-offset: 3px; border-radius: 10px; }
.lms-prog__count { display: inline-flex; align-items: center; gap: 6px; font-size: 13px; font-weight: 800; color: #4f46e5; }
.lms-prog__chev { transition: transform 0.24s cubic-bezier(0.22, 1, 0.36, 1); }
.lms-prog[open] .lms-prog__chev { transform: rotate(180deg); }
.lms-prog__head { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 12px; }
.lms-prog__head .lms-sec-label { letter-spacing: 0.1em; }
.lms-prog__bar { height: 7px; border-radius: 99px; background: #eef0f7; overflow: hidden; }
.lms-prog[open] .lms-steps { margin-top: 16px; animation: lmsAccIn 0.26s cubic-bezier(0.22, 1, 0.36, 1) both; }
/* Baris "sedang berjalan" — status tahap terbaca tanpa membuka stepper. Begitu
   stepper dibuka ia mubazir: tahap yang sama sudah ditandai di dalamnya. */
.lms-prog[open] .lms-prog__now { display: none; }
.lms-prog__now { display: flex; align-items: center; gap: 7px; margin-top: 11px; font-size: 12.5px; line-height: 1.35; color: #64748b; flex-wrap: wrap; }
.lms-prog__now strong { font-weight: 800; color: #1e293b; }
.lms-prog__now-dot { width: 8px; height: 8px; border-radius: 50%; flex: none; background: #f59e0b; box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.18); }
.lms-prog__now.is-fail .lms-prog__now-dot { background: #ef4444; box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.16); }
.lms-prog__now.is-fail strong { color: #dc2626; }
.lms-prog__now.is-done .lms-prog__now-dot { background: #10b981; box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.16); }
.lms-prog__now.is-todo .lms-prog__now-dot { background: #94a3b8; box-shadow: none; }
.lms-prog__now-lbl { flex: none; }
/* Anak <span> (bukan div): isi <summary> harus phrasing content agar HTML-nya sah. */
.lms-prog__bar > * { display: block; height: 100%; border-radius: 99px; background: linear-gradient(90deg, #8b5cf6, #6366f1); }
.lms-steps { display: flex; align-items: flex-start; gap: 0; overflow-x: auto; padding-bottom: 6px; }
.lms-step { flex: 0 0 130px; display: flex; flex-direction: column; align-items: center; position: relative; }
.lms-step__line { position: absolute; top: 14px; left: -50%; width: 100%; height: 3px; }
.lms-step__node { position: relative; z-index: 1; width: 30px; height: 30px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 800; background: #eef0f7; color: #94a3b8; }
.lms-step__node.is-done { background: linear-gradient(135deg, #8b5cf6, #6366f1); color: #fff; }
.lms-step__node.is-current { background: #fff; color: #4f46e5; border: 2px solid #f59e0b; box-shadow: 0 0 0 4px rgba(245, 158, 11, 0.18); }
.lms-step__node.is-fail { background: #fff; color: #dc2626; border: 2px solid #ef4444; box-shadow: 0 0 0 4px rgba(239, 68, 68, 0.14); }
.lms-step__lbl { margin-top: 9px; font-size: 10.5px; font-weight: 700; text-align: center; line-height: 1.3; padding: 0 6px; }

.lms-gugurnote { display: inline-flex; align-items: center; gap: 6px; margin-top: 14px; font-size: 12.5px; font-weight: 700; color: #dc2626; background: rgba(239, 68, 68, 0.08); border-radius: 10px; padding: 8px 12px; }
/* Pengunduran diri bukan penolakan — nadanya netral, bukan merah. */
.lms-gugurnote.is-netral { color: #475569; background: rgba(100, 116, 139, 0.1); }
.lms-btn-detail { appearance: none; cursor: pointer; font-family: inherit; font-size: 13.5px; font-weight: 800; color: #fff; background: linear-gradient(135deg, #8b5cf6, #6366f1); border: none; padding: 13px 24px; border-radius: 14px; display: inline-flex; align-items: center; gap: 9px; box-shadow: 0 10px 24px rgba(99, 102, 241, 0.3); transition: transform 0.16s; text-decoration: none; }
.lms-btn-detail:hover { transform: translateY(-2px); color: #fff; }
.lms-btn-hapus { appearance: none; cursor: pointer; width: 44px; height: 44px; border-radius: 13px; border: 1px solid #f4c9c9; background: #fff; color: #dc2626; display: inline-flex; align-items: center; justify-content: center; transition: all 0.16s; }
.lms-btn-hapus:hover:not(:disabled) { background: #dc2626; border-color: #dc2626; color: #fff; }
.lms-btn-hapus:disabled { opacity: 0.55; cursor: default; }
/* [feat/feedback] Inviting Warm Feedback CTA Button */
.lms-btn-feedback { appearance: none; cursor: pointer; font-family: inherit; font-size: 13.5px; font-weight: 800; color: #fff; background: linear-gradient(135deg, #f59e0b 0%, #f43f5e 100%); border: none; padding: 13px 24px; border-radius: 14px; display: inline-flex; align-items: center; gap: 8px; box-shadow: 0 10px 24px rgba(245, 158, 11, 0.35); transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1); text-decoration: none; animation: fb-cta-pulse 2.2s ease-in-out infinite; }
.lms-btn-feedback-sm { appearance: none; cursor: pointer; font-family: inherit; font-size: 11.5px; font-weight: 800; color: #fff; background: linear-gradient(135deg, #f59e0b 0%, #f43f5e 100%); border: none; padding: 6px 13px; border-radius: 10px; display: inline-flex; align-items: center; gap: 6px; box-shadow: 0 4px 12px rgba(245, 158, 11, 0.3); transition: all 0.2s ease; text-decoration: none; animation: fb-cta-pulse 2.5s ease-in-out infinite; }
@keyframes fb-cta-pulse { 0%, 100% { box-shadow: 0 10px 24px rgba(245, 158, 11, 0.35); } 50% { box-shadow: 0 12px 32px rgba(244, 63, 94, 0.55), 0 0 0 4px rgba(245, 158, 11, 0.2); } }
.lms-btn-feedback:hover, .lms-btn-feedback-sm:hover { transform: translateY(-2px) scale(1.02); color: #fff; background: linear-gradient(135deg, #fbbf24 0%, #e11d48 100%); }

/* ═══ RIWAYAT ═══ */
.lms-histrow { display: flex; align-items: center; justify-content: space-between; gap: 14px; flex-wrap: wrap; margin: 34px 2px 4px; }
.lms-fbtn { appearance: none; cursor: pointer; font-family: inherit; font-size: 12px; font-weight: 700; padding: 7px 14px; border-radius: 10px; transition: all 0.16s; border: 1px solid #e6e9f3; background: #fff; color: #64748b; }
.lms-fbtn.is-on { border-color: transparent; background: linear-gradient(135deg, #8b5cf6, #6366f1); color: #fff; }
.lms-tl { position: relative; margin-top: 14px; padding-left: 34px; }
.lms-tl__line { position: absolute; left: 15px; top: 6px; bottom: 6px; width: 2px; background: linear-gradient(180deg, #e2e8f0, #eef0f7); }
.lms-tl__item { position: relative; animation: lmsRiseIn 0.45s cubic-bezier(0.22, 1, 0.36, 1) both; }
.lms-tl__dot { position: absolute; left: -27px; top: 22px; width: 16px; height: 16px; border-radius: 50%; background: #fff; border: 3px solid #94a3b8; box-shadow: 0 0 0 4px #f4f6fc; }
.lms-hcard { appearance: none; cursor: pointer; font-family: inherit; width: 100%; background: #fff; border: 1px solid #eef0f7; border-radius: 18px; padding: 16px 18px; transition: all 0.18s; box-shadow: 0 3px 12px rgba(15, 23, 42, 0.04); }
.lms-hcard:hover { border-color: #d9def0; box-shadow: 0 12px 28px rgba(15, 23, 42, 0.08); transform: translateY(-1px); }
.lms-hcard__row { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; width: 100%; }
.lms-type { display: inline-block; font-size: 9.5px; font-weight: 800; letter-spacing: 0.08em; text-transform: uppercase; padding: 4px 9px; border-radius: 7px; }
.lms-hcard__title { font-size: 16px; font-weight: 800; color: #1e293b; letter-spacing: -0.01em; margin-top: 8px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.lms-hcard__meta { display: flex; align-items: center; gap: 14px; margin-top: 7px; font-size: 12px; color: #8792a6; flex-wrap: wrap; }
.lms-hcard__meta span { display: inline-flex; align-items: center; gap: 5px; }
.lms-pill { display: inline-block; padding: 5px 12px; border-radius: 999px; font-size: 11px; font-weight: 800; white-space: nowrap; }
/* Kolom kanan kartu riwayat & cap tanggal hero — dulu style inline, dijadikan
   kelas agar bisa ditata ulang di layar sempit (style inline tak bisa ditimpa
   media query). */
.lms-hcard__side { display: flex; flex-direction: column; align-items: flex-end; gap: 8px; flex: 0 0 auto; }
.lms-hero__stamp { text-align: right; color: #94a3b8; font-size: 12px; }

/* ═══ REKOMENDASI ═══ */
.lms-recrow { display: flex; align-items: center; gap: 9px; margin: 38px 2px 14px; }
.lms-recrow__ico { width: 30px; height: 30px; border-radius: 10px; background: linear-gradient(135deg, #8b5cf6, #6366f1); color: #fff; display: flex; align-items: center; justify-content: center; }
.lms-recs { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; }
.lms-rec { position: relative; background: #fff; border-radius: 20px; padding: 20px; box-shadow: 0 6px 20px rgba(15, 23, 42, 0.05); transition: all 0.2s; overflow: hidden; }
.lms-rec:hover { transform: translateY(-3px); box-shadow: 0 16px 36px rgba(15, 23, 42, 0.1); }
.lms-rec__glow { position: absolute; top: -40px; right: -40px; width: 130px; height: 130px; border-radius: 50%; pointer-events: none; }
.lms-rec__title { position: relative; font-size: 16.5px; font-weight: 800; color: #1e293b; letter-spacing: -0.01em; margin-top: 14px; }
.lms-rec__meta { position: relative; display: flex; align-items: center; gap: 12px; margin-top: 9px; font-size: 12.5px; color: #8792a6; }
.lms-rec__meta span { display: inline-flex; align-items: center; gap: 5px; }
.lms-rec__match { position: relative; display: flex; align-items: center; gap: 10px; margin-top: 16px; }
.lms-rec__matchbar { flex: 1; height: 7px; border-radius: 99px; background: #eef0f7; overflow: hidden; }
.lms-rec__matchbar div { height: 100%; border-radius: 99px; background: linear-gradient(90deg, #8b5cf6, #6366f1); }
.lms-rec__btn { position: relative; appearance: none; cursor: pointer; font-family: inherit; width: 100%; margin-top: 16px; font-size: 13.5px; font-weight: 800; color: #fff; background: linear-gradient(135deg, #8b5cf6, #6366f1); border: none; padding: 12px; border-radius: 13px; display: inline-flex; align-items: center; justify-content: center; gap: 8px; box-shadow: 0 8px 20px rgba(99, 102, 241, 0.28); transition: transform 0.16s; text-decoration: none; }
.lms-rec__btn:hover { transform: translateY(-2px); color: #fff; }

/* ═══ DRAWER ═══ */
.lms-overlay { position: fixed; inset: 0; z-index: 1055; background: rgba(15, 23, 42, 0.42); backdrop-filter: blur(2px); transition: opacity 0.32s; opacity: 0; pointer-events: none; }
.lms-overlay.is-on { opacity: 1; pointer-events: auto; }
.lms-drawer { position: fixed; top: 0; right: 0; bottom: 0; z-index: 1056; width: 560px; max-width: 100%; background: #f6f7fb; box-shadow: -30px 0 80px rgba(15, 23, 42, 0.2); overflow-y: auto; transition: transform 0.4s cubic-bezier(0.22, 1, 0.36, 1); transform: translateX(104%); }
.lms-drawer.is-on { transform: translateX(0); }
.lms-drawer__head { position: sticky; top: 0; z-index: 5; background: linear-gradient(180deg, #ffffff, #fbfbfe); border-bottom: 1px solid #eef0f7; padding: 18px 22px; }
.lms-drawer__ico { width: 50px; height: 50px; border-radius: 15px; background: linear-gradient(135deg, #8b5cf6, #6366f1); color: #fff; display: flex; align-items: center; justify-content: center; flex: 0 0 auto; box-shadow: 0 10px 24px rgba(99, 102, 241, 0.28); }
.lms-drawer__title { font-size: 18px; font-weight: 800; color: #0f172a; letter-spacing: -0.015em; margin-top: 6px; line-height: 1.25; }
.lms-drawer__close { appearance: none; border: 1px solid #e6e9f3; background: #fff; width: 38px; height: 38px; border-radius: 11px; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.16s; color: #64748b; flex: 0 0 auto; }
.lms-drawer__close:hover { background: #f1f5f9; color: #0f172a; }
.lms-drawer__body { padding: 20px 22px 40px; display: flex; flex-direction: column; gap: 20px; }
.lms-banner { display: flex; gap: 13px; padding: 15px 17px; border-radius: 16px; border: 1px solid transparent; }
.lms-banner__ico { width: 38px; height: 38px; border-radius: 11px; flex: 0 0 auto; display: flex; align-items: center; justify-content: center; color: #fff; }

.lms-stl { position: relative; padding-left: 30px; }
.lms-stl__line { position: absolute; left: 13px; top: 8px; bottom: 8px; width: 2px; background: #eef0f7; }
.lms-stl__node { position: absolute; left: -30px; top: 1px; width: 28px; height: 28px; border-radius: 50%; display: flex; align-items: center; justify-content: center; background: #fff; border: 3px solid #cbd2e0; }
.lms-stl__node span { width: 9px; height: 9px; border-radius: 50%; display: block; }
.lms-stl__tag { flex: 0 0 auto; font-size: 10.5px; font-weight: 800; padding: 4px 9px; border-radius: 999px; white-space: nowrap; }

.lms-form { background: #fff; border: 1px solid #eef0f7; border-radius: 18px; overflow: hidden; box-shadow: 0 2px 8px rgba(15, 23, 42, 0.03); transition: border-color 0.2s; }
.lms-form.is-open { border-color: #d9def0; }
.lms-form__head { appearance: none; border: none; background: #fff; width: 100%; display: flex; align-items: center; gap: 13px; padding: 15px 18px; cursor: pointer; text-align: left; font-family: inherit; }
.lms-form.is-open .lms-form__head { background: #fbfbff; }
.lms-form__step { width: 34px; height: 34px; border-radius: 10px; background: linear-gradient(135deg, #cbd2e0, #94a3b8); color: #fff; font-size: 13px; font-weight: 800; display: flex; align-items: center; justify-content: center; flex: 0 0 auto; }
.lms-form__step.is-ok { background: linear-gradient(135deg, #8b5cf6, #6366f1); }
.lms-form__title { display: block; font-size: 14.5px; font-weight: 800; color: #1e293b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.lms-form__sub { display: block; font-size: 11.5px; color: #8b93a7; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.lms-form__pill { flex: 0 0 auto; font-size: 10.5px; font-weight: 800; letter-spacing: 0.04em; padding: 4px 10px; border-radius: 999px; white-space: nowrap; }
.lms-form__pill.is-ok { background: rgba(16, 185, 129, 0.12); color: #059669; }
.lms-form__pill.is-wait { background: rgba(245, 158, 11, 0.14); color: #b45309; }
.lms-form__chev { flex: 0 0 auto; transition: transform 0.26s; }
.lms-form.is-open .lms-form__chev { transform: rotate(180deg); }
.lms-form__body { padding: 6px 18px 18px; animation: lmsAccIn 0.28s cubic-bezier(0.22, 1, 0.36, 1) both; }
@keyframes lmsAccIn { from { opacity: 0; transform: translateY(-6px); } to { opacity: 1; transform: translateY(0); } }
.lms-fields { display: grid; grid-template-columns: 1fr 1fr; gap: 1px; background: #eef0f7; border: 1px solid #eef0f7; border-radius: 14px; overflow: hidden; margin-top: 8px; }
.lms-field { background: #fff; padding: 11px 14px; min-width: 0; }
.lms-field__k { font-size: 10.5px; font-weight: 700; letter-spacing: 0.08em; color: #a2a9ba; text-transform: uppercase; }
.lms-field__v { font-size: 13.5px; font-weight: 700; color: #1e293b; margin-top: 3px; word-break: break-word; }
.lms-doc { display: flex; align-items: center; gap: 13px; padding: 12px 14px; border: 1px solid #eef0f7; border-radius: 14px; background: #fbfbfe; }
.lms-doc__ico { width: 40px; height: 40px; border-radius: 11px; display: flex; align-items: center; justify-content: center; flex: 0 0 auto; }
.lms-doc__ico.is-img { background: rgba(99, 102, 241, 0.12); }
.lms-doc__ico.is-pdf { background: rgba(239, 68, 68, 0.1); }
.lms-doc__name { font-size: 13.5px; font-weight: 800; color: #1e293b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.lms-doc__ext { font-size: 9px; font-weight: 800; letter-spacing: 0.06em; color: #8b93a7; background: #eef0f7; border-radius: 5px; padding: 2px 6px; flex: 0 0 auto; }
.lms-doc__desc { font-size: 11.5px; color: #8b93a7; margin-top: 2px; line-height: 1.4; }
.lms-doc__eye { appearance: none; border: 1px solid #d9def0; background: #fff; width: 38px; height: 38px; border-radius: 11px; display: flex; align-items: center; justify-content: center; cursor: pointer; color: #6366f1; flex: 0 0 auto; transition: all 0.16s; }
.lms-doc__eye:hover { background: #6366f1; color: #fff; border-color: #6366f1; }

/* ═══ LIGHTBOX ═══ */
.lms-lb { position: fixed; inset: 0; z-index: 1090; display: flex; align-items: center; justify-content: center; padding: 24px; background: rgba(10, 10, 20, 0.72); backdrop-filter: blur(6px); transition: opacity 0.28s; opacity: 0; pointer-events: none; }
.lms-lb.is-on { opacity: 1; pointer-events: auto; }
.lms-lb__wrap { max-width: 520px; width: 100%; animation: lmsPop 0.3s cubic-bezier(0.34, 1.56, 0.64, 1) both; }
@keyframes lmsPop { 0% { opacity: 0; transform: scale(0.9); } 60% { transform: scale(1.02); } 100% { opacity: 1; transform: scale(1); } }
.lms-lb__bar { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 14px; }
.lms-lb__ico { width: 40px; height: 40px; border-radius: 11px; background: rgba(255, 255, 255, 0.14); display: flex; align-items: center; justify-content: center; flex: 0 0 auto; }
.lms-lb__close { appearance: none; border: 1px solid rgba(255, 255, 255, 0.2); background: rgba(255, 255, 255, 0.08); width: 40px; height: 40px; border-radius: 12px; display: flex; align-items: center; justify-content: center; cursor: pointer; color: #fff; flex: 0 0 auto; transition: background 0.16s; }
.lms-lb__close:hover { background: rgba(255, 255, 255, 0.18); }
.lms-lb__card { background: #fff; border-radius: 18px; overflow: hidden; box-shadow: 0 30px 80px rgba(0, 0, 0, 0.5); }
.lms-lb__ph { width: 100%; height: 360px; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 10px; background: linear-gradient(135deg, #f1f2f9, #e8eaf6); color: #94a3b8; font-size: 13px; font-weight: 700; }
.lms-lb__foot { padding: 14px 18px; display: flex; align-items: center; justify-content: space-between; gap: 10px; border-top: 1px solid #eef0f7; }

/* ═══ MODAL HAPUS + TOAST ═══ */
.lms-modal { position: fixed; inset: 0; z-index: 1095; background: rgba(15, 23, 42, 0.45); backdrop-filter: blur(2px); display: flex; align-items: center; justify-content: center; padding: 1rem; }
.lms-modal__box { width: 100%; max-width: 26rem; background: #fff; border-radius: 20px; padding: 1.6rem; text-align: center; box-shadow: 0 24px 60px -20px rgba(15, 23, 42, 0.5); animation: lmsPop 0.3s cubic-bezier(0.34, 1.56, 0.64, 1) both; }
.lms-modal__ic { width: 52px; height: 52px; margin: 0 auto 0.9rem; border-radius: 15px; display: flex; align-items: center; justify-content: center; background: rgba(239, 68, 68, 0.1); color: #dc2626; }
.lms-modal__box h3 { margin: 0 0 0.4rem; font-size: 17px; font-weight: 800; color: #0f172a; letter-spacing: -0.015em; }
.lms-modal__box p { font-size: 13px; color: #64748b; line-height: 1.55; margin: 0 0 1.2rem; }
.lms-modal__act { display: flex; gap: 0.6rem; justify-content: center; }
.lms-modal__soft { appearance: none; cursor: pointer; font-family: inherit; font-size: 13px; font-weight: 700; padding: 11px 18px; border-radius: 12px; border: 1px solid #e6e9f3; background: #fff; color: #64748b; transition: background 0.16s; }
.lms-modal__soft:hover { background: #f6f7fb; }
.lms-modal__danger { appearance: none; cursor: pointer; font-family: inherit; font-size: 13px; font-weight: 800; padding: 11px 18px; border-radius: 12px; border: none; background: linear-gradient(135deg, #f87171, #ef4444); color: #fff; display: inline-flex; align-items: center; gap: 7px; box-shadow: 0 8px 20px rgba(239, 68, 68, 0.28); }
.lms-modal__danger:disabled { opacity: 0.6; cursor: default; }

/* Lapis toast bersama — lihat --wca-z-toast di evo-theme.css. Portal punya
   lightbox berkas (.lms-lb 1090) dan modal sendiri (1095) yang dulu sama-sama
   di bawah 1300, tapi angkanya disamakan supaya tidak ada lagi dua jawaban. */
.lms-toast {
    position: fixed; bottom: 24px; right: 24px; z-index: var(--wca-z-toast, 100000);
    display: flex; align-items: center; gap: 9px; padding: 12px 18px;
    max-width: min(520px, calc(100vw - 48px));
    border-radius: 13px; background: #0f172a; color: #fff;
    font-size: 13.5px; font-weight: 700; line-height: 1.5;
    box-shadow: 0 18px 40px rgba(0, 0, 0, 0.3);
}
.lms-toast.is-err { background: #dc2626; }
.lms-toast .bi { color: #34d399; }
.lms-toast.is-err .bi { color: #fff; }
.lms-toast-enter-active, .lms-toast-leave-active { transition: opacity 0.25s, transform 0.25s; }
.lms-toast-enter-from, .lms-toast-leave-to { opacity: 0; transform: translateY(12px); }

/* ═══ RESPONSIF ═══ */
@media (max-width: 1179.98px) {
    .lms-recs { grid-template-columns: repeat(2, 1fr); }
    .lms-drawer { width: 460px; }
}
/* ═══ TABLET & PONSEL — bukan versi mengecil dari desktop ═══
   Tiga bentuk di halaman ini memang lahir untuk layar lebar dan tidak pernah
   jadi benar sekadar dengan dikecilkan; di sini ketiganya diganti bentuk. */
@media (max-width: 759.98px) {
    .lms { padding: 20px 16px 44px; }
    .lms-stats { grid-template-columns: 1fr 1fr; gap: 14px; }
    .lms-recs { grid-template-columns: 1fr; }
    .lms-drawer { width: 100%; }
    .lms-fields { grid-template-columns: 1fr; }
    .lms-hero__in { padding: 20px 18px; }

    /* 1. STEPPER: mendatar → menurun.
       Bentuk mendatar butuh 130px per tahap; pada 5-6 tahap isinya tersembunyi
       di balik gulir samping yang tak terlihat, padahal ini informasi utama
       halaman. Menurun: semua tahap terbaca sekaligus, label boleh panjang. */
    .lms-steps { flex-direction: column; align-items: stretch; gap: 15px; overflow-x: visible; padding-bottom: 0; }
    .lms-step { flex: 0 0 auto; flex-direction: row; align-items: center; gap: 12px; width: 100%; }
    .lms-step__line { top: auto; bottom: calc(100% + 1px); left: 13.5px; width: 3px; height: 15px; }
    .lms-step__node { flex: 0 0 auto; }
    .lms-step__lbl { margin-top: 0; padding: 0; text-align: left; font-size: 12.5px; }

    /* 2. RIWAYAT: rel timeline dibuang.
       Garis + titiknya memakan 34px lebar (±10% layar 360px) hanya untuk
       menegaskan urutan yang sudah jelas dari urutan kartunya sendiri. */
    .lms-tl { padding-left: 0; }
    .lms-tl__line, .lms-tl__dot { display: none; }

    /* 3. AKSI KARTU: tombol utama selebar kartu, hapus tetap ikon di ujung. */
    .lms-btn-detail { flex: 1 1 auto; justify-content: center; }
    .lms-btn-hapus { margin-left: auto; }

    /* 4. AKSEN KARTU: pita kiri → garis atas. Setinggi kartu ia ikut memanjang
       mengikuti isi; di layar sempit kartunya paling panjang dan pita itu
       terbaca seperti kesalahan render. Di layar lebar bentuk aslinya dipakai. */
    .lms-hero__bar { right: 0; bottom: auto; width: auto; height: 4px; background: linear-gradient(90deg, #8b5cf6, #6366f1); }
    .lms-hero__in { padding-left: 18px; }
}
/* Tablet ke atas: stepper bukan dropdown — selalu terbuka & tak bisa dilipat. */
@media (min-width: 768px) {
    .lms-prog__sum { cursor: default; pointer-events: none; }
    .lms-prog__chev { display: none; }
    .lms-prog__bar { margin-bottom: 16px; }
    .lms-prog[open] .lms-steps { margin-top: 0; animation: none; }
}

/* ═══ PONSEL ═══ */
@media (max-width: 560px) {
    .lms { padding: 18px 12px 40px; }
    .lms-h1 { font-size: 23px; }
    .lms-sub { font-size: 13px; }
    .lms-btn-cari { width: 100%; justify-content: center; }

    /* Strip statistik: 2×2 dengan angka & ikon lebih ringkas. `nowrap` pada
       label membuat "Total Lamaran" meluap keluar kolomnya di 360px. */
    .lms-stats { padding: 14px; gap: 12px 10px; border-radius: 18px; }
    .lms-stat { gap: 10px; padding: 4px 0; }
    .lms-stat__ico { width: 38px; height: 38px; border-radius: 11px; }
    .lms-stat__val { font-size: 20px; }
    .lms-stat__lbl { font-size: 11px; white-space: normal; }

    /* Kartu lamaran aktif */
    .lms-hero { border-radius: 18px; }
    .lms-hero__in { padding: 18px 14px; }
    .lms-hero__title { font-size: 19px; }
    .lms-hero__desc { font-size: 13px; }
    /* Setelah membungkus ke baris sendiri, rata kanan terbaca seperti salah tempat. */
    .lms-hero__stamp { text-align: left; }
    .lms-prog { padding: 14px 12px; border-radius: 14px; }
    .lms-prog__head { flex-wrap: wrap; gap: 4px 10px; }
    .lms-btn-feedback { flex: 1 1 12rem; justify-content: center; }

    /* Riwayat: kolom kanan turun jadi baris sendiri. */
    .lms-histrow { gap: 10px; margin-top: 26px; }
    /* Strip filter digeser satu baris, bukan menumpuk jadi dua-tiga baris. */
    .lms-histrow > div:last-child { flex-wrap: nowrap; overflow-x: auto; scrollbar-width: none; margin-inline: -12px; padding: 2px 12px 4px; width: 100%; }
    .lms-histrow > div:last-child::-webkit-scrollbar { display: none; }
    .lms-fbtn { min-height: 2.25rem; flex: 0 0 auto; }
    .lms-hcard { padding: 14px; border-radius: 15px; }
    .lms-hcard__row { flex-direction: column; gap: 10px; }
    .lms-hcard__side { flex-direction: row; align-items: center; justify-content: space-between; width: 100%; }
    /* Judul posisi lebih penting daripada tinggi kartu — biarkan turun baris. */
    .lms-hcard__title { white-space: normal; }
    .lms-hcard__meta { gap: 6px 12px; }

    .lms-modal__act { flex-direction: column-reverse; }
    .lms-modal__soft, .lms-modal__danger { width: 100%; justify-content: center; }
}

/* PONSEL — toast sudut melebar penuh. Pada 360px, lebar sudut hanya menyisakan
   ruang teks selebar dua kata dan pesan panjang terpotong jadi banyak baris
   sempit. Lihat --wca-z-toast di evo-theme.css untuk lapisannya. */
@media (max-width: 560px) {
    .lms-toast {
        left: 12px;
        right: 12px;
        max-width: none;
        align-items: flex-start;
    }
    .lms-toast .bi { flex: none; margin-top: 1px; }
}
</style>
