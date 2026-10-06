<?php

namespace App\Support\Career;

use App\Jobs\Career\WcTutupMppJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * WEB CAREERS — PEMBUKUAN KURSI SEBUAH POSISI LOWONGAN.
 *
 * Satu tempat yang menjawab: "posisi ini sudah terisi berapa, dan karenanya
 * masih terbuka atau tidak."
 *
 * ── KENAPA MENGHITUNG ULANG, BUKAN MENAMBAH SATU ────────────────────────────
 *
 * `Terisi = Terisi + 1` terlihat lebih murah dan lebih langsung. Ia juga salah
 * secara permanen begitu dijalankan dua kali — dan dua kali itu pasti terjadi:
 * job yang diulang antrean, keputusan yang diklik dua kali karena layar terasa
 * lambat, admin yang membuka dua tab.
 *
 * Menghitung ulang dari lamaran yang benar-benar ada membuat pemanggilan
 * keberapa pun menghasilkan angka yang sama. Itu juga yang membuat JALUR BALIK
 * bekerja tanpa kode tambahan: kandidat yang mengundurkan diri sesudah diterima
 * berhenti terhitung, angkanya turun sendiri, dan posisinya terbuka lagi.
 *
 * ── SUMBER ANGKANYA SATU ────────────────────────────────────────────────────
 *
 * Status lamaran yang memotong kuota dibaca dari MASTER — Flag_Potong_Kuota,
 * lewat HasilKeputusan::kodePotongKuota(). Sampai perbaikan ini kelas ini
 * menulis 'LULUS' secara mati, sementara MonitoringController sudah membaca
 * master. Keduanya hanya KEBETULAN sepakat selama cuma LULUS yang bercentang;
 * begitu satu baris master lain dicentang, dua layar melaporkan angka berbeda
 * atas kursi yang sama, dan tidak ada galat yang menunjukkan mana yang benar.
 *
 * Memakai Hasil_Akhir = 'DITERIMA' juga tetap dihindari: ia menghasilkan
 * pembukuan ketiga pada baris mana pun yang salah satunya belum sempat
 * tersimpan.
 */
class KursiPosisi
{
    public const STATUS_BUKA = 'BUKA';

    public const STATUS_PENUH = 'PENUH';

