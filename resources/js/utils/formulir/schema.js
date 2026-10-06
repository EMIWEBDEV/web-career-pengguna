/**
 * WEB CAREER - Helper schema formulir dinamis.
 *
 * Schema DB dibuat kompatibel dengan renderer lama:
 *   { layout: 'SATU_HALAMAN'|'BERTAHAP', langkah: [{ bagian: [{ field: [] }] }] }
 */

import { TIPE_VALID, bersihkanField, galatTipe } from './katalogField';

// Daftar tipe hidup di katalog sekarang. Diekspor ulang dari sini supaya
// pemakai lama tidak perlu tahu bahwa tempatnya pindah.
export { TIPE_VALID };

/**
 * Konteks pemakaian formulir. Menentukan kunci isi-otomatis apa yang tersedia:
 * `nik` hanya terisi di formulir pendaftaran, `kampus` hanya di formulir tahap.
 */
const KONTEKS_VALID = new Set(['PENDAFTARAN', 'TAHAP', 'KEDUANYA']);

export function normalisasiSkema(skema) {
    const s = skema && typeof skema === 'object' ? skema : {};
    const layout = String(s.layout || 'SATU_HALAMAN').toUpperCase() === 'BERTAHAP' ? 'BERTAHAP' : 'SATU_HALAMAN';
    const langkah = Array.isArray(s.langkah) ? s.langkah : [];
    const konteksMentah = String(s.konteks || '').toUpperCase();

    return {
        schema_version: Number(s.schema_version || 1),
        // Skema lama tidak menyimpan konteks. KEDUANYA adalah default yang aman:
        // ia hanya mengizinkan kunci isi-otomatis yang tersedia di kedua konteks,
        // jadi formulir berjalan tidak mendadak jadi tidak sah.
        konteks: KONTEKS_VALID.has(konteksMentah) ? konteksMentah : 'KEDUANYA',
        template: s.template || 'TEMPLATE_1',
        layout,
        langkah: langkah.map((L, i) => ({
            kode: String(L.kode || `LANGKAH_${i + 1}`).trim(),
            judul: String(L.judul || `Langkah ${i + 1}`).trim(),
            ikon: L.ikon || 'bi-card-list',
            deskripsi: L.deskripsi || '',
            tampil_jika: L.tampil_jika || null,
            bagian: normalisasiBagian(L.bagian, i),
        })),
    };
}

function normalisasiBagian(bagian, langkahIndex = 0) {
    return (Array.isArray(bagian) ? bagian : []).map((B, i) => ({
        key: B.key || '',
        // Judul eksplisit '' (mis. langkah bertahap yang cuma punya satu bagian,
        // sehingga header langkah sudah cukup) sengaja dibiarkan kosong — beda
        // dengan tidak diisi sama sekali, yang tetap dapat label bawaan.
        judul: B.judul === undefined || B.judul === null ? `Bagian ${i + 1}` : String(B.judul).trim(),
        deskripsi: B.deskripsi || '',
        berulang: !!B.berulang,
        maks_baris: Number(B.maks_baris || 5),
        tampil_jika: B.tampil_jika || null,
        field: normalisasiField(B.field, langkahIndex, i),
    }));
}

function normalisasiField(field, langkahIndex = 0, bagianIndex = 0) {
    return (Array.isArray(field) ? field : []).map((F, fieldIndex) => {
        const tipe = TIPE_VALID.has(String(F.tipe || '').toLowerCase()) ? String(F.tipe).toLowerCase() : 'text';
        const key = slugKey(F.key || F.label || 'field');

        // Dibersihkan LEBIH DULU, baru dilengkapi. Urutannya penting: properti
        // sisa tipe lama harus gugur sebelum kita menambahkan yang wajib ada,
        // supaya skema yang sudah terlanjur kotor ikut rapi saat dimuat — bukan
        // hanya saat tipenya diubah di editor.
        const bersih = bersihkanField({ ...F, tipe });

        return {
            ...bersih,
            field_id: String(F.field_id || F.id || fallbackFieldId(key, langkahIndex, bagianIndex, fieldIndex)),
            key,
            label: String(F.label || F.key || 'Pertanyaan').trim(),
            tipe,
            wajib: !!F.wajib,
            penuh: !!bersih.penuh || F.lebar === 'full',
            lebar_persen: normalisasiLebarPersen(bersih, F),
            lebar_jika: normalisasiLebarJika(F.lebar_jika),
            ...(bolehPunyaOpsi(tipe) ? { opsi: Array.isArray(bersih.opsi) ? bersih.opsi : [] } : {}),
        };
    });
}

