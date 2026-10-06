<!-- WEB CAREER — Master Info Divisi. Konten landing page per divisi/departemen (HRIS).
     DATA via web route + ResponseHelper (axios), bukan props Inertia. -->
<template>
    <Head title="Master Info Divisi" />
    <div class="wca">
        <!-- ═══════════ LIST VIEW ═══════════ -->
        <template v-if="!detail">
            <div class="wca-phead">
                <div>
                    <h1>Master Info Divisi</h1>
                    <p>
                        Konten perkenalan tiap divisi &amp; departemen untuk landing page karir
                        (section <b>Fungsi Perusahaan</b>, halaman <b>Semua Tim</b> dan <b>Detail Tim</b>).
                    </p>
                </div>
            </div>

            <div class="wca-toolbar">
                <div class="wca-search2">
                    <i class="bi bi-search"></i>
                    <input v-model="q" type="text" placeholder="Cari nama divisi / label…" />
                </div>
            </div>

            <div class="wca-card">
                <div class="wca-card__body--flush">
                    <div v-loading="loading" class="wca-tablewrap">
                        <table class="wca-table">
                            <thead>
                                <tr>
                                    <th>Divisi</th>
                                    <th>Label Tampilan</th>
                                    <th>Kelengkapan</th>
                                    <th>Departemen</th>
                                    <th>Terakhir Diubah</th>
                                    <th>Status</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="d in filtered" :key="d.id">
                                    <td>
                                        <strong>{{ d.namaHris }}</strong>
                                    </td>
                                    <td>
                                        <template v-if="d.label">{{ d.label }}</template>
                                        <span v-else class="mdi-muted">—</span>
                                    </td>
                                    <td>
                                        <span class="wca-badge" :class="d.punyaInfo ? 'wca-b--green' : 'wca-b--amber'">
                                            {{ d.punyaInfo ? 'Terisi' : 'Belum diisi' }}
                                        </span>
                                    </td>
                                    <td>
                                        <span :class="{ 'mdi-muted': !d.jumlahSub }">
                                            {{ d.jumlahSubTerisi }}/{{ d.jumlahSub }} terisi
                                        </span>
                                    </td>
                                    <td><AuditStamp :by="d.updatedBy" :at="d.updatedAt" /></td>
                                    <td>
                                        <div class="mdi-status">
                                            <el-switch
                                                :model-value="d.status === 'AKTIF'"
                                                :disabled="!d.punyaInfo"
                                                @change="(v) => setStatus(d, v)"
                                            />
                                            <span class="mdi-status__lbl" :class="d.status === 'AKTIF' ? 'is-on' : 'is-off'">
                                                {{ d.punyaInfo ? (d.status === 'AKTIF' ? 'Aktif' : 'Nonaktif') : '—' }}
                                            </span>
                                        </div>
                                    </td>
                                    <td>
                                        <button class="wca-btn wca-btn--ghost mdi-kelola" @click="openKelola(d)">
                                            <i class="bi bi-pencil-square"></i> Kelola
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="!loading && !filtered.length">
                                    <td colspan="7">
                                        <div class="wca-empty">
                                            <i class="bi bi-diagram-3"></i>
                                            <h4>Tidak ada divisi cocok</h4>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </template>

        <!-- ═══════════ DRILL-DOWN: KELOLA SATU DIVISI ═══════════ -->
        <template v-else>
            <div class="wca-phead">
                <div>
                    <button class="mdi-back" @click="detail = null">
                        <i class="bi bi-arrow-left"></i> Kembali ke daftar divisi
                    </button>
                    <h1>{{ detail.label || detail.namaHris }}</h1>
                    <p>
                        Divisi: <b>{{ detail.namaHris }}</b>
                    </p>
                </div>
            </div>

            <div v-loading="detailLoading">
                <!-- ── A. Informasi Divisi ── -->
                <div class="wca-card mdi-card">
                    <div class="wca-card__body">
                        <div class="wca-fsection__label"><i class="bi bi-card-text"></i> Informasi Divisi</div>
                        <div class="wca-form">
                            <div class="wca-frow">
                                <div>
                                    <label class="wca-field-lbl">Nama Divisi di Website</label>
                                    <el-input v-model="form.label" maxlength="100" :placeholder="detail.namaHris" />
                                    <small class="mdi-hint">
                                        Nama yang dilihat pengunjung di website karir, mis. <i>"Information Technology"</i>.
                                        Kosongkan bila ingin memakai nama asli: <b>{{ detail.namaHris }}</b>.
                                    </small>
                                </div>
                                <div>
                                    <label class="wca-field-lbl">Kalimat Pembuka "Tentang Tim"</label>
                                    <el-input v-model="form.judulUtama" maxlength="255" placeholder='Contoh: "Bukan sekadar tim pendukung, tapi rekan berpikir."' />
                                    <small class="mdi-hint">
                                        Judul besar pada bagian <b>Tentang Tim</b> di halaman detail — buat kalimat menarik yang
                                        menggambarkan semangat divisi.
                                    </small>
                                </div>
                            </div>
                            <div class="wca-frow">
                                <div>
                                    <label class="wca-field-lbl">Ringkasan Singkat Divisi</label>
                                    <el-input v-model="form.deskripsiSingkat" type="textarea" :rows="3" maxlength="1000" show-word-limit
                                        placeholder="Contoh: Tim yang menjaga seluruh sistem, data, dan alat kerja di Evo berjalan lancar." />
                                    <small class="mdi-hint">
                                        1–2 kalimat. Tampil di <b>kartu divisi</b> (halaman utama &amp; Semua Tim) dan di bawah
                                        nama divisi pada halaman detail.
                                    </small>
                                </div>
                                <div>
                                    <label class="wca-field-lbl">Cerita Lengkap Divisi</label>
                                    <el-input v-model="form.deskripsiDetail" type="textarea" :rows="3" maxlength="8000"
                                        placeholder="Ceritakan keseharian tim, cara kerja, dan perannya bagi perusahaan…" />
                                    <small class="mdi-hint">
                                        Beberapa paragraf bebas. Tampil sebagai isi bagian <b>Tentang Tim</b> di halaman detail divisi.
                                    </small>
                                </div>
                            </div>

                            <div>
                                <label class="wca-field-lbl">Poin-Poin Tugas / Keunggulan Divisi</label>
                                <small class="mdi-hint" style="margin: 0 0 0.5rem">
                                    Daftar singkat (maks 10) yang tampil sebagai checklist ✓ di bagian <b>Tentang Tim</b> —
                                    satu baris satu poin, mis. <i>"Membangun &amp; merawat aplikasi internal perusahaan"</i>.
                                </small>
                                <div v-for="(p, i) in form.poin" :key="i" class="mdi-poin">
                                    <i class="bi bi-check-circle-fill"></i>
                                    <el-input v-model="form.poin[i]" maxlength="255" placeholder="Tulis satu poin tugas / keunggulan…" />
                                    <button class="wca-iconbtn wca-iconbtn--danger" title="Hapus poin" @click="form.poin.splice(i, 1)">
                                        <i class="bi bi-x-lg"></i>
                                    </button>
                                </div>
                                <button class="wca-btn wca-btn--ghost" :disabled="form.poin.length >= 10" @click="form.poin.push('')">
                                    <i class="bi bi-plus-lg"></i> Tambah Poin
                                </button>
                            </div>

                            <div class="mdi-actions">
                                <el-button type="primary" :loading="saving" @click="saveDivisi">
                                    {{ saving ? 'Menyimpan…' : 'Simpan Informasi' }}
                                </el-button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ── B. Gambar ── -->
                <div class="wca-card mdi-card">
                    <div class="wca-card__body">
                        <div class="wca-fsection__label"><i class="bi bi-images"></i> Foto Divisi</div>
                        <p class="mdi-hint" style="margin-top: -0.25rem">
                            JPG / PNG / WebP, maksimal 2 MB per foto. Klik ikon <i class="bi bi-upload"></i> untuk mengunggah.
                        </p>
                        <div class="mdi-imggrid">
                            <div v-for="s in SLOTS" :key="s.key" class="mdi-imgslot">
                                <div class="mdi-imgslot__frame" v-loading="imgBusy[s.key]">
                                    <img v-if="detail.img && detail.img[s.key]" :src="detail.img[s.key]" :alt="s.label" />
                                    <div v-else class="mdi-imgslot__empty"><i class="bi bi-image"></i></div>
                                </div>
                                <div class="mdi-imgslot__bar">
                                    <span>{{ s.label }}</span>
                                    <div class="mdi-imgslot__btns">
                                        <button class="wca-iconbtn" title="Unggah" @click="pickImg('divisi', detail.id, s.key)">
                                            <i class="bi bi-upload"></i>
                                        </button>
                                        <button v-if="detail.img && detail.img[s.key]" class="wca-iconbtn wca-iconbtn--danger"
                                            title="Hapus" @click="removeImg('divisi', detail.id, s.key)">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </div>
                                <small class="mdi-hint">{{ s.hint }}</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ── C. Departemen ── -->
                <div class="wca-card mdi-card">
                    <div class="wca-card__body--flush">
                        <div class="wca-fsection__label" style="padding: 1rem 1rem 0.4rem">
                            <i class="bi bi-diagram-2"></i> Departemen ({{ detail.sub.length }})
                        </div>
                        <div class="wca-tablewrap">
                            <table class="wca-table">
                                <thead>
                                    <tr>
                                        <th>Departemen</th>
                                        <th>Label</th>
                                        <th>Kelengkapan</th>
                                        <th>Status</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="s in detail.sub" :key="s.id">
                                        <td><strong>{{ s.namaHris }}</strong></td>
                                        <td>
                                            <template v-if="s.label">{{ s.label }}</template>
                                            <span v-else class="mdi-muted">—</span>
                                        </td>
                                        <td>
                                            <span class="wca-badge" :class="s.punyaInfo ? 'wca-b--green' : 'wca-b--amber'">
                                                {{ s.punyaInfo ? 'Terisi' : 'Belum diisi' }}
                                            </span>
                                        </td>
                                        <td>
                                            <div class="mdi-status">
                                                <el-switch
                                                    :model-value="s.status === 'AKTIF'"
                                                    :disabled="!s.punyaInfo"
                                                    @change="(v) => setSubStatus(s, v)"
                                                />
                                            </div>
                                        </td>
                                        <td>
                                            <button class="wca-btn wca-btn--ghost mdi-kelola" @click="openSubEdit(s)">
                                                <i class="bi bi-pencil-square"></i> Kelola
                                            </button>
                                        </td>
                                    </tr>
                                    <tr v-if="!detail.sub.length">
                                        <td colspan="5">
                                            <div class="wca-empty">
                                                <i class="bi bi-diagram-2"></i>
                                                <h4>Divisi ini tidak punya departemen terdaftar</h4>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </template>

        <!-- ── Modal edit departemen ── -->
        <AdminModal
            :show="subShow"
            :title="subEditing ? `Info: ${subEditing.namaHris}` : 'Info Departemen'"
            subtitle="Konten departemen untuk halaman Detail Tim"
            icon="bi-diagram-2"
            save-label="Simpan Departemen"
            @close="closeSub"
            @save="saveSub"
        >
            <div class="wca-fsection">
                <div class="wca-fsection__label"><i class="bi bi-card-text"></i> Informasi</div>
                <div class="wca-form">
                    <div class="wca-frow">
                        <div>
                            <label class="wca-field-lbl">Nama Departemen di Website</label>
                            <el-input v-model="subForm.label" maxlength="100" :placeholder="subEditing?.namaHris || ''" />
                            <small class="mdi-hint">Kosongkan bila ingin memakai nama asli: <b>{{ subEditing?.namaHris }}</b>.</small>
                        </div>
                        <div>
                            <label class="wca-field-lbl">Kalimat Pembuka</label>
                            <el-input v-model="subForm.judulUtama" maxlength="255" placeholder="Kalimat menarik tentang departemen ini…" />
                        </div>
                    </div>
                    <div>
                        <label class="wca-field-lbl">Ringkasan Singkat Departemen</label>
                        <el-input v-model="subForm.deskripsiSingkat" type="textarea" :rows="2" maxlength="1000" show-word-limit
                            placeholder="1-2 kalimat tentang peran departemen ini…" />
                        <small class="mdi-hint">Tampil di kartu sub-fungsi pada halaman detail divisi.</small>
                    </div>
                    <div>
                        <label class="wca-field-lbl">Cerita Lengkap Departemen</label>
                        <el-input v-model="subForm.deskripsiDetail" type="textarea" :rows="3" maxlength="8000"
                            placeholder="Ceritakan keseharian dan peran departemen ini…" />
                    </div>
                    <div>
                        <label class="wca-field-lbl">Poin-Poin Tugas / Keunggulan</label>
                        <div v-for="(p, i) in subForm.poin" :key="i" class="mdi-poin">
                            <i class="bi bi-check-circle-fill"></i>
                            <el-input v-model="subForm.poin[i]" maxlength="255" placeholder="Tulis satu poin tugas / keunggulan…" />
                            <button class="wca-iconbtn wca-iconbtn--danger" title="Hapus poin" @click="subForm.poin.splice(i, 1)">
                                <i class="bi bi-x-lg"></i>
                            </button>
                        </div>
                        <button class="wca-btn wca-btn--ghost" :disabled="subForm.poin.length >= 10" @click="subForm.poin.push('')">
                            <i class="bi bi-plus-lg"></i> Tambah Poin
                        </button>
                    </div>
                </div>
            </div>

            <div class="wca-fsection">
                <div class="wca-fsection__label"><i class="bi bi-images"></i> Gambar</div>
                <div class="mdi-imggrid mdi-imggrid--modal">
                    <div v-for="s in SLOTS" :key="s.key" class="mdi-imgslot">
                        <div class="mdi-imgslot__frame" v-loading="subImgBusy[s.key]">
                            <img v-if="subForm.img && subForm.img[s.key]" :src="subForm.img[s.key]" :alt="s.label" />
                            <div v-else class="mdi-imgslot__empty"><i class="bi bi-image"></i></div>
                        </div>
                        <div class="mdi-imgslot__bar">
                            <span>{{ s.label }}</span>
                            <div class="mdi-imgslot__btns">
                                <button class="wca-iconbtn" title="Unggah" @click="pickImg('sub', subEditing.id, s.key)">
                                    <i class="bi bi-upload"></i>
                                </button>
                                <button v-if="subForm.img && subForm.img[s.key]" class="wca-iconbtn wca-iconbtn--danger"
                                    title="Hapus" @click="removeImg('sub', subEditing.id, s.key)">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </div>
                        <small class="mdi-hint">{{ s.hint }}</small>
                    </div>
                </div>
            </div>
        </AdminModal>

        <!-- input file tersembunyi (dipakai semua slot) -->
        <input ref="fileInput" type="file" accept=".jpg,.jpeg,.png,.webp" style="display: none" @change="onFilePicked" />
    </div>
