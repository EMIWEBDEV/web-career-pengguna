<!-- WEB CAREER — Master Mode Keputusan (bagaimana sebuah tahap menyimpulkan). DATA via /api/v1/master-mode-keputusan. -->
<template>
    <Head title="Master Mode Keputusan" />
    <div class="wca">
        <div class="wca-phead">
            <div>
                <h1>Master Mode Keputusan</h1>
                <p>
                    Menentukan <b>bagaimana sebuah tahap menyimpulkan</b> dari hasil tes-tesnya: menunggu semua,
                    maju otomatis, atau diputus admin. Dibaca langsung oleh mesin seleksi — <b>bukan hardcode</b>.
                </p>
            </div>
            <div class="wca-phead__actions">
                <button class="wca-btn wca-btn--primary" @click="openCreate"><i class="bi bi-plus-lg"></i> Mode Kustom</button>
            </div>
        </div>

        <div class="wca-note wca-note--info">
            <i class="bi bi-info-circle"></i>
            <span>
                Tiap mode punya 4 perilaku: <b>Tunggu</b> (kapan dievaluasi), <b>Auto Lanjut</b> (maju sendiri?),
                <b>Syarat Lulus</b> (semua tes penentu / admin), dan <b>Auto Gugur</b> (gagal → langsung gugur).
                Tes ber-peran <b>Informatif</b> tidak pernah menentukan lulus.
            </span>
        </div>

        <div v-loading="loading" class="wca-tipegrid">
            <div v-for="m in list" :key="m.id" class="wca-tipecard" :class="{ off: m.status !== 'AKTIF' }">
                <div class="wca-tipecard__head">
                    <span class="wca-tipecard__ico" :style="{ background: (m.warna || '#6366f1') + '22', color: m.warna || '#6366f1' }">
                        <i class="bi" :class="m.ikon"></i>
                    </span>
                    <div class="wca-tipecard__title">
                        <strong>{{ m.nama }}</strong><small>{{ m.kode }}</small>
                    </div>
                    <span class="wca-badge" :class="m.sifat === 'SISTEM' ? 'wca-b--slate' : 'wca-b--indigo'">
                        {{ m.sifat === 'SISTEM' ? 'Sistem' : 'Kustom' }}
                    </span>
                </div>
                <p class="mk-label">{{ m.label }}</p>
                <p class="wca-tipecard__desc">{{ m.deskripsi }}</p>

                <!-- Ringkasan perilaku: inilah yang benar-benar dieksekusi mesin. -->
                <ul class="mk-rules">
                    <li><i class="bi bi-hourglass-split"></i> Tunggu: <b>{{ labelTunggu(m.tunggu) }}</b></li>
                    <li><i class="bi bi-check2-circle"></i> Syarat lulus: <b>{{ m.syaratLulus === 'MANUAL' ? 'Admin menilai' : 'Semua tes penentu lulus' }}</b></li>
                    <li :class="m.autoLanjut ? 'is-on' : 'is-off'"><i class="bi" :class="m.autoLanjut ? 'bi-fast-forward-fill' : 'bi-pause-circle'"></i> {{ m.autoLanjut ? 'Maju otomatis' : 'Tunggu keputusan admin' }}</li>
                    <li :class="m.autoGugur ? 'is-warn' : 'is-off'"><i class="bi" :class="m.autoGugur ? 'bi-x-octagon-fill' : 'bi-dash-circle'"></i> {{ m.autoGugur ? 'Gagal → gugur otomatis' : 'Gagal tidak menggugurkan' }}</li>
                </ul>

                <div class="wca-cardaudit"><AuditStamp :by="m.createdBy" :at="m.createdAt" :color="m.warna || '#6366f1'" /></div>
                <div class="wca-tipecard__foot">
                    <span class="wca-badge wca-b--slate"><i class="bi bi-hash"></i> {{ m.kode }}</span>
                    <div class="wca-tipecard__actions">
                        <el-switch :model-value="m.status === 'AKTIF'" @change="(v) => setStatus(m, v)" />
                        <button class="wca-iconbtn" title="Ubah" @click="openEdit(m)"><i class="bi bi-pencil"></i></button>
                        <button v-if="m.sifat !== 'SISTEM'" class="wca-iconbtn wca-iconbtn--danger" title="Hapus" @click="askRemove(m)">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </div>
            </div>
            <div v-if="!loading && !list.length" class="wca-empty">
                <i class="bi bi-diagram-3"></i>
                <h4>Belum ada mode keputusan</h4>
            </div>
        </div>

        <AdminModal :busy="saving"
            :show="show"
            :title="editingId ? 'Ubah Mode Keputusan' : 'Tambah Mode Kustom'"
            subtitle="Perilaku di bawah ini yang dijalankan mesin seleksi"
            icon="bi-diagram-3"
            :save-label="editingId ? 'Perbarui' : 'Simpan Mode'"
            @close="show = false"
            @save="save"
        >
            <div class="wca-fsection">
                <div class="wca-fsection__label"><i class="bi bi-card-text"></i> Identitas</div>
                <div class="wca-form">
                    <div class="wca-frow">
                        <div><label class="wca-field-lbl">Nama Singkat</label><el-input v-model="form.nama" placeholder="mis. Otomatis — semua lulus" /></div>
                        <div><label class="wca-field-lbl">Warna</label><el-color-picker v-model="form.warna" :predefine="palette" /></div>
                    </div>
                    <div><label class="wca-field-lbl">Label Pilihan</label><el-input v-model="form.label" placeholder="Kalimat yang tampil di builder alur" /></div>
                    <div><label class="wca-field-lbl">Ikon</label><IconPicker v-model="form.ikon" /></div>
                    <div>
                        <label class="wca-field-lbl">Deskripsi (efeknya)</label>
                        <el-input v-model="form.deskripsi" type="textarea" :rows="2" placeholder="Jelaskan konsekuensinya bagi kandidat & admin" />
                    </div>
                </div>
            </div>

            <div class="wca-fsection">
                <div class="wca-fsection__label"><i class="bi bi-sliders"></i> Perilaku Mesin</div>
                <div class="wca-form">
                    <div class="wca-frow">
                        <div>
                            <label class="wca-field-lbl">Tunggu</label>
                            <el-select v-model="form.tunggu" style="width:100%">
                                <el-option label="Semua tes wajib selesai" value="SEMUA" />
                                <el-option label="Tes wajib terakhir selesai" value="TERAKHIR" />
                                <el-option label="Segera (hasil pertama)" value="SEGERA" />
                            </el-select>
                        </div>
                        <div>
                            <label class="wca-field-lbl">Syarat Lulus</label>
                            <el-select v-model="form.syaratLulus" style="width:100%">
                                <el-option label="Semua tes penentu lulus" value="SEMUA_PENENTU" />
                                <el-option label="Admin yang menilai" value="MANUAL" />
                            </el-select>
                        </div>
                    </div>
                    <label class="mk-check"><el-checkbox v-model="form.autoLanjut">Maju otomatis ke tahap berikutnya bila syarat terpenuhi</el-checkbox></label>
                    <label class="mk-check"><el-checkbox v-model="form.autoGugur">Gugurkan otomatis bila ada tes penentu yang gagal</el-checkbox></label>
                    <p class="mk-note">{{ ringkasanPerilaku }}</p>
                </div>
            </div>
        </AdminModal>

        <ConfirmModal
            :show="delShow" title="Hapus Mode Keputusan" :busy="deleting" confirm-label="Ya, Hapus"
            note="Mode kustom akan dihapus permanen." @cancel="delShow = false" @confirm="confirmDelete"
        >
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
import IconPicker from '@career/IconPicker.vue';
import { ingatModal } from '@utils/ingatModal';

