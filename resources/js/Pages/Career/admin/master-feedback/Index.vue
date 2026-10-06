<!-- WEB CAREER — Master Feedback Form (induk-detail: Form + Pertanyaan). DATA dari DB via /api/v1/karir/master-feedback. -->
<template>
    <Head title="Master Feedback Form" />
    <div class="wca">
        <div class="pkg-head">
            <div class="pkg-head__l">
                <div class="pkg-head__title">
                    <span class="pkg-head__ico"><i class="bi bi-chat-dots"></i></span>
                    <h1>Master Feedback Form</h1>
                </div>
                <p>Buat template formulir feedback — atur pertanyaan dengan berbagai tipe (rating, NPS, Likert, teks, pilihan ganda).</p>
            </div>
            <button class="pkg-newbtn" @click="openCreate"><i class="bi bi-plus-lg"></i> Form Baru</button>
        </div>

        <div v-loading="loading" class="pkg-list">
            <div v-for="f in list" :key="f.Id_Master_Feedback_Form" class="pkg-card" :class="{ open: open === f.Id_Master_Feedback_Form }">
                <!-- Header row -->
                <div class="pkg-row">
                    <button type="button" class="pkg-chev" :class="{ open: open === f.Id_Master_Feedback_Form }" title="Buka detail" @click="toggleExpand(f)">
                        <i class="bi bi-chevron-right"></i>
                    </button>
                    <div class="pkg-row__main">
                        <button type="button" class="pkg-row__titlebtn" @click="toggleExpand(f)">
                            <span class="pkg-row__title">{{ f.Nama }}</span>
                        </button>
                        <div class="pkg-row__meta">
                            <span class="pkg-code">{{ f.Mode_Tampilan === 'WIZARD' ? 'Step-by-Step' : 'Single Page' }}</span>
                            <template v-if="f.Deskripsi"><span class="pkg-sep"></span><span class="pkg-mi">{{ f.Deskripsi }}</span></template>
                        </div>
                        <div class="pkg-pills">
                            <span class="pkg-pill pkg-pill--violet"><i class="bi bi-question-circle"></i> {{ f.Jumlah_Pertanyaan ?? '?' }} pertanyaan</span>
                            <span v-if="f.Durasi_Hari" class="pkg-pill pkg-pill--struct"><i class="bi bi-hourglass-split"></i> {{ f.Durasi_Hari }} hari</span>
                            <span v-else class="pkg-pill pkg-pill--struct"><i class="bi bi-infinity"></i> Unlimited</span>
                            <span class="pkg-pill" :class="f.Flag_Aktif === 'Y' ? 'pkg-pill--green' : 'pkg-pill--slate'">
                                <span class="pkg-pill__dot"></span> {{ f.Flag_Aktif === 'Y' ? 'Aktif' : 'Nonaktif' }}
                            </span>
                            <!-- [feat/feedback] Assignment badge -->
                            <span v-if="f.Assignment_General" class="pkg-pill pkg-pill--violet"><i class="bi bi-globe2"></i> General</span>
                            <span v-else-if="f.Assignment_Specific_Count" class="pkg-pill pkg-pill--struct"><i class="bi bi-bullseye"></i> {{ f.Assignment_Specific_Count }} Program</span>
                        </div>
                    </div>
                    <div class="pkg-row__act" @click.stop>
                        <el-switch :model-value="f.Flag_Aktif === 'Y'" @change="(v) => setStatus(f, v)" />
                        <button class="pkg-ibtn" title="Ubah" @click="openEdit(f)"><i class="bi bi-pencil"></i></button>
                        <button class="pkg-ibtn" title="Assign ke Program" @click="openAssign(f)"><i class="bi bi-link-45deg"></i></button>
                        <button class="pkg-ibtn pkg-ibtn--danger" title="Hapus" @click="askRemove(f)"><i class="bi bi-trash"></i></button>
                    </div>
                </div>

                <!-- Creator strip -->
                <div class="pkg-creator">
                    <span class="pkg-creator__av" style="background:#6366f1">{{ initials(f.Created_By_Nama || f.Created_By) }}</span>
                    <span class="pkg-creator__name">{{ f.Created_By_Nama || f.Created_By || 'Sistem' }}</span>
                    <span class="pkg-creator__at"><i class="bi bi-clock"></i> {{ f.Created_At || '—' }}</span>
                </div>

                <!-- Expanded detail: questions -->
                <div v-if="open === f.Id_Master_Feedback_Form" class="pkg-detail">
                    <div class="pkg-dhead pkg-dhead--indigo">
                        <span><i class="bi bi-list-ol"></i> PERTANYAAN FORM — {{ (f._pertanyaan || []).length }} butir</span>
                        <button class="wca-btn wca-btn--soft wca-btn--sm" type="button"
                                :disabled="savingQ[f.Id_Master_Feedback_Form]"
                                :onClick="savingQ[f.Id_Master_Feedback_Form] ? null : () => showTypePicker(f)"><i class="bi bi-plus-circle"></i> Tambah Pertanyaan</button>
                    </div>

                    <!-- Loading skeleton -->
                    <div v-if="f._loadingQuestions" class="fb-loading">
                        <div class="fb-loading__pulse"></div>
                        <span>Memuat pertanyaan...</span>
                    </div>

                    <div v-else-if="!f._pertanyaan || !f._pertanyaan.length" class="fb-empty-questions">
                        <div class="fb-empty-questions__icon"><i class="bi bi-lightbulb"></i></div>
                        <h4>Belum ada pertanyaan</h4>
                        <p>Klik <b>Tambah Pertanyaan</b> lalu pilih tipe pertanyaan — Rating, NPS, Likert, Teks, atau Pilihan Ganda.</p>
                    </div>

                    <!-- DRAG-DROP QUESTION LIST -->
                    <draggable
                        v-else
                        v-model="f._pertanyaan"
                        item-key="_key"
                        handle=".fb-q-drag"
                        ghost-class="fb-q-ghost"
                        @end="onReorder(f)"
                    >
                        <template #item="{ element: p, index: i }">
                            <div :class="['fb-q-card', { 'fb-q-card--expanded': f._editIdx === i }]">
                                <!-- COLLAPSED STATE -->
                                <div v-if="f._editIdx !== i" class="fb-q-collapsed" @click="startEditing(f, i)">
                                    <span class="fb-q-drag" @click.stop><i class="bi bi-grip-vertical"></i></span>
                                    <span class="fb-q-collapsed__num">{{ i + 1 }}</span>
                                    <span class="fb-q-collapsed__icon" v-html="typeIcon(p.Tipe)"></span>
                                    <span class="fb-q-collapsed__text">
                                        <strong>{{ p.Label || 'Pertanyaan tanpa judul' }}</strong>
                                        <small>{{ typeLabel(p.Tipe) }}</small>
                                    </span>
                                    <span :class="['wca-badge', typeBadgeClass(p.Tipe)]" style="margin-right:8px">{{ p.Tipe }}</span>
                                    <button class="fb-q-collapsed__edit" @click.stop="startEditing(f, i)"><i class="bi bi-pencil"></i></button>
                                    <button class="fb-q-collapsed__del" @click.stop="removeQuestion(f, i)"><i class="bi bi-trash"></i></button>
                                </div>

                                <!-- EXPANDED STATE -->
                                <div v-else class="fb-q-expanded">
                                    <div class="fb-q-expanded__head" @click="saveIfChanged(f); f._editIdx = null">
                                        <span class="fb-q-drag" @click.stop><i class="bi bi-grip-vertical"></i></span>
                                        <span class="fb-q-expanded__num">{{ i + 1 }}</span>
                                        <span class="fb-q-expanded__title">Edit Pertanyaan</span>
                                        <div class="fb-q-expanded__actions" @click.stop>
                                            <button class="fb-q-expanded__cancel" @click="cancelEditing(f, i)"><i class="bi bi-x-lg"></i> Batal</button>
                                            <button class="fb-q-expanded__close" @click="doneEditing(f)"><i class="bi bi-check-lg"></i> Selesai & Simpan</button>
                                        </div>
                                    </div>

                                    <div class="fb-q-expanded__body fb-slide-in" @click.stop>
                                        <!-- Left: editor -->
                                        <div class="fb-q-editor">
                                            <label class="wca-field-lbl">Tipe Pertanyaan</label>
                                            <div class="fb-type-pills">
                                                <button v-for="t in questionTypes" :key="t.value"
                                                    :class="['fb-type-pill', { 'fb-type-pill--active': p.Tipe === t.value }]"
                                                    @click="onTypeChange(p, t.value)">
                                                    <span class="fb-type-pill__icon" v-html="t.icon"></span>
                                                    <span class="fb-type-pill__label">{{ t.label }}</span>
                                                </button>
                                            </div>

                                            <label class="wca-field-lbl" style="margin-top:20px">Pertanyaan</label>
                                            <el-input v-model="p.Label" placeholder="Tulis pertanyaan yang akan ditampilkan ke kandidat..." size="large" />

                                            <!-- Scale range for RATING & NPS only -->
                                            <div v-if="['RATING','NPS'].includes(p.Tipe)" class="fb-scale-row">
                                                <label class="wca-field-lbl">
                                                    <i class="bi bi-sliders2-vertical"></i> Rentang Skala
                                                    <span style="color:var(--danger)">*</span>
                                                </label>
                                                <div class="fb-scale-card">
                                                    <div class="fb-scale-card__body">
                                                        <div class="fb-scale-card__field">
                                                            <span class="fb-scale-card__lbl">Nilai Minimum</span>
                                                            <el-input-number v-model="p.Skala_Min" :min="0" :max="10" size="large" style="width:100%" />
                                                        </div>
                                                        <div class="fb-scale-card__sep">
                                                            <span class="fb-scale-card__arrow">→</span>
                                                        </div>
                                                        <div class="fb-scale-card__field">
                                                            <span class="fb-scale-card__lbl">Nilai Maksimum</span>
                                                            <el-input-number v-model="p.Skala_Max" :min="1" :max="10" size="large" style="width:100%" />
                                                        </div>
                                                    </div>
                                                    <div class="fb-scale-card__foot">
                                                        <span class="fb-scale-card__info">
                                                            <i class="bi bi-info-circle"></i>
                                                            {{ p.Tipe === 'RATING' ? 'Kandidat akan memberikan rating dalam rentang ini menggunakan bintang.' : 'Kandidat akan memilih skor NPS dalam rentang ini.' }}
                                                        </span>
                                                        <el-button size="small" text type="primary" @click="p.Skala_Min = defaultMin(p.Tipe); p.Skala_Max = defaultMax(p.Tipe)">
                                                            <i class="bi bi-arrow-counterclockwise"></i> Kembalikan ke default
                                                        </el-button>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Label Kustom (NPS & LIKERT) -->
                                            <div v-if="['NPS','LIKERT'].includes(p.Tipe)" class="fb-label-custom">
                                                <label class="wca-field-lbl">
                                                    <i class="bi bi-tags"></i> Label Ujung Skala <span style="color:var(--muted);font-weight:400">(opsional)</span>
                                                </label>
                                                <div class="fb-label-custom__row">
                                                    <div class="fb-label-custom__field">
                                                        <span class="fb-label-custom__hint">Label Minimum</span>
                                                        <el-input v-model="p.Label_Min" :placeholder="p.Tipe === 'NPS' ? 'Tidak mungkin' : 'Sangat Tidak Setuju'" size="large" clearable />
                                                    </div>
                                                    <div class="fb-label-custom__sep"><span>—</span></div>
                                                    <div class="fb-label-custom__field">
                                                        <span class="fb-label-custom__hint">Label Maksimum</span>
                                                        <el-input v-model="p.Label_Max" :placeholder="p.Tipe === 'NPS' ? 'Sangat mungkin' : 'Sangat Setuju'" size="large" clearable />
                                                    </div>
                                                </div>
                                                <small class="fb-label-custom__note">
                                                    <i class="bi bi-info-circle"></i> Placeholder = nilai default. Kosongkan untuk pakai default.
                                                </small>
                                            </div>

                                            <!-- Options row -->
                                            <div v-if="['RADIO','CHECKBOX','DROPDOWN'].includes(p.Tipe)" class="fb-options-row">
                                                <label class="wca-field-lbl">
                                                    <i class="bi bi-list-ul"></i> Opsi Pilihan
                                                </label>
                                                <small class="fb-options-hint">
                                                    <i class="bi bi-info-circle"></i> Bisa langsung paste teks multi-baris — otomatis terpecah jadi opsi terpisah.
                                                </small>

                                                <!-- Individual option rows -->
                                                <div class="fb-option-list" v-if="(p._opsiList || []).length">
                                                    <div v-for="(opt, oi) in (p._opsiList || [])" :key="oi" class="fb-option-row">
                                                        <span class="fb-option-row__num">{{ oi + 1 }}</span>
                                                        <textarea
                                                            v-model="p._opsiList[oi]"
                                                            :placeholder="oi === 0 ? 'Tulis atau paste daftar opsi...' : ('Opsi ' + (oi + 1))"
                                                            class="fb-option-row__input"
                                                            rows="1"
                                                            @input="onOptionInput(p, oi, $event)"
                                                        ></textarea>
                                                        <button
                                                            v-if="(p._opsiList || []).length > 1"
                                                            class="fb-option-row__del"
                                                            @click="removeOption(p, oi)"
                                                        ><i class="bi bi-x"></i></button>
                                                    </div>
                                                </div>
                                                <button class="fb-option-add" @click="addOption(p)">
                                                    <i class="bi bi-plus"></i> Tambah Opsi
                                                </button>
                                            </div>
                                        </div>

                                        <!-- Right: proper preview -->
                                        <div class="fb-preview">
                                            <div class="fb-preview__label">Pratinjau — Tampilan Kandidat</div>
                                            <div class="fb-preview__card">
                                                <div class="fb-preview__q">{{ p.Label || 'Pertanyaan Anda...' }}</div>

                                                <!-- Rating: realistic stars preview -->
                                                <div v-if="p.Tipe === 'RATING'" class="fb-preview-rate-wrap">
                                                    <div class="fb-preview-rate__stars">
                                                        <span v-for="s in (p.Skala_Max || 5)" :key="s"
                                                              class="fb-preview-rate__star"
                                                              :class="{ 'fb-preview-rate__star--on': s <= rateDemo(p) }">
                                                            {{ s <= rateDemo(p) ? '★' : '☆' }}
                                                        </span>
                                                    </div>
                                                    <div class="fb-preview-rate__range">
                                                        Skala {{ p.Skala_Min || 1 }} – {{ p.Skala_Max || 5 }}
                                                    </div>
                                                </div>

                                                <!-- NPS: realistic scale-aware buttons with labels -->
                                                <div v-if="p.Tipe === 'NPS'" class="fb-preview-nps-wrap">
                                                    <div class="fb-preview-nps__btns">
                                                        <span v-for="n in npsRange(p)" :key="n"
                                                              class="fb-preview-nps__btn"
                                                              :class="{ 'fb-preview-nps__btn--demo': n === npsDemo(p) }"
                                                              :style="{ background: npsPreviewColor(n) }">
                                                            {{ n }}
                                                        </span>
                                                    </div>
                                                    <div class="fb-preview-nps__labels">
                                                        <span>{{ p.Label_Min || 'Tidak mungkin' }}</span>
                                                        <span>{{ p.Label_Max || 'Sangat mungkin' }}</span>
                                                    </div>
                                                </div>

                                                <!-- Likert: matches LikertInput.vue exactly -->
                                                <div v-if="p.Tipe === 'LIKERT'" class="fb-preview-likert-wrap">
                                                    <div class="fb-preview-likert__opts">
                                                        <span v-for="(lik, li) in likertLabels" :key="li"
                                                              :class="['fb-preview-likert__opt', { 'fb-preview-likert__opt--on': li === 2 }]">
                                                            {{ lik }}
                                                        </span>
                                                    </div>
                                                    <div class="fb-preview-likert__labels">
                                                        <span>{{ p.Label_Min || 'Sangat Tidak Setuju' }}</span>
                                                        <span>{{ p.Label_Max || 'Sangat Setuju' }}</span>
                                                    </div>
                                                </div>

                                                <!-- Textarea -->
                                                <div v-if="p.Tipe === 'TEXTAREA'" class="fb-preview-ta">
                                                    <div class="fb-preview-ta__box">Tulis jawaban kamu di sini...</div>
                                                    <div class="fb-preview-ta__counter">0/500</div>
                                                </div>

                                                <!-- Radio: proper preview with radio buttons -->
                                                <div v-if="p.Tipe === 'RADIO'" class="fb-pv-radio">
                                                    <template v-if="(p._opsiList || []).filter(o => o.trim()).length">
                                                        <div v-for="(opt, oi) in (p._opsiList || [])" :key="oi"
                                                              v-show="opt.trim()"
                                                              class="fb-pv-radio__card"
                                                              :class="{ 'fb-pv-radio__card--sel': oi === 0 }">
                                                            <span class="fb-pv-radio__dot">
                                                                <span v-if="oi === 0" class="fb-pv-radio__dot--fill"></span>
                                                            </span>
                                                            <span class="fb-pv-radio__text">{{ opt }}</span>
                                                        </div>
                                                    </template>
                                                    <template v-else>
                                                        <div class="fb-pv-radio__card fb-pv-radio__card--ph">
                                                            <span class="fb-pv-radio__dot"><span class="fb-pv-radio__dot--fill"></span></span>
                                                            <span class="fb-pv-radio__text">Opsi pilihan 1</span>
                                                        </div>
                                                        <div class="fb-pv-radio__card fb-pv-radio__card--ph">
                                                            <span class="fb-pv-radio__dot"></span>
                                                            <span class="fb-pv-radio__text">Opsi pilihan 2</span>
                                                        </div>
                                                        <div class="fb-pv-radio__card fb-pv-radio__card--ph">
                                                            <span class="fb-pv-radio__dot"></span>
                                                            <span class="fb-pv-radio__text">Opsi pilihan 3</span>
                                                        </div>
                                                        <small class="fb-pv-hint"><i class="bi bi-arrow-left"></i> Isi opsi di panel kiri</small>
                                                    </template>
                                                </div>

                                                <!-- Checkbox: proper preview with checkboxes -->
                                                <div v-if="p.Tipe === 'CHECKBOX'" class="fb-pv-check">
                                                    <template v-if="(p._opsiList || []).filter(o => o.trim()).length">
                                                        <div v-for="(opt, oi) in (p._opsiList || [])" :key="oi"
                                                              v-show="opt.trim()"
                                                              class="fb-pv-check__card"
                                                              :class="{ 'fb-pv-check__card--sel': oi < 2 }">
                                                            <span class="fb-pv-check__box">
                                                                <i v-if="oi < 2" class="bi bi-check-lg"></i>
                                                            </span>
                                                            <span class="fb-pv-check__text">{{ opt }}</span>
                                                        </div>
                                                    </template>
                                                    <template v-else>
                                                        <div class="fb-pv-check__card fb-pv-check__card--ph fb-pv-check__card--sel">
                                                            <span class="fb-pv-check__box"><i class="bi bi-check-lg"></i></span>
                                                            <span class="fb-pv-check__text">Opsi pilihan 1</span>
                                                        </div>
                                                        <div class="fb-pv-check__card fb-pv-check__card--ph fb-pv-check__card--sel">
                                                            <span class="fb-pv-check__box"><i class="bi bi-check-lg"></i></span>
                                                            <span class="fb-pv-check__text">Opsi pilihan 2</span>
                                                        </div>
                                                        <div class="fb-pv-check__card fb-pv-check__card--ph">
                                                            <span class="fb-pv-check__box"></span>
                                                            <span class="fb-pv-check__text">Opsi pilihan 3</span>
                                                        </div>
                                                        <small class="fb-pv-hint"><i class="bi bi-arrow-left"></i> Isi opsi di panel kiri</small>
                                                    </template>
                                                </div>

                                                <!-- Dropdown: realistic select box + option menu -->
                                                <div v-if="p.Tipe === 'DROPDOWN'" class="fb-preview-dd">
                                                    <div class="fb-preview-dd__box">
                                                        <span>Pilih salah satu</span>
                                                        <i class="bi bi-chevron-down"></i>
                                                    </div>
                                                    <template v-if="(p._opsiList || []).filter(o => o.trim()).length">
                                                        <div class="fb-preview-dd__menu">
                                                            <div v-for="(opt, oi) in (p._opsiList || [])" :key="oi"
                                                                 v-show="opt.trim()"
                                                                 class="fb-preview-dd__item"
                                                                 :class="{ 'fb-preview-dd__item--sel': oi === 0 }">
                                                                {{ opt }}
                                                                <i v-if="oi === 0" class="bi bi-check-lg"></i>
                                                            </div>
                                                        </div>
                                                    </template>
                                                    <template v-else>
                                                        <div class="fb-preview-dd__menu fb-preview-dd__menu--ph">
                                                            <div class="fb-preview-dd__item fb-preview-dd__item--ph fb-preview-dd__item--sel">
                                                                Opsi pilihan 1
                                                                <i class="bi bi-check-lg"></i>
                                                            </div>
                                                            <div class="fb-preview-dd__item fb-preview-dd__item--ph">Opsi pilihan 2</div>
                                                            <div class="fb-preview-dd__item fb-preview-dd__item--ph">Opsi pilihan 3</div>
                                                        </div>
                                                        <small class="fb-pv-hint"><i class="bi bi-arrow-left"></i> Isi opsi di panel kiri</small>
                                                    </template>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </draggable>

                    <!-- Bottom action bar — sticky when questions exist -->
                    <div v-if="(f._pertanyaan || []).length" class="fb-q-footer">
                        <div class="fb-q-footer__left">
                            <button class="fb-q-footer__add" type="button" @click="showTypePicker(f)">
                                <i class="bi bi-plus-lg"></i> Tambah Pertanyaan
                            </button>
                        </div>
                        <div class="fb-q-footer__right">
                            <span class="fb-q-footer__info">
                                <span class="fb-q-drag-inline"><i class="bi bi-grip-vertical"></i></span> Geser untuk urutkan
                            </span>
                            <button
                                class="wca-btn wca-btn--primary"
                                type="button"
                                :onClick="savingQ[f.Id_Master_Feedback_Form] ? null : () => saveQuestions(f)"
                                :disabled="savingQ[f.Id_Master_Feedback_Form]"
                            >
                                <i class="bi" :class="savingQ[f.Id_Master_Feedback_Form] ? 'bi-hourglass-split' : 'bi-check-lg'"></i>
                                {{ savingQ[f.Id_Master_Feedback_Form] ? 'Menyimpan...' : 'Simpan Perubahan' }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <div v-if="!loading && !list.length" class="pkg-empty">
                <i class="bi bi-chat-dots"></i> Belum ada form feedback. Klik <b>Form Baru</b> untuk membuat.
            </div>
        </div>

        <!-- Modal create/edit -->
        <AdminModal
            :show="show" :title="editingId ? 'Ubah Form Feedback' : 'Buat Form Feedback'"
            subtitle="Atur nama, mode tampilan, durasi, dan status form." icon="bi-chat-dots"
            :save-label="editingId ? 'Perbarui' : 'Simpan Form'"
            lg
            @close="show = false" @save="saveForm"
        >
            <!-- Identitas Form -->
            <div class="wca-fsection">
                <div class="wca-fsection__label"><i class="bi bi-pencil-square"></i> Identitas Form</div>
                <div class="wca-form">
                    <div>
                        <label class="wca-field-lbl"><i class="bi bi-tag"></i> Nama Form <span style="color:var(--danger)">*</span></label>
                        <el-input v-model="form.Nama" placeholder="mis. Feedback Rekrutmen Reguler 2026" size="large" />
                        <small style="display:block;margin-top:.35rem;color:var(--muted);font-weight:600;font-size:.72rem">Nama form yang mudah dikenali admin saat assign ke program.</small>
                    </div>
                    <div style="margin-top:16px">
                        <label class="wca-field-lbl"><i class="bi bi-text-paragraph"></i> Deskripsi <span style="color:var(--muted);font-weight:400">(opsional)</span></label>
                        <el-input v-model="form.Deskripsi" type="textarea" :rows="3" placeholder="Jelaskan tujuan dan konteks form feedback ini..." />
                    </div>
                </div>
            </div>

            <!-- Pengaturan Tampilan & Durasi -->
            <div class="wca-fsection">
                <div class="wca-fsection__label"><i class="bi bi-sliders"></i> Pengaturan Tampilan & Durasi</div>
                <div class="wca-form">
                    <div>
                        <label class="wca-field-lbl"><i class="bi bi-layout-text-window-reverse"></i> Mode Tampilan <span style="color:var(--danger)">*</span></label>
                        <div class="wca-radio-cards">
                            <label :class="['wca-radio-card', { 'is-active': form.Mode_Tampilan === 'SCROLL' }]">
                                <input type="radio" value="SCROLL" v-model="form.Mode_Tampilan" />
                                <span class="wca-radio-card__icon"><i class="bi bi-file-earmark-text"></i></span>
                                <span class="wca-radio-card__label">Single Page Scroll</span>
                                <span class="wca-radio-card__desc">Semua pertanyaan ditampilkan dalam satu halaman yang bisa di-scroll</span>
                            </label>
                            <label :class="['wca-radio-card', { 'is-active': form.Mode_Tampilan === 'WIZARD' }]">
                                <input type="radio" value="WIZARD" v-model="form.Mode_Tampilan" />
                                <span class="wca-radio-card__icon"><i class="bi bi-chevron-double-right"></i></span>
                                <span class="wca-radio-card__label">Step-by-Step Wizard</span>
                                <span class="wca-radio-card__desc">Satu pertanyaan per langkah dengan progress bar dan navigasi</span>
                            </label>
                        </div>
                    </div>
                    <div style="margin-top:20px">
                        <label class="wca-field-lbl"><i class="bi bi-hourglass-split"></i> Durasi Pengisian</label>
                        <el-input-number v-model="form.Durasi_Hari" :min="1" :max="30" placeholder="Unlimited" size="large" style="width:100%" />
                        <small style="display:block;margin-top:.35rem;color:var(--muted);font-weight:600;font-size:.72rem">
                            {{ form.Durasi_Hari ? 'Kandidat punya waktu ' + form.Durasi_Hari + ' hari sejak email dikirim untuk mengisi feedback' : 'Tidak ada batas waktu — kandidat bisa mengisi kapan saja (maks. 30 hari)' }}
                        </small>
                    </div>
                </div>
            </div>

            <!-- Status -->
            <div class="wca-fsection">
                <div class="wca-fsection__label"><i class="bi bi-toggle-on"></i> Status</div>
                <div class="wca-form">
                    <div>
                        <div :class="['wca-status-card', form.Flag_Aktif === 'Y' ? 'wca-status-card--on' : 'wca-status-card--off']">
                            <span class="wca-status-card__icon">
                                <i class="bi" :class="form.Flag_Aktif === 'Y' ? 'bi-check-circle-fill' : 'bi-pause-circle'"></i>
                            </span>
                            <div class="wca-status-card__body">
                                <strong>{{ form.Flag_Aktif === 'Y' ? 'Aktif' : 'Nonaktif' }}</strong>
                                <small>{{ form.Flag_Aktif === 'Y' ? 'Form dapat digunakan — kandidat akan menerima link feedback' : 'Form tidak akan muncul — kandidat tidak akan diminta mengisi feedback' }}</small>
                            </div>
                            <el-switch v-model="form.Flag_Aktif" active-value="Y" inactive-value="T" size="large" />
                        </div>
                    </div>
                </div>
            </div>
        </AdminModal>

        <!-- Confirm remove -->
        <ConfirmModal :show="!!removeTarget" title="Hapus Form Feedback" :message="removeMessage" icon="bi-trash" @confirm="doRemove" @close="removeTarget = null" />

        <!-- Assignment Modal -->
        <AdminModal
            :show="assignShow" :title="'Assign: ' + (assignForm?.Nama ?? '')"
            subtitle="Assignment spesifik (per program) mengesampingkan assignment general (semua program)." icon="bi-link-45deg"
            save-label="Tambahkan Assignment" @close="assignShow = false" @save="addAssignment"
        >
            <!-- Current Assignments -->
            <div class="wca-fsection">
                <div class="wca-fsection__label"><i class="bi bi-link-45deg"></i> Assignment Saat Ini</div>
                <div v-if="!assignments.length" class="fb-assign-empty">
                    <span class="fb-assign-empty__icon">📋</span>
                    <span>Belum ada assignment. Form ini tidak akan muncul untuk kandidat sampai ditugaskan ke program.</span>
                </div>
                <div v-for="a in assignments" :key="a.Id_Feedback_Assignment" :class="['fb-assign-card', { 'fb-assign-card--off': a.Flag_Aktif !== 'Y' }]">
                    <div class="fb-assign-card__icon">
                        <i :class="a.Flag_General === 'Y' ? 'bi bi-globe2' : 'bi bi-bullseye'"></i>
                    </div>
                    <div class="fb-assign-card__body">
                        <div class="fb-assign-card__title">
                            {{ a.Flag_General === 'Y' ? 'Semua Program' : (a.Program_Nama || 'Program #' + a.Program_Id) }}
                        </div>
                        <div class="fb-assign-card__meta">
                            <span :class="['fb-assign-card__badge', a.Flag_General === 'Y' ? 'fb-assign-card__badge--general' : 'fb-assign-card__badge--specific']">
                                {{ a.Flag_General === 'Y' ? 'General' : 'Spesifik' }}
                            </span>
                            <span v-if="a.Flag_Aktif === 'Y'" class="fb-assign-card__status fb-assign-card__status--on">● Aktif</span>
                            <span v-else class="fb-assign-card__status fb-assign-card__status--off">○ Nonaktif</span>
                        </div>
                    </div>
                    <el-switch :model-value="a.Flag_Aktif === 'Y'" @change="toggleAssignment(a)" size="small" />
                </div>
            </div>

            <!-- Add Assignment -->
            <div class="wca-fsection" style="margin-top:16px">
                <div class="wca-fsection__label"><i class="bi bi-plus-circle"></i> Tambah Assignment</div>
                <div class="fb-assign-form">
                    <div class="fb-assign-form__type">
                        <label :class="['fb-assign-type-card', { 'fb-assign-type-card--active': newAssign.type === 'general' }]" @click="newAssign.type = 'general'">
                            <i class="bi bi-globe2"></i>
                            <div>
                                <strong>Semua Program</strong>
                                <small>Form ini akan dipakai semua program</small>
                            </div>
                        </label>
                        <label :class="['fb-assign-type-card', { 'fb-assign-type-card--active': newAssign.type === 'specific' }]" @click="newAssign.type = 'specific'">
                            <i class="bi bi-bullseye"></i>
                            <div>
                                <strong>Program Tertentu</strong>
                                <small>Pilih satu atau beberapa program</small>
                            </div>
                        </label>
                    </div>
                    <div v-if="newAssign.type === 'specific'" class="fb-assign-form__program">
                        <label class="wca-field-lbl">Pilih Program (bisa lebih dari satu)</label>
                        <el-select v-model="newAssign.program_ids" placeholder="Cari program..." style="width:100%" filterable multiple collapse-tags collapse-tags-tooltip :max-collapse-tags="2" size="large">
                            <el-option v-for="p in programList" :key="p.value" :label="p.label" :value="p.value" />
                        </el-select>
                    </div>
                </div>
            </div>
        </AdminModal>
    </div>
</template>

<script>
import axios from 'axios';
import { Head } from '@inertiajs/vue3';
import AdminModal from '@career/AdminModal.vue';
import ConfirmModal from '@career/ConfirmModal.vue';
import draggable from 'vuedraggable';
import { ingatModal } from '@utils/ingatModal';

export default {
    components: { Head, AdminModal, ConfirmModal, draggable },
    // Modal di halaman ini selamat dari refresh — lihat @utils/ingatModal.
    mixins: [ingatModal('admin/master-feedback/Index')],
    data() {
        return {
            list: [],
            loading: false,
            saving: false, // proses simpan form berjalan → tombol modal dikunci
            open: null,
            assignShow: false,
            assignForm: null,
            assignments: [],
            programList: [],
            newAssign: { type: 'general', program_ids: [] },
            show: false,
            editingId: null,
            removeTarget: null,
            savingQ: {},
            form: { Nama: '', Deskripsi: '', Mode_Tampilan: 'SCROLL', Durasi_Hari: null, Flag_Aktif: 'Y' },
            likertLabels: ['STS', 'TS', 'N', 'S', 'SS'],
            questionTypes: [
                { value: 'RATING', label: 'Rating', icon: '<i class="bi bi-star-fill"></i>' },
                { value: 'NPS', label: 'NPS', icon: '<i class="bi bi-0-circle"></i>' },
                { value: 'LIKERT', label: 'Likert', icon: '<i class="bi bi-ui-radios"></i>' },
                { value: 'TEXTAREA', label: 'Teks', icon: '<i class="bi bi-text-paragraph"></i>' },
                { value: 'RADIO', label: 'Pilih Satu', icon: '<i class="bi bi-record-circle"></i>' },
                { value: 'CHECKBOX', label: 'Multi-Pilih', icon: '<i class="bi bi-check2-square"></i>' },
                { value: 'DROPDOWN', label: 'Dropdown', icon: '<i class="bi bi-chevron-down"></i>' },
            ],
        };
    },
    computed: {
        canAddAssign() {
            if (this.newAssign.type === 'general') return true;
            return this.newAssign.program_ids && this.newAssign.program_ids.length > 0;
        },
        removeMessage() {
            const name = this.removeTarget?.Nama ?? '';
            return `Yakin hapus "${name}"? Pertanyaan di dalamnya juga akan dihapus.`;
        },
    },
    mounted() { this.load(); },
    methods: {
        async toggleExpand(f) {
            if (this.open === f.Id_Master_Feedback_Form) {
                this.open = null;
                return;
            }
            // Buka accordion dulu — instant feedback
            this.open = f.Id_Master_Feedback_Form;
            f._loadingQuestions = true;
            // Fetch data async
            await this.loadQuestions(f);
            f._loadingQuestions = false;
        },
        async openAssign(f) {
            this.assignForm = f;
            this.assignShow = true;
            this.newAssign = { type: 'general', program_id: null };
            const [aRes, pRes] = await Promise.all([
                axios.get('/api/v1/karir/feedback-assignment'),
                axios.get('/api/v1/karir/options/program'),
            ]);
            this.assignments = (aRes.data.result || []).filter(a => a.Master_Feedback_Form_Id === f.Id_Master_Feedback_Form);
            this.programList = pRes.data.result || [];
        },
        async addAssignment() {
            try {
                if (this.newAssign.type === 'general') {
                    await axios.post('/api/v1/karir/feedback-assignment', {
                        master_feedback_form_id: this.assignForm.Id_Master_Feedback_Form,
                        flag_general: 'Y', flag_aktif: 'Y',
                    });
                } else {
                    for (const pid of this.newAssign.program_ids) {
                        await axios.post('/api/v1/karir/feedback-assignment', {
                            master_feedback_form_id: this.assignForm.Id_Master_Feedback_Form,
                            program_id: pid, flag_general: 'T', flag_aktif: 'Y',
                        });
                    }
                }
                this.$message.success('Assignment berhasil ditambahkan');
                this.newAssign = { type: 'general', program_ids: [] };
                this.assignShow = false;
            } catch (e) {
                const msg = e.response?.data?.message || e.message || 'Gagal menambah assignment';
                this.$message.error(msg);
                console.error('[MasterFeedback:addAssignment]', e);
            }
        },
        async toggleAssignment(a) {
            try {
                await axios.put(`/api/v1/karir/feedback-assignment/${a.Id_Feedback_Assignment}`, {
                    flag_aktif: a.Flag_Aktif === 'Y' ? 'T' : 'Y',
                });
                await this.openAssign(this.assignForm);
            } catch (e) {
                const msg = e.response?.data?.message || e.message || 'Gagal mengubah assignment';
                this.$message.error(msg);
                console.error('[MasterFeedback:toggleAssignment]', e);
            }
        },
        async load() {
            this.loading = true;
            try {
                const { data } = await axios.get('/api/v1/karir/master-feedback');
                this.list = (data.result || []).map(f => ({ ...f, _pertanyaan: [], _questionsLoaded: false }));
            } catch (e) {
                const msg = e.response?.data?.message || e.message || 'Gagal memuat data form';
                this.$message.error(msg);
                console.error('[MasterFeedback:load]', e);
            }
            this.loading = false;
        },
        async loadQuestions(f) {
            if (f._questionsLoaded) return;
            try {
                const { data } = await axios.get(`/api/v1/karir/master-feedback/${f.Id_Master_Feedback_Form}`);
                f._pertanyaan = (data.result?.pertanyaan || []).map(p => ({
                    ...p,
                    _key: 'q_' + (p.Id_Master_Feedback_Pertanyaan || Math.random().toString(36).slice(2, 10)),
                    _opsiList: Array.isArray(p.Opsi) ? [...p.Opsi] : [],
                    Label_Min: p.Label_Min || null,
                    Label_Max: p.Label_Max || null,
                }));
                if (!f._questionsLoaded) f._editIdx = null;
                f.Jumlah_Pertanyaan = f._pertanyaan.length;
                f._questionsLoaded = true;
            } catch (e) {
                f._pertanyaan = [];
                f._questionsLoaded = true;
                const msg = e.response?.data?.message || e.message || 'Gagal memuat pertanyaan';
                this.$message.error(msg);
                console.error('[MasterFeedback:loadQuestions]', e);
            }
        },
        openCreate() { this.editingId = null; this.form = { Nama: '', Deskripsi: '', Mode_Tampilan: 'SCROLL', Durasi_Hari: null, Flag_Aktif: 'Y' }; this.show = true; },
        async openEdit(f) {
            await this.loadQuestions(f);
            this.editingId = f.Id_Master_Feedback_Form;
            this.form = {
                Nama: f.Nama, Deskripsi: f.Deskripsi || '', Mode_Tampilan: f.Mode_Tampilan || 'SCROLL',
                Durasi_Hari: f.Durasi_Hari, Flag_Aktif: f.Flag_Aktif || 'T',
            };
            this.show = true;
        },
        async saveForm() {
            if (this.saving) return; // cegah klik ganda → data dobel
            this.saving = true;
            try {
                const payload = { nama: this.form.Nama, deskripsi: this.form.Deskripsi, mode_tampilan: this.form.Mode_Tampilan, durasi_hari: this.form.Durasi_Hari, flag_aktif: this.form.Flag_Aktif };
                if (this.editingId) {
                    await axios.put(`/api/v1/karir/master-feedback/${this.editingId}`, payload);
                } else {
                    await axios.post('/api/v1/karir/master-feedback', payload);
                }
                this.$message.success(this.editingId ? 'Form berhasil diupdate' : 'Form berhasil dibuat');
                this.show = false; this.load();
            } catch (e) {
                const msg = e.response?.data?.message || e.message || 'Gagal menyimpan form';
                this.$message.error(msg);
                console.error('[MasterFeedback:saveForm]', e);
            } finally {
                this.saving = false;
            }
        },
        askRemove(f) { this.removeTarget = f; },
        async doRemove() {
            if (!this.removeTarget) return;
            try {
                await axios.delete(`/api/v1/karir/master-feedback/${this.removeTarget.Id_Master_Feedback_Form}`);
                if (this.open === this.removeTarget.Id_Master_Feedback_Form) this.open = null;
                this.$message.success('Form berhasil dihapus');
                this.removeTarget = null; this.load();
            } catch (e) {
                const msg = e.response?.data?.message || e.message || 'Gagal menghapus form';
                this.$message.error(msg);
                console.error('[MasterFeedback:doRemove]', e);
            }
        },
        async setStatus(f, active) {
            try {
                await axios.put(`/api/v1/karir/master-feedback/${f.Id_Master_Feedback_Form}`, { nama: f.Nama, deskripsi: f.Deskripsi, mode_tampilan: f.Mode_Tampilan, durasi_hari: f.Durasi_Hari, flag_aktif: active ? 'Y' : 'T' });
                this.$message.success(active ? 'Form diaktifkan' : 'Form dinonaktifkan');
                this.load();
            } catch (e) {
                const msg = e.response?.data?.message || e.message || 'Gagal mengubah status form';
                this.$message.error(msg);
                console.error('[MasterFeedback:setStatus]', e);
            }
        },
        showTypePicker(f) {
            if (!f._pertanyaan) f._pertanyaan = [];
            const key = 'q_' + Date.now() + '_' + Math.random().toString(36).slice(2, 6);
            const newQ = { _key: key, Tipe: 'RATING', Label: '', Skala_Min: 1, Skala_Max: 5, _opsiList: [] };
            f._pertanyaan.push(newQ);
            f._snapshot = JSON.parse(JSON.stringify(newQ)); // snapshot untuk batalkan
            f._editIdx = f._pertanyaan.length - 1;
            // Auto-scroll to new card after DOM update
            this.$nextTick(() => {
                try {
                    const cards = document.querySelectorAll('.fb-q-card--expanded');
                    if (cards.length) cards[cards.length - 1].scrollIntoView({ behavior: 'smooth', block: 'center' });
                } catch (e) { /* ignore scroll errors */ }
            });
        },
        startEditing(f, i) {
            // Toggle: kalau sudah terbuka → tutup
            if (f._editIdx === i) {
                this.saveIfChanged(f);
                f._editIdx = null;
                return;
            }
            this.saveIfChanged(f); // simpan hanya jika ada perubahan
            this.snapshotQuestion(f, i); // ambil snapshot sebelum edit
            f._editIdx = i;
        },
        saveIfChanged(f) {
            if (!f._snapshot) return;
            const current = f._pertanyaan?.[f._editIdx];
            if (!current) return;
            const changed = JSON.stringify(current) !== JSON.stringify(f._snapshot);
            if (changed) this.saveQuestionsSilent(f); // background save — no await
        },
        snapshotQuestion(f, i) {
            if (!f._pertanyaan || !f._pertanyaan[i]) return;
            f._snapshot = JSON.parse(JSON.stringify(f._pertanyaan[i]));
        },
        cancelEditing(f, i) {
            // Kembalikan ke snapshot
            if (f._snapshot) {
                f._pertanyaan.splice(i, 1, f._snapshot);
                f._snapshot = null;
            }
            f._editIdx = null;
        },
        doneEditing(f) {
            const idx = f._editIdx;
            f._editIdx = null; // tutup instant — no wait
            const changed = f._snapshot && JSON.stringify(f._pertanyaan?.[idx]) !== JSON.stringify(f._snapshot);
            f._snapshot = null;
            if (changed) this.saveQuestionsSilent(f); // background save
        },
        addQuestion(f) { this.showTypePicker(f); },
        onReorder(f) {
            if (!f._pertanyaan) return;
            f._pertanyaan.forEach((p, i) => { p.Urutan = i + 1; });
        },
        onTypeChange(p, newType) {
            p.Tipe = newType;
            this.ensureDefaults(p);
            if (['RADIO','CHECKBOX','DROPDOWN'].includes(newType)) {
                if (!p._opsiList || !p._opsiList.length) p._opsiList = ['', ''];
            }
        },
        ensureDefaults(p) {
            if (p.Tipe === 'LIKERT') {
                p.Skala_Min = 1; p.Skala_Max = 5; // Likert selalu 1-5
            } else if (['RATING','NPS'].includes(p.Tipe)) {
                if (p.Skala_Min == null) p.Skala_Min = this.defaultMin(p.Tipe);
                if (p.Skala_Max == null) p.Skala_Max = this.defaultMax(p.Tipe);
            }
            if (['RADIO','CHECKBOX','DROPDOWN'].includes(p.Tipe)) {
                if (!p._opsiList) p._opsiList = [];
            }
        },
        addOption(p) {
            if (!p._opsiList) p._opsiList = [];
            p._opsiList.push('');
        },
        onOptionInput(p, idx, event) {
            const el = event.target;
            const val = el?.value ?? '';
            if (!val.includes('\n')) return;

            // Split, clean, filter
            const lines = val.split('\n')
                .map(l => l
                    .replace(/^[\s]*[-•*✓✅☑️✔️▪︎▸►▻\d]+[.)\s]*\s*/, '')
                    .trim()
                )
                .filter(l => l.length > 0);

            if (lines.length > 1) {
                p._opsiList = lines;
                // Reset textarea height after split
                if (el) { el.style.height = 'auto'; el.rows = 1; }
            }
        },
        removeOption(p, idx) {
            if (!p._opsiList) return;
            p._opsiList.splice(idx, 1);
        },
        async removeQuestion(f, i) {
            if (this.savingQ[f.Id_Master_Feedback_Form]) return;
            const removed = f._pertanyaan.splice(i, 1)[0];
            this.saveQuestionsSilent(f).catch((e) => {
                f._pertanyaan.splice(i, 0, removed);
                const msg = e.response?.data?.message || e.message || 'Gagal menghapus pertanyaan';
                this.$message.error(msg);
                console.error('[MasterFeedback:removeQuestion]', e);
            });
        },
        async saveQuestionsSilent(f) {
            if (this.savingQ[f.Id_Master_Feedback_Form]) return;
            this.savingQ = { ...this.savingQ, [f.Id_Master_Feedback_Form]: true };
            try {
                const pertanyaan = this.buildPertanyaanPayload(f);
                await axios.post(`/api/v1/karir/master-feedback/${f.Id_Master_Feedback_Form}/pertanyaan`, { pertanyaan });
            } catch (e) {
                const msg = e.response?.data?.message || e.message || 'Gagal menyimpan pertanyaan';
                this.$message.error(msg);
                console.error('[MasterFeedback:saveQuestions]', e);
                throw e; // rethrow agar caller bisa rollback (mis. removeQuestion)
            } finally {
                this.savingQ = { ...this.savingQ, [f.Id_Master_Feedback_Form]: false };
            }
        },
        buildPertanyaanPayload(f) {
            const optionTypes = ['RADIO', 'CHECKBOX', 'DROPDOWN'];
            const numericTypes = ['RATING', 'NPS', 'LIKERT'];
            return (f._pertanyaan || []).map((p, i) => {
                const item = { urutan: i + 1, tipe: p.Tipe, label: p.Label };
                // Kirim Id existing untuk UPSERT — cegah delete-reinsert
                if (p.Id_Master_Feedback_Pertanyaan) item.id = p.Id_Master_Feedback_Pertanyaan;
                if (numericTypes.includes(p.Tipe)) {
                    item.skala_min = p.Skala_Min ?? null;
                    item.skala_max = p.Skala_Max ?? null;
                    if (['NPS', 'LIKERT'].includes(p.Tipe)) {
                        item.label_min = p.Label_Min || null;
                        item.label_max = p.Label_Max || null;
                    }
                }
                if (optionTypes.includes(p.Tipe)) {
                    const clean = (p._opsiList || []).filter(o => o && o.trim());
                    item.opsi = clean.length ? clean : null;
                }
                return item;
            });
        },
        saveQuestions(f) {
            this.saveQuestionsSilent(f);
        },
        initials(name) {
            if (!name) return '?';
            return name.split(' ').filter(Boolean).slice(0, 2).map(w => w[0]).join('').toUpperCase();
        },
        typeBadgeClass(tipe) {
            const map = { RATING: 'wca-b--amber', NPS: 'wca-b--indigo', LIKERT: 'wca-b--indigo', TEXTAREA: 'wca-b--slate', RADIO: 'wca-b--green', CHECKBOX: 'wca-b--green', DROPDOWN: 'wca-b--slate' };
            return map[tipe] || 'wca-b--slate';
        },
        typeLabel(tipe) {
            const map = { RATING: 'Bintang 1-5', NPS: 'NPS 0-10', LIKERT: 'Likert', TEXTAREA: 'Teks Bebas', RADIO: 'Pilih Satu', CHECKBOX: 'Multi-Pilih', DROPDOWN: 'Dropdown' };
            return map[tipe] || tipe;
        },
        defaultMin(tipe) {
            const map = { RATING: 1, NPS: 0, LIKERT: 1 };
            return map[tipe] ?? 1;
        },
        defaultMax(tipe) {
            const map = { RATING: 5, NPS: 10, LIKERT: 5 };
            return map[tipe] ?? 5;
        },
        typeIcon(tipe) {
            const map = {
                RATING: '<i class="bi bi-star-fill"></i>',
                NPS: '<i class="bi bi-0-circle"></i>',
                LIKERT: '<i class="bi bi-ui-radios"></i>',
                TEXTAREA: '<i class="bi bi-text-paragraph"></i>',
                RADIO: '<i class="bi bi-record-circle"></i>',
                CHECKBOX: '<i class="bi bi-check2-square"></i>',
                DROPDOWN: '<i class="bi bi-chevron-down"></i>',
            };
            return map[tipe] || '<i class="bi bi-question-circle"></i>';
        },
        npsPreviewColor(n) {
            if (n <= 2) return 'rgba(239,68,68,0.85)';
            if (n <= 4) return 'rgba(239,68,68,0.55)';
            if (n <= 6) return 'rgba(245,158,11,0.55)';
            if (n <= 8) return 'rgba(34,197,94,0.55)';
            return 'rgba(34,197,94,0.85)';
        },
        npsRange(p) {
            const min = p.Skala_Min ?? 0;
            const max = p.Skala_Max ?? 10;
            const result = [];
            for (let i = min; i <= max; i++) result.push(i);
            return result;
        },
        npsDemo(p) {
            const max = p.Skala_Max ?? 10;
            return Math.floor(max * 0.7); // realistic demo: 70th percentile
        },
        rateDemo(p) {
            const max = p.Skala_Max || 5;
            return Math.ceil(max * 0.6); // realistic demo: 60% filled
        },
    },
};
</script>

