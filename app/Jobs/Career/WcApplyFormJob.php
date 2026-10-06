<?php

namespace App\Jobs\Career;

use App\Jobs\Career\Concerns\AntreanWebCareers;
use App\Jobs\Career\Concerns\CatatGagalWebCareers;
use App\Support\Career\GcsBerkas;
use App\Support\Career\LamaranService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * WEB CAREER — proses pendaftaran (apply-form) secara ASINKRON.
 *
 * Queue: 'wc-applyform'. Local → driver default (database) saat `queue:work`.
 * Non-local → otomatis connection 'cloudtasks' dengan nama queue yang sama.
 *
 * CATATAN GAMBAR/BERKAS: file TIDAK dititip sebagai base64 (kena batas ukuran/chunk).
 * Berkas sudah di-stream ke GCS saat REQUEST; payload hanya menyimpan PATH-nya.
 * Job ini murni menulis DB, lalu:
 *  - Bila insert DB gagal pada percobaan TERAKHIR → seluruh berkas GCS dihapus
 *    (tidak ada berkas yatim). Percobaan antara TIDAK menghapus apa pun: yang
 *    berikutnya masih membutuhkan berkas itu.
 *  - Begitu transaksinya selesai, tidak ada lagi yang boleh menghapus berkas.
 *  - Jadi: file & DB dua-duanya ada, atau dua-duanya tidak ada.
 */
class WcApplyFormJob implements ShouldQueue, ShouldBeUnique
{
    use AntreanWebCareers, CatatGagalWebCareers, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const QUEUE = 'wc-applyform';

    public $timeout = 300;

    public $tries = 3;

    public $backoff = 30;

    public $uniqueFor = 3600;

    protected string $processId;

    public function __construct(string $processId)
    {
        $this->processId = $processId;

        $this->aturAntrean(self::QUEUE);
    }

    public function uniqueId(): string
    {
        return self::QUEUE . '-' . $this->processId;
    }

