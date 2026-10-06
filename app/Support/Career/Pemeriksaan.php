<?php

namespace App\Support\Career;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * WEB CAREER — bentuk & rangkuman PEMERIKSAAN (background / reference check).
 *
 * Satu tempat untuk tiga hal yang kalau tersebar pasti berselisih:
 *
 *   1. daftar nilai yang sah (status, tingkat temuan, hubungan narasumber);
 *   2. bentuk payload yang dibaca layar;
 *   3. kalimat rangkuman yang tersimpan di kolom Adjudikasi_Ringkas.
 *
 * Seluruhnya berpenjaga `siap()` — selama skrip skemanya belum dijalankan,
 * modul ini diam dan halaman berjalan seperti sebelum fitur ini ada.
 */
class Pemeriksaan
{
    public const STATUS = ['MENUNGGU', 'PROSES', 'BERSIH', 'TEMUAN', 'TIDAK_TERVERIFIKASI'];

    public const TINGKAT = ['RENDAH', 'SEDANG', 'TINGGI'];

    public const STATUS_KONTAK = ['BELUM', 'TERHUBUNG', 'TIDAK_TERHUBUNG', 'MENOLAK'];

    public const HUBUNGAN = ['ATASAN_LANGSUNG', 'REKAN', 'KLIEN', 'HR'];

    public const METODE = ['TELEPON', 'EMAIL', 'FORM'];

    public const REKOMENDASI = ['YA', 'RAGU', 'TIDAK'];

    /**
     * Sumber persetujuan yang DICATAT PETUGAS, bukan dibaca dari formulir.
     *
     *   TELEPON  kandidat ditanya lewat telepon saat tahap ini dimulai — cara
     *            yang paling lazim dipakai untuk pemeriksaan latar belakang.
     *   BERKAS   persetujuan tertulis di luar sistem, dipindai & diarsipkan.
     *   TOLAK    kandidat menolak. Bukan "belum ada jejak": ini keputusan, dan
     *            alasannya wajib ditulis.
     */
    public const SUMBER_PETUGAS = ['TELEPON', 'BERKAS', 'TOLAK'];

    /**
     * Nilai adjudikasi per jenis pemeriksaan.
     *
     * SEMUANYA MUAT DI 20 KARAKTER — itu lebar kolom Adjudikasi, dan kolomnya
     * tidak dilebarkan. 'TIDAK_DIREKOMENDASIKAN' (22) karena itu ditulis
     * 'TIDAK_REKOMENDASI' (17); artinya sama, dan labelnya di layar tetap
     * berbunyi "Tidak direkomendasikan".
     *
     * Menambah nilai baru di sini: hitung dulu panjangnya.
     */
    public const ADJUDIKASI = [
        'BACKGROUND_CHECK' => ['BERSIH', 'PERTIMBANGAN', 'TIDAK_MEMENUHI'],
        'REFERENCE_CHECK' => ['DIREKOMENDASIKAN', 'RAGU', 'TIDAK_REKOMENDASI'],
    ];

    private static ?bool $siap = null;

    /** Skema pemeriksaan sudah dijalankan? */
    public static function siap(): bool
    {
        return self::$siap ??= Skema::adaTabel('N_WEB_CAREERS_Verifikasi_Latar')
            && Skema::adaTabel('N_WEB_CAREERS_Referensi_Kandidat')
            && Skema::adaKolom('N_WEB_CAREERS_Lamaran_Tahap_Tes', 'Adjudikasi');
    }

    /**
     * Tipe tahap ini pemeriksaan? — masternya yang menjawab.
     *
     * Daftar kode di bawah cuma cadangan untuk lingkungan yang kolomnya belum
     * ada; begitu Flag_Pemeriksaan hadir, tipe pemeriksaan baru cukup ditandai
     * di master tanpa menyentuh kode.
     */
    public static function untuk(?string $tipeKode): bool
    {
        return $tipeKode ? isset(self::petaPeriksa()[$tipeKode]) : false;
    }

