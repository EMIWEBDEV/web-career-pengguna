<!-- WEB CAREER — "TIDAK MELANJUTKAN SELEKSI" (POV KANDIDAT).

     Dulu tombolnya melayang sendirian di tengah kartu jadwal, tepat di bawah
     status — terbaca seperti langkah berikutnya, padahal ia satu-satunya
     jawaban yang tidak bisa ditarik kembali, dan sulit dipakai di ponsel
     (masukan user 1 Okt 2026). Sekarang ia duduk di KAKI kartu: kalimat
     pengantar + tombol teks, seperti "batalkan pesanan" di aplikasi pemesanan
     — mudah ditemukan saat dicari, tidak mengundang ketukan tak sengaja.
     Formulirnya terbuka di tempat, lengkap dengan jalan keluar yang lebih
     ringan bagi kandidat yang sebenarnya hanya berhalangan di jam itu.

     Dipakai kartu jadwal Portal Kandidat (mode "kartu") dan halaman tautan
     email (mode "halaman", lewat KonfirmasiJawab). Tetap ada walau jawaban
     kehadiran sudah terkunci (keputusan user 1 Okt 2026); server hakimnya.

     ISIAN (keputusan user 2 Okt 2026):
       • alasan WAJIB dipilih;
       • alasan "Lainnya" (butuhCatatan) WAJIB dijelaskan dengan kalimat
         sungguhan — minimal 15 karakter, bukan ketikan acak, dan kata-katanya
         dikenal KAMUS (nspell + hunspell-id/en_US di Web Worker; kata yang
         tidak dikenal disebutkan). kalimat.js, cermin Kalimat.php di server;
       • alasan lain: kolom yang sama menjadi "pesan untuk tim" opsional, dan
         tujuannya disebutkan (masukan/kesan untuk tim rekrutmen);
       • centang "saya mengerti" ikut TERKIRIM dan diperiksa server — tombol
         yang dibuka paksa lewat inspect element tidak menjalankan apa pun,
         dan kalaupun permintaannya dikirim manual, server menolaknya. -->
<script setup>
import { computed, nextTick, onBeforeUnmount, reactive, ref, useId, watch } from 'vue';
import { galatDari, kirimJawabanKandidat } from '@utils/career/konfirmasi';
import { periksaDasar, periksaKalimatLengkap } from '@utils/career/kalimat';
import { siapkanEjaan } from '@utils/career/ejaan';

/** Sama dengan KonfirmasiJadwal::MUNDUR_PENJELASAN_MIN. */
const PENJELASAN_MIN = 15;

const props = defineProps({
    /** Bahan KonfirmasiJadwal::bahanJawab() — sama dengan KonfirmasiJawab. */
    bahan: { type: Object, required: true },
    /** kartu (Portal Kandidat) | halaman (tautan email) */
    mode: { type: String, default: 'kartu' },
});
const emit = defineEmits(['berubah']);

const idForm = `kmd-${useId()}`;

const k = computed(() => props.bahan?.konfirmasi || {});
const pilihan = computed(() => (props.bahan?.pilihan || []).find((p) => p.sinyalMundur) || null);
const alasan = computed(() => props.bahan?.alasan?.MUNDUR || []);
const catatanMaks = computed(() => Number(props.bahan?.aturanUsulan?.catatanMaks) || 500);

/** Tampil selama kandidat masih boleh menjawab — termasuk saat jawabannya terkunci. */
const tampil = computed(() => !!pilihan.value && !!k.value.bolehJawab && k.value.status !== pilihan.value.kode);

/**
 * Jalan keluar yang lebih ringan: kandidat yang hanya berhalangan di jam itu
 * tidak perlu menutup lamarannya. "Perlu jadwal lain" hanya disarankan bila
 * memang masih bisa dipakai (jatah ada, tidak ada permintaan terbuka, dan
 * jawabannya belum terkunci) — selain itu, sarannya menghubungi tim.
 */
const opsiJadwalLain = computed(() => {
    const p = (props.bahan?.pilihan || []).find((x) => x.bukaPermintaan);
    if (!p) return null;
    const jatah = props.bahan?.jatah || {};
    const minta = props.bahan?.permintaan;
    const sudahMenjawab = ['AKAN_HADIR', 'JADWAL_LAIN'].includes(k.value.status);
    if ((jatah.sisa ?? 1) <= 0 || minta?.status === 'TERBUKA') return null;
    if (sudahMenjawab && props.bahan?.ubah?.terkunci) return null;

    return p;
});

