<!-- WEB CAREERS — Akses Klasifikasi Akun: CETAKAN hak akses kandidat + whitelist kategori program. -->
<template>
    <Head title="Akses Klasifikasi Akun" />
    <div class="wca">
        <div class="pkg-head">
            <div class="pkg-head__l">
                <div class="pkg-head__title">
                    <span class="pkg-head__ico"><i class="bi bi-shield-lock"></i></span>
                    <h1>Akses Klasifikasi Akun</h1>
                </div>
                <p>Cetakan hak akses yang <b>otomatis diberikan</b> ke kandidat saat mendaftar — admin tidak perlu mencentang satu per satu.</p>
            </div>
        </div>

        <div class="wca-note wca-note--info">
            <i class="bi bi-info-circle"></i>
            <span>
                Ubah cetakan → berlaku untuk kandidat baru seketika. Untuk menerapkan ke kandidat
                <b>yang sudah ada</b>, tekan <b>Terapkan Sekarang</b> pada klasifikasi terkait.
            </span>
        </div>

        <div v-loading="loading" class="ka-list">
            <div v-for="k in klasifikasi" :key="k.kode" class="ka-card" :class="{ off: k.status !== 'AKTIF' }">
                <div class="ka-card__hdr">
                    <span class="ka-card__ico"><i class="bi bi-person-badge"></i></span>
                    <div class="ka-card__id">
                        <strong>{{ k.nama }}</strong>
                        <small><code>{{ k.kode }}</code> · {{ k.durasiHari ? k.durasiHari + ' hari' : 'permanen' }} · {{ k.jumlahKandidat }} kandidat</small>
                    </div>
                    <span v-if="k.defaultRegister" class="wca-badge wca-b--green" title="Klasifikasi bawaan saat registrasi">Default Daftar</span>
                    <span class="wca-badge" :class="k.status === 'AKTIF' ? 'wca-b--indigo' : 'wca-b--slate'">{{ k.status }}</span>
                    <button class="ka-apply" :disabled="sibuk" title="Salin cetakan ini ke seluruh kandidat yang sudah ada" :onClick="sibuk ? null : () => terapkan(k)">
                        <i class="bi bi-arrow-repeat"></i> Terapkan Sekarang
                    </button>
                </div>

                <div class="ka-card__body">
                    <!-- MENU + AKSI -->
                    <div class="ka-block">
                        <div class="ka-block__lbl"><i class="bi bi-list-check"></i> Menu &amp; Aksi Kandidat</div>
                        <div class="ka-menus">
                            <div v-for="m in menu" :key="m.jenisPage" class="ka-menu" :class="{ on: aktifMenu(k, m) }">
                                <label class="ka-menu__head">
                                    <input type="checkbox" :checked="aktifMenu(k, m)" @change="(e) => toggleMenu(k, m, e.target.checked)" />
                                    <i class="bi" :class="ikonKelas(m.ikon)"></i>
                                    <span class="ka-menu__nama">{{ m.nama }}</span>
                                    <code>{{ m.jenisPage }}</code>
                                </label>
                                <div v-if="aktifMenu(k, m)" class="ka-aksi">
                                    <label v-for="a in aksi" :key="a.id" class="ka-chk" :class="{ on: aktifAksi(k, m, a) }">
                                        <input type="checkbox" :checked="aktifAksi(k, m, a)" @change="(e) => toggleAksi(k, m, a, e.target.checked)" />
                                        <span>{{ a.nama }}</span>
                                    </label>
                                </div>
                            </div>
                            <div v-if="!menu.length" class="ka-kosong">Belum ada menu ber-peran <b>Kandidat</b> di Master Menu.</div>
                        </div>
                    </div>

                    <!-- WHITELIST KATEGORI -->
                    <div class="ka-block">
                        <div class="ka-block__lbl"><i class="bi bi-tags-fill"></i> Whitelist Program (kategori yang boleh dilamar)</div>
                        <div class="ka-chks">
                            <label v-for="kat in kategori" :key="kat.kode" class="ka-chk ka-chk--kat" :class="{ on: k.kategori.includes(kat.kode) }">
                                <input type="checkbox" :checked="k.kategori.includes(kat.kode)" @change="(e) => toggleWhitelist(k, kat, e.target.checked)" />
                                <span>{{ kat.nama }}</span>
                            </label>
                        </div>
                        <p class="ka-note">Kandidat berklasifikasi ini hanya melihat &amp; melamar program pada kategori yang dicentang.</p>
                    </div>
                </div>
            </div>

            <div v-if="!loading && !klasifikasi.length" class="wca-empty">
                <i class="bi bi-shield-lock"></i>
                <h4>Belum ada klasifikasi akun</h4>
            </div>
        </div>

        <transition name="wca-toast"><div v-if="toast" class="wca-toast"><i class="bi bi-check-circle-fill"></i> {{ toast }}</div></transition>
    </div>
