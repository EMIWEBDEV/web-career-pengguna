/**
 * WEB CAREER — daftar provinsi Indonesia beserta pulau/kepulauannya.
 *
 * Dipakai Master Lokasi Kerja supaya provinsi dipilih dari daftar, bukan diketik
 * bebas — mengetik bebas sudah terbukti melahirkan varian ejaan yang memecah
 * pengelompokan (lihat catatan EJAAN di bawah).
 *
 * Memilih provinsi otomatis mengisi Pulau, karena pemetaannya tetap dan tidak
 * ada gunanya meminta admin mengetiknya ulang.
 *
 * ── EJAAN ────────────────────────────────────────────────────────────────────
 * Provinsi Sumatra sengaja ditulis "Sumatra", BUKAN "Sumatera" yang lebih baku.
 * Alasannya data: seluruh baris N_HRIS_Master_Lokasi yang sudah ada memakai
 * "Sumatra" ("Sumatra Selatan", "Sumatra Utara"). Memasukkan "Sumatera" lewat
 * daftar ini akan membuat dua ejaan hidup berdampingan di satu tabel dan
 * memecah chip filter Pulau jadi dua kelompok.
 *
 * Tabel LAIN memakai konvensi berbeda — N_WEB_CAREERS_Master_Lokasi menulis
 * "Sumatera Selatan". Kalau daftar ini nanti dipakai di halaman itu juga,
 * ejaannya harus diseragamkan lebih dulu di kedua tabel, bukan ditambal di UI.
 *
 * 38 provinsi (termasuk pemekaran Papua 2022).
 */

export const PULAU = [
    'Sumatra',
    'Jawa',
    'Kalimantan',
    'Sulawesi',
    'Bali & Nusa Tenggara',
    'Maluku',
    'Papua',
];

export const PROVINSI = [
    { nama: 'Aceh', pulau: 'Sumatra' },
    { nama: 'Sumatra Utara', pulau: 'Sumatra' },
    { nama: 'Sumatra Barat', pulau: 'Sumatra' },
    { nama: 'Riau', pulau: 'Sumatra' },
    { nama: 'Kepulauan Riau', pulau: 'Sumatra' },
    { nama: 'Jambi', pulau: 'Sumatra' },
    { nama: 'Bengkulu', pulau: 'Sumatra' },
    { nama: 'Sumatra Selatan', pulau: 'Sumatra' },
    { nama: 'Kepulauan Bangka Belitung', pulau: 'Sumatra' },
    { nama: 'Lampung', pulau: 'Sumatra' },

    { nama: 'DKI Jakarta', pulau: 'Jawa' },
    { nama: 'Jawa Barat', pulau: 'Jawa' },
    { nama: 'Banten', pulau: 'Jawa' },
    { nama: 'Jawa Tengah', pulau: 'Jawa' },
    { nama: 'DI Yogyakarta', pulau: 'Jawa' },
    { nama: 'Jawa Timur', pulau: 'Jawa' },

    { nama: 'Kalimantan Barat', pulau: 'Kalimantan' },
    { nama: 'Kalimantan Tengah', pulau: 'Kalimantan' },
    { nama: 'Kalimantan Selatan', pulau: 'Kalimantan' },
    { nama: 'Kalimantan Timur', pulau: 'Kalimantan' },
    { nama: 'Kalimantan Utara', pulau: 'Kalimantan' },

    { nama: 'Sulawesi Utara', pulau: 'Sulawesi' },
    { nama: 'Gorontalo', pulau: 'Sulawesi' },
    { nama: 'Sulawesi Tengah', pulau: 'Sulawesi' },
    { nama: 'Sulawesi Barat', pulau: 'Sulawesi' },
    { nama: 'Sulawesi Selatan', pulau: 'Sulawesi' },
    { nama: 'Sulawesi Tenggara', pulau: 'Sulawesi' },

    { nama: 'Bali', pulau: 'Bali & Nusa Tenggara' },
    { nama: 'Nusa Tenggara Barat', pulau: 'Bali & Nusa Tenggara' },
    { nama: 'Nusa Tenggara Timur', pulau: 'Bali & Nusa Tenggara' },

    { nama: 'Maluku', pulau: 'Maluku' },
    { nama: 'Maluku Utara', pulau: 'Maluku' },

    { nama: 'Papua', pulau: 'Papua' },
    { nama: 'Papua Barat', pulau: 'Papua' },
    { nama: 'Papua Barat Daya', pulau: 'Papua' },
    { nama: 'Papua Selatan', pulau: 'Papua' },
    { nama: 'Papua Tengah', pulau: 'Papua' },
    { nama: 'Papua Pegunungan', pulau: 'Papua' },
];

/** Provinsi dikelompokkan per pulau — bentuk siap pakai untuk <el-option-group>. */
export const PROVINSI_PER_PULAU = PULAU.map((pulau) => ({
    pulau,
    daftar: PROVINSI.filter((p) => p.pulau === pulau),
}));

/**
 * Pulau untuk sebuah nama provinsi. Toleran terhadap beda ejaan Sumatra/Sumatera
 * dan beda huruf besar-kecil, supaya baris lama tetap dikenali.
 * Mengembalikan null bila tidak ada yang cocok — pemanggil yang memutuskan.
 */
export function pulauDariProvinsi(nama) {
    if (!nama) return null;
    const kunci = String(nama).trim().toLowerCase().replace('sumatera', 'sumatra');
    return PROVINSI.find((p) => p.nama.toLowerCase() === kunci)?.pulau ?? null;
}
