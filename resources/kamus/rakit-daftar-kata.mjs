/**
 * RAKIT DAFTAR KATA SERVER — `npm run kamus`
 *
 * Peramban memeriksa ejaan dengan nspell langsung dari kamus Hunspell di
 * folder ini (id_ID.aff/.dic, en_US.aff/.dic). Server (PHP) tidak bisa
 * menjalankan nspell, jadi skrip ini MENGURAI kamus yang sama dengan nspell
 * yang sama lalu menuliskan semua bentuk katanya — termasuk turunan berimbuhan
 * (membatalkan, kepindahan, dipindahkan, …) — sebagai daftar terurut:
 *
 *     id-kata.txt, en-kata.txt   satu kata per baris, huruf kecil, urut byte
 *
 * App\Support\Career\Kamus mencari di daftar itu dengan pencarian biner.
 * Aturan "kata dikenal" diambil dari utils/career/ejaanAturan.js — berkas
 * yang sama dengan pekerja ejaan peramban — dan setiap kata diuji ulang
 * sebelum ditulis, jadi peramban & server mustahil berbeda pendapat.
 *
 * Jalankan ulang setiap kali .aff/.dic diganti (tambahan.txt TIDAK perlu —
 * dibaca langsung oleh keduanya).
 *
 * Lisensi kamus: id_ID — hunspell-id, LGPL-3.0 (LISENSI-id_ID.txt);
 * en_US — SCOWL, MIT AND BSD (LISENSI-en_US.txt). Daftar kata hasil urai
 * mewarisi lisensi kamusnya masing-masing.
 */
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import nspell from 'nspell';
import { bacaTambahan, kenalNspell } from '../js/utils/career/ejaanAturan.js';

const DIR = path.dirname(fileURLToPath(import.meta.url));
const KAMUS = [
    { nama: 'id_ID', keluaran: 'id-kata.txt' },
    { nama: 'en_US', keluaran: 'en-kata.txt' },
];
/** Hanya kata berhuruf utuh — pemeriksa memecah teks per deret huruf, jadi
 *  entri berspasi/bertanda hubung ("Banda Aceh", "anak-anak") tak pernah dicari. */
const HURUF = /^\p{L}+$/u;

const tambahan = bacaTambahan(fs.readFileSync(path.join(DIR, 'tambahan.txt'), 'utf8'));
const spellSemua = [];

for (const { nama, keluaran } of KAMUS) {
    const t0 = Date.now();
    const spell = nspell(
        fs.readFileSync(path.join(DIR, `${nama}.aff`), 'utf8'),
        fs.readFileSync(path.join(DIR, `${nama}.dic`), 'utf8'),
    );
    spellSemua.push(spell);

    const kata = new Set();
    for (const entri of Object.keys(spell.data)) {
        const kecil = entri.toLowerCase();
        if (HURUF.test(kecil) && kenalNspell(spell, kecil)) kata.add(kecil);
    }

    // UJI PARITAS: nspell menilai kata dari data hasil urainya (plus variasi
    // huruf besar) — kecuali COMPOUNDRULE, yang menilai saat pemeriksaan.
    // 20.000 "salah ketik" buatan (huruf dihapus/ditukar/diganti) dari kata
    // sungguhan dibandingkan: nspell vs daftar. Satu saja beda → batal.
    const contoh = [...kata];
    const acak = (n) => Math.floor(Math.random() * n);
    let selisih = 0;
    for (let i = 0; i < 20000; i++) {
        const k = contoh[acak(contoh.length)];
        const p = acak(k.length);
        const ubah = [
            k.slice(0, p) + k.slice(p + 1),
            k.slice(0, p) + String.fromCharCode(97 + acak(26)) + k.slice(p + 1),
            k.slice(0, p) + k.charAt(p + 1) + k.charAt(p) + k.slice(p + 2),
        ][i % 3];
        if (ubah && HURUF.test(ubah) && kenalNspell(spell, ubah) !== kata.has(ubah)) selisih++;
    }
    if (selisih) throw new Error(`${nama}: ${selisih} kata dinilai berbeda oleh nspell dan daftar — daftar TIDAK ditulis.`);

    // Urut BYTE (UTF-8) — sama dengan strcmp() di PHP.
    const urut = [...kata].map((k) => ({ k, b: Buffer.from(k, 'utf8') })).sort((x, y) => Buffer.compare(x.b, y.b)).map((x) => x.k);
    fs.writeFileSync(path.join(DIR, keluaran), urut.join('\n') + '\n');
    console.log(`${nama}: ${urut.length} kata → ${keluaran} (${(fs.statSync(path.join(DIR, keluaran)).size / 1048576).toFixed(2)} MB, ${Date.now() - t0} ms)`);
}

const sudahAda = tambahan.filter((k) => spellSemua.some((s) => kenalNspell(s, k)));
console.log(`tambahan.txt: ${tambahan.length} kata${sudahAda.length ? ` — sudah dikenal kamus (boleh dibuang): ${sudahAda.join(', ')}` : ''}`);
