<!-- WEB CAREERS — Master Menu (daftar halaman yang bisa diberi hak akses). -->
<template>
    <Head title="Master Menu" />
    <div class="wca">
        <div class="pkg-head">
            <div class="pkg-head__l">
                <div class="pkg-head__title">
                    <span class="pkg-head__ico"><i class="bi bi-list-nested"></i></span>
                    <h1>Master Menu</h1>
                </div>
                <p>Daftar halaman yang bisa diberi hak akses. <b>Kunci halaman</b> dipakai middleware — jangan diubah setelah dipasang di route.</p>
            </div>
            <button class="pkg-newbtn" @click="openCreate"><i class="bi bi-plus-lg"></i> Menu Baru</button>
        </div>

        <!-- FILTER PANEL -->
        <div v-if="sheetOpen" class="mm-sheetbg" @click="sheetOpen = false"></div>
        <div class="mm-filter" :class="{ 'is-open': sheetOpen }">
            <div class="mm-filter__head">
                <span class="mm-filter__title"><i class="bi bi-funnel"></i> Filter Panel</span>
                <div class="mm-filter__act">
                    <button v-if="adaFilter" class="mm-filter__reset" type="button" @click="resetFilter"><i class="bi bi-arrow-counterclockwise"></i> Reset</button>
                    <button class="mm-filter__close" type="button" aria-label="Tutup" @click="sheetOpen = false"><i class="bi bi-x-lg"></i></button>
                </div>
            </div>
            <div class="mm-filter__grid">
                <div>
                    <label class="wca-field-lbl">Cari</label>
                    <el-input v-model="filters.q" placeholder="Nama / kunci / URL" clearable @input="cariDebounce">
                        <template #prefix><i class="bi bi-search"></i></template>
                    </el-input>
                </div>
                <div>
                    <label class="wca-field-lbl">Untuk Peran</label>
                    <el-select v-model="filters.role" placeholder="Semua peran" clearable style="width:100%" @change="load">
                        <el-option label="Admin" value="ADMIN" />
                        <el-option label="Kandidat" value="KANDIDAT" />
                    </el-select>
                </div>
                <div>
                    <label class="wca-field-lbl">Status</label>
                    <el-select v-model="filters.status" placeholder="Semua status" clearable style="width:100%" @change="load">
                        <el-option label="Aktif" value="AKTIF" />
                        <el-option label="Nonaktif" value="NONAKTIF" />
                    </el-select>
                </div>
            </div>
        </div>
        <button class="mm-fab" type="button" aria-label="Filter" @click="sheetOpen = true">
            <i class="bi bi-funnel-fill"></i>
            <span v-if="jumlahFilter" class="mm-fab__badge">{{ jumlahFilter }}</span>
        </button>

        <div v-loading="loading" class="mm-grid">
            <div v-for="m in list" :key="m.id" class="mm-card" :class="{ off: m.status !== 'AKTIF', maint: m.maintenance }">
                <div class="mm-card__top">
                    <span class="mm-card__ico" :class="m.role === 'KANDIDAT' ? 'is-kand' : 'is-adm'"><i class="bi" :class="ikonKelas(m.ikon)"></i></span>
                    <div class="mm-card__id">
                        <strong>{{ m.nama }}</strong>
                        <code>{{ m.jenisPage }}</code>
                    </div>
                    <span class="wca-badge" :class="m.role === 'KANDIDAT' ? 'wca-b--amber' : 'wca-b--indigo'">{{ m.role === 'KANDIDAT' ? 'Kandidat' : 'Admin' }}</span>
                </div>

                <!-- Mode pemeliharaan: menutup seluruh route menu ini tanpa deploy. -->
                <div class="mm-maint" :class="{ on: m.maintenance }">
                    <div class="mm-maint__l">
                        <i class="bi" :class="m.maintenance ? 'bi-cone-striped' : 'bi-tools'"></i>
                        <div>
                            <div class="mm-maint__t">{{ m.maintenance ? 'Sedang dipelihara' : 'Mode pemeliharaan' }}</div>
                            <div class="mm-maint__s">{{ m.maintenance ? (m.role === 'KANDIDAT' ? 'Ditutup — halaman penuh (standalone)' : 'Ditutup — tampil di dalam shell') : 'Nonaktif, halaman berjalan normal' }}</div>
                        </div>
                    </div>
                    <el-switch :model-value="m.maintenance" @change="(v) => setMaintenance(m, v)" />
                </div>
                <div class="mm-card__meta">
                    <span v-if="m.header"><i class="bi bi-folder2"></i> {{ m.header }}</span>
                    <span v-if="m.url"><i class="bi bi-link-45deg"></i> {{ m.url }}</span>
                    <span><i class="bi bi-sort-numeric-down"></i> urutan {{ m.urutan }}</span>
                    <span :class="m.dipakai ? 'is-used' : ''"><i class="bi bi-people"></i> {{ m.dipakai }} pengguna</span>
                </div>
                <div class="mm-card__foot">
                    <el-switch :model-value="m.status === 'AKTIF'" @change="(v) => setStatus(m, v)" />
                    <div class="mm-card__act">
                        <button class="wca-iconbtn" title="Ubah" @click="openEdit(m)"><i class="bi bi-pencil"></i></button>
                        <button class="wca-iconbtn wca-iconbtn--danger" title="Hapus" @click="askRemove(m)"><i class="bi bi-trash"></i></button>
                    </div>
                </div>
            </div>
            <div v-if="!loading && !list.length" class="wca-empty">
                <i class="bi bi-list-nested"></i>
                <h4>{{ adaFilter ? 'Tidak ada menu yang cocok' : 'Belum ada menu' }}</h4>
            </div>
        </div>

        <AdminModal :busy="saving"
            :show="show" :title="editingId ? 'Ubah Menu' : 'Tambah Menu'"
            subtitle="Halaman yang dapat diberi hak akses" icon="bi-list-nested"
            :save-label="editingId ? 'Perbarui' : 'Simpan Menu'" @close="show = false" @save="save"
        >
            <div class="wca-fsection">
                <div class="wca-fsection__label"><i class="bi bi-key"></i> Identitas Halaman</div>
                <div class="wca-form">
                    <div class="wca-frow">
                        <div>
                            <label class="wca-field-lbl">Kunci Halaman (Jenis Page)</label>
                            <el-input v-model="form.jenisPage" :disabled="!!editingId" placeholder="mis. masterAlurPage" />
                            <small class="mm-hint">{{ editingId ? 'Tidak bisa diubah — dipakai middleware route.' : 'Huruf/angka tanpa spasi, akhiri "Page".' }}</small>
                        </div>
                        <div>
                            <label class="wca-field-lbl">Nama Menu</label>
                            <el-input v-model="form.nama" placeholder="mis. Master Tahapan Seleksi" />
                        </div>
                    </div>
                    <div class="wca-frow">
                        <div>
                            <label class="wca-field-lbl">Grup (Header)</label>
                            <el-input v-model="form.header" placeholder="mis. Master Data" />
                        </div>
                        <div>
                            <label class="wca-field-lbl">Untuk Peran</label>
                            <el-select v-model="form.role" style="width:100%">
                                <el-option label="Admin" value="ADMIN" />
                                <el-option label="Kandidat" value="KANDIDAT" />
                            </el-select>
                        </div>
                    </div>
                    <!-- Tingkat kedua sidebar. Dibiarkan kosong = menu duduk
                         langsung di bawah headernya, seperti sebelum kolom ini
                         ada — jadi mengabaikannya tidak merusak apa pun.

                         Ditawarkan sebagai pilihan dari sub-grup yang sudah
                         dipakai header yang sama, tapi tetap bisa diketik baru:
                         mengetik ulang "Lowongan & MPP" dengan spasi berbeda
                         menghasilkan dua sub-grup yang terlihat kembar. -->
                    <div class="wca-frow">
                        <div>
                            <label class="wca-field-lbl">Sub-grup</label>
                            <el-select
                                v-model="form.grup"
                                filterable allow-create default-first-option clearable
                                placeholder="Kosongkan bila tanpa sub-grup"
                                style="width:100%"
                            >
                                <el-option v-for="g in grupPilihan" :key="g" :label="g" :value="g" />
                            </el-select>
                            <small class="mm-hint">Tingkat kedua di dalam grup, mis. “Phone Screening”.</small>
                        </div>
                        <div>
                            <label class="wca-field-lbl">Urutan Sub-grup</label>
                            <el-input-number v-model="form.urutanGrup" :min="0" :max="999" controls-position="right" style="width:100%" />
                            <small class="mm-hint">Urutan sub-grupnya sendiri, bukan menunya.</small>
                        </div>
                    </div>
                    <div class="wca-frow">
                        <div><label class="wca-field-lbl">URL</label><el-input v-model="form.url" placeholder="/master-alur" /></div>
                        <div><label class="wca-field-lbl">Urutan</label><el-input-number v-model="form.urutan" :min="0" :max="9999" controls-position="right" style="width:100%" /></div>
                    </div>
                    <div><label class="wca-field-lbl">Ikon</label><IconPicker v-model="form.ikon" /></div>
                </div>
            </div>
        </AdminModal>

        <ConfirmModal
            :show="delShow" title="Hapus Menu" :busy="deleting" confirm-label="Ya, Hapus"
            note="Menu yang masih dipegang pengguna tidak bisa dihapus." @cancel="delShow = false" @confirm="confirmDelete"
        >
            Yakin ingin menghapus menu <strong>{{ delTarget?.nama }}</strong>?
        </ConfirmModal>

        <transition name="wca-toast"><div v-if="toast" class="wca-toast"><i class="bi bi-check-circle-fill"></i> {{ toast }}</div></transition>
    </div>
