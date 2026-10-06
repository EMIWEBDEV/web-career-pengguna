<?php

namespace App\Http\Controllers\Career\KonfirmasiJadwal;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Career\Lamaran\LamaranController;
use App\Http\Controllers\Controller;
use App\Support\Career\JejakJadwal;
use App\Support\Career\KonfirmasiJadwal;
use App\Support\Career\UndanganJadwal;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Vinkla\Hashids\Facades\Hashids;

/**
 * WEB CAREERS — KONFIRMASI KEHADIRAN dari sisi kandidat.
 *
 * DUA PINTU, SATU ATURAN:
 *   - Halaman TANPA login, dibuka dari tombol "Konfirmasi Kehadiran" di surel.
 *     Tautannya bertanda tangan (HMAC APP_KEY), berumur, dan TERIKAT VERSI
 *     jadwal: tautan dari surel lama tidak bisa mengonfirmasi jam yang sudah
 *     diganti. Tanda tangan GET diperiksa di sini (bukan middleware `signed`)
 *     supaya tautan rusak / kedaluwarsa dijawab halaman yang ramah, bukan 403.
 *   - PORTAL KANDIDAT (sudah login): dijawab langsung di kartu jadwal tanpa
 *     pindah halaman (permintaan user 1 Okt 2026 — "jangan banyak klik dan
 *     redirect"). Pintunya memeriksa sesi + kepemilikan, bukan tanda tangan.
 *
 * Seluruh aturan (batas, jatah, komitmen, final, versi) ada di
 * KonfirmasiJadwal — kelas ini hanya menerjemahkan HTTP.
 */
class KonfirmasiJadwalController extends Controller
{
    private const HALAMAN = 'Career/portal/KonfirmasiJadwal';

    /** GET /karir/konfirmasi/{id}/{versi} */
    public function halaman(Request $request, string $id, string $versi)
    {
        if (! URL::hasCorrectSignature($request)) {
            return $this->tampil(['keadaan' => 'TAUTAN_RUSAK']);
        }
        if (! URL::signatureHasNotExpired($request)) {
            return $this->tampil(['keadaan' => 'TAUTAN_KEDALUWARSA']);
        }

        $realId = Hashids::decode($id)[0] ?? null;
        $sub = $realId && KonfirmasiJadwal::siap() ? KonfirmasiJadwal::konteks((int) $realId) : null;
        if (! $sub) {
            return $this->tampil(['keadaan' => 'TAUTAN_RUSAK']);
        }

        return $this->tampil($this->muatan($sub, (int) $versi));
    }

    /** POST /karir/konfirmasi/{id}/{versi}/jawab */
    public function jawab(Request $request, string $id, string $versi)
    {
        [$realId, $sub] = $this->sasaran($id);
        if (! $sub) {
            return ResponseHelper::error('Jadwal tidak ditemukan.', 404);
        }

        return $this->jawabUntuk($request, (int) $realId, (int) $versi, $sub);
    }

    /** POST /karir/konfirmasi/{id}/{versi}/cabut — batalkan permintaan jadwal lain. */
    public function cabut(Request $request, string $id, string $versi)
    {
        [$realId, $sub] = $this->sasaran($id);
        if (! $sub) {
            return ResponseHelper::error('Jadwal tidak ditemukan.', 404);
        }

        return self::balas(KonfirmasiJadwal::cabutPermintaan((int) $realId, (int) $versi, $this->pelaku($request, $sub)));
    }

    /** POST /kandidat/konfirmasi/{id}/{versi}/jawab — dari kartu jadwal portal. */
    public function jawabPortal(Request $request, string $id, string $versi)
    {
        [$realId, $sub] = $this->milikSendiri($id);
        if (! $sub) {
            return ResponseHelper::error('Jadwal tidak ditemukan.', 404);
        }

        return $this->jawabUntuk($request, (int) $realId, (int) $versi, $sub);
    }

    /** POST /kandidat/konfirmasi/{id}/{versi}/cabut — dari kartu jadwal portal. */
    public function cabutPortal(Request $request, string $id, string $versi)
    {
        [$realId, $sub] = $this->milikSendiri($id);
        if (! $sub) {
            return ResponseHelper::error('Jadwal tidak ditemukan.', 404);
        }

        return self::balas(KonfirmasiJadwal::cabutPermintaan((int) $realId, (int) $versi, $this->pelaku($request, $sub)));
    }

