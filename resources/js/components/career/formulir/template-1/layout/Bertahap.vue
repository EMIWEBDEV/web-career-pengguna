<!--
  WEB CAREER — TEMPLATE 2: Identitas Bertahap.

  Banyak langkah dengan stepper, bagian bernama, mendukung bagian berulang,
  unggah berkas, dan pernyataan persetujuan. Dipakai formulir seperti
  "Form Identitas Peserta Rekrutmen MT" (Page 1-4) dan form MT lengkap
  (Identitas / Pendidikan / Organisasi / Pengalaman / Prestasi / Dokumen).

  Validasi berjalan PER LANGKAH: kandidat tidak bisa maju sebelum langkah
  yang sedang dibuka lengkap, tapi juga tidak dibombardir seluruh galat
  formulir sekaligus.
-->
<template>
    <div class="t2">
        <!-- ── Stepper (langkah skema + satu langkah pratinjau di ujung) ── -->
        <nav class="t2__steps">
            <button
                v-for="(L, i) in stepper"
                :key="i"
                class="t2__step"
                :class="{ done: i < aktif, cur: i === aktif }"
                type="button"
                :disabled="i > terjauh"
                @click="i <= terjauh && (aktif = i)"
            >
                <span class="t2__dot">
                    <i v-if="i < aktif" class="bi bi-check-lg"></i>
                    <i v-else class="bi" :class="L.ikon || 'bi-card-list'"></i>
                </span>
                <span class="t2__lbl">{{ L.judul }}</span>
            </button>
        </nav>

        <div v-if="!langkah.length" class="t2__kosong">
            <i class="bi bi-inbox"></i><span>Skema belum punya langkah.</span>
        </div>

        <!-- ── Isi langkah aktif ── -->
        <div v-else class="t2__panel">
            <header class="t2__head">
                <span class="t2__badge"><i class="bi" :class="kini.ikon || 'bi-card-list'"></i></span>
                <div>
                    <span class="t2__no">Langkah {{ aktif + 1 }} dari {{ stepper.length }}</span>
                    <h3>{{ kini.judul }}</h3>
                    <p v-if="kini.deskripsi">{{ kini.deskripsi }}</p>
                </div>
            </header>

            <!-- ── Pratinjau: seluruh jawaban dirangkum sebelum dikirim ──
                 Formulir bertahap menyembunyikan langkah yang tidak sedang dibuka,
                 jadi tanpa halaman ini kandidat menekan Kirim tanpa pernah melihat
                 utuh apa yang dikirimkan. Tiap blok bisa diklik untuk kembali ke
                 langkahnya — memperbaiki typo tidak perlu menekan Kembali berkali. -->
            <template v-if="diPratinjau">
                <div v-for="(L, iL) in ringkasan" :key="iL" class="t2v__blok">
                    <div class="t2v__blok-head">
                        <span><i class="bi" :class="L.ikon || 'bi-card-list'"></i> {{ L.judul }}</span>
                        <button type="button" class="t2v__ubah" @click="aktif = L.index">
                            <i class="bi bi-pencil"></i> Ubah
                        </button>
                    </div>

                    <div v-for="(B, iB) in L.bagian" :key="iB" class="t2v__bagian">
                        <h5 v-if="B.judul">{{ B.judul }}</h5>

                        <!-- Bagian berulang: satu kartu per baris -->
                        <template v-if="B.baris">
                            <div v-for="(baris, iR) in B.baris" :key="iR" class="t2v__baris">
                                <span class="t2v__baris-no">{{ iR + 1 }}</span>
                                <dl class="t2v__list">
                                    <template v-for="it in baris" :key="it.key">
                                        <dt>{{ it.label }}</dt>
                                        <dd :class="{ 'is-kosong': it.kosong }">{{ it.nilai }}</dd>
                                    </template>
                                </dl>
                            </div>
                        </template>

                        <dl v-else class="t2v__list">
                            <template v-for="it in B.item" :key="it.key">
                                <dt>{{ it.label }}</dt>
                                <dd :class="{ 'is-kosong': it.kosong }">{{ it.nilai }}</dd>
                            </template>
                        </dl>
                    </div>
                </div>

                <p v-if="!ringkasan.length" class="t2__kosong">
                    <i class="bi bi-inbox"></i><span>Belum ada jawaban untuk ditinjau.</span>
                </p>

                <!-- Persetujuan ikut dimunculkan di sini. Tombol kirim ada di
                     halaman ini, jadi centangnya harus ada di sini juga —
                     kalau tidak, kandidat melihat tombol terkunci tanpa tahu
                     centangnya tertinggal di langkah sebelumnya. Field yang
                     sama juga tetap tampil di langkahnya sendiri; keduanya
                     terikat key yang sama sehingga selalu seiring. -->
                <div v-if="persetujuan.length" class="t2v__setuju">
                    <h5><i class="bi bi-shield-check"></i> Pernyataan Persetujuan</h5>
                    <FieldRenderer
                        v-for="f in persetujuan"
                        :key="f.key"
                        :field="f"
                        :model-value="jawaban[f.key]"
                        :disabled="disabled"
                        :konteks="konteks"
                        :jawaban-konteks="jawaban"
                        @update:model-value="(v) => setNilai(f.key, v)"
                    />
                </div>
            </template>

            <BagianRenderer
                v-for="(B, iB) in bagianTerlihat"
                :key="iB"
                :bagian="B"
                :jawaban="jawaban"
                :disabled="disabled"
                :konteks-opsi="konteks"
                @ubah="setNilai"
                @ubah-baris="setNilaiBaris"
                @berkas="(e) => $emit('berkas', e)"
                @hapus-baris="(b, i) => $emit('hapus-baris', b, i)"
            />

            <div v-if="galat.length" class="t2__galat">
                <strong><i class="bi bi-exclamation-triangle-fill"></i> Lengkapi dulu langkah ini:</strong>
                <ul><li v-for="(g, i) in galat" :key="i">{{ g }}</li></ul>
            </div>

            <footer class="t2__foot">
                <button class="t2__btn t2__btn--ghost" type="button" :disabled="aktif === 0" :onClick="aktif === 0 ? null : mundur">
                    <i class="bi bi-arrow-left"></i> Kembali
                </button>
                <span class="t2__spacer"></span>
                <button v-if="!terakhir" class="t2__btn t2__btn--primary" type="button" :disabled="disabled" :onClick="disabled ? null : maju">
                    Lanjut <i class="bi bi-arrow-right"></i>
                </button>
                <!-- Bila persetujuan belum lengkap, tombolnya dirender TANPA
                     pendengar klik sama sekali (v-on objek kosong), bukan sekadar
                     ber-atribut disabled. Atribut disabled bisa dicabut lewat
                     inspect element lalu tombolnya jadi bisa ditekan; kalau tidak
                     ada listener yang terpasang, tidak ada yang bisa dipicu. -->
                <button
                    v-else
                    class="t2__btn t2__btn--primary"
                    type="button"
                    :disabled="disabled || !bolehKirim"
                    :aria-disabled="String(!bolehKirim)"
                    v-on="bolehKirim && !disabled ? { click: kirim } : {}"
                >
                    <i class="bi" :class="disabled ? 'bi-arrow-repeat t2__spin' : 'bi-send-check-fill'"></i>
                    {{ disabled ? 'Mengirim…' : labelKirim }}
                </button>
            </footer>

            <!-- Alasan tombol terkunci. Tombol mati tanpa penjelasan membuat
                 kandidat mengira formulirnya rusak. -->
            <p v-if="terakhir && !bolehKirim" class="t2__kunci">
                <i class="bi bi-lock-fill"></i>
                Seluruh pernyataan persetujuan harus dicentang sebelum formulir bisa dikirim.
            </p>
        </div>
    </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import BagianRenderer from '../../inti/BagianRenderer.vue';
