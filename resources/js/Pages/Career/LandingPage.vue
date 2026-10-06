<!-- ══════════════════════════════════════════════════════════
     WEB CAREER — LANDING PAGE
     Menyusun CareerLayout (navbar + footer) + section terpisah.
     Semua data dummy dari CareerLandingController (tanpa DB).
     ══════════════════════════════════════════════════════════ -->
<template>
    <Head title="Karier Bersama EVO Group" />

    <CareerLayout :has-mt="hasMt" :offices="offices">
        <HeroSection :benefits="benefits" :has-mt="hasMt" :hero-slides="heroSlides" :offices="offices" />
        <TentangSection />
        <AchievementSection :achievements="achievements" />
        <TimSection :tim="tim" />
        <MtSection :program-mt="programMt" />
        <LokasiSection :offices="offices" />
        <FaqSection :faqs="faq" />
        <CtaSection />
        <StickyApplyBar :has-mt="hasMt" />
    </CareerLayout>
</template>

<script setup>
import { Head } from '@inertiajs/vue3';
import { computed, nextTick, onMounted, onUnmounted } from 'vue';
import CareerLayout from './Layouts/CareerLayout.vue';
import HeroSection from './sections/HeroSection.vue';
import TentangSection from './sections/TentangSection.vue';
import AchievementSection from './sections/AchievementSection.vue';
import TimSection from './sections/TimSection.vue';
import MtSection from './sections/MtSection.vue';
import LokasiSection from './sections/LokasiSection.vue';
import FaqSection from './sections/FaqSection.vue';
import CtaSection from './sections/CtaSection.vue';
import StickyApplyBar from './components/StickyApplyBar.vue';
import { observeReveal, scrollToId } from '@utils/career/data';

defineOptions({ layout: null });

const props = defineProps({
    meta: { type: Object, default: () => ({}) },
    programMt: { type: Array, default: () => [] },
    achievements: { type: Array, default: () => [] },
    offices: { type: Array, default: () => [] },
    benefits: { type: Array, default: () => [] },
    tim: { type: Array, default: () => [] },
    heroSlides: { type: Array, default: () => [] },
    // FAQ pilihan admin (Master FAQ → Flag_Tampil_Landing).
    faq: { type: Array, default: () => [] },
});

const tim = computed(() => props.tim || []);
const programMt = computed(() => props.programMt || []);
const achievements = computed(() => props.achievements || []);
const offices = computed(() => props.offices || []);
const benefits = computed(() => props.benefits || []);
const heroSlides = computed(() => props.heroSlides || []);
const faq = computed(() => props.faq || []);
const hasMt = computed(() => programMt.value.length > 0);

// Reveal-on-scroll dipinjam dari @utils/career/data — SATU pelaksana untuk
// seluruh halaman karir.
//
// Salinan buatan tangan yang dulu berdiri di sini memindai DOM sekali saja,
// dan itu yang membuat section Management Trainee tak pernah tampil: ia
// digambar `v-if="programMt.length"`, jadi saat landing dibuka sebelum ada
// program MT terbit, section-nya belum ada untuk diamati. Begitu programnya
// terbit dan Inertia memperbarui props, section-nya muncul di DOM dengan
// `opacity: 0` dan tidak pernah ditandai — terlihat sebagai celah kosong di
// antara Tim dan Lokasi.
let revealObserver = null;

onMounted(() => {
    nextTick(() => {
        revealObserver = observeReveal();
        // Jika datang dari halaman lain (mis. detail) yang minta scroll ke section
        try {
            const target = sessionStorage.getItem('wcScrollTarget');
            if (target) {
                sessionStorage.removeItem('wcScrollTarget');
                setTimeout(() => scrollToId(target), 120);
            }
        } catch (e) {
            /* ignore */
        }
    });
});
onUnmounted(() => revealObserver?.disconnect());
</script>
