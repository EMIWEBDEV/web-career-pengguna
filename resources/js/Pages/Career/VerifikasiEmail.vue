<script>
// WEB CAREER — halaman hasil verifikasi email (magic link). Memakai AuthShell.
// Status dari server: sukses | sudah | kadaluarsa | invalid.
import axios from 'axios';
import { Link, router } from '@inertiajs/vue3';
import AuthShell from './components/AuthShell.vue';

export default {
    layout: null,
    components: { AuthShell, Link },
    props: {
        status: { type: String, default: 'invalid' },
        email: { type: String, default: null },
    },
    data() {
        return {
            detik: 10,
            countdownTimer: null,
            resending: false,
            resendMsg: '',
            resendErr: '',
            pasteVal: '',
            pasteErr: '',
        };
    },
    computed: {
        isSukses() { return this.status === 'sukses' || this.status === 'sudah'; },
    },
    mounted() {
        if (this.isSukses) {
            this.countdownTimer = setInterval(() => {
                this.detik -= 1;
                if (this.detik <= 0) {
                    clearInterval(this.countdownTimer);
                    router.visit('/login');
                }
            }, 1000);
        }
    },
    beforeUnmount() { clearInterval(this.countdownTimer); },
    methods: {
        keLogin() {
            clearInterval(this.countdownTimer);
            router.visit('/login');
        },
        async kirimUlang() {
            if (this.resending || !this.email) return;
            this.resending = true;
            this.resendMsg = '';
            this.resendErr = '';
            try {
                const res = await axios.post('/api/v1/kirim-verifikasi', { email: this.email }, { headers: { Accept: 'application/json' } });
                this.resendMsg = (res.data && res.data.message) || 'Email verifikasi telah dikirim ulang.';
            } catch (e) {
                const r = e.response;
                this.resendErr = (r && r.data && r.data.message) || 'Gagal mengirim ulang. Silakan coba lagi.';
            } finally {
                this.resending = false;
            }
        },
        // Terima tempelan: URL lengkap, query string, atau token mentah.
        prosesTempel() {
            this.pasteErr = '';
            const raw = (this.pasteVal || '').trim();
            if (!raw) { this.pasteErr = 'Tempel dulu tautan atau token dari email kamu.'; return; }

            let token = '';
            let email = '';
            if (raw.includes('token=')) {
                try {
                    const qs = raw.includes('?') ? raw.split('?')[1] : raw;
                    const p = new URLSearchParams(qs);
                    token = (p.get('token') || '').trim();
                    email = (p.get('email') || '').trim();
                } catch (e) { /* jatuh ke validasi bawah */ }
            } else if (/^[A-Za-z0-9]{40,}$/.test(raw)) {
                token = raw;
            }

            if (!token) {
                this.pasteErr = 'Format tidak dikenali. Salin utuh tautan dari email (mengandung "token=").';
                return;
            }
            const q = new URLSearchParams({ token });
            if (email) q.set('email', email);
            router.visit('/verifikasi-email?' + q.toString());
        },
    },
};
</script>

<template>
    <AuthShell
        page-title="Verifikasi Email"
        eyebrow="Verifikasi Akun"
        title="Konfirmasi"
        title-accent="Email."
        lede="Kami sedang memeriksa tautan verifikasi dari email kamu untuk mengaktifkan akun EVO Career."
        sub-mobile="Konfirmasi email untuk mengaktifkan akun"
        back-href="/login"
        back-label="Kembali ke Masuk"
        :show-pill="false"
        :center-card="true"
        :show-ver="false"
    >
        <!-- SUKSES / SUDAH -->
        <template v-if="isSukses">
            <div class="icon-badge icon-badge--ok">
                <svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5" /></svg>
            </div>
            <p class="eyebrow-txt">VERIFIKASI AKUN</p>
            <h1>{{ status === 'sudah' ? 'Email Sudah Terverifikasi' : 'Email Berhasil Diverifikasi!' }}</h1>
            <p class="desc">
                <template v-if="status === 'sudah'">Alamat email <b>{{ email }}</b> memang sudah terverifikasi sebelumnya — kamu tidak perlu melakukan apa pun lagi.</template>
                <template v-else>Terima kasih! Alamat email <b>{{ email }}</b> sudah terkonfirmasi dan akunmu kini aktif sepenuhnya. Silakan masuk untuk mulai melamar.</template>
            </p>
            <div class="countdown">
                <span class="countdown-num">{{ detik }}</span>
                <span>Kamu akan diarahkan ke halaman masuk dalam <b>{{ detik }} detik</b>…</span>
            </div>
            <button type="button" class="btn-main" @click="keLogin">Masuk Sekarang <i class="bi bi-arrow-right"></i></button>
        </template>

        <!-- KADALUARSA -->
        <template v-else-if="status === 'kadaluarsa'">
            <div class="icon-badge icon-badge--warn">
                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9" /><path d="M12 7v5l3 3" /></svg>
            </div>
            <p class="eyebrow-txt">TAUTAN KEDALUWARSA</p>
            <h1>Waktunya Sudah Habis</h1>
            <p class="desc">Demi keamanan, tautan verifikasi hanya berlaku <b>30 menit</b> dan sekali pakai. Tenang — cukup satu klik untuk mendapatkan tautan baru ke <b>{{ email }}</b>.</p>
            <button type="button" class="btn-main" :disabled="resending" :onClick="resending ? null : kirimUlang">
                <span v-if="resending" class="spinner" aria-hidden="true"></span>
                {{ resending ? 'Mengirim…' : 'Kirim Ulang Email Verifikasi' }}
            </button>
            <p v-if="resendMsg" class="feedback feedback--ok"><i class="bi bi-check-circle-fill"></i> {{ resendMsg }}</p>
            <p v-if="resendErr" class="feedback feedback--err"><i class="bi bi-exclamation-triangle-fill"></i> {{ resendErr }}</p>
        </template>

        <!-- INVALID -->
        <template v-else>
            <div class="icon-badge icon-badge--err">
                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6L6 18" /><path d="M6 6l12 12" /></svg>
            </div>
            <p class="eyebrow-txt">TAUTAN TIDAK VALID</p>
            <h1>Hmm, Tautannya Tidak Dikenali</h1>
            <p class="desc">Tautan mungkin terpotong saat disalin, sudah terpakai, atau sudah diganti tautan yang lebih baru. Coba <b>tempel utuh</b> tautan dari email terbaru kamu di bawah ini:</p>
            <div class="paste-field" :class="{ err: pasteErr }">
                <i class="bi bi-link-45deg"></i>
                <input v-model="pasteVal" type="text" placeholder="Tempel tautan / token dari email di sini" @input="pasteErr = ''" @keyup.enter="prosesTempel" />
            </div>
            <p v-if="pasteErr" class="feedback feedback--err">{{ pasteErr }}</p>
            <button type="button" class="btn-main" @click="prosesTempel">Verifikasi Sekarang</button>
            <p class="alt-row">Sudah terverifikasi sebelumnya? <Link href="/login" class="alt-link">Masuk di sini</Link></p>
        </template>

        <div class="ver-row">
                <span class="ver-tag" title="Versi aplikasi">Versi {{ $page.props.appVersion || '1.0.0' }}</span>
                <span>© 2026 EVO Group</span>
            </div>
    </AuthShell>
</template>
