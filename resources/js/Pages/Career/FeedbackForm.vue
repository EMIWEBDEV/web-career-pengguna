<template>
  <Head title="Masukan Kandidat" />

  <CareerLayout>
      <div class="fb-ambient" aria-hidden="true">
        <span class="aurora aurora--a"></span>
        <span class="aurora aurora--b"></span>
        <span class="aurora aurora--c"></span>
        <div class="grid"></div>
        <span class="twinkle twinkle--1"></span>
        <span class="twinkle twinkle--2"></span>
        <span class="twinkle twinkle--3"></span>
        <span class="twinkle twinkle--4"></span>
        <span class="twinkle twinkle--5"></span>
        <span class="bokeh bokeh--a"></span>
        <span class="bokeh bokeh--b"></span>
        <i class="bi bi-star-fill glyph glyph--1"></i>
        <i class="bi bi-chat-dots-fill glyph glyph--2"></i>
        <i class="bi bi-clipboard-check-fill glyph glyph--3"></i>
        <i class="bi bi-send-fill glyph glyph--4"></i>
        <i class="bi bi-hand-thumbs-up-fill glyph glyph--5"></i>
        <i class="bi bi-lightbulb-fill glyph glyph--6"></i>
        <i class="bi bi-emoji-smile-fill glyph glyph--7"></i>
        <i class="bi bi-pencil-fill glyph glyph--8"></i>
        <div class="particles">
          <span class="pt pt--1"></span><span class="pt pt--2"></span><span class="pt pt--3"></span>
          <span class="pt pt--4"></span><span class="pt pt--5"></span><span class="pt pt--6"></span>
        </div>
      </div>

      <div class="fb-page">
        <div v-if="success || error==='already_submitted'" class="fb-glass fb-glass--center">
          <div class="fb-glass__check">✓</div>
          <h1>Feedback Terkirim!</h1>
          <p>Terima kasih — masukanmu sangat berarti untuk kami terus berkembang.</p>
          <p v-if="is_wajib" class="fb-glass__note">Mengarahkan ke Portal Kandidat dalam 3 detik...</p>
        </div>
        <div v-else-if="error" class="fb-glass fb-glass--center">
          <div class="fb-glass__icon"><i :class="errorIcon"></i></div>
          <h1>{{ stateTitle }}</h1>
          <p>{{ message }}</p>
          <a :href="error==='already_submitted'||error==='expired'?'/kandidat/portal':'/'" class="fb-glass__btn">
            {{ error==='already_submitted'||error==='expired'?'Portal Kandidat →':'← Beranda' }}
          </a>
        </div>
        <div v-else class="fb-main">
          <!-- SCROLL MODE: CLEAN UNIFIED CANVAS -->
          <template v-if="mode_tampilan === 'SCROLL'">
            <!-- HERO HEADER CARD -->
            <div class="fb-wz-hero-card mb-4 text-center">
              <div class="fb-wz-hero-inner">
                <div class="fb-wz-hero-icon-wrap mb-2">
                  <div class="fb-wz-hero-icon">
                    <span>{{ is_wajib ? '🎉' : '💬' }}</span>
                  </div>
                </div>
                
                <h2 class="fb-wz-hero-title">
                  {{ is_wajib ? 'Selamat, Kamu Diterima!' : 'Bantu Kami Lebih Baik' }}
                </h2>
                
                <p class="fb-wz-hero-sub">
                  {{ is_wajib ? 'Mohon isi feedback singkat ini untuk menyelesaikan proses lamaranmu.' : 'Isi feedback singkat tentang pengalamanmu agar kami bisa terus meningkatkan kualitas proses seleksi.' }}
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

            <!-- STICKY PROGRESS TRACKER -->
            <div class="fb-scroll-progress mb-4">
              <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="wca-badge wca-b--indigo py-1 px-3">
                  <i class="bi bi-list-check me-1 text-amber"></i> {{ answeredCount }} dari {{ pertanyaan?.length || 0 }} Pertanyaan Terisi
                </span>
                <span class="fb-wz-pct-badge font-bold text-xs text-indigo">
                  {{ progressPercent }}% Selesai
                </span>
              </div>
              <div class="fb-wz-track-bar">
                <div class="fb-wz-track-fill" :style="{ width: progressPercent + '%' }"></div>
              </div>
            </div>

            <!-- QUESTION CARDS STACK -->
            <div class="fb-cards">
              <div v-for="(p,idx) in pertanyaan" :key="p.Id_Master_Feedback_Pertanyaan" class="fb-wz-card mb-3">
                <div class="fb-wz-card__body">
                  <div class="fb-wz-card__head d-flex align-items-center justify-content-between gap-2 mb-3">
                    <div class="d-flex align-items-center gap-2">
                      <span class="fb-wz-q-num">Q{{ idx + 1 }}</span>
                      <span class="wca-badge wca-b--indigo">
                        <i class="bi" :class="typeIcon(p.Tipe)"></i>
                        <span class="ms-1">{{ typeLabel(p.Tipe) }}</span>
                      </span>
                    </div>
                    <span v-if="p.Flag_Wajib === 'Y' || is_wajib" class="wca-badge wca-b--rose">Wajib ✨</span>
                  </div>
                  <h3 class="fb-wz-card__label mb-4 text-center">{{ p.Label }}</h3>
                  <div class="fb-wz-card__input-body py-2">
                    <component
                      :is="inputComp(p.Tipe)"
                      :pertanyaan="p"
                      :model-value="jawaban[p.Id_Master_Feedback_Pertanyaan]"
                      @update:model-value="v => jawaban[p.Id_Master_Feedback_Pertanyaan] = v"
                    />
                  </div>
                </div>
              </div>

              <!-- SUBMIT BUTTON SECTION -->
              <div class="text-center mt-4">
                <button
                  type="button"
                  class="fb-wz-btn fb-wz-btn--submit w-100 py-3 text-center"
                  :disabled="!isComplete || submitting"
                  :onClick="!isComplete || submitting ? null : submitFeedback"
                >
                  <span v-if="submitting" class="spinner-border spinner-border-sm me-1.5" role="status" aria-hidden="true"></span>
                  <span>{{ submitting ? 'Mengirim...' : 'Kirim Feedback ✨' }}</span>
                </button>
                <p v-if="submitError" class="alert alert-danger mt-3 text-center text-xs font-semibold py-2">
                  <i class="bi bi-exclamation-triangle-fill me-1"></i> {{ submitError }}
                </p>
              </div>

              <!-- TRUST SHIELD PILL BADGE -->
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

          <!-- WIZARD MODE: CLEAN SINGLE CANVAS -->
          <template v-else>
            <FeedbackFormWizard
              :pertanyaan="pertanyaan"
              :is-wajib="is_wajib"
              :submitting="submitting"
              :submit-error="submitError"
              @submit="handleWizardSubmit"
            />
          </template>
        </div>
      </div>
  </CareerLayout>
