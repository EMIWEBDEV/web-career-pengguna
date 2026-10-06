<?php

namespace App\Support\Career;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Vinkla\Hashids\Facades\Hashids;

/**
 * WEB CAREER — kosakata & mesin PHONE SCREENING.
 *
 * Satu tempat untuk hal-hal yang kalau tersebar pasti berselisih:
 *
 *   1. daftar nilai yang sah (tipe pertanyaan, metode, rekomendasi, status);
 *   2. rantai resolusi "loker ini pakai template mana";
 *   3. pembekuan template ke sesi kandidat;
 *   4. perhitungan skor & knockout.
 *
 * Seluruhnya berpenjaga `siap()` — selama skrip skemanya belum dijalankan,
 * modul ini diam dan halaman berjalan persis seperti sebelum fitur ini ada.
 * Pola yang sama dipakai `Pemeriksaan`, dan alasannya sama: fitur baru tidak
 * boleh membuat lingkungan yang belum dimigrasi berhenti bekerja.
 *
 * ── KENAPA "SKRINING", BUKAN "PHONE_SCREENING" ──────────────────────────────
 *
 * Nama tabelnya jadi jauh lebih pendek, dan instrumen ini pada akhirnya akan
 * dipakai untuk skrining yang bukan lewat telepon — user screening, walk-in.
 * Kolom `Metode` yang mencatat "lewat apa"-nya, sepola `Referensi_Kandidat`.
 */
class Skrining
{
    // ══════════════════════ TABEL ══════════════════════

    public const T_MASTER = 'N_WEB_CAREERS_Master_Skrining';

    public const T_VERSI = 'N_WEB_CAREERS_Master_Skrining_Versi';

    public const T_TANYA = 'N_WEB_CAREERS_Master_Skrining_Pertanyaan';

    public const T_IKAT = 'N_WEB_CAREERS_Program_Skrining';

    public const T_SESI = 'N_WEB_CAREERS_Lamaran_Skrining';

    public const T_JAWAB = 'N_WEB_CAREERS_Lamaran_Skrining_Jawaban';

    // ══════════════════════ KOSAKATA ══════════════════════

    /**
     * Tipe pertanyaan yang sah.
     *
     * Enam yang pertama DIPINJAM dari Master Feedback supaya penyunting
     * pertanyaan terasa sama di dua tempat. Sisanya dibutuhkan skrining tapi
     * tidak dibutuhkan feedback: skrining menanyakan gaji harapan (CURRENCY),
     * tanggal siap masuk (DATE), dan lama pengalaman (NUMBER) — tiga hal yang
     * tidak pernah ditanyakan ke kandidat yang sudah gugur.
     *
     * Daftar ini KEMBAR dengan CHECK constraint CK_NWC_SkrTanya_Tipe di
     * database. Menambah tipe di sini tanpa mengubah constraint-nya akan
     * ditolak basis data saat menyimpan — dan itu memang yang diinginkan:
     * lebih baik gagal keras di penyunting daripada diam-diam kehilangan data.
     */
    public const TIPE = [
        'RADIO', 'CHECKBOX', 'SELECT', 'RATING', 'LIKERT', 'NPS', 'BOOLEAN',
        'TEXT', 'TEXTAREA', 'EDITOR', 'NUMBER', 'CURRENCY', 'DATE',
    ];

    /** Tipe yang MENUNTUT daftar opsi. Tanpa opsi, pertanyaannya tak terjawab. */
    public const TIPE_BEROPSI = ['RADIO', 'CHECKBOX', 'SELECT'];

    /** Tipe berskala angka — Skala_Min/Max wajib, opsi tidak dipakai. */
    public const TIPE_BERSKALA = ['RATING', 'LIKERT', 'NPS'];

    /**
     * Tipe yang bisa menyumbang SKOR.
     *
     * Teks bebas sengaja tidak masuk: memberi angka pada jawaban esai berarti
     * mesin menebak maksud kalimat, dan tebakan itu akan terbaca sebagai hasil
     * ukur. Penilaian esai tetap urusan rekomendasi petugas.
     */
    public const TIPE_BERSKOR = ['RADIO', 'SELECT', 'BOOLEAN', 'RATING', 'LIKERT', 'NPS', 'NUMBER'];

