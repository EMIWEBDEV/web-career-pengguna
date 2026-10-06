<!-- WEB CAREER — JAWAB KONFIRMASI KEHADIRAN (POV KANDIDAT).

     SATU komponen untuk dua pintu, supaya aturan & kalimatnya mustahil berbeda:
       mode="halaman"  halaman tautan dari email (tanpa login) — kartu-kartu besar;
       mode="kartu"    langsung di kartu jadwal Portal Kandidat — ringkas, tanpa
                       pindah halaman (permintaan user 1 Okt 2026: "jangan banyak
                       klik dan redirect").
     Bahannya dari KonfirmasiJadwal::bahanJawab(); url.jawab/url.cabut milik
     pintunya masing-masing (tautan bertanda tangan, atau rute portal ber-sesi).

     KOMITMEN (keputusan user 1 Okt 2026): jawaban pertama bebas sampai jadwal
     mulai; MENGUBAH hanya sekian kali & paling lambat H-N (master tipe tahap).
     Karena itu "Saya akan hadir" meminta satu penegasan, dan kalimat aturannya
     selalu terbaca sebelum menjawab. "Tidak melanjutkan" tidak ikut terkunci.
     Server tetap hakimnya — layar ini hanya menyajikan.

     "TIDAK MELANJUTKAN" dipindah ke KonfirmasiMundur (masukan user 1 Okt 2026:
     tombolnya melayang di tengah kartu dan sulit dipakai). Halaman tautan
     merendernya di ujung komponen ini; kartu portal menaruhnya di KAKI kartu
     jadwal (`:mundur="false"`), sesudah catatan & peta. -->
<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { cabutPermintaanKandidat, galatDari, kirimJawabanKandidat, rona, sisaWaktu, urai } from '@utils/career/konfirmasi';
import KonfirmasiMundur from './KonfirmasiMundur.vue';

const props = defineProps({
    /** { konfirmasi, pilihan, alasan, permintaan, jatah, aturanUsulan, ubah, url } */
    bahan: { type: Object, required: true },
    mode: { type: String, default: 'halaman' },
    /** { aktivitas, waktuTeks } — untuk kalimat penegasan "akan hadir". */
    judul: { type: Object, default: () => ({}) },
    /** false = "tidak melanjutkan" dirender pemanggil di tempat lain (kaki kartu jadwal). */
    mundur: { type: Boolean, default: true },
    /** Pemanggil sedang memuat ulang sesudah jawaban dikirim dari luar komponen ini. */
    menunggu: { type: Boolean, default: false },
});
const emit = defineEmits(['berubah']);

const k = computed(() => props.bahan?.konfirmasi || {});
const status = computed(() => k.value.status || 'MENUNGGU');
const ubah = computed(() => props.bahan?.ubah || {});
const pilihan = computed(() => props.bahan?.pilihan || []);
const jatah = computed(() => props.bahan?.jatah || { maks: 2, terpakai: 0, sisa: 2 });
const aturanUsulan = computed(() => props.bahan?.aturanUsulan || { maks: 3, tanggalMin: '', tanggalMaks: '', bagian: [], catatanMaks: 500 });
const url = computed(() => props.bahan?.url || {});
const kartu = computed(() => props.mode === 'kartu');

const sekarang = ref(new Date());
let detak = null;
onMounted(() => { detak = setInterval(() => { sekarang.value = new Date(); }, 30000); });
onBeforeUnmount(() => clearInterval(detak));
const sisaBatas = computed(() => (k.value.batas ? sisaWaktu(k.value.batas, sekarang.value) : ''));
const batasMepet = computed(() => {
    const b = urai(k.value.batas);
    return b ? b.getTime() - sekarang.value.getTime() < 6 * 3600 * 1000 : false;
});

// "Tidak melanjutkan" (sinyal mundur) bukan tombol utama, apa pun gaya
// masternya — tempatnya di KonfirmasiMundur, di ujung kartu/halaman.
const pilihanUtama = computed(() => pilihan.value.filter((p) => p.gaya !== 'TEKS' && !p.sinyalMundur));
const defJadwalLain = computed(() => pilihan.value.find((p) => p.bukaPermintaan) || null);
const defHadir = computed(() => pilihanUtama.value.find((p) => !p.bukaPermintaan) || null);

const permintaan = computed(() => props.bahan?.permintaan || null);
const permintaanTerbuka = computed(() => (permintaan.value?.status === 'TERBUKA' ? permintaan.value : null));
const permintaanDitolak = computed(() =>
    permintaan.value?.status === 'DITOLAK' && status.value === 'MENUNGGU' ? permintaan.value : null,
);

/** Sudah menjawab: pilihannya disembunyikan di balik "Ubah jawaban" — bila jatahnya masih ada. */
const sudahMenjawab = computed(() => ['AKAN_HADIR', 'JADWAL_LAIN'].includes(status.value));
const ubahTerbuka = ref(false);
const bolehUbah = computed(() => sudahMenjawab.value && !ubah.value.terkunci);
const tampilPilihan = computed(() => k.value.bolehJawab && (!sudahMenjawab.value || (ubahTerbuka.value && bolehUbah.value)));
/**
 * Kartu portal, pertanyaan PERTAMA: kotak status tidak ditampilkan — panel
 * pertanyaannya sendiri yang membawa batas waktu. Dua kotak yang sama-sama
 * berbunyi "mohon konfirmasi" hanya menambah gulir di ponsel.
 */
const tanyaAwal = computed(() => kartu.value && tampilPilihan.value && !sudahMenjawab.value);
/** Warna status dari master — latar & garis panel status ikut warnanya. */
const gayaNada = computed(() => ({
    '--nada': k.value.warna || '#94a3b8',
    '--nada-bg': rona(k.value.warna, 0.08) || '#f8fafc',
    '--nada-garis': rona(k.value.warna, 0.26) || '#e5e7f5',
}));

const kalimatKomitmen = computed(() => {
    if (!sudahMenjawab.value) return ubah.value.teksSebelum || '';
    return ubah.value.sisa === 1 ? 'Ini satu-satunya kesempatan mengubah — sesudah dikirim, jawabanmu final.' : ubah.value.teksSesudah || '';
});

// ── FORMULIR ────────────────────────────────────────────────────────────────

const panel = ref(null); // null | 'HADIR' | 'JADWAL_LAIN'
const kirim = ref(false);
const galat = ref('');
const sukses = ref('');

/**
 * Jawaban terkirim, data terbarunya sedang dimuat induk. Pilihan disembunyikan
 * sampai bahan baru tiba — tanpa ini tombol yang sama muncul lagi beberapa
 * detik dan mengundang klik kedua. Cadangan 20 detik bila muat ulang gagal.
 */
const memperbarui = ref(false);
let tmPerbarui = null;
let tmSukses = null;
watch(() => props.bahan, () => {
    memperbarui.value = false;
    clearTimeout(tmPerbarui);
    // Data terbaru sudah tampil (panel status ikut berubah) — pesan suksesnya
    // cukup terbaca sebentar, lalu hilang. Tanpa ini kalimat yang sama
    // menumpuk tiga kali: pesan sukses, panel status, dan rincian permintaan.
    if (sukses.value) {
        clearTimeout(tmSukses);
        tmSukses = setTimeout(() => { sukses.value = ''; }, 6000);
    }
});
function tungguDataBaru() {
    memperbarui.value = true;
    clearTimeout(tmPerbarui);
    tmPerbarui = setTimeout(() => { memperbarui.value = false; }, 20000);
}
onBeforeUnmount(() => {
    clearTimeout(tmPerbarui);
    clearTimeout(tmSukses);
});
/** Sedang menunggu data terbaru — dari jawaban di sini, atau dari kaki kartu. */
const sibuk = computed(() => memperbarui.value || props.menunggu);
/** "Ubah jawaban" menempel di panel status — bila jatah & waktunya masih ada. */
const tombolUbah = computed(() => k.value.bolehJawab && bolehUbah.value && !ubahTerbuka.value && !permintaanTerbuka.value && !sibuk.value);

function mulaiUbah() {
    ubahTerbuka.value = true;
    galat.value = '';
}

const bagian = computed(() => aturanUsulan.value.bagian || []);
const maksUsulan = computed(() => Math.max(1, Number(aturanUsulan.value.maks) || 3));
const catatanMaks = computed(() => Number(aturanUsulan.value.catatanMaks) || 500);

const fJl = reactive({ alasan: '', catatan: '', usulan: [{ tanggal: '', bagian: '' }] });

const alasanJl = computed(() => props.bahan?.alasan?.JADWAL_LAIN || []);
const butuhCatatanJl = computed(() => !!alasanJl.value.find((a) => a.kode === fJl.alasan)?.butuhCatatan);

function minggu(tgl) {
    const d = urai(`${tgl} 00:00:00`);
    return d ? d.getDay() === 0 : false;
}

const galatUsulan = computed(() => {
    const isi = fJl.usulan.filter((u) => u.tanggal || u.bagian);
    if (!isi.length) return 'Pilih minimal satu waktu yang kamu bisa.';
    for (const u of isi) {
        if (!u.tanggal || !u.bagian) return 'Lengkapi tanggal dan bagian hari untuk setiap usulan.';
        if (minggu(u.tanggal)) return 'Hari Minggu bukan hari kerja — pilih Senin sampai Sabtu.';
        if ((aturanUsulan.value.tanggalMin && u.tanggal < aturanUsulan.value.tanggalMin)
            || (aturanUsulan.value.tanggalMaks && u.tanggal > aturanUsulan.value.tanggalMaks)) {
            return 'Tanggal usulan di luar rentang yang bisa dipilih.';
        }
    }
    const kunci = isi.map((u) => `${u.tanggal}|${u.bagian}`);
    if (new Set(kunci).size !== kunci.length) return 'Ada usulan waktu yang sama — pilih waktu yang berbeda.';
    return '';
});

const siapJl = computed(() => {
    if (!fJl.alasan) return false;
    if (butuhCatatanJl.value && fJl.catatan.trim().length < 5) return false;
    return !galatUsulan.value;
});

function tambahUsulan() {
    if (fJl.usulan.length < maksUsulan.value) fJl.usulan.push({ tanggal: '', bagian: '' });
}

function hapusUsulan(i) {
    fJl.usulan.splice(i, 1);
    if (!fJl.usulan.length) fJl.usulan.push({ tanggal: '', bagian: '' });
}

function buka(p) {
    galat.value = '';
    sukses.value = '';
    const tujuan = p.bukaPermintaan ? 'JADWAL_LAIN' : 'HADIR';
    panel.value = panel.value === tujuan ? null : tujuan;
}

async function jawab(kode, isian = {}) {
    if (kirim.value) return;
    kirim.value = true;
    galat.value = '';
    try {
        const res = await kirimJawabanKandidat(url.value.jawab, { kode, statusDilihat: status.value, ...isian });
        sukses.value = res.message || 'Jawabanmu sudah kami terima.';
        panel.value = null;
        ubahTerbuka.value = false;
        tungguDataBaru();
        emit('berubah', sukses.value);
    } catch (e) {
        const g = galatDari(e, 'Jawaban gagal dikirim. Coba lagi.');
        galat.value = g.pesan;
        // Status di server sudah berbeda, atau jawabannya kini terkunci →
        // tampilkan keadaan terbaru.
        if ([409, 422].includes(g.status) && (g.result?.terkunci || (g.result?.status && g.result.status !== status.value))) {
            emit('berubah', null);
        }
    } finally {
        kirim.value = false;
    }
}

function kirimHadir() {
    if (defHadir.value) jawab(defHadir.value.kode);
}

function kirimJadwalLain() {
    if (!siapJl.value || !defJadwalLain.value) return;
    jawab(defJadwalLain.value.kode, {
        alasan: fJl.alasan,
        catatan: fJl.catatan.trim() || null,
        usulan: fJl.usulan.filter((u) => u.tanggal && u.bagian),
    });
}

/** KonfirmasiMundur (halaman tautan) selesai mengirim — sama dengan jawaban lain. */
function mundurTerkirim(pesan) {
    if (pesan) {
        sukses.value = pesan;
        panel.value = null;
        ubahTerbuka.value = false;
        tungguDataBaru();
    }
    emit('berubah', pesan);
}

async function cabut() {
    if (kirim.value) return;
    kirim.value = true;
    galat.value = '';
    try {
        const res = await cabutPermintaanKandidat(url.value.cabut);
        sukses.value = res.message || 'Permintaan dibatalkan.';
        tungguDataBaru();
        emit('berubah', sukses.value);
    } catch (e) {
        galat.value = galatDari(e, 'Permintaan gagal dibatalkan.').pesan;
        emit('berubah', null);
    } finally {
        kirim.value = false;
    }
}

/** Dipanggil induk sesudah memuat ulang — pesan suksesnya tetap terbaca. */
defineExpose({ pesan: (p) => { sukses.value = p || ''; } });
</script>

<template>
    <div class="kfw" :class="`kfw--${mode}`">
        <!-- STATUS + apa yang masih bisa dilakukan atasnya — satu panel datar
             berwarna status (dulu kotak di dalam kotak). "Ubah jawaban" menempel
             di sini, bukan blok tersendiri di bawahnya. -->
        <section v-if="!tanyaAwal" class="kfw-status" :style="gayaNada" aria-live="polite">
            <span class="kfw-status__ic"><i class="bi" :class="k.ikon || 'bi-hourglass-split'"></i></span>
            <div class="kfw-status__isi">
                <small v-if="!kartu">Status konfirmasimu</small>
                <b>{{ k.kalimat || 'Mohon konfirmasi kehadiranmu.' }}</b>
                <p v-if="status === 'MENUNGGU' && k.batasTeks && !k.final" class="kfw-batas" :class="{ 'is-mepet': batasMepet && !k.batasLewat, 'is-lewat': k.batasLewat }">
                    <i class="bi bi-alarm"></i>
                    <span v-if="!k.batasLewat">Mohon jawab sebelum jadwal dimulai, <b>{{ k.batasTeks }}</b> · {{ sisaBatas }}</span>
                    <span v-else>Jadwal sudah dimulai <b>{{ k.batasTeks }}</b> — konfirmasi ditutup.</span>
                </p>
                <p v-else-if="sudahMenjawab && k.bolehJawab && ubah.teksSesudah" class="kfw-batas" :class="{ 'is-kunci': ubah.terkunci }">
                    <i class="bi" :class="ubah.terkunci ? 'bi-lock-fill' : 'bi-pencil'"></i>
                    <span>{{ ubah.teksSesudah }}<template v-if="ubah.terkunci && k.kontak"> Kontak tim: <b>{{ k.kontak }}</b>.</template></span>
                </p>
                <p v-if="k.batasLewat && !k.final && !kartu" class="kfw-catat">
                    <i class="bi bi-info-circle"></i>
                    Konfirmasi ditutup saat jadwal dimulai. Bila berhalangan, segera hubungi tim rekrutmen lewat kontak di email undangan.
                </p>
            </div>
            <button v-if="tombolUbah" type="button" class="kfw-btn kfw-btn--garis kfw-btn--kecil kfw-status__aksi" @click="mulaiUbah">
                <i class="bi bi-pencil-square"></i> Ubah jawaban
            </button>
        </section>

        <!-- Pesan hasil -->
        <div v-if="sukses" class="kfw-pesan is-ok" role="status"><i class="bi bi-check-circle-fill"></i> {{ sukses }}</div>
        <div v-if="galat" class="kfw-pesan is-err" role="alert"><i class="bi bi-exclamation-triangle-fill"></i> {{ galat }}</div>
        <p v-if="sibuk" class="kfw-memuat"><span class="kfw-putar kfw-putar--gelap" aria-hidden="true"></span> Memperbarui status…</p>

        <template v-if="!sibuk">
        <!-- Permintaan yang sedang ditinjau. Judulnya tidak diulang — panel
             status di atas sudah berbunyi "sedang kami tinjau". Label di ATAS
             isinya: alasan & catatan bisa panjang, dan dua kolom menyisakan isi
             selebar beberapa kata. -->
        <section v-if="permintaanTerbuka" class="kfw-blok kfw-minta">
            <span class="kfw-minta__alis"><i class="bi bi-arrow-repeat"></i> Rincian permintaanmu</span>
            <dl>
                <div>
                    <dt>Alasan</dt>
                    <dd>{{ permintaanTerbuka.alasan }}</dd>
                </div>
                <div v-if="permintaanTerbuka.catatan">
                    <dt>Catatanmu</dt>
                    <dd class="kfw-minta__kutip">{{ permintaanTerbuka.catatan }}</dd>
                </div>
                <div>
                    <dt>Jadwal yang diajukan</dt>
                    <dd>
                        <ul class="kfw-minta__usulan">
                            <li v-for="(u, i) in permintaanTerbuka.usulan" :key="i"><i class="bi bi-calendar-event"></i> {{ u }}</li>
                        </ul>
                    </dd>
                </div>
            </dl>
            <p class="kfw-pelan kfw-minta__info">
                <i class="bi bi-info-circle"></i> Jadwal semula tetap berlaku sampai tim menjawab. Jawaban tim dikirim lewat email.
            </p>
            <button v-if="k.bolehJawab && bolehUbah" type="button" class="kfw-btn kfw-btn--garis" :disabled="kirim" @click="cabut">
                <i class="bi bi-arrow-counterclockwise"></i> Batalkan — saya bisa hadir di jadwal semula
            </button>
        </section>

        <!-- Permintaan sebelumnya ditolak -->
        <section v-if="permintaanDitolak" class="kfw-blok kfw-tolak">
            <h2><i class="bi bi-chat-left-text-fill"></i> Permintaanmu sebelumnya belum bisa dipenuhi</h2>
            <p v-if="permintaanDitolak.catatanTim">“{{ permintaanDitolak.catatanTim }}”</p>
            <p class="kfw-pelan">Jadwal semula tetap berlaku. Mohon konfirmasi kehadiranmu di bawah.</p>
        </section>

        <!-- PILIHAN -->
        <section v-if="tampilPilihan" class="kfw-blok kfw-pilih" :class="{ 'is-awal': tanyaAwal }" aria-label="Pilihan jawaban">
            <h2>{{ sudahMenjawab ? 'Ubah jawabanmu' : 'Apakah kamu bisa hadir?' }}</h2>
            <!-- Kartu portal: batas waktunya ikut di sini (kotak status disembunyikan). -->
            <p v-if="tanyaAwal && k.batasTeks" class="kfw-batas kfw-batas--tanya" :class="{ 'is-mepet': batasMepet }">
                <i class="bi bi-alarm"></i>
                <span>Mohon jawab sebelum jadwal dimulai · <b>{{ sisaBatas }}</b></span>
            </p>
            <p v-if="kalimatKomitmen && panel !== 'HADIR'" class="kfw-komitmen"><i class="bi bi-shield-check"></i> {{ kalimatKomitmen }}</p>

            <div class="kfw-pilihan">
                <button
                    v-for="p in pilihanUtama"
                    :key="p.kode"
                    type="button"
                    class="kfw-btn"
                    :class="[p.gaya === 'UTAMA' ? 'kfw-btn--utama' : 'kfw-btn--garis', { 'is-aktif': (panel === 'JADWAL_LAIN' && p.bukaPermintaan) || (panel === 'HADIR' && !p.bukaPermintaan), 'is-kini': status === p.kode }]"
                    :disabled="kirim || (status === p.kode && !p.bukaPermintaan) || (p.bukaPermintaan && (!!permintaanTerbuka || jatah.sisa <= 0))"
                    :aria-expanded="String(p.bukaPermintaan ? panel === 'JADWAL_LAIN' : panel === 'HADIR')"
                    @click="buka(p)"
                >
                    <i class="bi" :class="p.ikon"></i>
                    {{ p.label }}
                    <small v-if="status === p.kode && !p.bukaPermintaan" class="kfw-btn__kini">(jawabanmu saat ini)</small>
                </button>
            </div>
            <p v-if="defJadwalLain && jatah.sisa <= 0 && !permintaanTerbuka" class="kfw-pelan kfw-jatah">
                <i class="bi bi-info-circle"></i> Kamu sudah {{ jatah.maks }}× meminta jadwal lain untuk aktivitas ini. Bila tetap berhalangan, hubungi tim rekrutmen.
            </p>

            <!-- AKAN HADIR — satu penegasan: jawabannya berkomitmen. -->
            <div v-if="panel === 'HADIR'" class="kfw-form kfw-form--hijau">
                <h3>Konfirmasi kehadiranmu</h3>
                <p class="kfw-tegas">
                    Kamu akan hadir di <b>{{ judul.aktivitas || 'jadwal ini' }}</b><template v-if="judul.waktuTeks">, <b>{{ judul.waktuTeks }}</b></template>.
                </p>
                <p v-if="kalimatKomitmen" class="kfw-pelan">{{ kalimatKomitmen }}</p>
                <div class="kfw-form__aksi">
                    <button type="button" class="kfw-btn kfw-btn--teks" @click="panel = null">Batal</button>
                    <button type="button" class="kfw-btn kfw-btn--utama" :disabled="kirim" @click="kirimHadir">
                        <span v-if="kirim" class="kfw-putar" aria-hidden="true"></span>
                        <i v-else class="bi bi-check2-circle"></i> Ya, saya akan hadir
                    </button>
                </div>
            </div>

            <!-- PERLU JADWAL LAIN -->
            <div v-if="panel === 'JADWAL_LAIN'" class="kfw-form">
                <h3>Kenapa waktu ini tidak bisa?</h3>
                <div class="kfw-alasan" role="radiogroup" aria-label="Alasan">
                    <button
                        v-for="a in alasanJl"
                        :key="a.kode"
                        type="button"
                        role="radio"
                        class="kfw-opsi"
                        :class="{ on: fJl.alasan === a.kode }"
                        :aria-checked="String(fJl.alasan === a.kode)"
                        @click="fJl.alasan = a.kode"
                    >{{ a.nama }}</button>
                </div>

                <label class="kfw-label">
                    Catatan <span v-if="butuhCatatanJl">(wajib)</span><span v-else class="kfw-pelan">(opsional)</span>
                    <textarea
                        v-model="fJl.catatan"
                        :maxlength="catatanMaks"
                        rows="3"
                        class="kfw-input"
                        placeholder="Contoh: ada ujian akhir semester sampai Jumat."
                    ></textarea>
                </label>

                <h3>Kapan kamu bisa? <small class="kfw-pelan">({{ fJl.usulan.length }}/{{ maksUsulan }})</small></h3>
                <div v-for="(u, i) in fJl.usulan" :key="i" class="kfw-usulan">
                    <input
                        v-model="u.tanggal"
                        type="date"
                        class="kfw-input kfw-usulan__tgl"
                        :min="aturanUsulan.tanggalMin"
                        :max="aturanUsulan.tanggalMaks"
                        :aria-label="`Tanggal usulan ${i + 1}`"
                    />
                    <div class="kfw-usulan__bag" role="radiogroup" :aria-label="`Bagian hari usulan ${i + 1}`">
                        <button
                            v-for="b in bagian"
                            :key="b.kode"
                            type="button"
                            role="radio"
                            class="kfw-opsi kfw-opsi--kecil"
                            :class="{ on: u.bagian === b.kode }"
                            :aria-checked="String(u.bagian === b.kode)"
                            @click="u.bagian = b.kode"
                        >{{ b.label }} <small>{{ b.mulai.replace(':', '.') }}–{{ b.selesai.replace(':', '.') }}</small></button>
                    </div>
                    <button v-if="fJl.usulan.length > 1" type="button" class="kfw-hapus" :aria-label="`Hapus usulan ${i + 1}`" @click="hapusUsulan(i)">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
                <button v-if="fJl.usulan.length < maksUsulan" type="button" class="kfw-tambah" @click="tambahUsulan">
                    <i class="bi bi-plus-circle"></i> Tambah pilihan waktu
                </button>
                <p v-if="galatUsulan && fJl.usulan.some((x) => x.tanggal || x.bagian)" class="kfw-galat-kecil">{{ galatUsulan }}</p>

                <p class="kfw-catat"><i class="bi bi-info-circle"></i> Jadwal semula tetap berlaku sampai tim menjawab permintaanmu.</p>
                <div class="kfw-form__aksi">
                    <button type="button" class="kfw-btn kfw-btn--teks" @click="panel = null">Batal</button>
                    <button type="button" class="kfw-btn kfw-btn--utama" :disabled="!siapJl || kirim" @click="kirimJadwalLain">
                        <span v-if="kirim" class="kfw-putar" aria-hidden="true"></span>
                        <i v-else class="bi bi-send-fill"></i> Kirim permintaan
                    </button>
                </div>
            </div>

            <div v-if="ubahTerbuka" class="kfw-teks-pilihan">
                <button type="button" class="kfw-btn kfw-btn--teks" @click="ubahTerbuka = false; panel = null">Batal mengubah</button>
            </div>
        </section>

        <!-- TIDAK MELANJUTKAN — di ujung, bukan di tengah pilihan; tetap ada
             walau jawabannya terkunci. Kartu portal merendernya sendiri. -->
        <KonfirmasiMundur v-if="mundur" :bahan="bahan" :mode="mode" @berubah="mundurTerkirim" />
        </template>
    </div>
</template>

<style scoped>
.kfw {
    --ink: #0f172a;
    --muted: #64748b;
    --line: #e5e7f5;
    --brand: #4f46e5;
    display: flex;
    flex-direction: column;
    gap: 12px;
    color: var(--ink);
    /* Tata letak mengikuti lebar KOMPONEN, bukan layar: kartu jadwal yang
       sama bisa 300px (ponsel) atau 1200px (desktop) lebarnya. */
    container-type: inline-size;
}
.kfw h2 {
    margin: 0 0 8px;
    font-size: 1rem;
    font-weight: 800;
    display: flex;
    align-items: center;
    gap: 8px;
}
.kfw h3 {
    margin: 14px 0 8px;
    font-size: 0.9rem;
    font-weight: 800;
}
.kfw-pelan {
    color: var(--muted);
    font-size: 0.85rem;
    font-weight: 500;
}

/* Blok: kartu besar di halaman tautan, polos di kartu portal. */
.kfw-status,
.kfw-blok {
    background: #fff;
    border: 1px solid var(--line);
    border-radius: 18px;
    padding: 16px;
    box-shadow: 0 14px 34px -28px rgba(30, 27, 75, 0.45);
}
.kfw-status {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-start;
    gap: 10px 12px;
    border-left: 5px solid var(--nada);
}
.kfw-status__isi {
    flex: 1 1 160px;
    min-width: 0;
    align-self: center;
}
.kfw-status__isi > b {
    display: block;
    font-size: 0.95rem;
    line-height: 1.4;
}
.kfw-status__aksi {
    flex: none;
    align-self: center;
}
.kfw-status__ic {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    flex: none;
    display: grid;
    place-items: center;
    color: #fff;
    background: var(--nada);
    font-size: 1.1rem;
}
.kfw-status small {
    display: block;
    color: var(--muted);
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}
.kfw-batas {
    margin: 12px 0 0;
    padding: 9px 12px;
    border-radius: 12px;
    background: #f8fafc;
    font-size: 0.85rem;
    display: flex;
    gap: 8px;
    align-items: flex-start;
    line-height: 1.5;
}
.kfw-batas > i {
    margin-top: 2px;
}
.kfw-batas.is-mepet {
    background: #fff7ed;
    color: #9a3412;
}
.kfw-batas.is-lewat {
    background: #fef2f2;
    color: #991b1b;
}
.kfw-batas.is-kunci {
    background: #f5f3ff;
    color: #4c1d95;
}
.kfw-catat {
    margin: 10px 0 0;
    font-size: 0.83rem;
    color: #475569;
    display: flex;
    gap: 8px;
    align-items: flex-start;
}

.kfw-pesan {
    padding: 11px 14px;
    border-radius: 14px;
    font-weight: 700;
    font-size: 0.88rem;
    display: flex;
    gap: 8px;
    align-items: flex-start;
}
.kfw-pesan.is-ok {
    background: #ecfdf5;
    color: #065f46;
    border: 1px solid #a7f3d0;
}
.kfw-pesan.is-err {
    background: #fef2f2;
    color: #991b1b;
    border: 1px solid #fecaca;
}

.kfw-minta {
    border-color: #fde68a;
    background: #fffdf5;
}
.kfw-minta__alis {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 0.72rem;
    font-weight: 800;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    color: #b45309;
}
.kfw-minta dl {
    margin: 10px 0 12px;
    display: flex;
    flex-direction: column;
    gap: 10px;
    font-size: 0.88rem;
}
.kfw-minta dt {
    font-size: 0.74rem;
    font-weight: 800;
    color: var(--muted);
}
.kfw-minta dd {
    margin: 2px 0 0;
    line-height: 1.55;
    font-weight: 600;
    overflow-wrap: anywhere;
}
.kfw-minta__kutip {
    white-space: pre-line;
    font-weight: 500 !important;
    color: #334155;
}
.kfw-minta__usulan {
    list-style: none;
    margin: 4px 0 0;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: 6px;
}
.kfw-minta__usulan li {
    display: flex;
    align-items: flex-start;
    gap: 8px;
    padding: 8px 11px;
    border-radius: 11px;
    background: #fff;
    border: 1px solid #fde68a;
    font-weight: 700;
    color: #1e293b;
}
.kfw-minta__usulan .bi {
    flex: none;
    margin-top: 2px;
    color: #d97706;
}
.kfw-minta__info {
    display: flex;
    gap: 7px;
    align-items: flex-start;
    margin: 0 0 12px;
    line-height: 1.5;
}
.kfw-tolak {
    border-color: #fecaca;
    background: #fffafa;
}
.kfw-tolak h2 {
    color: #991b1b;
}

.kfw-komitmen {
    margin: 0 0 12px;
    padding: 9px 12px;
    border-radius: 12px;
    background: #eef2ff;
    color: #3730a3;
    font-size: 0.84rem;
    font-weight: 600;
    line-height: 1.5;
    display: flex;
    gap: 8px;
    align-items: flex-start;
}
.kfw-komitmen > i {
    margin-top: 2px;
}
.kfw-tegas {
    margin: 0 0 6px;
    font-size: 0.92rem;
    line-height: 1.5;
}

.kfw-pilihan {
    display: flex;
    flex-direction: column;
    gap: 10px;
}
.kfw-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    min-height: 48px;
    padding: 11px 16px;
    border-radius: 14px;
    border: 1.5px solid transparent;
    font: inherit;
    font-weight: 800;
    font-size: 0.95rem;
    cursor: pointer;
    text-decoration: none;
    transition: transform 0.12s ease, box-shadow 0.12s ease, background 0.12s ease;
}
.kfw-btn:focus-visible {
    outline: 3px solid #c7d2fe;
    outline-offset: 2px;
}
.kfw-btn:disabled {
    opacity: 0.55;
    cursor: not-allowed;
}
.kfw-btn--utama {
    color: #fff;
    background: linear-gradient(135deg, #16a34a, #15803d);
    box-shadow: 0 12px 24px -14px rgba(22, 163, 74, 0.9);
}
.kfw-btn--utama:not(:disabled):hover {
    transform: translateY(-1px);
}
.kfw-btn--utama.is-aktif {
    outline: 3px solid #bbf7d0;
    outline-offset: 2px;
}
.kfw-btn--garis {
    color: #3730a3;
    background: #fff;
    border-color: #c7d2fe;
}
.kfw-btn--garis.is-aktif {
    background: #eef2ff;
    border-color: var(--brand);
}
.kfw-btn.is-kini {
    outline: 2px dashed currentColor;
    outline-offset: 2px;
}
.kfw-btn__kini {
    font-weight: 600;
    font-size: 0.75rem;
    opacity: 0.85;
}
.kfw-btn--teks {
    min-height: 40px;
    background: transparent;
    color: #475569;
    padding: 8px 10px;
}
.kfw-btn--kecil {
    min-height: 38px;
    padding: 7px 13px;
    border-radius: 11px;
    font-size: 0.82rem;
}
.kfw-teks-pilihan {
    display: flex;
    justify-content: center;
    margin-top: 6px;
}
.kfw-jatah {
    margin: 8px 0 0;
}

.kfw-form {
    margin-top: 12px;
    padding: 14px;
    border-radius: 14px;
    background: #f8f8ff;
    border: 1px solid #e0e7ff;
}
.kfw-form--hijau {
    background: #f0fdf4;
    border-color: #bbf7d0;
}
.kfw-form h3:first-child {
    margin-top: 0;
}
.kfw-alasan {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}
.kfw-opsi {
    padding: 8px 12px;
    border-radius: 999px;
    border: 1.5px solid #dbe1f1;
    background: #fff;
    font: inherit;
    font-size: 0.84rem;
    font-weight: 700;
    color: #334155;
    cursor: pointer;
}
.kfw-opsi.on {
    border-color: var(--brand);
    background: #eef2ff;
    color: #3730a3;
}
.kfw-opsi--kecil {
    padding: 6px 10px;
    font-size: 0.8rem;
}
.kfw-opsi small {
    font-weight: 600;
    color: var(--muted);
}
.kfw-label {
    display: flex;
    flex-direction: column;
    gap: 6px;
    margin: 12px 0 0;
    font-size: 0.84rem;
    font-weight: 800;
}
.kfw-input {
    width: 100%;
    box-sizing: border-box;
    border: 1.5px solid #dbe1f1;
    border-radius: 12px;
    padding: 10px 12px;
    font: inherit;
    font-size: 0.92rem;
    font-weight: 500;
    background: #fff;
    color: var(--ink);
}
.kfw-input:focus {
    outline: none;
    border-color: var(--brand);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.16);
}
.kfw-usulan {
    position: relative;
    display: flex;
    flex-direction: column;
    gap: 8px;
    padding: 10px;
    border-radius: 12px;
    background: #fff;
    border: 1px solid var(--line);
    margin-bottom: 8px;
}
.kfw-usulan__bag {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}
.kfw-usulan__tgl {
    max-width: calc(100% - 40px);
}
.kfw-hapus {
    position: absolute;
    top: 10px;
    right: 10px;
    width: 32px;
    height: 32px;
    border-radius: 10px;
    border: none;
    background: #f1f5f9;
    color: #475569;
    cursor: pointer;
}
.kfw-tambah {
    border: none;
    background: transparent;
    color: var(--brand);
    font: inherit;
    font-weight: 800;
    font-size: 0.86rem;
    cursor: pointer;
    padding: 6px 0;
}
.kfw-galat-kecil {
    margin: 4px 0 0;
    color: #b91c1c;
    font-size: 0.82rem;
    font-weight: 700;
}
.kfw-form__aksi {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
    margin-top: 14px;
    flex-wrap: wrap;
}
.kfw-putar {
    width: 16px;
    height: 16px;
    border-radius: 50%;
    border: 2.5px solid rgba(255, 255, 255, 0.45);
    border-top-color: #fff;
    animation: kfw-putar 0.8s linear infinite;
}
.kfw-btn--garis .kfw-putar,
.kfw-putar--gelap {
    border-color: rgba(79, 70, 229, 0.25);
    border-top-color: var(--brand);
}
.kfw-putar--gelap {
    display: inline-block;
    flex: none;
}
.kfw-memuat {
    margin: 0;
    display: flex;
    gap: 8px;
    align-items: center;
    font-size: 0.84rem;
    font-weight: 700;
    color: #4338ca;
}
@keyframes kfw-putar {
    to {
        transform: rotate(360deg);
    }
}

