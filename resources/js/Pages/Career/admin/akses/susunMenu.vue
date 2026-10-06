<!-- WEB CAREERS — Penyusun Menu per Akun (gaya WordPress: panel kiri–kanan + drag & drop).
     Panel kiri  = halaman yang tersedia (master menu) → centang → "Tambah ke Menu".
     Panel kanan = struktur menu akun: grup & item digeser bebas, label/ikon boleh ditimpa.

     Tidak ada yang tersimpan sebelum tombol "Simpan Susunan" ditekan, dan
     pencabutan halaman ditahan dulu di baki "Akan dicabut" supaya bisa dibatalkan. -->
<template>
    <Head title="Susun Menu" />
    <div class="wca sm-page">
        <!-- ░░ KEPALA ░░ -->
        <div class="sm-head">
            <button class="sm-back" type="button" @click="kembali"><i class="bi bi-arrow-left"></i></button>
            <span class="sm-avatar" :style="{ background: warnaAvatar(user.nama) }">{{ inisial(user.nama) }}</span>
            <div class="sm-head__id">
                <div class="sm-head__title">Susun Menu — {{ user.nama || '…' }}</div>
                <div class="sm-head__sub">
                    <span class="wca-badge" :class="badgeRole(user.role)">{{ user.role }}</span>
                    <span><i class="bi bi-envelope"></i> {{ user.email }}</span>
                    <span><i class="bi bi-diagram-3"></i> {{ jumlahItem }} menu dalam {{ grup.length }} grup</span>
                </div>
            </div>
            <div class="sm-head__act">
                <span v-if="kotor" class="sm-dirty"><i class="bi bi-dot"></i> Belum disimpan</span>
                <button class="sm-btn-ghost" type="button" :disabled="!kotor || sibuk" :onClick="!kotor || sibuk ? null : muat"><i class="bi bi-arrow-counterclockwise"></i> Batalkan</button>
                <button class="pkg-newbtn" type="button" :disabled="sibuk" :onClick="sibuk ? null : simpan">
                    <i class="bi" :class="sibuk ? 'bi-hourglass-split' : 'bi-check2'"></i> Simpan Susunan
                </button>
            </div>
        </div>

        <!-- ░░ BAKI PENCABUTAN ░░ -->
        <div v-if="dicabut.length" class="sm-trash">
            <div class="sm-trash__l">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <div>
                    <strong>{{ dicabut.length }} halaman akan dicabut saat disimpan</strong>
                    <span>Centang aksi &amp; kategori pada halaman itu ikut terhapus dan tidak bisa dikembalikan.</span>
                </div>
            </div>
            <div class="sm-trash__list">
                <span v-for="d in dicabut" :key="d._k" class="sm-trash__chip">
                    <i class="bi" :class="ikonKelas(d.ikon)"></i> {{ d.label }}
                    <button type="button" title="Batalkan pencabutan" @click="urungCabut(d)"><i class="bi bi-arrow-counterclockwise"></i></button>
                </span>
            </div>
        </div>

        <div v-loading="loading" class="sm-body">
            <!-- ░░ PANEL KIRI — HALAMAN TERSEDIA ░░ -->
            <aside class="sm-left">
                <div class="sm-panel__head">
                    <span class="sm-panel__title"><i class="bi bi-collection"></i> Halaman Tersedia</span>
                    <span class="sm-panel__count">{{ tersediaTersaring.length }}</span>
                </div>

                <el-input v-model="cari" placeholder="Cari halaman…" clearable size="small">
                    <template #prefix><i class="bi bi-search"></i></template>
                </el-input>

                <div class="sm-left__list">
                    <div v-for="(isi, header) in tersediaGrup" :key="header" class="sm-avail">
                        <button class="sm-avail__hdr" type="button" @click="bukaGrup[header] = !bukaGrup[header]">
                            <i class="bi" :class="bukaGrup[header] === false ? 'bi-chevron-right' : 'bi-chevron-down'"></i>
                            <span>{{ header }}</span>
                            <small>{{ isi.length }}</small>
                        </button>
                        <div v-if="bukaGrup[header] !== false" class="sm-avail__body">
                            <label v-for="m in isi" :key="m.jenisPage" class="sm-avail__item" :class="{ on: pilihan.includes(m.jenisPage) }">
                                <input v-model="pilihan" type="checkbox" :value="m.jenisPage" />
                                <i class="bi" :class="ikonKelas(m.ikon)"></i>
                                <span class="sm-avail__nama">{{ m.label }}</span>
                            </label>
                        </div>
                    </div>

                    <div v-if="!loading && !tersediaTersaring.length" class="sm-left__kosong">
                        <i class="bi bi-check2-circle"></i>
                        <p>{{ cari ? 'Tidak ada halaman yang cocok.' : 'Semua halaman untuk peran ini sudah masuk menu.' }}</p>
                    </div>
                </div>

                <div class="sm-left__foot">
                    <el-select v-model="tujuanGrup" size="small" placeholder="Masukkan ke grup…" style="width:100%">
                        <el-option v-for="(g, i) in grup" :key="g._k" :value="i" :label="g.judul || 'Tanpa nama'" />
                        <el-option :value="-1" label="＋ Grup baru (ikut header master)" />
                    </el-select>
                    <button class="sm-addbtn" type="button" :disabled="!pilihan.length" :onClick="!pilihan.length ? null : tambahKeMenu">
                        <i class="bi bi-arrow-right-circle"></i> Tambah ke Menu<span v-if="pilihan.length"> ({{ pilihan.length }})</span>
                    </button>
                </div>
            </aside>

            <!-- ░░ PANEL KANAN — STRUKTUR MENU ░░ -->
            <section class="sm-right">
                <div class="sm-panel__head">
                    <span class="sm-panel__title"><i class="bi bi-list-nested"></i> Struktur Menu Sidebar</span>
                    <span class="sm-panel__hint"><i class="bi bi-grip-vertical"></i> Geser grup &amp; item untuk mengurutkan</span>
                </div>

                <draggable v-model="grup" item-key="_k" handle=".sm-grp__drag" ghost-class="sm-ghost" animation="160" class="sm-grps">
                    <template #item="{ element: g, index: gi }">
                        <div class="sm-grp">
                            <div class="sm-grp__hdr">
                                <span class="sm-grp__drag" title="Geser grup"><i class="bi bi-grip-vertical"></i></span>
                                <i class="bi bi-folder2 sm-grp__ico"></i>
                                <input
                                    v-model="g.judul" class="sm-grp__judul" type="text"
                                    placeholder="Nama grup (header sidebar)" maxlength="100"
                                />
                                <span class="sm-grp__n">{{ g.items.length }} menu</span>
                                <span v-if="jmlSub(g)" class="sm-grp__sub">{{ jmlSub(g) }} sub-grup</span>
                                <!-- Sidebar mengelompokkan menurut NILAI sub-grupnya, bukan
                                     menurut posisi di daftar ini. Selama urutannya
                                     berselang-seling, apa yang terlihat di sini tidak sama
                                     dengan apa yang digambar sidebar — dan tombol inilah
                                     yang menyamakan keduanya. -->
                                <button v-if="perluRapi(g)" class="sm-mini sm-mini--rapi" type="button" title="Urutkan menu supaya sesuai tampilan sidebar" @click="rapikan(g)">
                                    <i class="bi bi-sort-down"></i>
                                </button>
                                <button class="sm-mini sm-mini--danger" type="button" :title="g.items.length ? 'Kosongkan dulu grupnya' : 'Hapus grup'" :disabled="!!g.items.length" :onClick="!!g.items.length ? null : () => hapusGrup(gi)">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>

                            <draggable v-model="g.items" item-key="_k" :group="{ name: 'menu-item' }" handle=".sm-item__drag" ghost-class="sm-ghost" animation="160" class="sm-items">
                                <template #item="{ element: it, index: ii }">
                                    <div class="sm-item" :class="{ 'is-open': terbuka === it._k, 'is-new': !it.idPageAccess, 'is-warn': it.yatim || it.nonaktifMaster }">
                                        <!-- Pita hanya muncul saat sub-grupnya BERGANTI dari
                                             baris sebelumnya. Menggambarnya di setiap baris
                                             mengubah daftar jadi deretan judul, dan judul yang
                                             berulang berhenti jadi penanda. -->
                                        <div v-if="pisahSub(g, ii)" class="sm-band">
                                            <span class="sm-band__t">{{ pisahSub(g, ii) }}</span>
                                            <span class="sm-band__l"></span>
                                        </div>
                                        <div class="sm-item__row">
                                            <span class="sm-item__drag" title="Geser item"><i class="bi bi-grip-vertical"></i></span>
                                            <span class="sm-item__ico"><i class="bi" :class="ikonKelas(it.ikon)"></i></span>
                                            <div class="sm-item__id">
                                                <strong>{{ it.label }}</strong>
                                                <small><code>{{ it.jenisPage }}</code> <span v-if="it.url">· {{ it.url }}</span></small>
                                            </div>

                                            <span v-if="!it.idPageAccess" class="sm-tag sm-tag--new">baru</span>
                                            <span v-else-if="!it.punyaView" class="sm-tag sm-tag--warn" title="Tanpa aksi VIEW menu ini tidak muncul di sidebar">tanpa VIEW</span>
                                            <span v-if="it.yatim" class="sm-tag sm-tag--danger" title="Master menunya sudah dihapus">yatim</span>
                                            <span v-else-if="it.nonaktifMaster" class="sm-tag sm-tag--warn" title="Menu dinonaktifkan di Master Menu">nonaktif</span>
                                            <span v-if="it.grup" class="sm-tag sm-tag--sub" :title="`Sub-grup: ${it.grup}`">
                                                <i class="bi bi-diagram-2"></i>{{ it.grup }}
                                            </span>
                                            <span v-if="ditimpa(it)" class="sm-tag sm-tag--custom" title="Tampilannya ditimpa khusus akun ini">ditimpa</span>

                                            <button class="sm-mini" type="button" title="Ubah tampilan" @click="terbuka = terbuka === it._k ? null : it._k">
                                                <i class="bi" :class="terbuka === it._k ? 'bi-chevron-up' : 'bi-sliders'"></i>
                                            </button>
                                            <button class="sm-mini sm-mini--danger" type="button" title="Keluarkan dari menu" @click="keluarkan(gi, it)">
                                                <i class="bi bi-x-lg"></i>
                                            </button>
                                        </div>

                                        <div v-if="terbuka === it._k" class="sm-item__edit">
                                            <div class="sm-edit__grid">
                                                <div>
                                                    <label class="wca-field-lbl">Label di sidebar</label>
                                                    <el-input v-model="it.label" size="small" :placeholder="it.labelMaster" maxlength="150" />
                                                </div>
                                                <div>
                                                    <label class="wca-field-lbl">Sub-grup</label>
                                                    <el-select
                                                        v-model="it.grup" size="small"
                                                        filterable allow-create default-first-option clearable
                                                        :placeholder="it.grupMaster || 'Tanpa sub-grup'"
                                                        style="width:100%"
                                                    >
                                                        <el-option v-for="sg in subPilihan(g)" :key="sg" :label="sg" :value="sg" />
                                                    </el-select>
                                                </div>
                                                <div>
                                                    <label class="wca-field-lbl">Sub-header</label>
                                                    <el-input v-model="it.subHeader" size="small" :placeholder="it.subHeaderMaster || '—'" maxlength="100" />
                                                </div>
                                                <div>
                                                    <label class="wca-field-lbl">Ikon</label>
                                                    <IconPicker :model-value="ikonKelas(it.ikon)" compact @update:model-value="(v) => (it.ikon = v || it.ikonMaster)" />
                                                </div>
                                            </div>
                                            <div class="sm-edit__foot">
                                                <span class="sm-edit__note">
                                                    Kosong / sama dengan master = <b>ikut Master Menu</b>, jadi perubahan di Master Menu tetap menular ke akun ini.
                                                </span>
                                                <button class="sm-btn-ghost sm-btn-ghost--sm" type="button" :disabled="!ditimpa(it)" :onClick="!ditimpa(it) ? null : () => kembalikanKeMaster(it)">
                                                    <i class="bi bi-arrow-counterclockwise"></i> Kembalikan ke master
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </template>
                            </draggable>

                            <div v-if="!g.items.length" class="sm-items__kosong">Seret item ke sini, atau tambahkan dari panel kiri.</div>
                        </div>
                    </template>
                </draggable>

                <div v-if="!loading && !grup.length" class="wca-empty">
                    <i class="bi bi-list-nested"></i>
                    <h4>Belum ada menu untuk akun ini</h4>
                    <p>Centang halaman di panel kiri lalu tekan “Tambah ke Menu”.</p>
                </div>

                <button class="sm-newgrp" type="button" @click="tambahGrup"><i class="bi bi-folder-plus"></i> Grup Baru</button>
            </section>
        </div>

        <ConfirmModal
            :show="konfirmShow" title="Simpan Susunan Menu" :busy="sibuk" confirm-label="Ya, Simpan"
            :note="dicabut.length ? `${dicabut.length} halaman akan DICABUT beserta seluruh centang aksi & kategorinya.` : 'Urutan & tampilan menu diperbarui di tempat — centang aksi dan kategori tidak tersentuh.'"
            @cancel="konfirmShow = false" @confirm="kirim"
        >
            Simpan susunan menu untuk <strong>{{ user.nama }}</strong>?
        </ConfirmModal>

        <transition name="wca-toast"><div v-if="toast" class="wca-toast"><i class="bi" :class="toastErr ? 'bi-exclamation-circle-fill' : 'bi-check-circle-fill'"></i> {{ toast }}</div></transition>
    </div>
