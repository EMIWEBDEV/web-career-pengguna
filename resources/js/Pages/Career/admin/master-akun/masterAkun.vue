<!-- WEB CAREER — Master Akun (Users). Tabs Pengguna/Pelamar vs Admin/Superadmin. DATA dari /api/v1/master-akun. -->
<template>
    <Head title="Master Akun" />
    <div class="wca">
        <div class="wca-phead">
            <div>
                <h1>Master Akun</h1>
                <p>Kelola akun sistem. Tab <b>Pengguna</b> = akun pelamar/kandidat, tab <b>Admin</b> = akun pengelola (Admin & Superadmin).</p>
            </div>
            <div class="wca-phead__actions">
                <button class="wca-btn wca-btn--primary" @click="openCreate">
                    <i class="bi bi-plus-lg"></i> {{ tab === 'pengguna' ? 'Tambah Pengguna' : 'Tambah Admin' }}
                </button>
            </div>
        </div>

        <div class="wca-segtab">
            <button class="wca-segtab__btn" :class="{ on: tab === 'pengguna' }" @click="switchTab('pengguna')">
                <i class="bi bi-people"></i> Pengguna / Pelamar <span class="wca-segtab__count">{{ penggunaList.length }}</span>
            </button>
            <button class="wca-segtab__btn" :class="{ on: tab === 'admin' }" @click="switchTab('admin')">
                <i class="bi bi-shield-lock"></i> Admin &amp; Superadmin <span class="wca-segtab__count">{{ adminList.length }}</span>
            </button>
        </div>

        <div class="wca-stats">
            <div class="wca-stat"><div class="wca-stat__top"><span class="wca-stat__ico"><i class="bi bi-person-lines-fill"></i></span></div><div class="wca-stat__num">{{ activeList.length }}</div><div class="wca-stat__label">Total {{ tab === 'pengguna' ? 'Pengguna' : 'Admin' }}</div></div>
            <div class="wca-stat"><div class="wca-stat__top"><span class="wca-stat__ico" style="background:rgba(16,185,129,.12);color:#059669"><i class="bi bi-check-circle"></i></span></div><div class="wca-stat__num">{{ countAktif }}</div><div class="wca-stat__label">Aktif</div></div>
            <div class="wca-stat"><div class="wca-stat__top"><span class="wca-stat__ico" style="background:rgba(148,163,184,.16);color:#475569"><i class="bi bi-slash-circle"></i></span></div><div class="wca-stat__num">{{ activeList.length - countAktif }}</div><div class="wca-stat__label">Nonaktif</div></div>
        </div>

        <div class="wca-toolbar">
            <div class="wca-search2"><i class="bi bi-search"></i><input v-model="q" type="text" placeholder="Cari nama / email…" /></div>
        </div>

        <!-- KEGAGALAN KIRIM TIDAK LEWAT TOAST.
             Kalimat galat SMTP panjang, dan justru itulah isinya yang berguna —
             "535 Authentication failed" menuntut tindakan yang sama sekali
             berbeda dari "Connection timed out". Toast tiga detik menghapusnya
             sebelum sempat dibaca, apalagi disalin ke tim infrastruktur. -->
        <div v-if="verifGagal" class="akun-alert">
            <i class="bi bi-exclamation-triangle-fill"></i>
            <div>
                <b>Gagal mengirim verifikasi ke {{ verifGagal.email }}</b>
                <p>{{ verifGagal.pesan }}</p>
                <span>Akun tetap tidak terverifikasi. Salin pesan ini bila perlu dilaporkan — pesan yang sama tersimpan di log server.</span>
            </div>
            <button class="akun-alert__x" title="Tutup" @click="verifGagal = null"><i class="bi bi-x-lg"></i></button>
        </div>

        <div class="wca-card">
            <div v-loading="loading" class="wca-card__body--flush">
                <div class="wca-tablewrap">
                    <table class="wca-table">
                        <thead>
                            <tr><th>Nama / Email</th><th>Peran</th><th>Verifikasi Email</th><th>Klasifikasi</th><th>Masa Berlaku</th><th>Status</th><th>Login Terakhir</th><th></th></tr>
                        </thead>
                        <tbody>
                            <tr v-for="a in filtered" :key="a.id">
                                <td>
                                    <div class="akun-idn">
                                        <span class="akun-ava" :class="avatarClass(a.role)">{{ inisial(a.nama) }}</span>
                                        <div><strong>{{ a.nama }}</strong><small>{{ a.email }}</small></div>
                                    </div>
                                </td>
                                <td><span class="wca-badge" :class="roleBadge(a.role)">{{ roleLabel(a.role) }}</span></td>
                                <!-- VERIFIKASI EMAIL — keadaan DAN sebabnya, bukan cuma lencana.
                                     Yang paling menolong bukan kata "belum", melainkan riwayat di
                                     bawahnya: "6x dicoba, tak pernah terkirim" langsung menunjuk ke
                                     SMTP, sementara "terkirim 13:55" menunjuk ke kotak masuk
                                     kandidat. Dua kesimpulan itu menuntut tindakan yang berbeda. -->
                                <td>
                                    <div class="akun-verif">
                                        <span v-if="a.emailVerified" class="wca-badge wca-b--emerald" :title="a.emailVerifiedAt ? 'Terverifikasi ' + fmtDateTime(a.emailVerifiedAt) : ''">
                                            <i class="bi bi-patch-check-fill"></i> Terverifikasi
                                        </span>
                                        <template v-else>
                                            <span class="wca-badge" :class="a.verifAttempt && !a.verifSentAt ? 'wca-b--rose' : 'wca-b--amber'">
                                                <i class="bi" :class="a.verifAttempt && !a.verifSentAt ? 'bi-exclamation-octagon-fill' : 'bi-hourglass-split'"></i>
                                                {{ a.verifAttempt && !a.verifSentAt ? 'Email tak terkirim' : 'Belum verifikasi' }}
                                            </span>
                                            <small class="akun-verif__note">{{ catatanVerif(a) }}</small>
                                            <button class="akun-verif__btn" :disabled="kirimId === a.id" :onClick="kirimId === a.id ? null : () => kirimVerifikasi(a)">
                                                <i class="bi" :class="kirimId === a.id ? 'bi-arrow-repeat akun-spin' : 'bi-envelope-arrow-up'"></i>
                                                {{ kirimId === a.id ? 'Mengirim…' : 'Kirim Ulang' }}
                                            </button>
                                        </template>
                                    </div>
                                </td>
                                <td>{{ a.klasifikasi || '—' }}</td>
                                <td>
                                    <span v-if="!a.valid_until" class="wca-badge wca-b--slate">Permanen</span>
                                    <span v-else :class="{ 'akun-exp': isExpired(a.valid_until) }">{{ fmtDate(a.valid_until) }}</span>
                                </td>
                                <td>
                                    <div class="akun-status">
                                        <el-switch :model-value="a.status === 'AKTIF'" @change="(v) => setStatus(a, v)" />
                                        <span class="akun-status__lbl" :class="a.status === 'AKTIF' ? 'is-on' : 'is-off'">{{ a.status === 'AKTIF' ? 'Aktif' : 'Nonaktif' }}</span>
                                    </div>
                                </td>
                                <td><small class="akun-muted">{{ a.last_login_at ? fmtDateTime(a.last_login_at) : 'Belum pernah' }}</small></td>
                                <td>
                                    <div style="display:flex;gap:.35rem;justify-content:flex-end">
                                        <!-- Hanya untuk akun internal yang sudah ditautkan ke karyawan:
                                             akun tanpa kode karyawan mustahil memegang loker, jadi tombolnya
                                             tidak digambar — bukan digambar lalu memunculkan daftar kosong. -->
                                        <button
                                            v-if="a.role !== 'KANDIDAT' && a.kodeKaryawan"
                                            class="wca-iconbtn akun-ibtn--serah"
                                            title="Pekerjaan yang dipegang & serah terima"
                                            @click="bukaSerah(a)"
                                        ><i class="bi bi-arrow-left-right"></i></button>
                                        <button class="wca-iconbtn" title="Ubah" @click="openEdit(a)"><i class="bi bi-pencil"></i></button>
                                        <button class="wca-iconbtn wca-iconbtn--danger" title="Hapus" @click="askRemove(a)"><i class="bi bi-trash"></i></button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="!filtered.length"><td colspan="8"><div class="wca-empty"><i class="bi bi-person-x"></i><h4>Belum ada {{ tab === 'pengguna' ? 'pengguna' : 'admin' }}</h4></div></td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <AdminModal :busy="saving" :show="show" :title="editingId ? 'Ubah Akun' : (tab === 'pengguna' ? 'Pengguna Baru' : 'Admin Baru')" subtitle="Akun akses sistem" icon="bi-person-badge" :save-label="editingId ? 'Perbarui' : 'Simpan Akun'" @close="show = false" @save="save">
            <div class="wca-fsection">
                <div class="wca-fsection__label"><i class="bi bi-person"></i> Identitas</div>
                <div class="wca-form">
                    <div class="wca-frow">
                        <div><label class="wca-field-lbl">Nama Lengkap</label><el-input v-model="form.nama" placeholder="Nama sesuai identitas" /></div>
                        <div><label class="wca-field-lbl">No. HP</label><el-input v-model="form.phone" placeholder="62812xxxxxxx" /></div>
                    </div>
                    <div><label class="wca-field-lbl">Email</label><el-input v-model="form.email" placeholder="nama@email.com" /></div>
                    <div>
                        <label class="wca-field-lbl">Kata Sandi <span v-if="editingId" class="akun-hint">(kosongkan bila tidak diganti)</span></label>
                        <el-input v-model="form.password" type="password" show-password :placeholder="editingId ? '••••••••' : 'Minimal 6 karakter'" />
                    </div>
                </div>
            </div>
            <div class="wca-fsection">
                <div class="wca-fsection__label"><i class="bi bi-shield-check"></i> Peran &amp; Akses</div>
                <div class="wca-form">
                    <div class="wca-frow">
                        <div>
                            <label class="wca-field-lbl">Peran</label>
                            <el-select filterable v-model="form.role" placeholder="Pilih peran" style="width:100%">
                                <el-option v-for="r in roleOptions" :key="r.value" :label="r.label" :value="r.value" />
                            </el-select>
                        </div>
                        <div><label class="wca-field-lbl">Klasifikasi (masa berlaku)</label><RefSelect type="klasifikasi-akun" v-model="form.klasifikasi" placeholder="Pilih klasifikasi" /></div>
                    </div>
                    <div>
                        <label class="wca-field-lbl">Status</label>
                        <el-select filterable v-model="form.status" placeholder="Pilih status" style="width:100%">
                            <el-option label="Aktif" value="AKTIF" />
                            <el-option label="Nonaktif" value="NONAKTIF" />
                        </el-select>
                    </div>

                    <!-- ══ JEMBATAN AKUN -> KARYAWAN ══════════════════════════
                         MPP menyimpan penanggung jawabnya sebagai KODE KARYAWAN
                         ('A1', 'H45'), bukan sebagai akun. Tanpa kolom ini sistem
                         tidak punya cara mengetahui akun mana yang setara dengan
                         karyawan itu — dan pertanyaan "MPP siapa yang boleh saya
                         buka" tidak bisa dijawab sama sekali.

                         Hanya digambar untuk akun internal: kandidat memang bukan
                         karyawan, dan menawarkan kotak ini pada 395 akun kandidat
                         cuma mengundang isian yang salah. -->
                    <div v-if="form.role !== 'KANDIDAT'">
                        <label class="wca-field-lbl">
                            Karyawan Terkait
                            <span class="akun-hint">(untuk lingkup MPP &amp; program)</span>
                        </label>
                        <!-- ISIAN BEBAS, bukan pilihan dari tabel Karyawan.
                             Sumber data kepegawaian yang dipakai berbeda dari tabel
                             itu, jadi memaksanya memilih dari sana berarti menolak
                             kode yang justru benar. -->
                        <el-input
                            v-model="form.kodeKaryawan"
                            placeholder="mis. A1"
                            maxlength="20"
                            clearable
                        >
                            <template #prefix><i class="bi bi-person-vcard"></i></template>
                        </el-input>
                        <small class="akun-hint akun-hint--blok">
                            Boleh dikosongkan. Selama kosong, akun ini tidak dikenali sebagai penanggung jawab
                            loker mana pun — dan lingkup <b>Sendiri</b> / <b>Tim</b> tidak bisa ditegakkan untuknya.
                            <br />Ditulis apa adanya; tidak dicocokkan ke daftar mana pun, jadi <b>pastikan ejaannya benar</b>.
                        </small>
                    </div>
                </div>
            </div>
        </AdminModal>

        <!-- ══ PEKERJAAN YANG DIPEGANG & SERAH TERIMA ══════════════════════════
             Dibuka dari AKUN orang yang berhalangan, bukan dari salah satu
             programnya — sebab titik tolaknya adalah ORANGNYA: "si A masuk
             rumah sakit, ia sedang memegang apa saja?".

             Centang per loker, jadi satu panel melayani dua keadaan sekaligus:
             centang semua = serah terima seluruh program (kasus cuti),
             centang satu = rekan membantu satu loker saja. -->
        <AdminModal
            :show="serahShow"
            xl
            icon="bi-arrow-left-right"
            title="Pekerjaan yang Dipegang"
            :subtitle="serahTarget ? `${serahTarget.nama} — ${serahTarget.karyawanNama || serahTarget.kodeKaryawan}` : ''"
            :busy="serahSibuk"
            busy-label="Memindahkan…"
            foot-note="Serah terima tidak menyentuh satu baris lamaran pun — kandidat ikut lokernya."
            @close="serahShow = false"
        >
            <div v-if="serahMuat" class="akun-hint">Memuat daftar loker…</div>

            <div v-else-if="!serahLoker.length" class="wca-empty">
                <i class="bi bi-inbox"></i>
                <h4>Tidak memegang loker apa pun</h4>
                <p class="akun-hint">Tidak ada yang perlu diserahterimakan dari akun ini.</p>
            </div>

            <template v-else>
                <div class="akun-serah-bar">
                    <label class="akun-chk">
                        <input type="checkbox" :checked="semuaTerpilih" @change="pilihSemua($event.target.checked)" />
                        <span>Pilih semua ({{ serahLoker.length }} loker)</span>
                    </label>
                    <span class="akun-hint">
                        Terpilih <b>{{ serahPilih.length }}</b> loker &middot; <b>{{ kandidatTerpilih }}</b> kandidat
                    </span>
                </div>

                <div class="akun-serah-list">
                    <label
                        v-for="l in serahLoker"
                        :key="l.id"
                        class="akun-serah-item"
                        :class="{ on: serahPilih.includes(l.id) }"
                    >
                        <input type="checkbox" :value="l.id" v-model="serahPilih" />
                        <div class="akun-serah-item__body">
                            <strong>{{ l.posisi }}</strong>
                            <small>{{ l.program }} &middot; {{ l.mppRef || '—' }}</small>
                        </div>
                        <div class="akun-serah-item__meta">
                            <span><i class="bi bi-people-fill"></i> {{ l.kandidat }} kandidat</span>
                            <span :class="l.status === 'PENUH' ? 'is-penuh' : ''">{{ l.terisi }}/{{ l.kuota }}</span>
                        </div>
                    </label>
                </div>

                <div class="wca-frow" style="margin-top:1rem">
                    <div>
                        <label class="wca-field-lbl">Serahkan ke <span class="mmp-req">*</span></label>
                        <!-- Penerima DIPILIH, bukan diketik: ia harus punya akun
                             yang bisa masuk, kalau tidak lokernya berpindah ke
                             kode yang tak seorang pun bisa mengerjakannya.
                             Sumbernya akun internal di sini, bukan tabel Karyawan. -->
                        <el-select
                            v-model="serahKe"
                            filterable remote clearable reserve-keyword
                            :remote-method="cariPenerima"
                            :loading="penerimaLoading"
                            placeholder="Pilih akun penerima…"
                            style="width:100%"
                        >
                            <el-option v-for="k in penerimaOptions" :key="k.value" :label="k.label" :value="k.value" />
                            <template #empty><div class="akun-selempty">Hanya akun internal aktif yang sudah punya kode karyawan</div></template>
                        </el-select>
                    </div>
                    <div>
                        <label class="wca-field-lbl">Alasan <span class="mmp-req">*</span></label>
                        <el-input v-model="serahAlasan" placeholder="mis. cuti sakit s/d 5 September" maxlength="500" show-word-limit />
                    </div>
                </div>
                <small class="akun-hint akun-hint--blok">
                    Alasan wajib. Inilah satu-satunya jawaban atas &ldquo;kenapa kandidat ini tiba-tiba dipegang
                    orang lain di tengah proses&rdquo; saat riwayatnya dibaca berbulan-bulan kemudian.
                </small>
            </template>

            <template #footer>
                <button class="wca-btn wca-btn--ghost" type="button" :disabled="serahSibuk" @click="serahShow = false">
                    <i class="bi bi-x-lg"></i> Tutup
                </button>
                <button
                    v-if="serahLoker.length"
                    class="wca-btn wca-btn--dark"
                    type="button"
                    :disabled="!bolehSerah"
                    @click="simpanSerah"
                >
                    <span v-if="serahSibuk" class="wca-spin" aria-hidden="true"></span>
                    <i v-else class="bi bi-arrow-left-right"></i>
                    Serahkan {{ serahPilih.length || '' }} loker
                </button>
            </template>
        </AdminModal>

        <ConfirmModal :show="delShow" title="Hapus Akun" :busy="deleting" confirm-label="Ya, Hapus Akun" note="Data akun akan dihapus permanen." @cancel="delShow = false" @confirm="confirmDelete">
            Yakin ingin menghapus akun <strong>{{ delTarget?.nama }}</strong> <span style="color:#7c81a3">({{ delTarget?.email }})</span>?
        </ConfirmModal>

        <transition name="wca-toast"><div v-if="toast" class="wca-toast"><i class="bi bi-check-circle-fill"></i> {{ toast }}</div></transition>
    </div>
