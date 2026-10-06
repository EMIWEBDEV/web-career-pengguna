<?php

namespace App\Console\Commands;

use App\Jobs\Career\WcJadwalEmailJob;
use App\Support\Career\JadwalPrivat;
use App\Support\Career\JejakJadwal;
use App\Support\Career\Skema;
use App\Support\Career\UndanganJadwal;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * PENGINGAT MENJELANG BATAS UNGGAH (MCU mandiri) — dijalankan penjadwal.
 *
 * Pada mode ber-Flag_Unggah_Kandidat, kandidat sendiri yang memilih kapan dan
 * di mana ia diperiksa, lalu mengunggah hasilnya sebelum batas unggahnya (akhir
 * rentang, atau tanggal perpanjangan dari tim — UndanganJadwal::batasUnggah). Tanpa
 * pengingat, yang lupa baru ketahuan SESUDAH batasnya lewat — dan yang
 * mengejarnya satu per satu adalah rekruter. Surat ini menggantikan telepon itu.
 *
 * VENDOR tidak diingatkan: hasilnya datang ke tim, sistem tidak tahu apakah
 * kandidat sudah datang, dan "segera periksakan diri" untuk orang yang sudah
 * diperiksa kemarin hanya membingungkan. Tim bisa mengirim ulang informasinya
 * sendiri lewat tombol "Kirim ulang email".
 *
 * ══ SEKALI PER JADWAL ══
 *
 * Tiap pengingat tercatat sebagai baris jejak (Aksi PENGINGAT). Yang sudah
 * diingatkan SESUDAH jadwalnya terakhir disimpan (Jadwal_At) dilewati — jadi
 * batas yang diperpanjang admin ikut diingatkan lagi, tapi satu batas tidak
 * pernah diingatkan dua kali. Jejaknya ditulis LEBIH DULU, baru suratnya
 * diantrekan: dua penjadwal yang kebetulan berjalan bersamaan tidak akan
 * sama-sama mengirim (penjadwal juga sudah withoutOverlapping + onOneServer).
 *
 * ══ DICICIL, BUKAN SERENTAK ══
 *
 * Satu angkatan bisa berbagi batas yang sama. Tiap putaran hanya mengirim
 * --maks surat (bawaan 20; penjadwal tiap 15 menit), yang batasnya paling dekat
 * lebih dulu — alasan yang sama dengan karir:pengingat-batas.
 *
 * Yang dilewati: sudah dicatat hasilnya / kehadirannya, sudah mengirim
 * berkasnya, lamaran atau tahapnya tidak berjalan, sedang ditahan, batas yang
 * sudah lewat, dan tipe berjadwal privat.
 *
 *   php artisan karir:pengingat-jadwal            kirim (maks 20)
 *   php artisan karir:pengingat-jadwal --coba     tampilkan saja
 */
class PengingatJadwal extends Command
{
    protected $signature = 'karir:pengingat-jadwal
        {--maks=20 : paling banyak sekian surat per putaran}
        {--jam=48 : ingatkan bila sisa waktunya kurang dari sekian jam}
        {--coba : tampilkan saja, jangan kirim & jangan catat}';

    protected $description = 'Kirim pengingat menjelang batas aktivitas berbatas waktu Web Careers (MCU mandiri), dicicil.';

