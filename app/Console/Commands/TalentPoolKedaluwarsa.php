<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * WEB CAREER — Auto-kedaluwarsa Talent Pool.
 *
 * Menandai kartu AKTIF yang sudah melewati Tanggal_Kedaluwarsa menjadi
 * KEDALUWARSA. Dijadwalkan lewat N_HRIS_Scheduler_Configs (mis. harian 01:00).
 * Pembacaan halaman juga menghitung status efektif real-time, jadi command ini
 * hanya "memadatkan" status di DB agar konsisten & bisa difilter.
 */
class TalentPoolKedaluwarsa extends Command
{
    protected $signature = 'talent-pool:kedaluwarsa';

    protected $description = 'Tandai kartu Talent Pool yang lewat masa berlaku menjadi KEDALUWARSA';

    public function handle(): int
    {
        try {
            $now = now();
            $jumlah = DB::table('N_WEB_CAREERS_Talent_Pool')
                ->where('Status', 'AKTIF')
                ->whereNotNull('Tanggal_Kedaluwarsa')
                ->where('Tanggal_Kedaluwarsa', '<', $now)
                ->update([
                    'Status' => 'KEDALUWARSA',
                    'Updated_At' => $now,
                    'Updated_By' => 'SISTEM(scheduler)',
                ]);

            $this->info("Talent Pool kedaluwarsa: {$jumlah} kartu ditandai.");
            Log::channel('web_career')->info("[SCHEDULER] Talent Pool kedaluwarsa: {$jumlah} kartu.");

            return self::SUCCESS;
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('[SCHEDULER] Gagal auto-kedaluwarsa Talent Pool: ' . $e->getMessage());
            $this->error('Gagal: ' . $e->getMessage());

            return self::FAILURE;
        }
    }
}
