<?php

namespace App\Jobs;

use App\Support\Sinkron\PenerbitOutbox;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

/**
 * Satu-satunya job project pengguna: menerbitkan peristiwa Outbox satu akun
 * ke Pub/Sub (queue wcp-terbit).
 *
 * Tidak pernah melempar galat ke antrean: kegagalannya dicatat di baris
 * Outbox (Galat_Terakhir + Coba_Lagi_At), dan penyapu yang mengulangnya.
 * Karena itu tidak perlu tabel failed_jobs di database publik.
 */
class TerbitkanOutbox implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    public function __construct(public string $kunciUrut) {}

    /**
     * Diantrekan sesudah commit (lihat Outbox::tulis). Antrean yang menolak
     * tidak boleh menggagalkan aksi kandidat — datanya sudah tersimpan, dan
     * penyapu akan menerbitkannya.
     */
    public static function antrekan(string $kunciUrut): void
    {
        try {
            static::dispatch($kunciUrut);
        } catch (\Throwable $e) {
            Log::warning("[OUTBOX] penerbit tidak bisa diantrekan ({$kunciUrut}), menunggu penyapu: ".$e->getMessage());
        }
    }

    public function handle(PenerbitOutbox $penerbit): void
    {
        try {
            $penerbit->terbitkanKunci($this->kunciUrut);
        } catch (\Throwable $e) {
            Log::warning("[OUTBOX] penerbit gagal ({$this->kunciUrut}), menunggu penyapu: ".$e->getMessage());
        }
    }
}