const buka = ref(false);
const kirim = ref(false);
const galat = ref('');
const f = reactive({ alasan: '', catatan: '', paham: false });
const formEl = ref(null);
// Galat isian baru ditampilkan sesudah kolomnya disentuh / tombol ditekan —
// bukan merah-merah begitu formulirnya dibuka.
const disentuh = ref(false);
const dicoba = ref(false);

const defAlasan = computed(() => alasan.value.find((a) => a.kode === f.alasan) || null);
/** "Lainnya" → kolom pesan berubah menjadi penjelasan WAJIB. */
const wajibJelas = computed(() => !!defAlasan.value?.butuhCatatan);
const panjang = computed(() => [...f.catatan.trim()].length);
const galatAlasan = computed(() => (f.alasan ? '' : 'Pilih salah satu alasan.'));

/**
 * PEMERIKSAAN KAMUS (nspell di Web Worker) — asinkron, ±0,4 detik sesudah
 * kandidat berhenti mengetik. Tanda dasar (panjang, huruf diulang, jumlah
 * kata) tetap seketika. Hasil untuk teks yang sudah diketik ulang dibuang.
 */
const dasarPenjelasan = computed(() => (wajibJelas.value ? periksaDasar(f.catatan, PENJELASAN_MIN) : ''));
const ejaan = reactive({ status: 'diam', teks: '', pesan: '' }); // diam | memeriksa | selesai
let nomorEjaan = 0;
let tmEjaan = null;

async function periksaEjaan() {
    const teks = f.catatan;
    const no = ++nomorEjaan;
    ejaan.status = 'memeriksa';
    const hasil = await periksaKalimatLengkap(teks, PENJELASAN_MIN);
    if (no !== nomorEjaan) return;
    Object.assign(ejaan, { status: 'selesai', teks, pesan: hasil.galat });
}

watch([() => f.catatan, wajibJelas], () => {
    clearTimeout(tmEjaan);
    nomorEjaan++;
    if (!wajibJelas.value || dasarPenjelasan.value) {
        Object.assign(ejaan, { status: 'diam', teks: '', pesan: '' });
        return;
    }
    ejaan.status = 'memeriksa';
    tmEjaan = setTimeout(periksaEjaan, 400);
});
// Kamus Indonesia mulai dimuat begitu "Lainnya" dipilih — saat kalimat pertama
// selesai diketik, pemeriksanya sudah siap.
watch(wajibJelas, (ya) => { if (ya) siapkanEjaan(); });
onBeforeUnmount(() => clearTimeout(tmEjaan));

const ejaanBerlaku = computed(() => ejaan.status === 'selesai' && ejaan.teks === f.catatan);
const memeriksaEjaan = computed(() => wajibJelas.value && !dasarPenjelasan.value && !ejaanBerlaku.value);
const galatPenjelasan = computed(() => {
    if (!wajibJelas.value) return '';
    if (dasarPenjelasan.value) return dasarPenjelasan.value;
    if (ejaanBerlaku.value) return ejaan.pesan;

    return 'Tunggu sebentar — kata-katanya sedang diperiksa.';
});
const galatPaham = computed(() => (f.paham ? '' : 'Centang pernyataan di atas untuk melanjutkan.'));
/** Semua syarat terpenuhi — satu-satunya jalan ke kirimMundur(). */
const siap = computed(() => !galatAlasan.value && !galatPenjelasan.value && !galatPaham.value && !kirim.value);

/**
 * Pernyataan terkirim, data terbarunya sedang dimuat induk — formulirnya
 * diganti penanda supaya tombolnya tidak mengundang klik kedua. Cadangan 20
 * detik bila muat ulang gagal.
 */
const memperbarui = ref(false);
let tmPerbarui = null;
watch(() => props.bahan, () => {
    memperbarui.value = false;
    clearTimeout(tmPerbarui);
});
onBeforeUnmount(() => clearTimeout(tmPerbarui));

async function alihkan() {
    buka.value = !buka.value;
    galat.value = '';
    if (!buka.value) return;
    await nextTick();
    formEl.value?.scrollIntoView?.({ behavior: 'smooth', block: 'nearest' });
}

function tutup() {
    buka.value = false;
    galat.value = '';
    dicoba.value = false;
}

function pilihAlasan(kode) {
    f.alasan = kode;
}

