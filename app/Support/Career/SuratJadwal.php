<?php

namespace App\Support\Career;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Vinkla\Hashids\Facades\Hashids;

/**
 * WEB CAREERS — SURAT PENGANTAR JADWAL (mis. surat pengantar MCU).
 *
 * Mode jadwal ber-Flag_Butuh_Surat (MCU vendor & mandiri) WAJIB membawa surat
 * pengantar: kandidat menunjukkannya di loket vendor atau klinik pilihannya,
 * dan tanpa surat itu pemeriksaannya tidak dicatat sebagai titipan perusahaan.
 *
 * ══ BOLEH LEBIH DARI SATU — HANYA PDF, TOTAL PALING BESAR 5 MB ══
 *
 * Satu jadwal bisa membawa beberapa surat (surat pengantar + rujukan paket
 * pemeriksaan, misalnya). Yang dibatasi JUMLAH UKURANNYA, bukan banyaknya:
 * lima surat @1 MB boleh, enam surat @1 MB tidak. Daftarnya disimpan di
 * Lamaran_Tahap_Tes.Jadwal_Surat_Json: [{"path","nama","ukuran"}, ...].
 *
 * ══ DUA LANGKAH: UNGGAH DULU, SIMPAN JADWAL KEMUDIAN ══
 *
 * simpan() menaruh berkas di GCS lalu menjawab dengan REF terenkripsi (berikut
 * ukurannya); jadwal disimpan membawa ref-ref itu. Dengan begitu satu set surat
 * bisa dipakai jendela massal untuk beberapa kandidat sekaligus, dan menyimpan
 * jadwal tetap berupa permintaan JSON biasa. Ref-nya dienkripsi dengan APP_KEY
 * — layar tidak bisa memalsukannya untuk menunjuk berkas lain di bucket, dan
 * ukuran di dalamnya bisa dipercaya saat totalnya dihitung.
 *
 * ══ SURAT LAMA TIDAK DIHAPUS ══
 *
 * Mengganti surat hanya memindahkan penunjuk di baris jadwal. Berkas lamanya
 * tetap di bucket: jejak jadwal masih menyebutnya, dan surat yang sudah
 * terkirim ke kandidat harus tetap bisa dibuktikan isinya belakangan.
 */
final class SuratJadwal
{
    public const FOLDER = 'surat-jadwal';

    /** Hanya PDF — surat resmi, bukan foto. */
    public const FORMAT = ['pdf'];

    /** Batas JUMLAH ukuran seluruh surat satu jadwal. */
    public const MAKS_TOTAL_MB = 5;

    /** Pengaman banyaknya berkas — batas ukuran di atas yang biasanya lebih dulu tercapai. */
    public const MAKS_BERKAS = 10;

    /** Umur ref hasil unggah — jendela jadwal yang ditinggal lama cukup diunggah ulang. */
    private const UMUR_REF_JAM = 24;

    public static function maksTotalByte(): int
    {
        return self::MAKS_TOTAL_MB * 1024 * 1024;
    }

    /**
     * Unggah satu surat → [ref, nama, ukuran].
     *
     * @throws \RuntimeException bila GCS menolak
     */
    public static function simpan(UploadedFile $berkas): array
    {
        // getContent(), BUKAN getRealPath(): di bawah Apache getRealPath()
        // bisa mengembalikan string kosong dan isinya gagal terbaca.
        $konten = $berkas->getContent();
        // Ukuran berkas di server — angka yang sama dipakai aturan `max`
        // validasinya, dan yang dijumlahkan untuk batas total.
        $ukuran = (int) ($berkas->getSize() ?: strlen($konten));
        $ext = strtolower($berkas->getClientOriginalExtension() ?: (string) $berkas->extension());
        $nama = mb_substr(trim((string) $berkas->getClientOriginalName()) ?: 'surat-pengantar.'.$ext, 0, 300);
        $now = now();

        $path = app(GcsBerkas::class)->unggahUnik(
            self::FOLDER.'/'.$now->format('Y').'/'.$now->format('m').'/'.$now->format('d'),
            pathinfo($nama, PATHINFO_FILENAME) ?: 'surat-pengantar',
            $ext,
            $konten,
        );

        return [
            'ref' => Crypt::encryptString(json_encode(['p' => $path, 'n' => $nama, 'u' => $ukuran, 't' => $now->timestamp])),
            'nama' => $nama,
            'ukuran' => $ukuran,
        ];
    }

