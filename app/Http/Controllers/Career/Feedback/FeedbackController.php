<?php

namespace App\Http\Controllers\Career\Feedback;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Support\Career\FeedbackService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class FeedbackController extends Controller
{
    public function __construct(private FeedbackService $service) {}

    public function show($hashids, $signature)
    {
        $feedback = $this->service->verifyToken($hashids, $signature);
        if (! $feedback) {
            return Inertia::render('Career/FeedbackForm', [
                'error' => 'invalid',
                'message' => 'Link tidak valid atau sudah kadaluarsa.',
            ]);
        }

        if ($this->service->isExpired($feedback)) {
            return Inertia::render('Career/FeedbackForm', [
                'error' => 'expired',
                'message' => 'Link sudah kadaluarsa. Durasi: ' . ($feedback->Durasi_Hari ?? 30) . ' hari.',
            ]);
        }

        if ($feedback->Status_Pengisian === 'TERISI') {
            return Inertia::render('Career/FeedbackForm', [
                'error' => 'already_submitted',
                'message' => 'Feedback sudah dikirim. Terima kasih!',
            ]);
        }

        $pertanyaan = DB::table('N_WEB_CAREERS_Master_Feedback_Pertanyaan')
            ->where('Master_Feedback_Form_Id', $feedback->Master_Feedback_Form_Id)
            ->where('Flag_Cancellation', 'T')
            ->orderBy('Urutan')
            ->get()
            ->map(function ($p) {
                $p->Opsi = $p->Opsi ? json_decode($p->Opsi) : null;
                $p->Skala_Min = $p->Skala_Min !== null ? (int) $p->Skala_Min : null;
                $p->Skala_Max = $p->Skala_Max !== null ? (int) $p->Skala_Max : null;
                return $p;
            });

        $lamaran = DB::table('N_WEB_CAREERS_Lamaran')
            ->where('Id_Lamaran', $feedback->Lamaran_Id)
            ->first();
        $isWajib = $lamaran && $lamaran->Hasil_Akhir === 'DITERIMA';

        // Deteksi apakah user sudah login (akses dari portal) atau belum (dari email)
        $isAuthenticated = session()->has('career_auth');

        return Inertia::render('Career/FeedbackForm', [
            'feedback' => $feedback,
            'pertanyaan' => $pertanyaan,
            'is_wajib' => $isWajib,
            'mode_tampilan' => $feedback->Mode_Tampilan ?? 'SCROLL',
            'is_authenticated' => $isAuthenticated,
        ]);
    }

    public function submit($hashids, $signature, Request $request)
    {
        $feedback = $this->service->verifyToken($hashids, $signature);
        if (! $feedback) {
            return ResponseHelper::error('Link tidak valid', 404);
        }

        if ($this->service->isExpired($feedback)) {
            return ResponseHelper::error('Link sudah kadaluarsa', 404);
        }

        if ($feedback->Status_Pengisian === 'TERISI') {
            return ResponseHelper::error('Feedback sudah dikirim sebelumnya', 404);
        }

        $pertanyaan = DB::table('N_WEB_CAREERS_Master_Feedback_Pertanyaan')
            ->where('Master_Feedback_Form_Id', $feedback->Master_Feedback_Form_Id)
            ->where('Flag_Cancellation', 'T')
            ->get();

        $rules = [
            'jawaban' => ['required', 'array', 'min:' . $pertanyaan->count(), 'max:' . $pertanyaan->count()],
            'jawaban.*.id_pertanyaan' => ['required', 'integer'],
            'jawaban.*.jawaban' => ['required', 'string'],
        ];
        $validated = $request->validate($rules);

        foreach ($pertanyaan as $p) {
            $jwb = collect($validated['jawaban'])->firstWhere('id_pertanyaan', $p->Id_Master_Feedback_Pertanyaan);
            if ($jwb && in_array($p->Tipe, ['RATING', 'NPS', 'LIKERT'])) {
                $nilai = (float) $jwb['jawaban'];
                $min = $p->Skala_Min ?? 1;
                $max = $p->Skala_Max ?? 5;
                if ($nilai < $min || $nilai > $max) {
                    return ResponseHelper::error("Nilai untuk \"{$p->Label}\" harus antara {$min}-{$max}", 422);
                }
            }
        }

        // Perkaya jawaban dengan snapshot pertanyaan (data integrity historis)
        $jawabanDenganSnapshot = [];
        foreach ($validated['jawaban'] as $jwb) {
            $q = $pertanyaan->firstWhere('Id_Master_Feedback_Pertanyaan', $jwb['id_pertanyaan']);
            $jwb['label_snapshot'] = $q->Label ?? null;
            $jwb['tipe_snapshot'] = $q->Tipe ?? null;
            $jwb['opsi_snapshot'] = $q->Opsi ?? null;
            $jwb['skala_min_snapshot'] = $q->Skala_Min ?? null;
            $jwb['skala_max_snapshot'] = $q->Skala_Max ?? null;
            $jawabanDenganSnapshot[] = $jwb;
        }

        $success = $this->service->simpanJawaban($feedback->Id_Feedback_Jawaban, $jawabanDenganSnapshot);

        if (! $success) {
            return ResponseHelper::error('Feedback sudah dikirim sebelumnya', 409);
        }

        $lamaran = DB::table('N_WEB_CAREERS_Lamaran')
            ->where('Id_Lamaran', $feedback->Lamaran_Id)
            ->first();
        $redirectTo = $lamaran && $lamaran->Hasil_Akhir === 'DITERIMA' ? '/kandidat/portal' : null;

        return ResponseHelper::success(['redirect_to' => $redirectTo], 'Feedback berhasil dikirim');
    }

    public function status($lamaranId)
    {
        $userId = session('career_auth.id');
        $feedback = DB::table('N_WEB_CAREERS_Feedback_Jawaban as fj')
            ->join('N_WEB_CAREERS_Lamaran as l', 'fj.Lamaran_Id', '=', 'l.Id_Lamaran')
            ->where('l.Id_Users', $userId)
            ->where('l.Id_Lamaran', $lamaranId)
            ->where('fj.Flag_Cancellation', 'T')
            ->select('fj.Id_Feedback_Jawaban', 'fj.Status_Pengisian', 'l.Hasil_Akhir')
            ->first();

        if (! $feedback) {
            return ResponseHelper::success(['status' => 'TIDAK_ADA']);
        }

        return ResponseHelper::success([
            'status' => $feedback->Status_Pengisian,
            'is_wajib' => $feedback->Hasil_Akhir === 'DITERIMA',
        ]);
    }
}
