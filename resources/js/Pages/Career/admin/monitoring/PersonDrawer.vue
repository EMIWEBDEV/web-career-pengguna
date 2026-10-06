<!--
  Drawer kanan detail SATU orang, dibuka dari papan Spotlight.
  Papan tetap terlihat di belakang → atasan bisa langsung klik orang lain
  tanpa menutup drawer (isi ikut berganti karena lamaranId reaktif).
-->
<template>
    <teleport to="body">
        <div class="wcm-dw">
            <aside class="wcm-dw__panel" role="dialog" aria-label="Detail pelamar">
                <header class="wcm-dw__head">
                    <span class="wcm-dw__ttl"><i class="bi bi-person-badge"></i> Detail Pelamar</span>
                    <button class="wca-iconbtn" title="Tutup (Esc)" @click="$emit('close')"><i class="bi bi-x-lg"></i></button>
                </header>
                <div class="wcm-dw__body">
                    <DetailPerjalanan :lamaran-id="lamaranId" bisa-buka
                        @open-stage="$emit('open-stage', $event)" />
                </div>
            </aside>
        </div>
    </teleport>
</template>

<script setup>
import DetailPerjalanan from './DetailPerjalanan.vue'
import { useLapisEsc } from '../../../../composables/useLapisEsc'

defineProps({ lamaranId: { type: String, required: true } })
const emit = defineEmits(['close', 'open-stage'])

useLapisEsc(() => emit('close'))
</script>

<style scoped>
/* Offcanvas LEVEL HALAMAN: menempel tepi layar, tinggi penuh viewport, dan
   berada di atas modal Spotlight — bukan panel di dalam modal.
   Tanpa backdrop yang menangkap klik, sehingga papan di belakang tetap bisa
   diklik dan atasan berpindah orang tanpa menutup offcanvas lebih dulu. */
/* Ukuran, padding, bayangan, dan animasi DISAMAKAN dengan StageDetailPanel &
   StagePersonDrawer. Sebelumnya ketiganya berbeda (480/500/520 px, tiga padding,
   tiga bayangan) sehingga berpindah antar-laci terasa seperti berpindah aplikasi. */
.wcm-dw { position: fixed; inset: 0; z-index: 1200; display: flex; justify-content: flex-end; pointer-events: none; }
.wcm-dw__panel { pointer-events: auto; width: min(500px, 100vw); height: 100vh; background: #fff; box-shadow: -20px 0 60px rgba(15, 23, 42, 0.22); border-left: 1px solid rgba(226, 232, 240, 0.9); display: flex; flex-direction: column; animation: wcmSlide 0.25s cubic-bezier(0.16, 1, 0.3, 1); }
@keyframes wcmSlide { from { transform: translateX(40px); opacity: 0.3; } to { transform: translateX(0); opacity: 1; } }
.wcm-dw__head { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 18px 22px; border-bottom: 1px solid #f1f5f9; flex: none; background: #fff; }
.wcm-dw__ttl { display: inline-flex; align-items: center; gap: 8px; font-size: 0.92rem; font-weight: 800; color: #0f172a; }
.wcm-dw__ttl .bi { color: #6366f1; }
/* Latar abu muda — TANPA ini, kartu putih di dalamnya melebur dengan panel dan
   seluruh isi laci terbaca sebagai satu blok datar tanpa hierarki. */
.wcm-dw__body { flex: 1; overflow-y: auto; padding: 20px 22px 34px; background: #f8fafc; }
</style>
