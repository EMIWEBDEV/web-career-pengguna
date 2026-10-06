<?php

namespace App\Support\Career;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * WEB CAREERS — VERSI ALUR SELEKSI, DAN PEMINDAHAN ROMBONGAN YANG BERJALAN.
 *
 * ══ MASALAH YANG DIPECAHKAN ═════════════════════════════════════════════════
 *
 * Menyunting alur yang sedang dipakai dulu MENIMPA definisinya di tempat.
 * Datanya selamat — tiap lamaran memegang salinan tahapnya sendiri — tapi
 * PAPAN worklist digambar dari master. Begitu satu tahap dibuang atau labelnya
 * diganti, kandidat rombongan lama jatuh ke kolom cadangan "Tahap di luar
 * alur", sementara kartunya tetap berbunyi "Tahap 3 dari 9".
 *
 * Papannya yang berbohong, bukan datanya. Dan itu lebih berbahaya, karena
 * keputusan diambil dari papan.
 *
 * ══ CARA KERJANYA ═══════════════════════════════════════════════════════════
 *
 * Alur yang SUDAH DIPAKAI tidak lagi ditimpa. Penyuntingannya MELAHIRKAN versi
 * baru: baris alur baru dengan Kode baru, `Induk_Id` menunjuk keluarga yang
 * sama, `Versi` bertambah satu. Program diarahkan ke versi baru; versi lama
 * dinonaktifkan — hilang dari pemilih alur, tapi TETAP tergambar di papan,
 * sebab AlurKolom mencarinya lewat Id, bukan lewat Flag_Aktif.
 *
 * Hasilnya dua rombongan punya Master_Alur_Id yang berbeda, dan seluruh
 * lapisan tampilan yang sudah ada langsung bekerja benar:
 *
 *   · AlurKolom::susun()   menggabungkan kolom kedua versi, menandai yang
 *                          bukan milik versi sekarang sebagai "alur lama";
 *   · penyaring alur di worklist basis job vacancy memisahkan keduanya;
 *   · pemilih alur di Program Kegiatan hanya menawarkan yang aktif.
 *
 * ══ KENAPA KODE VERSI BARU DIBANGKITKAN, BUKAN "_V2" ════════════════════════
 *
 * `Master_Alur.Kode` adalah VARCHAR(30) dan sudah ber-UNIQUE. Di production ada
 * kode yang panjangnya TEPAT 30 karakter — tidak tersisa satu huruf pun untuk
 * akhiran. Pola penamaan yang harus muat di kolom akan patah diam-diam; kunci
 * asing (`Induk_Id`) tidak pernah.
 *
 * ══ SIAPA YANG IKUT PINDAH ══════════════════════════════════════════════════
 *
 * Keputusannya PER KANDIDAT, bukan per alur. Melarang seluruh migrasi hanya
 * karena satu orang sudah diterima akan menghukum lima puluh orang lain yang
 * justru butuh dipindahkan — padahal yang harus dilindungi cuma satu orang itu.
 *
 *   BERJALAN, tahapnya masih ada di versi baru   → ikut
 *   BERJALAN, tahapnya DIBUANG di versi baru     → tidak (memindahkannya berarti
 *                                                  mencabut orang dari tahap yang
 *                                                  sedang ia kerjakan)
 *   sudah LULUS / gugur / mundur / talent pool   → tidak pernah
 *
 * ══ APA YANG TIDAK PERNAH DITULIS ULANG ═════════════════════════════════════
 *
 * Tahap yang sudah SELESAI beserta nilai, verdict, berkas, dan jejak
 * keputusannya. Itu bukti, dan bukti tidak disunting. Yang disusun ulang hanya
 * tahap yang BELUM dijalani.
 */
class VersiAlur
{
    /** Batas panjang kolom Master_Alur.Kode. */
    private const PANJANG_KODE = 30;

    /**
     * Status tahap yang BELUM DIJALANI — hanya baris inilah yang boleh disusun
     * ulang saat migrasi.
     *
     * Dua nilai, bukan satu: 'MENUNGGU' yang dipakai mesin sekarang, dan
     * 'BELUM' yang sempat dipakai muatan lama. Menyebut salah satunya saja
     * membuat migrasi diam-diam menyisakan baris tahap versi lama di tengah
     * susunan versi baru.
     */
    private const BELUM_DIJALANI = ['MENUNGGU', 'BELUM'];

