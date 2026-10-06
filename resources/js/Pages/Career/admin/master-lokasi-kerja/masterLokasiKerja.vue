<!-- WEB CAREER — Master Lokasi Kerja (kantor pusat & cabang per kota, tabel
     N_HRIS_Master_Lokasi). Data via web route + ResponseHelper (axios).

     PAGINASI, FILTER, PENCARIAN, dan URUT dikerjakan SERVER (pola Master Kampus).
     Klien hanya memegang satu halaman — karena itu semua angka ringkasan &
     cacah facet ikut datang dari server; menjumlahkan baris yang tampil akan salah.

     JANGAN tertukar dengan Master Lokasi (/master-lokasi) — itu kantor & vendor
     untuk penjadwalan wawancara/MCU, tabel dan kegunaannya berbeda. -->
<template>
    <Head title="Master Lokasi Kerja" />
    <div class="wca">
        <div class="wca-phead">
            <div>
                <h1>Master Lokasi Kerja</h1>
                <p>Kantor pusat &amp; kantor cabang per kota. Dipakai transaksi <b>GForm</b> sebagai penempatan lowongan, dan dibaca <b>landing page</b> untuk menampilkan sebaran kantor. Hanya lokasi <b>Aktif</b> yang muncul di sana.</p>
            </div>
            <div class="wca-phead__actions">
                <button class="wca-btn wca-btn--primary" @click="openCreate"><i class="bi bi-plus-lg"></i> Lokasi Baru</button>
            </div>
        </div>

        <div class="wca-note wca-note--info">
            <i class="bi bi-info-circle"></i>
            <span><b>Kode lokasi dikunci setelah dibuat</b> — kode inilah yang dirujuk transaksi GForm tanpa foreign key, jadi mengubahnya akan memutus rujukan tanpa peringatan. Lokasi yang sudah dipakai transaksi juga tidak bisa dihapus; nonaktifkan saja.</span>
        </div>

        <div class="wca-stats">
            <div class="wca-stat">
                <div class="wca-stat__top"><span class="wca-stat__ico"><i class="bi bi-geo-alt"></i></span></div>
                <div class="wca-stat__num">{{ ringkasan.total }}</div>
                <div class="wca-stat__label">Total Lokasi</div>
            </div>
            <div class="wca-stat">
                <div class="wca-stat__top"><span class="wca-stat__ico" style="background: rgba(16, 185, 129, 0.12); color: #059669"><i class="bi bi-check-circle"></i></span></div>
                <div class="wca-stat__num">{{ ringkasan.aktif }}</div>
                <div class="wca-stat__label">Aktif (tampil di landing)</div>
            </div>
            <div class="wca-stat">
                <div class="wca-stat__top"><span class="wca-stat__ico" style="background: rgba(148, 163, 184, 0.16); color: #475569"><i class="bi bi-eye-slash"></i></span></div>
                <div class="wca-stat__num">{{ ringkasan.nonaktif }}</div>
                <div class="wca-stat__label">Nonaktif (tersembunyi)</div>
            </div>
            <div class="wca-stat">
                <div class="wca-stat__top"><span class="wca-stat__ico" style="background: rgba(99, 102, 241, 0.12); color: #4f46e5"><i class="bi bi-clipboard-data"></i></span></div>
                <div class="wca-stat__num">{{ ringkasan.dipakai }}</div>
                <div class="wca-stat__label">Pemakaian di GForm</div>
            </div>
        </div>

        <!-- Baris 1: cari + status. Baris 2: pulau. Dipisah karena keduanya
             dimensi yang berbeda — dijejalkan satu baris, ketiganya berebut ruang
             dan tidak ada yang terbaca sebagai kelompok. -->
        <div class="wca-toolbar mlj-toolbar">
            <div class="wca-search2 mlj-search">
                <i class="bi bi-search"></i>
                <input
                    v-model="filters.q" type="text" placeholder="Cari kode / nama / provinsi…"
                    aria-label="Cari lokasi kerja" @input="cariTertunda" @keyup.enter="reload" @keyup.esc="bersihkanCari"
                />
                <button v-if="filters.q" class="mlj-clear" type="button" title="Bersihkan pencarian" @click="bersihkanCari">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
            <div class="wca-segt wca-segt--sm mlj-segt" role="group" aria-label="Saring berdasarkan status">
                <button
                    v-for="t in tabs" :key="t.key" class="wca-segt__it" :class="{ on: filters.status === t.key }"
                    :aria-pressed="filters.status === t.key" @click="pilihStatus(t.key)"
                >
                    <i class="bi" :class="t.icon"></i> <span class="mlj-segt__lbl">{{ t.label }}</span>
                    <span class="wca-tabn">{{ t.count }}</span>
                </button>
            </div>
        </div>

        <!-- Pulau jadi chip, bukan dropdown: nilainya sedikit, dan cacahnya ikut
             terbaca tanpa perlu dibuka dulu. Cacah dihitung server dengan
             mengabaikan filter pulau, jadi angkanya menjanjikan hasil klik. -->
        <div class="mlj-filters">
            <span class="mlj-filters__lbl"><i class="bi bi-globe-asia-australia"></i> Pulau</span>
            <button class="mlj-chip" :class="{ on: filters.pulau === '' }" type="button" @click="pilihPulau('')">
                Semua <b>{{ ringkasan.pulauSemua }}</b>
            </button>
            <button
                v-for="p in ringkasan.pulau" :key="p.nama" type="button"
                class="mlj-chip" :class="{ on: filters.pulau === p.nama, kosong: !p.jumlah && filters.pulau !== p.nama }"
                @click="pilihPulau(filters.pulau === p.nama ? '' : p.nama)"
            >
                {{ p.nama }} <b>{{ p.jumlah }}</b>
            </button>

            <span v-if="adaFilter" class="mlj-filters__sisa">
                <b>{{ total }}</b> dari {{ ringkasan.total }}
                <button class="mlj-reset" type="button" @click="resetFilter"><i class="bi bi-arrow-counterclockwise"></i> Reset</button>
            </span>
        </div>

        <div class="wca-card">
            <div class="wca-card__body--flush">
                <div v-loading="loading" class="wca-tablewrap">
                    <table class="wca-table">
                        <thead>
                            <tr>
                                <th style="width: 110px">Kode</th>
                                <th>Lokasi</th>
                                <th class="mlj-hide-sm">Provinsi</th>
                                <th style="width: 120px">Pulau</th>
                                <th style="width: 150px">
                                    <button class="mlj-sort" :class="{ on: sortBy === 'dipakai' }" @click="setSort('dipakai')">
                                        Dipakai <i class="bi" :class="sortIcon('dipakai')"></i>
                                    </button>
                                </th>
                                <th style="width: 150px">Status</th>
                                <th style="width: 90px"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="l in list" :key="l.kode" :class="{ 'is-off': l.status !== 'AKTIF' }">
                                <td><code class="mlj-kode">{{ l.kode }}</code></td>
                                <td>
                                    <div class="mlj-name">
                                        <span class="mlj-ico" :class="{ 'is-ho': l.kantorPusat }">
                                            <i class="bi" :class="l.kantorPusat ? 'bi-buildings' : 'bi-geo-alt'"></i>
                                        </span>
                                        <div class="mlj-name__txt">
                                            <strong>{{ l.nama }}</strong>
                                            <small v-if="l.keterangan">{{ l.keterangan }}</small>
                                        </div>
                                        <span v-if="l.kantorPusat" class="wca-badge wca-b--gold" title="Kantor pusat — diurutkan paling depan di landing page">
                                            <i class="bi bi-star-fill"></i> Pusat
                                        </span>
                                    </div>
                                </td>
                                <td class="mlj-hide-sm"><span class="mlj-desc">{{ l.provinsi || '—' }}</span></td>
                                <td><span class="wca-badge wca-b--slate">{{ l.pulau || '—' }}</span></td>
                                <td>
                                    <span v-if="l.dipakai" class="wca-badge wca-b--indigo" :title="`Dipakai ${l.dipakai} transaksi GForm`">{{ l.dipakai }} transaksi</span>
                                    <span v-else class="mlj-muted">—</span>
                                </td>
                                <td>
                                    <div class="mlj-status">
                                        <el-switch :model-value="l.status === 'AKTIF'" @change="(v) => setStatus(l, v)" />
                                        <span class="mlj-status__lbl" :class="l.status === 'AKTIF' ? 'is-on' : 'is-off'">{{ l.status === 'AKTIF' ? 'Aktif' : 'Nonaktif' }}</span>
                                    </div>
                                </td>
                                <td>
                                    <div class="mlj-actions">
                                        <button class="wca-iconbtn" title="Ubah" @click="openEdit(l)"><i class="bi bi-pencil"></i></button>
                                        <button
                                            class="wca-iconbtn wca-iconbtn--danger"
                                            :disabled="!!l.dipakai"
                                            :title="l.dipakai ? `Tidak bisa dihapus — dipakai ${l.dipakai} transaksi GForm` : 'Hapus'"
                                            :onClick="!!l.dipakai ? null : () => askRemove(l)"
                                        >
                                            <i class="bi" :class="l.dipakai ? 'bi-lock' : 'bi-trash'"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="!loading && !list.length">
                                <td colspan="7">
                                    <div class="wca-empty">
                                        <i class="bi" :class="adaFilter ? 'bi-funnel' : 'bi-geo-alt'"></i>
                                        <h4>{{ adaFilter ? 'Tidak ada lokasi kerja cocok' : 'Belum ada lokasi kerja' }}</h4>
                                        <button v-if="adaFilter" class="wca-btn wca-btn--ghost mlj-empty__btn" type="button" @click="resetFilter">
                                            <i class="bi bi-arrow-counterclockwise"></i> Reset filter
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="mlj-pager">
                    <span class="mlj-pager__info">
                        Menampilkan <b>{{ list.length }}</b> dari <b>{{ total.toLocaleString('id-ID') }}</b> lokasi
                    </span>
                    <!-- Pilihan terkecil 5 — sama dengan batas bawah yang sudah
                         dijaga server (perPage dijepit 5–100), jadi UI dan
                         backend tidak bisa berbeda pendapat. -->
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
            :title="editingId ? 'Ubah Lokasi Kerja' : 'Lokasi Kerja Baru'"
            subtitle="Kantor pusat & cabang untuk GForm dan landing page"
            :save-label="editingId ? 'Perbarui' : 'Simpan Lokasi'"
            foot-note="Kode lokasi dipakai lintas sistem — dikunci setelah dibuat."
            @close="show = false" @save="save"
        >
            <div class="wca-fsection">
                <div class="wca-fsection__label"><i class="bi bi-geo-alt"></i> Data Lokasi</div>
                <div class="wca-form">
                    <div class="wca-frow">
                        <div>
                            <label class="wca-field-lbl">
                                Kode Lokasi
                                <span v-if="editingId" class="mlj-hint">(terkunci — dirujuk transaksi GForm)</span>
                            </label>
                            <el-input
                                v-model="form.kode" :disabled="!!editingId" maxlength="20" show-word-limit
                                placeholder="mis. LOK-PLM" @input="form.kode = form.kode.toUpperCase()"
                            />
                        </div>
                        <div>
                            <label class="wca-field-lbl">Nama Lokasi</label>
                            <el-input v-model="form.nama" maxlength="100" show-word-limit placeholder="mis. Palembang" @keyup.enter="save" />
                        </div>
                    </div>
                    <div class="wca-frow">
                        <div>
                            <label class="wca-field-lbl">Provinsi</label>
                            <!-- Dikelompokkan per pulau supaya 38 provinsi bisa ditelusuri
                                 tanpa menggulir buta; tetap bisa diketik untuk mencari. -->
                            <el-select
                                v-model="form.provinsi" filterable placeholder="Pilih provinsi"
                                style="width: 100%" @change="isiPulauDariProvinsi"
                            >
                                <el-option-group v-for="g in provinsiPerPulau" :key="g.pulau" :label="g.pulau">
                                    <el-option v-for="p in g.daftar" :key="p.nama" :label="p.nama" :value="p.nama" />
                                </el-option-group>
                            </el-select>
                        </div>
                        <div>
                            <label class="wca-field-lbl">
                                Pulau
                                <span class="mlj-hint">(terisi otomatis dari provinsi)</span>
                            </label>
                            <el-select
                                v-model="form.pulau" filterable allow-create default-first-option
                                placeholder="Terisi otomatis" style="width: 100%"
                            >
                                <el-option v-for="p in pulauPilihan" :key="p" :label="p" :value="p" />
                            </el-select>
                        </div>
                    </div>
                    <div>
                        <label class="wca-field-lbl">Keterangan <span class="mlj-hint">(opsional — tampil sebagai pendamping nama)</span></label>
                        <el-input v-model="form.keterangan" type="textarea" :rows="2" maxlength="255" show-word-limit placeholder="mis. Kantor Cabang Sumatra Selatan" />
                    </div>

                    <label class="mlj-check">
                        <el-checkbox v-model="form.kantorPusat">Jadikan kantor pusat</el-checkbox>
                        <small>Hanya satu lokasi yang bisa jadi kantor pusat — menandai ini otomatis menurunkan kantor pusat lama jadi cabang. Kantor pusat diurutkan paling depan di landing page.</small>
                    </label>
                </div>
            </div>

            <div v-if="editingId && editingDipakai" class="wca-note wca-note--info mlj-note-modal">
                <i class="bi bi-clipboard-data"></i>
                <span>Lokasi ini dipakai <b>{{ editingDipakai }} transaksi GForm</b>. Mengubah namanya ikut mengubah label pada data tersebut.</span>
            </div>
        </AdminModal>

        <ConfirmModal
            :show="delShow" :busy="deleting" title="Hapus Lokasi Kerja" confirm-label="Ya, Hapus Lokasi"
            note="Lokasi akan dihapus permanen. Hanya lokasi yang belum dipakai transaksi GForm yang bisa dihapus."
            @cancel="delShow = false" @confirm="confirmDelete"
        >
            Yakin ingin menghapus <strong>{{ delTarget?.nama }}</strong> ({{ delTarget?.kode }})?
        </ConfirmModal>

        <transition name="wca-toast"><div v-if="toast" class="wca-toast"><i class="bi bi-check-circle-fill"></i> {{ toast }}</div></transition>
    </div>
