/**
 * WEB CAREER — Katalog tipe field.
 *
 * SATU tempat yang menjawab "tipe ini boleh punya properti apa". Sebelum ini
 * jawabannya tersebar di tiga daftar yang tidak pernah sinkron (TIPE_VALID di
 * schema.js, tipeField dan fieldPalette di masterFormulir.vue), sehingga
 * `currency`, `bulan`, `tahun`, dan `prefill` hidup di renderer tapi tidak
 * pernah bisa dipilih admin.
 *
 * Isi `properti` diturunkan dari apa yang BENAR-BENAR dibaca FieldRenderer.vue,
 * aturan.js, dan layout. Menawarkan kotak yang tidak dibaca siapa pun sama
 * buruknya dengan menyembunyikan kotak yang dibutuhkan: keduanya membuat admin
 * menebak.
 *
 * Janji itu kini dijaga MANUAL. Menambah properti di sini berarti memastikan
 * sendiri ada yang membacanya di ketiga berkas tersebut — dulu ada pemeriksaan
 * otomatis yang melakukannya, dan pemeriksaan itu sudah dihapus.
 *
 * Menambah tipe field baru = menambah satu entri di sini. Dropdown, palette,
 * inspector, normalisasi, dan validasi mengikutinya sendiri.
 */

/**
 * Properti yang berlaku untuk SEMUA tipe, jadi tidak diulang per entri.
 * `key`, `label`, `tipe`, dan `field_id` termasuk di sini karena tanpa keempatnya
 * sebuah field bukan field.
 */
export const PROPERTI_UNIVERSAL = [
    'field_id',
    'key',
    'label',
    'tipe',
    'wajib',
    'bantuan',
    'penuh',
    'lebar_persen',
    'lebar_jika',
    'tampil_jika',
    // Wajib bersyarat. Universal seperti `tampil_jika` karena pertanyaannya
    // sama untuk tipe apa pun: "kapan isian ini mengikat?"
    'wajib_jika',
    // Penanda bahwa key-nya DIKETIK SENDIRI oleh admin, bukan turunan label.
    // Tanpa ini key hasil ketikan tertimpa lagi pada ketukan berikutnya di
    // kotak Label — lihat sinkronKey() di masterFormulir.vue.
    'key_manual',
];

/**
 * Format yang DITERIMA SERVER untuk berkas kandidat. Cermin
 * BerkasFormulir::EKSTENSI_SERVER di PHP — skema boleh mempersempit daftar
 * ini, tidak boleh memperluasnya.
 */
export const FORMAT_BERKAS_SERVER = ['.pdf', '.jpg', '.jpeg', '.png', 'application/pdf', 'image/jpeg', 'image/png'];

/** Batas ukuran tertinggi — cermin BerkasFormulir::MAKS_MB_KERAS. */
export const MAKS_MB_BERKAS = 20;

/**
 * Format berkas yang diminta skema HARUS bisa diterima server.
 *
 * Browser menuruti `accept` apa adanya, server tidak: isian yang meminta .docx
 * membiarkan kandidat memilih berkas Word, lalu server menolaknya — di formulir
 * tahap penolakan itu dulu cuma notifikasi sekilas sementara nama berkasnya
 * tetap tercatat, dan CV-nya tidak pernah ada.
 */
function periksaBerkas(f) {
    if (!f.accept) return 'Format berkas yang diterima belum diatur.';

    const asing = String(f.accept)
        .split(',')
        .map((x) => x.trim().toLowerCase())
        .filter((x) => x && !FORMAT_BERKAS_SERVER.includes(x));
    if (asing.length) {
        return `Format ${asing.join(', ')} belum diterima server — pakai PDF, JPG, atau PNG.`;
    }

    if (Number(f.maks_mb) > MAKS_MB_BERKAS) return `Ukuran maksimal berkas ${MAKS_MB_BERKAS} MB.`;

    return null;
}

/** Batas bawah > batas atas: satu pemeriksa dipakai number dan currency. */
function periksaRentang(f) {
    const min = Number(f.min);
    const maks = Number(f.maks);
    if (Number.isFinite(min) && Number.isFinite(maks) && min > maks) {
        return 'Nilai minimum tidak boleh lebih besar dari maksimum.';
    }
    return null;
}

