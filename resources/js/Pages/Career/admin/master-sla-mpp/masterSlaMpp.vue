<!-- WEB CAREER — Master SLA MPP: "level ini harus tuntas dalam berapa hari kerja".
     Data via web route + ResponseHelper (axios); komponen & kelas mengikuti
     master lain (AdminModal, el-switch, wca-iconbtn, wca-badge). -->
<template>
    <Head title="Master SLA MPP" />
    <div class="wca">
        <div class="wca-phead">
            <div>
                <h1>Master SLA MPP</h1>
                <p>
                    Batas waktu penyelesaian MPP per <b>Level HRIS</b>, dihitung dalam <b>hari kerja</b> —
                    Senin&ndash;Jumat, di luar hari libur nasional. Level yang belum diatur
                    <b>tidak bisa dipakai membuat MPP baru</b> — tanggal periode targetnya dihitung
                    dari aturan di halaman ini.
                </p>
                <!-- Cakupannya disebut di muka: itu jawaban atas "kenapa tombol Aturan
                     Baru tidak menawarkan level apa pun". -->
                <p v-if="!loading" class="sla-cakupan">
                    <i class="bi" :class="belumDiatur.length ? 'bi-exclamation-circle' : 'bi-check-circle-fill'"></i>
                    <span v-if="belumDiatur.length"
                        ><b>{{ aturan.length }} dari {{ aturan.length + belumDiatur.length }}</b> level HRIS sudah
                        diatur.</span
                    >
                    <span v-else
                        >Seluruh <b>{{ aturan.length }} level HRIS</b> sudah punya aturan — tidak ada lagi yang perlu
                        ditambah.</span
                    >
                </p>
            </div>
            <div class="wca-phead__actions">
                <!-- TIDAK PERNAH DIMATIKAN.
                
                     Versi sebelumnya menonaktifkan tombol ini begitu semua level HRIS
                     punya aturan — dan tombol pudar yang tak bisa ditekan terbaca
                     sebagai "tombolnya hilang", bukan sebagai "tidak ada yang perlu
                     ditambah". Sekarang ia selalu bisa ditekan, dan yang menjelaskan
                     keadaannya adalah isi modalnya sendiri. -->
                <button
                    class="wca-btn wca-btn--primary"
                    title="Tetapkan SLA untuk satu atau beberapa level sekaligus"
                    @click="bukaBaru()"
                >
                    <i class="bi bi-plus-lg"></i> Aturan Baru
                </button>
            </div>
        </div>

        <!-- Kalimat ini yang paling perlu dibaca sebelum seseorang menyunting
             angkanya. Tanpa itu, orang wajar mengira mengubah 45 → 25 akan
             menggeser tenggat MPP yang sedang berjalan. -->
        <div class="wca-note wca-note--info">
            <i class="bi bi-shield-check"></i>
            <span>
                Mengubah angka di sini <b>tidak mengubah MPP yang sudah ada</b>. Setiap MPP membekukan angka yang
                berlaku pada hari ia dibuat, jadi laporan tahun lalu tetap dinilai dengan aturan tahun lalu. Yang
                berubah hanya MPP <b>baru</b>.
            </span>
        </div>

        <div class="wca-stats">
            <div class="wca-stat">
                <div class="wca-stat__top">
                    <span class="wca-stat__ico"><i class="bi bi-stopwatch"></i></span>
                </div>
                <div class="wca-stat__num">{{ aturan.length }}</div>
                <div class="wca-stat__label">Level diatur</div>
            </div>
            <div class="wca-stat">
                <div class="wca-stat__top">
                    <span class="wca-stat__ico" style="background: rgba(16, 185, 129, 0.12); color: #059669"
                        ><i class="bi bi-check-circle"></i
                    ></span>
                </div>
                <div class="wca-stat__num">{{ jumlahAktif }}</div>
                <div class="wca-stat__label">Aktif (mengunci)</div>
            </div>
            <div class="wca-stat">
                <div class="wca-stat__top">
                    <span class="wca-stat__ico" style="background: rgba(148, 163, 184, 0.16); color: #475569"
                        ><i class="bi bi-eye-slash"></i
                    ></span>
                </div>
                <div class="wca-stat__num">{{ aturan.length - jumlahAktif }}</div>
                <div class="wca-stat__label">Nonaktif (MPP tertahan)</div>
            </div>
            <div class="wca-stat">
                <div class="wca-stat__top">
                    <span class="wca-stat__ico" style="background: rgba(245, 158, 11, 0.14); color: #b45309"
                        ><i class="bi bi-exclamation-triangle"></i
                    ></span>
                </div>
                <div class="wca-stat__num">{{ belumDiatur.length }}</div>
                <div class="wca-stat__label">Belum diatur</div>
            </div>
        </div>

        <!-- ══ LEVEL YANG BELUM PUNYA TENGGAT ══
             Ditaruh DI ATAS tabel: yang ingin diketahui orang saat membuka
             halaman ini bukan "aturan apa yang sudah saya buat", melainkan
             "level mana yang MPP-nya masih tertahan". Chip-nya jalan pintas — tombol
             "Aturan Baru" di kepala tetap melayani semuanya sekaligus. -->
        <div v-if="belumDiatur.length" class="wca-note wca-note--warn sla-bebas">
            <i class="bi bi-unlock"></i>
            <span>
                <b>{{ belumDiatur.length }} level belum punya batas SLA</b> — MPP baru di level ini belum bisa
                dibuat sampai aturannya diisi.
                <span class="sla-bebas__chips">
                    <button
                        v-for="b in belumDiatur"
                        :key="b.idLevel"
                        type="button"
                        class="sla-chip"
                        title="Atur SLA untuk level ini"
                        @click="bukaBaru(b)"
                    >
                        <i class="bi bi-plus-lg"></i> {{ b.level }}
                    </button>
                </span>
            </span>
        </div>

        <div class="wca-card">
            <div class="wca-toolbar">
                <div class="wca-search2">
                    <i class="bi bi-search"></i>
                    <input
                        v-model="q"
                        type="text"
                        placeholder="Cari level / keterangan…"
                        aria-label="Cari aturan SLA"
                        @input="cariTertunda"
                        @keyup.enter="load"
                        @keyup.esc="bersihkanCari"
                    />
                    <button v-if="q" type="button" class="wca-search2__x" title="Bersihkan" @click="bersihkanCari">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
            </div>

            <div v-if="loading" class="wca-empty"><span class="wca-spin"></span> Memuat…</div>

            <div v-else-if="!aturan.length" class="wca-empty">
                <i class="bi bi-stopwatch"></i>
                <b>Belum ada aturan SLA</b>
                <span>Tekan <b>Aturan Baru</b> untuk menetapkan batas hari kerja per level.</span>
            </div>

            <div v-else class="wca-tablewrap">
                <table class="wca-table">
                    <thead>
                        <tr>
                            <th style="width: 54px">#</th>
                            <th>Level HRIS</th>
                            <th style="width: 160px">Batas</th>
                            <th>Keterangan</th>
                            <th style="width: 190px" class="sla-hide-sm">Terakhir diubah</th>
                            <th style="width: 150px">Status</th>
                            <th style="width: 90px"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(r, i) in aturan" :key="r.id" :class="{ 'is-mati': !r.aktif }">
                            <td>
                                <span class="wca-badge wca-b--slate">{{ i + 1 }}</span>
                            </td>
                            <td>
                                <div class="sla-lv">
                                    <span class="sla-lv__ico"><i class="bi bi-diagram-3"></i></span>
                                    <div style="min-width: 0">
                                        <b>{{ r.level }}</b>
                                        <small>Hierarki {{ r.hierarki || '—' }}</small>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="wca-badge wca-b--indigo sla-hari">{{ r.hariKerja }} hari kerja</span>
                            </td>
                            <td class="sla-ket">{{ r.keterangan || '—' }}</td>
                            <td class="sla-hide-sm">
                                <div class="sla-audit">
                                    <span>{{ r.diubahPada ? fmt(r.diubahPada) : '—' }}</span>
                                    <small>{{ r.diubahOleh || '—' }}</small>
                                </div>
                            </td>
                            <td>
                                <!-- SAKELAR SENDIRI, BUKAN el-switch.
                                
                                     Element Plus memakai birunya sendiri (#409eff) yang tidak ada di
                                     palet EVO, jadi satu-satunya benda biru di halaman ini justru
                                     penanda status — hal yang paling sering dibaca sekilas. Sakelar di
                                     bawah memakai indigo tema, dan sekalian jadi <button> beneran
                                     dengan role="switch" supaya keyboard & pembaca layar mengenalinya. -->
                                <div class="sla-status">
                                    <button
                                        type="button"
                                        class="sla-sw"
                                        :class="{ 'is-on': r.aktif }"
                                        role="switch"
                                        :aria-checked="r.aktif ? 'true' : 'false'"
                                        :title="
                                            r.aktif
                                                ? 'Nonaktifkan — MPP baru di level ini ikut tertahan'
                                                : 'Aktifkan kembali — MPP baru di level ini bisa dibuat lagi'
                                        "
                                        @click="toggle(r)"
                                    >
                                        <span class="sla-sw__knob"></span>
                                    </button>
                                    <span class="sla-status__lbl" :class="r.aktif ? 'is-on' : 'is-off'">
                                        {{ r.aktif ? 'Aktif' : 'Nonaktif' }}
                                    </span>
                                </div>
                            </td>
                            <td>
                                <div class="sla-actions">
                                    <button class="wca-iconbtn" title="Ubah batas hari kerja" @click="bukaSunting(r)">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <!-- HAPUS berbeda dari NONAKTIF: yang ini membuang barisnya sama
                                         sekali, untuk level yang memang tidak pernah direkrut lewat MPP. -->
                                    <button
                                        class="wca-iconbtn wca-iconbtn--danger"
                                        title="Hapus aturan — MPP baru di level ini ikut tertahan"
                                        @click="askHapus(r)"
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

        <!-- ══ BORANG ══
             Satu modal, dua wajah:
               BARU     — pilih BANYAK level sekaligus, tiap level dapat kartunya
                          sendiri berisi angka & keterangannya sendiri;
               SUNTING  — satu level, dan levelnya dikunci.
             Menetapkan SLA memang pekerjaan borongan: kebijakan datang sebagai
             tabel ("staf 30, officer 45, manajer 90"), bukan satu baris per hari. -->
        <AdminModal
            :busy="saving"
            :show="show"
            icon="bi-stopwatch"
            :title="editId ? 'Ubah Aturan SLA' : 'Aturan SLA Baru'"
            :subtitle="editId ? 'Batas hari kerja untuk satu level' : 'Pilih satu atau beberapa level sekaligus'"
            :save-label="editId ? 'Perbarui' : simpanLabel"
            foot-note="Perubahan hanya berlaku untuk MPP baru — yang sudah ada membawa angkanya sendiri."
            size="lg"
            @close="show = false"
            @save="simpan"
        >
            <div class="wca-fsection">
                <div class="wca-fsection__label"><i class="bi bi-diagram-3"></i> Level HRIS</div>

                <!-- BARU: multi-pilih -->
                <template v-if="!editId">
                    <el-select
                        v-model="pilihLevel"
                        multiple
                        filterable
                        collapse-tags
                        collapse-tags-tooltip
                        placeholder="Pilih satu atau beberapa level…"
                        style="width: 100%"
                        @change="sinkronKartu"
                    >
                        <el-option v-for="o in belumDiatur" :key="o.idLevel" :label="o.level" :value="o.idLevel" />
                    </el-select>
                    <p class="wca-hint" style="margin-top: 6px">
                        Level yang sudah punya aturan tidak muncul di sini — sunting yang sudah ada, jangan dibuat dua.
                    </p>

                    <div class="sla-pintas">
                        <button type="button" class="sla-pintas__btn" @click="pilihSemua">
                            <i class="bi bi-check2-all"></i> Pilih semua ({{ belumDiatur.length }})
                        </button>
                        <button
                            type="button"
                            class="sla-pintas__btn"
                            :disabled="!pilihLevel.length"
                            :onClick="!pilihLevel.length ? null : kosongkanPilihan"
                        >
                            <i class="bi bi-x-lg"></i> Kosongkan
                        </button>
                    </div>
                </template>

                <!-- SUNTING: dikunci -->
                <template v-else>
                    <el-input :model-value="kartu[0] ? kartu[0].level : ''" disabled />
                    <p class="wca-hint" style="margin-top: 6px">
                        Level tidak bisa dipindah — menonaktifkan aturan ini lalu membuat aturan baru meninggalkan jejak
                        keduanya; memindahkannya tidak.
                    </p>
                </template>
            </div>

            <div v-if="kartu.length" class="wca-fsection">
                <div class="wca-fsection__label">
                    <i class="bi bi-stopwatch"></i> Batas hari kerja
                    <span v-if="kartu.length > 1" class="sla-fsec__n">{{ kartu.length }} level</span>
                </div>

                <!-- MASSAL: satu angka disalin ke semua kartu. Kebijakan kerap
                     seragam untuk serumpun level, dan mengetik angka yang sama
                     sepuluh kali adalah pekerjaan yang tidak menambah apa pun. -->
                <div v-if="kartu.length > 1" class="sla-massal">
                    <span><i class="bi bi-magic"></i> Samakan semua:</span>
                    <el-input-number v-model="massalHari" :min="1" :max="400" controls-position="right" size="small" />
                    <button type="button" class="sla-pintas__btn" @click="sebarHari">
                        <i class="bi bi-arrow-down-up"></i> Terapkan ke {{ kartu.length }} level
                    </button>
                </div>

                <div class="sla-kartu">
                    <div v-for="k in kartu" :key="k.idLevel" class="sla-k">
                        <div class="sla-k__head">
                            <span class="sla-k__ico"><i class="bi bi-diagram-3"></i></span>
                            <b>{{ k.level }}</b>
                            <button
                                v-if="!editId && kartu.length > 1"
                                type="button"
                                class="sla-k__x"
                                title="Keluarkan level ini dari daftar"
                                @click="buangKartu(k)"
                            >
                                <i class="bi bi-x-lg"></i>
                            </button>
                        </div>

                        <div class="sla-k__body">
                            <div>
                                <label class="wca-field-lbl">Batas <span class="mmp-req">*</span></label>
                                <el-input-number
                                    v-model="k.hariKerja"
                                    :min="1"
                                    :max="400"
                                    controls-position="right"
                                    style="width: 100%"
                                    @change="() => hitungContoh(k)"
                                />
                                <!-- CONTOH NYATA, bukan sekadar angka: "45 hari kerja"
                                     tidak berarti apa-apa sampai orang melihat tanggalnya
                                     jatuh di mana. Dihitung SERVER, sumber yang sama
                                     dengan yang mengunci kalender MPP. -->
                            </div>
                            <div>
                                <label class="wca-field-lbl">Keterangan</label>
                                <el-input
                                    v-model="k.keterangan"
                                    type="textarea"
                                    :rows="2"
                                    maxlength="400"
                                    show-word-limit
                                    placeholder="mis. Sesuai SK Direksi No. … tahun 2026"
                                />
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div v-else class="wca-empty" style="padding: 24px 8px">
                <i class="bi" :class="belumDiatur.length ? 'bi-arrow-up' : 'bi-check-circle'"></i>
                <b v-if="!belumDiatur.length">Semua level HRIS sudah punya aturan</b>
                <span v-if="belumDiatur.length">Pilih dulu levelnya di atas.</span>
                <span v-else>
                    Daftar level datang dari HRIS, jadi tidak ada level baru yang bisa dibuat dari sini. Yang bisa kamu
                    lakukan:
                    <b>sunting</b> angka aturan yang ada, atau <b>nonaktifkan</b> salah satunya — level yang nonaktif
                    tidak bisa dipakai membuat MPP baru.
                </span>
            </div>
        </AdminModal>

        <ConfirmModal
            :show="delShow"
            :busy="deleting"
            title="Hapus Aturan SLA"
            confirm-label="Ya, Hapus Aturan"
            note="MPP yang sudah ada tidak berubah — angka SLA-nya beku di barisnya sendiri. Yang berubah: MPP BARU di level ini tidak bisa dibuat lagi sampai aturannya diisi ulang."
            @cancel="delShow = false"
            @confirm="hapus"
        >
            Hapus aturan <strong>{{ delTarget?.level }}</strong> ({{ delTarget?.hariKerja }} hari kerja)?
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

const API = '/api/v1/master-sla-mpp';
const CFG = { headers: { Accept: 'application/json' } };

export default {
    components: { Head, AdminModal, ConfirmModal },
    // Modal di halaman ini selamat dari refresh — lihat @utils/ingatModal.
    mixins: [ingatModal('admin/master-sla-mpp/masterSlaMpp')],
    data() {
        return {
            aturan: [],
            belumDiatur: [],
            loading: false,
            q: '',
            dtm: null,

            show: false,
            editId: null,
            saving: false,
            pilihLevel: [],
            // Satu kartu per level terpilih — masing-masing membawa angka dan
            // keterangannya sendiri.
            kartu: [],
            massalHari: 30,

            delShow: false,
            delTarget: null,
            deleting: false,

            toast: '',
            toastErr: false,
            tm: null,
            ctm: null,
        };
    },
    computed: {
        jumlahAktif() {
            return this.aturan.filter((r) => r.aktif).length;
        },
        simpanLabel() {
            return this.kartu.length > 1 ? `Simpan ${this.kartu.length} Aturan` : 'Simpan Aturan';
        },
    },
    mounted() {
        this.load();
    },
    methods: {
        async load() {
            this.loading = true;
            try {
                const res = await axios.get(API, { ...CFG, params: { q: this.q.trim() } });
                const r = res.data.result || {};
                this.aturan = r.data || [];
                this.belumDiatur = r.belumDiatur || [];
            } catch (e) {
                this.notice(e.response?.data?.message || 'Gagal memuat data.', true);
            } finally {
                this.loading = false;
            }
        },
        cariTertunda() {
            if (this.dtm) clearTimeout(this.dtm);
            this.dtm = setTimeout(() => this.load(), 400);
        },
        bersihkanCari() {
            this.q = '';
            this.load();
        },

        /* ── Borang ── */
        /**
         * Buka borang aturan baru.
         *
         * TIDAK menolak walau tak ada level bebas: yang menjelaskan keadaan itu
         * adalah isi modalnya, bukan sepotong toast yang lewat lalu hilang.
         */
        bukaBaru(level = null) {
            this.editId = null;
            this.massalHari = 30;
            this.pilihLevel = level ? [level.idLevel] : [];
            this.kartu = [];
            this.sinkronKartu();
            this.show = true;
        },
        bukaSunting(r) {
            this.editId = r.id;
            this.pilihLevel = [r.idLevel];
            this.kartu = [
                {
                    idLevel: r.idLevel,
                    level: r.level,
                    hariKerja: r.hariKerja,
                    keterangan: r.keterangan || '',
                    contoh: null,
                },
            ];
            this.show = true;
            this.hitungContoh(this.kartu[0]);
        },
        /**
         * Samakan daftar kartu dengan level yang terpilih.
         *
         * Kartu yang sudah ada DIPERTAHANKAN apa adanya — angka dan keterangan
         * yang sudah diketik tidak boleh hilang hanya karena satu level lain
         * ditambahkan ke pilihan.
         */
        sinkronKartu() {
            const lama = new Map(this.kartu.map((k) => [k.idLevel, k]));

            this.kartu = this.pilihLevel.map((id) => {
                if (lama.has(id)) return lama.get(id);
                const lv = this.belumDiatur.find((b) => b.idLevel === id);

                return {
                    idLevel: id,
                    level: lv?.level || `Level #${id}`,
                    hariKerja: this.massalHari,
                    keterangan: '',
                    contoh: null,
                };
            });

            this.kartu.forEach((k) => this.hitungContoh(k));
        },
        pilihSemua() {
            this.pilihLevel = this.belumDiatur.map((b) => b.idLevel);
            this.sinkronKartu();
        },
        kosongkanPilihan() {
            this.pilihLevel = [];
            this.kartu = [];
        },
        buangKartu(k) {
            this.pilihLevel = this.pilihLevel.filter((id) => id !== k.idLevel);
            this.sinkronKartu();
        },
        sebarHari() {
            this.kartu.forEach((k) => {
                k.hariKerja = this.massalHari;
                this.hitungContoh(k);
            });
            this.notice(`Batas ${this.massalHari} hari kerja disalin ke ${this.kartu.length} level.`);
        },
        /**
         * Contoh tenggat — DIHITUNG SERVER, bukan di layar.
         *
         * Hitungan hari kerja (akhir pekan + libur nasional) hidup di satu tempat
         * saja; menyalinnya ke sini berarti cepat atau lambat contoh di modal
         * berbeda dari tenggat yang benar-benar ditegakkan.
         */
        hitungContoh(k) {
            if (!k || !k.hariKerja) return;
            if (this.ctm) clearTimeout(this.ctm);

            this.ctm = setTimeout(async () => {
                try {
                    const res = await axios.get(`${API}/hitung`, {
                        ...CFG,
                        params: { idLevel: k.idLevel, hari: k.hariKerja },
                    });
                    k.contoh = res.data.result?.batas || null;
                } catch (e) {
                    k.contoh = null;
                }
            }, 350);
        },
        async simpan() {
            if (this.saving) return;
            if (!this.kartu.length) return this.notice('Pilih dulu levelnya.', true);

            const cacat = this.kartu.find((k) => !k.hariKerja || k.hariKerja < 1 || k.hariKerja > 400);
            if (cacat) return this.notice(`Batas hari kerja untuk ${cacat.level} belum benar.`, true);

            this.saving = true;
            try {
                const isi = (k) => ({ idLevel: k.idLevel, hariKerja: k.hariKerja, keterangan: k.keterangan || null });

                const res = this.editId
                    ? await axios.put(`${API}/${this.editId}`, isi(this.kartu[0]), CFG)
                    : // BORONGAN dalam SATU permintaan: sepuluh permintaan berturut-turut
                      // bisa separuh berhasil, dan separuh-berhasil pada aturan kebijakan
                      // adalah keadaan yang paling sulit dirapikan kembali.
                      await axios.post(API, { items: this.kartu.map(isi) }, CFG);

                this.notice(res.data?.message || 'Tersimpan.');
                this.show = false;
                await this.load();
            } catch (e) {
                const v = e.response?.data?.errors;
                const pesan = v ? Object.values(v).flat()[0] : e.response?.data?.message;
                this.notice(pesan || 'Gagal menyimpan.', true);
            } finally {
                this.saving = false;
            }
        },
        askHapus(r) {
            this.delTarget = r;
            this.delShow = true;
        },
        async hapus() {
            if (!this.delTarget || this.deleting) return;
            this.deleting = true;
            try {
                const res = await axios.delete(`${API}/${this.delTarget.id}`, CFG);
                this.notice(res.data?.message || 'Aturan dihapus.');
                this.delShow = false;
                this.delTarget = null;
                await this.load();
            } catch (e) {
                this.notice(e.response?.data?.message || 'Gagal menghapus.', true);
            } finally {
                this.deleting = false;
            }
        },
        async toggle(r) {
            try {
                const res = await axios.patch(`${API}/${r.id}/toggle`, {}, CFG);
                this.notice(res.data?.message || 'Status diperbarui.');
                await this.load();
            } catch (e) {
                this.notice(e.response?.data?.message || 'Gagal mengubah status.', true);
                await this.load();
            }
        },

        /* ── Bantu ── */
        fmt(v) {
            if (!v) return '—';
            const d = new Date(String(v).replace(' ', 'T'));

            return Number.isNaN(d.getTime())
                ? String(v)
                : d.toLocaleString('id-ID', { dateStyle: 'medium', timeStyle: 'short' });
        },
        fmtTgl(v) {
            if (!v) return '—';
            const d = new Date(`${v}T00:00:00`);

            return Number.isNaN(d.getTime()) ? String(v) : d.toLocaleDateString('id-ID', { dateStyle: 'full' });
        },
        notice(pesan, err = false) {
            this.toast = pesan;
            this.toastErr = err;
            if (this.tm) clearTimeout(this.tm);
            this.tm = setTimeout(() => {
                this.toast = '';
            }, 3200);
        },
    },
};
</script>

<style scoped>
/* ── Baris tabel ── */
.sla-lv {
    display: flex;
    align-items: center;
    gap: 10px;
}
.sla-lv__ico {
    flex: none;
    width: 32px;
    height: 32px;
    border-radius: 10px;
    display: grid;
    place-items: center;
    background: #eef2ff;
    color: #4f46e5;
    font-size: 14px;
}
.sla-lv b {
    display: block;
    font-size: 13px;
    font-weight: 800;
    color: #0f172a;
}
.sla-lv small {
    display: block;
    font-size: 11px;
    color: #94a3b8;
}
.sla-hari {
    font-weight: 800;
}
.sla-ket {
    font-size: 12px;
    color: #64748b;
    max-width: 340px;
}
.sla-audit span {
    display: block;
    font-size: 12px;
    color: #475569;
}
.sla-audit small {
    display: block;
    font-size: 11px;
    color: #94a3b8;
}
tr.is-mati {
    opacity: 0.62;
}

.sla-status {
    display: flex;
    align-items: center;
    gap: 9px;
}

/* Sakelar bergaya EVO — indigo saat menyala, abu saat mati. */
.sla-sw {
    appearance: none;
    position: relative;
    flex: none;
    cursor: pointer;
    width: 38px;
    height: 21px;
    padding: 0;
    border: none;
    border-radius: 999px;
    background: #cbd5e1;
    transition: background 0.18s ease;
}
.sla-sw.is-on {
    background: linear-gradient(135deg, #8b5cf6, #6366f1);
}
.sla-sw:focus-visible {
    outline: 2px solid #6366f1;
    outline-offset: 2px;
}
.sla-sw__knob {
    position: absolute;
    top: 3px;
    left: 3px;
    width: 15px;
    height: 15px;
    border-radius: 50%;
    background: #fff;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.25);
    transition: transform 0.18s cubic-bezier(0.34, 1.4, 0.64, 1);
}
.sla-sw.is-on .sla-sw__knob {
    transform: translateX(17px);
}

/* KOTAK CARI MELAR.
   Bawaan .wca-search2 dikunci 170px karena di master lain ia berdampingan
   dengan beberapa penyaring. Di sini ia satu-satunya isi toolbar, dan 170px
   membuat placeholder-nya sendiri terpotong ("Cari level / keteranga"). */
:deep(.wca-search2) {
    flex: 1 1 320px;
    width: auto;
    max-width: 520px;
    height: 38px;
}
@media (max-width: 640px) {
    :deep(.wca-search2) {
        flex-basis: 100%;
        max-width: none;
    }
}
.sla-status__lbl {
    font-size: 11.5px;
    font-weight: 800;
}
.sla-status__lbl.is-on {
    color: #059669;
}
.sla-status__lbl.is-off {
    color: #94a3b8;
}
.sla-actions {
    display: flex;
    gap: 6px;
    justify-content: flex-end;
}

@media (max-width: 900px) {
    .sla-hide-sm {
        display: none;
    }
}

.sla-cakupan {
    display: flex;
    align-items: center;
    gap: 6px;
    margin: 6px 0 0;
    font-size: 11.5px;
    color: #64748b;
}
.sla-cakupan i {
    color: #059669;
}

/* ── Chip level yang belum diatur ── */
.sla-bebas__chips {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin-top: 8px;
}
.sla-chip {
    appearance: none;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 5px 10px;
    border-radius: 999px;
    cursor: pointer;
    border: 1px dashed #fbbf24;
    background: #fff;
    color: #b45309;
    font: inherit;
    font-size: 11.5px;
    font-weight: 700;
    transition: all 0.15s;
}
.sla-chip:hover {
    background: #fffbeb;
    border-style: solid;
}

/* ── Borang ── */
.sla-fsec__n {
    margin-left: 8px;
    padding: 2px 8px;
    border-radius: 999px;
    background: rgba(99, 102, 241, 0.12);
    color: #4338ca;
    font-size: 10.5px;
    font-weight: 800;
}
.sla-pintas {
    display: flex;
    gap: 6px;
    margin-top: 8px;
}
.sla-pintas__btn {
    appearance: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 11px;
    border-radius: 9px;
    cursor: pointer;
    border: 1px solid #e2e8f0;
    background: #fff;
    color: #475569;
    font: inherit;
    font-size: 11.5px;
    font-weight: 700;
    transition: all 0.15s;
}
.sla-pintas__btn:hover:not(:disabled) {
    border-color: #c7d2fe;
    color: #4f46e5;
    background: #f8faff;
}
.sla-pintas__btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.sla-massal {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 9px;
    padding: 10px 12px;
    margin-bottom: 12px;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    background: #f8fafc;
    font-size: 12px;
    font-weight: 700;
    color: #475569;
}
.sla-massal i {
    color: #6366f1;
}

.sla-kartu {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 12px;
}
.sla-k {
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    background: #fff;
    overflow: hidden;
}
.sla-k__head {
    display: flex;
    align-items: center;
    gap: 9px;
    padding: 10px 12px;
    background: #f8fafc;
    border-bottom: 1px solid #eef0f7;
}
.sla-k__ico {
    flex: none;
    width: 26px;
    height: 26px;
    border-radius: 8px;
    display: grid;
    place-items: center;
    background: #eef2ff;
    color: #4f46e5;
    font-size: 12px;
}
.sla-k__head b {
    flex: 1;
    min-width: 0;
    font-size: 12.5px;
    color: #0f172a;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.sla-k__x {
    appearance: none;
    flex: none;
    width: 24px;
    height: 24px;
    border-radius: 7px;
    border: 1px solid #fecaca;
    background: #fff;
    color: #dc2626;
    cursor: pointer;
    display: grid;
    place-items: center;
    font-size: 10px;
    transition: all 0.15s;
}
.sla-k__x:hover {
    background: #dc2626;
    color: #fff;
}
.sla-k__body {
    padding: 12px;
    display: grid;
    gap: 10px;
}
.sla-contoh {
    display: flex;
    gap: 6px;
    margin-top: 6px;
    color: #4338ca;
}
.sla-contoh i {
    flex: none;
}
</style>
