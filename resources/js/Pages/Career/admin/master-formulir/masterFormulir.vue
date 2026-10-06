<!-- WEB CAREER - Master Formulir dinamis: builder schema + preview kandidat + management. -->
<template>
    <Head title="Master Formulir" />

    <div class="wca mfb-page">
        <!-- Header Utama (Native Web Career Modern Header) -->
        <div class="wca-phead wca-phead--modern mfb-phead">
            <div class="wca-phead__title-group">
                <div class="wca-phead__icon-badge mfb-icon-badge">
                    <i class="bi bi-input-cursor-text"></i>
                </div>
                <div>
                    <h1 class="wca-phead__title">Master Formulir</h1>
                    <p class="wca-phead__sub">
                        Kelola template formulir reusable. Pengikatan ke program dan tahapan seleksi dilakukan di modul
                        Master Tahapan Seleksi.
                    </p>
                </div>
            </div>

            <!-- Metrics Summary Pills -->
            <div class="mfb-metrics">
                <div class="mfb-metric-pill" title="Total Formulir">
                    <i class="bi bi-journal-text"></i>
                    <span
                        ><b>{{ list.length }}</b> Formulir</span
                    >
                </div>
                <div class="mfb-metric-pill mfb-metric-pill--published" title="Formulir Published">
                    <i class="bi bi-check-circle-fill"></i>
                    <span
                        ><b>{{ countPublished }}</b> Published</span
                    >
                </div>
                <div class="mfb-metric-pill mfb-metric-pill--draft" title="Draft / Belum Publish">
                    <i class="bi bi-pencil-fill"></i>
                    <span
                        ><b>{{ countDraft }}</b> Draft</span
                    >
                </div>
            </div>

            <!-- Action Bar -->
            <div class="wca-phead__actions mfb-phead__actions">
                <button class="mfb-btn mfb-btn--glow" type="button" @click="buatBaru">
                    <i class="bi bi-plus-lg"></i> Formulir Baru
                </button>

                <button class="mfb-btn mfb-btn--indigo" type="button" :disabled="!aktif || saving" :onClick="!aktif || saving ? null : simpanDraft">
                    <i class="bi" :class="saving ? 'bi-arrow-repeat mfb-spin' : 'bi-save'"></i>
                    {{ saving ? 'Menyimpan...' : 'Simpan Draft' }}
                </button>

                <button class="mfb-btn mfb-btn--soft" type="button" :disabled="!aktif || saving" :onClick="!aktif || saving ? null : reviewPublish">
                    <i class="bi bi-git"></i> Review &amp; Compare
                    <span v-if="perubahanVersi.length" class="mfb-btn-badge">{{ perubahanVersi.length }}</span>
                </button>

                <button
                    class="mfb-btn mfb-btn--emerald"
                    type="button"
                    :disabled="!aktif || saving"
                    :onClick="!aktif || saving ? null : () => publish(false)"
                >
                    <i class="bi bi-send-check-fill"></i> Publish Versi
                </button>
            </div>
        </div>

        <!-- Main Layout Grid -->
        <div class="mfb-layout">
            <!-- Sidebar: Catalog / Form List -->
            <aside class="mfb-sidebar">
                <div class="mfb-sidebar__title">
                    <span><i class="bi bi-journal-album"></i> Katalog Formulir</span>
                    <span class="mfb-sidebar__count">{{ tersaring.length }}</span>
                </div>

                <div class="mfb-sidebar__head">
                    <div class="mfb-search">
                        <i class="bi bi-search mfb-search__ico"></i>
                        <input
                            v-model="cari"
                            type="text"
                            placeholder="Cari nama, kode, kategori..."
                            class="mfb-search__input"
                        />
                        <button v-if="cari" type="button" class="mfb-search__clear" @click="cari = ''">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>

                    <!-- Filter Status Chips -->
                    <div class="mfb-filter-chips">
                        <button
                            v-for="chip in filterChips"
                            :key="chip.value"
                            type="button"
                            class="mfb-chip"
                            :class="{ active: statusFilter === chip.value }"
                            @click="statusFilter = chip.value"
                        >
                            {{ chip.label }}
                        </button>
                    </div>
                </div>

                <!-- Catalog Cards -->
                <div class="mfb-sidebar__list">
                    <div
                        v-for="f in tersaring"
                        :key="f.id"
                        class="mfb-card"
                        :class="{ active: aktif?.id === f.id, off: f.status !== 'AKTIF' }"
                        @click="pilih(f)"
                    >
                        <div class="mfb-card__head">
                            <div class="mfb-card__titlegroup">
                                <strong class="mfb-card__title">{{ f.nama }}</strong>
                                <span class="mfb-card__code">{{ f.kode }}</span>
                            </div>
                            <span v-if="f.kategori" class="mfb-card__cat">{{ f.kategori }}</span>
                        </div>

                        <p class="mfb-card__description">
                            {{ f.deskripsi || 'Template formulir reusable tanpa pengikatan program.' }}
                        </p>
                        <div class="mfb-card__meta">
                            <div class="mfb-card__badges">
                                <span class="mfb-card__pub" :class="f.published ? 'is-pub' : 'is-draft'">
                                    <i class="bi" :class="f.published ? 'bi-check-circle-fill' : 'bi-dash-circle'"></i>
                                    {{ f.published ? `v${f.published.versi} Published` : 'Belum Publish' }}
                                </span>
                                <span v-if="f.draft && !f.published" class="mfb-card__version">
                                    Draft v{{ f.draft.versi }}
                                </span>

                                <span
                                    class="mfb-card__status"
                                    :class="f.status === 'AKTIF' ? 'is-active' : 'is-inactive'"
                                >
                                    {{ f.status }}
                                </span>
                            </div>

                            <!-- Quick Action Buttons -->
                            <div class="mfb-card__actions" @click.stop>
                                <button
                                    type="button"
                                    class="mfb-iconbtn"
                                    title="Duplikat formulir ini"
                                    @click="mintaDuplikat(f)"
                                >
                                    <i class="bi bi-copy"></i>
                                </button>
                                <button
                                    type="button"
                                    class="mfb-iconbtn"
                                    :class="f.status === 'AKTIF' ? 'mfb-iconbtn--warn' : 'mfb-iconbtn--ok'"
                                    :title="f.status === 'AKTIF' ? 'Nonaktifkan' : 'Aktifkan'"
                                    @click="toggleStatus(f)"
                                >
                                    <i class="bi" :class="f.status === 'AKTIF' ? 'bi-toggle-on' : 'bi-toggle-off'"></i>
                                </button>
                                <button
                                    type="button"
                                    class="mfb-iconbtn mfb-iconbtn--danger"
                                    title="Hapus formulir"
                                    @click="mintaHapus(f)"
                                >
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Empty Search State -->
                    <div v-if="!tersaring.length" class="mfb-empty-search">
                        <i class="bi bi-folder-x"></i>
                        <p>Tidak ada formulir yang sesuai.</p>
                        <button
                            type="button"
                            class="mfb-mini"
                            @click="
                                cari = '';
                                statusFilter = 'SEMUA';
                            "
                        >
                            Reset Filter
                        </button>
                    </div>
                </div>
            </aside>

            <!-- Main Work Canvas -->
            <main class="mfb-work">
                <section v-if="aktif" class="mfb-panel">
                    <!-- Form Metadata Top Bar -->
                    <div class="mfb-meta-bar">
                        <div class="mfb-meta-bar__item mfb-meta-bar__title">
                            <label>Nama Formulir</label>
                            <el-input
                                v-model="meta.nama"
                                placeholder="Contoh: Formulir Pendaftaran Management Trainee"
                            />
                        </div>
                        <div class="mfb-meta-bar__item">
                            <label>Jenis / Kategori Formulir</label>
                            <RefSelect type="talent" v-model="meta.kategori" placeholder="Lintas kategori" clearable />
                        </div>
                        <div class="mfb-meta-bar__item">
                            <label>Konteks Penggunaan</label>
                            <el-select v-model="schema.konteks" style="width: 100%">
                                <el-option
                                    v-for="k in konteksOptions"
                                    :key="k.value"
                                    :value="k.value"
                                    :label="k.label"
                                />
                            </el-select>
                        </div>
                        <div class="mfb-meta-bar__item">
                            <label>Mode Layout</label>
                            <div class="mfb-segmented">
                                <button
                                    v-for="opt in layoutOptions"
                                    :key="opt.value"
                                    type="button"
                                    class="mfb-segmented__item"
                                    :class="{ active: schema.layout === opt.value }"
                                    @click="schema.layout = opt.value"
                                >
                                    {{ opt.label }}
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="mfb-master-notice">
                        <i class="bi bi-link-45deg"></i>
                        <div>
                            <strong>Master reusable, belum terikat ke program</strong>
                            <span
                                >Formulir ini dapat dipakai ulang. Pilih formulir dan versinya nanti dari Master Tahapan
                                Seleksi.</span
                            >
                        </div>
                    </div>

                    <!-- Description & Guidance Toggle -->
                    <details class="mfb-meta-extra">
                        <summary>
                            <i class="bi bi-info-circle"></i> Deskripsi &amp; Petunjuk Pengisian (Opsional)
                        </summary>
                        <div class="mfb-meta-extra__grid">
                            <div>
                                <label>Deskripsi Ringkas</label>
                                <el-input
                                    v-model="meta.deskripsi"
                                    type="textarea"
                                    :rows="2"
                                    placeholder="Penjelasan singkat tujuan formulir ini..."
                                />
                            </div>
                            <div>
                                <label>Petunjuk Khusus Kandidat</label>
                                <el-input
                                    v-model="meta.petunjuk"
                                    type="textarea"
                                    :rows="2"
                                    placeholder="Instruksi tambahan untuk kandidat saat mengisi..."
                                />
                            </div>
                        </div>
                    </details>

                    <!-- Workspace Navigation Tabs -->
                    <div class="mfb-tabs">
                        <button type="button" :class="{ active: tab === 'builder' }" @click="tab = 'builder'">
                            <i class="bi bi-tools"></i> Visual Builder
                        </button>
                        <button type="button" :class="{ active: tab === 'preview' }" @click="tab = 'preview'">
                            <i class="bi bi-phone-vibrate"></i> Responsive Preview
                        </button>
                        <button type="button" :class="{ active: tab === 'stats' }" @click="tab = 'stats'">
                            <i class="bi bi-bar-chart-steps"></i> Ringkasan &amp; Audit
                        </button>
                        <button type="button" :class="{ active: tab === 'json' }" @click="tab = 'json'">
                            <i class="bi bi-code-slash"></i> JSON Schema
                        </button>
                    </div>

                    <!-- TAB 1: VISUAL BUILDER -->
                    <div v-if="tab === 'builder'" class="mfb-builder">
                        <!-- Canvas Area -->
                        <section class="mfb-canvas">
                            <!-- Palette & Toolbar -->
                            <div class="mfb-toolbar">
                                <div class="mfb-toolbar__group">
                                    <button type="button" class="mfb-btn-tool mfb-btn-tool--step" @click="tambahStep">
                                        <i class="bi bi-plus-circle-fill"></i> Tambah Step
                                    </button>
                                    <button
                                        type="button"
                                        class="mfb-btn-tool mfb-btn-tool--section"
                                        :disabled="stepAktif < 0"
                                        :onClick="stepAktif < 0 ? null : tambahSection"
                                    >
                                        <i class="bi bi-layout-text-window-reverse"></i> Tambah Section
                                    </button>
                                </div>

                                <div class="mfb-toolbar__palette">
                                    <button
                                        v-for="p in fieldPalette"
                                        :key="p.value"
                                        type="button"
                                        class="mfb-palette-btn"
                                        :title="'Sisipkan ' + p.label"
                                        @click="tambahFieldPreset(p)"
                                    >
                                        <i class="bi" :class="p.icon"></i> {{ p.label }}
                                    </button>
                                </div>

                                <div class="mfb-toolbar__right">
                                    <button
                                        type="button"
                                        class="mfb-val-badge"
                                        :class="jumlahIssue ? 'has-issue' : 'is-valid'"
                                        @click="showValidation = !showValidation"
                                    >
                                        <i
                                            class="bi"
                                            :class="jumlahIssue ? 'bi-exclamation-triangle-fill' : 'bi-check2-circle'"
                                        ></i>
                                        <span>{{ jumlahIssue ? jumlahIssue + ' Isu Schema' : 'Schema Valid' }}</span>
                                    </button>
                                </div>
                            </div>

                            <!-- Stepper Container (Draggable Steps) -->
                            <draggable
                                v-model="schema.langkah"
                                item-key="kode"
                                handle=".mfb-drag-step"
                                class="mfb-steps-list"
                            >
                                <template #item="{ element: L, index: li }">
                                    <article
                                        class="mfb-step-card"
                                        :class="{ selected: pilihTarget?.tipe === 'step' && pilihTarget.index === li }"
                                    >
                                        <!-- Step Header -->
                                        <header class="mfb-step-card__head" @click="selectStep(li)">
                                            <span class="mfb-drag-step" title="Geser posisi step">
                                                <i class="bi bi-grip-vertical"></i>
                                            </span>
                                            <span class="mfb-step-num">Step {{ li + 1 }}</span>
                                            <!-- Step Icon Picker -->
                                            <div class="mfb-step-card__ico" @click.stop>
                                                <IconPicker
                                                    v-model="L.ikon"
                                                    :compact="true"
                                                    placeholder="Pilih Ikon Step"
                                                />
                                            </div>

                                            <input
                                                v-model="L.judul"
                                                class="mfb-step-card__input"
                                                placeholder="Judul Step (misal: Data Pribadi)"
                                                @focus="selectStep(li)"
                                            />

                                            <div class="mfb-step-card__act" @click.stop>
                                                <button type="button" title="Hapus step ini" @click="hapusStep(li)">
                                                    <i class="bi bi-trash3"></i>
                                                </button>
                                            </div>
                                        </header>

                                        <!-- Sections Container (Draggable Sections) -->
                                        <draggable
                                            v-model="L.bagian"
                                            item-key="judul"
                                            handle=".mfb-drag-sec"
                                            class="mfb-sections-list"
                                        >
                                            <template #item="{ element: B, index: bi }">
                                                <section
                                                    class="mfb-sec-card"
                                                    :class="{
                                                        selected:
                                                            pilihTarget?.tipe === 'section' &&
                                                            pilihTarget.li === li &&
                                                            pilihTarget.bi === bi,
                                                    }"
                                                >
                                                    <header class="mfb-sec-card__head" @click="selectSection(li, bi)">
                                                        <span class="mfb-drag-sec" title="Geser posisi section">
                                                            <i class="bi bi-grip-vertical"></i>
                                                        </span>
                                                        <input
                                                            v-model="B.judul"
                                                            class="mfb-sec-card__input"
                                                            placeholder="Judul Section (misal: Informasi Kontak)"
                                                            @focus="selectSection(li, bi)"
                                                        />
                                                        <span v-if="B.tampil_jika?.field" class="mfb-cond-badge">
                                                            <i class="bi bi-lightning-charge-fill"></i> Bersyarat
                                                        </span>

                                                        <div class="mfb-sec-card__act" @click.stop>
                                                            <button
                                                                type="button"
                                                                title="Duplikat section ini"
                                                                @click="duplikatSection(li, bi)"
                                                            >
                                                                <i class="bi bi-copy"></i>
                                                            </button>
                                                            <button
                                                                type="button"
                                                                title="Tambah field baru ke section"
                                                                @click="tambahField(li, bi)"
                                                            >
                                                                <i class="bi bi-plus-lg"></i> Field
                                                            </button>
                                                            <button
                                                                type="button"
                                                                title="Hapus section ini"
                                                                @click="hapusSection(li, bi)"
                                                            >
                                                                <i class="bi bi-trash"></i>
                                                            </button>
                                                        </div>
                                                    </header>

                                                    <!-- Fields Grid (Draggable Fields) -- `group` SAMA di semua section/step
                                                         supaya field bisa digeser lintas section maupun lintas step,
                                                         tidak lagi terkunci hanya di dalam section-nya sendiri. -->
                                                    <draggable
                                                        v-model="B.field"
                                                        item-key="field_id"
                                                        handle=".mfb-drag-field"
                                                        group="mfb-fields"
                                                        class="mfb-fields-grid"
                                                        @change="(e) => onFieldDragChange(e, li, bi)"
                                                    >
                                                        <template #item="{ element: F, index: fi }">
                                                            <div
                                                                class="mfb-field-card"
                                                                :class="{
                                                                    selected:
                                                                        pilihTarget?.tipe === 'field' &&
                                                                        pilihTarget.li === li &&
                                                                        pilihTarget.bi === bi &&
                                                                        pilihTarget.fi === fi,
                                                                    full:
                                                                        F.penuh || Number(F.lebar_persen || 33) >= 100,
                                                                    'has-error':
                                                                        showValidation && fieldIssues(F).length > 0,
                                                                }"
                                                                :style="gayaFieldAdmin(F)"
                                                                role="button"
                                                                tabindex="0"
                                                                @click="selectField(li, bi, fi)"
                                                                @keydown.enter="selectField(li, bi, fi)"
                                                            >
                                                                <div class="mfb-field-card__top">
                                                                    <span
                                                                        class="mfb-drag-field"
                                                                        title="Geser urutan field"
                                                                    >
                                                                        <i class="bi bi-grip-vertical"></i>
                                                                    </span>
                                                                    <i
                                                                        class="bi mfb-field-card__ico"
                                                                        :class="ikonField(F.tipe)"
                                                                    ></i>
                                                                    <strong class="mfb-field-card__label">{{
                                                                        F.label || F.key || 'Field Tanpa Judul'
                                                                    }}</strong>
                                                                </div>

                                                                <div class="mfb-field-card__meta">
                                                                    <span class="mfb-field-card__type">{{
                                                                        F.tipe
                                                                    }}</span>
                                                                    <span v-if="F.wajib" class="mfb-badge-req"
                                                                        >Wajib</span
                                                                    >
                                                                    <!-- Wajib bersyarat ditandai berbeda: menyebutnya
                                                                         "Wajib" akan berbohong pada kandidat yang
                                                                         syaratnya tidak terpenuhi, sedangkan tidak
                                                                         menandainya sama sekali menyembunyikan bahwa
                                                                         kolom ini punya aturan. -->
                                                                    <span
                                                                        v-else-if="F.wajib_jika?.field"
                                                                        class="mfb-badge-req mfb-badge-req--jika"
                                                                        :title="`Wajib hanya jika ${F.wajib_jika.field} ${F.wajib_jika.operator} ${F.wajib_jika.nilai}`"
                                                                        >Wajib bersyarat</span
                                                                    >
                                                                    <span class="mfb-field-card__width"
                                                                        >{{ Number(F.lebar_persen || 33) }}%<template
                                                                            v-if="F.lebar_jika?.field"
                                                                            >&nbsp;→&nbsp;{{
                                                                                F.lebar_jika.lebar_persen
                                                                            }}%</template
                                                                        ></span
                                                                    >

                                                                    <span
                                                                        v-if="F.tampil_jika?.field"
                                                                        class="mfb-cond-pill"
                                                                        :title="
                                                                            'Tampil jika ' +
                                                                            F.tampil_jika.field +
                                                                            ' ' +
                                                                            F.tampil_jika.operator +
                                                                            ' ' +
                                                                            (F.tampil_jika.nilai || '')
                                                                        "
                                                                    >
                                                                        <i class="bi bi-lightning-charge-fill"></i>
                                                                    </span>

                                                                    <span
                                                                        v-if="showValidation && fieldIssues(F).length"
                                                                        class="mfb-issue-icon"
                                                                        :title="fieldIssues(F).join('\n')"
                                                                    >
                                                                        <i class="bi bi-exclamation-triangle-fill"></i>
                                                                    </span>
                                                                </div>

                                                                <!-- Card Actions & Resize Handle -->
                                                                <div class="mfb-field-card__actions" @click.stop>
                                                                    <button
                                                                        type="button"
                                                                        class="mfb-field-btn"
                                                                        title="Duplikat field"
                                                                        @click="duplikatField(li, bi, fi)"
                                                                    >
                                                                        <i class="bi bi-copy"></i>
                                                                    </button>
                                                                    <button
                                                                        type="button"
                                                                        class="mfb-field-btn mfb-field-btn--danger"
                                                                        title="Hapus field"
                                                                        @click="hapusField(li, bi, fi)"
                                                                    >
                                                                        <i class="bi bi-trash3"></i>
                                                                    </button>
                                                                </div>

                                                                <span
                                                                    class="mfb-resize-handle"
                                                                    title="Geser ke kanan/kiri untuk mengubah lebar"
                                                                    @pointerdown.stop.prevent="
                                                                        mulaiResizeField($event, li, bi, fi)
                                                                    "
                                                                >
                                                                    <i class="bi bi-arrows-angle-expand"></i>
                                                                </span>
                                                            </div>
                                                        </template>
                                                    </draggable>
                                                </section>
                                            </template>
                                        </draggable>
                                    </article>
                                </template>
                            </draggable>
                        </section>

                        <!-- Right Panel Inspector -->
                        <aside class="mfb-inspector">
                            <PropertiField
                                v-if="fieldAktif"
                                :key="fieldAktif.field_id"
                                :field="fieldAktif"
                                :semua-field="semuaField"
                                :key-terkunci="keyFieldTerkunci"
                                :katalog-prefill="katalogPrefill"
                                :kunci-prefill="kunciPrefillAktif"
                                :operator-options="conditionOperators"
                                :lebar-options="lebarCepatOptions"
                                @ubah-tipe="ubahTipeField"
                                @ubah-label="ubahLabelField"
                                @rapikan-key="rapikanKeyField"
                                @rapikan-lebar="rapikanLebarField"
                                @hapus="hapusFieldAktif"
                            />

                            <template v-else-if="langkahAktif">
                                <div class="mfb-inspector__head">
                                    <h3><i class="bi bi-layers-half"></i> Properti Langkah</h3>
                                </div>

                                <div class="mfb-inspector__group">
                                    <label>Judul Langkah</label>
                                    <el-input v-model="langkahAktif.judul" placeholder="mis. Data Pendaftaran" />
                                </div>

                                <div class="mfb-inspector__group">
                                    <label>Kode Langkah</label>
                                    <el-input v-model="langkahAktif.kode" placeholder="PENDAFTARAN" />
                                    <small class="mfb-help">Pengenal langkah di skema. Ubah hanya bila benar-benar perlu.</small>
                                </div>

                                <div class="mfb-inspector__group">
                                    <label>Ikon Langkah</label>
                                    <el-select v-model="langkahAktif.ikon" style="width: 100%" filterable>
                                        <el-option v-for="i in ikonLangkahOptions" :key="i" :value="i" :label="i">
                                            <i class="bi" :class="i"></i> <span class="fr__opsi">{{ i }}</span>
                                        </el-option>
                                    </el-select>
                                </div>

                                <div class="mfb-inspector__group">
                                    <label>Deskripsi Langkah</label>
                                    <el-input
                                        v-model="langkahAktif.deskripsi"
                                        type="textarea"
                                        :rows="2"
                                        placeholder="Keterangan singkat di bawah judul langkah..."
                                    />
                                </div>

                                <div class="mfb-inspector__group">
                                    <label>Langkah Tampil Jika (Kondisional)</label>
                                    <el-select
                                        :model-value="langkahAktif.tampil_jika?.field || ''"
                                        style="width: 100%"
                                        clearable
                                        placeholder="Selalu Tampil"
                                        @change="aturLangkahSyarat"
                                    >
                                        <el-option
                                            v-for="f in semuaField.filter((x) => x.key)"
                                            :key="f.field_id || f.key"
                                            :value="f.key"
                                            :label="f.label || f.key"
                                        />
                                    </el-select>
                                    <div v-if="langkahAktif.tampil_jika?.field" class="mfb-condition-row">
                                        <el-select v-model="langkahAktif.tampil_jika.operator" style="width: 48%">
                                            <el-option
                                                v-for="o in conditionOperators"
                                                :key="o.value"
                                                :value="o.value"
                                                :label="o.label"
                                            />
                                        </el-select>
                                        <el-input
                                            v-model="langkahAktif.tampil_jika.nilai"
                                            placeholder="Nilai pemicu"
                                            style="width: 52%"
                                        />
                                    </div>
                                </div>
                            </template>

                            <template v-else-if="sectionAktif">
                                <div class="mfb-inspector__head">
                                    <h3><i class="bi bi-layout-text-window-reverse"></i> Properti Section</h3>
                                </div>
                                <p class="mfb-help">
                                    Atur pola layout kolom atau sisipkan blok template pertanyaan standar.
                                </p>

                                <div class="mfb-inspector__group">
                                    <label>Judul Section</label>
                                    <el-input v-model="sectionAktif.judul" placeholder="mis. Data Diri" />
                                    <small class="mfb-help">Kosongkan bila judul langkah sudah cukup menjelaskan.</small>
                                </div>

                                <div class="mfb-inspector__group">
                                    <label>Deskripsi Section</label>
                                    <el-input
                                        v-model="sectionAktif.deskripsi"
                                        type="textarea"
                                        :rows="2"
                                        placeholder="Keterangan singkat di bawah judul section..."
                                    />
                                </div>

                                <div class="mfb-inspector__group">
                                    <el-checkbox v-model="sectionAktif.berulang">Section Berulang (Multi-Baris)</el-checkbox>
                                    <small class="mfb-help">
                                        Kandidat bisa menambah baris — mis. riwayat organisasi atau pengalaman kerja.
                                        Jawabannya tersimpan sebagai daftar, bukan satu nilai.
                                    </small>
                                </div>

                                <div v-if="sectionAktif.berulang" class="mfb-inspector__group">
                                    <label>Maksimal Jumlah Baris</label>
                                    <el-input-number
                                        v-model="sectionAktif.maks_baris"
                                        :min="1"
                                        :max="20"
                                        style="width: 100%"
                                    />
                                </div>

                                <div class="mfb-inspector__group">
                                    <label>Preset Format Kolom</label>
                                    <div class="mfb-preset-grid">
                                        <button
                                            v-for="p in layoutPresetOptions"
                                            :key="p.value"
                                            type="button"
                                            @click="terapkanLayoutPreset(p.value)"
                                        >
                                            <i class="bi" :class="p.icon"></i>
                                            <div>
                                                <strong>{{ p.label }}</strong>
                                                <small>{{ p.help }}</small>
                                            </div>
                                        </button>
                                    </div>
                                </div>

                                <div class="mfb-inspector__group">
                                    <label>Section Tampil Jika (Kondisional)</label>
                                    <el-select
                                        :model-value="sectionAktif.tampil_jika?.field || ''"
                                        style="width: 100%"
                                        clearable
                                        placeholder="Selalu Tampil"
                                        @change="aturSectionSyarat"
                                    >
                                        <el-option
                                            v-for="f in fieldAcuanOptions"
                                            :key="f.field_id || f.key"
                                            :value="f.key"
                                            :label="f.label || f.key"
                                        />
                                    </el-select>
                                    <div v-if="sectionAktif.tampil_jika?.field" class="mfb-condition-row">
                                        <el-select v-model="sectionAktif.tampil_jika.operator" style="width: 48%">
                                            <el-option
                                                v-for="o in conditionOperators"
                                                :key="o.value"
                                                :value="o.value"
                                                :label="o.label"
                                            />
                                        </el-select>
                                        <el-input
                                            v-model="sectionAktif.tampil_jika.nilai"
                                            placeholder="Nilai pemicu"
                                            style="width: 52%"
                                        />
                                    </div>
                                </div>

                                <div class="mfb-inspector__group">
                                    <label>Blok Template Siap Pakai</label>
                                    <div class="mfb-block-list">
                                        <button
                                            v-for="b in sectionTemplates"
                                            :key="b.value"
                                            type="button"
                                            @click="tambahBlock(b)"
                                        >
                                            <i class="bi" :class="b.icon"></i>
                                            <div>
                                                <strong>{{ b.label }}</strong>
                                                <small>{{ b.help }}</small>
                                            </div>
                                            <i class="bi bi-plus-circle-fill"></i>
                                        </button>
                                    </div>
                                </div>
                            </template>

                            <template v-else>
                                <div class="mfb-inspector__empty">
                                    <i class="bi bi-cursor"></i>
                                    <h4>Klik Field / Section</h4>
                                    <p>
                                        Pilih salah satu elemen di canvas untuk mengatur detail label, tipe, validasi,
                                        opsi, dan kondisi tampil.
                                    </p>
                                </div>
                            </template>
                        </aside>
                    </div>

                    <!-- TAB 2: RESPONSIVE PREVIEW -->
                    <div v-else-if="tab === 'preview'" class="mfb-preview">
                        <div class="mfb-preview__bar">
                            <div class="mfb-preview__info">
                                <i class="bi bi-display"></i>
                                <span>Simulasi Tampilan Kandidat Real-time</span>
                            </div>
                            <div class="mfb-segmented" style="width: auto; min-width: 260px">
                                <button
                                    v-for="opt in previewModeOptions"
                                    :key="opt.value"
                                    type="button"
                                    class="mfb-segmented__item"
                                    :class="{ active: previewMode === opt.value }"
                                    @click="previewMode = opt.value"
                                >
                                    <i
                                        class="bi"
                                        :class="
                                            opt.value === 'desktop'
                                                ? 'bi-display'
                                                : opt.value === 'tablet'
                                                  ? 'bi-tablet-landscape'
                                                  : 'bi-phone'
                                        "
                                    ></i>
                                    {{ opt.label }}
                                </button>
                            </div>
                        </div>
                        <div class="mfb-preview__frame-wrapper">
                            <div class="mfb-mock-browser">
                                <div class="mfb-mock-browser__bar">
                                    <div class="mfb-mock-browser__dots"><span></span><span></span><span></span></div>
                                    <div class="mfb-mock-browser__url">
                                        <i class="bi bi-lock-fill"></i> webcareer.evo.id/apply/preview
                                    </div>
                                </div>
                                <div
                                    class="mfb-preview__frame"
                                    :class="'mfb-preview-frame--' + previewMode"
                                    :style="previewFrameStyle"
                                >
                                    <!-- Candidate Portal Shell Wrapper (100% Identical to Candidate Portal Page) -->
                                    <div
                                        class="mfb-portal-shell"
                                        :class="{ 'mfb-portal-shell--mobile': previewMode === 'mobile' }"
                                    >
                                        <!-- Mobile Status Bar (Visible in mobile mode) -->
                                        <div v-if="previewMode === 'mobile'" class="mfb-mobile-status-bar">
                                            <span class="mfb-mobile-status-bar__time">9:41</span>
                                            <div class="mfb-mobile-status-bar__notch"></div>
                                            <div class="mfb-mobile-status-bar__icons">
                                                <i class="bi bi-reception-4"></i>
                                                <i class="bi bi-wifi"></i>
                                                <i class="bi bi-battery-full"></i>
                                            </div>
                                        </div>

                                        <header class="mfb-portal-shell__header">
                                            <div class="mfb-portal-shell__brand">
                                                <span class="mfb-portal-shell__logo"
                                                    ><i class="bi bi-briefcase-fill"></i
                                                ></span>
                                                <div>
                                                    <span class="mfb-portal-shell__portal-name">WEB CAREER PORTAL</span>
                                                    <h2 class="mfb-portal-shell__title">
                                                        {{ meta.nama || 'Formulir Pendaftaran Rekrutmen' }}
                                                    </h2>
                                                </div>
                                            </div>
                                            <div class="mfb-portal-shell__badges">
                                                <span v-if="meta.kategori" class="mfb-portal-shell__tag"
                                                    ><i class="bi bi-tag-fill"></i> {{ meta.kategori }}</span
                                                >
                                                <span class="mfb-portal-shell__status"
                                                    ><i class="bi bi-circle-fill"></i> Dibuka</span
                                                >
                                            </div>
                                        </header>

                                        <div v-if="meta.petunjuk || meta.deskripsi" class="mfb-portal-shell__notice">
                                            <i class="bi bi-info-circle-fill"></i>
                                            <div>
                                                <strong>Petunjuk Pengisian Bagi Kandidat:</strong>
                                                <p>{{ meta.petunjuk || meta.deskripsi }}</p>
                                            </div>
                                        </div>

                                        <!-- Live Candidate Form Renderer (100% Identical to Candidate Apply Page) -->
                                        <div class="mfb-portal-shell__body">
                                            <DynamicForm
                                                v-model="previewJawaban"
                                                :skema="schema"
                                                :judul="meta.nama || 'Formulir Pendaftaran'"
                                                :keterangan="
                                                    meta.deskripsi ||
                                                    'Lengkapi data berikut dengan benar. Tanda * wajib diisi.'
                                                "
                                                label-kirim="Kirim Formulir (Preview Test)"
                                                @kirim="notice('Pratinjau kandidat lolos validasi formulir.')"
                                            />
                                        </div>

                                        <!-- Mobile Home Indicator Pill -->
                                        <div v-if="previewMode === 'mobile'" class="mfb-mobile-home-indicator">
                                            <span></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- TAB 3: STATS & AUDIT SUMMARY -->
                    <div v-else-if="tab === 'stats'" class="mfb-stats-view">
                        <div class="mfb-stats-grid">
                            <div class="mfb-stat-card">
                                <div class="mfb-stat-card__icon mfb-stat-card__icon--indigo">
                                    <i class="bi bi-layers-half"></i>
                                </div>
                                <div>
                                    <strong>{{ schema.langkah?.length || 0 }}</strong>
                                    <span>Langkah / Step Stepper</span>
                                </div>
                            </div>
                            <div class="mfb-stat-card">
                                <div class="mfb-stat-card__icon mfb-stat-card__icon--sky">
                                    <i class="bi bi-layout-text-window-reverse"></i>
                                </div>
                                <div>
                                    <strong>{{ totalSectionCount }}</strong>
                                    <span>Section Bagian</span>
                                </div>
                            </div>
                            <div class="mfb-stat-card">
                                <div class="mfb-stat-card__icon mfb-stat-card__icon--emerald">
                                    <i class="bi bi-input-cursor-text"></i>
                                </div>
                                <div>
                                    <strong>{{ semuaField.length }}</strong>
                                    <span>Total Field Pertanyaan</span>
                                </div>
                            </div>
                            <div class="mfb-stat-card">
                                <div class="mfb-stat-card__icon mfb-stat-card__icon--amber">
                                    <i class="bi bi-asterisk"></i>
                                </div>
                                <div>
                                    <strong>{{ wajibFieldCount }}</strong>
                                    <span>Field Wajib Diisi</span>
                                </div>
                            </div>
                        </div>

                        <div class="mfb-audit-box">
                            <h4><i class="bi bi-shield-check"></i> Info Registrasi &amp; Audit Trail</h4>
                            <div class="mfb-audit-grid">
                                <div>
                                    <label>Kode Unik Formulir</label>
                                    <span class="mfb-code-text">{{ aktif.kode }}</span>
                                </div>
                                <div>
                                    <label>Status Aktif System</label>
                                    <span class="mfb-badge-text" :class="aktif.status === 'AKTIF' ? 'ok' : 'off'">
                                        {{ aktif.status }}
                                    </span>
                                </div>
                                <div>
                                    <label>Pembuat (Audit Stamp)</label>
                                    <AuditStamp :by="aktif.createdBy" :at="aktif.createdAt" />
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- TAB 4: JSON SCHEMA -->
                    <div v-else class="mfb-json">
                        <div class="mfb-json__bar">
                            <span><i class="bi bi-file-earmark-code"></i> JSON Schema Payload</span>
                            <button type="button" class="mfb-btn mfb-btn--soft mfb-btn--sm" @click="salinJSON">
                                <i class="bi bi-clipboard"></i> Salin JSON
                            </button>
                        </div>
                        <pre class="mfb-codeblock"><code>{{ JSON.stringify(schema, null, 2) }}</code></pre>
                    </div>
                </section>

                <!-- Empty Selection Workspace -->
                <section v-else class="mfb-empty-work">
                    <div class="mfb-empty-work__content">
                        <i class="bi bi-input-cursor-text"></i>
                        <h3>Pilih atau Buat Formulir Baru</h3>
                        <p>
                            Silakan pilih formulir dari daftar di sebelah kiri untuk mulai mengedit skema visual builder
                            atau klik tombol di bawah.
                        </p>
                        <button class="mfb-btn mfb-btn--glow" type="button" @click="buatBaru">
                            <i class="bi bi-plus-lg"></i> Buat Formulir Baru
                        </button>
                    </div>
                </section>
            </main>
        </div>

        <!-- Toast Floating Notification -->
        <transition name="mfb-toast">
            <div v-if="toast" class="mfb-toast"><i class="bi bi-check-circle-fill"></i> {{ toast }}</div>
        </transition>

        <!-- Review & Compare Modal (Diff Viewer) -->
        <div v-if="showCompare" class="mfb-modal" @click.self="showCompare = false">
            <section class="mfb-modal__box">
                <header class="mfb-modal__head">
                    <div>
                        <h3><i class="bi bi-git"></i> Review Perubahan Sebelum Publish</h3>
                        <p>Perubahan ini akan disimpan sebagai versi baru dan langsung digunakan kandidat.</p>
                    </div>
                    <button type="button" class="mfb-modal__close" @click="showCompare = false">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </header>

                <div v-if="perubahanVersi.length" class="mfb-diff-list">
                    <div v-for="x in perubahanVersi" :key="x.id" class="mfb-diff" :class="'is-' + x.type">
                        <i
                            class="bi"
                            :class="
                                x.type === 'added'
                                    ? 'bi-plus-circle-fill'
                                    : x.type === 'removed'
                                      ? 'bi-dash-circle-fill'
                                      : 'bi-pencil-fill'
                            "
                        ></i>
                        <div>
                            <strong>{{ x.label }}</strong>
                            <small>{{ x.detail }}</small>
                        </div>
                    </div>
                </div>
                <div v-else class="mfb-empty mfb-empty--small">
                    <i class="bi bi-check2-circle"></i>
                    <span>Tidak ada perubahan field dari versi published saat ini.</span>
                </div>

                <footer class="mfb-modal__foot">
                    <button class="mfb-btn mfb-btn--soft" type="button" @click="showCompare = false">Batal</button>
                    <button
                        class="mfb-btn mfb-btn--emerald"
                        type="button"
                        :disabled="saving"
                        :onClick="saving ? null : () => { showCompare = false;
                            publish(true); }"
                    >
                        <i class="bi bi-send-check-fill"></i> Publish Versi Ini
                    </button>
                </footer>
            </section>
        </div>

        <!-- Confirm Modal Delete -->
        <ConfirmModal
            :show="showDeleteModal"
            title="Hapus Master Formulir"
            subtitle="Tindakan ini akan menghapus formulir dari daftar."
            :note="formToDelete ? `Formulir: ${formToDelete.nama} (${formToDelete.kode})` : ''"
            :busy="saving"
            danger
            @cancel="showDeleteModal = false"
            @confirm="eksekusiHapus"
        />

        <!-- Duplicate Modal Form -->
        <div v-if="showDuplicateModal" class="mfb-modal" @click.self="showDuplicateModal = false">
            <section class="mfb-modal__box">
                <header class="mfb-modal__head">
                    <div>
                        <h3><i class="bi bi-copy"></i> Duplikat Formulir</h3>
                        <p>Salin skema formulir ini menjadi formulir baru.</p>
                    </div>
                    <button type="button" class="mfb-modal__close" @click="showDuplicateModal = false">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </header>

                <div style="padding: 1rem 0">
                    <label style="font-weight: 700; font-size: 13px; margin-bottom: 6px; display: block"
                        >Nama Formulir Baru</label
                    >
                    <el-input v-model="duplicateName" placeholder="Nama formulir hasil duplikat" />
                </div>

                <footer class="mfb-modal__foot">
                    <button class="mfb-btn mfb-btn--soft" type="button" @click="showDuplicateModal = false">
                        Batal
                    </button>
                    <button
                        class="mfb-btn mfb-btn--indigo"
                        type="button"
                        :disabled="saving || !duplicateName.trim()"
                        :onClick="saving || !duplicateName.trim() ? null : eksekusiDuplikat"
                    >
                        <i class="bi bi-copy"></i> Duplikat Sekarang
                    </button>
                </footer>
            </section>
        </div>
    </div>
