<?php

namespace App\Console\Commands;

use App\Services\WebCareers\KalenderHcisClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * WEB CAREERS — tarik & periksa kalender operasional HO dari HCIS.
 *
 * Dua kegunaan:
 *
 *   1. DIJADWALKAN (harian) — menyegarkan singgahan supaya perhitungan SLA
 *      tidak pernah menunggu jaringan. Kalender jarang berubah; sekali sehari
 *      sudah lebih dari cukup.
 *
 *   2. DIPANGGIL MANUSIA — memeriksa sambungan ke HCIS tanpa perlu membuka
 *      MPP. Selama endpoint HCIS belum ada, ini juga cara memastikan berkas
 *      dummy-nya terbaca benar.
 */
class SinkronKalenderHcis extends Command
{
    protected $signature = 'hcis:kalender
        {--tahun=* : Tahun yang disegarkan (bawaan: tahun ini & tahun depan)}
        {--lihat : Tampilkan daftar tanggalnya, bukan hanya jumlahnya}
        {--segar : Buang singgahan lebih dulu, paksa ambil ulang}';

    protected $description = 'Tarik kalender operasional Head Office dari HCIS (atau berkas dummy).';

    public function handle(): int
    {
        $driver = (string) config('hcis.kalender.driver', 'mati');
        $unit = (string) config('hcis.kalender.unit', 'HEAD_OFFICE');

        // ── KANAL DIMATIKAN: BERHENTI DI SINI, DENGAN TERANG ──────────────
        //
        // Berhenti diam-diam akan membuat perintah ini melaporkan "0 tanggal"
        // — keluaran yang sama persis dengan HCIS yang sedang bermasalah.
        // Yang menjalankannya lalu mengira sambungannya rusak, dan mencari
        // kesalahan yang tidak ada.
        if (\App\Services\WebCareers\KalenderHcisClient::dimatikan()) {
            $this->line('');
            $this->warn('  Kanal HCIS sedang DIMATIKAN (HCIS_KALENDER_DRIVER=mati).');
            $this->line('  SLA dihitung dari tabel HRIS_Hari_Libur, bukan dari HCIS.');
            $this->line('  Tidak ada panggilan jaringan yang dibuat.');
            $this->line('');
            $this->line('  Untuk menyalakannya: setel HCIS_KALENDER_DRIVER=http di .env');
            $this->line('  beserta HCIS_WC_API_PUBLIC & HCIS_WC_API_SECRET.');
            $this->line('');

            return self::SUCCESS;
        }

        $this->line('');
        $this->line("  driver : <options=bold>{$driver}</> ".($driver === 'dummy'
            ? '<fg=yellow>(berkas lokal — endpoint HCIS belum ada)</>'
            : '<fg=green>(HCIS sungguhan)</>'));
        $this->line("  env    : ".config('hcis.env'));
        $this->line("  unit   : {$unit}");

        if ($driver === 'http') {
            $this->line('  domain : '.($this->domainAman() ?? '<fg=red>belum terisi</>'));
        }

        // Tahun ini DAN tahun depan: MPP yang dibuat Desember dengan SLA 90
        // hari kerja tenggatnya jatuh di tahun berikutnya, dan tanpa kalender
        // tahun itu seluruh liburnya luput dari hitungan.
        $tahunList = $this->option('tahun') ?: [now()->year, now()->year + 1];
        $adaGagal = false;

        $this->line('');

        foreach ($tahunList as $tahun) {
            $tahun = (int) $tahun;

            if ($this->option('segar')) {
                Cache::forget("hcis:kalender:{$unit}:{$tahun}");
            }

            $daftar = KalenderHcisClient::tahun($tahun);

            if ($daftar === []) {
                // Bukan selalu kegagalan: tahun yang berkas dummy-nya belum
                // dibuat memang kosong. Tapi tetap ditandai, karena kalender
                // kosong berarti SLA dihitung tanpa satu pun hari libur —
                // tenggatnya jadi lebih ketat daripada seharusnya.
                $this->warn("  {$tahun}: tidak ada tanggal libur. SLA akan dihitung TANPA hari libur.");
                $adaGagal = true;

                continue;
            }

            $libur = collect($daftar)->where('jenis', 'LIBUR_NASIONAL')->count();
            $cuti = collect($daftar)->where('jenis', 'CUTI_BERSAMA')->count();

            $this->info("  {$tahun}: ".count($daftar)." tanggal  ({$libur} libur nasional, {$cuti} cuti bersama)");

            if ($this->option('lihat')) {
                foreach ($daftar as $tgl => $b) {
                    $this->line(sprintf('      %s  %-16s %s', $tgl, $b['jenis'], $b['nama']));
                }
            }
        }

        $this->line('');

        return $adaGagal ? self::FAILURE : self::SUCCESS;
    }

    /** Domain aktif — tanpa membuat perintah ini gagal bila env-nya salah. */
    private function domainAman(): ?string
    {
        $semua = (array) config('hcis.domains', []);

        return $semua[trim((string) config('hcis.env'))] ?? null;
    }
}
