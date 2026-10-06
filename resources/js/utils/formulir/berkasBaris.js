/**
 * WEB CAREER — IDENTITAS BERKAS DI DALAM BAGIAN BERULANG (sisi browser).
 *
 * Cerminan dari App\Support\Career\BerkasBaris. Dua salinan aturan yang sama
 * adalah utang: cepat atau lambat keduanya berbeda, dan berkas yang diunggah
 * browser tidak lagi ketemu dengan yang dicari server. FORMAT_KUNCI dan
 * FORMAT_LABEL di bawah dibandingkan langsung dengan konstanta PHP-nya oleh
 * tests/Unit/BerkasBarisSinkronTest.php — mengubah salah satu tanpa yang lain
 * akan memerahkan uji itu.
 */

/** Susunan kunci komposit. HARUS sama dengan BerkasBaris::FORMAT_KUNCI. */
export const FORMAT_KUNCI = '{bagian}[{baris}].{field}';

/** Susunan label bernomor. HARUS sama dengan BerkasBaris::FORMAT_LABEL. */
export const FORMAT_LABEL = '{label} #{nomor}';

/**
 * Kunci komposit sebuah berkas.
 *
 * Bagian tanpa baris (atau sebaliknya) jatuh ke `field` saja — itu bukan
 * identitas berulang yang sah.
 */
export function kunciBerkas(bagian, baris, field) {
    if (bagian === null || bagian === undefined || bagian === '' || baris === null || baris === undefined) {
        return field;
    }

    return FORMAT_KUNCI.replace('{bagian}', bagian)
        .replace('{baris}', String(baris))
        .replace('{field}', field);
}

/** Label bernomor untuk tampilan. Nomornya berbasis 1 — yang dibaca manusia. */
export function labelBerkas(label, baris) {
    if (baris === null || baris === undefined) {
        return label;
    }

    return FORMAT_LABEL.replace('{label}', label).replace('{nomor}', String(baris + 1));
}
