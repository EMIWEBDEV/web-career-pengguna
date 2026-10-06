<script>
// WEB CAREER — Auth kandidat (login / register / cek KTP). Memakai AuthShell
// bersama seluruh halaman auth lain — hanya isi kartu yang berbeda.
import axios from 'axios';
import { Link, router } from '@inertiajs/vue3';
import AuthShell from './components/AuthShell.vue';
import TeleponNegara from '@career/TeleponNegara.vue';
import { logout as clearSession, syncFromServer } from '@utils/career/session';
import { csrfHeaders, refreshCsrfToken } from '../../utils/csrf';

const EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

const BERANDA_KANDIDAT = '/kandidat/portal';

/**
 * Tujuan sesudah masuk, dibaca dari `?redirect=`.
 *
 * Diisi CareerAuth saat sesi habis di tengah jalan — paling sering ketika
 * kandidat pulang dari mengerjakan tes CAT dan sesinya sudah lewat umur.
 *
 * HANYA PATH SATU DOMAIN yang diterima. Nilainya berakhir di router.visit(),
 * jadi alamat berhost seperti `https://situs-palsu` akan membuat halaman masuk
 * kita sendiri mengantar korban ke luar — persis pola open-redirect yang
 * dipakai untuk phishing, dan justru meyakinkan karena berangkat dari domain
 * yang benar. `//situs-palsu` ikut ditolak: peramban membacanya sebagai URL
 * berprotokol-relatif, tetap keluar domain walau diawali garis miring.
 */
function readRedirect() {
    try {
        const nilai = new URLSearchParams(window.location.search).get('redirect');
        if (!nilai || !nilai.startsWith('/') || nilai.startsWith('//')) {
            return BERANDA_KANDIDAT;
        }

        return nilai;
    } catch (e) {
        return BERANDA_KANDIDAT;
    }
}

// Halaman auth adalah pintu masuk sesi baru. Pastikan cookie XSRF dibuat
// sebelum mutation pertama; mengandalkan retry setelah 419 membuat cek KTP
// terlihat gagal dan pada sebagian browser header request lama ikut terbawa.
async function authRequestConfig() {
    await refreshCsrfToken();
    return {
        withCredentials: true,
        headers: csrfHeaders({ Accept: 'application/json' }),
    };
}

