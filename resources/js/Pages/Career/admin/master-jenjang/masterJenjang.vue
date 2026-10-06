<!-- WEB CAREER — Master Jenjang Pendidikan. Data via web route + ResponseHelper (axios). -->
<template>
    <Head title="Master Jenjang Pendidikan" />
    <div class="wca">
        <div class="wca-phead">
            <div>
                <h1>Master Jenjang Pendidikan</h1>
                <p>Tingkat pendidikan (SD, SMP, SMA, SMK, D3, D4, S1, S2, S3, …). Dipakai kandidat di formulir &amp; menggerakkan cascade <b>Jenjang → Jenis Institusi → Nama Kampus</b>.</p>
            </div>
            <div class="wca-phead__actions">
                <button class="wca-btn wca-btn--primary" @click="openCreate"><i class="bi bi-plus-lg"></i> Jenjang Baru</button>
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
                                <th style="width: 90px">Urutan</th>
                                <th>Kode</th>
                                <th>Nama Jenjang</th>
                                <th>Dibuat Oleh</th>
                                <th>Status</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="j in filtered" :key="j.id">
                                <td><span class="wca-badge wca-b--slate">{{ j.urutan }}</span></td>
                                <td><code class="jjg-code">{{ j.kode }}</code></td>
                                <td><strong>{{ j.nama }}</strong></td>
                                <td><AuditStamp :by="j.createdBy" :at="j.createdAt" /></td>
                                <td>
                                    <div class="jjg-status">
                                        <el-switch :model-value="j.status === 'AKTIF'" @change="(v) => setStatus(j, v)" />
                                        <span class="jjg-status__lbl" :class="j.status === 'AKTIF' ? 'is-on' : 'is-off'">{{ j.status === 'AKTIF' ? 'Aktif' : 'Nonaktif' }}</span>
                                    </div>
                                </td>
                                <td>
                                    <div class="jjg-actions">
                                        <button class="wca-iconbtn" title="Ubah" @click="openEdit(j)"><i class="bi bi-pencil"></i></button>
                                        <button class="wca-iconbtn wca-iconbtn--danger" title="Hapus" @click="askRemove(j)"><i class="bi bi-trash"></i></button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="!loading && !filtered.length">
                                <td colspan="6"><div class="wca-empty"><i class="bi bi-diagram-3"></i><h4>Tidak ada jenjang cocok</h4></div></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <AdminModal :busy="saving" :show="show" :title="editingId ? 'Ubah Jenjang' : 'Jenjang Baru'"
            subtitle="Tingkat pendidikan" icon="bi-diagram-3"
            :save-label="editingId ? 'Perbarui' : 'Simpan Jenjang'" @close="show = false" @save="save">
            <div class="wca-fsection">
                <div class="wca-fsection__label"><i class="bi bi-diagram-3"></i> Data Jenjang</div>
                <div class="wca-form">
                    <div class="wca-frow">
                        <div>
                            <label class="wca-field-lbl">Nama Jenjang</label>
                            <el-input v-model="form.nama" placeholder="mis. S1 (Sarjana)" />
                        </div>
                        <div>
                            <label class="wca-field-lbl">Urutan</label>
                            <el-input-number v-model="form.urutan" :min="0" :step="10" controls-position="right" style="width: 100%" />
                        </div>
                    </div>
                </div>
            </div>
        </AdminModal>

        <ConfirmModal :show="delShow" title="Hapus Jenjang" :busy="deleting" confirm-label="Ya, Hapus Jenjang"
            note="Jenjang akan dihapus permanen. Pastikan tidak sedang dipakai di formulir." @cancel="delShow = false" @confirm="confirmDelete">
            Yakin ingin menghapus jenjang <strong>{{ delTarget?.nama }}</strong>?
        </ConfirmModal>

        <transition name="wca-toast"><div v-if="toast" class="wca-toast"><i class="bi bi-check-circle-fill"></i> {{ toast }}</div></transition>
    </div>
</template>