    public function handle(LamaranService $svc, GcsBerkas $gcs): void
    {
        $row = DB::table('N_WEB_CAREERS_Apply_Payload')->where('Process_Id', $this->processId)->first();
        if (! $row) {
            Log::channel('web_career')->warning("[APPLY] payload {$this->processId} tidak ditemukan.");

            return;
        }
        if ($row->Status === 'SELESAI') {
            return; // idempoten
        }

        $payload = json_decode($row->Payload_Json ?: '{}', true) ?: [];
        // Berkas hanya berisi METADATA + PATH GCS (sudah terunggah saat request).
        $berkasSiap = json_decode($row->Berkas_Json ?: '[]', true) ?: [];
        $terunggah = array_column($berkasSiap, 'path'); // untuk kompensasi bila DB gagal

        try {
            // ── INSERT DB (lamaran + tahap + pengisian + berkas) ATOMIK ──
            $lamaranId = DB::transaction(function () use ($svc, $payload, $berkasSiap, $row) {
                $hasil = $svc->buatLamaran(
                    (int) $payload['userId'],
                    (int) $payload['pembukaanId'],
                    (int) $payload['posisiId'],
                    null,
                    $payload['gugurAlasan'] ?? null,
                    $payload['jawaban'] ?? null,
                );

                if (! ($hasil['ok'] ?? false)) {
                    throw new \RuntimeException($hasil['pesan'] ?? 'Gagal membuat lamaran.');
                }
                $lamaranId = $hasil['lamaranId'];

                // Berkas ditautkan ke pengisian formulir PENDAFTARAN (tahap 1).
                $pengisian = DB::table('N_WEB_CAREERS_Formulir_Pengisian')
                    ->where('Lamaran_Id', $lamaranId)
                    ->where('Sumber', 'PENDAFTARAN')
                    ->orderBy('Id_Formulir_Pengisian')
                    ->first();

                // Ada berkas tapi tak ada pengisian untuk ditempeli: dulu berkasnya
                // dilewati diam-diam lalu Berkas_Json dikosongkan — lamaran jadi
                // tercatat tanpa satu berkas pun dan jejak path-nya ikut lenyap.
                // Sekarang dibatalkan: lebih baik gagal & terlihat daripada tuntas
                // tapi kehilangan CV.
                if ($berkasSiap && ! $pengisian) {
                    throw new \RuntimeException('Pengisian pendaftaran tidak terbentuk — berkas lamaran tidak bisa ditautkan.');
                }

                if ($berkasSiap) {
                    $now = now();
                    foreach ($berkasSiap as $i => $b) {
                        DB::table('N_WEB_CAREERS_Formulir_Berkas')->insert([
                            'Formulir_Pengisian_Id' => $pengisian->Id_Formulir_Pengisian,
                            'Id_Users' => (int) $payload['userId'],
                            'Field_Key' => $b['field'],
                            // Posisi baris bagian berulang — tanpa ini worklist tak
                            // bisa memasangkan sertifikat ke barisnya sendiri.
                            'Bagian_Key' => $b['bagian'] ?? null,
                            'Baris_Index' => $b['baris'] ?? null,
                            'Urutan' => $i + 1,
                            'Nama_Asli' => $b['nama'],
                            'Path_File' => $b['path'],
                            'Ukuran_Byte' => $b['ukuran'],
                            'Mime' => $b['mime'],
                            'Ekstensi' => $b['ext'],
                            'Hash_File' => $b['hash'],
                            'Status_Verifikasi' => 'BELUM',
                            'Waktu_Unggah' => $now,
                            'Created_At' => $now, 'Created_By' => $row->Nama_Kandidat, 'Created_By_Id' => (int) $payload['userId'],
                            'Updated_At' => $now, 'Updated_By' => $row->Nama_Kandidat, 'Updated_By_Id' => (int) $payload['userId'],
                        ]);
                    }
                }

                // SELESAI ditulis DI DALAM transaksi yang sama. Kalau ditulis
                // sesudahnya lalu gagal, lamarannya sudah ada tetapi payload-nya
                // tidak berkata begitu — percobaan berikutnya mengulang dari awal,
                // ditolak "sudah melamar", dan pembersihannya menghapus berkas
                // yang sedang dipakai lamaran yang sah.
                DB::table('N_WEB_CAREERS_Apply_Payload')->where('Process_Id', $this->processId)->update([
                    'Status' => 'SELESAI',
                    'Lamaran_Id' => $lamaranId,
                    'Berkas_Json' => null,
                    'Waktu_Proses' => now(),
                    'Updated_At' => now(),
                ]);

                return $lamaranId;
            });
        } catch (\Throwable $e) {
            $terakhir = $this->attempts() >= $this->tries;

            // Kompensasi HANYA pada percobaan terakhir. Dulu berkas dihapus di
            // setiap kegagalan — termasuk yang masih akan diulang — sehingga
            // percobaan berikutnya yang BERHASIL mencatat baris Formulir_Berkas
            // yang menunjuk objek yang sudah tidak ada: CV tercatat, tapi 404.
            if ($terakhir) {
                $gcs->hapus($terunggah);
            }

            DB::table('N_WEB_CAREERS_Apply_Payload')->where('Process_Id', $this->processId)->update([
                // Selama masih akan diulang, statusnya tetap MENUNGGU: layar
                // kandidat berhenti memantau di GAGAL pertama, dan kandidat yang
                // melihat "gagal" mengirim lamaran kedua sementara yang pertama
                // masih diproses.
                'Status' => $terakhir ? 'GAGAL' : 'MENUNGGU',
                'Percobaan' => ($row->Percobaan ?? 0) + 1,
                'Pesan_Error' => substr($e->getMessage(), 0, 480),
                'Waktu_Proses' => now(),
                'Updated_At' => now(),
            ]);

            Log::channel('web_career')->error("[APPLY] {$this->processId} GAGAL (percobaan {$this->attempts()}/{$this->tries}): " . $e->getMessage());

            // Percobaan terakhir → catat ke N_WEB_CAREERS_Failed_Jobs & SELESAI.
            // Sengaja TIDAK throw agar kegagalan tak masuk N_LMS_Failed_Jobs global.
            if ($terakhir) {
                $this->catatGagalWc('APPLYFORM', json_encode(['processId' => $this->processId]), $e);

                return;
            }

            throw $e; // masih ada sisa percobaan → retry
        }

        // ── LAMARAN SUDAH TERSIMPAN ─────────────────────────────────────────
        // Dari sini ke bawah TIDAK ADA yang boleh menghapus berkas atau
        // menggagalkan job. Dulu langkah-langkah ini berada di dalam try yang
        // sama dengan kompensasi di atas: antrean biodata yang menolak membuat
        // seluruh berkas lamaran yang SUDAH tercatat ikut terhapus.

        Log::channel('web_career')->info("[APPLY] {$this->processId} selesai — lamaran #{$lamaranId}, " . count($terunggah) . ' berkas.');

        // Kirim email HASIL ke kandidat lewat QUEUE TERPISAH (wc-applymail) —
        // tidak menahan job apply. Gagal antre email TIDAK menggagalkan apply.
        $this->kirimEmailHasil($lamaranId, (int) $payload['userId']);

        // ── BIODATA → HRIS REKRUTMEN ────────────────────────────────────
        //
        // Formulir pendaftaran inilah yang memuat tanggal lahir, jenis
        // kelamin, dan alamat kandidat — data yang dulu tidak pernah sampai
        // ke HCLearn, karena satu-satunya pengiriman terjadi saat ia
        // MENDAFTAR AKUN, tepat ketika belum mengisi apa pun.
        //
        // Posisi yang dilamar ikut terbawa, dan ikut berubah bila kandidat
        // yang sama melamar posisi lain dengan akun yang sama.
        //
        // Queue tersendiri, best-effort: lamarannya sudah tersimpan, jadi
        // HCLearn yang sedang tumbang tidak boleh menggagalkan apa pun.
        try {
            WcBiodataHrisJob::dispatch((int) $payload['userId'], 'APPLY');
        } catch (\Throwable $e) {
            Log::channel('web_career')->warning("[APPLY] {$this->processId} biodata HRIS gagal diantrekan: " . $e->getMessage());
        }
    }

