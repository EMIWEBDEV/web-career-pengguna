<!-- WEB CAREER — Admin: Master Tipe Tahap (katalog jenis tahap untuk Alur Seleksi + deskripsi). -->
<template>
    <Head title="Master Tipe Tahap" />
    <div class="wca">
        <div class="wca-phead">
            <div>
                <h1>Master Tipe Tahap</h1>
                <p>Jenis tahap yang bisa dipilih saat menyusun <b>Alur Seleksi</b>. Tiap tipe punya perilaku berbeda (manual, tes online, atau verifikasi berkas).</p>
            </div>
            <div class="wca-phead__actions">
                <button class="wca-btn wca-btn--primary" @click="openCreate"><i class="bi bi-plus-lg"></i> Tipe Kustom</button>
            </div>
        </div>

        <div class="wca-note wca-note--info">
            <i class="bi bi-info-circle"></i>
            <span><b>Perilaku:</b> <b>Manual</b> = dinilai/diputuskan admin · <b>Tes CAT</b> = ujian online HCLearn (skor & hasil otomatis) · <b>Berkas</b> = pengumpulan/verifikasi dokumen. Tipe <b>Sistem</b> adalah bawaan (tak bisa dihapus).</span>
        </div>

        <div class="wca-toolbar">
            <div class="wca-search2"><i class="bi bi-search"></i><input v-model="q" type="text" placeholder="Cari tipe tahap…" /></div>
        </div>

        <div class="wca-tipegrid">
            <div v-for="t in filtered" :key="t.id" class="wca-tipecard" :class="{ off: t.status !== 'AKTIF' }">
                <div class="wca-tipecard__head">
                    <span class="wca-tipecard__ico" :class="periKelas(t.perilaku)"><i class="bi" :class="t.ikon"></i></span>
                    <div class="wca-tipecard__title">
                        <strong>{{ t.nama }}</strong>
                        <small>{{ t.kode }}</small>
                    </div>
                    <span class="wca-badge" :class="t.sifat === 'SISTEM' ? 'wca-b--slate' : 'wca-b--indigo'">{{ t.sifat === 'SISTEM' ? 'Sistem' : 'Kustom' }}</span>
                </div>
                <p class="wca-tipecard__desc">{{ t.deskripsi }}</p>
                <div class="wca-cardaudit"><AuditStamp :by="t.createdBy" :at="t.createdAt" /></div>
                <div class="wca-tipecard__foot">
                    <span class="wca-badge" :class="periBadge(t.perilaku)"><i class="bi" :class="periIkon(t.perilaku)"></i> {{ periLabel(t.perilaku) }}</span>
                    <div class="wca-tipecard__actions">
                        <el-switch :model-value="t.status === 'AKTIF'" @change="(v) => setStatus(t, v)" />
                        <button class="wca-iconbtn" title="Ubah" @click="openEdit(t)"><i class="bi bi-pencil"></i></button>
                        <button class="wca-iconbtn wca-iconbtn--danger" title="Hapus" @click="askRemove(t)"><i class="bi bi-trash"></i></button>
                    </div>
                </div>
            </div>
            <div v-if="!filtered.length" class="wca-empty"><i class="bi bi-diagram-2"></i><h4>Tidak ada tipe cocok</h4></div>
        </div>

        <!-- Modal tipe kustom -->
        <AdminModal :show="show" :title="editingId ? 'Ubah Tipe Tahap' : 'Tambah Tipe Kustom'" subtitle="Untuk kebutuhan tahap yang belum tercakup tipe bawaan" icon="bi-diagram-2" :save-label="editingId ? 'Perbarui' : 'Simpan Tipe'" @close="show = false" @save="save">
            <div class="wca-fsection">
                <div class="wca-fsection__label"><i class="bi bi-diagram-2"></i> Detail Tipe</div>
                <div class="wca-form">
                    <div class="wca-frow">
                        <div><label class="wca-field-lbl">Nama Tipe</label><el-input v-model="form.nama" placeholder="mis. Assessment Center" /></div>
                        <div><label class="wca-field-lbl">Perilaku</label>
                            <el-select filterable v-model="form.perilaku" placeholder="Pilih perilaku">
                                <el-option v-for="p in perilakuOptions" :key="p.kode" :label="p.nama" :value="p.kode" />
                            </el-select>
                        </div>
                    </div>
                    <div><label class="wca-field-lbl">Ikon</label><IconPicker v-model="form.ikon" /></div>
                    <div><label class="wca-field-lbl">Deskripsi</label><el-input v-model="form.deskripsi" type="textarea" :rows="2" placeholder="Jelaskan apa tahap ini & bagaimana dinilai" /></div>
                    <div class="wca-flow__note" style="margin:0"><i class="bi bi-info-circle"></i> Tipe kustom otomatis <b>Aktif</b> dan langsung bisa dipilih di builder Alur Seleksi.</div>
                </div>
            </div>
        </AdminModal>

        <ConfirmModal :show="delShow" title="Hapus Tipe Tahap" :busy="deleting" confirm-label="Ya, Hapus" note="Tipe tahap kustom akan dihapus permanen." @cancel="delShow = false" @confirm="confirmDelete">
            Yakin ingin menghapus tipe tahap <strong>{{ delTarget?.nama }}</strong>?
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
import IconPicker from '@career/IconPicker.vue';
import { ingatModal } from '@utils/ingatModal';

