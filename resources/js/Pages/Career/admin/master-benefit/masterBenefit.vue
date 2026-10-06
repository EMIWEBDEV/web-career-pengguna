<!-- WEB CAREER — Master Benefit (fasilitas & tunjangan). Data via web route + ResponseHelper (axios).

     PAGINASI, FILTER, PENCARIAN, dan URUT dikerjakan SERVER — klien hanya
     memegang satu halaman, jadi semua angka ringkasan & cacah tab ikut datang
     dari server; menjumlahkan baris yang tampil akan salah.

     "Dipakai" di sini = jumlah LOWONGAN yang memasang benefit ini, dibaca dari
     tabel jembatan N_WEB_CAREERS_Detail_Benefit_MPP. -->
<template>
    <Head title="Master Benefit" />
    <div class="wca">
        <div class="wca-phead">
            <div>
                <h1>Master Benefit</h1>
                <p>Fasilitas &amp; tunjangan (Gaji + THR, BPJS, pelatihan, …). Dipasang pada lowongan saat menyusun <b>MPP</b>, lalu tampil di detail lowongan yang dibaca kandidat. Hanya benefit <b>Aktif</b> yang muncul di pilihan baru.</p>
            </div>
            <div class="wca-phead__actions">
                <button class="wca-btn wca-btn--primary" @click="openCreate"><i class="bi bi-plus-lg"></i> Benefit Baru</button>
            </div>
        </div>

        <div class="wca-note wca-note--info">
            <i class="bi bi-info-circle"></i>
            <span>Benefit yang sudah terpasang di lowongan <b>tidak bisa dihapus</b> — nonaktifkan saja supaya data lama tetap utuh dan benefit ini berhenti muncul sebagai pilihan baru. Nama benefit <b>harus unik</b>.</span>
        </div>

        <div class="wca-stats">
            <div class="wca-stat">
                <div class="wca-stat__top"><span class="wca-stat__ico"><i class="bi bi-gift"></i></span></div>
                <div class="wca-stat__num">{{ ringkasan.total }}</div>
                <div class="wca-stat__label">Total Benefit</div>
            </div>
            <div class="wca-stat">
                <div class="wca-stat__top"><span class="wca-stat__ico" style="background: rgba(16, 185, 129, 0.12); color: #059669"><i class="bi bi-check-circle"></i></span></div>
                <div class="wca-stat__num">{{ ringkasan.aktif }}</div>
                <div class="wca-stat__label">Aktif (jadi pilihan)</div>
            </div>
            <div class="wca-stat">
                <div class="wca-stat__top"><span class="wca-stat__ico" style="background: rgba(245, 158, 11, 0.14); color: #b45309"><i class="bi bi-dash-circle"></i></span></div>
                <div class="wca-stat__num">{{ ringkasan.belumDipakai }}</div>
                <div class="wca-stat__label">Belum dipakai lowongan</div>
            </div>
            <div class="wca-stat">
                <div class="wca-stat__top"><span class="wca-stat__ico" style="background: rgba(99, 102, 241, 0.12); color: #4f46e5"><i class="bi bi-clipboard-data"></i></span></div>
                <div class="wca-stat__num">{{ ringkasan.dipakai }}</div>
                <div class="wca-stat__label">Pemasangan di MPP</div>
            </div>
        </div>

        <div class="wca-toolbar">
            <div class="wca-search2">
                <i class="bi bi-search"></i>
                <input
                    v-model="filters.q" type="text" placeholder="Cari nama / keterangan…"
                    aria-label="Cari benefit" @input="cariTertunda" @keyup.enter="reload" @keyup.esc="bersihkanCari"
                />
                <!-- Tombol bersihkan: tersembunyi sampai ada isian, jadi tidak menambah beban visual toolbar. -->
                <button v-if="filters.q" class="bnf-clear" type="button" title="Bersihkan pencarian" @click="bersihkanCari">
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
                                <th>
                                    <button class="bnf-sort" :class="{ on: sortBy === 'nama' }" @click="setSort('nama')">
                                        Benefit <i class="bi" :class="sortIcon('nama')"></i>
                                    </button>
                                </th>
                                <th>Keterangan</th>
                                <th style="width: 140px">
                                    <button class="bnf-sort" :class="{ on: sortBy === 'dipakai' }" @click="setSort('dipakai')">
                                        Dipakai <i class="bi" :class="sortIcon('dipakai')"></i>
                                    </button>
                                </th>
                                <th class="bnf-hide-sm" style="width: 190px">
                                    <button class="bnf-sort" :class="{ on: sortBy === 'baru' }" @click="setSort('baru')">
                                        Dibuat <i class="bi" :class="sortIcon('baru')"></i>
                                    </button>
                                </th>
                                <th style="width: 150px">Status</th>
                                <th style="width: 90px"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="b in list" :key="b.id" :class="{ 'is-off': b.status !== 'AKTIF' }">
                                <td>
                                    <div class="bnf-name">
                                        <span class="bnf-ico"><i class="bi" :class="ikon(b.nama)"></i></span>
                                        <strong>{{ b.nama }}</strong>
                                    </div>
                                </td>
                                <td><span class="bnf-desc">{{ b.keterangan || '—' }}</span></td>
                                <td>
                                    <span v-if="b.dipakai" class="wca-badge wca-b--indigo" :title="`Terpasang di ${b.dipakai} lowongan`">{{ b.dipakai }} lowongan</span>
                                    <span v-else class="wca-badge wca-b--amber" title="Belum pernah dipasang di lowongan mana pun">Belum dipakai</span>
                                </td>
                                <td class="bnf-hide-sm"><AuditStamp :at="b.createdAt" :by="b.createdBy || 'SISTEM'" /></td>
                                <td>
                                    <div class="bnf-status">
                                        <el-switch :model-value="b.status === 'AKTIF'" @change="(v) => setStatus(b, v)" />
                                        <span class="bnf-status__lbl" :class="b.status === 'AKTIF' ? 'is-on' : 'is-off'">{{ b.status === 'AKTIF' ? 'Aktif' : 'Nonaktif' }}</span>
                                    </div>
                                </td>
                                <td>
                                    <div class="bnf-actions">
                                        <button class="wca-iconbtn" title="Ubah" @click="openEdit(b)"><i class="bi bi-pencil"></i></button>
                                        <button
                                            class="wca-iconbtn wca-iconbtn--danger"
                                            :disabled="!!b.dipakai"
                                            :title="b.dipakai ? `Tidak bisa dihapus — terpasang di ${b.dipakai} lowongan` : 'Hapus'"
                                            :onClick="!!b.dipakai ? null : () => askRemove(b)"
                                        >
                                            <i class="bi" :class="b.dipakai ? 'bi-lock' : 'bi-trash'"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="!loading && !list.length">
                                <td colspan="6">
                                    <div class="wca-empty">
                                        <i class="bi" :class="adaFilter ? 'bi-funnel' : 'bi-gift'"></i>
                                        <h4>{{ adaFilter ? 'Tidak ada benefit cocok' : 'Belum ada benefit' }}</h4>
                                        <button v-if="adaFilter" class="wca-btn wca-btn--ghost bnf-empty__btn" type="button" @click="resetFilter">
                                            <i class="bi bi-arrow-counterclockwise"></i> Reset filter
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="bnf-pager">
                    <span class="bnf-pager__info">
                        Menampilkan <b>{{ list.length }}</b> dari <b>{{ total.toLocaleString('id-ID') }}</b> benefit
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
            :busy="saving" :show="show" icon="bi-gift"
            :title="editingId ? 'Ubah Benefit' : 'Benefit Baru'"
            subtitle="Fasilitas & tunjangan untuk lowongan MPP"
            :save-label="editingId ? 'Perbarui' : 'Simpan Benefit'"
            foot-note="Nama harus unik — dipakai sebagai label pilihan di MPP."
            @close="show = false" @save="save"
        >
            <div class="wca-fsection">
                <div class="wca-fsection__label"><i class="bi bi-gift"></i> Data Benefit</div>
                <div class="wca-form">
                    <div>
                        <label class="wca-field-lbl">Nama Benefit</label>
                        <el-input v-model="form.nama" maxlength="100" show-word-limit placeholder="mis. BPJS Kesehatan & Ketenagakerjaan" @keyup.enter="save" />
                    </div>
                    <div>
                        <label class="wca-field-lbl">Keterangan <span class="bnf-hint">(opsional — dijelaskan singkat, tampil sebagai pendamping nama)</span></label>
                        <el-input v-model="form.keterangan" type="textarea" :rows="2" maxlength="100" show-word-limit placeholder="mis. BPJS Kesehatan & Ketenagakerjaan ditanggung perusahaan" />
                    </div>
                </div>
            </div>

            <div v-if="editingId && editingDipakai" class="wca-note wca-note--info bnf-note-modal">
                <i class="bi bi-clipboard-data"></i>
                <span>Benefit ini terpasang di <b>{{ editingDipakai }} lowongan</b>. Mengubah namanya ikut mengubah label pada lowongan tersebut.</span>
            </div>
        </AdminModal>

        <ConfirmModal
            :show="delShow" :busy="deleting" title="Hapus Benefit" confirm-label="Ya, Hapus Benefit"
            note="Benefit akan dihapus permanen. Hanya benefit yang belum terpasang di lowongan yang bisa dihapus."
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