    public const STATUS_SESI = ['DRAF', 'SELESAI', 'BATAL'];

    public const STATUS_VERSI = ['DRAFT', 'PUBLISHED', 'ARCHIVED'];

    public const METODE = ['TELEPON', 'VIDEO', 'TATAP_MUKA'];

    public const HASIL_KONTAK = ['TERHUBUNG', 'TIDAK_TERHUBUNG', 'DIJADWAL_ULANG', 'MENOLAK'];

    /**
     * Rekomendasi petugas — BUKAN keputusan tahap.
     *
     * Semuanya muat di 20 karakter, dan itu lebar kolomnya. Menambah nilai
     * baru di sini: hitung dulu panjangnya.
     */
    public const REKOMENDASI = ['LANJUT', 'PERTIMBANGAN', 'TIDAK_LANJUT'];

    private static ?bool $siap = null;

    // ══════════════════════ PENJAGA ══════════════════════

    /** Skema skrining sudah dijalankan? */
    public static function siap(): bool
    {
        return self::$siap ??= Skema::adaTabel(self::T_MASTER)
            && Skema::adaTabel(self::T_SESI)
            && Skema::adaKolom('N_WEB_CAREERS_Lamaran_Tahap_Tes', 'Skrining_Kode');
    }

    /**
     * Aktivitas bertipe ini punya kuesioner skrining? — MASTERNYA yang menjawab.
     *
     * Daftar kode di bawah cuma cadangan untuk lingkungan yang kolomnya belum
     * ada. Begitu Flag_Skrining hadir, tipe skrining baru (user screening,
     * walk-in screening) cukup dicentang di Master Tipe Tahap tanpa menyentuh
     * satu baris pun kode di sini.
     */
    public static function untuk(?string $tipeKode): bool
    {
        return $tipeKode ? isset(self::petaSkrining()[$tipeKode]) : false;
    }

    /**
     * PETA TIPE SKRINING — DIBACA SEKALI PER PERMINTAAN.
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
    private static ?array $petaSkrining = null;

    /** @return array<string,bool> kode tipe yang bertanda, sebagai himpunan */
    private static function petaSkrining(): array
    {
        if (self::$petaSkrining !== null) {
            return self::$petaSkrining;
        }

        // Lingkungan yang kolom penandanya belum ada tetap dilayani daftar
        // cadangan di bawah — sama seperti sebelumnya, hanya tidak lagi
        // ditanyakan berulang kali. Dibaca dari master tipe yang SUDAH dimuat
        // (AlurKolom::masterTipe, sekali per permintaan), bukan kueri sendiri.
        $kode = Skema::adaKolom('N_WEB_CAREERS_Master_Tipe_Tahap', 'Flag_Skrining')
            ? AlurKolom::masterTipe()
                ->filter(fn ($t) => strtoupper(trim((string) ($t->Flag_Skrining ?? ''))) === 'Y')
                ->keys()->map(fn ($k) => (string) $k)->all()
            : ['PHONE_SCREEN'];

        return self::$petaSkrining = array_fill_keys($kode, true);
    }

    // ══════════════════════ RESOLUSI TEMPLATE ══════════════════════

