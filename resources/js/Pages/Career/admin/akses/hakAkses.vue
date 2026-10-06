<!-- WEB CAREERS — Manajemen Hak Akses (WordPress style: accordion pengguna → halaman → centang aksi & kategori). -->
<template>
    <Head title="Manajemen Hak Akses" />
    <div class="wca ha-page">
        <!-- ░░ STATISTIK ░░ -->
        <div class="ha-stats">
            <div class="ha-stats__hdr">
                <div>
                    <div class="ha-stats__title">Manajemen Hak Akses</div>
                    <div class="ha-stats__sub">Atur halaman, aksi, dan kategori program tiap pengguna</div>
                </div>
                <div v-if="!loadingSum" class="ha-live"><span class="ha-live__dot"></span> Live</div>
            </div>
            <div class="ha-stats__bar">
                <div class="ha-stat ha-stat--indigo">
                    <div class="ha-stat__ico"><i class="bi bi-people-fill"></i></div>
                    <div><div class="ha-stat__num">{{ loadingSum ? '…' : sum.total_user }}</div><div class="ha-stat__lbl">Pengguna Terkonfigurasi</div></div>
                </div>
                <div class="ha-stat ha-stat--violet">
                    <div class="ha-stat__ico"><i class="bi bi-shield-check"></i></div>
                    <div><div class="ha-stat__num">{{ loadingSum ? '…' : sum.total_config }}</div><div class="ha-stat__lbl">Halaman Diberikan</div></div>
                </div>
                <div class="ha-stat ha-stat--green">
                    <div class="ha-stat__ico"><i class="bi bi-check2-all"></i></div>
                    <div><div class="ha-stat__num">{{ loadingSum ? '…' : sum.total_aksi }}</div><div class="ha-stat__lbl">Aksi Aktif</div></div>
                </div>
                <div class="ha-stat ha-stat--amber">
                    <div class="ha-stat__ico"><i class="bi bi-tags-fill"></i></div>
                    <div><div class="ha-stat__num">{{ loadingSum ? '…' : sum.total_konten }}</div><div class="ha-stat__lbl">Akses Kategori</div></div>
                </div>
                <div class="ha-stats__act">
                    <button class="ha-btn-ghost" @click="openDup"><i class="bi bi-files"></i> Duplikat</button>
                    <button class="pkg-newbtn" @click="openTambah"><i class="bi bi-plus-lg"></i> Beri Akses</button>
                </div>
            </div>
        </div>

        <!-- ░░ FILTER PANEL ░░ -->
        <div v-if="sheetOpen" class="ha-sheetbg" @click="sheetOpen = false"></div>
        <div class="ha-filter" :class="{ 'is-open': sheetOpen }">
            <div class="ha-filter__head">
                <span class="ha-filter__title"><i class="bi bi-funnel"></i> Filter Panel</span>
                <div class="ha-filter__act">
                    <button v-if="adaFilter" class="ha-filter__reset" type="button" @click="resetFilter"><i class="bi bi-arrow-counterclockwise"></i> Reset</button>
                    <button class="ha-filter__close" type="button" aria-label="Tutup" @click="sheetOpen = false"><i class="bi bi-x-lg"></i></button>
                </div>
            </div>
            <div class="ha-filter__grid">
                <div>
                    <label class="wca-field-lbl">Cari Pengguna</label>
                    <el-input v-model="filters.q" placeholder="Nama atau email" clearable @input="cariDebounce">
                        <template #prefix><i class="bi bi-search"></i></template>
                    </el-input>
                </div>
                <div>
                    <label class="wca-field-lbl">Peran</label>
                    <el-select v-model="filters.role" placeholder="Semua peran" clearable style="width:100%" @change="load(1)">
                        <el-option label="Superadmin" value="SUPERADMIN" />
                        <el-option label="Admin" value="ADMIN" />
                        <el-option label="Kandidat" value="KANDIDAT" />
                    </el-select>
                </div>
                <div>
                    <label class="wca-field-lbl">Per Halaman</label>
                    <el-select v-model="filters.limit" style="width:100%" @change="load(1)">
                        <el-option :value="10" label="10 / halaman" />
                        <el-option :value="20" label="20 / halaman" />
                        <el-option :value="50" label="50 / halaman" />
                    </el-select>
                </div>
                <div class="ha-filter__count"><strong>{{ pag.total }}</strong> pengguna</div>
            </div>
        </div>
        <button class="ha-fab" type="button" aria-label="Filter" @click="sheetOpen = true">
            <i class="bi bi-funnel-fill"></i>
            <span v-if="jumlahFilter" class="ha-fab__badge">{{ jumlahFilter }}</span>
        </button>

        <!-- ░░ ACCORDION PENGGUNA ░░ -->
        <div v-loading="loading" class="ha-list">
            <div v-for="u in list" :key="u.id" class="ha-user" :class="{ open: buka === u.id }">
                <div class="ha-user__hdr" @click="buka = buka === u.id ? null : u.id">
                    <span class="ha-avatar" :style="{ background: warnaAvatar(u.nama) }">{{ inisial(u.nama) }}</span>
                    <div class="ha-user__info">
                        <div class="ha-user__nama">{{ u.nama }}</div>
                        <div class="ha-user__meta">
                            <span><i class="bi bi-envelope"></i> {{ u.email }}</span>
                            <span><i class="bi bi-file-earmark-text"></i> {{ u.jumlahHalaman }} halaman</span>
                        </div>
                    </div>
                    <span class="wca-badge" :class="badgeRole(u.role)">{{ u.role }}</span>
                    <button class="ha-susun" type="button" title="Susun urutan & grup menu sidebar akun ini" @click.stop="susunMenu(u)">
                        <i class="bi bi-list-nested"></i> <span>Susun Menu</span>
                    </button>
                    <i class="bi bi-chevron-down ha-user__chev" :class="{ open: buka === u.id }"></i>
                </div>

                <div v-if="buka === u.id" class="ha-user__body">
                    <div v-if="!u.halaman.length" class="ha-kosong"><i class="bi bi-shield-x"></i> Belum ada halaman untuk pengguna ini.</div>

                    <div v-for="h in u.halaman" :key="h.idPageAccess" class="ha-page-row">
                        <div class="ha-page-row__hdr">
                            <span class="ha-page-row__ico"><i class="bi" :class="ikonKelas(h.ikon)"></i></span>
                            <div class="ha-page-row__id">
                                <strong>{{ h.namaMenu }}</strong>
                                <small>{{ h.header || '—' }} · <code>{{ h.jenisPage }}</code></small>
                            </div>
                            <button class="ha-mini" title="Centang semua aksi" @click="bulk(u, h, 'AKSI', true)"><i class="bi bi-check2-all"></i></button>
                            <button class="ha-mini" title="Lepas semua aksi" @click="bulk(u, h, 'AKSI', false)"><i class="bi bi-x-square"></i></button>
                            <button class="ha-mini ha-mini--danger" title="Cabut halaman ini" @click="askCabut(u, h)"><i class="bi bi-trash"></i></button>
                        </div>

                        <!-- AKSI -->
                        <div class="ha-chk-block">
                            <div class="ha-chk-lbl"><i class="bi bi-lightning-charge-fill"></i> Aksi</div>
                            <div class="ha-chks">
                                <label v-for="a in ref.aksi" :key="a.id" class="ha-chk" :class="{ on: h.aksi.includes(a.id) }">
                                    <input type="checkbox" :checked="h.aksi.includes(a.id)" @change="(e) => toggleAksi(u, h, a, e.target.checked)" />
                                    <span>{{ a.nama }}</span>
                                </label>
                            </div>
                        </div>

                        <!-- KONTEN / KATEGORI -->
                        <div class="ha-chk-block">
                            <div class="ha-chk-lbl">
                                <i class="bi bi-tags-fill"></i> Kategori Program
                                <button class="ha-mini ha-mini--inline" title="Centang semua kategori" @click="bulk(u, h, 'KONTEN', true)"><i class="bi bi-check2-all"></i></button>
                            </div>
                            <div class="ha-chks">
                                <label v-for="k in ref.kategori" :key="k.kode" class="ha-chk ha-chk--kat" :class="{ on: h.kategori.includes(k.kode) }">
                                    <input type="checkbox" :checked="h.kategori.includes(k.kode)" @change="(e) => toggleKonten(u, h, k, e.target.checked)" />
                                    <span>{{ k.nama }}</span>
                                </label>
                            </div>
                            <p class="ha-chk-note">Kosong = tidak dibatasi kategori pada halaman ini.</p>
                        </div>

                        <!-- ══ LINGKUP PENANGGUNG JAWAB MPP ═══════════════════
                             Hanya untuk halaman yang memang memakai MPP. Menaruh
                             pilihan ini di setiap halaman berarti menawarkan
                             pengaturan yang tidak berpengaruh pada 40-an halaman
                             lain — dan orang akan mengubahnya lalu bertanya kenapa
                             tidak terjadi apa-apa.

                             PILIHAN TUNGGAL, bukan centang: ketiganya saling
                             meniadakan, dan centang akan mengundang orang
                             menyalakan dua sekaligus. -->
                        <div v-if="HALAMAN_PIC.includes(h.jenisPage)" class="ha-chk-block">
                            <div class="ha-chk-lbl">
                                <i class="bi bi-person-badge-fill"></i> Lingkup Penanggung Jawab MPP
                            </div>
                            <div class="ha-chks">
                                <label
                                    v-for="l in LINGKUP"
                                    :key="l.value"
                                    class="ha-chk ha-chk--lingkup"
                                    :class="{ on: (h.lingkupPic || 'SEMUA') === l.value }"
                                    :title="l.desc"
                                >
                                    <input
                                        type="radio"
                                        :name="`lingkup-${h.idPageAccess}`"
                                        :value="l.value"
                                        :checked="(h.lingkupPic || 'SEMUA') === l.value"
                                        @change="ubahLingkup(u, h, l.value)"
                                    />
                                    <span><i class="bi" :class="l.ikon"></i> {{ l.label }}</span>
                                </label>
                            </div>
                            <p class="ha-chk-note">
                                {{ (LINGKUP.find((x) => x.value === (h.lingkupPic || 'SEMUA')) || {}).desc }}
                                <b v-if="(h.lingkupPic || 'SEMUA') !== 'SEMUA' && !u.kodeKaryawan">
                                    — akun ini belum ditautkan ke karyawan, jadi ia akan melihat nol MPP sampai
                                    Kode Karyawan diisi di Master Akun.
                                </b>
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <div v-if="!loading && !list.length" class="wca-empty">
                <i class="bi bi-person-lock"></i>
                <h4>{{ adaFilter ? 'Tidak ada pengguna yang cocok' : 'Belum ada konfigurasi hak akses' }}</h4>
            </div>

            <div v-if="pag.totalPage > 1" class="ha-pager">
                <button :disabled="pag.page <= 1" :onClick="pag.page <= 1 ? null : () => load(pag.page - 1)"><i class="bi bi-chevron-left"></i></button>
                <span>Halaman {{ pag.page }} / {{ pag.totalPage }}</span>
                <button :disabled="pag.page >= pag.totalPage" @click="load(pag.page + 1)"><i class="bi bi-chevron-right"></i></button>
            </div>
        </div>

        <!-- MODAL: beri akses halaman -->
        <AdminModal :busy="sibuk" :show="showTambah" title="Beri Akses Halaman" subtitle="Pilih pengguna lalu centang halaman yang boleh dibuka" icon="bi-person-plus" lg save-label="Simpan Akses" @close="showTambah = false" @save="simpanTambah">
            <div class="wca-fsection">
                <div class="wca-fsection__label"><i class="bi bi-person"></i> Pengguna</div>
                <el-select v-model="formTambah.userId" filterable placeholder="Cari nama / email" style="width:100%">
                    <el-option v-for="p in ref.pengguna" :key="p.id" :value="p.id" :label="`${p.nama} — ${p.email} (${p.role})`" />
                </el-select>
            </div>
            <div class="wca-fsection">
                <div class="wca-fsection__label" style="display:flex;align-items:center;justify-content:space-between">
                    <span><i class="bi bi-list-check"></i> Halaman ({{ formTambah.pages.length }} dipilih)</span>
                    <button class="wca-btn wca-btn--soft wca-btn--sm" type="button" @click="toggleSemuaHalaman">
                        <i class="bi bi-check2-square"></i> {{ formTambah.pages.length === ref.menu.length ? 'Kosongkan' : 'Pilih semua' }}
                    </button>
                </div>
                <div class="ha-pick">
                    <label v-for="m in ref.menu" :key="m.jenisPage" class="ha-pick__item" :class="{ on: formTambah.pages.includes(m.jenisPage) }">
                        <input type="checkbox" :value="m.jenisPage" v-model="formTambah.pages" />
                        <i class="bi" :class="ikonKelas(m.ikon)"></i>
                        <span class="ha-pick__nama">{{ m.nama }}</span>
                        <span class="ha-pick__role" :class="m.role === 'KANDIDAT' ? 'is-kand' : ''">{{ m.role === 'KANDIDAT' ? 'kandidat' : 'admin' }}</span>
                    </label>
                </div>
                <p class="ha-chk-note">Aksi awal yang diberikan: <b>VIEW</b>. Sesuaikan sisanya lewat centang setelah tersimpan.</p>
            </div>
        </AdminModal>

        <!-- MODAL: duplikat akses -->
        <AdminModal :busy="sibuk" :show="showDup" title="Duplikat Hak Akses" subtitle="Salin seluruh hak akses dari satu pengguna ke pengguna lain" icon="bi-files" save-label="Duplikat" @close="showDup = false" @save="simpanDup">
            <div class="wca-fsection">
                <div class="wca-form">
                    <div>
                        <label class="wca-field-lbl">Salin Dari</label>
                        <el-select v-model="formDup.dariUserId" filterable placeholder="Pengguna sumber" style="width:100%">
                            <el-option v-for="p in ref.pengguna" :key="p.id" :value="p.id" :label="`${p.nama} — ${p.email}`" />
                        </el-select>
                    </div>
                    <div>
                        <label class="wca-field-lbl">Salin Ke</label>
                        <el-select v-model="formDup.keUserId" filterable placeholder="Pengguna tujuan" style="width:100%">
                            <el-option v-for="p in ref.pengguna" :key="p.id" :value="p.id" :label="`${p.nama} — ${p.email}`" />
                        </el-select>
                    </div>
                    <label class="ha-dup-check"><el-checkbox v-model="formDup.timpa">Hapus dulu hak akses tujuan (timpa penuh)</el-checkbox></label>
                </div>
            </div>
        </AdminModal>

        <ConfirmModal :show="cabutShow" title="Cabut Akses Halaman" :busy="sibuk" confirm-label="Ya, Cabut" note="Aksi & kategori pada halaman ini ikut terhapus." @cancel="cabutShow = false" @confirm="confirmCabut">
            Cabut akses <strong>{{ cabutTarget?.h?.namaMenu }}</strong> dari <strong>{{ cabutTarget?.u?.nama }}</strong>?
        </ConfirmModal>

        <transition name="wca-toast"><div v-if="toast" class="wca-toast"><i class="bi bi-check-circle-fill"></i> {{ toast }}</div></transition>
    </div>
