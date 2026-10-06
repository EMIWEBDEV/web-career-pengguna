<?php

namespace App\Support\Career;

use App\Jobs\Career\WcPengingatKonfirmasiJob;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * PENGINGAT OTOMATIS KONFIRMASI KEHADIRAN.
 *
 * Kandidat yang belum menjawab undangan (wawancara / FGD / tes offline)
 * diingatkan pada jam-jam tetap — bawaan 05.00, 08.00, 12.00, 16.00 WIB —
 * setiap hari sampai jadwalnya dimulai. Hari-H ikut, sebelum jam mulai: batas
 * konfirmasi adalah waktu mulai jadwal (KonfirmasiJadwal::batasDari). Paling
 * banyak empat email per kandidat per hari (keputusan user 3 Okt 2026).
 *
 * ══ ALUR ══
 *
 *   Cloud Scheduler "0 5,8,12,16 * * *" (Asia/Jakarta)
 *     → POST /api/tugas/pengingat-konfirmasi   (header X-Tugas-Token)
 *     → WcPengingatKonfirmasiJob, antrean wc-pengingatkonfirmasi → jalankan()
 *     → per kandidat: klaim slot di buku pengingat → baris jejak ANTRE →
 *       WcKonfirmasiEmailJob di antrean yang SAMA (surel konfirmasi lain tetap
 *       di wc-konfirmasimail, jadi pengingat massal tidak menyumbat tanda
 *       terima jawaban kandidat).
 *
 * ══ TIDAK MENGUNCI ══
 *
 * Calon penerima dibaca HANYA dari snapshot CRM_Konfirmasi_Email — tanpa join —
 * dengan WITH (NOLOCK), lewat indeks berfilter IX_WC_CRMK_Tick. Produksi tidak
 * memakai READ_COMMITTED_SNAPSHOT (dicek 3 Okt 2026), jadi SELECT biasa memasang
 * kunci baca yang bisa tertahan oleh — dan menahan — kandidat yang sedang
 * menjawab. Bacaan kotor tidak berbahaya di sini: setiap calon diperiksa ulang
 * dari baris aslinya sebelum apa pun ditulis, dan sekali lagi oleh
 * SuratKonfirmasi tepat sebelum surelnya berangkat. Tidak ada transaksi yang
 * membungkus pemindaian maupun panggilan ke EVO Mail.
 *
 * ══ SATU PER SLOT, EMPAT PER HARI ══
 *
 * Klaim = INSERT ke buku pengingat berkunci unik (Id_Users, Tanggal, Slot).
 * Cloud Scheduler yang mencoba ulang, dua tugas yang kebetulan berjalan
 * bersamaan, atau putaran lanjutan yang tumpang tindih DITOLAK basis data —
 * bukan diharapkan tidak terjadi. Kandidat dengan dua undangan tetap menerima
 * satu email per slot: undangan yang batasnya paling dekat didahulukan.
 */
final class PengingatKonfirmasi
{
    public const TABEL = 'N_WEB_CAREERS_CRM_Konfirmasi_Pengingat';

    public const ANTRE = 'ANTRE';

    public const TERKIRIM = 'TERKIRIM';

    public const GAGAL = 'GAGAL';

    public const LEWAT = 'LEWAT';

    /** Penanda pelaku di jejak & snapshot CRM. */
    public const OLEH = 'SISTEM (pengingat otomatis)';

    // ════════════════════════════════════════════════════════════════════════
    //  KESIAPAN & ATURAN WAKTU
    // ════════════════════════════════════════════════════════════════════════

    /** Skema fitur konfirmasi + buku pengingat (docs/03-10-2026/01) sudah dipasang? */
    public static function siap(): bool
    {
        try {
            return KonfirmasiJadwal::siap() && Skema::adaTabel(self::TABEL);
        } catch (\Throwable $e) {
            return false;
        }
    }

    public static function aktif(): bool
    {
        return (bool) config('konfirmasi.pengingat.aktif', true);
    }

    /**
     * "05,08,12,16" → [5, 8, 12, 16]. Menerima juga "5", "05:00", dan spasi;
     * jam di luar 0–23 dan nilai rusak dibuang, duplikat dilebur, diurutkan.
     *
     * @return array<int>
     */
    public static function jamSlot(string|array|null $nilai = null): array
    {
        $nilai ??= config('konfirmasi.pengingat.jam', '05,08,12,16');
        $daftar = is_array($nilai) ? $nilai : preg_split('/[\s,;]+/', (string) $nilai, -1, PREG_SPLIT_NO_EMPTY);

        $jam = [];
        foreach ($daftar as $j) {
            if (preg_match('/^(\d{1,2})(?::00)?$/', trim((string) $j), $m) && (int) $m[1] <= 23) {
                $jam[(int) $m[1]] = (int) $m[1];
            }
        }
        ksort($jam);

        return array_values($jam);
    }

