<!-- WEB CAREER — Master Jadwal Kegiatan (induk-detail: Jadwal + Agenda). DATA dari DB via /api/v1/master-jadwal. -->
<template>
    <Head title="Master Jadwal Kegiatan" />
    <div class="wca">
        <div class="pkg-head">
            <div class="pkg-head__l">
                <div class="pkg-head__title">
                    <span class="pkg-head__ico"><i class="bi bi-calendar3-range"></i></span>
                    <h1>Master Jadwal Kegiatan</h1>
                </div>
                <p>Timeline satu gelombang: aktivitas pendukung (rapat, campaign, evaluasi) <b>+ tahapan seleksi</b>. Tiap agenda bertanggal.</p>
            </div>
            <button class="pkg-newbtn" @click="openCreate"><i class="bi bi-plus-lg"></i> Jadwal Baru</button>
        </div>

        <!-- FILTER PANEL — seluruh saringan dikirim ke backend, bukan disaring di
             browser. Menyaring di layar berarti jadwal kategori lain tetap sampai
             ke peramban lebih dulu, lalu "disembunyikan" oleh kode yang bisa
             dibaca siapa saja. -->
        <div v-if="sheetOpen" class="jdw-sheetbg" @click="sheetOpen = false"></div>
        <div class="jdw-filter" :class="{ 'is-open': sheetOpen }">
            <div class="jdw-filter__head">
                <span class="jdw-filter__title"><i class="bi bi-funnel"></i> Filter Panel</span>
                <div class="jdw-filter__act">
                    <button v-if="adaFilter" class="jdw-filter__reset" type="button" @click="resetFilter"><i class="bi bi-arrow-counterclockwise"></i> Reset</button>
                    <button class="jdw-filter__close" type="button" aria-label="Tutup filter" @click="sheetOpen = false"><i class="bi bi-x-lg"></i></button>
                </div>
            </div>
            <div class="jdw-filter__grid">
                <div>
                    <label class="wca-field-lbl">Cari</label>
                    <el-input v-model="filters.q" placeholder="Nama kegiatan / kode / alur" clearable @input="cariDebounce">
                        <template #prefix><i class="bi bi-search"></i></template>
                    </el-input>
                </div>
                <!-- Penyaring kategori hanya berarti bila ADA yang bisa disaring.
                     Pengguna yang dijatah satu kategori melihat kotak berisi satu
                     pilihan yang sudah pasti terpilih — kendali yang tak bisa
                     mengubah apa pun, dan menyisakan pertanyaan apa gunanya. -->
                <div v-if="kategoriOpsi.length > 1">
                    <label class="wca-field-lbl">Kategori</label>
                    <el-select v-model="filters.kategori" placeholder="Semua kategori" clearable filterable style="width:100%">
                        <el-option v-for="k in kategoriOpsi" :key="k.kode" :label="k.label" :value="k.kode" />
                    </el-select>
                </div>
                <div>
                    <label class="wca-field-lbl">Status</label>
                    <el-select v-model="filters.status" placeholder="Semua status" clearable style="width:100%" @change="load">
                        <el-option label="Aktif" value="AKTIF" />
                        <el-option label="Nonaktif" value="NONAKTIF" />
                    </el-select>
                </div>
                <div>
                    <label class="wca-field-lbl">Tanggal Dibuat</label>
                    <el-date-picker
                        v-model="filters.rentang" type="daterange" value-format="YYYY-MM-DD"
                        start-placeholder="Dari" end-placeholder="Sampai" range-separator="&mdash;"
                        style="width:100%" @change="load"
                    />
                </div>
            </div>
        </div>

        <!-- FAB filter (mobile) -->
        <button class="jdw-fab" type="button" aria-label="Buka filter" @click="sheetOpen = true">
            <i class="bi bi-funnel-fill"></i>
            <span v-if="jumlahFilter" class="jdw-fab__badge">{{ jumlahFilter }}</span>
        </button>

        <!-- Daftar kosong DIBEDAKAN dari daftar yang tersaring habis: yang pertama
             perlu tombol "Jadwal Baru", yang kedua perlu tombol "Reset". Satu
             kalimat untuk keduanya akan menyuruh orang membuat jadwal yang
             sebenarnya sudah ada, hanya sedang tersembunyi oleh saringannya. -->
        <div v-if="!loading && !list.length" class="jdw-kosong">
            <i class="bi" :class="adaFilter ? 'bi-funnel' : 'bi-calendar3-range'"></i>
            <b>{{ adaFilter ? 'Tidak ada jadwal yang cocok' : 'Belum ada jadwal kegiatan' }}</b>
            <span v-if="adaFilter">Saringan yang sedang aktif menutup seluruh data. Longgarkan salah satunya, atau kembalikan ke semula.</span>
            <span v-else>Satu jadwal menampung agenda satu gelombang &mdash; rapat, campaign, evaluasi, dan tahapan seleksinya.</span>
            <button v-if="adaFilter" class="jdw-filter__reset" type="button" @click="resetFilter"><i class="bi bi-arrow-counterclockwise"></i> Kembalikan saringan</button>
        </div>

        <div v-loading="loading" class="pkg-list">
            <div v-for="j in list" :id="`jadwal-${j.id}`" :key="j.id" class="pkg-card" :class="{ open: open === j.id, 'is-focus': fokusId === j.id }">
                <!-- header row (pola standar "Program Kegiatan") -->
                <div class="pkg-row">
                    <button type="button" class="pkg-chev" :class="{ open: open === j.id }" title="Buka detail" @click="open = (open === j.id ? null : j.id)"><i class="bi bi-chevron-right"></i></button>
                    <div class="pkg-row__main">
                        <button type="button" class="pkg-row__titlebtn" @click="open = (open === j.id ? null : j.id)">
                            <span class="pkg-row__title">{{ j.kegiatan }}</span>
                        </button>
                        <div class="pkg-row__meta">
                            <span class="pkg-code">{{ j.kode }}</span>
                            <span class="pkg-sep"></span>
                            <span class="pkg-mi"><i class="bi bi-diagram-2"></i> {{ j.alurNama || j.alur || '—' }}</span>
                        </div>
                        <div class="pkg-pills">
                            <span class="pkg-pill pkg-pill--violet"><i class="bi bi-tags"></i> {{ katLabel(j.kategori) }}</span>
                            <span class="pkg-pill pkg-pill--struct"><i class="bi bi-list-ol"></i> {{ j.agenda.length }} agenda</span>
                            <span class="pkg-pill" :class="j.status === 'AKTIF' ? 'pkg-pill--green' : 'pkg-pill--slate'"><span class="pkg-pill__dot"></span> {{ j.status }}</span>
                        </div>
                    </div>
                    <div class="pkg-row__act" @click.stop>
                        <el-switch :model-value="j.status === 'AKTIF'" @change="(v) => setStatus(j, v)" />
                        <button class="pkg-ibtn" title="Duplikat jadwal ini" @click="openDup(j)"><i class="bi bi-files"></i></button>
                        <button class="pkg-ibtn" title="Ubah" @click="openEdit(j)"><i class="bi bi-pencil"></i></button>
                        <button class="pkg-ibtn pkg-ibtn--danger" title="Hapus" @click="askRemove(j)"><i class="bi bi-trash"></i></button>
                    </div>
                </div>

                <!-- creator strip -->
                <div class="pkg-creator">
                    <span class="pkg-creator__av" style="background:#6366f1">{{ initials(j.createdBy) }}</span>
                    <span class="pkg-creator__name">{{ j.createdBy || 'Sistem' }}</span>
                    <span class="pkg-creator__at"><i class="bi bi-clock"></i> {{ j.createdAt || '—' }}</span>
                </div>

                <!-- expanded detail -->
                <div v-if="open === j.id" class="pkg-detail">
                    <div class="pkg-dhead pkg-dhead--indigo"><i class="bi bi-calendar3-range"></i> AGENDA ({{ j.agenda.length }})</div>
                    <ol class="wca-jlist">
                        <li v-for="(a, i) in j.agenda" :key="i">
                            <span class="wca-jtag" :style="{ color: jenisWarna(a.jenis), background: jenisWarna(a.jenis) + '1a' }"><i class="bi" :class="jenisIkon(a.jenis)"></i> {{ jenisLabel(a.jenis) }}</span>
                            <span class="wca-jlist__lbl">{{ a.label }}</span>
                            <span class="wca-jlist__date"><i class="bi bi-calendar3"></i> {{ fmt(a.mulai) }} – {{ fmt(a.selesai) }}</span>
                        </li>
                        <li v-if="!j.agenda.length" class="jdw-empty-agenda">Belum ada agenda.</li>
                    </ol>
                </div>
            </div>
            <div v-if="!loading && !list.length" class="pkg-empty"><i class="bi bi-calendar3-range"></i> Belum ada jadwal.</div>
        </div>

        <!-- Modal buat/ubah jadwal -->
        <AdminModal :busy="saving" :show="show" :title="editingId ? 'Ubah Jadwal' : 'Buat Jadwal Kegiatan'" subtitle="Isi identitas jadwal lalu susun agenda (rapat/campaign/seleksi/evaluasi)" icon="bi-calendar3-range" lg :save-label="editingId ? 'Perbarui' : 'Simpan Jadwal'" @close="show = false" @save="save">
            <div class="wca-fsection">
                <div class="wca-fsection__label"><i class="bi bi-info-circle"></i> Identitas Jadwal</div>
                <div class="wca-form">
                    <div class="wca-frow">
                        <div><label class="wca-field-lbl">Nama Kegiatan</label><el-input v-model="form.kegiatan" placeholder="mis. Rekrutmen Reguler Q3" /></div>
                        <div><label class="wca-field-lbl">Kategori</label>
                            <el-select v-if="!kategoriTunggal" v-model="form.kategori" placeholder="Pilih kategori" filterable style="width:100%">
                                <el-option v-for="k in kategoriOpsi" :key="k.kode" :label="k.label" :value="k.kode" />
                            </el-select>
                            <!-- Satu-satunya kategori yang boleh: nilainya sudah
                                 dipasang saat borang dibuka, jadi yang tersisa cuma
                                 keterangan agar admin tahu jadwal ini masuk ke mana. -->
                            <div v-else class="jdw-kat-tetap">
                                <span class="pkg-pill pkg-pill--violet"><i class="bi bi-tags"></i> {{ kategoriTunggal.label }}</span>
                                <small><i class="bi bi-lock-fill"></i> satu-satunya kategori yang menjadi hak akses Anda</small>
                            </div>
                        </div>
                    </div>
                    <div class="wca-frow">
                        <div><label class="wca-field-lbl">Alur Seleksi</label>
                            <!-- Dipersempit ke kategori yang sedang dipilih. Tanpa
                                 :params, daftarnya memuat SELURUH alur — termasuk
                                 milik kategori yang tak boleh diakses pengguna ini,
                                 dan jadwal MT bisa berakhir menunjuk alur Rekrutmen
                                 tanpa satu pun peringatan. Pola yang sama sudah
                                 dipakai modal Duplikat di bawah. -->
                            <RefSelect type="alur" v-model="form.alur" :params="{ kategori: form.kategori }" placeholder="Pilih alur" no-data-text="Belum ada alur untuk kategori ini" clearable />
                        </div>
                    </div>
                    <!-- Status tidak ditanyakan: jadwal yang baru dibuat pasti aktif,
                         dan mematikannya sudah tersedia lewat toggle di daftar. -->
                    <div class="mjd-statusnote">
                        <i class="bi bi-check-circle-fill"></i>
                        <span>Jadwal langsung <strong>Aktif</strong> setelah dibuat. Nonaktifkan lewat toggle di daftar bila perlu.</span>
                    </div>
                </div>
            </div>

            <div class="wca-fsection">
                <div class="wca-fsection__label" style="display:flex;align-items:center;justify-content:space-between">
                    <span><i class="bi bi-list-ol"></i> Agenda ({{ form.agenda.length }})</span>
                    <button class="wca-btn wca-btn--soft wca-btn--sm" type="button" @click="addRow"><i class="bi bi-plus-circle"></i> Tambah Agenda</button>
                </div>
                <div class="jdw-agenda">
                    <div v-for="(s, i) in form.agenda" :key="i" class="jdw-arow">
                        <span class="jdw-arow__num">{{ i + 1 }}</span>
                        <div class="jdw-arow__grid">
                            <!-- Tiap field col-6 (2 per baris) — lebih lega daripada 4 berdesakan. -->
                            <div class="jdw-col6">
                                <label class="wca-field-lbl">Jenis</label>
                                <el-select filterable v-model="s.jenis" placeholder="Pilih jenis agenda" style="width:100%">
                                    <el-option v-for="o in jenisOptions" :key="o.value" :label="o.label" :value="o.value">
                                        <div class="jdw-opt">
                                            <span class="jdw-opt__ic" :style="{ color: o.warna }"><i class="bi" :class="o.ikon"></i></span>
                                            <span class="jdw-opt__txt">
                                                <strong>{{ o.label }}</strong>
                                                <small>{{ o.deskripsi }}</small>
                                            </span>
                                        </div>
                                    </el-option>
                                </el-select>
                                <div v-if="jenisInfo(s.jenis)" class="jdw-hint">
                                    <i class="bi" :class="jenisInfo(s.jenis).ikon" :style="{ color: jenisInfo(s.jenis).warna }"></i>
                                    {{ jenisInfo(s.jenis).deskripsi }}
                                </div>
                            </div>
                            <div class="jdw-col6"><label class="wca-field-lbl">Label</label><el-input v-model="s.label" placeholder="mis. Psikotes Online" /></div>
                            <div class="jdw-col6"><label class="wca-field-lbl">Mulai</label><el-date-picker v-model="s.mulai" type="date" value-format="YYYY-MM-DD" placeholder="Mulai" style="width:100%" :disabled-date="hariLampau" /></div>
                            <div class="jdw-col6"><label class="wca-field-lbl">Selesai</label><el-date-picker v-model="s.selesai" type="date" value-format="YYYY-MM-DD" placeholder="Selesai" style="width:100%" :disabled-date="(d) => sebelumMulai(d, s.mulai)" /></div>
                        </div>
                        <button class="wca-iconbtn wca-iconbtn--danger" type="button" title="Hapus agenda" @click="removeRow(i)"><i class="bi bi-trash"></i></button>
                    </div>
                    <div v-if="!form.agenda.length" class="jdw-empty-agenda" style="padding:.8rem">Belum ada agenda — klik "Tambah Agenda".</div>
                </div>
            </div>
        </AdminModal>

        <!-- DUPLIKAT — seluruh agenda disalin apa adanya; yang diganti hanya
             nama kegiatan & alur seleksi. Kategori ikut sumber karena alur
             terikat kategori. -->
        <AdminModal
            :busy="dupSaving" :show="dupShow" title="Duplikat Jadwal Kegiatan"
            :subtitle="dupTarget ? `Menyalin dari: ${dupTarget.kegiatan}` : ''"
            icon="bi-files" save-label="Duplikat Jadwal"
            foot-note="Tanggal agenda ikut tersalin — periksa & sesuaikan setelah ini."
            @close="dupShow = false" @save="simpanDup"
        >
            <div class="wca-fsection">
                <div class="wca-fsection__label"><i class="bi bi-clipboard-check"></i> Yang Disalin</div>
                <div class="jdw-dupinfo">
                    <div class="jdw-dupinfo__row">
                        <span><i class="bi bi-list-ol"></i> Agenda</span>
                        <b>{{ dupTarget?.agenda?.length || 0 }} baris (label &amp; tanggal ikut)</b>
                    </div>
                    <div class="jdw-dupinfo__row">
                        <span><i class="bi bi-tags"></i> Kategori</span>
                        <b>{{ katLabel(dupTarget?.kategori) }} <small>— mengikuti sumber</small></b>
                    </div>
                </div>
            </div>

            <div class="wca-fsection">
                <div class="wca-fsection__label"><i class="bi bi-pencil-square"></i> Yang Diganti</div>
                <div class="wca-form">
                    <div>
                        <label class="wca-field-lbl">Nama Kegiatan Baru</label>
                        <el-input v-model="dupForm.kegiatan" placeholder="mis. Rekrutmen Batch 2 2026" />
                    </div>
                    <div>
                        <label class="wca-field-lbl">Alur Seleksi</label>
                        <RefSelect
                            type="alur" v-model="dupForm.alur" clearable
                            :params="{ kategori: dupTarget?.kategori }"
                            placeholder="Pilih alur seleksi"
                        />
                        <small class="jdw-hint">Kosongkan bila ingin memakai alur yang sama dengan sumber.</small>
                    </div>
                </div>
            </div>
        </AdminModal>

        <ConfirmModal :show="delShow" title="Hapus Jadwal" :busy="deleting" confirm-label="Ya, Hapus" note="Jadwal & seluruh agenda-nya akan dihapus permanen." @cancel="delShow = false" @confirm="confirmDelete">
            Yakin ingin menghapus jadwal <strong>{{ delTarget?.kegiatan }}</strong>?
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

