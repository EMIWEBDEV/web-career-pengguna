<?php

namespace App\Console\Commands;

use App\Support\Sinkron\PenerbitOutbox;
use Illuminate\Console\Command;

/**
 * Penyapu Outbox untuk lokal / darurat. Di Cloud Run penyapunya dipicu Cloud
 * Scheduler lewat POST /api/tugas/terbit-outbox — kodenya sama.
 */
class TerbitkanOutboxCommand extends Command
{
    protected $signature = 'outbox:terbit {--batas= : paling banyak baris yang diterbitkan}';

    protected $description = 'Terbitkan peristiwa Outbox yang masih MENUNGGU ke Pub/Sub (urut per akun)';

    public function handle(PenerbitOutbox $penerbit): int
    {
        $batas = $this->option('batas') !== null ? (int) $this->option('batas') : null;
        $r = $penerbit->sapu($batas);

        $this->info("Terbit {$r['terbit']} peristiwa dari {$r['kunci']} akun; {$r['gagal']} akun gagal (dicoba lagi otomatis).");

        return self::SUCCESS;
    }
}
