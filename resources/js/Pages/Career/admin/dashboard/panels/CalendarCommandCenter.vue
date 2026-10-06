<template>
    <div class="wcc" :style="{ '--wcc-accent': accent }">
        <!-- ══════════════ HERO COMMAND CENTER BANNER ══════════════ -->
        <div class="wcc-hero">
            <div class="wcc-hero__content">
                <div class="wcc-hero__meta">
                    <span class="wcc-eyebrow"><i class="bi bi-stars"></i> Command Center Kalender</span>
                    <span class="wcc-status-pill" :class="statusOperasional.kelas">
                        <i class="bi" :class="statusOperasional.ikon"></i> {{ statusOperasional.label }}
                    </span>
                </div>
                <h3>Jadwal &amp; Operasional {{ categoryLabel }}</h3>
                <p>Monitor sesi tes, agenda program, dan tenggat waktu pendaftaran dalam satu hub interaktif modern.</p>
            </div>
            <div class="wcc-hero__actions">
                <button type="button" class="wcc-hero-btn wcc-hero-btn--ghost" title="Muat ulang data kalender" :disabled="memuat" :onClick="memuat ? null : refresh">
                    <i class="bi bi-arrow-clockwise" :class="{ 'wcc-spin': memuat }"></i>
                    <span>Muat Ulang</span>
                </button>
                <button type="button" class="wcc-hero-btn wcc-hero-btn--primary" @click="bukaFokus()">
                    <i class="bi bi-arrows-fullscreen"></i>
                    <span>Perluas Kalender</span>
                </button>
            </div>
        </div>

        <!-- ══════════════ KEADAAN MEMUAT / GALAT ══════════════ -->
        <div v-if="memuat && !events.length" class="wcc-state" aria-live="polite">
            <span class="wcc-loader"></span>
            <div>
                <b>Menyusun kalender operasional…</b>
                <small>Menyinkronkan sesi tes, agenda, dan batas pendaftaran</small>
            </div>
        </div>
        <div v-else-if="galat && !events.length" class="wcc-state wcc-state--error" role="alert">
            <i class="bi bi-wifi-off"></i>
            <div>
                <b>Kalender belum berhasil dimuat</b>
                <small>{{ galat }}</small>
            </div>
            <button type="button" @click="muatRingkas">Coba lagi</button>
        </div>

        <template v-else>
            <!-- ══════════════ KPI SIGNAL CARDS ══════════════ -->
            <div class="wcc-summary" :class="{ 'is-refreshing': memuat }">
                <button type="button" class="wcc-signal" :class="{ active: hariTerpilih === hariIni }" @click="pilihHari(hariIni)">
                    <span class="wcc-signal__icon tone-blue"><i class="bi bi-sun-fill"></i></span>
                    <div class="wcc-signal__body">
                        <b>{{ summary.hariIni || 0 }}</b>
                        <span>Kegiatan Hari Ini</span>
                    </div>
                    <i class="bi bi-chevron-right wcc-signal__arrow"></i>
                </button>

                <button type="button" class="wcc-signal" :class="{ danger: summary.perluPerhatian }" @click="bukaFokus('PERLU')">
                    <span class="wcc-signal__icon tone-orange"><i class="bi bi-exclamation-triangle-fill"></i></span>
                    <div class="wcc-signal__body">
                        <b>{{ summary.perluPerhatian || 0 }}</b>
                        <span>Perlu Perhatian</span>
                    </div>
                    <span v-if="summary.perluPerhatian" class="wcc-signal__chip warning">Perlu Aksi</span>
                </button>

                <button type="button" class="wcc-signal" :class="{ danger: summary.bentrok }" @click="bukaFokus('BENTROK')">
                    <span class="wcc-signal__icon tone-red"><i class="bi bi-intersect"></i></span>
                    <div class="wcc-signal__body">
                        <b>{{ summary.bentrok || 0 }}</b>
                        <span>Sesi Berbenturan</span>
                    </div>
                    <span v-if="summary.bentrok" class="wcc-signal__chip danger">Konflik</span>
                </button>

                <button type="button" class="wcc-signal wcc-signal--deadline" @click="bukaDeadline">
                    <span class="wcc-signal__icon tone-violet"><i class="bi bi-hourglass-bottom"></i></span>
                    <div class="wcc-signal__body">
                        <b>{{ deadlineSingkat }}</b>
                        <span>{{ summary.deadlineTerdekat?.program || 'Belum ada deadline' }}</span>
                    </div>
                    <i class="bi bi-clock-history wcc-signal__arrow"></i>
                </button>
            </div>

            <!-- ══════════════ DASHBOARD WORKSPACE ══════════════ -->
            <div class="wcc-workspace">
                <!-- PANEL NAVIGASI MINGGU & DAY STRIP -->
                <div class="wcc-nav-panel">
                    <div class="wcc-nav-header">
                        <div class="wcc-nav-title">
                            <i class="bi bi-calendar3"></i>
                            <div>
                                <b>{{ labelRentangMinggu }}</b>
                                <small>Pilih hari untuk melihat agenda</small>
                            </div>
                        </div>
                        <div class="wcc-nav-controls">
                            <button type="button" class="wcc-nav-btn" title="Minggu sebelumnya" @click="geserMinggu(-1)">
                                <i class="bi bi-chevron-left"></i>
                            </button>
                            <button type="button" class="wcc-nav-btn wcc-nav-btn--today" :disabled="offsetMinggu === 0" title="Kembali ke minggu ini" :onClick="offsetMinggu === 0 ? null : resetMinggu">
                                Hari ini
                            </button>
                            <button type="button" class="wcc-nav-btn" title="Minggu berikutnya" @click="geserMinggu(1)">
                                <i class="bi bi-chevron-right"></i>
                            </button>
                        </div>
                    </div>

                    <div class="wcc-days" role="tablist" aria-label="Pilih hari agenda">
                        <button v-for="day in days" :key="day.key" type="button" role="tab"
                            :aria-selected="hariTerpilih === day.key"
                            :class="{ active: hariTerpilih === day.key, today: day.isToday, busy: day.count >= 3 }"
                            @click="pilihHari(day.key)">
                            <span class="wcc-day__name">{{ day.weekday }}</span>
                            <b class="wcc-day__num">{{ day.number }}</b>
                            <small class="wcc-day__month">{{ day.month }}</small>
                            
                            <!-- Density Dots -->
                            <div v-if="day.types.length" class="wcc-day__dots">
                                <span v-for="(t, idx) in day.types" :key="idx" class="wcc-day__dot" :style="{ background: t }"></span>
                            </div>
                            <em v-if="day.count" class="wcc-day__badge">{{ day.count }}</em>
                        </button>
                    </div>
                </div>

                <!-- PANEL AGENDA HARI TERPILIH -->
                <div class="wcc-agenda">
                    <div class="wcc-agenda__head">
                        <div class="wcc-agenda__title">
                            <span class="wcc-agenda__subtitle">Agenda Terpilih</span>
                            <b>{{ labelHariTerpilih }}</b>
                        </div>
                        <div class="wcc-agenda__tools">
                            <div class="wcc-mini-search">
                                <i class="bi bi-search"></i>
                                <input v-model="cariWidget" type="text" placeholder="Cari di hari ini…" />
                                <button v-if="cariWidget" type="button" class="wcc-clear-btn" @click="cariWidget = ''">
                                    <i class="bi bi-x"></i>
                                </button>
                            </div>
                            <span v-if="agendaHariSaring.length" class="wcc-count">{{ agendaHariSaring.length }} kegiatan</span>
                        </div>
                    </div>

                    <!-- TABS FILTER QUICK TYPE IN WIDGET -->
                    <div class="wcc-widget-filter">
                        <button v-for="opt in filterJenisWidget" :key="opt.value" type="button"
                            :class="{ active: jenisWidget === opt.value }" @click="jenisWidget = opt.value">
                            <i class="bi" :class="opt.icon"></i> {{ opt.label }}
                        </button>
                    </div>

                    <div v-if="!agendaHariSaring.length" class="wcc-empty">
                        <span class="wcc-empty__icon"><i class="bi bi-calendar2-check"></i></span>
                        <div>
                            <b>{{ cariWidget || jenisWidget !== 'SEMUA' ? 'Tidak ada agenda yang cocok' : 'Hari ini masih lapang' }}</b>
                            <small>{{ cariWidget || jenisWidget !== 'SEMUA' ? 'Coba ubah kata kunci atau filter jenis agenda.' : 'Tidak ada jadwal kegiatan pada hari yang dipilih.' }}</small>
                        </div>
                    </div>

                    <div v-else class="wcc-event-list">
                        <!-- SATU BARIS = DUA AKSI, jadi dua tombol BERSEBELAHAN.
                             Dulu tombol "Salin ringkasan" bersarang di dalam tombol
                             barisnya. HTML melarang itu, dan akibatnya bukan sekadar
                             peringatan build: peramban MEMBETULKAN sendiri sarangnya
                             saat mengurai, sehingga pohon yang dirender berbeda dari
                             yang dirender server — persis penyebab galat hidrasi.
                             Pembungkusnya sekarang <div>, dan kedua tombol jadi
                             saudara di dalamnya. -->
                        <div v-for="event in agendaHariSaring" :key="event.id" class="wcc-event-row">
                        <button type="button" class="wcc-event"
                            :class="{ alert: event.konflik || event.kesiapan === 'PERLU_PERHATIAN' }"
                            :style="{ '--event-color': jenis(event).warna }" @click="bukaDetail(event)">
                            <span class="wcc-event__time">
                                <i class="bi bi-clock-history"></i>
                                {{ jamEvent(event) }}
                            </span>
                            <span class="wcc-event__marker" title="Jenis agenda">
                                <i class="bi" :class="jenis(event).ikon"></i>
                            </span>
                            <span class="wcc-event__content">
                                <b>{{ event.judul }}</b>
                                <small>{{ event.program }}<template v-if="event.ket"> · {{ event.ket }}</template></small>
                            </span>

                            <div class="wcc-event__meta">
                                <span v-if="event.konflik" class="wcc-badge danger" title="Sesi berbenturan">
                                    <i class="bi bi-intersect"></i> Bentrok
                                </span>
                                <span v-else-if="event.kesiapan" class="wcc-badge" :style="{ '--badge-color': kesiapan(event).warna }">
                                    <i class="bi" :class="kesiapan(event).ikon"></i> {{ kesiapan(event).label }}
                                </span>
                                <span v-if="event.jumlahPeserta" class="wcc-event__pcount" title="Jumlah Peserta">
                                    <i class="bi bi-people-fill"></i> {{ event.jumlahPeserta }}
                                </span>
                            </div>

                            <i class="bi bi-chevron-right wcc-event__arrow"></i>
                        </button>

                        <!-- Di LUAR tombol baris, ditumpuk di atasnya lewat CSS.
                             @click.stop tetap dipertahankan: pembungkusnya sekarang
                             div, tapi klik yang merambat naik masih bisa tertangkap
                             penangan lain di atasnya. -->
                        <button type="button" class="wcc-event__copy-btn" title="Salin ringkasan" @click.stop="salinEvent(event)">
                            <i class="bi bi-clipboard"></i>
                        </button>
                        </div>
                    </div>
                </div>
            </div>
        </template>

        <!-- TOAST NOTIFIKASI PIPELINE -->
        <Transition name="wcc-toast">
            <div v-if="toastMessage" class="wcc-toast" role="status">
                <i class="bi bi-check-circle-fill"></i>
                <span>{{ toastMessage }}</span>
            </div>
        </Transition>

        <!-- ══════════════ MODAL FULLSCREEN KALENDER OPERASIONAL ══════════════ -->
        <AdminModal
            :show="fokus"
            title="Kalender Operasional Command Center"
            :subtitle="`${categoryLabel} · Live Operational View`"
            icon="bi-calendar2-week-fill"
            full
            @close="tutupFokus"
        >
            <!-- KPI METRICS STRIP -->
            <div class="wcc-modal__stats-bar">
                <div class="wcc-modal__kpi-item">
                    <span class="wcc-modal__kpi-icon tone-blue"><i class="bi bi-sun-fill"></i></span>
                    <div>
                        <b>{{ summary.hariIni || 0 }}</b>
                        <span>Hari Ini</span>
                    </div>
                </div>
                <div class="wcc-modal__kpi-item" :class="{ danger: summary.perluPerhatian }">
                    <span class="wcc-modal__kpi-icon tone-orange"><i class="bi bi-exclamation-triangle-fill"></i></span>
                    <div>
                        <b>{{ summary.perluPerhatian || 0 }}</b>
                        <span>Perlu Perhatian</span>
                    </div>
                </div>
                <div class="wcc-modal__kpi-item" :class="{ danger: summary.bentrok }">
                    <span class="wcc-modal__kpi-icon tone-red"><i class="bi bi-intersect"></i></span>
                    <div>
                        <b>{{ summary.bentrok || 0 }}</b>
                        <span>Sesi Bentrok</span>
                    </div>
                </div>
                <div class="wcc-modal__kpi-item">
                    <span class="wcc-modal__kpi-icon tone-violet"><i class="bi bi-hourglass-bottom"></i></span>
                    <div>
                        <b>{{ deadlineSingkat }}</b>
                        <span>Deadline Terdekat</span>
                    </div>
                </div>
            </div>

            <!-- CONTROL TOOLBAR: CLEAR CRISP FLEX ROW -->
            <div class="wcc-modal__toolbar">
                <div class="wcc-modal__toolbar-left">
                    <!-- SEARCH BAR WITH CRISP BORDER -->
                    <label class="wcc-search">
                        <i class="bi bi-search"></i>
                        <input v-model="filter.cari" type="search" placeholder="Cari agenda, program, deskripsi…">
                        <button v-if="filter.cari" type="button" class="wcc-search-clear" @click="filter.cari = ''">
                            <i class="bi bi-x-circle-fill"></i>
                        </button>
                    </label>

                    <!-- TYPE FILTER BUTTONS -->
                    <div class="wcc-type-filter" role="group" aria-label="Filter jenis agenda">
                        <button v-for="option in filterJenis" :key="option.value" type="button"
                            :class="{ active: filter.jenis === option.value }" @click="filter.jenis = option.value">
                            <i class="bi" :class="option.icon"></i>{{ option.label }}
                        </button>
                    </div>

                    <!-- PROGRAM SELECT DROPDOWN -->
                    <div class="wcc-program-filter-wrap">
                        <select v-model="filter.program" class="wcc-select">
                            <option value="">Semua Program</option>
                            <option v-for="program in programOptions" :key="program" :value="program">{{ program }}</option>
                        </select>
                        <i class="bi bi-chevron-down wcc-select-icon"></i>
                    </div>
                </div>

                <div class="wcc-modal__toolbar-right">
                    <!-- VIEW SWITCHER BUTTONS -->
                    <div class="wcc-view-switcher" role="group" aria-label="Tampilan kalender">
                        <button type="button" :class="{ active: viewModalAktif === 'dayGridMonth' }" title="Tampilan Bulan" @click="gantiTampilanModal('dayGridMonth')">
                            <i class="bi bi-calendar3"></i> Bulan
                        </button>
                        <button type="button" :class="{ active: viewModalAktif === 'timeGridWeek' }" title="Tampilan Minggu" @click="gantiTampilanModal('timeGridWeek')">
                            <i class="bi bi-calendar-week"></i> Minggu
                        </button>
                        <button type="button" :class="{ active: viewModalAktif === 'timeGridDay' }" title="Tampilan Hari" @click="gantiTampilanModal('timeGridDay')">
                            <i class="bi bi-calendar-day"></i> Hari
                        </button>
                        <button type="button" :class="{ active: viewModalAktif === 'listWeek' }" title="Tampilan Daftar" @click="gantiTampilanModal('listWeek')">
                            <i class="bi bi-list-task"></i> Daftar
                        </button>
                    </div>

                    <!-- PRESET CHIPS & RESET -->
                    <div class="wcc-preset-bar">
                        <button type="button" class="wcc-preset-chip" :class="{ active: filter.mode === 'PERLU' }" @click="setFilterMode('PERLU')">
                            <i class="bi bi-exclamation-triangle-fill"></i> Perhatian
                        </button>
                        <button type="button" class="wcc-preset-chip danger" :class="{ active: filter.mode === 'BENTROK' }" @click="setFilterMode('BENTROK')">
                            <i class="bi bi-intersect"></i> Bentrok
                        </button>
                        <button v-if="isFilterAktif" type="button" class="wcc-reset-btn" title="Reset filter" @click="resetSemuaFilter">
                            <i class="bi bi-arrow-counterclockwise"></i>
                        </button>
                    </div>
                </div>
            </div>

            <div v-if="fokusGalat" class="wcc-inline-error" role="alert">
                <i class="bi bi-exclamation-circle-fill"></i>{{ fokusGalat }}
            </div>

            <!-- FULLCALENDAR MAIN WRAPPER -->
            <div class="wcc-calendar-wrap" :class="{ loading: fokusMemuat }">
                <FullCalendar ref="fullCalendar" :options="calendarOptions" />
            </div>

            <template #footer>
                <div class="wcc-legend" style="width: 100%; padding: 0; background: none; border: none;">
                    <div class="wcc-legend__items">
                        <span class="wcc-legend__label">Legenda Agenda:</span>
                        <button v-for="(item, key) in JENIS_KALENDER" :key="key" type="button"
                            class="wcc-legend__item" :class="{ active: filter.jenis === key }"
                            @click="filter.jenis = filter.jenis === key ? 'SEMUA' : key">
                            <i :style="{ background: item.warna }"></i>{{ item.label }}
                            <span v-if="filter.jenis === key" class="wcc-legend__check"><i class="bi bi-check"></i></span>
                        </button>
                        <button type="button" class="wcc-legend__item risk" :class="{ active: filter.mode === 'BENTROK' || filter.mode === 'PERLU' }"
                            @click="setFilterMode('BENTROK')">
                            <i class="risk"></i>Bentrok / Perhatian
                        </button>
                    </div>
                    <em class="wcc-legend__tip">
                        <i class="bi bi-info-circle"></i> Klik agenda untuk melihat detail peserta &amp; tindakan cepat.
                    </em>
                </div>
            </template>
        </AdminModal>

        <!-- ══════════════ MODAL DETAIL AGENDA ══════════════ -->
        <AdminModal
            :show="!!detail"
            :title="detail?.judul || 'Detail Agenda Operasional'"
            :subtitle="`${jenis(detail || {}).label || 'Agenda'} · ${detail?.program || ''}`"
            :icon="jenis(detail || {}).ikon || 'bi-calendar-event'"
            lg
            @close="tutupDetail"
        >
            <div v-if="detail" class="wcc-drawer__body" style="padding: 0;">
                <div v-if="detail.konflik" class="wcc-alertbox">
                    <i class="bi bi-intersect"></i>
                    <div>
                        <b>Jadwal Berbenturan Detected</b>
                        <span>Terdapat jadwal lain pada program yang sama di rentang waktu bersamaan.</span>
                    </div>
                </div>

                <dl class="wcc-detail-list">
                    <div>
                        <dt><i class="bi bi-building"></i> Program</dt>
                        <dd><b>{{ detail.program }}</b></dd>
                    </div>
                    <div>
                        <dt><i class="bi bi-clock"></i> Waktu</dt>
                        <dd>{{ formatRentang(detail) }}</dd>
                    </div>
                    <div v-if="detail.ket">
                        <dt><i class="bi bi-tag"></i> Keterangan</dt>
                        <dd>{{ detail.ket }}</dd>
                    </div>
                    <div v-if="detail.status">
                        <dt><i class="bi bi-activity"></i> Status Sesi</dt>
                        <dd><span class="wcc-status-tag">{{ detail.status }}</span></dd>
                    </div>
                </dl>

                <!-- PROGRES PESERTA & KESIAPAN -->
                <section v-if="detail.kesiapan || detail.jumlahPeserta" class="wcc-readiness">
                    <div class="wcc-readiness__head">
                        <span>Kesiapan Sesi &amp; Peserta</span>
                        <b :style="{ color: kesiapan(detail).warna }">
                            <i class="bi" :class="kesiapan(detail).ikon"></i> {{ kesiapan(detail).label }}
                        </b>
                    </div>

                    <!-- Progress Bar Visual -->
                    <div v-if="detail.jumlahPeserta" class="wcc-progress-bar">
                        <div class="wcc-progress-bar__track">
                            <div class="wcc-progress-bar__fill ok" :style="{ width: hitungPersentasePeserta(detail) + '%' }"></div>
                        </div>
                        <div class="wcc-progress-bar__meta">
                            <span>Tingkat Kesiapan</span>
                            <b>{{ hitungPersentasePeserta(detail) }}% Terkirim</b>
                        </div>
                    </div>

                    <div class="wcc-participants">
                        <div class="wcc-part-card">
                            <b>{{ detail.jumlahPeserta || 0 }}</b>
                            <span>Total Peserta</span>
                        </div>
                        <div class="wcc-part-card ok">
                            <b>{{ detail.pesertaTerkirim || 0 }}</b>
                            <span>Terkirim</span>
                        </div>
                        <div class="wcc-part-card wait">
                            <b>{{ detail.pesertaMenunggu || 0 }}</b>
                            <span>Menunggu</span>
                        </div>
                        <div class="wcc-part-card fail">
                            <b>{{ detail.pesertaGagal || 0 }}</b>
                            <span>Gagal</span>
                        </div>
                    </div>
                </section>

                <!-- TIMELINE SIAPA SAJA YANG TERJADWAL -->
                <section v-if="detail.jenis === 'TES'" class="wcc-people-timeline">
                    <div class="wcc-people-timeline__head">
                        <div>
                            <span>Timeline peserta terjadwal</span>
                            <b>Siapa saja yang ada di sesi ini</b>
                        </div>
                        <em v-if="Array.isArray(detail.peserta)">{{ detail.peserta.length }} orang</em>
                    </div>

                    <div v-if="detailMemuat" class="wcc-people-loading" aria-live="polite">
                        <span class="wcc-loader"></span>
                        <div><b>Memuat peserta…</b><small>Mengambil status terbaru kandidat.</small></div>
                    </div>
                    <div v-else-if="detailGalat" class="wcc-people-error" role="alert">
                        <i class="bi bi-wifi-off"></i>
                        <div><b>Peserta belum berhasil dimuat</b><small>{{ detailGalat }}</small></div>
                        <button type="button" class="wcc-hero-btn wcc-hero-btn--ghost" @click="bukaDetail(detail, true)">Coba lagi</button>
                    </div>
                    <div v-else-if="detail.peserta?.length" class="wcc-people-list">
                        <article v-for="(peserta, index) in detail.peserta" :key="peserta.kode || `${peserta.nama}-${index}`" class="wcc-person">
                            <div class="wcc-person__rail">
                                <span>{{ inisialPeserta(peserta.nama) }}</span>
                                <i v-if="index < detail.peserta.length - 1"></i>
                            </div>
                            <div class="wcc-person__main">
                                <div class="wcc-person__identity">
                                    <div><b>{{ peserta.nama }}</b><small>{{ peserta.posisi || 'Posisi belum dicatat' }}</small></div>
                                    <span class="wcc-person__status" :class="statusPeserta(peserta).kelas">
                                        <i class="bi" :class="statusPeserta(peserta).ikon"></i>{{ statusPeserta(peserta).label }}
                                    </span>
                                </div>
                                <div class="wcc-person__meta">
                                    <span v-if="peserta.kode"><i class="bi bi-person-vcard"></i>{{ peserta.kode }}</span>
                                    <span><i class="bi bi-send"></i>{{ labelStatus(peserta.statusKirim) }}</span>
                                    <span><i class="bi bi-activity"></i>{{ labelStatus(peserta.statusPengerjaan) }}</span>
                                </div>
                            </div>
                        </article>
                    </div>
                    <div v-else class="wcc-people-empty">
                        <i class="bi bi-people"></i>
                        <div><b>Belum ada peserta di sesi ini</b><small>Peserta akan muncul setelah ditambahkan melalui Penjadwalan.</small></div>
                    </div>
                </section>
            </div>

            <template #footer>
                <div class="wcc-drawer__actions" style="width: 100%; display: flex; align-items: center; justify-content: space-between;">
                    <div style="display: flex; gap: 8px;">
                        <button type="button" class="wca-btn wca-btn--ghost" @click="salinEvent(detail)">
                            <i class="bi bi-clipboard-check"></i> Salin Ringkasan
                        </button>
                        <a v-if="detail?.sourceUrl" class="wca-btn wca-btn--dark" :href="detail.sourceUrl">
                            <i class="bi bi-box-arrow-up-right"></i> Buka di {{ sumberLabel(detail) }}
                        </a>
                    </div>
                    <button type="button" class="wca-btn wca-btn--ghost" @click="tutupDetail">
                        <i class="bi bi-x-lg"></i> Tutup
                    </button>
                </div>
            </template>
        </AdminModal>
    </div>
