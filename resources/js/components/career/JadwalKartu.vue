<!-- WEB CAREER — KARTU JADWAL AKTIVITAS (POV KANDIDAT).
     Menjawab tiga hal yang dicari kandidat begitu diundang: KAPAN, DI MANA
     (atau lewat tautan apa), dan APA yang perlu disiapkan — plus, bila tipenya
     punya ketentuan biaya (MCU), siapa yang membayar.

     Dipakai di dua tempat pada halaman detail lamaran — kartu tahap ber-ujian
     online dan kartu tahap yang ditangani tim. Dulu markupnya disalin di
     keduanya, dan setiap perbaikan hanya sampai ke salah satunya.

     BERRENTANG TANGGAL (MCU vendor / mandiri): tidak ada jam janji temu. Yang
     dijanjikan RENTANG pemeriksaannya; ujungnya adalah batas. "Tempat" VENDOR
     adalah nama vendor yang diketik tim beserta cabang yang melayani (alamat &
     peta hanya bila tim menempelkannya); "tempat" MANDIRI adalah kalimat dari
     master mode jadwal — klinik/RS pilihan kandidat. Keduanya membawa SURAT
     PENGANTAR yang harus ditunjukkan saat pemeriksaan.

     SURAT PENGANTAR — selalu PDF, jadi bisa langsung dibaca di layar:
       LAYAR LEBAR (≥ 1200px): kartu dibelah seperti pembaca berkas di worklist
         admin — kiri rinciannya, kanan panel pratinjau yang langsung membuka
         surat pertama. Panelnya bisa disembunyikan; pilihan itu diingat.
       PONSEL & TABLET: tetap daftar. Mengetuk satu surat memunculkan dua
         pilihan, Lihat dan Unduh — PDF tidak pernah terbuka sendiri di layar
         kecil, tempat pembaca PDF bawaan peramban sering tidak ada. -->
