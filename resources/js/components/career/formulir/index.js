/**
 * WEB CAREER — Katalog formulir.
 *
 * TAMPILAN dan PERTANYAAN dipisah ke dua pohon. Yang digambar tinggal di
 * components/; yang dijawab — skema, aturan, validasi — tinggal di utils/.
 *
 *   components/career/formulir/
 *     index.js                 <- berkas ini
 *     DynamicForm.vue            formulir yang dirakit dari skema database
 *     inti/
 *       FieldRenderer.vue        render satu field sesuai tipenya
 *       BagianRenderer.vue       render satu bagian (biasa / berulang)
 *     template-1/                satu keluarga tampilan
 *       layout/SatuHalaman.vue   semua pertanyaan dalam satu layar
 *       layout/Bertahap.vue      stepper, validasi per langkah
 *       form-1/Form1.vue         pendaftaran MT
 *       form-2/Form2.vue         identitas peserta MT (lanjutan)
 *       form-3/Form3.vue         pendaftaran rekrutmen umum
 *       form-4/Form4.vue         pendaftaran magang
 *
 *   utils/formulir/            (@utils/formulir/...)
 *     aturan.js                  syarat tampil, nilai awal, validasi
 *     blok.js                    blok pertanyaan siap pakai (data diri, pendidikan)
 *     katalogField.js            tipe field & properti yang berlaku untuknya
 *     schema.js                  bentuk baku skema formulir
 *     referensi.js               pengambil opsi dari server untuk tipe `referensi`
 *     berkasBaris.js  drafLokal.js
 *     template-1/form-1.js .. form-4.js   PERTANYAAN & SYARAT tiap formulir
 *
 * ── KENAPA index.js TETAP DI SINI, BUKAN IKUT KE utils/ ─────────────────────
 *
 * Berkas ini bukan utilitas: ia mengimpor lima komponen .vue dan memetakan kode
 * database ke masing-masing. Memindahkannya ke utils/ akan melahirkan
 * "utilitas" yang bergantung pada folder komponen — arah ketergantungan yang
 * terbalik, dan lebih membingungkan daripada satu .js yang duduk di sini
 * sebagai pintu masuk foldernya sendiri.
 *
 * Database hanya menyimpan KODE (Master_Formulir.Komponen_Kode). Peta di
 * bawah yang menerjemahkannya jadi komponen — jadi tidak ada "if nama
 * formulir" di mana pun.
 *
 * MENAMBAH FORMULIR BARU
 *   - Masih mirip yang sudah ada -> buat utils/formulir/template-1/form-5.js
 *                                    + components/.../template-1/form-5/Form5.vue
 *   - Tampilannya beda jauh      -> buat template-2/ dengan layout sendiri
 *   Lalu daftarkan satu baris di FORMULIR, dan INSERT satu baris di
 *   N_WEB_CAREERS_Master_Formulir. Tidak ada ALTER TABLE — jawaban kandidat
 *   tersimpan sebagai JSON.
 */
import Form1 from './template-1/form-1/Form1.vue';
import Form2 from './template-1/form-2/Form2.vue';
import Form3 from './template-1/form-3/Form3.vue';
import Form4 from './template-1/form-4/Form4.vue';
import DynamicForm from './DynamicForm.vue';
import { SKEMA as SKEMA_FORM_1 } from '@utils/formulir/template-1/form-1';
import { SKEMA as SKEMA_FORM_2 } from '@utils/formulir/template-1/form-2';
import { SKEMA as SKEMA_FORM_3 } from '@utils/formulir/template-1/form-3';
import { SKEMA as SKEMA_FORM_4 } from '@utils/formulir/template-1/form-4';
import { kunciBagian, semuaField } from '@utils/formulir/aturan';

export const FORMULIR = {
    FORMULIR_1: {
        komponen: Form1,
        skema: SKEMA_FORM_1,
        nama: 'Form 1 — Pendaftaran MT',
        keterangan: 'Satu halaman. Jenjang, institusi, dan jurusan dipilih berantai dari master pendidikan.',
        template: 'Template 1',
        layout: 'Satu halaman',
        berkas: '@utils/formulir/template-1/form-1.js',
    },
    FORMULIR_2: {
        komponen: Form2,
        skema: SKEMA_FORM_2,
        nama: 'Form 2 — Identitas Peserta MT',
        keterangan: 'Empat langkah: validasi data, identitas tambahan, kesiapan & dokumen, pernyataan.',
        template: 'Template 1',
        layout: 'Bertahap',
        berkas: '@utils/formulir/template-1/form-2.js',
    },
    FORMULIR_3: {
        komponen: Form3,
        skema: SKEMA_FORM_3,
        nama: 'Form 3 — Pendaftaran Rekrutmen',
        keterangan: 'Satu halaman. Terbuka semua jenjang, menimbang pengalaman kerja & kesediaan.',
        template: 'Template 1',
        layout: 'Satu halaman',
        berkas: '@utils/formulir/template-1/form-3.js',
    },
    FORMULIR_4: {
        komponen: Form4,
        skema: SKEMA_FORM_4,
        nama: 'Form 4 — Pendaftaran Magang',
        keterangan: 'Tiga langkah: data & pendidikan, rencana magang, dokumen & pernyataan.',
        template: 'Template 1',
        layout: 'Bertahap',
        berkas: '@utils/formulir/template-1/form-4.js',
    },
};

