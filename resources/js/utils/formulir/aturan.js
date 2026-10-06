/**
 * WEB CAREER — Mesin aturan formulir.
 *
 * Berisi logika yang dipakai SEMUA formulir: syarat tampil, nilai awal,
 * dan validasi. Sengaja dipisah dari layout supaya menambah aturan baru
 * tidak menyentuh tampilan, dan sebaliknya.
 *
 * Tidak ada nama formulir apa pun di berkas ini.
 */

/**
 * Evaluasi satu syarat tampil.
 *
 * INI PENGGANTI IF/ELSE DI KODE TAMPILAN.
 * Aturannya ditulis sebagai DATA di skema.js masing-masing formulir:
 *
 *   tampil_jika: { field: 'jenis_kelamin', operator: '=', nilai: 'Perempuan' }
 *
 * Operator: = != > < >= <= ADA_DI TIDAK_ADA_DI
 * (dua terakhir menerima `nilai` berupa array — kosakatanya sengaja sama
 * dengan MesinSyarat di sisi PHP supaya admin tidak menghafal dua daftar.)
 *
 * Menambah syarat baru cukup menyunting skema.js — berkas ini tidak
 * perlu disentuh lagi.
 */
export function syaratTerpenuhi(syarat, jawaban) {
    if (!syarat || !syarat.field) return true;

    const kiri = jawaban?.[syarat.field];
    const kanan = syarat.nilai;

    // Keanggotaan himpunan. Dipakai syarat seperti "hanya jenjang perguruan
    // tinggi", yang mustahil ditulis dengan satu perbandingan tunggal.
    if (syarat.operator === 'ADA_DI' || syarat.operator === 'TIDAK_ADA_DI') {
        const daftar = (Array.isArray(kanan) ? kanan : [kanan]).map((v) =>
            String(v ?? '').trim().toLowerCase(),
        );
        const punya = (v) => daftar.includes(String(v ?? '').trim().toLowerCase());
        const ada = Array.isArray(kiri) ? kiri.some(punya) : punya(kiri);
        return syarat.operator === 'ADA_DI' ? ada : !ada;
    }

    // Checkbox menyimpan array -> "=" berarti "mengandung nilai ini".
    if (Array.isArray(kiri)) {
        return syarat.operator === '!=' ? !kiri.includes(kanan) : kiri.includes(kanan);
    }

    // Perbandingan angka dipakai HANYA bila kedua sisi memang angka.
    // Kalau dipaksa sebagai teks, '10' < '3' dan syarat IPK jadi salah.
    const angkaKiri = Number(kiri);
    const angkaKanan = Number(kanan);
    const keduanyaAngka =
        kiri !== '' && kiri !== null && kiri !== undefined && !Number.isNaN(angkaKiri) && !Number.isNaN(angkaKanan);

    const a = keduanyaAngka ? angkaKiri : String(kiri ?? '').trim().toLowerCase();
    const b = keduanyaAngka ? angkaKanan : String(kanan ?? '').trim().toLowerCase();

    switch (syarat.operator) {
        case '!=': return a !== b;
        case '>': return a > b;
        case '<': return a < b;
        case '>=': return a >= b;
        case '<=': return a <= b;
        default: return a === b;
    }
}

/**
 * WAJIB YANG BERGANTUNG PADA JAWABAN LAIN.
 *
 * `wajib` menyatakan "selalu wajib". `wajib_jika` menyatakan "wajib HANYA bila
 * syarat ini terpenuhi" - bentuk syaratnya sama persis dengan `tampil_jika`,
 * jadi admin tidak perlu menghafal dua tata cara.
 *
 *   wajib_jika: { field: 'status_pernikahan', operator: '=', nilai: 'Menikah' }
 *
 * ── KENAPA BUKAN CUKUP DENGAN `tampil_jika` ────────────────────────────────
 *
 * `tampil_jika` menyembunyikan kolomnya sama sekali. Itu benar untuk isian yang
 * memang tidak berlaku (nama pasangan bagi yang lajang), tapi salah untuk isian
 * yang tetap boleh diisi namun baru MENGIKAT pada jawaban tertentu - mis. nomor
 * NPWP yang opsional bagi pelamar magang tapi wajib bagi pelamar tetap.
 * Menyembunyikannya membuang jawaban yang sebenarnya berguna; memaksanya selalu
 * wajib menahan kandidat yang memang tidak punya.
 *
 * Keduanya bisa dipasang bersamaan: yang tersembunyi tidak pernah divalidasi
 * (lihat periksaLangkah), jadi `wajib_jika` hanya berlaku selagi kolomnya
 * benar-benar terlihat.
 *
 * Tanpa `wajib_jika`, nilainya jatuh ke `wajib` - jadi seluruh formulir lama
 * berperilaku persis seperti sebelumnya.
 */
