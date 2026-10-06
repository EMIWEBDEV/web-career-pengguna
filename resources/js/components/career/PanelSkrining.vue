<!--
    PANEL PHONE SCREENING — kuesioner yang diisi rekruter sambil menelepon.

    ══ KENAPA PANEL SENDIRI, BUKAN SATU KOTAK "CATAT HASIL" ═══════════════════

    Karena skrining telepon tidak punya "hasil" tunggal yang bisa ditulis dalam
    satu kotak, dan — ini yang menentukan bentuknya — SERING PUTUS DI TENGAH.
    Kandidat tidak angkat, minta dijadwalkan ulang, atau teleponnya terputus di
    pertanyaan kelima.

    Karena itu tiap potongannya bisa disimpan tanpa menutup apa pun. Yang
    menutup adalah tombol Selesaikan, dan itu tindakan terpisah yang menuntut
    kelengkapan.

    ══ SKOR DIHITUNG SERVER ═══════════════════════════════════════════════════

    Angka yang tampil di bilah atas datang dari tanggapan server sesudah
    menyimpan, bukan dihitung ulang di sini. Skor ini ikut menentukan apakah
    seseorang lanjut atau tidak; menghitungnya di dua tempat berarti dua rumus
    yang cepat atau lambat berselisih, dan yang salah selalu yang di layar.

    ══ KNOCKOUT MENANDAI, TIDAK MENGGUGURKAN ══════════════════════════════════

    Jawaban yang kena syarat auto-gugur memunculkan pita merah — bukan menutup
    lamaran. Palu tetap diketuk lewat mesin keputusan tahap.
