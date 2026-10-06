/**
 * WEB CAREER — Blok pertanyaan siap pakai.
 *
 * Blok pendidikan ditulis SEKALI di sini lalu dipanggil formulir MT, Rekrutmen,
 * dan Magang. Alasannya sederhana: aturan pendidikan (jenjang mana yang punya
 * IPK, jenjang mana yang punya fakultas) tidak boleh berbeda antar formulir.
 * Kalau disalin tiga kali, cepat atau lambat ketiganya berbeda.
 *
 * Isi bloknya sendiri tetap DATA — formulir bebas menambah/menimpa field
 * sesudahnya karena yang dikembalikan hanya array biasa.
 */

/** Jenjang perguruan tinggi. Dipakai memilih cabang pertanyaan, bukan validasi. */
export const JENJANG_PT = ['D1', 'D2', 'D3', 'D4', 'S1', 'Profesi', 'S2', 'S3'];

/** Jenjang sekolah menengah ke bawah. */
export const JENJANG_SEKOLAH = ['SD', 'SMP', 'SMA', 'SMK'];

const adaDi = (nilai) => ({ field: 'jenjang_pendidikan', operator: 'ADA_DI', nilai });

/**
 * Blok pendidikan bercabang.
 *
 * Kandidat memilih jenjang lebih dulu; itu yang menentukan sisanya:
 *   jenjang -> jenis institusi (lewat Jenis_Institusi_Jenjang)
 *           -> kampus/sekolah  (lewat Master_Kampus.Jenis_Institusi_Kode)
 *           -> jurusan         (lewat Prodi_Jenjang)
 *
 * Perguruan tinggi ditanya fakultas + IPK; sekolah menengah ditanya nilai
 * rata-rata rapor. Keduanya TIDAK disatukan jadi satu kolom "nilai" karena
 * skalanya beda (0–4 vs 0–100) — menggabungkannya membuat syarat auto-gugur
 * memberi hasil yang salah tanpa terlihat salah.
 *
 * @param {object} opsi
 * @param {boolean} opsi.wajibNilai       IPK / nilai rapor wajib diisi
 * @param {boolean} opsi.tanyaKemahasiswaan  tanya status mahasiswa & semester
 * @param {boolean} opsi.tanyaTahunLulus  tanya tahun lulus / perkiraan lulus
 * @param {string}  opsi.judulKampus      label kolom institusi
 */
export function blokPendidikan(opsi = {}) {
    const {
        wajibNilai = true,
        tanyaKemahasiswaan = true,
        tanyaTahunLulus = true,
        judulKampus = 'Nama Kampus / Sekolah',
    } = opsi;

    const field = [
        {
            key: 'jenjang_pendidikan',
            label: 'Jenjang Pendidikan',
            tipe: 'referensi',
            sumber: 'jenjang',
            wajib: true,
            ph: 'Pilih jenjang',
            bantuan: 'Menentukan pilihan institusi dan jurusan di bawah.',
            // Nilainya Kode ('S1', 'SMK') — sama persis dengan opsi statis lama,
            // jadi syarat auto-gugur yang sudah berjalan tetap kena.
            dapat_disaring: true,
            // Mengganti jenjang membatalkan seluruh rantai di bawahnya: daftar
            // institusi, kampus, dan jurusan semuanya disaring oleh jenjang.
            // Tanpa ini jawaban lama bertahan di layar, tidak lagi ada di daftar
            // pilihannya, dan tetap ikut terkirim saat disimpan.
            //
            // CATATAN: `reset_anak` baru ditangani layout SatuHalaman. Formulir
            // yang memakai layout Bertahap belum ikut mengosongkan anaknya —
            // blok ini dipakai Form 1 yang satu halaman, jadi berlaku di sana.
            reset_anak: ['jenis_institusi', 'nama_kampus', 'jurusan'],
        },
        {
            key: 'jenis_institusi',
            label: 'Jenis Institusi Pendidikan',
            tipe: 'referensi',
            sumber: 'jenis_institusi',
            wajib: true,
            ph: 'Pilih jenis institusi',
            bergantung: { jenjang: 'jenjang_pendidikan' },
            ph_terkunci: 'Pilih jenjang pendidikan dulu',
            dapat_disaring: true,
            reset_anak: ['nama_kampus'],
        },
        {
            key: 'nama_kampus',
            label: judulKampus,
            tipe: 'referensi',
            sumber: 'kampus',
            wajib: true,
            penuh: true,
            ph: 'Ketik minimal 2 huruf lalu pilih',
            bantuan: 'Daftar resmi Dapodik & PDDIKTI. Tidak ketemu? Hubungi admin.',
            bergantung: { jenjang: 'jenjang_pendidikan' },
            saring: { jenis: 'jenis_institusi' },
            ph_terkunci: 'Pilih jenjang pendidikan dulu',
            dapat_disaring: true,
        },
        {
            key: 'jurusan',
            label: 'Jurusan / Program Studi',
            tipe: 'referensi',
            sumber: 'prodi',
            wajib: true,
            ph: 'Ketik untuk mencari jurusan',
            bergantung: { jenjang: 'jenjang_pendidikan' },
            ph_terkunci: 'Pilih jenjang pendidikan dulu',
            dapat_disaring: true,
        },

        // ── Cabang perguruan tinggi ──
        {
            key: 'fakultas',
            label: 'Fakultas',
            tipe: 'text',
            wajib: false,
            ph: 'mis. Fakultas Teknik',
            bantuan: 'Kosongkan bila institusi Anda tidak memakai istilah fakultas.',
            tampil_jika: adaDi(JENJANG_PT),
        },
        {
            key: 'ipk',
            label: 'IPK',
            tipe: 'number',
            wajib: wajibNilai,
            min: 0,
            maks: 4,
            desimal: 2,
            ph: 'mis. 3.25',
            dapat_disaring: true,
            tampil_jika: adaDi(JENJANG_PT),
        },

        // ── Cabang sekolah menengah ke bawah ──
        {
            key: 'nilai_rata_rata',
            label: 'Nilai Rata-rata Rapor',
            tipe: 'number',
            wajib: wajibNilai,
            min: 0,
            maks: 100,
            desimal: 2,
            ph: 'mis. 85.5',
            bantuan: 'Skala 0–100, dari rapor semester terakhir.',
            dapat_disaring: true,
            tampil_jika: adaDi(JENJANG_SEKOLAH),
        },
    ];

    if (tanyaKemahasiswaan) {
        field.push(
            {
                key: 'status_kemahasiswaan',
                label: 'Status Pendidikan Saat Ini',
                tipe: 'select',
                wajib: true,
                opsi: ['Masih menempuh', 'Sudah Lulus'],
                dapat_disaring: true,
            },
            {
                key: 'semester',
                label: 'Semester / Kelas Saat Ini',
                tipe: 'number',
                wajib: true,
                min: 1,
                maks: 14,
                tampil_jika: { field: 'status_kemahasiswaan', operator: '=', nilai: 'Masih menempuh' },
            },
        );
    }

    if (tanyaTahunLulus) {
        field.push({
            key: 'tahun_lulus',
            label: 'Tahun Lulus / Perkiraan Lulus',
            tipe: 'number',
            wajib: true,
            min: 1980,
            maks: 2040,
            ph: 'mis. 2026',
        });
    }

    return field;
}