    /**
     * Nomor sementara saat menomori ulang tahap kandidat.
     *
     * N_WEB_CAREERS_Lamaran_Tahap punya UNIQUE (Lamaran_Id, Urutan). Menggeser
     * baris satu per satu langsung ke nomor tujuannya akan menabrak baris yang
     * NOMORNYA BELUM SEMPAT bergeser — urutan 4 dipindah ke 3 sementara 3 masih
     * ditempati. Semua digeser ke wilayah kosong ini dulu, baru ke tujuannya.
     */
    private const OFFSET_SEMENTARA = 9000;

    /** Kolom versi sudah terpasang? Selama belum, seluruh kelas ini diam. */
    public static function siap(): bool
    {
        static $siap = null;

        if ($siap === null) {
            try {
                $siap = Skema::adaKolom('N_WEB_CAREERS_Master_Alur', 'Induk_Id')
                    && Skema::adaTabel('N_WEB_CAREERS_Alur_Migrasi');
            } catch (\Throwable $e) {
                $siap = false;
            }
        }

        return $siap;
    }

    /**
     * Alur ini sudah dipakai lamaran mana pun?
     *
     * Hanya alur terpakai yang perlu diversikan. Alur yang belum pernah
     * dijalani siapa pun tidak punya rombongan untuk dilindungi, dan
     * melahirkan versi untuknya cuma menumpuk baris yang tak berarti apa-apa.
     */
    public static function terpakai(int $alurId): bool
    {
        return DB::table('N_WEB_CAREERS_Lamaran')->where('Master_Alur_Id', $alurId)->exists();
    }

    /**
     * PRATINJAU — siapa yang akan ikut pindah, siapa yang tidak, dan kenapa.
     *
     * Dihitung dari kode tahap yang AKAN dimiliki versi baru, jadi bisa dijawab
     * sebelum satu baris pun ditulis. Inilah yang membuat tombol "terapkan ke
     * yang sedang berjalan" bisa menyebutkan akibatnya lebih dulu — bukan
     * sesudahnya, saat sudah tidak bisa dibatalkan.
     *
     * @param  array<int, string>  $kodeBaru  kode tahap versi baru
     * @return array{ikut:int, tidak:int, rincian:array<int, array{kode:string, nama:string|null, tahap:string|null, sebab:string|null, ikut:bool}>}
     */
    public static function pratinjau(int $alurId, array $kodeBaru): array
    {
        $set = array_flip(array_map('strval', $kodeBaru));

        $lamaran = DB::table('N_WEB_CAREERS_Lamaran as l')
            ->leftJoin('N_WEB_CAREERS_Users as u', 'u.Id_Users', '=', 'l.Id_Users')
            ->where('l.Master_Alur_Id', $alurId)
            ->orderBy('l.Id_Lamaran')
            ->get(['l.Id_Lamaran', 'l.Kode', 'l.Status', 'l.Urutan_Tahap', 'u.Nama']);

        if ($lamaran->isEmpty()) {
            return ['ikut' => 0, 'tidak' => 0, 'rincian' => []];
        }

        return self::nilaiDaftar($lamaran, $set);
    }

    /**
     * PRATINJAU untuk perubahan alur DI TINGKAT PROGRAM.
     *
     * Bedanya dengan pratinjau() cuma cakupannya: di sini yang dinilai adalah
     * pelamar SATU PROGRAM yang alurnya belum sama dengan alur tujuan — bukan
     * seluruh pemakai satu alur. Aturan kelayakannya sama persis, dan memang
     * harus sama: dua pintu yang menilai hal yang sama dengan ukuran berbeda
     * adalah bug yang menunggu giliran.
     */
    public static function pratinjauProgram(int $programId, int $alurBaruId): array
    {
        $set = self::kodeTahapSet($alurBaruId);

        $lamaran = DB::table('N_WEB_CAREERS_Lamaran as l')
            ->leftJoin('N_WEB_CAREERS_Users as u', 'u.Id_Users', '=', 'l.Id_Users')
            ->where('l.Program_Id', $programId)
            ->where(fn ($w) => $w->whereNull('l.Master_Alur_Id')->orWhere('l.Master_Alur_Id', '!=', $alurBaruId))
            ->orderBy('l.Id_Lamaran')
            ->get(['l.Id_Lamaran', 'l.Kode', 'l.Status', 'l.Urutan_Tahap', 'u.Nama']);

        return self::nilaiDaftar($lamaran, $set);
    }

