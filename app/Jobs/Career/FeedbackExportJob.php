<?php

namespace App\Jobs\Career;

use App\Helpers\FormatTanggalHelper;
use App\Jobs\Career\Concerns\AntreanWebCareers;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class FeedbackExportJob implements ShouldQueue
{
    use AntreanWebCareers, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // Lihat WcLaporanKandidatJob::QUEUE — Cloud Tasks menolak underscore.
    public const QUEUE = 'async-export';

    public $timeout = 600;
    public $tries = 2;
    public $backoff = 30;

    public function __construct(
        private int $exportId,
        private int $formId,
        private array $filters
    ) {
        $this->aturAntrean(self::QUEUE);
    }

    public function handle()
    {
        $total = $this->countRows();
        DB::table('N_WEB_CAREERS_Export_Log')
            ->where('Id_Export', $this->exportId)
            ->update(['Progress_Total' => (int) ceil($total / 1000)]);

        $pertanyaan = DB::table('N_WEB_CAREERS_Master_Feedback_Pertanyaan')
            ->where('Master_Feedback_Form_Id', $this->formId)
            ->where('Flag_Cancellation', 'T')
            ->orderBy('Urutan')
            ->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $row = 1;

        $headers = ['Kode Lamaran', 'Nama', 'Program', 'Status', 'Tgl Submit'];
        foreach ($pertanyaan as $p) {
            $headers[] = $p->Label . ' (' . $p->Tipe . ')';
        }
        $sheet->fromArray($headers, null, "A{$row}");
        $row++;

        $lastId = 0;
        $chunk = 0;

        while (true) {
            $items = $this->fetchChunk($lastId, 1000);
            if (empty($items)) break;

            $lastId = $items[count($items) - 1]->Id_Feedback_Jawaban;
            $ids = array_column($items, 'Id_Feedback_Jawaban');

            $details = DB::table('N_WEB_CAREERS_Feedback_Jawaban_Detail')
                ->whereIn('Feedback_Jawaban_Id', $ids)
                ->get()
                ->groupBy('Feedback_Jawaban_Id');

            foreach ($items as $item) {
                $rowData = [
                    $item->Kode_Lamaran ?? '-',
                    $item->Nama ?? '-',
                    $item->Program_Nama ?? '-',
                    $item->Hasil_Akhir ?? '-',
                    $item->Submitted_At ? FormatTanggalHelper::format($item->Submitted_At, true) : '-',
                ];

                $itemDetails = $details[$item->Id_Feedback_Jawaban] ?? collect();
                foreach ($pertanyaan as $p) {
                    $jwb = $itemDetails->firstWhere('Master_Feedback_Pertanyaan_Id', $p->Id_Master_Feedback_Pertanyaan);
                    $rowData[] = $jwb->Jawaban ?? '';
                }

                $sheet->fromArray($rowData, null, "A{$row}");
                $row++;
            }

            $chunk++;
            DB::table('N_WEB_CAREERS_Export_Log')
                ->where('Id_Export', $this->exportId)
                ->update(['Progress_Chunk' => $chunk]);

            unset($items, $details, $ids);
            gc_collect_cycles();
        }

        $filePath = "exports/feedback/feedback-{$this->exportId}.xlsx";
        $tempPath = storage_path("app/{$filePath}");
        $dir = dirname($tempPath);
        if (! is_dir($dir)) mkdir($dir, 0755, true);

        $writer = new Xlsx($spreadsheet);
        $writer->save($tempPath);

        $stream = fopen($tempPath, 'r');
        Storage::disk('gcs')->put($filePath, $stream);
        fclose($stream);
        unlink($tempPath);

        $now = FormatTanggalHelper::getCurrentTime();
        DB::table('N_WEB_CAREERS_Export_Log')
            ->where('Id_Export', $this->exportId)
            ->update([
                'Status_Export' => 'SELESAI',
                'File_Url' => Storage::disk('gcs')->url($filePath),
                'File_Path' => $filePath,
                'Completed_At' => $now,
            ]);

        Log::channel('feedback')->info('Export selesai', ['export_id' => $this->exportId]);
    }

    public function failed(\Throwable $e)
    {
        Log::channel('feedback')->error('Export gagal', [
            'export_id' => $this->exportId,
            'error' => $e->getMessage(),
        ]);

        DB::table('N_WEB_CAREERS_Export_Log')
            ->where('Id_Export', $this->exportId)
            ->update([
                'Status_Export' => 'GAGAL',
                'Error_Message' => mb_substr($e->getMessage(), 0, 500),
                'Completed_At' => FormatTanggalHelper::getCurrentTime(),
            ]);

        $filePath = "exports/feedback/feedback-{$this->exportId}.xlsx";
        try { Storage::disk('gcs')->delete($filePath); } catch (\Throwable $e) {}
    }

    private function countRows(): int
    {
        $query = DB::table('N_WEB_CAREERS_Feedback_Jawaban')
            ->where('Master_Feedback_Form_Id', $this->formId)
            ->where('Status_Pengisian', 'TERISI')
            ->where('Flag_Cancellation', 'T');

        if (! empty($this->filters['date_from'])) {
            $query->where('Submitted_At', '>=', $this->filters['date_from']);
        }
        if (! empty($this->filters['date_to'])) {
            $query->where('Submitted_At', '<=', $this->filters['date_to']);
        }

        return $query->count();
    }

    private function fetchChunk(int $lastId, int $limit): array
    {
        $query = DB::table('N_WEB_CAREERS_Feedback_Jawaban as fj')
            ->join('N_WEB_CAREERS_Lamaran as l', 'fj.Lamaran_Id', '=', 'l.Id_Lamaran')
            ->leftJoin('N_WEB_CAREERS_Program as p', 'l.Program_Id', '=', 'p.Id_Program')
            ->leftJoin('N_WEB_CAREERS_Users as u', 'l.Id_Users', '=', 'u.Id_Users')
            ->where('fj.Master_Feedback_Form_Id', $this->formId)
            ->where('fj.Status_Pengisian', 'TERISI')
            ->where('fj.Flag_Cancellation', 'T')
            ->where('fj.Id_Feedback_Jawaban', '>', $lastId)
            ->select('fj.Id_Feedback_Jawaban', 'l.Kode as Kode_Lamaran', 'l.Hasil_Akhir',
                'u.Nama', 'p.Nama as Program_Nama', 'fj.Submitted_At');

        if (! empty($this->filters['date_from'])) {
            $query->where('fj.Submitted_At', '>=', $this->filters['date_from']);
        }
        if (! empty($this->filters['date_to'])) {
            $query->where('fj.Submitted_At', '<=', $this->filters['date_to']);
        }

        return $query->orderBy('fj.Id_Feedback_Jawaban')
            ->limit($limit)
            ->get()
            ->toArray();
    }
}