</template>

<script>
import axios from 'axios';
import draggable from 'vuedraggable';
import { Head } from '@inertiajs/vue3';
import RefSelect from '@career/RefSelect.vue';
import AuditStamp from '@career/AuditStamp.vue';
import ConfirmModal from '@career/ConfirmModal.vue';
import IconPicker from '@career/IconPicker.vue';
import DynamicForm from '@career/formulir/DynamicForm.vue';
import {
    buatFieldId,
    jawabanAwal,
    normalisasiSkema,
    skemaFormulir,
    skemaKosong,
    slugKey,
    validasiSkema,
    MAKS_PANJANG_KEY,
} from '@career/formulir';
import { KATALOG_FIELD, bersihkanField, galatTipe } from '@utils/formulir/katalogField';
import PropertiField from './PropertiField.vue';

const API = '/api/v1/master-formulir';
const CFG = { headers: { Accept: 'application/json' } };

export default {
    components: { Head, RefSelect, AuditStamp, ConfirmModal, IconPicker, DynamicForm, draggable, PropertiField },
    props: {
        // Diisi Inertia dari KatalogPrefill::untukEditor. Selama kosong,
        // pemeriksaan kunci isi-otomatis tidak berjalan sama sekali.
        katalogPrefill: { type: Array, default: () => [] },
    },
    data() {
        return {
            list: [],
            aktif: null,
            meta: { nama: '', kategori: '', deskripsi: '', petunjuk: '' },
            schema: skemaKosong(),
            pilihTarget: null,
            tab: 'builder',
            cari: '',
            statusFilter: 'SEMUA',
            filterChips: [
                { label: 'Semua', value: 'SEMUA' },
                { label: 'Aktif', value: 'AKTIF' },
                { label: 'Published', value: 'PUBLISHED' },
                { label: 'Draft', value: 'DRAFT' },
                { label: 'Nonaktif', value: 'NONAKTIF' },
            ],
            saving: false,
            toast: '',
            previewJawaban: {},
            resizeState: null,
            showCompare: false,
            showDeleteModal: false,
            formToDelete: null,
            showDuplicateModal: false,
            formToDuplicate: null,
            duplicateName: '',
            showValidation: true,
            previewMode: 'desktop',
            previewModeOptions: [
                { label: 'Desktop', value: 'desktop' },
                { label: 'Tablet', value: 'tablet' },
                { label: 'Mobile', value: 'mobile' },
            ],
            layoutOptions: [
                { label: 'Satu Halaman', value: 'SATU_HALAMAN' },
                { label: 'Bertahap (Stepper)', value: 'BERTAHAP' },
            ],
            /**
             * Menentukan kunci isi-otomatis yang tersedia. KEDUANYA hanya
             * mengambil irisan: formulir yang dipakai di dua tempat tidak boleh
             * memakai kunci yang di salah satunya selalu kosong.
             */
            konteksOptions: [
                { value: 'KEDUANYA', label: 'Keduanya (aman untuk semua)' },
                { value: 'PENDAFTARAN', label: 'Formulir Pendaftaran' },
                { value: 'TAHAP', label: 'Formulir Tahap Seleksi' },
            ],
            ikonLangkahOptions: [
                'bi-card-list',
                'bi-person-vcard',
                'bi-mortarboard',
                'bi-briefcase',
                'bi-file-earmark-text',
                'bi-shield-check',
                'bi-people',
                'bi-clipboard-check',
            ],
            lebarCepatOptions: [
                { label: '1/3', value: 33 },
                { label: '1/2', value: 50 },
                { label: '2/3', value: 67 },
                { label: 'Full', value: 100 },
            ],
            conditionOperators: [
                { label: 'Sama dengan (=)', value: '=' },
                { label: 'Tidak sama dengan (!=)', value: '!=' },
                { label: 'Lebih besar (>)', value: '>' },
                { label: 'Lebih kecil (<)', value: '<' },
                { label: 'Termasuk dalam (ADA_DI)', value: 'ADA_DI' },
                { label: 'Tidak termasuk (TIDAK_ADA_DI)', value: 'TIDAK_ADA_DI' },
            ],
            /**
             * Palette = jalan pintas, bukan daftar lengkap. Yang ditulis di sini
             * hanya urutan tipe yang sering dipakai plus nilai bawaan yang khas
             * pemakaiannya; label, ikon, dan pembersihan propertinya ikut katalog.
             *
             * `foto` membawa key bawaan `foto_verifikasi` — nilai itu sudah jadi
             * kesepakatan seluruh sistem: LamaranService mencarinya untuk lampiran
             * email, halaman Pelamar dan Detail Lamaran memakainya untuk label
             * "foto verifikasi identitas kandidat". Key lain tetap tersimpan, tapi
             * fotonya berhenti dikenali sebagai foto verifikasi.
             */
            paletteUrutan: [
                ['text', 'Teks', { label: 'Pertanyaan Teks' }],
                ['email', 'Email', { label: 'Alamat Email', ph: 'nama@contoh.com' }],
                ['phone', 'Telepon', { label: 'Nomor Telepon', ph: '08xxxxxxxxxx' }],
                ['number', 'Angka', { label: 'Pertanyaan Angka' }],
                ['date', 'Tanggal', { label: 'Pilih Tanggal' }],
                ['select', 'Pilihan', { label: 'Pilih Salah Satu' }],
                ['radio', 'Radio', { label: 'Pilihan Radio' }],
                ['checkbox', 'Checkbox', { label: 'Pilih Beberapa' }],
                ['textarea', 'Paragraf', { label: 'Ceritakan Lebih Lanjut' }],
                ['file', 'Upload', { label: 'Upload Dokumen', accept: '.pdf,.jpg,.jpeg,.png' }],
                [
                    'foto',
                    'Foto Kamera',
                    {
                        key: 'foto_verifikasi',
                        label: 'Foto Verifikasi Wajah',
                        wajib: true,
                        bantuan: 'Ambil foto wajah langsung dari kamera perangkat Anda.',
                    },
                ],
                ['referensi', 'Referensi', { label: 'Pilih dari Master Data', sumber: 'jenjang' }],
                ['prefill', 'Isi Otomatis', { label: 'Data dari Akun Anda' }],
                [
                    'consent',
                    'Persetujuan',
                    { label: 'Saya menyatakan seluruh data yang diisi adalah benar', wajib: true },
                ],
            ],
            layoutPresetOptions: [
                { value: 'THREE', label: '3 Kolom', help: 'Ringkas & padat', icon: 'bi-layout-three-columns' },
                { value: 'TWO', label: '2 Kolom', help: 'Seimbang & mudah dibaca', icon: 'bi-layout-split' },
                { value: 'LONG', label: 'Form Panjang', help: 'Lebar 2/3', icon: 'bi-layout-text-window' },
                {
                    value: 'FULL',
                    label: 'Full Width',
                    help: '100% lebar untuk upload/paragraf',
                    icon: 'bi-arrows-angle-expand',
                },
            ],
            sectionTemplates: [
                {
                    value: 'identity',
                    label: 'Data Diri Utama',
                    help: 'Nama lengkap, email, telepon',
                    icon: 'bi-person-vcard',
                    fields: [
                        { label: 'Nama Lengkap', tipe: 'text', wajib: true, lebar_persen: 67 },
                        { label: 'Email', tipe: 'email', wajib: true, lebar_persen: 33 },
                        { label: 'Nomor Telepon', tipe: 'phone', wajib: true, lebar_persen: 33 },
                    ],
                },
                {
                    value: 'education',
                    label: 'Riwayat Pendidikan',
                    help: 'Jenjang, nama kampus, prodi',
                    icon: 'bi-mortarboard',
                    fields: [
                        {
                            label: 'Jenjang Pendidikan',
                            tipe: 'select',
                            wajib: true,
                            opsi: ['SMA/K', 'Diploma', 'Sarjana (S1)', 'Magister (S2)', 'Doktor (S3)'],
                        },
                        { label: 'Nama Perguruan Tinggi / Sekolah', tipe: 'text', wajib: true, lebar_persen: 67 },
                        { label: 'Program Studi / Jurusan', tipe: 'text', wajib: true, lebar_persen: 67 },
                    ],
                },
                {
                    value: 'documents',
                    label: 'Berkas Dokumen Pendukung',
                    help: 'CV resume & berkas pendukung',
                    icon: 'bi-file-earmark-arrow-up',
                    fields: [
                        {
                            label: 'CV / Resume Terkini',
                            tipe: 'file',
                            wajib: true,
                            // Bukan .doc/.docx: server hanya menyimpan PDF/JPG/PNG
                            // (BerkasFormulir::EKSTENSI_SERVER). Menawarkan Word di
                            // sini berarti CV kandidat ditolak saat diunggah.
                            accept: '.pdf',
                            maks_mb: 5,
                            lebar_persen: 100,
                            penuh: true,
                        },
                        {
                            label: 'Dokumen / Sertifikat Pendukung',
                            tipe: 'file',
                            accept: '.pdf,.jpg,.jpeg,.png',
                            maks_mb: 5,
                            lebar_persen: 100,
                            penuh: true,
                        },
                    ],
                },
                {
                    value: 'consent',
                    label: 'Pernyataan Keabsahan Data',
                    help: 'Persetujuan kandidat',
                    icon: 'bi-shield-check',
                    fields: [
                        {
                            label: 'Saya menyatakan bahwa seluruh data yang disampaikan adalah benar dan sah.',
                            tipe: 'consent',
                            wajib: true,
                            lebar_persen: 100,
                            penuh: true,
                        },
                    ],
                },
            ],
        };
    },
    computed: {
        countPublished() {
            return this.list.filter((x) => Boolean(x.published)).length;
        },
        countDraft() {
            return this.list.filter((x) => !x.published).length;
        },
        tersaring() {
            let res = this.list;
            const q = this.cari.trim().toLowerCase();
            if (q) {
                res = res.filter((x) => `${x.nama} ${x.kode} ${x.kategori || ''}`.toLowerCase().includes(q));
            }
            if (this.statusFilter === 'AKTIF') res = res.filter((x) => x.status === 'AKTIF');
            else if (this.statusFilter === 'NONAKTIF') res = res.filter((x) => x.status !== 'AKTIF');
            else if (this.statusFilter === 'PUBLISHED') res = res.filter((x) => Boolean(x.published));
            else if (this.statusFilter === 'DRAFT') res = res.filter((x) => !x.published);
            return res;
        },
        stepAktif() {
            if (!this.pilihTarget) return this.schema.langkah?.length ? 0 : -1;
            return this.pilihTarget.li ?? this.pilihTarget.index ?? 0;
        },
        fieldAktif() {
            const t = this.pilihTarget;
            if (!t || t.tipe !== 'field') return null;
            return this.schema.langkah?.[t.li]?.bagian?.[t.bi]?.field?.[t.fi] || null;
        },
        /**
         * Key terkunci karena sudah dipublish — KECUALI bila key itu sendiri
         * melebihi lebar kolom Field_Key.
         *
         * Gembok ini melindungi data kandidat yang sudah menunjuk key tersebut.
         * Key yang kepanjangan tidak punya data untuk dilindungi: setiap
         * unggahan berkasnya selalu mati dengan "String or binary data would be
         * truncated", jadi tidak ada satu pun baris yang pernah tersimpan.
         *
         * Menguncinya justru menjebak — formulir sudah live, kandidat tidak
         * bisa mengunggah, dan admin tidak diberi jalan memperbaikinya. Pagar
         * yang sama juga dilonggarkan di server (guardKeyPublished); keduanya
         * harus sepakat, sebab pagar server-lah yang benar-benar menahan.
         */
        keyFieldTerkunci() {
            if (!this.fieldAktif || !this.aktif?.published?.schema) return false;
            if (!this.fieldDipublish(this.fieldAktif)) return false;

            return String(this.keyPublished(this.fieldAktif) || '').length <= MAKS_PANJANG_KEY;
        },
        /** Key versi PUBLISHED milik field ini — pembanding, bukan yang di layar. */
        keyPublished() {
            return (field) => {
                let ketemu = '';
                (this.aktif?.published?.schema?.langkah || []).forEach((L) =>
                    (L.bagian || []).forEach((B) =>
                        (B.field || []).forEach((F) => {
                            if (F.field_id && F.field_id === field.field_id) ketemu = F.key || '';
                        }),
                    ),
                );

                return ketemu;
            };
        },
        fieldPalette() {
            return this.paletteUrutan
                .filter(([value]) => KATALOG_FIELD[value])
                .map(([value, label, bawaan]) => ({
                    value,
                    label,
                    icon: KATALOG_FIELD[value].ikon,
                    // Lewat bersihkanField supaya nilai bawaan palette tidak bisa
                    // menyelipkan properti yang bukan milik tipenya.
                    field: bersihkanField({ ...bawaan, tipe: value }),
                }));
        },
        /**
         * Kunci isi-otomatis yang tersedia untuk konteks formulir ini.
         * KEDUANYA hanya mengambil irisan: formulir yang dipakai di dua tempat
         * tidak boleh memakai kunci yang di salah satunya selalu kosong.
         */
        kunciPrefillAktif() {
            const konteks = this.schema.konteks || 'KEDUANYA';
            const butuh = konteks === 'KEDUANYA' ? ['PENDAFTARAN', 'TAHAP'] : [konteks];
            return (this.katalogPrefill || [])
                .filter((k) => butuh.every((b) => k.konteks.includes(b)))
                .map((k) => k.kunci);
        },
        sectionAktif() {
            const t = this.pilihTarget;
            if (!t || t.tipe !== 'section') return null;
            return this.schema.langkah?.[t.li]?.bagian?.[t.bi] || null;
        },
        /**
         * Langkah yang sedang dipilih di kanvas.
         *
         * Kanvas sudah bisa memilih langkah sejak awal (pilihTarget.tipe ===
         * 'step'), tapi inspector tidak punya cabangnya — jadi ikon, deskripsi,
         * dan syarat tampil langkah tidak pernah bisa diisi walau ketiganya
         * sudah dinormalisasi dan dirender.
         */
        langkahAktif() {
            const t = this.pilihTarget;
            if (!t || t.tipe !== 'step') return null;
            return this.schema.langkah?.[t.index] || null;
        },
        semuaField() {
            const fields = [];
            (this.schema.langkah || []).forEach((L) =>
                (L.bagian || []).forEach((B) => (B.field || []).forEach((F) => fields.push(F))),
            );
            return fields;
        },
        wajibFieldCount() {
            // Yang bersyarat ikut dihitung: bagi perancang formulir, kolom yang
            // bisa mengikat adalah kolom yang perlu dipikirkan - dan angka ini
            // dipakai menakar beban pengisian, bukan menghitung bintang merah
            // pada satu kandidat tertentu.
            return this.semuaField.filter((f) => f.wajib || f.wajib_jika?.field).length;
        },
        totalSectionCount() {
            return (this.schema.langkah || []).reduce((acc, L) => acc + (L.bagian?.length || 0), 0);
        },
        fieldAcuanOptions() {
            const id = this.fieldAktif?.field_id;
            return this.semuaField.filter((f) => f.field_id !== id && f.key);
        },
        jumlahIssue() {
            return this.semuaField.reduce((jumlah, f) => jumlah + this.fieldIssues(f).length, 0);
        },
        previewFrameStyle() {
            return {
                width: this.previewMode === 'mobile' ? '390px' : this.previewMode === 'tablet' ? '768px' : '100%',
                maxWidth: '100%',
                margin: '0 auto',
            };
        },
        perubahanVersi() {
            const oldFields = {};
            const newFields = {};
            const flatten = (schema, target) =>
                (schema?.langkah || []).forEach((L) =>
                    (L.bagian || []).forEach((B) =>
                        (B.field || []).forEach((F) => {
                            target[F.field_id || F.key] = F;
                        }),
                    ),
                );
            flatten(this.aktif?.published?.schema, oldFields);
            flatten(this.schema, newFields);
            const out = [];
            Object.keys(newFields).forEach((id) => {
                const f = newFields[id];
                if (!oldFields[id]) {
                    out.push({
                        id,
                        type: 'added',
                        label: f.label || f.key,
                        detail: 'Field baru tipe ' + f.tipe + ' ditambahkan',
                    });
                } else if (
                    JSON.stringify({
                        label: f.label,
                        tipe: f.tipe,
                        wajib: f.wajib,
                        opsi: f.opsi,
                        tampil_jika: f.tampil_jika,
                        lebar_persen: f.lebar_persen,
                    }) !==
                    JSON.stringify({
                        label: oldFields[id].label,
                        tipe: oldFields[id].tipe,
                        wajib: oldFields[id].wajib,
                        opsi: oldFields[id].opsi,
                        tampil_jika: oldFields[id].tampil_jika,
                        lebar_persen: oldFields[id].lebar_persen,
                    })
                ) {
                    out.push({
                        id,
                        type: 'changed',
                        label: f.label || f.key,
                        detail: 'Konfigurasi field telah diperbarui',
                    });
                }
            });
            Object.keys(oldFields).forEach((id) => {
                if (!newFields[id]) {
                    out.push({
                        id,
                        type: 'removed',
                        label: oldFields[id].label || oldFields[id].key,
                        detail: 'Field dihapus dari draft',
                    });
                }
            });
            return out;
        },
    },
    watch: {
        schema: {
            deep: true,
            handler() {
                this.previewJawaban = jawabanAwal(this.schema, this.previewJawaban);
            },
        },
    },
    mounted() {
        this.load();
    },
    methods: {
        async load() {
            try {
                const { data } = await axios.get(API, CFG);
                this.list = data.result || [];
                if (!this.aktif && this.list.length) {
                    this.pilih(this.list[0]);
                } else if (this.aktif) {
                    const match = this.list.find((x) => x.id === this.aktif.id);
                    if (match) this.pilih(match);
                }
            } catch (e) {
                this.notice('Gagal memuat katalog formulir.');
            }
        },
        pilih(f) {
            this.aktif = f;
            this.meta = {
                nama: f.nama || '',
                kategori: f.kategori || '',
                deskripsi: f.deskripsi || '',
                petunjuk: f.petunjuk || '',
            };
            this.schema = normalisasiSkema(
                this.clone(f.draft?.schema || f.published?.schema || skemaFormulir(f.komponen) || skemaKosong()),
            );
            if (!this.schema.langkah?.length) this.schema = skemaKosong();
            this.pilihTarget = null;
            this.previewJawaban = jawabanAwal(this.schema, {});
        },
        buatBaru() {
            this.aktif = { id: null, nama: 'Formulir Baru', kode: 'BARU', status: 'AKTIF' };
            this.meta = { nama: 'Formulir Baru', kategori: '', deskripsi: '', petunjuk: '' };
            this.schema = normalisasiSkema(skemaKosong());
            this.pilihTarget = null;
            this.tab = 'builder';
            this.previewJawaban = jawabanAwal(this.schema, {});
        },
        async simpanDraft() {
            const valid = validasiSkema(this.schema, { kunciPrefill: this.kunciPrefillAktif });
            if (!valid.ok) return this.notice(valid.errors[0]);
            this.saving = true;
            try {
                const payload = { ...this.meta, schema: valid.skema };
                if (this.aktif?.id) {
                    await axios.put(`${API}/${this.aktif.id}`, payload, CFG);
                } else {
                    const { data } = await axios.post(API, payload, CFG);
                    this.aktif.id = data.result?.id;
                }
                await this.load();
                // Peringatan tidak menggagalkan simpan, tapi harus terlihat —
                // mis. field foto yang key-nya bukan `foto_verifikasi`.
                this.notice(
                    valid.peringatan.length
                        ? `Draft tersimpan. Perhatikan: ${valid.peringatan[0]}`
                        : 'Draft formulir berhasil tersimpan.',
                );
            } catch (e) {
                this.notice(e.response?.data?.message || 'Gagal menyimpan draft.');
            } finally {
                this.saving = false;
            }
        },
        reviewPublish() {
            if (!this.aktif?.id) return this.publish(true);
            this.showCompare = true;
        },
        async publish(force = false) {
            if (!force && this.aktif?.published?.schema) return (this.showCompare = true);
            if (!this.aktif?.id) await this.simpanDraft();
            if (!this.aktif?.id) return;
            const valid = validasiSkema(this.schema, { kunciPrefill: this.kunciPrefillAktif });
            if (!valid.ok) return this.notice(valid.errors[0]);
            this.saving = true;
            try {
                await axios.post(`${API}/${this.aktif.id}/publish`, { schema: valid.skema }, CFG);
                await this.load();
                this.notice('Formulir berhasil dipublish!');
            } catch (e) {
                this.notice(e.response?.data?.message || 'Gagal mempublish formulir.');
            } finally {
                this.saving = false;
            }
        },
        mintaDuplikat(f) {
            this.formToDuplicate = f;
            this.duplicateName = `${f.nama} - Salinan`;
            this.showDuplicateModal = true;
        },
        async eksekusiDuplikat() {
            if (!this.formToDuplicate) return;
            this.saving = true;
            try {
                const { data } = await axios.post(
                    `${API}/${this.formToDuplicate.id}/duplicate`,
                    { nama: this.duplicateName },
                    CFG,
                );
                this.showDuplicateModal = false;
                await this.load();
                if (data.result?.id) {
                    const found = this.list.find((x) => x.id === data.result.id);
                    if (found) this.pilih(found);
                }
                this.notice('Formulir berhasil diduplikat.');
            } catch (e) {
                this.notice(e.response?.data?.message || 'Gagal duplikasi formulir.');
            } finally {
                this.saving = false;
            }
        },
        async toggleStatus(f) {
            const nextAktif = f.status !== 'AKTIF';
            try {
                await axios.patch(`${API}/${f.id}/toggle`, { aktif: nextAktif }, CFG);
                await this.load();
                this.notice(`Status formulir diubah menjadi ${nextAktif ? 'AKTIF' : 'NONAKTIF'}.`);
            } catch (e) {
                this.notice(e.response?.data?.message || 'Gagal mengubah status.');
            }
        },
        mintaHapus(f) {
            this.formToDelete = f;
            this.showDeleteModal = true;
        },
        async eksekusiHapus() {
            if (!this.formToDelete) return;
            this.saving = true;
            try {
                await axios.delete(`${API}/${this.formToDelete.id}`, CFG);
                this.showDeleteModal = false;
                this.formToDelete = null;
                this.aktif = null;
                await this.load();
                this.notice('Formulir telah dihapus.');
            } catch (e) {
                this.notice(e.response?.data?.message || 'Gagal menghapus formulir.');
            } finally {
                this.saving = false;
            }
        },
        tambahStep() {
            let n = this.schema.langkah.length + 1;
            const used = new Set((this.schema.langkah || []).map((L) => L.kode));
            while (used.has('LANGKAH_' + n)) n += 1;
            this.schema.langkah.push({
                kode: `LANGKAH_${n}`,
                judul: `Langkah ${n}`,
                ikon: 'bi-card-list',
                bagian: [{ judul: 'Section Baru', field: [] }],
            });
            this.selectStep(this.schema.langkah.length - 1);
        },
        tambahSection() {
            const li = Math.max(this.stepAktif, 0);
            if (!this.schema.langkah[li]) this.tambahStep();
            this.schema.langkah[li].bagian.push({ judul: 'Section Baru', field: [] });
            this.selectSection(li, this.schema.langkah[li].bagian.length - 1);
        },
        tambahField(li, bi) {
            const key = this.keyUnik('field_baru');
            this.schema.langkah[li].bagian[bi].field.push({
                field_id: buatFieldId(),
                key,
                label: 'Field Baru',
                tipe: 'text',
                wajib: false,
                penuh: false,
                lebar_persen: 33,
            });
            this.selectField(li, bi, this.schema.langkah[li].bagian[bi].field.length - 1);
        },
        duplikatSection(li, bi) {
            const sec = this.schema.langkah[li]?.bagian?.[bi];
            if (!sec) return;
            const copy = this.clone(sec);
            copy.judul = `${sec.judul} (Salinan)`;
            (copy.field || []).forEach((f) => {
                f.field_id = buatFieldId();
                f.key = this.keyUnik(f.key || f.label);
                f.key_manual = false;
            });
            this.schema.langkah[li].bagian.splice(bi + 1, 0, copy);
            this.selectSection(li, bi + 1);
            this.notice('Section diduplikat.');
        },
        duplikatField(li, bi, fi) {
            const field = this.schema.langkah[li]?.bagian?.[bi]?.field?.[fi];
            if (!field) return;
            const copy = this.clone(field);
            copy.field_id = buatFieldId();
            copy.label = `${field.label} (Salinan)`;
            copy.key = this.keyUnik(slugKey(copy.label));
            // Salinan memulai hidupnya dengan key turunan label sendiri, jadi
            // penanda 'diketik manual' milik aslinya tidak boleh ikut terbawa —
            // kalau ikut, key salinan langsung beku padahal belum pernah
            // disentuh siapa pun.
            copy.key_manual = false;
            this.schema.langkah[li].bagian[bi].field.splice(fi + 1, 0, copy);
            this.selectField(li, bi, fi + 1);
            this.notice('Field diduplikat.');
        },
        hapusStep(li) {
            if (this.schema.langkah.length <= 1) return this.notice('Minimal harus ada satu langkah/step.');
            this.schema.langkah.splice(li, 1);
            this.pilihTarget = null;
        },
        hapusSection(li, bi) {
            if (this.schema.langkah[li].bagian.length <= 1) return this.notice('Minimal harus ada satu section.');
            this.schema.langkah[li].bagian.splice(bi, 1);
            this.pilihTarget = null;
        },
        hapusFieldAktif() {
            const t = this.pilihTarget;
            if (!t) return;
            this.hapusField(t.li, t.bi, t.fi);
        },
        hapusField(li, bi, fi) {
            const fields = this.schema.langkah?.[li]?.bagian?.[bi]?.field;
            if (!fields?.[fi]) return;
            fields.splice(fi, 1);
            if (fields[fi]) this.selectField(li, bi, fi);
            else this.selectSection(li, bi);
        },
        selectStep(index) {
            this.pilihTarget = { tipe: 'step', index, li: index };
        },
        selectSection(li, bi) {
            this.pilihTarget = { tipe: 'section', li, bi };
        },
        selectField(li, bi, fi) {
            const fieldId = this.schema.langkah?.[li]?.bagian?.[bi]?.field?.[fi]?.field_id;
            this.pilihTarget = { tipe: 'field', li, bi, fi, fieldId };
        },
        /**
         * Field yang lagi dipilih ikut digeser lintas section/step -> `li/bi/fi`
         * lama jadi salah alamat begitu drag selesai (posisinya sudah pindah).
         * Dicocokkan lewat `field_id` (bukan index) supaya inspector tetap
         * menunjuk field yang benar, bukan field lain yang kebetulan menempati
         * posisi lama itu sekarang.
         */
        onFieldDragChange(evt, li, bi) {
            const t = this.pilihTarget;
            if (!t || t.tipe !== 'field' || !t.fieldId) return;

            if (evt.added && evt.added.element?.field_id === t.fieldId) {
                this.pilihTarget = { tipe: 'field', li, bi, fi: evt.added.newIndex, fieldId: t.fieldId };
            } else if (evt.moved && evt.moved.element?.field_id === t.fieldId) {
                this.pilihTarget = { ...t, li, bi, fi: evt.moved.newIndex };
            }
        },
        targetSection() {
            const t = this.pilihTarget;
            if (t?.tipe === 'section') return { li: t.li, bi: t.bi };
            if (t?.tipe === 'field') return { li: t.li, bi: t.bi };
            const li = Math.max(this.stepAktif, 0);
            if (!this.schema.langkah[li]) this.tambahStep();
            if (!this.schema.langkah[li].bagian?.length) this.tambahSection();
            return { li, bi: 0 };
        },
        sectionJudulUnik(base) {
            const used = new Set();
            (this.schema.langkah || []).forEach((L) =>
                (L.bagian || []).forEach((B) =>
                    used.add(
                        String(B.judul || '')
                            .trim()
                            .toLowerCase(),
                    ),
                ),
            );
            const root = String(base || 'Section Baru').trim() || 'Section Baru';
            let value = root;
            let n = 2;
            while (used.has(value.toLowerCase())) value = root + ' ' + n++;
            return value;
        },
        fieldBaru(draft) {
            const field = this.clone(draft || {});
            field.field_id = buatFieldId();
            field.key = this.keyUnik(field.label || 'field_baru');
            field.label = field.label || 'Field Baru';
            field.tipe = field.tipe || 'text';
            field.wajib = Boolean(field.wajib);
            field.penuh = Boolean(field.penuh || Number(field.lebar_persen) >= 100);
            field.lebar_persen = Number(field.lebar_persen || (field.penuh ? 100 : 33));
            this.rapikanField(field);
            return field;
        },
        tambahFieldPreset(preset) {
            const { li, bi } = this.targetSection();
            const section = this.schema.langkah[li].bagian[bi];
            section.field.push(this.fieldBaru(preset.field));
            this.selectField(li, bi, section.field.length - 1);
        },
        tambahBlock(block) {
            const li = Math.max(this.stepAktif, 0);
            if (!this.schema.langkah[li]) this.tambahStep();
            const section = {
                judul: this.sectionJudulUnik(block.label || 'Section Baru'),
                field: [],
            };
            this.schema.langkah[li].bagian.push(section);
            (block.fields || []).forEach((f) => section.field.push(this.fieldBaru(f)));
            this.selectSection(li, this.schema.langkah[li].bagian.length - 1);
        },
        terapkanLayoutPreset(preset) {
            if (!this.sectionAktif) return;
            const width = { THREE: 33, TWO: 50, LONG: 67, FULL: 100 }[preset] || 33;
            this.sectionAktif.field.forEach((f) => {
                const forced = preset === 'FULL' || ['file', 'consent'].includes(f.tipe);
                f.lebar_persen = forced ? 100 : width;
                f.penuh = f.lebar_persen >= 100;
            });
            this.notice('Preset layout kolom diterapkan.');
        },
        /**
         * Key mengikuti label — SELAMA key-nya belum pernah diketik sendiri.
         *
         * Bawaannya memang diturunkan dari label: itu yang membuat pembuatan
         * field cepat, dan sembilan dari sepuluh kali memang itu yang diinginkan.
         *
         * Tapi begitu admin mengetik key-nya sendiri, label TIDAK BOLEH lagi
         * menimpanya. Sebelum ada penjaga ini, fungsi ini dipanggil pada tiap
         * ketukan di kotak Label, jadi key hasil ketikan hidup paling lama satu
         * ketukan — yang membuat kotak Key tampak bisa diisi padahal sebenarnya
         * tidak. Itulah yang membuat label panjang selalu memaksa key panjang,
         * dan key panjang itulah yang menabrak batas kolom varchar(60).
         */
        sinkronKey() {
            if (!this.fieldAktif || this.keyFieldTerkunci) return;
            if (this.fieldAktif.key_manual) return;
            this.fieldAktif.key = this.keyUnik(slugKey(this.fieldAktif.label), this.fieldAktif.field_id);
        },
        ubahLabelField() {
            this.sinkronKey();
            const f = this.fieldAktif;
            if (!f) return;
            const label = String(f.label || '').toLowerCase();
            if (f.tipe !== 'text' || f.opsi?.length || f.accept) return;
            if (label.includes('email')) f.tipe = 'email';
            else if (/telepon|nomor hp|whatsapp|wa\b/.test(label)) f.tipe = 'phone';
            else if (/cv|resume|ijazah|sertifikat|transkrip|ktp|upload|unggah|dokumen|berkas/.test(label)) {
                f.tipe = 'file';
                f.lebar_persen = 100;
                f.penuh = true;
            } else if (/setuju|persetujuan|pernyataan/.test(label)) {
                f.tipe = 'consent';
                f.lebar_persen = 100;
                f.penuh = true;
            } else if (/alamat|cerita|deskripsi|ringkasan|pengalaman|catatan/.test(label)) {
                f.tipe = 'textarea';
                f.lebar_persen = 67;
            }
            this.rapikanField(f);
        },
        ubahTipeField() {
            if (!this.fieldAktif) return;
            this.rapikanField(this.fieldAktif);
        },
        aturSectionSyarat(key) {
            if (!this.sectionAktif) return;
            this.sectionAktif.tampil_jika = key ? { field: key, operator: '=', nilai: '' } : null;
        },
        aturLangkahSyarat(key) {
            if (!this.langkahAktif) return;
            this.langkahAktif.tampil_jika = key ? { field: key, operator: '=', nilai: '' } : null;
        },
        /**
         * Dipanggil saat kotak Key DITINGGALKAN: rapikan ketikannya, lalu catat
         * bahwa key ini kini milik admin.
         *
         * Penandanya dipasang di sini, bukan pada tiap ketukan: selama masih
         * mengetik, key setengah jadi tidak boleh langsung memutus hubungan
         * dengan label — admin yang salah ketik lalu mengosongkan kotaknya
         * berhak mendapatkan bawaannya kembali.
         *
         * Dikosongkan = kembali mengikuti label. Itu jalan pulangnya, dan ia
         * harus ada: tanpa itu, sekali key diketik, tidak ada cara membatalkan
         * selain menghapus field-nya.
         */
        rapikanKeyField() {
            if (!this.fieldAktif || this.keyFieldTerkunci) return;

            const diketik = String(this.fieldAktif.key || '').trim();

            if (diketik === '') {
                this.fieldAktif.key_manual = false;
                this.fieldAktif.key = this.keyUnik(slugKey(this.fieldAktif.label), this.fieldAktif.field_id);

                return;
            }

            const rapi = this.keyUnik(slugKey(diketik), this.fieldAktif.field_id);
            this.fieldAktif.key = rapi;
            // Hanya dianggap manual bila hasilnya memang BEDA dari turunan
            // labelnya. Mengetik ulang persis yang sama tidak perlu memutus
            // hubungan dengan label.
            this.fieldAktif.key_manual = rapi !== this.keyUnik(slugKey(this.fieldAktif.label), this.fieldAktif.field_id);
        },
        rapikanField(f) {
            // Dua arah: melengkapi bawaan tipe baru DAN membuang properti yang
            // bukan miliknya. Yang kedua itu perbaikannya — sebelumnya properti
            // sisa (opsi pada field yang sudah jadi teks, accept pada field yang
            // sudah bukan berkas) menempel selamanya di Schema_Json.
            //
            // Objeknya disunting DI TEMPAT, bukan diganti, supaya `pilihTarget`
            // yang menunjuk field ini tetap sahih.
            const bersih = bersihkanField(f);
            Object.keys(f).forEach((k) => {
                if (!(k in bersih)) delete f[k];
            });
            Object.entries(bersih).forEach(([k, v]) => {
                if (f[k] === undefined) f[k] = v;
            });
        },
        rapikanLebarField() {
            if (!this.fieldAktif) return;
            const v = this.snapLebar(Number(this.fieldAktif.lebar_persen || 33));
            this.fieldAktif.lebar_persen = v;
            this.fieldAktif.penuh = v >= 100;
        },
        snapLebar(value) {
            const v = Math.min(100, Math.max(33, Math.round(Number(value || 33))));
            const snap = [33, 50, 67, 100].find((x) => Math.abs(x - v) <= 2);
            return snap || v;
        },
        mulaiResizeField(e, li, bi, fi) {
            const grid = e.currentTarget.closest('.mfb-fields-grid');
            const field = this.schema.langkah?.[li]?.bagian?.[bi]?.field?.[fi];
            if (!grid || !field) return;

            this.selectField(li, bi, fi);
            this.resizeState = {
                li,
                bi,
                fi,
                startX: e.clientX,
                startWidth: grid.getBoundingClientRect().width,
                startPersen: Number(field.lebar_persen || (field.penuh ? 100 : 33)),
            };
            window.addEventListener('pointermove', this.resizeFieldBerjalan);
            window.addEventListener('pointerup', this.selesaiResizeField, { once: true });
        },
        resizeFieldBerjalan(e) {
            const s = this.resizeState;
            if (!s) return;
            const field = this.schema.langkah?.[s.li]?.bagian?.[s.bi]?.field?.[s.fi];
            if (!field) return this.selesaiResizeField();

            const deltaPersen = ((e.clientX - s.startX) / Math.max(1, s.startWidth)) * 100;
            const persen = this.snapLebar(s.startPersen + deltaPersen);
            field.lebar_persen = persen;
            field.penuh = persen >= 100;
        },
        selesaiResizeField() {
            window.removeEventListener('pointermove', this.resizeFieldBerjalan);
            this.resizeState = null;
            this.rapikanLebarField();
        },
        /**
         * Penanda masalah di kanvas. Aturan per tipe diambil dari katalog;
         * sisanya pemeriksaan lintas-field yang hanya bisa dilihat dari sini
         * (key duplikat, rujukan ke field yang tidak ada).
         */
        fieldIssues(field) {
            const issues = [];
            if (!field?.label?.trim()) issues.push('Label belum diisi');
            if (!field?.key?.trim()) issues.push('Key belum dibuat');

            const gTipe = galatTipe(field);
            if (gTipe) issues.push(gTipe);

            if (field.tampil_jika?.field === field.key) issues.push('Kondisi tidak boleh mengacu ke dirinya sendiri');

            const keyAda = new Set(this.semuaField.map((f) => f.key).filter(Boolean));
            [
                field.beda_dengan,
                ...(Array.isArray(field.reset_anak) ? field.reset_anak : []),
                ...Object.values(field.bergantung || {}),
                ...Object.values(field.saring || {}),
                field.tampil_jika?.field,
            ]
                .filter(Boolean)
                .forEach((k) => {
                    if (!keyAda.has(k)) issues.push(`Menunjuk key "${k}" yang tidak ada`);
                });

            if (field.tipe === 'foto' && field.key !== 'foto_verifikasi') {
                issues.push('Key sebaiknya "foto_verifikasi" agar dikenali sebagai foto verifikasi');
            }

            const duplicate = this.semuaField.filter((f) => f !== field && f.key && f.key === field.key).length;
            if (duplicate) issues.push('Key identifier duplikat');
            return issues;
        },
        keyUnik(base, abaikanFieldId = null) {
            const used = new Set();
            (this.schema.langkah || []).forEach((L) =>
                (L.bagian || []).forEach((B) =>
                    (B.field || []).forEach((F) => {
                        if (F.field_id !== abaikanFieldId) used.add(F.key);
                    }),
                ),
            );
            // Akhiran pembeda dihitung ke dalam batas kolom, bukan ditempel
            // begitu saja: `Field_Key` cuma varchar(60), dan basis yang sudah
            // mepet batas akan melewatinya persis saat ada key kembar - galat
            // yang baru muncul jauh kemudian, di layar KANDIDAT yang sedang
            // mengunggah berkas.
            const dasar = slugKey(base);
            let key = dasar;
            let i = 2;
            while (used.has(key)) {
                const akhiran = `_${i++}`;
                key = dasar.slice(0, MAKS_PANJANG_KEY - akhiran.length).replace(/_+$/g, '') + akhiran;
            }

            return key;
        },
        fieldDipublish(field) {
            let ketemu = false;
            (this.aktif?.published?.schema?.langkah || []).forEach((L) =>
                (L.bagian || []).forEach((B) =>
                    (B.field || []).forEach((F) => {
                        if (
                            (field.field_id && F.field_id && F.field_id === field.field_id) ||
                            (!F.field_id && F.key === field.key)
                        )
                            ketemu = true;
                    }),
                ),
            );
            return ketemu;
        },
        gayaFieldAdmin(f) {
            const persen = Number(f.lebar_persen || (f.penuh ? 100 : 33));
            const span = Math.min(12, Math.max(4, Math.round((Math.min(100, Math.max(33, persen)) / 100) * 12)));
            return { '--mfb-span': String(span) };
        },
        ikonField(t) {
            return (
                {
                    textarea: 'bi-textarea-t',
                    number: 'bi-123',
                    date: 'bi-calendar3',
                    select: 'bi-menu-button-wide',
                    radio: 'bi-record-circle',
                    checkbox: 'bi-check2-square',
                    file: 'bi-paperclip',
                    foto: 'bi-person-bounding-box',
                    phone: 'bi-telephone',
                    email: 'bi-envelope',
                    consent: 'bi-shield-check',
                    referensi: 'bi-database',
                }[t] || 'bi-input-cursor-text'
            );
        },
        clone(x) {
            return JSON.parse(JSON.stringify(x || {}));
        },
        salinJSON() {
            const jsonText = JSON.stringify(this.schema, null, 2);
            navigator.clipboard.writeText(jsonText).then(() => {
                this.notice('JSON Schema tersalin ke clipboard!');
            });
        },
        notice(x) {
            this.toast = x;
            clearTimeout(this._tm);
            this._tm = setTimeout(() => (this.toast = ''), 3500);
        },
    },
};
</script>

