import { onMounted, onUnmounted } from 'vue'

/**
 * Esc untuk UI bertumpuk (modal → offcanvas → offcanvas → lightbox).
 *
 * Tanpa ini, tiap lapis memasang listener sendiri di `document` dan SATU Esc
 * menutup semuanya sekaligus: `stopPropagation()` hanya menghentikan
 * perambatan ke node berikutnya, bukan listener lain pada node yang sama.
 *
 * Solusinya satu tumpukan bersama — hanya lapis paling atas yang menanggapi
 * Esc, sehingga lapisan tertutup satu per satu sesuai urutan pembukaannya.
 */
const tumpukan = []

export function useLapisEsc(tutup) {
    const token = {}

    function onKey(e) {
        if (e.key !== 'Escape') return
        if (tumpukan[tumpukan.length - 1] !== token) return // bukan lapis teratas
        e.stopPropagation()
        e.preventDefault()
        tutup()
    }

    onMounted(() => {
        tumpukan.push(token)
        document.addEventListener('keydown', onKey, true)
    })

    onUnmounted(() => {
        const i = tumpukan.indexOf(token)
        if (i >= 0) tumpukan.splice(i, 1)
        document.removeEventListener('keydown', onKey, true)
    })
}