    public function handle(): int
    {
        if (! JejakJadwal::siap() || ! Skema::adaKolom('N_WEB_CAREERS_Master_Mode_Jadwal', 'Flag_Batas_Waktu')) {
            $this->warn('Fitur belum aktif — skrip docs/30-09-2026/01-mcu-mandiri-dan-biaya.sql belum dijalankan.');

            return self::SUCCESS;
        }

        $modeBerbatas = DB::table('N_WEB_CAREERS_Master_Mode_Jadwal')
            ->where('Flag_Batas_Waktu', 'Y')
            // Hanya yang kandidatnya mengunggah sendiri — lihat kepala kelas.
            ->when(
                Skema::adaKolom('N_WEB_CAREERS_Master_Mode_Jadwal', 'Flag_Unggah_Kandidat'),
                fn ($q) => $q->where('Flag_Unggah_Kandidat', 'Y'),
            )
            ->pluck('Kode')
            ->all();

        if (! $modeBerbatas) {
            $this->info('Tidak ada mode jadwal berbatas waktu.');

            return self::SUCCESS;
        }

        $sekarang = now();
        $maks = max(1, min(200, (int) $this->option('maks')));
        $jam = max(1, min(168, (int) $this->option('jam')));
        $coba = (bool) $this->option('coba');

        // Batas yang BERLAKU: perpanjangan batas unggah bila lebih lambat dari
        // akhir rentang — sama dengan UndanganJadwal::batasUnggah().
        $batasSql = UndanganJadwal::siapBatasUnggah()
            ? 'CASE WHEN t.Jadwal_Batas_Unggah > t.Jadwal_Selesai THEN t.Jadwal_Batas_Unggah ELSE t.Jadwal_Selesai END'
            : 't.Jadwal_Selesai';

        $baris = DB::table('N_WEB_CAREERS_Lamaran_Tahap_Tes as t')
            ->join('N_WEB_CAREERS_Lamaran_Tahap as h', 'h.Id_Lamaran_Tahap', '=', 't.Lamaran_Tahap_Id')
            ->join('N_WEB_CAREERS_Lamaran as l', 'l.Id_Lamaran', '=', 'h.Lamaran_Id')
            ->whereIn('t.Jadwal_Mode', $modeBerbatas)
            ->where(fn ($w) => $w->whereNull('t.Flag_Selesai')->orWhere('t.Flag_Selesai', '<>', 'Y'))
            ->whereNull('t.Jadwal_Hadir')
            ->whereNull('t.Unggah_Kirim_At')
            ->whereNotNull('t.Jadwal_Selesai')
            ->whereRaw("({$batasSql}) > ?", [$sekarang])
            ->whereRaw("({$batasSql}) <= ?", [$sekarang->copy()->addHours($jam)])
            ->where('l.Status', 'BERJALAN')
            ->where('h.Status', 'BERJALAN')
            ->where(fn ($w) => $w->whereNull('h.Hold_Flag')->orWhere('h.Hold_Flag', '<>', 'Y'))
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))
                ->from(JejakJadwal::TABEL.' as j')
                ->whereColumn('j.Lamaran_Tahap_Tes_Id', 't.Id_Lamaran_Tahap_Tes')
                ->where('j.Aksi', JejakJadwal::PENGINGAT)
                // Jadwal_At kosong (jadwal sangat lama) jatuh ke Jadwal_Mulai —
                // tanpa itu perbandingannya NULL dan pengingat terkirim tiap putaran.
                ->whereRaw('j.Created_At >= COALESCE(t.Jadwal_At, t.Jadwal_Mulai)'))
            ->orderByRaw($batasSql)
            ->limit($maks)
            ->get(['t.Id_Lamaran_Tahap_Tes', 't.Label', DB::raw("({$batasSql}) as Batas"), 't.Tipe_Tahap_Kode', 'h.Tipe_Tahap_Kode as TahapTipe', 'l.Kode']);

        if ($baris->isEmpty()) {
            $this->info('Tidak ada batas yang perlu diingatkan.');

            return self::SUCCESS;
        }

        $terbit = 0;
        foreach ($baris as $r) {
            $batas = Carbon::parse($r->Batas);
            $sisa = UndanganJadwal::sisaTeks($sekarang, $batas);

            $this->line(sprintf('  %s  %-28s %s  (%s)', $r->Kode, mb_substr((string) $r->Label, 0, 28), UndanganJadwal::teksBatas($batas), $sisa));

            if ($coba) {
                continue;
            }

            // Jadwal privat tidak pernah diumumkan — apalagi diingatkan.
            if (JadwalPrivat::untuk($r->Tipe_Tahap_Kode ?: $r->TahapTipe)) {
                continue;
            }

            $muatan = UndanganJadwal::muatan((int) $r->Id_Lamaran_Tahap_Tes);
            if (! $muatan || empty($muatan['data']['email'])) {
                continue;
            }

            // DICATAT LEBIH DULU — lihat penjelasan di kepala kelas.
            $jejakId = JejakJadwal::catat((int) $r->Id_Lamaran_Tahap_Tes, JejakJadwal::PENGINGAT);
            if (! $jejakId) {
                continue;
            }

            WcJadwalEmailJob::dispatch($muatan['userId'], $muatan['data'] + [
                'pengingat' => true,
                'sisa_teks' => $sisa,
            ]);
            JejakJadwal::tandaiUndangan($jejakId, 'terkirim');
            $terbit++;
        }

        $this->info($coba ? "Coba: {$baris->count()} kandidat akan diingatkan." : "{$terbit} pengingat dijadwalkan.");

        return self::SUCCESS;
    }
}
