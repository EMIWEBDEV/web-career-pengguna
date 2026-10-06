// WEB CAREER — Helper halaman Monitoring Rekrutmen.

const BULAN = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des']

/** 'Y-m-d H:i' (dari server) → '12 Jul 2026 14:03'. */
export function formatTanggal(s) {
    if (!s) return '—'
    const m = String(s).match(/^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2}))?/)
    if (!m) return s
    const t = `${parseInt(m[3], 10)} ${BULAN[parseInt(m[2], 10) - 1]} ${m[1]}`
    return m[4] ? `${t} ${m[4]}:${m[5]}` : t
}

/** Umur hari → label singkat. */
export function formatAging(h) {
    if (h === null || h === undefined) return ''
    if (h <= 0) return 'hari ini'
    return `${h} hari`
}

/** tone badge (dari PipelineProgress::badge) → kelas warna wca-badge. */
export function toneClass(tone) {
    const map = {
        gugur: 'wca-b--red',
        talent: 'wca-b--sky',
        lolos: 'wca-b--green',
        // HasilKeputusan::bucket() mengembalikan 'lulus', sedangkan badge lama
        // memakai 'lolos'. Keduanya didaftarkan: satu kunci yang tak terdaftar
        // membuat toneClass jatuh diam-diam ke abu-abu.
        lulus: 'wca-b--green',
        // Keluar atas kehendak kandidat: bukan kegagalan dia (merah), bukan
        // pula proses berjalan (indigo).
        keluar: 'wca-b--violet',
        perlu: 'wca-b--amber',
        nunggu: 'wca-b--slate',
        skor: 'wca-b--indigo',
        berjalan: 'wca-b--indigo',
        hold: 'wca-b--slate',
        pascaPenerimaan: 'wca-b--green',
    }
    return map[tone] || 'wca-b--slate'
}

/**
 * Nama resmi keputusan dari master; kode mentah hanya bila master bungkam.
 * Dipakai menggantikan peta literal {LULUS, GUGUR, TALENT_POOL} yang dulu
 * tersebar di beberapa komponen — peta itu membuat setiap outcome baru tampil
 * sebagai kode mentah berwarna "berjalan".
 */
export function labelOutcome(kode, masterHasil) {
    if (!kode) return '—'
    return masterHasil?.[kode]?.nama || kode
}

/** Kelas badge sebuah outcome, diturunkan dari bucket master. */
export function toneOutcome(kode, masterHasil) {
    return toneClass(masterHasil?.[kode]?.bucket)
}

/** Warna hex dari master — untuk titik/ikon, bukan kelas badge. */
export function warnaOutcome(kode, masterHasil) {
    return masterHasil?.[kode]?.warna || null
}

/**
 * Opsi filter status papan, dibangun dari master supaya kode yang HR tambahkan
 * besok ikut bisa disaring tanpa menyentuh berkas ini. BERJALAN dan DITAHAN
 * bukan outcome — keduanya keadaan proses — jadi ditambahkan manual.
 */
export function opsiStatus(masterHasil) {
    const dariMaster = Object.entries(masterHasil || {}).map(([val, m]) => ({ val, label: m.nama }))
    return [
        { val: 'BERJALAN', label: 'Berjalan' },
        { val: 'DITAHAN', label: 'Ditahan' },
        ...dariMaster,
    ]
}

/** Label bucket panel Perlu Perhatian. */
export function jenisPerhatian(jenis) {
    return {
        SIAP_DIPUTUS: { label: 'Siap Diputus', icon: 'bi-hammer' },
        MENUNGGU_TES: { label: 'Menunggu Hasil Tes', icon: 'bi-hourglass-split' },
        MACET: { label: 'Macet di Tahap', icon: 'bi-exclamation-octagon' },
    }[jenis] || { label: jenis, icon: 'bi-info-circle' }
}