    /**
     * PETA TIPE PEMERIKSAAN — DIBACA SEKALI PER PERMINTAAN.
     *
     * Bentuk sebelumnya bertanya ke database pada SETIAP pemanggilan untuk()
     * — dan untuk() dipanggil sekali per aktivitas kandidat. Pada papan
     * worklist berisi 352 kandidat itu berarti ~1.100 kueri `exists`, DITAMBAH
     * ~1.100 pemeriksaan skema (`Schema::hasColumn` menembak sys.columns, dan
     * satu pemeriksaan saja memakan puluhan milidetik di SQL Server jarak
     * jauh). Dua pemanggil semacam ini menghabiskan lebih dari 300 detik dari
     * satu permintaan papan — endpoint-nya tidak pernah selesai dimuat.
     *
     * Masternya belasan baris dan tidak berubah di tengah permintaan, jadi
     * dibaca sekali lalu dipegang. Statis per proses PHP: pada permintaan web
     * ia hidup selama satu permintaan, persis seumur data yang diwakilinya.
     */
    private static ?array $petaPeriksa = null;

    /** @return array<string,bool> kode tipe yang bertanda, sebagai himpunan */
    private static function petaPeriksa(): array
    {
        if (self::$petaPeriksa !== null) {
            return self::$petaPeriksa;
        }

        // Lingkungan yang kolom penandanya belum ada tetap dilayani daftar
        // cadangan di bawah — sama seperti sebelumnya, hanya tidak lagi
        // ditanyakan berulang kali. Dibaca dari master tipe yang SUDAH dimuat
        // (AlurKolom::masterTipe, sekali per permintaan), bukan kueri sendiri.
        $kode = Skema::adaKolom('N_WEB_CAREERS_Master_Tipe_Tahap', 'Flag_Pemeriksaan')
            ? AlurKolom::masterTipe()
                ->filter(fn ($t) => strtoupper(trim((string) ($t->Flag_Pemeriksaan ?? ''))) === 'Y')
                ->keys()->map(fn ($k) => (string) $k)->all()
            : ['REFERENCE_CHECK', 'BACKGROUND_CHECK'];

        return self::$petaPeriksa = array_fill_keys($kode, true);
    }

    /** Master komponen yang aktif — dipakai layar untuk menawarkan pilihan. */
    public static function jenisTersedia(): array
    {
        if (! Skema::adaTabel('N_WEB_CAREERS_Master_Jenis_Verifikasi')) {
            return [];
        }

        return DB::table('N_WEB_CAREERS_Master_Jenis_Verifikasi')
            ->where('Flag_Aktif', 'Y')
            ->orderBy('Urutan')
            ->get()
            ->map(fn ($r) => [
                'kode' => $r->Kode,
                'nama' => $r->Nama,
                'deskripsi' => $r->Deskripsi,
                'ikon' => $r->Ikon,
                'warna' => $r->Warna,
                // Penanda data pribadi spesifik (UU PDP) — layar memakainya untuk
                // mengingatkan bahwa isinya tidak boleh dibagikan sembarangan.
                'sensitif' => ($r->Flag_Sensitif ?? 'T') === 'Y',
            ])
            ->values()
            ->all();
    }

    /**
     * Isi pemeriksaan satu aktivitas — komponen, narasumber, dan adjudikasinya.
     *
     * Null bila aktivitasnya bukan pemeriksaan atau skemanya belum ada, sehingga
     * layar cukup memeriksa satu kunci untuk tahu perlu menampilkan panel atau
     * tidak.
     */
    public static function bentuk(object $sub, bool $penuh = false): ?array
    {
        if (! self::siap() || ! self::untuk($sub->Tipe_Tahap_Kode ?? null)) {
            return null;
        }

        $ref = ($sub->Tipe_Tahap_Kode ?? '') === 'REFERENCE_CHECK';

        return [
            'jenis' => $ref ? 'REFERENSI' : 'LATAR',
            'komponen' => $ref ? [] : self::komponen((int) $sub->Id_Lamaran_Tahap_Tes, $penuh),
            'persetujuan' => self::persetujuanAktivitas($sub),
            'tanggapan' => [
                'dimintaPada' => ($sub->Tanggapan_Diminta_At ?? null) ? (string) $sub->Tanggapan_Diminta_At : null,
                'isi' => $sub->Tanggapan_Isi ?? null,
                'pada' => ($sub->Tanggapan_At ?? null) ? (string) $sub->Tanggapan_At : null,
            ],
            'narasumber' => $ref ? self::narasumber((int) $sub->Id_Lamaran_Tahap_Tes) : [],
            'tersedia' => $ref ? [] : self::jenisTersedia(),
            'pilihanAdjudikasi' => self::ADJUDIKASI[$sub->Tipe_Tahap_Kode ?? ''] ?? [],
            'adjudikasi' => $sub->Adjudikasi ?? null,
            'adjudikasiOleh' => $sub->Adjudikasi_By ?? null,
            'adjudikasiPada' => ($sub->Adjudikasi_At ?? null) ? (string) $sub->Adjudikasi_At : null,
            'ringkas' => $sub->Adjudikasi_Ringkas ?? null,
        ];
    }

