<?php

namespace App\Http\Controllers\Career\KonfirmasiJadwal;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Support\Career\Kalimat;
use App\Support\Portal\PenilaiWaktu;
use App\Support\Portal\Potret;
use App\Support\Sinkron\Outbox;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Vinkla\Hashids\Facades\Hashids;

/**
 * WEB CAREERS — KONFIRMASI KEHADIRAN dari sisi kandidat (zona luar).
 *
 * DUA PINTU, SATU ATURAN:
 *   - Halaman TANPA login dari tombol di surel undangan. Tautannya bertanda
 *     tangan (kunci TAUTAN_KUNCI, dibuat zona dalam), berumur, terikat VERSI
 *     jadwal, dan membawa kode lamaran (`l`) yang ikut ditandatangani.
 *   - PORTAL KANDIDAT (sudah login): dijawab langsung di kartu jadwal;
 *     kepemilikan dibuktikan potret akun yang masuk.
 *
 * Isi halaman & aturan jawabnya datang dari POTRET (konfirmasi.{id}). Yang
 * diperiksa di sini adalah yang bisa dinilai kandidat saat itu juga — versi,
 * batas, pilihan, alasan, kalimat penjelasan, usulan — supaya umpan baliknya
 * instan. Jawabannya dicatat sebagai peristiwa Konfirmasi.Dijawab / Dicabut;
 * zona dalam yang memutuskan dan memperbarui potret.
 */
class KonfirmasiJadwalController extends Controller
{
    private const HALAMAN = 'Career/portal/KonfirmasiJadwal';

    /** Kode jawaban yang dianggap "sudah menyatakan" — mengubahnya tunduk aturan ubah. */
    private const SUDAH_MENYATAKAN = ['AKAN_HADIR', 'JADWAL_LAIN'];

    /** GET /karir/konfirmasi/{id}/{versi}?l={kode lamaran} */
    public function halaman(Request $request, string $id, string $versi)
    {
        if (! URL::hasCorrectSignature($request)) {
            return $this->tampil(['keadaan' => 'TAUTAN_RUSAK']);
        }
        if (! URL::signatureHasNotExpired($request)) {
            return $this->tampil(['keadaan' => 'TAUTAN_KEDALUWARSA']);
        }

        $sasaran = $this->lewatTautan($request, $id);
        if (! $sasaran) {
            return $this->tampil(['keadaan' => 'TAUTAN_RUSAK']);
        }

        return $this->tampil($this->muatan($sasaran, (int) $versi));
    }

    /** POST /karir/konfirmasi/{id}/{versi}/jawab (bertanda tangan) */
    public function jawab(Request $request, string $id, string $versi)
    {
        $sasaran = $this->lewatTautan($request, $id);

        return $sasaran
            ? $this->jawabUntuk($request, $sasaran, (int) $versi, 'TAUTAN')
            : ResponseHelper::error('Jadwal tidak ditemukan.', 404);
    }

    /** POST /karir/konfirmasi/{id}/{versi}/cabut — batalkan permintaan jadwal lain. */
    public function cabut(Request $request, string $id, string $versi)
    {
        $sasaran = $this->lewatTautan($request, $id);

        return $sasaran
            ? $this->cabutUntuk($request, $sasaran, (int) $versi, 'TAUTAN')
            : ResponseHelper::error('Jadwal tidak ditemukan.', 404);
    }

    /** POST /kandidat/konfirmasi/{id}/{versi}/jawab — dari kartu jadwal portal. */
    public function jawabPortal(Request $request, string $id, string $versi)
    {
        $sasaran = $this->milikSendiri($id);

        return $sasaran
            ? $this->jawabUntuk($request, $sasaran, (int) $versi, 'PORTAL')
            : ResponseHelper::error('Jadwal tidak ditemukan.', 404);
    }

    /** POST /kandidat/konfirmasi/{id}/{versi}/cabut — dari kartu jadwal portal. */
    public function cabutPortal(Request $request, string $id, string $versi)
    {
        $sasaran = $this->milikSendiri($id);

        return $sasaran
            ? $this->cabutUntuk($request, $sasaran, (int) $versi, 'PORTAL')
            : ResponseHelper::error('Jadwal tidak ditemukan.', 404);
    }