</template>

<script>
import axios from 'axios';
import { Head, router } from '@inertiajs/vue3';
import draggable from 'vuedraggable';
import ConfirmModal from '@career/ConfirmModal.vue';
import IconPicker from '@career/IconPicker.vue';

const CFG = { headers: { Accept: 'application/json' } };
let seq = 0;
const kunci = () => `k${++seq}`;

export default {
    components: { Head, draggable, ConfirmModal, IconPicker },
    props: { targetUser: { type: Object, default: () => ({}) } },
    data() {
        return {
            user: { ...this.targetUser },
            grup: [],
            tersedia: [],
            dicabut: [],          // item ber-idPageAccess yang dikeluarkan → dihapus saat simpan
            pilihan: [],
            tujuanGrup: -1,
            bukaGrup: {},
            cari: '',
            terbuka: null,
            awal: '',             // cap jari susunan saat dimuat → dasar penanda "belum disimpan"
            loading: false, sibuk: false, konfirmShow: false,
            toast: '', toastErr: false, tm: null,
            lepasGuard: null,
        };
    },
    computed: {
        userId() { return this.targetUser?.id || window.location.pathname.split('/').pop(); },
        jumlahItem() { return this.grup.reduce((n, g) => n + g.items.length, 0); },
        tersediaTersaring() {
            const q = this.cari.trim().toLowerCase();
            if (!q) return this.tersedia;
            return this.tersedia.filter((m) => `${m.label} ${m.jenisPage} ${m.header} ${m.url || ''}`.toLowerCase().includes(q));
        },
        tersediaGrup() {
            return this.tersediaTersaring.reduce((acc, m) => {
                (acc[m.header] ??= []).push(m);
                return acc;
            }, {});
        },
        muatan() {
            return {
                grup: this.grup.map((g) => ({
                    judul: (g.judul || '').trim() || 'Menu',
                    items: g.items.map((it) => ({
                        idPageAccess: it.idPageAccess || null,
                        jenisPage: it.jenisPage,
                        labelCustom: this.bedaMaster(it.label, it.labelMaster),
                        ikonCustom: this.bedaMaster(this.ikonKelas(it.ikon), this.ikonKelas(it.ikonMaster)) ? it.ikon : null,
                        subHeaderCustom: this.bedaMaster(it.subHeader, it.subHeaderMaster),
                        grupCustom: this.bedaMaster(it.grup, it.grupMaster),
                    })),
                })),
                hapus: this.dicabut.map((d) => d.idPageAccess),
            };
        },
        kotor() { return this.awal !== '' && JSON.stringify(this.muatan) !== this.awal; },
    },
    mounted() {
        this.muat();
        window.addEventListener('beforeunload', this.jagaTutup);
        // Pindah halaman lewat Inertia juga harus ditahan — kalau tidak, susunan
        // yang belum disimpan hilang tanpa peringatan apa pun.
        this.lepasGuard = router.on('before', (e) => {
            if (this.kotor && !this.sibuk && !window.confirm('Susunan menu belum disimpan. Tinggalkan halaman ini?')) {
                e.preventDefault();
            }
        });
    },
    beforeUnmount() {
        window.removeEventListener('beforeunload', this.jagaTutup);
        if (this.lepasGuard) this.lepasGuard();
        if (this.tm) clearTimeout(this.tm);
    },
    methods: {
        ikonKelas(i) { return (i || 'bi-dot').replace(/^bi\s+/, ''); },
        inisial(n) { const p = String(n || '').trim().split(/\s+/); return ((p[0]?.[0] || '') + (p[1]?.[0] || '')).toUpperCase() || '?'; },
        warnaAvatar(n) {
            const w = ['#6366f1', '#8b5cf6', '#0ea5e9', '#059669', '#d97706', '#dc2626', '#0f766e'];
            let s = 0; for (const c of String(n || '')) s += c.charCodeAt(0);
            return w[s % w.length];
        },
        badgeRole(r) { return r === 'KANDIDAT' ? 'wca-b--amber' : (r === 'SUPERADMIN' ? 'wca-b--indigo' : 'wca-b--slate'); },
        jagaTutup(e) { if (this.kotor) { e.preventDefault(); e.returnValue = ''; } },

        /** Nilai yang berbeda dari master dikirim sebagai timpaan; sisanya null. */
        bedaMaster(nilai, master) {
            const v = String(nilai ?? '').trim();
            return v && v !== String(master ?? '').trim() ? v : null;
        },
        ditimpa(it) {
            return !!(this.bedaMaster(it.label, it.labelMaster)
                || this.bedaMaster(this.ikonKelas(it.ikon), this.ikonKelas(it.ikonMaster))
                || this.bedaMaster(it.subHeader, it.subHeaderMaster)
                || this.bedaMaster(it.grup, it.grupMaster));
        },
        kembalikanKeMaster(it) {
            it.label = it.labelMaster;
            it.ikon = it.ikonMaster;
            it.subHeader = it.subHeaderMaster || '';
            it.grup = it.grupMaster || '';
        },

        /* ── SUB-GRUP ───────────────────────────────────────────────────────
           Sub-grup di sini bukan wadah yang bisa diseret, melainkan NILAI yang
           menempel di tiap menu — persis seperti label dan ikonnya. Itu bukan
           penyederhanaan: sidebar memang mengelompokkan menurut nilai, jadi
           wadah yang bisa diseret di layar ini akan menjanjikan sesuatu yang
           tidak dijamin hasil akhirnya. Yang digambar di sini hanya pita
           penanda, supaya nilai itu tetap terlihat tanpa berpura-pura jadi
           wadah. */

        /** Sub-grup yang sudah dipakai di grup ini — ditawarkan saat menyunting. */
        subPilihan(g) {
            const dari = (g?.items || []).map((x) => (x.grup || '').trim()).filter(Boolean);
            const master = (this.tersedia || []).map((x) => (x.grupMaster || '').trim()).filter(Boolean);

            return [...new Set([...dari, ...master])].sort((a, b) => a.localeCompare(b, 'id'));
        },
        jmlSub(g) {
            return new Set((g.items || []).map((x) => (x.grup || '').trim()).filter(Boolean)).size;
        },
        /** Judul pita bila baris ke-i memulai rentetan sub-grup baru. */
        pisahSub(g, i) {
            const kini = (g.items[i]?.grup || '').trim();
            if (!kini) return '';

            const sebelum = i > 0 ? (g.items[i - 1]?.grup || '').trim() : '';

            return kini === sebelum ? '' : kini;
        },
        /** Sub-grup yang sama muncul di dua tempat terpisah = urutannya belum rapi. */
        perluRapi(g) {
            const urut = (g.items || []).map((x) => (x.grup || '').trim());
            const lihat = new Set();
            let lalu = null;
            for (const k of urut) {
                if (k !== lalu) {
                    if (lihat.has(k)) return true;
                    lihat.add(k);
                    lalu = k;
                }
            }

            return false;
        },
        /**
         * Kumpulkan menu yang sesub-grup jadi berdekatan, tanpa mengubah urutan
         * di dalamnya maupun urutan kemunculan sub-grupnya. Menu tanpa sub-grup
         * naik ke atas — di sidebar pun ia digambar sebelum sub-grup pertama.
         */
        rapikan(g) {
            const urutSub = [];
            for (const it of g.items) {
                const k = (it.grup || '').trim();
                if (k && !urutSub.includes(k)) urutSub.push(k);
            }

            const lepas = g.items.filter((it) => !(it.grup || '').trim());
            const berkelompok = urutSub.flatMap((k) => g.items.filter((it) => (it.grup || '').trim() === k));

            g.items = [...lepas, ...berkelompok];
            this.notice('Urutan disamakan dengan tampilan sidebar.');
        },

        async muat() {
            this.loading = true;
            try {
                const r = (await axios.get(`/api/v1/hak-akses/susun/${this.userId}`, CFG)).data.result || {};
                this.user = r.pengguna || this.user;
                this.tersedia = r.tersedia || [];
                this.grup = (r.struktur || []).map((g) => ({
                    _k: kunci(),
                    judul: g.judul,
                    items: (g.items || []).map((it) => ({
                        ...it, _k: kunci(),
                        subHeader: it.subHeader || '',
                        grup: it.grup || '',
                    })),
                }));
                this.dicabut = [];
                this.pilihan = [];
                this.terbuka = null;
                this.tujuanGrup = this.grup.length ? 0 : -1;
                this.$nextTick(() => { this.awal = JSON.stringify(this.muatan); });
            } catch (e) {
                this.notice(e.response?.data?.message || 'Gagal memuat susunan menu.', true);
            } finally { this.loading = false; }
        },

        /* ── Panel kiri → kanan ── */
        tambahKeMenu() {
            const dipilih = this.tersedia.filter((m) => this.pilihan.includes(m.jenisPage));
            if (!dipilih.length) return;

            for (const m of dipilih) {
                // Grup tujuan: pilihan admin, atau grup dengan nama header master
                // (dibuat kalau belum ada) — persis mengikuti SOP master menu.
                let g = this.tujuanGrup >= 0 ? this.grup[this.tujuanGrup] : this.grup.find((x) => x.judul === m.header);
                if (!g) { g = { _k: kunci(), judul: m.header, items: [] }; this.grup.push(g); }

                g.items.push({
                    _k: kunci(),
                    idPageAccess: null,
                    jenisPage: m.jenisPage,
                    label: m.label, labelMaster: m.labelMaster,
                    ikon: m.ikon, ikonMaster: m.ikonMaster,
                    subHeader: m.subHeaderMaster || '', subHeaderMaster: m.subHeaderMaster,
                    grup: m.grupMaster || '', grupMaster: m.grupMaster,
                    headerMaster: m.headerMaster,
                    url: m.url, role: m.role,
                    yatim: false, nonaktifMaster: false,
                    jumlahAksi: 0, jumlahKategori: 0, punyaView: true,
                });
            }

            this.tersedia = this.tersedia.filter((m) => !this.pilihan.includes(m.jenisPage));
            this.notice(`${dipilih.length} halaman masuk susunan — tekan Simpan untuk menerapkan.`);
            this.pilihan = [];
        },

        /** Keluarkan item dari menu. Yang sudah tersimpan masuk baki pencabutan dulu. */
        keluarkan(gi, it) {
            const g = this.grup[gi];
            g.items = g.items.filter((x) => x._k !== it._k);
            if (this.terbuka === it._k) this.terbuka = null;

            if (it.idPageAccess) {
                this.dicabut.push(it);
            } else {
                // Belum pernah tersimpan → cukup dikembalikan ke panel kiri.
                this.tersedia.push(this.keTersedia(it));
            }
        },
        urungCabut(d) {
            this.dicabut = this.dicabut.filter((x) => x._k !== d._k);
            let g = this.grup.find((x) => x.judul === d.header) || this.grup[0];
            if (!g) { g = { _k: kunci(), judul: d.header || d.headerMaster || 'Menu', items: [] }; this.grup.push(g); }
            g.items.push(d);
        },
        keTersedia(it) {
            return {
                jenisPage: it.jenisPage, label: it.labelMaster, labelMaster: it.labelMaster,
                header: it.headerMaster, headerMaster: it.headerMaster, subHeaderMaster: it.subHeaderMaster,
                grup: it.grupMaster, grupMaster: it.grupMaster,
                ikon: it.ikonMaster, ikonMaster: it.ikonMaster, url: it.url, role: it.role,
            };
        },

        tambahGrup() {
            this.grup.push({ _k: kunci(), judul: `Grup Baru ${this.grup.length + 1}`, items: [] });
            this.tujuanGrup = this.grup.length - 1;
        },
        hapusGrup(gi) {
            if (this.grup[gi].items.length) return;
            this.grup.splice(gi, 1);
            if (this.tujuanGrup >= this.grup.length) this.tujuanGrup = this.grup.length ? 0 : -1;
        },

        /* ── Simpan ── */
        simpan() {
            if (this.sibuk) return;
            if (!this.kotor) return this.notice('Tidak ada perubahan untuk disimpan.');

            const kosong = this.grup.filter((g) => g.items.length && !(g.judul || '').trim());
            if (kosong.length) return this.notice('Ada grup berisi menu tapi tanpa nama. Beri nama dulu.', true);

            const dobel = {};
            for (const g of this.grup) {
                for (const it of g.items) {
                    if (dobel[it.jenisPage]) return this.notice(`Halaman "${it.label}" muncul dua kali dalam susunan.`, true);
                    dobel[it.jenisPage] = true;
                }
            }
            this.konfirmShow = true;
        },
        async kirim() {
            if (this.sibuk) return;
            this.sibuk = true;
            try {
                const r = await axios.post(`/api/v1/hak-akses/susun/${this.userId}`, this.muatan, CFG);
                this.konfirmShow = false;
                await this.muat();
                this.notice(r.data?.message || 'Susunan disimpan.');
            } catch (e) {
                this.notice(e.response?.data?.message || 'Gagal menyimpan susunan.', true);
            } finally { this.sibuk = false; }
        },
        kembali() { router.visit('/hak-akses'); },

        notice(x, err = false) {
            this.toast = x; this.toastErr = err;
            if (this.tm) clearTimeout(this.tm);
            this.tm = setTimeout(() => (this.toast = ''), 3200);
        },
    },
};
</script>

