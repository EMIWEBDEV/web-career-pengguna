<template>
    <AuthShell
        page-title="Ganti Kata Sandi"
        eyebrow="Keamanan Akun"
        title="Atur Ulang"
        title-accent="Kata Sandi."
        lede="Buat kata sandi baru untuk mengamankan akun & melanjutkan proses lamaranmu di EVO Group."
        sub-mobile="Buat kata sandi baru untuk akun EVO Career"
        back-href="/login"
        back-label="Kembali ke Masuk"
        :show-pill="false"
        :card-title="stepTitle"
        card-tag="Keamanan"
        :show-ver="false"
        :notice="notice"
        @close-notice="notice.visible = false"
    >
        <!-- FASE 1: minta kode OTP (email) -->
        <form v-if="step === 'minta'" @submit.prevent="mintaOtp" novalidate>
            <p class="step-hint">Masukkan email akunmu. Kami akan mengirim kode OTP 6 digit untuk mengatur ulang kata sandi.</p>

            <div class="field">
                <label for="g-email"><span class="lbl-text"><i class="bi bi-envelope"></i> Email</span></label>
                <div class="input-wrap" :class="{ 'is-error': errors.email }">
                    <input id="g-email" v-model="form.email" type="email" placeholder="nama@email.com" autocomplete="email" @input="clearErr('email')" />
                </div>
                <p v-if="errors.email" class="help">{{ errors.email }}</p>
            </div>

            <button type="submit" class="btn-login" :disabled="processing || !form.email">
                <span v-if="processing" class="spinner" aria-hidden="true"></span>
                {{ processing ? 'Mengirim…' : 'Kirim Kode OTP' }}
                <i v-if="!processing" class="bi bi-send"></i>
            </button>

            <p v-if="form.email && form.email === cooldownEmail && resendCooldown > 0" class="otp-meta">
                <i class="bi bi-clock"></i> Kode sudah dikirim ke email ini — bisa minta lagi dalam {{ resendCooldown }}s
            </p>

            <p class="switch-row">Ingat kata sandimu? <Link href="/login" class="switch-link">Masuk di sini</Link></p>
            <div class="ver-row">
                <span class="ver-tag" title="Versi aplikasi">Versi {{ $page.props.appVersion || '1.0.0' }}</span>
                <span>© 2026 EVO Group</span>
            </div>
        </form>

        <!-- FASE 2: masukkan 6 digit OTP -->
        <form v-else-if="step === 'otp'" @submit.prevent="lanjutKeReset" novalidate>
            <p class="step-hint">Kami mengirim kode OTP 6 digit ke <b>{{ form.email }}</b>. Masukkan kodenya untuk melanjutkan.</p>

            <div class="otp-boxes" :class="{ 'is-error': errors.otp }" @paste="onPaste">
                <input
                    v-for="(d, i) in otpDigits"
                    :key="i"
                    ref="otpInputs"
                    class="otp-box"
                    type="text"
                    inputmode="numeric"
                    maxlength="1"
                    :value="otpDigits[i]"
                    :aria-label="`Digit ${i + 1}`"
                    @input="onDigit(i, $event)"
                    @keydown="onKeydown(i, $event)"
                    @focus="$event.target.select()"
                />
            </div>
            <p v-if="errors.otp" class="help help-center">{{ errors.otp }}</p>

            <p class="otp-meta">
                <span v-if="otpCountdown > 0"><i class="bi bi-clock"></i> Kode berlaku {{ otpCountdownText }}</span>
                <span v-else class="otp-expired"><i class="bi bi-clock-history"></i> Kode kedaluwarsa — silakan kirim ulang</span>
            </p>

            <div class="wait-note">
                <i class="bi bi-clock-history"></i>
                <span>Kode OTP dikirim via antrean email — biasanya tiba dalam <b>1–3 menit</b>. Cek folder <b>Spam</b>/<b>Promosi</b> bila belum masuk.</span>
            </div>

            <button type="submit" class="btn-login" :disabled="processing || otpValue.length !== 6">
                <span v-if="processing" class="spinner" aria-hidden="true"></span>
                {{ processing ? 'Memeriksa…' : 'Lanjutkan' }}
                <i v-if="!processing" class="bi bi-arrow-right"></i>
            </button>

            <p class="switch-row">
                Tidak menerima kode?
                <button v-if="resendCooldown <= 0" type="button" class="switch-link as-btn" :disabled="processing" :onClick="processing ? null : () => mintaOtp()">Kirim ulang</button>
                <span v-else class="switch-muted">Kirim ulang dalam {{ resendCooldown }}s</span>
            </p>
            <p class="switch-row"><button type="button" class="switch-link" @click="kembaliKeMinta()"><i class="bi bi-arrow-left"></i> Ganti email</button></p>
            <div class="ver-row">
                <span class="ver-tag" title="Versi aplikasi">Versi {{ $page.props.appVersion || '1.0.0' }}</span>
                <span>© 2026 EVO Group</span>
            </div>
        </form>

        <!-- FASE 3: kata sandi baru -->
        <form v-else @submit.prevent="submitReset" novalidate>
            <p class="step-hint">Kode diterima untuk <b>{{ form.email }}</b>. Sekarang buat kata sandi barumu.</p>

            <div class="field">
                <label for="g-pass"><span class="lbl-text"><i class="bi bi-key"></i> Kata Sandi Baru</span></label>
                <div class="input-wrap" :class="{ 'is-error': errors.password }">
                    <input id="g-pass" v-model="form.password" :type="show ? 'text' : 'password'" placeholder="Minimal 6 karakter" autocomplete="new-password" @input="clearErr('password')" />
                    <button type="button" class="toggle-eye" :aria-label="show ? 'Sembunyikan' : 'Tampilkan'" @click="show = !show"><i :class="show ? 'bi bi-eye-slash' : 'bi bi-eye'"></i></button>
                </div>
                <p v-if="errors.password" class="help">{{ errors.password }}</p>
            </div>

            <div class="field">
                <label for="g-conf"><span class="lbl-text"><i class="bi bi-shield-lock"></i> Konfirmasi Kata Sandi</span></label>
                <div class="input-wrap" :class="{ 'is-error': errors.confirm }">
                    <input id="g-conf" v-model="form.confirm" :type="show ? 'text' : 'password'" placeholder="Ulangi kata sandi baru" autocomplete="new-password" @input="clearErr('confirm')" />
                </div>
                <p v-if="errors.confirm" class="help">{{ errors.confirm }}</p>
            </div>

            <button type="submit" class="btn-login" :disabled="!canSubmit">
                <span v-if="processing" class="spinner" aria-hidden="true"></span>
                {{ processing ? 'Menyimpan…' : 'Simpan Kata Sandi' }}
                <i v-if="!processing" class="bi bi-check-lg"></i>
            </button>

            <p class="switch-row">Ingat kata sandimu? <Link href="/login" class="switch-link">Masuk di sini</Link></p>
            <div class="ver-row">
                <span class="ver-tag" title="Versi aplikasi">Versi {{ $page.props.appVersion || '1.0.0' }}</span>
                <span>© 2026 EVO Group</span>
            </div>
        </form>
    </AuthShell>
