<!--
  WEB CAREER — TEMPLATE 1 / FORM 2: Identitas Peserta (lanjutan, bertahap).

  Berkas ini hanya TAMPILAN. Seluruh pertanyaan dan syaratnya ada di
  ./skema.js — sengaja dipisah supaya menyunting isi formulir tidak
  berisiko merusak tampilan, dan sebaliknya.

  Memakai layout "Bertahap" (stepper) karena formulirnya 4 halaman,
  sedangkan Form 1 memakai layout "SatuHalaman". Keduanya tetap satu
  keluarga tampilan yang sama: TEMPLATE 1.
-->
<template>
    <Bertahap
        v-model="jawaban"
        :skema="SKEMA"
        :disabled="disabled"
        :label-kirim="labelKirim"
        :konteks="konteks"
        :langkah-awal="langkahAwal"
        @kirim="(v) => $emit('kirim', v)"
        @berkas="(e) => $emit('berkas', e)"
        @pindah-langkah="(i) => $emit('pindah-langkah', i)"
    />
</template>

<script setup>
import { computed } from 'vue';
import Bertahap from '../layout/Bertahap.vue';
import { SKEMA } from '@utils/formulir/template-1/form-2';

const props = defineProps({
    modelValue: { type: Object, default: () => ({}) },
    disabled: { type: Boolean, default: false },
    labelKirim: { type: String, default: 'Kirim Formulir' },
    konteks: { type: Object, default: () => ({}) },
    // Dipulihkan dari Formulir_Pengisian.Langkah_Terakhir agar kandidat
    // bisa berhenti dan melanjutkan di lain waktu.
    langkahAwal: { type: Number, default: 0 },
});

const emit = defineEmits(['update:modelValue', 'kirim', 'berkas', 'pindah-langkah']);

const jawaban = computed({
    get: () => props.modelValue,
    set: (v) => emit('update:modelValue', v),
});
</script>