</template>

<script setup>
import { computed, nextTick, onMounted, onUnmounted, reactive, ref, watch } from 'vue';
import axios from 'axios';
import AdminModal from '@career/AdminModal.vue';
import FullCalendar from '@fullcalendar/vue3';
import dayGridPlugin from '@fullcalendar/daygrid';
import interactionPlugin from '@fullcalendar/interaction';
import listPlugin from '@fullcalendar/list';
import timeGridPlugin from '@fullcalendar/timegrid';
import idLocale from '@fullcalendar/core/locales/id';
import { CFG } from '@utils/career/dashboard';
import {
    JENIS_KALENDER, KESIAPAN_KALENDER, formatRentang, hitungPersentasePeserta,
    keEventFullCalendar, kunciTanggal, salinRingkasanEvent, saringEvent,
    tambahHari, tanggalApi, warnaEvent,
} from '@utils/career/kalender';

const props = defineProps({
    category: { type: String, required: true },
    categoryLabel: { type: String, default: 'Program' },
    accent: { type: String, default: '#6366f1' },
});

const events = ref([]);
const summary = ref({ hariIni: 0, perluPerhatian: 0, bentrok: 0, hariPadat: 0, deadlineTerdekat: null });
const memuat = ref(false);
const galat = ref('');
const fokus = ref(false);
const fokusMemuat = ref(false);
const fokusGalat = ref('');
const fokusRaw = ref([]);
const detail = ref(null);
const detailMemuat = ref(false);
const detailGalat = ref('');
const fullCalendar = ref(null);
const hariIni = tanggalApi(new Date());
const hariTerpilih = ref(hariIni);
const offsetMinggu = ref(0);
const cariWidget = ref('');
const jenisWidget = ref('SEMUA');
const viewModalAktif = ref('timeGridWeek');
const toastMessage = ref('');
let toastTimer = null;