<script>
import axios from 'axios';
import { Head } from '@inertiajs/vue3';
import AdminModal from '@career/AdminModal.vue';
import ConfirmModal from '@career/ConfirmModal.vue';
import AuditStamp from '@career/AuditStamp.vue';
import { ingatModal } from '@utils/ingatModal';

const API = '/api/v1/master-jenjang';
const CFG = { headers: { Accept: 'application/json' } };

export default {
    components: { Head, AdminModal, ConfirmModal, AuditStamp },
    // Modal di halaman ini selamat dari refresh — lihat @utils/ingatModal.
    mixins: [ingatModal('admin/master-jenjang/masterJenjang')],
    data() {
        return {
            list: [], loading: false, q: '',
            show: false, editingId: null, saving: false,
            form: { nama: '', urutan: 0 },
            delShow: false, delTarget: null, deleting: false,
            toast: '', tm: null,
        };
    },
    computed: {
        filtered() {
            const s = this.q.trim().toLowerCase();
            if (!s) return this.list;
            return this.list.filter((j) => (`${j.kode} ${j.nama}`).toLowerCase().includes(s));
        },
    },
    mounted() { this.load(); },
    methods: {
        async load() {
            this.loading = true;
            try { const res = await axios.get(API, CFG); this.list = res.data.result || []; }
            catch (e) { this.notice('Gagal memuat data jenjang.'); }
            finally { this.loading = false; }
        },
        openCreate() { this.editingId = null; this.form = { nama: '', urutan: (this.list.length + 1) * 10 }; this.show = true; },
        openEdit(j) { this.editingId = j.id; this.form = { nama: j.nama, urutan: j.urutan }; this.show = true; },
        async save() {
            if (this.saving) return;
            if (!this.form.nama.trim()) return this.notice('Nama jenjang wajib diisi.');
            this.saving = true;
            const payload = { nama: this.form.nama, urutan: this.form.urutan };
            try {
                if (this.editingId) { await axios.put(`${API}/${this.editingId}`, payload, CFG); this.notice('Jenjang diperbarui.'); }
                else { await axios.post(API, payload, CFG); this.notice('Jenjang berhasil ditambahkan.'); }
                this.show = false; await this.load();
            } catch (e) { this.notice(e.response?.data?.message || 'Gagal menyimpan jenjang.'); }
            finally { this.saving = false; }
        },
        async setStatus(j, v) {
            const prev = j.status; j.status = v ? 'AKTIF' : 'NONAKTIF';
            try { await axios.patch(`${API}/${j.id}/toggle`, { aktif: v }, CFG); this.notice(`Jenjang "${j.nama}" ${v ? 'diaktifkan' : 'dinonaktifkan'}.`); }
            catch (e) { j.status = prev; this.notice(e.response?.data?.message || 'Gagal mengubah status.'); }
        },
        askRemove(j) { this.delTarget = j; this.delShow = true; },
        async confirmDelete() {
            if (this.deleting || !this.delTarget) return;
            const j = this.delTarget; this.deleting = true;
            try { await axios.delete(`${API}/${j.id}`, CFG); this.notice('Jenjang dihapus.'); this.delShow = false; this.delTarget = null; await this.load(); }
            catch (e) { this.notice(e.response?.data?.message || 'Gagal menghapus jenjang.'); }
            finally { this.deleting = false; }
        },
        notice(m) { this.toast = m; if (this.tm) clearTimeout(this.tm); this.tm = setTimeout(() => (this.toast = ''), 3000); },
    },
};
</script>

<style scoped>
.jjg-code { font-family: 'JetBrains Mono', monospace; font-size: 12px; background: #eef0f7; color: #4f46e5; padding: 2px 8px; border-radius: 6px; }
.jjg-status { display: flex; align-items: center; gap: 10px; --el-switch-on-color: #059669; }
.jjg-status__lbl { font-size: 12px; font-weight: 700; }
.jjg-status__lbl.is-on { color: #059669; }
.jjg-status__lbl.is-off { color: #94a3b8; }
.jjg-actions { display: flex; gap: 0.35rem; justify-content: flex-end; }
@media (max-width: 640px) { .jjg-status__lbl { display: none; } }
</style>
