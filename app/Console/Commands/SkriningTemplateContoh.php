<?php

namespace App\Console\Commands;

use App\Support\Career\BankPertanyaan;
use App\Support\Career\Skrining;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * WEB CAREER — DUA TEMPLATE SKRINING SIAP PAKAI, DISUSUN DARI PUSTAKA.
 *
 * ── KENAPA PERINTAH ARTISAN, BUKAN BERKAS .SQL ──────────────────────────────
 *
 * Menyalin pertanyaan dari pustaka ke template bukan sekadar INSERT ... SELECT.
 * Ada aturan yang menentukan kolom mana ikut dan mana tidak: bobot hanya ikut
 * untuk tipe yang bisa dinilai, skala hanya untuk tipe berskala, dan panduan
 * penilaian digabung jadi satu Bantuan supaya sampai ke rekruter di worklist.
 *
 * Aturan itu sudah tertulis satu kali di BankPertanyaan::keSalinan(), dan itulah
 * yang dipakai tombol "Ambil dari Pustaka". Menuliskannya ulang dalam SQL
 * berarti punya dua salinan yang harus diubah bersamaan — dan yang satu pasti
 * ketinggalan. Jadi perintah ini memanggil fungsi yang sama persis.
 *
 * ── AMAN DIJALANKAN ULANG ───────────────────────────────────────────────────
 *
 * Template yang kodenya sudah ada dilewati, bukan ditimpa. Template yang sudah
 * dipakai sesi tidak boleh berubah isinya diam-diam — itu aturan yang sama yang
 * membuat versi PUBLISHED tidak pernah disunting.
 *
 *      php artisan career:skrining-contoh
 *      php artisan career:skrining-contoh --draf      (jangan langsung terbit)
 */
class SkriningTemplateContoh extends Command
{
    protected $signature = 'career:skrining-contoh {--draf : Biarkan versi 1 sebagai draf, jangan diterbitkan}';

    protected $description = 'Menyusun dua template phone screening siap pakai dari pustaka pertanyaan';

    /**
     * Resep dua template.
     *
     * Tiap potongan menyebut FASE dan KELOMPOK sekaligus, bukan salah satunya.
     * Fase sendiri terlalu longgar — CLOSING memuat pertanyaan penutup untuk
     * kandidat DAN kotak penilaian petugas, dan keduanya harus muncul di ujung
     * dengan urutan yang benar, bukan tercampur.
     *
     * Batas jumlahnya bukan angka sembarang: skrining telepon yang lewat 20-25
     * menit berhenti jadi penyaringan dan berubah jadi wawancara — dan kandidat
     * yang belum tentu lolos sudah menghabiskan jam kerja rekruter.
     */
    private const RESEP = [
        [
            'kode' => 'SKR-AWAL-UMUM',
            'nama' => 'Skrining Awal — Umum',
            'kategori' => 'REKRUTMEN',
            'deskripsi' => 'Kuesioner telepon untuk posisi apa pun: verifikasi data, riwayat singkat, ketersediaan, dan ekspektasi gaji.',
            'petunjuk' => 'Sebut nama Anda dan perusahaannya lebih dulu, lalu pastikan waktunya memang luang. Isi jawabannya sambil menelepon — jangan menunggu selesai.',
            'jf' => 'GENERAL',
            'ct' => null,
            'bagian' => [
                ['fase' => 'OPENING', 'kelompok' => ['General'], 'ambil' => 3],
                ['fase' => 'VERIFICATION', 'kelompok' => ['Verifikasi'], 'ambil' => 3],
                ['fase' => 'BACKGROUND', 'kelompok' => ['Riwayat Karier'], 'ambil' => 3],
                ['fase' => 'MOTIVATION', 'kelompok' => ['Motivasi'], 'ambil' => 2],
                ['fase' => 'AVAILABILITY', 'kelompok' => ['Ketersediaan'], 'ambil' => 4],
                ['fase' => 'COMPENSATION', 'kelompok' => ['Kompensasi'], 'ambil' => 2],
                ['fase' => 'CLOSING', 'kelompok' => ['Penutup'], 'ambil' => 2],
                ['fase' => null, 'kelompok' => ['Ringkasan Petugas'], 'ambil' => 2],
            ],
        ],
        [
            'kode' => 'SKR-AWAL-MT',
            'nama' => 'Skrining Awal — Management Trainee',
            'kategori' => 'MT',
            'deskripsi' => 'Kuesioner telepon untuk pelamar MT dan fresh graduate: motivasi, kesiapan ditempatkan, dan indikasi perilaku awal.',
            'petunjuk' => 'Pelamar MT umumnya belum pernah diwawancara kerja. Beri jeda sesudah tiap pertanyaan; diam beberapa detik bukan berarti ia tidak bisa menjawab.',
            'jf' => 'GENERAL',
            'ct' => 'MT',
            'bagian' => [
                ['fase' => 'OPENING', 'kelompok' => ['General'], 'ambil' => 3],
                ['fase' => 'VERIFICATION', 'kelompok' => ['Verifikasi'], 'ambil' => 2],
                ['fase' => 'MOTIVATION', 'kelompok' => ['Motivasi', 'Management Trainee'], 'ambil' => 3],
                ['fase' => 'BEHAVIORAL', 'kelompok' => ['Management Trainee'], 'ambil' => 4],
                ['fase' => 'CULTURE_FIT', 'kelompok' => ['Kecocokan Budaya'], 'ambil' => 2],
                ['fase' => 'AVAILABILITY', 'kelompok' => ['Ketersediaan'], 'ambil' => 3],
                ['fase' => 'CLOSING', 'kelompok' => ['Penutup'], 'ambil' => 2],
                ['fase' => null, 'kelompok' => ['Ringkasan Petugas'], 'ambil' => 2],
            ],
        ],
    ];

