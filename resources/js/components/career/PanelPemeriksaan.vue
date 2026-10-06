<!-- WEB CAREER — PANEL PEMERIKSAAN (background check / reference check).
     Dipakai di jendela "Catat Hasil Aktivitas" pada Worklist Pelamar.

     KENAPA PANEL SENDIRI, BUKAN SATU KOTAK CATATAN:

     Pemeriksaan berlangsung berhari-hari dan satuannya bukan "aktivitas",
     melainkan KOMPONEN (ijazah, riwayat kerja, catatan hukum) atau NARASUMBER.
     Satu kotak catatan bebas memaksa tiga percakapan berbeda ditumpuk jadi satu
     paragraf: siapa yang bilang apa, siapa yang menolak, dan mana yang belum
     dijawab — semuanya hilang begitu ditutup.

     Setiap baris di sini disimpan sendiri-sendiri TANPA menutup aktivitasnya.
     Yang menutup tetap tombol "Simpan Hasil" di kaki jendela. -->
<template>
    <div class="pmk">
        <div class="pmk__head">
            <i class="bi" :class="referensi ? 'bi-person-vcard-fill' : 'bi-shield-check'"></i>
            <div>
                <b>{{ referensi ? 'Narasumber Referensi' : 'Komponen Pemeriksaan' }}</b>
                <small>{{ data.ringkas || (referensi ? 'Belum ada narasumber dicatat.' : 'Belum ada komponen dicatat.') }}</small>
            </div>
            <button v-if="referensi" type="button" class="pmk__add" :disabled="sibuk" :onClick="sibuk ? null : () => bukaRef(null)">
                <i class="bi bi-plus-lg"></i> Narasumber
            </button>
        </div>

        <!-- ══ PERSETUJUAN — dasar hukum pemeriksaannya ══

             Ditaruh paling atas karena ia syarat, bukan pelengkap: tanpa jejak
             persetujuan, aktivitas ini tidak bisa ditutup sama sekali (dijaga
             juga di server, bukan cuma di layar). -->
        <div class="pmk__setuju" :class="kelasSetuju">
            <i class="bi" :class="ikonSetuju"></i>
            <div>
                <b>{{ judulSetuju }}</b>
                <small v-if="data.persetujuan?.ada">
                    “{{ potong(data.persetujuan.pernyataan, 150) }}”
                    <template v-if="data.persetujuan.pada"> — {{ tglSingkat(data.persetujuan.pada) }}</template>
                </small>
                <small v-else-if="ditolak">
                    Alasan: “{{ data.persetujuan.pernyataan }}”
                    <template v-if="data.persetujuan.pada"> — {{ tglSingkat(data.persetujuan.pada) }}</template>
                    · dicatat {{ data.persetujuan.oleh }}
                </small>
                <small v-else>
                    Tanyakan kesediaannya diperiksa — biasanya lewat telepon saat tahap ini dimulai —
                    lalu tekan salah satu tombol di bawah.
                </small>

                <!-- KONTEKS, BUKAN IZIN.

                     Pernyataan di formulir lamaran ("izin memakai data pribadi untuk
                     proses rekrutmen") memang dasar yang sah untuk memproses lamaran,
                     tapi ia bukan jawaban atas "bersediakah Anda kami periksa latar
                     belakangnya?". Ditampilkan supaya petugas tahu dasar umumnya sudah
                     ada — bukan supaya pertanyaannya dilewati. -->
                <small v-if="data.persetujuan?.formulir?.ada" class="is-konteks">
                    <i class="bi bi-file-earmark-check"></i>
                    Formulir lamaran: kandidat sudah menyetujui penggunaan data pribadinya
                    ({{ tglSingkat(data.persetujuan.formulir.pada) }}).
                </small>

                <!-- Persetujuan UMUM tetap dasar yang sah, tapi bedanya harus
                     terlihat oleh yang memutuskan — bukan disamarkan jadi satu
                     centang hijau. -->

                <!-- DUA TOMBOL, SATU TEKAN.

                     Petugas sedang menelepon saat mengisi ini. Menyuruhnya mengetik
                     kalimat persetujuan sambil bicara hanya melahirkan kalimat
                     asal-asalan — atau langkah yang dilewati diam-diam. Kalimat
                     lengkapnya (siapa yang menanyakan, kapan) dirakit server.

                     PENOLAKAN beda: alasannya wajib, sebab "menolak diperiksa"
                     dan "nomornya salah sambung" menuntut tindakan yang berbeda. -->
                <div v-if="!keputusanAda || setujuBuka" class="pmk__pilih">
                    <button
                        type="button" class="pmk__ya" :disabled="sibuk"
                        :onClick="sibuk ? null : putusSetuju"
                    ><i class="bi bi-check-lg"></i> Setuju</button>
                    <button
                        type="button" class="pmk__tidak" :class="{ 'is-on': tolakBuka }" :disabled="sibuk"
                        :onClick="sibuk ? null : () => tolakBuka = !tolakBuka"
                    ><i class="bi bi-x-lg"></i> Tidak setuju</button>
                    <button
                        v-if="setujuBuka" type="button" class="pmk__batal" :disabled="sibuk"
                        :onClick="sibuk ? null : tutupPilih"
                    >Batal</button>
                </div>

                <div v-if="tolakBuka" class="pmk__setujuform">
                    <input
                        v-model="fSetuju" type="text" maxlength="200"
                        placeholder="Alasan kandidat menolak — mis. keberatan catatan hukumnya diperiksa"
                        @keyup.enter="putusTolak"
                    />
                    <button type="button" class="pmk__simpan" :disabled="sibuk || !fSetuju.trim()" :onClick="sibuk || !fSetuju.trim() ? null : putusTolak">
                        <i class="bi bi-check-lg"></i> Catat penolakan
                    </button>
                </div>
            </div>
            <button
                v-if="keputusanAda && !setujuBuka" type="button" class="pmk__add" :disabled="sibuk"
                :onClick="sibuk ? null : () => setujuBuka = true"
            >
                <i class="bi bi-pencil"></i> Ubah
            </button>
        </div>

        <!-- ══ HAK MENANGGAPI ══

             Wajib sebelum kesimpulan yang menggugurkan. Yang paling sering jadi
             sebab bukan pemalsuan, melainkan pihak ketiga yang tak membalas —
             dan itu hanya ketahuan kalau kandidatnya ditanya. -->
        <div class="pmk__tanggap" :class="{ 'is-ok': data.tanggapan?.dimintaPada }">
            <label class="pmk__tanggapchk">
                <input
                    type="checkbox" :checked="!!data.tanggapan?.dimintaPada" :disabled="sibuk"
                    @change="tandaiDiminta($event.target.checked)"
                />
                <span>
                    <b>Kandidat sudah diberi kesempatan menanggapi temuan</b>
                    <small v-if="data.tanggapan?.dimintaPada">Ditandai {{ tglSingkat(data.tanggapan.dimintaPada) }}</small>
                    <small v-else>Wajib sebelum kesimpulan yang menggugurkan bisa disimpan.</small>
                </span>
            </label>
            <textarea
                v-if="data.tanggapan?.dimintaPada"
                v-model="fTanggapan" rows="2" maxlength="1000"
                placeholder="Jawaban kandidat atas temuan — kosongkan bila belum menjawab."
                @blur="simpanTanggapan"
            ></textarea>
        </div>

        <!-- Kandidat menolak → tidak ada yang boleh diperiksa. Daftarnya
             disembunyikan, bukan sekadar diberi peringatan: kotak isian yang
             tetap terbuka adalah undangan untuk mengisinya. -->
        <p v-if="ditolak" class="pmk__buntu">
            <i class="bi bi-slash-circle"></i>
            Kandidat menolak pemeriksaan, jadi tidak ada temuan yang boleh dicatat.
            Tutup aktivitas ini dengan kesimpulan selain “bersih/direkomendasikan”.
        </p>

        <!-- ══ BACKGROUND CHECK — satu baris per komponen ══ -->
        <div v-else-if="!referensi" class="pmk__list">
            <div v-for="j in data.tersedia" :key="j.kode" class="pmk__row" :class="{ 'is-edit': edit === j.kode }">
                <div class="pmk__rowtop">
                    <span class="pmk__ico" :style="{ color: j.warna || '#6366f1' }"><i class="bi" :class="j.ikon || 'bi-check2-square'"></i></span>
                    <div class="pmk__nama">
                        <b>{{ j.nama }}</b>
                        <!-- Penanda data pribadi spesifik (UU PDP). Bukan hiasan:
                             ia yang mengingatkan bahwa isi baris ini tidak boleh
                             disalin ke grup obrolan atau lampiran surel biasa. -->
                        <span v-if="j.sensitif" class="pmk__sensitif" title="Data pribadi bersifat spesifik — akses terbatas">
                            <i class="bi bi-lock-fill"></i> SENSITIF
                        </span>
                        <small v-if="cari(j.kode)?.terkunci" class="is-kunci">
                            <i class="bi bi-lock-fill"></i>
                            Isi temuan disembunyikan — tekan untuk membuka. Pembukaannya dicatat.
                            <button type="button" class="pmk__buka" :disabled="sibuk" :onClick="sibuk ? null : bukaKunci">Buka</button>
                        </small>
                        <small v-else-if="cari(j.kode)?.ringkasan" class="pmk__isi">
                            <KontenAman :html="cari(j.kode).ringkasan" />
                        </small>
                        <small v-else-if="cari(j.kode)?.diredaksi" class="is-samar">
                            Isi temuan sudah diredaksi ({{ tglSingkat(cari(j.kode).diredaksi) }}) karena melewati batas retensi.
                        </small>
                        <small v-else-if="j.deskripsi" class="is-samar">{{ j.deskripsi }}</small>

                        <!-- Lampiran MILIK KOMPONEN INI. Surat dari kampus menempel
                             ke baris Pendidikan, bukan mengambang di aktivitas. -->
                        <span v-if="berkasKomponen(j.kode).length" class="pmk__lampir">
                            <a
                                v-for="b in berkasKomponen(j.kode)" :key="b.id"
                                :href="b.url" target="_blank" rel="noopener" :title="b.nama"
                            ><i class="bi" :class="b.isImage ? 'bi-file-earmark-image' : 'bi-file-earmark-pdf'"></i> {{ potong(b.nama, 24) }}</a>
                        </span>
                    </div>
                    <span class="pmk__pill" :class="kelasStatus(cari(j.kode)?.status)">
                        {{ labelStatus(cari(j.kode)?.status) }}
                        <template v-if="cari(j.kode)?.tingkatTemuan"> · {{ cari(j.kode).tingkatTemuan }}</template>
                    </span>
                    <button type="button" class="pmk__btn" :disabled="sibuk" :onClick="sibuk ? null : () => bukaKomponen(j)">
                        <i class="bi" :class="cari(j.kode) ? 'bi-pencil' : 'bi-plus-lg'"></i>
                    </button>
                    <button v-if="cari(j.kode)" type="button" class="pmk__btn is-danger" :disabled="sibuk" :onClick="sibuk ? null : () => hapusKomponen(j)">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>

                <div v-if="edit === j.kode" class="pmk__form">
                    <div class="pmk__opts">
                        <button
                            v-for="st in STATUS" :key="st.v"
                            type="button" class="pmk__opt" :class="{ 'is-on': f.status === st.v }"
                            @click="f.status = st.v"
                        >{{ st.t }}</button>
                    </div>

                    <div v-if="f.status === 'TEMUAN'" class="pmk__opts is-kecil">
                        <span class="pmk__lbl">Tingkat:</span>
                        <button
                            v-for="tk in TINGKAT" :key="tk"
                            type="button" class="pmk__opt is-kecil" :class="{ 'is-on': f.tingkatTemuan === tk }"
                            @click="f.tingkatTemuan = tk"
                        >{{ tk }}</button>
                    </div>

                    <div class="pmk__grid">
                        <label>
                            <span>Sumber / vendor</span>
                            <input v-model="f.sumber" type="text" placeholder="mis. Universitas Sriwijaya" />
                        </label>
                        <label>
                            <span>Tanggal selesai</span>
                            <input v-model="f.tanggalSelesai" type="date" />
                        </label>
                    </div>

                    <div class="pmk__area">
                        <span>
                            Ringkasan
                            <b v-if="f.status === 'TEMUAN'">wajib untuk status Temuan</b>
                        </span>
                        <EditorQuill
                            v-model="f.ringkasan"
                            mungil
                            placeholder="Apa yang diperiksa, ke siapa, dan hasilnya."
                        />
                        <small class="pmk__hitung" :class="{ 'is-lebih': lebihPanjang(f.ringkasan) }">
                            {{ panjang(f.ringkasan) }} / {{ BATAS }} karakter (termasuk format)
                        </small>
                    </div>

                    <div class="pmk__aksi">
                        <!-- Hanya untuk komponen yang SUDAH tersimpan: berkas butuh
                             baris untuk ditempeli. -->
                        <button
                            v-if="cari(j.kode)" type="button" class="pmk__batal" :disabled="sibuk"
                            :onClick="sibuk ? null : () => pilihBerkas(cari(j.kode))"
                        >
                            <i class="bi bi-paperclip"></i> Lampirkan berkas
                        </button>
                        <span class="pmk__spacer"></span>
                        <button type="button" class="pmk__batal" :disabled="sibuk" :onClick="sibuk ? null : () => edit = null">Batal</button>
                        <button type="button" class="pmk__simpan" :disabled="sibuk" :onClick="sibuk ? null : () => simpanKomponen(j)">
                            <i class="bi bi-check-lg"></i> Simpan komponen
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- ══ REFERENCE CHECK — satu kartu per narasumber ══ -->
        <div v-else-if="referensi" class="pmk__list">
            <p v-if="!data.narasumber.length && edit !== 'BARU'" class="pmk__kosong">
                Belum ada narasumber. Umumnya 2–3 orang, dengan setidaknya satu atasan langsung.
            </p>

            <div v-for="n in data.narasumber" :key="n.id" class="pmk__row" :class="{ 'is-edit': edit === n.id }">
                <div class="pmk__rowtop">
                    <span class="pmk__ico"><i class="bi bi-person-fill"></i></span>
                    <div class="pmk__nama">
                        <b>{{ n.nama }}</b>
                        <small>
                            {{ [n.jabatan, n.perusahaan].filter(Boolean).join(' · ') || 'Tanpa keterangan jabatan' }}
                            <template v-if="n.hubungan"> — {{ labelHubungan(n.hubungan) }}</template>
                        </small>
                        <small v-if="n.ringkasan" class="pmk__isi">
                            <KontenAman :html="n.ringkasan" />
                        </small>
                    </div>
                    <span class="pmk__pill" :class="kelasKontak(n.statusKontak)">
                        {{ labelKontak(n.statusKontak) }}
                        <template v-if="n.percobaan > 0 && n.statusKontak === 'TIDAK_TERHUBUNG'"> · {{ n.percobaan }}×</template>
                    </span>
                    <span v-if="n.rekomendasi" class="pmk__pill" :class="kelasRekom(n.rekomendasi)">
                        <i class="bi bi-arrow-repeat"></i> {{ labelRekom(n.rekomendasi) }}
                    </span>
                    <button type="button" class="pmk__btn" :disabled="sibuk" :onClick="sibuk ? null : () => bukaRef(n)"><i class="bi bi-pencil"></i></button>
                    <button type="button" class="pmk__btn is-danger" :disabled="sibuk" :onClick="sibuk ? null : () => hapusRef(n)"><i class="bi bi-trash"></i></button>
                </div>
            </div>

            <div v-if="edit === 'BARU' || typeof edit === 'number'" class="pmk__form is-lepas">
                <div class="pmk__grid">
                    <label>
                        <span>Nama <b>wajib</b></span>
                        <input v-model="f.nama" type="text" placeholder="Nama narasumber" />
                    </label>
                    <label>
                        <span>Hubungan</span>
                        <select v-model="f.hubungan">
                            <option value="">— pilih —</option>
                            <option v-for="h in HUBUNGAN" :key="h.v" :value="h.v">{{ h.t }}</option>
                        </select>
                    </label>
                    <label>
                        <span>Jabatan</span>
                        <input v-model="f.jabatan" type="text" placeholder="mis. Manager Operasional" />
                    </label>
                    <label>
                        <span>Perusahaan</span>
                        <input v-model="f.perusahaan" type="text" />
                    </label>
                    <label>
                        <span>Telepon</span>
                        <input v-model="f.kontakTelp" type="text" />
                    </label>
                    <label>
                        <span>Email</span>
                        <input v-model="f.kontakEmail" type="email" />
                    </label>
                </div>

                <div class="pmk__opts">
                    <button
                        v-for="k in KONTAK" :key="k.v"
                        type="button" class="pmk__opt" :class="{ 'is-on': f.statusKontak === k.v }"
                        @click="f.statusKontak = k.v"
                    >{{ k.t }}</button>
                </div>

                <div v-if="f.statusKontak === 'TERHUBUNG'" class="pmk__opts is-kecil">
                    <span class="pmk__lbl">Bersedia mempekerjakan kembali?</span>
                    <button
                        v-for="r in REKOM" :key="r.v"
                        type="button" class="pmk__opt is-kecil" :class="{ 'is-on': f.rekomendasi === r.v }"
                        @click="f.rekomendasi = r.v"
                    >{{ r.t }}</button>
                </div>

                <div v-if="f.statusKontak === 'TIDAK_TERHUBUNG'" class="pmk__grid">
                    <label>
                        <span>Jumlah percobaan</span>
                        <input v-model.number="f.percobaan" type="number" min="0" max="99" />
                    </label>
                    <div></div>
                </div>

                <div class="pmk__area">
                    <span>
                        Ringkasan keterangan
                        <b v-if="f.statusKontak === 'TERHUBUNG'">wajib bila terhubung</b>
                    </span>
                    <!-- EDITOR, BUKAN KOTAK POLOS. Keterangan narasumber kerap
                         berisi beberapa butir (masa kerja, alasan berhenti, catatan)
                         yang jauh lebih terbaca sebagai daftar daripada satu paragraf.

                         Tanpa tombol gambar: isinya keterangan tentang orang, dan
                         gambar yang tertanam di HTML tidak bisa diredaksi terpisah
                         saat masa retensinya lewat. -->
                    <EditorQuill
                        v-model="f.ringkasan"
                        mungil
                        placeholder="Isi percakapannya — masa kerja, jabatan, alasan berhenti, catatan penting."
                    />
                    <small class="pmk__hitung" :class="{ 'is-lebih': lebihPanjang(f.ringkasan) }">
                        {{ panjang(f.ringkasan) }} / {{ BATAS }} karakter (termasuk format)
                    </small>
                </div>

                <div class="pmk__aksi">
                    <button type="button" class="pmk__batal" :disabled="sibuk" :onClick="sibuk ? null : () => edit = null">Batal</button>
                    <button type="button" class="pmk__simpan" :disabled="sibuk" :onClick="sibuk ? null : simpanRef">
                        <i class="bi bi-check-lg"></i> Simpan narasumber
                    </button>
                </div>
            </div>
        </div>

        <p v-if="galat" class="pmk__galat"><i class="bi bi-exclamation-triangle-fill"></i> {{ galat }}</p>
    </div>
</template>

<script>
import axios from 'axios';
import EditorQuill from '@career/EditorQuill.vue';
import KontenAman from '@career/KontenAman.vue';

const CFG = { headers: { Accept: 'application/json' } };
const API = '/api/v1/karir/lamaran/sub-tes';

const STATUS = [
    { v: 'MENUNGGU', t: 'Menunggu' },
    { v: 'PROSES', t: 'Proses' },
    { v: 'BERSIH', t: 'Bersih' },
    { v: 'TEMUAN', t: 'Ada temuan' },
    { v: 'TIDAK_TERVERIFIKASI', t: 'Tak terverifikasi' },
];

const KONTAK = [
    { v: 'BELUM', t: 'Belum dihubungi' },
    { v: 'TERHUBUNG', t: 'Terhubung' },
    { v: 'TIDAK_TERHUBUNG', t: 'Tidak terhubung' },
    { v: 'MENOLAK', t: 'Menolak' },
];

const HUBUNGAN = [
    { v: 'ATASAN_LANGSUNG', t: 'Atasan langsung' },
    { v: 'REKAN', t: 'Rekan setim' },
    { v: 'KLIEN', t: 'Klien' },
    { v: 'HR', t: 'HR / Personalia' },
];

const REKOM = [
    { v: 'YA', t: 'Ya' },
    { v: 'RAGU', t: 'Ragu' },
    { v: 'TIDAK', t: 'Tidak' },
];

export default {
    name: 'PanelPemeriksaan',
    components: { EditorQuill, KontenAman },
    props: {
        subTesId: { type: String, required: true },
        data: { type: Object, required: true },
        // Berkas aktivitas ini — dipakai menampilkan lampiran per komponen.
        // Datang dari induk supaya satu daftar dipakai bersama kotak berkas
        // di bawah panel, bukan dua daftar yang bisa berselisih.
        berkas: { type: Array, default: () => [] },
    },
    emits: ['perbarui', 'berkas-berubah'],
    data() {
        return {
            STATUS, KONTAK, HUBUNGAN, REKOM,
            TINGKAT: ['RENDAH', 'SEDANG', 'TINGGI'],
            // Kode komponen, id narasumber, 'BARU', atau null.
            edit: null,
            f: {},
            sibuk: false,
            galat: '',
            setujuBuka: false,
            tolakBuka: false,
            fSetuju: '',
            fTanggapan: '',
            // Lebar kolom Ringkasan di basis data. Kolomnya TIDAK dilebarkan,
            // jadi editor di sini yang harus tahu batasnya — termasuk tag
            // formatnya, sebab yang tersimpan memang HTML-nya.
            BATAS: 1000,
        };
    },
    watch: {
        // Kotak tanggapan mengikuti data terbaru — termasuk sesudah panel
        // menyimpan sendiri dan menerima keadaan baru dari server.
        data: {
            immediate: true,
            handler(baru) {
                this.fTanggapan = baru?.tanggapan?.isi || '';
            },
        },
    },
    computed: {
        referensi() {
            return this.data?.jenis === 'REFERENSI';
        },
        setujuAda() {
            return !! this.data?.persetujuan?.ada;
        },
        ditolak() {
            return !! this.data?.persetujuan?.ditolak;
        },
        /** Sudah ada KEPUTUSAN — setuju maupun menolak. */
        keputusanAda() {
            return this.setujuAda || this.ditolak;
        },
        kelasSetuju() {
            if (this.ditolak) return 'is-tolak';
            if (! this.setujuAda) return 'is-kurang';

            return this.data.persetujuan.eksplisit ? 'is-ok' : 'is-umum';
        },
        ikonSetuju() {
            if (this.ditolak) return 'bi-shield-slash';

            return this.setujuAda ? 'bi-shield-check' : 'bi-shield-exclamation';
        },
        judulSetuju() {
            if (this.ditolak) return 'Kandidat MENOLAK diperiksa';
            if (! this.setujuAda) return 'Persetujuan kandidat belum ditanyakan';

            if (this.data.persetujuan.sumber === 'TELEPON') return 'Kandidat setuju diperiksa';

            return this.data.persetujuan.eksplisit
                ? 'Persetujuan pemeriksaan tercatat'
                : 'Persetujuan penggunaan data pribadi tercatat';
        },
    },
    methods: {
        potong(teks, n) {
            const t = String(teks || '');

            return t.length > n ? `${t.slice(0, n)}…` : t;
        },
        tglSingkat(v) {
            if (! v) return '';
            const d = new Date(String(v).replace(' ', 'T'));

            return Number.isNaN(d.getTime()) ? String(v) : d.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
        },
        berkasKomponen(kode) {
            const k = this.cari(kode);
            if (! k) return [];

            return (this.berkas || []).filter((b) => b.verifikasiId === k.id);
        },
        cari(kode) {
            return (this.data.komponen || []).find((k) => k.kode === kode) || null;
        },
        labelStatus(v) {
            return STATUS.find((s) => s.v === v)?.t || 'Belum dicatat';
        },
        kelasStatus(v) {
            return {
                BERSIH: 'is-ok',
                TEMUAN: 'is-warn',
                TIDAK_TERVERIFIKASI: 'is-abu',
                PROSES: 'is-info',
                MENUNGGU: 'is-abu',
            }[v] || 'is-kosong';
        },
        labelKontak(v) {
            return KONTAK.find((k) => k.v === v)?.t || v;
        },
        kelasKontak(v) {
            return { TERHUBUNG: 'is-ok', MENOLAK: 'is-warn', TIDAK_TERHUBUNG: 'is-abu' }[v] || 'is-kosong';
        },
        labelHubungan(v) {
            return HUBUNGAN.find((h) => h.v === v)?.t || v;
        },
        labelRekom(v) {
            return { YA: 'Bersedia', RAGU: 'Ragu', TIDAK: 'Tidak bersedia' }[v] || v;
        },
        kelasRekom(v) {
            return { YA: 'is-ok', RAGU: 'is-info', TIDAK: 'is-warn' }[v] || 'is-abu';
        },

        bukaKomponen(j) {
            const ada = this.cari(j.kode);
            this.galat = '';
            this.f = {
                status: ada?.status || 'PROSES',
                tingkatTemuan: ada?.tingkatTemuan || 'RENDAH',
                sumber: ada?.sumber || '',
                ringkasan: ada?.ringkasan || '',
                tanggalSelesai: ada?.tanggalSelesai || '',
            };
            this.edit = this.edit === j.kode ? null : j.kode;
        },
        bukaRef(n) {
            this.galat = '';
            this.f = {
                nama: n?.nama || '',
                jabatan: n?.jabatan || '',
                perusahaan: n?.perusahaan || '',
                hubungan: n?.hubungan || '',
                kontakTelp: n?.kontakTelp || '',
                kontakEmail: n?.kontakEmail || '',
                statusKontak: n?.statusKontak || 'BELUM',
                percobaan: n?.percobaan || 0,
                rekomendasi: n?.rekomendasi || '',
                ringkasan: n?.ringkasan || '',
            };
            this.edit = n ? n.id : 'BARU';
        },

        /** Satu tempat untuk memanggil server: pesan galatnya selalu ditampilkan. */
        async kirim(metode, url, isi = null) {
            this.sibuk = true;
            this.galat = '';
            try {
                // GET & DELETE tidak berbadan: axios menerima (url, config),
                // sedangkan patch/post menerima (url, data, config). Menyamakan
                // ketiganya membuat header Accept hilang di dua di antaranya.
                const res = ['get', 'delete'].includes(metode)
                    ? await axios[metode](url, CFG)
                    : await axios[metode](url, isi || {}, CFG);
                this.$emit('perbarui', res.data?.result || null);
                this.edit = null;

                return true;
            } catch (e) {
                this.galat = e.response?.data?.message || 'Gagal menyimpan. Coba lagi.';

                return false;
            } finally {
                this.sibuk = false;
            }
        },
        simpanKomponen(j) {
            if (this.lebihPanjang(this.f.ringkasan)) return this.tolakPanjang();

            return this.kirim('patch', `${API}/${this.subTesId}/verifikasi`, {
                jenisKode: j.kode,
                status: this.f.status,
                tingkatTemuan: this.f.status === 'TEMUAN' ? this.f.tingkatTemuan : null,
                sumber: this.f.sumber || null,
                ringkasan: this.f.ringkasan || null,
                tanggalSelesai: this.f.tanggalSelesai || null,
            });
        },
        hapusKomponen(j) {
            return this.kirim('delete', `${API}/${this.subTesId}/verifikasi/${j.kode}`);
        },
        simpanRef() {
            if (this.lebihPanjang(this.f.ringkasan)) return this.tolakPanjang();

            return this.kirim('post', `${API}/${this.subTesId}/referensi`, {
                refId: typeof this.edit === 'number' ? this.edit : null,
                nama: this.f.nama,
                jabatan: this.f.jabatan || null,
                perusahaan: this.f.perusahaan || null,
                hubungan: this.f.hubungan || null,
                kontakTelp: this.f.kontakTelp || null,
                kontakEmail: this.f.kontakEmail || null,
                statusKontak: this.f.statusKontak,
                percobaan: this.f.percobaan || 0,
                rekomendasi: this.f.statusKontak === 'TERHUBUNG' ? (this.f.rekomendasi || null) : null,
                ringkasan: this.f.ringkasan || null,
            });
        },
        hapusRef(n) {
            return this.kirim('delete', `${API}/${this.subTesId}/referensi/${n.id}`);
        },

        /**
         * Membuka isi temuan sensitif — dan pembukaannya dicatat di server.
         *
         * Sengaja satu tekan sadar, bukan otomatis saat panel terbuka: yang
         * dicatat harus benar-benar "orang ini membaca catatan hukum si A",
         * bukan "panelnya kebetulan ter-render".
         */
        bukaKunci() {
            return this.kirim('get', `${API}/${this.subTesId}/pemeriksaan`);
        },
        /** Panjang isi editor — yang dihitung HTML-nya, sebab itu yang disimpan. */
        panjang(html) {
            return String(html || '').length;
        },
        lebihPanjang(html) {
            return this.panjang(html) > this.BATAS;
        },
        tolakPanjang() {
            this.galat = `Ringkasannya melebihi ${this.BATAS} karakter (termasuk format). Ringkas dulu — kolomnya memang selebar itu.`;

            return Promise.resolve(false);
        },

        /** SETUJU: satu tekan. Kalimat lengkapnya dirakit server. */
        async putusSetuju() {
            const ok = await this.kirim('patch', `${API}/${this.subTesId}/persetujuan`, { sumber: 'TELEPON' });
            if (ok) this.tutupPilih();

            return ok;
        },
        /** TIDAK SETUJU: alasannya wajib — server menolak yang kosong. */
        async putusTolak() {
            if (! this.fSetuju.trim()) return false;

            const ok = await this.kirim('patch', `${API}/${this.subTesId}/persetujuan`, {
                sumber: 'TOLAK',
                keterangan: this.fSetuju.trim(),
            });
            if (ok) this.tutupPilih();

            return ok;
        },
        tutupPilih() {
            this.setujuBuka = false;
            this.tolakBuka = false;
            this.fSetuju = '';
        },
        tandaiDiminta(nyala) {
            return this.kirim('patch', `${API}/${this.subTesId}/tanggapan`, {
                diminta: nyala,
                isi: nyala ? (this.fTanggapan || null) : null,
            });
        },
        simpanTanggapan() {
            const lama = this.data?.tanggapan?.isi || '';
            if ((this.fTanggapan || '') === lama) return null;

            return this.kirim('patch', `${API}/${this.subTesId}/tanggapan`, {
                diminta: true,
                isi: this.fTanggapan || null,
            });
        },

        /** Lampirkan berkas ke SATU komponen pemeriksaan. */
        pilihBerkas(komponen) {
            const input = document.createElement('input');
            input.type = 'file';
            input.accept = '.pdf,.jpg,.jpeg';
            input.onchange = () => {
                const file = input.files?.[0];
                if (file) this.unggahBerkas(file, komponen);
            };
            input.click();
        },
        async unggahBerkas(file, komponen) {
            this.sibuk = true;
            this.galat = '';
            try {
                const fd = new FormData();
                fd.append('file', file);
                fd.append('verifikasiId', komponen.id);
                const { data } = await axios.post(`${API}/${this.subTesId}/berkas`, fd, CFG);
                this.$emit('berkas-berubah', data?.result || []);
            } catch (e) {
                this.galat = e.response?.data?.message || 'Berkas gagal diunggah.';
            } finally {
                this.sibuk = false;
            }
        },
    },
};
</script>

<style scoped>
.pmk__kosong { margin: 0; padding: 14px 8px; text-align: center; font-size: 11.5px; line-height: 1.55; color: #a2a9ba; }
.pmk { margin-top: 12px; border: 1px solid #e2e8f0; border-radius: 12px; background: #fff; overflow: hidden; }

.pmk__head { display: flex; align-items: flex-start; gap: 9px; padding: 10px 12px; background: #f8fafc; border-bottom: 1px solid #eef2f7; }
.pmk__head > i { color: #4f46e5; font-size: 15px; margin-top: 1px; }
.pmk__head > div { flex: 1; min-width: 0; }
.pmk__head b { display: block; font-size: 12.5px; font-weight: 800; color: #1e293b; }
.pmk__head small { display: block; margin-top: 1px; font-size: 11px; color: #64748b; }
.pmk__add { display: inline-flex; align-items: center; gap: 4px; padding: 5px 10px; border-radius: 8px; border: 1px solid #c7d2fe; background: #eef2ff; color: #4338ca; font-size: 11.5px; font-weight: 800; cursor: pointer; }
.pmk__add:hover { background: #e0e7ff; }
.pmk__add:disabled { opacity: .5; cursor: not-allowed; }

/* PERSETUJUAN — tiga keadaan, tiga warna. Hijau: pernyataannya menyebut
   pemeriksaan. Biru: persetujuan umum data pribadi (sah, tapi bedanya harus
   terlihat). Kuning: belum ada apa-apa — dan aktivitasnya tidak bisa ditutup. */
.pmk__setuju { display: flex; gap: 9px; align-items: flex-start; padding: 10px 12px; border-bottom: 1px solid #f1f5f9; }
.pmk__setuju > i { flex: none; margin-top: 1px; font-size: 15px; }
.pmk__setuju > div { flex: 1; min-width: 0; }
.pmk__setuju b { display: block; font-size: 12px; }
.pmk__setuju small { display: block; margin-top: 2px; font-size: 11px; line-height: 1.5; color: #64748b; }
.pmk__setuju small.is-catat { margin-top: 4px; color: #b45309; }
.pmk__setuju small.is-konteks { margin-top: 5px; color: #64748b; }
.pmk__setuju small.is-konteks i { color: #94a3b8; }
.pmk__setuju.is-ok { background: #f6fdf9; } .pmk__setuju.is-ok > i, .pmk__setuju.is-ok b { color: #047857; }
.pmk__setuju.is-umum { background: #f7fbff; } .pmk__setuju.is-umum > i, .pmk__setuju.is-umum b { color: #1d4ed8; }
.pmk__setuju.is-kurang { background: #fffbeb; } .pmk__setuju.is-kurang > i, .pmk__setuju.is-kurang b { color: #b45309; }
.pmk__setuju.is-tolak { background: #fef7f7; } .pmk__setuju.is-tolak > i, .pmk__setuju.is-tolak b { color: #b91c1c; }

/* Dua tombol keputusan — sengaja sama besar: keduanya jawaban yang sah, dan
   yang satu tidak boleh terlihat seperti jalan yang benar. */
.pmk__pilih { display: flex; flex-wrap: wrap; gap: 7px; margin-top: 9px; }
.pmk__ya, .pmk__tidak {
    display: inline-flex; align-items: center; gap: 5px; cursor: pointer;
    padding: 7px 14px; border-radius: 9px; font-size: 12px; font-weight: 800;
}
.pmk__ya { border: 1px solid #a7f3d0; background: #ecfdf5; color: #047857; }
.pmk__ya:hover { background: #d1fae5; }
.pmk__tidak { border: 1px solid #fecaca; background: #fff; color: #b91c1c; }
.pmk__tidak:hover, .pmk__tidak.is-on { background: #fef2f2; }
.pmk__ya:disabled, .pmk__tidak:disabled { opacity: .5; cursor: not-allowed; }

.pmk__buntu {
    display: flex; gap: 7px; align-items: flex-start; margin: 0;
    padding: 12px; font-size: 11.5px; line-height: 1.6; color: #b91c1c; background: #fef7f7;
}
.pmk__buntu i { flex: none; margin-top: 2px; }

.pmk__hitung { display: block; margin-top: 3px; font-size: 10px; color: #a3adc2; text-align: right; }
.pmk__hitung.is-lebih { color: #b91c1c; font-weight: 800; }
/* Isi ber-format di dalam baris ringkas: paragrafnya tidak boleh menambah
   jarak, dan daftarnya tetap terbaca. */
.pmk__isi :deep(p) { margin: 0; }
.pmk__isi :deep(ul), .pmk__isi :deep(ol) { margin: 2px 0; padding-left: 16px; }

.pmk__setujuform { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 8px; }
.pmk__setujuform input { flex: 1 1 240px; padding: 6px 9px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 12px; font-family: inherit; }
.pmk__setujuform input:focus { outline: none; border-color: #6366f1; }

/* HAK MENANGGAPI. */
.pmk__tanggap { padding: 10px 12px; border-bottom: 1px solid #f1f5f9; background: #fcfcfd; }
.pmk__tanggap.is-ok { background: #f6fdf9; }
.pmk__tanggapchk { display: flex; gap: 8px; align-items: flex-start; cursor: pointer; }
.pmk__tanggapchk input { margin-top: 2px; }
.pmk__tanggapchk b { display: block; font-size: 12px; color: #334155; }
.pmk__tanggapchk small { display: block; margin-top: 1px; font-size: 10.5px; color: #94a3b8; }
.pmk__tanggap textarea { width: 100%; margin-top: 8px; padding: 7px 9px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 12px; font-family: inherit; resize: vertical; }
.pmk__tanggap textarea:focus { outline: none; border-color: #6366f1; }

/* Temuan sensitif yang masih terkunci + lampiran per komponen. */
.pmk__nama small.is-kunci { display: flex; flex-wrap: wrap; align-items: center; gap: 5px; color: #b45309; }
.pmk__buka { padding: 1px 7px; border-radius: 6px; border: 1px solid #fcd34d; background: #fff; color: #92400e; font-size: 10px; font-weight: 800; cursor: pointer; }
.pmk__buka:hover { background: #fef3c7; }
.pmk__lampir { display: flex; flex-wrap: wrap; gap: 5px; margin-top: 4px; }
.pmk__lampir a { display: inline-flex; align-items: center; gap: 3px; padding: 1px 6px; border-radius: 6px; background: #eef2ff; color: #4338ca; font-size: 10px; font-weight: 700; text-decoration: none; }
.pmk__lampir a:hover { background: #e0e7ff; }
.pmk__spacer { flex: 1; }

.pmk__list { display: flex; flex-direction: column; }
.pmk__row { border-bottom: 1px solid #f1f5f9; }
.pmk__row:last-child { border-bottom: none; }
.pmk__row.is-edit { background: #fbfcff; }

.pmk__rowtop { display: flex; align-items: center; gap: 9px; padding: 9px 12px; }
.pmk__ico { flex: none; width: 26px; height: 26px; display: grid; place-items: center; border-radius: 8px; background: #f1f5f9; font-size: 13px; }
.pmk__nama { flex: 1; min-width: 0; }
.pmk__nama b { display: inline-block; font-size: 12.5px; color: #1e293b; }
.pmk__nama small { display: block; font-size: 11px; line-height: 1.45; color: #64748b; overflow-wrap: anywhere; }
.pmk__nama small.is-samar { color: #a3adc2; }
.pmk__sensitif { display: inline-flex; align-items: center; gap: 3px; margin-left: 6px; padding: 1px 5px; border-radius: 5px; background: #fef2f2; color: #b91c1c; font-size: 9px; font-weight: 800; letter-spacing: .03em; vertical-align: middle; }

.pmk__pill { flex: none; padding: 3px 8px; border-radius: 999px; font-size: 10.5px; font-weight: 800; white-space: nowrap; }
.pmk__pill.is-ok { background: #ecfdf5; color: #047857; }
.pmk__pill.is-warn { background: #fffbeb; color: #b45309; }
.pmk__pill.is-info { background: #eff6ff; color: #1d4ed8; }
.pmk__pill.is-abu { background: #f1f5f9; color: #475569; }
.pmk__pill.is-kosong { background: #f8fafc; color: #94a3b8; }

.pmk__btn { flex: none; width: 26px; height: 26px; display: grid; place-items: center; border-radius: 7px; border: 1px solid #e2e8f0; background: #fff; color: #475569; cursor: pointer; font-size: 11px; }
.pmk__btn:hover { border-color: #6366f1; color: #4338ca; }
.pmk__btn.is-danger:hover { border-color: #ef4444; color: #b91c1c; }
.pmk__btn:disabled { opacity: .45; cursor: not-allowed; }

.pmk__form { padding: 2px 12px 12px; display: flex; flex-direction: column; gap: 8px; }
.pmk__form.is-lepas { padding-top: 10px; border-top: 1px dashed #e2e8f0; }

.pmk__opts { display: flex; flex-wrap: wrap; align-items: center; gap: 5px; }
.pmk__lbl { font-size: 11px; font-weight: 700; color: #64748b; margin-right: 2px; }
.pmk__opt { padding: 5px 10px; border-radius: 8px; border: 1px solid #e2e8f0; background: #fff; color: #475569; font-size: 11.5px; font-weight: 700; cursor: pointer; }
.pmk__opt:hover { border-color: #c7d2fe; }
.pmk__opt.is-on { border-color: #6366f1; background: #eef2ff; color: #4338ca; }
.pmk__opt.is-kecil { padding: 3px 8px; font-size: 10.5px; }

.pmk__grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
@media (max-width: 620px) { .pmk__grid { grid-template-columns: 1fr; } }
.pmk__grid label, .pmk__area { display: flex; flex-direction: column; gap: 3px; }
.pmk__grid span, .pmk__area span { font-size: 10.5px; font-weight: 700; color: #64748b; }
.pmk__grid b, .pmk__area b { color: #b45309; font-weight: 700; }
.pmk__grid input, .pmk__grid select, .pmk__area textarea {
    width: 100%; padding: 7px 9px; border: 1px solid #e2e8f0; border-radius: 8px;
    font-size: 12px; color: #1e293b; font-family: inherit; background: #fff;
}
.pmk__grid input:focus, .pmk__grid select:focus, .pmk__area textarea:focus { outline: none; border-color: #6366f1; }
.pmk__area textarea { resize: vertical; }

.pmk__aksi { display: flex; justify-content: flex-end; gap: 7px; }
.pmk__batal { padding: 6px 12px; border-radius: 8px; border: 1px solid #e2e8f0; background: #fff; color: #64748b; font-size: 11.5px; font-weight: 700; cursor: pointer; }
.pmk__simpan { display: inline-flex; align-items: center; gap: 5px; padding: 6px 12px; border-radius: 8px; border: none; background: linear-gradient(135deg, #8b5cf6, #6366f1); color: #fff; font-size: 11.5px; font-weight: 800; cursor: pointer; }
.pmk__simpan:disabled, .pmk__batal:disabled { opacity: .5; cursor: not-allowed; }.pmk__galat { display: flex; gap: 6px; margin: 0; padding: 9px 12px; background: #fef2f2; color: #b91c1c; font-size: 11.5px; border-top: 1px solid #fee2e2; }
</style>