    /**
     * Tentukan hasil lamaran & antrekan email ke kandidat (queue wc-applymail).
     * Status:
     *   GUGUR    = lamaran auto-gugur (Status lamaran GUGUR).
     *   LOLOS    = lolos syarat otomatis (tahap 1 = LULUS).
     *   MENUNGGU = tanpa syarat / menunggu keputusan admin (tahap 1 masih BERJALAN).
     * Dibungkus try: kegagalan email tidak boleh menggagalkan proses apply.
     */
    protected function kirimEmailHasil(int $lamaranId, int $userId): void
    {
        try {
            $lam = DB::table('N_WEB_CAREERS_Lamaran as l')
                ->leftJoin('N_WEB_CAREERS_Program_Posisi as pos', 'pos.Id_Program_Posisi', '=', 'l.Program_Posisi_Id')
                ->leftJoin('N_WEB_CAREERS_Program as pr', 'pr.Id_Program', '=', 'l.Program_Id')
                ->where('l.Id_Lamaran', $lamaranId)
                ->select('l.Kode', 'l.Status', 'l.Total_Tahap', 'pos.Posisi as posisi', 'pr.Nama as program')
                ->first();

            if (! $lam) {
                return;
            }

            $extra = ['kode' => $lam->Kode, 'posisi' => $lam->posisi, 'program' => $lam->program];

            // Data kartu kandidat untuk email (tanggal lahir, kampus, PATH foto).
            // Pengambilannya dipakai bersama dengan email keputusan tahap —
            // lihat LamaranService::dataKandidatEmail().
            $extra += \App\Support\Career\LamaranService::dataKandidatEmail($lamaranId);

            if ($lam->Status === 'GUGUR') {
                $status = 'GUGUR';
            } else {
                // Tahap TERAKHIR yang sudah diputus LULUS. CATATAN penting: saat lolos,
                // tahap di-set Status='SELESAI' + Hasil='LULUS' (BUKAN Status='LULUS').
                // Jadi deteksi lolos memakai kolom Hasil, bukan Status. Reusable untuk
                // tahap mana pun (administrasi, psikotes, wawancara, dst.).
                $lolos = DB::table('N_WEB_CAREERS_Lamaran_Tahap')
                    ->where('Lamaran_Id', $lamaranId)->where('Hasil', 'LULUS')
                    ->orderByDesc('Urutan')->first();

                if ($lolos) {
                    $status = 'LOLOS';
                    $extra['tahapLolos'] = $lolos->Label;
                    $extra['urutan'] = (int) $lolos->Urutan;
                    $extra['total'] = (int) ($lam->Total_Tahap ?? 0);
                    // Tahap berikutnya (null bila ini tahap terakhir → berarti DITERIMA).
                    $extra['tahapBerikut'] = DB::table('N_WEB_CAREERS_Lamaran_Tahap')
                        ->where('Lamaran_Id', $lamaranId)->where('Urutan', '>', $lolos->Urutan)
                        ->orderBy('Urutan')->value('Label');
                    $extra['diterima'] = $lam->Status === 'LULUS';
                } else {
                    // Belum ada keputusan otomatis (tahap 1 masih BERJALAN) → menunggu admin.
                    $status = 'MENUNGGU';
                }
            }

            WcApplyEmailJob::dispatch($userId, $status, $extra);

            Log::info("[APPLY] email hasil '{$status}' di-antre user #{$userId} lamaran #{$lamaranId} (queue " . WcApplyEmailJob::QUEUE . ').');
        } catch (\Throwable $e) {
            Log::error("[APPLY] gagal antre email hasil lamaran #{$lamaranId}: " . $e->getMessage());
        }
    }
}
