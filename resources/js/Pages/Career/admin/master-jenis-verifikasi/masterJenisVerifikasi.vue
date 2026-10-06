<template>
    <Head title="Master Jenis Verifikasi" />
    <div class="wca">
        <div class="wca-phead">
            <div>
                <h1>Master Jenis Verifikasi</h1>
                <p>
                    Komponen yang diperiksa pada tahap <b>Background Check</b> — identitas, ijazah,
                    riwayat kerja, catatan hukum. Daftar inilah yang muncul sebagai centang saat tim
                    mengerjakan pemeriksaan di Worklist.
                </p>
                <p class="mjv-beku">
                    <i class="bi bi-snow"></i>
                    Mengubah daftar ini <b>tidak menulis ulang pemeriksaan yang sudah tercatat</b>:
                    nama komponen dibekukan di barisnya masing-masing.
                </p>
            </div>
            <button class="wca-btn wca-btn--dark" @click="bukaTambah">
                <i class="bi bi-plus-lg"></i> Komponen Baru
            </button>
        </div>

        <div class="wca-stats">
            <div class="wca-stat">
                <div class="wca-stat__top">
                    <span class="wca-stat__ico" style="background: rgba(99, 102, 241, 0.14); color: #4338ca"
                        ><i class="bi bi-list-check"></i
                    ></span>
                </div>
                <div class="wca-stat__num">{{ daftar.length }}</div>
                <div class="wca-stat__label">Komponen</div>
            </div>
            <div class="wca-stat">
                <div class="wca-stat__top">
                    <span class="wca-stat__ico" style="background: rgba(5, 150, 105, 0.14); color: #047857"
                        ><i class="bi bi-check-circle"></i
                    ></span>
                </div>
                <div class="wca-stat__num">{{ jumlahAktif }}</div>
                <div class="wca-stat__label">Aktif</div>
            </div>
            <div class="wca-stat">
                <div class="wca-stat__top">
                    <span class="wca-stat__ico" style="background: rgba(220, 38, 38, 0.12); color: #b91c1c"
                        ><i class="bi bi-shield-lock"></i
                    ></span>
                </div>
                <div class="wca-stat__num">{{ jumlahSensitif }}</div>
                <div class="wca-stat__label">Data spesifik</div>
            </div>
            <div class="wca-stat">
                <div class="wca-stat__top">
                    <span class="wca-stat__ico" style="background: rgba(245, 158, 11, 0.14); color: #b45309"
                        ><i class="bi bi-hourglass-split"></i
                    ></span>
                </div>
                <div class="wca-stat__num">{{ jumlahRetensi }}</div>
                <div class="wca-stat__label">Punya batas retensi</div>
            </div>
        </div>

        <!-- Keterangan dua kolom yang paling mudah disalahpahami. Ditulis di
             muka, bukan di dalam modal: keputusannya diambil sebelum modal
             dibuka. -->
        <div class="wca-note wca-note--info mjv-note">
            <i class="bi bi-info-circle-fill"></i>
            <span>
                <b>Data spesifik</b> (UU PDP 27/2022 Psl 4 ayat 2) menandai komponen yang isinya catatan
                kejahatan atau kesehatan — pembukaannya dicatat ke jejak akses.
                <b>Retensi</b> menentukan kapan isi temuannya diredaksi otomatis; kosong berarti tidak
                diredaksi sistem ini.
            </span>
        </div>

        <div class="wca-card">
            <div class="wca-toolbar">
                <input v-model="cari" class="wca-search2 mjv-cari" type="text" placeholder="Cari komponen…" />
            </div>

            <div class="wca-tablewrap">
                <table class="wca-table">
                    <thead>
                        <tr>
                            <th style="width: 34%">Komponen</th>
                            <th style="width: 16%">Kode</th>
                            <th style="width: 12%">Retensi</th>
                            <th style="width: 12%">Dipakai</th>
                            <th style="width: 14%">Status</th>
                            <th style="width: 12%">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="loading">
                            <td colspan="6" class="wca-empty">Memuat…</td>
                        </tr>
                        <tr v-else-if="!tersaring.length">
                            <td colspan="6" class="wca-empty">Tidak ada komponen yang cocok.</td>
                        </tr>
                        <tr v-for="r in tersaring" v-else :key="r.id">
                            <td>
                                <div class="mjv-nama">
                                    <span class="mjv-ico" :style="{ color: r.warna }"><i class="bi" :class="r.ikon"></i></span>
                                    <div>
                                        <b>{{ r.nama }}</b>
                                        <span v-if="r.sensitif" class="mjv-sensitif"><i class="bi bi-lock-fill"></i> DATA SPESIFIK</span>
                                        <small v-if="r.deskripsi">{{ r.deskripsi }}</small>
                                    </div>
                                </div>
                            </td>
                            <td><code class="mjv-kode">{{ r.kode }}</code></td>
                            <td>
                                <span v-if="r.retensiHari">{{ r.retensiHari }} hari</span>
                                <span v-else class="mjv-samar">—</span>
                            </td>
                            <td>
                                <span v-if="r.dipakai">{{ r.dipakai }}×</span>
                                <span v-else class="mjv-samar">belum</span>
                            </td>
                            <td>
                                <div class="mjv-status">
                                    <button
                                        type="button" class="mjv-sw" :class="{ 'is-on': r.aktif }"
                                        role="switch" :aria-checked="r.aktif ? 'true' : 'false'"
                                        :title="r.aktif ? 'Nonaktifkan — tidak lagi ditawarkan untuk pemeriksaan baru' : 'Aktifkan kembali'"
                                        @click="toggle(r)"
                                    ><span class="mjv-sw__knob"></span></button>
                                    <span class="mjv-status__lbl" :class="r.aktif ? 'is-on' : 'is-off'">
                                        {{ r.aktif ? 'Aktif' : 'Nonaktif' }}
                                    </span>
                                </div>
                            </td>
                            <td>
                                <div class="mjv-aksi">
                                    <button class="wca-iconbtn" title="Ubah komponen" @click="bukaSunting(r)">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <!-- Bawaan sistem tidak bisa dihapus, hanya dinonaktifkan —
                                         tombolnya tetap terlihat supaya alasannya bisa dibaca,
                                         bukan hilang tanpa keterangan. -->
                                    <button
                                        class="wca-iconbtn wca-iconbtn--danger"
                                        :disabled="r.sistem"
                                        :title="r.sistem ? 'Bawaan sistem — nonaktifkan saja' : 'Hapus komponen'"
                                        :onClick="r.sistem ? null : () => askHapus(r)"
                                    >
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <AdminModal
            :show="show"
            :busy="saving"
            icon="bi-shield-check"
            :title="editing ? `Ubah — ${form.nama}` : 'Komponen Pemeriksaan Baru'"
            subtitle="Satu baris per hal yang diperiksa pada Background Check"
            :save-label="editing ? 'Perbarui' : 'Tambahkan'"
            :save-disabled="!sah"
            foot-note="Kode dipakai sistem & dibekukan pada pemeriksaan yang sudah tercatat."
            @close="show = false"
            @save="simpan"
        >
            <div class="wca-form">
                <div class="wca-frow">
                    <div>
                        <label class="wca-field-lbl">Nama komponen <span class="mjv-req">*</span></label>
                        <el-input v-model="form.nama" placeholder="mis. Verifikasi Sertifikasi Profesi" maxlength="120" />
                    </div>
                    <div>
                        <label class="wca-field-lbl">Kode <span class="mjv-req">*</span></label>
                        <el-input
                            v-model="form.kode"
                            placeholder="SERTIFIKASI_PROFESI"
                            maxlength="30"
                            :disabled="editing && editingSistem"
                            @input="form.kode = (form.kode || '').toUpperCase().replace(/[^A-Z0-9_]/g, '_')"
                        />
                        <small class="mjv-hint">
                            {{ editing && editingSistem
                                ? 'Bawaan sistem — kodenya tidak bisa diubah, namanya boleh.'
                                : 'Huruf besar, angka, garis bawah. Tidak bisa diubah setelah dipakai.' }}
                        </small>
                    </div>
                </div>

                <div>
                    <label class="wca-field-lbl">Keterangan</label>
                    <el-input v-model="form.deskripsi" type="textarea" :rows="2" maxlength="400" show-word-limit
                        placeholder="Apa yang diperiksa dan ke mana dikonfirmasi." />
                </div>

                <div class="wca-frow">
                    <div>
                        <label class="wca-field-lbl">Ikon</label>
                        <el-input v-model="form.ikon" placeholder="bi-patch-check" />
                    </div>
                    <div>
                        <label class="wca-field-lbl">Urutan tampil</label>
                        <el-input-number v-model="form.urutan" :min="0" :max="999" controls-position="right" style="width: 100%" />
                    </div>
                </div>

                <label class="mjv-kotak" :class="{ 'is-on': form.sensitif }">
                    <el-checkbox v-model="form.sensitif" />
                    <span>
                        <b>Data pribadi bersifat spesifik</b>
                        <small>
                            Catatan kejahatan atau data kesehatan (UU PDP 27/2022 Psl 4 ayat 2). Setiap
                            pembukaan temuannya dicatat ke jejak akses, dan isinya tidak boleh dibagikan
                            di luar tim yang berhak.
                        </small>
                    </span>
                </label>

                <div>
                    <label class="wca-field-lbl">Batas retensi</label>
                    <div class="mjv-retensi">
                        <el-input-number v-model="form.retensiHari" :min="1" :max="10950" :step="30" controls-position="right" style="width: 180px" />
                        <span>hari sejak pemeriksaan selesai</span>
                        <button type="button" class="mjv-kosongkan" @click="form.retensiHari = null">Kosongkan</button>
                    </div>
                    <small class="mjv-hint">
                        Sesudah lewat, <b>isi temuannya diredaksi</b> oleh perintah terjadwal — barisnya tetap
                        ada supaya laporan lama tidak berubah sendiri. Kosong = tidak diredaksi sistem ini.
                    </small>
                </div>
            </div>
        </AdminModal>

        <ConfirmModal
            :show="delShow" :busy="deleting" title="Hapus Komponen" confirm-label="Ya, Hapus"
            note="Pemeriksaan yang sudah tercatat tidak berubah — nama komponennya dibekukan di barisnya sendiri. Yang berubah: komponen ini tidak lagi ditawarkan untuk pemeriksaan baru."
            @cancel="delShow = false" @confirm="hapus"
        >
            Hapus komponen <strong>{{ delTarget?.nama }}</strong>?
        </ConfirmModal>

        <transition name="wca-toast">
            <div v-if="toast" class="wca-toast" :class="{ 'is-err': toastErr }">
                <i class="bi" :class="toastErr ? 'bi-exclamation-triangle-fill' : 'bi-check-circle-fill'"></i> {{ toast }}
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

