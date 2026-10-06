<!--
  Pratinjau berkas layar penuh (gambar / PDF).
  SENGAJA bukan offcanvas lapis ke-4: ini konten media, bukan navigasi —
  menumpuknya di antrean drawer justru membuat orang kehilangan arah.
-->
<template>
    <teleport to="body">
        <div class="wcm-lb" @click.self="$emit('close')">
            <div class="wcm-lb__bar">
                <span class="wcm-lb__nama"><i class="bi" :class="jenis === 'pdf' ? 'bi-file-earmark-pdf' : 'bi-image'"></i> {{ berkas.nama }}</span>
                <span class="wcm-lb__meta">{{ formatUkuran(berkas.ukuran) }}</span>
                <a :href="berkas.url" target="_blank" rel="noopener" class="wcm-lb__aksi" title="Buka di tab baru"><i class="bi bi-box-arrow-up-right"></i></a>
                <button class="wcm-lb__aksi" title="Tutup (Esc)" @click="$emit('close')"><i class="bi bi-x-lg"></i></button>
            </div>
            <div class="wcm-lb__isi" @click.self="$emit('close')">
                <img v-if="jenis === 'gambar'" :src="berkas.url" :alt="berkas.nama" />
                <iframe v-else-if="jenis === 'pdf'" :src="berkas.url" :title="berkas.nama"></iframe>
                <div v-else class="wcm-lb__tak">
                    <i class="bi bi-file-earmark-arrow-down"></i>
                    <p>Berkas ini tidak bisa dipratinjau di sini.</p>
                    <a :href="berkas.url" target="_blank" rel="noopener" class="wca-btn wca-btn--primary wca-btn--sm">
                        <i class="bi bi-download"></i> Buka berkas
                    </a>
                </div>
            </div>
        </div>
    </teleport>
</template>

<script setup>
import { computed } from 'vue'
import { formatUkuran, jenisPratinjau } from '@utils/career/monitoring'
import { useLapisEsc } from '../../../../composables/useLapisEsc'

const props = defineProps({ berkas: { type: Object, required: true } })
const emit = defineEmits(['close'])

const jenis = computed(() => jenisPratinjau(props.berkas.ext, props.berkas.mime))

useLapisEsc(() => emit('close'))
</script>

<style scoped>
.wcm-lb { position: fixed; inset: 0; z-index: 1500; background: rgba(15, 23, 42, 0.86); backdrop-filter: blur(4px); display: flex; flex-direction: column; animation: wcmLbIn 0.16s ease; }
@keyframes wcmLbIn { from { opacity: 0; } to { opacity: 1; } }
.wcm-lb__bar { flex: none; display: flex; align-items: center; gap: 12px; padding: 13px 18px; color: #e5e7eb; }
.wcm-lb__nama { display: inline-flex; align-items: center; gap: 9px; font-size: 13.5px; font-weight: 700; flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.wcm-lb__meta { font-size: 12px; color: #9ca3af; white-space: nowrap; }
.wcm-lb__aksi { display: grid; place-items: center; width: 34px; height: 34px; border: 0; border-radius: 10px; background: rgba(255, 255, 255, 0.12); color: #e5e7eb; cursor: pointer; text-decoration: none; }
.wcm-lb__aksi:hover { background: rgba(255, 255, 255, 0.22); color: #fff; }
.wcm-lb__isi { flex: 1; min-height: 0; display: grid; place-items: center; padding: 0 18px 18px; }
.wcm-lb__isi img { max-width: 100%; max-height: 100%; object-fit: contain; border-radius: 12px; background: #fff; }
.wcm-lb__isi iframe { width: 100%; height: 100%; border: 0; border-radius: 12px; background: #fff; }
.wcm-lb__tak { text-align: center; color: #cbd5e1; }
.wcm-lb__tak .bi { font-size: 40px; opacity: 0.6; }
.wcm-lb__tak p { margin: 10px 0 14px; font-size: 13px; }
</style>
