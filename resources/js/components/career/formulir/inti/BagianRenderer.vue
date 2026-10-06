<!-- WEB CAREER — Render SATU bagian formulir: biasa atau berulang (repeater). -->
<template>
    <section class="bg">
        <header v-if="bagian.judul" class="bg__head">
            <h4>{{ bagian.judul }}</h4>
            <p v-if="bagian.deskripsi">{{ bagian.deskripsi }}</p>
        </header>

        <!-- ── Bagian BERULANG: Pengalaman Kerja, Organisasi, Prestasi ──
             Jawabannya array objek, satu objek per baris. -->
        <template v-if="bagian.berulang">
            <!-- Alias v-for TIDAK BOLEH bernama `baris`. Namanya dulu sama
                 dengan computed penampungnya, jadi `baris.length` di dalam loop
                 membaca panjang OBJEK satu baris — selalu undefined — dan
                 tombol hapus di bawah tidak pernah terpasang sama sekali. -->
            <div v-for="(isiBaris, i) in baris" :key="i" class="bg__baris">
                <div class="bg__baris-head">
                    <span class="bg__baris-no">{{ i + 1 }}</span>
                    <!-- Baris terakhir sengaja tidak bisa dihapus: bagian berulang
                         minimal harus menyimpan satu baris, biar jawabannya tetap
                         berbentuk daftar dan validasinya punya baris tujuan. -->
                    <button
                        v-if="!disabled && baris.length > 1"
                        class="bg__hapus"
                        type="button"
                        title="Hapus baris ini"
                        @click="hapusBaris(i)"
                    >
                        <i class="bi bi-trash"></i> Hapus
                    </button>
                </div>
                <div class="bg__grid">
                    <FieldRenderer
                        v-for="f in fieldTerlihat(isiBaris)"
                        :key="f.key"
                        :field="f"
                        :bagian="kunci"
                        :baris="i"
                        :model-value="isiBaris[f.key]"
                        :disabled="disabled"
                        :konteks="konteksOpsi"
                        :jawaban-konteks="isiBaris"
                        @update:model-value="(v) => ubahBaris(i, f.key, v)"
                        @berkas="(e) => $emit('berkas', { ...e, bagian: kunci, baris: i })"
                    />
                </div>
            </div>

            <button
                v-if="!disabled && baris.length < (bagian.maks_baris || 5)"
                class="bg__tambah"
                type="button"
                @click="tambahBaris"
            >
                <i class="bi bi-plus-lg"></i> Tambah {{ bagian.judul || 'baris' }}
                <small>({{ baris.length }}/{{ bagian.maks_baris || 5 }})</small>
            </button>
        </template>

        <!-- ── Bagian biasa ── -->
        <div v-else class="bg__grid">
            <FieldRenderer
                v-for="f in fieldTerlihat(jawaban)"
                :key="f.key"
                :field="f"
                :model-value="jawaban[f.key]"
                :disabled="disabled"
                :konteks="konteksOpsi"
                :jawaban-konteks="jawaban"
                :galat="galat[f.key] || ''"
                @update:model-value="(v) => $emit('ubah', f.key, v)"
                @berkas="(e) => $emit('berkas', e)"
            />
        </div>
    </section>
</template>

<script setup>
import { computed } from 'vue';
import FieldRenderer from './FieldRenderer.vue';
import { fieldTampil, kunciBagian, barisKosong } from '@utils/formulir/aturan';

const props = defineProps({
    bagian: { type: Object, required: true },
    jawaban: { type: Object, required: true },
    disabled: { type: Boolean, default: false },
    galat: { type: Object, default: () => ({}) },
    // Konteks pembukaan (opsi dinamis field, mis. kampus whitelist).
    konteksOpsi: { type: Object, default: () => ({}) },
});

const emit = defineEmits(['ubah', 'ubah-baris', 'berkas', 'hapus-baris']);

const kunci = computed(() => kunciBagian(props.bagian));
const baris = computed(() => props.jawaban[kunci.value] || []);

/**
 * Syarat tampil dievaluasi terhadap KONTEKS yang benar: di bagian berulang,
 * acuannya jawaban baris itu sendiri, bukan jawaban formulir secara global.
 * Kalau tidak, satu baris bisa menyembunyikan field di baris lain.
 */
function fieldTerlihat(konteks) {
    return fieldTampil(props.bagian.field, konteks);
}

function ubahBaris(i, key, nilai) {
    emit('ubah-baris', kunci.value, i, key, nilai);
}

function tambahBaris() {
    emit('ubah', kunci.value, [...baris.value, barisKosong(props.bagian)]);
}

/**
 * Baris dibuang dari jawaban, DAN berkasnya diberitahukan ke induk.
 *
 * Tanpa pancaran kedua, objek berkas baris itu menetap di bucket selamanya dan
 * indeks berkas di atasnya tidak pernah turun — sertifikat lalu menempel ke
 * baris yang salah.
 */
function hapusBaris(i) {
    emit('ubah', kunci.value, baris.value.filter((_, j) => j !== i));
    emit('hapus-baris', kunci.value, i);
}
</script>

<style scoped>
.bg { margin-bottom: 1.1rem; }

.bg__head { margin-bottom: .7rem; }
.bg__head h4 { margin: 0; font-size: .95rem; font-weight: 800; color: #0f172a; letter-spacing: -.01em; }
.bg__head p { margin: .2rem 0 0; font-size: 12px; color: #64748b; line-height: 1.55; }

.bg__grid { display: grid; grid-template-columns: repeat(12, minmax(0, 1fr)); gap: .8rem; }

.bg__baris { border: 1px solid rgba(11, 16, 51, .09); border-radius: 12px; padding: .8rem; margin-bottom: .6rem; background: #f8fafc; }
.bg__baris-head { display: flex; align-items: center; margin-bottom: .6rem; }
.bg__baris-no { width: 1.5rem; height: 1.5rem; display: grid; place-items: center; border-radius: 50%; background: rgba(79, 70, 229, .12); color: #4338ca; font-size: 11px; font-weight: 700; }
.bg__hapus { margin-left: auto; border: 0; background: transparent; color: #94a3b8; font-family: inherit; font-size: .78rem; font-weight: 600; cursor: pointer; padding: .25rem .55rem; border-radius: 6px; display: inline-flex; align-items: center; gap: .3rem; transition: color 160ms ease, background 160ms ease; }
.bg__hapus:hover { color: #dc2626; background: rgba(220, 38, 38, .08); }

.bg__tambah { width: 100%; border: 1px dashed rgba(79, 70, 229, .3); border-radius: 10px; background: transparent; color: #4338ca; font: inherit; font-size: 12px; font-weight: 600; padding: .5rem; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: .35rem; transition: background 160ms ease; }
.bg__tambah:hover { background: rgba(79, 70, 229, .07); }
.bg__tambah small { color: #94a3b8; font-weight: 500; }

@media (max-width: 700px) {
    .bg__grid { grid-template-columns: 1fr; }
    .bg__grid > * { grid-column: 1 / -1 !important; }
}
</style>