/** Hanya tipe berdaftar-pilihan yang membawa `opsi`; sisanya tidak boleh punya. */
function bolehPunyaOpsi(tipe) {
    return ['select', 'radio', 'checkbox'].includes(tipe);
}

/**
 * Lebar BERSYARAT — lebar field berubah mengikuti jawaban field lain.
 *
 * Contoh: "Status Kemahasiswaan" memakan satu baris penuh selagi belum dijawab
 * atau dijawab "Sudah Lulus", lalu menyusut jadi setengah begitu dijawab
 * "Mahasiswa" — karena saat itu field "Semester" muncul di sebelahnya.
 *
 * Ditulis sebagai DATA di skema (diatur admin lewat Master Formulir), bukan
 * if/else per nama field di kode tampilan. Syaratnya memakai kosakata yang sama
 * dengan `tampil_jika` supaya admin tidak menghafal dua aturan berbeda.
 */
function normalisasiLebarJika(aturan) {
    if (!aturan || typeof aturan !== 'object' || !aturan.field) return null;
    return {
        field: String(aturan.field),
        operator: String(aturan.operator || '='),
        nilai: aturan.nilai ?? '',
        lebar_persen: Math.min(100, Math.max(33, Math.round(Number(aturan.lebar_persen) || 100))),
    };
}

/**
 * PANJANG MAKSIMAL SEBUAH KEY.
 *
 * Bukan angka pilihan rasa: `Field_Key` di N_WEB_CAREERS_Formulir_Berkas dan
 * N_WEB_CAREERS_Formulir_Jawaban_Index bertipe varchar(60). Key yang lebih
 * panjang tidak ditolak saat formulir disimpan — skema tersimpan sebagai JSON,
 * yang tidak punya batas — melainkan jauh kemudian, saat KANDIDAT mengunggah
 * berkas, dalam bentuk galat mentah SQL Server:
 *
 *   "String or binary data would be truncated in table ... column 'Field_Key'"
 *
 * Kandidat yang melihatnya tidak melakukan kesalahan apa pun dan tidak bisa
 * berbuat apa-apa. Jadi batasnya ditegakkan di HULU, saat key dibuat.
 *
 * Angkanya 56, bukan 60: keyUnik() menambahkan akhiran '_2', '_3' ketika ada
 * key kembar, dan akhiran itu harus tetap muat. Tanpa sisa ruang ini, key
 * sepanjang tepat 60 karakter berubah jadi 62 begitu ada duplikat — dan itulah
 * yang terjadi pada 'dokumen_pendukung_e_g_sio_forklift_loader_boiler_excavator_s'.
 */
export const MAKS_PANJANG_KEY = 56;

export function slugKey(s) {
    const rapi = String(s || 'field')
        .trim()
        .toLowerCase()
        .replace(/[^a-z0-9_]+/g, '_')
        .replace(/^[0-9]+/g, '')
        .replace(/^_+|_+$/g, '');

    // Dipotong SESUDAH dirapikan, lalu garis bawah sisa di ujung dibuang -
    // pemotongan di tengah kata kerap menyisakan 'excavator_' yang menggantung.
    return rapi.slice(0, MAKS_PANJANG_KEY).replace(/_+$/g, '') || 'field';
}

