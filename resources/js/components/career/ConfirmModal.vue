<!-- WEB CAREER — Modal konfirmasi reusable (hapus / aksi berbahaya). Elegan, tombol danger. -->
<template>
    <AdminModal :show="show" :title="title" :subtitle="subtitle" :icon="icon" :size="size" @close="$emit('cancel')">
        <!-- MODE FORMULIR. Isi modal ini biasanya satu kalimat konfirmasi, jadi
             bawaannya rata tengah di dalam <p> dan diberi ikon peringatan besar.
             Untuk modal yang isinya BORANG — label, input, pilihan — bentuk itu
             salah pada dua hal sekaligus: <div> di dalam <p> dikeluarkan sendiri
             oleh peramban (tata letaknya jadi tak bisa diatur), dan label rata
             tengah membuat borang tidak punya garis baca. -->
        <div v-if="formMode" class="cfm cfm--form">
            <slot />
            <p v-if="note" class="cfm__note">{{ note }}</p>
        </div>
        <div v-else class="cfm">
            <span class="cfm__ico" :class="danger ? 'is-danger' : 'is-warn'">
                <i class="bi" :class="danger ? 'bi-trash3' : 'bi-exclamation-triangle'"></i>
            </span>
            <p class="cfm__txt"><slot>Yakin ingin melanjutkan tindakan ini?</slot></p>
            <p v-if="note" class="cfm__note">{{ note }}</p>
        </div>
        <template #footer>
            <button class="wca-btn wca-btn--ghost" type="button" @click="$emit('cancel')"><i class="bi bi-x-circle"></i> {{ cancelLabel }}</button>
            <button class="wca-btn" :class="danger ? 'wca-btn--danger' : 'wca-btn--dark'" type="button" :disabled="busy || confirmDisabled" :onClick="busy || confirmDisabled ? null : () => $emit('confirm')">
                <span v-if="busy" class="wca-spin" aria-hidden="true"></span>
                <i v-else class="bi" :class="confirmIcon || (danger ? 'bi-trash' : 'bi-check-lg')"></i>
                {{ busy ? busyLabel : confirmLabel }}
            </button>
        </template>
    </AdminModal>
</template>

<script>
import AdminModal from './AdminModal.vue';

export default {
    components: { AdminModal },
    props: {
        show: { type: Boolean, default: false },
        title: { type: String, default: 'Konfirmasi' },
        subtitle: { type: String, default: 'Tindakan ini tidak dapat dibatalkan' },
        icon: { type: String, default: 'bi-exclamation-octagon' },
        /* Ukuran diteruskan apa adanya ke AdminModal (validasinya di sana).
           Konfirmasi sebaris memang cukup dengan bawaan, tapi modal ber-formMode
           bisa berisi borang penuh — dan borang yang dijejalkan ke lebar
           konfirmasi memaksa tiap kartu pilihan menumpuk satu per baris. */
        size: { type: String, default: '' },
        note: { type: String, default: '' },
        danger: { type: Boolean, default: true },
        /* Ikon tombol konfirmasi. Tanpa ini ia hanya punya dua wajah: tong
           sampah (danger) atau centang. Tindakan yang bukan penghapusan —
           memutus tahap, menahan, menerbitkan — jadi memakai ikon tong sampah
           yang menjanjikan hal yang tidak ia lakukan. */
        confirmIcon: { type: String, default: '' },
        busy: { type: Boolean, default: false },
        // Kunci tombol konfirmasi selama syarat di dalam modal belum terpenuhi
        // (mis. centang persetujuan sebelum menggugurkan kandidat).
        confirmDisabled: { type: Boolean, default: false },
        // Isi modal berupa BORANG, bukan kalimat konfirmasi: rata kiri, tanpa
        // ikon peringatan, dan slot-nya tidak dibungkus <p>.
        formMode: { type: Boolean, default: false },
        confirmLabel: { type: String, default: 'Ya, Lanjutkan' },
        cancelLabel: { type: String, default: 'Batal' },
        busyLabel: { type: String, default: 'Memproses…' },
    },
    emits: ['confirm', 'cancel'],
};
</script>

<style scoped>
.cfm { text-align: center; padding: 8px 6px 2px; }
.cfm--form { text-align: left; padding: 2px 2px 4px; }
.cfm__ico {
    display: inline-grid;
    place-items: center;
    width: 66px;
    height: 66px;
    border-radius: 50%;
    font-size: 29px;
    margin-bottom: 16px;
}
.cfm__ico.is-danger { background: rgba(220, 38, 38, 0.1); color: #dc2626; }
.cfm__ico.is-warn { background: rgba(217, 119, 6, 0.12); color: #d97706; }
.cfm__txt { margin: 0 0 8px; font-size: 15px; color: #0f1235; line-height: 1.55; }
.cfm__txt :deep(strong) { color: #0b1033; }
.cfm__note { margin: 0; font-size: 12.5px; color: #dc2626; }
</style>