import FieldRenderer from '../../inti/FieldRenderer.vue';
import { bagianTampil, fieldTampil, kunciBagian, periksaLangkah, syaratTerpenuhi } from '@utils/formulir/aturan';

const props = defineProps({
    konteks: { type: Object, default: () => ({}) }, // opsi dinamis pembukaan
    skema: { type: Object, required: true },
    modelValue: { type: Object, required: true },
    disabled: { type: Boolean, default: false },
    labelKirim: { type: String, default: 'Kirim Formulir' },
    // Langkah terakhir yang sudah pernah dicapai — dipulihkan dari
    // Formulir_Pengisian.Langkah_Terakhir agar kandidat bisa lanjut esok hari.
    langkahAwal: { type: Number, default: 0 },
});

const emit = defineEmits(['update:modelValue', 'kirim', 'berkas', 'hapus-baris', 'pindah-langkah']);

const langkah = computed(() => props.skema?.langkah || []);
const jawaban = computed(() => props.modelValue);

const aktif = ref(props.langkahAwal);
// Langkah terjauh yang sudah lolos validasi. Kandidat bebas mundur-maju di
// wilayah yang sudah dilewati, tapi tidak bisa melompati yang belum lengkap.
const terjauh = ref(props.langkahAwal);
const galat = ref([]);