export function buatFieldId() {
    if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
        return `fld_${crypto.randomUUID()}`;
    }
    return `fld_${Date.now()}_${Math.random().toString(36).slice(2, 10)}`;
}

function fallbackFieldId(key, langkahIndex, bagianIndex, fieldIndex) {
    return `fld_legacy_${langkahIndex + 1}_${bagianIndex + 1}_${fieldIndex + 1}_${slugKey(key)}`;
}

/**
 * @param {object} bersih field yang sudah disaring katalog
 * @param {object} mentah field asli — satu-satunya sumber properti lebar LEGACY
 *        (`lebar`, `width_percent`, `lebar_span`, `kolom`). Keempatnya sengaja
 *        tidak masuk katalog: mereka hanya perlu dibaca sekali saat memuat skema
 *        lama, lalu digantikan `lebar_persen` yang tersimpan sesudahnya.
 */
function normalisasiLebarPersen(bersih, mentah = bersih) {
    if (bersih.penuh || mentah.lebar === 'full') return 100;
    const dariPersen = Number(bersih.lebar_persen || mentah.width_percent || 0);
    if (dariPersen) return Math.min(100, Math.max(33, Math.round(dariPersen)));
    const span = Number(mentah.lebar_span || mentah.kolom || 0);
    if (span >= 3) return 100;
    if (span === 2) return 67;
    return 33;
}

export function skemaKosong() {
    return {
        schema_version: 1,
        template: 'TEMPLATE_1',
        layout: 'SATU_HALAMAN',
        langkah: [
            {
                kode: 'PENDAFTARAN',
                judul: 'Data Pendaftaran',
                ikon: 'bi-person-vcard',
                bagian: [
                    {
                        judul: 'Data Diri',
                        field: [
                            {
                                field_id: buatFieldId(),
                                key: 'nama_lengkap',
                                label: 'Nama Lengkap',
                                tipe: 'text',
                                wajib: true,
                                penuh: false,
                                lebar_persen: 33,
                            },
                            {
                                field_id: buatFieldId(),
                                key: 'email',
                                label: 'Email',
                                tipe: 'email',
                                wajib: true,
                                penuh: false,
                                lebar_persen: 33,
                            },
                        ],
                    },
                ],
            },
        ],
    };
}

/**
 * Validasi skema sebelum disimpan/dipublish.
 *
 * @param {object} skema
 * @param {object} [opsi]
 * @param {string[]} [opsi.kunciPrefill] daftar kunci isi-otomatis yang tersedia
 *        untuk konteks formulir ini. Bila tidak diberikan, pemeriksaan kunci
 *        DILEWATI — skema bawaan di registry tidak tahu konteks pemakaiannya,
 *        dan menolaknya di sana hanya akan memblokir uji tanpa alasan.
 * @returns {{ ok: boolean, errors: string[], peringatan: string[], skema: object }}
 */
