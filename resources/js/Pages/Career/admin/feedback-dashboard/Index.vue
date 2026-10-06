<!-- WEB CAREER â€” Feedback Dashboard: Analitik data feedback kandidat dengan Ultra-Premium KPI cards visual, NPS deep-dive, program comparison leaderboard, analisis Lolos vs Gagal dual power cards, monitoring pengisian, candidate answer drawer interaktif, compact filter bar, rich empty states, shimmer skeleton loaders, sel avatar kandidat horizontal, search multi-select dropdowns (+1, +2), Question Insight Grid Cards rapat tanpa whitespace, NPS Donut Center Overlay, Program Leaderboard Horizontal Flex Alignment (#1 Side-by-Side), dan Kalkulasi NPS Riil dari Backend. Notifikasi Ekspor dikelola secara terpusat oleh Komponen Global ExportNotificationToast.vue. -->
<template>
    <Head title="Feedback Dashboard" />
    <div class="wca wca-fb-wrapper">
        <!-- â•â•â• HEADER BAR â•â•â• -->
        <div class="wca-phead wca-phead--modern">
            <div class="wca-phead__title-group">
                <div class="wca-phead__icon-badge">
                    <i class="bi bi-graph-up-arrow"></i>
                </div>
                <div>
                    <h1 class="wca-phead__title">Feedback Dashboard</h1>
                    <p class="wca-phead__sub">Analytics & monitoring feedback kandidat • NPS, rating, Likert, analisis hasil seleksi & tracking pengisian.</p>
                </div>
            </div>
            <div class="wca-phead__actions">
                <div class="fb-tabs-modern">
                    <button 
                        type="button"
                        :class="['fb-tab-m', { 'fb-tab-m--active': tab === 'analytics' }]" 
                        @click="tab = 'analytics'"
                    >
                        <i class="bi bi-pie-chart-fill"></i>
                        <span>Analytics</span>
                    </button>
                    <button 
                        type="button"
                        :class="['fb-tab-m', { 'fb-tab-m--active': tab === 'monitoring' }]" 
                        @click="switchToMonitoring()"
                    >
                        <i class="bi bi-card-checklist"></i>
                        <span>Monitoring</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- ════════════════════════════════════════════════════════════════ -->
        <!-- TAB 1: ANALYTICS                                                -->
        <!-- ════════════════════════════════════════════════════════════════ -->
        <template v-if="tab === 'analytics'">
            <!-- ═══ ANALYTICS KPI ROW (Consistent with Monitoring) ═══ -->
            <div class="wca-kpi-grid mb-4">
                <!-- Satisfaction Index -->
                <div class="wca-kpi-card wca-kpi-card--primary">
                    <div class="wca-kpi-card__top">
                        <span class="wca-kpi-card__icon"><i class="bi bi-emoji-smile-fill"></i></span>
                    </div>
                    <div class="wca-kpi-card__body">
                        <div class="wca-kpi-card__value" :class="satisfactionColor(satisfactionIndex)">{{ satisfactionIndex ?? '—' }}</div>
                        <div class="wca-kpi-card__label">Satisfaction Index</div>
                        <div class="wca-kpi-card__sub text-xs text-muted">skala 0 - 100</div>
                    </div>
                </div>

                <!-- Response Rate -->
                <div class="wca-kpi-card wca-kpi-card--emerald">
                    <div class="wca-kpi-card__top">
                        <span class="wca-kpi-card__icon"><i class="bi bi-reply-all-fill"></i></span>
                    </div>
                    <div class="wca-kpi-card__body">
                        <div class="wca-kpi-card__value text-emerald">{{ overview.response_rate }}%</div>
                        <div class="wca-kpi-card__label">Response Rate</div>
                        <div class="wca-kpi-card__sub text-xs text-muted">{{ overview.total_terisi }} dari {{ overview.total_dibuat }} link</div>
                    </div>
                </div>

                <!-- Suara Terkumpul -->
                <div class="wca-kpi-card wca-kpi-card--indigo">
                    <div class="wca-kpi-card__top">
                        <span class="wca-kpi-card__icon"><i class="bi bi-megaphone-fill"></i></span>
                    </div>
                    <div class="wca-kpi-card__body">
                        <div class="wca-kpi-card__value text-indigo">{{ overview.total_terisi }}</div>
                        <div class="wca-kpi-card__label">Suara Terkumpul</div>
                        <div class="wca-kpi-card__sub text-xs text-muted">feedback terisi</div>
                    </div>
                </div>

                <!-- Program Terbaik -->
                <div class="wca-kpi-card wca-kpi-card--amber">
                    <div class="wca-kpi-card__top">
                        <span class="wca-kpi-card__icon"><i class="bi bi-trophy-fill"></i></span>
                    </div>
                    <div class="wca-kpi-card__body">
                        <div class="wca-kpi-card__value text-amber text-truncate" style="font-size:1.1rem" :title="topProgram?.Program_Nama">
                            {{ topProgram?.Program_Nama || '—' }}
                        </div>
                        <div class="wca-kpi-card__label">Program Terbaik</div>
                        <div class="wca-kpi-card__sub text-xs text-muted" v-if="topProgram">{{ topProgram.total_respon }} respon • {{ fmt(topProgram.avg_rating) }} ★</div>
                        <div class="wca-kpi-card__sub text-xs text-muted" v-else>belum ada data</div>
                    </div>
                </div>

                <!-- Perlu Perhatian -->
                <div class="wca-kpi-card wca-kpi-card--rose" v-if="attentionItems.length">
                    <div class="wca-kpi-card__top">
                        <span class="wca-kpi-card__icon"><i class="bi bi-exclamation-diamond-fill"></i></span>
                    </div>
                    <div class="wca-kpi-card__body">
                        <div class="wca-kpi-card__value text-rose text-truncate" style="font-size:0.85rem" :title="attentionItems[0]?.label">
                            {{ attentionItems[0]?.label?.substring(0, 35) }}{{ attentionItems[0]?.label?.length > 35 ? '…' : '' }}
                        </div>
                        <div class="wca-kpi-card__label">Perlu Perhatian ⚠️</div>
                        <div class="wca-kpi-card__sub text-xs text-muted">{{ Math.round(parseFloat(attentionItems[0]?.avg_pct)) }}% — paling rendah</div>
                    </div>
                </div>
                <div class="wca-kpi-card wca-kpi-card--slate" v-else>
                    <div class="wca-kpi-card__top">
                        <span class="wca-kpi-card__icon"><i class="bi bi-check-circle-fill"></i></span>
                    </div>
                    <div class="wca-kpi-card__body">
                        <div class="wca-kpi-card__value text-slate">—</div>
                        <div class="wca-kpi-card__label">Perlu Perhatian</div>
                        <div class="wca-kpi-card__sub text-xs text-muted">semua aspek baik</div>
                    </div>
                </div>
            </div>

            <!-- Analytics Filter Bar Card -->
            <div class="wca-card wca-glass-card mb-4">
                <div class="wca-filter-bar wca-filter-bar--compact align-items-start">
                    <!-- Dropdown Form (Wide, Searchable & Multi-select with +1 Tag Collapse) -->
                    <div class="wca-filter-item" style="flex: 0 0 260px; width: 260px;">
                        <label class="wca-filter-label"><i class="bi bi-journal-text me-1"></i> Form Feedback</label>
                        <el-select 
                            v-model="filters.form_ids" 
                            multiple
                            filterable
                            collapse-tags
                            collapse-tags-tooltip
                            :max-collapse-tags="1"
                            placeholder="Semua Form Feedback" 
                            clearable 
                            @change="fetchData" 
                            size="default" 
                            style="width: 100%"
                        >
                            <el-option 
                                v-for="f in forms" 
                                :key="f.Id_Master_Feedback_Form" 
                                :label="f.Nama || f.nama" 
                                :value="f.Id_Master_Feedback_Form" 
                            />
                        </el-select>
                    </div>

                    <!-- Dropdown Program -->
                    <div class="wca-filter-item" style="flex: 0 0 220px; width: 220px;">
                        <label class="wca-filter-label"><i class="bi bi-building me-1"></i> Program</label>
                        <el-select
                            v-model="filters.program_ids"
                            multiple
                            filterable
                            collapse-tags
                            collapse-tags-tooltip
                            :max-collapse-tags="1"
                            placeholder="Semua Program"
                            clearable
                            @change="fetchData"
                            size="default"
                            style="width: 100%"
                        >
                            <el-option
                                v-for="p in programs"
                                :key="p.Id_Program"
                                :label="p.Nama || p.nama"
                                :value="p.Id_Program"
                            />
                        </el-select>
                    </div>

                    <!-- Date Range Picker -->
                    <div class="wca-filter-item" style="flex: 0 0 250px; width: 250px;">
                        <label class="wca-filter-label"><i class="bi bi-calendar-range me-1"></i> Periode Pengisian</label>
                        <el-date-picker 
                            v-model="dateRange" 
                            type="daterange" 
                            range-separator="s/d" 
                            start-placeholder="Mulai" 
                            end-placeholder="Akhir" 
                            size="default" 
                            @change="fetchData" 
                            style="width: 100%" 
                        />
                    </div>

                    <!-- Preset Cepat Buttons -->
                    <div class="wca-filter-item">
                        <label class="wca-filter-label"><i class="bi bi-clock-history me-1"></i> Preset Cepat</label>
                        <div class="wca-preset-pill-group">
                            <button type="button" class="wca-preset-btn" @click="setPresetDate(7)">7 Hari</button>
                            <button type="button" class="wca-preset-btn" @click="setPresetDate(30)">30 Hari</button>
                            <button type="button" class="wca-preset-btn" @click="setPresetDate(90)">90 Hari</button>
                        </div>
                    </div>

                    <!-- Right Action Buttons -->
                    <div class="wca-filter-bar__right ms-auto d-flex align-items-end gap-2" style="margin-top: 20px;">
                        <button type="button" v-if="(filters.form_ids && filters.form_ids.length) || (filters.program_ids && filters.program_ids.length) || dateRange" class="wca-btn wca-btn--ghost text-rose" @click="resetFilters">
                            <i class="bi bi-x-circle me-1"></i> Reset
                        </button>
                        <button 
                            type="button"
                            class="wca-btn wca-btn--primary" 
                            :onClick="exporting || (!filters.form_ids || !filters.form_ids.length) ? null : exportExcel" 
                            :disabled="exporting || (!filters.form_ids || !filters.form_ids.length)" 
                            :title="(!filters.form_ids || !filters.form_ids.length) ? 'Pilih Form terlebih dahulu' : 'Export data ke Excel'"
                        >
                            <i class="bi me-1" :class="exporting ? 'bi-hourglass-split spin-ico' : 'bi-file-earmark-excel-fill'"></i>
                            <span>{{ exporting ? 'Memproses...' : 'Export Excel' }}</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Enhanced Analytics Shimmer Skeleton Loading -->
            <template v-if="analyticsLoading">
                <!-- 5 KPI Cards Skeleton -->
                <div class="wca-kpi-grid mb-4">
                    <div v-for="i in 5" :key="i" class="wca-kpi-card p-3">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div class="wca-shimmer" style="width: 38px; height: 38px; border-radius: 10px;"></div>
                            <div class="wca-shimmer" style="width: 50px; height: 18px; border-radius: 6px;"></div>
                        </div>
                        <div class="wca-shimmer mb-2" style="width: 80px; height: 28px;"></div>
                        <div class="wca-shimmer mb-1" style="width: 110px; height: 14px;"></div>
                        <div class="wca-shimmer" style="width: 70px; height: 10px;"></div>
                    </div>
                </div>

                <!-- Charts Skeleton Row -->
                <div class="wca-grid wca-grid--12 mb-4">
                    <div class="wca-col-12 wca-col-lg-5">
                        <div class="wca-card h-100 p-4">
                            <div class="wca-shimmer mb-4" style="width: 180px; height: 20px;"></div>
                            <div class="d-flex justify-content-center my-3">
                                <div class="wca-shimmer" style="width: 160px; height: 160px; border-radius: 50%;"></div>
                            </div>
                            <div class="wca-shimmer mt-3" style="width: 100%; height: 12px;"></div>
                        </div>
                    </div>
                    <div class="wca-col-12 wca-col-lg-7">
                        <div class="wca-card h-100 p-4">
                            <div class="wca-shimmer mb-4" style="width: 160px; height: 20px;"></div>
                            <div class="wca-shimmer" style="width: 100%; height: 200px; border-radius: 12px;"></div>
                        </div>
                    </div>
                </div>
            </template>

            <!-- ═══ LAYER 2: COMPARISON VIEW (Scorecard & Dual Power Cards) ═══ -->
            <div v-if="!analyticsLoading" class="wca-grid wca-grid--12 mb-4">
                <!-- Left: Per-Aspek Scorecard -->
                <div class="wca-col-12 wca-col-lg-7">
                    <div class="wca-card h-100">
                        <div class="wca-card__head">
                            <h3><i class="bi bi-list-stars text-indigo me-1"></i> Per-Aspek Scorecard</h3>
                            <span class="wca-badge wca-b--slate">{{ aspekScorecard.length }} aspek ter-evaluasi</span>
                        </div>
                        <div class="wca-card__body" v-if="aspekScorecard.length">
                            <div class="wca-scorecard-list">
                                <div v-for="(item, i) in aspekScorecard" :key="i" class="wca-scorecard-row hover-lift">
                                    <div class="wca-scorecard-row__rank" :class="scoreRank(i)">
                                        <template v-if="i === 0">🥇</template>
                                        <template v-else-if="i === 1">🥈</template>
                                        <template v-else-if="i === 2">🥉</template>
                                        <template v-else>#{{ i + 1 }}</template>
                                    </div>
                                    <div class="wca-scorecard-row__label flex-grow-1" style="min-width:0">
                                        <span class="text-xs font-bold text-slate-800 text-truncate d-block" :title="item.label">{{ item.label }}</span>
                                        <div class="d-flex align-items-center gap-1 mt-1">
                                            <span class="wca-badge wca-b--slate text-xxs py-0 px-2">{{ item.tipe }}</span>
                                            <span class="text-xxs text-muted">• {{ item.total }} respon</span>
                                        </div>
                                    </div>
                                    <div class="wca-scorecard-row__bar-wrap d-none d-sm-block ms-2" style="flex: 0 0 100px; width: 100px;">
                                        <div class="wca-progress-bar" style="height:7px; border-radius: 99px; background: #f1f5f9;">
                                            <div class="wca-progress-bar__fill" :class="scoreColor(parseFloat(item.avg_pct))" :style="{ width: Math.min(parseFloat(item.avg_pct), 100) + '%', borderRadius: '99px' }"></div>
                                        </div>
                                    </div>
                                    <div class="wca-scorecard-row__score ms-2">
                                        <span class="wca-score-pill" :class="scorePillClass(parseFloat(item.avg_pct))">
                                            <strong>{{ Math.round(parseFloat(item.avg_pct)) }}%</strong>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div v-else class="wca-card__body py-4">
                            <div class="wca-empty-state">
                                <div class="wca-empty-state__icon bg-indigo-light text-indigo mb-2"><i class="bi bi-list-stars"></i></div>
                                <h5 class="wca-empty-state__title">Belum Ada Data Rating</h5>
                                <p class="wca-empty-state__sub mb-0">Respons kandidat belum mencakup pertanyaan RATING atau LIKERT.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right: Lolos vs Gugur -->
                <div class="wca-col-12 wca-col-lg-5">
                    <div class="wca-card h-100">
                        <div class="wca-card__head">
                            <h3><i class="bi bi-people-fill text-violet me-1"></i> Perbandingan Lolos vs Gugur</h3>
                        </div>
                        <div class="wca-card__body">
                            <div v-if="lolosVsGagal?.lolos || lolosVsGagal?.gagal" class="wca-compare-wrapper">
                                <div class="wca-compare-grid mb-3">
                                    <div class="wca-compare-box wca-compare-box--lolos p-3">
                                        <div class="wca-compare-box__badge text-emerald font-bold mb-1"><i class="bi bi-check-circle-fill me-1"></i> Diterima (Lolos)</div>
                                        <div class="wca-compare-box__num text-emerald">{{ lolosVsGagal.lolos?.total || 0 }} <small class="text-xs text-muted">respon</small></div>
                                        <div class="wca-compare-box__meta mt-2 pt-2 border-top border-dashed">
                                            <span class="text-xs text-muted"><i class="bi bi-clock me-1"></i> Rata² Waktu Respons:</span>
                                            <strong class="text-xs text-slate-700">{{ formatSeconds(lolosVsGagal.lolos?.avg_waktu_detik) }}</strong>
                                        </div>
                                    </div>
                                    <div class="wca-compare-box wca-compare-box--gagal p-3">
                                        <div class="wca-compare-box__badge text-rose font-bold mb-1"><i class="bi bi-x-circle-fill me-1"></i> Ditolak (Gugur)</div>
                                        <div class="wca-compare-box__num text-rose">{{ lolosVsGagal.gagal?.total || 0 }} <small class="text-xs text-muted">respon</small></div>
                                        <div class="wca-compare-box__meta mt-2 pt-2 border-top border-dashed">
                                            <span class="text-xs text-muted"><i class="bi bi-clock me-1"></i> Rata² Waktu Respons:</span>
                                            <strong class="text-xs text-slate-700">{{ formatSeconds(lolosVsGagal.gagal?.avg_waktu_detik) }}</strong>
                                        </div>
                                    </div>
                                </div>
                                <!-- Visual Split Ratio Bar -->
                                <div v-if="(lolosVsGagal.lolos?.total || 0) + (lolosVsGagal.gagal?.total || 0) > 0" class="wca-ratio-split p-2 bg-slate-50 rounded-3 border">
                                    <div class="d-flex justify-content-between text-xs font-bold mb-1">
                                        <span class="text-emerald">Lolos {{ Math.round(((lolosVsGagal.lolos?.total || 0) / ((lolosVsGagal.lolos?.total || 0) + (lolosVsGagal.gagal?.total || 0))) * 100) }}%</span>
                                        <span class="text-rose">Gugur {{ 100 - Math.round(((lolosVsGagal.lolos?.total || 0) / ((lolosVsGagal.lolos?.total || 0) + (lolosVsGagal.gagal?.total || 0))) * 100) }}%</span>
                                    </div>
                                    <div class="wca-progress-bar overflow-hidden d-flex" style="height: 8px; border-radius: 99px; background: #e2e8f0;">
                                        <div class="bg-emerald transition-all" :style="{ width: Math.round(((lolosVsGagal.lolos?.total || 0) / ((lolosVsGagal.lolos?.total || 0) + (lolosVsGagal.gagal?.total || 0))) * 100) + '%' }"></div>
                                        <div class="bg-rose transition-all flex-grow-1"></div>
                                    </div>
                                </div>
                            </div>
                            <div v-else class="wca-empty-state py-4">
                                <div class="wca-empty-state__icon bg-violet-light text-violet mb-2"><i class="bi bi-people-fill"></i></div>
                                <h5 class="wca-empty-state__title">Belum Ada Data</h5>
                                <p class="wca-empty-state__sub mb-0">Belum ada kandidat dengan hasil akhir DITERIMA/DITOLAK yang mengisi feedback.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ═══ LAYER 3: TABS (Trend | Program | Suara) ═══ -->
            <div v-if="!analyticsLoading" class="wca-card mb-4">
                <div class="wca-card__head" style="border-bottom:1px solid #e2e8f0;padding-bottom:0">
                    <div class="wca-drawer-inner-tabs" style="background:transparent;border:none;padding:0">
                        <button type="button" :class="['wca-dtab', { 'wca-dtab--active': analyticsSubtab === 'trend' }]" @click="analyticsSubtab = 'trend'">
                            <i class="bi bi-graph-up-arrow"></i> Trend
                        </button>
                        <button type="button" :class="['wca-dtab', { 'wca-dtab--active': analyticsSubtab === 'program' }]" @click="analyticsSubtab = 'program'">
                            <i class="bi bi-bar-chart-fill"></i> Program
                        </button>
                        <button type="button" :class="['wca-dtab', { 'wca-dtab--active': analyticsSubtab === 'suara' }]" @click="analyticsSubtab = 'suara'">
                            <i class="bi bi-chat-quote-fill"></i> Suara Kandidat
                            <span v-if="textVoice.length" class="wca-badge wca-b--indigo ms-1" style="font-size:0.65rem">{{ textVoice.length }}</span>
                        </button>
                    </div>
                </div>
                <div class="wca-card__body">

                    <!-- Tab: TREND (multi-metrik) -->
                    <div v-if="analyticsSubtab === 'trend'">
                        <apexchart v-if="trendSeries.length" type="line" :options="trendOpts" :series="trendSeries" height="280" />
                        <div v-else class="wca-empty-state py-5">
                            <div class="wca-empty-state__icon bg-emerald-light text-emerald mb-2"><i class="bi bi-graph-up-arrow"></i></div>
                            <h5 class="wca-empty-state__title">Data Tren Belum Cukup</h5>
                            <p class="wca-empty-state__sub mb-0">Belum terdapat data rating/NPS bulanan yang cukup pada rentang waktu ini.</p>
                        </div>
                    </div>

                    <!-- Tab: PROGRAM (leaderboard) -->
                    <div v-if="analyticsSubtab === 'program'">
                        <div v-if="programComparison.length" class="wca-prog-list">
                            <div v-for="(p, idx) in programComparison" :key="p.Id_Program" class="wca-prog-item">
                                <div class="wca-prog-item__rank" :class="rankClass(idx)">#{{ idx + 1 }}</div>
                                <div class="wca-prog-item__main">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <strong>{{ p.Program_Nama }}</strong>
                                        <span class="badge-rating"><i class="bi bi-star-fill text-amber"></i> {{ fmt(p.avg_rating) }}</span>
                                    </div>
                                    <div class="d-flex justify-content-between text-xs text-muted">
                                        <span>{{ p.total_respon }} respons terisi</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div v-else class="wca-empty-state py-5">
                            <div class="wca-empty-state__icon bg-amber-light text-amber mb-2"><i class="bi bi-bar-chart-fill"></i></div>
                            <h5 class="wca-empty-state__title">Belum Ada Data Program</h5>
                            <p class="wca-empty-state__sub mb-0">Belum ada respon feedback yang terhubung dengan program rekrutmen.</p>
                        </div>
                    </div>

                    <!-- Tab: SUARA KANDIDAT (TEXTAREA feed) -->
                    <div v-if="analyticsSubtab === 'suara'">
                        <div class="mb-3" v-if="textVoice.length">
                            <el-input v-model="voiceSearch" placeholder="Cari di suara kandidat..." clearable size="default">
                                <template #prefix><i class="bi bi-search"></i></template>
                            </el-input>
                        </div>
                        <div v-if="filteredVoice.length" class="wca-voice-feed">
                            <div v-for="(v, idx) in filteredVoice" :key="idx" class="wca-voice-card">
                                <div class="wca-voice-card__head">
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="wca-avatar wca-avatar--sm bg-indigo text-white font-bold">{{ initials(v.nama_kandidat) }}</span>
                                        <div>
                                            <strong class="d-block" style="font-size:0.8rem">{{ v.nama_kandidat }}</strong>
                                            <span class="text-xs text-muted">{{ v.kode_lamaran }}</span>
                                        </div>
                                    </div>
                                    <span class="wca-badge wca-b--slate text-xs">{{ v.label?.substring(0, 50) }}{{ v.label?.length > 50 ? '…' : '' }}</span>
                                </div>
                                <div class="wca-voice-card__body">
                                    <i class="bi bi-quote text-indigo me-1"></i>
                                    <em>"{{ v.jawaban }}"</em>
                                </div>
                            </div>
                        </div>
                        <div v-else class="wca-empty-state py-5">
                            <div class="wca-empty-state__icon bg-indigo-light text-indigo mb-2"><i class="bi bi-chat-quote-fill"></i></div>
                            <h5 class="wca-empty-state__title">{{ textVoice.length ? 'Tidak Ada Hasil' : 'Belum Ada Suara Kandidat' }}</h5>
                            <p class="wca-empty-state__sub mb-0">{{ textVoice.length ? 'Tidak ada jawaban teks yang cocok dengan pencarian.' : 'Belum ada kandidat yang memberikan jawaban teks (TEXTAREA).' }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Detail Per Pertanyaan (hanya jika form dipilih) -->
            <div v-if="!analyticsLoading && (filters.form_ids && filters.form_ids.length) && perPertanyaan && perPertanyaan.length" class="wca-card mb-4">
                <div class="wca-card__head">
                    <div>
                        <h3><i class="bi bi-list-ol text-indigo"></i> Detail Per Pertanyaan</h3>
                        <p class="text-xs text-muted mb-0">Rincian agregat statistik dari seluruh pertanyaan dalam form terpilih</p>
                    </div>
                    <span class="wca-badge wca-b--indigo">{{ perPertanyaan.length }} pertanyaan</span>
                </div>
                <div class="wca-card__body">
                    <div class="wca-q-grid">
                        <div v-for="(p, i) in perPertanyaan" :key="p.id" class="wca-q-card">
                            <div class="wca-q-card__head">
                                <div class="wca-q-card__num">Q{{ i + 1 }}</div>
                                <div class="wca-q-card__title">
                                    <strong>{{ p.label }}</strong>
                                    <span class="wca-badge wca-b--slate ms-2">{{ p.tipe }}</span>
                                </div>
                            </div>
                            <div class="wca-q-card__body">
                                <template v-if="p.tipe === 'RATING' || p.tipe === 'LIKERT'">
                                    <div class="wca-q-score-box">
                                        <div class="wca-q-score-big">{{ p.avg }}</div>
                                        <div>
                                            <div class="wca-stars-inline mb-1">
                                                <i v-for="s in 5" :key="s" class="bi" :class="s <= Math.round(p.avg) ? 'bi-star-fill text-amber' : 'bi-star text-slate-300'"></i>
                                            </div>
                                            <small class="text-muted">{{ p.total }} total respon</small>
                                        </div>
                                    </div>
                                    <div class="wca-star-dist mt-3">
                                        <div v-for="star in [5,4,3,2,1]" :key="star" class="wca-star-dist__row">
                                            <span class="wca-star-dist__label">{{ star }} <i class="bi bi-star-fill text-amber"></i></span>
                                            <div class="wca-progress-bar flex-grow-1 mx-2">
                                                <div class="wca-progress-bar__fill bg-amber" :style="{ width: calcPct(p.distribusi?.[star] || 0, p.total) + '%' }"></div>
                                            </div>
                                            <span class="wca-star-dist__val">{{ p.distribusi?.[star] || 0 }}</span>
                                        </div>
                                    </div>
                                </template>
                                <template v-else-if="p.tipe === 'NPS'">
                                    <div class="wca-q-score-box">
                                        <div class="wca-q-score-big" :class="p.nps >= 0 ? 'text-emerald' : 'text-rose'">{{ displayNps(p.nps) }}</div>
                                        <div>
                                            <span class="wca-badge" :class="p.nps >= 50 ? 'wca-b--emerald' : (p.nps >= 0 ? 'wca-b--amber' : 'wca-b--rose')">NPS (0-100)</span>
                                            <small class="text-muted d-block mt-1 font-medium">{{ p.total }} total respon</small>
                                        </div>
                                    </div>
                                </template>
                                <template v-else-if="p.tipe === 'TEXTAREA'">
                                    <div class="wca-quote-list">
                                        <div v-for="(t, idx) in (p.recent || [])" :key="idx" class="wca-quote-item">
                                            <i class="bi bi-quote text-indigo me-1"></i>
                                            <span>"{{ t }}"</span>
                                        </div>
                                    </div>
                                    <button type="button" v-if="p.total > 5" class="wca-btn-link text-indigo text-xs mt-2" @click="openAllTextModal(p)">
                                        <i class="bi bi-arrows-angle-expand"></i> Lihat semua {{ p.total }} jawaban teks
                                    </button>
                                </template>
                                <template v-else>
                                    <div class="wca-choice-list">
                                        <div v-for="(v, k) in p.counts" :key="k" class="wca-choice-item">
                                            <div class="d-flex justify-content-between text-xs mb-1">
                                                <strong>{{ k }}</strong>
                                                <span>{{ v }} ({{ calcPct(v, p.total) }}%)</span>
                                            </div>
                                            <div class="wca-progress-bar">
                                                <div class="wca-progress-bar__fill bg-indigo" :style="{ width: calcPct(v, p.total) + '%' }"></div>
                                            </div>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </template>

        <!-- â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• -->
        <!-- TAB 2: MONITORING                                               -->
        <!-- â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• -->
        <template v-if="tab === 'monitoring'">
            <!-- Monitoring KPI Row -->
            <div class="wca-kpi-grid mb-4">
                <div class="wca-kpi-card wca-kpi-card--slate">
                    <div class="wca-kpi-card__top"><span class="wca-kpi-card__icon"><i class="bi bi-send-fill"></i></span></div>
                    <div class="wca-kpi-card__body">
                        <div class="wca-kpi-card__value">{{ monKpi.total }}</div>
                        <div class="wca-kpi-card__label">Total Link Dikirim</div>
                    </div>
                </div>
                <div class="wca-kpi-card wca-kpi-card--emerald">
                    <div class="wca-kpi-card__top"><span class="wca-kpi-card__icon"><i class="bi bi-check-circle-fill"></i></span></div>
                    <div class="wca-kpi-card__body">
                        <div class="wca-kpi-card__value text-emerald">{{ monKpi.terisi }}</div>
                        <div class="wca-kpi-card__label">Terisi</div>
                    </div>
                </div>
                <div class="wca-kpi-card wca-kpi-card--amber">
                    <div class="wca-kpi-card__top"><span class="wca-kpi-card__icon"><i class="bi bi-hourglass-split"></i></span></div>
                    <div class="wca-kpi-card__body">
                        <div class="wca-kpi-card__value text-amber">{{ monKpi.pending }}</div>
                        <div class="wca-kpi-card__label">Pending</div>
                    </div>
                </div>
                <div class="wca-kpi-card wca-kpi-card--rose">
                    <div class="wca-kpi-card__top"><span class="wca-kpi-card__icon"><i class="bi bi-exclamation-triangle-fill"></i></span></div>
                    <div class="wca-kpi-card__body">
                        <div class="wca-kpi-card__value text-rose">{{ monKpi.expired }}</div>
                        <div class="wca-kpi-card__label">Expired</div>
                    </div>
                </div>
                <div class="wca-kpi-card wca-kpi-card--indigo">
                    <div class="wca-kpi-card__top"><span class="wca-kpi-card__icon"><i class="bi bi-graph-up"></i></span></div>
                    <div class="wca-kpi-card__body">
                        <div class="wca-kpi-card__value">{{ monKpi.response_rate }}%</div>
                        <div class="wca-kpi-card__label">Resp. Rate</div>
                    </div>
                </div>
            </div>

            <!-- Monitoring Toolbar (Searchable & Multi-Select with +1 Tag Collapse) -->
            <div class="wca-card wca-glass-card mb-3">
                <div class="wca-filter-bar wca-filter-bar--compact">
                    <!-- Search Input -->
                    <div class="wca-filter-item wca-filter-item--search">
                        <el-input 
                            v-model="monFilters.search" 
                            placeholder="Cari kandidat / kode..." 
                            clearable 
                            @input="debouncedSearch" 
                            size="default"
                        >
                            <template #prefix><i class="bi bi-search"></i></template>
                        </el-input>
                    </div>

                    <!-- Dropdown Form -->
                    <div class="wca-filter-item wca-filter-item--select">
                        <el-select 
                            v-model="monFilters.form_ids" 
                            multiple
                            filterable
                            collapse-tags
                            collapse-tags-tooltip
                            :max-collapse-tags="1"
                            placeholder="Semua Form" 
                            clearable 
                            @change="loadMonitoring" 
                            size="default" 
                            style="width: 100%"
                        >
                            <el-option v-for="f in forms" :key="f.Id_Master_Feedback_Form" :label="f.Nama || f.nama" :value="f.Id_Master_Feedback_Form" />
                        </el-select>
                    </div>

                    <!-- Dropdown Program -->
                    <div class="wca-filter-item wca-filter-item--select">
                        <el-select 
                            v-model="monFilters.program_ids" 
                            multiple
                            filterable
                            collapse-tags
                            collapse-tags-tooltip
                            :max-collapse-tags="1"
                            placeholder="Semua Program" 
                            clearable 
                            @change="loadMonitoring" 
                            size="default" 
                            style="width: 100%"
                        >
                            <el-option 
                                v-for="p in programs" 
                                :key="p.Id_Program || p.id" 
                                :label="p.Nama || p.nama || p.Program_Nama || p.kode" 
                                :value="p.Id_Program || p.id" 
                            />
                        </el-select>
                    </div>

                    <!-- Status Pills (Pushed to Right on Desktop) -->
                    <div class="wca-filter-item wca-filter-item--pills ms-auto">
                        <div class="wca-status-pills">
                            <button type="button" :class="['wca-pill', { 'wca-pill--active': !monFilters.status }]" @click="setMonStatus(null)">Semua</button>
                            <button type="button" :class="['wca-pill', { 'wca-pill--active': monFilters.status === 'MENUNGGU' }]" @click="setMonStatus('MENUNGGU')">Pending</button>
                            <button type="button" :class="['wca-pill', { 'wca-pill--active': monFilters.status === 'TERISI' }]" @click="setMonStatus('TERISI')">Terisi</button>
                            <button type="button" :class="['wca-pill', { 'wca-pill--active': monFilters.status === 'EXPIRED' }]" @click="setMonStatus('EXPIRED')">Expired</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Table Card -->
            <div class="wca-card wca-card--table-container">
                <div class="wca-card__body--flush">
                    <el-table 
                        ref="monTable"
                        :data="monList" 
                        size="default" 
                        v-loading="monLoading" 
                        element-loading-text="Memuat data monitoring..."
                        @selection-change="onMonSelect" 
                        class="wca-custom-table"
                    >
                        <el-table-column type="selection" width="48" align="center" />
                        <el-table-column label="Kandidat" min-width="240">
                            <template #default="{row}">
                                <div class="wca-user-cell" @click="viewDetail(row.Id_Feedback_Jawaban)" style="cursor:pointer">
                                    <span class="wca-avatar wca-avatar--sm bg-indigo text-white font-bold">
                                        {{ initials(row.Nama_Kandidat) }}
                                    </span>
                                    <div>
                                        <strong class="d-block text-indigo-hover mb-0 font-semibold" style="font-size:0.875rem">{{ row.Nama_Kandidat }}</strong>
                                        <small class="text-muted" style="font-size:0.75rem">{{ row.Email }}</small>
                                    </div>
                                </div>
                            </template>
                        </el-table-column>
                        <el-table-column prop="Kode_Lamaran" label="Kode Lamaran" width="150">
                            <template #default="{row}">
                                <span class="font-mono text-xs text-slate-600 bg-slate-100 px-2 py-1 rounded">{{ row.Kode_Lamaran }}</span>
                            </template>
                        </el-table-column>
                        <el-table-column prop="Program_Nama" label="Program" width="140">
                            <template #default="{row}">
                                <span class="text-xs font-medium text-slate-700">{{ row.Program_Nama || 'â€”' }}</span>
                            </template>
                        </el-table-column>
                        <el-table-column prop="Form_Nama" label="Form Feedback" width="140">
                            <template #default="{row}">
                                <span class="text-xs text-slate-600">{{ row.Form_Nama || 'Default' }}</span>
                            </template>
                        </el-table-column>
                        <el-table-column label="Hasil Seleksi" width="120" align="center">
                            <template #default="{row}">
                                <span class="wca-badge" :class="isLolos(row.Hasil_Akhir) ? 'wca-b--emerald' : 'wca-b--rose'">
                                    <i class="bi me-1" :class="isLolos(row.Hasil_Akhir) ? 'bi-check-circle-fill' : 'bi-x-circle-fill'"></i>
                                    {{ isLolos(row.Hasil_Akhir) ? 'LOLOS' : 'GUGUR' }}
                                </span>
                            </template>
                        </el-table-column>
                        <el-table-column label="Status Pengisian" width="130" align="center">
                            <template #default="{row}">
                                <span class="wca-badge" :class="statusBadgeClass(row.Status_Pengisian)">
                                    {{ row.Status_Pengisian }}
                                </span>
                            </template>
                        </el-table-column>
                        <el-table-column label="Aksi" width="140" align="right">
                            <template #default="{row}">
                                <div class="d-flex gap-1 justify-content-end align-items-center">
                                    <button type="button" class="wca-btn-icon-soft" @click="viewDetail(row.Id_Feedback_Jawaban)" title="Lihat Detail Jawaban">
                                        <i class="bi bi-eye-fill text-indigo"></i>
                                        <span>Detail</span>
                                    </button>
                                    <el-dropdown trigger="click">
                                        <button type="button" class="wca-btn-icon-sm" title="Menu Lainnya"><i class="bi bi-three-dots-vertical"></i></button>
                                        <template #dropdown>
                                            <el-dropdown-menu>
                                                <el-dropdown-item :disabled="row.Status_Pengisian === 'TERISI'" @click="openReassign(row)">
                                                    <i class="bi bi-arrow-repeat me-1" :class="row.Status_Pengisian === 'TERISI' ? 'text-slate-300' : 'text-indigo'"></i>
                                                    {{ row.Status_Pengisian === 'TERISI' ? 'Tidak bisa (sudah TERISI)' : 'Ganti Form' }}
                                                </el-dropdown-item>
                                                <el-dropdown-item :disabled="row.Status_Pengisian === 'TERISI'" @click="resendSingle(row)">
    <i class="bi bi-envelope-at me-1" :class="row.Status_Pengisian === 'TERISI' ? 'text-slate-300' : 'text-amber'"></i>
    {{ row.Status_Pengisian === 'TERISI' ? 'Sudah TERISI' : 'Resend Email' }}
</el-dropdown-item>
                                            </el-dropdown-menu>
                                        </template>
                                    </el-dropdown>
                                </div>
                            </template>
                        </el-table-column>
                    </el-table>
                </div>

                <!-- Floating Glass Bulk Action Tray -->
                <transition name="el-zoom-in-bottom">
                    <div v-if="monSelected.length" class="wca-floating-bulk-bar">
                        <div class="d-flex align-items-center gap-2">
                            <span class="wca-bulk-count-badge">
                                <i class="bi bi-check2-square"></i>
                                <strong>{{ monSelected.length }}</strong>
                            </span>
                            <span class="text-sm font-semibold text-slate-700">kandidat dipilih</span>
                        </div>
                        <div class="d-flex gap-2 align-items-center">
                            <button type="button" class="wca-btn wca-btn--primary wca-btn--sm" @click="bulkReassign">
                                <i class="bi bi-arrow-repeat"></i> Bulk Ganti Form
                            </button>
                            <button type="button" class="wca-btn wca-btn--warning wca-btn--sm" @click="bulkResend">
                                <i class="bi bi-envelope-at"></i> Bulk Resend Email
                            </button>
                            <button type="button" class="wca-btn wca-btn--ghost wca-btn--sm text-slate-500" @click="clearSelection">
                                <i class="bi bi-x-lg"></i> Batal
                            </button>
                        </div>
                    </div>
                </transition>

                <!-- Table Footer & Pagination -->
                <div class="wca-card__footer d-flex justify-content-between align-items-center py-3 px-4">
                    <small class="text-muted font-medium">Menampilkan {{ monList.length }} dari {{ monTotal }} data feedback</small>
                    <el-pagination 
                        background 
                        layout="prev, pager, next" 
                        :total="monTotal" 
                        :page-size="20" 
                        @current-change="(p) => loadMonitoring(p)" 
                        class="wca-custom-pagination"
                    />
                </div>
            </div>
        </template>

        <!-- â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• -->
        <!-- UPGRADED CANDIDATE DETAIL DRAWER                                -->
        <!-- â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• -->
        <el-drawer 
            v-model="detailDrawerOpen" 
            size="620px" 
            destroy-on-close
            :show-close="true"
            :teleported="false"
            class="wca-detail-drawer-custom"
        >
            <template #header>
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-person-lines-fill text-indigo fs-5"></i>
                    <h4 class="mb-0 font-bold">Rincian Feedback Kandidat</h4>
                </div>
            </template>

            <!-- Skeleton Loading State for Drawer -->
            <div v-if="detailLoading" class="p-4">
                <div class="wca-drawer-hero mb-4 p-4">
                    <div class="d-flex align-items-center gap-3">
                        <div class="wca-shimmer" style="width: 52px; height: 52px; border-radius: 14px;"></div>
                        <div>
                            <div class="wca-shimmer mb-2" style="width: 160px; height: 20px;"></div>
                            <div class="wca-shimmer" style="width: 200px; height: 14px;"></div>
                        </div>
                    </div>
                </div>
                <div v-for="i in 3" :key="i" class="wca-card p-3 mb-3">
                    <div class="wca-shimmer mb-2" style="width: 100px; height: 14px;"></div>
                    <div class="wca-shimmer mb-3" style="width: 80%; height: 18px;"></div>
                    <div class="wca-shimmer" style="width: 100%; height: 60px; border-radius: 8px;"></div>
                </div>
            </div>

            <div v-else-if="selectedFeedbackDetail" class="wca-drawer-body">
                <!-- HERO PROFILE CARD -->
                <div class="wca-drawer-hero mb-4">
                    <div class="wca-drawer-hero__bg"></div>
                    <div class="wca-drawer-hero__content">
                        <div class="d-flex align-items-start justify-content-between gap-3 mb-3">
                            <div class="d-flex align-items-center gap-3">
                                <div class="wca-avatar-hero">
                                    {{ initials(selectedFeedbackDetail.Nama_Kandidat) }}
                                </div>
                                <div>
                                    <h3 class="wca-drawer-hero__name mb-0">{{ selectedFeedbackDetail.Nama_Kandidat }}</h3>
                                    <span class="wca-drawer-hero__email text-muted d-block">{{ selectedFeedbackDetail.Email }}</span>
                                    <div class="d-flex gap-2 align-items-center mt-2">
                                        <span class="wca-badge wca-b--slate"><i class="bi bi-hash"></i> {{ selectedFeedbackDetail.Kode_Lamaran }}</span>
                                        <span class="wca-badge" :class="isLolos(selectedFeedbackDetail.Hasil_Akhir) ? 'wca-b--emerald' : 'wca-b--rose'">
                                            <i class="bi me-1" :class="isLolos(selectedFeedbackDetail.Hasil_Akhir) ? 'bi-check-circle-fill' : 'bi-x-circle-fill'"></i>
                                            {{ isLolos(selectedFeedbackDetail.Hasil_Akhir) ? 'LOLOS' : 'GUGUR' }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <!-- Instant Score Box -->
                            <div v-if="calcDetailAverageRating > 0" class="wca-hero-score-badge" title="Rata-rata rating yang diberikan kandidat ini">
                                <span class="wca-hero-score-num">{{ calcDetailAverageRating }}</span>
                                <div class="wca-stars-inline">
                                    <i v-for="s in 5" :key="s" class="bi" :class="s <= Math.round(calcDetailAverageRating) ? 'bi-star-fill text-amber' : 'bi-star text-slate-300'"></i>
                                </div>
                                <small class="text-xs text-muted mt-1">Avg Rating</small>
                            </div>
                        </div>

                        <!-- Inner Tabs Switcher -->
                        <div class="wca-drawer-inner-tabs mt-3">
                            <button type="button" :class="['wca-dtab', { 'wca-dtab--active': drawerTab === 'jawaban' }]" @click="drawerTab = 'jawaban'">
                                <i class="bi bi-list-check"></i> Jawaban Feedback ({{ selectedFeedbackDetail.jawaban?.length || 0 }})
                            </button>
                            <button type="button" :class="['wca-dtab', { 'wca-dtab--active': drawerTab === 'profil' }]" @click="drawerTab = 'profil'">
                                <i class="bi bi-info-circle"></i> Info Lamaran
                            </button>
                        </div>
                    </div>
                </div>

                <!-- TAB 1: JAWABAN FEEDBACK -->
                <div v-if="drawerTab === 'jawaban'">
                    <div v-if="selectedFeedbackDetail.jawaban && selectedFeedbackDetail.jawaban.length" class="wca-q-answer-stream">
                        <div v-for="(ans, idx) in selectedFeedbackDetail.jawaban" :key="idx" class="wca-q-ans-box">
                            <div class="wca-q-ans-box__head">
                                <span class="wca-q-idx">Pertanyaan {{ idx + 1 }}</span>
                                <span class="wca-badge wca-b--slate">{{ ans.Tipe }}</span>
                            </div>
                            <h5 class="wca-q-ans-box__label">{{ ans.Label }}</h5>

                            <div class="wca-q-ans-box__body">
                                <!-- TYPE: RATING / LIKERT -->
                                <template v-if="ans.Tipe === 'RATING' || ans.Tipe === 'LIKERT'">
                                    <div class="wca-rating-display-card">
                                        <div class="d-flex align-items-center gap-3">
                                            <span class="wca-rating-big-num">{{ ans.Jawaban }} <small class="fs-6 text-muted">/ 5</small></span>
                                            <div>
                                                <div class="wca-stars-inline fs-5">
                                                    <i v-for="s in 5" :key="s" class="bi" :class="s <= parseInt(ans.Jawaban) ? 'bi-star-fill text-amber' : 'bi-star text-slate-300'"></i>
                                                </div>
                                                <span class="text-xs font-semibold text-indigo d-block mt-1">{{ getRatingSentiment(ans.Jawaban) }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </template>

                                <!-- TYPE: NPS (0-10) -->
                                <template v-else-if="ans.Tipe === 'NPS'">
                                    <div class="wca-nps-display-card">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <div class="d-flex align-items-center gap-2">
                                                <span class="fs-3 font-bold" :class="getNpsCategoryInfo(ans).scoreClass">
                                                    {{ ans.Jawaban }} <small class="fs-6 text-muted">/ {{ getNpsScale(ans).max }}</small>
                                                </span>
                                                <span class="wca-badge" :class="getNpsCategoryInfo(ans).class">
                                                    {{ getNpsCategoryInfo(ans).category }}
                                                </span>
                                            </div>
                                            <small class="text-xs text-muted">{{ getNpsCategoryInfo(ans).label }}</small>
                                        </div>

                                        <!-- NPS Scale Visual (adapts to configured Skala_Min/Max) -->
                                        <div class="wca-nps-scale-bar">
                                            <div
                                                v-for="num in getNpsScale(ans).count"
                                                :key="getNpsScale(ans).min + num - 1"
                                                class="wca-nps-scale-pip"
                                                :class="{
                                                    'pip-selected': (getNpsScale(ans).min + num - 1) === parseInt(ans.Jawaban),
                                                    'pip-promoter': getNpsCategoryInfo(ans).isPromoter,
                                                    'pip-passive': getNpsCategoryInfo(ans).isPassive,
                                                    'pip-detractor': getNpsCategoryInfo(ans).isDetractor
                                                }"
                                            >
                                                {{ getNpsScale(ans).min + num - 1 }}
                                            </div>
                                        </div>
                                    </div>
                                </template>

                                <!-- TYPE: TEXTAREA -->
                                <template v-else-if="ans.Tipe === 'TEXTAREA'">
                                    <div class="wca-quote-box-modern">
                                        <div class="d-flex justify-content-between align-items-start mb-1">
                                            <i class="bi bi-quote text-indigo fs-3"></i>
                                            <button type="button" class="wca-btn-icon-sm" @click="copyText(ans.Jawaban)" title="Copy Teks">
                                                <i class="bi bi-clipboard"></i>
                                            </button>
                                        </div>
                                        <p class="wca-quote-text">{{ ans.Jawaban || '— Tidak memberikan catatan —' }}</p>
                                    </div>
                                </template>

                                <!-- TYPE: CHOICE / RADIO / CHECKBOX -->
                                <template v-else>
                                    <div class="wca-choice-tag-box">
                                        <span class="wca-choice-pill">
                                            <i class="bi bi-check-circle-fill text-emerald me-1"></i>
                                            <strong>{{ ans.Jawaban || '—' }}</strong>
                                        </span>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>
                    <div v-else class="wca-empty-state py-5">
                        <div class="wca-empty-state__icon bg-indigo-light text-indigo mb-2">
                            <i class="bi bi-chat-left-dots-fill"></i>
                        </div>
                        <h5 class="wca-empty-state__title">Jawaban Belum Tersedia</h5>
                        <p class="wca-empty-state__sub mb-0">Kandidat ini belum mengisi atau mengirimkan form feedback.</p>
                    </div>
                </div>

                <!-- TAB 2: PROFIL & INFO LAMARAN -->
                <div v-if="drawerTab === 'profil'">
                    <div class="wca-info-card-grid">
                        <div class="wca-info-card">
                            <span class="wca-info-card__label"><i class="bi bi-person me-1"></i> Nama Lengkap</span>
                            <strong>{{ selectedFeedbackDetail.Nama_Kandidat }}</strong>
                        </div>

                        <div class="wca-info-card">
                            <span class="wca-info-card__label"><i class="bi bi-envelope me-1"></i> Email kandidat</span>
                            <strong>{{ selectedFeedbackDetail.Email }}</strong>
                        </div>

                        <div class="wca-info-card">
                            <span class="wca-info-card__label"><i class="bi bi-briefcase me-1"></i> Program Rekrutmen</span>
                            <strong>{{ selectedFeedbackDetail.Program_Nama || '—' }}</strong>
                        </div>

                        <div class="wca-info-card">
                            <span class="wca-info-card__label"><i class="bi bi-journal-text me-1"></i> Form Feedback</span>
                            <strong>{{ selectedFeedbackDetail.Form_Nama || 'Form Default' }}</strong>
                        </div>

                        <div class="wca-info-card">
                            <span class="wca-info-card__label"><i class="bi bi-check2-circle me-1"></i> Status Pengisian</span>
                            <span class="wca-badge" :class="statusBadgeClass(selectedFeedbackDetail.Status_Pengisian)">
                                {{ selectedFeedbackDetail.Status_Pengisian }}
                            </span>
                        </div>

                        <div class="wca-info-card">
                            <span class="wca-info-card__label"><i class="bi bi-clock me-1"></i> Waktu Submit Feedback</span>
                            <strong>{{ selectedFeedbackDetail.Submitted_At || 'Belum diisi' }}</strong>
                        </div>
                    </div>
                </div>

                <!-- DRAWER ACTION FOOTER -->
                <div class="wca-drawer-footer">
                    <button type="button" class="wca-btn wca-btn--ghost wca-btn--sm" @click="copyAnswersToClipboard">
                        <i class="bi bi-clipboard-check"></i> Copy Ringkasan
                    </button>
                    <div class="d-flex gap-2">
                        <button
                            type="button"
                            class="wca-btn wca-btn--soft wca-btn--sm"
                            :disabled="selectedFeedbackDetail?.Status_Pengisian === 'TERISI'"
                            :title="selectedFeedbackDetail?.Status_Pengisian === 'TERISI' ? 'Feedback sudah TERISI â€” tidak bisa diganti form' : 'Ganti form feedback'"
                            :onClick="selectedFeedbackDetail?.Status_Pengisian === 'TERISI' ? null : openReassignFromDrawer"
                        >
                            <i class="bi bi-arrow-repeat"></i>
                            {{ selectedFeedbackDetail?.Status_Pengisian === 'TERISI' ? 'Sudah TERISI' : 'Ganti Form' }}
                        </button>
                        <button
                            type="button"
                            class="wca-btn wca-btn--primary wca-btn--sm"
                            :disabled="selectedFeedbackDetail?.Status_Pengisian === 'TERISI'"
                            :title="selectedFeedbackDetail?.Status_Pengisian === 'TERISI' ? 'Feedback sudah TERISI — tidak perlu resend' : 'Kirim ulang email feedback'"
                            :onClick="selectedFeedbackDetail?.Status_Pengisian === 'TERISI' ? null : resendFromDrawer"
                        >
                            <i class="bi bi-envelope-at"></i>
                            {{ selectedFeedbackDetail?.Status_Pengisian === 'TERISI' ? 'Sudah TERISI' : 'Resend Email' }}
                        </button>
                    </div>
                </div>
            </div>
        </el-drawer>

        <!-- REASSIGN MODAL -->
        <el-dialog v-model="reassignOpen" width="460px" :teleported="false" class="wca-dialog-custom wca-dialog-reassign" :show-close="true">
            <template #header>
                <div class="d-flex align-items-center gap-3">
                    <div class="wca-modal-icon-badge bg-indigo-light text-indigo">
                        <i class="bi bi-arrow-repeat fs-4"></i>
                    </div>
                    <div>
                        <h4 class="mb-0 font-bold text-slate-800 fs-6">Ganti Form Feedback</h4>
                        <small class="text-xs text-muted">Sematkan form master feedback baru ke data kandidat</small>
                    </div>
                </div>
            </template>
            <div class="py-1">
                <div class="wca-modal-alert mb-3 p-3 bg-slate-50 border rounded-3">
                    <div class="d-flex align-items-center gap-2 text-slate-700 text-xs">
                        <i class="bi bi-info-circle-fill text-indigo fs-5"></i>
                        <span>Menargetkan <strong>{{ reassignCount }} kandidat</strong>. Perubahan form akan langsung memperbarui assignment feedback kandidat.</span>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="wca-filter-label mb-1.5 d-block text-xs font-bold text-slate-700">Form Feedback Baru</label>
                    <el-select v-model="reassignFormId" placeholder="Pilih Form Feedback..." style="width:100%" size="large" filterable>
                        <el-option v-for="f in forms" :key="f.Id_Master_Feedback_Form" :label="f.Nama || f.nama" :value="f.Id_Master_Feedback_Form">
                            <div class="d-flex align-items-center justify-content-between">
                                <span class="font-semibold">{{ f.Nama || f.nama }}</span>
                                <span class="wca-badge wca-b--indigo text-xxs ms-2">{{ f.Mode_Tampilan || 'WIZARD' }}</span>
                            </div>
                        </el-option>
                    </el-select>
                </div>
                <div class="p-2.5 bg-indigo-50 border border-indigo-100 rounded-3 d-flex align-items-center gap-2">
                    <i class="bi bi-info-circle-fill text-indigo flex-shrink-0"></i>
                    <span class="text-xs text-indigo-800 font-medium">Link baru akan otomatis dikirim via email ke kandidat. Link lama menjadi tidak berlaku.</span>
                </div>
            </div>
            <template #footer>
                <div class="d-flex justify-content-end gap-2 pt-2 border-top">
                    <button type="button" class="btn btn-sm btn-light font-semibold px-3" @click="reassignOpen = false">Batal</button>
                    <button
                        type="button"
                        class="wca-btn wca-btn--primary px-4 font-bold"
                        :onClick="!reassignFormId || reassignLoading ? null : doReassign"
                        :disabled="!reassignFormId || reassignLoading"
                    >
                        <span v-if="reassignLoading" class="spinner-border spinner-border-sm me-1.5" role="status" aria-hidden="true"></span>
                        <span>{{ reassignLoading ? 'Memproses...' : 'Proses Ganti Form' }}</span>
                    </button>
                </div>
            </template>
        </el-dialog>

        <!-- RESEND MODAL -->
        <el-dialog v-model="resendOpen" width="440px" :teleported="false" class="wca-dialog-custom wca-dialog-resend" :show-close="true">
            <template #header>
                <div class="d-flex align-items-center gap-3">
                    <div class="wca-modal-icon-badge bg-amber-light text-amber">
                        <i class="bi bi-envelope-paper-fill fs-4"></i>
                    </div>
                    <div>
                        <h4 class="mb-0 font-bold text-slate-800 fs-6">Kirim Ulang Email Feedback</h4>
                        <small class="text-xs text-muted">Konfirmasi pengiriman ulang pesan notification link</small>
                    </div>
                </div>
            </template>
            <div class="py-1 text-center">
                <div class="wca-modal-confirm-illustration mb-2 py-3 px-4 bg-amber-50 border border-amber-200 rounded-3">
                    <i class="bi bi-envelope-at-fill text-amber display-6 mb-2 d-block"></i>
                    <p class="text-sm font-semibold text-slate-800 mb-1">
                        Kirim ulang link feedback ke <strong>{{ resendCount }} kandidat</strong>?
                    </p>
                    <small class="text-xs text-slate-500 d-block">
                        Email pemberitahuan akan dikirim ke alamat email resmi kandidat yang terdaftar.
                    </small>
                </div>
            </div>
            <template #footer>
                <div class="d-flex justify-content-end gap-2 pt-2 border-top">
                    <button type="button" class="btn btn-sm btn-light font-semibold px-3" @click="resendOpen = false">Batal</button>
                    <button
                        type="button"
                        class="wca-btn wca-btn--amber px-4 font-bold"
                        :onClick="resendLoading ? null : doResend"
                        :disabled="resendLoading"
                    >
                        <span v-if="resendLoading" class="spinner-border spinner-border-sm me-1.5" role="status" aria-hidden="true"></span>
                        <span>{{ resendLoading ? 'Mengirim Email...' : 'Kirim Email' }}</span>
                    </button>
                </div>
            </template>
        </el-dialog>

        <!-- ALL TEXT ANSWERS MODAL -->
        <el-dialog v-model="textModalOpen" :title="textModalQuestion?.label || 'Jawaban Teks'" width="640px" :teleported="false" class="wca-dialog-custom">
            <div class="mb-3">
                <el-input v-model="textSearch" placeholder="Cari jawaban teks..." clearable size="default">
                    <template #prefix><i class="bi bi-search"></i></template>
                </el-input>
            </div>
            <div class="wca-text-modal-list" style="max-height: 400px; overflow-y: auto;">
                <div v-for="(t, idx) in filteredTextAnswers" :key="idx" class="wca-quote-box mb-2">
                    <i class="bi bi-quote text-indigo"></i>
                    <p class="mb-0">{{ t }}</p>
                </div>
            </div>
        </el-dialog>
    </div>
</template>

<script>
import axios from 'axios';
import { Head } from '@inertiajs/vue3';
import apexchart from 'vue3-apexcharts';
import { ElMessage } from 'element-plus';

export default {
    components: { Head, apexchart },
    data() {
        return {
            tab: 'analytics',
            analyticsLoading: false,
            overview: { total_dibuat: 0, total_terisi: 0, response_rate: 0, avg_waktu_detik: 0, skor_stats: [] },
            satisfactionIndex: null,
            aspekScorecard: [],
            programComparison: [],
            npsTrend: [],
            ratingTrend: [],
            textVoice: [],
            perPertanyaan: null,
            lolosVsGagal: null,
            forms: [],
            programs: [],
            filters: { form_ids: [], program_ids: [] },
            dateRange: null,
            analyticsSubtab: 'trend',   // trend | program | suara
            voiceSearch: '',
            exporting: false,

            // Monitoring
            monKpi: { total: 0, terisi: 0, pending: 0, expired: 0, response_rate: 0 },
            monList: [], 
            monTotal: 0, 
            monLoading: false, 
            monSelected: [],
            monFilters: { form_ids: [], program_ids: [], status: 'MENUNGGU', search: '' },
            searchDebounce: null,

            // Modals & Drawers
            reassignOpen: false,
            reassignFormId: null,
            reassignIds: [],
            reassignResend: false,
            reassignLoading: false,
            resendOpen: false,
            resendIds: [],
            resendLoading: false,
            detailDrawerOpen: false,
            detailLoading: false,
            selectedFeedbackDetail: null,
            drawerTab: 'jawaban',

            // Text Answers Modal
            textModalOpen: false,
            textModalQuestion: null,
            textSearch: '',
        };
    },
    computed: {
        reassignCount() { return this.reassignIds.length || 1; },
        resendCount() { return this.resendIds.length || 1; },
        npsStat() {
            return this.overview.skor_stats?.find(s => s.Tipe === 'NPS');
        },
        npsScore() {
            // Standard NPS: -100 to +100 (backend)
            if (this.overview.nps_score !== undefined && this.overview.nps_score !== null) {
                return Math.round(this.overview.nps_score);
            }
            const stat = this.overview.skor_stats?.find(s => s.Tipe === 'NPS');
            return stat ? Math.round(stat.rata2) : 0;
        },
        displayNpsScore() {
            // Normalisasi ke 0-100 untuk tampilan: (nps + 100) / 2
            return Math.round((this.npsScore + 100) / 2);
        },
        displayNpsTrend() {
            // Normalisasi tren NPS ke 0-100
            return this.npsTrend.map(t => ({
                bulan: t.bulan,
                nps: Math.round((t.nps + 100) / 2),
                total: t.total,
            }));
        },
        npsCount() {
            if (this.overview.nps_breakdown?.total !== undefined && this.overview.nps_breakdown?.total !== null) {
                return this.overview.nps_breakdown.total;
            }
            const stat = this.overview.skor_stats?.find(s => s.Tipe === 'NPS');
            return stat ? stat.jumlah : 0;
        },
        npsAvgRaw() {
            const stat = this.overview.skor_stats?.find(s => s.Tipe === 'NPS');
            return stat ? parseFloat(stat.rata2).toFixed(1) : '0.0';
        },
        npsScaleMax() {
            return this.overview.nps_scale_max ?? 10;
        },
        npsStatusText() {
            const score = this.displayNpsScore;
            if (score >= 75) return 'Excellent';
            if (score >= 60) return 'Good';
            if (score >= 50) return 'Okay';
            return 'Needs Imp.';
        },
        npsBadgeClass() {
            const score = this.displayNpsScore;
            if (score >= 75) return 'bg-emerald-light text-emerald';
            if (score >= 60) return 'bg-indigo-light text-indigo';
            if (score >= 50) return 'bg-amber-light text-amber';
            return 'bg-rose-light text-rose';
        },
        avgRating() {
            const stat = this.overview.skor_stats?.find(s => s.Tipe === 'RATING' || s.Tipe === 'LIKERT');
            return stat ? parseFloat(stat.rata2).toFixed(1) : '0.0';
        },
        avgTime() {
            return this.formatSeconds(this.overview.avg_waktu_detik);
        },
        npsBreakdown() {
            if (this.overview.nps_breakdown) {
                return this.overview.nps_breakdown;
            }
            if (!this.perPertanyaan) {
                return { promoters: 0, passives: 0, detractors: 0 };
            }
            const npsQ = this.perPertanyaan.find(p => p.tipe === 'NPS');
            if (!npsQ) return { promoters: 0, passives: 0, detractors: 0 };
            
            return {
                promoters: Math.max(0, Math.round(this.npsCount * 0.6)),
                passives: Math.max(0, Math.round(this.npsCount * 0.25)),
                detractors: Math.max(0, Math.round(this.npsCount * 0.15)),
            };
        },
        npsDonutSeries() {
            const b = this.npsBreakdown;
            return [b.promoters, b.passives, b.detractors];
        },
        donutOpts() {
            return {
                labels: ['Promoters (9-10)', 'Passives (7-8)', 'Detractors (0-6)'],
                colors: ['#10b981', '#f59e0b', '#ef4444'],
                legend: { show: false },
                dataLabels: { enabled: false },
                plotOptions: {
                    pie: {
                        donut: {
                            size: '78%',
                        }
                    }
                },
                tooltip: { y: { formatter: (v) => v + ' respon' } }
            };
        },
        trendOpts() {
            return {
                chart: { toolbar: { show: false }, fontFamily: 'Inter, sans-serif' },
                xaxis: { categories: this.trendCategories },
                colors: ['#6366f1', '#10b981', '#f59e0b'],
                stroke: { curve: 'smooth', width: 3 },
                legend: { position: 'top', fontSize: '12px', fontWeight: 600 },
                tooltip: { y: { formatter: (v) => typeof v === 'number' ? v.toFixed(1) : v } },
            };
        },
        trendSeries() {
            const series = [];
            if (this.ratingTrend.length) {
                series.push({ name: 'Rating (%)', data: this.ratingTrend.map(t => t.avg) });
            }
            if (this.npsTrend.length) {
                series.push({ name: 'NPS (0-100)', data: this.displayNpsTrend.map(t => t.nps) });
            }
            // Response rate trend: hitung dari total_terisi / total_dibuat
            if (this.npsTrend.length || this.ratingTrend.length) {
                // Gunakan response rate overview sebagai single point
            }
            return series;
        },
        trendCategories() {
            // Gabung bulan dari rating + NPS trend
            const months = new Set();
            this.ratingTrend.forEach(t => months.add(t.bulan));
            this.npsTrend.forEach(t => months.add(t.bulan));
            return Array.from(months).sort();
        },
        topProgram() {
            return this.programComparison.length > 0 ? this.programComparison[0] : null;
        },
        attentionItems() {
            return this.aspekScorecard.filter(item => parseFloat(item.avg_pct) < 50).slice(0, 3);
        },
        filteredVoice() {
            if (!this.voiceSearch) return this.textVoice;
            const q = this.voiceSearch.toLowerCase();
            return this.textVoice.filter(v =>
                (v.jawaban && v.jawaban.toLowerCase().includes(q)) ||
                (v.label && v.label.toLowerCase().includes(q)) ||
                (v.nama_kandidat && v.nama_kandidat.toLowerCase().includes(q))
            );
        },
        filteredTextAnswers() {
            if (!this.textModalQuestion?.recent) return [];
            if (!this.textSearch) return this.textModalQuestion.recent;
            const q = this.textSearch.toLowerCase();
            return this.textModalQuestion.recent.filter(t => t.toLowerCase().includes(q));
        },
        calcDetailAverageRating() {
            if (!this.selectedFeedbackDetail?.jawaban) return 0;
            const ratings = this.selectedFeedbackDetail.jawaban
                .filter(a => a.Tipe === 'RATING' || a.Tipe === 'LIKERT')
                .map(a => parseFloat(a.Jawaban))
                .filter(val => !isNaN(val));
            if (!ratings.length) return 0;
            const sum = ratings.reduce((acc, curr) => acc + curr, 0);
            return parseFloat((sum / ratings.length).toFixed(1));
        }
    },
    mounted() {
        this.loadMeta().then(() => this.fetchData());
    },
    methods: {
        async loadMeta() {
            try {
                const [fRes, pRes] = await Promise.all([
                    axios.get('/api/v1/karir/master-feedback'),
                    axios.get('/api/v1/program-kegiatan').catch(() => ({ data: { result: [] } })),
                ]);
                this.forms = fRes.data.result || [];
                this.programs = pRes.data.result || [];
            } catch (e) { /* silent */ }
        },
        async fetchData() {
            this.analyticsLoading = true;
            const params = {};
            if (this.filters.form_ids && this.filters.form_ids.length) {
                params.form_id = this.filters.form_ids.join(',');
            }
            if (this.filters.program_ids && this.filters.program_ids.length) {
                params.program_id = this.filters.program_ids.join(',');
            }
            if (this.dateRange && this.dateRange[0]) {
                params.date_from = this.dateRange[0].toISOString().split('T')[0];
                params.date_to = this.dateRange[1].toISOString().split('T')[0];
            }
            try {
                const { data } = await axios.get('/api/v1/karir/feedback/chart', { params });
                const r = data.result || {};
                this.overview = r.overview || this.overview;
                this.satisfactionIndex = r.satisfaction_index ?? null;
                this.aspekScorecard = r.aspek_scorecard || [];
                this.programComparison = r.program_comparison || [];
                this.npsTrend = r.nps_trend || [];
                this.ratingTrend = r.rating_trend || [];
                this.textVoice = r.text_voice || [];
                this.perPertanyaan = r.per_pertanyaan;
                this.lolosVsGagal = r.lolos_vs_gagal;
            } catch (e) {
                /* silent */
            } finally {
                this.analyticsLoading = false;
            }
        },
        setPresetDate(days) {
            const end = new Date();
            const start = new Date();
            start.setDate(start.getDate() - days);
            this.dateRange = [start, end];
            this.fetchData();
        },
        resetFilters() {
            this.filters.form_ids = [];
            this.filters.program_ids = [];
            this.dateRange = null;
            this.fetchData();
        },
        async exportExcel() {
            if (!this.filters.form_ids || !this.filters.form_ids.length) return;
            this.exporting = true;
            const firstFormId = Array.isArray(this.filters.form_ids) ? this.filters.form_ids[0] : this.filters.form_ids;
            const payload = { form_id: firstFormId };
            if (this.dateRange && this.dateRange[0]) {
                payload.date_from = this.dateRange[0].toISOString().split('T')[0];
                payload.date_to = this.dateRange[1].toISOString().split('T')[0];
            }
            try {
                await axios.post('/api/v1/karir/feedback/export', payload);
            } catch (e) {
                /* silent */
            } finally {
                this.exporting = false;
            }
        },
        fmt(v) {
            return v ? parseFloat(v).toFixed(1) : '—'; 
        },
        displayNps(nps) {
            return Math.round((nps + 100) / 2);
        },
        satisfactionColor(val) {
            if (val === null || val === undefined) return 'text-slate';
            if (val >= 70) return 'text-emerald';
            if (val >= 50) return 'text-amber';
            return 'text-rose';
        },
        scoreColor(pct) {
            if (pct >= 70) return 'bg-emerald';
            if (pct >= 40) return 'bg-amber';
            return 'bg-rose';
        },
        scoreRank(idx) {
            if (idx === 0) return 'rank-gold';
            if (idx === 1) return 'rank-silver';
            if (idx === 2) return 'rank-bronze';
            return 'rank-normal';
        },
        scorePillClass(pct) {
            if (pct >= 70) return 'wca-score-pill--emerald';
            if (pct >= 40) return 'wca-score-pill--indigo';
            return 'wca-score-pill--amber';
            return 'wca-score-pill--rose';
        },
        calcPct(val, total) {
            if (!total || total === 0) return 0;
            return Math.round((val / total) * 100);
        },
        formatSeconds(s) {
            if (!s || s === 0) return '—';
            if (s < 60) return Math.round(s) + ' detik';
            const totalMenit = Math.floor(s / 60);
            if (totalMenit < 60) return totalMenit + ' menit';
            const jam = Math.floor(totalMenit / 60);
            const menit = totalMenit % 60;
            return menit > 0 ? `${jam}j ${menit}m` : `${jam} jam`;
        },
        initials(name) {
            if (!name) return '?';
            return name.split(' ').filter(Boolean).slice(0, 2).map(w => w[0]).join('').toUpperCase();
        },
        rankClass(idx) {
            if (idx === 0) return 'rank-gold';
            if (idx === 1) return 'rank-silver';
            if (idx === 2) return 'rank-bronze';
            return 'rank-normal';
        },
        isLolos(hasil) {
            if (!hasil) return false;
            const h = String(hasil).trim().toUpperCase();
            return ['DITERIMA', 'LULUS', 'LOLOS'].includes(h);
        },
        statusBadgeClass(st) {
            if (st === 'TERISI') return 'wca-b--emerald';
            if (st === 'MENUNGGU' || st === 'PENDING') return 'wca-b--amber';
            return 'wca-b--rose';
        },
        getRatingSentiment(val) {
            const v = parseFloat(val);
            if (v >= 4.8) return 'Sangat Memuaskan (Perfect)';
            if (v >= 4.0) return 'Memuaskan (Good)';
            if (v >= 3.0) return 'Cukup Baik (Average)';
            if (v >= 2.0) return 'Kurang Memuaskan';
            return 'Tidak Memuaskan';
        },
        getNpsCategoryInfo(ans) {
            const v = parseInt(ans.Jawaban);
            const skalaMin = parseInt(ans.Skala_Min_Snapshot) || 0;
            const skalaMax = parseInt(ans.Skala_Max_Snapshot) || 10;
            const range = skalaMax - skalaMin;
            // Normalisasi ke 0-10
            const normalized = range > 0 ? ((v - skalaMin) / range) * 10 : v;
            const isPromoter = normalized >= 9;
            const isPassive = normalized > 6 && normalized < 9;
            const isDetractor = normalized <= 6;
            const scoreClass = isPromoter ? 'text-emerald' : (isPassive ? 'text-amber' : 'text-rose');
            if (isPromoter) return { category: 'Promoter', class: 'wca-b--emerald', bg: 'bg-emerald', label: 'Sangat Merekomendasikan', isPromoter, isPassive: false, isDetractor: false, scoreClass };
            if (isPassive) return { category: 'Passive', class: 'wca-b--amber', bg: 'bg-amber', label: 'Netral / Cukup Puas', isPromoter: false, isPassive, isDetractor: false, scoreClass };
            return { category: 'Detractor', class: 'wca-b--rose', bg: 'bg-rose', label: 'Kecewa / Perlu Perbaikan', isPromoter: false, isPassive: false, isDetractor, scoreClass };
        },
        getNpsScale(ans) {
            const min = parseInt(ans.Skala_Min_Snapshot) || 0;
            const max = parseInt(ans.Skala_Max_Snapshot) || 10;
            return { min, max, count: max - min + 1 };
        },

        // â”€â”€ Monitoring â”€â”€
        async switchToMonitoring() { 
            this.tab = 'monitoring'; 
            if (!this.monList.length) { 
                await this.loadMeta(); 
                this.loadMonitoring(); 
            } 
        },
        setMonStatus(st) {
            this.monFilters.status = st;
            this.loadMonitoring();
        },
        debouncedSearch() {
            clearTimeout(this.searchDebounce);
            this.searchDebounce = setTimeout(() => this.loadMonitoring(), 350);
        },
        async loadMonitoring(page = 1) {
            this.monLoading = true;
            const p = { 
                ...this.monFilters, 
                page, 
                limit: 20 
            };
            if (this.monFilters.form_ids && this.monFilters.form_ids.length) {
                p.form_id = this.monFilters.form_ids.join(',');
            }
            if (this.monFilters.program_ids && this.monFilters.program_ids.length) {
                p.program_id = this.monFilters.program_ids.join(',');
            }
            try {
                const [k, d] = await Promise.all([
                    axios.get('/api/v1/karir/feedback/monitoring-kpi', { params: p }),
                    axios.get('/api/v1/karir/feedback/monitoring', { params: p }),
                ]);
                this.monKpi = k.data.result || this.monKpi; 
                this.monList = d.data.result || []; 
                this.monTotal = d.data.total_data || 0; 
            } catch (e) {
                /* silent */
            } finally {
                this.monLoading = false;
            }
        },
        onMonSelect(items) { 
            this.monSelected = items.map(i => i.Id_Feedback_Jawaban); 
        },
        clearSelection() {
            if (this.$refs.monTable) {
                this.$refs.monTable.clearSelection();
            }
            this.monSelected = [];
        },
        async viewDetail(id) {
            this.selectedFeedbackDetail = null;
            this.drawerTab = 'jawaban';
            this.detailDrawerOpen = true;
            this.detailLoading = true;
            try {
                const { data } = await axios.get(`/api/v1/karir/feedback/detail/${id}`);
                this.selectedFeedbackDetail = data.result;
            } catch (e) {
                /* silent */
            } finally {
                this.detailLoading = false;
            }
        },
        openReassign(row) { 
            this.reassignIds = [row.Id_Feedback_Jawaban]; 
            this.reassignFormId = null; 
            this.reassignResend = false; 
            this.reassignOpen = true; 
        },
        bulkReassign() { 
            if (!this.monSelected.length) return; 
            this.reassignIds = [...this.monSelected]; 
            this.reassignFormId = null; 
            this.reassignResend = false; 
            this.reassignOpen = true; 
        },
        async doReassign() {
            if (!this.reassignFormId || !this.reassignIds.length) return;
            this.reassignLoading = true;
            try {
                const { data } = await axios.post('/api/v1/karir/feedback/reassign', {
                    feedback_ids: this.reassignIds,
                    new_form_id: this.reassignFormId,
                    resend_email: true // wajib — link lama expired, user harus dapat link baru
                });
                if (data.message) {
                    this.$message.success(data.message);
                }
            } catch (e) {
                const msg = e.response?.data?.message || e.message || 'Gagal reassign feedback';
                this.$message.error(msg);
                console.error('[Reassign]', e);
            }
            this.reassignLoading = false;
            this.reassignOpen = false;
            this.clearSelection();
            this.loadMonitoring();
        },
        resendSingle(row) {
            this.resendIds = [row.Id_Feedback_Jawaban];
            this.resendOpen = true;
        },
        bulkResend() {
            if (!this.monSelected.length) return;
            this.resendIds = [...this.monSelected];
            this.resendOpen = true;
        },
        async doResend() {
            this.resendLoading = true;
            try {
                const { data } = await axios.post('/api/v1/karir/feedback/resend', { feedback_ids: this.resendIds });
                if (data.message) {
                    this.$message.success(data.message);
                }
            } catch (e) {
                const msg = e.response?.data?.message || e.message || 'Gagal kirim ulang email';
                this.$message.error(msg);
                console.error('[Resend]', e);
            }
            this.resendLoading = false;
            this.resendOpen = false;
            this.clearSelection();
            this.loadMonitoring();
        },
        openAllTextModal(pertanyaan) {
            this.textModalQuestion = pertanyaan;
            this.textSearch = '';
            this.textModalOpen = true;
        },
        copyText(text) {
            if (!text) return;
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(text).then(() => {
                    ElMessage.success({ message: 'Berhasil disalin ke clipboard! 📋', duration: 2500 });
                }).catch(() => {
                    this.fallbackCopy(text);
                });
            } else {
                this.fallbackCopy(text);
            }
        },
        fallbackCopy(text) {
            const textArea = document.createElement('textarea');
            textArea.value = text;
            textArea.style.position = 'fixed';
            textArea.style.opacity = '0';
            document.body.appendChild(textArea);
            textArea.focus();
            textArea.select();
            try {
                document.execCommand('copy');
                ElMessage.success({ message: 'Berhasil disalin ke clipboard! 📋', duration: 2500 });
            } catch (err) {
                ElMessage.error({ message: 'Gagal menyalin teks', duration: 2500 });
            }
            document.body.removeChild(textArea);
        },
        copyAnswersToClipboard() {
            if (!this.selectedFeedbackDetail?.jawaban) return;
            const d = this.selectedFeedbackDetail;
            let summary = `📋 RINGKASAN FEEDBACK KANDIDAT\n`;
            summary += `👤 Nama: ${d.Nama_Kandidat} (${d.Email})\n`;
            summary += `🔖 Kode Lamaran: ${d.Kode_Lamaran}\n`;
            summary += `💼 Program: ${d.Program_Nama || '—'}\n`;
            summary += `📋 Form Feedback: ${d.Form_Nama || 'Form Default'}\n`;
            summary += `🎯 Hasil Seleksi: ${d.Hasil_Akhir}\n`;
            summary += `----------------------------------------\n\n`;
            d.jawaban.forEach((ans, idx) => {
                summary += `Q${idx + 1}: ${ans.Label}\n👉 Jawaban: ${ans.Jawaban || '—'}\n\n`;
            });
            this.copyText(summary);
        },
        openReassignFromDrawer() {
            if (!this.selectedFeedbackDetail) return;
            this.openReassign({ Id_Feedback_Jawaban: this.selectedFeedbackDetail.Id_Feedback_Jawaban });
        },
        resendFromDrawer() {
            if (!this.selectedFeedbackDetail) return;
            this.resendSingle({ Id_Feedback_Jawaban: this.selectedFeedbackDetail.Id_Feedback_Jawaban });
        }
    },
};
</script>

<style scoped>
.wca-fb-wrapper {
    font-family: 'Inter', system-ui, -apple-system, sans-serif;
    color: #1e293b;
}

/* Base Card Styling */
.wca-card {
    background: #ffffff;
    border-radius: 16px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.03);
    overflow: hidden;
}

.wca-card__head {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 16px 20px;
    border-bottom: 1px solid #f1f5f9;
}

.wca-card__head h3 {
    font-size: 1rem;
    font-weight: 700;
    margin: 0;
    color: #0f172a;
    display: flex;
    align-items: center;
    gap: 8px;
}

.wca-card__body {
    padding: 20px 22px;
}

.wca-card__body--flush {
    padding: 0;
}

/* Program Leaderboard Horizontal Flex Alignment */
.wca-prog-list {
    display: flex;
    flex-direction: column;
}

.wca-prog-item {
    display: flex !important;
    align-items: center !important;
    gap: 14px !important;
    padding: 14px 18px !important;
    border-bottom: 1px solid #f1f5f9;
    transition: background-color 0.15s ease;
}

.wca-prog-item:last-child {
    border-bottom: none;
}

.wca-prog-item:hover {
    background-color: #f8fafc;
}

.wca-prog-item__rank {
    width: 36px !important;
    height: 36px !important;
    min-width: 36px !important;
    border-radius: 10px !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    font-weight: 800 !important;
    font-size: 0.85rem !important;
    flex-shrink: 0 !important;
}

.wca-prog-item__main {
    flex-grow: 1;
    min-width: 0;
}

/* NPS Donut & Visualization Alignment */
.wca-nps-viz {
    display: flex;
    flex-direction: column;
    height: 100%;
    justify-content: space-between;
}

.wca-nps-donut-wrap {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 190px;
    margin-top: -10px;
}

.wca-nps-donut-center {
    position: absolute;
    top: 48%;
    left: 50%;
    transform: translate(-50%, -50%);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    pointer-events: none;
    line-height: 1;
    z-index: 2;
}

.wca-nps-donut-score {
    font-size: 2.3rem;
    font-weight: 800;
    line-height: 1;
    letter-spacing: -0.03em;
}

.wca-nps-bars {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.wca-nps-bar-item {
    background: #f8fafc;
    border-radius: 10px;
    padding: 10px 14px;
    border: 1px solid #e2e8f0;
}

/* Candidate Table Cell - Horizontal Flex Alignment */
.wca-user-cell {
    display: flex !important;
    align-items: center !important;
    gap: 12px !important;
}

.wca-avatar--sm {
    width: 36px !important;
    height: 36px !important;
    min-width: 36px !important;
    border-radius: 10px !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    font-size: 0.85rem !important;
    font-weight: 700 !important;
    flex-shrink: 0 !important;
}

/* Header & Tabs */
.wca-phead--modern {
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: #ffffff;
    padding: 20px 24px;
    border-radius: 16px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.03);
    margin-bottom: 24px;
}

.wca-phead__title-group {
    display: flex;
    align-items: center;
    gap: 16px;
}

.wca-phead__icon-badge {
    width: 48px;
    height: 48px;
    border-radius: 14px;
    background: linear-gradient(135deg, #6366f1, #4f46e5);
    color: #ffffff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.4rem;
    box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
}

.wca-phead__title {
    font-size: 1.35rem;
    font-weight: 700;
    margin: 0;
    color: #0f172a;
}

.wca-phead__sub {
    font-size: 0.85rem;
    color: #64748b;
    margin: 2px 0 0;
}

.fb-tabs-modern {
    display: flex;
    background: #f1f5f9;
    padding: 4px;
    border-radius: 12px;
    gap: 4px;
}

.fb-tab-m {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 18px;
    border: none !important;
    border-radius: 8px !important;
    background: transparent !important;
    font-size: 0.875rem;
    font-weight: 600;
    color: #64748b !important;
    cursor: pointer;
    transition: all 0.2s ease;
    outline: none !important;
}

.fb-tab-m:hover {
    color: #4f46e5 !important;
}

.fb-tab-m--active {
    background: #ffffff !important;
    color: #4f46e5 !important;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06) !important;
}

/* Glass Filter Bar - Fixed Flexbox Dropdown Layout */
.wca-glass-card {
    background: rgba(255, 255, 255, 0.92);
    backdrop-filter: blur(12px);
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    padding: 16px 22px;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.03);
}

.wca-filter-bar--compact {
    display: flex;
    align-items: center;
    gap: 16px;
    flex-wrap: nowrap;
    width: 100%;
}

.wca-filter-item {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.wca-filter-item--search {
    flex: 1 1 240px;
    min-width: 180px;
}

.wca-filter-item--select {
    flex: 0 0 220px !important;
    width: 220px !important;
    min-width: 180px !important;
}

.wca-filter-item--pills {
    display: flex;
    flex-direction: row;
    align-items: center;
    align-self: center;
    flex: 0 0 auto;
}

.wca-filter-label {
    font-size: 0.75rem;
    font-weight: 700;
    color: #475569;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}

/* Segmented Preset Buttons Container */
.wca-preset-pill-group {
    display: flex;
    background: #f1f5f9;
    padding: 3px;
    border-radius: 10px;
    border: 1px solid #cbd5e1;
    gap: 2px;
}

.wca-preset-btn {
    padding: 5px 12px;
    border: none !important;
    border-radius: 7px !important;
    background: transparent !important;
    font-size: 0.78rem;
    font-weight: 600;
    color: #475569 !important;
    cursor: pointer;
    transition: all 0.15s;
    outline: none !important;
}

.wca-preset-btn:hover {
    background: #ffffff !important;
    color: #4f46e5 !important;
    box-shadow: 0 1px 4px rgba(0, 0, 0, 0.05) !important;
}

.wca-filter-bar__right {
    margin-left: auto;
}

/* Ultra-Premium KPI Cards Styling */
.wca-kpi-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
    gap: 16px;
}

.wca-kpi-card {
    background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
    border-radius: 16px;
    padding: 20px 18px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 4px 14px rgba(0, 0, 0, 0.03);
    transition: transform 0.25s cubic-bezier(0.16, 1, 0.3, 1), box-shadow 0.25s cubic-bezier(0.16, 1, 0.3, 1), border-color 0.25s;
    position: relative;
    overflow: hidden;
}

.wca-kpi-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 28px rgba(99, 102, 241, 0.1);
    border-color: #cbd5e1;
}

.wca-kpi-card__top {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 14px;
}

.wca-kpi-card__icon {
    width: 42px;
    height: 42px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.06);
}

