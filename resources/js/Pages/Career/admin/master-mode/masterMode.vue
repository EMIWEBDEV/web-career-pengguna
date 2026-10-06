<!-- WEB CAREER — Master Mode Pelaksanaan (per-modul). DATA via web route + ResponseHelper (axios), bukan props Inertia. -->
<template>
    <Head title="Master Mode Pelaksanaan" />
    <div class="wca">
        <div class="wca-phead">
            <div>
                <h1>Master Mode Pelaksanaan</h1>
                <p>
                    Cara sebuah <b>Program</b> berjalan terhadap waktu. Dipakai saat buat program & sebagai preset di
                    Master Kategori.
                </p>
            </div>
            <div class="wca-phead__actions">
                <button class="wca-btn wca-btn--primary" @click="openCreate">
                    <i class="bi bi-plus-lg"></i> Mode Kustom
                </button>
            </div>
        </div>
        <div class="wca-note wca-note--info">
            <i class="bi bi-info-circle"></i>
            <span
                >Mode adalah <b>label cara pelaksanaan</b> Program terhadap waktu. Nilai <b>Sistem</b> tak bisa
                diubah/dihapus.</span
            >
        </div>

        <div v-loading="loading" class="wca-tipegrid">
            <div v-for="m in list" :key="m.id" class="wca-tipecard" :class="{ off: m.status !== 'AKTIF' }">
                <div class="wca-tipecard__head">
                    <span
                        class="wca-tipecard__ico"
                        :style="{ background: (m.warna || '#6366f1') + '22', color: m.warna || '#6366f1' }"
                        ><i class="bi" :class="m.ikon"></i
                    ></span>
                    <div class="wca-tipecard__title">
                        <strong>{{ m.nama }}</strong
                        ><small>{{ m.kode }}</small>
                    </div>
                    <span class="wca-badge" :class="m.sifat === 'SISTEM' ? 'wca-b--slate' : 'wca-b--indigo'">{{
                        m.sifat === 'SISTEM' ? 'Sistem' : 'Kustom'
                    }}</span>
                </div>
                <p class="wca-tipecard__desc">{{ m.deskripsi }}</p>
                <div class="wca-tipecard__foot">
                    <span
                        class="wca-badge"
                        :style="{
                            background: (m.warna || '#6366f1') + '1a',
                            color: m.warna || '#6366f1',
                            border: '1px solid ' + ((m.warna || '#6366f1') + '33'),
                        }"
                        ><i class="bi bi-toggles"></i> {{ m.nama }}</span
                    >
                    <div class="wca-tipecard__actions">
                        <el-switch :model-value="m.status === 'AKTIF'" @change="(v) => setStatus(m, v)" />
                        <button class="wca-iconbtn" title="Ubah" @click="openEdit(m)">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <button class="wca-iconbtn wca-iconbtn--danger" title="Hapus" @click="askRemove(m)">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </div>
                <div class="wca-cardaudit">
                    <AuditStamp :by="m.createdBy" :at="m.createdAt" :color="m.warna || '#6366f1'" />
                </div>
            </div>
            <div v-if="!loading && !list.length" class="wca-empty">
                <i class="bi bi-toggles"></i>
                <h4>Belum ada mode</h4>
            </div>
        </div>

        <AdminModal :busy="saving"
            :show="show"
            :title="editingId ? 'Ubah Mode' : 'Tambah Mode Kustom'"
            subtitle="Untuk pola pelaksanaan di luar bawaan"
            icon="bi-toggles"
            :save-label="editingId ? 'Perbarui' : 'Simpan Mode'"
            @close="show = false"
            @save="save"
        >
            <div class="wca-fsection">
                <div class="wca-fsection__label"><i class="bi bi-toggles"></i> Detail Mode</div>
                <div class="wca-form">
                    <div>
                        <label class="wca-field-lbl">Nama Mode</label
                        ><el-input v-model="form.nama" placeholder="mis. Hybrid" />
                    </div>
                    <div>
                        <label class="wca-field-lbl">Deskripsi</label
                        ><el-input
                            v-model="form.deskripsi"
                            type="textarea"
                            :rows="2"
                            placeholder="Jelaskan cara mode ini berjalan"
                        />
                    </div>
                    <div>
                        <label class="wca-field-lbl">Warna</label
                        ><el-color-picker v-model="form.warna" :predefine="palette" />
                    </div>
                </div>
            </div>
        </AdminModal>

        <ConfirmModal
            :show="delShow"
            title="Hapus Mode"
            :busy="deleting"
            confirm-label="Ya, Hapus"
            note="Mode kustom akan dihapus permanen."
            @cancel="delShow = false"
            @confirm="confirmDelete"
        >
            Yakin ingin menghapus mode <strong>{{ delTarget?.nama }}</strong
            >?
        </ConfirmModal>

        <transition name="wca-toast"
            ><div v-if="toast" class="wca-toast"><i class="bi bi-check-circle-fill"></i> {{ toast }}</div></transition
        >
    </div>
