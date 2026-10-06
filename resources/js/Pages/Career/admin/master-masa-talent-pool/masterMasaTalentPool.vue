<!-- WEB CAREER — Master Masa Berlaku Talent Pool. Menentukan lama kartu Talent Pool
     berlaku sebelum kedaluwarsa (HARI/BULAN/TAHUN). Hanya SATU yang aktif. -->
<template>
    <Head title="Master Masa Berlaku Talent Pool" />
    <div class="wca">
        <div class="pkg-head">
            <div class="pkg-head__l">
                <div class="pkg-head__title">
                    <span class="pkg-head__ico mtp-ico"><i class="bi bi-hourglass-split"></i></span>
                    <h1>Masa Berlaku Talent Pool</h1>
                </div>
                <p>Berapa lama kartu Talent Pool berlaku sebelum otomatis kedaluwarsa. Yang <b>aktif</b> dipakai untuk kartu baru.</p>
            </div>
            <button class="pkg-newbtn" @click="openCreate"><i class="bi bi-plus-lg"></i> Masa Baru</button>
        </div>

        <div v-loading="loading" class="pkg-list">
            <div v-for="m in list" :key="m.id" class="mtp-card" :class="{ 'is-aktif': m.status === 'AKTIF' }">
                <div class="mtp-card__l">
                    <span class="mtp-dur"><b>{{ m.angka }}</b> <small>{{ satuanLabel(m.satuan) }}</small></span>
                    <div class="mtp-meta">
                        <strong>{{ m.nama }}</strong>
                        <span class="mtp-code">{{ m.kode }}</span>
                    </div>
                </div>
                <div class="mtp-card__r">
                    <span v-if="m.status === 'AKTIF'" class="mtp-badge"><i class="bi bi-check-circle-fill"></i> Dipakai</span>
                    <el-switch :model-value="m.status === 'AKTIF'" :disabled="m.status === 'AKTIF'" @change="(v) => setAktif(m, v)" />
                    <button class="pkg-ibtn" title="Ubah" @click="openEdit(m)"><i class="bi bi-pencil"></i></button>
                    <button class="pkg-ibtn pkg-ibtn--danger" :disabled="m.status === 'AKTIF'" title="Hapus" :onClick="m.status === 'AKTIF' ? null : () => askRemove(m)"><i class="bi bi-trash"></i></button>
                </div>
            </div>
            <div v-if="!loading && !list.length" class="pkg-empty"><i class="bi bi-hourglass"></i> Belum ada masa berlaku.</div>
        </div>

        <AdminModal :busy="saving" :show="show" :title="editingId ? 'Ubah Masa Berlaku' : 'Masa Berlaku Baru'" subtitle="Durasi sebelum kartu Talent Pool kedaluwarsa." icon="bi-hourglass-split" :save-label="editingId ? 'Perbarui' : 'Simpan'" @close="show = false" @save="save">
            <div class="wca-fsection">
                <div class="wca-form">
                    <div><label class="wca-field-lbl">Nama</label><el-input v-model="form.nama" placeholder="mis. 6 Bulan Standar" /></div>
                    <div class="wca-frow">
                        <div><label class="wca-field-lbl">Durasi</label><el-input-number v-model="form.angka" :min="1" :max="3650" controls-position="right" style="width:100%" /></div>
                        <div><label class="wca-field-lbl">Satuan</label>
                            <el-select v-model="form.satuan" style="width:100%">
                                <el-option label="Hari" value="HARI" />
                                <el-option label="Bulan" value="BULAN" />
                                <el-option label="Tahun" value="TAHUN" />
                            </el-select>
                        </div>
                    </div>
                    <label v-if="!editingId" class="mtp-check"><el-checkbox v-model="form.aktif">Langsung jadikan yang aktif (menonaktifkan lainnya)</el-checkbox></label>
                    <p class="mtp-note"><i class="bi bi-info-circle"></i> Kartu baru akan kedaluwarsa <b>{{ form.angka || '—' }} {{ satuanLabel(form.satuan) }}</b> setelah kandidat masuk pool.</p>
                </div>
            </div>
        </AdminModal>

        <ConfirmModal :show="delShow" title="Hapus Masa Berlaku" :busy="deleting" confirm-label="Ya, Hapus" danger @cancel="delShow = false" @confirm="confirmDelete">
            Hapus <strong>{{ delTarget?.nama }}</strong>?
        </ConfirmModal>

        <transition name="wca-toast"><div v-if="toast" class="wca-toast"><i class="bi bi-check-circle-fill"></i> {{ toast }}</div></transition>
    </div>
</template>

<script>
import axios from 'axios';
import { Head } from '@inertiajs/vue3';
import AdminModal from '@career/AdminModal.vue';
import ConfirmModal from '@career/ConfirmModal.vue';
import { ingatModal } from '@utils/ingatModal';

const API = '/api/v1/master-masa-talent-pool';
const CFG = { headers: { Accept: 'application/json' } };