<style scoped>
/* ── Header button refinement ── */
.pkg-newbtn { padding: 9px 18px; font-size: .85rem; }

/* ── Radio Cards (Mode Tampilan) ── */
.wca-radio-cards { display: flex; gap: 12px; }
.wca-radio-card {
    flex: 1; display: flex; flex-direction: column; align-items: center; gap: 6px;
    padding: 16px 12px; border: 2px solid var(--border-light, #e2e8f0); border-radius: 12px;
    cursor: pointer; transition: all 0.2s; text-align: center; background: #fff;
}
.wca-radio-card:hover { border-color: #a5b4fc; background: rgba(99,102,241,.03); }
.wca-radio-card.is-active { border-color: #6366f1; background: rgba(99,102,241,.06); box-shadow: 0 0 0 3px rgba(99,102,241,.1); }
.wca-radio-card input[type="radio"] { display: none; }
.wca-radio-card__icon { font-size: 1.5rem; color: var(--muted,#94a3b8); line-height: 1; }
.wca-radio-card.is-active .wca-radio-card__icon { color: #6366f1; }
.wca-radio-card__label { font-size: .82rem; font-weight: 700; color: #334155; }
.wca-radio-card.is-active .wca-radio-card__label { color: #6366f1; }
.wca-radio-card__desc { font-size: .7rem; color: var(--muted,#94a3b8); line-height: 1.35; }

/* ── Status Card ── */
.wca-status-card {
    display: flex; align-items: center; gap: 14px; padding: 16px 18px;
    border: 1.5px solid var(--border-light,#e2e8f0); border-radius: 12px; background: #fff; width: 100%;
}
.wca-status-card--on { border-color: rgba(16,185,129,.3); background: rgba(16,185,129,.04); }
.wca-status-card--off { border-color: rgba(148,163,184,.25); background: rgba(148,163,184,.03); }
.wca-status-card__icon { font-size: 1.6rem; line-height: 1; }
.wca-status-card--on .wca-status-card__icon { color: #10b981; }
.wca-status-card--off .wca-status-card__icon { color: #94a3b8; }
.wca-status-card__body { flex: 1; }
.wca-status-card__body strong { display: block; font-size: .85rem; color: #1e293b; }
.wca-status-card__body small { display: block; font-size: .73rem; color: var(--muted,#94a3b8); margin-top: 2px; }

/* ── Loading Skeleton ── */
.fb-loading {
    display: flex; flex-direction: column; align-items: center; gap: 12px;
    padding: 32px 20px; color: #94a3b8; font-size: .82rem; font-weight: 600;
}
.fb-loading__pulse {
    width: 40px; height: 40px; border-radius: 12px;
    background: linear-gradient(135deg, #6366f1, #818cf8);
    animation: fb-pulse 1.2s ease-in-out infinite;
}
@keyframes fb-pulse {
    0%, 100% { opacity: 0.4; transform: scale(0.9); }
    50% { opacity: 1; transform: scale(1.05); }
}

/* ── Empty Questions State ── */
.fb-empty-questions {
    text-align: center; padding: 40px 20px;
    background: linear-gradient(135deg, #f8f7ff 0%, #f0f0ff 100%);
    border: 2px dashed #c4b5fd; border-radius: 14px; margin-bottom: 8px;
}
.fb-empty-questions__icon { font-size: 2.5rem; color: #a78bfa; margin-bottom: 12px; }
.fb-empty-questions h4 { margin: 0 0 6px; font-size: 1rem; color: #4f46e5; }
.fb-empty-questions p { margin: 0; font-size: .82rem; color: #8b83a9; line-height: 1.5; }

/* ── Question Card ── */
.fb-q-card {
    background: #fff; border: 1px solid #e2e8f0; border-radius: 10px;
    margin-bottom: 8px; transition: border-color .2s, box-shadow .2s; overflow: hidden;
}
.fb-q-card:hover { border-color: #c4b5fd; }
.fb-q-card--expanded { border-color: #6366f1; box-shadow: 0 4px 20px rgba(99,102,241,.12); }

/* Smooth expand animation — no glitch, pure CSS */
.fb-slide-in {
    animation: fb-fade-slide .2s ease-out;
}
@keyframes fb-fade-slide {
    from { opacity: 0; transform: translateY(-4px); }
    to   { opacity: 1; transform: translateY(0); }
}

/* Drag handle */
.fb-q-drag { cursor: grab; color: #cbd5e1; font-size: 1rem; padding: 0 2px; user-select: none; flex-shrink: 0; line-height: 1; }
.fb-q-drag:hover { color: #6366f1; }
.fb-q-drag-inline { display: inline-flex; vertical-align: middle; color: #6366f1; }
.fb-q-ghost { opacity: 0.35; background: #f0f0ff; border: 2px dashed #6366f1; border-radius: 10px; }

/* ── Collapsed State ── */
.fb-q-collapsed {
    display: flex; align-items: center; gap: 8px; padding: 10px 12px; cursor: pointer;
    transition: background .12s;
}
.fb-q-collapsed:hover { background: rgba(99,102,241,.02); }
.fb-q-collapsed__num {
    width: 24px; height: 24px; border-radius: 6px; background: #6366f1;
    color: #fff; font-size: .7rem; font-weight: 700; display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
}
.fb-q-collapsed__icon { font-size: .9rem; color: #94a3b8; flex-shrink: 0; line-height: 1; }
.fb-q-collapsed__text { flex: 1; min-width: 0; }
.fb-q-collapsed__text strong { display: block; font-size: .82rem; color: #334155; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.fb-q-collapsed__text small { display: block; font-size: .68rem; color: #94a3b8; }
.fb-q-collapsed__edit, .fb-q-collapsed__del {
    border: none; background: none; cursor: pointer; font-size: .85rem; padding: 5px 7px; border-radius: 5px;
    transition: all .12s; flex-shrink: 0; line-height: 1;
}
.fb-q-collapsed__edit { color: #6366f1; }
.fb-q-collapsed__edit:hover { background: rgba(99,102,241,.08); }
.fb-q-collapsed__del { color: #94a3b8; }
.fb-q-collapsed__del:hover { background: rgba(239,68,68,.08); color: #ef4444; }

/* ── Expanded State ── */
.fb-q-expanded__head {
    display: flex; align-items: center; gap: 8px; padding: 10px 14px;
    background: #f8f7ff; border-bottom: 1px solid #ede9fe; cursor: pointer;
}
.fb-q-expanded__num {
    width: 24px; height: 24px; border-radius: 6px; background: #6366f1;
    color: #fff; font-size: .7rem; font-weight: 700; display: flex; align-items: center; justify-content: center;
}
.fb-q-expanded__title { flex: 1; font-size: .8rem; font-weight: 700; color: #4f46e5; }
.fb-q-expanded__actions { display: flex; gap: 6px; }
.fb-q-expanded__cancel {
    border: none; background: #f1f5f9; color: #64748b;
    padding: 5px 12px; border-radius: 7px; font-size: .75rem; font-weight: 700; cursor: pointer;
}
.fb-q-expanded__cancel:hover { background: #e2e8f0; color: #475569; }
.fb-q-expanded__close {
    border: none; background: #10b981; color: #fff;
    padding: 5px 12px; border-radius: 7px; font-size: .75rem; font-weight: 700; cursor: pointer;
}
.fb-q-expanded__close:hover { opacity: .85; }

.fb-q-expanded__body { display: flex; gap: 18px; padding: 16px; }

/* ── Editor (left) ── */
.fb-q-editor { flex: 3; min-width: 0; }

/* Type pills */
.fb-type-pills { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 4px; }
.fb-type-pill {
    display: flex; align-items: center; gap: 6px; padding: 8px 14px;
    border: 1.5px solid #e2e8f0; border-radius: 10px; background: #fff;
    cursor: pointer; transition: all .15s; font-size: .78rem; font-weight: 600; color: #64748b;
}
.fb-type-pill:hover { border-color: #a5b4fc; }
.fb-type-pill--active {
    border-color: #6366f1; background: rgba(99,102,241,.08); color: #4f46e5;
    box-shadow: 0 2px 8px rgba(99,102,241,.15);
}
.fb-type-pill__icon { font-size: .95rem; line-height: 1; }
.fb-type-pill__label { white-space: nowrap; }

/* ── Scale Range Card ── */
.fb-scale-row { margin-top: 20px; }
.fb-scale-card {
    margin-top: 6px; border: 1.5px solid #e2e8f0; border-radius: 14px;
    background: #fff; overflow: hidden;
}
.fb-scale-card__body {
    display: flex; align-items: center; gap: 0; padding: 18px 20px 14px;
}
.fb-scale-card__field { flex: 1; }
.fb-scale-card__lbl {
    display: block; font-size: .7rem; font-weight: 700; color: #94a3b8;
    text-transform: uppercase; letter-spacing: .06em; margin-bottom: 6px;
}
.fb-scale-card__sep {
    display: flex; align-items: center; justify-content: center;
    padding: 0 14px; margin-top: 18px;
}
.fb-scale-card__arrow { font-size: 1.3rem; color: #c4b5fd; font-weight: 700; }
.fb-scale-card__foot {
    display: flex; justify-content: space-between; align-items: center;
    padding: 10px 20px; background: #f8fafc; border-top: 1px solid #f1f5f9;
}
.fb-scale-card__info { font-size: .72rem; color: #94a3b8; font-weight: 600; }
.fb-scale-card__info i { margin-right: 4px; }

/* ── Label Custom ── */
.fb-label-custom { margin-top: 16px; }
.fb-label-custom__row { display: flex; align-items: flex-start; gap: 12px; margin-top: 6px; }
.fb-label-custom__field { flex: 1; }
.fb-label-custom__hint { display: block; font-size: .7rem; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: .06em; margin-bottom: 4px; }
.fb-label-custom__sep { display: flex; align-items: center; padding-top: 22px; color: #c4b5fd; font-weight: 700; font-size: 1rem; }
.fb-label-custom__note { display: block; margin-top: 6px; font-size: .7rem; color: #94a3b8; }

/* ── Options hint ── */
.fb-options-hint { display: block; margin-top: 2px; margin-bottom: 8px; font-size: .7rem; color: #94a3b8; }

/* ── Option Rows (clean, minimal) ── */
.fb-options-row { margin-top: 20px; }
.fb-option-list { display: flex; flex-direction: column; gap: 8px; margin-top: 8px; }
.fb-option-row {
    display: flex; align-items: center; gap: 10px;
}
.fb-option-row__num {
    width: 26px; height: 26px; border-radius: 50%;
    background: #f1f5f9; color: #64748b;
    font-size: .72rem; font-weight: 700;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
}
.fb-option-row__input {
    flex: 1; padding: 10px 14px; border: 1.5px solid #e2e8f0; border-radius: 10px;
    font-size: .88rem; font-family: 'Inter', sans-serif; color: #334155;
    outline: none; transition: border-color .15s; background: #fff;
    resize: none; overflow: hidden; line-height: 1.5;
}
.fb-option-row__input:focus { border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99,102,241,.08); }
.fb-option-row__input::placeholder { color: #cbd5e1; }
.fb-option-row__del {
    border: none; background: none; color: #cbd5e1; cursor: pointer;
    padding: 6px 8px; border-radius: 8px; font-size: 1rem; flex-shrink: 0;
    transition: all .15s; line-height: 1;
}
.fb-option-row__del:hover { background: rgba(239,68,68,.08); color: #ef4444; }

.fb-option-add {
    display: inline-flex; align-items: center; gap: 8px; margin-top: 12px;
    border: none; background: none; padding: 8px 4px;
    color: #6366f1; font-weight: 600; font-size: .84rem;
    cursor: pointer; transition: opacity .15s;
}
.fb-option-add:hover { opacity: .8; }

/* ── Preview Panel ── */
.fb-preview { flex: 2; min-width: 240px; }
.fb-preview__label {
    font-size: .68rem; font-weight: 800; letter-spacing: .1em; color: #a78bfa;
    text-transform: uppercase; margin-bottom: 10px; display: flex; align-items: center; gap: 6px;
}
.fb-preview__label::before { content: '👁️'; font-size: .75rem; }
.fb-preview__card {
    background: #fff; border: 1.5px solid #e2e8f0; border-radius: 16px;
    padding: 24px 22px; min-height: 120px; box-shadow: 0 4px 20px rgba(0,0,0,.04);
}
.fb-preview__q { font-size: .9rem; font-weight: 600; color: #1e293b; margin-bottom: 18px; line-height: 1.5; }

/* ── Rating Preview ── */
.fb-preview-rate-wrap { text-align: center; }
.fb-preview-rate__stars { display: flex; gap: 6px; justify-content: center; }
.fb-preview-rate__star {
    font-size: 34px; line-height: 1; cursor: default; color: #cbd5e1; transition: none; user-select: none;
}
.fb-preview-rate__star--on { color: #f59e0b; }
.fb-preview-rate__range { margin-top: 8px; font-size: .72rem; color: #94a3b8; font-weight: 600; }

/* ── Likert Preview (matches LikertInput.vue) ── */
.fb-preview-likert-wrap { text-align: center; }
.fb-preview-likert__opts { display: flex; gap: 8px; justify-content: center; }
.fb-preview-likert__opt {
    display: flex; flex-direction: column; align-items: center; gap: 4px;
    padding: 10px 14px; border: 2px solid #e2e8f0; border-radius: 10px;
    font-size: .8rem; font-weight: 600; color: #94a3b8; background: #fff;
    cursor: default; user-select: none; min-width: 44px;
}
.fb-preview-likert__opt--on {
    border-color: #6366f1; background: rgba(99,102,241,.06); color: #6366f1;
}
.fb-preview-likert__labels {
    display: flex; justify-content: space-between; margin-top: 8px;
    font-size: .68rem; color: #94a3b8;
}

/* ── NPS Preview ── */
.fb-preview-nps-wrap { text-align: center; }
.fb-preview-nps__btns { display: flex; gap: 4px; flex-wrap: wrap; justify-content: center; }
.fb-preview-nps__btn {
    width: 34px; height: 34px; border-radius: 8px; color: #fff; font-size: .8rem;
    font-weight: 700; display: flex; align-items: center; justify-content: center;
    opacity: 0.6; cursor: default; user-select: none;
}
.fb-preview-nps__btn--demo {
    opacity: 1; transform: scale(1.12); box-shadow: 0 3px 10px rgba(0,0,0,.2); position: relative; z-index: 1;
}
.fb-preview-nps__labels { display: flex; justify-content: space-between; margin-top: 8px; font-size: .7rem; color: #94a3b8; font-weight: 600; }

/* Textarea preview */
.fb-preview-ta__box {
    padding: 14px 16px; border: 1.5px solid #e2e8f0; border-radius: 10px;
    font-size: .85rem; color: #94a3b8; min-height: 80px; background: #fafafa;
}
.fb-preview-ta__counter { text-align: right; font-size: .7rem; color: #cbd5e1; margin-top: 4px; }

/* ── Radio Preview (realistic cards with radio dot) ── */
.fb-pv-radio { display: flex; flex-direction: column; gap: 8px; }
.fb-pv-radio__card {
    display: flex; align-items: center; gap: 12px; padding: 12px 16px;
    border: 2px solid #e2e8f0; border-radius: 12px; background: #fff;
    cursor: default; user-select: none; transition: none;
}
.fb-pv-radio__card--sel {
    border-color: #6366f1; background: rgba(99,102,241,.04);
}
.fb-pv-radio__dot {
    width: 20px; height: 20px; border-radius: 50%; border: 2px solid #cbd5e1;
    display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.fb-pv-radio__card--sel .fb-pv-radio__dot { border-color: #6366f1; }
.fb-pv-radio__dot--fill {
    width: 10px; height: 10px; border-radius: 50%; background: #6366f1;
}
.fb-pv-radio__text { font-size: .88rem; color: #475569; }
.fb-pv-radio__card--sel .fb-pv-radio__text { color: #6366f1; font-weight: 600; }

/* ── Checkbox Preview (realistic cards with checkbox) ── */
.fb-pv-check { display: flex; flex-direction: column; gap: 8px; }
.fb-pv-check__card {
    display: flex; align-items: center; gap: 12px; padding: 12px 16px;
    border: 2px solid #e2e8f0; border-radius: 12px; background: #fff;
    cursor: default; user-select: none; transition: none;
}
.fb-pv-check__card--sel {
    border-color: #6366f1; background: rgba(99,102,241,.04);
}
.fb-pv-check__box {
    width: 20px; height: 20px; border-radius: 5px; border: 2px solid #cbd5e1;
    display: flex; align-items: center; justify-content: center; flex-shrink: 0;
    font-size: .7rem;
}
.fb-pv-check__card--sel .fb-pv-check__box {
    border-color: #6366f1; background: #6366f1; color: #fff;
}
.fb-pv-check__text { font-size: .88rem; color: #475569; }
.fb-pv-check__card--sel .fb-pv-check__text { color: #6366f1; font-weight: 600; }

/* Placeholder state */
.fb-pv-radio__card--ph, .fb-pv-check__card--ph { opacity: 0.5; border-style: dashed; }
.fb-pv-hint {
    display: block; margin-top: 8px; font-size: .7rem; color: #94a3b8;
    font-weight: 600; text-align: right;
}
/* Dropdown preview — realistic select */
.fb-preview-dd__box {
    display: flex; justify-content: space-between; align-items: center;
    padding: 12px 16px; border: 1.5px solid #c4b5fd; border-radius: 10px;
    font-size: .85rem; color: #6366f1; background: #fff;
    box-shadow: 0 0 0 3px rgba(99,102,241,.1); cursor: default;
}
.fb-preview-dd__menu {
    margin-top: 6px; border: 1px solid #e2e8f0; border-radius: 10px;
    overflow: hidden; box-shadow: 0 8px 24px rgba(0,0,0,.08);
}
.fb-preview-dd__item {
    display: flex; justify-content: space-between; align-items: center;
    padding: 10px 16px; font-size: .82rem; color: #475569; background: #fff;
    border-bottom: 1px solid #f1f5f9; cursor: default;
}
.fb-preview-dd__item:last-child { border-bottom: none; }
.fb-preview-dd__item--sel { color: #6366f1; background: rgba(99,102,241,.05); font-weight: 600; }
.fb-preview-dd__item--sel i { font-size: .8rem; }
.fb-preview-dd__item--ph { opacity: 0.5; }
.fb-preview-dd__menu--ph { border-style: dashed; }
.fb-preview__empty { font-size: .8rem; color: #cbd5e1; font-style: italic; padding: 8px 0; }

/* ── Mobile ── */
@media (max-width: 768px) {
    .fb-preview { display: none; }
    .fb-q-expanded__body { flex-direction: column; gap: 0; padding: 12px; }
    .fb-q-editor { width: 100%; }
    .wca-radio-cards { flex-direction: column; gap: 6px; }
    /* Scale card: each field full-width, proper sizing */
    .fb-scale-card__body { flex-direction: column; gap: 10px; padding: 14px; }
    .fb-scale-card__sep { display: none; }
    .fb-scale-card__field { width: 100%; }
    .fb-scale-card__field .el-input-number { width: 100% !important; }
    .fb-scale-card__foot { flex-direction: column; gap: 6px; align-items: flex-start; }
    .fb-q-footer { flex-direction: column; gap: 8px; align-items: stretch; }
    .pkg-row__act { flex-wrap: wrap; }
}

/* ── Assignment Modal ── */
.fb-assign-info {
    display: flex; gap: 10px; padding: 12px 16px; background: #eff6ff;
    border: 1px solid #bfdbfe; border-radius: 10px; margin-bottom: 8px;
    font-size: .78rem; color: #3b82f6; line-height: 1.5;
}
.fb-assign-info i { font-size: 1rem; flex-shrink: 0; margin-top: 1px; }
.fb-assign-empty { display: flex; align-items: center; gap: 10px; padding: 14px; background: #fefce8; border: 1px solid #fde68a; border-radius: 10px; font-size: .8rem; color: #a16207; }
.fb-assign-empty__icon { font-size: 1.5rem; }
.fb-assign-card {
    display: flex; align-items: center; gap: 12px; padding: 12px 14px;
    background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; margin-top: 8px;
}
.fb-assign-card--off { opacity: 0.5; background: #f8fafc; }
.fb-assign-card__icon { font-size: 1.3rem; color: #6366f1; width: 32px; text-align: center; flex-shrink: 0; }
.fb-assign-card__body { flex: 1; }
.fb-assign-card__title { font-size: .85rem; font-weight: 600; color: #1e293b; }
.fb-assign-card__meta { display: flex; gap: 8px; align-items: center; margin-top: 3px; }
.fb-assign-card__badge { font-size: .65rem; font-weight: 700; padding: 2px 8px; border-radius: 4px; text-transform: uppercase; letter-spacing: .05em; }
.fb-assign-card__badge--general { background: #ede9fe; color: #7c3aed; }
.fb-assign-card__badge--specific { background: #d1fae5; color: #059669; }
.fb-assign-card__status { font-size: .72rem; font-weight: 600; }
.fb-assign-card__status--on { color: #10b981; }
.fb-assign-card__status--off { color: #94a3b8; }
.fb-assign-form__program { margin-top: 12px; }
.fb-assign-form__program :deep(.el-select__tags) { max-height: 120px; overflow-y: auto; }
.fb-assign-form__program :deep(.el-tag) { max-width: 180px; overflow: hidden; text-overflow: ellipsis; }
.fb-assign-form__submit {
    display: block; width: 100%; margin-top: 12px; padding: 12px;
    border: none; border-radius: 10px; background: linear-gradient(135deg,#6366f1,#4f46e5);
    color: #fff; font-size: .85rem; font-weight: 700; cursor: pointer; transition: opacity .15s;
}
.fb-assign-form__submit:disabled { opacity: .45; cursor: not-allowed; }
.fb-assign-form__submit:hover:not(:disabled) { opacity: .9; }
.fb-assign-form { margin-top: 6px; }
.fb-assign-form__type { display: flex; gap: 10px; }
.fb-assign-type-card {
    flex: 1; display: flex; align-items: flex-start; gap: 10px; padding: 14px;
    border: 2px solid #e2e8f0; border-radius: 12px; cursor: pointer; transition: all .15s;
    background: #fff;
}
.fb-assign-type-card:hover { border-color: #a5b4fc; }
.fb-assign-type-card--active { border-color: #6366f1; background: rgba(99,102,241,.04); }
.fb-assign-type-card i { font-size: 1.3rem; color: #6366f1; flex-shrink: 0; margin-top: 1px; }
.fb-assign-type-card strong { display: block; font-size: .84rem; color: #1e293b; }
.fb-assign-type-card small { display: block; font-size: .72rem; color: #94a3b8; margin-top: 2px; line-height: 1.4; }

/* ── Footer Action Bar ── */
.fb-q-footer {
    display: flex; justify-content: space-between; align-items: center; gap: 12px;
    padding: 12px 16px; background: #fafbfc; border: 1px solid #e2e8f0;
    border-radius: 12px; margin-top: 16px; flex-wrap: wrap;
}
.fb-q-footer__left { flex-shrink: 0; }
.fb-q-footer__add {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 8px 16px; border: 1.5px solid #e2e8f0; border-radius: 8px;
    background: #fff; color: #64748b; font-size: .82rem; font-weight: 600;
    cursor: pointer; transition: all .15s;
}
.fb-q-footer__add:hover { border-color: #6366f1; color: #6366f1; background: rgba(99,102,241,.03); }
.fb-q-footer__right { display: flex; align-items: center; gap: 14px; }
.fb-q-footer__info { font-size: .72rem; color: #94a3b8; font-weight: 600; white-space: nowrap; }
</style>
