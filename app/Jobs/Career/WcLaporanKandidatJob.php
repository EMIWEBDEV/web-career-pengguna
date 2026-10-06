<?php

namespace App\Jobs\Career;

use App\Jobs\Career\Concerns\AntreanWebCareers;
use App\Support\Career\KopKakiLaporan;
use App\Support\Career\LaporanKandidat;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * WEB CAREER — cetak LAPORAN KANDIDAT (PDF / Excel) di latar belakang.
 *
 * KENAPA DIANTREKAN
 * Merender PDF berisi foto tertanam, seluruh perjalanan tahap, dan jawaban
 * formulir bisa memakan beberapa detik — dan itu untuk SATU kandidat. Bila
 * dikerjakan di dalam permintaan HTTP, admin menatap layar membeku lalu
 * menekan tombolnya lagi, dan lahir dua berkas untuk satu permintaan.
 *
 * Statusnya dicatat di N_WEB_CAREERS_Export_Log — tabel yang sudah dipakai
 * ekspor lain — sehingga layar bisa menanyakan "sudah jadi belum" tanpa
 * menunggu, dan kegagalan meninggalkan jejak yang bisa dibaca.
 */
class WcLaporanKandidatJob implements ShouldQueue
{
    use AntreanWebCareers, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // NAMA QUEUE di Cloud Tasks — bukan kunci config('queue.names.*).
    // Cloud Tasks hanya menerima huruf, angka, dan hyphen; 'async_export'
    // (kunci config-nya) ditolak INVALID_ARGUMENT saat task dikirim.
    public const QUEUE = 'async-export';

    public $timeout = 300;
    public $tries = 2;
    public $backoff = 20;

    /**
     * @param  int[]  $pengisianIds  formulir yang ikut dicetak; kosong = terbaru.
     * @param  string  $format  PDF | XLSX
     */
    public function __construct(
        private int $exportId,
        private int $lamaranId,
        private array $pengisianIds,
        private string $format,
    ) {
        $this->aturAntrean(self::QUEUE);
    }

    public function handle(): void
    {
        $d = LaporanKandidat::rakit($this->lamaranId, $this->pengisianIds);

        if (! $d) {
            throw new \RuntimeException("Lamaran #{$this->lamaranId} tidak ditemukan.");
        }

        $slug = \Illuminate\Support\Str::slug($d['kandidat']['nama'] ?: 'kandidat');
        $ext = $this->format === 'XLSX' ? 'xlsx' : 'pdf';
        $path = "laporan-kandidat/{$d['kandidat']['kodeLamaran']}-{$slug}-{$this->exportId}.{$ext}";

        $isi = $this->format === 'XLSX' ? $this->buatExcel($d) : $this->buatPdf($d);

        Storage::disk('gcs')->put($path, $isi);

        DB::table('N_WEB_CAREERS_Export_Log')
            ->where('Id_Export', $this->exportId)
            ->update([
                'Status_Export' => 'SELESAI',
                'File_Path' => $path,
                'File_Url' => Storage::disk('gcs')->url($path),
                'Progress_Chunk' => 1,
                'Progress_Total' => 1,
                'Completed_At' => now(),
            ]);

        Log::channel('web_career')->info("[LAPORAN] #{$this->exportId} selesai ({$this->format}) — {$path}");
    }

    /**
     * Render PDF dari blade. Kertas A4 potret — laporan ini dicetak & diarsip.
     *
     * Kop & kaki halaman TIDAK ada di dalam HTML, melainkan digambar ke kanvas
     * sesudah dokumen tersusun — lihat KopKakiLaporan. Alasannya di sana.
     */
    private function buatPdf(array $d): string
    {
        $pdf = Pdf::loadView('career.laporan.kandidat', [
            'd' => $d,
            'logo' => LaporanKandidat::logoDataUri(),
            'tglLamar' => $d['lamaran']['waktuLamar']
                ? \Illuminate\Support\Carbon::parse($d['lamaran']['waktuLamar'])->format('d M Y')
                : null,
            ...$this->nadaHasil($d),
        ]);

        $this->siapkan($pdf);

        // Harus SESUDAH render: page_script menyusuri halaman yang sudah jadi,
        // dan sebelum render belum ada satu halaman pun untuk disusuri.
        $pdf->render();
        KopKakiLaporan::pasang($pdf->getDomPDF(), $d['kandidat']['nama'] ?: '—', $d['kandidat']['kodeLamaran']);

        return $pdf->output();
    }