export default {
    layout: null,
    components: { AuthShell, Link, TeleponNegara },
    props: {
        mode: { type: String, default: 'login' },
        turnstileSiteKey: { type: String, default: '' },
    },
    data() {
        return {
            tab: this.mode === 'register' ? 'register' : 'login',
            // Cloudflare Turnstile (khusus login). state: idle | ok | error.
            tsToken: '',
            tsState: 'idle',
            tsWidgetId: null,
            redirectTarget: readRedirect(),
            showPassword: false,
            processing: false,
            // Register 2 langkah: 1 = cek KTP, 2 = form identitas lengkap.
            regStep: 1,
            checkingKtp: false,
            errors: {},
            form: { nama: '', email: '', phone: '', nik: '', password: '' },
            notice: { visible: false, type: 'info', message: '' },
            noticeTimer: null,
            redirectTimer: null,
            // Email yang masih menunggu verifikasi (dari register / login ditolak)
            // → memunculkan baris "kirim ulang email verifikasi".
            pendingVerifEmail: '',
            resendingVerif: false,
        };
    },
    computed: {
        isLogin() { return this.tab === 'login'; },
        pageTitle() { return this.isLogin ? 'Masuk' : 'Daftar Akun'; },
        ledeText() {
            return `Satu akun untuk melamar lowongan, mengikuti program Management Trainee, dan memantau progres seleksimu. Silakan ${this.isLogin ? 'masuk' : 'daftar'} untuk melanjutkan.`;
        },
        // Navigasi antar halaman auth (bukan tab) — pertahankan ?redirect=.
        redirectQuery() {
            return this.redirectTarget && this.redirectTarget !== '/kandidat/portal'
                ? '?redirect=' + encodeURIComponent(this.redirectTarget) : '';
        },
        registerUrl() { return '/register' + this.redirectQuery; },
        loginUrl() { return '/login' + this.redirectQuery; },
        canSubmit() {
            if (this.processing) return false;
            if (!this.form.email || !this.form.password) return false;
            if (!this.isLogin && (!this.form.nama || !this.form.phone)) return false;
            // Login: bila Turnstile aktif, wajib lolos captcha dulu.
            if (this.isLogin && this.turnstileSiteKey && !this.tsToken) return false;
            return true;
        },
    },
    watch: {
        // Bila route berpindah (/login ↔ /register), sinkronkan tab dari prop mode.
        mode(m) {
            this.tab = m === 'register' ? 'register' : 'login';
            this.regStep = 1;
            if (this.tab === 'login') this.$nextTick(() => this.renderTurnstile());
        },
    },
    mounted() {
        // Bila datang dari logout, bersihkan sesi klien (sessionStorage).
        try {
            const params = new URLSearchParams(window.location.search);
            if (params.get('loggedout')) clearSession();
            const email = params.get('email') || '';
            if (EMAIL_RE.test(email)) this.form.email = email;
            if (params.get('registered') === '1') {
                this.flashNotice('info', 'Akun development berhasil dibuat dan email sudah otomatis terverifikasi. Silakan masuk.');
            }
            // Dipulangkan dari formulir lamaran (langsung ketik URL /karir/apply
            // atau sesinya habis) — katakan alasannya. Tanpa ini halaman masuk
            // muncul tanpa sebab dan terbaca seperti aplikasi yang error.
            else if (this.redirectTarget && this.redirectTarget.startsWith('/karir/apply')) {
                this.flashNotice('info', 'Masuk dulu untuk melamar lowongan ini. Setelah masuk kamu langsung dibawa ke formulirnya.');
            }
        } catch (e) { /* noop */ }
        if (this.isLogin) this.renderTurnstile();
    },
    beforeUnmount() {
        clearTimeout(this.noticeTimer);
        clearTimeout(this.redirectTimer);
        if (this.tsWidgetId !== null && window.turnstile) { try { window.turnstile.remove(this.tsWidgetId); } catch (e) { /* noop */ } }
    },
    methods: {
        /* ── Cloudflare Turnstile (login only) ── */
        loadTurnstileScript() {
            return new Promise((resolve) => {
                if (window.turnstile) return resolve();
                const existing = document.getElementById('cf-turnstile-script');
                if (existing) {
                    const t = setInterval(() => { if (window.turnstile) { clearInterval(t); resolve(); } }, 100);
                    return;
                }
                const s = document.createElement('script');
                s.id = 'cf-turnstile-script';
                s.src = 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit';
                s.async = true;
                s.defer = true;
                s.onload = () => resolve();
                s.onerror = () => resolve();
                document.head.appendChild(s);
            });
        },
        async renderTurnstile() {
            if (!this.isLogin || !this.turnstileSiteKey) return;
            await this.loadTurnstileScript();
            await this.$nextTick();
            const el = this.$refs.tsWidget;
            if (!el || !window.turnstile) return;
            if (this.tsWidgetId !== null) { try { window.turnstile.remove(this.tsWidgetId); } catch (e) { /* noop */ } this.tsWidgetId = null; }
            this.tsToken = '';
            this.tsState = 'idle';
            try {
                this.tsWidgetId = window.turnstile.render(el, {
                    sitekey: this.turnstileSiteKey,
                    theme: 'light',
                    size: 'flexible',
                    callback: (token) => { this.tsToken = token; this.tsState = 'ok'; },
                    'error-callback': () => { this.tsToken = ''; this.tsState = 'error'; },
                    'expired-callback': () => { this.tsToken = ''; this.tsState = 'idle'; },
                    'timeout-callback': () => { this.tsToken = ''; this.tsState = 'idle'; },
                });
            } catch (e) { /* noop */ }
        },
        resetTurnstile() {
            if (this.tsWidgetId !== null && window.turnstile) { try { window.turnstile.reset(this.tsWidgetId); } catch (e) { /* noop */ } }
            this.tsToken = '';
            this.tsState = 'idle';
        },
        /* ── Flash / notice toast ── */
        flashNotice(type, message) {
            if (!message) return;
            this.notice.type = type;
            this.notice.message = message;
            this.notice.visible = true;
            clearTimeout(this.noticeTimer);
            this.noticeTimer = setTimeout(() => (this.notice.visible = false), 6000);
        },
        clearErr(key) { if (this.errors[key]) delete this.errors[key]; },
        clearAll() { Object.keys(this.errors).forEach((k) => delete this.errors[k]); },
        /**
         * Nomor sudah datang RAPI dari TeleponNegara: kode negara + nomor lokal,
         * angka saja, nol di depan sudah dibuang.
         *
         * Pengubah lama (0xxx → 62xxx, 8xxx → 628xxx) DIHAPUS, dan bukan karena
         * pindah tempat — ia justru merusak begitu negaranya bukan Indonesia:
         * nomor Malaysia +60 12… akan dipaksa jadi 6012… lalu dibaca sebagai
         * nomor Indonesia. Pemilih negara yang menentukan awalannya sekarang.
         */
        setPhone(v) {
            this.form.phone = String(v || '').slice(0, 15);
            this.clearErr('phone');
        },
        // KTP hanya angka, maksimal 16 digit.
        onNikInput() {
            this.form.nik = (this.form.nik || '').replace(/\D/g, '').slice(0, 16);
            this.clearErr('nik');
        },
        /* ── Langkah 1 register: cek KTP dulu, baru buka form identitas ── */
        async cekKtp() {
            this.clearErr('nik');
            if (!/^\d{16}$/.test(this.form.nik)) {
                this.errors.nik = 'Nomor KTP harus tepat 16 digit angka.';
                return;
            }
            this.checkingKtp = true;
            try {
                await axios.post('/api/v1/cek-ktp', { nik: this.form.nik }, await authRequestConfig());
                this.regStep = 2; // KTP tersedia → tampilkan form identitas.
            } catch (e) {
                const r = e.response;
                if (r && r.status === 422 && r.data && r.data.errors && r.data.errors.nik) {
                    this.errors.nik = Array.isArray(r.data.errors.nik) ? r.data.errors.nik[0] : r.data.errors.nik;
                } else {
                    this.errors.nik = (r && r.data && r.data.message) || 'Gagal memeriksa KTP. Coba lagi.';
                }
            } finally {
                this.checkingKtp = false;
            }
        },
        // Kembali ke langkah 1 untuk mengubah KTP.
        gantiKtp() {
            this.regStep = 1;
            this.clearErr('nik');
        },
        /* ── Lupa sandi → langsung ke halaman Lupa Kata Sandi ── */
        goForgot() {
            const email = this.form.email && EMAIL_RE.test(this.form.email) ? this.form.email : '';
            router.visit('/ganti-sandi' + (email ? '?email=' + encodeURIComponent(email) : ''));
        },
        /* ── Kirim ulang email verifikasi ── */
        async resendVerif() {
            if (this.resendingVerif || !this.pendingVerifEmail) return;
            this.resendingVerif = true;
            try {
                const res = await axios.post(
                    '/api/v1/kirim-verifikasi',
                    { email: this.pendingVerifEmail },
                    await authRequestConfig(),
                );
                this.flashNotice('info', (res.data && res.data.message) || 'Email verifikasi dikirim ulang.');
            } catch (e) {
                const r = e.response;
                this.flashNotice('error', (r && r.data && r.data.message) || 'Gagal mengirim ulang. Silakan coba lagi.');
            } finally {
                this.resendingVerif = false;
            }
        },
        validate() {
            this.clearAll();
            if (!this.form.email) this.errors.email = 'Email wajib diisi.';
            else if (!EMAIL_RE.test(this.form.email)) this.errors.email = 'Format email tidak valid.';
            if (!this.form.password) this.errors.password = 'Kata sandi wajib diisi.';
            else if (!this.isLogin && this.form.password.length < 6) this.errors.password = 'Minimal 6 karakter.';
            if (!this.isLogin) {
                if (!this.form.nama) this.errors.nama = 'Nama wajib diisi.';
                // ANGKA SAJA, 8–15 digit termasuk kode negara (batas E.164).
                // Awalan '62' TIDAK lagi diwajibkan — negaranya dipilih sendiri,
                // dan memaksa +62 berarti menutup pintu bagi pelamar luar negeri.
                //
                // Aturan yang sama persis ada di server (AuthController::register).
                // Yang di sini hanya supaya salahnya ketahuan sebelum tombol
                // ditekan; yang menegakkan tetap server.
                if (!this.form.phone) this.errors.phone = 'No. HP wajib diisi.';
                else if (/\D/.test(this.form.phone)) this.errors.phone = 'No. HP hanya boleh angka 0–9.';
                else if (!/^\d{8,15}$/.test(this.form.phone)) this.errors.phone = 'No. HP harus 8–15 digit termasuk kode negara.';
                if (!this.form.nik) this.errors.nik = 'Nomor KTP (NIK) wajib diisi.';
                else if (!/^\d{16}$/.test(this.form.nik)) this.errors.nik = 'Nomor KTP harus tepat 16 digit angka.';
            }
            return Object.keys(this.errors).length === 0;
        },
        async submit() {
            if (!this.validate()) return;
            this.processing = true;
            try {
                const url = this.isLogin ? '/api/v1/login' : '/api/v1/register';
                const payload = this.isLogin
                    ? { email: this.form.email, password: this.form.password, turnstile_token: this.tsToken }
                    : { nama: this.form.nama, email: this.form.email, phone: this.form.phone, nik: this.form.nik, password: this.form.password };
                const res = await axios.post(url, payload, await authRequestConfig());

                if (!this.isLogin) {
                    const hasil = res.data && res.data.result;
                    if (hasil && hasil.perlu_verifikasi === false) {
                        const params = new URLSearchParams();
                        params.set('registered', '1');
                        params.set('email', this.form.email);
                        if (this.redirectTarget && this.redirectTarget !== '/kandidat/portal') {
                            params.set('redirect', this.redirectTarget);
                        }
                        router.visit('/login?' + params.toString());
                        return;
                    }
                    router.visit('/menunggu-verifikasi?email=' + encodeURIComponent(this.form.email));
                    return;
                }

                const user = res.data && res.data.result;
                if (user) syncFromServer(user);
                let dest = this.redirectTarget;
                if (dest === '/kandidat/portal' && user && ['ADMIN', 'SUPERADMIN'].includes(user.role)) {
                    dest = '/karir';
                }
                this.flashNotice('info', 'Berhasil masuk — mengalihkan…');
                this.redirectTimer = setTimeout(() => router.visit(dest), 600);
            } catch (e) {
                this.processing = false;
                // Token Turnstile sekali pakai → reset agar user bisa mencoba lagi.
                if (this.isLogin) this.resetTurnstile();
                const r = e.response;
                if (r && r.status === 422 && r.data.errors) {
                    Object.keys(r.data.errors).forEach((k) => (this.errors[k] = Array.isArray(r.data.errors[k]) ? r.data.errors[k][0] : r.data.errors[k]));
                } else if (r && r.status === 403 && r.data && r.data.code === 'BELUM_VERIFIKASI') {
                    this.pendingVerifEmail = this.form.email;
                    this.flashNotice('error', r.data.message || 'Email kamu belum diverifikasi.');
                } else {
                    this.flashNotice('error', (r && r.data && r.data.message) || 'Terjadi kesalahan. Silakan coba lagi.');
                }
            }
        },
    },
};
</script>

