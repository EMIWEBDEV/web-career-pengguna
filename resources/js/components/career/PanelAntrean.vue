<!--
    WEB CAREER — PANEL ANTREAN PENERBITAN TOKEN.

    Bentuknya panel unggahan Google Drive: menempel di pojok kanan-bawah,
    melipat jadi satu baris, dan tidak pernah menghalangi pekerjaan di
    belakangnya.

    KENAPA ADA
    Menekan "Generate" untuk 500 kandidat menyerahkan pekerjaannya ke Cloud
    Tasks lalu mengembalikan admin ke daftar yang tampak tidak berubah. Yang
    sebenarnya terjadi — token terbit satu per satu selama beberapa menit —
    tidak terlihat sama sekali. Admin menekan Generate sekali lagi, atau
    menyegarkan halaman berkali-kali, atau mengira sistemnya menggantung.

    APA YANG DIJAMIN PANEL INI
      1. Kemajuannya nyata, bukan animasi hiburan: angkanya dihitung server
         dari baris peserta yang benar-benar sudah punya token.
      2. Yang gagal disebut NAMANYA, berikut sebabnya — dan bisa dicoba ulang
         satu per satu tanpa mengirim ulang yang sudah berhasil.
      3. Ia bertahan melewati muat ulang halaman. Sumbernya keadaan di basis
         data, bukan ingatan tab ini; menutup tab tidak membatalkan apa pun,
         dan membukanya kembali memunculkan panel yang sama.

    TIDAK ADA KOLOM BARU DI BASIS DATA. Lihat PenjadwalanController::progres().