export function wajibKini(f, jawaban) {
    if (!f) {
        return false;
    }

    if (f.wajib_jika && f.wajib_jika.field) {
        return syaratTerpenuhi(f.wajib_jika, jawaban);
    }

    return !!f.wajib;
}

/** Field yang lolos syarat tampil pada kondisi jawaban saat ini. */
export function fieldTampil(field, jawaban) {
    return (field || []).filter((f) => syaratTerpenuhi(f.tampil_jika, jawaban));
}

/**
 * Bagian yang layak tampil: lolos syarat bagian (bila ada) DAN masih menyisakan
 * minimal satu field terlihat. Bagian berulang tidak dinilai per field karena
 * isinya baru muncul setelah kandidat menambah baris.
 *
 * Syarat di tingkat bagian dipakai untuk blok yang seluruhnya tidak relevan —
 * mis. "Riwayat Pekerjaan" bagi pelamar yang menjawab belum punya pengalaman.
 */
export function bagianTampil(bagian, jawaban) {
    return (bagian || []).filter(
        (b) => syaratTerpenuhi(b.tampil_jika, jawaban) && (b.berulang || fieldTampil(b.field, jawaban).length > 0),
    );
}

/** Semua field sebuah skema, diratakan. */
export function semuaField(skema) {
    const out = [];
    (skema?.langkah || []).forEach((L) =>
        (L.bagian || []).forEach((B) => (B.field || []).forEach((f) => out.push(f))),
    );
    return out;
}

/**
 * Field bertanda `dapat_disaring` — nilainya diproyeksikan ke
 * N_WEB_CAREERS_Formulir_Jawaban_Index saat pengisian dikirim, supaya bisa
 * dipakai syarat auto-gugur di Program Kegiatan.
 */
export function fieldDapatDisaring(skema) {
    return semuaField(skema).filter((f) => f.dapat_disaring);
}

/** Nilai kosong yang sesuai tipe field. */
export function nilaiKosong(f) {
    if (f.tipe === 'checkbox') return [];
    if (f.tipe === 'consent') return false;
    if (f.tipe === 'number') return null;
    return '';
}

/** Key penampung sebuah bagian berulang (array of objek). */
export function kunciBagian(B) {
    return (
        B.key ||
        String(B.judul || 'bagian')
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, '_')
            .replace(/^_+|_+$/g, '')
    );
}

export function barisKosong(B) {
    const baris = {};
    (B.field || []).forEach((f) => (baris[f.key] = nilaiKosong(f)));
    return baris;
}

/**
 * Bangun objek jawaban awal dari skema.
 * Bagian berulang jadi array berisi satu baris kosong.
 *
 * `profil` mengisi otomatis dua macam field:
 *
 *   tipe 'prefill'   — TERKUNCI. Jawabannya milik sistem; kandidat hanya
 *                      melihatnya (kecuali dibuka lewat `buka_jika`).
 *   field biasa      — DISEMAI. Cukup menambahkan `prefill: '<kunci profil>'`
 *                      pada field bertipe apa pun: nilainya terisi dari profil
 *                      bila ada, tapi tetap BISA DIUBAH kandidat.
 *
 * Yang kedua ditambahkan untuk jawaban yang memang sudah diberikan di formulir
 * pendaftaran tapi WAJAR BERUBAH — "Tahun Lulus / Perkiraan Lulus" adalah
 * perkiraan, dan mahasiswa tingkat akhir kerap merevisinya. Menguncinya seperti
 * nama kampus akan memaksa kandidat mengirim data yang ia tahu keliru; tidak
 * menyemainya sama sekali memaksa ia mengetik ulang sesuatu yang sudah dijawab.
 *
 * Seeding hanya berlaku bila profilnya benar-benar berisi — profil kosong tidak
 * boleh menimpa nilai kosong bawaan tipe field (mis. array untuk checkbox).
 */
