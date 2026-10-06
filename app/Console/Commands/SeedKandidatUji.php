<?php

namespace App\Console\Commands;

use App\Support\Career\LamaranService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * WEB CAREER — PEMBUAT KANDIDAT UJI (DATA DUMMY).
 *
 * ══ KENAPA LEWAT LamaranService, BUKAN INSERT LANGSUNG ═════════════════════
 *
 * Satu lamaran bukan satu baris. Ia menurunkan satu baris Lamaran_Tahap per
 * tahap alur, masing-masing membekukan Flag_Tuntas, Flag_Talent_Pool,
 * Urutan_Aktivitas, Keputusan_Mode, dan Mode_Pengumuman milik master SAAT ITU;
 * lalu tiap tahap menurunkan lagi baris Lamaran_Tahap_Tes untuk aktivitasnya.
 *
 * Menulis semua itu dengan INSERT tangan berarti menyalin aturan pembekuan ke
 * tempat kedua — dan begitu Master Alur berubah, data uji akan berbentuk
 * berbeda dari data sungguhan tanpa ada yang menyadarinya. Yang diuji lalu
 * bukan sistemnya, melainkan tiruan yang kebetulan mirip.
 *
 * Maka perintah ini memanggil jalur yang SAMA dengan pelamar sungguhan:
 * buatLamaran() untuk membuat, ketukPalu() untuk memajukan tahap. Apa pun yang
 * berlaku bagi kandidat asli otomatis berlaku di sini.
 *
 * ══ AMAN DIULANG & AMAN DIBERSIHKAN ════════════════════════════════════════
 *
 * Seluruh akun yang dibuat diberi penanda tetap:
 *   Email      : uji.<slug>+<nomor>@contoh.test   (domain .test, tak bisa dikirimi)
 *   Kode_Calon : UJI-<slug>-<nomor>
 *
 * Domain `.test` dipesan RFC 6761 dan tidak pernah bisa dirutekan keluar —
 * jadi tak ada satu pun email yang bisa nyasar ke orang sungguhan, bahkan bila
 * suatu saat data uji ini terbawa ke lingkungan yang mengirim email.
 *
 * `--bersihkan` menghapus persis apa yang dibuat penanda itu, tidak lebih.
 *
 * CONTOH
 *   php artisan karir:seed-kandidat --program=20 --jumlah=120
 *   php artisan karir:seed-kandidat --program=20 --jumlah=100 --tahap=2 --slug=psikotes
 *   php artisan karir:seed-kandidat --program=25 --jumlah=40 --slug=squad
 *   php artisan karir:seed-kandidat --program=43 --loker=104 --jumlah=250 --tahap=2 --slug=mt250
 *   php artisan karir:seed-kandidat --program=20 --bersihkan
 */
class SeedKandidatUji extends Command
{
    protected $signature = 'karir:seed-kandidat
        {--program= : Id program (lihat daftar bila dikosongkan)}
        {--jumlah=60 : Berapa kandidat dibuat (1–500)}
        {--slug=uji : Penanda kumpulan — dipakai email & Kode_Calon, dan dipakai --bersihkan}
        {--loker= : Id lowongan (Program_Posisi). Kosong = disebar rata ke seluruh lowongan program}
        {--tahap= : Parkir SEMUA kandidat di tahap ini (1-based). Kosong = sebaran corong}
        {--bersihkan : Hapus kandidat uji ber-slug ini, jangan membuat yang baru}
        {--email= : Pola email SUNGGUHAN untuk menguji surel, mis. fransbachtiar4+konf[n]@gmail.com ([n] = nomor kandidat)}
        {--seed=2026 : Benih pengacak; nilai sama menghasilkan sebaran yang sama}';

    protected $description = 'Buat kandidat uji yang tersebar di seluruh tahap alur sebuah program';

    /**
     * SEBARAN KANDIDAT PER TAHAP.
     *
     * Bobotnya menirukan corong rekrutmen sungguhan: menumpuk di tahap awal,
     * menipis ke atas. Sebaran rata akan menyembunyikan justru masalah yang
     * paling sering muncul — kolom kanban yang membengkak, penjadwalan massal
     * untuk puluhan orang sekaligus, paginasi yang baru pecah di angka besar.
     *
     * Kuncinya URUTAN tahap (1-based), nilainya bobot relatif.
     */
    private const BOBOT_TAHAP = [1 => 26, 2 => 24, 3 => 18, 4 => 14, 5 => 10, 6 => 5, 7 => 3];