</template>

<script>
import axios from 'axios';
import { Head } from '@inertiajs/vue3';

const API = '/api/v1/klasifikasi-akses';
const CFG = { headers: { Accept: 'application/json' } };

export default {
    components: { Head },
    data() {
        return { klasifikasi: [], aksi: [], menu: [], kategori: [], loading: false, sibuk: false, toast: '', tm: null };
    },
    mounted() { this.load(); },
    methods: {
        ikonKelas(i) { return (i || 'bi-dot').replace(/^bi\s+/, ''); },
        cetakanMenu(k, m) { return (k.menu || []).find((x) => x.jenisPage === m.jenisPage) || null; },
        aktifMenu(k, m) { return !!this.cetakanMenu(k, m)?.aktif; },
        aktifAksi(k, m, a) { return (this.cetakanMenu(k, m)?.aksi || []).includes(a.id); },

        async load() {
            this.loading = true;
            try {
                const r = (await axios.get(API, CFG)).data.result || {};
                this.klasifikasi = r.klasifikasi || [];
                this.aksi = r.aksi || [];
                this.menu = r.menu || [];
                this.kategori = r.kategori || [];
            } catch (e) { this.notice('Gagal memuat data.', true); }
            finally { this.loading = false; }
        },

        async toggleMenu(k, m, aktif) {
            try {
                await axios.post(`${API}/toggle-menu`, { kode: k.kode, jenisPage: m.jenisPage, aktif }, CFG);
                const c = this.cetakanMenu(k, m);
                if (c) { c.aktif = aktif; if (aktif && !c.aksi.length) { const v = this.aksi.find((a) => a.nama === 'VIEW'); if (v) c.aksi = [v.id]; } }
                else { const v = this.aksi.find((a) => a.nama === 'VIEW'); k.menu.push({ jenisPage: m.jenisPage, aktif, aksi: v ? [v.id] : [] }); }
                this.notice(aktif ? `Menu "${m.nama}" ditambahkan ke cetakan.` : `Menu "${m.nama}" dilepas dari cetakan.`);
            } catch (e) { this.notice(e.response?.data?.message || 'Gagal memperbarui cetakan.', true); }
        },
        async toggleAksi(k, m, a, aktif) {
            const c = this.cetakanMenu(k, m);
            if (!c) return;
            const sebelum = [...c.aksi];
            c.aksi = aktif ? [...c.aksi, a.id] : c.aksi.filter((x) => x !== a.id);
            try {
                await axios.post(`${API}/toggle-aksi`, { kode: k.kode, jenisPage: m.jenisPage, idAksi: a.id, aktif }, CFG);
            } catch (e) { c.aksi = sebelum; this.notice(e.response?.data?.message || 'Gagal memperbarui aksi.', true); }
        },
        async toggleWhitelist(k, kat, aktif) {
            const sebelum = [...k.kategori];
            k.kategori = aktif ? [...k.kategori, kat.kode] : k.kategori.filter((x) => x !== kat.kode);
            try {
                await axios.post(`${API}/toggle-whitelist`, { kode: k.kode, kategori: kat.kode, aktif }, CFG);
            } catch (e) { k.kategori = sebelum; this.notice(e.response?.data?.message || 'Gagal memperbarui whitelist.', true); }
        },
        async terapkan(k) {
            if (this.sibuk) return;
            this.sibuk = true;
            try {
                const r = await axios.post(`${API}/terapkan`, { kode: k.kode }, CFG);
                this.notice(r.data?.message || 'Cetakan diterapkan.');
            } catch (e) { this.notice(e.response?.data?.message || 'Gagal menerapkan cetakan.', true); }
            finally { this.sibuk = false; }
        },
        notice(x) { this.toast = x; if (this.tm) clearTimeout(this.tm); this.tm = setTimeout(() => (this.toast = ''), 3000); },
    },
};
</script>

