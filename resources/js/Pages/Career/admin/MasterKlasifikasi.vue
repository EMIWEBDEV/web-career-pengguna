<!-- WEB CAREER — Admin: Master Kategori (label kategori TA + preset default program). CRUD real DB, Element Plus. -->
<template>
    <Head title="Master Kategori" />
    <div class="wca">
        <div class="wca-phead">
            <div>
                <h1>Master Kategori</h1>
                <p>Kategori Talent Acquisition (Recruitment, MT, …). Baris di sini <b>adalah</b> kategorinya — menyetel <b>preset default</b> saat buat Program.</p>
            </div>
            <div class="wca-phead__actions">
                <button class="wca-btn wca-btn--primary" @click="openCreate"><i class="bi bi-plus-lg"></i> Kategori Baru</button>
            </div>
        </div>

        <div class="wca-note wca-note--info">
            <i class="bi bi-info-circle"></i>
            <span>Kategori <b>Sistem</b> (Recruitment, MT) tak bisa dihapus & identitasnya terkunci — tapi presetnya tetap bisa disesuaikan. Preset <b>bukan pengunci</b>: hanya auto-terisi & bisa diubah saat <b>Buat Program</b> (mis. MT tertentu jadi Rolling). Mode dari <b>Master Mode</b>, Alur dari <b>Master Tahapan Seleksi</b> (arahkan kursor ke alur untuk intip tahapannya). <b>Sumber Kandidat</b> diatur di <b>Pembukaan Program</b>.</span>
        </div>

        <div class="wca-toolbar">
            <div class="wca-search2"><i class="bi bi-search"></i><input v-model="q" type="text" placeholder="Cari kategori…" /></div>
        </div>

        <div class="wca-card">
            <div class="wca-card__body--flush">
                <div class="wca-tablewrap">
                    <table class="wca-table">
                        <thead><tr><th>Kategori</th><th>Mode Default</th><th>Alur Default</th><th>Dibuat Oleh</th><th></th></tr></thead>
                        <tbody>
                            <tr v-for="k in filtered" :key="k.id" :class="{ off: k.status !== 'AKTIF' }">
                                <td>
                                    <div style="display:flex;align-items:center;gap:.55rem">
                                        <span class="wca-dot" :style="{ background:(k.warna||'#6366f1') }"></span>
                                        <div>
                                            <strong>{{ k.nama }}</strong>
                                            <span class="wca-badge wca-b--slate" style="margin-left:.2rem">{{ k.kode }}</span>
                                            <span class="wca-badge" :class="k.sifat === 'SISTEM' ? 'wca-b--indigo' : 'wca-b--amber'" style="margin-left:.2rem"><i class="bi" :class="k.sifat === 'SISTEM' ? 'bi-shield-lock' : 'bi-pencil-square'"></i> {{ k.sifat === 'SISTEM' ? 'Sistem' : 'Kustom' }}</span><br />
                                            <small style="color:var(--muted);font-weight:700">{{ k.deskripsi }}</small>
                                        </div>
                                    </div>
                                </td>
                                <td><span class="wca-badge" :class="k.modeDefault === 'TERSTRUKTUR' ? 'wca-b--indigo' : 'wca-b--slate'">{{ modeLabel(k.modeDefault) }}</span></td>
                                <td>
                                    <el-tooltip placement="top" effect="light" :show-after="80">
                                        <template #content>
                                            <div style="max-width:280px">
                                                <div style="font-weight:900;margin-bottom:.35rem">{{ k.alurNama }}</div>
                                                <ol style="margin:0;padding-left:1.15rem;font-weight:700;line-height:1.7">
                                                    <li v-for="(st, si) in alurStages(k.alurDefault)" :key="si">{{ st }}</li>
                                                </ol>
                                            </div>
                                        </template>
                                        <span class="wca-badge wca-b--sky" style="cursor:help"><i class="bi bi-signpost-split"></i> {{ k.alurNama }} <i class="bi bi-info-circle" style="margin-left:.15rem"></i></span>
                                    </el-tooltip>
                                </td>
                                <td><AuditStamp :by="k.createdBy" :at="k.createdAt" :color="k.warna || '#6366f1'" /></td>
                                <td>
                                    <div style="display:flex;gap:.5rem;align-items:center;justify-content:flex-end">
                                        <el-switch :model-value="k.status === 'AKTIF'" @change="(v) => setStatus(k, v)" />
                                        <button class="wca-iconbtn" title="Ubah preset" @click="openEdit(k)"><i class="bi bi-pencil"></i></button>
                                        <button class="wca-iconbtn wca-iconbtn--danger" title="Hapus" @click="askRemove(k)"><i class="bi bi-trash"></i></button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="!filtered.length"><td colspan="5"><div class="wca-empty"><i class="bi bi-tags"></i><h4>Belum ada kategori</h4></div></td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Modal Create/Edit -->
        <AdminModal :show="show" :title="editingId ? 'Ubah Kategori' : 'Kategori Baru'" subtitle="Identitas + preset default (bisa diubah saat buat program)" icon="bi-tags" :save-label="editingId ? 'Perbarui' : 'Simpan Kategori'" @close="show = false" @save="save">
            <!-- 1. Identitas -->
            <div class="wca-fsection">
                <div class="wca-fsection__label"><i class="bi bi-card-heading"></i> Identitas Kategori</div>
                <div class="wca-form">
                    <div class="wca-frow">
                        <div><label class="wca-field-lbl">Nama Kategori</label><el-input v-model="form.nama" placeholder="Management Trainee" /></div>
                        <div>
                            <label class="wca-field-lbl">Kategori</label>
                            <el-select filterable v-model="form.kategori" placeholder="Pilih kategori" :disabled="editingIsSystem" style="width:100%">
                                <el-option v-for="opt in kategoriOptions" :key="opt.kode" :label="opt.nama" :value="opt.kode" />
                            </el-select>
                            <small v-if="editingIsSystem" style="display:block;margin-top:.3rem;color:var(--muted);font-weight:700;font-size:.72rem"><i class="bi bi-shield-lock"></i> Identitas kategori sistem terkunci — preset di bawah tetap bisa diubah.</small>
                        </div>
                    </div>
                    <div class="wca-frow">
                        <div><label class="wca-field-lbl">Warna Label</label><br />
                            <el-color-picker v-model="form.warna" :predefine="palette" />
                        </div>
                        <div><label class="wca-field-lbl">Deskripsi</label><el-input v-model="form.deskripsi" type="textarea" :rows="2" placeholder="Ringkasan singkat" /></div>
                    </div>
                </div>
            </div>

            <!-- 2. Preset default program -->
            <div class="wca-fsection">
                <div class="wca-fsection__label"><i class="bi bi-sliders"></i> Preset Default Program <span style="font-weight:700;color:var(--muted);text-transform:none;letter-spacing:0">— auto-isi saat buat program</span></div>
                <div class="wca-form">
                    <div><label class="wca-field-lbl">Alur Seleksi Default</label>
                        <el-select filterable v-model="form.alurDefault" placeholder="Pilih alur (dari Master Tahapan Seleksi)" style="width:100%">
                            <el-option v-for="a in alurOptions" :key="a.id" :label="`${a.nama} (${a.kategori})`" :value="a.kode" />
                        </el-select>
                        <div v-if="alurStages(form.alurDefault).length" class="wca-peek">
                            <i class="bi bi-eye"></i> <b>Tahapan:</b>
                            <template v-for="(st, si) in alurStages(form.alurDefault)" :key="si"><span class="wca-peek__st">{{ st }}</span><i v-if="si < alurStages(form.alurDefault).length - 1" class="bi bi-chevron-right wca-peek__sep"></i></template>
                        </div>
                    </div>
                    <div><label class="wca-field-lbl">Mode Pelaksanaan Default</label>
                        <el-select filterable v-model="form.modeDefault" placeholder="Pilih mode (dari Master Mode)" style="width:100%">
                            <el-option v-for="mo in modeOptions" :key="mo.kode" :label="mo.nama" :value="mo.kode" />
                        </el-select>
                        <small style="display:block;margin-top:.3rem;color:var(--muted);font-weight:700;font-size:.72rem"><i class="bi bi-info-circle"></i> Hanya default cerdas — bisa dioverride saat Buat Program (mis. MT tertentu jadi Rolling).</small>
                    </div>
                </div>
            </div>
        </AdminModal>

        <ConfirmModal :show="delShow" title="Hapus Kategori" :busy="deleting" confirm-label="Ya, Hapus" note="Kategori kustom akan dihapus permanen." @cancel="delShow = false" @confirm="confirmDelete">
            Yakin ingin menghapus kategori <strong>{{ delTarget?.nama }}</strong>?
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

