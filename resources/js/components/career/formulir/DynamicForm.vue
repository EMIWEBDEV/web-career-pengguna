<!-- WEB CAREER - Renderer formulir dinamis berbasis Schema_Json. -->
<template>
    <Bertahap
        v-if="layout === 'BERTAHAP'"
        v-model="jawaban"
        :skema="schemaNormal"
        :disabled="disabled"
        :label-kirim="labelKirim"
        :konteks="konteks"
        :langkah-awal="langkahAwal"
        @kirim="(v) => $emit('kirim', v)"
        @berkas="(e) => $emit('berkas', e)"
        @hapus-baris="(b, i) => $emit('hapus-baris', b, i)"
        @pindah-langkah="(i) => $emit('pindah-langkah', i)"
    />
    <SatuHalaman
        v-else
        v-model="jawaban"
        :skema="schemaNormal"
        :judul="judul"
        :keterangan="keterangan"
        :disabled="disabled"
        :label-kirim="labelKirim"
        :konteks="konteks"
        @kirim="(v) => $emit('kirim', v)"
        @berkas="(e) => $emit('berkas', e)"
        @hapus-baris="(b, i) => $emit('hapus-baris', b, i)"
    />
</template>

<script setup>
import { computed } from 'vue';
import SatuHalaman from './template-1/layout/SatuHalaman.vue';
import Bertahap from './template-1/layout/Bertahap.vue';
import { normalisasiSkema } from '@utils/formulir/schema';

const props = defineProps({
    modelValue: { type: Object, default: () => ({}) },
    skema: { type: Object, required: true },
    judul: { type: String, default: 'Formulir Pendaftaran' },
    keterangan: { type: String, default: 'Lengkapi data berikut. Tanda * wajib diisi.' },
    disabled: { type: Boolean, default: false },
    labelKirim: { type: String, default: 'Kirim Formulir' },
    konteks: { type: Object, default: () => ({}) },
    langkahAwal: { type: Number, default: 0 },
});

const emit = defineEmits(['update:modelValue', 'kirim', 'berkas', 'hapus-baris', 'pindah-langkah']);

const jawaban = computed({
    get: () => props.modelValue,
    set: (v) => emit('update:modelValue', v),
});

const schemaNormal = computed(() => normalisasiSkema(props.skema));
const layout = computed(() => String(schemaNormal.value.layout || 'SATU_HALAMAN').toUpperCase());
</script>
