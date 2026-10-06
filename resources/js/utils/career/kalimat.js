/**
 * WEB CAREER — "KALIMAT SUNGGUHAN" untuk isian penjelasan bebas kandidat.
 *
 * CERMIN app/Support/Career/Kalimat.php (keputusan user 2 Okt 2026): layar
 * memberi tahu lebih awal, server yang memutuskan. Ubah keduanya bersamaan —
 * urutan langkah, angka, daftar kata, dan bunyi galatnya harus sama persis.
 *
 *   1–4  tanda dasar (panjang, huruf diulang, jumlah & ragam kata, huruf vs
 *        simbol) — periksaDasar(), seketika saat mengetik;
 *   5–6  ketikan acak (kecuali kata yang dikenal kamus) & kata umum;
 *   7    KAMUS (nspell — ejaan.js): ≥ 60% kata isi dikenal, minimal dua.
 * periksaKalimatLengkap() menjalankan semuanya; tanpa pemeriksa ejaan
 * (peramban lama) langkah 7 dilewati dan server tetap memeriksanya.
 */
import { kenaliKata } from './ejaan';

const KATA_UMUM = new Set([
    // kata ganti & sapaan
    'saya', 'aku', 'kami', 'kita', 'kamu', 'anda', 'dia', 'beliau', 'mereka', 'sy', 'gw', 'gue',
    // kata sambung & depan
    'dan', 'atau', 'yang', 'di', 'ke', 'dari', 'untuk', 'dengan', 'pada', 'dalam', 'oleh', 'karena', 'sebab',
    'karna', 'krn', 'jadi', 'sehingga', 'tapi', 'tetapi', 'namun', 'agar', 'supaya', 'kalau', 'jika', 'bila',
    'apabila', 'saat', 'ketika', 'sejak', 'setelah', 'sebelum', 'selama', 'sampai', 'hingga', 'bahwa', 'serta',
    'maka', 'lalu', 'kemudian', 'sedangkan', 'walaupun', 'meskipun', 'akibat', 'demi', 'tentang', 'terhadap',
    'antara', 'bagi', 'per', 'sama', 'seperti',
    // keterangan & kata kerja bantu
    'tidak', 'tak', 'belum', 'bukan', 'sudah', 'telah', 'akan', 'sedang', 'masih', 'bisa', 'dapat', 'harus',
    'wajib', 'perlu', 'ingin', 'mau', 'ada', 'ini', 'itu', 'juga', 'lagi', 'pun', 'saja', 'hanya', 'sangat',
    'lebih', 'kurang', 'sekali', 'mungkin', 'memang', 'baru', 'lama', 'segera', 'mendadak', 'tiba', 'nanti',
    'sekarang', 'besok', 'hari', 'minggu', 'bulan', 'tahun', 'waktu', 'jadwal', 'mohon', 'maaf', 'terima',
    'kasih', 'tolong', 'banyak', 'semua', 'beberapa', 'lain', 'lainnya', 'sendiri', 'baik', 'buruk', 'jauh',
    'dekat', 'tetap', 'pernah', 'selalu', 'sering', 'kembali', 'mulai', 'ikut', 'mengikuti', 'melanjutkan',
    'lanjut', 'berhenti', 'mundur', 'batal', 'membatalkan', 'memutuskan', 'keputusan', 'memilih', 'pilihan',
    // topik yang lazim pada alasan mundur
    'kerja', 'bekerja', 'pekerjaan', 'kantor', 'perusahaan', 'posisi', 'jabatan', 'tawaran', 'diterima',
    'menerima', 'gaji', 'penempatan', 'lokasi', 'tempat', 'kota', 'luar', 'daerah', 'rumah', 'pindah',
    'keluarga', 'orang', 'tua', 'ayah', 'ibu', 'anak', 'istri', 'suami', 'menikah', 'nikah', 'sakit',
    'kesehatan', 'rawat', 'kuliah', 'kampus', 'studi', 'sekolah', 'pendidikan', 'beasiswa', 'urusan',
    'pribadi', 'kondisi', 'keadaan', 'alasan', 'proses', 'seleksi', 'lamaran', 'rekrutmen', 'tim', 'bidang',
    'minat', 'sesuai', 'cocok', 'kontrak', 'izin', 'usaha', 'bisnis', 'jarak', 'transportasi', 'biaya',
    // Inggris dasar
    'i', 'my', 'me', 'am', 'is', 'are', 'was', 'have', 'has', 'got', 'because', 'the', 'and', 'to', 'for',
    'not', 'no', 'job', 'offer', 'family', 'work', 'moving', 'health', 'other', 'another', 'company', 'position',
    'personal', 'reason', 'study', 'with', 'from', 'can', 'cannot', 'will',
]);

const BARIS_KIBOR = ['qwertyuiop', 'asdfghjkl', 'zxcvbnm'];

export const GALAT_ACAK = 'Teksnya belum terbaca sebagai kalimat. Ceritakan dengan kata-kata biasa, misalnya: '
    + '"Saya diterima bekerja di perusahaan lain sehingga tidak bisa melanjutkan."';

