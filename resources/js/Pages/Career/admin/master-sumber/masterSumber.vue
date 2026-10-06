<!-- WEB CAREER — Master Sumber Kandidat (per-modul). DATA via web route + ResponseHelper (axios), bukan props Inertia. -->
<template>
    <Head title="Master Sumber Kandidat" />
    <div class="wca">
        <div class="wca-phead">
            <div>
                <h1>Master Sumber Kandidat</h1>
                <p>
                    Channel/audience saat program <b>dibuka</b>: dari mana & ke siapa. Dipakai di menu
                    <b>Pembukaan Program</b>.
                </p>
            </div>
            <div class="wca-phead__actions">
                <button class="wca-btn wca-btn--primary" @click="openCreate">
                    <i class="bi bi-plus-lg"></i> Sumber Kustom
                </button>
            </div>
        </div>
        <div class="wca-note wca-note--info">
            <i class="bi bi-info-circle"></i>
            <span
                >Sumber menandai <b>dari mana & ke siapa</b> sebuah program dibuka (referral, jobstreet, kampus, …).
                Nilai <b>Sistem</b> tak bisa dihapus.</span
            >
        </div>

        <div v-loading="loading" class="wca-tipegrid">
            <div v-for="s in list" :key="s.id" class="wca-tipecard" :class="{ off: s.status !== 'AKTIF' }">
                <div class="wca-tipecard__head">
                    <span
                        class="wca-tipecard__ico"
                        :style="{ background: (s.warna || '#6366f1') + '22', color: s.warna || '#6366f1' }"
                        ><i class="bi" :class="s.ikon"></i
                    ></span>
                    <div class="wca-tipecard__title">
                        <strong>{{ s.nama }}</strong
                        ><small>{{ s.kode }}</small>
                    </div>
                    <span class="wca-badge" :class="s.sifat === 'SISTEM' ? 'wca-b--slate' : 'wca-b--indigo'">{{
                        s.sifat === 'SISTEM' ? 'Sistem' : 'Kustom'
                    }}</span>
                </div>
                <p class="wca-tipecard__desc">{{ s.deskripsi }}</p>
                <div class="wca-tipecard__foot">
                    <span
                        class="wca-badge"
                        :style="{
                            background: (s.warna || '#6366f1') + '1a',
                            color: s.warna || '#6366f1',
                            border: '1px solid ' + ((s.warna || '#6366f1') + '33'),
                        }"
                        ><i class="bi bi-broadcast"></i> {{ s.nama }}</span
                    >
                    <div class="wca-tipecard__actions">
                        <el-switch :model-value="s.status === 'AKTIF'" @change="(v) => setStatus(s, v)" />
                        <button class="wca-iconbtn" title="Ubah" @click="openEdit(s)">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <button class="wca-iconbtn wca-iconbtn--danger" title="Hapus" @click="askRemove(s)">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </div>
                <div class="wca-cardaudit">
                    <AuditStamp :by="s.createdBy" :at="s.createdAt" :color="s.warna || '#6366f1'" />
                </div>
            </div>
            <div v-if="!loading && !list.length" class="wca-empty">
                <i class="bi bi-broadcast"></i>
                <h4>Belum ada sumber</h4>
            </div>
        </div>

        <AdminModal :busy="saving"
            :show="show"
            :title="editingId ? 'Ubah Sumber' : 'Tambah Sumber Kustom'"
            subtitle="Untuk channel/audience di luar bawaan"
            icon="bi-broadcast"
            :save-label="editingId ? 'Perbarui' : 'Simpan Sumber'"
            @close="show = false"
            @save="save"
        >
            <div class="wca-fsection">
                <div class="wca-fsection__label"><i class="bi bi-broadcast"></i> Detail Sumber</div>
                <div class="wca-form">
                    <div>
                        <label class="wca-field-lbl">Nama Sumber</label
                        ><el-input v-model="form.nama" placeholder="mis. Referral Karyawan" />
                    </div>
                    <div>
                        <label class="wca-field-lbl">Deskripsi</label
                        ><el-input
                            v-model="form.deskripsi"
                            type="textarea"
                            :rows="2"
                            placeholder="Jelaskan dari mana & ke siapa"
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
            title="Hapus Sumber"
            :busy="deleting"
            confirm-label="Ya, Hapus"
            note="Sumber akan dihapus permanen."
            @cancel="delShow = false"
            @confirm="confirmDelete"
        >
            Yakin ingin menghapus sumber <strong>{{ delTarget?.nama }}</strong
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

const API = '/api/v1/master-sumber';
const CFG = { headers: { Accept: 'application/json' } };

export default {
    components: { Head, AdminModal, ConfirmModal, AuditStamp },
    // Modal di halaman ini selamat dari refresh — lihat @utils/ingatModal.
    mixins: [ingatModal('admin/master-sumber/masterSumber')],
    data() {
        return {
            list: [],
            loading: false,
            show: false,
            editingId: null,
            form: { nama: '', deskripsi: '', warna: '#059669' },
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
                this.notice('Gagal memuat data sumber.');
            } finally {
                this.loading = false;
            }
        },
        openCreate() {
            this.editingId = null;
            this.form = { nama: '', deskripsi: '', warna: '#059669' };
            this.show = true;
        },
        openEdit(s) {
            this.editingId = s.id;
            this.form = { nama: s.nama, deskripsi: s.deskripsi || '', warna: s.warna || '#059669' };
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
                    this.notice('Sumber diperbarui.');
                } else {
                    await axios.post(API, payload, CFG);
                    this.notice('Sumber ditambahkan.');
                }
                this.show = false;
                await this.load();
            } catch (e) {
                this.notice(e.response?.data?.message || 'Gagal menyimpan.');
            } finally {
                this.saving = false;
            }
        },
        async setStatus(s, v) {
            const prev = s.status;
            s.status = v ? 'AKTIF' : 'NONAKTIF';
            try {
                await axios.patch(`${API}/${s.id}/toggle`, { aktif: v }, CFG);
                this.notice(`Sumber "${s.nama}" ${v ? 'diaktifkan' : 'dinonaktifkan'}.`);
            } catch (e) {
                s.status = prev;
                this.notice('Gagal mengubah status.');
            }
        },
        askRemove(s) {
            this.delTarget = s;
            this.delShow = true;
        },
        async confirmDelete() {
            if (this.deleting || !this.delTarget) return;
            const s = this.delTarget;
            this.deleting = true;
            try {
                await axios.delete(`${API}/${s.id}`, CFG);
                this.notice('Sumber dihapus.');
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
