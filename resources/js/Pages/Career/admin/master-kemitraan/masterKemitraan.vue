<!-- WEB CAREER — Master Kemitraan / MoU (per-modul). DATA via web route + ResponseHelper (axios), bukan props Inertia. -->
<template>
    <Head title="Master Kemitraan / MoU" />
    <div class="wca">
        <div class="wca-phead">
            <div>
                <h1>Master Kemitraan / MoU</h1>
                <p>
                    Payung kerja sama dengan institusi/kampus untuk program <b>Internship</b> — No. MoU, jenis magang,
                    kuota, PIC, & masa berlaku.
                </p>
            </div>
            <div class="wca-phead__actions">
                <button class="wca-btn wca-btn--primary" @click="openCreate">
                    <i class="bi bi-plus-lg"></i> Tambah MoU
                </button>
            </div>
        </div>

        <div class="wca-note wca-note--info">
            <i class="bi bi-info-circle"></i>
            <span
                >MoU jadi <b>syarat</b> membuka program Internship: pembukaan magang mengacu ke MoU <b>Aktif</b> (kuota &
                masa berlaku diambil dari sini). <b>Jenis:</b> MSIB · Mandiri · PKL · Penelitian. Status <b>Draft</b> =
                penjajakan belum diteken.</span
            >
        </div>

        <div class="wca-toolbar">
            <div class="wca-search2">
                <i class="bi bi-search"></i
                ><input v-model="q" type="text" placeholder="Cari No. MoU / mitra / PIC…" />
            </div>
            <div class="wca-segt">
                <button class="wca-segt__it" :class="{ on: tab === '' }" @click="tab = ''">
                    <i class="bi bi-grid"></i> Semua
                </button>
                <button class="wca-segt__it" :class="{ on: tab === 'AKTIF' }" @click="tab = 'AKTIF'">
                    <i class="bi bi-check-circle"></i> Aktif
                </button>
                <button class="wca-segt__it" :class="{ on: tab === 'DRAFT' }" @click="tab = 'DRAFT'">
                    <i class="bi bi-pencil-square"></i> Draft
                </button>
                <button class="wca-segt__it" :class="{ on: tab === 'BERAKHIR' }" @click="tab = 'BERAKHIR'">
                    <i class="bi bi-archive"></i> Berakhir
                </button>
            </div>
        </div>

        <div v-loading="loading" class="wca-card">
            <div class="wca-card__body--flush">
                <div class="wca-tablewrap">
                    <table class="wca-table">
                        <thead>
                            <tr>
                                <th>MoU / Mitra</th>
                                <th>Kampus</th>
                                <th>Jenis</th>
                                <th>Kuota</th>
                                <th>Periode</th>
                                <th>Status</th>
                                <th>Dibuat Oleh</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="m in filtered" :key="m.id">
                                <td>
                                    <strong>{{ m.mitra }}</strong
                                    ><br />
                                    <small style="color: var(--muted); font-weight: 700"
                                        ><i class="bi bi-hash"></i>{{ m.nomor || '—' }}</small
                                    >
                                </td>
                                <td>
                                    <small v-if="m.kampusNama" style="color: var(--indigo); font-weight: 700"
                                        ><i class="bi bi-mortarboard"></i> {{ m.kampusNama }}</small
                                    >
                                    <small v-else style="color: var(--muted)">—</small>
                                </td>
                                <td>
                                    <span class="wca-badge" :class="jenisBadge(m.jenis)"
                                        ><i class="bi" :class="jenisIkon(m.jenis)"></i> {{ jenisLabel(m.jenis) }}</span
                                    >
                                </td>
                                <td>
                                    <div class="wca-kuota">
                                        <div class="wca-kuota__bar">
                                            <span
                                                :style="{
                                                    width: pct(m) + '%',
                                                    background:
                                                        m.terisi >= m.kuota && m.kuota > 0
                                                            ? '#ef4444'
                                                            : 'linear-gradient(90deg,#4f46e5,#7c3aed)',
                                                }"
                                            ></span>
                                        </div>
                                        <small>{{ m.terisi }}/{{ m.kuota }}</small>
                                    </div>
                                </td>
                                <td><small style="font-weight: 700">{{ periode(m) }}</small></td>
                                <td>
                                    <span class="wca-badge" :class="statusBadge(m.status)"
                                        ><i class="bi" :class="statusIkon(m.status)"></i> {{ statusLabel(m.status) }}</span
                                    >
                                </td>
                                <td><AuditStamp :by="m.createdBy" :at="m.createdAt" /></td>
                                <td>
                                    <div style="display: flex; gap: 0.35rem; justify-content: flex-end">
                                        <button class="wca-iconbtn" title="Ubah" @click="openEdit(m)">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <button
                                            class="wca-iconbtn wca-iconbtn--danger"
                                            title="Hapus"
                                            @click="askRemove(m)"
                                        >
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="!loading && !filtered.length">
                                <td colspan="8">
                                    <div class="wca-empty">
                                        <i class="bi bi-file-earmark-medical"></i>
                                        <h4>Tidak ada MoU cocok</h4>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Modal Create/Edit -->
        <AdminModal :busy="saving"
            :show="show"
            :title="editingId ? 'Ubah MoU' : 'MoU Baru'"
            subtitle="Kerja sama institusi untuk program Internship"
            icon="bi-file-earmark-medical"
            :save-label="editingId ? 'Perbarui' : 'Simpan MoU'"
            @close="show = false"
            @save="save"
        >
            <div class="wca-fsection">
                <div class="wca-fsection__label"><i class="bi bi-buildings"></i> Institusi Mitra</div>
                <div class="wca-form">
                    <div class="wca-frow">
                        <div>
                            <label class="wca-field-lbl">Kampus / Institusi</label>
                            <RefSelect
                                type="kampus"
                                v-model="form.kampusId"
                                placeholder="Pilih kampus (Master Kampus)"
                                clearable
                            />
                        </div>
                        <div>
                            <label class="wca-field-lbl">Nama Mitra</label
                            ><el-input v-model="form.mitra" placeholder="Universitas Sriwijaya" />
                        </div>
                    </div>
                    <div class="wca-frow">
                        <div>
                            <label class="wca-field-lbl">No. MoU</label
                            ><el-input v-model="form.nomor" placeholder="001/MoU/EVO-UNSRI/2026" />
                        </div>
                        <div>
                            <label class="wca-field-lbl">Jenis Kerja Sama</label>
                            <el-select filterable v-model="form.jenis" placeholder="Pilih jenis" style="width: 100%">
                                <el-option v-for="j in jenisOptions" :key="j.kode" :label="j.nama" :value="j.kode" />
                            </el-select>
                        </div>
                    </div>
                </div>
            </div>

            <div class="wca-fsection">
                <div class="wca-fsection__label"><i class="bi bi-sliders"></i> Kuota, PIC & Masa Berlaku</div>
                <div class="wca-form">
                    <div class="wca-frow">
                        <div>
                            <label class="wca-field-lbl">Kuota Peserta</label
                            ><el-input-number
                                v-model="form.kuota"
                                :min="0"
                                controls-position="right"
                                style="width: 100%"
                            />
                        </div>
                        <div>
                            <label class="wca-field-lbl">Sudah Terisi</label
                            ><el-input-number
                                v-model="form.terisi"
                                :min="0"
                                controls-position="right"
                                style="width: 100%"
                            />
                        </div>
                    </div>
                    <div class="wca-frow">
                        <div>
                            <label class="wca-field-lbl">PIC Mitra</label
                            ><el-input v-model="form.pic" placeholder="Dr. Bambang (CDC)" />
                        </div>
                        <div>
                            <label class="wca-field-lbl">Kontak PIC</label
                            ><el-input v-model="form.picKontak" placeholder="karir@kampus.ac.id" />
                        </div>
                    </div>
                    <div class="wca-frow">
                        <div>
                            <label class="wca-field-lbl">Tanggal Mulai</label
                            ><el-date-picker
                                v-model="form.mulai"
                                type="date"
                                value-format="YYYY-MM-DD"
                                placeholder="Pilih tanggal"
                                style="width: 100%"
                            />
                        </div>
                        <div>
                            <label class="wca-field-lbl">Tanggal Selesai</label
                            ><el-date-picker
                                v-model="form.selesai"
                                type="date"
                                value-format="YYYY-MM-DD"
                                placeholder="Pilih tanggal"
                                style="width: 100%"
                            />
                        </div>
                    </div>
                    <div>
                        <label class="wca-field-lbl">Status</label>
                        <el-select filterable v-model="form.status" placeholder="Pilih status" style="width: 100%">
                            <el-option v-for="s in statusOptions" :key="s.kode" :label="s.nama" :value="s.kode" />
                        </el-select>
                    </div>
                    <div>
                        <label class="wca-field-lbl">Catatan</label
                        ><el-input
                            v-model="form.catatan"
                            type="textarea"
                            :rows="2"
                            placeholder="Keterangan tambahan kerja sama"
                        />
                    </div>
                </div>
            </div>
        </AdminModal>

        <!-- Modal konfirmasi hapus -->
        <ConfirmModal
            :show="delShow"
            title="Hapus MoU"
            subtitle="Tindakan ini tidak dapat dibatalkan"
            icon="bi-file-earmark-medical"
            :busy="deleting"
            confirm-label="Ya, Hapus MoU"
            busy-label="Menghapus…"
            note="Data MoU akan dihapus permanen dari sistem."
            @cancel="delShow = false"
            @confirm="confirmDelete"
        >
            Yakin ingin menghapus MoU<br />
            <strong>{{ delTarget?.mitra }}</strong>
            <span style="color: #7c81a3; font-weight: 500">({{ delTarget?.nomor || '—' }})</span>?
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

