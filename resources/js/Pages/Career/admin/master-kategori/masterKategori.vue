<!-- WEB CAREER — Master Kategori (per-modul, pola Master Siklus). DATA via web route + ResponseHelper (axios), bukan props Inertia. -->
<template>
    <Head title="Master Kategori" />
    <div class="wca">
        <div class="wca-phead">
            <div>
                <h1>Master Kategori</h1>
                <p>
                    Kategori Talent Acquisition (Recruitment, MT, …). Menyetel <b>preset default</b> Mode & Alur saat
                    buat Program.
                </p>
            </div>
            <div class="wca-phead__actions">
                <button class="wca-btn wca-btn--primary" @click="openCreate">
                    <i class="bi bi-plus-lg"></i> Kategori Baru
                </button>
            </div>
        </div>

        <div class="wca-note wca-note--info">
            <i class="bi bi-info-circle"></i>
            <span
                >Kategori menyetel <b>preset default</b>: Mode dari <b>Master Mode</b>, Alur dari
                <b>Master Tahapan Seleksi</b>. Preset <b>bukan pengunci</b> — hanya auto-terisi & bisa diubah saat
                <b>Buat Program</b>.</span
            >
        </div>

        <div class="wca-toolbar">
            <div class="wca-search2">
                <i class="bi bi-search"></i><input v-model="q" type="text" placeholder="Cari kategori…" />
            </div>
        </div>

        <div v-loading="loading" class="wca-card">
            <div class="wca-card__body--flush">
                <div class="wca-tablewrap">
                    <table class="wca-table">
                        <thead>
                            <tr>
                                <th>Kategori</th>
                                <th>Jenis</th>
                                <th>Mode Default</th>
                                <th>Alur Default</th>
                                <th>Dibuat Oleh</th>
                                <th>Status</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="k in filtered" :key="k.id" :class="{ off: k.status !== 'AKTIF' }">
                                <td>
                                    <div style="display: flex; align-items: center; gap: 0.55rem">
                                        <span class="wca-dot" :style="{ background: k.warna || '#6366f1' }"></span>
                                        <div>
                                            <strong>{{ k.nama }}</strong>
                                            <span class="wca-badge wca-b--slate" style="margin-left: 0.2rem">{{
                                                k.warna || '#6366f1'
                                            }}</span>
                                            <span
                                                class="wca-badge"
                                                :class="k.sifat === 'SISTEM' ? 'wca-b--indigo' : 'wca-b--amber'"
                                                style="margin-left: 0.2rem"
                                                >{{ k.sifat === 'SISTEM' ? 'Sistem' : 'Kustom' }}</span
                                            ><br />
                                            <small style="color: var(--muted); font-weight: 700">{{ k.deskripsi }}</small>
                                        </div>
                                    </div>
                                </td>
                                <td><span class="wca-badge wca-b--slate">{{ k.kategori || '—' }}</span></td>
                                <td><span class="wca-badge wca-b--indigo">{{ k.modeDefault || '—' }}</span></td>
                                <td>
                                    <span class="wca-badge wca-b--sky"
                                        ><i class="bi bi-signpost-split"></i> {{ k.alurNama || k.alurDefault || '—' }}</span
                                    >
                                </td>
                                <td><AuditStamp :by="k.createdBy" :at="k.createdAt" :color="k.warna || '#6366f1'" /></td>
                                <td><el-switch :model-value="k.status === 'AKTIF'" @change="(v) => setStatus(k, v)" /></td>
                                <td>
                                    <div style="display: flex; gap: 0.5rem; align-items: center; justify-content: flex-end">
                                        <button class="wca-iconbtn" title="Ubah" @click="openEdit(k)">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <button class="wca-iconbtn wca-iconbtn--danger" title="Hapus" @click="askRemove(k)">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="!loading && !filtered.length">
                                <td colspan="7">
                                    <div class="wca-empty"><i class="bi bi-tags"></i><h4>Belum ada kategori</h4></div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <AdminModal :busy="saving"
            :show="show"
            :title="editingId ? 'Ubah Kategori' : 'Kategori Baru'"
            subtitle="Identitas + preset default (bisa diubah saat buat program)"
            icon="bi-tags"
            :save-label="editingId ? 'Perbarui' : 'Simpan Kategori'"
            @close="show = false"
            @save="save"
        >
            <div class="wca-fsection">
                <div class="wca-fsection__label"><i class="bi bi-card-heading"></i> Identitas Kategori</div>
                <div class="wca-form">
                    <div class="wca-frow">
                        <div>
                            <label class="wca-field-lbl">Nama Kategori</label
                            ><el-input v-model="form.nama" placeholder="Management Trainee" />
                        </div>
                        <div>
                            <label class="wca-field-lbl">Kategori</label>
                            <RefSelect type="talent" v-model="form.kategori" placeholder="Pilih kategori" />
                        </div>
                    </div>
                    <div class="wca-frow">
                        <div>
                            <label class="wca-field-lbl">Warna Label</label><br />
                            <el-color-picker v-model="form.warna" :predefine="palette" />
                        </div>
                        <div>
                            <label class="wca-field-lbl">Deskripsi</label
                            ><el-input v-model="form.deskripsi" type="textarea" :rows="2" placeholder="Ringkasan singkat" />
                        </div>
                    </div>
                </div>
            </div>

            <div class="wca-fsection">
                <div class="wca-fsection__label"><i class="bi bi-sliders"></i> Preset Default Program</div>
                <div class="wca-form">
                    <div class="wca-frow">
                        <div>
                            <label class="wca-field-lbl">Mode Pelaksanaan Default</label>
                            <RefSelect type="mode" v-model="form.modeDefault" placeholder="Pilih mode" clearable />
                        </div>
                        <div>
                            <label class="wca-field-lbl">Alur Seleksi Default</label>
                            <RefSelect type="alur" v-model="form.alurDefault" placeholder="Pilih alur" clearable />
                        </div>
                    </div>
                </div>
            </div>
        </AdminModal>

        <ConfirmModal
            :show="delShow"
            title="Hapus Kategori"
            :busy="deleting"
            confirm-label="Ya, Hapus"
            note="Kategori akan dihapus permanen."
            @cancel="delShow = false"
            @confirm="confirmDelete"
        >
            Yakin ingin menghapus kategori <strong>{{ delTarget?.nama }}</strong
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
import { ingatModal } from '@utils/ingatModal';

