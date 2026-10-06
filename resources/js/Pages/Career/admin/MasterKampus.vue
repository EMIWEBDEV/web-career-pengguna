<!-- WEB CAREER — Admin: Master Kampus (universitas/mitra pendidikan). CRUD, form Element Plus. -->
<template>
    <Head title="Master Kampus" />
    <div class="wca">
        <div class="wca-phead">
            <div>
                <h1>Master Kampus</h1>
                <p>Daftar universitas/mitra pendidikan. Dipakai program MT campus-hiring (dan Internship nanti) saat sumber kandidat memakai <b>Kampus</b>.</p>
            </div>
            <div class="wca-phead__actions">
                <button class="wca-btn wca-btn--primary" @click="openCreate"><i class="bi bi-plus-lg"></i> Kampus Baru</button>
            </div>
        </div>

        <div class="wca-stats">
            <div class="wca-stat"><div class="wca-stat__top"><span class="wca-stat__ico"><i class="bi bi-mortarboard"></i></span></div><div class="wca-stat__num">{{ list.length }}</div><div class="wca-stat__label">Total Kampus</div></div>
            <div class="wca-stat"><div class="wca-stat__top"><span class="wca-stat__ico" style="background:rgba(16,185,129,.12);color:#059669"><i class="bi bi-check-circle"></i></span></div><div class="wca-stat__num">{{ aktif }}</div><div class="wca-stat__label">Aktif</div></div>
            <div class="wca-stat"><div class="wca-stat__top"><span class="wca-stat__ico" style="background:rgba(99,102,241,.12);color:#4338ca"><i class="bi bi-geo-alt"></i></span></div><div class="wca-stat__num">{{ kotaCount }}</div><div class="wca-stat__label">Kota</div></div>
        </div>

        <div class="wca-toolbar">
            <div class="wca-search2"><i class="bi bi-search"></i><input v-model="q" type="text" placeholder="Cari nama / singkatan / kota…" /></div>
        </div>

        <div class="wca-card">
            <div class="wca-card__body--flush">
                <div class="wca-tablewrap">
                    <table class="wca-table">
                        <thead><tr><th>Kampus</th><th>Kota</th><th>Akreditasi</th><th>Status</th><th>Dibuat Oleh</th><th></th></tr></thead>
                        <tbody>
                            <tr v-for="k in filtered" :key="k.id">
                                <td><strong>{{ k.nama }}</strong> <span class="wca-badge wca-b--slate" style="margin-left:.2rem">{{ k.singkatan }}</span></td>
                                <td><i class="bi bi-geo-alt" style="color:var(--indigo)"></i> {{ k.kota }}</td>
                                <td><span class="wca-badge" :class="k.akreditasi === 'Unggul' ? 'wca-b--green' : 'wca-b--amber'">{{ k.akreditasi }}</span></td>
                                <td>
                                    <div class="kmp-status">
                                        <el-switch :model-value="k.status === 'AKTIF'" @change="(v) => setStatus(k, v)" />
                                        <span class="kmp-status__lbl" :class="k.status === 'AKTIF' ? 'is-on' : 'is-off'">{{ k.status === 'AKTIF' ? 'Aktif' : 'Nonaktif' }}</span>
                                    </div>
                                </td>
                                <td><AuditStamp :by="k.createdBy" :at="k.createdAt" /></td>
                                <td>
                                    <div style="display:flex;gap:.35rem;justify-content:flex-end">
                                        <button class="wca-iconbtn" title="Ubah" @click="openEdit(k)"><i class="bi bi-pencil"></i></button>
                                        <button class="wca-iconbtn wca-iconbtn--danger" title="Hapus" @click="askRemove(k)"><i class="bi bi-trash"></i></button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="!filtered.length"><td colspan="6"><div class="wca-empty"><i class="bi bi-mortarboard"></i><h4>Tidak ada kampus cocok</h4></div></td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Modal Create/Edit -->
        <AdminModal :show="show" :title="editingId ? 'Ubah Kampus' : 'Kampus Baru'" subtitle="Universitas / mitra pendidikan" icon="bi-mortarboard" :save-label="editingId ? 'Perbarui' : 'Simpan Kampus'" @close="show = false" @save="save">
            <div class="wca-fsection">
                <div class="wca-fsection__label"><i class="bi bi-mortarboard"></i> Data Kampus</div>
                <div class="wca-form">
                    <div class="wca-frow">
                        <div><label class="wca-field-lbl">Nama Kampus</label><el-input v-model="form.nama" placeholder="Universitas Sriwijaya" /></div>
                        <div><label class="wca-field-lbl">Singkatan</label><el-input v-model="form.singkatan" placeholder="UNSRI" /></div>
                    </div>
                    <div class="wca-frow">
                        <div><label class="wca-field-lbl">Kota</label><el-input v-model="form.kota" placeholder="Palembang" /></div>
                        <div><label class="wca-field-lbl">Akreditasi</label>
                            <el-select filterable v-model="form.akreditasi" placeholder="Pilih akreditasi">
                                <el-option label="Unggul" value="Unggul" />
                                <el-option label="Baik Sekali" value="Baik Sekali" />
                                <el-option label="Baik" value="Baik" />
                            </el-select>
                        </div>
                    </div>
                </div>
            </div>
        </AdminModal>

        <!-- Modal konfirmasi hapus -->
        <ConfirmModal
            :show="delShow"
            title="Hapus Kampus"
            :busy="deleting"
            confirm-label="Ya, Hapus Kampus"
            note="Data kampus akan dihapus permanen dari sistem."
            @cancel="delShow = false"
            @confirm="confirmDelete"
        >
            Yakin ingin menghapus kampus<br />
            <strong>{{ delTarget?.nama }}</strong> <span v-if="delTarget?.singkatan">({{ delTarget?.singkatan }})</span>?
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

