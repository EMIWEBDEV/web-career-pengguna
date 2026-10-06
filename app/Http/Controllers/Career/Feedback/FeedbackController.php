<?php

namespace App\Http\Controllers\Career\Feedback;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Support\Portal\Potret;
use App\Support\Portal\TautanFeedback;
use App\Support\Sinkron\Outbox;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

/**
 * WEB CAREER — isian FEEDBACK kandidat (zona luar).
 *
 * Tautan dari surel: /feedback/{kode}/{hashids}/{signature} — lihat TautanFeedback.
 *
 * Formulir & pertanyaannya dibaca dari POTRET lamaran (`feedback`); jawabannya
 * dicatat sebagai peristiwa Feedback.Dikirim — kunci idempotennya per
 * feedback, jadi kiriman kedua dikenali sebagai "sudah terkirim".
 */
class FeedbackController extends Controller
{
    /** @return array{kode: string, idUsers: int, hasilAkhir: ?string, isi: array}|null */
    private function sasaran(string $kode, string $hashids, string $signature): ?array
    {
        $sah = TautanFeedback::urai($kode, $hashids, $signature);
        if (! $sah) {
            return null;
        }
        [$feedbackId] = $sah;

        $potret = Potret::lewatTautan($kode);
        $isi = $potret ? collect($potret['feedback'] ?? [])->firstWhere('id', $feedbackId) : null;

        return is_array($isi) ? [
            'kode' => $kode,
            'idUsers' => (int) $potret['_idUsers'],
            'hasilAkhir' => $potret['lamaran']['hasilAkhir'] ?? null,
            'isi' => $isi,
        ] : null;
    }

    private static function kunciIdem(array $isi): string
    {
        return 'Feedback.Dikirim:'.(int) $isi['id'];
    }

    private static function kedaluwarsa(array $isi): bool
    {
        $dibuat = ! empty($isi['dibuatAt']) ? Carbon::parse($isi['dibuatAt']) : null;

        return $dibuat !== null && $dibuat->copy()->addDays(max(1, (int) ($isi['durasiHari'] ?? 30)))->isPast();
    }

    /** Sudah terisi menurut potret, ATAU kirimannya sudah tercatat di Outbox. */
    private static function sudahTerisi(array $isi): bool
    {
        return ($isi['status'] ?? null) === 'TERISI' || Outbox::status(self::kunciIdem($isi)) !== null;
    }

    public function show(string $kode, string $hashids, string $signature)
    {
        $s = $this->sasaran($kode, $hashids, $signature);
        if (! $s) {
            return Inertia::render('Career/FeedbackForm', [
                'error' => 'invalid',
                'message' => 'Link tidak valid atau sudah kadaluarsa.',
            ]);
        }

        $isi = $s['isi'];
        if (self::kedaluwarsa($isi)) {
            return Inertia::render('Career/FeedbackForm', [
                'error' => 'expired',
                'message' => 'Link sudah kadaluarsa. Durasi: '.((int) ($isi['durasiHari'] ?? 30)).' hari.',
            ]);
        }

        if (self::sudahTerisi($isi)) {
            return Inertia::render('Career/FeedbackForm', [
                'error' => 'already_submitted',
                'message' => 'Feedback sudah dikirim. Terima kasih!',
            ]);
        }

        return Inertia::render('Career/FeedbackForm', [
            'feedback' => ['nama' => $isi['formNama'] ?? null],
            'pertanyaan' => array_values($isi['pertanyaan'] ?? []),
            'is_wajib' => $s['hasilAkhir'] === 'DITERIMA',
            'mode_tampilan' => $isi['modeTampilan'] ?? 'SCROLL',
            // Dibuka dari portal (sudah login) atau dari surel.
            'is_authenticated' => session()->has('career_auth'),
        ]);
    }

    public function submit(string $kode, string $hashids, string $signature, Request $request)
    {
        $s = $this->sasaran($kode, $hashids, $signature);
        if (! $s) {
            return ResponseHelper::error('Link tidak valid', 404);
        }

        $isi = $s['isi'];
        if (self::kedaluwarsa($isi)) {
            return ResponseHelper::error('Link sudah kadaluarsa', 404);
        }
        if (self::sudahTerisi($isi)) {
            return ResponseHelper::error('Feedback sudah dikirim sebelumnya', 409);
        }

        $pertanyaan = collect($isi['pertanyaan'] ?? []);
        $validated = $request->validate([
            'jawaban' => ['required', 'array', 'min:'.$pertanyaan->count(), 'max:'.$pertanyaan->count()],
            'jawaban.*.id_pertanyaan' => ['required', 'integer'],
            'jawaban.*.jawaban' => ['required', 'string'],
        ]);

        // Jawaban diperkaya SNAPSHOT pertanyaannya (integritas historis) — dan
        // nilai skala diperiksa terhadap rentangnya.
        $jawaban = [];
        foreach ($validated['jawaban'] as $jwb) {
            $q = $pertanyaan->firstWhere('Id_Master_Feedback_Pertanyaan', (int) $jwb['id_pertanyaan']);
            if (! $q) {
                return ResponseHelper::error('Pertanyaan tidak dikenali.', 422);
            }
            if (in_array($q['Tipe'] ?? null, ['RATING', 'NPS', 'LIKERT'], true)) {
                $nilai = (float) $jwb['jawaban'];
                $min = $q['Skala_Min'] ?? 1;
                $max = $q['Skala_Max'] ?? 5;
                if ($nilai < $min || $nilai > $max) {
                    return ResponseHelper::error("Nilai untuk \"{$q['Label']}\" harus antara {$min}-{$max}", 422);
                }
            }
            $jawaban[] = $jwb + [
                'label_snapshot' => $q['Label'] ?? null,
                'tipe_snapshot' => $q['Tipe'] ?? null,
                'opsi_snapshot' => $q['Opsi'] ?? null,
                'skala_min_snapshot' => $q['Skala_Min'] ?? null,
                'skala_max_snapshot' => $q['Skala_Max'] ?? null,
            ];
        }

        $hasil = DB::transaction(fn () => Outbox::tulis(Outbox::FEEDBACK_DIKIRIM, self::kunciIdem($isi), $s['idUsers'], [
            'kode' => $s['kode'],
            'akun' => ['id_publik' => $s['idUsers']],
            'feedback_id' => (int) $isi['id'],
            'jawaban' => $jawaban,
            'dikirim_at' => now()->toIso8601String(),
        ], 'feedback'));

        if ($hasil['hasil'] === 'DUPLIKAT') {
            return ResponseHelper::error('Feedback sudah dikirim sebelumnya', 409);
        }

        $redirectTo = $s['hasilAkhir'] === 'DITERIMA' ? '/kandidat/portal' : null;

        return ResponseHelper::success(['redirect_to' => $redirectTo], 'Feedback berhasil dikirim');
    }
}