async function kirimMundur() {
    // Diperiksa ULANG di sini, bukan hanya lewat atribut `disabled` — atribut
    // itu bisa dihapus lewat inspect element. Server memeriksa hal yang sama.
    dicoba.value = true;
    // Penjelasan yang belum selesai diperiksa kamus (mis. tombol ditekan
    // sebelum jeda mengetik habis) diperiksa sekarang juga — untuk teks yang
    // ada SAAT INI, tanpa ikut antrean pemeriksaan sambil-mengetik.
    if (wajibJelas.value && !dasarPenjelasan.value && !ejaanBerlaku.value) {
        await nextTick();
        clearTimeout(tmEjaan);
        nomorEjaan++;
        const teks = f.catatan;
        const hasil = await periksaKalimatLengkap(teks, PENJELASAN_MIN);
        if (teks === f.catatan) Object.assign(ejaan, { status: 'selesai', teks, pesan: hasil.galat });
    }
    if (!siap.value || !pilihan.value) {
        galat.value = galatAlasan.value || galatPenjelasan.value || galatPaham.value;
        return;
    }
    kirim.value = true;
    galat.value = '';
    try {
        const res = await kirimJawabanKandidat(props.bahan?.url?.jawab, {
            kode: pilihan.value.kode,
            statusDilihat: k.value.status,
            alasan: f.alasan,
            catatan: f.catatan.trim() || null,
            paham: f.paham === true,
        });
        buka.value = false;
        memperbarui.value = true;
        clearTimeout(tmPerbarui);
        tmPerbarui = setTimeout(() => { memperbarui.value = false; }, 20000);
        emit('berubah', res?.message || 'Pernyataanmu sudah kami terima.');
    } catch (e) {
        const g = galatDari(e, 'Pernyataan gagal dikirim. Coba lagi.');
        galat.value = g.pesan;
        // Status di server sudah berbeda → tampilkan keadaan terbarunya.
        if ([409, 422].includes(g.status) && g.result?.status && g.result.status !== k.value.status) {
            emit('berubah', null);
        }
    } finally {
        kirim.value = false;
    }
}
</script>

