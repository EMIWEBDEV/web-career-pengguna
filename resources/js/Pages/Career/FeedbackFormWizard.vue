<template>
  <div class="fb-wz-container">
    <!-- ═══ WIZARD ULTRA-PREMIUM HERO HEADER ═══ -->
    <div class="fb-wz-hero-card mb-4 text-center">
      <div class="fb-wz-hero-inner">
        <div class="fb-wz-hero-icon-wrap mb-2">
          <div class="fb-wz-hero-icon">
            <span>{{ isWajib ? '🎉' : '💬' }}</span>
          </div>
        </div>
        
        <h2 class="fb-wz-hero-title">
          {{ isWajib ? 'Selamat, Kamu Diterima!' : 'Bantu Kami Lebih Baik' }}
        </h2>
        
        <p class="fb-wz-hero-sub">
          {{ isWajib ? 'Mohon isi feedback singkat ini untuk menyelesaikan proses lamaranmu.' : 'Isi feedback singkat tentang pengalamanmu agar kami bisa terus meningkatkan kualitas proses seleksi.' }}
        </p>

        <div class="fb-wz-hero-chips d-flex align-items-center justify-content-center gap-2 mt-3 flex-wrap">
          <span class="fb-wz-chip">
            <i class="bi bi-clock-history me-1 text-indigo"></i> ~2 Menit
          </span>
          <span class="fb-wz-chip">
            <i class="bi bi-lightning-charge-fill me-1 text-emerald"></i> Praktis &amp; Cepat
          </span>
          <span class="fb-wz-chip">
            <i class="bi bi-shield-lock-fill me-1 text-amber"></i> 100% Rahasia
          </span>
        </div>
      </div>
    </div>

    <!-- ═══ HEADER STEPPER & PROGRESS TRACKER ═══ -->
    <div class="fb-wz-stepper-box mb-4">
      <div class="d-flex align-items-center justify-content-between mb-2">
        <div class="d-flex align-items-center gap-2">
          <span class="wca-badge wca-b--indigo py-1 px-3">
            <i class="bi bi-stars me-1 text-amber"></i> Pertanyaan {{ currentStep + 1 }} dari {{ totalSteps }}
          </span>
          <span v-if="currentPertanyaan?.Flag_Wajib === 'Y' || isWajib" class="wca-badge wca-b--rose py-1 px-2">
            Wajib ✨
          </span>
        </div>
        <span class="fb-wz-pct-badge font-bold text-xs text-indigo">
          {{ progressPercent }}% Selesai
        </span>
      </div>

      <!-- Step Dots Line -->
      <div class="fb-wz-dots-row my-3">
        <button
          v-for="(_, idx) in pertanyaan"
          :key="idx"
          type="button"
          :class="[
            'fb-wz-dot-btn',
            {
              'fb-wz-dot-btn--active': idx === currentStep,
              'fb-wz-dot-btn--done': isStepAnswered(idx),
              'fb-wz-dot-btn--passed': idx < currentStep && !isStepAnswered(idx),
              'fb-wz-dot-btn--future': idx > currentStep && !isStepAnswered(idx)
            }
          ]"
          @click="goToStep(idx)"
          :title="`Pertanyaan ${idx + 1}: ${pertanyaan[idx]?.Label || ''}`"
        >
          <span v-if="isStepAnswered(idx)" class="fb-wz-dot-icon">✓</span>
          <span v-else>{{ idx + 1 }}</span>
        </button>
      </div>

      <!-- Progress Fill Bar -->
      <div class="fb-wz-track-bar">
        <div
          class="fb-wz-track-fill"
          :style="{ width: progressPercent + '%' }"
        ></div>
      </div>
    </div>

    <!-- ═══ QUESTION CARD SYSTEM ═══ -->
    <Transition :name="slideTransition" mode="out-in">
      <div class="fb-wz-card" :key="currentStep">
        <div class="fb-wz-card__body">
          <!-- Question Header Meta -->
          <div class="fb-wz-card__head d-flex align-items-center justify-content-between gap-2 mb-3">
            <div class="d-flex align-items-center gap-2">
              <span class="fb-wz-q-num">Q{{ currentStep + 1 }}</span>
              <span class="wca-badge wca-b--indigo">
                <i class="bi" :class="typeIcon(currentPertanyaan?.Tipe)"></i>
                <span class="ms-1">{{ typeLabel(currentPertanyaan?.Tipe) }}</span>
              </span>
            </div>
            <span class="text-xxs text-muted font-semibold d-none d-sm-inline">Tekan Enter ↵ untuk lanjut</span>
          </div>

          <!-- Question Label -->
          <h3 class="fb-wz-card__label mb-4 text-center">
            {{ currentPertanyaan?.Label }}
          </h3>

          <!-- Dynamic Input Component -->
          <div class="fb-wz-card__input-body py-3">
            <component
              :is="inputComponent(currentPertanyaan?.Tipe)"
              :pertanyaan="currentPertanyaan"
              :model-value="jawaban[currentPertanyaan?.Id_Master_Feedback_Pertanyaan]"
              @update:model-value="handleValueUpdate"
            />
          </div>
        </div>

        <!-- Integrated Card Footer Navigation -->
        <div class="fb-wz-card__footer d-flex align-items-center justify-content-between gap-3 mt-4 pt-3 border-top">
          <div>
            <button
              v-if="currentStep > 0"
              type="button"
              class="fb-wz-btn fb-wz-btn--prev"
              @click="prevStep"
            >
              <i class="bi bi-arrow-left me-1"></i> Sebelumnya
            </button>
            <span v-else class="text-xs text-slate-400 d-none d-sm-inline">
              <i class="bi bi-info-circle me-1 text-indigo"></i> Pilih jawaban di atas untuk lanjut
            </span>
          </div>

          <div>
            <button
              v-if="currentStep < totalSteps - 1"
              type="button"
              class="fb-wz-btn fb-wz-btn--next"
              :disabled="!canAdvance"
              :onClick="!canAdvance ? null : nextStep"
            >
              Selanjutnya <i class="bi bi-arrow-right ms-1"></i>
            </button>

            <button
              v-else
              type="button"
              class="fb-wz-btn fb-wz-btn--submit"
              :disabled="!isComplete || submitting"
              :title="!isComplete ? 'Mohon isi semua pertanyaan terlebih dahulu' : 'Kirim Feedback'"
              :onClick="!isComplete || submitting ? null : submitWizard"
            >
              <span v-if="submitting" class="spinner-border spinner-border-sm me-1.5" role="status" aria-hidden="true"></span>
              <span>{{ submitting ? 'Mengirim...' : 'Kirim Feedback ✨' }}</span>
            </button>
          </div>
        </div>
      </div>
    </Transition>

    <!-- Error Alert if any -->
    <div v-if="submitError" class="alert alert-danger mt-3 text-center text-xs font-semibold py-2">
      <i class="bi bi-exclamation-triangle-fill me-1"></i> {{ submitError }}
    </div>

    <!-- ═══ REASSURANCE GLASSMORPHISM TRUST BADGE ═══ -->
    <div class="fb-wz-reassurance-wrap mt-4 text-center">
      <div class="fb-wz-trust-pill">
        <div class="fb-wz-trust-left">
          <i class="bi bi-shield-check text-emerald fs-6"></i>
          <span>Masukan Anda tersimpan secara <strong>aman &amp; confidential</strong></span>
        </div>
        <span class="fb-wz-trust-divider"></span>
        <div class="fb-wz-trust-right">
          <i class="bi bi-lock-fill text-indigo"></i>
          <span>SSL Encrypted</span>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import RatingInput from './components/feedback/RatingInput.vue'