const API = '/api/v1/master-benefit';
const CFG = { headers: { Accept: 'application/json' } };

// Ikon hanya hiasan sisi tampilan — tabelnya memang tidak punya kolom ikon.
// Dicocokkan dari nama supaya barisnya mudah dibedakan sekilas.
const IKON = [
    [/(bpjs|asuransi|kesehatan)/i, 'bi-shield-plus'],
    [/(gaji|thr|tunjangan|upah)/i, 'bi-cash-coin'],
    [/(bonus|insentif)/i, 'bi-gift'],
    [/(pelatihan|training|sertifikasi|belajar|pembelajaran|skill)/i, 'bi-mortarboard'],
    [/(karir|karier|jenjang|promosi)/i, 'bi-graph-up-arrow'],
    [/(makan|transport|shift|uang)/i, 'bi-cup-hot'],
    [/(laptop|perangkat|fasilitas|alat)/i, 'bi-laptop'],
    [/(hybrid|remote|wfh|fleksibel)/i, 'bi-house-door'],
    [/(lingkungan|tim|kolaborat|kreatif)/i, 'bi-people'],
    [/(cuti|libur)/i, 'bi-calendar-heart'],
];

const KOSONG = { nama: '', keterangan: '' };
const RINGKASAN_KOSONG = {
    total: 0, aktif: 0, nonaktif: 0, dipakai: 0, belumDipakai: 0,
    status: { semua: 0, aktif: 0, nonaktif: 0 },
};

