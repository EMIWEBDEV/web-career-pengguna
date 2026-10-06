<?php

namespace App\Http\Controllers\Career\Feedback;

use App\Helpers\FormatTanggalHelper;
use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Jobs\Career\FeedbackExportJob;
use App\Support\Career\FeedbackService;
use App\Support\CareerShell;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class FeedbackAdminController extends Controller
{
    public function __construct(private FeedbackService $service) {}

    public function dashboard()
    {
        return Inertia::render('Career/admin/feedback-dashboard/Index',
            CareerShell::props('/karir/feedback-dashboard', 'Feedback Dashboard')
        );
    }

    public function chartData(Request $request)
    {
        $formId = $request->input('form_id');
        $programId = $request->input('program_id');
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        $formIds = is_array($formId) ? array_filter(array_map('intval', $formId)) : ($formId !== null && $formId !== '' ? array_filter(array_map('intval', explode(',', $formId))) : []);
        $programIds = is_array($programId) ? array_filter(array_map('intval', $programId)) : ($programId !== null && $programId !== '' ? array_filter(array_map('intval', explode(',', $programId))) : []);

        $firstFormId = !empty($formIds) ? reset($formIds) : null;
        $firstProgId = !empty($programIds) ? reset($programIds) : null;

        $overview = $this->service->aggregate($firstFormId, $firstProgId, $dateFrom, $dateTo);

        // Layer 1: Health Bar
        $satisfactionIndex = $this->calcSatisfactionIndex($overview);

        // Layer 2: Comparison View
        $aspekScorecard = $this->getAspekScorecard($firstFormId, $firstProgId, $dateFrom, $dateTo);
        $lolosVsGagal = $this->getLolosVsGagal($firstFormId, $dateFrom, $dateTo);
        $programComparison = $this->getProgramComparison($firstFormId, $dateFrom, $dateTo);

        // Layer 3: Tabs
        $ratingTrend = $this->getRatingTrend($firstFormId, $firstProgId, $dateFrom, $dateTo);
        $npsTrend = $this->getNpsTrend($firstFormId, $firstProgId, $dateFrom, $dateTo);
        $textVoice = $this->getTextVoice($firstFormId, $firstProgId, $dateFrom, $dateTo, 20);
        $perPertanyaan = $firstFormId ? $this->getPerPertanyaan($firstFormId, $dateFrom, $dateTo) : null;

        return ResponseHelper::success([
            'overview'            => $overview,
            'satisfaction_index'  => $satisfactionIndex,
            'aspek_scorecard'     => $aspekScorecard,
            'lolos_vs_gagal'      => $lolosVsGagal,
            'program_comparison'  => $programComparison,
            'rating_trend'        => $ratingTrend,
            'nps_trend'           => $npsTrend,
            'text_voice'          => $textVoice,
            'per_pertanyaan'      => $perPertanyaan,
        ]);
    }

    public function detail($feedbackId)
    {
        $feedback = DB::table('N_WEB_CAREERS_Feedback_Jawaban as fj')
            ->join('N_WEB_CAREERS_Lamaran as l', 'fj.Lamaran_Id', '=', 'l.Id_Lamaran')
            ->leftJoin('N_WEB_CAREERS_Program as p', 'l.Program_Id', '=', 'p.Id_Program')
            ->leftJoin('N_WEB_CAREERS_Users as u', 'l.Id_Users', '=', 'u.Id_Users')
            ->leftJoin('N_WEB_CAREERS_Master_Feedback_Form as ff', 'fj.Master_Feedback_Form_Id', '=', 'ff.Id_Master_Feedback_Form')
            ->where('fj.Id_Feedback_Jawaban', $feedbackId)
            ->select('fj.*', 'l.Kode as Kode_Lamaran', 'l.Hasil_Akhir', 'p.Nama as Program_Nama',
                'u.Nama as Nama_Kandidat', 'u.Email', 'ff.Nama as Form_Nama')
            ->first();

        if (! $feedback) return ResponseHelper::error('Feedback tidak ditemukan', 404);

        $jawaban = DB::table('N_WEB_CAREERS_Feedback_Jawaban_Detail as d')
            ->where('d.Feedback_Jawaban_Id', $feedbackId)
            ->select('d.Label_Snapshot as Label', 'd.Tipe_Snapshot as Tipe', 'd.Opsi_Snapshot as Opsi',
                     'd.Jawaban', 'd.Skala_Min_Snapshot', 'd.Skala_Max_Snapshot')
            ->orderBy('d.Id_Feedback_Jawaban_Detail')
            ->get();

        $feedback->jawaban = $jawaban;
        return ResponseHelper::success($feedback);
    }

    public function requestExport(Request $request)
    {
        $validated = $request->validate([
            'form_id' => 'required|integer',
            'program_id' => 'nullable|integer',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
        ]);

        $now = FormatTanggalHelper::getCurrentTime();
        $userId = session('career_auth.id');

        $exportId = DB::table('N_WEB_CAREERS_Export_Log')->insertGetId([
            'Export_Type' => 'FEEDBACK',
            'Id_Users' => $userId,
            'Keterangan' => 'Export Feedback ' . FormatTanggalHelper::format($now, true),
            'Filters_Json' => json_encode($validated),
            'Status_Export' => 'DIPROSES',
            'Created_At' => $now,
        ], 'Id_Export');

        // Nama queue ditentukan job-nya sendiri (hanya berlaku di Cloud Tasks) —
        // lihat AntreanWebCareers. Dipaksa di sini dulu, sehingga
        // `php artisan queue:work` yang mendengarkan queue 'default' tak melihatnya.
        FeedbackExportJob::dispatch($exportId, (int) $validated['form_id'], $validated);

        return ResponseHelper::success(['export_id' => $exportId], 'Export dimulai');
    }

    public function pollExport()
    {
        $userId = session('career_auth.id');

        // ── PANEL INI HANYA MILIK EKSPOR FEEDBACK ───────────────────────────
        //
        // Tabel Export_Log dipakai bersama beberapa fitur, dan panel melayang
        // ini dulu menyapu SELURUH isinya. Akibatnya laporan kandidat yang
        // dibuat dari worklist muncul di DUA panel sekaligus, di sudut layar
        // yang sama persis — dan panel yang bukan pemiliknya membawa dua tombol
        // yang justru berbahaya untuknya:
        //
        //   • "Unduh" mengalihkan ke signed URL GCS mentah, melewati keputusan
        //     sengaja mengalirkan berkas berisi data pribadi kandidat lewat
        //     origin sendiri (lihat LamaranController::laporanUnduh);
        //   • "X" memanggil dismissExport() yang MENGHAPUS berkasnya di GCS —
        //     admin mengira ia menutup pemberitahuan, yang terjadi berkasnya
        //     hilang.
        //
        // Disaring dari jenisnya, bukan dari kepemilikan layar: fitur baru yang
        // menumpang tabel ini tidak akan ikut muncul di sini kecuali memang
        // didaftarkan.
        $items = DB::table('N_WEB_CAREERS_Export_Log')
            ->where('Id_Users', $userId)
            ->where('Export_Type', 'FEEDBACK')
            ->where('Flag_Cancellation', 'T')
            ->where(function ($q) {
                $q->where('Status_Export', 'DIPROSES')
                  ->orWhere(function ($q2) {
                      $q2->where('Status_Export', 'SELESAI')
                         ->where('Completed_At', '>=', \Carbon\Carbon::now()->subDays(1));
                  });
            })
            ->orderBy('Created_At', 'DESC')
            ->get()
            ->map(function ($e) {
                $e->Created_At = FormatTanggalHelper::format($e->Created_At, true);
                $e->Completed_At = $e->Completed_At ? FormatTanggalHelper::format($e->Completed_At, true) : null;
                if ($e->File_Path) {
                    $e->File_Url = Storage::disk('gcs')->temporaryUrl($e->File_Path, now()->addMinutes(15));
                }
                return $e;
            });

        return ResponseHelper::success($items);
    }

    public function downloadExport($id)
    {
        $export = DB::table('N_WEB_CAREERS_Export_Log')
            ->where('Id_Export', $id)
            ->where('Flag_Cancellation', 'T')
            ->first();

        if (! $export || ! $export->File_Path) {
            return ResponseHelper::error('File tidak ditemukan', 404);
        }

        $url = Storage::disk('gcs')->temporaryUrl($export->File_Path, now()->addMinutes(15));
        return redirect()->away($url);
    }

    public function dismissExport($id)
    {
        $export = DB::table('N_WEB_CAREERS_Export_Log')
            ->where('Id_Export', $id)
            ->where('Flag_Cancellation', 'T')
            ->first();

        if (! $export) return ResponseHelper::error('Export tidak ditemukan', 404);

        if (! empty($export->File_Path)) {
            try {
                Storage::disk('gcs')->delete($export->File_Path);
            } catch (\Throwable $e) {
                Log::channel('feedback')->warning('Gagal hapus file export GCS', [
                    'export_id' => $id, 'path' => $export->File_Path,
                ]);
            }
        }

        $now = FormatTanggalHelper::getCurrentTime();
        DB::table('N_WEB_CAREERS_Export_Log')
            ->where('Id_Export', $id)
            ->update([
                'Flag_Cancellation' => 'Y',
                'Cancelled_At' => $now,
                'Cancelled_By' => session('career_auth.name'),
            ]);

        return ResponseHelper::success(null, 'Export dihapus');
    }

    // ═══════════════ PRIVATE HELPERS ═══════════════

    private function getProgramComparison(?int $formId, ?string $dateFrom, ?string $dateTo): array
    {
        // Step 1: Hitung rata-rata rating per feedback (pakai Tipe_Snapshot)
        $ratingPerFb = DB::table('N_WEB_CAREERS_Feedback_Jawaban_Detail as d')
            ->join('N_WEB_CAREERS_Feedback_Jawaban as fj', 'd.Feedback_Jawaban_Id', '=', 'fj.Id_Feedback_Jawaban')
            ->where('fj.Flag_Cancellation', 'T')
            ->where('fj.Status_Pengisian', 'TERISI')
            ->whereIn('d.Tipe_Snapshot', ['RATING', 'LIKERT'])
            ->when($formId, fn($q) => $q->where('fj.Master_Feedback_Form_Id', $formId))
            ->when($dateFrom, fn($q) => $q->where('fj.Submitted_At', '>=', $dateFrom))
            ->when($dateTo, fn($q) => $q->where('fj.Submitted_At', '<=', $dateTo))
            ->selectRaw('d.Feedback_Jawaban_Id, AVG(TRY_CAST(d.Jawaban AS FLOAT)) as avg_rating')
            ->groupBy('d.Feedback_Jawaban_Id');

        // Step 2: Join dengan program untuk agregat per program
        $items = DB::table('N_WEB_CAREERS_Feedback_Jawaban as fj')
            ->join('N_WEB_CAREERS_Lamaran as l', 'fj.Lamaran_Id', '=', 'l.Id_Lamaran')
            ->join('N_WEB_CAREERS_Program as p', 'l.Program_Id', '=', 'p.Id_Program')
            ->joinSub($ratingPerFb, 'fb_rating', fn($j) => $j->on('fb_rating.Feedback_Jawaban_Id', '=', 'fj.Id_Feedback_Jawaban'))
            ->where('fj.Flag_Cancellation', 'T')
            ->where('fj.Status_Pengisian', 'TERISI')
            ->when($formId, fn($q) => $q->where('fj.Master_Feedback_Form_Id', $formId))
            ->when($dateFrom, fn($q) => $q->where('fj.Submitted_At', '>=', $dateFrom))
            ->when($dateTo, fn($q) => $q->where('fj.Submitted_At', '<=', $dateTo))
            ->selectRaw('p.Id_Program, p.Nama as Program_Nama, COUNT(*) as total_respon, AVG(fb_rating.avg_rating) as avg_rating')
            ->groupBy('p.Id_Program', 'p.Nama')
            ->orderBy('total_respon', 'DESC')
            ->get()
            ->toArray();

        return $items;
    }

    private function getNpsTrend(?int $formId, ?int $programId, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $query = DB::table('N_WEB_CAREERS_Feedback_Jawaban_Detail as d')
            ->join('N_WEB_CAREERS_Feedback_Jawaban as fj', 'd.Feedback_Jawaban_Id', '=', 'fj.Id_Feedback_Jawaban')
            ->join('N_WEB_CAREERS_Lamaran as l', 'fj.Lamaran_Id', '=', 'l.Id_Lamaran')
            ->where('fj.Flag_Cancellation', 'T')
            ->where('fj.Status_Pengisian', 'TERISI')
            ->where('d.Tipe_Snapshot', 'NPS');

        if ($formId) $query->where('fj.Master_Feedback_Form_Id', $formId);
        if ($programId) $query->where('l.Program_Id', $programId);
        if ($dateFrom) $query->where('fj.Submitted_At', '>=', $dateFrom);
        if ($dateTo) $query->where('fj.Submitted_At', '<=', $dateTo);

        // Group by month, normalize scores with their snapshot scale, lalu hitung NPS
        $rows = $query->selectRaw("
                FORMAT(fj.Submitted_At, 'yyyy-MM') as bulan,
                d.Jawaban as skor,
                d.Skala_Min_Snapshot,
                d.Skala_Max_Snapshot
            ")
            ->get()
            ->groupBy('bulan');

        $trend = [];
        foreach ($rows as $bulan => $items) {
            // Normalisasi tiap skor ke 0-10 berdasarkan skala snapshot-nya
            $normalizedScores = [];
            foreach ($items as $it) {
                $score = (int) $it->skor;
                $skalaMin = (int) ($it->Skala_Min_Snapshot ?? 0);
                $skalaMax = (int) ($it->Skala_Max_Snapshot ?? 10);
                $range = $skalaMax - $skalaMin;
                $normalizedScores[] = $range > 0 ? (($score - $skalaMin) / $range) * 10 : $score;
            }
            $trend[] = [
                'bulan' => $bulan,
                'nps' => $this->service->calculateNPS($normalizedScores),
                'total' => count($normalizedScores),
            ];
        }

        return $trend;
    }

    private function getPerPertanyaan(int $formId, ?string $dateFrom, ?string $dateTo): array
    {
        // Baca dari snapshot — data historis akurat meskipun pertanyaan master berubah
        $baseQuery = DB::table('N_WEB_CAREERS_Feedback_Jawaban_Detail as d')
            ->join('N_WEB_CAREERS_Feedback_Jawaban as fj', 'd.Feedback_Jawaban_Id', '=', 'fj.Id_Feedback_Jawaban')
            ->where('fj.Master_Feedback_Form_Id', $formId)
            ->where('fj.Status_Pengisian', 'TERISI')
            ->where('fj.Flag_Cancellation', 'T');

        if ($dateFrom) $baseQuery->where('fj.Submitted_At', '>=', $dateFrom);
        if ($dateTo) $baseQuery->where('fj.Submitted_At', '<=', $dateTo);

        $all = $baseQuery
            ->select('d.Master_Feedback_Pertanyaan_Id', 'd.Label_Snapshot', 'd.Tipe_Snapshot',
                     'd.Opsi_Snapshot', 'd.Skala_Min_Snapshot', 'd.Skala_Max_Snapshot', 'd.Jawaban')
            ->get()
            ->groupBy('Master_Feedback_Pertanyaan_Id');

        $result = [];
        foreach ($all as $pertanyaanId => $rows) {
            $first = $rows->first();
            $label = $first->Label_Snapshot ?? 'Pertanyaan #' . $pertanyaanId;
            $tipe = $first->Tipe_Snapshot ?? 'TEXTAREA';

            $item = ['id' => $pertanyaanId, 'label' => $label, 'tipe' => $tipe];
            $jawabanValues = $rows->pluck('Jawaban');

            if (in_array($tipe, ['RATING', 'LIKERT'])) {
                $scores = $jawabanValues->map(fn($v) => (float) $v)->toArray();
                $item['avg'] = count($scores) > 0 ? round(array_sum($scores) / count($scores), 1) : 0;
                $item['total'] = count($scores);
                $dist = array_fill(1, 5, 0);
                foreach ($scores as $s) {
                    $bucket = max(1, min(5, (int) $s));
                    if (isset($dist[$bucket])) $dist[$bucket]++;
                }
                $item['distribusi'] = $dist;
            } elseif ($tipe === 'NPS') {
                $skalaMin = (int) ($first->Skala_Min_Snapshot ?? 0);
                $skalaMax = (int) ($first->Skala_Max_Snapshot ?? 10);
                $scores = $jawabanValues->map(fn($v) => (int) $v)->toArray();
                $item['nps'] = $this->service->calculateNPS($scores, $skalaMin, $skalaMax);
                $item['total'] = count($scores);
            } elseif (in_array($tipe, ['RADIO', 'CHECKBOX', 'DROPDOWN'])) {
                $counts = $rows->groupBy('Jawaban')->map->count()->toArray();
                $item['counts'] = $counts;
                $item['total'] = $rows->count();
            } elseif ($tipe === 'TEXTAREA') {
                $item['recent'] = $jawabanValues->take(5)->toArray();
                $item['total'] = $rows->count();
            }

            $result[] = $item;
        }

        return $result;
    }

    private function getLolosVsGagal(?int $formId, ?string $dateFrom, ?string $dateTo): array
    {
        $query = DB::table('N_WEB_CAREERS_Feedback_Jawaban as fj')
            ->join('N_WEB_CAREERS_Lamaran as l', 'fj.Lamaran_Id', '=', 'l.Id_Lamaran')
            ->where('fj.Status_Pengisian', 'TERISI')
            ->where('fj.Flag_Cancellation', 'T')
            ->whereIn('l.Hasil_Akhir', ['DITERIMA', 'DITOLAK']);

        if ($formId) $query->where('fj.Master_Feedback_Form_Id', $formId);

        if ($dateFrom) $query->where('fj.Submitted_At', '>=', $dateFrom);
        if ($dateTo) $query->where('fj.Submitted_At', '<=', $dateTo);

        $groups = $query->selectRaw("
                l.Hasil_Akhir,
                COUNT(*) as total,
                AVG(DATEDIFF(SECOND, fj.Created_At, fj.Submitted_At)) as avg_waktu_detik
            ")
            ->groupBy('l.Hasil_Akhir')
            ->get()
            ->keyBy('Hasil_Akhir');

        $lolos = $groups['DITERIMA'] ?? null;
        $gagal = $groups['DITOLAK'] ?? null;

        return [
            'lolos' => $lolos ? ['total' => $lolos->total, 'avg_waktu_detik' => $lolos->avg_waktu_detik] : null,
            'gagal' => $gagal ? ['total' => $gagal->total, 'avg_waktu_detik' => $gagal->avg_waktu_detik] : null,
        ];
    }

    // ═══════════════ LAYER 1: HEALTH BAR ═══════════════

    /**
     * Composite Satisfaction Index (0-100).
     * Bobot: RATING 40%, LIKERT 30%, NPS (dinormalisasi) 30%.
     * Jika satu tipe tidak ada, bobot sisanya disesuaikan proporsional.
     */
    private function calcSatisfactionIndex(array $overview): ?int
    {
        $stats = collect($overview['skor_stats'] ?? [])->keyBy('Tipe');
        $score = 0;
        $weight = 0;

        foreach (['RATING' => 40, 'LIKERT' => 30, 'NPS' => 30] as $tipe => $w) {
            if (!isset($stats[$tipe]) || $stats[$tipe]->jumlah == 0) continue;
            if ($tipe === 'NPS') {
                // NPS: normalize -100..+100 → 0..100
                $npsNorm = ($overview['nps_score'] + 100) / 2;
                $score += $npsNorm * $w;
            } else {
                // RATING / LIKERT: normalize skala ke 0..100
                $avg = (float) $stats[$tipe]->rata2;
                $score += ($avg / 5) * 100 * $w / 100;
            }
            $weight += $w;
        }

        if ($weight === 0) return null;
        // Normalisasi berdasarkan bobot aktual
        return (int) round($score / $weight * 100);
    }

    // ═══════════════ LAYER 2: COMPARISON VIEW ═══════════════

    /**
     * Per-Aspek Scorecard: semua pertanyaan RATING/LIKERT, dinormalisasi ke 0-100%.
     * Normalisasi: ((skor - Skala_Min) / (Skala_Max - Skala_Min)) × 100
     * Sorted worst→best.
     */
    private function getAspekScorecard(?int $formId, ?int $programId, ?string $dateFrom, ?string $dateTo): array
    {
        $query = DB::table('N_WEB_CAREERS_Feedback_Jawaban_Detail as d')
            ->join('N_WEB_CAREERS_Feedback_Jawaban as fj', 'd.Feedback_Jawaban_Id', '=', 'fj.Id_Feedback_Jawaban')
            ->join('N_WEB_CAREERS_Lamaran as l', 'fj.Lamaran_Id', '=', 'l.Id_Lamaran')
            ->where('fj.Flag_Cancellation', 'T')
            ->where('fj.Status_Pengisian', 'TERISI')
            ->whereIn('d.Tipe_Snapshot', ['RATING', 'LIKERT'])
            ->whereNotNull('d.Jawaban');

        if ($formId) $query->where('fj.Master_Feedback_Form_Id', $formId);
        if ($programId) $query->where('l.Program_Id', $programId);
        if ($dateFrom) $query->where('fj.Submitted_At', '>=', $dateFrom);
        if ($dateTo) $query->where('fj.Submitted_At', '<=', $dateTo);

        // Normalisasi tiap skor ke 0-100% berdasarkan skala snapshot-nya, lalu AVG
        $items = $query->selectRaw("
                d.Label_Snapshot as label,
                d.Tipe_Snapshot as tipe,
                AVG(
                    ((TRY_CAST(d.Jawaban AS FLOAT) - ISNULL(d.Skala_Min_Snapshot, 1))
                    / NULLIF(ISNULL(d.Skala_Max_Snapshot, 5) - ISNULL(d.Skala_Min_Snapshot, 1), 0))
                    * 100
                ) as avg_pct,
                COUNT(*) as total
            ")
            ->groupBy('d.Label_Snapshot', 'd.Tipe_Snapshot')
            ->orderBy('avg_pct', 'ASC')
            ->get()
            ->toArray();

        return $items;
    }

    // ═══════════════ LAYER 3: TABS ═══════════════

    /**
     * Rating/Likert trend per bulan — dinormalisasi ke 0-100%.
     */
    private function getRatingTrend(?int $formId, ?int $programId, ?string $dateFrom, ?string $dateTo): array
    {
        $query = DB::table('N_WEB_CAREERS_Feedback_Jawaban_Detail as d')
            ->join('N_WEB_CAREERS_Feedback_Jawaban as fj', 'd.Feedback_Jawaban_Id', '=', 'fj.Id_Feedback_Jawaban')
            ->join('N_WEB_CAREERS_Lamaran as l', 'fj.Lamaran_Id', '=', 'l.Id_Lamaran')
            ->where('fj.Flag_Cancellation', 'T')
            ->where('fj.Status_Pengisian', 'TERISI')
            ->whereIn('d.Tipe_Snapshot', ['RATING', 'LIKERT']);

        if ($formId) $query->where('fj.Master_Feedback_Form_Id', $formId);
        if ($programId) $query->where('l.Program_Id', $programId);
        if ($dateFrom) $query->where('fj.Submitted_At', '>=', $dateFrom);
        if ($dateTo) $query->where('fj.Submitted_At', '<=', $dateTo);

        $rows = $query->selectRaw("
                FORMAT(fj.Submitted_At, 'yyyy-MM') as bulan,
                AVG(
                    ((TRY_CAST(d.Jawaban AS FLOAT) - ISNULL(d.Skala_Min_Snapshot, 1))
                    / NULLIF(ISNULL(d.Skala_Max_Snapshot, 5) - ISNULL(d.Skala_Min_Snapshot, 1), 0))
                    * 100
                ) as avg_pct,
                COUNT(*) as total
            ")
            ->groupBy(DB::raw("FORMAT(fj.Submitted_At, 'yyyy-MM')"))
            ->orderBy('bulan')
            ->get();

        return $rows->map(fn($r) => [
            'bulan' => $r->bulan,
            'avg' => round((float) $r->avg_pct, 1),
            'total' => (int) $r->total,
        ])->toArray();
    }

    /**
     * Suara Kandidat: TEXTAREA responses terbaru.
     */
    private function getTextVoice(?int $formId, ?int $programId, ?string $dateFrom, ?string $dateTo, int $limit = 20): array
    {
        $query = DB::table('N_WEB_CAREERS_Feedback_Jawaban_Detail as d')
            ->join('N_WEB_CAREERS_Feedback_Jawaban as fj', 'd.Feedback_Jawaban_Id', '=', 'fj.Id_Feedback_Jawaban')
            ->join('N_WEB_CAREERS_Lamaran as l', 'fj.Lamaran_Id', '=', 'l.Id_Lamaran')
            ->leftJoin('N_WEB_CAREERS_Users as u', 'l.Id_Users', '=', 'u.Id_Users')
            ->where('fj.Flag_Cancellation', 'T')
            ->where('fj.Status_Pengisian', 'TERISI')
            ->where('d.Tipe_Snapshot', 'TEXTAREA')
            ->whereNotNull('d.Jawaban')
            ->where('d.Jawaban', '!=', '');

        if ($formId) $query->where('fj.Master_Feedback_Form_Id', $formId);
        if ($programId) $query->where('l.Program_Id', $programId);
        if ($dateFrom) $query->where('fj.Submitted_At', '>=', $dateFrom);
        if ($dateTo) $query->where('fj.Submitted_At', '<=', $dateTo);

        return $query->select(
                'd.Label_Snapshot as label',
                'd.Jawaban as jawaban',
                'l.Kode as kode_lamaran',
                'u.Nama as nama_kandidat',
                'fj.Submitted_At'
            )
            ->orderBy('fj.Submitted_At', 'DESC')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    // ═══════════════ MONITORING ═══════════════

    public function monitoringKpi(Request $request) {
        $fId = $request->input('form_id'); $pId = $request->input('program_id');
        $fIds = is_array($fId) ? array_filter(array_map('intval', $fId)) : ($fId !== null && $fId !== '' ? array_filter(array_map('intval', explode(',', $fId))) : []);
        $pIds = is_array($pId) ? array_filter(array_map('intval', $pId)) : ($pId !== null && $pId !== '' ? array_filter(array_map('intval', explode(',', $pId))) : []);

        $q = DB::table('N_WEB_CAREERS_Feedback_Jawaban as fj')->join('N_WEB_CAREERS_Lamaran as l','fj.Lamaran_Id','=','l.Id_Lamaran')->leftJoin('N_WEB_CAREERS_Master_Feedback_Form as ff','fj.Master_Feedback_Form_Id','=','ff.Id_Master_Feedback_Form')->where('fj.Flag_Cancellation','T');
        if(!empty($fIds)) $q->whereIn('fj.Master_Feedback_Form_Id', $fIds); if(!empty($pIds)) $q->whereIn('l.Program_Id', $pIds);
        $all=(clone$q)->count(); $ok=(clone$q)->where('fj.Status_Pengisian','TERISI')->count();
        $pend=(clone$q)->where('fj.Status_Pengisian','MENUNGGU')->whereRaw('DATEDIFF(DAY,fj.Created_At,GETDATE())<=COALESCE(ff.Durasi_Hari,30)')->count();
        $exp=(clone$q)->where('fj.Status_Pengisian','MENUNGGU')->whereRaw('DATEDIFF(DAY,fj.Created_At,GETDATE())>COALESCE(ff.Durasi_Hari,30)')->count();
        return ResponseHelper::success(['total'=>$all,'terisi'=>$ok,'pending'=>$pend,'expired'=>$exp,'response_rate'=>$all>0?round($ok/$all*100):0]);
    }

    public function monitoringData(Request $request) {
        $fId=$request->input('form_id');$pId=$request->input('program_id');$st=$request->input('status');$s=$request->input('search');$df=$request->input('date_from');$dt=$request->input('date_to');$pg=(int)$request->input('page',1);$lm=min((int)$request->input('limit',20),100);
        $fIds = is_array($fId) ? array_filter(array_map('intval', $fId)) : ($fId !== null && $fId !== '' ? array_filter(array_map('intval', explode(',', $fId))) : []);
        $pIds = is_array($pId) ? array_filter(array_map('intval', $pId)) : ($pId !== null && $pId !== '' ? array_filter(array_map('intval', explode(',', $pId))) : []);

        $q=DB::table('N_WEB_CAREERS_Feedback_Jawaban as fj')->join('N_WEB_CAREERS_Lamaran as l','fj.Lamaran_Id','=','l.Id_Lamaran')->leftJoin('N_WEB_CAREERS_Users as u','l.Id_Users','=','u.Id_Users')->leftJoin('N_WEB_CAREERS_Program as p','l.Program_Id','=','p.Id_Program')->leftJoin('N_WEB_CAREERS_Master_Feedback_Form as ff','fj.Master_Feedback_Form_Id','=','ff.Id_Master_Feedback_Form')->where('fj.Flag_Cancellation','T');
        if(!empty($fIds))$q->whereIn('fj.Master_Feedback_Form_Id',$fIds);if(!empty($pIds))$q->whereIn('l.Program_Id',$pIds);if($s)$q->where(fn($x)=>$x->where('u.Nama','LIKE',"%{$s}%")->orWhere('l.Kode','LIKE',"%{$s}%"));if($df)$q->where('l.Waktu_Lamar','>=',$df);if($dt)$q->where('l.Waktu_Lamar','<=',$dt);
        if($st==='EXPIRED')$q->where('fj.Status_Pengisian','MENUNGGU')->whereRaw('DATEDIFF(DAY,fj.Created_At,GETDATE())>COALESCE(ff.Durasi_Hari,30)');elseif($st)$q->where('fj.Status_Pengisian',$st);
        $ttl=$q->count();$items=$q->select('fj.Id_Feedback_Jawaban','fj.Status_Pengisian','fj.Created_At','fj.Submitted_At','fj.Master_Feedback_Form_Id','ff.Nama as Form_Nama','l.Id_Lamaran','l.Kode as Kode_Lamaran','l.Hasil_Akhir','l.Waktu_Lamar','u.Nama as Nama_Kandidat','u.Email','p.Nama as Program_Nama')->orderBy('fj.Created_At','DESC')->offset(($pg-1)*$lm)->limit($lm)->get();
        return ResponseHelper::successWithPagination($items,$pg,$lm,$ttl);
    }

    public function reassignForm(Request $request) {
        $v=$request->validate(['feedback_ids'=>'required|array|min:1','feedback_ids.*'=>'integer','new_form_id'=>'required|integer','resend_email'=>'boolean']);
        $now=FormatTanggalHelper::getCurrentTime();$uid=session('career_auth.id');$un=(string)($uid??'SISTEM');$c=0;$skipped=0;
        foreach($v['feedback_ids'] as $fid){$old=DB::table('N_WEB_CAREERS_Feedback_Jawaban')->where('Id_Feedback_Jawaban',$fid)->where('Flag_Cancellation','T')->first();if(!$old)continue;
            // Jangan reassign feedback yang sudah TERISI — jawaban sudah masuk
            if($old->Status_Pengisian==='TERISI'){$skipped++;continue;}
            DB::transaction(function()use($fid,$v,$now,$un,$uid,$old,&$c){DB::table('N_WEB_CAREERS_Feedback_Jawaban')->where('Id_Feedback_Jawaban',$fid)->update(['Flag_Cancellation'=>'Y','Cancelled_At'=>$now,'Cancelled_By'=>$un]);DB::table('N_WEB_CAREERS_Feedback_Jawaban_Detail')->where('Feedback_Jawaban_Id',$fid)->delete();
                $nid=DB::table('N_WEB_CAREERS_Feedback_Jawaban')->insertGetId(['Lamaran_Id'=>$old->Lamaran_Id,'Master_Feedback_Form_Id'=>$v['new_form_id'],'Email_Token'=>$old->Email_Token,'Status_Pengisian'=>'MENUNGGU','Created_At'=>$now],'Id_Feedback_Jawaban');
                $fs=app(FeedbackService::class);$t=$fs->generateTokenPair($nid,$old->Email_Token);DB::table('N_WEB_CAREERS_Feedback_Jawaban')->where('Id_Feedback_Jawaban',$nid)->update(['Token_Hash'=>$t['hashids'].'.'.$t['signature']]);$c++;
                // Wajib kirim email — link lama expired, user harus dapat link baru
                $l=DB::table('N_WEB_CAREERS_Lamaran')->where('Id_Lamaran',$old->Lamaran_Id)->first();if($l){$st=$l->Hasil_Akhir==='DITERIMA'?'LOLOS':'GUGUR';$url=rtrim(config('app.url'),'/').'/feedback/'.$t['hashids'].'/'.$t['signature'];\App\Jobs\Career\WcApplyEmailJob::dispatchSync((int)$l->Id_Users,$st,['kode'=>$l->Kode,'feedbackUrl'=>$url]);}
            });}
        $msg = $c > 0 ? "$c feedback di-reassign." : "Tidak ada yang di-reassign.";
        if($skipped > 0) $msg .= " $skipped dilewati (sudah TERISI).";
        Log::channel('feedback')->info('Reassign',['count'=>$c,'skipped'=>$skipped,'form'=>$v['new_form_id']]);return ResponseHelper::success(['reassigned'=>$c,'skipped'=>$skipped],$msg);
    }

    public function resendEmail(Request $request) {
        $v=$request->validate(['feedback_ids'=>'required|array|min:1','feedback_ids.*'=>'integer']);$s=0;$skipped=0;$failed=0;
        foreach($v['feedback_ids'] as $fid){$fb=DB::table('N_WEB_CAREERS_Feedback_Jawaban')->where('Id_Feedback_Jawaban',$fid)->where('Flag_Cancellation','T')->first();if(!$fb||!$fb->Email_Token)continue;
            if($fb->Status_Pengisian==='TERISI'){$skipped++;continue;}
            $l=DB::table('N_WEB_CAREERS_Lamaran')->where('Id_Lamaran',$fb->Lamaran_Id)->first();if(!$l)continue;
            $st=$l->Hasil_Akhir==='DITERIMA'?'LOLOS':'GUGUR';$pt=explode('.',$fb->Token_Hash,2);$url=rtrim(config('app.url'),'/').'/feedback/'.($pt[0]??'').'/'.($pt[1]??'');
            try {
                // dispatchSync: proses job langsung (blocking) tapi logic email tetap di Job class
                \App\Jobs\Career\WcApplyEmailJob::dispatchSync((int)$l->Id_Users, $st, ['kode'=>$l->Kode, 'feedbackUrl'=>$url]);
                $s++;
            } catch (\Throwable $e) {
                $failed++;
                Log::channel('feedback')->error('Resend email gagal',['feedback_id'=>$fid,'error'=>$e->getMessage()]);
            }
        }
        $msg = $s > 0 ? "$s email terkirim." : "Tidak ada email yang dikirim.";
        if($skipped > 0) $msg .= " $skipped dilewati (sudah TERISI).";
        if($failed > 0) $msg .= " $failed gagal.";
        return ResponseHelper::success(['resent'=>$s,'skipped'=>$skipped,'failed'=>$failed],$msg);
    }
}
