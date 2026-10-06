<?php

namespace App\Console\Commands;

use App\Support\Career\JejakJadwal;
use App\Support\Career\KonfirmasiJadwal;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Antrekan ulang SUREL KONFIRMASI KEHADIRAN yang gagal (antrean wc-konfirmasimail).
 *
 * Dipakai sesudah gangguan server surat (SMTP ditangguhkan, kredensial salah,
 * EVO Mail mati): baris jejak berstatus GAGAL dikembalikan ke ANTRE lalu
 * dikirim lagi oleh WcKonfirmasiEmailJob.
 *
 * AMAN DIULANG: isi surel dirakit ulang saat job berjalan, dari data terkini.
 * Surel yang sudah basi (jadwal diganti, kandidat sudah menjawab, lamaran
 * ditutup) otomatis ditandai LEWAT dan tidak berangkat. Kunci idempotensinya
 * tetap "wc-jejak-{id}", jadi EVO Mail tidak menggandakan surel yang ternyata
 * sempat terkirim.
 *
 * Undangan jadwal (antrean wc-applymail) BUKAN urusan perintah ini — kirim
 * ulang lewat tombol "Kirim ulang" di worklist, atau php artisan queue:retry
 * <uuid> untuk job tertentu (jangan "queue:retry all": tabel job gagal dipakai
 * bersama aplikasi lain).
 */
class KirimUlangKonfirmasi extends Command
{
    protected $signature = 'karir:konfirmasi-kirim-ulang
        {--jam=48 : Hanya jejak yang dibuat sekian jam terakhir}
        {--coba : Tampilkan saja, jangan antrekan apa pun}';

    protected $description = 'Antrekan ulang surel konfirmasi kehadiran yang GAGAL terkirim';

    public function handle(): int
    {
        if (! KonfirmasiJadwal::siap()) {
            $this->error('Skema konfirmasi belum dipasang.');

            return self::FAILURE;
        }

        $jam = max(1, (int) $this->option('jam'));
        $rows = DB::table(JejakJadwal::TABEL)
            ->where('Undangan', 'GAGAL')
            ->whereNotNull('Data_Json')
            ->where('Created_At', '>=', now()->subHours($jam))
            ->orderBy('Id_Jadwal_Jejak')
            ->get(['Id_Jadwal_Jejak', 'Lamaran_Tahap_Tes_Id', 'Aksi', 'Versi', 'Data_Json', 'Created_At']);

        $ids = $rows->filter(fn ($r) => ! empty(json_decode((string) $r->Data_Json, true)['surel'] ?? null))
            ->pluck('Id_Jadwal_Jejak')->map(fn ($v) => (int) $v)->all();

        if (! $ids) {
            $this->info("Tidak ada surel konfirmasi GAGAL dalam {$jam} jam terakhir.");

            return self::SUCCESS;
        }

        $this->table(['Jejak', 'Aktivitas', 'Aksi', 'Versi', 'Surel', 'Dibuat'], $rows->whereIn('Id_Jadwal_Jejak', $ids)->map(fn ($r) => [
            $r->Id_Jadwal_Jejak, $r->Lamaran_Tahap_Tes_Id, $r->Aksi, $r->Versi,
            json_decode((string) $r->Data_Json, true)['surel'] ?? '-', (string) $r->Created_At,
        ])->all());

        if ($this->option('coba')) {
            $this->warn(count($ids).' surel AKAN diantrekan ulang (mode --coba: tidak ada yang diubah).');

            return self::SUCCESS;
        }

        DB::table(JejakJadwal::TABEL)->whereIn('Id_Jadwal_Jejak', $ids)->update(['Undangan' => 'ANTRE']);
        // Dicicil beberapa detik — sama dengan pengingat massal.
        foreach (array_chunk($ids, 1) as $i => $satu) {
            KonfirmasiJadwal::antrekan($satu, $i * max(0, (int) config('konfirmasi.cicil_detik', 2)));
        }

        $this->info(count($ids).' surel konfirmasi diantrekan ulang. Jalankan worker: php artisan queue:work --stop-when-empty');

        return self::SUCCESS;
    }
}
