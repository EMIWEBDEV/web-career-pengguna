<?php

namespace App\Console\Commands;

use App\Support\Career\GcsBerkas;
use App\Support\Career\LamaranService;
use App\Support\Career\LamaranTargetValidator;
use App\Support\Career\PemulihanBerkas;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * WEB CAREER — KANDIDAT CONTOH UNTUK PANEL PEMULIHAN BERKAS (DATA DUMMY).
 *
 * Dua belas kandidat MT & Rekrutmen yang berkasnya bermasalah dengan cara yang
 * berbeda-beda. Sebagian DITANGKAP sistem (muncul di rekomendasi panel),
 * sebagian sengaja LOLOS dari tangkapan — berkasnya tercatat ada padahal salah
 * atau rusak, atau isiannya opsional — dan hanya bisa dipulihkan lewat
 * Tambah Manual. Keduanya perlu ada: panel ini harus diuji pada apa yang ia
 * tangkap DAN pada apa yang memang tidak mungkin ia tangkap.
 *
 * ══ JALUR YANG SAMA DENGAN KANDIDAT SUNGGUHAN ══════════════════════════════
 *
 * buatLamaran() membuat lamaran sekaligus mengirim formulir pendaftarannya
 * (skema dibekukan, syarat dinilai), ketukPalu() memajukan tahap, dan
 * simpanPengisian() mengirim formulir tahap Kelengkapan Data. Alasannya sama
 * dengan karir:seed-kandidat: data uji yang dibentuk dengan INSERT tangan
 * menguji tiruan, bukan sistemnya.
 *
 * Yang DITULIS LANGSUNG hanya baris Formulir_Berkas. Di dunia nyata baris itu
 * lahir dari unggahan kandidat — dan justru di situlah kerusakan yang hendak
 * ditiru terjadi, jadi di sini ia sengaja dibuat bolong.
 *
 * Berkas yang "ada" MEMINJAM objek GCS yang sudah dipakai berkas lain (tidak
 * ada satu pun unggahan baru); berkas "rusak" menunjuk path yang memang tidak
 * pernah ada.
 *
 * ══ PENANDA & PEMBERSIHAN ══════════════════════════════════════════════════
 *
 *   Email      : uji.pbk+<nomor>@contoh.test   (domain .test, tak bisa dikirimi)
 *   Kode_Calon : UJI-PBK-<nomor>
 *   Sandi      : uji12345 — bisa dipakai masuk sebagai kandidatnya
 *
 *   php artisan karir:seed-pemulihan-berkas
 *   php artisan karir:seed-pemulihan-berkas --mt=43 --rekrutmen=40
 *   php artisan karir:seed-pemulihan-berkas --bersihkan
 */
class SeedPemulihanBerkas extends Command
{
    protected $signature = 'karir:seed-pemulihan-berkas
        {--mt= : Id program MT (kosong = dipilih otomatis)}
        {--rekrutmen= : Id program Rekrutmen (kosong = dipilih otomatis)}
        {--bersihkan : Hapus seluruh kandidat contoh, jangan membuat yang baru}';

    protected $description = 'Buat 12 kandidat contoh (MT & Rekrutmen) berkas hilang untuk mencoba panel Pemulihan Berkas';

    private const AWALAN_KODE = 'UJI-PBK-';

    private const AWALAN_EMAIL = 'uji.pbk+';

    private const PENCATAT = 'SEEDER(pbk)';

    private const FORMULIR_MT = 'FRM-MT-KELENGKAPAN-DATA';

    private const FORMULIR_REKRUTMEN = 'FRM-REKRUTMEN-LENGKAP';