<style scoped>
/* ── MAIN LAYOUT & MODERN THEME ── */
.mfb-page {
    padding: 1.5rem;
    color: #0f172a;
    background: var(--wca-bg, #f8fafc);
    min-height: 100vh;
}

/* Global Icon Alignment & Centering */
.mfb-page i.bi,
.mfb-page .bi {
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    vertical-align: middle !important;
    line-height: 1 !important;
    height: 1em;
    width: 1em;
}

/* ── NATIVE SEGMENTED BUTTON GROUP ── */
.mfb-segmented {
    display: flex;
    background: #f1f5f9;
    border: 1px solid #cbd5e1;
    border-radius: 12px;
    padding: 3px;
    gap: 3px;
    width: 100%;
}

.mfb-segmented__item {
    flex: 1;
    border: 0;
    background: transparent;
    color: #64748b;
    font-family: inherit;
    font-size: 12px;
    font-weight: 700;
    padding: 0.5rem 0.75rem;
    border-radius: 9px;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.4rem;
    transition: all 0.18s cubic-bezier(0.4, 0, 0.2, 1);
    white-space: nowrap;
}

.mfb-segmented__item:hover:not(.active) {
    color: #0f172a;
    background: rgba(255, 255, 255, 0.6);
}

.mfb-segmented__item.active {
    background: #ffffff;
    color: #4f46e5;
    box-shadow: 0 2px 8px rgba(79, 70, 229, 0.2);
}

:deep(.el-input__wrapper),
:deep(.el-select__wrapper) {
    border-radius: 10px !important;
    background-color: #ffffff !important;
    border: 1px solid #cbd5e1 !important;
    box-shadow: none !important;
    padding: 4px 11px !important;
    transition: all 0.15s ease !important;
}

:deep(.el-input__wrapper:hover),
:deep(.el-select__wrapper:hover) {
    border-color: #94a3b8 !important;
}

:deep(.el-input__wrapper.is-focus),
:deep(.el-select__wrapper.is-focused) {
    border-color: #6366f1 !important;
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15) !important;
}

