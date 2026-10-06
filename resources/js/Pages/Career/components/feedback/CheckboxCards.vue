<template>
  <div class="fb-checkbox-stack">
    <div class="text-xxs font-semibold text-slate-400 mb-1 px-1 d-flex align-items-center justify-content-between">
      <span>Pilih satu atau lebih jawaban:</span>
      <span v-if="(modelValue || []).length > 0" class="fb-checkbox-counter">
        {{ modelValue.length }} Dipilih
      </span>
    </div>
    <button
      v-for="(opt, i) in (pertanyaan?.Opsi || [])"
      :key="i"
      type="button"
      :class="['fb-checkbox-card', { 'fb-checkbox-card--active': isSelected(opt) }]"
      @click="toggle(opt)"
    >
      <div class="fb-checkbox-card__left">
        <span class="fb-checkbox-card__badge">{{ optionKey(i) }}</span>
        <span class="fb-checkbox-card__label">{{ opt }}</span>
      </div>
      <div class="fb-checkbox-card__indicator">
        <i class="bi" :class="isSelected(opt) ? 'bi-check-square-fill fb-checkbox-icon--active' : 'bi-square text-slate-300'"></i>
      </div>
    </button>
  </div>
</template>

<script setup>
const props = defineProps({ pertanyaan: Object, modelValue: Array })
const emit = defineEmits(['update:modelValue'])

function isSelected(opt) {
  return (props.modelValue || []).includes(opt)
}

function toggle(opt) {
  const current = props.modelValue || []
  const next = current.includes(opt) ? current.filter(o => o !== opt) : [...current, opt]
  emit('update:modelValue', next)
}

function optionKey(idx) {
  return String.fromCharCode(65 + idx)
}
</script>

<style scoped>
.fb-checkbox-stack {
  display: flex;
  flex-direction: column;
  gap: 10px;
  width: 100%;
}

.fb-checkbox-counter {
  color: #6366f1;
  font-weight: 700;
  background: #eef2ff;
  padding: 2px 10px;
  border-radius: 12px;
  border: 1px solid #c7d2fe;
}

.fb-checkbox-card {
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

.fb-checkbox-card:hover:not(.fb-checkbox-card--active) {
  border-color: #c7d2fe;
  background: #ffffff;
  transform: translateY(-1px);
  box-shadow: 0 4px 14px rgba(99, 102, 241, 0.08);
}

.fb-checkbox-card--active {
  border-color: #6366f1;
  background: linear-gradient(135deg, rgba(238, 242, 255, 0.95) 0%, rgba(224, 231, 255, 0.95) 100%);
  box-shadow: 0 6px 20px rgba(99, 102, 241, 0.16);
}

.fb-checkbox-card__left {
  display: flex;
  align-items: center;
  gap: 12px;
  flex: 1;
}

.fb-checkbox-card__badge {
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

.fb-checkbox-card--active .fb-checkbox-card__badge {
  background: linear-gradient(135deg, #6366f1, #4f46e5);
  color: #ffffff;
  box-shadow: 0 2px 8px rgba(99, 102, 241, 0.35);
}

.fb-checkbox-card__label {
  font-size: 0.92rem;
  font-weight: 600;
  color: #334155;
  line-height: 1.45;
}

.fb-checkbox-card--active .fb-checkbox-card__label {
  color: #312e81;
  font-weight: 700;
}

.fb-checkbox-card__indicator {
  font-size: 1.3rem;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.fb-checkbox-icon--active {
  color: #6366f1;
  filter: drop-shadow(0 2px 6px rgba(99, 102, 241, 0.4));
}
</style>
