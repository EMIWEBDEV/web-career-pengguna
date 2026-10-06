<!-- WEB CAREER — Master Kampus/Institusi. PAGINASI + FILTER server-side (data ratusan ribu). -->
<template>
    <Head title="Master Kampus" />
    <div class="wca">
        <div class="wca-phead">
            <div>
                <h1>Master Kampus / Institusi</h1>
                <p>Daftar perguruan tinggi &amp; sekolah (impor PDDIKTI/Dapodik + dunia). Dipakai kandidat di formulir lewat cascade <b>Jenjang → Jenis → Nama Kampus</b>. Data besar — gunakan filter &amp; pencarian.</p>
            </div>
            <div class="wca-phead__actions">
                <button class="wca-btn wca-btn--primary" @click="openCreate"><i class="bi bi-plus-lg"></i> Kampus Baru</button>
            </div>
        </div>

        <div class="wca-toolbar kmp-toolbar">
            <div class="wca-search2">
                <i class="bi bi-search"></i>
                <input v-model="filters.q" type="text" placeholder="Cari nama kampus / sekolah…" @input="debouncedReload" />
            </div>
            <el-select v-model="filters.jenis" placeholder="Semua Jenis" clearable filterable class="kmp-fsel" @change="reload">
                <el-option v-for="o in jenisOpsi" :key="o.kode" :label="o.nama" :value="o.kode" />
            </el-select>
            <el-select v-model="filters.jenjang" placeholder="Semua Jenjang" clearable class="kmp-fsel" @change="reload">
                <el-option v-for="o in jenjangOpsi" :key="o.kode" :label="o.nama" :value="o.kode" />
            </el-select>
            <el-select v-model="filters.kepemilikan" placeholder="Semua" clearable class="kmp-fsel kmp-fsel--sm" @change="reload">
                <el-option label="Negeri" value="Negeri" />
                <el-option label="Swasta" value="Swasta" />
            </el-select>
        </div>

        <div class="wca-card">
            <div class="wca-card__body--flush">
                <div v-loading="loading" class="wca-tablewrap">
                    <table class="wca-table">
                        <thead>
                            <tr>
                                <th>Nama</th>
                                <th>Jenis</th>
                                <th>Jenjang</th>
                                <th>Kepemilikan</th>
                                <th>Lokasi</th>
                                <th>Status</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="k in list" :key="k.id">
                                <td>
                                    <strong>{{ k.nama }}</strong>
                                    <span v-if="k.singkatan" class="wca-badge wca-b--slate" style="margin-left: 0.2rem">{{ k.singkatan }}</span>
                                    <div v-if="k.negara && k.negara !== 'Indonesia'" class="kmp-sub"><i class="bi bi-globe2"></i> {{ k.negara }}</div>
                                </td>
                                <td><span v-if="k.jenis" class="wca-badge wca-b--green">{{ k.jenis }}</span><span v-else class="kmp-muted">—</span></td>
                                <td><span v-if="k.jenjang" class="wca-badge wca-b--slate">{{ k.jenjang }}</span><span v-else class="kmp-muted">—</span></td>
                                <td>
                                    <span v-if="k.kepemilikan" class="wca-badge" :class="k.kepemilikan === 'Negeri' ? 'wca-b--green' : 'wca-b--amber'">{{ k.kepemilikan }}</span>
                                    <span v-else class="kmp-muted">—</span>
                                </td>
                                <td>
                                    <template v-if="k.kota || k.provinsi"><i class="bi bi-geo-alt" style="color: var(--indigo)"></i> {{ [k.kota, k.provinsi].filter(Boolean).join(', ') }}</template>
                                    <span v-else class="kmp-muted">—</span>
                                </td>
                                <td>
                                    <div class="kmp-status">
                                        <el-switch :model-value="k.status === 'AKTIF'" @change="(v) => setStatus(k, v)" />
                                        <span class="kmp-status__lbl" :class="k.status === 'AKTIF' ? 'is-on' : 'is-off'">{{ k.status === 'AKTIF' ? 'Aktif' : 'Nonaktif' }}</span>
                                    </div>
                                </td>
                                <td>
                                    <div class="kmp-actions">
                                        <button class="wca-iconbtn" title="Ubah" @click="openEdit(k)"><i class="bi bi-pencil"></i></button>
                                        <button class="wca-iconbtn wca-iconbtn--danger" title="Hapus" @click="askRemove(k)"><i class="bi bi-trash"></i></button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="!loading && !list.length">
                                <td colspan="7"><div class="wca-empty"><i class="bi bi-mortarboard"></i><h4>Tidak ada kampus cocok</h4></div></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="kmp-pager">
                    <span class="kmp-pager__info">Total <b>{{ total.toLocaleString('id-ID') }}</b> institusi</span>
                    <el-pagination
                        background layout="prev, pager, next, sizes"
                        :total="total" :current-page="page" :page-size="perPage" :page-sizes="[25, 50, 100]"
                        @current-change="onPage" @size-change="onSize" />
                </div>
            </div>
        </div>

        <AdminModal :busy="saving" :show="show" :title="editingId ? 'Ubah Kampus' : 'Kampus Baru'"
            subtitle="Perguruan tinggi / sekolah" icon="bi-mortarboard"
            :save-label="editingId ? 'Perbarui' : 'Simpan Kampus'" @close="show = false" @save="save">
            <div class="wca-fsection">
                <div class="wca-fsection__label"><i class="bi bi-mortarboard"></i> Data Institusi</div>
                <div class="wca-form">
                    <div class="wca-frow">
                        <div><label class="wca-field-lbl">Nama</label><el-input v-model="form.nama" placeholder="Universitas Sriwijaya" /></div>
                        <div><label class="wca-field-lbl">Singkatan</label><el-input v-model="form.singkatan" placeholder="UNSRI" /></div>
                    </div>
                    <div class="wca-frow">
                        <div>
                            <label class="wca-field-lbl">Jenis Institusi</label>
                            <el-select v-model="form.jenis" placeholder="Pilih jenis" filterable clearable style="width: 100%">
                                <el-option v-for="o in jenisOpsi" :key="o.kode" :label="o.nama" :value="o.kode" />
                            </el-select>
                        </div>
                        <div>
                            <label class="wca-field-lbl">Jenjang (untuk sekolah)</label>
                            <el-select v-model="form.jenjang" placeholder="Pilih jenjang" clearable style="width: 100%">
                                <el-option v-for="o in jenjangOpsi" :key="o.kode" :label="o.nama" :value="o.kode" />
                            </el-select>
                        </div>
                    </div>
                    <div class="wca-frow">
                        <div>
                            <label class="wca-field-lbl">Kepemilikan</label>
                            <el-select v-model="form.kepemilikan" placeholder="Negeri / Swasta" clearable style="width: 100%">
                                <el-option label="Negeri" value="Negeri" />
                                <el-option label="Swasta" value="Swasta" />
                            </el-select>
                        </div>
                        <div><label class="wca-field-lbl">Akreditasi</label><RefSelect type="akreditasi" v-model="form.akreditasi" clearable placeholder="Pilih akreditasi" /></div>
                    </div>
                    <div class="wca-frow">
                        <div><label class="wca-field-lbl">Kota / Kabupaten</label><el-input v-model="form.kota" placeholder="Palembang" /></div>
                        <div><label class="wca-field-lbl">Provinsi</label><el-input v-model="form.provinsi" placeholder="Sumatera Selatan" /></div>
                    </div>
                    <div class="wca-frow">
                        <div><label class="wca-field-lbl">Negara</label><el-input v-model="form.negara" placeholder="Indonesia" /></div>
                        <div><label class="wca-field-lbl">Website</label><el-input v-model="form.website" placeholder="https://…" /></div>
                    </div>
                </div>
            </div>
        </AdminModal>

        <ConfirmModal :show="delShow" title="Hapus Kampus" :busy="deleting" confirm-label="Ya, Hapus Kampus"
            note="Data kampus akan dihapus permanen dari sistem." @cancel="delShow = false" @confirm="confirmDelete">
            Yakin ingin menghapus kampus <strong>{{ delTarget?.nama }}</strong>?
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