let requestRingkas = null;
let requestFokus = null;
let requestDetail = null;
let filterTimer = null;
let overflowSebelum = '';

const filter = reactive({ jenis: 'SEMUA', program: '', cari: '', mode: '' });
const filterJenis = [
    { value: 'SEMUA', label: 'Semua', icon: 'bi-grid' },
    { value: 'TES', label: 'Tes', icon: JENIS_KALENDER.TES.ikon },
    { value: 'AGENDA', label: 'Agenda', icon: JENIS_KALENDER.AGENDA.ikon },
    { value: 'TUTUP', label: 'Deadline', icon: JENIS_KALENDER.TUTUP.ikon },
];

const filterJenisWidget = [
    { value: 'SEMUA', label: 'Semua', icon: 'bi-grid-fill' },
    { value: 'TES', label: 'Tes', icon: JENIS_KALENDER.TES.ikon },
    { value: 'AGENDA', label: 'Agenda', icon: JENIS_KALENDER.AGENDA.ikon },
    { value: 'TUTUP', label: 'Deadline', icon: JENIS_KALENDER.TUTUP.ikon },
];

/* ─────────────────── FILTER STATE COMPUTED ─────────────────── */
const isFilterAktif = computed(() => Boolean(filter.cari || filter.jenis !== 'SEMUA' || filter.program || filter.mode));

function setFilterMode(mode) {
    filter.mode = filter.mode === mode ? '' : mode;
    fullCalendar.value?.getApi()?.refetchEvents();
}

function resetSemuaFilter() {
    filter.cari = '';
    filter.jenis = 'SEMUA';
    filter.program = '';
    filter.mode = '';
    fullCalendar.value?.getApi()?.refetchEvents();
}

function gantiTampilanModal(viewName) {
    viewModalAktif.value = viewName;
    fullCalendar.value?.getApi()?.changeView(viewName);
}

/* ─────────────────── OPERATIONAL HEALTH COMPUTED ─────────────────── */
const statusOperasional = computed(() => {
    if (summary.value.bentrok > 0) {
        return { label: `${summary.value.bentrok} Sesi Bentrok`, kelas: 'danger', ikon: 'bi-exclamation-octagon-fill' };
    }
    if (summary.value.perluPerhatian > 0) {
        return { label: `${summary.value.perluPerhatian} Perlu Perhatian`, kelas: 'warning', ikon: 'bi-exclamation-triangle-fill' };
    }
    return { label: 'Operasional Normal', kelas: 'success', ikon: 'bi-check-circle-fill' };
});

/* ─────────────────── MINGGU & STRIP DAY NAV ─────────────────── */
const awalMingguNav = computed(() => {
    const d = new Date();
    const day = d.getDay();
    const diff = d.getDate() - day + (day === 0 ? -6 : 1);
    const monday = new Date(d.setDate(diff));
    monday.setHours(0, 0, 0, 0);
    return tambahHari(monday, offsetMinggu.value * 7);
});

const days = computed(() => Array.from({ length: 7 }, (_, index) => {
    const date = tambahHari(awalMingguNav.value, index);
    const key = tanggalApi(date);
    const dayEvents = events.value.filter((event) => kunciTanggal(event.mulai) === key);
    const types = [...new Set(dayEvents.map((e) => warnaEvent(e)))];
    return {
        key,
        date,
        weekday: key === hariIni ? 'Hari ini' : date.toLocaleDateString('id-ID', { weekday: 'short' }),
        number: date.getDate(),
        month: date.toLocaleDateString('id-ID', { month: 'short' }),
        isToday: key === hariIni,
        count: dayEvents.length,
        types,
    };
}));

const labelRentangMinggu = computed(() => {
    const start = awalMingguNav.value;
    const end = tambahHari(start, 6);
    const fmt = (d) => d.toLocaleDateString('id-ID', { day: 'numeric', month: 'short' });
    const year = end.getFullYear();
    return `${fmt(start)} – ${fmt(end)} ${year}`;
});

const agendaHari = computed(() => events.value.filter((event) => kunciTanggal(event.mulai) === hariTerpilih.value));

const agendaHariSaring = computed(() => {
    return saringEvent(agendaHari.value, {
        jenis: jenisWidget.value,
        cari: cariWidget.value,
    });
});

const labelHariTerpilih = computed(() => {
    const [year, month, day] = hariTerpilih.value.split('-').map(Number);
    return new Date(year, month - 1, day).toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
});

const deadlineSingkat = computed(() => {
    const value = summary.value.deadlineTerdekat?.mulai;
    if (!value) return 'Aman';
    const date = new Date(String(value).replace(' ', 'T'));
    return date.toLocaleDateString('id-ID', { day: 'numeric', month: 'short' });
});

const programOptions = computed(() => [...new Set([...events.value, ...fokusRaw.value].map((event) => event.program).filter(Boolean))].sort());

function geserMinggu(delta) {
    offsetMinggu.value += delta;
    const firstDay = days.value[0]?.key;
    if (firstDay) hariTerpilih.value = firstDay;
    muatRentangMinggu();
}

function resetMinggu() {
    offsetMinggu.value = 0;
    hariTerpilih.value = hariIni;
    muatRingkas();
}

function salinEvent(ev) {
    if (!ev) return;
    const teks = salinRingkasanEvent(ev);
    navigator.clipboard?.writeText(teks);
    tampilkanToast('Ringkasan agenda berhasil disalin!');
}

function tampilkanToast(msg) {
    toastMessage.value = msg;
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => { toastMessage.value = ''; }, 3000);
}

function jenis(event) {
    return JENIS_KALENDER[event?.jenis] || JENIS_KALENDER.AGENDA;
}
function kesiapan(event) {
    return KESIAPAN_KALENDER[event?.kesiapan] || KESIAPAN_KALENDER.MENUNGGU;
}
function sumberLabel(event) {
    return { TES: 'Penjadwalan', AGENDA: 'Master Jadwal', TUTUP: 'Pembukaan Program' }[event?.jenis] || 'modul sumber';
}
function jamEvent(event) {
    if (event.allDay) return 'All day';
    const date = new Date(String(event.mulai).replace(' ', 'T'));
    return Number.isNaN(date.getTime()) ? '—' : date.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
}
function pilihHari(key) {
    hariTerpilih.value = key;
}
function bukaDeadline() {
    if (summary.value.deadlineTerdekat) bukaDetail(summary.value.deadlineTerdekat);
    else bukaFokus();
}

function labelStatus(value) {
    return String(value || 'BELUM')
        .toLocaleLowerCase('id-ID')
        .replaceAll('_', ' ')
        .replace(/(^|\s)\S/g, (char) => char.toUpperCase());
}

function inisialPeserta(nama) {
    return String(nama || '?').trim().split(/\s+/).slice(0, 2).map((part) => part[0]).join('').toUpperCase();
}

