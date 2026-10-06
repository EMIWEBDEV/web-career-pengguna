<?php

namespace App\Console\Commands;

use App\Http\Controllers\Career\Lamaran\LamaranController;
use App\Support\Career\KonfirmasiJadwal;
use App\Support\Career\SuratKonfirmasi;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Vinkla\Hashids\Facades\Hashids;

/**
 * DATA UJI — KONFIRMASI KEHADIRAN.
 *
 * Membuat kandidat uji (lewat karir:seed-kandidat, jalur pelamar sungguhan),
 * memarkirnya di tahap berjadwal ber-konfirmasi (Wawancara / FGD), lalu
 * menjadwalkan & menjawab undangan mereka lewat jalur yang SAMA dengan layar:
 * controller Atur Jadwal (LamaranController::subTesJadwal) dan layanan
 * KonfirmasiJadwal. Tidak ada INSERT tangan untuk status — kecuali memundurkan
 * jam (batas yang sudah lewat, surel "kemarin") yang memang tidak bisa dibuat
 * lewat layar hari ini.
 *
 * ══ PAKET "coba" (bawaan) — 7 kandidat untuk DICOBA SENDIRI ══
 *    Semuanya BELUM DIJADWALKAN — user sendiri yang menjadwalkan (satu per
 *    satu atau massal) lalu memainkan tiap jawaban. Kolom "rencana" hanya
 *    saran urutan uji: hadir, jadwal lain → setujui / tawarkan / tolak,
 *    mundur, pengingat manual, tidak menjawab sampai jadwal dimulai.
 *
 * ══ FORMULIR & BERKAS LENGKAP ══
 *    Tiap dummy disalin dari lamaran SUMBER yang lengkap (bawaan: berkas
 *    formulir terbanyak di program; --sumber=LMR-xxxx untuk memilih):
 *    formulir pendaftaran + foto verifikasi wajah, formulir tahap (CV, KTP,
 *    KK, ijazah, transkrip, pas foto, sertifikat), proyeksi jawaban,
 *    nilai/catatan tahap sebelumnya, berkas hasil tahap & unggahan tes.
 *    Identitas (nama, email, HP, NIK) diganti milik dummy. File di GCS TIDAK
 *    digandakan — baris berkas dummy menunjuk path yang sama.
 *
 * Batas konfirmasi = WAKTU MULAI jadwal (keputusan user 1 Okt 2026) —
 * tidak ada batas terpisah yang diatur.
 *
 * ══ PAKET "lengkap" — 13 skenario yang SUDAH dijalankan ══
 *    menunggu, akan hadir, minta jadwal lain, mundur, sudah dimulai tanpa
 *    jawaban, ditolak, disetujui, sudah diingatkan, dicatat tim lewat telepon,
 *    ditunda, siap diingatkan (2), ditawari waktu lain.
 *
 * Email memakai plus-addressing Gmail: fransbachtiar4+coba1@gmail.com dst.
 * SEMUANYA masuk ke kotak fransbachtiar4@gmail.com — alamat persisnya sudah
 * dipakai akun asli user, dan kolom Email UNIQUE.
 *
 * Surel berangkat begitu worker web-careers dijalankan:
 *     php artisan queue:work --stop-when-empty
 *
 * DILARANG di produksi — menolak berjalan di basis data web_career_hr atau
 * APP_ENV=production.
 */
class SeedKonfirmasiUji extends Command
{
    protected $signature = 'karir:seed-konfirmasi
        {--paket=coba : coba (7 kandidat untuk dicoba sendiri) | lengkap (13 skenario jadi)}
        {--program=43 : Id program uji (pembukaannya harus masih terbuka) — bawaan: MT ALUR BARU}
        {--tahap=5 : Urutan tahap Wawancara/FGD tempat kandidat diparkir — bawaan: Wawancara Management dan MCU}
        {--slug= : Penanda kandidat uji (maks 6 huruf). Kosong = coba / konf sesuai paket}
        {--email= : Pola email ([n] = nomor kandidat). Kosong = fransbachtiar4+<slug>[n]@gmail.com}
        {--sumber= : Kode lamaran sumber formulir & berkas (kosong = berkas formulir terbanyak di program)}
        {--bersihkan : Hapus kandidat uji ber-slug ini beserta seluruh jejak konfirmasinya}';

    protected $description = 'Data uji konfirmasi kehadiran (paket coba: 7 kandidat; paket lengkap: 13 skenario) ke Gmail uji';

    private const LINK = 'https://meet.google.com/evo-uji-konf';

    private const ADMIN = 'DEMO KONFIRMASI';