:deep(.el-input__inner),
:deep(.el-select__selected-item) {
    color: #0f172a !important;
    font-weight: 600 !important;
    font-size: 12.5px !important;
}

:deep(.el-slider__bar) {
    background: linear-gradient(90deg, #6366f1, #4f46e5) !important;
    border-radius: 4px !important;
}

:deep(.el-slider__button) {
    border-color: #4f46e5 !important;
    width: 16px !important;
    height: 16px !important;
    box-shadow: 0 2px 6px rgba(79, 70, 229, 0.3) !important;
}

:deep(.el-checkbox) {
    margin-right: 0;
}

:deep(.el-checkbox__input.is-checked .el-checkbox__inner) {
    background-color: #4f46e5 !important;
    border-color: #4f46e5 !important;
    border-radius: 6px !important;
}

:deep(.el-checkbox__inner) {
    border-radius: 6px !important;
    border-color: #cbd5e1 !important;
    width: 16px !important;
    height: 16px !important;
}

:deep(.el-checkbox__label) {
    color: #334155 !important;
    font-weight: 700 !important;
    font-size: 12px !important;
}

/* Header & Title Section */
.mfb-phead {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1.5rem;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    padding: 1.15rem 1.5rem;
    margin-bottom: 1.25rem;
    box-shadow: 0 4px 20px -2px rgba(15, 23, 42, 0.05);
}

.mfb-icon-badge {
    width: 48px;
    height: 48px;
    border-radius: 14px;
    background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
    color: #ffffff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    box-shadow: 0 8px 20px -4px rgba(79, 70, 229, 0.4);
}

.mfb-metrics {
    display: flex;
    gap: 0.65rem;
    align-items: center;
}

.mfb-metric-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    background: #f1f5f9;
    color: #475569;
    padding: 0.45rem 0.85rem;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    border: 1px solid #e2e8f0;
}

