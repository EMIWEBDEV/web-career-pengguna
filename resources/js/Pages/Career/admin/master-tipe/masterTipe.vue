<!-- WEB CAREER — Master Tipe Tahap (per-modul). DATA via web route + ResponseHelper (axios), bukan props Inertia. -->
<template>
    <Head title="Master Tipe Tahap" />
    <div class="wca">
        <div class="wca-phead">
            <div>
                <h1>Master Tipe Tahap</h1>
                <p>
                    Jenis tahap yang bisa dipilih saat menyusun <b>Alur Seleksi</b>. Tiap tipe punya
                    <b>perilaku</b> berbeda (manual, tes online, atau verifikasi berkas).
                </p>
            </div>
            <div class="wca-phead__actions">
                <button class="wca-btn wca-btn--primary" @click="openCreate">
                    <i class="bi bi-plus-lg"></i> Tipe Kustom
                </button>
            </div>
        </div>

        <div class="wca-note wca-note--info">
            <i class="bi bi-info-circle"></i>
            <span
                >Tipe <b>Sistem</b> adalah bawaan (tak bisa dihapus). Tipe <b>Kustom</b> otomatis <b>Aktif</b> dan
                langsung bisa dipilih di builder Alur Seleksi.</span
            >
        </div>

        <div v-loading="loading" class="wca-tipegrid">
            <div v-for="t in list" :key="t.id" class="wca-tipecard" :class="{ off: t.status !== 'AKTIF' }">
                <div class="wca-tipecard__head">
                    <span class="wca-tipecard__ico"><i class="bi" :class="t.ikon"></i></span>
                    <div class="wca-tipecard__title">
                        <strong>{{ t.nama }}</strong
                        ><small>{{ t.kode }}</small>
                    </div>
                    <span class="wca-badge" :class="t.sifat === 'SISTEM' ? 'wca-b--slate' : 'wca-b--indigo'">{{
                        t.sifat === 'SISTEM' ? 'Sistem' : 'Kustom'
                    }}</span>
                </div>
                <p class="wca-tipecard__desc">{{ t.deskripsi }}</p>
                <div class="wca-cardaudit">
                    <AuditStamp :by="t.createdBy" :at="t.createdAt" />
                </div>
                <div class="wca-tipecard__foot">
                    <span class="wca-badge wca-b--indigo"><i class="bi bi-lightning-charge"></i> {{ t.perilaku }}</span>
                    <div class="wca-tipecard__actions">
                        <el-switch :model-value="t.status === 'AKTIF'" @change="(v) => setStatus(t, v)" />
                        <button class="wca-iconbtn" title="Ubah" @click="openEdit(t)">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <button class="wca-iconbtn wca-iconbtn--danger" title="Hapus" @click="askRemove(t)">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </div>
            </div>
            <div v-if="!loading && !list.length" class="wca-empty">
                <i class="bi bi-diagram-2"></i>
                <h4>Belum ada tipe tahap</h4>
            </div>
        </div>

        <AdminModal :busy="saving"
            :show="show"
            :title="editingId ? 'Ubah Tipe Tahap' : 'Tambah Tipe Kustom'"
            subtitle="Untuk kebutuhan tahap yang belum tercakup tipe bawaan"
            icon="bi-diagram-2"
            :save-label="editingId ? 'Perbarui' : 'Simpan Tipe'"
            @close="show = false"
            @save="save"
        >
            <div class="wca-fsection">
                <div class="wca-fsection__label"><i class="bi bi-diagram-2"></i> Detail Tipe</div>
                <div class="wca-form">
                    <div class="wca-frow">
                        <div>
                            <label class="wca-field-lbl">Nama Tipe</label
                            ><el-input v-model="form.nama" placeholder="mis. Assessment Center" />
                        </div>
                        <div>
                            <label class="wca-field-lbl">Perilaku</label>
                            <RefSelect type="perilaku" v-model="form.perilaku" placeholder="Pilih perilaku" />
                        </div>
                    </div>
                    <div>
                        <label class="wca-field-lbl">Ikon</label><IconPicker v-model="form.ikon" />
                    </div>
                    <div>
                        <label class="wca-field-lbl">Deskripsi</label
                        ><el-input
                            v-model="form.deskripsi"
                            type="textarea"
                            :rows="2"
                            placeholder="Jelaskan apa tahap ini & bagaimana dinilai"
                        />
                    </div>
                </div>
            </div>
        </AdminModal>

        <ConfirmModal
            :show="delShow"
            title="Hapus Tipe Tahap"
            :busy="deleting"
            confirm-label="Ya, Hapus"
            note="Tipe tahap kustom akan dihapus permanen."
            @cancel="delShow = false"
            @confirm="confirmDelete"
        >
            Yakin ingin menghapus tipe tahap <strong>{{ delTarget?.nama }}</strong
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
import RefSelect from '@career/RefSelect.vue';
import IconPicker from '@career/IconPicker.vue';
import { ingatModal } from '@utils/ingatModal';