    /**
     * Komponen pemeriksaan. `$penuh = false` MENGUNCI isi temuan yang
     * ditandai sensitif.
     *
     * Payload Worklist memuat ratusan kandidat sekaligus; menyertakan isi
     * temuan catatan hukum di dalamnya berarti data pribadi spesifik terkirim
     * ke layar setiap kali daftar dibuka — tanpa ada yang benar-benar
     * membacanya, dan tanpa satu pun jejak siapa menerimanya.
     *
     * Isi penuhnya diminta terpisah lewat endpoint `buka`, dan di sanalah
     * pembukaannya dicatat.
     */
    public static function komponen(int $subTesId, bool $penuh = false): array
    {
        $sensitif = self::kodeSensitif();

        return DB::table('N_WEB_CAREERS_Verifikasi_Latar')
            ->where('Lamaran_Tahap_Tes_Id', $subTesId)
            ->orderBy('Id_Verifikasi_Latar')
            ->get()
            ->map(fn ($r) => [
                'id' => (int) $r->Id_Verifikasi_Latar,
                'kode' => $r->Jenis_Kode,
                'nama' => $r->Jenis_Nama,
                'status' => $r->Status,
                'tingkatTemuan' => $r->Tingkat_Temuan,
                'sumber' => $r->Sumber,
                'vendorRef' => $r->Vendor_Ref,
                'sensitif' => in_array($r->Jenis_Kode, $sensitif, true),
                // Temuan yang diredaksi retensi tidak lagi punya isi — tapi
                // barisnya tetap ada supaya laporan lama tidak berubah sendiri.
                'diredaksi' => ($r->Redaksi_At ?? null) ? (string) $r->Redaksi_At : null,
                'terkunci' => ! $penuh && in_array($r->Jenis_Kode, $sensitif, true) && ! empty($r->Ringkasan),
                'ringkasan' => (! $penuh && in_array($r->Jenis_Kode, $sensitif, true)) ? null : $r->Ringkasan,
                'tanggalMulai' => (string) ($r->Tanggal_Mulai ?: ''),
                'tanggalSelesai' => (string) ($r->Tanggal_Selesai ?: ''),
                'petugas' => $r->Petugas,
            ])
            ->values()
            ->all();
    }

    /** Kode komponen yang ditandai data pribadi bersifat spesifik. */
    public static function kodeSensitif(): array
    {
        if (! Skema::adaTabel('N_WEB_CAREERS_Master_Jenis_Verifikasi')) {
            return [];
        }

        return DB::table('N_WEB_CAREERS_Master_Jenis_Verifikasi')
            ->where('Flag_Sensitif', 'Y')
            ->pluck('Kode')
            ->all();
    }

