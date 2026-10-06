<!-- WEB CAREER — SEMUA LOWONGAN: halaman khusus kumpulan SELURUH peluang
     (lowongan rekrutmen + program MT) dengan Desktop Floating Back to Top Button (.sl-desktop-totop "Ke Atas"),
     Mobile Hero Padding Clearance (6.2rem agar bebas dari tumpang tindih navbar),
     Premium Glassmorphic Team Group Header (.sl-group__btn-about "Profil & Budaya Tim"),
     Fixed Floating Saved Jobs Button (Bookmark Melayang + Real-time Badge),
     Zero-Offside Safe Toolbar Container (.sl-tabs overflow-x auto & flex-shrink 0),
     Fixed Mobile Filter Drawer (Vue <Teleport to="body">), Ultra-Compact Mobile Density,
     Floating Bottom Action Bar, Touch-Swipeable Keyword Chips, dan Compact Toolbar 36px. -->
<template>
    <Head title="Semua Lowongan" />

    <CareerLayout :has-mt="hasMt" :offices="offices">
        <div class="sl">
            <!-- ═══ HERO (Ultra-Compact Mobile Height ~120px with Safe Top Clearance) ═══ -->
            <header class="sl-hero">
                <div class="sl-hero__glow sl-hero__glow--a"></div>
                <div class="sl-hero__glow sl-hero__glow--b"></div>
                <div class="sl-hero__in">
                    <span class="wc-eyebrow"><span class="wc-dot"></span> Career Opportunities</span>
                    <h1>Jelajahi <span class="wc-grad">seluruh peluang</span> di EVO Group.</h1>
                    <p class="sl-hero__desc-desktop">{{ lowongan.length }} lowongan terbuka dan {{ programMt.length }} program Management Trainee menantimu. Temukan peran terbaikmu, lalu lamar langsung.</p>

                    <div class="sl-hero__stats">
                        <div class="sl-stat">
                            <span class="sl-stat__ico sl-stat__ico--indigo"><i class="bi bi-briefcase-fill"></i></span>
                            <span><b>{{ lowongan.length }}</b> Lowongan</span>
                        </div>
                        <div class="sl-stat">
                            <span class="sl-stat__ico sl-stat__ico--amber"><i class="bi bi-mortarboard-fill"></i></span>
                            <span><b>{{ programMt.length }}</b> Program MT</span>
                        </div>
                        <!-- Statistik ketiga TIDAK lagi "Kuota". Jumlah kursi
                             adalah angka perencanaan internal dan tidak lagi
                             dikirim backend; kota penempatan sama informatifnya
                             bagi pelamar dan memang terdata. -->
                        <div class="sl-stat">
                            <span class="sl-stat__ico sl-stat__ico--green"><i class="bi bi-geo-alt-fill"></i></span>
                            <span><b>{{ lokasiOptions.length }}</b> Kota</span>
                        </div>
                    </div>
                </div>
            </header>

            <!-- ═══ COMPACT MT HIGHLIGHT STRIP (1-Baris Ramping & Smooth Scroll Trigger) ═══ -->
            <div v-if="hasMt && (tab === 'semua' || tab === 'mt')" class="sl-mt-strip">
                <div class="sl-mt-strip__in">
                    <div class="sl-mt-strip__left">
                        <span class="sl-mt-strip__badge"><i class="bi bi-stars"></i> Highlight MT</span>
                        <span class="sl-mt-strip__txt">
                            Program <strong>Management Trainee (MT)</strong> EVO Group sedang dibuka — jalur percepatan karir & leadership.
                        </span>
                    </div>
                    <button type="button" class="sl-mt-strip__btn" @click="scrollToMt">
                        Lihat {{ programMt.length }} Program MT <i class="bi bi-arrow-down-circle-fill"></i>
                    </button>
                </div>
            </div>

            <!-- ═══ TOOLBAR 2-TIER CLEAN LAYOUT (Zero-Offside Safe) ═══ -->
            <div class="sl-toolbar-wrap">
                <div class="sl-toolbar">
                    <!-- TIER 1: Main Tabs & Search Bar -->
                    <div class="sl-toolbar__tier1">
                        <div class="sl-tabs">
                            <button type="button" class="sl-tab" :class="{ 'is-on': tab === 'semua' }" @click="tab = 'semua'">
                                <i class="bi bi-grid-fill"></i> <span class="sl-tab__lbl-desktop">Semua</span><span class="sl-tab__lbl-mobile">Semua</span> <span class="sl-tab__n">{{ lowongan.length + programMt.length }}</span>
                            </button>
                            <button type="button" class="sl-tab" :class="{ 'is-on': tab === 'rek' }" @click="tab = 'rek'">
                                <i class="bi bi-briefcase-fill"></i> <span class="sl-tab__lbl-desktop">Lowongan</span><span class="sl-tab__lbl-mobile">Lowongan</span> <span class="sl-tab__n">{{ lowongan.length }}</span>
                            </button>
                            <button type="button" class="sl-tab" :class="{ 'is-on': tab === 'mt' }" @click="tab = 'mt'">
                                <i class="bi bi-stars"></i> <span class="sl-tab__lbl-desktop">Management Trainee</span><span class="sl-tab__lbl-mobile">MT</span> <span class="sl-tab__n">{{ programMt.length }}</span>
                            </button>
                            <!-- Tab Bookmark Saved Jobs -->
                            <button type="button" class="sl-tab sl-tab--saved" :class="{ 'is-on': tab === 'saved' }" @click="tab = 'saved'">
                                <i class="bi bi-bookmark-fill"></i> <span class="sl-tab__lbl-desktop">Disimpan</span><span class="sl-tab__lbl-mobile">Simpan</span> <span class="sl-tab__n">{{ savedJobIds.length }}</span>
                            </button>
                        </div>

                        <!-- Search Bar -->
                        <div class="sl-search">
                            <i class="bi bi-search"></i>
                            <input v-model="search" type="text" placeholder="Cari posisi, skill, benefit..." />
                            <button v-if="search" type="button" class="sl-search__clear" @click="search = ''">
                                <i class="bi bi-x-lg"></i>
                            </button>
                        </div>
                    </div>

                    <!-- TIER 2: Suggestion Chips & Controls -->
                    <div class="sl-toolbar__tier2">
                        <!-- Tag Sugesti Kata Kunci Cepat (Touch-Swipeable Carousel) -->
                        <div class="sl-suggestions">
                            <span class="sl-suggestions__lbl"><i class="bi bi-lightning-charge-fill"></i> Sugesti:</span>
                            <button
                                v-for="sug in suggestions"
                                :key="sug"
                                type="button"
                                class="sl-sug-pill"
                                :class="{ 'is-active': search.toLowerCase() === sug.toLowerCase() }"
                                @click="toggleSearchTag(sug)"
                            >
                                {{ sug }}
                            </button>
                        </div>

                        <div class="sl-controls">
                            <!-- Sorting Dropdown -->
                            <div class="sl-sort">
                                <i class="bi bi-sort-down sl-sort__ico"></i>
                                <span class="sl-sort__lbl">Urutkan</span>
                                <select v-model="sortBy" class="sl-sort__select" aria-label="Urutkan lowongan">
                                    <option value="newest">Terbaru</option>
                                    <option value="deadline">Paling Cepat Ditutup</option>
                                    <option value="name">Nama Posisi (A-Z)</option>
                                </select>
                                <i class="bi bi-chevron-down sl-sort__chev"></i>
                            </div>

                            <!-- View Mode Switcher (Grid vs List) -->
                            <div class="sl-view-toggle">
                                <button
                                    type="button"
                                    class="sl-view-btn"
                                    :class="{ 'is-active': viewMode === 'grid' }"
                                    title="Tampilan Grid"
                                    @click="viewMode = 'grid'"
                                >
                                    <i class="bi bi-grid-3x3-gap-fill"></i>
                                </button>
                                <button
                                    type="button"
                                    class="sl-view-btn"
                                    :class="{ 'is-active': viewMode === 'list' }"
                                    title="Tampilan List"
                                    @click="viewMode = 'list'"
                                >
                                    <i class="bi bi-list-task"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ═══ LOWONGAN REKRUTMEN — sidebar filter divisi + daftar per tim ═══ -->
            <section v-if="tab !== 'mt'" class="sl-sec">
                <div class="sl-layout">
                    <!-- ── DESKTOP SIDEBAR FILTER (In-page) ── -->
                    <aside class="sl-aside sl-aside--desktop">
                        <div class="sl-aside__body">
                            <div class="sl-aside__head">
                                <span><i class="bi bi-funnel-fill"></i> Filter Multifaset</span>
                                <button v-if="jumlahFilterAktif" type="button" class="sl-aside__reset" @click="resetFilter">
                                    <i class="bi bi-arrow-counterclockwise"></i> Reset
                                </button>
                            </div>

                            <!-- 📂 KELOMPOK 1: TIM / DIVISI -->
                            <div class="sl-fgroup" :class="{ 'is-collapsed': collapsedGroups.divisi }">
                                <div class="sl-fgroup__head" @click="toggleGroup('divisi')">
                                    <span class="sl-fgroup__title">
                                        Tim / Divisi
                                        <b v-if="selDivisi.length" class="sl-fgroup__badge">{{ selDivisi.length }}</b>
                                    </span>
                                    <i class="bi bi-chevron-down sl-fgroup__chev"></i>
                                </div>

                                <div v-show="!collapsedGroups.divisi" class="sl-fgroup__list">
                                    <label v-for="d in divisiOptions" :key="d.slug" class="sl-check">
                                        <input v-model="selDivisi" type="checkbox" :value="d.slug" />
                                        <span class="sl-check__box"><i class="bi bi-check-lg"></i></span>
                                        <span class="sl-check__lbl">{{ d.nama }}</span>
                                        <span class="sl-check__n">{{ d.count }}</span>
                                    </label>
                                </div>
                            </div>

                            <!-- 📂 KELOMPOK 2: LOKASI -->
                            <div v-if="lokasiOptions.length > 1" class="sl-fgroup" :class="{ 'is-collapsed': collapsedGroups.lokasi }">
                                <div class="sl-fgroup__head" @click="toggleGroup('lokasi')">
                                    <span class="sl-fgroup__title">
                                        Lokasi Penempatan
                                        <b v-if="selLokasi.length" class="sl-fgroup__badge">{{ selLokasi.length }}</b>
                                    </span>
                                    <i class="bi bi-chevron-down sl-fgroup__chev"></i>
                                </div>

                                <div v-show="!collapsedGroups.lokasi" class="sl-fgroup__list">
                                    <label v-for="o in lokasiOptions" :key="o.nama" class="sl-check">
                                        <input v-model="selLokasi" type="checkbox" :value="o.nama" />
                                        <span class="sl-check__box"><i class="bi bi-check-lg"></i></span>
                                        <span class="sl-check__lbl">{{ o.nama }}</span>
                                        <span class="sl-check__n">{{ o.count }}</span>
                                    </label>
                                </div>
                            </div>

                            <!-- 📂 KELOMPOK 3: TIPE KERJA -->
                            <div v-if="tipeOptions.length > 1" class="sl-fgroup" :class="{ 'is-collapsed': collapsedGroups.tipe }">
                                <div class="sl-fgroup__head" @click="toggleGroup('tipe')">
                                    <span class="sl-fgroup__title">
                                        Tipe Kerja
                                        <b v-if="selTipe.length" class="sl-fgroup__badge">{{ selTipe.length }}</b>
                                    </span>
                                    <i class="bi bi-chevron-down sl-fgroup__chev"></i>
                                </div>

                                <div v-show="!collapsedGroups.tipe" class="sl-fgroup__list">
                                    <label v-for="o in tipeOptions" :key="o.nama" class="sl-check">
                                        <input v-model="selTipe" type="checkbox" :value="o.nama" />
                                        <span class="sl-check__box"><i class="bi bi-check-lg"></i></span>
                                        <span class="sl-check__lbl">{{ o.nama }}</span>
                                        <span class="sl-check__n">{{ o.count }}</span>
                                    </label>
                                </div>
                            </div>

                            <!-- 🔔 JOB ALERT WIDGET -->
                            <div class="sl-job-alert">
                                <div class="sl-job-alert__head">
                                    <i class="bi bi-bell-fill"></i>
                                    <span>Job Tips</span>
                                </div>
                                <p>Belum menemukan posisi yang cocok? Pantau Terus Portal EVO CAREER.</p>
                                <Link href="/karir/landing-page" class="sl-job-alert__btn">
                                   Kembali ke Beranda <i class="bi bi-arrow-right"></i>
                                </Link>
                            </div>
                        </div>
                    </aside>

                    <!-- ── DAFTAR POSISI PER TIM (Maksimal 6 posisi awal per tim saat tab Semua) ── -->
                    <div class="sl-main">
                        <!-- 📍 ACTIVE LOCATION FILTER BANNER -->
                        <div v-if="selLokasi.length" class="sl-loc-banner">
                            <div class="sl-loc-banner__in">
                                <div class="sl-loc-banner__icon">
                                    <i class="bi bi-geo-alt-fill"></i>
                                </div>
                                <div class="sl-loc-banner__info">
                                    <span class="sl-loc-banner__tag">Filter Penempatan Aktif</span>
                                    <h4 class="sl-loc-banner__title">
                                        Lowongan Penempatan: <strong>{{ selLokasi.join(', ') }}</strong>
                                    </h4>
                                    <span class="sl-loc-banner__sub">
                                        Menampilkan <strong>{{ filteredJobs.length }} posisi</strong> yang membuka penempatan di kota ini.
                                    </span>
                                </div>
                                <button type="button" class="sl-loc-banner__clear" @click="selLokasi = []">
                                    <i class="bi bi-x-lg"></i> Hapus Filter Lokasi
                                </button>
                            </div>
                        </div>

                        <div class="sl-sec__head">
                            <h2>
                                <i class="bi" :class="tab === 'saved' ? 'bi-bookmark-fill' : 'bi-briefcase-fill'"></i>
                                {{ tab === 'saved' ? 'Lowongan Disimpan' : 'Posisi Tersedia' }}
                            </h2>
                            <span class="sl-sec__count">{{ filteredJobs.length }} posisi · {{ groupedJobs.length }} tim</span>
                        </div>

                        <template v-if="groupedJobs.length">
                            <section v-for="g in groupedJobs" :key="g.slug || 'lainnya'" class="sl-group">
                                <!-- Premium Glassmorphic Team Group Header -->
                                <div class="sl-group__head">
                                    <div class="sl-group__title-wrap">
                                        <span class="sl-group__dot"></span>
                                        <h3>{{ g.nama }}</h3>
                                        <span class="sl-group__count">{{ g.jobs.length }} Posisi</span>
                                    </div>

                                    <Link v-if="g.slug" class="sl-group__btn-about" :href="`/karir/tim/${g.slug}`" :title="`Lihat profil & budaya tim ${g.nama}`">
                                        <i class="bi bi-building"></i>
                                        <span>Profil & Budaya Tim</span>
                                        <i class="bi bi-arrow-right"></i>
                                    </Link>
                                </div>

                                <!-- Render Kartu Lowongan (Max 6 jika belum di-expand) -->
                                <div class="rek__grid" :class="{ 'is-list-grid': viewMode === 'list' }">
                                    <LowonganCard
                                        v-for="job in (isTeamExpanded(g.slug) || g.jobs.length <= 6 ? g.jobs : g.jobs.slice(0, 6))"
                                        :key="job.id"
                                        :job="job"
                                        :is-saved="savedJobIds.includes(job.id)"
                                        :view-mode="viewMode"
                                        @toggle-save="toggleSaveJob"
                                    />
                                </div>

                                <!-- Tombol Expand jika Posisi > 6 -->
                                <div v-if="g.jobs.length > 6" class="sl-group__expand">
                                    <button type="button" class="sl-group__expand-btn" @click="toggleExpandTeam(g.slug)">
                                        <i class="bi" :class="isTeamExpanded(g.slug) ? 'bi-chevron-up' : 'bi-plus-circle-fill'"></i>
                                        {{ isTeamExpanded(g.slug) ? 'Sembunyikan' : `Tampilkan ${g.jobs.length - 6} Posisi ${g.nama} Lainnya (+${g.jobs.length - 6})` }}
                                    </button>
                                </div>
                            </section>
                        </template>

                        <div v-else class="sl-empty sl-empty--col">
                            <div class="sl-empty__ico"><i class="bi" :class="tab === 'saved' ? 'bi-bookmark-x' : 'bi-search'"></i></div>
                            <h3>{{ tab === 'saved' ? 'Belum Ada Lowongan Disimpan' : 'Tidak Ada Lowongan Ditemukan' }}</h3>
                            <p>{{ tab === 'saved' ? 'Klik ikon bookmark pada kartu lowongan untuk menyimpannya di sini.' : 'Maaf, tidak ada posisi yang cocok dengan kata kunci atau filter yang Anda pilih saat ini.' }}</p>
                            <button type="button" class="sl-empty__btn" @click="resetSemua">
                                <i class="bi bi-arrow-counterclockwise"></i> Tampilkan Semua Lowongan
                            </button>
                        </div>
                    </div>
                </div>
            </section>

            <!-- ═══ PROGRAM MT SECTION (FULL LIGHT) ═══ -->
            <section v-if="tab !== 'rek' && tab !== 'saved'" id="mt" class="sl-mt">
                <div class="sl-mt__wrap">
                    <div class="sl-sec__head">
                        <h2><i class="bi bi-gem"></i> Program Management Trainee</h2>
                        <span class="sl-sec__count sl-sec__count--gold">{{ filteredMt.length }} program</span>
                    </div>
                    <div v-if="filteredMt.length" class="sl-mt__grid">
                        <Link
                            v-for="(mt, i) in pagedMt"
                            :key="mt.id"
                            class="mtl__card"
                            :style="{ '--d': i * 60 + 'ms' }"
                            :href="mtUrl(mt.id)"
                        >
                            <div class="mtl__cardtop">
                                <span class="mtl__batch"><i class="bi bi-stars"></i> {{ mt.batch || 'Management Trainee' }}</span>
                                <span class="mtl__status" :class="statusClass(mt.status)">{{ statusLabel(mt.status) }}</span>
                            </div>
                            <h3 class="mtl__title">{{ mt.nama }}</h3>
                            <div class="mtl__tag">{{ mt.tagline || 'Program Management Trainee EVO Group.' }}</div>
                            <p class="mtl__desc">{{ mt.ringkasan }}</p>

                            <!-- Isi program: posisi apa saja yang dibuka. Chip yang
                                 cocok dengan kata pencarian dinaikkan ke depan &
                                 disorot, supaya jelas MENGAPA program ini muncul. -->
                            <div v-if="(mt.posisi || []).length" class="mtl__posisi">
                                <span class="mtl__posisi-lbl"><i class="bi bi-diagram-3"></i> {{ mt.jumlahPosisi }} posisi</span>
                                <span
                                    v-for="p in chipPosisi(mt)"
                                    :key="p.id"
                                    class="mtl__chip"
                                    :class="{ 'is-hit': p.cocok }"
                                >{{ p.posisi }}</span>
                                <span v-if="mt.jumlahPosisi > 3" class="mtl__chip mtl__chip--more">+{{ mt.jumlahPosisi - 3 }}</span>
                            </div>

                            <!-- Hanya fakta terdata. "Jenis kegiatan" & "durasi
                                 program" dihapus — dulu teks mati yang sama untuk
                                 setiap program MT, bukan data. -->
                            <div class="mtl__meta">
                                <span v-if="mt.penempatan"><i class="bi bi-geo-alt"></i> {{ mt.penempatan }}</span>
                                <span><i class="bi bi-person-lines-fill"></i> {{ mt.pelamar }} pelamar</span>
                            </div>
                            <!-- Bar "kuota terisi" DIHAPUS bersama datanya. -->
                            <div class="mtl__foot">
                                <span class="mtl__deadline" :class="{ soon: daysLeft(mt.tanggalTutup) <= 7 }">
                                    <i class="bi bi-calendar-event"></i>
                                    Ditutup {{ formatDate(mt.tanggalTutup) }}
                                </span>
                                <span class="mtl__cta">
                                    {{ mt.jumlahPosisi ? `Lihat ${mt.jumlahPosisi} posisi` : 'Pelajari' }}
                                    <i class="bi bi-arrow-right"></i>
                                </span>
                            </div>
                        </Link>
                    </div>
                    <div v-else class="sl-empty sl-empty--col">
                        <i class="bi bi-clipboard-x"></i>
                        <span>Tidak ada program MT yang cocok dengan pencarianmu.</span>
                    </div>

                    <!-- Paginasi MT (10 per halaman) -->
                    <div v-if="mtTotalPages > 1" class="sl-pager">
                        <button type="button" class="sl-pager__nav" :disabled="mtPage <= 1" :onClick="mtPage <= 1 ? null : () => goMtPage(mtPage - 1)">
                            <i class="bi bi-chevron-left"></i>
                        </button>
                        <button v-for="p in mtTotalPages" :key="p" type="button" class="sl-pager__num" :class="{ 'is-on': p === mtPage }" @click="goMtPage(p)">
                            {{ p }}
                        </button>
                        <button type="button" class="sl-pager__nav" :disabled="mtPage >= mtTotalPages" @click="goMtPage(mtPage + 1)">
                            <i class="bi bi-chevron-right"></i>
                        </button>
                    </div>
                </div>
            </section>

            <!-- ═══ DESKTOP FLOATING BACK TO TOP BUTTON ═══ -->
            <button
                v-if="showBackToTop"
                type="button"
                class="sl-desktop-totop"
                title="Kembali ke Atas"
                @click="scrollToTop"
            >
                <i class="bi bi-chevron-up"></i>
                <span>Ke Atas</span>
            </button>

            <!-- ═══ MOBILE FLOATING BOTTOM FILTER & SAVED JOBS & BACK TO TOP BAR ═══ -->
            <div class="sl-mobile-fab-wrap">
                <!-- Filter Button -->
                <button type="button" class="sl-mobile-fab-filter" @click="filterOpen = true">
                    <i class="bi bi-funnel-fill"></i> Filter & Cari
                    <span v-if="jumlahFilterAktif" class="sl-mobile-fab-badge">{{ jumlahFilterAktif }}</span>
                </button>

                <!-- Fixed Floating Saved Jobs Button -->
                <button
                    type="button"
                    class="sl-mobile-fab-saved"
                    :class="{ 'is-active': tab === 'saved' }"
                    :title="tab === 'saved' ? 'Tampilkan Semua Lowongan' : 'Lihat Lowongan Disimpan'"
                    @click="tab = tab === 'saved' ? 'semua' : 'saved'"
                >
                    <i class="bi" :class="tab === 'saved' ? 'bi-bookmark-fill' : 'bi-bookmark-star-fill'"></i>
                    <span v-if="savedJobIds.length" class="sl-mobile-fab-saved-badge">{{ savedJobIds.length }}</span>
                </button>

                <!-- Back to Top Button -->
                <button v-if="showBackToTop" type="button" class="sl-mobile-fab-top" title="Kembali ke Atas" @click="scrollToTop">
                    <i class="bi bi-arrow-up-short"></i>
                </button>
            </div>

            <!-- ═══ TELEPORTED MOBILE FILTER DRAWER (100% Fixed & High Z-Index) ═══ -->
            <Teleport to="body">
                <div v-if="filterOpen" class="sl-mobile-backdrop" @click="filterOpen = false"></div>
                <div v-if="filterOpen" class="sl-mobile-drawer">
                    <div class="sl-mobile-drawer__handle"></div>
                    <div class="sl-mobile-drawer__head">
                        <span><i class="bi bi-funnel-fill"></i> Filter Multifaset</span>
                        <div class="sl-mobile-drawer__actions">
                            <button v-if="jumlahFilterAktif" type="button" class="sl-aside__reset" @click="resetFilter">
                                <i class="bi bi-arrow-counterclockwise"></i> Reset
                            </button>
                            <button type="button" class="sl-mobile-drawer__close" @click="filterOpen = false">
                                <i class="bi bi-x-lg"></i>
                            </button>
                        </div>
                    </div>

                    <div class="sl-mobile-drawer__content">
                        <!-- 📂 KELOMPOK 1: TIM / DIVISI -->
                        <div class="sl-fgroup" :class="{ 'is-collapsed': collapsedGroups.divisi }">
                            <div class="sl-fgroup__head" @click="toggleGroup('divisi')">
                                <span class="sl-fgroup__title">
                                    Tim / Divisi
                                    <b v-if="selDivisi.length" class="sl-fgroup__badge">{{ selDivisi.length }}</b>
                                </span>
                                <i class="bi bi-chevron-down sl-fgroup__chev"></i>
                            </div>

                            <div v-show="!collapsedGroups.divisi" class="sl-fgroup__list">
                                <label v-for="d in divisiOptions" :key="d.slug" class="sl-check">
                                    <input v-model="selDivisi" type="checkbox" :value="d.slug" />
                                    <span class="sl-check__box"><i class="bi bi-check-lg"></i></span>
                                    <span class="sl-check__lbl">{{ d.nama }}</span>
                                    <span class="sl-check__n">{{ d.count }}</span>
                                </label>
                            </div>
                        </div>

                        <!-- 📂 KELOMPOK 2: LOKASI -->
                        <div v-if="lokasiOptions.length > 1" class="sl-fgroup" :class="{ 'is-collapsed': collapsedGroups.lokasi }">
                            <div class="sl-fgroup__head" @click="toggleGroup('lokasi')">
                                <span class="sl-fgroup__title">
                                    Lokasi Penempatan
                                    <b v-if="selLokasi.length" class="sl-fgroup__badge">{{ selLokasi.length }}</b>
                                </span>
                                <i class="bi bi-chevron-down sl-fgroup__chev"></i>
                            </div>

                            <div v-show="!collapsedGroups.lokasi" class="sl-fgroup__list">
                                <label v-for="o in lokasiOptions" :key="o.nama" class="sl-check">
                                    <input v-model="selLokasi" type="checkbox" :value="o.nama" />
                                    <span class="sl-check__box"><i class="bi bi-check-lg"></i></span>
                                    <span class="sl-check__lbl">{{ o.nama }}</span>
                                    <span class="sl-check__n">{{ o.count }}</span>
                                </label>
                            </div>
                        </div>

                        <!-- 📂 KELOMPOK 3: TIPE KERJA -->
                        <div v-if="tipeOptions.length > 1" class="sl-fgroup" :class="{ 'is-collapsed': collapsedGroups.tipe }">
                            <div class="sl-fgroup__head" @click="toggleGroup('tipe')">
                                <span class="sl-fgroup__title">
                                    Tipe Kerja
                                    <b v-if="selTipe.length" class="sl-fgroup__badge">{{ selTipe.length }}</b>
                                </span>
                                <i class="bi bi-chevron-down sl-fgroup__chev"></i>
                            </div>

                            <div v-show="!collapsedGroups.tipe" class="sl-fgroup__list">
                                <label v-for="o in tipeOptions" :key="o.nama" class="sl-check">
                                    <input v-model="selTipe" type="checkbox" :value="o.nama" />
                                    <span class="sl-check__box"><i class="bi bi-check-lg"></i></span>
                                    <span class="sl-check__lbl">{{ o.nama }}</span>
                                    <span class="sl-check__n">{{ o.count }}</span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <button type="button" class="sl-mobile-drawer__apply" @click="filterOpen = false">
                        Terapkan Filter ({{ filteredJobs.length }} Posisi)
                    </button>
                </div>
            </Teleport>

            <!-- ═══ CTA KEMBALI ═══ -->
            <div class="sl-back">
                <Link href="/karir/landing-page" class="sl-back__btn">
                    <i class="bi bi-arrow-left"></i> Kembali ke Beranda Karir
                </Link>
            </div>
        </div>
    </CareerLayout>