    /**
     * Selaraskan satu posisi dengan keadaan lamarannya.
     *
     * @return array{terisi:int, kuota:int, status:string, berubah:bool}
     */
    public static function sinkron(int $programPosisiId): array
    {
        return DB::transaction(function () use ($programPosisiId) {
            // DIKUNCI, bukan sekadar dibaca. Dua keputusan yang tiba bersamaan
            // pada kursi terakhir sama-sama akan membaca "9 dari 10" tanpa kunci,
            // lalu keduanya menutup posisi dengan angka yang sudah usang. Pola
            // yang sama dengan gerbang kuota di LamaranService.
            $posisi = DB::table('N_WEB_CAREERS_Program_Posisi')
                ->where('Id_Program_Posisi', $programPosisiId)
                ->lockForUpdate()
                ->first(['Id_Program_Posisi', 'Kuota', 'Terisi', 'Status', 'Mpp_Ref']);

            if (! $posisi) {
                return ['terisi' => 0, 'kuota' => 0, 'status' => self::STATUS_BUKA, 'berubah' => false];
            }

            $terisi = (int) DB::table('N_WEB_CAREERS_Lamaran')
                ->where('Program_Posisi_Id', $programPosisiId)
                ->whereIn('Status', HasilKeputusan::kodePotongKuota() ?: ['LULUS'])
                ->count();

            $kuota = (int) $posisi->Kuota;

            // Kuota 0 berarti TANPA BATAS pada gerbang yang sudah ada
            // (`if ($kuota > 0)`), bukan "penuh sejak awal". Menyamakannya dengan
            // penuh akan menutup setiap posisi yang kuotanya belum diisi.
            $penuh = $kuota > 0 && $terisi >= $kuota;
            $statusBaru = $penuh ? self::STATUS_PENUH : self::STATUS_BUKA;

            // Status selain BUKA/PENUH tidak disentuh: posisi yang sengaja
            // ditutup admin (mis. dibatalkan) tidak boleh dibuka lagi hanya
            // karena kursinya kebetulan kosong.
            $statusLama = (string) $posisi->Status;
            $bolehUbah = in_array($statusLama, [self::STATUS_BUKA, self::STATUS_PENUH, ''], true);

            $berubah = (int) $posisi->Terisi !== $terisi || ($bolehUbah && $statusLama !== $statusBaru);

            if ($berubah) {
                DB::table('N_WEB_CAREERS_Program_Posisi')
                    ->where('Id_Program_Posisi', $programPosisiId)
                    ->update([
                        'Terisi' => $terisi,
                        'Status' => $bolehUbah ? $statusBaru : $statusLama,
                        // Dicatat hanya saat BERALIH ke penuh. Menimpanya setiap
                        // kali akan menghapus kapan sebenarnya kursi terakhir
                        // terisi — satu-satunya jejak kejadian itu.
                        'Ditutup_At' => $bolehUbah && $statusBaru === self::STATUS_PENUH && $statusLama !== self::STATUS_PENUH
                            ? now()
                            : ($bolehUbah && $statusBaru === self::STATUS_BUKA ? null : DB::raw('Ditutup_At')),
                    ]);

                Log::channel('web_career')->info(sprintf(
                    '[KURSI] posisi #%d: %d/%d -> %s',
                    $programPosisiId, $terisi, $kuota, $bolehUbah ? $statusBaru : $statusLama
                ));
            }

            return [
                'terisi' => $terisi,
                'kuota' => $kuota,
                'status' => $bolehUbah ? $statusBaru : $statusLama,
                'berubah' => $berubah,
                'mppRef' => $posisi->Mpp_Ref,
            ];
        });
    }

    /**
     * Selaraskan posisi milik sebuah lamaran, lalu antrekan penutupan MPP-nya.
     *
     * Dipanggil dari titik penerimaan DAN dari jalur balik (mengundurkan diri,
     * pengulangan tahap). Aman dipanggil untuk lamaran yang tidak menempel ke
     * posisi mana pun — ia berhenti di baris pertama.
     */
    public static function untukLamaran(int $lamaranId): void
    {
        $posisiId = (int) DB::table('N_WEB_CAREERS_Lamaran')
            ->where('Id_Lamaran', $lamaranId)
            ->value('Program_Posisi_Id');

        if (! $posisiId) {
            return;
        }

        $hasil = self::sinkron($posisiId);

        // BUKU KURSI MPP ikut diselaraskan — lintas program.
        //
        // Pembukuan per loker saja tidak pernah bisa menjawab "MPP ini sudah
        // terisi berapa": satu MPP yang dibuka di dua program punya dua baris
        // loker yang tidak saling kenal. Lihat App\Support\Career\KursiMpp.
        //
        // Serentak, bukan diantrekan seperti penutupan MPP di bawah: angka
        // inilah yang dibaca gerbang keputusan berikutnya, dan jendela beberapa
        // detik saat ia masih usang adalah persis jendela tempat kursi ke-16
        // bisa lolos.
        KursiMpp::sinkron($hasil['mppRef'] ?? null);

        if (! empty($hasil['mppRef'])) {
            // afterCommit: kalau dikirim sebelum transaksi pemanggilnya selesai,
            // worker bisa membaca keadaan LAMA dan menutup MPP yang belum penuh —
            // atau membiarkan terbuka yang sudah penuh. Keduanya salah, dan
            // keduanya sulit ditelusuri karena bergantung pada waktu.
            WcTutupMppJob::dispatch((string) $hasil['mppRef'])->afterCommit();
        }
    }
}