<template>
    <div class="jdw" :class="[j.batasWaktu ? 'is-batas' : (j.daring ? 'is-daring' : 'is-luring'), { 'is-split': split }]">
        <!-- KAPAN — kepala kartu. Judul aktivitas TIDAK diulang di sini: kepala
             blok aktivitas tepat di atasnya sudah menyebutnya (masukan user
             1 Okt 2026 — kartu terasa penuh). Ubin tanggal, hari, jam, dan
             jaraknya dari sekarang ("Besok", "Hari ini · 3 jam lagi") terbaca
             sekilas, termasuk di ponsel. -->
        <div class="jdw__hero">
            <span class="jdw__tgl" :class="{ 'is-ikon': !ubin }" aria-hidden="true">
                <template v-if="ubin"><small>{{ ubin.bulan }}</small><b>{{ ubin.tanggal }}</b></template>
                <i v-else class="bi" :class="ikon"></i>
            </span>
            <div class="jdw__kapan">
                <div class="jdw__eyebrow">{{ alis }}</div>
                <b class="jdw__hari">{{ hariTeks }}</b>
                <div v-if="jamTeks || relatif" class="jdw__jam">
                    <span v-if="jamTeks">{{ jamTeks }}</span>
                    <span v-if="relatif" class="jdw__relatif" :class="`is-${relatif.nada}`">
                        <i class="bi" :class="relatif.ikon"></i> {{ relatif.teks }}
                    </span>
                </div>
            </div>
        </div>

        <!-- DI MANA — beserta tindakannya di baris yang sama: "Gabung
             pertemuan" untuk daring, "Buka peta" untuk tatap muka & vendor.
             Dulu tombol Gabung berdiri sendiri di dasar kartu, jauh dari
             tautannya. Patokan yang diketik rekruter ("Gedung B lantai 3")
             tetap ikut terbaca di bawah nama tempatnya. -->
        <div class="jdw__lokasi">
            <span class="jdw__lokasi-ic"><i class="bi" :class="lokasi.ikon"></i></span>
            <div class="jdw__lokasi-isi">
                <span class="jdw__lbl">{{ lokasi.label }}</span>
                <a v-if="lokasi.tautan" :href="lokasi.tautan" target="_blank" rel="noopener" class="jdw__tautan" :title="lokasi.tautan">{{ lokasi.tautanTeks }}</a>
                <b v-else :class="{ 'is-pelan': lokasi.pelan }">{{ lokasi.nama }}</b>
                <small v-for="(r, i) in lokasi.baris" :key="i" :class="{ 'is-pelan': r.pelan }">
                    <i v-if="r.ikon" class="bi" :class="r.ikon"></i>
                    <em v-if="r.label">{{ r.label }}:</em>
                    {{ r.teks }}
                </small>
            </div>
            <a v-if="lokasi.aksi" :href="lokasi.aksi.url" target="_blank" rel="noopener" class="jdw__aksi" :class="{ 'is-utama': lokasi.aksi.utama }">
                <i class="bi" :class="lokasi.aksi.ikon"></i> {{ lokasi.aksi.label }}
            </a>
        </div>

        <div v-if="adaBadan" class="jdw__badan">
            <div class="jdw__kiri">
                <!-- KONFIRMASI KEHADIRAN — dijawab LANGSUNG di kartu ini, tanpa
                     pindah halaman (permintaan user 1 Okt 2026). Letaknya SESUDAH
                     kapan & di mana: kandidat perlu tahu waktunya dulu sebelum
                     bisa menjawab "bisa hadir?". Komponennya sama dengan halaman
                     tautan dari email — aturan, pilihan, dan kalimatnya mustahil
                     berbeda. "Tidak melanjutkan" ada di kaki kartu. -->
                <div v-if="konfirmasi && konfirmasi.jawab" class="jdw__jawab">
                    <KonfirmasiJawab
                        :bahan="konfirmasi.jawab"
                        mode="kartu"
                        :judul="{ aktivitas: j.label, waktuTeks: waktu }"
                        :mundur="false"
                        :menunggu="menungguData"
                        @berubah="$emit('konfirmasi-berubah', $event)"
                    />
                </div>
                <div v-else-if="konfirmasi" class="jdw__konf" :style="{ '--nada': konfirmasi.warna || '#94a3b8' }">
                    <span class="jdw__konf-ic"><i class="bi" :class="konfirmasi.ikon || 'bi-hourglass-split'"></i></span>
                    <div class="jdw__konf-isi">
                        <b>{{ konfirmasi.kalimat || 'Mohon konfirmasi kehadiranmu.' }}</b>
                    </div>
                </div>

                <!-- BATAS SUDAH LEWAT — dikatakan apa adanya, dengan jalan keluarnya. -->
                <p v-if="j.lewat" class="jdw__lewat">
                    <i class="bi bi-alarm-fill"></i>
                    Rentang tanggalnya sudah lewat. Segera hubungi tim rekrutmen bila kamu belum sempat melakukannya.
                </p>

                <!-- INSTRUKSI / CATATAN diberi judulnya sendiri. Tanpa itu kalimat seperti
                     "bawa KTP, bawa KK" muncul begitu saja di dasar kartu — kandidat
                     tidak tahu itu pesan dari tim, syarat masuk, atau keterangan sistem.
                     Yang berformat dirender lewat KontenAman (daftar-izin yang sama
                     dengan penyaring server). -->
                <div v-if="j.catatanHtml || j.catatan" class="jdw__cat">
                    <span class="jdw__cat-lbl">
                        <i class="bi bi-info-circle-fill"></i>
                        {{ j.batasWaktu ? 'INSTRUKSI' : 'CATATAN DARI TIM REKRUTMEN' }}
                    </span>
                    <KontenAman v-if="j.catatanHtml" class="jdw__cat-isi" :html="j.catatanHtml" ringkas />
                    <p v-else>{{ j.catatan }}</p>
                </div>

                <div v-if="j.vendor && tempat.catatanHtml" class="jdw__cat is-cabang">
                    <span class="jdw__cat-lbl"><i class="bi bi-signpost-split-fill"></i> CABANG / LOKASI YANG MELAYANI</span>
                    <KontenAman class="jdw__cat-isi" :html="tempat.catatanHtml" ringkas />
                </div>

                <!-- SURAT PENGANTAR — wajib ditunjukkan di loket vendor / klinik. Bisa
                     lebih dari satu. Layar lebar: memilih baris = membukanya di panel
                     pratinjau, tombol Unduh di tiap baris. Layar kecil: mengetuk baris
                     membuka pilihan Lihat / Unduh di bawahnya. -->
                <div v-if="surat.length" class="jdw__surat">
                    <div class="jdw__surat-head">
                        <span class="jdw__surat-ico"><i class="bi bi-file-earmark-text-fill"></i></span>
                        <div style="min-width: 0; flex: 1">
                            <b>{{ labelSurat }}<template v-if="surat.length > 1"> ({{ surat.length }})</template></b>
                            <small>{{ lebar ? 'Baca di samping, unduh, lalu tunjukkan saat pemeriksaan.' : 'Ketuk surat untuk melihat atau mengunduhnya, lalu tunjukkan saat pemeriksaan.' }}</small>
                        </div>
                        <button v-if="lebar && !panelBuka" type="button" class="jdw__surat-tampil" @click="bukaPanel()">
                            <i class="bi bi-layout-sidebar-reverse"></i> Tampilkan pratinjau
                        </button>
                    </div>
                    <div class="jdw__surat-list">
                        <div
                            v-for="(s, i) in surat" :key="i"
                            class="jdw__surat-row"
                            :class="{ 'is-on': split && i === aktif, 'is-buka': !lebar && pilihan === i }"
                        >
                            <button
                                type="button" class="jdw__surat-item"
                                :aria-expanded="lebar ? undefined : pilihan === i"
                                :aria-current="split && i === aktif ? 'true' : undefined"
                                :title="lebar ? 'Lihat ' + s.nama : s.nama"
                                @click="klikSurat(i)"
                            >
                                <i class="bi bi-file-earmark-pdf-fill"></i>
                                <span class="jdw__surat-nama">{{ s.nama }}</span>
                                <span v-if="s.ukuran" class="jdw__surat-uk">{{ teksUkuran(s.ukuran) }}</span>
                                <template v-if="lebar">
                                    <span v-if="split && i === aktif" class="jdw__surat-lihat is-on"><i class="bi bi-eye-fill"></i> Dilihat</span>
                                    <span v-else class="jdw__surat-lihat"><i class="bi bi-eye"></i> Lihat</span>
                                </template>
                                <i v-else class="bi jdw__surat-caret" :class="pilihan === i ? 'bi-chevron-up' : 'bi-chevron-down'"></i>
                            </button>
                            <a v-if="lebar" :href="urlUnduh(s)" class="jdw__surat-btn" :title="'Unduh ' + s.nama">
                                <i class="bi bi-download"></i> Unduh
                            </a>
                            <div v-if="!lebar && pilihan === i" class="jdw__surat-opsi">
                                <a :href="s.url" target="_blank" rel="noopener" class="is-lihat"><i class="bi bi-eye"></i> Lihat</a>
                                <a :href="urlUnduh(s)" class="is-unduh"><i class="bi bi-download"></i> Unduh</a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- BIAYA — aturan tipe aktivitas ini, dari master. Selalu tampil di
                     tempat yang sama: kandidat harus tahu siapa yang membayar SEBELUM
                     berangkat, bukan sesudah uangnya keluar. -->
                <div v-if="j.biaya" class="jdw__biaya">
                    <span class="jdw__cat-lbl"><i class="bi bi-cash-coin"></i> BIAYA</span>
                    <p>{{ j.biaya }}</p>
                </div>

                <!-- PETA tempat tatap muka — nama, alamat, dan tombol "Buka peta"
                     sudah di baris DI MANA; di sini cukup petanya. -->
                <div v-if="!j.daring && !j.batasWaktu && peta.embed" class="jdw__peta">
                    <iframe
                        :src="peta.embed"
                        :title="'Peta ' + (tempat.nama || j.lokasi || 'lokasi kegiatan')"
                        loading="lazy"
                        referrerpolicy="no-referrer-when-downgrade"
                    ></iframe>
                </div>
            </div>

            <!-- ═══ PANEL PRATINJAU SURAT (layar lebar) ═══
                 Bentuknya meniru pembaca berkas di worklist admin: bilah judul
                 (nama surat + Unduh + tab baru + sembunyikan), jalur surat bila
                 lebih dari satu, lalu bidang tampil gelap yang mengambil sisa
                 tingginya. Surat pertama langsung terbuka — tidak ada klik yang
                 perlu dilakukan untuk sekadar membacanya. -->
            <aside v-if="split && suratAktif" class="jdw-pv" :aria-label="'Pratinjau ' + labelSurat">
                <div class="jdw-pv__head">
                    <span class="jdw-pv__headico"><i class="bi bi-file-earmark-pdf-fill"></i></span>
                    <span class="jdw-pv__headtxt">
                        <b :title="suratAktif.nama">{{ suratAktif.nama }}</b>
                        <em>
                            {{ labelSurat }}<template v-if="surat.length > 1"> · {{ aktif + 1 }} dari {{ surat.length }}</template>
                            · PDF<template v-if="suratAktif.ukuran"> · {{ teksUkuran(suratAktif.ukuran) }}</template>
                        </em>
                    </span>
                    <a :href="urlUnduh(suratAktif)" class="jdw-pv__unduh" :title="'Unduh ' + suratAktif.nama">
                        <i class="bi bi-download"></i> Unduh
                    </a>
                    <a :href="suratAktif.url" target="_blank" rel="noopener" class="jdw-pv__hbtn" title="Buka di tab baru">
                        <i class="bi bi-box-arrow-up-right"></i>
                    </a>
                    <button type="button" class="jdw-pv__hbtn" title="Sembunyikan pratinjau" @click="tutupPanel()">
                        <i class="bi bi-chevron-double-right"></i>
                    </button>
                </div>

                <div v-if="surat.length > 1" class="jdw-pv__strip">
                    <button
                        v-for="(s, i) in surat" :key="i"
                        type="button" class="jdw-pv__sitem" :class="{ 'is-on': i === aktif }"
                        :title="s.nama" @click="lihat(i)"
                    >
                        <span class="jdw-pv__sthumb"><i class="bi bi-file-earmark-pdf"></i><em>PDF</em></span>
                        <span class="jdw-pv__sname">{{ s.nama }}</span>
                    </button>
                </div>

                <div ref="pvView" class="jdw-pv__view">
                    <!-- pdf.js: tiap halaman digambar ke kanvas selebar panel di
                         sini (disusun lewat kode — lihat susunHalaman). -->
                    <div v-show="mesin === 'pdfjs'" ref="pvLembar" class="jdw-pv__lembar"></div>
                    <!-- Cadangan bila pdf.js tidak bisa berjalan di peramban ini:
                         pembaca PDF bawaannya. Tidak disembunyikan selama memuat —
                         bingkai yang disembunyikan terbaca selebar nol oleh pembaca
                         PDF, dan lembarnya terbuka kecil di tengah. -->
                    <iframe
                        v-if="mesin === 'iframe'"
                        :src="srcIframe" :title="suratAktif.nama"
                        class="jdw-pv__pdf" @load="selesaiMuat()"
                    ></iframe>
                    <div v-if="muat" class="jdw-pv__state">
                        <span class="jdw-pv__spin"></span>
                        <span>Memuat surat…</span>
                    </div>
                </div>
            </aside>
        </div>

        <!-- KAKI — "Tidak melanjutkan seleksi": di dasar kartu, tidak di tengah
             (masukan user 1 Okt 2026). Tetap ada walau jawabannya terkunci. -->
        <KonfirmasiMundur
            v-if="konfirmasi && konfirmasi.jawab"
            :bahan="konfirmasi.jawab"
            mode="kartu"
            @berubah="mundurBerubah"
        />
    </div>
