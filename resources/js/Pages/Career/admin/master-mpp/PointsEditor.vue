<!-- Editor daftar baris teks (Tanggung Jawab / Persyaratan) — tambah/hapus/edit inline.
     v-model array of string. Dipakai 2x di form MPP dengan UI/UX modern. -->
<template>
    <div class="pted">
        <div class="pted-head">
            <label class="wca-field-lbl">
                <i class="bi" :class="icon"></i> {{ label }}
                <span class="pted-badge" :class="{ 'is-active': list.length > 0 }">
                    {{ list.length }} Poin
                </span>
            </label>
            <button
                v-if="list.length > 1"
                type="button"
                class="pted-clear"
                title="Hapus semua poin"
                @click="clearAll"
            >
                <i class="bi bi-trash3"></i> Reset
            </button>
        </div>

        <div class="pted-box">
            <!-- Empty state illustration -->
            <div v-if="!list.length" class="pted-empty">
                <i class="bi bi-card-checklist"></i>
                <span>Belum ada {{ label.toLowerCase() }}. Ketik pada kolom di bawah lalu tekan <strong>Enter ↵</strong>.</span>
            </div>

            <!-- Existing List Items -->
            <transition-group name="pted-item" tag="div" class="pted-list" v-if="list.length">
                <div v-for="(item, i) in list" :key="i" class="pted-row">
                    <span class="pted-idx">{{ i + 1 }}</span>
                    <input
                        class="pted-input"
                        :value="item"
                        :placeholder="placeholder"
                        :maxlength="maxlength"
                        @input="ubah(i, $event.target.value)"
                        @keyup.enter="fokusBaru"
                    />
                    <button
                        type="button"
                        class="pted-rm"
                        title="Hapus poin ini"
                        @click="hapus(i)"
                    >
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
            </transition-group>

            <!-- Add New Item Row -->
            <div class="pted-add-row" :class="{ 'is-focused': isInputFocused }">
                <i class="bi bi-plus-lg pted-add-icon"></i>
                <input
                    ref="inputBaru"
                    v-model="draft"
                    class="pted-input pted-input--new"
                    :placeholder="`Ketik ${label.toLowerCase()} baru…`"
                    :maxlength="maxlength"
                    @focus="isInputFocused = true"
                    @blur="isInputFocused = false"
                    @keyup.enter="tambah"
                />
                <button
                    type="button"
                    class="pted-add-btn"
                    :disabled="!draft.trim()"
                    :onClick="!draft.trim() ? null : tambah"
                >
                    <i class="bi bi-plus-circle-fill"></i> Tambah <span class="pted-kbd">Enter ↵</span>
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, nextTick } from 'vue';

const props = defineProps({
    modelValue: { type: Array, default: () => [] },
    label: { type: String, required: true },
    icon: { type: String, default: 'bi-check-circle' },
    placeholder: { type: String, default: 'Tulis satu poin…' },
    maxlength: { type: Number, default: 300 },
});

const emit = defineEmits(['update:modelValue']);

const draft = ref('');
const inputBaru = ref(null);
const isInputFocused = ref(false);

const list = computed({
    get: () => props.modelValue,
    set: (v) => emit('update:modelValue', v),
});

function ubah(i, val) {
    const next = [...list.value];
    next[i] = val;
    list.value = next;
}

function hapus(i) {
    list.value = list.value.filter((_, idx) => idx !== i);
}

function clearAll() {
    list.value = [];
}

async function tambah() {
    const v = draft.value.trim();
    if (!v) return;
    list.value = [...list.value, v];
    draft.value = '';
    await nextTick();
    inputBaru.value?.focus();
}

function fokusBaru() {
    inputBaru.value?.focus();
}
</script>

<style scoped>
.pted {
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
    width: 100%;
}

.pted-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
}

.pted-badge {
    display: inline-flex;
    align-items: center;
    padding: 0.1rem 0.5rem;
    font-size: 0.68rem;
    font-weight: 800;
    color: #64748b;
    background: #f1f5f9;
    border-radius: 999px;
    margin-left: 0.3rem;
    transition: all 0.2s ease;
}

