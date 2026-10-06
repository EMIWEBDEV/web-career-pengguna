/**
 * WEB CAREER — KONFIRMASI KEHADIRAN: pembantu layar (tim & kandidat).
 *
 * Dipakai bersama oleh Agenda Seleksi, panel konfirmasi di drawer worklist,
 * jendela Atur Jadwal, dan halaman konfirmasi kandidat. Aturan yang BERLAKU
 * tetap milik server (KonfirmasiJadwal.php) — yang di sini hanya mempercepat
 * umpan balik dan menyeragamkan bahasa tampilan.
 */
import axios from 'axios';

export const API_AGENDA = '/api/v1/karir/agenda-seleksi';
export const API_KONFIRMASI = '/api/v1/karir/konfirmasi-jadwal';

const CFG = { headers: { Accept: 'application/json' } };

/** Kanal yang disebut tim saat mencatat jawaban atas nama kandidat. */
export const KANAL_TIM = [
    { kode: 'TELEPON', label: 'Telepon', ikon: 'bi-telephone-fill' },
    { kode: 'WA', label: 'WhatsApp', ikon: 'bi-whatsapp' },
    { kode: 'EMAIL', label: 'Email', ikon: 'bi-envelope-fill' },
    { kode: 'LAYAR', label: 'Langsung', ikon: 'bi-person-badge-fill' },
];

/** Gaya cadangan bila master belum termuat. */
const GAYA = {
    MENUNGGU: { warna: '#94a3b8', ikon: 'bi-hourglass-split', label: 'Menunggu jawaban' },
    AKAN_HADIR: { warna: '#16a34a', ikon: 'bi-check-circle-fill', label: 'Akan hadir' },
    JADWAL_LAIN: { warna: '#d97706', ikon: 'bi-arrow-repeat', label: 'Minta jadwal lain' },
    MUNDUR: { warna: '#dc2626', ikon: 'bi-x-circle-fill', label: 'Menyatakan mundur' },
    TANPA_JAWABAN: { warna: '#ea580c', ikon: 'bi-exclamation-circle-fill', label: 'Tidak menjawab' },
    DITUNDA: { warna: '#7c3aed', ikon: 'bi-pause-circle-fill', label: 'Ditunda' },
};

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

export function gayaStatus(kode, dari = null) {
    const g = GAYA[kode] || { warna: '#94a3b8', ikon: 'bi-circle', label: kode || '—' };

    return {
        warna: dari?.warna || g.warna,
        ikon: dari?.ikon || g.ikon,
        label: dari?.label || dari?.statusLabel || g.label,
    };
}

// ── API TIM ─────────────────────────────────────────────────────────────────

export async function muatPerluTindakan(params = {}) {
    const r = await axios.get(`${API_AGENDA}/perlu-tindakan`, { ...CFG, params });

    return r.data.result;
}

export async function muatAgenda(params = {}) {
    const r = await axios.get(`${API_AGENDA}/agenda`, { ...CFG, params });

    return r.data.result;
}

export async function muatDetail(id) {
    const r = await axios.get(`${API_KONFIRMASI}/${encodeURIComponent(id)}`, CFG);

    return r.data.result;
}

let janjiOpsiTunda = null;

/**
 * Master alasan tunda + rentang tanggal — dimuat sekali per halaman (jarang
 * berubah); gagal = dicoba lagi pada pembukaan berikutnya.
 */
export function muatOpsiTunda() {
    janjiOpsiTunda ??= axios.get(`${API_KONFIRMASI}/opsi-tunda`, CFG)
        .then((r) => r.data.result)
        .catch((e) => {
            janjiOpsiTunda = null;
            throw e;
        });

    return janjiOpsiTunda;
}

export async function catatJawaban(id, payload) {
    const r = await axios.post(`${API_KONFIRMASI}/${encodeURIComponent(id)}/catat`, payload, CFG);

    return r.data;
}

export async function setujuiPermintaan(pid, payload) {
    const r = await axios.post(`${API_KONFIRMASI}/permintaan/${encodeURIComponent(pid)}/setujui`, payload, CFG);

    return r.data;
}

export async function tawarkanWaktu(pid, payload) {
    const r = await axios.post(`${API_KONFIRMASI}/permintaan/${encodeURIComponent(pid)}/tawarkan`, payload, CFG);

    return r.data;
}

