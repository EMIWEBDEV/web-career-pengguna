/**
 * WEB CAREER — penyimpan pengaturan EXPORT STUDIO di localStorage.
 *
 * Menyimpan susunan, centang, judul yang ditulis ulang, dan bentuk tampilan
 * tiap bagian — supaya admin yang mengatur berkas satu kandidat tidak perlu
 * mengulang seluruh penyesuaiannya pada kandidat berikutnya, dan tidak
 * kehilangan apa pun sesudah menekan "Cetak & Unduh".
 *
 * ── DISIMPAN PER LAMARAN, BUKAN SATU UNTUK SEMUA ────────────────────────────
 *
 * Tiap kandidat punya formulir dan tahap yang berbeda, jadi kunci seksinya pun
 * berbeda. Satu simpanan bersama akan memulihkan centang milik kandidat lain —
 * yang paling sering berarti bagian yang tidak ada di sana ikut "dicentang"
 * dan bagian yang ada justru tidak.
 *
 * ── YANG DISIMPAN HANYA PILIHAN, BUKAN DATANYA ──────────────────────────────
 *
 * Isinya kunci seksi, urutan, dan penyesuaian tampilan. Tidak ada nama, nilai,
 * maupun jawaban kandidat — localStorage tidak pernah dibersihkan sendiri dan
 * bisa dibaca skrip mana pun di origin ini, jadi data pribadi tidak boleh
 * singgah di sana.
 */

const AWALAN = 'wc.export-studio.';

/** Simpanan lebih tua dari ini dibuang saat dibaca. */
const UMUR_HARI = 30;

/** Batas jumlah lamaran yang disimpan — lihat pangkas(). */
const MAKS_LAMARAN = 20;

function kunciFor(lamaranId) {
    return `${AWALAN}${lamaranId}`;
}

/**
 * localStorage bisa melempar: mode penyamaran menolak menulis, dan kuota bisa
 * penuh. Pengaturan yang gagal disimpan bukan alasan untuk menggagalkan cetak,
 * jadi setiap sentuhan dibungkus dan kegagalannya diabaikan.
 */
function aman(fn, cadangan = null) {
    try {
        return fn();
    } catch {
        return cadangan;
    }
}

/**
 * Buang simpanan terlama bila jumlahnya melewati batas.
 *
 * Tanpa ini, admin yang membuka ratusan kandidat meninggalkan ratusan entri
 * yang tak pernah dipakai lagi — dan localStorage punya kuota yang, kalau
 * penuh, membuat SELURUH penyimpanan di origin ini gagal menulis.
 */
function pangkas() {
    aman(() => {
        const entri = [];

        for (let i = 0; i < localStorage.length; i++) {
            const k = localStorage.key(i);

            if (!k || !k.startsWith(AWALAN)) continue;

            const isi = aman(() => JSON.parse(localStorage.getItem(k)), null);

            entri.push({ k, pada: isi?.pada || 0 });
        }

        if (entri.length <= MAKS_LAMARAN) return;

        entri
            .sort((a, b) => a.pada - b.pada)
            .slice(0, entri.length - MAKS_LAMARAN)
            .forEach((e) => localStorage.removeItem(e.k));
    });
}

/**
 * Baca pengaturan tersimpan untuk satu lamaran.
 *
 * @returns {{pilih:object, gaya:object, judul:object, jarak:object, urutan:object, bab:string[]}|null}
 */
export function bacaSetelan(lamaranId) {
    if (!lamaranId) return null;

    return aman(() => {
        const mentah = localStorage.getItem(kunciFor(lamaranId));

        if (!mentah) return null;

        const isi = JSON.parse(mentah);

        // Simpanan usang dibuang: alur seleksi & formulir kandidat bisa berubah
        // dalam sebulan, dan memulihkan centang lama pada struktur baru
        // menghasilkan pilihan yang setengah salah tanpa terlihat salah.
        const umur = (Date.now() - (isi?.pada || 0)) / 86400000;

        if (umur > UMUR_HARI) {
            localStorage.removeItem(kunciFor(lamaranId));

            return null;
        }

        return isi?.data || null;
    });
}

/** Simpan pengaturan satu lamaran. */
export function simpanSetelan(lamaranId, data) {
    if (!lamaranId || !data) return;

    aman(() => {
        localStorage.setItem(
            kunciFor(lamaranId),
            JSON.stringify({ pada: Date.now(), data }),
        );
    });

    pangkas();
}

/** Hapus pengaturan satu lamaran — dipakai tombol "Setel ulang". */
export function hapusSetelan(lamaranId) {
    if (!lamaranId) return;

    aman(() => localStorage.removeItem(kunciFor(lamaranId)));
}