const API = '/api/v1/master-kategori';
const CFG = { headers: { Accept: 'application/json' } };

export default {
    components: { Head, AdminModal, ConfirmModal, AuditStamp, RefSelect },
    // Modal di halaman ini selamat dari refresh — lihat @utils/ingatModal.
    mixins: [ingatModal('admin/master-kategori/masterKategori')],
    data() {
        return {
            list: [],
            loading: false,
            q: '',
            show: false,
            editingId: null,
            form: { nama: '', kategori: '', warna: '#4f46e5', modeDefault: '', alurDefault: '', deskripsi: '' },
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
    computed: {
        filtered() {
            const s = this.q.trim().toLowerCase();
            return s
                ? this.list.filter((k) => (k.nama + ' ' + k.kode + ' ' + (k.kategori || '')).toLowerCase().includes(s))
                : this.list;
        },
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
                this.notice('Gagal memuat data kategori.');
            } finally {
                this.loading = false;
            }
        },
        openCreate() {
            this.editingId = null;
            this.form = { nama: '', kategori: '', warna: '#4f46e5', modeDefault: '', alurDefault: '', deskripsi: '' };
            this.show = true;
        },
        openEdit(k) {
            this.editingId = k.id;
            this.form = {
                nama: k.nama,
                kategori: k.kategori || '',
                warna: k.warna || '#4f46e5',
                modeDefault: k.modeDefault || '',
                alurDefault: k.alurDefault || '',
                deskripsi: k.deskripsi || '',
            };
            this.show = true;
        },
        async save() {
            if (this.saving) return;
            if (!this.form.nama.trim()) return this.notice('Nama wajib diisi.');
            if (!this.form.kategori) return this.notice('Kategori wajib dipilih.');
            this.saving = true;
            const payload = {
                nama: this.form.nama,
                kategori: this.form.kategori,
                warna: this.form.warna,
                modeDefault: this.form.modeDefault,
                alurDefault: this.form.alurDefault,
                deskripsi: this.form.deskripsi,
            };
            try {
                if (this.editingId) {
                    await axios.put(`${API}/${this.editingId}`, payload, CFG);
                    this.notice('Kategori diperbarui.');
                } else {
                    await axios.post(API, payload, CFG);
                    this.notice('Kategori ditambahkan.');
                }
                this.show = false;
                await this.load();
            } catch (e) {
                this.notice(e.response?.data?.message || 'Gagal menyimpan.');
            } finally {
                this.saving = false;
            }
        },
        async setStatus(k, v) {
            const prev = k.status;
            k.status = v ? 'AKTIF' : 'NONAKTIF';
            try {
                await axios.patch(`${API}/${k.id}/toggle`, { aktif: v }, CFG);
                this.notice(`Kategori "${k.nama}" ${v ? 'diaktifkan' : 'dinonaktifkan'}.`);
            } catch (e) {
                k.status = prev;
                this.notice('Gagal mengubah status.');
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
                this.notice('Kategori dihapus.');
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
@media (max-width: 720px) {
    .wca-frow {
        grid-template-columns: 1fr;
    }
    .wca-tablewrap {
        overflow-x: auto;
    }
}
</style>
