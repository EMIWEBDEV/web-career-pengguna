/**
 * WEB CAREER — PEMERIKSA EJAAN (sisi halaman).
 *
 * Membungkus pekerja ejaan (ejaanWorker.js, nspell): dibuat sekali, saat
 * pertama dibutuhkan. Peramban yang tidak sanggup menjalankannya (pekerja
 * modul tidak didukung, kamus gagal diunduh, terlalu lama) mendapat `null` —
 * pemanggil kembali ke pemeriksaan tanpa kamus, dan server tetap memutuskan
 * dengan kamus yang sama (Kamus.php).
 */

import urlPekerja from './ejaanWorker.js?worker&url';

/** Batas tunggu satu pemeriksaan — termasuk unduh & urai kamus pertama kali di ponsel lambat. */
const BATAS_MS = 25000;

/**
 * Pekerja dibuat dari URL modulnya. Di produksi sesama-origin (/build/assets).
 * Di dev, modulnya dilayani server Vite (http://localhost:5180) sementara
 * halamannya https://web-careers.test — peramban menolak pekerja lintas-origin,
 * jadi pekerja dimulai dari Blob sesama-origin yang meng-import modul itu.
 */
function buatPekerja() {
    const url = new URL(urlPekerja, import.meta.url);
    if (url.origin === location.origin) return new Worker(url, { type: 'module' });
    const blob = new Blob([`import ${JSON.stringify(url.href)};`], { type: 'text/javascript' });

    return new Worker(URL.createObjectURL(blob), { type: 'module' });
}

let pekerja = null; // Worker | false (tidak tersedia)
let nomor = 0;
const menunggu = new Map();

function lepasSemua(galat) {
    for (const j of menunggu.values()) j.tolak(galat);
    menunggu.clear();
}

function siapkan() {
    if (pekerja !== null) return pekerja;
    try {
        pekerja = buatPekerja();
        pekerja.onmessage = ({ data }) => {
            const j = menunggu.get(data?.no);
            if (!j) return;
            menunggu.delete(data.no);
            if (data.galat) j.tolak(new Error(data.galat));
            else j.terima(data.dikenal);
        };
        pekerja.onerror = (e) => {
            lepasSemua(e);
            pekerja?.terminate?.();
            pekerja = false;
        };
    } catch (e) {
        pekerja = false;
    }

    return pekerja;
}

/**
 * Kata-kata (huruf kecil) dikenal kamus? → boolean[] sejajar `kata`, atau
 * null bila pemeriksa ejaan tidak tersedia di peramban ini.
 *
 * @param {string[]} kata
 * @param {boolean} inggris  ikut kamus Inggris (dimuat saat pertama diminta)
 */
export async function kenaliKata(kata, inggris = false) {
    const w = siapkan();
    if (!w) return null;
    const no = ++nomor;
    let tm = null;
    try {
        return await new Promise((terima, tolak) => {
            menunggu.set(no, { terima, tolak });
            tm = setTimeout(() => {
                menunggu.delete(no);
                tolak(new Error('Pemeriksa ejaan terlalu lama.'));
            }, BATAS_MS);
            w.postMessage({ no, kata, inggris });
        });
    } catch (e) {
        return null;
    } finally {
        clearTimeout(tm);
    }
}

/** Muat kamus Indonesia lebih awal (saat kolomnya muncul) — pemeriksaan pertama jadi cepat. */
export function siapkanEjaan() {
    kenaliKata([]).catch(() => {});
}
