/**
 * TANGGAL YANG DIKETIK, BUKAN DIKLIK.
 *
 * Kandidat mengetik `02/05/2026`; yang tersimpan tetap `2026-05-02` — sama
 * persis dengan yang dihasilkan el-date-picker (`value-format="YYYY-MM-DD"`).
 * Penyimpanannya tidak berubah sedikit pun, jadi seluruh laporan, penyaring,
 * dan perhitungan usia yang sudah ada tetap membaca hal yang sama.
 *
 * ── KENAPA DIKETIK ──────────────────────────────────────────────────────────
 *
 * Kalender bagus untuk tanggal yang DICARI ("Senin depan itu tanggal berapa?").
 * Tanggal lahir tidak dicari — ia sudah diingat. Memilihnya lewat kalender
 * menuntut orang menggulir puluhan tahun ke belakang hanya untuk memasukkan
 * angka yang sejak awal ada di kepalanya.
 *
 * ── KENAPA dd/mm/yyyy ───────────────────────────────────────────────────────
 *
 * Itu urutan yang dipakai KTP dan yang diucapkan orang Indonesia. Menampilkan
 * yyyy-mm-dd di layar akan membuat sebagian mengetik terbalik, dan kesalahannya
 * tidak terlihat: `02/05` dan `05/02` sama-sama tanggal yang sah.
 */

/** Ambil digit saja, potong 8 — ddmmyyyy. */
function digit(teks) {
    return String(teks ?? '').replace(/\D/g, '').slice(0, 8);
}

/**
 * Sisipkan garis miring SAAT MENGETIK.
 *
 * Dipanggil tiap ketukan. Garis miringnya ditambahkan sistem, bukan diketik
 * orang — mengetiknya sendiri akan menghasilkan `2//5/26` begitu jarinya
 * meleset, dan tidak ada yang menahannya.
 */
export function masker(teks) {
    const mentah = String(teks ?? '');

    // ── PEMISAH YANG DIKETIK SENDIRI ────────────────────────────────────────
    //
    // Orang mengetik "2/5/1998", bukan "02051998" — itu cara paling wajar
    // menyebut tanggal, dan menempelkannya dari tempat lain pun berbentuk
    // begitu. Tanpa cabang ini digitnya dirapatkan jadi "251998" lalu dipotong
    // per dua menjadi "25/19/98": tanggal yang sama sekali berbeda, tanpa satu
    // pun tanda bahwa ada yang salah.
    //
    // Jadi begitu ada pemisah, potongannya dihormati — hari dan bulan
    // dilengkapi nol di depan, bukan digeser.
    let d;

    if (/[/\-. ]/.test(mentah)) {
        const bagian = mentah.split(/[/\-. ]+/);
        const hari = (bagian[0] ?? '').replace(/\D/g, '');
        const bulan = (bagian[1] ?? '').replace(/\D/g, '');
        const tahun = (bagian[2] ?? '').replace(/\D/g, '');

        // Nol depan hanya untuk potongan yang SUDAH DITINGGALKAN — yang di
        // belakangnya sudah ada potongan lain. Memasangnya pada potongan yang
        // sedang diketik akan mengubah "1" jadi "01" tepat saat orang hendak
        // mengetik "12".
        const h = bagian.length > 1 && hari.length === 1 ? `0${hari}` : hari;
        const b = bagian.length > 2 && bulan.length === 1 ? `0${bulan}` : bulan;

        // DIGABUNG JADI SATU DERET, bukan dipotong per potongan.
        //
        // Ini yang dulu salah: tiap potongan dipangkas ke lebarnya sendiri, jadi
        // digit ke-3 yang diketik pada bagian bulan ("05/051") dibuang begitu
        // saja — kolomnya berhenti menerima ketikan tanpa sebab yang terlihat.
        // Digabung lebih dulu, luapannya mengalir sendiri ke bagian berikutnya.
        d = (h + b + tahun).slice(0, 8);
    } else {
        d = digit(mentah);
    }

    if (d.length <= 2) return d;
    if (d.length <= 4) return `${d.slice(0, 2)}/${d.slice(2)}`;

    return `${d.slice(0, 2)}/${d.slice(2, 4)}/${d.slice(4)}`;
}

