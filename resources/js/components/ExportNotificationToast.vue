<!-- WEB CAREER — Global Floating Export Toast Widget: Ultra-Compact Light Theme Glassmorphism (Static Elegant Hourglass Icon, Slim 310px, Tight Spacing, Pixel-Perfect Layout). -->
<template>
  <Transition name="el-zoom-in-bottom">
    <div
      v-if="items && items.length > 0"
      class="wca-export-toast-widget"
      :class="{ 'wca-export-toast-widget--minimized': isMinimized }"
    >
      <!-- Header Bar / Minimized Pill Content -->
      <div class="wca-export-toast-head">
        <div class="d-flex align-items-center gap-2 overflow-hidden">
          <div class="wca-head-icon-circle" :class="hasActiveProcessing ? 'bg-amber-light text-amber' : 'bg-emerald-light text-emerald'">
            <i class="bi" :class="hasActiveProcessing ? 'bi-hourglass-split' : 'bi-file-earmark-excel-fill'"></i>
          </div>
          <div class="d-flex align-items-center gap-1.5 min-w-0">
            <span class="font-bold text-slate-800 text-xs text-truncate">Ekspor Berkas</span>
            <span class="wca-badge" :class="hasActiveProcessing ? 'wca-b--amber' : 'wca-b--emerald'">
              {{ headerBadgeText }}
            </span>
          </div>
        </div>

        <div class="d-flex align-items-center gap-1 flex-shrink-0 ms-2">
          <button
            type="button"
            class="wca-toast-ctrl-btn"
            @click="isMinimized = !isMinimized"
            :title="isMinimized ? 'Perluas' : 'Minimalkan'"
          >
            <i class="bi" :class="isMinimized ? 'bi-chevron-up' : 'bi-dash-lg'"></i>
          </button>
          <button
            type="button"
            class="wca-toast-ctrl-btn wca-toast-ctrl-btn--close"
            @click="dismissAll"
            title="Tutup"
          >
            <i class="bi bi-x-lg"></i>
          </button>
        </div>
      </div>

      <!-- Expanded Toast Card Body -->
      <div v-if="!isMinimized" class="wca-export-toast-body">
        <div
          v-for="item in items"
          :key="item.Id_Export"
          class="wca-export-item"
        >
          <div class="d-flex align-items-center gap-2.5">
            <!-- Left Avatar Icon Box (28px Compact Static) -->
            <div
              class="wca-export-avatar-box"
              :class="item.Status_Export === 'DIPROSES' ? 'bg-amber-light text-amber' : (item.Status_Export === 'SELESAI' ? 'bg-emerald-light text-emerald' : 'bg-rose-light text-rose')"
            >
              <i
                class="bi"
                :class="item.Status_Export === 'DIPROSES' ? 'bi-hourglass-split' : (item.Status_Export === 'SELESAI' ? 'bi-file-earmark-excel-fill' : 'bi-exclamation-triangle-fill')"
              ></i>
            </div>

            <!-- Main Content Area -->
            <div class="flex-grow-1 min-w-0">
              <div class="d-flex justify-content-between align-items-center gap-1 mb-1">
                <strong class="text-slate-800 font-bold text-xs text-truncate" style="font-size:0.78rem">{{ item.Keterangan || 'Ekspor Data' }}</strong>
                <button
                  type="button"
                  class="wca-btn-icon-xs text-slate-400"
                  @click="dismiss(item.Id_Export)"
                  title="Hapus"
                >
                  <i class="bi bi-x"></i>
                </button>
              </div>

              <!-- Status & Progress per Item -->
              <template v-if="item.Status_Export === 'DIPROSES'">
                <!-- Ultra-Slim Progress Bar (4px) -->
                <div class="wca-progress-mini my-1">
                  <div
                    class="wca-progress-mini__bar bg-indigo-gradient"
                    :style="{ width: progressPercent(item) + '%' }"
                  ></div>
                </div>

                <div class="d-flex justify-content-between align-items-center" style="font-size:0.68rem">
                  <span class="text-amber font-semibold">
                    <i class="bi bi-hourglass-split me-1"></i> Memproses...
                  </span>
                  <span class="text-slate-500 font-bold">
                    {{ item.Progress_Chunk || 0 }}/{{ item.Progress_Total || 0 }} chunk ({{ progressPercent(item) }}%)
                  </span>
                </div>
              </template>

              <!-- Completed Status with Download Button -->
              <template v-else-if="item.Status_Export === 'SELESAI'">
                <div class="d-flex justify-content-between align-items-center mt-1">
                  <span class="text-emerald font-semibold" style="font-size:0.7rem">
                    <i class="bi bi-check-circle-fill me-1"></i> Selesai
                  </span>
                  <a
                    :href="'/api/v1/karir/export/download/' + item.Id_Export"
                    target="_blank"
                    class="wca-btn-download-link"
                  >
                    <i class="bi bi-download me-1"></i> Unduh
                  </a>
                </div>
              </template>

              <!-- Failed Status -->
              <template v-else-if="item.Status_Export === 'GAGAL'">
                <div>
                  <span class="text-rose font-medium text-truncate d-block" style="font-size:0.68rem">
                    <i class="bi bi-exclamation-circle-fill me-1"></i> {{ item.Error_Message || 'Gagal memproses file' }}
                  </span>
                </div>
              </template>
            </div>
          </div>
        </div>
      </div>
    </div>
  </Transition>
</template>

