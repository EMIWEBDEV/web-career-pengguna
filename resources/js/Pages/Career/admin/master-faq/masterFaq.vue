<!-- WEB CAREER — Master FAQ (per-modul, pola Master Kategori).
     DATA via web route + ResponseHelper (axios), bukan props Inertia.
     Dua entitas dalam satu halaman: PERTANYAAN dan KATEGORI. -->
<template>
    <Head title="Master FAQ" />
    <div class="wca">
        <div class="wca-phead">
            <div>
                <h1>Master FAQ</h1>
                <p>
                    Pertanyaan yang sering diajukan kandidat. Muncul di <b>accordion landing page</b> (yang ditandai)
                    dan seluruhnya di halaman publik <b>/karir/faq</b>.
                </p>
            </div>
            <div class="wca-phead__actions">
                <a class="wca-btn" href="/karir/faq" target="_blank" rel="noopener">
                    <i class="bi bi-box-arrow-up-right"></i> Lihat Halaman Publik
                </a>
                <button v-if="tab === 'faq'" class="wca-btn wca-btn--primary" @click="openCreate">
                    <i class="bi bi-plus-lg"></i> Pertanyaan Baru
                </button>
                <button v-else class="wca-btn wca-btn--primary" @click="openCreateKat">
                    <i class="bi bi-plus-lg"></i> Kategori Baru
                </button>
            </div>
        </div>

        <!-- ── KPI Summary Cards ── -->
        <div class="mf-kpi-grid">
            <div class="mf-kpi-card mf-kpi--indigo">
                <div class="mf-kpi-card__icon"><i class="bi bi-patch-question-fill"></i></div>
                <div class="mf-kpi-card__info">
                    <span class="mf-kpi-card__val">{{ list.length }}</span>
                    <span class="mf-kpi-card__lbl">Total Pertanyaan</span>
                </div>
            </div>

            <div class="mf-kpi-card mf-kpi--purple">
                <div class="mf-kpi-card__icon"><i class="bi bi-collection-fill"></i></div>
                <div class="mf-kpi-card__info">
                    <span class="mf-kpi-card__val">{{ kategori.length }}</span>
                    <span class="mf-kpi-card__lbl">Total Kategori</span>
                </div>
            </div>

            <div class="mf-kpi-card mf-kpi--amber">
                <div class="mf-kpi-card__icon"><i class="bi bi-stars"></i></div>
                <div class="mf-kpi-card__info">
                    <span class="mf-kpi-card__val">{{ totalLanding }}</span>
                    <span class="mf-kpi-card__lbl">Tampil di Landing Page</span>
                </div>
            </div>

            <div class="mf-kpi-card mf-kpi--emerald">
                <div class="mf-kpi-card__icon"><i class="bi bi-hand-thumbs-up-fill"></i></div>
                <div class="mf-kpi-card__info">
                    <span class="mf-kpi-card__val">{{ totalMembantu }}</span>
                    <span class="mf-kpi-card__lbl">Penilaian Membantu</span>
                </div>
            </div>
        </div>

        <div class="wca-note wca-note--info">
            <i class="bi bi-info-circle-fill"></i>
            <span>
                <b>Informasi Urutan:</b> Tombol naik/turun (<i class="bi bi-chevron-up"></i> <i class="bi bi-chevron-down"></i>) mengatur posisi urutan pertanyaan <b>di dalam kelompok kategorinya masing-masing</b>.
                <b>Jawaban Ringkas</b> dipakai di accordion landing, sedangkan <b>Jawaban Detail</b> tampil di halaman <b>/karir/faq</b>.
            </span>
        </div>

        <div class="wca-segt">
            <button class="wca-segt__it" :class="{ on: tab === 'faq' }" @click="tab = 'faq'">
                <i class="bi bi-patch-question"></i> Pertanyaan
                <span class="wca-badge wca-b--slate">{{ list.length }}</span>
            </button>
            <button class="wca-segt__it" :class="{ on: tab === 'kategori' }" @click="tab = 'kategori'">
                <i class="bi bi-collection"></i> Kategori
                <span class="wca-badge wca-b--slate">{{ kategori.length }}</span>
            </button>
        </div>

        <!-- ════════════════ TAB: PERTANYAAN ════════════════ -->
        <template v-if="tab === 'faq'">
            <!-- ── Modern Filter & Control Panel ── -->
            <div class="mf-filter-panel">
                <div class="mf-fp-top">
                    <div class="mf-fp-search">
                        <i class="bi bi-search mf-fps-icon"></i>
                        <input
                            v-model="q"
                            type="text"
                            class="mf-fps-input"
                            placeholder="Cari pertanyaan, slug, atau kata kunci jawaban..."
                        />
                        <button v-if="q" class="mf-fps-clear" title="Bersihkan pencarian" @click="q = ''">
                            <i class="bi bi-x-circle-fill"></i>
                        </button>
                    </div>

                    <div class="mf-fp-status">
                        <button
                            class="mf-st-pill"
                            :class="{ 'is-active': statusFilter === '' }"
                            @click="statusFilter = ''"
                        >
                            Semua ({{ list.length }})
                        </button>
                        <button
                            class="mf-st-pill mf-st-pill--landing"
                            :class="{ 'is-active': statusFilter === 'landing' }"
                            @click="statusFilter = 'landing'"
                        >
                            <i class="bi bi-stars"></i> Landing ({{ totalLanding }})
                        </button>
                        <button
                            class="mf-st-pill mf-st-pill--active"
                            :class="{ 'is-active': statusFilter === 'aktif' }"
                            @click="statusFilter = 'aktif'"
                        >
                            <i class="bi bi-check-circle-fill"></i> Aktif ({{ totalAktif }})
                        </button>
                        <button
                            class="mf-st-pill mf-st-pill--draft"
                            :class="{ 'is-active': statusFilter === 'draft' }"
                            @click="statusFilter = 'draft'"
                        >
                            <i class="bi bi-dash-circle-fill"></i> Nonaktif ({{ totalDraft }})
                        </button>
                    </div>

                    <div class="mf-fp-view">
                        <div class="mf-view-toggle">
                            <button
                                class="mf-vt-btn"
                                :class="{ 'is-active': groupView }"
                                title="Tampilkan per kelompok kategori"
                                @click="groupView = true"
                            >
                                <i class="bi bi-grid-fill"></i>
                                <span>Per Kategori</span>
                            </button>
                            <button
                                class="mf-vt-btn"
                                :class="{ 'is-active': !groupView }"
                                title="Tampilkan tabel gabungan"
                                @click="groupView = false"
                            >
                                <i class="bi bi-list-task"></i>
                                <span>Tabel Semua</span>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="mf-fp-bottom">
                    <span class="mf-fp-label"><i class="bi bi-funnel-fill"></i> Kategori:</span>
                    <div class="mf-chips-scroll">
                        <button
                            class="mf-chip"
                            :class="{ 'is-active': katFilter === '' }"
                            @click="katFilter = ''"
                        >
                            Semua Kategori <span class="mf-chip__cnt">{{ list.length }}</span>
                        </button>
                        <button
                            v-for="k in kategori"
                            :key="k.id"
                            class="mf-chip"
                            :class="{ 'is-active': String(katFilter) === String(k.id) }"
                            @click="katFilter = katFilter === k.id ? '' : k.id"
                        >
                            <i class="bi" :class="k.ikon || 'bi-folder-fill'"></i>
                            {{ k.nama }}
                            <span class="mf-chip__cnt">{{ jumlahFaqByKat(k.id) }}</span>
                        </button>
                        <button
                            v-if="jumlahFaqByKat('tanpa-kategori') > 0"
                            class="mf-chip"
                            :class="{ 'is-active': katFilter === 'tanpa-kategori' }"
                            @click="katFilter = katFilter === 'tanpa-kategori' ? '' : 'tanpa-kategori'"
                        >
                            <i class="bi bi-folder"></i> Tanpa Kategori
                            <span class="mf-chip__cnt">{{ jumlahFaqByKat('tanpa-kategori') }}</span>
                        </button>

                        <button
                            v-if="q || katFilter || statusFilter"
                            class="mf-chip-reset"
                            title="Reset semua filter"
                            @click="resetFilter"
                        >
                            <i class="bi bi-arrow-counterclockwise"></i> Reset Filter
                        </button>
                    </div>
                </div>
            </div>

            <!-- Grouped View (Default & Super Clear for Laypeople) -->
            <div v-if="groupView" class="mf-grouped-list">
                <div v-if="!groupedFiltered.length" class="wca-card">
                    <div class="wca-empty">
                        <i class="bi bi-patch-question"></i>
                        <h4>Belum ada pertanyaan</h4>
                    </div>
                </div>

                <div
                    v-for="grp in groupedFiltered"
                    :key="grp.katId || 'tanpa-kat'"
                    class="wca-card mf-group-card"
                >
                    <div class="mf-group-card__head">
                        <div class="mf-group-card__title">
                            <span class="mf-ikon"><i class="bi" :class="grp.ikon || 'bi-collection-fill'"></i></span>
                            <div>
                                <h3>{{ grp.nama }}</h3>
                                <span v-if="grp.deskripsi" class="mf-group-card__desc">{{ grp.deskripsi }}</span>
                            </div>
                        </div>
                        <div class="mf-group-card__badge">
                            <span class="wca-badge wca-b--indigo">{{ grp.items.length }} Pertanyaan</span>
                        </div>
                    </div>

                    <div class="wca-card__body--flush">
                        <div class="wca-tablewrap">
                            <table class="wca-table">
                                <thead>
                                    <tr>
                                        <th style="width: 100px">Urutan</th>
                                        <th>Pertanyaan</th>
                                        <th style="width: 92px">Landing</th>
                                        <th style="width: 140px">Statistik</th>
                                        <th style="width: 80px">Aktif</th>
                                        <th style="width: 120px">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="(f, idx) in grp.items"
                                        :key="f.id"
                                        :class="{
                                            off: !f.aktif,
                                            'mf-row-busy': busyMap[f.id],
                                            'mf-row-swapping': busyMap[f.id] === 'reorder',
                                            'mf-row-updated': flashMap[f.id] === 'status',
                                            'mf-row-landing-flash': flashMap[f.id] === 'landing',
                                        }"
                                    >
                                        <td>
                                            <div class="mf-urut-cell">
                                                <span class="mf-pos-badge__num">#{{ idx + 1 }}</span>
                                                <div class="mf-urut-btns">
                                                    <button
                                                        class="wca-iconbtn"
                                                        title="Naikkan urutan"
                                                        :disabled="idx === 0 || !!busyMap[f.id]"
                                                        :onClick="idx === 0 || !!busyMap[f.id] ? null : () => pindahGroup(grp, idx, -1)"
                                                    >
                                                        <span v-if="busyMap[f.id] === 'reorder'" class="wca-spin mf-btn-spin"></span>
                                                        <i v-else class="bi bi-chevron-up"></i>
                                                    </button>
                                                    <button
                                                        class="wca-iconbtn"
                                                        title="Turunkan urutan"
                                                        :disabled="idx === grp.items.length - 1 || !!busyMap[f.id]"
                                                        :onClick="idx === grp.items.length - 1 || !!busyMap[f.id] ? null : () => pindahGroup(grp, idx, 1)"
                                                    >
                                                        <span v-if="busyMap[f.id] === 'reorder'" class="wca-spin mf-btn-spin"></span>
                                                        <i v-else class="bi bi-chevron-down"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div style="display: flex; align-items: flex-start; gap: 0.55rem">
                                                <span class="mf-ikon"
                                                    ><i class="bi" :class="f.ikon || 'bi-question-circle-fill'"></i
                                                ></span>
                                                <div>
                                                    <strong>{{ f.pertanyaan }}</strong>
                                                    <span
                                                        v-if="!f.jawabanDetail"
                                                        class="wca-badge wca-b--amber"
                                                        style="margin-left: 0.3rem"
                                                        title="Halaman /karir/faq hanya menampilkan jawaban ringkas"
                                                        >Detail kosong</span
                                                    >
                                                    <span
                                                        v-else
                                                        class="wca-badge wca-b--indigo"
                                                        style="margin-left: 0.3rem"
                                                        >Rich Text</span
                                                    ><br />
                                                    <small style="color: var(--muted); font-weight: 700">{{
                                                        potong(f.jawabanRingkas)
                                                    }}</small
                                                    ><br />
                                                    <a
                                                        class="mf-slug"
                                                        :href="'/karir/faq#' + f.slug"
                                                        target="_blank"
                                                        rel="noopener"
                                                        title="Buka tautan langsung ke pertanyaan ini"
                                                    >
                                                        <i class="bi bi-link-45deg"></i>#{{ f.slug }}
                                                    </a>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="mf-switch-cell">
                                                <el-switch
                                                    :model-value="f.tampilLanding"
                                                    :disabled="!!busyMap[f.id]"
                                                    @change="(v) => setLanding(f, v)"
                                                />
                                                <span v-if="busyMap[f.id] === 'landing'" class="wca-spin mf-mini-spin"></span>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="mf-stat">
                                                <span title="Dilihat"><i class="bi bi-eye"></i> {{ f.dilihat }}</span>
                                                <span title="Membantu"
                                                    ><i class="bi bi-hand-thumbs-up"></i> {{ f.membantu }}</span
                                                >
                                                <span title="Tidak membantu"
                                                    ><i class="bi bi-hand-thumbs-down"></i> {{ f.tidakMembantu }}</span
                                                >
                                            </div>
                                        </td>
                                        <td>
                                            <div class="mf-switch-cell">
                                                <el-switch
                                                    :model-value="f.aktif"
                                                    :disabled="!!busyMap[f.id]"
                                                    @change="(v) => setStatus(f, v)"
                                                />
                                                <span v-if="busyMap[f.id] === 'status'" class="wca-spin mf-mini-spin"></span>
                                            </div>
                                        </td>
                                        <td>
                                            <div
                                                style="
                                                    display: flex;
                                                    gap: 0.5rem;
                                                    align-items: center;
                                                    justify-content: flex-end;
                                                "
                                            >
                                                <button class="wca-iconbtn" title="Ubah" @click="openEdit(f)">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <button
                                                    class="wca-iconbtn"
                                                    title="Selaraskan slug dengan pertanyaan (tautan lama akan mati)"
                                                    @click="askSlug(f)"
                                                >
                                                    <i class="bi bi-arrow-repeat"></i>
                                                </button>
                                                <button
                                                    class="wca-iconbtn wca-iconbtn--danger"
                                                    title="Hapus"
                                                    @click="askRemove(f)"
                                                >
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Flat Table View -->
            <div v-else v-loading="loading" class="wca-card">
                <div class="wca-card__body--flush">
                    <div class="wca-tablewrap">
                        <table class="wca-table">
                            <thead>
                                <tr>
                                    <th style="width: 140px">
                                        Urutan
                                        <span class="mf-th-sub" title="Urutan ditentukan relatif di dalam kategorinya masing-masing">(per Kategori)</span>
                                    </th>
                                    <th>Pertanyaan</th>
                                    <th>Kategori</th>
                                    <th style="width: 92px">Landing</th>
                                    <th style="width: 150px">Statistik</th>
                                    <th style="width: 80px">Aktif</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="(f, i) in filtered"
                                    :key="f.id"
                                    :class="{
                                        off: !f.aktif,
                                        'mf-row-busy': busyMap[f.id],
                                        'mf-row-swapping': busyMap[f.id] === 'reorder',
                                        'mf-row-updated': flashMap[f.id] === 'status',
                                        'mf-row-landing-flash': flashMap[f.id] === 'landing',
                                    }"
                                >
                                    <td>
                                        <div class="mf-urut-cell">
                                            <div
                                                class="mf-pos-badge"
                                                :title="'Posisi urut ke-' + posisiSekategori(f) + ' dari ' + totalSekategori(f) + ' pertanyaan di kategori ' + (f.kategoriNama || 'Tanpa kategori')"
                                            >
                                                <span class="mf-pos-badge__num">#{{ posisiSekategori(f) }}</span>
                                                <span class="mf-pos-badge__sub">di {{ potong(f.kategoriNama || 'Tanpa kategori', 10) }}</span>
                                            </div>
                                            <div class="mf-urut-btns">
                                                <button
                                                    class="wca-iconbtn"
                                                    :title="pesanNaik(i)"
                                                    :disabled="!bisaNaik(i) || !!busyMap[f.id]"
                                                    :onClick="!bisaNaik(i) || !!busyMap[f.id] ? null : () => pindah(i, -1)"
                                                >
                                                    <span v-if="busyMap[f.id] === 'reorder'" class="wca-spin mf-btn-spin"></span>
                                                    <i v-else class="bi bi-chevron-up"></i>
                                                </button>
                                                <button
                                                    class="wca-iconbtn"
                                                    :title="pesanTurun(i)"
                                                    :disabled="!bisaTurun(i) || !!busyMap[f.id]"
                                                    :onClick="!bisaTurun(i) || !!busyMap[f.id] ? null : () => pindah(i, 1)"
                                                >
                                                    <span v-if="busyMap[f.id] === 'reorder'" class="wca-spin mf-btn-spin"></span>
                                                    <i v-else class="bi bi-chevron-down"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div style="display: flex; align-items: flex-start; gap: 0.55rem">
                                            <span class="mf-ikon"
                                                ><i class="bi" :class="f.ikon || 'bi-question-circle-fill'"></i
                                            ></span>
                                            <div>
                                                <strong>{{ f.pertanyaan }}</strong>
                                                <span
                                                    v-if="!f.jawabanDetail"
                                                    class="wca-badge wca-b--amber"
                                                    style="margin-left: 0.3rem"
                                                    title="Halaman /karir/faq hanya menampilkan jawaban ringkas"
                                                    >Detail kosong</span
                                                ><br />
                                                <small style="color: var(--muted); font-weight: 700">{{
                                                    potong(f.jawabanRingkas)
                                                }}</small
                                                ><br />
                                                <a
                                                    class="mf-slug"
                                                    :href="'/karir/faq#' + f.slug"
                                                    target="_blank"
                                                    rel="noopener"
                                                    title="Buka tautan langsung ke pertanyaan ini"
                                                >
                                                    <i class="bi bi-link-45deg"></i>#{{ f.slug }}
                                                </a>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span v-if="f.kategoriNama" class="wca-badge wca-b--indigo">{{
                                            f.kategoriNama
                                        }}</span>
                                        <span v-else class="wca-badge wca-b--slate">Tanpa kategori</span>
                                        <div v-if="f.kategoriNonaktif" class="mf-warn">
                                            <i class="bi bi-exclamation-triangle-fill"></i> Kategori nonaktif — tidak
                                            tampil di publik
                                        </div>
                                    </td>
                                    <td>
                                        <div class="mf-switch-cell">
                                            <el-switch
                                                :model-value="f.tampilLanding"
                                                :disabled="!!busyMap[f.id]"
                                                @change="(v) => setLanding(f, v)"
                                            />
                                            <span v-if="busyMap[f.id] === 'landing'" class="wca-spin mf-mini-spin"></span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="mf-stat">
                                            <span title="Dilihat"><i class="bi bi-eye"></i> {{ f.dilihat }}</span>
                                            <span title="Membantu"
                                                ><i class="bi bi-hand-thumbs-up"></i> {{ f.membantu }}</span
                                            >
                                            <span title="Tidak membantu"
                                                ><i class="bi bi-hand-thumbs-down"></i> {{ f.tidakMembantu }}</span
                                            >
                                        </div>
                                    </td>
                                    <td>
                                        <div class="mf-switch-cell">
                                            <el-switch
                                                :model-value="f.aktif"
                                                :disabled="!!busyMap[f.id]"
                                                @change="(v) => setStatus(f, v)"
                                            />
                                            <span v-if="busyMap[f.id] === 'status'" class="wca-spin mf-mini-spin"></span>
                                        </div>
                                    </td>
                                    <td>
                                        <div
                                            style="
                                                display: flex;
                                                gap: 0.5rem;
                                                align-items: center;
                                                justify-content: flex-end;
                                            "
                                        >
                                            <button class="wca-iconbtn" title="Ubah" @click="openEdit(f)">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <button
                                                class="wca-iconbtn"
                                                title="Selaraskan slug dengan pertanyaan (tautan lama akan mati)"
                                                @click="askSlug(f)"
                                            >
                                                <i class="bi bi-arrow-repeat"></i>
                                            </button>
                                            <button
                                                class="wca-iconbtn wca-iconbtn--danger"
                                                title="Hapus"
                                                @click="askRemove(f)"
                                            >
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="!loading && !filtered.length">
                                    <td colspan="7">
                                        <div class="wca-empty">
                                            <i class="bi bi-patch-question"></i>
                                            <h4>Belum ada pertanyaan</h4>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </template>

        <!-- ════════════════ TAB: KATEGORI ════════════════ -->
        <template v-else>
            <div v-loading="loadingKat" class="wca-card">
                <div class="wca-card__body--flush">
                    <div class="wca-tablewrap">
                        <table class="wca-table">
                            <thead>
                                <tr>
                                    <th style="width: 90px">Urutan</th>
                                    <th>Kategori</th>
                                    <th style="width: 110px">Pertanyaan</th>
                                    <th style="width: 80px">Status</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="k in kategori"
                                    :key="k.id"
                                    :class="{
                                        off: !k.aktif,
                                        'mf-row-busy': busyKatMap[k.id],
                                        'mf-row-updated': flashKatMap[k.id] === 'status',
                                    }"
                                >
                                    <td>
                                        <span class="wca-badge wca-b--slate">{{ k.urutan }}</span>
                                    </td>
                                    <td>
                                        <div style="display: flex; align-items: flex-start; gap: 0.55rem">
                                            <span class="mf-ikon"
                                                ><i class="bi" :class="k.ikon || 'bi-collection-fill'"></i
                                            ></span>
                                            <div>
                                                <strong>{{ k.nama }}</strong>
                                                <span class="wca-badge wca-b--slate" style="margin-left: 0.3rem">{{
                                                    k.kode
                                                }}</span
                                                ><br />
                                                <small style="color: var(--muted); font-weight: 700">{{
                                                    k.deskripsi || '—'
                                                }}</small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="wca-badge wca-b--indigo">{{ k.jumlahFaq }}</span>
                                    </td>
                                    <td>
                                        <div class="mf-switch-cell">
                                            <el-switch
                                                :model-value="k.aktif"
                                                :disabled="!!busyKatMap[k.id]"
                                                @change="(v) => setStatusKat(k, v)"
                                            />
                                            <span v-if="busyKatMap[k.id] === 'status'" class="wca-spin mf-mini-spin"></span>
                                        </div>
                                    </td>
                                    <td>
                                        <div
                                            style="
                                                display: flex;
                                                gap: 0.5rem;
                                                align-items: center;
                                                justify-content: flex-end;
                                            "
                                        >
                                            <button class="wca-iconbtn" title="Ubah" @click="openEditKat(k)">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <button
                                                class="wca-iconbtn wca-iconbtn--danger"
                                                title="Hapus"
                                                @click="askRemoveKat(k)"
                                            >
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="!loadingKat && !kategori.length">
                                    <td colspan="5">
                                        <div class="wca-empty">
                                            <i class="bi bi-collection"></i>
                                            <h4>Belum ada kategori</h4>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </template>

        <!-- ════════════════ MODAL: PERTANYAAN ════════════════ -->
        <AdminModal
            xl
            :busy="saving"
            :show="show"
            :title="editingId ? 'Ubah Pertanyaan' : 'Pertanyaan Baru'"
            subtitle="Jawaban ringkas untuk landing, jawaban detail untuk halaman FAQ"
            icon="bi-patch-question"
            :save-label="editingId ? 'Perbarui' : 'Simpan Pertanyaan'"
            @close="show = false"
            @save="save"
        >
            <div class="mf-modal-tip">
                <div class="mf-modal-tip__icon"><i class="bi bi-lightbulb-fill"></i></div>
                <div class="mf-modal-tip__content">
                    <strong>Ketentuan Pengisian Jawaban</strong>
                    <p><b>Jawaban Ringkas</b> wajib diisi untuk accordion di Landing Page (teks tanpa format). <b>Jawaban Detail</b> opsional, digunakan jika pertanyaan membutuhkan penjelasan panjang/link di halaman <code>/karir/faq</code>.</p>
                </div>
            </div>

            <div class="wca-fsection">
                <div class="wca-fsection__label"><i class="bi bi-patch-question-fill"></i> Informasi Pertanyaan</div>
                <div class="wca-form">
                    <div class="wca-frow">
                        <div>
                            <label class="wca-field-lbl">Kategori <span class="mf-subtle">(Opsional)</span></label>
                            <el-select
                                v-model="form.kategoriId"
                                placeholder="Pilih kategori"
                                clearable
                                style="width: 100%"
                            >
                                <el-option v-for="k in kategoriAktif" :key="k.id" :label="k.nama" :value="k.id" />
                            </el-select>
                        </div>
                        <div>
                            <label class="wca-field-lbl">Ikon Pertanyaan</label>
                            <IconPicker v-model="form.ikon" />
                        </div>
                    </div>
                    <div>
                        <label class="wca-field-lbl">Pertanyaan <span class="mf-req">*</span></label>
                        <el-input
                            v-model="form.pertanyaan"
                            maxlength="300"
                            show-word-limit
                            placeholder="Contoh: Apakah Fresh Graduate bisa melamar di EVO Group?"
                        />
                    </div>
                    <div>
                        <label class="wca-field-lbl">
                            Jawaban Ringkas <span class="mf-req">*</span>
                            <span class="wca-badge wca-b--slate" style="margin-left: 0.35rem; font-size: 0.68rem;">Accordion Landing</span>
                        </label>
                        <el-input
                            v-model="form.jawabanRingkas"
                            type="textarea"
                            :rows="3"
                            maxlength="1000"
                            show-word-limit
                            placeholder="Jawaban singkat 1-2 kalimat yang langsung menjawab pertanyaan calon pelamar."
                        />
                    </div>
                </div>
            </div>

            <div class="wca-fsection">
                <div class="wca-fsection__label">
                    <i class="bi bi-file-earmark-richtext-fill"></i> Jawaban Detail
                    <span class="wca-badge wca-b--indigo" style="margin-left: 0.35rem; font-size: 0.68rem;">Halaman /karir/faq</span>
                </div>
                <div class="wca-form">
                    <EditorQuill
                        v-model="form.jawabanDetail"
                        hint="Format yang didukung: tebal, miring, garis bawah, coret, daftar, kutipan, tautan, dan judul kecil."
                        placeholder="Penjelasan lengkap — boleh pakai daftar bernomor untuk alur pendaftaran, serta tautan ke halaman lain."
                    />
                </div>
            </div>

            <div class="wca-fsection">
                <div class="wca-fsection__label"><i class="bi bi-toggles"></i> Penempatan & Status</div>
                <div class="wca-form">
                    <div style="margin-bottom: 0.9rem;">
                        <label class="wca-field-lbl">Urutan Tampil</label>
                        <div style="max-width: 220px;">
                            <el-input v-model.number="form.urutan" type="number" :min="0" placeholder="0" />
                        </div>
                        <span class="mf-help-text">Posisi relatif pertanyaan di dalam kategorinya (0 = paling atas).</span>
                    </div>

                    <div class="mf-switch-grid">
                        <div
                            class="mf-switch-card"
                            :class="{ 'is-active': form.tampilLanding }"
                            @click="form.tampilLanding = !form.tampilLanding"
                        >
                            <div class="mf-switch-card__icon mf-icon--amber"><i class="bi bi-stars"></i></div>
                            <div class="mf-switch-card__body">
                                <div class="mf-switch-card__title">Tampilkan di Landing Page</div>
                                <div class="mf-switch-card__desc">Sorot pertanyaan ini di accordion halaman utama karir</div>
                            </div>
                            <el-switch v-model="form.tampilLanding" @click.stop />
                        </div>

                        <div
                            class="mf-switch-card"
                            :class="{ 'is-active': form.aktif }"
                            @click="form.aktif = !form.aktif"
                        >
                            <div class="mf-switch-card__icon mf-icon--emerald"><i class="bi bi-eye-fill"></i></div>
                            <div class="mf-switch-card__body">
                                <div class="mf-switch-card__title">Status Publik (Aktif)</div>
                                <div class="mf-switch-card__desc">Tampilkan pertanyaan ini di portal publik karir</div>
                            </div>
                            <el-switch v-model="form.aktif" @click.stop />
                        </div>
                    </div>
                </div>
            </div>
        </AdminModal>

        <!-- ════════════════ MODAL: KATEGORI ════════════════ -->
        <AdminModal
            lg
            :busy="savingKat"
            :show="showKat"
            :title="editingKatId ? 'Ubah Kategori FAQ' : 'Tambah Kategori FAQ Baru'"
            subtitle="Pengelompokan pertanyaan di halaman publik /karir/faq"
            icon="bi-collection"
            :save-label="editingKatId ? 'Perbarui Kategori' : 'Simpan Kategori'"
            @close="showKat = false"
            @save="saveKat"
        >
            <!-- Guidance Tip -->
            <div class="mf-modal-tip">
                <div class="mf-modal-tip__icon"><i class="bi bi-lightbulb-fill"></i></div>
                <div class="mf-modal-tip__content">
                    <strong>Fungsi Kategori FAQ</strong>
                    <p>Kategori membagi pertanyaan ke dalam kelompok/section di halaman <code>/karir/faq</code> dan membuat tombol saringan tab (*filter chips*). Mengubah status kategori ke nonaktif akan me-nonaktifkan tampilan kelompok ini di portal publik.</p>
                </div>
            </div>

            <!-- Live Preview Box -->
            <div class="mf-kat-preview">
                <span class="mf-kat-preview__lbl"><i class="bi bi-eye"></i> Pratinjau Tampilan Kategori di Publik</span>
                <div class="mf-kat-preview__card">
                    <span class="mf-ikon"><i class="bi" :class="formKat.ikon || 'bi-collection-fill'"></i></span>
                    <div>
                        <strong class="mf-kat-preview__title">{{ formKat.nama || 'Nama Kategori FAQ' }}</strong>
                        <p class="mf-kat-preview__desc">{{ formKat.deskripsi || 'Subjudul pengantar kelompok pertanyaan akan tampil di sini...' }}</p>
                    </div>
                </div>
            </div>

            <div class="wca-fsection">
                <div class="wca-fsection__label"><i class="bi bi-collection-fill"></i> Identitas Kategori</div>
                <div class="wca-form">
                    <div class="wca-frow">
                        <div>
                            <label class="wca-field-lbl">Nama Kategori <span class="mf-req">*</span></label>
                            <el-input v-model="formKat.nama" maxlength="100" placeholder="Contoh: Pendaftaran & Akun" />
                        </div>
                        <div>
                            <label class="wca-field-lbl">Ikon Kategori</label>
                            <IconPicker v-model="formKat.ikon" />
                        </div>
                    </div>
                    <div>
                        <label class="wca-field-lbl">Deskripsi Subjudul <span class="mf-subtle">(Opsional)</span></label>
                        <el-input
                            v-model="formKat.deskripsi"
                            type="textarea"
                            :rows="2"
                            maxlength="300"
                            show-word-limit
                            placeholder="Subjudul pengantar singkat yang menjelaskan kelompok pertanyaan ini di halaman FAQ..."
                        />
                    </div>
                </div>
            </div>

            <div class="wca-fsection">
                <div class="wca-fsection__label"><i class="bi bi-sliders"></i> Pengaturan Urutan & Status</div>
                <div class="wca-form">
                    <div class="wca-frow" style="align-items: flex-start;">
                        <div>
                            <label class="wca-field-lbl">Urutan Tampil Section Kategori</label>
                            <el-input v-model.number="formKat.urutan" type="number" :min="0" placeholder="0" />
                            <span class="mf-help-text">Nomor urut posisi kelompok kategori di halaman /karir/faq (0 = section paling atas).</span>
                        </div>
                        <div>
                            <label class="wca-field-lbl">Status Kategori</label>
                            <div
                                class="mf-switch-card"
                                :class="{ 'is-active': formKat.aktif }"
                                @click="formKat.aktif = !formKat.aktif"
                            >
                                <div class="mf-switch-card__icon mf-icon--emerald"><i class="bi bi-toggle-on"></i></div>
                                <div class="mf-switch-card__body">
                                    <div class="mf-switch-card__title">Status Kategori (Aktif)</div>
                                    <div class="mf-switch-card__desc">Tampilkan kelompok kategori ini dan pertanyaannya di publik</div>
                                </div>
                                <el-switch v-model="formKat.aktif" @click.stop />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </AdminModal>

        <!-- ════════════════ KONFIRMASI ════════════════ -->
        <ConfirmModal
            :show="delShow"
            title="Hapus Pertanyaan"
            :busy="deleting"
            confirm-label="Ya, Hapus"
            note="Pertanyaan disembunyikan dari publik; statistik dilihat & penilaian tetap tersimpan."
            @cancel="delShow = false"
            @confirm="confirmDelete"
        >
            Yakin ingin menghapus pertanyaan <strong>{{ delTarget?.pertanyaan }}</strong
            >?
        </ConfirmModal>

        <ConfirmModal
            :show="delKatShow"
            title="Hapus Kategori"
            :busy="deletingKat"
            confirm-label="Ya, Hapus"
            note="Kategori yang masih dipakai pertanyaan tidak bisa dihapus."
            @cancel="delKatShow = false"
            @confirm="confirmDeleteKat"
        >
            Yakin ingin menghapus kategori <strong>{{ delKatTarget?.nama }}</strong
            >?
        </ConfirmModal>

        <ConfirmModal
            :show="slugShow"
            title="Selaraskan Slug"
            :busy="slugBusy"
            confirm-label="Ya, Perbarui Slug"
            note="Tautan lama (#slug sebelumnya) berhenti membuka pertanyaan ini."
            @cancel="slugShow = false"
            @confirm="confirmSlug"
        >
            Slug akan dibuat ulang dari pertanyaan saat ini. Tautan
            <strong>#{{ slugTarget?.slug }}</strong> yang sudah dibagikan ke kandidat akan berhenti berfungsi.
            Lanjutkan?
        </ConfirmModal>

        <transition name="wca-toast">
            <div v-if="toast" class="wca-toast"><i class="bi bi-check-circle-fill"></i> {{ toast }}</div>
        </transition>
    </div>
