/**
 * JADWAL PENGISIAN FORMULIR — hitungan perpanjangan untuk PRATINJAU di layar.
 *
 * Cermin App\Support\Career\BatasIsi::batasBaru(). Server tetap yang
 * memutuskan; di sini hanya supaya admin melihat batas barunya sebelum
 * menekan Perpanjang — terutama pada batas yang sudah lewat, tempat "+2 hari"
 * dihitung dari hari ini, bukan dari batas lamanya.
 *
 * Seluruh waktu berbentuk "YYYY-MM-DD HH:mm:ss" waktu setempat (WIB), sama
 * dengan yang dikirim & diterima server.
 */

/** Sekali jalan — sama dengan BatasIsiController::MAKS_HARI / MAKS_JAM. */
export const MAKS_HARI = 60;
export const MAKS_JAM = 72;

const HARI_MS = 86400000;

export function keDate(s) {
    if (!s) return null;
    const d = s instanceof Date ? new Date(s.getTime()) : new Date(String(s).replace(' ', 'T'));

    return Number.isNaN(d.getTime()) ? null : d;
}

export function keTeks(d) {
    const dua = (n) => String(n).padStart(2, '0');

    return `${d.getFullYear()}-${dua(d.getMonth() + 1)}-${dua(d.getDate())} ${dua(d.getHours())}:${dua(d.getMinutes())}:${dua(d.getSeconds())}`;
}

const HARI = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
const BULAN = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

/** "Rabu, 30 Sep 2026 pukul 23.59" — bentuk yang sama dengan BatasIsi::teks(). */
export function teksBatas(v) {
    const d = keDate(v);
    if (!d) return '—';
    const dua = (n) => String(n).padStart(2, '0');

    return `${HARI[d.getDay()]}, ${dua(d.getDate())} ${BULAN[d.getMonth()]} ${d.getFullYear()} pukul ${dua(d.getHours())}.${dua(d.getMinutes())}`;
}

/** Selisih hari KALENDER (bukan 24 jam) — sama dengan DATEDIFF(day, …) SQL Server. */
function selisihHariKalender(a, b) {
    const tengahMalam = (d) => new Date(d.getFullYear(), d.getMonth(), d.getDate()).getTime();

    return Math.round((tengahMalam(b) - tengahMalam(a)) / HARI_MS);
}

/**
 * Batas baru sebuah perpanjangan, atau null bila isiannya belum lengkap.
 *
 *   HARI    + N hari dari batas lama; bila sudah lewat, dari hari ini dengan
 *           jam batas lamanya.
 *   JAM     + N jam dari batas lama, atau dari sekarang bila sudah lewat.
 *   SAMPAI  tanggal & jam pilihan admin.
 *
 * @param {string|null} lama  batas sekarang; null = banyak kandidat, tiap orang dari batasnya sendiri
 * @param {{cara: string, hari: number, jam: number, sampai: string}} p
 */
export function batasBaru(lama, p, kini = new Date()) {
    if (p.cara === 'SAMPAI') return keDate(p.sampai);

    const d = keDate(lama);
    const n = Number(p.cara === 'HARI' ? p.hari : p.jam);
    if (!d || !(n >= 1)) return null;

    if (p.cara === 'HARI') {
        d.setDate(d.getDate() + (d < kini ? selisihHariKalender(d, kini) : 0) + n);

        return d;
    }

    return new Date((d < kini ? kini : d).getTime() + n * 3600000);
}

/**
 * Isian perpanjangan sah? null = sah; selain itu kalimat yang ditampilkan.
 *
 * @param {string|null} lama  lihat batasBaru()
 */
export function salahPerpanjang(lama, p, kini = new Date()) {
    if (p.cara === 'HARI' || p.cara === 'JAM') {
        const n = Number(p.cara === 'HARI' ? p.hari : p.jam);
        const maks = p.cara === 'HARI' ? MAKS_HARI : MAKS_JAM;
        if (!Number.isInteger(n) || n < 1) return 'Isi berapa lama perpanjangannya.';
        if (n > maks) return `Sekali jalan paling lama ${maks} ${p.cara === 'HARI' ? 'hari' : 'jam'} — ulangi bila perlu lebih.`;

        return null;
    }

    const sampai = keDate(p.sampai);
    if (!sampai) return 'Pilih tanggal & jam batas barunya.';
    if (sampai <= kini) return 'Waktu itu sudah lewat — pilih yang akan datang.';
    const d = keDate(lama);
    if (d && sampai <= d) return 'Harus lebih lambat dari batas sekarang — jadwal hanya bisa diperpanjang.';

    return null;
}
