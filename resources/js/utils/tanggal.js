/**
 * TANGGAL YANG DIBACA ORANG — satu bentuk, satu tempat.
 *
 * ══ KENAPA SATU BERKAS ══════════════════════════════════════════════════════
 *
 * Sebelumnya tiap halaman membawa peta bulannya sendiri dan pemformatnya
 * sendiri. Hasilnya tiga bentuk berbeda pada satu layar yang sama: window
 * pendaftaran tertulis "5 Agu 2026 21:16", kolom dibuat tertulis
 * "2026-08-22 21:15:12.997" apa adanya dari basis data, dan yang ketiga
 * tergantung halaman mana yang kebetulan dibuka.
 *
 * Yang terakhir itu yang paling buruk: `2026-08-22 21:15:12.997` bukan tanggal
 * yang ditulis siapa pun di Indonesia, dan tiga angka di belakang koma adalah
 * milidetik — ketelitian yang tidak pernah dibutuhkan mata, tapi tetap harus
 * dilewati untuk sampai ke jamnya.
 *
 * ══ BENTUKNYA ══════════════════════════════════════════════════════════════
 *
 *      tglJam('2026-08-05 11:45:00')  ->  '05 Agu 2026 11:45'
 *      tgl('2026-08-05')              ->  '05 Agu 2026'
 *      tglPendek('2026-08-05')        ->  '05 Agu'
 *      tglPanjang('2026-08-05')       ->  '05 Agustus 2026'
 *
 * Hari SELALU dua digit. "5 Agu" dan "05 Agu" sama-sama benar dibaca, tapi di
 * dalam daftar yang panjang lebar yang berubah-ubah membuat kolom tanggalnya
 * bergerigi — dan mata kehilangan garis lurus yang dipakainya memindai.
 *
 * ══ KENAPA BULANNYA DISINGKAT ══════════════════════════════════════════════
 *
 * Panel admin penuh kolom sempit: baris daftar, sel tabel, kaki kartu. Nama
 * bulan penuh ("September") membuat kolom tanggal melebar mengikuti bulan
 * TERPANJANG yang kebetulan muncul, lalu menyempit lagi bulan berikutnya —
 * lebar yang bergoyang tanpa ada yang mengubah apa pun. Singkatan tiga huruf
 * lebarnya tetap sepanjang tahun.
 *
 * Bentuk panjangnya tidak hilang: `tglPanjang()` menyediakannya untuk halaman
 * publik yang memang lapang dan bernada resmi (surat, detail lowongan).
 */

const BULAN = [
    'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
    'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
];

/** Singkatan tiga huruf — bentuk baku panel admin. */
const BULAN_PENDEK = BULAN.map((b) => b.slice(0, 3));

const dua = (n) => String(n).padStart(2, '0');

/**
 * Ubah apa pun yang datang dari server jadi Date — atau null.
 *
 * SQL Server mengirim `2026-08-22 21:15:12.997` dengan SPASI, bukan `T`.
 * Safari menolak bentuk itu dan memulangkan Invalid Date, sementara Chrome
 * menerimanya — jadi tanggalnya benar di layar yang dipakai menguji dan kosong
 * di layar sebagian pemakai. Spasinya diganti sebelum diurai.
 */
function urai(nilai) {
    if (!nilai) return null;
    if (nilai instanceof Date) return Number.isNaN(nilai.getTime()) ? null : nilai;

    const d = new Date(String(nilai).replace(' ', 'T'));

    return Number.isNaN(d.getTime()) ? null : d;
}

/**
 * BENTUK BAKU: '2026-08-05 11:45:00' -> '05 Agu 2026 11:45'.
 *
 * Ini yang dipakai untuk SETIAP stempel waktu di panel admin — dibuat, diubah,
 * window pendaftaran, jadwal. Kosong / tidak terbaca -> tanda pisah.
 */
export function tglJam(nilai, kosong = '—') {
    const d = urai(nilai);
    if (!d) return kosong;

    return `${dua(d.getDate())} ${BULAN_PENDEK[d.getMonth()]} ${d.getFullYear()} ${dua(d.getHours())}:${dua(d.getMinutes())}`;
}

/** '2026-08-05' -> '05 Agu 2026'. Bentuk baku, tanpa jam. */
export function tgl(nilai, kosong = '—') {
    const d = urai(nilai);
    if (!d) return kosong;

    return `${dua(d.getDate())} ${BULAN_PENDEK[d.getMonth()]} ${d.getFullYear()}`;
}

/**
 * '2026-08-05' -> '05 Agustus 2026'.
 *
 * Bulan dieja penuh. Untuk halaman publik dan dokumen yang bernada resmi, yang
 * ruangnya lapang dan pembacanya bukan orang yang memindai daftar.
 */
export function tglPanjang(nilai, kosong = '—') {
    const d = urai(nilai);
    if (!d) return kosong;

    return `${dua(d.getDate())} ${BULAN[d.getMonth()]} ${d.getFullYear()}`;
}

/**
 * Bentuk sependek mungkin untuk baris daftar: '05 Agu'.
 *
 * Tahun sengaja dibuang. Di daftar yang isinya hampir seluruhnya tahun berjalan,
 * "2026" berulang di setiap baris tidak membedakan apa pun — ia hanya memakan
 * lebar yang dibutuhkan nama programnya.
 */
export function tglPendek(nilai, kosong = '—') {
    const d = urai(nilai);
    if (!d) return kosong;

    return `${dua(d.getDate())} ${BULAN_PENDEK[d.getMonth()]}`;
}