/**
 * Langkah PRATINJAU disisipkan sebagai langkah semu di ujung stepper — bukan
 * bagian dari skema, jadi admin tidak perlu membuatnya di Master Formulir dan
 * tidak ada key jawaban baru yang lahir karenanya.
 */
const LANGKAH_PRATINJAU = {
    judul: 'Periksa & Kirim',
    ikon: 'bi-clipboard-check',
    deskripsi: 'Periksa kembali seluruh jawaban Anda. Tekan "Ubah" pada bagian mana pun untuk memperbaikinya.',
    bagian: [],
};

const stepper = computed(() => [...langkah.value, LANGKAH_PRATINJAU]);
const iPratinjau = computed(() => langkah.value.length);
const diPratinjau = computed(() => aktif.value === iPratinjau.value);

const kini = computed(() => (diPratinjau.value ? LANGKAH_PRATINJAU : langkah.value[aktif.value] || {}));
const terakhir = computed(() => diPratinjau.value);
const bagianTerlihat = computed(() => (diPratinjau.value ? [] : bagianTampil(kini.value.bagian, jawaban.value)));

watch(aktif, (v) => {
    galat.value = [];
    if (v > terjauh.value) terjauh.value = v;
    // Langkah pratinjau tidak ada di skema. Indeksnya dipotong sebelum dikirim
    // supaya Formulir_Pengisian.Langkah_Terakhir tidak menyimpan posisi yang
    // tidak dikenal skema saat draf dipulihkan di lain hari.
    emit('pindah-langkah', Math.min(v, Math.max(langkah.value.length - 1, 0)));
});

/**
 * Tanda pengenal SUSUNAN skema — layout dan daftar kode langkahnya saja.
 *
 * Pembandingnya tidak boleh identitas objek: `normalisasiSkema()` membangun
 * ulang seluruh pohon setiap kali dipanggil, jadi objeknya selalu baru. Di
 * pratinjau Master Formulir, admin menyunting skema yang sama secara langsung —
 * satu ketukan huruf pun melahirkan objek baru, dan pratinjau akan terlempar
 * balik ke langkah 1 terus-menerus sehingga langkah selanjutnya mustahil diuji.
 */
const tandaSkema = computed(() => [
    props.skema?.layout || '',
    (props.skema?.langkah || []).map((L) => L.kode || L.judul || '').join('|'),
].join('::'));

// Susunan langkah benar-benar berganti (template lain, langkah ditambah/dihapus)
// -> mulai lagi dari awal. Menyunting isi field tidak termasuk.
watch(tandaSkema, () => {
    aktif.value = 0;
    terjauh.value = 0;
    galat.value = [];
});

// Draf datang BELAKANGAN (permintaan ke server itu asinkron), sementara `aktif`
// sudah terlanjur diinisialisasi 0 saat komponen dipasang. Tanpa pengawas ini,
// posisi langkah yang dipulihkan tidak pernah terpakai dan kandidat selalu
// dilempar balik ke langkah 1. Hanya bergerak MAJU, supaya pemulihan tidak
// pernah menarik mundur kandidat yang sudah lebih jauh mengisi.
watch(() => props.langkahAwal, (v) => {
    const tujuan = Math.min(Math.max(Number(v) || 0, 0), Math.max(langkah.value.length - 1, 0));
    if (tujuan > aktif.value) {
        terjauh.value = Math.max(terjauh.value, tujuan);
        aktif.value = tujuan;
    }
});

function setNilai(key, nilai) {
    emit('update:modelValue', { ...jawaban.value, [key]: nilai });
}

function setNilaiBaris(kunciBagian, i, key, nilai) {
    const arr = [...(jawaban.value[kunciBagian] || [])];
    arr[i] = { ...arr[i], [key]: nilai };
    setNilai(kunciBagian, arr);
}

function periksa() {
    galat.value = periksaLangkah(kini.value, jawaban.value);
    return galat.value.length === 0;
}

function maju() {
    if (!periksa()) return;
    aktif.value = Math.min(aktif.value + 1, iPratinjau.value);
}

