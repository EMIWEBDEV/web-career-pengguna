<!-- WEB CAREER — Pemilih ikon (Modal Pop-up Grid):
     Memuat ikon Bootstrap dari /api/v1/karir/options/icons,
     menampilkan tombol pemicu ramah pengguna dan modal pop-up interaktif
     dengan fitur pencarian instan, filter, serta grid visual yang mudah dipilih. -->
<template>
    <div class="iconpicker" :class="{ 'iconpicker--compact': compact }">
        <!-- Control Box / Trigger Button -->
        <div class="iconpicker__trigger" :class="{ 'iconpicker__trigger--compact': compact }" @click="openModal">
            <div
                class="iconpicker__preview"
                :class="{ 'is-empty': !modelValue }"
                :title="
                    compact ? (modelValue ? pretty(modelValue) + ' (Klik untuk ganti)' : 'Pilih ikon step') : undefined
                "
            >
                <i class="bi" :class="modelValue || 'bi-app-indicator'"></i>
            </div>
            <template v-if="!compact">
                <div class="iconpicker__label">
                    <span v-if="modelValue" class="iconpicker__name">{{ pretty(modelValue) }}</span>
                    <span v-if="modelValue" class="iconpicker__code">{{ modelValue }}</span>
                    <span v-else class="iconpicker__placeholder">{{ placeholder }}</span>
                </div>
                <div class="iconpicker__actions">
                    <button
                        v-if="modelValue"
                        type="button"
                        class="iconpicker__clearbtn"
                        title="Hapus Ikon"
                        @click.stop="clearIcon"
                    >
                        <i class="bi bi-x-lg"></i>
                    </button>
                    <button type="button" class="iconpicker__btn">
                        <i class="bi bi-grid-3x3-gap-fill"></i>
                        <span>{{ modelValue ? 'Ganti' : 'Pilih Ikon' }}</span>
                    </button>
                </div>
            </template>
        </div>

        <!-- Popup Modal -->
        <teleport to="body">
            <transition name="ip-modal">
                <div v-if="showModal" class="ip-mask wca" @click.self="closeModal">
                    <div class="ip-dialog" role="dialog" aria-modal="true">
                        <!-- Header -->
                        <div class="ip-head">
                            <div class="ip-head__title">
                                <div class="ip-head__icon"><i class="bi bi-grid-3x3-gap-fill"></i></div>
                                <div>
                                    <h3>Pilih Ikon</h3>
                                    <p>Klik salah satu ikon di bawah ini untuk memilih</p>
                                </div>
                            </div>
                            <button type="button" class="ip-close" title="Tutup Modal" @click="closeModal">
                                <i class="bi bi-x-lg"></i>
                            </button>
                        </div>

                        <!-- Toolbar Search -->
                        <div class="ip-toolbar">
                            <div class="ip-search">
                                <i class="bi bi-search"></i>
                                <input
                                    ref="searchInput"
                                    v-model="searchQuery"
                                    type="text"
                                    placeholder="Cari ikon (mis. question, user, check, info, star)..."
                                />
                                <button
                                    v-if="searchQuery"
                                    type="button"
                                    class="ip-search__clear"
                                    @click="searchQuery = ''"
                                >
                                    <i class="bi bi-x-circle-fill"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Grid Body -->
                        <div class="ip-body">
                            <div v-if="loading" class="ip-loading">
                                <span class="wca-spin"></span>
                                <p>Memuat daftar ikon…</p>
                            </div>

                            <div v-else-if="filteredIcons.length === 0" class="ip-empty">
                                <i class="bi bi-search"></i>
                                <h4>Ikon tidak ditemukan</h4>
                                <p>Coba gunakan kata kunci pencarian lainnya.</p>
                            </div>

                            <div v-else class="ip-grid">
                                <button
                                    v-for="item in visibleIcons"
                                    :key="item.value"
                                    type="button"
                                    class="ip-tile"
                                    :class="{ 'is-selected': selectedTemp === item.value }"
                                    :title="item.value"
                                    @click="selectTempIcon(item.value)"
                                >
                                    <i class="bi" :class="item.value"></i>
                                    <span class="ip-tile__name">{{ item.label }}</span>
                                    <div v-if="selectedTemp === item.value" class="ip-tile__check">
                                        <i class="bi bi-check-lg"></i>
                                    </div>
                                </button>
                            </div>

                            <!-- Load More Button -->
                            <div v-if="filteredIcons.length > limit" class="ip-more">
                                <span>Menampilkan {{ visibleIcons.length }} dari {{ filteredIcons.length }} ikon</span>
                                <button type="button" class="wca-btn wca-btn--ghost" @click="limit += 120">
                                    Tampilkan Lebih Banyak
                                </button>
                            </div>
                        </div>

                        <!-- Footer -->
                        <div class="ip-foot">
                            <div class="ip-foot__selected">
                                <template v-if="selectedTemp">
                                    <div class="ip-foot__prev"><i class="bi" :class="selectedTemp"></i></div>
                                    <div class="ip-foot__text">
                                        <span>Dipilih:</span>
                                        <strong>{{ pretty(selectedTemp) }}</strong>
                                        <code>{{ selectedTemp }}</code>
                                    </div>
                                </template>
                                <template v-else>
                                    <span class="ip-foot__none">Belum ada ikon yang dipilih</span>
                                </template>
                            </div>

                            <div class="ip-foot__btns">
                                <button type="button" class="wca-btn wca-btn--ghost" @click="closeModal">Batal</button>
                                <button type="button" class="wca-btn wca-btn--dark" @click="confirmSelection">
                                    <i class="bi bi-check-lg"></i> Gunakan Ikon Ini
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </transition>
        </teleport>
    </div>