/** Blok data diri standar — sama di ketiga formulir gerbang. */
export function blokDataDiri(opsi = {}) {
    const { tanyaDomisili = false } = opsi;

    const field = [
        { key: 'nama_lengkap', label: 'Nama Lengkap Sesuai KTP', tipe: 'text', wajib: true, ph: 'Tulis persis seperti tertera di KTP' },
        // ── NIK DIAMBIL DARI AKUN, TIDAK DIKETIK ULANG ──────────────────────
        //
        // Pendaftaran akun sudah mewajibkan NIK tepat 16 digit dan menyimpannya
        // ke N_WEB_CAREERS_Users.NIK, jadi nilainya selalu ada dan sudah
        // tervalidasi. Menanyakannya lagi di sini hanya membuka satu cara untuk
        // salah: kandidat mengetik ulang dari ingatan, meleset beberapa digit,
        // dan lamarannya membawa NIK yang berbeda dari akun yang mengajukannya.
        // Dua nomor untuk satu orang, dan tidak ada yang tahu mana yang benar.
        //
        // `tipe: 'prefill'` membuatnya TERKUNCI di layar (FieldRenderer
        // menggambarnya disabled berlabel "otomatis"); `prefill: 'nik'` menunjuk
        // kunci profil yang dikirim server — kunci yang sama yang sudah dipakai
        // FRM-MT-KELENGKAPAN-DATA, bukan jalur baru.
        {
            key: 'nik',
            label: 'NIK (Nomor KTP)',
            tipe: 'prefill',
            prefill: 'nik',
            wajib: true,
            bantuan: 'Diambil dari akun Anda. Bila keliru, perbaiki lewat Profil — bukan di sini.',
        },
        { key: 'tanggal_lahir', label: 'Tanggal Lahir', tipe: 'date', wajib: true },
        {
            key: 'jenis_kelamin',
            label: 'Jenis Kelamin',
            tipe: 'select',
            wajib: true,
            opsi: ['Laki-Laki', 'Perempuan'],
            dapat_disaring: true,
        },
        {
            key: 'no_hp',
            label: 'No. Handphone Aktif (WA)',
            tipe: 'phone',
            wajib: true,
            ph: '628xxxxxxxxx',
            bantuan: 'Wajib berawalan 62. Ketik 08… otomatis jadi 628…',
        },
        { key: 'email', label: 'Email', tipe: 'text', wajib: true, ph: 'nama.lengkap@gmail.com' },
    ];

    if (tanyaDomisili) {
        field.push({
            key: 'kota_domisili',
            label: 'Kota Domisili Saat Ini',
            tipe: 'text',
            wajib: true,
            ph: 'mis. Palembang',
            dapat_disaring: true,
        });
    }

    return field;
}
