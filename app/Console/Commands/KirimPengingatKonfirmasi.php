<?php

namespace App\Console\Commands;

use App\Support\Career\PengingatKonfirmasi;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * PENGINGAT KONFIRMASI KEHADIRAN — kembaran pemicu Cloud Scheduler.
 *
 * Di produksi yang menekan tombolnya Cloud Scheduler lewat
 * POST /api/tugas/pengingat-konfirmasi (lihat PemicuTugasController). Perintah
 * ini menjalankan logika yang SAMA (PengingatKonfirmasi::jalankan) secara
 * langsung — untuk staging, uji, dan penerapan yang punya cron biasa.
 * Jalankan HANYA SALAH SATU: kalau cron dan Cloud Scheduler sama-sama hidup,
 * keduanya berebut slot yang sama (kunci unik buku pengingat mencegah surel
 * ganda, tapi tetap pemborosan).
 *
 *   php artisan karir:pengingat-konfirmasi                 slot yang sedang berlangsung
 *   php artisan karir:pengingat-konfirmasi --coba          tampilkan calon saja
 *   php artisan karir:pengingat-konfirmasi --slot=08 --coba
 *   php artisan karir:pengingat-konfirmasi --slot=12 --tanggal=2026-10-04
 */
class KirimPengingatKonfirmasi extends Command
{
    protected $signature = 'karir:pengingat-konfirmasi
        {--slot= : jam slot, mis. 08 — bawaan: slot yang sedang berlangsung}
        {--tanggal= : tanggal slot (Y-m-d) — bawaan: hari ini}
        {--maks= : paling banyak sekian kandidat (bawaan per_putaran di config)}
        {--coba : tampilkan calon penerima saja, jangan catat & jangan kirim}';

    protected $description = 'Kirim pengingat konfirmasi kehadiran ke kandidat yang belum menjawab (kembaran pemicu Cloud Scheduler).';

    public function handle(): int
    {
        if (! PengingatKonfirmasi::siap()) {
            $this->warn('Fitur belum aktif — jalankan docs/01-10-2026/01-konfirmasi-kehadiran.sql lalu docs/03-10-2026/01-pengingat-konfirmasi.sql.');

            return self::SUCCESS;
        }

        $coba = (bool) $this->option('coba');
        if (! $coba && ! PengingatKonfirmasi::aktif()) {
            $this->warn('Pengingat dinonaktifkan (KONFIRMASI_PENGINGAT_AKTIF=false). Pakai --coba untuk melihat calonnya.');

            return self::SUCCESS;
        }

        $jamDaftar = PengingatKonfirmasi::jamSlot();
        $jamTeks = implode(', ', array_map(fn ($j) => sprintf('%02d.00', $j), $jamDaftar));

        if ($this->option('slot') !== null) {
            if (! preg_match('/^\d{1,2}$/', (string) $this->option('slot')) || (int) $this->option('slot') > 23) {
                $this->error('--slot harus jam 0–23, mis. --slot=08.');

                return self::INVALID;
            }
            $jam = (int) $this->option('slot');
            $tanggal = (string) ($this->option('tanggal') ?: now()->toDateString());
            if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal) || ! strtotime($tanggal)) {
                $this->error('--tanggal harus berbentuk Y-m-d, mis. --tanggal=2026-10-04.');

                return self::INVALID;
            }
            if (! in_array($jam, $jamDaftar, true)) {
                $this->warn(sprintf('Jam %02d bukan slot terjadwal (%s WIB) — tetap dijalankan karena disebut eksplisit.', $jam, $jamTeks));
            }
        } else {
            $slot = PengingatKonfirmasi::slotSekarang();
            if (! $slot) {
                $this->info("Sekarang bukan jam pengingat ({$jamTeks} WIB). Untuk uji pakai --slot=08 [--coba].");

                return self::SUCCESS;
            }
            ['tanggal' => $tanggal, 'jam' => $jam] = $slot;
        }

        $maks = $this->option('maks') !== null ? max(1, (int) $this->option('maks')) : null;
        $hasil = PengingatKonfirmasi::jalankan($tanggal, $jam, $coba, $maks);

        if ($coba) {
            if (! $hasil['calon']) {
                $this->info("Slot {$hasil['slot']}: tidak ada kandidat yang perlu diingatkan.");

                return self::SUCCESS;
            }
            $this->table(
                ['CRM', 'Nama', 'Email', 'Aktivitas', 'Batas konfirmasi', 'Sisa', 'Ke'],
                array_map(fn ($c) => [
                    $c['crm'], $c['nama'], $c['email'], $c['aktivitas'],
                    Carbon::parse($c['batas'])->format('d M Y H.i'), $c['sisa'], $c['pengingatKe'],
                ], $hasil['calon']),
            );
            $this->info("Coba: {$hasil['dipilih']} kandidat akan diingatkan pada slot {$hasil['slot']}.");

            return self::SUCCESS;
        }

        $this->info("Slot {$hasil['slot']}: {$hasil['dipilih']} calon, {$hasil['diantrekan']} pengingat diantrekan.");
        foreach ($hasil['lewat'] as $crm => $alasan) {
            $this->line("  dilewati CRM #{$crm}: {$alasan}");
        }
        if ($hasil['sisa']) {
            $this->warn('Putaran penuh — jalankan lagi untuk kandidat berikutnya (job antrean melakukannya sendiri).');
        }

        return self::SUCCESS;
    }
}