/**
 * Nilai awal satu field biasa: dari profil bila layak, kalau tidak kosong biasa.
 *
 * DUA PENJAGA, keduanya karena data pendaftaran tidak pernah serapi skema:
 *
 *  1. Tipe. Formulir lama menyimpan tahun sebagai ANGKA (2026), sedangkan opsi
 *     select berupa TEKS ('2026'). Tanpa disamakan, kotaknya tampil kosong
 *     padahal nilainya ada — kandidat lalu mengisi ulang dan mengira sistemnya
 *     tidak menyimpan apa-apa.
 *  2. Keanggotaan. Nilai di luar daftar pilihan (mis. tahun 2011 pada daftar
 *     yang dimulai 2018) TIDAK dipakai: select yang memegang nilai asing
 *     tampak kosong tapi ikut terkirim saat disimpan, jadi jawabannya lolos
 *     tanpa pernah benar-benar terlihat kandidat.
 */
function semaiField(f, profil) {
    const semai = f.prefill ? profil?.[f.prefill] : null;
    if (semai === null || semai === undefined || semai === '') {
        return nilaiKosong(f);
    }

    if (Array.isArray(f.opsi) && f.opsi.length) {
        const cocok = f.opsi.find((o) => String(o) === String(semai));

        return cocok ?? nilaiKosong(f);
    }

    return semai;
}

export function jawabanAwal(skema, profil = {}) {
    const out = {};
    (skema?.langkah || []).forEach((L) => {
        (L.bagian || []).forEach((B) => {
            if (B.berulang) {
                out[kunciBagian(B)] = [barisKosong(B)];
                return;
            }
            (B.field || []).forEach((f) => {
                if (f.tipe === 'prefill') {
                    out[f.key] = profil[f.prefill] ?? '';

                    return;
                }

                out[f.key] = semaiField(f, profil);
            });
        });
    });
    return out;
}

/**
 * Validasi satu langkah. Field yang sedang TERSEMBUNYI tidak ikut divalidasi —
 * memaksa mengisi kolom yang tidak terlihat adalah jebakan klasik form dinamis.
 *
 * @returns {string[]} daftar pesan galat (kosong = lolos)
 */
export function periksaLangkah(langkah, jawaban) {
    const galat = [];

    (langkah?.bagian || []).forEach((B) => {
        // Bagian yang sedang tersembunyi tidak divalidasi — alasannya sama
        // dengan field tersembunyi: menuntut isian yang tak terlihat = jebakan.
        if (!syaratTerpenuhi(B.tampil_jika, jawaban)) return;

        if (B.berulang) {
            const baris = jawaban[kunciBagian(B)] || [];
            baris.forEach((r, i) => {
                fieldTampil(B.field, r).forEach((f) => {
                    // Syarat dinilai terhadap BARIS ini, bukan jawaban global:
                    // di bagian berulang, "menikah" pada baris ke-2 tidak boleh
                    // mewajibkan kolom di baris ke-1.
                    if (wajibKini(f, r) && kosong(r[f.key])) {
                        galat.push(`${B.judul} baris ${i + 1}: "${f.label}" wajib diisi.`);
                        return;
                    }
                    const gFormat = galatFormat(f, r[f.key]);
                    if (gFormat) galat.push(`${B.judul} baris ${i + 1}: ${gFormat}`);
                });
            });
            return;
        }

        fieldTampil(B.field, jawaban).forEach((f) => {
            // Prefill yang masih terkunci tidak divalidasi (isinya milik
            // sistem). Begitu dibuka untuk disunting, ia jadi isian biasa —
            // termasuk boleh dinyatakan wajib dan dicek format teleponnya.
            if (f.tipe === 'prefill' && !syaratTerpenuhi(f.buka_jika, jawaban)) return;
            if (wajibKini(f, jawaban) && kosong(jawaban[f.key])) {
                galat.push(
                    f.tipe === 'consent' ? `Anda harus menyetujui: "${f.label}".` : `"${f.label}" wajib diisi.`,
                );
                return;
            }
            const gFormat = galatFormat(f, jawaban[f.key]);
            if (gFormat) galat.push(gFormat);

            const gBeda = galatBedaDengan(f, jawaban);
            if (gBeda) galat.push(gBeda);
        });
    });

    return galat;
}