    /**
     * PERSETUJUAN kandidat atas pemeriksaan ini.
     *
     * Dua sumber, dan yang pertama dicari lebih dulu:
     *
     *   FORMULIR  Bidang bertipe `consent` di formulir lamaran yang SUDAH
     *             dikirim. Nilainya dibaca dari Formulir_Pengisian berikut waktu
     *             & IP pengirimannya, dan pernyataannya sendiri ikut beku di
     *             Schema_Snapshot_Json — jadi teks yang disetujui tidak bisa
     *             berubah belakangan.
     *
     *   BERKAS    Persetujuan tertulis di luar sistem. Admin menyatakannya, dan
     *             namanya ikut tercatat.
     *
     * `eksplisit` menandai apakah pernyataannya benar-benar menyebut pemeriksaan
     * latar belakang. Persetujuan umum "penggunaan data pribadi untuk proses
     * rekrutmen" biasanya sudah cukup sebagai dasar, tetapi bedanya perlu
     * terlihat oleh orang yang memutuskan — bukan disamarkan jadi satu centang
     * hijau.
     */
    public static function persetujuanAktivitas(object $sub): ?array
    {
        if (! Skema::adaKolom('N_WEB_CAREERS_Lamaran_Tahap_Tes', 'Persetujuan_Sumber')) {
            return null;
        }

        // KEPUTUSAN YANG DICATAT PETUGAS MENANG atas apa pun yang tertulis di
        // formulir: ia dibuat SESUDAH melihat keadaan sebenarnya, dan untuk
        // pemeriksaan latar belakang persetujuannya memang lazim diminta lewat
        // telepon saat tahap ini dimulai.
        $sumber = $sub->Persetujuan_Sumber ?? null;
        $lamaranId = (int) (DB::table('N_WEB_CAREERS_Lamaran_Tahap')
            ->where('Id_Lamaran_Tahap', $sub->Lamaran_Tahap_Id)
            ->value('Lamaran_Id') ?? 0);

        // Persetujuan di formulir lamaran = KONTEKS, bukan keputusan.
        //
        // Pernyataan "izin memakai data pribadi untuk proses rekrutmen" memang
        // dasar yang sah untuk memproses lamaran, tapi ia BUKAN jawaban atas
        // pertanyaan "bersediakah Anda kami periksa latar belakangnya?" — dan
        // pertanyaan itu memang ditanyakan lewat telepon saat tahap ini dimulai.
        // Menganggap keduanya satu hal membuat pemeriksaan berjalan tanpa
        // seorang pun pernah bertanya.
        $formulir = self::persetujuanFormulir($lamaranId);
        $ditolak = $sumber === 'TOLAK';
        $adaKeputusan = in_array($sumber, self::SUMBER_PETUGAS, true);

        return [
            // `ada` berarti BOLEH DIPERIKSA — hanya dari keputusan di aktivitas ini.
            'ada' => $adaKeputusan && ! $ditolak,
            'ditolak' => $ditolak,
            'keputusan' => $adaKeputusan ? ($ditolak ? 'TOLAK' : 'SETUJU') : null,
            'sumber' => $sumber,
            'eksplisit' => $adaKeputusan,
            'pernyataan' => $adaKeputusan
                ? ($sub->Persetujuan_Ref ?: ($ditolak ? 'Kandidat menolak pemeriksaan.' : 'Persetujuan dicatat petugas.'))
                : null,
            'pada' => ($sub->Persetujuan_At ?? null) ? (string) $sub->Persetujuan_At : null,
            'oleh' => $sub->Persetujuan_By ?? null,
            // Ditampilkan sebagai keterangan pendamping, bukan sebagai izin.
            'formulir' => $formulir,
        ];
    }

    /** Kata kunci yang membuat sebuah pernyataan terhitung EKSPLISIT. */
    private const KATA_PEMERIKSAAN = ['latar belakang', 'background', 'verifikasi', 'pemeriksaan', 'referensi'];

    public static function persetujuanFormulir(int $lamaranId): ?array
    {
        if (! $lamaranId || ! Skema::adaTabel('N_WEB_CAREERS_Formulir_Pengisian')) {
            return null;
        }

        $rows = DB::table('N_WEB_CAREERS_Formulir_Pengisian')
            ->where('Lamaran_Id', $lamaranId)
            ->whereNotNull('Waktu_Kirim')
            ->orderByDesc('Waktu_Kirim')
            ->get(['Id_Formulir_Pengisian', 'Jawaban_Json', 'Schema_Snapshot_Json', 'Waktu_Kirim', 'Ip_Pengirim']);

        $terbaik = null;

        foreach ($rows as $r) {
            $jawab = json_decode((string) $r->Jawaban_Json, true) ?: [];
            $skema = json_decode((string) $r->Schema_Snapshot_Json, true) ?: [];

            foreach ($skema['langkah'] ?? [] as $langkah) {
                foreach ($langkah['bagian'] ?? [] as $bagian) {
                    foreach ($bagian['field'] ?? [] as $field) {
                        if (($field['tipe'] ?? '') !== 'consent') {
                            continue;
                        }

                        $nilai = $jawab[$field['key'] ?? ''] ?? null;
                        if (! filter_var($nilai, FILTER_VALIDATE_BOOLEAN)) {
                            continue;
                        }

                        $label = (string) ($field['label'] ?? '');
                        $kecil = mb_strtolower($label);

                        // TIGA TINGKAT, bukan ya/tidak. Formulir lamaran biasanya
                        // memuat beberapa pernyataan sekaligus — "data saya benar",
                        // "bersedia mengikuti seleksi", "izin memakai data pribadi".
                        // Yang paling dekat dengan pemeriksaan latar belakang harus
                        // menang, bukan yang kebetulan terbaca lebih dulu.
                        $skor = 0;
                        foreach (self::KATA_PEMERIKSAAN as $kata) {
                            if (str_contains($kecil, $kata)) {
                                $skor = 2;
                                break;
                            }
                        }
                        if ($skor === 0 && str_contains($kecil, 'data pribadi')) {
                            $skor = 1;
                        }
                        $eksplisit = $skor === 2;

                        $calon = [
                            'ada' => true,
                            'sumber' => 'FORMULIR',
                            'eksplisit' => $eksplisit,
                            'pernyataan' => $label,
                            'pada' => (string) $r->Waktu_Kirim,
                            'oleh' => 'Kandidat',
                            'ip' => $r->Ip_Pengirim,
                            'ref' => 'Pengisian #'.$r->Id_Formulir_Pengisian,
                        ];

                        $calon['skor'] = $skor;

                        // Yang EKSPLISIT langsung menang; sisanya bersaing lewat
                        // skor, dan yang seri dimenangkan pengisian terbaru
                        // (daftarnya sudah diurutkan menurun).
                        if ($skor === 2) {
                            return $calon;
                        }
                        if (! $terbaik || $skor > $terbaik['skor']) {
                            $terbaik = $calon;
                        }
                    }
                }
            }
        }

        return $terbaik;
    }