    public function handle(): int
    {
        if (! Skrining::siap() || ! BankPertanyaan::siap()) {
            $this->error('Skema skrining atau pustaka pertanyaan belum dijalankan.');

            return self::FAILURE;
        }

        foreach (self::RESEP as $resep) {
            $this->susun($resep, ! $this->option('draf'));
        }

        return self::SUCCESS;
    }

    private function susun(array $resep, bool $terbit): void
    {
        if (DB::table(Skrining::T_MASTER)->where('Kode', $resep['kode'])->exists()) {
            $this->line("  <fg=yellow>lewat</>  {$resep['kode']} — sudah ada, tidak ditimpa.");

            return;
        }

        $bank = $this->pilihPertanyaan($resep);

        if ($bank === []) {
            $this->line("  <fg=red>gagal</>  {$resep['kode']} — tidak ada pertanyaan pustaka yang cocok.");

            return;
        }

        $cap = [
            'Created_At' => now(), 'Created_By' => 'SISTEM', 'Created_By_Id' => null,
            'Updated_At' => now(), 'Updated_By' => 'SISTEM', 'Updated_By_Id' => null,
        ];

        DB::transaction(function () use ($resep, $bank, $cap, $terbit) {
            $id = DB::table(Skrining::T_MASTER)->insertGetId([
                'Kode' => $resep['kode'],
                'Nama' => $resep['nama'],
                'Deskripsi' => $resep['deskripsi'],
                'Petunjuk' => $resep['petunjuk'],
                'Kategori' => $resep['kategori'],
                'Flag_Aktif' => 'Y',
            ] + $cap, 'Id_Master_Skrining');

            $versiId = DB::table(Skrining::T_VERSI)->insertGetId([
                'Master_Skrining_Id' => $id,
                'Versi' => 1,
                'Status' => $terbit ? 'PUBLISHED' : 'DRAFT',
                'Published_At' => $terbit ? now() : null,
            ] + $cap, 'Id_Master_Skrining_Versi');

            // Disisipkan sepotong-sepotong, bukan seribu baris sekaligus:
            // jumlahnya puluhan, dan potongan kecil menjaga paketnya jauh di
            // bawah batas 2.100 parameter milik driver-nya.
            foreach (array_chunk($bank, 50, true) as $bagian) {
                $baris = [];
                foreach ($bagian as $urutan => $b) {
                    $baris[] = BankPertanyaan::keSalinan($b, $versiId, $urutan + 1) + $cap;
                }
                DB::table(Skrining::T_TANYA)->insert($baris);
            }
        });

        $status = $terbit ? '<fg=green>terbit</>' : '<fg=cyan>draf</>';
        $this->line("  <fg=green>dibuat</> {$resep['kode']} — ".count($bank)." pertanyaan, versi 1 {$status}.");

        foreach ($this->ringkasKelompok($bank) as $k => $n) {
            $this->line("           · {$k}: {$n}");
        }
    }