/** Tipe field yang berupa unggahan berkas. Cermin BerkasFormulir::TIPE_BERKAS. */
const TIPE_BERKAS = new Set(['file', 'foto']);

/**
 * BERKAS YANG HARUS SUDAH ADA DI SERVER untuk jawaban ini.
 *
 * Nama berkas masuk ke jawaban begitu dipilih, sedangkan isinya naik lewat
 * permintaan terpisah yang bisa gagal. Yang dikembalikan: setiap field berkas
 * TERLIHAT yang wajib, ATAU yang jawabannya sudah menyebut nama berkas — nama
 * tanpa berkas adalah tanda unggahannya gagal, dan itu harus tertangkap
 * sebelum formulir terkirim, termasuk pada isian yang tidak wajib.
 *
 * Aturan tampil & wajibnya sama dengan periksaLangkah(): bagian tersembunyi
 * dilewati, di bagian berulang syarat dinilai terhadap barisnya sendiri.
 * Cermin PHP-nya BerkasFormulir::harapan() — server menolak kiriman dengan
 * aturan yang sama, jadi keduanya WAJIB diubah bersamaan.
 *
 * `skema` harus sudah dinormalkan (normalisasiSkema), sama seperti yang
 * dirender, supaya kunci field & bagian sama persis dengan jawabannya.
 *
 * @returns {Array<{bagian: string|null, baris: number|null, field: string, label: string, judulBagian: string|null, nama: string, wajib: boolean, pesan: string}>}
 */
export function harapanBerkas(skema, jawaban) {
    const out = [];
    const akar = jawaban || {};

    (skema?.langkah || []).forEach((L) => {
        (L.bagian || []).forEach((B) => {
            if (!syaratTerpenuhi(B.tampil_jika, akar)) return;

            if (B.berulang) {
                const kunci = kunciBagian(B);
                const daftar = akar[kunci];
                if (!Array.isArray(daftar)) return;

                daftar.forEach((r, i) => {
                    if (!r || typeof r !== 'object' || Array.isArray(r)) return;
                    (B.field || []).forEach((f) => {
                        const h = harapSatu(f, r, kunci, i, B.judul);
                        if (h) out.push(h);
                    });
                });
                return;
            }

            (B.field || []).forEach((f) => {
                const h = harapSatu(f, akar, null, null, null);
                if (h) out.push(h);
            });
        });
    });

    return out;
}

/** Field berkas yang seharusnya ada, tapi `ada(bagian, baris, field)` menyatakan tidak. */
export function berkasKurang(skema, jawaban, ada) {
    return harapanBerkas(skema, jawaban).filter((h) => !ada(h.bagian, h.baris, h.field));
}

function harapSatu(f, konteks, bagian, baris, judulBagian) {
    if (!f || !TIPE_BERKAS.has(f.tipe) || !syaratTerpenuhi(f.tampil_jika, konteks)) return null;

    const nilai = konteks[f.key];
    const wajib = wajibKini(f, konteks);
    const adaNama = !kosong(nilai);
    if (!wajib && !adaNama) return null;

    const awalan = judulBagian !== null ? `${judulBagian} baris ${baris + 1}: ` : '';

    return {
        bagian,
        baris,
        field: f.key,
        label: f.label,
        judulBagian,
        nama: adaNama ? String(nilai) : '',
        wajib,
        pesan:
            awalan +
            (adaNama
                ? `"${f.label}" belum tersimpan di server — unggah ulang berkasnya.`
                : `"${f.label}" wajib diunggah.`),
    };
}

