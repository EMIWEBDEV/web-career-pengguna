/**
 * JEJAK PENYARING PUSTAKA PERTANYAAN — dipakai bersama dua halaman.
 *
 * ══ MASALAH YANG DISELESAIKAN ══════════════════════════════════════════════
 *
 * Penyaring pustaka ada tujuh: departemen, level, tipe kandidat, fase,
 * kompetensi, jenis, prioritas. Menampilkan tujuh kotak sekaligus memakan
 * sepertiga tinggi jendela sebelum satu pertanyaan pun terlihat, jadi lima di
 * antaranya disembunyikan di balik tombol.
 *
 * Tapi penyaring yang tersembunyi adalah penyaring yang DILUPAKAN. Orang
 * menutup panelnya, lalu keheranan kenapa dari 1.022 pertanyaan cuma 16 yang
 * muncul — dan menyalahkan datanya, bukan penyaringnya.
 *
 * Maka apa pun yang sedang aktif selalu tergambar sebagai keping, di luar
 * panel, entah panelnya terbuka atau tertutup. Kepingnya sendiri yang jadi
 * tombol pelepasnya: yang menyembunyikan hasil dan yang membatalkannya adalah
 * benda yang sama, sehingga tidak ada yang perlu dicari.
 *
 * ══ KENAPA FUNGSI, BUKAN MIXIN ═════════════════════════════════════════════
 *
 * Kedua halaman menamai keadaannya berbeda (`f`/`cari` di Master Pertanyaan,
 * `fBank`/`cariBank` di pemilih Master Skrining). Mixin harus dibuat bisa
 * diatur namanya, dan itu lebih rumit daripada memanggil fungsi murni dengan
 * data apa adanya.
 */

/**
 * Urutan sengaja: yang paling sering dipakai lebih dulu, supaya kepingnya
 * tidak melompat-lompat posisi ketika penyaring lain ditambah atau dilepas.
 */
const DIMENSI = [
    { kunci: 'jf', dim: 'Departemen', tag: 'JOB_FAMILY' },
    { kunci: 'level', dim: 'Level', tag: 'LEVEL' },
    { kunci: 'ct', dim: 'Tipe', tag: 'CANDIDATE_TYPE' },
    { kunci: 'fase', dim: 'Fase', tag: 'FASE' },
    { kunci: 'komp', dim: 'Kompetensi', tag: 'KOMPETENSI' },
];

/** Penyaring yang tinggal di balik tombol geser — dihitung untuk lencananya. */
export const LANJUT = ['ct', 'fase', 'komp', 'jenis', 'prioritas'];

/**
 * Daftar keping penyaring yang sedang aktif.
 *
 * @param {object}   o
 * @param {string}   o.cari            teks pencarian, boleh kosong
 * @param {object}   o.nilai           { jf, level, ct, fase, komp, jenis, prioritas }
 * @param {object}   o.tag             peta dimensi → daftar tag { kode, nama, warna }
 * @param {Function} o.labelJenis
 * @param {Function} o.labelPrioritas
 * @returns {Array<{id: string, kunci: string, kode: string, dim: string, nama: string, warna: string|null}>}
 */
export function chipPenyaring({ cari, nilai, tag, labelJenis, labelPrioritas }) {
    const out = [];

    if (cari) {
        out.push({ id: 'cari', kunci: 'cari', kode: cari, dim: 'Cari', nama: cari, warna: null });
    }

    for (const d of DIMENSI) {
        const daftar = tag?.[d.tag] || [];
        for (const kode of nilai?.[d.kunci] || []) {
            // Tag bisa saja sudah dihapus dari master sementara pilihannya masih
            // tersangkut di keadaan halaman. Kodenya tetap ditampilkan supaya
            // penyaringnya bisa dilepas, bukan menghilang jadi keping kosong.
            const t = daftar.find((x) => x.kode === kode);
            out.push({
                id: `${d.kunci}:${kode}`,
                kunci: d.kunci,
                kode,
                dim: d.dim,
                nama: t?.nama || kode,
                warna: t?.warna || null,
            });
        }
    }

    for (const kode of nilai?.jenis || []) {
        out.push({ id: `jenis:${kode}`, kunci: 'jenis', kode, dim: 'Jenis', nama: labelJenis(kode), warna: null });
    }
    for (const kode of nilai?.prioritas || []) {
        out.push({ id: `prioritas:${kode}`, kunci: 'prioritas', kode, dim: 'Prioritas', nama: labelPrioritas(kode), warna: null });
    }

    return out;
}

/** Berapa penyaring yang aktif di balik tombol geser — isi lencananya. */
export function jmlLanjut(nilai) {
    return LANJUT.reduce((n, k) => n + (nilai?.[k]?.length || 0), 0);
}