    /**
     * Panjang terbesar slug yang masih muat di Kode_Calon.
     *
     * Kolomnya varchar(15) dan isinya berpola UJI-<SLUG>-<4 digit>, jadi yang
     * tersisa untuk slug tinggal enam huruf.
     *
     * Penjagaan ini ada karena kegagalannya sangat tidak informatif: slug yang
     * satu huruf kelewat panjang membuat SETIAP kandidat ditolak dengan
     * "String or binary data would be truncated" — kalimat yang tidak menyebut
     * kolom, tidak menyebut nilainya, dan tidak menyinggung slug sama sekali.
     * Meminta 300 kandidat lalu menerima 300 baris galat itu, satu per satu,
     * adalah cara yang sangat mahal untuk mengetahui bahwa namanya kepanjangan.
     */
    private const SLUG_MAKS = 6;

    /** Pola email sungguhan (--email), atau null = alamat .test yang tak bisa dikirimi. */
    private ?string $polaEmail = null;

    public function handle(LamaranService $svc): int
    {
        $slug = Str::slug((string) $this->option('slug')) ?: 'uji';

        if ($this->option('bersihkan')) {
            return $this->bersihkan($slug);
        }

        if (strlen($slug) > self::SLUG_MAKS) {
            $this->error(sprintf(
                "Slug '%s' kepanjangan (%d huruf). Maksimum %d — Kode_Calon cuma varchar(15) dan polanya UJI-<SLUG>-0000.",
                $slug,
                strlen($slug),
                self::SLUG_MAKS,
            ));
            $this->line('  Coba yang lebih pendek, mis. --slug='.substr($slug, 0, self::SLUG_MAKS));

            return self::FAILURE;
        }

        // --email: alamat SUNGGUHAN (plus-addressing satu kotak masuk) untuk
        // melihat surel uji sampai ke penerima. [n] wajib — tanpa itu semua
        // kandidat berebut satu alamat, dan akun kedua menimpa yang pertama.
        $polaEmail = trim((string) $this->option('email'));
        if ($polaEmail !== '' && (! str_contains($polaEmail, '[n]') || ! filter_var(str_replace('[n]', '1', $polaEmail), FILTER_VALIDATE_EMAIL))) {
            $this->error("Pola --email harus alamat sah yang memuat [n], mis. fransbachtiar4+konf[n]@gmail.com");

            return self::FAILURE;
        }
        $this->polaEmail = $polaEmail ?: null;

        $programId = (int) $this->option('program');
        if (! $programId) {
            return $this->tampilkanProgram();
        }

        $jumlah = max(1, min(500, (int) $this->option('jumlah')));
        mt_srand((int) $this->option('seed'));

        $program = DB::table('N_WEB_CAREERS_Program')->where('Id_Program', $programId)->first();
        if (! $program) {
            $this->error("Program #{$programId} tidak ada.");

            return self::FAILURE;
        }

        // Pembukaan & posisi WAJIB ADA — buatLamaran() menolak tanpa keduanya.
        // Dikatakan di sini, bukan dibiarkan gagal 120 kali berturut-turut.
        $pembukaan = DB::table('N_WEB_CAREERS_Pembukaan')->where('Program_Id', $programId)->orderByDesc('Id_Pembukaan')->first();
        if (! $pembukaan) {
            $this->error("Program '{$program->Nama}' belum punya pembukaan — kandidat tidak bisa melamar ke sana.");

            return self::FAILURE;
        }

        $posisi = DB::table('N_WEB_CAREERS_Program_Posisi')->where('Program_Id', $programId)->get();
        if ($posisi->isEmpty()) {
            $this->error("Program '{$program->Nama}' belum punya lowongan.");

            return self::FAILURE;
        }

        /*
         * --loker: SATU LOWONGAN SAJA.
         *
         * Tanpa ini kandidat disebar rata ke seluruh lowongan program, dan
         * pengujian yang butuh perbandingan timpang — 250 pelamar di satu
         * lowongan, 100 di lowongan sebelahnya — tidak bisa disusun sama
         * sekali. Justru bentuk timpang itulah yang menguji penjadwalan per
         * lowongan: kalau semua sama banyak, saringannya bisa saja tidak
         * bekerja tanpa ada yang menyadarinya.
         */
        $lokerId = (int) $this->option('loker');
        if ($lokerId) {
            $pilih = $posisi->firstWhere('Id_Program_Posisi', $lokerId);
            if (! $pilih) {
                $this->error("Lowongan #{$lokerId} bukan milik program '{$program->Nama}'. Yang ada:");
                foreach ($posisi as $x) {
                    $this->line("  #{$x->Id_Program_Posisi}  {$x->Posisi}".($x->Mpp_Ref ? "  ({$x->Mpp_Ref})" : ''));
                }

                return self::FAILURE;
            }
            $posisi = collect([$pilih]);
        }

        $alur = DB::table('N_WEB_CAREERS_Master_Alur')->where('Kode', $program->Alur_Kode)->first();
        $tahapAlur = $alur
            ? DB::table('N_WEB_CAREERS_Master_Alur_Tahap')->where('Master_Alur_Id', $alur->Id_Master_Alur)->orderBy('Urutan')->get()
            : collect();

        if ($tahapAlur->isEmpty()) {
            $this->error("Alur '{$program->Alur_Kode}' tidak punya tahap.");

            return self::FAILURE;
        }

        $this->info("Program : {$program->Nama} (#{$programId})");
        $this->info('Lowongan: '.($lokerId
            ? $posisi->first()->Posisi." (#{$lokerId})"
            : $posisi->count().' lowongan, disebar rata'));
        $this->info("Alur    : ".($alur->Nama ?? '-').' — '.$tahapAlur->count().' tahap');
        $this->info("Membuat : {$jumlah} kandidat, penanda '{$slug}'");

        /*
         * --tahap: SELURUH kandidat diparkir di satu tahap yang sama.
         *
         * Sebaran corong bawaan bagus untuk melihat papan yang hidup, tapi
         * buruk untuk menguji satu layar tertentu: meminta 300 kandidat demi
         * menguji penjadwalan massal lalu mendapat 42 di tahap yang dituju
         * bukan pengujian, melainkan tebak-tebakan.
         *
         * Diparkir berarti tahapnya BERJALAN dan aktivitasnya belum satu pun
         * selesai — persis keadaan yang dicari layar penjadwalan.
         */
        $parkir = $this->option('tahap') === null ? 0 : (int) $this->option('tahap');

        if ($parkir < 0 || $parkir > $tahapAlur->count()) {
            $this->error("Alur ini punya {$tahapAlur->count()} tahap — --tahap={$parkir} di luar jangkauan.");

            return self::FAILURE;
        }

        if ($parkir > 0) {
            $label = $tahapAlur->firstWhere('Urutan', $parkir)->Label ?? ('tahap '.$parkir);
            $this->info("Diparkir: SEMUA di tahap {$parkir} — {$label}");
        }

        $this->newLine();

        $sasaran = $parkir > 0
            ? array_fill(0, $jumlah, $parkir)
            : $this->rencanaSebaran($jumlah, $tahapAlur->count());
        $mulai = $this->nomorTerakhir($slug) + 1;

        // Nomor lima digit membuat Kode_Calon melewati 15 huruf, dan gejalanya
        // sama tidak informatifnya dengan slug kepanjangan. Dihentikan di sini,
        // sebelum satu baris pun ditulis.
        if ($mulai + $jumlah - 1 > 9999) {
            $this->error("Nomor kandidat akan melewati 9999 untuk slug '{$slug}'. Pakai slug lain, atau bersihkan yang lama.");

            return self::FAILURE;
        }

        $bar = $this->output->createProgressBar($jumlah);
        $bar->start();

        $berhasil = 0;
        $gagal = [];
        $perTahap = array_fill(1, $tahapAlur->count(), 0);

        for ($i = 0; $i < $jumlah; $i++) {
            $nomor = $mulai + $i;
            $henti = $sasaran[$i];

            try {
                $userId = $this->buatAkun($slug, $nomor);
                $pos = $posisi[$nomor % $posisi->count()];

                $hasil = $svc->buatLamaran($userId, (int) $pembukaan->Id_Pembukaan, (int) $pos->Id_Program_Posisi, null, null, null);
                if (! ($hasil['ok'] ?? false)) {
                    $gagal[] = "#{$nomor}: ".($hasil['pesan'] ?? 'ditolak');
                    $bar->advance();

                    continue;
                }

                $lamaranId = (int) ($hasil['lamaranId'] ?? $hasil['id'] ?? 0);
                // Yang dilaporkan tahap tempat kandidat BENAR-BENAR berhenti,
                // bukan tahap yang direncanakan. Keduanya bisa berbeda — dan
                // laporan yang menyebut rencana akan menyembunyikan justru hal
                // yang perlu diketahui: tahap mana yang menolak ditutup.
                $mendarat = $lamaranId && $henti > 1
                    ? $this->majukanSampai($svc, $lamaranId, $henti)
                    : 1;

                $perTahap[$mendarat] = ($perTahap[$mendarat] ?? 0) + 1;
                $berhasil++;
            } catch (\Throwable $e) {
                $gagal[] = "#{$nomor}: ".Str::limit($e->getMessage(), 120);
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->table(
            ['#', 'Tahap', 'Kandidat berhenti di sini'],
            $tahapAlur->values()->map(fn ($t, $k) => [$t->Urutan, $t->Label, $perTahap[$k + 1] ?? 0])->all()
        );

        $this->info("Selesai: {$berhasil} kandidat dibuat.");
        if ($gagal) {
            $this->newLine();
            $this->warn(count($gagal).' gagal:');
            foreach (array_slice($gagal, 0, 10) as $g) {
                $this->line('  '.$g);
            }
        }
        $this->newLine();
        $this->line("Hapus kembali: <options=bold>php artisan karir:seed-kandidat --slug={$slug} --bersihkan</>");

        return self::SUCCESS;
    }

    /** Daftar program yang bisa dipakai — supaya tak perlu menebak id-nya. */
    private function tampilkanProgram(): int
    {
        $rows = DB::table('N_WEB_CAREERS_Program as p')
            ->leftJoin('N_WEB_CAREERS_Master_Alur as a', 'a.Kode', '=', 'p.Alur_Kode')
            ->leftJoin('N_WEB_CAREERS_Pembukaan as b', 'b.Program_Id', '=', 'p.Id_Program')
            ->where('p.Status', 'BERJALAN')
            ->groupBy('p.Id_Program', 'p.Nama', 'p.Kategori', 'a.Nama')
            ->orderBy('p.Kategori')
            ->orderBy('p.Nama')
            ->get([
                'p.Id_Program', 'p.Nama', 'p.Kategori', 'a.Nama as AlurNama',
                DB::raw('COUNT(b.Id_Pembukaan) as Pembukaan'),
            ]);

        $this->warn('Sebutkan --program=<id>. Program yang siap dipakai:');
        $this->newLine();
        $this->table(
            ['Id', 'Kategori', 'Program', 'Alur', 'Pembukaan'],
            $rows->map(fn ($r) => [
                $r->Id_Program, $r->Kategori, Str::limit($r->Nama, 42),
                Str::limit($r->AlurNama ?: '— belum ada alur —', 32),
                $r->Pembukaan ?: '— belum ada —',
            ])->all()
        );
        $this->line('Yang tanpa alur atau tanpa pembukaan tidak bisa dipakai.');

        return self::INVALID;
    }

    /**
     * Berapa kandidat berhenti di tahap ke berapa.
     *
     * Dikembalikan sebagai daftar sepanjang $jumlah lalu diacak, supaya urutan
     * pembuatannya tidak sejalan dengan tahapnya — kalau berurutan, seluruh
     * kandidat tahap 1 akan punya Id_Lamaran berdekatan dan setiap pengujian
     * pengurutan jadi kebetulan benar.
     */
    private function rencanaSebaran(int $jumlah, int $jmlTahap): array
    {
        $bobot = [];
        for ($u = 1; $u <= $jmlTahap; $u++) {
            // Tahap di luar daftar bobot (alur lebih panjang dari 7) diberi
            // bobot terkecil — ekor corong memang selalu tipis.
            $bobot[$u] = self::BOBOT_TAHAP[$u] ?? 2;
        }
        $total = array_sum($bobot);

        $daftar = [];
        foreach ($bobot as $urutan => $b) {
            $n = (int) round($jumlah * $b / $total);
            for ($i = 0; $i < $n; $i++) {
                $daftar[] = $urutan;
            }
        }
        // Pembulatan bisa meleset satu-dua; ditambal di tahap pertama.
        while (count($daftar) < $jumlah) {
            $daftar[] = 1;
        }
        $daftar = array_slice($daftar, 0, $jumlah);
        shuffle($daftar);

        return $daftar;
    }

    /**
     * Akun kandidat uji. Dibuat sekali, dipakai ulang bila sudah ada.
     *
     * Kata sandinya bukan rahasia dan memang tidak perlu: akun ini tidak
     * mewakili siapa pun. Yang penting ia TIDAK PERNAH bisa menerima email —
     * domain .test menjamin itu di tingkat DNS, bukan lewat kesepakatan.
     */
    private function buatAkun(string $slug, int $nomor): int
    {
        // --email: alamat sungguhan (uji surel). Penanda Kode_Calon tetap
        // UJI-<SLUG>-<nomor>, jadi --bersihkan tetap menjangkaunya.
        $email = $this->polaEmail
            ? str_replace('[n]', (string) $nomor, $this->polaEmail)
            : "uji.{$slug}+{$nomor}@contoh.test";
        $ada = DB::table('N_WEB_CAREERS_Users')->where('Email', $email)->value('Id_Users');
        if ($ada) {
            return (int) $ada;
        }

        $now = now();

        return (int) DB::table('N_WEB_CAREERS_Users')->insertGetId([
            'Nama' => $this->namaAcak($nomor),
            'Email' => $email,
            'No_Hp' => '08'.str_pad((string) (11000000 + $nomor), 10, '0', STR_PAD_LEFT),
            'Password' => Hash::make('uji12345'),
            'Role' => 'KANDIDAT',
            'Status' => 'AKTIF',
            'Kode_Calon' => 'UJI-'.strtoupper($slug).'-'.str_pad((string) $nomor, 4, '0', STR_PAD_LEFT),
            // Terverifikasi sejak awal: kandidat yang belum memverifikasi email
            // tidak boleh melamar, dan yang sedang diuji di sini bukan alur
            // verifikasinya.
            'Flag_Email_Verified' => 'Y',
            'Email_Verified_At' => $now,
            'Created_At' => $now, 'Created_By' => 'SEEDER(uji)',
            'Updated_At' => $now, 'Updated_By' => 'SEEDER(uji)',
        ], 'Id_Users');
    }

    /**
     * Majukan lamaran sampai berhenti di tahap ke-$sampai.
     *
     * Memakai ketukPalu(), bukan UPDATE status: itulah yang menutup tahap
     * dengan benar — menandai aktivitasnya, mencatat jejak keputusan, dan
     * membuka tahap berikutnya lewat aturan yang sama dengan yang dipakai
     * admin sungguhan.
     *
     * @return int urutan tahap tempat kandidat BENAR-BENAR berhenti
     */
    private function majukanSampai(LamaranService $svc, int $lamaranId, int $sampai): int
    {
        for ($u = 1; $u < $sampai; $u++) {
            $tahapId = DB::table('N_WEB_CAREERS_Lamaran_Tahap')
                ->where('Lamaran_Id', $lamaranId)
                ->where('Urutan', $u)
                ->value('Id_Lamaran_Tahap');

            if (! $tahapId) {
                return $u;
            }

            // Tahap berisi tes TIDAK BISA diputus selama hasil aktivitasnya
            // belum tercatat — gerbang di ketukPalu(), dan gerbang itu benar:
            // meloloskan tes yang nilainya belum ada berarti memutus tanpa
            // dasar. Jadi hasilnya dicatat lebih dulu, persis seperti yang
            // dilakukan admin sebelum mengetuk palu.
            $this->tuntaskanAktivitas((int) $tahapId);

            $hasil = $svc->ketukPalu((int) $tahapId, 'LULUS', 'Data uji — diloloskan otomatis oleh seeder.', null, false);

            // Ditolak → berhenti di sini, dan itu yang dilaporkan. Memaksa maju
            // dengan UPDATE langsung akan menghasilkan kandidat yang mustahil
            // ada di sistem sungguhan, dan pengujian atasnya tidak berarti apa-apa.
            if (! ($hasil['ok'] ?? false)) {
                return $u;
            }
        }

        return $sampai;
    }

    /**
     * Catat hasil seluruh aktivitas PENENTU sebuah tahap sebagai lulus.
     *
     * Ditulis langsung ke baris aktivitas — bukan lewat jalur admin — karena
     * tiap jenis aktivitas punya jendelanya sendiri (kehadiran, nilai manual,
     * MCU, unggahan), dan yang dibutuhkan seeder cuma satu: aktivitasnya
     * berstatus selesai supaya tahapnya boleh diputus. Kolom yang disentuh
     * persis kolom yang dibaca gerbang di ketukPalu().
     */
    private function tuntaskanAktivitas(int $lamaranTahapId): void
    {
        $now = now();

        DB::table('N_WEB_CAREERS_Lamaran_Tahap_Tes')
            ->where('Lamaran_Tahap_Id', $lamaranTahapId)
            ->where('Flag_Selesai', '<>', 'Y')
            ->update([
                'Status' => 'SELESAI',
                'Hasil' => 'LULUS',
                'Flag_Selesai' => 'Y',
                'Waktu_Selesai' => $now,
                'Catatan' => 'Data uji — hasil dicatat otomatis oleh seeder.',
                'Updated_At' => $now,
                'Updated_By' => 'SEEDER(uji)',
            ]);
    }

    private function nomorTerakhir(string $slug): int
    {
        $kode = DB::table('N_WEB_CAREERS_Users')
            ->where('Kode_Calon', 'like', 'UJI-'.strtoupper($slug).'-%')
            ->orderByDesc('Id_Users')
            ->value('Kode_Calon');

        return (int) preg_replace('/\D/', '', (string) $kode);
    }

    /**
     * Hapus kandidat uji ber-slug ini, berikut seluruh jejaknya.
     *
     * Urutannya dari daun ke akar supaya tidak menabrak foreign key. Yang
     * disentuh HANYA baris milik akun ber-Kode_Calon `UJI-<SLUG>-`; tidak ada
     * satu pun kondisi yang bisa menjangkau kandidat sungguhan.
     */
    private function bersihkan(string $slug): int
    {
        $pola = 'UJI-'.strtoupper($slug).'-%';
        $userIds = DB::table('N_WEB_CAREERS_Users')->where('Kode_Calon', 'like', $pola)->pluck('Id_Users');

        if ($userIds->isEmpty()) {
            $this->warn("Tidak ada kandidat uji ber-slug '{$slug}'.");

            return self::SUCCESS;
        }

        $lamaranIds = DB::table('N_WEB_CAREERS_Lamaran')->whereIn('Id_Users', $userIds)->pluck('Id_Lamaran');
        $tahapIds = $lamaranIds->isEmpty() ? collect()
            : DB::table('N_WEB_CAREERS_Lamaran_Tahap')->whereIn('Lamaran_Id', $lamaranIds)->pluck('Id_Lamaran_Tahap');
        $pengisianIds = $lamaranIds->isEmpty() ? collect()
            : DB::table('N_WEB_CAREERS_Formulir_Pengisian')->whereIn('Lamaran_Id', $lamaranIds)->pluck('Id_Formulir_Pengisian');

        if (! $this->confirm(
            "Hapus {$userIds->count()} akun uji, {$lamaranIds->count()} lamaran, dan seluruh tahapnya?",
            true
        )) {
            return self::SUCCESS;
        }

        DB::transaction(function () use ($userIds, $lamaranIds, $tahapIds, $pengisianIds) {
            $hapus = function (string $tabel, string $kolom, $ids) {
                if ($ids->isEmpty()) {
                    return 0;
                }
                $n = 0;
                foreach ($ids->chunk(500) as $bagian) {
                    $n += DB::table($tabel)->whereIn($kolom, $bagian->all())->delete();
                }

                return $n;
            };

            // Peserta penjadwalan lebih dulu: barisnya menunjuk ke lamaran, dan
            // gelombang penjadwalannya sendiri sengaja TIDAK dihapus — ia bisa
            // memuat kandidat sungguhan juga.
            $hapus('N_WEB_CAREERS_Penjadwalan_Peserta', 'Lamaran_Id', $lamaranIds);

            // JEJAK YANG LAHIR SAAT DATA UJI DICOBA DI LAYAR — jadwal yang diatur,
            // berkas yang diunggah, keputusan yang diketuk, hold, ulang tahap,
            // Talent Pool. Tanpa ini barisnya tertinggal menunjuk lamaran yang
            // sudah tidak ada. Tabel yang belum dibuat di basis data ini dilewati.
            $ada = fn (string $t) => \App\Support\Career\Skema::adaTabel($t);
            // Dicicil 500-an: SQL Server menolak lebih dari 2100 parameter, dan
            // 500 kandidat × 7 tahap sudah 3500.
            $tesIds = $tahapIds->chunk(500)->flatMap(fn ($b) => DB::table('N_WEB_CAREERS_Lamaran_Tahap_Tes')
                ->whereIn('Lamaran_Tahap_Id', $b->all())->pluck('Id_Lamaran_Tahap_Tes'))->values();
            if ($ada(\App\Support\Career\JejakJadwal::TABEL)) {
                $hapus(\App\Support\Career\JejakJadwal::TABEL, 'Lamaran_Tahap_Tes_Id', $tesIds);
            }
            // Konfirmasi kehadiran: permintaan jadwal lain & snapshot CRM —
            // SESUDAH jejak (jejak menunjuk permintaan), SEBELUM aktivitasnya.
            foreach ([\App\Support\Career\KonfirmasiJadwal::T_MINTA, \App\Support\Career\KonfirmasiJadwal::T_CRM] as $tabel) {
                if ($ada($tabel)) {
                    $hapus($tabel, 'Lamaran_Tahap_Tes_Id', $tesIds);
                }
            }
            foreach ([
                ['N_WEB_CAREERS_Lamaran_Tes_Berkas', 'Lamaran_Id', $lamaranIds],
                // Proyeksi jawaban formulir (karir:seed-konfirmasi menyalinnya
                // dari lamaran sumber bersama formulir & berkasnya).
                ['N_WEB_CAREERS_Formulir_Jawaban_Index', 'Lamaran_Id', $lamaranIds],
                ['N_WEB_CAREERS_Lamaran_Tahap_Berkas', 'Lamaran_Id', $lamaranIds],
                ['N_WEB_CAREERS_Lamaran_Keputusan_Jejak', 'Lamaran_Id', $lamaranIds],
                ['N_WEB_CAREERS_Lamaran_Tahap_Hold', 'Lamaran_Id', $lamaranIds],
                ['N_WEB_CAREERS_Lamaran_Tahap_Tes_Riwayat', 'Lamaran_Tahap_Id', $tahapIds],
                ['N_WEB_CAREERS_Lamaran_Tahap_Riwayat', 'Lamaran_Id', $lamaranIds],
                ['N_WEB_CAREERS_Lamaran_Ulang', 'Lamaran_Id', $lamaranIds],
                ['N_WEB_CAREERS_Talent_Pool', 'Lamaran_Id', $lamaranIds],
            ] as [$tabel, $kolom, $ids]) {
                if ($ada($tabel)) {
                    $hapus($tabel, $kolom, $ids);
                }
            }
            $hapus('N_WEB_CAREERS_Formulir_Berkas', 'Formulir_Pengisian_Id', $pengisianIds);
            $hapus('N_WEB_CAREERS_Formulir_Pengisian', 'Lamaran_Id', $lamaranIds);
            $hapus('N_WEB_CAREERS_Lamaran_Tahap_Tes', 'Lamaran_Tahap_Id', $tahapIds);
            $hapus('N_WEB_CAREERS_Lamaran_Tahap', 'Lamaran_Id', $lamaranIds);
            $hapus('N_WEB_CAREERS_Lamaran', 'Id_Users', $userIds);
            $hapus('N_WEB_CAREERS_Users', 'Id_Users', $userIds);
        });

        $this->info("Bersih: {$userIds->count()} akun uji & {$lamaranIds->count()} lamaran dihapus.");

        return self::SUCCESS;
    }

    /** Nama Indonesia yang terbaca wajar — supaya papan tidak berisi "Dummy 41". */
    private function namaAcak(int $nomor): string
    {
        $depan = ['Adi', 'Bayu', 'Citra', 'Dewi', 'Eka', 'Fajar', 'Gita', 'Hadi', 'Indah', 'Joko',
            'Kartika', 'Lestari', 'Maya', 'Nanda', 'Oki', 'Putri', 'Rizky', 'Sari', 'Tono', 'Utami',
            'Vina', 'Wahyu', 'Yuda', 'Zahra', 'Bagus', 'Cahya', 'Dian', 'Endah', 'Firman', 'Galih'];
        $belakang = ['Pratama', 'Wijaya', 'Santoso', 'Nugroho', 'Hidayat', 'Kusuma', 'Ramadhan',
            'Setiawan', 'Maulana', 'Anggraini', 'Permana', 'Saputra', 'Handoko', 'Yulianti',
            'Firdaus', 'Susanto', 'Halim', 'Wibowo', 'Puspita', 'Gunawan'];

        return strtoupper($depan[$nomor % count($depan)].' '.$belakang[intdiv($nomor, count($depan)) % count($belakang)]);
    }
}
