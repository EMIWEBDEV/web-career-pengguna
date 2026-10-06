<?php

namespace App\Jobs\Career;

use App\Jobs\Career\Concerns\AntreanWebCareers;
use App\Services\Surat\SuratClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * WEB CAREER — pengingat H-1 batas pengisian formulir tahap.
 *
 * Diterbitkan perintah `karir:pengingat-batas`, yang sudah mencicil jumlahnya
 * per putaran. Suratnya dikirim EVO Mail Server (templat `pengingat-batas`);
 * job ini hanya menyusun isinya. Batasnya sendiri tetap berlaku walau surat
 * gagal — kandidat melihat hitung mundurnya di portal.
 */
class WcPengingatBatasJob implements ShouldQueue
{
    use AntreanWebCareers, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Ikut antrean email yang sudah ada — sifat kerjanya sama. */
    public const QUEUE = 'wc-applymail';

    public $tries = 3;

    public $backoff = 30;

    protected int $userId;

    protected array $data;

    /** Acak per terbitan; stabil lintas percobaan ulang — lihat WcJadwalEmailJob. */
    protected string $kunci;

    public function __construct(int $userId, array $data)
    {
        $this->userId = $userId;
        $this->data = $data;
        $this->kunci = 'batas:'.$userId.':'.Str::random(16);

        $this->aturAntrean(self::QUEUE);
    }

    public function handle(): void
    {
        $email = $this->data['email'] ?? null;
        if (! $email) {
            Log::channel('web_career')->warning("[BATAS] user #{$this->userId} tanpa email — pengingat dilewati.");

            return;
        }

        try {
            app(SuratClient::class)->kirim(
                kepada: $email,
                template: 'pengingat-batas',
                data: [
                    'nama' => $this->data['nama'] ?? 'Kandidat',
                    'tahap' => $this->data['tahap'] ?? 'formulir',
                    'batas_teks' => $this->data['batas_teks'] ?? '-',
                    'sisa_teks' => $this->data['sisa_teks'] ?? null,
                    'posisi' => $this->data['posisi'] ?? null,
                    'program' => $this->data['program'] ?? null,
                    'kode' => $this->data['kode'] ?? null,
                ],
                kunciIdempotensi: $this->kunci,
            );

            Log::channel('web_career')->info("[BATAS] pengingat {$this->data['tahap']} terkirim ke {$email}.");
        } catch (\Throwable $e) {
            Log::channel('web_career')->error(
                "[BATAS] pengingat ke {$email} GAGAL (percobaan {$this->attempts()}/{$this->tries}): ".$e->getMessage()
            );

            throw $e;
        }
    }
}