    /**
     * Pindahkan pelamar SATU PROGRAM ke alur barunya.
     *
     * @return array{ikut:int, tidak:int, batch:string}
     */
    public static function migrasikanProgram(int $programId, int $alurBaruId, ?int $userId, string $userName): array
    {
        $lamaran = DB::table('N_WEB_CAREERS_Lamaran')
            ->where('Program_Id', $programId)
            ->where(fn ($w) => $w->whereNull('Master_Alur_Id')->orWhere('Master_Alur_Id', '!=', $alurBaruId))
            ->get(['Id_Lamaran', 'Kode', 'Status', 'Urutan_Tahap', 'Total_Tahap', 'Master_Alur_Id']);

        return self::jalankanMigrasi($lamaran, $alurBaruId, $userId, $userName, 'program #'.$programId);
    }

    /** @return array{ikut:int, tidak:int, rincian:array} */
    private static function nilaiDaftar($lamaran, array $set): array
    {
        if ($lamaran->isEmpty()) {
            return ['ikut' => 0, 'tidak' => 0, 'rincian' => []];
        }

        $tahapPer = self::tahapPer($lamaran->pluck('Id_Lamaran')->all());

        $ikut = 0;
        $tidak = 0;
        $rincian = [];

        foreach ($lamaran as $l) {
            $daftar = $tahapPer->get($l->Id_Lamaran, collect());
            [$boleh, $sebab] = self::nilaiKelayakan($l, $daftar, $set);
            $ikut += $boleh ? 1 : 0;
            $tidak += $boleh ? 0 : 1;

            // Riwayat yang tidak ada di alur tujuan bukan lagi penghalang,
            // tapi tetap perlu disebut: baris-baris itu akan tinggal di
            // timeline kandidat dengan nama lamanya, dan admin yang membuka
            // portalnya besok berhak tahu kenapa.
            $riwayatLain = $boleh
                ? collect($daftar)
                    ->whereNotIn('Status', self::BELUM_DIJALANI)
                    ->filter(fn ($t) => ! isset($set[(string) $t->Kode]))
                    ->pluck('Label')->filter()->values()->all()
                : [];

            $rincian[] = [
                'kode' => $l->Kode,
                'nama' => $l->Nama ?? null,
                'status' => $l->Status,
                'tahap' => collect($daftar)->firstWhere('Status', 'BERJALAN')->Label ?? null,
                'sebab' => $sebab,
                'ikut' => $boleh,
                // Nama tahap yang sudah ia lewati dan tidak ada di alur tujuan.
                'riwayatLain' => $riwayatLain,
            ];
        }

        return ['ikut' => $ikut, 'tidak' => $tidak, 'rincian' => $rincian];
    }

    /** Kode tahap sebuah alur, sebagai himpunan untuk pencocokan cepat. */
    private static function kodeTahapSet(int $alurId): array
    {
        return array_flip(
            DB::table('N_WEB_CAREERS_Master_Alur_Tahap')
                ->where('Master_Alur_Id', $alurId)
                ->pluck('Kode')->map(fn ($k) => (string) $k)->all()
        );
    }

    /**
     * Lahirkan versi baru dari sebuah alur — header-nya saja.
     *
     * Tahapnya TIDAK disalin di sini: pemanggil langsung memanggil
     * simpanTahap() dengan susunan BARU yang sedang disimpan admin. Menyalin
     * tahap lama lebih dulu hanya untuk ditimpa detik berikutnya akan
     * meninggalkan baris hantu bila penyimpanannya gagal di tengah.
     *
     * @return int Id_Master_Alur versi baru
     */
    public static function lahirkan(int $alurId, array $data, ?int $userId, string $userName): int
    {
        $lama = DB::table('N_WEB_CAREERS_Master_Alur')->where('Id_Master_Alur', $alurId)->first();
        $induk = (int) ($lama->Induk_Id ?: $lama->Id_Master_Alur);

        $versi = (int) DB::table('N_WEB_CAREERS_Master_Alur')
            ->where(fn ($w) => $w->where('Induk_Id', $induk)->orWhere('Id_Master_Alur', $induk))
            ->max('Versi');
        $versi = max(1, $versi) + 1;

        return (int) DB::table('N_WEB_CAREERS_Master_Alur')->insertGetId([
            'Kode' => self::kodeVersi($data['nama'] ?? $lama->Nama, $versi),
            'Nama' => $data['nama'] ?? $lama->Nama,
            'Kategori' => $data['kategori'] ?? $lama->Kategori,
            'Deskripsi' => $data['deskripsi'] ?? $lama->Deskripsi,
            'Flag_Aktif' => 'Y',
            'Induk_Id' => $induk,
            'Versi' => $versi,
            'Created_At' => now(), 'Created_By' => $userName, 'Created_By_Id' => $userId,
            'Updated_At' => now(), 'Updated_By' => $userName, 'Updated_By_Id' => $userId,
        ], 'Id_Master_Alur');
    }