</template>

<script setup>
import { computed, onMounted, onUnmounted, reactive, ref, watch } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import CareerLayout from './Layouts/CareerLayout.vue';
import LowonganCard from './components/LowonganCard.vue';
import { daysLeft, formatDate, mtUrl, statusClass, statusLabel } from '@utils/career/data';

defineOptions({ layout: null });

const page = usePage();

const props = defineProps({
    lowongan: { type: Array, default: () => [] },
    programMt: { type: Array, default: () => [] },
    offices: { type: Array, default: () => [] },
    tim: { type: Array, default: () => [] },
});

const search = ref('');
const sortBy = ref('newest');
const viewMode = ref('grid');

/** Waktu pembukaan lowongan (epoch ms); 0 bila tanggalnya kosong/tak valid. */
function waktuDibuka(job) {
    const t = Date.parse(job?.dibuka || '');
    return Number.isNaN(t) ? 0 : t;
}
const showBackToTop = ref(false);

function handleScroll() {
    showBackToTop.value = window.scrollY > 300;
}

function scrollToTop() {
    try { window.scrollTo({ top: 0, behavior: 'smooth' }); } catch { /* noop */ }
}

onMounted(() => {
    window.addEventListener('scroll', handleScroll, { passive: true });
});

onUnmounted(() => {
    window.removeEventListener('scroll', handleScroll);
});