<style scoped>
.ka-list { display: flex; flex-direction: column; gap: .85rem; }
.ka-card { background: #fff; border: 1px solid rgba(15,23,42,.08); border-radius: 16px; overflow: hidden; }
.ka-card.off { opacity: .6; }
.ka-card__hdr { display: flex; align-items: center; gap: .7rem; padding: .9rem 1rem; border-bottom: 1px solid rgba(15,23,42,.06); background: linear-gradient(135deg,#fbfbff,#fff); flex-wrap: wrap; }
.ka-card__ico { flex: none; width: 40px; height: 40px; border-radius: 12px; background: rgba(99,102,241,.12); color: #4f46e5; display: grid; place-items: center; font-size: 17px; }
.ka-card__id { flex: 1; min-width: 180px; }
.ka-card__id strong { display: block; font-size: 14.5px; color: #1e2447; }
.ka-card__id small { font-size: 11.5px; color: #94a3b8; }
.ka-card__id code { background: #f1f5f9; border-radius: 4px; padding: 0 5px; }
.ka-apply { display: inline-flex; align-items: center; gap: .35rem; border: 1px solid rgba(5,150,105,.3); background: #ecfdf5; color: #047857; font-size: 11.5px; font-weight: 700; border-radius: 9px; padding: .4rem .7rem; cursor: pointer; }
.ka-apply:hover:not(:disabled) { background: #d1fae5; }
.ka-apply:disabled { opacity: .5; cursor: not-allowed; }
.ka-card__body { padding: .9rem 1rem 1rem; display: flex; flex-direction: column; gap: .9rem; }
.ka-block__lbl { display: flex; align-items: center; gap: .4rem; font-size: 10.5px; font-weight: 800; letter-spacing: .05em; text-transform: uppercase; color: #64748b; margin-bottom: .5rem; }
.ka-menus { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px,1fr)); gap: .5rem; }
.ka-menu { border: 1px solid rgba(15,23,42,.1); border-radius: 11px; padding: .55rem .65rem; background: #fff; transition: all .14s; }
.ka-menu.on { border-color: #6366f1; background: #f8f9ff; }
.ka-menu__head { display: flex; align-items: center; gap: .45rem; font-size: 12.5px; font-weight: 700; color: #334155; cursor: pointer; }
.ka-menu__head input { accent-color: #4f46e5; cursor: pointer; }
.ka-menu__nama { flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.ka-menu__head code { font-size: 9.5px; color: #94a3b8; background: #f1f5f9; border-radius: 4px; padding: 0 4px; }
.ka-aksi { display: flex; flex-wrap: wrap; gap: .3rem; margin-top: .5rem; padding-top: .45rem; border-top: 1px dashed rgba(15,23,42,.08); }
.ka-chks { display: flex; flex-wrap: wrap; gap: .4rem; }
.ka-chk { display: inline-flex; align-items: center; gap: .3rem; border: 1px solid rgba(15,23,42,.12); border-radius: 8px; padding: .25rem .55rem; font-size: 11px; font-weight: 700; color: #64748b; cursor: pointer; background: #fff; transition: all .14s; }
.ka-chk input { accent-color: #4f46e5; cursor: pointer; }
.ka-chk.on { background: #eef2ff; border-color: #6366f1; color: #4338ca; }
.ka-chk--kat { padding: .3rem .65rem; font-size: 11.5px; }
.ka-chk--kat.on { background: #fffbeb; border-color: #f59e0b; color: #b45309; }
.ka-chk--kat input { accent-color: #d97706; }
.ka-note { margin: .45rem 0 0; font-size: 10.5px; color: #94a3b8; }
.ka-kosong { font-size: 12px; color: #94a3b8; }
</style>