</template>

<script>
import axios from 'axios';
import { Head } from '@inertiajs/vue3';
import AdminModal from '@career/AdminModal.vue';
import AuditStamp from '@career/AuditStamp.vue';
import { ingatModal } from '@utils/ingatModal';

const API = '/api/v1/master-info-divisi';
const CFG = { headers: { Accept: 'application/json' } };
const SLOTS = [
    { key: 'header', label: 'Foto Sampul', hint: 'Latar kartu divisi & bagian atas halaman detail' },
    { key: 'utama', label: 'Galeri 1 (besar)', hint: 'Foto terbesar di galeri "Tentang Tim"' },
    { key: 'img2', label: 'Galeri 2', hint: 'Foto pendamping di galeri "Tentang Tim"' },
    { key: 'img3', label: 'Galeri 3', hint: 'Foto pendamping di galeri "Tentang Tim"' },
];

export default {
    components: { Head, AdminModal, AuditStamp },
    // Modal di halaman ini selamat dari refresh — lihat @utils/ingatModal.
    mixins: [ingatModal('admin/master-divisi-info/masterDivisiInfo')],
    data() {
        return {
            SLOTS,
            list: [],
            loading: false,
            q: '',
            // drill-down
            detail: null,
            detailLoading: false,
            form: { label: '', deskripsiSingkat: '', judulUtama: '', deskripsiDetail: '', poin: [] },
            saving: false,
            imgBusy: { header: false, utama: false, img2: false, img3: false },
            // modal departemen
            subShow: false,
            subEditing: null,
            subForm: { label: '', deskripsiSingkat: '', judulUtama: '', deskripsiDetail: '', poin: [], img: {} },
            // upload context (input file tersembunyi dipakai bergantian)
            pending: null,
            subImgBusy: { header: false, utama: false, img2: false, img3: false },
        };
    },
    computed: {
        filtered() {
            const s = this.q.trim().toLowerCase();
            if (!s) return this.list;
            return this.list.filter((d) => (`${d.namaHris} ${d.label || ''}`).toLowerCase().includes(s));
        },
    },
    async mounted() {
        await this.load();
        // Deep-link dari Program Kegiatan: ?buka=<id> → langsung buka editor divisi.
        this.bukaDariUrl();
    },
    methods: {
        async load() {
            this.loading = true;
            try {
                const res = await axios.get(API, CFG);
                this.list = res.data.result || [];
            } catch (e) {
                console.error('[MasterDivisiInfo:load]', e);
                this.$message.error(e.response?.data?.message || 'Gagal memuat data divisi.');
            } finally {
                this.loading = false;
            }
        },
        /** Buka editor divisi otomatis bila datang dengan query ?buka=<id>. */
        bukaDariUrl() {
            try {
                const id = new URLSearchParams(window.location.search).get('buka');
                if (!id) return;
                const row = this.list.find((d) => String(d.id) === String(id));
                if (row) this.openKelola(row);
            } catch (e) { /* abaikan — deep-link opsional */ }
        },
        async openKelola(row) {
            this.detail = { ...row, sub: [], img: {} };
            await this.fetchDetail(row.id);
        },
        async fetchDetail(id) {
            this.detailLoading = true;
            try {
                const res = await axios.get(`${API}/${id}`, CFG);
                this.detail = res.data.result;
                this.form = {
                    label: this.detail.label || '',
                    deskripsiSingkat: this.detail.deskripsiSingkat || '',
                    judulUtama: this.detail.judulUtama || '',
                    deskripsiDetail: this.detail.deskripsiDetail || '',
                    poin: [...(this.detail.poin || [])],
                };
            } catch (e) {
                console.error('[MasterDivisiInfo:fetchDetail]', e);
                this.$message.error(e.response?.data?.message || 'Gagal memuat detail divisi.');
                this.detail = null;
            } finally {
                this.detailLoading = false;
            }
        },
        async saveDivisi() {
            if (this.saving || !this.detail) return;
            this.saving = true;
            try {
                const payload = { ...this.form, poin: this.form.poin.map((p) => p.trim()).filter(Boolean) };
                await axios.put(`${API}/${this.detail.id}`, payload, CFG);
                this.$message.success('Informasi divisi disimpan.');
                await this.fetchDetail(this.detail.id);
                this.load(); // refresh list di belakang layar
            } catch (e) {
                console.error('[MasterDivisiInfo:saveDivisi]', e);
                this.$message.error(e.response?.data?.message || 'Gagal menyimpan informasi divisi.');
            } finally {
                this.saving = false;
            }
        },
        async setStatus(d, v) {
            const prev = d.status;
            d.status = v ? 'AKTIF' : 'NONAKTIF';
            try {
                await axios.patch(`${API}/${d.id}/toggle`, { aktif: v }, CFG);
                this.$message.success(`"${d.label || d.namaHris}" ${v ? 'diaktifkan' : 'dinonaktifkan'}.`);
            } catch (e) {
                d.status = prev;
                console.error('[MasterDivisiInfo:setStatus]', e);
                this.$message.error(e.response?.data?.message || 'Gagal mengubah status.');
            }
        },
        async setSubStatus(s, v) {
            const prev = s.status;
            s.status = v ? 'AKTIF' : 'NONAKTIF';
            try {
                await axios.patch(`${API}/sub/${s.id}/toggle`, { aktif: v }, CFG);
                this.$message.success(`"${s.label || s.namaHris}" ${v ? 'diaktifkan' : 'dinonaktifkan'}.`);
            } catch (e) {
                s.status = prev;
                console.error('[MasterDivisiInfo:setSubStatus]', e);
                this.$message.error(e.response?.data?.message || 'Gagal mengubah status departemen.');
            }
        },
        closeSub() {
            this.subShow = false;
            // Gambar terunggah langsung tersimpan walau modal ditutup tanpa
            // "Simpan" — segarkan daftar sub agar preview tidak basi.
            if (this.detail) this.fetchDetail(this.detail.id);
        },
        openSubEdit(s) {
            this.subEditing = s;
            this.subForm = {
                label: s.label || '',
                deskripsiSingkat: s.deskripsiSingkat || '',
                judulUtama: s.judulUtama || '',
                deskripsiDetail: s.deskripsiDetail || '',
                poin: [...(s.poin || [])],
                img: { ...(s.img || {}) },
            };
            this.subShow = true;
        },
        async saveSub() {
            if (!this.subEditing) return;
            try {
                const payload = {
                    label: this.subForm.label,
                    deskripsiSingkat: this.subForm.deskripsiSingkat,
                    judulUtama: this.subForm.judulUtama,
                    deskripsiDetail: this.subForm.deskripsiDetail,
                    poin: this.subForm.poin.map((p) => p.trim()).filter(Boolean),
                };
                await axios.put(`${API}/sub/${this.subEditing.id}`, payload, CFG);
                this.$message.success('Informasi departemen disimpan.');
                this.subShow = false;
                await this.fetchDetail(this.detail.id);
            } catch (e) {
                console.error('[MasterDivisiInfo:saveSub]', e);
                this.$message.error(e.response?.data?.message || 'Gagal menyimpan informasi departemen.');
            }
        },
        // ── Upload gambar (input file tersembunyi dipakai bergantian) ──
        pickImg(jenis, id, slot) {
            this.pending = { jenis, id, slot };
            this.$refs.fileInput.value = '';
            this.$refs.fileInput.click();
        },
        async onFilePicked(ev) {
            const file = ev.target.files?.[0];
            if (!file || !this.pending) return;
            const { jenis, id, slot } = this.pending;
            if (file.size > 2 * 1024 * 1024) {
                this.$message.error('Ukuran gambar melebihi 2 MB.');
                return;
            }
            const busy = jenis === 'sub' ? this.subImgBusy : this.imgBusy;
            busy[slot] = true;
            try {
                const fd = new FormData();
                fd.append('jenis', jenis);
                fd.append('slot', slot);
                fd.append('file', file);
                const res = await axios.post(`${API}/${id}/gambar`, fd, CFG);
                const url = res.data.result?.url;
                if (jenis === 'sub') {
                    this.subForm.img = { ...this.subForm.img, [slot]: url };
                } else {
                    this.detail.img = { ...this.detail.img, [slot]: url };
                }
                this.$message.success('Gambar berhasil diunggah.');
            } catch (e) {
                console.error('[MasterDivisiInfo:upload]', e);
                this.$message.error(e.response?.data?.message || 'Gagal mengunggah gambar.');
            } finally {
                busy[slot] = false;
            }
        },
        async removeImg(jenis, id, slot) {
            const busy = jenis === 'sub' ? this.subImgBusy : this.imgBusy;
            busy[slot] = true;
            try {
                await axios.delete(`${API}/${id}/gambar`, { ...CFG, data: { jenis, slot } });
                if (jenis === 'sub') {
                    this.subForm.img = { ...this.subForm.img, [slot]: null };
                } else {
                    this.detail.img = { ...this.detail.img, [slot]: null };
                }
                this.$message.success('Gambar dihapus.');
            } catch (e) {
                console.error('[MasterDivisiInfo:removeImg]', e);
                this.$message.error(e.response?.data?.message || 'Gagal menghapus gambar.');
            } finally {
                busy[slot] = false;
            }
        },
    },
};
</script>

