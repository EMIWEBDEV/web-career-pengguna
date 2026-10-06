<?php

namespace App\Jobs\Career;

use App\Jobs\Career\Concerns\AntreanWebCareers;
use App\Jobs\Career\Concerns\CatatGagalWebCareers;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * WEB CAREERS — TUTUP (ATAU BUKA LAGI) SEBUAH MPP MENGIKUTI KURSINYA.
 *
 * ── KENAPA DIANTREKAN, PADAHAL PENUTUPAN POSISI TIDAK ───────────────────────
 *
 * Keduanya sengaja dibedakan.
 *
 * POSISI ditutup SERENTAK dengan penerimaan, di dalam transaksi yang sama.
 * Kursi yang sudah terisi sementara posisinya masih tertulis "BUKA" tidak boleh
 * pernah ada, walau sedetik: pada jendela itu orang lain melihat lowongan
 * terbuka yang kursinya sudah habis, dan mendaftarkan kandidat ke sana.
 *
 * MPP berbeda. Pertanyaan "apakah MPP ini sudah tuntas" menyeberang banyak
 * program dan banyak posisi — satu MPP bisa dipakai lima program sekaligus —
 * dan jawabannya butuh hitung ulang menyeluruh. Menahan keputusan seorang
 * rekruter demi hitungan itu berarti ia menunggu tanpa sebab yang terlihat,
 * untuk sesuatu yang tidak genting: MPP yang menutup tiga detik kemudian tidak
 * merugikan siapa pun.
 *
 * ── DUA ARAH, DAN ITU BUKAN TAMBAHAN ────────────────────────────────────────
 *
 * Job ini juga MEMBUKA kembali. Kandidat yang mengundurkan diri sesudah
 * diterima mengembalikan kursinya; MPP yang sudah terlanjur ditutup harus
 * terbuka lagi, kalau tidak lowongannya mati permanen karena satu orang
 * membatalkan. Karena angkanya dihitung ulang dari nol, arah kedua ini tidak
 * butuh kode sendiri — ia jatuh dari perhitungan yang sama.
 *
 * ── AMAN DIJALANKAN BERULANG ────────────────────────────────────────────────
 *
 * Tidak ada `+ 1` di mana pun. Dijalankan sekali atau sepuluh kali, hasilnya
 * sama — sifat yang wajib dimiliki apa pun yang hidup di antrean, sebab
 * percobaan ulang adalah hal biasa, bukan kekecualian.
 */
class WcTutupMppJob implements ShouldQueue
{
    use AntreanWebCareers;
    use CatatGagalWebCareers;
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 60;

    public function __construct(public string $noTransaksi)
    {
    }

    /**
     * Satu job per MPP. Kiriman kedua untuk MPP yang sama sementara yang pertama
     * masih mengantre tidak berbahaya — hasilnya identik — tapi juga tidak perlu
     * dikerjakan dua kali.
     */
    public function uniqueId(): string
    {
        return 'tutup-mpp-' . $this->noTransaksi;
    }

    public function handle(): void
    {
        $mpp = DB::table('HRIS_Transaksi_GForm')
            ->where('No_Transaksi', $this->noTransaksi)
            ->first(['No_Transaksi', 'Jumlah_Rekruitmen', 'Flag_Selesai', 'Status']);

        if (! $mpp) {
            return;
        }

        // MPP yang DIBATALKAN tidak disentuh. Membukanya kembali karena kursinya
        // kosong akan menghidupkan lagi sesuatu yang sengaja dimatikan.
        if (($mpp->Status ?? '') === 'Y') {
            return;
        }

        // Dihitung dari SELURUH posisi yang menunjuk MPP ini, lintas program.
        $agregat = DB::table('N_WEB_CAREERS_Program_Posisi')
            ->where('Mpp_Ref', $this->noTransaksi)
            ->selectRaw('COUNT(*) AS jml, ISNULL(SUM(Kuota), 0) AS kuota, ISNULL(SUM(Terisi), 0) AS terisi')
            ->first();

        // Belum ada satu pun posisi dibuka atasnya: MPP ini belum dipakai
        // program mana pun, jadi tidak ada yang bisa disimpulkan tentang
        // selesai atau belumnya.
        if (! $agregat || (int) $agregat->jml === 0) {
            return;
        }

        $terisi = (int) $agregat->terisi;
        $rencana = (int) $mpp->Jumlah_Rekruitmen;

        // TOLOK UKURNYA RENCANA MPP, BUKAN JUMLAH KUOTA POSISI.
        //
        // Keduanya bisa berbeda — pada data saat fitur ini dibangun, tujuh MPP
        // punya kursi terbuka melebihi rencananya (MPP-2026-006: rencana 1,
        // dibuka 5). Memakai jumlah kuota posisi sebagai tolok ukur berarti MPP
        // itu baru dianggap selesai setelah 5 orang diterima untuk persetujuan
        // yang hanya menyebut 1 — angka persetujuan berhenti berarti apa pun.
        $selesai = $rencana > 0 && $terisi >= $rencana;
        $flagBaru = $selesai ? 'Y' : 'T';

        if (($mpp->Flag_Selesai ?? 'T') === $flagBaru) {
            return;
        }

        DB::table('HRIS_Transaksi_GForm')
            ->where('No_Transaksi', $this->noTransaksi)
            ->update(['Flag_Selesai' => $flagBaru]);

        Log::channel('web_career')->info(sprintf(
            '[MPP] %s %s otomatis — terisi %d dari rencana %d (kursi dibuka: %d).',
            $this->noTransaksi,
            $selesai ? 'DITUTUP' : 'DIBUKA lagi',
            $terisi,
            $rencana,
            (int) $agregat->kuota
        ));
    }
}