    /**
     * SKENARIO — satu baris satu kandidat.
     *
     * Nilai berkas: ada | nama (nama tercatat di jawaban, berkasnya tidak
     * pernah tersimpan) | kosong | salah (berkas lain masuk ke isian ini) |
     * rusak (tercatat, tapi objeknya tidak ada di penyimpanan). Isian yang
     * tidak disebut = ada.
     */
    private const KANDIDAT = [
        // ── MT — formulir Kelengkapan Data ─────────────────────────────────
        ['no' => 1, 'nama' => 'ANDI PRATAMA', 'jk' => 'L', 'jalur' => 'MT',
            'berkas' => ['dok_cv' => 'nama'],
            'kasus' => 'CV: nama berkas tercatat di jawaban, berkasnya tidak pernah tersimpan',
            'tertangkap' => true, 'tindakan' => 'Unggah CV dari daftar rekomendasi'],
        ['no' => 2, 'nama' => 'BUNGA LESTARI', 'jk' => 'P', 'jalur' => 'MT',
            'berkas' => ['dok_kk' => 'kosong'],
            'kasus' => 'Kartu Keluarga (wajib) kosong',
            'tertangkap' => true, 'tindakan' => 'Unggah KK dari daftar rekomendasi'],
        ['no' => 3, 'nama' => 'CANDRA WIJAYA', 'jk' => 'L', 'jalur' => 'MT',
            'sertifikat' => [['Microsoft Office Specialist', 'ada'], ['Brevet Pajak A&B', 'nama'], ['TOEFL ITP 550', 'nama']],
            'kasus' => 'Sertifikat kurang: 3 ditulis, 2 berkasnya hilang (Brevet Pajak & TOEFL)',
            'tertangkap' => true, 'tindakan' => 'Unggah 2 sertifikat dari daftar rekomendasi'],
        ['no' => 4, 'nama' => 'DINDA KUSUMA', 'jk' => 'P', 'jalur' => 'MT',
            'berkas' => ['dok_cv' => 'kosong', 'dok_kk' => 'nama'],
            'kasus' => 'CV kosong + KK tercatat tanpa berkas',
            'tertangkap' => true, 'tindakan' => 'Unggah CV dan KK dari daftar rekomendasi'],
        ['no' => 5, 'nama' => 'EKO SAPUTRA', 'jk' => 'L', 'jalur' => 'MT',
            'berkas' => ['dok_kk' => 'salah'],
            'kasus' => 'Isian KK berisi scan KTP — sistem menganggap KK sudah ada',
            'tertangkap' => false, 'tindakan' => 'Tambah Manual → KK → timpa (dialog peringatan)'],
        ['no' => 6, 'nama' => 'FITRI HANDAYANI', 'jk' => 'P', 'jalur' => 'MT',
            'berkas' => ['dok_cv' => 'rusak'],
            'kasus' => 'CV tercatat, tapi berkasnya tidak ada di penyimpanan (gagal dibuka)',
            'tertangkap' => false, 'tindakan' => 'Tambah Manual → CV → timpa (dialog peringatan)'],
        ['no' => 7, 'nama' => 'GILANG RAMADHAN', 'jk' => 'L', 'jalur' => 'MT',
            'sertifikat' => [['Microsoft Office Specialist', 'ada'], ['K3 Umum (Kemnaker)', 'kosong']],
            'kasus' => 'Sertifikat K3 ditulis tanpa berkas — isian sertifikat opsional',
            'tertangkap' => false, 'tindakan' => 'Tambah Manual → Upload Sertifikat baris 2 (K3 Umum)'],

        // ── REKRUTMEN — formulir pendaftaran (CV & Dokumen Pendukung wajib) ─
        ['no' => 8, 'nama' => 'HANA PUSPITA', 'jk' => 'P', 'jalur' => 'REKRUTMEN', 'loker' => 0,
            'berkas' => ['cv' => 'kosong'],
            'kasus' => 'CV (wajib) kosong',
            'tertangkap' => true, 'tindakan' => 'Unggah CV dari daftar rekomendasi'],
        ['no' => 9, 'nama' => 'IRFAN MAULANA', 'jk' => 'L', 'jalur' => 'REKRUTMEN', 'loker' => 1,
            'berkas' => ['cv' => 'nama'],
            'kasus' => 'CV: nama berkas tercatat, berkasnya tidak pernah tersimpan',
            'tertangkap' => true, 'tindakan' => 'Unggah CV dari daftar rekomendasi'],
        ['no' => 10, 'nama' => 'JIHAN SAFITRI', 'jk' => 'P', 'jalur' => 'REKRUTMEN', 'loker' => 1,
            'berkas' => ['dok' => 'nama'],
            'kasus' => 'Dokumen pendukung (sertifikat SIO/STR) tercatat tanpa berkas',
            'tertangkap' => true, 'tindakan' => 'Unggah dokumen pendukung dari daftar rekomendasi'],
        ['no' => 11, 'nama' => 'KEVIN ADITYA', 'jk' => 'L', 'jalur' => 'REKRUTMEN', 'loker' => 0,
            'berkas' => ['cv' => 'salah'],
            'kasus' => 'Isian CV berisi sertifikat vaksin — sistem menganggap CV sudah ada',
            'tertangkap' => false, 'tindakan' => 'Tambah Manual → CV → timpa (dialog peringatan)'],
        ['no' => 12, 'nama' => 'LAILA RAHMAWATI', 'jk' => 'P', 'jalur' => 'REKRUTMEN', 'loker' => 0,
            'berkas' => ['cv' => 'rusak'],
            'kasus' => 'CV tercatat, tapi berkasnya tidak ada di penyimpanan (gagal dibuka)',
            'tertangkap' => false, 'tindakan' => 'Tambah Manual → CV → timpa (dialog peringatan)'],
    ];

    /** Objek GCS yang dipinjam untuk berkas "ada" — diisi pinjamObjek(). */
    private array $objek = [];

