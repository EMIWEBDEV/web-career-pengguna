<!-- WEB CAREER — Master Experience Level (tingkat pengalaman). Data via web route + ResponseHelper (axios). -->
<template>
    <Head title="Master Experience Level" />
    <div class="wca">
        <div class="wca-phead">
            <div>
                <h1>Master Experience Level</h1>
                <p>Tingkat pengalaman kerja (Fresh Graduate, Min. 1-2 Tahun, Min. 3-5 Tahun, …). Dipakai sebagai pilihan <b>Experience Level</b> saat menyusun MPP &amp; lowongan. Hanya level <b>Aktif</b> yang muncul di pilihan baru.</p>
            </div>
            <div class="wca-phead__actions">
                <button class="wca-btn wca-btn--primary" @click="openCreate"><i class="bi bi-plus-lg"></i> Level Baru</button>
            </div>
        </div>

        <div class="wca-note wca-note--info">
            <i class="bi bi-info-circle"></i>
            <span>Level yang sudah dipakai MPP <b>tidak bisa dihapus</b> — nonaktifkan saja supaya data lama tetap utuh dan level ini berhenti muncul sebagai pilihan baru.</span>
        </div>

        <div class="wca-stats">
            <div class="wca-stat">
                <div class="wca-stat__top"><span class="wca-stat__ico"><i class="bi bi-bar-chart-steps"></i></span></div>
                <div class="wca-stat__num">{{ ringkasan.total }}</div>
                <div class="wca-stat__label">Total Level</div>
            </div>
            <div class="wca-stat">
                <div class="wca-stat__top"><span class="wca-stat__ico" style="background: rgba(16, 185, 129, 0.12); color: #059669"><i class="bi bi-check-circle"></i></span></div>
                <div class="wca-stat__num">{{ ringkasan.aktif }}</div>
                <div class="wca-stat__label">Aktif (jadi pilihan)</div>
            </div>
            <div class="wca-stat">
                <div class="wca-stat__top"><span class="wca-stat__ico" style="background: rgba(148, 163, 184, 0.16); color: #475569"><i class="bi bi-eye-slash"></i></span></div>
                <div class="wca-stat__num">{{ ringkasan.nonaktif }}</div>
                <div class="wca-stat__label">Nonaktif (tersembunyi)</div>
            </div>
            <div class="wca-stat">
                <div class="wca-stat__top"><span class="wca-stat__ico" style="background: rgba(99, 102, 241, 0.12); color: #4f46e5"><i class="bi bi-clipboard-data"></i></span></div>
                <div class="wca-stat__num">{{ ringkasan.dipakai }}</div>
                <div class="wca-stat__label">Pemakaian di MPP</div>
            </div>
        </div>

        <div class="wca-toolbar">
            <div class="wca-search2">
                <i class="bi bi-search"></i>
                <input
                    v-model="filters.q" type="text" placeholder="Cari nama / keterangan…"
                    aria-label="Cari experience level" @input="cariTertunda" @keyup.enter="reload" @keyup.esc="bersihkanCari"
                />
                <!-- Tombol bersihkan: tersembunyi sampai ada isian, jadi tidak menambah beban visual toolbar. -->
                <button v-if="filters.q" class="xpl-clear" type="button" title="Bersihkan pencarian" @click="bersihkanCari">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
            <div class="wca-segt wca-segt--sm">
                <button
                    v-for="t in tabs" :key="t.key" class="wca-segt__it" :class="{ on: filters.status === t.key }"
                    :aria-pressed="filters.status === t.key" @click="pilihStatus(t.key)"
                >
                    <i class="bi" :class="t.icon"></i> {{ t.label }}
                    <span class="wca-tabn">{{ t.count }}</span>
                </button>
            </div>
        </div>

        <div class="wca-card">
            <div class="wca-card__body--flush">
                <div v-loading="loading" class="wca-tablewrap">
                    <table class="wca-table">
                        <thead>
                            <tr>
                                <th style="width: 60px">Jenjang</th>
                                <th>Experience Level</th>
                                <th>Keterangan</th>
                                <th style="width: 130px">
                                    <button class="xpl-sort" :class="{ on: sortBy === 'dipakai' }" @click="setSort('dipakai')">
                                        Dipakai <i class="bi" :class="sortIcon('dipakai')"></i>
                                    </button>
                                </th>
                                <th class="xpl-hide-sm" style="width: 190px">Dibuat</th>
                                <th style="width: 150px">Status</th>
                                <th style="width: 90px"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(x, i) in list" :key="x.id" :class="{ 'is-off': x.status !== 'AKTIF' }">
                                <!-- Nomor berjalan lintas halaman — indeks dalam halaman
                                     saja akan mengulang 1,2,3 di tiap halaman. -->
                                <td><span class="wca-badge wca-b--slate">{{ (page - 1) * perPage + i + 1 }}</span></td>
                                <td>
                                    <div class="xpl-name">
                                        <span class="xpl-ico"><i class="bi" :class="ikon(x.nama)"></i></span>
                                        <strong>{{ x.nama }}</strong>
                                    </div>
                                </td>
                                <td><span class="xpl-desc">{{ x.keterangan || '—' }}</span></td>
                                <td>
                                    <span v-if="x.dipakai" class="wca-badge wca-b--indigo" :title="`Dipakai ${x.dipakai} data MPP`">{{ x.dipakai }} MPP</span>
                                    <span v-else class="xpl-muted">—</span>
                                </td>
                                <td class="xpl-hide-sm"><AuditStamp :at="x.createdAt" :by="x.createdBy || 'SISTEM'" /></td>
                                <td>
                                    <div class="xpl-status">
                                        <el-switch :model-value="x.status === 'AKTIF'" @change="(v) => setStatus(x, v)" />
                                        <span class="xpl-status__lbl" :class="x.status === 'AKTIF' ? 'is-on' : 'is-off'">{{ x.status === 'AKTIF' ? 'Aktif' : 'Nonaktif' }}</span>
                                    </div>
                                </td>
                                <td>
                                    <div class="xpl-actions">
                                        <button class="wca-iconbtn" title="Ubah" @click="openEdit(x)"><i class="bi bi-pencil"></i></button>
                                        <button
                                            class="wca-iconbtn wca-iconbtn--danger"
                                            :disabled="!!x.dipakai"
                                            :title="x.dipakai ? `Tidak bisa dihapus — dipakai ${x.dipakai} data MPP` : 'Hapus'"
                                            :onClick="!!x.dipakai ? null : () => askRemove(x)"
                                        >
                                            <i class="bi" :class="x.dipakai ? 'bi-lock' : 'bi-trash'"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="!loading && !list.length">
                                <td colspan="7">
                                    <div class="wca-empty">
                                        <i class="bi" :class="adaFilter ? 'bi-funnel' : 'bi-bar-chart-steps'"></i>
                                        <h4>{{ adaFilter ? 'Tidak ada experience level cocok' : 'Belum ada experience level' }}</h4>
                                        <button v-if="adaFilter" class="wca-btn wca-btn--ghost xpl-empty__btn" type="button" @click="resetFilter">
                                            <i class="bi bi-arrow-counterclockwise"></i> Reset filter
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="xpl-pager">
                    <span class="xpl-pager__info">
                        Menampilkan <b>{{ list.length }}</b> dari <b>{{ total.toLocaleString('id-ID') }}</b> level
                    </span>
                    <!-- Pilihan terkecil 5 — sama dengan batas bawah yang dijaga
                         server (perPage dijepit 5–100), jadi UI dan backend
                         tidak bisa berbeda pendapat. -->
                    <el-pagination
                        background layout="prev, pager, next, sizes"
                        :total="total" :current-page="page" :page-size="perPage" :page-sizes="[5, 10, 25, 50, 100]"
                        @current-change="onPage" @size-change="onSize"
                    />
                </div>
            </div>
        </div>

        <AdminModal
            :busy="saving" :show="show" icon="bi-bar-chart-steps"
            :title="editingId ? 'Ubah Experience Level' : 'Experience Level Baru'"
            subtitle="Tingkat pengalaman untuk MPP & lowongan"
            :save-label="editingId ? 'Perbarui' : 'Simpan Level'"
            foot-note="Nama harus unik — dipakai sebagai label pilihan di MPP."
            @close="show = false" @save="save"
        >
            <div class="wca-fsection">
                <div class="wca-fsection__label"><i class="bi bi-bar-chart-steps"></i> Data Experience Level</div>
                <div class="wca-form">
                    <div>
                        <label class="wca-field-lbl">Nama Experience Level</label>
                        <el-input v-model="form.nama" maxlength="100" show-word-limit placeholder="mis. Min. 1 - 2 Tahun" @keyup.enter="save" />
                    </div>
                    <div>
                        <label class="wca-field-lbl">Keterangan <span class="xpl-hint">(opsional — dijelaskan singkat, tampil sebagai pendamping nama)</span></label>
                        <el-input v-model="form.keterangan" type="textarea" :rows="2" maxlength="100" show-word-limit placeholder="mis. Pengalaman 1-3 tahun di bidang terkait" />
                    </div>
                </div>
            </div>
            <div v-if="editingId && editingDipakai" class="wca-note wca-note--info xpl-note-modal">
                <i class="bi bi-clipboard-data"></i>
                <span>Level ini dipakai <b>{{ editingDipakai }} data MPP</b>. Mengubah namanya ikut mengubah label pada data tersebut.</span>
            </div>
        </AdminModal>

        <ConfirmModal
            :show="delShow" :busy="deleting" title="Hapus Experience Level" confirm-label="Ya, Hapus Level"
            note="Experience level akan dihapus permanen. Hanya level yang belum dipakai MPP yang bisa dihapus."
            @cancel="delShow = false" @confirm="confirmDelete"
        >
            Yakin ingin menghapus <strong>{{ delTarget?.nama }}</strong>?
        </ConfirmModal>

        <transition name="wca-toast"><div v-if="toast" class="wca-toast"><i class="bi bi-check-circle-fill"></i> {{ toast }}</div></transition>
    </div>
