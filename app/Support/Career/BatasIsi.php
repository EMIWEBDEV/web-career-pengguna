<?php

namespace App\Support\Career;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * WEB CAREERS — JADWAL PENGISIAN FORMULIR TAHAP (waktu dibuka + batas akhir).
 * Satu pintu untuk seluruh aturannya.
 *
 * ══ TIGA LAPIS, DARI YANG PALING UMUM KE PALING KHUSUS ═══════════════════
 *
 *   ATURAN   Master_Alur_Tahap.Batas_Mode, dibekukan ke Lamaran_Tahap:
 *              TANPA     formulir terbuka kapan saja;
 *              OTOMATIS  terbuka sejak tahapnya dibuka, Batas_Hari hari (23.59);
 *              MANUAL    TERKUNCI sampai admin mengisi jadwalnya — waktu
 *                        dibuka dan batas akhirnya — per program dari worklist.
 *            Berlaku untuk tahap bertipe Formulir MANA PUN, termasuk tahap 1
 *            (keputusan user: "dikontrol dari Master Alur, jangan hardcode").
 *            Pelamar biasa mengirim formulir tahap 1 saat melamar — masa
 *            pendaftarannya tetap milik Pembukaan Program — jadi jadwal tahap 1
 *            mengenai yang mengisinya SESUDAH masuk (mis. tarik Talent Pool).
 *   PROGRAM  N_WEB_CAREERS_Program_Batas_Tahap — satu jadwal (Buka_At, Batas_At)
 *            untuk seluruh kandidat sebuah program di satu kolom tahap. Kuncinya
 *            KODE tahap, sama dengan kolom papan worklist (AlurKolom), bukan Id
 *            master: satu program bisa berisi kandidat dari beberapa versi alur.
 *   PRIBADI  jadwal / perpanjangan satu kandidat.
 *
 * ══ SEKALI DIPASANG, HANYA DIPERPANJANG (KECUALI SUPERADMIN) ══════════════
 *
 * Keputusan user: jadwal yang sudah dipasang tidak bisa disunting — hanya
 * diperpanjang (+ N hari, + N jam, atau sampai tanggal & jam tertentu),
 * disesuaikan berkala. SUPERADMIN tetap bisa MENGEDIT (ubahProgram, cara
 * UBAH) untuk kasus salah klik — dengan alasan wajib, dan tercatat di riwayat
 * kandidat maupun riwayat KOLOM (T_RIWAYAT_KOLOM: siapa, kapan, lama → baru).
 * Tiga akibat aturan "hanya maju", dijaga di setiap pintu tulis lainnya:
 *
 *   · batas siapa pun tidak pernah MUNDUR;
 *   · jadwal kolom tidak pernah MENUTUP formulir yang sudah terbuka — waktu
 *     dibuka kandidat yang sudah berjadwal tidak disentuh;
 *   · batas tiap kandidat = yang paling lambat antara miliknya dan kolomnya.
 *     Kandidat yang pernah diperpanjang pribadi tidak tertinggal ketika
 *     kolomnya kemudian diperpanjang lebih jauh.
 *
 * Jadwal program berlaku apa pun mode snapshot-nya: menyunting alur yang sedang
 * dipakai melahirkan versi baru, jadi kandidat yang sudah berjalan tidak pernah
 * ikut aturan barunya — jadwal dari worklist-lah jalan resmi untuk mereka.
 *
 * Yang berlaku disimpan di Lamaran_Tahap (Buka_At, Batas_At, Batas_Sumber),
 * dihitung saat tahap DIBUKA dan setiap kali admin mengubahnya; setiap
 * perubahan tercatat di N_WEB_CAREERS_Lamaran_Tahap_Batas_Riwayat — polanya
 * sama dengan perpanjangan SLA MPP (PerpanjangSla).
 *
 * ══ KAPAN JADWAL BERLAKU ═════════════════════════════════════════════════
 *
 * Hanya bila Master Alur memberi tahapnya jadwal (MANUAL/OTOMATIS), atau
 * tahap itu memang sudah berjadwal (jadwal kolom / pribadi). Tahap TANPA yang
 * belum berjadwal tidak mengenal jadwal sama sekali: status() null, tidak ada
 * panel, tombol, atau pengaturan kolom di worklist — lihat berjadwal() dan
 * kolomBolehDijadwal().
 *
 * ══ JADWAL DIMATIKAN DI MASTER ALUR ══════════════════════════════════════
 *
 * Snapshot tidak ikut berubah (itu gunanya snapshot), jadi dua hal dijaga:
 *   · jadwal kolom hanya DITEMPELKAN ke kandidat yang tahapnya masih berjadwal
 *     — menurut snapshot-nya sendiri ATAU menurut alur program sekarang
 *     (modeSekarang). Kandidat baru di tahap yang sudah "Tanpa jadwal" tidak
 *     ikut terkena jadwal kolom lama;
 *   · kandidat yang masih terikat jadwal lama bisa DILEPAS dari worklist
 *     (lepasProgram / aturKandidat LEPAS) — hanya bila Master Alur tahap itu
 *     sudah "Tanpa jadwal". Melepas tidak melanggar aturan "hanya diperpanjang":
 *     waktu tanpa batas tidak merugikan siapa pun.
 *
 * ══ KAPAN FORMULIR TERKUNCI ══════════════════════════════════════════════
 *
 *   BELUM_DIATUR  mode MANUAL dan jadwalnya belum diisi (keputusan user:
 *                 "kalau belum di-setup, formulirnya terkunci");
 *   BELUM_BUKA    waktu dibukanya belum tiba;
 *   LEWAT         batas akhirnya lewat — SELALU terkunci. Keputusan user: tidak
 *                 ada pilihan "tetap terbuka, ditandai terlambat". Kolom
 *                 Batas_Aksi tetap ada (tidak di-ALTER), selalu ditulis KUNCI,
 *                 dan tidak dibaca lagi.
 *
 * Tidak ada gugur otomatis: setelah terkunci, admin yang memutuskan (pelajaran
 * kasus Psikotes 1 — mesin sempat menggugurkan puluhan kandidat sendiri).
 *
 * Seluruh kolom & tabelnya dibuat docs/28-09-2026/04-batas-isi-formulir.sql.
 * Sebelum skrip dijalankan, siap() = false dan setiap pintu di sini diam.
 */
final class BatasIsi
{
    public const TANPA = 'TANPA';

    public const OTOMATIS = 'OTOMATIS';

    public const MANUAL = 'MANUAL';

    public const KUNCI = 'KUNCI';

    public const SUMBER_ATURAN = 'ATURAN';

    public const SUMBER_PROGRAM = 'PROGRAM';

    public const SUMBER_PRIBADI = 'PRIBADI';