const API = '/api/v1/master-kampus';
const CFG = { headers: { Accept: 'application/json' } };
const blank = () => ({ nama: '', singkatan: '', jenis: '', jenjang: '', kepemilikan: '', kota: '', provinsi: '', negara: '', akreditasi: '', website: '', npsn: '' });

export default {
    components: { Head, AdminModal, ConfirmModal, RefSelect },
    // Modal di halaman ini selamat dari refresh — lihat @utils/ingatModal.
    mixins: [ingatModal('admin/master-kampus/masterKampus')],
    data() {
        return {
            list: [], total: 0, page: 1, perPage: 25, loading: false,
            filters: { q: '', jenis: '', jenjang: '', kepemilikan: '' },
            jenisOpsi: [], jenjangOpsi: [],
            show: false, editingId: null, saving: false, form: blank(),
            delShow: false, delTarget: null, deleting: false,
            toast: '', tm: null, dtm: null,
        };
    },
    mounted() { this.loadRefs(); this.load(); },
    methods: {
        async load() {
            this.loading = true;
            try {
                const params = { ...this.filters, page: this.page, perPage: this.perPage };
                const res = await axios.get(API, { ...CFG, params });
                const r = res.data.result || {};
                this.list = r.rows || [];
                this.total = r.total || 0;
            } catch (e) { this.notice('Gagal memuat data kampus.'); }
            finally { this.loading = false; }
        },
        async loadRefs() {
            try {
                const [ji, jj] = await Promise.all([
                    axios.get('/api/v1/pendidikan/jenis-institusi', CFG),
                    axios.get('/api/v1/pendidikan/jenjang', CFG),
                ]);
                this.jenisOpsi = ji.data.result || [];
                this.jenjangOpsi = jj.data.result || [];
            } catch (e) { /* filter opsional */ }
        },
        reload() { this.page = 1; this.load(); },
        debouncedReload() { if (this.dtm) clearTimeout(this.dtm); this.dtm = setTimeout(() => this.reload(), 400); },
        onPage(p) { this.page = p; this.load(); },
        onSize(s) { this.perPage = s; this.page = 1; this.load(); },
        openCreate() { this.editingId = null; this.form = blank(); this.show = true; },
        openEdit(k) {
            this.editingId = k.id;
            this.form = { nama: k.nama, singkatan: k.singkatan || '', jenis: k.jenis || '', jenjang: k.jenjang || '', kepemilikan: k.kepemilikan || '', kota: k.kota || '', provinsi: k.provinsi || '', negara: k.negara || '', akreditasi: k.akreditasi || '', website: k.website || '', npsn: k.npsn || '' };
            this.show = true;
        },
        async save() {
            if (this.saving) return;
            if (!this.form.nama.trim()) return this.notice('Nama kampus wajib diisi.');
            this.saving = true;
            try {
                if (this.editingId) { await axios.put(`${API}/${this.editingId}`, this.form, CFG); this.notice('Kampus diperbarui.'); }
                else { await axios.post(API, this.form, CFG); this.notice('Kampus berhasil ditambahkan.'); }
                this.show = false; await this.load();
            } catch (e) { this.notice(e.response?.data?.message || 'Gagal menyimpan kampus.'); }
            finally { this.saving = false; }
        },
        async setStatus(k, v) {
            const prev = k.status; k.status = v ? 'AKTIF' : 'NONAKTIF';
            try { await axios.patch(`${API}/${k.id}/toggle`, { aktif: v }, CFG); this.notice(`Kampus "${k.nama}" ${v ? 'diaktifkan' : 'dinonaktifkan'}.`); }
            catch (e) { k.status = prev; this.notice(e.response?.data?.message || 'Gagal mengubah status.'); }
        },
        askRemove(k) { this.delTarget = k; this.delShow = true; },
        async confirmDelete() {
            if (this.deleting || !this.delTarget) return;
            const k = this.delTarget; this.deleting = true;
            try { await axios.delete(`${API}/${k.id}`, CFG); this.notice('Kampus dihapus.'); this.delShow = false; this.delTarget = null; await this.load(); }
            catch (e) { this.notice(e.response?.data?.message || 'Gagal menghapus kampus.'); }
            finally { this.deleting = false; }
        },
        notice(m) { this.toast = m; if (this.tm) clearTimeout(this.tm); this.tm = setTimeout(() => (this.toast = ''), 3000); },
    },
};
</script>

<style scoped>
.kmp-toolbar { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; }
.kmp-fsel { width: 200px; }
.kmp-fsel--sm { width: 140px; }
.kmp-sub { font-size: 11px; color: #8b93a7; margin-top: 2px; }
.kmp-status { display: flex; align-items: center; gap: 10px; --el-switch-on-color: #059669; }
.kmp-status__lbl { font-size: 12px; font-weight: 700; }
.kmp-status__lbl.is-on { color: #059669; }
.kmp-status__lbl.is-off { color: #94a3b8; }
.kmp-muted { color: #94a3b8; }
.kmp-actions { display: flex; gap: 0.35rem; justify-content: flex-end; }
.kmp-pager { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 14px 16px; flex-wrap: wrap; border-top: 1px solid #eef0f7; }
.kmp-pager__info { font-size: 13px; color: #64748b; }
@media (max-width: 640px) { .kmp-status__lbl { display: none; } .kmp-fsel, .kmp-fsel--sm { width: 100%; } }
</style>
