<template>
  <div class="fb-nps">
    <div class="fb-nps__buttons">
      <button
        v-for="s in npsButtons"
        :key="s"
        class="fb-nps__btn"
        :class="{ 'fb-nps__btn--active': modelValue === s }"
        :style="{ background: npsColor(s) }"
        @click="$emit('update:modelValue', s)"
        type="button"
      >{{ s }}</button>
    </div>
    <div class="fb-nps__labels">
      <span>{{ pertanyaan?.Label_Min || 'Tidak mungkin' }}</span>
      <span>{{ pertanyaan?.Label_Max || 'Sangat mungkin' }}</span>
    </div>
  </div>
</template>
<script setup>
import { computed } from 'vue'
const props = defineProps({ pertanyaan: Object, modelValue: Number })
defineEmits(['update:modelValue'])
const npsButtons = computed(() => {
  const min = props.pertanyaan?.Skala_Min ?? 0
  const max = props.pertanyaan?.Skala_Max ?? 10
  const arr = []; for (let i = min; i <= max; i++) arr.push(i); return arr
})
function npsColor(s) {
  if (s <= 2) return 'rgba(239,68,68,0.85)'
  if (s <= 4) return 'rgba(239,68,68,0.55)'
  if (s <= 6) return 'rgba(245,158,11,0.55)'
  if (s <= 8) return 'rgba(34,197,94,0.55)'
  return 'rgba(34,197,94,0.85)'
}
</script>
<style scoped>
.fb-nps { text-align: center; }
.fb-nps__buttons { display: flex; gap: 5px; flex-wrap: wrap; justify-content: center; }
.fb-nps__btn {
  width: 40px; height: 40px; border: none; border-radius: 10px; color: #fff;
  font-weight: 700; font-size: .9rem; cursor: pointer; opacity: .55;
  transition: all .2s cubic-bezier(.4,0,.2,1); outline: none;
  -webkit-tap-highlight-color: transparent; user-select: none;
}
.fb-nps__btn:hover { opacity: .8; transform: scale(1.1); }
.fb-nps__btn--active { opacity: 1; transform: scale(1.15); box-shadow: 0 3px 12px rgba(0,0,0,.2); }
.fb-nps__labels { display: flex; justify-content: space-between; margin-top: 10px; font-size: .75rem; color: #94a3b8; font-weight: 600; }
</style>