    /**
     * Pindahkan seluruh rujukan dari versi lama ke versi baru.
     *
     * ══ INI BAGIAN PALING BERBAHAYA DARI SELURUH FITUR ══════════════════════
     *
     * Empat tempat mengikat diri ke alur — dua lewat Id TAHAP, dua lewat kode
     * alur. Melahirkan versi tanpa membawa serta keempatnya berarti syarat
     * gugur berhenti berlaku dan jadwal kehilangan rujukan tahapnya, dan
     * TIDAK SATU GALAT PUN MUNCUL. Kandidat yang seharusnya tersaring lolos;
     * jadwal yang sudah dibuat menunjuk tahap yang bukan miliknya lagi.
     *
     * Pemetaan tahap lama → baru memakai KODE, bukan nomor urut — sebab kode
     * itulah identitasnya, dan urutan boleh berubah di versi baru. Tahap yang
     * kodenya tidak ada lagi di versi baru: rujukannya DIBIARKAN menunjuk
     * tahap lama, tidak dipaksa ke tahap mana pun. Menebak akan memindahkan
     * syarat gugur ke tahap yang bukan tempatnya.
     *
     * @return array{program:int, syarat:int, jadwal:int, masterJadwal:int}
     */
    public static function pindahkanRujukan(int $lamaId, int $baruId, ?int $userId, string $userName): array
    {
        $kodeLama = DB::table('N_WEB_CAREERS_Master_Alur')->where('Id_Master_Alur', $lamaId)->value('Kode');
        $kodeBaru = DB::table('N_WEB_CAREERS_Master_Alur')->where('Id_Master_Alur', $baruId)->value('Kode');

        // Peta Id tahap: lama → baru, dicocokkan lewat KODE.
        $tLama = DB::table('N_WEB_CAREERS_Master_Alur_Tahap')->where('Master_Alur_Id', $lamaId)->pluck('Kode', 'Id_Master_Alur_Tahap');
        $tBaru = DB::table('N_WEB_CAREERS_Master_Alur_Tahap')->where('Master_Alur_Id', $baruId)->pluck('Id_Master_Alur_Tahap', 'Kode');

        $peta = [];
        foreach ($tLama as $idLama => $kode) {
            if (isset($tBaru[$kode])) {
                $peta[(int) $idLama] = (int) $tBaru[$kode];
            }
        }

        $n = ['program' => 0, 'syarat' => 0, 'jadwal' => 0, 'masterJadwal' => 0];

        // 1. PROGRAM — menunjuk lewat kode alur.
        $n['program'] = DB::table('N_WEB_CAREERS_Program')
            ->where('Alur_Kode', $kodeLama)
            ->update([
                'Alur_Kode' => $kodeBaru,
                'Updated_At' => now(), 'Updated_By' => $userName, 'Updated_By_Id' => $userId,
            ]);

        // 2. MASTER JADWAL — juga lewat kode alur.
        if (Skema::adaTabel('N_WEB_CAREERS_Master_Jadwal')) {
            $n['masterJadwal'] = DB::table('N_WEB_CAREERS_Master_Jadwal')
                ->where('Alur_Kode', $kodeLama)
                ->update(['Alur_Kode' => $kodeBaru]);
        }

        // 3 & 4. SYARAT PROGRAM dan PENJADWALAN — lewat Id tahap.
        foreach ($peta as $idLama => $idBaru) {
            $n['syarat'] += DB::table('N_WEB_CAREERS_Program_Syarat')
                ->where('Master_Alur_Tahap_Id', $idLama)
                ->update(['Master_Alur_Tahap_Id' => $idBaru, 'Updated_At' => now(), 'Updated_By' => $userName]);

            if (Skema::adaTabel('N_WEB_CAREERS_Penjadwalan_Tahap')) {
                $n['jadwal'] += DB::table('N_WEB_CAREERS_Penjadwalan_Tahap')
                    ->where('Master_Alur_Tahap_Id', $idLama)
                    ->update(['Master_Alur_Tahap_Id' => $idBaru]);
            }
        }

        // Versi lama DINONAKTIFKAN, bukan dihapus. Hilang dari pemilih alur —
        // options('alur') menyaring Flag_Aktif — tapi tetap tergambar di papan,
        // sebab AlurKolom mencarinya lewat Id. Persis yang dibutuhkan rombongan
        // yang masih berjalan di sana.
        DB::table('N_WEB_CAREERS_Master_Alur')->where('Id_Master_Alur', $lamaId)->update([
            'Flag_Aktif' => 'N',
            'Updated_At' => now(),
            'Updated_By' => $userName.' (diganti versi baru)',
            'Updated_By_Id' => $userId,
        ]);

        return $n;
    }

