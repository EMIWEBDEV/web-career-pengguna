<?php

namespace App\Jobs\Career;

use App\Exceptions\Career\SuratGagal;
use App\Jobs\Career\Concerns\AntreanWebCareers;
use App\Jobs\Career\Concerns\CatatGagalWebCareers;
use App\Services\Surat\SuratClient;
use App\Support\Career\JejakJadwal;
use App\Support\Career\KonfirmasiJadwal;
use App\Support\Career\SuratKonfirmasi;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * WEB CAREERS — SUREL KONFIRMASI KEHADIRAN (antrean `wc-konfirmasimail`).
 *
 * Membawa HANYA id baris jejak. Isi surel dirakit saat job berjalan, dari data
 * terkini (SuratKonfirmasi) — sehingga surel yang sudah basi (jadwal diganti,
 * kandidat sudah menjawab) ditandai LEWAT dan tidak pernah berangkat.
 *
 * ══ IDEMPOTEN ══
 *
 * Kunci ke server surat = "wc-jejak-{id}": Cloud Tasks yang mengulang job ini
 * membawa kunci yang sama, dan EVO Mail mengenali kembarannya. Kiriman yang
 * DISENGAJA (pengingat kedua, kirim ulang) adalah baris jejak baru — kunci baru,
 * tetap berangkat.
 *
 * ══ GAGAL ══
 *
 * Galat sementara (jaringan, 5xx, 429) diulang dengan jeda menaik. Galat
 * permanen (4xx: alamat ditolak, templat belum diizinkan) langsung GAGAL —
 * mengulangnya hanya menunda kabar buruk. Yang gagal final dicatat di
 * N_WEB_CAREERS_Failed_Jobs, barisnya berstatus GAGAL, dan hitungan gagal di
 * snapshot CRM naik — terlihat di Agenda Seleksi.
 *
 * Nama antrean harus ada di Cloud Tasks:
 *     gcloud tasks queues create wc-konfirmasimail
 * Di lokal cukup `php artisan queue:work` (lihat AntreanWebCareers).
 *
 * Pengingat OTOMATIS memakai job yang sama di antreannya sendiri
 * (`wc-pengingatkonfirmasi`, lewat argumen $antrean) — supaya ratusan
 * pengingat terjadwal tidak mengantre di depan tanda terima jawaban kandidat.
 */
class WcKonfirmasiEmailJob implements ShouldQueue
{
    use AntreanWebCareers, CatatGagalWebCareers, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const QUEUE = 'wc-konfirmasimail';

    public $tries = 4;

    public $backoff = [30, 120, 600];

    public function __construct(public int $jejakId, ?string $antrean = null)
    {
        $this->aturAntrean($antrean ?: self::QUEUE);
    }

    public function handle(SuratClient $surat): void
    {
        $j = DB::table(JejakJadwal::TABEL)->where('Id_Jadwal_Jejak', $this->jejakId)->first();
        if (! $j || in_array($j->Undangan, ['TERKIRIM', 'LEWAT', 'PRIVAT'], true)) {
            return;
        }

        $data = json_decode((string) ($j->Data_Json ?? ''), true) ?: [];
        [$kirim, $lewat] = SuratKonfirmasi::susun($j, $data);

        if (! $kirim) {
            KonfirmasiJadwal::tandaiSurel($this->jejakId, 'LEWAT', $lewat);
            Log::channel('web_career')->info("[KONFIRMASI] surel jejak #{$this->jejakId} dilewati: {$lewat}");

            return;
        }

        try {
            $surat->kirim(
                kepada: $kirim['kepada'],
                template: $kirim['template'],
                data: $kirim['data'],
                kunciIdempotensi: 'wc-jejak-'.$this->jejakId,
            );

            KonfirmasiJadwal::tandaiSurel($this->jejakId, 'TERKIRIM');
            Log::channel('web_career')->info("[KONFIRMASI] {$kirim['template']} ({$j->Aksi}) terkirim ke {$kirim['kepada']} — jejak #{$this->jejakId}");
        } catch (\Throwable $e) {
            // 4xx selain 429 = permintaan yang memang tidak akan pernah diterima.
            $permanen = $e instanceof SuratGagal && preg_match('/\(HTTP 4(?!29)\d\d\)/', $e->getMessage());

            if ($permanen || $this->attempts() >= $this->tries) {
                KonfirmasiJadwal::tandaiSurel($this->jejakId, 'GAGAL', $e->getMessage());
                $this->hitungGagal($j, $kirim['template']);
                $this->catatGagalWc('EMAIL:KONFIRMASI', json_encode(['jejak' => $this->jejakId, 'templat' => $kirim['template']]), $e);

                return;
            }

            Log::channel('web_career')->warning("[KONFIRMASI] surel jejak #{$this->jejakId} gagal (percobaan {$this->attempts()}/{$this->tries}): ".$e->getMessage());

            throw $e;
        }
    }

    /** Surel ke KANDIDAT yang gagal final ikut terhitung di snapshot CRM. */
    private function hitungGagal(object $j, string $templat): void
    {
        if ($templat === 'kabar-tim' || $j->Versi === null) {
            return;
        }

        try {
            DB::table(KonfirmasiJadwal::T_CRM)
                ->where('Lamaran_Tahap_Tes_Id', $j->Lamaran_Tahap_Tes_Id)
                ->where('Versi', $j->Versi)
                ->update([
                    'Jumlah_Gagal' => DB::raw('CASE WHEN Jumlah_Gagal < 255 THEN Jumlah_Gagal + 1 ELSE 255 END'),
                    'Updated_At' => now(),
                ]);
        } catch (\Throwable $e) {
            Log::channel('web_career')->warning('[KONFIRMASI] hitungan gagal CRM tidak tertulis: '.$e->getMessage());
        }
    }
}