    /**
     * Template mana yang berlaku untuk satu loker di satu aktivitas alur.
     *
     * Dibaca berurutan, berhenti di kecocokan pertama:
     *
     *   1. khusus loker ini      Program_Skrining, Program_Posisi_Id terisi
     *   2. bawaan program        Program_Skrining, Program_Posisi_Id NULL
     *   3. bawaan alur           Master_Alur_Tahap_Tes.Skrining_Kode
     *   —  tidak ada             null → aktivitas jalan tanpa kuesioner
     *
     * Urutan inilah yang membuat program berisi 50 loker tidak perlu diatur
     * 50 kali: atur sekali di tingkat program, lalu timpa hanya loker yang
     * memang berbeda.
     *
     * Tidak ada kecocokan BUKAN error. Fitur baru tidak boleh mematahkan
     * program yang sudah berjalan — aktivitasnya sekadar berperilaku seperti
     * tahap manual biasa hari ini.
     *
     * @return array{kode: string, versi: int, versiId: int, nama: string}|null
     */
    public static function resolusi(?int $programId, ?int $posisiId, ?int $alurTesId): ?array
    {
        if (! self::siap() || ! $alurTesId) {
            return null;
        }

        $ikat = null;

        if ($posisiId) {
            $ikat = DB::table(self::T_IKAT)
                ->where('Program_Posisi_Id', $posisiId)
                ->where('Master_Alur_Tahap_Tes_Id', $alurTesId)
                ->where('Flag_Aktif', 'Y')
                ->first();
        }

        if (! $ikat && $programId) {
            $ikat = DB::table(self::T_IKAT)
                ->where('Program_Id', $programId)
                ->whereNull('Program_Posisi_Id')
                ->where('Master_Alur_Tahap_Tes_Id', $alurTesId)
                ->where('Flag_Aktif', 'Y')
                ->first();
        }

        $kode = $ikat->Skrining_Kode ?? null;
        $versiDiminta = $ikat->Skrining_Versi ?? null;

        // Lapis 3 — bawaan alur. Berpenjaga hasColumn karena kolomnya baru,
        // dan modul ini harus tetap jalan di lingkungan yang belum dimigrasi.
        if (! $kode && Skema::adaKolom('N_WEB_CAREERS_Master_Alur_Tahap_Tes', 'Skrining_Kode')) {
            $kode = DB::table('N_WEB_CAREERS_Master_Alur_Tahap_Tes')
                ->where('Id_Master_Alur_Tahap_Tes', $alurTesId)
                ->value('Skrining_Kode');
        }

        if (! $kode) {
            return null;
        }

        return self::versiTerpakai($kode, $versiDiminta !== null ? (int) $versiDiminta : null);
    }

    /**
     * Versi mana yang benar-benar dipakai untuk sebuah kode template.
     *
     * `$versi` null berarti "ikut yang PUBLISHED". Kalau versi yang dipaku
     * ternyata tidak ada lagi, sistem TIDAK diam-diam turun ke versi lain —
     * ia mengembalikan null, dan aktivitasnya berjalan tanpa kuesioner.
     * Menjalankan kuesioner yang berbeda dari yang diperintahkan lebih buruk
     * daripada tidak menjalankan apa pun.
     *
     * @return array{kode: string, versi: int, versiId: int, nama: string}|null
     */
    public static function versiTerpakai(string $kode, ?int $versi = null): ?array
    {
        $master = DB::table(self::T_MASTER)->where('Kode', $kode)->first();
        if (! $master) {
            return null;
        }

        $q = DB::table(self::T_VERSI)->where('Master_Skrining_Id', $master->Id_Master_Skrining);

        $baris = $versi !== null
            ? $q->where('Versi', $versi)->first()
            : $q->where('Status', 'PUBLISHED')->first();

        if (! $baris) {
            return null;
        }

        return [
            'kode' => $master->Kode,
            'nama' => $master->Nama,
            'versi' => (int) $baris->Versi,
            'versiId' => (int) $baris->Id_Master_Skrining_Versi,
        ];
    }

    // ══════════════════════ PERTANYAAN ══════════════════════

    /** Pertanyaan satu versi, siap dikirim ke layar. */
    public static function pertanyaan(int $versiId, bool $untukPenyusun = false): array
    {
        return DB::table(self::T_TANYA)
            ->where('Master_Skrining_Versi_Id', $versiId)
            ->orderBy('Urutan')
            ->get()
            ->map(fn ($r) => self::bentukTanya($r, $untukPenyusun))
            ->values()
            ->all();
    }