    /** Setelan mesin cetak. */
    private function siapkan(\Barryvdh\DomPDF\PDF $pdf): \Barryvdh\DomPDF\PDF
    {
        $pdf->setPaper('a4', 'portrait');
        // isRemoteEnabled dibiarkan MATI: seluruh gambar sudah ditanam sebagai
        // data URI. Menyalakannya berarti dompdf boleh menembak URL apa pun
        // yang kebetulan ada di dalam HTML — termasuk yang datang dari isian
        // kandidat.
        $pdf->setOption('isHtml5ParserEnabled', true);
        $pdf->setOption('defaultFont', 'DejaVu Sans');
        // SUBSET FONTNYA — bawaan paketnya mati.
        //
        // Dokumen ini memakai dua rumpun huruf (DejaVu Sans untuk isi, DejaVu
        // Sans Mono untuk label kecil, mengikuti rancangan). Tanpa subset,
        // KEEMPAT berkas font ditanam utuh dan satu laporan satu kandidat jadi
        // ~1,35 MB; dengan subset ~63 KB dan render dua kali lebih cepat.
        // Berkasnya tersimpan permanen di GCS per kandidat, jadi selisih itu
        // menumpuk. Keempat font tetap tertanam — hanya glif yang benar-benar
        // dipakai yang ikut.
        $pdf->setOption('isFontSubsettingEnabled', true);

        return $pdf;
    }

    /**
     * Status akhir → label & warna lencana.
     *
     * Dihitung di sini, bukan di dalam blade: keputusan "mundur itu bukan
     * gagal" adalah aturan bisnis, dan aturan bisnis di dalam template akan
     * berbeda dari yang dipakai layar tanpa ada yang menyadarinya.
     */
    private function nadaHasil(array $d): array
    {
        $status = strtoupper((string) ($d['lamaran']['status'] ?? ''));

        return match (true) {
            $status === 'LULUS' => ['labelHasil' => 'DITERIMA', 'nadaHasil' => 'l-lolos'],
            $status === 'GUGUR' => ['labelHasil' => 'TIDAK LOLOS', 'nadaHasil' => 'l-gugur'],
            $status === 'TALENT_POOL' => ['labelHasil' => 'TALENT POOL', 'nadaHasil' => 'l-talent'],
            $status === 'BERJALAN' => ['labelHasil' => 'BERJALAN', 'nadaHasil' => 'l-jalan'],
            // Keputusan yang datang DARI KANDIDAT (mundur / menolak penawaran)
            // tidak diwarnai merah: prosesnya berhenti, tapi bukan karena ia
            // ditolak.
            $status !== '' => ['labelHasil' => str_replace('_', ' ', $status), 'nadaHasil' => 'l-netral'],
            default => ['labelHasil' => '—', 'nadaHasil' => 'l-netral'],
        };
    }

    /**
     * Excel — untuk yang perlu MENGOLAH datanya, bukan membacanya.
     *
     * Sengaja berbeda bentuk dari PDF: di sini tiap baris satu fakta, tanpa
     * gambar dan tanpa tata letak dua kolom. Meniru tampilan PDF di dalam
     * spreadsheet menghasilkan berkas yang tidak enak dibaca DAN tidak bisa
     * disaring — gagal di dua-duanya.
     */
    private function buatExcel(array $d): string
    {
        $book = new Spreadsheet();
        $book->getProperties()->setTitle('Laporan Kandidat')->setCompany('EVO Group');

        $s = $book->getActiveSheet();
        $s->setTitle('Profil');

        // ── KEPALA DOKUMEN ──────────────────────────────────────────────
        // Judul + identitas kandidat berdiri sendiri di atas. Berkas ini
        // sering disalin ke lampiran email dan dibuka tanpa konteks apa pun;
        // tanpa kepala, yang membukanya tidak tahu ini milik siapa.
        $s->setCellValue('A1', 'PROFIL KANDIDAT — EVO GROUP');
        $s->mergeCells('A1:B1');
        $s->getStyle('A1')->getFont()->setBold(true)->setSize(15)->getColor()->setRGB('FFFFFF');
        $s->getStyle('A1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1D4ED8');
        $s->getStyle('A1')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER)->setIndent(1);
        $s->getRowDimension(1)->setRowHeight(30);

