/**
 * Apakah layarnya cukup lebar untuk DUA PANEL berdampingan?
 *
 * ══ KENAPA PERLU DITANYAKAN DI JAVASCRIPT ═══════════════════════════════════
 *
 * Panel jelajah memilih butir pertama secara otomatis begitu datanya dimuat —
 * di layar lebar itu benar: panel kanan yang kosong tidak memberi tahu apa pun,
 * sementara daftarnya tetap terlihat utuh di kiri.
 *
 * Di ponsel keduanya TIDAK berdampingan; panel isi MENGGANTIKAN daftarnya.
 * Memilih otomatis di sana berarti halaman terbuka langsung pada rincian butir
 * pertama, dengan daftarnya tersembunyi — orang yang baru membuka halaman
 * disodori satu program yang tidak ia minta, dan harus menekan "kembali" untuk
 * melihat apa saja yang sebenarnya ada.
 *
 * Ambangnya sengaja disamakan dengan titik henti di ExplorerLayout.vue. Kalau
 * salah satunya berubah tanpa yang lain, gejalanya justru yang paling sulit
 * dilacak: benar di kedua ujung, salah di antaranya.
 */
export function layarLebar() {
    // SSR / lingkungan tanpa jendela: anggap lebar. Menganggapnya sempit akan
    // membuat halaman terbit tanpa satu pun butir terpilih.
    if (typeof window === 'undefined' || !window.matchMedia) {
        return true;
    }

    return window.matchMedia('(min-width: 1024px)').matches;
}
