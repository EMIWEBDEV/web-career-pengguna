<!-- ══════════════════════════════════════════════════════════
     WEB CAREER — Baris aksi di dalam panel jawaban FAQ: menyalin tautan
     langsung ke pertanyaan ini. Dipakai lewat slot #aksi di FaqAccordion.
     ══════════════════════════════════════════════════════════ -->
<template>
    <div class="fa">
        <button type="button" class="fa-salin" :class="{ 'is-ok': tersalin }" @click="$emit('salin', item.slug)">
            <i class="bi" :class="tersalin ? 'bi-check2-circle' : 'bi-link-45deg'"></i>
            <span>{{ tersalin ? 'Tautan Disalin!' : 'Salin Tautan' }}</span>
        </button>
    </div>
</template>

<script setup>
defineProps({
    item: { type: Object, required: true },
    tersalin: { type: Boolean, default: false },
});

defineEmits(['salin']);
</script>

<style scoped>
.fa {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: flex-end;
    gap: 0.85rem;
    margin-top: 1.25rem;
    padding-top: 1rem;
    border-top: 1px dashed rgba(226, 232, 240, 0.95);
}
.fa-salin {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.4rem 0.9rem;
    border-radius: 999px;
    border: 1.5px solid rgba(99, 102, 241, 0.25);
    background: rgba(99, 102, 241, 0.06);
    color: #4f46e5;
    font-size: 0.82rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.2s ease;
}
.fa-salin i {
    font-size: 0.95rem;
}
.fa-salin:hover {
    background: #4f46e5;
    color: #ffffff;
    border-color: #4f46e5;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(79, 70, 229, 0.25);
}
.fa-salin.is-ok {
    border-color: rgba(16, 185, 129, 0.45);
    background: #10b981;
    color: #ffffff;
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.25);
}
</style>