    public function handle(): int
    {
        $db = (string) config('database.connections.'.config('database.default').'.database');
        if (app()->environment('production') || strcasecmp($db, 'web_career_hr') === 0) {
            $this->error("DITOLAK: data uji tidak boleh dibuat di produksi (basis data '{$db}').");

            return self::FAILURE;
        }
        if (! KonfirmasiJadwal::siap()) {
            $this->error('Skema konfirmasi belum ada — jalankan docs/01-10-2026/01-konfirmasi-kehadiran.sql, lalu php artisan cache:clear file.');

            return self::FAILURE;
        }

        $paket = strtolower((string) $this->option('paket')) === 'lengkap' ? 'lengkap' : 'coba';
        $slug = (string) ($this->option('slug') ?: ($paket === 'lengkap' ? 'konf' : 'coba'));
        $pola = (string) ($this->option('email') ?: "fransbachtiar4+{$slug}[n]@gmail.com");
        $jumlah = $paket === 'lengkap' ? 13 : 7;

        if ($this->option('bersihkan')) {
            return Artisan::call('karir:seed-kandidat', ['--slug' => $slug, '--bersihkan' => true, '--no-interaction' => true], $this->output);
        }

        $programId = (int) $this->option('program');
        $tahap = (int) $this->option('tahap');

        // Paket yang sudah ada TIDAK ditambal: skenarionya bergantung pada
        // urutan & keadaan awal. Bersihkan dulu, lalu buat ulang dari nol.
        $sudah = DB::table('N_WEB_CAREERS_Users')->where('Kode_Calon', 'like', 'UJI-'.strtoupper($slug).'-%')->count();
        if ($sudah) {
            $this->warn("Paket '{$slug}' sudah ada ({$sudah} akun). Buat ulang dari nol:");
            $this->line("  php artisan karir:seed-konfirmasi --slug={$slug} --bersihkan");
            $this->line("  php artisan karir:seed-konfirmasi --paket={$paket}");

            return self::FAILURE;
        }

        // ── 1. Kandidat (jalur pelamar sungguhan) ─────────────────────────────
        $this->info("Paket '{$paket}': membuat {$jumlah} kandidat uji…");
        Artisan::call('karir:seed-kandidat', [
            '--program' => $programId,
            '--jumlah' => $jumlah,
            '--tahap' => $tahap,
            '--slug' => $slug,
            '--email' => $pola,
            '--no-interaction' => true,
        ], $this->output);

        $daftar = $this->kandidat($slug, $programId, $tahap)->take($jumlah)->values();
        if ($daftar->count() < $jumlah) {
            $this->error('Kandidat yang siap dijadwalkan hanya '.$daftar->count().' — periksa keluaran di atas.');

            return self::FAILURE;
        }

        // ── 2. Formulir & berkas lengkap, disalin dari lamaran sumber ────────
        $sumber = $this->sumber($programId, $slug);
        if ($sumber) {
            $this->info("Formulir & berkas disalin dari {$sumber->Kode} ({$sumber->Nama}, {$sumber->Berkas} berkas formulir)…");
            foreach ($daftar as $i => $k) {
                DB::transaction(fn () => $this->lengkapi($sumber, $k, $i + 1, $tahap));
            }
        } else {
            $this->warn('Tidak ada lamaran sumber berformulir lengkap di program ini — formulir dummy dibiarkan kosong.');
        }

        // Identitas "admin" untuk jejak — tanpa id akun: kabar tim jatuh ke
        // PIC loker sesuai basis data.
        session(['career_auth' => ['id' => null, 'nama' => self::ADMIN, 'role' => 'SUPERADMIN']]);

        $hasil = [];
        foreach ($daftar as $i => $k) {
            $no = $i + 1;
            try {
                [$keadaan, $coba] = $paket === 'coba' ? $this->skenarioCoba($no, $k) : [$this->skenarioLengkap($no, $k), ''];
                $hasil[] = [$no, $k->Nama, $k->Email, $keadaan, $coba];
            } catch (\Throwable $e) {
                $hasil[] = [$no, $k->Nama, $k->Email, 'GAGAL: '.$e->getMessage(), ''];
            }
        }

        $this->newLine();
        $this->table(['#', 'Kandidat', 'Email', 'Keadaan', $paket === 'coba' ? 'Rencana uji (saran)' : 'Yang bisa dicoba'], $hasil);

        // Tautan konfirmasi sisi kandidat — bisa dibuka langsung tanpa menunggu
        // surel (sama persis dengan tombol di email & portal).
        $tautan = [];
        foreach ($daftar as $i => $k) {
            $t = DB::table('N_WEB_CAREERS_Lamaran_Tahap_Tes')->where('Id_Lamaran_Tahap_Tes', $k->Id_Lamaran_Tahap_Tes)
                ->first(['Jadwal_Versi', 'Jadwal_Mulai', 'Konfirmasi_Status']);
            if ($t && $t->Jadwal_Mulai && $t->Konfirmasi_Status) {
                $tautan[] = [$i + 1, $k->Nama, KonfirmasiJadwal::tautan((int) $k->Id_Lamaran_Tahap_Tes, (int) $t->Jadwal_Versi, (string) $t->Jadwal_Mulai)];
            }
        }
        if ($tautan) {
            $this->newLine();
            $this->line('<options=bold>Tautan konfirmasi (sisi kandidat) — buka di peramban laptop ini:</>');
            foreach ($tautan as [$no, $nama, $url]) {
                $this->line("  #{$no} {$nama}");
                $this->line("     {$url}");
            }
        }

        $this->newLine();
        $antre = DB::table('N_WEB_CAREERS_Jobs')->count();
        $this->info("Surel di antrean web-careers: {$antre}. Kirim: php artisan queue:work --stop-when-empty");
        $this->line('Masuk Portal sebagai kandidat: email di tabel, kata sandi uji12345');
        $contoh = $daftar->first() ? KonfirmasiJadwal::konteks((int) $daftar->first()->Id_Lamaran_Tahap_Tes) : null;
        $tim = $contoh ? SuratKonfirmasi::penerimaTim($contoh) : null;
        $this->line('Kabar tim → '.($tim ? "{$tim['nama']} <{$tim['email']}>" : 'PIC loker belum punya email').' (dari basis data)');
        $this->line("Hapus kembali: php artisan karir:seed-konfirmasi --slug={$slug} --bersihkan");

        return self::SUCCESS;
    }