</template>

<script>
import axios from 'axios';
import { Head } from '@inertiajs/vue3';
import AdminModal from '@career/AdminModal.vue';
import ConfirmModal from '@career/ConfirmModal.vue';
import IconPicker from '@career/IconPicker.vue';
import { ingatModal } from '@utils/ingatModal';

const API = '/api/v1/master-menu';
const CFG = { headers: { Accept: 'application/json' } };
const KOSONG = { jenisPage: '', nama: '', header: '', subHeader: '', grup: '', urutanGrup: null, ikon: '', url: '', role: 'ADMIN', urutan: 0 };

export default {
    components: { Head, AdminModal, ConfirmModal, IconPicker },
    // Modal di halaman ini selamat dari refresh — lihat @utils/ingatModal.
    mixins: [ingatModal('admin/akses/masterMenu')],
    data() {
        return {
            list: [], loading: false, show: false, editingId: null,
            form: { ...KOSONG },
            filters: { q: '', role: null, status: null },
            sheetOpen: false, cariTimer: null,
            delShow: false, delTarget: null, deleting: false, saving: false, toast: '', tm: null,
        };
    },
    computed: {
        adaFilter() { return !!(this.filters.q || this.filters.role || this.filters.status); },
        jumlahFilter() { return [this.filters.q, this.filters.role, this.filters.status].filter(Boolean).length; },
        /**
         * Sub-grup yang sudah dipakai HEADER yang sedang dipilih.
         *
         * Dipersempit ke headernya, bukan seluruh daftar: "Talent Pool" milik
         * Seleksi tidak ada gunanya ditawarkan saat headernya Master Data, dan
         * menawarkannya justru mengundang sub-grup bernama sama muncul di dua
         * tempat yang tidak berhubungan.
         */
        grupPilihan() {
            const h = (this.form.header || '').trim().toLowerCase();
            const cocok = this.list.filter(
                (m) => m.grup && (!h || (m.header || '').trim().toLowerCase() === h),
            );

            return [...new Set(cocok.map((m) => m.grup))].sort((a, b) => a.localeCompare(b, 'id'));
        },
    },
    mounted() { this.load(); },
    methods: {
        ikonKelas(i) { return (i || 'bi-dot').replace(/^bi\s+/, ''); },
        async load() {
            this.loading = true;
            try {
                const params = { q: this.filters.q || undefined, role: this.filters.role || undefined, status: this.filters.status || undefined };
                this.list = (await axios.get(API, { ...CFG, params })).data.result || [];
            } catch (e) { this.notice('Gagal memuat data menu.'); }
            finally { this.loading = false; }
        },
        cariDebounce() { if (this.cariTimer) clearTimeout(this.cariTimer); this.cariTimer = setTimeout(() => this.load(), 400); },
        resetFilter() { this.filters = { q: '', role: null, status: null }; this.load(); },
        openCreate() { this.editingId = null; this.form = { ...KOSONG }; this.show = true; },
        openEdit(m) {
            this.editingId = m.id;
            this.form = {
                jenisPage: m.jenisPage, nama: m.nama, header: m.header || '', subHeader: m.subHeader || '',
                grup: m.grup || '', urutanGrup: m.urutanGrup ?? null,
                ikon: m.ikon || '', url: m.url || '', role: m.role, urutan: m.urutan,
            };
            this.show = true;
        },
        async save() {
            if (this.saving) return;
            if (!this.form.nama.trim()) return this.notice('Nama menu wajib diisi.');
            if (!this.editingId && !/^[A-Za-z][A-Za-z0-9_]*$/.test(this.form.jenisPage)) return this.notice('Kunci halaman tidak valid (huruf/angka tanpa spasi).');
            this.saving = true;
            try {
                if (this.editingId) { await axios.put(`${API}/${this.editingId}`, this.form, CFG); this.notice('Menu diperbarui.'); }
                else { await axios.post(API, this.form, CFG); this.notice('Menu ditambahkan.'); }
                this.show = false;
                await this.load();
            } catch (e) { this.notice(e.response?.data?.message || 'Gagal menyimpan.'); }
            finally { this.saving = false; }
        },
        async setStatus(m, v) {
            const prev = m.status;
            m.status = v ? 'AKTIF' : 'NONAKTIF';
            try { await axios.patch(`${API}/${m.id}/toggle`, { aktif: v }, CFG); this.notice(`Menu "${m.nama}" ${v ? 'diaktifkan' : 'dinonaktifkan'}.`); }
            catch (e) { m.status = prev; this.notice('Gagal mengubah status.'); }
        },
        /** Nyalakan/matikan mode pemeliharaan menu (menutup route-nya seketika). */
        async setMaintenance(m, v) {
            const prev = m.maintenance;
            m.maintenance = v;
            try {
                const r = await axios.patch(`${API}/${m.id}/maintenance`, { maintenance: v }, CFG);
                this.notice(r.data?.message || 'Mode pemeliharaan diperbarui.');
            } catch (e) { m.maintenance = prev; this.notice(e.response?.data?.message || 'Gagal mengubah mode pemeliharaan.'); }
        },
        askRemove(m) { this.delTarget = m; this.delShow = true; },
        async confirmDelete() {
            if (this.deleting || !this.delTarget) return;
            this.deleting = true;
            try {
                await axios.delete(`${API}/${this.delTarget.id}`, CFG);
                this.notice('Menu dihapus.');
                this.delShow = false; this.delTarget = null;
                await this.load();
            } catch (e) { this.notice(e.response?.data?.message || 'Gagal menghapus.'); }
            finally { this.deleting = false; }
        },
        notice(x) { this.toast = x; if (this.tm) clearTimeout(this.tm); this.tm = setTimeout(() => (this.toast = ''), 3000); },
    },
};
</script>

