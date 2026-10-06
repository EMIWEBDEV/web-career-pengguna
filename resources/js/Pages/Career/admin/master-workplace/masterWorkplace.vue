<!-- WEB CAREER — Master Workplace (tipe LOKASI kerja). Data via web route + ResponseHelper (axios).
     Bentuk halaman sengaja dibuat kembar dengan Master Experience Level: dua-duanya
     master pendamping Detail_MPP dengan skema yang sama persis, jadi admin tidak
     perlu belajar dua tata letak untuk pekerjaan yang sama. -->
<template>
    <Head title="Master Workplace" />
    <div class="wca">
        <div class="wca-phead">
            <div>
                <h1>Master Workplace</h1>
                <p>Tipe lokasi kerja (On-site/WFO, Hybrid, Remote/WFH, …). Dipakai sebagai pilihan <b>Workplace Type</b> saat menyusun MPP &amp; lowongan. Hanya tipe <b>Aktif</b> yang muncul di pilihan baru.</p>
            </div>
            <div class="wca-phead__actions">
                <button class="wca-btn wca-btn--primary" @click="openCreate"><i class="bi bi-plus-lg"></i> Tipe Baru</button>
            </div>
        </div>

        <div class="wca-note wca-note--info">
            <i class="bi bi-info-circle"></i>
            <span>Tipe yang sudah dipakai lowongan <b>tidak bisa dihapus</b> — nonaktifkan saja supaya data lama tetap utuh dan tipe ini berhenti muncul sebagai pilihan baru.</span>
        </div>

        <div class="wca-stats">
            <div class="wca-stat">
                <div class="wca-stat__top"><span class="wca-stat__ico"><i class="bi bi-geo-alt"></i></span></div>
                <div class="wca-stat__num">{{ ringkasan.total }}</div>
                <div class="wca-stat__label">Total Tipe</div>
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
                    aria-label="Cari tipe lokasi kerja" @input="cariTertunda" @keyup.enter="reload" @keyup.esc="bersihkanCari"
                />
                <!-- Tombol bersihkan: tersembunyi sampai ada isian, jadi tidak menambah beban visual toolbar. -->
                <button v-if="filters.q" class="mw-clear" type="button" title="Bersihkan pencarian" @click="bersihkanCari">
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
                                <th style="width: 60px">Urutan</th>
                                <th>Tipe Lokasi Kerja</th>
                                <th>Keterangan</th>
                                <th style="width: 150px">
                                    <button class="mw-sort" :class="{ on: sortBy === 'dipakai' }" @click="setSort('dipakai')">
                                        Dipakai <i class="bi" :class="sortIcon('dipakai')"></i>
                                    </button>
                                </th>
                                <th class="mw-hide-sm" style="width: 190px">Dibuat</th>
                                <th style="width: 150px">Status</th>
                                <th style="width: 90px"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(w, i) in list" :key="w.id" :class="{ 'is-off': w.status !== 'AKTIF' }">
                                <!-- Nomor berjalan lintas halaman — indeks dalam halaman
                                     saja akan mengulang 1,2,3 di tiap halaman. -->
                                <td><span class="wca-badge wca-b--slate">{{ (page - 1) * perPage + i + 1 }}</span></td>
                                <td>
                                    <div class="mw-name">
                                        <span class="mw-ico"><i class="bi" :class="ikon(w.nama)"></i></span>
                                        <strong>{{ w.nama }}</strong>
                                    </div>
                                </td>
                                <td><span class="mw-desc">{{ w.keterangan || '—' }}</span></td>
                                <td>
                                    <span v-if="w.dipakai" class="wca-badge wca-b--indigo" :title="`Dipakai ${w.dipakai} lowongan`">{{ w.dipakai }} lowongan</span>
                                    <span v-else class="mw-muted">—</span>
                                </td>
                                <td class="mw-hide-sm"><AuditStamp :at="w.createdAt" :by="w.createdBy || 'SISTEM'" /></td>
                                <td>
                                    <div class="mw-status">
                                        <el-switch :model-value="w.status === 'AKTIF'" @change="(v) => setStatus(w, v)" />
                                        <span class="mw-status__lbl" :class="w.status === 'AKTIF' ? 'is-on' : 'is-off'">{{ w.status === 'AKTIF' ? 'Aktif' : 'Nonaktif' }}</span>
                                    </div>
                                </td>
                                <td>
                                    <div class="mw-actions">
                                        <button class="wca-iconbtn" title="Ubah" @click="openEdit(w)"><i class="bi bi-pencil"></i></button>
                                        <button
                                            class="wca-iconbtn wca-iconbtn--danger"
                                            :disabled="!!w.dipakai"
                                            :title="w.dipakai ? `Tidak bisa dihapus — dipakai ${w.dipakai} lowongan` : 'Hapus'"
                                            :onClick="!!w.dipakai ? null : () => askRemove(w)"
                                        >
                                            <i class="bi" :class="w.dipakai ? 'bi-lock' : 'bi-trash'"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="!loading && !list.length">
                                <td colspan="7">
                                    <div class="wca-empty">
                                        <i class="bi" :class="adaFilter ? 'bi-funnel' : 'bi-geo-alt'"></i>
                                        <h4>{{ adaFilter ? 'Tidak ada tipe lokasi kerja cocok' : 'Belum ada tipe lokasi kerja' }}</h4>
                                        <button v-if="adaFilter" class="wca-btn wca-btn--ghost mw-empty__btn" type="button" @click="resetFilter">
                                            <i class="bi bi-arrow-counterclockwise"></i> Reset filter
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="mw-pager">
                    <span class="mw-pager__info">
                        Menampilkan <b>{{ list.length }}</b> dari <b>{{ total.toLocaleString('id-ID') }}</b> tipe
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
            :busy="saving" :show="show" icon="bi-geo-alt"
            :title="editingId ? 'Ubah Tipe Lokasi Kerja' : 'Tipe Lokasi Kerja Baru'"
            subtitle="Tipe lokasi kerja untuk MPP & lowongan"
            :save-label="editingId ? 'Perbarui' : 'Simpan Tipe'"
            foot-note="Nama harus unik — dipakai sebagai label pilihan di MPP."
            @close="show = false" @save="save"
        >
            <div class="wca-fsection">
                <div class="wca-fsection__label"><i class="bi bi-geo-alt"></i> Data Tipe Lokasi Kerja</div>
                <div class="wca-form">
                    <div>
                        <label class="wca-field-lbl">Nama Tipe Lokasi Kerja</label>
                        <el-input v-model="form.nama" maxlength="100" show-word-limit placeholder="mis. Remote (WFH)" @keyup.enter="save" />
                    </div>
                    <div>
                        <label class="wca-field-lbl">Keterangan <span class="mw-hint">(opsional — dijelaskan singkat, tampil sebagai pendamping nama)</span></label>
                        <el-input v-model="form.keterangan" type="textarea" :rows="2" maxlength="100" show-word-limit placeholder="mis. Bekerja dari rumah / mana saja (full remote)" />
                    </div>
                </div>
            </div>
            <div v-if="editingId && editingDipakai" class="wca-note wca-note--info mw-note-modal">
                <i class="bi bi-clipboard-data"></i>
                <span>Tipe ini dipakai <b>{{ editingDipakai }} lowongan</b>. Mengubah namanya ikut mengubah label pada data tersebut.</span>
            </div>
        </AdminModal>

        <ConfirmModal
            :show="delShow" :busy="deleting" title="Hapus Tipe Lokasi Kerja" confirm-label="Ya, Hapus Tipe"
            note="Tipe lokasi kerja akan dihapus permanen. Hanya tipe yang belum dipakai lowongan yang bisa dihapus."
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