    /** Satu baris pertanyaan → bentuk yang dibaca layar. */
    /**
     * Satu baris pertanyaan → bentuk yang dibaca layar.
     *
     * `$untukPenyusun` menentukan apakah Bank_Id ikut. Penyusun template
     * memang membutuhkannya — ia dikirim balik saat menyimpan supaya tombol
     * "Segarkan dari pustaka" tahu baris mana yang berasal dari mana.
     *
     * Panel skrining kandidat tidak. Di sana ia cuma nomor mentah yang
     * memberi tahu berapa banyak pertanyaan yang pernah ada di pustaka —
     * kepada orang yang sedang menelepon kandidat, yang tidak punya urusan
     * apa pun dengan itu.
     */
    public static function bentukTanya(object $r, bool $untukPenyusun = false): array
    {
        return [
            'seksi' => $r->Seksi,
            'urutan' => (int) $r->Urutan,
            'kode' => $r->Kode,
            'tipe' => $r->Tipe,
            'label' => $r->Label,
            'bantuan' => $r->Bantuan,
            'opsi' => self::opsiArray($r->Opsi ?? null),
            'skalaMin' => $r->Skala_Min !== null ? (int) $r->Skala_Min : null,
            'skalaMax' => $r->Skala_Max !== null ? (int) $r->Skala_Max : null,
            'labelMin' => $r->Label_Min,
            'labelMax' => $r->Label_Max,
            'wajib' => ($r->Flag_Wajib ?? 'T') === 'Y',
            'bobot' => $r->Bobot !== null ? (float) $r->Bobot : null,
            'knockout' => ($r->Flag_Knockout ?? 'T') === 'Y',
            'knockoutOperator' => $r->Knockout_Operator,
            'knockoutNilai' => $r->Knockout_Nilai,
            'knockoutPesan' => $r->Knockout_Pesan,
            'catatan' => ($r->Flag_Catatan ?? 'T') === 'Y',
            'tampilJika' => self::jsonArray($r->Tampil_Jika ?? null),
            // Asal-usul dari Bank Pertanyaan. Kolomnya baru, jadi dibaca
            // dengan `??` — lingkungan yang belum menjalankan skrip banknya
            // tetap membaca pertanyaan template seperti biasa.
            'bankId' => $untukPenyusun && isset($r->Bank_Id) && $r->Bank_Id !== null ? (int) $r->Bank_Id : null,
            'bankKode' => $r->Bank_Kode ?? null,
            'ubahan' => ($r->Flag_Ubahan ?? 'T') === 'Y',
        ];
    }

    /**
     * Normalisasi daftar opsi.
     *
     * Menerima dua bentuk karena Master Feedback menyimpan yang pertama dan
     * skrining butuh yang kedua:
     *
     *   ["Ya", "Tidak"]                                → tanpa skor
     *   [{"nilai":"YA","label":"Ya","skor":10}, ...]   → dengan skor
     *
     * Keluarannya selalu bentuk kedua, supaya layar dan mesin skor tidak
     * perlu tahu mana yang tersimpan.
     */
    public static function opsiArray(?string $json): array
    {
        $data = self::jsonArray($json);
        if (! $data) {
            return [];
        }

        $out = [];

        foreach ($data as $o) {
            if (is_string($o) || is_numeric($o)) {
                $out[] = ['nilai' => (string) $o, 'label' => (string) $o, 'skor' => null];

                continue;
            }

            if (! is_array($o)) {
                continue;
            }

            $nilai = (string) ($o['nilai'] ?? $o['label'] ?? '');
            if ($nilai === '') {
                continue;
            }

            $out[] = [
                'nilai' => $nilai,
                'label' => (string) ($o['label'] ?? $nilai),
                'skor' => isset($o['skor']) && $o['skor'] !== '' && $o['skor'] !== null
                    ? (float) $o['skor']
                    : null,
            ];
        }

        return $out;
    }

    /** JSON → larik, tanpa melempar untuk isi yang rusak. */
    public static function jsonArray(?string $json): array
    {
        if (! $json) {
            return [];
        }

        $data = json_decode($json, true);

        return is_array($data) ? $data : [];
    }

    // ══════════════════════ PENILAIAN ══════════════════════

