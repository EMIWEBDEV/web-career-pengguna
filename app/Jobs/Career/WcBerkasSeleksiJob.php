<?php

namespace App\Jobs\Career;

use App\Jobs\Career\Concerns\AntreanWebCareers;
use App\Services\WebCareers\LaporanTesClient;
use App\Support\Career\RakitBerkasSeleksi;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * WEB CAREER — cetak BERKAS SELEKSI KANDIDAT di latar belakang.
 *
 * KENAPA DIANTREKAN — DAN KENAPA LEBIH PERLU DARIPADA LAPORAN BIASA
 *
 * Satu berkas seleksi bisa memuat belasan halaman rancangan, beberapa laporan
 * psikotes yang datanya diambil dari CAT lewat jaringan, dan puluhan lampiran
 * yang harus diunduh satu per satu dari penyimpanan awan lalu digabungkan.
 * Dikerjakan di dalam permintaan HTTP, admin menatap layar membeku selama
 * hampir semenit, menekan tombolnya lagi, dan lahir dua berkas untuk satu
 * permintaan — masing-masing menembak CAT dan mengunduh lampiran yang sama.
 *
 * ANGGARAN WAKTUNYA LEBIH LONGGAR daripada laporan kandidat (900 detik, bukan
 * 300): yang menentukan bukan rendering, melainkan banyaknya lampiran dan
 * kecepatan CAT menjawab.
 *
 * TIDAK DIULANG SETELAH GAGAL ($tries = 1). Berbeda dari job penjadwalan yang
 * idempoten, satu percobaan ulang di sini berarti mengunduh ulang seluruh
 * lampiran dan menembak CAT sekali lagi — mahal, dan hampir tidak pernah
 * menolong: kegagalannya biasanya data, bukan gangguan sesaat. Admin bisa
 * menekan cetak lagi kapan pun, dan pesan galatnya tersimpan di Export_Log.
 *
 * ── PRATINJAU IKUT LEWAT SINI ─────────────────────────────────────────────
 *
 * Dulu pratinjau dirender di dalam permintaan HTTP. Di produksi itu berakhir
 * 503: perakitannya makan ~56 detik (44 detik di antaranya untuk mengambil dan
 * merender laporan psikotes dari CAT), sementara Cloud Run memutus permintaan
 * pada 60 detik. Menaikkan batasnya hanya memindahkan masalah — admin tetap
 * menatap layar diam hampir semenit, dan tiap pratinjau menahan satu instance
 * selama itu.
 *
 * Yang dilakukan pratinjau di sini SAMA PERSIS dengan unduhan kecuali dua hal:
 *   • `ringan: true`  — lampiran formulir diganti halaman penanda (berkas
 *                       penilaian FGD/wawancara tetap digabung sungguhan);
 *   • berkasnya ditandai sementara, dan TIDAK muncul di riwayat unduhan.
 *
 * Itu sebabnya yang dilihat admin di layar identik dengan yang ia terima —
 * satu-satunya perbedaan justru yang memang sengaja dibedakan.
 */
class WcBerkasSeleksiJob implements ShouldQueue
{
    use AntreanWebCareers, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // NAMA QUEUE di Cloud Tasks — bukan kunci config('queue.names.*).
    // Cloud Tasks hanya menerima huruf, angka, dan hyphen.
    public const QUEUE = 'async-export';

    public $timeout = 900;

    public $tries = 1;

    /**
     * @param  list<string>  $kunci  seksi yang dicentang admin
     * @param  int|null  $penggunaId  admin yang meminta — dipakai saat memanggil
     *                                CAT, sebab di dalam antrean tidak ada sesi.
     */
    /**
     * @param  array  $atur  penyesuaian tampilan per kunci — judul yang ditulis
     *                       ulang, bentuk bagian berulang, jarak atas.
     * @param  list<string>  $bab  urutan bab yang digeser admin; kosong = bawaan.
     */
    public function __construct(
        private int $exportId,
        private int $lamaranId,
        private array $kunci,
        private ?int $penggunaId = null,
        private array $atur = [],
        private array $bab = [],
        private bool $pratinjau = false,
    ) {
        $this->aturAntrean(self::QUEUE);
    }

    public function handle(): void
    {
        $mulai = microtime(true);

        $lamaran = DB::table('N_WEB_CAREERS_Lamaran as l')
            ->leftJoin('N_WEB_CAREERS_Users as u', 'u.Id_Users', '=', 'l.Id_Users')
            ->where('l.Id_Lamaran', $this->lamaranId)
            ->select('l.Kode', 'u.Nama')
            ->first();

        if (! $lamaran) {
            throw new \RuntimeException("Lamaran #{$this->lamaranId} tidak ditemukan.");
        }

        // Rekaman sambungan CAT dikosongkan dulu: proses pekerja antrean
        // hidup lama dan mengerjakan banyak job berturut-turut, jadi status
        // job sebelumnya masih menempel kalau tidak dibersihkan.
        LaporanTesClient::bersihkanStatus();

        $isi = RakitBerkasSeleksi::jalankan(
            $this->lamaranId,
            $this->kunci,
            ringan: $this->pratinjau,
            penggunaId: $this->penggunaId,
            atur: $this->atur,
            bab: $this->bab,
        );

        $slug = Str::slug($lamaran->Nama ?: 'kandidat');

        // Pratinjau disimpan di folder TERPISAH. Bukan sekadar kerapian:
        // berkasnya memuat halaman penanda alih-alih lampiran sungguhan, jadi
        // ia dokumen setengah jadi yang terlihat resmi. Mencampurnya dengan
        // arsip unduhan berarti suatu hari ada yang mengirimkannya ke luar.
        $path = $this->pratinjau
            ? "berkas-seleksi/pratinjau/{$lamaran->Kode}-{$slug}-{$this->exportId}.pdf"
            : "berkas-seleksi/{$lamaran->Kode}-{$slug}-{$this->exportId}.pdf";

        Storage::disk('gcs')->put($path, $isi);

        DB::table('N_WEB_CAREERS_Export_Log')
            ->where('Id_Export', $this->exportId)
            ->update([
                'Status_Export' => 'SELESAI',
                // Status sambungan HCLearn ikut disimpan supaya layar bisa
                // memberitahu admin — lihat LaporanTesClient::ringkasStatus().
                // Ditaruh di kolom yang sudah ada; tidak ada kolom baru.
                'Keterangan' => json_encode(
                    ['hclearn' => LaporanTesClient::ringkasStatus()],
                    JSON_UNESCAPED_UNICODE,
                ),
                'File_Path' => $path,
                'File_Url' => Storage::disk('gcs')->url($path),
                'Progress_Chunk' => 1,
                'Progress_Total' => 1,
                'Completed_At' => now(),
            ]);

        Log::channel('web_career')->info(sprintf(
            '[BERKAS-SELEKSI%s] #%d selesai — %s (%s, %.1f detik)',
            $this->pratinjau ? '-PRATINJAU' : '',
            $this->exportId,
            $path,
            number_format(strlen($isi) / 1024, 1) . ' KB',
            microtime(true) - $mulai,
        ));
    }

    public function failed(\Throwable $e): void
    {
        Log::channel('web_career')->error("[BERKAS-SELEKSI] #{$this->exportId} gagal: " . $e->getMessage());

        DB::table('N_WEB_CAREERS_Export_Log')
            ->where('Id_Export', $this->exportId)
            ->update([
                'Status_Export' => 'GAGAL',
                'Error_Message' => mb_substr($e->getMessage(), 0, 500),
                'Completed_At' => now(),
            ]);
    }
}