<template>
    <AuthShell
        :page-title="pageTitle"
        eyebrow="Portal Career"
        title="Karier Impian"
        title-accent="Dimulai di Sini."
        :lede="ledeText"
        sub-mobile="Satu akun untuk lamaran & Management Trainee EVO Group"
        :card-title="isLogin ? 'Selamat Datang' : 'Buat Akun'"
        :card-subtitle="isLogin ? 'Masuk untuk melanjutkan perjalanan karirmu.' : 'Lengkapi data untuk membuat akun kandidat.'"
        :card-tag="isLogin ? 'Autentikasi' : 'Registrasi'"
        :show-ver="false"
        :notice="notice"
        @close-notice="notice.visible = false"
    >
        <!-- ═══ LANGKAH 1 REGISTER — CEK KTP ═══ -->
        <form v-if="!isLogin && regStep === 1" @submit.prevent="cekKtp" novalidate>
            <div class="ktp-intro">
                <span class="ktp-intro__ico"><i class="bi bi-person-vcard"></i></span>
                <div>
                    <div class="ktp-intro__title">Verifikasi KTP dulu</div>
                    <div class="ktp-intro__sub">Masukkan 16 digit NIK. Kami cek ketersediaannya sebelum kamu mengisi data diri.</div>
                </div>
            </div>

            <div class="field">
                <label for="c-nik"><span class="lbl-text"><i class="bi bi-person-vcard"></i> Nomor KTP (NIK)</span></label>
                <div class="input-wrap" :class="{ 'is-error': errors.nik }">
                    <input id="c-nik" v-model="form.nik" type="text" inputmode="numeric" maxlength="16" placeholder="16 digit sesuai KTP" autocomplete="off" autofocus @input="onNikInput" @keyup.enter="cekKtp" />
                    <span class="nik-count" :class="{ 'is-ok': form.nik.length === 16 }">{{ form.nik.length }}/16</span>
                </div>
                <p v-if="errors.nik" class="help">{{ errors.nik }}</p>
                <p v-else class="help help--muted">Digunakan sebagai identitas peserta seleksi. Satu KTP untuk satu akun.</p>
            </div>

            <button type="submit" class="btn-login" :disabled="form.nik.length !== 16 || checkingKtp">
                <span v-if="checkingKtp" class="spinner" aria-hidden="true"></span>
                {{ checkingKtp ? 'Memeriksa…' : 'Cek KTP & Lanjutkan' }}
                <i v-if="!checkingKtp" class="bi bi-arrow-right"></i>
            </button>

            <p class="switch-row">Sudah punya akun? <Link :href="loginUrl" class="switch-link">Masuk di sini</Link></p>
            <div class="ver-row">
                <span class="ver-tag" title="Versi aplikasi">Versi {{ $page.props.appVersion || '1.0.0' }}</span>
                <span>© 2026 EVO Group</span>
            </div>
        </form>

        <!-- ═══ LANGKAH 2 REGISTER (form lengkap) & LOGIN ═══ -->
        <form v-else @submit.prevent="submit" novalidate>
            <!-- Ringkasan KTP terverifikasi (register step 2) -->
            <div v-if="!isLogin" class="ktp-ok">
                <span class="ktp-ok__ico"><i class="bi bi-patch-check-fill"></i></span>
                <div class="ktp-ok__body">
                    <span class="ktp-ok__label">KTP terverifikasi</span>
                    <span class="ktp-ok__nik">{{ form.nik }}</span>
                </div>
                <button type="button" class="ktp-ok__edit" @click="gantiKtp"><i class="bi bi-pencil"></i> Ubah</button>
            </div>

            <!-- Nama (register) -->
            <div v-if="!isLogin" class="field">
                <label for="c-nama"><span class="lbl-text"><i class="bi bi-person"></i> Nama Lengkap</span></label>
                <div class="input-wrap" :class="{ 'is-error': errors.nama }">
                    <input id="c-nama" v-model="form.nama" type="text" placeholder="Nama sesuai KTP" autocomplete="name" autofocus @input="clearErr('nama')" />
                </div>
                <p v-if="errors.nama" class="help">{{ errors.nama }}</p>
            </div>

            <!-- Email -->
            <div class="field">
                <label for="c-email"><span class="lbl-text"><i class="bi bi-envelope"></i> Email</span></label>
                <div class="input-wrap" :class="{ 'is-error': errors.email }">
                    <input id="c-email" v-model="form.email" type="email" placeholder="nama@email.com" autocomplete="email" :autofocus="isLogin" @input="clearErr('email')" />
                </div>
                <p v-if="errors.email" class="help">{{ errors.email }}</p>
            </div>

            <!-- No. HP (register) — pemilih KODE NEGARA, sama persis dengan
                 formulir lamaran. Dulu kotak polos yang MEMAKSA awalan 62:
                 kandidat bernomor luar negeri tak punya cara mendaftar sama
                 sekali, dan penolakannya berbunyi "No. HP harus format 62"
                 seolah nomornya yang salah. Default tetap Indonesia. -->
            <div v-if="!isLogin" class="field">
                <label><span class="lbl-text"><i class="bi bi-telephone"></i> No. HP</span></label>
                <div class="input-wrap tel-wrap" :class="{ 'is-error': errors.phone }">
                    <TeleponNegara
                        :model-value="form.phone"
                        placeholder="81234567890"
                        @update:model-value="setPhone"
                    />
                </div>
                <p v-if="errors.phone" class="help">{{ errors.phone }}</p>
            </div>

            <!-- Password -->
            <div class="field">
                <label for="c-pass">
                    <span class="lbl-text"><i class="bi bi-key"></i> {{ isLogin ? 'Password' : 'Buat Password' }}</span>
                    <button v-if="isLogin" type="button" class="forgot-link" @click="goForgot">Lupa sandi?</button>
                </label>
                <div class="input-wrap" :class="{ 'is-error': errors.password }">
                    <input
                        id="c-pass"
                        v-model="form.password"
                        :type="showPassword ? 'text' : 'password'"
                        :placeholder="isLogin ? 'Masukan Password' : 'Minimal 6 karakter'"
                        :autocomplete="isLogin ? 'current-password' : 'new-password'"
                        @input="clearErr('password')"
                    />
                    <button type="button" class="toggle-eye" :aria-label="showPassword ? 'Sembunyikan' : 'Tampilkan'" @click="showPassword = !showPassword">
                        <i :class="showPassword ? 'bi bi-eye-slash' : 'bi bi-eye'"></i>
                    </button>
                </div>
                <p v-if="errors.password" class="help">{{ errors.password }}</p>
            </div>

            <!-- Cloudflare Turnstile (login) — tampilan custom, ruang tetap (anti-geser). -->
            <div v-if="isLogin && turnstileSiteKey" class="ts2">
                <!-- Widget asli Cloudflare: tampil HANYA saat idle (menunggu). Saat ok/error
                     widget disembunyikan total → tidak menumpuk dengan kartu custom. -->
                <div v-show="tsState === 'idle'" ref="tsWidget" class="ts2__widget"></div>

                <!-- Kartu sukses -->
                <div v-if="tsState === 'ok'" class="ts2__card ts2__card--ok">
                    <span class="ts2__ico ts2__ico--ok"><i class="bi bi-check-lg"></i></span>
                    <div class="ts2__txt">
                        <b>Verifikasi keamanan lolos</b>
                        <small>Anti-bot &amp; perangkat tepercaya</small>
                    </div>
                    <span class="ts2__secure"><i class="bi bi-shield-lock"></i> SECURE</span>
                </div>

                <!-- Kartu error (bersih — widget Cloudflare disembunyikan) -->
                <div v-else-if="tsState === 'error'" class="ts2__card ts2__card--err">
                    <span class="ts2__ico ts2__ico--err"><i class="bi bi-exclamation-lg"></i></span>
                    <div class="ts2__txt">
                        <b>Verifikasi gagal</b>
                        <small>Tidak dapat terhubung — coba muat ulang</small>
                    </div>
                    <button type="button" class="ts2__reload" @click="renderTurnstile"><i class="bi bi-arrow-clockwise"></i> Muat ulang</button>
                </div>
            </div>

            <button type="submit" class="btn-login" :disabled="!canSubmit">
                <span v-if="processing" class="spinner" aria-hidden="true"></span>
                {{ processing ? 'Memproses…' : isLogin ? 'Login Akses' : 'Daftar Sekarang' }}
                <i v-if="!processing" class="bi bi-arrow-right"></i>
            </button>

            <p class="switch-row">
                <template v-if="isLogin">Belum punya akun? <Link :href="registerUrl" class="switch-link">Daftar sekarang</Link></template>
                <template v-else>Sudah punya akun? <Link :href="loginUrl" class="switch-link">Masuk di sini</Link></template>
            </p>

            <p v-if="pendingVerifEmail" class="verif-pending">
                <i class="bi bi-envelope-exclamation"></i>
                Belum menerima email verifikasi?
                <button type="button" class="switch-link" :disabled="resendingVerif" :onClick="resendingVerif ? null : resendVerif">
                    {{ resendingVerif ? 'Mengirim…' : 'Kirim ulang' }}
                </button>
            </p>

            <div class="ver-row">
                <span class="ver-tag" title="Versi aplikasi">Versi {{ $page.props.appVersion || '1.0.0' }}</span>
                <span>© 2026 EVO Group</span>
            </div>
        </form>
    </AuthShell>