import NpsInput from './components/feedback/NpsInput.vue'
import LikertInput from './components/feedback/LikertInput.vue'
import TextareaInput from './components/feedback/TextareaInput.vue'
import RadioCards from './components/feedback/RadioCards.vue'
import CheckboxCards from './components/feedback/CheckboxCards.vue'
import DropdownSelect from './components/feedback/DropdownSelect.vue'

const props = defineProps({
  pertanyaan: {
    type: Array,
    default: () => []
  },
  isWajib: {
    type: Boolean,
    default: false
  },
  submitting: {
    type: Boolean,
    default: false
  },
  submitError: {
    type: String,
    default: null
  }
})

const emit = defineEmits(['submit'])

const currentStep = ref(0)
const jawaban = ref({})
const slideTransition = ref('fb-wz-slide-left')
let autoAdvanceTimer = null

const totalSteps = computed(() => props.pertanyaan?.length || 0)
const currentPertanyaan = computed(() => props.pertanyaan?.[currentStep.value])

function isStepAnswered(idx) {
  const p = props.pertanyaan?.[idx]
  if (!p) return false
  const val = jawaban.value[p.Id_Master_Feedback_Pertanyaan]
  if (Array.isArray(val)) return val.length > 0
  return val !== undefined && val !== null && val !== ''
}

const answeredCount = computed(() => {
  if (!props.pertanyaan) return 0
  return props.pertanyaan.filter((_, idx) => isStepAnswered(idx)).length
})

