<!-- WEB CAREER — Admin: Master Talent Acquisition (kelompok/kategori program: Rekrutmen, MT, Internship, dll). -->
<template>
    <Head title="Master Talent Acquisition" />
    <div class="wca">
        <div class="wca-phead">
            <div>
                <h1>Master Talent Acquisition</h1>
                <p>Kelompok/kategori program rekrutmen (Rekrutmen, Management Trainee, Internship, dll). Dipakai sebagai <b>kategori</b> di Master Kategori, Tahapan Seleksi & Jadwal.</p>
            </div>
            <div class="wca-phead__actions">
                <button class="wca-btn wca-btn--primary" @click="openCreate"><i class="bi bi-plus-lg"></i> Kategori Baru</button>
            </div>
        </div>

        <div class="wca-note wca-note--info">
            <i class="bi bi-info-circle"></i>
            <span>Hanya kategori berstatus <b>Aktif</b> yang muncul sebagai pilihan saat menyusun program/alur/jadwal. Nonaktifkan (bukan hapus) untuk menyembunyikan tanpa kehilangan data.</span>
        </div>

        <div class="wca-stats">
            <div class="wca-stat"><div class="wca-stat__top"><span class="wca-stat__ico"><i class="bi bi-diagram-3"></i></span></div><div class="wca-stat__num">{{ list.length }}</div><div class="wca-stat__label">Total Kategori</div></div>
            <div class="wca-stat"><div class="wca-stat__top"><span class="wca-stat__ico" style="background:rgba(16,185,129,.12);color:#059669"><i class="bi bi-check-circle"></i></span></div><div class="wca-stat__num">{{ aktif }}</div><div class="wca-stat__label">Aktif (muncul)</div></div>
            <div class="wca-stat"><div class="wca-stat__top"><span class="wca-stat__ico" style="background:rgba(148,163,184,.16);color:#475569"><i class="bi bi-eye-slash"></i></span></div><div class="wca-stat__num">{{ list.length - aktif }}</div><div class="wca-stat__label">Nonaktif (tersembunyi)</div></div>
        </div>

        <div class="wca-toolbar">
            <div class="wca-search2"><i class="bi bi-search"></i><input v-model="q" type="text" placeholder="Cari kode / nama…" /></div>
        </div>

        <div class="wca-card">
            <div class="wca-card__body--flush">
                <div class="wca-tablewrap">
                    <table class="wca-table">
                        <thead>
                            <tr>
                                <th>Kode</th>
                                <th>Nama</th>
                                <th>Deskripsi</th>
                                <th>Dibuat Oleh</th>
                                <th>Status</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="t in filtered" :key="t.id">
                                <td><span class="wca-badge wca-b--indigo">{{ t.kode }}</span></td>
                                <td><strong>{{ t.nama }}</strong></td>
                                <td><span class="ta-desc">{{ t.deskripsi }}</span></td>
                                <td><AuditStamp :by="t.createdBy" :at="t.createdAt" /></td>
                                <td>
                                    <div class="ta-status">
                                        <el-switch :model-value="t.status === 'AKTIF'" @change="(v) => setStatus(t, v)" />
                                        <span class="ta-status__lbl" :class="t.status === 'AKTIF' ? 'is-on' : 'is-off'">{{ t.status === 'AKTIF' ? 'Aktif' : 'Nonaktif' }}</span>
                                    </div>
                                </td>
                                <td>
                                    <div style="display:flex;gap:.35rem;justify-content:flex-end">
                                        <button class="wca-iconbtn" title="Ubah" @click="openEdit(t)"><i class="bi bi-pencil"></i></button>
                                        <button class="wca-iconbtn wca-iconbtn--danger" title="Hapus" @click="askRemove(t)"><i class="bi bi-trash"></i></button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="!filtered.length"><td colspan="6"><div class="wca-empty"><i class="bi bi-diagram-3"></i><h4>Tidak ada kategori cocok</h4></div></td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <AdminModal :show="show" :title="editingId ? 'Ubah Kategori' : 'Kategori Baru'" subtitle="Kelompok Talent Acquisition" icon="bi-diagram-3" :save-label="editingId ? 'Perbarui' : 'Simpan Kategori'" @close="show = false" @save="save">
            <div class="wca-fsection">
                <div class="wca-fsection__label"><i class="bi bi-diagram-3"></i> Detail Kategori</div>
                <div class="wca-form">
                    <div><label class="wca-field-lbl">Nama Kategori</label><el-input v-model="form.nama" placeholder="mis. Professional Hire" /></div>
                    <div><label class="wca-field-lbl">Deskripsi</label><el-input v-model="form.deskripsi" type="textarea" :rows="2" placeholder="Jelaskan kelompok program ini" /></div>
                </div>
            </div>
        </AdminModal>

        <ConfirmModal :show="delShow" title="Hapus Kategori" :busy="deleting" confirm-label="Ya, Hapus" note="Kategori Talent Acquisition akan dihapus permanen." @cancel="delShow = false" @confirm="confirmDelete">
            Yakin ingin menghapus kategori <strong>{{ delTarget?.nama }}</strong>?
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

