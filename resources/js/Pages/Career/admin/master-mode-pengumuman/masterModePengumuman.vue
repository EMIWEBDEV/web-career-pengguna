<!-- WEB CAREER — Master Mode Pengumuman (kapan hasil tahap terlihat kandidat). DATA via web route + ResponseHelper (axios). -->
<template>
    <Head title="Master Mode Pengumuman" />
    <div class="wca">
        <div class="wca-phead">
            <div>
                <h1>Master Mode Pengumuman</h1>
                <p>
                    Menentukan <b>kapan hasil sebuah tahap terlihat kandidat</b>. Dipakai builder
                    <b>Alur Seleksi</b> — tanpa hardcode. Mode <b>NONAKTIF</b> tidak muncul sebagai pilihan.
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
                >Mode <b>Sistem</b> adalah bawaan (tak bisa dihapus, cukup dinonaktifkan). Untuk menyembunyikan
                sebuah mode dari builder Alur, cukup matikan sakelar <b>Aktif</b>-nya.</span
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
                        <strong>{{ m.nama }}</strong><small>{{ m.kode }}</small>
                    </div>
                    <span class="wca-badge" :class="m.sifat === 'SISTEM' ? 'wca-b--slate' : 'wca-b--indigo'">{{
                        m.sifat === 'SISTEM' ? 'Sistem' : 'Kustom'
                    }}</span>
                </div>
                <p class="wca-tipecard__label">{{ m.label }}</p>
                <p class="wca-tipecard__desc">{{ m.deskripsi }}</p>
                <div class="wca-cardaudit">
                    <AuditStamp :by="m.createdBy" :at="m.createdAt" :color="m.warna || '#6366f1'" />
                </div>
                <div class="wca-tipecard__foot">
                    <span v-if="m.butuhJeda" class="wca-badge wca-b--indigo" title="Mode ini memakai input jeda hari saat menyusun alur">
                        <i class="bi bi-calendar-event"></i> Pakai jeda hari
                    </span>
                    <span v-else class="wca-badge wca-b--slate"><i class="bi bi-dash-circle"></i> Tanpa jeda</span>
                    <div class="wca-tipecard__actions">
                        <el-switch :model-value="m.status === 'AKTIF'" @change="(v) => setStatus(m, v)" />
                        <button class="wca-iconbtn" title="Ubah" @click="openEdit(m)"><i class="bi bi-pencil"></i></button>
                        <button
                            v-if="m.sifat !== 'SISTEM'"
                            class="wca-iconbtn wca-iconbtn--danger"
                            title="Hapus"
                            @click="askRemove(m)"
                        >
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </div>
            </div>
            <div v-if="!loading && !list.length" class="wca-empty">
                <i class="bi bi-megaphone"></i>
                <h4>Belum ada mode pengumuman</h4>
            </div>
        </div>

        <AdminModal :busy="saving"
            :show="show"
            :title="editingId ? 'Ubah Mode Pengumuman' : 'Tambah Mode Kustom'"
            subtitle="Untuk cara penerbitan hasil yang belum tercakup mode bawaan"
            icon="bi-megaphone"
            :save-label="editingId ? 'Perbarui' : 'Simpan Mode'"
            @close="show = false"
            @save="save"
        >
            <div class="wca-fsection">
                <div class="wca-fsection__label"><i class="bi bi-megaphone"></i> Detail Mode</div>
                <div class="wca-form">
                    <div class="wca-frow">
                        <div>
                            <label class="wca-field-lbl">Nama Singkat</label>
                            <el-input v-model="form.nama" placeholder="mis. Otomatis" />
                        </div>
                        <div>
                            <label class="wca-field-lbl">Warna</label>
                            <el-color-picker v-model="form.warna" :predefine="palette" />
                        </div>
                    </div>
                    <div>
                        <label class="wca-field-lbl">Label Pilihan</label>
                        <el-input v-model="form.label" placeholder="mis. Otomatis — begitu keputusan dibuat" />
                    </div>
                    <div>
                        <label class="wca-field-lbl">Ikon</label><IconPicker v-model="form.ikon" />
                    </div>
                    <div>
                        <label class="wca-field-lbl">Deskripsi (konsekuensi bagi kandidat)</label>
                        <el-input
                            v-model="form.deskripsi"
                            type="textarea"
                            :rows="2"
                            placeholder="Jelaskan kapan & bagaimana hasil terlihat kandidat"
                        />
                    </div>
                    <label class="wca-check">
                        <el-checkbox v-model="form.butuhJeda">Mode ini memakai input jeda hari (tanggal pengumuman terjadwal)</el-checkbox>
                    </label>
                </div>
            </div>
        </AdminModal>

        <ConfirmModal
            :show="delShow"
            title="Hapus Mode Pengumuman"
            :busy="deleting"
            confirm-label="Ya, Hapus"
            note="Mode pengumuman kustom akan dihapus permanen."
            @cancel="delShow = false"
            @confirm="confirmDelete"
        >
            Yakin ingin menghapus mode <strong>{{ delTarget?.nama }}</strong>?
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
import IconPicker from '@career/IconPicker.vue';
import { ingatModal } from '@utils/ingatModal';