// Accordion Collapsible Groups state
const collapsedGroups = reactive({ divisi: false, lokasi: false, tipe: false });

function toggleGroup(key) {
    collapsedGroups[key] = !collapsedGroups[key];
}

// Team jobs expansion state (max 6 positions initially per team)
const expandedTeams = ref({});

function isTeamExpanded(slug) {
    return !!expandedTeams.value[slug];
}

function toggleExpandTeam(slug) {
    expandedTeams.value[slug] = !expandedTeams.value[slug];
}

function scrollToMt() {
    try {
        const el = document.getElementById('mt');
        if (el) {
            el.scrollIntoView({ behavior: 'smooth' });
        }
    } catch { /* noop */ }
}

const savedJobIds = ref([]);

function loadSavedJobs() {
    try {
        const stored = localStorage.getItem('evo_saved_jobs');
        return stored ? JSON.parse(stored) : [];
    } catch {
        return [];
    }
}

function toggleSaveJob(jobId) {
    const idx = savedJobIds.value.indexOf(jobId);
    if (idx > -1) {
        savedJobIds.value.splice(idx, 1);
    } else {
        savedJobIds.value.push(jobId);
    }
    try {
        localStorage.setItem('evo_saved_jobs', JSON.stringify(savedJobIds.value));
    } catch { /* noop */ }
}