    /** Kandidat uji ber-slug ini di tahap ber-konfirmasi yang masih bisa dijadwalkan. */
    private function kandidat(string $slug, int $programId, int $tahap)
    {
        $tipe = DB::table('N_WEB_CAREERS_Master_Tipe_Tahap')->where('Flag_Konfirmasi', 'Y')->pluck('Kode')->all();

        return DB::table('N_WEB_CAREERS_Users as u')
            ->join('N_WEB_CAREERS_Lamaran as l', 'l.Id_Users', '=', 'u.Id_Users')
            ->join('N_WEB_CAREERS_Lamaran_Tahap as h', 'h.Lamaran_Id', '=', 'l.Id_Lamaran')
            ->join('N_WEB_CAREERS_Lamaran_Tahap_Tes as t', 't.Lamaran_Tahap_Id', '=', 'h.Id_Lamaran_Tahap')
            ->where('u.Kode_Calon', 'like', 'UJI-'.strtoupper($slug).'-%')
            ->where('l.Program_Id', $programId)
            ->where('l.Status', 'BERJALAN')
            ->where('h.Urutan', $tahap)
            ->where('h.Status', 'BERJALAN')
            ->where('t.Flag_Selesai', '<>', 'Y')
            ->whereNull('t.Jadwal_Mulai')
            ->where(fn ($w) => $w->whereIn('t.Tipe_Tahap_Kode', $tipe)->orWhere(fn ($x) => $x->whereNull('t.Tipe_Tahap_Kode')->whereIn('h.Tipe_Tahap_Kode', $tipe)))
            ->orderBy('u.Kode_Calon')
            ->get(['u.Id_Users', 'u.Nama', 'u.Email', 'u.Kode_Calon', 'l.Id_Lamaran', 't.Id_Lamaran_Tahap_Tes']);
    }

    // ════════════════════════════════════════════════════════════════════════
    //  PAKET "coba" — keadaan awal, langkahnya dilakukan user sendiri
    // ════════════════════════════════════════════════════════════════════════

    /**
     * Paket coba: SEMUA belum dijadwalkan. Yang dikembalikan hanya saran
     * rencana uji per kandidat — user sendiri yang menjadwalkan.
     *
     * @return array{0: string, 1: string} [keadaan, rencana uji]
     */
    private function skenarioCoba(int $no, object $k): array
    {
        $rencana = [
            1 => 'Atur Jadwal → buka email/portal → "Saya akan hadir"',
            2 => 'Jadwalkan → "Saya perlu jadwal lain" → Agenda: Setujui usulan',
            3 => 'Jadwalkan → "jadwal lain" → Agenda: Tawarkan waktu lain',
            4 => 'Jadwalkan → "jadwal lain" → Agenda: Tolak (jadwal semula tetap)',
            5 => 'Jadwalkan → "Saya tidak melanjutkan seleksi"',
            6 => 'Jadwalkan, biarkan → Agenda: Kirim pengingat (jeda 10 menit)',
            7 => 'Jadwalkan beberapa menit lagi, biarkan lewat → catat Hadir/Tidak hadir',
        ];

        return ['Belum dijadwalkan', $rencana[$no] ?? 'Atur Jadwal (satuan atau massal)'];

        return ['-', ''];
    }

