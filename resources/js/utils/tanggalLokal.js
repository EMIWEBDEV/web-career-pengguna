/**
 * TANGGAL LOKAL DALAM BENTUK YYYY-MM-DD.
 *
 * Ada di utils, bukan di dalam satu .vue, karena jebakannya berlaku di mana pun
 * tanggal hari ini dikirim ke server.
 *
 * ── KENAPA BUKAN toISOString().slice(0, 10) ─────────────────────────────────
 *
 * `toISOString()` mengubah waktunya ke UTC lebih dulu. Di WIB (UTC+7) setiap
 * saat sebelum pukul 07:00 pagi masih terhitung HARI KEMARIN dalam UTC — jadi
 * MPP yang dibuat pukul 06.30 akan tercatat berperiode kemarin, dan pintu simpan
 * menolaknya sebagai "tanggal sudah lewat". Kesalahan yang hanya muncul pagi
 * hari adalah kesalahan yang paling lama tidak ketahuan.
 *
 * Bagian tanggal dibaca dari penanggalan LOKAL peramban, tanpa singgah ke UTC.
 */
export function hariIni(d = new Date()) {
    const dua = (n) => String(n).padStart(2, '0');

    return `${d.getFullYear()}-${dua(d.getMonth() + 1)}-${dua(d.getDate())}`;
}