function statusPeserta(peserta) {
    const kirim = String(peserta?.statusKirim || '').toUpperCase();
    const kerja = String(peserta?.statusPengerjaan || '').toUpperCase();
    if (kirim === 'GAGAL') return { label: 'Gagal dikirim', kelas: 'danger', ikon: 'bi-x-circle-fill' };
    if (['SELESAI', 'COMPLETED', 'SUDAH'].includes(kerja)) return { label: 'Selesai', kelas: 'success', ikon: 'bi-check-circle-fill' };
    if (['MENGERJAKAN', 'BERJALAN', 'IN_PROGRESS'].includes(kerja)) return { label: 'Mengerjakan', kelas: 'active', ikon: 'bi-play-circle-fill' };
    if (kirim === 'TERKIRIM') return { label: 'Undangan terkirim', kelas: 'sent', ikon: 'bi-send-check-fill' };
    return { label: 'Menunggu', kelas: 'waiting', ikon: 'bi-hourglass-split' };
}

async function bukaDetail(event, paksa = false) {
    if (!event) return;
    requestDetail?.abort();
    const eventId = event.id;
    detail.value = paksa ? event : { ...event, peserta: event.peserta ?? null };
    detailGalat.value = '';
    if (!event.pesertaUrl || (Array.isArray(event.peserta) && !paksa)) return;

    const controller = new AbortController();
    requestDetail = controller;
    detailMemuat.value = true;
    try {
        const { data } = await axios.get(event.pesertaUrl, {
            params: { kategori: props.category }, signal: controller.signal, ...CFG,
        });
        if (detail.value?.id === eventId) {
            detail.value = { ...detail.value, peserta: data.result?.peserta || [] };
        }
    } catch (error) {
        if (error?.code !== 'ERR_CANCELED' && detail.value?.id === eventId) {
            detailGalat.value = error?.response?.data?.message || 'Sambungan ke server gagal.';
        }
    } finally {
        if (!controller.signal.aborted) detailMemuat.value = false;
    }
}

function tutupDetail() {
    requestDetail?.abort();
    detailMemuat.value = false;
    detailGalat.value = '';
    detail.value = null;
}

async function ambil(params, signal) {
    const { data } = await axios.get('/api/v1/karir/dashboard/kalender', {
        params: { kategori: props.category, ...params }, signal, ...CFG,
    });
    return data.result || { events: [], summary: {} };
}

async function muatRentangMinggu() {
    const start = awalMingguNav.value;
    const end = tambahHari(start, 7);
    try {
        const result = await ambil({ mulai: tanggalApi(start), akhir: tanggalApi(end) });
        if (result.events) {
            const map = new Map(events.value.map((e) => [e.id, e]));
            result.events.forEach((e) => map.set(e.id, e));
            events.value = Array.from(map.values());
        }
    } catch {
        // Fallback
    }
}

async function muatRingkas() {
    requestRingkas?.abort();
    const controller = new AbortController();
    requestRingkas = controller;
    memuat.value = true;
    galat.value = '';
    try {
        const start = awalMingguNav.value;
        const result = await ambil({ mulai: tanggalApi(start), akhir: tanggalApi(tambahHari(start, 21)) }, controller.signal);
        events.value = result.events || [];
        summary.value = { ...summary.value, ...(result.summary || {}) };
    } catch (error) {
        if (error?.code !== 'ERR_CANCELED') galat.value = error?.response?.data?.message || 'Sambungan ke server gagal.';
    } finally {
        if (!controller.signal.aborted) memuat.value = false;
    }
}

async function muatEventsFokus(info, success, failure) {
    requestFokus?.abort();
    const controller = new AbortController();
    requestFokus = controller;
    fokusMemuat.value = true;
    fokusGalat.value = '';
    try {
        const result = await ambil({ mulai: info.startStr.slice(0, 10), akhir: info.endStr.slice(0, 10) }, controller.signal);
        fokusRaw.value = result.events || [];
        let hasil = saringEvent(fokusRaw.value, filter);
        if (filter.mode === 'PERLU') hasil = hasil.filter((event) => event.kesiapan === 'PERLU_PERHATIAN');
        if (filter.mode === 'BENTROK') hasil = hasil.filter((event) => event.konflik);
        success(hasil.map(keEventFullCalendar));
    } catch (error) {
        if (error?.code === 'ERR_CANCELED') return;
        fokusGalat.value = error?.response?.data?.message || 'Kalender gagal dimuat.';
        failure(error);
    } finally {
        if (!controller.signal.aborted) fokusMemuat.value = false;
    }
}

function bukaFokus(mode = '') {
    filter.mode = mode;
    fokus.value = true;
}
function tutupFokus() {
    fokus.value = false;
    filter.mode = '';
}
function eventKlik(info) {
    bukaDetail(info.event.extendedProps);
}
function refresh() {
    muatRingkas();
    if (fokus.value) fullCalendar.value?.getApi()?.refetchEvents();
}
defineExpose({ refresh });

const calendarOptions = computed(() => ({
    plugins: [dayGridPlugin, timeGridPlugin, listPlugin, interactionPlugin],
    locale: idLocale,
    initialView: window.matchMedia('(max-width: 760px)').matches ? 'listWeek' : 'timeGridWeek',
    headerToolbar: {
        left: 'prev,next today',
        center: 'title',
        right: '',
    },
    buttonText: { today: 'Hari ini', month: 'Bulan', week: 'Minggu', day: 'Hari', list: 'Daftar' },
    firstDay: 1,
    nowIndicator: true,
    navLinks: true,
    dayMaxEvents: 4,
    eventTimeFormat: { hour: '2-digit', minute: '2-digit', hour12: false },
    slotLabelFormat: { hour: '2-digit', minute: '2-digit', hour12: false },
    allDayText: 'Sepanjang hari',
    height: 'auto',
    events: muatEventsFokus,
    eventClick: eventKlik,
    eventDidMount(info) {
        const event = info.event.extendedProps;
        info.el.setAttribute('title', `${event.judul} — ${event.program || ''}`);
        if (event.konflik || event.kesiapan === 'PERLU_PERHATIAN') info.el.classList.add('wcc-risk-event');
    },
}));

watch(() => props.category, () => {
    offsetMinggu.value = 0;
    hariTerpilih.value = hariIni;
    events.value = [];
    fokusRaw.value = [];
    filter.program = '';
    cariWidget.value = '';
    jenisWidget.value = 'SEMUA';
    muatRingkas();
    nextTick(() => fullCalendar.value?.getApi()?.refetchEvents());
});

watch(() => [filter.jenis, filter.program, filter.cari, filter.mode], () => {
    clearTimeout(filterTimer);
    filterTimer = setTimeout(() => fullCalendar.value?.getApi()?.refetchEvents(), 220);
});

watch(fokus, (active) => {
    if (active) {
        overflowSebelum = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
        nextTick(() => fullCalendar.value?.getApi()?.updateSize());
    } else {
        document.body.style.overflow = overflowSebelum;
    }
});

function onKeydown(event) {
    if (event.key !== 'Escape') return;
    if (detail.value) tutupDetail();
    else if (fokus.value) tutupFokus();
}

onMounted(() => {
    window.addEventListener('keydown', onKeydown);
    muatRingkas();
});
onUnmounted(() => {
    requestRingkas?.abort();
    requestFokus?.abort();
    requestDetail?.abort();
    clearTimeout(filterTimer);
    clearTimeout(toastTimer);
    window.removeEventListener('keydown', onKeydown);
    document.body.style.overflow = overflowSebelum;
});
</script>

<style scoped>
.wcc {
    --ink: #0f172a;
    --ink2: #475569;
    --line: #cbd5e1;
    --line-subtle: #e2e8f0;
    --card-bg: #ffffff;
    color: var(--ink);
    font-family: inherit;
    box-sizing: border-box;
}

.wcc *, .wcc *::before, .wcc *::after {
    box-sizing: border-box;
}

/* GLOBAL ICON ALIGNMENT FIX */
.wcc i.bi, .wcc-modal i.bi, .wcc-drawer i.bi {
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    line-height: 1 !important;
    vertical-align: middle !important;
}

