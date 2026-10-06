/**
 * LAYAR SEMPIT — pengamat yang IKUT BERUBAH saat jendelanya diubah ukurannya.
 *
 * ══ KENAPA TIDAK CUKUP layarLebar() ═════════════════════════════════════════
 *
 * `layarLebar()` menjawab satu kali, saat dipanggil. Itu memang yang dibutuhkan
 * untuk keputusan sekali jalan seperti "pilih butir pertama otomatis atau
 * tidak". Tapi ada keputusan yang harus IKUT berubah selama halaman terbuka:
 * apakah panel filter dirender di tempatnya (kartu di kolom kiri) atau
 * dipindahkan ke <body> sebagai lembar bawah.
 *
 * Jawaban sekali jalan membuatnya salah begitu jendela diubah ukurannya — dan
 * salahnya diam: panelnya tetap di tempat lama, lembar bawahnya tidak pernah
 * muncul, dan tidak ada galat apa pun yang bisa dilacak.
 *
 * Ambangnya sengaja SAMA dengan layarLebar(): 1024px. Kalau salah satunya
 * bergeser tanpa yang lain, gejalanya justru yang paling sulit dilacak — benar
 * di kedua ujung, salah di antaranya.
 *
 * Pakai sebagai mixin:
 *
 *     import { layarSempit } from '@utils/layarSempit';
 *     export default { mixins: [layarSempit()], ... }   // -> this.sempit
 */

const KUERI = '(max-width: 1023px)';

export function layarSempit() {
    return {
        data() {
            return {
                sempit: typeof window !== 'undefined' && window.matchMedia
                    ? window.matchMedia(KUERI).matches
                    // SSR / lingkungan tanpa jendela: anggap lebar. Menganggapnya
                    // sempit akan membuat panel filter terbit di <body> pada
                    // halaman yang sebenarnya berkolom dua.
                    : false,
                layarSempitMq: null,
                layarSempitDengar: null,
            };
        },
        mounted() {
            if (typeof window === 'undefined' || !window.matchMedia) return;

            this.layarSempitMq = window.matchMedia(KUERI);
            this.layarSempitDengar = (e) => { this.sempit = e.matches; };

            // addEventListener('change') tidak ada di Safari lawas; addListener
            // yang usang itulah satu-satunya yang jalan di sana.
            if (this.layarSempitMq.addEventListener) {
                this.layarSempitMq.addEventListener('change', this.layarSempitDengar);
            } else {
                this.layarSempitMq.addListener(this.layarSempitDengar);
            }
        },
        beforeUnmount() {
            if (!this.layarSempitMq || !this.layarSempitDengar) return;

            if (this.layarSempitMq.removeEventListener) {
                this.layarSempitMq.removeEventListener('change', this.layarSempitDengar);
            } else {
                this.layarSempitMq.removeListener(this.layarSempitDengar);
            }
        },
    };
}

export default layarSempit;