/* ── MODE KARTU (portal): di dalam kartu jadwal — datar & padat, tanpa
   kotak di dalam kotak. Panel status berwarna status (rona dari master). ── */
.kfw--kartu {
    gap: 10px;
}
.kfw--kartu .kfw-blok {
    border-radius: 14px;
    padding: 13px 14px;
    box-shadow: none;
    border-color: #eef0f7;
}
.kfw--kartu .kfw-status {
    border-radius: 14px;
    padding: 12px 14px;
    box-shadow: none;
    border: 1px solid var(--nada-garis, var(--line));
    background: var(--nada-bg, #f8fafc);
}
.kfw--kartu .kfw-status__ic {
    width: 34px;
    height: 34px;
    font-size: 15px;
}
.kfw--kartu .kfw-status__isi > b {
    font-size: 13.5px;
}
/* Kalimat aturan di bawah status: satu baris teks, bukan kotak kedua. */
.kfw--kartu .kfw-status .kfw-batas {
    margin: 3px 0 0;
    padding: 0;
    background: transparent;
    font-size: 12.5px;
    color: #475569;
}
.kfw--kartu .kfw-status .kfw-batas > i {
    color: var(--nada);
}
.kfw--kartu .kfw-status .kfw-batas.is-mepet {
    color: #9a3412;
}
.kfw--kartu .kfw-status .kfw-batas.is-lewat {
    color: #991b1b;
}
/* Pertanyaan pertama: panel utama kartu — membawa batas waktunya sendiri. */
.kfw--kartu .kfw-pilih {
    border-color: #e0e7ff;
    background: linear-gradient(180deg, #f6f7ff, #fff 70%);
}
.kfw--kartu .kfw-pilih h2 {
    margin: 0;
    font-size: 14.5px;
}
.kfw-batas--tanya {
    margin: 5px 0 12px;
    padding: 0;
    background: transparent;
    font-size: 12.5px;
    color: #475569;
}
.kfw-batas--tanya > i {
    color: #6366f1;
}
.kfw-batas--tanya.is-mepet,
.kfw-batas--tanya.is-mepet > i {
    color: #c2410c;
}
.kfw--kartu .kfw-pilih:not(.is-awal) h2 {
    margin-bottom: 10px;
}
.kfw--kartu .kfw-komitmen {
    margin: 0 0 12px;
    padding: 0;
    background: transparent;
    font-size: 12px;
    color: #3730a3;
}
.kfw--kartu .kfw-btn {
    min-height: 44px;
    font-size: 13px;
    border-radius: 12px;
    padding: 9px 14px;
}
.kfw--kartu .kfw-btn--kecil {
    min-height: 38px;
    padding: 7px 13px;
    font-size: 12.5px;
    border-radius: 11px;
}
.kfw--kartu .kfw-btn--teks {
    min-height: 36px;
    font-size: 12.5px;
}

@media (min-width: 640px) {
    .kfw-status,
    .kfw-blok {
        padding: 20px;
    }
    .kfw--kartu .kfw-status {
        padding: 12px 16px;
    }
    .kfw--kartu .kfw-blok {
        padding: 14px 16px;
    }
}
/* Pilihan jawaban berjajar begitu kartunya cukup lebar — di ponsel (dan di
   peramban tanpa container query) tetap bertumpuk, sasaran ketuknya selebar kartu. */
@container (min-width: 440px) {
    .kfw-pilihan {
        flex-direction: row;
    }
    .kfw-pilihan .kfw-btn {
        flex: 1 1 0;
    }
}
/* Kartu sempit: "Ubah jawaban" turun ke baris sendiri, selebar panel. */
@container (max-width: 420px) {
    .kfw-status__aksi {
        flex: 1 1 100%;
    }
}
@media (prefers-reduced-motion: reduce) {
    .kfw-btn,
    .kfw-putar {
        transition: none;
        animation: none;
    }
}
</style>
