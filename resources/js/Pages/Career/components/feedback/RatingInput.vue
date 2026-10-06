<template>
  <div class="fb-rating-wrapper text-center">
    <div class="fb-rating d-flex justify-content-center align-items-center gap-2 mb-3">
      <button
        v-for="s in (pertanyaan?.Skala_Max || 5)"
        :key="s"
        class="fb-rating__star"
        :class="{ 'fb-rating__star--active': (hover || modelValue) >= s, 'fb-rating__star--selected': modelValue === s }"
        @click="$emit('update:modelValue', s)"
        @mouseenter="hover = s"
        @mouseleave="hover = 0"
        type="button"
        :title="`Rating ${s}`"
      >
        <i class="bi" :class="(hover || modelValue) >= s ? 'bi-star-fill' : 'bi-star'"></i>
      </button>
    </div>

    <!-- Rating Sentiment Pill -->
    <div v-if="activeValue > 0" class="fb-rating-sentiment">
      <span class="fb-rating-pill" :class="sentimentClass(activeValue)">
        {{ sentimentText(activeValue) }} ({{ activeValue }}/5)
      </span>
    </div>
    <div v-else class="text-xs text-muted">
      Klik bintang di atas untuk memberikan nilai
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'

const props = defineProps({ pertanyaan: Object, modelValue: Number })
defineEmits(['update:modelValue'])

const hover = ref(0)
const activeValue = computed(() => hover.value || props.modelValue || 0)

function sentimentText(val) {
  const map = {
    1: 'Sangat Tidak Memuaskan 😞',
    2: 'Kurang Memuaskan 🙁',
    3: 'Cukup Baik 😐',
    4: 'Memuaskan 🙂',
    5: 'Sangat Memuaskan! 🌟'
  }
  return map[val] || `${val}/5`
}

function sentimentClass(val) {
  if (val >= 5) return 'fb-rating-pill--emerald'
  if (val >= 4) return 'fb-rating-pill--indigo'
  if (val >= 3) return 'fb-rating-pill--amber'
  return 'fb-rating-pill--rose'
}
</script>

<style scoped>
.fb-rating {
  display: flex;
  gap: 10px;
  justify-content: center;
}

.fb-rating__star {
  background: transparent;
  border: none;
  font-size: 2.5rem;
  cursor: pointer;
  padding: 4px 6px;
  color: #cbd5e1;
  transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
  line-height: 1;
  outline: none !important;
  border-radius: 12px;
}

.fb-rating__star:hover {
  transform: scale(1.25) translateY(-2px);
  color: #f59e0b;
}

.fb-rating__star--active {
  color: #f59e0b;
  filter: drop-shadow(0 4px 10px rgba(245, 158, 11, 0.4));
}

.fb-rating__star--selected {
  transform: scale(1.15);
}

.fb-rating-pill {
  display: inline-flex;
  align-items: center;
  padding: 6px 16px;
  border-radius: 20px;
  font-size: 0.85rem;
  font-weight: 700;
  animation: fbPop 0.25s cubic-bezier(0.16, 1, 0.3, 1);
}

.fb-rating-pill--emerald { background: #ecfdf5; color: #047857; border: 1px solid #a7f3d0; }
.fb-rating-pill--indigo { background: #eef2ff; color: #4338ca; border: 1px solid #c7d2fe; }
.fb-rating-pill--amber { background: #fffbeb; color: #b45309; border: 1px solid #fde68a; }
.fb-rating-pill--rose { background: #fff1f2; color: #be123c; border: 1px solid #fecdd3; }

@keyframes fbPop {
  0% { transform: scale(0.9); opacity: 0; }
  100% { transform: scale(1); opacity: 1; }
}
</style>