const API = '/api/v1/master-tipe';
const CFG = { headers: { Accept: 'application/json' } };

export default {
    components: { Head, AdminModal, ConfirmModal, AuditStamp, RefSelect, IconPicker },
    // Modal di halaman ini selamat dari refresh — lihat @utils/ingatModal.
    mixins: [ingatModal('admin/master-tipe/masterTipe')],
    data() {
        return {
            list: [],
            loading: false,
            show: false,
            editingId: null,
            form: { nama: '', perilaku: '', ikon: '', deskripsi: '' },
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
                this.notice('Gagal memuat data tipe tahap.');
            } finally {
                this.loading = false;
            }
        },
        openCreate() {
            this.editingId = null;
            this.form = { nama: '', perilaku: '', ikon: '', deskripsi: '' };
            this.show = true;
        },
        openEdit(t) {
            this.editingId = t.id;
            this.form = { nama: t.nama, perilaku: t.perilaku || '', ikon: t.ikon || '', deskripsi: t.deskripsi || '' };
            this.show = true;
        },
        async save() {
            if (this.saving) return;
            if (!this.form.nama.trim()) return this.notice('Nama wajib diisi.');
            if (!this.form.perilaku) return this.notice('Perilaku wajib dipilih.');
            this.saving = true;
            const payload = {
                nama: this.form.nama,
                perilaku: this.form.perilaku,
                ikon: this.form.ikon,
                deskripsi: this.form.deskripsi,
            };
            try {
                if (this.editingId) {
                    await axios.put(`${API}/${this.editingId}`, payload, CFG);
                    this.notice('Tipe tahap diperbarui.');
                } else {
                    await axios.post(API, payload, CFG);
                    this.notice('Tipe tahap ditambahkan.');
                }
                this.show = false;
                await this.load();
            } catch (e) {
                this.notice(e.response?.data?.message || 'Gagal menyimpan.');
            } finally {
                this.saving = false;
            }
        },
        async setStatus(t, v) {
            const prev = t.status;
            t.status = v ? 'AKTIF' : 'NONAKTIF';
            try {
                await axios.patch(`${API}/${t.id}/toggle`, { aktif: v }, CFG);
                this.notice(`Tipe "${t.nama}" ${v ? 'diaktifkan' : 'dinonaktifkan'}.`);
            } catch (e) {
                t.status = prev;
                this.notice('Gagal mengubah status.');
            }
        },
        askRemove(t) {
            this.delTarget = t;
            this.delShow = true;
        },
        async confirmDelete() {
            if (this.deleting || !this.delTarget) return;
            const t = this.delTarget;
            this.deleting = true;
            try {
                await axios.delete(`${API}/${t.id}`, CFG);
                this.notice('Tipe tahap dihapus.');
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
