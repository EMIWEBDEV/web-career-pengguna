/**
 * WEB CAREER — Draf lokal pengisian formulir lamaran.
 *
 * KENAPA ADA
 * Formulir lamaran belum punya baris apa pun di database selagi diisi: lamaran
 * baru lahir setelah kandidat menekan Kirim. Jadi tidak ada tempat di server
 * untuk menitipkan isian setengah jadi, tidak seperti formulir tahap lanjut
 * yang sudah punya `Formulir_Pengisian` dan menyimpan drafnya ke sana.
 * Sebelum ini, satu kali refresh — atau tab yang ditutup untuk mencari nomor
 * KTP — berarti mengisi ulang tujuh langkah dari nol.
 *
 * KENAPA localStorage, BUKAN sessionStorage
 * Kandidat kerap meninggalkan formulir untuk memindai ijazah atau mencari
 * transkrip, kadang sampai esok hari. sessionStorage mati bersama tabnya, jadi
 * justru pada kasus yang paling perlu ditolong ia tidak menolong apa-apa.
 *
 * BUKAN SUMBER KEBENARAN
 * Draf ini kenyamanan. Kegagalan menulis (kuota penuh, mode privat) tidak
 * pernah boleh menghentikan pengisian, dan yang tersimpan di sini tidak pernah
 * dipakai sebagai bukti apa pun — yang sah tetap yang masuk ke database saat
 * finalisasi.
 */

const AWALAN = 'evo_career_draf_formulir:';

/** Versi bentuk simpanan. Naikkan bila susunan objeknya berubah. */
const VERSI = 1;

/**
 * Umur maksimal draf.
 *
 * Lowongan bisa keburu tutup, dan isian formulir lamaran memuat data pribadi
 * (NIK, tanggal lahir, nomor telepon). Membiarkannya mengendap tanpa batas di
 * peramban yang mungkin dipakai bergantian — warnet, komputer keluarga —
 * bukan kenyamanan, melainkan kelalaian.
 */
const UMUR_MAKS_MS = 7 * 24 * 60 * 60 * 1000;

/**
 * Tipe field yang jawabannya TIDAK ikut disimpan.
 *
 *   file, foto  Yang tersimpan di jawaban hanyalah NAMA berkasnya; isinya hidup
 *               sebagai objek File di memori halaman dan tidak bisa masuk
 *               localStorage. Kalau namanya ikut dipulihkan, kandidat melihat
 *               kartu hijau "berkas siap dikirim" untuk berkas yang isinya
 *               sudah tidak ada di mana pun: validasi lolos, lalu yang sampai
 *               ke server kosong. Lebih baik dikosongkan dan diminta ulang.
 *
 *   prefill     Terkunci dan dimiliki sistem (NIK, nama akun). Sumbernya akun
 *               yang sedang login, bukan draf kemarin — kalau datanya sempat
 *               diperbarui, draf lama justru mengembalikan yang basi ke kolom
 *               yang tidak bisa dikoreksi kandidat.
 */
const TIPE_TAK_DISIMPAN = new Set(['file', 'foto', 'prefill']);

/**
 * FNV-1a → base36.
 *
 * Bukan untuk keamanan, hanya supaya kunci tetap pendek dan alamat surel
 * kandidat tidak terpampang di daftar localStorage yang bisa dibaca sekilas
 * oleh siapa pun yang membuka DevTools di komputer bersama.
 */
function ringkas(teks) {
    let h = 2166136261;
    for (let i = 0; i < teks.length; i += 1) {
        h ^= teks.charCodeAt(i);
        h = Math.imul(h, 16777619);
    }

    return (h >>> 0).toString(36);
}

/**
 * Kunci simpanan satu draf.
 *
 * Diikat ke AKUN dan LOWONGAN sekaligus. Tanpa identitas akun, dua orang yang
 * bergantian memakai komputer yang sama akan saling mewarisi isian — termasuk
 * NIK dan tanggal lahir orang lain, yang lalu ikut terkirim tanpa disadari.
 *
 * @returns {?string} null bila targetnya tidak jelas — lebih baik tidak
 *                    menyimpan daripada menyimpan ke kunci yang salah.
 */
