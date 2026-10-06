<?php

namespace App\Jobs\Career;

use App\Jobs\Career\Concerns\AntreanWebCareers;
use App\Services\Surat\SuratClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * WEB CAREER — undangan JADWAL wawancara / tes tatap muka.
 *
 * Lewat antrean karena pengiriman bisa memakan beberapa detik, sementara
 * rekruter menunggu modalnya tertutup. Jadwalnya sendiri sudah tersimpan
 * sebelum job ini dijalankan, jadi kegagalan email tidak pernah membuat
 * kandidat kehilangan jadwalnya — ia tetap terlihat di portal.
 *
 * Suratnya dikirim EVO Mail Server; job ini yang menentukan isinya.
 */
class WcJadwalEmailJob implements ShouldQueue
{
    use AntreanWebCareers, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Ikut antrean email yang sudah ada — sifat kerjanya sama. */
    public const QUEUE = 'wc-applymail';

    public $tries = 3;

    public $backoff = 30;

    protected int $userId;

    protected array $data;

    /**
     * Kunci idempotensi, disusun di konstruktor supaya STABIL lintas percobaan
     * ulang: Cloud Tasks mengulang job dengan muatan yang sama, jadi kunci
     * yang sama ikut terbawa dan server surat mengenali kembarannya.
     *
     * Acak, bukan diturunkan dari id jadwal — rekruter memang sesekali perlu
     * mengirim ulang undangan yang sama (kandidat melapor tidak menerimanya),
     * dan kunci yang tetap akan membuat pengiriman ulang itu dijawab "sudah
     * pernah dikirim" tanpa satu surat pun berangkat.
     */
    protected string $kunci;

    public function __construct(int $userId, array $data)
    {
        $this->userId = $userId;
        $this->data = $data;
        $awalan = match (true) {
            ! empty($data['pengingat']) => 'ingat:',
            ! empty($data['kirim_ulang']) => 'ulang:',
            ! empty($data['perpanjang']) => 'panjang:',
            default => 'jadwal:',
        };
        $this->kunci = $awalan.$userId.':'.Str::random(16);

        $this->aturAntrean(self::QUEUE);
    }

    public function handle(): void
    {
        $email = $this->data['email'] ?? null;
        if (! $email) {
            Log::channel('web_career')->warning("[JADWAL] user #{$this->userId} tanpa email — undangan dilewati.");

            return;
        }

        try {
            /*
             * Seluruh rincian undangan diteruskan apa adanya.
             *
             * Rinciannya memang berbeda-beda per aktivitas — lokasi dan
             * patokan untuk tatap muka, tautan pertemuan untuk daring, catatan
             * berkas yang perlu dibawa — dan mendaftarkannya satu per satu di
             * sini berarti setiap penambahan satu baris keterangan menuntut
             * deploy DUA layanan sekaligus. Yang dibaca template di seberang
             * tetap hanya kunci yang ia kenal.
             */
            app(SuratClient::class)->kirim(
                kepada: $email,
                template: 'undangan-jadwal',
                data: $this->data + [
                    // Disusun DI SINI, bukan di server surat: "Sabtu, 02
                    // Agustus 2026 · 09.00 – 10.30 WIB" menuntut nama hari
                    // berbahasa Indonesia dan zona waktu yang benar, dan
                    // keduanya milik layanan yang menyimpan jadwalnya.
                    //
                    // Mode BERRENTANG TANGGAL (MCU vendor / mandiri) tidak
                    // punya jam janji temu — yang dijanjikan rentangnya.
                    'waktu_teks' => ! empty($this->data['batas_waktu'])
                        ? ($this->data['rentang_teks'] ?? ('Paling lambat '.($this->data['batas_teks'] ?? '—')))
                        : $this->susunWaktu($this->data['mulai'] ?? null, $this->data['selesai'] ?? null),
                    'daring' => strtoupper((string) ($this->data['mode'] ?? '')) === 'DARING',
                ],
                kunciIdempotensi: $this->kunci,
            );

            Log::channel('web_career')->info(
                "[JADWAL] undangan {$this->data['aktivitas']} terkirim ke {$email}."
            );
        } catch (\Throwable $e) {
            Log::channel('web_career')->error(
                "[JADWAL] undangan ke {$email} GAGAL (percobaan {$this->attempts()}/{$this->tries}): ".$e->getMessage()
            );

            throw $e;
        }
    }

    /**
     * "Sabtu, 02 Agustus 2026 · 09.00 – 10.30 WIB".
     *
     * Hari ikut disebut karena itu yang paling cepat ditangkap orang saat
     * membaca undangan; tanggal saja menuntut membuka kalender.
     */
    private function susunWaktu(?string $mulai, ?string $selesai): string
    {
        if (! $mulai) {
            return '—';
        }

        try {
            $m = Carbon::parse($mulai)->locale('id');
            $teks = $m->translatedFormat('l, d F Y').' · '.$m->format('H.i');

            if ($selesai) {
                $teks .= ' – '.Carbon::parse($selesai)->format('H.i');
            }

            return $teks.' WIB';
        } catch (\Throwable $e) {
            return (string) $mulai;
        }
    }
}
