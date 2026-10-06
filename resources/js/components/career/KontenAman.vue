<!-- ══════════════════════════════════════════════════════════
     WEB CAREER — Perender HTML berformat (catatan penilaian, jawaban FAQ).

     KENAPA ADA KOMPONEN SENDIRI
     Sejak catatan hasil wawancara & tes offline ditulis di editor berformat,
     nilainya adalah HTML — bukan teks. Menampilkannya dengan interpolasi biasa
     ({{ }}) membuat tag mentah terbaca di layar; menampilkannya dengan v-html
     telanjang memindahkan tanggung jawab penyaringan ke setiap tempat yang
     kebetulan menampilkannya, dan satu yang lupa sudah cukup.

     Komponen ini menutup keduanya: SATU pintu, penyaringan selalu jalan.

     LAPIS KEDUA, BUKAN SATU-SATUNYA
     Penyaring sesungguhnya ada di server (App\Support\Career\HtmlBersih) saat
     data disimpan. DOMPurify di sini menjaga isi yang sudah telanjur tersimpan
     sebelum penyaring itu ada, dan isi yang datang lewat jalur lain.

     DAFTAR-IZINNYA SENGAJA SAMA PERSIS dengan daftar di HtmlBersih. Kalau
     berbeda, format yang lolos server justru dibuang di layar — dan admin
     melihat catatannya berubah sendiri tanpa penjelasan.
     ══════════════════════════════════════════════════════════ -->
<template>
    <div v-if="adaIsi" class="ka" :class="{ 'is-ringkas': ringkas }" v-html="bersih"></div>
    <p v-else-if="kosong" class="ka-kosong">{{ kosong }}</p>
</template>

<script>
import DOMPurify from 'dompurify';

const TAG = ['p', 'br', 'strong', 'b', 'em', 'i', 'u', 's', 'ul', 'ol', 'li', 'h3', 'h4', 'blockquote', 'a', 'img'];
// `class` diizinkan HANYA demi perataan Quill (ql-align-*); nilainya sudah
// disaring daftar-izin di server (HtmlBersih), dan DOMPurify tetap membuang
// atribut lain apa pun.
const ATTR = ['href', 'target', 'rel', 'src', 'alt', 'loading', 'class'];

export default {
    props: {
        html: { type: String, default: '' },
        /** Batasi tinggi & beri gulir sendiri — untuk baris daftar yang sempit. */
        ringkas: { type: Boolean, default: false },
        /** Kalimat pengganti bila isinya kosong. Kosongkan = tidak menampilkan apa pun. */
        kosong: { type: String, default: '' },
    },
    computed: {
        bersih() {
            return DOMPurify.sanitize(this.html || '', { ALLOWED_TAGS: TAG, ALLOWED_ATTR: ATTR });
        },
        /**
         * Isi dinilai SETELAH disaring, bukan dari panjang string mentahnya.
         * "<p><br></p>" panjangnya 11 karakter tapi tidak berisi apa pun, dan
         * merendernya menyisakan blok kosong yang terbaca sebagai catatan ada.
         */
        adaIsi() {
            const s = this.bersih;

            return !!s && (s.includes('<img') || s.replace(/<[^>]*>/g, '').trim() !== '');
        },
    },
};
</script>

<style scoped>
.ka {
    font-size: 0.82rem;
    line-height: 1.65;
    color: #475569;
    overflow-wrap: anywhere;
}
.ka.is-ringkas {
    max-height: 190px;
    overflow-y: auto;
}
.ka :deep(p) {
    margin: 0 0 0.4rem;
}
.ka :deep(p:last-child) {
    margin-bottom: 0;
}
.ka :deep(ul),
.ka :deep(ol) {
    margin: 0 0 0.4rem;
    padding-left: 1.15rem;
}
.ka :deep(h3),
.ka :deep(h4) {
    margin: 0.5rem 0 0.3rem;
    font-size: 0.86rem;
    font-weight: 800;
    color: #1e293b;
}
.ka :deep(blockquote) {
    margin: 0.4rem 0;
    padding: 0.2rem 0 0.2rem 0.7rem;
    border-left: 3px solid #cbd5e1;
    color: #64748b;
}
.ka :deep(a) {
    color: #4f46e5;
    font-weight: 700;
}
/* Potret ponsel beresolusi penuh akan menjebol lebar kolom pemuatnya. */
/* Perataan Quill — tanpa ini gambar yang ditengahkan penilai tetap tampil
   menempel di kiri saat catatannya dibaca ulang. */
.ka :deep(.ql-align-center) { text-align: center; }
.ka :deep(.ql-align-right) { text-align: right; }
.ka :deep(.ql-align-justify) { text-align: justify; }
/* Gambar mengikuti perataan bloknya: `display:block` + margin auto membuat
   text-align tidak berlaku padanya, jadi dipakai inline-block. */
.ka :deep(.ql-align-center img) { margin-left: auto; margin-right: auto; }
.ka :deep(.ql-align-right img) { margin-left: auto; }
.ka :deep(img) {
    display: block;
    max-width: 100%;
    height: auto;
    margin: 0.4rem 0;
    border-radius: 0.5rem;
    border: 1px solid #e2e8f0;
}
.ka-kosong {
    margin: 0;
    font-size: 0.8rem;
    color: #94a3b8;
}
</style>