    /** Jam slot yang memuat $waktu (HH:00–HH:59), atau null di luar jam pengingat. */
    public static function slotUntuk(Carbon $waktu, array $jam): ?int
    {
        $h = (int) $waktu->format('G');

        return in_array($h, $jam, true) ? $h : null;
    }

    /**
     * Slot yang sedang berlangsung — dihitung SAAT PEMICU ditekan, lalu dibawa
     * tugasnya. Tugas yang baru jalan beberapa menit kemudian tetap tercatat
     * di slot yang benar.
     *
     * @return array{tanggal:string, jam:int}|null
     */
    public static function slotSekarang(?Carbon $sekarang = null): ?array
    {
        $sekarang ??= now();
        $jam = self::slotUntuk($sekarang, self::jamSlot());

        return $jam === null ? null : ['tanggal' => $sekarang->toDateString(), 'jam' => $jam];
    }

    /** Tugas yang tertahan di antrean jauh melewati jam slotnya tidak dikirim lagi. */
    public static function slotKedaluwarsa(string $tanggal, int $jam, ?Carbon $sekarang = null, ?int $berlakuMenit = null): bool
    {
        $berlakuMenit ??= (int) config('konfirmasi.pengingat.slot_berlaku_menit', 120);
        $mulai = Carbon::parse($tanggal)->setTime($jam, 0);

        return ($sekarang ?? now())->gt($mulai->addMinutes(max(15, $berlakuMenit)));
    }

    /** "2026-10-03 08:00" — label slot untuk log & jejak. */
    public static function labelSlot(string $tanggal, int $jam): string
    {
        return sprintf('%s %02d:00', $tanggal, $jam);
    }

    /**
     * Sisa waktu menuju batas konfirmasi, untuk kalimat surel:
     * "2 hari 21 jam lagi", "2 hari lagi", "5 jam lagi", "40 menit lagi".
     *
     * Lebih rinci daripada UndanganJadwal::sisaTeks ("2 hari lagi") — untuk
     * pengingat yang datang beberapa kali sehari, "2 hari lagi" yang tidak
     * berubah dari pagi sampai sore terbaca seperti surel yang sama dikirim
     * ulang.
     */
    public static function sisaTeks(Carbon $dari, Carbon $ke): string
    {
        $menit = (int) floor(max(0, $dari->diffInSeconds($ke, false)) / 60);

        if ($menit < 1) {
            return 'kurang dari 1 menit lagi';
        }
        if ($menit < 60) {
            return "{$menit} menit lagi";
        }

        $jam = intdiv($menit, 60);
        if ($jam < 24) {
            return "{$jam} jam lagi";
        }

        $hari = intdiv($jam, 24);
        $sisaJam = $jam % 24;

        return $sisaJam > 0 ? "{$hari} hari {$sisaJam} jam lagi" : "{$hari} hari lagi";
    }

    // ════════════════════════════════════════════════════════════════════════
    //  PEMILIHAN CALON — SATU KUERI, NOLOCK, TANPA JOIN KE TABEL PROSES
    // ════════════════════════════════════════════════════════════════════════