</template>

<script>
import axios from 'axios';
import { Head } from '@inertiajs/vue3';
import AdminModal from '@career/AdminModal.vue';
import ConfirmModal from '@career/ConfirmModal.vue';
import { PULAU, PROVINSI_PER_PULAU, pulauDariProvinsi } from '../../../../data/wilayahIndonesia';
import { ingatModal } from '@utils/ingatModal';

const API = '/api/v1/master-lokasi-kerja';
const CFG = { headers: { Accept: 'application/json' } };
const KOSONG = { kode: '', nama: '', provinsi: '', pulau: '', keterangan: '', kantorPusat: false };
const RINGKASAN_KOSONG = {
    total: 0, aktif: 0, nonaktif: 0, dipakai: 0,
    status: { semua: 0, aktif: 0, nonaktif: 0 },
    pulau: [], pulauSemua: 0,
};

export default {
    components: { Head, AdminModal, ConfirmModal },
    // Modal di halaman ini selamat dari refresh — lihat @utils/ingatModal.
    mixins: [ingatModal('admin/master-lokasi-kerja/masterLokasiKerja')],
    data() {
        return {
            list: [], total: 0, page: 1, perPage: 25, loading: false,
            ringkasan: { ...RINGKASAN_KOSONG },
            filters: { q: '', pulau: '', status: '' },
            sortBy: 'urutan', sortDir: 'asc', // 'urutan' = kantor pusat dulu, lalu abjad (dikerjakan server).
            // Provinsi lama yang tidak ada di daftar baku. Diisi saat membuka
            // baris untuk diubah — tidak bisa diturunkan dari list seperti dulu,
            // karena list sekarang cuma berisi satu halaman.
            provinsiEkstra: [],
            show: false, editingId: null, editingDipakai: 0, saving: false,
            form: { ...KOSONG },
            delShow: false, delTarget: null, deleting: false,
            toast: '', tm: null, dtm: null,
        };
    },
    computed: {
        tabs() {
            const s = this.ringkasan.status || RINGKASAN_KOSONG.status;
            return [
                { key: '', label: 'Semua', icon: 'bi-collection', count: s.semua },
                { key: 'AKTIF', label: 'Aktif', icon: 'bi-check-circle', count: s.aktif },
                { key: 'NONAKTIF', label: 'Nonaktif', icon: 'bi-eye-slash', count: s.nonaktif },
            ];
        },
        adaFilter() {
            return this.filters.q.trim() !== '' || this.filters.pulau !== '' || this.filters.status !== '';
        },
        /** Pulau untuk FORM — daftar baku ditambah nilai yang sudah ada di data. */
        pulauPilihan() {
            return [...new Set([...PULAU, ...this.ringkasan.pulau.map((p) => p.nama).filter(Boolean)])];
        },
        provinsiPerPulau() {
            return this.provinsiEkstra.length
                ? [...PROVINSI_PER_PULAU, { pulau: 'Lainnya (dari data lama)', daftar: this.provinsiEkstra.map((nama) => ({ nama })) }]
                : PROVINSI_PER_PULAU;
        },
    },
    mounted() {
        this.load();
    },
    methods: {
        async load() {
            this.loading = true;
            try {
                const params = {
                    q: this.filters.q.trim(),
                    pulau: this.filters.pulau,
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
                this.notice('Gagal memuat data lokasi kerja.');
            } finally {
                this.loading = false;
            }
        },
        /** Setiap perubahan filter mengembalikan ke halaman 1 — halaman 3 dari hasil lama tidak bermakna. */
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
        pilihPulau(nama) {
            this.filters.pulau = nama;
            this.reload();
        },
        resetFilter() {
            this.filters = { q: '', pulau: '', status: '' };
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
                this.sortDir = 'desc';
            }
            this.reload();
        },
        sortIcon(kolom) {
            if (this.sortBy !== kolom) return 'bi-arrow-down-up';
            return this.sortDir === 'asc' ? 'bi-sort-down-alt' : 'bi-sort-down';
        },
        /**
         * Pulau mengikuti provinsi yang dipilih. Kalau provinsinya tidak dikenali
         * (nilai lama di grup "Lainnya"), isian pulau dibiarkan apa adanya —
         * lebih baik dibiarkan daripada dikosongkan tanpa diminta.
         */
        isiPulauDariProvinsi(nama) {
            const pulau = pulauDariProvinsi(nama);
            if (pulau) this.form.pulau = pulau;
        },
        openCreate() {
            this.editingId = null;
            this.editingDipakai = 0;
            this.provinsiEkstra = [];
            this.form = { ...KOSONG };
            this.show = true;
        },
        openEdit(l) {
            this.editingId = l.kode;
            this.editingDipakai = l.dipakai || 0;

            // Provinsi di luar daftar baku diselipkan sebagai opsi, supaya nilai
            // lama tetap terbaca dan tidak hilang diam-diam saat disimpan ulang.
            const baku = PROVINSI_PER_PULAU.flatMap((g) => g.daftar.map((p) => p.nama));
            this.provinsiEkstra = l.provinsi && !baku.includes(l.provinsi) ? [l.provinsi] : [];

            this.form = {
                kode: l.kode,
                nama: l.nama,
                provinsi: l.provinsi || '',
                pulau: l.pulau || '',
                keterangan: l.keterangan || '',
                kantorPusat: !!l.kantorPusat,
            };
            this.show = true;
        },
        async save() {
            if (this.saving) return;
            if (!this.editingId && !this.form.kode.trim()) return this.notice('Kode lokasi wajib diisi.');
            if (!/^[A-Za-z0-9_-]+$/.test(this.form.kode.trim())) return this.notice('Kode hanya boleh huruf, angka, tanda hubung, dan garis bawah.');
            if (!this.form.nama.trim()) return this.notice('Nama lokasi wajib diisi.');
            if (!this.form.provinsi.trim()) return this.notice('Provinsi wajib diisi.');
            if (!this.form.pulau.trim()) return this.notice('Pulau wajib diisi.');

            this.saving = true;
            const f = this.form;
            const payload = {
                nama: f.nama.trim(),
                provinsi: f.provinsi.trim(),
                pulau: f.pulau.trim(),
                keterangan: f.keterangan.trim(),
                kantorPusat: f.kantorPusat,
            };
            try {
                if (this.editingId) {
                    await axios.put(`${API}/${this.editingId}`, payload, CFG);
                    this.notice('Lokasi kerja diperbarui.');
                } else {
                    await axios.post(API, { ...payload, kode: f.kode.trim().toUpperCase() }, CFG);
                    this.notice('Lokasi kerja ditambahkan.');
                }
                this.show = false;
                await this.load();
            } catch (e) {
                this.notice(e.response?.data?.message || 'Gagal menyimpan lokasi kerja.');
            } finally {
                this.saving = false;
            }
        },
        async setStatus(l, v) {
            const prev = l.status;
            l.status = v ? 'AKTIF' : 'NONAKTIF';
            try {
                await axios.patch(`${API}/${l.kode}/toggle`, { aktif: v }, CFG);
                this.notice(`"${l.nama}" ${v ? 'diaktifkan' : 'dinonaktifkan'}.`);
                // Muat ulang: kartu statistik & cacah facet dihitung server,
                // jadi tidak bisa disesuaikan dari sini.
                await this.load();
            } catch (err) {
                l.status = prev;
                this.notice(err.response?.data?.message || 'Gagal mengubah status.');
            }
        },
        askRemove(l) {
            if (l.dipakai) return this.notice(`"${l.nama}" dipakai ${l.dipakai} transaksi GForm — nonaktifkan saja.`);
            this.delTarget = l;
            this.delShow = true;
        },
        async confirmDelete() {
            if (this.deleting || !this.delTarget) return;
            this.deleting = true;
            try {
                await axios.delete(`${API}/${this.delTarget.kode}`, CFG);
                this.notice('Lokasi kerja dihapus.');
                this.delShow = false;
                this.delTarget = null;
                await this.load();
            } catch (e) {
                this.notice(e.response?.data?.message || 'Gagal menghapus lokasi kerja.');
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
/* ── Toolbar & filter ── */
.mlj-toolbar { align-items: center; margin-bottom: 0.55rem; }
.mlj-search { flex: 1 1 240px; max-width: 340px; transition: border-color 0.18s ease, box-shadow 0.18s ease; }
.mlj-search:focus-within { border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.12); }
.mlj-segt { margin-left: auto; }

.mlj-filters {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.4rem;
    margin-bottom: 1rem;
}
.mlj-filters__lbl {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    margin-right: 0.15rem;
    font-size: 11.5px;
    font-weight: 800;
    letter-spacing: 0.02em;
    color: #94a3b8;
    text-transform: uppercase;
}
.mlj-chip {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 5px 12px;
    border: 1px solid var(--line, rgba(11, 16, 51, 0.1));
    border-radius: 999px;
    background: #fff;
    font: 700 12px 'Plus Jakarta Sans', system-ui, sans-serif;
    color: #475569;
    cursor: pointer;
    transition: all 0.16s ease;
}
.mlj-chip b {
    min-width: 18px;
    padding: 0 5px;
    border-radius: 999px;
    background: rgba(11, 16, 51, 0.07);
    color: #64748b;
    font-size: 10.5px;
    text-align: center;
}
.mlj-chip:hover { border-color: rgba(79, 70, 229, 0.4); color: #4338ca; }
.mlj-chip.on { background: #4f46e5; border-color: #4f46e5; color: #fff; box-shadow: 0 4px 12px rgba(79, 70, 229, 0.25); }
.mlj-chip.on b { background: rgba(255, 255, 255, 0.25); color: #fff; }
/* Pulau yang tidak menyisakan baris apa pun tetap bisa diklik, hanya diredupkan
   supaya tidak terbaca sebagai pilihan yang menjanjikan hasil. */
.mlj-chip.kosong { opacity: 0.45; }

.mlj-filters__sisa {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    margin-left: auto;
    font-size: 12px;
    font-weight: 600;
    color: #64748b;
}
.mlj-filters__sisa b { color: #4338ca; }
.mlj-reset {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 4px 10px;
    border: 1px solid rgba(79, 70, 229, 0.3);
    border-radius: 999px;
    background: #fff;
    font: 700 11.5px 'Plus Jakarta Sans', system-ui, sans-serif;
    color: #4338ca;
    cursor: pointer;
}
.mlj-reset:hover { background: #4f46e5; border-color: #4f46e5; color: #fff; }

/* ── Pager ── */
.mlj-pager {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 0.6rem;
    padding: 0.85rem 1.1rem;
    border-top: 1px solid var(--line, rgba(11, 16, 51, 0.08));
}
.mlj-pager__info { font-size: 12px; font-weight: 600; color: #64748b; }
.mlj-pager__info b { color: #0f1235; }

.mlj-kode { font-family: 'JetBrains Mono', monospace; font-size: 11px; background: #eef0f7; color: #4f46e5; padding: 2px 8px; border-radius: 6px; }

.mlj-name { display: flex; align-items: center; gap: 10px; min-width: 0; }
.mlj-ico {
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
.mlj-ico.is-ho { background: rgba(217, 119, 6, 0.12); color: #b45309; }
.mlj-name__txt { display: flex; flex-direction: column; line-height: 1.25; min-width: 0; }
.mlj-name__txt strong { font-size: 13px; color: #0f1235; }
.mlj-name__txt small { font-size: 11px; color: #94a3b8; }
.mlj-desc { color: #64748b; font-size: 12.5px; }
.mlj-muted { color: #94a3b8; }
.mlj-hint { font-weight: 400; color: #94a3b8; font-size: 11px; }
.mlj-note-modal { margin-top: 0.9rem; }

.mlj-check { display: block; margin-top: 0.35rem; }
.mlj-check small { display: block; margin-top: 4px; font-size: 11.5px; line-height: 1.55; color: #64748b; }

.mlj-sort {
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
.mlj-sort i { font-size: 11px; color: #94a3b8; }
.mlj-sort:hover, .mlj-sort.on { color: #4f46e5; }
.mlj-sort.on i { color: #4f46e5; }

.mlj-status { display: flex; align-items: center; gap: 10px; --el-switch-on-color: #059669; }
.mlj-status__lbl { font-size: 12px; font-weight: 700; }
.mlj-status__lbl.is-on { color: #059669; }
.mlj-status__lbl.is-off { color: #94a3b8; }

.mlj-actions { display: flex; gap: 0.35rem; justify-content: flex-end; }
.mlj-actions .wca-iconbtn:disabled { opacity: 0.45; cursor: not-allowed; }

.mlj-clear {
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
.mlj-clear:hover { background: rgba(239, 68, 68, 0.15); color: #dc2626; }
.mlj-empty__btn { margin-top: 0.9rem; }

tr.is-off .mlj-ico { background: rgba(148, 163, 184, 0.16); color: #64748b; }
tr.is-off .mlj-name__txt strong { color: #64748b; }

@media (max-width: 900px) {
    .mlj-hide-sm { display: none; }
}
@media (max-width: 640px) {
    .mlj-status__lbl { display: none; }
    .mlj-search { max-width: none; }
    .mlj-segt { margin-left: 0; width: 100%; justify-content: space-between; }
    .mlj-segt .wca-segt__it { flex: 1; justify-content: center; padding: 0.5rem 0.4rem; }
    .mlj-segt__lbl { display: none; }
    /* Baris pulau digulir mendatar, bukan membungkus jadi tiga baris. */
    .mlj-filters { flex-wrap: nowrap; overflow-x: auto; padding-bottom: 2px; }
    .mlj-filters__lbl, .mlj-filters__sisa { display: none; }
    .mlj-chip { flex: none; }
    .mlj-pager { justify-content: center; }
    .mlj-pager__info { width: 100%; text-align: center; }
}
</style>