-->
<template>
    <transition name="ant-pop">
        <section
            v-if="tampil"
            class="ant"
            :class="{ 'is-lipat': lipat }"
            role="status"
            aria-live="polite"
        >
            <!-- ══ KEPALA ══ -->
            <header class="ant__head" @click="lipat = !lipat">
                <span class="ant__ico" :class="kelasIkon">
                    <i class="bi" :class="ikon"></i>
                </span>
                <div class="ant__ttl">
                    <b>{{ judul }}</b>
                    <small>{{ ringkas }}</small>
                </div>
                <button
                    type="button" class="ant__btn"
                    :title="lipat ? 'Bentangkan' : 'Lipat'"
                    @click.stop="lipat = !lipat"
                >
                    <i class="bi" :class="lipat ? 'bi-chevron-up' : 'bi-chevron-down'"></i>
                </button>
                <!-- Menutup panel TIDAK membatalkan apa pun — penerbitannya
                     berjalan di Cloud Tasks, bukan di tab ini. Judulnya
                     menyebut itu supaya tak seorang pun ragu menekannya. -->
                <button
                    type="button" class="ant__btn"
                    :title="adaBerjalan ? 'Sembunyikan — penerbitan tetap berjalan di latar belakang' : 'Tutup'"
                    @click.stop="tutup"
                >
                    <i class="bi bi-x-lg"></i>
                </button>
            </header>

            <!-- Bilah kemajuan keseluruhan. Sengaja MELEKAT DI KEPALA, jadi ia
                 tetap terbaca saat panelnya dilipat — itulah satu-satunya hal
                 yang benar-benar ingin diketahui orang saat menunggu. -->
            <div class="ant__rail">
                <span
                    class="ant__fill" :class="{ 'is-gagal': !adaBerjalan && totalGagal > 0 && totalSelesai === 0 }"
                    :style="{ width: persenHalus + '%' }"
                ></span>
                <span v-if="adaBerjalan" class="ant__kilau"></span>
            </div>

            <!-- ══ ISI ══ -->
            <div v-show="!lipat" class="ant__isi">
                <article v-for="s in sesiTampil" :key="s.id" class="ant-row" :class="{ 'is-buka': buka === s.id }">
                    <!-- Yang disebut adalah NAMA PROGRAM, bukan kode gelombang.
                         "JDW-0017" tidak berarti apa-apa bagi orang yang baru
                         saja menekan Generate untuk sebuah program; nama
                         programnya justru satu-satunya hal yang ia kenali.
                         Kodenya tetap ada — di tooltip, untuk penelusuran. -->
                    <button
                        type="button" class="ant-row__head"
                        :title="`${s.kode}${s.aktivitas ? ' · ' + s.aktivitas : ''}`"
                        @click="toggleBuka(s)"
                    >
                        <span class="ant-row__ico" :class="kelasStatus(s)">
                            <i class="bi" :class="ikonStatus(s)"></i>
                        </span>
                        <span class="ant-row__in">
                            <span class="ant-row__ttl">
                                <b>{{ namaSesi(s) }}</b>
                                <em v-if="s.aktivitas">{{ s.aktivitas }}</em>
                            </span>
                            <span class="ant-row__meta">
                                <b>{{ s.selesai }}</b> dari {{ s.total }} kandidat
                                <template v-if="s.gagal"> · <i class="is-gagal">{{ s.gagal }} gagal</i></template>
                            </span>
                            <span class="ant-row__rail">
                                <span class="ant-row__fill" :style="{ width: halus(s) + '%' }"></span>
                            </span>
                        </span>
                        <span class="ant-row__persen">{{ Math.round(halus(s)) }}%</span>
                        <i class="bi ant-row__caret" :class="buka === s.id ? 'bi-chevron-up' : 'bi-chevron-down'"></i>
                    </button>

                    <!-- Daftar orang — diambil HANYA saat barisnya dibuka.
                         Menariknya tiap denyut berarti memindahkan lima ratus
                         baris tiap dua detik demi menggerakkan satu bilah. -->
                    <div v-if="buka === s.id" class="ant-row__body">
                        <div v-if="memuatOrang" class="ant-kosong"><i class="bi bi-arrow-repeat ant-putar"></i> Memuat daftar…</div>
                        <template v-else>
                            <div class="ant-tabs">
                                <button
                                    v-for="t in tabOrang" :key="t.kode"
                                    type="button" class="ant-tab" :class="{ 'is-on': tabAktif === t.kode }"
                                    @click="tabAktif = t.kode"
                                >
                                    {{ t.label }} <span>{{ t.jumlah }}</span>
                                </button>
                            </div>

                            <div v-if="!orangTampil.length" class="ant-kosong">Tidak ada kandidat pada keadaan ini.</div>

                            <div v-else class="ant-orang">
                                <div v-for="o in orangTampil" :key="o.id" class="ant-o" :class="'is-' + keadaan(o).toLowerCase()">
                                    <span class="ant-o__dot"><i class="bi" :class="ikonKeadaan(o)"></i></span>
                                    <span class="ant-o__in">
                                        <b>{{ o.nama }}</b>
                                        <em v-if="keadaan(o) === 'GAGAL'" :title="o.pesanError">{{ o.pesanError || 'Gagal tanpa keterangan' }}</em>
                                        <em v-else-if="keadaan(o) === 'SELESAI'">Token terbit</em>
                                        <em v-else>Menunggu giliran…</em>
                                    </span>
                                    <button
                                        v-if="keadaan(o) === 'GAGAL'"
                                        type="button" class="ant-o__ulang" :disabled="ulangId === o.id"
                                        :title="`Coba lagi untuk ${o.nama} saja`"
                                        :onClick="ulangId === o.id ? null : () => ulangOrang(s, o)"
                                    >
                                        <i class="bi" :class="ulangId === o.id ? 'bi-arrow-repeat ant-putar' : 'bi-arrow-clockwise'"></i>
                                    </button>
                                </div>
                            </div>

                            <button
                                v-if="jumlahGagal > 1"
                                type="button" class="ant-semua" :disabled="ulangId === 'SEMUA'"
                                @click="ulangSemuaGagal(s)"
                            >
                                <i class="bi" :class="ulangId === 'SEMUA' ? 'bi-arrow-repeat ant-putar' : 'bi-arrow-clockwise'"></i>
                                Coba lagi {{ jumlahGagal }} yang gagal
                            </button>
                        </template>
                    </div>
                </article>
            </div>
        </section>
    </transition>
</template>

<script>
import axios from 'axios';

const CFG = { headers: { Accept: 'application/json' } };

/** Denyut saat ada pekerjaan berjalan, dan saat semuanya sudah diam. */
const DETAK_SIBUK = 2500;
const DETAK_SANTAI = 15000;

/**
 * Ingatan panel yang melewati muat ulang halaman.
 *
 * Yang disimpan HANYA sikap admin terhadap panel — terlipat atau tidak, dan
 * gelombang mana yang sudah ia tutup. Angkanya tidak pernah disimpan: itu
 * selalu dibaca ulang dari server, supaya panel tak pernah menampilkan
 * kemajuan basi dari kunjungan sebelumnya.
 */
const SIMPAN = 'pjd.antrean.v1';

