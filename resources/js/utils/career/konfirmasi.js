/**
 * WEB CAREER — KONFIRMASI KEHADIRAN: pembantu layar (tim & kandidat).
 *
 * Dipakai bersama oleh Agenda Seleksi, panel konfirmasi di drawer worklist,
 * jendela Atur Jadwal, dan halaman konfirmasi kandidat. Aturan yang BERLAKU
 * tetap milik server (KonfirmasiJadwal.php) — yang di sini hanya mempercepat
 * umpan balik dan menyeragamkan bahasa tampilan.
 */
import axios from 'axios';

const CFG = { headers: { Accept: 'application/json' } };

/**
 * Rona lembut dari warna master ('#16a34a', 0.08 → 'rgba(22, 163, 74, 0.08)')
 * — latar & garis panel status mengikuti warna statusnya tanpa color-mix(),
 * yang belum ada di peramban ponsel lama. Bukan hex → null (CSS memakai cadangannya).
 */
export function rona(warna, alfa) {
    const m = /^#?([0-9a-f]{3}|[0-9a-f]{6})$/i.exec(String(warna || '').trim());
    if (!m) return null;
    const h = m[1].length === 3 ? m[1].replace(/./g, (c) => c + c) : m[1];
    const n = parseInt(h, 16);

    return `rgba(${(n >> 16) & 255}, ${(n >> 8) & 255}, ${n & 255}, ${alfa})`;
}

// ── API KANDIDAT (tautan bertanda tangan, atau rute portal ber-sesi) ─────────

export async function kirimJawabanKandidat(url, payload) {
    const r = await axios.post(url, payload, CFG);

    return r.data;
}

export async function cabutPermintaanKandidat(url) {
    const r = await axios.post(url, {}, CFG);

    return r.data;
}

// ── GALAT ───────────────────────────────────────────────────────────────────

/** Galat axios → { status, pesan, result }. */
export function galatDari(e, cadangan = 'Terjadi kesalahan. Coba lagi.') {
    const status = e?.response?.status || 0;
    const data = e?.response?.data || {};
    const pertama = data.errors ? Object.values(data.errors).flat()[0] : null;

    let pesan = pertama || data.message || cadangan;
    if (!status) pesan = 'Tidak terhubung ke server. Periksa koneksi lalu coba lagi.';
    // Tanda tangan tautan kedaluwarsa / rusak (InvalidSignatureException).
    if (status === 403 && (!data.message || /signature/i.test(data.message))) {
        pesan = 'Tautan ini sudah tidak berlaku. Muat ulang halaman atau buka email terbaru.';
    }
    if (status === 419) pesan = 'Sesi kedaluwarsa. Muat ulang halaman lalu coba lagi.';
    if (status === 429) pesan = data.message || 'Terlalu banyak percobaan. Tunggu sebentar lalu coba lagi.';

    return { status, pesan, result: data.result ?? null };
}

/** SQL Server mengirim spasi, bukan 'T' — Safari menolak bentuk itu. */
export function urai(nilai) {
    if (!nilai) return null;
    if (nilai instanceof Date) return Number.isNaN(nilai.getTime()) ? null : nilai;
    const d = new Date(String(nilai).replace(' ', 'T'));

    return Number.isNaN(d.getTime()) ? null : d;
}

/** Selisih bertutur: '3 jam lagi', '2 hari lagi', 'lewat 40 menit'. */
export function sisaWaktu(nilai, sekarang = new Date()) {
    const d = urai(nilai);
    if (!d) return '';
    const menit = Math.round((d.getTime() - sekarang.getTime()) / 60000);
    const abs = Math.abs(menit);
    let teks;
    if (abs < 1) teks = 'kurang dari semenit';
    else if (abs < 60) teks = `${abs} menit`;
    else if (abs < 60 * 24) teks = `${Math.floor(abs / 60)} jam${abs % 60 && abs < 600 ? ` ${abs % 60} menit` : ''}`;
    else teks = `${Math.floor(abs / 1440)} hari${Math.floor((abs % 1440) / 60) ? ` ${Math.floor((abs % 1440) / 60)} jam` : ''}`;

    return menit >= 0 ? `${teks} lagi` : `lewat ${teks}`;
}