const progressPercent = computed(() => {
  if (totalSteps.value === 0) return 0
  return Math.round((answeredCount.value / totalSteps.value) * 100)
})

const canAdvance = computed(() => {
  return isStepAnswered(currentStep.value)
})

const isComplete = computed(() => {
  if (!props.pertanyaan || props.pertanyaan.length === 0) return false
  return props.pertanyaan.every((_, idx) => isStepAnswered(idx))
})

function inputComponent(tipe) {
  const map = {
    RATING: RatingInput,
    NPS: NpsInput,
    LIKERT: LikertInput,
    TEXTAREA: TextareaInput,
    RADIO: RadioCards,
    CHECKBOX: CheckboxCards,
    DROPDOWN: DropdownSelect
  }
  return map[tipe] || TextareaInput
}

function typeIcon(tipe) {
  const map = {
    RATING: 'bi-star-fill',
    NPS: 'bi-speedometer2',
    LIKERT: 'bi-bar-chart-fill',
    TEXTAREA: 'bi-pencil-square',
    RADIO: 'bi-ui-radios',
    CHECKBOX: 'bi-ui-checks',
    DROPDOWN: 'bi-caret-down-square-fill'
  }
  return map[tipe] || 'bi-chat-dots-fill'
}

function typeLabel(tipe) {
  const map = {
    RATING: 'Rating Bintang',
    NPS: 'NPS (0-10)',
    LIKERT: 'Skala Kepuasan',
    TEXTAREA: 'Teks Catatan',
    RADIO: 'Pilihan Tunggal',
    CHECKBOX: 'Pilihan Berganda',
    DROPDOWN: 'Menu Pilihan'
  }
  return map[tipe] || 'Pertanyaan'
}

function handleValueUpdate(v) {
  const p = currentPertanyaan.value
  if (!p) return
  jawaban.value[p.Id_Master_Feedback_Pertanyaan] = v

  // Auto advance on single selection input types (RATING, NPS, LIKERT, RADIO)
  if (['RATING', 'NPS', 'LIKERT', 'RADIO'].includes(p.Tipe)) {
    if (autoAdvanceTimer) clearTimeout(autoAdvanceTimer)
    if (currentStep.value < totalSteps.value - 1 && canAdvance.value) {
      autoAdvanceTimer = setTimeout(() => {
        nextStep()
      }, 350)
    }
  }
}

function nextStep() {
  if (currentStep.value < totalSteps.value - 1) {
    slideTransition.value = 'fb-wz-slide-left'
    currentStep.value++
  }
}

function prevStep() {
  if (currentStep.value > 0) {
    slideTransition.value = 'fb-wz-slide-right'
    currentStep.value--
  }
}

function goToStep(idx) {
  if (idx === currentStep.value) return
  slideTransition.value = idx > currentStep.value ? 'fb-wz-slide-left' : 'fb-wz-slide-right'
  currentStep.value = idx
}

function submitWizard() {
  if (!isComplete.value || props.submitting) return
  emit('submit', { ...jawaban.value })
}

function handleKeyDown(e) {
  if (e.key === 'Enter' && !e.shiftKey) {
    if (e.target && e.target.tagName === 'TEXTAREA') return
    if (currentStep.value < totalSteps.value - 1) {
      if (canAdvance.value) nextStep()
    } else {
      if (isComplete.value) submitWizard()
    }
  }
}

onMounted(() => {
  window.addEventListener('keydown', handleKeyDown)
})

onUnmounted(() => {
  window.removeEventListener('keydown', handleKeyDown)
  if (autoAdvanceTimer) clearTimeout(autoAdvanceTimer)
})
</script>

<style scoped>
.fb-wz-container {
  max-width: 660px;
  margin: 0 auto;
  width: 100%;
}

/* ═══ WIZARD HERO HEADER ═══ */
.fb-wz-hero-card {
  background: rgba(255, 255, 255, 0.94);
  backdrop-filter: blur(16px);
  -webkit-backdrop-filter: blur(16px);
  border: 1.5px solid rgba(99, 102, 241, 0.14);
  border-radius: 24px;
  padding: 24px 28px;
  box-shadow: 0 10px 30px rgba(99, 102, 241, 0.06), 0 2px 8px rgba(0, 0, 0, 0.02);
  transition: all 0.3s ease;
}

.fb-wz-hero-card:hover {
  border-color: rgba(99, 102, 241, 0.25);
  box-shadow: 0 14px 36px rgba(99, 102, 241, 0.1);
}

.fb-wz-hero-icon-wrap {
  display: inline-flex;
}