    // ════════════════════════════════════════════════════════════════════════
    //  PAKET "lengkap" — 13 skenario yang sudah dijalankan sampai ujung
    // ════════════════════════════════════════════════════════════════════════

    private function skenarioLengkap(int $no, object $k): string
    {
        $id = (int) $k->Id_Lamaran_Tahap_Tes;
        $hari = $this->hariKerja(2 + intdiv($no - 1, 5));
        $mulai = $hari->copy()->setTime(9, 0)->addMinutes((($no - 1) % 5) * 60);

        $kandidat = $this->pelakuKandidat($k);
        if ($no === 5) {
            // Sudah dimulai dua jam lalu tanpa jawaban (undangan dianggap kemarin).
            // Atur Jadwal menolak waktu mulai yang sudah lewat (2 Okt 2026), jadi
            // penjadwalannya dilakukan "tiga jam lalu": jam dimundurkan sebentar,
            // lalu dipulihkan — semua cap waktunya tetap konsisten.
            Carbon::setTestNow(now()->subHours(3));
            try {
                $this->jadwalkan($id, now()->addHour()->setSecond(0));
            } finally {
                Carbon::setTestNow();
            }
            $this->mundurkan($id, kirimJam: -26);
            $this->buangUndanganAntre($k->Email);

            return 'Jadwal sudah mulai tanpa jawaban — catat kehadirannya';
        }

        $this->jadwalkan($id, $mulai);
        $usulan = [
            ['tanggal' => $this->hariKerja(5)->format('Y-m-d'), 'bagian' => 'PAGI'],
            ['tanggal' => $this->hariKerja(6)->format('Y-m-d'), 'bagian' => 'SIANG'],
        ];
        $mintaLain = fn () => KonfirmasiJadwal::jawab($id, $this->versi($id), KonfirmasiJadwal::JADWAL_LAIN, [
            'alasan' => 'BENTROK_KULIAH',
            'catatan' => 'Ada ujian akhir semester sampai Jumat.',
            'usulan' => $usulan,
        ], $kandidat);
        $idMinta = fn () => (int) DB::table(KonfirmasiJadwal::T_MINTA)->where('Lamaran_Tahap_Tes_Id', $id)->orderByDesc('Id_Jadwal_Permintaan')->value('Id_Jadwal_Permintaan');

        switch ($no) {
            case 1:
                return 'Menunggu jawaban — undangan berisi tombol Konfirmasi';

            case 2:
                $this->wajibOk(KonfirmasiJadwal::jawab($id, $this->versi($id), KonfirmasiJadwal::AKAN_HADIR, [], $kandidat), 'jawab');

                return 'Akan hadir — tanda terima';

            case 3:
                $this->wajibOk($mintaLain(), 'minta jadwal lain');

                return 'Minta jadwal lain (TERBUKA) — proses di Agenda Seleksi';

            case 4:
                // Sama dengan formulir kandidat: alasan wajib, centang "saya
                // mengerti" ikut terkirim (diperiksa server sejak 2 Okt 2026).
                $this->wajibOk(KonfirmasiJadwal::jawab($id, $this->versi($id), KonfirmasiJadwal::MUNDUR, [
                    'alasan' => 'TAWARAN_LAIN',
                    'catatan' => 'Sudah menerima tawaran di perusahaan lain.',
                    'paham' => true,
                ], $kandidat), 'mundur');

                return 'Menyatakan mundur — otomatis Tidak Hadir + tanda terima + kabar tim';

            case 6:
                $this->wajibOk($mintaLain(), 'minta jadwal lain');
                $this->wajibOk(KonfirmasiJadwal::tolak($idMinta(), 'Panel pewawancara hanya tersedia di tanggal ini. Mohon hadir di jadwal semula.', self::ADMIN, null), 'tolak');

                return 'Minta jadwal lain → DITOLAK (jadwal semula tetap)';

            case 7:
                $this->wajibOk($mintaLain(), 'minta jadwal lain');
                $this->wajibOk(KonfirmasiJadwal::setujui($idMinta(), 0, '09:30', 'Disesuaikan dengan usulanmu. Sampai jumpa!', self::ADMIN, null), 'setujui');

                return 'Minta jadwal lain → DISETUJUI (jadwal baru, akan hadir)';

            case 8:
                $this->mundurkan($id, kirimJam: -20);
                $r = KonfirmasiJadwal::kirimPengingat([$id], self::ADMIN, null);
                if ($r['gagal']) {
                    throw new \RuntimeException('pengingat: '.$r['gagal'][0]['alasan']);
                }

                return 'Pengingat manual terkirim (hitungan CRM naik)';

            case 9:
                $this->wajibOk(KonfirmasiJadwal::jawab($id, $this->versi($id), KonfirmasiJadwal::AKAN_HADIR, [
                    'catatan' => 'Ditelepon tim — kandidat memastikan hadir.',
                ], KonfirmasiJadwal::pelakuTim(self::ADMIN, null, 'TELEPON')), 'catat tim');

                return 'Dicatat tim lewat telepon: akan hadir';

            case 10:
                $this->wajibOk(KonfirmasiJadwal::tunda($id, [
                    'alasan' => 'PEWAWANCARA',
                    'perkiraan' => $this->hariKerja(6)->format('Y-m-d'),
                    'pesan' => 'Mohon maaf atas perubahannya — rincian jadwal pengganti kami kirim lewat email.',
                ], self::ADMIN, null), 'tunda');

                return 'Jadwal DITUNDA (alasan + perkiraan tanggal) — coba "Perbarui info" di drawer';

            case 11:
            case 12:
                $this->mundurkan($id, kirimJam: -20);

                return 'Menunggu, undangan kemarin — siap diingatkan dari Agenda Seleksi';

            case 13:
                $this->wajibOk($mintaLain(), 'minta jadwal lain');
                $this->wajibOk(KonfirmasiJadwal::tawarkan($idMinta(), $this->hariKerja(7)->setTime(13, 0)->format('Y-m-d H:i:s'), 'Tanggal usulanmu penuh; ini waktu terdekat yang tersedia.', self::ADMIN, null), 'tawarkan');

                return 'Minta jadwal lain → DITAWARKAN waktu lain (konfirmasi ulang)';
        }

        return '-';
    }

