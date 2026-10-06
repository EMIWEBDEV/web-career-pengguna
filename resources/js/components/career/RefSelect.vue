<!-- WEB CAREER — Select referensi: opsi DIAMBIL DARI DB via /api/v1/karir/options/{type} (bukan hardcode). -->
<template>
    <el-select
        :model-value="modelValue"
        filterable
        :clearable="clearable"
        :disabled="disabled"
        :placeholder="placeholder"
        :loading="loading"
        loading-text="Memuat…"
        :no-data-text="noDataText"
        style="width: 100%"
        @update:model-value="pilih"
    >
        <el-option v-for="o in options" :key="o.value" :label="o.label" :value="o.value" />
    </el-select>
</template>

<script>
import axios from 'axios';

export default {
    props: {
        modelValue: { type: [String, Number, null], default: null },
        type: { type: String, required: true },
        placeholder: { type: String, default: 'Pilih…' },
        clearable: { type: Boolean, default: false },
        disabled: { type: Boolean, default: false },
        // Filter kontekstual, mis. { kategori: 'MT', alur: 'ALR-MT' }. Opsi dimuat
        // ulang tiap nilainya berubah supaya pilihan selalu menyempit mengikuti
        // field di atasnya.
        params: { type: Object, default: () => ({}) },
        noDataText: { type: String, default: 'Tidak ada data' },
    },
    // 'picked' membawa objek opsi UTUH (bukan cuma value) supaya pemanggil bisa
    // mengisi otomatis field turunan — dipakai picker MPP di Program Kegiatan.
    emits: ['update:modelValue', 'picked'],
    data() {
        return { options: [], loading: false };
    },
    watch: {
        type() { this.load(); },
        params: { deep: true, handler() { this.load(); } },
    },
    mounted() {
        this.load();
    },
    methods: {
        pilih(value) {
            this.$emit('update:modelValue', value);
            this.$emit('picked', this.options.find((o) => o.value === value) || null);
        },
        async load() {
            this.loading = true;
            try {
                const res = await axios.get(`/api/v1/karir/options/${this.type}`, {
                    headers: { Accept: 'application/json' },
                    params: this.params,
                });
                this.options = res.data.result || [];
            } catch (e) {
                this.options = [];
            } finally {
                this.loading = false;
            }
        },
    },
};
</script>
