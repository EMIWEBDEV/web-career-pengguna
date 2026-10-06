<!-- WEB CAREER — Portal Kandidat: DETAIL LAMARAN TERKIRIM
     Full sessionStorage: tahapan (app.pipeline) + progres (stageIdx/result) real-time.
     Palet calm/elegan. Tahap TES → tautan CAT eksternal. Tahap FORM2 → formulir lanjutan. -->
<template>
    <Head title="Detail Lamaran" />
    <div class="wca wct">
        <Link href="/kandidat/portal" class="wct-back"><i class="bi bi-arrow-left"></i> Kembali ke Lamaran Saya</Link>

        <div v-if="!app" class="wca-empty2">
            <div class="wca-empty2__ic"><i class="bi bi-file-earmark-x"></i></div>
            <h3>Lamaran tidak ditemukan</h3>
            <p>Data lamaran ini tidak ada di sesi peramban kamu. Mungkin kamu membukanya di perangkat/tab berbeda.</p>
            <Link href="/kandidat/portal" class="wca-btn wca-btn--primary"><i class="bi bi-arrow-left"></i> Ke Lamaran Saya</Link>
        </div>

        <template v-else>
            <!-- ══ HERO (calm) ══ -->
            <header class="wct-hero" :class="'is-' + resultKey">
                <div class="wct-hero__accent"></div>
                <div class="wct-hero__main">
                    <div class="wct-hero__badges">
                        <span class="wca-badge" :class="jenis === 'MT' ? 'wca-b--gold' : 'wca-b--sky'">{{ jenis === 'MT' ? 'Management Trainee' : 'Rekrutmen' }}</span>
                        <span class="wca-badge" :class="resultBadge"><i class="bi" :class="resultIcon"></i> {{ resultText }}</span>
                    </div>
                    <h1>{{ app.posisi }}</h1>
                    <p class="wct-hero__meta">
                        <span><i class="bi bi-building"></i> {{ catalog?.perusahaan || app.program }}</span>
                        <span v-if="catalog?.batch"><i class="bi bi-collection"></i> {{ catalog.batch }}</span>
                        <span><i class="bi bi-geo-alt"></i> {{ catalog?.lokasi || app.lokasi }}</span>
                        <span><i class="bi bi-calendar3"></i> Melamar {{ app.appliedAt }}</span>
                    </p>
                </div>
                <div class="wct-hero__now">
                    <small>Tahap saat ini</small>
                    <div class="wct-hero__stage"><i class="bi" :class="tipeIcon(current.tipe)"></i> {{ current.label }}</div>
                    <div class="wct-hero__prog"><span :style="{ width: progressPct + '%' }"></span></div>
                    <small class="wct-hero__pct">{{ curIdx }} dari {{ pipeline.length }} tahap terlewati · {{ progressPct }}%</small>
                </div>
            </header>

            <!-- Alert status / pengumuman -->
            <div v-if="alert" class="wct-alert" :class="'wct-alert--' + alert.kind">
                <span class="wct-alert__ic"><i class="bi" :class="alert.icon"></i></span>
                <div class="wct-alert__body">
                    <div class="wct-alert__top"><span class="wct-alert__tag">{{ alert.tag }}</span><span v-if="alert.date" class="wct-alert__date"><i class="bi bi-calendar3"></i> {{ fmtDateLong(alert.date) }}</span></div>
                    <strong>{{ alert.title }}</strong>
                    <p>{{ alert.msg }}</p>
                </div>
            </div>

            <div class="wct-grid">
                <!-- ══ KIRI ══ -->
                <div class="wct-col">
                    <section class="wct-card">
                        <h2 class="wct-h2"><i class="bi bi-signpost-split"></i> Tahapan Seleksi</h2>
                        <ol class="wct-steps">
                            <li v-for="(p, i) in pipeline" :key="i" class="wct-step" :class="stepState(i)">
                                <span class="wct-step__node"><i class="bi" :class="i < curIdx ? 'bi-check-lg' : tipeIcon(p.tipe)"></i></span>
                                <div class="wct-step__body">
                                    <div class="wct-step__top">
                                        <strong>{{ p.label }}</strong>
                                        <span class="wct-step__tag">{{ tipeLabel(p.tipe) }}</span>
                                    </div>
                                    <div class="wct-step__foot">
                                        <small v-if="jadwalFor(i)" class="wct-step__date"><i class="bi bi-calendar-event"></i> {{ jadwalFor(i) }}</small>
                                        <span v-if="i === curIdx && !isFailed" class="wct-chipstat wct-chipstat--now"><i class="bi bi-geo-alt-fill"></i> Posisi Anda sekarang</span>
                                        <span v-else-if="i === curIdx && isFailed" class="wct-chipstat wct-chipstat--fail"><i class="bi bi-x-circle"></i> Tidak lolos</span>
                                        <span v-else-if="i < curIdx" class="wct-chipstat wct-chipstat--ok"><i class="bi bi-check-circle"></i> Selesai</span>
                                        <span v-else class="wct-chipstat wct-chipstat--wait"><i class="bi bi-clock"></i> Menunggu</span>
                                    </div>
                                </div>
                            </li>
                        </ol>
                    </section>

                    <section v-if="timeline.length" class="wct-card">
                        <h2 class="wct-h2"><i class="bi bi-calendar3-range"></i> Jadwal Kegiatan <small class="wct-h2__sub">gaya waterfall</small></h2>
                        <div class="wct-gantt">
                            <div v-for="(t, i) in timeline" :key="i" class="wct-gantt__row" :class="ganttState(i)">
                                <div class="wct-gantt__label">
                                    <span class="wct-gantt__idx">{{ i + 1 }}</span>
                                    <div>
                                        <strong>{{ t.label }}</strong>
                                        <small v-if="t.tanggal">{{ t.tanggal }}</small>
                                    </div>
                                </div>
                                <div class="wct-gantt__track">
                                    <div class="wct-gantt__bar" :style="barStyle(i)">
                                        <span v-if="i === curIdx && !isFailed" class="wct-gantt__nowdot"></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="wct-gantt__legend">
                            <span><i class="wct-dot wct-dot--done"></i> Selesai</span>
                            <span><i class="wct-dot wct-dot--now"></i> Sedang berjalan</span>
                            <span><i class="wct-dot wct-dot--wait"></i> Akan datang</span>
                        </div>
                    </section>

                    <section class="wct-card">
                        <h2 class="wct-h2"><i class="bi bi-person-vcard"></i> Biodata Pelamar</h2>
                        <div class="wct-bio">
                            <div v-if="app.facePhoto" class="wct-bio__face"><img :src="app.facePhoto" alt="Foto wajah" /></div>
                            <div class="wct-bio__grid">
                                <div v-for="(b, i) in bio" :key="i" class="wct-bio__item"><small>{{ b.label }}</small><b>{{ b.value }}</b></div>
                                <div v-if="!bio.length" class="wct-empty-inline"><i class="bi bi-info-circle"></i> Rincian biodata tidak tersimpan pada sesi ini.</div>
                            </div>
                        </div>
                        <template v-if="experiences.length">
                            <h3 class="wct-h3"><i class="bi bi-briefcase"></i> Pengalaman</h3>
                            <div class="wct-xp">
                                <div v-for="(x, i) in experiences" :key="i" class="wct-xp__item">
                                    <span class="wct-xp__dot"></span>
                                    <div>
                                        <strong>{{ x.posisi }}</strong>
                                        <div class="wct-xp__co"><i class="bi bi-building"></i> {{ x.perusahaan }} <span class="wct-xp__dur">{{ x.durasi }}</span></div>
                                        <small class="wct-xp__per">{{ x.periode }}</small>
                                        <p v-if="x.desc">{{ x.desc }}</p>
                                    </div>
                                </div>
                            </div>
                        </template>
                        <template v-if="fileList.length">
                            <h3 class="wct-h3"><i class="bi bi-paperclip"></i> Berkas Terlampir</h3>
                            <div class="wct-files">
                                <span v-for="f in fileList" :key="f.key" class="wct-file"><i class="bi" :class="f.isPdf ? 'bi-file-earmark-pdf' : 'bi-file-earmark-image'"></i> {{ f.name }}</span>
                            </div>
                        </template>
                    </section>
                </div>

                <!-- ══ KANAN ══ -->
                <aside class="wct-col wct-col--side">
                    <!-- Tindak lanjut (state-aware) -->
                    <section class="wct-card wct-cta" :class="'wct-cta--' + resultKey">
                        <h2 class="wct-h2"><i class="bi" :class="isFailed ? 'bi-emoji-frown' : (isPassed ? 'bi-trophy' : 'bi-bell-fill')"></i> {{ isFailed ? 'Hasil Seleksi' : (isPassed ? 'Selamat!' : 'Tindak Lanjut') }}</h2>
                        <p class="wct-next">{{ nextAction }}</p>

                        <!-- TES aktif → panel akses CAT (jadwal, hitung mundur, token & OTP) -->
                        <div v-if="showTest && test" class="wct-test">
                            <div class="wct-test__sched">
                                <div v-if="testSched"><small><i class="bi bi-calendar-event"></i> Jadwal Tes</small><b>{{ testSched }}</b></div>
                                <div><small><i class="bi bi-unlock"></i> Akses Dibuka</small><b>{{ fmtDT(test.mulai) }}</b></div>
                                <div><small><i class="bi bi-hourglass-bottom"></i> Kedaluwarsa</small><b>{{ fmtDT(test.selesai) }}</b></div>
                            </div>

                            <div class="wct-test__count" :class="{ 'is-exp': expired }">
                                <i class="bi" :class="expired ? 'bi-lock-fill' : 'bi-stopwatch'"></i>
                                <span v-if="!expired">Sisa waktu akses: <b>{{ countdown }}</b></span>
                                <span v-else>Tautan tes telah <b>kedaluwarsa</b>. Silakan hubungi tim rekrutmen untuk penjadwalan ulang.</span>
                            </div>

                            <div class="wct-cred">
                                <div class="wct-cred__row">
                                    <div class="wct-cred__val">
                                        <small><i class="bi bi-key"></i> Token Akses</small>
                                        <code :class="{ masked: !showToken }">{{ showToken ? test.token : mask(test.token) }}</code>
                                    </div>
                                    <div class="wct-cred__act">
                                        <button type="button" class="wct-icbtn" :title="showToken ? 'Sembunyikan' : 'Tampilkan'" @click="showToken = !showToken"><i class="bi" :class="showToken ? 'bi-eye-slash' : 'bi-eye'"></i></button>
                                        <button type="button" class="wct-copy" :class="{ ok: copied === 'token' }" @click="copy(test.token, 'token')"><i class="bi" :class="copied === 'token' ? 'bi-check2' : 'bi-clipboard'"></i> {{ copied === 'token' ? 'Tersalin' : 'Salin' }}</button>
                                    </div>
                                </div>
                                <div class="wct-cred__row">
                                    <div class="wct-cred__val">
                                        <small><i class="bi bi-shield-lock"></i> Kode OTP</small>
                                        <code :class="{ masked: !showOtp }">{{ showOtp ? test.otp : mask(test.otp) }}</code>
                                    </div>
                                    <div class="wct-cred__act">
                                        <button type="button" class="wct-icbtn" :title="showOtp ? 'Sembunyikan' : 'Tampilkan'" @click="showOtp = !showOtp"><i class="bi" :class="showOtp ? 'bi-eye-slash' : 'bi-eye'"></i></button>
                                        <button type="button" class="wct-copy" :class="{ ok: copied === 'otp' }" @click="copy(test.otp, 'otp')"><i class="bi" :class="copied === 'otp' ? 'bi-check2' : 'bi-clipboard'"></i> {{ copied === 'otp' ? 'Tersalin' : 'Salin' }}</button>
                                    </div>
                                </div>
                            </div>

                            <a v-if="!expired" :href="CAT_URL" target="_blank" rel="noopener" class="wca-btn wca-btn--primary wca-btn--full wct-testbtn">
                                <i class="bi bi-box-arrow-up-right"></i> Akses Tes Online (CAT)
                            </a>
                            <button v-else type="button" class="wca-btn wca-btn--full wct-testbtn is-disabled" disabled>
                                <i class="bi bi-lock-fill"></i> Akses Ditutup
                            </button>
                            <small class="wct-hint2">Masukkan <b>Token</b> &amp; <b>OTP</b> di halaman tes. Tautan membuka platform CAT di tab baru — selesaikan sebelum kedaluwarsa.</small>
                        </div>

                        <!-- FORM2 aktif → formulir tahap lanjut -->
                        <Link v-if="showForm2" :href="`/karir/apply/${programId}?form=2`" class="wca-btn wca-btn--primary wca-btn--full">
                            <i class="bi bi-pencil-square"></i> Isi Formulir Tahap Lanjut (Form 2)
                        </Link>
                        <small v-if="showForm2" class="wct-hint2">Lengkapi data & dokumen tambahan agar bisa lanjut ke tahap berikutnya.</small>

                        <div v-if="catalog?.tanggalPengumuman && !isFailed && !isPassed && !showTest && !showForm2" class="wct-announce">
                            <span class="wct-announce__ic"><i class="bi bi-megaphone-fill"></i></span>
                            <div><small>Pengumuman Hasil Seleksi</small><b>{{ fmtDateLong(catalog.tanggalPengumuman) }}</b></div>
                        </div>
                    </section>

                    <section v-if="catalog" class="wct-card">
                        <h2 class="wct-h2"><i class="bi bi-info-circle"></i> Tentang Program</h2>
                        <p class="wct-desc">{{ catalog.ringkasan || catalog.deskripsi }}</p>
                        <div class="wct-chips">
                            <span v-if="catalog.durasi" class="wct-chip"><i class="bi bi-clock-history"></i> {{ catalog.durasi }}</span>
                            <span v-if="catalog.ikatan" class="wct-chip"><i class="bi bi-link-45deg"></i> {{ catalog.ikatan }}</span>
                            <span v-if="catalog.penempatan" class="wct-chip"><i class="bi bi-pin-map"></i> {{ catalog.penempatan }}</span>
                            <span v-if="catalog.tipeKegiatan" class="wct-chip"><i class="bi bi-mortarboard"></i> {{ catalog.tipeKegiatan }}</span>
                            <span v-if="catalog.level" class="wct-chip"><i class="bi bi-bar-chart-steps"></i> {{ catalog.level }}</span>
                        </div>
                        <template v-if="catalog.benefit?.length">
                            <h3 class="wct-h3"><i class="bi bi-gift"></i> Benefit</h3>
                            <ul class="wct-ul"><li v-for="(b, i) in catalog.benefit" :key="i"><i class="bi bi-check-circle-fill"></i> {{ b }}</li></ul>
                        </template>
                        <template v-if="catalog.kriteria?.length">
                            <h3 class="wct-h3"><i class="bi bi-list-check"></i> Kriteria</h3>
                            <ul class="wct-ul wct-ul--dot"><li v-for="(k, i) in catalog.kriteria" :key="i"><i class="bi bi-dot"></i> {{ k }}</li></ul>
                        </template>
                    </section>
                </aside>
            </div>
        </template>
    </div>