    // ════════════════════════════════════════════════════════════════════════

    /**
     * Sasaran lewat tautan surel: kode lamaran dari query yang IKUT
     * DITANDATANGANI, jadi tidak bisa ditukar ke lamaran orang lain.
     *
     * @return array{id: int, kode: string, idUsers: int, konf: array, potret: array}|null
     */
    private function lewatTautan(Request $request, string $id): ?array
    {
        $realId = Hashids::decode($id)[0] ?? null;
        $kode = (string) $request->query('l', '');
        $potret = $realId && $kode !== '' ? Potret::lewatTautan($kode) : null;
        $konf = $potret['konfirmasi'][(string) $realId] ?? null;

        return is_array($konf) ? [
            'id' => (int) $realId,
            'kode' => $kode,
            'idUsers' => (int) $potret['_idUsers'],
            'konf' => $konf,
            'potret' => $potret,
        ] : null;
    }

    /** Sasaran pintu PORTAL: hanya jadwal yang tercantum di potret akun yang masuk. */
    private function milikSendiri(string $id): ?array
    {
        $userId = (int) session('career_auth.id');
        $realId = Hashids::decode($id)[0] ?? null;
        $temu = $realId && $userId ? Potret::cari($userId, 'konfirmasi', (int) $realId) : null;

        return $temu ? [
            'id' => (int) $realId,
            'kode' => $temu['kode'],
            'idUsers' => $userId,
            'konf' => $temu['isi'],
            'potret' => $temu['potret'],
        ] : null;
    }

    /** Blok jawab yang sudah disegarkan waktunya (batas, H-n, usulan). */
    private static function blok(array $konf): array
    {
        return PenilaiWaktu::segarkan((array) ($konf['halaman'] ?? []));
    }