const API = '/api/v1/karir/master/rich/kampus';
const CFG = { headers: { Accept: 'application/json' } };

export default {
    components: { Head, AdminModal, ConfirmModal, AuditStamp },
    props: {
        kampus: { type: Array, default: () => [] },
    },
    // Modal di halaman ini selamat dari refresh — lihat @utils/ingatModal.
    mixins: [ingatModal('admin/MasterKampus')],
    data() {
        return {
            list: this.kampus.map((k) => ({ ...k })),
            q: '',
            show: false,
            editingId: null,
            saving: false,
            form: { nama: '', singkatan: '', kota: '', akreditasi: 'Unggul' },
            delShow: false,
            delTarget: null,
            deleting: false,
            toast: '',
            tm: null,
        };
    },
    computed: {
        filtered() {
            const s = this.q.trim().toLowerCase();
            if (!s) return this.list;
            return this.list.filter((k) => (k.nama + ' ' + k.singkatan + ' ' + k.kota).toLowerCase().includes(s));
        },
        aktif() { return this.list.filter((k) => k.status === 'AKTIF').length; },
        kotaCount() { return new Set(this.list.map((k) => k.kota)).size; },
    },
    methods: {
        openCreate() {
            this.editingId = null;
            this.form = { nama: '', singkatan: '', kota: '', akreditasi: 'Unggul' };
            this.show = true;
        },
        openEdit(k) {
            this.editingId = k.id;
            this.form = { nama: k.nama, singkatan: k.singkatan, kota: k.kota, akreditasi: k.akreditasi };
            this.show = true;
        },
        async save() {
            if (this.saving) return;
            const f = this.form;
            if (!f.nama.trim()) return this.notice('Nama kampus wajib diisi.');
            this.saving = true;
            const payload = { nama: f.nama, singkatan: f.singkatan, kota: f.kota, akreditasi: f.akreditasi };
            try {
                const res = this.editingId
                    ? await axios.put(`${API}/${this.editingId}`, payload, CFG)
                    : await axios.post(API, payload, CFG);
                const row = res.data.result;
                if (this.editingId) {
                    const i = this.list.findIndex((x) => x.id === row.id);
                    if (i >= 0) this.list.splice(i, 1, row); else this.list.unshift(row);
                } else {
                    this.list.unshift(row);
                }
                this.notice(this.editingId ? 'Kampus diperbarui.' : 'Kampus berhasil ditambahkan.');
                this.show = false;
            } catch (e) {
                this.notice(e.response?.data?.message || 'Gagal menyimpan kampus.');
            } finally {
                this.saving = false;
            }
        },
        async setStatus(k, v) {
            const prev = k.status;
            k.status = v ? 'AKTIF' : 'NONAKTIF';
            try {
                await axios.patch(`${API}/${k.id}/toggle`, { aktif: v }, CFG);
                this.notice(`Kampus ${v ? 'diaktifkan' : 'dinonaktifkan'}.`);
            } catch (e) {
                k.status = prev;
                this.notice(e.response?.data?.message || 'Gagal mengubah status.');
            }
        },
        askRemove(k) {
            this.delTarget = k;
            this.delShow = true;
        },
        async confirmDelete() {
            if (this.deleting || !this.delTarget) return;
            const k = this.delTarget;
            this.deleting = true;
            try {
                await axios.delete(`${API}/${k.id}`, CFG);
                const i = this.list.findIndex((x) => x.id === k.id);
                if (i >= 0) this.list.splice(i, 1);
                this.notice('Kampus dihapus.');
                this.delShow = false;
                this.delTarget = null;
            } catch (e) {
                this.notice(e.response?.data?.message || 'Gagal menghapus kampus.');
            } finally {
                this.deleting = false;
            }
        },
        notice(m) { this.toast = m; if (this.tm) clearTimeout(this.tm); this.tm = setTimeout(() => (this.toast = ''), 3000); },
    },
};
</script>

<style scoped>
/* Status toggle */
.kmp-status { display: flex; align-items: center; gap: 10px; --el-switch-on-color: #059669; }
.kmp-status__lbl { font-size: 12px; font-weight: 700; letter-spacing: 0.01em; }
.kmp-status__lbl.is-on { color: #059669; }
.kmp-status__lbl.is-off { color: #94a3b8; }

@media (max-width: 640px) {
    .kmp-status__lbl { display: none; }
}
</style>
