/**
 * WEB CAREER — PRATINJAU PDF DI DALAM HALAMAN (pdf.js).
 *
 * Kenapa tidak cukup <iframe> berisi pembaca PDF bawaan peramban: di bingkai
 * selebar panel (±550px) pembaca Chrome membuka bilah thumbnail lalu
 * menghitung zoom dengan bilah itu, sehingga lembarnya tampil sebesar perangko
 * di tengah panel — dan menyembunyikan bilahnya (`#navpanes=0`) tidak membuat
 * zoom-nya dihitung ulang. pdf.js menggambar tiap halaman tepat selebar panel,
 * sama di semua peramban.
 *
 * Pustakanya BESAR (±1,8 MB bersama worker-nya), jadi dimuat hanya saat
 * pratinjau benar-benar ditampilkan — ponsel tidak pernah mengunduhnya. Build
 * `legacy` dipakai karena kandidat datang dengan peramban apa saja; build
 * modern menuntut fitur yang belum ada di Safari/Chrome yang sedikit lama.
 */

let pustaka = null;

/** Muat pdf.js (sekali) beserta worker-nya. */
export function muatPustakaPdf() {
    if (!pustaka) {
        pustaka = Promise.all([
            import('pdfjs-dist/legacy/build/pdf.min.mjs'),
            import('pdfjs-dist/legacy/build/pdf.worker.min.mjs?url'),
        ])
            .then(([lib, worker]) => {
                lib.GlobalWorkerOptions.workerSrc = worker.default;

                return lib;
            })
            .catch((e) => {
                // Gagal sekali (mis. jaringan putus) tidak boleh mengunci
                // pratinjau selamanya — percobaan berikutnya memuat ulang.
                pustaka = null;
                throw e;
            });
    }

    return pustaka;
}

/**
 * Buka satu PDF. Mengembalikan TUGAS pemuatannya (bukan dokumennya) supaya
 * pemanggil bisa membatalkannya (`destroy()`) bila surat lain keburu dipilih.
 *
 * Rentang (Range) dimatikan: rute surat mengalirkan berkas utuh, dan surat
 * pengantar hanya beberapa halaman — meminta per potong hanya menambah
 * permintaan.
 */
export async function bukaPdf(url) {
    const lib = await muatPustakaPdf();

    return lib.getDocument({
        url,
        disableRange: true,
        disableStream: true,
        isEvalSupported: false,
    });
}

/**
 * Gambar satu halaman ke kanvas, selebar `lebarCss` piksel layar.
 *
 * Kanvasnya digambar pada kerapatan layar (paling banyak 2×) supaya huruf
 * tetap tajam di layar retina, lalu diperkecil lewat CSS.
 *
 * @returns {Promise<{ lebar: number, tinggi: number }>} ukuran CSS kanvasnya
 */
export async function gambarHalaman(doc, nomor, canvas, lebarCss) {
    const halaman = await doc.getPage(nomor);
    const dasar = halaman.getViewport({ scale: 1 });
    const skala = Math.max(0.1, lebarCss / dasar.width);
    const rapat = Math.min(window.devicePixelRatio || 1, 2);
    const viewport = halaman.getViewport({ scale: skala * rapat });

    canvas.width = Math.floor(viewport.width);
    canvas.height = Math.floor(viewport.height);
    const lebar = Math.floor(viewport.width / rapat);
    const tinggi = Math.floor(viewport.height / rapat);
    canvas.style.width = `${lebar}px`;
    canvas.style.height = `${tinggi}px`;

    await halaman.render({ canvas, viewport }).promise;
    halaman.cleanup();

    return { lebar, tinggi };
}

/** Rasio tinggi/lebar halaman — untuk menyiapkan tempatnya sebelum digambar. */
export async function rasioHalaman(doc, nomor) {
    const halaman = await doc.getPage(nomor);
    const v = halaman.getViewport({ scale: 1 });

    return v.width > 0 ? v.height / v.width : 1.414;
}