</template>

<script>
import axios from 'axios';
import { Head, router } from '@inertiajs/vue3';
import AdminModal from '@career/AdminModal.vue';
import ConfirmModal from '@career/ConfirmModal.vue';
import { ingatModal } from '@utils/ingatModal';

const API = '/api/v1/hak-akses';
const CFG = { headers: { Accept: 'application/json' } };

export default {
    components: { Head, AdminModal, ConfirmModal },
    // Modal di halaman ini selamat dari refresh — lihat @utils/ingatModal.
    mixins: [ingatModal('admin/akses/hakAkses')],
    data() {
        return {
            list: [], loading: false, loadingSum: true,
            sum: { total_user: 0, total_config: 0, total_aksi: 0, total_konten: 0 },
            ref: { aksi: [], menu: [], kategori: [], pengguna: [] },
            // Ditulis sebagai data supaya keterangannya hidup bersama pilihannya.
            // Tiga kalimat ini adalah satu-satunya tempat arti tiap lingkup
            // dijelaskan kepada orang yang memilihnya.
            // Halaman yang benar-benar memakai lingkup PIC. Ditulis sebagai
            // daftar, bukan disebar jadi beberapa perbandingan string: menambah
            // halaman ber-PIC nanti cukup satu baris di sini, dan tidak ada
            // salinan yang bisa ketinggalan.
            //
            //   programPage      memilih MPP saat membuka lowongan
            //   pelamarPage      mengerjakan kandidat lokernya
            //   penjadwalanPage  menjadwalkan peserta lokernya
            //
            // hasilTesPage (Monitoring) SENGAJA TIDAK di sini: halaman itu belum
            // digerbangi PIC di server. Menawarkan pilihannya berarti memasang
            // kendali yang tidak berbuat apa-apa — dan pengaturan yang tampak
            // aktif tapi tidak menegakkan apa pun lebih berbahaya daripada tidak
            // ada sama sekali, sebab orang berhenti memeriksanya.
            HALAMAN_PIC: ['programPage', 'pelamarPage', 'penjadwalanPage'],
            LINGKUP: [
                { value: 'SENDIRI', label: 'Sendiri', ikon: 'bi-person-fill', desc: 'Hanya MPP yang penanggung jawabnya dirinya sendiri.' },
                { value: 'TIM', label: 'Tim', ikon: 'bi-people-fill', desc: 'MPP milik siapa pun yang satu divisi/sub-divisi dengannya — untuk saling menutup.' },
                { value: 'SEMUA', label: 'Semua', ikon: 'bi-globe2', desc: 'Seluruh MPP, siapa pun penanggung jawabnya.' },
            ],
            filters: { q: '', role: null, limit: 10 },
            pag: { page: 1, limit: 10, total: 0, totalPage: 1 },
            buka: null, sheetOpen: false, cariTimer: null,
            showTambah: false, formTambah: { userId: null, pages: [] },
            showDup: false, formDup: { dariUserId: null, keUserId: null, timpa: false },
            cabutShow: false, cabutTarget: null,
            sibuk: false, toast: '', tm: null,
        };
    },
    computed: {
        adaFilter() { return !!(this.filters.q || this.filters.role); },
        jumlahFilter() { return [this.filters.q, this.filters.role].filter(Boolean).length; },
    },
    mounted() { this.load(1); this.loadSummary(); this.loadRef(); },
    methods: {
        ikonKelas(i) { return (i || 'bi-dot').replace(/^bi\s+/, ''); },
        inisial(n) { const p = String(n || '').trim().split(/\s+/); return ((p[0]?.[0] || '') + (p[1]?.[0] || '')).toUpperCase() || '?'; },
        warnaAvatar(n) {
            const w = ['#6366f1', '#8b5cf6', '#0ea5e9', '#059669', '#d97706', '#dc2626', '#0f766e'];
            let s = 0; for (const c of String(n || '')) s += c.charCodeAt(0);
            return w[s % w.length];
        },
        badgeRole(r) { return r === 'KANDIDAT' ? 'wca-b--amber' : (r === 'SUPERADMIN' ? 'wca-b--indigo' : 'wca-b--slate'); },

        /** Buka penyusun menu (panel kiri–kanan + drag & drop) untuk akun ini. */
        susunMenu(u) { router.visit(`/hak-akses/susun/${u.id}`); },

        async loadSummary() {
            this.loadingSum = true;
            try { this.sum = (await axios.get(`${API}/summary`, CFG)).data.result || this.sum; }
            catch (e) { /* diamkan — statistik bukan penghalang */ }
            finally { this.loadingSum = false; }
        },
        async loadRef() {
            try { this.ref = (await axios.get(`${API}/referensi`, CFG)).data.result || this.ref; }
            catch (e) { this.notice('Gagal memuat referensi.', true); }
        },
        async load(page = 1) {
            this.loading = true;
            try {
                const params = { q: this.filters.q || undefined, role: this.filters.role || undefined, page, limit: this.filters.limit };
                const r = (await axios.get(API, { ...CFG, params })).data.result || {};
                this.list = r.data || [];
                this.pag = { page: r.page || 1, limit: r.limit || 10, total: r.total || 0, totalPage: r.totalPage || 1 };
            } catch (e) { this.notice('Gagal memuat data hak akses.', true); }
            finally { this.loading = false; }
        },
        cariDebounce() { if (this.cariTimer) clearTimeout(this.cariTimer); this.cariTimer = setTimeout(() => this.load(1), 400); },
        resetFilter() { this.filters = { q: '', role: null, limit: 10 }; this.load(1); },

        /* ── Centang aksi / kategori (optimistis, rollback bila gagal) ── */
        async toggleAksi(u, h, a, aktif) {
            const sebelum = [...h.aksi];
            h.aksi = aktif ? [...h.aksi, a.id] : h.aksi.filter((x) => x !== a.id);
            try {
                await axios.post(`${API}/toggle-aksi`, { idPageAccess: h.idPageAccess, idAksi: a.id, aktif }, CFG);
                this.loadSummary();
            } catch (e) { h.aksi = sebelum; this.notice(e.response?.data?.message || 'Gagal memperbarui aksi.', true); }
        },
        async toggleKonten(u, h, k, aktif) {
            const sebelum = [...h.kategori];
            h.kategori = aktif ? [...h.kategori, k.kode] : h.kategori.filter((x) => x !== k.kode);
            try {
                await axios.post(`${API}/toggle-konten`, { idPageAccess: h.idPageAccess, kategori: k.kode, aktif }, CFG);
                this.loadSummary();
            } catch (e) { h.kategori = sebelum; this.notice(e.response?.data?.message || 'Gagal memperbarui kategori.', true); }
        },
        /**
         * Ubah lingkup PIC — optimistis, dikembalikan bila server menolak.
         *
         * Pola yang sama dengan toggleKonten di atas: layar berubah lebih dulu
         * supaya terasa seketika, dan hanya dikembalikan bila benar-benar gagal.
         */
        async ubahLingkup(u, h, lingkup) {
            const sebelum = h.lingkupPic;
            if (sebelum === lingkup) return;

            h.lingkupPic = lingkup;
            try {
                await axios.post(`${API}/ubah-lingkup`, { idPageAccess: h.idPageAccess, lingkup }, CFG);
            } catch (e) {
                h.lingkupPic = sebelum;
                this.notice(e.response?.data?.message || 'Gagal mengubah lingkup PIC.', true);
            }
        },
        async bulk(u, h, jenis, aktif) {
            try {
                await axios.post(`${API}/bulk-toggle`, { idPageAccess: h.idPageAccess, jenis, aktif }, CFG);
                if (jenis === 'AKSI') h.aksi = aktif ? this.ref.aksi.map((a) => a.id) : [];
                else h.kategori = aktif ? this.ref.kategori.map((k) => k.kode) : [];
                this.notice(aktif ? 'Semua dicentang.' : 'Semua dilepas.');
                this.loadSummary();
            } catch (e) { this.notice(e.response?.data?.message || 'Gagal menyimpan.', true); }
        },

        /* ── Beri akses ── */
        openTambah() { this.formTambah = { userId: null, pages: [] }; this.showTambah = true; },
        toggleSemuaHalaman() {
            this.formTambah.pages = this.formTambah.pages.length === this.ref.menu.length ? [] : this.ref.menu.map((m) => m.jenisPage);
        },
        async simpanTambah() {
            if (this.sibuk) return;
            if (!this.formTambah.userId) return this.notice('Pilih pengguna dulu.', true);
            if (!this.formTambah.pages.length) return this.notice('Pilih minimal satu halaman.', true);
            this.sibuk = true;
            try {
                const r = await axios.post(API, this.formTambah, CFG);
                this.notice(r.data?.message || 'Akses ditambahkan.');
                this.showTambah = false;
                await this.load(this.pag.page); this.loadSummary();
            } catch (e) { this.notice(e.response?.data?.message || 'Gagal menyimpan.', true); }
            finally { this.sibuk = false; }
        },

        /* ── Duplikat ── */
        openDup() { this.formDup = { dariUserId: null, keUserId: null, timpa: false }; this.showDup = true; },
        async simpanDup() {
            if (this.sibuk) return;
            if (!this.formDup.dariUserId || !this.formDup.keUserId) return this.notice('Pilih pengguna sumber & tujuan.', true);
            if (this.formDup.dariUserId === this.formDup.keUserId) return this.notice('Pengguna sumber dan tujuan tidak boleh sama.', true);
            this.sibuk = true;
            try {
                const r = await axios.post(`${API}/duplikat`, this.formDup, CFG);
                this.notice(r.data?.message || 'Hak akses diduplikat.');
                this.showDup = false;
                await this.load(this.pag.page); this.loadSummary();
            } catch (e) { this.notice(e.response?.data?.message || 'Gagal menduplikat.', true); }
            finally { this.sibuk = false; }
        },

        /* ── Cabut halaman ── */
        askCabut(u, h) { this.cabutTarget = { u, h }; this.cabutShow = true; },
        async confirmCabut() {
            if (this.sibuk || !this.cabutTarget) return;
            this.sibuk = true;
            try {
                await axios.delete(`${API}/${this.cabutTarget.h.idPageAccess}`, CFG);
                this.notice('Akses halaman dicabut.');
                this.cabutShow = false; this.cabutTarget = null;
                await this.load(this.pag.page); this.loadSummary();
            } catch (e) { this.notice(e.response?.data?.message || 'Gagal mencabut akses.', true); }
            finally { this.sibuk = false; }
        },

        notice(x) { this.toast = x; if (this.tm) clearTimeout(this.tm); this.tm = setTimeout(() => (this.toast = ''), 3000); },
    },
};
</script>