    /**
     * Pindahkan rombongan yang MASIH BERJALAN ke versi baru.
     *
     * @return array{ikut:int, tidak:int, batch:string}
     */
    public static function migrasikan(int $lamaId, int $baruId, ?int $userId, string $userName): array
    {
        $lamaran = DB::table('N_WEB_CAREERS_Lamaran')
            ->where('Master_Alur_Id', $lamaId)
            ->get(['Id_Lamaran', 'Kode', 'Status', 'Urutan_Tahap', 'Total_Tahap', 'Master_Alur_Id']);

        return self::jalankanMigrasi($lamaran, $baruId, $userId, $userName, 'alur #'.$lamaId);
    }

    /**
     * Mesin migrasinya sendiri — dipakai dua pintu masuk.
     *
     * Menyunting ALUR (versi baru lahir) dan mengganti alur SEBUAH PROGRAM
     * adalah dua peristiwa berbeda dengan satu akibat yang sama persis:
     * rombongan berpindah dari satu definisi tahap ke definisi lain. Menyalin
     * mesinnya untuk masing-masing berarti dua salinan yang kelak berbeda
     * pendapat soal siapa yang layak pindah — dan tidak ada galat yang akan
     * memberi tahu salinan mana yang benar.
     *
     * `Alur_Dari_Id` diambil dari LAMARANNYA sendiri, bukan dari satu nilai
     * tetap: pada jalur program, satu penyimpanan bisa memindahkan rombongan
     * dari beberapa alur asal sekaligus.
     *
     * @return array{ikut:int, tidak:int, batch:string}
     */
    private static function jalankanMigrasi($lamaran, int $baruId, ?int $userId, string $userName, string $asal): array
    {
        $batch = 'MGR-'.strtoupper(Str::random(10));

        $tahapBaru = DB::table('N_WEB_CAREERS_Master_Alur_Tahap')
            ->where('Master_Alur_Id', $baruId)->orderBy('Urutan')->get();
        $set = array_flip($tahapBaru->pluck('Kode')->map(fn ($k) => (string) $k)->all());

        if ($lamaran->isEmpty() || $tahapBaru->isEmpty()) {
            return ['ikut' => 0, 'tidak' => 0, 'batch' => $batch];
        }

        $tahapPer = self::tahapPer($lamaran->pluck('Id_Lamaran')->all());
        $ikut = 0;
        $tidak = 0;
        $jejak = [];

        foreach ($lamaran as $l) {
            $daftar = $tahapPer->get($l->Id_Lamaran, collect());
            $kini = collect($daftar)->firstWhere('Status', 'BERJALAN');
            [$boleh, $sebab] = self::nilaiKelayakan($l, $daftar, $set);

            $sebelum = (int) $l->Urutan_Tahap;
            $totalSebelum = (int) $l->Total_Tahap;
            $sesudah = $sebelum;
            $totalSesudah = $totalSebelum;

            if ($boleh) {
                [$sesudah, $totalSesudah] = self::pindahkanSatu($l, $kini, $tahapBaru, $baruId, $userId, $userName);
                $ikut++;
            } else {
                $tidak++;
            }

            $jejak[] = [
                'Batch_Kode' => $batch,
                'Alur_Dari_Id' => (int) ($l->Master_Alur_Id ?: $baruId),
                'Alur_Ke_Id' => $baruId,
                'Lamaran_Id' => (int) $l->Id_Lamaran,
                'Ikut' => $boleh ? 'Y' : 'T',
                'Sebab' => $sebab,
                'Tahap_Sebelum' => $sebelum,
                'Tahap_Sesudah' => $sesudah,
                'Total_Sebelum' => $totalSebelum,
                'Total_Sesudah' => $totalSesudah,
                'Created_At' => now(), 'Created_By' => $userName, 'Created_By_Id' => $userId,
            ];
        }

        foreach (array_chunk($jejak, 100) as $potong) {
            DB::table('N_WEB_CAREERS_Alur_Migrasi')->insert($potong);
        }

        Log::channel('web_career')->info(
            "[ALUR] migrasi {$batch}: {$asal} -> alur #{$baruId}, {$ikut} ikut, {$tidak} tidak."
        );

        return ['ikut' => $ikut, 'tidak' => $tidak, 'batch' => $batch];
    }

    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Pindahkan SATU lamaran.
     *
     * Yang disentuh HANYA tahap yang belum dijalani. Tahap yang sudah SELESAI —
     * berikut nilai, verdict, berkas, dan jejak keputusannya — tidak ditulis
     * ulang sebaris pun: itu bukti bahwa pekerjaan itu benar-benar terjadi.
     * Tahap yang sedang BERJALAN juga tidak disentuh; memindahkan orang dari
     * tahap yang sedang ia kerjakan bukan migrasi, itu pembatalan.
     *
     * @return array{0:int, 1:int}  [urutan sesudah, total sesudah]
     */
    private static function pindahkanSatu(object $l, ?object $kini, $tahapBaru, int $baruId, ?int $userId, string $userName): array
    {
        $kodeKini = (string) ($kini->Kode ?? '');
        $tujuanKini = $tahapBaru->firstWhere('Kode', $kodeKini);

        // Tanpa rumah di alur tujuan tak ada yang bisa dikerjakan. Kelayakan
        // sudah menolaknya lebih dulu; ini penjaga terakhir supaya pemanggil
        // baru tidak diam-diam melewatinya.
        if (! $tujuanKini) {
            return [(int) $l->Urutan_Tahap, (int) $l->Total_Tahap];
        }

        $urutTarget = (int) $tujuanKini->Urutan;

        // Tahap yang BELUM dijalani dibuang, lalu disusun ulang dari versi baru.
        $dibuang = DB::table('N_WEB_CAREERS_Lamaran_Tahap')
            ->where('Lamaran_Id', $l->Id_Lamaran)
            ->whereIn('Status', self::BELUM_DIJALANI)
            ->pluck('Id_Lamaran_Tahap')->all();

        if ($dibuang) {
            DB::table('N_WEB_CAREERS_Lamaran_Tahap_Tes')->whereIn('Lamaran_Tahap_Id', $dibuang)->delete();
            DB::table('N_WEB_CAREERS_Lamaran_Tahap')->whereIn('Id_Lamaran_Tahap', $dibuang)->delete();
        }

        // ══ PENOMORAN ULANG: BERURUTAN, BUKAN MENYALIN NOMOR MASTER ════════
        //
        // Bentuk sebelumnya memberi tiap baris nomor tahap yang SAMA dengan
        // nomornya di master, dan melewati baris yang kodenya tak ada di sana.
        // Dua akibatnya buruk. Baris riwayat yang kodenya hilang tertinggal di
        // nomor lamanya, lalu baris lain dipindahkan tepat ke nomor itu —
        // UNIQUE (Lamaran_Id, Urutan) menolak, dan migrasinya gagal seluruhnya.
        // Dan bila kebetulan tidak bertabrakan, urutannya bisa berlubang.
        //
        // Yang dinomori sekarang adalah TIMELINE KANDIDAT: riwayat dulu, sesuai
        // urutan ia benar-benar menjalaninya, lalu tahap yang sedang berjalan,
        // lalu sisa alur baru. Rapat, tanpa lubang, tanpa tabrakan — dan
        // nomornya tidak perlu sama dengan master, karena yang mencocokkan
        // papan maupun penjadwalan adalah KODE, bukan nomor.
        //
        // DUA LANGKAH, lewat wilayah nomor kosong: UNIQUE menolak keadaan
        // antara di mana dua baris sesaat memakai nomor yang sama.
        $tersisa = DB::table('N_WEB_CAREERS_Lamaran_Tahap')
            ->where('Lamaran_Id', $l->Id_Lamaran)->orderBy('Urutan')
            ->get(['Id_Lamaran_Tahap', 'Kode', 'Urutan']);

        $nomor = [];
        $n = 0;
        foreach ($tersisa as $t) {
            $nomor[(int) $t->Id_Lamaran_Tahap] = ++$n;
        }

        $posisiKini = $kini ? ($nomor[(int) $kini->Id_Lamaran_Tahap] ?? $n) : $n;

        foreach ($tersisa as $i => $t) {
            if ((int) $t->Urutan === $nomor[(int) $t->Id_Lamaran_Tahap]) {
                continue;
            }

            DB::table('N_WEB_CAREERS_Lamaran_Tahap')
                ->where('Id_Lamaran_Tahap', $t->Id_Lamaran_Tahap)
                ->update(['Urutan' => self::OFFSET_SEMENTARA + $i]);
        }

        foreach ($tersisa as $t) {
            if ((int) $t->Urutan === $nomor[(int) $t->Id_Lamaran_Tahap]) {
                continue;
            }

            DB::table('N_WEB_CAREERS_Lamaran_Tahap')
                ->where('Id_Lamaran_Tahap', $t->Id_Lamaran_Tahap)
                ->update([
                    'Urutan' => $nomor[(int) $t->Id_Lamaran_Tahap],
                    'Updated_At' => now(), 'Updated_By' => $userName,
                ]);
        }

        $sudahAda = DB::table('N_WEB_CAREERS_Lamaran_Tahap')
            ->where('Lamaran_Id', $l->Id_Lamaran)->pluck('Kode')->map(fn ($k) => (string) $k)->all();

        // Tahap versi baru SESUDAH tahap yang sedang berjalan, yang belum
        // dimiliki kandidat ini, ditambahkan sebagai BELUM — melanjutkan
        // penomoran timeline-nya, bukan memakai nomor master.
        $u = $n;

        foreach ($tahapBaru as $t) {
            if ((int) $t->Urutan <= $urutTarget || in_array((string) $t->Kode, $sudahAda, true)) {
                continue;
            }

            $u++;

            $idTahapBaru = DB::table('N_WEB_CAREERS_Lamaran_Tahap')->insertGetId([
                'Lamaran_Id' => (int) $l->Id_Lamaran,
                'Master_Alur_Tahap_Id' => (int) $t->Id_Master_Alur_Tahap,
                'Urutan' => $u,
                'Kode' => $t->Kode,
                'Label' => $t->Label,
                'Tipe_Tahap_Kode' => $t->Tipe_Tahap_Kode,
                'Provider' => $t->Provider ?: 'INTERNAL',
                'Keputusan_Mode' => $t->Mode_Keputusan_Kode,
                'Formulir_Kode' => $t->Formulir_Kode,
                'Status' => 'BELUM',
                'Mode_Pengumuman' => $t->Mode_Pengumuman,
                'Flag_Notifikasi' => $t->Flag_Notifikasi,
                'Urutan_Aktivitas' => $t->Urutan_Aktivitas,
                'Siap_Diputus' => 'T',
                'Flag_Bypass' => 'T',
                'Flag_Tuntas' => $t->Flag_Tuntas ?: 'T',
                'Hold_Flag' => 'T',
                'Flag_Talent_Pool' => $t->Flag_Talent_Pool ?: 'T',
                'Flag_Upload_Hasil' => $t->Flag_Upload_Hasil,
                'Flag_Wajib_Upload' => $t->Flag_Wajib_Upload,
                // Aturan batas pengisian formulir ikut dibekukan — lihat BatasIsi.
                ...BatasIsi::snapshot($t),
                'Created_At' => now(), 'Created_By' => $userName, 'Created_By_Id' => $userId,
                'Updated_At' => now(), 'Updated_By' => $userName, 'Updated_By_Id' => $userId,
            ], 'Id_Lamaran_Tahap');

            // ══ AKTIVITASNYA IKUT DIBEKUKAN ════════════════════════════════
            //
            // Tanpa ini, tahap baru lahir KOSONG — punya baris tahap, tapi nol
            // baris aktivitas. Worklist dan Penjadwalan sama-sama meng-INNER
            // JOIN Lamaran_Tahap_Tes, jadi kandidat yang berhenti di tahap
            // seperti itu MENGHILANG dari kedua papan: tidak bisa dijadwalkan,
            // tidak bisa dinilai, tidak bisa dilanjutkan — dan tidak ada satu
            // pun galat yang muncul. Persis yang terjadi pada LMR-VFZMGSW5
            // sesudah alurnya dipindah ke "Wawancara Management dan MCU".
            //
            // pastikanSubTes() dipakai ulang, bukan disalin: ia sudah menangani
            // master yang tak punya aktivitas (dibuatkan satu dari tahap itu
            // sendiri) dan aman dipanggil berulang.
            (new LamaranService())->pastikanSubTes((int) $idTahapBaru);
        }

        DB::table('N_WEB_CAREERS_Lamaran')->where('Id_Lamaran', $l->Id_Lamaran)->update([
            'Master_Alur_Id' => $baruId,
            'Urutan_Tahap' => $posisiKini,
            'Total_Tahap' => $u,
            'Updated_At' => now(), 'Updated_By' => $userName, 'Updated_By_Id' => $userId,
        ]);

        return [$posisiKini, $u];
    }