</template>

<script>
import axios from 'axios';
import { Head } from '@inertiajs/vue3';
import AdminModal from '@career/AdminModal.vue';
import ConfirmModal from '@career/ConfirmModal.vue';
import AuditStamp from '@career/AuditStamp.vue';
import { ingatModal } from '@utils/ingatModal';

const API = '/api/v1/master-mode';
const CFG = { headers: { Accept: 'application/json' } };

export default {
    components: { Head, AdminModal, ConfirmModal, AuditStamp },
    // Modal di halaman ini selamat dari refresh — lihat @utils/ingatModal.
    mixins: [ingatModal('admin/master-mode/masterMode')],
    data() {
        return {
            list: [],
            loading: false,
            show: false,
            editingId: null,
            form: { nama: '', deskripsi: '', warna: '#4f46e5' },
            palette: [
                '#4f46e5',
                '#7c3aed',
                '#0ea5e9',
                '#059669',
                '#d97706',
                '#f59e0b',
                '#dc2626',
                '#64748b',
                '#0f172a',
            ],
            delShow: false,
            delTarget: null,
            deleting: false,
            saving: false,
            toast: '',
            tm: null,
        };
    },
    mounted() {
        this.load();
    },
    methods: {
        async load() {
            this.loading = true;
            try {
                const res = await axios.get(API, CFG);
                this.list = res.data.result || [];
            } catch (e) {
                this.notice('Gagal memuat data mode.');
            } finally {
                this.loading = false;
            }
        },
        openCreate() {
            this.editingId = null;
            this.form = { nama: '', deskripsi: '', warna: '#4f46e5' };
            this.show = true;
        },
        openEdit(m) {
            this.editingId = m.id;
            this.form = { nama: m.nama, deskripsi: m.deskripsi || '', warna: m.warna || '#4f46e5' };
            this.show = true;
        },
        async save() {
            if (this.saving) return;
            if (!this.form.nama.trim()) return this.notice('Nama wajib diisi.');
            this.saving = true;
            const payload = { nama: this.form.nama, deskripsi: this.form.deskripsi, warna: this.form.warna };
            try {
                if (this.editingId) {
                    await axios.put(`${API}/${this.editingId}`, payload, CFG);
                    this.notice('Mode diperbarui.');
                } else {
                    await axios.post(API, payload, CFG);
                    this.notice('Mode ditambahkan.');
                }
                this.show = false;
                await this.load();
            } catch (e) {
                this.notice(e.response?.data?.message || 'Gagal menyimpan.');
            } finally {
                this.saving = false;
            }
        },
        async setStatus(m, v) {
            const prev = m.status;
            m.status = v ? 'AKTIF' : 'NONAKTIF';
            try {
                await axios.patch(`${API}/${m.id}/toggle`, { aktif: v }, CFG);
                this.notice(`Mode "${m.nama}" ${v ? 'diaktifkan' : 'dinonaktifkan'}.`);
            } catch (e) {
                m.status = prev;
                this.notice('Gagal mengubah status.');
            }
        },
        askRemove(m) {
            this.delTarget = m;
            this.delShow = true;
        },
        async confirmDelete() {
            if (this.deleting || !this.delTarget) return;
            const m = this.delTarget;
            this.deleting = true;
            try {
                await axios.delete(`${API}/${m.id}`, CFG);
                this.notice('Mode dihapus.');
                this.delShow = false;
                this.delTarget = null;
                await this.load();
            } catch (e) {
                this.notice(e.response?.data?.message || 'Gagal menghapus.');
            } finally {
                this.deleting = false;
            }
        },
        notice(x) {
            this.toast = x;
            if (this.tm) clearTimeout(this.tm);
            this.tm = setTimeout(() => (this.toast = ''), 3000);
        },
    },
};
</script>

<style scoped>
.wca-cardaudit {
    margin-top: 10px;
    padding-top: 10px;
    border-top: 1px dashed rgba(11, 16, 51, 0.08);
}
</style>