        $s->setCellValue('A2', $d['kandidat']['nama'] . ' · ' . $d['kandidat']['kodeLamaran']);
        $s->mergeCells('A2:B2');
        $s->getStyle('A2')->getFont()->setBold(true)->setSize(10)->getColor()->setRGB('1E293B');
        $s->getStyle('A2')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('EEF2F9');
        $s->getStyle('A2')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER)->setIndent(1);
        $s->getRowDimension(2)->setRowHeight(20);

        $baris = 4;
        // Dikelompokkan dengan sub-judul: 20 baris label-nilai beruntun tanpa
        // pemisah memaksa mata menelusuri satu per satu untuk menemukan
        // "posisi apa yang ia lamar".
        $blok = [
            'DATA KANDIDAT' => [
                'Nama Kandidat' => $d['kandidat']['nama'],
                'Kode Lamaran' => $d['kandidat']['kodeLamaran'],
                'Email' => $d['kandidat']['email'],
                'No. Handphone' => $d['kandidat']['hp'],
                'Tanggal Lahir' => $d['kandidat']['tglLahir'],
                'Jenis Kelamin' => $d['kandidat']['jkel'],
                'Institusi' => $d['kandidat']['kampus'],
                'Tahun Lulus' => $d['kandidat']['tahunLulus'],
            ],
            'LAMARAN' => [
                'Program' => $d['lamaran']['program'],
                'Posisi Dilamar' => $d['lamaran']['posisi'],
                'Level' => $d['lamaran']['level'],
                'Departemen' => $d['lamaran']['departemen'],
                'Penempatan' => $d['lamaran']['lokasi'],
                'Tanggal Melamar' => $d['lamaran']['waktuLamar'],
            ],
            'HASIL' => [
                'Status Akhir' => $d['lamaran']['status'],
                'Berhenti di Tahap' => $d['lamaran']['gugurDi'],
                'Alasan' => $d['lamaran']['alasanGugur'],
                'Dicetak' => $d['dicetak'],
            ],
        ];

        foreach ($blok as $judulBlok => $isi) {
            $s->setCellValue("A{$baris}", $judulBlok);
            $s->mergeCells("A{$baris}:B{$baris}");
            $s->getStyle("A{$baris}")->getFont()->setBold(true)->setSize(8)->getColor()->setRGB('1D4ED8');
            $s->getStyle("A{$baris}")->getBorders()->getBottom()->setBorderStyle(Border::BORDER_THIN);
            $baris++;

            foreach ($isi as $k => $v) {
                $s->setCellValue("A{$baris}", $k);
                $s->setCellValueExplicit("B{$baris}", (string) ($v ?: '—'),
                    \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $s->getStyle("A{$baris}")->getFont()->getColor()->setRGB('64748B');
                $s->getStyle("B{$baris}")->getFont()->setBold(true);
                $s->getStyle("B{$baris}")->getAlignment()->setWrapText(true);
                $baris++;
            }
            $baris++;
        }

        // ── Sheet perjalanan tahap ──────────────────────────────────────
        $t = $book->createSheet();
        $t->setTitle('Perjalanan');
        // "Biaya" = penggantian biaya aktivitas (MCU): Diganti / Tidak diganti /
        // Menunggu hasil — turunan hasilnya, dibaca Finance.
        $t->fromArray(['#', 'Tahap', 'Status', 'Hasil', 'Aktivitas', 'Nilai', 'Biaya', 'Diputus', 'Oleh'], null, 'A1');
        $this->kepala($t, 'A1:I1');

        $r = 2;
        foreach ($d['tahap'] as $th) {
            if (! $th['aktivitas']) {
                $t->fromArray([$th['urutan'], $th['label'], $th['status'], $th['hasil'] ?: '—', '—', '—', '—',
                    $th['diputusAt'] ?: '—', $th['diputusOleh'] ?: '—'], null, "A{$r}");
                $r++;

                continue;
            }
            // Satu baris per AKTIVITAS: itulah bentuk yang bisa disaring dan
            // dijumlahkan. Tahapnya diulang di tiap baris — pengulangan itu
            // justru yang membuat pivot table bekerja.
            foreach ($th['aktivitas'] as $a) {
                $t->fromArray([
                    $th['urutan'], $th['label'], $th['status'], $th['hasil'] ?: '—',
                    $a['label'] . ($a['internal'] ? ' (internal)' : ''),
                    $a['nilai'] ?? '—',
                    $a['biaya'] ?? '—',
                    $th['diputusAt'] ?: '—', $th['diputusOleh'] ?: '—',
                ], null, "A{$r}");
                $r++;
            }
        }
        $this->rapikanTabel($t, 'A', 'I', $r);

        // ── Sheet jawaban formulir ──────────────────────────────────────
        $f = $book->createSheet();
        $f->setTitle('Formulir');
        $f->fromArray(['Formulir', 'Dikirim', 'Pertanyaan', 'Jawaban'], null, 'A1');
        $this->kepala($f, 'A1:D1');

        $r = 2;
        foreach ($d['formulir'] as $form) {
            foreach ($form['isian'] as $j) {
                $nilai = $j['berkas'] ? 'TERLAMPIR: ' . $j['berkas']['nama'] : ($j['nilai'] ?: '—');
                $f->fromArray([$form['label'], $form['waktuKirim'], $j['label'], $nilai], null, "A{$r}");
                $r++;
            }
        }
        $this->rapikanTabel($f, 'A', 'D', $r);
        // Jawaban bisa satu paragraf; lebar otomatis akan melebar sampai layar
        // habis, jadi kolomnya dipatok dan dibungkus.
        $f->getColumnDimension('D')->setAutoSize(false);
        $f->getColumnDimension('D')->setWidth(62);
        $f->getStyle('D2:D' . max(2, $r - 1))->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);

        $s->getColumnDimension('A')->setWidth(24);
        $s->getColumnDimension('B')->setWidth(52);
        $book->setActiveSheetIndex(0);

        // Ditulis ke memori, bukan ke berkas sementara: laporan satu kandidat
        // kecil, dan menulis ke disk hanya menambah satu titik gagal (izin
        // folder, sisa berkas saat job mati di tengah).
        ob_start();
        (new Xlsx($book))->save('php://output');
        $isi = (string) ob_get_clean();

        $book->disconnectWorksheets();

        return $isi;
    }

    /** Gaya baris kepala tabel — sama di seluruh sheet. */
    private function kepala(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, string $range): void
    {
        $sheet->getStyle($range)->getFont()->setBold(true)->setSize(9)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle($range)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1D4ED8');
        $sheet->getStyle($range)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getRowDimension(1)->setRowHeight(22);
        $sheet->freezePane('A2');
    }

    /**
     * Rapikan tabel data: lebar kolom, garis, baris berselang, dan SARINGAN.
     *
     * Autofilter yang paling menentukan — sheet ini memang dibuat untuk
     * diolah, dan tanpa saringan pembaca harus memasangnya sendiri tiap kali
     * membuka berkas. Baris berselang dipakai karena tabel perjalanan mengulang
     * nama tahap di tiap aktivitas; tanpa selang-seling, mata kehilangan baris
     * saat menggeser ke kanan.
     *
     * @param  int  $akhir  nomor baris SESUDAH baris terakhir yang terisi
     */
    private function rapikanTabel(
        \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet,
        string $dari,
        string $sampai,
        int $akhir,
    ): void {
        $terakhir = max(2, $akhir - 1);
        $rentang = "{$dari}1:{$sampai}{$terakhir}";

        foreach (range($dari, $sampai) as $k) {
            $sheet->getColumnDimension($k)->setAutoSize(true);
        }

        $sheet->getStyle($rentang)->getBorders()->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('E2E8F0');

        for ($b = 2; $b <= $terakhir; $b++) {
            if ($b % 2 === 0) {
                $sheet->getStyle("{$dari}{$b}:{$sampai}{$b}")->getFill()
                    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F8FAFC');
            }
        }

        $sheet->setAutoFilter($rentang);
    }

    public function failed(\Throwable $e): void
    {
        Log::channel('web_career')->error("[LAPORAN] #{$this->exportId} gagal: " . $e->getMessage());

        DB::table('N_WEB_CAREERS_Export_Log')
            ->where('Id_Export', $this->exportId)
            ->update([
                'Status_Export' => 'GAGAL',
                'Error_Message' => mb_substr($e->getMessage(), 0, 500),
                'Completed_At' => now(),
            ]);
    }
}