/**
 * Nilai jawaban jadi teks yang bisa dibaca di halaman pratinjau.
 *
 * Bukan sekadar String(v): centang persetujuan yang tampil "true" dan daftar
 * pilihan yang tampil "a,b,c" membuat kandidat harus menerjemahkan sendiri
 * jawabannya, padahal justru halaman inilah tempat ia memeriksa.
 */
function teksNilai(f, v) {
    if (f.tipe === 'consent') {
        return v === true ? 'Disetujui' : 'Belum disetujui';
    }
    if (Array.isArray(v)) {
        return v.length ? v.join(', ') : '';
    }
    if (v === null || v === undefined || v === '') {
        return '';
    }
    if (f.tipe === 'currency') {
        const angka = String(v).replace(/\D/g, '');
        return angka ? 'Rp ' + angka.replace(/\B(?=(\d{3})+(?!\d))/g, '.') : '';
    }
    return String(v);
}

function butir(f, sumber) {
    const teks = teksNilai(f, sumber?.[f.key]);

    return { key: f.key, label: f.label, nilai: teks === '' ? '—' : teks, kosong: teks === '' };
}

/**
 * Rangkuman untuk halaman pratinjau: hanya langkah, bagian, dan field yang
 * BENAR-BENAR tampil bagi kandidat ini. Menampilkan pertanyaan yang tersembunyi
 * oleh syarat tampil akan membuatnya bingung ditanya hal yang tak pernah muncul.
 */
const ringkasan = computed(() => langkah.value
    .map((L, index) => ({ L, index }))
    .filter(({ L }) => syaratTerpenuhi(L.tampil_jika, jawaban.value))
    .map(({ L, index }) => ({
        index,
        judul: L.judul,
        ikon: L.ikon,
        bagian: bagianTampil(L.bagian, jawaban.value).map((B) => {
            if (B.berulang) {
                const baris = (jawaban.value?.[kunciBagian(B)] || [])
                    .map((r) => fieldTampil(B.field, r).map((f) => butir(f, r)))
                    .filter((r) => r.some((it) => !it.kosong));

                return { judul: B.judul, baris };
            }

            return { judul: B.judul, item: fieldTampil(B.field, jawaban.value).map((f) => butir(f, jawaban.value)) };
        }).filter((B) => (B.baris ? B.baris.length : B.item.length)),
    }))
    .filter((L) => L.bagian.length));

function mundur() {
    galat.value = [];
    aktif.value = Math.max(aktif.value - 1, 0);
}

/**
 * Semua pernyataan persetujuan (tipe `consent`) sudah disetujui?
 *
 * Dihitung dari SELURUH langkah, bukan hanya langkah terakhir: satu formulir
 * boleh menaruh persetujuan di mana saja, dan yang tersembunyi oleh syarat
 * tampil tidak ikut dihitung — menuntut centang yang tidak terlihat itu jebakan.
 *
 * CATATAN: ini kenyamanan antarmuka, bukan pengaman. Penjaga sebenarnya tetap
 * validasi wajib di aturan.js dan pemeriksaan di server.
 */
/** Semua field persetujuan yang sedang tampil, lintas langkah. */
const persetujuan = computed(() => langkah.value
    .filter((L) => syaratTerpenuhi(L.tampil_jika, jawaban.value))
    .flatMap((L) => bagianTampil(L.bagian, jawaban.value)
        .flatMap((B) => (B.berulang ? [] : fieldTampil(B.field, jawaban.value)))
        .filter((f) => f.tipe === 'consent')));

const bolehKirim = computed(() => langkah.value.every((L) => {
    if (!syaratTerpenuhi(L.tampil_jika, jawaban.value)) return true;

    return (L.bagian || []).every((B) => {
        if (!syaratTerpenuhi(B.tampil_jika, jawaban.value)) return true;

        return (B.field || [])
            .filter((x) => x.tipe === 'consent' && syaratTerpenuhi(x.tampil_jika, jawaban.value))
            .every((x) => jawaban.value?.[x.key] === true);
    });
}));

function kirim() {
    if (!periksa()) return;
    // Sapu seluruh langkah sebelum benar-benar mengirim: syarat tampil bisa
    // berubah setelah kandidat mengedit jawaban di langkah awal, sehingga
    // field yang tadinya tersembunyi jadi wajib.
    // Judul langkah ikut disebut: galat sapuan ini muncul di halaman pratinjau,
    // yang tidak menampilkan kolom isian apa pun — tanpa penunjuk langkah,
    // kandidat tahu ada yang kurang tapi tidak tahu harus kembali ke mana.
    const semua = langkah.value.flatMap((L) => periksaLangkah(L, jawaban.value).map((g) => `${L.judul} — ${g}`));
    if (semua.length) {
        galat.value = semua;
        return;
    }
    emit('kirim', jawaban.value);
}
</script>

