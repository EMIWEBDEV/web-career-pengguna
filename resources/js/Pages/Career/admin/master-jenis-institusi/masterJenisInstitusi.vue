<!-- WEB CAREER — Master Jenis Institusi Pendidikan. Data via web route + ResponseHelper (axios). -->
<template>
    <Head title="Master Jenis Institusi" />
    <div class="wca">
        <div class="wca-phead">
            <div>
                <h1>Master Jenis Institusi</h1>
                <p>Jenis institusi pendidikan (Universitas, Politeknik, SMA, SMK, …). <b>Jenjang Berlaku</b> menentukan jenis ini muncul untuk jenjang mana pada formulir kandidat. <b>Kode</b> dipakai sebagai binding ke Master Kampus.</p>
            </div>
            <div class="wca-phead__actions">
                <button class="wca-btn wca-btn--primary" @click="openCreate"><i class="bi bi-plus-lg"></i> Jenis Baru</button>
            </div>
        </div>

        <div class="wca-toolbar">
            <div class="wca-search2">
                <i class="bi bi-search"></i>
                <input v-model="q" type="text" placeholder="Cari kode / nama…" />
            </div>
        </div>

        <div class="wca-card">
            <div class="wca-card__body--flush">
                <div v-loading="loading" class="wca-tablewrap">
                    <table class="wca-table">
                        <thead>
                            <tr>
                                <th style="width: 80px">Urutan</th>
                                <th>Nama</th>
                                <th>Kategori</th>
                                <th>Jenjang Berlaku</th>
                                <th>Status</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="j in filtered" :key="j.id">
                                <td><span class="wca-badge wca-b--slate">{{ j.urutan }}</span></td>
                                <td>
                                    <strong>{{ j.nama }}</strong>
                                    <code class="jin-code">{{ j.kode }}</code>
                                </td>
                                <td>
                                    <span class="wca-badge" :class="j.kategori === 'PT' ? 'wca-b--green' : 'wca-b--amber'">{{ j.kategori || '—' }}</span>
                                </td>
                                <td>
                                    <div class="jin-chips">
                                        <span v-for="jj in j.jenjangBerlaku" :key="jj" class="jin-chip">{{ jj }}</span>
                                        <span v-if="!j.jenjangBerlaku.length" class="jin-muted">—</span>
                                    </div>
                                </td>
                                <td>
                                    <div class="jin-status">
                                        <el-switch :model-value="j.status === 'AKTIF'" @change="(v) => setStatus(j, v)" />
                                        <span class="jin-status__lbl" :class="j.status === 'AKTIF' ? 'is-on' : 'is-off'">{{ j.status === 'AKTIF' ? 'Aktif' : 'Nonaktif' }}</span>
                                    </div>
                                </td>
                                <td>
                                    <div class="jin-actions">
                                        <button class="wca-iconbtn" title="Ubah" @click="openEdit(j)"><i class="bi bi-pencil"></i></button>
                                        <button class="wca-iconbtn wca-iconbtn--danger" title="Hapus" @click="askRemove(j)"><i class="bi bi-trash"></i></button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="!loading && !filtered.length">
                                <td colspan="6"><div class="wca-empty"><i class="bi bi-buildings"></i><h4>Tidak ada jenis institusi cocok</h4></div></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <AdminModal :busy="saving" :show="show" :title="editingId ? 'Ubah Jenis Institusi' : 'Jenis Institusi Baru'"
            subtitle="Jenis institusi pendidikan" icon="bi-buildings"
            :save-label="editingId ? 'Perbarui' : 'Simpan Jenis'" @close="show = false" @save="save">
            <div class="wca-fsection">
                <div class="wca-fsection__label"><i class="bi bi-buildings"></i> Data Jenis Institusi</div>
                <div class="wca-form">
                    <div class="wca-frow">
                        <div>
                            <label class="wca-field-lbl">Nama Jenis</label>
                            <el-input v-model="form.nama" placeholder="mis. Universitas" />
                        </div>
                        <div>
                            <label class="wca-field-lbl">Kategori</label>
                            <el-select v-model="form.kategori" placeholder="Pilih kategori" style="width: 100%">
                                <el-option label="Perguruan Tinggi (PT)" value="PT" />
                                <el-option label="Sekolah" value="SEKOLAH" />
                            </el-select>
                        </div>
                    </div>
                    <div class="wca-frow">
                        <div style="grid-column: 1 / -1">
                            <label class="wca-field-lbl">Jenjang Berlaku <span class="jin-hint">(jenis ini muncul untuk jenjang terpilih di formulir)</span></label>
                            <el-select v-model="form.jenjangBerlaku" multiple filterable placeholder="Pilih jenjang" style="width: 100%">
                                <el-option v-for="o in jenjangOpsi" :key="o.kode" :label="o.nama" :value="o.kode" />
                            </el-select>
                        </div>
                    </div>
                    <div class="wca-frow">
                        <div>
                            <label class="wca-field-lbl">Urutan</label>
                            <el-input-number v-model="form.urutan" :min="0" :step="10" controls-position="right" style="width: 100%" />
                        </div>
                    </div>
                </div>
            </div>
        </AdminModal>

        <ConfirmModal :show="delShow" title="Hapus Jenis Institusi" :busy="deleting" confirm-label="Ya, Hapus Jenis"
            note="Jenis institusi akan dihapus permanen. Baris kampus yang memakainya akan kehilangan binding." @cancel="delShow = false" @confirm="confirmDelete">
            Yakin ingin menghapus <strong>{{ delTarget?.nama }}</strong>?
        </ConfirmModal>

        <transition name="wca-toast"><div v-if="toast" class="wca-toast"><i class="bi bi-check-circle-fill"></i> {{ toast }}</div></transition>
    </div>
