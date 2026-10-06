<script>
// WEB CAREER — halaman "Menunggu Verifikasi Email". Tujuan redirect setelah
// register berhasil. Memakai AuthShell bersama seluruh halaman auth.
import axios from 'axios';
import { Link, router } from '@inertiajs/vue3';
import AuthShell from './components/AuthShell.vue';

const EXPIRY_DETIK = 30 * 60; // selaras VERIF_BERLAKU_MENIT di server
const RESEND_COOLDOWN = 120; // selaras VERIF_THROTTLE_MENIT (2 menit)
const POLL_MS = 4000;

export default {
    layout: null,
    components: { AuthShell, Link },
    props: {
        email: { type: String, default: '' },
    },
    data() {
        return {
            state: 'menunggu', // menunggu | sukses
            sisaExpiry: EXPIRY_DETIK,
            cooldown: RESEND_COOLDOWN,
            resending: false,
            noticeMsg: '',
            noticeType: 'info',
            pasteOpen: false,
            pasteVal: '',
            pasteErr: '',
            pasteBusy: false,
            pollTimer: null,
            tickTimer: null,
        };
    },
    computed: {
        expiryLabel() {
            const s = Math.max(0, this.sisaExpiry);
            const m = Math.floor(s / 60);
            const d = s % 60;
            return m + ':' + String(d).padStart(2, '0');
        },
        expiryHabis() { return this.sisaExpiry <= 0; },
        emailValid() { return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(this.email || ''); },
    },
    mounted() {
        if (!this.emailValid) return;
        this.startTick();
        this.startPolling();
    },
    beforeUnmount() {
        clearInterval(this.pollTimer);
        clearInterval(this.tickTimer);
    },
    methods: {
        startTick() {
            this.tickTimer = setInterval(() => {
                if (this.sisaExpiry > 0) this.sisaExpiry -= 1;
                if (this.cooldown > 0) this.cooldown -= 1;
            }, 1000);
        },
        startPolling() { this.pollTimer = setInterval(this.cekStatus, POLL_MS); },
        async cekStatus() {
            try {
                const res = await axios.get('/api/v1/status-verifikasi', {
                    params: { email: this.email },
                    headers: { Accept: 'application/json' },
                });
                if (res.data && res.data.result && res.data.result.verified) this.onVerified();
            } catch (e) { /* diamkan; polling lanjut */ }
        },
        onVerified() {
            if (this.state === 'sukses') return;
            this.state = 'sukses';
            clearInterval(this.pollTimer);
            clearInterval(this.tickTimer);
            setTimeout(() => router.visit('/login'), 2200);
        },
        showNotice(type, msg) {
            this.noticeType = type;
            this.noticeMsg = msg;
        },
        async kirimUlang() {
            if (this.resending || this.cooldown > 0 || !this.emailValid) return;
            this.resending = true;
            this.noticeMsg = '';
            try {
                const res = await axios.post('/api/v1/kirim-verifikasi', { email: this.email }, { headers: { Accept: 'application/json' } });
                this.showNotice('info', (res.data && res.data.message) || 'Email verifikasi telah dikirim ulang.');
                this.sisaExpiry = EXPIRY_DETIK;
                this.cooldown = RESEND_COOLDOWN;
            } catch (e) {
                const r = e.response;
                this.showNotice('error', (r && r.data && r.data.message) || 'Gagal mengirim ulang. Silakan coba lagi.');
                if (r && r.status === 429) this.cooldown = RESEND_COOLDOWN;
            } finally {
                this.resending = false;
            }
        },
        parseTempel(raw) {
            const s = (raw || '').trim();
            if (!s) return null;
            let token = '';
            let email = '';
            if (s.includes('token=')) {
                try {
                    const qs = s.includes('?') ? s.split('?').slice(1).join('?') : s;
                    const p = new URLSearchParams(qs);
                    token = (p.get('token') || '').trim();
                    email = (p.get('email') || '').trim();
                } catch (e) { /* noop */ }
            } else if (/^[A-Za-z0-9]{40,}$/.test(s)) {
                token = s;
            }
            return token ? { token, email } : null;
        },
        async prosesTempel() {
            if (this.pasteBusy) return;
            this.pasteErr = '';
            const parsed = this.parseTempel(this.pasteVal);
            if (!parsed) {
                this.pasteErr = 'Format tidak dikenali. Salin utuh tautan dari email (mengandung "token=").';
                return;
            }
            this.pasteBusy = true;
            try {
                const body = { token: parsed.token };
                if (parsed.email || this.email) body.email = parsed.email || this.email;
                const res = await axios.post('/api/v1/verifikasi-token', body, { headers: { Accept: 'application/json' } });
                const st = res.data && res.data.result && res.data.result.status;
                if (st === 'sukses' || st === 'sudah') this.onVerified();
            } catch (e) {
                const r = e.response;
                if (r && r.status === 410) {
                    this.pasteErr = 'Tautan sudah kedaluwarsa. Silakan tekan "Kirim Ulang" untuk tautan baru.';
                } else {
                    this.pasteErr = (r && r.data && r.data.message) || 'Verifikasi gagal. Periksa kembali tautan yang kamu tempel.';
                }
            } finally {
                this.pasteBusy = false;
            }
        },
    },
};
</script>