</template>

<script>
import axios from 'axios';
import { Head } from '@inertiajs/vue3';
import AdminModal from '@career/AdminModal.vue';
import ConfirmModal from '@career/ConfirmModal.vue';
import IconPicker from '@career/IconPicker.vue';
import EditorQuill from '@career/EditorQuill.vue';
import { ingatModal } from '@utils/ingatModal';

const API = '/api/v1/master-faq';
const API_KAT = '/api/v1/master-faq-kategori';
const CFG = { headers: { Accept: 'application/json' } };

const formKosong = () => ({
    kategoriId: '',
    ikon: '',
    pertanyaan: '',
    jawabanRingkas: '',
    jawabanDetail: '',
    urutan: 0,
    aktif: true,
    tampilLanding: false,
});

const formKatKosong = () => ({ nama: '', ikon: '', deskripsi: '', urutan: 0, aktif: true });

export default {
    components: { Head, AdminModal, ConfirmModal, IconPicker, EditorQuill },
    // Modal di halaman ini selamat dari refresh — lihat @utils/ingatModal.
    mixins: [ingatModal('admin/master-faq/masterFaq')],
    data() {
        return {
            tab: 'faq',
            list: [],
            kategori: [],
            loading: false,
            loadingKat: false,
            q: '',
            katFilter: '',
            statusFilter: '',
            groupView: true,

            busyMap: {},
            busyKatMap: {},
            flashMap: {},
            flashKatMap: {},

            show: false,
            editingId: null,
            form: formKosong(),
            saving: false,

            showKat: false,
            editingKatId: null,
            formKat: formKatKosong(),
            savingKat: false,

            delShow: false,
            delTarget: null,
            deleting: false,

            delKatShow: false,
            delKatTarget: null,
            deletingKat: false,

            slugShow: false,
            slugTarget: null,
            slugBusy: false,

            toast: '',
            tm: null,
        };
    },
    computed: {
        totalLanding() {
            return this.list.filter((f) => f.tampilLanding).length;
        },
        totalAktif() {
            return this.list.filter((f) => f.aktif).length;
        },
        totalDraft() {
            return this.list.filter((f) => !f.aktif).length;
        },
        totalMembantu() {
            return this.list.reduce((sum, f) => sum + Number(f.membantu || 0), 0);
        },
        filtered() {
            const s = this.q.trim().toLowerCase();
            let items = this.list;

            if (this.katFilter) {
                if (this.katFilter === 'tanpa-kategori') {
                    items = items.filter((f) => !f.kategoriId);
                } else {
                    items = items.filter((f) => String(f.kategoriId) === String(this.katFilter));
                }
            }

            if (this.statusFilter === 'aktif') {
                items = items.filter((f) => f.aktif);
            } else if (this.statusFilter === 'landing') {
                items = items.filter((f) => f.tampilLanding);
            } else if (this.statusFilter === 'draft') {
                items = items.filter((f) => !f.aktif);
            }

            if (s) {
                items = items.filter((f) =>
                    [f.pertanyaan, f.jawabanRingkas, f.slug, f.kategoriNama]
                        .filter(Boolean)
                        .join(' ')
                        .toLowerCase()
                        .includes(s),
                );
            }

            return items;
        },
        groupedFiltered() {
            const items = this.filtered;
            const groups = [];

            for (const k of this.kategori) {
                const katItems = items.filter((f) => String(f.kategoriId) === String(k.id));
                if (katItems.length > 0 || !this.q) {
                    groups.push({
                        katId: k.id,
                        nama: k.nama,
                        ikon: k.ikon,
                        deskripsi: k.deskripsi,
                        items: katItems,
                    });
                }
            }

            const uncategorized = items.filter((f) => !f.kategoriId);
            if (uncategorized.length > 0) {
                groups.push({
                    katId: null,
                    nama: 'Tanpa Kategori',
                    ikon: 'bi-folder-symlink-fill',
                    deskripsi: 'Pertanyaan yang belum dimasukkan ke kategori manapun.',
                    items: uncategorized,
                });
            }

            return groups.filter((g) => g.items.length > 0);
        },
        kategoriAktif() {
            return this.kategori.filter((k) => k.aktif);
        },
    },
    mounted() {
        this.load();
        this.loadKategori();
    },
    methods: {
        jumlahFaqByKat(katId) {
            if (!katId) return this.list.length;
            if (katId === 'tanpa-kategori') {
                return this.list.filter((f) => !f.kategoriId).length;
            }
            return this.list.filter((f) => String(f.kategoriId) === String(katId)).length;
        },
        resetFilter() {
            this.q = '';
            this.katFilter = '';
            this.statusFilter = '';
        },
        // ── Muat data ──
        async load() {
            this.loading = true;
            try {
                const res = await axios.get(API, CFG);
                this.list = res.data.result || [];
            } catch (e) {
                this.notice('Gagal memuat data FAQ.');
            } finally {
                this.loading = false;
            }
        },
        async loadKategori() {
            this.loadingKat = true;
            try {
                const res = await axios.get(API_KAT, CFG);
                this.kategori = res.data.result || [];
            } catch (e) {
                this.notice('Gagal memuat kategori FAQ.');
            } finally {
                this.loadingKat = false;
            }
        },

        // ── Pertanyaan: modal ──
        openCreate() {
            this.editingId = null;
            this.form = formKosong();
            this.show = true;
        },
        openEdit(f) {
            this.editingId = f.id;
            this.form = {
                kategoriId: f.kategoriId || '',
                ikon: f.ikon || '',
                pertanyaan: f.pertanyaan,
                jawabanRingkas: f.jawabanRingkas || '',
                jawabanDetail: f.jawabanDetail || '',
                urutan: f.urutan || 0,
                aktif: f.aktif,
                tampilLanding: f.tampilLanding,
            };
            this.show = true;
        },
        async save() {
            if (this.saving) return;
            if (!this.form.pertanyaan.trim()) return this.notice('Pertanyaan wajib diisi.');
            if (!this.form.jawabanRingkas.trim()) return this.notice('Jawaban ringkas wajib diisi.');

            this.saving = true;
            const payload = {
                kategoriId: this.form.kategoriId || null,
                ikon: this.form.ikon || null,
                pertanyaan: this.form.pertanyaan,
                jawabanRingkas: this.form.jawabanRingkas,
                jawabanDetail: this.form.jawabanDetail || null,
                urutan: Number(this.form.urutan) || 0,
                aktif: this.form.aktif,
                tampilLanding: this.form.tampilLanding,
            };

            try {
                if (this.editingId) {
                    await axios.put(`${API}/${this.editingId}`, payload, CFG);
                    this.notice('Pertanyaan diperbarui.');
                } else {
                    await axios.post(API, payload, CFG);
                    this.notice('Pertanyaan ditambahkan.');
                }
                this.show = false;
                await Promise.all([this.load(), this.loadKategori()]);
            } catch (e) {
                this.notice(e.response?.data?.message || 'Gagal menyimpan.');
            } finally {
                this.saving = false;
            }
        },

        // ── Pertanyaan: switch ──
        async setStatus(f, v) {
            const prev = f.aktif;
            f.aktif = v;
            this.busyMap = { ...this.busyMap, [f.id]: 'status' };
            try {
                await axios.patch(`${API}/${f.id}/toggle`, { aktif: v }, CFG);
                this.flashMap = { ...this.flashMap, [f.id]: 'status' };
                setTimeout(() => {
                    delete this.flashMap[f.id];
                    this.flashMap = { ...this.flashMap };
                }, 1200);
                this.notice(`Pertanyaan ${v ? 'diaktifkan' : 'dinonaktifkan'}.`);
            } catch (e) {
                f.aktif = prev;
                this.notice('Gagal mengubah status.');
            } finally {
                delete this.busyMap[f.id];
                this.busyMap = { ...this.busyMap };
            }
        },
        async setLanding(f, v) {
            const prev = f.tampilLanding;
            f.tampilLanding = v;
            this.busyMap = { ...this.busyMap, [f.id]: 'landing' };
            try {
                await axios.patch(`${API}/${f.id}/landing`, { tampil: v }, CFG);
                this.flashMap = { ...this.flashMap, [f.id]: 'landing' };
                setTimeout(() => {
                    delete this.flashMap[f.id];
                    this.flashMap = { ...this.flashMap };
                }, 1200);
                this.notice(v ? 'Ditampilkan di landing page.' : 'Disembunyikan dari landing page.');
            } catch (e) {
                f.tampilLanding = prev;
                this.notice('Gagal mengubah tampilan landing.');
            } finally {
                delete this.busyMap[f.id];
                this.busyMap = { ...this.busyMap };
            }
        },

        // ── Pertanyaan: urutan ──
        posisiSekategori(f) {
            if (!f) return 1;
            const katId = f.kategoriId || null;
            const sekategori = this.list.filter((x) => (x.kategoriId || null) === katId);
            const idx = sekategori.findIndex((x) => x.id === f.id);
            return idx >= 0 ? idx + 1 : 1;
        },
        totalSekategori(f) {
            if (!f) return 1;
            const katId = f.kategoriId || null;
            return this.list.filter((x) => (x.kategoriId || null) === katId).length;
        },
        pesanNaik(i) {
            const f = this.filtered[i];
            if (!f) return 'Naikkan urutan';
            if (this.q) return 'Matikan pencarian untuk mengubah urutan';
            if (this.posisiSekategori(f) === 1) {
                return `Sudah berada di urutan teratas (#1) dalam kategori "${f.kategoriNama || 'Tanpa kategori'}"`;
            }
            return `Naikkan urutan dalam kategori "${f.kategoriNama || 'Tanpa kategori'}"`;
        },
        pesanTurun(i) {
            const f = this.filtered[i];
            if (!f) return 'Turunkan urutan';
            if (this.q) return 'Matikan pencarian untuk mengubah urutan';
            if (this.posisiSekategori(f) === this.totalSekategori(f)) {
                return `Sudah berada di urutan terbawah dalam kategori "${f.kategoriNama || 'Tanpa kategori'}"`;
            }
            return `Turunkan urutan dalam kategori "${f.kategoriNama || 'Tanpa kategori'}"`;
        },
        tetanggaSekategori(i, arah) {
            const kini = this.filtered[i];
            const calon = this.filtered[i + arah];
            if (!kini || !calon) return null;

            return (kini.kategoriId || null) === (calon.kategoriId || null) ? calon : null;
        },
        bisaNaik(i) {
            return !this.q && !!this.tetanggaSekategori(i, -1);
        },
        bisaTurun(i) {
            return !this.q && !!this.tetanggaSekategori(i, 1);
        },
        async pindahGroup(grp, idx, arah) {
            const targetIdx = idx + arah;
            if (targetIdx < 0 || targetIdx >= grp.items.length) return;

            const itemA = grp.items[idx];
            const itemB = grp.items[targetIdx];

            const urut = [...this.list];
            const iAsli = urut.findIndex((x) => x.id === itemA.id);
            const jAsli = urut.findIndex((x) => x.id === itemB.id);
            if (iAsli < 0 || jAsli < 0) return;

            this.busyMap = { ...this.busyMap, [itemA.id]: 'reorder', [itemB.id]: 'reorder' };
            [urut[iAsli], urut[jAsli]] = [urut[jAsli], urut[iAsli]];
            this.list = urut;

            try {
                await axios.post(`${API}/reorder`, { ids: urut.map((x) => x.id) }, CFG);
                this.notice('Urutan pertanyaan berhasil diperbarui.');
                await this.load();
            } catch (e) {
                this.notice('Gagal menyimpan urutan.');
                await this.load();
            } finally {
                delete this.busyMap[itemA.id];
                delete this.busyMap[itemB.id];
                this.busyMap = { ...this.busyMap };
            }
        },
        async pindah(i, arah) {
            const calon = this.tetanggaSekategori(i, arah);
            if (!calon) return;

            const urut = [...this.list];
            const itemA = this.filtered[i];
            const itemB = calon;
            const iAsli = urut.findIndex((x) => x.id === itemA.id);
            const jAsli = urut.findIndex((x) => x.id === itemB.id);
            if (iAsli < 0 || jAsli < 0) return;

            this.busyMap = { ...this.busyMap, [itemA.id]: 'reorder', [itemB.id]: 'reorder' };
            [urut[iAsli], urut[jAsli]] = [urut[jAsli], urut[iAsli]];
            this.list = urut;

            try {
                await axios.post(`${API}/reorder`, { ids: urut.map((x) => x.id) }, CFG);
                this.notice('Urutan pertanyaan berhasil diperbarui.');
                await this.load();
            } catch (e) {
                this.notice('Gagal menyimpan urutan.');
                await this.load();
            } finally {
                delete this.busyMap[itemA.id];
                delete this.busyMap[itemB.id];
                this.busyMap = { ...this.busyMap };
            }
        },

        // ── Pertanyaan: slug & hapus ──
        askSlug(f) {
            this.slugTarget = f;
            this.slugShow = true;
        },
        async confirmSlug() {
            if (this.slugBusy || !this.slugTarget) return;
            this.slugBusy = true;
            try {
                await axios.patch(`${API}/${this.slugTarget.id}/slug`, {}, CFG);
                this.notice('Slug diperbarui.');
                this.slugShow = false;
                this.slugTarget = null;
                await this.load();
            } catch (e) {
                this.notice(e.response?.data?.message || 'Gagal memperbarui slug.');
            } finally {
                this.slugBusy = false;
            }
        },
        askRemove(f) {
            this.delTarget = f;
            this.delShow = true;
        },
        async confirmDelete() {
            if (this.deleting || !this.delTarget) return;
            this.deleting = true;
            try {
                await axios.delete(`${API}/${this.delTarget.id}`, CFG);
                this.notice('Pertanyaan dihapus.');
                this.delShow = false;
                this.delTarget = null;
                await Promise.all([this.load(), this.loadKategori()]);
            } catch (e) {
                this.notice(e.response?.data?.message || 'Gagal menghapus.');
            } finally {
                this.deleting = false;
            }
        },

        // ── Kategori ──
        openCreateKat() {
            this.editingKatId = null;
            this.formKat = formKatKosong();
            this.showKat = true;
        },
        openEditKat(k) {
            this.editingKatId = k.id;
            this.formKat = {
                nama: k.nama,
                ikon: k.ikon || '',
                deskripsi: k.deskripsi || '',
                urutan: k.urutan || 0,
                aktif: k.aktif,
            };
            this.showKat = true;
        },
        async saveKat() {
            if (this.savingKat) return;
            if (!this.formKat.nama.trim()) return this.notice('Nama kategori wajib diisi.');

            this.savingKat = true;
            const payload = {
                nama: this.formKat.nama,
                ikon: this.formKat.ikon || null,
                deskripsi: this.formKat.deskripsi || null,
                urutan: Number(this.formKat.urutan) || 0,
                aktif: this.formKat.aktif,
            };

            try {
                if (this.editingKatId) {
                    await axios.put(`${API_KAT}/${this.editingKatId}`, payload, CFG);
                    this.notice('Kategori diperbarui.');
                } else {
                    await axios.post(API_KAT, payload, CFG);
                    this.notice('Kategori ditambahkan.');
                }
                this.showKat = false;
                await Promise.all([this.loadKategori(), this.load()]);
            } catch (e) {
                this.notice(e.response?.data?.message || 'Gagal menyimpan.');
            } finally {
                this.savingKat = false;
            }
        },
        async setStatusKat(k, v) {
            const prev = k.aktif;
            k.aktif = v;
            this.busyKatMap = { ...this.busyKatMap, [k.id]: 'status' };
            try {
                await axios.patch(`${API_KAT}/${k.id}/toggle`, { aktif: v }, CFG);
                this.flashKatMap = { ...this.flashKatMap, [k.id]: 'status' };
                setTimeout(() => {
                    delete this.flashKatMap[k.id];
                    this.flashKatMap = { ...this.flashKatMap };
                }, 1200);
                this.notice(`Kategori "${k.nama}" ${v ? 'diaktifkan' : 'dinonaktifkan'}.`);
                await this.load();
            } catch (e) {
                k.aktif = prev;
                this.notice('Gagal mengubah status.');
            } finally {
                delete this.busyKatMap[k.id];
                this.busyKatMap = { ...this.busyKatMap };
            }
        },
        askRemoveKat(k) {
            this.delKatTarget = k;
            this.delKatShow = true;
        },
        async confirmDeleteKat() {
            if (this.deletingKat || !this.delKatTarget) return;
            this.deletingKat = true;
            try {
                await axios.delete(`${API_KAT}/${this.delKatTarget.id}`, CFG);
                this.notice('Kategori dihapus.');
                this.delKatShow = false;
                this.delKatTarget = null;
                await this.loadKategori();
            } catch (e) {
                this.notice(e.response?.data?.message || 'Gagal menghapus.');
            } finally {
                this.deletingKat = false;
            }
        },

        // ── Pembantu ──
        potong(t, n = 110) {
            const s = (t || '').trim();
            return s.length > n ? s.slice(0, n) + '…' : s;
        },
        notice(x) {
            this.toast = x;
            if (this.tm) clearTimeout(this.tm);
            this.tm = setTimeout(() => (this.toast = ''), 3000);
        },
    },
};
</script>

