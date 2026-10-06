<?php

namespace App\Jobs\Career\Concerns;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Mencatat kegagalan job modul WEB CAREERS ke tabel SENDIRI
 * (N_WEB_CAREERS_Failed_Jobs) — TIDAK menyentuh N_LMS_Failed_Jobs.
 *
 * Pola pemakaian di handle() job:
 *   } catch (\Throwable $e) {
 *       if ($this->attempts() >= $this->tries) {
 *           $this->catatGagalWc('EMAIL:'.$jenis, $payloadJson, $e);
 *           return;                 // JANGAN throw → tak masuk failed-provider global
 *       }
 *       throw $e;                   // masih ada sisa percobaan → retry normal
 *   }
 */
trait CatatGagalWebCareers
{
    protected function catatGagalWc(string $jenis, string $payload, \Throwable $e): void
    {
        try {
            DB::table('N_WEB_CAREERS_Failed_Jobs')->insert([
                'Uuid' => (string) Str::uuid(),
                'Jenis' => $jenis,
                'Connection' => $this->connection ?? (env('QUEUE_CONNECTION') ?: 'database'),
                'Queue' => $this->queue ?? null,
                'Payload' => $payload,
                'Exception' => mb_substr($e->getMessage(), 0, 3000),
                'Percobaan' => method_exists($this, 'attempts') ? $this->attempts() : null,
                'Failed_At' => now(),
            ]);
        } catch (\Throwable $ignore) {
            // Jangan biarkan pencatatan gagal menjatuhkan proses.
            Log::error('[WC-FAILED] gagal menulis N_WEB_CAREERS_Failed_Jobs: ' . $ignore->getMessage());
        }

        Log::error("[WC-FAILED] {$jenis} GAGAL FINAL — dicatat di N_WEB_CAREERS_Failed_Jobs. Pesan: " . $e->getMessage());
    }
}