    /**
     * Boleh ikut pindah? — beserta SEBAB bila tidak.
     *
     * Sebabnya wajib terisi saat jawabannya tidak. Pertanyaan yang akan
     * benar-benar ditanya berbulan kemudian bukan "berapa yang pindah",
     * melainkan "kenapa orang ini tidak".
     *
     * @param  array<string, int>  $kodeVersiBaru
     * @return array{0:bool, 1:string|null}
     */
    private static function nilaiKelayakan(object $l, $tahapList, array $kodeVersiBaru): array
    {
        if ($l->Status !== 'BERJALAN') {
            return [false, 'Sudah ditutup ('.strtolower(str_replace('_', ' ', (string) $l->Status)).') — riwayatnya tidak diubah.'];
        }

        $tahapList = collect($tahapList);
        $kini = $tahapList->firstWhere('Status', 'BERJALAN');

        if (! $kini) {
            return [false, 'Tidak punya tahap berjalan yang bisa dicocokkan.'];
        }

        $kode = (string) ($kini->Kode ?? '');
        if ($kode === '') {
            return [false, 'Tahapnya tidak punya kode identitas (data lama).'];
        }

        if (! isset($kodeVersiBaru[$kode])) {
            return [false, 'Sedang di tahap "'.($kini->Label ?? $kode).'" yang dibuang di versi baru.'];
        }

        // ══ RIWAYAT TIDAK MENGHALANGI PERPINDAHAN ═════════════════════
        //
        // Bentuk sebelumnya menolak siapa pun yang PERNAH melewati tahap yang
        // tidak ada di alur tujuan, dengan alasan baris riwayatnya tak punya
        // nomor di sana. Alasan itu salah menempatkan soalnya: nomor urut milik
        // SNAPSHOT kandidat, bukan milik master. Riwayat tidak perlu punya
        // padanan di alur tujuan — ia sudah terjadi, dan nomornya sudah ada.
        //
        // Akibat aturan lama itu nyata dan berat: alur MT yang disunting
        // mengganti "Psikotes" menjadi "Psikotes Tahap 1", dan seluruh rombongan
        // yang sudah melewatinya jadi mustahil dipindahkan — padahal tahap yang
        // sedang mereka jalani ADA di alur baru, dengan kode yang sama persis.
        // Yang tampak di layar kandidat: alur lama yang sudah tidak dipakai
        // siapa pun, lengkap dengan tahap-tahap yang sudah dihapus.
        //
        // Yang benar-benar wajib cuma satu, dan sudah diperiksa di atas: tahap
        // yang SEDANG dijalani harus ada di alur tujuan, supaya kandidat punya
        // tempat berdiri. Riwayatnya dibawa apa adanya — lihat pindahkanSatu(),
        // yang menomori ulang berurutan tanpa menyentuh isinya.
        return [true, null];
    }