    /** Sumber di riwayat saat batas waktu dilepas (Batas_Baru NULL). */
    public const SUMBER_LEPAS = 'LEPAS';

    /** Sumber di riwayat saat SUPERADMIN mengedit jadwal (boleh mundur). */
    public const SUMBER_UBAH = 'UBAH';

    public const BELUM_DIATUR = 'BELUM_DIATUR';

    public const BELUM_BUKA = 'BELUM_BUKA';

    public const LEWAT = 'LEWAT';

    /**
     * Kandidat yang masuk tahap sesudah (atau menjelang) batas program tidak
     * langsung terkunci: ia mendapat sekurangnya sekian hari sejak formulirnya
     * terbuka untuknya.
     */
    public const JENDELA_MINIMUM_HARI = 2;

    public const T_PROGRAM = 'N_WEB_CAREERS_Program_Batas_Tahap';

    public const T_RIWAYAT = 'N_WEB_CAREERS_Lamaran_Tahap_Batas_Riwayat';

    /** Riwayat di tingkat KOLOM (program × tahap) — docs/29-09-2026/02. */
    public const T_RIWAYAT_KOLOM = 'N_WEB_CAREERS_Program_Batas_Riwayat';

    private const T_TAHAP = 'N_WEB_CAREERS_Lamaran_Tahap';

    private const KOLOM_STATUS = ['Status', 'Formulir_Kode', 'Formulir_Pengisian_Id', 'Batas_Mode', 'Batas_Aksi', 'Batas_Sumber', 'Buka_At', 'Batas_At'];

