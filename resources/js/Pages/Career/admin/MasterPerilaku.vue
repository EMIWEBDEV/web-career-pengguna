<!-- WEB CAREER — Admin: Master Perilaku (cara tahap dijalankan/diputuskan; dipakai Master Tipe Tahap). -->
<template>
    <Head title="Master Perilaku" />
    <div class="wca">
        <div class="wca-phead">
            <div>
                <h1>Master Perilaku</h1>
                <p>Cara sebuah tahap <b>dijalankan & diputuskan</b>. Dipakai oleh <b>Master Tipe Tahap</b> agar tidak hardcode.</p>
            </div>
            <div class="wca-phead__actions">
                <button class="wca-btn wca-btn--primary" @click="openCreate"><i class="bi bi-plus-lg"></i> Perilaku Kustom</button>
            </div>
        </div>

        <div class="wca-note wca-note--info">
            <i class="bi bi-info-circle"></i>
            <span>Perilaku menentukan siapa yang memutuskan hasil tahap: <b>admin (manual)</b>, <b>sistem (tes CAT otomatis)</b>, atau <b>verifikasi berkas</b>. Perilaku <b>Sistem</b> adalah bawaan.</span>
        </div>

        <div class="wca-tipegrid">
            <div v-for="p in list" :key="p.id" class="wca-tipecard" :class="{ off: p.status !== 'AKTIF' }">
                <div class="wca-tipecard__head">
                    <span class="wca-tipecard__ico" :style="{ background: (p.warna||'#6366f1') + '22', color: (p.warna||'#6366f1') }"><i class="bi" :class="p.ikon"></i></span>
                    <div class="wca-tipecard__title">
                        <strong>{{ p.nama }}</strong>
                        <small>{{ p.kode }}</small>
                    </div>
                    <span class="wca-badge" :class="p.sifat === 'SISTEM' ? 'wca-b--slate' : 'wca-b--indigo'">{{ p.sifat === 'SISTEM' ? 'Sistem' : 'Kustom' }}</span>
                </div>
                <p class="wca-tipecard__desc">{{ p.deskripsi }}</p>
                <div class="wca-cardaudit"><AuditStamp :by="p.createdBy" :at="p.createdAt" :color="p.warna || '#6366f1'" /></div>
                <div class="wca-tipecard__foot">
                    <span class="wca-badge" :style="{ background:(p.warna||'#6366f1')+'1a', color:(p.warna||'#6366f1'), border:'1px solid '+((p.warna||'#6366f1')+'33') }">{{ p.kode }}</span>
                    <div class="wca-tipecard__actions">
                        <el-switch :model-value="p.status === 'AKTIF'" @change="(v) => setStatus(p, v)" />
                        <button class="wca-iconbtn" title="Ubah" @click="openEdit(p)"><i class="bi bi-pencil"></i></button>
                        <button class="wca-iconbtn wca-iconbtn--danger" title="Hapus" @click="askRemove(p)"><i class="bi bi-trash"></i></button>
                    </div>
                </div>
            </div>
        </div>

        <AdminModal :show="show" :title="editingId ? 'Ubah Perilaku' : 'Tambah Perilaku Kustom'" subtitle="Untuk cara pelaksanaan tahap yang belum tercakup" icon="bi-lightning-charge" :save-label="editingId ? 'Perbarui' : 'Simpan Perilaku'" @close="show = false" @save="save">
            <div class="wca-fsection">
                <div class="wca-fsection__label"><i class="bi bi-lightning-charge"></i> Detail Perilaku</div>
                <div class="wca-form">
                    <div class="wca-frow">
                        <div><label class="wca-field-lbl">Nama Perilaku</label><el-input v-model="form.nama" placeholder="mis. Verifikasi Referensi" /></div>
                        <div><label class="wca-field-lbl">Warna</label>
                            <el-color-picker v-model="form.warna" :predefine="palette" />
                        </div>
                    </div>
                    <div><label class="wca-field-lbl">Deskripsi</label><el-input v-model="form.deskripsi" type="textarea" :rows="2" placeholder="Jelaskan siapa yang memutuskan & bagaimana caranya" /></div>
                </div>
            </div>
        </AdminModal>

        <ConfirmModal :show="delShow" title="Hapus Perilaku" :busy="deleting" confirm-label="Ya, Hapus" note="Perilaku kustom akan dihapus permanen." @cancel="delShow = false" @confirm="confirmDelete">
            Yakin ingin menghapus perilaku <strong>{{ delTarget?.nama }}</strong>?
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
const KEY = 'perilaku';
const CFG = { headers: { Accept: 'application/json' } };

