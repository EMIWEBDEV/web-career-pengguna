<?php

namespace App\Jobs\Career;

use App\Jobs\Career\Concerns\AntreanWebCareers;
use App\Support\Career\PengingatKonfirmasi;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Satu putaran pengingat konfirmasi kehadiran untuk satu slot jam.
 *
 * Dilempar PemicuTugasController saat Cloud Scheduler menekan
 * POST /api/tugas/pengingat-konfirmasi. Pekerjaan sesungguhnya ada di
 * PengingatKonfirmasi::jalankan(); kelas ini hanya mengatur antrean, slot,
 * dan putaran lanjutan.
 *
 * ══ ANTREAN SENDIRI ══
 *
 * `wc-pengingatkonfirmasi` dipakai job ini DAN surel pengingatnya
 * (WcKonfirmasiEmailJob dengan antrean yang sama). Tanda terima jawaban,
 * kabar tim, dan undangan tetap di `wc-konfirmasimail` — ratusan pengingat
 * pukul 05.00 tidak membuat kandidat yang baru menjawab menunggu tanda
 * terimanya. Laju kirimnya diatur di setelan antrean Cloud Tasks.
 *
 * ══ PUTARAN LANJUTAN ══
 *
 * Satu putaran memproses `per_putaran` kandidat. Bila penuh, job ini
 * menjadwalkan dirinya sendiri untuk slot yang SAMA, ditunda sepanjang
 * cicilan surel putaran ini — sehingga surelnya tidak menumpuk dan kunci
 * slotnya tidak berebut. Dibatasi `putaran_maks`.
 */
class WcPengingatKonfirmasiJob implements ShouldQueue
{
    use AntreanWebCareers, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const QUEUE = 'wc-pengingatkonfirmasi';

    public $tries = 3;

    public $backoff = [60, 300];

    /** Jauh di bawah batas waktu permintaan Cloud Run — antrean produksi = Cloud Tasks. */
    public $timeout = 240;

    public function __construct(public string $tanggal, public int $jam, public int $putaran = 1)
    {
        $this->aturAntrean(self::QUEUE);
    }

    /**
     * Dipanggil pemicu. Slot diambil dari JAM PEMICU — bukan jam tugas ini
     * akhirnya dijalankan antrean. Di luar jam pengingat → null (tidak ada
     * yang diantrekan).
     */
    public static function dariPemicu(): ?self
    {
        $slot = PengingatKonfirmasi::slotSekarang();

        return $slot ? new self($slot['tanggal'], $slot['jam']) : null;
    }

    public function handle(): void
    {
        $slot = PengingatKonfirmasi::labelSlot($this->tanggal, $this->jam);

        if (! PengingatKonfirmasi::aktif()) {
            Log::channel('web_career')->info("[PENGINGAT] slot {$slot}: dinonaktifkan (KONFIRMASI_PENGINGAT_AKTIF=false).");

            return;
        }
        if (! PengingatKonfirmasi::siap()) {
            Log::channel('web_career')->warning("[PENGINGAT] slot {$slot}: skema belum dipasang (docs/01-10-2026/01 + docs/03-10-2026/01) — dilewati.");

            return;
        }
        if (PengingatKonfirmasi::slotKedaluwarsa($this->tanggal, $this->jam)) {
            Log::channel('web_career')->warning("[PENGINGAT] slot {$slot} putaran {$this->putaran}: terlambat diproses antrean — dilewati, menunggu slot berikutnya.");

            return;
        }

        $hasil = PengingatKonfirmasi::jalankan($this->tanggal, $this->jam);

        Log::channel('web_career')->info(sprintf(
            '[PENGINGAT] slot %s putaran %d: %d calon, %d diantrekan, %d dilewati.',
            $slot, $this->putaran, $hasil['dipilih'], $hasil['diantrekan'], count($hasil['lewat']),
        ));

        if (! $hasil['sisa']) {
            return;
        }

        $maksPutaran = max(1, (int) config('konfirmasi.pengingat.putaran_maks', 25));
        if ($this->putaran >= $maksPutaran) {
            Log::channel('web_career')->warning("[PENGINGAT] slot {$slot}: batas {$maksPutaran} putaran tercapai — sisanya menunggu slot berikutnya.");

            return;
        }

        $tunda = $hasil['diantrekan'] * max(0, (int) config('konfirmasi.cicil_detik', 2));
        self::dispatch($this->tanggal, $this->jam, $this->putaran + 1)
            ->delay(now()->addSeconds(min(900, max(5, $tunda))));
    }

    public function failed(\Throwable $e): void
    {
        Log::channel('web_career')->error(sprintf(
            '[PENGINGAT] slot %s putaran %d gagal: %s',
            PengingatKonfirmasi::labelSlot($this->tanggal, $this->jam), $this->putaran, $e->getMessage(),
        ));
    }
}
