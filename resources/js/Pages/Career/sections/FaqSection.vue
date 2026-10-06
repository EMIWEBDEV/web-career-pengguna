<!-- WEB CAREER — Section 6: Candidate FAQ.
     Isi TIDAK lagi hardcoded: datang dari Master FAQ (kolom Flag_Tampil_Landing),
     dikirim CareerLandingController::index() sebagai prop `faq`.
     Accordion-nya milik components/FaqAccordion.vue — dipakai bersama halaman /karir/faq. -->
<template>
    <!-- Belum ada FAQ yang ditandai untuk landing → section tidak dirender.
         Landing tidak boleh menampilkan judul dengan isi kosong di bawahnya. -->
    <section v-if="faqs.length" id="faq" class="wc-section wc-faq">
        <div class="wc-sec-head wc-reveal">
            <span class="wc-eyebrow"><span class="wc-dot"></span> FAQ Candidates</span>
            <h2>Pertanyaan yang sering <span class="wc-grad">diajukan.</span></h2>
            <p>Segala hal yang perlu Kamu ketahui tentang alur pendaftaran dan seleksi di EVO Group.</p>
        </div>

        <div class="wc-reveal">
            <FaqAccordion :items="faqs" single :initial-open="slugPertama" />
        </div>

        <div class="wc-faq__more wc-reveal">
            <Link href="/karir/faq" class="wc-faq__more-btn">
                Lihat semua pertanyaan
                <i class="bi bi-arrow-right"></i>
            </Link>
        </div>
    </section>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import FaqAccordion from '../components/FaqAccordion.vue';

const props = defineProps({
    faqs: { type: Array, default: () => [] },
});

// Pertanyaan pertama terbuka saat halaman dimuat (perilaku lama dipertahankan).
const slugPertama = computed(() => props.faqs[0]?.slug || '');
</script>

<style scoped>
.wc-faq {
    width: min(1000px, calc(100vw - 2rem));
    margin: clamp(2.5rem, 5vw, 3.5rem) auto;
}
.wc-faq > .wc-reveal {
    margin-top: 2rem;
}
.wc-faq__more {
    display: flex;
    justify-content: center;
    margin-top: 1.75rem;
}
.wc-faq__more-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.55rem;
    padding: 0.8rem 1.6rem;
    border-radius: 999px;
    border: 1px solid rgba(139, 92, 246, 0.35);
    background: rgba(255, 255, 255, 0.9);
    color: #4f46e5;
    font-size: 0.94rem;
    font-weight: 800;
    text-decoration: none;
    box-shadow: 0 8px 24px rgba(99, 102, 241, 0.1);
    transition: transform 0.25s cubic-bezier(0.16, 1, 0.3, 1), box-shadow 0.25s ease, background 0.25s ease, color 0.25s ease;
}
.wc-faq__more-btn:hover {
    background: linear-gradient(135deg, #8b5cf6, #6366f1);
    color: #ffffff;
    transform: translateY(-2px);
    box-shadow: 0 16px 34px rgba(99, 102, 241, 0.24);
}
.wc-faq__more-btn i {
    transition: transform 0.25s ease;
}
.wc-faq__more-btn:hover i {
    transform: translateX(3px);
}
</style>