    /**
     * Calon penerima satu slot: per kandidat SATU undangan (batas terdekat)
     * yang masih MENUNGGU, batasnya belum lewat, kandidatnya tidak menerima
     * email konfirmasi dalam N jam terakhir, slot ini belum diklaim untuknya,
     * dan jatah hariannya belum habis.
     *
     * @return array<object{Id_CRM_Konfirmasi:int, Id_Users:int, Batas_Konfirmasi:string}>
     */
    public static function calon(string $tanggal, int $jam, Carbon $sekarang, int $maks): array
    {
        $maks = max(1, min(1000, $maks));
        $jedaJam = max(0, (int) config('konfirmasi.pengingat.jeda_jam', 2));
        $maksHarian = max(1, (int) config('konfirmasi.pengingat.maks_harian', 4));
        $crm = KonfirmasiJadwal::T_CRM;
        $buku = self::TABEL;

        // Waktu ber-"T" (ISO 8601) — satu-satunya bentuk teks yang dibaca
        // DATETIME sama di setiap setelan bahasa SQL Server.
        $iso = fn (Carbon $c) => $c->format('Y-m-d\TH:i:s');

        // TOP ditulis langsung (sudah di-cast int): sebagai parameter, driver
        // mengirimnya sebagai teks dan SQL Server menolaknya.
        $sql = <<<SQL
            WITH c AS (
                SELECT k.Id_CRM_Konfirmasi, k.Id_Users, k.Batas_Konfirmasi,
                       ROW_NUMBER() OVER (PARTITION BY k.Id_Users
                                          ORDER BY k.Batas_Konfirmasi, k.Id_CRM_Konfirmasi) AS Urut
                FROM dbo.{$crm} k WITH (NOLOCK)
                WHERE k.Selesai = 'T'
                  AND k.Status_Konfirmasi = 'MENUNGGU'
                  AND k.Batas_Konfirmasi > ?
            )
            SELECT TOP ({$maks}) c.Id_CRM_Konfirmasi, c.Id_Users, c.Batas_Konfirmasi
            FROM c
            WHERE c.Urut = 1
              AND NOT EXISTS (SELECT 1 FROM dbo.{$crm} k2 WITH (NOLOCK)
                              WHERE k2.Selesai = 'T' AND k2.Id_Users = c.Id_Users
                                AND k2.Terakhir_Kirim_At > ?)
              AND NOT EXISTS (SELECT 1 FROM dbo.{$buku} p WITH (NOLOCK)
                              WHERE p.Id_Users = c.Id_Users AND p.Tanggal = ? AND p.Slot = ?)
              AND (SELECT COUNT(1) FROM dbo.{$buku} p2 WITH (NOLOCK)
                   WHERE p2.Id_Users = c.Id_Users AND p2.Tanggal = ?) < ?
            ORDER BY c.Batas_Konfirmasi, c.Id_CRM_Konfirmasi
            SQL;

        return DB::select($sql, [
            $iso($sekarang),
            $iso($sekarang->copy()->subHours($jedaJam)),
            $tanggal, $jam,
            $tanggal, $maksHarian,
        ]);
    }

    // ════════════════════════════════════════════════════════════════════════
    //  MENJALANKAN SATU SLOT
    // ════════════════════════════════════════════════════════════════════════

    /**
     * Kerjakan satu putaran untuk satu slot.
     *
     * `sisa` = putaran ini penuh, mungkin masih ada calon — pemanggil
     * menjadwalkan putaran lanjutan. Kandidat yang sudah diklaim tidak akan
     * terpilih lagi (kuerinya mengecualikan slot yang sudah tercatat), jadi
     * putaran berikutnya otomatis mulai dari kandidat sesudahnya.
     *
     * @return array{slot:string, dipilih:int, diantrekan:int, lewat:array<int,string>, sisa:bool, calon:array}
     */
    public static function jalankan(string $tanggal, int $jam, bool $coba = false, ?int $maks = null): array
    {
        $sekarang = now();
        $maks = max(1, $maks ?? (int) config('konfirmasi.pengingat.per_putaran', 200));
        $calon = self::calon($tanggal, $jam, $sekarang, $maks);

        $hasil = [
            'slot' => self::labelSlot($tanggal, $jam),
            'dipilih' => count($calon),
            'diantrekan' => 0,
            'lewat' => [],
            'sisa' => ! $coba && count($calon) >= $maks,
            'calon' => [],
        ];

        if ($coba) {
            $hasil['calon'] = self::rincianCalon($calon, $sekarang);

            return $hasil;
        }

        $jeda = max(0, (int) config('konfirmasi.cicil_detik', 2));
        $urut = 0;

        foreach ($calon as $c) {
            $id = (int) $c->Id_CRM_Konfirmasi;
            try {
                [$status, $info] = self::proses($id, $tanggal, $jam, $urut * $jeda);
            } catch (\Throwable $e) {
                Log::channel('web_career')->error("[PENGINGAT] undangan CRM #{$id} slot {$hasil['slot']} gagal diproses: ".$e->getMessage());
                [$status, $info] = ['galat', mb_substr($e->getMessage(), 0, 200)];
            }

            if ($status === 'antre') {
                $hasil['diantrekan']++;
                $urut++;
            } elseif ($status !== 'sudah') {
                $hasil['lewat'][$id] = (string) $info;
            }
        }

        return $hasil;
    }