.mfb-metric-pill b {
    color: #0f172a;
    font-weight: 800;
}

.mfb-metric-pill--published {
    background: #ecfdf5;
    color: #047857;
    border-color: #a7f3d0;
}

.mfb-metric-pill--published b {
    color: #065f46;
}

.mfb-metric-pill--draft {
    background: #fffbeb;
    color: #b45309;
    border-color: #fde68a;
}

.mfb-metric-pill--draft b {
    color: #92400e;
}

.mfb-phead__actions {
    display: flex;
    gap: 0.5rem;
    align-items: center;
}

/* Button Variants */
.mfb-btn {
    border: 0;
    border-radius: 10px;
    padding: 0.6rem 1.1rem;
    font-family: inherit;
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.45rem;
    transition: all 0.18s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
}

.mfb-btn:disabled {
    opacity: 0.45;
    cursor: not-allowed;
    box-shadow: none !important;
}

.mfb-btn--glow {
    background: linear-gradient(135deg, #4f46e5 0%, #4338ca 100%);
    color: #ffffff;
    box-shadow: 0 6px 14px -3px rgba(79, 70, 229, 0.4);
}

.mfb-btn--glow:hover:not(:disabled) {
    transform: translateY(-1px);
    box-shadow: 0 8px 18px -2px rgba(79, 70, 229, 0.5);
}

.mfb-btn--indigo {
    background: #4f46e5;
    color: #ffffff;
}

.mfb-btn--indigo:hover:not(:disabled) {
    background: #4338ca;
}

.mfb-btn--emerald {
    background: #059669;
    color: #ffffff;
    box-shadow: 0 4px 12px -2px rgba(5, 150, 105, 0.35);
}

.mfb-btn--emerald:hover:not(:disabled) {
    background: #047857;
}

.mfb-btn--soft {
    background: #eef2ff;
    color: #4338ca;
    border: 1px solid #c7d2fe;
}

.mfb-btn--soft:hover:not(:disabled) {
    background: #e0e7ff;
}

.mfb-btn--danger {
    background: #fee2e2;
    color: #b91c1c;
    border: 1px solid #fca5a5;
}

.mfb-btn--danger:hover:not(:disabled) {
    background: #fca5a5;
}

.mfb-btn--full {
    width: 100%;
}

.mfb-btn--sm {
    padding: 0.4rem 0.75rem;
    font-size: 12px;
}

.mfb-btn-badge {
    background: #ef4444;
    color: #ffffff;
    font-size: 10px;
    padding: 2px 6px;
    border-radius: 10px;
}

.mfb-spin {
    animation: mfbSpin 1s linear infinite;
}

@keyframes mfbSpin {
    100% {
        transform: rotate(360deg);
    }
}

/* ── LAYOUT GRID ── */
.mfb-layout {
    display: grid;
    grid-template-columns: 20rem minmax(0, 1fr);
    gap: 1.25rem;
    align-items: start;
}

/* Sidebar Catalog */
.mfb-sidebar {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    padding: 1.1rem;
    position: sticky;
    top: 1.25rem;
    max-height: calc(100vh - 2.5rem);
    display: flex;
    flex-direction: column;
    box-shadow: 0 4px 20px -2px rgba(15, 23, 42, 0.04);
}

.mfb-sidebar__title {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 13px;
    font-weight: 800;
    color: #0f172a;
    margin-bottom: 0.75rem;
    padding-bottom: 0.5rem;
    border-bottom: 1px solid #f1f5f9;
}

.mfb-sidebar__count {
    background: #eef2ff;
    color: #4338ca;
    font-size: 11px;
    font-weight: 800;
    padding: 2px 8px;
    border-radius: 10px;
}

.mfb-sidebar__head {
    margin-bottom: 0.85rem;
}

.mfb-search {
    position: relative;
    margin-bottom: 0.65rem;
}

.mfb-search__ico {
    position: absolute;
    left: 0.75rem;
    top: 50%;
    transform: translateY(-50%);
    color: #94a3b8;
    font-size: 14px;
}

.mfb-search__input {
    width: 100%;
    padding: 0.55rem 2rem 0.55rem 2.2rem;
    border: 1px solid #cbd5e1;
    border-radius: 10px;
    font-size: 12.5px;
    outline: 0;
    transition: border-color 0.15s;
}

.mfb-search__input:focus {
    border-color: #6366f1;
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.12);
}

