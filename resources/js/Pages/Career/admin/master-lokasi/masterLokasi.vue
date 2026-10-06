<!-- WEB CAREER — Master Lokasi (kantor sendiri & vendor: klinik MCU, tempat
     wawancara). Dipakai penjadwalan tatap muka: rekruter MEMILIH lokasi berikut
     titik petanya, bukan mengetik alamat bebas yang tak bisa dibuka kandidat. -->
<template>
    <Head title="Master Lokasi" />
    <div class="wca">
        <div class="wca-phead">
            <div>
                <h1>Master Lokasi</h1>
                <p>Kantor sendiri &amp; vendor (klinik MCU, tempat asesmen). Dipakai saat menjadwalkan <b>wawancara</b>, <b>tes offline</b>, dan <b>MCU</b> — kandidat menerima peta yang bisa dibuka, bukan alamat yang harus disalin.</p>
            </div>
            <div class="wca-phead__actions">
                <button class="wca-btn wca-btn--primary" @click="openCreate"><i class="bi bi-plus-lg"></i> Lokasi Baru</button>
            </div>
        </div>

        <!-- Saringan -->
        <div class="mlk-bar">
            <el-input v-model="cari" clearable placeholder="Cari nama, kode, alamat, atau kota…" class="mlk-bar__cari" @keyup.enter="muat">
                <template #prefix><i class="bi bi-search"></i></template>
            </el-input>
            <el-select v-model="jenis" clearable placeholder="Semua jenis" class="mlk-bar__jenis" @change="muat">
                <el-option label="Kantor" value="KANTOR" />
                <el-option label="Vendor" value="VENDOR" />
            </el-select>
            <button class="wca-btn" :disabled="loading" :onClick="loading ? null : muat">
                <i class="bi" :class="loading ? 'bi-arrow-repeat mlk-spin' : 'bi-funnel'"></i> Terapkan
            </button>
        </div>

        <div v-if="loading" class="mlk-state"><span class="mlk-spinner"></span> Memuat lokasi…</div>
        <div v-else-if="!rows.length" class="mlk-state">
            <i class="bi bi-geo-alt"></i>
            <span>Belum ada lokasi{{ cari || jenis ? ' yang cocok dengan saringan' : '' }}.</span>
        </div>

        <!-- Kartu, bukan tabel: tiap lokasi membawa peta, dan peta tidak pernah
             nyaman dijejalkan ke dalam sel tabel. -->
        <div v-else class="mlk-grid">
            <div v-for="l in rows" :key="l.id" class="mlk-card" :class="{ 'is-off': !l.aktif }">
                <div class="mlk-card__map">
                    <iframe
                        v-if="l.mapsEmbed"
                        :src="l.mapsEmbed"
                        :title="'Peta ' + l.nama"
                        loading="lazy"
                        referrerpolicy="no-referrer-when-downgrade"
                    ></iframe>
                    <div v-else class="mlk-card__nomap"><i class="bi bi-map"></i> Belum ada titik peta</div>
                </div>

                <div class="mlk-card__body">
                    <div class="mlk-card__top">
                        <span class="mlk-tag" :class="l.jenis === 'VENDOR' ? 'is-vendor' : 'is-kantor'">{{ l.jenis === 'VENDOR' ? 'Vendor' : 'Kantor' }}</span>
                        <span v-if="l.utama" class="mlk-tag is-utama"><i class="bi bi-star-fill"></i> Utama</span>
                        <span v-if="!l.aktif" class="mlk-tag is-off">Nonaktif</span>
                        <!-- Terbaca langsung dari daftar: tempat ini muncul di
                             dropdown mana saat menjadwalkan. Tanpa lencana ini,
                             satu-satunya cara mengetahuinya adalah membuka
                             jendela ubah satu per satu. -->
                        <span v-for="k in (l.peruntukan || [])" :key="k" class="mlk-tag is-guna">{{ namaPeruntukan(k) }}</span>
                        <span v-if="!(l.peruntukan || []).length" class="mlk-tag is-warn" title="Tidak akan muncul saat menjadwalkan">
                            <i class="bi bi-exclamation-triangle-fill"></i> Tanpa peruntukan
                        </span>
                    </div>

                    <h3>{{ l.nama }}</h3>
                    <p v-if="l.kategori" class="mlk-card__kat">{{ l.kategori }}</p>
                    <p v-if="l.alamat" class="mlk-card__alamat">{{ l.alamat }}</p>
                    <p v-if="l.kota" class="mlk-card__meta"><i class="bi bi-pin-map"></i> {{ [l.kota, l.provinsi].filter(Boolean).join(', ') }}</p>
                    <p v-if="l.kontakTelp" class="mlk-card__meta"><i class="bi bi-telephone"></i> {{ l.kontakTelp }}<template v-if="l.kontakNama"> · {{ l.kontakNama }}</template></p>
                    <p v-if="l.lintang !== null" class="mlk-card__koor">{{ l.lintang }}, {{ l.bujur }}</p>

                    <div class="mlk-card__act">
                        <a v-if="l.mapsUrl" :href="l.mapsUrl" target="_blank" rel="noopener" class="mlk-btn"><i class="bi bi-box-arrow-up-right"></i> Peta</a>
                        <button class="mlk-btn" @click="openEdit(l)"><i class="bi bi-pencil"></i> Ubah</button>
                        <button class="mlk-btn" @click="toggle(l)">
                            <i class="bi" :class="l.aktif ? 'bi-toggle-on' : 'bi-toggle-off'"></i>
                            {{ l.aktif ? 'Nonaktifkan' : 'Aktifkan' }}
                        </button>
                        <button class="mlk-btn is-del" @click="askDelete(l)"><i class="bi bi-trash3"></i></button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tambah / Ubah -->
        <AdminModal
            :busy="saving"
            :show="show"
            :title="editingId ? 'Ubah Lokasi' : 'Lokasi Baru'"
            subtitle="Kantor sendiri atau vendor"
            icon="bi-geo-alt"
            lg
            :save-label="editingId ? 'Perbarui' : 'Simpan Lokasi'"
            :save-disabled="!bolehSimpan"
            @close="show = false"
            @save="simpan"
        >
            <div class="wca-fsection">
                <div class="wca-fsection__label"><i class="bi bi-building"></i> Identitas</div>
                <div class="wca-form">
                    <div class="wca-frow">
                        <div>
                            <label class="wca-field-lbl">Nama <b class="mlk-req">*</b></label>
                            <el-input v-model="form.nama" placeholder="PT EVO Nusa Bersaudara" />
                        </div>
                        <div>
                            <label class="wca-field-lbl">Jenis <b class="mlk-req">*</b></label>
                            <el-select v-model="form.jenis" style="width: 100%">
                                <el-option label="Kantor (milik perusahaan)" value="KANTOR" />
                                <el-option label="Vendor (klinik / pihak ketiga)" value="VENDOR" />
                            </el-select>
                        </div>
                    </div>
                    <!-- PERUNTUKAN menentukan tempat ini muncul di dropdown MANA
                         saat menjadwalkan. MCU hanya menawarkan yang ber-MEDIS,
                         wawancara hanya yang ber-KANTOR. Boleh lebih dari satu:
                         rumah sakit yang juga menyediakan ruang wawancara tetap
                         SATU baris — satu titik peta, satu riwayat pemakaian. -->
                    <div>
                        <label class="wca-field-lbl">Dipakai untuk <b class="mlk-req">*</b></label>
                        <el-select v-model="form.peruntukan" multiple style="width: 100%" placeholder="Pilih peruntukan">
                            <el-option v-for="p in peruntukan" :key="p.kode" :value="p.kode" :label="p.nama">
                                <div class="mlk-opt">
                                    <b>{{ p.nama }}</b>
                                    <small>{{ p.deskripsi }}</small>
                                </div>
                            </el-option>
                        </el-select>
                        <!-- Tanpa peruntukan, tempat ini TIDAK PERNAH muncul di
                             mana pun. Disebutkan di sini, bukan dibiarkan
                             ketahuan berminggu-minggu kemudian saat seseorang
                             mencarinya di jendela jadwal. -->
                        <p v-if="!form.peruntukan.length" class="mlk-hint is-warn">
                            <i class="bi bi-exclamation-triangle-fill"></i>
                            Belum dipilih — lokasi ini tidak akan muncul saat menjadwalkan.
                        </p>
                    </div>
                    <div class="wca-frow">
                        <div>
                            <label class="wca-field-lbl">Kategori</label>
                            <el-input v-model="form.kategori" :placeholder="form.jenis === 'VENDOR' ? 'mis. Klinik / Rumah Sakit' : 'mis. Kantor Pusat / Pabrik'" />
                        </div>
                        <div>
                            <label class="wca-field-lbl">Kontak (nama)</label>
                            <el-input v-model="form.kontakNama" placeholder="mis. Bp. Andi — Bagian MCU" />
                        </div>
                    </div>
                    <div class="wca-frow">
                        <div>
                            <label class="wca-field-lbl">Telepon</label>
                            <el-input v-model="form.kontakTelp" placeholder="0711-123456" />
                        </div>
                        <div class="mlk-utama">
                            <el-checkbox v-model="form.utama" />
                            <div>
                                <b>Jadikan lokasi utama</b>
                                <small>Terpilih otomatis saat menjadwalkan. Hanya satu lokasi boleh bertanda utama.</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="wca-fsection">
                <div class="wca-fsection__label"><i class="bi bi-geo"></i> Alamat &amp; Titik Peta</div>
                <div class="wca-form">
                    <div class="wca-frow wca-frow--full">
                        <div>
                            <label class="wca-field-lbl">Alamat</label>
                            <el-input v-model="form.alamat" type="textarea" :rows="2" placeholder="Jalan, nomor, kelurahan, kecamatan" />
                        </div>
                    </div>
                    <div class="wca-frow">
                        <div><label class="wca-field-lbl">Kota</label><el-input v-model="form.kota" placeholder="Palembang" /></div>
                        <div><label class="wca-field-lbl">Provinsi</label><el-input v-model="form.provinsi" placeholder="Sumatera Selatan" /></div>
                    </div>
                    <div class="wca-frow">
                        <div><label class="wca-field-lbl">Lintang (latitude)</label><el-input v-model="form.lintang" placeholder="-2.9441526" /></div>
                        <div><label class="wca-field-lbl">Bujur (longitude)</label><el-input v-model="form.bujur" placeholder="104.7701185" /></div>
                    </div>

                    <!-- Menempel dari Google Maps adalah cara paling sering dipakai
                         orang, jadi disediakan langsung daripada memaksa mereka
                         memisahkan angkanya sendiri. -->
                    <div class="wca-frow wca-frow--full">
                        <div>
                            <label class="wca-field-lbl">Tempel tautan / kode sematan Google Maps</label>
                            <el-input v-model="tempelMaps" placeholder="Tempel URL Google Maps atau kode <iframe> — koordinatnya diambil otomatis" @input="ambilKoordinat" />
                            <small class="mlk-hint">
                                <i class="bi bi-info-circle"></i>
                                Yang disimpan hanya koordinatnya, bukan kode sematannya — peta disusun ulang oleh sistem agar tidak kedaluwarsa.
                            </small>
                        </div>
                    </div>

                    <div v-if="pratinjauMaps" class="mlk-prev">
                        <iframe :src="pratinjauMaps" title="Pratinjau peta" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
                        <span><i class="bi bi-check-circle-fill"></i> Titik ditemukan — periksa sebelum menyimpan.</span>
                    </div>

                    <div class="wca-frow wca-frow--full">
                        <div>
                            <label class="wca-field-lbl">Catatan internal</label>
                            <el-input v-model="form.catatan" type="textarea" :rows="2" placeholder="mis. Parkir di basement, masuk lewat lobi timur." />
                        </div>
                    </div>
                </div>
            </div>
        </AdminModal>

        <ConfirmModal
            :show="delShow"
            title="Hapus Lokasi"
            :busy="deleting"
            confirm-label="Ya, Hapus Lokasi"
            note="Lokasi yang sudah dipakai menjadwalkan tidak bisa dihapus — nonaktifkan saja agar jadwal lama tetap utuh."
            @cancel="delShow = false"
            @confirm="hapus"
        >
            Yakin ingin menghapus <strong>{{ delTarget?.nama }}</strong>?
        </ConfirmModal>

        <transition name="wca-toast">
            <div v-if="toast" class="wca-toast" :class="{ 'is-err': toastErr }">
                <i class="bi" :class="toastErr ? 'bi-exclamation-circle-fill' : 'bi-check-circle-fill'"></i> {{ toast }}
            </div>
        </transition>
    </div>