// ── Pembantu ────────────────────────────────────────────────────────

export function komponenFormulir(kode) {
    return FORMULIR[kode]?.komponen || null;
}

export function skemaFormulir(kode) {
    return FORMULIR[kode]?.skema || { langkah: [] };
}

export function infoFormulir(kode) {
    return FORMULIR[kode] || null;
}

export function komponenDinamis() {
    return DynamicForm;
}

export function skemaDariFormulir(formulir) {
    if (formulir?.schema) return formulir.schema;
    if (formulir?.schemaJson) return formulir.schemaJson;
    return skemaFormulir(formulir?.komponen);
}

/**
 * Sumber skema untuk peta label/tipe/grup di bawah.
 *
 * Menerima DUA bentuk, dan itu memang perlu:
 *   • kode komponen ('FORMULIR_2') — formulir bawaan lama;
 *   • objek skema langsung — skema yang DIBEKUKAN saat kandidat mengirim
 *     formulirnya (Schema_Snapshot_Json).
 *
 * Formulir yang disusun lewat Master Formulir tidak punya kode komponen sama
 * sekali. Selama peta ini hanya menerima kode, seluruh formulir semacam itu
 * jatuh ke tebakan-dari-nama-kunci: `v_nama` terbaca "V Nama". Snapshot juga
 * yang membuat peninjau membaca pertanyaan versi YANG DIISI kandidat, bukan
 * versi yang kebetulan published hari ini.
 */
function skemaSumber(sumber) {
    if (! sumber) return null;
    if (typeof sumber === 'string') return FORMULIR[sumber]?.skema || null;
    if (Array.isArray(sumber.langkah)) return sumber;

    return sumber.schema || sumber.skema || null;
}

/**
 * Jalan-jalan seluruh field skema dalam URUTAN FORMULIRNYA
 * (langkah → bagian → field), sambil membawa konteks tiap field.
 *
 * Satu tempat untuk urutan itu, dipakai labelField/tipeField/grupField —
 * supaya "urutan menurut skema" tidak pernah bisa berbeda antar ketiganya.
 */
function telusuriField(skema, kunjungi) {
    let posisi = 0;
    (skema.langkah || []).forEach((L, li) => {
        (L.bagian || []).forEach((B) => {
            (B.field || []).forEach((f) => {
                if (! f.key) return;
                kunjungi(f, { langkah: L, bagian: B, indexLangkah: li, posisi: posisi++ });
            });
        });
    });
}

/**
 * Peta `key → label` seluruh field sebuah formulir, termasuk field di dalam
 * bagian berulang.
 *
 * KENAPA PERLU
 * Yang tersimpan di database hanyalah pasangan kunci→jawaban; labelnya hidup di
 * skema (berkas ini). Layar peninjau karena itu terpaksa MENEBAK label dari
 * nama kunci — `v_nama` jadi "V Nama", `v_wa` jadi "V Wa", `dok_cv` jadi
 * "Dok Cv". Itu bahasa mesin yang bocor ke mata orang.
 *
 * Dengan peta ini, yang terbaca adalah label yang benar-benar dilihat kandidat
 * saat mengisi — jadi peninjau dan pengisi membaca pertanyaan yang sama.
 *
 * Kunci yang tidak ada di skema (formulir versi lama, field yang sudah dihapus)
 * TIDAK dibuang — pemanggil tetap menampilkannya dengan tebakan dari kuncinya.
 * Menyembunyikannya berarti jawaban yang pernah diberikan kandidat lenyap dari
 * layar tanpa ada yang tahu.
 */
export function labelField(sumber) {
    const peta = {};
    const skema = skemaSumber(sumber);
    if (!skema) {
        return peta;
    }

    telusuriField(skema, (f) => {
        if (f.label) peta[f.key] = f.label;
    });

    return peta;
}

/**
 * Peta `key → tipe` seluruh field sebuah formulir — pasangan dari labelField().
 *
 * KENAPA PERLU
 * Yang tersimpan di database cuma kunci→jawaban, tanpa keterangan bentuknya.
 * Nominal gaji karena itu tampil di layar peninjau sebagai `9500000`: angka
 * telanjang yang harus dihitung digitnya sendiri untuk tahu ini sembilan juta
 * atau sembilan puluh juta. Dengan peta ini layar tahu field mana yang uang,
 * lalu menampilkannya sebagai "Rp 9.500.000" — sama seperti yang dilihat
 * kandidat saat mengetiknya.
 */