const API = '/api/v1/master-jenis-verifikasi';
const CFG = { headers: { Accept: 'application/json' } };
const KOSONG = () => ({ nama: '', kode: '', deskripsi: '', ikon: 'bi-patch-check', warna: '#6366f1', sensitif: false, retensiHari: null, urutan: 99 });

export default {
    components: { Head, AdminModal, ConfirmModal },
    // Modal di halaman ini selamat dari refresh — lihat @utils/ingatModal.
    mixins: [ingatModal('admin/master-jenis-verifikasi/masterJenisVerifikasi')],
    data() {
        return {
            daftar: [],
            loading: false,
            cari: '',

            show: false,
            editing: null,
            editingSistem: false,
            form: KOSONG(),
            saving: false,

            delShow: false,
            delTarget: null,
            deleting: false,

            toast: '',
            toastErr: false,
            tm: null,
        };
    },
    computed: {
        tersaring() {
            const q = this.cari.trim().toLowerCase();
            if (!q) return this.daftar;

            return this.daftar.filter((r) => `${r.nama} ${r.kode} ${r.deskripsi || ''}`.toLowerCase().includes(q));
        },
        jumlahAktif() {
            return this.daftar.filter((r) => r.aktif).length;
        },
        jumlahSensitif() {
            return this.daftar.filter((r) => r.sensitif).length;
        },
        jumlahRetensi() {
            return this.daftar.filter((r) => r.retensiHari).length;
        },
        sah() {
            return !!(this.form.nama || '').trim() && /^[A-Z0-9_]+$/.test(this.form.kode || '');
        },
    },
    mounted() {
        this.muat();
    },
    methods: {
        notice(pesan, err = false) {
            this.toast = pesan;
            this.toastErr = err;
            if (this.tm) clearTimeout(this.tm);
            this.tm = setTimeout(() => (this.toast = ''), 3200);
        },
        async muat() {
            this.loading = true;
            try {
                const res = await axios.get(API, CFG);
                this.daftar = res.data.result || [];
            } catch (e) {
                this.notice(e.response?.data?.message || 'Gagal memuat data.', true);
            } finally {
                this.loading = false;
            }
        },
        bukaTambah() {
            this.editing = null;
            this.editingSistem = false;
            this.form = KOSONG();
            this.show = true;
        },
        bukaSunting(r) {
            this.editing = r.id;
            this.editingSistem = r.sistem;
            this.form = {
                nama: r.nama,
                kode: r.kode,
                deskripsi: r.deskripsi || '',
                ikon: r.ikon || 'bi-patch-check',
                warna: r.warna || '#6366f1',
                sensitif: r.sensitif,
                retensiHari: r.retensiHari,
                urutan: r.urutan,
            };
            this.show = true;
        },
        async simpan() {
            if (!this.sah || this.saving) return;
            this.saving = true;
            try {
                const res = this.editing
                    ? await axios.put(`${API}/${this.editing}`, this.form, CFG)
                    : await axios.post(API, this.form, CFG);
                this.notice(res.data?.message || 'Tersimpan.');
                this.show = false;
                await this.muat();
            } catch (e) {
                this.notice(e.response?.data?.message || 'Gagal menyimpan.', true);
            } finally {
                this.saving = false;
            }
        },
        async toggle(r) {
            try {
                const res = await axios.patch(`${API}/${r.id}/toggle`, {}, CFG);
                r.aktif = !!res.data?.result?.aktif;
                this.notice(res.data?.message || 'Status diubah.');
            } catch (e) {
                this.notice(e.response?.data?.message || 'Gagal mengubah status.', true);
            }
        },
        askHapus(r) {
            if (r.sistem) {
                this.notice('Komponen bawaan sistem tidak bisa dihapus — nonaktifkan saja.', true);

                return;
            }
            this.delTarget = r;
            this.delShow = true;
        },
        async hapus() {
            if (!this.delTarget || this.deleting) return;
            this.deleting = true;
            try {
                const res = await axios.delete(`${API}/${this.delTarget.id}`, CFG);
                this.notice(res.data?.message || 'Dihapus.');
                this.delShow = false;
                this.delTarget = null;
                await this.muat();
            } catch (e) {
                this.notice(e.response?.data?.message || 'Gagal menghapus.', true);
            } finally {
                this.deleting = false;
            }
        },
    },
};
</script>

