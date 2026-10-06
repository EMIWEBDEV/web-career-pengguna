/**
 * NAMA ORANG YANG DIGAMBAR — inisial dan warnanya, satu tempat.
 *
 * ══ KENAPA WARNANYA DIHITUNG, BUKAN DISIMPAN ════════════════════════════════
 *
 * Program Kegiatan sudah memakai keping inisial, tapi warnanya diambil dari
 * kolom `warna` milik programnya — bukan milik orangnya. Di halaman master yang
 * tidak punya kolom seperti itu, satu-satunya pilihan lain adalah memberi semua
 * orang warna yang sama, dan keping abu-abu yang seragam tidak menambah apa pun
 * di atas teks biasa.
 *
 * Maka warnanya diturunkan dari namanya. Sifat yang dibutuhkan cuma satu:
 * REKRUTER1 harus selalu mendapat warna yang sama, di halaman mana pun, sesudah
 * muat ulang, di komputer siapa pun. Hash sederhana sudah cukup — ini hiasan
 * yang membantu mata mengenali orang, bukan pengenal yang dipercaya sistem.
 *
 * ══ KENAPA PALETNYA DIKUNCI ════════════════════════════════════════════════
 *
 * Warna acak dari roda HSL memang selalu berbeda, tapi separuhnya jatuh ke
 * kuning-hijau pucat yang teks putihnya tidak terbaca. Delapan warna berikut
 * dipilih dari palet evo yang sudah ada, semuanya cukup gelap untuk menampung
 * huruf putih.
 */

const PALET = [
    '#6366f1', // indigo   — primary
    '#8b5cf6', // ungu     — info
    '#0ea5e9', // langit
    '#059669', // hijau    — success gelap
    '#d97706', // amber    — warning gelap
    '#e11d48', // rose
    '#4f46e5', // indigo tua
    '#0891b2', // teal
];

/**
 * Dua huruf yang mewakili sebuah nama.
 *
 *      inisial('Frans Bachtiar')  ->  'FB'
 *      inisial('REKRUTER1')       ->  'RE'
 *      inisial(null)              ->  'SY'   (Sistem)
 *
 * Nama satu kata memakai dua huruf pertamanya, bukan satu huruf saja: keping
 * berisi satu huruf terlihat seperti gagal dimuat.
 */
export function inisial(nama) {
    if (!nama) return 'SY';

    const bagian = String(nama).trim().split(/\s+/);
    const dua = (bagian[0]?.[0] || '') + (bagian[1]?.[0] || bagian[0]?.[1] || '');

    return dua.toUpperCase() || 'SY';
}

/**
 * Warna tetap untuk sebuah nama. Nama kosong selalu abu — "Sistem" bukan orang,
 * dan memberinya warna cerah membuatnya tampak seperti salah satu rekruter.
 */
export function warnaOrang(nama) {
    if (!nama) return '#94a3b8';

    const teks = String(nama).trim().toUpperCase();
    let h = 0;
    for (let i = 0; i < teks.length; i += 1) {
        h = (h * 31 + teks.charCodeAt(i)) % 100000;
    }

    return PALET[h % PALET.length];
}