onMounted(() => {
    savedJobIds.value = loadSavedJobs();
});

const suggestions = ['IT & Digital', 'QC', 'Staff Admin', 'Palembang', 'Banyuasin', 'Full-time'];

function getSearchParam(key) {
    try {
        const rawUrl = page.url || (typeof window !== 'undefined' ? window.location.search : '');
        const queryStr = rawUrl.includes('?') ? rawUrl.substring(rawUrl.indexOf('?')) : rawUrl;
        return new URLSearchParams(queryStr).get(key) || '';
    } catch {
        return '';
    }
}

const initTab = (() => {
    const t = getSearchParam('tab');
    return ['semua', 'rek', 'mt', 'saved'].includes(t) ? t : 'semua';
})();
const tab = ref(initTab);

const initQuery = getSearchParam('q');
if (initQuery) {
    search.value = initQuery;
}

const initDivisi = getSearchParam('tim').split(',').filter(Boolean);
const initLokasi = getSearchParam('lokasi').split(',').filter(Boolean);
const selDivisi = ref(initDivisi);
const selLokasi = ref(initLokasi);
const selTipe = ref([]);
const filterOpen = ref(false);

watch(
    () => page.url,
    () => {
        const queryLok = getSearchParam('lokasi');
        const listLok = queryLok ? queryLok.split(',').filter(Boolean) : [];
        if (JSON.stringify(listLok) !== JSON.stringify(selLokasi.value)) {
            selLokasi.value = listLok;
        }

        const queryTim = getSearchParam('tim');
        const listTim = queryTim ? queryTim.split(',').filter(Boolean) : [];
        if (JSON.stringify(listTim) !== JSON.stringify(selDivisi.value)) {
            selDivisi.value = listTim;
        }

        const queryQ = getSearchParam('q');
        if (queryQ !== search.value) {
            search.value = queryQ;
        }
    },
    { immediate: true }
);

const jumlahFilterAktif = computed(() => selDivisi.value.length + selLokasi.value.length + selTipe.value.length);
function resetFilter() { selDivisi.value = []; selLokasi.value = []; selTipe.value = []; }
function resetSemua() { resetFilter(); search.value = ''; if (tab.value === 'saved') tab.value = 'semua'; }

watch(selDivisi, (v) => {
    try {
        const url = new URL(window.location.href);
        if (v.length) url.searchParams.set('tim', v.join(','));
        else url.searchParams.delete('tim');
        window.history.replaceState({}, '', url);
    } catch { /* noop */ }
});

watch(selLokasi, (v) => {
    try {
        const url = new URL(window.location.href);
        if (v.length) url.searchParams.set('lokasi', v.join(','));
        else url.searchParams.delete('lokasi');
        window.history.replaceState({}, '', url);
    } catch { /* noop */ }
});

const divisiOptions = computed(() => {
    const opsi = props.tim.filter((t) => t.lowongan > 0).map((t) => ({ slug: t.slug, nama: t.nama, count: t.lowongan }));
    const tanpaDivisi = props.lowongan.filter((j) => !j.timSlug).length;
    if (tanpaDivisi) opsi.push({ slug: '', nama: 'Tim Lainnya', count: tanpaDivisi });
    return opsi;
});

const hitungOpsi = (ambil) => {
    const peta = new Map();
    for (const j of props.lowongan) {
        const v = ambil(j);
        if (!v || v === '—') continue;
        peta.set(v, (peta.get(v) || 0) + 1);
    }
    return [...peta.entries()].map(([nama, count]) => ({ nama, count })).sort((a, b) => b.count - a.count);
};
const lokasiKota = (job) => {
    const teks = String(job?.lokasi || '').toLowerCase();
    return props.offices.find((office) => teks.includes(String(office.kota || '').toLowerCase()))?.kota || job?.lokasi;
};
const lokasiOptions = computed(() => {
    if (!props.offices.length) return [];
    return props.offices
        .map((office) => ({
            nama: office.kota,
            // props.lowongan hanya berisi posisi dari pembukaan aktif, sehingga
            // angka ini tidak pernah menghitung MPP bebas yang belum dibuka.
            count: props.lowongan.filter((job) => lokasiKota(job) === office.kota).length,
        }))
        .filter((office) => office.nama);
});
const tipeOptions = computed(() => hitungOpsi((j) => j.tipeKerja));

const PER_PAGE = 10;
const mtPage = ref(1);
watch([search, tab], () => { mtPage.value = 1; });

const hasMt = computed(() => props.programMt.length > 0);

const filteredJobs = computed(() => {
    const q = search.value.trim().toLowerCase();
    let jobs = props.lowongan.filter((job) => {
        if (tab.value === 'saved' && !savedJobIds.value.includes(job.id)) return false;
        if (selDivisi.value.length && !selDivisi.value.includes(job.timSlug || '')) return false;
        if (selLokasi.value.length && !selLokasi.value.includes(lokasiKota(job))) return false;
        if (selTipe.value.length && !selTipe.value.includes(job.tipeKerja)) return false;
        if (!q) return true;
        return [job.posisi, job.lokasi, job.ringkasan, job.departemen, ...(job.skill || []), ...(job.benefit || [])].join(' ').toLowerCase().includes(q);
    });

    return jobs.sort((a, b) => {
        // Dulu "Kuota Hampir Penuh" (sisa kursi paling sedikit). Kuota tidak
        // lagi dikirim, jadi urgensi diukur dari yang paling dekat ditutup.
        // EVERGREEN (tanpa tanggal tutup) tidak urgen -> dibuang ke belakang.
        if (sortBy.value === 'deadline') {
            const tutup = (x) => (x.tanggalTutup ? new Date(x.tanggalTutup).getTime() : Infinity);
            return tutup(a) - tutup(b);
        }
        if (sortBy.value === 'name') return (a.posisi || '').localeCompare(b.posisi || '');
        // Terbaru = tanggal pembukaan lowongan (bukan `id`, yang berupa string
        // "PB-<kode>-<n>" sehingga pengurangannya menghasilkan NaN).
        // Posisi dari pembukaan yang sama diurut alfabetis agar stabil.
        const selisih = waktuDibuka(b) - waktuDibuka(a);
        return selisih !== 0 ? selisih : (a.posisi || '').localeCompare(b.posisi || '');
    });
});

const groupedJobs = computed(() => {
    const peta = new Map();
    for (const j of filteredJobs.value) {
        const k = j.timSlug || '';
        if (!peta.has(k)) peta.set(k, []);
        peta.get(k).push(j);
    }
    return divisiOptions.value
        .filter((d) => peta.has(d.slug))
        .map((d) => ({ slug: d.slug, nama: d.nama, jobs: peta.get(d.slug) }));
});

/** Teks satu posisi yang ikut dicari (nama, unit, lokasi, level, skill). */
const teksPosisi = (p) => [p.posisi, p.departemen, p.lokasi, p.level, ...(p.skill || [])].join(' ').toLowerCase();

/**
 * Program MT ikut tersaring lewat POSISI di dalamnya, bukan hanya nama program.
 * Mengetik "ui/ux" atau "talent" harus memunculkan program yang memuat posisi
 * itu — kalau tidak, posisi MT jadi tak terjangkau pencarian sama sekali.
 */
const filteredMt = computed(() => {
    const q = search.value.trim().toLowerCase();
    if (!q) return props.programMt;
    return props.programMt.filter((mt) => {
        if ([mt.nama, mt.tagline, mt.penempatan, mt.ringkasan].join(' ').toLowerCase().includes(q)) return true;
        return (mt.posisi || []).some((p) => teksPosisi(p).includes(q));
    });
});

/** 3 chip posisi untuk kartu program — yang cocok pencarian didahulukan. */
function chipPosisi(mt) {
    const q = search.value.trim().toLowerCase();
    const daftar = (mt.posisi || []).map((p) => ({
        id: p.id,
        posisi: p.posisi,
        cocok: !!q && teksPosisi(p).includes(q),
    }));
    if (q) daftar.sort((a, b) => Number(b.cocok) - Number(a.cocok));
    return daftar.slice(0, 3);
}