export default {
    components: { Head, AdminModal, ConfirmModal, AuditStamp },
    props: {
        perilaku: { type: Array, default: () => [] },
    },
    // Modal di halaman ini selamat dari refresh — lihat @utils/ingatModal.
    mixins: [ingatModal('admin/MasterPerilaku')],
    data() {
        return {
            list: this.perilaku.map((p) => ({ ...p })),
            palette: ['#4f46e5','#7c3aed','#0ea5e9','#059669','#d97706','#f59e0b','#dc2626','#64748b','#0f172a'],
            show: false,
            editingId: null,
            form: { nama: '', warna: '#4f46e5', deskripsi: '' },
            delShow: false,
            delTarget: null,
            deleting: false,
            toast: '',
            tm: null,
        };
    },
    methods: {
        openCreate() {
            this.editingId = null;
            this.form = { nama: '', warna: '#4f46e5', deskripsi: '' };
            this.show = true;
        },
        openEdit(p) {
            this.editingId = p.id;
            this.form = { nama: p.nama, warna: p.warna || '#4f46e5', deskripsi: p.deskripsi || '' };
            this.show = true;
        },
        async save() {
            if (!this.form.nama.trim()) return this.notice('Nama wajib diisi.');
            const payload = { nama: this.form.nama, warna: this.form.warna, deskripsi: this.form.deskripsi };
            try {
                if (this.editingId) {
                    const res = await axios.put(`${API}/${KEY}/${this.editingId}`, payload, CFG);
                    const i = this.list.findIndex((x) => x.id === this.editingId);
                    if (i >= 0) this.list.splice(i, 1, res.data.result);
                    this.notice('Perilaku diperbarui.');
                } else {
                    const res = await axios.post(`${API}/${KEY}`, payload, CFG);
                    this.list.push(res.data.result);
                    this.notice('Perilaku kustom ditambahkan.');
                }
                this.show = false;
            } catch (e) {
                this.notice(e.response?.data?.message || 'Gagal menyimpan.');
            }
        },
        async setStatus(p, v) {
            const prev = p.status;
            p.status = v ? 'AKTIF' : 'NONAKTIF';
            try {
                await axios.patch(`${API}/${KEY}/${p.id}/toggle`, { aktif: v }, CFG);
                this.notice(`Perilaku "${p.nama}" ${v ? 'diaktifkan' : 'dinonaktifkan'}.`);
            } catch (e) {
                p.status = prev;
                this.notice('Gagal mengubah status.');
            }
        },
        askRemove(p) {
            this.delTarget = p;
            this.delShow = true;
        },
        async confirmDelete() {
            if (this.deleting || !this.delTarget) return;
            const p = this.delTarget;
            this.deleting = true;
            try {
                await axios.delete(`${API}/${KEY}/${p.id}`, CFG);
                const i = this.list.findIndex((x) => x.id === p.id);
                if (i >= 0) this.list.splice(i, 1);
                this.notice('Perilaku kustom dihapus.');
                this.delShow = false;
                this.delTarget = null;
            } catch (e) {
                this.notice(e.response?.data?.message || 'Gagal menghapus.');
            } finally {
                this.deleting = false;
            }
        },
        notice(m) {
            this.toast = m;
            if (this.tm) clearTimeout(this.tm);
            this.tm = setTimeout(() => (this.toast = ''), 3000);
        },
    },
};
</script>

<style scoped>
.wca-cardaudit { margin-top: 10px; padding-top: 10px; border-top: 1px dashed rgba(11,16,51,.08); }
</style>
