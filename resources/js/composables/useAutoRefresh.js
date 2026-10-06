import { ref, watch, onMounted, onUnmounted } from 'vue'

/**
 * Auto-refresh berkala + refresh saat tab kembali terlihat.
 * Dipakai halaman monitoring (pola timer sama dengan useExportNotification).
 *
 * - intervalSec: 0 = off; ganti nilai → timer di-arm ulang otomatis.
 * - Saat tab hidden timer dijeda (hemat server); saat kembali visible
 *   langsung refresh sekali lalu timer jalan lagi.
 * - Guard overlap: tick dilewati bila fetch sebelumnya masih berjalan.
 */
export function useAutoRefresh(refreshFn, { initial = 30 } = {}) {
    const intervalSec = ref(initial)
    const busy = ref(false)
    let timer = null

    async function refreshNow() {
        if (busy.value) return
        busy.value = true
        try {
            await refreshFn()
        } finally {
            busy.value = false
        }
    }

    function disarm() {
        if (timer) {
            clearInterval(timer)
            timer = null
        }
    }

    function arm() {
        disarm()
        if (intervalSec.value > 0 && !document.hidden) {
            timer = setInterval(() => {
                if (!document.hidden) refreshNow()
            }, intervalSec.value * 1000)
        }
    }

    function onVisibility() {
        if (document.hidden) {
            disarm()
        } else {
            refreshNow()
            arm()
        }
    }

    watch(intervalSec, arm)

    onMounted(() => {
        document.addEventListener('visibilitychange', onVisibility)
        arm()
    })
    onUnmounted(() => {
        disarm()
        document.removeEventListener('visibilitychange', onVisibility)
    })

    return { intervalSec, busy, refreshNow }
}