/**
 * Pilihan wajib punya minimal dua opsi — satu opsi bukan pilihan, itu pernyataan.
 * KECUALI opsinya memang datang dari konteks (`sumber_opsi`), yang isinya baru
 * ada saat formulir dirender.
 */
function periksaOpsi(f) {
    if (f.sumber_opsi) return null;
    const jumlah = Array.isArray(f.opsi) ? f.opsi.filter(Boolean).length : 0;
    return jumlah >= 2 ? null : 'Minimal harus ada 2 opsi pilihan.';
}

export const KATALOG_FIELD = {
    text: {
        label: 'Teks Ringkas',
        ikon: 'bi-input-cursor-text',
        properti: ['ph', 'maks_panjang', 'hanya_angka', 'beda_dengan', 'prefill', 'dapat_disaring'],
    },
    textarea: {
        label: 'Paragraf / Deskripsi',
        ikon: 'bi-textarea-t',
        // Tanpa `dapat_disaring`: teks bebas tidak bisa jadi syarat auto-gugur
        // yang bermakna — tidak ada dua jawaban yang persis sama.
        properti: ['ph', 'maks_panjang'],
        bawaan: { lebar_persen: 67 },
    },
    number: {
        label: 'Angka',
        ikon: 'bi-123',
        properti: ['ph', 'min', 'maks', 'desimal', 'prefill', 'dapat_disaring'],
        periksa: periksaRentang,
    },
    currency: {
        label: 'Nominal Rupiah',
        ikon: 'bi-cash-coin',
        // Renderernya el-input biasa berformat Rupiah, bukan el-input-number,
        // jadi min/maks tidak punya pembaca. Batasi lewat syarat auto-gugur.
        properti: ['ph', 'dapat_disaring'],
    },
    date: {
        label: 'Tanggal',
        ikon: 'bi-calendar-event',
        properti: ['ph', 'prefill', 'dapat_disaring'],
    },
    bulan: {
        label: 'Bulan',
        ikon: 'bi-calendar-month',
        properti: ['ph', 'prefill', 'dapat_disaring'],
    },
    tahun: {
        label: 'Tahun',
        ikon: 'bi-calendar4',
        // el-date-picker type="year" tidak membaca min/maks. Untuk tahun yang
        // perlu dibatasi, pakai tipe Angka yang memang punya keduanya.
        properti: ['ph', 'prefill', 'dapat_disaring'],
    },
    select: {
        label: 'Dropdown Pilihan',
        ikon: 'bi-menu-button-wide',
        properti: ['ph', 'opsi', 'sumber_opsi', 'bebas_ketik', 'reset_anak', 'prefill', 'dapat_disaring'],
        bawaan: { opsi: ['Opsi 1', 'Opsi 2'] },
        periksa: periksaOpsi,
    },
    radio: {
        // Tanpa `ph`: el-radio-group tidak punya placeholder.
        label: 'Radio Button',
        ikon: 'bi-record-circle',
        properti: ['opsi', 'prefill', 'dapat_disaring'],
        bawaan: { opsi: ['Ya', 'Tidak'] },
        periksa: periksaOpsi,
    },
    checkbox: {
        label: 'Checkbox (Pilihan Ganda)',
        ikon: 'bi-check2-square',
        properti: ['opsi', 'dapat_disaring'],
        bawaan: { opsi: ['Opsi 1', 'Opsi 2'] },
        periksa: periksaOpsi,
    },
    file: {
        label: 'Upload Berkas',
        ikon: 'bi-paperclip',
        properti: ['accept', 'maks_mb'],
        bawaan: { accept: '.pdf', maks_mb: 5, penuh: true, lebar_persen: 100 },
        periksa: periksaBerkas,
    },
    daftar: {
        // ── KENAPA TIPE SENDIRI, BUKAN TEXTAREA ─────────────────────────────
        //
        // "Sebutkan minimal 5 hal" selama ini dipakai sebagai textarea, dan
        // jawabannya jadi "ga,ga,ga,ga,ga" — lima kata yang memenuhi hitungan
        // tapi tidak menjawab apa pun. Yang bisa dihitung mesin cuma koma, dan
        // koma bukan gagasan.
        //
        // Dengan butir terpisah, jumlahnya jadi hal yang dijaga sistem, dan
        // setiap butir punya kolomnya sendiri — kandidat melihat ada lima kotak
        // kosong yang menunggu, bukan satu kotak yang bisa diisi apa saja.
        label: 'Daftar Bernomor',
        ikon: 'bi-list-ol',
        properti: ['ph', 'min_butir', 'maks_butir'],
        bawaan: { min_butir: 3, penuh: true, lebar_persen: 100 },
        periksa: (f) => {
            const min = Number(f.min_butir);
            const maks = Number(f.maks_butir);
            if (Number.isFinite(min) && min < 1) return 'Minimal jawaban setidaknya 1.';
            if (Number.isFinite(min) && Number.isFinite(maks) && min > maks) {
                return 'Minimal jawaban tidak boleh lebih besar dari maksimal.';
            }
            return null;
        },
    },
    foto: {
        // Dirender AmbilFoto.vue, yang tidak membaca ph/accept/maks_mb.
        label: 'Foto Verifikasi (Kamera)',
        ikon: 'bi-person-bounding-box',
        properti: [],
        bawaan: { penuh: true, lebar_persen: 100 },
    },
    phone: {
        label: 'Nomor Telepon',
        ikon: 'bi-telephone',
        properti: ['ph', 'beda_dengan', 'prefill', 'dapat_disaring'],
    },
    email: {
        label: 'Email',
        ikon: 'bi-envelope',
        properti: ['ph', 'prefill'],
    },
    consent: {
        // `label` ITU pernyataan persetujuannya — tidak ada kotak isian terpisah.
        label: 'Persetujuan / Declaration',
        ikon: 'bi-shield-check',
        properti: [],
        bawaan: { penuh: true, lebar_persen: 100 },
    },
    referensi: {
        label: 'Referensi Master Data',
        ikon: 'bi-database',
        properti: [
            'sumber',
            'bergantung',
            'saring',
            'ph',
            'ph_terkunci',
            'bebas_ketik',
            'reset_anak',
            'prefill',
            'dapat_disaring',
        ],
        periksa: (f) => (f.sumber ? null : 'Sumber master data referensi belum dipilih.'),
    },
    prefill: {
        label: 'Isi Otomatis (Terkunci)',
        ikon: 'bi-magic',
        properti: ['prefill', 'tipe_buka', 'buka_jika'],
        periksa: (f) => (f.prefill ? null : 'Sumber isi otomatis belum dipilih.'),
    },
};

