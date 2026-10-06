<!-- ══════════════════════════════════════════════════════════
     WEB CAREER — Baris aksi di dalam panel jawaban FAQ.
     Dua hal: menyalin tautan langsung ke pertanyaan ini, dan menilai
     apakah jawabannya membantu (bahan HR memperbaiki jawaban yang
     sering dibaca tapi dinilai tidak membantu).
     Dipakai lewat slot #aksi di FaqAccordion.
     ══════════════════════════════════════════════════════════ -->
<template>
    <div class="fa">
        <div class="fa-nilai">
            <template v-if="status === 'selesai'">
                <span class="fa-terima"><i class="bi bi-check-circle-fill"></i> Terima kasih atas penilaian Anda</span>
            </template>
            <template v-else-if="status === 'gagal'">
                <span class="fa-gagal"><i class="bi bi-exclamation-triangle-fill"></i> Penilaian gagal dikirim</span>
            </template>
            <template v-else>
                <span class="fa-tanya">Apakah jawaban ini membantu?</span>
                <div class="fa-vote-btns">
                    <button
                        type="button"
                        class="fa-btn fa-btn--yes"
                        :disabled="status === 'mengirim'"
                        :onClick="status === 'mengirim' ? null : () => $emit('vote', { id: item.id, membantu: true })"
                    >
                        <i class="bi bi-hand-thumbs-up-fill"></i> Ya
                    </button>
                    <button
                        type="button"
                        class="fa-btn fa-btn--no"
                        :disabled="status === 'mengirim'"
                        :onClick="status === 'mengirim' ? null : () => $emit('vote', { id: item.id, membantu: false })"
                    >
                        <i class="bi bi-hand-thumbs-down-fill"></i> Tidak
                    </button>
                </div>
            </template>
        </div>

        <button type="button" class="fa-salin" :class="{ 'is-ok': tersalin }" @click="$emit('salin', item.slug)">
            <i class="bi" :class="tersalin ? 'bi-check2-circle' : 'bi-link-45deg'"></i>
            <span>{{ tersalin ? 'Tautan Disalin!' : 'Salin Tautan' }}</span>
        </button>
    </div>
</template>

<script setup>
defineProps({
    item: { type: Object, required: true },
    // undefined | 'mengirim' | 'selesai' | 'gagal'
    status: { type: String, default: '' },
    tersalin: { type: Boolean, default: false },
});

defineEmits(['vote', 'salin']);
</script>

<style scoped>
.fa {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 0.85rem;
    margin-top: 1.25rem;
    padding-top: 1rem;
    border-top: 1px dashed rgba(226, 232, 240, 0.95);
}
.fa-nilai {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.65rem;
}
.fa-tanya {
    color: #64748b;
    font-size: 0.84rem;
    font-weight: 600;
}
.fa-vote-btns {
    display: flex;
    align-items: center;
    gap: 0.4rem;
}
.fa-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.38rem 0.85rem;
    border-radius: 999px;
    border: 1.5px solid #e2e8f0;
    background: #ffffff;
    color: #475569;
    font-size: 0.8rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}
.fa-btn i {
    font-size: 0.85rem;
}
.fa-btn--yes:hover:not(:disabled) {
    border-color: rgba(16, 185, 129, 0.45);
    background: rgba(16, 185, 129, 0.08);
    color: #059669;
    transform: translateY(-1px);
}
.fa-btn--no:hover:not(:disabled) {
    border-color: rgba(245, 158, 11, 0.45);
    background: rgba(245, 158, 11, 0.08);
    color: #d97706;
    transform: translateY(-1px);
}
.fa-btn:disabled {
    opacity: 0.5;
    cursor: default;
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
.fa-terima {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    padding: 0.35rem 0.85rem;
    border-radius: 999px;
    background: rgba(16, 185, 129, 0.1);
    color: #059669;
    font-size: 0.83rem;
    font-weight: 700;
}
.fa-gagal {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    padding: 0.35rem 0.85rem;
    border-radius: 999px;
    background: rgba(245, 158, 11, 0.1);
    color: #d97706;
    font-size: 0.83rem;
    font-weight: 700;
}
</style>