    /** Satu jalur jawab untuk kedua pintu. */
    private function jawabUntuk(Request $request, int $realId, int $versi, object $sub)
    {
        $data = $request->validate([
            'kode' => 'required|string|max:20',
            'alasan' => 'nullable|string|max:30',
            'catatan' => 'nullable|string|max:1000',
            'usulan' => 'nullable|array|max:5',
            'usulan.*.tanggal' => 'required_with:usulan|date_format:Y-m-d',
            'usulan.*.bagian' => 'required_with:usulan|string|max:10',
            'statusDilihat' => 'nullable|string|max:20',
            // Centang "saya mengerti lamaran saya akan ditutup" — WAJIB ikut
            // terkirim saat tidak melanjutkan; diperiksa KonfirmasiJadwal::jawab.
            'paham' => 'nullable|boolean',
        ]);

        return self::balas(KonfirmasiJadwal::jawab($realId, $versi, $data['kode'], [
            'alasan' => $data['alasan'] ?? null,
            'catatan' => $data['catatan'] ?? null,
            'usulan' => $data['usulan'] ?? [],
            'statusDilihat' => $data['statusDilihat'] ?? null,
            'paham' => $request->boolean('paham'),
        ], $this->pelaku($request, $sub)));
    }

    /** Hasil layanan → JSON. `terkunci` memberi tahu layar untuk memuat ulang aturannya. */
    private static function balas(array $hasil)
    {
        return $hasil['ok']
            ? ResponseHelper::success(['status' => $hasil['status'] ?? null], $hasil['pesan'])
            : response()->json([
                'success' => false,
                'status' => $hasil['kode'],
                'message' => $hasil['pesan'],
                'result' => ['status' => $hasil['status'] ?? null, 'terkunci' => (bool) ($hasil['terkunci'] ?? false)],
            ], $hasil['kode']);
    }

    /**
     * POST /karir/konfirmasi/{id}/{versi}/dibuka — halaman benar-benar dibuka
     * di peramban. Dikirim JavaScript halaman, BUKAN dicatat saat GET: pemindai
     * tautan surel (Safe Links, pratinjau Gmail) membuka GET secara otomatis
     * dan akan mengarang "sudah dibuka" untuk surel yang belum dibaca siapa pun.
     * Sekali per versi.
     */
    public function dibuka(Request $request, string $id, string $versi)
    {
        [$realId, $sub] = $this->sasaran($id);
        if (! $sub || (int) ($sub->Jadwal_Versi ?? 0) !== (int) $versi) {
            return ResponseHelper::success(null, 'Diabaikan.');
        }

        $sudah = DB::table(JejakJadwal::TABEL)
            ->where('Lamaran_Tahap_Tes_Id', $realId)
            ->where('Versi', (int) $versi)
            ->where('Aksi', KonfirmasiJadwal::A_DIBUKA)
            ->exists();

        if (! $sudah) {
            KonfirmasiJadwal::tulisJejak((int) $realId, (int) $versi, KonfirmasiJadwal::A_DIBUKA, $this->pelaku($request, $sub));
        }

        return ResponseHelper::success(null, 'Tercatat.');
    }

    // ════════════════════════════════════════════════════════════════════════

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

    /** @return array{0: ?int, 1: ?object} */
    private function sasaran(string $id): array
    {
        $realId = Hashids::decode($id)[0] ?? null;

        return [$realId, $realId && KonfirmasiJadwal::siap() ? KonfirmasiJadwal::konteks((int) $realId) : null];
    }

    /**
     * Sasaran pintu PORTAL: hanya jadwal milik akun yang sedang masuk. Admin
     * yang menengok portal tidak bisa menjawab atas nama kandidat dari sini —
     * itu jalurnya "Catat jawaban" di worklist.
     *
     * @return array{0: ?int, 1: ?object}
     */
    private function milikSendiri(string $id): array
    {
        [$realId, $sub] = $this->sasaran($id);
        $saya = (int) session('career_auth.id');

        return $sub && $saya > 0 && (int) $sub->Id_Users === $saya ? [$realId, $sub] : [null, null];
    }

    /**
     * Pelaku jawaban. Kanal PORTAL bila yang membuka sedang masuk sebagai
     * kandidat pemilik undangan; selain itu TAUTAN (dari surel).
     */
    private function pelaku(Request $request, object $sub): array
    {
        $portal = (int) session('career_auth.id') === (int) $sub->Id_Users;

        return [
            'jenis' => KonfirmasiJadwal::KANDIDAT,
            'kanal' => $portal ? KonfirmasiJadwal::K_PORTAL : KonfirmasiJadwal::K_TAUTAN,
            'nama' => (string) $sub->KandidatNama,
            'id' => (int) $sub->Id_Users,
            'ip' => $request->ip(),
            'perangkat' => self::perangkat((string) $request->userAgent()),
        ];
    }

    /** Ringkasan perangkat — cukup untuk membaca jejak, tanpa menyimpan UA utuh. */
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

