<template>
  <el-select
    :model-value="modelValue"
    @update:model-value="$emit('update:modelValue', $event)"
    placeholder="Pilih salah satu..."
    class="fb-dropdown"
    clearable
  >
    <el-option v-for="(opt, i) in (pertanyaan.Opsi || [])" :key="i" :label="opt" :value="opt" />
  </el-select>
</template>
<script setup>
defineProps({ pertanyaan: Object, modelValue: String })
defineEmits(['update:modelValue'])
</script>
<style scoped>
.fb-dropdown { width: 100%; }
</style>
