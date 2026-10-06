<?php

namespace App\Console\Commands;

use App\Support\Career\Pemeriksaan;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * WEB CAREER — REDAKSI TEMUAN PEMERIKSAAN YANG SUDAH LEWAT MASA SIMPANNYA.
 *
 * UU PDP 27/2022 menuntut data pribadi dihapus setelah tujuannya tercapai atau
 * masa retensinya berakhir. Untuk pemeriksaan latar belakang, "tujuannya" habis
 * begitu keputusan rekrutmen diambil — sesudah itu catatan hukum seseorang
 * hanya menunggu jadi masalah.
 *
 * ── YANG DIBUANG ISI TEMUANNYA, BUKAN BARISNYA ──────────────────────────────
 *
 * Menghapus barisnya membuat laporan lama berubah sendiri: "5 dari 6 bersih"
 * mendadak jadi "5 dari 5", dan tidak ada yang bisa menjelaskan ke mana
 * perginya satu komponen. Yang dibuang isi ringkasan & sumbernya; status,
 * tanggal, dan siapa yang memeriksa tetap tinggal sebagai jejak bahwa
 * pemeriksaan itu pernah dilakukan.
 *
 * ── HANYA KOMPONEN YANG PUNYA BATAS ─────────────────────────────────────────
 *
 * Retensi_Hari kosong berarti komponen itu mengikuti kebijakan umum perusahaan
 * — dan kebijakan umum bukan urusan perintah ini. Perintah ini tidak menebak.
 *
 * Jalankan terjadwal (harian sudah cukup):
 *     php artisan career:pemeriksaan-retensi
 *     php artisan career:pemeriksaan-retensi --uji   (lihat dulu, tanpa menulis)
 */
class PemeriksaanRetensi extends Command
{
    protected $signature = 'career:pemeriksaan-retensi {--uji : Tampilkan yang akan diredaksi tanpa mengubah apa pun}';

    protected $description = 'Redaksi isi temuan pemeriksaan yang sudah melewati batas retensinya.';

    public function handle(): int
    {
        if (! Pemeriksaan::siap() || ! Schema::hasColumn('N_WEB_CAREERS_Verifikasi_Latar', 'Redaksi_At')) {
            $this->warn('Skema pemeriksaan belum lengkap — tidak ada yang dikerjakan.');

            return self::SUCCESS;
        }

        $uji = (bool) $this->option('uji');

        /* Yang diperiksa: komponen yang punya Retensi_Hari, sudah SELESAI
           (Tanggal_Selesai terisi), lewat batasnya, dan belum pernah diredaksi.

           Tanggal_Selesai yang kosong sengaja dilewati: pemeriksaan yang belum
           selesai belum punya titik mulai hitungan, dan menghitungnya dari
           tanggal dibuat akan membuang temuan yang masih dipakai. */
        $baris = DB::table('N_WEB_CAREERS_Verifikasi_Latar as v')
            ->join('N_WEB_CAREERS_Master_Jenis_Verifikasi as m', 'm.Kode', '=', 'v.Jenis_Kode')
            ->whereNotNull('m.Retensi_Hari')
            ->whereNotNull('v.Tanggal_Selesai')
            ->whereNull('v.Redaksi_At')
            ->whereRaw('DATEADD(day, m.Retensi_Hari, v.Tanggal_Selesai) < GETDATE()')
            ->select([
                'v.Id_Verifikasi_Latar',
                'v.Jenis_Kode',
                'v.Jenis_Nama',
                'v.Tanggal_Selesai',
                'v.Lamaran_Id',
                'm.Retensi_Hari',
            ])
            ->get();

        if ($baris->isEmpty()) {
            $this->info('Tidak ada temuan yang melewati batas retensi.');

            return self::SUCCESS;
        }

        $this->line(sprintf('%d temuan melewati batas retensi:', $baris->count()));
        foreach ($baris as $b) {
            $this->line(sprintf(
                '  #%d  %-28s selesai %s  (batas %d hari)  lamaran #%d',
                $b->Id_Verifikasi_Latar,
                $b->Jenis_Nama,
                substr((string) $b->Tanggal_Selesai, 0, 10),
                $b->Retensi_Hari,
                $b->Lamaran_Id,
            ));
        }

        if ($uji) {
            $this->warn('Mode uji — tidak ada yang diubah.');

            return self::SUCCESS;
        }

        $oleh = 'SISTEM(retensi)';
        $jumlah = DB::table('N_WEB_CAREERS_Verifikasi_Latar')
            ->whereIn('Id_Verifikasi_Latar', $baris->pluck('Id_Verifikasi_Latar'))
            ->update([
                'Ringkasan' => null,
                'Sumber' => null,
                'Vendor_Ref' => null,
                'Redaksi_At' => now(),
                'Redaksi_Oleh' => $oleh,
                'Updated_At' => now(),
                'Updated_By' => $oleh,
            ]);

        Log::channel('web_career')->info(sprintf(
            '[RETENSI PEMERIKSAAN] %d temuan diredaksi (isi dibuang, jejak status tetap).',
            $jumlah,
        ));

        $this->info(sprintf('%d temuan diredaksi. Status & tanggalnya tetap tersimpan.', $jumlah));

        return self::SUCCESS;
    }
}