.pted-badge.is-active {
    color: #4338ca;
    background: #eef2ff;
    border: 1px solid #c7d2fe;
}

.pted-clear {
    border: none;
    background: transparent;
    color: #94a3b8;
    font-size: 0.72rem;
    font-weight: 700;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.15rem 0.4rem;
    border-radius: 0.35rem;
    transition: all 0.15s ease;
}

.pted-clear:hover {
    color: #ef4444;
    background: #fef2f2;
}

.pted-box {
    border: 1px solid #e2e8f0;
    border-radius: 0.85rem;
    padding: 0.6rem;
    background: #f8fafc;
    display: flex;
    flex-direction: column;
    gap: 0.45rem;
}

.pted-empty {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.6rem 0.75rem;
    background: #ffffff;
    border: 1px dashed #cbd5e1;
    border-radius: 0.65rem;
    font-size: 0.76rem;
    color: #64748b;
}

.pted-empty i {
    font-size: 1rem;
    color: #6366f1;
    flex-shrink: 0;
}

.pted-empty strong {
    color: #334155;
}

.pted-list {
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
}

.pted-row {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 0.65rem;
    padding: 0.35rem 0.6rem;
    transition: all 0.15s ease;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.02);
}

.pted-row:hover,
.pted-row:focus-within {
    border-color: #c7d2fe;
    box-shadow: 0 3px 8px rgba(99, 102, 241, 0.08);
}

.pted-idx {
    width: 1.3rem;
    height: 1.3rem;
    border-radius: 50%;
    background: #eef2ff;
    color: #4338ca;
    font-size: 0.68rem;
    font-weight: 800;
    display: grid;
    place-items: center;
    flex-shrink: 0;
}

.pted-input {
    flex: 1;
    border: none;
    outline: none;
    font: inherit;
    font-size: 0.82rem;
    color: #0f172a;
    background: transparent;
    min-width: 0;
}

.pted-rm {
    flex: none;
    border: none;
    background: transparent;
    color: #94a3b8;
    cursor: pointer;
    padding: 0.25rem;
    border-radius: 0.35rem;
    display: grid;
    place-items: center;
    transition: all 0.15s ease;
}

.pted-rm:hover {
    color: #ef4444;
    background: #fef2f2;
}

/* Add Row */
.pted-add-row {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    background: #ffffff;
    border: 1.5px dashed #cbd5e1;
    border-radius: 0.65rem;
    padding: 0.35rem 0.6rem;
    transition: all 0.2s ease;
}

.pted-add-row.is-focused,
.pted-add-row:hover {
    border-color: #6366f1;
    border-style: solid;
    box-shadow: 0 3px 12px rgba(99, 102, 241, 0.1);
}

.pted-add-icon {
    color: #6366f1;
    font-size: 0.9rem;
    flex-shrink: 0;
}

.pted-input--new::placeholder {
    color: #94a3b8;
    font-size: 0.8rem;
}

.pted-add-btn {
    flex: none;
    border: none;
    background: #4338ca;
    color: #ffffff;
    font-size: 0.74rem;
    font-weight: 800;
    cursor: pointer;
    padding: 0.3rem 0.65rem;
    border-radius: 0.45rem;
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    transition: all 0.15s ease;
}

.pted-add-btn:disabled {
    background: #e2e8f0;
    color: #94a3b8;
    cursor: not-allowed;
}

.pted-add-btn:not(:disabled):hover {
    background: #3730a3;
    transform: translateY(-1px);
}

.pted-kbd {
    font-size: 0.65rem;
    font-weight: 700;
    opacity: 0.8;
    background: rgba(255, 255, 255, 0.2);
    padding: 0.05rem 0.3rem;
    border-radius: 0.25rem;
}

.pted-add-btn:disabled .pted-kbd {
    display: none;
}

/* Animations */
.pted-item-enter-active,
.pted-item-leave-active {
    transition: all 0.2s ease;
}

.pted-item-enter-from {
    opacity: 0;
    transform: translateY(-6px);
}

.pted-item-leave-to {
    opacity: 0;
    transform: translateX(12px);
}
</style>

