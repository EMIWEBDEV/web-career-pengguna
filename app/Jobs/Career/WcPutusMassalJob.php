<?php

namespace App\Jobs\Career;

use App\Http\Controllers\Career\Lamaran\LamaranController;
use App\Jobs\Career\Concerns\AntreanWebCareers;
use App\Jobs\Career\Concerns\CatatGagalWebCareers;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * WEB CAREERS — SATU KEPUTUSAN SELEKSI, DIKERJAKAN DI LATAR BELAKANG.
 *
 * KENAPA DIANTREKAN
 * Satu keputusan bukan satu baris UPDATE: ia menjalankan mesin syarat, menulis
 * riwayat tahap, membuka tahap berikutnya, dan menerbitkan tugas email. Lima
 * puluh kali pekerjaan itu di dalam SATU permintaan HTTP melewati batas waktu —
 * dan yang terjadi bukan "gagal semua", melainkan yang lebih buruk: sebagian
 * orang pertama benar-benar diputus, sisanya tidak, lalu layar memuntahkan satu
 * galat tanpa menyebut siapa yang sudah selesai. Pada 150 kandidat, jalur itu
 * tidak pernah bisa tuntas.
 *
 * ── SATU JOB SATU ORANG ─────────────────────────────────────────────────────
 *
 * Bukan satu job untuk seluruh gelombang. Kegagalan satu kandidat — tahapnya
 * baru saja ditahan orang lain, syaratnya belum tuntas — tidak boleh
 * menghentikan 149 lainnya, dan percobaan ulang harus bisa menyasar tepat satu
 * orang. Dengan satu job per orang, kedua sifat itu didapat tanpa kode
 * tambahan: antreannya sendiri yang mengurus keduanya.
 *
 * ── TANPA TABEL BARU ────────────────────────────────────────────────────────
 *
 * Seluruh keadaan gelombang sudah punya tempatnya:
 *
 *   kirimannya         payload job ini — dibawa antrean, bukan disalin ke
 *                      tabel lain (N_WEB_CAREERS_Jobs untuk driver database,
 *                      Cloud Tasks di produksi);
 *   sudah diputus      N_WEB_CAREERS_Lamaran_Tahap.Diputus_At — kebenaran yang
 *                      sesungguhnya, bukan salinan status;
 *   gagal + sebabnya   N_WEB_CAREERS_Failed_Jobs lewat CatatGagalWebCareers,
 *                      dengan Jenis 'PUTUSMASSAL:<gelombang>' sehingga panel
 *                      bisa memanggil kembali seluruh kegagalan gelombang itu
 *                      — berikut payload utuhnya, yang membuat "Coba Lagi"
 *                      tidak menuntut admin mengetik ulang alasannya.
 *
 * SEKALI JALAN, TANPA PERCOBAAN OTOMATIS ($tries = 1). Keputusan seleksi bukan
 * operasi yang aman diulang mentah-mentah: percobaan buta setelah galat yang
 * ambigu berisiko mengetuk palu untuk kedua kalinya. Percobaan ulang di sini
 * adalah keputusan manusia — tombol "Coba Lagi" di panel, yang memeriksa dulu
 * apakah tahapnya sudah terlanjur diputus.
 *
 * Nama antrean `wc-putusmassal` harus ada di Cloud Tasks:
 *     gcloud tasks queues create wc-putusmassal --location=asia-southeast1
 * Di lokal cukup `php artisan queue:work` (lihat AntreanWebCareers).
 */
class WcPutusMassalJob implements ShouldQueue
{
    use AntreanWebCareers, CatatGagalWebCareers, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const QUEUE = 'wc-putusmassal';

    /** Satu keputusan + emailnya; longgar, tapi jauh dari batas permintaan HTTP. */
    public $timeout = 180;

    public $tries = 1;

    /**
     * @param  array  $muatan  kiriman utuh satu keputusan — lihat putusMassal().
     */
    public function __construct(private array $muatan)
    {
        $this->aturAntrean(self::QUEUE);
    }

