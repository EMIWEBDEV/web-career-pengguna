import { ref, onMounted, onUnmounted } from 'vue'
import axios from 'axios'

export function useExportNotification() {
    const items = ref([])
    let pollTimer = null
    let isPolling = false
    const POLL_INTERVAL = 5000

    async function poll() {
        try {
            const { data } = await axios.get('/api/v1/karir/export/poll')
            items.value = data.result || []
            const hasActive = items.value.some(i => i.Status_Export === 'DIPROSES')
            if (!hasActive && items.value.length === 0) {
                stopPolling()
            }
        } catch (e) {
            /* silent — notifikasi export tidak kritis */
        }
    }

    function startPolling() {
        if (isPolling) return
        isPolling = true
        poll()
        pollTimer = setInterval(poll, POLL_INTERVAL)
    }

    function stopPolling() {
        isPolling = false
        if (pollTimer) {
            clearInterval(pollTimer)
            pollTimer = null
        }
    }

    async function dismiss(id) {
        try {
            await axios.delete(`/api/v1/karir/export/${id}`)
            items.value = items.value.filter(i => i.Id_Export !== id)
        } catch (e) {
            /* silent */
        }
    }

    onMounted(() => startPolling())
    onUnmounted(() => stopPolling())

    return { items, dismiss }
}