export default {
    components: { Head, AdminModal, ConfirmModal },
    // Modal di halaman ini selamat dari refresh — lihat @utils/ingatModal.
    mixins: [ingatModal('admin/master-masa-talent-pool/masterMasaTalentPool')],
    data() {
        return {
            list: [],
            loading: false,
            show: false,
            editingId: null,
            form: { nama: '', angka: 6, satuan: 'BULAN', aktif: true },
            delShow: false,
            delTarget: null,
            deleting: false,
            saving: false,
            toast: '',
            tm: null,
        };
    },
    mounted() { this.load(); },
    methods: {
        satuanLabel(s) { return { HARI: 'Hari', BULAN: 'Bulan', TAHUN: 'Tahun' }[s] || s; },
        async load() {
            this.loading = true;
            try {
                this.list = (await axios.get(API, CFG)).data.result || [];
            } catch (e) {
                this.notice('Gagal memuat data.');
            } finally {
                this.loading = false;
            }
        },
        openCreate() {
            this.editingId = null;
            this.form = { nama: '', angka: 6, satuan: 'BULAN', aktif: true };
            this.show = true;
        },
        openEdit(m) {
            this.editingId = m.id;
            this.form = { nama: m.nama, angka: m.angka, satuan: m.satuan, aktif: m.status === 'AKTIF' };
            this.show = true;
        },
        async save() {
            if (this.saving) return;
            if (!this.form.nama.trim()) return this.notice('Nama wajib diisi.');
            if (!this.form.angka || this.form.angka < 1) return this.notice('Durasi minimal 1.');
            this.saving = true;
            try {
                if (this.editingId) {
                    await axios.put(`${API}/${this.editingId}`, this.form, CFG);
                    this.notice('Masa berlaku diperbarui.');
                } else {
                    await axios.post(API, this.form, CFG);
                    this.notice('Masa berlaku ditambahkan.');
                }
                this.show = false;
                await this.load();
            } catch (e) {
                this.notice(e.response?.data?.message || 'Gagal menyimpan.');
            } finally {
                this.saving = false;
            }
        },
        async setAktif(m, v) {
            if (!v) return; // menonaktifkan langsung tidak diperbolehkan — harus aktifkan yang lain
            try {
                await axios.patch(`${API}/${m.id}/toggle`, { aktif: true }, CFG);
                this.notice(`"${m.nama}" kini dipakai.`);
                await this.load();
            } catch (e) {
                this.notice('Gagal mengubah status.');
            }
        },
        askRemove(m) { this.delTarget = m; this.delShow = true; },
        async confirmDelete() {
            if (this.deleting || !this.delTarget) return;
            this.deleting = true;
            try {
                await axios.delete(`${API}/${this.delTarget.id}`, CFG);
                this.notice('Dihapus.');
                this.delShow = false;
                this.delTarget = null;
                await this.load();
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
.mtp-ico { background: linear-gradient(135deg, #fbbf24, #d97706) !important; }
.mtp-card { display: flex; align-items: center; justify-content: space-between; gap: 1rem; background: #fff; border: 1px solid rgba(15, 23, 42, .09); border-radius: 14px; padding: .8rem 1rem; margin-bottom: .6rem; box-shadow: 0 6px 18px rgba(15, 23, 42, .04); }
.mtp-card.is-aktif { border-color: rgba(217, 119, 6, .45); background: linear-gradient(180deg, rgba(234, 179, 8, .06), #fff); }
.mtp-card__l { display: flex; align-items: center; gap: .9rem; min-width: 0; }
.mtp-dur { flex: none; min-width: 4.2rem; height: 3.2rem; padding: 0 .7rem; border-radius: 12px; background: #fff7ed; border: 1px solid rgba(234, 179, 8, .3); display: flex; flex-direction: column; align-items: center; justify-content: center; color: #b45309; line-height: 1; }
.mtp-dur b { font-size: 1.35rem; font-weight: 800; }
.mtp-dur small { font-size: 10.5px; font-weight: 700; text-transform: uppercase; letter-spacing: .03em; }
.mtp-meta { min-width: 0; display: flex; flex-direction: column; }
.mtp-meta strong { font-size: 14px; color: #1e293b; }
.mtp-code { font-size: 11px; color: #94a3b8; font-weight: 700; }
.mtp-card__r { display: inline-flex; align-items: center; gap: .55rem; flex: none; }
.mtp-badge { display: inline-flex; align-items: center; gap: .3rem; font-size: 11px; font-weight: 800; color: #a16207; background: rgba(234, 179, 8, .16); border-radius: 999px; padding: 3px 10px; }
.mtp-check { display: block; margin-top: .3rem; }
.mtp-note { margin: .5rem 0 0; font-size: 11.5px; color: #64748b; display: flex; align-items: flex-start; gap: .35rem; }
.mtp-note > i { color: #d97706; margin-top: 1px; }
</style>