const API = '/api/v1/master-mode-pengumuman';
const CFG = { headers: { Accept: 'application/json' } };

export default {
    components: { Head, AdminModal, ConfirmModal, AuditStamp, IconPicker },
    // Modal di halaman ini selamat dari refresh — lihat @utils/ingatModal.
    mixins: [ingatModal('admin/master-mode-pengumuman/masterModePengumuman')],
    data() {
        return {
            list: [],
            loading: false,
            show: false,
            editingId: null,
            form: { nama: '', label: '', ikon: '', warna: '#4f46e5', deskripsi: '', butuhJeda: false },
            palette: ['#059669', '#4f46e5', '#7c3aed', '#0ea5e9', '#d97706', '#f59e0b', '#dc2626', '#64748b', '#0f172a'],
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
                this.notice('Gagal memuat data mode pengumuman.');
            } finally {
                this.loading = false;
            }
        },
        openCreate() {
            this.editingId = null;
            this.form = { nama: '', label: '', ikon: '', warna: '#4f46e5', deskripsi: '', butuhJeda: false };
            this.show = true;
        },
        openEdit(m) {
            this.editingId = m.id;
            this.form = {
                nama: m.nama,
                label: m.label || '',
                ikon: m.ikon || '',
                warna: m.warna || '#4f46e5',
                deskripsi: m.deskripsi || '',
                butuhJeda: !!m.butuhJeda,
            };
            this.show = true;
        },
        async save() {
            if (this.saving) return;
            if (!this.form.nama.trim()) return this.notice('Nama wajib diisi.');
            if (!this.form.label.trim()) return this.notice('Label pilihan wajib diisi.');
            this.saving = true;
            const payload = {
                nama: this.form.nama,
                label: this.form.label,
                ikon: this.form.ikon,
                warna: this.form.warna,
                deskripsi: this.form.deskripsi,
                butuhJeda: this.form.butuhJeda,
            };
            try {
                if (this.editingId) {
                    await axios.put(`${API}/${this.editingId}`, payload, CFG);
                    this.notice('Mode pengumuman diperbarui.');
                } else {
                    await axios.post(API, payload, CFG);
                    this.notice('Mode pengumuman ditambahkan.');
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
                this.notice('Mode pengumuman dihapus.');
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
.wca-tipecard__label { margin: 2px 0 4px; font-weight: 700; font-size: 13px; color: #334155; }
.wca-check { display: block; margin-top: .3rem; }
.wca-cardaudit {
    margin-top: 10px;
    padding-top: 10px;
    border-top: 1px dashed rgba(11, 16, 51, 0.08);
}
</style>
