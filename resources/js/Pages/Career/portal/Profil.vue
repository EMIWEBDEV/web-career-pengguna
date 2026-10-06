<!-- WEB CAREER — Profil Saya (kandidat).

     Seluruh isinya datang dari prop `user` (App\Support\Career\ProfilPengguna),
     dibaca ulang dari DB setiap halaman dibuka. Tidak ada lagi cadangan dari
     sessionStorage: sumber itu peninggalan masa prototipe tanpa DB, dan sejak
     akun benar-benar hidup di N_WEB_CAREERS_Users ia hanya sanggup memunculkan
     angka yang salah (selalu 0, dan berbeda tiap peramban). Kalau server tidak
     mengirim `user`, artinya memang belum login — dan itu yang ditampilkan. -->
<template>
    <Head title="Profil Saya" />
    <div class="wca">
        <div class="wca-phead">
            <div>
                <h1>Profil Saya</h1>
                <p>Informasi akun &amp; ringkasan lamaranmu di EVO Group.</p>
            </div>
        </div>

        <!-- Belum login -->
        <div v-if="!me" class="wca-empty2">
            <div class="wca-empty2__ic"><i class="bi bi-person-lock"></i></div>
            <h3>Kamu belum masuk</h3>
            <p>Masuk untuk melihat profil &amp; akunmu.</p>
            <Link href="/login?redirect=/profil" class="wca-btn wca-btn--primary"><i class="bi bi-box-arrow-in-right"></i> Masuk</Link>
        </div>

        <div v-else class="wca-grid wca-grid--2">
            <!-- ══ DATA AKUN ══ -->
            <div class="wca-card">
                <div class="wca-card__head"><h3><i class="bi bi-person-vcard"></i> Data Akun</h3></div>
                <div class="wca-card__body">
                    <div class="wca-prof__id">
                        <span class="wca-avatar wca-prof__av">{{ initials(me.nama) }}</span>
                        <div>
                            <div class="wca-prof__nama">{{ me.nama || '—' }}</div>
                            <div class="wca-prof__badges">
                                <span class="wca-badge" :class="peranWarna"><i class="bi" :class="peranIkon"></i> {{ me.roleLabel }}</span>
                                <span class="wca-badge" :class="aktif ? 'wca-b--green' : 'wca-b--red'">
                                    <i class="bi" :class="aktif ? 'bi-check-circle' : 'bi-x-circle'"></i> {{ aktif ? 'Aktif' : 'Nonaktif' }}
                                </span>
                                <span class="wca-badge" :class="me.emailVerified ? 'wca-b--indigo' : 'wca-b--amber'">
                                    <i class="bi" :class="me.emailVerified ? 'bi-patch-check' : 'bi-exclamation-triangle'"></i>
                                    {{ me.emailVerified ? 'Email terverifikasi' : 'Email belum diverifikasi' }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="wca-dinfo">
                        <div><small>Email</small><b>{{ me.email || '—' }}</b></div>
                        <div><small>No. HP</small><b>{{ me.no_hp || '—' }}</b></div>
                        <div v-if="me.nik"><small>No. KTP</small><b>{{ me.nik }}</b></div>
                        <div><small>Klasifikasi Akun</small><b>{{ me.klasifikasiLabel || '—' }}</b></div>
                        <div><small>Masa Berlaku</small><b>{{ masaBerlaku }}</b></div>
                        <div><small>Terdaftar</small><b>{{ fmt(me.mulai_berlaku || me.terdaftarSejak) }}</b></div>
                        <div><small>Login Terakhir</small><b>{{ fmtDT(me.last_login_at) }}</b></div>
                    </div>

                    <div v-if="expiringSoon" class="wca-note wca-note--warn" style="margin-top: 1rem">
                        <i class="bi bi-hourglass-split"></i>
                        <span>Masa berlaku akun tinggal <b>{{ sisaHari }} hari</b>. Perpanjang bila diperlukan.</span>
                    </div>
                </div>
            </div>

            <div>
                <!-- ══ RINGKASAN LAMARAN ══ -->
                <div v-if="me.ringkasan" class="wca-card" style="margin-bottom: 1.25rem">
                    <div class="wca-card__head"><h3><i class="bi bi-bar-chart"></i> Ringkasan Lamaran</h3></div>
                    <div class="wca-card__body">
                        <div class="wca-dinfo">
                            <div><small>Total Terkirim</small><b>{{ me.ringkasan.total }}</b></div>
                            <div><small>Sedang Berjalan</small><b>{{ me.ringkasan.berjalan }}</b></div>
                            <div><small>Lulus</small><b>{{ me.ringkasan.lulus }}</b></div>
                            <div><small>Tidak Lanjut</small><b>{{ me.ringkasan.gugur }}</b></div>
                        </div>
                        <Link href="/kandidat/portal" class="wca-btn wca-btn--soft" style="width: 100%; margin-top: 1rem"><i class="bi bi-file-earmark-text"></i> Lihat Lamaran Saya</Link>
                    </div>
                </div>

                <!-- ══ KEAMANAN ══ -->
                <div class="wca-card">
                    <div class="wca-card__head"><h3><i class="bi bi-shield-check"></i> Keamanan</h3></div>
                    <div class="wca-card__body">
                        <div class="wca-dinfo" style="margin-bottom: 1rem">
                            <div><small>Verifikasi Email</small><b>{{ me.emailVerified ? fmtDT(me.emailVerifiedAt) : 'Belum diverifikasi' }}</b></div>
                            <div><small>Sandi Terakhir Diubah</small><b>{{ fmtDT(me.pwdChangedAt) }}</b></div>
                        </div>
                        <Link :href="'/ganti-sandi?email=' + encodeURIComponent(me.email || '')" class="wca-btn wca-btn--ghost" style="width: 100%; margin-bottom: 0.6rem"><i class="bi bi-key"></i> Ganti Kata Sandi</Link>
                        <a href="/logout" class="wca-btn wca-btn--danger" style="width: 100%"><i class="bi bi-box-arrow-right"></i> Keluar</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { initials } from '@utils/career/inisial';

const props = defineProps({
    user: { type: Object, default: null },
});

const me = computed(() => props.user);
const aktif = computed(() => (me.value?.status || 'AKTIF') === 'AKTIF');

// Warna badge peran. Superadmin sengaja dibedakan dari admin: dua peran itu
// tidak sama besarnya, dan sebelumnya keduanya jatuh ke label "Kandidat".
const PERAN_WARNA = { KANDIDAT: 'wca-b--sky' };
const PERAN_IKON = { KANDIDAT: 'bi-person-badge' };
const peranWarna = computed(() => PERAN_WARNA[me.value?.role] || 'wca-b--slate');
const peranIkon = computed(() => PERAN_IKON[me.value?.role] || 'bi-person');

const BULAN = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
function fmt(v) {
    if (!v) return '—';
    const d = new Date(String(v).replace(' ', 'T'));
    if (isNaN(d.getTime())) return v;
    return `${d.getDate()} ${BULAN[d.getMonth()]} ${d.getFullYear()}`;
}
function fmtDT(v) {
    if (!v) return 'Belum pernah';
    const d = new Date(String(v).replace(' ', 'T'));
    if (isNaN(d.getTime())) return v;
    return `${d.getDate()} ${BULAN[d.getMonth()]} ${d.getFullYear()} · ${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}`;
}

const masaBerlaku = computed(() => (me.value?.valid_until ? `s/d ${fmt(me.value.valid_until)}` : 'Permanen (tanpa batas)'));
const sisaHari = computed(() => {
    if (!me.value?.valid_until) return null;
    const d = new Date(String(me.value.valid_until).replace(' ', 'T'));
    return Math.ceil((d.getTime() - Date.now()) / 86400000);
});
const expiringSoon = computed(() => sisaHari.value !== null && sisaHari.value > 0 && sisaHari.value <= 14);
</script>