export function tipeField(sumber) {
    const peta = {};
    const skema = skemaSumber(sumber);
    if (!skema) {
        return peta;
    }

    telusuriField(skema, (f) => {
        if (f.tipe) peta[f.key] = f.tipe;
    });

    return peta;
}

/**
 * Peta `key → kelompok tampilan` — judul + ikon LANGKAH tempat field itu diisi.
 *
 * KENAPA PERLU
 * Yang tersimpan di database hanya pasangan kunci→jawaban, tanpa satu pun tanda
 * bahwa `v_nama`, `v_email`, dan `v_wa` sebenarnya SATU kelompok pertanyaan
 * ("Validasi Data") yang kandidat isi di satu layar. Tanpa itu, peninjau
 * disodori tiga puluh kotak label-nilai berderet tanpa jeda — dan yang hilang
 * bukan cuma kerapian: hubungan antar jawaban ikut hilang. "Kesesuaian Data:
 * Sesuai" hanya punya arti di sebelah nama, email, dan WA yang divalidasinya.
 *
 * Yang dipakai LANGKAH, bukan `bagian`: langkah punya judul DAN ikon, dan
 * batasnya sama dengan batas yang dilihat kandidat saat mengisi. `bagian` di
 * dalamnya kerap bernomor ("A. Validasi Data Peserta") — penomoran yang berguna
 * saat mengisi, tapi jadi derau saat membaca kembali.
 *
 * Kunci yang tak ada di skema tidak dipetakan; pemanggil menaruhnya di kelompok
 * terakhir apa adanya, bukan membuangnya (lihat catatan di labelField()).
 */
export function grupField(sumber) {
    const peta = {};
    const skema = skemaSumber(sumber);
    if (!skema) {
        return peta;
    }

    telusuriField(skema, (f, ctx) => {
        peta[f.key] = {
            judul: ctx.langkah.judul || ctx.bagian.judul || '',
            ikon: ctx.langkah.ikon || 'bi-list-ul',
            urutan: ctx.indexLangkah,
            // Nomor urut field di dalam SELURUH formulir. Inilah yang membuat
            // layar peninjau terbaca dari atas ke bawah persis seperti
            // formulirnya. Tanpa ini urutannya mengikuti urutan kunci di
            // Jawaban_Json — urutan penyimpanan, yang tidak pernah dijanjikan
            // sama dengan urutan pertanyaan.
            posisi: ctx.posisi,
        };
    });

    return peta;
}

/**
 * Sub-bagian berulang (riwayat kerja, sertifikasi) juga punya tempatnya sendiri
 * di formulir. Kuncinya adalah `bagian.key`, bukan `field.key`, jadi ia tak
 * pernah terjaring telusuriField() — dan tanpa peta ini seluruh riwayat
 * terlempar ke akhir daftar, terpisah dari kelompok tempat ia sebenarnya diisi.
 */
export function grupBagian(sumber) {
    const peta = {};
    const skema = skemaSumber(sumber);
    if (!skema) {
        return peta;
    }

    let posisi = 0;
    (skema.langkah || []).forEach((L, indexLangkah) => {
        (L.bagian || []).forEach((B) => {
            const awal = posisi;
            posisi += (B.field || []).length;
            if (! B.berulang) return;
            // kunciBagian(): `key` bila ada, selebihnya slug dari judulnya —
            // aturan yang sama dengan yang dipakai saat jawabannya DISIMPAN.
            // Menghitungnya ulang di sini akan mudah berselisih diam-diam.
            peta[kunciBagian(B)] = {
                judul: L.judul || B.judul || '',
                ikon: L.ikon || 'bi-list-ul',
                urutan: indexLangkah,
                posisi: awal,
                label: B.judul || '',
            };
        });
    });

    return peta;
}

/** Daftar untuk dropdown admin. */
export function daftarFormulir() {
    return Object.entries(FORMULIR).map(([kode, f]) => ({
        kode,
        nama: f.nama,
        keterangan: f.keterangan,
        template: f.template,
        layout: f.layout,
        berkas: f.berkas,
        jumlahLangkah: (f.skema.langkah || []).length,
        jumlahField: semuaField(f.skema).length,
    }));
}

// Diteruskan dari inti/ supaya pemakai cukup mengimpor dari satu tempat.
export {
    syaratTerpenuhi,
    fieldTampil,
    bagianTampil,
    semuaField,
    fieldDapatDisaring,
    nilaiKosong,
    kunciBagian,
    barisKosong,
    jawabanAwal,
    periksaLangkah,
} from '@utils/formulir/aturan';

export { normalisasiSkema, skemaKosong, validasiSkema, slugKey, buatFieldId, MAKS_PANJANG_KEY } from '@utils/formulir/schema';
