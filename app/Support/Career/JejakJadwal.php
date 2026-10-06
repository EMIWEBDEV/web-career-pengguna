<?php

namespace App\Support\Career;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * WEB CAREERS — JEJAK JADWAL AKTIVITAS.
 *
 * Satu baris setiap kali jadwal sebuah aktivitas DIBUAT, DIUBAH, DIPERPANJANG,
 * DIINGATKAN, atau surelnya DIKIRIM ULANG ke kandidat. Baris tidak pernah
 * diubah atau dihapus.
 *
 * ══ KENAPA ADA ══
 *
 * Sebelumnya terapkanJadwal() menimpa kolom Jadwal_* apa adanya: yang tersisa
 * hanya siapa yang terakhir menyimpan. Kalau kandidat berkata "di email saya
 * disuruh ke RS A", tidak ada satu pun bukti di sistem bahwa jadwalnya pernah
 * menunjuk RS A — atau siapa yang memindahkannya, dan kenapa.
 *
 * "Nilai sebelum" adalah baris sebelumnya; "nilai sesudah" adalah baris ini.
 * Tidak perlu pasangan kolom lama/baru — cukup membaca dua baris berurutan.
 *
 * Tabelnya dibuat docs/30-09-2026/01-mcu-mandiri-dan-biaya.sql. Sebelum skrip
 * itu dijalankan, siap() = false: jadwal tetap tersimpan seperti biasa, hanya
 * tanpa jejak dan tanpa tuntutan alasan.
 */
final class JejakJadwal
{
    public const TABEL = 'N_WEB_CAREERS_Lamaran_Tahap_Tes_Jadwal_Jejak';

    public const BUAT = 'BUAT';

    public const UBAH = 'UBAH';

    public const PERPANJANG = 'PERPANJANG';

    public const PENGINGAT = 'PENGINGAT';

    /** Surel jadwal dikirim ulang oleh tim (tombol "Kirim ulang"). */
    public const KIRIM_ULANG = 'KIRIM_ULANG';

    /** Alasan perubahan paling pendek yang masih berarti sesuatu. */
    public const ALASAN_MIN = 5;

