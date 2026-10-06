<?php

namespace App\Console\Commands;

use App\Support\Career\PencarianCepat;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * WEB CAREER — MENYIAPKAN JALUR CEPAT PENCARIAN NAMA.
 *
 * Membuat dua hal di SQL Server:
 *
 *   1. INDEKS PENUTUP (covering index) untuk cascade kampus. Query autocomplete
 *      menyaring Jenis_Institusi_Kode lalu mengurutkan Nama; dengan indeks ini
 *      SQL Server cukup membaca indeksnya saja — tanpa balik ke tabel.
 *
 *   2. INDEKS FULL-TEXT pada kolom Nama. Ini yang menghapus `LIKE '%kata%'`:
 *      pencarian jadi lewat indeks kata, sehingga "negeri jakarta" tetap
 *      menemukan "Universitas Negeri Jakarta" tanpa memindai 328 ribu baris.
 *
 * Aman diulang — yang sudah ada dilewati.
 *
 *   php artisan career:optimasi-pencarian
 *   php artisan career:optimasi-pencarian --ukur   (ukur sebelum/sesudah)
 */
class OptimasiPencarian extends Command
{
    protected $signature = 'career:optimasi-pencarian {--ukur : Tampilkan pengukuran waktu query}';

    protected $description = 'Buat indeks penutup & full-text agar pencarian kampus/prodi tidak memindai tabel';

    private const KATALOG = 'WC_Katalog_Pencarian';

    /** Tabel & kolom yang dicari pengguna. */
    private const SASARAN = [
        ['tabel' => 'N_WEB_CAREERS_Master_Kampus', 'kolom' => 'Nama'],
        ['tabel' => 'N_WEB_CAREERS_Master_Prodi', 'kolom' => 'Nama'],
    ];

    public function handle(): int
    {
        try {
            if (DB::getDriverName() !== 'sqlsrv') {
                $this->warn('Perintah ini khusus SQL Server. Dilewati.');

                return self::SUCCESS;
            }

            if ($this->option('ukur')) {
                $this->ukur('SEBELUM');
            }

            $this->indeksPenutup();
            $this->fullText();

            foreach (self::SASARAN as $s) {
                PencarianCepat::lupakan($s['tabel'], $s['kolom']);
            }

            if ($this->option('ukur')) {
                $this->ukur('SESUDAH');
            }

            Log::channel('web_career')->info('[OPTIMASI] Jalur cepat pencarian disiapkan.');

            return self::SUCCESS;
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('[OPTIMASI] Gagal: ' . $e->getMessage());
            $this->error('Gagal: ' . $e->getMessage());

            return self::FAILURE;
        }
    }

    private function adaIndeks(string $tabel, string $nama): bool
    {
        return (int) DB::selectOne(
            'SELECT COUNT(*) n FROM sys.indexes WHERE object_id = OBJECT_ID(?) AND name = ?',
            [$tabel, $nama]
        )->n > 0;
    }