    /**
     * Hitung skor sebuah sesi dari jawaban yang tersimpan.
     *
     * Aturannya sederhana dan sengaja begitu:
     *
     *   nilai_pertanyaan = skor_jawaban × bobot
     *   maksimum         = skor_tertinggi_yang_mungkin × bobot
     *
     * Pertanyaan tanpa bobot TIDAK IKUT sama sekali — tidak di pembilang,
     * tidak di penyebut. Itu bedanya dengan bobot nol: nol berarti "ikut
     * hitungan tapi tak bernilai", dan itu menggeser rata-rata ke bawah.
     *
     * Pertanyaan yang belum dijawab juga tidak ikut penyebut. Sesi yang baru
     * setengah jalan karena itu menunjukkan persentase dari yang SUDAH
     * ditanyakan — bukan angka rendah palsu yang membuat kandidat terlihat
     * buruk hanya karena wawancaranya belum selesai.
     *
     * @param  array<int,object>  $jawaban  baris T_JAWAB
     * @return array{skor: ?float, maks: ?float, persen: ?float}
     */
    public static function hitungSkor(array $jawaban): array
    {
        $skor = 0.0;
        $maks = 0.0;
        $ada = false;

        foreach ($jawaban as $j) {
            $bobot = $j->Bobot_Snapshot !== null ? (float) $j->Bobot_Snapshot : null;
            if ($bobot === null) {
                continue;
            }

            $nilai = $j->Nilai !== null ? (float) $j->Nilai : null;
            if ($nilai === null) {
                continue;
            }

            $puncak = self::puncakNilai($j);
            if ($puncak === null || $puncak <= 0) {
                continue;
            }

            $ada = true;
            $skor += $nilai * $bobot;
            $maks += $puncak * $bobot;
        }

        if (! $ada || $maks <= 0) {
            return ['skor' => null, 'maks' => null, 'persen' => null];
        }

        return [
            'skor' => round($skor, 2),
            'maks' => round($maks, 2),
            'persen' => round($skor / $maks * 100, 2),
        ];
    }

    /**
     * Nilai tertinggi yang MUNGKIN untuk satu pertanyaan.
     *
     * Untuk pertanyaan beropsi: skor opsi tertinggi. Untuk berskala:
     * Skala_Max. Keduanya dibaca dari SALINAN di baris jawaban, bukan dari
     * master — supaya mengubah opsi di master besok tidak menulis ulang arti
     * skor yang sudah tercatat hari ini.
     */
    private static function puncakNilai(object $j): ?float
    {
        $opsi = self::opsiArray($j->Opsi_Snapshot ?? null);

        if ($opsi) {
            $skor = array_filter(
                array_column($opsi, 'skor'),
                fn ($s) => $s !== null
            );

            return $skor ? (float) max($skor) : null;
        }

        return $j->Skala_Max_Snapshot !== null ? (float) $j->Skala_Max_Snapshot : null;
    }

    /**
     * Nilai satu jawaban terhadap opsinya.
     *
     * Untuk CHECKBOX: menjumlahkan skor seluruh opsi yang dipilih. Untuk
     * sisanya: skor opsi yang cocok, atau angkanya sendiri bila berskala.
     */
    public static function nilaiJawaban(string $tipe, $jawaban, array $opsi): ?float
    {
        if ($jawaban === null || $jawaban === '') {
            return null;
        }

        if (! in_array($tipe, self::TIPE_BERSKOR, true)) {
            return null;
        }

        if (in_array($tipe, self::TIPE_BERSKALA, true) || $tipe === 'NUMBER') {
            return is_numeric($jawaban) ? (float) $jawaban : null;
        }

        $peta = [];
        foreach ($opsi as $o) {
            $peta[$o['nilai']] = $o['skor'];
        }

        $dipilih = is_array($jawaban) ? $jawaban : [$jawaban];
        $total = null;

        foreach ($dipilih as $d) {
            $s = $peta[(string) $d] ?? null;
            if ($s !== null) {
                $total = ($total ?? 0) + $s;
            }
        }

        return $total;
    }

    // ══════════════════════ KNOCKOUT ══════════════════════