    /**
     * Ref → [path, nama, ukuran]; null bila rusak, kedaluwarsa, atau menunjuk
     * ke luar folder surat.
     */
    public static function baca(?string $ref): ?array
    {
        $ref = trim((string) $ref);
        if ($ref === '') {
            return null;
        }

        try {
            $x = json_decode(Crypt::decryptString($ref), true, 4, JSON_THROW_ON_ERROR);
        } catch (\Throwable $e) {
            return null;
        }

        $path = (string) ($x['p'] ?? '');
        if (! str_starts_with($path, self::FOLDER.'/') || str_contains($path, '..')) {
            return null;
        }
        if ((int) ($x['t'] ?? 0) < now()->subHours(self::UMUR_REF_JAM)->timestamp) {
            return null;
        }

        return [
            'path' => $path,
            'nama' => mb_substr((string) ($x['n'] ?? 'surat-pengantar.pdf'), 0, 300),
            'ukuran' => max(0, (int) ($x['u'] ?? 0)),
        ];
    }

    /**
     * Daftar surat sebuah jadwal — [[path, nama, ukuran], ...], urut seperti
     * disimpan. Isian rusak dilewati, bukan menjatuhkan halaman.
     *
     * @param  object  $s  baris Lamaran_Tahap_Tes
     */
    public static function daftar(object $s): array
    {
        $x = json_decode((string) ($s->Jadwal_Surat_Json ?? ''), true);

        return is_array($x) ? self::rapikan($x) : [];
    }

    /** Daftar dari JSON jejak (Surat_Json). */
    public static function daftarDariJson(?string $json): array
    {
        $x = json_decode((string) $json, true);

        return is_array($x) ? self::rapikan($x) : [];
    }