</template>

<script setup>
import { ref, computed } from 'vue'; import axios from 'axios'
import { Head } from '@inertiajs/vue3'
import CareerLayout from './Layouts/CareerLayout.vue'; import FeedbackFormWizard from './FeedbackFormWizard.vue'
import './components/AuthShell.vue'; // load global .authx CSS
defineOptions({ layout: null })
import RatingInput from './components/feedback/RatingInput.vue'; import NpsInput from './components/feedback/NpsInput.vue'
import LikertInput from './components/feedback/LikertInput.vue'; import TextareaInput from './components/feedback/TextareaInput.vue'
import RadioCards from './components/feedback/RadioCards.vue'; import CheckboxCards from './components/feedback/CheckboxCards.vue'
import DropdownSelect from './components/feedback/DropdownSelect.vue'

const props = defineProps({ feedback: Object, pertanyaan: Array, is_wajib: Boolean, mode_tampilan: String, error: String, message: String, is_authenticated: Boolean })
const jawaban = ref({}); const submitting = ref(false); const submitError = ref(null); const success = ref(false)
const stateTitle = computed(()=>({invalid:'Link Tidak Valid',expired:'Link Kadaluarsa',already_submitted:'Feedback Sudah Terkirim'}[props.error]||''))
const errorIcon = computed(()=>({invalid:'bi-link-45deg',expired:'bi-hourglass-split',already_submitted:'bi-check-circle-fill'}[props.error]||'bi-exclamation-circle'))