const API = '/api/v1/karir/master/rich';
const KEY = 'kategori';
const CFG = { headers: { Accept: 'application/json' } };

export default {
    components: { Head, AdminModal, ConfirmModal, AuditStamp },
    props: {
        klasifikasi: { type: Array, default: () => [] },
        alurOptions: { type: Array, default: () => [] },
        modeOptions: { type: Array, default: () => [] },
        kategoriOptions: { type: Array, default: () => [] },
    },
    // Modal di halaman ini selamat dari refresh — lihat @utils/ingatModal.
    mixins: [ingatModal('admin/MasterKlasifikasi')],
    data() {
        return {
            list: this.klasifikasi.map((k) => ({ ...k })),
            q: '',
            show: false,
            editingId: null,
            editingSifat: 'KUSTOM',
            form: { nama: '', kategori: 'REKRUTMEN', warna: '#0ea5e9', modeDefault: 'ROLLING', alurDefault: '', deskripsi: '' },
            palette: ['#4f46e5', '#7c3aed', '#0ea5e9', '#059669', '#d97706', '#f59e0b', '#dc2626', '#64748b', '#0f172a'],
            delShow: false,
            delTarget: null,
            deleting: false,
            toast: '',
            tm: null,
        };
    },
    computed: {
        editingIsSystem() {
            return this.editingSifat === 'SISTEM';
        },
        filtered() {
            const s = this.q.trim().toLowerCase();
            return s ? this.list.filter((k) => (k.nama + ' ' + k.kode).toLowerCase().includes(s)) : this.list;
        },
    },
    methods: {
        alurStages(kode) {
            return this.alurOptions.find((a) => a.kode === kode)?.stages ?? [];
        },
        modeLabel(m) {
            return this.modeOptions.find((x) => x.kode === m)?.nama || { TERSTRUKTUR: 'Terstruktur', ROLLING: 'Rolling' }[m] || m;
        },
        openCreate() {
            this.editingId = null;
            this.editingSifat = 'KUSTOM';
            this.form = { nama: '', kategori: 'REKRUTMEN', warna: '#0ea5e9', modeDefault: 'ROLLING', alurDefault: this.alurOptions[0]?.kode ?? '', deskripsi: '' };
            this.show = true;
        },
        openEdit(k) {
            this.editingId = k.id;
            this.editingSifat = k.sifat || 'KUSTOM';
            this.form = { nama: k.nama, kategori: k.kategori, warna: k.warna, modeDefault: k.modeDefault, alurDefault: k.alurDefault, deskripsi: k.deskripsi };
            this.show = true;
        },
        async save() {
            if (!this.form.nama.trim()) return this.notice('Nama wajib diisi.');
            if (!this.form.kategori) return this.notice('Kategori wajib dipilih.');
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
                    const res = await axios.put(`${API}/${KEY}/${this.editingId}`, payload, CFG);
                    const i = this.list.findIndex((x) => x.id === this.editingId);
                    if (i >= 0) this.list.splice(i, 1, res.data.result);
                    this.notice('Kategori diperbarui.');
                } else {
                    const res = await axios.post(`${API}/${KEY}`, payload, CFG);
                    this.list.unshift(res.data.result);
                    this.notice('Kategori kustom ditambahkan.');
                }
                this.show = false;
            } catch (e) {
                this.notice(e.response?.data?.message || 'Gagal menyimpan.');
            }
        },
        async setStatus(k, v) {
            const prev = k.status;
            k.status = v ? 'AKTIF' : 'NONAKTIF';
            try {
                await axios.patch(`${API}/${KEY}/${k.id}/toggle`, { aktif: v }, CFG);
                this.notice(`Kategori "${k.nama}" ${v ? 'diaktifkan' : 'dinonaktifkan'}.`);
            } catch (e) {
                k.status = prev;
                this.notice(e.response?.data?.message || 'Gagal mengubah status.');
            }
        },
        askRemove(k) {
            if (k.sifat === 'SISTEM') return;
            this.delTarget = k;
            this.delShow = true;
        },
        async confirmDelete() {
            if (this.deleting || !this.delTarget) return;
            const k = this.delTarget;
            this.deleting = true;
            try {
                await axios.delete(`${API}/${KEY}/${k.id}`, CFG);
                const i = this.list.findIndex((x) => x.id === k.id);
                if (i >= 0) this.list.splice(i, 1);
                this.notice('Kategori kustom dihapus.');
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