-->
<template>
    <div class="pss">
        <!-- ══ BELUM ADA SESI ════════════════════════════════════════════════ -->
        <div v-if="!sesi" class="pss-mulai">
            <div class="pss-mulai__ico"><i class="bi bi-telephone-outbound"></i></div>
            <div class="pss-mulai__t">
                <b>{{ isi.nama || isi.kode || 'Kuesioner skrining' }}</b>
                <small v-if="isi.terikat">Versi {{ isi.versi }} · {{ isi.pertanyaan.length }} pertanyaan</small>
                <small v-else class="pss-mulai__belum">
                    Aktivitas ini belum terikat template. Pasang dulu di Program Kegiatan.
                </small>
            </div>
            <div v-if="isi.terikat" class="pss-mulai__aksi">
                <el-select v-model="metodeAwal" class="skr-sel" popper-class="skr-pop">
                    <el-option v-for="m in isi.pilihanMetode" :key="m" :label="labelMetode(m)" :value="m" />
                </el-select>
                <button class="skr-btn skr-btn--isi" :disabled="busy" @click="mulai">
                    <i class="bi bi-play-fill"></i> {{ busy ? 'Membuka…' : 'Mulai Skrining' }}
                </button>
            </div>
        </div>

        <template v-else>
            <!-- ══ BILAH KEADAAN ═════════════════════════════════════════════ -->
            <div class="pss-bar" :class="{ 'is-kunci': sesi.dikunci, 'is-ko': sesi.knockout }">
                <div class="pss-bar__kiri">
                    <span class="pss-bar__st" :class="`is-${sesi.status.toLowerCase()}`">
                        <i class="bi" :class="sesi.status === 'SELESAI' ? 'bi-lock-fill' : 'bi-pencil-fill'"></i>
                        {{ sesi.status === 'SELESAI' ? 'Selesai & terkunci' : 'Sedang diisi' }}
                    </span>
                    <span class="pss-bar__nm">{{ sesi.nama }} <em>v{{ sesi.versi }}</em></span>
                </div>

                <div class="pss-bar__kanan">
                    <div v-if="skor.persen !== null" class="pss-skor">
                        <div class="pss-skor__bar">
                            <span :style="{ width: `${Math.min(100, skor.persen)}%` }" :class="kelasSkor"></span>
                        </div>
                        <b>{{ skor.persen }}%</b>
                        <small>{{ skor.skor }} / {{ skor.maks }}</small>
                    </div>
                    <span v-else class="pss-bar__nilai">belum ada skor</span>

                    <span class="pss-bar__isi">{{ terisi }} / {{ isi.pertanyaan.length }} terjawab</span>
                </div>
            </div>

            <!-- Pita knockout. Kalimatnya menyebut PERTANYAAN MANA — "kandidat
                 tidak memenuhi syarat" tanpa menyebut apanya memaksa petugas
                 menyusuri ulang seluruh jawaban untuk menemukan sebabnya. -->
            <div v-if="sesi.knockout" class="pss-ko">
                <i class="bi bi-exclamation-octagon-fill"></i>
                <div>
                    <b>Ditandai gugur otomatis</b>
                    <span>{{ sesi.knockoutPesan }}</span>
                    <small>
                        Ini <b>rekomendasi</b>, bukan keputusan — tahapnya tetap diputus lewat tombol
                        keputusan seperti biasa.
                    </small>
                </div>
            </div>

            <p v-if="petunjuk" class="pss-petunjuk"><i class="bi bi-info-circle"></i> {{ petunjuk }}</p>

            <!-- ══ PERTANYAAN ════════════════════════════════════════════════ -->
            <div v-for="(grup, seksi) in perSeksi" :key="seksi" class="pss-blok">
                <div class="pss-blok__h">
                    <i class="bi bi-list-check"></i>
                    {{ seksi === '_' ? 'Pertanyaan' : seksi }}
                </div>

                <div v-for="q in grup" :key="q.kode" class="pss-q" :class="{ 'is-ko': q.knockout, 'is-kena': kenaKo(q) }">
                    <div class="pss-q__t">
                        <span class="pss-q__no">{{ q.urutan }}</span>
                        <div>
                            <b>{{ q.label }}<span v-if="q.wajib" class="pss-q__wajib">*</span></b>
                            <small v-if="q.bantuan">{{ q.bantuan }}</small>
                        </div>
                        <span v-if="q.bobot !== null" class="pss-q__bobot">bobot {{ q.bobot }}</span>
                    </div>

                    <div class="pss-q__isi">
                        <!-- Pilihan tunggal & Ya/Tidak: kartu, bukan dropdown.
                             Petugas sedang menelepon — mengklik satu dari tiga
                             kartu yang sudah terlihat jauh lebih cepat daripada
                             membuka daftar, membacanya, lalu memilih. -->
                        <div v-if="q.tipe === 'RADIO' || q.tipe === 'BOOLEAN'" class="pss-opts">
                            <button
                                v-for="o in opsiUntuk(q)" :key="o.nilai"
                                type="button" class="pss-opt"
                                :class="{ 'is-on': jawab[q.kode] === o.nilai }"
                                :disabled="terkunci"
                                @click="pilih(q, o.nilai)"
                            >
                                <span class="pss-opt__dot"></span>
                                <span>{{ o.label }}</span>
                                <em v-if="o.skor !== null">{{ o.skor }}</em>
                            </button>
                        </div>

                        <el-select
                            v-else-if="q.tipe === 'SELECT'"
                            v-model="jawab[q.kode]" :disabled="terkunci" clearable filterable
                            placeholder="Pilih…" class="skr-sel" popper-class="skr-pop" @change="tandaiKotor"
                        >
                            <el-option
                                v-for="o in q.opsi" :key="o.nilai"
                                :label="o.skor !== null ? `${o.label} (${o.skor})` : o.label" :value="o.nilai"
                            />
                        </el-select>

                        <div v-else-if="q.tipe === 'CHECKBOX'" class="pss-cbs">
                            <label v-for="o in q.opsi" :key="o.nilai" class="pss-cb">
                                <input
                                    type="checkbox" :disabled="terkunci"
                                    :checked="(jawab[q.kode] || []).includes(o.nilai)"
                                    @change="toggleCb(q, o.nilai)"
                                />
                                {{ o.label }}
                            </label>
                        </div>

                        <!-- Skala digambar sebagai deret tombol angka, bukan
                             slider: nilainya diskret dan petugas harus bisa
                             mengenainya sekali tekan tanpa melihat layar lama. -->
                        <div v-else-if="berskala(q.tipe)" class="pss-skala">
                            <small v-if="q.labelMin">{{ q.labelMin }}</small>
                            <button
                                v-for="n in deret(q)" :key="n"
                                type="button" class="pss-skala__n"
                                :class="{ 'is-on': Number(jawab[q.kode]) === n }"
                                :disabled="terkunci"
                                @click="pilih(q, n)"
                            >{{ n }}</button>
                            <small v-if="q.labelMax">{{ q.labelMax }}</small>
                        </div>

                        <input
                            v-else-if="q.tipe === 'NUMBER'"
                            v-model="jawab[q.kode]" class="skr-ctl" type="number" :disabled="terkunci" @blur="tandaiKotor"
                        />
                        <input
                            v-else-if="q.tipe === 'CURRENCY'"
                            :value="rupiah(jawab[q.kode])" class="skr-ctl pss-rp" type="text" :disabled="terkunci"
                            inputmode="numeric" placeholder="Rp 0"
                            @input="ketikRupiah(q, $event)" @blur="tandaiKotor"
                        />
                        <el-date-picker
                            v-else-if="q.tipe === 'DATE'"
                            v-model="jawab[q.kode]" type="date" value-format="YYYY-MM-DD" format="DD MMM YYYY"
                            :disabled="terkunci" placeholder="Pilih tanggal" class="skr-sel" popper-class="skr-pop"
                            @change="tandaiKotor"
                        />
                        <EditorQuill
                            v-else-if="q.tipe === 'EDITOR'"
                            v-model="jawab[q.kode]" :disabled="terkunci" @blur="tandaiKotor"
                        />
                        <textarea
                            v-else-if="q.tipe === 'TEXTAREA'"
                            v-model="jawab[q.kode]" class="skr-ctl" rows="3" :disabled="terkunci" @blur="tandaiKotor"
                        ></textarea>
                        <input
                            v-else
                            v-model="jawab[q.kode]" class="skr-ctl" type="text" maxlength="1000" :disabled="terkunci" @blur="tandaiKotor"
                        />

                        <input
                            v-if="q.catatan"
                            v-model="catatan[q.kode]" class="skr-ctl pss-q__note" type="text"
                            placeholder="Catatan untuk jawaban ini…" maxlength="1000" :disabled="terkunci" @blur="tandaiKotor"
                        />
                    </div>
                </div>
            </div>

            <!-- ══ CATATAN PETUGAS ═══════════════════════════════════════════
                 Dua bagian, satu kartu. Keduanya ditulis PETUGAS tentang
                 jalannya percakapan — bukan jawaban kandidat — dan tak satu
                 pun pernah dilihat kandidat. -->
            <div class="pss-blok">
                <div class="pss-blok__h"><i class="bi bi-journal-text"></i> Catatan petugas</div>

                <div class="pss-bagian">
                    <div class="pss-bagian__h">
                        <i class="bi bi-telephone"></i>
                        <span>Jalannya panggilan</span>
                        <small>diisi sesudah percakapan selesai</small>
                    </div>
                    <div class="pss-panggil">
                        <div>
                            <label class="skr-lbl">Metode</label>
                            <el-select v-model="kepala.metode" :disabled="terkunci" class="skr-sel" popper-class="skr-pop" @change="tandaiKotor">
                                <el-option v-for="m in isi.pilihanMetode" :key="m" :label="labelMetode(m)" :value="m" />
                            </el-select>
                        </div>
                        <div>
                            <label class="skr-lbl">Nomor dihubungi</label>
                            <!-- Komponen yang sama dengan formulir lamaran: pemilih
                                 kode negara, bawaan Indonesia (+62). Kotak teks bebas
                                 dulu menerima "0812", "62812", dan "+62 812-3456"
                                 sebagai tiga nomor berbeda — padahal ketiganya orang
                                 yang sama, dan itulah yang dipakai mencocokkan ulang
                                 saat panggilannya diulang esok hari. -->
                            <TeleponNegara
                                :model-value="String(kepala.kontakNomor ?? '')"
                                :disabled="terkunci"
                                placeholder="81234567890"
                                @update:model-value="(v) => { kepala.kontakNomor = v; tandaiKotor(); }"
                            />
                        </div>
                        <div>
                            <label class="skr-lbl">Percobaan</label>
                            <input
                                v-model.number="kepala.percobaan" class="skr-ctl skr-ctl--num" type="number"
                                :disabled="terkunci" min="0" max="99" @change="tandaiKotor"
                            />
                        </div>
                        <div>
                            <label class="skr-lbl">Hasil kontak</label>
                            <el-select v-model="kepala.hasilKontak" :disabled="terkunci" clearable placeholder="—" class="skr-sel" popper-class="skr-pop" @change="tandaiKotor">
                                <el-option v-for="k in isi.pilihanKontak" :key="k" :label="labelKontak(k)" :value="k" />
                            </el-select>
                        </div>
                        <div>
                            <label class="skr-lbl">Durasi (menit)</label>
                            <input
                                v-model.number="kepala.durasiMenit" class="skr-ctl skr-ctl--num" type="number"
                                :disabled="terkunci" min="0" max="1440" @change="tandaiKotor"
                            />
                        </div>
                    </div>
                </div>

                <div class="pss-bagian">
                    <div class="pss-bagian__h">
                        <i class="bi bi-flag"></i>
                        <span>Kesimpulan &amp; rekomendasi</span>
                        <small>wajib sebelum sesi bisa diselesaikan</small>
                    </div>

                    <label class="skr-lbl">Rekomendasi</label>
                    <div class="pss-opts pss-opts--rek">
                        <button
                            v-for="r in isi.pilihanRekomendasi" :key="r"
                            type="button" class="pss-opt" :class="[`is-${r.toLowerCase()}`, { 'is-on': kepala.rekomendasi === r }]"
                            :disabled="terkunci"
                            @click="kepala.rekomendasi = kepala.rekomendasi === r ? null : r; tandaiKotor()"
                        >
                            <span class="pss-opt__dot"></span>
                            <span>{{ labelRekomendasi(r) }}</span>
                        </button>
                    </div>

                    <label class="pss-lbl pss-lbl--sp">
                        Ringkasan
                        <small><i class="bi bi-eye-slash-fill"></i> hanya tim — tidak pernah dilihat kandidat</small>
                    </label>
                    <EditorQuill v-model="kepala.ringkasanHtml" :disabled="terkunci" @blur="tandaiKotor" />
                </div>
            </div>

            <!-- ══ AKSI ══════════════════════════════════════════════════════
                 Keterangan ditaruh DI ATAS tombolnya, bukan di tooltip: yang
                 perlu tahu bedanya justru orang yang belum pernah menekannya,
                 dan ia tidak akan menyorot tombol yang belum ia pahami. -->
            <div v-if="!terkunci" class="pss-siap" :class="{ 'is-siap': !kurangWajib.length && kepala.rekomendasi }">
                <i class="bi" :class="!kurangWajib.length && kepala.rekomendasi ? 'bi-check-circle-fill' : 'bi-hourglass-split'"></i>
                <div>
                    <b v-if="!kurangWajib.length && kepala.rekomendasi">Siap diselesaikan.</b>
                    <b v-else>Belum bisa diselesaikan.</b>
                    <span v-if="kurangWajib.length">
                        {{ kurangWajib.length }} pertanyaan wajib belum dijawab<template v-if="!kepala.rekomendasi">, dan rekomendasi belum dipilih</template>.
                    </span>
                    <span v-else-if="!kepala.rekomendasi">Rekomendasi petugas belum dipilih.</span>
                    <span v-else>Jawabannya lengkap dan rekomendasi sudah dipilih.</span>
                    <span v-if="kotor" class="pss-siap__kotor">
                        Perubahan terakhir belum disimpan — <b>Selesaikan</b> ikut menyimpannya.
                    </span>
                </div>
            </div>

            <div class="pss-aksi">
                <!-- Isian tersimpan sendiri 0,9 detik sesudah kotaknya
                     ditinggalkan. Menyebutkannya di sini menghapus kecemasan
                     yang membuat orang menekan Simpan setiap dua pertanyaan —
                     dan kecemasan itu masuk akal selama tidak ada yang
                     memberitahunya. -->
                <span v-if="tersimpan" class="pss-aksi__st"><i class="bi bi-check-circle-fill"></i> {{ tersimpan }}</span>
                <span v-else-if="kotor" class="pss-aksi__st is-kotor">
                    <i class="bi bi-exclamation-circle-fill"></i> ada perubahan yang belum disimpan
                </span>
                <span v-else-if="!terkunci" class="pss-aksi__st is-samar"><i class="bi bi-check2"></i> tidak ada perubahan</span>

                <template v-if="!terkunci">
                    <el-tooltip placement="top" :show-after="150" popper-class="skr-pop">
                        <template #content>
                            Menyimpan jawaban sekarang juga, <b>tanpa menutup sesi</b>.<br />
                            Dipakai saat telepon terputus atau kandidat minta dijadwalkan ulang —
                            panelnya bisa dibuka lagi dan dilanjutkan.
                        </template>
                        <button class="skr-btn" :disabled="busy" @click="simpan(true)">
                            <i class="bi bi-save"></i> Simpan &amp; lanjutkan nanti
                        </button>
                    </el-tooltip>

                    <el-tooltip placement="top" :show-after="150" popper-class="skr-pop">
                        <template #content>
                            Menghitung skor akhir lalu <b>mengunci sesi</b>. Sesudah ini aktivitas
                            skrining baru bisa ditutup dan tahapnya diputus.<br />
                            Menyuntingnya lagi menuntut buka kunci, dan pembukaannya tercatat di log.
                        </template>
                        <button class="skr-btn skr-btn--isi" :disabled="busy" @click="$emit('selesaikan', kurangWajib)">
                            <i class="bi bi-lock-fill"></i> Selesaikan &amp; kunci
                        </button>
                    </el-tooltip>
                </template>
                <button v-else class="skr-btn" @click="$emit('buka-kunci')">
                    <i class="bi bi-unlock"></i> Buka Kunci
                </button>
            </div>

            <p v-if="!terkunci && kurangWajib.length" class="pss-kurang">
                <i class="bi bi-exclamation-triangle"></i>
                Belum bisa diselesaikan — {{ kurangWajib.length }} pertanyaan wajib masih kosong:
                <b>{{ kurangWajib.slice(0, 3).join(', ') }}</b>
                <span v-if="kurangWajib.length > 3">, dan {{ kurangWajib.length - 3 }} lagi</span>.
            </p>

            <p class="pss-jejak">
                Petugas <b>{{ sesi.petugas || '—' }}</b>
                · dibuka {{ tglJam(sesi.dibuatPada) }}
                <span v-if="sesi.waktuSelesai"> · selesai {{ tglJam(sesi.waktuSelesai) }}</span>
            </p>
        </template>
    </div>