<style scoped>
.t2 { max-width: 48rem; margin: 0 auto; }

/* ── Stepper ── */
.t2__steps { display: flex; gap: .3rem; overflow-x: auto; padding-bottom: .7rem; margin-bottom: 1rem; border-bottom: 1px solid rgba(11, 16, 51, .09); }
.t2__step { position: relative; flex: 1; min-width: 6rem; display: flex; flex-direction: column; align-items: center; gap: .3rem; border: 0; background: transparent; cursor: pointer; padding: .3rem .2rem; font: inherit; }
/* Garis penghubung antar langkah. Digambar oleh langkah KE-2 dan seterusnya,
   membentang mundur ke titik sebelumnya, sehingga jumlah garis selalu pas
   (n-1) berapa pun banyaknya langkah. Titik berdiameter 1.9rem, jadi garis
   berhenti .95rem + jarak nafas dari pusat masing-masing titik. */
.t2__step + .t2__step::before {
    content: '';
    position: absolute;
    top: calc(.3rem + .95rem - 1px);
    right: calc(50% + 1.25rem);
    left: calc(-50% + 1.25rem);
    height: 2px;
    border-radius: 2px;
    background: #e2e8f0;
    transition: background 220ms ease;
}
/* Ruas yang sudah dilewati ikut hijau - kemajuan terbaca sekilas. */
.t2__step.done::before, .t2__step.cur::before { background: linear-gradient(90deg, #34d399, #10b981); }
.t2__step:disabled { cursor: not-allowed; opacity: .45; }
.t2__dot { width: 1.9rem; height: 1.9rem; display: grid; place-items: center; border-radius: 50%; background: #eef2f7; color: #94a3b8; font-size: .8rem; transition: all 200ms ease; }
.t2__step.done .t2__dot { background: rgba(16, 185, 129, .15); color: #059669; }
.t2__step.cur .t2__dot { background: linear-gradient(140deg, #4f46e5, #7c3aed); color: #fff; box-shadow: 0 6px 14px -6px rgba(79, 70, 229, .9); }
.t2__lbl { font-size: 10.5px; font-weight: 600; color: #94a3b8; text-align: center; line-height: 1.3; }
.t2__step.cur .t2__lbl { color: #4338ca; }
.t2__step.done .t2__lbl { color: #059669; }

/* ── Panel ── */
.t2__kosong { display: flex; align-items: center; gap: .5rem; padding: 2rem; justify-content: center; color: #94a3b8; font-size: 13px; }
.t2__head { display: flex; align-items: flex-start; gap: .8rem; margin-bottom: 1.1rem; }
.t2__badge { flex: none; width: 2.5rem; height: 2.5rem; display: grid; place-items: center; border-radius: .9rem; background: linear-gradient(140deg, #4f46e5, #7c3aed); color: #fff; font-size: 1.05rem; }
.t2__no { font-size: 10.5px; font-weight: 700; letter-spacing: .1em; text-transform: uppercase; color: #7c3aed; }
.t2__head h3 { margin: .1rem 0 0; font-size: 1.1rem; font-weight: 800; color: #0f172a; letter-spacing: -.015em; }
.t2__head p { margin: .15rem 0 0; font-size: 12.5px; color: #64748b; line-height: 1.55; }

/* ── Halaman pratinjau ── */
.t2v__blok { border: 1px solid rgba(11, 16, 51, .1); border-radius: 14px; margin-bottom: .8rem; overflow: hidden; }
.t2v__blok-head { display: flex; align-items: center; justify-content: space-between; gap: .5rem; padding: .6rem .85rem; background: #f8fafc; border-bottom: 1px solid rgba(11, 16, 51, .07); font-size: 12.5px; font-weight: 800; color: #0f172a; }
.t2v__blok-head .bi { color: #7c3aed; }
.t2v__ubah { display: inline-flex; align-items: center; gap: .3rem; border: 1px solid rgba(79, 70, 229, .28); background: #fff; color: #4338ca; font: inherit; font-size: 11.5px; font-weight: 700; padding: .25rem .6rem; border-radius: 8px; cursor: pointer; transition: background 150ms ease; }
.t2v__ubah:hover { background: rgba(79, 70, 229, .08); }
.t2v__bagian { padding: .7rem .85rem; }
.t2v__bagian + .t2v__bagian { border-top: 1px dashed rgba(11, 16, 51, .09); }
.t2v__bagian h5 { margin: 0 0 .45rem; font-size: 11.5px; font-weight: 800; letter-spacing: .04em; text-transform: uppercase; color: #94a3b8; }

/* Dua kolom: label di kiri, jawaban di kanan. Sejajar walau jawabannya panjang. */
.t2v__list { display: grid; grid-template-columns: minmax(7rem, 38%) 1fr; gap: .3rem .8rem; margin: 0; }
.t2v__list dt { font-size: 12px; color: #64748b; line-height: 1.5; }
.t2v__list dd { margin: 0; font-size: 12.5px; font-weight: 700; color: #0f172a; line-height: 1.5; overflow-wrap: anywhere; }
/* Yang belum diisi ditandai, bukan disembunyikan — justru inilah yang paling
   perlu terlihat saat kandidat memeriksa sebelum mengirim. */
.t2v__list dd.is-kosong { color: #cbd5e1; font-weight: 500; }

.t2v__baris { display: flex; gap: .6rem; padding: .5rem .6rem; border: 1px solid rgba(11, 16, 51, .08); border-radius: 10px; background: #f8fafc; }
.t2v__baris + .t2v__baris { margin-top: .4rem; }
.t2v__baris-no { flex: none; width: 1.35rem; height: 1.35rem; display: grid; place-items: center; border-radius: 50%; background: rgba(79, 70, 229, .12); color: #4338ca; font-size: 10.5px; font-weight: 700; }
.t2v__baris .t2v__list { flex: 1; min-width: 0; }

.t2v__setuju { margin-top: 1rem; padding: .85rem; border: 1px solid rgba(79, 70, 229, .22); border-radius: 14px; background: rgba(79, 70, 229, .04); display: flex; flex-direction: column; gap: .6rem; }
.t2v__setuju h5 { margin: 0; font-size: 12px; font-weight: 800; color: #4338ca; display: flex; align-items: center; gap: .35rem; }

@media (max-width: 560px) {
    .t2v__list { grid-template-columns: 1fr; gap: 0 0; }
    .t2v__list dt { margin-top: .4rem; }
}

.t2__galat { margin-top: 1rem; padding: .75rem .9rem; border: 1px solid rgba(239, 68, 68, .25); border-radius: 12px; background: rgba(239, 68, 68, .07); color: #b91c1c; font-size: 12.5px; }
.t2__galat strong { display: flex; align-items: center; gap: .35rem; }
.t2__galat ul { margin: .4rem 0 0; padding-left: 1.2rem; line-height: 1.7; }

.t2__foot { display: flex; align-items: center; gap: .5rem; margin-top: 1.3rem; padding-top: .9rem; border-top: 1px solid rgba(11, 16, 51, .08); }
.t2__spacer { flex: 1; }
.t2__btn { border: 0; border-radius: .8rem; padding: .65rem 1.2rem; font: inherit; font-weight: 700; font-size: 13px; cursor: pointer; display: inline-flex; align-items: center; gap: .4rem; transition: filter 160ms ease, transform 160ms ease; }
.t2__btn:disabled { opacity: .45; cursor: not-allowed; }
.t2__kunci { display: flex; align-items: center; gap: .45rem; margin: .7rem 0 0; font-size: 12.5px; font-weight: 600; color: #b45309; }
.t2__kunci .bi { flex: none; }
/* Putaran pada tombol kirim: umpan balik bahwa permintaan sedang jalan.
   Tanpa ini kandidat mengira kliknya tidak terbaca lalu menekan lagi. */
.t2__spin { display: inline-block; animation: t2Spin .9s linear infinite; }
@keyframes t2Spin { to { transform: rotate(360deg); } }
.t2__btn--ghost { background: #f1f5f9; color: #475569; }
.t2__btn--primary { background: linear-gradient(140deg, #4f46e5, #7c3aed); color: #fff; box-shadow: 0 10px 22px -12px rgba(79, 70, 229, .9); }
.t2__btn--primary:hover:not(:disabled) { filter: brightness(1.06); transform: translateY(-1px); }

@media (max-width: 620px) {
    .t2__lbl { display: none; }
    .t2__step { min-width: 2.6rem; }
    .t2__step + .t2__step::before { right: calc(50% + 1.1rem); left: calc(-50% + 1.1rem); }
}
</style>