    /** Satu jalur jawab untuk kedua pintu. */
    private function jawabUntuk(Request $request, array $s, int $versi, string $kanal)
    {
        $data = $request->validate([
            'kode' => 'required|string|max:20',
            'alasan' => 'nullable|string|max:30',
            'catatan' => 'nullable|string|max:1000',
            'usulan' => 'nullable|array|max:5',
            'usulan.*.tanggal' => 'required_with:usulan|date_format:Y-m-d',
            'usulan.*.bagian' => 'required_with:usulan|string|max:10',
            'statusDilihat' => 'nullable|string|max:20',
            // Centang "saya mengerti lamaran saya akan ditutup" — WAJIB saat
            // tidak melanjutkan.
            'paham' => 'nullable|boolean',
        ]);

        $konf = $s['konf'];
        $h = self::blok($konf);
        $status = (string) ($h['konfirmasi']['status'] ?? '');

        if ((int) ($konf['versi'] ?? 0) !== $versi) {
            return self::tolak(409, 'Jadwalnya sudah diperbarui. Buka undangan terbaru, lalu jawab di sana.', $status, true);
        }
        if (! empty($h['konfirmasi']['final'])) {
            return self::tolak(409, 'Pernyataanmu sudah kami terima dan tidak bisa diubah lewat halaman ini.', $status);
        }
        if (empty($h['konfirmasi']['bolehJawab'])) {
            return self::tolak(422, 'Batas konfirmasi sudah lewat. Hubungi tim rekrutmen bila berhalangan.', $status);
        }
        // Tampilan yang dilihat sudah basi (tab lain / tim lebih dulu).
        if (! empty($data['statusDilihat']) && $data['statusDilihat'] !== $status) {
            return self::tolak(409, 'Status konfirmasi baru saja berubah. Periksa keadaan terbaru, lalu jawab lagi bila perlu.', $status, true);
        }

        $pilihan = collect($h['pilihan'] ?? [])->firstWhere('kode', $data['kode']);
        if (! $pilihan) {
            return self::tolak(422, 'Pilihan jawaban tidak dikenali.', $status);
        }
        $bukaPermintaan = (bool) ($pilihan['bukaPermintaan'] ?? false);
        $mundur = (bool) ($pilihan['sinyalMundur'] ?? false);

        if ($data['kode'] === $status && ! $bukaPermintaan) {
            return ResponseHelper::success(['status' => $status], 'Jawabanmu sudah tercatat.');
        }
        if ($data['kode'] === $status && $bukaPermintaan) {
            return self::tolak(409, 'Permintaan jadwal lain sudah ada dan sedang ditinjau tim.', $status);
        }

        // KOMITMEN: jawaban yang sudah ada hanya boleh diubah sesuai aturan
        // tipenya. "Tidak melanjutkan" tetap bebas.
        if (in_array($status, self::SUDAH_MENYATAKAN, true) && ! $mundur && ! empty($h['ubah']['terkunci'])) {
            return self::tolak(422, (string) (($h['ubah']['pesanKunci'] ?? null) ?: 'Jawabanmu sudah tidak bisa diubah.'), $status, true);
        }

        if ($bukaPermintaan && ($galat = self::periksaPermintaan($h, $data))) {
            return self::tolak(422, $galat, $status);
        }
        if ($mundur && ($galat = self::periksaMundur($h, $data, $request->boolean('paham')))) {
            return self::tolak(422, $galat, $status);
        }

        $isian = [
            'kode' => $data['kode'],
            'alasan' => ($data['alasan'] ?? null) ?: null,
            'catatan' => self::rapikan($data['catatan'] ?? null),
            'usulan' => array_values($data['usulan'] ?? []),
            'paham' => $request->boolean('paham'),
        ];

        // Jawaban yang SAMA persis (klik dua kali) = satu peristiwa.
        $sidik = substr(hash('sha256', json_encode($isian, JSON_UNESCAPED_UNICODE)), 0, 16);
        DB::transaction(fn () => Outbox::tulis(
            Outbox::KONFIRMASI_DIJAWAB,
            "Konfirmasi.Dijawab:{$s['id']}:{$versi}:{$s['idUsers']}:{$sidik}",
            $s['idUsers'],
            [
                'kode' => $s['kode'],
                'akun' => ['id_publik' => $s['idUsers']],
                'aktivitas_id' => $s['id'],
                'versi' => $versi,
                'status_dilihat' => $status,
                'jawaban' => $isian,
                'pelaku' => $this->pelaku($request, $kanal),
            ],
            $kanal,
        ));

        Log::info("[KONFIRMASI] aktivitas #{$s['id']} v{$versi} dijawab {$data['kode']} lewat {$kanal}.");

        $pesan = match (true) {
            $mundur => 'Terima kasih sudah memberi tahu. Pernyataanmu kami teruskan ke tim rekrutmen.',
            $bukaPermintaan => 'Permintaan jadwal lain sudah kami terima. Tim rekrutmen akan meninjau usulanmu.',
            default => 'Terima kasih! Jawabanmu sudah kami terima.',
        };

        return ResponseHelper::success(['status' => $data['kode']], $pesan);
    }

    /** Batalkan permintaan jadwal lain. */
    private function cabutUntuk(Request $request, array $s, int $versi, string $kanal)
    {
        $konf = $s['konf'];
        $h = self::blok($konf);
        $status = (string) ($h['konfirmasi']['status'] ?? '');

        if ((int) ($konf['versi'] ?? 0) !== $versi) {
            return self::tolak(409, 'Jadwalnya sudah diperbarui. Muat ulang halaman.', $status, true);
        }
        if (($h['permintaan']['status'] ?? null) !== 'TERBUKA' && $status !== 'JADWAL_LAIN') {
            return self::tolak(409, 'Tidak ada permintaan jadwal lain yang bisa dibatalkan.', $status);
        }

        DB::transaction(fn () => Outbox::tulis(
            Outbox::KONFIRMASI_DICABUT,
            'Konfirmasi.Dicabut:'.Str::uuid(),
            $s['idUsers'],
            [
                'kode' => $s['kode'],
                'akun' => ['id_publik' => $s['idUsers']],
                'aktivitas_id' => $s['id'],
                'versi' => $versi,
                'pelaku' => $this->pelaku($request, $kanal),
            ],
            $kanal,
        ));

        return ResponseHelper::success(['status' => null], 'Permintaan jadwal lain dibatalkan.');
    }