    // ════════════════════════════════════════════════════════════════════════
    //  FORMULIR & BERKAS LENGKAP — disalin dari lamaran sumber
    // ════════════════════════════════════════════════════════════════════════

    /** Lamaran sumber: --sumber, atau yang berkas formulirnya terbanyak di program. */
    private function sumber(int $programId, string $slug): ?object
    {
        $q = DB::table('N_WEB_CAREERS_Lamaran as l')
            ->join('N_WEB_CAREERS_Users as u', 'u.Id_Users', '=', 'l.Id_Users')
            ->join('N_WEB_CAREERS_Formulir_Pengisian as fp', 'fp.Lamaran_Id', '=', 'l.Id_Lamaran')
            ->leftJoin('N_WEB_CAREERS_Formulir_Berkas as fb', 'fb.Formulir_Pengisian_Id', '=', 'fp.Id_Formulir_Pengisian')
            ->where('fp.Status', 'TERKIRIM')
            ->where('u.Kode_Calon', 'not like', 'UJI-'.strtoupper($slug).'-%')
            ->groupBy('l.Id_Lamaran', 'l.Kode', 'u.Nama')
            ->select('l.Id_Lamaran', 'l.Kode', 'u.Nama', DB::raw('COUNT(fb.Id_Formulir_Berkas) as Berkas'));

        $kode = trim((string) $this->option('sumber'));

        return $kode !== ''
            ? $q->where('l.Kode', $kode)->first()
            : $q->where('l.Program_Id', $programId)->orderByRaw('COUNT(fb.Id_Formulir_Berkas) DESC')->first();
    }

