/**
 * WEB CAREER — PEKERJA EJAAN (Web Worker, nspell).
 *
 * Kamus Hunspell Indonesia (hunspell-id) membengkak jadi ±470 ribu bentuk
 * kata saat diurai nspell — ±1–2 detik & ±50 MB di desktop, lebih lama di
 * ponsel. Karena itu diurai DI SINI, di luar utas halaman: formulir tetap
 * lancar diketik selama kamus dimuat. Kamus Inggris (lebih ringan) hanya
 * dimuat bila diminta — kalimat Indonesia biasanya sudah lolos tanpanya.
 *
 * Pesan masuk:  { no, kata: string[] (huruf kecil), inggris: boolean }
 * Pesan keluar: { no, dikenal: boolean[] }  atau  { no, galat }
 *
 * Aturan "kata dikenal" = utils/career/ejaanAturan.js — sama dengan daftar
 * kata server (Kamus.php, hasil `npm run kamus`).
 */
import nspell from 'nspell';
import { bacaTambahan, kenalNspell } from './ejaanAturan';
import idAff from '@kamus/id_ID.aff?url';
import idDic from '@kamus/id_ID.dic?url';
import enAff from '@kamus/en_US.aff?url';
import enDic from '@kamus/en_US.dic?url';
import tambahanTeks from '@kamus/tambahan.txt?raw';

const TAMBAHAN = new Set(bacaTambahan(tambahanTeks));
const BERKAS = { id: [idAff, idDic], en: [enAff, enDic] };
const kamus = {};

async function teks(url) {
    // Terhadap URL modul ini, bukan lokasi pekerja: pekerja yang dimulai dari
    // Blob (dev) tidak punya alamat dasar untuk jalur relatif "/build/…".
    const r = await fetch(new URL(url, import.meta.url));
    if (!r.ok) throw new Error(`Kamus gagal dimuat (${r.status}).`);

    return r.text();
}

/** Satu kamus diurai sekali; gagal dimuat = dicoba lagi pada permintaan berikutnya. */
function muat(nama) {
    if (!kamus[nama]) {
        const [aff, dic] = BERKAS[nama];
        kamus[nama] = Promise.all([teks(aff), teks(dic)]).then(([a, d]) => nspell(a, d));
        kamus[nama].catch(() => { delete kamus[nama]; });
    }

    return kamus[nama];
}

self.onmessage = async ({ data }) => {
    const { no, kata = [], inggris = false } = data || {};
    try {
        const id = await muat('id');
        const en = inggris ? await muat('en') : null;
        const dikenal = kata.map((k) => TAMBAHAN.has(k) || kenalNspell(id, k) || (!!en && kenalNspell(en, k)));
        self.postMessage({ no, dikenal });
    } catch (e) {
        self.postMessage({ no, galat: String(e?.message || e) });
    }
};