<style scoped>
.mf-req {
    color: #ef4444;
    font-weight: 700;
    margin-left: 2px;
}
.mf-subtle {
    color: #94a3b8;
    font-size: 0.72rem;
    font-weight: 600;
}
.mf-help-text {
    display: block;
    margin-top: 0.35rem;
    color: #64748b;
    font-size: 0.75rem;
    font-weight: 500;
}

/* Modal tip box */
.mf-modal-tip {
    display: flex;
    align-items: flex-start;
    gap: 0.85rem;
    padding: 0.85rem 1rem;
    margin-bottom: 1.1rem;
    border-radius: 0.75rem;
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.06) 0%, rgba(124, 58, 237, 0.08) 100%);
    border: 1px solid rgba(99, 102, 241, 0.18);
}
.mf-modal-tip__icon {
    display: grid;
    place-items: center;
    width: 2.1rem;
    height: 2.1rem;
    flex: none;
    border-radius: 0.6rem;
    background: #6366f1;
    color: #fff;
    font-size: 1rem;
    box-shadow: 0 4px 10px rgba(99, 102, 241, 0.25);
}
.mf-modal-tip__content {
    font-size: 0.8rem;
    color: #334155;
    line-height: 1.45;
}
.mf-modal-tip__content strong {
    display: block;
    color: #1e293b;
    font-size: 0.82rem;
    margin-bottom: 0.15rem;
}
.mf-modal-tip__content p {
    margin: 0;
}
.mf-modal-tip__content code {
    background: rgba(99, 102, 241, 0.12);
    color: #4f46e5;
    padding: 0.1rem 0.35rem;
    border-radius: 0.25rem;
    font-family: inherit;
    font-size: 0.76rem;
    font-weight: 700;
}