    /**
     * Ambil pertanyaan pustaka menurut resep, urut sesuai jalannya percakapan.
     *
     * Kunci hasilnya adalah urutan akhir; nilainya baris pustaka mentah. Kode
     * yang sudah terpilih di bagian sebelumnya dilewati — "Motivasi" dan
     * "Management Trainee" beririsan, dan satu pertanyaan yang muncul dua kali
     * dalam satu telepon membuat rekruternya terlihat tidak menyimak.
     */
    private function pilihPertanyaan(array $resep): array
    {
        $out = [];
        $sudah = [];

        foreach ($resep['bagian'] as $bagian) {
            $q = DB::table(BankPertanyaan::TABEL.' as p')
                ->where('p.Flag_Aktif', 'Y')
                ->where('p.Konteks', 'SKRINING')
                ->whereIn('p.Kelompok', $bagian['kelompok'])
                ->whereIn('p.Prioritas', ['MANDATORY', 'RECOMMENDED'])
                ->whereExists(fn ($w) => $w->select(DB::raw(1))
                    ->from(BankPertanyaan::T_IKAT_TAG.' as t')
                    ->whereColumn('t.Master_Pertanyaan_Id', 'p.Id_Master_Pertanyaan')
                    ->where('t.Dimensi', 'JOB_FAMILY')->where('t.Tag_Kode', $resep['jf']));

            if ($resep['ct']) {
                $q->whereExists(fn ($w) => $w->select(DB::raw(1))
                    ->from(BankPertanyaan::T_IKAT_TAG.' as c')
                    ->whereColumn('c.Master_Pertanyaan_Id', 'p.Id_Master_Pertanyaan')
                    ->where('c.Dimensi', 'CANDIDATE_TYPE')->where('c.Tag_Kode', $resep['ct']));
            }

            if ($bagian['fase']) {
                $q->whereExists(fn ($w) => $w->select(DB::raw(1))
                    ->from(BankPertanyaan::T_IKAT_TAG.' as f')
                    ->whereColumn('f.Master_Pertanyaan_Id', 'p.Id_Master_Pertanyaan')
                    ->where('f.Dimensi', 'FASE')->where('f.Tag_Kode', $bagian['fase']));
            }

            if ($sudah !== []) {
                $q->whereNotIn('p.Kode', $sudah);
            }

            // Yang wajib lebih dulu, lalu urutan bawaan pustaka. Urutan itu
            // sudah menyimpan alur percakapan yang benar di dalam kelompoknya.
            $rows = $q->orderByRaw("CASE p.Prioritas WHEN 'MANDATORY' THEN 0 ELSE 1 END")
                ->orderBy('p.Urutan')
                ->orderBy('p.Id_Master_Pertanyaan')
                ->limit($bagian['ambil'])
                ->get(['p.*']);

            foreach ($rows as $r) {
                $out[] = $r;
                $sudah[] = $r->Kode;
            }
        }

        return $out;
    }

    private function ringkasKelompok(array $bank): array
    {
        $out = [];
        foreach ($bank as $b) {
            $k = $b->Kelompok ?: 'Tanpa kelompok';
            $out[$k] = ($out[$k] ?? 0) + 1;
        }

        return $out;
    }
}
