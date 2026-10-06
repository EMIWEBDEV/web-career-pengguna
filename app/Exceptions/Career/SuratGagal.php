<?php

namespace App\Exceptions\Career;

use RuntimeException;

/**
 * Pengiriman lewat EVO Mail Server gagal.
 *
 * ══ KENAPA KELASNYA SENDIRI, BUKAN RuntimeException BIASA ═════════════════
 *
 * Supaya job bisa membedakannya dari kegagalan LAIN yang terjadi di dalam
 * blok yang sama — kueri yang meledak, berkas GCS yang hilang, baris yang
 * tidak ketemu. Ketiganya menuntut penanganan berbeda, dan tanpa kelas
 * tersendiri yang tersisa cuma mencocokkan potongan kalimat galat.
 *
 * ══ SELALU LAYAK DIULANG ══════════════════════════════════════════════════
 *
 * Termasuk yang lahir dari jawaban 4xx — kunci yang salah, izin template yang
 * belum diberikan, muatan yang kurang satu isian. Percobaan ulang memang tidak
 * akan menyembuhkannya, tapi tujuannya bukan itu: yang dicari adalah kegagalan
 * itu MENDARAT di N_WEB_CAREERS_Failed_Jobs, lengkap dengan kalimat aslinya,
 * alih-alih lenyap tanpa jejak.
 *
 * Surat yang tidak berangkat karena kunci salah ketik dan surat yang tidak
 * berangkat karena server surat mati tampak sama persis dari sisi kandidat:
 * tidak ada apa-apa di kotak masuk. Yang membedakannya cuma catatan ini.
 */
class SuratGagal extends RuntimeException
{
}