export function kunciDraf({ identitas, lowonganId } = {}) {
    if (! lowonganId) {
        return null;
    }

    return `${AWALAN}${ringkas(String(identitas || 'tamu'))}.${lowonganId}`;
}

/**
 * Sidik jari susunan skema + daftar key yang tidak boleh ikut tersimpan.
 *
 * Master Formulir bisa diterbitkan ulang kapan saja. Draf yang lahir di versi
 * lama tidak boleh dituang begitu saja ke versi baru: sebuah key bisa bertahan
 * sementara pertanyaannya berganti makna, dan kandidat akan mengirim jawaban
 * atas pertanyaan yang tidak pernah ia baca. Begitu sidiknya berbeda, drafnya
 * dibuang.
 *
 * `punyaBerkas` dilaporkan terpisah dari `takDisimpan`: keduanya sama-sama
 * tidak ikut disimpan, tapi hanya berkas & foto yang perlu DILAMPIRKAN ULANG
 * oleh kandidat. Field terkunci terisi sendiri dari akun, jadi menyebutnya
 * dalam peringatan cuma membuat orang mencari yang tidak hilang.
 *
 * @returns {{tanda: string, punyaBerkas: boolean, takDisimpan: Set<string>, takDisimpanBaris: Map<string, Set<string>>}}
 */
export function petaSkema(skema) {
    const sidik = [String(skema?.layout || '')];
    const takDisimpan = new Set();
    const takDisimpanBaris = new Map();
    let punyaBerkas = false;

    (skema?.langkah || []).forEach((L, iL) => {
        sidik.push(`L:${L.kode || L.judul || iL}`);

        (L.bagian || []).forEach((B) => {
            const kunciB = B.berulang ? kunciBagianRingan(B) : null;
            const perBaris = new Set();

            (B.field || []).forEach((f) => {
                const tipe = String(f?.tipe || '').toLowerCase();
                sidik.push(`${f?.key || ''}:${tipe}`);

                if (! TIPE_TAK_DISIMPAN.has(tipe) || ! f?.key) {
                    return;
                }
                if (tipe === 'file' || tipe === 'foto') {
                    punyaBerkas = true;
                }

                // Field di dalam bagian berulang tidak tinggal di akar jawaban;
                // ia satu kolom di tiap baris array bagian itu.
                if (kunciB) {
                    perBaris.add(f.key);
                } else {
                    takDisimpan.add(f.key);
                }
            });

            if (kunciB && perBaris.size) {
                takDisimpanBaris.set(kunciB, perBaris);
            }
        });
    });

    return { tanda: ringkas(sidik.join('|')), punyaBerkas, takDisimpan, takDisimpanBaris };
}

/**
 * Salinan `kunciBagian()` dari aturan.js.
 *
 * Sengaja tidak diimpor supaya modul ini tetap bisa dipakai (dan diuji) tanpa
 * menyeret seluruh mesin aturan — satu-satunya yang dibutuhkan di sini adalah
 * nama penampung bagian berulang.
 */
function kunciBagianRingan(B) {
    return (
        B.key ||
        String(B.judul || 'bagian')
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, '_')
            .replace(/^_+|_+$/g, '')
    );
}

/** Buang jawaban field yang tidak boleh ikut tersimpan, termasuk di baris berulang. */
function saring(jawaban, peta) {
    const keluar = {};

    Object.entries(jawaban || {}).forEach(([key, nilai]) => {
        if (peta.takDisimpan.has(key)) {
            return;
        }

        const perBaris = peta.takDisimpanBaris.get(key);
        if (perBaris && Array.isArray(nilai)) {
            keluar[key] = nilai.map((baris) => {
                if (! baris || typeof baris !== 'object') {
                    return baris;
                }
                const salin = { ...baris };
                perBaris.forEach((k) => delete salin[k]);

                return salin;
            });

            return;
        }

        keluar[key] = nilai;
    });

    return keluar;
}

