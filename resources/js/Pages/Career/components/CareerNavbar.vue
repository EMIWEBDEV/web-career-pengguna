<!-- WEB CAREER — Navbar (bagian dari CareerLayout) -->
<template>
    <nav class="wc-nav" :class="{ 'is-scrolled': isScrolled, 'is-open': menuOpen }">
        <button class="wc-nav__brand" type="button" @click="goHome">
            <span class="wc-nav__mark"><img src="/logo/EVOGROUP.png" alt="EVO Group" /></span>
            <span class="wc-nav__brand-txt">
                <strong>EVO Group</strong>
                <small>Career Portal</small>
            </span>
        </button>

        <div class="wc-nav__links">
            <button
                v-for="link in links"
                :key="link.id"
                type="button"
                :class="{ active: activeSection === link.id }"
                @click="goToSection(link.id)"
            >
                {{ link.label }}
            </button>
        </div>

        <div class="wc-nav__right">
            <template v-if="isAuthed">
                <Link class="wc-nav__cta wc-nav__cta--ghost" :href="dashboardUrl"><i class="bi bi-grid-1x2"></i><span>{{ dashboardLabel }}</span></Link>
                <a class="wc-nav__cta wc-nav__cta--primary" href="/logout"><i class="bi bi-box-arrow-right"></i><span>Logout</span></a>
            </template>
            <template v-else>
                <Link class="wc-nav__cta wc-nav__cta--ghost" href="/login"><i class="bi bi-box-arrow-in-right"></i><span>Masuk</span></Link>
                <Link class="wc-nav__cta wc-nav__cta--primary" href="/register"><i class="bi bi-person-plus"></i><span>Daftar</span></Link>
            </template>
            <button class="wc-nav__burger" type="button" aria-label="Menu" @click="menuOpen = !menuOpen">
                <i class="bi" :class="menuOpen ? 'bi-x-lg' : 'bi-list'"></i>
            </button>
        </div>

        <!-- Menu mobile / tablet (≤1040px) -->
        <transition name="wc-navm">
            <div v-if="menuOpen" class="wc-nav__mobile">
                <button
                    v-for="link in links"
                    :key="link.id"
                    type="button"
                    :class="{ active: activeSection === link.id }"
                    @click="pick(link.id)"
                >
                    <i class="bi bi-chevron-right"></i> {{ link.label }}
                </button>
                <div class="wc-nav__mobile-cta">
                    <template v-if="isAuthed">
                        <Link class="wc-nav__cta wc-nav__cta--ghost" :href="dashboardUrl" @click="menuOpen = false"><i class="bi bi-grid-1x2"></i><span>{{ dashboardLabel }}</span></Link>
                        <a class="wc-nav__cta wc-nav__cta--primary" href="/logout"><i class="bi bi-box-arrow-right"></i><span>Logout</span></a>
                    </template>
                    <template v-else>
                        <Link class="wc-nav__cta wc-nav__cta--ghost" href="/login" @click="menuOpen = false"><i class="bi bi-box-arrow-in-right"></i><span>Masuk</span></Link>
                        <Link class="wc-nav__cta wc-nav__cta--primary" href="/register" @click="menuOpen = false"><i class="bi bi-person-plus"></i><span>Daftar</span></Link>
                    </template>
                </div>
            </div>
        </transition>
    </nav>
</template>

<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { CAREER_HOME, CAREER_LANDING, goHome, goToSection } from '@utils/career/data';

const props = defineProps({
    hasMt: { type: Boolean, default: false },
});

// Status login real (dari sesi server, dibagikan lewat prop Inertia `careerAuth`).
const page = usePage();
const authUser = computed(() => page.props.careerAuth || null);
const isAuthed = computed(() => !!authUser.value);
const isAdminRole = computed(() => ['ADMIN', 'SUPERADMIN'].includes(authUser.value?.role));
const dashboardUrl = computed(() => (isAdminRole.value ? '/karir' : '/kandidat/portal'));
const dashboardLabel = computed(() => (isAdminRole.value ? 'Panel Admin' : 'Portal Saya'));

const links = computed(() => {
    const l = [
        { id: 'hero', label: 'Beranda' },
        { id: 'tentang', label: 'Tentang Kami' },
        { id: 'tim', label: 'Lowongan' },
    ];
    if (props.hasMt) l.push({ id: 'mt', label: 'Management Trainee' });
    l.push({ id: 'lokasi', label: 'Lokasi' });
    l.push({ id: 'faq', label: 'FAQ' });
    return l;
});

const isScrolled = ref(false);
const activeSection = ref('hero');
const menuOpen = ref(false);
const isLanding = typeof window !== 'undefined' && (window.location.pathname === CAREER_HOME || window.location.pathname === CAREER_LANDING);

function pick(id) {
    menuOpen.value = false;
    goToSection(id);
}

function handleScroll() {
    isScrolled.value = window.scrollY > 40;
}

let spy = null;
onMounted(() => {
    handleScroll();
    window.addEventListener('scroll', handleScroll, { passive: true });
    if (isLanding) {
        const sections = links.value.map((l) => document.getElementById(l.id)).filter(Boolean);
        spy = new IntersectionObserver(
            (entries) => entries.forEach((e) => e.isIntersecting && (activeSection.value = e.target.id)),
            { rootMargin: '-45% 0px -50% 0px' },
        );
        sections.forEach((s) => spy.observe(s));
    }
});
onUnmounted(() => {
    window.removeEventListener('scroll', handleScroll);
    spy?.disconnect();
});
</script>