</template>

<script>
import axios from 'axios';
import { Head } from '@inertiajs/vue3';
import AdminModal from '@career/AdminModal.vue';
import ConfirmModal from '@career/ConfirmModal.vue';
import RefSelect from '@career/RefSelect.vue';
import { ingatModal } from '@utils/ingatModal';

const API = '/api/v1/master-akun';
const CFG = { headers: { Accept: 'application/json' } };

export default {
    components: { Head, AdminModal, ConfirmModal, RefSelect },
    // Modal di halaman ini selamat dari refresh — lihat @utils/ingatModal.
    mixins: [ingatModal('admin/master-akun/masterAkun')],
    data() {
        return {
            all: [],
            loading: false,
            tab: 'pengguna',
            q: '',
            show: false,
            editingId: null,
            saving: false,
            form: { nama: '', email: '', phone: '', password: '', role: 'KANDIDAT', klasifikasi: '', status: 'AKTIF', kodeKaryawan: null },
            // Opsi karyawan dimuat SAAT DIKETIK, bukan sekaligus: daftarnya berisi
            // 958 orang, dan mengirim semuanya demi satu pilihan membuat modal
            // ini terasa berat tanpa alasan yang terlihat.
            penerimaOptions: [],
            penerimaLoading: false,

            // ── SERAH TERIMA ──
            serahShow: false,
            serahTarget: null,
            serahMuat: false,
            serahSibuk: false,
            serahLoker: [],
            serahPilih: [],
            serahKe: null,
            serahAlasan: '',
            delShow: false,
            delTarget: null,
            deleting: false,
            kirimId: null,
            verifGagal: null,
            toast: '',
            tm: null,
        };
    },
    computed: {
        semuaTerpilih() {
            return this.serahLoker.length > 0 && this.serahPilih.length === this.serahLoker.length;
        },
        kandidatTerpilih() {
            return this.serahLoker
                .filter((l) => this.serahPilih.includes(l.id))
                .reduce((n, l) => n + (l.kandidat || 0), 0);
        },
        /** Alasan minimal 5 huruf — sama dengan gerbang di server. */
        bolehSerah() {
            return !this.serahSibuk
                && this.serahPilih.length > 0
                && !!this.serahKe
                && (this.serahAlasan || '').trim().length >= 5;
        },
        penggunaList() { return this.all.filter((a) => a.role === 'KANDIDAT'); },
        adminList() { return this.all.filter((a) => a.role === 'ADMIN' || a.role === 'SUPERADMIN'); },
        activeList() { return this.tab === 'pengguna' ? this.penggunaList : this.adminList; },
        filtered() {
            const s = this.q.trim().toLowerCase();
            if (!s) return this.activeList;
            return this.activeList.filter((a) => (a.nama + ' ' + a.email).toLowerCase().includes(s));
        },
        countAktif() { return this.activeList.filter((a) => a.status === 'AKTIF').length; },
        roleOptions() {
            return this.tab === 'pengguna'
                ? [{ value: 'KANDIDAT', label: 'Pengguna (Pelamar)' }]
                : [{ value: 'ADMIN', label: 'Admin' }, { value: 'SUPERADMIN', label: 'Superadmin' }];
        },
    },
    mounted() {
        this.load();
    },
    methods: {
        async load() {
            this.loading = true;
            try {
                const res = await axios.get(API, CFG);
                this.all = res.data.result || [];
            } catch (e) {
                this.notice('Gagal memuat data akun.');
            } finally {
                this.loading = false;
            }
        },
        switchTab(t) { this.tab = t; this.q = ''; },
        inisial(nama) { return (nama || '?').trim().split(/\s+/).slice(0, 2).map((w) => w[0]).join('').toUpperCase(); },
        avatarClass(role) { return { KANDIDAT: 'ava-sky', ADMIN: 'ava-indigo', SUPERADMIN: 'ava-amber' }[role] || 'ava-slate'; },
        roleBadge(role) { return { KANDIDAT: 'wca-b--sky', ADMIN: 'wca-b--indigo', SUPERADMIN: 'wca-b--amber' }[role] || 'wca-b--slate'; },
        roleLabel(role) { return { KANDIDAT: 'Pengguna', ADMIN: 'Admin', SUPERADMIN: 'Superadmin' }[role] || role; },
        fmtDate(d) {
            if (!d) return '—';
            const dt = new Date(String(d).replace(' ', 'T'));
            return isNaN(dt.getTime()) ? d : dt.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
        },
        fmtDateTime(d) {
            if (!d) return '—';
            const dt = new Date(String(d).replace(' ', 'T'));
            return isNaN(dt.getTime()) ? d : dt.toLocaleString('id-ID', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
        },
        isExpired(d) {
            const dt = new Date(String(d).replace(' ', 'T'));
            return !isNaN(dt.getTime()) && dt < new Date(new Date().toDateString());
        },
        openCreate() {
            this.editingId = null;
            this.form = { nama: '', email: '', phone: '', password: '', role: this.tab === 'pengguna' ? 'KANDIDAT' : 'ADMIN', klasifikasi: '', status: 'AKTIF', kodeKaryawan: '' };
            this.show = true;
        },
        openEdit(a) {
            this.editingId = a.id;
            this.form = { nama: a.nama, email: a.email, phone: a.phone || '', password: '', role: a.role, klasifikasi: a.klasifikasi || '', status: a.status, kodeKaryawan: a.kodeKaryawan || '' };
            this.show = true;
        },
        /**
         * Cari karyawan aktif — dipanggil el-select tiap admin mengetik.
         *
         * Dibatasi 2 huruf oleh server maupun di sini: satu huruf memanggil
         * hampir seluruh 958 baris, dan daftar sepanjang itu bukan pilihan,
         * melainkan gulungan.
         */
        /** Buka panel: siapa dia, dan sedang memegang apa. */
        async bukaSerah(a) {
            this.serahTarget = a;
            this.serahShow = true;
            this.serahMuat = true;
            this.serahLoker = [];
            this.serahPilih = [];
            this.serahKe = null;
            this.serahAlasan = '';
            this.penerimaOptions = [];
            this.cariPenerima('');
            try {
                const res = await axios.get(`${API}/${a.id}/pekerjaan`, CFG);
                this.serahLoker = res.data.result?.loker || [];
            } catch (e) {
                this.notice(e.response?.data?.message || 'Gagal memuat daftar loker.');
                this.serahShow = false;
            } finally {
                this.serahMuat = false;
            }
        },
        pilihSemua(aktif) {
            this.serahPilih = aktif ? this.serahLoker.map((l) => l.id) : [];
        },
        async simpanSerah() {
            if (!this.bolehSerah || this.serahSibuk) return;
            this.serahSibuk = true;
            try {
                const res = await axios.post(`${API}/serah-terima`, {
                    posisiIds: this.serahPilih,
                    keKode: this.serahKe,
                    alasan: this.serahAlasan.trim(),
                }, CFG);
                this.serahShow = false;
                this.notice(res.data?.message || 'Serah terima selesai.');
            } catch (e) {
                this.notice(e.response?.data?.message || 'Gagal melakukan serah terima.');
            } finally {
                this.serahSibuk = false;
            }
        },
        /**
         * Calon penerima serah terima — akun internal yang sudah punya kode.
         *
         * Bukan dari tabel Karyawan: penerimanya harus BISA MASUK dan
         * mengerjakan lokernya. Karyawan tanpa akun di sini bukan calon yang
         * sah, seberapa pun benar kodenya.
         */
        async cariPenerima(q) {
            const kata = String(q || '').trim();
            this.penerimaLoading = true;
            try {
                const res = await axios.get('/api/v1/master-akun/opsi/penerima', { ...CFG, params: { q: kata || undefined } });
                this.penerimaOptions = res.data.result || [];
            } catch (e) {
                // Didiamkan dengan sengaja: pencarian yang gagal cukup terlihat
                // sebagai daftar kosong. Toast di sini akan muncul berkali-kali
                // saat orang mengetik cepat di jaringan yang buruk, menutupi
                // borang yang sedang ia isi.
                this.penerimaOptions = [];
            } finally {
                this.penerimaLoading = false;
            }
        },
        async save() {
            if (this.saving) return;
            const f = this.form;
            if (!f.nama.trim() || !f.email.trim()) return this.notice('Nama & email wajib diisi.');
            if (!this.editingId && (!f.password || f.password.length < 6)) return this.notice('Kata sandi minimal 6 karakter.');
            if (f.password && f.password.length < 6) return this.notice('Kata sandi minimal 6 karakter.');
            this.saving = true;
            const payload = {
                nama: f.nama, email: f.email, phone: f.phone, password: f.password || '',
                role: f.role, klasifikasi: f.klasifikasi, status: f.status,
                // Kandidat tidak pernah membawa kode karyawan, walau nilainya
                // sempat terisi lalu perannya diubah menjadi KANDIDAT: kotaknya
                // hilang dari layar tapi isinya masih menempel di form.
                kodeKaryawan: f.role === 'KANDIDAT' ? null : (f.kodeKaryawan || null),
            };
            try {
                if (this.editingId) {
                    await axios.put(`${API}/${this.editingId}`, payload, CFG);
                    this.notice('Akun diperbarui.');
                } else {
                    await axios.post(API, payload, CFG);
                    this.notice('Akun berhasil dibuat.');
                }
                this.show = false;
                await this.load();
            } catch (e) {
                this.notice(e.response?.data?.message || 'Gagal menyimpan akun.');
            } finally {
                this.saving = false;
            }
        },
        async setStatus(a, v) {
            const prev = a.status;
            a.status = v ? 'AKTIF' : 'NONAKTIF';
            try {
                await axios.patch(`${API}/${a.id}/toggle`, { aktif: v }, CFG);
                this.notice(`Akun ${v ? 'diaktifkan' : 'dinonaktifkan'}.`);
            } catch (e) {
                a.status = prev;
                this.notice(e.response?.data?.message || 'Gagal mengubah status.');
            }
        },
        /**
         * Riwayat verifikasi dalam satu kalimat.
         *
         * `verifSentAt` HANYA terisi setelah SMTP benar-benar menerima emailnya
         * (WcSyncEmailJob). Jadi percobaan yang tercatat tanpa waktu kirim
         * berarti tak satu pun pernah keluar — dan itu masalah server, bukan
         * kandidat yang lupa membuka kotak masuknya. Membedakan keduanya di
         * sini menghemat penyelidikan yang selama ini harus lewat log.
         */
        catatanVerif(a) {
            if (a.verifSentAt) {
                const n = a.verifAttempt > 1 ? ` · ${a.verifAttempt}x dikirim` : '';
                return `Terkirim ${this.fmtDateTime(a.verifSentAt)}${n} — menunggu kandidat membuka tautannya.`;
            }
            if (a.verifAttempt > 0) {
                return `${a.verifAttempt}x dicoba, tidak pernah berhasil terkirim. Periksa pengaturan email server.`;
            }
            return 'Belum pernah dikirimi tautan verifikasi.';
        },
        async kirimVerifikasi(a) {
            if (this.kirimId) return;
            this.kirimId = a.id;
            this.verifGagal = null;
            try {
                const res = await axios.patch(`${API}/${a.id}/kirim-verifikasi`, {}, CFG);
                this.notice(res.data.message || 'Tautan verifikasi terkirim.');
                await this.load();
            } catch (e) {
                // Pesan server diteruskan apa adanya — di situlah sebabnya.
                this.verifGagal = { email: a.email, pesan: e.response?.data?.message || 'Tidak ada balasan dari server.' };
            } finally {
                this.kirimId = null;
            }
        },
        askRemove(a) { this.delTarget = a; this.delShow = true; },
        async confirmDelete() {
            if (this.deleting || !this.delTarget) return;
            const a = this.delTarget;
            this.deleting = true;
            try {
                await axios.delete(`${API}/${a.id}`, CFG);
                this.notice('Akun dihapus.');
                this.delShow = false;
                this.delTarget = null;
                await this.load();
            } catch (e) {
                this.notice(e.response?.data?.message || 'Gagal menghapus akun.');
            } finally {
                this.deleting = false;
            }
        },
        notice(m) { this.toast = m; if (this.tm) clearTimeout(this.tm); this.tm = setTimeout(() => (this.toast = ''), 3000); },
    },
};
</script>

<style scoped>
.wca-segtab { display: inline-flex; gap: 4px; padding: 4px; margin-bottom: 1rem; background: #eef1f8; border: 1px solid rgba(11, 16, 51, .08); border-radius: 14px; }
.wca-segtab__btn { display: inline-flex; align-items: center; gap: 8px; padding: 9px 16px; border: 0; background: transparent; color: #5b5f86; font: 600 13.5px 'Plus Jakarta Sans', system-ui, sans-serif; border-radius: 10px; cursor: pointer; }
.wca-segtab__btn.on { background: #fff; color: #4f46e5; box-shadow: 0 2px 8px rgba(11, 16, 51, .08); }
.wca-segtab__count { min-width: 20px; padding: 1px 7px; border-radius: 999px; background: rgba(79, 70, 229, .12); color: #4338ca; font-size: 11px; font-weight: 700; }
.wca-segtab__btn:not(.on) .wca-segtab__count { background: rgba(11, 16, 51, .07); color: #64748b; }
.akun-idn { display: flex; align-items: center; gap: 10px; }
.akun-idn small { display: block; color: #7c81a3; font-size: 12px; }
.akun-ava { flex: 0 0 auto; width: 38px; height: 38px; display: grid; place-items: center; border-radius: 11px; color: #fff; font: 700 13px 'Plus Jakarta Sans', system-ui, sans-serif; }
.ava-sky { background: linear-gradient(135deg, #0ea5e9, #0284c7); }
.ava-indigo { background: linear-gradient(135deg, #6366f1, #4f46e5); }
.ava-amber { background: linear-gradient(135deg, #f59e0b, #d97706); }
.ava-slate { background: linear-gradient(135deg, #94a3b8, #64748b); }
.akun-muted { color: #7c81a3; font-size: 12px; }
.akun-exp { color: #dc2626; font-weight: 600; }
.akun-hint { color: #9096b8; font-weight: 500; font-size: 11.5px; }
/* Keterangan di BAWAH bidang, bukan di sampingnya — kalimatnya dua baris dan
   menempel di label akan mendorong kotaknya turun tak beraturan. */
.akun-hint--blok { display: block; margin-top: .35rem; line-height: 1.6; }
.akun-hint--blok b { color: #64748b; font-weight: 700; }
.akun-selempty { padding: .6rem .8rem; font-size: 12px; color: #94a3b8; }
.akun-ibtn--serah { color: #b45309; }
.akun-ibtn--serah:hover { background: #fffbeb; border-color: #fde68a; }
.akun-serah-bar { display: flex; align-items: center; justify-content: space-between; gap: .75rem; flex-wrap: wrap; margin-bottom: .6rem; }
.akun-chk { display: inline-flex; align-items: center; gap: .4rem; font-size: 12.5px; font-weight: 700; color: #334155; cursor: pointer; }
.akun-serah-list { display: flex; flex-direction: column; gap: .4rem; max-height: 22rem; overflow-y: auto; }
.akun-serah-item { display: flex; align-items: center; gap: .7rem; padding: .6rem .75rem; border: 1px solid #e2e8f0; border-radius: .7rem; background: #fff; cursor: pointer; transition: all .15s ease; }
.akun-serah-item:hover { border-color: #c7d2fe; }
.akun-serah-item.on { border-color: #6366f1; background: #eef2ff; }
.akun-serah-item__body { flex: 1; min-width: 0; display: flex; flex-direction: column; }
.akun-serah-item__body strong { font-size: 13px; color: #1e293b; }
.akun-serah-item__body small { font-size: 11.5px; color: #64748b; }
.akun-serah-item__meta { flex: none; display: flex; align-items: center; gap: .6rem; font-size: 11.5px; color: #64748b; }
.akun-serah-item__meta .is-penuh { color: #b45309; font-weight: 800; }
.akun-status { display: flex; align-items: center; gap: 10px; --el-switch-on-color: #059669; }
.akun-status__lbl { font-size: 12px; font-weight: 700; }
.akun-status__lbl.is-on { color: #059669; }
.akun-status__lbl.is-off { color: #94a3b8; }

/* ── Verifikasi email ─────────────────────────────────────────────────────── */
.akun-verif { display: flex; flex-direction: column; align-items: flex-start; gap: 5px; min-width: 210px; }
.akun-verif__note { color: #7c81a3; font-size: 11.5px; line-height: 1.45; max-width: 260px; }
.akun-verif__btn {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 4px 10px; border: 1px solid #c7d2fe; border-radius: 8px;
    background: #eef2ff; color: #4338ca;
    font: 700 11.5px 'Plus Jakarta Sans', system-ui, sans-serif; cursor: pointer;
}
.akun-verif__btn:hover:not(:disabled) { background: #e0e7ff; border-color: #a5b4fc; }
.akun-verif__btn:disabled { opacity: .6; cursor: progress; }
.akun-spin { display: inline-block; animation: akun-rot 0.9s linear infinite; }
@keyframes akun-rot { to { transform: rotate(360deg); } }

/* Merah galat — di sini memang ada yang rusak, berbeda dari "menunggu". */
.akun-alert {
    display: flex; gap: 11px; align-items: flex-start; margin-bottom: 1rem;
    padding: 12px 14px; border-radius: 12px;
    background: #fef2f2; border: 1px solid #fecaca; color: #991b1b;
}
.akun-alert > .bi { flex: none; margin-top: 2px; font-size: 15px; color: #dc2626; }
.akun-alert > div { flex: 1; min-width: 0; font-size: 12.5px; line-height: 1.55; }
.akun-alert p {
    margin: 3px 0; padding: 7px 9px; border-radius: 8px;
    background: rgba(255, 255, 255, .7); color: #7f1d1d;
    font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 11.5px;
    /* Pesan SMTP bisa sangat panjang dan tanpa spasi — dipatahkan supaya tetap
       terbaca utuh, bukan memaksa seluruh halaman menggeser ke samping. */
    overflow-wrap: anywhere; white-space: pre-wrap;
}
.akun-alert span { display: block; color: #b91c1c; font-size: 11.5px; }
.akun-alert__x { flex: none; border: 0; background: transparent; color: #b91c1c; cursor: pointer; font-size: 13px; padding: 2px; }
@media (max-width: 640px) {
    .wca-segtab { width: 100%; }
    .wca-segtab__btn { flex: 1; justify-content: center; padding: 9px 10px; font-size: 12.5px; }
    .akun-status__lbl { display: none; }
}
</style>
