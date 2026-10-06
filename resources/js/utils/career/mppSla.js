/* WEB CAREER — HITUNGAN SLA & KURSI SEBUAH MPP, SATU SUMBER UNTUK SEMUA LAYAR.
 *
 * Kartu grid, baris tabel, dan panel split menampilkan angka yang sama tentang
 * MPP yang sama: sudah terisi berapa, tenggatnya aman atau lewat, sisa berapa
 * hari. Ketika masing-masing menghitungnya sendiri, cepat atau lambat ketiganya
 * berbeda — dan yang membacanya tidak punya cara tahu mana yang benar.
 *
 * ── SISA HARI DARI SERVER, LEWAT/TIDAK DARI LAYAR ──────────────────────────
 *
 * SISANYA dihitung server dalam HARI KERJA (SlaMpp::keadaan) — libur nasional
 * & cuti bersama HCIS ikut dikecualikan, dan itu mustahil dihitung di layar
 * tanpa menyalin kalendernya ke sini.
 *
 * Tapi status "sudah lewat atau belum" tetap dinilai ulang di layar terhadap
 * tanggalnya, sebab jawabannya berubah tiap tengah malam tanpa satu baris pun
 * data berubah — halaman yang dibuka semalam akan menyebut MPP-nya masih aman
 * sampai dimuat ulang.
 *
 * ── TIGA KUNCI SLA ────────────────────────────────────────────────────────
 *
 *   ontime  tenggatnya masih jauh.
 *   risk    tinggal ≤7 hari, dan kursinya BELUM penuh. Kursi yang sudah penuh
 *           tidak perlu diperingatkan — pekerjaannya memang sudah selesai.
 *   over    tenggatnya sudah terlampaui.
 */

/** Tengah malam hari ini, sebagai angka — dasar seluruh perbandingan tanggal. */
function hariIni() {
    return new Date().setHours(0, 0, 0, 0);
}

function keTanggal(v) {
    if (!v) return null;

    const d = new Date(`${v}T00:00:00`);

    return Number.isNaN(d.getTime()) ? null : d;
}

/**
 * Tenggat SLA yang BERLAKU — null berarti MPP ini memang tidak punya tenggat.
 *
 * TANPA jatuh-tempo ke tanggalPeriode. Pada program MT, tanggal itu adalah
 * tanggal MPP DIBUAT — yang menurut definisinya selalu sudah lewat — sehingga
 * memakainya sebagai tenggat membuat setiap MT tampak melanggar SLA yang tidak
 * pernah mengikatnya.
 */
export function batasSla(m) {
    if (!m || m.jenisProgram === 'MT' || !m.sla?.hari) return null;

    return m.sla.batas || null;
}

/**
 * Sisa waktu sampai tenggat — negatif berarti sudah lewat.
 *
 * ── HARI KERJA, BUKAN HARI KALENDER ───────────────────────────────────────
 *
 * Server (SlaMpp::keadaan) sudah menghitungnya dalam HARI KERJA: Senin–Sabtu,
 * dikurangi libur nasional & cuti bersama dari HCIS. Angka itu yang dipakai —
 * bukan selisih kalender yang dihitung di sini.
 *
 * Bedanya bukan kosmetik. Tenggat 30 hari kerja yang melewati Idul Fitri
 * memakan ±7 hari kalender lebih panjang; menghitungnya sebagai hari kalender
 * membuat layar menyebut "sisa 5 hari" pada MPP yang sebenarnya masih punya
 * 5 hari KERJA — dan rekruter mengira waktunya lebih sempit daripada nyatanya.
 *
 * Cadangan selisih kalender tetap ada untuk MPP yang server-nya belum sempat
 * mengirim `keadaan` (mis. respons lama yang masih tersimpan di layar).
 * Melesetnya ke arah "terlihat lebih mendesak", yang aman.
 */
export function sisaHariSla(m) {
    if (!batasSla(m)) return null;

    // Angka resmi dari server — hari kerja, sudah memperhitungkan libur.
    if (typeof m?.sla?.keadaan?.sisa === 'number') {
        return m.sla.keadaan.sisa;
    }

    const b = keTanggal(batasSla(m));
    if (!b) return null;

    return Math.round((b.getTime() - hariIni()) / 86400000);
}

/** Kuota & terisi, dengan cadangan ke jumlah rencana bila ledger belum terbaca. */
export function kursiMpp(m) {
    const kuota = Number(m?.kursi?.kuota ?? m?.jumlahRekrutmen ?? 0);
    const terisi = Number(m?.kursi?.terisi ?? 0);

    return { kuota, terisi };
}

export function penuhMpp(m) {
    const { kuota, terisi } = kursiMpp(m);

    return kuota > 0 && terisi >= kuota;
}

/** Persentase keterisian, dipatok 0–100. */
export function persenKursi(m) {
    const { kuota, terisi } = kursiMpp(m);
    if (kuota <= 0) return 0;

    return Math.min(100, Math.max(0, Math.round((terisi / kuota) * 100)));
}

/** 'ontime' | 'risk' | 'over' | 'none' — lihat catatan kepala berkas. */
export function kunciSla(m) {
    const sisa = sisaHariSla(m);
    if (sisa === null) return 'none';
    if (sisa < 0) return 'over';
    if (sisa <= 7 && !penuhMpp(m)) return 'risk';

    return 'ontime';
}

/** Kalimat panjang — dipakai title/tooltip dan pil di panel detail. */
export function labelSla(m) {
    const sisa = sisaHariSla(m);
    if (sisa === null) {
        return m?.jenisProgram === 'MT' ? 'Tidak terikat SLA' : 'Tanpa ketentuan SLA';
    }

    if (sisa < 0) return `Lewat SLA ${Math.abs(sisa)} hari`;
    if (sisa === 0) return 'Jatuh tempo hari ini';
    if (sisa <= 7 && !penuhMpp(m)) return `Mendekati batas · sisa ${sisa} hari`;

    return `Dalam SLA · sisa ${sisa} hari`;
}

/** Bentuk pendek untuk lencana sempit di kartu/daftar: "+6h", "H-3", "24h". */
export function pendekSla(m) {
    const sisa = sisaHariSla(m);
    if (sisa === null) return '—';
    if (sisa < 0) return `+${Math.abs(sisa)}h`;
    if (sisa <= 7 && !penuhMpp(m)) return `H-${sisa}`;

    return `${sisa}h`;
}