<style scoped>
.mdi-muted {
    color: #94a3b8;
}
.mdi-status {
    display: flex;
    align-items: center;
    gap: 10px;
    --el-switch-on-color: #059669;
}
.mdi-status__lbl {
    font-size: 12px;
    font-weight: 700;
}
.mdi-status__lbl.is-on {
    color: #059669;
}
.mdi-status__lbl.is-off {
    color: #94a3b8;
}
.mdi-kelola {
    white-space: nowrap;
}
.mdi-back {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    margin-bottom: 0.5rem;
    border: 0;
    background: none;
    padding: 0;
    color: #4f46e5;
    font-size: 0.82rem;
    font-weight: 700;
    cursor: pointer;
}
.mdi-card {
    margin-bottom: 1rem;
}
.mdi-hint {
    display: block;
    margin-top: 0.25rem;
    color: #94a3b8;
    font-size: 0.75rem;
}
.mdi-poin {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 0.5rem;
}
.mdi-poin > i {
    color: #059669;
    flex: 0 0 auto;
}
.mdi-actions {
    display: flex;
    justify-content: flex-end;
    margin-top: 0.75rem;
}
/* Grid slot gambar */
.mdi-imggrid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
    gap: 0.9rem;
    margin-top: 0.75rem;
}
.mdi-imgslot__frame {
    position: relative;
    aspect-ratio: 16 / 10;
    border-radius: 0.6rem;
    overflow: hidden;
    background: #f1f5f9;
    border: 1px dashed #cbd5e1;
}
.mdi-imgslot__frame img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}
.mdi-imgslot__empty {
    position: absolute;
    inset: 0;
    display: grid;
    place-items: center;
    color: #cbd5e1;
    font-size: 1.6rem;
}
.mdi-imgslot__bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-top: 0.35rem;
    font-size: 0.78rem;
    font-weight: 700;
    color: #475569;
}
.mdi-imgslot__btns {
    display: flex;
    gap: 0.3rem;
}
</style>
