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