    /**
     * Jawaban mana yang menggugurkan? — MesinSyarat yang menilainya.
     *
     * Tidak ada mesin perbandingan kedua di sini: operator, penanganan
     * ANTARA/ADA_DI, dan cara membandingkan angka-vs-teks sudah dipecahkan
     * sekali di MesinSyarat, dan dua mesin yang mirip pasti berselisih.
     *
     * Hasilnya REKOMENDASI, bukan keputusan — sepola MesinSyarat sendiri yang
     * mengembalikan `lolos` + `jejak` lalu menyerahkan palunya ke pemanggil.
     * Jawaban knockout menandai Knockout_Flag; menggugurkan kandidat tetap
     * lewat mesin keputusan tahap.
     *
     * @param  array<int,object>  $jawaban  baris T_JAWAB
     * @return array{kena: bool, kode: ?string, pesan: ?string}
     */
    public static function periksaKnockout(array $jawaban, int $versiId): array
    {
        $aturan = DB::table(self::T_TANYA)
            ->where('Master_Skrining_Versi_Id', $versiId)
            ->where('Flag_Knockout', 'Y')
            ->whereNotNull('Knockout_Operator')
            ->get()
            ->keyBy('Kode');

        if ($aturan->isEmpty()) {
            return ['kena' => false, 'kode' => null, 'pesan' => null];
        }

        foreach ($jawaban as $j) {
            $a = $aturan[$j->Kode_Snapshot] ?? null;
            if (! $a || $j->Jawaban === null || $j->Jawaban === '') {
                continue;
            }

            $nilai = self::jsonArray($j->Jawaban) ?: $j->Jawaban;

            // Knockout berbunyi "gugur BILA cocok", sedangkan MesinSyarat
            // menjawab "lolos". Karena itu hasilnya dibalik di sini — bukan
            // dengan menulis operator kebalikan di master, yang akan membuat
            // orang menyusun syarat dengan logika terbalik di kepalanya.
            $hasil = MesinSyarat::nilai([
                'penghubung' => 'DAN',
                'aturan' => [[
                    'field' => $j->Kode_Snapshot,
                    'operator' => $a->Knockout_Operator,
                    'nilai' => $a->Knockout_Nilai,
                ]],
            ], [$j->Kode_Snapshot => $nilai]);

            if ($hasil['lolos']) {
                return [
                    'kena' => true,
                    'kode' => $j->Kode_Snapshot,
                    'pesan' => $a->Knockout_Pesan ?: 'Jawaban "'.$j->Label_Snapshot.'" tidak memenuhi syarat.',
                ];
            }
        }

        return ['kena' => false, 'kode' => null, 'pesan' => null];
    }

    // ══════════════════════ PEMBEKUAN ══════════════════════

    /**
     * Bekukan template ke satu baris Lamaran_Tahap_Tes.
     *
     * Dipanggil SEKALI, saat aktivitas kandidat dibuat. Sesudah ini
     * Program_Skrining tidak pernah dibaca lagi untuk kandidat tersebut —
     * mengubah pengikatan besok tidak akan menggeser seorang pun yang sudah
     * berjalan.
     *
     * Hasilnya DISEBAR ke larik insert pemanggil, jadi bentuknya penting:
     * larik KOSONG bila skemanya belum ada — bukan dua kunci bernilai null.
     * Menyebar kunci yang kolomnya belum dibuat akan mematahkan seluruh
     * pendaftaran di lingkungan yang belum dimigrasi, dan itu persis yang
     * penjaga `siap()` ada untuk cegah.
     *
     * @return array{Skrining_Kode?: ?string, Skrining_Versi?: ?int}
     */
    public static function bekukan(?int $programId, ?int $posisiId, ?int $alurTesId, ?string $tipeKode): array
    {
        if (! self::siap()) {
            return [];
        }

        $kosong = ['Skrining_Kode' => null, 'Skrining_Versi' => null];

        if (! self::untuk($tipeKode)) {
            return $kosong;
        }

        $pakai = self::resolusi($programId, $posisiId, $alurTesId);

        return $pakai
            ? ['Skrining_Kode' => $pakai['kode'], 'Skrining_Versi' => $pakai['versi']]
            : $kosong;
    }

    // ══════════════════════ RINGKASAN UNTUK LAYAR ══════════════════════