.mfb-search__clear {
    position: absolute;
    right: 0.5rem;
    top: 50%;
    transform: translateY(-50%);
    border: 0;
    background: transparent;
    color: #94a3b8;
    cursor: pointer;
    font-size: 12px;
}

.mfb-filter-chips {
    display: flex;
    gap: 0.35rem;
    overflow-x: auto;
    padding-bottom: 0.2rem;
}

.mfb-chip {
    border: 1px solid #e2e8f0;
    background: #f8fafc;
    color: #64748b;
    border-radius: 14px;
    padding: 0.25rem 0.65rem;
    font-size: 11px;
    font-weight: 700;
    cursor: pointer;
    white-space: nowrap;
}

.mfb-chip.active {
    background: #eef2ff;
    color: #4338ca;
    border-color: #818cf8;
}

.mfb-sidebar__list {
    overflow-y: auto;
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 0.55rem;
    padding-right: 0.2rem;
}

.mfb-card {
    border: 1px solid #e2e8f0;
    background: #ffffff;
    border-radius: 12px;
    padding: 0.85rem;
    text-align: left;
    cursor: pointer;
    transition: all 0.15s ease;
    position: relative;
}

.mfb-card:hover {
    border-color: #cbd5e1;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px -2px rgba(15, 23, 42, 0.06);
}

.mfb-card.active {
    border-color: #6366f1;
    background: #faf5ff;
    box-shadow: 0 4px 16px -2px rgba(99, 102, 241, 0.18);
}

.mfb-card.active::before {
    content: '';
    position: absolute;
    left: 0;
    top: 10px;
    bottom: 10px;
    width: 4px;
    background: #6366f1;
    border-radius: 0 4px 4px 0;
}

.mfb-card.off {
    opacity: 0.65;
}

.mfb-card__head {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 0.5rem;
    margin-bottom: 0.45rem;
}

.mfb-card__titlegroup {
    display: flex;
    flex-direction: column;
}

.mfb-card__title {
    font-size: 13px;
    font-weight: 800;
    color: #0f172a;
    line-height: 1.3;
}

.mfb-card__code {
    font-size: 10.5px;
    color: #64748b;
    font-family: monospace;
}

.mfb-card__cat {
    background: #e2e8f0;
    color: #334155;
    font-size: 10px;
    font-weight: 700;
    padding: 2px 6px;
    border-radius: 6px;
}

.mfb-card__description {
    margin: 0.45rem 0 0;
    color: #64748b;
    font-size: 11px;
    line-height: 1.4;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.mfb-card__meta {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 0.5rem;
    padding-top: 0.5rem;
    border-top: 1px dashed #f1f5f9;
}

.mfb-card__badges {
    display: flex;
    gap: 0.35rem;
    align-items: center;
}

.mfb-card__pub {
    font-size: 10.5px;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
}

.mfb-card__pub.is-pub {
    color: #059669;
}

.mfb-card__pub.is-draft {
    color: #d97706;
}

.mfb-card__version {
    color: #64748b;
    font-size: 10px;
    font-weight: 700;
}

.mfb-card__status {
    font-size: 9.5px;
    font-weight: 800;
    padding: 1px 5px;
    border-radius: 4px;
    text-transform: uppercase;
}

.mfb-card__status.is-active {
    background: #dcfce7;
    color: #15803d;
}

.mfb-card__status.is-inactive {
    background: #f1f5f9;
    color: #64748b;
}

.mfb-card__actions {
    display: flex;
    gap: 0.25rem;
}

.mfb-iconbtn {
    border: 0;
    background: transparent;
    color: #94a3b8;
    border-radius: 6px;
    width: 24px;
    height: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    line-height: 1;
    cursor: pointer;
    font-size: 12px;
    transition:
        background 0.15s,
        color 0.15s;
}

.mfb-iconbtn:hover {
    background: #f1f5f9;
    color: #334155;
}

.mfb-iconbtn--warn:hover {
    background: #fef3c7;
    color: #b45309;
}

.mfb-iconbtn--ok:hover {
    background: #dcfce7;
    color: #15803d;
}

.mfb-iconbtn--danger:hover {
    background: #fee2e2;
    color: #b91c1c;
}

.mfb-empty-search {
    padding: 2rem 1rem;
    text-align: center;
    color: #94a3b8;
}

.mfb-empty-search i {
    font-size: 2rem;
    margin-bottom: 0.5rem;
}

.mfb-empty-search p {
    font-size: 12px;
    margin-bottom: 0.75rem;
}

/* ── WORKSPACE PANEL ── */
.mfb-work {
    min-width: 0;
}

.mfb-panel {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    padding: 1.35rem;
    box-shadow: 0 4px 20px -2px rgba(15, 23, 42, 0.04);
}

.mfb-meta-bar {
    display: grid;
    grid-template-columns: 1.5fr 1fr 1.2fr;
    gap: 1rem;
    margin-bottom: 1rem;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 1.1rem;
}

.mfb-meta-bar__item label {
    display: block;
    font-size: 11.5px;
    font-weight: 800;
    color: #475569;
    margin-bottom: 0.35rem;
    text-transform: uppercase;
    letter-spacing: 0.02em;
}

.mfb-master-notice {
    display: flex;
    align-items: flex-start;
    gap: 0.65rem;
    margin: 0.85rem 0;
    padding: 0.7rem 0.8rem;
    border: 1px solid #c7d2fe;
    border-radius: 9px;
    background: #eef2ff;
    color: #3730a3;
}

.mfb-master-notice > i {
    margin-top: 0.1rem;
    font-size: 1rem;
}

.mfb-master-notice div {
    display: grid;
    gap: 0.15rem;
}

.mfb-master-notice strong {
    font-size: 12px;
}

.mfb-master-notice span {
    color: #4f46e5;
    font-size: 11px;
    line-height: 1.45;
}

.mfb-meta-extra {
    margin-bottom: 1.25rem;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 0.65rem 0.85rem;
    background: #ffffff;
}

.mfb-meta-extra summary {
    font-size: 12px;
    font-weight: 700;
    color: #475569;
    cursor: pointer;
}

.mfb-meta-extra__grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.85rem;
    margin-top: 0.75rem;
    padding-top: 0.75rem;
    border-top: 1px dashed #e2e8f0;
}