    private const HARI = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];

    private const BULAN = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

    /** Kolom & tabelnya sudah dibuat? Sebelum itu seluruh fitur diam. */
    public static function siap(): bool
    {
        try {
            return Skema::adaKolom(self::T_TAHAP, 'Batas_At')
                && Skema::adaKolom(self::T_TAHAP, 'Buka_At')
                && Skema::adaKolom('N_WEB_CAREERS_Master_Alur_Tahap', 'Batas_Mode')
                && Skema::adaTabel(self::T_PROGRAM)
                && Skema::adaKolom(self::T_PROGRAM, 'Buka_At')
                && Skema::adaTabel(self::T_RIWAYAT);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** Aturan yang DIBEKUKAN dari tahap master ke tahap kandidat. */
    public static function snapshot(object $master): array
    {
        if (! self::siap()) {
            return [];
        }

        return [
            'Batas_Mode' => $master->Batas_Mode ?? null,
            'Batas_Hari' => $master->Batas_Hari ?? null,
            'Batas_Aksi' => self::berjadwal($master->Batas_Mode ?? null) ? self::KUNCI : null,
        ];
    }

    /** Kolom yang dinolkan saat sebuah tahap diulang — aturannya tetap. */
    public static function kolomBersih(): array
    {
        return self::siap()
            ? ['Buka_At' => null, 'Batas_At' => null, 'Batas_Sumber' => null, 'Batas_Pengingat_At' => null]
            : [];
    }

    // ══ HITUNGAN (murni — diuji tanpa basis data) ═════════════════════════

    /** N hari sesudah $dibuka, pukul 23.59.59. */
    public static function jatuhTempo(Carbon $dibuka, int $hari): Carbon
    {
        return $dibuka->copy()->addDays(max(1, $hari))->setTime(23, 59, 59);
    }

    /** Batas yang terlalu dekat dari saat formulir terbuka → jendela minimum. */
    public static function denganJendelaMinimum(Carbon $batas, Carbon $terbuka): Carbon
    {
        $minimum = self::jatuhTempo($terbuka, self::JENDELA_MINIMUM_HARI);

        return $batas->lt($minimum) ? $minimum : $batas->copy();
    }

    /**
     * Keadaan jadwal satu tahap kandidat, untuk layar — null bila bukan tahap
     * formulir yang sedang berjalan (atau kolomnya belum dibuat).
     *
     * `alasan` null = formulir terbuka. `belumDiatur` juga pengingat bagi admin:
     * di kolom itu kandidat sedang menunggu tanpa bisa mengisi apa pun.
     */
    public static function status(?object $t, ?Carbon $sekarang = null): ?array
    {
        if (! $t || empty($t->Formulir_Kode) || ($t->Status ?? '') !== 'BERJALAN' || ! property_exists($t, 'Batas_At')) {
            return null;
        }

        $sekarang ??= now();
        $terkirim = ! empty($t->Formulir_Pengisian_Id);
        $mode = $t->Batas_Mode ?? null;
        $buka = ! empty($t->Buka_At ?? null) ? Carbon::parse($t->Buka_At) : null;
        $batas = ! empty($t->Batas_At) ? Carbon::parse($t->Batas_At) : null;

        // Tahap tanpa jadwal di Master Alur dan belum pernah dijadwalkan: konsep
        // jadwal tidak berlaku di sini — layar tidak menampilkan apa pun.
        if (! self::berjadwal($mode) && ! $batas && ! $buka) {
            return null;
        }

        $alasan = null;
        if (! $terkirim) {
            $alasan = match (true) {
                $mode === self::MANUAL && ! $batas => self::BELUM_DIATUR,
                $buka !== null && $sekarang->lt($buka) => self::BELUM_BUKA,
                $batas !== null && $sekarang->gt($batas) => self::LEWAT,
                default => null,
            };
        }
        $lewat = $alasan === self::LEWAT;

        return [
            'buka' => $buka?->format('Y-m-d H:i:s'),
            'bukaTeks' => $buka ? self::teks($buka) : null,
            'batas' => $batas?->format('Y-m-d H:i:s'),
            'teks' => $batas ? self::teks($batas) : null,
            'sumber' => $t->Batas_Sumber ?? null,
            'mode' => $mode,
            'aksi' => self::KUNCI,
            'terkirim' => $terkirim,
            'alasan' => $alasan,
            'belumDiatur' => $alasan === self::BELUM_DIATUR,
            'belumBuka' => $alasan === self::BELUM_BUKA,
            'lewat' => $lewat,
            // Belum diatur, belum dibuka, atau lewat batas: ketiganya terkunci.
            'terkunci' => $alasan !== null,
            // Negatif = sudah lewat. Null bila tak berbatas / sudah terkirim.
            'sisaDetik' => ($terkirim || ! $batas) ? null : (int) $sekarang->diffInSeconds($batas, false),
        ];
    }

    /** Master Alur memberi tahap ini jadwal (bukan TANPA)? */
    public static function berjadwal(?string $mode): bool
    {
        return in_array($mode, [self::MANUAL, self::OTOMATIS], true);
    }

    /**
     * Kolom papan worklist boleh diberi jadwal? DITENTUKAN MASTER ALUR, bukan
     * posisi tahap: tahap bertipe Formulir yang di Master Alur berjadwal. Kolom
     * yang sudah punya jadwal, atau berisi kandidat berjadwal (snapshot alur
     * versi lama), tetap bisa diurus — supaya tidak ada yang terkunci tanpa
     * jalan keluar sesudah alurnya disunting.
     *
     * @param  array{formulir?:bool, cadangan?:bool, batasMode?:string|null}  $kolom  bentuk AlurKolom
     */
    public static function kolomBolehDijadwal(array $kolom, bool $adaJadwalKolom, bool $adaKandidatBerjadwal): bool
    {
        return ! empty($kolom['formulir']) && empty($kolom['cadangan'])
            && (self::berjadwal($kolom['batasMode'] ?? null) || $adaJadwalKolom || $adaKandidatBerjadwal);
    }

    /**
     * Mode jadwal tahap $kode menurut alur yang SEKARANG dipasang di program —
     * yang admin lihat di Master Alur hari ini. Null bila alur itu tidak punya
     * tahap ber-kode tersebut (tahapnya milik versi lama).
     *
     * Sengaja tanpa cache statis: buka() juga berjalan di queue worker yang
     * hidup berhari-hari, dan Master Alur boleh diubah kapan saja.
     */
    public static function modeSekarang(int $programId, string $kodeTahap): ?string
    {
        return DB::table('N_WEB_CAREERS_Program as p')
            ->join('N_WEB_CAREERS_Master_Alur as a', 'a.Kode', '=', 'p.Alur_Kode')
            ->join('N_WEB_CAREERS_Master_Alur_Tahap as m', 'm.Master_Alur_Id', '=', 'a.Id_Master_Alur')
            ->where('p.Id_Program', $programId)
            ->where('m.Kode', $kodeTahap)
            ->value('m.Batas_Mode');
    }

    /** Ada kandidat di kolom ini yang sedang menjalani jadwal (atau menunggunya)? */
    public static function adaKandidatBerjadwal(int $programId, string $kodeTahap): bool
    {
        return self::tahapProgram($programId, $kodeTahap)
            ->whereNull('t.Formulir_Pengisian_Id')
            ->where(fn ($w) => $w->whereIn('t.Batas_Mode', [self::MANUAL, self::OTOMATIS])
                ->orWhereNotNull('t.Batas_At')
                ->orWhereNotNull('t.Buka_At'))
            ->exists();
    }

    /** "Rabu, 15 Okt 2026 pukul 23.59 WIB". */
    public static function teks(Carbon|string|null $at): string
    {
        if (! $at) {
            return '—';
        }
        $c = $at instanceof Carbon ? $at : Carbon::parse($at);

        return self::HARI[$c->dayOfWeek].', '.$c->format('d').' '.self::BULAN[$c->month - 1].' '.$c->format('Y')
            .' pukul '.$c->format('H.i').' WIB';
    }

    // ══ PINTU TULIS ═══════════════════════════════════════════════════════

    /**
     * Tahap baru saja DIBUKA untuk kandidat — tetapkan jadwalnya.
     *
     * Aman dipanggil untuk tahap apa pun: yang tak berformulir, sudah terisi,
     * tidak berjalan, atau tidak berjadwal dilewati tanpa menulis apa pun.
     * Urutannya: jadwal program (dengan jendela minimum) → aturan OTOMATIS.
     * Mode MANUAL tanpa jadwal program dibiarkan kosong = terkunci.
     */
    public static function buka(int $lamaranTahapId, ?Carbon $sekarang = null, ?string $oleh = null, ?int $olehId = null): void
    {
        if (! self::siap()) {
            return;
        }
        $sekarang ??= now();

        $t = DB::table(self::T_TAHAP.' as t')
            ->join('N_WEB_CAREERS_Lamaran as l', 'l.Id_Lamaran', '=', 't.Lamaran_Id')
            ->where('t.Id_Lamaran_Tahap', $lamaranTahapId)
            ->first(['t.Id_Lamaran_Tahap', 't.Lamaran_Id', 't.Kode', 't.Status', 't.Formulir_Kode', 't.Formulir_Pengisian_Id',
                't.Batas_Mode', 't.Batas_Hari', 't.Batas_Aksi', 't.Buka_At', 't.Batas_At', 'l.Program_Id']);

        if (! $t || $t->Status !== 'BERJALAN' || $t->Formulir_Pengisian_Id || ! $t->Formulir_Kode) {
            return;
        }

        $program = ($t->Program_Id && $t->Kode)
            ? DB::table(self::T_PROGRAM)->where('Program_Id', $t->Program_Id)->where('Tahap_Kode', $t->Kode)->first(['Buka_At', 'Batas_At'])
            : null;

        // Jadwal kolom hanya ditempelkan bila tahap ini masih berjadwal — menurut
        // snapshot kandidat, atau menurut alur program sekarang. Tahap yang di
        // Master Alur sudah "Tanpa jadwal" tidak ikut terkena jadwal kolom lama.
        $berlaku = $program && (self::berjadwal($t->Batas_Mode ?? null)
            || self::berjadwal(self::modeSekarang((int) $t->Program_Id, (string) $t->Kode)));

        if ($berlaku) {
            $bukaProgram = $program->Buka_At ? Carbon::parse($program->Buka_At) : null;
            $tgl = Carbon::parse($program->Batas_At);
            // Jendela minimum dihitung dari saat formulir BENAR-BENAR terbuka
            // untuknya: sekarang, atau waktu dibuka program bila belum tiba.
            $terbuka = $bukaProgram && $bukaProgram->gt($sekarang) ? $bukaProgram : $sekarang;
            $baru = self::denganJendelaMinimum($tgl, $terbuka);
            $alasan = $baru->eq($tgl)
                ? 'Tahap dibuka — mengikuti jadwal program'
                : 'Tahap dibuka menjelang/sesudah batas program — diberi jendela minimum '.self::JENDELA_MINIMUM_HARI.' hari';
            self::tulisSatu($t, $bukaProgram, $baru, self::SUMBER_PROGRAM, $alasan, $oleh, $olehId, $sekarang);

            return;
        }

        if (($t->Batas_Mode ?? null) === self::OTOMATIS && (int) $t->Batas_Hari > 0) {
            self::tulisSatu(
                $t, null,
                self::jatuhTempo($sekarang, (int) $t->Batas_Hari),
                self::SUMBER_ATURAN,
                "Tahap dibuka — otomatis {$t->Batas_Hari} hari",
                $oleh, $olehId, $sekarang,
            );
        }
    }

    /**
     * PASANG jadwal satu kolom (program × kode tahap) — HANYA bila kolom itu
     * belum berjadwal; sesudahnya hanya perpanjangProgram().
     *
     * Kandidat di tahap itu yang belum mengirim:
     *   · belum berjadwal        → mengikuti jadwal ini (dibuka + batas);
     *   · batasnya lebih awal    → batasnya maju ke jadwal ini, waktu dibukanya
     *                              tetap (formulir yang sudah terbuka tidak ditutup);
     *   · batasnya lebih lambat  → tidak diubah.
     * Yang masuk belakangan ikut lewat buka(). Berbasis himpunan: beberapa
     * perintah SQL berapa pun jumlah kandidatnya.
     *
     * @return array{diubah:int, lebihLambat:int, terkirim:int, langsungLewat:int}|null null = kolomnya sudah berjadwal
     */
    public static function pasangProgram(int $programId, string $kodeTahap, ?Carbon $buka, Carbon $batas, string $oleh, ?int $olehId): ?array
    {
        $sekarang = now();

        return DB::transaction(function () use ($programId, $kodeTahap, $buka, $batas, $oleh, $olehId, $sekarang) {
            $ada = DB::table(self::T_PROGRAM)->where('Program_Id', $programId)->where('Tahap_Kode', $kodeTahap)->lockForUpdate()->exists();
            if ($ada) {
                return null;
            }

            DB::table(self::T_PROGRAM)->insert([
                'Program_Id' => $programId, 'Tahap_Kode' => $kodeTahap, 'Buka_At' => $buka, 'Batas_At' => $batas,
                'Created_At' => $sekarang, 'Created_By' => $oleh, 'Created_By_Id' => $olehId,
                'Updated_At' => $sekarang, 'Updated_By' => $oleh, 'Updated_By_Id' => $olehId,
            ]);

            $semua = self::tahapProgram($programId, $kodeTahap);
            $belum = (clone $semua)->whereNull('t.Formulir_Pengisian_Id');
            $teks = 'Jadwal program: '.($buka ? 'dibuka '.self::teks($buka).', ' : '').'batas '.self::teks($batas);

            // Lebih dulu yang sudah berjadwal tapi batasnya lebih awal — sesudah
            // langkah berikutnya, yang kosong pun bernilai $batas dan tak ikut.
            $diubah = self::majuKe((clone $belum)->where('t.Batas_At', '<', $batas), $batas, $teks, $oleh, $olehId, $sekarang);

            $kosong = self::hanyaBerjadwal((clone $belum)->whereNull('t.Batas_At'), $programId, $kodeTahap);
            self::catatBanyak($kosong, [$buka, '?'], [$batas], self::SUMBER_PROGRAM, $teks, $oleh, $olehId, $sekarang);
            $diubah += (clone $kosong)->update(self::isiTulis($buka, $batas, self::SUMBER_PROGRAM, $oleh, $olehId, $sekarang));

            self::catatKolom($programId, $kodeTahap, 'PASANG', [null, $buka], [null, $batas], $diubah, null, $oleh, $olehId, $sekarang);

            return [
                'diubah' => $diubah,
                'lebihLambat' => (clone $belum)->where('t.Batas_At', '>', $batas)->count(),
                'terkirim' => (clone $semua)->whereNotNull('t.Formulir_Pengisian_Id')->count(),
                'langsungLewat' => $batas->lt($sekarang) ? $diubah : 0,
            ];
        });
    }

    /**
     * PERPANJANG batas akhir satu kolom — lihat batasBaru() untuk caranya.
     * Setiap kandidat kolom itu yang belum mengirim dan batasnya lebih awal
     * dari batas baru ikut maju — termasuk yang pernah diperpanjang pribadi,
     * supaya tidak tertinggal. Waktu dibuka siapa pun tidak disentuh.
     *
     * @return array{lama:Carbon, baru:Carbon, diubah:int}
     *
     * @throws \DomainException kolom belum berjadwal / batas baru tidak lebih lambat
     */
    public static function perpanjangProgram(int $programId, string $kodeTahap, string $cara, ?int $nilai, ?Carbon $sampai, string $oleh, ?int $olehId): array
    {
        $sekarang = now();

        return DB::transaction(function () use ($programId, $kodeTahap, $cara, $nilai, $sampai, $oleh, $olehId, $sekarang) {
            $baris = DB::table(self::T_PROGRAM)->where('Program_Id', $programId)->where('Tahap_Kode', $kodeTahap)->lockForUpdate()->first();
            if (! $baris) {
                throw new \DomainException('Kolom ini belum berjadwal — atur jadwalnya dulu.');
            }

            $lama = Carbon::parse($baris->Batas_At);
            $baru = self::batasBaru($lama, $cara, $nilai, $sampai, $sekarang);
            if (! $baru || ! $baru->gt($lama)) {
                throw new \DomainException('Batas baru harus lebih lambat dari batas sekarang ('.self::teks($lama).').');
            }
            if (! $baru->gt($sekarang)) {
                throw new \DomainException('Batas baru sudah lewat — pilih waktu yang akan datang.');
            }

            DB::table(self::T_PROGRAM)->where('Id_Program_Batas_Tahap', $baris->Id_Program_Batas_Tahap)
                ->update(['Batas_At' => $baru, 'Updated_At' => $sekarang, 'Updated_By' => $oleh, 'Updated_By_Id' => $olehId]);

            $belum = self::tahapProgram($programId, $kodeTahap)->whereNull('t.Formulir_Pengisian_Id');
            $teks = 'Jadwal program diperpanjang sampai '.self::teks($baru);
            $diubah = self::majuKe((clone $belum)->where('t.Batas_At', '<', $baru), $baru, $teks, $oleh, $olehId, $sekarang);

            // Penyembuh: baris kolom ini yang entah bagaimana belum berjadwal
            // (seharusnya tidak ada — buka() memasangnya) ikut jadwal kolom.
            $bukaKolom = $baris->Buka_At ? Carbon::parse($baris->Buka_At) : null;
            $kosong = self::hanyaBerjadwal((clone $belum)->whereNull('t.Batas_At'), $programId, $kodeTahap);
            self::catatBanyak($kosong, [$bukaKolom, '?'], [$baru], self::SUMBER_PROGRAM, $teks, $oleh, $olehId, $sekarang);
            $diubah += (clone $kosong)->update(self::isiTulis($bukaKolom, $baru, self::SUMBER_PROGRAM, $oleh, $olehId, $sekarang));

            self::catatKolom($programId, $kodeTahap, 'PERPANJANG', [$bukaKolom, $bukaKolom], [$lama, $baru], $diubah, null, $oleh, $olehId, $sekarang);

            return ['lama' => $lama, 'baru' => $baru, 'diubah' => $diubah];
        });
    }

    /**
     * EDIT jadwal satu kolom — KHUSUS SUPERADMIN (pemanggil yang memastikan),
     * untuk kasus salah klik: waktu dibuka dan batas akhir boleh maju maupun
     * MUNDUR. Kandidat yang mengikuti jadwal kolom (sumber PROGRAM, belum
     * mengirim) ikut berubah; jadwal pribadi tidak disentuh. Alasan wajib dan
     * tercatat di riwayat kandidat maupun riwayat kolom.
     *
     * @return array{diubah:int, bukaLama:?string, batasLama:string, langsungLewat:int}
     *
     * @throws \DomainException kolom belum berjadwal
     */
    public static function ubahProgram(int $programId, string $kodeTahap, ?Carbon $buka, Carbon $batas, string $alasan, string $oleh, ?int $olehId): array
    {
        $sekarang = now();

        return DB::transaction(function () use ($programId, $kodeTahap, $buka, $batas, $alasan, $oleh, $olehId, $sekarang) {
            $baris = DB::table(self::T_PROGRAM)->where('Program_Id', $programId)->where('Tahap_Kode', $kodeTahap)->lockForUpdate()->first();
            if (! $baris) {
                throw new \DomainException('Kolom ini belum berjadwal — atur jadwalnya dulu.');
            }

            DB::table(self::T_PROGRAM)->where('Id_Program_Batas_Tahap', $baris->Id_Program_Batas_Tahap)->update([
                'Buka_At' => $buka, 'Batas_At' => $batas,
                'Updated_At' => $sekarang, 'Updated_By' => $oleh, 'Updated_By_Id' => $olehId,
            ]);

            $ikut = self::tahapProgram($programId, $kodeTahap)
                ->whereNull('t.Formulir_Pengisian_Id')
                ->where('t.Batas_Sumber', self::SUMBER_PROGRAM);
            $teks = 'Jadwal kolom diedit superadmin: '.($buka ? 'dibuka '.self::teks($buka).', ' : '').'batas '.self::teks($batas).' — '.trim($alasan);
            self::catatBanyak($ikut, [$buka, '?'], [$batas], self::SUMBER_UBAH, $teks, $oleh, $olehId, $sekarang);
            $diubah = (clone $ikut)->update(self::isiTulis($buka, $batas, self::SUMBER_PROGRAM, $oleh, $olehId, $sekarang));

            self::catatKolom($programId, $kodeTahap, 'UBAH', [$baris->Buka_At, $buka], [$baris->Batas_At, $batas], $diubah, $alasan, $oleh, $olehId, $sekarang);

            return [
                'diubah' => $diubah,
                'bukaLama' => $baris->Buka_At ? (string) $baris->Buka_At : null,
                'batasLama' => (string) $baris->Batas_At,
                'langsungLewat' => $batas->lt($sekarang) ? $diubah : 0,
            ];
        });
    }

    /**
     * Riwayat jadwal satu kolom, terbaru dulu. Kosong bila tabelnya belum dibuat.
     *
     * @return \Illuminate\Support\Collection<int, object>
     */
    public static function riwayatKolom(int $programId, string $kodeTahap, int $batasBaris = 50)
    {
        if (! self::riwayatKolomSiap()) {
            return collect();
        }

        return DB::table(self::T_RIWAYAT_KOLOM)
            ->where('Program_Id', $programId)
            ->where('Tahap_Kode', $kodeTahap)
            ->orderByDesc('Id_Program_Batas_Riwayat')
            ->limit($batasBaris)
            ->get();
    }

    /**
     * LEPAS batas waktu satu kolom — dipakai sesudah Master Alur mematikan
     * jadwal tahapnya. Kandidat di kolom itu yang belum mengirim dan masih
     * terikat jadwal lama (snapshot berjadwal, atau sudah bertanggal) bisa
     * mengisi tanpa batas waktu; jadwal kolomnya dihapus. Pemanggil yang
     * memastikan tahapnya memang sudah "Tanpa jadwal" di Master Alur.
     *
     * @return array{dilepas:int, jadwalDihapus:bool}
     */
    public static function lepasProgram(int $programId, string $kodeTahap, string $oleh, ?int $olehId): array
    {
        $sekarang = now();

        return DB::transaction(function () use ($programId, $kodeTahap, $oleh, $olehId, $sekarang) {
            $sasaran = self::terikat(self::tahapProgram($programId, $kodeTahap)->whereNull('t.Formulir_Pengisian_Id'));
            $dilepas = self::tulisLepas($sasaran, 'Batas waktu dilepas — tahap ini tanpa jadwal di Master Alur', $oleh, $olehId, $sekarang);
            $jadwal = DB::table(self::T_PROGRAM)->where('Program_Id', $programId)->where('Tahap_Kode', $kodeTahap)->first();
            $hapus = DB::table(self::T_PROGRAM)->where('Program_Id', $programId)->where('Tahap_Kode', $kodeTahap)->delete();

            self::catatKolom($programId, $kodeTahap, 'LEPAS', [$jadwal->Buka_At ?? null, null], [$jadwal->Batas_At ?? null, null], $dilepas,
                'Tahap ini tanpa jadwal di Master Alur', $oleh, $olehId, $sekarang);

            return ['dilepas' => $dilepas, 'jadwalDihapus' => $hapus > 0];
        });
    }

    /**
     * Jadwal kandidat tertentu (satuan maupun massal) — dua jalan saja:
     *
     *   ATUR    kandidat yang BELUM berjadwal di tahap yang oleh Master Alur
     *           diberi jadwal: waktu dibuka (kosong = terbuka sekarang) + batas
     *           akhir. Yang sudah berjadwal, atau tahapnya TANPA, dilewati.
     *   HARI    perpanjang + N hari  ┐ hanya kandidat yang SUDAH berjadwal, dari
     *   JAM     perpanjang + N jam   │ batas masing-masing (yang sudah lewat: dari
     *   SAMPAI  perpanjang sampai    ┘ sekarang — lihat batasBaru()). SAMPAI hanya
     *           tanggal & jam X        mengenai yang batasnya lebih awal dari X.
     *
     *   LEPAS   lepas batas waktu — hanya tahap yang oleh alur program sekarang
     *           sudah "Tanpa jadwal" (lihat lepasProgram()).
     *   UBAH    KHUSUS SUPERADMIN (pemanggil yang memastikan): tetapkan waktu
     *           dibuka & batas akhir apa adanya — boleh mundur — untuk kandidat
     *           yang terikat jadwal. Alasan wajib; menjadi jadwal pribadi.
     *
     * Tidak ada jalan untuk memundurkan batas: sesudah dijadwalkan, kandidat
     * hanya bisa diperpanjang atau dilepas. Yang tidak layak dilewati dan dihitung.
     *
     * @param  int[]  $tahapIds  Id_Lamaran_Tahap
     * @return array{diubah:int, dilewati:int}
     */
    public static function aturKandidat(array $tahapIds, string $cara, ?Carbon $buka, ?Carbon $sampai, ?int $nilai, ?string $alasan, string $oleh, ?int $olehId): array
    {
        $sekarang = now();
        $tahapIds = array_values(array_unique(array_map('intval', $tahapIds)));

        return DB::transaction(function () use ($tahapIds, $cara, $buka, $sampai, $nilai, $alasan, $oleh, $olehId, $sekarang) {
            $layak = DB::table(self::T_TAHAP.' as t')
                ->join('N_WEB_CAREERS_Lamaran as l', 'l.Id_Lamaran', '=', 't.Lamaran_Id')
                ->whereIn('t.Id_Lamaran_Tahap', $tahapIds)
                ->where('l.Status', 'BERJALAN')
                ->where('t.Status', 'BERJALAN')
                ->whereNotNull('t.Formulir_Kode')
                ->whereNull('t.Formulir_Pengisian_Id');

            $catatan = trim((string) $alasan);
            $ekor = $catatan !== '' ? " — {$catatan}" : '';
            $diubah = 0;

            if ($cara === 'ATUR' && $sampai) {
                $sasaran = (clone $layak)->whereNull('t.Batas_At')->whereIn('t.Batas_Mode', [self::MANUAL, self::OTOMATIS]);
                $teks = 'Jadwal pribadi: '.($buka ? 'dibuka '.self::teks($buka).', ' : 'terbuka sekarang, ').'batas '.self::teks($sampai).$ekor;
                self::catatBanyak($sasaran, [$buka, '?'], [$sampai], self::SUMBER_PRIBADI, $teks, $oleh, $olehId, $sekarang);
                $diubah = (clone $sasaran)->update(self::isiTulis($buka, $sampai, self::SUMBER_PRIBADI, $oleh, $olehId, $sekarang));
            } elseif (in_array($cara, ['HARI', 'JAM'], true) && $nilai > 0) {
                // Cermin batasBaru() dalam SQL — satu perintah untuk semua,
                // masing-masing dari batasnya sendiri.
                [$ekspr, $ikat] = $cara === 'HARI'
                    ? ['DATEADD(day, CASE WHEN t.Batas_At < ? THEN DATEDIFF(day, t.Batas_At, ?) ELSE 0 END + ?, t.Batas_At)', [$sekarang, $sekarang, $nilai]]
                    : ['DATEADD(hour, ?, CASE WHEN t.Batas_At < ? THEN ? ELSE t.Batas_At END)', [$nilai, $sekarang, $sekarang]];
                $sasaran = (clone $layak)->whereNotNull('t.Batas_At');
                $teks = "Diperpanjang {$nilai} ".($cara === 'HARI' ? 'hari' : 'jam').$ekor;
                self::catatBanyak($sasaran, [false, $ekspr], $ikat, self::SUMBER_PRIBADI, $teks, $oleh, $olehId, $sekarang);
                $diubah = (clone $sasaran)->update(self::isiTulisMaju(DB::raw(self::ganti($ekspr, $ikat)), self::SUMBER_PRIBADI, $oleh, $olehId, $sekarang));
            } elseif ($cara === 'UBAH' && $sampai) {
                $sasaran = self::terikat(clone $layak);
                $teks = 'Jadwal diedit superadmin: '.($buka ? 'dibuka '.self::teks($buka).', ' : 'terbuka sekarang, ').'batas '.self::teks($sampai).$ekor;
                self::catatBanyak($sasaran, [$buka, '?'], [$sampai], self::SUMBER_UBAH, $teks, $oleh, $olehId, $sekarang);
                $diubah = (clone $sasaran)->update(self::isiTulis($buka, $sampai, self::SUMBER_PRIBADI, $oleh, $olehId, $sekarang));
            } elseif ($cara === 'LEPAS') {
                // Hanya yang Master Alur-nya (alur program sekarang) sudah tanpa jadwal.
                $mode = [];
                $masihBerjadwal = function ($r) use (&$mode) {
                    $k = $r->Program_Id.'|'.$r->Kode;
                    $mode[$k] ??= (self::modeSekarang((int) $r->Program_Id, (string) $r->Kode) ?? self::TANPA);

                    return self::berjadwal($mode[$k]);
                };
                $boleh = (clone $layak)->get(['t.Id_Lamaran_Tahap', 'l.Program_Id', 't.Kode'])
                    ->reject($masihBerjadwal)
                    ->pluck('Id_Lamaran_Tahap')->map(fn ($v) => (int) $v)->all();
                $sasaran = self::terikat((clone $layak)->whereIn('t.Id_Lamaran_Tahap', $boleh ?: [0]));
                $diubah = self::tulisLepas($sasaran, 'Batas waktu dilepas'.$ekor, $oleh, $olehId, $sekarang);
            } elseif ($cara === 'SAMPAI' && $sampai && $sampai->gt($sekarang)) {
                $sasaran = (clone $layak)->whereNotNull('t.Batas_At')->where('t.Batas_At', '<', $sampai);
                $teks = 'Diperpanjang sampai '.self::teks($sampai).$ekor;
                self::catatBanyak($sasaran, [false, '?'], [$sampai], self::SUMBER_PRIBADI, $teks, $oleh, $olehId, $sekarang);
                $diubah = (clone $sasaran)->update(self::isiTulisMaju($sampai, self::SUMBER_PRIBADI, $oleh, $olehId, $sekarang));
            }

            return ['diubah' => $diubah, 'dilewati' => count($tahapIds) - $diubah];
        });
    }

    /**
     * Batas akhir baru untuk sebuah perpanjangan; null bila caranya tak dikenal.
     *
     *   HARI    + N hari dari batas lama. Bila batasnya SUDAH LEWAT, dihitung dari
     *           hari ini dengan jam batas lamanya — "+2 hari" pada batas Kamis
     *           23.59 yang terlewat, dilakukan Senin, menjadi Rabu 23.59, bukan
     *           Sabtu yang juga sudah lewat.
     *   JAM     + N jam dari batas lama, atau dari sekarang bila sudah lewat.
     *   SAMPAI  tanggal & jam pilihan admin.
     *
     * Dicerminkan apa adanya di aturKandidat() (SQL) dan utils/career/batasIsi.js
     * (pratinjau di layar).
     */
    public static function batasBaru(Carbon $lama, string $cara, ?int $nilai, ?Carbon $sampai, ?Carbon $sekarang = null): ?Carbon
    {
        $sekarang ??= now();
        $lewat = $lama->lt($sekarang);

        return match ($cara) {
            'HARI' => $nilai > 0
                ? $lama->copy()->addDays(($lewat ? (int) $lama->copy()->startOfDay()->diffInDays($sekarang->copy()->startOfDay()) : 0) + $nilai)
                : null,
            'JAM' => $nilai > 0 ? ($lewat ? $sekarang : $lama)->copy()->addHours($nilai) : null,
            'SAMPAI' => $sampai?->copy(),
            default => null,
        };
    }

    /**
     * Hold dilepas → batas akhir digeser sepanjang lama ditahan. Kandidat tidak
     * salah apa-apa selama tahapnya sengaja ditunda tim; jamnya ikut berhenti.
     *
     * @param  object  $tahap  baris Lamaran_Tahap SEBELUM hold-nya dilepas (masih membawa Hold_At)
     */
    public static function geserKarenaHold(object $tahap, Carbon $sekarang, string $oleh, ?int $olehId): void
    {
        if (! self::siap() || empty($tahap->Hold_At) || empty($tahap->Batas_At) || ! empty($tahap->Formulir_Pengisian_Id)) {
            return;
        }

        $detik = (int) Carbon::parse($tahap->Hold_At)->diffInSeconds($sekarang, false);
        if ($detik < 60) {
            return;
        }

        $t = (object) [
            'Id_Lamaran_Tahap' => $tahap->Id_Lamaran_Tahap,
            'Lamaran_Id' => $tahap->Lamaran_Id,
            'Buka_At' => $tahap->Buka_At ?? null,
            'Batas_At' => $tahap->Batas_At,
            'Batas_Aksi' => $tahap->Batas_Aksi ?? null,
        ];
        $baru = Carbon::parse($tahap->Batas_At)->addSeconds($detik);
        $lama = (int) ceil($detik / 86400);
        $buka = ! empty($tahap->Buka_At) ? Carbon::parse($tahap->Buka_At) : null;

        self::tulisSatu($t, $buka, $baru, self::SUMBER_PRIBADI, "Batas digeser sepanjang lama ditahan (±{$lama} hari)", $oleh, $olehId, $sekarang);
    }

    // ══ PENJAGA ═══════════════════════════════════════════════════════════

    /**
     * Pesan penolakan bila formulir tahap ini TERKUNCI; null bila boleh diisi.
     * Dipanggil setiap pintu tulis kandidat (kirim, draf, unggah, hapus
     * berkas) — layar bisa dilewati lewat DevTools.
     */
    public static function pesanKunci(int $lamaranTahapId): ?string
    {
        $s = self::statusTahap($lamaranTahapId);
        if (! ($s['terkunci'] ?? false)) {
            return null;
        }

        return match ($s['alasan']) {
            self::BELUM_DIATUR => 'Formulir tahap ini belum dibuka — jadwal pengisiannya belum diatur tim rekrutmen.',
            self::BELUM_BUKA => 'Formulir tahap ini baru dibuka '.$s['bukaTeks'].'.',
            default => 'Batas pengisian tahap ini sudah lewat ('.$s['teks'].'). Formulirnya tidak bisa dikirim lagi — hubungi tim rekrutmen bila memerlukan perpanjangan.',
        };
    }

    /** Keadaan jadwal satu tahap, dibaca langsung dari basis data (portal, penjaga). */
    public static function statusTahap(int $lamaranTahapId): ?array
    {
        if (! self::siap()) {
            return null;
        }

        return self::status(DB::table(self::T_TAHAP)->where('Id_Lamaran_Tahap', $lamaranTahapId)->first(self::KOLOM_STATUS));
    }

    // ══ DALAMAN ═══════════════════════════════════════════════════════════

    /** Tahap kolom $kode milik kandidat BERJALAN sebuah program yang sedang berjalan. */
    private static function tahapProgram(int $programId, string $kode)
    {
        return DB::table(self::T_TAHAP.' as t')
            ->join('N_WEB_CAREERS_Lamaran as l', 'l.Id_Lamaran', '=', 't.Lamaran_Id')
            ->where('l.Program_Id', $programId)
            ->where('l.Status', 'BERJALAN')
            ->where('t.Kode', $kode)
            ->where('t.Status', 'BERJALAN')
            ->whereNotNull('t.Formulir_Kode');
    }

    /** Tabel riwayat kolom sudah dibuat? Sebelum itu pencatatannya dilewati. */
    private static function riwayatKolomSiap(): bool
    {
        try {
            return Skema::adaTabel(self::T_RIWAYAT_KOLOM);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Catat satu perubahan jadwal KOLOM — tetap tercatat walau kolomnya sedang
     * tidak berisi kandidat.
     *
     * @param  array{0: mixed, 1: mixed}  $buka  [lama, baru]
     * @param  array{0: mixed, 1: mixed}  $batas  [lama, baru]
     */
    private static function catatKolom(int $programId, string $kodeTahap, string $aksi, array $buka, array $batas, int $jumlah, ?string $alasan, string $oleh, ?int $olehId, Carbon $sekarang): void
    {
        if (! self::riwayatKolomSiap()) {
            return;
        }

        $waktu = fn ($v) => $v ? Carbon::parse($v)->format('Y-m-d H:i:s') : null;

        DB::table(self::T_RIWAYAT_KOLOM)->insert([
            'Program_Id' => $programId,
            'Tahap_Kode' => $kodeTahap,
            'Aksi' => $aksi,
            'Buka_Lama' => $waktu($buka[0]),
            'Buka_Baru' => $waktu($buka[1]),
            'Batas_Lama' => $waktu($batas[0]),
            'Batas_Baru' => $waktu($batas[1]),
            'Jumlah_Kandidat' => $jumlah,
            'Alasan' => $alasan !== null ? mb_substr(trim($alasan), 0, 500) : null,
            'Created_At' => $sekarang,
            'Created_By' => mb_substr($oleh, 0, 200),
            'Created_By_Id' => $olehId,
            'Created_By_Role' => mb_substr((string) (session('career_auth.role') ?? ''), 0, 20) ?: null,
        ]);
    }

    /**
     * Saring baris yang BELUM berjadwal ke yang memang boleh dijadwalkan: bila
     * alur program sekarang sudah "Tanpa jadwal", hanya yang snapshot-nya masih
     * berjadwal (rombongan alur lama) yang ikut jadwal kolom.
     */
    private static function hanyaBerjadwal($kueri, int $programId, string $kodeTahap)
    {
        return self::berjadwal(self::modeSekarang($programId, $kodeTahap))
            ? $kueri
            : $kueri->whereIn('t.Batas_Mode', [self::MANUAL, self::OTOMATIS]);
    }

    /** Baris yang masih terikat jadwal: snapshot berjadwal, atau sudah bertanggal. */
    private static function terikat($kueri)
    {
        return $kueri->where(fn ($w) => $w->whereIn('t.Batas_Mode', [self::MANUAL, self::OTOMATIS])
            ->orWhereNotNull('t.Batas_At')
            ->orWhereNotNull('t.Buka_At'));
    }

    /**
     * Lepas batas waktu sekumpulan baris: tahapnya menjadi TANPA, tanpa waktu
     * dibuka maupun batas — formulir terbuka sampai dikirim. Riwayat dicatat
     * lebih dulu (Batas_Baru NULL = tanpa batas).
     */
    private static function tulisLepas($kueri, string $alasan, string $oleh, ?int $olehId, Carbon $sekarang): int
    {
        self::catatBanyak($kueri, [null, 'NULL'], [], self::SUMBER_LEPAS, $alasan, $oleh, $olehId, $sekarang);

        return (clone $kueri)->update([
            't.Batas_Mode' => self::TANPA,
            't.Batas_Hari' => null,
            't.Buka_At' => null,
            't.Batas_At' => null,
            't.Batas_Sumber' => null,
            't.Batas_Pengingat_At' => null,
            't.Updated_At' => $sekarang, 't.Updated_By' => $oleh, 't.Updated_By_Id' => $olehId,
        ]);
    }

    /**
     * Batas sekumpulan baris maju ke $batas dan mulai mengikuti kolom (sumber
     * PROGRAM, jadi perpanjangan kolom berikutnya ikut membawanya). Waktu
     * dibukanya tidak disentuh.
     */
    private static function majuKe($kueri, Carbon $batas, string $alasan, string $oleh, ?int $olehId, Carbon $sekarang): int
    {
        self::catatBanyak($kueri, [false, '?'], [$batas], self::SUMBER_PROGRAM, $alasan, $oleh, $olehId, $sekarang);

        return (clone $kueri)->update(self::isiTulisMaju($batas, self::SUMBER_PROGRAM, $oleh, $olehId, $sekarang));
    }

    /** Ikatan `?` ditanam sebagai literal — hanya untuk nilai yang disusun di sini (tanggal & angka). */
    private static function ganti(string $ekspr, array $ikat): string
    {
        foreach ($ikat as $v) {
            $lit = $v instanceof Carbon ? "'".$v->format('Y-m-d H:i:s')."'" : (is_int($v) ? (string) $v : "'".str_replace("'", "''", (string) $v)."'");
            $ekspr = preg_replace('/\?/', $lit, $ekspr, 1);
        }

        return $ekspr;
    }

    private static function isiTulis(?Carbon $buka, Carbon $batas, string $sumber, string $oleh, ?int $olehId, Carbon $sekarang): array
    {
        return [
            't.Buka_At' => $buka,
            't.Batas_At' => $batas,
            't.Batas_Sumber' => $sumber,
            't.Batas_Aksi' => self::KUNCI,
            // Jadwal berubah → pengingat H-1 boleh dikirim lagi untuk batas barunya.
            't.Batas_Pengingat_At' => null,
            't.Updated_At' => $sekarang, 't.Updated_By' => $oleh, 't.Updated_By_Id' => $olehId,
        ];
    }

    /** Perpanjangan: hanya batas akhir yang maju; waktu dibuka tetap. */
    private static function isiTulisMaju(mixed $batas, string $sumber, string $oleh, ?int $olehId, Carbon $sekarang): array
    {
        return [
            't.Batas_At' => $batas,
            't.Batas_Sumber' => $sumber,
            't.Batas_Aksi' => self::KUNCI,
            't.Batas_Pengingat_At' => null,
            't.Updated_At' => $sekarang, 't.Updated_By' => $oleh, 't.Updated_By_Id' => $olehId,
        ];
    }

    /**
     * Riwayat untuk sekumpulan baris — satu INSERT … SELECT, berapa pun jumlahnya.
     *
     * @param  array{0: Carbon|null|false, 1: string}  $baru  [waktu dibuka baru — false = tetap, ungkapan batas baru]
     */
    private static function catatBanyak($kueri, array $baru, array $ikatBatas, string $sumber, string $alasan, string $oleh, ?int $olehId, Carbon $sekarang): void
    {
        [$bukaBaru, $eksprBatas] = $baru;
        $eksprBuka = $bukaBaru === false ? 't.Buka_At' : '?';
        $ikatBuka = $bukaBaru === false ? [] : [$bukaBaru instanceof Carbon ? $bukaBaru->format('Y-m-d H:i:s') : null];

        $pilih = (clone $kueri)->selectRaw(
            "t.Id_Lamaran_Tahap, t.Lamaran_Id, t.Buka_At, {$eksprBuka}, t.Batas_At, {$eksprBatas}, ?, ?, ?, ?, ?",
            array_merge(
                $ikatBuka,
                array_map(fn ($v) => $v instanceof Carbon ? $v->format('Y-m-d H:i:s') : $v, $ikatBatas),
                [$sumber, mb_substr($alasan, 0, 500), $sekarang->format('Y-m-d H:i:s'), mb_substr($oleh, 0, 200), $olehId],
            ),
        );

        DB::table(self::T_RIWAYAT)->insertUsing(
            ['Lamaran_Tahap_Id', 'Lamaran_Id', 'Buka_Lama', 'Buka_Baru', 'Batas_Lama', 'Batas_Baru', 'Sumber', 'Alasan', 'Created_At', 'Created_By', 'Created_By_Id'],
            $pilih,
        );
    }

    /** Tulis jadwal SATU tahap berikut riwayatnya; diam bila nilainya sama. */
    private static function tulisSatu(object $t, ?Carbon $buka, ?Carbon $batas, ?string $sumber, string $alasan, ?string $oleh, ?int $olehId, Carbon $sekarang): void
    {
        $bukaLama = ! empty($t->Buka_At ?? null) ? Carbon::parse($t->Buka_At) : null;
        $batasLama = ! empty($t->Batas_At ?? null) ? Carbon::parse($t->Batas_At) : null;
        $sama = fn (?Carbon $a, ?Carbon $b) => $a?->format('Y-m-d H:i:s') === $b?->format('Y-m-d H:i:s');
        if ($sama($bukaLama, $buka) && $sama($batasLama, $batas)) {
            return;
        }

        $oleh = $oleh ?: 'SISTEM';

        DB::table(self::T_TAHAP)->where('Id_Lamaran_Tahap', $t->Id_Lamaran_Tahap)->update([
            'Buka_At' => $buka,
            'Batas_At' => $batas,
            'Batas_Sumber' => $batas ? $sumber : null,
            'Batas_Aksi' => $batas ? self::KUNCI : ($t->Batas_Aksi ?? null),
            'Batas_Pengingat_At' => null,
            'Updated_At' => $sekarang,
        ]);

        DB::table(self::T_RIWAYAT)->insert([
            'Lamaran_Tahap_Id' => $t->Id_Lamaran_Tahap,
            'Lamaran_Id' => $t->Lamaran_Id,
            'Buka_Lama' => $bukaLama,
            'Buka_Baru' => $buka,
            'Batas_Lama' => $batasLama,
            'Batas_Baru' => $batas,
            'Sumber' => $sumber,
            'Alasan' => mb_substr($alasan, 0, 500),
            'Created_At' => $sekarang,
            'Created_By' => mb_substr($oleh, 0, 200),
            'Created_By_Id' => $olehId,
        ]);
    }
}