/** Ingatan yang menumpuk tanpa batas akan jadi sampah; yang lama dibuang. */
const BATAS_TUTUP = 60;

function bacaSimpanan() {
    try {
        const v = JSON.parse(localStorage.getItem(SIMPAN) || '{}');

        return {
            lipat: v.lipat === true,
            ditutup: Array.isArray(v.ditutup) ? v.ditutup.filter((x) => typeof x === 'string') : [],
        };
    } catch (e) {
        // Mode privat, kuota penuh, atau isi yang rusak — panel tetap harus
        // muncul. Ingatan adalah kenyamanan, bukan syarat.
        return { lipat: false, ditutup: [] };
    }
}

export default {
    name: 'PanelAntrean',
    props: {
        /**
         * Gelombang yang WAJIB ikut dipantau meski statusnya belum sempat
         * berubah — id penjadwalan yang baru saja dibuat di tab ini. Tanpa ini,
         * panel baru muncul pada denyut berikutnya dan admin sempat mengira
         * tombol Generate-nya tidak bekerja.
         */
        ids: { type: Array, default: () => [] },
    },
    emits: ['selesai', 'tuntas'],
    data() {
        const ingat = bacaSimpanan();

        return {
            sesi: [],
            lipat: ingat.lipat,
            buka: '',
            orang: [],
            memuatOrang: false,
            tabAktif: 'GAGAL',
            ulangId: '',
            timer: null,
            rafId: null,
            // Kemajuan yang DITAMPILKAN, per id gelombang. Selalu mengejar
            // angka sebenarnya, tidak pernah melompat — lihat animasi().
            tampilPersen: {},
            persenHalus: 0,
            // Gelombang yang sudah dilaporkan selesai; supaya `selesai` tidak
            // dipancarkan berulang tiap denyut.
            sudahLapor: [],
            // Gelombang yang DITUTUP manual oleh admin — disimpan per id, bukan
            // sebagai satu sakelar. Menutup panel berarti "yang ini sudah saya
            // lihat", dan itu harus bertahan melewati muat ulang halaman; tapi
            // gelombang BARU tetap harus bisa memunculkan panelnya lagi, karena
            // panel yang tinggal diam selamanya setelah sekali ditutup berubah
            // jadi gangguan, bukan bantuan.
            ditutup: ingat.ditutup,
        };
    },
    computed: {
        /** Gelombang yang benar-benar dilihat admin — yang ditutup tidak ikut. */
        sesiTampil() {
            return this.sesi.filter((s) => !this.ditutup.includes(s.id));
        },
        tampil() {
            return this.sesiTampil.length > 0;
        },
        adaBerjalan() {
            return this.sesiTampil.some((s) => s.berjalan);
        },
        /**
         * Masih ada pekerjaan DI MANA PUN, termasuk pada gelombang yang panelnya
         * sudah ditutup. Yang menentukan panel terus berdenyut adalah ini, bukan
         * adaBerjalan: menutup panel tidak boleh membuat halaman berhenti tahu
         * kapan penerbitannya rampung.
         */
        adaKerja() {
            return this.sesi.some((s) => s.berjalan);
        },
        totalPeserta() { return this.sesiTampil.reduce((n, s) => n + s.total, 0); },
        totalSelesai() { return this.sesiTampil.reduce((n, s) => n + s.selesai, 0); },
        totalGagal() { return this.sesiTampil.reduce((n, s) => n + s.gagal, 0); },
        persenTarget() {
            return this.totalPeserta > 0 ? Math.floor((this.totalSelesai * 100) / this.totalPeserta) : 0;
        },
        judul() {
            if (this.adaBerjalan) return 'Menerbitkan token ujian…';
            if (this.totalGagal > 0) return this.totalSelesai > 0 ? 'Selesai sebagian' : 'Penerbitan gagal';

            return 'Token ujian selesai terbit';
        },
        ringkas() {
            const n = this.sesiTampil.length;
            const sesiTxt = n > 1 ? `${n} gelombang · ` : '';
            const gagalTxt = this.totalGagal ? ` · ${this.totalGagal} gagal` : '';

            return `${sesiTxt}${this.totalSelesai} dari ${this.totalPeserta} kandidat${gagalTxt}`;
        },
        ikon() {
            if (this.adaBerjalan) return 'bi-arrow-repeat ant-putar';
            if (this.totalGagal > 0) return 'bi-exclamation-triangle-fill';

            return 'bi-check-lg';
        },
        kelasIkon() {
            if (this.adaBerjalan) return 'is-jalan';

            return this.totalGagal > 0 ? (this.totalSelesai > 0 ? 'is-separuh' : 'is-gagal') : 'is-ok';
        },
        jumlahGagal() {
            return this.orang.filter((o) => this.keadaan(o) === 'GAGAL').length;
        },
        tabOrang() {
            const hit = (k) => this.orang.filter((o) => this.keadaan(o) === k).length;

            return [
                { kode: 'GAGAL', label: 'Gagal', jumlah: hit('GAGAL') },
                { kode: 'MENUNGGU', label: 'Menunggu', jumlah: hit('MENUNGGU') },
                { kode: 'SELESAI', label: 'Selesai', jumlah: hit('SELESAI') },
            ];
        },
        orangTampil() {
            // Dipotong: satu gelombang bisa 500 orang, dan panel selebar 380px
            // tidak dirancang untuk digulir sepanjang itu. Yang gagal — satu-
            // satunya yang menuntut tindakan — hampir tidak pernah sebanyak ini.
            return this.orang.filter((o) => this.keadaan(o) === this.tabAktif).slice(0, 120);
        },
    },
    watch: {
        // Gelombang baru dibuat → panel yang telanjur ditutup dibuka lagi.
        // Id baru memang tidak ada di daftar `ditutup`, jadi ia muncul sendiri;
        // yang perlu dipaksa hanyalah membentangkan panel yang terlipat.
        ids(baru, lama) {
            if ((baru || []).length > (lama || []).length) {
                this.lipat = false;
                this.denyut();
            }
        },
        lipat() { this.simpanSikap(); },
        ditutup() { this.simpanSikap(); },
    },
    mounted() {
        this.denyut();
        this.animasi();
    },
    beforeUnmount() {
        clearTimeout(this.timer);
        cancelAnimationFrame(this.rafId);
    },
    methods: {
        /**
         * Satu denyut: ambil keadaan terbaru, lalu jadwalkan denyut berikutnya.
         *
         * setTimeout berantai, BUKAN setInterval: permintaan yang lambat tidak
         * boleh menumpuk di belakang permintaan berikutnya. Jarak denyutnya pun
         * ikut keadaan — dua setengah detik saat ada yang dikerjakan, lima
         * belas detik saat semuanya sudah diam.
         */
        async denyut() {
            clearTimeout(this.timer);
            try {
                const params = this.ids.length ? { ids: this.ids.join(',') } : {};
                const res = await axios.get('/api/v1/penjadwalan/progres', { params, ...CFG });
                const data = res.data?.result?.data || [];
                this.serap(data);
            } catch (e) {
                // Diam. Panel yang meneriakkan galat jaringan tiap dua setengah
                // detik jauh lebih mengganggu daripada bilah yang berhenti
                // bergerak sesaat.
            }
            const jeda = this.adaKerja ? DETAK_SIBUK : DETAK_SANTAI;
            // Berhenti total kalau memang tidak ada apa-apa lagi yang berubah:
            // tidak ada yang dikerjakan DAN tidak ada yang ditampilkan.
            if (this.adaKerja || this.tampil) this.timer = setTimeout(() => this.denyut(), jeda);
        },
        serap(data) {
            const sebelum = new Map(this.sesi.map((s) => [s.id, s]));
            const adaKerjaSebelum = this.adaKerja;
            this.sesi = data;

            data.forEach((s) => {
                const lama = sebelum.get(s.id);
                // Baru selesai pada denyut ini → beri tahu halaman supaya
                // daftarnya menyegarkan diri, sekali saja.
                if (lama && lama.berjalan && !s.berjalan && !this.sudahLapor.includes(s.id)) {
                    this.sudahLapor.push(s.id);
                    this.$emit('selesai', s);
                }
                if (this.tampilPersen[s.id] === undefined) {
                    // Gelombang yang sudah jalan sebelum panel dibuka tidak
                    // perlu dianimasikan dari nol — itu berbohong tentang apa
                    // yang baru saja terjadi.
                    this.tampilPersen = { ...this.tampilPersen, [s.id]: s.persen };
                }
            });

            // SELURUH pekerjaan baru saja rampung pada denyut ini — bukan satu
            // gelombang, melainkan yang terakhir dari semuanya. Inilah saat yang
            // tepat menyuruh halaman menarik datanya lagi: sebelum ini, angka di
            // belakang panel masih menunjukkan keadaan sebelum Generate ditekan,
            // dan admin tidak punya cara membedakan "belum jalan" dari "sudah
            // jalan tapi layarnya belum diperbarui".
            //
            // Menyegarkan HANYA di sini, bukan tiap gelombang selesai: dengan
            // beberapa gelombang sekaligus, yang kedua akan menarik ulang daftar
            // tepat saat yang pertama masih menggambar hasilnya.
            if (adaKerjaSebelum && !this.adaKerja) this.$emit('tuntas');

            // Daftar orang yang sedang terbuka ikut disegarkan.
            if (this.buka && data.some((s) => s.id === this.buka)) this.muatOrang(this.buka, true);
        },
        /**
         * Nama yang disebut di layar. Program lebih dulu — itu yang dikenali
         * admin. Kode gelombang hanya dipakai bila programnya memang tak
         * terbaca, supaya barisnya tidak pernah kosong.
         */
        namaSesi(s) {
            return s.program || s.kode || '—';
        },
        simpanSikap() {
            try {
                localStorage.setItem(SIMPAN, JSON.stringify({
                    lipat: this.lipat,
                    ditutup: this.ditutup.slice(-BATAS_TUTUP),
                }));
            } catch (e) { /* mode privat atau kuota penuh — panel tetap jalan */ }
        },
        /**
         * Bilah yang MENGEJAR, bukan melompat.
         *
         * Server melapor tiap dua setengah detik. Tanpa penghalusan ini, bilah
         * berdiri diam lalu meloncat sepuluh persen sekaligus — dan lompatan
         * itu terbaca sebagai layar yang macet lalu tersentak, bukan sebagai
         * pekerjaan yang berjalan mulus.
         *
         * Pendekatannya kejar-mendekat (`beda * 0,1` tiap bingkai): cepat saat
         * jaraknya jauh, melambat sendiri saat hampir sampai — tanpa perlu tahu
         * kapan laporan berikutnya datang.
         */
        animasi() {
            const kejar = (kini, tuju) => {
                const beda = tuju - kini;
                if (Math.abs(beda) < 0.35) return tuju;
                // Mundur (mis. setelah coba-lagi menambah total) langsung saja:
                // bilah yang menyusut perlahan terbaca seperti kerusakan.
                return beda < 0 ? tuju : kini + beda * 0.1;
            };

            const langkah = () => {
                const peta = { ...this.tampilPersen };
                let berubah = false;
                this.sesi.forEach((s) => {
                    const kini = peta[s.id] ?? 0;
                    const baru = kejar(kini, s.persen);
                    if (baru !== kini) { peta[s.id] = baru; berubah = true; }
                });
                if (berubah) this.tampilPersen = peta;

                const halus = kejar(this.persenHalus, this.persenTarget);
                if (halus !== this.persenHalus) this.persenHalus = halus;

                this.rafId = requestAnimationFrame(langkah);
            };

            this.rafId = requestAnimationFrame(langkah);
        },
        halus(s) {
            return this.tampilPersen[s.id] ?? s.persen;
        },
        /** Keadaan satu orang, diturunkan dari kolom yang memang sudah ada. */
        keadaan(o) {
            if (o.shortToken) return 'SELESAI';

            return o.pesanError ? 'GAGAL' : 'MENUNGGU';
        },
        ikonKeadaan(o) {
            const k = this.keadaan(o);

            return k === 'SELESAI' ? 'bi-check-lg' : (k === 'GAGAL' ? 'bi-exclamation-lg' : 'bi-hourglass-split');
        },
        kelasStatus(s) {
            if (s.berjalan) return 'is-jalan';

            return s.gagal > 0 ? (s.selesai > 0 ? 'is-separuh' : 'is-gagal') : 'is-ok';
        },
        ikonStatus(s) {
            if (s.berjalan) return 'bi-arrow-repeat ant-putar';

            return s.gagal > 0 ? 'bi-exclamation-triangle-fill' : 'bi-check-lg';
        },
        toggleBuka(s) {
            if (this.buka === s.id) { this.buka = ''; this.orang = []; return; }
            this.buka = s.id;
            // Yang dibuka orang saat menekan baris hampir selalu "siapa yang
            // gagal" — kecuali memang tak ada yang gagal.
            this.tabAktif = s.gagal > 0 ? 'GAGAL' : (s.berjalan ? 'MENUNGGU' : 'SELESAI');
            this.muatOrang(s.id);
        },
        async muatOrang(id, diam = false) {
            if (!diam) { this.memuatOrang = true; this.orang = []; }
            try {
                const res = await axios.get(`/api/v1/penjadwalan/${id}/peserta`, CFG);
                this.orang = res.data?.result?.data || res.data?.result || [];
            } catch (e) {
                if (!diam) this.orang = [];
            } finally {
                this.memuatOrang = false;
            }
        },
        async ulangOrang(s, o) {
            if (this.ulangId) return;
            this.ulangId = o.id;
            try {
                await axios.post(`/api/v1/penjadwalan/peserta/${o.id}/ulang`, {}, CFG);
                // Tandai di layar seketika, tanpa menunggu denyut berikutnya —
                // tombol yang tidak berubah apa pun setelah ditekan akan
                // ditekan lagi.
                this.orang = this.orang.map((x) => (x.id === o.id ? { ...x, pesanError: null } : x));
                this.denyut();
            } catch (e) {
                const p = e.response?.data?.message;
                if (p) this.orang = this.orang.map((x) => (x.id === o.id ? { ...x, pesanError: p } : x));
            } finally {
                this.ulangId = '';
            }
        },
        /**
         * Coba lagi SELURUH yang gagal di gelombang ini.
         *
         * Memakai jalur per-orang berulang, bukan ulang() se-gelombang: yang
         * kedua juga menyapu peserta yang masih MENUNGGU — orang yang jobnya
         * memang belum sampai giliran — dan mengirim mereka lebih awal justru
         * bisa membuat dua job bekerja pada baris yang sama.
         */
        async ulangSemuaGagal(s) {
            if (this.ulangId) return;
            this.ulangId = 'SEMUA';
            const gagal = this.orang.filter((o) => this.keadaan(o) === 'GAGAL');
            try {
                for (const o of gagal) {
                    // Berurutan, bukan serentak: sepuluh permintaan bersamaan
                    // ke jalur yang sama hanya memperbesar peluang bentrok di
                    // sisi CAT — dan yang ditunggu admin toh antreannya, bukan
                    // permintaan ini.
                    await axios.post(`/api/v1/penjadwalan/peserta/${o.id}/ulang`, {}, CFG).catch(() => null);
                }
                this.orang = this.orang.map((o) => (this.keadaan(o) === 'GAGAL' ? { ...o, pesanError: null } : o));
                this.denyut();
            } finally {
                this.ulangId = '';
            }
        },
        /**
         * Menutup = menandai gelombang yang SEDANG TERLIHAT sebagai sudah
         * dilihat. Denyutnya sengaja TIDAK dihentikan: penerbitannya masih
         * berjalan di Cloud Tasks, dan halaman di belakang panel tetap berhak
         * disegarkan saat pekerjaannya rampung.
         */
        tutup() {
            const dilihat = this.sesiTampil.map((s) => s.id);
            this.ditutup = [...new Set([...this.ditutup, ...dilihat])].slice(-BATAS_TUTUP);
            this.buka = '';
            this.orang = [];
        },
    },
};
</script>

