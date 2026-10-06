<!-- WEB CAREER — Admin: Master Mode Pelaksanaan (referensi cara program berjalan terhadap waktu). -->
<template>
    <Head title="Master Mode Pelaksanaan" />
    <div class="wca">
        <div class="wca-phead">
            <div>
                <h1>Master Mode Pelaksanaan</h1>
                <p>Cara sebuah <b>Program</b> berjalan terhadap waktu. Dipakai saat buat program & sebagai preset di Master Kategori.</p>
            </div>
            <div class="wca-phead__actions">
                <button class="wca-btn wca-btn--primary" @click="openCreate"><i class="bi bi-plus-lg"></i> Mode Kustom</button>
            </div>
        </div>
        <div class="wca-note wca-note--info">
            <i class="bi bi-info-circle"></i>
            <span>Mode <b>mengikat</b>: <b>Terstruktur</b> → wajib punya Jadwal Kegiatan; <b>Rolling</b> → jalan per SLA tanpa jadwal. Nilai <b>Sistem</b> tak bisa diubah/dihapus.</span>
        </div>
        <div class="wca-tipegrid">
            <div v-for="m in list" :key="m.id" class="wca-tipecard" :class="{ off: m.status !== 'AKTIF' }">
                <div class="wca-tipecard__head">
                    <span class="wca-tipecard__ico" :style="{ background:(m.warna||'#6366f1')+'22', color:(m.warna||'#6366f1') }"><i class="bi" :class="m.ikon"></i></span>
                    <div class="wca-tipecard__title"><strong>{{ m.nama }}</strong><small>{{ m.kode }}</small></div>
                    <span class="wca-badge" :class="m.sifat === 'SISTEM' ? 'wca-b--slate' : 'wca-b--indigo'">{{ m.sifat === 'SISTEM' ? 'Sistem' : 'Kustom' }}</span>
                </div>
                <p class="wca-tipecard__desc">{{ m.deskripsi }}</p>
                <div class="wca-tipecard__foot">
                    <span class="wca-badge" :class="m.kode === 'TERSTRUKTUR' ? 'wca-b--indigo' : 'wca-b--slate'">{{ m.kode === 'TERSTRUKTUR' ? 'Pakai Jadwal' : 'Per SLA' }}</span>
                    <div class="wca-tipecard__actions">
                        <el-switch :model-value="m.status === 'AKTIF'" @change="(v) => setStatus(m, v)" />
                        <button class="wca-iconbtn" title="Ubah" @click="openEdit(m)"><i class="bi bi-pencil"></i></button>
                        <button class="wca-iconbtn wca-iconbtn--danger" title="Hapus" @click="askRemove(m)"><i class="bi bi-trash"></i></button>
                    </div>
                </div>
                <div class="wca-cardaudit"><AuditStamp :by="m.createdBy" :at="m.createdAt" :color="m.warna || '#6366f1'" /></div>
            </div>
        </div>

        <AdminModal :show="show" :title="editingId ? 'Ubah Mode' : 'Tambah Mode Kustom'" subtitle="Untuk pola pelaksanaan di luar bawaan" icon="bi-toggles" :save-label="editingId ? 'Perbarui' : 'Simpan Mode'" @close="show = false" @save="save">
            <div class="wca-fsection">
                <div class="wca-fsection__label"><i class="bi bi-toggles"></i> Detail Mode</div>
                <div class="wca-form">
                    <div class="wca-frow">
                        <div><label class="wca-field-lbl">Nama Mode</label><el-input v-model="form.nama" placeholder="mis. Hybrid" /></div>
                        <div><label class="wca-field-lbl">Warna</label>
                            <el-color-picker v-model="form.warna" :predefine="palette" />
                        </div>
                    </div>
                    <div><label class="wca-field-lbl">Deskripsi</label><el-input v-model="form.deskripsi" type="textarea" :rows="2" placeholder="Jelaskan cara mode ini berjalan" /></div>
                </div>
            </div>
        </AdminModal>

        <ConfirmModal :show="delShow" title="Hapus Mode" :busy="deleting" confirm-label="Ya, Hapus" note="Mode kustom akan dihapus permanen." @cancel="delShow = false" @confirm="confirmDelete">
            Yakin ingin menghapus mode <strong>{{ delTarget?.nama }}</strong>?
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

const API = '/api/v1/karir/master/simple';
const KEY = 'mode';
const CFG = { headers: { Accept: 'application/json' } };

export default {
    components: { Head, AdminModal, ConfirmModal, AuditStamp },
    props: { mode: { type: Array, default: () => [] } },
    // Modal di halaman ini selamat dari refresh — lihat @utils/ingatModal.
    mixins: [ingatModal('admin/MasterMode')],
    data() {
        return {
            list: this.mode.map((m) => ({ ...m })),
            show: false,
            editingId: null,
            palette: ['#4f46e5','#7c3aed','#0ea5e9','#059669','#d97706','#f59e0b','#dc2626','#64748b','#0f172a'],
            form: { nama: '', warna: '#4f46e5', deskripsi: '' },
            delShow: false,
            delTarget: null,
            deleting: false,
            toast: '',
            tm: null,
        };
    },
    methods: {
        openCreate() { this.editingId = null; this.form = { nama: '', warna: '#4f46e5', deskripsi: '' }; this.show = true; },
        openEdit(m) { this.editingId = m.id; this.form = { nama: m.nama, warna: m.warna || '#4f46e5', deskripsi: m.deskripsi || '' }; this.show = true; },
        async save() {
            if (!this.form.nama.trim()) return this.notice('Nama wajib diisi.');
            const payload = { nama: this.form.nama, warna: this.form.warna, deskripsi: this.form.deskripsi };
            try {
                if (this.editingId) {
                    const res = await axios.put(`${API}/${KEY}/${this.editingId}`, payload, CFG);
                    const i = this.list.findIndex((x) => x.id === this.editingId);
                    if (i >= 0) this.list.splice(i, 1, res.data.result);
                    this.notice('Mode diperbarui.');
                } else {
                    const res = await axios.post(`${API}/${KEY}`, payload, CFG);
                    this.list.push(res.data.result);
                    this.notice('Mode kustom ditambahkan.');
                }
                this.show = false;
            } catch (e) {
                this.notice(e.response?.data?.message || 'Gagal menyimpan.');
            }
        },
        async setStatus(m, v) {
            const prev = m.status;
            m.status = v ? 'AKTIF' : 'NONAKTIF';
            try {
                await axios.patch(`${API}/${KEY}/${m.id}/toggle`, { aktif: v }, CFG);
                this.notice(`Mode "${m.nama}" ${v ? 'diaktifkan' : 'dinonaktifkan'}.`);
            } catch (e) {
                m.status = prev;
                this.notice('Gagal mengubah status.');
            }
        },
        askRemove(m) { this.delTarget = m; this.delShow = true; },
        async confirmDelete() {
            if (this.deleting || !this.delTarget) return;
            const m = this.delTarget;
            this.deleting = true;
            try {
                await axios.delete(`${API}/${KEY}/${m.id}`, CFG);
                const i = this.list.findIndex((x) => x.id === m.id);
                if (i >= 0) this.list.splice(i, 1);
                this.notice('Mode kustom dihapus.');
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
.wca-cardaudit { margin-top: 10px; padding-top: 10px; border-top: 1px dashed rgba(11, 16, 51, .08); }
</style>