.mfb-meta-extra__grid label {
    display: block;
    font-size: 11px;
    font-weight: 700;
    color: #64748b;
    margin-bottom: 0.3rem;
}

/* Tabs Header */
.mfb-tabs {
    display: flex;
    gap: 0.4rem;
    border-bottom: 2px solid #f1f5f9;
    margin-bottom: 1.25rem;
}

.mfb-tabs button {
    border: 0;
    background: transparent;
    padding: 0.75rem 1.25rem;
    font-family: inherit;
    font-size: 13px;
    font-weight: 700;
    color: #64748b;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    border-bottom: 2px solid transparent;
    margin-bottom: -2px;
    transition: all 0.15s;
}

.mfb-tabs button:hover {
    color: #4338ca;
}

.mfb-tabs button.active {
    color: #4f46e5;
    border-bottom-color: #4f46e5;
}

/* Builder Layout Grid */
.mfb-builder {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 21rem;
    gap: 1.25rem;
    align-items: start;
}

/* Toolbar & Palette */
.mfb-toolbar {
    display: flex;
    flex-wrap: wrap;
    gap: 0.65rem;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 1rem;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 0.75rem 0.9rem;
}

.mfb-toolbar__group {
    display: flex;
    gap: 0.4rem;
}

.mfb-btn-tool {
    border: 1px solid #cbd5e1;
    background: #ffffff;
    color: #334155;
    border-radius: 8px;
    padding: 0.45rem 0.75rem;
    font-size: 12px;
    font-weight: 700;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
}

.mfb-btn-tool--step {
    background: #eef2ff;
    color: #4338ca;
    border-color: #c7d2fe;
}

.mfb-btn-tool--section {
    background: #f0fdf4;
    color: #15803d;
    border-color: #bbf7d0;
}

.mfb-toolbar__palette {
    display: flex;
    gap: 0.35rem;
    flex-wrap: wrap;
}

.mfb-palette-btn {
    border: 1px solid #e2e8f0;
    background: #ffffff;
    color: #475569;
    border-radius: 8px;
    padding: 0.4rem 0.65rem;
    font-size: 11.5px;
    font-weight: 600;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    transition: all 0.15s;
}

.mfb-palette-btn:hover {
    border-color: #818cf8;
    color: #4338ca;
    background: #eef2ff;
}

.mfb-val-badge {
    border: 0;
    border-radius: 20px;
    padding: 0.4rem 0.8rem;
    font-size: 11.5px;
    font-weight: 800;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
}

.mfb-val-badge.is-valid {
    background: #ecfdf5;
    color: #047857;
}

.mfb-val-badge.has-issue {
    background: #fef2f2;
    color: #b91c1c;
}

/* ── STEPPER & SECTION CARDS ── */
.mfb-step-card {
    border: 1px solid #cbd5e1;
    background: #f8fafc;
    border-radius: 14px;
    padding: 1rem;
    margin-bottom: 1.25rem;
    transition: border-color 0.15s;
}

.mfb-step-card.selected {
    border-color: #6366f1;
    box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.2);
}

.mfb-step-card__head {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    margin-bottom: 0.85rem;
}

.mfb-step-num {
    background: #6366f1;
    color: #ffffff;
    font-size: 11px;
    font-weight: 800;
    padding: 3px 9px;
    border-radius: 10px;
    white-space: nowrap;
}

.mfb-step-card__ico {
    display: inline-flex;
    align-items: center;
}

.mfb-step-card__input {
    flex: 1;
    border: 1px solid transparent;
    border-radius: 8px;
    padding: 0.35rem 0.65rem;
    font-family: inherit;
    font-size: 14px;
    font-weight: 800;
    color: #0f172a;
    background: transparent;
    transition: all 0.15s ease;
    min-width: 0;
}

.mfb-step-card__input:hover,
.mfb-step-card__input:focus {
    background: #ffffff;
    border-color: #cbd5e1;
    box-shadow: 0 2px 6px rgba(15, 23, 42, 0.05);
}

.mfb-step-card__act button,
.mfb-sec-card__act button {
    border: 0;
    background: transparent;
    color: #94a3b8;
    cursor: pointer;
    padding: 0.3rem 0.45rem;
    border-radius: 6px;
    font-size: 12.5px;
}

.mfb-step-card__act button:hover,
.mfb-sec-card__act button:hover {
    background: #fee2e2;
    color: #b91c1c;
}

.mfb-sec-card {
    border: 1px solid #e2e8f0;
    background: #ffffff;
    border-radius: 12px;
    padding: 0.85rem;
    margin-bottom: 0.85rem;
    transition: border-color 0.15s;
}

.mfb-sec-card.selected {
    border-color: #6366f1;
    box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.18);
}

.mfb-sec-card__head {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 0.75rem;
}

.mfb-sec-card__input {
    flex: 1;
    border: 0;
    background: transparent;
    font-family: inherit;
    font-size: 13px;
    font-weight: 700;
    color: #1e293b;
}

.mfb-cond-badge {
    background: #fffbeb;
    color: #b45309;
    border: 1px solid #fde68a;
    font-size: 10px;
    font-weight: 700;
    padding: 2px 6px;
    border-radius: 6px;
}

.mfb-sec-card__act {
    display: flex;
    gap: 0.25rem;
}

/* ── FIELDS GRID ── */
.mfb-fields-grid {
    display: grid;
    grid-template-columns: repeat(12, minmax(0, 1fr));
    gap: 0.75rem;
}

.mfb-field-card {
    grid-column: span var(--mfb-span, 4);
    position: relative;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    background: #ffffff;
    padding: 0.75rem 2rem 0.75rem 0.75rem;
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
    cursor: pointer;
    transition: all 0.15s;
}

.mfb-field-card.full {
    grid-column: 1 / -1;
}

.mfb-field-card:hover {
    border-color: #818cf8;
    box-shadow: 0 4px 12px rgba(99, 102, 241, 0.08);
}

.mfb-field-card.selected {
    border-color: #6366f1;
    background: #faf5ff;
    box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.25);
}

.mfb-field-card.has-error {
    border-color: #fca5a5;
    background: #fff5f5;
}

.mfb-field-card__top {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    min-width: 0;
}

.mfb-field-card__ico {
    color: #6366f1;
    font-size: 14px;
}

.mfb-field-card__label {
    font-size: 12.5px;
    font-weight: 700;
    color: #0f172a;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.mfb-field-card__meta {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 10.5px;
    color: #64748b;
}

.mfb-field-card__type {
    background: #f1f5f9;
    padding: 1px 5px;
    border-radius: 4px;
    font-family: monospace;
}

/* TOMBOL KECIL SEKUNDER ("+ Tambah Opsi Baru", "Reset Filter").
   Kelas ini dipakai sejak awal tapi TIDAK PERNAH punya aturan di berkas mana
   pun, jadi tombolnya jatuh ke tampilan bawaan peramban -- kotak abu-abu yang
   tampak seperti sisa markup, bukan tombol yang boleh ditekan. */
.mfb-mini {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    padding: 0.4rem 0.7rem;
    border: 1px dashed #c7d2fe;
    border-radius: 8px;
    background: #f8faff;
    color: #4f46e5;
    font-family: inherit;
    font-size: 11.5px;
    font-weight: 700;
    cursor: pointer;
    transition: background 0.16s, border-color 0.16s, color 0.16s;
}

.mfb-mini:hover {
    background: #eef2ff;
    border-color: #a5b4fc;
    color: #4338ca;
}

.mfb-mini:active {
    background: #e0e7ff;
}

.mfb-mini .bi {
    font-size: 12px;
}

.mfb-badge-req {
    background: #fee2e2;
    color: #b91c1c;
    font-size: 9.5px;
    font-weight: 800;
    padding: 1px 4px;
    border-radius: 4px;
}

/* Wajib BERSYARAT -- sengaja tidak merah. Merah dibaca sebagai "pasti wajib",
   dan kolom ini belum tentu mengikat. */
.mfb-badge-req--jika {
    background: #ede9fe;
    color: #6d28d9;
}

.mfb-field-card__width {
    color: #94a3b8;
    font-size: 10px;
}

.mfb-cond-pill {
    color: #d97706;
    font-size: 11px;
}

.mfb-issue-icon {
    color: #dc2626;
    font-size: 11px;
}

.mfb-field-card__actions {
    position: absolute;
    top: 0.4rem;
    right: 0.4rem;
    display: flex;
    gap: 0.15rem;
}

.mfb-field-btn {
    border: 0;
    background: transparent;
    color: #94a3b8;
    width: 22px;
    height: 22px;
    border-radius: 4px;
    display: flex;
    align-items: center;
    justify-content: center;
    line-height: 1;
    cursor: pointer;
    font-size: 11px;
}

.mfb-field-btn:hover {
    background: #e2e8f0;
    color: #334155;
}

.mfb-field-btn--danger:hover {
    background: #fee2e2;
    color: #b91c1c;
}

.mfb-resize-handle {
    position: absolute;
    right: 0.3rem;
    bottom: 0.3rem;
    width: 18px;
    height: 18px;
    display: flex;
    align-items: center;
    justify-content: center;
    line-height: 1;
    border-radius: 4px;
    color: #cbd5e1;
    cursor: ew-resize;
    touch-action: none;
    font-size: 11px;
}

.mfb-resize-handle:hover {
    color: #6366f1;
    background: #eef2ff;
}

.mfb-drag-step,
.mfb-drag-sec,
.mfb-drag-field {
    color: #cbd5e1;
    cursor: grab;
}

/* ── RIGHT INSPECTOR PANEL ── */
.mfb-inspector {
    border: 1px solid #e2e8f0;
    background: #ffffff;
    border-radius: 16px;
    padding: 1.1rem;
    position: sticky;
    top: 1.25rem;
    max-height: calc(100vh - 2.5rem);
    overflow-y: auto;
    box-shadow: 0 4px 16px -2px rgba(15, 23, 42, 0.03);
}

.mfb-inspector__head {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
    padding-bottom: 0.6rem;
    border-bottom: 1px solid #f1f5f9;
}

.mfb-inspector__head h3 {
    margin: 0;
    font-size: 14px;
    font-weight: 800;
    display: flex;
    gap: 0.4rem;
    align-items: center;
    color: #0f172a;
}

.mfb-inspector__tag {
    background: #eef2ff;
    color: #4338ca;
    font-size: 11px;
    font-weight: 800;
    padding: 2px 8px;
    border-radius: 10px;
    font-family: monospace;
}

.mfb-inspector__group {
    margin-bottom: 0.95rem;
}

.mfb-inspector__group label {
    display: block;
    font-size: 11.5px;
    font-weight: 800;
    color: #475569;
    margin-bottom: 0.35rem;
}

.mfb-key-input {
    display: flex;
    align-items: center;
    gap: 0.4rem;
}

.mfb-lock-tag {
    background: #fee2e2;
    color: #b91c1c;
    font-size: 10px;
    font-weight: 800;
    padding: 2px 6px;
    border-radius: 4px;
    white-space: nowrap;
}

.mfb-help {
    font-size: 11px;
    color: #64748b;
    margin-top: 0.25rem;
}

.mfb-inspector__checks {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
    margin-bottom: 0.95rem;
    background: #f8fafc;
    padding: 0.65rem;
    border-radius: 8px;
    border: 1px solid #f1f5f9;
}

.mfb-slider {
    display: grid;
    grid-template-columns: auto minmax(0, 1fr) auto;
    gap: 0.55rem;
    align-items: center;
    color: #64748b;
    font-size: 11.5px;
    margin-top: 0.4rem;
}

.mfb-condition-row {
    display: flex;
    gap: 0.4rem;
    margin-top: 0.4rem;
}

.mfb-inspector__hint {
    display: block;
    margin-top: 0.4rem;
    font-size: 11px;
    line-height: 1.5;
    color: #94a3b8;
}
.mfb-inspector__hint b {
    color: #4f46e5;
    font-weight: 700;
}

.mfb-optrow {
    display: flex;
    gap: 0.4rem;
    margin-bottom: 0.35rem;
}

.mfb-optrow button {
    border: 0;
    background: transparent;
    color: #94a3b8;
    cursor: pointer;
    padding: 0 0.4rem;
}

.mfb-optrow button:hover {
    color: #ef4444;
}

.mfb-preset-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.4rem;
}

.mfb-preset-grid button {
    border: 1px solid #e2e8f0;
    background: #f8fafc;
    border-radius: 10px;
    padding: 0.65rem;
    text-align: left;
    cursor: pointer;
    display: flex;
    gap: 0.5rem;
    align-items: center;
    transition: all 0.15s ease;
}

.mfb-preset-grid button:hover {
    border-color: #818cf8;
    background: #eef2ff;
}

.mfb-preset-grid i {
    color: #4f46e5;
    font-size: 1.15rem;
    flex-shrink: 0;
}

.mfb-preset-grid button div {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
    flex: 1;
    min-width: 0;
}

.mfb-preset-grid strong {
    display: block;
    font-size: 11.5px;
    font-weight: 800;
    color: #0f172a;
    line-height: 1.3;
}

.mfb-preset-grid small {
    display: block;
    color: #64748b;
    font-size: 10px;
    line-height: 1.3;
}

.mfb-block-list {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.mfb-block-list button {
    width: 100%;
    border: 1px solid #e2e8f0;
    background: #ffffff;
    border-radius: 12px;
    padding: 0.75rem 0.85rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    text-align: left;
    cursor: pointer;
    transition: all 0.18s ease;
}

.mfb-block-list button:hover {
    border-color: #6366f1;
    background: #faf5ff;
    box-shadow: 0 4px 12px rgba(99, 102, 241, 0.08);
}

.mfb-block-list button > i:first-child {
    color: #4f46e5;
    font-size: 1.35rem;
    flex-shrink: 0;
}

.mfb-block-list button div {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
    flex: 1;
    min-width: 0;
}

.mfb-block-list button strong {
    display: block;
    font-size: 12.5px;
    font-weight: 800;
    color: #0f172a;
    line-height: 1.3;
}

.mfb-block-list button small {
    display: block;
    font-size: 11px;
    color: #64748b;
    line-height: 1.3;
}

.mfb-block-list button > i:last-child {
    color: #94a3b8;
    font-size: 1.1rem;
    flex-shrink: 0;
    transition: color 0.15s;
}

.mfb-block-list button:hover > i:last-child {
    color: #4f46e5;
}

.mfb-block-list button:hover {
    border-color: #818cf8;
    background: #f5f3ff;
}

.mfb-block-list button > i:first-child {
    color: #4f46e5;
    font-size: 1.2rem;
}

.mfb-inspector__empty {
    padding: 3rem 1rem;
    text-align: center;
    color: #94a3b8;
}

.mfb-inspector__empty i {
    font-size: 2.5rem;
    color: #cbd5e1;
    margin-bottom: 0.5rem;
}

.mfb-inspector__empty h4 {
    margin: 0 0 0.3rem;
    color: #334155;
    font-weight: 700;
}

.mfb-inspector__empty p {
    font-size: 12px;
    line-height: 1.5;
}

/* ── PREVIEW TAB & MOCK BROWSER ── */
.mfb-preview {
    border: 1px dashed #cbd5e1;
    border-radius: 16px;
    padding: 1.25rem;
    background: #f8fafc;
}

.mfb-preview__bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.25rem;
}

