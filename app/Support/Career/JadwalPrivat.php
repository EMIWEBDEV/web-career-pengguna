<?php

namespace App\Support\Career;

/**
 * WEB CAREERS — TAHAP YANG JADWALNYA TIDAK DIUMUMKAN KE KANDIDAT.
 *
 * Tim boleh menjadwalkannya seperti biasa; kandidat tidak menerima undangan
 * email dan tidak melihatnya di portal.
 *
 * ══ KENAPA NEGOSIASI ══
 *
 * Negosiasi penawaran adalah pembicaraan internal tim sebelum angkanya diajukan.
 * Undangan "Negosiasi Penawaran, Selasa 10.00" yang mendarat di email kandidat
 * memberitahunya bahwa angkanya sedang dirundingkan — dan sejak saat itu tiap
 * hari tanpa kabar terbaca sebagai penolakan yang tertunda. Yang perlu ia terima
 * adalah HASILNYA, bukan jadwal rapat tentang dirinya.
 *
 * ══ KENAPA DAFTAR DI KODE, BUKAN KOLOM MASTER ══
 *
 * Ini keputusan sadar, bukan kelalaian. Sempat dibuat sebagai kolom master
 * (Flag_Jadwal_Privat) supaya bisa dicentang tanpa rilis, lalu SENGAJA
 * dikembalikan ke sini: sejauh ini hanya negosiasi yang berperilaku begini, dan
 * satu kolom yang selamanya berisi satu baris 'Y' hanya menambah tempat untuk
 * salah setel — termasuk kemungkinan seseorang tanpa sengaja mencentang
 * wawancara, lalu kandidatnya tidak pernah diberi tahu harus datang.
 *
 * Daftarnya di sini justru membuat perubahannya harus lewat tinjauan kode, dan
 * itu memang yang diinginkan untuk keputusan sepenting "kandidat tidak
 * diberi tahu".
 *
 * ⚠ SEBELUM MENAMBAH KODE BARU KE DAFTAR INI
 *
 * Tanyakan satu hal: apakah kandidat perlu HADIR atau MENGERJAKAN sesuatu di
 * aktivitas itu? Kalau ya, JANGAN. Ia tidak akan menerima undangan dan tidak
 * melihatnya di portal — artinya ia tidak diberi tahu sama sekali, lalu dianggap
 * mangkir. Daftar ini hanya untuk kegiatan yang dijalankan tim SENDIRI.
 *
 * Kolom Flag_Jadwal_Privat masih ada di basis data tetapi TIDAK LAGI DIBACA.
 * Aman ditinggal (tidak mengganggu apa pun) atau dibuang lain waktu.
 */
class JadwalPrivat
{
    /** Kode Master_Tipe_Tahap yang jadwalnya internal. */
    private const KODE = [
        'NEGOTIATION',
    ];

    /** Tipe tahap ini dijadwalkan diam-diam? */
    public static function untuk(?string $kodeTipe): bool
    {
        return $kodeTipe !== null && in_array($kodeTipe, self::KODE, true);
    }

    /**
     * KANDIDAT BOLEH MELIHAT AKTIVITAS INI DI PORTALNYA?
     *
     * `Tampil_Kandidat` dibekukan ke baris lamaran saat orangnya melamar, dari
     * setelan Master Alur waktu itu. Setelan itu bisa salah, dan satu bentuk
     * salahnya berakibat fatal: aktivitas yang DIJADWALKAN tapi ditandai
     * internal.
     *
     * Yang dialami kandidatnya begini. Ia menerima undangan MCU lewat email,
     * membuka portal untuk memastikan tanggal dan rumah sakitnya, dan tidak
     * menemukan apa pun — halamannya justru berbunyi "menunggu dijadwalkan".
     * Ia menyimpulkan jadwalnya batal, atau emailnya salah kirim. Kalau ia
     * tidak datang, yang tercatat di sistem adalah mangkir.
     *
     * Karena itu JADWAL MENANG ATAS SETELAN: aktivitas yang sudah punya tanggal
     * adalah janji temu, dan janji temu selalu ditunjukkan kepada orang yang
     * harus menepatinya. Satu-satunya pengecualian adalah tipe berjadwal privat
     * di atas — itu memang dijalankan tim untuk dirinya sendiri, dan kandidat
     * tidak pernah diundang ke sana.
     *
     * Diputuskan SAAT DIBACA, bukan dengan memperbaiki baris lamarannya. 358
     * baris MCU sudah telanjur tersimpan salah, dan kandidatnya sudah diundang
     * hari ini — mereka perlu melihat jadwalnya sekarang, bukan setelah ada
     * yang sempat menjalankan skrip perbaikan.
     *
     * @param  object       $baris     Baris N_WEB_CAREERS_Lamaran_Tahap_Tes.
     * @param  string|null  $kodeTipe  Kode tipe yang sudah diselesaikan bila
     *                                 baris itu mewarisinya dari tahap.
     */
    public static function terlihat(object $baris, ?string $kodeTipe = null): bool
    {
        if (($baris->Tampil_Kandidat ?? 'Y') === 'Y') {
            return true;
        }

        return ! empty($baris->Jadwal_Mulai)
            && ! self::untuk($kodeTipe ?: ($baris->Tipe_Tahap_Kode ?? null));
    }
}