const API = '/api/v1/master-kemitraan';
const CFG = { headers: { Accept: 'application/json' } };

const JENIS_OPTIONS = [
    { kode: 'MSIB', nama: 'MSIB — Magang Studi Independen Bersertifikat' },
    { kode: 'MANDIRI', nama: 'Mandiri — Magang Mandiri' },
    { kode: 'PKL', nama: 'PKL — Praktik Kerja Lapangan' },
    { kode: 'PENELITIAN', nama: 'Penelitian — Riset / Tugas Akhir' },
];
const STATUS_OPTIONS = [
    { kode: 'AKTIF', nama: 'Aktif — berlaku' },
    { kode: 'DRAFT', nama: 'Draft — penjajakan (belum diteken)' },
    { kode: 'BERAKHIR', nama: 'Berakhir — lewat masa berlaku' },
];

function blankForm() {
    return {
        kampusId: null,
        mitra: '',
        nomor: '',
        jenis: 'MSIB',
        kuota: 10,
        terisi: 0,
        pic: '',
        picKontak: '',
        mulai: null,
        selesai: null,
        status: 'DRAFT',
        catatan: '',
    };
}

export default {
    components: { Head, AdminModal, ConfirmModal, AuditStamp, RefSelect },
    // Modal di halaman ini selamat dari refresh — lihat @utils/ingatModal.
    mixins: [ingatModal('admin/master-kemitraan/masterKemitraan')],
    data() {
        return {
            jenisOptions: JENIS_OPTIONS,
            statusOptions: STATUS_OPTIONS,
            list: [],
            loading: false,
            q: '',
            tab: '',
            show: false,
            editingId: null,
            saving: false,
            form: blankForm(),
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
            return this.list.filter((m) => {
                if (this.tab && m.status !== this.tab) return false;
                if (!s) return true;
                return ((m.mitra || '') + ' ' + (m.nomor || '') + ' ' + (m.pic || '')).toLowerCase().includes(s);
            });
        },
    },
    mounted() {
        this.load();
    },
    methods: {
        jenisLabel(j) {
            return { MSIB: 'MSIB', MANDIRI: 'Mandiri', PKL: 'PKL', PENELITIAN: 'Penelitian' }[j] || j;
        },
        jenisBadge(j) {
            return { MSIB: 'wca-b--indigo', MANDIRI: 'wca-b--green', PKL: 'wca-b--amber', PENELITIAN: 'wca-b--sky' }[j] || 'wca-b--slate';
        },
        jenisIkon(j) {
            return { MSIB: 'bi-patch-check', MANDIRI: 'bi-person-arms-up', PKL: 'bi-tools', PENELITIAN: 'bi-journal-text' }[j] || 'bi-dot';
        },
        statusLabel(s) {
            return { AKTIF: 'Aktif', DRAFT: 'Draft', BERAKHIR: 'Berakhir' }[s] || s;
        },
        statusBadge(s) {
            return { AKTIF: 'wca-b--green', DRAFT: 'wca-b--amber', BERAKHIR: 'wca-b--slate' }[s] || 'wca-b--slate';
        },
        statusIkon(s) {
            return { AKTIF: 'bi-check-circle', DRAFT: 'bi-pencil-square', BERAKHIR: 'bi-archive' }[s] || 'bi-dot';
        },
        pct(m) {
            return m.kuota > 0 ? Math.min(100, Math.round((m.terisi / m.kuota) * 100)) : 0;
        },
        fmt(d) {
            if (!d) return null;
            const [y, mo, dd] = String(d).slice(0, 10).split('-');
            const bulan = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
            return `${parseInt(dd)} ${bulan[parseInt(mo) - 1]} ${y}`;
        },
        periode(m) {
            if (!m.mulai && !m.selesai) return 'Belum ditentukan';
            return `${this.fmt(m.mulai) || '—'} — ${this.fmt(m.selesai) || '—'}`;
        },
        async load() {
            this.loading = true;
            try {
                const res = await axios.get(API, CFG);
                this.list = res.data.result || [];
            } catch (e) {
                this.notice('Gagal memuat data kemitraan.');
            } finally {
                this.loading = false;
            }
        },
        openCreate() {
            this.editingId = null;
            this.form = blankForm();
            this.show = true;
        },
        openEdit(m) {
            this.editingId = m.id;
            this.form = {
                kampusId: m.kampusId ?? null,
                mitra: m.mitra || '',
                nomor: m.nomor || '',
                jenis: m.jenis || 'MSIB',
                kuota: m.kuota ?? 0,
                terisi: m.terisi ?? 0,
                pic: m.pic || '',
                picKontak: m.picKontak || '',
                mulai: m.mulai ? String(m.mulai).slice(0, 10) : null,
                selesai: m.selesai ? String(m.selesai).slice(0, 10) : null,
                status: m.status || 'DRAFT',
                catatan: m.catatan || '',
            };
            this.show = true;
        },
        buildPayload() {
            const f = this.form;
            return {
                nomor: f.nomor || null,
                mitra: f.mitra || '',
                kampusId: f.kampusId != null && f.kampusId !== '' ? parseInt(f.kampusId, 10) : null,
                jenis: f.jenis,
                kuota: f.kuota != null ? parseInt(f.kuota, 10) : 0,
                terisi: f.terisi != null ? parseInt(f.terisi, 10) : 0,
                pic: f.pic || null,
                picKontak: f.picKontak || null,
                mulai: f.mulai || null,
                selesai: f.selesai || null,
                status: f.status,
                catatan: f.catatan || null,
            };
        },
        async save() {
            if (this.saving) return;
            const f = this.form;
            if (!f.mitra || !f.mitra.trim()) return this.notice('Nama mitra wajib diisi.');
            if (!f.jenis) return this.notice('Jenis kerja sama wajib dipilih.');
            if (!f.status) return this.notice('Status wajib dipilih.');
            this.saving = true;
            const payload = this.buildPayload();
            try {
                if (this.editingId) {
                    await axios.put(`${API}/${this.editingId}`, payload, CFG);
                    this.notice('MoU diperbarui.');
                } else {
                    await axios.post(API, payload, CFG);
                    this.notice('MoU berhasil ditambahkan.');
                }
                this.show = false;
                await this.load();
            } catch (e) {
                this.notice(e.response?.data?.message || 'Gagal menyimpan MoU.');
            } finally {
                this.saving = false;
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
                this.notice('MoU dihapus.');
                this.delShow = false;
                this.delTarget = null;
                await this.load();
            } catch (e) {
                this.notice(e.response?.data?.message || 'Gagal menghapus MoU.');
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