</template>

<style scoped>
/* ═══ TELEPON BERKODE NEGARA — MENYATU DENGAN ISIAN LAIN ═══════════════════
   TeleponNegara membawa bingkainya sendiri (dipakai apa adanya di formulir
   lamaran yang memakai Element Plus). Di halaman ini bingkai itu DILEPAS dan
   yang berlaku tinggal .input-wrap — kalau tidak, kotaknya jadi dua lapis:
   satu bingkai di dalam satu bingkai, dengan sudut & tinggi yang berbeda.

   Cincin fokus dan garis merah galat pun tetap milik .input-wrap, jadi kolom
   telepon bereaksi persis sama dengan Email, Nama, dan Password di sebelahnya. */
.tel-wrap :deep(.tnp) {
    border: 0;
    border-radius: inherit;
    background: transparent;
    height: auto;
}
.tel-wrap :deep(.tnp:focus-within) { box-shadow: none; }

/* Padding & ukuran huruf disamakan dengan `.authx .input-wrap input`
   (15px 16px / 15px) supaya tingginya sebaris dengan isian lain. */
.tel-wrap :deep(.tnp__trigger) { padding: 15px 10px 15px 16px; }
.tel-wrap :deep(.tnp__dial) { font-size: 15px; color: #0f172a; }
.tel-wrap :deep(.tnp__num) { padding: 15px 16px 15px 12px; font-size: 15px; color: #0f172a; }
.tel-wrap :deep(.tnp__num::placeholder) { color: #94a3b8; }
.tel-wrap :deep(.tnp__sep) { margin: 10px 0; }

/* Ikut mengecil di layar pendek & sempit — titik hentinya SAMA PERSIS dengan
   AuthShell, supaya kolom telepon tidak pernah lebih tinggi dari tetangganya. */
@media (max-height: 880px) {
    .tel-wrap :deep(.tnp__trigger) { padding: 13px 9px 13px 15px; }
    .tel-wrap :deep(.tnp__num) { padding: 13px 15px 13px 11px; font-size: 14.5px; }
    .tel-wrap :deep(.tnp__dial) { font-size: 14.5px; }
}
@media (max-height: 760px) {
    .tel-wrap :deep(.tnp__trigger) { padding: 12px 8px 12px 14px; }
    .tel-wrap :deep(.tnp__num) { padding: 12px 14px 12px 10px; font-size: 14px; }
    .tel-wrap :deep(.tnp__dial) { font-size: 14px; }
}
@media (max-height: 660px) {
    .tel-wrap :deep(.tnp__trigger) { padding: 11px 8px 11px 14px; }
    .tel-wrap :deep(.tnp__num) { padding: 11px 14px 11px 10px; }
}
/* Layar sangat sempit: bendera + kode dipersempit supaya kolom nomornya tetap
   cukup lebar untuk 12 digit — kalau tidak, nomornya tergulung dan orang tak
   bisa melihat apa yang baru saja ia ketik. */
@media (max-width: 360px) {
    .tel-wrap :deep(.tnp__trigger) { padding-left: 11px; padding-right: 6px; gap: 5px; }
    .tel-wrap :deep(.tnp__num) { padding-left: 8px; padding-right: 11px; }
    .tel-wrap :deep(.tnp__chev) { display: none; }
}

/* Turnstile custom — ruang tetap (min-height) supaya widget/kartu tidak menggeser layout. */
.ts2 {
    margin: 6px 0 14px;
    min-height: 70px;
    display: flex;
    flex-direction: column;
    gap: 8px;
    justify-content: center;
}
.ts2__widget {
    width: 100%;
    min-height: 62px;
}
/* Kartu status (sukses & error) — satu bentuk, warna berbeda. */
.ts2__card {
    display: flex;
    align-items: center;
    gap: 13px;
    padding: 12px 15px;
    border-radius: 16px;
    border: 1.5px solid;
    animation: tsPop 0.4s cubic-bezier(0.34, 1.56, 0.64, 1) both;
}
.ts2__card--ok {
    background: linear-gradient(135deg, rgba(16, 185, 129, 0.12), rgba(16, 185, 129, 0.05));
    border-color: rgba(16, 185, 129, 0.35);
    box-shadow: 0 10px 24px -10px rgba(16, 185, 129, 0.45);
}
.ts2__card--err {
    background: linear-gradient(135deg, rgba(239, 68, 68, 0.1), rgba(239, 68, 68, 0.04));
    border-color: rgba(239, 68, 68, 0.35);
    box-shadow: 0 10px 24px -10px rgba(239, 68, 68, 0.4);
}
.ts2__ico {
    flex: 0 0 auto;
    width: 38px;
    height: 38px;
    border-radius: 50%;
    display: grid;
    place-items: center;
    color: #fff;
    font-size: 20px;
}
.ts2__ico--ok {
    background: linear-gradient(135deg, #10b981, #059669);
    box-shadow: 0 8px 18px -4px rgba(16, 185, 129, 0.55);
}
.ts2__ico--err {
    background: linear-gradient(135deg, #f87171, #dc2626);
    box-shadow: 0 8px 18px -4px rgba(239, 68, 68, 0.55);
}
.ts2__card--err .ts2__txt b { color: #991b1b; }
.ts2__card--err .ts2__txt small { color: #b45c5c; }
.ts2__reload {
    flex: 0 0 auto;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    border: 1px solid rgba(239, 68, 68, 0.4);
    background: #fff;
    color: #dc2626;
    font: 700 11.5px 'Inter', sans-serif;
    padding: 7px 12px;
    border-radius: 10px;
    cursor: pointer;
    transition: background 0.18s ease;
}
.ts2__reload:hover { background: rgba(239, 68, 68, 0.08); }
.ts2__txt {
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
    line-height: 1.25;
}
.ts2__txt b {
    font-size: 14px;
    font-weight: 800;
    color: #065f46;
}
.ts2__txt small {
    font-size: 12px;
    color: #4b8a76;
    margin-top: 1px;
}
.ts2__secure {
    flex: 0 0 auto;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    font-size: 11px;
    font-weight: 800;
    letter-spacing: 0.08em;
    color: #059669;
}
@keyframes tsPop {
    from { opacity: 0; transform: scale(0.94); }
    to { opacity: 1; transform: scale(1); }
}
@media (max-width: 420px) {
    .ts2__secure span, .ts2__secure { font-size: 10px; }
    .ts2__txt small { display: none; }
}
</style>
