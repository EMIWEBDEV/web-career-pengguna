/**
 * WEB CAREER — kamera verifikasi wajah: pemanasan, bingkai gelap, dan kamera
 * yang tidak ada.
 *
 * Foto verifikasi yang HITAM hampir selalu lahir dari satu hal: gambar diambil
 * saat sensor belum siap. Aliran kamera sudah "menyala" begitu izin diberikan,
 * tetapi beberapa detik pertama bingkainya gelap atau hitam selagi pengaturan
 * cahaya otomatis bekerja — dan tombol "Ambil Foto" yang langsung ditekan
 * menangkap persis bingkai itu. Karena itu ada DUA pagar:
 *
 *   1. JEDA pemanasan sebelum tombolnya bisa ditekan;
 *   2. PENILAIAN bingkai saat ditekan — bingkai yang masih gelap/polos ditolak
 *      dengan kalimat yang menyebut apa yang harus dibenahi.
 *
 * Verifikasi wajah WAJIB. Tidak ada jalan pintas unggah berkas: tanpa kamera,
 * formulir memang tidak bisa dilanjutkan — dan kalimat galatnya mengatakan itu.
 */

/** Jeda pemanasan kamera sebelum foto boleh diambil (detik) — keputusan user 28 Sep 2026. */
export const JEDA_KAMERA_DETIK = 6;

/** Rata-rata kecerahan (0–255) di bawah ini = bingkai hitam / terlalu gelap. */
const BATAS_GELAP = 28;

/** Sebaran kecerahan di bawah ini = bingkai polos satu warna (lensa tertutup, kamera virtual kosong). */
const BATAS_RATA = 7;

/**
 * Ada kamera di perangkat ini?
 *
 * true/false, atau null bila peramban tidak bisa menjawab — sebagian peramban
 * baru memberi daftar perangkat setelah izin diberikan, jadi null berarti
 * "coba saja minta izinnya".
 */
export async function adaKamera() {
    if (!navigator.mediaDevices?.enumerateDevices) return null;
    try {
        const daftar = await navigator.mediaDevices.enumerateDevices();
        if (!daftar.length) return null;

        return daftar.some((d) => d.kind === 'videoinput');
    } catch (e) {
        return null;
    }
}

/**
 * Kecerahan satu bingkai video — rata-rata & sebarannya — dari salinan kecil
 * 64×48. Cukup untuk menilai gelap-terang, dan murah dihitung berulang.
 */
export function nilaiBingkai(video) {
    if (!video || !video.videoWidth || video.readyState < 2) return { siap: false, rata: 0, sebaran: 0 };

    const c = document.createElement('canvas');
    c.width = 64;
    c.height = 48;
    const ctx = c.getContext('2d', { willReadFrequently: true });
    ctx.drawImage(video, 0, 0, c.width, c.height);
    const { data } = ctx.getImageData(0, 0, c.width, c.height);

    let jumlah = 0;
    let kuadrat = 0;
    const n = data.length / 4;
    for (let i = 0; i < data.length; i += 4) {
        const y = 0.299 * data[i] + 0.587 * data[i + 1] + 0.114 * data[i + 2];
        jumlah += y;
        kuadrat += y * y;
    }
    const rata = jumlah / n;

    return { siap: true, rata, sebaran: Math.sqrt(Math.max(0, kuadrat / n - rata * rata)) };
}

/** Bingkai ini layak jadi foto verifikasi? null bila layak, selain itu kalimat untuk kandidat. */
export function galatBingkai(video) {
    const b = nilaiBingkai(video);
    if (!b.siap) return 'Kamera belum siap. Tunggu sebentar, lalu ambil lagi.';
    if (b.rata < BATAS_GELAP) {
        return 'Gambar masih gelap atau hitam. Pastikan wajahmu terkena cahaya dan lensa tidak tertutup, lalu ambil lagi.';
    }
    if (b.sebaran < BATAS_RATA) {
        return 'Kamera belum menangkap gambar (layarnya polos). Pastikan lensa tidak tertutup, lalu ambil lagi.';
    }

    return null;
}

/** Galat getUserMedia → kalimat untuk kandidat. */
export function pesanGalatKamera(e) {
    switch (e?.name) {
        case 'NotAllowedError':
        case 'SecurityError':
            return 'Izin kamera ditolak. Aktifkan izin kamera untuk situs ini di pengaturan peramban, lalu coba lagi.';
        case 'NotFoundError':
        case 'OverconstrainedError':
        case 'DevicesNotFoundError':
            return PESAN_TANPA_KAMERA;
        case 'NotReadableError':
        case 'TrackStartError':
            return 'Kamera sedang dipakai aplikasi lain. Tutup aplikasi tersebut (mis. Zoom atau WhatsApp), lalu coba lagi.';
        default:
            return 'Kamera tidak dapat diakses. Pastikan halaman dibuka lewat HTTPS dan izin kamera aktif, lalu coba lagi.';
    }
}

/** Kalimat bila perangkat tidak punya kamera — verifikasi wajah wajib, jadi ia juga menyebut jalan keluarnya. */
export const PESAN_TANPA_KAMERA =
    'Kamera tidak terdeteksi. Verifikasi wajah wajib, jadi formulir tidak bisa dilanjutkan tanpa kamera — gunakan perangkat yang memiliki kamera (mis. ponsel), lalu coba lagi.';