</template>

<script>
import KontenAman from '@career/KontenAman.vue';
import KonfirmasiJawab from '@career/KonfirmasiJawab.vue';
import KonfirmasiMundur from '@career/KonfirmasiMundur.vue';
import { sisaWaktu, urai } from '@utils/career/konfirmasi';
import { bukaPdf, gambarHalaman, rasioHalaman } from '@utils/career/pratinjauPdf';

/** Mulai lebar ini kartu dibelah dengan panel pratinjau; di bawahnya daftar. */
const LAYAR_LEBAR = '(min-width: 1200px)';
/** Panel pratinjau yang disembunyikan kandidat tetap tersembunyi di kunjungan berikutnya. */
const KUNCI_PANEL = 'jdw:pratinjau-surat';
/** Surat pengantar hanya beberapa halaman; berkas yang jauh lebih tebal cukup dibuka di tab baru. */
const MAKS_HALAMAN = 40;
/** pdf.js yang belum menggambar apa pun selama ini dianggap gagal → pembaca bawaan peramban. */
const BATAS_PDFJS_MS = 15000;
/** Janji temu tanpa jam selesai dianggap berlangsung selama ini sejak mulai. */
const DURASI_CADANGAN_MS = 3 * 3600 * 1000;

/** 'https://meet.google.com/abc-defg/' → 'meet.google.com/abc-defg' — terbaca utuh di ponsel. */
function tautanRingkas(url) {
    return String(url || '').trim().replace(/^https?:\/\//i, '').replace(/^www\./i, '').replace(/\/+$/, '');
}

function layarLebar() {
    try {
        return window.matchMedia(LAYAR_LEBAR).matches;
    } catch (e) {
        return false;
    }
}

function panelTersimpan() {
    try {
        return window.localStorage.getItem(KUNCI_PANEL) !== 'tutup';
    } catch (e) {
        return true;
    }
}

export default {
    name: 'JadwalKartu',
    components: { KontenAman, KonfirmasiJawab, KonfirmasiMundur },
    // Kandidat menjawab di kartu ini → induk memuat ulang data lamarannya.
    emits: ['konfirmasi-berubah'],
    props: {
        /**
         * Satu jadwal aktivitas: { label, daring, mulai, selesai, link, lokasi,
         * tempat, catatan, catatanHtml, batasWaktu, batasTeks, rentangTeks,
         * tempatKalimat, vendor, surat: [{ nama, ukuran, url }], suratLabel,
         * modeNama, lewat, biaya }.
         */
        jadwal: { type: Object, required: true },
        /**
         * Konfirmasi kehadiran (null = tidak diminta): { status, kalimat, warna,
         * ikon, batas, batasTeks, bolehJawab, jawab } — `jawab` adalah bahan
         * KonfirmasiJawab (lihat KonfirmasiJadwal::untukKandidat()).
         */
        konfirmasi: { type: Object, default: null },
    },
    data() {
        return {
            lebar: layarLebar(),
            panelBuka: panelTersimpan(),
            // Surat yang sedang dibuka di panel pratinjau.
            aktif: 0,
            // Layar kecil: baris surat yang pilihan Lihat/Unduh-nya terbuka.
            pilihan: null,
            muat: true,
            // 'pdfjs' (bawaan) | 'iframe' (cadangan: pembaca PDF peramban).
            mesin: 'pdfjs',
            // Jam dinding untuk "Besok" / "Hari ini · 3 jam lagi" — berdetak per menit.
            kini: Date.now(),
            // Pernyataan dari kaki kartu terkirim, data terbarunya sedang dimuat
            // induk — panel jawaban ikut menahan tombolnya supaya tidak diklik lagi.
            menungguData: false,
        };
    },
    created() {
        // Di LUAR data: objek pdf.js memakai field privat (#…) yang rusak bila
        // dibungkus proxy reaktif Vue, dan pengamat/pewaktu tidak perlu reaktif.
        this.pv = { gen: 0, tugas: null, doc: null, lebar: 0, io: null, ro: null, rTimer: null, timer: null, mq: null };
        this.detak = null;
        this.tmTunggu = null;
    },
    computed: {
        j() { return this.jadwal || {}; },
        surat() { return Array.isArray(this.j.surat) ? this.j.surat : []; },
        labelSurat() { return this.j.suratLabel || 'Surat pengantar'; },
        /** Kartu dibelah: layar lebar, ada surat, dan panelnya tidak disembunyikan. */
        split() { return this.lebar && this.panelBuka && this.surat.length > 0; },
        suratAktif() { return this.surat[this.aktif] || this.surat[0] || null; },
        /** Sumber cadangan (pembaca PDF peramban) — hanya bila pdf.js gagal. */
        srcIframe() { return this.suratAktif ? `${this.suratAktif.url}#view=FitH` : 'about:blank'; },
        /** Berubah tiap kali surat yang harus dipratinjau berganti. */
        kunciPratinjau() { return this.split && this.suratAktif ? `${this.aktif}|${this.suratAktif.url}` : ''; },
        /** Lokasi dari Master Lokasi — kosong bila rekruter mengetik bebas. */
        tempat() { return this.j.tempat || {}; },
        ikon() {
            if (this.j.batasWaktu) return this.j.vendor ? 'bi-hospital' : 'bi-person-walking';

            return this.j.daring ? 'bi-camera-video-fill' : 'bi-geo-alt-fill';
        },
        alis() {
            if (this.j.batasWaktu) return `INSTRUKSI · ${String(this.j.modeNama || 'MANDIRI').toUpperCase()}`;

            return this.j.daring ? 'DIJADWALKAN · DARING' : 'DIJADWALKAN · TATAP MUKA';
        },
        /** Ubin kalender kepala kartu — null untuk rentang tanggal (MCU) / jadwal tanpa waktu. */
        ubin() {
            const d = this.j.batasWaktu ? null : urai(this.j.mulai);
            if (!d) return null;

            return {
                bulan: d.toLocaleDateString('id-ID', { month: 'short' }).replace('.', '').toUpperCase(),
                tanggal: String(d.getDate()).padStart(2, '0'),
            };
        },
        /** Baris utama kepala kartu: "Jumat, 2 Oktober 2026" — atau rentang pemeriksaannya. */
        hariTeks() {
            if (this.j.batasWaktu) return this.j.rentangTeks || this.j.batasTeks || '—';
            const d = urai(this.j.mulai);

            return d ? d.toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }) : '—';
        },
        /** "09.00 – 23.59 WIB" */
        jamTeks() {
            if (this.j.batasWaktu || !urai(this.j.mulai)) return '';
            const jam = (v) => {
                const d = urai(v);

                return d ? `${String(d.getHours()).padStart(2, '0')}.${String(d.getMinutes()).padStart(2, '0')}` : '';
            };

            return `${jam(this.j.mulai)}${this.j.selesai ? ' – ' + jam(this.j.selesai) : ''} WIB`;
        },
        /**
         * Jarak jadwal dari sekarang, dengan bahasa sehari-hari. Hari dihitung
         * per TANGGAL kalender, bukan per 24 jam: jadwal besok pukul 08.00 yang
         * dilihat malam ini tetap "Besok", bukan "Hari ini".
         */
        relatif() {
            if (this.j.batasWaktu) {
                return this.j.lewat ? { teks: 'Rentang sudah lewat', nada: 'lewat', ikon: 'bi-clock-history' } : null;
            }
            const mulai = urai(this.j.mulai);
            if (!mulai) return null;
            const kini = new Date(this.kini);
            const akhir = urai(this.j.selesai) || new Date(mulai.getTime() + DURASI_CADANGAN_MS);

            if (kini >= mulai && kini <= akhir) return { teks: 'Sedang berlangsung', nada: 'kini', ikon: 'bi-broadcast' };
            if (kini > akhir) return { teks: 'Sudah lewat', nada: 'lewat', ikon: 'bi-clock-history' };

            const hari = Math.round(
                (new Date(mulai.getFullYear(), mulai.getMonth(), mulai.getDate())
                    - new Date(kini.getFullYear(), kini.getMonth(), kini.getDate())) / 86400000,
            );
            if (hari <= 0) return { teks: `Hari ini · ${sisaWaktu(mulai, kini)}`, nada: 'segera', ikon: 'bi-alarm' };
            if (hari === 1) return { teks: 'Besok', nada: 'dekat', ikon: 'bi-calendar-event' };
            if (hari === 2) return { teks: 'Lusa', nada: 'dekat', ikon: 'bi-calendar-event' };

            return { teks: `${hari} hari lagi`, nada: 'nanti', ikon: 'bi-calendar3' };
        },
        /**
         * Baris DI MANA: label, isi, baris keterangan, dan tindakannya.
         *
         * Daring → tautan + "Gabung pertemuan". Tatap muka → nama tempat,
         * alamat, wilayah, telepon, patokan + "Buka peta" (titik Master
         * Lokasi, atau teks lokasi apa adanya — lihat peta()). Tempat yang
         * diketik tim tanpa alamat berkata jujur "Alamat menyusul". Vendor MCU
         * → nama + alamat/peta HANYA bila tim menempelkannya (vendor
         * berjaringan punya banyak cabang). Mandiri → kalimat master mode.
         */
        lokasi() {
            const j = this.j;
            const t = this.tempat;

            if (j.batasWaktu && j.vendor) {
                return {
                    label: 'VENDOR',
                    ikon: 'bi-hospital',
                    nama: t.nama || '—',
                    baris: t.alamat
                        ? [{ teks: t.alamat }]
                        : (t.mapsUrl ? [{ teks: 'Lokasinya dari tim rekrutmen — buka peta untuk melihat titiknya.', pelan: true }] : []),
                    aksi: t.mapsUrl ? { url: t.mapsUrl, label: 'Buka peta', ikon: 'bi-map' } : null,
                };
            }
            if (j.batasWaktu) {
                return { label: 'TEMPAT PEMERIKSAAN', ikon: 'bi-person-walking', nama: j.tempatKalimat || 'Tempat pilihanmu', baris: [], aksi: null };
            }
            if (j.daring) {
                return j.link
                    ? {
                        label: 'TAUTAN PERTEMUAN',
                        ikon: 'bi-camera-video-fill',
                        tautan: j.link,
                        tautanTeks: tautanRingkas(j.link),
                        baris: [],
                        // Menonjol hanya di hari-H / saat berlangsung — sebelum itu tombol
                        // utama kartu adalah jawaban kehadiran; dua tombol utama berebut.
                        aksi: { url: j.link, label: 'Gabung pertemuan', ikon: 'bi-camera-video-fill', utama: ['segera', 'kini'].includes(this.relatif?.nada) },
                    }
                    : { label: 'TAUTAN PERTEMUAN', ikon: 'bi-camera-video-fill', nama: 'Tautan menyusul dari tim rekrutmen.', pelan: true, baris: [], aksi: null };
            }

            const baris = [];
            if (t.alamat) baris.push({ teks: t.alamat });
            else if (t.lepas && t.nama) baris.push({ teks: 'Alamat menyusul dari tim rekrutmen.', pelan: true });
            if (this.wilayah) baris.push({ teks: this.wilayah });
            if (t.kontakTelp) baris.push({ ikon: 'bi-telephone-fill', teks: t.kontakTelp });
            // PATOKAN yang diketik rekruter ("Gedung B lantai 3, temui resepsionis")
            // — dulu tertelan begitu lokasinya dipilih dari master.
            if (j.lokasi && t.nama) baris.push({ label: 'Patokan', teks: j.lokasi });

            return {
                label: 'TEMPAT',
                ikon: 'bi-geo-alt-fill',
                nama: t.nama || j.lokasi || '—',
                baris,
                aksi: this.peta.url ? { url: this.peta.url, label: 'Buka peta', ikon: 'bi-map' } : null,
            };
        },
        /** Ada isi di bawah baris DI MANA — tanpa ini kartu berekor ruang kosong. */
        adaBadan() {
            const j = this.j;

            return !!(this.konfirmasi || j.lewat || j.catatanHtml || j.catatan || (j.vendor && this.tempat.catatanHtml)
                || this.surat.length || j.biaya || (!j.daring && !j.batasWaktu && this.peta.embed));
        },
        /**
         * Kota/provinsi/kode pos — HANYA yang belum tertulis di alamat.
         *
         * Alamat lengkap biasanya sudah memuat kota dan provinsinya, sehingga
         * menampilkan keduanya apa adanya menghasilkan dua baris yang isinya
         * sama ("Banyuasin, Sumatera Selatan" lalu "Banyuasin · Sumatera
         * Selatan") — terbaca seperti data yang salah tersimpan dua kali.
         */
        wilayah() {
            const alamat = (this.tempat.alamat || '').toLowerCase();

            return [this.tempat.kota, this.tempat.provinsi, this.tempat.kodePos]
                .filter((v) => v && !alamat.includes(String(v).toLowerCase()))
                .join(' · ');
        },
        /**
         * Sumber peta: titik dari Master Lokasi lebih dulu (sudah dibentuk
         * server berikut koordinatnya), lalu teks lokasi apa adanya sebagai
         * cadangan. Jadwal lama yang lokasinya diketik bebas tetap dapat peta,
         * bukan sekadar sebaris alamat mati.
         */
        peta() {
            if (this.tempat.mapsEmbed) {
                return { embed: this.tempat.mapsEmbed, url: this.tempat.mapsUrl };
            }

            // Tempat yang diketik tim TIDAK dipetakan — termasuk lewat cadangan
            // di bawah. Tanpa penjagaan ini, patokan seperti "Lantai 2, poli
            // MCU" akan dicarikan petanya sendiri dan menghasilkan pin acak.
            if (this.tempat.lepas) return { embed: null, url: null };

            const teks = (this.j.lokasi || '').trim();
            if (!teks) return { embed: null, url: null };

            const q = encodeURIComponent(teks);

            return {
                embed: `https://www.google.com/maps?q=${q}&output=embed`,
                url: `https://www.google.com/maps/search/?api=1&query=${q}`,
            };
        },
        waktu() {
            if (!this.j.mulai) return '—';
            const d = new Date(String(this.j.mulai).replace(' ', 'T'));
            if (Number.isNaN(d.getTime())) return '—';

            const hari = d.toLocaleDateString('id-ID', { weekday: 'long', day: '2-digit', month: 'short', year: 'numeric' });
            const jam = (v) => new Date(String(v).replace(' ', 'T'))
                .toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }).replace(':', '.');

            return `${hari} · ${jam(this.j.mulai)}${this.j.selesai ? ' – ' + jam(this.j.selesai) : ''} WIB`;
        },
    },
    watch: {
        // Data konfirmasi terbaru tiba sesudah pernyataan dari kaki kartu.
        konfirmasi() {
            this.menungguData = false;
            clearTimeout(this.tmTunggu);
        },
        // Jadwal diubah tim (surat ditambah/dicabut) → mulai lagi dari surat pertama.
        surat(baru, lama) {
            if ((baru || []).length !== (lama || []).length || this.aktif >= (baru || []).length) {
                this.aktif = 0;
                this.pilihan = null;
            }
        },
        // Surat lain dipilih, atau panel baru muncul → gambar ulang pratinjaunya.
        // Panel yang hilang (disembunyikan / layar mengecil) → lepas semuanya.
        kunciPratinjau(baru) {
            if (baru) {
                this.$nextTick(() => this.tampilkan());
            } else {
                this.bersihkanPratinjau();
                this.lepasPengamatUkuran();
            }
        },
    },
    mounted() {
        try {
            this.pv.mq = window.matchMedia(LAYAR_LEBAR);
            this.pv.mq.addEventListener ? this.pv.mq.addEventListener('change', this.ubahLebar) : this.pv.mq.addListener(this.ubahLebar);
        } catch (e) {
            this.pv.mq = null;
        }
        if (this.kunciPratinjau) this.$nextTick(() => this.tampilkan());
        this.detak = setInterval(() => { this.kini = Date.now(); }, 60000);
    },
    beforeUnmount() {
        clearInterval(this.detak);
        clearTimeout(this.tmTunggu);
        this.bersihkanPratinjau();
        this.lepasPengamatUkuran();
        const mq = this.pv.mq;
        if (mq) mq.removeEventListener ? mq.removeEventListener('change', this.ubahLebar) : mq.removeListener(this.ubahLebar);
    },
    methods: {
        /**
         * "Tidak melanjutkan" dari kaki kartu terkirim → induk memuat ulang.
         * Sampai data barunya tiba, panel jawaban di atas ikut menahan
         * tombolnya (cadangan 20 detik bila muat ulang gagal).
         */
        mundurBerubah(pesan) {
            if (pesan) {
                this.menungguData = true;
                clearTimeout(this.tmTunggu);
                this.tmTunggu = setTimeout(() => { this.menungguData = false; }, 20000);
            }
            this.$emit('konfirmasi-berubah', pesan);
        },
        ubahLebar(e) {
            this.lebar = !!e.matches;
            this.pilihan = null;
        },
        /**
         * Layar lebar: buka surat itu di panel (sekaligus memunculkan panelnya
         * bila tadi disembunyikan). Layar kecil: buka/tutup pilihan Lihat &
         * Unduh di bawah barisnya — PDF tidak dibuka tanpa diminta.
         */
        klikSurat(i) {
            if (!this.lebar) {
                this.pilihan = this.pilihan === i ? null : i;

                return;
            }
            if (!this.panelBuka) this.bukaPanel();
            this.lihat(i);
        },
        lihat(i) {
            if (i >= 0 && i < this.surat.length) this.aktif = i;
        },
        /**
         * Pratinjau surat aktif: pdf.js lebih dulu; bila pustakanya gagal dimuat,
         * PDF-nya gagal dibaca, atau tak satu halaman pun tergambar dalam
         * BATAS_PDFJS_MS, pindah ke pembaca PDF bawaan peramban.
         *
         * `gen` menandai pemanggilan terbaru: kandidat yang berpindah surat
         * sebelum surat sebelumnya selesai dimuat tidak boleh melihat surat
         * lama muncul belakangan menimpa yang baru.
         */
        async tampilkan() {
            if (!this.kunciPratinjau) return;
            this.bersihkanPratinjau();
            const gen = ++this.pv.gen;
            this.mesin = 'pdfjs';
            this.muat = true;
            this.pv.timer = setTimeout(() => { if (gen === this.pv.gen && this.muat) this.pakaiCadangan(); }, BATAS_PDFJS_MS);
            this.pasangPengamatUkuran();

            try {
                const tugas = await bukaPdf(this.urlIsi(this.suratAktif));
                if (gen !== this.pv.gen) {
                    tugas.destroy();

                    return;
                }
                this.pv.tugas = tugas;
                this.pv.doc = await tugas.promise;
                if (gen !== this.pv.gen) return;
                await this.susunHalaman(gen);
            } catch (e) {
                if (gen === this.pv.gen) this.pakaiCadangan();
            }
        },
        /**
         * Tempat tiap halaman disiapkan lebih dulu (tinggi menurut rasio halaman
         * pertama), lalu digambar saat mendekati layar — berkas 30 halaman tidak
         * menggambar 30 kanvas sekaligus. Halaman pertama langsung digambar.
         */
        async susunHalaman(gen) {
            const wadah = this.$refs.pvLembar;
            const doc = this.pv.doc;
            if (!wadah || !doc) return;

            const lebar = Math.max(200, wadah.clientWidth - 28);
            this.pv.lebar = lebar;
            const rasio = await rasioHalaman(doc, 1);
            if (gen !== this.pv.gen) return;

            if (this.pv.io) this.pv.io.disconnect();
            wadah.innerHTML = '';
            const jumlah = Math.min(doc.numPages, MAKS_HALAMAN);
            const kotak = [];
            for (let i = 1; i <= jumlah; i++) {
                const k = document.createElement('div');
                k.className = 'jdw-pv__hal';
                k.dataset.nomor = String(i);
                k.style.width = `${lebar}px`;
                k.style.height = `${Math.round(lebar * rasio)}px`;
                k.appendChild(document.createElement('canvas'));
                wadah.appendChild(k);
                kotak.push(k);
            }
            if (doc.numPages > jumlah) {
                const catatan = document.createElement('p');
                catatan.className = 'jdw-pv__lanjut';
                catatan.textContent = `Menampilkan ${jumlah} dari ${doc.numPages} halaman — buka di tab baru untuk membaca selengkapnya.`;
                wadah.appendChild(catatan);
            }
            wadah.scrollTop = 0;

            this.pv.io = new IntersectionObserver((entri) => {
                entri.forEach((e) => { if (e.isIntersecting) this.gambar(e.target, gen); });
            }, { root: wadah, rootMargin: '600px 0px' });
            kotak.forEach((k) => this.pv.io.observe(k));

            await this.gambar(kotak[0], gen);
            if (gen === this.pv.gen) this.selesaiMuat();
        },
        async gambar(kotak, gen) {
            const doc = this.pv.doc;
            const lebar = this.pv.lebar;
            if (!kotak || !doc || gen !== this.pv.gen || !kotak.isConnected || kotak.dataset.lebar === String(lebar)) return;
            kotak.dataset.lebar = String(lebar);
            try {
                const ukuran = await gambarHalaman(doc, Number(kotak.dataset.nomor), kotak.querySelector('canvas'), lebar);
                if (kotak.isConnected) kotak.style.height = `${ukuran.tinggi}px`;
            } catch (e) {
                // Satu halaman gagal digambar (mis. dibatalkan karena berpindah
                // surat) — tandai agar dicoba lagi saat terlihat berikutnya.
                delete kotak.dataset.lebar;
            }
        },
        /** Lebar panel berubah (jendela diubah) → susun ulang selebar yang baru. */
        pasangPengamatUkuran() {
            if (this.pv.ro || typeof ResizeObserver === 'undefined' || !this.$refs.pvView) return;
            this.pv.ro = new ResizeObserver(() => {
                clearTimeout(this.pv.rTimer);
                this.pv.rTimer = setTimeout(() => {
                    const wadah = this.$refs.pvLembar;
                    if (this.mesin !== 'pdfjs' || !this.pv.doc || !wadah) return;
                    if (Math.abs(Math.max(200, wadah.clientWidth - 28) - this.pv.lebar) > 8) this.susunHalaman(this.pv.gen);
                }, 200);
            });
            this.pv.ro.observe(this.$refs.pvView);
        },
        lepasPengamatUkuran() {
            clearTimeout(this.pv.rTimer);
            if (this.pv.ro) this.pv.ro.disconnect();
            this.pv.ro = null;
        },
        /** Batalkan pemuatan & lepaskan dokumen yang sedang dipratinjau. */
        bersihkanPratinjau() {
            this.pv.gen++;
            clearTimeout(this.pv.timer);
            if (this.pv.io) this.pv.io.disconnect();
            this.pv.io = null;
            if (this.pv.tugas) this.pv.tugas.destroy();
            this.pv.tugas = null;
            this.pv.doc = null;
            if (this.$refs.pvLembar) this.$refs.pvLembar.innerHTML = '';
        },
        /** pdf.js tak bisa berjalan di sini → pembaca PDF bawaan peramban. */
        pakaiCadangan() {
            this.bersihkanPratinjau();
            this.mesin = 'iframe';
            this.muat = true;
            // Sebagian pembaca PDF tidak pernah mengirim `load` — penanda
            // memuat yang berputar selamanya lebih buruk daripada PDF yang
            // muncul sedikit terlambat.
            this.pv.timer = setTimeout(() => { this.muat = false; }, 10000);
        },
        selesaiMuat() {
            clearTimeout(this.pv.timer);
            this.muat = false;
        },
        /** Isi PDF langsung dari server ini — lihat SuratJadwal::sajikanIsi. */
        urlIsi(s) {
            const url = String(s?.url || '');

            return url + (url.includes('?') ? '&' : '?') + 'isi=1';
        },
        bukaPanel() {
            this.panelBuka = true;
            try { window.localStorage.removeItem(KUNCI_PANEL); } catch (e) { /* penyimpanan diblokir — cukup untuk kunjungan ini */ }
        },
        tutupPanel() {
            this.panelBuka = false;
            try { window.localStorage.setItem(KUNCI_PANEL, 'tutup'); } catch (e) { /* penyimpanan diblokir — cukup untuk kunjungan ini */ }
        },
        /** Tautan unduh: rute yang sama, dengan Content-Disposition attachment dari server. */
        urlUnduh(s) {
            const url = String(s?.url || '');

            return url ? url + (url.includes('?') ? '&' : '?') + 'unduh=1' : '#';
        },
        /** "1,2 MB" / "350 KB" — sama dengan SuratJadwal::teksUkuran. */
        teksUkuran(byte) {
            const n = Number(byte) || 0;
            if (n >= 1048576) return `${(Math.round((n / 1048576) * 10) / 10).toString().replace('.', ',')} MB`;

            return `${Math.max(1, Math.round(n / 1024))} KB`;
        },
    },
};
</script>

