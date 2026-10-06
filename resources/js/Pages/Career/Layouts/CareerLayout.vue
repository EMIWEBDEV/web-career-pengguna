<!-- ══════════════════════════════════════════════════════════
     WEB CAREER — MAIN LAYOUT
     Membungkus navbar + footer + background untuk SEMUA halaman
     Web Career (landing & detail). Import stylesheet modul di sini.
     ══════════════════════════════════════════════════════════ -->
<template>
    <div class="wc-page">
        <!-- Background dekoratif -->
        <div class="wc-bg" aria-hidden="true">
            <div class="wc-bg__grid"></div>
            <span class="wc-bg__orb wc-bg__orb--1"></span>
            <span class="wc-bg__orb wc-bg__orb--2"></span>
            <span class="wc-bg__orb wc-bg__orb--3"></span>
        </div>

        <CareerNavbar :has-mt="hasMt" />

        <slot />

        <CareerFooter :has-mt="hasMt" />
    </div>
</template>

<script setup>
import CareerNavbar from '../components/CareerNavbar.vue';
import CareerFooter from '../components/CareerFooter.vue';

defineProps({
    hasMt: { type: Boolean, default: false },
    // Dideklarasikan agar binding :offices dari halaman tidak bocor jadi
    // atribut DOM. Footer sudah tidak memakainya.
    offices: { type: Array, default: () => [] },
});
</script>
