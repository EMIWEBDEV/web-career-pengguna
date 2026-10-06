<!-- WEB CAREER — Paragraf panjang yang DILIPAT, dengan tombol buka.

     KENAPA ADA
     Uraian tugas pada riwayat kerja/organisasi tidak punya batas panjang: satu
     kandidat menulis satu kalimat, kandidat berikutnya menulis lima paragraf.
     Dibiarkan utuh, satu baris riwayat memenuhi seluruh tinggi drawer dan baris
     kedua tak pernah terlihat kecuali peninjau menggulung jauh — padahal yang
     dicari sering justru pola antar-baris, bukan isi satu baris.

     KENAPA DIUKUR, BUKAN DIHITUNG DARI JUMLAH HURUF
     Ambang "lebih dari sekian karakter" selalu meleset: teks yang sama muat tiga
     baris di drawer lebar dan tujuh baris di layar sempit. Yang menentukan perlu
     tidaknya tombol adalah APAKAH TEKSNYA BENAR-BENAR TERPOTONG — dan itu hanya
     diketahui setelah dirender. Karena itu tombolnya muncul dari hasil ukur
     (scrollHeight > clientHeight), dan diukur ulang setiap lebarnya berubah.
     Akibatnya tidak pernah ada tombol "Selengkapnya" yang, ketika ditekan, tidak
     menampilkan apa pun yang baru. -->
<template>
    <div class="uln">
        <!-- Lipatan menempel selama belum dibuka — TIDAK dilepas hanya karena
             teksnya pendek. Kalau dilepas, elemennya berhenti meluap dan
             scrollHeight selalu sama dengan clientHeight: keputusan "tidak
             terpotong" jadi tak bisa dibatalkan, dan teks yang seharusnya
             terpotong lagi saat drawer dipersempit tetap terpampang penuh.
             Untuk teks di bawah batas baris, melipat tidak mengubah apa pun
             yang terlihat. -->
        <p ref="teksEl" class="uln__teks" :class="{ 'is-lipat': !buka }">{{ teks }}</p>
        <button
            v-if="terpotong" type="button" class="uln__more"
            :aria-expanded="buka" @click="buka = !buka"
        >
            {{ buka ? 'Ringkas' : 'Selengkapnya' }}
            <svg
                class="uln__morei" :class="{ 'is-up': buka }"
                width="13" height="13" viewBox="0 0 24 24" fill="none"
                stroke="currentColor" stroke-width="3" stroke-linecap="round"
            ><path d="M6 9l6 6 6-6" /></svg>
        </button>
    </div>
</template>

<script setup>
import { ref, watch, onMounted, onBeforeUnmount, nextTick } from 'vue';

const props = defineProps({
    teks: { type: String, default: '' },
    /** Berapa baris yang terlihat saat terlipat. */
    baris: { type: Number, default: 4 },
});

const teksEl = ref(null);
const buka = ref(false);
// Hanya menentukan MUNCUL TIDAKNYA TOMBOL — lipatannya sendiri selalu menempel
// selama belum dibuka (lihat template). Mulai dari false: tombol yang sempat
// berkedip lalu hilang lebih mengganggu daripada tombol yang telat muncul
// satu frame.
const terpotong = ref(false);
let pengamat = null;

function ukur() {
    const el = teksEl.value;
    // Saat dibuka teksnya memang tidak terpotong — mengukur di keadaan itu akan
    // menyimpulkan "tidak perlu tombol" lalu menghilangkan tombol Ringkas-nya.
    if (!el || buka.value) return;

    // Elemen yang sedang tersembunyi (riwayatnya ditutup) berukuran 0; ukur ulang
    // nanti saat ia terlihat — ResizeObserver yang membangunkannya.
    if (!el.clientHeight) return;

    terpotong.value = el.scrollHeight - el.clientHeight > 2;
}

onMounted(() => {
    nextTick(ukur);
    if (typeof ResizeObserver !== 'undefined') {
        pengamat = new ResizeObserver(ukur);
        pengamat.observe(teksEl.value);
    }
});

onBeforeUnmount(() => pengamat?.disconnect());

watch(() => props.teks, () => {
    buka.value = false;
    nextTick(ukur);
});
</script>

<style scoped>
.uln__teks {
    margin: 3px 0 0; font-size: 12.5px; font-weight: 500; color: #475569; line-height: 1.62;
    white-space: pre-line; overflow-wrap: anywhere;
}
/* -webkit-line-clamp: memotong TEPAT di batas baris, bukan di tinggi tetap yang
   memenggal huruf jadi separuh. Masih bentuk yang paling luas didukung. */
.uln__teks.is-lipat {
    display: -webkit-box; -webkit-box-orient: vertical;
    -webkit-line-clamp: v-bind(baris); line-clamp: v-bind(baris);
    overflow: hidden;
}
.uln__more {
    display: inline-flex; align-items: center; gap: 4px; margin-top: 6px; padding: 3px 9px;
    appearance: none; border: 1px solid #dfe3f3; border-radius: 999px; background: #f5f6fe;
    color: #4f46e5; font: inherit; font-size: 11px; font-weight: 800; cursor: pointer;
    transition: background .16s ease, border-color .16s ease;
}
.uln__more:hover { background: #e9eafd; border-color: #c7d2fe; }
.uln__morei { transition: transform .2s ease; }
.uln__morei.is-up { transform: rotate(180deg); }
</style>