<style scoped>
/* ═══ KARTU JADWAL ═══
   Tiga lapis: KAPAN (kepala berwarna mode), DI MANA (+ tindakannya), lalu
   badan (konfirmasi, catatan, surat, biaya, peta) dan kaki (tidak
   melanjutkan). Tata letak mengikuti lebar KARTU lewat container query —
   kartu yang sama tampil di ponsel 300px dan desktop 1200px. */
.jdw {
    container: jdw / inline-size;
    border-radius: 18px;
    border: 1px solid #e3e6f3;
    background: #fff;
    overflow: hidden;
    box-shadow: 0 1px 2px rgba(15, 23, 42, .04), 0 18px 36px -30px rgba(30, 27, 75, .5);
}

.jdw__hero { display: flex; align-items: center; gap: 14px; padding: 16px 18px 15px; }
.jdw.is-daring .jdw__hero { background: linear-gradient(135deg, rgba(99, 102, 241, .11), rgba(139, 92, 246, .05) 55%, rgba(255, 255, 255, 0)); }
.jdw.is-luring .jdw__hero { background: linear-gradient(135deg, rgba(245, 158, 11, .14), rgba(245, 158, 11, .05) 55%, rgba(255, 255, 255, 0)); }
.jdw.is-batas .jdw__hero { background: linear-gradient(135deg, rgba(13, 148, 136, .12), rgba(13, 148, 136, .04) 55%, rgba(255, 255, 255, 0)); }