    private function indeksPenutup(): void
    {
        $this->line('  <options=bold>Indeks penutup</>');

        // Cascade kampus: WHERE Jenis_Institusi_Kode = ? ORDER BY Nama.
        // Kolom lain ikut disertakan (INCLUDE) supaya tidak perlu lookup balik.
        if ($this->adaIndeks('N_WEB_CAREERS_Master_Kampus', 'IX_MK_Cascade')) {
            $this->line('    IX_MK_Cascade  sudah ada');
        } else {
            $this->line('    IX_MK_Cascade  membuat…');
            DB::statement('
                CREATE INDEX IX_MK_Cascade
                ON N_WEB_CAREERS_Master_Kampus (Jenis_Institusi_Kode, Flag_Aktif, Nama)
                INCLUDE (Kode, Kota, Provinsi, Negara, Negara_Kode, Kepemilikan, Akreditasi)');
            $this->line('    IX_MK_Cascade  <fg=green>dibuat</>');
        }

        // Prodi: WHERE Cakupan = ? ORDER BY Nama, sering di-join ke Prodi_Jenjang.
        if ($this->adaIndeks('N_WEB_CAREERS_Master_Prodi', 'IX_MPR_Cascade')) {
            $this->line('    IX_MPR_Cascade sudah ada');
        } else {
            $this->line('    IX_MPR_Cascade membuat…');
            DB::statement('
                CREATE INDEX IX_MPR_Cascade
                ON N_WEB_CAREERS_Master_Prodi (Cakupan, Flag_Aktif, Nama)
                INCLUDE (Kode, Nama_En, Bidang_Kode, Gelar, Kelompok)');
            $this->line('    IX_MPR_Cascade <fg=green>dibuat</>');
        }
    }

    private function fullText(): void
    {
        $this->newLine();
        $this->line('  <options=bold>Full-text</>');

        $terpasang = (int) DB::selectOne("SELECT SERVERPROPERTY('IsFullTextInstalled') v")->v;
        if (! $terpasang) {
            $this->warn('    Full-Text Search tidak terpasang di server ini.');
            $this->line('    Pencarian tetap jalan memakai pencocokan awalan (masih memakai indeks).');

            return;
        }

        $adaKatalog = (int) DB::selectOne(
            'SELECT COUNT(*) n FROM sys.fulltext_catalogs WHERE name = ?', [self::KATALOG]
        )->n > 0;

        if (! $adaKatalog) {
            DB::statement('CREATE FULLTEXT CATALOG ' . self::KATALOG);
            $this->line('    katalog ' . self::KATALOG . ' <fg=green>dibuat</>');
        } else {
            $this->line('    katalog ' . self::KATALOG . ' sudah ada');
        }

        foreach (self::SASARAN as $s) {
            $this->indeksFullText($s['tabel'], $s['kolom']);
        }
    }

    private function indeksFullText(string $tabel, string $kolom): void
    {
        $sudah = (int) DB::selectOne(
            'SELECT COUNT(*) n FROM sys.fulltext_indexes WHERE object_id = OBJECT_ID(?)', [$tabel]
        )->n > 0;

        if ($sudah) {
            $this->line("    {$tabel}.{$kolom} sudah punya indeks full-text");

            return;
        }

        // Full-text wajib bersandar pada satu indeks UNIK berkolom tunggal
        // yang tidak boleh NULL — di sini indeks unik pada kolom Kode.
        $kunci = DB::selectOne('
            SELECT TOP 1 i.name
            FROM sys.indexes i
            WHERE i.object_id = OBJECT_ID(?) AND i.is_unique = 1 AND i.is_disabled = 0
              AND (SELECT COUNT(*) FROM sys.index_columns ic
                   WHERE ic.object_id = i.object_id AND ic.index_id = i.index_id) = 1
            ORDER BY i.is_primary_key DESC', [$tabel]);

        if (! $kunci) {
            $this->warn("    {$tabel}: tidak ada indeks unik berkolom tunggal — full-text dilewati.");

            return;
        }

        // STOPLIST OFF: nama sekolah banyak memuat kata pendek & angka yang
        // pada daftar bawaan dianggap kata umum lalu dibuang.
        DB::statement("
            CREATE FULLTEXT INDEX ON {$tabel} ({$kolom})
            KEY INDEX {$kunci->name}
            ON " . self::KATALOG . '
            WITH STOPLIST = OFF, CHANGE_TRACKING AUTO');

        $this->line("    {$tabel}.{$kolom} <fg=green>dibuat</> (kunci: {$kunci->name})");
        $this->line('      <fg=gray>pengindeksan berjalan di latar belakang</>');
    }

    private function ukur(string $tahap): void
    {
        $this->newLine();
        $this->line("  <options=bold>Pengukuran {$tahap}</>");

        $uji = [
            ['Universitas', 'negeri'],
            ['SD', 'sukamaju'],
            ['SMK', 'negeri 1'],
        ];

        foreach ($uji as [$jenis, $q]) {
            $mulai = microtime(true);
            $query = DB::table('N_WEB_CAREERS_Master_Kampus')
                ->where('Jenis_Institusi_Kode', $jenis)
                ->where('Flag_Aktif', 'Y');
            PencarianCepat::terapkan($query, 'N_WEB_CAREERS_Master_Kampus', 'Nama', $q);
            // Kolom dibatasi persis seperti di controller: kalau di sini pakai
            // get() polos (SELECT *), pengukurannya menyesatkan karena indeks
            // penutup jadi tak terpakai.
            $n = $query->orderBy('Nama')->limit(30)
                ->get(['Nama', 'Kota', 'Provinsi', 'Negara', 'Negara_Kode', 'Kepemilikan', 'Akreditasi'])
                ->count();
            $ms = (microtime(true) - $mulai) * 1000;

            $warna = $ms < 50 ? 'green' : ($ms < 300 ? 'yellow' : 'red');
            $this->line(sprintf('    %-12s "%-10s" → %2d baris, <fg=%s>%6.0f ms</>', $jenis, $q, $n, $warna, $ms));
        }
    }
}