    /**
     * Lengkapi satu lamaran dummy dari lamaran sumber.
     *
     * Hanya tahap SEBELUM tahap parkir yang disalin — tahap parkir adalah
     * tahap yang sedang diuji, dan harus bersih (belum dijadwalkan). Tahap &
     * aktivitas dipetakan lewat URUTAN-nya, jadi sumber harus beralur sama.
     */
    private function lengkapi(object $sumber, object $k, int $no, int $tahapParkir): void
    {
        $now = now();
        $d = (int) $k->Id_Lamaran;
        $s = (int) $sumber->Id_Lamaran;
        $uid = (int) $k->Id_Users;
        $akun = DB::table('N_WEB_CAREERS_Users')->where('Id_Users', $uid)->first(['Nama', 'Email', 'No_Hp']);
        $nama = Str::title(Str::lower((string) $akun->Nama));
        $hp = preg_replace('/^0/', '62', (string) $akun->No_Hp);
        // NIK rekaan, unik per nomor dummy (kode wilayah Palembang).
        $nik = '167101'.str_pad((string) (int) preg_replace('/\D/', '', (string) $k->Kode_Calon), 10, '0', STR_PAD_LEFT);
        $oleh = 'SEEDER(uji)';

        $tahapS = DB::table('N_WEB_CAREERS_Lamaran_Tahap')->where('Lamaran_Id', $s)->get()->keyBy('Id_Lamaran_Tahap');
        $tahapD = DB::table('N_WEB_CAREERS_Lamaran_Tahap')->where('Lamaran_Id', $d)->get();
        $tahapDPerUrutan = $tahapD->keyBy('Urutan');
        $petaTahap = function (?int $idS, ?int $alurTahapId = null) use ($tahapS, $tahapD, $tahapDPerUrutan) {
            $u = $idS ? ($tahapS->get($idS)->Urutan ?? null) : null;
            $t = $u !== null ? $tahapDPerUrutan->get($u) : $tahapD->firstWhere('Master_Alur_Tahap_Id', $alurTahapId);

            return $t ? (object) ['id' => (int) $t->Id_Lamaran_Tahap, 'urutan' => (int) $t->Urutan] : null;
        };

        $urutanTahapS = $tahapS->map(fn ($t) => (int) $t->Urutan);
        $urutanTahapD = $tahapD->keyBy('Id_Lamaran_Tahap')->map(fn ($t) => (int) $t->Urutan);
        $tesS = DB::table('N_WEB_CAREERS_Lamaran_Tahap_Tes')->whereIn('Lamaran_Tahap_Id', $tahapS->keys()->all())->get();
        $tesD = DB::table('N_WEB_CAREERS_Lamaran_Tahap_Tes')->whereIn('Lamaran_Tahap_Id', $tahapD->pluck('Id_Lamaran_Tahap')->all())->get()
            ->keyBy(fn ($t) => $urutanTahapD[$t->Lamaran_Tahap_Id].'|'.$t->Urutan);
        $petaTes = function (?int $idS) use ($tesS, $tesD, $urutanTahapS) {
            $t = $idS ? $tesS->firstWhere('Id_Lamaran_Tahap_Tes', $idS) : null;

            return $t ? ($tesD->get($urutanTahapS[$t->Lamaran_Tahap_Id].'|'.$t->Urutan)->Id_Lamaran_Tahap_Tes ?? null) : null;
        };

        // 1) Formulir (pendaftaran + tahap) berikut berkas & proyeksi jawabannya.
        foreach (DB::table('N_WEB_CAREERS_Formulir_Pengisian')->where('Lamaran_Id', $s)->where('Status', 'TERKIRIM')->orderBy('Id_Formulir_Pengisian')->get() as $fp) {
            $t = $petaTahap($fp->Lamaran_Tahap_Id ? (int) $fp->Lamaran_Tahap_Id : null, $fp->Master_Alur_Tahap_Id ? (int) $fp->Master_Alur_Tahap_Id : null);
            if (! $t || $t->urutan >= $tahapParkir) {
                continue;
            }

            $jawab = json_decode((string) $fp->Jawaban_Json, true) ?: [];
            foreach (['nama_lengkap', 'v_nama'] as $kunci) {
                if (array_key_exists($kunci, $jawab)) {
                    $jawab[$kunci] = $nama;
                }
            }
            foreach (['email', 'v_email'] as $kunci) {
                if (array_key_exists($kunci, $jawab)) {
                    $jawab[$kunci] = $akun->Email;
                }
            }
            foreach (['no_hp', 'v_wa'] as $kunci) {
                if (array_key_exists($kunci, $jawab)) {
                    $jawab[$kunci] = $hp;
                }
            }
            if (array_key_exists('nik', $jawab)) {
                $jawab['nik'] = $nik;
            }

            $baru = (array) $fp;
            unset($baru['Id_Formulir_Pengisian']);
            $idBaru = (int) DB::table('N_WEB_CAREERS_Formulir_Pengisian')->insertGetId([
                'Kode' => 'FLL-'.strtoupper(Str::random(8)),
                'Id_Users' => $uid,
                'Lamaran_Id' => $d,
                'Lamaran_Tahap_Id' => $t->id,
                'Penjadwalan_Tahap_Id' => null,
                'Jawaban_Json' => json_encode($jawab, JSON_UNESCAPED_UNICODE),
                'Waktu_Kirim' => $now,
                'Ip_Pengirim' => null,
                'Created_At' => $now, 'Created_By' => $oleh, 'Created_By_Id' => $uid,
                'Updated_At' => $now, 'Updated_By' => $oleh, 'Updated_By_Id' => $uid,
            ] + $baru, 'Id_Formulir_Pengisian');

            // Tahapnya menunjuk formulir ini, lengkap dengan rekomendasi mesinnya.
            $tahapSumber = $fp->Lamaran_Tahap_Id ? $tahapS->get($fp->Lamaran_Tahap_Id) : null;
            DB::table('N_WEB_CAREERS_Lamaran_Tahap')->where('Id_Lamaran_Tahap', $t->id)->update([
                'Formulir_Pengisian_Id' => $idBaru,
                'Rekomendasi' => $tahapSumber->Rekomendasi ?? 'LOLOS',
                'Rekomendasi_Alasan' => $tahapSumber->Rekomendasi_Alasan ?? 'Seluruh syarat terpenuhi.',
                'Rekomendasi_At' => $now,
            ]);

            foreach (DB::table('N_WEB_CAREERS_Formulir_Berkas')->where('Formulir_Pengisian_Id', $fp->Id_Formulir_Pengisian)->orderBy('Id_Formulir_Berkas')->get() as $b) {
                $bb = (array) $b;
                unset($bb['Id_Formulir_Berkas']);
                DB::table('N_WEB_CAREERS_Formulir_Berkas')->insert([
                    'Formulir_Pengisian_Id' => $idBaru,
                    'Id_Users' => $uid,
                    'Status_Verifikasi' => 'BELUM',
                    'Diverifikasi_By' => null, 'Diverifikasi_By_Id' => null, 'Diverifikasi_At' => null,
                    'Waktu_Unggah' => $now,
                    'Created_At' => $now, 'Created_By' => $oleh, 'Created_By_Id' => $uid,
                    'Updated_At' => $now, 'Updated_By' => $oleh, 'Updated_By_Id' => $uid,
                ] + $bb);
            }

            foreach (DB::table('N_WEB_CAREERS_Formulir_Jawaban_Index')->where('Formulir_Pengisian_Id', $fp->Id_Formulir_Pengisian)->get() as $x) {
                $xx = (array) $x;
                unset($xx['Id_Formulir_Jawaban_Index']);
                DB::table('N_WEB_CAREERS_Formulir_Jawaban_Index')->insert([
                    'Formulir_Pengisian_Id' => $idBaru,
                    'Lamaran_Id' => $d,
                    'Id_Users' => $uid,
                    'Created_At' => $now,
                    'Created_By_Id' => $uid,
                ] + $xx);
            }
        }

        // 2) Nilai & catatan aktivitas tahap sebelumnya (status/hasil tetap
        //    milik seeder — gerbang keputusannya sudah benar).
        foreach ($tesS as $t) {
            if ($urutanTahapS[$t->Lamaran_Tahap_Id] >= $tahapParkir) {
                continue;
            }
            $idD = $petaTes((int) $t->Id_Lamaran_Tahap_Tes);
            $isi = array_filter([
                'Nilai' => $t->Nilai,
                'Nilai_Teks' => $t->Nilai_Teks,
                'Catatan' => $t->Catatan,
                'Catatan_Html' => $t->Catatan_Html ?? null,
            ], fn ($v) => $v !== null && $v !== '');
            if ($idD && $isi) {
                DB::table('N_WEB_CAREERS_Lamaran_Tahap_Tes')->where('Id_Lamaran_Tahap_Tes', $idD)->update($isi + ['Updated_At' => $now, 'Updated_By' => $oleh]);
            }
        }

        // 3) Berkas hasil tahap (diunggah tim) & berkas unggahan tes (kandidat).
        foreach (DB::table('N_WEB_CAREERS_Lamaran_Tahap_Berkas')->where('Lamaran_Id', $s)->whereNull('Ulang_Id')->get() as $b) {
            $t = $petaTahap((int) $b->Lamaran_Tahap_Id);
            if (! $t || $t->urutan >= $tahapParkir) {
                continue;
            }
            $bb = (array) $b;
            unset($bb['Id_Lamaran_Tahap_Berkas']);
            DB::table('N_WEB_CAREERS_Lamaran_Tahap_Berkas')->insert([
                'Lamaran_Id' => $d,
                'Lamaran_Tahap_Id' => $t->id,
                'Lamaran_Tahap_Tes_Id' => $petaTes($b->Lamaran_Tahap_Tes_Id ? (int) $b->Lamaran_Tahap_Tes_Id : null),
                'Created_At' => $now, 'Created_By' => $oleh, 'Created_By_Id' => null,
                'Updated_At' => $now, 'Updated_By' => $oleh, 'Updated_By_Id' => null,
                'Putaran' => 1,
            ] + $bb);
        }
        foreach (DB::table('N_WEB_CAREERS_Lamaran_Tes_Berkas')->where('Lamaran_Id', $s)->whereNull('Ulang_Id')->get() as $b) {
            $idD = $petaTes((int) $b->Lamaran_Tahap_Tes_Id);
            $asal = $tesS->firstWhere('Id_Lamaran_Tahap_Tes', (int) $b->Lamaran_Tahap_Tes_Id);
            if (! $idD || ! $asal || $urutanTahapS[$asal->Lamaran_Tahap_Id] >= $tahapParkir) {
                continue;
            }
            $bb = (array) $b;
            unset($bb['Id_Lamaran_Tes_Berkas']);
            DB::table('N_WEB_CAREERS_Lamaran_Tes_Berkas')->insert([
                'Lamaran_Id' => $d,
                'Lamaran_Tahap_Tes_Id' => $idD,
                'Id_Users' => $uid,
                'Created_At' => $now, 'Created_By' => $nama, 'Created_By_Id' => $uid,
                'Terkirim_At' => $now,
                'Putaran' => 1,
            ] + $bb);
        }

        // NIK akun mengikuti formulir — satu identitas di semua layar.
        DB::table('N_WEB_CAREERS_Users')->where('Id_Users', $uid)->update(['NIK' => $nik, 'Updated_At' => $now]);
    }