/* Ubin tanggal — lembar kalender: bulan di pita atas, tanggal besar di bawahnya. */
.jdw__tgl { flex: none; width: 56px; border-radius: 14px; overflow: hidden; text-align: center; background: #fff; border: 1px solid rgba(99, 102, 241, .18); box-shadow: 0 10px 20px -12px rgba(30, 27, 75, .5); }
.jdw__tgl small { display: block; padding: 3px 0 2px; font-size: 10px; font-weight: 800; letter-spacing: .12em; color: #fff; background: linear-gradient(140deg, #818cf8, #6366f1); }
.jdw__tgl b { display: block; padding: 2px 0 5px; font-size: 23px; font-weight: 800; line-height: 1.15; letter-spacing: -.02em; color: #1e1b4b; }
.jdw.is-luring .jdw__tgl { border-color: rgba(245, 158, 11, .32); }
.jdw.is-luring .jdw__tgl small { background: linear-gradient(140deg, #fbbf24, #f59e0b); }
.jdw.is-luring .jdw__tgl b { color: #451a03; }
.jdw__tgl.is-ikon { height: 56px; display: grid; place-items: center; border: 0; color: #fff; font-size: 22px; background: linear-gradient(140deg, #818cf8, #6366f1); }
.jdw.is-luring .jdw__tgl.is-ikon { background: linear-gradient(140deg, #fbbf24, #f59e0b); }
.jdw.is-batas .jdw__tgl.is-ikon { background: linear-gradient(140deg, #2dd4bf, #0d9488); }

.jdw__kapan { flex: 1; min-width: 0; }
.jdw__eyebrow { font-size: 10.5px; font-weight: 800; letter-spacing: .1em; color: #8e95a9; }
.jdw__hari { display: block; margin-top: 2px; font-size: 16.5px; font-weight: 800; line-height: 1.3; letter-spacing: -.01em; color: #0f172a; }
.jdw__jam { display: flex; flex-wrap: wrap; align-items: center; gap: 5px 10px; margin-top: 4px; font-size: 13px; font-weight: 700; color: #475569; }
.jdw__relatif { display: inline-flex; align-items: center; gap: 5px; padding: 3px 9px; border-radius: 999px; font-size: 11.5px; font-weight: 800; line-height: 1.45; background: #f1f5f9; color: #475569; }
.jdw__relatif.is-dekat { background: #eef2ff; color: #4338ca; }
.jdw__relatif.is-segera { background: #fff7ed; color: #c2410c; }
.jdw__relatif.is-kini { background: #dcfce7; color: #15803d; }
.jdw__relatif.is-lewat { background: #f1f5f9; color: #64748b; }

/* DI MANA — isi di kiri, tindakannya di kanan (kartu sempit: selebar kartu). */
.jdw__lokasi { display: flex; flex-wrap: wrap; align-items: flex-start; gap: 10px 12px; padding: 13px 18px; border-top: 1px solid #eef0f7; }
.jdw__lokasi-ic { flex: none; width: 38px; height: 38px; border-radius: 12px; display: grid; place-items: center; font-size: 16px; color: #4f46e5; background: rgba(99, 102, 241, .1); }
.jdw.is-luring .jdw__lokasi-ic { color: #dc2626; background: rgba(239, 68, 68, .09); }
.jdw.is-batas .jdw__lokasi-ic { color: #0f766e; background: rgba(13, 148, 136, .1); }
.jdw__lokasi-isi { flex: 1 1 160px; min-width: 0; align-self: center; }
.jdw__lbl { display: block; font-size: 10.5px; font-weight: 800; letter-spacing: .08em; color: #94a3b8; }
.jdw__lokasi-isi b { display: block; margin-top: 2px; font-size: 13.5px; font-weight: 800; line-height: 1.45; color: #1e293b; overflow-wrap: anywhere; }
.jdw__lokasi-isi b.is-pelan { font-weight: 600; font-style: italic; color: #64748b; }
.jdw__tautan { display: block; margin-top: 2px; font-size: 13.5px; font-weight: 700; color: #4f46e5; text-decoration: none; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.jdw__tautan:hover { text-decoration: underline; }
.jdw__lokasi-isi small { display: block; margin-top: 3px; font-size: 12px; line-height: 1.5; color: #64748b; overflow-wrap: anywhere; }
.jdw__lokasi-isi small.is-pelan { font-style: italic; }
.jdw__lokasi-isi small em { font-style: normal; font-weight: 700; color: #475569; }
.jdw__lokasi-isi small .bi { color: #94a3b8; }
.jdw__aksi { flex: none; align-self: center; display: inline-flex; align-items: center; justify-content: center; gap: 7px; min-height: 42px; padding: 9px 16px; border-radius: 12px; font-size: 13px; font-weight: 800; text-decoration: none; color: #4338ca; background: rgba(99, 102, 241, .1); transition: background .15s ease, filter .15s ease; }
.jdw__aksi:hover { background: rgba(99, 102, 241, .17); }
.jdw__aksi:focus-visible { outline: 3px solid #c7d2fe; outline-offset: 2px; }
.jdw__aksi.is-utama { color: #fff; background: linear-gradient(135deg, #818cf8, #6366f1); box-shadow: 0 10px 22px -12px rgba(79, 70, 229, .9); }
.jdw__aksi.is-utama:hover { filter: brightness(1.06); }

.jdw__badan { padding: 0 18px 16px; }
.jdw__jawab { margin-top: 14px; }

.jdw__lewat { display: flex; align-items: flex-start; gap: 8px; margin: 13px 0 0; padding: 10px 12px; border-radius: 11px; background: rgba(239, 68, 68, .07); border: 1px solid rgba(239, 68, 68, .22); font-size: 12.5px; line-height: 1.55; color: #b91c1c; font-weight: 600; }
.jdw__lewat .bi { flex: none; margin-top: 2px; }

.jdw__biaya { margin: 13px 0 0; padding: 10px 13px; border: 1px solid rgba(16, 185, 129, .25); border-left: 3px solid rgba(16, 185, 129, .65); border-radius: 0 12px 12px 0; background: rgba(236, 253, 245, .8); }
.jdw__biaya .jdw__cat-lbl { color: #059669; }
.jdw__biaya p { margin: 5px 0 0; font-size: 12.5px; line-height: 1.6; color: #065f46; font-weight: 600; }

.jdw__peta { margin-top: 14px; border: 1px solid rgba(15, 23, 42, .1); border-radius: 14px; overflow: hidden; background: #f8fafc; }
.jdw__peta iframe { display: block; width: 100%; height: 230px; border: 0; }

.jdw__cat.is-cabang { border-color: rgba(13, 148, 136, .2); border-left-color: rgba(13, 148, 136, .6); }
.jdw__cat.is-cabang .jdw__cat-lbl { color: #0f766e; }
.jdw__surat { margin: 13px 0 0; padding: 11px 13px; border-radius: 12px; background: #fff; border: 1px solid rgba(99, 102, 241, .25); }
.jdw__surat-head { display: flex; align-items: center; gap: 11px; }
.jdw__surat-list { display: flex; flex-direction: column; gap: 6px; margin-top: 10px; }
/* Satu baris surat: tombol pilih (nama) + Unduh; di layar kecil pilihan
   Lihat/Unduh turun ke baris kedua di dalam bingkai yang sama. */
.jdw__surat-row { display: flex; align-items: center; flex-wrap: wrap; gap: 6px 8px; padding: 3px 6px 3px 3px; border-radius: 11px; border: 1px solid #eef0f7; background: #f8fafc; transition: border-color .16s, background .16s, box-shadow .16s; }
.jdw__surat-row:hover { border-color: rgba(99, 102, 241, .35); background: rgba(99, 102, 241, .05); }
.jdw__surat-row.is-on { border-color: #6366f1; background: #f6f5ff; box-shadow: 0 8px 20px -14px rgba(79, 70, 229, .6); }
.jdw__surat-row.is-buka { border-color: rgba(99, 102, 241, .45); background: #fff; }
.jdw__surat-item { appearance: none; border: 0; background: transparent; cursor: pointer; font-family: inherit; text-align: left; color: inherit; flex: 1 1 0; min-width: 0; display: flex; align-items: center; gap: 9px; padding: 7px 7px; border-radius: 9px; }
.jdw__surat-item:focus-visible { outline: 2px solid #818cf8; outline-offset: 1px; }
.jdw__surat-item > .bi-file-earmark-pdf-fill { flex: none; color: #dc2626; }
.jdw__surat-nama { min-width: 0; flex: 1; font-size: 12.5px; font-weight: 700; color: #1e293b; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.jdw__surat-uk { flex: none; font-size: 11px; font-weight: 700; color: #94a3b8; }
.jdw__surat-lihat { flex: none; display: inline-flex; align-items: center; gap: 5px; padding: 4px 9px; border-radius: 8px; font-size: 11px; font-weight: 800; color: #6366f1; background: rgba(99, 102, 241, .08); }
.jdw__surat-lihat.is-on { color: #fff; background: linear-gradient(135deg, #8b5cf6, #6366f1); }
.jdw__surat-caret { flex: none; color: #94a3b8; font-size: 12px; }
.jdw__surat-ico { flex: none; width: 34px; height: 34px; border-radius: 10px; display: grid; place-items: center; font-size: 15px; color: #4f46e5; background: rgba(99, 102, 241, .1); }
.jdw__surat b { display: block; font-size: 13px; font-weight: 800; color: #1e293b; }
.jdw__surat small { display: block; font-size: 11.5px; color: #64748b; margin-top: 1px; }
.jdw__surat-btn { flex: none; display: inline-flex; align-items: center; gap: 6px; padding: 6px 12px; border-radius: 9px; font-size: 12px; font-weight: 800; color: #fff; text-decoration: none; background: linear-gradient(135deg, #818cf8, #6366f1); box-shadow: 0 8px 18px -10px rgba(79, 70, 229, .9); }
.jdw__surat-btn:hover { filter: brightness(1.06); }
.jdw__surat-opsi { flex: 1 1 100%; display: grid; grid-template-columns: 1fr 1fr; gap: 8px; padding: 0 3px 6px 6px; }
.jdw__surat-opsi a { display: inline-flex; align-items: center; justify-content: center; gap: 7px; padding: 10px 12px; border-radius: 10px; font-size: 13px; font-weight: 800; text-decoration: none; }
.jdw__surat-opsi .is-lihat { color: #4f46e5; background: rgba(99, 102, 241, .1); border: 1px solid rgba(99, 102, 241, .25); }
.jdw__surat-opsi .is-unduh { color: #fff; background: linear-gradient(135deg, #818cf8, #6366f1); box-shadow: 0 8px 18px -10px rgba(79, 70, 229, .9); }
.jdw__surat-tampil { flex: none; appearance: none; cursor: pointer; font-family: inherit; display: inline-flex; align-items: center; gap: 6px; padding: 6px 11px; border-radius: 9px; border: 1px solid rgba(99, 102, 241, .3); background: rgba(99, 102, 241, .06); color: #4f46e5; font-size: 11.5px; font-weight: 800; }
.jdw__surat-tampil:hover { background: rgba(99, 102, 241, .12); }

/* ═══ KARTU DIBELAH (layar lebar, ada surat) ═══
   Kiri rincian, kanan panel pratinjau. Panelnya ikut setinggi kolom kiri
   (paling pendek 640px) supaya PDF selalu punya ruang baca yang layak. */
.jdw.is-split .jdw__badan { display: grid; grid-template-columns: minmax(0, 1fr) minmax(420px, 46%); gap: 18px; align-items: stretch; }
.jdw__kiri { min-width: 0; }

/* Panel pratinjau — bentuk & warna pembaca berkas worklist admin. */
.jdw-pv { display: flex; flex-direction: column; min-height: 640px; margin-top: 14px; border-radius: 14px; overflow: hidden; border: 1px solid #e2ddf9; background: #fff; box-shadow: 0 18px 40px -26px rgba(30, 27, 75, .45); }
.jdw-pv__head { flex: none; display: flex; align-items: center; gap: 9px; padding: 11px 10px 11px 13px; border-bottom: 1px solid #eef0f7; background: linear-gradient(135deg, #f7f5ff, #eef2ff); }
.jdw-pv__headico { flex: none; width: 32px; height: 32px; border-radius: 10px; display: flex; align-items: center; justify-content: center; background: linear-gradient(135deg, #8b5cf6, #6366f1); color: #fff; font-size: 15px; box-shadow: 0 8px 18px rgba(99, 102, 241, .26); }
.jdw-pv__headtxt { flex: 1; min-width: 0; }
.jdw-pv__headtxt b { display: block; font-size: 12.5px; font-weight: 800; color: #1e1b4b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.jdw-pv__headtxt em { display: block; font-size: 10.5px; font-style: normal; color: #8b8bb0; margin-top: 1px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.jdw-pv__unduh { flex: none; display: inline-flex; align-items: center; gap: 6px; height: 28px; padding: 0 11px; border-radius: 9px; font-size: 11.5px; font-weight: 800; color: #fff; text-decoration: none; background: linear-gradient(135deg, #818cf8, #6366f1); box-shadow: 0 8px 18px -10px rgba(79, 70, 229, .9); }
.jdw-pv__unduh:hover { filter: brightness(1.06); }
.jdw-pv__hbtn { appearance: none; border: 1px solid #e2ddf9; background: rgba(255, 255, 255, .82); cursor: pointer; font-family: inherit; flex: none; width: 28px; height: 28px; border-radius: 9px; display: flex; align-items: center; justify-content: center; font-size: 11.5px; color: #7c7ca8; text-decoration: none; transition: all .16s; }
.jdw-pv__hbtn:hover { background: #fff; color: #4f46e5; border-color: #c7d2fe; }
.jdw-pv__strip { flex: none; display: flex; gap: 8px; padding: 9px 11px; border-bottom: 1px solid #f1f5f9; background: #fbfbfe; overflow-x: auto; scrollbar-width: thin; }
.jdw-pv__sitem { appearance: none; border: 1px solid #edeaf9; background: #fff; cursor: pointer; font-family: inherit; flex: none; width: 76px; display: flex; flex-direction: column; align-items: center; gap: 4px; padding: 6px 5px; border-radius: 11px; transition: transform .16s cubic-bezier(.22, 1, .36, 1), box-shadow .16s, border-color .16s; }
.jdw-pv__sitem:hover { transform: translateY(-2px); border-color: #c7d2fe; box-shadow: 0 10px 22px rgba(99, 102, 241, .16); }
.jdw-pv__sitem.is-on { border-color: #6366f1; background: #f6f5ff; box-shadow: 0 10px 22px rgba(99, 102, 241, .2); }
.jdw-pv__sthumb { position: relative; width: 100%; height: 46px; border-radius: 8px; display: flex; align-items: center; justify-content: center; background: #eef2ff; border: 1px solid #dfe4fd; color: #4f46e5; font-size: 18px; }
.jdw-pv__sthumb em { position: absolute; right: 3px; bottom: 3px; font-style: normal; font-size: 7.5px; font-weight: 800; letter-spacing: .04em; padding: 1px 4px; border-radius: 4px; background: rgba(30, 27, 49, .72); color: #fff; }
.jdw-pv__sname { width: 100%; font-size: 9px; font-weight: 700; color: #64748b; line-height: 1.25; text-align: center; overflow: hidden; text-overflow: ellipsis; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; }
.jdw-pv__sitem.is-on .jdw-pv__sname { color: #4338ca; font-weight: 800; }
.jdw-pv__view { flex: 1 1 auto; min-height: 0; position: relative; display: flex; align-items: center; justify-content: center; background: #1e1b31; }
.jdw-pv__pdf { position: absolute; inset: 0; width: 100%; height: 100%; border: 0; background: #fff; }
/* Halaman-halaman pdf.js: lembar putih di atas bidang gelap, digulir di dalam
   panel. Lembarnya dibuat lewat kode (tanpa atribut scoped), jadi dijangkau
   lewat :deep dari wadahnya. Padding kiri+kanan 28px = pengurang lebar di
   susunHalaman(). */
.jdw-pv__lembar { position: absolute; inset: 0; overflow-y: auto; overflow-x: hidden; padding: 14px; display: flex; flex-direction: column; align-items: center; gap: 12px; scrollbar-width: thin; scrollbar-color: rgba(255, 255, 255, .28) transparent; }
.jdw-pv__lembar :deep(.jdw-pv__hal) { flex: none; background: #fff; border-radius: 3px; overflow: hidden; box-shadow: 0 12px 30px rgba(0, 0, 0, .45); }
.jdw-pv__lembar :deep(.jdw-pv__hal canvas) { display: block; }
.jdw-pv__lembar :deep(.jdw-pv__lanjut) { flex: none; margin: 2px 0 0; font-size: 11.5px; color: #b9b6d6; text-align: center; }
.jdw-pv__state { position: absolute; inset: 0; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 10px; padding: 24px 18px; font-size: 12px; color: #b9b6d6; background: #1e1b31; }
.jdw-pv__spin { width: 26px; height: 26px; border-radius: 50%; border: 3px solid rgba(255, 255, 255, .18); border-top-color: #a78bfa; animation: jdw-putar .8s linear infinite; }
@keyframes jdw-putar { to { transform: rotate(360deg); } }
@media (prefers-reduced-motion: reduce) { .jdw-pv__spin { animation-duration: 2.4s; } }
.jdw__cat { margin: 13px 0 0; padding: 10px 13px; border: 1px solid rgba(99, 102, 241, .18); border-left: 3px solid rgba(99, 102, 241, .55); border-radius: 0 12px 12px 0; background: rgba(255, 255, 255, .78); }
.jdw__cat-lbl { display: inline-flex; align-items: center; gap: 6px; font-size: 9.5px; font-weight: 800; letter-spacing: .09em; color: #6366f1; }
.jdw__cat p { margin: 5px 0 0; font-size: 12.5px; line-height: 1.6; color: #475569; white-space: pre-line; }
.jdw__cat-isi { margin-top: 5px; font-size: 12.5px; line-height: 1.6; color: #475569; }


/* Konfirmasi kehadiran — warna dari master status. */
.jdw__konf { display: flex; align-items: center; flex-wrap: wrap; gap: 10px 12px; margin-top: 14px; padding: 11px 13px; border-radius: 12px; background: #fff; border: 1px solid #eef0f7; border-left: 4px solid var(--nada); }
.jdw__konf-ic { flex: none; width: 32px; height: 32px; border-radius: 50%; display: grid; place-items: center; color: #fff; background: var(--nada); font-size: 14px; }
.jdw__konf-isi { flex: 1 1 200px; min-width: 0; }
.jdw__konf-isi b { display: block; font-size: 13px; font-weight: 800; color: #1e293b; line-height: 1.45; }
.jdw__konf-isi small { display: block; font-size: 11.5px; color: #64748b; margin-top: 2px; }

/* Kartu sempit (ponsel, atau kolom sempit di layar mana pun): bantalan lebih
   rapat, ubin lebih kecil, tombol tindakan selebar kartu, peta lebih pendek
   supaya isian di bawahnya tetap terlihat. */
@container jdw (max-width: 520px) {
    .jdw__hero { gap: 12px; padding: 14px 14px 13px; }
    .jdw__tgl { width: 50px; }
    .jdw__tgl b { font-size: 20px; }
    .jdw__tgl.is-ikon { height: 50px; font-size: 19px; }
    .jdw__hari { font-size: 15px; }
    .jdw__lokasi { padding: 12px 14px; }
    .jdw__aksi { flex: 1 1 100%; }
    .jdw__badan { padding: 0 14px 14px; }
    .jdw__peta iframe { height: 180px; }
    .jdw__surat-btn { padding: 6px 10px; }
}
</style>
