<!-- WEB CAREER — Admin: Master Sumber Kandidat (channel/audience pembukaan; dipakai Pembukaan Program). -->
<template>
    <Head title="Master Sumber Kandidat" />
    <div class="wca">
        <div class="wca-phead">
            <div>
                <h1>Master Sumber Kandidat</h1>
                <p>Channel/audience saat program <b>dibuka</b>: dari mana & ke siapa. Dipakai di menu <b>Pembukaan Program</b>.</p>
            </div>
            <div class="wca-phead__actions">
                <button class="wca-btn wca-btn--primary" @click="openCreate"><i class="bi bi-plus-lg"></i> Sumber Kustom</button>
            </div>
        </div>
        <div class="wca-note wca-note--info">
            <i class="bi bi-info-circle"></i>
            <span><b>Umum</b> = terbuka publik (tampil di landing). <b>Kampus</b> = tertarget kampus tertentu (campus hiring). Nilai <b>Sistem</b> tak bisa diubah/dihapus.</span>
        </div>
        <div class="wca-tipegrid">
            <div v-for="s in list" :key="s.id" class="wca-tipecard" :class="{ off: s.status !== 'AKTIF' }">
                <div class="wca-tipecard__head">
                    <span class="wca-tipecard__ico" :style="{ background:(s.warna||'#6366f1')+'22', color:(s.warna||'#6366f1') }"><i class="bi" :class="s.ikon"></i></span>
                    <div class="wca-tipecard__title"><strong>{{ s.nama }}</strong><small>{{ s.kode }}</small></div>
                    <span class="wca-badge" :class="s.sifat === 'SISTEM' ? 'wca-b--slate' : 'wca-b--indigo'">{{ s.sifat === 'SISTEM' ? 'Sistem' : 'Kustom' }}</span>
                </div>
                <p class="wca-tipecard__desc">{{ s.deskripsi }}</p>
                <div class="wca-tipecard__foot">
                    <span class="wca-badge" :class="s.kode === 'KAMPUS' ? 'wca-b--indigo' : 'wca-b--green'"><i class="bi" :class="s.kode === 'KAMPUS' ? 'bi-mortarboard' : 'bi-globe'"></i> {{ s.kode === 'KAMPUS' ? 'Target Kampus' : 'Publik / Landing' }}</span>
                    <div class="wca-tipecard__actions">
                        <el-switch :model-value="s.status === 'AKTIF'" @change="(v) => setStatus(s, v)" />
                        <button class="wca-iconbtn" title="Ubah" @click="openEdit(s)"><i class="bi bi-pencil"></i></button>
                        <button class="wca-iconbtn wca-iconbtn--danger" title="Hapus" @click="askRemove(s)"><i class="bi bi-trash"></i></button>
                    </div>
                </div>
                <div class="wca-cardaudit"><AuditStamp :by="s.createdBy" :at="s.createdAt" :color="s.warna || '#6366f1'" /></div>
            </div>
        </div>

        <AdminModal :show="show" :title="editingId ? 'Ubah Sumber' : 'Tambah Sumber Kustom'" subtitle="Untuk channel/audience di luar bawaan" icon="bi-broadcast" :save-label="editingId ? 'Perbarui' : 'Simpan Sumber'" @close="show = false" @save="save">
            <div class="wca-fsection">
                <div class="wca-fsection__label"><i class="bi bi-broadcast"></i> Detail Sumber</div>
                <div class="wca-form">
                    <div class="wca-frow">
                        <div><label class="wca-field-lbl">Nama Sumber</label><el-input v-model="form.nama" placeholder="mis. Referral Karyawan" /></div>
                        <div><label class="wca-field-lbl">Warna</label>
                            <el-color-picker v-model="form.warna" :predefine="palette" />
                        </div>
                    </div>
                    <div><label class="wca-field-lbl">Deskripsi</label><el-input v-model="form.deskripsi" type="textarea" :rows="2" placeholder="Jelaskan dari mana & ke siapa" /></div>
                </div>
            </div>
        </AdminModal>

        <ConfirmModal :show="delShow" title="Hapus Sumber" :busy="deleting" confirm-label="Ya, Hapus" note="Sumber kustom akan dihapus permanen." @cancel="delShow = false" @confirm="confirmDelete">
            Yakin ingin menghapus sumber <strong>{{ delTarget?.nama }}</strong>?
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
const KEY = 'sumber';
const CFG = { headers: { Accept: 'application/json' } };

export default {
    components: { Head, AdminModal, ConfirmModal, AuditStamp },
    props: { sumber: { type: Array, default: () => [] } },
    // Modal di halaman ini selamat dari refresh — lihat @utils/ingatModal.
    mixins: [ingatModal('admin/MasterSumber')],
    data() {
        return {
            list: this.sumber.map((s) => ({ ...s })),
            show: false,
            editingId: null,
            palette: ['#4f46e5','#7c3aed','#0ea5e9','#059669','#d97706','#f59e0b','#dc2626','#64748b','#0f172a'],
            form: { nama: '', warna: '#059669', deskripsi: '' },
            delShow: false,
            delTarget: null,
            deleting: false,
            toast: '',
            tm: null,
        };
    },
    methods: {
        openCreate() { this.editingId = null; this.form = { nama: '', warna: '#059669', deskripsi: '' }; this.show = true; },
        openEdit(s) { this.editingId = s.id; this.form = { nama: s.nama, warna: s.warna || '#059669', deskripsi: s.deskripsi || '' }; this.show = true; },
        async save() {
            if (!this.form.nama.trim()) return this.notice('Nama wajib diisi.');
            const payload = { nama: this.form.nama, warna: this.form.warna, deskripsi: this.form.deskripsi };
            try {
                if (this.editingId) {
                    const res = await axios.put(`${API}/${KEY}/${this.editingId}`, payload, CFG);
                    const i = this.list.findIndex((x) => x.id === this.editingId);
                    if (i >= 0) this.list.splice(i, 1, res.data.result);
                    this.notice('Sumber diperbarui.');
                } else {
                    const res = await axios.post(`${API}/${KEY}`, payload, CFG);
                    this.list.push(res.data.result);
                    this.notice('Sumber kustom ditambahkan.');
                }
                this.show = false;
            } catch (e) {
                this.notice(e.response?.data?.message || 'Gagal menyimpan.');
            }
        },
        async setStatus(s, v) {
            const prev = s.status;
            s.status = v ? 'AKTIF' : 'NONAKTIF';
            try {
                await axios.patch(`${API}/${KEY}/${s.id}/toggle`, { aktif: v }, CFG);
                this.notice(`Sumber "${s.nama}" ${v ? 'diaktifkan' : 'dinonaktifkan'}.`);
            } catch (e) {
                s.status = prev;
                this.notice('Gagal mengubah status.');
            }
        },
        askRemove(s) { this.delTarget = s; this.delShow = true; },
        async confirmDelete() {
            if (this.deleting || !this.delTarget) return;
            const s = this.delTarget;
            this.deleting = true;
            try {
                await axios.delete(`${API}/${KEY}/${s.id}`, CFG);
                const i = this.list.findIndex((x) => x.id === s.id);
                if (i >= 0) this.list.splice(i, 1);
                this.notice('Sumber kustom dihapus.');
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
