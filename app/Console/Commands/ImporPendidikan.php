<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * WEB CAREER — IMPOR MASTER BIDANG ILMU & PROGRAM STUDI.
 *
 * Membaca berkas di database/data/pendidikan/ lalu mengisi:
 *   N_WEB_CAREERS_Master_Jenis_Institusi  melengkapi jenis yang dipakai data
 *                                         kampus tapi belum terdaftar
 *   N_WEB_CAREERS_Master_Bidang_Ilmu   rumpun/fakultas (ISCED-F 2013, UNESCO)
 *   N_WEB_CAREERS_Master_Prodi         nama prodi/jurusan
 *   N_WEB_CAREERS_Prodi_Jenjang        prodi berlaku di jenjang mana
 *
 * AMAN DIULANG: baris dicocokkan dengan Kode, jadi menjalankan ulang hanya
 * memperbarui yang berubah — tidak menggandakan. Baris yang diubah admin lewat
 * UI tidak ditimpa kecuali dijalankan dengan --paksa.
 *
 * Contoh:
 *   php artisan career:impor-pendidikan
 *   php artisan career:impor-pendidikan --hanya=bidang
 *   php artisan career:impor-pendidikan --paksa
 */
class ImporPendidikan extends Command
{
    protected $signature = 'career:impor-pendidikan
        {--hanya= : Batasi ke satu bagian: jenis|bidang|prodi|jenjang}
        {--paksa : Timpa juga baris yang sudah disunting admin}';

    protected $description = 'Impor master bidang ilmu (ISCED) & program studi dari database/data/pendidikan';

    private const T_BIDANG = 'N_WEB_CAREERS_Master_Bidang_Ilmu';

    private const T_PRODI = 'N_WEB_CAREERS_Master_Prodi';

    private const T_PJ = 'N_WEB_CAREERS_Prodi_Jenjang';

    private const T_JI = 'N_WEB_CAREERS_Master_Jenis_Institusi';

    private const T_JIJ = 'N_WEB_CAREERS_Jenis_Institusi_Jenjang';

