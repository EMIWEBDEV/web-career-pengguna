<!-- SHELL SIDEBAR — desain "Worklist Pelamar" (Claude Design):
     RAIL ikon 68px (kiri, tetap) + SIDEBAR overlay 290px yang muncul saat
     hover / dikunci (pin), drawer penuh di mobile. Data digerakkan props
     (brand, navigation, user) yang sama dengan shell lama — semua halaman
     admin (KPI/HCIS/Career) otomatis ikut. Font Inter dari evo-theme.
     Kotak CARI MENU di kepala sidebar menyaring menu milik pemakai langsung
     di peramban, tanpa request (@utils/cariMenu). -->
<template>
    <!-- Scrim mobile -->
    <div class="evs-scrim" :class="{ 'is-on': shell.state.isMobile && shell.state.mobileSidebarOpen }" @click="shell.closeMobileSidebar()"></div>

    <!-- ═══ RAIL IKON ═══ -->
    <!-- Hover DI MANA PUN pada rail (logo, menu, area kosong, avatar) membuka sidebar. -->
    <nav class="evs-rail" aria-label="Navigasi modul" @mouseenter="hoverOpen" @mouseleave="hoverClose">
        <button type="button" class="evs-rail__logo" :class="{ 'is-pinned': shell.state.sidebarLocked }" title="Klik untuk mengunci sidebar" @click="togglePin">
            <img v-if="!imgErr.mainLogo" :src="brand.mainLogo" :alt="brand.mainLogoAlt || 'EVO Group'" @error="imgErr.mainLogo = true" />
            <span v-else class="evs-rail__logofb">EVO</span>
        </button>
        <div style="height: 16px"></div>

        <!-- CARI MENU dari rail. Di perangkat ber-mouse sidebar sudah terbuka
             begitu kursor menyentuh rail, dan kotak carinya langsung terlihat;
             tombol ini melayani papan ketik (Tab → Enter) dan layar sentuh,
             sekaligus memberi tahu bahwa menu bisa dicari. -->
        <template v-if="bisaCari">
            <button type="button" class="evs-rail__btn" :title="`Cari menu (${pintasan})`" aria-label="Cari menu" @click="bukaCari">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7" /><path d="M20 20l-3.6-3.6" /></svg>
            </button>
            <span class="evs-rail__sep" aria-hidden="true"></span>
        </template>

        <!-- SOROTAN RAIL MENGIKUTI HALAMAN, BUKAN AKORDEON.
             `homeLink.hasActive` ikut menyala saat salah satu dashboard di
             dalam collapse-nya yang dibuka, bukan cuma beranda persis. -->
        <a class="evs-rail__btn" :class="{ 'is-active': homeLink.hasActive ?? homeLink.isActive }" :href="homeLink.url || '#'" :title="homeLink.title || 'Dashboard'" @click="visit($event, homeLink.url)">
            <svg width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 10.5L12 3l9 7.5" /><path d="M5 9.5V21h14V9.5" /></svg>
        </a>

        <!-- Dulu: `m.id === openModule`. openModule adalah keadaan AKORDEON,
             dan syncFromNav() selalu mengisinya dengan modul aktif ATAU modul
             pertama — dengan satu modul saja (CAREER), syaratnya SELALU benar.
             Tombol modul karena itu menyala permanen, dan di halaman beranda ia
             menyala BERSAMA tombol Dashboard: rail tampak "aktif semua" dan
             tidak menunjuk apa pun.

             Sekarang dibaca dari halaman: modul menyala hanya bila ada itemnya
             yang sedang dibuka (activeGroupId terisi). Beranda dan modul jadi
             saling meniadakan, sebab item beranda memang bukan anggota grup. -->
        <button
            v-for="m in modules"
            :key="m.id"
            type="button"
            class="evs-rail__btn"
            :class="{ 'is-active': !!m.activeGroupId }"
            :title="m.label || m.name"
            @click="railModule(m.id)"
        >
            <i :class="m.icon || 'bi bi-grid'" style="font-size: 19px"></i>
        </button>

        <div style="flex: 1"></div>
        <div class="evs-rail__avatar" :title="user.name">{{ initials }}</div>
    </nav>

    <!-- ═══ SIDEBAR OVERLAY ═══ -->
    <aside class="evs-sb" :class="{ 'is-open': expanded, 'is-mobile': shell.state.isMobile }" @mouseenter="hoverOpen" @mouseleave="hoverClose">
        <!-- Header -->
        <div class="evs-sb__head">
            <div class="evs-sb__brandrow">
                <button type="button" class="evs-sb__logo" title="Kunci / lepas sidebar" @click="togglePin">
                    <img v-if="!imgErr.mainLogo2" :src="brand.mainLogo" :alt="brand.mainLogoAlt || 'EVO Group'" @error="imgErr.mainLogo2 = true" />
                    <span v-else class="evs-rail__logofb">EVO</span>
                </button>
                <div class="evs-sb__brandtxt">
                    <div class="evs-sb__brandtitle">EVO GROUP</div>
                    <div class="evs-sb__brandsub">{{ sectionLabel }}</div>
                </div>
                <button type="button" class="evs-sb__pin" :class="{ 'is-pinned': shell.state.sidebarLocked }" title="Kunci / lepas sidebar" @click="togglePin">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 4h6l-1 6 3 3v2H7v-2l3-3z" /><path d="M12 15v5" /></svg>
                </button>
            </div>
            <div v-if="subsidiaries.length" class="evs-sb__subs">
                <template v-for="(s, i) in subsidiaries" :key="s.id || i">
                    <img :src="s.src" :alt="s.name" @error="$event.target.style.display = 'none'" />
                    <span v-if="i < subsidiaries.length - 1" class="evs-sb__subsdiv"></span>
                </template>
            </div>

            <!-- CARI MENU — disaring di peramban dari menu yang SUDAH tergambar
                 untuk akun ini. Tidak ada request ke server, jadi menu yang
                 tidak boleh dilihat mustahil muncul di hasilnya.

                 Ditaruh paling bawah kepala, tepat di atas daftar: hasilnya
                 menggantikan daftar itu, dan logo anak usaha tidak boleh
                 berdiri di antara kotak cari dan jawabannya. -->
            <form v-if="bisaCari" class="evs-cari" :class="{ 'is-fokus': cariFokus }" role="search" @submit.prevent>
                <label for="evs-cari-input" class="evs-cari__ico" aria-hidden="true">
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7" /><path d="M20 20l-3.6-3.6" /></svg>
                </label>
                <input
                    id="evs-cari-input"
                    ref="cariInput"
                    v-model="kueri"
                    type="search"
                    class="evs-cari__input"
                    placeholder="Cari menu…"
                    maxlength="80"
                    aria-label="Cari menu"
                    role="combobox"
                    aria-autocomplete="list"
                    :aria-expanded="hasil?.total ? 'true' : 'false'"
                    :aria-controls="hasil?.total ? 'evs-hasil' : undefined"
                    :aria-activedescendant="kursorId"
                    autocomplete="off"
                    autocapitalize="off"
                    spellcheck="false"
                    enterkeyhint="go"
                    @focus="cariFokus = true"
                    @blur="cariFokus = false"
                    @keydown="tombolCari"
                />
                <!-- mousedown.prevent: fokus tetap di kotak cari, jadi orang
                     bisa langsung mengetik ulang tanpa mengeklik lagi. -->
                <button v-if="kueri" type="button" class="evs-cari__hapus" title="Hapus pencarian" aria-label="Hapus pencarian" @mousedown.prevent @click="hapusCari">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18" /></svg>
                </button>
                <kbd v-else-if="!cariFokus" class="evs-cari__kbd" aria-hidden="true">{{ pintasan }}</kbd>
                <span class="evs-sr" aria-live="polite">{{ ringkasCari }}</span>
            </form>
        </div>

        <!-- Nav scroll -->
        <div ref="gulirEl" class="evs-sb__scroll">
            <!-- HASIL CARI — menggantikan menu selama kotak cari berisi.
                 Dikelompokkan per grup (grup berisi hasil terbaik di depan),
                 dan kata yang cocok disorot supaya jelas KENAPA sebuah menu
                 ikut muncul — termasuk yang cocok lewat nama grupnya. -->
            <div v-if="hasil" class="evs-hasil">
                <template v-if="hasil.total">
                    <div class="evs-hasil__ringkas" aria-hidden="true">
                        <span>HASIL PENCARIAN</span>
                        <span class="evs-hasil__total">{{ hasil.total }} menu</span>
                    </div>

                    <div id="evs-hasil" role="listbox" aria-label="Hasil pencarian menu">
                        <section v-for="k in hasil.kelompok" :key="k.key" class="evs-hasil__grp" role="group" :aria-label="k.title">
                            <div class="evs-hasil__label" aria-hidden="true">
                                <span class="evs-hasil__chip"><i :class="k.ikon"></i></span>
                                <span class="evs-hasil__judul"><template v-for="(s, i) in k.seg" :key="i"><mark v-if="s.cocok" class="evs-mark">{{ s.t }}</mark><template v-else>{{ s.t }}</template></template></span>
                                <span class="evs-hasil__garis"></span>
                                <span class="evs-hasil__n">{{ k.items.length }}</span>
                            </div>

                            <!-- mousemove, bukan mouseenter: daftar yang bergulir
                                 karena ↑/↓ melintas di bawah kursor yang diam, dan
                                 mouseenter akan merebut sorotan dari papan ketik. -->
                            <a
                                v-for="it in k.items"
                                :id="`evs-hasil-${it.n}`"
                                :key="it.key"
                                class="evs-hit"
                                :class="{ 'is-kursor': it.n === kursor, 'is-active': it.isActive }"
                                :href="it.url"
                                role="option"
                                :aria-selected="it.n === kursor ? 'true' : 'false'"
                                tabindex="-1"
                                @mousemove="kursor = it.n"
                                @click="pilihHasil($event, it)"
                            >
                                <span class="evs-hit__ico"><i :class="it.icon"></i></span>
                                <span class="evs-hit__txt">
                                    <span class="evs-hit__title"><template v-for="(s, i) in it.segJudul" :key="i"><mark v-if="s.cocok" class="evs-mark">{{ s.t }}</mark><template v-else>{{ s.t }}</template></template></span>
                                    <span v-if="it.sub" class="evs-hit__path">
                                        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 4v7a4 4 0 0 0 4 4h11" /><path d="M16 11l4 4-4 4" /></svg>
                                        <span class="evs-hit__pathtxt"><template v-for="(s, i) in it.segSub" :key="i"><mark v-if="s.cocok" class="evs-mark">{{ s.t }}</mark><template v-else>{{ s.t }}</template></template></span>
                                    </span>
                                </span>
                                <span v-if="it.isActive" class="evs-hit__now">Aktif</span>
                                <span v-else class="evs-hit__go" aria-hidden="true">
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 5v7a3 3 0 0 1-3 3H5" /><path d="M9 11l-4 4 4 4" /></svg>
                                </span>
                            </a>
                        </section>
                    </div>
                </template>

                <!-- KOSONG. Kalimat keduanya menjawab pertanyaan yang pasti
                     muncul ("menunya ada, kok tidak ketemu?"): yang dicari
                     hanya menu yang boleh diakses akun ini. -->
                <div v-else class="evs-kosong">
                    <span class="evs-kosong__ico">
                        <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7" /><path d="M20 20l-3.6-3.6" /><path d="M8.6 8.6l4.8 4.8M13.4 8.6l-4.8 4.8" /></svg>
                    </span>
                    <div class="evs-kosong__judul">Menu tidak ditemukan</div>
                    <p class="evs-kosong__teks">Tidak ada menu yang cocok dengan “<b>{{ kueri.trim() }}</b>”.</p>
                    <p class="evs-kosong__catatan">Pencarian hanya mencakup menu yang bisa diakses akun Anda.</p>
                    <div v-if="saranCari.length" class="evs-kosong__saran">
                        <span class="evs-kosong__coba">Coba cari</span>
                        <button v-for="s in saranCari" :key="s" type="button" class="evs-saran" @mousedown.prevent @click="pakaiSaran(s)">{{ s }}</button>
                    </div>
                </div>
            </div>

            <!-- MENU BIASA. v-show, bukan v-if: keadaan akordeon & posisi
                 gulirnya tetap utuh selama orang sedang mencari. -->
            <div v-show="!hasil" class="evs-nav">
                <!-- SATU DASHBOARD → TAUTAN TUNGGAL, seperti sebelumnya. Collapse
                     berisi satu baris hanya menambah satu klik tanpa memberi
                     pilihan apa pun. -->
                <a
                    v-if="!dashboardLain.length"
                    class="evs-sb__home" :class="{ 'is-active': homeLink.isActive }"
                    :href="homeLink.url || '#'" @click="visit($event, homeLink.url)"
                >
                    <span class="evs-sb__homeico">
                        <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 10.5L12 3l9 7.5" /><path d="M5 9.5V21h14V9.5" /></svg>
                    </span>
                    <span style="min-width: 0">
                        <span class="evs-sb__hometitle">{{ homeLink.title || 'Dashboard' }}</span>
                        <span class="evs-sb__homesub">{{ homeLink.subtitle || 'Halaman Utama' }}</span>
                    </span>
                </a>

                <!-- LEBIH DARI SATU DASHBOARD → kepala yang bisa dibuka.
                     Kepalanya TETAP menuju beranda saat ditekan; yang membuka
                     hanya tanda panahnya. Kalau seluruh kepala dijadikan tombol
                     buka-tutup, Dashboard Utama kehilangan satu-satunya jalan
                     menuju dirinya sendiri. -->
                <div v-else class="evs-sb__homewrap" :class="{ 'is-active': homeLink.hasActive }">
                    <div class="evs-sb__home evs-sb__home--grp" :class="{ 'is-active': homeLink.isActive }">
                        <a class="evs-sb__homelink" :href="homeLink.url || '#'" @click="visit($event, homeLink.url)">
                            <span class="evs-sb__homeico">
                                <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 10.5L12 3l9 7.5" /><path d="M5 9.5V21h14V9.5" /></svg>
                            </span>
                            <span style="min-width: 0">
                                <span class="evs-sb__hometitle">{{ homeLink.title || 'Dashboard' }}</span>
                                <span class="evs-sb__homesub">{{ dashboardLain.length + 1 }} dashboard</span>
                            </span>
                        </a>
                        <button
                            type="button" class="evs-sb__homechev" :class="{ 'is-open': dashboardBuka }"
                            :aria-expanded="dashboardBuka" title="Tampilkan dashboard lain"
                            @click="dashboardBuka = !dashboardBuka"
                        >
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M6 9l6 6 6-6" /></svg>
                        </button>
                    </div>
                    <div class="evs-grp__body" :class="{ 'is-open': dashboardBuka }" :style="dashboardBuka ? { maxHeight: dashboardLain.length * 46 + 8 + 'px' } : {}">
                        <a v-for="it in dashboardLain" :key="it.id" class="evs-item" :class="{ 'is-active': it.isActive }" :href="it.url" @click="visit($event, it.url)">
                            <span class="evs-item__dot" :class="{ 'is-active': it.isActive }"></span>
                            <span class="evs-item__txt">{{ it.title }}</span>
                        </a>
                    </div>
                </div>

                <div class="evs-sb__label">MODUL</div>

                <div class="evs-sb__mods">
                    <template v-for="m in modules" :key="m.id">
                        <!-- Kepala modul -->
                        <button type="button" class="evs-mod" :class="{ 'is-open': m.id === openModule }" @click="toggleModule(m.id)">
                            <span class="evs-mod__ico"><i :class="m.icon || 'bi bi-grid'" style="font-size: 19px"></i></span>
                            <span style="flex: 1; min-width: 0">
                                <span class="evs-mod__title">{{ m.label || m.name }}</span>
                                <span class="evs-mod__sub">{{ m.subtitle || '' }}</span>
                            </span>
                            <svg class="evs-mod__chev" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M6 9l6 6 6-6" /></svg>
                        </button>

                        <!-- Isi modul: grup akordeon -->
                        <div class="evs-mod__body" :class="{ 'is-open': m.id === openModule }">
                            <div class="evs-groups">
                                <template v-for="g in m.groups || []" :key="g.id">
                                    <button type="button" class="evs-grp" :class="{ 'is-active': g.id === m.activeGroupId }" @click="toggleGroup(g.id)">
                                        <span class="evs-grp__ico" :class="{ 'is-active': g.id === m.activeGroupId }">
                                            <i :class="groupIcon(g)" style="font-size: 16px"></i>
                                        </span>
                                        <span style="flex: 1; text-align: left">{{ g.title }}</span>
                                        <svg class="evs-grp__chev" :class="{ 'is-open': openGroups[g.id] }" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#aab2c5" stroke-width="2.4" stroke-linecap="round"><path d="M9 6l6 6-6 6" /></svg>
                                    </button>
                                    <div class="evs-grp__body" :class="{ 'is-open': openGroups[g.id] }" :style="openGroups[g.id] ? { maxHeight: tinggiGrup(g) + 'px' } : {}">
                                        <!--
                                            Menu tanpa sub-grup digambar LEBIH DULU,
                                            tepat di bawah judul grupnya. Menu yang
                                            baru ditambahkan lewat Master Menu belum
                                            punya sub-grup, dan menaruhnya di ujung
                                            bawah setelah semua sub-grup membuatnya
                                            terlihat seperti sisa, bukan seperti
                                            menu yang belum diberi tempat.
                                        -->
                                        <a v-for="it in g.items || []" :key="it.id" class="evs-item" :class="{ 'is-active': it.isActive }" :href="it.url" @click="visit($event, it.url)">
                                            <span class="evs-item__dot" :class="{ 'is-active': it.isActive }"></span>
                                            <span class="evs-item__txt">{{ it.title }}</span>
                                        </a>

                                        <div v-for="sb in g.subs || []" :key="sb.id" class="evs-sub">
                                            <button type="button" class="evs-sub__head" :class="{ 'is-open': openSubs[sb.id] }" @click="toggleSub(sb.id)">
                                                <svg class="evs-sub__chev" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round"><path d="M9 6l6 6-6 6" /></svg>
                                                <span class="evs-sub__txt">{{ sb.title }}</span>
                                                <span class="evs-sub__n">{{ (sb.items || []).length }}</span>
                                            </button>
                                            <div class="evs-sub__body" :class="{ 'is-open': openSubs[sb.id] }" :style="openSubs[sb.id] ? { maxHeight: (sb.items || []).length * 46 + 6 + 'px' } : {}">
                                                <a v-for="it in sb.items || []" :key="it.id" class="evs-item evs-item--sub" :class="{ 'is-active': it.isActive }" :href="it.url" @click="visit($event, it.url)">
                                                    <span class="evs-item__dot" :class="{ 'is-active': it.isActive }"></span>
                                                    <span class="evs-item__txt">{{ it.title }}</span>
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </div>

        <!-- Contekan papan ketik selama ada hasil. Disembunyikan di layar
             sentuh (lihat @media hover: none) — di sana tidak ada ↑/↓/Esc. -->
        <div v-if="hasil?.total" class="evs-kaki-cari" aria-hidden="true">
            <span><kbd>↑</kbd><kbd>↓</kbd> pilih</span>
            <span><kbd>↵</kbd> buka</span>
            <span><kbd>Esc</kbd> hapus</span>
        </div>

        <!-- Footer user + dropdown profil (perilaku lama dipertahankan) -->
        <div class="evs-sb__foot" style="position: relative" @click.stop>
            <div class="shell-profile-menu" :class="{ 'is-visible': shell.state.showProfileMenu && expanded }" @click.stop>
                <button class="shell-profile-menu__item shell-btn" type="button" @click.stop="profileAction('/profil')">
                    <i class="bi bi-person-circle"></i>
                    <span>Profil Saya</span>
                </button>
                <!-- "Tentang" DIHAPUS: tidak pernah ada route /about, jadi satu-
                     satunya yang dilakukannya adalah melempar pemakainya ke
                     halaman 404 dari dalam menu profilnya sendiri. -->
                <div class="shell-profile-divider"></div>
                <button class="shell-profile-menu__item shell-btn is-danger" type="button" @click.stop="profileAction('/logout')">
                    <i class="bi bi-box-arrow-right"></i>
                    <span>Keluar Sesi</span>
                </button>
            </div>

            <button type="button" class="evs-user" :class="{ 'is-open': shell.state.showProfileMenu }" @click.stop="toggleProfileMenu">
                <span class="evs-user__avatar">{{ initials }}</span>
                <span style="flex: 1; min-width: 0; text-align: left">
                    <span class="evs-user__name">{{ user.name || 'Pengguna' }}</span>
                    <span class="evs-user__role">{{ roleLabel }}</span>
                </span>
                <svg class="evs-user__chev" :class="{ 'is-rot': shell.state.showProfileMenu }" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#aab2c5" stroke-width="2.4" stroke-linecap="round" style="flex: 0 0 auto"><path d="M6 15l6-6 6 6" /></svg>
            </button>

            <!-- VERSI APLIKASI — baris terakhir sidebar.
                 Ditaruh di sini, bukan di topbar: topbar dipakai untuk hal yang
                 dikerjakan (notifikasi, profil, jam), sedangkan versi hanya
                 dicari ketika ada yang perlu dilaporkan. Ia harus mudah
                 DITEMUKAN tanpa pernah ikut meminta perhatian.

                 Disembunyikan saat sidebar terlipat — di lebar 64px, teks
                 sekecil ini cuma jadi noda. -->
            <div v-if="expanded" class="evs-ver">
                <span class="evs-ver__dot"></span>
                <span class="evs-ver__num">Versi {{ versi }}</span>
            </div>
        </div>
    </aside>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { useShellState } from '../../composables/useShellState';