<script setup>
import { ref, computed } from 'vue';
import { useExportNotification } from '../composables/useExportNotification.js';

const { items, dismiss } = useExportNotification();
const isMinimized = ref(false);

const hasActiveProcessing = computed(() => {
  if (!items.value) return false;
  return items.value.some(item => item.Status_Export === 'DIPROSES');
});

const headerBadgeText = computed(() => {
  if (!items.value || !items.value.length) return '';
  const total = items.value.length;
  const diprosesCount = items.value.filter(e => e.Status_Export === 'DIPROSES').length;
  const selesaiCount = items.value.filter(e => e.Status_Export === 'SELESAI').length;

  if (diprosesCount > 0) {
    return `${diprosesCount} Diproses`;
  }
  return `${selesaiCount} Selesai`;
});

function progressPercent(item) {
  if (!item || !item.Progress_Total || item.Progress_Total <= 0) return 0;
  const chunk = item.Progress_Chunk || 0;
  const pct = Math.round((chunk / item.Progress_Total) * 100);
  return isNaN(pct) ? 0 : Math.min(100, Math.max(0, pct));
}

function dismissAll() {
  if (!items.value) return;
  items.value.forEach(item => dismiss(item.Id_Export));
}
</script>

<style scoped>

/* Ultra-Compact Floating Container (Slim 310px) */
.wca-export-toast-widget {
  position: fixed;
  bottom: 20px;
  right: 20px;
  width: 310px;
  background: rgba(255, 255, 255, 0.96);
  backdrop-filter: blur(14px);
  -webkit-backdrop-filter: blur(14px);
  color: #0f172a;
  border-radius: 12px;
  box-shadow: 0 10px 28px rgba(0, 0, 0, 0.1), 0 2px 8px rgba(99, 102, 241, 0.06);
  border: 1px solid #cbd5e1;
  z-index: 9999;
  overflow: hidden;
  font-family: 'Inter', system-ui, -apple-system, sans-serif;
  transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
}

.wca-export-toast-widget--minimized {
  width: auto;
  max-width: 280px;
  border-radius: 30px;
  box-shadow: 0 6px 20px rgba(0, 0, 0, 0.09);
  overflow: hidden;
}

.wca-export-toast-widget--minimized .wca-export-toast-head {
  border-bottom: none;
  padding: 6px 16px;
  background: #ffffff;
  overflow: hidden;
}

.wca-export-toast-head {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 8px 12px;
  background: #f8fafc;
  border-bottom: 1px solid #e2e8f0;
}

.wca-head-icon-circle {
  width: 22px;
  height: 22px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.75rem;
  flex-shrink: 0;
}

.wca-toast-ctrl-btn {
  background: transparent;
  border: none;
  color: #64748b;
  cursor: pointer;
  font-size: 0.78rem;
  padding: 3px 6px;
  border-radius: 6px;
  transition: color 0.15s, background-color 0.15s;
  outline: none !important;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}

.wca-toast-ctrl-btn:hover {
  color: #0f172a;
  background: #e2e8f0;
}

.wca-toast-ctrl-btn--close:hover {
  color: #ef4444;
  background: rgba(239, 68, 68, 0.1);
}

.wca-export-toast-body {
  padding: 8px;
  display: flex;
  flex-direction: column;
  gap: 6px;
  max-height: 240px;
  overflow-y: auto;
}

.wca-export-item {
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  padding: 8px 10px;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.02);
}

.wca-export-avatar-box {
  width: 28px;
  height: 28px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.95rem;
  flex-shrink: 0;
}

.bg-emerald-light { background-color: rgba(16, 185, 129, 0.12); }
.bg-amber-light { background-color: rgba(245, 158, 11, 0.12); }
.bg-rose-light { background-color: rgba(239, 68, 68, 0.12); }

.text-emerald { color: #059669 !important; }
.text-amber { color: #d97706 !important; }
.text-rose { color: #e11d48 !important; }

.wca-badge {
  font-size: 0.62rem;
  font-weight: 700;
  padding: 1px 6px;
  border-radius: 12px;
  letter-spacing: 0.02em;
}

.wca-b--emerald { background: rgba(16, 185, 129, 0.15); color: #059669; }
.wca-b--amber { background: rgba(245, 158, 11, 0.15); color: #d97706; }
.wca-b--rose { background: rgba(239, 68, 68, 0.15); color: #e11d48; }

.wca-progress-mini {
  height: 4px;
  background: #f1f5f9;
  border-radius: 8px;
  overflow: hidden;
}

.wca-progress-mini__bar {
  height: 100%;
  border-radius: 8px;
  transition: width 0.4s ease;
}

.bg-indigo-gradient {
  background: linear-gradient(90deg, #6366f1 0%, #818cf8 100%);
}

.wca-btn-download-link {
  font-size: 0.72rem;
  font-weight: 700;
  color: #059669;
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  padding: 2px 8px;
  background: #ecfdf5;
  border: 1px solid #a7f3d0;
  border-radius: 6px;
  transition: all 0.15s ease;
}

.wca-btn-download-link:hover {
  background: #10b981;
  color: #ffffff;
  border-color: #10b981;
}

.wca-btn-icon-xs {
  background: transparent;
  border: none;
  color: #94a3b8;
  cursor: pointer;
  font-size: 0.95rem;
  padding: 0 2px;
  outline: none !important;
  line-height: 1;
}

.wca-btn-icon-xs:hover {
  color: #f43f5e;
}
</style>