const API = '/api/v1/master-mode-keputusan';
const CFG = { headers: { Accept: 'application/json' } };
const KOSONG = { nama: '', label: '', ikon: '', warna: '#4f46e5', deskripsi: '', tunggu: 'SEMUA', syaratLulus: 'SEMUA_PENENTU', autoLanjut: true, autoGugur: true };

export default {
    components: { Head, AdminModal, ConfirmModal, AuditStamp, IconPicker },
    // Modal di halaman ini selamat dari refresh — lihat @utils/ingatModal.
    mixins: [ingatModal('admin/master-mode-keputusan/masterModeKeputusan')],
    data() {
        return {
            list: [], loading: false, show: false, editingId: null,
            form: { ...KOSONG },
            palette: ['#059669', '#0ea5e9', '#4f46e5', '#7c3aed', '#d97706', '#dc2626', '#64748b'],
            delShow: false, delTarget: null, deleting: false, saving: false, toast: '', tm: null,
        };
    },
    computed: {
        /** Kalimat efek — supaya admin paham akibat kombinasi flag tanpa menebak. */
        ringkasanPerilaku() {
            const tunggu = { SEMUA: 'setelah SEMUA tes wajib selesai', TERAKHIR: 'setelah tes wajib TERAKHIR selesai', SEGERA: 'begitu hasil pertama masuk' }[this.form.tunggu];
            const syarat = this.form.syaratLulus === 'MANUAL' ? 'admin yang menilai hasilnya' : 'tahap lulus bila semua tes penentu lulus';
            const lanjut = this.form.autoLanjut ? 'lalu maju otomatis' : 'lalu menunggu keputusan admin';
            const gugur = this.form.autoGugur ? ' Ada tes penentu gagal → kandidat langsung gugur.' : '';
            return `Tahap dievaluasi ${tunggu}; ${syarat}, ${lanjut}.${gugur}`;
        },
    },
    mounted() { this.load(); },
    methods: {
        labelTunggu(t) { return { SEMUA: 'Semua tes wajib', TERAKHIR: 'Tes wajib terakhir', SEGERA: 'Hasil pertama' }[t] || t; },
        async load() {
            this.loading = true;
            try { this.list = (await axios.get(API, CFG)).data.result || []; }
            catch (e) { this.notice('Gagal memuat data mode keputusan.'); }
            finally { this.loading = false; }
        },
        openCreate() { this.editingId = null; this.form = { ...KOSONG }; this.show = true; },
        openEdit(m) {
            this.editingId = m.id;
            this.form = {
                nama: m.nama, label: m.label || '', ikon: m.ikon || '', warna: m.warna || '#4f46e5',
                deskripsi: m.deskripsi || '', tunggu: m.tunggu || 'SEMUA', syaratLulus: m.syaratLulus || 'SEMUA_PENENTU',
                autoLanjut: !!m.autoLanjut, autoGugur: !!m.autoGugur,
            };
            this.show = true;
        },
        async save() {
            if (this.saving) return;
            if (!this.form.nama.trim()) return this.notice('Nama wajib diisi.');
            if (!this.form.label.trim()) return this.notice('Label pilihan wajib diisi.');
            this.saving = true;
            try {
                if (this.editingId) { await axios.put(`${API}/${this.editingId}`, this.form, CFG); this.notice('Mode keputusan diperbarui.'); }
                else { await axios.post(API, this.form, CFG); this.notice('Mode keputusan ditambahkan.'); }
                this.show = false;
                await this.load();
            } catch (e) { this.notice(e.response?.data?.message || 'Gagal menyimpan.'); }
            finally { this.saving = false; }
        },
        async setStatus(m, v) {
            const prev = m.status;
            m.status = v ? 'AKTIF' : 'NONAKTIF';
            try { await axios.patch(`${API}/${m.id}/toggle`, { aktif: v }, CFG); this.notice(`Mode "${m.nama}" ${v ? 'diaktifkan' : 'dinonaktifkan'}.`); }
            catch (e) { m.status = prev; this.notice('Gagal mengubah status.'); }
        },
        askRemove(m) { this.delTarget = m; this.delShow = true; },
        async confirmDelete() {
            if (this.deleting || !this.delTarget) return;
            this.deleting = true;
            try {
                await axios.delete(`${API}/${this.delTarget.id}`, CFG);
                this.notice('Mode keputusan dihapus.');
                this.delShow = false; this.delTarget = null;
                await this.load();
            } catch (e) { this.notice(e.response?.data?.message || 'Gagal menghapus.'); }
            finally { this.deleting = false; }
        },
        notice(x) { this.toast = x; if (this.tm) clearTimeout(this.tm); this.tm = setTimeout(() => (this.toast = ''), 3000); },
    },
};
</script>

<style scoped>
.mk-label { margin: 2px 0 4px; font-weight: 700; font-size: 13px; color: #334155; }
.mk-rules { list-style: none; margin: .6rem 0 0; padding: 0; display: flex; flex-direction: column; gap: .28rem; }
.mk-rules li { display: flex; align-items: center; gap: .4rem; font-size: 11.5px; font-weight: 600; color: #475569; }
.mk-rules li.is-on { color: #047857; }
.mk-rules li.is-warn { color: #b45309; }
.mk-rules li.is-off { color: #94a3b8; }
.mk-check { display: block; margin-top: .35rem; }
.mk-note { margin: .55rem 0 0; font-size: 11.5px; line-height: 1.55; color: #64748b; }
.wca-cardaudit { margin-top: 10px; padding-top: 10px; border-top: 1px dashed rgba(11, 16, 51, 0.08); }
</style>