import { cariMenu, susunIndeksMenu } from '@utils/cariMenu';

const props = defineProps({
    user: { type: Object, default: () => ({}) },
    brand: { type: Object, default: () => ({}) },
    navigation: { type: Object, default: () => ({ home: null, modules: [] }) },
});

const shell = useShellState();

/**
 * Versi aplikasi, dari shared props Inertia (HandleInertiaRequests).
 *
 * Cadangan '1.0.0' senada dengan config: panel yang berbunyi "vundefined"
 * membuat orang melaporkan versinya sebagai rusak, padahal yang rusak hanya
 * penampilnya.
 */
const versi = computed(() => usePage().props?.appVersion || '1.0.0');
const imgErr = reactive({ mainLogo: false, mainLogo2: false });

const modules = computed(() => props.navigation?.modules || []);
const homeLink = computed(() => props.navigation?.home || {});
const subsidiaries = computed(() => props.brand?.subsidiaries || []);
const sectionLabel = computed(() => (props.navigation?.sectionLabel ? props.navigation.sectionLabel.toUpperCase() + ' SYSTEM' : 'UNIFIED PLATFORM'));

const initials = computed(() => {
    const n = (props.user?.name || 'EV').trim().split(/\s+/);
    return ((n[0]?.[0] || '') + (n[1]?.[0] || '')).toUpperCase() || 'EV';
});
const roleLabel = computed(() => props.user?.department || props.user?.nik || 'Administrator');