/** '2026-05-02' -> '02/05/2026'. Kosong/tak dikenal -> ''. */
export function keTampilan(iso) {
    const m = /^(\d{4})-(\d{2})-(\d{2})/.exec(String(iso ?? ''));

    return m ? `${m[3]}/${m[2]}/${m[1]}` : '';
}

/**
 * '02/05/2026' -> '2026-05-02'. Null bila belum lengkap ATAU bukan tanggal nyata.
 *
 * Kelengkapan dan kebenaran sengaja dibedakan oleh pemanggilnya: yang pertama
 * berarti "masih mengetik", yang kedua "salah". Menyamakan keduanya akan
 * memerahkan kolom sejak ketukan pertama.
 */
export function keIso(teks) {
    const d = digit(teks);
    if (d.length !== 8) {
        return null;
    }

    const hari = +d.slice(0, 2);
    const bulan = +d.slice(2, 4);
    const tahun = +d.slice(4);

    // Diperiksa lewat Date, bukan lewat batas 1-31: 31 April dan 30 Februari
    // lolos pemeriksaan batas, dan keduanya bukan tanggal. Date menormalkan
    // tanggal yang mustahil ke bulan berikutnya — jadi bila hasilnya tidak
    // sama dengan yang dimasukkan, yang dimasukkan memang tidak ada.
    const t = new Date(tahun, bulan - 1, hari);
    if (t.getFullYear() !== tahun || t.getMonth() !== bulan - 1 || t.getDate() !== hari) {
        return null;
    }

    const dua = (n) => String(n).padStart(2, '0');

    // Tahun ikut dipadkan jadi empat digit. Tanpa ini tahun 998 menghasilkan
    // '998-05-02', dan pembacaan tahun di galat() (`+iso.slice(0, 4)`) memungut
    // '998-' yang bernilai NaN -- sementara `NaN < 1900` bernilai false, jadi
    // penahan tahun terlalu lampau itu lolos begitu saja. Bentuk keluarannya
    // tetap 'YYYY-MM-DD' seperti el-date-picker.
    return `${String(tahun).padStart(4, '0')}-${dua(bulan)}-${dua(hari)}`;
}

/** Sudah 8 digit? Dipakai membedakan "masih mengetik" dari "salah". */
export function lengkap(teks) {
    return digit(teks).length === 8;
}

/**
 * ARAH WAKTU YANG WAJAR UNTUK SEBUAH PERTANYAAN.
 *
 * Dipakai ketika skema tidak menyebutkan arahnya sendiri. Tanpa ini, seluruh
 * kolom tanggal memakai aturan tanggal lahir — dan pertanyaan yang jawabannya
 * MEMANG di masa depan ("Tanggal Dapat Mulai Bekerja", yang praktis selalu
 * sebulan ke depan karena one month notice) dimerahi tepat ketika kandidat
 * menjawabnya dengan benar.
 *
 * Pesan merah yang menyalahkan jawaban yang benar lebih buruk daripada tidak
 * ada pemeriksaan sama sekali: ia melatih orang mengabaikan warna merah, dan
 * pemeriksaan yang benar-benar penting ikut terabaikan.
 *
 * Yang dibaca LABELNYA, bukan kuncinya: kunci dibuat admin dan bisa berbunyi
 * apa saja (`tgl_1`, `field_7`), sementara label adalah kalimat yang dibaca
 * kandidat — dan itulah yang menyatakan maksud pertanyaannya.
 */
const KATA_DEPAN = [
    'mulai bekerja', 'mulai kerja', 'dapat mulai', 'siap mulai', 'kesiapan',
    'bergabung', 'onboarding', 'masuk kerja', 'efektif',
    'rencana', 'target', 'jadwal', 'tenggat', 'batas',
    'kedaluwarsa', 'kadaluarsa', 'berlaku sampai', 'masa berlaku', 'expired',
    'wawancara', 'tes', 'seleksi', 'janji temu',
];