/** Gabungan pemeriksaan format nilai satu field: telepon, email, digit saja. */
function galatFormat(f, v) {
    return galatTelepon(f, v) || galatEmail(f, v) || galatAngka(f, v) || galatDaftar(f, v);
}

/**
 * Daftar butir: jumlah yang BENAR-BENAR terisi, bukan panjang lariknya.
 *
 * Larik bisa berisi lima baris yang tiga di antaranya kosong. Menghitung
 * panjangnya akan meloloskan itu — dan tuntutan "minimal 5" jadi tuntutan
 * "tekan tambah 5 kali".
 */
function galatDaftar(f, v) {
    if (f.tipe !== 'daftar') return '';

    const isi = Array.isArray(v) ? v.filter((x) => String(x ?? '').trim() !== '') : [];
    const min = Math.max(1, Number(f.min_butir) || 1);
    const maks = Number(f.maks_butir) || 0;

    // Kosong sama sekali diserahkan ke pemeriksa "wajib" di atas: field tak
    // wajib yang dibiarkan kosong bukan pelanggaran, dan dua pesan untuk satu
    // keadaan hanya membuat orang menebak mana yang harus dituruti.
    if (isi.length === 0) return '';

    if (isi.length < min) {
        return `"${f.label}" baru terisi ${isi.length} dari ${min} yang diminta.`;
    }
    if (maks && isi.length > maks) {
        return `"${f.label}" melebihi ${maks} jawaban.`;
    }

    return '';
}

/** Email harus berbentuk "sesuatu@sesuatu.sesuatu" — bukan sekadar terisi. */
function galatEmail(f, v) {
    const tipeEfektif = f.tipe === 'prefill' ? f.tipe_buka : f.tipe;
    if (tipeEfektif !== 'email' || kosong(v)) return '';
    const ok = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(v).trim());
    return ok ? '' : `"${f.label}" belum berupa format email yang benar.`;
}

/**
 * Field `hanya_angka` (mis. NIK): isinya sudah disaring jadi digit saja sejak
 * diketik (lihat FieldRenderer.vue), pemeriksaan ini jaring pengaman kedua —
 * mis. nilai lama yang tersimpan sebelum aturan ini ditambahkan.
 */
function galatAngka(f, v) {
    if (!f.hanya_angka || kosong(v)) return '';
    const s = String(v).trim();
    if (!/^\d+$/.test(s)) return `"${f.label}" hanya boleh berisi angka.`;
    if (f.maks_panjang && s.length !== f.maks_panjang) {
        return `"${f.label}" harus tepat ${f.maks_panjang} digit.`;
    }
    return '';
}

/**
 * Field yang nilainya TIDAK BOLEH sama dengan field lain (`beda_dengan`).
 *
 * Dipakai kontak darurat: nomor yang sama dengan nomor kandidat sendiri membuat
 * kontak darurat kehilangan gunanya — justru saat kandidat tak bisa dihubungi.
 * Dibandingkan sebagai digit saja, supaya "+62 812-3456" dan "628123456"
 * tidak lolos hanya karena beda tanda baca.
 */
function galatBedaDengan(f, jawaban) {
    if (!f.beda_dengan || kosong(jawaban[f.key])) return '';

    const digit = (v) => String(v ?? '').replace(/\D/g, '');
    const ini = digit(jawaban[f.key]);
    const lain = digit(jawaban[f.beda_dengan]);
    if (!ini || !lain || ini !== lain) return '';

    return `"${f.label}" tidak boleh sama dengan nomor Anda sendiri.`;
}

// Telepon internasional: kode negara + nomor. Panjang beda tiap negara, jadi
// cukup periksa minimal 8 digit (tak lagi wajib berawalan 62).
function galatTelepon(f, v) {
    const tipeEfektif = f.tipe === 'prefill' ? f.tipe_buka : f.tipe;
    if (tipeEfektif !== 'phone' || kosong(v)) return '';
    const s = String(v).replace(/\D/g, '');
    if (s.length < 8) return `"${f.label}" belum lengkap.`;
    return '';
}

function kosong(v) {
    if (Array.isArray(v)) return v.length === 0;
    if (typeof v === 'boolean') return v === false;
    return v === null || v === undefined || String(v).trim() === '';
}