/** Satu kata terlihat seperti ketikan acak. */
function acak(kata) {
    if (kata.length >= 4 && !/[aiueo]/u.test(kata)) return true;
    if (/[bcdfghjklmnpqrstvwxyz]{5,}/u.test(kata)) return true;

    return BARIS_KIBOR.some((baris) => [baris, [...baris].reverse().join('')].some((b) => {
        for (let i = 0; i + 4 <= b.length; i++) {
            if (kata.includes(b.slice(i, i + 4))) return true;
        }

        return false;
    }));
}

function rapikan(teks) {
    return String(teks || '').replace(/<[^>]*>/g, '').replace(/\s+/gu, ' ').trim();
}

/** Kata-kata kalimat: deret huruf ≥ 2, huruf kecil, urutan kemunculan. */
export function kataKalimat(teks) {
    return (rapikan(teks).toLowerCase().match(/\p{L}+/gu) || []).filter((k) => [...k].length >= 2);
}

/** Kalimat galat kamus — menyebut sampai tiga kata yang tidak dikenali. */
export function galatAsing(asing) {
    if (!asing.length) return 'Tulis kalimat yang lebih jelas — minimal dua kata bermakna tentang apa yang terjadi.';

    return `Ada kata yang tidak dikenali: ${asing.slice(0, 3).map((a) => `"${a}"`).join(', ')}. Periksa ejaannya, atau tulis dengan kata-kata biasa.`;
}

/** Langkah 1–4 — seketika, tanpa kamus. '' = lolos. */
export function periksaDasar(teks, min = 15) {
    const t = rapikan(teks);

    if ([...t].length < min) return `Tulis minimal ${min} karakter — ceritakan apa yang terjadi dan kenapa.`;
    if (/(.)\1{3,}/u.test(t)) return 'Hindari huruf atau tanda yang diulang-ulang — tulis kalimat yang sebenarnya.';

    const kata = kataKalimat(t);
    if (kata.length < 3) return 'Tulis dalam bentuk kalimat, minimal 3 kata — bukan satu-dua kata saja.';
    const unik = new Set(kata);
    // Satu kata mendominasi ("kerja kerja kerja kerja di rumah") juga
    // diulang-ulang; dua kali (reduplikasi "akhir akhir") tetap wajar.
    const frek = new Map();
    for (const k of kata) frek.set(k, (frek.get(k) || 0) + 1);
    const terbanyak = Math.max(...frek.values());
    if (unik.size < 3 || unik.size < kata.length / 2 || (terbanyak >= 3 && terbanyak > kata.length * 0.3)) {
        return 'Hindari kata yang diulang-ulang — tulis kalimat yang sebenarnya.';
    }

    const huruf = (t.match(/\p{L}/gu) || []).length;
    const isi = [...t.replace(/\s/gu, '')].length;
    if (huruf < isi * 0.7) return 'Tulis dengan kata-kata, bukan deretan angka atau simbol.';

    return '';
}

/**
 * Langkah 1–7. `kenal(kata)` = penilai kamus, atau null = tanpa kamus
 * (langkah 7 dilewati). '' = lolos. Sama dengan Kalimat::nilai().
 */
export function nilaiKalimat(teks, min, kenal) {
    const dasar = periksaDasar(teks, min);
    if (dasar) return dasar;

    const kata = kataKalimat(teks);
    if (kata.filter((k) => acak(k) && !(kenal && kenal(k))).length / kata.length > 0.25) return GALAT_ACAK;
    if (![...new Set(kata)].some((k) => KATA_UMUM.has(k))) return GALAT_ACAK;

    if (kenal) {
        const isi = kata.filter((k) => [...k].length >= 3);
        let dikenal = 0;
        const asing = [];
        for (const k of isi) {
            if (kenal(k)) dikenal++;
            else if (!asing.includes(k)) asing.push(k);
        }
        // ≥ 60% dikenal — bilangan bulat, sama persis dengan Kalimat.php.
        if (dikenal < 2 || dikenal * 5 < isi.length * 3) return galatAsing(asing);
    }

    return '';
}

/**
 * Pemeriksaan LENGKAP dengan kamus nspell. Kamus Indonesia lebih dulu; bila
 * belum lolos, kamus Inggris ikut dimuat dan dinilai ulang — hasil akhirnya
 * sama dengan server, yang selalu memakai keduanya.
 *
 * @returns {Promise<{ galat: string, kamus: boolean }>} kamus=false → pemeriksa ejaan tidak tersedia
 */
export async function periksaKalimatLengkap(teks, min = 15) {
    const dasar = periksaDasar(teks, min);
    if (dasar) return { galat: dasar, kamus: false };

    const unik = [...new Set(kataKalimat(teks))];
    const nilaiDengan = (dikenal) => {
        const peta = new Map(unik.map((k, i) => [k, dikenal[i] === true]));

        return nilaiKalimat(teks, min, (k) => peta.get(k) === true);
    };

    const id = await kenaliKata(unik, false);
    if (!id) return { galat: nilaiKalimat(teks, min, null), kamus: false };
    const galat = nilaiDengan(id);
    if (!galat) return { galat: '', kamus: true };

    const semua = await kenaliKata(unik, true);

    return semua ? { galat: nilaiDengan(semua), kamus: true } : { galat, kamus: true };
}
