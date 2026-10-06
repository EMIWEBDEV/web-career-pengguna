/* WEB CAREER — RENTANG TANGGAL YANG BISA DIBACA SEKALI LIHAT.
 *
 * Dipakai di mana pun sebuah PERIODE ditampilkan. Satu tanggal saja tidak
 * pernah cukup: "01 Oktober 2026" di bawah label "Periode Target" tidak bisa
 * dibaca — itu tanggal mulai, tanggal selesai, atau hari apa? Yang dimaksud
 * rentang kerjanya, dari hari MPP dibuat sampai tenggat SLA-nya.
 *
 * Dua aturan yang berlaku di semua pemakainya:
 *
 *   TAHUN ditulis SEKALI bila keduanya setahun ("20 Agu – 01 Okt 2026"), dan
 *   DUA KALI bila menyeberang tahun — di situ tahunnya justru bagian yang
 *   paling perlu terlihat.
 *
 *   TANPA AWAL YANG SAH, tidak ada rentang. MPP yang lahir sebelum kolom
 *   snapshot SLA ada tidak menyimpan tanggal mulai; menambalnya dengan "hari
 *   ini" mencetak rentang terbalik ("20 Agustus – 1 Juli"), yang lebih buruk
 *   daripada tidak ada rentang sama sekali. Di situ tenggatnya ditulis
 *   sendirian — dan pemakainya wajib mengganti labelnya jadi "Tenggat".
 */

const PENDEK = { day: '2-digit', month: 'short' };
const PANJANG = { day: 'numeric', month: 'long' };

function tanggal(v) {
    if (! v) return null;

    const d = new Date(`${v}T00:00:00`);

    return Number.isNaN(d.getTime()) ? null : d;
}

function tulis(d, gaya, tahun) {
    return d.toLocaleDateString('id-ID', { ...gaya, ...(tahun ? { year: 'numeric' } : {}) });
}

/** Rentangnya bisa ditulis? — awalnya ada, sah, dan tidak melewati akhirnya. */
export function punyaRentang(mulai, akhir) {
    const a = tanggal(mulai);
    const b = tanggal(akhir);

    return !! (a && b && a <= b);
}

function rentang(mulai, akhir, gaya) {
    const b = tanggal(akhir);
    if (! b) return '—';

    const a = tanggal(mulai);
    if (! a || a > b) return tulis(b, gaya, true);

    return `${tulis(a, gaya, a.getFullYear() !== b.getFullYear())} – ${tulis(b, gaya, true)}`;
}

/** "20 Agu – 01 Okt 2026" — untuk kartu & tempat sempit lain. */
export function rentangPendek(mulai, akhir) {
    return rentang(mulai, akhir, PENDEK);
}

/** "20 Agustus – 1 Oktober 2026" — untuk borang & panel detail. */
export function rentangPanjang(mulai, akhir) {
    return rentang(mulai, akhir, PANJANG);
}