<style scoped>
/* ── Statistik ── */
.ha-stats { background: linear-gradient(135deg, #fff, #fbfbff); border: 1px solid rgba(15,23,42,.08); border-radius: 18px; padding: 1rem 1.15rem 1.15rem; margin-bottom: 1rem; }
.ha-stats__hdr { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: .9rem; }
.ha-stats__title { font-size: 18px; font-weight: 800; color: #1e2447; letter-spacing: -.02em; }
.ha-stats__sub { font-size: 12.5px; color: #64748b; margin-top: 2px; }
.ha-live { display: inline-flex; align-items: center; gap: .35rem; font-size: 11px; font-weight: 800; color: #047857; background: rgba(16,185,129,.1); border-radius: 999px; padding: 3px 10px; }
.ha-live__dot { width: 6px; height: 6px; border-radius: 50%; background: #10b981; animation: haPulse 1.6s infinite; }
@keyframes haPulse { 0%,100% { opacity: 1 } 50% { opacity: .3 } }
.ha-stats__bar { display: grid; grid-template-columns: repeat(4, 1fr) auto; gap: .7rem; align-items: stretch; }
.ha-stat { display: flex; align-items: center; gap: .6rem; padding: .7rem .8rem; border-radius: 13px; border: 1px solid rgba(15,23,42,.06); background: #fff; }
.ha-stat__ico { flex: none; width: 36px; height: 36px; border-radius: 10px; display: grid; place-items: center; font-size: 16px; }
.ha-stat__num { font-size: 20px; font-weight: 800; color: #1e2447; line-height: 1.1; font-variant-numeric: tabular-nums; }
.ha-stat__lbl { font-size: 11px; color: #64748b; font-weight: 600; }
.ha-stat--indigo .ha-stat__ico { background: rgba(99,102,241,.12); color: #4f46e5; }
.ha-stat--violet .ha-stat__ico { background: rgba(139,92,246,.12); color: #7c3aed; }
.ha-stat--green .ha-stat__ico { background: rgba(16,185,129,.12); color: #047857; }
.ha-stat--amber .ha-stat__ico { background: rgba(217,119,6,.12); color: #b45309; }
.ha-stats__act { display: flex; align-items: center; gap: .5rem; }
.ha-btn-ghost { display: inline-flex; align-items: center; gap: .4rem; border: 1px solid rgba(79,70,229,.25); background: #eef2ff; color: #4338ca; font-size: 12.5px; font-weight: 700; border-radius: 11px; padding: .6rem .9rem; cursor: pointer; white-space: nowrap; }
.ha-btn-ghost:hover { background: #e0e7ff; }

/* ── Filter ── */
.ha-filter { background: #fff; border: 1px solid rgba(15,23,42,.08); border-radius: 16px; padding: .9rem 1rem 1rem; margin-bottom: 1rem; }
.ha-filter__head { display: flex; align-items: center; justify-content: space-between; margin-bottom: .65rem; }
.ha-filter__title { display: inline-flex; align-items: center; gap: .45rem; font-size: 12px; font-weight: 800; letter-spacing: .06em; text-transform: uppercase; color: #4338ca; }
.ha-filter__act { display: inline-flex; gap: .4rem; }
.ha-filter__reset { display: inline-flex; align-items: center; gap: .3rem; border: 1px solid rgba(79,70,229,.25); background: #eef2ff; color: #4338ca; font-size: 11.5px; font-weight: 700; border-radius: 9px; padding: 4px 10px; cursor: pointer; }
.ha-filter__close { display: none; border: none; background: transparent; color: #64748b; font-size: 15px; cursor: pointer; }
.ha-filter__grid { display: grid; grid-template-columns: minmax(220px,1.6fr) 1fr 1fr auto; gap: .7rem; align-items: end; }
.ha-filter__count { font-size: 12px; color: #64748b; padding-bottom: .5rem; white-space: nowrap; }
.ha-sheetbg { display: none; }
.ha-fab { display: none; position: fixed; right: 18px; bottom: 20px; z-index: 70; width: 52px; height: 52px; border-radius: 50%; border: none; background: linear-gradient(135deg,#8b5cf6,#6366f1); color: #fff; font-size: 19px; cursor: pointer; box-shadow: 0 12px 28px rgba(99,102,241,.45); }
.ha-fab__badge { position: absolute; top: -4px; right: -4px; min-width: 19px; height: 19px; border-radius: 999px; background: #ef4444; color: #fff; font-size: 10.5px; font-weight: 800; display: grid; place-items: center; padding: 0 5px; border: 2px solid #fff; }

/* ── Accordion pengguna ── */
.ha-list { display: flex; flex-direction: column; gap: .7rem; }
.ha-user { background: #fff; border: 1px solid rgba(15,23,42,.08); border-radius: 15px; overflow: hidden; transition: box-shadow .16s; }
.ha-user.open { box-shadow: 0 12px 30px rgba(15,23,42,.08); border-color: rgba(99,102,241,.3); }
.ha-user__hdr { display: flex; align-items: center; gap: .75rem; padding: .85rem 1rem; cursor: pointer; }
.ha-user__hdr:hover { background: #fafbff; }
.ha-avatar { flex: none; width: 40px; height: 40px; border-radius: 12px; display: grid; place-items: center; color: #fff; font-size: 13px; font-weight: 800; }
.ha-user__info { flex: 1; min-width: 0; }
.ha-user__nama { font-size: 14px; font-weight: 700; color: #1e2447; }
.ha-user__meta { display: flex; flex-wrap: wrap; gap: .3rem .8rem; font-size: 11.5px; color: #94a3b8; margin-top: 2px; }
.ha-susun { flex: none; display: inline-flex; align-items: center; gap: .35rem; border: 1px solid rgba(79,70,229,.25); background: #eef2ff; color: #4338ca; font-size: 11.5px; font-weight: 700; border-radius: 9px; padding: .35rem .65rem; cursor: pointer; white-space: nowrap; }
.ha-susun:hover { background: #e0e7ff; }
.ha-user__chev { color: #94a3b8; transition: transform .18s; }
.ha-user__chev.open { transform: rotate(180deg); }
.ha-user__body { border-top: 1px solid rgba(15,23,42,.06); padding: .8rem 1rem 1rem; background: #fafbfd; display: flex; flex-direction: column; gap: .7rem; }
.ha-kosong { font-size: 12.5px; color: #94a3b8; padding: .8rem 0; }

.ha-page-row { background: #fff; border: 1px solid rgba(15,23,42,.07); border-radius: 12px; padding: .75rem .85rem; }
.ha-page-row__hdr { display: flex; align-items: center; gap: .55rem; margin-bottom: .6rem; }
.ha-page-row__ico { flex: none; width: 30px; height: 30px; border-radius: 9px; background: rgba(99,102,241,.1); color: #4f46e5; display: grid; place-items: center; font-size: 14px; }
.ha-page-row__id { flex: 1; min-width: 0; }
.ha-page-row__id strong { display: block; font-size: 13px; color: #1e2447; }
.ha-page-row__id small { font-size: 10.5px; color: #94a3b8; }
.ha-page-row__id code { background: #f1f5f9; border-radius: 4px; padding: 0 4px; }
.ha-mini { flex: none; border: 1px solid rgba(15,23,42,.1); background: #fff; color: #475569; border-radius: 8px; width: 28px; height: 28px; cursor: pointer; font-size: 12px; }
.ha-mini:hover { background: #f1f5f9; }
.ha-mini--danger { border-color: #fecaca; color: #b91c1c; }
.ha-mini--danger:hover { background: #fef2f2; }
.ha-mini--inline { width: 22px; height: 22px; font-size: 10px; margin-left: .3rem; }

.ha-chk-block { padding-top: .5rem; }
.ha-chk-lbl { display: flex; align-items: center; gap: .35rem; font-size: 10.5px; font-weight: 800; letter-spacing: .05em; text-transform: uppercase; color: #64748b; margin-bottom: .4rem; }
.ha-chks { display: flex; flex-wrap: wrap; gap: .4rem; }
.ha-chk { display: inline-flex; align-items: center; gap: .35rem; border: 1px solid rgba(15,23,42,.12); border-radius: 8px; padding: .3rem .6rem; font-size: 11.5px; font-weight: 700; color: #64748b; cursor: pointer; user-select: none; transition: all .14s; background: #fff; }
.ha-chk input { accent-color: #4f46e5; cursor: pointer; }
.ha-chk:hover { border-color: #a5b4fc; }
.ha-chk.on { background: #eef2ff; border-color: #6366f1; color: #4338ca; }
.ha-chk--kat.on { background: #fffbeb; border-color: #f59e0b; color: #b45309; }
.ha-chk--kat input { accent-color: #d97706; }
.ha-chk-note { margin: .4rem 0 0; font-size: 10.5px; color: #94a3b8; }

.ha-pager { display: flex; align-items: center; justify-content: center; gap: .8rem; padding: .8rem 0; font-size: 12.5px; color: #64748b; }
.ha-pager button { border: 1px solid rgba(15,23,42,.12); background: #fff; border-radius: 8px; width: 32px; height: 32px; cursor: pointer; }
.ha-pager button:disabled { opacity: .4; cursor: not-allowed; }

/* Modal pilih halaman */
.ha-pick { display: grid; grid-template-columns: repeat(auto-fill, minmax(230px,1fr)); gap: .4rem; max-height: 320px; overflow-y: auto; padding: .2rem; }
.ha-pick__item { display: flex; align-items: center; gap: .45rem; border: 1px solid rgba(15,23,42,.1); border-radius: 9px; padding: .45rem .6rem; font-size: 12px; cursor: pointer; background: #fff; }
.ha-pick__item.on { background: #eef2ff; border-color: #6366f1; }
.ha-pick__item input { accent-color: #4f46e5; }
.ha-pick__nama { flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; color: #334155; font-weight: 600; }
.ha-pick__role { font-size: 9.5px; font-weight: 800; text-transform: uppercase; color: #64748b; background: #f1f5f9; border-radius: 999px; padding: 1px 6px; }
.ha-pick__role.is-kand { background: #fef3c7; color: #b45309; }
.ha-dup-check { display: block; margin-top: .5rem; }

@media (max-width: 1100px) { .ha-stats__bar { grid-template-columns: repeat(2, 1fr); } .ha-stats__act { grid-column: 1 / -1; } }
@media (max-width: 640px) {
    .ha-susun span { display: none; }
    .ha-filter { display: none; }
    .ha-filter.is-open { display: block; position: fixed; left: 0; right: 0; bottom: 0; z-index: 80; margin: 0; border-radius: 18px 18px 0 0; max-height: 78vh; overflow-y: auto; box-shadow: 0 -18px 40px rgba(15,23,42,.25); }
    .ha-filter__grid { grid-template-columns: 1fr; }
    .ha-filter__close { display: inline-flex; }
    .ha-fab { display: grid; place-items: center; }
    .ha-sheetbg { display: block; position: fixed; inset: 0; z-index: 75; background: rgba(15,23,42,.45); }
    .ha-stats__bar { grid-template-columns: 1fr 1fr; }
}

/* Pilihan lingkup — bentuknya mengikuti .ha-chk lain supaya terbaca sebagai
   keluarga kendali yang sama, tapi warnanya amber: ini SATU pilihan yang
   meniadakan dua lainnya, bukan centang yang bisa menumpuk. */
.ha-chk--lingkup.on { border-color: #f59e0b; background: #fffbeb; color: #92400e; }
.ha-chk--lingkup span { display: inline-flex; align-items: center; gap: .3rem; }
.ha-chk--lingkup .bi { font-size: 11px; }
</style>