</template>

<script>
import axios from 'axios';
import { Head } from '@inertiajs/vue3';
import AdminModal from '@career/AdminModal.vue';
import ConfirmModal from '@career/ConfirmModal.vue';
import { ingatModal } from '@utils/ingatModal';

const API = '/api/v1/master-jenis-institusi';
const CFG = { headers: { Accept: 'application/json' } };

export default {
    components: { Head, AdminModal, ConfirmModal },
    // Modal di halaman ini selamat dari refresh — lihat @utils/ingatModal.
    mixins: [ingatModal('admin/master-jenis-institusi/masterJenisInstitusi')],
    data() {
        return {
            list: [], loading: false, q: '',
            jenjangOpsi: [],
            show: false, editingId: null, saving: false,
            form: { nama: '', kategori: 'PT', jenjangBerlaku: [], urutan: 0 },
            delShow: false, delTarget: null, deleting: false,
            toast: '', tm: null,
        };
    },
    computed: {
        filtered() {
            const s = this.q.trim().toLowerCase();
            if (!s) return this.list;
            return this.list.filter((j) => (`${j.kode} ${j.nama} ${j.kategori || ''}`).toLowerCase().includes(s));
        },
    },
    mounted() { this.load(); this.loadJenjang(); },
    methods: {
        async load() {
            this.loading = true;
            try { const res = await axios.get(API, CFG); this.list = res.data.result || []; }
            catch (e) { this.notice('Gagal memuat data jenis institusi.'); }
            finally { this.loading = false; }
        },
        async loadJenjang() {
            try { const res = await axios.get('/api/v1/pendidikan/jenjang', CFG); this.jenjangOpsi = res.data.result || []; }
            catch (e) { /* opsi jenjang opsional */ }
        },
        openCreate() { this.editingId = null; this.form = { nama: '', kategori: 'PT', jenjangBerlaku: [], urutan: (this.list.length + 1) * 10 }; this.show = true; },
        openEdit(j) { this.editingId = j.id; this.form = { nama: j.nama, kategori: j.kategori || 'PT', jenjangBerlaku: [...(j.jenjangBerlaku || [])], urutan: j.urutan }; this.show = true; },
        async save() {
            if (this.saving) return;
            if (!this.form.nama.trim()) return this.notice('Nama jenis wajib diisi.');
            this.saving = true;
            const f = this.form;
            const payload = { nama: f.nama, kategori: f.kategori, jenjangBerlaku: f.jenjangBerlaku, urutan: f.urutan };
            try {
                if (this.editingId) { await axios.put(`${API}/${this.editingId}`, payload, CFG); this.notice('Jenis institusi diperbarui.'); }
                else { await axios.post(API, payload, CFG); this.notice('Jenis institusi ditambahkan.'); }
                this.show = false; await this.load();
            } catch (e) { this.notice(e.response?.data?.message || 'Gagal menyimpan jenis institusi.'); }
            finally { this.saving = false; }
        },
        async setStatus(j, v) {
            const prev = j.status; j.status = v ? 'AKTIF' : 'NONAKTIF';
            try { await axios.patch(`${API}/${j.id}/toggle`, { aktif: v }, CFG); this.notice(`"${j.nama}" ${v ? 'diaktifkan' : 'dinonaktifkan'}.`); }
            catch (e) { j.status = prev; this.notice(e.response?.data?.message || 'Gagal mengubah status.'); }
        },
        askRemove(j) { this.delTarget = j; this.delShow = true; },
        async confirmDelete() {
            if (this.deleting || !this.delTarget) return;
            const j = this.delTarget; this.deleting = true;
            try { await axios.delete(`${API}/${j.id}`, CFG); this.notice('Jenis institusi dihapus.'); this.delShow = false; this.delTarget = null; await this.load(); }
            catch (e) { this.notice(e.response?.data?.message || 'Gagal menghapus jenis institusi.'); }
            finally { this.deleting = false; }
        },
        notice(m) { this.toast = m; if (this.tm) clearTimeout(this.tm); this.tm = setTimeout(() => (this.toast = ''), 3000); },
    },
};
</script>

<style scoped>
.jin-code { font-family: 'JetBrains Mono', monospace; font-size: 11px; background: #eef0f7; color: #4f46e5; padding: 1px 7px; border-radius: 6px; margin-left: 0.4rem; }
.jin-chips { display: flex; flex-wrap: wrap; gap: 4px; }
.jin-chip { font-size: 11px; font-weight: 700; background: rgba(99, 102, 241, 0.1); color: #4f46e5; padding: 2px 8px; border-radius: 999px; }
.jin-muted { color: #94a3b8; }
.jin-hint { font-weight: 400; color: #94a3b8; font-size: 11px; }
.jin-status { display: flex; align-items: center; gap: 10px; --el-switch-on-color: #059669; }
.jin-status__lbl { font-size: 12px; font-weight: 700; }
.jin-status__lbl.is-on { color: #059669; }
.jin-status__lbl.is-off { color: #94a3b8; }
.jin-actions { display: flex; gap: 0.35rem; justify-content: flex-end; }
@media (max-width: 640px) { .jin-status__lbl { display: none; } }
</style>