const mtTotalPages = computed(() => Math.max(1, Math.ceil(filteredMt.value.length / PER_PAGE)));
const pagedMt = computed(() => filteredMt.value.slice((mtPage.value - 1) * PER_PAGE, mtPage.value * PER_PAGE));
function goMtPage(p) { if (p >= 1 && p <= mtTotalPages.value) { mtPage.value = p; scrollTop(); } }
</script>

<style scoped>
/* Icon Vertical Alignment Helper */
.bi {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    line-height: 1;
    vertical-align: middle;
}

.sl { min-height: 100vh; background: linear-gradient(180deg, #f3f2fd 0%, #eef1fb 46%, #eaf0fb 100%); }

/* ═══ HERO ═══ */
.sl-hero { position: relative; overflow: hidden; padding: 6.5rem 1.5rem 2.4rem; }
.sl-hero__glow { position: absolute; border-radius: 50%; filter: blur(10px); pointer-events: none; }
.sl-hero__glow--a { top: 10px; right: 10%; width: 360px; height: 360px; background: radial-gradient(circle at 30% 30%, rgba(139, 92, 246, 0.16), rgba(139, 92, 246, 0) 70%); }
.sl-hero__glow--b { bottom: 10px; left: 5%; width: 360px; height: 360px; background: radial-gradient(circle at 60% 40%, rgba(99, 102, 241, 0.12), rgba(99, 102, 241, 0) 70%); }
.sl-hero__in { position: relative; max-width: 1200px; margin: 0 auto; text-align: center; }
.sl-hero__in h1 { margin: 14px 0 0; font-size: clamp(1.9rem, 4vw, 2.9rem); font-weight: 800; color: #0f172a; letter-spacing: -0.03em; }
.sl-hero__in p { margin: 12px auto 0; font-size: 15px; color: #64748b; max-width: 620px; line-height: 1.65; }
.sl-hero__stats { display: flex; justify-content: center; gap: 14px; flex-wrap: wrap; margin-top: 26px; }
.sl-stat { display: inline-flex; align-items: center; gap: 10px; padding: 10px 18px; border-radius: 15px; background: rgba(255, 255, 255, 0.88); border: 1px solid rgba(226, 232, 240, 0.9); font-size: 13px; font-weight: 600; color: #64748b; box-shadow: 0 6px 18px rgba(15, 23, 42, 0.05); }
.sl-stat b { color: #0f172a; font-weight: 800; }
.sl-stat__ico { width: 34px; height: 34px; border-radius: 10px; display: inline-flex; align-items: center; justify-content: center; font-size: 15px; }
.sl-stat__ico--indigo { background: rgba(99, 102, 241, 0.12); color: #6366f1; }
.sl-stat__ico--amber { background: rgba(245, 158, 11, 0.14); color: #f59e0b; }
.sl-stat__ico--green { background: rgba(16, 185, 129, 0.12); color: #10b981; }

/* ═══ COMPACT MT HIGHLIGHT STRIP ═══ */
.sl-mt-strip {
    max-width: 1200px;
    margin: 0 auto 1.25rem;
    padding: 0 1.5rem;
}
.sl-mt-strip__in {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding: 0.75rem 1.35rem;
    border-radius: 1rem;
    background: linear-gradient(135deg, #1e1b4b 0%, #312e81 100%);
    border: 1px solid rgba(245, 158, 11, 0.4);
    box-shadow: 0 10px 28px rgba(30, 27, 75, 0.2);
    flex-wrap: wrap;
}
.sl-mt-strip__left {
    display: flex;
    align-items: center;
    gap: 0.85rem;
    flex-wrap: wrap;
}
.sl-mt-strip__badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 4px 11px;
    border-radius: 999px;
    background: rgba(245, 158, 11, 0.22);
    border: 1px solid rgba(245, 158, 11, 0.4);
    color: #fbbf24;
    font-size: 0.7rem;
    font-weight: 800;
    white-space: nowrap;
}
.sl-mt-strip__txt {
    color: #e0e7ff;
    font-size: 0.84rem;
    font-weight: 500;
}
.sl-mt-strip__txt strong {
    color: #ffffff;
    font-weight: 800;
}
.sl-mt-strip__btn {
    appearance: none;
    cursor: pointer;
    font-family: inherit;
    font-size: 0.76rem;
    font-weight: 800;
    padding: 6px 15px;
    border-radius: 999px;
    border: none;
    background: linear-gradient(135deg, #fef08a, #f59e0b);
    color: #1e1b4b;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    box-shadow: 0 6px 16px rgba(245, 158, 11, 0.35);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    white-space: nowrap;
}
.sl-mt-strip__btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 22px rgba(245, 158, 11, 0.45);
}

/* ═══ TOOLBAR 2-TIER CLEAN LAYOUT (Zero-Offside Safe) ═══ */
.sl-toolbar-wrap { position: sticky; top: 64px; z-index: 20; max-width: 1200px; margin: 0 auto; padding: 8px 1.5rem 6px; }
.sl-toolbar {
    padding: 12px 16px;
    border-radius: 20px;
    background: rgba(255, 255, 255, 0.9);
    border: 1px solid rgba(226, 232, 240, 0.9);
    backdrop-filter: blur(16px);
    -webkit-backdrop-filter: blur(16px);
    box-shadow: 0 10px 30px rgba(15, 23, 42, 0.06);
    display: flex;
    flex-direction: column;
    gap: 12px;
    max-width: 100%;
    box-sizing: border-box;
}

.sl-toolbar__tier1 {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 14px;
    flex-wrap: wrap;
    max-width: 100%;
}

.sl-tabs {
    display: flex;
    align-items: center;
    gap: 8px;
    overflow-x: auto;
    flex-wrap: nowrap;
    scrollbar-width: none;
    -webkit-overflow-scrolling: touch;
    max-width: 100%;
    padding-bottom: 2px;
}
.sl-tabs::-webkit-scrollbar { display: none; }

.sl-tab {
    appearance: none;
    cursor: pointer;
    font-family: inherit;
    font-size: 13px;
    font-weight: 700;
    padding: 9px 15px;
    border-radius: 12px;
    border: 1px solid #e6e9f3;
    background: rgba(255, 255, 255, 0.9);
    color: #64748b;
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
    display: inline-flex;
    align-items: center;
    gap: 8px;
    flex-shrink: 0;
    white-space: nowrap;
}
.sl-tab:hover:not(.is-on) { border-color: rgba(139, 92, 246, 0.3); color: #6366f1; background: #ffffff; }
.sl-tab.is-on { border-color: transparent; background: linear-gradient(135deg, #8b5cf6, #6366f1); color: #fff; box-shadow: 0 10px 24px rgba(99, 102, 241, 0.28); }
.sl-tab__n { font-size: 11px; font-weight: 800; padding: 2px 8px; border-radius: 8px; background: #eef0f7; color: #94a3b8; }
.sl-tab.is-on .sl-tab__n { background: rgba(255, 255, 255, 0.24); color: #fff; }

.sl-tab__lbl-desktop { display: inline; }
.sl-tab__lbl-mobile { display: none; }

.sl-search { position: relative; display: flex; align-items: center; flex: 1; min-width: 240px; max-width: 380px; }
.sl-search .bi-search { position: absolute; left: 15px; color: #94a3b8; font-size: 14px; }
.sl-search input { width: 100%; padding: 10px 36px 10px 40px; border-radius: 12px; border: 1px solid #e6e9f3; background: rgba(255, 255, 255, 0.95); font-family: inherit; font-size: 13px; color: #334155; outline: none; transition: all 0.18s; }
.sl-search input:focus { border-color: #a5b4fc; background: #fff; box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.14); }
.sl-search__clear { position: absolute; right: 10px; appearance: none; border: none; background: #eef0f7; width: 22px; height: 22px; border-radius: 7px; color: #64748b; cursor: pointer; font-size: 10px; display: inline-flex; align-items: center; justify-content: center; }

.sl-toolbar__tier2 { display: flex; align-items: center; justify-content: space-between; gap: 14px; flex-wrap: wrap; padding-top: 10px; border-top: 1px dashed rgba(226, 232, 240, 0.9); }

/* Touch-Swipeable Keyword Chips Carousel */
.sl-suggestions {
    display: flex;
    align-items: center;
    gap: 8px;
    overflow-x: auto;
    flex-wrap: nowrap;
    scrollbar-width: none;
    -webkit-overflow-scrolling: touch;
    padding-bottom: 2px;
    max-width: 100%;
}
.sl-suggestions::-webkit-scrollbar { display: none; }
.sl-suggestions__lbl { font-size: 11.5px; font-weight: 800; color: #64748b; display: inline-flex; align-items: center; gap: 4px; flex-shrink: 0; }
.sl-suggestions__lbl i { color: #f59e0b; }
.sl-sug-pill { appearance: none; cursor: pointer; font-family: inherit; font-size: 11.5px; font-weight: 700; padding: 4px 12px; border-radius: 999px; background: rgba(255, 255, 255, 0.8); border: 1px solid rgba(226, 232, 240, 0.9); color: #64748b; transition: all 0.18s ease; flex-shrink: 0; white-space: nowrap; }
.sl-sug-pill:hover { background: #ffffff; border-color: rgba(139, 92, 246, 0.4); color: #6366f1; transform: translateY(-1px); }
.sl-sug-pill.is-active { background: #6366f1; border-color: transparent; color: #ffffff; }

.sl-controls { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
/* Dropdown urutkan — panah bawaan dimatikan (appearance:none), jadi ikon
   sortir, label, dan chevron digambar sendiri sebagai lapisan di atas select. */
.sl-sort { position: relative; display: inline-flex; align-items: center; }
.sl-sort__ico { position: absolute; left: 11px; color: #6366f1; font-size: 13px; pointer-events: none; z-index: 1; }
.sl-sort__lbl { position: absolute; left: 30px; font-size: 12px; font-weight: 700; color: #94a3b8; pointer-events: none; z-index: 1; }
.sl-sort__lbl::after { content: ':'; }
.sl-sort__chev { position: absolute; right: 11px; color: #94a3b8; font-size: 10px; pointer-events: none; z-index: 1; transition: color 0.18s ease; }
.sl-sort__select { appearance: none; cursor: pointer; font-family: inherit; font-size: 12px; font-weight: 700; color: #334155; padding: 8px 30px 8px 90px; border-radius: 10px; border: 1px solid #e6e9f3; background: #ffffff; outline: none; transition: border-color 0.18s ease, box-shadow 0.18s ease, background 0.18s ease; }
.sl-sort:hover .sl-sort__select { border-color: #a5b4fc; background: #fbfcff; }
.sl-sort:hover .sl-sort__chev { color: #6366f1; }
.sl-sort__select:focus-visible { border-color: #818cf8; box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.16); }
/* Daftar opsi memakai gaya bawaan sistem — pastikan tetap terbaca. */
.sl-sort__select option { color: #334155; font-weight: 600; }

.sl-view-toggle { display: inline-flex; padding: 3px; border-radius: 10px; background: #f1f5f9; border: 1px solid #e2e8f0; }
.sl-view-btn { appearance: none; border: none; background: none; cursor: pointer; padding: 5px 9px; border-radius: 7px; color: #94a3b8; font-size: 13px; transition: all 0.18s ease; display: inline-flex; align-items: center; justify-content: center; }
.sl-view-btn.is-active { background: #ffffff; color: #6366f1; box-shadow: 0 4px 10px rgba(15, 23, 42, 0.08); }

/* ═══ SECTIONS ═══ */
.sl-sec { max-width: 1200px; margin: 0 auto; padding: 1.6rem 1.5rem 2.4rem; position: relative; }
.sl-sec__head { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 1.4rem; }
.sl-sec__head h2 { margin: 0; font-size: 20px; font-weight: 800; color: #0f172a; letter-spacing: -0.02em; display: flex; align-items: center; gap: 10px; }
.sl-sec__head h2 .bi { color: #6366f1; }
.sl-sec__count { font-size: 12px; font-weight: 800; color: #6366f1; background: rgba(99, 102, 241, 0.1); border-radius: 999px; padding: 6px 14px; }
.sl-sec__count--gold { color: #b45309; background: rgba(245, 158, 11, 0.14); }

/* Empty State */
.sl-empty { padding: 3.5rem 1rem; text-align: center; color: #64748b; font-size: 14px; display: flex; align-items: center; justify-content: center; gap: 10px; }
.sl-empty--col { flex-direction: column; gap: 8px; }
.sl-empty__ico { display: inline-flex; align-items: center; justify-content: center; width: 4.5rem; height: 4.5rem; border-radius: 1.25rem; background: rgba(99, 102, 241, 0.08); color: #6366f1; font-size: 2rem; margin-bottom: 6px; }
.sl-empty h3 { margin: 0; font-size: 1.15rem; font-weight: 800; color: #1e293b; }
.sl-empty p { margin: 0; max-width: 420px; font-size: 0.88rem; color: #64748b; line-height: 1.5; }
.sl-empty__btn { appearance: none; cursor: pointer; font-family: inherit; font-size: 13px; font-weight: 800; color: #ffffff; background: linear-gradient(135deg, #8b5cf6, #6366f1); border: none; border-radius: 12px; padding: 12px 24px; box-shadow: 0 10px 24px rgba(99, 102, 241, 0.3); transition: transform 0.18s ease, box-shadow 0.18s ease; margin-top: 8px; display: inline-flex; align-items: center; gap: 6px; }
.sl-empty__btn:hover { transform: translateY(-2px); box-shadow: 0 14px 30px rgba(99, 102, 241, 0.4); }

/* ═══ LAYOUT SIDEBAR FILTER + KONTEN ═══ */
.sl-layout { display: grid; grid-template-columns: 268px minmax(0, 1fr); gap: 1.7rem; align-items: start; }
.sl-aside { position: sticky; top: 168px; z-index: 15; }

.sl-aside__body { background: rgba(255, 255, 255, 0.94); border: 1px solid #e6e9f3; border-radius: 20px; padding: 18px; box-shadow: 0 10px 30px rgba(15, 23, 42, 0.05); height: fit-content; overflow: visible; display: flex; flex-direction: column; gap: 12px; }
.sl-aside__head { display: flex; align-items: center; justify-content: space-between; padding-bottom: 10px; border-bottom: 1px solid #f1f2f9; }
.sl-aside__head > span { font-size: 14px; font-weight: 800; color: #0f172a; display: inline-flex; align-items: center; gap: 8px; }
.sl-aside__head .bi-funnel-fill { color: #6366f1; }

/* Compact Reset Button */
.sl-aside__reset { appearance: none; border: none; background: none; cursor: pointer; font-family: inherit; font-size: 11.5px; font-weight: 800; color: #e11d48; display: inline-flex; align-items: center; gap: 4px; padding: 4px 8px; border-radius: 8px; transition: background 0.16s ease; }
.sl-aside__reset:hover { background: rgba(225, 29, 72, 0.08); }

/* Clean Accordion Filter Groups */
.sl-fgroup { border-bottom: 1px solid #f1f2f9; padding-bottom: 10px; }
.sl-fgroup:last-of-type { border-bottom: none; }
.sl-fgroup__head { display: flex; align-items: center; justify-content: space-between; cursor: pointer; padding: 6px 0; user-select: none; }
.sl-fgroup__title { font-size: 11.5px; font-weight: 800; letter-spacing: 0.04em; text-transform: uppercase; color: #64748b; display: inline-flex; align-items: center; gap: 6px; }
.sl-fgroup__badge { font-size: 10px; font-weight: 800; color: #ffffff; background: #6366f1; border-radius: 999px; padding: 1px 6px; }
.sl-fgroup__chev { font-size: 11px; color: #94a3b8; transition: transform 0.25s ease; }
.sl-fgroup.is-collapsed .sl-fgroup__chev { transform: rotate(-90deg); }

.sl-fgroup__list { display: flex; flex-direction: column; gap: 4px; margin-top: 6px; }
.sl-check { display: flex; align-items: center; gap: 9px; padding: 6px 8px; border-radius: 10px; cursor: pointer; transition: background 0.14s; }
.sl-check:hover { background: #f6f7fd; }
.sl-check input { position: absolute; opacity: 0; pointer-events: none; }
.sl-check__box { flex: 0 0 auto; width: 17px; height: 17px; border-radius: 5px; border: 1.5px solid #cbd5e1; background: #fff; display: inline-flex; align-items: center; justify-content: center; color: #fff; font-size: 11px; transition: all 0.15s; }
.sl-check input:checked + .sl-check__box { background: linear-gradient(135deg, #8b5cf6, #6366f1); border-color: transparent; }
.sl-check__lbl { flex: 1; min-width: 0; font-size: 12.5px; font-weight: 600; color: #475569; line-height: 1.35; }
.sl-check input:checked ~ .sl-check__lbl { color: #4338ca; font-weight: 700; }
.sl-check__n { flex: 0 0 auto; font-size: 10.5px; font-weight: 800; color: #94a3b8; background: #eef0f7; border-radius: 8px; padding: 2px 7px; }
.sl-check input:checked ~ .sl-check__n { color: #4f46e5; background: rgba(99, 102, 241, 0.12); }

/* Job Alert Banner Widget */
.sl-job-alert { margin-top: 4px; padding: 14px; border-radius: 14px; background: linear-gradient(135deg, rgba(139, 92, 246, 0.08), rgba(99, 102, 241, 0.12)); border: 1px solid rgba(99, 102, 241, 0.2); }
.sl-job-alert__head { display: flex; align-items: center; gap: 6px; font-size: 12.5px; font-weight: 800; color: #4f46e5; }
.sl-job-alert p { margin: 6px 0 10px; font-size: 11px; color: #64748b; line-height: 1.45; }
.sl-job-alert__btn { display: inline-flex; align-items: center; gap: 4px; font-size: 11px; font-weight: 800; color: #4f46e5; text-decoration: none; }
.sl-job-alert__btn:hover { color: #3730a3; }

.sl-main { min-width: 0; }

/* ═══ GRUP PER TIM & EXPAND BUTTON ═══ */
.sl-group { margin-bottom: 2.1rem; }

/* Premium Glassmorphic Team Group Header */
.sl-group__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    flex-wrap: wrap;
    margin-bottom: 0.95rem;
    padding-bottom: 0.75rem;
    border-bottom: 1px solid #e9ebf5;
}
.sl-group__title-wrap {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}
.sl-group__head h3 {
    margin: 0;
    font-size: 16px;
    font-weight: 800;
    color: #0f172a;
    letter-spacing: -0.015em;
}
.sl-group__dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: linear-gradient(135deg, #8b5cf6, #6366f1);
    box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.14);
    flex-shrink: 0;
}
.sl-group__count {
    font-size: 11px;
    font-weight: 800;
    color: #6366f1;
    background: rgba(99, 102, 241, 0.09);
    border-radius: 999px;
    padding: 3px 10px;
    border: 1px solid rgba(99, 102, 241, 0.18);
}
.sl-group__btn-about {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 14px;
    border-radius: 999px;
    background: rgba(99, 102, 241, 0.08);
    border: 1px solid rgba(99, 102, 241, 0.22);
    color: #4f46e5;
    font-size: 12px;
    font-weight: 800;
    text-decoration: none;
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
    box-shadow: 0 4px 12px rgba(99, 102, 241, 0.06);
}
.sl-group__btn-about i {
    font-size: 13px;
    transition: transform 0.2s ease;
}
.sl-group__btn-about:hover {
    background: linear-gradient(135deg, #8b5cf6, #6366f1);
    color: #ffffff;
    border-color: transparent;
    box-shadow: 0 8px 20px rgba(99, 102, 241, 0.32);
    transform: translateY(-1px);
}
.sl-group__btn-about:hover i.bi-arrow-right {
    transform: translateX(3px);
}

.sl-group__expand { display: flex; justify-content: center; margin-top: 1.25rem; }
.sl-group__expand-btn { appearance: none; cursor: pointer; font-family: inherit; font-size: 12.5px; font-weight: 800; color: #6366f1; background: rgba(99, 102, 241, 0.08); border: 1px solid rgba(99, 102, 241, 0.22); border-radius: 12px; padding: 10px 20px; transition: all 0.2s ease; display: inline-flex; align-items: center; gap: 6px; }
.sl-group__expand-btn:hover { background: #6366f1; color: #ffffff; border-color: transparent; box-shadow: 0 8px 20px rgba(99, 102, 241, 0.28); transform: translateY(-1px); }

/* ═══ DESKTOP FLOATING BACK TO TOP BUTTON ═══ */
.sl-desktop-totop {
    position: fixed;
    bottom: 32px;
    right: 32px;
    z-index: 90;
    appearance: none;
    cursor: pointer;
    font-family: inherit;
    font-size: 13px;
    font-weight: 800;
    color: #4f46e5;
    background: rgba(255, 255, 255, 0.94);
    border: 1px solid rgba(226, 232, 240, 0.9);
    border-radius: 999px;
    padding: 10px 18px;
    display: inline-flex;
    align-items: center;
    gap: 7px;
    box-shadow: 0 10px 28px rgba(15, 23, 42, 0.12);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    transition: all 0.22s cubic-bezier(0.16, 1, 0.3, 1);
    animation: slFadeIn 0.25s ease;
}
@keyframes slFadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}
.sl-desktop-totop i {
    font-size: 15px;
    transition: transform 0.2s ease;
}
.sl-desktop-totop:hover {
    background: linear-gradient(135deg, #8b5cf6, #6366f1);
    color: #ffffff;
    border-color: transparent;
    box-shadow: 0 12px 30px rgba(99, 102, 241, 0.35);
    transform: translateY(-3px);
}
.sl-desktop-totop:hover i {
    transform: translateY(-2px);
}

@media (max-width: 991.98px) {
    .sl-desktop-totop { display: none; }
}

/* ═══ TELEPORTED MOBILE FILTER DRAWER STYLES ═══ */
.sl-mobile-backdrop {
    position: fixed;
    inset: 0;
    z-index: 99998;
    background: rgba(15, 23, 42, 0.65);
    backdrop-filter: blur(6px);
    -webkit-backdrop-filter: blur(6px);
}
.sl-mobile-drawer {
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    z-index: 99999;
    background: #ffffff;
    border-radius: 28px 28px 0 0;
    box-shadow: 0 -12px 48px rgba(0, 0, 0, 0.3);
    padding: 12px 20px 20px;
    display: flex;
    flex-direction: column;
    max-height: 84vh;
    animation: slSlideUp 0.3s cubic-bezier(0.16, 1, 0.3, 1);
}
@keyframes slSlideUp {
    from { transform: translateY(100%); }
    to { transform: translateY(0); }
}
.sl-mobile-drawer__handle {
    width: 38px;
    height: 5px;
    border-radius: 99px;
    background: #cbd5e1;
    margin: 0 auto 10px;
    flex-shrink: 0;
}
.sl-mobile-drawer__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding-bottom: 10px;
    border-bottom: 1px solid #f1f2f9;
    flex-shrink: 0;
}
.sl-mobile-drawer__head > span {
    font-size: 14px;
    font-weight: 800;
    color: #0f172a;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}
.sl-mobile-drawer__actions {
    display: flex;
    align-items: center;
    gap: 8px;
}
.sl-mobile-drawer__close {
    appearance: none;
    border: none;
    background: #f1f5f9;
    color: #64748b;
    width: 28px;
    height: 28px;
    border-radius: 50%;
    cursor: pointer;
    font-size: 12px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}
.sl-mobile-drawer__content {
    flex: 1;
    overflow-y: auto;
    -webkit-overflow-scrolling: touch;
    padding: 10px 0;
    display: flex;
    flex-direction: column;
    gap: 12px;
}
.sl-mobile-drawer__apply {
    appearance: none;
    border: none;
    cursor: pointer;
    font-family: inherit;
    font-size: 13.5px;
    font-weight: 800;
    color: #ffffff;
    background: linear-gradient(135deg, #8b5cf6, #6366f1);
    border-radius: 14px;
    padding: 13px;
    margin-top: 10px;
    box-shadow: 0 10px 24px rgba(99, 102, 241, 0.35);
    flex-shrink: 0;
}

/* Mobile Floating Action Bar & Fixed Saved Jobs Button */
.sl-mobile-fab-wrap {
    position: fixed;
    bottom: 20px;
    left: 50%;
    transform: translateX(-50%);
    z-index: 85;
    display: none;
    align-items: center;
    gap: 10px;
    width: calc(100% - 32px);
    max-width: 420px;
}
.sl-mobile-fab-filter {
    appearance: none;
    cursor: pointer;
    font-family: inherit;
    font-size: 13.5px;
    font-weight: 800;
    color: #ffffff;
    background: linear-gradient(135deg, #1e1b4b 0%, #4338ca 100%);
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 999px;
    padding: 13px 20px;
    flex: 1;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    box-shadow: 0 14px 36px rgba(30, 27, 75, 0.38);
    transition: transform 0.2s ease;
}
.sl-mobile-fab-filter:active {
    transform: scale(0.97);
}
.sl-mobile-fab-badge {
    font-size: 11px;
    font-weight: 800;
    background: #f59e0b;
    color: #1e1b4b;
    border-radius: 999px;
    padding: 2px 7px;
}

.sl-mobile-fab-saved {
    position: relative;
    appearance: none;
    cursor: pointer;
    width: 46px;
    height: 46px;
    border-radius: 50%;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    color: #6366f1;
    font-size: 19px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 10px 24px rgba(15, 23, 42, 0.15);
    flex-shrink: 0;
    transition: all 0.2s ease;
}
.sl-mobile-fab-saved.is-active {
    background: linear-gradient(135deg, #8b5cf6, #6366f1);
    color: #ffffff;
    border-color: transparent;
    box-shadow: 0 10px 26px rgba(99, 102, 241, 0.4);
}
.sl-mobile-fab-saved-badge {
    position: absolute;
    top: -4px;
    right: -4px;
    font-size: 10px;
    font-weight: 800;
    background: #f59e0b;
    color: #1e1b4b;
    border-radius: 999px;
    padding: 1px 6px;
    border: 2px solid #ffffff;
    box-shadow: 0 4px 10px rgba(245, 158, 11, 0.35);
}

.sl-mobile-fab-top {
    appearance: none;
    cursor: pointer;
    width: 46px;
    height: 46px;
    border-radius: 50%;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    color: #4338ca;
    font-size: 22px;
    display: grid;
    place-items: center;
    box-shadow: 0 10px 24px rgba(15, 23, 42, 0.15);
    flex-shrink: 0;
}

@media (max-width: 991.98px) {
    .sl-aside--desktop { display: none; }
    .sl-mobile-fab-wrap { display: flex; }
    .sl-layout { grid-template-columns: 1fr; gap: 1rem; }
}

/* ═══ GRID KARTU REKRUTMEN ═══ */
.rek__grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 1.05rem; }
.rek__grid.is-list-grid { grid-template-columns: 1fr; }

/* ═══ SECTION MT (LIGHT) ═══ */
.sl-mt { position: relative; overflow: hidden; padding: 2.6rem 1.5rem 3rem; background: radial-gradient(700px 340px at 84% 0%, rgba(139, 92, 246, 0.12), transparent 60%), linear-gradient(160deg, #f4f1fe 0%, #eef2ff 54%, #f6f1ff 100%); border-top: 1px solid rgba(99, 102, 241, 0.1); border-bottom: 1px solid rgba(99, 102, 241, 0.1); }
.sl-mt__wrap { position: relative; z-index: 2; max-width: 1200px; margin: 0 auto; }
.sl-mt .sl-sec__head { margin-bottom: 1.4rem; }
.sl-mt__grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(288px, 1fr)); gap: 1.05rem; }

.mtl__card { display: flex; flex-direction: column; background: rgba(255, 255, 255, 0.92); border: 1px solid rgba(226, 232, 240, 0.9); border-radius: 20px; padding: 18px; box-shadow: 0 8px 24px rgba(15, 23, 42, 0.05); text-decoration: none; transition: transform 0.18s ease, box-shadow 0.18s ease, border-color 0.18s ease; }
.mtl__card:hover { transform: translateY(-5px); border-color: rgba(139, 92, 246, 0.4); box-shadow: 0 20px 44px rgba(124, 110, 222, 0.18); }
.mtl__cardtop { display: flex; align-items: center; justify-content: space-between; gap: 8px; }
.mtl__batch { display: inline-flex; align-items: center; gap: 6px; font-size: 0.66rem; font-weight: 800; color: #b45309; background: rgba(245, 158, 11, 0.13); border: 1px solid rgba(245, 158, 11, 0.26); border-radius: 8px; padding: 4px 9px; }
.mtl__status { font-size: 0.63rem; font-weight: 800; border-radius: 8px; padding: 4px 9px; color: #059669; background: rgba(16, 185, 129, 0.12); }
.mtl__status.is-soon { color: #b45309; background: rgba(245, 158, 11, 0.14); }
.mtl__status.is-closed { color: #e11d48; background: rgba(225, 29, 72, 0.1); }
.mtl__title { margin: 13px 0 0; font-size: 1.03rem; font-weight: 800; color: #1e293b; letter-spacing: -0.01em; line-height: 1.25; text-wrap: pretty; }
.mtl__tag { font-size: 0.72rem; font-weight: 700; color: #8b5cf6; margin-top: 5px; }
.mtl__desc { margin: 9px 0 0; font-size: 0.78rem; line-height: 1.55; color: #64748b; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.mtl__posisi { display: flex; flex-wrap: wrap; align-items: center; gap: 6px; margin-top: 12px; }
.mtl__posisi-lbl { display: inline-flex; align-items: center; gap: 5px; font-size: 0.67rem; font-weight: 800; color: #6d28d9; }
.mtl__posisi-lbl i { font-size: 0.76rem; }
.mtl__chip { max-width: 13rem; overflow: hidden; white-space: nowrap; text-overflow: ellipsis; font-size: 0.65rem; font-weight: 700; color: #4f46e5; background: rgba(99, 102, 241, 0.08); border: 1px solid rgba(99, 102, 241, 0.16); border-radius: 7px; padding: 3px 8px; }
.mtl__chip.is-hit { color: #6d28d9; background: rgba(139, 92, 246, 0.18); border-color: rgba(139, 92, 246, 0.42); font-weight: 800; }
.mtl__chip--more { color: #64748b; background: #f1f5f9; border-color: #e2e8f0; }
.mtl__meta { display: grid; grid-template-columns: 1fr 1fr; gap: 9px 12px; margin-top: 14px; }
.mtl__meta span { display: inline-flex; align-items: center; gap: 6px; font-size: 0.72rem; color: #64748b; }
.mtl__meta i { color: #8b5cf6; font-size: 0.82rem; }
.mtl__foot { display: flex; align-items: center; justify-content: space-between; gap: 10px; margin-top: 15px; padding-top: 14px; border-top: 1px solid #f1f2f9; }
.mtl__deadline { display: inline-flex; align-items: center; gap: 6px; font-size: 0.68rem; color: #94a3b8; }
.mtl__deadline.soon { color: #b45309; font-weight: 700; }
.mtl__cta { display: inline-flex; align-items: center; gap: 5px; font-size: 0.75rem; font-weight: 800; color: #4f46e5; }
.mtl__cta i { transition: transform 0.18s ease; }
.mtl__card:hover .mtl__cta i { transform: translateX(4px); }
@media (max-width: 560px) { .mtl__meta { grid-template-columns: 1fr; } }

/* ═══ PAGINASI ═══ */
.sl-pager { display: flex; align-items: center; justify-content: center; gap: 7px; margin-top: 26px; flex-wrap: wrap; }
.sl-pager__num, .sl-pager__nav { appearance: none; cursor: pointer; min-width: 38px; height: 38px; padding: 0 10px; border-radius: 11px; border: 1px solid #e6e9f3; background: rgba(255, 255, 255, 0.9); color: #64748b; font-family: inherit; font-size: 13.5px; font-weight: 700; display: inline-flex; align-items: center; justify-content: center; transition: all 0.16s; }
.sl-pager__num:hover:not(.is-on), .sl-pager__nav:hover:not(:disabled) { border-color: #a5b4fc; color: #4f46e5; }
.sl-pager__num.is-on { background: linear-gradient(135deg, #8b5cf6, #6366f1); border-color: transparent; color: #fff; box-shadow: 0 10px 22px rgba(99, 102, 241, 0.3); }
.sl-pager__nav:disabled { opacity: 0.4; cursor: not-allowed; }

/* ═══ CTA KEMBALI ═══ */
.sl-back { display: flex; justify-content: center; padding: 2.6rem 1rem 3.2rem; }
.sl-back__btn { display: inline-flex; align-items: center; gap: 9px; font-size: 13.5px; font-weight: 800; color: #4f46e5; padding: 12px 24px; border-radius: 14px; text-decoration: none; background: #fff; border: 1px solid #dfe3f3; box-shadow: 0 8px 22px rgba(15, 23, 42, 0.06); transition: all 0.18s; }
.sl-back__btn:hover { transform: translateY(-2px); border-color: #a5b4fc; color: #4338ca; }

/* ═══ ACTIVE LOCATION FILTER BANNER ═══ */
.sl-loc-banner {
    background: linear-gradient(135deg, #1e1b4b 0%, #312e81 100%);
    border: 1px solid rgba(129, 140, 248, 0.3);
    box-shadow: 0 12px 30px rgba(49, 46, 129, 0.25);
    border-radius: 20px;
    padding: 18px 22px;
    margin-bottom: 24px;
    color: #ffffff;
    animation: slLocSlideDown 0.3s cubic-bezier(0.16, 1, 0.3, 1);
}

@keyframes slLocSlideDown {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}

.sl-loc-banner__in {
    display: flex;
    align-items: center;
    gap: 16px;
}

.sl-loc-banner__icon {
    width: 46px;
    height: 46px;
    border-radius: 14px;
    background: rgba(99, 102, 241, 0.25);
    border: 1px solid rgba(165, 180, 252, 0.3);
    color: #818cf8;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.4rem;
    flex-shrink: 0;
}

.sl-loc-banner__info {
    flex: 1;
}

.sl-loc-banner__tag {
    display: inline-block;
    font-size: 0.7rem;
    font-weight: 800;
    letter-spacing: 0.8px;
    text-transform: uppercase;
    color: #a5b4fc;
    margin-bottom: 2px;
}

.sl-loc-banner__title {
    font-size: 1.1rem;
    font-weight: 800;
    margin: 0 0 2px 0;
    color: #ffffff;
}

.sl-loc-banner__sub {
    font-size: 0.82rem;
    color: #cbd5e1;
}

.sl-loc-banner__clear {
    appearance: none;
    border: 1px solid rgba(255, 255, 255, 0.2);
    background: rgba(255, 255, 255, 0.1);
    color: #ffffff;
    padding: 8px 16px;
    border-radius: 12px;
    font-size: 0.8rem;
    font-weight: 700;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: all 0.18s ease;
    flex-shrink: 0;
}

.sl-loc-banner__clear:hover {
    background: rgba(225, 29, 72, 0.25);
    border-color: rgba(244, 63, 94, 0.4);
    color: #fca5a5;
}

@media (max-width: 767.98px) {
    .sl-hero { padding: 6.2rem 1rem 1rem; }
    .sl-hero__in h1 { font-size: 1.35rem; margin-top: 6px; }
    .sl-hero__desc-desktop { display: none; }
    .sl-hero__stats { gap: 6px; margin-top: 12px; }
    .sl-stat { padding: 5px 10px; font-size: 11px; border-radius: 10px; }
    .sl-stat__ico { width: 24px; height: 24px; font-size: 12px; border-radius: 6px; }
    
    .sl-tab__lbl-desktop { display: none; }
    .sl-tab__lbl-mobile { display: inline; }
    
    .sl-toolbar-wrap { position: static; padding: 4px 1rem; }
    .sl-toolbar { padding: 8px 12px; border-radius: 16px; gap: 8px; }
    .sl-toolbar__tier1 { flex-direction: column; align-items: stretch; gap: 8px; }
    .sl-tabs { gap: 5px; flex-wrap: nowrap; overflow-x: auto; scrollbar-width: none; }
    .sl-tabs::-webkit-scrollbar { display: none; }
    .sl-tab { padding: 7px 11px; font-size: 12px; border-radius: 10px; flex-shrink: 0; }
    .sl-tab__n { font-size: 10px; padding: 1px 6px; }
    
    .sl-search { max-width: 100%; width: 100%; }
    .sl-search input { padding: 8px 30px 8px 34px; font-size: 12px; border-radius: 10px; }
    .sl-search .bi-search { left: 12px; font-size: 12px; }
    
    .sl-toolbar__tier2 { padding-top: 8px; gap: 8px; flex-direction: column; align-items: stretch; }
    .sl-controls { justify-content: space-between; width: 100%; }
    
    .sl-group { margin-bottom: 1.1rem; }
    .sl-group__head { margin-bottom: 0.6rem; padding-bottom: 0.4rem; flex-direction: row; justify-content: space-between; align-items: center; }
    .sl-group__head h3 { font-size: 13.5px; }
    .sl-group__btn-about { font-size: 11px; padding: 4px 10px; }
    
    .sl-sec { padding: 1rem 1rem 2rem; }
    .sl-sec__head { margin-bottom: 1rem; }
    .sl-sec__head h2 { font-size: 16px; }
    .sl-sec__count { font-size: 10.5px; padding: 4px 10px; }
    
    .sl-mt-strip__in { flex-direction: column; align-items: flex-start; padding: 0.6rem 1rem; }
    .sl-mt-strip__btn { width: 100%; justify-content: center; margin-top: 4px; font-size: 0.72rem; }

    /* List mobile: tiap lowongan jadi kartu sendiri yang berjarak —
       bukan satu blok panjang yang menempel (lihat LowonganCard.vue). */
    .rek__grid.is-list-grid {
        gap: 10px;
        background: transparent;
        border: none;
        box-shadow: none;
    }
}
</style>
