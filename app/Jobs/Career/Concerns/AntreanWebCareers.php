<?php

namespace App\Jobs\Career\Concerns;

/**
 * Penentuan ANTREAN untuk job modul Web Careers.
 *
 * Aturannya satu kalimat: **transport ditentukan QUEUE_CONNECTION, bukan
 * dipaksa di dalam job.** Dengan begitu satu perintah cukup untuk semuanya:
 *
 *     php artisan queue:work
 *
 * Dulu tiap job memanggil `->onConnection('webcareers')`. Akibatnya job masuk
 * ke koneksi 'webcareers', sementara `queue:work` tanpa argumen membaca koneksi
 * DEFAULT — jadi job tidak pernah terambil kecuali worker dijalankan dengan
 * `queue:work webcareers`. Gampang terlupa, dan kalau lupa, lamaran menggantung
 * tanpa jejak error.
 *
 * Nama queue hanya dipasang untuk Cloud Tasks, karena di sana tiap queue adalah
 * sumber daya tersendiri yang harus disebut namanya. Untuk driver database
 * JANGAN dinamai: `queue:work` mendengarkan queue 'default', jadi job bernama
 * lain akan tergeletak selamanya.
 *
 * Ringkasnya:
 *   lokal   QUEUE_CONNECTION=webcareers → tabel N_WEB_CAREERS_Jobs, queue 'default'
 *   cloud   QUEUE_CONNECTION=cloudtasks → Cloud Tasks, queue sesuai nama job
 *   uji     QUEUE_CONNECTION=sync       → dijalankan langsung, tanpa worker
 */
trait AntreanWebCareers
{
    protected function aturAntrean(string $namaQueue): void
    {
        if (config('queue.default') === 'cloudtasks') {
            $this->onQueue($namaQueue);
        }
    }
}