</template>

<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { computed, onMounted, onBeforeUnmount, ref } from 'vue';
import { CAT_URL, ensureTestSession, getApp, nextActionFor } from '@utils/career/session';

const props = defineProps({
    programId: { type: String, default: '' },
    catalog: { type: Object, default: null },
    stages: { type: Array, default: () => [] },
});

const app = ref(null);
const test = ref(null);
const nowTs = ref(Date.now());
let clock = null;
function refresh() {
    app.value = getApp(props.programId);
    test.value = current.value.tipe === 'TES' ? ensureTestSession(props.programId) : null;
}
onMounted(() => {
    refresh();
    clock = setInterval(() => { nowTs.value = Date.now(); }, 1000);
    window.addEventListener('focus', refresh);
    document.addEventListener('visibilitychange', refresh);
});
onBeforeUnmount(() => {
    if (clock) clearInterval(clock);
    window.removeEventListener('focus', refresh);
    document.removeEventListener('visibilitychange', refresh);
});

// ── Sesi tes: jadwal, hitung mundur, salin token/OTP ──
const testSched = computed(() => (props.catalog?.jadwal || [])[curIdx.value]?.tanggal || null);
const expired = computed(() => !!test.value && nowTs.value > test.value.selesai);
const countdown = computed(() => {
    if (!test.value) return '';
    let ms = test.value.selesai - nowTs.value;
    if (ms < 0) ms = 0;
    const d = Math.floor(ms / 86400000);
    const h = Math.floor((ms % 86400000) / 3600000);
    const m = Math.floor((ms % 3600000) / 60000);
    const s = Math.floor((ms % 60000) / 1000);
    return (d > 0 ? `${d} hari ` : '') + `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
});
const HARI = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];
const BLN = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
function fmtDT(ts) {
    const d = new Date(ts);
    return `${HARI[d.getDay()]}, ${d.getDate()} ${BLN[d.getMonth()]} ${d.getFullYear()} · ${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')} WIB`;
}
// Sembunyi/tampil kredensial (masked default)
const showToken = ref(false);
const showOtp = ref(false);
function mask(v) { return v ? '•'.repeat(v.length) : ''; }

const HARI_FULL = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
const BLN_FULL = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
function fmtDateLong(iso) {
    if (!iso) return '';
    const d = new Date(iso + 'T00:00:00');
    if (isNaN(d.getTime())) return iso;
    return `${HARI_FULL[d.getDay()]}, ${d.getDate()} ${BLN_FULL[d.getMonth()]} ${d.getFullYear()}`;
}

const copied = ref('');
let copyTm = null;
function copy(text, key) {
    const done = () => { copied.value = key; clearTimeout(copyTm); copyTm = setTimeout(() => (copied.value = ''), 1800); };
    if (navigator.clipboard?.writeText) {
        navigator.clipboard.writeText(text).then(done).catch(fallbackCopy.bind(null, text, done));
    } else { fallbackCopy(text, done); }
}
function fallbackCopy(text, done) {
    try {
        const ta = document.createElement('textarea');
        ta.value = text; ta.style.position = 'fixed'; ta.style.opacity = '0';
        document.body.appendChild(ta); ta.select(); document.execCommand('copy'); document.body.removeChild(ta);
        done();
    } catch { /* noop */ }
}

const jenis = computed(() => app.value?.jenis || props.catalog?.jenis || 'REKRUTMEN');

// Tahapan = pipeline tersimpan di sessionStorage (admin-aligned). Fallback ke katalog/stages.
const pipeline = computed(() => app.value?.pipeline?.length ? app.value.pipeline : (props.catalog?.pipeline?.length ? props.catalog.pipeline : (props.stages || []).map((s) => ({ tipe: s.key, label: s.label }))));
const curIdx = computed(() => Math.min(app.value?.stageIdx || 0, Math.max(0, pipeline.value.length - 1)));
const current = computed(() => pipeline.value[curIdx.value] || { label: '—', tipe: null });

const resultKey = computed(() => (app.value?.result === 'GAGAL' ? 'fail' : (app.value?.result === 'LULUS' ? 'pass' : 'run')));
const isFailed = computed(() => resultKey.value === 'fail');
const isPassed = computed(() => resultKey.value === 'pass');
const resultText = computed(() => ({ fail: 'Tidak Lolos', pass: 'Lulus', run: 'Sedang Berjalan' }[resultKey.value]));
const resultBadge = computed(() => ({ fail: 'wca-b--red', pass: 'wca-b--green', run: 'wca-b--indigo' }[resultKey.value]));
const resultIcon = computed(() => ({ fail: 'bi-x-circle', pass: 'bi-check-circle', run: 'bi-hourglass-split' }[resultKey.value]));

const progressPct = computed(() => {
    if (isFailed.value) return Math.round((curIdx.value / Math.max(1, pipeline.value.length)) * 100);
    if (isPassed.value) return 100;
    return Math.round(((curIdx.value + 0.5) / Math.max(1, pipeline.value.length)) * 100);
});
function stepState(i) {
    if (isFailed.value && i === curIdx.value) return 'fail';
    return i < curIdx.value ? 'done' : (i === curIdx.value ? 'current' : 'wait');
}
const nextAction = computed(() => app.value?.nextAction || nextActionFor(app.value));

// Banner alert (pengumuman resmi diprioritaskan, lalu hasil lulus/gagal)
const alert = computed(() => {
    const a = app.value;
    if (!a) return null;
    if (a.pengumuman) {
        const h = a.pengumuman.hasil;
        const k = h === 'LULUS' ? 'pass' : (h === 'GAGAL' ? 'fail' : 'info');
        return { kind: k, icon: k === 'pass' ? 'bi-trophy-fill' : (k === 'fail' ? 'bi-emoji-frown-fill' : 'bi-megaphone-fill'), tag: 'Pengumuman Resmi', title: a.pengumuman.judul, msg: a.pengumuman.isi || nextAction.value, date: a.pengumuman.tanggal };
    }
    if (isPassed.value) return { kind: 'pass', icon: 'bi-trophy-fill', tag: 'Hasil Akhir', title: 'Selamat! Anda Dinyatakan LULUS', msg: nextAction.value };
    if (isFailed.value) {
        const title = a.knockout?.length ? 'Belum Memenuhi Syarat Administrasi' : (a.failStage ? `Belum Lolos — Tahap ${a.failStage}` : 'Belum Lolos Seleksi');
        return { kind: 'fail', icon: 'bi-emoji-frown-fill', tag: 'Hasil Seleksi', title, msg: nextAction.value };
    }
    return null;
});

// CTA gating: TES → akses CAT; FORM2 → formulir lanjut. Hanya saat tahap aktif & masih berjalan.
const showTest = computed(() => !isFailed.value && !isPassed.value && current.value.tipe === 'TES');
const showForm2 = computed(() => !isFailed.value && !isPassed.value && current.value.tipe === 'FORM2' && !app.value?.form2Done);

// Jadwal (Gantt). Selaras 1:1 dengan pipeline (label sama) → petakan tanggal by label/index.
const timeline = computed(() => {
    const j = props.catalog?.jadwal;
    if (j && j.length) return j;
    return pipeline.value.map((p) => ({ label: p.label, tanggal: null }));
});
function jadwalFor(i) {
    const j = props.catalog?.jadwal || [];
    return j[i]?.tanggal || null;
}
function ganttState(i) {
    if (isFailed.value && i === curIdx.value) return 'fail';
    return i < curIdx.value ? 'done' : (i === curIdx.value ? 'now' : 'wait');
}
function barStyle(i) {
    const m = Math.max(1, timeline.value.length);
    return { marginLeft: (i / m) * 62 + '%', width: Math.max(24, 100 / m + 10) + '%' };
}

const bio = computed(() => app.value?.bio || []);
const experiences = computed(() => app.value?.experiences || []);
const fileList = computed(() => Object.entries(app.value?.files || {}).map(([key, v]) => ({ key, ...v })));

const TIPE = {
    FORM: { i: 'bi-file-earmark-text', l: 'Formulir' },
    FORM2: { i: 'bi-folder-plus', l: 'Biodata Lanjutan' },
    HCLEARN_TEST: { i: 'bi-pc-display', l: 'Tes Online' },
    TES: { i: 'bi-pc-display', l: 'Tes Online' },
    INTERVIEW: { i: 'bi-chat-dots', l: 'Wawancara' },
    FGD: { i: 'bi-people-fill', l: 'FGD' },
    SCREENING: { i: 'bi-funnel', l: 'Seleksi' },
    DECISION: { i: 'bi-clipboard-check', l: 'Keputusan' },
    OFFERING: { i: 'bi-envelope-paper', l: 'Penawaran' },
    ONBOARDING: { i: 'bi-rocket-takeoff', l: 'Onboarding' },
};
function tipeIcon(t) { return TIPE[t]?.i || 'bi-diagram-2'; }
function tipeLabel(t) { return TIPE[t]?.l || 'Tahap'; }
</script>