<style scoped>
.mm-filter { background: #fff; border: 1px solid rgba(15,23,42,.08); border-radius: 16px; padding: .9rem 1rem 1rem; margin-bottom: 1rem; box-shadow: 0 8px 24px rgba(15,23,42,.04); }
.mm-filter__head { display: flex; align-items: center; justify-content: space-between; margin-bottom: .65rem; }
.mm-filter__title { display: inline-flex; align-items: center; gap: .45rem; font-size: 12px; font-weight: 800; letter-spacing: .06em; text-transform: uppercase; color: #4338ca; }
.mm-filter__act { display: inline-flex; gap: .4rem; }
.mm-filter__reset { display: inline-flex; align-items: center; gap: .3rem; border: 1px solid rgba(79,70,229,.25); background: #eef2ff; color: #4338ca; font-size: 11.5px; font-weight: 700; border-radius: 9px; padding: 4px 10px; cursor: pointer; }
.mm-filter__close { display: none; border: none; background: transparent; color: #64748b; font-size: 15px; cursor: pointer; }
.mm-filter__grid { display: grid; grid-template-columns: minmax(220px,1.6fr) 1fr 1fr; gap: .7rem; align-items: end; }
.mm-sheetbg { display: none; }
.mm-fab { display: none; position: fixed; right: 18px; bottom: 20px; z-index: 70; width: 52px; height: 52px; border-radius: 50%; border: none; background: linear-gradient(135deg,#8b5cf6,#6366f1); color: #fff; font-size: 19px; cursor: pointer; box-shadow: 0 12px 28px rgba(99,102,241,.45); }
.mm-fab__badge { position: absolute; top: -4px; right: -4px; min-width: 19px; height: 19px; border-radius: 999px; background: #ef4444; color: #fff; font-size: 10.5px; font-weight: 800; display: grid; place-items: center; padding: 0 5px; border: 2px solid #fff; }

.mm-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: .85rem; }
.mm-card { background: #fff; border: 1px solid rgba(15,23,42,.08); border-radius: 15px; padding: .9rem 1rem; display: flex; flex-direction: column; gap: .55rem; transition: box-shadow .16s; }
.mm-card:hover { box-shadow: 0 10px 26px rgba(15,23,42,.07); }
.mm-card.off { opacity: .58; }
.mm-card__top { display: flex; align-items: center; gap: .6rem; }
.mm-card__ico { flex: none; width: 38px; height: 38px; border-radius: 11px; display: grid; place-items: center; font-size: 16px; }
.mm-card__ico.is-adm { background: rgba(99,102,241,.12); color: #4f46e5; }
.mm-card__ico.is-kand { background: rgba(217,119,6,.12); color: #b45309; }
.mm-card__id { flex: 1; min-width: 0; display: flex; flex-direction: column; }
.mm-card__id strong { font-size: 13.5px; color: #1e2447; }
.mm-card__id code { font-size: 11px; color: #64748b; background: #f1f5f9; border-radius: 5px; padding: 1px 6px; width: fit-content; margin-top: 2px; }
.mm-card__meta { display: flex; flex-wrap: wrap; gap: .5rem .9rem; font-size: 11.5px; color: #64748b; }
.mm-card__meta .is-used { color: #047857; font-weight: 700; }
.mm-card__foot { display: flex; align-items: center; justify-content: space-between; padding-top: .5rem; border-top: 1px dashed rgba(11,16,51,.08); }
.mm-card__act { display: inline-flex; gap: .35rem; }
.mm-hint { display: block; margin-top: .3rem; font-size: 11px; color: #94a3b8; }

/* Blok mode pemeliharaan */
.mm-card.maint { border-color: rgba(245, 158, 11, .5); box-shadow: 0 8px 22px rgba(217, 119, 6, .1); }
.mm-maint { display: flex; align-items: center; justify-content: space-between; gap: .6rem; padding: .55rem .65rem; border-radius: 11px; border: 1px dashed rgba(15, 23, 42, .12); background: #f8fafc; }
.mm-maint.on { border-style: solid; border-color: rgba(245, 158, 11, .45); background: #fffbeb; }
.mm-maint__l { display: flex; align-items: center; gap: .5rem; min-width: 0; }
.mm-maint__l > i { font-size: 16px; color: #94a3b8; flex: none; }
.mm-maint.on .mm-maint__l > i { color: #b45309; }
.mm-maint__t { font-size: 12px; font-weight: 700; color: #475569; }
.mm-maint.on .mm-maint__t { color: #b45309; }
.mm-maint__s { font-size: 10.5px; color: #94a3b8; }

@media (max-width: 640px) {
    .mm-filter { display: none; }
    .mm-filter.is-open { display: block; position: fixed; left: 0; right: 0; bottom: 0; z-index: 80; margin: 0; border-radius: 18px 18px 0 0; max-height: 78vh; overflow-y: auto; box-shadow: 0 -18px 40px rgba(15,23,42,.25); }
    .mm-filter__grid { grid-template-columns: 1fr; }
    .mm-filter__close { display: inline-flex; }
    .mm-fab { display: grid; place-items: center; }
    .mm-sheetbg { display: block; position: fixed; inset: 0; z-index: 75; background: rgba(15,23,42,.45); }
}
</style>