<style scoped>
.mjv-beku { display: flex; gap: 6px; margin-top: 6px; font-size: 11.5px; line-height: 1.5; color: #64748b; }
.mjv-beku i { flex: none; margin-top: 2px; color: #0891b2; }
.mjv-note { margin-bottom: 12px; }

:deep(.wca-search2) { flex: 1 1 320px; width: auto; max-width: 480px; height: 38px; }
@media (max-width: 640px) { :deep(.wca-search2) { flex-basis: 100%; max-width: none; } }

.mjv-nama { display: flex; gap: 10px; align-items: flex-start; }
.mjv-ico { flex: none; width: 30px; height: 30px; display: grid; place-items: center; border-radius: 9px; background: #f1f5f9; font-size: 14px; }
.mjv-nama b { font-size: 12.5px; color: #1e293b; }
.mjv-nama small { display: block; margin-top: 2px; font-size: 11px; line-height: 1.45; color: #94a3b8; }
.mjv-sensitif { display: inline-flex; align-items: center; gap: 3px; margin-left: 6px; padding: 1px 5px; border-radius: 5px; background: #fef2f2; color: #b91c1c; font-size: 9px; font-weight: 800; vertical-align: middle; }
.mjv-kode { padding: 2px 6px; border-radius: 5px; background: #f1f5f9; font-size: 11px; color: #475569; }
.mjv-samar { color: #cbd5e1; }

.mjv-status { display: flex; align-items: center; gap: 9px; }
.mjv-status__lbl { font-size: 11.5px; font-weight: 700; }
.mjv-status__lbl.is-on { color: #047857; }
.mjv-status__lbl.is-off { color: #94a3b8; }

/* Sakelar bergaya EVO — sama dengan Master SLA MPP, bukan biru bawaan Element Plus. */
.mjv-sw { appearance: none; position: relative; flex: none; cursor: pointer; width: 38px; height: 21px; padding: 0; border: none; border-radius: 999px; background: #cbd5e1; transition: background .18s ease; }
.mjv-sw.is-on { background: linear-gradient(135deg, #8b5cf6, #6366f1); }
.mjv-sw:focus-visible { outline: 2px solid #6366f1; outline-offset: 2px; }
.mjv-sw__knob { position: absolute; top: 3px; left: 3px; width: 15px; height: 15px; border-radius: 50%; background: #fff; box-shadow: 0 1px 3px rgba(15, 23, 42, .25); transition: transform .18s cubic-bezier(.34, 1.4, .64, 1); }
.mjv-sw.is-on .mjv-sw__knob { transform: translateX(17px); }

.mjv-aksi { display: flex; gap: 6px; }
.mjv-req { color: #ef4444; font-weight: 800; }
.mjv-hint { display: block; margin-top: 4px; font-size: 10.5px; line-height: 1.5; color: #94a3b8; }

.mjv-kotak { display: flex; gap: 10px; align-items: flex-start; padding: 10px 12px; border: 1px solid #e2e8f0; border-radius: 10px; cursor: pointer; }
.mjv-kotak.is-on { border-color: #fecaca; background: #fef7f7; }
.mjv-kotak b { display: block; font-size: 12.5px; color: #1e293b; }
.mjv-kotak small { display: block; margin-top: 2px; font-size: 11px; line-height: 1.55; color: #64748b; }

.mjv-retensi { display: flex; align-items: center; gap: 9px; flex-wrap: wrap; }
.mjv-retensi > span { font-size: 12px; color: #64748b; }
.mjv-kosongkan { padding: 4px 9px; border-radius: 7px; border: 1px solid #e2e8f0; background: #fff; color: #64748b; font-size: 11px; font-weight: 700; cursor: pointer; }
.mjv-kosongkan:hover { border-color: #6366f1; color: #4338ca; }
</style>
