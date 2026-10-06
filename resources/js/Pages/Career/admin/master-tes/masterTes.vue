<!-- WEB CAREER — Master Jenis Tes (induk-detail: Jenis Tes + Paket). DATA dari DB via /api/v1/master-tes. -->
<template>
    <Head title="Master Jenis Tes" />
    <div class="wca">
        <div class="wca-phead">
            <div>
                <h1>Master Jenis Tes</h1>
                <p>Katalog metode tes untuk alur seleksi. Tes <b>CAT</b> berjalan di platform — durasi & skor diatur di sana. Tiap jenis tes bisa punya beberapa <b>paket</b>.</p>
            </div>
            <div class="wca-phead__actions">
                <button class="wca-btn wca-btn--primary" @click="openCreate"><i class="bi bi-plus-lg"></i> Jenis Tes Baru</button>
            </div>
        </div>

        <div v-loading="loading" class="wca-card">
            <div class="wca-card__body--flush">
                <div class="wca-tablewrap">
                    <table class="wca-table">
                        <thead><tr><th>Nama Tes</th><th>Kategori</th><th>Metode</th><th>Pelaksana / Platform</th><th>Paket</th><th>Status</th><th></th></tr></thead>
                        <tbody>
                            <tr v-for="t in list" :key="t.id">
                                <td>
                                    <strong>{{ t.nama }}</strong><br />
                                    <small style="color:var(--muted);font-weight:700">{{ t.kode }}</small>
                                    <div style="margin-top:.3rem"><AuditStamp :by="t.createdBy" :at="t.createdAt" /></div>
                                </td>
                                <td><span class="wca-badge wca-b--indigo">{{ t.kategori || '—' }}</span></td>
                                <td>
                                    <span class="wca-badge" :class="t.cat ? 'wca-b--amber' : 'wca-b--slate'">
                                        <i class="bi" :class="t.cat ? 'bi-pc-display' : 'bi-clipboard-check'"></i> {{ t.metode === 'CAT' ? 'CAT (Online)' : 'Manual' }}
                                    </span>
                                    <span v-if="t.cat" class="wca-badge wca-b--green" style="margin-left:.3rem"><i class="bi bi-cpu"></i> Cat</span>
                                </td>
                                <td>
                                    <span class="wca-badge" :class="t.cat ? 'wca-b--indigo' : 'wca-b--slate'">
                                        <i class="bi" :class="t.cat ? 'bi-hdd-network' : 'bi-person-workspace'"></i> {{ t.pelaksana || '—' }}
                                    </span>
                                </td>
                                <td>
                                    <div v-if="(t.paket || []).length" class="mts-paketlist">
                                        <div v-for="(p, i) in t.paket" :key="i" class="mts-paketchip">
                                            <strong>{{ p.nama }}</strong>
                                            <span class="mts-paketchip__meta">{{ p.durasi ? p.durasi + ' menit' : '—' }}<template v-if="(p.alat || []).length"> · {{ (p.alat || []).join(', ') }}</template></span>
                                        </div>
                                    </div>
                                    <small v-else style="color:var(--muted)">Belum ada paket</small>
                                </td>
                                <td>
                                    <div style="display:flex;align-items:center;gap:.45rem">
                                        <el-switch :model-value="t.status === 'AKTIF'" @change="(v) => setStatus(t, v)" />
                                        <span style="font-size:.74rem;font-weight:800" :style="{ color: t.status === 'AKTIF' ? '#059669' : 'var(--muted)' }">{{ t.status === 'AKTIF' ? 'Aktif' : 'Nonaktif' }}</span>
                                    </div>
                                </td>
                                <td>
                                    <div style="display:flex;gap:.35rem;justify-content:flex-end">
                                        <button class="wca-iconbtn" title="Ubah" @click="openEdit(t)"><i class="bi bi-pencil"></i></button>
                                        <button class="wca-iconbtn wca-iconbtn--danger" title="Hapus" @click="askRemove(t)"><i class="bi bi-trash"></i></button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="!loading && !list.length"><td colspan="7"><div class="wca-empty"><i class="bi bi-clipboard-x"></i><h4>Belum ada jenis tes</h4></div></td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Modal buat/ubah jenis tes -->
        <AdminModal :busy="saving" :show="show" :title="editingId ? 'Ubah Jenis Tes' : 'Tambah Jenis Tes'" subtitle="Definisikan metode tes lalu susun paket-nya" icon="bi-ui-checks-grid" lg :save-label="editingId ? 'Perbarui' : 'Simpan Tes'" @close="show = false" @save="save">
            <div class="wca-fsection">
                <div class="wca-fsection__label"><i class="bi bi-ui-checks-grid"></i> Detail Tes</div>
                <div class="wca-form">
                    <div class="wca-frow">
                        <div><label class="wca-field-lbl">Nama Tes</label><el-input v-model="form.nama" placeholder="Tes Bahasa Inggris" /></div>
                        <div><label class="wca-field-lbl">Kategori</label><el-input v-model="form.kategori" placeholder="Bahasa" /></div>
                    </div>
                    <div><label class="wca-field-lbl">Metode Pelaksanaan</label>
                        <RefSelect type="metode" v-model="form.metode" placeholder="Pilih metode" />
                    </div>
                    <div>
                        <label class="wca-field-lbl">{{ form.metode === 'CAT' ? 'Platform CAT' : 'Pelaksana / Penilai' }}</label>
                        <el-input v-model="form.pelaksana" :placeholder="form.metode === 'CAT' ? 'HCLearn' : 'Asesor Eksternal / Internal'" />
                    </div>
                    <div>
                        <label class="wca-field-lbl">Masa Berlaku Nilai (bulan)</label>
                        <el-input-number v-model="form.masaBerlakuBulan" :min="0" :max="120" controls-position="right" style="width:100%" placeholder="mis. 6" />
                        <small style="display:block;margin-top:.3rem;color:var(--muted);font-weight:600;font-size:.72rem">Dalam rentang ini, nilai tes lama <b>dipakai ulang</b> — kandidat tak perlu tes lagi. Kosong/0 = selalu tes baru.</small>
                    </div>
                </div>
            </div>

            <div class="wca-fsection">
                <div class="wca-fsection__label" style="display:flex;align-items:center;justify-content:space-between">
                    <span><i class="bi bi-boxes"></i> Paket ({{ form.paket.length }})</span>
                    <button class="wca-btn wca-btn--soft wca-btn--sm" type="button" @click="addPaket"><i class="bi bi-plus-circle"></i> Tambah Paket</button>
                </div>
                <div class="mts-paket">
                    <div v-for="(p, i) in form.paket" :key="i" class="mts-prow">
                        <span class="mts-prow__num">{{ i + 1 }}</span>
                        <div class="mts-prow__grid">
                            <div><label class="wca-field-lbl">Nama Paket</label><el-input v-model="p.nama" placeholder="mis. Paket A" /></div>
                            <div><label class="wca-field-lbl">Alat</label>
                                <el-select v-model="p.alat" multiple filterable allow-create default-first-option :reserve-keyword="false" placeholder="Ketik alat lalu Enter" style="width:100%">
                                    <el-option v-for="a in p.alat" :key="a" :label="a" :value="a" />
                                </el-select>
                            </div>
                            <div><label class="wca-field-lbl">Durasi (menit)</label><el-input-number v-model="p.durasi" :min="0" :step="5" controls-position="right" style="width:100%" /></div>
                        </div>
                        <button class="wca-iconbtn wca-iconbtn--danger" type="button" title="Hapus paket" @click="removePaket(i)"><i class="bi bi-trash"></i></button>
                    </div>
                    <div v-if="!form.paket.length" class="mts-empty">Belum ada paket — klik "Tambah Paket".</div>
                </div>
            </div>
        </AdminModal>

        <ConfirmModal :show="delShow" title="Hapus Jenis Tes" :busy="deleting" confirm-label="Ya, Hapus" note="Jenis tes & seluruh paket-nya akan dihapus permanen." @cancel="delShow = false" @confirm="confirmDelete">
            Yakin ingin menghapus jenis tes <strong>{{ delTarget?.nama }}</strong>?
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
import RefSelect from '@career/RefSelect.vue';
import { ingatModal } from '@utils/ingatModal';