</template>

<script>
import axios from 'axios';
import { Link, router } from '@inertiajs/vue3';
import AuthShell from './components/AuthShell.vue';

const RESEND_COOLDOWN = 120; // detik — selaras RESET_OTP_THROTTLE_MENIT (2 menit) di backend
const OTP_BERLAKU = 10 * 60; // detik — selaras RESET_OTP_BERLAKU_MENIT (10 menit)

export default {
    layout: null,
    components: { AuthShell, Link },
    props: {
        email: { type: String, default: '' },
    },
    data() {
        return {
            step: 'minta', // 'minta' → 'otp' → 'reset'
            form: { email: this.email || '', password: '', confirm: '' },
            otpDigits: ['', '', '', '', '', ''],
            show: false,
            processing: false,
            errors: {},
            notice: { visible: false, type: 'info', message: '' },
            noticeTimer: null,
            resendCooldown: 0,
            otpCountdown: 0,
            cooldownEmail: '',
            tickTimer: null,
        };
    },
    computed: {
        stepTitle() {
            return this.step === 'minta' ? 'Lupa Kata Sandi' : this.step === 'otp' ? 'Verifikasi OTP' : 'Reset Kata Sandi';
        },
        otpValue() { return this.otpDigits.join(''); },
        canSubmit() {
            return !this.processing && this.otpValue.length === 6 && !!this.form.password && !!this.form.confirm;
        },
        otpCountdownText() {
            const m = Math.floor(this.otpCountdown / 60);
            const s = this.otpCountdown % 60;
            return `${m}:${String(s).padStart(2, '0')}`;
        },
    },
    beforeUnmount() {
        clearTimeout(this.noticeTimer);
        clearInterval(this.tickTimer);
    },
    methods: {
        flashNotice(type, message) {
            if (!message) return;
            this.notice.type = type;
            this.notice.message = message;
            this.notice.visible = true;
            clearTimeout(this.noticeTimer);
            this.noticeTimer = setTimeout(() => (this.notice.visible = false), 6000);
        },
        clearErr(key) { if (this.errors[key]) delete this.errors[key]; },
        focusOtp(i) {
            this.$nextTick(() => {
                const els = this.$refs.otpInputs;
                if (els && els[i]) els[i].focus();
            });
        },
        resetOtpBoxes() { this.otpDigits = ['', '', '', '', '', '']; },
        onDigit(i, e) {
            const v = (e.target.value || '').replace(/\D/g, '');
            const digit = v ? v[v.length - 1] : '';
            this.otpDigits.splice(i, 1, digit);
            e.target.value = digit;
            this.clearErr('otp');
            if (digit && i < 5) this.focusOtp(i + 1);
        },
        onKeydown(i, e) {
            if (e.key === 'Backspace' && !this.otpDigits[i] && i > 0) {
                this.otpDigits.splice(i - 1, 1, '');
                this.focusOtp(i - 1);
            } else if (e.key === 'ArrowLeft' && i > 0) {
                this.focusOtp(i - 1);
            } else if (e.key === 'ArrowRight' && i < 5) {
                this.focusOtp(i + 1);
            }
        },
        onPaste(e) {
            e.preventDefault();
            const txt = (e.clipboardData ? e.clipboardData.getData('text') : '') || '';
            const digits = txt.replace(/\D/g, '').slice(0, 6).split('');
            if (!digits.length) return;
            const next = ['', '', '', '', '', ''];
            digits.forEach((d, idx) => (next[idx] = d));
            this.otpDigits = next;
            this.clearErr('otp');
            this.focusOtp(Math.min(digits.length, 6) - 1);
        },
        startTick() {
            clearInterval(this.tickTimer);
            this.tickTimer = setInterval(() => {
                if (this.resendCooldown > 0) this.resendCooldown -= 1;
                if (this.otpCountdown > 0) this.otpCountdown -= 1;
                if (this.resendCooldown <= 0 && this.otpCountdown <= 0) clearInterval(this.tickTimer);
            }, 1000);
        },
        mulaiCooldown() {
            this.cooldownEmail = this.form.email;
            this.resendCooldown = RESEND_COOLDOWN;
            this.startTick();
        },
        kembaliKeMinta() {
            this.step = 'minta';
            this.resetOtpBoxes();
            this.errors = {};
            this.processing = false;
            this.otpCountdown = 0;
        },
        async mintaOtp() {
            this.errors = {};
            if (!this.form.email) {
                this.errors.email = 'Email wajib diisi.';
                return;
            }
            if (this.form.email === this.cooldownEmail && this.resendCooldown > 0) {
                this.flashNotice('info', `Kode OTP baru saja dikirim ke email ini. Silakan cek kotak masuk/spam, atau minta lagi dalam ${this.resendCooldown} detik.`);
                return;
            }
            this.processing = true;
            try {
                const res = await axios.post('/api/v1/lupa-sandi', { email: this.form.email }, { headers: { Accept: 'application/json' } });
                this.step = 'otp';
                this.resetOtpBoxes();
                this.cooldownEmail = this.form.email;
                this.resendCooldown = RESEND_COOLDOWN;
                this.otpCountdown = OTP_BERLAKU;
                this.startTick();
                this.focusOtp(0);
                this.flashNotice('info', (res.data && res.data.message) || 'Jika email terdaftar, kode OTP telah dikirim.');
            } catch (e) {
                const r = e.response;
                if (r && r.status === 422 && r.data.errors) {
                    Object.keys(r.data.errors).forEach((k) => (this.errors[k] = Array.isArray(r.data.errors[k]) ? r.data.errors[k][0] : r.data.errors[k]));
                } else {
                    this.flashNotice('error', (r && r.data && r.data.message) || 'Tidak dapat mengirim kode OTP. Coba lagi.');
                }
            } finally {
                this.processing = false;
            }
        },
        async lanjutKeReset() {
            if (this.otpValue.length !== 6) {
                this.errors = { otp: 'Masukkan 6 digit kode OTP.' };
                return;
            }
            this.errors = {};
            this.processing = true;
            try {
                await axios.post('/api/v1/verifikasi-otp', { email: this.form.email, otp: this.otpValue }, { headers: { Accept: 'application/json' } });
                this.step = 'reset';
            } catch (e) {
                const r = e.response;
                const code = r && r.data && r.data.code;
                const pesan = (r && r.data && r.data.message) || 'Kode OTP tidak valid. Silakan coba lagi.';
                this.resetOtpBoxes();
                if (code === 'OTP_LOCKED') {
                    this.kembaliKeMinta();
                    this.mulaiCooldown();
                    this.flashNotice('error', pesan);
                } else {
                    this.focusOtp(0);
                    if (code === 'OTP_EXPIRED') this.otpCountdown = 0;
                    this.errors = { otp: pesan };
                }
            } finally {
                this.processing = false;
            }
        },
        async submitReset() {
            this.errors = {};
            if (this.otpValue.length !== 6) this.errors.otp = 'Kode OTP harus 6 digit.';
            if (!this.form.password) this.errors.password = 'Kata sandi wajib diisi.';
            else if (this.form.password.length < 6) this.errors.password = 'Minimal 6 karakter.';
            if (this.form.password && this.form.password !== this.form.confirm) this.errors.confirm = 'Konfirmasi tidak cocok.';
            if (Object.keys(this.errors).length) {
                if (this.errors.otp) this.step = 'otp';
                return;
            }
            this.processing = true;
            try {
                await axios.post(
                    '/api/v1/ganti-sandi',
                    { email: this.form.email, otp: this.otpValue, password: this.form.password },
                    { headers: { Accept: 'application/json' } },
                );
                clearInterval(this.tickTimer);
                this.flashNotice('info', 'Kata sandi diperbarui — mengalihkan ke halaman masuk…');
                setTimeout(() => router.visit('/login'), 900);
            } catch (e) {
                this.processing = false;
                const r = e.response;
                const code = r && r.data && r.data.code;
                const pesanOtp = (r && r.data && r.data.message) || 'Kode OTP tidak valid. Silakan coba lagi.';
                if (code === 'OTP_LOCKED') {
                    this.resetOtpBoxes();
                    this.kembaliKeMinta();
                    this.mulaiCooldown();
                    this.flashNotice('error', pesanOtp);
                } else if (code === 'OTP_INVALID' || code === 'OTP_EXPIRED') {
                    this.step = 'otp';
                    this.resetOtpBoxes();
                    this.focusOtp(0);
                    if (code === 'OTP_EXPIRED') this.otpCountdown = 0;
                    this.errors = { otp: pesanOtp };
                } else if (r && r.status === 422 && r.data.errors) {
                    Object.keys(r.data.errors).forEach((k) => (this.errors[k] = Array.isArray(r.data.errors[k]) ? r.data.errors[k][0] : r.data.errors[k]));
                    if (this.errors.otp) this.step = 'otp';
                } else {
                    this.flashNotice('error', (r && r.data && r.data.message) || 'Tidak dapat memperbarui kata sandi.');
                }
            }
        },
    },
};
</script>