</template>

<script>
import axios from 'axios';
import { nextTick } from 'vue';

export default {
    props: {
        modelValue: { type: String, default: '' },
        placeholder: { type: String, default: 'Cari & pilih ikon…' },
        compact: { type: Boolean, default: false },
    },
    emits: ['update:modelValue'],
    data() {
        return {
            options: [],
            loading: false,
            showModal: false,
            searchQuery: '',
            selectedTemp: '',
            limit: 120,
        };
    },
    computed: {
        filteredIcons() {
            const q = this.searchQuery.trim().toLowerCase();
            if (!q) return this.options;

            return this.options.filter(
                (item) => item.value.toLowerCase().includes(q) || item.label.toLowerCase().includes(q),
            );
        },
        visibleIcons() {
            return this.filteredIcons.slice(0, this.limit);
        },
    },
    watch: {
        searchQuery() {
            this.limit = 120;
        },
    },
    mounted() {
        this.load();
    },
    methods: {
        pretty(v) {
            if (!v) return '';
            return String(v)
                .replace(/^bi-/, '')
                .split('-')
                .map((w) => w.charAt(0).toUpperCase() + w.slice(1))
                .join(' ');
        },
        async load() {
            this.loading = true;
            try {
                const res = await axios.get('/api/v1/karir/options/icons', {
                    headers: { Accept: 'application/json' },
                });
                this.options = (res.data.result || []).map((v) => ({
                    value: v,
                    label: this.pretty(v),
                }));
            } catch (e) {
                this.options = [];
            } finally {
                this.loading = false;
            }
        },
        openModal() {
            this.selectedTemp = this.modelValue || '';
            this.searchQuery = '';
            this.limit = 120;
            this.showModal = true;
            nextTick(() => {
                this.$refs.searchInput?.focus();
            });
        },
        closeModal() {
            this.showModal = false;
        },
        clearIcon() {
            this.$emit('update:modelValue', '');
        },
        selectTempIcon(val) {
            this.selectedTemp = val;
        },
        confirmSelection() {
            this.$emit('update:modelValue', this.selectedTemp);
            this.showModal = false;
        },
    },
};
</script>