    /**
     * Isi skrining satu aktivitas — null bila aktivitasnya bukan skrining.
     *
     * Layar cukup memeriksa satu kunci untuk tahu perlu menggambar panel atau
     * tidak, sepola `Pemeriksaan::bentuk()`.
     */
    /**
     * Apa yang MASIH DITUNGGU dari kuesioner skrining aktivitas ini — null bila
     * sudah beres, atau bila aktivitas ini memang bukan skrining.
     *
     * ── KENAPA DI SINI, BUKAN DI LAYAR ──────────────────────────────────────
     *
     * Aturan yang sama dipakai dua pintu yang berbeda: rapor aktivitas (yang
     * menyalakan / mematikan tombol) dan pintu penyimpanan hasil (yang menolak
     * permintaan). Kalau keduanya menghitung sendiri-sendiri, cepat atau lambat
     * tombolnya mati padahal server mengizinkan — atau sebaliknya, dan yang
     * kedua itu celah: rekruter bisa menutup aktivitas tanpa pernah menelepon.
     *
     * ── YANG TIDAK DIJAGA DI SINI ───────────────────────────────────────────
     *
     * Aktivitas yang TIDAK terikat template dibiarkan lewat. Loker yang belum
     * dipasangi kuesioner harus tetap bisa jalan seperti tahap manual biasa —
     * menutup jalannya berarti satu pengikatan yang lupa diisi di Program
     * Kegiatan menghentikan seluruh rekrutmennya, dan sebabnya berada di layar
     * yang sama sekali berbeda dari tempat orang menemukan macetnya.
     */
    public static function belumTuntas(object $sub): ?string
    {
        if (! self::siap() || ! self::untuk($sub->Tipe_Tahap_Kode ?? null)) {
            return null;
        }

        // Tidak terikat kuesioner → tidak ada yang bisa dituntut.
        if (empty($sub->Skrining_Kode) || empty($sub->Id_Lamaran_Tahap_Tes)) {
            return null;
        }

        $sesi = DB::table(self::T_SESI)
            ->where('Lamaran_Tahap_Tes_Id', $sub->Id_Lamaran_Tahap_Tes)
            ->whereNull('Arsip_At')
            ->first();

        if (! $sesi) {
            return 'kuesioner skrining belum diisi';
        }

        if (($sesi->Status ?? '') !== 'SELESAI') {
            // Sesi yang sudah dibuka tapi belum ditutup adalah keadaan yang
            // paling sering terjadi: telepon terputus, kandidat minta
            // dijadwalkan ulang. Kalimatnya menyebut TINDAKAN yang kurang,
            // bukan sekadar keadaannya.
            return 'kuesioner skrining belum diselesaikan';
        }

        return null;
    }

    public static function bentuk(object $sub, bool $penuh = false): ?array
    {
        if (! self::siap() || ! self::untuk($sub->Tipe_Tahap_Kode ?? null)) {
            return null;
        }

        $kode = $sub->Skrining_Kode ?? null;
        $versi = $sub->Skrining_Versi ?? null;

        $sesi = DB::table(self::T_SESI)
            ->where('Lamaran_Tahap_Tes_Id', $sub->Id_Lamaran_Tahap_Tes)
            ->whereNull('Arsip_At')
            ->first();

        $versiId = null;
        if ($kode && $versi !== null) {
            $pakai = self::versiTerpakai($kode, (int) $versi);
            $versiId = $pakai['versiId'] ?? null;
        }

        return [
            'kode' => $kode,
            'versi' => $versi !== null ? (int) $versi : null,
            'nama' => $sesi->Nama_Snapshot ?? null,
            // Kuesioner belum terikat sama sekali — layar memakainya untuk
            // memberi tahu bahwa aktivitas ini akan berjalan seperti tahap
            // manual biasa, bukan untuk memunculkan pesan galat.
            'terikat' => (bool) $kode,
            'sesi' => $sesi ? self::bentukSesi($sesi) : null,
            'pertanyaan' => $penuh && $versiId ? self::pertanyaan($versiId) : [],
            'jawaban' => $penuh && $sesi ? self::jawabanSesi((int) $sesi->Id_Lamaran_Skrining) : [],
            'pilihanMetode' => self::METODE,
            'pilihanKontak' => self::HASIL_KONTAK,
            'pilihanRekomendasi' => self::REKOMENDASI,
        ];
    }