const API = '/api/v1/karir/master/rich';
const KEY = 'tipe';
const CFG = { headers: { Accept: 'application/json' } };

export default {
    components: { Head, AdminModal, ConfirmModal, AuditStamp, IconPicker },
    props: {
        tipe: { type: Array, default: () => [] },
        perilakuOptions: { type: Array, default: () => [] },
    },
    // Modal di halaman ini selamat dari refresh — lihat @utils/ingatModal.
    mixins: [ingatModal('admin/MasterTipeTahap')],
    data() {
        return {
            list: this.tipe.map((t) => ({ ...t })),
            q: '',
            show: false,
            editingId: null,
            form: { nama: '', perilaku: '', ikon: '', deskripsi: '' },
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
            return s ? this.list.filter((t) => (t.nama + ' ' + t.kode + ' ' + t.deskripsi).toLowerCase().includes(s)) : this.list;
        },
    },
    methods: {
        periLabel(p) { return this.perilakuOptions.find((o) => o.kode === p)?.nama || { MANUAL: 'Manual', CAT: 'Tes CAT', BERKAS: 'Berkas' }[p] || p; },
        periBadge(p) { return { MANUAL: 'wca-b--indigo', CAT: 'wca-b--amber', BERKAS: 'wca-b--sky' }[p] || 'wca-b--slate'; },
        periIkon(p) { return { MANUAL: 'bi-hand-index-thumb', CAT: 'bi-cpu', BERKAS: 'bi-folder-check' }[p] || 'bi-dot'; },
        periKelas(p) { return { MANUAL: 'peri-man', CAT: 'peri-cat', BERKAS: 'peri-doc' }[p] || ''; },
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
            if (!this.form.nama.trim()) return this.notice('Nama wajib diisi.');
            if (!this.form.perilaku) return this.notice('Perilaku wajib dipilih.');
            const payload = { nama: this.form.nama, perilaku: this.form.perilaku, ikon: this.form.ikon, deskripsi: this.form.deskripsi };
            try {
                if (this.editingId) {
                    const res = await axios.put(`${API}/${KEY}/${this.editingId}`, payload, CFG);
                    const i = this.list.findIndex((x) => x.id === this.editingId);
                    if (i >= 0) this.list.splice(i, 1, res.data.result);
                    this.notice('Tipe tahap diperbarui.');
                } else {
                    const res = await axios.post(`${API}/${KEY}`, payload, CFG);
                    this.list.push(res.data.result);
                    this.notice('Tipe tahap kustom ditambahkan.');
                }
                this.show = false;
            } catch (e) {
                this.notice(e.response?.data?.message || 'Gagal menyimpan.');
            }
        },
        async setStatus(t, v) {
            const prev = t.status;
            t.status = v ? 'AKTIF' : 'NONAKTIF';
            try {
                await axios.patch(`${API}/${KEY}/${t.id}/toggle`, { aktif: v }, CFG);
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
                await axios.delete(`${API}/${KEY}/${t.id}`, CFG);
                const i = this.list.findIndex((x) => x.id === t.id);
                if (i >= 0) this.list.splice(i, 1);
                this.notice('Tipe tahap kustom dihapus.');
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
.wca-cardaudit { margin-top: 10px; padding-top: 10px; border-top: 1px dashed rgba(11, 16, 51, .08); }
</style>