<style scoped>
.iconpicker {
    width: 100%;
}
.iconpicker--compact {
    width: auto;
    display: inline-block;
}
.iconpicker__trigger {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    padding: 0.45rem 0.65rem;
    border: 1.5px solid var(--line, #e2e8f0);
    border-radius: 0.65rem;
    background: #ffffff;
    cursor: pointer;
    user-select: none;
    transition: all 0.18s ease;
}
.iconpicker__trigger--compact {
    padding: 0;
    border: none;
    background: transparent;
    border-radius: 0.5rem;
}
.iconpicker__trigger--compact:hover {
    box-shadow: none;
}
.iconpicker__trigger--compact .iconpicker__preview {
    width: 2.2rem;
    height: 2.2rem;
    border: 1.5px solid #cbd5e1;
    transition: all 0.18s ease;
}
.iconpicker__trigger--compact:hover .iconpicker__preview {
    border-color: #6366f1;
    background: #eef2ff;
    color: #4f46e5;
    transform: scale(1.05);
}
.iconpicker__preview {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 2.3rem;
    height: 2.3rem;
    flex: none;
    border-radius: 0.5rem;
    background: rgba(99, 102, 241, 0.1);
    color: #4f46e5;
    font-size: 1.15rem;
}
.iconpicker__preview.is-empty {
    background: #f1f5f9;
    color: #94a3b8;
}
.iconpicker__label {
    display: flex;
    flex-direction: column;
    flex: 1;
    min-width: 0;
}
.iconpicker__name {
    font-size: 0.83rem;
    font-weight: 700;
    color: #1e293b;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.iconpicker__code {
    font-size: 0.72rem;
    color: #64748b;
    font-weight: 600;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.iconpicker__placeholder {
    font-size: 0.82rem;
    color: #94a3b8;
}
.iconpicker__actions {
    display: flex;
    align-items: center;
    gap: 0.35rem;
}
.iconpicker__clearbtn {
    display: grid;
    place-items: center;
    width: 1.8rem;
    height: 1.8rem;
    border: none;
    border-radius: 0.4rem;
    background: transparent;
    color: #94a3b8;
    cursor: pointer;
    font-size: 0.85rem;
    transition: all 0.15s ease;
}
.iconpicker__clearbtn:hover {
    background: #fee2e2;
    color: #ef4444;
}
.iconpicker__btn {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.4rem 0.75rem;
    border: 1px solid rgba(99, 102, 241, 0.25);
    border-radius: 0.5rem;
    background: rgba(99, 102, 241, 0.08);
    color: #4f46e5;
    font-size: 0.78rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.18s ease;
}
.iconpicker__btn:hover {
    background: #4f46e5;
    color: #ffffff;
}

/* Modal Popup Styles */
.ip-mask {
    position: fixed;
    inset: 0;
    z-index: 99999;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 1.25rem;
    background: rgba(15, 23, 42, 0.55);
    backdrop-filter: blur(6px);
}
.ip-dialog {
    display: flex;
    flex-direction: column;
    width: 100%;
    max-width: 760px;
    max-height: 88vh;
    border-radius: 1rem;
    background: #ffffff;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
    overflow: hidden;
    animation: ipPop 0.22s cubic-bezier(0.16, 1, 0.3, 1);
}
@keyframes ipPop {
    from {
        opacity: 0;
        transform: scale(0.95) translateY(10px);
    }
    to {
        opacity: 1;
        transform: scale(1) translateY(0);
    }
}

.ip-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1.1rem 1.35rem;
    background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
    color: #ffffff;
}
.ip-head__title {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}
.ip-head__icon {
    display: grid;
    place-items: center;
    width: 2.5rem;
    height: 2.5rem;
    border-radius: 0.7rem;
    background: rgba(255, 255, 255, 0.18);
    color: #ffffff;
    font-size: 1.2rem;
}
.ip-head__title h3 {
    margin: 0;
    font-size: 1.05rem;
    font-weight: 800;
    color: #ffffff;
}
.ip-head__title p {
    margin: 0.15rem 0 0;
    font-size: 0.78rem;
    color: rgba(255, 255, 255, 0.85);
}
.ip-close {
    display: grid;
    place-items: center;
    width: 2.1rem;
    height: 2.1rem;
    border: none;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.15);
    color: #ffffff;
    font-size: 0.95rem;
    cursor: pointer;
    transition: all 0.15s ease;
}
.ip-close:hover {
    background: rgba(255, 255, 255, 0.3);
    transform: rotate(90deg);
}

