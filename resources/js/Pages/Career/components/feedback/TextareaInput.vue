<template>
  <div class="fb-textarea">
    <textarea
      :value="modelValue"
      @input="$emit('update:modelValue', $event.target.value)"
      :maxlength="500"
      rows="4"
      class="fb-textarea__input"
      placeholder="Tulis saran atau masukanmu di sini..."
    ></textarea>
    <div class="fb-textarea__counter">{{ (modelValue || '').length }}/500</div>
  </div>
</template>
<script setup>
defineProps({ pertanyaan: Object, modelValue: String })
defineEmits(['update:modelValue'])
</script>
<style scoped>
.fb-textarea { position: relative; }
.fb-textarea__input { width: 100%; padding: 12px 14px; border: 1.5px solid #e2e8f0; border-radius: 10px; font-size: 14px; font-family: 'Inter', sans-serif; resize: vertical; line-height: 1.5; color: #334155; transition: border-color 0.15s; }
.fb-textarea__input:focus { outline: none; border-color: #6366f1; }
.fb-textarea__counter { text-align: right; font-size: 11px; color: #94a3b8; margin-top: 4px; }
</style>