function isAnswered(p) {
  if (!p) return false
  const val = jawaban.value[p.Id_Master_Feedback_Pertanyaan]
  if (Array.isArray(val)) return val.length > 0
  if (typeof val === 'number') return !isNaN(val)
  return val !== undefined && val !== null && String(val).trim() !== ''
}

const answeredCount = computed(() => {
  if (!props.pertanyaan) return 0
  return props.pertanyaan.filter(p => isAnswered(p)).length
})

const isComplete = computed(() => {
  if (!props.pertanyaan || props.pertanyaan.length === 0) return false
  return props.pertanyaan.every(p => isAnswered(p))
})

const progressPercent = computed(() => {
  if (!props.pertanyaan || props.pertanyaan.length === 0) return 0
  return Math.round((answeredCount.value / props.pertanyaan.length) * 100)
})

function inputComp(t){const m={RATING:RatingInput,NPS:NpsInput,LIKERT:LikertInput,TEXTAREA:TextareaInput,RADIO:RadioCards,CHECKBOX:CheckboxCards,DROPDOWN:DropdownSelect};return m[t]||TextareaInput}

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

async function submitFeedback(){
  if(!isComplete.value || submitting.value) return;
  submitting.value = true;
  submitError.value = null;
  try {
    const arr = Object.entries(jawaban.value).map(([id,val])=>({id_pertanyaan:parseInt(id),jawaban:Array.isArray(val)?val:String(val)}))
    const{data}=await axios.post(window.location.pathname,{jawaban:arr})
    if(data.success){
      success.value=true;
      if(data.result?.redirect_to) setTimeout(()=>window.location.href=data.result.redirect_to, 3000)
    }
  } catch(e){
    submitError.value=e.response?.data?.message||'Gagal mengirim. Coba lagi.'
  } finally {
    submitting.value=false
  }
}
function handleWizardSubmit(w){jawaban.value=w;submitFeedback()}
</script>