.fb-wz-hero-icon {
  width: 54px;
  height: 54px;
  border-radius: 18px;
  background: linear-gradient(135deg, #eef2ff 0%, #e0e7ff 100%);
  border: 1px solid #c7d2fe;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.7rem;
  box-shadow: 0 6px 18px rgba(99, 102, 241, 0.18);
  animation: fbFloatIcon 4s ease-in-out infinite;
}

@keyframes fbFloatIcon {
  0%, 100% { transform: translateY(0) scale(1); }
  50% { transform: translateY(-4px) scale(1.04); }
}

.fb-wz-hero-title {
  font-size: 1.45rem;
  font-weight: 800;
  color: #0f172a;
  margin-bottom: 6px;
  letter-spacing: -0.015em;
  background: linear-gradient(135deg, #0f172a 0%, #3730a3 100%);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
}

.fb-wz-hero-sub {
  font-size: 0.88rem;
  color: #64748b;
  line-height: 1.6;
  max-width: 520px;
  margin: 0 auto;
}

.fb-wz-hero-chips {
  display: flex;
  gap: 8px;
  justify-content: center;
}

.fb-wz-chip {
  display: inline-flex;
  align-items: center;
  background: rgba(248, 250, 252, 0.9);
  border: 1px solid rgba(226, 232, 240, 0.9);
  padding: 4px 12px;
  border-radius: 20px;
  font-size: 0.76rem;
  font-weight: 650;
  color: #475569;
}

/* ═══ STEPPER & DOTS ═══ */
.fb-wz-stepper-box {
  background: rgba(255, 255, 255, 0.92);
  backdrop-filter: blur(12px);
  border: 1px solid rgba(99, 102, 241, 0.12);
  border-radius: 20px;
  padding: 16px 20px;
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.03);
}

.fb-wz-pct-badge {
  background: #eef2ff;
  padding: 4px 12px;
  border-radius: 20px;
  border: 1px solid #c7d2fe;
}

.fb-wz-dots-row {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
  flex-wrap: wrap;
}

.fb-wz-dot-btn {
  width: 32px;
  height: 32px;
  border-radius: 10px;
  border: none;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 0.82rem;
  font-weight: 800;
  cursor: pointer;
  transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
  outline: none !important;
}

.fb-wz-dot-btn--active {
  background: linear-gradient(135deg, #6366f1, #4f46e5);
  color: #ffffff;
  box-shadow: 0 4px 14px rgba(99, 102, 241, 0.4);
  transform: scale(1.12);
}

.fb-wz-dot-btn--done {
  background: #ecfdf5;
  color: #047857;
  border: 1px solid #a7f3d0;
}

.fb-wz-dot-btn--passed {
  background: #f1f5f9;
  color: #94a3b8;
}

.fb-wz-dot-btn--future {
  background: #f8fafc;
  color: #cbd5e1;
  border: 1px solid #f1f5f9;
}

.fb-wz-dot-btn:hover:not(.fb-wz-dot-btn--active) {
  transform: translateY(-2px);
  background: #e2e8f0;
}

.fb-wz-track-bar {
  height: 6px;
  background: #f1f5f9;
  border-radius: 99px;
  overflow: hidden;
}

.fb-wz-track-fill {
  height: 100%;
  border-radius: 99px;
  background: linear-gradient(90deg, #6366f1 0%, #a78bfa 100%);
  transition: width 0.35s cubic-bezier(0.4, 0, 0.2, 1);
  box-shadow: 0 0 10px rgba(99, 102, 241, 0.3);
}

/* ═══ QUESTION CARD & INTEGRATED FOOTER ═══ */
.fb-wz-card {
  background: rgba(255, 255, 255, 0.95);
  backdrop-filter: blur(16px);
  border: 1.5px solid rgba(99, 102, 241, 0.12);
  border-radius: 24px;
  padding: 28px 26px 20px;
  box-shadow: 0 12px 32px rgba(99, 102, 241, 0.08), 0 2px 8px rgba(0, 0, 0, 0.02);
  min-height: 240px;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
}

.fb-wz-q-num {
  width: 32px;
  height: 32px;
  border-radius: 10px;
  background: linear-gradient(135deg, #6366f1, #4f46e5);
  color: #ffffff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 800;
  font-size: 0.85rem;
  box-shadow: 0 3px 10px rgba(99, 102, 241, 0.3);
}

.fb-wz-card__label {
  font-size: 1.15rem;
  font-weight: 700;
  color: #0f172a;
  line-height: 1.5;
  letter-spacing: -0.01em;
}

.fb-wz-card__footer {
  border-top-color: rgba(226, 232, 240, 0.8) !important;
}

/* ═══ BUTTONS ═══ */
.fb-wz-btn {
  padding: 12px 24px;
  border-radius: 14px;
  font-size: 0.9rem;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
  border: none;
  outline: none !important;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}

.fb-wz-btn--prev {
  background: #f1f5f9;
  color: #475569;
  border: 1px solid #cbd5e1;
}

.fb-wz-btn--prev:hover {
  background: #e2e8f0;
  color: #0f172a;
  transform: translateX(-2px);
}

.fb-wz-btn--next {
  background: linear-gradient(135deg, #6366f1, #4f46e5);
  color: #ffffff;
  box-shadow: 0 8px 20px rgba(99, 102, 241, 0.3);
}

.fb-wz-btn--next:not(:disabled):hover {
  transform: translateX(2px);
  box-shadow: 0 12px 26px rgba(99, 102, 241, 0.4);
}

.fb-wz-btn--submit {
  position: relative;
  overflow: hidden;
  background: linear-gradient(135deg, #10b981 0%, #059669 100%);
  color: #ffffff;
  padding: 13px 28px;
  box-shadow: 0 10px 24px rgba(16, 185, 129, 0.35);
}

.fb-wz-btn--submit:not(:disabled):hover {
  transform: translateY(-2px);
  box-shadow: 0 14px 30px rgba(16, 185, 129, 0.45);
}

.fb-wz-btn:disabled,
.fb-wz-btn--submit:disabled,
.fb-wz-btn--next:disabled {
  background: #f1f5f9 !important;
  background-image: none !important;
  color: #94a3b8 !important;
  border: 1px solid #e2e8f0 !important;
  box-shadow: none !important;
  opacity: 0.55 !important;
  cursor: not-allowed !important;
  transform: none !important;
  filter: grayscale(1) !important;
  pointer-events: none !important;
}

.fb-wz-btn:disabled::after,
.fb-wz-btn--submit:disabled::after,
.fb-wz-btn--next:disabled::after {
  display: none !important;
}

.fb-wz-btn:disabled:hover,
.fb-wz-btn--submit:disabled:hover,
.fb-wz-btn--next:disabled:hover {
  background: #f1f5f9 !important;
  color: #94a3b8 !important;
  transform: none !important;
  box-shadow: none !important;
}

/* ═══ REASSURANCE ELEGANT TRUST PILL BADGE ═══ */
.fb-wz-reassurance-wrap {
  display: flex;
  justify-content: center;
  margin-top: 20px;
}

.fb-wz-trust-pill {
  display: inline-flex;
  align-items: center;
  gap: 12px;
  padding: 8px 18px;
  border-radius: 30px;
  background: rgba(255, 255, 255, 0.88);
  backdrop-filter: blur(14px);
  -webkit-backdrop-filter: blur(14px);
  border: 1px solid rgba(99, 102, 241, 0.12);
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.03);
  font-size: 0.78rem;
  color: #475569;
  transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
  max-width: 100%;
}

.fb-wz-trust-pill:hover {
  background: #ffffff;
  border-color: rgba(99, 102, 241, 0.25);
  box-shadow: 0 6px 20px rgba(99, 102, 241, 0.08);
  transform: translateY(-1px);
}

.fb-wz-trust-left,
.fb-wz-trust-right {
  display: flex;
  align-items: center;
  gap: 6px;
  font-weight: 600;
  white-space: nowrap;
}

.fb-wz-trust-divider {
  width: 1px;
  height: 14px;
  background: #cbd5e1;
  flex-shrink: 0;
}

@media (max-width: 576px) {
  .fb-wz-trust-pill {
    flex-direction: column;
    gap: 6px;
    border-radius: 16px;
    padding: 10px 16px;
  }
  .fb-wz-trust-divider {
    display: none;
  }
}

/* ═══ SLIDE TRANSITIONS ═══ */
.fb-wz-slide-left-enter-active,
.fb-wz-slide-left-leave-active,
.fb-wz-slide-right-enter-active,
.fb-wz-slide-right-leave-active {
  transition: all 0.28s cubic-bezier(0.16, 1, 0.3, 1);
}

.fb-wz-slide-left-enter-from {
  opacity: 0;
  transform: translateX(36px);
}
.fb-wz-slide-left-leave-to {
  opacity: 0;
  transform: translateX(-36px);
}

.fb-wz-slide-right-enter-from {
  opacity: 0;
  transform: translateX(-36px);
}
.fb-wz-slide-right-leave-to {
  opacity: 0;
  transform: translateX(36px);
}
</style>