<template>
    <AuthShell
        page-title="Menunggu Verifikasi"
        eyebrow="Verifikasi Email"
        title="Satu Langkah"
        title-accent="Lagi."
        lede="Aktifkan akunmu lewat tautan yang kami kirim ke email. Halaman ini otomatis lanjut begitu emailmu terverifikasi."
        sub-mobile="Cek email untuk mengaktifkan akun EVO Career"
        back-href="/login"
        back-label="Kembali ke Masuk"
        :show-pill="false"
        :center-card="true"
        :show-ver="false"
    >
        <!-- SUKSES -->
        <template v-if="state === 'sukses'">
            <div class="icon-badge icon-badge--ok">
                <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5" /></svg>
            </div>
            <p class="eyebrow-txt">TERVERIFIKASI</p>
            <h1>Email Berhasil Diverifikasi! 🎉</h1>
            <p class="desc">Akunmu kini aktif sepenuhnya. Sebentar, kami arahkan kamu ke halaman masuk…</p>
            <div class="redir-row"><span class="spinner spinner--indigo"></span> Mengalihkan ke halaman masuk…</div>
        </template>

        <!-- EMAIL TIDAK DIKETAHUI -->
        <template v-else-if="!emailValid">
            <div class="icon-badge icon-badge--warn">
                <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 9v4" /><path d="M12 17h.01" /><path d="M10.3 3.9L2 18a2 2 0 0 0 1.7 3h16.6a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z" /></svg>
            </div>
            <h1>Halaman Verifikasi</h1>
            <p class="desc">Halaman ini muncul setelah kamu mendaftar. Silakan mulai dari pendaftaran atau masuk bila sudah punya akun.</p>
            <Link href="/register" class="btn-main">Daftar Sekarang</Link>
            <p class="alt-row">Sudah punya akun? <Link href="/login" class="alt-link">Masuk di sini</Link></p>
        </template>

        <!-- MENUNGGU -->
        <template v-else>
            <div class="icon-badge icon-badge--wait">
                <svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 6h16a1 1 0 0 1 1 1v10a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1z" /><path d="M3.5 7.2l8.5 6 8.5-6" /></svg>
                <span class="ping"></span>
            </div>
            <p class="eyebrow-txt">SATU LANGKAH LAGI</p>
            <h1>Cek Email Kamu</h1>
            <p class="desc">Kami telah mengirim tautan verifikasi ke<br /><b class="email-chip">{{ email }}</b></p>
            <p class="desc-sub">Buka email itu lalu tekan <b>“Verifikasi Email Saya”</b>. Halaman ini akan otomatis lanjut begitu emailmu terverifikasi — tidak perlu menyegarkan (refresh) apa pun.</p>

            <div class="live-row">
                <span class="dot-live"></span>
                Menunggu verifikasi…
                <span class="live-sep">•</span>
                Tautan berlaku <b :class="{ danger: expiryHabis }">{{ expiryHabis ? 'habis' : expiryLabel }}</b>
            </div>

            <div class="wait-note">
                <i class="bi bi-clock-history"></i>
                <span>Email dikirim melalui antrean — biasanya tiba dalam <b>1–3 menit</b>. Jika belum ada, cek folder <b>Spam</b>/<b>Promosi</b> sebelum mengirim ulang.</span>
            </div>

            <div v-if="noticeMsg" class="notice" :class="noticeType === 'error' ? 'notice--err' : 'notice--ok'">
                <i :class="noticeType === 'error' ? 'bi bi-exclamation-triangle-fill' : 'bi bi-check-circle-fill'"></i>
                <span>{{ noticeMsg }}</span>
            </div>

            <button type="button" class="btn-main" :disabled="resending || cooldown > 0" @click="kirimUlang">
                <span v-if="resending" class="spinner" aria-hidden="true"></span>
                <template v-if="cooldown > 0">Kirim ulang dalam {{ cooldown }}s</template>
                <template v-else>{{ resending ? 'Mengirim…' : 'Kirim Ulang Email Verifikasi' }}</template>
            </button>

            <button type="button" class="paste-toggle" @click="pasteOpen = !pasteOpen">
                <i class="bi" :class="pasteOpen ? 'bi-chevron-up' : 'bi-chevron-down'"></i>
                Tautan di email tidak bisa diklik? Tempel di sini
            </button>
            <transition name="collapse">
                <div v-if="pasteOpen" class="paste-box">
                    <p class="paste-hint">Salin seluruh tautan dari email (yang mengandung <code>token=</code>), lalu tempel di bawah — verifikasi tetap di halaman ini.</p>
                    <div class="paste-field" :class="{ err: pasteErr }">
                        <i class="bi bi-link-45deg"></i>
                        <input v-model="pasteVal" type="text" placeholder="Tempel tautan / token dari email" @input="pasteErr = ''" @keyup.enter="prosesTempel" />
                    </div>
                    <p v-if="pasteErr" class="paste-err">{{ pasteErr }}</p>
                    <button type="button" class="btn-ghost" :disabled="pasteBusy" :onClick="pasteBusy ? null : prosesTempel">
                        <span v-if="pasteBusy" class="spinner spinner--indigo" aria-hidden="true"></span>
                        {{ pasteBusy ? 'Memverifikasi…' : 'Verifikasi Sekarang' }}
                    </button>
                </div>
            </transition>

            <p class="alt-row">Salah alamat email? <Link href="/register" class="alt-link">Daftar ulang</Link></p>
        </template>

        <div class="ver-row">
                <span class="ver-tag" title="Versi aplikasi">Versi {{ $page.props.appVersion || '1.0.0' }}</span>
                <span>© 2026 EVO Group</span>
            </div>
    </AuthShell>
</template>
