<?php

namespace App\Http\Controllers\Career\Tugas;

use App\Http\Controllers\Controller;
use App\Jobs\Career\WcPengingatKonfirmasiJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * PemicuTugasController — pintu masuk Cloud Scheduler ke pekerjaan terjadwal
 * Web Careers. Pola sama dengan cat-evo-pembaharuan (Diagnostik\PemicuTugasController).
 *
 * ── Kenapa perlu ada ─────────────────────────────────────────────────────────
 * Cloud Tasks adalah ANTREAN, bukan penjadwal: ia tidak punya cron dan tidak
 * pernah membangunkan dirinya sendiri setiap hari. Container Cloud Run juga tidak
 * menjalankan `php artisan schedule:run`. Yang menekan tombolnya Cloud Scheduler,
 * lewat satu permintaan HTTP ke berkas ini; sesudah itu Cloud Tasks mengambil alih.
 *
 *   Cloud Scheduler (05.00, 08.00, 12.00, 16.00)  →  HTTP  →  berkas ini  →  antrean
 *
 * Jadwalnya hidup di Cloud Scheduler, BUKAN di basis data.
 *
 * ── Pengamanan ───────────────────────────────────────────────────────────────
 * Kunci rahasia dari env (TUGAS_TOKEN, minimal 32 karakter), dikirim di header
 * X-Tugas-Token dan dibandingkan waktu-tetap. Tanpa kunci yang layak di env,
 * endpoint ini menjawab 404 — gagal-aman, bukan terbuka. Sengaja HANYA header (tidak lewat ?token=): URL ikut
 * tercatat di log akses Cloud Run dan penyeimbang beban.
 */
class PemicuTugasController extends Controller
{
    /**
     * Nama tugas → kelas job. Hanya yang terdaftar di sini yang bisa dipicu.
     * Setiap kelas menyediakan `dariPemicu(): ?static` (null = bukan jamnya)
     * dan konstanta QUEUE.
     */
    private const TUGAS = [
        'pengingat-konfirmasi' => WcPengingatKonfirmasiJob::class,
    ];

    public function jalankan(Request $request, string $tugas)
    {
        $rahasia = (string) config('tugas.token', '');

        // Gagal-aman: tanpa kunci — atau kunci terlalu pendek untuk disebut
        // rahasia (pola GEMBOK_SECRET) — endpoint ini tidak ada.
        if (strlen($rahasia) < 32) {
            abort(404);
        }

        $dikirim = (string) $request->header('X-Tugas-Token', '');

        // hash_equals: perbandingan waktu-tetap agar kunci tidak bisa ditebak
        // bertahap lewat pengukuran selisih waktu respons.
        if ($dikirim === '' || ! hash_equals($rahasia, $dikirim)) {
            abort(404);
        }

        if (! isset(self::TUGAS[$tugas])) {
            abort(404);
        }

        $kelas = self::TUGAS[$tugas];
        $job = $kelas::dariPemicu();

        if (! $job) {
            Log::channel('web_career')->info("[PemicuTugas] {$tugas} dipicu di luar jamnya — tidak ada yang diantrekan.");

            return response()->json([
                'success' => true,
                'tugas' => $tugas,
                'dilewati' => 'Bukan jam tugas ini.',
                'waktu' => now()->format('Y-m-d H:i:s'),
            ], 202);
        }

        dispatch($job);

        Log::channel('web_career')->info('[PemicuTugas] dipicu', [
            'tugas' => $tugas,
            'antrean' => $kelas::QUEUE,
        ]);

        // 202: tugasnya sudah diantrekan, bukan sudah selesai. Cloud Scheduler
        // hanya perlu tahu bahwa tombolnya tertekan — pekerjaannya berlangsung
        // di antrean, jauh lebih lama daripada batas waktu permintaan HTTP.
        return response()->json([
            'success' => true,
            'tugas' => $tugas,
            'antrean' => $kelas::QUEUE,
            'waktu' => now()->format('Y-m-d H:i:s'),
        ], 202);
    }
}