    public function handle(LamaranService $svc): int
    {
        if (app()->environment('production')) {
            $this->error('Data contoh tidak boleh dibuat di lingkungan produksi.');

            return self::FAILURE;
        }

        if ($this->option('bersihkan')) {
            return $this->bersihkan();
        }

        if (DB::table('N_WEB_CAREERS_Users')->where('Kode_Calon', 'like', self::AWALAN_KODE.'%')->exists()) {
            $this->error('Kandidat contoh sudah ada. Bersihkan dulu supaya susunannya tetap persis dua belas:');
            $this->line('  php artisan karir:seed-pemulihan-berkas --bersihkan');

            return self::FAILURE;
        }

        $mt = $this->sasaran('MT', self::FORMULIR_MT, null, $this->option('mt') ? (int) $this->option('mt') : null);
        $rek = $this->sasaran('REKRUTMEN', self::FORMULIR_REKRUTMEN, 1, $this->option('rekrutmen') ? (int) $this->option('rekrutmen') : null);
        if (! $mt || ! $rek) {
            $this->error('Tidak ada program '.(! $mt ? 'MT (berformulir '.self::FORMULIR_MT.')' : 'Rekrutmen (berformulir '.self::FORMULIR_REKRUTMEN.' di tahap 1)')
                .' yang sedang menerima lamaran.');

            return self::FAILURE;
        }

        try {
            $this->objek = $this->pinjamObjek();
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("MT        : {$mt['program']->Nama} (#{$mt['program']->Id_Program}) — ".$mt['posisi']->pluck('Posisi')->implode(', '));
        $this->info("Rekrutmen : {$rek['program']->Nama} (#{$rek['program']->Id_Program}) — ".$rek['posisi']->pluck('Posisi')->implode(', '));
        $this->newLine();

        $hasil = [];
        foreach (self::KANDIDAT as $k) {
            try {
                $hasil[] = $this->buatSatu($svc, $k, $k['jalur'] === 'MT' ? $mt : $rek);
                $this->line("  ✓ #{$k['no']} {$k['nama']}");
            } catch (\Throwable $e) {
                $this->error("  ✗ #{$k['no']} {$k['nama']}: ".Str::limit($e->getMessage(), 200));
            }
        }

        $this->newLine();
        $this->laporan($hasil);

        return self::SUCCESS;
    }

    // ══ PEMBUATAN ═══════════════════════════════════════════════════════════

    /**
     * Satu kandidat, utuh atau tidak sama sekali — kandidat setengah jadi
     * (lamaran ada, formulirnya tidak) hanya akan membingungkan pengujian.
     */
    private function buatSatu(LamaranService $svc, array $k, array $sasaran): array
    {
        return DB::transaction(function () use ($svc, $k, $sasaran) {
            $posisi = $sasaran['posisi'][($k['loker'] ?? 0) % $sasaran['posisi']->count()];
            $userId = $this->buatAkun($k);

            $jawab = $k['jalur'] === 'MT' ? $this->jawabanMtDaftar($k) : $this->jawabanRekrutmen($k, $posisi->Posisi);
            $hasil = $svc->buatLamaran($userId, $sasaran['pembukaanId'], (int) $posisi->Id_Program_Posisi, null, null, $jawab);
            if (! ($hasil['ok'] ?? false) || empty($hasil['lamaranId'])) {
                throw new \RuntimeException('buatLamaran ditolak: '.($hasil['pesan'] ?? '-'));
            }
            $lamaranId = (int) $hasil['lamaranId'];

            $daftar = $this->pengisianTahap($lamaranId, 1);
            if ($k['jalur'] === 'MT') {
                $this->simpanBerkas($daftar, $userId, $k, 'foto_verifikasi', null, null, 'ada', 'wajah');
            } else {
                $this->pastikanIsian($daftar, ['cv_curriculum_vitae', 'dokumen_pendukung_e_g_rekrutmen']);
                $this->simpanBerkas($daftar, $userId, $k, 'cv_curriculum_vitae', null, null, $k['berkas']['cv'] ?? 'ada', 'pdf');
                $this->simpanBerkas($daftar, $userId, $k, 'dokumen_pendukung_e_g_rekrutmen', null, null, $k['berkas']['dok'] ?? 'ada', 'pdf');
            }

            if ($k['jalur'] === 'MT') {
                $tahap = DB::table('N_WEB_CAREERS_Lamaran_Tahap')
                    ->where('Lamaran_Id', $lamaranId)
                    ->where('Formulir_Kode', self::FORMULIR_MT)
                    ->orderBy('Urutan')
                    ->first();
                $this->majukanSampai($svc, $lamaranId, (int) $tahap->Urutan);

                $kirim = $svc->simpanPengisian((int) $tahap->Id_Lamaran_Tahap, $userId, $this->jawabanMtKelengkapan($k));
                if (! ($kirim['ok'] ?? false)) {
                    throw new \RuntimeException('Formulir Kelengkapan Data ditolak: '.($kirim['pesan'] ?? '-'));
                }

                $lengkap = $this->pengisianTahap($lamaranId, (int) $tahap->Urutan);
                $this->pastikanIsian($lengkap, ['dok_cv', 'dok_kk']);
                foreach (['dok_cv', 'dok_ktp', 'dok_kk', 'dok_ijazah', 'dok_transkrip'] as $f) {
                    $this->simpanBerkas($lengkap, $userId, $k, $f, null, null, $k['berkas'][$f] ?? 'ada', 'pdf');
                }
                $this->simpanBerkas($lengkap, $userId, $k, 'dok_foto', null, null, $k['berkas']['dok_foto'] ?? 'ada', 'gambar');
                foreach ($this->sertifikat($k) as $i => [$nama, $keadaan]) {
                    $this->simpanBerkas($lengkap, $userId, $k, 'sert_file', 'riwayat_sertifikasi', $i, $keadaan, 'pdf', $nama);
                }
            }

            $l = DB::table('N_WEB_CAREERS_Lamaran as l')
                ->leftJoin('N_WEB_CAREERS_Lamaran_Tahap as t', function ($j) {
                    $j->on('t.Lamaran_Id', '=', 'l.Id_Lamaran')->on('t.Urutan', '=', 'l.Urutan_Tahap');
                })
                ->where('l.Id_Lamaran', $lamaranId)
                ->first(['l.Kode', 'l.Status', 'l.Urutan_Tahap', 'l.Total_Tahap', 't.Label']);

            return $k + [
                'lamaranId' => $lamaranId,
                'kode' => $l->Kode,
                'status' => $l->Status,
                'tahap' => "{$l->Urutan_Tahap}/{$l->Total_Tahap} {$l->Label}",
                'program' => $sasaran['program']->Nama,
                'lowongan' => $posisi->Posisi.' · '.($posisi->Mpp_Ref ?: 'tanpa MPP'),
            ];
        });
    }

    /** Akun kandidat contoh — pola yang sama dengan karir:seed-kandidat. */
    private function buatAkun(array $k): int
    {
        $now = now();

        return (int) DB::table('N_WEB_CAREERS_Users')->insertGetId([
            'Nama' => $k['nama'],
            'Email' => $this->email($k),
            'No_Hp' => $this->hp($k),
            'Password' => Hash::make('uji12345'),
            'Role' => 'KANDIDAT',
            'Status' => 'AKTIF',
            'Kode_Calon' => self::AWALAN_KODE.str_pad((string) $k['no'], 4, '0', STR_PAD_LEFT),
            'Flag_Email_Verified' => 'Y',
            'Email_Verified_At' => $now,
            'Created_At' => $now, 'Created_By' => self::PENCATAT,
            'Updated_At' => $now, 'Updated_By' => self::PENCATAT,
        ], 'Id_Users');
    }

    /**
     * Majukan lamaran sampai tahap ke-$sampai BERJALAN — lewat ketukPalu(),
     * seperti admin sungguhan. Aktivitas tiap tahap dicatat selesai lebih dulu,
     * persis yang dilakukan karir:seed-kandidat.
     */
    private function majukanSampai(LamaranService $svc, int $lamaranId, int $sampai): void
    {
        for ($langkah = 0; $langkah < 20; $langkah++) {
            $kini = (int) DB::table('N_WEB_CAREERS_Lamaran')->where('Id_Lamaran', $lamaranId)->value('Urutan_Tahap');
            if ($kini >= $sampai) {
                return;
            }

            $tahap = DB::table('N_WEB_CAREERS_Lamaran_Tahap')
                ->where('Lamaran_Id', $lamaranId)
                ->where('Urutan', $kini)
                ->first(['Id_Lamaran_Tahap', 'Label']);

            DB::table('N_WEB_CAREERS_Lamaran_Tahap_Tes')
                ->where('Lamaran_Tahap_Id', $tahap->Id_Lamaran_Tahap)
                ->where('Flag_Selesai', '<>', 'Y')
                ->update([
                    'Status' => 'SELESAI', 'Hasil' => 'LULUS', 'Flag_Selesai' => 'Y',
                    'Waktu_Selesai' => now(),
                    'Catatan' => 'Data contoh — hasil dicatat otomatis oleh seeder.',
                    'Updated_At' => now(), 'Updated_By' => self::PENCATAT,
                ]);

            $hasil = $svc->ketukPalu((int) $tahap->Id_Lamaran_Tahap, 'LULUS', 'Data contoh — diloloskan otomatis oleh seeder.', null, false);
            if (! ($hasil['ok'] ?? false)) {
                throw new \RuntimeException("Tahap '{$tahap->Label}' menolak diputus: ".($hasil['pesan'] ?? '-'));
            }
        }

        throw new \RuntimeException("Lamaran #{$lamaranId} tidak sampai ke tahap {$sampai}.");
    }

    /** Pengisian TERKIRIM yang menempel di tahap ke-$urutan. */
    private function pengisianTahap(int $lamaranId, int $urutan): object
    {
        $fp = DB::table('N_WEB_CAREERS_Lamaran_Tahap as t')
            ->join('N_WEB_CAREERS_Formulir_Pengisian as fp', 'fp.Id_Formulir_Pengisian', '=', 't.Formulir_Pengisian_Id')
            ->where('t.Lamaran_Id', $lamaranId)
            ->where('t.Urutan', $urutan)
            ->first(['fp.Id_Formulir_Pengisian', 'fp.Master_Formulir_Versi_Id', 'fp.Jawaban_Json']);

        if (! $fp) {
            throw new \RuntimeException("Formulir tahap {$urutan} lamaran #{$lamaranId} tidak tersimpan.");
        }

        return $fp;
    }

    /**
     * Isian yang dipakai skenario benar-benar ada di skema yang dibekukan —
     * kalau formulirnya kelak diubah, galatnya menyebut isian mana yang hilang
     * alih-alih diam-diam membuat kandidat yang "lengkap".
     */
    private function pastikanIsian(object $fp, array $field): void
    {
        $skema = json_decode((string) DB::table('N_WEB_CAREERS_Master_Formulir_Versi')
            ->where('Id_Master_Formulir_Versi', $fp->Master_Formulir_Versi_Id)
            ->value('Schema_Json'), true) ?: [];
        $ada = [];
        array_walk_recursive($skema, function ($v, $k) use (&$ada) {
            if ($k === 'key') {
                $ada[(string) $v] = true;
            }
        });

        $hilang = array_values(array_filter($field, fn ($f) => ! isset($ada[$f])));
        if ($hilang) {
            throw new \RuntimeException('Isian tidak ada di formulirnya: '.implode(', ', $hilang));
        }
    }

    /**
     * Satu isian berkas menurut keadaannya. Nama di jawaban sudah diisi oleh
     * jawaban*(); di sini hanya baris Formulir_Berkas-nya yang dibuat — atau
     * sengaja tidak dibuat.
     */
    private function simpanBerkas(object $fp, int $userId, array $k, string $field, ?string $bagian, ?int $baris, string $keadaan, string $jenis, ?string $judul = null): void
    {
        if (in_array($keadaan, ['nama', 'kosong'], true)) {
            return;
        }

        $objek = $keadaan === 'salah' ? $this->objek['pdf'] : $this->objek[$jenis];
        $namaAsli = $this->namaBerkas($k, $field, $keadaan, $judul, $objek['ext']);
        $path = $keadaan === 'rusak'
            ? 'contoh-uji/pemulihan-berkas/tidak-ada-'.$k['no'].'-'.Str::slug($field).'.pdf'
            : $objek['path'];
        $now = now();
        $urutan = (int) DB::table('N_WEB_CAREERS_Formulir_Berkas')->where('Formulir_Pengisian_Id', $fp->Id_Formulir_Pengisian)->max('Urutan');

        DB::table('N_WEB_CAREERS_Formulir_Berkas')->insert([
            'Formulir_Pengisian_Id' => $fp->Id_Formulir_Pengisian,
            // Diunggah kandidat sendiri: pemilik & pengunggah orang yang sama,
            // jadi worklist TIDAK menandainya "Diunggah admin".
            'Id_Users' => $userId,
            'Bagian_Key' => $bagian,
            'Baris_Index' => $baris,
            'Field_Key' => $field,
            'Urutan' => $urutan + 1,
            'Nama_Asli' => $namaAsli,
            'Path_File' => $path,
            'Ukuran_Byte' => $objek['ukuran'],
            'Mime' => $objek['mime'],
            'Ekstensi' => pathinfo($namaAsli, PATHINFO_EXTENSION),
            'Status_Verifikasi' => 'BELUM',
            'Waktu_Unggah' => $now,
            'Created_At' => $now, 'Created_By' => $k['nama'], 'Created_By_Id' => $userId,
            'Updated_At' => $now, 'Updated_By' => $k['nama'], 'Updated_By_Id' => $userId,
        ]);
    }

    // ══ JAWABAN FORMULIR ════════════════════════════════════════════════════

    /** Formulir MT Pendaftaran — isian yang sama dengan formulir v7. */
    private function jawabanMtDaftar(array $k): array
    {
        return [
            'nama_lengkap' => Str::title($k['nama']),
            'tanggal_lahir' => $this->lahir($k),
            'jenis_kelamin' => $k['jk'] === 'L' ? 'Laki-Laki' : 'Perempuan',
            'no_hp' => $this->hp($k),
            'email' => $this->email($k),
            'status_kemahasiswaan' => 'Mahasiswa',
            'semester' => 8,
            'jenjang_pendidikan' => 'S1',
            'jenis_institusi' => 'Universitas',
            'nama_kampus' => ['Universitas Sriwijaya', 'Universitas Gadjah Mada', 'Universitas Diponegoro', 'Institut Pertanian Bogor'][$k['no'] % 4],
            'jurusan_fakultas' => 'Ekonomi dan Bisnis',
            'program_studi' => 'Manajemen',
            'ipk' => 3.45 + ($k['no'] % 5) / 10,
            'bersedia_ditempatkan' => 'Ya',
            'foto_verifikasi' => 'foto_verifikasi-verifikasi.'.$this->objek['wajah']['ext'],
        ];
    }

    /** Formulir MT - Kelengkapan Data — isian berkas mengikuti skenario. */
    private function jawabanMtKelengkapan(array $k): array
    {
        $nama = Str::title($k['nama']);
        $alamat = 'Jl. Contoh Uji No. '.$k['no'].', Palembang, Sumatera Selatan';
        $isi = fn (string $f) => $this->nilaiBerkas($k, $f, $k['berkas'][$f] ?? 'ada', null, $f === 'dok_foto' ? $this->objek['gambar']['ext'] : 'pdf');

        return [
            'v_nama' => $nama,
            'v_email' => $this->email($k),
            'v_wa' => $this->hp($k),
            'data_sesuai' => 'Sesuai',
            'nik' => '1671'.str_pad((string) (900000000000 + $k['no']), 12, '0', STR_PAD_LEFT),
            'agama' => 'Islam',
            'status_pernikahan' => 'Lajang',
            'alamat_ktp' => $alamat,
            'alamat_domisili' => $alamat,
            'darurat_nama' => 'Orang tua '.Str::before($nama, ' '),
            'darurat_hubungan' => 'Orang Tua',
            'darurat_hp' => '6281300000'.str_pad((string) $k['no'], 3, '0', STR_PAD_LEFT),
            'punya_pengalaman_kerja' => 'Ya',
            'punya_pengalaman_organisasi' => 'Ya',
            'punya_sertifikasi' => 'Ya',
            'riwayat_kerja_magang' => [[
                'kerja_perusahaan' => 'PT Contoh Sejahtera',
                'kerja_jabatan' => 'Staf Magang',
                'kerja_periode' => '2025',
                'kerja_uraian' => 'Magang tiga bulan di bagian administrasi — data contoh.',
            ]],
            'riwayat_organisasi' => [['org_nama' => 'BEM Fakultas', 'org_jabatan' => 'Anggota', 'org_periode' => '2023-2024']],
            'riwayat_sertifikasi' => array_map(fn ($s) => [
                'sert_nama' => $s[0],
                'sert_tanggal' => 'Maret 2025',
                'sert_file' => $this->nilaiBerkas($k, 'sert_file', $s[1], $s[0], 'pdf'),
            ], $this->sertifikat($k)),
            'siap_seleksi' => 'Ya',
            'siap_shift' => 'Ya',
            'siap_durasi_mt' => 'Ya',
            'siap_ikatan_dinas' => 'Ya',
            'mulai_bekerja' => '1 bulan',
            'ekspektasi_gaji' => 5500000,
            'pengalaman_produksi' => 'Tidak',
            'pengalaman_produksi_uraian' => null,
            'buta_warna' => 'Tidak',
            'riwayat_asma' => 'Tidak',
            'kenalan_evo' => 'Tidak',
            'kenalan_evo_list' => [['kenalan_nama' => null, 'kenalan_hubungan' => null]],
            'dok_cv' => $isi('dok_cv'),
            'dok_ktp' => $isi('dok_ktp'),
            'dok_kk' => $isi('dok_kk'),
            'dok_ijazah' => $isi('dok_ijazah'),
            'dok_transkrip' => $isi('dok_transkrip'),
            'dok_foto' => $isi('dok_foto'),
            'setuju_data_benar' => true,
            'setuju_ikut_seleksi' => true,
            'setuju_data_pribadi' => true,
        ];
    }

    /** Formulir Rekrutmen (pendaftaran) — isian yang sama dengan formulir v18. */
    private function jawabanRekrutmen(array $k, string $posisi): array
    {
        $nama = Str::title($k['nama']);
        $alamat = 'Jl. Contoh Uji No. '.$k['no'].', Jakarta Timur';

        return [
            'posisi_dilamar' => $posisi,
            'tanggal_tersedia' => now()->addMonth()->format('Y-m-d'),
            'sumber_info' => 'Instagram/Media Sosial',
            'nama_lengkap' => $nama,
            'nama_panggilan' => Str::before($nama, ' '),
            'jenis_kelamin' => $k['jk'] === 'L' ? 'Pria' : 'Wanita',
            'tempat_lahir' => 'Jakarta',
            'tanggal_lahir' => $this->lahir($k),
            'alamat_ktp' => $alamat,
            'alamat_sekarang' => $alamat,
            'status_tempat_tinggal' => 'Rumah Pribadi/Orang Tua',
            'no_hp' => $this->hp($k),
            'no_telp_rumah' => '',
            'email' => $this->email($k),
            'berat_badan' => 60,
            'tinggi_badan' => 168,
            'status_pernikahan' => 'Lajang',
            'agama' => 'Islam',
            'no_ktp' => '3175'.str_pad((string) (900000000000 + $k['no']), 12, '0', STR_PAD_LEFT),
            'no_npwp' => '',
            'no_bpjs_tk' => '',
            'no_sim_a' => '',
            'sim_a_berlaku' => '',
            'no_sim_c' => '',
            'sim_c_berlaku' => '',
            'keluarga_utama' => [['kel_utama_nama' => 'Orang tua '.Str::before($nama, ' '), 'kel_utama_jk' => 'P', 'kel_utama_usia' => 52, 'kel_utama_pendidikan' => 'SMA', 'kel_utama_pekerjaan' => 'Wiraswasta']],
            'keluarga_menikah' => [['kel_nikah_hubungan' => '', 'kel_nikah_nama' => '', 'kel_nikah_jk' => '', 'kel_nikah_usia' => null, 'kel_nikah_pendidikan' => '', 'kel_nikah_pekerjaan' => '']],
            'pendidikan_formal' => [['jenjang' => 'S1', 'nama_institusi' => 'Universitas Indonesia', 'kota' => 'Depok', 'jurusan' => 'Akuntansi', 'tahun_masuk' => 2019, 'tahun_lulus' => 2023, 'ipk' => 3.41]],
            'kontak_darurat' => [['kd_nama' => 'Orang tua '.Str::before($nama, ' '), 'kd_telepon' => '6281300000'.str_pad((string) $k['no'], 3, '0', STR_PAD_LEFT), 'kd_hubungan' => 'Orang Tua', 'kd_alamat' => $alamat]],
            'referensi_rekomendasi' => [['ref_nama' => '', 'ref_jabatan' => '', 'ref_telepon' => '', 'ref_hubungan' => '', 'ref_alamat' => '']],
            'kenalan_di_perusahaan' => '',
            'ekspektasi_gaji' => '',
            'fasilitas_diinginkan' => '',
            'cv_curriculum_vitae' => $this->nilaiBerkas($k, 'cv_curriculum_vitae', $k['berkas']['cv'] ?? 'ada', null, 'pdf'),
            'dokumen_pendukung_e_g_rekrutmen' => $this->nilaiBerkas($k, 'dokumen_pendukung_e_g_rekrutmen', $k['berkas']['dok'] ?? 'ada', null, 'pdf'),
            'setuju_kebenaran_data' => true,
        ];
    }

    /** Nilai isian berkas di JAWABAN — kosong hanya bila skenarionya kosong. */
    private function nilaiBerkas(array $k, string $field, string $keadaan, ?string $judul, string $ext): string
    {
        return $keadaan === 'kosong' ? '' : $this->namaBerkas($k, $field, $keadaan, $judul, $ext);
    }

    /**
     * Nama berkas yang dibaca admin. Untuk skenario "salah", namanya memang
     * menyebut dokumen lain — begitulah admin mengenali bahwa isinya keliru.
     */
    private function namaBerkas(array $k, string $field, string $keadaan, ?string $judul, string $ext): string
    {
        $orang = str_replace(' ', '_', Str::title($k['nama']));
        $jenis = match (true) {
            $keadaan === 'salah' && $field === 'dok_kk' => 'KTP',
            $keadaan === 'salah' => 'Sertifikat_Vaksin',
            $field === 'sert_file' => 'Sertifikat_'.Str::slug((string) $judul, '_'),
            $field === 'foto_verifikasi' => 'foto_verifikasi',
            default => [
                'dok_cv' => 'CV', 'cv_curriculum_vitae' => 'CV', 'dok_ktp' => 'KTP', 'dok_kk' => 'KK',
                'dok_ijazah' => 'Ijazah', 'dok_transkrip' => 'Transkrip', 'dok_foto' => 'Foto',
                'dokumen_pendukung_e_g_rekrutmen' => 'Sertifikat_SIO',
            ][$field] ?? Str::studly($field),
        };

        return $field === 'foto_verifikasi'
            ? "foto_verifikasi-verifikasi.{$ext}"
            : "{$jenis}_{$orang}.".($keadaan === 'salah' ? 'pdf' : $ext);
    }

    /** Baris sertifikat skenario — bawaannya satu sertifikat yang lengkap. */
    private function sertifikat(array $k): array
    {
        return $k['sertifikat'] ?? [['Microsoft Office Specialist', 'ada']];
    }

    private function email(array $k): string
    {
        return self::AWALAN_EMAIL.$k['no'].'@contoh.test';
    }

    private function hp(array $k): string
    {
        return '6281299900'.str_pad((string) $k['no'], 3, '0', STR_PAD_LEFT);
    }

    private function lahir(array $k): string
    {
        return sprintf('%04d-%02d-%02d', 2000 + ($k['no'] % 4), 1 + ($k['no'] % 12), 10 + $k['no']);
    }

    // ══ SASARAN & OBJEK PINJAMAN ════════════════════════════════════════════

    /**
     * Program yang sedang menerima lamaran, berformulir yang dibutuhkan, dan
     * punya lowongan yang lolos LamaranTargetValidator — gerbang yang sama
     * dengan pelamar sungguhan. Yang lowongannya paling banyak didahulukan
     * (tampilan per-MPP butuh lebih dari satu), lalu yang paling ramai.
     */
    private function sasaran(string $kategori, string $formulir, ?int $urutanTahap, ?int $programId): ?array
    {
        $program = DB::table('N_WEB_CAREERS_Program as p')
            ->join('N_WEB_CAREERS_Master_Alur as a', 'a.Kode', '=', 'p.Alur_Kode')
            ->where('p.Kategori', $kategori)
            ->where('p.Status', 'BERJALAN')
            ->when($programId, fn ($w) => $w->where('p.Id_Program', $programId))
            ->whereExists(fn ($w) => $w->select(DB::raw(1))
                ->from('N_WEB_CAREERS_Master_Alur_Tahap as t')
                ->whereColumn('t.Master_Alur_Id', 'a.Id_Master_Alur')
                ->where('t.Formulir_Kode', $formulir)
                ->when($urutanTahap, fn ($x) => $x->where('t.Urutan', $urutanTahap)))
            ->get(['p.Id_Program', 'p.Nama', DB::raw('(SELECT COUNT(*) FROM N_WEB_CAREERS_Lamaran l WHERE l.Program_Id = p.Id_Program) as Pelamar')]);

        $validator = app(LamaranTargetValidator::class);
        $calon = [];
        foreach ($program as $p) {
            $pembukaan = DB::table('N_WEB_CAREERS_Pembukaan')->where('Program_Id', $p->Id_Program)->orderByDesc('Id_Pembukaan')->first();
            if (! $pembukaan) {
                continue;
            }
            $posisi = DB::table('N_WEB_CAREERS_Program_Posisi')
                ->where('Program_Id', $p->Id_Program)
                ->orderBy('Id_Program_Posisi')
                ->get()
                ->filter(fn ($x) => $validator->validasi((int) $pembukaan->Id_Pembukaan, (int) $x->Id_Program_Posisi)['ok'] ?? false)
                ->values();
            if ($posisi->isNotEmpty()) {
                $calon[] = ['program' => $p, 'pembukaanId' => (int) $pembukaan->Id_Pembukaan, 'posisi' => $posisi];
            }
        }

        usort($calon, fn ($a, $b) => [$b['posisi']->count(), (int) $b['program']->Pelamar] <=> [$a['posisi']->count(), (int) $a['program']->Pelamar]);

        return $calon[0] ?? null;
    }

    /**
     * Objek GCS yang dipinjam untuk berkas "ada": PDF, gambar, dan foto wajah
     * dari berkas yang SUDAH tersimpan — diperiksa benar-benar ada di bucket,
     * supaya berkas yang dinyatakan ada memang bisa dibuka.
     */
    private function pinjamObjek(): array
    {
        $disk = Storage::disk(GcsBerkas::DISK);
        $cari = function (callable $saring) use ($disk): ?array {
            $rows = DB::table('N_WEB_CAREERS_Formulir_Berkas')
                ->whereNotNull('Path_File')
                ->where('Path_File', 'not like', 'contoh-uji/%')
                ->where($saring)
                ->orderByDesc('Id_Formulir_Berkas')
                ->limit(20)
                ->get(['Path_File', 'Ukuran_Byte', 'Mime', 'Ekstensi']);
            foreach ($rows as $r) {
                try {
                    if ($disk->exists($r->Path_File)) {
                        return [
                            'path' => $r->Path_File,
                            'ukuran' => (int) $r->Ukuran_Byte,
                            'mime' => $r->Mime,
                            'ext' => strtolower($r->Ekstensi ?: pathinfo($r->Path_File, PATHINFO_EXTENSION)),
                        ];
                    }
                } catch (\Throwable $e) {
                    throw new \RuntimeException('Penyimpanan berkas tidak bisa diperiksa: '.$e->getMessage());
                }
            }

            return null;
        };

        $pdf = $cari(fn ($w) => $w->where('Ekstensi', 'pdf'));
        $wajah = $cari(fn ($w) => $w->where('Field_Key', 'foto_verifikasi'));
        $gambar = $cari(fn ($w) => $w->whereIn('Ekstensi', ['png', 'jpg', 'jpeg'])->where('Field_Key', '<>', 'foto_verifikasi')) ?? $wajah;

        if (! $pdf || ! $wajah || ! $gambar) {
            throw new \RuntimeException('Tidak ada berkas PDF/gambar yang bisa dipinjam dari penyimpanan — kandidat contoh butuh berkas yang benar-benar bisa dibuka.');
        }

        return ['pdf' => $pdf, 'gambar' => $gambar, 'wajah' => $wajah];
    }

    // ══ LAPORAN & PEMBERSIHAN ═══════════════════════════════════════════════

    /**
     * Ringkasan untuk manusia, lalu pembuktian: pemindai panel dijalankan pada
     * kandidat yang baru dibuat. Yang "tertangkap" harus punya temuan, yang
     * "meleset" harus nihil — kalau tidak, skenarionya tidak menguji apa yang
     * diklaimnya.
     */
    private function laporan(array $hasil): void
    {
        if (! $hasil) {
            $this->warn('Tidak ada kandidat yang dibuat.');

            return;
        }

        $temuan = PemulihanBerkas::temukanHilang(array_column($hasil, 'lamaranId'));
        $cocok = 0;

        $this->table(
            ['#', 'Nama', 'Lamaran · Tahap', 'Program · Lowongan', 'Kasus', 'Sistem', 'Yang dilakukan admin'],
            array_map(function ($h) use ($temuan, &$cocok) {
                $n = count($temuan[$h['lamaranId']] ?? []);
                $benar = $h['tertangkap'] ? $n > 0 : $n === 0;
                $cocok += $benar ? 1 : 0;

                return [
                    $h['no'],
                    $h['nama'],
                    $h['kode'].' · '.$h['tahap'].($h['status'] !== 'BERJALAN' ? " ({$h['status']})" : ''),
                    Str::limit($h['program'], 26).' · '.$h['lowongan'],
                    Str::limit($h['kasus'], 60),
                    ($h['tertangkap'] ? "tertangkap ({$n})" : 'meleset').($benar ? ' ✓' : ' ✗ TIDAK SESUAI'),
                    $h['tindakan'],
                ];
            }, $hasil)
        );

        $this->info("Pemindai panel sesuai skenario: {$cocok} dari ".count($hasil).'.');
        $this->line('Masuk sebagai kandidat: uji.pbk+<nomor>@contoh.test / uji12345');
        $this->line('Hapus kembali: <options=bold>php artisan karir:seed-pemulihan-berkas --bersihkan</>');
    }

    /**
     * Hapus seluruh kandidat contoh berikut jejaknya. Tabel turunan dicari dari
     * INFORMATION_SCHEMA — kolom kunci lamaran, bukan daftar mati — supaya tabel
     * baru yang ikut menyimpan jejak lamaran tidak tertinggal sebagai yatim.
     * Setiap penghapusan dibatasi id milik akun ber-Kode_Calon UJI-PBK-.
     */
    private function bersihkan(): int
    {
        $userIds = DB::table('N_WEB_CAREERS_Users')
            ->where('Kode_Calon', 'like', self::AWALAN_KODE.'%')
            ->where('Email', 'like', self::AWALAN_EMAIL.'%@contoh.test')
            ->pluck('Id_Users');

        if ($userIds->isEmpty()) {
            $this->warn('Tidak ada kandidat contoh pemulihan berkas.');

            return self::SUCCESS;
        }

        $lamaranIds = DB::table('N_WEB_CAREERS_Lamaran')->whereIn('Id_Users', $userIds)->pluck('Id_Lamaran');
        $tahapIds = $lamaranIds->isEmpty() ? collect() : DB::table('N_WEB_CAREERS_Lamaran_Tahap')->whereIn('Lamaran_Id', $lamaranIds)->pluck('Id_Lamaran_Tahap');
        $tesIds = $tahapIds->isEmpty() ? collect() : DB::table('N_WEB_CAREERS_Lamaran_Tahap_Tes')->whereIn('Lamaran_Tahap_Id', $tahapIds)->pluck('Id_Lamaran_Tahap_Tes');
        $pengisianIds = $lamaranIds->isEmpty() ? collect() : DB::table('N_WEB_CAREERS_Formulir_Pengisian')->whereIn('Lamaran_Id', $lamaranIds)->pluck('Id_Formulir_Pengisian');

        $inti = ['N_WEB_CAREERS_Lamaran', 'N_WEB_CAREERS_Lamaran_Tahap', 'N_WEB_CAREERS_Lamaran_Tahap_Tes', 'N_WEB_CAREERS_Formulir_Pengisian', 'N_WEB_CAREERS_Users'];
        $berkolom = fn (string $kolom) => array_values(array_diff(
            DB::table('INFORMATION_SCHEMA.COLUMNS')->where('TABLE_NAME', 'like', 'N[_]WEB[_]CAREERS[_]%')->where('COLUMN_NAME', $kolom)->pluck('TABLE_NAME')->all(),
            $inti,
        ));

        $n = DB::transaction(function () use ($berkolom, $userIds, $lamaranIds, $tahapIds, $tesIds, $pengisianIds) {
            $n = 0;
            $hapus = function (string $tabel, string $kolom, $ids) use (&$n) {
                foreach ($ids->chunk(500) as $bagian) {
                    $n += DB::table($tabel)->whereIn($kolom, $bagian->all())->delete();
                }
            };

            // Daun dulu, akar terakhir.
            foreach ($berkolom('Lamaran_Tahap_Tes_Id') as $t) {
                $hapus($t, 'Lamaran_Tahap_Tes_Id', $tesIds);
            }
            foreach ($berkolom('Formulir_Pengisian_Id') as $t) {
                $hapus($t, 'Formulir_Pengisian_Id', $pengisianIds);
            }
            foreach ($berkolom('Lamaran_Tahap_Id') as $t) {
                $hapus($t, 'Lamaran_Tahap_Id', $tahapIds);
            }
            foreach ($berkolom('Lamaran_Id') as $t) {
                $hapus($t, 'Lamaran_Id', $lamaranIds);
            }
            $hapus('N_WEB_CAREERS_Formulir_Pengisian', 'Id_Formulir_Pengisian', $pengisianIds);
            $hapus('N_WEB_CAREERS_Lamaran_Tahap_Tes', 'Id_Lamaran_Tahap_Tes', $tesIds);
            $hapus('N_WEB_CAREERS_Lamaran_Tahap', 'Id_Lamaran_Tahap', $tahapIds);
            $hapus('N_WEB_CAREERS_Lamaran', 'Id_Lamaran', $lamaranIds);
            $hapus('N_WEB_CAREERS_Users', 'Id_Users', $userIds);

            return $n;
        });

        $this->info("Bersih: {$userIds->count()} akun contoh, {$lamaranIds->count()} lamaran ({$n} baris) dihapus.");

        return self::SUCCESS;
    }
}