export function validasiSkema(skema, opsi = {}) {
    const s = normalisasiSkema(skema);
    const errors = [];
    const peringatan = [];
    if (!s.langkah.length) errors.push('Minimal harus ada satu langkah.');

    const posisi = new Map();
    let urut = 0;
    s.langkah.forEach((L) => L.bagian.forEach((B) => B.field.forEach((F) => {
        if (F.key && !posisi.has(F.key)) posisi.set(F.key, urut);
        urut++;
    })));

    const kunciPrefill = Array.isArray(opsi.kunciPrefill) ? new Set(opsi.kunciPrefill) : null;

    const keys = new Set();
    const ids = new Set();
    s.langkah.forEach((L, li) => {
        if (!L.bagian.length) errors.push(`Langkah ${li + 1} belum punya section.`);
        L.bagian.forEach((B, bi) => {
            if (!B.field.length) errors.push(`Section ${bi + 1} pada langkah ${li + 1} belum punya field.`);
            B.field.forEach((F) => {
                if (!F.key) errors.push(`Ada field tanpa key pada langkah ${li + 1}.`);
                if (!/^[a-z][a-z0-9_]*$/.test(F.key))
                    errors.push(`Key "${F.key}" hanya boleh huruf kecil, angka, dan underscore; harus diawali huruf.`);
                if (keys.has(F.key)) errors.push(`Key "${F.key}" dipakai lebih dari sekali.`);
                keys.add(F.key);
                if (!F.field_id) errors.push(`Field "${F.label}" belum punya ID sistem.`);
                if (ids.has(F.field_id)) errors.push(`ID sistem field "${F.label}" terduplikasi.`);
                ids.add(F.field_id);

                const gTipe = galatTipe(F);
                if (gTipe) errors.push(`Field "${F.label}": ${gTipe}`);

                errors.push(...galatRujukan(F, posisi));

                if (kunciPrefill && F.prefill && !kunciPrefill.has(F.prefill)) {
                    errors.push(
                        `Field "${F.label}" mengisi otomatis dari "${F.prefill}", yang tidak tersedia untuk konteks formulir ini.`,
                    );
                }

                // Peringatan, bukan galat: formulirnya tetap sah. Yang hilang
                // hanya pengenalannya sebagai foto verifikasi oleh LamaranService,
                // halaman Pelamar, dan Detail Lamaran — ketiganya mencari key
                // `foto_verifikasi` secara harfiah.
                if (F.tipe === 'foto' && F.key !== 'foto_verifikasi') {
                    peringatan.push(
                        `Field "${F.label}" memakai key "${F.key}". Foto hanya dikenali sebagai foto verifikasi identitas bila key-nya "foto_verifikasi".`,
                    );
                }
            });
        });
    });

    return { ok: errors.length === 0, errors, peringatan, skema: s };
}

/**
 * Rujukan ke field lain: tampil_jika, beda_dengan, reset_anak, bergantung, saring.
 *
 * Dua kesalahan yang dijaring:
 *
 *   1. Menunjuk key yang tidak ada. Syaratnya diam-diam tidak pernah terpenuhi,
 *      dan field-nya tidak pernah muncul — tanpa pesan galat apa pun.
 *   2. `tampil_jika` menunjuk field yang letaknya SESUDAH field ini. Pada layout
 *      bertahap acuannya berada di langkah yang belum dibuka saat field ini
 *      dinilai, jadi syaratnya mustahil terpenuhi. `lebar_jika` sengaja TIDAK
 *      ikut aturan ini: pemakaian utamanya justru mengacu dirinya sendiri —
 *      sebuah field menyusut ketika jawabannya sendiri memunculkan field
 *      pendamping di sebelahnya.
 */
function galatRujukan(F, posisi) {
    const out = [];
    const ada = (key, dari) => {
        if (!key) return;
        if (!posisi.has(key)) {
            out.push(`Field "${F.label}" (${dari}) menunjuk key "${key}" yang tidak ada di formulir ini.`);
        }
    };

    const acuan = F.tampil_jika?.field;
    if (acuan) {
        if (!posisi.has(acuan)) {
            out.push(`Field "${F.label}" (tampil jika) menunjuk key "${acuan}" yang tidak ada di formulir ini.`);
        } else if (posisi.get(acuan) >= posisi.get(F.key)) {
            out.push(
                `Field "${F.label}" bersyarat pada "${acuan}", yang letaknya sesudah field ini — syaratnya tidak akan pernah terpenuhi.`,
            );
        }
    }

    ada(F.beda_dengan, 'beda dengan');
    (Array.isArray(F.reset_anak) ? F.reset_anak : []).forEach((k) => ada(k, 'reset anak'));
    Object.values(F.bergantung || {}).forEach((k) => ada(k, 'bergantung'));
    Object.values(F.saring || {}).forEach((k) => ada(k, 'saring'));

    return out;
}