/* Switch grid & cards */
.mf-switch-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 0.85rem;
}
@media (max-width: 640px) {
    .mf-switch-grid {
        grid-template-columns: 1fr;
    }
}
.mf-switch-card {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    padding: 0.85rem 1rem;
    border-radius: 0.75rem;
    background: #ffffff;
    border: 1.5px solid var(--line, #e2e8f0);
    cursor: pointer;
    user-select: none;
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}
.mf-switch-card:hover {
    border-color: #cbd5e1;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.03);
}
.mf-switch-card.is-active {
    background: #f8fafc;
    border-color: rgba(99, 102, 241, 0.4);
    box-shadow: 0 4px 14px rgba(99, 102, 241, 0.08);
}
.mf-switch-card__icon {
    display: grid;
    place-items: center;
    width: 2.2rem;
    height: 2.2rem;
    flex: none;
    border-radius: 0.6rem;
    font-size: 1rem;
}
.mf-icon--amber {
    background: rgba(245, 158, 11, 0.12);
    color: #d97706;
}
.mf-icon--emerald {
    background: rgba(16, 185, 129, 0.12);
    color: #059669;
}
.mf-switch-card__body {
    flex: 1;
    min-width: 0;
}
.mf-switch-card__title {
    font-size: 0.82rem;
    font-weight: 700;
    color: #1e293b;
    line-height: 1.25;
}
.mf-switch-card__desc {
    font-size: 0.73rem;
    color: #64748b;
    font-weight: 500;
    margin-top: 0.15rem;
    line-height: 1.3;
}

.mf-switch-cell {
    position: relative;
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
}
.mf-mini-spin {
    width: 14px;
    height: 14px;
    border-width: 2px;
    border-color: #6366f1;
    border-top-color: transparent;
}
.mf-btn-spin {
    width: 12px;
    height: 12px;
    border-width: 2px;
    border-color: #4f46e5;
    border-top-color: transparent;
}

/* Row animations & busy feedback */
.wca-table tbody tr {
    transition: background-color 0.4s ease, transform 0.3s cubic-bezier(0.16, 1, 0.3, 1), opacity 0.3s ease;
}
.mf-row-busy {
    opacity: 0.75;
    background-color: rgba(99, 102, 241, 0.06) !important;
}
.mf-row-updated {
    animation: mfPulseGreen 1.3s cubic-bezier(0.16, 1, 0.3, 1);
}
@keyframes mfPulseGreen {
    0% {
        background-color: rgba(16, 185, 129, 0.25);
    }
    100% {
        background-color: transparent;
    }
}
.mf-row-landing-flash {
    animation: mfPulseAmber 1.3s cubic-bezier(0.16, 1, 0.3, 1);
}
@keyframes mfPulseAmber {
    0% {
        background-color: rgba(245, 158, 11, 0.25);
    }
    100% {
        background-color: transparent;
    }
}
.mf-row-swapping {
    animation: mfSwapRow 0.6s cubic-bezier(0.16, 1, 0.3, 1);
}
@keyframes mfSwapRow {
    0% {
        background-color: rgba(99, 102, 241, 0.22);
        transform: scale(0.99);
    }
    50% {
        background-color: rgba(99, 102, 241, 0.1);
    }
    100% {
        background-color: transparent;
        transform: scale(1);
    }
}

.mf-th-sub {
    display: block;
    font-size: 0.68rem;
    font-weight: 600;
    color: #64748b;
    text-transform: none;
    letter-spacing: normal;
    margin-top: 0.1rem;
}
.mf-urut-cell {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.mf-pos-badge {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    padding: 0.25rem 0.5rem;
    border-radius: 0.5rem;
    background: rgba(99, 102, 241, 0.08);
    border: 1px solid rgba(99, 102, 241, 0.2);
    min-width: 58px;
    cursor: help;
}
.mf-pos-badge__num {
    font-size: 0.82rem;
    font-weight: 800;
    color: #4f46e5;
    line-height: 1.1;
}
.mf-pos-badge__sub {
    font-size: 0.65rem;
    font-weight: 700;
    color: #64748b;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 65px;
}
.mf-urut-btns {
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
}

.mf-urut {
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
}
.mf-ikon {
    display: grid;
    place-items: center;
    width: 2rem;
    height: 2rem;
    flex: none;
    border-radius: 0.6rem;
    background: rgba(99, 102, 241, 0.1);
    color: #6366f1;
    font-size: 0.95rem;
}
.mf-slug {
    display: inline-flex;
    align-items: center;
    gap: 0.15rem;
    margin-top: 0.2rem;
    color: var(--muted, #64748b);
    font-size: 0.72rem;
    font-weight: 700;
    text-decoration: none;
}
.mf-slug:hover {
    color: #4f46e5;
    text-decoration: underline;
}
.mf-warn {
    margin-top: 0.3rem;
    color: #b45309;
    font-size: 0.72rem;
    font-weight: 700;
}
.mf-stat {
    display: flex;
    flex-wrap: wrap;
    gap: 0.55rem;
    color: var(--muted, #64748b);
    font-size: 0.78rem;
    font-weight: 700;
}
/* KPI Summary Cards */
.mf-kpi-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 1rem;
    margin-bottom: 1.25rem;
}
@media (max-width: 900px) {
    .mf-kpi-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}
@media (max-width: 500px) {
    .mf-kpi-grid {
        grid-template-columns: 1fr;
    }
}
.mf-kpi-card {
    display: flex;
    align-items: center;
    gap: 0.85rem;
    padding: 1rem 1.15rem;
    border-radius: 0.95rem;
    background: #ffffff;
    border: 1.5px solid var(--line, #e2e8f0);
    box-shadow: 0 4px 14px rgba(15, 23, 42, 0.03);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}
.mf-kpi-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(15, 23, 42, 0.06);
}
.mf-kpi-card__icon {
    display: grid;
    place-items: center;
    width: 2.75rem;
    height: 2.75rem;
    flex: none;
    border-radius: 0.75rem;
    font-size: 1.25rem;
}
.mf-kpi--indigo .mf-kpi-card__icon {
    background: rgba(99, 102, 241, 0.12);
    color: #4f46e5;
}
.mf-kpi--purple .mf-kpi-card__icon {
    background: rgba(168, 85, 247, 0.12);
    color: #9333ea;
}
.mf-kpi--amber .mf-kpi-card__icon {
    background: rgba(245, 158, 11, 0.12);
    color: #d97706;
}
.mf-kpi--emerald .mf-kpi-card__icon {
    background: rgba(16, 185, 129, 0.12);
    color: #059669;
}
.mf-kpi-card__info {
    display: flex;
    flex-direction: column;
}
.mf-kpi-card__val {
    font-size: 1.35rem;
    font-weight: 900;
    color: #1e293b;
    line-height: 1.1;
}
.mf-kpi-card__lbl {
    font-size: 0.76rem;
    font-weight: 600;
    color: #64748b;
    margin-top: 0.15rem;
}

/* Toolbar & Group View */
.mf-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    flex-wrap: wrap;
    margin-bottom: 1.25rem;
}
.mf-toolbar__search {
    flex: 1;
    min-width: 240px;
}
.mf-toolbar__right {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}
.mf-view-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    padding: 0.55rem 0.95rem;
    border-radius: 0.6rem;
    border: 1.5px solid var(--line, #e2e8f0);
    background: #ffffff;
    color: #475569;
    font-size: 0.82rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.18s ease;
}
.mf-view-btn:hover,
.mf-view-btn.is-active {
    border-color: #6366f1;
    background: rgba(99, 102, 241, 0.08);
    color: #4f46e5;
}

.mf-grouped-list {
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
}
.mf-group-card {
    border-radius: 0.95rem;
    overflow: hidden;
}
.mf-group-card__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1rem 1.25rem;
    background: #f8fafc;
    border-bottom: 1px solid var(--line, #e2e8f0);
}
.mf-group-card__title {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}
.mf-group-card__title h3 {
    margin: 0;
    font-size: 1rem;
    font-weight: 800;
    color: #1e293b;
}
.mf-group-card__desc {
    font-size: 0.76rem;
    color: #64748b;
    font-weight: 500;
}

/* Filter & Control Panel */
.mf-filter-panel {
    background: #ffffff;
    border: 1.5px solid var(--line, #e2e8f0);
    border-radius: 1rem;
    padding: 1.1rem 1.25rem;
    margin-bottom: 1.25rem;
    box-shadow: 0 4px 16px rgba(15, 23, 42, 0.03);
    display: flex;
    flex-direction: column;
    gap: 0.9rem;
}
.mf-fp-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    flex-wrap: wrap;
}
.mf-fp-search {
    position: relative;
    flex: 1;
    min-width: 260px;
    display: flex;
    align-items: center;
}
.mf-fps-icon {
    position: absolute;
    left: 0.95rem;
    color: #94a3b8;
    font-size: 1rem;
}
.mf-fps-input {
    width: 100%;
    padding: 0.65rem 2.5rem 0.65rem 2.6rem;
    border-radius: 0.75rem;
    border: 1.5px solid #e2e8f0;
    background: #f8fafc;
    font-size: 0.85rem;
    font-weight: 600;
    color: #1e293b;
    outline: none;
    transition: all 0.2s ease;
}
.mf-fps-input:focus {
    border-color: #6366f1;
    background: #ffffff;
    box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.12);
}
.mf-fps-clear {
    position: absolute;
    right: 0.75rem;
    border: none;
    background: transparent;
    color: #94a3b8;
    cursor: pointer;
    font-size: 1rem;
    padding: 0;
    display: grid;
    place-items: center;
}
.mf-fps-clear:hover {
    color: #64748b;
}

.mf-fp-status {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    flex-wrap: wrap;
}
.mf-st-pill {
    padding: 0.45rem 0.85rem;
    border-radius: 0.65rem;
    border: 1.5px solid #e2e8f0;
    background: #ffffff;
    color: #64748b;
    font-size: 0.78rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.18s ease;
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
}
.mf-st-pill:hover {
    border-color: #cbd5e1;
    color: #334155;
}
.mf-st-pill.is-active {
    background: #f1f5f9;
    border-color: #94a3b8;
    color: #0f172a;
}
.mf-st-pill--landing.is-active {
    background: rgba(245, 158, 11, 0.12);
    border-color: rgba(245, 158, 11, 0.4);
    color: #d97706;
}
.mf-st-pill--active.is-active {
    background: rgba(16, 185, 129, 0.12);
    border-color: rgba(16, 185, 129, 0.4);
    color: #059669;
}
.mf-st-pill--draft.is-active {
    background: rgba(239, 68, 68, 0.12);
    border-color: rgba(239, 68, 68, 0.4);
    color: #dc2626;
}

.mf-fp-view {
    display: flex;
    align-items: center;
}
.mf-view-toggle {
    display: inline-flex;
    padding: 0.2rem;
    background: #f1f5f9;
    border-radius: 0.75rem;
    border: 1px solid #e2e8f0;
}
.mf-vt-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.4rem 0.75rem;
    border-radius: 0.6rem;
    border: none;
    background: transparent;
    color: #64748b;
    font-size: 0.78rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.18s ease;
}
.mf-vt-btn:hover {
    color: #334155;
}
.mf-vt-btn.is-active {
    background: #ffffff;
    color: #4f46e5;
    box-shadow: 0 2px 8px rgba(15, 23, 42, 0.08);
}