</template>

<script>
import axios from 'axios';
import { Head } from '@inertiajs/vue3';
import AdminModal from '@career/AdminModal.vue';
import ConfirmModal from '@career/ConfirmModal.vue';
import { ingatModal } from '@utils/ingatModal';

const CFG = { headers: { Accept: 'application/json' } };

const KOSONG = () => ({
    nama: '', jenis: 'KANTOR', kategori: '', alamat: '', kota: '', provinsi: '',
    lintang: '', bujur: '', kontakNama: '', kontakTelp: '', catatan: '', utama: false,
    // Untuk apa lokasi ini dipakai — menentukan ia muncul di dropdown mana saat
    // menjadwalkan. Kosong = tidak muncul di mana pun.
    peruntukan: [],
});

export default {
    name: 'MasterLokasi',
    components: { Head, AdminModal, ConfirmModal },
    // Halaman merender modal ber-teleport, jadi Vue tak punya satu simpul akar
    // untuk menempelkan atribut bawaan Inertia. Dimatikan agar tidak memicu
    // peringatan "Extraneous non-props attributes".
    inheritAttrs: false,
    // Modal di halaman ini selamat dari refresh — lihat @utils/ingatModal.
    mixins: [ingatModal('admin/master-lokasi/masterLokasi')],
    data() {
        return {
            rows: [], loading: false, cari: '', jenis: '', peruntukan: [],
            show: false, saving: false, editingId: null, form: KOSONG(), tempelMaps: '',
            delShow: false, deleting: false, delTarget: null,
            toast: '', toastErr: false, tm: null,
        };
    },
    computed: {
        bolehSimpan() { return !!this.form.nama.trim() && !!this.form.jenis; },
        /** Pratinjau peta di modal, disusun dari koordinat yang sedang diisi. */
        pratinjauMaps() {
            const la = parseFloat(this.form.lintang);
            const bu = parseFloat(this.form.bujur);
            if (Number.isNaN(la) || Number.isNaN(bu)) return '';

            return `https://www.google.com/maps?q=${la},${bu}&output=embed`;
        },
    },
    mounted() {
        this.muat();
        this.muatPeruntukan();
    },
    methods: {
        notice(x, err = false) {
            this.toast = x; this.toastErr = err;
            if (this.tm) clearTimeout(this.tm);
            this.tm = setTimeout(() => (this.toast = ''), 4000);
        },
        /** Daftar peruntukan (kantor / medis / …) — dari master, bukan ditulis di sini. */
        async muatPeruntukan() {
            try {
                const res = await axios.get('/api/v1/master-lokasi/peruntukan', CFG);
                this.peruntukan = res.data.result || [];
            } catch (e) {
                this.notice('Daftar peruntukan gagal dimuat.', true);
            }
        },
        /** Nama peruntukan untuk lencana di daftar. */
        namaPeruntukan(kode) {
            return this.peruntukan.find((p) => p.kode === kode)?.nama || kode;
        },
        async muat() {
            this.loading = true;
            try {
                const res = await axios.get('/api/v1/master-lokasi', {
                    ...CFG,
                    params: { cari: this.cari || undefined, jenis: this.jenis || undefined },
                });
                this.rows = res.data.result || [];
            } catch (e) {
                this.notice(e.response?.data?.message || 'Gagal memuat lokasi.', true);
            } finally {
                this.loading = false;
            }
        },
        /**
         * Ambil koordinat dari tautan / kode sematan Google Maps.
         *
         * Tiga bentuk yang beredar dan semuanya sering ditempel orang:
         *   .../@-2.944,104.770,17z          (URL peta biasa)
         *   ...!3d-2.944!2d104.770           (kode sematan iframe — URUTAN TERBALIK)
         *   ...?q=-2.944,104.770             (tautan berbagi)
         * Perhatikan bentuk kedua: bujur (2d) muncul SEBELUM lintang (3d), dan
         * menukarnya membuat pin jatuh di belahan bumi lain.
         */
        ambilKoordinat() {
            const t = String(this.tempelMaps || '');
            if (!t.trim()) return;

            let la = null; let bu = null;

            const embed = t.match(/!3d(-?\d+\.?\d*)/);
            const embedLng = t.match(/!2d(-?\d+\.?\d*)/);
            if (embed && embedLng) { la = embed[1]; bu = embedLng[1]; }

            if (la === null) {
                const at = t.match(/@(-?\d+\.?\d*),(-?\d+\.?\d*)/);
                if (at) { la = at[1]; bu = at[2]; }
            }
            if (la === null) {
                const q = t.match(/[?&]q=(-?\d+\.?\d*),(-?\d+\.?\d*)/);
                if (q) { la = q[1]; bu = q[2]; }
            }

            if (la !== null) {
                this.form.lintang = la;
                this.form.bujur = bu;
                this.notice('Koordinat terbaca dari tautan.');
            }
        },
        openCreate() {
            this.editingId = null;
            this.form = KOSONG();
            this.tempelMaps = '';
            this.show = true;
        },
        openEdit(l) {
            this.editingId = l.id;
            this.form = {
                nama: l.nama || '', jenis: l.jenis || 'KANTOR', kategori: l.kategori || '',
                alamat: l.alamat || '', kota: l.kota || '', provinsi: l.provinsi || '',
                lintang: l.lintang ?? '', bujur: l.bujur ?? '',
                kontakNama: l.kontakNama || '', kontakTelp: l.kontakTelp || '',
                catatan: l.catatan || '', utama: !!l.utama,
                peruntukan: [...(l.peruntukan || [])],
            };
            this.tempelMaps = '';
            this.show = true;
        },
        async simpan() {
            if (this.saving || !this.bolehSimpan) return;
            this.saving = true;
            // Koordinat kosong dikirim null, bukan string kosong — validator
            // numeric menolak '' dan pesannya membingungkan.
            const body = {
                ...this.form,
                lintang: this.form.lintang === '' ? null : this.form.lintang,
                bujur: this.form.bujur === '' ? null : this.form.bujur,
            };
            try {
                if (this.editingId) {
                    await axios.put(`/api/v1/master-lokasi/${this.editingId}`, body, CFG);
                } else {
                    await axios.post('/api/v1/master-lokasi', body, CFG);
                }
                this.notice(this.editingId ? 'Lokasi diperbarui.' : 'Lokasi ditambahkan.');
                this.show = false;
                await this.muat();
            } catch (e) {
                const err = e.response?.data?.errors;
                this.notice(err ? Object.values(err).flat()[0] : (e.response?.data?.message || 'Gagal menyimpan.'), true);
            } finally {
                this.saving = false;
            }
        },
        async toggle(l) {
            try {
                const res = await axios.patch(`/api/v1/master-lokasi/${l.id}/toggle`, {}, CFG);
                this.notice(res.data?.message || 'Status diubah.');
                await this.muat();
            } catch (e) {
                this.notice(e.response?.data?.message || 'Gagal mengubah status.', true);
            }
        },
        askDelete(l) { this.delTarget = l; this.delShow = true; },
        async hapus() {
            if (this.deleting || !this.delTarget) return;
            this.deleting = true;
            try {
                await axios.delete(`/api/v1/master-lokasi/${this.delTarget.id}`, CFG);
                this.notice('Lokasi dihapus.');
                this.delShow = false;
                this.delTarget = null;
                await this.muat();
            } catch (e) {
                // 409 = sudah dipakai menjadwalkan; pesannya sudah menjelaskan
                // apa yang harus dilakukan, jadi ditampilkan apa adanya.
                this.notice(e.response?.data?.message || 'Gagal menghapus.', true);
            } finally {
                this.deleting = false;
            }
        },
    },
};
</script>