    /**
     * Satu kandidat: periksa ulang → klaim slot → catat → antrekan surel.
     *
     * @return array{0:string, 1:?string} [antre|lewat|sudah|galat, keterangan]
     */
    private static function proses(int $crmId, string $tanggal, int $jam, int $tundaDetik): array
    {
        // Baris ASLI (bukan bacaan NOLOCK) — snapshot bisa saja berubah sejak
        // dipindai. Belum diklaim, jadi slot kandidat tetap bebas bila ditolak.
        $crm = DB::table(KonfirmasiJadwal::T_CRM)->where('Id_CRM_Konfirmasi', $crmId)->first();
        if (! $crm || $crm->Selesai === 'Y' || $crm->Status_Konfirmasi !== KonfirmasiJadwal::MENUNGGU
            || Carbon::parse($crm->Batas_Konfirmasi)->lte(now())) {
            return ['lewat', 'Undangan sudah tidak menunggu jawaban.'];
        }

        // Klaim DI LUAR transaksi: satu percobaan per kandidat per slot. Bila
        // langkah sesudahnya gagal, klaimnya tetap ada (ditandai GAGAL) — tanpa
        // itu putaran lanjutan memilih kandidat yang sama berulang-ulang.
        $bukuId = self::klaim($crm, $tanggal, $jam);
        if (! $bukuId) {
            return ['sudah', null];
        }

        try {
            return DB::transaction(function () use ($crm, $bukuId, $tanggal, $jam, $tundaDetik) {
                $sub = KonfirmasiJadwal::konteks((int) $crm->Lamaran_Tahap_Tes_Id);
                $galat = KonfirmasiJadwal::galatTerbuka($sub, (int) $crm->Versi) ?? self::galatTambahan($sub);
                if (! $galat && KonfirmasiJadwal::statusEfektif($sub->Konfirmasi_Status, $crm->Batas_Konfirmasi) !== KonfirmasiJadwal::MENUNGGU) {
                    $galat = 'Kandidat sudah menjawab.';
                }
                if ($galat) {
                    self::tutup($bukuId, self::LEWAT, $galat);

                    return ['lewat', $galat];
                }

                $ke = min(255, ((int) $crm->Jumlah_Pengingat) + 1);
                $now = now();

                // Snapshot ikut dihitung: Agenda Seleksi, aturan jarak pengingat
                // manual, dan laporan membaca kolom-kolom ini.
                DB::table(KonfirmasiJadwal::T_CRM)->where('Id_CRM_Konfirmasi', $crm->Id_CRM_Konfirmasi)->update([
                    'Jumlah_Pengingat' => DB::raw('CASE WHEN Jumlah_Pengingat < 255 THEN Jumlah_Pengingat + 1 ELSE 255 END'),
                    'Terakhir_Kirim_At' => $now,
                    'Terakhir_Kirim_Jenis' => 'PENGINGAT',
                    'Terakhir_Kirim_Oleh' => KonfirmasiJadwal::SISTEM,
                    'Updated_At' => $now,
                ]);

                $jejakId = KonfirmasiJadwal::tulisJejak(
                    (int) $crm->Lamaran_Tahap_Tes_Id,
                    (int) $crm->Versi,
                    KonfirmasiJadwal::A_INGAT_JAWAB,
                    ['jenis' => KonfirmasiJadwal::SISTEM, 'kanal' => KonfirmasiJadwal::K_SISTEM, 'nama' => self::OLEH],
                    [
                        'Undangan' => 'ANTRE',
                        'Data_Json' => [
                            'surel' => KonfirmasiJadwal::S_PENGINGAT,
                            'otomatis' => true,
                            'slot' => self::labelSlot($tanggal, $jam),
                            'ke' => $ke,
                        ],
                    ],
                );

                DB::table(self::TABEL)->where('Id_CRM_Pengingat', $bukuId)->update(['Jejak_Id' => $jejakId, 'Ke' => $ke]);

                // Diantrekan SESUDAH commit, di antrean khusus pengingat.
                KonfirmasiJadwal::antrekan([$jejakId], $tundaDetik, WcPengingatKonfirmasiJob::QUEUE);

                return ['antre', null];
            });
        } catch (\Throwable $e) {
            self::tutup($bukuId, self::GAGAL, $e->getMessage());

            throw $e;
        }
    }

    /** Keadaan yang tidak diperiksa galatTerbuka() tetapi menghentikan pengingat otomatis. */
    private static function galatTambahan(object $sub): ?string
    {
        return match (true) {
            ($sub->StatusTahap ?? '') !== 'BERJALAN' => 'Tahap ini sudah tidak berjalan.',
            // Tim sengaja menahan kandidat — sistem tidak mengejarnya sendiri.
            ($sub->Hold_Flag ?? 'T') === 'Y' => 'Tahap sedang ditahan tim.',
            empty($sub->KandidatEmail) => 'Tanpa alamat email kandidat.',
            default => null,
        };
    }