</template>

<script>
import axios from 'axios';

import EditorQuill from './EditorQuill.vue';
import TeleponNegara from './TeleponNegara.vue';
import { tglJam } from '@utils/tanggal';

const CFG = { headers: { Accept: 'application/json' } };

/*
 * CHAT tetap punya label walau tidak lagi ditawarkan: sesi lama yang telanjur
 * memakainya harus tetap terbaca "Chat", bukan berubah jadi kode mentah di
 * layar orang yang membuka riwayatnya setahun kemudian.
 */
const NAMA_METODE = {
    TELEPON: 'Telepon',
    VIDEO: 'Gmeet / Zoom',
    TATAP_MUKA: 'Tatap muka',
    CHAT: 'Chat',
};
const NAMA_KONTAK = {
    TERHUBUNG: 'Terhubung',
    TIDAK_TERHUBUNG: 'Tidak terhubung',
    DIJADWAL_ULANG: 'Dijadwalkan ulang',
    MENOLAK: 'Menolak',
};
const NAMA_REKOM = { LANJUT: 'Lanjut ke tahap berikut', PERTIMBANGAN: 'Perlu pertimbangan', TIDAK_LANJUT: 'Tidak dilanjutkan' };

/* Ya/Tidak tidak menyimpan opsinya di master — bentuknya selalu sama, dan
   memaksa tiap pertanyaan BOOLEAN membawa dua baris opsi identik cuma
   menambah data yang bisa berbeda-beda tanpa alasan. */