    /**
     * Catat pembukaan temuan sensitif.
     *
     * HANYA yang sensitif. Mencatat setiap pembukaan halaman kandidat akan
     * menghasilkan jutaan baris yang tak pernah dibaca, dan log yang terlalu
     * ramai sama tidak bergunanya dengan tidak ada log.
     */
    public static function catatAkses(object $sub, array $kode, $request = null): void
    {
        if (! $kode || ! Skema::adaTabel('N_WEB_CAREERS_Pemeriksaan_Akses_Log')) {
            return;
        }

        $lamaranId = (int) (DB::table('N_WEB_CAREERS_Lamaran_Tahap')
            ->where('Id_Lamaran_Tahap', $sub->Lamaran_Tahap_Id)
            ->value('Lamaran_Id') ?? 0);

        DB::table('N_WEB_CAREERS_Pemeriksaan_Akses_Log')->insert([
            'Lamaran_Id' => $lamaranId,
            'Lamaran_Tahap_Tes_Id' => $sub->Id_Lamaran_Tahap_Tes,
            'Jenis_Kode' => mb_substr(implode(',', $kode), 0, 200),
            'Id_Users' => session('career_auth.id'),
            'Nama' => session('career_auth.nama', 'ADMIN'),
            'Ip_Address' => $request?->ip(),
            'User_Agent' => mb_substr((string) $request?->userAgent(), 0, 400),
            'Created_At' => now(),
        ]);
    }

    public static function narasumber(int $subTesId): array
    {
        return DB::table('N_WEB_CAREERS_Referensi_Kandidat')
            ->where('Lamaran_Tahap_Tes_Id', $subTesId)
            ->orderBy('Id_Referensi_Kandidat')
            ->get()
            ->map(fn ($r) => [
                'id' => (int) $r->Id_Referensi_Kandidat,
                'nama' => $r->Nama,
                'jabatan' => $r->Jabatan,
                'perusahaan' => $r->Perusahaan,
                'hubungan' => $r->Hubungan,
                'periodeKerja' => $r->Periode_Kerja,
                'kontakTelp' => $r->Kontak_Telp,
                'kontakEmail' => $r->Kontak_Email,
                'izinHubungi' => ($r->Izin_Hubungi ?? 'T') === 'Y',
                'statusKontak' => $r->Status_Kontak,
                'percobaan' => (int) $r->Percobaan,
                'tanggalKontak' => (string) ($r->Tanggal_Kontak ?: ''),
                'metode' => $r->Metode,
                'rekomendasi' => $r->Rekomendasi,
                'ringkasan' => $r->Ringkasan,
                'petugas' => $r->Petugas,
            ])
            ->values()
            ->all();
    }