<style scoped>
/* ══ PANEL ══
   z-index 1080: di atas isi halaman & sidebar (1030), DI BAWAH topeng modal
   (1200) — panel yang menutupi modal konfirmasi akan menghalangi keputusan
   yang jauh lebih penting daripada bilah kemajuan. */
.ant {
    position: fixed; right: 20px; bottom: 20px; z-index: 1080;
    width: 380px; max-width: calc(100vw - 32px);
    background: #fff; border: 1px solid #e2e5f0; border-radius: 14px;
    box-shadow: 0 18px 44px rgba(15, 23, 42, .18), 0 2px 6px rgba(15, 23, 42, .06);
    overflow: hidden; display: flex; flex-direction: column;
}
.ant__head { display: flex; align-items: center; gap: 10px; padding: 11px 12px; cursor: pointer; background: #1e1b4b; }
.ant__ico { flex: none; width: 28px; height: 28px; border-radius: 8px; display: grid; place-items: center; font-size: 13px; color: #fff; }
.ant__ico.is-jalan { background: linear-gradient(135deg, #8b5cf6, #6366f1); }
.ant__ico.is-ok { background: linear-gradient(135deg, #34d399, #10b981); }
.ant__ico.is-separuh { background: linear-gradient(135deg, #fbbf24, #f59e0b); }
.ant__ico.is-gagal { background: linear-gradient(135deg, #f87171, #ef4444); }
.ant__ttl { flex: 1; min-width: 0; }
.ant__ttl b { display: block; font-size: 12.5px; font-weight: 800; color: #fff; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.ant__ttl small { display: block; font-size: 11px; color: #a5b4fc; margin-top: 1px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.ant__btn { appearance: none; flex: none; width: 26px; height: 26px; border-radius: 7px; border: none; background: transparent; color: #c7d2fe; cursor: pointer; display: grid; place-items: center; font-size: 11px; transition: background .16s, color .16s; }
.ant__btn:hover { background: rgba(255, 255, 255, .12); color: #fff; }

/* Bilah keseluruhan — setebal 3px dan menempel di kepala, jadi ia tetap
   terlihat saat panelnya dilipat. */
.ant__rail { position: relative; height: 3px; background: #312e81; overflow: hidden; }
.ant__fill { display: block; height: 100%; background: linear-gradient(90deg, #a78bfa, #6366f1); transition: none; }
.ant__fill.is-gagal { background: linear-gradient(90deg, #fca5a5, #ef4444); }
/* Kilau berjalan: penanda bahwa pekerjaannya HIDUP, bukan bilah yang membeku.
   Hanya muncul saat memang ada yang sedang dikerjakan. */
.ant__kilau { position: absolute; inset: 0; background: linear-gradient(90deg, transparent, rgba(255, 255, 255, .55), transparent); transform: translateX(-100%); animation: antKilau 1.5s ease-in-out infinite; }
@keyframes antKilau { to { transform: translateX(100%); } }

.ant__isi { max-height: 62vh; overflow-y: auto; overscroll-behavior: contain; background: #fbfbfe; }
.ant__isi::-webkit-scrollbar { width: 6px; }
.ant__isi::-webkit-scrollbar-thumb { background: #d9def0; border-radius: 999px; }

/* ══ BARIS GELOMBANG ══ */
.ant-row { border-bottom: 1px solid #eef0f7; }
.ant-row:last-child { border-bottom: none; }
.ant-row.is-buka { background: #fff; }
.ant-row__head { appearance: none; width: 100%; display: flex; align-items: center; gap: 10px; padding: 10px 12px; border: none; background: transparent; font: inherit; text-align: left; cursor: pointer; transition: background .14s; }
.ant-row__head:hover { background: #f5f3ff; }
.ant-row__ico { flex: none; width: 24px; height: 24px; border-radius: 7px; display: grid; place-items: center; font-size: 11px; color: #fff; }
.ant-row__ico.is-jalan { background: linear-gradient(135deg, #8b5cf6, #6366f1); }
.ant-row__ico.is-ok { background: linear-gradient(135deg, #34d399, #10b981); }
.ant-row__ico.is-separuh { background: linear-gradient(135deg, #fbbf24, #f59e0b); }
.ant-row__ico.is-gagal { background: linear-gradient(135deg, #f87171, #ef4444); }
.ant-row__in { flex: 1; min-width: 0; }
.ant-row__ttl { display: flex; align-items: baseline; gap: 7px; min-width: 0; }
/* Nama program bisa panjang — dialah yang menyusut dan berelipsis, bukan
   nama aktivitas di sebelahnya. Aktivitas dibatasi 45% supaya "Psikotes
   Dasar Angkatan II" tidak menggusur nama programnya sampai hilang. */
.ant-row__ttl b { font-size: 12px; font-weight: 800; color: #1e293b; flex: 1 1 auto; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.ant-row__ttl em { font-style: normal; font-size: 11px; color: #94a3b8; flex: 0 1 auto; max-width: 45%; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.ant-row__meta { display: block; font-size: 10.5px; color: #64748b; margin-top: 2px; }
.ant-row__meta b { color: #4338ca; font-weight: 800; }
.ant-row__meta .is-gagal { font-style: normal; color: #dc2626; font-weight: 700; }
.ant-row__rail { display: block; height: 4px; border-radius: 99px; background: #eef0f7; margin-top: 6px; overflow: hidden; }
.ant-row__fill { display: block; height: 100%; border-radius: 99px; background: linear-gradient(90deg, #8b5cf6, #6366f1); }
.ant-row__persen { flex: none; font-size: 11.5px; font-weight: 800; color: #4338ca; font-variant-numeric: tabular-nums; min-width: 34px; text-align: right; }
.ant-row__caret { flex: none; font-size: 10px; color: #a2a9ba; }

/* ══ DAFTAR ORANG ══ */
.ant-row__body { padding: 0 12px 11px; }
.ant-tabs { display: flex; gap: 5px; margin-bottom: 8px; }
.ant-tab { appearance: none; flex: 1; border: 1px solid #e6e9f3; background: #fff; border-radius: 8px; padding: 5px 7px; cursor: pointer; font: inherit; font-size: 10.5px; font-weight: 800; color: #64748b; transition: all .15s; }
.ant-tab:hover { border-color: #c7d2fe; color: #4f46e5; }
.ant-tab.is-on { border-color: transparent; background: linear-gradient(135deg, #8b5cf6, #6366f1); color: #fff; }
.ant-tab span { opacity: .75; }
.ant-orang { max-height: 220px; overflow-y: auto; overscroll-behavior: contain; display: flex; flex-direction: column; gap: 5px; }
.ant-orang::-webkit-scrollbar { width: 5px; }
.ant-orang::-webkit-scrollbar-thumb { background: #dfe3f0; border-radius: 999px; }
.ant-o { display: flex; align-items: center; gap: 8px; padding: 6px 8px; border-radius: 9px; background: #fbfbfe; border: 1px solid #eef0f7; }
.ant-o.is-gagal { background: #fef2f2; border-color: #fecaca; }
.ant-o.is-selesai { background: #f0fdf4; border-color: #bbf7d0; }
.ant-o__dot { flex: none; width: 18px; height: 18px; border-radius: 5px; display: grid; place-items: center; font-size: 9px; color: #fff; background: #94a3b8; }
.ant-o.is-gagal .ant-o__dot { background: #ef4444; }
.ant-o.is-selesai .ant-o__dot { background: #10b981; }
.ant-o.is-menunggu .ant-o__dot { background: #cbd5e1; color: #475569; }
.ant-o__in { flex: 1; min-width: 0; }
.ant-o__in b { display: block; font-size: 11.5px; font-weight: 700; color: #1e293b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
/* Sebab kegagalan dipotong DUA BARIS, bukan satu: kalimat galat dari CAT
   kerap menyebut tindakan yang harus diambil di ujungnya, dan elipsis pada
   baris pertama justru membuang bagian yang berguna. */
.ant-o__in em { display: -webkit-box; -webkit-line-clamp: 2; line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; font-style: normal; font-size: 10px; color: #94a3b8; margin-top: 1px; line-height: 1.4; }
.ant-o.is-gagal .ant-o__in em { color: #b91c1c; }
.ant-o__ulang { appearance: none; flex: none; width: 24px; height: 24px; border-radius: 7px; border: 1px solid #fecaca; background: #fff; color: #dc2626; cursor: pointer; display: grid; place-items: center; font-size: 11px; transition: all .15s; }
.ant-o__ulang:hover:not(:disabled) { background: #dc2626; color: #fff; border-color: #dc2626; }
.ant-o__ulang:disabled { opacity: .5; cursor: not-allowed; }
.ant-semua { appearance: none; width: 100%; margin-top: 8px; display: inline-flex; align-items: center; justify-content: center; gap: 6px; padding: 7px; border-radius: 9px; border: 1px solid #fecaca; background: #fff; color: #b91c1c; cursor: pointer; font: inherit; font-size: 11.5px; font-weight: 800; transition: all .15s; }
.ant-semua:hover:not(:disabled) { background: #fef2f2; border-color: #f87171; }
.ant-semua:disabled { opacity: .55; cursor: not-allowed; }
.ant-kosong { padding: 16px 8px; text-align: center; font-size: 11.5px; color: #a2a9ba; }

.ant-putar { display: inline-block; animation: antPutar 1s linear infinite; }
@keyframes antPutar { to { transform: rotate(360deg); } }

.ant-pop-enter-active, .ant-pop-leave-active { transition: opacity .22s, transform .22s cubic-bezier(.22, 1, .36, 1); }
.ant-pop-enter-from, .ant-pop-leave-to { opacity: 0; transform: translateY(14px) scale(.97); }

@media (max-width: 560px) {
    .ant { right: 12px; left: 12px; bottom: 12px; width: auto; }
    .ant__isi { max-height: 55vh; }
}
/* Gerak dimatikan bagi yang memintanya. Kilau berjalan & putaran ikon adalah
   hiasan; angkanya sendiri tetap terbaca tanpa keduanya. */
@media (prefers-reduced-motion: reduce) {
    .ant__kilau { animation: none; opacity: 0; }
    .ant-putar { animation: none; }
}
</style>