const OPSI_BOOLEAN = [
    { nilai: 'YA', label: 'Ya', skor: null },
    { nilai: 'TIDAK', label: 'Tidak', skor: null },
];

export default {
    name: 'PanelSkrining',
    components: { EditorQuill, TeleponNegara },
    props: {
        /** Hashid aktivitas (Lamaran_Tahap_Tes). */
        subTesId: { type: [String, Number], required: true },
        /** Bentuk penuh dari Skrining::bentuk($sub, true). */
        isi: { type: Object, required: true },
        /** Petunjuk template — ditampilkan sebelum pertanyaan dimulai. */
        petunjuk: { type: String, default: '' },
        /**
         * Nomor HP dari akun kandidat — CADANGAN untuk kolom "Nomor dihubungi".
         *
         * Sesi baru sudah diisi nomor ini oleh server. Prop ini melayani sesi
         * LAMA yang dibuka sebelum perilaku itu ada, dan kolomnya masih kosong:
         * tanpa cadangan ini rekruter tetap harus menyalin nomornya sendiri.
         *
         * Hanya dipakai saat kolomnya benar-benar kosong; nomor yang sudah
         * tercatat tidak pernah ditimpa.
         */
        nomorAkun: { type: String, default: '' },
    },
    emits: ['perbarui', 'selesaikan', 'buka-kunci'],
    data() {
        return {
            jawab: {},
            catatan: {},
            kepala: {
                metode: 'TELEPON',
                kontakNomor: '',
                percobaan: 0,
                hasilKontak: null,
                durasiMenit: null,
                rekomendasi: null,
                ringkasanHtml: '',
            },
            metodeAwal: 'TELEPON',
            skor: { skor: null, maks: null, persen: null },
            busy: false,
            kotor: false,
            tersimpan: '',
            tmPesan: null,
        };
    },
    computed: {
        sesi() {
            return this.isi?.sesi || null;
        },
        terkunci() {
            return !!this.sesi?.dikunci || this.sesi?.status === 'SELESAI';
        },
        perSeksi() {
            const out = {};
            (this.isi.pertanyaan || []).forEach((q) => {
                const k = q.seksi || '_';
                (out[k] = out[k] || []).push(q);
            });

            return out;
        },
        terisi() {
            return (this.isi.pertanyaan || []).filter((q) => this.adaJawaban(q.kode)).length;
        },
        kurangWajib() {
            return (this.isi.pertanyaan || [])
                .filter((q) => q.wajib && !this.adaJawaban(q.kode))
                .map((q) => q.label);
        },
        kelasSkor() {
            if (this.skor.persen === null) return '';

            return this.skor.persen >= 70 ? 'is-baik' : this.skor.persen >= 40 ? 'is-sedang' : 'is-rendah';
        },
    },
    watch: {
        isi: {
            immediate: true,
            deep: true,
            handler() { this.serap(); },
        },
    },
    beforeUnmount() {
        clearTimeout(this.tmPesan);
    },
    methods: {
        tglJam,
        labelMetode(m) { return NAMA_METODE[m] || m; },
        labelKontak(k) { return NAMA_KONTAK[k] || k; },
        labelRekomendasi(r) { return NAMA_REKOM[r] || r; },
        berskala(t) { return ['RATING', 'LIKERT', 'NPS'].includes(t); },
        opsiUntuk(q) { return q.tipe === 'BOOLEAN' ? OPSI_BOOLEAN : q.opsi; },
        deret(q) {
            const a = q.skalaMin ?? 1;
            const b = q.skalaMax ?? 5;

            return Array.from({ length: b - a + 1 }, (_, i) => a + i);
        },
        adaJawaban(kode) {
            const v = this.jawab[kode];
            if (Array.isArray(v)) return v.length > 0;

            return v !== null && v !== undefined && String(v).trim() !== '';
        },
        kenaKo(q) {
            return this.sesi?.knockout && this.sesi.knockoutKode === q.kode;
        },

        /**
         * Serap jawaban dari server ke penampung layar.
         *
         * Dipanggil ulang setiap `isi` berubah — termasuk sesudah menyimpan,
         * karena server mengembalikan nilai yang SUDAH dihitungnya (skor per
         * jawaban, knockout). Menahan salinan lokal yang tak pernah disegarkan
         * berarti layar perlahan menyimpang dari yang tersimpan.
         */
        serap() {
            const jawab = {};
            const catatan = {};

            (this.isi.jawaban || []).forEach((j) => {
                jawab[j.kode] = j.jawaban;
                catatan[j.kode] = j.catatan || '';
            });

            // Pertanyaan yang belum punya baris jawaban tetap perlu kunci di
            // penampung — v-model pada kunci yang belum ada membuat Vue tidak
            // reaktif pada isian pertamanya.
            (this.isi.pertanyaan || []).forEach((q) => {
                if (!(q.kode in jawab)) jawab[q.kode] = q.tipe === 'CHECKBOX' ? [] : null;
                if (!(q.kode in catatan)) catatan[q.kode] = '';
            });

            this.jawab = jawab;
            this.catatan = catatan;

            const s = this.sesi;
            if (s) {
                this.kepala = {
                    metode: s.metode || 'TELEPON',
                    kontakNomor: s.kontakNomor || this.nomorAkun || '',
                    percobaan: s.percobaan ?? 0,
                    hasilKontak: s.hasilKontak,
                    durasiMenit: s.durasiMenit,
                    rekomendasi: s.rekomendasi,
                    ringkasanHtml: s.ringkasanHtml || '',
                };
                this.skor = { skor: s.skor, maks: s.skorMaks, persen: s.skorPersen };
            }

            this.kotor = false;
        },

        pilih(q, nilai) {
            if (this.terkunci) return;

            // Menekan pilihan yang sama membatalkannya. Salah tekan saat sedang
            // menelepon terlalu mudah terjadi untuk hanya bisa dibetulkan
            // dengan memilih jawaban lain yang juga salah.
            this.jawab[q.kode] = this.jawab[q.kode] === nilai ? null : nilai;
            this.tandaiKotor();
        },
        toggleCb(q, nilai) {
            if (this.terkunci) return;

            const arr = Array.isArray(this.jawab[q.kode]) ? [...this.jawab[q.kode]] : [];
            const i = arr.indexOf(nilai);
            if (i >= 0) arr.splice(i, 1);
            else arr.push(nilai);

            this.jawab[q.kode] = arr;
            this.tandaiKotor();
        },
        rupiah(v) {
            if (v === null || v === undefined || v === '') return '';

            return `Rp ${Number(String(v).replace(/\D/g, '') || 0).toLocaleString('id-ID')}`;
        },
        ketikRupiah(q, e) {
            const angka = e.target.value.replace(/\D/g, '');
            this.jawab[q.kode] = angka === '' ? null : Number(angka);
            // Kotaknya digambar ulang dari nilai bersih supaya kursor tidak
            // melompat ke ujung setiap kali pemisah ribuan bertambah.
            e.target.value = this.rupiah(this.jawab[q.kode]);
        },

        /**
         * Tandai ada perubahan — TIDAK memanggil server.
         *
         * ── KENAPA TIDAK LAGI MENYIMPAN SENDIRI ────────────────────────────
         *
         * Dulu isian tersimpan 900 ms sesudah sentuhan terakhir. Niatnya baik:
         * telepon bisa putus di pertanyaan kelima, dan menunggu tombol Simpan
         * berarti kehilangan semuanya.
         *
         * Tapi harganya satu permintaan tulis untuk setiap kotak yang
         * ditinggalkan — dua puluh satu pertanyaan berarti puluhan penulisan
         * ke basis data untuk satu telepon, dan ringkasan yang diketik sambil
         * berpikir mengirim ulang seluruh muatan setiap kali penulisnya
         * berhenti sejenak.
         *
         * Yang menggantikannya bukan ketiadaan pengaman, melainkan pengaman
         * yang lebih jujur: tombol "Simpan & lanjutkan nanti" yang memang ada
         * untuk itu, penanda "belum disimpan" yang selalu terlihat, dan
         * peringatan saat jendelanya ditutup dengan perubahan yang menggantung.
         */
        tandaiKotor() {
            this.kotor = true;
            this.tersimpan = '';
        },
        async simpan(pesan = true) {
            if (!this.sesi || this.terkunci || this.busy) return;

            this.busy = true;
            try {
                const { data } = await axios.put(`/api/v1/karir/lamaran/skrining/${this.sesi.id}`, this.muatan(), CFG);
                const r = data.result || {};

                this.skor = { skor: r.skor ?? null, maks: r.maks ?? null, persen: r.persen ?? null };
                this.kotor = false;

                if (pesan) {
                    this.tersimpan = 'tersimpan';
                    clearTimeout(this.tmPesan);
                    this.tmPesan = setTimeout(() => (this.tersimpan = ''), 2500);
                }

                // Induk yang memuat ulang: knockout bisa berubah, dan pitanya
                // digambar dari `isi` yang datang dari sana.
                this.$emit('perbarui');
            } catch (e) {
                this.$emit('perbarui', e.response?.data?.message || 'Gagal menyimpan skrining.');
            } finally {
                this.busy = false;
            }
        },
        /** Muatan simpan — dipakai juga oleh induk saat menyelesaikan sesi. */
        muatan() {
            return {
                ...this.kepala,
                jawaban: (this.isi.pertanyaan || []).map((q) => ({
                    kode: q.kode,
                    nilai: this.jawab[q.kode],
                    catatan: this.catatan[q.kode] || null,
                })),
            };
        },
        async mulai() {
            this.busy = true;
            try {
                await axios.post(`/api/v1/karir/lamaran/sub-tes/${this.subTesId}/skrining`, { metode: this.metodeAwal }, CFG);
                this.$emit('perbarui');
            } catch (e) {
                this.$emit('perbarui', e.response?.data?.message || 'Gagal membuka sesi.');
            } finally {
                this.busy = false;
            }
        },
    },
};
</script>