    /** Daftar → JSON untuk disimpan; null bila kosong. */
    public static function json(array $daftar): ?string
    {
        $daftar = self::rapikan($daftar);

        return $daftar ? json_encode($daftar, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;
    }

    public static function totalByte(array $daftar): int
    {
        return array_sum(array_map(fn ($i) => (int) ($i['ukuran'] ?? 0), $daftar));
    }

    /** "1,2 MB" / "350 KB" — untuk pesan & layar. */
    public static function teksUkuran(int $byte): string
    {
        return $byte >= 1024 * 1024
            ? str_replace('.', ',', (string) round($byte / 1048576, 1)).' MB'
            : max(1, (int) round($byte / 1024)).' KB';
    }

    /**
     * Pesan galat bila daftar ini melanggar batas; null bila sah.
     *
     * Dipakai jalur satuan DAN massal — keduanya harus menolak hal yang sama.
     */
    public static function galatBatas(array $daftar): ?string
    {
        if (count($daftar) > self::MAKS_BERKAS) {
            return 'Surat pengantar paling banyak '.self::MAKS_BERKAS.' berkas.';
        }

        $total = self::totalByte($daftar);
        if ($total > self::maksTotalByte()) {
            return 'Total ukuran surat pengantar '.self::teksUkuran($total).' — melebihi batas '.self::MAKS_TOTAL_MB
                .' MB. Hapus salah satu surat atau perkecil ukurannya.';
        }

        return null;
    }

    /**
     * Tautan satu surat untuk SUREL — bertanda tangan, tanpa login.
     *
     * Surel dibuka di aplikasi surat yang tidak membawa sesi portal; tautan ke
     * rute berlogin akan selalu mendarat di halaman masuk. Umurnya mengikuti
     * rentangnya (akhir rentang + 30 hari, paling sedikit 30 hari dari
     * sekarang, paling lama 180 hari) — sesudahnya surat tetap bisa diunduh
     * dari portal. Yang disajikan selalu surat urutan itu pada jadwal TERBARU.
     */
    public static function tautanEmail(int $subTesId, int $urutan = 0, Carbon|string|null $selesai = null): string
    {
        $akhir = now()->addDays(30);
        if ($selesai) {
            $s = Carbon::parse($selesai)->addDays(30);
            $akhir = $s->gt($akhir) ? $s : $akhir;
        }
        $maks = now()->addDays(180);

        return URL::temporarySignedRoute(
            'career.surat.jadwal',
            $akhir->gt($maks) ? $maks : $akhir,
            ['id' => Hashids::encode($subTesId), 'urutan' => $urutan],
        );
    }

    /** Path surat urutan ke-N sebuah jadwal; null bila tak ada. */
    public static function path(object $s, int $urutan): ?string
    {
        return self::daftar($s)[$urutan]['path'] ?? null;
    }

    /** Nama asli surat urutan ke-N (nama saat diunggah tim); null bila tak ada. */
    public static function nama(object $s, int $urutan): ?string
    {
        return self::daftar($s)[$urutan]['nama'] ?? null;
    }

    /**
     * Sajikan surat lewat URL GCS bertanda tangan 15 menit; 404 bila tak ada.
     *
     * DUA CARA, satu berkas:
     *   inline  (bawaan) — tampil di peramban: pratinjau di portal, tautan surel;
     *   unduh   — Content-Disposition attachment, jadi tombol "Unduh" benar-benar
     *             mengunduh, bukan membuka tab lain. Atribut `download` pada
     *             tautan tidak bisa diandalkan: berkasnya dialihkan ke domain
     *             GCS, dan peramban mengabaikan atribut itu lintas domain.
     * Keduanya membawa nama ASLI suratnya, bukan nama acak di bucket.
     */
    public static function layani(?string $path, ?string $nama = null, bool $unduh = false)
    {
        if ($path) {
            try {
                $disk = Storage::disk(GcsBerkas::DISK);
                if ($disk->exists($path)) {
                    $opsi = [
                        // V4: tanda tangan V2 pustaka GCS tidak menyandikan parameter
                        // response-content-* — spasi di "attachment; filename=…" membuat
                        // GCS menjawab 400. V4 menyandikan dan ikut menandatanganinya.
                        'version' => 'v4',
                        'responseDisposition' => self::disposisi($unduh ? 'attachment' : 'inline', $nama ?: basename($path)),
                    ];
                    if (str_ends_with(strtolower($path), '.pdf')) {
                        $opsi['responseType'] = 'application/pdf';
                    }

                    return redirect()->away($disk->temporaryUrl($path, now()->addMinutes(15), $opsi));
                }
            } catch (\Throwable $e) {
                Log::channel('web_career')->warning('[SURAT-JADWAL] signed URL gagal: '.$e->getMessage());
            }
        }

        abort(404, 'Surat tidak ditemukan.');
    }

    /**
     * Isi surat LANGSUNG dari server ini (bukan dialihkan ke GCS) — khusus
     * pratinjau di portal kandidat.
     *
     * Pratinjau digambar pdf.js, yang mengambil berkasnya lewat fetch. Fetch
     * yang dialihkan ke storage.googleapis.com butuh izin CORS di bucket, dan
     * bucket-nya tidak memberikannya; lewat rute ini berkasnya datang dari
     * domain yang sama. Aman dilewatkan server: surat pengantar paling besar
     * MAKS_TOTAL_MB seluruhnya, dan hanya PDF.
     */
    public static function sajikanIsi(?string $path, ?string $nama = null)
    {
        if ($path && str_ends_with(strtolower($path), '.pdf')) {
            try {
                $disk = Storage::disk(GcsBerkas::DISK);
                if ($disk->exists($path)) {
                    $ukuran = (int) $disk->size($path);

                    return response()->stream(function () use ($disk, $path) {
                        $alir = $disk->readStream($path);
                        if (is_resource($alir)) {
                            fpassthru($alir);
                            fclose($alir);
                        }
                    }, 200, array_filter([
                        'Content-Type' => 'application/pdf',
                        'Content-Length' => $ukuran ?: null,
                        'Content-Disposition' => self::disposisi('inline', $nama ?: basename($path)),
                        'Cache-Control' => 'private, max-age=300',
                        'X-Content-Type-Options' => 'nosniff',
                    ]));
                }
            } catch (\Throwable $e) {
                Log::channel('web_career')->warning('[SURAT-JADWAL] isi surat gagal dibaca: '.$e->getMessage());
            }
        }

        abort(404, 'Surat tidak ditemukan.');
    }

    /**
     * Content-Disposition beserta nama berkasnya (RFC 6266): nama asli dalam
     * UTF-8, ditambah cadangan ASCII untuk peramban lama. Nama yang tak bisa
     * dibentuk jatuh ke "surat-pengantar.pdf", bukan menggagalkan unduhan.
     */
    private static function disposisi(string $jenis, string $nama): string
    {
        $nama = trim(str_replace(['/', '\\', '"'], '-', $nama)) ?: 'surat-pengantar.pdf';
        $cadangan = trim(str_replace('%', '', (string) preg_replace('/[^\x20-\x7E]/', '', Str::ascii($nama)))) ?: 'surat-pengantar.pdf';

        try {
            return HeaderUtils::makeDisposition($jenis, $nama, $cadangan);
        } catch (\Throwable $e) {
            return $jenis.'; filename="surat-pengantar.pdf"';
        }
    }

    /** @param  array<int, mixed>  $x */
    private static function rapikan(array $x): array
    {
        $hasil = [];
        foreach ($x as $i) {
            if (! is_array($i) || empty($i['path']) || ! str_starts_with((string) $i['path'], self::FOLDER.'/')) {
                continue;
            }
            $hasil[] = [
                'path' => (string) $i['path'],
                'nama' => mb_substr((string) ($i['nama'] ?? 'surat-pengantar.pdf'), 0, 300),
                'ukuran' => max(0, (int) ($i['ukuran'] ?? 0)),
            ];
        }

        return $hasil;
    }
}