.mfb-preview__info {
    display: flex;
    gap: 0.5rem;
    align-items: center;
    font-size: 13px;
    font-weight: 800;
    color: #334155;
}

.mfb-preview__frame-wrapper {
    overflow-x: auto;
    padding: 0.5rem 0;
}

.mfb-mock-browser {
    border: 1px solid #cbd5e1;
    border-radius: 16px;
    background: #ffffff;
    box-shadow: 0 12px 35px -5px rgba(15, 23, 42, 0.1);
    overflow: hidden;
}

.mfb-mock-browser__bar {
    background: #f1f5f9;
    border-bottom: 1px solid #e2e8f0;
    padding: 0.6rem 1rem;
    display: flex;
    align-items: center;
    gap: 1rem;
}

.mfb-mock-browser__dots {
    display: flex;
    gap: 0.35rem;
}

.mfb-mock-browser__dots span {
    width: 10px;
    height: 10px;
    border-radius: 50%;
}

.mfb-mock-browser__dots span:nth-child(1) {
    background: #ef4444;
}
.mfb-mock-browser__dots span:nth-child(2) {
    background: #f59e0b;
}
.mfb-mock-browser__dots span:nth-child(3) {
    background: #10b981;
}

.mfb-mock-browser__url {
    background: #ffffff;
    border: 1px solid #cbd5e1;
    border-radius: 20px;
    padding: 0.2rem 1rem;
    font-size: 11px;
    color: #64748b;
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    font-family: monospace;
}

.mfb-preview__frame {
    padding: 1.5rem;
    transition: width 0.25s cubic-bezier(0.4, 0, 0.2, 1);
}

.mfb-portal-shell {
    background: #f8fafc;
    border-radius: 16px;
    padding: 1.5rem;
    border: 1px solid #e2e8f0;
}

.mfb-portal-shell__header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 1rem;
    margin-bottom: 1.25rem;
    padding-bottom: 1.25rem;
    border-bottom: 1px solid #e2e8f0;
}

.mfb-portal-shell__brand {
    display: flex;
    align-items: center;
    gap: 0.85rem;
}

.mfb-portal-shell__logo {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    background: linear-gradient(135deg, #4f46e5 0%, #6366f1 100%);
    color: #ffffff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.35rem;
    box-shadow: 0 4px 12px rgba(79, 70, 229, 0.3);
}

.mfb-portal-shell__portal-name {
    display: block;
    font-size: 10.5px;
    font-weight: 800;
    color: #6366f1;
    letter-spacing: 0.05em;
    margin-bottom: 2px;
}

.mfb-portal-shell__title {
    margin: 0;
    font-size: 1.25rem;
    font-weight: 800;
    color: #0f172a;
    line-height: 1.3;
}

.mfb-portal-shell__badges {
    display: flex;
    gap: 0.5rem;
    align-items: center;
}

.mfb-portal-shell__tag {
    background: #eef2ff;
    color: #4338ca;
    font-size: 11px;
    font-weight: 700;
    padding: 4px 10px;
    border-radius: 20px;
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
}

.mfb-portal-shell__status {
    background: #dcfce7;
    color: #15803d;
    font-size: 11px;
    font-weight: 700;
    padding: 4px 10px;
    border-radius: 20px;
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
}

.mfb-portal-shell__status i {
    font-size: 6px;
}

.mfb-portal-shell__notice {
    display: flex;
    gap: 0.75rem;
    align-items: flex-start;
    background: #eff6ff;
    border: 1px solid #bfdbfe;
    border-radius: 12px;
    padding: 0.85rem 1.1rem;
    margin-bottom: 1.5rem;
    color: #1e40af;
}

.mfb-portal-shell__notice i {
    font-size: 1.2rem;
    color: #3b82f6;
    margin-top: 2px;
}

.mfb-portal-shell__notice strong {
    display: block;
    font-size: 12px;
    font-weight: 800;
    margin-bottom: 2px;
}

.mfb-portal-shell__notice p {
    margin: 0;
    font-size: 12px;
    color: #1e3a8a;
    line-height: 1.4;
}

.mfb-portal-shell__body {
    background: #ffffff;
    border-radius: 16px;
    box-shadow: 0 4px 20px -2px rgba(15, 23, 42, 0.06);
    padding: 1.5rem;
}

/* Smartphone Device Mockup Styling */
.mfb-portal-shell--mobile {
    border-radius: 36px !important;
    border: 10px solid #1e293b !important;
    padding: 0.75rem 1rem 1.25rem !important;
    background: #f8fafc;
    box-shadow: 0 25px 60px -15px rgba(15, 23, 42, 0.35) !important;
    position: relative;
    overflow: hidden;
}

.mfb-mobile-status-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.2rem 0.5rem 0.6rem;
    font-size: 11px;
    font-weight: 700;
    color: #334155;
}

.mfb-mobile-status-bar__notch {
    width: 90px;
    height: 18px;
    background: #1e293b;
    border-radius: 0 0 12px 12px;
    margin-top: -0.75rem;
}

.mfb-mobile-status-bar__icons {
    display: flex;
    gap: 0.3rem;
    align-items: center;
    font-size: 11px;
}

.mfb-mobile-home-indicator {
    display: flex;
    justify-content: center;
    padding-top: 0.85rem;
}

.mfb-mobile-home-indicator span {
    width: 120px;
    height: 4px;
    background: #cbd5e1;
    border-radius: 4px;
}

/* Mobile Portal Header & Notice Responsive Styling */
.mfb-portal-shell--mobile .mfb-portal-shell__header {
    flex-direction: column;
    gap: 0.75rem;
    padding-bottom: 0.85rem;
    margin-bottom: 0.85rem;
}

.mfb-portal-shell--mobile .mfb-portal-shell__title {
    font-size: 1.05rem;
}

.mfb-portal-shell--mobile .mfb-portal-shell__badges {
    flex-wrap: wrap;
}

.mfb-portal-shell--mobile .mfb-portal-shell__notice {
    padding: 0.65rem 0.85rem;
    font-size: 11.5px;
    margin-bottom: 1rem;
}

.mfb-portal-shell--mobile .mfb-portal-shell__body {
    padding: 1rem 0.85rem;
    border-radius: 14px;
}

/* Mobile Stepper & Buttons Optimization */
.mfb-preview-frame--mobile :deep(.t2__steps) {
    gap: 0.2rem;
    padding-bottom: 0.5rem;
}

.mfb-preview-frame--mobile :deep(.t2__step) {
    min-width: 2.2rem;
}

.mfb-preview-frame--mobile :deep(.t2__lbl) {
    font-size: 9.5px;
}

.mfb-preview-frame--mobile :deep(.t2__foot) {
    flex-wrap: wrap;
    gap: 0.5rem;
}

.mfb-preview-frame--mobile :deep(.t2__btn) {
    width: 100%;
    justify-content: center;
}

/* ── STATS & AUDIT TAB ── */
.mfb-stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.mfb-stat-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    padding: 1.2rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    box-shadow: 0 2px 12px rgba(15, 23, 42, 0.03);
}

.mfb-stat-card__icon {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    line-height: 1;
    font-size: 1.4rem;
}

.mfb-stat-card__icon--indigo {
    background: #eef2ff;
    color: #4f46e5;
}
.mfb-stat-card__icon--sky {
    background: #e0f2fe;
    color: #0284c7;
}
.mfb-stat-card__icon--emerald {
    background: #ecfdf5;
    color: #059669;
}
.mfb-stat-card__icon--amber {
    background: #fffbeb;
    color: #d97706;
}

.mfb-stat-card strong {
    display: block;
    font-size: 1.5rem;
    font-weight: 800;
    color: #0f172a;
}

.mfb-stat-card span {
    font-size: 12px;
    color: #64748b;
}

.mfb-audit-box {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    padding: 1.35rem;
}

.mfb-audit-box h4 {
    margin: 0 0 1rem;
    font-size: 14px;
    color: #0f172a;
    display: flex;
    align-items: center;
    gap: 0.45rem;
}

.mfb-audit-grid {
    display: grid;
    grid-template-columns: 1fr 1fr 1.5fr;
    gap: 1.25rem;
}

.mfb-audit-grid label {
    display: block;
    font-size: 11.5px;
    font-weight: 800;
    color: #64748b;
    margin-bottom: 0.35rem;
}

.mfb-code-text {
    font-family: monospace;
    font-size: 13px;
    font-weight: 700;
    background: #f1f5f9;
    padding: 4px 8px;
    border-radius: 6px;
}

.mfb-badge-text {
    font-size: 12px;
    font-weight: 800;
    padding: 3px 8px;
    border-radius: 6px;
}

.mfb-badge-text.ok {
    background: #dcfce7;
    color: #15803d;
}

.mfb-badge-text.off {
    background: #f1f5f9;
    color: #64748b;
}

/* ── JSON TAB ── */
.mfb-json__bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.75rem;
    font-size: 12.5px;
    font-weight: 700;
    color: #475569;
}

.mfb-codeblock {
    margin: 0;
    max-height: 65vh;
    overflow: auto;
    background: #0f172a;
    color: #38bdf8;
    border-radius: 12px;
    padding: 1.25rem;
    font-family: monospace;
    font-size: 12.5px;
    line-height: 1.5;
}

/* ── EMPTY WORKSPACE ── */
.mfb-empty-work {
    min-height: 24rem;
    display: grid;
    place-items: center;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    padding: 2rem;
}

.mfb-empty-work__content {
    text-align: center;
    max-width: 400px;
}

.mfb-empty-work__content i {
    font-size: 3.5rem;
    color: #6366f1;
    margin-bottom: 0.85rem;
}

.mfb-empty-work__content h3 {
    margin: 0 0 0.4rem;
    font-size: 1.2rem;
    font-weight: 800;
    color: #0f172a;
}

.mfb-empty-work__content p {
    color: #64748b;
    font-size: 13px;
    line-height: 1.5;
    margin-bottom: 1.25rem;
}

/* ── MODAL & TOAST ── */
.mfb-modal {
    position: fixed;
    inset: 0;
    z-index: 100;
    background: rgba(15, 23, 42, 0.45);
    backdrop-filter: blur(4px);
    display: grid;
    place-items: center;
    padding: 1rem;
}

.mfb-modal__box {
    width: min(580px, 100%);
    max-height: min(720px, 90vh);
    overflow-y: auto;
    background: #ffffff;
    border-radius: 16px;
    padding: 1.25rem;
    box-shadow: 0 24px 60px -12px rgba(15, 23, 42, 0.25);
}

.mfb-modal__head {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    border-bottom: 1px solid #e2e8f0;
    padding-bottom: 0.85rem;
}

.mfb-modal__head h3 {
    margin: 0;
    font-size: 1rem;
    font-weight: 800;
    display: flex;
    gap: 0.45rem;
    align-items: center;
}

.mfb-modal__head p {
    margin: 0.25rem 0 0;
    color: #64748b;
    font-size: 12px;
}

.mfb-modal__close {
    border: 0;
    background: transparent;
    color: #94a3b8;
    cursor: pointer;
    font-size: 1rem;
}

.mfb-diff-list {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    padding: 1rem 0;
}

.mfb-diff {
    display: flex;
    gap: 0.75rem;
    align-items: flex-start;
    padding: 0.65rem 0.85rem;
    border-radius: 10px;
    background: #f8fafc;
}

.mfb-diff strong {
    display: block;
    font-size: 12.5px;
}

.mfb-diff small {
    color: #64748b;
    font-size: 11.5px;
}

.mfb-diff.is-added i {
    color: #059669;
}
.mfb-diff.is-removed i {
    color: #dc2626;
}
.mfb-diff.is-changed i {
    color: #d97706;
}

.mfb-modal__foot {
    display: flex;
    justify-content: flex-end;
    gap: 0.5rem;
    border-top: 1px solid #e2e8f0;
    padding-top: 0.85rem;
}

/* Lapis toast bersama — lihat --wca-z-toast di evo-theme.css.
   Dulu 120: DI BAWAH modal EVO (1200) dan bahkan di bawah panel penyusun
   formulirnya sendiri. Setiap "Skema tersimpan" tertelan modal yang masih
   terbuka — dan menyimpan skema justru selalu dilakukan dari dalam modal. */
.mfb-toast {
    position: fixed;
    right: 1.5rem;
    bottom: 1.5rem;
    max-width: min(520px, calc(100vw - 3rem));
    line-height: 1.5;
    z-index: var(--wca-z-toast, 100000);
    display: flex;
    gap: 0.5rem;
    align-items: center;
    background: #0f172a;
    color: #ffffff;
    border-radius: 12px;
    padding: 0.85rem 1.1rem;
    font-size: 13px;
    font-weight: 700;
    box-shadow: 0 20px 40px -10px rgba(15, 23, 42, 0.35);
}

.mfb-toast-enter-active,
.mfb-toast-leave-active {
    transition: all 0.2s ease;
}

.mfb-toast-enter-from,
.mfb-toast-leave-to {
    opacity: 0;
    transform: translateY(10px);
}

/* ── RESPONSIVE MEDIA QUERIES ── */
@media (max-width: 1024px) {
    .mfb-layout {
        grid-template-columns: 1fr;
    }
    .mfb-sidebar {
        position: static;
        max-height: 400px;
    }
    .mfb-builder {
        grid-template-columns: 1fr;
    }
    .mfb-inspector {
        position: static;
        max-height: none;
    }
    .mfb-meta-bar {
        grid-template-columns: 1fr;
    }
    .mfb-stats-grid {
        grid-template-columns: 1fr 1fr;
    }
}

@media (max-width: 640px) {
    .mfb-fields-grid {
        grid-template-columns: 1fr !important;
    }
    .mfb-field-card {
        grid-column: 1 / -1 !important;
    }
}

/* Force 1-Column Stacking in Mobile Preview Frame Mode */
.mfb-preview-frame--mobile :deep(.bg__grid),
.mfb-preview-frame--mobile :deep(.t1__grid),
.mfb-preview-frame--mobile :deep(.wca-fields-grid),
.mfb-preview-frame--mobile :deep(.fr-grid) {
    grid-template-columns: 1fr !important;
}

.mfb-preview-frame--mobile :deep(.bg__grid > *),
.mfb-preview-frame--mobile :deep(.t1__grid > *),
.mfb-preview-frame--mobile :deep(.wca-fields-grid > *),
.mfb-preview-frame--mobile :deep(.fr-grid > *) {
    grid-column: 1 / -1 !important;
}

/* PONSEL — toast sudut melebar penuh. Pada 360px, lebar sudut hanya menyisakan
   ruang teks selebar dua kata dan pesan panjang terpotong jadi banyak baris
   sempit. Lihat --wca-z-toast di evo-theme.css untuk lapisannya. */
@media (max-width: 560px) {
    .mfb-toast {
        left: 12px;
        right: 12px;
        max-width: none;
        align-items: flex-start;
    }
    .mfb-toast .bi { flex: none; margin-top: 1px; }
}
</style>
