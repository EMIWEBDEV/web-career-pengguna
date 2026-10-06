<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Sengaja kosong. Penyapu Outbox dipicu Cloud Scheduler lewat HTTP
        // (POST /api/tugas/terbit-outbox), bukan schedule:run — Cloud Run tidak
        // punya proses cron. Untuk lokal: php artisan outbox:terbit.
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}