<style scoped>
/* Ambient */
.fb-ambient { position: fixed; inset: 0; z-index: 0; pointer-events: none; overflow: hidden; }
.fb-ambient .grid { position: absolute; inset: -20%; background-image: linear-gradient(rgba(99,102,241,.045) 1px,transparent 1px),linear-gradient(90deg,rgba(99,102,241,.045) 1px,transparent 1px); background-size: 54px 54px; -webkit-mask:radial-gradient(ellipse at 42% 42%,#000 34%,transparent 74%); mask:radial-gradient(ellipse at 42% 42%,#000 34%,transparent 74%); }
.fb-ambient .aurora { position: absolute; border-radius: 50%; }
.fb-ambient .aurora--a { top: -14%; left: -8%; width: 560px; height: 560px; filter: blur(90px); opacity: .6; background: radial-gradient(circle,rgba(139,92,246,.55),transparent 68%); animation: fb-fl-a 20s ease-in-out infinite; }
.fb-ambient .aurora--b { top: 8%; right: -6%; width: 440px; height: 440px; filter: blur(85px); opacity: .55; background: radial-gradient(circle,rgba(99,102,241,.5),transparent 66%); animation: fb-fl-b 24s ease-in-out 3s infinite reverse; }
.fb-ambient .aurora--c { bottom: -12%; left: 30%; width: 380px; height: 380px; filter: blur(75px); opacity: .45; background: radial-gradient(circle,rgba(245,158,11,.4),transparent 70%); animation: fb-fl-c 26s ease-in-out 5s infinite; }
@keyframes fb-fl-a { 0%,100%{transform:translate(0,0) scale(1)} 33%{transform:translate(50px,-40px) scale(1.1)} 66%{transform:translate(-30px,25px) scale(.9)} }
@keyframes fb-fl-b { 0%,100%{transform:translate(0,0)} 50%{transform:translate(-40px,-25px)} }
@keyframes fb-fl-c { 0%,100%{transform:translate(0,0) scale(1)} 50%{transform:translate(30px,-20px) scale(1.15)} }
.fb-ambient .bokeh { position: absolute; border-radius: 50%; filter: blur(12px); }
.fb-ambient .bokeh--a { top: 18%; left: 55%; width: 160px; height: 160px; background: radial-gradient(circle,rgba(139,92,246,.12),transparent 70%); animation: fb-bk 20s ease-in-out infinite; }
.fb-ambient .bokeh--b { top: 60%; left: 20%; width: 110px; height: 110px; background: radial-gradient(circle,rgba(99,102,241,.1),transparent 70%); animation: fb-bk 25s ease-in-out -6s infinite reverse; }
@keyframes fb-bk { 0%,100%{transform:translate(0,0)} 50%{transform:translate(25px,-15px)} }
.fb-ambient .twinkle { position: absolute; border-radius: 50%; animation: fb-tw 3.8s ease-in-out infinite; }
.fb-ambient .twinkle--1 { top: 22%; left: 46%; width: 5px; height: 5px; background: #c4b5fd; box-shadow:0 0 10px #a78bfa; animation-delay:-.2s; }
.fb-ambient .twinkle--2 { top: 34%; left: 52%; width: 4px; height: 4px; background: #fcd34d; box-shadow:0 0 10px #f59e0b; animation-delay:-1.1s; }
.fb-ambient .twinkle--3 { top: 14%; left: 38%; width: 6px; height: 6px; background: #a5b4fc; box-shadow:0 0 12px #6366f1; animation-delay:-2s; }
.fb-ambient .twinkle--4 { top: 48%; left: 64%; width: 4px; height: 4px; background: #c4b5fd; box-shadow:0 0 8px #8b5cf6; animation-delay:-3.2s; }
.fb-ambient .twinkle--5 { top: 65%; left: 28%; width: 5px; height: 5px; background: #fcd34d; box-shadow:0 0 10px #f59e0b; animation-delay:-.5s; }
@keyframes fb-tw { 0%,100%{opacity:.25;transform:scale(1)} 50%{opacity:1;transform:scale(2.2)} }
.fb-ambient .glyph { position: absolute; display: block; line-height: 1; animation: fb-gly 9s ease-in-out infinite; filter: drop-shadow(0 0 10px currentColor); }
.fb-ambient .glyph--1 { top: 18%; left: 8%; color: rgba(245,158,11,.5); font-size: 36px; animation-delay: 0s; }
.fb-ambient .glyph--2 { top: 38%; right: 6%; color: rgba(99,102,241,.5); font-size: 30px; animation-delay: -2s; }
.fb-ambient .glyph--3 { top: 55%; left: 5%; color: rgba(16,185,129,.45); font-size: 28px; animation-delay: -4s; }
.fb-ambient .glyph--4 { top: 22%; right: 10%; color: rgba(139,92,246,.45); font-size: 32px; animation-delay: -6s; }
.fb-ambient .glyph--5 { top: 62%; right: 8%; color: rgba(99,102,241,.5); font-size: 26px; animation-delay: -1s; }
.fb-ambient .glyph--6 { top: 70%; left: 12%; color: rgba(245,158,11,.45); font-size: 28px; animation-delay: -3s; }
.fb-ambient .glyph--7 { top: 10%; left: 50%; color: rgba(245,158,11,.4); font-size: 24px; animation-delay: -5s; }
.fb-ambient .glyph--8 { top: 75%; right: 15%; color: rgba(99,102,241,.45); font-size: 22px; animation-delay: -7s; }
@keyframes fb-gly { 0%,100%{transform:translateY(0) rotate(0);opacity:.5} 50%{transform:translateY(-25px) rotate(12deg);opacity:1} }
.fb-ambient .particles { position: absolute; inset: 0; }
.fb-ambient .pt { position: absolute; width: 4px; height: 4px; border-radius: 50%; background: #c4b5fd; box-shadow: 0 0 6px #a78bfa; animation: fb-pt 14s linear infinite; }
.fb-ambient .pt--1 { left: 15%; animation-delay: 0s; }
.fb-ambient .pt--2 { left: 35%; animation-delay: -3s; }
.fb-ambient .pt--3 { left: 55%; animation-delay: -6s; }
.fb-ambient .pt--4 { left: 75%; animation-delay: -9s; }
.fb-ambient .pt--5 { left: 25%; animation-delay: -1.5s; }
.fb-ambient .pt--6 { left: 65%; animation-delay: -7.5s; }
@keyframes fb-pt { 0%{transform:translateY(105vh) scale(0);opacity:0} 10%{opacity:.6} 90%{opacity:.6} 100%{transform:translateY(-10vh) scale(1.5);opacity:0} }

/* Page */
.fb-page { position: relative; min-height: 60vh; padding: 7rem 20px 80px; display: flex; flex-direction: column; align-items: center; }
.fb-glass { position: relative; background: rgba(255,255,255,.85); border: 1px solid rgba(0,0,0,.06); border-radius: 28px; box-shadow: 0 4px 24px rgba(0,0,0,.03), 0 1px 4px rgba(0,0,0,.02); }
.fb-glass--center { text-align: center; max-width: 480px; width: 100%; padding: 56px 36px; margin-top: 60px; }
.fb-glass__check { width: 80px; height: 80px; border-radius: 50%; background: linear-gradient(135deg,#10b981,#059669); color: #fff; font-size: 2.2rem; font-weight: 700; display: flex; align-items: center; justify-content: center; margin: 0 auto 24px; box-shadow: 0 12px 32px rgba(16,185,129,.3); animation: fb-pop .5s cubic-bezier(.18,.89,.32,1.28) both; }
@keyframes fb-pop { 0%{transform:scale(0) rotate(-8deg)} 60%{transform:scale(1.15) rotate(3deg)} 100%{transform:scale(1) rotate(0)} }
.fb-glass__icon { font-size: 2.8rem; margin-bottom: 18px; }
.fb-glass h1 { font-size: 1.7rem; font-weight: 800; color: #1e293b; margin: 0 0 12px; letter-spacing: -.01em; }
.fb-glass p { font-size: .92rem; color: #64748b; line-height: 1.65; margin: 0; }
.fb-glass__note { font-size: .8rem!important; color: #94a3b8!important; margin-top: 16px!important; }
.fb-glass__btn { display: inline-block; margin-top: 24px; padding: 13px 30px; background: linear-gradient(135deg,#6366f1,#4f46e5); color: #fff; border-radius: 12px; text-decoration: none; font-weight: 700; font-size: .88rem; transition: transform .15s,box-shadow .15s; }
.fb-glass__btn:hover { transform: translateY(-1px); box-shadow: 0 8px 24px rgba(99,102,241,.3); }

.fb-main { position: relative; z-index: 1; max-width: 660px; width: 100%; display: flex; flex-direction: column; align-items: center; gap: 0; }

/* SCROLL PROGRESS TRACKER */
.fb-scroll-progress {
  width: 100%;
  background: rgba(255, 255, 255, 0.92);
  backdrop-filter: blur(12px);
  border: 1px solid rgba(99, 102, 241, 0.12);
  border-radius: 20px;
  padding: 16px 20px;
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.03);
  position: sticky;
  top: 84px;
  z-index: 10;
}

/* WIZARD HERO HEADER */
.fb-wz-hero-card {
  width: 100%;
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

.fb-wz-pct-badge {
  background: #eef2ff;
  padding: 4px 12px;
  border-radius: 20px;
  border: 1px solid #c7d2fe;
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

.fb-cards { width: 100%; display: flex; flex-direction: column; gap: 0; }

.fb-wz-card {
  background: rgba(255, 255, 255, 0.95);
  backdrop-filter: blur(16px);
  border: 1.5px solid rgba(99, 102, 241, 0.12);
  border-radius: 24px;
  padding: 26px 24px;
  box-shadow: 0 12px 32px rgba(99, 102, 241, 0.08), 0 2px 8px rgba(0, 0, 0, 0.02);
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

/* BUTTONS */
.fb-wz-btn {
  padding: 14px 28px;
  border-radius: 14px;
  font-size: 0.94rem;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
  border: none;
  outline: none !important;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}

.fb-wz-btn--submit {
  position: relative;
  overflow: hidden;
  background: linear-gradient(135deg, #10b981 0%, #059669 100%);
  color: #ffffff;
  box-shadow: 0 10px 24px rgba(16, 185, 129, 0.35);
}

.fb-wz-btn--submit:not(:disabled):hover {
  transform: translateY(-2px);
  box-shadow: 0 14px 30px rgba(16, 185, 129, 0.45);
}

.fb-wz-btn:disabled,
.fb-wz-btn--submit:disabled {
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
.fb-wz-btn--submit:disabled::after {
  display: none !important;
}

/* REASSURANCE ELEGANT TRUST PILL BADGE */
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
</style>