const API = '/api/v1/master-tes';
const CFG = { headers: { Accept: 'application/json' } };

export default {
    components: { Head, AdminModal, ConfirmModal, AuditStamp, RefSelect },
    // Modal di halaman ini selamat dari refresh — lihat @utils/ingatModal.
    mixins: [ingatModal('admin/master-tes/masterTes')],
    data() {
        return {
            list: [],
            loading: false,
            show: false,
            editingId: null,
            form: { nama: '', kategori: '', metode: 'CAT', pelaksana: '', cat: true, masaBerlakuBulan: null, paket: [] },
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
                this.notice('Gagal memuat data jenis tes.');
            } finally {
                this.loading = false;
            }
        },
        openCreate() {
            this.editingId = null;
            this.form = { nama: '', kategori: '', metode: 'CAT', pelaksana: '', cat: true, masaBerlakuBulan: null, paket: [] };
            this.show = true;
        },
        openEdit(t) {
            this.editingId = t.id;
            this.form = {
                nama: t.nama,
                kategori: t.kategori || '',
                metode: t.metode || (t.cat ? 'CAT' : 'MANUAL'),
                pelaksana: t.pelaksana || '',
                cat: !!t.cat,
                masaBerlakuBulan: t.masaBerlakuBulan ?? null,
                paket: (t.paket || []).map((p) => ({ nama: p.nama, alat: [...(p.alat || [])], durasi: p.durasi ?? 0 })),
            };
            this.show = true;
        },
        addPaket() { this.form.paket.push({ nama: '', alat: [], durasi: 0 }); },
        removePaket(i) { this.form.paket.splice(i, 1); },
        async save() {
            if (this.saving) return;
            if (!this.form.nama.trim()) return this.notice('Nama tes wajib diisi.');
            if (!this.form.metode) return this.notice('Metode wajib dipilih.');
            if (this.form.paket.some((p) => !p.nama || !p.nama.trim())) return this.notice('Setiap paket wajib punya nama.');
            this.saving = true;
            this.form.cat = this.form.metode === 'CAT';
            const payload = {
                nama: this.form.nama,
                kategori: this.form.kategori,
                metode: this.form.metode,
                pelaksana: this.form.pelaksana,
                cat: this.form.metode === 'CAT',
                masaBerlakuBulan: this.form.masaBerlakuBulan ?? null,
                paket: this.form.paket.map((p) => ({ nama: p.nama, alat: p.alat || [], durasi: p.durasi ?? 0 })),
            };
            try {
                if (this.editingId) {
                    await axios.put(`${API}/${this.editingId}`, payload, CFG);
                    this.notice('Jenis tes diperbarui.');
                } else {
                    await axios.post(API, payload, CFG);
                    this.notice('Jenis tes ditambahkan.');
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
                this.notice(`Tes "${t.nama}" ${v ? 'diaktifkan' : 'dinonaktifkan'}.`);
            } catch (e) {
                t.status = prev;
                this.notice('Gagal mengubah status.');
            }
        },
        askRemove(t) { this.delTarget = t; this.delShow = true; },
        async confirmDelete() {
            if (this.deleting || !this.delTarget) return;
            const t = this.delTarget;
            this.deleting = true;
            try {
                await axios.delete(`${API}/${t.id}`, CFG);
                this.notice('Jenis tes dihapus.');
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
.mts-paketlist { display: flex; flex-direction: column; gap: .35rem; }
.mts-paketchip { display: flex; flex-direction: column; background: #f8fafc; border: 1px solid rgba(11, 16, 51, .07); border-radius: 8px; padding: .35rem .55rem; }
.mts-paketchip strong { font-size: 13px; }
.mts-paketchip__meta { font-size: 11px; color: #94a3b8; font-weight: 700; }
.mts-paket { display: flex; flex-direction: column; gap: .6rem; }
.mts-prow { display: flex; align-items: flex-start; gap: .6rem; background: #f8fafc; border: 1px solid rgba(11, 16, 51, .07); border-radius: 12px; padding: .7rem; }
.mts-prow__num { flex: 0 0 auto; width: 26px; height: 26px; border-radius: 8px; background: #4f46e5; color: #fff; display: grid; place-items: center; font: 700 12px 'Plus Jakarta Sans', sans-serif; margin-top: 1.5rem; }
.mts-prow__grid { flex: 1; display: grid; grid-template-columns: 1fr 1.6fr 1fr; gap: .5rem; min-width: 0; }
.mts-empty { color: #94a3b8; font-size: 13px; padding: .8rem; }
@media (max-width: 900px) {
    .mts-prow__grid { grid-template-columns: 1fr 1fr; }
}
@media (max-width: 560px) {
    .mts-prow__grid { grid-template-columns: 1fr; }
    .mts-prow__num { display: none; }
}
</style>