export const TIPE_VALID = new Set(Object.keys(KATALOG_FIELD));

export function propertiTipe(tipe) {
    return KATALOG_FIELD[tipe]?.properti || [];
}

export function bolehPunya(tipe, prop) {
    return PROPERTI_UNIVERSAL.includes(prop) || propertiTipe(tipe).includes(prop);
}

/** Salinan dangkal-dalam sederhana: nilai bawaan hanya berisi skalar & array skalar. */
export function bawaanTipe(tipe) {
    const bawaan = KATALOG_FIELD[tipe]?.bawaan || {};
    return Object.fromEntries(Object.entries(bawaan).map(([k, v]) => [k, Array.isArray(v) ? [...v] : v]));
}

/**
 * Field baru yang hanya memuat properti sah bagi tipenya, plus bawaan yang
 * belum terisi.
 *
 * MEMBUANG, bukan sekadar menambah — itu bedanya dengan rapikanField() lama.
 * Properti sisa dari tipe sebelumnya (opsi pada field yang sudah jadi teks,
 * accept pada field yang sudah bukan berkas) selama ini ikut tersimpan ke
 * Schema_Json dan tidak pernah hilang, karena normalisasi meneruskan `...F`
 * apa adanya.
 */
export function bersihkanField(field) {
    const f = field && typeof field === 'object' ? field : {};
    const tipe = String(f.tipe || 'text').toLowerCase();
    const out = {};

    Object.entries(f).forEach(([k, v]) => {
        if (bolehPunya(tipe, k)) out[k] = v;
    });

    Object.entries(bawaanTipe(tipe)).forEach(([k, v]) => {
        if (out[k] === undefined || out[k] === null || out[k] === '') out[k] = v;
    });

    return out;
}

export function galatTipe(field) {
    const periksa = KATALOG_FIELD[field?.tipe]?.periksa;
    return periksa ? periksa(field) : null;
}