.mf-fp-bottom {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding-top: 0.75rem;
    border-top: 1px dashed #e2e8f0;
}
.mf-fp-label {
    font-size: 0.76rem;
    font-weight: 700;
    color: #64748b;
    white-space: nowrap;
    display: flex;
    align-items: center;
    gap: 0.3rem;
}
.mf-chips-scroll {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    overflow-x: auto;
    padding-bottom: 0.2rem;
    scrollbar-width: thin;
}
.mf-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.35rem 0.75rem;
    border-radius: 2rem;
    border: 1.5px solid #e2e8f0;
    background: #ffffff;
    color: #475569;
    font-size: 0.78rem;
    font-weight: 700;
    cursor: pointer;
    white-space: nowrap;
    transition: all 0.18s ease;
}
.mf-chip:hover {
    border-color: #6366f1;
    color: #4f46e5;
}
.mf-chip.is-active {
    background: #4f46e5;
    border-color: #4f46e5;
    color: #ffffff;
    box-shadow: 0 4px 12px rgba(79, 70, 229, 0.25);
}
.mf-chip__cnt {
    display: inline-grid;
    place-items: center;
    padding: 0.05rem 0.4rem;
    border-radius: 1rem;
    background: rgba(15, 23, 42, 0.08);
    color: inherit;
    font-size: 0.7rem;
    font-weight: 800;
}
.mf-chip.is-active .mf-chip__cnt {
    background: rgba(255, 255, 255, 0.25);
    color: #ffffff;
}
.mf-chip-reset {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    padding: 0.35rem 0.75rem;
    border-radius: 2rem;
    border: 1.5px solid rgba(239, 68, 68, 0.3);
    background: rgba(239, 68, 68, 0.06);
    color: #dc2626;
    font-size: 0.76rem;
    font-weight: 700;
    cursor: pointer;
    white-space: nowrap;
    transition: all 0.18s ease;
}
.mf-chip-reset:hover {
    background: #dc2626;
    color: #ffffff;
    border-color: #dc2626;
}

/* Live Category Preview */
.mf-kat-preview {
    margin-bottom: 1.25rem;
    padding: 0.85rem 1rem;
    border-radius: 0.85rem;
    background: #f8fafc;
    border: 1.5px dashed #cbd5e1;
}
.mf-kat-preview__lbl {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.72rem;
    font-weight: 800;
    color: #4f46e5;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    margin-bottom: 0.6rem;
}
.mf-kat-preview__card {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    padding: 0.75rem 0.95rem;
    border-radius: 0.75rem;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    box-shadow: 0 2px 8px rgba(15, 23, 42, 0.04);
}
.mf-kat-preview__title {
    font-size: 0.9rem;
    font-weight: 800;
    color: #1e293b;
    display: block;
    line-height: 1.25;
}
.mf-kat-preview__desc {
    margin: 0.2rem 0 0 0;
    font-size: 0.78rem;
    color: #64748b;
    line-height: 1.35;
}

@media (max-width: 720px) {
    .wca-frow {
        grid-template-columns: 1fr;
    }
    .wca-tablewrap {
        overflow-x: auto;
    }
}
</style>
