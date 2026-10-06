<?php

namespace App\Console\Commands;

use App\Jobs\Career\WcPengingatBatasJob;
use App\Support\Career\BatasIsi;
use App\Support\Career\LamaranService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * PENGINGAT H-1 BATAS PENGISIAN FORMULIR TAHAP — dijalankan penjadwal.
 *
 * ══ DICICIL, BUKAN SERENTAK ═══════════════════════════════════════════════
 *
 * Satu program bisa memberi 500 kandidat tanggal batas yang SAMA, jadi 500
 * orang itu masuk jendela "tinggal sehari" pada detik yang sama. Mengirim
 * semuanya sekaligus adalah pola yang dibaca penyedia surat sebagai pengiriman
 * massal mendadak — alasan yang sama dengan batas Putuskan Massal. Karena itu
 * tiap putaran hanya mengirim --maks surat (bawaan 20, penjadwal tiap 15
 * menit = 80 per jam), yang batasnya paling dekat lebih dulu.
 *
 * Tiap tahap ditandai (Batas_Pengingat_At) begitu dijadwalkan, jadi satu batas
 * tidak pernah diingatkan dua kali. Batas yang diubah admin mengosongkan
 * penandanya lagi (BatasIsi), supaya tanggal barunya ikut diingatkan.
 *
 * Yang dilewati: sudah mengirim formulir, sedang ditahan (hold), lamaran atau
 * tahapnya tidak berjalan, dan batas yang sudah lewat.
 *
 *   php artisan karir:pengingat-batas            kirim (maks 20)
 *   php artisan karir:pengingat-batas --coba     tampilkan saja
 */
class PengingatBatasIsi extends Command
{
    protected $signature = 'karir:pengingat-batas
        {--maks=20 : paling banyak sekian surat per putaran}
        {--jam=24 : ingatkan bila sisa waktunya kurang dari sekian jam}
        {--coba : tampilkan saja, jangan kirim & jangan tandai}';

    protected $description = 'Kirim pengingat H-1 batas pengisian formulir tahap Web Careers (dicicil).';

    public function handle(): int
    {
        if (! BatasIsi::siap()) {
            $this->warn('Fitur batas pengisian belum aktif — skrip docs/28-09-2026/04 belum dijalankan.');

            return self::SUCCESS;
        }

        $sekarang = now();
        $maks = max(1, min(200, (int) $this->option('maks')));
        $jam = max(1, min(72, (int) $this->option('jam')));
        $coba = (bool) $this->option('coba');

        $baris = DB::table('N_WEB_CAREERS_Lamaran_Tahap as t')
            ->join('N_WEB_CAREERS_Lamaran as l', 'l.Id_Lamaran', '=', 't.Lamaran_Id')
            ->leftJoin('N_WEB_CAREERS_Users as u', 'u.Id_Users', '=', 'l.Id_Users')
            ->leftJoin('N_WEB_CAREERS_Program as p', 'p.Id_Program', '=', 'l.Program_Id')
            ->leftJoin('N_WEB_CAREERS_Program_Posisi as x', 'x.Id_Program_Posisi', '=', 'l.Program_Posisi_Id')
            ->where('l.Status', 'BERJALAN')
            ->where('t.Status', 'BERJALAN')
            ->whereNotNull('t.Formulir_Kode')
            ->whereNull('t.Formulir_Pengisian_Id')
            ->whereNotNull('t.Batas_At')
            ->whereNull('t.Batas_Pengingat_At')
            ->where('t.Batas_At', '>', $sekarang)
            ->where('t.Batas_At', '<=', $sekarang->copy()->addHours($jam))
            // Formulir yang belum dibuka tidak diingatkan — belum ada yang bisa diisi.
            ->where(fn ($w) => $w->whereNull('t.Buka_At')->orWhere('t.Buka_At', '<=', $sekarang))
            ->where(fn ($w) => $w->whereNull('t.Hold_Flag')->orWhere('t.Hold_Flag', '<>', 'Y'))
            ->orderBy('t.Batas_At')
            ->limit($maks)
            ->get(['t.Id_Lamaran_Tahap', 't.Label', 't.Batas_At', 'l.Id_Lamaran', 'l.Kode', 'l.Id_Users',
                'u.Nama', 'p.Nama as Program', 'x.Posisi']);

        if ($baris->isEmpty()) {
            $this->info('Tidak ada batas yang perlu diingatkan.');

            return self::SUCCESS;
        }

        $terbit = 0;
        foreach ($baris as $r) {
            $email = LamaranService::emailKandidat((int) $r->Id_Users, (int) $r->Id_Lamaran);
            $sisaJam = (int) floor($sekarang->diffInMinutes(\Illuminate\Support\Carbon::parse($r->Batas_At)) / 60);
            $sisa = $sisaJam < 1 ? 'kurang dari 1 jam lagi' : "sekitar {$sisaJam} jam lagi";

            $this->line(sprintf('  %s  %-28s %s  → %s', $r->Kode, mb_substr((string) $r->Label, 0, 28), BatasIsi::teks($r->Batas_At), $email ?: '(tanpa email)'));

            if ($coba) {
                continue;
            }

            // Ditandai LEBIH DULU, dengan syarat masih kosong: dua penjadwal yang
            // kebetulan berjalan bersamaan tidak akan sama-sama mengirim.
            $milikKu = DB::table('N_WEB_CAREERS_Lamaran_Tahap')
                ->where('Id_Lamaran_Tahap', $r->Id_Lamaran_Tahap)
                ->whereNull('Batas_Pengingat_At')
                ->update(['Batas_Pengingat_At' => $sekarang]);

            if (! $milikKu || ! $email) {
                continue;
            }

            WcPengingatBatasJob::dispatch((int) $r->Id_Users, [
                'email' => $email,
                'nama' => $r->Nama,
                'tahap' => $r->Label,
                'batas_teks' => BatasIsi::teks($r->Batas_At),
                'sisa_teks' => $sisa,
                'posisi' => $r->Posisi,
                'program' => $r->Program,
                'kode' => $r->Kode,
            ]);
            $terbit++;
        }

        $this->info($coba ? "Coba: {$baris->count()} kandidat akan diingatkan." : "{$terbit} pengingat dijadwalkan.");

        return self::SUCCESS;
    }
}