// Kotak cari sedang difokus. Ikut menahan sidebar desktop tetap terbuka:
// tanpa ini, orang yang sedang mengetik kehilangan sidebarnya begitu mouse
// tergeser keluar — hover yang tadi membukanya sudah berakhir. Ini juga yang
// membuka sidebar saat Ctrl+K ditekan tanpa mouse di atasnya.
const cariFokus = ref(false);

// Sidebar terbuka: mobile pakai drawer; desktop pakai kunci (pin), hover,
// ATAU kotak cari yang sedang difokus.
const expanded = computed(() => (shell.state.isMobile ? shell.state.mobileSidebarOpen : shell.state.sidebarLocked || shell.state.sidebarExpanded || cariFokus.value));

/* ── Akordeon modul & grup ── */
const openModule = ref(null);
const openGroups = reactive({});
const openSubs = reactive({});

/**
 * Dashboard SELAIN beranda yang boleh dilihat akun ini.
 *
 * Datang dari server (LayoutShell): anggota grup yang memuat URL beranda.
 * Kosong pada akun yang cuma punya satu dashboard — dan di situ sidebar
 * menggambar tautan tunggal, bukan collapse berisi satu baris.
 */
const dashboardLain = computed(() => homeLink.value?.items || []);
// Terbuka sendiri saat yang sedang dibuka memang salah satu dashboard di
// dalamnya; kalau tidak, ia mulai tertutup supaya menu utama tidak terdorong.
const dashboardBuka = ref(false);

