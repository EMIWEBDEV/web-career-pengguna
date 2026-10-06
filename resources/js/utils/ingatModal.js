/* WEB CAREER — MODAL YANG SELAMAT DARI REFRESH.
 *
 * Masalahnya sederhana dan mahal: admin mengisi borang panjang di dalam modal,
 * lalu halamannya ter-refresh — Ctrl+R yang tidak sengaja, sesi Vite yang
 * memuat ulang, klik tombol back-forward, laptop yang menutup. Modalnya hilang
 * beserta seluruh isian, tanpa satu pun peringatan, dan tidak ada cara
 * mengembalikannya.
 *
 * Mixin ini menyimpan keadaan halaman ke sessionStorage selama ada modal yang
 * terbuka, dan memasangnya kembali saat halaman dimuat ulang.
 *
 * KENAPA sessionStorage, bukan localStorage: umurnya persis seumur TAB. Refresh
 * tidak menghapusnya, tetapi menutup tab menghapusnya — dan itu memang yang
 * diinginkan. Orang yang menutup halaman sudah memutuskan untuk pergi; borang
 * setengah jadi yang muncul lagi seminggu kemudian, di komputer bersama,
 * berisi data kandidat, adalah hal lain lagi.
 *
 * YANG TIDAK DIINGAT:
 *   - Dialog konfirmasi hapus/batal/cabut. Konfirmasi yang muncul sendiri
 *     setelah refresh adalah jebakan: jarinya masih di posisi "Ya".
 *   - Penanda proses (memuat, menyimpan, sibuk). Kalau ikut disimpan, borang
 *     bisa kembali dalam keadaan terkunci selamanya — prosesnya sudah mati
 *     bersama halaman lamanya, tidak ada yang akan membukanya lagi.
 *   - Daftar & angka ringkasan. Halaman memuat ulang semuanya di mounted();
 *     menyimpannya cuma memperbesar simpanan dan menahan data basi sesaat.
 */

const AWALAN = 'wca:modal:';

/* Simpanan kedaluwarsa. Tab yang dibiarkan terbuka semalaman lalu di-refresh
   pagi harinya sebaiknya mulai bersih — borang kemarin yang muncul kembali
   tanpa konteks lebih membingungkan daripada borang kosong. */
const UMUR_MS = 12 * 60 * 60 * 1000;

/* Batas ukuran. sessionStorage biasanya ~5 MB untuk SELURUH asal (origin), jadi
   satu halaman tidak boleh menghabiskannya sendiri. */
const BATAS_BYTE = 512 * 1024;

/* Penanda proses & data yang selalu dimuat ulang — jangan diingat. */
const ABAIKAN_BAWAAN = [
    /^(list|rows|items|daftar|kandidat|peserta|data|total|totalData|totalPages|page|perPage|halaman)$/i,
    /^(loading|memuat|saving|menyimpan|deleting|menghapus|busy|sibuk|toast|notice|noticeType|flash)$/i,
    /(loading|memuat|saving|menyimpan|deleting|busy|sibuk|timer|interval|denyut|polling)$/i,
    /^(tm|dtm)$/i,
    /^ringkasan$/i,
];

/* Modal yang TIDAK BOLEH bangkit sendiri: semuanya bertanya "yakin?" tentang
   sesuatu yang tidak bisa dibatalkan.
 *
 * Polanya ditulis berpasangan — awalan huruf kecil (delShow) dan potongan
 * berhuruf besar di tengah nama (katDelShow) — bukan /del/i yang polos, sebab
 * yang polos ikut menangkap kata biasa seperti `model` dan `modelValue`. */
const JANGAN_BAWAAN = [
    /^del/i, /Del/,
    /^hapus/i, /Hapus/,
    /^batal/i, /Batal/,
    /^cabut/i, /Cabut/,
    /^guard/i, /Guard/,
];

/* Nama yang berarti "ada modal terbuka". */
const POLA_BUKA = /(show|tampil|terbuka|buka)/i;

function cocok(nama, pola) {
    return pola.some((p) => (p instanceof RegExp ? p.test(nama) : p === nama));
}

function baca(kunci) {
    try {
        const mentah = sessionStorage.getItem(AWALAN + kunci);
        if (! mentah) return null;

        const paket = JSON.parse(mentah);
        if (! paket || typeof paket !== 'object' || ! paket.ts) return null;

        if (Date.now() - paket.ts > UMUR_MS) {
            sessionStorage.removeItem(AWALAN + kunci);

            return null;
        }

        return paket.data || null;
    } catch (e) {
        // JSON rusak / storage diblokir peramban: anggap tidak ada simpanan.
        return null;
    }
}