    /**
     * RIWAYAT SETIAP LANGKAH PEMERIKSAAN.
     *
     * Ditulis ke N_WEB_CAREERS_Lamaran_Keputusan_Jejak — tabel jejak yang SUDAH
     * ADA dan sudah ditampilkan Monitoring sebagai lini masa lamaran. Tidak ada
     * tabel riwayat baru: dua lini masa untuk satu lamaran berarti dua cerita
     * yang harus dibaca bergantian, dan yang satu pasti terlupa.
     *
     * Yang dicatat: keputusan persetujuan, tiap komponen yang dinilai, tiap
     * narasumber yang dihubungi. Semuanya membawa nama petugas & waktunya dari
     * kolom tabel itu sendiri.
     *
     * TIDAK BOLEH MENGGAGALKAN PEKERJAAN. Gagal menulis riwayat lebih baik
     * daripada gagal menyimpan temuan yang barusan diketik orang.
     */
    public static function tulisRiwayat(object $sub, string $verdict, string $ringkasan): void
    {
        try {
            $lamaranId = (int) (DB::table('N_WEB_CAREERS_Lamaran_Tahap')
                ->where('Id_Lamaran_Tahap', $sub->Lamaran_Tahap_Id)
                ->value('Lamaran_Id') ?? 0);

            DB::table('N_WEB_CAREERS_Lamaran_Keputusan_Jejak')->insert([
                'Lamaran_Id' => $lamaranId ?: null,
                'Lamaran_Tahap_Id' => $sub->Lamaran_Tahap_Id,
                'Mode_Kode' => 'PEMERIKSAAN',
                // Kolomnya VARCHAR(20) — dipotong di sini, bukan diserahkan ke
                // basis data yang akan menolak seluruh penulisan.
                'Verdict' => mb_substr($verdict, 0, 20),
                'Ringkasan' => mb_substr(($sub->Label ? $sub->Label.' — ' : '').$ringkasan, 0, 500),
                'Created_At' => now(),
                'Created_By' => session('career_auth.nama', 'SISTEM'),
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::channel('web_career')
                ->warning('[PEMERIKSAAN] gagal menulis riwayat: '.$e->getMessage());
        }
    }

    /**
     * Kalimat rangkuman untuk daftar Worklist — disimpan, bukan dihitung ulang
     * tiap kali ditampilkan.
     *
     * Halaman Worklist memuat ratusan baris sekaligus; menghitung "5 dari 6
     * bersih" per baris berarti dua query per kandidat hanya untuk satu kalimat.
     */
    public static function hitungRingkas(int $subTesId, ?string $tipeKode): void
    {
        if (! self::siap()) {
            return;
        }

        $ringkas = ($tipeKode === 'REFERENCE_CHECK')
            ? self::ringkasReferensi($subTesId)
            : self::ringkasLatar($subTesId);

        DB::table('N_WEB_CAREERS_Lamaran_Tahap_Tes')
            ->where('Id_Lamaran_Tahap_Tes', $subTesId)
            ->update(['Adjudikasi_Ringkas' => $ringkas]);
    }

    private static function ringkasLatar(int $subTesId): ?string
    {
        $baris = DB::table('N_WEB_CAREERS_Verifikasi_Latar')
            ->where('Lamaran_Tahap_Tes_Id', $subTesId)
            ->get(['Status']);

        if ($baris->isEmpty()) {
            return null;
        }

        $total = $baris->count();
        $bersih = $baris->where('Status', 'BERSIH')->count();
        $temuan = $baris->where('Status', 'TEMUAN')->count();
        $gantung = $baris->whereIn('Status', ['MENUNGGU', 'PROSES'])->count();
        $buntu = $baris->where('Status', 'TIDAK_TERVERIFIKASI')->count();

        $bagian = ["{$bersih} dari {$total} bersih"];
        if ($temuan) {
            $bagian[] = "{$temuan} temuan";
        }
        if ($buntu) {
            $bagian[] = "{$buntu} tak terverifikasi";
        }
        if ($gantung) {
            $bagian[] = "{$gantung} belum selesai";
        }

        return implode(', ', $bagian);
    }

    private static function ringkasReferensi(int $subTesId): ?string
    {
        $baris = DB::table('N_WEB_CAREERS_Referensi_Kandidat')
            ->where('Lamaran_Tahap_Tes_Id', $subTesId)
            ->get(['Status_Kontak', 'Rekomendasi']);

        if ($baris->isEmpty()) {
            return null;
        }

        $total = $baris->count();
        $terhubung = $baris->where('Status_Kontak', 'TERHUBUNG')->count();
        $menolak = $baris->where('Status_Kontak', 'MENOLAK')->count();
        $tidak = $baris->where('Rekomendasi', 'TIDAK')->count();

        $bagian = ["{$terhubung} dari {$total} narasumber terhubung"];
        if ($menolak) {
            $bagian[] = "{$menolak} menolak";
        }
        // Angka ini sengaja ikut naik ke daftar: satu "tidak bersedia
        // mempekerjakan kembali" lebih menentukan daripada berapa pun jumlah
        // narasumber yang berhasil dihubungi.
        if ($tidak) {
            $bagian[] = "{$tidak} tidak merekomendasikan";
        }

        return implode(', ', $bagian);
    }
}
