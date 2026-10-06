<!-- WEB CAREER — Footer 2 tingkat (bagian dari CareerLayout)
     dengan Social Media Icons Bar & Enhanced Mobile Footer UX -->
<template>
    <footer class="wc-footer">
        <!-- Tingkat 1 -->
        <div class="wc-footer__top">
            <div class="wc-footer__brand">
                <div class="wc-footer__logo">
                    <span class="wc-footer__mark"><img src="/logo/EVOGROUP.png" alt="EVO Group" /></span>
                    <span class="wc-footer__brand-txt">
                        <strong>EVO Group</strong>
                        <small>Career Portal</small>
                    </span>
                </div>
                <p>Ekosistem people, pet &amp; manufacturing terkemuka di Sumatera Selatan. Naik level bersama kami.</p>

                <!-- Baris Ikon Media Sosial & Kontak -->
                <div class="wc-footer__social">
                    <a
                        href="https://www.linkedin.com/company/evopet/posts/?feedView=all"
                        target="_blank"
                        rel="noopener noreferrer"
                        title="LinkedIn"
                        ><i class="bi bi-linkedin"></i
                    ></a>
                    <a href="mailto:rekrutmen@evopet.id" title="Email Rekrutmen"
                        ><i class="bi bi-envelope-fill"></i
                    ></a>
                    <a href="https://wa.me/6282277999790" target="_blank" rel="noopener noreferrer" title="WhatsApp HR"
                        ><i class="bi bi-whatsapp"></i
                    ></a>
                </div>
            </div>

            <div class="wc-footer__cols">
                <div class="wc-footer__col">
                    <strong>Karier</strong>
                    <!-- #lowongan sudah tidak ada sejak redesign — arahkan ke Fungsi Perusahaan. -->
                    <button type="button" @click="goToSection('tim')">Lowongan</button>
                    <button v-if="hasMt" type="button" @click="goToSection('mt')">Management Trainee</button>
                    <a href="/karir/lowongan">Semua Lowongan</a>
                </div>
                <div class="wc-footer__col">
                    <strong>Akses</strong>
                    <a href="/login">Masuk</a>
                    <a href="/register">Daftar Akun</a>
                    <a href="/kandidat/portal">Portal Kandidat</a>
                </div>
                <div class="wc-footer__col">
                    <strong>Tentang</strong>
                    <button type="button" @click="goToSection('achievement')">Pencapaian</button>
                    <button type="button" @click="goToSection('lokasi')">Lokasi Kami</button>
                    <a href="/karir/faq">FAQ Kandidat</a>
                </div>
                <div class="wc-footer__col wc-footer__col--office">
                    <!-- URL embed hasil "Bagikan → Sematkan peta" dari Google Maps;
                         tidak butuh API key. Ganti kalau lokasi kantor pindah. -->
                    <iframe
                        class="wc-footer__map"
                        src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3984.5520718382104!2d104.76754357511668!3d-2.9441472396858823!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x2e3b7707ce6db5a3%3A0x353d0e6057d35b20!2sPT.%20EVO%20NUSA%20BERSAUDARA!5e0!3m2!1sid!2sid!4v1786609962390!5m2!1sid!2sid"
                        title="Peta lokasi kantor pusat EVO Group"
                        loading="lazy"
                        referrerpolicy="strict-origin-when-cross-origin"
                        allow="fullscreen"
                    ></iframe>
                </div>
            </div>
        </div>
        <!-- Tingkat 2 -->
        <div class="wc-footer__bar">
            <div class="wc-footer__bar-inner">
                <span>&copy; {{ tahun }} EVO Group. Seluruh hak cipta.</span>
                <span class="wc-footer__credit">
                    Dikelola oleh <b>Tim Technology EVO Group</b>
                    <span class="wc-footer__ver" title="Versi aplikasi">Versi {{ versi }}</span>
                </span>
            </div>
        </div>
    </footer>
</template>

<script setup>
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { goToSection } from '@utils/career/data';

defineProps({
    hasMt: { type: Boolean, default: false },
});

const tahun = new Date().getFullYear();

/**
 * Versi aplikasi — dibagikan HandleInertiaRequests ke seluruh halaman.
 *
 * Cadangan '1.0.0' bukan basa-basi: komponen ini juga dipakai halaman yang
 * dirender tanpa shared props lengkap, dan kaki halaman yang berbunyi
 * "vundefined" jauh lebih buruk daripada angka bawaan yang benar.
 */
const versi = computed(() => usePage().props?.appVersion || '1.0.0');
</script>