.ip-toolbar {
    padding: 0.85rem 1.35rem;
    background: #f8fafc;
    border-bottom: 1px solid #e2e8f0;
}
.ip-search {
    position: relative;
    display: flex;
    align-items: center;
}
.ip-search i {
    position: absolute;
    left: 0.85rem;
    color: #94a3b8;
    font-size: 0.95rem;
    pointer-events: none;
}
.ip-search input {
    width: 100%;
    padding: 0.65rem 2.4rem 0.65rem 2.3rem;
    border: 1.5px solid #cbd5e1;
    border-radius: 0.65rem;
    font: inherit;
    font-size: 0.85rem;
    background: #ffffff;
    outline: none;
    color: #1e293b;
    transition: all 0.18s ease;
}
.ip-search input:focus {
    border-color: #6366f1;
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.12);
}
.ip-search__clear {
    position: absolute;
    right: 0.65rem;
    border: none;
    background: transparent;
    color: #94a3b8;
    cursor: pointer;
    font-size: 0.9rem;
}
.ip-search__clear:hover {
    color: #64748b;
}

.ip-body {
    flex: 1;
    overflow-y: auto;
    padding: 1.25rem 1.35rem;
    min-height: 280px;
    max-height: 400px;
}
.ip-loading,
.ip-empty {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 3rem 1rem;
    text-align: center;
    color: #64748b;
}
.ip-empty i {
    font-size: 2.2rem;
    color: #cbd5e1;
    margin-bottom: 0.5rem;
}
.ip-empty h4 {
    margin: 0 0 0.25rem;
    font-size: 0.95rem;
    color: #334155;
}
.ip-empty p {
    margin: 0;
    font-size: 0.8rem;
}

.ip-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(95px, 1fr));
    gap: 0.65rem;
}
.ip-tile {
    position: relative;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 0.4rem;
    padding: 0.85rem 0.4rem;
    border: 1.5px solid #f1f5f9;
    border-radius: 0.75rem;
    background: #ffffff;
    cursor: pointer;
    transition: all 0.18s cubic-bezier(0.16, 1, 0.3, 1);
}
.ip-tile:hover {
    border-color: #cbd5e1;
    background: #f8fafc;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.04);
}
.ip-tile i {
    font-size: 1.35rem;
    color: #475569;
    transition: transform 0.18s ease;
}
.ip-tile:hover i {
    color: #4f46e5;
    transform: scale(1.15);
}
.ip-tile__name {
    font-size: 0.7rem;
    font-weight: 600;
    color: #64748b;
    text-align: center;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    width: 100%;
    padding: 0 0.2rem;
}
.ip-tile.is-selected {
    border-color: #6366f1;
    background: rgba(99, 102, 241, 0.06);
    box-shadow: 0 4px 14px rgba(99, 102, 241, 0.15);
}
.ip-tile.is-selected i {
    color: #4f46e5;
}
.ip-tile.is-selected .ip-tile__name {
    color: #4f46e5;
    font-weight: 700;
}
.ip-tile__check {
    position: absolute;
    top: 0.3rem;
    right: 0.3rem;
    display: grid;
    place-items: center;
    width: 1.1rem;
    height: 1.1rem;
    border-radius: 50%;
    background: #6366f1;
    color: #ffffff;
    font-size: 0.65rem;
}

.ip-more {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.5rem;
    margin-top: 1.25rem;
    padding-top: 1rem;
    border-top: 1px dashed #e2e8f0;
    font-size: 0.78rem;
    color: #64748b;
}

.ip-foot {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding: 0.9rem 1.35rem;
    background: #f8fafc;
    border-top: 1px solid #e2e8f0;
}
.ip-foot__selected {
    display: flex;
    align-items: center;
    gap: 0.65rem;
}
.ip-foot__prev {
    display: grid;
    place-items: center;
    width: 2rem;
    height: 2rem;
    border-radius: 0.5rem;
    background: rgba(99, 102, 241, 0.1);
    color: #4f46e5;
    font-size: 1.1rem;
}
.ip-foot__text {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.8rem;
    color: #334155;
}
.ip-foot__text code {
    background: #e2e8f0;
    padding: 0.1rem 0.35rem;
    border-radius: 0.25rem;
    font-size: 0.72rem;
    color: #4f46e5;
}
.ip-foot__none {
    font-size: 0.8rem;
    color: #94a3b8;
    font-style: italic;
}
.ip-foot__btns {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

/* Modal Transitions */
.ip-modal-enter-active,
.ip-modal-leave-active {
    transition: opacity 0.2s ease;
}
.ip-modal-enter-from,
.ip-modal-leave-to {
    opacity: 0;
}
</style>