/* ══════════════ HERO BANNER ══════════════ */
.wcc-hero {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 24px;
    padding: 22px 24px;
    border-radius: 20px;
    color: #ffffff;
    background: radial-gradient(circle at 85% 15%, rgba(255, 255, 255, 0.22), transparent 40%),
                linear-gradient(135deg, color-mix(in srgb, var(--wcc-accent) 88%, #0f172a), var(--wcc-accent));
    box-shadow: 0 12px 30px rgba(15, 23, 42, 0.15);
    position: relative;
    overflow: hidden;
}

.wcc-hero__meta {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 8px;
}

.wcc-eyebrow {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 0.7rem;
    font-weight: 900;
    text-transform: uppercase;
    letter-spacing: 0.12em;
    opacity: 0.9;
}

.wcc-status-pill {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 3px 9px;
    border-radius: 99px;
    font-size: 0.65rem;
    font-weight: 800;
    background: rgba(255, 255, 255, 0.2);
    backdrop-filter: blur(4px);
}
.wcc-status-pill.success { background: rgba(34, 197, 94, 0.25); color: #dcfce7; }
.wcc-status-pill.warning { background: rgba(245, 158, 11, 0.3); color: #fef3c7; }
.wcc-status-pill.danger { background: rgba(239, 68, 68, 0.35); color: #fee2e2; }

.wcc-hero h3 {
    margin: 0;
    font-size: 1.25rem;
    font-weight: 900;
    letter-spacing: -0.02em;
    color: #ffffff !important;
    text-shadow: 0 1px 3px rgba(0, 0, 0, 0.35);
}

.wcc-hero p {
    margin: 6px 0 0;
    font-size: 0.8rem;
    line-height: 1.45;
    color: rgba(255, 255, 255, 0.95) !important;
    text-shadow: 0 1px 2px rgba(0, 0, 0, 0.2);
    max-width: 620px;
}

.wcc-hero__actions {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-shrink: 0;
}

.wcc-hero-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 16px;
    border-radius: 12px;
    font: inherit;
    font-size: 0.76rem;
    font-weight: 800;
    cursor: pointer;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
}

.wcc-hero-btn--ghost {
    border: 1.5px solid rgba(255, 255, 255, 0.45);
    color: #ffffff;
    background: rgba(255, 255, 255, 0.14);
    backdrop-filter: blur(8px);
}
.wcc-hero-btn--ghost:hover:not(:disabled) {
    background: rgba(255, 255, 255, 0.25);
}

.wcc-hero-btn--primary {
    border: 0;
    color: var(--wcc-accent);
    background: #ffffff;
    box-shadow: 0 4px 14px rgba(0, 0, 0, 0.15);
}
.wcc-hero-btn--primary:hover {
    transform: translateY(-1px);
    box-shadow: 0 6px 18px rgba(0, 0, 0, 0.22);
    background: #f8fafc;
}

.wcc-spin { animation: spin 0.9s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }

/* ══════════════ STATE MEMUAT & ERROR ══════════════ */
.wcc-state {
    min-height: 180px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 14px;
    padding: 24px;
    border: 1.5px dashed var(--line);
    border-radius: 18px;
    background: #f8fafc;
    color: var(--ink2);
}
.wcc-loader {
    width: 26px;
    height: 26px;
    border: 3px solid #cbd5e1;
    border-top-color: var(--wcc-accent);
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
}
.wcc-state div { display: grid; }
.wcc-state b { font-size: 0.88rem; color: var(--ink); }
.wcc-state small { font-size: 0.72rem; color: #64748b; margin-top: 2px; }
.wcc-state--error i { font-size: 28px; color: #ef4444; }
.wcc-state--error button {
    margin-top: 8px;
    border: 0;
    border-radius: 10px;
    padding: 8px 14px;
    color: #ffffff;
    background: var(--wcc-accent);
    font-weight: 800;
    cursor: pointer;
}

/* ══════════════ KPI SIGNAL CARDS ══════════════ */
.wcc-summary {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 12px;
    margin: 14px 0;
    transition: opacity 0.2s;
}
.wcc-summary.is-refreshing {
    opacity: 0.6;
    pointer-events: none;
}

.wcc-signal {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 14px;
    border: 1.5px solid var(--line-subtle);
    border-radius: 16px;
    background: #ffffff;
    text-align: left;
    font: inherit;
    cursor: pointer;
    transition: all 0.2s ease;
    position: relative;
}
.wcc-signal:hover {
    border-color: color-mix(in srgb, var(--wcc-accent) 55%, var(--line));
    box-shadow: 0 8px 22px rgba(15, 23, 42, 0.07);
    transform: translateY(-1px);
}
.wcc-signal.active {
    border-color: var(--wcc-accent);
    box-shadow: 0 0 0 2px color-mix(in srgb, var(--wcc-accent) 25%, transparent);
}
.wcc-signal.danger {
    background: #fff8f6;
    border-color: #fca5a5;
}

.wcc-signal__icon {
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    width: 38px;
    height: 38px;
    flex-shrink: 0;
    border-radius: 12px;
    font-size: 1.1rem;
    line-height: 1 !important;
}
.wcc-signal__icon i {
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    width: 1em !important;
    height: 1em !important;
    line-height: 1 !important;
    margin: 0 !important;
}
.wcc-signal__icon i::before {
    vertical-align: 0 !important;
    display: block !important;
    line-height: 1 !important;
    margin: 0 !important;
}
.tone-blue { color: #0284c7; background: #e0f2fe; }
.tone-orange { color: #ea580c; background: #ffedd5; }
.tone-red { color: #dc2626; background: #fee2e2; }
.tone-violet { color: #7c3aed; background: #ede9fe; }

.wcc-signal__body {
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
}
.wcc-signal__body b {
    font-size: 1.02rem;
    font-weight: 900;
    line-height: 1.1;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    color: var(--ink);
}
.wcc-signal__body span {
    font-size: 0.69rem;
    font-weight: 700;
    color: #64748b;
    margin-top: 3px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.wcc-signal__arrow {
    font-size: 0.8rem;
    color: #94a3b8;
    transition: transform 0.2s;
}
.wcc-signal:hover .wcc-signal__arrow {
    color: var(--wcc-accent);
    transform: translateX(2px);
}

.wcc-signal__chip {
    padding: 2px 7px;
    border-radius: 99px;
    font-size: 0.6rem;
    font-weight: 900;
    flex-shrink: 0;
}
.wcc-signal__chip.warning { background: #ffedd5; color: #c2410c; }
.wcc-signal__chip.danger { background: #fee2e2; color: #b91c1c; }

/* ══════════════ DASHBOARD WORKSPACE ══════════════ */
.wcc-workspace {
    display: grid;
    grid-template-columns: minmax(460px, 1fr) minmax(420px, 1.15fr);
    gap: 14px;
}

/* NAVIGASI MINGGU & DAY STRIP */
.wcc-nav-panel {
    display: flex;
    flex-direction: column;
    gap: 10px;
    padding: 14px;
    border: 1.5px solid var(--line-subtle);
    border-radius: 18px;
    background: #f8fafc;
}

.wcc-nav-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 10px 14px;
    background: linear-gradient(135deg, #1e1b4b 0%, #312e81 60%, #4338ca 100%);
    border-radius: 12px;
    color: #ffffff;
    margin-bottom: 8px;
    box-shadow: 0 4px 15px rgba(30, 27, 75, 0.2);
}

.wcc-nav-title {
    display: flex;
    align-items: center;
    gap: 9px;
}
.wcc-nav-title i {
    font-size: 1.15rem;
    color: #a5b4fc;
}
.wcc-nav-title b {
    display: block;
    font-size: 0.86rem;
    font-weight: 900;
    color: #ffffff !important;
}
.wcc-nav-title small {
    display: block;
    font-size: 0.66rem;
    color: rgba(255, 255, 255, 0.85);
}

.wcc-nav-controls {
    display: flex;
    align-items: center;
    gap: 4px;
}

.wcc-nav-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    border: 1.5px solid #cbd5e1;
    border-radius: 9px;
    background: #ffffff;
    color: #334155;
    font-size: 0.74rem;
    font-weight: 800;
    cursor: pointer;
    transition: all 0.15s ease;
}
.wcc-nav-btn:hover:not(:disabled) {
    background: #f1f5f9;
    color: var(--wcc-accent);
    border-color: var(--wcc-accent);
}
.wcc-nav-btn--today {
    width: auto;
    padding: 0 10px;
    font-weight: 800;
    font-size: 0.7rem;
}
.wcc-nav-btn:disabled {
    opacity: 0.45;
    cursor: not-allowed;
}

.wcc-days {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 6px;
}

.wcc-days button {
    position: relative;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    min-width: 0;
    min-height: 96px;
    padding: 10px 4px;
    border: 1.5px solid transparent;
    border-radius: 14px;
    background: #ffffff;
    color: var(--ink2);
    font: inherit;
    cursor: pointer;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
}
.wcc-days button:hover {
    border-color: #cbd5e1;
    box-shadow: 0 4px 12px rgba(15, 23, 42, 0.05);
}
.wcc-days button.active {
    color: #ffffff;
    background: var(--wcc-accent);
    box-shadow: 0 8px 20px color-mix(in srgb, var(--wcc-accent) 30%, transparent);
    border-color: var(--wcc-accent);
}
.wcc-days button.today:not(.active) {
    border-color: color-mix(in srgb, var(--wcc-accent) 60%, var(--line));
    background: color-mix(in srgb, var(--wcc-accent) 6%, #ffffff);
}
.wcc-days button.busy:not(.active) {
    background: #fff7ed;
    border-color: #fed7aa;
}

.wcc-day__name {
    font-size: 0.63rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    opacity: 0.85;
}
.wcc-day__num {
    margin: 4px 0 1px;
    font-size: 1.18rem;
    font-weight: 900;
}
.wcc-day__month {
    font-size: 0.64rem;
    opacity: 0.75;
}

.wcc-day__dots {
    display: flex;
    align-items: center;
    gap: 3px;
    margin-top: 5px;
}
.wcc-day__dot {
    width: 5px;
    height: 5px;
    border-radius: 99px;
}

.wcc-day__badge {
    position: absolute;
    top: 5px;
    right: 5px;
    min-width: 18px;
    height: 18px;
    padding: 0 4px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 99px;
    background: #ffffff;
    color: var(--wcc-accent);
    font-size: 0.6rem;
    font-style: normal;
    font-weight: 900;
    box-shadow: 0 2px 6px rgba(15, 23, 42, 0.12);
}
.wcc-days button.active .wcc-day__badge {
    background: rgba(255, 255, 255, 0.25);
    color: #ffffff;
}

/* AGENDA PANEL */
.wcc-agenda {
    display: flex;
    flex-direction: column;
    min-height: 220px;
    padding: 14px;
    border: 1.5px solid var(--line-subtle);
    border-radius: 18px;
    background: #ffffff;
}

.wcc-agenda__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 10px 14px;
    background: linear-gradient(135deg, #1e1b4b 0%, #312e81 60%, #4338ca 100%);
    border-radius: 12px;
    color: #ffffff;
    margin-bottom: 10px;
    box-shadow: 0 4px 15px rgba(30, 27, 75, 0.2);
}

.wcc-agenda__title span {
    display: block;
    font-size: 0.64rem;
    font-weight: 800;
    color: rgba(255, 255, 255, 0.85);
    text-transform: uppercase;
    letter-spacing: 0.06em;
}
.wcc-agenda__title b {
    display: block;
    font-size: 0.88rem;
    font-weight: 900;
    color: #ffffff !important;
}

.wcc-agenda__tools {
    display: flex;
    align-items: center;
    gap: 8px;
}

.wcc-mini-search {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 5px 9px;
    border: 1.5px solid #cbd5e1;
    border-radius: 10px;
    background: #ffffff;
    font-size: 0.72rem;
}
.wcc-mini-search input {
    width: 110px;
    border: 0;
    outline: 0;
    background: transparent;
    font: inherit;
    font-size: 0.7rem;
    color: var(--ink);
}
.wcc-mini-search i { color: #64748b; }

.wcc-clear-btn {
    border: 0;
    background: transparent;
    color: #94a3b8;
    cursor: pointer;
    padding: 0;
    display: flex;
    align-items: center;
    justify-content: center;
}
.wcc-clear-btn:hover { color: #ef4444; }

.wcc-count {
    padding: 3px 9px;
    border-radius: 99px;
    background: #eef2ff;
    color: #4338ca;
    font-size: 0.66rem;
    font-weight: 800;
    white-space: nowrap;
}

.wcc-widget-filter {
    display: flex;
    align-items: center;
    gap: 4px;
    margin-bottom: 10px;
    padding: 3px;
    border: 1.5px solid #e2e8f0;
    border-radius: 10px;
    background: #f8fafc;
}
.wcc-widget-filter button {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 5px 10px;
    border: 0;
    border-radius: 7px;
    background: transparent;
    color: #475569;
    font: inherit;
    font-size: 0.68rem;
    font-weight: 800;
    cursor: pointer;
    transition: all 0.15s ease;
}
.wcc-widget-filter button.active {
    background: #ffffff;
    color: var(--wcc-accent);
    box-shadow: 0 1px 4px rgba(15, 23, 42, 0.08);
}

.wcc-event-list {
    display: flex;
    flex-direction: column;
    gap: 7px;
    max-height: 235px;
    overflow-y: auto;
    padding-right: 4px;
}

/* Pembungkus satu baris agenda. Ada supaya tombol salin bisa berdiri di LUAR
   tombol barisnya — HTML melarang tombol bersarang, dan peramban membetulkan
   sarangnya sendiri saat mengurai sehingga pohonnya berbeda dari yang dirender
   server. Geseran hover pindah ke sini supaya keduanya bergerak bersama. */
.wcc-event-row {
    position: relative;
    transition: transform 0.15s ease;
}
.wcc-event-row:hover { transform: translateX(2px); }

.wcc-event {
    display: flex;
    align-items: center;
    gap: 10px;
    width: 100%;
    padding: 9px 11px;
    border: 1.5px solid #edf2f7;
    border-left: 4px solid var(--event-color);
    border-radius: 12px;
    background: #ffffff;
    color: var(--ink);
    text-align: left;
    font: inherit;
    cursor: pointer;
    transition: all 0.15s ease;
    position: relative;
}
.wcc-event-row:hover .wcc-event {
    background: #f8fafc;
    border-color: #cbd5e1;
    border-left-color: var(--event-color);
}
.wcc-event.alert {
    background: #fffaf7;
    border-color: #fed7aa;
}

.wcc-event__time {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    width: 58px;
    flex-shrink: 0;
    font-size: 0.67rem;
    font-weight: 800;
    color: #475569;
    font-variant-numeric: tabular-nums;
}
.wcc-event__time i { font-size: 0.72rem; color: #94a3b8; }

.wcc-event__marker {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 30px;
    height: 30px;
    flex-shrink: 0;
    border-radius: 9px;
    color: var(--event-color);
    background: color-mix(in srgb, var(--event-color) 12%, #ffffff);
    font-size: 0.9rem;
}

.wcc-event__content {
    flex: 1;
    min-width: 0;
}
.wcc-event__content b {
    display: block;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    font-size: 0.76rem;
    font-weight: 800;
    color: var(--ink);
}
.wcc-event__content small {
    display: block;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    margin-top: 1px;
    font-size: 0.67rem;
    color: #64748b;
}

.wcc-event__meta {
    display: flex;
    align-items: center;
    gap: 6px;
    flex-shrink: 0;
}

.wcc-event__pcount {
    display: inline-flex;
    align-items: center;
    gap: 3px;
    padding: 2px 6px;
    border-radius: 6px;
    background: #f1f5f9;
    color: #475569;
    font-size: 0.62rem;
    font-weight: 800;
}

.wcc-event__copy-btn {
    /* Ditumpuk di atas baris, tepat di kiri panahnya — posisi yang sama dengan
       sebelumnya, hanya tidak lagi bersarang di dalam tombolnya. */
    position: absolute;
    top: 50%;
    right: 30px;
    transform: translateY(-50%);
    z-index: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 26px;
    height: 26px;
    border: 0;
    border-radius: 6px;
    background: transparent;
    color: #94a3b8;
    cursor: pointer;
    opacity: 0;
    transition: all 0.15s ease;
}
/* Ikut tampil saat papan ketik masuk ke barisnya — kalau hanya :hover, tombol
   ini tidak pernah bisa dijangkau tanpa tetikus. */
.wcc-event-row:hover .wcc-event__copy-btn,
.wcc-event-row:focus-within .wcc-event__copy-btn { opacity: 1; }
.wcc-event__copy-btn:hover { background: #e2e8f0; color: var(--wcc-accent); }

.wcc-event__arrow {
    font-size: 0.7rem;
    color: #cbd5e1;
    transition: color 0.15s;
}
.wcc-event-row:hover .wcc-event__arrow { color: var(--wcc-accent); }

.wcc-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 2px 7px;
    border-radius: 99px;
    color: var(--badge-color);
    background: color-mix(in srgb, var(--badge-color) 12%, #ffffff);
    font-size: 0.6rem;
    font-weight: 900;
    white-space: nowrap;
}
.wcc-badge.danger { --badge-color: #b91c1c; }

.wcc-empty {
    min-height: 120px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 12px;
    padding: 16px;
    border: 1.5px dashed #cbd5e1;
    border-radius: 12px;
    background: #fafafa;
    color: #64748b;
}
.wcc-empty__icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 40px;
    height: 40px;
    border-radius: 12px;
    background: #f1f5f9;
    color: #94a3b8;
    font-size: 1.15rem;
}
.wcc-empty div { display: grid; }
.wcc-empty b { font-size: 0.8rem; color: var(--ink); }
.wcc-empty small { font-size: 0.68rem; color: #94a3b8; margin-top: 2px; }

/* TOAST NOTIFIKASI */
/* Lapis toast bersama — lihat --wca-z-toast di evo-theme.css. 2200 dulu sama
   tinggi dengan lapisan tertinggi command center ini, jadi urutannya bergantung
   pada siapa yang tergambar belakangan. */
.wcc-toast {
    position: fixed;
    bottom: 24px;
    right: 24px;
    max-width: min(520px, calc(100vw - 48px));
    line-height: 1.5;
    z-index: var(--wca-z-toast, 100000);
    display: flex;
    align-items: center;
    gap: 9px;
    padding: 10px 16px;
    border-radius: 12px;
    background: #0f172a;
    color: #ffffff;
    font-size: 0.76rem;
    font-weight: 800;
    box-shadow: 0 10px 30px rgba(15, 23, 42, 0.25);
}
.wcc-toast i { color: #22c55e; font-size: 0.9rem; }

.wcc-toast-enter-active, .wcc-toast-leave-active { transition: all 0.25s ease; }
.wcc-toast-enter-from, .wcc-toast-leave-to { opacity: 0; transform: translateY(12px); }

/* ══════════════ FULL MODAL KALENDER CRISP & ULTRA-HIGH CONTRAST ══════════════ */
.wcc-modal {
    position: fixed;
    inset: 0;
    z-index: 2050;
    padding: 24px;
    background: rgba(15, 23, 42, 0.75);
    backdrop-filter: blur(16px);
    -webkit-backdrop-filter: blur(16px);
    display: flex;
    align-items: center;
    justify-content: center;
    animation: wccModalIn 0.25s cubic-bezier(0.16, 1, 0.3, 1);
}

@keyframes wccModalIn {
    from { opacity: 0; transform: scale(0.98); }
    to { opacity: 1; transform: scale(1); }
}

.wcc-modal__panel {
    width: 100%;
    height: 100%;
    max-width: 1480px;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    border-radius: 24px;
    background: #ffffff;
    border: 1px solid rgba(255, 255, 255, 0.2);
    box-shadow: 0 30px 90px -10px rgba(15, 23, 42, 0.45);
}

/* HEADER MODAL */
.wcc-modal__head {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 18px;
    padding: 20px 28px;
    color: #ffffff;
    background: linear-gradient(135deg, #3730a3 0%, var(--wcc-accent, #6366f1) 60%, #4338ca 100%);
    box-shadow: 0 4px 20px rgba(79, 70, 229, 0.25);
}

.wcc-modal__title-group {
    display: flex;
    align-items: center;
    gap: 14px;
}

.wcd-modal-title-text h2 {
    margin: 0;
    font-size: 1.15rem;
    font-weight: 900;
    letter-spacing: -0.02em;
    color: #ffffff;
}

.wcd-modal-title-text p {
    margin: 3px 0 0;
    font-size: 0.76rem;
    color: rgba(255, 255, 255, 0.85);
    font-weight: 500;
}

.wcc-modal__logo {
    display: grid;
    place-items: center;
    width: 44px;
    height: 44px;
    border-radius: 14px;
    background: rgba(255, 255, 255, 0.2);
    backdrop-filter: blur(8px);
    font-size: 1.3rem;
    color: #ffffff;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.wcc-modal__head-actions {
    display: flex;
    align-items: center;
    gap: 12px;
}

.wcc-modal__tag {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 6px 14px;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.18);
    backdrop-filter: blur(6px);
    font-size: 0.72rem;
    font-weight: 800;
    color: #ffffff;
    border: 1px solid rgba(255, 255, 255, 0.25);
}

.wcc-icon-btn {
    display: grid;
    place-items: center;
    width: 38px;
    height: 38px;
    border: 0;
    border-radius: 12px;
    color: #ffffff;
    background: rgba(255, 255, 255, 0.2);
    cursor: pointer;
    transition: all 0.2s ease;
}
.wcc-icon-btn:hover { background: rgba(255, 255, 255, 0.35); transform: scale(1.05); }

/* STATS STRIP BELOW MODAL HEADER */
.wcc-modal__stats-bar {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 14px;
    padding: 14px 28px;
    background: #f8fafc;
    border-bottom: 1px solid #e2e8f0;
}

.wcc-modal__kpi-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 16px;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    background: #ffffff;
    box-shadow: 0 2px 6px rgba(15, 23, 42, 0.03);
    transition: transform 0.2s ease;
}
.wcc-modal__kpi-item:hover { transform: translateY(-1px); }
.wcc-modal__kpi-item.danger {
    background: #fef2f2;
    border-color: #fca5a5;
}

.wcc-modal__kpi-icon {
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    width: 38px;
    height: 38px;
    border-radius: 10px;
    font-size: 1.1rem;
    line-height: 1 !important;
    text-align: center;
    flex-shrink: 0;
}
.wcc-modal__kpi-icon i {
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    width: 1em !important;
    height: 1em !important;
    line-height: 1 !important;
    margin: 0 !important;
}
.wcc-modal__kpi-icon i::before {
    vertical-align: 0 !important;
    display: block !important;
    line-height: 1 !important;
    margin: 0 !important;
}

.wcc-modal__kpi-item b {
    display: block;
    font-size: 1.05rem;
    font-weight: 900;
    line-height: 1;
    color: var(--ink);
}
.wcc-modal__kpi-item span {
    display: block;
    font-size: 0.68rem;
    font-weight: 700;
    color: #475569;
    margin-top: 3px;
}

/* TOOLBAR CONTROL FULLFLEX WITH HIGH CONTRAST & CLEAR ACTIVE STATES */
.wcc-modal__toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    padding: 12px 24px;
    background: #ffffff;
    border-bottom: 1.5px solid var(--line-subtle);
    flex-wrap: wrap;
}

.wcc-modal__toolbar-left {
    display: flex;
    align-items: center;
    gap: 10px;
    flex: 1;
    min-width: 0;
}

.wcc-modal__toolbar-right {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-shrink: 0;
}

.wcc-search {
    display: flex;
    align-items: center;
    gap: 8px;
    flex: 1;
    max-width: 320px;
    padding: 8px 12px;
    border: 1.5px solid #cbd5e1;
    border-radius: 12px;
    color: #64748b;
    background: #ffffff;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);
}
.wcc-search input {
    width: 100%;
    border: 0;
    outline: 0;
    font: inherit;
    font-size: 0.75rem;
    font-weight: 700;
    color: var(--ink);
    background: transparent;
}
.wcc-search-clear {
    border: 0;
    background: transparent;
    color: #cbd5e1;
    cursor: pointer;
    padding: 0;
    display: flex;
    align-items: center;
    justify-content: center;
}
.wcc-search-clear:hover { color: #ef4444; }

.wcc-type-filter {
    display: flex;
    gap: 4px;
    padding: 4px;
    border: 1.5px solid #cbd5e1;
    border-radius: 12px;
    background: #f8fafc;
}
.wcc-type-filter button {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    border: 1.5px solid transparent;
    border-radius: 8px;
    background: transparent;
    color: #1e293b;
    font: inherit;
    font-size: 0.7rem;
    font-weight: 800;
    cursor: pointer;
    transition: all 0.15s ease;
}
.wcc-type-filter button.active {
    background: #ffffff;
    color: var(--wcc-accent);
    border-color: var(--wcc-accent);
    box-shadow: 0 2px 6px rgba(15, 23, 42, 0.08);
}

.wcc-program-filter-wrap {
    position: relative;
    display: flex;
    align-items: center;
}

.wcc-select {
    appearance: none;
    padding: 8px 32px 8px 12px;
    border: 1.5px solid #cbd5e1;
    border-radius: 12px;
    background: #ffffff;
    color: #0f172a;
    font: inherit;
    font-size: 0.72rem;
    font-weight: 800;
    cursor: pointer;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);
}
.wcc-select:focus {
    outline: 0;
    border-color: var(--wcc-accent);
}
.wcc-select-icon {
    position: absolute;
    right: 10px;
    pointer-events: none;
    font-size: 0.7rem;
    color: #64748b;
}

/* VIEW SWITCHER BUTTONS WITH HIGH CONTRAST & SOLID ACTIVE STATE */
.wcc-view-switcher {
    display: flex;
    align-items: center;
    gap: 4px;
    padding: 4px;
    border: 1.5px solid #cbd5e1;
    border-radius: 12px;
    background: #f1f5f9;
}
.wcc-view-switcher button {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    border: 1.5px solid #cbd5e1;
    border-radius: 9px;
    background: #ffffff;
    color: #1e293b;
    font: inherit;
    font-size: 0.7rem;
    font-weight: 800;
    cursor: pointer;
    transition: all 0.15s ease;
}
.wcc-view-switcher button.active {
    background: var(--wcc-accent) !important;
    border-color: var(--wcc-accent) !important;
    color: #ffffff !important;
    font-weight: 900 !important;
    box-shadow: 0 3px 10px color-mix(in srgb, var(--wcc-accent) 40%, transparent) !important;
}

/* PRESET BAR & RESET */
.wcc-preset-bar {
    display: flex;
    align-items: center;
    gap: 6px;
}
.wcc-preset-chip {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 7px 12px;
    border: 1.5px solid #f97316;
    border-radius: 10px;
    background: #fff7ed;
    color: #c2410c;
    font: inherit;
    font-size: 0.7rem;
    font-weight: 800;
    cursor: pointer;
    transition: all 0.15s ease;
}
.wcc-preset-chip.danger {
    border-color: #ef4444;
    background: #fef2f2;
    color: #dc2626;
}
.wcc-preset-chip.active {
    background: #ea580c;
    color: #ffffff;
    border-color: #ea580c;
}
.wcc-preset-chip.danger.active {
    background: #dc2626;
    color: #ffffff;
    border-color: #dc2626;
}

.wcc-reset-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    border: 1.5px solid #cbd5e1;
    border-radius: 10px;
    background: #ffffff;
    color: #64748b;
    font: inherit;
    font-size: 0.8rem;
    font-weight: 800;
    cursor: pointer;
}
.wcc-reset-btn:hover { background: #fee2e2; color: #b91c1c; border-color: #fca5a5; }

/* FULLCALENDAR WRAPPER & MODERN EVENT CARDS */
.wcc-calendar-wrap {
    flex: 1;
    overflow: auto;
    padding: 16px 24px;
    background: #f8fafc;
    transition: opacity 0.2s;
}
.wcc-calendar-wrap.loading { opacity: 0.58; }

.wcc-calendar-wrap :deep(.fc) {
    --fc-border-color: #e2e8f0;
    --fc-button-bg-color: #ffffff;
    --fc-button-text-color: #334155;
    --fc-button-border-color: #cbd5e1;
    --fc-button-hover-bg-color: var(--wcc-accent);
    --fc-button-hover-border-color: var(--wcc-accent);
    --fc-today-bg-color: rgba(99, 102, 241, 0.04) !important;
    font-size: 0.74rem;
    background: #ffffff;
    border-radius: 18px;
    padding: 16px;
    box-shadow: 0 4px 25px rgba(15, 23, 42, 0.03);
}
.wcc-calendar-wrap :deep(.fc-day-today) {
    background: rgba(99, 102, 241, 0.04) !important;
}

/* NAVBAR TOOLBAR FULLCALENDAR (HARI INI / NAVIGASI) */
.wcc-calendar-wrap :deep(.fc-toolbar) {
    display: flex !important;
    align-items: center !important;
    justify-content: space-between !important;
    margin-bottom: 14px !important;
    padding: 10px 18px !important;
    background: linear-gradient(135deg, #1e1b4b 0%, #312e81 60%, #4338ca 100%) !important;
    border: 1px solid rgba(255, 255, 255, 0.2) !important;
    border-radius: 14px !important;
    box-shadow: 0 4px 18px rgba(30, 27, 75, 0.25) !important;
}
.wcc-calendar-wrap :deep(.fc-toolbar-chunk:first-child) {
    display: flex !important;
    align-items: center !important;
    gap: 6px !important;
}
.wcc-calendar-wrap :deep(.fc-toolbar-title) {
    font-size: 1.1rem !important;
    font-weight: 900 !important;
    color: #ffffff !important;
    text-shadow: 0 1px 2px rgba(0, 0, 0, 0.3) !important;
}
.wcc-calendar-wrap :deep(.fc-button) {
    font-size: 0.74rem !important;
    font-weight: 800 !important;
    text-transform: none !important;
    border-radius: 9px !important;
    padding: 6px 12px !important;
    border: 1px solid rgba(255, 255, 255, 0.25) !important;
    background: rgba(255, 255, 255, 0.15) !important;
    color: #ffffff !important;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1) !important;
    transition: all 0.15s ease !important;
}
.wcc-calendar-wrap :deep(.fc-button:hover:not(:disabled)) {
    background: rgba(255, 255, 255, 0.3) !important;
    color: #ffffff !important;
    border-color: #ffffff !important;
}
.wcc-calendar-wrap :deep(.fc-button-primary:not(:disabled).fc-button-active) {
    background: #ffffff !important;
    border-color: #ffffff !important;
    color: #312e81 !important;
    font-weight: 900 !important;
}

/* HEADER HARI FULLCALENDAR (SENIN, SELASA, DST.) - TEKS DIBUAT WARNA PUTIH BENING */
.wcc-calendar-wrap :deep(.fc-col-header) {
    background: linear-gradient(135deg, #1e1b4b 0%, #312e81 60%, #4338ca 100%) !important;
    border-radius: 10px 10px 0 0 !important;
    overflow: hidden !important;
}
.wcc-calendar-wrap :deep(th.fc-col-header-cell) {
    background: transparent !important;
    border-color: rgba(255, 255, 255, 0.15) !important;
    padding: 9px 0 !important;
}
.wcc-calendar-wrap :deep(.fc-col-header-cell-cushion),
.wcc-calendar-wrap :deep(.fc-col-header-cell a) {
    color: #ffffff !important;
    font-weight: 900 !important;
    font-size: 0.76rem !important;
    text-transform: uppercase !important;
    letter-spacing: 0.05em !important;
    text-decoration: none !important;
    text-shadow: 0 1px 2px rgba(0, 0, 0, 0.3) !important;
}

/* ALL-DAY EVENT PILLS & DAYGRID CARDS */
.wcc-calendar-wrap :deep(.fc-daygrid-event),
.wcc-calendar-wrap :deep(.fc-h-event) {
    border-radius: 6px !important;
    padding: 3px 8px !important;
    font-weight: 800 !important;
    font-size: 0.72rem !important;
    background: color-mix(in srgb, var(--fc-event-bg-color, var(--wcc-accent)) 16%, #f8fafc) !important;
    border: 1px solid color-mix(in srgb, var(--fc-event-bg-color, var(--wcc-accent)) 35%, transparent) !important;
    border-left: 4px solid var(--fc-event-border-color, var(--wcc-accent)) !important;
    color: #0f172a !important;
    box-shadow: 0 2px 6px rgba(15, 23, 42, 0.08) !important;
}

.wcc-calendar-wrap :deep(.fc-event-title-container),
.wcc-calendar-wrap :deep(.fc-event-title),
.wcc-calendar-wrap :deep(.fc-event-main),
.wcc-calendar-wrap :deep(.fc-event-time),
.wcc-calendar-wrap :deep(.fc-daygrid-dot-event) {
    color: #0f172a !important;
    font-weight: 800 !important;
}

/* TIMED EVENT CARDS (TRANSLUCENT & CRISP BORDER) */
.wcc-calendar-wrap :deep(.fc-timegrid-event) {
    border-radius: 8px !important;
    padding: 4px 8px !important;
    cursor: pointer !important;
    font-weight: 800 !important;
    border: 1px solid color-mix(in srgb, var(--fc-event-bg-color, var(--wcc-accent)) 40%, transparent) !important;
    border-left: 4px solid var(--fc-event-border-color, var(--wcc-accent)) !important;
    background: color-mix(in srgb, var(--fc-event-bg-color, var(--wcc-accent)) 16%, #ffffff) !important;
    color: #0f172a !important;
    box-shadow: 0 2px 6px rgba(15, 23, 42, 0.05) !important;
    transition: transform 0.15s ease, box-shadow 0.15s ease !important;
}
.wcc-calendar-wrap :deep(.fc-timegrid-event:hover) {
    transform: translateY(-1px) scale(1.01) !important;
    box-shadow: 0 6px 14px rgba(15, 23, 42, 0.1) !important;
}
.wcc-calendar-wrap :deep(.fc-timegrid-event .fc-event-main) {
    color: #0f172a !important;
    font-weight: 800 !important;
    line-height: 1.2 !important;
}
.wcc-calendar-wrap :deep(.fc-timegrid-event .fc-event-time) {
    color: #475569 !important;
    font-weight: 800 !important;
}
.wcc-calendar-wrap :deep(.wcc-risk-event) {
    border-left-color: #dc2626 !important;
    background: color-mix(in srgb, #dc2626 15%, #ffffff) !important;
    box-shadow: 0 0 0 2px rgba(220, 38, 38, 0.3) !important;
}

.wcc-inline-error {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 9px 24px;
    color: #b91c1c;
    background: #fee2e2;
    font-size: 0.72rem;
    font-weight: 800;
}

/* LEGEND FOOTER */
.wcc-legend {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    flex-wrap: wrap;
    padding: 12px 24px;
    border-top: 1.5px solid var(--line-subtle);
    background: #ffffff;
    color: #64748b;
    font-size: 0.68rem;
}
.wcc-legend__items { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
.wcc-legend__label { font-weight: 800; color: #475569; }

.wcc-legend__item {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 10px;
    border: 1.5px solid #cbd5e1;
    border-radius: 99px;
    background: #f8fafc;
    color: #334155;
    font: inherit;
    font-size: 0.67rem;
    font-weight: 800;
    cursor: pointer;
    transition: all 0.15s ease;
}
.wcc-legend__item:hover, .wcc-legend__item.active {
    background: #ffffff;
    border-color: var(--wcc-accent);
    color: var(--wcc-accent);
    box-shadow: 0 2px 6px rgba(15, 23, 42, 0.08);
}
.wcc-legend__item > i { width: 9px; height: 9px; border-radius: 99px; }
.wcc-legend__item.risk i { background: #b91c1c; }
.wcc-legend__check { margin-left: 2px; color: var(--wcc-accent); font-weight: 900; }
.wcc-legend__tip { font-style: normal; color: #64748b; }

/* ══════════════ DRAWER DETAIL AGENDA ══════════════ */
.wcc-drawer-layer {
    position: fixed;
    inset: 0;
    z-index: 2100;
    background: rgba(15, 23, 42, 0.45);
    backdrop-filter: blur(4px);
}
.wcc-drawer {
    position: absolute;
    top: 0;
    right: 0;
    width: min(460px, 100%);
    height: 100%;
    display: flex;
    flex-direction: column;
    background: #ffffff;
    box-shadow: -20px 0 60px rgba(15, 23, 42, 0.22);
    animation: drawerIn 0.22s cubic-bezier(0.16, 1, 0.3, 1);
}
.wcc-drawer header {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 20px;
    color: #ffffff;
    background: linear-gradient(130deg, color-mix(in srgb, var(--detail-color) 80%, #0f172a), var(--detail-color));
}
.wcc-detail-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 42px;
    height: 42px;
    flex-shrink: 0;
    border-radius: 14px;
    background: rgba(255, 255, 255, 0.18);
    font-size: 1.25rem;
}
.wcc-drawer header div { flex: 1; min-width: 0; }
.wcc-drawer header small { font-size: 0.66rem; font-weight: 900; text-transform: uppercase; letter-spacing: 0.08em; opacity: 0.85; }
.wcc-drawer header h2 { margin: 2px 0 0; font-size: 1.05rem; font-weight: 900; }
.wcc-drawer header button {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 36px;
    border: 0;
    border-radius: 10px;
    color: inherit;
    background: rgba(255, 255, 255, 0.16);
    cursor: pointer;
}

.wcc-drawer__body { flex: 1; overflow-y: auto; padding: 20px; }

.wcc-alertbox {
    display: flex;
    gap: 10px;
    padding: 12px 14px;
    border: 1.5px solid #fecaca;
    border-radius: 14px;
    color: #991b1b;
    background: #fff5f5;
    margin-bottom: 14px;
}
.wcc-alertbox i { font-size: 1.2rem; flex-shrink: 0; }
.wcc-alertbox div { display: grid; }
.wcc-alertbox b { font-size: 0.78rem; }
.wcc-alertbox span { font-size: 0.68rem; color: #b91c1c; margin-top: 2px; }

.wcc-detail-list {
    display: flex;
    flex-direction: column;
    margin: 0 0 16px;
    border: 1.5px solid var(--line-subtle);
    border-radius: 16px;
    overflow: hidden;
}
.wcc-detail-list > div {
    display: grid;
    grid-template-columns: 125px 1fr;
    gap: 10px;
    padding: 12px 14px;
    border-bottom: 1.5px solid var(--line-subtle);
    background: #ffffff;
}
.wcc-detail-list > div:last-child { border-bottom: 0; }
.wcc-detail-list dt { font-size: 0.68rem; font-weight: 800; color: #64748b; }
.wcc-detail-list dt i { margin-right: 6px; color: #94a3b8; }
.wcc-detail-list dd { margin: 0; font-size: 0.74rem; font-weight: 800; color: var(--ink); }

.wcc-status-tag {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 6px;
    background: #f1f5f9;
    color: #475569;
    font-size: 0.68rem;
}

.wcc-readiness {
    padding: 14px;
    border: 1.5px solid var(--line-subtle);
    border-radius: 16px;
    background: #f8fafc;
}
.wcc-readiness__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    margin-bottom: 12px;
}
.wcc-readiness__head span { font-size: 0.72rem; font-weight: 900; color: #475569; }
.wcc-readiness__head b { font-size: 0.68rem; }

.wcc-progress-bar { margin-bottom: 12px; }
.wcc-progress-bar__track {
    height: 7px;
    border-radius: 99px;
    background: #e2e8f0;
    overflow: hidden;
}
.wcc-progress-bar__fill {
    height: 100%;
    border-radius: 99px;
    transition: width 0.4s ease;
}
.wcc-progress-bar__fill.ok { background: #22c55e; }
.wcc-progress-bar__meta {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-top: 4px;
    font-size: 0.64rem;
    color: #64748b;
}

.wcc-participants {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 8px;
}
.wcc-part-card {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 10px 4px;
    border-radius: 12px;
    background: #ffffff;
    border: 1.5px solid #edf2f7;
}
.wcc-part-card b { font-size: 0.95rem; font-weight: 900; }
.wcc-part-card span { font-size: 0.6rem; font-weight: 700; color: #94a3b8; margin-top: 2px; }
.wcc-part-card.ok b { color: #15803d; }
.wcc-part-card.wait b { color: #b45309; }
.wcc-part-card.fail b { color: #b91c1c; }

.wcc-drawer footer {
    padding: 16px 20px;
    border-top: 1.5px solid var(--line-subtle);
    background: #ffffff;
}
.wcc-drawer__actions {
    display: flex;
    flex-direction: column;
    gap: 8px;
}
.wcc-copy-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    width: 100%;
    padding: 10px;
    border: 1.5px solid #cbd5e1;
    border-radius: 12px;
    background: #ffffff;
    color: var(--ink);
    font: inherit;
    font-size: 0.74rem;
    font-weight: 800;
    cursor: pointer;
    transition: all 0.15s ease;
}
.wcc-copy-btn:hover { background: #f8fafc; border-color: var(--wcc-accent); color: var(--wcc-accent); }

.wcc-source-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    width: 100%;
    padding: 10px;
    border-radius: 12px;
    color: #ffffff;
    background: var(--detail-color, #4f46e5);
    font-size: 0.74rem;
    font-weight: 900;
    text-decoration: none;
    transition: opacity 0.15s;
}
.wcc-source-btn:hover { opacity: 0.9; }

/* Timeline peserta — nama dimuat hanya saat event tes dibuka. */
.wcc-people-timeline {
    margin-top: 14px;
    padding: 14px;
    border: 1px solid var(--line-subtle);
    border-radius: 16px;
    background: #ffffff;
}
.wcc-people-timeline__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 12px;
}
.wcc-people-timeline__head > div { display: grid; gap: 2px; }
.wcc-people-timeline__head span {
    color: #94a3b8;
    font-size: 0.61rem;
    font-weight: 900;
    letter-spacing: 0.07em;
    text-transform: uppercase;
}
.wcc-people-timeline__head b { color: var(--ink); font-size: 0.78rem; }
.wcc-people-timeline__head em {
    padding: 3px 9px;
    border-radius: 999px;
    color: #4338ca;
    background: #eef2ff;
    font-size: 0.65rem;
    font-style: normal;
    font-weight: 900;
}
.wcc-people-loading, .wcc-people-error, .wcc-people-empty {
    min-height: 78px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    padding: 10px;
    border-radius: 12px;
    background: #f8fafc;
    color: #64748b;
}
.wcc-people-loading > div, .wcc-people-error > div, .wcc-people-empty > div { display: grid; flex: 1; }
.wcc-people-loading b, .wcc-people-error b, .wcc-people-empty b { font-size: 0.72rem; }
.wcc-people-loading small, .wcc-people-error small, .wcc-people-empty small { font-size: 0.64rem; color: #94a3b8; }
.wcc-people-error { color: #b91c1c; background: #fff7f7; }
.wcc-people-error > i, .wcc-people-empty > i { font-size: 20px; }
.wcc-people-error button {
    flex: none;
    padding: 6px 9px;
    border: 0;
    border-radius: 8px;
    color: #ffffff;
    background: #b91c1c;
    font: inherit;
    font-size: 0.63rem;
    font-weight: 900;
    cursor: pointer;
}
.wcc-people-list { max-height: 410px; overflow-y: auto; padding-right: 3px; }
.wcc-person { display: grid; grid-template-columns: 36px 1fr; gap: 9px; min-width: 0; }
.wcc-person__rail { display: flex; flex-direction: column; align-items: center; }
.wcc-person__rail > span {
    width: 32px;
    height: 32px;
    display: grid;
    place-items: center;
    flex: none;
    border: 2px solid #ffffff;
    border-radius: 50%;
    color: #4338ca;
    background: #e0e7ff;
    box-shadow: 0 0 0 1px #c7d2fe;
    font-size: 0.62rem;
    font-weight: 900;
}
.wcc-person__rail > i { width: 2px; min-height: 28px; flex: 1; background: #e2e8f0; }
.wcc-person__main {
    min-width: 0;
    margin-bottom: 9px;
    padding: 9px 10px;
    border: 1px solid #edf1f6;
    border-radius: 12px;
    background: #fcfdff;
}
.wcc-person__identity { display: flex; align-items: flex-start; justify-content: space-between; gap: 8px; }
.wcc-person__identity > div { min-width: 0; display: grid; }
.wcc-person__identity b, .wcc-person__identity small { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.wcc-person__identity b { color: var(--ink); font-size: 0.72rem; }
.wcc-person__identity small { margin-top: 2px; color: #64748b; font-size: 0.63rem; }
.wcc-person__status {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    flex: none;
    padding: 3px 7px;
    border-radius: 999px;
    font-size: 0.57rem;
    font-weight: 900;
}
.wcc-person__status.success { color: #166534; background: #dcfce7; }
.wcc-person__status.active { color: #075985; background: #e0f2fe; }
.wcc-person__status.sent { color: #4338ca; background: #eef2ff; }
.wcc-person__status.waiting { color: #92400e; background: #fef3c7; }
.wcc-person__status.danger { color: #991b1b; background: #fee2e2; }
.wcc-person__meta { display: flex; flex-wrap: wrap; gap: 4px 10px; margin-top: 7px; color: #94a3b8; font-size: 0.58rem; }
.wcc-person__meta span { display: inline-flex; align-items: center; gap: 4px; }

.wcc-drawer footer p {
    margin: 0;
    text-align: center;
    color: #94a3b8;
    font-size: 0.68rem;
}

@keyframes drawerIn {
    from { transform: translateX(40px); opacity: 0.4; }
    to { transform: translateX(0); opacity: 1; }
}

/* ══════════════ RESPONSIVE ══════════════ */
@media (max-width: 1024px) {
    .wcc-summary { grid-template-columns: repeat(2, 1fr); }
    .wcc-workspace { grid-template-columns: 1fr; }
    .wcc-modal__stats-bar { grid-template-columns: repeat(2, 1fr); }
    .wcc-modal__toolbar-left, .wcc-modal__toolbar-right { flex-wrap: wrap; width: 100%; }
}

@media (max-width: 760px) {
    .wcc-hero { flex-direction: column; align-items: flex-start; padding: 16px; }
    .wcc-hero p { display: none; }
    .wcc-summary { grid-template-columns: 1fr; gap: 8px; }
    .wcc-days { grid-template-columns: repeat(7, 58px); overflow-x: auto; justify-content: start; }
    .wcc-modal { padding: 0; }
    .wcc-modal__panel { border-radius: 0; }
    .wcc-modal__stats-bar { grid-template-columns: 1fr; gap: 6px; }
    .wcc-modal__toolbar { flex-direction: column; align-items: stroke; }
    .wcc-search { max-width: 100%; }
    .wcc-program-filter-wrap, .wcc-select { width: 100%; }
    .wcc-view-switcher { justify-content: center; }
}

@media (prefers-reduced-motion: reduce) {
    .wcc-loader, .wcc-drawer, .wcc-spin { animation: none; }
}

/* PONSEL — toast sudut melebar penuh. Pada 360px, lebar sudut hanya menyisakan
   ruang teks selebar dua kata dan pesan panjang terpotong jadi banyak baris
   sempit. Lihat --wca-z-toast di evo-theme.css untuk lapisannya. */
@media (max-width: 560px) {
    .wcc-toast {
        left: 12px;
        right: 12px;
        max-width: none;
        align-items: flex-start;
    }
    .wcc-toast .bi { flex: none; margin-top: 1px; }
}
</style>
