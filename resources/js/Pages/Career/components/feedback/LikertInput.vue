<template>
  <div class="fb-likert">
    <div class="fb-likert__options">
      <label v-for="(label, i) in ['STS','TS','N','S','SS']" :key="i"
             :class="['fb-likert__opt', { 'fb-likert__opt--active': modelValue === i+1 }]">
        <input type="radio" :value="i+1" :checked="modelValue === i+1"
               @change="$emit('update:modelValue', i+1)" class="fb-likert__radio" />
        <span>{{ label }}</span>
      </label>
    </div>
    <div class="fb-likert__labels">
      <span>{{ pertanyaan?.Label_Min || 'Sangat Tidak Setuju' }}</span>
      <span>{{ pertanyaan?.Label_Max || 'Sangat Setuju' }}</span>
    </div>
  </div>
</template>
<script setup>
defineProps({ pertanyaan: Object, modelValue: Number })
defineEmits(['update:modelValue'])
</script>
<style scoped>
.fb-likert__options { display: flex; gap: 8px; justify-content: center; }
.fb-likert__opt { display: flex; flex-direction: column; align-items: center; gap: 4px; padding: 10px 14px; border: 2px solid #e2e8f0; border-radius: 10px; cursor: pointer; transition: all 0.15s; font-size: 13px; font-weight: 600; color: #94a3b8; }
.fb-likert__opt--active { border-color: #6366f1; background: rgba(99,102,241,0.06); color: #6366f1; }
.fb-likert__radio { display: none; }
.fb-likert__labels { display: flex; justify-content: space-between; margin-top: 8px; font-size: 11px; color: #94a3b8; }
</style>