    public function handle(): void
    {
        $m = $this->muatan;
        $tahapId = (int) ($m['tahapRealId'] ?? 0);

        if (! $tahapId) {
            Log::channel('web_career')->error('[PUTUS MASSAL] payload tanpa tahapRealId — dilewati.');

            return;
        }

        // JOB BERJALAN TANPA SESI, sementara seluruh jalur keputusan mencatat
        // "siapa yang mengetuk palu" dari sesi. Tanpa baris ini, riwayat
        // keputusan seluruh gelombang tertulis atas nama ADMIN tanpa wajah —
        // dan justru riwayat itulah yang dibuka saat ada sengketa.
        session()->put('career_auth', [
            'id' => $m['adminId'] ?? null,
            'nama' => $m['adminNama'] ?? 'ADMIN',
        ]);

        try {
            $hasil = app(LamaranController::class)->prosesKeputusanSatu(
                $tahapId,
                [
                    'hasil' => $m['hasil'],
                    'catatan' => $m['catatan'] ?? null,
                    'catatanHtml' => $m['catatanHtml'] ?? null,
                    'catatanEksternalHtml' => $m['catatanEksternalHtml'] ?? null,
                    'talentPool' => $m['talentPool'] ?? null,
                    'tanggalKonfirmasi' => $m['tanggalKonfirmasi'] ?? null,
                ],
                (bool) ($m['catatAktivitas'] ?? false),
            );
        } catch (\Throwable $e) {
            $this->catatGagalWc('PUTUSMASSAL:'.($m['gelombang'] ?? '-'), json_encode($m), $e);

            return; // JANGAN throw → kegagalannya sudah punya rumah sendiri.
        }

        if (! $hasil['ok']) {
            // Ditolak dengan sopan oleh gerbang keputusan (syarat belum tuntas,
            // tahap sedang ditahan, kuota penuh). Bukan kecelakaan teknis, tapi
            // tetap kegagalan yang harus terbaca admin beserta kalimat aslinya.
            $this->catatGagalWc(
                'PUTUSMASSAL:'.($m['gelombang'] ?? '-'),
                json_encode($m),
                new \RuntimeException((string) $hasil['pesan']),
            );

            return;
        }

        // AKTIVITAS YANG TIDAK BISA DIISI OTOMATIS. Bukan kegagalan — keputusan
        // tahapnya sendiri sudah jatuh — jadi ia tidak masuk daftar gagal.
        // Dicatat di log supaya jejaknya tetap ada saat rapor tesnya kelak
        // ditinjau dan seseorang bertanya kenapa satu aktivitas kosong.
        if ($hasil['dilewati']) {
            Log::channel('web_career')->info(sprintf(
                '[PUTUS MASSAL] %s (%s): aktivitas dilewati — %s.',
                $m['nama'] ?? '?', $m['tahapLabel'] ?? '?', implode(', ', $hasil['dilewati'])
            ));
        }

        Log::channel('web_career')->info(sprintf(
            '[PUTUS MASSAL] %s → %s pada "%s" (gelombang %s).',
            $m['nama'] ?? '?', $m['hasil'], $m['tahapLabel'] ?? '?', $m['gelombang'] ?? '-'
        ));
    }

    /**
     * Job mati sama sekali (batas waktu antrean, proses dibunuh) — di luar
     * jangkauan try/catch di handle(). Kegagalannya tetap harus punya baris,
     * karena panel membaca daftar gagal dari sana; tanpa ini, kandidatnya
     * tergantung di "menunggu" selamanya dan tak ada tombol yang menjangkaunya.
     */
    public function failed(\Throwable $e): void
    {
        $this->catatGagalWc(
            'PUTUSMASSAL:'.($this->muatan['gelombang'] ?? '-'),
            json_encode($this->muatan),
            $e,
        );
    }
}