function syncFromNav() {
    const aktif = modules.value.find((m) => m.isActive) || modules.value[0] || null;
    openModule.value = aktif ? aktif.id : null;
    Object.keys(openGroups).forEach((k) => delete openGroups[k]);
    if (aktif && aktif.activeGroupId) openGroups[aktif.activeGroupId] = true;

    // Sub-grup yang memuat halaman aktif ikut terbuka. Tanpa ini, membuka
    // Master Pertanyaan Skrining memperlihatkan grup "Master Data" yang
    // terbuka tapi seluruh sub-grupnya tertutup — halaman yang sedang dibuka
    // justru satu-satunya yang tidak terlihat.
    Object.keys(openSubs).forEach((k) => delete openSubs[k]);
    if (aktif && aktif.activeSubId) openSubs[aktif.activeSubId] = true;
    // Hanya bila anaknya yang aktif — bukan berandanya sendiri. Membuka
    // collapse saat Dashboard Utama dibuka justru menyembunyikan bahwa
    // halaman yang aktif adalah kepalanya, bukan salah satu anaknya.
    dashboardBuka.value = dashboardLain.value.some((it) => it.isActive);
}
watch(modules, syncFromNav, { immediate: true, deep: true });
watch(dashboardLain, syncFromNav, { deep: true });

function toggleModule(id) {
    openModule.value = openModule.value === id ? null : id;
}
function toggleGroup(id) {
    openGroups[id] = !openGroups[id];
}
function toggleSub(id) {
    openSubs[id] = !openSubs[id];
}

/**
 * Tinggi collapse grup.
 *
 * Dihitung, bukan diserahkan ke `max-height: 9999px`: nilai tetap yang jauh
 * lebih besar daripada isinya membuat animasi buka-tutup berjalan sebagian
 * besar waktunya di ruang kosong — kotaknya sudah selesai bergerak sementara
 * transisinya masih berjalan.
 *
 * Sub-grup yang tertutup hanya menyumbang tinggi kepalanya sendiri.
 */
function tinggiGrup(g) {
    const item = (g.items || []).length * 46;
    const sub = (g.subs || []).reduce(
        (n, sb) => n + 34 + (openSubs[sb.id] ? (sb.items || []).length * 46 + 6 : 0),
        0,
    );

    return item + sub + 10;
}
function railModule(id) {
    openModule.value = id;
    if (!shell.state.isMobile && !shell.state.sidebarLocked) shell.toggleDesktopSidebarLock();
}

/* ── Buka/tutup ── */
let hoverT = null;
function hoverOpen() {
    clearTimeout(hoverT);
    shell.setSidebarExpandedByHover(true);
}
function hoverClose() {
    clearTimeout(hoverT);
    hoverT = setTimeout(() => shell.setSidebarExpandedByHover(false), 150);
}
function togglePin() {
    if (shell.state.isMobile) {
        shell.toggleMobileSidebar();
        return;
    }
    shell.toggleDesktopSidebarLock();
}
function visit(e, url) {
    if (!url) return;
    e.preventDefault();
    shell.closeMobileSidebar();
    router.visit(url);
}

/* ── Dropdown profil footer (perilaku shell lama) ── */
function toggleProfileMenu() {
    shell.state.showProfileMenu = !shell.state.showProfileMenu;
}
function profileAction(url) {
    shell.resetInteractionState();
    shell.closeMobileSidebar();
    if (!url) return;
    if (url === '/logout') {
        router.get('/logout', {}, { preserveScroll: true });
        return;
    }
    router.visit(url);
}

/* ── Cari menu ── */

/**
 * Kotak cari baru muncul bila menunya cukup banyak untuk perlu dicari.
 * Portal kandidat hanya punya dua-tiga menu — kotak cari di atas dua baris
 * cuma menambah satu hal untuk dibaca tanpa pernah menghemat apa pun.
 */
const MIN_MENU_UNTUK_CARI = 6;

const esMac = typeof navigator !== 'undefined' && /mac|iphone|ipad|ipod/i.test(navigator.userAgentData?.platform || navigator.platform || '');
const pintasan = esMac ? '⌘K' : 'Ctrl K';

const cariInput = ref(null);
const gulirEl = ref(null);
const kueri = ref('');
const kursor = ref(0); // hasil yang disorot — digerakkan ↑/↓ dan mouse

const indeksMenu = computed(() => susunIndeksMenu(props.navigation, { ikonGrup: groupIcon }));
const bisaCari = computed(() => indeksMenu.value.length >= MIN_MENU_UNTUK_CARI);
const hasil = computed(() => (bisaCari.value ? cariMenu(indeksMenu.value, kueri.value) : null));
const kursorId = computed(() => (hasil.value?.total ? `evs-hasil-${kursor.value}` : undefined));
const ringkasCari = computed(() => {
    if (!hasil.value) return '';

    return hasil.value.total ? `${hasil.value.total} menu cocok` : 'Tidak ada menu yang cocok';
});
// Saran saat hasil kosong: nama grup milik akun ini sendiri, bukan daftar
// tetap — admin dan kandidat punya grup yang berbeda.
const saranCari = computed(() => [...new Set(indeksMenu.value.map((e) => e.grup.title))].slice(0, 4));

// Kueri berubah → sorotan kembali ke hasil teratas, yang paling cocok.
watch(kueri, () => {
    kursor.value = 0;
});
// Menu bisa berubah di tengah pencarian (pindah halaman saat sidebar
// dikunci); sorotan tidak boleh menunjuk hasil yang sudah tidak ada.
watch(
    () => hasil.value?.total ?? 0,
    (n) => {
        if (kursor.value >= n) kursor.value = 0;
    },
);

// Masuk mode cari: ingat posisi gulir menu, hasil dimulai dari atas.
// Keluar: menu kembali ke posisi semula — menghapus kueri tidak boleh
// melempar orang ke puncak daftar yang tadi sudah ia gulir.
let gulirMenu = 0;
watch(
    () => !!hasil.value,
    (sedangCari) => {
        const el = gulirEl.value;
        if (!el) return;
        if (sedangCari) gulirMenu = el.scrollTop;
        nextTick(() => {
            el.scrollTop = sedangCari ? 0 : gulirMenu;
        });
    },
);

// Sidebar tertutup → kueri dilupakan. Membuka sidebar lagi mestinya
// memperlihatkan menu lengkap, bukan sisa pencarian yang sudah tak diingat.
// (AppShell layout persisten: tanpa ini kueri ikut terbawa antarhalaman.)
watch(expanded, (buka) => {
    if (!buka) kueri.value = '';
});

function bukaCari() {
    if (!bisaCari.value) return;
    if (shell.state.isMobile) shell.openMobileSidebar();
    // Fokus itu sendiri yang membuka sidebar desktop (lihat cariFokus).
    nextTick(() => {
        cariInput.value?.focus({ preventScroll: true });
        cariInput.value?.select();
    });
}
function hapusCari() {
    kueri.value = '';
    cariInput.value?.focus({ preventScroll: true });
}
function pakaiSaran(s) {
    kueri.value = s;
    cariInput.value?.focus({ preventScroll: true });
}

function bukaHasil(it, tabBaru = false) {
    if (!it?.url || it.url === '#') return;
    if (tabBaru) {
        window.open(it.url, '_blank', 'noopener');
        return;
    }
    kueri.value = '';
    cariInput.value?.blur();
    shell.closeMobileSidebar();
    router.visit(it.url);
}
// Klik biasa → navigasi Inertia. Ctrl/⌘/Shift + klik dibiarkan ke peramban
// (tab/jendela baru), sama seperti tautan mana pun.
function pilihHasil(e, it) {
    if (e.ctrlKey || e.metaKey || e.shiftKey || e.altKey) return;
    e.preventDefault();
    bukaHasil(it);
}

function tombolCari(e) {
    if (e.isComposing) return;
    const n = hasil.value?.total || 0;

    if ((e.key === 'ArrowDown' || e.key === 'ArrowUp') && n) {
        e.preventDefault();
        kursor.value = (kursor.value + (e.key === 'ArrowDown' ? 1 : n - 1)) % n;
        nextTick(gulirKeKursor);
    } else if (e.key === 'Enter' && n) {
        e.preventDefault();
        bukaHasil(hasil.value.rata[kursor.value], e.ctrlKey || e.metaKey);
    } else if (e.key === 'Escape') {
        // Esc pertama mengosongkan, Esc kedua melepas kotak cari. Tidak
        // diteruskan ke dokumen: panel halaman yang ikut mendengar Esc tidak
        // boleh ikut tertutup hanya karena orang membatalkan pencarian.
        e.preventDefault();
        e.stopPropagation();
        if (kueri.value) {
            kueri.value = '';
            return;
        }
        cariInput.value?.blur();
        shell.closeMobileSidebar();
    }
}