/**
 * Baca draf yang masih layak dipakai.
 *
 * Draf kedaluwarsa atau milik susunan skema lain langsung DIHAPUS, bukan
 * sekadar diabaikan: kalau hanya diabaikan ia akan terus mengendap sampai
 * kuota penuh, membawa data pribadi yang tidak lagi ada gunanya.
 *
 * @returns {?{jawaban: object, langkah: number, pada: number}}
 */
export function bacaDraf(kunci, tanda) {
    if (! kunci) {
        return null;
    }

    let simpanan = null;
    try {
        simpanan = JSON.parse(localStorage.getItem(kunci));
    } catch (e) {
        // Isinya rusak atau localStorage tidak bisa dibaca. Perlakukan seperti
        // tidak ada draf; yang rusak dibuang di bawah kalau memang bisa.
        hapusDraf(kunci);

        return null;
    }

    if (! simpanan || typeof simpanan !== 'object' || Array.isArray(simpanan)) {
        return null;
    }

    const usang = simpanan.v !== VERSI
        || ! simpanan.pada
        || (Date.now() - Number(simpanan.pada)) > UMUR_MAKS_MS
        || simpanan.tanda !== tanda;

    if (usang) {
        hapusDraf(kunci);

        return null;
    }

    if (! simpanan.jawaban || typeof simpanan.jawaban !== 'object' || Array.isArray(simpanan.jawaban)) {
        hapusDraf(kunci);

        return null;
    }

    return {
        jawaban: simpanan.jawaban,
        langkah: Math.max(0, Number(simpanan.langkah) || 0),
        pada: Number(simpanan.pada),
    };
}

/**
 * Simpan draf.
 *
 * @returns {boolean} false bila peramban menolak menyimpan. Pemanggil TIDAK
 *                    boleh memperlakukan ini sebagai galat yang menghentikan
 *                    pengisian — lihat catatan di kepala berkas.
 */
export function tulisDraf(kunci, { jawaban, langkah, peta } = {}) {
    if (! kunci || ! peta) {
        return false;
    }

    try {
        localStorage.setItem(kunci, JSON.stringify({
            v: VERSI,
            tanda: peta.tanda,
            langkah: Math.max(0, Number(langkah) || 0),
            pada: Date.now(),
            jawaban: saring(jawaban, peta),
        }));

        return true;
    } catch (e) {
        return false;
    }
}

export function hapusDraf(kunci) {
    if (! kunci) {
        return;
    }

    try {
        localStorage.removeItem(kunci);
    } catch (e) {
        // Mode privat pada beberapa peramban melarang menulis sama sekali.
    }
}

/**
 * Buang draf kedaluwarsa milik lowongan MANA PUN.
 *
 * `bacaDraf()` hanya menyentuh draf yang kebetulan dibuka lagi. Kandidat yang
 * membuka lima lowongan lalu hanya menuntaskan satu meninggalkan empat draf
 * berisi data pribadi yang tidak pernah dijenguk siapa pun. Sapuan ini
 * dijalankan sekali tiap halaman lamaran dibuka.
 *
 * @returns {number} jumlah draf yang dibuang
 */
export function sapuDrafKedaluwarsa() {
    let dibuang = 0;

    try {
        const batas = Date.now() - UMUR_MAKS_MS;
        // Kunci dikumpulkan dulu: menghapus sambil menelusuri localStorage
        // menggeser indeksnya dan membuat sebagian kunci terlewat.
        const kunci = [];
        for (let i = 0; i < localStorage.length; i += 1) {
            const k = localStorage.key(i);
            if (k && k.startsWith(AWALAN)) {
                kunci.push(k);
            }
        }

        kunci.forEach((k) => {
            let pada = 0;
            try {
                pada = Number(JSON.parse(localStorage.getItem(k))?.pada) || 0;
            } catch (e) {
                pada = 0; // rusak → ikut dibuang
            }
            if (pada <= batas) {
                localStorage.removeItem(k);
                dibuang += 1;
            }
        });
    } catch (e) {
        // localStorage tidak tersedia. Tidak ada yang perlu disapu.
    }

    return dibuang;
}