const API = '/api/v1/karir/master/rich';
const KEY = 'talent';
const CFG = { headers: { Accept: 'application/json' } };

export default {
    components: { Head, AdminModal, ConfirmModal, AuditStamp },
    props: { talent: { type: Array, default: () => [] } },
    // Modal di halaman ini selamat dari refresh — lihat @utils/ingatModal.
    mixins: [ingatModal('admin/MasterTalent')],
    data() {
        return {
            list: this.talent.map((t) => ({ ...t })),
            q: '',
            show: false,
            editingId: null,
            form: { nama: '', deskripsi: '' },
            delShow: false,
            delTarget: null,
            deleting: false,
            toast: '',
            tm: null,
        };
    },
    computed: {
        aktif() { return this.list.filter((t) => t.status === 'AKTIF').length; },
        filtered() {
            const s = this.q.trim().toLowerCase();
            if (!s) return this.list;
            return this.list.filter((t) => (t.kode + ' ' + t.nama + ' ' + (t.deskripsi || '')).toLowerCase().includes(s));
        },
    },
    methods: {
        openCreate() { this.editingId = null; this.form = { nama: '', deskripsi: '' }; this.show = true; },
        openEdit(t) { this.editingId = t.id; this.form = { nama: t.nama, deskripsi: t.deskripsi || '' }; this.show = true; },
        async save() {
            if (!this.form.nama.trim()) return this.notice('Nama wajib diisi.');
            const payload = { nama: this.form.nama, deskripsi: this.form.deskripsi };
            try {
                if (this.editingId) {
                    const res = await axios.put(`${API}/${KEY}/${this.editingId}`, payload, CFG);
                    const i = this.list.findIndex((x) => x.id === this.editingId);
                    if (i >= 0) this.list.splice(i, 1, res.data.result);
                    this.notice('Kategori diperbarui.');
                } else {
                    const res = await axios.post(`${API}/${KEY}`, payload, CFG);
                    this.list.push(res.data.result);
                    this.notice('Kategori ditambahkan.');
                }
                this.show = false;
            } catch (e) {
                this.notice(e.response?.data?.message || 'Gagal menyimpan.');
            }
        },
        async setStatus(t, v) {
            const prev = t.status;
            t.status = v ? 'AKTIF' : 'NONAKTIF';
            try {
                await axios.patch(`${API}/${KEY}/${t.id}/toggle`, { aktif: v }, CFG);
                this.notice(`Kategori "${t.nama}" ${v ? 'diaktifkan' : 'dinonaktifkan'}.`);
            } catch (e) {
                t.status = prev;
                this.notice('Gagal mengubah status.');
            }
        },
        askRemove(t) { this.delTarget = t; this.delShow = true; },
        async confirmDelete() {
            if (this.deleting || !this.delTarget) return;
            const t = this.delTarget;
            this.deleting = true;
            try {
                await axios.delete(`${API}/${KEY}/${t.id}`, CFG);
                const i = this.list.findIndex((x) => x.id === t.id);
                if (i >= 0) this.list.splice(i, 1);
                this.notice('Kategori dihapus.');
                this.delShow = false;
                this.delTarget = null;
            } catch (e) {
                this.notice(e.response?.data?.message || 'Gagal menghapus.');
            } finally {
                this.deleting = false;
            }
        },
        notice(x) { this.toast = x; if (this.tm) clearTimeout(this.tm); this.tm = setTimeout(() => (this.toast = ''), 3000); },
    },
};
</script>

<style scoped>
.ta-desc { color: #5b5f86; font-size: 12.5px; }
.ta-status { display: flex; align-items: center; gap: 10px; --el-switch-on-color: #059669; }
.ta-status__lbl { font-size: 12px; font-weight: 700; }
.ta-status__lbl.is-on { color: #059669; }
.ta-status__lbl.is-off { color: #94a3b8; }
</style>