/** Status/hasil sub-tes mentah → kalimat yang terbaca. */
export function labelStatusTes(x) {
    if (x.hasil === 'LULUS') return 'Lulus'
    if (x.hasil === 'GAGAL') return 'Gagal'
    return {
        BELUM: 'Belum dikerjakan',
        DIJADWALKAN: 'Dijadwalkan',
        SELESAI: 'Selesai',
        TIDAK_HADIR: 'Tidak hadir',
    }[x.status] || x.status || '—'
}

/**
 * Sub-tes yang layak ditampilkan. Setiap tahap otomatis mendapat satu sub-tes
 * bawaan saat alur dibuat; pada tahap non-tes (formulir, screening, penawaran)
 * baris itu tidak pernah dipakai sehingga selamanya "BELUM" — menampilkannya
 * hanya membingungkan. Ujian pihak ke-3 tetap ditampilkan walau belum
 * dikerjakan, karena itu memang tes sungguhan yang sedang ditunggu.
 */
export function tesBermakna(daftar) {
    return (daftar || []).filter(
        (x) => x.provider === 'THIRD_PARTY' || x.nilai !== null || (x.status && x.status !== 'BELUM'),
    )
}

/** Byte → ukuran ringkas (mis. "1,2 MB"). */
export function formatUkuran(b) {
    if (!b && b !== 0) return '—'
    if (b < 1024) return `${b} B`
    if (b < 1024 * 1024) return `${(b / 1024).toFixed(0)} KB`
    return `${(b / 1024 / 1024).toFixed(1).replace('.', ',')} MB`
}

/** Ekstensi berkas → bisa dipratinjau sebagai gambar / PDF? */
export function jenisPratinjau(ext, mime) {
    const e = String(ext || '').toLowerCase()
    const m = String(mime || '').toLowerCase()
    if (m.startsWith('image/') || ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'].includes(e)) return 'gambar'
    if (m === 'application/pdf' || e === 'pdf') return 'pdf'
    return null
}

/**
 * Label skor kesehatan → warna ring + teks tampil.
 * KOSONG dan SELESAI sama-sama "tidak ada proses berjalan", tapi artinya jauh
 * berbeda: yang satu belum kedatangan pelamar, yang satu sudah tuntas.
 */
/**
 * Keadaan program untuk kartu di Overview — WARNA dan KETERANGAN, tanpa vonis.
 *
 * Menggantikan skor kesehatan 0-100 yang dulu dipajang di ring. Skor itu
 * menyesatkan dua kali: angkanya berdiri tanpa satuan di sebelah "1 Aktif ·
 * 1 Total" sehingga terbaca sebagai jumlah orang, dan labelnya (SEHAT /
 * PERLU AKSI / KRITIS) adalah kesimpulan sistem — padahal yang memutuskan
 * seharusnya super admin.
 *
 * Yang tersisa di sini semuanya bisa ditelusuri ke hitungan nyata: berapa
 * tertahan, berapa menunggu diketuk, berapa masih berproses. Warna hanya
 * menuntun mata ke kartu yang punya angka itu, bukan menilai programnya.
 */
export function keadaanProgram(sehat = {}, totalPelamar = 0) {
    const aktif = sehat.aktif || 0
    const macet = sehat.macet || 0
    const siap = sehat.siapDiputus || 0
    const nungguTes = sehat.menungguTes || 0

    if (macet > 0) return { warna: '#ef4444', teks: `${macet} tertahan lama`, kelas: 'is-kritis' }
    if (siap > 0) return { warna: '#f59e0b', teks: `${siap} siap diputus`, kelas: 'is-warn' }
    if (nungguTes > 0) return { warna: '#4f46e5', teks: `${nungguTes} menunggu tes`, kelas: 'is-aktif' }
    if (aktif > 0) return { warna: '#4f46e5', teks: `${aktif} berproses`, kelas: 'is-aktif' }
    // Dua keadaan tanpa proses berjalan yang TIDAK boleh disamakan: belum
    // pernah ada pelamar, versus semua pelamar sudah tuntas.
    if (totalPelamar > 0) return { warna: '#64748b', teks: 'Proses selesai', kelas: 'is-selesai' }

    return { warna: '#cbd5e1', teks: 'Belum ada pelamar', kelas: 'is-idle' }
}