/** Jaga hasil yang disorot tetap terlihat saat berpindah dengan ↑/↓. */
function gulirKeKursor() {
    const wadah = gulirEl.value;
    const baris = wadah?.querySelector('.evs-hit.is-kursor');
    if (!wadah || !baris) return;
    if (kursor.value === 0) {
        wadah.scrollTop = 0;
        return;
    }

    // Baris pertama sebuah kelompok ikut membawa labelnya ke layar — hasil
    // tanpa label grupnya kehilangan jawaban "ini menu di bagian mana".
    const label = baris.previousElementSibling?.classList.contains('evs-hasil__label') ? baris.previousElementSibling : null;
    const w = wadah.getBoundingClientRect();
    const atas = (label || baris).getBoundingClientRect().top;
    const bawah = baris.getBoundingClientRect().bottom;

    if (atas < w.top + 4) wadah.scrollTop -= w.top + 4 - atas;
    else if (bawah > w.bottom - 4) wadah.scrollTop += bawah - (w.bottom - 4);
}

/**
 * Pintasan: Ctrl+K (⌘K di Mac) dari mana saja, atau "/" saat tidak sedang
 * mengetik. Dilewati di dalam editor teks kaya (Quill dsb.) — di sana Ctrl+K
 * lazim berarti "sisipkan tautan" — dan selama ada modal terbuka, karena
 * sidebar berada di BAWAH lapisan modal dan kotak carinya tak terlihat.
 */
function pintasanCari(e) {
    if (!bisaCari.value || e.defaultPrevented || e.isComposing || e.repeat) return;

    const t = e.target;
    const kaya = !!t?.isContentEditable;
    const mengetik = kaya || /^(INPUT|TEXTAREA|SELECT)$/.test(t?.tagName || '');
    const modK = (e.key === 'k' || e.key === 'K') && (esMac ? e.metaKey : e.ctrlKey) && !e.altKey && !e.shiftKey;
    const garis = e.key === '/' && !e.ctrlKey && !e.metaKey && !e.altKey && !mengetik;

    if (!((modK && !kaya) || garis) || adaModalTerbuka()) return;
    e.preventDefault();
    bukaCari();
}
// Hanya aria-modal — role="dialog" saja juga dipakai panel yang selalu
// tampil (SpotlightBoard), dan menghitungnya mematikan pintasan di sana.
function adaModalTerbuka() {
    return [...document.querySelectorAll('[aria-modal="true"]')].some((el) => el.getClientRects().length > 0);
}

// Klik di luar sidebar → tutup dropdown profil.
function onDocMousedown(e) {
    if (!shell.state.showProfileMenu) return;
    const root = document.querySelector('.evs-sb');
    if (root && !root.contains(e.target)) shell.resetInteractionState();
}
onMounted(() => {
    document.addEventListener('mousedown', onDocMousedown);
    document.addEventListener('keydown', pintasanCari);
});
onBeforeUnmount(() => {
    document.removeEventListener('mousedown', onDocMousedown);
    document.removeEventListener('keydown', pintasanCari);
});

/* Ikon grup — fallback tematik per judul (props grup tak membawa ikon). */
function groupIcon(g) {
    const t = (g.title || '').toLowerCase();
    // 'akses' diperiksa sebelum 'akun': grup "Hak Akses & Akun" memuat
    // keduanya, dan yang menjelaskan isinya adalah aksesnya.
    if (t.includes('akses')) return 'bi bi-person-lock';
    if (t.includes('master')) return 'bi bi-database';
    if (t.includes('operasional')) return 'bi bi-list-task';
    if (t.includes('seleksi')) return 'bi bi-clipboard-check';
    if (t.includes('pengaturan') || t.includes('setting')) return 'bi bi-gear';
    if (t.includes('lamaran')) return 'bi bi-file-earmark-text';
    if (t.includes('akun')) return 'bi bi-person-vcard';
    if (t.includes('data')) return 'bi bi-folder2';

    // Grup yang seluruh isinya bersub-grup punya `items` kosong; ikonnya
    // diambil dari menu pertama di sub-grup pertama.
    const pertama = (g.items || [])[0] || ((g.subs || [])[0] || {}).items?.[0];

    return pertama?.icon || 'bi bi-folder2';
}
</script>

<style scoped>
/* ═══ RAIL ═══ */
/* Susunan z-index MENGIKUTI SHELL LAMA: seluruh shell ≤ 1030 supaya progress
   bar bawaan Inertia (NProgress, z-index 1031) selalu tampil utuh di atasnya. */