    // ════════════════════════════════════════════════════════════════════════
    //  PEMBANTU
    // ════════════════════════════════════════════════════════════════════════

    /**
     * Jadwalkan lewat controller Atur Jadwal — validasi, versi, CRM, undangan,
     * dan jejaknya persis seperti tombol "Simpan & Undang Kandidat".
     */
    private function jadwalkan(int $subTesId, Carbon $mulai): void
    {
        $hash = Hashids::encode($subTesId);
        // Alamat LENGKAP dari APP_URL: tautan bertanda tangan di surel dirakit
        // dari host request ini. Request tanpa host jatuh ke http://localhost —
        // tautannya salah alamat DAN tanda tangannya tak cocok di host asli.
        $req = Request::create(rtrim((string) config('app.url'), '/')."/api/v1/karir/lamaran/sub-tes/{$hash}/jadwal", 'PATCH', [
            'mode' => 'DARING',
            'mulai' => $mulai->format('Y-m-d H:i:s'),
            'selesai' => $mulai->copy()->addMinutes(45)->format('Y-m-d H:i:s'),
            'link' => self::LINK,
            'catatanHtml' => '<p>Siapkan KTP dan CV terbaru. Masuk ruang pertemuan 10 menit sebelum mulai.</p>',
        ]);
        $req->setLaravelSession(app('session.store'));
        $asli = app('request');
        app()->instance('request', $req);

        try {
            $res = app(LamaranController::class)->subTesJadwal($req, $hash);
        } finally {
            // Request konsol dipulihkan — pembuat URL ikut kembali ke APP_URL.
            app()->instance('request', $asli);
        }
        $isi = json_decode($res->getContent(), true) ?: [];
        if (! ($isi['success'] ?? false)) {
            throw new \RuntimeException('jadwal: '.($isi['message'] ?? 'ditolak'));
        }
    }

