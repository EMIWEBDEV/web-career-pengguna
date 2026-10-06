/**
 * DASHBOARD — pembantu bersama & PALET DATA.
 *
 * PALET INI DIVALIDASI, BUKAN DIKIRA-KIRA.
 * Seluruh hex di bawah lolos enam pemeriksaan validator data-viz (rentang
 * lightness, lantai chroma, pemisahan CVD protan/deutan/tritan, lantai
 * penglihatan normal, kontras vs surface) pada surface kartu #ffffff:
 *
 *   seri tren  "#6366f1,#d97706"        → semua PASS (CVD ΔE 32.2, normal 35.1,
 *                                          kontras keduanya ≥ 3:1)
 *   ramp matriks (ordinal, 4 langkah)   → semua PASS (monoton, jarak ΔL ≥ 0.06,
 *                                          ujung terang 2.24:1 vs surface)
 *
 * Kalau warnanya diubah, JALANKAN ULANG validator-nya. Menukar #d97706 dengan
 * #f59e0b yang lebih cerah, misalnya, langsung menurunkan kontras ke 2.15:1 dan
 * mewajibkan label/tabel sebagai penggantinya.
 */

export const CFG = { headers: { Accept: 'application/json' } };

/** Dua seri grafik tren. Warna mengikuti ENTITAS, tidak pernah peringkat. */
export const SERI = { masuk: '#6366f1', diterima: '#d97706' };

/** Ramp satu-hue untuk matriks MT (magnitudo). Terang → gelap, 4 langkah. */
export const RAMP = ['#9aa8fb', '#7c8bf7', '#5b5fe8', '#4338ca'];

/**
 * Palet STATUS — terpisah dari warna seri dan tidak pernah dipakai sebagai
 * "seri ke-3". Selalu berpasangan dengan ikon + teks, jadi maknanya tidak
 * pernah dipikul warna sendirian (warning & serious memang di bawah 3:1 pada
 * latar terang — ikon dan labelnya yang menanggung).
 */
export const STATUS = {
    good: { warna: '#0ca30c', ikon: 'bi-check-circle-fill' },
    warning: { warna: '#fab219', ikon: 'bi-exclamation-triangle-fill' },
    serious: { warna: '#ec835a', ikon: 'bi-exclamation-circle-fill' },
    critical: { warna: '#d03b3b', ikon: 'bi-x-octagon-fill' },
};

/** Tinta & chrome grafik — resesif, garis tipis, tidak pernah putus-putus. */
export const INK = {
    utama: '#0f172a',
    kedua: '#475569',
    redup: '#94a3b8',
    grid: '#eef2f7',
    sumbu: '#cbd5e1',
};

/* ─────────────────────────── FORMAT ─────────────────────────── */

export function angka(n) {
    if (n === null || n === undefined || n === '') return '—';
    return Number(n).toLocaleString('id-ID');
}

export function desimal(n, digit = 1) {
    if (n === null || n === undefined || n === '') return '—';
    return Number(n).toLocaleString('id-ID', { minimumFractionDigits: digit, maximumFractionDigits: digit });
}

function keDate(v) {
    if (!v) return null;
    const d = new Date(String(v).replace(' ', 'T'));
    return Number.isNaN(d.getTime()) ? null : d;
}

export function tanggal(v) {
    const d = keDate(v);
    return d ? d.toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' }) : '—';
}

export function tanggalPendek(v) {
    const d = keDate(v);
    return d ? d.toLocaleDateString('id-ID', { day: 'numeric', month: 'short' }) : '—';
}

export function tglJam(v) {
    const d = keDate(v);
    if (!d) return '—';
    return `${d.toLocaleDateString('id-ID', { day: 'numeric', month: 'short' })} · ${d.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' })}`;
}

export function inisial(n) {
    return (n || '?').trim().split(/\s+/).slice(0, 2).map((s) => s[0]).join('').toUpperCase();
}

export function persen(bagian, total) {
    if (!total) return null;
    return Math.round((bagian / total) * 100);
}

/* ─────────────────────────── NADA ─────────────────────────── */

/**
 * Label kesehatan dari MetrikRekrutmen::skorSehat → status + kata-kata.
 * KOSONG dan SELESAI sengaja dibedakan: yang satu belum pernah ada pelamar,
 * yang satu sudah tuntas semua. Menyamakannya membuat program yang sukses
 * terlihat seperti program yang tidak pernah jalan.
 */
export function nadaSehat(label) {
    const peta = {
        SEHAT: { ...STATUS.good, teks: 'Sehat' },
        PERLU_AKSI: { ...STATUS.warning, teks: 'Perlu aksi' },
        KRITIS: { ...STATUS.critical, teks: 'Kritis' },
        SELESAI: { warna: INK.kedua, ikon: 'bi-flag-fill', teks: 'Selesai' },
        KOSONG: { warna: INK.redup, ikon: 'bi-dash-circle', teks: 'Belum ada pelamar' },
    };
    return peta[label] || peta.KOSONG;
}

/** Nada umur menggantung/macet terhadap ambang dari server. */
export function nadaUmur(hari, ambang) {
    if (hari >= ambang * 3) return STATUS.critical;
    if (hari >= ambang) return STATUS.serious;
    return STATUS.warning;
}

/**
 * Langkah ramp untuk satu sel matriks. Mengembalikan null saat nilainya 0 —
 * sel kosong memakai latar surface, BUKAN langkah teringan ramp, supaya
 * "tidak ada orang di sini" tidak terbaca sebagai "ada sedikit".
 */
export function selRamp(nilai, maks) {
    if (!nilai || !maks) return null;
    const idx = Math.min(RAMP.length - 1, Math.floor(((nilai / maks) * RAMP.length) - 0.0001));
    return RAMP[Math.max(0, idx)];
}

/** Teks putih di atas dua langkah tergelap, tinta gelap di dua teringan. */
export function selTinta(warna) {
    const i = RAMP.indexOf(warna);
    return i >= 2 ? '#ffffff' : INK.utama;
}