<style scoped>
.mlk-bar { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 16px; }
.mlk-bar__cari { flex: 1; min-width: 240px; }
.mlk-bar__jenis { width: 170px; }
.mlk-spin { display: inline-block; animation: mlkSpin .9s linear infinite; }
@keyframes mlkSpin { to { transform: rotate(360deg); } }

.mlk-state { display: flex; align-items: center; justify-content: center; gap: 10px; padding: 60px 20px; color: #94a3b8; font-size: 13.5px; font-weight: 600; }
.mlk-spinner { width: 18px; height: 18px; border: 2px solid #e2e8f0; border-top-color: #6366f1; border-radius: 50%; animation: mlkSpin .8s linear infinite; }

.mlk-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 16px; }
.mlk-card { border: 1px solid #eef0f7; border-radius: 18px; overflow: hidden; background: #fff; box-shadow: 0 2px 10px rgba(15, 23, 42, .04); transition: box-shadow .2s, transform .2s; }
.mlk-card:hover { box-shadow: 0 14px 34px rgba(15, 23, 42, .09); transform: translateY(-2px); }
.mlk-card.is-off { opacity: .62; }
.mlk-card__map { height: 158px; background: #f1f5f9; }
.mlk-card__map iframe { width: 100%; height: 100%; border: 0; display: block; }
.mlk-card__nomap { height: 100%; display: flex; align-items: center; justify-content: center; gap: 8px; color: #94a3b8; font-size: 12.5px; font-weight: 700; }
.mlk-card__body { padding: 14px 16px 16px; }
.mlk-card__top { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 8px; }
.mlk-tag { padding: 3px 9px; border-radius: 999px; font-size: 10.5px; font-weight: 800; }
.mlk-tag.is-kantor { color: #4338ca; background: rgba(99, 102, 241, .12); }
.mlk-tag.is-vendor { color: #b45309; background: rgba(245, 158, 11, .15); }
.mlk-tag.is-utama { color: #059669; background: rgba(16, 185, 129, .13); }
.mlk-tag.is-off { color: #64748b; background: #f1f5f9; }
.mlk-tag.is-guna { color: #0f766e; background: rgba(20, 184, 166, .13); }
.mlk-tag.is-warn { color: #b45309; background: #fef3c7; }
.mlk-opt { display: flex; flex-direction: column; line-height: 1.35; padding: 2px 0; }
.mlk-opt small { color: #94a3b8; font-size: 11px; }
.mlk-hint { display: flex; align-items: flex-start; gap: 6px; margin: 6px 0 0; font-size: 11.5px; color: #64748b; }
.mlk-hint.is-warn { color: #b45309; }
.mlk-card__body h3 { margin: 0; font-size: 15.5px; font-weight: 800; color: #0f172a; letter-spacing: -.01em; }
.mlk-card__kat { margin: 2px 0 0; font-size: 11.5px; font-weight: 700; color: #6366f1; }
.mlk-card__alamat { margin: 7px 0 0; font-size: 12.5px; line-height: 1.55; color: #64748b; }
.mlk-card__meta { display: flex; align-items: center; gap: 6px; margin: 5px 0 0; font-size: 12px; color: #64748b; }
.mlk-card__koor { margin: 6px 0 0; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 11px; color: #a2a9ba; }
.mlk-card__act { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 12px; padding-top: 12px; border-top: 1px solid #f4f5fa; }
.mlk-btn { display: inline-flex; align-items: center; gap: 5px; padding: 6px 11px; border: 1px solid #e6e8f2; border-radius: 9px; background: #fff; font: inherit; font-size: 12px; font-weight: 700; color: #475569; cursor: pointer; text-decoration: none; transition: border-color .16s, background .16s, color .16s; }
.mlk-btn:hover { border-color: #a5b4fc; background: #f5f3ff; color: #4f46e5; }
.mlk-btn.is-del { color: #dc2626; border-color: rgba(220, 38, 38, .25); }
.mlk-btn.is-del:hover { background: #fef2f2; border-color: rgba(220, 38, 38, .5); color: #dc2626; }

.mlk-req { color: #dc2626; }
.mlk-hint { display: flex; align-items: flex-start; gap: 6px; margin-top: 5px; font-size: 11px; line-height: 1.5; color: #94a3b8; }
.mlk-utama { display: flex; align-items: flex-start; gap: 9px; padding: 10px 12px; border: 1px solid #eef0f7; border-radius: 10px; background: #fbfbfe; }
.mlk-utama b { display: block; font-size: 12.5px; color: #334155; }
.mlk-utama small { display: block; font-size: 11px; color: #94a3b8; margin-top: 2px; line-height: 1.45; }
.mlk-prev { margin-top: 10px; border: 1px solid #e6e8f2; border-radius: 12px; overflow: hidden; }
.mlk-prev iframe { display: block; width: 100%; height: 200px; border: 0; }
.mlk-prev span { display: flex; align-items: center; gap: 7px; padding: 8px 12px; border-top: 1px solid #eef0f7; font-size: 11.5px; font-weight: 700; color: #059669; background: rgba(16, 185, 129, .06); }

.wca-toast.is-err { background: #dc2626; }
</style>