const API = '/api/v1/master-jadwal';
const CFG = { headers: { Accept: 'application/json' } };

export default {
    components: { Head, AdminModal, ConfirmModal, AuditStamp, RefSelect },
    // Modal di halaman ini selamat dari refresh — lihat @utils/ingatModal.
    mixins: [ingatModal('admin/master-jadwal/masterJadwal')],
    data() {
        return {
            list: [],
            loading: false,
            open: null,
            // Saringan dikirim ke server; daftar yang kembali sudah bersih.
            filters: { q: '', kategori: null, status: null, rentang: null },
            cariTimer: null,
            sheetOpen: false,
            // Kategori yang BOLEH dilihat pengguna ini — datang dari server bersama
            // daftar jadwalnya, bukan dari master lengkap.
            kategoriOpsi: [],
            show: false,
            editingId: null,
            form: { kegiatan: '', kategori: '', alur: '', agenda: [] },
            // Duplikat jadwal — agenda disalin, hanya nama & alur yang diganti.
            dupShow: false,
            dupTarget: null,
            dupSaving: false,
            dupForm: { kegiatan: '', alur: '' },
            // Jenis agenda dimuat dari DB (satu sumber: label + deskripsi + ikon + warna),
            // bukan lagi dua peta hardcode yang tercecer di sini.
            jenisOptions: [],
            delShow: false,
            delTarget: null,
            deleting: false,
            saving: false,
            toast: '',
            tm: null,
            fokusId: new URLSearchParams(window.location.search).get('fokus'),
        };
    },
    computed: {
        /** Tepat satu kategori yang boleh -> tidak ada yang perlu dipilih. */
        kategoriTunggal() {
            return this.kategoriOpsi.length === 1 ? this.kategoriOpsi[0] : null;
        },
        adaFilter() {
            return !!(this.filters.q || this.filters.kategori || this.filters.status || (this.filters.rentang && this.filters.rentang.length));
        },
        jumlahFilter() {
            return [this.filters.q, this.filters.kategori, this.filters.status, this.filters.rentang?.length ? 1 : null].filter(Boolean).length;
        },
    },
    watch: {
        'filters.kategori'() { this.load(); },
    },
    mounted() {
        this.load();
        this.loadJenis();
    },
    methods: {
        /** Hari yang sudah lewat tidak bisa dipilih (masukan user 2 Okt 2026). */
        hariLampau(d) {
            const k = new Date();

            return d < new Date(k.getFullYear(), k.getMonth(), k.getDate());
        },
        /** Lampau, atau sebelum hari `mulai` ('YYYY-MM-DD…'). */
        sebelumMulai(d, mulai) {
            if (this.hariLampau(d)) return true;
            if (!mulai) return false;
            const [y, m, t] = String(mulai).slice(0, 10).split('-').map(Number);

            return d < new Date(y, m - 1, t);
        },
        katLabel(k) { return { REKRUTMEN: 'Rekrutmen', MT: 'Management Trainee', INTERNSHIP: 'Internship' }[k] || k; },
        initials(name) {
            if (!name) return 'SY';
            const p = String(name).trim().split(/\s+/);
            return ((p[0]?.[0] || '') + (p[1]?.[0] || p[0]?.[1] || '')).toUpperCase() || 'SY';
        },
        async loadJenis() {
            try {
                const res = await axios.get('/api/v1/karir/options/jenis-agenda', CFG);
                this.jenisOptions = res.data.result || [];
            } catch (e) {
                this.jenisOptions = [];
            }
        },
        jenisInfo(j) { return this.jenisOptions.find((o) => o.value === j) || null; },
        jenisLabel(j) { return this.jenisInfo(j)?.label || j; },
        jenisIkon(j) { return this.jenisInfo(j)?.ikon || 'bi-dot'; },
        jenisWarna(j) { return this.jenisInfo(j)?.warna || '#64748b'; },
        fmt(d) {
            if (!d) return '—';
            const dt = new Date(String(d).replace(' ', 'T'));
            return isNaN(dt.getTime()) ? d : dt.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
        },
        /** Ketik di kolom cari -> tunggu 400ms lalu minta ke server sekali saja. */
        cariDebounce() {
            if (this.cariTimer) clearTimeout(this.cariTimer);
            this.cariTimer = setTimeout(() => this.load(), 400);
        },
        resetFilter() {
            this.filters = { q: '', kategori: null, status: null, rentang: null };
            this.sheetOpen = false;
            this.load();
        },
        async load() {
            this.loading = true;
            try {
                const params = {
                    q: this.filters.q || undefined,
                    kategori: this.filters.kategori || undefined,
                    status: this.filters.status || undefined,
                    dari: this.filters.rentang?.[0] || undefined,
                    sampai: this.filters.rentang?.[1] || undefined,
                };
                const res = await axios.get(API, { ...CFG, params });
                const r = res.data.result || {};
                this.list = r.data || [];
                this.kategoriOpsi = r.kategori || [];
                if (this.fokusId && this.list.some((j) => j.id === this.fokusId)) {
                    this.open = this.fokusId;
                    this.$nextTick(() => document.getElementById(`jadwal-${this.fokusId}`)?.scrollIntoView({ behavior: 'smooth', block: 'center' }));
                }
            } catch (e) {
                this.notice('Gagal memuat data jadwal.');
            } finally {
                this.loading = false;
            }
        },
        openCreate() {
            this.editingId = null;
            this.form = { kegiatan: '', kategori: '', alur: '', agenda: [] };
            // Hak akses cuma satu kategori -> langsung dipasang. Tanpa ini borang
            // menampilkan pil terkunci berisi namanya, tapi nilainya tetap kosong —
            // dan penyimpanan ditolak "Kategori wajib dipilih" untuk pilihan yang
            // memang tidak pernah ditawarkan kepadanya.
            if (this.kategoriTunggal) this.form.kategori = this.kategoriTunggal.kode;
            this.show = true;
        },
        openEdit(j) {
            this.editingId = j.id;
            this.form = {
                kegiatan: j.kegiatan,
                kategori: j.kategori,
                alur: j.alur || '',
                status: j.status,
                agenda: (j.agenda || []).map((a) => ({ jenis: a.jenis, label: a.label, mulai: a.mulai, selesai: a.selesai })),
            };
            this.show = true;
        },
        addRow() { this.form.agenda.push({ jenis: 'RAPAT', label: '', mulai: null, selesai: null }); },
        removeRow(i) { this.form.agenda.splice(i, 1); },
        async save() {
            if (this.saving) return;
            if (!this.form.kegiatan.trim()) return this.notice('Nama kegiatan wajib diisi.');
            if (!this.form.kategori) return this.notice('Kategori wajib dipilih.');
            if (this.form.agenda.some((a) => !a.label || !a.label.trim())) return this.notice('Setiap agenda wajib punya label.');
            this.saving = true;
            const payload = { ...this.form };
            try {
                if (this.editingId) {
                    await axios.put(`${API}/${this.editingId}`, payload, CFG);
                    this.notice('Jadwal diperbarui.');
                } else {
                    await axios.post(API, payload, CFG);
                    this.notice('Jadwal ditambahkan.');
                }
                this.show = false;
                await this.load();
            } catch (e) {
                this.notice(e.response?.data?.message || 'Gagal menyimpan.');
            } finally {
                this.saving = false;
            }
        },
        async setStatus(j, v) {
            const prev = j.status;
            j.status = v ? 'AKTIF' : 'NONAKTIF';
            try {
                await axios.patch(`${API}/${j.id}/toggle`, { aktif: v }, CFG);
                this.notice(`Jadwal "${j.kegiatan}" ${v ? 'diaktifkan' : 'dinonaktifkan'}.`);
            } catch (e) {
                j.status = prev;
                this.notice('Gagal mengubah status.');
            }
        },
        /* ── Duplikat jadwal ── */
        openDup(j) {
            this.dupTarget = j;
            // Saran nama: tambahkan "(Salinan)" supaya tidak bentrok & jelas asalnya.
            this.dupForm = { kegiatan: `${j.kegiatan} (Salinan)`, alur: j.alur || '' };
            this.dupShow = true;
        },
        async simpanDup() {
            if (this.dupSaving || !this.dupTarget) return;
            if (!this.dupForm.kegiatan.trim()) return this.notice('Nama kegiatan baru wajib diisi.');
            this.dupSaving = true;
            try {
                const res = await axios.post(`${API}/${this.dupTarget.id}/duplikat`, {
                    kegiatan: this.dupForm.kegiatan,
                    alur: this.dupForm.alur || null,
                }, CFG);
                this.notice(res.data?.message || 'Jadwal diduplikat.');
                this.dupShow = false;
                this.dupTarget = null;
                await this.load();
            } catch (e) {
                this.notice(e.response?.data?.message || 'Gagal menduplikat jadwal.');
            } finally {
                this.dupSaving = false;
            }
        },

        askRemove(j) { this.delTarget = j; this.delShow = true; },
        async confirmDelete() {
            if (this.deleting || !this.delTarget) return;
            const j = this.delTarget;
            this.deleting = true;
            try {
                await axios.delete(`${API}/${j.id}`, CFG);
                this.notice('Jadwal dihapus.');
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
/* ── FILTER PANEL — card di desktop, bottom-sheet via FAB di mobile.
   Bentuknya sengaja sama persis dengan Master Alur: keduanya daftar master
   yang dikerjakan orang yang sama berselang beberapa menit, dan dua panel
   saringan yang berbeda rupa akan terbaca sebagai dua aturan berbeda. ── */
.jdw-filter { background: #fff; border: 1px solid rgba(15, 23, 42, .08); border-radius: 16px; padding: .9rem 1rem 1rem; margin-bottom: 1rem; box-shadow: 0 8px 24px rgba(15, 23, 42, .04); }
.jdw-filter__head { display: flex; align-items: center; justify-content: space-between; margin-bottom: .65rem; }
.jdw-filter__title { display: inline-flex; align-items: center; gap: .45rem; font-size: 12px; font-weight: 800; letter-spacing: .06em; text-transform: uppercase; color: #4338ca; }
.jdw-filter__act { display: inline-flex; align-items: center; gap: .4rem; }
.jdw-filter__reset { display: inline-flex; align-items: center; gap: .3rem; border: 1px solid rgba(79, 70, 229, .25); background: #eef2ff; color: #4338ca; font-size: 11.5px; font-weight: 700; border-radius: 9px; padding: 4px 10px; cursor: pointer; }
.jdw-filter__reset:hover { background: #e0e7ff; }
.jdw-filter__close { display: none; border: none; background: transparent; color: #64748b; font-size: 15px; cursor: pointer; padding: 4px; }
.jdw-filter__grid { display: grid; grid-template-columns: minmax(200px, 1.4fr) 1fr 1fr 1.4fr; gap: .7rem; align-items: end; }
@media (max-width: 960px) { .jdw-filter__grid { grid-template-columns: 1fr 1fr; } }

/* FAB — hanya mobile */
.jdw-fab { display: none; position: fixed; right: 18px; bottom: 20px; z-index: 70; width: 52px; height: 52px; border-radius: 50%; border: none; background: linear-gradient(135deg, #8b5cf6, #6366f1); color: #fff; font-size: 19px; cursor: pointer; box-shadow: 0 12px 28px rgba(99, 102, 241, .45); }
.jdw-fab__badge { position: absolute; top: -4px; right: -4px; min-width: 19px; height: 19px; border-radius: 999px; background: #ef4444; color: #fff; font-size: 10.5px; font-weight: 800; display: grid; place-items: center; padding: 0 5px; border: 2px solid #fff; }
.jdw-sheetbg { display: none; }

@media (max-width: 640px) {
    .jdw-filter { display: none; }
    .jdw-filter.is-open { display: block; position: fixed; left: 0; right: 0; bottom: 0; z-index: 80; margin: 0; border-radius: 18px 18px 0 0; box-shadow: 0 -18px 40px rgba(15, 23, 42, .25); max-height: 78vh; overflow-y: auto; }
    .jdw-filter__grid { grid-template-columns: 1fr; }
    .jdw-filter__close { display: inline-flex; }
    .jdw-fab { display: grid; place-items: center; }
    .jdw-sheetbg { display: block; position: fixed; inset: 0; z-index: 75; background: rgba(15, 23, 42, .45); }
}

/* Daftar kosong — kalimatnya berbeda antara "belum ada" dan "tersaring habis". */
.jdw-kosong { display: flex; flex-direction: column; align-items: center; gap: .5rem; padding: 2.6rem 1.2rem; text-align: center; background: #fff; border: 1px dashed rgba(15, 23, 42, .12); border-radius: 16px; }
.jdw-kosong > i { font-size: 26px; color: #a5b4fc; }
.jdw-kosong b { font-size: 14px; color: #334155; }
.jdw-kosong span { font-size: 12.5px; line-height: 1.65; color: #64748b; max-width: 34rem; }
.jdw-kosong .jdw-filter__reset { margin-top: .3rem; }

/* Kategori yang sudah pasti — KETERANGAN, bukan kendali. Sengaja tidak
   menyerupai kotak input: bingkai kosong berisi satu nilai terbaca sebagai
   input mati, dan orang mencoba mengkliknya. */
.jdw-kat-tetap { display: flex; align-items: center; flex-wrap: wrap; gap: .5rem; padding: .15rem 0; }
.jdw-kat-tetap small { display: inline-flex; align-items: center; gap: .3rem; font-size: .72rem; font-weight: 700; color: var(--muted); }

.mjd-statusnote { display: flex; align-items: center; gap: .45rem; font-size: 12.5px; color: #047857; background: rgba(16,185,129,.1); border: 1px solid rgba(16,185,129,.25); border-radius: 10px; padding: .55rem .7rem; }

/* Ringkasan "yang ikut tersalin" pada modal duplikat — supaya admin tahu
   persis apa yang dibawa dan apa yang perlu ia ganti. */
.jdw-dupinfo { display: flex; flex-direction: column; gap: .4rem; padding: .6rem .75rem; border-radius: 11px; background: rgba(79,70,229,.05); border: 1px solid rgba(79,70,229,.16); }
.jdw-dupinfo__row { display: flex; align-items: center; justify-content: space-between; gap: .6rem; font-size: 12px; color: #64748b; }
.jdw-dupinfo__row b { color: #334155; font-weight: 700; }
.jdw-dupinfo__row b small { color: #94a3b8; font-weight: 600; }
.jdw-dupinfo__row > span { display: inline-flex; align-items: center; gap: .35rem; }
.jdw-hint { display: block; margin-top: .3rem; font-size: 11px; color: #94a3b8; }
.jdw-head-act { display: inline-flex; align-items: center; gap: .4rem; margin-left: auto; }
.jdw-meta { margin-bottom: .8rem; }
.jdw-empty-agenda { color: #94a3b8; font-size: 13px; }
.jdw-agenda { display: flex; flex-direction: column; gap: .6rem; }
.jdw-arow { display: flex; align-items: flex-start; gap: .6rem; background: #f8fafc; border: 1px solid rgba(11, 16, 51, .07); border-radius: 12px; padding: .7rem; }
.jdw-arow__num { flex: 0 0 auto; width: 26px; height: 26px; border-radius: 8px; background: #4f46e5; color: #fff; display: grid; place-items: center; font: 700 12px 'Plus Jakarta Sans', sans-serif; margin-top: 1.5rem; }
/* Grid 2 kolom (tiap field col-6 dari 12) — lebih lega daripada 4 berdesakan. */
.jdw-arow__grid { flex: 1; display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .6rem .7rem; min-width: 0; }
.jdw-col6 { min-width: 0; }
.jdw-hint { display: flex; align-items: center; gap: .35rem; margin-top: .3rem; font-size: 11px; color: #64748b; line-height: 1.4; }
.jdw-opt { display: flex; align-items: center; gap: .5rem; padding: .1rem 0; }
.jdw-opt__ic { flex: none; font-size: 1rem; }
.jdw-opt__txt { display: flex; flex-direction: column; line-height: 1.3; }
.jdw-opt__txt small { color: #94a3b8; font-size: 11px; }
.pkg-card.is-focus { border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99, 102, 241, .14), 0 14px 32px rgba(15, 23, 42, .08); }
@media (max-width: 560px) {
    .jdw-arow__grid { grid-template-columns: 1fr; }
    .jdw-arow__num { display: none; }
}
</style>
