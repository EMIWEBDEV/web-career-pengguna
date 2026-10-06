<?php

namespace App\Console\Commands;

use App\Support\Career\LamaranService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * WEB CAREER — PULIHKAN TAHAP YANG TIDAK PUNYA SATU PUN AKTIVITAS.
 *
 * ── GEJALANYA ───────────────────────────────────────────────────────────────
 *
 * Kandidat berhenti di sebuah tahap dan TIDAK MUNCUL di mana pun: tidak di
 * Worklist rekruter, tidak di Penjadwalan. Kartunya ada di Monitoring dan
 * status lamarannya BERJALAN, tapi tak seorang pun bisa mengerjakannya.
 *
 * ── SEBABNYA ────────────────────────────────────────────────────────────────
 *
 * Worklist dan Penjadwalan sama-sama meng-INNER JOIN Lamaran_Tahap_Tes. Tahap
 * yang punya nol baris aktivitas otomatis tersaring keluar — bukan tampil
 * kosong, melainkan HILANG. Tidak ada galat, tidak ada baris log; papannya
 * sekadar tidak menyebut orang itu.
 *
 * Sumbernya VersiAlur: pemindahan kandidat ke alur baru menyisipkan baris
 * TAHAP tanpa membekukan aktivitasnya. Jalur itu sudah diperbaiki, tapi
 * kandidat yang telanjur dipindah sebelum perbaikan tetap terjebak — perintah
 * ini yang mengeluarkan mereka.
 *
 * ── AMAN ────────────────────────────────────────────────────────────────────
 *
 * Hanya MENAMBAH aktivitas untuk tahap yang benar-benar kosong; tahap yang
 * sudah punya aktivitas tidak disentuh sama sekali. Jalankan dengan --lihat
 * lebih dulu untuk melihat daftarnya tanpa mengubah apa pun.
 */
class TahapTanpaAktivitas extends Command
{
    protected $signature = 'career:tahap-tanpa-aktivitas
                            {--lihat : hanya tampilkan, jangan ubah apa pun}
                            {--semua : ikut perbaiki tahap yang BELUM dijalani, bukan cuma yang berjalan}';

    protected $description = 'Cari & pulihkan tahap lamaran yang tidak punya aktivitas (kandidat hilang dari Worklist)';

    public function handle(): int
    {
        $lihatSaja = (bool) $this->option('lihat');

        $q = DB::table('N_WEB_CAREERS_Lamaran as l')
            ->join('N_WEB_CAREERS_Lamaran_Tahap as t', 't.Lamaran_Id', '=', 'l.Id_Lamaran')
            ->leftJoin('N_WEB_CAREERS_Lamaran_Tahap_Tes as st', 'st.Lamaran_Tahap_Id', '=', 't.Id_Lamaran_Tahap')
            ->join('N_WEB_CAREERS_Users as u', 'u.Id_Users', '=', 'l.Id_Users')
            ->where('l.Status', 'BERJALAN')
            ->whereNull('st.Id_Lamaran_Tahap_Tes');

        // Bawaannya hanya tahap yang SEDANG dijalani — itulah yang membuat
        // kandidat hilang hari ini. Tahap BELUM yang kosong belum menyakiti
        // siapa pun, tapi akan menyakiti begitu kandidatnya sampai ke sana,
        // jadi --semua menambalnya sekalian.
        $q->when(! $this->option('semua'), fn ($w) => $w
            ->whereColumn('t.Urutan', 'l.Urutan_Tahap')
            ->where('t.Status', 'BERJALAN'));

        $baris = $q->orderBy('l.Kode')->orderBy('t.Urutan')
            ->get(['l.Kode', 'u.Nama', 't.Id_Lamaran_Tahap', 't.Urutan', 't.Kode as tahap', 't.Status']);

        if ($baris->isEmpty()) {
            $this->info('Tidak ada tahap tanpa aktivitas. Semua kandidat terlihat di papan.');

            return self::SUCCESS;
        }

        $this->warn($baris->count().' tahap tanpa aktivitas ditemukan:');
        $this->table(
            ['Lamaran', 'Nama', 'Urut', 'Tahap', 'Status'],
            $baris->map(fn ($r) => [$r->Kode, $r->Nama, $r->Urutan, $r->tahap, $r->Status])->all()
        );

        if ($lihatSaja) {
            $this->line('');
            $this->comment('--lihat aktif: tidak ada yang diubah. Jalankan tanpa --lihat untuk memperbaiki.');

            return self::SUCCESS;
        }

        $svc = new LamaranService();
        $pulih = 0;
        $gagal = 0;

        foreach ($baris as $r) {
            try {
                $svc->pastikanSubTes((int) $r->Id_Lamaran_Tahap);

                $n = DB::table('N_WEB_CAREERS_Lamaran_Tahap_Tes')
                    ->where('Lamaran_Tahap_Id', $r->Id_Lamaran_Tahap)->count();

                if ($n > 0) {
                    $pulih++;
                    $this->line("  <info>✓</info> {$r->Kode} · {$r->tahap} → {$n} aktivitas");
                } else {
                    $gagal++;
                    $this->line("  <comment>!</comment> {$r->Kode} · {$r->tahap} → masih kosong");
                }
            } catch (\Throwable $e) {
                $gagal++;
                $this->line("  <error>✗</error> {$r->Kode} · {$r->tahap} → ".$e->getMessage());
            }
        }

        $this->line('');
        $this->info("Selesai: {$pulih} tahap dipulihkan".($gagal ? ", {$gagal} perlu diperiksa manual" : '.'));

        return $gagal ? self::FAILURE : self::SUCCESS;
    }
}