const KATA_BELAKANG = [
    'lahir', 'kelahiran',
    'terbit', 'diterbitkan', 'dikeluarkan', 'penerbitan',
    'lulus', 'kelulusan', 'wisuda', 'ijazah',
    'masuk sekolah', 'mulai sekolah',
    'resign', 'berhenti', 'keluar dari',
];

/**
 * Arah waktu sebuah kolom: true bila jawabannya tidak boleh melewati hari ini.
 *
 * Urutannya disengaja — yang eksplisit selalu menang atas tebakan:
 *
 *   1. `maks_hari_ini` di skema, bila perancang formulir menyebutkannya;
 *   2. label yang jelas menyebut masa depan  — boleh ke depan;
 *   3. label yang jelas menyebut masa lalu   — tidak boleh ke depan;
 *   4. tidak ada petunjuk sama sekali        — TIDAK dibatasi.
 *
 * Bawaan terakhir itu longgar dengan sengaja. Menolak tanggal yang sebenarnya
 * sah adalah kesalahan yang menghentikan pekerjaan orang; menerima salah ketik
 * tahun sesekali adalah kesalahan yang masih bisa diperbaiki verifikator.
 * Ketika ragu, yang lebih murah adalah tidak menghalangi.
 */
export function batasHariIni(field) {
    if (!field) {
        return false;
    }

    if (typeof field.maks_hari_ini === 'boolean') {
        return field.maks_hari_ini;
    }

    const teks = `${field.label ?? ''} ${field.ph ?? ''}`.toLowerCase();

    if (KATA_DEPAN.some((k) => teks.includes(k))) {
        return false;
    }

    return KATA_BELAKANG.some((k) => teks.includes(k));
}

/**
 * Pesan galat, atau null bila tidak apa-apa.
 *
 * Batas bawahnya tetap tahun 1900 untuk semua kolom — tahun tiga digit selalu
 * salah ketik, apa pun pertanyaannya.
 *
 * Batas ATASNYA menyesuaikan arah pertanyaan; lihat batasHariIni(). Dulu ia
 * selalu menyala, karena penulis pertamanya hanya memikirkan tanggal lahir:
 * di sana tahun di masa depan hampir selalu salah ketik (2026 jadi 2062) dan
 * pantas ditahan sebelum masuk berkas resmi. Alasan itu tidak berlaku untuk
 * tanggal yang memang belum terjadi.
 */
export function galat(teks, { maksHariIni = false } = {}) {
    if (!teks) {
        return null;
    }
    if (!lengkap(teks)) {
        return 'Lengkapi tanggalnya — tulis hari/bulan/tahun, mis. 02/05/1998.';
    }

    const iso = keIso(teks);
    if (!iso) {
        return 'Tanggal itu tidak ada. Periksa lagi hari dan bulannya.';
    }

    const tahun = +iso.slice(0, 4);
    if (tahun < 1900) {
        return 'Tahunnya terlalu jauh ke belakang — periksa lagi.';
    }
    if (maksHariIni && iso > hariIniIso()) {
        return 'Tanggalnya belum terjadi. Periksa lagi tahunnya.';
    }

    return null;
}

/**
 * Hari ini sebagai YYYY-MM-DD menurut penanggalan LOKAL.
 *
 * Bukan toISOString(): di WIB (UTC+7) setiap saat sebelum pukul 07.00 masih
 * terhitung kemarin dalam UTC, dan tanggal lahir "hari ini" akan ditolak
 * sebagai masa depan — kesalahan yang hanya muncul pagi hari.
 */
function hariIniIso() {
    const d = new Date();
    const dua = (n) => String(n).padStart(2, '0');

    return `${d.getFullYear()}-${dua(d.getMonth() + 1)}-${dua(d.getDate())}`;
}