    /** SELURUH tahap milik tiap lamaran — kelayakan dinilai dari keseluruhannya. */
    private static function tahapPer(array $lamaranIds)
    {
        if (! $lamaranIds) {
            return collect();
        }

        return MetrikRekrutmen::potongIn(
            fn () => DB::table('N_WEB_CAREERS_Lamaran_Tahap')->orderBy('Urutan'),
            'Lamaran_Id',
            $lamaranIds,
        )->groupBy('Lamaran_Id');
    }

    /**
     * Kode untuk versi baru — dibangkitkan, bukan disusun dari kode lama.
     *
     * Akhiran versinya dipesan lebih dulu dari jatah 30 karakter, bukan
     * ditempel di ujung: kode yang sudah mentok akan terpotong diam-diam
     * kembali menjadi kode yang sama, lalu menabrak constraint UNIQUE.
     */
    private static function kodeVersi(string $nama, int $versi): string
    {
        $akhiran = '_V'.$versi;
        $kode = KodeUnik::buat(
            'N_WEB_CAREERS_Master_Alur',
            'Kode',
            $nama,
            self::PANJANG_KODE - strlen($akhiran),
            'ALUR',
        ).$akhiran;

        // KodeUnik menjamin dasarnya unik; akhiran versi hanya menambah
        // kejelasan. Tetap diperiksa — dua penyimpanan berbarengan bisa
        // menghasilkan dasar yang sama sebelum salah satunya tersimpan.
        $n = 0;
        $asli = $kode;
        while (DB::table('N_WEB_CAREERS_Master_Alur')->where('Kode', $kode)->exists()) {
            $n++;
            $sisip = '_'.$n;
            $kode = substr($asli, 0, self::PANJANG_KODE - strlen($sisip)).$sisip;
            if ($n > 50) {
                $kode = 'ALUR_'.strtoupper(Str::random(20));
                break;
            }
        }

        return $kode;
    }
}
