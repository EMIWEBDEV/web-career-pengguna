<!-- SHELL TOPBAR — desain "Worklist Pelamar" (Claude Design):
     hamburger (mobile) · sapaan berdasar jam + nama · jam & tanggal live ·
     tombol ikon (unggah, notifikasi, bantuan) · badge modul hijau (label modul
     + nama pengguna + peran). Props sama dengan shell lama. -->
<template>
    <header class="evt-bar">
        <div class="evt-bar__left">
            <button type="button" class="evt-burger" title="Menu" @click="shell.toggleMobileSidebar()">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#334155" stroke-width="2.2" stroke-linecap="round"><path d="M3 6h18M3 12h18M3 18h18" /></svg>
            </button>
            <div style="min-width: 0">
                <div class="evt-greet"><span style="font-size: 12px">{{ greetIcon }}</span> {{ greetText }}</div>
                <div class="evt-hello">Halo, {{ firstName }}</div>
            </div>
        </div>

        <div class="evt-bar__right">
            <div class="evt-clock">
                <div class="evt-clock__time">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#8b5cf6" stroke-width="2.2"><circle cx="12" cy="12" r="9" /><path d="M12 7v5l3 2" /></svg>
                    {{ timeStr }}
                </div>
                <div class="evt-clock__date">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="2.2"><rect x="3" y="4" width="18" height="18" rx="2" /><path d="M3 10h18M8 2v4M16 2v4" /></svg>
                    {{ dateStr }}
                </div>
            </div>

            <div class="evt-icons">
                <button type="button" class="evt-iconbtn" title="Pusat Unduhan" aria-label="Pusat Unduhan" @click.stop="openComingSoon('activity')">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 15V3m0 0l-4 4m4-4l4 4" /><path d="M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2" /></svg>
                </button>
                <button type="button" class="evt-iconbtn" title="Notifikasi" aria-label="Notifikasi" @click.stop="openComingSoon('notifications')">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8a6 6 0 1 0-12 0c0 7-3 9-3 9h18s-3-2-3-9" /><path d="M13.7 21a2 2 0 0 1-3.4 0" /></svg>
                    <span v-if="unread > 0" class="evt-iconbtn__dot"></span>
                </button>
                <button type="button" class="evt-iconbtn" title="Panduan" aria-label="Panduan" @click.stop="openComingSoon('help')">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9" /><path d="M9.5 9a2.5 2.5 0 1 1 3.5 2.3c-.8.4-1 .9-1 1.7" /><path d="M12 17h.01" /></svg>
                </button>
            </div>

            <!-- Badge modul → popover jalur navigasi + kartu akun (perilaku lama) -->
            <div ref="badgeRoot" style="position: relative">
                <button type="button" class="evt-badge" :class="{ 'is-open': shell.state.showBreadcrumb }" @mousedown.stop @click.stop="toggleBreadcrumb">
                    <span class="evt-badge__ico">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="7" width="18" height="13" rx="2" /><path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" /></svg>
                    </span>
                    <span class="evt-badge__txt">
                        <span class="evt-badge__label">{{ moduleLabel }}</span>
                        <span class="evt-badge__name">{{ user.name || 'Pengguna' }}</span>
                    </span>
                    <span class="evt-badge__role">{{ roleChip }}</span>
                </button>

                <div class="shell-topbar-popover" :class="{ 'is-visible': shell.state.showBreadcrumb }" @mousedown.stop @click.stop>
                    <!-- Kartu identitas akun -->
                    <div class="shell-user-card">
                        <div class="shell-user-card__avatar">{{ userInitials }}</div>
                        <div class="shell-user-card__info">
                            <div class="shell-user-card__name">{{ user.name || 'Pengguna' }}</div>
                            <div class="shell-user-card__meta">
                                <span class="shell-user-card__badge shell-user-card__badge--nik"><i class="bi bi-person-badge"></i> {{ user.nik || '-' }}</span>
                                <span v-if="user.department" class="shell-user-card__badge shell-user-card__badge--dept"><i class="bi bi-building"></i> {{ user.department }}</span>
                            </div>
                        </div>
                        <button class="shell-user-card__logout shell-btn" type="button" title="Keluar dari Akun" @click="handleLogout">
                            <i class="bi bi-box-arrow-right"></i>
                        </button>
                    </div>

                    <div class="shell-popover-head">
                        <div>
                            <div class="shell-popover-title">Jalur Navigasi Anda</div>
                            <div class="shell-popover-subtitle" style="font-size: 0.65rem; color: #64748b; margin-top: 0.15rem">Melacak posisi Anda saat ini di dalam aplikasi</div>
                        </div>
                    </div>

                    <div class="shell-transit-map">
                        <div class="shell-transit-node is-completed">
                            <div class="shell-transit-station">
                                <div class="shell-transit-dot"><i class="bi bi-house-door-fill"></i></div>
                                <div class="shell-transit-line"></div>
                            </div>
                            <div class="shell-transit-info">
                                <div class="shell-transit-label">Platform Utama</div>
                                <button class="shell-transit-link" @click="navigateTo(homeUrl)">
                                    Beranda EVO
                                    <i class="bi bi-box-arrow-up-right" style="font-size: 0.75rem"></i>
                                </button>
                            </div>
                        </div>

                        <div class="shell-transit-node is-active">
                            <div class="shell-transit-station">
                                <div class="shell-transit-dot"><i :class="activeModuleIcon"></i></div>
                                <div class="shell-transit-line"></div>
                            </div>
                            <div class="shell-transit-info">
                                <div class="shell-transit-label">Modul Aktif</div>
                                <div class="shell-transit-name">{{ moduleLabel }}</div>
                                <div v-if="breadcrumbSiblings.length" class="shell-transit-branches">
                                    <div class="shell-transit-branches-title">Akses Cepat (Cabang Jalur):</div>
                                    <div class="shell-transit-chips">
                                        <button v-for="sib in breadcrumbSiblings" :key="sib.id" class="shell-transit-chip shell-btn" :title="sib.title" @click="navigateTo(sib.url)">
                                            <i :class="sib.icon"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="shell-transit-node is-current">
                            <div class="shell-transit-station">
                                <div class="shell-transit-dot-pulse"></div>
                            </div>
                            <div class="shell-transit-info">
                                <div class="shell-transit-label">Titik Lokasi Anda</div>
                                <div class="shell-transit-name" style="font-size: 1rem; color: #f59e0b">{{ activePageDisplay }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <AppComingSoonModal v-model="showComingSoon" :variant="comingSoonVariant" />
    </header>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AppComingSoonModal from './AppComingSoonModal.vue';
import { useShellState } from '../../composables/useShellState';

const props = defineProps({
    user: { type: Object, default: () => ({}) },
    brand: { type: Object, default: () => ({}) },
    layout: { type: Object, default: () => ({}) },
    navigation: { type: Object, default: () => ({}) },
});

const shell = useShellState();
const badgeRoot = ref(null);

/* ── Modal "Segera Hadir" untuk unduhan / notifikasi / panduan (perilaku lama) ── */
const showComingSoon = ref(false);
const comingSoonVariant = ref('activity');
function openComingSoon(variant) {
    comingSoonVariant.value = variant;
    showComingSoon.value = true;
}

/* ── Popover jalur navigasi pada badge modul (perilaku lama) ── */
function toggleBreadcrumb() {
    shell.state.showBreadcrumb = !shell.state.showBreadcrumb;
}
function navigateTo(url) {
    if (!url) return;
    shell.resetInteractionState();
    router.visit(url);
}
function handleLogout() {
    shell.resetInteractionState();
    router.get('/logout', {}, { preserveScroll: true });
}
function onDocMousedown(e) {
    if (!shell.state.showBreadcrumb) return;
    if (badgeRoot.value && !badgeRoot.value.contains(e.target)) shell.resetInteractionState();
}

const userInitials = computed(() => {
    const n = (props.user?.name || 'EV').trim().split(/\s+/);
    return ((n[0]?.[0] || '') + (n[1]?.[0] || '')).toUpperCase() || 'EV';
});
const homeUrl = computed(() => props.navigation?.home?.url || '/');
const activeModuleData = computed(() => {
    const mods = props.navigation?.modules || [];
    return mods.find((m) => m.isActive) || mods[0] || null;
});
const activeModuleIcon = computed(() => activeModuleData.value?.icon || 'bi bi-grid');
const activePageDisplay = computed(() => props.layout?.shell?.currentPageTitle || props.layout?.shell?.activeSubMenuLabel || 'Halaman');
const breadcrumbSiblings = computed(() => {
    if (!activeModuleData.value) return [];
    const siblings = [];
    (activeModuleData.value.groups || []).forEach((group) => {
        if (group.title && group.title.toLowerCase().includes('admin')) return;
        (group.items || []).forEach((item) => {
            const titleMatch = item.title && (item.title.toLowerCase().includes('admin') || item.title.toLowerCase().includes('pengaturan'));
            const urlMatch = item.url && item.url.toLowerCase().includes('admin');
            if (!titleMatch && !urlMatch) {
                siblings.push({ id: item.id || item.title, title: item.title, url: item.url, icon: item.icon || 'bi bi-circle' });
            }
        });
    });
    return siblings.slice(0, 10);
});

const firstName = computed(() => ((props.user?.name || 'Pengguna').trim().split(/\s+/)[0] || 'Pengguna').toUpperCase());
const roleChip = computed(() => (props.user?.nik || 'ADMIN').toUpperCase());
const moduleLabel = computed(() => (props.layout?.shell?.activeModule || props.navigation?.sectionLabel || 'EVO').toUpperCase());
const unread = computed(() => props.layout?.unreadNotificationsCount || 0);

/* ── Jam & tanggal live (tick 15 detik, seperti desain) ── */
const now = ref(new Date());
let clockT = null;
onMounted(() => {
    clockT = setInterval(() => (now.value = new Date()), 15000);
    document.addEventListener('mousedown', onDocMousedown);
});
onBeforeUnmount(() => {
    clearInterval(clockT);
    document.removeEventListener('mousedown', onDocMousedown);
});

const timeStr = computed(() => String(now.value.getHours()).padStart(2, '0') + '.' + String(now.value.getMinutes()).padStart(2, '0'));
const MON = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
const dateStr = computed(() => now.value.getDate() + ' ' + MON[now.value.getMonth()] + ' ' + now.value.getFullYear());

const greetText = computed(() => {
    const h = now.value.getHours();
    if (h < 11) return 'SELAMAT PAGI';
    if (h < 15) return 'SELAMAT SIANG';
    if (h < 19) return 'SELAMAT SORE';
    return 'SELAMAT MALAM';
});
const greetIcon = computed(() => (now.value.getHours() < 19 && now.value.getHours() >= 5 ? '☀' : '✦'));
</script>

<style scoped>
.evt-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 14px;
    flex: 0 0 auto;
    padding: 13px 26px;
    background: rgba(255, 255, 255, 0.85);
    backdrop-filter: blur(14px);
    -webkit-backdrop-filter: blur(14px);
    border-bottom: 1px solid #eef0f7;
    position: sticky;
    top: 0;
    z-index: 1020;
}
.evt-bar__left {
    display: flex;
    align-items: center;
    gap: 13px;
    min-width: 0;
    flex: 1 1 auto;
}
.evt-burger {
    display: none;
    appearance: none;
    border: 1px solid #e6e9f3;
    background: #fff;
    width: 42px;
    height: 42px;
    border-radius: 12px;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    flex: 0 0 auto;
}
.evt-greet {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 10.5px;
    font-weight: 800;
    letter-spacing: 0.16em;
    color: #b6892b;
}
.evt-hello {
    font-size: 18px;
    font-weight: 800;
    color: #0f172a;
    letter-spacing: -0.02em;
    line-height: 1.2;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.evt-bar__right {
    display: flex;
    align-items: center;
    gap: 12px;
    flex: 0 0 auto;
}
.evt-clock {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 1px;
}
.evt-clock__time {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 14px;
    font-weight: 800;
    color: #4338ca;
}
.evt-clock__date {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 11.5px;
    color: #8b93a7;
}
.evt-icons {
    display: flex;
    align-items: center;
    gap: 8px;
}
.evt-iconbtn {
    position: relative;
    appearance: none;
    border: 1px solid #e6e9f3;
    background: #fff;
    width: 40px;
    height: 40px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    color: #64748b;
    transition: all 0.16s;
}
.evt-iconbtn:hover {
    color: #4f46e5;
    border-color: #c7cdf0;
}
.evt-iconbtn__dot {
    position: absolute;
    top: 8px;
    right: 9px;
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: #ef4444;
    border: 1.5px solid #fff;
}
/* ══ WARNANYA INDIGO, BUKAN HIJAU ═══════════════════════════════════════════
 *
 * Diambil dari rancangan `EVO Career Login Redesign` — nilai warnanya disalin
 * apa adanya, bukan dikira-kira dari tangkapan layar.
 *
 * Hijau emerald yang lama membuat badge ini jadi satu-satunya benda hijau di
 * seluruh panel: sidebar, tombol utama, kartu, grafik — semuanya indigo. Warna
 * yang berdiri sendiri di pojok kanan atas terbaca sebagai peringatan atau
 * penanda status, padahal ia cuma identitas akun.
 */
.evt-badge {
    appearance: none;
    cursor: pointer;
    font-family: inherit;
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 6px 10px;
    border-radius: 14px;
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.1), rgba(139, 92, 246, 0.08));
    border: 1px solid rgba(99, 102, 241, 0.22);
    flex: 0 0 auto;
    transition: all 0.16s;
}
.evt-badge:hover,
.evt-badge.is-open {
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.17), rgba(139, 92, 246, 0.13));
    border-color: rgba(99, 102, 241, 0.36);
}
.evt-badge__ico {
    width: 34px;
    height: 34px;
    border-radius: 10px;
    background: linear-gradient(135deg, #818cf8, #4f46e5);
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    flex: 0 0 auto;
}
.evt-badge__txt {
    display: flex;
    flex-direction: column;
    gap: 1px;
    min-width: 0;
}
.evt-badge__label {
    display: block;
    font-size: 10px;
    font-weight: 800;
    letter-spacing: 0.16em;
    /* Abu, bukan berwarna: ini kicker — penanda konteks yang dibaca sesudah
       namanya, bukan sebelum. Mewarnainya membuatnya bersaing dengan nama
       penggunanya sendiri. */
    color: #94a3b8;
}
.evt-badge__name {
    display: block;
    font-size: 12.5px;
    font-weight: 800;
    color: #0f172a;
    max-width: 150px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
/* Pil peran: tinta pekat berteks putih, sesuai rancangan. Ia satu-satunya
   benda bernada gelap di badge ini — dan memang itu yang harus terbaca lebih
   dulu saat orang memeriksa "saya masuk sebagai siapa". */
.evt-badge__role {
    display: inline-block;
    font-size: 8.5px;
    font-weight: 800;
    letter-spacing: 0.1em;
    color: #ffffff;
    background: #0f172a;
    border-radius: 7px;
    padding: 3px 8px;
}

/* ═══ POPOVER JALUR NAVIGASI (gaya sama dgn shell lama) ═══ */
.shell-topbar-popover {
    position: absolute;
    top: calc(100% + 0.75rem);
    right: 0;
    z-index: 1060;
    width: min(22rem, calc(100vw - 1.5rem));
    border-radius: 1.25rem;
    border: 1px solid rgba(226, 232, 240, 0.95);
    background: rgba(255, 255, 255, 0.96);
    backdrop-filter: blur(18px);
    box-shadow: 0 28px 60px rgba(15, 23, 42, 0.18);
    overflow: hidden;
    opacity: 0;
    visibility: hidden;
    transform: translateY(0.5rem);
    pointer-events: none;
    transition: opacity 0.18s ease, transform 0.18s ease, visibility 0.18s ease;
}
.shell-topbar-popover.is-visible {
    opacity: 1;
    visibility: visible;
    transform: translateY(0);
    pointer-events: auto;
}
.shell-popover-head {
    min-height: 3.25rem;
    padding: 0.85rem 1rem;
    border-bottom: 1px solid #f1f5f9;
    background: linear-gradient(180deg, rgba(248, 250, 252, 0.45) 0%, rgba(255, 255, 255, 0) 100%);
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
}
.shell-popover-title {
    color: #0f172a;
    font-size: 0.825rem;
    line-height: 1.25;
    font-weight: 850;
    letter-spacing: -0.015em;
    display: flex;
    align-items: center;
    gap: 0.55rem;
}
.shell-user-card {
    display: flex;
    align-items: center;
    gap: 0.85rem;
    padding: 1rem 1rem 0.85rem;
    background: linear-gradient(135deg, rgba(79, 70, 229, 0.04), rgba(99, 102, 241, 0.02));
    border-bottom: 1px solid #f1f5f9;
}
.shell-user-card__avatar {
    width: 2.75rem;
    height: 2.75rem;
    border-radius: 0.9rem;
    background: linear-gradient(135deg, #4f46e5, #6366f1);
    color: #ffffff;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 0.9rem;
    font-weight: 950;
    flex-shrink: 0;
    box-shadow: 0 6px 16px rgba(79, 70, 229, 0.28);
    letter-spacing: 0.02em;
}
.shell-user-card__info {
    min-width: 0;
    flex: 1;
}
.shell-user-card__name {
    color: #0f172a;
    font-size: 0.82rem;
    font-weight: 900;
    line-height: 1.2;
    letter-spacing: -0.01em;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.shell-user-card__meta {
    display: flex;
    flex-wrap: wrap;
    gap: 0.35rem;
    margin-top: 0.4rem;
}
.shell-user-card__badge {
    display: inline-flex;
    align-items: center;
    gap: 0.28rem;
    padding: 0.22rem 0.55rem;
    border-radius: 999px;
    font-size: 0.58rem;
    font-weight: 800;
    line-height: 1;
    white-space: nowrap;
    max-width: 11rem;
    overflow: hidden;
    text-overflow: ellipsis;
}
.shell-user-card__badge i {
    font-size: 0.62rem;
    flex-shrink: 0;
}
.shell-user-card__badge--nik {
    background: rgba(79, 70, 229, 0.08);
    color: #4f46e5;
    border: 1px solid rgba(79, 70, 229, 0.14);
}
.shell-user-card__badge--dept {
    background: rgba(16, 185, 129, 0.08);
    color: #059669;
    border: 1px solid rgba(16, 185, 129, 0.14);
    overflow: hidden;
    text-overflow: ellipsis;
}
.shell-user-card__logout {
    width: 2rem;
    height: 2rem;
    border-radius: 0.6rem;
    background: rgba(239, 68, 68, 0.08);
    color: #ef4444;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    line-height: 1;
    flex-shrink: 0;
    border: none;
    cursor: pointer;
    transition: all 200ms ease;
}
.shell-user-card__logout:hover {
    background: #ef4444;
    color: #ffffff;
    box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
}

@media (max-width: 1179.98px) {
    .evt-clock,
    .evt-icons,
    .evt-badge__txt,
    .evt-badge__role {
        display: none;
    }
}
@media (max-width: 767.98px) {
    .shell-topbar-popover {
        position: fixed;
        top: 4.5rem;
        right: 1rem;
        width: min(24rem, calc(100vw - 2rem));
    }
}
@media (max-width: 991.98px) {
    .evt-bar {
        padding: 12px 16px;
    }
    .evt-burger {
        display: flex;
    }
}
</style>