export async function tolakPermintaan(pid, payload) {
    const r = await axios.post(`${API_KONFIRMASI}/permintaan/${encodeURIComponent(pid)}/tolak`, payload, CFG);

    return r.data;
}

export async function kirimPengingat(ids) {
    const r = await axios.post(`${API_KONFIRMASI}/pengingat`, { ids }, CFG);

    return r.data;
}

/** Tunda: { alasan (kode master TUNDA), perkiraan ('YYYY-MM-DD' | null), pesan }. */
export async function tundaJadwal(id, isian) {
    const r = await axios.post(`${API_KONFIRMASI}/${encodeURIComponent(id)}/tunda`, isian, CFG);

    return r.data;
}

/** Perbarui info penundaan; `kabari` = kirim email kabar terbaru ke kandidat. */
export async function perbaruiTunda(id, isian, kabari = true) {
    const r = await axios.post(`${API_KONFIRMASI}/${encodeURIComponent(id)}/tunda/perbarui`, { ...isian, kabari }, CFG);

    return r.data;
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

/** "Halaman benar-benar dibuka" — senyap; kegagalannya tidak mengganggu apa pun. */
export function tandaiDibuka(url) {
    if (!url) return;
    axios.post(url, {}, CFG).catch(() => {});
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

// ── WAKTU ───────────────────────────────────────────────────────────────────

const HARI = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
const BULAN = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
const dua = (n) => String(n).padStart(2, '0');

/** SQL Server mengirim spasi, bukan 'T' — Safari menolak bentuk itu. */
export function urai(nilai) {
    if (!nilai) return null;
    if (nilai instanceof Date) return Number.isNaN(nilai.getTime()) ? null : nilai;
    const d = new Date(String(nilai).replace(' ', 'T'));

    return Number.isNaN(d.getTime()) ? null : d;
}

/** Date → 'YYYY-MM-DD HH:mm:ss' (waktu LOKAL, tanpa singgah ke UTC). */
export function keServer(d) {
    const t = urai(d);
    if (!t) return null;

    return `${t.getFullYear()}-${dua(t.getMonth() + 1)}-${dua(t.getDate())} ${dua(t.getHours())}:${dua(t.getMinutes())}:00`;
}

/** 'Kam, 08 Okt · 10.00' */
export function waktuSingkat(nilai, kosong = '—') {
    const d = urai(nilai);
    if (!d) return kosong;

    return `${HARI[d.getDay()].slice(0, 3)}, ${dua(d.getDate())} ${BULAN[d.getMonth()]} · ${dua(d.getHours())}.${dua(d.getMinutes())}`;
}

/** 'YYYY-MM-DD' → 'Sab, 03 Okt 2026' (tanpa jam). */
export function tanggalSingkat(nilai, kosong = '—') {
    const d = urai(String(nilai || '').length === 10 ? `${nilai} 00:00:00` : nilai);
    if (!d) return kosong;

    return `${HARI[d.getDay()].slice(0, 3)}, ${dua(d.getDate())} ${BULAN[d.getMonth()]} ${d.getFullYear()}`;
}

/** '10.00' */
export function jam(nilai) {
    const d = urai(nilai);

    return d ? `${dua(d.getHours())}.${dua(d.getMinutes())}` : '';
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

/** '12 menit lalu', '3 jam lalu', '2 hari lalu'. */
export function sejak(nilai, sekarang = new Date()) {
    const d = urai(nilai);
    if (!d) return '';
    const menit = Math.max(0, Math.round((sekarang.getTime() - d.getTime()) / 60000));
    if (menit < 1) return 'baru saja';
    if (menit < 60) return `${menit} menit lalu`;
    if (menit < 1440) return `${Math.floor(menit / 60)} jam lalu`;

    return `${Math.floor(menit / 1440)} hari lalu`;
}

/** Ringkasan hasil aksi banyak kandidat: '3 berhasil, 1 dilewati'. */
export function ringkasBanyak(res) {
    const ok = (res?.result?.berhasil || []).length;
    const no = (res?.result?.gagal || []).length;

    return { ok, no, gagal: res?.result?.gagal || [], berhasil: res?.result?.berhasil || [] };
}