    /** Minta jadwal lain: alasan dari master + usulan dalam rentang & jumlah yang diizinkan. */
    private static function periksaPermintaan(array $h, array $data): ?string
    {
        $alasan = collect($h['alasan']['JADWAL_LAIN'] ?? [])->firstWhere('kode', (string) ($data['alasan'] ?? ''));
        if (! $alasan) {
            return 'Pilih alasan kenapa waktu ini tidak bisa.';
        }
        if (! empty($alasan['butuhCatatan']) && self::rapikan($data['catatan'] ?? null) === null) {
            return 'Ceritakan sedikit kenapa waktu ini tidak bisa.';
        }
        if ((int) ($h['jatah']['sisa'] ?? 1) < 1) {
            return 'Kesempatan meminta jadwal lain untuk aktivitas ini sudah habis. Hubungi tim rekrutmen.';
        }

        $aturan = (array) ($h['aturanUsulan'] ?? []);
        $usulan = array_values($data['usulan'] ?? []);
        if (! $usulan) {
            return 'Usulkan setidaknya satu waktu pengganti.';
        }
        if (count($usulan) > (int) ($aturan['maks'] ?? 3)) {
            return 'Usulan waktu paling banyak '.(int) ($aturan['maks'] ?? 3).'.';
        }
        $bagian = array_column((array) ($aturan['bagian'] ?? []), 'kode');
        foreach ($usulan as $u) {
            if (($aturan['tanggalMin'] ?? '') !== '' && $u['tanggal'] < $aturan['tanggalMin']) {
                return 'Usulan waktu paling cepat besok.';
            }
            if (($aturan['tanggalMaks'] ?? '') !== '' && $u['tanggal'] > $aturan['tanggalMaks']) {
                return 'Usulan waktu terlalu jauh — paling lambat '.Carbon::parse($aturan['tanggalMaks'])->translatedFormat('d M Y').'.';
            }
            if ($bagian && ! in_array($u['bagian'], $bagian, true)) {
                return 'Pilihan waktu dalam sehari tidak dikenali.';
            }
        }

        return null;
    }

    /** Tidak melanjutkan: alasan wajib, penjelasan berupa kalimat sungguhan, centang "paham". */
    private static function periksaMundur(array $h, array $data, bool $paham): ?string
    {
        $alasan = collect($h['alasan']['MUNDUR'] ?? [])->firstWhere('kode', (string) ($data['alasan'] ?? ''));
        if (! $alasan) {
            return 'Pilih alasan kenapa kamu tidak melanjutkan seleksi.';
        }
        if (! empty($alasan['butuhCatatan']) && ($galat = Kalimat::periksa((string) ($data['catatan'] ?? ''), 15))) {
            return $galat;
        }
        if (! $paham) {
            return 'Centang "Saya mengerti lamaran saya akan ditutup" untuk melanjutkan.';
        }

        return null;
    }

    private static function rapikan(?string $teks): ?string
    {
        $t = trim(preg_replace('/\s+/u', ' ', strip_tags((string) $teks)));

        return mb_strlen($t) >= 5 ? mb_substr($t, 0, 1000) : null;
    }

    /** `terkunci` memberi tahu layar untuk memuat ulang aturannya. */
    private static function tolak(int $kode, string $pesan, ?string $status, bool $terkunci = false)
    {
        return response()->json([
            'success' => false,
            'status' => $kode,
            'message' => $pesan,
            'result' => ['status' => $status ?: null, 'terkunci' => $terkunci],
        ], $kode);
    }

    /** Jejak pelaku — kanal & perangkat, tanpa menyimpan user agent utuh. */
    private function pelaku(Request $request, string $kanal): array
    {
        return [
            'jenis' => 'KANDIDAT',
            'kanal' => $kanal,
            'ip' => $request->ip(),
            'perangkat' => self::perangkat((string) $request->userAgent()),
        ];
    }