<template>
    <div v-if="tampil || memperbarui" class="kmd" :class="[`kmd--${mode}`, { 'is-buka': buka }]">
        <p v-if="memperbarui" class="kmd-memuat" role="status">
            <span class="kmd-putar" aria-hidden="true"></span> Pernyataanmu terkirim — memperbarui status…
        </p>

        <template v-else>
            <div class="kmd-baris">
                <p class="kmd-tanya">
                    <i class="bi bi-signpost-split" aria-hidden="true"></i>
                    <span>Ada perubahan rencana?</span>
                </p>
                <button
                    type="button"
                    class="kmd-pemicu"
                    :aria-expanded="String(buka)"
                    :aria-controls="idForm"
                    @click="alihkan"
                >
                    <i class="bi bi-x-circle" aria-hidden="true"></i>
                    <span>{{ pilihan.label }}</span>
                    <i class="bi kmd-pemicu__panah" :class="buka ? 'bi-chevron-up' : 'bi-chevron-down'" aria-hidden="true"></i>
                </button>
            </div>

            <Transition name="kmd-geser">
                <div
                    v-if="buka"
                    :id="idForm"
                    ref="formEl"
                    class="kmd-form"
                    role="group"
                    :aria-labelledby="`${idForm}-judul`"
                >
                    <h3 :id="`${idForm}-judul`">
                        <i class="bi bi-exclamation-octagon-fill" aria-hidden="true"></i>
                        Yakin tidak melanjutkan seleksi?
                    </h3>
                    <p class="kmd-akibat">
                        Lamaranmu untuk posisi ini akan ditutup oleh tim rekrutmen, dan pernyataan ini
                        <b>tidak bisa dibatalkan</b>.
                    </p>

                    <p class="kmd-ringan">
                        <i class="bi bi-lightbulb" aria-hidden="true"></i>
                        <span v-if="opsiJadwalLain">
                            Hanya berhalangan di waktu ini? Pilih <b>“{{ opsiJadwalLain.label }}”</b> saja — lamaranmu tetap berjalan.
                        </span>
                        <span v-else>
                            Hanya berhalangan di waktu ini? Hubungi tim rekrutmen<template v-if="k.kontak"> di <b>{{ k.kontak }}</b></template> — lamaranmu tetap berjalan.
                        </span>
                    </p>

                    <div v-if="alasan.length" class="kmd-bag">
                        <span class="kmd-label">Alasan <b class="kmd-wajib">wajib</b></span>
                        <div class="kmd-alasan" role="radiogroup" aria-label="Alasan tidak melanjutkan" aria-required="true">
                            <button
                                v-for="a in alasan"
                                :key="a.kode"
                                type="button"
                                role="radio"
                                class="kmd-opsi"
                                :class="{ on: f.alasan === a.kode }"
                                :aria-checked="String(f.alasan === a.kode)"
                                @click="pilihAlasan(a.kode)"
                            >{{ a.nama }}</button>
                        </div>
                        <p v-if="dicoba && galatAlasan" class="kmd-galat-kecil">{{ galatAlasan }}</p>
                    </div>

                    <!-- Satu kolom, dua peran: alasan "Lainnya" → PENJELASAN wajib
                         (kalimat sungguhan); alasan lain → PESAN opsional yang
                         tujuannya disebutkan. -->
                    <label class="kmd-bag">
                        <span v-if="wajibJelas" class="kmd-label">Jelaskan alasanmu <b class="kmd-wajib">wajib</b></span>
                        <span v-else class="kmd-label">Pesan untuk tim rekrutmen <em>(opsional)</em></span>
                        <span class="kmd-guna">
                            <template v-if="wajibJelas">
                                Ceritakan dalam satu-dua kalimat: apa yang terjadi dan kenapa kamu tidak bisa melanjutkan.
                                Minimal {{ PENJELASAN_MIN }} karakter.
                            </template>
                            <template v-else>
                                Untuk masukan atau kesanmu tentang proses seleksi — misalnya hal yang perlu kami perbaiki.
                                Hanya dibaca tim rekrutmen dan tidak mengubah apa pun.
                            </template>
                        </span>
                        <textarea
                            v-model="f.catatan"
                            :maxlength="catatanMaks"
                            rows="3"
                            class="kmd-input"
                            :class="{ 'is-galat': wajibJelas && (disentuh || dicoba) && galatPenjelasan && !memeriksaEjaan }"
                            :aria-required="String(wajibJelas)"
                            :placeholder="wajibJelas
                                ? 'Contoh: Saya harus pindah ke luar kota karena urusan keluarga yang mendesak.'
                                : 'Contoh: Terima kasih atas kesempatannya, prosesnya jelas dan cepat.'"
                            @blur="disentuh = true"
                        ></textarea>
                        <!-- Umpan balik langsung: hitungan sampai cukup panjang, lalu
                             pemeriksaan kamus, lalu hasilnya (kata yang tidak
                             dikenal disebutkan, seperti monkeytype). -->
                        <span
                            v-if="wajibJelas"
                            class="kmd-hitung"
                            :class="{
                                'is-ok': !galatPenjelasan,
                                'is-cek': memeriksaEjaan,
                                'is-galat': galatPenjelasan && !memeriksaEjaan && (panjang >= PENJELASAN_MIN || dicoba),
                            }"
                            aria-live="polite"
                        >
                            <template v-if="memeriksaEjaan"><span class="kmd-putar kmd-putar--kecil" aria-hidden="true"></span> Memeriksa kata-kata…</template>
                            <template v-else-if="!galatPenjelasan"><i class="bi bi-check-circle-fill"></i> Terbaca sebagai kalimat</template>
                            <template v-else-if="panjang >= PENJELASAN_MIN || dicoba">{{ galatPenjelasan }}</template>
                            <template v-else>{{ panjang }}/{{ PENJELASAN_MIN }} karakter</template>
                        </span>
                    </label>

                    <label class="kmd-centang" :class="{ 'is-galat': dicoba && galatPaham }">
                        <input v-model="f.paham" type="checkbox" aria-required="true" />
                        <span>Saya mengerti lamaran saya akan ditutup.</span>
                    </label>

                    <p v-if="galat" class="kmd-galat" role="alert">
                        <i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i> {{ galat }}
                    </p>

                    <div class="kmd-aksi">
                        <button type="button" class="kmd-btn kmd-btn--teks" @click="tutup">Batal</button>
                        <button
                            type="button"
                            class="kmd-btn kmd-btn--merah"
                            :disabled="!siap"
                            :aria-disabled="String(!siap)"
                            @click="kirimMundur"
                        >
                            <span v-if="kirim" class="kmd-putar kmd-putar--terang" aria-hidden="true"></span>
                            Ya, saya tidak melanjutkan
                        </button>
                    </div>
                </div>
            </Transition>
        </template>
    </div>
