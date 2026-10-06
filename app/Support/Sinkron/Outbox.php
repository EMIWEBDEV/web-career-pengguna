<?php

namespace App\Support\Sinkron;

use App\Jobs\TerbitkanOutbox;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * OUTBOX — satu-satunya pintu aplikasi publik ke zona dalam.
 *
 * Dipanggil DI DALAM transaksi yang sama dengan data bisnisnya: tabel milik
 * dan peristiwanya tersimpan berdua, atau tidak sama sekali. Penulisannya
 * lewat procedure usp_PUB_Outbox_Tulis — peran aplikasi hanya punya EXECUTE,
 * jadi aplikasi tidak bisa mengisi Event_Id/Status atau menyunting baris lama.
 *
 * KUNCI IDEMPOTEN ditentukan dari AKSINYA (mis. "Lamaran.Dikirim:LMR-…"):
 * tombol yang ditekan dua kali tetap menjadi satu peristiwa (procedure
 * menjawab DUPLIKAT). KUNCI URUT = akun kandidat, supaya peristiwa satu akun
 * tiba di zona dalam sesuai urutan terjadinya.
 *
 * Sesudah commit, penerbit diantrekan (queue wcp-terbit); bila antrean itu
 * sedang mati, penyapu tiap menit yang menerbitkannya.
 */
final class Outbox
{
    public const AKUN_TERDAFTAR = 'Akun.Terdaftar';
    public const AKUN_DIPERBARUI = 'Akun.Diperbarui';
    public const AKUN_KODE_DIMINTA = 'Akun.KodeDiminta';
    public const LAMARAN_DIKIRIM = 'Lamaran.Dikirim';
    public const LAMARAN_DIBATALKAN = 'Lamaran.Dibatalkan';
    public const FORMULIR_DIKIRIM = 'Formulir.Dikirim';
    public const BERKAS_DIUNGGAH = 'Berkas.Diunggah';
    public const KONFIRMASI_DIJAWAB = 'Konfirmasi.Dijawab';
    public const KONFIRMASI_DICABUT = 'Konfirmasi.Dicabut';
    public const FEEDBACK_DIKIRIM = 'Feedback.Dikirim';

    public const TABEL = 'N_WEB_CAREERS_Sinkron_Outbox';

    /** Batas muatan: CHECK di tabel = 524.288 byte NVARCHAR (2 byte per karakter). */
    private const MAKS_KARAKTER = 250000;

    /** @var list<array>|null peristiwa yang dicatat saat mode palsu (pengujian) */
    private static ?array $palsu = null;

    /**
     * PENGUJIAN: catat peristiwa di memori, tanpa procedure database (sqlite
     * tidak mengenal usp_PUB_Outbox_Tulis). Aturan lain tetap berlaku: wajib di
     * dalam transaksi, batas ukuran, dan penolak ganda per kunci idempoten.
     */
    public static function palsukan(): void
    {
        self::$palsu = [];
    }

    /** PENGUJIAN: peristiwa yang tercatat selama mode palsu (bisa disaring per jenis). */
    public static function tercatat(?string $jenis = null): array
    {
        return array_values(array_filter(self::$palsu ?? [], fn (array $e) => $jenis === null || $e['jenis'] === $jenis));
    }

    /**
     * Catat satu peristiwa.
     *
     * @return array{hasil: string, eventId: ?string} hasil BARU atau DUPLIKAT
     */
    public static function tulis(
        string $jenis,
        string $kunciIdem,
        int $akunId,
        array $muatan,
        ?string $oleh = null,
        int $versiSkema = 1,
    ): array {
        if (DB::transactionLevel() < 1) {
            throw new RuntimeException("Peristiwa {$jenis} wajib dicatat di dalam transaksi data bisnisnya.");
        }

        $json = json_encode(
            ['kontrak' => $versiSkema, 'terjadi_at' => now('UTC')->format('Y-m-d\TH:i:s.v\Z')] + $muatan,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        );
        if (mb_strlen($json) > self::MAKS_KARAKTER) {
            throw new RuntimeException("Muatan peristiwa {$jenis} terlalu besar (".mb_strlen($json).' karakter).');
        }

        $kunciUrut = self::kunciUrut($akunId);

        if (self::$palsu !== null) {
            foreach (self::$palsu as $e) {
                if ($e['kunci'] === $kunciIdem) {
                    return ['hasil' => 'DUPLIKAT', 'eventId' => $e['eventId']];
                }
            }
            $eventId = (string) Str::uuid();
            self::$palsu[] = ['jenis' => $jenis, 'kunci' => $kunciIdem, 'kunciUrut' => $kunciUrut,
                'muatan' => json_decode($json, true), 'eventId' => $eventId];

            return ['hasil' => 'BARU', 'eventId' => $eventId];
        }

        $baris = DB::selectOne(
            'SET NOCOUNT ON;
             DECLARE @hasil VARCHAR(10), @event UNIQUEIDENTIFIER;
             EXEC dbo.usp_PUB_Outbox_Tulis
                  @Idempotency_Key = ?, @Jenis = ?, @Versi_Skema = ?, @Kunci_Urut = ?,
                  @Muatan = ?, @Created_By = ?, @Hasil = @hasil OUTPUT, @Event_Id = @event OUTPUT;
             SELECT @hasil AS Hasil, CONVERT(VARCHAR(36), @event) AS Event_Id;',
            [$kunciIdem, $jenis, $versiSkema, $kunciUrut, $json, $oleh !== null ? mb_substr($oleh, 0, 60) : null],
        );

        $hasil = (string) ($baris->Hasil ?? '');
        if (! in_array($hasil, ['BARU', 'DUPLIKAT'], true)) {
            throw new RuntimeException("Outbox menolak peristiwa {$jenis} ({$kunciIdem}).");
        }

        if ($hasil === 'BARU') {
            DB::afterCommit(fn () => TerbitkanOutbox::antrekan($kunciUrut));
        }

        return ['hasil' => $hasil, 'eventId' => $baris->Event_Id ?? null];
    }

    public static function kunciUrut(int $akunId): string
    {
        return 'akun:'.$akunId;
    }

    /**
     * Status satu peristiwa — hanya kolom yang boleh dibaca peran aplikasi
     * (Status, Hasil_At, Hasil_Keterangan). Hasil diisi Sync Worker sesudah
     * zona dalam memprosesnya: DIPROSES atau DITOLAK.
     */
    public static function status(string $kunciIdem): ?object
    {
        if (self::$palsu !== null) {
            foreach (self::$palsu as $e) {
                if ($e['kunci'] === $kunciIdem) {
                    return (object) ['Event_Id' => $e['eventId'], 'Jenis' => $e['jenis'], 'Status' => 'MENUNGGU',
                        'Hasil_At' => null, 'Hasil_Keterangan' => null, 'Created_At' => null];
                }
            }

            return null;
        }

        return DB::table(self::TABEL)
            ->where('Idempotency_Key', $kunciIdem)
            ->first(['Event_Id', 'Jenis', 'Status', 'Hasil_At', 'Hasil_Keterangan', 'Created_At']);
    }
}
