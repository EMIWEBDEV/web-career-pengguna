/**
 * WEB CAREER — PEMULIHAN BERKAS: pembantu layar panel admin.
 *
 * Yang ada di sini hanya yang DIPAKAI BERSAMA oleh halaman, dialog unggah, dan
 * langkah tambah manual: pemanggilan API, bahasa tampilan untuk sebuah isian,
 * dan pemeriksaan berkas sebelum dikirim.
 *
 * Pemeriksaan di sini hanya mempercepat umpan balik. Aturan yang BERLAKU tetap
 * milik server (BerkasFormulir::periksaBerkas — format, ukuran, dan isi berkas);
 * yang lolos di sini masih bisa ditolak di sana, dan pesannya ditampilkan apa
 * adanya.
 */
import axios from 'axios';

export const API = '/api/v1/karir/pemulihan-berkas';

const CFG = { headers: { Accept: 'application/json' } };

/**
 * Alasan yang paling sering — satu ketukan, lalu boleh disunting. Alasan tetap
 * WAJIB ditulis: ia yang menjawab "berkas ini datang dari mana" ketika seseorang
 * membuka worklist berbulan-bulan kemudian.
 */
export const ALASAN_CEPAT = [
    'Dikirim ulang kandidat lewat email',
    'Dikirim kandidat lewat WhatsApp',
    'Diserahkan langsung, hasil pindai berkas fisik',
    'Gagal terunggah saat kandidat mengisi formulir',
];

export const ALASAN_MIN = 5;
export const ALASAN_MAKS = 500;

// ── API ──────────────────────────────────────────────────────────────────────

export async function muatDaftar(params) {
    const res = await axios.get(`${API}/daftar`, { ...CFG, params });

    return res.data.result;
}

export async function cariKandidat(params) {
    const res = await axios.get(`${API}/kandidat`, { ...CFG, params });

    return res.data.result || [];
}

export async function muatIsian(lamaranId) {
    const res = await axios.get(`${API}/kandidat/${encodeURIComponent(lamaranId)}/isian`, CFG);

    return res.data.result?.formulir || [];
}

/**
 * Kirim satu berkas pemulihan. `timpa` hanya dikirim setelah dialog peringatan
 * disetujui — server tetap menolaknya bila akun ini tak punya hak TIMPA.
 */
export async function kirimBerkas({ butir, file, alasan, timpa = false }, onProgress) {
    const fd = new FormData();
    fd.append('pengisian', butir.pengisianId);
    if (butir.bagian !== null && butir.bagian !== undefined) {
        fd.append('bagian', butir.bagian);
        fd.append('baris', String(butir.baris));
    }
    fd.append('field', butir.field);
    fd.append('alasan', alasan.trim());
    if (timpa) fd.append('timpa', '1');
    fd.append('berkas', file);

    const res = await axios.post(`${API}/unggah`, fd, {
        ...CFG,
        onUploadProgress: (e) => {
            if (onProgress && e.total) onProgress(Math.min(100, Math.round((e.loaded / e.total) * 100)));
        },
    });

    return res.data;
}

/**
 * Galat axios → { status, pesan, result }. Galat validasi Laravel (422 tanpa
 * `result`) diringkas ke pesan pertamanya, supaya dialog cukup punya satu
 * tempat untuk menampilkan penolakan.
 */
export function galatDari(e, cadangan = 'Terjadi kesalahan. Coba lagi.') {
    const status = e?.response?.status || 0;
    const data = e?.response?.data || {};
    const pertama = data.errors ? Object.values(data.errors).flat()[0] : null;

    let pesan = pertama || data.message || cadangan;
    if (!status) pesan = 'Tidak terhubung ke server. Periksa koneksi lalu coba lagi.';
    if (status === 413) pesan = data.message || 'Berkas terlalu besar untuk diterima server.';
    if (status === 419) pesan = 'Sesi kedaluwarsa. Muat ulang halaman lalu coba lagi.';

    return { status, pesan, result: data.result ?? null };
}

// ── BAHASA TAMPILAN ──────────────────────────────────────────────────────────

const STATUS = { BERJALAN: 'Berjalan', LULUS: 'Diterima', GUGUR: 'Gugur', MUNDUR: 'Mundur' };

/** Status lamaran dalam bahasa layar: 'LULUS' → 'Diterima'. */
export function labelStatus(s) {
    return STATUS[s] || (s ? s.charAt(0) + s.slice(1).toLowerCase() : '—');
}

/** 1234567 → '1,2 MB'. */
export function ukuranBerkas(byte) {
    const b = Number(byte) || 0;
    if (b < 1024) return `${b} B`;
    if (b < 1024 * 1024) return `${Math.round(b / 1024)} KB`;

    return `${(b / 1024 / 1024).toFixed(1).replace('.', ',')} MB`;
}

export function extDari(nama) {
    const m = /\.([a-z0-9]+)$/i.exec(String(nama || ''));

    return m ? m[1].toLowerCase() : '';
}

export function ikonBerkas(nama) {
    const ext = extDari(nama);
    if (ext === 'pdf') return 'bi-file-earmark-pdf-fill';
    if (['jpg', 'jpeg', 'png'].includes(ext)) return 'bi-file-earmark-image-fill';

    return 'bi-file-earmark-fill';
}

/** Posisi isian di formulirnya: 'Riwayat Sertifikasi · baris 2 (AWS Cloud)'. */
export function lokasiIsian(butir) {
    if (butir.bagian === null || butir.bagian === undefined) return '';
    const baris = `baris ${Number(butir.baris) + 1}`;

    return [butir.judulBagian || butir.bagian, butir.labelBaris ? `${baris} — ${butir.labelBaris}` : baris].join(' · ');
}

/** Formulir mana yang memuat isian ini, dalam bahasa admin. */
export function asalFormulir(butir) {
    if (butir.sumber === 'PENDAFTARAN') return `${butir.formulir} · pendaftaran`;
    if (butir.tahap) return `${butir.formulir} · tahap ${butir.tahap}`;

    return butir.formulir || 'Formulir';
}

export function teksAturan(aturan) {
    if (!aturan) return '';
    const fmt = (aturan.format || []).join(', ') || '—';

    return `${fmt} · maks. ${String(aturan.maksMb).replace('.', ',')} MB`;
}

/**
 * Periksa berkas sebelum dikirim — format dari ekstensi, lalu ukuran. Null
 * bila lolos. Isinya (PDF sungguhan atau bukan) hanya bisa dinilai server.
 */
export function periksaLokal(file, aturan) {
    if (!file) return 'Pilih berkas terlebih dahulu.';
    const format = (aturan?.format || []).map((f) => f.toLowerCase());
    let ext = extDari(file.name);
    if (ext === 'jpeg') ext = 'jpg';
    if (format.length && !format.includes(ext)) {
        return `Format .${ext || '?'} tidak diterima. Isian ini hanya menerima ${(aturan.format || []).join(', ')}.`;
    }
    const maks = Number(aturan?.maksMb) || 5;
    if (file.size > maks * 1024 * 1024) {
        return `Ukuran ${ukuranBerkas(file.size)} melebihi batas ${String(maks).replace('.', ',')} MB.`;
    }
    if (file.size === 0) return 'Berkas kosong (0 B). Pilih berkas lain.';

    return null;
}