    private static function perangkat(string $ua): string
    {
        $os = match (true) {
            str_contains($ua, 'Android') => 'Android',
            str_contains($ua, 'iPhone') || str_contains($ua, 'iPad') => 'iOS',
            str_contains($ua, 'Windows') => 'Windows',
            str_contains($ua, 'Mac OS') => 'macOS',
            str_contains($ua, 'Linux') => 'Linux',
            default => 'Lainnya',
        };
        $peramban = match (true) {
            str_contains($ua, 'Edg/') => 'Edge',
            str_contains($ua, 'OPR/') => 'Opera',
            str_contains($ua, 'Chrome/') => 'Chrome',
            str_contains($ua, 'Firefox/') => 'Firefox',
            str_contains($ua, 'Safari/') => 'Safari',
            default => 'Lainnya',
        };

        return $os.' · '.$peramban;
    }

    /** Render halaman + kepala keamanan (tanpa indeks, tanpa rujukan, tanpa bingkai). */
    private function tampil(array $props)
    {
        return Inertia::render(self::HALAMAN, $props)
            ->toResponse(request())
            ->withHeaders([
                'X-Robots-Tag' => 'noindex, nofollow',
                'Referrer-Policy' => 'no-referrer',
                'Cache-Control' => 'no-store, private',
                'Content-Security-Policy' => "frame-ancestors 'none'",
            ]);
    }

    /** Seluruh bahan halaman untuk satu tautan sah. */
    private function muatan(array $s, int $versi): array
    {
        $konf = $s['konf'];
        $props = self::blok($konf);
        $versiKini = (int) ($konf['versi'] ?? 0);
        $mulai = ($konf['mulai'] ?? null) ?: ($props['jadwal']['mulai'] ?? null);
        $keadaan = (string) ($props['keadaan'] ?? 'TAUTAN_RUSAK');

        $idPublik = DB::table('N_WEB_CAREERS_Lamaran')->where('Kode', $s['kode'])->value('Id_Lamaran');
        $portal = $idPublik ? url('/kandidat/lamaran/'.Hashids::encode($idPublik)) : url('/kandidat/portal');

        if ($versiKini !== $versi && ! in_array($keadaan, ['LAMARAN_SELESAI', 'SELESAI'], true)) {
            // Versi lama: arahkan ke undangan terbaru. Pemegang tautan sah adalah
            // kandidatnya sendiri, jadi tautan versi kini tidak memperluas akses.
            return [
                'keadaan' => 'VERSI_LAMA',
                'judul' => $props['judul'] ?? [],
                'urlTerbaru' => $this->tautan('career.konfirmasi.halaman', $s, $versiKini, $mulai),
                'jadwalTerbaru' => $props['jadwal']['waktuTeks'] ?? null,
            ];
        }

        if ($keadaan === 'TERBUKA' && $mulai && Carbon::parse($mulai)->lte(now())) {
            $keadaan = 'ACARA_LEWAT';
        }
        $props['keadaan'] = $keadaan;

        $props['url'] = ['portal' => $portal];
        if (in_array($keadaan, ['TERBUKA', 'ACARA_LEWAT'], true)) {
            $props['url'] += [
                'jawab' => $this->tautan('career.konfirmasi.jawab', $s, $versi, $mulai),
                'cabut' => $this->tautan('career.konfirmasi.cabut', $s, $versi, $mulai),
            ];
        }

        return $props;
    }

    /** Tautan bertanda tangan untuk aktivitas ini — berlaku sampai acaranya dimulai (maks. N hari). */
    private function tautan(string $rute, array $s, int $versi, ?string $mulai): string
    {
        $maks = now()->addDays(max(1, (int) config('konfirmasi.tautan_hari_maks', 60)));
        $sampai = $mulai ? Carbon::parse($mulai)->addDay() : $maks;

        return URL::temporarySignedRoute($rute, $sampai->lt($maks) ? $sampai : $maks, [
            'id' => Hashids::encode($s['id']),
            'versi' => $versi,
            'l' => $s['kode'],
        ]);
    }
}