</template>

<style scoped>
.kmd {
    --kmd-merah: #b91c1c;
    color: #0f172a;
}

/* KARTU portal: kaki kartu jadwal — garis pemisah tipis, latar sedikit
   lebih redup dari badan kartu supaya terbaca sebagai "pilihan lain". */
.kmd--kartu {
    padding: 11px 18px 12px;
    border-top: 1px solid #eef0f7;
    background: #fbfbfe;
}
/* HALAMAN tautan email: berdiri sebagai blok sendiri di bawah jawaban. */
.kmd--halaman {
    padding: 12px 16px;
    border-radius: 18px;
    border: 1px solid #e5e7f5;
    background: rgba(255, 255, 255, 0.72);
}

.kmd-baris {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 2px 12px;
}
.kmd-tanya {
    margin: 0;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-size: 12.5px;
    font-weight: 600;
    color: #64748b;
}
.kmd-tanya .bi {
    color: #94a3b8;
}
.kmd-pemicu {
    appearance: none;
    display: inline-flex;
    align-items: center;
    gap: 7px;
    min-height: 40px;
    margin: 0 -10px 0 0;
    padding: 6px 10px;
    border: 0;
    border-radius: 10px;
    background: transparent;
    color: var(--kmd-merah);
    font: inherit;
    font-size: 12.5px;
    font-weight: 800;
    cursor: pointer;
    transition: background 0.15s ease;
}
.kmd-pemicu:hover {
    background: rgba(220, 38, 38, 0.07);
}
.kmd-pemicu:focus-visible {
    outline: 2px solid #fca5a5;
    outline-offset: 1px;
}
.kmd-pemicu__panah {
    font-size: 11px;
    opacity: 0.75;
}

.kmd-form {
    margin-top: 10px;
    padding: 15px 16px 14px;
    border-radius: 14px;
    border: 1px solid #fecaca;
    background: #fff;
    box-shadow: 0 14px 30px -24px rgba(127, 29, 29, 0.55);
}
.kmd-form h3 {
    margin: 0;
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 14.5px;
    font-weight: 800;
    color: #991b1b;
}
.kmd-akibat {
    margin: 6px 0 0;
    font-size: 13px;
    line-height: 1.55;
    color: #475569;
}
.kmd-ringan {
    margin: 12px 0 0;
    padding: 9px 11px;
    border-radius: 11px;
    background: #f8fafc;
    border: 1px solid #eef0f7;
    display: flex;
    align-items: flex-start;
    gap: 8px;
    font-size: 12.5px;
    line-height: 1.55;
    color: #334155;
}
.kmd-ringan .bi {
    flex: none;
    margin-top: 2px;
    color: #d97706;
}
.kmd-bag {
    display: flex;
    flex-direction: column;
    gap: 7px;
    margin-top: 14px;
}
.kmd-label {
    font-size: 12.5px;
    font-weight: 800;
    color: #1e293b;
}
.kmd-label em {
    font-style: normal;
    font-weight: 600;
    color: #94a3b8;
}
.kmd-wajib {
    display: inline-block;
    margin-left: 4px;
    padding: 1px 7px;
    border-radius: 999px;
    background: #fef2f2;
    color: #b91c1c;
    font-size: 10.5px;
    font-weight: 800;
    letter-spacing: 0.02em;
    vertical-align: 1px;
}
/* Tujuan kolom: dibaca SEBELUM mengetik, bukan placeholder yang hilang. */
.kmd-guna {
    margin-top: -3px;
    font-size: 12px;
    line-height: 1.5;
    font-weight: 500;
    color: #64748b;
}
.kmd-hitung {
    font-size: 12px;
    font-weight: 700;
    color: #94a3b8;
}
.kmd-hitung.is-ok {
    color: #15803d;
}
.kmd-hitung.is-cek {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    color: #4338ca;
}
.kmd-putar.kmd-putar--kecil {
    width: 12px;
    height: 12px;
    border-width: 2px;
}
.kmd-hitung.is-galat {
    color: #b91c1c;
    font-weight: 600;
    line-height: 1.5;
}
.kmd-galat-kecil {
    margin: 0;
    font-size: 12px;
    font-weight: 700;
    color: #b91c1c;
}
.kmd-alasan {
    display: flex;
    flex-wrap: wrap;
    gap: 7px;
}
.kmd-opsi {
    appearance: none;
    min-height: 36px;
    padding: 7px 12px;
    border-radius: 999px;
    border: 1.5px solid #e2e8f0;
    background: #fff;
    font: inherit;
    font-size: 12.5px;
    font-weight: 700;
    color: #334155;
    cursor: pointer;
    transition: border-color 0.15s ease, background 0.15s ease;
}
.kmd-opsi:hover {
    border-color: #fca5a5;
}
.kmd-opsi.on {
    border-color: #dc2626;
    background: #fef2f2;
    color: #991b1b;
}
.kmd-input {
    width: 100%;
    box-sizing: border-box;
    border: 1.5px solid #e2e8f0;
    border-radius: 12px;
    padding: 10px 12px;
    font: inherit;
    font-size: 13.5px;
    font-weight: 500;
    color: #0f172a;
    background: #fff;
    resize: vertical;
}
.kmd-input:focus {
    outline: none;
    border-color: #f87171;
    box-shadow: 0 0 0 3px rgba(248, 113, 113, 0.16);
}
.kmd-input.is-galat {
    border-color: #ef4444;
}
.kmd-centang {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    margin-top: 14px;
    font-size: 13px;
    font-weight: 600;
    color: #1e293b;
    cursor: pointer;
}
.kmd-centang.is-galat {
    color: #b91c1c;
}
.kmd-centang input {
    flex: none;
    width: 18px;
    height: 18px;
    margin: 1px 0 0;
    accent-color: #dc2626;
}
.kmd-galat {
    margin: 12px 0 0;
    display: flex;
    gap: 8px;
    align-items: flex-start;
    font-size: 12.5px;
    font-weight: 700;
    color: #991b1b;
}
.kmd-aksi {
    display: flex;
    flex-wrap: wrap;
    justify-content: flex-end;
    gap: 8px;
    margin-top: 14px;
}
.kmd-btn {
    appearance: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    min-height: 42px;
    padding: 9px 16px;
    border-radius: 12px;
    border: 0;
    font: inherit;
    font-size: 13px;
    font-weight: 800;
    cursor: pointer;
}
.kmd-btn:focus-visible {
    outline: 3px solid #fecaca;
    outline-offset: 2px;
}
.kmd-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}
.kmd-btn--teks {
    background: transparent;
    color: #475569;
}
.kmd-btn--teks:hover {
    background: #f1f5f9;
}
.kmd-btn--merah {
    color: #fff;
    background: #dc2626;
    box-shadow: 0 10px 22px -14px rgba(220, 38, 38, 0.95);
}
.kmd-btn--merah:not(:disabled):hover {
    background: #c81e1e;
}