    /**
     * Mundurkan jam kirim di snapshot CRM — satu-satunya bagian yang tidak bisa
     * dibuat lewat layar hari ini (undangan "kemarin").
     */
    private function mundurkan(int $subTesId, ?int $kirimJam = null): void
    {
        $ubah = [];
        if ($kirimJam !== null) {
            $ubah['Undangan_At'] = now()->addHours($kirimJam)->format('Y-m-d H:i:s');
            $ubah['Terakhir_Kirim_At'] = now()->addHours($kirimJam)->format('Y-m-d H:i:s');
        }
        DB::table(KonfirmasiJadwal::T_CRM)->where('Lamaran_Tahap_Tes_Id', $subTesId)->where('Versi', $this->versi($subTesId))->update($ubah);
    }

    /** Buang undangan di antrean untuk alamat ini (dianggap sudah terkirim kemarin). */
    private function buangUndanganAntre(string $email): void
    {
        $ids = DB::table('N_WEB_CAREERS_Jobs')->get(['id', 'payload'])
            ->filter(fn ($j) => str_contains($j->payload, 'WcJadwalEmailJob') && str_contains($j->payload, $email))
            ->pluck('id')->all();
        if ($ids) {
            DB::table('N_WEB_CAREERS_Jobs')->whereIn('id', $ids)->delete();
        }
    }

    private function versi(int $subTesId): int
    {
        return (int) DB::table('N_WEB_CAREERS_Lamaran_Tahap_Tes')->where('Id_Lamaran_Tahap_Tes', $subTesId)->value('Jadwal_Versi');
    }

    private function pelakuKandidat(object $k): array
    {
        return [
            'jenis' => KonfirmasiJadwal::KANDIDAT,
            'kanal' => KonfirmasiJadwal::K_TAUTAN,
            'nama' => $k->Nama,
            'id' => (int) $k->Id_Users,
            'ip' => '127.0.0.1',
            'perangkat' => 'Android · Chrome',
        ];
    }

    private function wajibOk(array $r, string $langkah): void
    {
        if (! ($r['ok'] ?? false)) {
            throw new \RuntimeException("{$langkah}: ".($r['pesan'] ?? 'ditolak'));
        }
    }

    /** Hari kerja ke-N dari hari ini (Minggu dilewati). */
    private function hariKerja(int $n): Carbon
    {
        $d = now()->startOfDay();
        while ($n > 0) {
            $d->addDay();
            if (! $d->isSunday()) {
                $n--;
            }
        }

        return $d;
    }
}
