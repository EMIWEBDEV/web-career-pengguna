/**
 * WEB CAREER — ATURAN "KATA DIKENAL" untuk pemeriksa ejaan (nspell).
 *
 * Dipakai DUA tempat yang hasilnya wajib sama persis:
 *   • pekerja ejaan peramban (utils/career/ejaanWorker.js) — memeriksa kata
 *     yang diketik kandidat;
 *   • perakit daftar kata server (resources/kamus/rakit-daftar-kata.mjs,
 *     `npm run kamus`) — menulis resources/kamus/*-kata.txt yang dibaca
 *     App\Support\Career\Kamus.
 * Karena itu aturannya tinggal di satu berkas ini.
 */

/**
 * Kata (huruf kecil) dikenal sebuah kamus nspell bila bentuk kecilnya,
 * bentuk Kapitalnya ("Palembang"), atau bentuk KAPITAL-SEMUANYA ("KTP") ada
 * di kamus — kandidat sering mengetik nama tempat & singkatan dengan huruf kecil.
 */
export function kenalNspell(spell, kata) {
    const kapital = kata.charAt(0).toUpperCase() + kata.slice(1);

    return spell.correct(kata) || spell.correct(kapital) || spell.correct(kata.toUpperCase());
}

/** Isi resources/kamus/tambahan.txt → daftar kata (baris kosong & # diabaikan). */
export function bacaTambahan(teks) {
    return String(teks || '')
        .split(/\r?\n/)
        .map((b) => b.trim().toLowerCase())
        .filter((b) => b && !b.startsWith('#'));
}