const API = '/api/v1/master-workplace';
const CFG = { headers: { Accept: 'application/json' } };

// Ikon hanya hiasan sisi tampilan — tabelnya memang tidak punya kolom ikon.
// Dicocokkan dari nama supaya barisnya mudah dibedakan sekilas.
const IKON = [
    [/(on[\s-]?site|wfo|kantor)/i, 'bi-building'],
    [/(hybrid|campur)/i, 'bi-shuffle'],
    [/(remote|wfh|rumah)/i, 'bi-house-door'],
    [/(lapangan|field|proyek|project)/i, 'bi-cone-striped'],
    [/./, 'bi-geo-alt'],
];

const RINGKASAN_KOSONG = {
    total: 0, aktif: 0, nonaktif: 0, dipakai: 0,
    status: { semua: 0, aktif: 0, nonaktif: 0 },
};

export default {
    components: { Head, AdminModal, AuditStamp, ConfirmModal },
    // Modal di halaman ini selamat dari refresh — lihat @utils/ingatModal.
    mixins: [ingatModal('admin/master-workplace/masterWorkplace')],
    data() {
        return {
            list: [], total: 0, page: 1, perPage: 25, loading: false,
            ringkasan: { ...RINGKASAN_KOSONG },
            filters: { q: '', status: '' },
            sortBy: 'urutan', sortDir: 'asc', // 'urutan' = urutan asli (id), dikerjakan server — bukan abjad.
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
            return cocok ? cocok[1] : 'bi-geo-alt';
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
                this.notice('Gagal memuat data tipe lokasi kerja.');
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
        openEdit(w) {
            this.editingId = w.id;
            this.editingDipakai = w.dipakai || 0;
            this.form = { nama: w.nama, keterangan: w.keterangan || '' };
            this.show = true;
        },
        async save() {
            if (this.saving) return;
            if (!this.form.nama.trim()) return this.notice('Nama tipe lokasi kerja wajib diisi.');
            this.saving = true;
            const payload = { nama: this.form.nama.trim(), keterangan: this.form.keterangan.trim() };
            try {
                if (this.editingId) {
                    await axios.put(`${API}/${this.editingId}`, payload, CFG);
                    this.notice('Tipe lokasi kerja diperbarui.');
                } else {
                    await axios.post(API, payload, CFG);
                    this.notice('Tipe lokasi kerja ditambahkan.');
                }
                this.show = false;
                await this.load();
            } catch (e) {
                this.notice(e.response?.data?.message || 'Gagal menyimpan tipe lokasi kerja.');
            } finally {
                this.saving = false;
            }
        },
        async setStatus(w, v) {
            const prev = w.status;
            w.status = v ? 'AKTIF' : 'NONAKTIF';
            try {
                await axios.patch(`${API}/${w.id}/toggle`, { aktif: v }, CFG);
                this.notice(`"${w.nama}" ${v ? 'diaktifkan' : 'dinonaktifkan'}.`);
                // Muat ulang: kartu statistik & cacah tab dihitung server,
                // jadi tidak bisa disesuaikan dari sini.
                await this.load();
            } catch (err) {
                w.status = prev;
                this.notice(err.response?.data?.message || 'Gagal mengubah status.');
            }
        },
        askRemove(w) {
            if (w.dipakai) return this.notice(`"${w.nama}" dipakai ${w.dipakai} lowongan — nonaktifkan saja.`);
            this.delTarget = w;
            this.delShow = true;
        },
        async confirmDelete() {
            if (this.deleting || !this.delTarget) return;
            this.deleting = true;
            try {
                await axios.delete(`${API}/${this.delTarget.id}`, CFG);
                this.notice('Tipe lokasi kerja dihapus.');
                this.delShow = false;
                this.delTarget = null;
                await this.load();
            } catch (e) {
                this.notice(e.response?.data?.message || 'Gagal menghapus tipe lokasi kerja.');
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
.mw-name { display: flex; align-items: center; gap: 10px; min-width: 0; }
.mw-ico {
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
.mw-name strong { font-size: 13px; color: #0f1235; }
.mw-desc { color: #64748b; font-size: 12.5px; }
.mw-muted { color: #94a3b8; }
.mw-hint { font-weight: 400; color: #94a3b8; font-size: 11px; }
.mw-note-modal { margin-top: 0.9rem; }

.mw-sort {
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
.mw-sort i { font-size: 11px; color: #94a3b8; }
.mw-sort:hover, .mw-sort.on { color: #4f46e5; }
.mw-sort.on i { color: #4f46e5; }

.mw-status { display: flex; align-items: center; gap: 10px; --el-switch-on-color: #059669; }
.mw-status__lbl { font-size: 12px; font-weight: 700; }
.mw-status__lbl.is-on { color: #059669; }
.mw-status__lbl.is-off { color: #94a3b8; }

.mw-actions { display: flex; gap: 0.35rem; justify-content: flex-end; }
.mw-actions .wca-iconbtn:disabled { opacity: 0.45; cursor: not-allowed; }

.mw-clear {
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
.mw-clear:hover { background: rgba(239, 68, 68, 0.15); color: #dc2626; }
.mw-empty__btn { margin-top: 0.9rem; }

.mw-pager {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 0.6rem;
    padding: 0.85rem 1.1rem;
    border-top: 1px solid var(--line, rgba(11, 16, 51, 0.08));
}
.mw-pager__info { font-size: 12px; font-weight: 600; color: #64748b; }
.mw-pager__info b { color: #0f1235; }

tr.is-off .mw-ico { background: rgba(148, 163, 184, 0.16); color: #64748b; }
tr.is-off .mw-name strong { color: #64748b; }

@media (max-width: 900px) {
    .mw-hide-sm { display: none; }
}
@media (max-width: 640px) {
    .mw-status__lbl { display: none; }
    .mw-pager { justify-content: center; }
    .mw-pager__info { width: 100%; text-align: center; }
}
</style>