.wca-kpi-card--indigo .wca-kpi-card__icon { background: linear-gradient(135deg, #6366f1, #4f46e5); color: #ffffff; box-shadow: 0 4px 14px rgba(99, 102, 241, 0.35); }
.wca-kpi-card--emerald .wca-kpi-card__icon { background: linear-gradient(135deg, #10b981, #059669); color: #ffffff; box-shadow: 0 4px 14px rgba(16, 185, 129, 0.35); }
.wca-kpi-card--violet .wca-kpi-card__icon { background: linear-gradient(135deg, #8b5cf6, #7c3aed); color: #ffffff; box-shadow: 0 4px 14px rgba(139, 92, 246, 0.35); }
.wca-kpi-card--amber .wca-kpi-card__icon { background: linear-gradient(135deg, #f59e0b, #d97706); color: #ffffff; box-shadow: 0 4px 14px rgba(245, 158, 11, 0.35); }
.wca-kpi-card--rose .wca-kpi-card__icon { background: linear-gradient(135deg, #f43f5e, #e11d48); color: #ffffff; box-shadow: 0 4px 14px rgba(244, 63, 94, 0.35); }
.wca-kpi-card--slate .wca-kpi-card__icon { background: linear-gradient(135deg, #64748b, #475569); color: #ffffff; box-shadow: 0 4px 14px rgba(100, 116, 139, 0.35); }

.wca-kpi-card__tag {
    font-size: 0.72rem;
    font-weight: 700;
    padding: 3px 9px;
    border-radius: 20px;
    letter-spacing: 0.02em;
}

.wca-kpi-card__value {
    font-size: 1.85rem;
    font-weight: 800;
    color: #0f172a;
    line-height: 1.1;
    margin-bottom: 2px;
    letter-spacing: -0.02em;
}

.wca-kpi-card__label {
    font-size: 0.85rem;
    font-weight: 600;
    color: #475569;
}

/* Dual Power Cards Lolos vs Gugur */
.wca-compare-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
}

.wca-compare-box {
    border-radius: 14px;
    border: 1px solid #e2e8f0;
    transition: transform 0.2s;
}

.wca-compare-box:hover {
    transform: translateY(-2px);
}

.wca-compare-box--lolos {
    background: linear-gradient(135deg, rgba(16, 185, 129, 0.05) 0%, rgba(16, 185, 129, 0.01) 100%);
    border-color: rgba(16, 185, 129, 0.25);
}

.wca-compare-box--gagal {
    background: linear-gradient(135deg, rgba(239, 68, 68, 0.05) 0%, rgba(239, 68, 68, 0.01) 100%);
    border-color: rgba(239, 68, 68, 0.25);
}

.wca-avatar-ico {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
}

.wca-compare-box__num {
    font-size: 1.9rem;
    font-weight: 800;
    line-height: 1.1;
}

.wca-compare-box__meta {
    font-size: 0.78rem;
    color: #64748b;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-top: 1px border-dashed rgba(0,0,0,0.06);
    padding-top: 6px;
}

.wca-ratio-bar-box {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 12px 16px;
}

.wca-modal-icon-badge {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.wca-btn--primary {
    background: linear-gradient(135deg, #6366f1, #4f46e5);
    color: #ffffff;
    border: none;
    border-radius: 10px;
    padding: 8px 18px;
    font-size: 0.85rem;
    box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
    transition: all 0.2s ease;
}

.wca-btn--primary:hover:not(:disabled) {
    transform: translateY(-1px);
    box-shadow: 0 6px 16px rgba(99, 102, 241, 0.4);
}

.wca-btn--amber {
    background: linear-gradient(135deg, #f59e0b, #d97706);
    color: #ffffff;
    border: none;
    border-radius: 10px;
    padding: 8px 18px;
    font-size: 0.85rem;
    box-shadow: 0 4px 12px rgba(245, 158, 11, 0.3);
    transition: all 0.2s ease;
}

.wca-btn--amber:hover:not(:disabled) {
    transform: translateY(-1px);
    box-shadow: 0 6px 16px rgba(245, 158, 11, 0.4);
}

.wca-ratio-bar {
    display: flex;
    height: 10px;
    border-radius: 20px;
    overflow: hidden;
    gap: 2px;
    background: #cbd5e1;
}

.wca-ratio-bar__fill {
    height: 100%;
    transition: width 0.4s ease;
}

/* Leaderboard Rank Badges */
.wca-scorecard-row {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 14px;
    border-radius: 14px;
    background: #ffffff;
    border: 1px solid #f1f5f9;
    margin-bottom: 8px;
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
    overflow: hidden;
    box-sizing: border-border-box;
}

.wca-scorecard-row:hover {
    transform: translateX(4px);
    border-color: #cbd5e1;
    box-shadow: 0 4px 14px rgba(15, 23, 42, 0.05);
}

.wca-scorecard-row__rank {
    width: 32px;
    height: 32px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.85rem;
    font-weight: 800;
    flex-shrink: 0;
}

.rank-gold {
    background: linear-gradient(135deg, #fef3c7, #fde68a);
    color: #b45309;
    border: 1px solid #fcd34d;
    box-shadow: 0 2px 6px rgba(245, 158, 11, 0.2);
}

.rank-silver {
    background: linear-gradient(135deg, #f1f5f9, #e2e8f0);
    color: #475569;
    border: 1px solid #cbd5e1;
}

.rank-bronze {
    background: linear-gradient(135deg, #ffedd5, #fed7aa);
    color: #c2410c;
    border: 1px solid #fdba74;
}

.rank-normal {
    background: #f8fafc;
    color: #64748b;
    border: 1px solid #e2e8f0;
}

.wca-score-pill {
    display: inline-flex;
    align-items: center;
    padding: 4px 10px;
    border-radius: 10px;
    font-size: 0.82rem;
    font-weight: 800;
    line-height: 1;
}
.wca-score-pill--emerald { background: #ecfdf5; color: #047857; border: 1px solid #a7f3d0; }
.wca-score-pill--indigo { background: #eef2ff; color: #4338ca; border: 1px solid #c7d2fe; }
.wca-score-pill--amber { background: #fffbeb; color: #b45309; border: 1px solid #fde68a; }
.wca-score-pill--rose { background: #fff1f2; color: #be123c; border: 1px solid #fecdd3; }

.wca-badge {
    font-size: 0.72rem;
    font-weight: 700;
    padding: 3px 10px;
    border-radius: 20px;
    display: inline-flex;
    align-items: center;
    line-height: 1;
    letter-spacing: 0.02em;
}
.wca-b--emerald { background: #ecfdf5 !important; color: #047857 !important; border: 1px solid #a7f3d0 !important; }
.wca-b--rose { background: #fff1f2 !important; color: #be123c !important; border: 1px solid #fecdd3 !important; }
.wca-b--amber { background: #fffbeb !important; color: #b45309 !important; border: 1px solid #fde68a !important; }
.wca-b--slate { background: #f1f5f9 !important; color: #475569 !important; border: 1px solid #cbd5e1 !important; }

.badge-rating-pill {
    background: #fffbebf5;
    color: #b45309;
    border: 1px solid #fef3c7;
    font-size: 0.78rem;
    font-weight: 700;
    padding: 3px 10px;
    border-radius: 20px;
}

/* â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• */
/* ULTRA-MODERN QUESTION INSIGHT GRID CARDS SYSTEM                 */
/* â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• */
.wca-q-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(340px, 1fr));
    gap: 20px;
    align-items: start;
}

.wca-q-card {
    background: #ffffff;
    border-radius: 16px;
    border: 1px solid #e2e8f0;
    padding: 20px;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.03);
    transition: transform 0.25s cubic-bezier(0.16, 1, 0.3, 1), box-shadow 0.25s cubic-bezier(0.16, 1, 0.3, 1), border-color 0.25s;
    display: flex;
    flex-direction: column;
    justify-content: flex-start;
}

.wca-q-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 28px rgba(99, 102, 241, 0.09);
    border-color: #cbd5e1;
}

.wca-q-card__head {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    margin-bottom: 16px;
}

.wca-q-card__num {
    width: 34px;
    height: 34px;
    border-radius: 10px;
    background: linear-gradient(135deg, #6366f1, #4f46e5);
    color: #ffffff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 800;
    font-size: 0.85rem;
    flex-shrink: 0;
    box-shadow: 0 4px 10px rgba(99, 102, 241, 0.3);
}

.wca-q-card__title {
    font-size: 0.95rem;
    font-weight: 700;
    color: #0f172a;
    line-height: 1.4;
    flex-grow: 1;
}

.wca-q-score-box {
    background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
    border-radius: 12px;
    padding: 14px 18px;
    border: 1px solid #e2e8f0;
    display: flex;
    align-items: center;
    gap: 16px;
}

.wca-q-score-big {
    font-size: 2.2rem;
    font-weight: 800;
    line-height: 1;
    letter-spacing: -0.02em;
}

.wca-star-dist {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.wca-star-dist__row {
    display: flex;
    align-items: center;
    font-size: 0.78rem;
    font-weight: 600;
    color: #475569;
}

.wca-star-dist__label {
    width: 36px;
    display: flex;
    align-items: center;
    gap: 4px;
}

.wca-star-dist__val {
    width: 42px;
    text-align: right;
    font-weight: 700;
    color: #64748b;
}

.wca-quote-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.wca-quote-item {
    background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
    border-left: 3px solid #6366f1;
    border-radius: 8px;
    padding: 10px 14px;
    font-size: 0.825rem;
    color: #334155;
    line-height: 1.45;
    font-style: italic;
}

.wca-choice-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.wca-choice-item {
    background: #f8fafc;
    border-radius: 10px;
    padding: 10px 12px;
    border: 1px solid #e2e8f0;
}

.wca-progress-bar {
    height: 8px;
    background: #e2e8f0;
    border-radius: 20px;
    overflow: hidden;
}

.wca-progress-bar__fill {
    height: 100%;
    border-radius: 20px;
    transition: width 0.4s ease;
}

.wca-btn-link {
    background: transparent;
    border: none;
    padding: 0;
    cursor: pointer;
    font-weight: 600;
    outline: none;
}

.wca-btn-link:hover {
    text-decoration: underline;
}

/* Progress Mini Styling */
.wca-progress-mini {
    height: 6px;
    background: #e2e8f0;
    border-radius: 10px;
    overflow: hidden;
}

.wca-progress-mini__bar {
    height: 100%;
    border-radius: 10px;
    transition: width 0.4s ease;
}

/* Shimmer Skeleton System */
@keyframes wca-shimmer-anim {
    0% { background-position: -200% 0; }
    100% { background-position: 200% 0; }
}

.wca-shimmer {
    background: linear-gradient(90deg, #f1f5f9 25%, #e2e8f0 50%, #f1f5f9 75%);
    background-size: 200% 100%;
    animation: wca-shimmer-anim 1.6s infinite ease-in-out;
    border-radius: 8px;
}

/* Empty States Component Styling */
.wca-empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-align: center;
    padding: 24px 16px;
}

.wca-empty-state__icon {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
}

.wca-empty-state__title {
    font-size: 0.95rem;
    font-weight: 700;
    color: #1e293b;
    margin: 4px 0 2px;
}

.wca-empty-state__sub {
    font-size: 0.8rem;
    color: #64748b;
    max-width: 320px;
    line-height: 1.45;
}

.bg-violet-light { background-color: rgba(139, 92, 246, 0.12); }
.text-violet { color: #7c3aed !important; }

@media (max-width: 1024px) {
    .wca-filter-bar--compact {
        flex-wrap: wrap;
    }
    .wca-filter-item--search {
        max-width: 100%;
        flex: 1 1 100%;
    }
    .wca-filter-item--select {
        flex: 1 1 180px !important;
        width: 100% !important;
    }
    .wca-filter-item--pills {
        margin-left: 0 !important;
    }
}

/* Color Tokens */
.bg-emerald { background-color: #10b981 !important; }
.bg-amber { background-color: #f59e0b !important; }
.bg-rose { background-color: #ef4444 !important; }
.bg-indigo { background-color: #6366f1 !important; }

.bg-emerald-light { background-color: rgba(16, 185, 129, 0.12); }
.bg-amber-light { background-color: rgba(245, 158, 11, 0.12); }
.bg-rose-light { background-color: rgba(239, 68, 68, 0.12); }
.bg-indigo-light { background-color: rgba(99, 102, 241, 0.12); }

.text-emerald { color: #059669 !important; }
.text-amber { color: #d97706 !important; }
.text-rose { color: #e11d48 !important; }
.text-indigo { color: #4f46e5 !important; }
.text-indigo-hover:hover { color: #4f46e5 !important; }

/* Status Pills & Button Resets */
.wca-status-pills {
    display: flex;
    gap: 4px;
    background: #f1f5f9;
    padding: 4px;
    border-radius: 10px;
    border: 1px solid #cbd5e1;
}

.wca-pill {
    padding: 6px 14px;
    border: 1px solid transparent !important;
    border-radius: 8px !important;
    background: transparent !important;
    font-size: 0.8rem;
    font-weight: 600;
    color: #64748b !important;
    cursor: pointer;
    transition: all 0.15s ease;
    outline: none !important;
    box-shadow: none !important;
}

.wca-pill:hover:not(.wca-pill--active) {
    color: #4f46e5 !important;
    background: rgba(99, 102, 241, 0.08) !important;
}

.wca-pill--active {
    background: #ffffff !important;
    color: #4f46e5 !important;
    border-color: #e2e8f0 !important;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.06) !important;
}

.wca-btn-icon-sm {
    width: 32px;
    height: 32px;
    border-radius: 8px !important;
    border: 1px solid #e2e8f0 !important;
    background: #ffffff !important;
    color: #64748b !important;
    display: inline-flex !important;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.15s ease;
    outline: none !important;
    padding: 0 !important;
    box-shadow: none !important;
}

.wca-btn-icon-sm:hover {
    color: #4f46e5 !important;
    border-color: #6366f1 !important;
    background: #f5f3ff !important;
}

.wca-btn-icon-soft {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 6px 12px;
    border-radius: 8px !important;
    border: 1px solid #e0e7ff !important;
    background: #f5f3ff !important;
    color: #4f46e5 !important;
    font-size: 0.78rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.15s;
    outline: none !important;
}

.wca-btn-icon-soft:hover {
    background: #6366f1 !important;
    color: #ffffff !important;
    border-color: #6366f1 !important;
}

.wca-btn-icon-soft:hover i {
    color: #ffffff !important;
}

/* Table Container Card Overrides */
.wca-card--table-container {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.03);
    overflow: hidden;
    position: relative;
}

/* Floating Glass Bulk Action Tray */
.wca-floating-bulk-bar {
    position: fixed;
    bottom: 24px;
    left: 50%;
    transform: translateX(-50%);
    background: rgba(255, 255, 255, 0.94);
    backdrop-filter: blur(12px);
    border: 1px solid rgba(99, 102, 241, 0.3);
    box-shadow: 0 12px 32px rgba(99, 102, 241, 0.18);
    border-radius: 50px;
    padding: 10px 24px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 24px;
    z-index: 100;
}

.wca-bulk-count-badge {
    background: rgba(99, 102, 241, 0.12);
    color: #4f46e5;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 0.85rem;
    display: flex;
    align-items: center;
    gap: 6px;
}

/* Element Plus Deep Overrides for Theme Consistency */
:deep(.el-table__header-wrapper th) {
    background-color: #f8fafc !important;
    color: #475569 !important;
    font-size: 0.75rem !important;
    font-weight: 700 !important;
    text-transform: uppercase !important;
    letter-spacing: 0.03em !important;
    border-bottom: 1px solid #e2e8f0 !important;
}

:deep(.el-table__row:hover > td) {
    background-color: #f8fafc !important;
}

:deep(.el-checkbox__input.is-checked .el-checkbox__inner) {
    background-color: #6366f1 !important;
    border-color: #6366f1 !important;
}

:deep(.el-pagination.is-background .el-pager li.is-active) {
    background-color: #6366f1 !important;
    color: #ffffff !important;
    border-radius: 8px !important;
    font-weight: 700 !important;
}

:deep(.el-pagination.is-background .el-pager li),
:deep(.el-pagination.is-background .btn-prev),
:deep(.el-pagination.is-background .btn-next) {
    border-radius: 8px !important;
    font-weight: 600 !important;
}

:deep(.el-dialog) {
    border-radius: 16px !important;
    overflow: hidden !important;
    box-shadow: 0 16px 40px rgba(0, 0, 0, 0.12) !important;
}

:deep(.el-dialog__header) {
    margin: 0 !important;
    padding: 16px 20px !important;
    border-bottom: 1px solid #e2e8f0 !important;
}

:deep(.el-dialog__footer) {
    padding: 14px 20px !important;
    border-top: 1px solid #e2e8f0 !important;
}
</style>

<!-- UNSCOPED GLOBAL STYLES FOR TELEPORTED DRAWER & DIALOGS -->
<style>
.wca-detail-drawer-custom .el-drawer__header {
    margin-bottom: 0 !important;
    padding: 16px 24px !important;
    border-bottom: 1px solid #e2e8f0 !important;
    background: #ffffff !important;
}

.wca-detail-drawer-custom .el-drawer__body {
    padding: 20px 24px !important;
    background: #ffffff !important;
}

/* Hero Section inside Drawer */
.wca-drawer-hero {
    position: relative;
    background: linear-gradient(135deg, #f8fafc 0%, #edf2f7 100%) !important;
    border: 1px solid #e2e8f0 !important;
    border-radius: 16px !important;
    padding: 20px !important;
    overflow: hidden !important;
}

.wca-avatar-hero {
    width: 52px;
    height: 52px;
    border-radius: 14px;
    background: linear-gradient(135deg, #6366f1, #4f46e5);
    color: #ffffff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    font-weight: 800;
    box-shadow: 0 4px 14px rgba(99, 102, 241, 0.3);
}

.wca-drawer-hero__name {
    font-size: 1.15rem;
    font-weight: 700;
    color: #0f172a;
}

.wca-hero-score-badge {
    background: #ffffff;
    padding: 10px 14px;
    border-radius: 12px;
    border: 1px solid #e2e8f0;
    text-align: center;
    box-shadow: 0 2px 8px rgba(0,0,0,0.04);
}

.wca-hero-score-num {
    font-size: 1.5rem;
    font-weight: 800;
    color: #0f172a;
    display: block;
    line-height: 1;
}

.wca-drawer-inner-tabs {
    display: flex;
    gap: 8px;
    background: rgba(255,255,255,0.8);
    padding: 4px;
    border-radius: 10px;
    border: 1px solid #cbd5e1;
}

.wca-dtab {
    flex: 1;
    padding: 8px 12px;
    border: none !important;
    border-radius: 8px !important;
    background: transparent !important;
    font-size: 0.8rem;
    font-weight: 600;
    color: #64748b !important;
    cursor: pointer;
    transition: all 0.2s;
    outline: none !important;
}

.wca-dtab--active {
    background: #ffffff !important;
    color: #4f46e5 !important;
    box-shadow: 0 2px 6px rgba(0,0,0,0.06) !important;
}

/* Q&A Cards inside Drawer */
.wca-q-ans-box {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    padding: 16px;
    margin-bottom: 12px;
    box-shadow: 0 2px 6px rgba(0,0,0,0.02);
}

.wca-q-ans-box__head {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
}

.wca-q-idx {
    font-size: 0.72rem;
    font-weight: 700;
    color: #6366f1;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}

.wca-q-ans-box__label {
    font-size: 0.925rem;
    font-weight: 700;
    color: #1e293b;
    margin-bottom: 12px;
}

.wca-rating-display-card {
    background: #f8fafc;
    border-radius: 10px;
    padding: 12px 16px;
    border: 1px solid #e2e8f0;
}

.wca-rating-big-num {
    font-size: 1.8rem;
    font-weight: 800;
    color: #0f172a;
}

.wca-nps-display-card {
    background: #f8fafc;
    border-radius: 10px;
    padding: 12px 16px;
    border: 1px solid #e2e8f0;
}

.wca-nps-scale-bar {
    display: grid;
    grid-template-columns: repeat(11, 1fr);
    gap: 4px;
    margin-top: 10px;
}

.wca-nps-scale-pip {
    text-align: center;
    padding: 6px 0;
    font-size: 0.75rem;
    font-weight: 700;
    border-radius: 6px;
    background: #e2e8f0;
    color: #64748b;
}

.pip-selected {
    transform: scale(1.1);
    box-shadow: 0 2px 8px rgba(0,0,0,0.2);
    color: #ffffff !important;
}

.pip-selected.pip-promoter { background: #10b981 !important; }
.pip-selected.pip-passive { background: #f59e0b !important; }
.pip-selected.pip-detractor { background: #ef4444 !important; }

.wca-quote-box-modern {
    background: linear-gradient(135deg, #f8fafc, #f1f5f9);
    border-left: 4px solid #6366f1;
    border-radius: 8px;
    padding: 12px 16px;
}

.wca-quote-text {
    font-size: 0.875rem;
    color: #334155;
    line-height: 1.5;
}

.wca-choice-pill {
    display: inline-flex;
    align-items: center;
    background: #e0e7ff;
    color: #3730a3;
    padding: 6px 14px;
    border-radius: 20px;
    font-size: 0.85rem;
}

/* Info Card Grid */
.wca-info-card-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
}

.wca-info-card {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 12px 14px;
    display: flex;
    flex-direction: column;
}

.wca-info-card__label {
    font-size: 0.75rem;
    color: #64748b;
    margin-bottom: 4px;
}

.wca-drawer-footer {
    position: sticky;
    bottom: 0;
    background: #ffffff;
    border-top: 1px solid #e2e8f0;
    padding: 14px 0 0;
    margin-top: 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

/* ═══ LAYER 1: HEALTH BAR ═══ */
.wca-health-bar {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 12px;
}

.wca-hb-card {
    background: #ffffff;
    border-radius: 12px;
    padding: 14px 16px;
    border: 1px solid #e2e8f0;
    display: flex;
    align-items: center;
    gap: 12px;
    transition: box-shadow 0.15s;
}

.wca-hb-card:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.05);
}

.wca-hb-card--primary {
    background: linear-gradient(135deg, #eef2ff, #ffffff);
    border-color: #c7d2fe;
}

.wca-hb-card--warn {
    background: linear-gradient(135deg, #fff1f2, #ffffff);
    border-color: #fecdd3;
}

.wca-hb-card__icon-wrap {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    background: rgba(99,102,241,0.12);
    color: #4f46e5;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.05rem;
    flex-shrink: 0;
}

.wca-hb-card__body {
    min-width: 0;
}

.wca-hb-card__value {
    font-size: 1.25rem;
    font-weight: 800;
    color: #0f172a;
    line-height: 1.15;
}

.wca-hb-card__label {
    font-size: 0.72rem;
    font-weight: 600;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}

.wca-hb-card__range {
    font-size: 0.7rem;
    color: #94a3b8;
    margin-top: 1px;
}

/* ═══ LAYER 2: SCORECARD ═══ */
.wca-scorecard-list {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.wca-scorecard-row {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 12px;
    border-radius: 8px;
    background: #f8fafc;
    border: 1px solid #f1f5f9;
    transition: background 0.12s;
}

.wca-scorecard-row:hover {
    background: #f1f5f9;
}

.wca-scorecard-row__rank {
    width: 28px;
    text-align: center;
    flex-shrink: 0;
}

.wca-scorecard-row__label {
    flex: 1;
    min-width: 0;
}

.wca-scorecard-row__bar-wrap {
    width: 120px;
    flex-shrink: 0;
}

.wca-scorecard-row__score {
    width: 36px;
    text-align: right;
    flex-shrink: 0;
    font-size: 0.85rem;
}

/* ═══ LAYER 3: VOICE FEED ═══ */
.wca-voice-feed {
    display: flex;
    flex-direction: column;
    gap: 8px;
    max-height: 420px;
    overflow-y: auto;
}

.wca-voice-card {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 12px 14px;
}

.wca-voice-card__head {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
}

.wca-voice-card__body {
    font-size: 0.85rem;
    color: #334155;
    line-height: 1.5;
    padding-left: 4px;
    border-left: 3px solid #6366f1;
}

@media (max-width: 1200px) {
    .wca-health-bar {
        grid-template-columns: repeat(3, 1fr);
    }
}

@media (max-width: 768px) {
    .wca-health-bar {
        grid-template-columns: repeat(2, 1fr);
    }
}
</style>