    /** Satu baris sesi → bentuk yang dibaca layar. */
    public static function bentukSesi(object $s): array
    {
        return [
            // ── ID SESI TIDAK PERNAH KELUAR TELANJANG ────────────────────
            //
            // Id berurut yang tampil di URL memberi tahu dua hal yang tidak
            // pantas diketahui siapa pun dari luar: berapa banyak sesi skrining
            // yang pernah ada, dan bahwa /skrining/5 hampir pasti juga ada.
            // Itu undangan menghitung dan menebak.
            //
            // Sepola seluruh modul lain di aplikasi ini — lamaran, tahap,
            // sub-tes, program — yang sudah lama memakai hash.
            'id' => Hashids::encode($s->Id_Lamaran_Skrining),
            // Beberapa tempat di layar memakainya sebagai kunci daftar, bukan
            // sebagai alamat. Dikirim terpisah supaya tak ada yang tergoda
            // menguraikan hash-nya sendiri untuk keperluan itu.
            'kunci' => 'ss'.$s->Id_Lamaran_Skrining,
            'kode' => $s->Skrining_Kode,
            'versi' => (int) $s->Skrining_Versi,
            'nama' => $s->Nama_Snapshot,
            'status' => $s->Status,
            'metode' => $s->Metode,
            'kontakNomor' => $s->Kontak_Nomor,
            'percobaan' => (int) $s->Percobaan,
            'hasilKontak' => $s->Hasil_Kontak,
            'waktuMulai' => $s->Waktu_Mulai ? (string) $s->Waktu_Mulai : null,
            'waktuSelesai' => $s->Waktu_Selesai ? (string) $s->Waktu_Selesai : null,
            'durasiMenit' => $s->Durasi_Menit !== null ? (int) $s->Durasi_Menit : null,
            'skor' => $s->Skor !== null ? (float) $s->Skor : null,
            'skorMaks' => $s->Skor_Maks !== null ? (float) $s->Skor_Maks : null,
            'skorPersen' => $s->Skor_Persen !== null ? (float) $s->Skor_Persen : null,
            'rekomendasi' => $s->Rekomendasi,
            'knockout' => ($s->Knockout_Flag ?? 'T') === 'Y',
            'knockoutKode' => $s->Knockout_Kode,
            'knockoutPesan' => $s->Knockout_Pesan,
            'ringkasanHtml' => $s->Ringkasan_Html,
            // Namanya cukup. Id_Users mentah tidak pernah dibaca layar mana
            // pun, dan mengirimnya berarti setiap rekruter yang membuka satu
            // sesi ikut mengetahui nomor akun rekan-rekannya.
            'petugas' => $s->Petugas,
            'dikunci' => (bool) $s->Dikunci_At,
            'dibuatPada' => $s->Created_At ? (string) $s->Created_At : null,
            'diperbaruiPada' => $s->Updated_At ? (string) $s->Updated_At : null,
            'diperbaruiOleh' => $s->Updated_By ?: $s->Created_By,
        ];
    }

    /** Jawaban satu sesi, urut. */
    public static function jawabanSesi(int $sesiId): array
    {
        return DB::table(self::T_JAWAB)
            ->where('Lamaran_Skrining_Id', $sesiId)
            ->orderBy('Urutan')
            ->get()
            ->map(fn ($r) => [
                'kode' => $r->Kode_Snapshot,
                'label' => $r->Label_Snapshot,
                'tipe' => $r->Tipe_Snapshot,
                'seksi' => $r->Seksi_Snapshot,
                'urutan' => (int) $r->Urutan,
                'opsi' => self::opsiArray($r->Opsi_Snapshot ?? null),
                'skalaMin' => $r->Skala_Min_Snapshot !== null ? (int) $r->Skala_Min_Snapshot : null,
                'skalaMax' => $r->Skala_Max_Snapshot !== null ? (int) $r->Skala_Max_Snapshot : null,
                'bobot' => $r->Bobot_Snapshot !== null ? (float) $r->Bobot_Snapshot : null,
                // CHECKBOX menyimpan larik; sisanya nilai tunggal. Dikembalikan
                // apa adanya supaya layar tidak perlu menebak bentuknya.
                'jawaban' => in_array($r->Tipe_Snapshot, ['CHECKBOX'], true)
                    ? self::jsonArray($r->Jawaban ?? null)
                    : $r->Jawaban,
                'jawabanTeks' => $r->Jawaban_Teks,
                'nilai' => $r->Nilai !== null ? (float) $r->Nilai : null,
                'catatan' => $r->Catatan,
            ])
            ->values()
            ->all();
    }
}
