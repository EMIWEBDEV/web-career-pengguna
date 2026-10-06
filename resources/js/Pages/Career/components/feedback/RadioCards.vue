<template>
  <div class="fb-radio-stack">
    <button
      v-for="(opt, i) in (pertanyaan?.Opsi || [])"
      :key="i"
      type="button"
      :class="['fb-radio-card', { 'fb-radio-card--active': modelValue === opt }]"
      @click="$emit('update:modelValue', opt)"
    >
      <div class="fb-radio-card__left">
        <span class="fb-radio-card__badge">{{ optionKey(i) }}</span>
        <span class="fb-radio-card__label">{{ opt }}</span>
      </div>
      <div class="fb-radio-card__indicator">
        <i class="bi" :class="modelValue === opt ? 'bi-record-circle-fill fb-radio-icon--active' : 'bi-circle text-slate-300'"></i>
      </div>
    </button>
  </div>
</template>

<script setup>
defineProps({ pertanyaan: Object, modelValue: String })
defineEmits(['update:modelValue'])

function optionKey(idx) {
  return String.fromCharCode(65 + idx)
}
</script>

<style scoped>
.fb-radio-stack {
  display: flex;
  flex-direction: column;
  gap: 10px;
  width: 100%;
}

.fb-radio-card {
  width: 100%;
  padding: 14px 18px;
  border: 1.5px solid #e2e8f0;
  border-radius: 16px;
  background: rgba(255, 255, 255, 0.92);
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 14px;
  cursor: pointer;
  text-align: left;
  transition: all 0.22s cubic-bezier(0.16, 1, 0.3, 1);
  outline: none !important;
  user-select: none;
}

.fb-radio-card:hover:not(.fb-radio-card--active) {
  border-color: #c7d2fe;
  background: #ffffff;
  transform: translateY(-1px);
  box-shadow: 0 4px 14px rgba(99, 102, 241, 0.08);
}

.fb-radio-card--active {
  border-color: #6366f1;
  background: linear-gradient(135deg, rgba(238, 242, 255, 0.95) 0%, rgba(224, 231, 255, 0.95) 100%);
  box-shadow: 0 6px 20px rgba(99, 102, 241, 0.16);
}

.fb-radio-card__left {
  display: flex;
  align-items: center;
  gap: 12px;
  flex: 1;
}

.fb-radio-card__badge {
  width: 28px;
  height: 28px;
  border-radius: 8px;
  background: #f1f5f9;
  color: #64748b;
  font-size: 0.8rem;
  font-weight: 800;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  transition: all 0.2s ease;
}

.fb-radio-card--active .fb-radio-card__badge {
  background: linear-gradient(135deg, #6366f1, #4f46e5);
  color: #ffffff;
  box-shadow: 0 2px 8px rgba(99, 102, 241, 0.35);
}

.fb-radio-card__label {
  font-size: 0.92rem;
  font-weight: 600;
  color: #334155;
  line-height: 1.45;
}

.fb-radio-card--active .fb-radio-card__label {
  color: #312e81;
  font-weight: 700;
}

.fb-radio-card__indicator {
  font-size: 1.3rem;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.fb-radio-icon--active {
  color: #6366f1;
  filter: drop-shadow(0 2px 6px rgba(99, 102, 241, 0.4));
}
</style>