<style scoped>
/* Kontrol memakai `skr-*` dari evo-theme.css. Panel ini hidup DI DALAM
   AdminModal yang di-teleport ke <body>, jadi apa pun yang bergantung pada
   custom property milik halaman induknya tidak akan berlaku di sini. */

.pss { display: flex; flex-direction: column; gap: 13px; }

/* ── BELUM ADA SESI ──────────────────────────────────────────────────────── */
.pss-mulai {
    display: flex; align-items: center; gap: 15px; flex-wrap: wrap;
    padding: 19px; border: 1px dashed #cbd5e1; border-radius: 14px; background: #fbfcfe;
}
.pss-mulai__ico {
    width: 44px; height: 44px; border-radius: 13px;
    background: linear-gradient(135deg, #818cf8, #4f46e5); color: #fff;
    display: flex; align-items: center; justify-content: center; font-size: 19px; flex: 0 0 auto;
}
.pss-mulai__t { flex: 1; min-width: 180px; }
.pss-mulai__t b { display: block; font-size: 14.5px; color: #0f172a; }
.pss-mulai__t small { display: block; font-size: 12px; color: #94a3b8; margin-top: 3px; }
.pss-mulai__belum { color: #b45309 !important; }
.pss-mulai__aksi { display: flex; gap: 9px; flex: 0 0 auto; flex-wrap: wrap; }
.pss-mulai__aksi .skr-sel { width: 160px; }

/* ── BILAH KEADAAN ───────────────────────────────────────────────────────── */
.pss-bar {
    display: flex; align-items: center; justify-content: space-between;
    gap: 15px; flex-wrap: wrap; padding: 12px 15px; border-radius: 13px;
    background: rgba(99, 102, 241, 0.06); border: 1px solid rgba(99, 102, 241, 0.18);
}
.pss-bar.is-kunci { background: rgba(100, 116, 139, 0.07); border-color: rgba(100, 116, 139, 0.2); }
.pss-bar.is-ko { background: rgba(220, 38, 38, 0.06); border-color: rgba(220, 38, 38, 0.22); }
.pss-bar__kiri { display: flex; align-items: center; gap: 11px; flex-wrap: wrap; }
.pss-bar__st {
    display: inline-flex; align-items: center; gap: 6px;
    font-size: 11px; font-weight: 700; letter-spacing: 0.03em;
    padding: 4px 10px; border-radius: 7px;
    background: rgba(245, 158, 11, 0.16); color: #b45309;
}
.pss-bar__st.is-selesai { background: rgba(5, 150, 105, 0.14); color: #047857; }
.pss-bar__nm { font-size: 12.5px; color: #475569; font-weight: 600; }
.pss-bar__nm em { font-style: normal; color: #94a3b8; font-weight: 500; }
.pss-bar__kanan { display: flex; align-items: center; gap: 15px; flex-wrap: wrap; }
.pss-bar__isi, .pss-bar__nilai { font-size: 11.5px; color: #94a3b8; }

.pss-skor { display: flex; align-items: center; gap: 9px; }
.pss-skor__bar { width: 96px; height: 7px; border-radius: 4px; background: #e2e8f0; overflow: hidden; }
.pss-skor__bar span { display: block; height: 100%; border-radius: 4px; transition: width 0.25s; background: #6366f1; }
.pss-skor__bar span.is-baik { background: #059669; }
.pss-skor__bar span.is-sedang { background: #f59e0b; }
.pss-skor__bar span.is-rendah { background: #dc2626; }
.pss-skor b { font-size: 13.5px; color: #0f172a; font-variant-numeric: tabular-nums; }
.pss-skor small { font-size: 11px; color: #94a3b8; font-variant-numeric: tabular-nums; }

/* ── KNOCKOUT ────────────────────────────────────────────────────────────── */
.pss-ko {
    display: flex; align-items: flex-start; gap: 12px;
    padding: 14px 16px; border-radius: 13px;
    background: rgba(220, 38, 38, 0.06); border: 1px solid rgba(220, 38, 38, 0.2);
}
.pss-ko > i { color: #dc2626; font-size: 18px; margin-top: 1px; flex: 0 0 auto; }
.pss-ko b { display: block; font-size: 13.5px; color: #b91c1c; }
.pss-ko span { display: block; font-size: 12.5px; color: #475569; margin-top: 3px; }
.pss-ko small { display: block; font-size: 11.5px; color: #94a3b8; margin-top: 6px; line-height: 1.55; }

.pss-petunjuk {
    display: flex; gap: 9px; align-items: flex-start; margin: 0;
    padding: 11px 14px; border-radius: 11px;
    background: rgba(14, 165, 233, 0.06); font-size: 12.5px; color: #475569; line-height: 1.55;
}
.pss-petunjuk i { color: #0ea5e9; margin-top: 2px; }

/* ── BLOK ────────────────────────────────────────────────────────────────── */
.pss-blok { border: 1px solid #eef1f7; border-radius: 13px; padding: 15px; background: #fff; }
.pss-blok__h {
    display: flex; align-items: center; gap: 8px;
    font-size: 11px; font-weight: 700; letter-spacing: 0.08em;
    text-transform: uppercase; color: #6366f1; margin-bottom: 13px;
}
.pss-panggil { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 11px; }

/* ── PERTANYAAN ──────────────────────────────────────────────────────────── */
.pss-q { padding: 13px 0; border-top: 1px solid #f1f5f9; }
.pss-q:first-of-type { border-top: 0; padding-top: 0; }
.pss-q.is-kena { background: rgba(220, 38, 38, 0.04); border-radius: 10px; padding-left: 11px; padding-right: 11px; }
.pss-q__t { display: flex; align-items: flex-start; gap: 10px; margin-bottom: 9px; flex-wrap: wrap; }
.pss-q__no {
    flex: 0 0 auto; width: 23px; height: 23px; border-radius: 7px;
    background: #f1f5f9; color: #64748b; font-size: 10.5px; font-weight: 700;
    display: flex; align-items: center; justify-content: center; margin-top: 1px;
}
.pss-q.is-ko .pss-q__no { background: rgba(220, 38, 38, 0.12); color: #b91c1c; }
.pss-q__t > div { flex: 1; min-width: 180px; }
.pss-q__t b { display: block; font-size: 13.5px; color: #0f172a; line-height: 1.45; font-weight: 600; }
.pss-q__t small { display: block; font-size: 11.5px; color: #94a3b8; margin-top: 3px; }
.pss-q__wajib { color: #dc2626; margin-left: 3px; }
.pss-q__bobot {
    flex: 0 0 auto; font-size: 10px; font-weight: 700; padding: 3px 8px;
    border-radius: 6px; background: rgba(5, 150, 105, 0.12); color: #047857;
}
.pss-q__isi { padding-left: 33px; display: flex; flex-direction: column; gap: 8px; }
.pss-q__note { font-size: 12px; }
.pss-rp { font-variant-numeric: tabular-nums; }

.pss-opts { display: flex; flex-wrap: wrap; gap: 8px; }
.pss-opts--rek { margin-bottom: 4px; }
.pss-opt {
    display: inline-flex; align-items: center; gap: 8px;
    min-height: 38px; padding: 8px 14px; border-radius: 10px;
    border: 1px solid #e2e8f0; background: #fff; cursor: pointer;
    font-family: inherit; font-size: 12.5px; color: #475569; transition: all 0.13s;
}
.pss-opt:hover:not(:disabled) { border-color: #cbd5e1; background: #f8fafc; }
.pss-opt:disabled { opacity: 0.55; cursor: default; }
.pss-opt__dot {
    width: 14px; height: 14px; border-radius: 50%;
    border: 2px solid #cbd5e1; flex: 0 0 auto; transition: all 0.13s;
}
.pss-opt.is-on { border-color: #6366f1; background: rgba(99, 102, 241, 0.07); color: #4338ca; font-weight: 600; }
.pss-opt.is-on .pss-opt__dot { border-color: #6366f1; background: #6366f1; box-shadow: inset 0 0 0 2px #fff; }
.pss-opt em { font-style: normal; font-size: 11px; color: #047857; font-weight: 700; }
.pss-opt.is-lanjut.is-on { border-color: #059669; background: rgba(5, 150, 105, 0.08); color: #047857; }
.pss-opt.is-lanjut.is-on .pss-opt__dot { border-color: #059669; background: #059669; }
.pss-opt.is-pertimbangan.is-on { border-color: #f59e0b; background: rgba(245, 158, 11, 0.1); color: #b45309; }
.pss-opt.is-pertimbangan.is-on .pss-opt__dot { border-color: #f59e0b; background: #f59e0b; }
.pss-opt.is-tidak_lanjut.is-on { border-color: #dc2626; background: rgba(220, 38, 38, 0.07); color: #b91c1c; }
.pss-opt.is-tidak_lanjut.is-on .pss-opt__dot { border-color: #dc2626; background: #dc2626; }

.pss-cbs { display: flex; flex-wrap: wrap; gap: 9px 18px; }
.pss-cb { display: flex; align-items: center; gap: 7px; font-size: 12.5px; color: #475569; cursor: pointer; }
.pss-cb input { accent-color: #6366f1; width: 16px; height: 16px; }

.pss-skala { display: flex; align-items: center; gap: 7px; flex-wrap: wrap; }
.pss-skala small { font-size: 11px; color: #94a3b8; }
.pss-skala__n {
    width: 38px; height: 38px; border-radius: 10px;
    border: 1px solid #e2e8f0; background: #fff; color: #475569;
    font-family: inherit; font-size: 13px; font-weight: 600;
    cursor: pointer; transition: all 0.13s;
}
.pss-skala__n:hover:not(:disabled) { border-color: #a5b4fc; }
.pss-skala__n:disabled { opacity: 0.55; cursor: default; }
.pss-skala__n.is-on { border-color: #6366f1; background: #6366f1; color: #fff; }

/* ── AKSI ────────────────────────────────────────────────────────────────── */
/* ── BAGIAN DI DALAM SATU KARTU ──────────────────────────────────────────────
   Pemisahnya garis tipis, bukan kotak di dalam kotak. Dua kartu bersarang
   membuat isinya terdorong dua kali dari tepi dan terbaca seperti dua hal
   yang tidak berhubungan — padahal justru sebaliknya. */
.pss-siap__kotor { display: block; margin-top: 3px; color: #b45309; font-size: 11.5px; }

.pss-bagian + .pss-bagian {
    margin-top: 16px;
    padding-top: 15px;
    border-top: 1px dashed #e6eaf3;
}
.pss-bagian__h {
    display: flex; align-items: baseline; gap: 8px; flex-wrap: wrap;
    margin-bottom: 11px;
}
.pss-bagian__h > i { color: #818cf8; font-size: 12px; }
.pss-bagian__h > span {
    font-size: 11.5px; font-weight: 800; letter-spacing: .05em; text-transform: uppercase;
    color: #475569;
}
.pss-bagian__h > small { font-size: 11px; color: #94a3b8; font-weight: 500; }

/* ── KESIAPAN ────────────────────────────────────────────────────────────────
   Menyebut apa yang KURANG, bukan sekadar "belum lengkap". Rekruter yang
   sedang menelepon tidak punya waktu menyusuri dua puluh satu pertanyaan
   untuk menemukan mana yang terlewat. */
.pss-siap {
    display: flex; align-items: flex-start; gap: 10px;
    padding: 11px 13px; margin: 14px 0 10px;
    border-radius: 11px;
    border: 1px solid rgba(245, 158, 11, .28);
    background: rgba(245, 158, 11, .07);
    font-size: 12.5px; line-height: 1.55; color: #475569;
}
.pss-siap > i { color: #d97706; margin-top: 2px; flex: 0 0 auto; }
.pss-siap b { display: block; color: #92400e; }
.pss-siap.is-siap { border-color: rgba(16, 185, 129, .3); background: rgba(16, 185, 129, .07); }
.pss-siap.is-siap > i { color: #059669; }
.pss-siap.is-siap b { color: #047857; }

.pss-aksi { display: flex; align-items: center; justify-content: flex-end; gap: 9px; flex-wrap: wrap; }
.pss-aksi__st.is-samar { color: #94a3b8; }
.pss-aksi__st { margin-right: auto; font-size: 11.5px; color: #047857; display: flex; align-items: center; gap: 5px; }
.pss-aksi__st.is-kotor { color: #b45309; }
.pss-kurang {
    margin: 0; padding: 11px 14px; border-radius: 11px;
    background: rgba(245, 158, 11, 0.08); border: 1px solid rgba(245, 158, 11, 0.2);
    font-size: 12px; color: #b45309; line-height: 1.55;
}
.pss-jejak { margin: 0; font-size: 11px; color: #94a3b8; text-align: right; }

@media (max-width: 900px) {
    .pss-q__isi { padding-left: 0; }
    .pss-mulai__aksi { width: 100%; }
    .pss-mulai__aksi .skr-sel { flex: 1; width: auto; }
    .pss-aksi { justify-content: stretch; }
    .pss-aksi__st { width: 100%; margin-right: 0; }
    .pss-aksi > .skr-btn { flex: 1; }
    .pss-skala__n { width: 42px; height: 42px; }
}
</style>