.evs-rail {
    position: fixed;
    inset: 0 auto 0 0;
    z-index: 1028;
    width: 68px;
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 16px 0;
    background: rgba(255, 255, 255, 0.72);
    backdrop-filter: blur(14px);
    -webkit-backdrop-filter: blur(14px);
    border-right: 1px solid rgba(226, 232, 240, 0.7);
}
.evs-rail__logo {
    appearance: none;
    cursor: pointer;
    padding: 0;
    width: 46px;
    height: 46px;
    border-radius: 14px;
    background: linear-gradient(135deg, #f4f2ff, #eef2ff);
    display: flex;
    align-items: center;
    justify-content: center;
    transition: box-shadow 0.18s;
    border: 2px solid transparent;
}
.evs-rail__logo.is-pinned {
    border-color: #8b5cf6;
    box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.18);
}
.evs-rail__logo img {
    width: 30px;
    height: 30px;
    object-fit: contain;
}
.evs-rail__logofb {
    font-size: 12px;
    font-weight: 900;
    color: #4f46e5;
}
.evs-rail__btn {
    appearance: none;
    border: none;
    cursor: pointer;
    width: 44px;
    height: 44px;
    border-radius: 13px;
    margin-bottom: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.16s;
    background: transparent;
    color: #94a3b8;
    text-decoration: none;
}
.evs-rail__btn:hover {
    background: #eef0f7;
    color: #4f46e5;
}
.evs-rail__btn.is-active {
    background: linear-gradient(135deg, #8b5cf6, #6366f1);
    color: #fff;
    box-shadow: 0 8px 20px rgba(99, 102, 241, 0.34);
}
/* Pemisah tombol cari dari tombol halaman: cari adalah ALAT, bukan tujuan. */
.evs-rail__sep {
    flex: 0 0 auto;
    width: 22px;
    height: 1px;
    margin: 0 0 10px;
    background: #e2e6ef;
}
.evs-rail__avatar {
    width: 42px;
    height: 42px;
    border-radius: 13px;
    background: linear-gradient(135deg, #8b5cf6, #6366f1);
    color: #fff;
    font-size: 13px;
    font-weight: 800;
    display: flex;
    align-items: center;
    justify-content: center;
}

/* ═══ SCRIM ═══ */
.evs-scrim {
    position: fixed;
    inset: 0;
    z-index: 1029;
    background: rgba(15, 23, 42, 0.42);
    backdrop-filter: blur(2px);
    transition: opacity 0.3s;
    opacity: 0;
    pointer-events: none;
}
.evs-scrim.is-on {
    opacity: 1;
    pointer-events: auto;
}

/* ═══ SIDEBAR OVERLAY ═══ */
.evs-sb {
    position: fixed;
    top: 0;
    left: 0;
    bottom: 0;
    z-index: 1030; /* sama seperti shell lama — tetap di bawah NProgress (1031) */
    width: 290px;
    display: flex;
    flex-direction: column;
    background: #fff;
    border-right: 1px solid #eef0f7;
    box-shadow: 0 30px 90px rgba(15, 23, 42, 0.22);
    transition: transform 0.3s cubic-bezier(0.22, 1, 0.36, 1), opacity 0.3s;
    transform: translateX(-14px);
    opacity: 0;
    pointer-events: none;
}
.evs-sb.is-open {
    transform: translateX(0);
    opacity: 1;
    pointer-events: auto;
}
.evs-sb.is-mobile {
    width: min(300px, 90vw);
    transform: translateX(-104%);
    opacity: 1;
    transition: transform 0.34s cubic-bezier(0.22, 1, 0.36, 1);
}
.evs-sb.is-mobile.is-open {
    transform: translateX(0);
}

.evs-sb__head {
    padding: 18px 18px 14px;
    flex: 0 0 auto;
}
.evs-sb__brandrow {
    display: flex;
    align-items: center;
    gap: 12px;
}
.evs-sb__logo {
    appearance: none;
    cursor: pointer;
    padding: 0;
    width: 52px;
    height: 52px;
    border-radius: 16px;
    background: linear-gradient(135deg, #f4f2ff, #eef2ff);
    border: 1px solid #e7e3fb;
    display: flex;
    align-items: center;
    justify-content: center;
    flex: 0 0 auto;
}
.evs-sb__logo img {
    width: 34px;
    height: 34px;
    object-fit: contain;
}
.evs-sb__brandtxt {
    min-width: 0;
    flex: 1;
}
.evs-sb__brandtitle {
    font-size: 16px;
    font-weight: 900;
    color: #0f172a;
    letter-spacing: -0.02em;
    line-height: 1;
}
.evs-sb__brandsub {
    font-size: 10px;
    font-weight: 800;
    letter-spacing: 0.18em;
    color: #8b93a7;
    margin-top: 3px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.evs-sb__pin {
    appearance: none;
    cursor: pointer;
    width: 34px;
    height: 34px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex: 0 0 auto;
    transition: all 0.16s;
    border: 1px solid #e6e9f3;
    background: #fff;
    color: #94a3b8;
}
.evs-sb__pin.is-pinned {
    border-color: transparent;
    background: linear-gradient(135deg, #8b5cf6, #6366f1);
    color: #fff;
}
.evs-sb__subs {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-top: 14px;
    padding: 10px 12px;
    border-radius: 14px;
    background: #f8f9fc;
    border: 1px solid #eef0f7;
}
.evs-sb__subs img {
    height: 19px;
    width: auto;
    object-fit: contain;
    opacity: 0.9;
    flex: 1;
    min-width: 0;
}
.evs-sb__subsdiv {
    width: 1px;
    height: 18px;
    background: #e2e8f0;
    flex: 0 0 auto;
}

.evs-sb__scroll {
    flex: 1;
    overflow-y: auto;
    padding: 6px 12px 12px;
}
.evs-sb__home {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 11px 12px;
    border-radius: 13px;
    transition: background 0.16s;
    text-decoration: none;
}
.evs-sb__home:hover {
    background: #f4f5fb;
}
.evs-sb__homeico {
    width: 36px;
    height: 36px;
    border-radius: 11px;
    background: #f1f2f9;
    color: #64748b;
    display: flex;
    align-items: center;
    justify-content: center;
    flex: 0 0 auto;
}
.evs-sb__hometitle {
    display: block;
    font-size: 14px;
    font-weight: 800;
    color: #1e293b;
}
.evs-sb__homesub {
    display: block;
    font-size: 11px;
    color: #94a3b8;
}
/* HALAMAN BERANDA SEDANG DIBUKA. Dulu tidak ada aturannya sama sekali: baris
   Dashboard di sidebar terbuka tidak pernah menyala, padahal tombol kembarnya
   di rail menyala — dua penanda untuk satu halaman yang saling bertentangan. */
.evs-sb__home.is-active {
    background: #eef2ff;
}
.evs-sb__home.is-active .evs-sb__homeico {
    background: #6366f1;
    color: #fff;
}
.evs-sb__home.is-active .evs-sb__hometitle {
    color: #3730a3;
}

/* ── Beranda sebagai KEPALA COLLAPSE (≥2 dashboard) ── */
.evs-sb__homewrap {
    border-radius: 13px;
}
/* Kepalanya dibelah dua: tautan menuju beranda + tombol buka-tutup sendiri.
   Keduanya harus terpisah — kalau seluruh kepala jadi tombol collapse,
   Dashboard Utama tidak punya jalan menuju dirinya sendiri. */
.evs-sb__home--grp {
    gap: 0;
    padding: 0;
}
.evs-sb__homelink {
    display: flex;
    align-items: center;
    gap: 12px;
    flex: 1;
    min-width: 0;
    padding: 11px 12px;
    border-radius: 13px;
    text-decoration: none;
}
.evs-sb__home--grp:hover {
    background: transparent;
}
.evs-sb__homelink:hover {
    background: #f4f5fb;
}
.evs-sb__homechev {
    appearance: none;
    border: 0;
    background: none;
    cursor: pointer;
    flex: 0 0 auto;
    width: 32px;
    height: 32px;
    margin-right: 6px;
    border-radius: 9px;
    display: grid;
    place-items: center;
    color: #aab2c5;
    transition: transform 0.2s ease, background 0.16s, color 0.16s;
}
.evs-sb__homechev:hover {
    background: #eef0f7;
    color: #6366f1;
}
.evs-sb__homechev.is-open {
    transform: rotate(180deg);
    color: #6366f1;
}
.evs-sb__label {
    font-size: 10px;
    font-weight: 800;
    letter-spacing: 0.18em;
    color: #aab2c5;
    padding: 16px 12px 8px;
}
.evs-sb__mods {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

/* Modul (level 1) */
.evs-mod {
    position: relative;
    appearance: none;
    cursor: pointer;
    font-family: inherit;
    width: 100%;
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px;
    border-radius: 14px;
    transition: all 0.18s;
    border: 1px solid transparent;
    background: transparent;
}
.evs-mod.is-open {
    border-color: rgba(99, 102, 241, 0.18);
    background: linear-gradient(135deg, rgba(139, 92, 246, 0.1), rgba(99, 102, 241, 0.1));
    box-shadow: inset 3px 0 0 #6366f1;
}
.evs-mod__ico {
    width: 38px;
    height: 38px;
    border-radius: 11px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex: 0 0 auto;
    background: #f1f2f9;
    color: #8792a6;
}
.evs-mod.is-open .evs-mod__ico {
    background: linear-gradient(135deg, #8b5cf6, #6366f1);
    color: #fff;
    box-shadow: 0 8px 18px rgba(99, 102, 241, 0.34);
}
.evs-mod__title {
    display: block;
    font-size: 14.5px;
    font-weight: 900;
    letter-spacing: -0.01em;
    color: #1e293b;
    text-align: left;
}
.evs-mod.is-open .evs-mod__title {
    color: #4338ca;
}
.evs-mod__sub {
    display: block;
    font-size: 11px;
    font-weight: 600;
    color: #94a3b8;
    text-align: left;
}
.evs-mod.is-open .evs-mod__sub {
    color: #7c74b0;
}
.evs-mod__chev {
    flex: 0 0 auto;
    color: #8b83c9;
    transition: transform 0.26s;
}
.evs-mod.is-open .evs-mod__chev {
    transform: rotate(180deg);
}
.evs-mod__body {
    overflow: hidden;
    transition: all 0.32s ease;
    padding-left: 8px;
    max-height: 0;
    opacity: 0;
    margin: 0;
}
.evs-mod__body.is-open {
    max-height: 1600px;
    opacity: 1;
    margin: 2px 0 6px;
}
.evs-groups {
    display: flex;
    flex-direction: column;
    gap: 2px;
    padding-top: 4px;
}

/* Grup (level 2) */
.evs-grp {
    appearance: none;
    cursor: pointer;
    font-family: inherit;
    width: 100%;
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 11px 12px;
    border-radius: 13px;
    border: none;
    background: transparent;
    font-size: 13.5px;
    font-weight: 800;
    color: #5b6478;
    transition: background 0.16s;
}
.evs-grp:hover,
.evs-grp.is-active {
    background: #f4f5fb;
}
.evs-grp__ico {
    width: 34px;
    height: 34px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex: 0 0 auto;
    background: #f1f2f9;
    color: #8792a6;
}
.evs-grp__ico.is-active {
    background: rgba(99, 102, 241, 0.12);
    color: #6366f1;
}
.evs-grp__chev {
    flex: 0 0 auto;
    transition: transform 0.24s;
}
.evs-grp__chev.is-open {
    transform: rotate(90deg);
}
.evs-grp__body {
    overflow: hidden;
    transition: all 0.26s ease;
    padding-left: 12px;
    display: flex;
    flex-direction: column;
    gap: 1px;
    max-height: 0;
    opacity: 0;
    margin: 0;
}
.evs-grp__body.is-open {
    opacity: 1;
    margin: 2px 0 4px;
}

/* Item (level 3) */
.evs-item {
    display: flex;
    align-items: center;
    gap: 11px;
    padding: 9px 12px 9px 14px;
    border-radius: 11px;
    font-size: 13px;
    font-weight: 600;
    color: #6b7488;
    transition: all 0.14s;
    text-decoration: none;
}
.evs-item:hover {
    background: #f4f5fb;
    color: #4338ca;
}
.evs-item.is-active {
    font-weight: 800;
    color: #4338ca;
    background: rgba(99, 102, 241, 0.1);
}
.evs-item__dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: #c3cad8;
    flex: 0 0 auto;
}
.evs-item__dot.is-active {
    width: 7px;
    height: 7px;
    background: #6366f1;
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.18);
}
.evs-item__txt {
    min-width: 0;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* ── SUB-GRUP (level 3, menu jadi level 4) ───────────────────────────────────
   Kepalanya sengaja TIDAK menyerupai menu: huruf kecil berspasi, tanpa titik,
   tanpa latar saat disorot penuh. Kalau ia terlihat seperti menu, orang
   mengkliknya berharap pindah halaman — dan yang terjadi cuma daftar terbuka. */
.evs-sub {
    display: flex;
    flex-direction: column;
}
.evs-sub__head {
    display: flex;
    align-items: center;
    gap: 7px;
    width: 100%;
    border: 0;
    background: transparent;
    padding: 9px 10px 6px 6px;
    font-family: inherit;
    font-size: 10.5px;
    font-weight: 800;
    letter-spacing: 0.085em;
    text-transform: uppercase;
    color: #98a0b5;
    cursor: pointer;
    text-align: left;
    transition: color 0.14s;
}
.evs-sub__head:hover { color: #4338ca; }
.evs-sub__head.is-open { color: #4f46e5; }
.evs-sub__chev {
    flex: 0 0 auto;
    color: #c3cad8;
    transition: transform 0.22s;
}
.evs-sub__head.is-open .evs-sub__chev { transform: rotate(90deg); color: #6366f1; }
.evs-sub__txt {
    flex: 1;
    min-width: 0;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
/* Jumlah isinya. Ditulis kecil dan samar: ia menjawab "seberapa panjang kalau
   saya buka", bukan menuntut dibaca. */
.evs-sub__n {
    flex: 0 0 auto;
    font-size: 9.5px;
    font-weight: 700;
    letter-spacing: 0;
    color: #b6bdcf;
    background: #f1f3f9;
    border-radius: 5px;
    padding: 1px 5px;
    font-variant-numeric: tabular-nums;
}
.evs-sub__head.is-open .evs-sub__n { background: rgba(99, 102, 241, 0.11); color: #4f46e5; }

.evs-sub__body {
    overflow: hidden;
    transition: all 0.24s ease;
    display: flex;
    flex-direction: column;
    gap: 1px;
    max-height: 0;
    opacity: 0;
    /* Garis penghubung: menyatakan menu-menu ini milik kepalanya, bukan
       tetangga sejajar yang kebetulan menjorok. */
    margin-left: 6px;
    padding-left: 8px;
    border-left: 1px solid #eef0f7;
}
.evs-sub__body.is-open { opacity: 1; }
.evs-item--sub { padding-left: 10px; }

/* ═══ CARI MENU ═══════════════════════════════════════════════════════════
   Kotaknya mengikuti "Cari menu…" di HCLearn — latar abu lembut, kaca
   pembesar di kiri — dengan aksen ungu-indigo sidebar ini saat difokus. */
.evs-cari {
    display: flex;
    align-items: center;
    gap: 8px;
    height: 42px;
    margin: 12px 0 0;
    padding: 0 7px 0 12px;
    border: 1px solid #e8ebf3;
    border-radius: 13px;
    background: #f6f7fb;
    transition: background 0.16s, border-color 0.16s, box-shadow 0.16s;
}
.evs-cari:hover {
    border-color: #dfe3ee;
}
.evs-cari.is-fokus {
    border-color: rgba(99, 102, 241, 0.5);
    background: #fff;
    box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.12);
}
.evs-cari__ico {
    display: flex;
    flex: 0 0 auto;
    margin: 0;
    color: #9aa3b8;
    cursor: text;
    transition: color 0.16s;
}
.evs-cari.is-fokus .evs-cari__ico {
    color: #6366f1;
}
.evs-cari__input {
    flex: 1;
    min-width: 0;
    height: 100%;
    padding: 0;
    border: 0;
    outline: 0;
    background: transparent;
    font-family: inherit;
    font-size: 13.5px;
    font-weight: 600;
    color: #1e293b;
    -webkit-appearance: none;
    appearance: none;
}
.evs-cari__input::placeholder {
    font-weight: 500;
    color: #9aa3b8;
    opacity: 1;
}
/* Tombol × bawaan type="search" disembunyikan: bentuk dan letaknya berbeda
   di tiap peramban, dan Firefox tidak punya sama sekali. Penggantinya
   .evs-cari__hapus — sama di mana pun. */
.evs-cari__input::-webkit-search-cancel-button,
.evs-cari__input::-webkit-search-decoration {
    display: none;
    -webkit-appearance: none;
    appearance: none;
}
.evs-cari__hapus {
    appearance: none;
    flex: 0 0 auto;
    width: 26px;
    height: 26px;
    padding: 0;
    border: 0;
    border-radius: 8px;
    display: grid;
    place-items: center;
    background: #eef0f7;
    color: #64748b;
    cursor: pointer;
    transition: background 0.14s, color 0.14s;
}
.evs-cari__hapus:hover {
    background: #e6e4fb;
    color: #4338ca;
}
.evs-cari__kbd {
    flex: 0 0 auto;
    padding: 2px 6px;
    border: 1px solid #e3e7f0;
    border-bottom-width: 2px;
    border-radius: 7px;
    background: #fff;
    font-family: inherit;
    font-size: 10.5px;
    font-weight: 800;
    line-height: 1.35;
    letter-spacing: 0.02em;
    color: #8b93a7;
    white-space: nowrap;
}
.evs-sr {
    position: absolute;
    width: 1px;
    height: 1px;
    margin: -1px;
    padding: 0;
    border: 0;
    overflow: hidden;
    clip: rect(0 0 0 0);
    white-space: nowrap;
}

/* ── Hasil ── */
.evs-hasil {
    padding: 2px 0 8px;
    animation: evs-cari-masuk 0.2s ease-out;
}
@keyframes evs-cari-masuk {
    from {
        opacity: 0;
        transform: translateY(4px);
    }
    to {
        opacity: 1;
        transform: none;
    }
}
.evs-hasil__ringkas {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 10px 12px 2px;
    font-size: 10px;
    font-weight: 800;
    letter-spacing: 0.18em;
    color: #aab2c5;
}
.evs-hasil__total {
    padding: 2px 8px;
    border-radius: 999px;
    background: rgba(99, 102, 241, 0.1);
    font-size: 10.5px;
    letter-spacing: 0;
    color: #4f46e5;
    font-variant-numeric: tabular-nums;
}
.evs-hasil__label {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 12px 10px 6px;
}
.evs-hasil__chip {
    flex: 0 0 auto;
    width: 22px;
    height: 22px;
    border-radius: 7px;
    display: grid;
    place-items: center;
    background: #f1f2f9;
    color: #8792a6;
    font-size: 11px;
}
.evs-hasil__judul {
    min-width: 0;
    font-size: 10.5px;
    font-weight: 800;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    color: #8b93a7;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.evs-hasil__garis {
    flex: 1;
    min-width: 10px;
    height: 1px;
    background: linear-gradient(90deg, #e8ebf3, rgba(232, 235, 243, 0));
}
.evs-hasil__n {
    flex: 0 0 auto;
    padding: 1px 6px;
    border-radius: 5px;
    background: #f1f3f9;
    font-size: 10px;
    font-weight: 700;
    color: #98a0b5;
    font-variant-numeric: tabular-nums;
}

/* Satu hasil. Keadaan tersorot meminjam bahasa .evs-mod.is-open — garis
   indigo di kiri + ubin ikon bergradasi — supaya "yang akan dibuka Enter"
   terbaca sama dengan "modul yang sedang terbuka". */
.evs-hit {
    display: flex;
    align-items: center;
    gap: 11px;
    padding: 8px 10px;
    border: 1px solid transparent;
    border-radius: 12px;
    text-decoration: none;
    transition: background 0.14s, border-color 0.14s, box-shadow 0.14s;
}
.evs-hit + .evs-hit {
    margin-top: 2px;
}
.evs-hit.is-kursor {
    border-color: rgba(99, 102, 241, 0.18);
    background: linear-gradient(135deg, rgba(139, 92, 246, 0.09), rgba(99, 102, 241, 0.09));
    box-shadow: inset 3px 0 0 #6366f1;
}
.evs-hit__ico {
    flex: 0 0 auto;
    width: 34px;
    height: 34px;
    border-radius: 10px;
    display: grid;
    place-items: center;
    background: #f1f2f9;
    color: #8792a6;
    font-size: 16px;
    transition: background 0.14s, color 0.14s, box-shadow 0.14s;
}
/* Halaman yang sedang dibuka: ubinnya sudah berwarna walau tidak disorot. */
.evs-hit.is-active .evs-hit__ico {
    background: rgba(99, 102, 241, 0.12);
    color: #6366f1;
}
.evs-hit.is-kursor .evs-hit__ico {
    background: linear-gradient(135deg, #8b5cf6, #6366f1);
    color: #fff;
    box-shadow: 0 6px 14px rgba(99, 102, 241, 0.3);
}
.evs-hit__txt {
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: 2px;
}
/* Boleh dua baris, tidak dipangkas di baris pertama seperti menu biasa:
   yang sedang mencari justru perlu membaca nama menunya utuh untuk memilih
   ("Master Mode Pelaksan…" dan "Master Mode Pengum…" tak bisa dibedakan). */
.evs-hit__title {
    display: -webkit-box;
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 2;
    line-clamp: 2;
    overflow: hidden;
    font-size: 13px;
    font-weight: 700;
    line-height: 1.3;
    color: #334155;
    overflow-wrap: anywhere;
}
.evs-hit.is-kursor .evs-hit__title {
    color: #3730a3;
}
.evs-hit__path {
    display: flex;
    align-items: center;
    gap: 4px;
    min-width: 0;
    font-size: 11px;
    font-weight: 600;
    line-height: 1.2;
    color: #9aa3b8;
}
.evs-hit__path svg {
    flex: 0 0 auto;
    color: #c3cad8;
}
.evs-hit__pathtxt {
    min-width: 0;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.evs-hit__now {
    flex: 0 0 auto;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 3px 8px 3px 7px;
    border-radius: 999px;
    background: #ecfdf5;
    font-size: 10px;
    font-weight: 800;
    color: #047857;
}
.evs-hit__now::before {
    content: '';
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: #10b981;
}
/* Tanda ↵ hanya pada hasil tersorot: satu-satunya yang akan dibuka Enter. */
.evs-hit__go {
    flex: 0 0 auto;
    width: 26px;
    height: 24px;
    border: 1px solid rgba(99, 102, 241, 0.22);
    border-radius: 7px;
    display: grid;
    place-items: center;
    background: #fff;
    color: #6366f1;
    opacity: 0;
    transform: translateX(-4px);
    transition: opacity 0.14s, transform 0.14s;
}
.evs-hit.is-kursor .evs-hit__go {
    opacity: 1;
    transform: none;
}

/* Kata yang cocok — goresan stabilo di bawah huruf, bukan blok kuning
   bawaan <mark> yang menenggelamkan hurufnya. */
.evs-mark {
    padding: 0;
    border-radius: 2px;
    background: linear-gradient(180deg, transparent 58%, rgba(139, 92, 246, 0.26) 58%);
    font-weight: 800;
    color: #4f46e5;
}

/* ── Kosong ── */
.evs-kosong {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 30px 14px 18px;
    text-align: center;
}
.evs-kosong__ico {
    width: 60px;
    height: 60px;
    margin-bottom: 14px;
    border: 1px solid #e7e3fb;
    border-radius: 20px;
    display: grid;
    place-items: center;
    background: linear-gradient(135deg, #f4f2ff, #eef2ff);
    color: #6366f1;
    box-shadow: 0 12px 26px rgba(99, 102, 241, 0.14);
}
.evs-kosong__judul {
    font-size: 14px;
    font-weight: 800;
    color: #1e293b;
}
.evs-kosong__teks {
    margin: 5px 0 0;
    font-size: 12px;
    line-height: 1.5;
    color: #64748b;
    overflow-wrap: anywhere;
}
.evs-kosong__teks b {
    color: #4338ca;
}
.evs-kosong__catatan {
    margin: 8px 0 0;
    font-size: 11px;
    line-height: 1.45;
    color: #9aa3b8;
}
.evs-kosong__saran {
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    gap: 6px;
    margin-top: 16px;
}
.evs-kosong__coba {
    width: 100%;
    margin-bottom: 2px;
    font-size: 10px;
    font-weight: 800;
    letter-spacing: 0.16em;
    text-transform: uppercase;
    color: #aab2c5;
}
.evs-saran {
    appearance: none;
    cursor: pointer;
    padding: 5px 11px;
    border: 1px solid #e4e1fb;
    border-radius: 999px;
    background: #f5f4ff;
    font-family: inherit;
    font-size: 11.5px;
    font-weight: 700;
    color: #4f46e5;
    transition: background 0.14s, border-color 0.14s, transform 0.14s;
}
.evs-saran:hover {
    border-color: #d4d0fa;
    background: #ecebff;
    transform: translateY(-1px);
}

/* ── Contekan papan ketik ── */
.evs-kaki-cari {
    flex: 0 0 auto;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 14px;
    padding: 8px 12px;
    border-top: 1px solid #eef0f7;
    background: #fafbfe;
    font-size: 10.5px;
    font-weight: 600;
    color: #9aa3b8;
}
.evs-kaki-cari span {
    display: inline-flex;
    align-items: center;
    gap: 3px;
}
.evs-kaki-cari kbd {
    min-width: 18px;
    height: 18px;
    padding: 0 4px;
    border: 1px solid #e3e7f0;
    border-bottom-width: 2px;
    border-radius: 5px;
    display: inline-grid;
    place-items: center;
    background: #fff;
    font-family: inherit;
    font-size: 10px;
    font-weight: 800;
    line-height: 1;
    color: #64748b;
}

/* Footer */
.evs-sb__foot {
    flex: 0 0 auto;
    padding: 12px;
    border-top: 1px solid #eef0f7;
}

/* LENCANA VERSI — baris paling bawah sidebar.
   Titik hijau kecil menandai "aplikasi hidup"; angkanya monospace supaya
   deretan digit tidak bergoyang saat versinya berganti panjang. */
.evs-ver {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    margin-top: 9px;
    padding-top: 9px;
    border-top: 1px dashed #eef0f7;
}

.evs-ver__dot {
    width: 6px;
    height: 6px;
    border-radius: 999px;
    background: #34d399;
    box-shadow: 0 0 0 3px rgba(52, 211, 153, 0.16);
    flex: 0 0 auto;
}

.evs-ver__num {
    font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
    font-size: 10.5px;
    font-weight: 700;
    letter-spacing: 0.02em;
    color: #aab2c5;
    white-space: nowrap;
}
.evs-user {
    appearance: none;
    cursor: pointer;
    width: 100%;
    display: flex;
    align-items: center;
    gap: 11px;
    padding: 9px 11px;
    border-radius: 14px;
    border: 1px solid #eef0f7;
    background: #fff;
    transition: background 0.16s;
}
.evs-user:hover {
    background: #f8f9fc;
}
.evs-user__avatar {
    width: 40px;
    height: 40px;
    border-radius: 12px;
    background: linear-gradient(135deg, #8b5cf6, #6366f1);
    color: #fff;
    font-size: 14px;
    font-weight: 800;
    display: flex;
    align-items: center;
    justify-content: center;
    flex: 0 0 auto;
}
.evs-user__name {
    display: block;
    font-size: 13px;
    font-weight: 800;
    color: #1e293b;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.evs-user__role {
    display: block;
    font-size: 11px;
    color: #94a3b8;
}
.evs-user.is-open {
    background: #f4f5fb;
}
.evs-user__chev {
    transition: transform 0.24s;
}
.evs-user__chev.is-rot {
    transform: rotate(180deg);
}

@media (max-width: 991.98px) {
    .evs-rail {
        display: none;
    }
}

/* Layar sentuh: tanpa papan ketik fisik, contekan pintasan cuma teks mati.
   Kotak cari 16px supaya Safari iOS tidak memperbesar halaman saat fokus,
   dan baris hasil sedikit lebih tinggi supaya enak diketuk jari. */
@media (hover: none), (pointer: coarse) {
    .evs-cari__kbd,
    .evs-kaki-cari,
    .evs-hit__go {
        display: none;
    }
    .evs-cari__input {
        font-size: 16px;
    }
    .evs-hit {
        padding: 10px;
    }
}

/* Layar pendek (ponsel mendatar, laptop kecil ber-zoom): kepala sidebar
   kini ikut memuat kotak cari, jadi logo anak usaha yang mengalah supaya
   daftar menu tetap punya ruang. */
@media (max-height: 560px) {
    .evs-sb__subs {
        display: none;
    }
}

@media (prefers-reduced-motion: reduce) {
    .evs-hasil {
        animation: none;
    }
    .evs-cari,
    .evs-hit,
    .evs-hit__ico,
    .evs-hit__go,
    .evs-saran {
        transition: none;
    }
}
</style>