<style scoped>
/* ── Kepala ── */
.sm-head { display: flex; align-items: center; gap: .75rem; background: linear-gradient(135deg, #fff, #fbfbff); border: 1px solid rgba(15,23,42,.08); border-radius: 16px; padding: .8rem 1rem; margin-bottom: .8rem; }
.sm-back { flex: none; width: 34px; height: 34px; border-radius: 10px; border: 1px solid rgba(15,23,42,.12); background: #fff; color: #475569; cursor: pointer; }
.sm-back:hover { background: #f1f5f9; }
.sm-avatar { flex: none; width: 40px; height: 40px; border-radius: 12px; display: grid; place-items: center; color: #fff; font-size: 13px; font-weight: 800; }
.sm-head__id { flex: 1; min-width: 0; }
.sm-head__title { font-size: 16px; font-weight: 800; color: #1e2447; letter-spacing: -.01em; }
.sm-head__sub { display: flex; flex-wrap: wrap; align-items: center; gap: .35rem .7rem; font-size: 11.5px; color: #94a3b8; margin-top: 3px; }
.sm-head__act { display: flex; align-items: center; gap: .5rem; }
.sm-dirty { display: inline-flex; align-items: center; font-size: 11px; font-weight: 800; color: #b45309; background: #fffbeb; border: 1px solid rgba(245,158,11,.35); border-radius: 999px; padding: 3px 10px 3px 4px; }
.sm-btn-ghost { display: inline-flex; align-items: center; gap: .35rem; border: 1px solid rgba(79,70,229,.25); background: #eef2ff; color: #4338ca; font-size: 12.5px; font-weight: 700; border-radius: 11px; padding: .55rem .85rem; cursor: pointer; white-space: nowrap; }
.sm-btn-ghost:hover:not(:disabled) { background: #e0e7ff; }
.sm-btn-ghost:disabled { opacity: .45; cursor: not-allowed; }
.sm-btn-ghost--sm { font-size: 11px; padding: .3rem .6rem; border-radius: 8px; }

/* ── Baki pencabutan ── */
.sm-trash { border: 1px solid rgba(239,68,68,.35); background: #fef2f2; border-radius: 14px; padding: .7rem .9rem; margin-bottom: .8rem; }
.sm-trash__l { display: flex; align-items: flex-start; gap: .55rem; }
.sm-trash__l > i { color: #dc2626; font-size: 16px; margin-top: 1px; }
.sm-trash__l strong { display: block; font-size: 12.5px; color: #991b1b; }
.sm-trash__l span { font-size: 11px; color: #b91c1c; }
.sm-trash__list { display: flex; flex-wrap: wrap; gap: .35rem; margin-top: .55rem; }
.sm-trash__chip { display: inline-flex; align-items: center; gap: .35rem; background: #fff; border: 1px solid rgba(239,68,68,.3); color: #b91c1c; border-radius: 999px; padding: 3px 4px 3px 10px; font-size: 11.5px; font-weight: 700; }
.sm-trash__chip button { border: none; background: rgba(239,68,68,.1); color: #b91c1c; border-radius: 50%; width: 20px; height: 20px; cursor: pointer; font-size: 10px; }

/* ── Kerangka dua panel ── */
.sm-body { display: grid; grid-template-columns: 330px 1fr; gap: .85rem; align-items: start; }
.sm-panel__head { display: flex; align-items: center; justify-content: space-between; gap: .5rem; margin-bottom: .6rem; }
.sm-panel__title { display: inline-flex; align-items: center; gap: .4rem; font-size: 12px; font-weight: 800; letter-spacing: .05em; text-transform: uppercase; color: #4338ca; }
.sm-panel__count { font-size: 11px; font-weight: 800; color: #64748b; background: #f1f5f9; border-radius: 999px; padding: 1px 9px; }
.sm-panel__hint { font-size: 11px; color: #94a3b8; }

/* ── Panel kiri ── */
.sm-left { position: sticky; top: 12px; background: #fff; border: 1px solid rgba(15,23,42,.08); border-radius: 16px; padding: .85rem .9rem; display: flex; flex-direction: column; gap: .6rem; }
.sm-left__list { max-height: 52vh; overflow-y: auto; display: flex; flex-direction: column; gap: .3rem; padding-right: 2px; }
.sm-avail__hdr { display: flex; align-items: center; gap: .4rem; width: 100%; border: none; background: #f8fafc; border-radius: 9px; padding: .4rem .55rem; font-size: 11.5px; font-weight: 800; color: #475569; cursor: pointer; text-align: left; }
.sm-avail__hdr span { flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.sm-avail__hdr small { font-weight: 700; color: #94a3b8; }
.sm-avail__body { display: flex; flex-direction: column; gap: 2px; padding: .25rem 0 .35rem .3rem; }
.sm-avail__item { display: flex; align-items: center; gap: .45rem; border: 1px solid transparent; border-radius: 8px; padding: .35rem .5rem; font-size: 12px; color: #334155; cursor: pointer; }
.sm-avail__item:hover { background: #f8fafc; }
.sm-avail__item.on { background: #eef2ff; border-color: #a5b4fc; color: #3730a3; }
.sm-avail__item input { accent-color: #4f46e5; cursor: pointer; }
.sm-avail__nama { flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-weight: 600; }
.sm-left__kosong { text-align: center; padding: 1.4rem .5rem; color: #94a3b8; }
.sm-left__kosong i { font-size: 22px; color: #cbd5e1; }
.sm-left__kosong p { font-size: 11.5px; margin: .35rem 0 0; }
.sm-left__foot { border-top: 1px dashed rgba(15,23,42,.1); padding-top: .6rem; display: flex; flex-direction: column; gap: .45rem; }
.sm-addbtn { display: inline-flex; align-items: center; justify-content: center; gap: .4rem; border: none; border-radius: 11px; background: linear-gradient(135deg,#8b5cf6,#6366f1); color: #fff; font-size: 12.5px; font-weight: 700; padding: .6rem .9rem; cursor: pointer; }
.sm-addbtn:disabled { opacity: .45; cursor: not-allowed; }

/* ── Panel kanan ── */
.sm-right { background: #fff; border: 1px solid rgba(15,23,42,.08); border-radius: 16px; padding: .85rem .9rem 1rem; min-height: 320px; }
.sm-grps { display: flex; flex-direction: column; gap: .6rem; }
.sm-grp { border: 1px solid rgba(15,23,42,.09); border-radius: 13px; background: #fafbfd; overflow: hidden; }
.sm-grp__hdr { display: flex; align-items: center; gap: .45rem; padding: .5rem .6rem; background: #f1f5f9; border-bottom: 1px solid rgba(15,23,42,.07); }
.sm-grp__drag, .sm-item__drag { flex: none; color: #94a3b8; cursor: grab; font-size: 14px; padding: 0 2px; }
.sm-grp__drag:active, .sm-item__drag:active { cursor: grabbing; }
.sm-grp__ico { color: #6366f1; font-size: 14px; }
.sm-grp__judul { flex: 1; min-width: 0; border: 1px solid transparent; background: transparent; border-radius: 7px; padding: .25rem .45rem; font-size: 12.5px; font-weight: 800; color: #1e2447; }
.sm-grp__judul:hover { border-color: rgba(15,23,42,.12); background: #fff; }
.sm-grp__judul:focus { outline: none; border-color: #6366f1; background: #fff; }
.sm-grp__n { font-size: 10.5px; font-weight: 700; color: #94a3b8; white-space: nowrap; }

.sm-items { display: flex; flex-direction: column; gap: .35rem; padding: .5rem .55rem; min-height: 34px; }
.sm-items__kosong { font-size: 11.5px; color: #94a3b8; padding: .1rem .8rem .7rem; font-style: italic; }
.sm-item { background: #fff; border: 1px solid rgba(15,23,42,.09); border-radius: 10px; }
.sm-item.is-open { border-color: #a5b4fc; box-shadow: 0 6px 18px rgba(99,102,241,.12); }
.sm-item.is-new { border-left: 3px solid #10b981; }
.sm-item.is-warn { border-left: 3px solid #f59e0b; }
.sm-item__row { display: flex; align-items: center; gap: .45rem; padding: .45rem .55rem; }
.sm-item__ico { flex: none; width: 27px; height: 27px; border-radius: 8px; background: rgba(99,102,241,.1); color: #4f46e5; display: grid; place-items: center; font-size: 13px; }
.sm-item__id { flex: 1; min-width: 0; }
.sm-item__id strong { display: block; font-size: 12.5px; color: #1e2447; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.sm-item__id small { font-size: 10.5px; color: #94a3b8; }
.sm-item__id code { background: #f1f5f9; border-radius: 4px; padding: 0 4px; }

.sm-tag { flex: none; font-size: 9.5px; font-weight: 800; text-transform: uppercase; letter-spacing: .03em; border-radius: 999px; padding: 2px 7px; }
.sm-tag--new { background: #d1fae5; color: #047857; }
.sm-tag--warn { background: #fef3c7; color: #b45309; }
.sm-tag--danger { background: #fee2e2; color: #b91c1c; }
.sm-tag--custom { background: #ede9fe; color: #6d28d9; }
/* Keping sub-grup dibuat TENANG, bukan berwarna seperti keping keadaan di
   sebelahnya. "baru" dan "yatim" menuntut tindakan; sub-grup cuma menyatakan
   di mana menu ini akan duduk — dan keping seterang tetangganya membuat mata
   menganggap keduanya sama penting. */
.sm-tag--sub {
    display: inline-flex; align-items: center; gap: 4px;
    max-width: 160px;
    background: #f1f3fa; color: #64748b;
    text-transform: none; letter-spacing: 0; font-size: 10px; font-weight: 700;
    overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}
.sm-tag--sub i { font-size: 9px; color: #a5b4fc; }

.sm-mini { flex: none; border: 1px solid rgba(15,23,42,.1); background: #fff; color: #475569; border-radius: 8px; width: 26px; height: 26px; cursor: pointer; font-size: 11px; }
.sm-mini:hover:not(:disabled) { background: #f1f5f9; }
.sm-mini:disabled { opacity: .35; cursor: not-allowed; }
.sm-mini--danger { border-color: #fecaca; color: #b91c1c; }
.sm-mini--danger:hover:not(:disabled) { background: #fef2f2; }
.sm-mini--rapi { border-color: #c7d2fe; color: #4338ca; background: #f5f6ff; }
.sm-mini--rapi:hover:not(:disabled) { background: #eef2ff; }

.sm-item__edit { border-top: 1px dashed rgba(15,23,42,.1); padding: .6rem .6rem .55rem; background: #fbfcfe; border-radius: 0 0 9px 9px; }
.sm-edit__grid { display: grid; grid-template-columns: 1.15fr 1fr 1fr auto; gap: .55rem; align-items: end; }
.sm-edit__foot { display: flex; align-items: center; justify-content: space-between; gap: .6rem; margin-top: .5rem; }
.sm-edit__note { font-size: 10.5px; color: #94a3b8; }

/* ── PITA SUB-GRUP ───────────────────────────────────────────────────────────
   Setipis mungkin. Ia penanda tempat, bukan baris yang bisa dipilih — kalau
   setinggi item, daftar sepuluh menu terlihat seperti daftar enam belas. */
.sm-band {
    display: flex; align-items: center; gap: 8px;
    padding: 9px 6px 3px;
}
.sm-band__t {
    flex: 0 0 auto;
    font-size: 9.5px; font-weight: 800; letter-spacing: .09em; text-transform: uppercase;
    color: #7c86a8;
}
.sm-band__l {
    flex: 1; height: 1px;
    background: linear-gradient(90deg, rgba(99,102,241,.28), rgba(99,102,241,0));
}
/* Item pertama sesudah pita tidak perlu jarak ganda. */
.sm-item:first-child .sm-band { padding-top: 2px; }

.sm-grp__sub {
    flex: none;
    font-size: 10px; font-weight: 700;
    color: #4338ca; background: rgba(99,102,241,.1);
    border-radius: 6px; padding: 2px 7px;
}

.sm-newgrp { display: inline-flex; align-items: center; gap: .4rem; margin-top: .7rem; border: 1px dashed rgba(79,70,229,.4); background: #f8f9ff; color: #4338ca; font-size: 12px; font-weight: 700; border-radius: 10px; padding: .5rem .85rem; cursor: pointer; }
.sm-newgrp:hover { background: #eef2ff; }
.sm-ghost { opacity: .45; background: #eef2ff !important; border-style: dashed !important; }

@media (max-width: 1000px) {
    .sm-body { grid-template-columns: 1fr; }
    .sm-left { position: static; max-height: none; }
    .sm-left__list { max-height: 40vh; }
    .sm-edit__grid { grid-template-columns: 1fr 1fr; }
}
@media (max-width: 620px) {
    .sm-edit__grid { grid-template-columns: 1fr; }
}
@media (max-width: 640px) {
    .sm-head { flex-wrap: wrap; }
    .sm-head__act { width: 100%; justify-content: flex-end; }
}
</style>
