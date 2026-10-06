<?php

namespace App\Support\Career;

use App\Http\Controllers\Career\Lamaran\LamaranController;
use App\Jobs\Career\WcKonfirmasiEmailJob;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Vinkla\Hashids\Facades\Hashids;

/**
 * WEB CAREERS — KONFIRMASI KEHADIRAN & PERMINTAAN JADWAL LAIN.
 *
 * Satu-satunya pintu yang mengubah jawaban kandidat atas undangannya. Layar
 * kandidat, worklist, dan Agenda Seleksi semuanya lewat sini, supaya aturannya
 * mustahil berbeda antar-layar.
 *
 * ══ LIMA LAPIS YANG TIDAK BOLEH TERCAMPUR ══
 *
 *   jadwal      Lamaran_Tahap_Tes.Jadwal_* + Jadwal_Versi (janji)
 *   konfirmasi  Lamaran_Tahap_Tes.Konfirmasi_Status (jawaban atas versi itu)
 *   permintaan  Lamaran_Tahap_Tes_Jadwal_Permintaan (pekerjaan tim)
 *   surel       baris jejak, kolom Undangan (ANTRE / TERKIRIM / GAGAL / LEWAT)
 *   kehadiran   Jadwal_Hadir — FAKTA hari H, dicatat tim; tidak disentuh di sini
 *
 * "Akan hadir" adalah rencana, Jadwal_Hadir adalah fakta. Menyamakan keduanya
 * menghapus angka yang paling ingin diketahui: siapa yang bilang datang tetapi
 * tidak datang.
 *
 * ══ VERSI ══
 *
 * Setiap jadwal disimpan, versinya naik. Jawaban dan tautan menempel pada
 * versi: tautan dari email lama tidak bisa mengonfirmasi jam yang sudah
 * diganti. Versi TIDAK PERNAH direset — termasuk saat tahap diulang. Pelajaran
 * IDENTITY peserta CAT yang direset (Juli 2026): nomor yang dipakai sebagai
 * kunci di luar barisnya tidak boleh mundur.
 *
 * ══ BATAS KONFIRMASI = WAKTU MULAI JADWAL ══
 *
 * Keputusan user (1 Okt 2026): tidak ada isian batas terpisah. Kandidat bisa
 * menjawab sampai jadwalnya dimulai — jadwal 01 Okt 09.00–10.00 berarti
 * batasnya 01 Okt 09.00, berapa pun tanggal selesainya. Lewat dari itu tombol
 * kandidat hilang dan tim mencatat kehadiran seperti biasa. Menggeser batas
 * berarti menggeser jadwalnya (Ubah Jadwal), bukan "perpanjang batas".
 * Batas_Konfirmasi di snapshot CRM selalu salinan Jadwal_Mulai versi itu.
 *
 * ══ KOMITMEN JAWABAN ══
 *
 * Keputusan user (1 Okt 2026 sore): kandidat tidak boleh bolak-balik. Jawaban
 * PERTAMA bebas sampai jadwal mulai; MENGUBAH jawaban hanya
 * Master_Tipe_Tahap.Ubah_Jawaban_Maks kali (bawaan 1) dan paling lambat
 * Ubah_Jawaban_Batas_Jam sebelum jadwal mulai (bawaan 72 = H-3). Sesudahnya
 * terkunci — perubahan lewat tim (Catat jawaban). "Tidak melanjutkan" TIDAK
 * ikut terkunci: kehadiran tidak bisa dipaksa, dan tim lebih baik tahu lebih
 * awal. Jatahnya dihitung per WAKTU jadwal (suntingan kecil tim tidak
 * memulihkannya), dari baris jejak — tanpa kolom baru. Lihat aturanUbah().
 *
 * ══ PENUNDAAN ══
 *
 * Tunda = jadwal dikosongkan, Konfirmasi_Status = DITUNDA sampai jadwal
 * pengganti terbit. Alasan (master, jenis TUNDA), perkiraan tanggal pengganti
 * (boleh kosong = "akan dikabarkan"), dan pesan untuk kandidat disimpan di
 * baris jejak TUNDA; "Perbarui info" menambah baris TUNDA_INFO dan boleh
 * mengabari kandidat lagi. Portal, halaman tautan, dan panel tim membaca
 * baris terakhirnya (infoTunda). Undangan berikutnya berbunyi "jadwal
 * pengganti" (penggantiTunda).
 *
 * ══ SNAPSHOT CRM ══
 *
 * N_WEB_CAREERS_CRM_Konfirmasi_Email menyimpan satu baris per undangan: batas,
 * status, dan HITUNGAN email (pengingat, kirim ulang admin, gagal). Daftar
 * Perlu Tindakan dan laporan per hari cukup membaca tabel sempit itu — tanpa
 * join ke tabel lamaran yang sibuk dipakai worklist.
 *
 * ══ OUTBOX ══
 *
 * Setiap surel lahir sebagai baris jejak berstatus ANTRE di transaksi yang
 * sama dengan perubahan statusnya, lalu dikirim WcKonfirmasiEmailJob sesudah
 * commit. Kunci idempotensinya "wc-jejak-{id}": percobaan ulang tidak pernah
 * menggandakan surel, sementara kiriman yang disengaja (baris baru) tetap
 * berangkat.
 */
final class KonfirmasiJadwal
{
    // ── status konfirmasi (Lamaran_Tahap_Tes.Konfirmasi_Status) ──────────────
    public const MENUNGGU = 'MENUNGGU';

    public const AKAN_HADIR = 'AKAN_HADIR';

    public const JADWAL_LAIN = 'JADWAL_LAIN';

    public const MUNDUR = 'MUNDUR';

    public const TANPA_JAWABAN = 'TANPA_JAWABAN';

    /** Jadwal dikosongkan tim, pengganti menyusul (Jadwal_Mulai NULL). */
    public const DITUNDA = 'DITUNDA';

    // ── status permintaan jadwal lain ───────────────────────────────────────
    public const P_TERBUKA = 'TERBUKA';

    public const P_DISETUJUI = 'DISETUJUI';

    public const P_WAKTU_LAIN = 'WAKTU_LAIN';

    public const P_DITOLAK = 'DITOLAK';

    public const P_DICABUT = 'DICABUT';

    public const P_DITUTUP = 'DITUTUP';

    // ── aksi jejak baru (≤ 20 huruf: kolom Aksi VARCHAR(20)) ────────────────
    public const A_DIBUKA = 'DIBUKA';

    public const A_JAWAB = 'JAWAB';

    public const A_INGAT_JAWAB = 'INGAT_JAWAB';

    public const A_PROSES = 'PROSES_PERMINTAAN';

    public const A_KABAR_TIM = 'KABAR_TIM';

    public const A_KEHADIRAN = 'KEHADIRAN';

    public const A_TUNDA = 'TUNDA';

    /** Info penundaan diperbarui (perkiraan tanggal, alasan, pesan). */
    public const A_TUNDA_INFO = 'TUNDA_INFO';

    // ── pelaku & kanal ──────────────────────────────────────────────────────
    public const TIM = 'TIM';

    public const KANDIDAT = 'KANDIDAT';

    public const SISTEM = 'SISTEM';

    public const K_LAYAR = 'LAYAR';

    public const K_PORTAL = 'PORTAL';

    public const K_TAUTAN = 'TAUTAN';

    public const K_SISTEM = 'SISTEM';

    /** Kanal yang boleh disebut tim saat mencatat jawaban atas nama kandidat. */
    public const KANAL_TIM = ['TELEPON', 'WA', 'EMAIL', 'LAYAR'];

    // ── jenis surel di Data_Json.surel ──────────────────────────────────────
    public const S_TANDA_TERIMA = 'TANDA_TERIMA';

    public const S_PENGINGAT = 'PENGINGAT';

    public const S_JADWAL_BARU = 'JADWAL_BARU';

    public const S_DITOLAK = 'DITOLAK';

    public const S_DITUNDA = 'DITUNDA';

    /** Kabar terbaru penundaan — dari "Perbarui info" yang mengabari kandidat. */
    public const S_TUNDA_INFO = 'TUNDA_INFO';

    public const S_KABAR_TIM = 'KABAR_TIM';

    public const T_CRM = 'N_WEB_CAREERS_CRM_Konfirmasi_Email';

    public const T_MINTA = 'N_WEB_CAREERS_Lamaran_Tahap_Tes_Jadwal_Permintaan';

    public const T_MASTER = 'N_WEB_CAREERS_Master_Konfirmasi_Jadwal';

    public const T_ALASAN = 'N_WEB_CAREERS_Master_Alasan_Jadwal';

    private const T_TES = 'N_WEB_CAREERS_Lamaran_Tahap_Tes';

    /** Batas teks catatan kandidat — mengikuti kolom NVARCHAR(500). */
    public const CATATAN_MAKS = 500;

    /** Penjelasan alasan "Lainnya" saat kandidat tidak melanjutkan — minimal karakter. */
    public const MUNDUR_PENJELASAN_MIN = 15;

    /** Cache per permintaan untuk worklist (lihat muatBanyak). */
    private static array $muatan = [];

    // ════════════════════════════════════════════════════════════════════════
    //  SKEMA & MASTER
    // ════════════════════════════════════════════════════════════════════════