/**
 * Nilai yang tidak selamat melewati JSON.
 *
 * BERKAS — JSON.stringify mengubah File/Blob jadi `{}`, dan `{}` yang dipasang
 * kembali ke borang tampak seperti berkas yang masih terpilih padahal isinya
 * sudah tidak ada. Lebih jujur mengembalikannya sebagai kosong: orangnya
 * memilih ulang, bukan mengirim benda hampa.
 *
 * TANGGAL — lebih licik, karena kelihatannya berhasil. `new Date(...)` menjadi
 * string ISO saat disimpan dan tetap string saat dibaca kembali; komponen yang
 * menerimanya menuntut Date lalu memuntahkan "type check failed for prop", dan
 * bidangnya diam-diam berhenti bekerja. Kuncinya karena itu DILEWATI sama
 * sekali (mengembalikan `undefined` membuat JSON.stringify membuang kuncinya),
 * bukan dikosongkan — dengan begitu nilai bawaan dari data() tetap utuh dan
 * tidak ada yang perlu memasang apa pun kembali.
 */
function pengganti(_kunci, nilai) {
    if (typeof File !== 'undefined' && nilai instanceof File) return null;
    if (typeof Blob !== 'undefined' && nilai instanceof Blob) return null;
    if (typeof FormData !== 'undefined' && nilai instanceof FormData) return null;
    if (nilai instanceof Date) return undefined;

    return nilai;
}

function tulis(kunci, data) {
    try {
        const isi = JSON.stringify({ ts: Date.now(), data }, pengganti);
        if (isi.length > BATAS_BYTE) return;

        sessionStorage.setItem(AWALAN + kunci, isi);
    } catch (e) {
        // Kuota penuh atau mode privat. Kehilangan simpanan boleh; mematikan
        // borang karena gagal menyimpan cadangan, tidak.
    }
}

function hapus(kunci) {
    try {
        sessionStorage.removeItem(AWALAN + kunci);
    } catch (e) {
        /* diamkan — lihat catatan di tulis() */
    }
}

/**
 * @param {string} kunci   Nama unik halaman ini di sessionStorage.
 * @param {object} [opsi]
 *   - abaikan: nama/pola data tambahan yang tidak ikut disimpan
 *   - jangan:  nama/pola yang boleh disimpan tapi TIDAK dipasang kembali
 *   - buka:    daftar nama penanda "modal terbuka" (bila deteksi otomatis salah)
 */
export function ingatModal(kunci, opsi = {}) {
    const abaikan = [...ABAIKAN_BAWAAN, ...(opsi.abaikan || [])];
    const jangan = [...JANGAN_BAWAAN, ...(opsi.jangan || [])];

    return {
        created() {
            const simpanan = baca(kunci);

            if (simpanan) {
                const bukaNama = opsi.buka || Object.keys(simpanan).filter((n) => POLA_BUKA.test(n) && ! cocok(n, jangan));
                const adaYangTerbuka = bukaNama.some((n) => simpanan[n] === true);

                if (adaYangTerbuka) {
                    for (const nama of Object.keys(simpanan)) {
                        if (! (nama in this.$data) || cocok(nama, jangan)) continue;
                        this.$data[nama] = simpanan[nama];
                    }
                } else {
                    // Simpanan tanpa modal terbuka tidak ada gunanya — dan bila
                    // dipasang diam-diam, penyaring & halaman daftar ikut mundur
                    // ke keadaan lama tanpa ada yang memintanya.
                    hapus(kunci);
                }
            }

            /* Satu pengamat untuk SELURUH $data. Mengamati per bidang berarti
               daftar bidang harus dirawat di tiap halaman, dan bidang yang
               terlewat adalah isian yang diam-diam tidak pernah tersimpan. */
            this.$watch(
                () => this.$data,
                () => this.ingatModalSimpan(),
                { deep: true },
            );
        },
        beforeUnmount() {
            if (this.ingatModalTimer) {
                clearTimeout(this.ingatModalTimer);
                this.ingatModalTimer = null;
            }
        },
        methods: {
            /**
             * Menyimpan keadaan — ditunda sesaat.
             *
             * Tanpa penundaan, satu huruf yang diketik di kotak deskripsi berarti
             * satu JSON.stringify seluruh halaman. Yang menarik bagi kita bukan
             * setiap ketukan, melainkan keadaan beberapa saat setelah orang
             * berhenti mengetik.
             */
            ingatModalSimpan() {
                if (this.ingatModalTimer) clearTimeout(this.ingatModalTimer);

                this.ingatModalTimer = setTimeout(() => {
                    this.ingatModalTimer = null;

                    const nyata = {};
                    let terbuka = false;

                    for (const [nama, nilai] of Object.entries(this.$data)) {
                        if (cocok(nama, abaikan)) continue;
                        if (typeof nilai === 'function') continue;

                        nyata[nama] = nilai;
                        if (nilai === true && POLA_BUKA.test(nama) && ! cocok(nama, jangan)) terbuka = true;
                    }

                    if (opsi.buka) {
                        terbuka = opsi.buka.some((n) => this.$data[n] === true);
                    }

                    // Tidak ada modal terbuka → tidak ada yang perlu diselamatkan,
                    // dan simpanan lama justru harus dibuang: modal yang sudah
                    // ditutup sengaja tidak boleh hidup lagi karena refresh.
                    if (! terbuka) {
                        hapus(kunci);

                        return;
                    }

                    tulis(kunci, nyata);
                }, 250);
            },
        },
    };
}