.kmd-memuat {
    margin: 0;
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 12.5px;
    font-weight: 700;
    color: #4338ca;
}
.kmd-putar {
    flex: none;
    display: inline-block;
    width: 15px;
    height: 15px;
    border-radius: 50%;
    border: 2.5px solid rgba(79, 70, 229, 0.25);
    border-top-color: #4f46e5;
    animation: kmd-putar 0.8s linear infinite;
}
.kmd-putar--terang {
    border-color: rgba(255, 255, 255, 0.45);
    border-top-color: #fff;
}
@keyframes kmd-putar {
    to {
        transform: rotate(360deg);
    }
}

.kmd-geser-enter-active,
.kmd-geser-leave-active {
    transition: opacity 0.18s ease, transform 0.18s ease;
}
.kmd-geser-enter-from,
.kmd-geser-leave-to {
    opacity: 0;
    transform: translateY(-4px);
}

/* Wadah sempit (kartu jadwal di ponsel): tombol aksi formulir selebar kotak —
   sasaran ketuk yang lega, dan "Ya, saya tidak melanjutkan" tidak terdesak ke
   tepi. Patokannya lebar kartu/komponen induk (container), bukan layar. */
@container (max-width: 480px) {
    .kmd--kartu {
        padding: 10px 14px 12px;
    }
    .kmd-form {
        padding: 14px 13px 13px;
    }
    .kmd-baris {
        flex-direction: column;
        align-items: flex-start;
    }
    .kmd-pemicu {
        margin: 0 0 0 -10px;
    }
    .kmd-aksi {
        flex-direction: column-reverse;
    }
    .kmd-aksi .kmd-btn {
        width: 100%;
    }
}
@media (prefers-reduced-motion: reduce) {
    .kmd-geser-enter-active,
    .kmd-geser-leave-active {
        transition: none;
    }
    .kmd-putar {
        animation-duration: 2.4s;
    }
}
</style>
