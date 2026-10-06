<!--
  KEADAAN PANEL — satu tampilan untuk tiga keadaan yang selalu berulang di
  monitoring: sedang memuat, gagal memuat, dan tidak ada isi.

  Sebelumnya tiap komponen menuliskannya sendiri-sendiri dengan kelas berbeda
  (.wcm-det__load, .wcm-sl__load, .wcm-sd__load, .wcm-fg__kosong) sehingga satu
  modul punya empat gaya untuk hal yang sama — dan "gagal memuat" tampil sama
  persis dengan "belum ada data", padahal artinya jauh berbeda: yang satu perlu
  dicoba lagi, yang satu memang belum ada isinya.

  Bentuknya mengikuti primitif .wca-empty yang sudah dipakai seluruh halaman
  admin lain (Kandidat, MasterAkun, MasterKampus, …) supaya monitoring tidak
  terasa seperti modul asing.
-->
<template>
    <div class="wcm-keadaan" :class="[`is-${keadaan}`, { 'is-rapat': rapat }]" role="status" aria-live="polite">
        <i class="bi" :class="[ikonAkhir, { 'wcm-spin': keadaan === 'memuat' }]"></i>
        <h4>{{ teks || teksBawaan }}</h4>
        <p v-if="ket">{{ ket }}</p>

        <!-- Tombol coba lagi HANYA pada keadaan galat. Kondisi kosong tidak
             menawarkan tombol: mengulang permintaan tidak akan memunculkan
             data yang memang belum ada. -->
        <button v-if="keadaan === 'galat'" type="button" class="wca-btn wca-btn--primary wca-btn--sm" @click="$emit('ulang')">
            <i class="bi bi-arrow-clockwise"></i> Coba lagi
        </button>
    </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    keadaan: { type: String, default: 'kosong' }, // memuat | galat | kosong
    ikon: { type: String, default: '' },
    teks: { type: String, default: '' },
    ket: { type: String, default: '' },
    // Versi ringkas untuk laci/kolom sempit — padding & ikon diperkecil.
    rapat: { type: Boolean, default: false },
});

defineEmits(['ulang']);

const IKON = { memuat: 'bi-arrow-repeat', galat: 'bi-wifi-off', kosong: 'bi-inbox' };
const TEKS = { memuat: 'Memuat…', galat: 'Gagal memuat data', kosong: 'Belum ada data' };

const ikonAkhir = computed(() => props.ikon || IKON[props.keadaan] || IKON.kosong);
const teksBawaan = computed(() => TEKS[props.keadaan] || TEKS.kosong);
</script>

<style scoped>
.wcm-keadaan {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    gap: 0.35rem;
    padding: 3rem 1rem;
    color: #94a3b8;
}
.wcm-keadaan > .bi {
    font-size: 2.4rem;
    color: #cbd5e1;
    line-height: 1;
}
.wcm-keadaan h4 {
    margin: 0.4rem 0 0;
    font-size: 0.9rem;
    font-weight: 900;
    color: #334155;
}
.wcm-keadaan p {
    margin: 0;
    max-width: 38ch;
    font-size: 0.78rem;
    line-height: 1.55;
    color: #94a3b8;
}
.wcm-keadaan .wca-btn {
    margin-top: 0.7rem;
}

/* Galat bukan sekadar "tidak ada" — diberi warna agar dibaca sebagai masalah
   yang bisa ditindaklanjuti, bukan keadaan normal. */
.wcm-keadaan.is-galat > .bi {
    color: #fb7185;
}
.wcm-keadaan.is-galat h4 {
    color: #be123c;
}

/* Memuat: ikon memakai warna aksen supaya terbaca "sedang bekerja",
   bukan "kosong dan diam". */
.wcm-keadaan.is-memuat > .bi {
    color: #a5b4fc;
}

/* Versi ringkas — dipakai di laci sempit & kolom papan. */
.wcm-keadaan.is-rapat {
    padding: 1.5rem 0.75rem;
    gap: 0.25rem;
}
.wcm-keadaan.is-rapat > .bi {
    font-size: 1.5rem;
}
.wcm-keadaan.is-rapat h4 {
    margin: 0.25rem 0 0;
    font-size: 0.8rem;
}
.wcm-keadaan.is-rapat p {
    font-size: 0.73rem;
}

/* Putaran ikon "memuat" — didefinisikan lokal supaya komponen ini berdiri
   sendiri tanpa bergantung pada gaya induknya. */
.wcm-spin {
    display: inline-block;
    animation: wcmKeadaanSpin 900ms linear infinite;
}
@keyframes wcmKeadaanSpin {
    to {
        transform: rotate(360deg);
    }
}
@media (prefers-reduced-motion: reduce) {
    .wcm-spin {
        animation: none;
    }
}
</style>
