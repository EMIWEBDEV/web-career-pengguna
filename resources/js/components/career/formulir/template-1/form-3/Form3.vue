<!--
  WEB CAREER — TEMPLATE 1 / FORM 3: Pendaftaran Rekrutmen Umum.

  Berkas ini hanya TAMPILAN. Seluruh pertanyaan dan syaratnya ada di
  ./skema.js — sengaja dipisah supaya menyunting isi formulir tidak
  berisiko merusak tampilan, dan sebaliknya.

  Memakai layout "SatuHalaman" seperti Form 1: ini formulir gerbang, jadi
  kandidat harus bisa melihat seluruh yang diminta sekali lihat.
-->
<template>
    <SatuHalaman
        v-model="jawaban"
        :skema="SKEMA"
        :judul="judul"
        :keterangan="keterangan"
        :disabled="disabled"
        :label-kirim="labelKirim"
        :konteks="konteks"
        @kirim="(v) => $emit('kirim', v)"
        @berkas="(e) => $emit('berkas', e)"
    />
</template>

<script setup>
import { computed } from 'vue';
import SatuHalaman from '../layout/SatuHalaman.vue';
import { SKEMA } from '@utils/formulir/template-1/form-3';

const props = defineProps({
    modelValue: { type: Object, default: () => ({}) },
    judul: { type: String, default: 'Formulir Pendaftaran' },
    keterangan: {
        type: String,
        default: 'Lengkapi data berikut untuk melamar posisi ini. Tanda * wajib diisi.',
    },
    disabled: { type: Boolean, default: false },
    labelKirim: { type: String, default: 'Kirim Lamaran' },
    konteks: { type: Object, default: () => ({}) },
});

const emit = defineEmits(['update:modelValue', 'kirim', 'berkas']);

const jawaban = computed({
    get: () => props.modelValue,
    set: (v) => emit('update:modelValue', v),
});
</script>