export default {
    components: { Head, AdminModal, AuditStamp, ConfirmModal },
    // Modal di halaman ini selamat dari refresh — lihat @utils/ingatModal.
    mixins: [ingatModal('admin/master-benefit/masterBenefit')],
    data() {
        return {
            list: [], total: 0, page: 1, perPage: 25, loading: false,
            ringkasan: { ...RINGKASAN_KOSONG },
            filters: { q: '', status: '' },
            sortBy: 'nama', sortDir: 'asc', // dikerjakan server
            show: false, editingId: null, editingDipakai: 0, saving: false,
            form: { ...KOSONG },
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
            return cocok ? cocok[1] : 'bi-star';
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
                this.sortDir = kolom === 'nama' ? 'asc' : 'desc';
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
                this.notice('Gagal memuat data benefit.');
            } finally {
                this.loading = false;
            }
        },
        openCreate() {
            this.editingId = null;
            this.editingDipakai = 0;
            this.form = { ...KOSONG };
            this.show = true;
        },
        openEdit(b) {
            this.editingId = b.id;
            this.editingDipakai = b.dipakai || 0;
            this.form = { nama: b.nama, keterangan: b.keterangan || '' };
            this.show = true;
        },
        async save() {
            if (this.saving) return;
            if (!this.form.nama.trim()) return this.notice('Nama benefit wajib diisi.');
            this.saving = true;
            const payload = { nama: this.form.nama.trim(), keterangan: this.form.keterangan.trim() };
            try {
                if (this.editingId) {
                    await axios.put(`${API}/${this.editingId}`, payload, CFG);
                    this.notice('Benefit diperbarui.');
                } else {
                    await axios.post(API, payload, CFG);
                    this.notice('Benefit ditambahkan.');
                }
                this.show = false;
                await this.load();
            } catch (e) {
                this.notice(e.response?.data?.message || 'Gagal menyimpan benefit.');
            } finally {
                this.saving = false;
            }
        },
        async setStatus(b, v) {
            const prev = b.status;
            b.status = v ? 'AKTIF' : 'NONAKTIF';
            try {
                await axios.patch(`${API}/${b.id}/toggle`, { aktif: v }, CFG);
                this.notice(`"${b.nama}" ${v ? 'diaktifkan' : 'dinonaktifkan'}.`);
                // Muat ulang: kartu statistik & cacah tab dihitung server,
                // jadi tidak bisa disesuaikan dari sini.
                await this.load();
            } catch (err) {
                b.status = prev;
                this.notice(err.response?.data?.message || 'Gagal mengubah status.');
            }
        },
        askRemove(b) {
            if (b.dipakai) return this.notice(`"${b.nama}" terpasang di ${b.dipakai} lowongan — nonaktifkan saja.`);
            this.delTarget = b;
            this.delShow = true;
        },
        async confirmDelete() {
            if (this.deleting || !this.delTarget) return;
            this.deleting = true;
            try {
                await axios.delete(`${API}/${this.delTarget.id}`, CFG);
                this.notice('Benefit dihapus.');
                this.delShow = false;
                this.delTarget = null;
                await this.load();
            } catch (e) {
                this.notice(e.response?.data?.message || 'Gagal menghapus benefit.');
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
.bnf-name { display: flex; align-items: center; gap: 10px; min-width: 0; }
.bnf-ico {
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
.bnf-name strong { font-size: 13px; color: #0f1235; }
.bnf-desc { color: #64748b; font-size: 12.5px; }
.bnf-hint { font-weight: 400; color: #94a3b8; font-size: 11px; }
.bnf-note-modal { margin-top: 0.9rem; }

.bnf-sort {
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
.bnf-sort i { font-size: 11px; color: #94a3b8; }
.bnf-sort:hover, .bnf-sort.on { color: #4f46e5; }
.bnf-sort.on i { color: #4f46e5; }

.bnf-status { display: flex; align-items: center; gap: 10px; --el-switch-on-color: #059669; }
.bnf-status__lbl { font-size: 12px; font-weight: 700; }
.bnf-status__lbl.is-on { color: #059669; }
.bnf-status__lbl.is-off { color: #94a3b8; }

.bnf-actions { display: flex; gap: 0.35rem; justify-content: flex-end; }
.bnf-actions .wca-iconbtn:disabled { opacity: 0.45; cursor: not-allowed; }

.bnf-clear {
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
.bnf-clear:hover { background: rgba(239, 68, 68, 0.15); color: #dc2626; }
.bnf-empty__btn { margin-top: 0.9rem; }

.bnf-pager {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 0.6rem;
    padding: 0.85rem 1.1rem;
    border-top: 1px solid var(--line, rgba(11, 16, 51, 0.08));
}
.bnf-pager__info { font-size: 12px; font-weight: 600; color: #64748b; }
.bnf-pager__info b { color: #0f1235; }

tr.is-off .bnf-ico { background: rgba(148, 163, 184, 0.16); color: #64748b; }
tr.is-off .bnf-name strong { color: #64748b; }

@media (max-width: 900px) {
    .bnf-hide-sm { display: none; }
}
@media (max-width: 640px) {
    .bnf-status__lbl { display: none; }
    .bnf-pager { justify-content: center; }
    .bnf-pager__info { width: 100%; text-align: center; }
}
</style>