    /** Skema fitur ini sudah dipasang (docs/01-10-2026/01)? Sebelumnya fitur diam. */
    public static function siap(): bool
    {
        try {
            return Skema::adaKolom(self::T_TES, 'Konfirmasi_Status')
                && Skema::adaKolom(JejakJadwal::TABEL, 'Data_Json')
                && Skema::adaTabel(self::T_CRM)
                && Skema::adaTabel(self::T_MINTA)
                && Skema::adaTabel(self::T_MASTER);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** Lima status, berikut label & perilakunya — dari master, per Kode. */
    public static function master(): Collection
    {
        static $cache = null;

        return $cache ??= DB::table(self::T_MASTER)->orderBy('Urutan')->get()->keyBy('Kode');
    }

    /** Alasan aktif untuk satu jenis (JADWAL_LAIN / MUNDUR), per Kode. */
    public static function alasan(string $jenis): Collection
    {
        static $cache = [];

        return $cache[$jenis] ??= DB::table(self::T_ALASAN)
            ->where('Jenis', $jenis)->where('Flag_Aktif', 'Y')
            ->orderBy('Urutan')->get()->keyBy('Kode');
    }

    /** Pilihan yang ditawarkan ke kandidat: status ber-label tombol, urut master. */
    public static function pilihanKandidat(): Collection
    {
        return self::master()->filter(fn ($m) => ($m->Flag_Aktif ?? 'Y') === 'Y' && ! empty($m->Label_Tombol));
    }

    /** Definisi tipe sebuah aktivitas — tipenya sendiri, jatuh ke tipe tahapnya. */
    public static function tipeDari(object $sub): ?object
    {
        return AlurKolom::masterTipe()->get(($sub->Tipe_Tahap_Kode ?? null) ?: ($sub->TahapTipe ?? null));
    }

    /**
     * Aktivitas ini MEMINTA konfirmasi kandidat?
     *
     * Ya bila tipenya menyalakan Flag_Konfirmasi, jadwalnya janji temu berjam
     * (bukan rentang tanggal MCU), dan tipenya bukan jadwal privat. Ditentukan
     * dari master — tipe baru cukup dinyalakan lewat data.
     */
    public static function berlaku(object $sub, ?object $tipe = null): bool
    {
        if (! self::siap()) {
            return false;
        }

        $tipe ??= self::tipeDari($sub);
        if (($tipe->Flag_Konfirmasi ?? 'T') !== 'Y') {
            return false;
        }
        if (JadwalPrivat::untuk(($sub->Tipe_Tahap_Kode ?? null) ?: ($sub->TahapTipe ?? null))) {
            return false;
        }

        return ! UndanganJadwal::berbatasWaktu(UndanganJadwal::mode($sub->Jadwal_Mode ?? null));
    }

    /**
     * Aturan konfirmasi untuk jendela Atur Jadwal — null bila tipe aktivitas
     * ini tidak meminta konfirmasi. Layar cukup memberi tahu bahwa kandidat
     * bisa menjawab sampai waktu mulai; tidak ada yang perlu diisi. Bentuk
     * jadwal berbatas waktu (MCU mandiri) disaring layar dari flag modenya.
     */
    public static function aturanLayar(object $sub, ?object $tipe = null): ?array
    {
        if (! self::siap()) {
            return null;
        }

        $tipe ??= self::tipeDari($sub);
        if (($tipe->Flag_Konfirmasi ?? 'T') !== 'Y'
            || JadwalPrivat::untuk(($sub->Tipe_Tahap_Kode ?? null) ?: ($sub->TahapTipe ?? null))) {
            return null;
        }

        return ['batas' => 'MULAI'];
    }

    /** Status yang BERLAKU saat dibaca: MENUNGGU yang batasnya lewat = TANPA_JAWABAN. */
    public static function statusEfektif(?string $status, $batas, ?Carbon $sekarang = null): ?string
    {
        if ($status === self::MENUNGGU && $batas && Carbon::parse($batas)->lte($sekarang ?? now())) {
            return self::TANPA_JAWABAN;
        }

        return $status;
    }

    /**
     * Batas konfirmasi sebuah jadwal = WAKTU MULAINYA (lihat docblock kelas).
     */
    public static function batasDari(string|Carbon $mulai): Carbon
    {
        return ($mulai instanceof Carbon ? $mulai->copy() : Carbon::parse($mulai))->setSecond(0);
    }

    // ════════════════════════════════════════════════════════════════════════
    //  KONTEKS
    // ════════════════════════════════════════════════════════════════════════

    /**
     * Satu aktivitas berikut lamaran, kandidat, program, dan PIC lokernya.
     *
     * @param  bool  $kunci  kunci baris aktivitas sampai transaksi selesai
     */
    public static function konteks(int $subTesId, bool $kunci = false): ?object
    {
        $q = DB::table(self::T_TES.' as t')
            ->join('N_WEB_CAREERS_Lamaran_Tahap as h', 'h.Id_Lamaran_Tahap', '=', 't.Lamaran_Tahap_Id')
            ->join('N_WEB_CAREERS_Lamaran as l', 'l.Id_Lamaran', '=', 'h.Lamaran_Id')
            ->join('N_WEB_CAREERS_Users as u', 'u.Id_Users', '=', 'l.Id_Users')
            ->leftJoin('N_WEB_CAREERS_Program as p', 'p.Id_Program', '=', 'l.Program_Id')
            ->leftJoin('N_WEB_CAREERS_Program_Posisi as x', 'x.Id_Program_Posisi', '=', 'l.Program_Posisi_Id')
            ->where('t.Id_Lamaran_Tahap_Tes', $subTesId)
            ->select(
                't.*',
                'h.Lamaran_Id', 'h.Label as TahapLabel', 'h.Tipe_Tahap_Kode as TahapTipe', 'h.Status as StatusTahap',
                'h.Hold_Flag',
                'l.Status as StatusLamaran', 'l.Kode as LamaranKode', 'l.Id_Users', 'l.Program_Id', 'l.Program_Posisi_Id',
                'u.Nama as KandidatNama', 'u.Email as KandidatEmail',
                'p.Nama as ProgramNama', 'p.Kategori as ProgramKategori',
                'x.Posisi', 'x.Pic_Kode_Karyawan',
            );

        if ($kunci) {
            // Hanya baris aktivitasnya yang dikunci; tabel induk cukup dibaca.
            DB::table(self::T_TES)->where('Id_Lamaran_Tahap_Tes', $subTesId)->lockForUpdate()->value('Id_Lamaran_Tahap_Tes');
        }

        return $q->first();
    }

    /** Baris CRM untuk satu versi undangan. */
    public static function crm(int $subTesId, ?int $versi): ?object
    {
        if ($versi === null) {
            return null;
        }

        return DB::table(self::T_CRM)
            ->where('Lamaran_Tahap_Tes_Id', $subTesId)
            ->where('Versi', $versi)
            ->first();
    }

    /** Permintaan jadwal lain yang masih terbuka pada aktivitas ini. */
    public static function permintaanTerbuka(int $subTesId): ?object
    {
        return DB::table(self::T_MINTA)
            ->where('Lamaran_Tahap_Tes_Id', $subTesId)
            ->where('Status', self::P_TERBUKA)
            ->first();
    }

    /** Berapa kali KANDIDAT sudah meminta jadwal lain untuk aktivitas ini. */
    public static function jumlahPermintaan(int $subTesId): int
    {
        return (int) DB::table(self::T_MINTA)
            ->where('Lamaran_Tahap_Tes_Id', $subTesId)
            ->whereIn('Kanal', [self::K_TAUTAN, self::K_PORTAL])
            ->count();
    }

    // ════════════════════════════════════════════════════════════════════════
    //  TERBIT — dipanggil sesudah jadwal disimpan, DI DALAM transaksinya
    // ════════════════════════════════════════════════════════════════════════

    /**
     * Pasang konfirmasi untuk versi jadwal yang barusan disimpan.
     *
     * Perubahan POKOK (waktu, bentuk, tempat, tautan) mengembalikan status ke
     * MENUNGGU: janji lamanya tidak berlaku. Perubahan kecil (instruksi, surat,
     * kalimat biaya) mempertahankan jawaban kandidat — orang yang sudah bilang
     * "akan hadir" tidak perlu ditanya ulang karena salah ketik di instruksi.
     *
     * Batasnya selalu WAKTU MULAI jadwal yang barusan disimpan (batasDari).
     *
     * @param  object  $sebelum  baris aktivitas SEBELUM disimpan
     * @param  array{status?: string, permintaanId?: int, permintaanHasil?: string, catatanTim?: string}  $opsi
     * @return array{versi: int, status: ?string}|null
     */
    public static function terbitkan(int $subTesId, object $sebelum, string $oleh, ?int $olehId, array $opsi = []): ?array
    {
        if (! self::siap()) {
            return null;
        }

        $sub = self::konteks($subTesId);
        if (! $sub || empty($sub->Jadwal_Mulai)) {
            return null;
        }

        $now = now();
        $versi = (int) $sub->Jadwal_Versi;
        $batas = self::batasDari($sub->Jadwal_Mulai);

        // Undangan versi lama selesai — tidak ada lagi surel otomatis untuknya.
        DB::table(self::T_CRM)
            ->where('Lamaran_Tahap_Tes_Id', $subTesId)
            ->where('Selesai', 'T')
            ->where('Versi', '<>', $versi)
            ->update(['Selesai' => 'Y', 'Selesai_Alasan' => 'DIGANTI', 'Selesai_At' => $now, 'Updated_At' => $now]);

        if (! self::berlaku($sub)) {
            // Mode berpindah ke rentang tanggal (MCU) atau tipe tanpa konfirmasi:
            // jawaban lama tidak berarti apa-apa lagi.
            DB::table(self::T_TES)->where('Id_Lamaran_Tahap_Tes', $subTesId)->update(['Konfirmasi_Status' => null]);
            self::tutupPermintaan($subTesId, self::P_DITUTUP, $oleh, $olehId, null, null);

            return ['versi' => $versi, 'status' => null];
        }

        $pokok = self::perubahanPokok($sebelum, $sub);
        $lama = $sebelum->Konfirmasi_Status ?? null;
        $status = $opsi['status'] ?? (
            $pokok || $lama === null || in_array($lama, [self::MENUNGGU, self::TANPA_JAWABAN], true)
                ? self::MENUNGGU
                : $lama
        );

        // Permintaan yang masih terbuka: jadwal yang diubah tim adalah
        // jawabannya (waktu lain), kecuali pemanggil menyebut hasilnya sendiri.
        if ($pokok) {
            self::tutupPermintaan(
                $subTesId,
                $opsi['permintaanHasil'] ?? self::P_WAKTU_LAIN,
                $oleh,
                $olehId,
                $versi,
                $opsi['catatanTim'] ?? null,
            );
        } else {
            // Suntingan kecil: permintaannya tetap berlaku dan ikut pindah ke
            // versi baru — tanpa ini tim tidak bisa lagi memprosesnya ("jadwal
            // sudah diperbarui"), padahal jamnya sama sekali tidak berubah.
            DB::table(self::T_MINTA)
                ->where('Lamaran_Tahap_Tes_Id', $subTesId)
                ->where('Status', self::P_TERBUKA)
                ->update(['Versi' => $versi, 'Updated_At' => $now]);
        }

        DB::table(self::T_TES)->where('Id_Lamaran_Tahap_Tes', $subTesId)->update(['Konfirmasi_Status' => $status]);

        $jawabLama = ! $pokok && $status !== self::MENUNGGU;
        $crmLama = $jawabLama ? self::crm($subTesId, $versi - 1) : null;

        DB::table(self::T_CRM)->updateOrInsert(
            ['Lamaran_Tahap_Tes_Id' => $subTesId, 'Versi' => $versi],
            [
                'Id_Users' => (int) $sub->Id_Users,
                'Email' => mb_substr((string) $sub->KandidatEmail, 0, 150),
                'Nama' => mb_substr((string) $sub->KandidatNama, 0, 150),
                'Program_Id' => $sub->Program_Id,
                'Program_Posisi_Id' => $sub->Program_Posisi_Id,
                'Aktivitas' => mb_substr((string) $sub->Label, 0, 120),
                'Jadwal_Mulai' => $sub->Jadwal_Mulai,
                'Batas_Konfirmasi' => $batas->format('Y-m-d H:i:s'),
                'Status_Konfirmasi' => $status,
                'Undangan_At' => $now,
                'Dijawab_At' => $status === self::MENUNGGU ? null : ($crmLama->Dijawab_At ?? $now),
                'Terakhir_Kirim_At' => $now,
                'Terakhir_Kirim_Jenis' => 'UNDANGAN',
                'Terakhir_Kirim_Oleh' => mb_substr($oleh ?: 'SISTEM', 0, 200),
                'Selesai' => $status === self::MUNDUR ? 'Y' : 'T',
                'Selesai_Alasan' => $status === self::MUNDUR ? 'MUNDUR' : null,
                'Selesai_At' => $status === self::MUNDUR ? $now : null,
                'Updated_At' => $now,
            ],
        );

        return ['versi' => $versi, 'status' => $status];
    }

    /** Waktu, bentuk, tempat, atau tautan berubah? (atau jadwal baru terbit) */
    public static function perubahanPokok(object $sebelum, object $sesudah): bool
    {
        if (empty($sebelum->Jadwal_Mulai)) {
            return true;
        }

        $sama = fn ($a, $b) => trim((string) $a) === trim((string) $b);
        $waktu = fn ($v) => $v ? Carbon::parse($v)->format('Y-m-d H:i') : '';

        return ! ($waktu($sebelum->Jadwal_Mulai) === $waktu($sesudah->Jadwal_Mulai)
            && $waktu($sebelum->Jadwal_Selesai ?? null) === $waktu($sesudah->Jadwal_Selesai ?? null)
            && $sama($sebelum->Jadwal_Mode ?? null, $sesudah->Jadwal_Mode ?? null)
            && $sama($sebelum->Jadwal_Lokasi_Id ?? null, $sesudah->Jadwal_Lokasi_Id ?? null)
            && $sama($sebelum->Jadwal_Lokasi_Nama ?? null, $sesudah->Jadwal_Lokasi_Nama ?? null)
            && $sama($sebelum->Jadwal_Link ?? null, $sesudah->Jadwal_Link ?? null)
            && $sama($sebelum->Jadwal_Kontak ?? null, $sesudah->Jadwal_Kontak ?? null));
    }

    // ════════════════════════════════════════════════════════════════════════
    //  JAWABAN — kandidat (tautan/portal) atau tim atas nama kandidat
    // ════════════════════════════════════════════════════════════════════════

    /**
     * Catat jawaban.
     *
     * @param  array{alasan?: ?string, catatan?: ?string, usulan?: ?array, statusDilihat?: ?string}  $isian
     * @param  array{jenis: string, kanal: string, nama?: ?string, id?: ?int, ip?: ?string, perangkat?: ?string}  $pelaku
     * @return array{ok: bool, kode: int, pesan: string, status?: ?string}
     */
    public static function jawab(int $subTesId, int $versi, string $kode, array $isian, array $pelaku): array
    {
        if (! self::siap()) {
            return ['ok' => false, 'kode' => 409, 'pesan' => 'Fitur konfirmasi belum aktif.'];
        }

        $def = self::master()->get($kode);
        if (! $def || empty($def->Label_Tombol)) {
            return ['ok' => false, 'kode' => 422, 'pesan' => 'Pilihan jawaban tidak dikenali.'];
        }

        $olehTim = ($pelaku['jenis'] ?? '') === self::TIM;

        try {
            $hasil = DB::transaction(function () use ($subTesId, $versi, $kode, $def, $isian, $pelaku, $olehTim) {
                $sub = self::konteks($subTesId, true);
                if ($galat = self::galatTerbuka($sub, $versi)) {
                    return ['ok' => false, 'kode' => 409, 'pesan' => $galat, 'status' => $sub->Konfirmasi_Status ?? null];
                }

                $crm = self::crm($subTesId, $versi);
                $lama = (string) $sub->Konfirmasi_Status;
                $efektif = self::statusEfektif($lama, $crm->Batas_Konfirmasi ?? null);

                if (! $olehTim) {
                    // Lewat batas = tombol kandidat HILANG, apa pun jawabannya.
                    // Sesudah itu hanya tim yang mencatat (lewat telepon/WA).
                    if ($efektif === self::TANPA_JAWABAN || ! $crm || Carbon::parse($crm->Batas_Konfirmasi)->lte(now())) {
                        return ['ok' => false, 'kode' => 422, 'pesan' => 'Batas konfirmasi sudah lewat. Hubungi tim rekrutmen bila berhalangan.', 'status' => $efektif];
                    }
                    $defLama = self::master()->get($lama);
                    if (($defLama->Flag_Final_Kandidat ?? 'T') === 'Y') {
                        return ['ok' => false, 'kode' => 409, 'pesan' => 'Pernyataanmu sudah kami terima dan tidak bisa diubah lewat halaman ini.', 'status' => $lama];
                    }
                }

                // Tampilan yang dilihat sudah basi (tab lain / admin lain lebih
                // dulu). Jawab dengan keadaan terbaru, jangan menimpa diam-diam.
                if (! empty($isian['statusDilihat']) && $isian['statusDilihat'] !== $efektif) {
                    return ['ok' => false, 'kode' => 409, 'pesan' => 'Status konfirmasi baru saja berubah. Periksa keadaan terbaru, lalu jawab lagi bila perlu.', 'status' => $efektif];
                }

                // Jawaban sama dengan yang tercatat → sudah beres, tanpa surel kedua.
                if ($kode === $lama && ($def->Flag_Buka_Permintaan ?? 'T') !== 'Y') {
                    return ['ok' => true, 'kode' => 200, 'pesan' => 'Jawabanmu sudah tercatat.', 'status' => $lama];
                }
                if ($kode === $lama && ($def->Flag_Buka_Permintaan ?? 'T') === 'Y') {
                    return ['ok' => false, 'kode' => 409, 'pesan' => 'Permintaan jadwal lain sudah ada dan sedang ditinjau tim.', 'status' => $lama];
                }

                // ── KOMITMEN: jawaban yang SUDAH ADA hanya boleh diubah sesuai
                // aturan tipenya (aturanUbah). "Tidak melanjutkan" tetap bebas;
                // tim yang mencatat atas nama kandidat tidak dibatasi.
                $mengubah = ! $olehTim && in_array($lama, [self::AKAN_HADIR, self::JADWAL_LAIN], true);
                if ($mengubah && ($def->Flag_Sinyal_Mundur ?? 'T') !== 'Y') {
                    $aturan = self::aturanUbahUntuk($sub);
                    if ($aturan['terkunci']) {
                        return ['ok' => false, 'kode' => 422, 'pesan' => self::pesanKunci($aturan), 'status' => $efektif, 'terkunci' => true];
                    }
                }

                $tipe = self::tipeDari($sub);
                $permintaanId = null;
                $data = ['surel' => self::S_TANDA_TERIMA, 'jenis' => $kode];

                // ── MINTA JADWAL LAIN ─────────────────────────────────────
                if (($def->Flag_Buka_Permintaan ?? 'T') === 'Y') {
                    [$permintaanId, $galat] = self::bukaPermintaan($sub, $versi, $tipe, $isian, $pelaku, $olehTim);
                    if ($galat) {
                        return ['ok' => false, 'kode' => 422, 'pesan' => $galat, 'status' => $efektif];
                    }
                }

                // ── MUNDUR: alasan dari master ────────────────────────────
                // Dari KANDIDAT (keputusan user 2 Okt 2026): alasan WAJIB;
                // alasan ber-Flag_Butuh_Catatan ("Lainnya") wajib dijelaskan
                // dengan kalimat sungguhan (Kalimat::periksa — cermin
                // utils/career/kalimat.js); centang "saya mengerti lamaran
                // saya akan ditutup" WAJIB ikut terkirim bernilai benar —
                // tombol yang dibuka paksa lewat inspect element tetap ditolak
                // di sini. Tim yang mencatat atas nama kandidat tidak dibatasi.
                if (($def->Flag_Sinyal_Mundur ?? 'T') === 'Y') {
                    $alasanKode = trim((string) ($isian['alasan'] ?? ''));
                    $alasanMd = $alasanKode !== '' ? self::alasan('MUNDUR')->get($alasanKode) : null;
                    if ($alasanKode !== '' && ! $alasanMd) {
                        return ['ok' => false, 'kode' => 422, 'pesan' => 'Alasan tidak dikenali.', 'status' => $efektif];
                    }
                    if (! $olehTim) {
                        if (! $alasanMd) {
                            return ['ok' => false, 'kode' => 422, 'pesan' => 'Pilih alasan kenapa kamu tidak melanjutkan seleksi.', 'status' => $efektif];
                        }
                        if (($alasanMd->Flag_Butuh_Catatan ?? 'T') === 'Y'
                            && ($galatKalimat = Kalimat::periksa((string) ($isian['catatan'] ?? ''), self::MUNDUR_PENJELASAN_MIN))) {
                            return ['ok' => false, 'kode' => 422, 'pesan' => $galatKalimat, 'status' => $efektif];
                        }
                        if (($isian['paham'] ?? false) !== true) {
                            return ['ok' => false, 'kode' => 422, 'pesan' => 'Centang "Saya mengerti lamaran saya akan ditutup" untuk melanjutkan.', 'status' => $efektif];
                        }
                    }
                    $data['alasan'] = $alasanKode ?: null;
                    $data['alasanNama'] = $alasanMd->Nama ?? null;
                }

                $catatan = self::rapikanCatatan($isian['catatan'] ?? null);
                if ($catatan !== null) {
                    $data['catatan'] = $catatan;
                }
                if ($olehTim && $catatan === null) {
                    return ['ok' => false, 'kode' => 422, 'pesan' => 'Tuliskan catatan percakapannya (minimal 5 huruf) — jawaban atas nama kandidat harus bisa dijelaskan.', 'status' => $efektif];
                }

                $data += self::jejakPelaku($pelaku);

                // Berpindah DARI minta jadwal lain ke jawaban lain: permintaannya
                // tidak lagi berarti apa-apa. Kandidat = dicabut; tim = ditutup.
                if ($lama === self::JADWAL_LAIN && ($def->Flag_Buka_Permintaan ?? 'T') !== 'Y') {
                    self::tutupPermintaan(
                        $subTesId,
                        $olehTim ? self::P_DITUTUP : self::P_DICABUT,
                        (string) ($pelaku['nama'] ?? $sub->KandidatNama),
                        $pelaku['id'] ?? null,
                        null,
                        $olehTim ? 'Jawaban dicatat tim: '.(self::master()->get($kode)->Label_Tim ?? $kode) : null,
                    );
                }

                // CAS: hanya berubah bila versi & status masih yang dibaca tadi.
                $ubah = DB::table(self::T_TES)
                    ->where('Id_Lamaran_Tahap_Tes', $subTesId)
                    ->where('Jadwal_Versi', $versi)
                    ->where('Konfirmasi_Status', $lama)
                    ->update(['Konfirmasi_Status' => $kode, 'Updated_At' => now()]);
                if (! $ubah) {
                    return ['ok' => false, 'kode' => 409, 'pesan' => 'Status konfirmasi baru saja berubah. Muat ulang halaman.', 'status' => null];
                }

                $now = now();
                $final = ($def->Flag_Final_Kandidat ?? 'T') === 'Y';
                DB::table(self::T_CRM)
                    ->where('Lamaran_Tahap_Tes_Id', $subTesId)->where('Versi', $versi)
                    ->update([
                        'Status_Konfirmasi' => $kode,
                        'Dijawab_At' => $now,
                        'Selesai' => $final ? 'Y' : 'T',
                        'Selesai_Alasan' => $final ? 'MUNDUR' : null,
                        'Selesai_At' => $final ? $now : null,
                        'Updated_At' => $now,
                    ]);

                $ids = [self::tulisJejak($subTesId, $versi, self::A_JAWAB, $pelaku, [
                    'Status_Dari' => $efektif,
                    'Status_Ke' => $kode,
                    'Permintaan_Id' => $permintaanId,
                    'Alasan' => $catatan,
                    'Undangan' => 'ANTRE',
                    'Data_Json' => $data,
                ])];

                if (($def->Flag_Kabar_Tim ?? 'T') === 'Y') {
                    $ids[] = self::tulisJejak($subTesId, $versi, self::A_KABAR_TIM, ['jenis' => self::SISTEM, 'kanal' => self::K_SISTEM], [
                        'Permintaan_Id' => $permintaanId,
                        'Undangan' => 'ANTRE',
                        'Data_Json' => ['surel' => self::S_KABAR_TIM, 'jenis' => $kode] + array_intersect_key($data, array_flip(['alasan', 'alasanNama', 'catatan'])),
                    ]);
                }

                // ── TIDAK MELANJUTKAN = TIDAK HADIR ─────────────────────────
                // Keputusan user 2 Okt 2026: pernyataan ini berlaku PERSIS seperti
                // tim menekan "Tidak Hadir" — implementasinya sama
                // (LamaranService::catatTidakHadir), di transaksi yang sama dengan
                // jawaban & snapshot-nya; surelnya baru berangkat sesudah semuanya
                // tersimpan (antrekan → afterCommit). Catatannya = alasan &
                // penjelasan kandidat. Tim tak perlu mengeklik apa pun lagi untuk
                // aktivitas ini; lamarannya tetap ditutup tim lewat keputusan
                // "Mengundurkan Diri". Kandidat yang TIDAK menjawab tetap
                // ditandai tim sendiri.
                $tidakHadir = ($def->Flag_Sinyal_Mundur ?? 'T') === 'Y'
                    && self::tidakHadirKarenaMundur($subTesId, $data['alasanNama'] ?? null, $catatan, $pelaku, $olehTim);

                self::antrekan($ids);

                // Sisa jatah ubah dihitung SESUDAH baris jejak di atas tertulis
                // — jawaban yang barusan dikirim sudah ikut terhitung.
                $pesan = self::kalimatSukses($kode);
                if (! $olehTim && ($def->Flag_Final_Kandidat ?? 'T') !== 'Y') {
                    $pesan .= ' '.self::teksUbah(self::aturanUbahUntuk($sub), true);
                }

                return ['ok' => true, 'kode' => 200, 'pesan' => $pesan, 'status' => $kode, 'tidakHadir' => $tidakHadir];
            });
        } catch (\Illuminate\Database\QueryException $e) {
            // UNIQUE "satu permintaan terbuka" menangkap dua kiriman serentak.
            if (str_contains($e->getMessage(), 'UX_WC_LTTJP_Terbuka')) {
                return ['ok' => false, 'kode' => 409, 'pesan' => 'Permintaan jadwal lain sudah ada dan sedang ditinjau tim.'];
            }
            throw $e;
        }

        if ($hasil['ok'] ?? false) {
            Log::channel('web_career')->info("[KONFIRMASI] aktivitas #{$subTesId} v{$versi} → {$kode} oleh ".($pelaku['jenis'] ?? '?').'/'.($pelaku['kanal'] ?? '?')
                .(($hasil['tidakHadir'] ?? false) ? ' — ditandai TIDAK HADIR otomatis' : ''));
        }
        // Sesudah commit — sama dengan tombol Tidak Hadir: jejak kehadiran,
        // undangan versi lain & permintaan yang tersisa ditutup.
        if (($hasil['ok'] ?? false) && ($hasil['tidakHadir'] ?? false)) {
            $olehTim
                ? self::catatKehadiran($subTesId, 'T', (string) ($pelaku['nama'] ?? 'TIM'), $pelaku['id'] ?? null, $pelaku, ['sebab' => self::MUNDUR])
                : self::catatKehadiran($subTesId, 'T', 'SISTEM', null, ['jenis' => self::SISTEM, 'kanal' => self::K_SISTEM, 'nama' => 'SISTEM'], ['sebab' => self::MUNDUR]);
        }

        return $hasil;
    }

    /**
     * "Tidak melanjutkan" → Tidak Hadir, lewat gerbang yang sama dengan tombol
     * tim (jadwal ada, belum dicatat, giliran urutannya sudah tiba). Gerbang
     * yang menolak = pernyataannya tetap tercatat, kehadirannya ditandai tim.
     */
    private static function tidakHadirKarenaMundur(int $subTesId, ?string $alasanNama, ?string $catatan, array $pelaku, bool $olehTim): bool
    {
        $sub = DB::table(self::T_TES)->where('Id_Lamaran_Tahap_Tes', $subTesId)->first();
        if (! $sub || empty($sub->Jadwal_Mulai) || ! empty($sub->Jadwal_Hadir) || ($sub->Flag_Selesai ?? 'N') === 'Y') {
            return false;
        }
        if ($kunci = LamaranController::kunciUrutan($sub)) {
            Log::channel('web_career')->info("[KONFIRMASI] aktivitas #{$subTesId}: Tidak Hadir otomatis dilewati — {$kunci}");

            return false;
        }

        [$html, $ringkas] = self::catatanMundur($alasanNama, $catatan, $pelaku, $olehTim);
        app(LamaranService::class)->catatTidakHadir(
            $sub,
            $html,
            $ringkas,
            $olehTim ? (string) ($pelaku['nama'] ?? 'TIM') : 'SISTEM (pernyataan kandidat)',
            $olehTim ? ($pelaku['id'] ?? null) : null,
        );

        return true;
    }

    /**
     * Catatan Tidak Hadir dari pernyataan "tidak melanjutkan" — isinya sama
     * dengan yang dikirim kandidat (alasan + penjelasannya), atau catatan
     * percakapan bila dicatat tim.
     *
     * @return array{0: ?string, 1: string} [html, ringkas]
     */
    private static function catatanMundur(?string $alasanNama, ?string $catatan, array $pelaku, bool $olehTim): array
    {
        $lewat = [
            self::K_PORTAL => 'lewat Portal Kandidat',
            self::K_TAUTAN => 'lewat tautan konfirmasi di email',
            'TELEPON' => 'lewat telepon',
            'WA' => 'lewat WhatsApp',
            'EMAIL' => 'lewat email',
            'LAYAR' => 'langsung',
        ][$pelaku['kanal'] ?? ''] ?? null;

        $kepala = $olehTim
            ? 'Dicatat '.($pelaku['nama'] ?? 'tim').($lewat ? " ({$lewat})" : '').': kandidat menyatakan tidak melanjutkan seleksi.'
            : 'Kandidat menyatakan tidak melanjutkan seleksi'.($lewat ? " {$lewat}" : '').'.';

        $html = '<p><strong>'.e($kepala).'</strong></p>'
            .($alasanNama ? '<p>Alasan: '.e($alasanNama).'</p>' : '')
            .($catatan ? '<blockquote>'.e($catatan).'</blockquote>' : '');
        $ringkas = $kepala.($alasanNama ? " Alasan: {$alasanNama}." : '').($catatan ? ' "'.$catatan.'"' : '');

        return [HtmlBersih::saring($html), mb_substr($ringkas, 0, 480)];
    }

    /** Kalimat balasan per jawaban — dibaca kandidat di halaman. */
    private static function kalimatSukses(string $kode): string
    {
        $def = self::master()->get($kode);

        return match (true) {
            ($def->Flag_Buka_Permintaan ?? 'T') === 'Y' => 'Permintaanmu sudah kami terima. Jadwal semula tetap berlaku sampai tim menjawab.',
            ($def->Flag_Sinyal_Mundur ?? 'T') === 'Y' => 'Pernyataanmu sudah kami terima. Tim rekrutmen akan menutup lamaranmu.',
            default => 'Terima kasih, kehadiranmu sudah kami catat.',
        };
    }

    // ════════════════════════════════════════════════════════════════════════
    //  KOMITMEN JAWABAN — berapa kali & sampai kapan kandidat boleh mengubah
    // ════════════════════════════════════════════════════════════════════════

    /**
     * Aturan MENGUBAH jawaban untuk satu jadwal (lihat docblock kelas). Murni
     * — tanpa basis data — supaya bisa diuji langsung.
     *
     * sebab: USULAN (waktu ini usulan kandidat sendiri yang disetujui tim) |
     * SEKALI (tipe tanpa jatah ubah) | JATAH (jatah habis) | WAKTU (lewat
     * batas ubah); null = masih boleh diubah.
     *
     * USULAN didahulukan (keputusan user 2 Okt 2026): kandidat yang meminta
     * jadwal lain lalu usulannya disetujui sudah memilih waktunya sendiri —
     * "Ubah jawaban" sesudah itu hanya membuka putaran tawar-menawar baru.
     *
     * @return array{maks: int, terpakai: int, sisa: int, jam: int, batas: ?Carbon, terkunci: bool, sebab: ?string}
     */
    public static function aturanUbah(?object $tipe, string|Carbon|null $mulai, int $terpakai, ?Carbon $sekarang = null, bool $usulanSendiri = false): array
    {
        $maks = max(0, (int) ($tipe->Ubah_Jawaban_Maks ?? config('konfirmasi.ubah_maks', 1)));
        $jam = max(0, (int) ($tipe->Ubah_Jawaban_Batas_Jam ?? config('konfirmasi.ubah_batas_jam', 72)));
        $batas = $mulai ? self::batasDari($mulai)->subHours($jam) : null;
        $terpakai = max(0, $terpakai);

        $sebab = match (true) {
            $usulanSendiri => 'USULAN',
            $maks === 0 => 'SEKALI',
            $terpakai >= $maks => 'JATAH',
            ! $batas || $batas->lte($sekarang ?? now()) => 'WAKTU',
            default => null,
        };

        return [
            'maks' => $maks,
            'terpakai' => $terpakai,
            'sisa' => max(0, $maks - $terpakai),
            'jam' => $jam,
            'batas' => $batas,
            'terkunci' => $sebab !== null,
            'sebab' => $sebab,
        ];
    }

    /** aturanUbah() untuk satu aktivitas: tipenya, jam mulainya, jatah terpakainya, asal waktunya. */
    public static function aturanUbahUntuk(object $sub): array
    {
        $id = (int) $sub->Id_Lamaran_Tahap_Tes;
        $mulai = $sub->Jadwal_Mulai ?? null;
        $versi = self::versiSejam($id, $mulai);

        return self::aturanUbah(
            self::tipeDari($sub),
            $mulai,
            self::jumlahUbah($id, $mulai, $versi),
            null,
            self::dariUsulan($id, array_merge($versi, [(int) ($sub->Jadwal_Versi ?? 0)])),
        );
    }

    /**
     * Versi-versi jadwal yang berjam mulai SAMA dengan `$mulai` — suntingan
     * kecil tim (instruksi, surat) menaikkan versi tanpa menggeser waktunya.
     *
     * @return int[]
     */
    private static function versiSejam(int $subTesId, ?string $mulai): array
    {
        if (! $mulai) {
            return [];
        }
        $kunci = Carbon::parse($mulai)->format('Y-m-d H:i');

        return DB::table(self::T_CRM)
            ->where('Lamaran_Tahap_Tes_Id', $subTesId)
            ->get(['Versi', 'Jadwal_Mulai'])
            ->filter(fn ($c) => Carbon::parse($c->Jadwal_Mulai)->format('Y-m-d H:i') === $kunci)
            ->pluck('Versi')->map(fn ($v) => (int) $v)->values()->all();
    }

    /**
     * Waktu jadwal ini lahir dari USULAN kandidat yang disetujui tim
     * (permintaan DISETUJUI yang versi hasilnya salah satu versi berjam sama).
     *
     * @param  int[]  $versi
     */
    private static function dariUsulan(int $subTesId, array $versi): bool
    {
        $versi = array_values(array_unique(array_filter($versi, fn ($v) => $v > 0)));
        if (! $versi) {
            return false;
        }

        return DB::table(self::T_MINTA)
            ->where('Lamaran_Tahap_Tes_Id', $subTesId)
            ->where('Status', self::P_DISETUJUI)
            ->whereIn('Versi_Hasil', $versi)
            ->exists();
    }

    /**
     * Berapa kali KANDIDAT sudah mengubah jawabannya untuk WAKTU jadwal ini:
     * baris jejak kandidat yang berangkat dari jawaban yang sudah ada (akan
     * hadir / jadwal lain), di semua versi berjam mulai sama — suntingan kecil
     * tim (instruksi, surat) menaikkan versi tetapi tidak memulihkan jatahnya.
     * Jawaban ulang sesudah permintaan DITOLAK berangkat dari MENUNGGU, jadi
     * tidak terhitung.
     */
    public static function jumlahUbah(int $subTesId, ?string $mulai, ?array $versi = null): int
    {
        if (! $mulai) {
            return 0;
        }

        $versi ??= self::versiSejam($subTesId, $mulai);
        if (! $versi) {
            return 0;
        }

        return (int) DB::table(JejakJadwal::TABEL)
            ->where('Lamaran_Tahap_Tes_Id', $subTesId)
            ->whereIn('Versi', $versi)
            ->where('Pelaku', self::KANDIDAT)
            ->whereIn('Aksi', [self::A_JAWAB, self::A_PROSES])
            ->whereIn('Status_Dari', [self::AKAN_HADIR, self::JADWAL_LAIN])
            ->count();
    }

    /** "72" → "3 hari", "36" → "36 jam". */
    public static function jarakTeks(int $jam): string
    {
        return $jam > 0 && $jam % 24 === 0 ? ($jam / 24).' hari' : $jam.' jam';
    }

    /**
     * Kalimat aturan ubah untuk kandidat — di surel, halaman, dan balasan.
     *
     * @param  bool  $sudahMenjawab  false = sebelum menjawab (ajakan berkomitmen)
     */
    public static function teksUbah(array $aturan, bool $sudahMenjawab): string
    {
        $kali = fn (int $n) => $n === 1 ? 'satu kali' : $n.' kali';
        $batasTeks = $aturan['batas'] ? UndanganJadwal::teksBatas($aturan['batas']) : null;

        if (! $sudahMenjawab) {
            return match ($aturan['sebab']) {
                null => 'Pilih dengan yakin: setelah dikirim, jawabanmu hanya bisa diubah '.$kali($aturan['sisa'])
                    .', paling lambat '.$batasTeks.' ('.self::jarakTeks($aturan['jam']).' sebelum jadwal).',
                'WAKTU' => 'Jadwal tinggal kurang dari '.self::jarakTeks($aturan['jam']).' — jawabanmu langsung final begitu dikirim.',
                default => 'Pilih dengan yakin: jawabanmu langsung final begitu dikirim.',
            };
        }

        return match ($aturan['sebab']) {
            null => 'Bila berubah rencana, jawaban masih bisa diubah '.$kali($aturan['sisa']).', paling lambat '.$batasTeks.'.',
            'USULAN' => 'Jadwal ini kamu ajukan sendiri dan sudah disetujui tim — kehadiranmu final. Bila ada keadaan mendesak, hubungi tim rekrutmen.',
            default => 'Jawabanmu kini final. Bila ada keadaan mendesak, hubungi tim rekrutmen.',
        };
    }

    /** Kalimat saat perubahan ditolak — sekaligus jalan keluarnya. */
    public static function pesanKunci(array $aturan): string
    {
        $jalan = ' Bila ada keadaan mendesak, hubungi tim rekrutmen. Pilihan "tidak melanjutkan seleksi" tetap tersedia.';

        return match ($aturan['sebab'] ?? null) {
            'USULAN' => 'Jadwal ini usulanmu sendiri yang sudah disetujui tim, jadi kehadiranmu final.'.$jalan,
            'SEKALI' => 'Jawaban untuk jadwal ini bersifat final dan tidak bisa diubah lewat halaman ini.'.$jalan,
            'JATAH' => 'Kamu sudah mengubah jawaban '.($aturan['maks'] === 1 ? 'satu kali' : $aturan['maks'].' kali').' — jawabanmu kini terkunci.'.$jalan,
            'WAKTU' => 'Perubahan jawaban ditutup sejak '.UndanganJadwal::teksBatas($aturan['batas'])
                .' ('.self::jarakTeks($aturan['jam']).' sebelum jadwal).'.$jalan,
            default => 'Jawabanmu sudah terkunci.'.$jalan,
        };
    }

    /** Aturan ubah untuk layar (JSON) — kandidat maupun tim. */
    public static function bentukUbah(array $aturan): array
    {
        return [
            'maks' => $aturan['maks'],
            'terpakai' => $aturan['terpakai'],
            'sisa' => $aturan['sisa'],
            'batas' => $aturan['batas']?->format('Y-m-d H:i:s'),
            'batasTeks' => $aturan['batas'] ? UndanganJadwal::teksBatas($aturan['batas']) : null,
            'jarakTeks' => self::jarakTeks($aturan['jam']),
            'terkunci' => $aturan['terkunci'],
            'sebab' => $aturan['sebab'],
            'teksSebelum' => self::teksUbah($aturan, false),
            'teksSesudah' => self::teksUbah($aturan, true),
            'pesanKunci' => $aturan['terkunci'] ? self::pesanKunci($aturan) : null,
        ];
    }

    /**
     * Buka permintaan jadwal lain (validasi alasan, usulan, jatah, SLA).
     *
     * @return array{0: ?int, 1: ?string} [id permintaan, galat]
     */
    private static function bukaPermintaan(object $sub, int $versi, ?object $tipe, array $isian, array $pelaku, bool $olehTim): array
    {
        $alasanKode = trim((string) ($isian['alasan'] ?? ''));
        $alasan = self::alasan('JADWAL_LAIN')->get($alasanKode);
        if (! $alasan) {
            return [null, 'Pilih alasan kenapa waktu ini tidak bisa.'];
        }

        $catatan = self::rapikanCatatan($isian['catatan'] ?? null);
        if (($alasan->Flag_Butuh_Catatan ?? 'T') === 'Y' && $catatan === null) {
            return [null, 'Ceritakan singkat alasannya di catatan (minimal 5 huruf).'];
        }

        [$usulan, $galat] = self::periksaUsulan($isian['usulan'] ?? [], $sub);
        if ($galat) {
            return [null, $galat];
        }

        // Jatah per aktivitas — permintaan yang dicatat TIM tidak memakan jatah:
        // tim yang memutuskan memang perlu atau tidak.
        $maks = (int) ($tipe->Jadwal_Ulang_Maks ?? 2);
        if (! $olehTim && self::jumlahPermintaan((int) $sub->Id_Lamaran_Tahap_Tes) >= $maks) {
            return [null, "Kamu sudah {$maks} kali meminta jadwal lain untuk aktivitas ini. Bila tetap berhalangan, hubungi tim rekrutmen."];
        }

        $now = now();
        $id = DB::table(self::T_MINTA)->insertGetId([
            'Lamaran_Tahap_Tes_Id' => (int) $sub->Id_Lamaran_Tahap_Tes,
            'Versi' => $versi,
            'Status' => self::P_TERBUKA,
            'Alasan_Kode' => $alasanKode,
            'Catatan_Kandidat' => $catatan,
            'Usulan_Json' => json_encode($usulan, JSON_UNESCAPED_UNICODE),
            'Kanal' => mb_substr((string) ($pelaku['kanal'] ?? self::K_TAUTAN), 0, 10),
            'Diminta_At' => $now,
            'Sla_Batas' => self::slaBatas($sub, $tipe, $now)->format('Y-m-d H:i:s'),
            'Created_At' => $now,
            'Created_By' => mb_substr((string) ($pelaku['nama'] ?? $sub->KandidatNama ?? 'KANDIDAT'), 0, 200),
        ], 'Id_Jadwal_Permintaan');

        return [(int) $id, null];
    }

    /**
     * Tenggat tim menjawab: Sla_Jadwal_Ulang_Jam jam kerja (8 jam = 1 hari
     * kerja, kalender HCIS), tetapi tidak melewati 4 jam sebelum jadwal semula.
     */
    public static function slaBatas(object $sub, ?object $tipe, Carbon $dari): Carbon
    {
        $hari = max(1, (int) ceil(((int) ($tipe->Sla_Jadwal_Ulang_Jam ?? 8)) / 8));

        try {
            $sla = SlaMpp::tambahHariKerja($dari, $hari)->setTime($dari->hour, $dari->minute);
        } catch (\Throwable $e) {
            $sla = $dari->copy()->addDays($hari);
        }

        if (! empty($sub->Jadwal_Mulai)) {
            $sebelumAcara = Carbon::parse($sub->Jadwal_Mulai)->subHours(4);
            if ($sebelumAcara->lt($sla) && $sebelumAcara->gt($dari->copy()->addHour())) {
                $sla = $sebelumAcara;
            }
        }

        return $sla->setSecond(0);
    }

    /**
     * Rapikan & periksa usulan waktu kandidat.
     *
     * Bentuk masukan: [{tanggal: 'Y-m-d', bagian: 'PAGI'}...] — bagian hari dari
     * config/konfirmasi.php. Disimpan dengan jam mulai & selesainya, supaya
     * perubahan jam kerja kelak tidak mengubah arti usulan lama.
     *
     * @return array{0: array, 1: ?string}
     */
    public static function periksaUsulan(mixed $usulan, object $sub): array
    {
        $maks = max(1, (int) config('konfirmasi.usulan_maks', 3));
        $hariMaks = max(1, (int) config('konfirmasi.usulan_hari_maks', 14));
        $bagian = collect(config('konfirmasi.bagian_hari', []))->keyBy('kode');

        $daftar = collect(is_array($usulan) ? $usulan : [])
            ->filter(fn ($u) => is_array($u) && ! empty($u['tanggal']) && ! empty($u['bagian']))
            ->values();

        if ($daftar->isEmpty()) {
            return [[], 'Pilih minimal satu waktu yang kamu bisa.'];
        }
        if ($daftar->count() > $maks) {
            return [[], "Paling banyak {$maks} pilihan waktu."];
        }

        $besok = now()->addDay()->startOfDay();
        $akhir = now()->addDays($hariMaks)->endOfDay();
        $hasil = [];
        $sudah = [];

        foreach ($daftar as $u) {
            try {
                $tgl = Carbon::createFromFormat('Y-m-d', (string) $u['tanggal'])->startOfDay();
            } catch (\Throwable $e) {
                return [[], 'Tanggal usulan tidak dikenali.'];
            }
            $b = $bagian->get((string) $u['bagian']);
            if (! $b) {
                return [[], 'Bagian hari usulan tidak dikenali.'];
            }
            if ($tgl->lt($besok) || $tgl->gt($akhir)) {
                return [[], "Usulan waktu harus mulai besok dan paling jauh {$hariMaks} hari ke depan."];
            }
            if ($tgl->isSunday()) {
                return [[], 'Hari Minggu bukan hari kerja — pilih Senin sampai Sabtu.'];
            }
            $kunci = $tgl->format('Y-m-d').'|'.$b['kode'];
            if (isset($sudah[$kunci])) {
                continue;
            }
            $sudah[$kunci] = true;
            $hasil[] = [
                'tanggal' => $tgl->format('Y-m-d'),
                'bagian' => $b['kode'],
                'mulai' => $b['mulai'],
                'selesai' => $b['selesai'],
            ];
        }

        return [$hasil, null];
    }

    /** "Senin, 12 Okt 2026 · Pagi (08.00–12.00)" */
    public static function teksUsulan(array $u): string
    {
        $bagian = collect(config('konfirmasi.bagian_hari', []))->keyBy('kode')->get($u['bagian'] ?? '');
        $tgl = Carbon::parse($u['tanggal']);
        $hari = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'][$tgl->dayOfWeek];

        return $hari.', '.UndanganJadwal::tanggalPendek($tgl).' · '.($bagian['label'] ?? ($u['bagian'] ?? ''))
            .' ('.str_replace(':', '.', $u['mulai'] ?? '').'–'.str_replace(':', '.', $u['selesai'] ?? '').')';
    }

    /**
     * Kandidat membatalkan permintaannya: "saya bisa hadir di jadwal semula".
     *
     * @return array{ok: bool, kode: int, pesan: string, status?: ?string}
     */
    public static function cabutPermintaan(int $subTesId, int $versi, array $pelaku): array
    {
        return DB::transaction(function () use ($subTesId, $versi, $pelaku) {
            $sub = self::konteks($subTesId, true);
            if ($galat = self::galatTerbuka($sub, $versi)) {
                return ['ok' => false, 'kode' => 409, 'pesan' => $galat];
            }

            $p = self::permintaanTerbuka($subTesId);
            if (! $p || (int) $p->Versi !== $versi || $sub->Konfirmasi_Status !== self::JADWAL_LAIN) {
                return ['ok' => false, 'kode' => 409, 'pesan' => 'Tidak ada permintaan yang bisa dibatalkan — tim mungkin sudah menjawabnya.', 'status' => $sub->Konfirmasi_Status];
            }

            $crm = self::crm($subTesId, $versi);
            if (! $crm || Carbon::parse($crm->Batas_Konfirmasi)->lte(now())) {
                return ['ok' => false, 'kode' => 422, 'pesan' => 'Batas konfirmasi sudah lewat — tim rekrutmen akan menghubungimu soal permintaan ini.', 'status' => $sub->Konfirmasi_Status];
            }

            // Membatalkan permintaan = MENGUBAH jawaban — ikut aturan komitmen.
            $aturan = self::aturanUbahUntuk($sub);
            if ($aturan['terkunci']) {
                return ['ok' => false, 'kode' => 422, 'pesan' => self::pesanKunci($aturan), 'status' => $sub->Konfirmasi_Status, 'terkunci' => true];
            }

            $now = now();
            $ubah = DB::table(self::T_MINTA)
                ->where('Id_Jadwal_Permintaan', $p->Id_Jadwal_Permintaan)
                ->where('Status', self::P_TERBUKA)
                ->update([
                    'Status' => self::P_DICABUT,
                    'Diproses_At' => $now,
                    'Diproses_By' => mb_substr((string) ($pelaku['nama'] ?? $sub->KandidatNama), 0, 200),
                    'Updated_At' => $now,
                ]);
            if (! $ubah) {
                return ['ok' => false, 'kode' => 409, 'pesan' => 'Permintaan baru saja diproses tim. Muat ulang halaman.'];
            }

            DB::table(self::T_TES)->where('Id_Lamaran_Tahap_Tes', $subTesId)->update(['Konfirmasi_Status' => self::AKAN_HADIR]);
            DB::table(self::T_CRM)->where('Lamaran_Tahap_Tes_Id', $subTesId)->where('Versi', $versi)
                ->update(['Status_Konfirmasi' => self::AKAN_HADIR, 'Dijawab_At' => $now, 'Updated_At' => $now]);

            $ids = [self::tulisJejak($subTesId, $versi, self::A_PROSES, $pelaku, [
                'Status_Dari' => self::JADWAL_LAIN,
                'Status_Ke' => self::AKAN_HADIR,
                'Permintaan_Id' => (int) $p->Id_Jadwal_Permintaan,
                'Undangan' => 'ANTRE',
                // Tanda terima "akan hadir" — kandidat memegang bukti tertulis
                // bahwa ia kembali ke jadwal semula.
                'Data_Json' => ['surel' => self::S_TANDA_TERIMA, 'jenis' => self::AKAN_HADIR, 'hasil' => self::P_DICABUT] + self::jejakPelaku($pelaku),
            ])];

            // Tim menunggu permintaan ini di Agenda — kabari bahwa kandidat
            // kembali ke jadwal semula (bila master "akan hadir" mengabari tim).
            if ((self::master()->get(self::AKAN_HADIR)->Flag_Kabar_Tim ?? 'T') === 'Y') {
                $ids[] = self::tulisJejak($subTesId, $versi, self::A_KABAR_TIM, ['jenis' => self::SISTEM, 'kanal' => self::K_SISTEM], [
                    'Undangan' => 'ANTRE',
                    'Data_Json' => ['surel' => self::S_KABAR_TIM, 'jenis' => self::AKAN_HADIR, 'dicabut' => true],
                ]);
            }
            self::antrekan($ids);

            return [
                'ok' => true,
                'kode' => 200,
                'pesan' => 'Permintaan dibatalkan. Kehadiranmu di jadwal semula sudah kami catat. '.self::teksUbah(self::aturanUbahUntuk($sub), true),
                'status' => self::AKAN_HADIR,
            ];
        });
    }

    // ════════════════════════════════════════════════════════════════════════
    //  PROSES PERMINTAAN OLEH TIM
    // ════════════════════════════════════════════════════════════════════════

    /**
     * Setujui satu usulan kandidat → versi baru, langsung AKAN_HADIR.
     *
     * Hanya WAKTU yang berubah; bentuk, tempat, tautan, instruksi tetap.
     * Durasinya mengikuti jadwal semula.
     *
     * @return array{ok: bool, kode: int, pesan: string}
     */
    public static function setujui(int $permintaanId, int $urutanUsulan, string $jamMulai, ?string $catatanTim, string $oleh, ?int $olehId): array
    {
        return self::prosesGeser($permintaanId, function (object $p, object $sub) use ($urutanUsulan, $jamMulai) {
            $usulan = json_decode((string) $p->Usulan_Json, true) ?: [];
            $u = $usulan[$urutanUsulan] ?? null;
            if (! $u) {
                return [null, 'Usulan yang dipilih tidak ada.'];
            }
            if (! preg_match('/^\d{2}:\d{2}$/', $jamMulai)) {
                return [null, 'Jam mulai tidak dikenali.'];
            }
            if ($jamMulai < $u['mulai'] || $jamMulai >= $u['selesai']) {
                return [null, 'Jam mulai harus di dalam rentang usulan ('.$u['mulai'].'–'.$u['selesai'].').'];
            }
            // Usulan yang terlanjur lewat (permintaan lama baru diproses) tidak
            // bisa disetujui — jadwal tidak boleh mundur ke masa lalu (masukan
            // user 2 Okt 2026). Batasnya sama dengan "Tawarkan waktu lain".
            $m = Carbon::parse($u['tanggal'].' '.$jamMulai);
            if ($m->lte(now()->addHour())) {
                return [null, $m->lte(now())
                    ? 'Waktu usulan itu sudah lewat — pilih usulan lain, atau tawarkan waktu lain.'
                    : 'Jam mulai paling cepat satu jam dari sekarang — pilih jam yang lebih lambat.'];
            }

            return [$m, null];
        }, self::P_DISETUJUI, self::AKAN_HADIR, $catatanTim, $oleh, $olehId, $urutanUsulan);
    }

    /**
     * Tawarkan waktu lain dari tim → versi baru, MENUNGGU; kandidat bisa
     * menjawab sampai waktu baru itu dimulai.
     *
     * @return array{ok: bool, kode: int, pesan: string}
     */
    public static function tawarkan(int $permintaanId, string $mulai, ?string $catatanTim, string $oleh, ?int $olehId): array
    {
        return self::prosesGeser($permintaanId, function () use ($mulai) {
            try {
                $m = Carbon::parse($mulai)->setSecond(0);
            } catch (\Throwable $e) {
                return [null, 'Waktu baru tidak dikenali.'];
            }
            if ($m->lte(now()->addHour())) {
                return [null, 'Waktu baru harus paling cepat satu jam dari sekarang.'];
            }

            return [$m, null];
        }, self::P_WAKTU_LAIN, self::MENUNGGU, $catatanTim, $oleh, $olehId, null);
    }

    /**
     * Inti setujui / tawarkan: geser waktu jadwal, naikkan versi, tutup
     * permintaan, catat jejak versi + surel jadwal baru — satu transaksi.
     */
    private static function prosesGeser(
        int $permintaanId,
        \Closure $waktuBaru,
        string $hasilPermintaan,
        string $statusBaru,
        ?string $catatanTim,
        string $oleh,
        ?int $olehId,
        ?int $usulanDipilih,
    ): array {
        return DB::transaction(function () use ($permintaanId, $waktuBaru, $hasilPermintaan, $statusBaru, $catatanTim, $oleh, $olehId, $usulanDipilih) {
            $p = DB::table(self::T_MINTA)->where('Id_Jadwal_Permintaan', $permintaanId)->lockForUpdate()->first();
            if (! $p) {
                return ['ok' => false, 'kode' => 404, 'pesan' => 'Permintaan tidak ditemukan.'];
            }
            if ($p->Status !== self::P_TERBUKA) {
                return ['ok' => false, 'kode' => 409, 'pesan' => self::sudahDiproses($p)];
            }

            $sub = self::konteks((int) $p->Lamaran_Tahap_Tes_Id, true);
            if ($galat = self::galatTerbuka($sub, (int) $p->Versi)) {
                return ['ok' => false, 'kode' => 409, 'pesan' => $galat];
            }

            [$mulai, $galat] = $waktuBaru($p, $sub);
            if ($galat) {
                return ['ok' => false, 'kode' => 422, 'pesan' => $galat];
            }

            $durasi = ! empty($sub->Jadwal_Selesai)
                ? max(5, Carbon::parse($sub->Jadwal_Mulai)->diffInMinutes(Carbon::parse($sub->Jadwal_Selesai)))
                : null;
            $selesai = $durasi ? $mulai->copy()->addMinutes($durasi) : null;

            $sebelum = DB::table(self::T_TES)->where('Id_Lamaran_Tahap_Tes', $sub->Id_Lamaran_Tahap_Tes)->first();
            $now = now();
            DB::table(self::T_TES)->where('Id_Lamaran_Tahap_Tes', $sub->Id_Lamaran_Tahap_Tes)->update([
                'Jadwal_Mulai' => $mulai->format('Y-m-d H:i:s'),
                'Jadwal_Selesai' => $selesai?->format('Y-m-d H:i:s'),
                'Jadwal_Versi' => DB::raw('ISNULL(Jadwal_Versi, 0) + 1'),
                'Jadwal_At' => $now,
                'Jadwal_By' => mb_substr($oleh, 0, 150),
                'Jadwal_By_Id' => $olehId,
                'Updated_At' => $now,
                'Updated_By' => mb_substr($oleh, 0, 150),
            ]);
            $versiBaru = (int) DB::table(self::T_TES)->where('Id_Lamaran_Tahap_Tes', $sub->Id_Lamaran_Tahap_Tes)->value('Jadwal_Versi');

            $ubah = DB::table(self::T_MINTA)
                ->where('Id_Jadwal_Permintaan', $permintaanId)
                ->where('Status', self::P_TERBUKA)
                ->update([
                    'Status' => $hasilPermintaan,
                    'Usulan_Dipilih' => $usulanDipilih,
                    'Versi_Hasil' => $versiBaru,
                    'Catatan_Tim' => self::rapikanCatatan($catatanTim, 1000),
                    'Diproses_At' => $now,
                    'Diproses_By' => mb_substr($oleh, 0, 200),
                    'Diproses_By_Id' => $olehId,
                    'Updated_At' => $now,
                    'Updated_By' => mb_substr($oleh, 0, 200),
                ]);
            if (! $ubah) {
                throw new \DomainException('Permintaan baru saja diproses rekan lain.');
            }

            self::terbitkan((int) $sub->Id_Lamaran_Tahap_Tes, $sebelum, $oleh, $olehId, [
                'status' => $statusBaru,
                // Permintaannya sudah ditutup di atas dengan hasil yang tepat.
                'permintaanHasil' => $hasilPermintaan,
            ]);

            $alasan = $hasilPermintaan === self::P_DISETUJUI
                ? "Permintaan jadwal lain #{$permintaanId} disetujui"
                : "Permintaan jadwal lain #{$permintaanId}: ditawarkan waktu lain";
            $jejakVersi = JejakJadwal::catat((int) $sub->Id_Lamaran_Tahap_Tes, JejakJadwal::UBAH, $alasan, 'ANTRE', [
                'Permintaan_Id' => $permintaanId,
                'Status_Dari' => self::JADWAL_LAIN,
                'Status_Ke' => $statusBaru,
                'Data_Json' => [
                    'surel' => self::S_JADWAL_BARU,
                    'hasil' => $hasilPermintaan,
                    'catatanTim' => self::rapikanCatatan($catatanTim, 1000),
                ],
            ]);
            if (! $jejakVersi) {
                throw new \RuntimeException('Jejak versi jadwal gagal ditulis.');
            }
            self::antrekan([$jejakVersi]);

            DB::table(self::T_TES)->where('Id_Lamaran_Tahap_Tes', $sub->Id_Lamaran_Tahap_Tes)->update(['Jadwal_Email_At' => $now]);

            return [
                'ok' => true,
                'kode' => 200,
                'pesan' => $hasilPermintaan === self::P_DISETUJUI
                    ? 'Usulan disetujui. Jadwal baru dikirim ke kandidat dan langsung tercatat akan hadir.'
                    : 'Waktu baru ditawarkan. Kandidat diminta mengonfirmasi ulang.',
            ];
        });
    }

    /**
     * Tolak permintaan — jadwal semula tetap, kandidat diminta menjawab lagi
     * (masih bisa sampai jadwal itu dimulai).
     *
     * @return array{ok: bool, kode: int, pesan: string}
     */
    public static function tolak(int $permintaanId, string $catatanTim, string $oleh, ?int $olehId): array
    {
        $catatanTim = self::rapikanCatatan($catatanTim, 1000);
        if ($catatanTim === null) {
            return ['ok' => false, 'kode' => 422, 'pesan' => 'Tuliskan catatan untuk kandidat — kenapa jadwal semula tetap.'];
        }

        return DB::transaction(function () use ($permintaanId, $catatanTim, $oleh, $olehId) {
            $p = DB::table(self::T_MINTA)->where('Id_Jadwal_Permintaan', $permintaanId)->lockForUpdate()->first();
            if (! $p) {
                return ['ok' => false, 'kode' => 404, 'pesan' => 'Permintaan tidak ditemukan.'];
            }
            if ($p->Status !== self::P_TERBUKA) {
                return ['ok' => false, 'kode' => 409, 'pesan' => self::sudahDiproses($p)];
            }

            $sub = self::konteks((int) $p->Lamaran_Tahap_Tes_Id, true);
            $versi = (int) $p->Versi;
            if ($galat = self::galatTerbuka($sub, $versi)) {
                return ['ok' => false, 'kode' => 409, 'pesan' => $galat];
            }

            // Jadwalnya tidak berubah, jadi batasnya pun tetap: waktu mulainya.
            $batas = self::batasDari((string) $sub->Jadwal_Mulai);

            $now = now();
            DB::table(self::T_MINTA)->where('Id_Jadwal_Permintaan', $permintaanId)->update([
                'Status' => self::P_DITOLAK,
                'Catatan_Tim' => $catatanTim,
                'Diproses_At' => $now,
                'Diproses_By' => mb_substr($oleh, 0, 200),
                'Diproses_By_Id' => $olehId,
                'Updated_At' => $now,
                'Updated_By' => mb_substr($oleh, 0, 200),
            ]);
            DB::table(self::T_TES)->where('Id_Lamaran_Tahap_Tes', $sub->Id_Lamaran_Tahap_Tes)->update(['Konfirmasi_Status' => self::MENUNGGU]);
            DB::table(self::T_CRM)->where('Lamaran_Tahap_Tes_Id', $sub->Id_Lamaran_Tahap_Tes)->where('Versi', $versi)->update([
                'Status_Konfirmasi' => self::MENUNGGU,
                'Batas_Konfirmasi' => $batas->format('Y-m-d H:i:s'),
                'Dijawab_At' => null,
                'Updated_At' => $now,
            ]);

            $ids = [self::tulisJejak((int) $sub->Id_Lamaran_Tahap_Tes, $versi, self::A_PROSES, self::pelakuTim($oleh, $olehId), [
                'Status_Dari' => self::JADWAL_LAIN,
                'Status_Ke' => self::MENUNGGU,
                'Permintaan_Id' => $permintaanId,
                'Alasan' => $catatanTim,
                'Undangan' => 'ANTRE',
                'Data_Json' => ['surel' => self::S_DITOLAK, 'hasil' => self::P_DITOLAK, 'catatanTim' => $catatanTim, 'batas' => $batas->format('Y-m-d H:i:s')],
            ])];
            self::antrekan($ids);

            return ['ok' => true, 'kode' => 200, 'pesan' => 'Permintaan ditolak. Kandidat diberi tahu jadwal semula tetap berlaku.'];
        });
    }

    /** Kalimat "sudah diproses" yang menyebut siapa dan kapan. */
    private static function sudahDiproses(object $p): string
    {
        $label = [
            self::P_DISETUJUI => 'disetujui',
            self::P_WAKTU_LAIN => 'ditawari waktu lain',
            self::P_DITOLAK => 'ditolak',
            self::P_DICABUT => 'dicabut kandidat',
            self::P_DITUTUP => 'ditutup sistem',
        ][$p->Status] ?? strtolower((string) $p->Status);

        return 'Permintaan ini sudah '.$label
            .($p->Diproses_By ? ' oleh '.$p->Diproses_By : '')
            .($p->Diproses_At ? ' pada '.Carbon::parse($p->Diproses_At)->format('d M H.i') : '').'.';
    }

    // ════════════════════════════════════════════════════════════════════════
    //  PENGINGAT MANUAL & KIRIM ULANG
    // ════════════════════════════════════════════════════════════════════════

    /**
     * Kirim pengingat "mohon segera konfirmasi" — MANUAL oleh tim.
     *
     * Dihitung di CRM: Jumlah_Pengingat (berapa pengingat) DAN Jumlah_Kirim_Manual
     * (berapa yang dikirim tangan admin), ditandai Terakhir_Kirim_Oleh. Surelnya
     * diantrekan berselang beberapa detik — puluhan surel serentak dari satu
     * alamat dibaca penyedia email sebagai pengiriman massal mendadak.
     *
     * @param  array<int>  $subTesIds
     * @return array{berhasil: array, gagal: array}
     */
    public static function kirimPengingat(array $subTesIds, string $oleh, ?int $olehId): array
    {
        $berhasil = [];
        $gagal = [];
        $urut = 0;
        $jeda = max(0, (int) config('konfirmasi.cicil_detik', 2));
        $jedaKirim = max(1, (int) config('konfirmasi.jeda_kirim_menit', 10));
        $jarakSopan = max(1, (int) config('konfirmasi.jarak_sopan_jam', 16));

        foreach (array_values(array_unique(array_map('intval', $subTesIds))) as $id) {
            $hasil = DB::transaction(function () use ($id, $oleh, $olehId, $jedaKirim, $jarakSopan, &$urut, $jeda) {
                $sub = self::konteks($id, true);
                $nama = $sub->KandidatNama ?? ('#'.$id);
                $versi = (int) ($sub->Jadwal_Versi ?? 0);
                if ($galat = self::galatTerbuka($sub, $versi)) {
                    return [false, $nama, $galat, null];
                }

                $crm = self::crm($id, $versi);
                if (! $crm) {
                    return [false, $nama, 'Undangan ini belum tercatat di daftar konfirmasi — simpan ulang jadwalnya.', null];
                }
                $efektif = self::statusEfektif($sub->Konfirmasi_Status, $crm->Batas_Konfirmasi);
                if ($efektif !== self::MENUNGGU) {
                    return [false, $nama, $efektif === self::TANPA_JAWABAN
                        ? 'Batas konfirmasi sudah lewat — perpanjang dulu supaya kandidat bisa menjawab.'
                        : 'Kandidat sudah menjawab ('.(self::master()->get($efektif)->Label_Tim ?? $efektif).').', null];
                }

                $menit = $crm->Terakhir_Kirim_At ? (int) floor(Carbon::parse($crm->Terakhir_Kirim_At)->diffInMinutes(now())) : 999;
                if ($menit < $jedaKirim) {
                    return [false, $nama, "Email terakhir baru {$menit} menit lalu — tunggu {$jedaKirim} menit.", null];
                }
                $peringatan = $crm->Terakhir_Kirim_At && $menit < $jarakSopan * 60
                    ? 'Kandidat sudah menerima email '.UndanganJadwal::sisaTeks(Carbon::parse($crm->Terakhir_Kirim_At), now()).' sebelumnya.'
                    : null;
                $peringatan = $peringatan ? str_replace(' lagi', '', $peringatan) : null;

                $now = now();
                DB::table(self::T_CRM)->where('Id_CRM_Konfirmasi', $crm->Id_CRM_Konfirmasi)->update([
                    'Jumlah_Pengingat' => DB::raw('CASE WHEN Jumlah_Pengingat < 255 THEN Jumlah_Pengingat + 1 ELSE 255 END'),
                    'Jumlah_Kirim_Manual' => DB::raw('CASE WHEN Jumlah_Kirim_Manual < 255 THEN Jumlah_Kirim_Manual + 1 ELSE 255 END'),
                    'Terakhir_Kirim_At' => $now,
                    'Terakhir_Kirim_Jenis' => 'PENGINGAT',
                    'Terakhir_Kirim_Oleh' => mb_substr($oleh, 0, 200),
                    'Updated_At' => $now,
                ]);

                $jejakId = self::tulisJejak($id, $versi, self::A_INGAT_JAWAB, self::pelakuTim($oleh, $olehId), [
                    'Undangan' => 'ANTRE',
                    'Data_Json' => ['surel' => self::S_PENGINGAT, 'manual' => true, 'ke' => ((int) $crm->Jumlah_Pengingat) + 1],
                ]);
                self::antrekan([$jejakId], $urut * $jeda);
                $urut++;

                return [true, $nama, null, $peringatan];
            });

            [$ok, $nama, $galat, $peringatan] = $hasil;
            if ($ok) {
                $berhasil[] = ['nama' => $nama, 'peringatan' => $peringatan];
            } else {
                $gagal[] = ['nama' => $nama, 'alasan' => $galat];
            }
        }

        Log::channel('web_career')->info('[KONFIRMASI] pengingat manual: '.count($berhasil).' diantrekan, '.count($gagal).' dilewati — oleh '.$oleh);

        return ['berhasil' => $berhasil, 'gagal' => $gagal];
    }

    /**
     * Kirim ulang undangan oleh admin (tombol lama "Kirim ulang") — dihitung
     * & ditandai di CRM. Surelnya sendiri tetap dikirim jalur lama.
     */
    public static function tandaiKirimUlang(int $subTesId, string $oleh): void
    {
        if (! self::siap()) {
            return;
        }

        try {
            $versi = DB::table(self::T_TES)->where('Id_Lamaran_Tahap_Tes', $subTesId)->value('Jadwal_Versi');
            if ($versi === null) {
                return;
            }
            $now = now();
            DB::table(self::T_CRM)
                ->where('Lamaran_Tahap_Tes_Id', $subTesId)->where('Versi', (int) $versi)
                ->update([
                    'Jumlah_Kirim_Manual' => DB::raw('CASE WHEN Jumlah_Kirim_Manual < 255 THEN Jumlah_Kirim_Manual + 1 ELSE 255 END'),
                    'Terakhir_Kirim_At' => $now,
                    'Terakhir_Kirim_Jenis' => 'KIRIM_ULANG',
                    'Terakhir_Kirim_Oleh' => mb_substr($oleh ?: 'ADMIN', 0, 200),
                    'Updated_At' => $now,
                ]);
        } catch (\Throwable $e) {
            Log::channel('web_career')->warning("[KONFIRMASI] hitungan kirim ulang #{$subTesId} gagal: ".$e->getMessage());
        }
    }

    // ════════════════════════════════════════════════════════════════════════
    //  TUNDA JADWAL
    // ════════════════════════════════════════════════════════════════════════

    /**
     * Rapikan & periksa isian penundaan dari tim.
     *
     * Alasan dari master (jenis TUNDA) — namanya dibaca kandidat. Alasan ber-
     * Flag_Butuh_Catatan (Lainnya) wajib dijelaskan di pesan. Perkiraan tanggal
     * pengganti BOLEH kosong: "akan dikabarkan" adalah jawaban yang jujur bila
     * tim memang belum tahu.
     *
     * @param  array{alasan?: ?string, perkiraan?: ?string, pesan?: ?string}  $isian
     * @return array{0: ?array{alasanKode: string, alasan: string, butuhCatatan: bool, perkiraan: ?string, pesan: ?string}, 1: ?string}
     */
    public static function periksaTunda(array $isian): array
    {
        $kode = trim((string) ($isian['alasan'] ?? ''));
        $alasan = self::alasan('TUNDA')->get($kode);
        if (! $alasan) {
            return [null, 'Pilih alasan penundaan.'];
        }

        $butuhCatatan = ($alasan->Flag_Butuh_Catatan ?? 'T') === 'Y';
        $pesan = self::rapikanCatatan($isian['pesan'] ?? null, 1000);
        if ($butuhCatatan && $pesan === null) {
            return [null, 'Alasan "'.$alasan->Nama.'" perlu dijelaskan di pesan untuk kandidat (minimal 5 huruf).'];
        }

        $perkiraan = null;
        if (! empty($isian['perkiraan'])) {
            try {
                $tgl = Carbon::createFromFormat('Y-m-d', (string) $isian['perkiraan'])->startOfDay();
            } catch (\Throwable) {
                return [null, 'Tanggal perkiraan tidak dikenali.'];
            }
            $hariMaks = max(7, (int) config('konfirmasi.tunda_hari_maks', 180));
            if ($tgl->lte(now()->startOfDay()) || $tgl->gt(now()->addDays($hariMaks)->startOfDay())) {
                return [null, "Perkiraan jadwal pengganti paling cepat besok dan paling jauh {$hariMaks} hari lagi."];
            }
            $perkiraan = $tgl->format('Y-m-d');
        }

        return [[
            'alasanKode' => $kode,
            'alasan' => (string) $alasan->Nama,
            'butuhCatatan' => $butuhCatatan,
            'perkiraan' => $perkiraan,
            'pesan' => $pesan,
        ], null];
    }

    /** Kalimat alasan untuk kandidat: "Lainnya" diwakili penjelasan timnya. */
    public static function alasanTundaTeks(array $info): string
    {
        return ! empty($info['butuhCatatan']) && ! empty($info['pesan']) ? (string) $info['pesan'] : (string) ($info['alasan'] ?? '');
    }

    /**
     * Tunda jadwal: kosongkan jadwalnya, status DITUNDA, kabari kandidat —
     * alasan, perkiraan jadwal pengganti (atau "akan dikabarkan"), dan pesan
     * tim. Versi tetap naik — tautan lama mati.
     *
     * @param  array{alasan?: ?string, perkiraan?: ?string, pesan?: ?string}  $isian
     * @return array{ok: bool, kode: int, pesan: string}
     */
    public static function tunda(int $subTesId, array $isian, string $oleh, ?int $olehId): array
    {
        [$info, $galat] = self::periksaTunda($isian);
        if ($galat) {
            return ['ok' => false, 'kode' => 422, 'pesan' => $galat];
        }

        return DB::transaction(function () use ($subTesId, $info, $oleh, $olehId) {
            $sub = self::konteks($subTesId, true);
            $versi = (int) ($sub->Jadwal_Versi ?? 0);
            if ($galat = self::galatTerbuka($sub, $versi, false)) {
                return ['ok' => false, 'kode' => 409, 'pesan' => $galat];
            }
            $alasan = self::alasanTundaTeks($info);

            // Ringkasan jadwal yang ditunda — surelnya dirakit dari sini karena
            // baris jadwalnya sesaat lagi dikosongkan.
            $ringkas = [
                'aktivitas' => $sub->Label,
                'posisi' => $sub->Posisi ?: $sub->ProgramNama,
                'waktu_teks' => UndanganJadwal::waktuTeks($sub->Jadwal_Mulai, $sub->Jadwal_Selesai),
                'kepada' => $sub->KandidatEmail,
                'nama' => $sub->KandidatNama,
                'kode' => $sub->LamaranKode,
                'tahap' => $sub->TahapLabel,
            ];

            $now = now();
            $kosong = [
                'Jadwal_Mode' => null, 'Jadwal_Mulai' => null, 'Jadwal_Selesai' => null, 'Jadwal_Link' => null,
                'Jadwal_Lokasi' => null, 'Jadwal_Lokasi_Id' => null, 'Jadwal_Lokasi_Nama' => null,
                'Jadwal_Lokasi_Alamat' => null, 'Jadwal_Kontak' => null, 'Jadwal_Catatan' => null,
                'Jadwal_Email_At' => null,
                'Jadwal_At' => $now, 'Jadwal_By' => mb_substr($oleh, 0, 150), 'Jadwal_By_Id' => $olehId,
                'Jadwal_Versi' => DB::raw('ISNULL(Jadwal_Versi, 0) + 1'),
                // Penanda "ditunda, pengganti menyusul" — dibaca portal, panel
                // tim, dan Agenda sampai jadwal pengganti terbit (terbitkan()).
                'Konfirmasi_Status' => self::DITUNDA,
                'Status' => 'BELUM',
                'Updated_At' => $now, 'Updated_By' => mb_substr($oleh, 0, 150),
            ];
            foreach (['Jadwal_Catatan_Html', 'Jadwal_Lokasi_Html', 'Jadwal_Maps_Url', 'Jadwal_Surat_Json', 'Jadwal_Batas_Unggah'] as $kol) {
                if (Skema::adaKolom(self::T_TES, $kol)) {
                    $kosong[$kol] = null;
                }
            }
            DB::table(self::T_TES)->where('Id_Lamaran_Tahap_Tes', $subTesId)->update($kosong);
            $versiBaru = (int) DB::table(self::T_TES)->where('Id_Lamaran_Tahap_Tes', $subTesId)->value('Jadwal_Versi');

            DB::table(self::T_CRM)->where('Lamaran_Tahap_Tes_Id', $subTesId)->where('Selesai', 'T')
                ->update(['Selesai' => 'Y', 'Selesai_Alasan' => 'DITUNDA', 'Selesai_At' => $now, 'Updated_At' => $now]);
            self::tutupPermintaan($subTesId, self::P_DITUTUP, $oleh, $olehId, null, 'Jadwal ditunda: '.$alasan);

            $jejakId = self::tulisJejak($subTesId, $versiBaru, self::A_TUNDA, self::pelakuTim($oleh, $olehId), [
                'Status_Dari' => $sub->Konfirmasi_Status,
                'Status_Ke' => self::DITUNDA,
                'Alasan' => $alasan,
                'Undangan' => JadwalPrivat::untuk(($sub->Tipe_Tahap_Kode ?? null) ?: $sub->TahapTipe) ? 'PRIVAT' : 'ANTRE',
                // Salinan jadwal yang ditunda — "Mulai/Mode/Lokasi" jejak memang
                // untuk itu: versi ini adalah versi "tanpa jadwal".
                'Mode' => $sub->Jadwal_Mode,
                'Mulai' => $sub->Jadwal_Mulai,
                'Selesai' => $sub->Jadwal_Selesai,
                'Data_Json' => ['surel' => self::S_DITUNDA, 'alasan' => $alasan, 'tunda' => $info, 'ringkas' => $ringkas],
            ]);
            if (! JadwalPrivat::untuk(($sub->Tipe_Tahap_Kode ?? null) ?: $sub->TahapTipe)) {
                self::antrekan([$jejakId]);
            }

            return ['ok' => true, 'kode' => 200, 'pesan' => 'Jadwal ditunda. Kandidat dikabari lewat email dan Portal Kandidat.', 'status' => self::DITUNDA];
        });
    }

    /**
     * Perbarui info penundaan (alasan, perkiraan tanggal, pesan) selama jadwal
     * pengganti belum terbit — mis. dari "belum diketahui" menjadi "perkiraan
     * 3 Okt". $kabari = kirim surel kabar terbaru ke kandidat; portalnya
     * berubah saat itu juga, dikabari atau tidak.
     *
     * @param  array{alasan?: ?string, perkiraan?: ?string, pesan?: ?string}  $isian
     * @return array{ok: bool, kode: int, pesan: string}
     */
    public static function perbaruiTunda(int $subTesId, array $isian, bool $kabari, string $oleh, ?int $olehId): array
    {
        [$info, $galat] = self::periksaTunda($isian);
        if ($galat) {
            return ['ok' => false, 'kode' => 422, 'pesan' => $galat];
        }

        return DB::transaction(function () use ($subTesId, $info, $kabari, $oleh, $olehId) {
            $sub = self::konteks($subTesId, true);
            if (! $sub || ($sub->Konfirmasi_Status ?? null) !== self::DITUNDA || ! empty($sub->Jadwal_Mulai)) {
                return ['ok' => false, 'kode' => 409, 'pesan' => 'Aktivitas ini sudah tidak berstatus ditunda — muat ulang.'];
            }
            if (($sub->StatusLamaran ?? '') !== 'BERJALAN' || ($sub->Flag_Selesai ?? 'T') === 'Y') {
                return ['ok' => false, 'kode' => 409, 'pesan' => 'Proses seleksi untuk aktivitas ini sudah selesai.'];
            }

            $lama = self::infoTunda($subTesId);
            $sama = fn (string $k) => ($lama[$k] ?? null) === $info[$k];
            if ($lama && $sama('alasanKode') && $sama('perkiraan') && $sama('pesan')) {
                return ['ok' => false, 'kode' => 422, 'pesan' => 'Belum ada yang berubah dari info sebelumnya.'];
            }

            $kirim = $kabari && ! empty($sub->KandidatEmail)
                && ! JadwalPrivat::untuk(($sub->Tipe_Tahap_Kode ?? null) ?: $sub->TahapTipe);

            $jejakId = self::tulisJejak($subTesId, (int) $sub->Jadwal_Versi, self::A_TUNDA_INFO, self::pelakuTim($oleh, $olehId), [
                'Alasan' => self::alasanTundaTeks($info),
                'Undangan' => $kirim ? 'ANTRE' : null,
                'Data_Json' => [
                    'surel' => $kirim ? self::S_TUNDA_INFO : null,
                    'tunda' => $info,
                    'sebelum' => $lama ? array_intersect_key($lama, array_flip(['alasanKode', 'alasan', 'butuhCatatan', 'perkiraan', 'pesan'])) : null,
                    'ringkas' => $lama['ringkas'] ?? null,
                ],
            ]);
            if ($kirim) {
                self::antrekan([$jejakId]);
            }

            return [
                'ok' => true,
                'kode' => 200,
                'pesan' => $kirim ? 'Info penundaan diperbarui — kandidat dikabari lewat email.' : 'Info penundaan diperbarui (tanpa email).',
                'status' => self::DITUNDA,
            ];
        });
    }

    /** Info penundaan yang BERLAKU untuk satu aktivitas (lihat infoTundaBanyak). */
    public static function infoTunda(int $subTesId): ?array
    {
        return self::infoTundaBanyak([$subTesId])[$subTesId] ?? null;
    }

    /**
     * Info penundaan terakhir per aktivitas: penundaan yang berlaku dimulai di
     * baris TUNDA terakhir; isinya dari baris terakhir (TUNDA atau TUNDA_INFO).
     * Pemanggil yang memastikan aktivitasnya MASIH berstatus DITUNDA.
     *
     * @param  array<int>  $subTesIds
     * @return array<int, array>
     */
    public static function infoTundaBanyak(array $subTesIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $subTesIds))));
        if (! $ids || ! self::siap()) {
            return [];
        }

        $out = [];
        foreach (array_chunk($ids, 500) as $potong) {
            $kelompok = DB::table(JejakJadwal::TABEL)
                ->whereIn('Lamaran_Tahap_Tes_Id', $potong)
                ->whereIn('Aksi', [self::A_TUNDA, self::A_TUNDA_INFO])
                ->orderByDesc('Id_Jadwal_Jejak')
                ->get(['Id_Jadwal_Jejak', 'Lamaran_Tahap_Tes_Id', 'Aksi', 'Alasan', 'Data_Json', 'Created_At', 'Created_By'])
                ->groupBy('Lamaran_Tahap_Tes_Id');

            foreach ($kelompok as $id => $baris) {
                $awal = $baris->firstWhere('Aksi', self::A_TUNDA);
                if (! $awal) {
                    continue;
                }
                $kini = $baris->first();
                $d = json_decode((string) $kini->Data_Json, true) ?: [];
                $dAwal = json_decode((string) $awal->Data_Json, true) ?: [];
                // Baris lama (sebelum fitur ini) hanya punya teks alasan.
                $t = $d['tunda'] ?? ['alasan' => $kini->Alasan, 'butuhCatatan' => false, 'perkiraan' => null, 'pesan' => null];

                $out[(int) $id] = [
                    'alasanKode' => $t['alasanKode'] ?? null,
                    'alasan' => $t['alasan'] ?? null,
                    'alasanTeks' => self::alasanTundaTeks($t),
                    'butuhCatatan' => (bool) ($t['butuhCatatan'] ?? false),
                    'perkiraan' => $t['perkiraan'] ?? null,
                    'perkiraanTeks' => ! empty($t['perkiraan']) ? self::tanggalTeks($t['perkiraan']) : null,
                    'pesan' => $t['pesan'] ?? null,
                    'ringkas' => $dAwal['ringkas'] ?? null,
                    'jadwalSemula' => $dAwal['ringkas']['waktu_teks'] ?? null,
                    'ditundaAt' => (string) $awal->Created_At,
                    'ditundaOleh' => $awal->Created_By,
                    'diperbaruiAt' => $kini->Aksi === self::A_TUNDA_INFO ? (string) $kini->Created_At : null,
                    'diperbaruiOleh' => $kini->Aksi === self::A_TUNDA_INFO ? $kini->Created_By : null,
                    'jumlahPembaruan' => $baris->filter(fn ($b) => $b->Aksi === self::A_TUNDA_INFO && (int) $b->Id_Jadwal_Jejak > (int) $awal->Id_Jadwal_Jejak)->count(),
                ];
            }
        }

        return $out;
    }

    /** Info penundaan untuk KANDIDAT — tanpa nama akun tim. */
    public static function tundaUntukKandidat(?array $info): ?array
    {
        if (! $info) {
            return null;
        }

        return [
            'alasan' => $info['alasanTeks'] ?: null,
            'perkiraan' => $info['perkiraan'],
            'perkiraanTeks' => $info['perkiraanTeks'],
            // Pesan "Lainnya" sudah menjadi alasannya — jangan diulang.
            'pesan' => $info['butuhCatatan'] ? null : $info['pesan'],
            'jadwalSemula' => $info['jadwalSemula'],
            'ditundaTeks' => UndanganJadwal::teksBatas($info['ditundaAt']),
            'diperbaruiTeks' => $info['diperbaruiAt'] ? UndanganJadwal::teksBatas($info['diperbaruiAt']) : null,
        ];
    }

    /**
     * Jadwal yang berlaku sekarang adalah PENGGANTI dari penundaan? Ya bila
     * versi tepat sebelumnya adalah versi "tanpa jadwal" milik baris TUNDA.
     */
    public static function penggantiTunda(object $sub): bool
    {
        $versi = (int) ($sub->Jadwal_Versi ?? 0);
        if ($versi < 2 || empty($sub->Jadwal_Mulai) || ! self::siap()) {
            return false;
        }

        return DB::table(JejakJadwal::TABEL)
            ->where('Lamaran_Tahap_Tes_Id', (int) $sub->Id_Lamaran_Tahap_Tes)
            ->where('Versi', $versi - 1)
            ->where('Aksi', self::A_TUNDA)
            ->exists();
    }

    /** "2026-10-03" → "Sabtu, 03 Oktober 2026" (tanpa jam). */
    public static function tanggalTeks(string $tanggal): string
    {
        return UndanganJadwal::teksBatas(Carbon::parse($tanggal)->setTime(23, 59));
    }

    // ════════════════════════════════════════════════════════════════════════
    //  PENUTUPAN — kehadiran dicatat, tahap diputus, tahap diulang
    // ════════════════════════════════════════════════════════════════════════

    /** Kehadiran dicatat tim: jejak KEHADIRAN + undangan selesai. Tidak menggagalkan pemanggil. */
    public static function catatKehadiran(int $subTesId, string $hadir, string $oleh, ?int $olehId, ?array $pelaku = null, array $data = []): void
    {
        if (! self::siap()) {
            return;
        }

        try {
            $versi = DB::table(self::T_TES)->where('Id_Lamaran_Tahap_Tes', $subTesId)->value('Jadwal_Versi');
            $now = now();
            self::tulisJejak($subTesId, $versi !== null ? (int) $versi : null, self::A_KEHADIRAN, $pelaku ?? self::pelakuTim($oleh, $olehId), [
                'Data_Json' => ['hadir' => $hadir] + $data,
            ]);
            DB::table(self::T_CRM)->where('Lamaran_Tahap_Tes_Id', $subTesId)->where('Selesai', 'T')
                ->update(['Selesai' => 'Y', 'Selesai_Alasan' => 'KEHADIRAN', 'Selesai_At' => $now, 'Updated_At' => $now]);
            self::tutupPermintaan($subTesId, self::P_DITUTUP, $oleh, $olehId, null, 'Kehadiran sudah dicatat.');
        } catch (\Throwable $e) {
            Log::channel('web_career')->warning("[KONFIRMASI] jejak kehadiran #{$subTesId} gagal: ".$e->getMessage());
        }
    }

    /**
     * Tahap diputus (lolos, gugur, mundur, Talent Pool): undangan & permintaan
     * yang masih terbuka di tahap itu tidak berarti apa-apa lagi.
     */
    public static function tutupTahap(int $lamaranTahapId, string $alasan = 'DITUTUP'): void
    {
        if (! self::siap()) {
            return;
        }

        try {
            self::tutupAktivitas(
                DB::table(self::T_TES)->where('Lamaran_Tahap_Id', $lamaranTahapId)->pluck('Id_Lamaran_Tahap_Tes')->all(),
                $alasan,
            );
        } catch (\Throwable $e) {
            Log::channel('web_career')->warning("[KONFIRMASI] penutupan tahap #{$lamaranTahapId} gagal: ".$e->getMessage());
        }
    }

    /**
     * Tutup undangan & permintaan terbuka sejumlah aktivitas (tahap diulang:
     * jadwalnya dilepas, jadi undangan lamanya tidak lagi berarti apa-apa).
     *
     * @param  array<int>  $subTesIds
     */
    public static function tutupAktivitas(array $subTesIds, string $alasan = 'DITUTUP'): void
    {
        $subTesIds = array_values(array_filter(array_map('intval', $subTesIds)));
        if (! $subTesIds || ! self::siap()) {
            return;
        }

        $now = now();
        foreach (array_chunk($subTesIds, 500) as $potong) {
            DB::table(self::T_CRM)->whereIn('Lamaran_Tahap_Tes_Id', $potong)->where('Selesai', 'T')
                ->update(['Selesai' => 'Y', 'Selesai_Alasan' => mb_substr($alasan, 0, 12), 'Selesai_At' => $now, 'Updated_At' => $now]);
            DB::table(self::T_MINTA)->whereIn('Lamaran_Tahap_Tes_Id', $potong)->where('Status', self::P_TERBUKA)
                ->update(['Status' => self::P_DITUTUP, 'Diproses_At' => $now, 'Diproses_By' => 'SISTEM', 'Updated_At' => $now]);
            // Penundaan yang penggantinya belum terbit tidak berarti apa-apa lagi
            // begitu tahapnya diputus / diulang — jangan tersisa di portal & Agenda.
            DB::table(self::T_TES)->whereIn('Id_Lamaran_Tahap_Tes', $potong)->where('Konfirmasi_Status', self::DITUNDA)
                ->whereNull('Jadwal_Mulai')
                ->update(['Konfirmasi_Status' => null]);
        }
    }

    /** Tutup permintaan terbuka satu aktivitas dengan hasil tertentu. */
    private static function tutupPermintaan(int $subTesId, string $hasil, string $oleh, ?int $olehId, ?int $versiHasil, ?string $catatan): void
    {
        $now = now();
        DB::table(self::T_MINTA)
            ->where('Lamaran_Tahap_Tes_Id', $subTesId)
            ->where('Status', self::P_TERBUKA)
            ->update([
                'Status' => $hasil,
                'Versi_Hasil' => $versiHasil,
                'Catatan_Tim' => $catatan !== null ? mb_substr($catatan, 0, 1000) : null,
                'Diproses_At' => $now,
                'Diproses_By' => mb_substr($oleh ?: 'SISTEM', 0, 200),
                'Diproses_By_Id' => $olehId,
                'Updated_At' => $now,
            ]);
    }

    // ════════════════════════════════════════════════════════════════════════
    //  JEJAK & ANTREAN
    // ════════════════════════════════════════════════════════════════════════

    /**
     * Tulis satu baris jejak kejadian (bukan salinan jadwal — kolom salinannya
     * dibiarkan kosong, kecuali pemanggil menyebutnya).
     */
    public static function tulisJejak(int $subTesId, ?int $versi, string $aksi, array $pelaku, array $kolom = []): int
    {
        $data = $kolom['Data_Json'] ?? null;
        unset($kolom['Data_Json']);

        $baris = $kolom + [
            'Lamaran_Tahap_Tes_Id' => $subTesId,
            'Aksi' => $aksi,
            'Versi' => $versi,
            'Pelaku' => $pelaku['jenis'] ?? self::SISTEM,
            'Kanal' => mb_substr((string) ($pelaku['kanal'] ?? self::K_SISTEM), 0, 10),
            'Data_Json' => $data !== null ? json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            'Created_At' => now(),
            'Created_By' => mb_substr((string) ($pelaku['nama'] ?? ($pelaku['jenis'] ?? 'SISTEM')), 0, 200),
            'Created_By_Id' => $pelaku['id'] ?? null,
        ];
        if (isset($baris['Alasan']) && $baris['Alasan'] !== null) {
            $baris['Alasan'] = mb_substr((string) $baris['Alasan'], 0, 500);
        }

        return (int) DB::table(JejakJadwal::TABEL)->insertGetId($baris, 'Id_Jadwal_Jejak');
    }

    /**
     * Antrekan surel untuk baris-baris jejak SESUDAH transaksi commit.
     * Surel tidak bisa ditarik kembali; barisnya bisa digulung balik.
     *
     * @param  array<int>  $jejakIds
     * @param  string|null  $antrean  null = antrean surel konfirmasi biasa;
     *                                pengingat otomatis memakai antreannya sendiri
     */
    public static function antrekan(array $jejakIds, int $tundaDetik = 0, ?string $antrean = null): void
    {
        $jejakIds = array_values(array_filter(array_map('intval', $jejakIds)));
        if (! $jejakIds) {
            return;
        }

        DB::afterCommit(function () use ($jejakIds, $tundaDetik, $antrean) {
            foreach ($jejakIds as $i => $id) {
                try {
                    $job = WcKonfirmasiEmailJob::dispatch($id, $antrean);
                    $detik = $tundaDetik + $i;
                    if ($detik > 0) {
                        $job->delay(now()->addSeconds($detik));
                    }
                } catch (\Throwable $e) {
                    // Barisnya tetap ANTRE — terlihat di Perlu Tindakan
                    // sebagai surel yang belum berangkat.
                    Log::channel('web_career')->error("[KONFIRMASI] surel jejak #{$id} gagal diantrekan: ".$e->getMessage());
                }
            }
        });
    }

    /** Tandai hasil kirim satu baris jejak. */
    public static function tandaiSurel(int $jejakId, string $status, ?string $galat = null): void
    {
        $baris = DB::table(JejakJadwal::TABEL)->where('Id_Jadwal_Jejak', $jejakId)->first(['Data_Json']);
        $data = json_decode((string) ($baris->Data_Json ?? ''), true) ?: [];
        $data[$status === 'TERKIRIM' ? 'terkirimAt' : 'gagalAt'] = now()->format('Y-m-d H:i:s');
        if ($galat) {
            $data['galat'] = mb_substr($galat, 0, 300);
        }

        DB::table(JejakJadwal::TABEL)->where('Id_Jadwal_Jejak', $jejakId)->update([
            'Undangan' => mb_substr($status, 0, 10),
            'Data_Json' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);

        // Pengingat otomatis: hasilnya ikut tercatat di buku pengingat, supaya
        // satu SELECT di tabel itu menjawab "siapa diingatkan, sampai atau tidak".
        if (! empty($data['otomatis'])) {
            PengingatKonfirmasi::tandai($jejakId, $status, $galat);
        }
    }

    /** Pelaku tim dari sesi. */
    public static function pelakuTim(?string $nama = null, ?int $id = null, string $kanal = self::K_LAYAR): array
    {
        return [
            'jenis' => self::TIM,
            'kanal' => $kanal,
            'nama' => $nama ?: (session('career_auth.nama') ?: 'ADMIN'),
            'id' => $id ?? (session('career_auth.id') ? (int) session('career_auth.id') : null),
        ];
    }

    /** Jejak perangkat & IP — IP disimpan sebagai hash bergaram (data pribadi). */
    private static function jejakPelaku(array $pelaku): array
    {
        $out = [];
        if (! empty($pelaku['ip'])) {
            $out['ipHash'] = substr(hash_hmac('sha256', (string) $pelaku['ip'], (string) config('app.key')), 0, 16);
        }
        if (! empty($pelaku['perangkat'])) {
            $out['perangkat'] = mb_substr((string) $pelaku['perangkat'], 0, 40);
        }

        return $out;
    }

    /** Catatan teks polos: dipangkas, minimal 5 huruf, maksimal $maks. Null bila kosong/terlalu pendek. */
    public static function rapikanCatatan(?string $teks, int $maks = self::CATATAN_MAKS): ?string
    {
        $teks = trim(preg_replace('/\s+/u', ' ', strip_tags((string) $teks)));

        return mb_strlen($teks) >= 5 ? mb_substr($teks, 0, $maks) : null;
    }

    /**
     * Gerbang umum: aktivitas masih bisa dikonfirmasi?
     *
     * @return ?string galat, atau null bila terbuka
     */
    public static function galatTerbuka(?object $sub, int $versi, bool $periksaStatus = true): ?string
    {
        return match (true) {
            ! $sub => 'Jadwal tidak ditemukan.',
            ($sub->StatusLamaran ?? '') !== 'BERJALAN' => 'Proses seleksi untuk lamaran ini sudah selesai.',
            ($sub->Flag_Selesai ?? 'T') === 'Y' => 'Aktivitas ini sudah selesai.',
            ! empty($sub->Jadwal_Hadir) => 'Kehadiran untuk jadwal ini sudah dicatat.',
            empty($sub->Jadwal_Mulai) => 'Jadwal ini sudah tidak berlaku.',
            (int) ($sub->Jadwal_Versi ?? 0) !== $versi => 'Jadwal ini sudah diperbarui. Buka email terbarumu atau Portal Kandidat.',
            $periksaStatus && empty($sub->Konfirmasi_Status) => 'Jadwal ini tidak meminta konfirmasi.',
            Carbon::parse($sub->Jadwal_Mulai)->lte(now()) => 'Jadwal ini sudah lewat.',
            default => null,
        };
    }

    // ════════════════════════════════════════════════════════════════════════
    //  TAUTAN BERTANDA TANGAN (halaman kandidat tanpa login)
    // ════════════════════════════════════════════════════════════════════════

    /** Kedaluwarsa tautan: sehari sesudah acara, paling lama N hari dari sekarang. */
    public static function kedaluwarsa(?string $mulai): Carbon
    {
        $maks = now()->addDays(max(1, (int) config('konfirmasi.tautan_hari_maks', 60)));
        $akhir = $mulai ? Carbon::parse($mulai)->addDay() : now()->addDays(7);
        if ($akhir->lt(now()->addDay())) {
            $akhir = now()->addDay();
        }

        return $akhir->gt($maks) ? $maks : $akhir;
    }

    /**
     * Tautan bertanda tangan untuk satu versi. `$rute` = halaman | jawab |
     * cabut | dibuka.
     */
    public static function tautan(int $subTesId, int $versi, ?string $mulai, string $rute = 'halaman'): string
    {
        return URL::temporarySignedRoute(
            'career.konfirmasi.'.$rute,
            self::kedaluwarsa($mulai),
            ['id' => Hashids::encode($subTesId), 'versi' => $versi],
        );
    }

    // ════════════════════════════════════════════════════════════════════════
    //  BACAAN UNTUK LAYAR
    // ════════════════════════════════════════════════════════════════════════

    /**
     * Muat CRM & permintaan terbuka untuk banyak aktivitas SEKALIGUS — dipanggil
     * pemuat papan worklist sebelum memetakan kartu. Tanpa ini, ringkas() akan
     * bertanya ke basis data per aktivitas.
     *
     * @param  array<int>  $subIds
     */
    public static function muatBanyak(array $subIds): void
    {
        if (! self::siap()) {
            return;
        }

        $subIds = array_values(array_unique(array_filter(array_map('intval', $subIds))));
        $baru = array_values(array_diff($subIds, array_keys(self::$muatan)));
        if (! $baru) {
            return;
        }

        foreach (array_chunk($baru, 500) as $potong) {
            $crm = DB::table(self::T_CRM)->whereIn('Lamaran_Tahap_Tes_Id', $potong)->get()->groupBy('Lamaran_Tahap_Tes_Id');
            $minta = DB::table(self::T_MINTA)->whereIn('Lamaran_Tahap_Tes_Id', $potong)->where('Status', self::P_TERBUKA)->get()->keyBy('Lamaran_Tahap_Tes_Id');
            foreach ($potong as $id) {
                self::$muatan[$id] = [
                    'crm' => $crm->get($id, collect())->keyBy('Versi'),
                    'minta' => $minta->get($id),
                ];
            }
        }
    }

    /**
     * Ringkasan konfirmasi satu aktivitas untuk worklist — null bila aktivitas
     * ini tidak meminta konfirmasi.
     */
    public static function ringkas(object $x): ?array
    {
        if (! self::siap() || empty($x->Konfirmasi_Status)) {
            return null;
        }
        // Ditunda: tanpa jadwal, tanpa CRM — info penundaannya dari jejak.
        // Jarang, jadi dibaca per aktivitas (tidak ikut muatBanyak papan).
        if ($x->Konfirmasi_Status === self::DITUNDA) {
            return empty($x->Jadwal_Mulai) ? self::bentukTunda($x, self::infoTunda((int) $x->Id_Lamaran_Tahap_Tes)) : null;
        }
        if (empty($x->Jadwal_Mulai)) {
            return null;
        }

        $id = (int) $x->Id_Lamaran_Tahap_Tes;
        if (! isset(self::$muatan[$id])) {
            self::muatBanyak([$id]);
        }

        $versi = (int) ($x->Jadwal_Versi ?? 0);
        $crm = self::$muatan[$id]['crm']->get($versi);
        $minta = self::$muatan[$id]['minta'] ?? null;

        return self::bentuk($x, $crm, $minta);
    }

    /** Bentuk payload konfirmasi yang sama untuk worklist, agenda, dan detail. */
    public static function bentuk(object $x, ?object $crm, ?object $minta): array
    {
        $status = self::statusEfektif($x->Konfirmasi_Status, $crm->Batas_Konfirmasi ?? null);
        $def = self::master()->get($status);
        $batas = $crm->Batas_Konfirmasi ?? null;

        return [
            'status' => $status,
            'statusTersimpan' => $x->Konfirmasi_Status,
            'label' => $def->Label_Tim ?? $status,
            'warna' => $def->Warna ?? null,
            'ikon' => $def->Ikon ?? null,
            'versi' => (int) ($x->Jadwal_Versi ?? 0),
            'batas' => $batas ? (string) $batas : null,
            'batasTeks' => $batas ? UndanganJadwal::teksBatas($batas) : null,
            'batasLewat' => $batas ? Carbon::parse($batas)->lte(now()) : false,
            'undanganAt' => $crm->Undangan_At ?? null,
            'dijawabAt' => $crm->Dijawab_At ?? null,
            'jumlahPengingat' => (int) ($crm->Jumlah_Pengingat ?? 0),
            'jumlahKirimManual' => (int) ($crm->Jumlah_Kirim_Manual ?? 0),
            'jumlahGagal' => (int) ($crm->Jumlah_Gagal ?? 0),
            'terakhirKirimAt' => $crm->Terakhir_Kirim_At ?? null,
            'terakhirKirimJenis' => $crm->Terakhir_Kirim_Jenis ?? null,
            'terakhirKirimOleh' => $crm->Terakhir_Kirim_Oleh ?? null,
            'permintaan' => $minta ? self::bentukPermintaan($minta) : null,
            'bolehPengingat' => $status === self::MENUNGGU,
            'tunda' => null,
        ];
    }

    /**
     * Bentuk payload aktivitas DITUNDA untuk layar tim — kunci yang sama
     * dengan bentuk(), supaya layar tidak perlu bercabang untuk membacanya.
     */
    public static function bentukTunda(object $x, ?array $info): array
    {
        $def = self::master()->get(self::DITUNDA);

        return [
            'status' => self::DITUNDA,
            'statusTersimpan' => self::DITUNDA,
            'label' => $def->Label_Tim ?? 'Ditunda',
            'warna' => $def->Warna ?? '#7c3aed',
            'ikon' => $def->Ikon ?? 'bi-pause-circle-fill',
            'versi' => (int) ($x->Jadwal_Versi ?? 0),
            'batas' => null,
            'batasTeks' => null,
            'batasLewat' => false,
            'undanganAt' => null,
            'dijawabAt' => null,
            'jumlahPengingat' => 0,
            'jumlahKirimManual' => 0,
            'jumlahGagal' => 0,
            'terakhirKirimAt' => null,
            'terakhirKirimJenis' => null,
            'terakhirKirimOleh' => null,
            'permintaan' => null,
            'bolehPengingat' => false,
            'tunda' => $info ? array_diff_key($info, array_flip(['ringkas'])) : null,
        ];
    }

    /**
     * Bahan layar JAWAB kandidat untuk satu versi — dipakai halaman tautan
     * (tanpa login) DAN kartu jadwal Portal Kandidat, supaya aturan, pilihan,
     * dan kalimatnya mustahil berbeda. `$url` = { jawab, cabut } milik layar
     * itu (tautan bertanda tangan, atau rute portal yang memeriksa sesi).
     */
    public static function bahanJawab(object $sub, int $versi, array $url): array
    {
        $id = (int) $sub->Id_Lamaran_Tahap_Tes;
        $crm = self::crm($id, $versi);
        $batas = $crm->Batas_Konfirmasi ?? null;
        $efektif = self::statusEfektif($sub->Konfirmasi_Status, $batas);
        $def = self::master()->get($efektif);
        $final = (self::master()->get($sub->Konfirmasi_Status)->Flag_Final_Kandidat ?? 'T') === 'Y';
        $batasLewat = ! $batas || Carbon::parse($batas)->lte(now());
        $terbuka = self::galatTerbuka($sub, $versi) === null;
        $tipe = self::tipeDari($sub);
        $maks = (int) ($tipe->Jadwal_Ulang_Maks ?? 2);

        $alasan = fn (string $jenis) => self::alasan($jenis)->map(fn ($a) => [
            'kode' => $a->Kode,
            'nama' => $a->Nama,
            'butuhCatatan' => ($a->Flag_Butuh_Catatan ?? 'T') === 'Y',
        ])->values()->all();

        // Permintaan versi ini: yang terbuka, atau jawaban tim yang terakhir
        // (ditolak → catatannya perlu dibaca kandidat sebelum menjawab lagi).
        $p = DB::table(self::T_MINTA)
            ->where('Lamaran_Tahap_Tes_Id', $id)
            ->where('Versi', $versi)
            ->orderByDesc('Id_Jadwal_Permintaan')
            ->first();

        $terpakai = self::jumlahPermintaan($id);
        $hariMaks = max(1, (int) config('konfirmasi.usulan_hari_maks', 14));

        return [
            'konfirmasi' => [
                'status' => $efektif,
                'kalimat' => $def->Kalimat_Kandidat ?? null,
                'warna' => $def->Warna ?? null,
                'ikon' => $def->Ikon ?? null,
                'batas' => $batas ? (string) $batas : null,
                'batasTeks' => $batas ? UndanganJadwal::teksBatas($batas) : null,
                'batasLewat' => $batasLewat,
                'final' => $final,
                'bolehJawab' => $terbuka && ! $batasLewat && ! $final,
                'kontak' => $sub->Jadwal_Kontak ?? null,
            ],
            'pilihan' => self::pilihanKandidat()->map(fn ($m) => [
                'kode' => $m->Kode,
                'label' => $m->Label_Tombol,
                'gaya' => $m->Gaya_Tombol ?: 'GARIS',
                'ikon' => $m->Ikon,
                'warna' => $m->Warna,
                'bukaPermintaan' => ($m->Flag_Buka_Permintaan ?? 'T') === 'Y',
                'sinyalMundur' => ($m->Flag_Sinyal_Mundur ?? 'T') === 'Y',
                'final' => ($m->Flag_Final_Kandidat ?? 'T') === 'Y',
            ])->values()->all(),
            'alasan' => ['JADWAL_LAIN' => $alasan('JADWAL_LAIN'), 'MUNDUR' => $alasan('MUNDUR')],
            'permintaan' => $p ? [
                'status' => $p->Status,
                'alasan' => self::alasan('JADWAL_LAIN')->get($p->Alasan_Kode)->Nama ?? $p->Alasan_Kode,
                'catatan' => $p->Catatan_Kandidat,
                'usulan' => array_map(fn ($u) => self::teksUsulan($u), json_decode((string) $p->Usulan_Json, true) ?: []),
                'dimintaAt' => (string) $p->Diminta_At,
                'catatanTim' => $p->Status === self::P_DITOLAK ? $p->Catatan_Tim : null,
            ] : null,
            'jatah' => ['maks' => $maks, 'terpakai' => $terpakai, 'sisa' => max(0, $maks - $terpakai)],
            'aturanUsulan' => [
                'maks' => max(1, (int) config('konfirmasi.usulan_maks', 3)),
                'tanggalMin' => now()->addDay()->format('Y-m-d'),
                'tanggalMaks' => now()->addDays($hariMaks)->format('Y-m-d'),
                'bagian' => array_values(config('konfirmasi.bagian_hari', [])),
                'catatanMaks' => self::CATATAN_MAKS,
            ],
            'ubah' => self::bentukUbah(self::aturanUbahUntuk($sub)),
            'url' => $url,
        ];
    }

    /**
     * Konfirmasi untuk PORTAL KANDIDAT — null bila aktivitas ini tidak meminta
     * konfirmasi. Satu kueri per aktivitas: portal memuat segelintir saja.
     */
    public static function untukKandidat(object $s): ?array
    {
        if (! self::siap() || empty($s->Konfirmasi_Status)) {
            return null;
        }

        $id = (int) $s->Id_Lamaran_Tahap_Tes;

        // DITUNDA — kandidat wajib tahu: alasannya, perkiraan jadwal pengganti
        // (atau "akan dikabarkan"), pesan tim. Tidak ada yang perlu dijawab.
        if ($s->Konfirmasi_Status === self::DITUNDA) {
            if (! empty($s->Jadwal_Mulai)) {
                return null;
            }
            $def = self::master()->get(self::DITUNDA);

            return [
                'status' => self::DITUNDA,
                'kalimat' => $def->Kalimat_Kandidat ?? 'Jadwal ini ditunda. Jadwal pengganti akan kami kabarkan.',
                'warna' => $def->Warna ?? '#7c3aed',
                'ikon' => $def->Ikon ?? 'bi-pause-circle-fill',
                'bolehJawab' => false,
                'tunda' => self::tundaUntukKandidat(self::infoTunda($id)),
            ];
        }
        if (empty($s->Jadwal_Mulai)) {
            return null;
        }

        // Konteks lengkap (status lamaran, tipe, kandidat) untuk bahan jawab —
        // satu kueri per aktivitas: portal memuat segelintir saja.
        $sub = self::konteks($id);
        if (! $sub) {
            return null;
        }
        $versi = (int) ($sub->Jadwal_Versi ?? 0);
        $hash = Hashids::encode($id);
        $bahan = self::bahanJawab($sub, $versi, [
            'jawab' => route('career.portal.konfirmasi.jawab', ['id' => $hash, 'versi' => $versi], false),
            'cabut' => route('career.portal.konfirmasi.cabut', ['id' => $hash, 'versi' => $versi], false),
        ]);
        $k = $bahan['konfirmasi'];

        return [
            'status' => $k['status'],
            'kalimat' => $k['kalimat'],
            'warna' => $k['warna'],
            'ikon' => $k['ikon'],
            'batas' => $k['batas'],
            'batasTeks' => $k['batasTeks'],
            'bolehJawab' => $k['bolehJawab'],
            // Dijawab LANGSUNG di kartu portal — tanpa pindah halaman.
            'jawab' => $bahan,
        ];
    }

    /** Bentuk satu permintaan untuk layar tim. */
    public static function bentukPermintaan(object $p): array
    {
        $usulan = json_decode((string) $p->Usulan_Json, true) ?: [];
        $alasan = self::alasan('JADWAL_LAIN')->get($p->Alasan_Kode);
        $sla = Carbon::parse($p->Sla_Batas);
        $total = max(1, Carbon::parse($p->Diminta_At)->diffInMinutes($sla));
        $sisa = now()->diffInMinutes($sla, false);

        return [
            'id' => Hashids::encode($p->Id_Jadwal_Permintaan),
            'status' => $p->Status,
            'versi' => (int) $p->Versi,
            'alasanKode' => $p->Alasan_Kode,
            'alasan' => $alasan->Nama ?? $p->Alasan_Kode,
            'catatan' => $p->Catatan_Kandidat,
            'usulan' => array_map(fn ($u) => $u + ['teks' => self::teksUsulan($u)], $usulan),
            'kanal' => $p->Kanal,
            'dimintaAt' => (string) $p->Diminta_At,
            'slaBatas' => (string) $p->Sla_Batas,
            'slaTeks' => UndanganJadwal::teksBatas($sla),
            // hijau > 50% sisa, kuning ≤ 50%, merah lewat
            'slaNada' => $sisa < 0 ? 'merah' : ($sisa <= $total / 2 ? 'kuning' : 'hijau'),
            'slaLewat' => $sisa < 0,
            'diprosesBy' => $p->Diproses_By,
            'diprosesAt' => $p->Diproses_At ? (string) $p->Diproses_At : null,
            'catatanTim' => $p->Catatan_Tim,
        ];
    }

    /**
     * Riwayat satu aktivitas: versi jadwal, jawaban, surel, tindakan tim.
     * Terbaru di atas.
     */
    public static function riwayat(int $subTesId, int $batas = 80): array
    {
        if (! Skema::adaTabel(JejakJadwal::TABEL)) {
            return [];
        }

        $label = [
            'BUAT' => 'Jadwal dibuat', 'UBAH' => 'Jadwal diubah', 'PERPANJANG' => 'Batas unggah diperpanjang',
            'PENGINGAT' => 'Pengingat batas unggah', 'KIRIM_ULANG' => 'Undangan dikirim ulang',
            self::A_DIBUKA => 'Halaman konfirmasi dibuka', self::A_JAWAB => 'Jawaban konfirmasi',
            self::A_INGAT_JAWAB => 'Pengingat konfirmasi',
            self::A_PROSES => 'Permintaan jadwal lain diproses', self::A_KABAR_TIM => 'Kabar ke tim',
            self::A_KEHADIRAN => 'Kehadiran dicatat', self::A_TUNDA => 'Jadwal ditunda',
            self::A_TUNDA_INFO => 'Info penundaan diperbarui',
        ];
        $master = self::master();
        $kolom = Skema::adaKolom(JejakJadwal::TABEL, 'Data_Json')
            ? ['Id_Jadwal_Jejak', 'Aksi', 'Versi', 'Pelaku', 'Kanal', 'Status_Dari', 'Status_Ke', 'Permintaan_Id', 'Alasan', 'Undangan', 'Mulai', 'Mode', 'Lokasi_Nama', 'Data_Json', 'Created_At', 'Created_By']
            : ['Id_Jadwal_Jejak', 'Aksi', 'Alasan', 'Undangan', 'Mulai', 'Mode', 'Lokasi_Nama', 'Created_At', 'Created_By'];

        return DB::table(JejakJadwal::TABEL)
            ->where('Lamaran_Tahap_Tes_Id', $subTesId)
            ->orderByDesc('Id_Jadwal_Jejak')
            ->limit($batas)
            ->get($kolom)
            ->map(function ($j) use ($label, $master) {
                $data = json_decode((string) ($j->Data_Json ?? ''), true) ?: [];

                $otomatis = (bool) ($data['otomatis'] ?? false);

                return [
                    'id' => (int) $j->Id_Jadwal_Jejak,
                    'aksi' => $j->Aksi,
                    'label' => $otomatis && $j->Aksi === self::A_INGAT_JAWAB
                        ? 'Pengingat konfirmasi otomatis'
                        : ($label[$j->Aksi] ?? $j->Aksi),
                    'versi' => isset($j->Versi) ? (int) $j->Versi : null,
                    'pelaku' => $j->Pelaku ?? self::TIM,
                    'kanal' => $j->Kanal ?? null,
                    'dari' => isset($j->Status_Dari) && $j->Status_Dari ? ($master->get($j->Status_Dari)->Label_Tim ?? $j->Status_Dari) : null,
                    'ke' => isset($j->Status_Ke) && $j->Status_Ke ? ($master->get($j->Status_Ke)->Label_Tim ?? $j->Status_Ke) : null,
                    'alasan' => $j->Alasan,
                    'surel' => $j->Undangan,
                    'mulai' => $j->Mulai ? (string) $j->Mulai : null,
                    'mode' => $j->Mode,
                    'tempat' => $j->Lokasi_Nama,
                    'jenisSurel' => $data['surel'] ?? null,
                    'catatan' => $data['catatan'] ?? ($data['catatanTim'] ?? (! empty($data['tunda']['butuhCatatan']) ? null : ($data['tunda']['pesan'] ?? null))),
                    // Penundaan: perkiraan jadwal pengganti (null = "akan dikabarkan").
                    'perkiraanTeks' => ! empty($data['tunda']['perkiraan']) ? self::tanggalTeks($data['tunda']['perkiraan']) : null,
                    'tunda' => isset($data['tunda']),
                    'alasanNama' => $data['alasanNama'] ?? null,
                    'hadir' => $data['hadir'] ?? null,
                    'manual' => (bool) ($data['manual'] ?? false),
                    'otomatis' => $otomatis,
                    'slot' => $data['slot'] ?? null,
                    'galat' => $data['galat'] ?? null,
                    'terkirimAt' => $data['terkirimAt'] ?? null,
                    'oleh' => $j->Created_By,
                    'at' => (string) $j->Created_At,
                ];
            })
            ->values()
            ->all();
    }
}