    public static function siap(): bool
    {
        try {
            return Skema::adaTabel(self::TABEL);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Apa yang sedang terjadi pada jadwal yang DISIMPAN lewat jendela Atur
     * Jadwal — dibaca SEBELUM ditulis ulang: BUAT atau UBAH.
     *
     * PERPANJANG tidak pernah lahir dari sini. Ia hanya dicatat tombol
     * "Perpanjang" (LamaranController::subTesPerpanjang), yang memundurkan
     * BATAS UNGGAH hasil tanpa menyentuh rentang pemeriksaan. Memundurkan
     * rentangnya sendiri adalah perubahan janji — dicatat UBAH, dengan alasan.
     *
     * @param  object  $sebelum  baris Lamaran_Tahap_Tes sebelum disimpan
     */
    public static function aksi(object $sebelum): string
    {
        return empty($sebelum->Jadwal_Mulai) ? self::BUAT : self::UBAH;
    }

    /**
     * Catat keadaan jadwal SEKARANG sebagai satu baris jejak.
     *
     * Isinya dibaca ulang dari barisnya setelah disimpan — bukan dari masukan
     * layar — supaya yang tercatat persis yang benar-benar dipegang sistem.
     *
     * TIDAK PERNAH MENGGAGALKAN PEMANGGIL. Jadwalnya sudah tersimpan dan
     * undangannya mungkin sudah berangkat; menjawab "gagal" karena jejaknya
     * tidak tertulis membuat rekruter mengulang dan kandidat menerima dua surat.
     * Kegagalannya dicatat ke log sebagai galat supaya terlihat.
     *
     * KONFIRMASI KEHADIRAN (docs/01-10-2026/01): bila kolomnya sudah ada,
     * baris ikut menyebut VERSI jadwal yang dibekukannya, siapa pelakunya, dan
     * lewat kanal apa. `$tambahan` dipakai jalur konfirmasi untuk menautkan
     * permintaan & perubahan status (Permintaan_Id, Status_Dari/Ke, Data_Json,
     * Rombongan_Kode) — kolom yang belum ada dilewati.
     *
     * @param  string|null  $undangan  'terkirim' | 'gagal' | 'privat' | 'antre' | null
     * @param  array<string,mixed>  $tambahan
     * @return int|null id baris jejak
     */
    public static function catat(int $subTesId, string $aksi, ?string $alasan = null, ?string $undangan = null, array $tambahan = []): ?int
    {
        if (! self::siap()) {
            return null;
        }

        try {
            $s = DB::table('N_WEB_CAREERS_Lamaran_Tahap_Tes')->where('Id_Lamaran_Tahap_Tes', $subTesId)->first();
            if (! $s) {
                return null;
            }

            $namaTempat = $s->Jadwal_Lokasi_Id
                ? DB::table('N_WEB_CAREERS_Master_Lokasi')->where('Id_Master_Lokasi', $s->Jadwal_Lokasi_Id)->value('Nama')
                : ($s->Jadwal_Lokasi_Nama ?? null);

            $alasan = trim((string) $alasan);

            // `$tambahan` dari pemanggil disimpan dulu — namanya dipakai ulang
            // di bawah untuk kolom salinan jadwal.
            $ekstra = $tambahan;
            $tambahan = [];

            // Tempat vendor & surat pengantar (kolom 30-09 tahap kedua) — ikut
            // dibekukan bila kolomnya sudah ada: "surat mana yang diterima
            // kandidat" adalah pertanyaan yang pasti datang.
            if (Skema::adaKolom(self::TABEL, 'Surat_Json')) {
                $tambahan += [
                    'Lokasi_Html' => $s->Jadwal_Lokasi_Html ?? null,
                    'Maps_Url' => $s->Jadwal_Maps_Url ?? null,
                    'Surat_Json' => $s->Jadwal_Surat_Json ?? null,
                ];
            }
            // Apakah kandidat diberi tahu soal biaya — bukti bila kelak ada
            // yang berkata "saya tidak pernah diberi tahu harus membayar".
            if (Skema::adaKolom(self::TABEL, 'Tampil_Biaya')) {
                $tambahan['Tampil_Biaya'] = UndanganJadwal::tampilBiaya($s) ? 'Y' : 'T';
            }
            if (Skema::adaKolom(self::TABEL, 'Kalimat_Biaya')) {
                $tambahan['Kalimat_Biaya'] = $s->Jadwal_Kalimat_Biaya ?? null;
            }
            // Batas unggah yang diperpanjang tim — rentang pemeriksaannya tetap
            // di Mulai/Selesai, jadi perpanjangan butuh kolomnya sendiri.
            if (Skema::adaKolom(self::TABEL, 'Batas_Unggah')) {
                $tambahan['Batas_Unggah'] = UndanganJadwal::batasDiperpanjang($s) ? $s->Jadwal_Batas_Unggah : null;
            }
            // Versi jadwal yang dibekukan baris ini + pelakunya.
            if (Skema::adaKolom(self::TABEL, 'Versi')) {
                $tambahan['Versi'] = isset($s->Jadwal_Versi) ? (int) $s->Jadwal_Versi : null;
                $tambahan['Pelaku'] = session('career_auth.id') ? 'TIM' : 'SISTEM';
                $tambahan['Kanal'] = session('career_auth.id') ? 'LAYAR' : 'SISTEM';
                foreach (['Permintaan_Id', 'Status_Dari', 'Status_Ke', 'Rombongan_Kode', 'Pelaku', 'Kanal'] as $k) {
                    if (array_key_exists($k, $ekstra)) {
                        $tambahan[$k] = $ekstra[$k];
                    }
                }
                if (array_key_exists('Data_Json', $ekstra) && $ekstra['Data_Json'] !== null) {
                    $tambahan['Data_Json'] = is_string($ekstra['Data_Json'])
                        ? $ekstra['Data_Json']
                        : json_encode($ekstra['Data_Json'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                }
            }

            return (int) DB::table(self::TABEL)->insertGetId($tambahan + [
                'Lamaran_Tahap_Tes_Id' => $subTesId,
                'Aksi' => $aksi,
                'Mode' => $s->Jadwal_Mode,
                'Mulai' => $s->Jadwal_Mulai,
                'Selesai' => $s->Jadwal_Selesai,
                'Lokasi_Id' => $s->Jadwal_Lokasi_Id,
                'Lokasi_Nama' => $namaTempat ? mb_substr((string) $namaTempat, 0, 200) : null,
                'Catatan_Html' => $s->Jadwal_Catatan_Html ?? null,
                'Alasan' => $alasan !== '' ? mb_substr($alasan, 0, 500) : null,
                'Undangan' => $undangan ? strtoupper(mb_substr($undangan, 0, 10)) : null,
                'Created_At' => now(),
                'Created_By' => session('career_auth.nama') ?: 'SISTEM',
                'Created_By_Id' => session('career_auth.id'),
            ], 'Id_Jadwal_Jejak');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("[JADWAL] jejak aktivitas #{$subTesId} ({$aksi}) gagal ditulis: ".$e->getMessage());

            return null;
        }
    }

    /** Tandai hasil pengiriman surat pada satu baris jejak. */
    public static function tandaiUndangan(?int $jejakId, string $undangan): void
    {
        if (! $jejakId) {
            return;
        }

        try {
            DB::table(self::TABEL)->where('Id_Jadwal_Jejak', $jejakId)
                ->update(['Undangan' => strtoupper(mb_substr($undangan, 0, 10))]);
        } catch (\Throwable $e) {
            Log::channel('web_career')->warning("[JADWAL] status undangan jejak #{$jejakId} gagal ditulis: ".$e->getMessage());
        }
    }

    /**
     * Riwayat satu aktivitas — terbaru di atas, siap dikirim ke layar.
     *
     * Nama mode dibaca dari master SAAT INI (hanya label); yang menentukan isi
     * jadwal tetap kolom-kolom yang dibekukan di baris jejak.
     */
    public static function daftar(int $subTesId): array
    {
        if (! self::siap()) {
            return [];
        }

        $mode = DB::table('N_WEB_CAREERS_Master_Mode_Jadwal')->get()->keyBy('Kode');

        return DB::table(self::TABEL)
            ->where('Lamaran_Tahap_Tes_Id', $subTesId)
            // Hanya kejadian JADWAL. Jawaban kandidat, pengingat konfirmasi, dan
            // surel lain ditampilkan riwayat konfirmasi (KonfirmasiJadwal::riwayat).
            ->whereIn('Aksi', [self::BUAT, self::UBAH, self::PERPANJANG, self::PENGINGAT, self::KIRIM_ULANG, 'TUNDA'])
            ->orderByDesc('Id_Jadwal_Jejak')
            ->limit(50)
            ->get()
            ->map(fn ($j) => [
                'aksi' => $j->Aksi,
                'mode' => $j->Mode,
                'modeNama' => $mode->get($j->Mode)->Nama ?? $j->Mode,
                // Selesai = BATAS AKHIR (bukan jam selesai) pada mode ini.
                'batasWaktu' => UndanganJadwal::berbatasWaktu($mode->get($j->Mode)),
                'mulai' => $j->Mulai ? (string) $j->Mulai : null,
                'selesai' => $j->Selesai ? (string) $j->Selesai : null,
                'rentang' => UndanganJadwal::berbatasWaktu($mode->get($j->Mode))
                    ? UndanganJadwal::rentangPendek($j->Mulai, $j->Selesai)
                    : null,
                // Batas unggah hasil yang berlaku saat itu, bila diperpanjang.
                'batasUnggah' => ! empty($j->Batas_Unggah) ? UndanganJadwal::tanggalPendek($j->Batas_Unggah) : null,
                'tempat' => $j->Lokasi_Nama,
                // Nama surat-surat pengantar yang melekat saat itu.
                'surat' => implode(', ', array_column(SuratJadwal::daftarDariJson($j->Surat_Json ?? null), 'nama')) ?: null,
                'alasan' => $j->Alasan,
                'undangan' => $j->Undangan,
                'oleh' => $j->Created_By,
                'at' => (string) $j->Created_At,
            ])
            ->values()
            ->all();
    }
}