    public function handle(): int
    {
        $dir = database_path('data/pendidikan');
        if (! is_dir($dir)) {
            $this->error("Folder data tidak ada: {$dir}");

            return self::FAILURE;
        }

        try {
            $this->siapkanTabel();

            $hanya = (string) $this->option('hanya');
            $paksa = (bool) $this->option('paksa');

            if ($hanya === '' || $hanya === 'jenis') {
                $this->imporJenisInstitusi("{$dir}/jenis-institusi.csv");
            }
            if ($hanya === '' || $hanya === 'bidang') {
                $this->imporBidang("{$dir}/bidang-ilmu.csv", $paksa);
            }
            if ($hanya === '' || $hanya === 'prodi') {
                $this->imporProdi("{$dir}/prodi.csv", $paksa);
            }
            if ($hanya === '' || $hanya === 'jenjang') {
                $this->imporJenjang("{$dir}/prodi-jenjang.csv");
            }

            $this->newLine();
            $this->line('  <fg=green>Selesai.</> Isi tabel sekarang:');
            $this->line('    jenis inst. : ' . DB::table(self::T_JI)->count());
            $this->line('    bidang ilmu : ' . DB::table(self::T_BIDANG)->count());
            $this->line('    prodi       : ' . DB::table(self::T_PRODI)->count());
            $this->line('    relasi      : ' . DB::table(self::T_PJ)->count());

            Log::channel('web_career')->info('[IMPOR] Master pendidikan selesai diimpor.');

            return self::SUCCESS;
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('[IMPOR] Master pendidikan gagal: ' . $e->getMessage());
            $this->error('Gagal: ' . $e->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * Buat tabel bila belum ada. Sengaja di sini (bukan berkas .sql terpisah)
     * supaya rekan yang baru menarik repo cukup menjalankan satu perintah.
     */
    private function siapkanTabel(): void
    {
        if (! Schema::hasTable(self::T_BIDANG)) {
            $this->line('  membuat tabel ' . self::T_BIDANG);
            DB::statement('
                CREATE TABLE ' . self::T_BIDANG . ' (
                    Id_Master_Bidang_Ilmu INT IDENTITY(1,1) PRIMARY KEY,
                    Kode           VARCHAR(10)   NOT NULL,
                    Kode_Induk     VARCHAR(10)   NULL,
                    Level_Bidang   VARCHAR(10)   NOT NULL,
                    Nama           NVARCHAR(200) NOT NULL,
                    Nama_En        NVARCHAR(200) NULL,
                    Nama_Fakultas  NVARCHAR(200) NULL,
                    Urutan         INT           NOT NULL CONSTRAINT DF_MBI_Urutan DEFAULT (0),
                    Sumber         VARCHAR(20)   NULL,
                    Flag_Aktif     CHAR(1)       NOT NULL CONSTRAINT DF_MBI_Aktif DEFAULT (\'Y\'),
                    Created_At     DATETIME      NULL,
                    Created_By     NVARCHAR(100) NULL,
                    Updated_At     DATETIME      NULL,
                    Updated_By     NVARCHAR(100) NULL
                )');
            DB::statement('CREATE UNIQUE INDEX UX_MBI_Kode ON ' . self::T_BIDANG . ' (Kode)');
            DB::statement('CREATE INDEX IX_MBI_Induk ON ' . self::T_BIDANG . ' (Kode_Induk)');
        }

        if (! Schema::hasTable(self::T_PRODI)) {
            $this->line('  membuat tabel ' . self::T_PRODI);
            DB::statement('
                CREATE TABLE ' . self::T_PRODI . ' (
                    Id_Master_Prodi INT IDENTITY(1,1) PRIMARY KEY,
                    Kode         VARCHAR(80)   NOT NULL,
                    Nama         NVARCHAR(250) NOT NULL,
                    Nama_En      NVARCHAR(250) NULL,
                    Bidang_Kode  VARCHAR(10)   NULL,
                    Gelar        NVARCHAR(40)  NULL,
                    Kelompok     NVARCHAR(120) NULL,
                    Cakupan      VARCHAR(10)   NOT NULL CONSTRAINT DF_MPR_Cakupan DEFAULT (\'ID\'),
                    Sumber       VARCHAR(20)   NULL,
                    Flag_Aktif   CHAR(1)       NOT NULL CONSTRAINT DF_MPR_Aktif DEFAULT (\'Y\'),
                    Created_At   DATETIME      NULL,
                    Created_By   NVARCHAR(100) NULL,
                    Updated_At   DATETIME      NULL,
                    Updated_By   NVARCHAR(100) NULL
                )');
            DB::statement('CREATE UNIQUE INDEX UX_MPR_Kode ON ' . self::T_PRODI . ' (Kode)');
            DB::statement('CREATE INDEX IX_MPR_Cari ON ' . self::T_PRODI . ' (Cakupan, Nama)');
            DB::statement('CREATE INDEX IX_MPR_Bidang ON ' . self::T_PRODI . ' (Bidang_Kode)');
        }

        if (! Schema::hasTable(self::T_PJ)) {
            $this->line('  membuat tabel ' . self::T_PJ);
            DB::statement('
                CREATE TABLE ' . self::T_PJ . ' (
                    Id_Pj        INT IDENTITY(1,1) PRIMARY KEY,
                    Kode_Prodi   VARCHAR(80)  NOT NULL,
                    Kode_Jenjang VARCHAR(20)  NOT NULL,
                    Created_At   DATETIME     NULL,
                    Created_By   NVARCHAR(100) NULL
                )');
            DB::statement('CREATE UNIQUE INDEX UX_PJ ON ' . self::T_PJ . ' (Kode_Prodi, Kode_Jenjang)');
            DB::statement('CREATE INDEX IX_PJ_Jenjang ON ' . self::T_PJ . ' (Kode_Jenjang)');
        }
    }

    /**
     * Melengkapi Master Jenis Institusi.
     *
     * Data kampus hasil impor Dapodik memakai jenis yang belum terdaftar di
     * master (SDTK, SPM Ulya, PKBM, …). Selama jenisnya tidak ada, kampus itu
     * tidak pernah muncul di cascade — pelamarnya terpaksa mengetik manual.
     */
    private function imporJenisInstitusi(string $path): void
    {
        $baris = $this->baca($path);
        $now = now();
        $ada = DB::table(self::T_JI)->pluck('Kode')->flip();
        $jenjangSah = DB::table('N_WEB_CAREERS_Master_Jenjang')->pluck('Kode')->flip();
        $bindingAda = DB::table(self::T_JIJ)->get(['Kode_Jenis', 'Kode_Jenjang'])
            ->mapWithKeys(fn ($r) => [$r->Kode_Jenis . '|' . $r->Kode_Jenjang => true])->all();

        $baru = $lewat = $bindingBaru = 0;
        foreach ($baris as $j) {
            $jenjangList = array_filter(array_map('trim', explode(',', (string) $j['jenjang'])));

            if (! isset($ada[$j['kode']])) {
                DB::table(self::T_JI)->insert([
                    'Kode' => $j['kode'],
                    'Nama' => $j['nama'],
                    'Kategori' => $j['kategori'],
                    'Jenjang_Berlaku' => $j['jenjang'] ?: null,
                    'Urutan' => (int) $j['urutan'],
                    'Flag_Aktif' => 'Y',
                    'Created_At' => $now, 'Created_By' => 'IMPOR',
                    'Updated_At' => $now, 'Updated_By' => 'IMPOR',
                ]);
                $baru++;
            } else {
                $lewat++;
            }

            foreach ($jenjangList as $kj) {
                if (! isset($jenjangSah[$kj]) || isset($bindingAda[$j['kode'] . '|' . $kj])) {
                    continue;
                }
                DB::table(self::T_JIJ)->insert([
                    'Kode_Jenis' => $j['kode'], 'Kode_Jenjang' => $kj,
                    'Created_At' => $now, 'Created_By' => 'IMPOR',
                ]);
                $bindingAda[$j['kode'] . '|' . $kj] = true;
                $bindingBaru++;
            }
        }

        $this->line("  jenis institusi");
        $this->line("    baru {$baru} · sudah ada {$lewat} · binding jenjang baru {$bindingBaru}");
    }

    /** Baca CSV ber-delimiter ';' menjadi array asosiatif. */
    private function baca(string $path): array
    {
        if (! is_file($path)) {
            throw new \RuntimeException("Berkas tidak ditemukan: {$path}");
        }
        $h = fopen($path, 'r');
        $header = fgetcsv($h, 0, ';');
        // Buang BOM di kolom pertama supaya nama kolom tetap cocok.
        if ($header) {
            $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header[0]);
        }
        $out = [];
        while ($r = fgetcsv($h, 0, ';')) {
            if (count($r) === 1 && trim((string) $r[0]) === '') {
                continue;
            }
            $out[] = array_combine($header, array_pad($r, count($header), null));
        }
        fclose($h);

        return $out;
    }

    private function imporBidang(string $path, bool $paksa): void
    {
        $baris = $this->baca($path);
        $now = now();
        $ada = DB::table(self::T_BIDANG)->pluck('Sumber', 'Kode')->all();
        $baru = $ubah = $lewat = 0;

        $bar = $this->output->createProgressBar(count($baris));
        $bar->setFormat('  bidang ilmu  %current%/%max% [%bar%] %percent:3s%%');
        foreach (array_chunk($baris, 200) as $potongan) {
            DB::transaction(function () use ($potongan, $ada, $paksa, $now, &$baru, &$ubah, &$lewat, $bar) {
                foreach ($potongan as $b) {
                    $isi = [
                        'Kode_Induk' => $b['kode_induk'] ?: null,
                        'Level_Bidang' => $b['level'],
                        'Nama' => $b['nama'],
                        'Nama_En' => $b['nama_en'] ?: null,
                        'Nama_Fakultas' => $b['nama_fakultas'] ?: null,
                        'Urutan' => (int) $b['urutan'],
                        'Sumber' => $b['sumber'],
                        'Updated_At' => $now,
                        'Updated_By' => 'IMPOR',
                    ];
                    if (! array_key_exists($b['kode'], $ada)) {
                        DB::table(self::T_BIDANG)->insert($isi + [
                            'Kode' => $b['kode'], 'Flag_Aktif' => 'Y',
                            'Created_At' => $now, 'Created_By' => 'IMPOR',
                        ]);
                        $baru++;
                    } elseif ($paksa || $ada[$b['kode']] === 'isced') {
                        // Baris hasil impor boleh disegarkan; hasil suntingan
                        // admin (Sumber lain) dibiarkan kecuali --paksa.
                        DB::table(self::T_BIDANG)->where('Kode', $b['kode'])->update($isi);
                        $ubah++;
                    } else {
                        $lewat++;
                    }
                    $bar->advance();
                }
            });
        }
        $bar->finish();
        $this->newLine();
        $this->line("    baru {$baru} · diperbarui {$ubah} · dilewati {$lewat}");
    }

    private function imporProdi(string $path, bool $paksa): void
    {
        $baris = $this->baca($path);
        $now = now();
        $ada = DB::table(self::T_PRODI)->pluck('Sumber', 'Kode')->all();
        $bidangSah = DB::table(self::T_BIDANG)->pluck('Kode')->flip();
        $baru = $ubah = $lewat = $yatim = 0;

        $bar = $this->output->createProgressBar(count($baris));
        $bar->setFormat('  prodi        %current%/%max% [%bar%] %percent:3s%%');
        foreach (array_chunk($baris, 300) as $potongan) {
            DB::transaction(function () use ($potongan, $ada, $bidangSah, $paksa, $now, &$baru, &$ubah, &$lewat, &$yatim, $bar) {
                foreach ($potongan as $p) {
                    $bidang = $p['bidang_kode'] ?: null;
                    if ($bidang !== null && ! isset($bidangSah[$bidang])) {
                        // Jangan simpan rujukan yang menggantung — lebih baik
                        // kosong daripada menunjuk bidang yang tidak ada.
                        $bidang = null;
                        $yatim++;
                    }
                    $isi = [
                        'Nama' => $p['nama'],
                        'Nama_En' => $p['nama_en'] ?: null,
                        'Bidang_Kode' => $bidang,
                        'Gelar' => $p['gelar'] ?: null,
                        'Kelompok' => $p['kelompok'] ?: null,
                        'Cakupan' => $p['cakupan'] ?: 'ID',
                        'Sumber' => $p['sumber'],
                        'Updated_At' => $now,
                        'Updated_By' => 'IMPOR',
                    ];
                    if (! array_key_exists($p['kode'], $ada)) {
                        DB::table(self::T_PRODI)->insert($isi + [
                            'Kode' => $p['kode'], 'Flag_Aktif' => 'Y',
                            'Created_At' => $now, 'Created_By' => 'IMPOR',
                        ]);
                        $baru++;
                    } elseif ($paksa || in_array($ada[$p['kode']], ['cip', 'kurasi'], true)) {
                        DB::table(self::T_PRODI)->where('Kode', $p['kode'])->update($isi);
                        $ubah++;
                    } else {
                        $lewat++;
                    }
                    $bar->advance();
                }
            });
        }
        $bar->finish();
        $this->newLine();
        $this->line("    baru {$baru} · diperbarui {$ubah} · dilewati {$lewat}"
            . ($yatim ? " · <fg=yellow>{$yatim} bidang tak dikenal dikosongkan</>" : ''));
    }

    private function imporJenjang(string $path): void
    {
        $baris = $this->baca($path);
        $now = now();

        $prodiSah = DB::table(self::T_PRODI)->pluck('Kode')->flip();
        $jenjangSah = DB::table('N_WEB_CAREERS_Master_Jenjang')->pluck('Kode')->flip();
        $sudah = DB::table(self::T_PJ)->get(['Kode_Prodi', 'Kode_Jenjang'])
            ->mapWithKeys(fn ($r) => [$r->Kode_Prodi . '|' . $r->Kode_Jenjang => true])->all();

        $antre = [];
        $baru = $lewat = 0;
        $jenjangTakDikenal = [];

        foreach ($baris as $b) {
            $kp = $b['kode_prodi'];
            $kj = $b['kode_jenjang'];
            if (! isset($prodiSah[$kp])) {
                $lewat++;
                continue;
            }
            if (! isset($jenjangSah[$kj])) {
                $jenjangTakDikenal[$kj] = true;
                $lewat++;
                continue;
            }
            if (isset($sudah[$kp . '|' . $kj])) {
                $lewat++;
                continue;
            }
            $antre[] = ['Kode_Prodi' => $kp, 'Kode_Jenjang' => $kj, 'Created_At' => $now, 'Created_By' => 'IMPOR'];
            $sudah[$kp . '|' . $kj] = true;
            $baru++;
        }

        $bar = $this->output->createProgressBar(max(1, count($antre)));
        $bar->setFormat('  relasi       %current%/%max% [%bar%] %percent:3s%%');
        foreach (array_chunk($antre, 500) as $potongan) {
            DB::table(self::T_PJ)->insert($potongan);
            $bar->advance(count($potongan));
        }
        // Rapikan relasi yang sudah dicabut dari berkas sumber. Hanya baris
        // hasil impor yang dibuang — yang ditambah admin lewat UI dibiarkan.
        $dariBerkas = [];
        foreach ($baris as $b) {
            $dariBerkas[$b['kode_prodi'] . '|' . $b['kode_jenjang']] = true;
        }
        $dibuang = 0;
        DB::table(self::T_PJ)->where('Created_By', 'IMPOR')
            ->orderBy('Id_Pj')->chunk(1000, function ($rows) use ($dariBerkas, &$dibuang) {
                $basi = [];
                foreach ($rows as $r) {
                    if (! isset($dariBerkas[$r->Kode_Prodi . '|' . $r->Kode_Jenjang])) {
                        $basi[] = $r->Id_Pj;
                    }
                }
                if ($basi) {
                    $dibuang += DB::table(self::T_PJ)->whereIn('Id_Pj', $basi)->delete();
                }
            });

        $bar->finish();
        $this->newLine();
        $this->line("    baru {$baru} · dilewati {$lewat}" . ($dibuang ? " · dibuang {$dibuang} (sudah tak ada di sumber)" : ''));
        if ($jenjangTakDikenal) {
            $this->warn('    jenjang tak dikenal di Master Jenjang: ' . implode(', ', array_keys($jenjangTakDikenal)));
        }
    }
}