    /** Seluruh bahan halaman untuk satu tautan sah. */
    private function muatan(object $sub, int $versi): array
    {
        $id = (int) $sub->Id_Lamaran_Tahap_Tes;
        $versiKini = (int) ($sub->Jadwal_Versi ?? 0);
        $judul = [
            'aktivitas' => $sub->Label,
            'posisi' => $sub->Posisi ?: $sub->ProgramNama,
            'program' => $sub->ProgramNama,
            'tahap' => $sub->TahapLabel,
            'kode' => $sub->LamaranKode,
            'nama' => $sub->KandidatNama,
        ];

        $keadaan = match (true) {
            ($sub->StatusLamaran ?? '') !== 'BERJALAN' => 'LAMARAN_SELESAI',
            ($sub->Flag_Selesai ?? 'T') === 'Y' || ! empty($sub->Jadwal_Hadir) => 'SELESAI',
            empty($sub->Jadwal_Mulai) => 'DITUNDA',
            $versiKini !== $versi => 'VERSI_LAMA',
            empty($sub->Konfirmasi_Status) => 'TIDAK_BERLAKU',
            Carbon::parse($sub->Jadwal_Mulai)->lte(now()) => 'ACARA_LEWAT',
            default => 'TERBUKA',
        };

        $props = ['keadaan' => $keadaan, 'judul' => $judul];

        // Ditunda: kandidat wajib tahu alasannya, perkiraan jadwal pengganti
        // (atau "akan dikabarkan"), dan pesan tim — bukan sekadar "ditunda".
        if ($keadaan === 'DITUNDA' && ($sub->Konfirmasi_Status ?? null) === KonfirmasiJadwal::DITUNDA) {
            $props['tunda'] = KonfirmasiJadwal::tundaUntukKandidat(KonfirmasiJadwal::infoTunda($id));
            $props['url'] = ['portal' => url('/kandidat/lamaran/'.Hashids::encode($sub->Lamaran_Id))];

            return $props;
        }

        // Versi lama yang masih punya penerus: arahkan ke undangan terbaru.
        // Pemegang tautan sah untuk aktivitas ini adalah kandidatnya sendiri,
        // jadi menerbitkan tautan versi kini tidak memperluas akses siapa pun.
        if ($keadaan === 'VERSI_LAMA') {
            $props['urlTerbaru'] = ! empty($sub->Konfirmasi_Status)
                ? KonfirmasiJadwal::tautan($id, $versiKini, (string) $sub->Jadwal_Mulai)
                : null;
            $props['jadwalTerbaru'] = UndanganJadwal::waktuTeks($sub->Jadwal_Mulai, $sub->Jadwal_Selesai);

            return $props;
        }

        if (! in_array($keadaan, ['TERBUKA', 'ACARA_LEWAT'], true)) {
            return $props;
        }

        $mode = UndanganJadwal::mode($sub->Jadwal_Mode);
        $daring = strtoupper((string) $sub->Jadwal_Mode) === 'DARING';
        $tempat = $daring ? null : LamaranController::tempatJadwal($sub);

        $props['jadwal'] = [
            'waktuTeks' => UndanganJadwal::waktuTeks($sub->Jadwal_Mulai, $sub->Jadwal_Selesai),
            'mulai' => (string) $sub->Jadwal_Mulai,
            'modeNama' => $mode->Nama ?? $sub->Jadwal_Mode,
            'daring' => $daring,
            'link' => $daring ? $sub->Jadwal_Link : null,
            'tempat' => $tempat ? [
                'nama' => $tempat['nama'] ?? null,
                'alamat' => $tempat['alamatLengkap'] ?? null,
                'mapsUrl' => $tempat['mapsUrl'] ?? null,
            ] : null,
            'detailLokasi' => $daring ? null : ($sub->Jadwal_Lokasi ?: null),
            'kontak' => $sub->Jadwal_Kontak ?? null,
            'instruksi' => $sub->Jadwal_Catatan ?: null,
        ];

        // Status, pilihan, alasan, permintaan, jatah, aturan usulan & aturan
        // ubah — SAMA PERSIS dengan kartu jadwal portal (bahanJawab).
        $mulai = (string) $sub->Jadwal_Mulai;
        $props += KonfirmasiJadwal::bahanJawab($sub, $versi, [
            'jawab' => KonfirmasiJadwal::tautan($id, $versi, $mulai, 'jawab'),
            'cabut' => KonfirmasiJadwal::tautan($id, $versi, $mulai, 'cabut'),
            'dibuka' => KonfirmasiJadwal::tautan($id, $versi, $mulai, 'dibuka'),
            'portal' => url('/kandidat/lamaran/'.Hashids::encode($sub->Lamaran_Id)),
        ]);

        Log::channel('web_career')->info("[KONFIRMASI] halaman aktivitas #{$id} v{$versi} dimuat ({$keadaan}).");

        return $props;
    }
}