    /**
     * INSERT baris buku. null = slot ini sudah milik kandidat tersebut, atau
     * jatah hariannya habis.
     */
    private static function klaim(object $crm, string $tanggal, int $jam): ?int
    {
        // Dijaga dua kali: oleh kunci unik slot (slotnya hanya empat) dan oleh
        // hitungan ini — supaya menambah jam di .env tidak diam-diam menaikkan
        // jumlah email per hari.
        $maksHarian = max(1, (int) config('konfirmasi.pengingat.maks_harian', 4));
        $hariIni = DB::table(self::TABEL)
            ->where('Id_Users', $crm->Id_Users)
            ->where('Tanggal', $tanggal)
            ->count();
        if ($hariIni >= $maksHarian) {
            return null;
        }

        try {
            return (int) DB::table(self::TABEL)->insertGetId([
                'Id_CRM_Konfirmasi' => (int) $crm->Id_CRM_Konfirmasi,
                'Lamaran_Tahap_Tes_Id' => (int) $crm->Lamaran_Tahap_Tes_Id,
                'Versi' => (int) $crm->Versi,
                'Id_Users' => (int) $crm->Id_Users,
                'Tanggal' => $tanggal,
                'Slot' => $jam,
                'Batas_Konfirmasi' => $crm->Batas_Konfirmasi,
                'Status' => self::ANTRE,
                'Created_At' => now(),
            ], 'Id_CRM_Pengingat');
        } catch (QueryException $e) {
            // 2627 = pelanggaran UNIQUE constraint, 2601 = indeks unik.
            if (in_array((int) ($e->errorInfo[1] ?? 0), [2601, 2627], true)) {
                return null;
            }

            throw $e;
        }
    }

    private static function tutup(int $bukuId, string $status, ?string $keterangan): void
    {
        try {
            DB::table(self::TABEL)->where('Id_CRM_Pengingat', $bukuId)->update([
                'Status' => $status,
                'Keterangan' => $keterangan !== null ? mb_substr($keterangan, 0, 300) : null,
                'Selesai_At' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::channel('web_career')->warning("[PENGINGAT] buku #{$bukuId} gagal ditandai {$status}: ".$e->getMessage());
        }
    }

    /**
     * Hasil kirim surel → buku pengingat. Dipanggil KonfirmasiJadwal::tandaiSurel
     * untuk baris jejak ber-Data_Json otomatis.
     */
    public static function tandai(int $jejakId, string $statusSurel, ?string $keterangan = null): void
    {
        $status = [
            'TERKIRIM' => self::TERKIRIM,
            'GAGAL' => self::GAGAL,
            'LEWAT' => self::LEWAT,
            'PRIVAT' => self::LEWAT,
        ][$statusSurel] ?? null;
        if (! $status) {
            return;
        }

        try {
            DB::table(self::TABEL)->where('Jejak_Id', $jejakId)->update([
                'Status' => $status,
                'Keterangan' => $keterangan !== null ? mb_substr($keterangan, 0, 300) : null,
                'Selesai_At' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::channel('web_career')->warning("[PENGINGAT] jejak #{$jejakId} gagal ditandai {$status}: ".$e->getMessage());
        }
    }

    /**
     * Rincian calon untuk mode --coba (nama, email, aktivitas) — satu kueri
     * berdasarkan kunci utama.
     */
    private static function rincianCalon(array $calon, Carbon $sekarang): array
    {
        if (! $calon) {
            return [];
        }

        $baris = DB::table(KonfirmasiJadwal::T_CRM)
            ->whereIn('Id_CRM_Konfirmasi', array_map(fn ($c) => (int) $c->Id_CRM_Konfirmasi, $calon))
            ->get(['Id_CRM_Konfirmasi', 'Nama', 'Email', 'Aktivitas', 'Batas_Konfirmasi', 'Jumlah_Pengingat'])
            ->keyBy('Id_CRM_Konfirmasi');

        return array_map(function ($c) use ($baris, $sekarang) {
            $b = $baris->get((int) $c->Id_CRM_Konfirmasi);

            return [
                'crm' => (int) $c->Id_CRM_Konfirmasi,
                'nama' => $b->Nama ?? '-',
                'email' => $b->Email ?? '-',
                'aktivitas' => $b->Aktivitas ?? '-',
                'batas' => (string) $c->Batas_Konfirmasi,
                'sisa' => self::sisaTeks($sekarang, Carbon::parse($c->Batas_Konfirmasi)),
                'pengingatKe' => ((int) ($b->Jumlah_Pengingat ?? 0)) + 1,
            ];
        }, $calon);
    }
}