</template>

<script>
import axios from 'axios';
import { Head } from '@inertiajs/vue3';
import AdminModal from '@career/AdminModal.vue';
import AuditStamp from '@career/AuditStamp.vue';
import ConfirmModal from '@career/ConfirmModal.vue';
import { ingatModal } from '@utils/ingatModal';

const API = '/api/v1/master-experience-level';
const CFG = { headers: { Accept: 'application/json' } };

// Ikon hanya hiasan sisi tampilan — tabelnya memang tidak punya kolom ikon.
// Dicocokkan dari nama supaya barisnya mudah dibedakan sekilas.
const IKON = [
    [/(fresh|lulusan baru|entry)/i, 'bi-mortarboard'],
    [/(trainee|management trainee|mt)/i, 'bi-person-workspace'],
    [/(senior|manajer|manager|kepala|head)/i, 'bi-person-badge'],
    [/(direktur|director|eksekutif|executive)/i, 'bi-award'],
    [/./, 'bi-bar-chart-steps'],
];

const RINGKASAN_KOSONG = {
    total: 0, aktif: 0, nonaktif: 0, dipakai: 0,
    status: { semua: 0, aktif: 0, nonaktif: 0 },
};

export default {
    components: { Head, AdminModal, AuditStamp, ConfirmModal },
    // Modal di halaman ini selamat dari refresh — lihat @utils/ingatModal.
    mixins: [ingatModal('admin/master-experience-level/masterExperienceLevel')],
    data() {
        return {
            list: [], total: 0, page: 1, perPage: 25, loading: false,
            ringkasan: { ...RINGKASAN_KOSONG },
            filters: { q: '', status: '' },
            // 'urutan' = jenjang asli (id) — data ini berjenjang, bukan abjad. Dikerjakan server.
            sortBy: 'urutan', sortDir: 'asc',
            show: false, editingId: null, editingDipakai: 0, saving: false,
            form: { nama: '', keterangan: '' },
            delShow: false, delTarget: null, deleting: false,
            toast: '', tm: null, dtm: null,
        };
    },
    computed: {
        adaFilter() {
            return this.filters.q.trim() !== '' || this.filters.status !== '';
        },
        /** Cacah datang dari server: klien cuma memegang satu halaman. */
        tabs() {
            const s = this.ringkasan.status || RINGKASAN_KOSONG.status;
            return [
                { key: '', label: 'Semua', icon: 'bi-collection', count: s.semua },
                { key: 'AKTIF', label: 'Aktif', icon: 'bi-check-circle', count: s.aktif },
                { key: 'NONAKTIF', label: 'Nonaktif', icon: 'bi-eye-slash', count: s.nonaktif },
            ];
        },
    },
    mounted() {
        this.load();
    },
    methods: {
        ikon(nama) {
            const cocok = IKON.find(([pola]) => pola.test(nama || ''));
            return cocok ? cocok[1] : 'bi-bar-chart-steps';
        },
        /** Setiap perubahan filter/urut kembali ke halaman 1 — halaman 3 dari hasil lama tidak bermakna. */
        reload() {
            this.page = 1;
            this.load();
        },
        cariTertunda() {
            if (this.dtm) clearTimeout(this.dtm);
            this.dtm = setTimeout(() => this.reload(), 400);
        },
        bersihkanCari() {
            this.filters.q = '';
            this.reload();
        },
        pilihStatus(key) {
            this.filters.status = key;
            this.reload();
        },
        resetFilter() {
            this.filters = { q: '', status: '' };
            this.reload();
        },
        onPage(p) {
            this.page = p;
            this.load();
        },
        onSize(s) {
            this.perPage = s;
            this.page = 1;
            this.load();
        },
        setSort(kolom) {
            if (this.sortBy === kolom) {
                this.sortDir = this.sortDir === 'asc' ? 'desc' : 'asc';
            } else {
                this.sortBy = kolom;
                this.sortDir = kolom === 'dipakai' ? 'desc' : 'asc';
            }
            this.reload();
        },
        sortIcon(kolom) {
            if (this.sortBy !== kolom) return 'bi-arrow-down-up';
            return this.sortDir === 'asc' ? 'bi-sort-down-alt' : 'bi-sort-down';
        },
        async load() {
            this.loading = true;
            try {
                const params = {
                    q: this.filters.q.trim(),
                    status: this.filters.status,
                    sortBy: this.sortBy,
                    sortDir: this.sortDir,
                    page: this.page,
                    perPage: this.perPage,
                };
                const res = await axios.get(API, { ...CFG, params });
                const r = res.data.result || {};
                this.list = r.rows || [];
                this.total = r.total || 0;
                this.ringkasan = r.ringkasan || { ...RINGKASAN_KOSONG };

                // Halaman terakhir bisa jadi kosong setelah baris dihapus/disaring.
                if (!this.list.length && this.page > 1) {
                    this.page = 1;
                    await this.load();
                }
            } catch (e) {
                this.notice('Gagal memuat data experience level.');
            } finally {
                this.loading = false;
            }
        },
        openCreate() {
            this.editingId = null;
            this.editingDipakai = 0;
            this.form = { nama: '', keterangan: '' };
            this.show = true;
        },
        openEdit(x) {
            this.editingId = x.id;
            this.editingDipakai = x.dipakai || 0;
            this.form = { nama: x.nama, keterangan: x.keterangan || '' };
            this.show = true;
        },
        async save() {
            if (this.saving) return;
            if (!this.form.nama.trim()) return this.notice('Nama experience level wajib diisi.');
            this.saving = true;
            const payload = { nama: this.form.nama.trim(), keterangan: this.form.keterangan.trim() };
            try {
                if (this.editingId) {
                    await axios.put(`${API}/${this.editingId}`, payload, CFG);
                    this.notice('Experience level diperbarui.');
                } else {
                    await axios.post(API, payload, CFG);
                    this.notice('Experience level ditambahkan.');
                }
                this.show = false;
                await this.load();
            } catch (e) {
                this.notice(e.response?.data?.message || 'Gagal menyimpan experience level.');
            } finally {
                this.saving = false;
            }
        },
        async setStatus(x, v) {
            const prev = x.status;
            x.status = v ? 'AKTIF' : 'NONAKTIF';
            try {
                await axios.patch(`${API}/${x.id}/toggle`, { aktif: v }, CFG);
                this.notice(`"${x.nama}" ${v ? 'diaktifkan' : 'dinonaktifkan'}.`);
                // Muat ulang: kartu statistik & cacah tab dihitung server,
                // jadi tidak bisa disesuaikan dari sini.
                await this.load();
            } catch (err) {
                x.status = prev;
                this.notice(err.response?.data?.message || 'Gagal mengubah status.');
            }
        },
        askRemove(x) {
            if (x.dipakai) return this.notice(`"${x.nama}" dipakai ${x.dipakai} data MPP — nonaktifkan saja.`);
            this.delTarget = x;
            this.delShow = true;
        },
        async confirmDelete() {
            if (this.deleting || !this.delTarget) return;
            this.deleting = true;
            try {
                await axios.delete(`${API}/${this.delTarget.id}`, CFG);
                this.notice('Experience level dihapus.');
                this.delShow = false;
                this.delTarget = null;
                await this.load();
            } catch (e) {
                this.notice(e.response?.data?.message || 'Gagal menghapus experience level.');
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
.xpl-name { display: flex; align-items: center; gap: 10px; min-width: 0; }
.xpl-ico {
    flex: 0 0 auto;
    width: 32px;
    height: 32px;
    border-radius: 10px;
    display: grid;
    place-items: center;
    background: rgba(99, 102, 241, 0.1);
    color: #4f46e5;
    font-size: 14px;
}
.xpl-name strong { font-size: 13px; color: #0f1235; }
.xpl-desc { color: #64748b; font-size: 12.5px; }
.xpl-muted { color: #94a3b8; }
.xpl-hint { font-weight: 400; color: #94a3b8; font-size: 11px; }
.xpl-note-modal { margin-top: 0.9rem; }

.xpl-sort {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    border: none;
    background: none;
    padding: 0;
    font: inherit;
    color: inherit;
    cursor: pointer;
}
.xpl-sort i { font-size: 11px; color: #94a3b8; }
.xpl-sort:hover, .xpl-sort.on { color: #4f46e5; }
.xpl-sort.on i { color: #4f46e5; }

.xpl-status { display: flex; align-items: center; gap: 10px; --el-switch-on-color: #059669; }
.xpl-status__lbl { font-size: 12px; font-weight: 700; }
.xpl-status__lbl.is-on { color: #059669; }
.xpl-status__lbl.is-off { color: #94a3b8; }

.xpl-actions { display: flex; gap: 0.35rem; justify-content: flex-end; }
.xpl-actions .wca-iconbtn:disabled { opacity: 0.45; cursor: not-allowed; }

.xpl-clear {
    flex: none;
    display: grid;
    place-items: center;
    width: 20px;
    height: 20px;
    padding: 0;
    border: none;
    border-radius: 999px;
    background: rgba(148, 163, 184, 0.22);
    color: #475569;
    font-size: 9px;
    cursor: pointer;
}
.xpl-clear:hover { background: rgba(239, 68, 68, 0.15); color: #dc2626; }
.xpl-empty__btn { margin-top: 0.9rem; }

.xpl-pager {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 0.6rem;
    padding: 0.85rem 1.1rem;
    border-top: 1px solid var(--line, rgba(11, 16, 51, 0.08));
}
.xpl-pager__info { font-size: 12px; font-weight: 600; color: #64748b; }
.xpl-pager__info b { color: #0f1235; }

tr.is-off .xpl-ico { background: rgba(148, 163, 184, 0.16); color: #64748b; }
tr.is-off .xpl-name strong { color: #64748b; }

@media (max-width: 900px) {
    .xpl-hide-sm { display: none; }
}
@media (max-width: 640px) {
    .xpl-status__lbl { display: none; }
    .xpl-pager { justify-content: center; }
    .xpl-pager__info { width: 100%; text-align: center; }
}
</style>
