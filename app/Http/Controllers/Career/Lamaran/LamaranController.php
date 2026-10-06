<?php

namespace App\Http\Controllers\Career\Lamaran;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Career\MasterLokasi\MasterLokasiController;
use App\Http\Controllers\Controller;
use App\Jobs\Career\WcApplyEmailJob;
use App\Jobs\Career\WcApplyFormJob;
use App\Jobs\Career\WcBerkasSeleksiJob;
use App\Jobs\Career\WcBiodataHrisJob;
use App\Jobs\Career\WcJadwalEmailJob;
use App\Jobs\Career\WcLaporanKandidatJob;
use App\Support\Career\AksesService;
use App\Support\Career\AlurKolom;
use App\Support\Career\BerkasBaris;
use App\Support\Career\BerkasFormulir;
use App\Support\Career\BerkasSeleksi;
use App\Support\Career\BatasIsi;
use App\Support\Career\BiayaAktivitas;
use App\Support\Career\CatatanEksternal;
use App\Support\Career\FormulirSchema;
use App\Support\Career\GcsBerkas;
use App\Support\Career\HtmlBersih;
use App\Support\Career\JadwalPrivat;
use App\Support\Career\JejakJadwal;
use App\Support\Career\KatalogPrefill;
use App\Support\Career\KonfirmasiJadwal;
use App\Support\Career\LamaranService;
use App\Support\Career\Pemeriksaan;
use App\Support\Career\LamaranTargetValidator;
use App\Support\Career\PemulihanBerkas;
use App\Support\Career\PipelineProgress;
use App\Support\Career\RakitBerkasSeleksi;
use App\Support\Career\SuratJadwal;
use App\Support\Career\UlangTahap;
use App\Support\Career\UndanganJadwal;
use App\Support\CareerShell;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Vinkla\Hashids\Facades\Hashids;

/**
 * WEB CAREER — Lamaran (sisi kandidat + worklist admin).
 *
 * Sisi kandidat  : lihat loker, melamar, isi formulir tiap tahap, pantau status.
 * Sisi admin     : worklist tahap yang menunggu keputusan + ketuk palu.
 *
 * Seluruh mutasi berat (snapshot tahap, mesin syarat, gerak lamaran) ada di
 * LamaranService supaya bisa diuji terpisah dari HTTP.
 */
class LamaranController extends Controller
{
    /** Kunci halaman worklist — dipakai middleware DAN penyaring kategori. */
    private const PAGE = 'pelamarPage';

    /**
     * Batas ukuran berkas lamaran bila isiannya tidak ditemukan di skema —
     * aturan lama sebelum batas mengikuti skema (BerkasFormulir::aturanUnggah).
     */
    private const MAKS_MB_APPLY = 2;

    public function __construct(
        private LamaranService $svc,
        private LamaranTargetValidator $targetValidator,
    ) {}

    /**
     * 422 yang MEMBAWA rincian berkas yang kurang.
     *
     * Kalimatnya untuk dibaca kandidat; `berkasKurang` untuk layar — supaya ia
     * bisa mengosongkan tepat isian yang bermasalah dan kandidat melihat kotak
     * unggah itu lagi, bukan sekadar notifikasi yang hilang dalam empat detik.
     */
    private function galatBerkasKurang(array $kurang)
    {
        return response()->json([
            'success' => false,
            'status' => 422,
            'message' => BerkasFormulir::pesanRingkas($kurang),
            'result' => [
                'berkasKurang' => array_map(fn (array $k) => [
                    'bagian' => $k['bagian'],
                    'baris' => $k['baris'],
                    'field' => $k['field'],
                    'label' => $k['label'],
                    'pesan' => $k['pesan'],
                ], $kurang),
            ],
        ], 422);
    }

    /**
     * MASTER TIPE TAHAP — satu-satunya sumber "aktivitas ini dijalankan bagaimana".
     *
     * Dikunci per-request supaya tidak dikueri berulang di dalam perulangan
     * tahap/aktivitas. Kolomnya yang menggantikan kode-kode yang dulu ditulis
     * langsung di PHP & Vue:
     *   Perilaku_Kode 'CAT' → ujian online berjadwal (dulu: cek Provider/jenis tes)
     *   Flag_Formulir       → tipe ini menempelkan formulir (dulu: 'FORM'/'DOCUMENT')
     *   Flag_Upload_Hasil   → tipe ini berbasis berkas hasil (dulu: 'MCU')
     *   Pesan_Kandidat      → kalimat yang dibaca kandidat saat menunggu di sini
     */
    public static function masterTipeTahap(): \Illuminate\Support\Collection
    {
        // SELURUH KOLOM, bukan daftar pilih.
        //
        // Daftar kolom yang ditulis manual di sini sudah dua kali diam-diam
        // membuang kolom baru: `Mode_Jadwal_Bawaan` (bentuk jadwal bawaan tiap
        // tipe) dan `Label_Berkas` sama-sama tersimpan rapi di master lalu
        // hilang sebelum sampai ke layar — tanpa satu pun galat, karena
        // `$tipe->Kolom_Baru ?? null` dengan patuh menghasilkan null.
        //
        // Masternya belasan baris; mengambil semua kolomnya tidak lebih mahal,
        // dan menutup kelas kekeliruan yang tak terlihat sampai ada yang
        // bertanya kenapa setelannya "tidak berfungsi".
        //
        // Pemuatnya milik AlurKolom (bentuknya sama persis): papan worklist
        // memakai keduanya dalam satu permintaan, dan dua pemuat terpisah
        // berarti tabel yang sama dikueri dua kali.
        return AlurKolom::masterTipe();
    }

    private static ?\Illuminate\Support\Collection $modePengumumanCache = null;

    /**
     * Master "kapan hasil boleh dilihat kandidat".
     *
     * Dua kolom yang menentukan, keduanya dari master — bukan dari `if` kode:
     *   Flag_Terbit_Otomatis = 'Y' -> terbit begitu keputusan dibuat;
     *   Butuh_Jeda           = 'Y' -> tertahan sampai Waktu_Diumumkan terlewati;
     *   selain itu                 -> tertahan sampai admin mengisi Waktu_Diumumkan.
     */
    public static function masterModePengumuman(): \Illuminate\Support\Collection
    {
        return self::$modePengumumanCache ??= DB::table('N_WEB_CAREERS_Master_Mode_Pengumuman')
            ->get(['Kode', 'Nama', 'Label', 'Ikon', 'Warna', 'Butuh_Jeda', 'Flag_Terbit_Otomatis'])
            ->keyBy('Kode');
    }

    /**
     * Hasil tahap ini sudah boleh dilihat kandidat? Aturan Master Mode
     * Pengumuman di atas, dalam SATU tempat: portal kandidat memakainya untuk
     * menahan hasil & catatan eksternal, worklist memakainya untuk memberi tahu
     * admin apakah catatannya sudah terbaca. Dua salinan akan berselisih.
     */
    private static function terbitKeKandidat(object $t, ?\Carbon\Carbon $sekarang = null): bool
    {
        $mp = self::masterModePengumuman()->get(strtoupper((string) ($t->Mode_Pengumuman ?: 'OTOMATIS')));
        $diumumkan = $t->Waktu_Diumumkan ? \Carbon\Carbon::parse($t->Waktu_Diumumkan) : null;
        $sekarang ??= now();

        return ($mp->Flag_Terbit_Otomatis ?? 'N') === 'Y'
            || (($mp->Butuh_Jeda ?? 'N') === 'Y'
                ? ($diumumkan && $sekarang->gte($diumumkan))
                : (bool) $diumumkan);
    }

    // ═══════════════════════ KANDIDAT ═══════════════════════

    /** /kandidat/loker — daftar posisi yang sedang dibuka. */
    public function loker()
    {
        return Inertia::render('Career/portal/Loker', CareerShell::props('/kandidat/loker', 'Cari Lowongan', [
            'loker' => $this->daftarLoker(),
        ]));
    }

    /** JSON daftar loker — posisi BUKA pada program BERJALAN. */
    public function lokerList()
    {
        return ResponseHelper::success($this->daftarLoker(), 'Daftar loker');
    }

    /**
     * Loker = PEMBUKAAN yang terbit & dalam masa berlaku, dikalikan posisi program-nya.
     * Satu kartu = satu (pembukaan × posisi). Channel UMUM/KAMPUS sudah digabung —
     * tiap program cukup satu pembukaan; pembatasan kampus lewat SYARAT, bukan channel.
     */
    private function daftarLoker(): array
    {
        // Window pendaftaran presisi sampai JAM (kolom kini datetime).
        $kini = now();

        $pembukaan = DB::table('N_WEB_CAREERS_Pembukaan as pb')
            ->join('N_WEB_CAREERS_Program as p', 'p.Id_Program', '=', 'pb.Program_Id')
            ->where('pb.Status_Publish', 'TERBIT')
            ->where('p.Status', 'BERJALAN')
            // EVERGREEN selalu buka; BERBATAS harus dalam rentang tanggal+jam.
            ->where(function ($q) use ($kini) {
                $q->where('pb.Masa_Berlaku', 'EVERGREEN')
                    ->orWhere(function ($w) use ($kini) {
                        $w->where('pb.Masa_Berlaku', 'BERBATAS')
                            ->where(function ($a) use ($kini) {
                                $a->whereNull('pb.Tanggal_Buka')->orWhere('pb.Tanggal_Buka', '<=', $kini);
                            })
                            ->where(function ($b) use ($kini) {
                                $b->whereNull('pb.Tanggal_Tutup')->orWhere('pb.Tanggal_Tutup', '>=', $kini);
                            });
                    });
            })
            ->select('pb.*', 'p.Nama as ProgramNama', 'p.Kategori', 'p.Warna', 'p.Kode as ProgramKode')
            ->get();

        if ($pembukaan->isEmpty()) {
            return [];
        }

        // Posisi BUKA per program, dikelompokkan agar tidak query berulang.
        //
        // PINTU KEDUA YANG MENGHADAP KANDIDAT. /kandidat/loker menyusun daftarnya
        // sendiri, tidak lewat landing — jadi penyaring loker-yang-dimatikan
        // harus ditulis di sini juga. Menyaringnya di satu tempat saja berarti
        // loker yang sudah dimatikan hilang dari beranda tapi tetap berdiri di
        // katalog portal, lengkap dengan tombol lamarnya.
        $posisi = DB::table('N_WEB_CAREERS_Program_Posisi')
            ->whereIn('Program_Id', $pembukaan->pluck('Program_Id')->unique())
            ->where('Status', 'BUKA')
            ->whereRaw("ISNULL(Flag_Aktif, 'Y') = 'Y'")
            ->get()
            ->groupBy('Program_Id');

        $out = [];
        foreach ($pembukaan as $pb) {
            foreach ($posisi->get($pb->Program_Id, []) as $x) {
                $out[] = [
                    'applyId' => 'PB-'.$pb->Kode.'-'.$x->Id_Program_Posisi,
                    'programDetailId' => 'PB-'.$pb->Kode,
                    'pembukaanId' => Hashids::encode($pb->Id_Pembukaan),
                    'posisiId' => Hashids::encode($x->Id_Program_Posisi),
                    'program' => $pb->ProgramNama,
                    'kategori' => $pb->Kategori,
                    'warna' => $pb->Warna,
                    'tutup' => $pb->Masa_Berlaku === 'BERBATAS' ? $pb->Tanggal_Tutup : null,
                    'posisi' => $x->Posisi,
                    'departemen' => $x->Departemen,
                    'lokasi' => $x->Lokasi,
                    'level' => $x->Level ?? null,
                    'kuota' => (int) $x->Kuota,
                ];
            }
        }

        return $out;
    }

    /**
     * POST /api/v1/lamaran — kandidat melamar. MULTIPART (dinamis: field + berkas + foto).
     *
     * Request: validasi → STREAM berkas ke GCS (bukan base64/JSON, aman dari batas
     * ukuran) → simpan payload (form + PATH berkas) di staging → dispatch job.
     * Job: insert DB (lamaran+tahap+pengisian+berkas); bila gagal, berkas GCS dihapus.
     *
     * Fleksibel: dengan berkas atau tanpa berkas sama-sama jalan.
     * Berkas: hanya PDF & JPG, maks 2 MB/berkas. Foto verifikasi: JPG.
     */
    public function lamar(Request $request)
    {
        $userId = (int) session('career_auth.id');
        if (! $userId) {
            return ResponseHelper::error('Sesi tidak sah.', 401);
        }

        try {
            $data = $request->validate([
                'pembukaanId' => 'required|string',
                'posisiId' => 'required|string',
                'gugur' => 'nullable',
                'alasan' => 'nullable|string',
                'jawaban' => 'nullable',
                // Format & ukuran tiap berkas diperiksa terhadap SKEMA isiannya
                // di bawah (BerkasFormulir), bukan satu aturan tetap untuk semua:
                // dulu 2 MB & PDF/JPG berlaku walau Master Formulir menjanjikan
                // 5 MB atau PNG.
                'berkas' => 'nullable|array|max:40',
                'berkas.*' => 'file',
                // Berkas milik BARIS bagian berulang (mis. sertifikat). Dulu
                // semuanya dikirim sebagai berkas[field] — tiga sertifikat
                // runtuh jadi satu karena kuncinya sama persis.
                'baris' => 'nullable|array|max:100',
                'baris.*.berkas' => 'required|file',
                'baris.*.bagian' => 'required|string|max:60',
                'baris.*.baris' => 'required|integer|min:0|max:99',
                'baris.*.field' => 'required|string|max:60',
                'foto' => 'nullable|file|max:2048|mimetypes:image/jpeg',
            ], [
                'berkas.*.file' => 'Salah satu berkas gagal terkirim utuh. Pilih ulang berkasnya lalu kirim kembali.',
                'baris.*.berkas.file' => 'Salah satu berkas gagal terkirim utuh. Pilih ulang berkasnya lalu kirim kembali.',
                'foto.max' => 'Foto verifikasi maksimal 2 MB.',
                'foto.mimetypes' => 'Foto verifikasi harus JPG.',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ResponseHelper::error(collect($e->errors())->flatten()->first() ?? 'Berkas tidak valid.', 422);
        }

        $pembukaanId = Hashids::decode($data['pembukaanId'])[0] ?? null;
        $posisiId = Hashids::decode($data['posisiId'])[0] ?? null;
        if (! $pembukaanId || ! $posisiId) {
            return ResponseHelper::error('Data lamaran tidak valid.', 422);
        }

        // Tolak pasangan pembukaan-posisi yang tidak berhubungan sebelum berkas
        // diunggah. Job memvalidasi ulang jika status berubah selama mengantre.
        $target = $this->targetValidator->validasi((int) $pembukaanId, (int) $posisiId);
        if (! $target['ok']) {
            return ResponseHelper::error($target['pesan'], 422);
        }

        // Cek duplikat lebih awal (umpan balik cepat) — job juga idempoten.
        $sudah = DB::table('N_WEB_CAREERS_Lamaran')
            ->where('Id_Users', $userId)
            ->where('Program_Id', $target['program']->Id_Program)
            ->where('Program_Posisi_Id', (int) $posisiId)
            ->exists();
        if ($sudah) {
            return ResponseHelper::error('Anda sudah melamar posisi ini.', 422);
        }

        // ── KELAYAKAN JALUR (aturan MT/REKRUTMEN + cooldown, master DB) ──
        // Feedback INSTAN sebelum unggah berkas (hindari berkas GCS yatim). Job &
        // service tetap cek ulang (defense-in-depth).
        $kategoriProgram = $target['program']->Kategori;
        $kelayakan = (new \App\Support\Career\KelayakanLamaran)->cek($userId, $kategoriProgram);
        if (! $kelayakan['boleh']) {
            return ResponseHelper::error($kelayakan['alasan'], 422);
        }

        // jawaban dikirim sebagai JSON string di multipart.
        $jawaban = $request->input('jawaban');
        if (is_string($jawaban)) {
            $jawaban = json_decode($jawaban, true) ?: [];
        }
        $gugurAlasan = filter_var($request->input('gugur'), FILTER_VALIDATE_BOOLEAN) ? ($data['alasan'] ?? 'Tidak memenuhi syarat wajib.') : null;

        // ── BERKAS DIPERIKSA SEBELUM SATU PUN NAIK KE GCS ──────────────────
        //
        // Skemanya dari sumber yang SAMA dengan halaman lamar
        // (FormulirSchema::pendaftaranProgram), jadi yang dituntut server
        // persis yang tampil di layar kandidat.
        $skema = FormulirSchema::pendaftaranProgram(
            $target['program']->Alur_Kode ?? null,
            (string) ($target['program']->Kategori ?? ''),
        )['schema'] ?? null;

        $masuk = [];
        foreach ((array) $request->file('berkas', []) as $field => $file) {
            if ($file) {
                $masuk[] = ['file' => $file, 'bagian' => null, 'baris' => null, 'field' => (string) $field];
            }
        }
        foreach ((array) ($data['baris'] ?? []) as $i => $b) {
            if ($file = $request->file("baris.{$i}.berkas")) {
                $masuk[] = ['file' => $file, 'bagian' => (string) $b['bagian'], 'baris' => (int) $b['baris'], 'field' => (string) $b['field']];
            }
        }

        foreach ($masuk as $m) {
            if ($m['bagian'] !== null && ($galat = FormulirDrafController::periksaBaris($skema, $m['bagian'], $m['baris'], $m['field']))) {
                return ResponseHelper::error($galat, 422);
            }
            $aturan = BerkasFormulir::aturanUnggah($skema, $m['bagian'], $m['field'], self::MAKS_MB_APPLY);
            if ($galat = BerkasFormulir::periksaBerkas($m['file'], $aturan)) {
                return ResponseHelper::error($galat, 422);
            }
        }

        // Berkas yang diwajibkan skema — atau yang namanya sudah disebut
        // jawaban — HARUS ikut terkirim. Dulu `berkas` cukup `nullable`: berkas
        // yang tertinggal di browser (dipulihkan dari draf, dibuang PHP karena
        // melewati max_file_uploads, gagal dibaca) lolos tanpa jejak, dan
        // lamarannya tercatat lengkap dengan nama CV tanpa CV-nya.
        $kurang = BerkasFormulir::kurang(
            $skema,
            is_array($jawaban) ? $jawaban : [],
            function (?string $bagian, ?int $baris, string $field) use ($masuk): bool {
                foreach ($masuk as $m) {
                    if ($m['bagian'] === $bagian && $m['baris'] === $baris && $m['field'] === $field) {
                        return true;
                    }
                }

                return false;
            },
        );
        if ($kurang) {
            return $this->galatBerkasKurang($kurang);
        }

        $nama = self::namaDariJawaban($jawaban) ?: session('career_auth.nama', 'Kandidat');
        $processId = (string) Str::uuid();
        $now = now();

        // ── UNGGAH BERKAS KE GCS SAAT REQUEST (stream binary, BUKAN base64/JSON) ──
        // Payload staging hanya menyimpan PATH-nya → tidak ada gambar di JSON, aman
        // dari batas ukuran. Apply fleksibel: tanpa berkas pun tetap jalan.
        $gcs = app(\App\Support\Career\GcsBerkas::class);
        $folder = $gcs->folderKandidat($now->format('Y'), $now->format('m'), $now->format('d'), $nama);
        $terunggah = [];
        $berkasMeta = [];

        try {
            foreach ($masuk as $m) {
                $file = $m['file'];
                $ext = $gcs->normalkanExt(strtolower($file->getClientOriginalExtension() ?: $file->extension()));
                $konten = $file->getContent(); // andal (getPathname), bukan getRealPath yang bisa kosong di Apache
                // Nama objek UNIK: path deterministik membuat dua pelamar
                // bernama sama di hari yang sama saling menimpa CV.
                $path = $gcs->unggahUnik($folder, $m['field'], $ext, $konten);

                $terunggah[] = $path;
                $berkasMeta[] = [
                    'field' => $m['field'],
                    'bagian' => $m['bagian'],
                    'baris' => $m['baris'],
                    'nama' => $file->getClientOriginalName(),
                    'path' => $path,
                    'ext' => $ext,
                    'mime' => $file->getMimeType(),
                    'ukuran' => strlen($konten),
                    'hash' => hash('sha256', $konten),
                ];
            }

            if ($request->hasFile('foto')) {
                $f = $request->file('foto');
                $konten = $f->getContent();
                $path = $gcs->unggahFoto($folder, $konten);
                $terunggah[] = $path;
                $berkasMeta[] = [
                    'field' => 'foto_verifikasi',
                    'nama' => 'verifikasi.jpg',
                    'path' => $path,
                    'ext' => 'jpg',
                    'mime' => $f->getMimeType() ?: 'image/jpeg',
                    'ukuran' => strlen($konten),
                    'hash' => hash('sha256', $konten),
                ];
            }
        } catch (\Throwable $e) {
            // Ada berkas gagal unggah → bersihkan yang sempat masuk, batalkan (tak ada insert).
            $gcs->hapus($terunggah);
            Log::channel('web_career')->error('[APPLY] unggah berkas gagal: '.$e->getMessage()
                .' | at '.$e->getFile().':'.$e->getLine()."\n".$e->getTraceAsString());

            return ResponseHelper::error('Gagal mengunggah berkas: '.$e->getMessage(), 422);
        }

        try {
            DB::table('N_WEB_CAREERS_Apply_Payload')->insert([
                'Process_Id' => $processId,
                'Users_Id' => $userId,
                'Pembukaan_Id' => (int) $pembukaanId,
                'Program_Posisi_Id' => (int) $posisiId,
                'Nama_Kandidat' => $nama,
                'Tahun' => $now->format('Y'),
                'Bulan' => $now->format('m'),
                'Tanggal' => $now->format('d'),
                'Payload_Json' => json_encode([
                    'userId' => $userId,
                    'pembukaanId' => (int) $pembukaanId,
                    'posisiId' => (int) $posisiId,
                    'jawaban' => $jawaban,
                    'gugurAlasan' => $gugurAlasan,
                ], JSON_UNESCAPED_UNICODE),
                'Berkas_Json' => json_encode($berkasMeta),
                'Status' => 'MENUNGGU',
                'Created_At' => $now, 'Created_By' => $nama, 'Created_By_Id' => $userId,
                'Updated_At' => $now, 'Updated_By' => $nama, 'Updated_By_Id' => $userId,
            ]);

            WcApplyFormJob::dispatch($processId);

            Log::channel('web_career')->info("[APPLY] {$processId} diantrikan (wc-applyform) — ".count($berkasMeta).' berkas.');

            return ResponseHelper::success([
                'processId' => $processId,
                'mode' => 'ANTRIAN',
            ], 'Lamaran diterima & sedang diproses.', 202);
        } catch (\Throwable $e) {
            // Payload/dispatch gagal setelah berkas terunggah → bersihkan (tak ada yatim).
            $gcs->hapus($terunggah);
            Log::channel('web_career')->error('Gagal mengantrikan lamaran: '.$e->getMessage());

            return ResponseHelper::error('Gagal memproses lamaran.', 500);
        }
    }

    /**
     * GET /api/v1/lamaran/apply-status/{processId} — status pemrosesan apply (queue).
     * Dipoll frontend supaya kandidat lihat HASIL NYATA (lolos/gugur), bukan sukses palsu.
     */
    public function applyStatus(string $processId)
    {
        $userId = (int) session('career_auth.id');
        $row = DB::table('N_WEB_CAREERS_Apply_Payload')->where('Process_Id', $processId)->first();
        if (! $row || (int) $row->Users_Id !== $userId) {
            return ResponseHelper::error('Proses tidak ditemukan.', 404);
        }

        $out = ['status' => $row->Status, 'pesan' => $row->Pesan_Error];

        if ($row->Status === 'SELESAI' && $row->Lamaran_Id) {
            $l = DB::table('N_WEB_CAREERS_Lamaran')->where('Id_Lamaran', $row->Lamaran_Id)->first();

            // Sudah ada tahap yang benar-benar diputus LULUS?
            // Kalau belum, kandidat masih MENUNGGU keputusan admin — tahap
            // pertamanya bermode manual. Tanpa penanda ini layar hasil akan
            // menyatakan "Lolos Seleksi Administrasi" untuk lamaran yang belum
            // diputus siapa pun. Memakai kolom Hasil (bukan Status) karena saat
            // lolos tahap disimpan Status='SELESAI' + Hasil='LULUS'.
            $adaLulus = $l && DB::table('N_WEB_CAREERS_Lamaran_Tahap')
                ->where('Lamaran_Id', $l->Id_Lamaran)->where('Hasil', 'LULUS')->exists();

            $tahapKini = $l ? DB::table('N_WEB_CAREERS_Lamaran_Tahap')
                ->where('Lamaran_Id', $l->Id_Lamaran)->where('Urutan', $l->Urutan_Tahap)
                ->value('Label') : null;

            $out['lamaran'] = $l ? [
                'id' => Hashids::encode($l->Id_Lamaran),
                'kode' => $l->Kode,
                'status' => $l->Status,          // BERJALAN / GUGUR
                'hasilAkhir' => $l->Hasil_Akhir,
                'gugurDi' => $l->Gugur_Di_Tahap,
                'alasanGugur' => $l->Alasan_Gugur,
                'tahap' => (int) $l->Urutan_Tahap,
                'totalTahap' => (int) $l->Total_Tahap,
                'tahapLabel' => $tahapKini,
                // true → belum ada tahap yang diputus lolos; jangan ucapkan selamat.
                'menungguKeputusan' => $l->Status === 'BERJALAN' && ! $adaLulus,
            ] : null;
        }

        return ResponseHelper::success($out, 'Status lamaran');
    }

    /** DELETE /api/v1/lamaran/{id} — kandidat MENGHAPUS/membatalkan lamarannya sendiri. */
    public function batalkan(string $id)
    {
        $userId = (int) session('career_auth.id');
        if (! $userId) {
            return ResponseHelper::error('Sesi tidak sah.', 401);
        }

        $realId = Hashids::decode($id)[0] ?? null;
        if (! $realId) {
            return ResponseHelper::error('Lamaran tidak valid.', 422);
        }

        // Hanya boleh menghapus lamaran MILIK SENDIRI.
        $lamaran = DB::table('N_WEB_CAREERS_Lamaran')
            ->where('Id_Lamaran', $realId)->where('Id_Users', $userId)->first();
        if (! $lamaran) {
            return ResponseHelper::error('Lamaran tidak ditemukan.', 404);
        }

        try {
            DB::transaction(function () use ($realId) {
                // Anak-anaknya dulu (urutan aman terhadap referensi).
                $pengisianIds = DB::table('N_WEB_CAREERS_Formulir_Pengisian')
                    ->where('Lamaran_Id', $realId)->pluck('Id_Formulir_Pengisian');
                if ($pengisianIds->isNotEmpty()) {
                    DB::table('N_WEB_CAREERS_Formulir_Berkas')->whereIn('Formulir_Pengisian_Id', $pengisianIds)->delete();
                }
                DB::table('N_WEB_CAREERS_Formulir_Jawaban_Index')->where('Lamaran_Id', $realId)->delete();
                DB::table('N_WEB_CAREERS_Formulir_Pengisian')->where('Lamaran_Id', $realId)->delete();
                DB::table('N_WEB_CAREERS_Lamaran_Tahap')->where('Lamaran_Id', $realId)->delete();
                DB::table('N_WEB_CAREERS_Lamaran')->where('Id_Lamaran', $realId)->delete();
            });

            Log::channel('web_career')->info("Lamaran #{$realId} dibatalkan oleh kandidat #{$userId}");

            return ResponseHelper::success(null, 'Lamaran dibatalkan.');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('Gagal membatalkan lamaran: '.$e->getMessage());

            return ResponseHelper::error('Gagal membatalkan lamaran.', 500);
        }
    }

    /** /kandidat/portal — lamaran milik kandidat yang sedang login. */
    public function portalIndex()
    {
        $lamaran = $this->lamaranSaya();

        // Statistik REAL untuk strip widget dashboard (bukan hardcode di Vue).
        $stats = [
            'total' => count($lamaran),
            'berjalan' => count(array_filter($lamaran, fn ($l) => $l['status'] === 'BERJALAN')),
            'lulus' => count(array_filter($lamaran, fn ($l) => $l['status'] === 'LULUS')),
            'gugur' => count(array_filter($lamaran, fn ($l) => in_array($l['status'], ['GUGUR', 'MUNDUR'], true))),
        ];

        return Inertia::render('Career/portal/LamaranSaya', CareerShell::props('/kandidat/portal', 'Lamaran Saya', [
            'lamaran' => $lamaran,
            'stats' => $stats,
        ]));
    }

    private function lamaranSaya(): array
    {
        $userId = (int) session('career_auth.id');

        $rows = DB::table('N_WEB_CAREERS_Lamaran as l')
            ->join('N_WEB_CAREERS_Program as p', 'p.Id_Program', '=', 'l.Program_Id')
            ->leftJoin('N_WEB_CAREERS_Program_Posisi as x', 'x.Id_Program_Posisi', '=', 'l.Program_Posisi_Id')
            ->where('l.Id_Users', $userId)
            ->orderByDesc('l.Id_Lamaran')
            ->select('l.*', 'p.Nama as ProgramNama', 'p.Warna', 'x.Posisi', 'x.Lokasi')
            ->get();

        // KARTU sama persis dengan landing (MT & rekrutmen) — dibangun ulang dari
        // sumber yang sama, lalu dicocokkan ke lamaran lewat pembukaan (+posisi).
        $landing = app(\App\Http\Controllers\Career\CareerLandingController::class);
        $petaMt = collect($landing->dbMtCards())->keyBy('pembukaanId');
        $petaRek = collect($landing->dbLowonganCards())->keyBy(fn ($c) => ($c['pembukaanId'] ?? '').'|'.($c['posisiId'] ?? ''));

        // TAHAPAN nyata tiap lamaran (nama + status per urutan) — dipakai stepper
        // "PROGRES SELEKSI" pada kartu Lamaran Aktif, sekali kueri untuk semuanya.
        $tahapPeta = collect();
        if ($rows->isNotEmpty()) {
            $tahapPeta = DB::table('N_WEB_CAREERS_Lamaran_Tahap')
                ->whereIn('Lamaran_Id', $rows->pluck('Id_Lamaran')->all())
                ->orderBy('Urutan')
                ->select('Lamaran_Id', 'Urutan', 'Label', 'Status', 'Hasil')
                ->get()
                ->groupBy('Lamaran_Id');
        }

        return $rows->map(function ($l) use ($petaMt, $petaRek, $tahapPeta) {
            $pbHash = $l->Pembukaan_Id ? Hashids::encode($l->Pembukaan_Id) : null;
            $posHash = $l->Program_Posisi_Id ? Hashids::encode($l->Program_Posisi_Id) : null;
            $kartu = $l->Kategori === 'MT'
                ? ($petaMt[$pbHash] ?? null)
                : ($petaRek[$pbHash.'|'.$posHash] ?? null);

            $tahapan = ($tahapPeta[$l->Id_Lamaran] ?? collect())->map(fn ($t) => [
                'urutan' => (int) $t->Urutan,
                'label' => $t->Label,
                'status' => $t->Status,
                'hasil' => $t->Hasil,
            ])->values()->all();

            return [
                'id' => Hashids::encode($l->Id_Lamaran),
                'kode' => $l->Kode,
                'program' => $l->ProgramNama,
                'posisi' => $l->Posisi,
                'lokasi' => $l->Lokasi,
                'warna' => $l->Warna,
                'kategori' => $l->Kategori,
                'status' => $l->Status,
                'statusLabel' => self::statusKandidat($l->Status)['label'],
                'statusNada' => self::statusKandidat($l->Status)['nada'],
                'hasilAkhir' => $l->Hasil_Akhir,
                'urutanTahap' => (int) $l->Urutan_Tahap,
                'totalTahap' => (int) $l->Total_Tahap,
                'gugurDi' => $l->Gugur_Di_Tahap,
                'waktuLamar' => $l->Waktu_Lamar,
                'tahapan' => $tahapan,
                // Data kartu gaya-landing (bisa null bila pembukaan sudah tak terbit).
                'kartu' => $kartu,
            ];
        })->values()->all();
    }

    /** /kandidat/lamaran/{id} — detail satu lamaran + tahap aktif + formulirnya. */
    public function portalDetail(string $id)
    {
        $userId = (int) session('career_auth.id');
        $realId = Hashids::decode($id)[0] ?? null;

        $lamaran = DB::table('N_WEB_CAREERS_Lamaran as l')
            ->join('N_WEB_CAREERS_Program as p', 'p.Id_Program', '=', 'l.Program_Id')
            ->leftJoin('N_WEB_CAREERS_Program_Posisi as x', 'x.Id_Program_Posisi', '=', 'l.Program_Posisi_Id')
            ->leftJoin('N_WEB_CAREERS_Program_Batch as b', 'b.Id_Program_Batch', '=', 'l.Program_Batch_Id')
            ->leftJoin('N_WEB_CAREERS_Pembukaan as pb', 'pb.Id_Pembukaan', '=', 'l.Pembukaan_Id')
            ->where('l.Id_Lamaran', $realId)
            ->where('l.Id_Users', $userId)
            ->select('l.*', 'p.Nama as ProgramNama', 'p.Kategori as ProgramKategori', 'p.Penyelenggara',
                'x.Posisi', 'x.Lokasi', 'x.Departemen', 'b.Nama as BatchNama', 'pb.Kode as PembukaanKode')
            ->first();

        if (! $lamaran) {
            abort(404);
        }

        // Token ujian pihak ke-3 milik pelamar ini, dikunci per Penjadwalan_Tahap.
        // Cocokkan ke baris peserta lewat lamaran, akun login, atau kode pelamar.
        $tahapRows = DB::table('N_WEB_CAREERS_Lamaran_Tahap')
            ->where('Lamaran_Id', $realId)
            ->orderBy('Urutan')
            ->get();

        // SUB-TES tiap tahap. Satu tahap bisa berisi beberapa aktivitas (mis.
        // Psikotes 2 + DISC + Wawancara) yang dijadwalkan sendiri-sendiri, jadi
        // status "sudah/belum dijadwalkan" hidup di sini, bukan di level tahap.
        $subTesRows = DB::table('N_WEB_CAREERS_Lamaran_Tahap_Tes')
            ->whereIn('Lamaran_Tahap_Id', $tahapRows->pluck('Id_Lamaran_Tahap')->all())
            ->orderBy('Urutan')
            ->get()
            ->groupBy('Lamaran_Tahap_Id');

        $penjadwalanTahapIds = $tahapRows->pluck('Penjadwalan_Tahap_Id')
            ->merge($subTesRows->flatten(1)->pluck('Penjadwalan_Tahap_Id'))
            ->filter()->unique()->values()->all();

        $ujianByTahap = collect();
        if ($penjadwalanTahapIds) {
            $ujianByTahap = DB::table('N_WEB_CAREERS_Penjadwalan_Peserta as ps')
                ->join('N_WEB_CAREERS_Penjadwalan_Tahap as pt', 'pt.Id_Penjadwalan_Tahap', '=', 'ps.Penjadwalan_Tahap_Id')
                ->whereIn('ps.Penjadwalan_Tahap_Id', $penjadwalanTahapIds)
                ->where(function ($w) use ($realId, $userId) {
                    $w->where('ps.Lamaran_Id', $realId)->orWhere('ps.Users_Id', $userId);
                })
                // Jendela PESERTA menang atas jendela tahap: satu kandidat bisa
                // digeser sendiri (sakit, salah zona waktu) tanpa menggeser
                // seisi angkatan. Kosong → ikut jendela tahap seperti biasa.
                ->select('ps.*', 'pt.Nama_Ujian')
                ->selectRaw('COALESCE(ps.Waktu_Mulai, pt.Waktu_Mulai) as Jendela_Mulai')
                ->selectRaw('COALESCE(ps.Waktu_Akhir, pt.Waktu_Akhir) as Jendela_Akhir')
                ->get()
                ->keyBy('Penjadwalan_Tahap_Id');
        }

        $sekarang = now();

        // Perilaku & kalimat tiap tipe — dipakai portal untuk bicara sesuai
        // aktivitas yang sedang ditunggu, bukan "lamaranmu sedang diproses".
        $tipeTahap = self::masterTipeTahap();

        $bentukUjian = function ($u) use ($sekarang) {
            if (! $u) {
                return null;
            }
            $mulai = $u->Jendela_Mulai ? \Carbon\Carbon::parse($u->Jendela_Mulai) : null;
            $akhir = $u->Jendela_Akhir ? \Carbon\Carbon::parse($u->Jendela_Akhir) : null;
            $dalamJendela = $mulai && $akhir && $sekarang->betweenIncluded($mulai, $akhir);

            return [
                'terjadwal' => (bool) $u->Link_Ujian || (bool) $u->Short_Token,
                'token' => $u->Short_Token,
                'otp' => $u->Akses_OTP,
                'link' => $u->Link_Ujian,
                'namaUjian' => $u->Nama_Ujian,
                'waktuMulai' => optional($mulai)->toIso8601String(),
                'waktuSelesai' => optional($akhir)->toIso8601String(),
                'statusKirim' => $u->Status_Kirim,
                'statusPengerjaan' => $u->Status_Pengerjaan,
                'nilai' => $u->Total_Nilai,
                'kelulusan' => $u->Status_Kelulusan,
                'belumMulai' => $mulai ? $sekarang->lt($mulai) : false,
                'sudahLewat' => $akhir ? $sekarang->gt($akhir) : false,
                'bisaAkses' => $dalamJendela && (bool) $u->Link_Ujian && $u->Status_Pengerjaan !== 'selesai',
            ];
        };

        // Berkas hasil yang diunggah TIM per tahap (MCU, hasil wawancara, dst.).
        // Boleh dilihat kandidat — yang ditahan hanya nilainya, bukan dokumennya.
        $berkasTahap = DB::table('N_WEB_CAREERS_Lamaran_Tahap_Berkas')
            ->where('Lamaran_Id', $realId)
            // Hanya berkas putaran berjalan; yang sudah ikut pengulangan
            // punya Ulang_Id dan menjadi riwayat. Lihat UlangTahap.
            ->whereNull('Ulang_Id')
            ->orderBy('Id_Lamaran_Tahap_Berkas')
            ->get()
            ->groupBy('Lamaran_Tahap_Id');

        // Lokasi yang dipakai jadwal tatap muka — diambil sekali, bukan
        // satu kueri per aktivitas.
        $lokasiJadwal = DB::table('N_WEB_CAREERS_Master_Lokasi')
            ->whereIn('Id_Master_Lokasi', $subTesRows->flatten(1)->pluck('Jadwal_Lokasi_Id')->filter()->unique()->all() ?: [0])
            ->get()
            ->keyBy('Id_Master_Lokasi');

        $modePengumuman = self::masterModePengumuman();

        $tahap = $tahapRows->map(function ($t) use ($ujianByTahap, $subTesRows, $tipeTahap, $bentukUjian, $modePengumuman, $sekarang, $berkasTahap, $lokasiJadwal) {
            $u = $t->Penjadwalan_Tahap_Id ? $ujianByTahap->get($t->Penjadwalan_Tahap_Id) : null;
            $ujian = $bentukUjian($u);

            $info = $tipeTahap->get($t->Tipe_Tahap_Kode);

            // TIAP AKTIVITAS punya tipenya sendiri: satu tahap boleh berisi ujian
            // online, tes manual, dan wawancara sekaligus. Yang dikatakan kepada
            // kandidat mengikuti tipe AKTIVITAS, bukan tipe tahap — kalau tidak,
            // sesi wawancara ikut diberi kalimat "menunggu token ujian".
            // AKTIVITAS INTERNAL DISARING DI SINI.
            //
            // Background check, cek referensi, verifikasi ijazah: tim wajib
            // mencatatnya, kandidat tidak punya urusan apa pun dengannya. Baris
            // "Background Check — menunggu" di portal cuma memancing pertanyaan
            // tentang sesuatu yang tak bisa ia kerjakan, dan sekaligus
            // mengumumkan bahwa referensinya sedang dihubungi.
            //
            // Disaring di SERVER, bukan disembunyikan di layar — apa pun yang
            // masuk payload bisa dibaca lewat DevTools.
            //
            // JADWAL MENANG ATAS SETELAN — lihat JadwalPrivat::terlihat().
            // Aktivitas yang sudah bertanggal adalah janji temu yang undangannya
            // sudah dikirim; menyembunyikannya membuat kandidat membaca
            // "menunggu dijadwalkan" untuk pemeriksaan yang harus ia datangi
            // besok pagi.
            $terlihat = collect($subTesRows->get($t->Id_Lamaran_Tahap, []))
                ->filter(fn ($s) => JadwalPrivat::terlihat($s, $s->Tipe_Tahap_Kode ?: $t->Tipe_Tahap_Kode))
                ->values();

            // Giliran pengerjaan pada tahap BERURUTAN. Dihitung dari SELURUH
            // aktivitas (termasuk yang tersembunyi): background check yang belum
            // selesai tetap menahan wawancara sesudahnya, dan kandidat harus
            // melihat wawancaranya belum bisa dimulai — bukan tombol yang
            // ditekan lalu ditolak server.
            $penghalang = self::modeUrutanMengunci($t->Urutan_Aktivitas ?? null)
                ? collect($subTesRows->get($t->Id_Lamaran_Tahap, []))
                    ->sortBy('Urutan')
                    ->first(fn ($s) => ($s->Flag_Selesai ?? 'N') !== 'Y')
                : null;

            $tes = $terlihat->map(function ($s) use ($ujianByTahap, $bentukUjian, $tipeTahap, $t, $lokasiJadwal, $penghalang) {
                $su = $s->Penjadwalan_Tahap_Id ? $ujianByTahap->get($s->Penjadwalan_Tahap_Id) : null;
                $ti = $tipeTahap->get($s->Tipe_Tahap_Kode ?: $t->Tipe_Tahap_Kode);
                $online = ($ti->Perilaku_Kode ?? 'MANUAL') === 'CAT';

                return [
                    // Dipakai portal untuk menunggah berkas aktivitas ini.
                    'id' => Hashids::encode($s->Id_Lamaran_Tahap_Tes),
                    'urutan' => (int) $s->Urutan,
                    'label' => $s->Label,
                    'tipe' => $s->Tipe_Tahap_Kode ?: $t->Tipe_Tahap_Kode,
                    'tipeNama' => $ti->Nama ?? null,
                    'tipeIkon' => $ti->Ikon ?? null,
                    // Kalimat tunggu untuk aktivitas ini — dari master, bukan
                    // peta teks di dalam kode.
                    'pesan' => $ti->Pesan_Kandidat ?? null,
                    'eksternal' => $online,
                    'peran' => $s->Peran,
                    'status' => $s->Status,
                    'hasil' => $s->Hasil,
                    // NILAI DAN CATATAN PENILAI SENGAJA TIDAK DIKIRIM.
                    //
                    // Keduanya bahan penilaian INTERNAL. Catatan aktivitas dulu
                    // ikut terkirim dengan anggapan ia berisi keterangan untuk
                    // kandidat — padahal yang ditulis tim di sana adalah
                    // penilaian ("gugup, tidak direkomendasikan"), dan sejak
                    // catatannya bisa memuat lembar penilaian terpindai,
                    // membocorkannya berarti menyerahkan seluruh rapor internal.
                    //
                    // Yang memang untuk kandidat tetap ada dan tidak berubah:
                    // `jadwal.catatan` (pesan undangan) dan `mcu.catatan` (hasil
                    // kesehatan dirinya sendiri).
                    //
                    // Ditahan di SERVER, bukan disembunyikan di layar: apa pun
                    // yang masuk payload bisa dibaca lewat DevTools.
                    // Aturan unggahan untuk KANDIDAT pada aktivitas ini.
                    // Formatnya ikut dikirim supaya portal menampilkan aturan
                    // yang benar-benar berlaku, bukan aturan umum yang ditebak.
                    //
                    // Sumbernya DUA: setelan alur, atau MODE jadwalnya (MCU
                    // mandiri — kandidat yang memegang hasilnya). Bentuknya satu:
                    // wajib, format, maksMb, petunjuk, `terkirim` (sudah
                    // dinyatakan lengkap — pembeda "masih mengunggah" dari
                    // "menunggu dinilai"), dan `tertutup` (batasnya lewat; server
                    // menolak unggah, hapus, maupun kirim). Lihat
                    // UndanganJadwal::aturanUnggah().
                    'unggah' => UndanganJadwal::aturanUnggah($s),
                    // Jadwal tatap muka: kapan, di mana / lewat tautan apa.
                    // Inilah yang dicari kandidat begitu diundang wawancara.
                    // Status MCU boleh dilihat kandidat — itu menyangkut dirinya
                    // sendiri. Penyedia & tanggal ikut supaya ia tahu hasil mana
                    // yang dimaksud bila perlu menanyakannya ke klinik.
                    'mcu' => $s->Mcu_Status ? [
                        'status' => $s->Mcu_Status,
                        // Label dari MASTER — status keempat (Temporary Unfit)
                        // yang baru ditambahkan langsung ikut terbaca, tanpa
                        // jatuh ke `default` dan memuntahkan kode mentah
                        // "TEMPORARY_UNFIT" ke layar kandidat.
                        'label' => self::masterMcuStatus()->get($s->Mcu_Status)->Nama ?? $s->Mcu_Status,
                        'penyedia' => $s->Mcu_Penyedia,
                        'tanggal' => (string) ($s->Mcu_Tanggal ?: ''),
                        'catatan' => $s->Mcu_Catatan,
                    ] : null,
                    // TIPE INI DILAKSANAKAN PADA WAKTU & TEMPAT TERTENTU.
                    //
                    // Diambil dari Master_Tipe_Tahap.Flag_Jadwal — bukan daftar kode
                    // tipe yang ditulis di layar. Tes offline, wawancara, MCU, dan
                    // phone screening bernilai Y; formulir dan tes daring tidak.
                    //
                    // Portal memakainya untuk menahan kotak unggah sampai jadwalnya
                    // terbit: berkas jawaban tes offline baru masuk akal diminta
                    // sesudah kandidat tahu kapan dan di mana tesnya dikerjakan.
                    'perluJadwal' => ($ti->Flag_Jadwal ?? 'T') === 'Y',
                    // TIPE INI MENEMPELKAN FORMULIR — Master_Tipe_Tahap.Flag_Formulir.
                    //
                    // Yang ditunggu aktivitas ini adalah ISIAN KANDIDAT, bukan
                    // jadwal dari tim. Tanpa penanda ini portal tidak punya cara
                    // membedakan "Formulir" dari "Background Check": keduanya
                    // MANUAL dan sama-sama tak berjadwal, jadi keduanya dilencanai
                    // "Menunggu jadwal" — janji yang tidak akan pernah ditepati
                    // untuk yang satu, dan salah alamat untuk yang lain.
                    'berformulir' => ($ti->Flag_Formulir ?? 'T') === 'Y',
                    'jadwal' => $s->Jadwal_Mulai ? [
                        'mode' => $s->Jadwal_Mode,
                        'daring' => strtoupper((string) $s->Jadwal_Mode) === 'DARING',
                        'mulai' => (string) $s->Jadwal_Mulai,
                        'selesai' => (string) ($s->Jadwal_Selesai ?: ''),
                        'link' => $s->Jadwal_Link,
                        // Nomor yang dijanjikan akan dihubungi — kandidat harus
                        // bisa memastikan nomornya benar sebelum harinya tiba.
                        'kontak' => $s->Jadwal_Kontak ?? null,
                        // Detail yang diketik rekruter ("Gedung B lantai 3").
                        'lokasi' => $s->Jadwal_Lokasi,
                        // Tempatnya sendiri, LENGKAP dengan peta. Kandidat butuh
                        // tahu di mana persisnya — alamat teks menuntut dia
                        // menyalinnya sendiri ke aplikasi peta.
                        //
                        // Berlaku juga untuk tempat yang DIKETIK rekruter (RS
                        // yang belum terdaftar): petanya disusun dari nama +
                        // alamatnya, jadi kandidat tidak menerima undangan yang
                        // lebih miskin hanya karena tempatnya belum sempat
                        // didaftarkan.
                        'tempat' => self::tempatJadwal(
                            $s,
                            $s->Jadwal_Lokasi_Id ? $lokasiJadwal->get($s->Jadwal_Lokasi_Id) : null,
                        ),
                        'catatan' => $s->Jadwal_Catatan,
                        // Rentang (MCU vendor / mandiri), tempat vendor, instruksi
                        // berformat, aturan biaya, dan SURAT PENGANTAR — dibuka
                        // lewat rute portal yang memeriksa kepemilikannya. Lihat
                        // jadwalTambahan().
                    ] + self::jadwalTambahan($s, $ti, '/kandidat/lamaran/tes/'.Hashids::encode($s->Id_Lamaran_Tahap_Tes).'/surat') : null,
                    // KONFIRMASI KEHADIRAN — status jawabannya, batasnya, dan
                    // tautan bertanda tangan ke halaman konfirmasi (sama dengan
                    // tombol di surel undangan).
                    'konfirmasi' => KonfirmasiJadwal::untukKandidat($s),
                    // KETENTUAN BIAYA aktivitas ini beserta kesimpulannya
                    // (diganti / tidak) begitu hasilnya keluar. Turunan dari
                    // hasil yang sudah tersimpan — bukan isian siapa pun.
                    // Admin yang mematikan informasi biaya untuk jadwal ini
                    // (mis. sudah dijelaskan di instruksi) mematikan
                    // kesimpulannya juga: kandidat tidak membaca soal biaya
                    // yang tak pernah disampaikan kepadanya.
                    'biaya' => UndanganJadwal::tampilBiaya($s) ? BiayaAktivitas::status($s, $ti) : null,
                    'selesai' => $s->Flag_Selesai === 'Y',
                    'butuhJadwal' => $online && $s->Flag_Selesai !== 'Y' && ! $su,
                    // Tahap BERURUTAN: aktivitas ini belum gilirannya. Kandidat
                    // perlu tahu urutannya — kalau tidak, ia melihat tes yang
                    // tak bisa dimulai tanpa satu pun keterangan kenapa.
                    //
                    // Nama penghalangnya TIDAK disebut bila aktivitas itu
                    // internal: menyebut "menunggu Background Check" justru
                    // membocorkan langkah yang sengaja disembunyikan.
                    'terkunci' => $penghalang
                        && $s->Flag_Selesai !== 'Y'
                        && $penghalang->Id_Lamaran_Tahap_Tes !== $s->Id_Lamaran_Tahap_Tes,
                    'menunggu' => $penghalang
                        && $s->Flag_Selesai !== 'Y'
                        && $penghalang->Id_Lamaran_Tahap_Tes !== $s->Id_Lamaran_Tahap_Tes
                        && JadwalPrivat::terlihat($penghalang, $penghalang->Tipe_Tahap_Kode ?: $t->Tipe_Tahap_Kode)
                            ? $penghalang->Label
                            : null,
                    'ujian' => $bentukUjian($su),
                ];
            })->values();

            // Tahap menunggu dijadwalkan bila ada aktivitas ujian online yang
            // belum punya sesi. Aktivitas manual tidak ikut dihitung.
            $butuhJadwal = ! $ujian && $tes->contains(fn ($x) => $x['butuhJadwal']);

            // BOLEHKAH hasil tahap ini diperlihatkan kepada kandidat?
            //
            // Keputusan admin dan pengumuman kepada kandidat adalah dua hal
            // berbeda: admin bisa saja sudah mengetuk palu sementara hasilnya
            // baru boleh dibuka serentak nanti. Aturannya diambil dari Master
            // Mode Pengumuman, jadi mengubah kebijakan cukup lewat data.
            $mp = $modePengumuman->get(strtoupper((string) ($t->Mode_Pengumuman ?: 'OTOMATIS')));
            $diumumkan = $t->Waktu_Diumumkan ? \Carbon\Carbon::parse($t->Waktu_Diumumkan) : null;

            $terbit = self::terbitKeKandidat($t, $sekarang);

            $hasilTampil = (bool) $t->Hasil && $terbit;

            return [
                'id' => Hashids::encode($t->Id_Lamaran_Tahap),
                'urutan' => (int) $t->Urutan,
                'label' => $t->Label,
                'tipe' => $t->Tipe_Tahap_Kode,
                'tipeNama' => $info->Nama ?? null,
                'tipeIkon' => $info->Ikon ?? null,
                // 'CAT' = ujian online berjadwal; 'MANUAL' = diatur/dinilai tim.
                'perilaku' => $info->Perilaku_Kode ?? 'MANUAL',
                // Kalimat tunggu bawaan tahap (dipakai bila aktivitasnya tunggal).
                'pesan' => $info->Pesan_Kandidat ?? null,
                'provider' => $t->Provider,
                'formulir' => $t->Formulir_Kode,
                'status' => $t->Status,
                // Ditahan di SERVER, bukan disembunyikan di CSS: hasil yang belum
                // boleh diumumkan tidak dikirim sama sekali ke browser kandidat.
                'hasil' => $hasilTampil ? $t->Hasil : null,
                'skor' => $hasilTampil ? $t->Skor : null,
                // CATATAN INTERNAL TIDAK LAGI DIKIRIM. `Catatan` memuat catatan
                // keputusan TIM; dulu ikut di muatan ini walau tidak ditampilkan
                // — dan apa pun yang masuk muatan bisa dibaca lewat DevTools.
                // Yang memang untuk kandidat adalah catatan EKSTERNAL, dan itu
                // pun baru dikirim setelah hasil tahapnya boleh diumumkan.
                'catatanEksternal' => $hasilTampil ? ($t->Catatan_Eksternal_Html ?? null) : null,
                // Kapan catatan itu ditulis: "link meet untuk besok" baru
                // bermakna bila kandidat tahu besok dari hari apa.
                'catatanAt' => $hasilTampil && ($t->Catatan_Eksternal_Html ?? null) ? (string) $t->Diputus_At : null,
                // Batas pengisian formulir tahap ini (bila berlaku) — kartu
                // tahap aktif menampilkan hitung mundurnya.
                'batas' => BatasIsi::status($t),
                'waktuMulai' => optional($t->Waktu_Mulai)->__toString(),
                'waktuSelesai' => optional($t->Waktu_Selesai)->__toString(),
                'sudahIsi' => (bool) $t->Formulir_Pengisian_Id,
                'butuhJadwal' => $butuhJadwal,
                // Setelah tes dikerjakan, yang ditunggu kandidat berbeda-beda:
                //   otomatis  → sistem langsung memutuskan, tak ada jeda manusia;
                //   manual    → hasil masuk lalu ditinjau tim sebelum diumumkan.
                // Tanpa dua penanda ini, kandidat yang sudah selesai tes hanya
                // melihat layar yang sama dengan yang belum — dan mengira bisa
                // (atau harus) mengulang tesnya.
                'otomatis' => strtoupper((string) ($t->Keputusan_Mode ?? 'MANUAL')) === 'SYSTEM',
                'siapDiputus' => ($t->Siap_Diputus ?? 'N') === 'Y',
                // PENAWARAN: tahap ini menuntut JAWABAN kandidat, bukan sekadar
                // menunggu tim. Tanpa penanda ini portal hanya berkata "tim akan
                // menghubungimu" — dan kandidat yang sudah memegang penawaran
                // tidak punya cara menyatakan menerima atau mundur.
                'penawaran' => ($info->Flag_Penawaran ?? 'T') === 'Y',
                // `penawaranDiajukan` dan `tanggapan` TIDAK LAGI DIKIRIM ke
                // portal: keduanya hanya melayani tombol "Terima / Mundur" yang
                // sudah dicabut. Apa pun yang masuk muatan bisa dibaca lewat
                // DevTools, jadi data yang tak lagi dipakai layar sebaiknya
                // tidak ikut keluar sama sekali.
                //
                // Worklist admin tetap menerima keduanya lewat muatannya
                // sendiri — jawaban lama yang pernah tercatat tidak hilang.
                // Keputusan tahap — hanya diberikan bila memang sudah boleh
                // diumumkan. Bila belum, `hasil` di atas TIDAK dipakai portal.
                'hasilTampil' => $hasilTampil,
                // Berkas hasil tahap. Hanya ikut bila hasilnya sudah boleh
                // diumumkan — dokumen penilaian tidak boleh mendahului keputusan.
                'berkas' => $hasilTampil
                    ? collect($berkasTahap->get($t->Id_Lamaran_Tahap, []))->map(function ($b) {
                        $ext = strtolower($b->Ext ?: pathinfo($b->Nama_File, PATHINFO_EXTENSION));

                        return [
                            'nama' => $b->Nama_File,
                            'ext' => $ext,
                            'ukuran' => (int) $b->Ukuran,
                            'isPdf' => $ext === 'pdf' || $b->Mime === 'application/pdf',
                            'isImage' => in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true),
                            'url' => route('career.portal.tahap.berkas', ['id' => Hashids::encode($b->Id_Lamaran_Tahap_Berkas)]),
                        ];
                    })->values()
                    : [],
                'diputusAt' => optional($t->Diputus_At)->__toString(),
                'menungguPengumuman' => (bool) $t->Hasil && ! $terbit,
                'pengumuman' => $mp ? [
                    'kode' => $mp->Kode,
                    'nama' => $mp->Nama,
                    'label' => $mp->Label,
                    'ikon' => $mp->Ikon,
                    'warna' => $mp->Warna,
                    'tanggal' => optional($diumumkan)->toIso8601String(),
                ] : null,
                'ujian' => $ujian,
                'tes' => $tes,
                // Tahap ini memuat aktivitas yang jadwalnya INTERNAL (negosiasi).
                //
                // Dikirim sebagai boolean telanjang — tanpa nama, tanpa jumlah,
                // tanpa jadwal. Layar hanya perlu tahu bahwa ada yang sedang
                // ditangani tim supaya kalimatnya tidak berbunyi "belum ada
                // jadwal" untuk sesuatu yang memang tidak akan pernah
                // dijadwalkan untuk kandidat. Apa yang dikerjakan tim tetap
                // tidak ikut keluar.
                'adaJadwalInternal' => collect($subTesRows->get($t->Id_Lamaran_Tahap, []))
                    ->contains(fn ($s) => JadwalPrivat::untuk($s->Tipe_Tahap_Kode ?? null)),
            ];
        })->values();

        // Tahap aktif yang menuntut formulir & belum diisi -> kandidat kerjakan.
        $aktif = DB::table('N_WEB_CAREERS_Lamaran_Tahap as t')
            ->leftJoin('N_WEB_CAREERS_Master_Formulir as f', 'f.Kode', '=', 't.Formulir_Kode')
            ->where('t.Lamaran_Id', $realId)
            ->where('t.Status', 'BERJALAN')
            ->whereNotNull('t.Formulir_Kode')
            ->whereNull('t.Formulir_Pengisian_Id')
            ->orderBy('t.Urutan')
            ->select('t.Id_Lamaran_Tahap', 't.Label', 't.Formulir_Kode', 't.Formulir_Komponen', 't.Formulir_Versi',
                'f.Nama as FormulirNama', 'f.Komponen_Kode')
            ->first();

        // Schema dikunci ke versi yang dibekukan saat tahap dibuat (bila ada),
        // supaya kandidat yang sedang mengisi tidak tiba-tiba mendapat schema
        // dinamis versi baru gara-gara Master Formulir disunting di tengah jalan.
        $schemaAktif = $aktif
            ? \App\Support\Career\FormulirSchema::byKodeDanVersi(
                $aktif->Formulir_Kode,
                $aktif->Formulir_Versi !== null ? (int) $aktif->Formulir_Versi : null
            )
            : null;
        $tugas = $aktif ? [
            'tahapId' => Hashids::encode($aktif->Id_Lamaran_Tahap),
            'label' => $aktif->Label,
            'formulir' => $aktif->Formulir_Kode,
            'formulirNama' => $aktif->FormulirNama,
            // KOMPONEN YANG DIBEKUKAN, master hanya cadangan untuk lamaran lama.
            //
            // Ini yang menentukan pertanyaan mana yang muncul di layar kandidat.
            // Dulu selalu dibaca hidup-hidup dari master, sehingga admin yang
            // mengarahkan Master Formulir ke komponen versi baru langsung
            // mengubah formulir orang yang sudah berjalan berminggu-minggu —
            // termasuk yang tinggal menekan kirim.
            'komponen' => $aktif->Formulir_Komponen ?: ($schemaAktif['komponen'] ?? $aktif->Komponen_Kode),
            'schema' => $schemaAktif['schema'] ?? null,
            'versiId' => $schemaAktif['versiId'] ?? null,
            'versi' => $schemaAktif['versi'] ?? null,
            // Batas pengisian formulir ini — terkunci = formulir diganti pesan.
            'batas' => BatasIsi::statusTahap((int) $aktif->Id_Lamaran_Tahap),
        ] : null;

        // KONTEKS FORMULIR — opsi yang memang PENDEK dan khusus lamaran ini.
        //
        // Daftar kampus TIDAK lagi dikirim dari sini. Master Kampus berisi
        // 328.998 baris hasil impor Dapodik/PDDIKTI (±7,8 MB JSON) — mengirimnya
        // di setiap pembukaan halaman membuat halaman berat tanpa guna, karena
        // kandidat hanya memilih satu. Field bertipe `referensi` sekarang
        // mencarinya sendiri ke /api/v1/referensi/{sumber} sambil mengetik.
        //
        // Pembatasan kampus mitra (bila perlu) tetap lewat SYARAT auto-gugur
        // (operator ADA_DI) di Program Kegiatan, bukan lewat daftar opsi.
        //
        // Dicetak sebagai objek, bukan array: array PHP kosong menjadi `[]` di
        // JSON, sedangkan prop `konteks` di sisi Vue bertipe Object.
        $konteks = (object) [];

        // FORMULIR & BERKAS yang SUDAH kandidat kirim (panel "Formulir & Berkas Saya").
        $formulir = $this->formulirTerkirim($realId, '/kandidat/lamaran/berkas/file/');

        // URL detail lowongan/program publik (tombol "Lihat Detail Lowongan").
        // Kartu landing ber-id 'PB-{PembukaanKode}[-{Program_Posisi_Id}]'.
        $lowonganUrl = null;
        if ($lamaran->PembukaanKode) {
            $lowonganUrl = $lamaran->ProgramKategori === 'MT'
                ? '/karir/landing-page/mt/PB-'.$lamaran->PembukaanKode
                : '/karir/landing-page/lowongan/PB-'.$lamaran->PembukaanKode.'-'.$lamaran->Program_Posisi_Id;
        }

        // KARTU lowongan (info kaya: tanggung jawab, syarat, skill, benefit, tipe
        // kerja, tempat kerja, pengalaman) — sumber sama dengan landing/MPP.
        $kartu = null;
        try {
            $landing = app(\App\Http\Controllers\Career\CareerLandingController::class);
            if ($lamaran->ProgramKategori === 'MT') {
                $pbHash = $lamaran->Pembukaan_Id ? Hashids::encode($lamaran->Pembukaan_Id) : null;
                $kartu = collect($landing->dbMtCards())->firstWhere('pembukaanId', $pbHash);
            } else {
                $pbHash = $lamaran->Pembukaan_Id ? Hashids::encode($lamaran->Pembukaan_Id) : null;
                $posHash = $lamaran->Program_Posisi_Id ? Hashids::encode($lamaran->Program_Posisi_Id) : null;
                $kartu = collect($landing->dbLowonganCards())->first(fn ($c) => ($c['pembukaanId'] ?? null) === $pbHash && ($c['posisiId'] ?? null) === $posHash);
            }
        } catch (\Throwable $e) {
            $kartu = null;
        }

        $akun = DB::table('N_WEB_CAREERS_Users')
            ->where('Id_Users', $userId)
            ->first(['Nama', 'Email', 'No_Hp', 'NIK']);

        return Inertia::render('Career/portal/LamaranDetail', CareerShell::props('/kandidat/portal', 'Detail Lamaran', [
            'lamaran' => [
                'id' => $id,
                'kode' => $lamaran->Kode,
                'program' => $lamaran->ProgramNama,
                'kategori' => $lamaran->ProgramKategori,
                'penyelenggara' => $lamaran->Penyelenggara,
                'posisi' => $lamaran->Posisi,
                'lokasi' => $lamaran->Lokasi,
                'departemen' => $lamaran->Departemen,
                'batch' => $lamaran->BatchNama,
                'status' => $lamaran->Status,
                // Kata & nada yang dipakai layar — dari master, bukan peta
                // literal di dalam Vue yang selalu ketinggalan satu hasil.
                'statusLabel' => self::statusKandidat($lamaran->Status)['label'],
                'statusNada' => self::statusKandidat($lamaran->Status)['nada'],
                'urutanTahap' => (int) $lamaran->Urutan_Tahap,
                'totalTahap' => (int) $lamaran->Total_Tahap,
                'hasilAkhir' => $lamaran->Hasil_Akhir,
                'gugurDi' => $lamaran->Gugur_Di_Tahap,
                'alasanGugur' => $lamaran->Alasan_Gugur,
                'waktuLamar' => optional($lamaran->Waktu_Lamar)->__toString(),
            ],
            'tahap' => $tahap,
            'tugas' => $tugas,
            'konteks' => $konteks,
            'formulir' => $formulir,
            'lowonganUrl' => $lowonganUrl,
            'kartu' => $kartu,
            // Profil untuk prefill field bertipe "terisi otomatis".
            // Prefill formulir diambil dari TABEL akun, bukan dari session.
            // Session hanya berisi apa yang sempat ditaruh saat login — `hp`
            // tidak pernah ada di sana, sehingga "No. WhatsApp Terdaftar" di
            // formulir selalu tampil kosong padahal datanya ada di akun.
            //
            // `kampus` datang dari jawaban formulir PENDAFTARAN lamaran ini —
            // kandidat sudah memilihnya dari Master Kampus saat melamar, jadi
            // formulir tahap berikutnya cukup menampilkannya kembali (terkunci)
            // alih-alih menanyakan ulang dan berisiko dapat dua jawaban berbeda.
            // Kampus & tahun lulus DITARIK DARI JAWABAN FORMULIR PENDAFTARAN
            // lamaran ini — bukan dari tabel akun. Keduanya sudah dijawab
            // kandidat saat melamar; menanyakannya lagi di formulir tahap
            // berikutnya membuka peluang dua jawaban berbeda untuk orang
            // yang sama, dan tim tidak punya cara tahu mana yang benar.
            //
            // Disaring lewat KatalogPrefill: kunci yang ditawarkan ke admin dan
            // kunci yang dikirim ke sini berasal dari satu daftar.
            'profil' => KatalogPrefill::saring(KatalogPrefill::TAHAP, [
                'nama' => $akun->Nama ?? session('career_auth.nama'),
                'email' => $akun->Email ?? session('career_auth.email'),
                'hp' => $akun->No_Hp ?? session('career_auth.hp'),
                'nik' => $akun->NIK ?? null,
                'posisi' => $lamaran->Posisi ?? null,
                ...array_intersect_key(
                    LamaranService::dataKandidatEmail($realId),
                    array_flip(['kampus', 'tahunLulus', 'tglLahir', 'jkel', 'jurusan', 'jenjang', 'ipk', 'statusStudi', 'semester']),
                ),
            ]),
        ]));
    }

    /**
     * Bentuk daftar formulir yang SUDAH dikirim untuk sebuah lamaran (jawaban +
     * berkas). $urlBerkasPrefix menentukan basis URL berkas (admin vs kandidat).
     */
    private function formulirTerkirim(int $lamaranId, string $urlBerkasPrefix): array
    {
        $pengisian = DB::table('N_WEB_CAREERS_Formulir_Pengisian as fp')
            ->leftJoin('N_WEB_CAREERS_Lamaran_Tahap as t', 't.Id_Lamaran_Tahap', '=', 'fp.Lamaran_Tahap_Id')
            ->where('fp.Lamaran_Id', $lamaranId)
            ->orderBy('t.Urutan')
            ->select('fp.*', 't.Urutan as TahapUrutan', 't.Label as TahapLabel')
            ->get();

        // SKEMA yang dibekukan saat formulir dikirim — bukan yang published
        // sekarang. Inilah satu-satunya sumber yang tahu: label asli tiap
        // pertanyaan, tipenya, kelompoknya, dan URUTANNYA.
        //
        // Tanpa ini layar hanya punya Komponen_Kode, dan itu cuma terisi untuk
        // formulir bawaan lama. Untuk formulir yang disusun lewat Master
        // Formulir — yang kini menjadi mayoritas — kodenya NULL, sehingga
        // labelnya ditebak dari nama kunci: `v_nama` terbaca "V Nama",
        // `v_email` terbaca "V Email". Bahasa mesin yang bocor ke mata orang,
        // dan urutan bacanya ikut acak karena mengikuti urutan kunci di JSON,
        // bukan urutan pertanyaan di formulirnya.
        $adaSnapshot = FormulirSchema::punyaKolomPengisianSnapshot();

        $berkasPer = DB::table('N_WEB_CAREERS_Formulir_Berkas')
            ->whereIn('Formulir_Pengisian_Id', $pengisian->pluck('Id_Formulir_Pengisian')->all() ?: [0])
            ->orderBy('Urutan')
            ->get()
            ->groupBy('Formulir_Pengisian_Id');

        return $pengisian->values()->map(function ($fp, $i) use ($berkasPer, $urlBerkasPrefix, $adaSnapshot) {
            $jawaban = json_decode($fp->Jawaban_Json ?: '{}', true) ?: [];
            $skema = $adaSnapshot && ! empty($fp->Schema_Snapshot_Json)
                ? (json_decode($fp->Schema_Snapshot_Json, true) ?: null)
                : null;

            $berkas = collect($berkasPer->get($fp->Id_Formulir_Pengisian, []))->map(function ($b) use ($urlBerkasPrefix) {
                $ext = strtolower($b->Ekstensi ?: pathinfo($b->Nama_Asli, PATHINFO_EXTENSION));

                return [
                    'field' => $b->Field_Key,
                    // Posisi baris dibawa apa adanya supaya pencocokan berkas ke
                    // kartu baris tidak perlu menebak dari nama berkasnya.
                    'bagian' => $b->Bagian_Key,
                    'baris' => $b->Baris_Index !== null ? (int) $b->Baris_Index : null,
                    'nomor' => $b->Baris_Index !== null ? ((int) $b->Baris_Index) + 1 : null,
                    'nama' => $b->Nama_Asli,
                    'url' => url($urlBerkasPrefix.Hashids::encode($b->Id_Formulir_Berkas)),
                    'ext' => $ext,
                    'isPdf' => $ext === 'pdf' || $b->Mime === 'application/pdf',
                    'isImage' => in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true),
                    'ukuran' => (int) $b->Ukuran_Byte,
                    'status' => $b->Status_Verifikasi,
                    // Waktu unggah — dasar urutan "terbaru dulu" di panel berkas.
                    // Urutan kolom `Urutan` menata lembaran MENURUT FORMULIRNYA;
                    // yang dicari peninjau justru sebaliknya: apa yang paling
                    // baru masuk, tak peduli dari formulir mana.
                    'waktu' => (string) ($b->Waktu_Unggah ?: $b->Created_At ?: ''),
                ];
            })->values();

            return [
                'no' => $i + 1,
                'urutan' => (int) ($fp->TahapUrutan ?? 0),
                'label' => $fp->TahapLabel ?: ($fp->Sumber === 'PENDAFTARAN' ? 'Formulir Pendaftaran' : 'Formulir Tahap'),
                'sumber' => $fp->Sumber,
                // Kode komponen skema (FORMULIR_1/2/…) — dipakai layar untuk
                // mencari LABEL ASLI tiap isian. Tanpa ini label cuma bisa
                // ditebak dari nama kuncinya, dan `v_nama`/`v_wa` terbaca
                // "V Nama"/"V Wa": bahasa mesin, bukan bahasa manusia.
                'komponen' => $fp->Komponen_Kode,
                // Skema beku (langkah → bagian → field). Dipakai layar untuk
                // label, tipe, kelompok, DAN urutan baca. Lihat catatan di atas.
                'skema' => $skema,
                // String mentah dari SQL Server: optional()->__toString()
                // di atasnya menghasilkan NULL, sehingga formulir yang jelas
                // sudah dikirim tetap dianggap belum.
                'waktuKirim' => (string) ($fp->Waktu_Kirim ?: ''),
                // Jawaban yang ternyata BERKAS dibawa berikut url/tipe berkasnya.
                // Tanpa ini nama berkas hanya tampil sebagai teks mati di daftar
                // isian, padahal dokumennya ada dan seharusnya bisa dibuka.
                'jawaban' => collect($jawaban)->map(function ($v, $k) use ($berkas) {
                    $b = $berkas->firstWhere('field', $k);
                    // Satu aturan untuk seluruh bentuk jawaban — termasuk field
                    // berulang yang dulu menjatuhkan halaman ini. Lihat nilaiIsian().
                    // $k di sini ADALAH kunci bagian berulangnya (mis.
                    // "riwayat_sertifikasi") — itulah yang dicocokkan ke
                    // Bagian_Key di tabel berkas.
                    $isi = self::nilaiIsian($v, 0, self::pencariBerkas($berkas, $k));

                    return [
                        'key' => $k,
                        'label' => ucwords(str_replace(['_', '-'], ' ', $k)),
                        'nilai' => $isi['nilai'],
                        'baris' => $isi['baris'],
                        // Butir daftar dibawa apa adanya — lihat nilaiIsian().
                        'daftar' => $isi['daftar'],
                        'berkas' => $b ? [
                            'field' => $b['field'],
                            'nama' => $b['nama'],
                            'url' => $b['url'],
                            'ext' => $b['ext'],
                            'isImage' => $b['isImage'],
                            'isPdf' => $b['isPdf'],
                        ] : null,
                    ];
                })->values(),
                'berkas' => $berkas,
            ];
        })->all();
    }

    /**
     * Satu jawaban formulir → bentuk yang bisa DIBACA ORANG.
     *
     * ══ KENAPA ADA ══
     *
     * Sebelumnya jawaban dirapikan langsung di tempat, dengan satu baris yang
     * sama persis disalin di DUA layar:
     *
     *     is_array($v) ? implode(', ', $v) : (is_bool($v) ? … : $v)
     *
     * Baris itu benar hanya selama isinya DATAR. Begitu formulir memakai field
     * BERULANG — riwayat kerja, organisasi, sertifikasi, daftar kenalan — tiap
     * barisnya adalah OBJEK berisi beberapa sub-isian, dan implode() tidak bisa
     * memampatkan objek jadi teks: PHP melempar "Array to string conversion"
     * dan SELURUH halaman detail lamaran mati. Kandidat tidak bisa membuka
     * lamarannya sendiri hanya karena ia mengisi riwayat kerjanya.
     *
     * Bahkan seandainya tidak melempar, hasilnya "Array, Array" — sama tidak
     * bergunanya.
     *
     * SATU TEMPAT, bukan dua salinan: layar kandidat dan worklist admin
     * menampilkan jawaban yang sama, dan dua salinan aturan pasti berselisih —
     * yang satu diperbaiki, yang lain tertinggal, dan selisihnya baru ketahuan
     * saat ada yang membandingkan dua layar itu berdampingan.
     *
     * ══ BERKAS DI DALAM BARIS BERULANG ══
     *
     * Satu sub-isian bisa berupa UNGGAHAN, bukan teks — `sert_file` pada
     * riwayat sertifikasi yang paling sering. Yang tersimpan di Jawaban_Json
     * cuma NAMA berkasnya ("LMR-9XWXPWWC-menyala-bosku-18.pdf"), sedangkan
     * berkas sungguhannya hidup di N_WEB_CAREERS_Formulir_Berkas dengan
     * Field_Key sub-isian itu.
     *
     * Tanpa `$cariBerkas`, nama itu tampil sebagai teks mati: peninjau melihat
     * ada sertifikat tapi tidak bisa membukanya, dan berkasnya terlempar ke
     * daftar terpisah yang berjudul nama file — persis masalah yang sudah
     * diperbaiki untuk KTP/CV di tingkat atas, tapi masih tersisa satu tingkat
     * di dalam.
     *
     * @param  ?\Closure  $cariBerkas  fn(string $kunci): ?array — berkas untuk
     *                                 satu sub-isian, atau null bila sub-isian itu memang teks biasa.
     * @return array{nilai: string, baris: array} `baris` hanya terisi untuk
     *                                            field berulang, supaya layar bisa menampilkannya sebagai daftar
     *                                            alih-alih satu paragraf panjang.
     */
    /**
     * Pencari berkas per kunci isian, dipakai nilaiIsian() untuk sub-isian
     * berulang.
     *
     * Hanya bentuk RINGKAS yang dikembalikan — yang benar-benar dipakai layar
     * untuk menggambar tombol "Lihat Berkas". Ukuran & status verifikasi tidak
     * ikut: keduanya milik daftar berkas utuh, dan mengulangnya di tiap baris
     * riwayat cuma menggandakan muatan tanpa ada yang membacanya.
     *
     * ── SATU SUB-ISIAN BISA MEMUAT BANYAK BERKAS ────────────────────────────
     *
     * Sertifikat kerap diunggah berlembar: satu baris "Ahli K3 Umum" membawa
     * sertifikat, lampiran nilai, dan surat keterangan sekaligus. Bentuk lama
     * mengembalikan SATU berkas saja, jadi lembar kedua dan seterusnya masuk ke
     * basis data lalu tidak pernah muncul di layar mana pun — tak ada galat,
     * hanya dokumen yang hilang diam-diam. Argumen keempat (`$banyak`)
     * mengembalikan SELURUHNYA sebagai daftar.
     */
    private static function pencariBerkas(\Illuminate\Support\Collection $berkas, ?string $bagian = null): \Closure
    {
        // Berkas yang SUDAH diambil baris sebelumnya, dikunci per URL (unik per
        // baris tabel berkas). Inilah yang menjamin dua baris riwayat tidak
        // pernah menunjuk dokumen yang sama.
        $dipakai = [];

        return static function (string $kunci, string $nilai = '', ?int $baris = null, bool $banyak = false) use ($berkas, $bagian, &$dipakai): array|null {
            if ($banyak) {
                return self::berkasSeBaris($berkas, $bagian, $kunci, $nilai, $baris, $dipakai);
            }

            $b = null;

            // ── 0. COCOKKAN POSISI BARISNYA ─────────────────────────────────
            //
            // Yang paling tegas, dan satu-satunya yang tahan terhadap dua baris
            // yang mengunggah berkas BERNAMA SAMA. Hanya berlaku untuk baris
            // yang tercatat sejak kolom Bagian_Key/Baris_Index ada; berkas lama
            // bernilai null di keduanya dan jatuh ke pencocokan nama di bawah.
            if ($bagian !== null && $baris !== null) {
                $b = $berkas->first(
                    fn ($x) => ($x['bagian'] ?? null) === $bagian
                        && ($x['baris'] ?? null) === $baris
                        && $x['field'] === $kunci
                        && empty($dipakai[$x['url']])
                );
            }

            // ── 1. COCOKKAN NAMA BERKASNYA ──────────────────────────────────
            //
            // Untuk baris berulang yang tercatat SEBELUM kolom posisi ada,
            // inilah satu-satunya pencocokan yang benar. Jawaban_Json menyimpan
            // NAMA berkas tiap baris ("sertifikat-haccp.pdf"), dan nama itu yang
            // membedakan baris ke-2 dari ke-1 — sub-kuncinya sendiri identik di
            // semua baris.
            if (! $b && $nilai !== '') {
                $b = $berkas->first(
                    fn ($x) => $x['field'] === $kunci && $x['nama'] === $nilai && empty($dipakai[$x['url']])
                );
            }

            // ── TIDAK ADA CADANGAN, DAN ITU DISENGAJA ───────────────────────
            //
            // Baris yang menyebut nama berkas tapi berkasnya tidak ditemukan
            // dibiarkan KOSONG, bukan dicarikan pengganti. Kasus nyatanya:
            //
            //   baris 1 menyebut  LMR-QTCGFST2-frans-bachtiar-1.pdf  (hilang)
            //   baris 2 menyebut  spesifikasi512mb-...pdf            (ada)
            //
            // Percobaan pertama perbaikan ini memakai cadangan "ambil berkas
            // pertama yang belum terpakai". Akibatnya baris 1 gagal mencocokkan
            // nama lalu menyambar satu-satunya berkas tersisa — milik baris 2 —
            // dan saat giliran baris 2 tiba berkasnya sudah habis. Dokumen yang
            // benar menempel pada sertifikat yang salah, DAN sertifikat yang
            // benar kehilangan dokumennya: dua kekeliruan dari satu tebakan.
            //
            // Kosong membuat peninjau bertanya. Dokumen yang salah tidak akan
            // pernah dipertanyakan siapa pun.
            //
            // Aman untuk data lama: seluruh pengisian yang ada (diperiksa 6 dari
            // 6 pada 12 Agustus 2026) menyimpan nama yang cocok persis dengan
            // Nama_Asli, sebab keduanya ditulis oleh proses unggah yang sama.

            if (! $b) {
                return null;
            }

            $dipakai[$b['url']] = true;

            return self::berkasRingkas($b);
        };
    }

    /** Bentuk berkas yang dibaca layar — tanpa ukuran & status verifikasi. */
    private static function berkasRingkas(array $b): array
    {
        return [
            'field' => $b['field'],
            'nama' => $b['nama'],
            'url' => $b['url'],
            'ext' => $b['ext'],
            'isImage' => $b['isImage'],
            'isPdf' => $b['isPdf'],
            // Ukuran, status verifikasi, dan waktu unggah ikut turun ke bentuk
            // ringkas ini. Panel berkas di layar peninjau mengurutkan lembaran
            // dari yang TERBARU dan menyebut beratnya sebelum dibuka — dua hal
            // yang tak bisa dijawab kalau ketiganya berhenti di bentuk penuh.
            'ukuran' => $b['ukuran'] ?? 0,
            'status' => $b['status'] ?? '',
            'waktu' => $b['waktu'] ?? '',
            // Tanda "diunggah admin" — hanya jalur worklist yang mengisinya
            // (lihat worklistBerkas); portal kandidat selalu mendapat false.
            'olehAdmin' => $b['olehAdmin'] ?? false,
            'oleh' => $b['oleh'] ?? null,
            'catatan' => $b['catatan'] ?? null,
        ];
    }

    /**
     * SELURUH berkas milik satu sub-isian pada satu baris berulang.
     *
     * Urutan pencocokannya sama persis dengan bentuk satuan di pencariBerkas():
     * posisi baris lebih dulu (paling tegas), nama berkas sebagai cadangan untuk
     * data lama yang belum punya Bagian_Key/Baris_Index. Bedanya hanya satu —
     * yang ditemukan tidak berhenti di berkas pertama.
     *
     * @param  array<string,bool>  $dipakai  penanda berkas yang sudah diklaim baris lain (by-ref)
     * @return array<int,array>
     */
    private static function berkasSeBaris(
        \Illuminate\Support\Collection $berkas,
        ?string $bagian,
        string $kunci,
        string $nilai,
        ?int $baris,
        array &$dipakai,
    ): array {
        $hasil = [];

        if ($bagian !== null && $baris !== null) {
            foreach ($berkas as $x) {
                if (($x['bagian'] ?? null) === $bagian && ($x['baris'] ?? null) === $baris
                    && $x['field'] === $kunci && empty($dipakai[$x['url']])) {
                    $dipakai[$x['url']] = true;
                    $hasil[] = self::berkasRingkas($x);
                }
            }
        }

        if ($hasil || $nilai === '') {
            return $hasil;
        }

        // ── CADANGAN: COCOKKAN NAMANYA ──────────────────────────────────────
        //
        // Nilai utuh dicoba lebih dulu — itulah bentuk unggahan tunggal, dan
        // nama berkas sendiri boleh mengandung koma. Baru sesudah itu ia dipecah
        // sebagai daftar, karena nilaiIsian() menggabungkan unggahan berganda
        // dengan ", ".
        $nama = $berkas->contains(fn ($x) => $x['field'] === $kunci && $x['nama'] === $nilai)
            ? [$nilai]
            : array_filter(array_map('trim', explode(',', $nilai)), fn ($n) => $n !== '');

        foreach ($nama as $n) {
            $b = $berkas->first(fn ($x) => $x['field'] === $kunci && $x['nama'] === $n && empty($dipakai[$x['url']]));
            if ($b) {
                $dipakai[$b['url']] = true;
                $hasil[] = self::berkasRingkas($b);
            }
        }

        return $hasil;
    }

    private static function nilaiIsian(mixed $v, int $dalam = 0, ?\Closure $cariBerkas = null): array
    {
        if ($v === null) {
            return ['nilai' => '', 'baris' => [], 'daftar' => []];
        }

        if (is_bool($v)) {
            return ['nilai' => $v ? 'Ya' : 'Tidak', 'baris' => [], 'daftar' => []];
        }

        if ($v instanceof \stdClass) {
            $v = (array) $v;
        }

        if (! is_array($v)) {
            return ['nilai' => trim((string) $v), 'baris' => [], 'daftar' => []];
        }

        // Pagar kedalaman: jawaban formulir tak pernah bersarang sedalam ini,
        // dan tanpa pagar satu data rusak bisa membuat halaman berputar
        // sampai kehabisan memori — kegagalan yang jauh lebih sulit dilacak
        // daripada nilai yang sekadar tidak tampil.
        if ($dalam > 3) {
            return ['nilai' => '…', 'baris' => [], 'daftar' => []];
        }

        $bersarang = false;
        foreach ($v as $x) {
            if (is_array($x) || $x instanceof \stdClass) {
                $bersarang = true;
                break;
            }
        }

        // ── DATAR: centang berganda / pilihan berganda ───────────────────────
        if (! $bersarang) {
            $isi = [];
            foreach ($v as $x) {
                $t = self::nilaiIsian($x, $dalam + 1)['nilai'];
                if ($t !== '') {
                    $isi[] = $t;
                }
            }

            // BUTIRNYA IKUT DIKIRIM, bukan cuma hasil gabungannya.
            //
            // Jawaban tipe `daftar` ("sebutkan minimal 5 hal") disimpan sebagai
            // larik — lima gagasan terpisah. Digabung dengan koma, kelimanya
            // jadi satu kalimat panjang tanpa batas yang terlihat: "sdfa, fa,
            // fafda, fafa, fa". Yang dibaca peninjau bukan lima jawaban lagi,
            // melainkan satu jawaban yang kebetulan berkoma — dan tuntutan
            // "minimal 5" yang dijaga formulir jadi mustahil diperiksa ulang
            // dengan mata.
            //
            // `nilai` TETAP berisi gabungannya: dipakai ekspor, pencarian, dan
            // layar lama. Yang ditambahkan cuma bentuk aslinya, supaya layar
            // yang mau menggambarnya bernomor bisa melakukannya.
            return ['nilai' => implode(', ', $isi), 'baris' => [], 'daftar' => $isi];
        }

        // ── BERULANG: satu objek per baris ───────────────────────────────────
        $baris = [];
        // Indeks MENTAH dari Jawaban_Json, bukan posisi tampil. Baris yang
        // seluruh isinya kosong tidak ikut ditampilkan, jadi keduanya bisa
        // berbeda — dan yang dicocokkan ke Baris_Index adalah yang mentah.
        foreach (array_values($v) as $ri => $row) {
            if ($row instanceof \stdClass) {
                $row = (array) $row;
            }

            if (! is_array($row)) {
                $t = self::nilaiIsian($row, $dalam + 1)['nilai'];
                if ($t !== '') {
                    $baris[] = [['label' => '', 'nilai' => $t]];
                }

                continue;
            }

            $awalan = self::awalanBersama(array_keys($row));
            $pasangan = [];

            foreach ($row as $k => $x) {
                $t = self::nilaiIsian($x, $dalam + 1)['nilai'];
                if ($t === '') {
                    // Sub-isian kosong DILEWATI, bukan ditampilkan "—".
                    // Baris riwayat kerja yang uraiannya belum diisi tetap
                    // terbaca utuh; deretan tanda hubung hanya menutupi yang
                    // benar-benar ada.
                    continue;
                }

                $nama = $awalan !== '' && str_starts_with((string) $k, $awalan)
                    ? substr((string) $k, strlen($awalan))
                    : (string) $k;

                // Sub-isian yang ternyata UNGGAHAN dibawa berikut url-nya.
                // Kuncinya dicari APA ADANYA (`sert_file`), bukan yang sudah
                // dipangkas awalan — Field_Key di tabel berkas menyimpan
                // bentuk penuhnya.
                // NILAINYA IKUT DIKIRIM, dan itu yang membedakan baris satu
                // dari baris lainnya: `$k` identik di seluruh baris riwayat
                // (`sert_file` lagi dan lagi), sedangkan `$t` memuat NAMA
                // berkas milik baris ini. Tanpa argumen kedua, tiap baris
                // menerima berkas yang sama — lihat pencariBerkas().
                //
                // Diminta SEKALIGUS BANYAK: satu baris sertifikat kerap membawa
                // lebih dari satu lembar. `berkas` tetap dikirim (berkas pertama)
                // supaya layar lama yang hanya mengenal satu berkas tidak ikut
                // rusak oleh perubahan ini.
                $lampiran = $cariBerkas ? $cariBerkas((string) $k, $t, $ri, true) : [];

                $pasangan[] = [
                    'label' => ucwords(str_replace(['_', '-'], ' ', $nama)),
                    'nilai' => $t,
                    'berkas' => $lampiran[0] ?? null,
                    'berkasList' => $lampiran,
                ];
            }

            if ($pasangan) {
                $baris[] = $pasangan;
            }
        }

        // Ringkasan teks tetap disediakan: dipakai ekspor, pencarian, dan layar
        // lama yang belum membaca `baris`.
        $ringkas = [];
        foreach ($baris as $i => $pasangan) {
            $isi = implode(', ', array_map(fn ($p) => ($p['label'] !== '' ? $p['label'].': ' : '').$p['nilai'], $pasangan));
            $ringkas[] = count($baris) > 1 ? ($i + 1).') '.$isi : $isi;
        }

        return ['nilai' => implode(' | ', $ringkas), 'baris' => $baris, 'daftar' => []];
    }

    /**
     * Awalan yang DIPAKAI BERSAMA seluruh kunci satu baris berulang, mis.
     * `kerja_` pada kerja_perusahaan / kerja_jabatan / kerja_periode.
     *
     * Dibuang dari label supaya terbaca "Perusahaan, Jabatan, Periode" —
     * bukan "Kerja Perusahaan, Kerja Jabatan, Kerja Periode" yang mengulang
     * nama fieldnya di tiap kolom.
     *
     * Syaratnya ketat: minimal dua kunci, seluruhnya berawalan segmen yang
     * sama, dan tiap kunci masih menyisakan sesuatu sesudah awalan itu. Tanpa
     * syarat itu, `nama` dan `nomor` akan terpotong jadi `a` dan `omor`.
     */
    private static function awalanBersama(array $kunci): string
    {
        if (count($kunci) < 2) {
            return '';
        }

        $awal = null;
        foreach ($kunci as $k) {
            $bagian = explode('_', (string) $k);
            if (count($bagian) < 2 || $bagian[0] === '') {
                return '';
            }
            $awal ??= $bagian[0];
            if ($bagian[0] !== $awal) {
                return '';
            }
        }

        return $awal.'_';
    }

    /**
     * Aktivitas milik kandidat yang sedang login, atau null.
     *
     * Kepemilikan diperiksa lewat join ke Lamaran.Id_Users — mengetahui id
     * aktivitas orang lain tidak cukup untuk mengunggah atau membaca berkasnya.
     */
    private function tesMilikSaya(string $id): ?object
    {
        $userId = (int) session('career_auth.id');
        $realId = Hashids::decode($id)[0] ?? null;
        if (! $realId || ! $userId) {
            return null;
        }

        return DB::table('N_WEB_CAREERS_Lamaran_Tahap_Tes as t')
            ->join('N_WEB_CAREERS_Lamaran_Tahap as h', 'h.Id_Lamaran_Tahap', '=', 't.Lamaran_Tahap_Id')
            ->join('N_WEB_CAREERS_Lamaran as l', 'l.Id_Lamaran', '=', 'h.Lamaran_Id')
            ->where('t.Id_Lamaran_Tahap_Tes', $realId)
            ->where('l.Id_Users', $userId)
            ->select('t.*', 'h.Lamaran_Id', 'h.Status as StatusTahap')
            ->first();
    }

    /**
     * Pesan penolakan bila kotak unggah aktivitas ini SUDAH DITUTUP (batasnya
     * lewat) — null bila masih terbuka. Kalimatnya menyebut jalan keluarnya:
     * perpanjangan hanya bisa diberikan tim.
     */
    private static function galatUnggahTertutup(?array $aturan): ?string
    {
        if (! $aturan || empty($aturan['tertutup'])) {
            return null;
        }

        return 'Batas unggah sudah lewat ('.($aturan['batasTeks'] ?? '-').') — kotak unggah ditutup. '
            .'Hubungi tim rekrutmen bila kamu membutuhkan perpanjangan.';
    }

    /**
     * Mode urutan ini MENGUNCI aktivitas berikutnya atau tidak?
     *
     * Jawabannya dibaca dari `Master_Mode_Urutan.Flag_Berurutan`, bukan dari
     * membandingkan Kode-nya dengan 'BERURUTAN'. Bedanya baru terasa saat mode
     * ketiga ditambahkan lewat master: dengan perbandingan Kode, mode baru itu
     * diam-diam berperilaku seperti PARALEL di setiap tempat yang lupa diubah.
     *
     * SATU IMPLEMENTASI, dipakai bersama papan Monitoring lewat
     * PipelineReadModel (di-cache per proses di sana). Dulu aturannya ditulis
     * dua kali di dua berkas — dan dua salinan aturan giliran berarti worklist
     * dan portal kandidat bisa berselisih tentang aktivitas mana yang sedang
     * terbuka, tanpa satu pun galat yang menandainya.
     */
    private static function modeUrutanMengunci(?string $kode): bool
    {
        return \App\Support\Career\PipelineReadModel::urutanMengunci($kode);
    }

    /**
     * Mode lanjut ini menuntut pemicu admin?
     *
     * Dibaca dari `Master_Mode_Lanjut.Flag_Butuh_Trigger`, bukan dari
     * membandingkan Kode dengan 'MANUAL' — mode baru lewat master tidak boleh
     * menuntut kode ini ikut diubah.
     *
     * Nilai kosong = OTOMATIS. Itu perilaku sebelum fitur ini ada, dan aturan
     * baru tidak boleh berlaku surut ke aktivitas yang belum disetel.
     */
    private static ?\Illuminate\Support\Collection $modeLanjutCache = null;

    private static function lanjutButuhTrigger(?string $kode): bool
    {
        if (! $kode) {
            return false;
        }

        self::$modeLanjutCache ??= DB::table('N_WEB_CAREERS_Master_Mode_Lanjut')->get()->keyBy('Kode');

        return (self::$modeLanjutCache->get($kode)->Flag_Butuh_Trigger ?? 'T') === 'Y';
    }

    /**
     * PATCH /api/v1/karir/lamaran/sub-tes/{id}/lanjutkan — buka aktivitas
     * berikutnya pada tahap BERURUTAN yang ber-mode MANUAL.
     *
     * Inilah perantara yang selama ini tidak ada. Tanpa ini, aktivitas
     * berikutnya terbuka begitu yang sebelumnya selesai — dan kandidat yang
     * nilainya jelas di bawah ambang sudah telanjur diundang ke asesmen
     * berikutnya sebelum ada yang sempat membaca hasilnya.
     */
    public function subTesLanjutkan(string $id)
    {
        $realId = Hashids::decode($id)[0] ?? null;
        if (! $realId) {
            return ResponseHelper::error('Aktivitas tidak valid.', 422);
        }

        $sub = DB::table('N_WEB_CAREERS_Lamaran_Tahap_Tes')->where('Id_Lamaran_Tahap_Tes', $realId)->first();
        if (! $sub) {
            return ResponseHelper::error('Aktivitas tidak ditemukan.', 404);
        }

        if (($sub->Flag_Selesai ?? 'N') !== 'Y') {
            return ResponseHelper::error('Aktivitas ini belum selesai — belum ada hasil yang bisa ditinjau.', 409);
        }

        if (! self::lanjutButuhTrigger($sub->Lanjut_Mode ?? null)) {
            return ResponseHelper::error('Aktivitas ini disetel lanjut otomatis — tidak perlu dilanjutkan manual.', 409);
        }

        if (! empty($sub->Lanjut_At)) {
            return ResponseHelper::error('Aktivitas ini sudah dilanjutkan.', 409);
        }

        $now = now();
        $nama = session('career_auth.nama', 'ADMIN');

        DB::table('N_WEB_CAREERS_Lamaran_Tahap_Tes')->where('Id_Lamaran_Tahap_Tes', $realId)->update([
            'Lanjut_At' => $now,
            'Lanjut_By' => $nama,
            'Lanjut_By_Id' => session('career_auth.id'),
            'Updated_At' => $now,
            'Updated_By' => $nama,
        ]);

        Log::channel('web_career')->info("Aktivitas #{$realId} ({$sub->Label}) dilanjutkan oleh {$nama}.");

        return ResponseHelper::success(null, 'Aktivitas berikutnya dibuka.');
    }

    /**
     * GERBANG URUTAN AKTIVITAS.
     *
     * Pada tahap ber-`Urutan_Aktivitas='BERURUTAN'`, hanya aktivitas terdepan
     * yang boleh disentuh: wawancara HR baru masuk akal setelah hasil psikotes
     * keluar, dan menjadwalkannya lebih dulu berarti mengundang orang untuk
     * sesuatu yang mungkin tidak akan terjadi.
     *
     * DITEGAKKAN DI SERVER, bukan cukup dengan menyembunyikan tombolnya.
     * Tombol yang hilang tetap bisa dipanggil lewat DevTools, dan yang lebih
     * sering terjadi: layar basi. Admin yang membuka drawer sebelum rekannya
     * menyelesaikan aktivitas pertama masih memegang daftar tombol versi lama.
     *
     * Publik karena dipakai juga KonfirmasiJadwal: pernyataan "tidak
     * melanjutkan" yang menandai Tidak Hadir melewati gerbang yang sama
     * dengan tombolnya.
     *
     * @return string|null pesan penolakan, atau null bila boleh dikerjakan
     */
    public static function kunciUrutan(object $sub): ?string
    {
        $tahap = DB::table('N_WEB_CAREERS_Lamaran_Tahap')
            ->where('Id_Lamaran_Tahap', $sub->Lamaran_Tahap_Id)
            ->first(['Urutan_Aktivitas']);

        if (! self::modeUrutanMengunci($tahap->Urutan_Aktivitas ?? null)) {
            return null;
        }

        // Aktivitas SEBELUM ini yang belum membuka jalan. Dua sebab berbeda:
        //
        //   1. belum selesai            → kerjakan dulu
        //   2. selesai tapi ber-mode MANUAL dan belum ditekan "Lanjutkan"
        //      → tim sengaja menahannya untuk membaca hasilnya dulu
        //
        // Keduanya menutup jalan, tapi tindakan yang diminta berbeda — jadi
        // pesannya pun harus berbeda, kalau tidak admin mencari tombol yang
        // salah.
        $sebelum = DB::table('N_WEB_CAREERS_Lamaran_Tahap_Tes')
            ->where('Lamaran_Tahap_Id', $sub->Lamaran_Tahap_Id)
            ->where('Urutan', '<', $sub->Urutan)
            ->orderBy('Urutan')
            ->get(['Label', 'Flag_Selesai', 'Lanjut_Mode', 'Lanjut_At']);

        foreach ($sebelum as $s) {
            if (($s->Flag_Selesai ?? 'N') !== 'Y') {
                return "Tahap ini dikerjakan BERURUTAN. Selesaikan \"{$s->Label}\" lebih dulu.";
            }

            if (self::lanjutButuhTrigger($s->Lanjut_Mode ?? null) && empty($s->Lanjut_At)) {
                return "\"{$s->Label}\" sudah selesai tetapi belum dilanjutkan. Tekan \"Lanjutkan\" pada aktivitas itu dulu.";
            }
        }

        return null;
    }

    /**
     * Status lamaran → kata yang dibaca KANDIDAT + nadanya.
     *
     * Portal dulu hanya mengenal tiga status (BERJALAN / LULUS / GUGUR) lewat
     * peta literal di dalam layar. Sejak hasil keputusan jadi master, Status
     * bisa berisi MENGUNDURKAN_DIRI atau DITOLAK_KANDIDAT — dan keduanya jatuh
     * ke luar peta itu: kandidat yang mundur melihat tulisan `MENGUNDURKAN_DIRI`
     * apa adanya, sementara halaman detailnya menganggap lamaran masih berjalan
     * karena nadanya pun tak ketemu.
     *
     * Diambil dari MASTER, bukan peta baru yang lebih panjang: menambah hasil
     * keputusan berikutnya tidak boleh menuntut layar ikut diubah.
     *
     * Nada dipilih dari flag, BUKAN dari kodenya. "Mengundurkan diri" memang
     * bukan kelulusan, tapi ia juga bukan penolakan — mewarnainya merah seperti
     * GUGUR mengatakan kepada kandidat bahwa ia ditolak, padahal ia yang pergi.
     *
     * @return array{label:string, nada:string}
     */
    private static function statusKandidat(?string $status): array
    {
        $status = strtoupper((string) $status);

        if ($status === 'BERJALAN') {
            return ['label' => 'Berjalan', 'nada' => 'berjalan'];
        }

        $def = LamaranService::masterHasilKeputusan()->get($status);

        if (! $def) {
            return ['label' => $status ?: '—', 'nada' => 'berjalan'];
        }

        if (($def->Flag_Lolos ?? 'T') === 'Y') {
            return ['label' => 'Diterima', 'nada' => 'lolos'];
        }

        // Keputusan yang datang DARI KANDIDAT tidak pernah diwarnai sebagai
        // kegagalan — termasuk saat ia berakhir di Talent Pool.
        if (($def->Flag_Oleh_Kandidat ?? 'T') === 'Y') {
            return ['label' => $def->Nama, 'nada' => 'netral'];
        }

        return [
            'label' => $def->Nama,
            'nada' => ($def->Flag_Talent_Pool ?? 'T') === 'Y' ? 'menunggu' : 'gugur',
        ];
    }

    /** Bentuk satu berkas kandidat untuk dikirim ke layar. */
    private static function bentukTesBerkas(object $b): array
    {
        $ext = strtolower($b->Ext ?: pathinfo($b->Nama_File, PATHINFO_EXTENSION));

        return [
            'id' => Hashids::encode($b->Id_Lamaran_Tes_Berkas),
            'nama' => $b->Nama_File,
            'ext' => $ext,
            'ukuran' => (int) $b->Ukuran,
            'isPdf' => $ext === 'pdf' || $b->Mime === 'application/pdf',
            'isImage' => in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true),
            'url' => route('career.portal.tes.berkas.file', ['id' => Hashids::encode($b->Id_Lamaran_Tes_Berkas)]),
            // SUDAH IKUT DIKIRIM = tidak bisa dihapus kandidat lagi. Dikirim
            // per berkas supaya layar menyembunyikan tombol hapus tepat pada
            // yang memang terkunci — bukan mengunci seluruh daftar hanya karena
            // salah satunya sudah diserahkan.
            'terkunci' => ! empty($b->Terkirim_At),
            'terkirim' => ($b->Terkirim_At ?? null) ? (string) $b->Terkirim_At : null,
        ];
    }

    /** GET daftar berkas yang SUDAH diunggah kandidat untuk satu aktivitas. */
    public function tesBerkas(string $id)
    {
        $tes = $this->tesMilikSaya($id);
        if (! $tes) {
            return ResponseHelper::error('Aktivitas tidak ditemukan.', 404);
        }

        $rows = DB::table('N_WEB_CAREERS_Lamaran_Tes_Berkas')
            ->where('Lamaran_Tahap_Tes_Id', $tes->Id_Lamaran_Tahap_Tes)
            ->whereNull('Ulang_Id')
            ->orderBy('Id_Lamaran_Tes_Berkas')
            ->get();

        return ResponseHelper::success($rows->map(fn ($b) => self::bentukTesBerkas($b))->all(), 'Berkas aktivitas');
    }

    /**
     * POST unggah berkas kandidat untuk sebuah aktivitas.
     *
     * Format & ukuran diambil dari aturan yang DIBEKUKAN pada aktivitas ini,
     * bukan aturan umum: tes menggambar menerima gambar, tes tertulis menerima
     * PDF, dan batasnya berbeda-beda. Divalidasi di server juga — layar bisa
     * dilewati, dan berkas raksasa yang lolos membebani bucket diam-diam.
     */
    public function tesBerkasUnggah(Request $request, string $id)
    {
        $tes = $this->tesMilikSaya($id);
        if (! $tes) {
            return ResponseHelper::error('Aktivitas tidak ditemukan.', 404);
        }

        // Aturan dari alur ATAU dari mode jadwal (MCU mandiri) — sumber yang
        // sama dengan yang dibaca portal, jadi yang ditawarkan layar persis
        // yang diterima di sini.
        $aturan = UndanganJadwal::aturanUnggah($tes);
        if (! $aturan) {
            return ResponseHelper::error('Aktivitas ini tidak meminta unggahan berkas.', 409);
        }

        if ($tes->Flag_Selesai === 'Y' || $tes->StatusTahap !== 'BERJALAN') {
            return ResponseHelper::error('Aktivitas ini sudah selesai - berkas tidak bisa diubah lagi.', 409);
        }

        if ($galat = self::galatUnggahTertutup($aturan)) {
            return ResponseHelper::error($galat, 409);
        }

        // SUDAH DIKIRIM → LANGKAH UNGGAH DITUTUP SEPENUHNYA.
        //
        // "Kirim Berkas" adalah pernyataan bahwa berkasnya LENGKAP. Setelah itu
        // aktivitas ini pindah ke meja penilai, dan apa yang dinilai harus sama
        // persis dengan apa yang dinyatakan kandidat — tidak bertambah, tidak
        // berkurang. Membiarkan berkas menyusup masuk setelah pernyataan berarti
        // penilai bisa membaca lampiran yang belum pernah dinyatakan lengkap,
        // atau selesai menilai lalu isinya berubah di belakangnya.
        //
        // DITAHAN DI SERVER, bukan sekadar kotak seret-lepasnya disembunyikan:
        // pintu ini tetap bisa diketuk langsung tanpa lewat layar.
        if ($tes->Unggah_Kirim_At) {
            return ResponseHelper::error(
                'Berkas untuk aktivitas ini sudah kamu kirim dan sedang dinilai tim — '
                .'tidak bisa ditambah atau diubah lagi. Hubungi tim rekrutmen bila ada yang perlu diperbaiki.',
                409,
            );
        }

        $format = $aturan['format'];
        $maksMb = (int) $aturan['maksMb'];

        $data = $request->validate([
            'berkas' => 'required|file|mimes:'.implode(',', $format).'|max:'.($maksMb * 1024),
        ], [
            'berkas.mimes' => 'Hanya menerima berkas '.implode(', ', $format).'.',
            'berkas.max' => "Ukuran berkas melebihi {$maksMb} MB.",
        ]);

        $file = $data['berkas'];
        $gcs = app(GcsBerkas::class);
        $now = now();
        $nama = session('career_auth.nama');

        try {
            // getContent(), BUKAN getRealPath(): di bawah Apache getRealPath()
            // bisa mengembalikan string kosong dan isinya gagal terbaca.
            $konten = $file->getContent();
            $ext = strtolower($file->getClientOriginalExtension() ?: $file->extension());

            $folder = $gcs->folderTahap(
                $now->format('Y'), $now->format('m'), $now->format('d'),
                (string) ($nama ?: 'kandidat'),
            );

            $path = $gcs->unggah($folder, $tes->Label.'-'.Str::lower(Str::random(6)), $ext, $konten);
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('[TES-BERKAS] unggah gagal: '.$e->getMessage());

            return ResponseHelper::error('Berkas gagal diunggah: '.Str::limit($e->getMessage(), 140), 500);
        }

        $baruId = DB::table('N_WEB_CAREERS_Lamaran_Tes_Berkas')->insertGetId([
            'Lamaran_Tahap_Tes_Id' => $tes->Id_Lamaran_Tahap_Tes,
            'Lamaran_Id' => $tes->Lamaran_Id,
            'Id_Users' => (int) session('career_auth.id'),
            'Nama_File' => $file->getClientOriginalName(),
            'Path_File' => $path,
            'Mime' => $file->getMimeType(),
            'Ext' => $ext,
            'Ukuran' => strlen($konten),
            'Created_At' => $now,
            'Created_By' => $nama,
            'Created_By_Id' => session('career_auth.id'),
        ], 'Id_Lamaran_Tes_Berkas');

        $baris = DB::table('N_WEB_CAREERS_Lamaran_Tes_Berkas')->where('Id_Lamaran_Tes_Berkas', $baruId)->first();

        // Dulu di sini ada blok yang MEMBATALKAN pernyataan "sudah lengkap"
        // setiap kali berkas susulan masuk, supaya penanda di worklist tidak
        // berbunyi "lengkap sejak 09:12" atas data yang sudah berubah.
        //
        // Blok itu tidak diperlukan lagi — dan tidak akan pernah tercapai:
        // gerbang di atas menolak unggahan begitu aktivitasnya dikirim, jadi
        // keadaan "ada susulan setelah pernyataan" mustahil terbentuk. Yang
        // dinilai tim selalu persis yang dinyatakan kandidat.

        return ResponseHelper::success(self::bentukTesBerkas($baris), 'Berkas terunggah.');
    }

    /**
     * PATCH kandidat menyatakan berkasnya SUDAH LENGKAP untuk aktivitas ini.
     *
     * KENAPA PERLU TOMBOL TERSENDIRI
     * Mengunggah dan "selesai mengunggah" bukan hal yang sama. Tanpa pernyataan
     * ini dua pihak sama-sama menebak: kandidat tidak tahu apakah masih ada
     * yang harus dilakukan, dan admin tidak tahu apakah berkas yang masuk sudah
     * lengkap atau baru satu dari tiga. Menilai pekerjaan yang belum lengkap
     * adalah keputusan yang tidak bisa ditarik kembali.
     */
    public function tesBerkasKirim(Request $request, string $id)
    {
        $tes = $this->tesMilikSaya($id);
        if (! $tes) {
            return ResponseHelper::error('Aktivitas tidak ditemukan.', 404);
        }

        $aturan = UndanganJadwal::aturanUnggah($tes);
        if (! $aturan) {
            return ResponseHelper::error('Aktivitas ini tidak meminta unggahan berkas.', 409);
        }

        if ($tes->Flag_Selesai === 'Y' || $tes->StatusTahap !== 'BERJALAN') {
            return ResponseHelper::error('Aktivitas ini sudah selesai — berkas tidak bisa diubah lagi.', 409);
        }

        if ($galat = self::galatUnggahTertutup($aturan)) {
            return ResponseHelper::error($galat, 409);
        }

        $jumlah = DB::table('N_WEB_CAREERS_Lamaran_Tes_Berkas')
            ->where('Lamaran_Tahap_Tes_Id', $tes->Id_Lamaran_Tahap_Tes)
            ->whereNull('Ulang_Id')
            ->count();

        // Gerbang ini berlaku untuk SEMUA aktivitas berunggahan, bukan hanya
        // yang wajib: menyatakan "berkas saya lengkap" tanpa satu pun berkas
        // adalah pernyataan yang tidak berarti apa-apa, dan admin yang
        // membacanya akan mencari sesuatu yang tidak pernah ada.
        if ($jumlah < 1) {
            return ResponseHelper::error('Belum ada berkas yang diunggah. Unggah dulu, baru tekan kirim.', 422);
        }

        $now = now();
        $nama = session('career_auth.nama');

        DB::transaction(function () use ($tes, $now, $nama, $request) {
            DB::table('N_WEB_CAREERS_Lamaran_Tahap_Tes')
                ->where('Id_Lamaran_Tahap_Tes', $tes->Id_Lamaran_Tahap_Tes)
                ->update([
                    'Unggah_Kirim_At' => $now,
                    'Unggah_Kirim_By' => $nama,
                    'Unggah_Kirim_Ip' => Str::limit((string) $request->ip(), 60, ''),
                    'Updated_At' => $now,
                    'Updated_By' => $nama,
                ]);

            // GEMBOK MELEKAT PADA BERKASNYA, bukan hanya pada aktivitasnya.
            //
            // Aktivitasnya sendiri sudah tertutup — tesBerkasUnggah() menolak
            // unggahan apa pun sesudah ini. Stempel per berkas tetap ditulis
            // karena ia menjawab hal yang tidak bisa dijawab kolom aktivitas:
            // KAPAN berkas INI diserahkan, dan bahwa ia memang termasuk yang
            // dinyatakan lengkap. Dari situlah gerbang hapus membaca izinnya,
            // jadi keputusan "boleh dihapus atau tidak" tidak pernah bergantung
            // pada satu kolom yang letaknya jauh dari berkasnya.
            //
            // `whereNull` menjaga stempel PERTAMA tetap utuh — itulah saat
            // berkas ini benar-benar diserahkan.
            DB::table('N_WEB_CAREERS_Lamaran_Tes_Berkas')
                ->where('Lamaran_Tahap_Tes_Id', $tes->Id_Lamaran_Tahap_Tes)
                ->whereNull('Terkirim_At')
                ->update(['Terkirim_At' => $now]);
        });

        return ResponseHelper::success(
            ['waktu' => $now->toDateTimeString(), 'jumlah' => $jumlah],
            "Berkas kamu sudah dikirim ({$jumlah} berkas). Tim rekrutmen akan menilainya."
        );
    }

    /** DELETE berkas kandidat - selama aktivitasnya belum selesai. */
    public function tesBerkasHapus(string $id)
    {
        $userId = (int) session('career_auth.id');
        $realId = Hashids::decode($id)[0] ?? null;

        $b = $realId ? DB::table('N_WEB_CAREERS_Lamaran_Tes_Berkas as b')
            ->join('N_WEB_CAREERS_Lamaran_Tahap_Tes as t', 't.Id_Lamaran_Tahap_Tes', '=', 'b.Lamaran_Tahap_Tes_Id')
            ->where('b.Id_Lamaran_Tes_Berkas', $realId)
            ->where('b.Id_Users', $userId)
            ->select('b.*', 't.Flag_Selesai')
            ->first() : null;

        if (! $b) {
            return ResponseHelper::error('Berkas tidak ditemukan.', 404);
        }

        if ($b->Flag_Selesai === 'Y') {
            return ResponseHelper::error('Aktivitas sudah selesai - berkas tidak bisa dihapus.', 409);
        }

        // BATAS UNGGAH LEWAT → yang sudah masuk dibekukan apa adanya. Tim
        // membacanya sebagai bahan; menghapus sesudah batas sama saja menarik
        // bukti dari meja penilai.
        $pemilik = DB::table('N_WEB_CAREERS_Lamaran_Tahap_Tes')->where('Id_Lamaran_Tahap_Tes', $b->Lamaran_Tahap_Tes_Id)->first();
        if ($pemilik && ($galat = self::galatUnggahTertutup(UndanganJadwal::aturanUnggah($pemilik)))) {
            return ResponseHelper::error($galat, 409);
        }

        // SUDAH IKUT DIKIRIM → TIDAK BISA DITARIK LAGI.
        //
        // Menekan "Kirim Berkas" memindahkan berkas ini ke meja penilai. Sejak
        // detik itu ia bukan lagi draf pribadi kandidat melainkan BAHAN
        // PENILAIAN yang bisa sedang dibaca — sementara penghapusan di bawah
        // permanen sampai ke GCS: tidak ada tong sampah, tidak ada pemulihan.
        // Kandidat yang berubah pikiran (atau salah pencet) bisa mengosongkan
        // lampiran yang sudah dinilai, dan rapornya memuat penilaian atas
        // dokumen yang tak lagi ada.
        //
        // Penanda dibaca dari BERKASNYA, bukan dari Unggah_Kirim_At aktivitas —
        // lihat alasan lengkapnya di tesBerkasKirim().
        //
        // DITAHAN DI SERVER, bukan sekadar tombolnya disembunyikan: pintu ini
        // tetap bisa diketuk langsung tanpa lewat layar.
        if ($b->Terkirim_At) {
            return ResponseHelper::error(
                'Berkas ini sudah kamu kirim dan sedang dinilai tim — tidak bisa dihapus lagi. '
                .'Hubungi tim rekrutmen bila ada yang perlu diperbaiki.',
                409,
            );
        }

        // BASIS DATA DULU, GCS BELAKANGAN — lihat alasan lengkapnya di
        // hapusBerkasTahap(). Singkatnya: berkas yang terdaftar tapi isinya
        // sudah lenyap lebih merugikan daripada objek yatim di penyimpanan.
        DB::table('N_WEB_CAREERS_Lamaran_Tes_Berkas')->where('Id_Lamaran_Tes_Berkas', $realId)->delete();

        try {
            Storage::disk(GcsBerkas::DISK)->delete($b->Path_File);
        } catch (\Throwable $e) {
            Log::channel('web_career')->warning('[TES-BERKAS] sisa GCS gagal dihapus: '.$e->getMessage());
        }

        return ResponseHelper::success(null, 'Berkas dihapus.');
    }

    /** GET pratinjau berkas aktivitas milik kandidat (signed URL 15 menit). */
    public function tesBerkasFile(string $id)
    {
        $userId = (int) session('career_auth.id');
        $realId = Hashids::decode($id)[0] ?? null;

        $b = $realId ? DB::table('N_WEB_CAREERS_Lamaran_Tes_Berkas')
            ->where('Id_Lamaran_Tes_Berkas', $realId)
            ->where('Id_Users', $userId)
            ->first() : null;

        if (! $b || ! $b->Path_File) {
            abort(404);
        }

        try {
            $gcs = Storage::disk(GcsBerkas::DISK);
            if ($gcs->exists($b->Path_File)) {
                return redirect()->away($gcs->temporaryUrl($b->Path_File, now()->addMinutes(15)));
            }
        } catch (\Throwable $e) {
            Log::channel('web_career')->warning('[TES-BERKAS] signed URL gagal: '.$e->getMessage());
        }

        abort(404);
    }

    /**
     * GET berkas HASIL TAHAP milik kandidat login (signed URL GCS 15 menit).
     *
     * Endpoint admin (berkasTahapFile) bergerbang izin `pelamarPage`, jadi tidak
     * bisa dipakai kandidat. Yang ini memeriksa kepemilikan lewat
     * Lamaran.Id_Users — mengetahui id berkas orang lain tidak cukup untuk
     * membukanya.
     */
    public function portalBerkasTahap(string $id)
    {
        $userId = (int) session('career_auth.id');
        $realId = Hashids::decode($id)[0] ?? null;

        $b = DB::table('N_WEB_CAREERS_Lamaran_Tahap_Berkas as tb')
            ->join('N_WEB_CAREERS_Lamaran as l', 'l.Id_Lamaran', '=', 'tb.Lamaran_Id')
            ->where('tb.Id_Lamaran_Tahap_Berkas', $realId)
            ->where('l.Id_Users', $userId)
            ->select('tb.Path_File', 'tb.Nama_File')
            ->first();

        if (! $b || ! $b->Path_File) {
            abort(404);
        }

        try {
            $gcs = Storage::disk(GcsBerkas::DISK);
            if ($gcs->exists($b->Path_File)) {
                return redirect()->away($gcs->temporaryUrl($b->Path_File, now()->addMinutes(15)));
            }
        } catch (\Throwable $e) {
            Log::channel('web_career')->warning('[PORTAL] signed URL berkas tahap gagal: '.$e->getMessage());
        }

        abort(404);
    }

    // portalTanggapanPenawaran() DICABUT.
    //
    // Kandidat tidak lagi menyatakan menerima / mengundurkan diri sendiri
    // lewat portal. Keputusan itu kini hanya dicatat tim di worklist, lewat
    // "Keputusan dari Kandidat" — satu pintu, dengan jejak siapa mencatatnya.
    //
    // Kolom Tanggapan_Kandidat / Tanggapan_Catatan / Tanggapan_At SENGAJA
    // DIBIARKAN di tabel: membuangnya akan menghapus riwayat jawaban yang
    // pernah tercatat, dan worklist masih membacanya untuk menampilkan
    // jawaban lama. Yang dicabut kemampuannya menulis dari sisi kandidat.

    /**
     * Kode hasil untuk kandidat yang mundur, dipilih dari MASTER.
     *
     * Menolak penawaran resmi dan mengundurkan diri sebelum ada penawaran
     * adalah dua sebab berbeda; menyamakannya membuat laporan tidak bisa
     * menjawab "penawaran kita kalah" versus "kandidat pergi lebih dulu".
     */
    private static function kodeMundurKandidat(?string $tipeKode): ?string
    {
        $kandidat = \App\Support\Career\LamaranService::masterHasilKeputusan()
            ->filter(fn ($h) => ($h->Flag_Oleh_Kandidat ?? 'T') === 'Y');

        if ($kandidat->isEmpty()) {
            return null;
        }

        // Tahap penawaran resmi → "menolak penawaran" bila ada di master.
        if ($tipeKode === 'OFFERING' && $kandidat->has('DITOLAK_KANDIDAT')) {
            return 'DITOLAK_KANDIDAT';
        }

        return $kandidat->has('MENGUNDURKAN_DIRI')
            ? 'MENGUNDURKAN_DIRI'
            : $kandidat->keys()->first();
    }

    /** GET pratinjau berkas MILIK kandidat login (cek kepemilikan lamaran). */
    public function portalBerkasFile(string $id)
    {
        $userId = (int) session('career_auth.id');
        $realId = Hashids::decode($id)[0] ?? null;

        $b = DB::table('N_WEB_CAREERS_Formulir_Berkas as fb')
            ->join('N_WEB_CAREERS_Formulir_Pengisian as fp', 'fp.Id_Formulir_Pengisian', '=', 'fb.Formulir_Pengisian_Id')
            ->join('N_WEB_CAREERS_Lamaran as l', 'l.Id_Lamaran', '=', 'fp.Lamaran_Id')
            ->where('fb.Id_Formulir_Berkas', $realId)
            ->where('l.Id_Users', $userId) // hanya berkas milik kandidat sendiri
            ->select('fb.*')
            ->first();
        if (! $b) {
            abort(404);
        }

        if ($b->Path_File) {
            try {
                $gcs = Storage::disk(GcsBerkas::DISK);
                if ($gcs->exists($b->Path_File)) {
                    return redirect()->away($gcs->temporaryUrl($b->Path_File, now()->addMinutes(15)));
                }
            } catch (\Throwable $e) {
                Log::channel('web_career')->warning('Signed URL GCS gagal (portal) berkas '.$b->Id_Formulir_Berkas.': '.$e->getMessage());
            }
        }

        foreach ([storage_path('app/'.$b->Path_File), public_path($b->Path_File), $b->Path_File] as $kandidat) {
            if ($kandidat && is_file($kandidat)) {
                return response()->file($kandidat, ['Content-Type' => $b->Mime ?: 'application/octet-stream']);
            }
        }

        abort(404, 'File tidak ditemukan.');
    }

    /**
     * WEBHOOK CAT/HCLearn — hasil tes pihak ke-3 (server-to-server, guard secret).
     * Dipanggil CAT saat tes difinalisasi → WC memperbarui nilai peserta lalu
     * MENGGERAKKAN tahap otomatis: LULUS → tahap berikutnya, GUGUR → ditutup.
     * Idempoten: bila peserta sudah 'selesai', abaikan (balas ok).
     */
    public function hasilUjianCallback(Request $request)
    {
        // Guard: cocokkan secret (header X-WC-Secret). Tanpa login.
        $secret = (string) config('hclearn.callback_secret');
        if (! $secret || ! hash_equals($secret, (string) $request->header('X-WC-Secret'))) {
            return ResponseHelper::error('Secret tidak valid.', 401);
        }

        $data = $request->validate([
            'Id_WC_Penjadwalan_Peserta' => 'required|integer',
            'Status_Kelulusan' => 'nullable|string|max:30',
            'lulus' => 'nullable|boolean',
            'Total_Nilai' => 'nullable|numeric',
            'Total_Soal' => 'nullable|integer',
            'Ambang_Batas_Nilai' => 'nullable|numeric',
            'Status_Pengerjaan' => 'nullable|string|max:30',
            // DUA PERISTIWA LEWAT SATU PINTU — lihat cabang di bawah.
            'Jenis_Event' => 'nullable|string|max:40',
        ]);

        // PENCOCOKAN LEWAT PENGENAL YANG KITA KIRIM.
        //
        // CAT mengembalikan angka yang KITA berikan saat menjadwalkan, dan sejak
        // 3 Agustus 2026 itu adalah `Ref_Cat_Peserta` (nomor SEQUENCE), bukan
        // lagi kolom IDENTITY kita — id IDENTITY bisa terulang setelah tabel
        // di-reset, dan itu membuat token tertukar antar kandidat.
        //
        // Pencarian lewat IDENTITY DIPERTAHANKAN sebagai cadangan: hasil ujian
        // dari penjadwalan lama masih memakai id yang lama, dan kehilangan
        // hasilnya berarti kandidat tampak tak pernah mengerjakan tes.
        //
        // TAPI cadangan itu DIBATASI pada baris yang memang belum punya
        // pengenal baru (`Ref_Cat_Peserta IS NULL`). Tanpa batasan itu, hasil
        // lama bernomor 3 akan mendarat pada peserta yang KEBETULAN kini
        // ber-IDENTITY 3 — orang yang berbeda. Itu persis kelas kesalahan yang
        // sedang diperbaiki: nilai ujian tertukar antar kandidat.
        $ref = (int) $data['Id_WC_Penjadwalan_Peserta'];
        $peserta = DB::table('N_WEB_CAREERS_Penjadwalan_Peserta')->where('Ref_Cat_Peserta', $ref)->first()
            ?: DB::table('N_WEB_CAREERS_Penjadwalan_Peserta')
                ->where('Id_Penjadwalan_Peserta', $ref)
                ->whereNull('Ref_Cat_Peserta')
                ->first();

        if (! $peserta) {
            Log::channel('web_career')->warning("[HASIL-UJIAN] peserta tak dikenal untuk pengenal {$ref}.");

            return ResponseHelper::error('Peserta penjadwalan tidak ditemukan.', 404);
        }

        // ── "SELESAI MENGERJAKAN" — DATANG LEBIH DULU, TANPA VERDICT ────────
        //
        // CAT mengirim ini tepat saat peserta menekan kirim, sebelum skoring
        // berjalan. Verdict-nya menyusul lewat panggilan kedua.
        //
        // Kenapa perlu dua panggilan: skoring bisa memakan waktu, gagal, atau
        // tertahan di antrean — dan selama itu portal kandidat tidak tahu tesnya
        // sudah dikerjakan, lalu menyuguhkan tombol "Mulai Tes Sekarang" untuk
        // ujian yang baru saja ia selesaikan. Kandidat menekannya dan menemukan
        // dirinya di ruang ujian yang sudah tertutup. Yang menentukan tombol itu
        // bukan hasilnya, melainkan sudah-atau-belum ia mengerjakan — jadi dua
        // hal itu dipisah.
        //
        // MESIN KEPUTUSAN TIDAK DIJALANKAN di sini. Payload ini tidak membawa
        // kelulusan, dan prosesHasilUjian() membaca ketiadaan verdict sebagai
        // GUGUR — satu panggilan ini akan menggugurkan setiap peserta yang baru
        // selesai mengerjakan.
        if (strtoupper((string) ($data['Jenis_Event'] ?? '')) === 'SELESAI_MENGERJAKAN') {
            // HANYA status pengerjaan. Flag_Selesai & Status_Kelulusan sengaja
            // tidak disentuh: keduanya milik verdict, dan mengisinya di sini
            // membuat peserta tampak sudah dinilai padahal skoringnya belum
            // tentu berhasil.
            DB::table('N_WEB_CAREERS_Penjadwalan_Peserta')
                ->where('Id_Penjadwalan_Peserta', $peserta->Id_Penjadwalan_Peserta)
                ->update([
                    'Status_Pengerjaan' => 'selesai',
                    'Updated_At' => now(),
                ]);

            Log::channel('web_career')->info(
                "[HASIL-UJIAN] peserta #{$peserta->Id_Penjadwalan_Peserta} SELESAI MENGERJAKAN — menunggu verdict."
            );

            return ResponseHelper::success(['diproses' => true], 'Status pengerjaan diperbarui.');
        }

        $hasil = $this->prosesHasilUjian($peserta, $data, 'CALLBACK');

        if (! $hasil['diproses']) {
            return ResponseHelper::success($hasil, 'Hasil diterima (tahap sudah final).');
        }

        return ResponseHelper::success($hasil, 'Hasil tes diproses.');
    }

    /**
     * SATU JALUR untuk memasukkan hasil ujian pihak ke-3 ke mesin keputusan.
     *
     * Dipakai dua pemanggil yang membawa data dari sumber yang sama (CAT):
     *   - webhook `hasilUjianCallback` — CAT mendorong begitu tes difinalisasi;
     *   - `subTesSinkron` — admin menarik sendiri saat dorongan itu tak sampai.
     *
     * Disatukan supaya keduanya mustahil berbeda perilaku: verdict dihitung
     * dengan aturan yang sama, nilai disimpan ke kolom yang sama, mesin tahap
     * dievaluasi lewat pintu yang sama, dan emailnya pun sama. Idempoten:
     * tahap yang sudah final tidak diproses ulang.
     *
     * @return array{diproses:bool, hasil?:string, outcome?:string}
     */
    private function prosesHasilUjian(object $peserta, array $data, string $asal): array
    {
        // Verdict LULUS/GUGUR dari CAT (bool 'lulus' diprioritaskan; fallback teks).
        $teks = strtoupper((string) ($data['Status_Kelulusan'] ?? ''));
        $lulus = array_key_exists('lulus', $data) && $data['lulus'] !== null
            ? (bool) $data['lulus']
            : in_array($teks, ['LULUS', 'LOLOS', 'PASS', 'ACCEPT'], true);
        $hasil = $lulus ? 'LULUS' : 'GUGUR';

        // SATU TRANSAKSI: nilai yang direkam dan tahap yang menyimpulkannya
        // adalah satu peristiwa. CAT tidak mengirim hasil yang sama dua kali,
        // dan gerbang idempoten di bawah menolak percobaan ulang — jadi
        // kegagalan di antara keduanya berarti nilai tersimpan tanpa pernah
        // dinilai, dan tahapnya menunggu selamanya.
        $jalan = DB::transaction(function () use ($peserta, $data, $hasil) {
            // Rekam nilai apa pun keputusannya (untuk tampil di worklist admin).
            DB::table('N_WEB_CAREERS_Penjadwalan_Peserta')
                ->where('Id_Penjadwalan_Peserta', $peserta->Id_Penjadwalan_Peserta)
                ->update(array_filter([
                    'Total_Nilai' => $data['Total_Nilai'] ?? null,
                    'Total_Soal' => $data['Total_Soal'] ?? null,
                    'Ambang_Batas_Nilai' => $data['Ambang_Batas_Nilai'] ?? null,
                    'Status_Kelulusan' => $data['Status_Kelulusan'] ?? $hasil,
                    'Status_Pengerjaan' => $data['Status_Pengerjaan'] ?? 'selesai',
                    'Flag_Selesai' => 'Y',
                    'Waktu_Callback' => now(),
                    'Updated_At' => now(),
                ], fn ($v) => $v !== null));

            // Tahap lamaran pemilik ujian ini.
            //
            // DICARI LEWAT SUB-TES, bukan lewat kolom Penjadwalan_Tahap_Id milik
            // tahapnya. Satu tahap bisa memuat beberapa ujian yang dijadwalkan
            // di penjadwalan BERBEDA — "psikotest" berisi kognitif (penjadwalan
            // #316) dan rr (#323) — sementara Lamaran_Tahap hanya punya SATU
            // kolom penjadwalan, yang berisi salah satunya saja.
            //
            // Akibatnya hasil ujian yang penjadwalannya bukan yang tercatat di
            // kolom itu tidak menemukan tahapnya, lalu pulang sebagai
            // 'diproses' => false. Callback-nya sendiri menjawab HTTP 200 dan
            // nilainya tersimpan di Penjadwalan_Peserta, jadi tidak ada yang
            // tampak gagal — tapi sub-tesnya tidak pernah beranjak dari
            // DIJADWALKAN, dan worklist selamanya menawarkan "Sinkronkan" untuk
            // ujian yang sebenarnya sudah selesai.
            //
            // Lamaran_Tahap_Tes menyimpan Penjadwalan_Tahap_Id per aktivitas,
            // jadi ia satu-satunya yang tahu pemilik sebenarnya.
            $tahap = DB::table('N_WEB_CAREERS_Lamaran_Tahap as t')
                ->join('N_WEB_CAREERS_Lamaran_Tahap_Tes as s', 's.Lamaran_Tahap_Id', '=', 't.Id_Lamaran_Tahap')
                ->where('t.Lamaran_Id', $peserta->Lamaran_Id)
                ->where('s.Penjadwalan_Tahap_Id', $peserta->Penjadwalan_Tahap_Id)
                ->select('t.*')
                ->first();

            // Cadangan untuk data lama: sebelum sub-tes menyimpan penjadwalannya
            // sendiri, ikatan itu hanya ada di kolom tahap.
            if (! $tahap) {
                $tahap = DB::table('N_WEB_CAREERS_Lamaran_Tahap')
                    ->where('Lamaran_Id', $peserta->Lamaran_Id)
                    ->where('Penjadwalan_Tahap_Id', $peserta->Penjadwalan_Tahap_Id)
                    ->first();
            }

            if (! $tahap || $tahap->Status !== 'BERJALAN') {
                return ['diproses' => false]; // sudah diproses sebelumnya (idempoten)
            }

            // MESIN KEPUTUSAN: rekam hasil SUB-TES ini, lalu evaluasi mode tahap.
            // Tahap 1-tes → identik dgn perilaku lama (maju/gugur). Tahap multi-tes →
            // tahap hanya maju/gugur bila kondisi mode terpenuhi; selain itu menunggu.
            // Sub-tes dikenali lewat Penjadwalan_Tahap_Id — ikatan pasti antara sesi
            // ujian dan aktivitas, penting saat satu tahap memuat beberapa ujian.
            $eval = $this->svc->rekamHasilTesEksternal(
                (int) $tahap->Id_Lamaran_Tahap,
                null,
                $hasil,
                isset($data['Total_Nilai']) ? (float) $data['Total_Nilai'] : null,
                isset($data['Total_Soal']) ? (int) $data['Total_Soal'] : null,
                (int) $peserta->Penjadwalan_Tahap_Id
            );

            return [
                'diproses' => true,
                'outcome' => $eval['outcome'] ?? 'TUNGGU',
                'label' => $tahap->Label,
            ];
        });

        if (! $jalan['diproses']) {
            return ['diproses' => false];
        }

        $outcome = $jalan['outcome'];

        // Email hanya saat tahap BENAR-BENAR menyimpulkan (bukan per sub-tes) —
        // supaya baterai tes tidak membanjiri kandidat dgn email tiap hasil.
        // DI LUAR TRANSAKSI: lihat alasannya di subTesKehadiran().
        if (in_array($outcome, ['LANJUT', 'GUGUR'], true)) {
            $this->kirimEmailHasilTahap((int) $peserta->Lamaran_Id, $outcome === 'LANJUT');
        }

        Log::channel('web_career')->info("[{$asal}] hasil tes peserta #{$peserta->Id_Penjadwalan_Peserta} = {$hasil} → tahap {$jalan['label']}: {$outcome}.");

        return ['diproses' => true, 'hasil' => $hasil, 'outcome' => $outcome];
    }

    /**
     * Kirim email hasil sebuah TAHAP kepada kandidat.
     *
     * `$tahapId` menunjuk tahap mana yang diberitakan. Dibiarkan null saat
     * dipanggil sesudah sebuah keputusan baru diambil — yang dimaksud pasti
     * keputusan barusan, yaitu yang Diputus_At-nya terbaru.
     *
     * DISEBUT EKSPLISIT saat admin mengirim ULANG. Tanpa itu, kirim ulang
     * untuk "Seleksi Administrasi" pada kandidat yang sudah berada di tahap
     * 3 akan memberitakan tahap 3 — kandidat menerima kabar tahap yang salah,
     * dan justru email yang gagal itulah yang tetap tidak pernah sampai.
     */
    private function kirimEmailHasilTahap(int $lamaranId, bool $lulus, ?int $tahapId = null): void
    {
        try {
            $l = DB::table('N_WEB_CAREERS_Lamaran as l')
                ->join('N_WEB_CAREERS_Program as p', 'p.Id_Program', '=', 'l.Program_Id')
                ->leftJoin('N_WEB_CAREERS_Program_Posisi as x', 'x.Id_Program_Posisi', '=', 'l.Program_Posisi_Id')
                ->where('l.Id_Lamaran', $lamaranId)
                ->select('l.Id_Users', 'l.Kode', 'l.Status', 'l.Hasil_Akhir', 'l.Program_Id',
                    'l.Urutan_Tahap', 'l.Total_Tahap', 'p.Nama as ProgramNama', 'x.Posisi')
                ->first();
            if (! $l || ! $l->Id_Users) {
                return;
            }

            // Metode ini HANYA dipanggil saat sebuah tahap benar-benar menyimpulkan
            // (LANJUT / GUGUR) — lihat pemanggilnya. Jadi hasilnya selalu LOLOS atau
            // GUGUR, tidak pernah MENUNGGU.
            //
            // Dulu di sini: lulus tahap ANTARA dikirimi status 'MENUNGGU', yang di
            // template berbunyi "Pendaftaranmu sedang ditinjau" — kandidat yang baru
            // saja diloloskan justru diberi tahu bahwa lamarannya belum diperiksa.
            // Kalimat "sedang ditinjau" tetap benar untuk email SAAT MELAMAR
            // (WcApplyFormJob), bukan untuk keputusan tahap.
            // Metode ini HANYA dipanggil saat tahap benar-benar menyimpulkan,
            // jadi hasilnya wajib LOLOS atau GUGUR. 'MENUNGGU' milik jalur APPLY
            // (WcApplyFormJob) dan tidak boleh bocor ke sini — pagar ini yang
            // membuat kekeliruan itu mustahil terulang diam-diam.
            $status = $lulus ? 'LOLOS' : 'GUGUR';

            if (! in_array($status, ['LOLOS', 'GUGUR'], true)) {
                Log::channel('web_career')->error(
                    "[EMAIL] status keputusan tahap tidak sah: '{$status}' (lamaran #{$lamaranId}) — email dibatalkan."
                );

                return;
            }

            // `diterima` = seluruh seleksi tuntas, bukan sekadar satu tahap lewat.
            // Template memakainya untuk memilih antara "Kamu Diterima" dan
            // "Kamu Lolos tahap X — lanjut ke tahap Y".
            $diterima = $lulus && ($l->Status === 'LULUS' || $l->Hasil_Akhir === 'DITERIMA');

            // Tahap yang BARU SAJA diputus + tahap sesudahnya, supaya emailnya
            // menyebut nama tahapnya alih-alih kalimat umum.
            $tahapDiputus = DB::table('N_WEB_CAREERS_Lamaran_Tahap')
                ->where('Lamaran_Id', $lamaranId)
                ->whereNotNull('Diputus_At')
                ->when($tahapId, fn ($q) => $q->where('Id_Lamaran_Tahap', $tahapId))
                ->orderByDesc('Diputus_At')
                ->orderByDesc('Urutan')
                ->first(array_merge(['Urutan', 'Label'], CatatanEksternal::siap() ? [CatatanEksternal::KOLOM] : []));

            $tahapBerikut = $tahapDiputus
                ? DB::table('N_WEB_CAREERS_Lamaran_Tahap')
                    ->where('Lamaran_Id', $lamaranId)
                    ->where('Urutan', '>', $tahapDiputus->Urutan)
                    ->orderBy('Urutan')
                    ->value('Label')
                : null;

            $kandidat = LamaranService::dataKandidatEmail($lamaranId);

            // [feat/feedback] Buat feedback record untuk keputusan FINAL
            $feedbackUrl = null;
            if (in_array($status, ['GUGUR', 'LOLOS'], true)) {
                $userEmail = DB::table('N_WEB_CAREERS_Users')
                    ->where('Id_Users', $l->Id_Users)
                    ->value('Email');
                if ($userEmail) {
                    $feedbackService = app(\App\Support\Career\FeedbackService::class);
                    $feedbackId = $feedbackService->buatFeedback(
                        (int) $lamaranId,
                        $userEmail,
                        $l->Program_Id
                    );
                    if ($feedbackId) {
                        $tokens = $feedbackService->generateTokenPair($feedbackId, $userEmail);
                        $feedbackUrl = rtrim(config('app.url'), '/')
                            .'/feedback/'.$tokens['hashids'].'/'.$tokens['signature'];
                    }
                }
            }
            // Akhir [feat/feedback]

            WcApplyEmailJob::dispatch((int) $l->Id_Users, $status, [
                'kode' => $l->Kode,
                'posisi' => $l->Posisi ?: $l->ProgramNama,
                'program' => $l->ProgramNama,
                'feedbackUrl' => $feedbackUrl, // [feat/feedback]
                // Konteks tahap — tanpa ini email lolos berbunyi umum saja.
                'tahapLolos' => $tahapDiputus->Label ?? null,
                // Catatan UNTUK KANDIDAT dari tahap ini (mis. link Zoom psikotes),
                // sebagai TEKS — server surat tidak menerima HTML dari pemanggil.
                'catatan' => CatatanEksternal::keTeksSurat($tahapDiputus->Catatan_Eksternal_Html ?? null),
                'tahapBerikut' => $tahapBerikut,
                'urutan' => $tahapDiputus->Urutan ?? null,
                'total' => $l->Total_Tahap ?? null,
                'diterima' => $diterima,
                // Kartu data kandidat — disebar utuh, jadi field yang kelak
                // ditambahkan di dataKandidatEmail() ikut terbawa tanpa perlu
                // menyalin namanya satu per satu di sini.
                ...$kandidat,
            ]);
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("[CALLBACK] gagal antre email hasil lamaran #{$lamaranId}: ".$e->getMessage());
        }
    }

    /** POST /api/v1/lamaran/tahap/{id}/kirim — kandidat mengirim formulir tahap. */
    public function kirimFormulir(Request $request, string $id)
    {
        $userId = (int) session('career_auth.id');
        $realId = Hashids::decode($id)[0] ?? null;
        if (! $realId) {
            return ResponseHelper::error('Tahap tidak valid.', 422);
        }

        $data = $request->validate([
            'jawaban' => 'required|array',
        ]);

        // Skema yang DIBEKUKAN untuk tahap ini — sumbernya sama dengan
        // simpanPengisian() dan dengan yang dirender portal.
        $tahap = DB::table('N_WEB_CAREERS_Lamaran_Tahap as t')
            ->join('N_WEB_CAREERS_Lamaran as l', 'l.Id_Lamaran', '=', 't.Lamaran_Id')
            ->where('t.Id_Lamaran_Tahap', $realId)
            ->where('l.Id_Users', $userId)
            ->first(['t.Formulir_Kode', 't.Formulir_Versi', 'l.Kategori']);
        if (! $tahap) {
            return ResponseHelper::error('Tahap tidak ditemukan.', 404);
        }
        $skema = ($tahap->Formulir_Kode
            ? FormulirSchema::byKodeDanVersi($tahap->Formulir_Kode, $tahap->Formulir_Versi !== null ? (int) $tahap->Formulir_Versi : null)
            : FormulirSchema::pendaftaranUntukKategori((string) ($tahap->Kategori ?? '')))['schema'] ?? null;

        try {
            // SATU TRANSAKSI: jawaban tersimpan DAN berkasnya berpindah
            // kepemilikan, atau tidak sama sekali.
            //
            // Dulu ketiganya berdiri sendiri. Bila pemindahan berkas gagal di
            // tengah, pengisiannya sudah telanjur tercatat — dan kandidat tidak
            // bisa mengulang karena tahapnya sudah dianggap terisi. Yang tersisa
            // adalah formulir terkirim yang lampirannya masih berstatus draf:
            // tim rekrutmen membaca nama berkas yang tidak bisa dibuka.
            $hasil = DB::transaction(function () use ($realId, $userId, $data, $request, $skema) {
                // KUNCI tahap lalu draf — urutan yang sama dengan unggahan draf
                // (FormulirDrafController::kunciDraf). Unggahan yang masih
                // berjalan menunggu kiriman ini selesai, lalu ditolak karena
                // formulirnya sudah terkirim; kiriman ganda (klik dua kali,
                // atau mengulang setelah galat) berhenti di sini alih-alih
                // melahirkan pengisian kedua TANPA berkas yang lalu menggantikan
                // yang pertama di Lamaran_Tahap.
                $draf = FormulirDrafController::kunciDraf((int) $realId, $userId);

                // BERKAS YANG DISEBUT JAWABAN HARUS BENAR-BENAR ADA.
                //
                // Nama berkas masuk ke jawaban begitu dipilih, isinya naik
                // lewat unggahan terpisah. Unggahan yang gagal atau tertimpa
                // dulu lolos sebagai "Terkirim" — nama tercatat, berkasnya tidak
                // pernah ada. Aturan tampil & wajibnya sama dengan layar
                // (BerkasFormulir ↔ aturan.js).
                $kurang = BerkasFormulir::kurang(
                    $skema,
                    $data['jawaban'],
                    BerkasFormulir::adaDiDaftar(BerkasBaris::daftar($draf->Berkas_Json ?? null)),
                );
                if ($kurang) {
                    return ['berkasKurang' => $kurang];
                }

                $r = $this->svc->simpanPengisian((int) $realId, $userId, $data['jawaban'], $request->ip());

                // Penolakan aturan (syarat tak terpenuhi, tahap sudah terisi)
                // datang sebagai nilai balik. Ditinggikan jadi lemparan supaya
                // ia benar-benar membatalkan transaksinya; penangkapnya di bawah
                // mengembalikannya jadi 422 seperti semula.
                if (! $r['ok']) {
                    throw new \DomainException($r['pesan']);
                }

                // BERKAS DRAF -> BERKAS PERMANEN, lalu draf dibuang.
                //
                // Urutannya penting dan tidak boleh dibalik. Sebelumnya draf langsung
                // dihapus berikut berkasnya di GCS, sehingga pengisian hanya mewarisi
                // NAMA berkas sebagai teks jawaban sementara isinya lenyap — kandidat
                // melihat nama yang tak bisa dibuka dan tim rekrutmen kehilangan
                // dokumennya.
                //
                // hapusBerkas: false karena objek GCS-nya TIDAK disalin, hanya
                // berpindah kepemilikan ke Formulir_Berkas. Menghapusnya di sini
                // berarti membuang berkas yang baru saja diadopsi — dan itu pula
                // yang membuat langkah ini aman berada di dalam transaksi:
                // seluruhnya menyentuh basis data saja, tak satu pun objek GCS
                // dihapus, jadi tidak ada akibat yang mustahil digulung balik.
                $pengisianId = (int) DB::table('N_WEB_CAREERS_Lamaran_Tahap')
                    ->where('Id_Lamaran_Tahap', $realId)
                    ->value('Formulir_Pengisian_Id');

                if ($pengisianId) {
                    FormulirDrafController::jadikanPermanen((int) $realId, $userId, $pengisianId, $data['jawaban']);
                }

                FormulirDrafController::bersihkan((int) $realId, $userId, hapusBerkas: false);

                return $r;
            });

            if (isset($hasil['berkasKurang'])) {
                return $this->galatBerkasKurang($hasil['berkasKurang']);
            }

            // ── BIODATA MENYUSUL KE HRIS REKRUTMEN ──────────────────────────
            //
            // Di sinilah data diri kandidat sebenarnya masuk — tanggal lahir,
            // jenis kelamin, alamat, pendidikan. Dulu tidak ada satu pun dari
            // ini yang sampai ke HCLearn: satu-satunya pengiriman terjadi saat
            // kandidat MENDAFTAR AKUN, tepat ketika ia belum mengisi apa pun.
            //
            // Di luar transaksi dan lewat antrean: panggilan ini menembak server
            // lain, dan formulir kandidat sudah tersimpan dengan selamat. Ia
            // tidak boleh menunggu — apalagi gagal — karena HCLearn sedang sibuk.
            //
            // Dibungkus sendiri: formulirnya SUDAH tersimpan. Antrean yang
            // menolak dulu berujung "Gagal menyimpan formulir" (500), kandidat
            // mengulang, dan kiriman keduanya membuat pengisian tanpa berkas.
            try {
                WcBiodataHrisJob::dispatch($userId, 'KIRIM_FORMULIR');
            } catch (\Throwable $e) {
                Log::channel('web_career')->warning('Biodata HRIS gagal diantrekan setelah kirim formulir: '.$e->getMessage());
            }

            return ResponseHelper::success(['rekomendasi' => $hasil['rekomendasi'] ?? null], $hasil['pesan']);
        } catch (\DomainException $e) {
            // Formulir sudah terkirim / tahap tertutup: 409, supaya layar tahu
            // yang dibutuhkan adalah memuat ulang, bukan memperbaiki isian.
            $status = $e->getMessage() === FormulirDrafController::PESAN_TERTUTUP ? 409 : 422;

            return ResponseHelper::error($e->getMessage(), $status);
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('Gagal simpan pengisian: '.$e->getMessage());

            return ResponseHelper::error('Gagal menyimpan formulir.', 500);
        }
    }

    // ═══════════════════════ ADMIN — WORKLIST ═══════════════════════

    /** /karir/pelamar — panel kiri: daftar PROGRAM berjalan; panel kanan: kanban alur program terpilih. */
    public function worklist()
    {
        return Inertia::render('Career/admin/Pelamar', CareerShell::props('/karir/pelamar', 'Worklist Pelamar', [
            'talent' => $this->talentTabs(),
            'programAwal' => $this->daftarProgram(1, self::PROGRAM_PER_HALAMAN, '', ''),
            // BERAPA KANDIDAT BOLEH DIPUTUS SEKALI KIRIM — dari konstanta yang
            // sama dengan aturan validasinya, supaya layar tidak pernah
            // mengizinkan pilihan yang akan ditolak server.
            'batasPutusMassal' => self::BATAS_PUTUS_MASSAL,
            // CATATAN KEPUTUSAN DUA ARAH — internal (tim) & eksternal (kandidat).
            // `eksternalSiap` false selama kolomnya belum dibuat: tab eksternal
            // tidak ditawarkan. `tabBawaan` menurut kategori yang dipegang akun
            // ini — lihat CatatanEksternal::tabBawaan().
            'catatanTahap' => [
                'eksternalSiap' => CatatanEksternal::siap(),
                'tabBawaan' => CatatanEksternal::tabBawaan(AksesService::kategoriDiizinkan(self::PAGE)),
            ],
            // TOMBOL KEPUTUSAN DIBACA DARI MASTER, bukan tiga tombol yang
            // ditulis mati di layar. Master sudah lama memuat lima hasil —
            // termasuk "Kandidat Menolak" dan "Mengundurkan Diri" — tetapi
            // worklist hanya pernah menampilkan tiga, sehingga dua sebab
            // berhentinya proses yang datang DARI KANDIDAT tak punya jalan
            // dicatat sama sekali dan terpaksa dicatat sebagai "Tidak Lolos".
            'hasilKeputusan' => \App\Support\Career\LamaranService::masterHasilKeputusan()
                ->values()
                ->map(fn ($h) => [
                    'kode' => $h->Kode,
                    'nama' => $h->Nama,
                    'labelTombol' => $h->Label_Tombol,
                    'labelKonfirmasi' => $h->Label_Konfirmasi,
                    'ikon' => $h->Ikon,
                    'warna' => $h->Warna,
                    'deskripsi' => $h->Deskripsi,
                    'lolos' => ($h->Flag_Lolos ?? 'T') === 'Y',
                    'kirimEmail' => ($h->Flag_Kirim_Email ?? 'T') === 'Y',
                    // Bila `pilihTalentPool` menyala, ini PILIHAN AWAL yang
                    // dicentangkan — bukan nasib yang sudah ditetapkan.
                    'talentPool' => ($h->Flag_Talent_Pool ?? 'T') === 'Y',
                    // Admin yang memutuskan kandidat disimpan atau tidak.
                    // Dipakai pengunduran diri & penolakan penawaran: keduanya
                    // bukan kegagalan seleksi, jadi jawabannya beda-beda per
                    // orang dan tidak boleh dipatok oleh jenis hasilnya.
                    'pilihTalentPool' => ($h->Flag_Pilih_Talent_Pool ?? 'T') === 'Y',
                    'labelTalentPool' => $h->Label_Talent_Pool ?? 'Simpan kandidat ini di Talent Pool',
                    // Keputusan yang datang dari KANDIDAT, bukan dari perusahaan.
                    // Dipisah di layar supaya tidak terbaca sebagai penilaian tim.
                    'olehKandidat' => ($h->Flag_Oleh_Kandidat ?? 'T') === 'Y',
                    'butuhAlasan' => ($h->Butuh_Alasan ?? 'T') === 'Y',
                    // HASIL INI MENUNTUT AKTIVITAS TAHAPNYA TUNTAS DULU?
                    //
                    // Asimetri yang disengaja: "Lolos" dan "Talent Pool"
                    // menyatakan orang ini cukup baik — pernyataan yang tak boleh
                    // dibuat di atas bukti yang belum ada. "Tidak Lolos",
                    // "Mengundurkan Diri", dan "Tahan" justru paling sering
                    // dibutuhkan JUSTRU saat segalanya belum lengkap; menguncinya
                    // menutup jalan keluar yang sah.
                    //
                    // Dari master, bukan daftar kode di sini: hasil keputusan
                    // baru yang ditambahkan admin harus menyatakan sikapnya
                    // sendiri, bukan diam-diam lolos dari gerbang.
                    'butuhTuntas' => ($h->Flag_Butuh_Tuntas ?? 'T') === 'Y',
                ])->all(),
            // JAWABAN KANDIDAT ATAS PENAWARAN — dicatat tim saat menandai
            // kehadiran negosiasi. `menutup` menyatakan jawaban itu langsung
            // menutup lamaran atau tidak, supaya layar bisa memperingatkan
            // sebelum ditekan alih-alih sesudahnya.
            'jawabanPenawaran' => self::masterJawabanPenawaran()->values()->map(fn ($j) => [
                'kode' => $j->Kode,
                'nama' => $j->Nama,
                'label' => $j->Label_Panjang ?: $j->Nama,
                'keterangan' => $j->Keterangan,
                'ikon' => $j->Ikon,
                'nada' => $j->Nada ?: 'ok',
                'lolos' => ($j->Flag_Lolos ?? 'T') === 'Y',
                'menutup' => (bool) $j->Hasil_Keputusan_Kode,
                'butuhAlasan' => ($j->Flag_Butuh_Alasan ?? 'T') === 'Y',
            ])->all(),
            // STATUS HASIL MCU — dari master, bukan array yang ditulis di layar.
            // Empat nilai baku ketenagakerjaan; `lolos` menerjemahkannya jadi
            // verdict aktivitas, `butuhCatatan` menandai yang tak berarti
            // apa-apa tanpa keterangannya.
            'mcuStatusOpsi' => self::masterMcuStatus()->values()->map(fn ($m) => [
                'kode' => $m->Kode,
                'nama' => $m->Nama,
                'label' => $m->Label_Panjang ?: $m->Nama,
                'keterangan' => $m->Keterangan,
                'ikon' => $m->Ikon,
                'nada' => $m->Nada ?: 'ok',
                'lolos' => ($m->Flag_Lolos ?? 'T') === 'Y',
                'butuhCatatan' => ($m->Flag_Butuh_Catatan ?? 'T') === 'Y',
            ])->all(),
            // BENTUK PELAKSANAAN JADWAL — dari master, bukan dua tombol yang
            // ditulis mati di layar. Tiap bentuk membawa sendiri field apa yang
            // wajib diisi untuknya, jadi modal tidak perlu tahu nama-namanya.
            'modeJadwal' => self::masterModeJadwal()->values()->map(fn ($m) => [
                'kode' => $m->Kode,
                'nama' => $m->Nama,
                'ikon' => $m->Ikon,
                'warna' => $m->Warna,
                'deskripsi' => $m->Deskripsi,
                'butuhTautan' => ($m->Flag_Butuh_Tautan ?? 'T') === 'Y',
                'butuhLokasi' => ($m->Flag_Butuh_Lokasi ?? 'T') === 'Y',
                'butuhKontak' => ($m->Flag_Butuh_Kontak ?? 'T') === 'Y',
                'labelKontak' => $m->Label_Kontak ?: 'Nomor yang dihubungi',
                'petunjukKontak' => $m->Petunjuk_Kontak,
                'kalimatUndangan' => $m->Kalimat_Undangan,
                'luring' => ($m->Flag_Luring ?? 'T') === 'Y',
                // WAKTUNYA BATAS AKHIR, bukan janji temu (mis. MCU mandiri):
                // jendela jadwal menanyakan "paling lambat", bukan tanggal & jam.
                'batasWaktu' => UndanganJadwal::berbatasWaktu($m),
                // Batas yang langsung terisi saat mode ini dipilih — rekruter
                // cukup menyimpan, tidak perlu memilih tanggal.
                'batasHari' => (int) ($m->Batas_Hari_Bawaan ?? 0) ?: null,
                // VENDOR: nama vendor + catatan cabang diketik tim (bukan Master
                // Lokasi); alamat & tautan Google Maps opsional.
                'butuhTempat' => UndanganJadwal::butuhTempat($m),
                'labelTempat' => $m->Label_Tempat ?? null,
                // Surat pengantar wajib dilampirkan (MCU vendor & mandiri).
                'butuhSurat' => UndanganJadwal::butuhSurat($m),
                'labelSurat' => UndanganJadwal::labelSurat($m),
                // Kandidat sendiri yang mengunggah hasilnya (MANDIRI).
                'unggahKandidat' => UndanganJadwal::unggahKandidat($m),
            ])->all(),
            // Kolom & tabel fitur jadwal 30-09-2026 sudah ada? Sebelum skripnya
            // dijalankan, alasan perubahan tidak dituntut dan instruksi tetap
            // berupa teks polos.
            'jadwalFitur' => [
                'jejak' => JejakJadwal::siap(),
                'instruksi' => UndanganJadwal::siapInstruksi(),
                'alasanMin' => JejakJadwal::ALASAN_MIN,
                // Kolom tempat vendor, tautan peta, dan surat pengantar.
                'vendor' => UndanganJadwal::siapVendor(),
                // Surat pengantar: hanya PDF, boleh lebih dari satu, dengan
                // batas JUMLAH ukuran seluruhnya.
                'suratMaksMb' => SuratJadwal::MAKS_TOTAL_MB,
                'suratFormat' => SuratJadwal::FORMAT,
                'suratMaksBerkas' => SuratJadwal::MAKS_BERKAS,
                // Batas sekali kirim ulang surel — sama dengan batas massal lain.
                'kirimUlangMaks' => self::BATAS_PUTUS_MASSAL,
            ],
        ]));
    }

    /** Berapa program per halaman di panel kiri (paginasi muncul bila lebih). */
    private const PROGRAM_PER_HALAMAN = 10;

    /**
     * BERAPA KANDIDAT BOLEH DIPUTUS DALAM SATU KALI KIRIM — DEMI REPUTASI SMTP.
     *
     * Bukan batas teknis. Satu keputusan hampir selalu menerbitkan satu email ke
     * kandidat, jadi menekan "Putuskan" sekali untuk 150 orang berarti 150 email
     * keluar dalam hitungan detik dari satu alamat pengirim yang sama, dengan
     * badan surat yang nyaris identik. Itulah pola yang dibaca penyedia surat
     * sebagai pengiriman massal mendadak, dan hukumannya jatuh pada seluruh
     * domain: surat berikutnya masuk folder spam, atau akunnya ditangguhkan.
     * Yang hilang bukan cuma gelombang itu — undangan wawancara dan tautan setel
     * ulang kata sandi ikut berhenti sampai penangguhannya dicabut.
     *
     * Lima adalah ambang yang dipilih sengaja: cukup untuk membereskan satu
     * panel kecil dalam satu tarikan, cukup kecil untuk menjaga laju kirim tetap
     * menyerupai kerja manusia. Menaikkannya menuntut pembatas laju yang
     * sesungguhnya di sisi antrean surat, bukan sekadar angka yang lebih besar
     * di sini.
     *
     * ANGKANYA HIDUP DI SATU TEMPAT. Layar membacanya sebagai prop
     * `batasPutusMassal`, aturan validasi memakainya lewat konstanta yang sama —
     * jadi tidak ada dua angka yang bisa berselisih, dan tombol yang tampak
     * boleh ditekan tidak akan ditolak diam-diam oleh server.
     */
    public const BATAS_PUTUS_MASSAL = 5;

    /** GET panel kiri — daftar program berjalan (paginasi + cari + filter jenis). */
    /**
     * GET /api/v1/karir/lamaran/{id}/email-hasil — TAHAP APA SAJA yang
     * emailnya bisa dikirim ulang untuk satu lamaran.
     *
     * Kirim ulang tanpa daftar ini berarti menebak. Kandidat yang emailnya
     * gagal terkirim sudah berpindah ke tahap berikutnya begitu keputusannya
     * jatuh — papan menunjukkannya di kolom baru — sementara yang perlu
     * dikirim ulang justru kabar tahap SEBELUMNYA. Satu tombol "kirim ulang"
     * tanpa pilihan akan selalu mengirimkan tahap yang salah.
     *
     * Yang disebut hanya tahap yang BENAR-BENAR pernah diputus: hanya itu
     * yang punya kabar untuk disampaikan.
     */
    public function emailHasilDaftar(string $id)
    {
        $realId = Hashids::decode($id)[0] ?? null;
        if (! $realId) {
            return ResponseHelper::error('Lamaran tidak valid.', 422);
        }

        $hasilMaster = \App\Support\Career\LamaranService::masterHasilKeputusan();

        // ALAMAT YANG BENAR-BENAR AKAN DIPAKAI, dihitung dari database.
        // Dikirim ke layar supaya admin melihat ke mana suratnya pergi SEBELUM
        // menekan kirim — bukan supaya layar yang menentukannya.
        $kepada = \App\Support\Career\LamaranService::emailKandidat(
            (int) DB::table('N_WEB_CAREERS_Lamaran')->where('Id_Lamaran', (int) $realId)->value('Id_Users'),
            (int) $realId,
        );

        $rows = DB::table('N_WEB_CAREERS_Lamaran_Tahap')
            ->where('Lamaran_Id', (int) $realId)
            ->whereNotNull('Diputus_At')
            ->orderByDesc('Urutan')
            ->get(['Id_Lamaran_Tahap', 'Urutan', 'Label', 'Hasil', 'Diputus_At', 'Diputus_By']);

        $daftar = $rows->map(function ($t) use ($hasilMaster) {
            $def = $hasilMaster->get((string) $t->Hasil);

            return [
                'tahapId' => Hashids::encode($t->Id_Lamaran_Tahap),
                'urutan' => (int) $t->Urutan,
                'label' => $t->Label,
                'hasil' => (string) $t->Hasil,
                'hasilNama' => $def->Nama ?? (string) $t->Hasil,
                'lolos' => ($def->Flag_Lolos ?? 'T') === 'Y',
                // Ada keputusan yang MEMANG tidak pernah dikabarkan lewat email:
                // Talent Pool, pengunduran diri, penolakan penawaran. Layar perlu
                // tahu supaya barisnya tidak menawarkan tombol yang akan ditolak.
                'kirimEmail' => ($def->Flag_Kirim_Email ?? 'T') === 'Y',
                'diputusAt' => (string) $t->Diputus_At,
                'diputusBy' => $t->Diputus_By,
            ];
        })->values()->all();

        return ResponseHelper::success(['tahap' => $daftar, 'email' => $kepada], 'Tahap yang emailnya bisa dikirim ulang');
    }

    /**
     * POST /api/v1/karir/lamaran/tahap/{id}/email-hasil — KIRIM ULANG email
     * keputusan satu tahap.
     *
     * Dipakai ketika pengirimannya gagal di percobaan pertama (server surat
     * menolak, kotak surat penuh, jam mesin melenceng). Keputusannya sendiri
     * sudah sah dan tidak boleh diambil ulang hanya demi memicu emailnya —
     * mengetuk palu dua kali meninggalkan dua jejak keputusan untuk satu
     * peristiwa, dan jejak itulah yang dibaca saat ada sengketa.
     *
     * TIDAK MENYENTUH KEPUTUSAN. Yang dikirim ulang adalah kabar atas
     * keputusan yang sudah tercatat, apa adanya.
     *
     * ── ALAMAT TUJUAN TIDAK DITERIMA DARI LAYAR ─────────────────────────
     *
     * Layar WAJIB mengirimkan alamat yang sedang ia tampilkan, tapi alamat
     * itu dipakai sebagai PEMERIKSA, bukan sebagai tujuan. Tujuannya selalu
     * dihitung ulang dari database.
     *
     * Dua bahaya yang ditutup sekaligus:
     *
     *   1. Permintaan bisa disusun sendiri. Sekali alamat tujuan diterima
     *      mentah dari klien, surat berisi keputusan seleksi orang lain bisa
     *      diarahkan ke mana saja oleh siapa pun yang punya akses admin.
     *   2. Layar bisa basi. Drawer yang masih memegang kandidat sebelumnya,
     *      atau data yang berubah di tab lain, akan mengirimkan kabar orang
     *      ini ke alamat orang itu — dan tak ada satu pun galat yang muncul.
     *
     * Kalau keduanya berselisih, pengiriman DIBATALKAN. Bukan "pakai yang
     * dari database diam-diam": selisih itu sendiri pertanda ada yang salah,
     * dan admin berhak tahu sebelum surat berangkat.
     */
    public function emailHasilUlang(Request $request, string $id)
    {
        $realId = Hashids::decode($id)[0] ?? null;
        if (! $realId) {
            return ResponseHelper::error('Tahap tidak valid.', 422);
        }

        $data = $request->validate([
            // Wajib ada — layar harus menyatakan alamat yang ia tampilkan supaya
            // ada yang bisa dicocokkan. Yang tidak menyertakannya bukan layar ini.
            'email' => 'required|email|max:200',
        ]);

        $t = DB::table('N_WEB_CAREERS_Lamaran_Tahap')
            ->where('Id_Lamaran_Tahap', (int) $realId)
            ->first(['Lamaran_Id', 'Urutan', 'Label', 'Hasil', 'Diputus_At']);

        // Tahap yang belum diputus tidak punya kabar untuk dikirim. Mengirimnya
        // berarti mengabarkan hasil yang belum ada.
        if (! $t || ! $t->Diputus_At) {
            return ResponseHelper::error('Tahap ini belum diputus — belum ada hasil yang bisa dikabarkan.', 422);
        }

        $def = \App\Support\Career\LamaranService::masterHasilKeputusan()->get((string) $t->Hasil);
        if (! $def) {
            return ResponseHelper::error('Hasil tahap ini tidak dikenali — email tidak dikirim.', 422);
        }

        // KEPUTUSAN YANG MEMANG TIDAK DIKABARKAN, TETAP TIDAK DIKABARKAN.
        //
        // Talent Pool bukan penolakan dan bukan kelulusan — ia catatan internal
        // bahwa kandidat disimpan untuk kesempatan lain; surat untuk itu justru
        // membuat kandidat mengira ia diterima. Pengunduran diri dan penolakan
        // penawaran pun tak perlu dibalas surat: kandidatlah yang memutuskan.
        //
        // Dibaca dari Master_Hasil_Keputusan.Flag_Kirim_Email — aturan yang sama
        // persis dengan yang dipakai putus(), bukan salinan kedua yang bisa
        // menyimpang. Pintu kirim ulang tidak boleh lebih longgar daripada pintu
        // keputusannya sendiri.
        if (($def->Flag_Kirim_Email ?? 'T') !== 'Y') {
            return ResponseHelper::error(
                "Keputusan \"{$def->Nama}\" memang tidak dikabarkan lewat email — tidak ada surat yang bisa dikirim ulang.",
                422,
            );
        }

        $kepada = \App\Support\Career\LamaranService::emailKandidat(
            (int) DB::table('N_WEB_CAREERS_Lamaran')->where('Id_Lamaran', (int) $t->Lamaran_Id)->value('Id_Users'),
            (int) $t->Lamaran_Id,
        );

        if (! $kepada) {
            return ResponseHelper::error(
                'Kandidat ini tidak punya alamat email — tidak di akunnya, tidak juga di jawaban formulirnya. '
                    .'Lengkapi dulu datanya, lalu kirim ulang.',
                422,
            );
        }

        // Dibandingkan tanpa peduli besar-kecil huruf dan spasi di tepi: itu
        // perbedaan yang tidak pernah berarti pada alamat email, dan menolak
        // karenanya cuma membuat admin mengira sistemnya rusak.
        $diLayar = mb_strtolower(trim($data['email']));
        if ($diLayar !== mb_strtolower($kepada)) {
            Log::channel('web_career')->warning(sprintf(
                '[EMAIL ULANG] DIBATALKAN — layar menyebut %s, data menyebut %s (tahap #%d, oleh %s).',
                $diLayar, $kepada, $realId, session('career_auth.nama', 'ADMIN')
            ));

            return ResponseHelper::error(
                "Alamat di layar ({$diLayar}) berbeda dengan data kandidat ini. "
                    .'Muat ulang halaman lalu coba lagi — email tidak dikirim.',
                409,
            );
        }

        $this->kirimEmailHasilTahap((int) $t->Lamaran_Id, ($def->Flag_Lolos ?? 'T') === 'Y', (int) $realId);

        Log::channel('web_career')->info(sprintf(
            '[EMAIL ULANG] tahap %d (%s) hasil %s lamaran #%d → %s, oleh %s.',
            $t->Urutan, $t->Label, $t->Hasil, $t->Lamaran_Id, $kepada, session('career_auth.nama', 'ADMIN')
        ));

        return ResponseHelper::success(
            ['tahap' => $t->Label, 'email' => $kepada],
            "Email hasil \"{$t->Label}\" diantrekan ulang ke {$kepada}.",
        );
    }

    public function worklistProgram(Request $request)
    {
        return ResponseHelper::success(
            $this->daftarProgram(
                max(1, (int) $request->query('page', 1)),
                self::PROGRAM_PER_HALAMAN,
                trim((string) $request->query('q', '')),
                (string) $request->query('jenis', '')
            ),
            'Daftar program'
        );
    }

    /** GET panel kanan — kolom (alur program) + kartu pelamar program tsb. */
    public function worklistDetail(string $id)
    {
        $realId = Hashids::decode($id)[0] ?? null;
        if (! $realId) {
            return ResponseHelper::error('Program tidak valid.', 422);
        }

        return ResponseHelper::success($this->detailProgram((int) $realId, self::kartuDiminta()), 'Detail program');
    }

    /**
     * `?lamaran=` — SATU KARTU saja, bukan seluruh papan.
     *
     * Layar memintanya sesudah aksi atas satu kandidat (catat hasil, jadwal,
     * hadir, putus, …). Dulu setiap aksi memuat ulang seluruh papan: ratusan
     * kartu dibangun ulang demi satu kandidat yang berubah — dikali puluhan
     * rekruter yang bekerja serempak. Kartunya dibangun lewat jalur yang SAMA
     * (kolom, gerbang PIC, papanPelamar), hanya disaring satu lamaran; lingkup
     * akses tetap berlaku karena saringannya menumpang di kueri yang sama.
     *
     * null = seluruh papan. Nilai yang tak terbaca = -1: tidak cocok dengan
     * lamaran mana pun, sehingga yang pulang kartu kosong — bukan seluruh papan.
     */
    private static function kartuDiminta(): ?int
    {
        $id = request()->query('lamaran');
        if ($id === null || $id === '') {
            return null;
        }

        return (int) (Hashids::decode((string) $id)[0] ?? 0) ?: -1;
    }

    // ══════════════════════════════════════════════════════════════════════
    //  WORKLIST BERBASIS JOB VACANCY (MPP)
    // ══════════════════════════════════════════════════════════════════════
    //
    //  KENAPA ADA CARA KEDUA, BUKAN MENGGANTI YANG PERTAMA
    //
    //  Panel kiri worklist selama ini berbasis PROGRAM. Program adalah wadah
    //  penyelenggaraan ("LOKER BULAN 6", "LOKER BULAN 8"), bukan pekerjaan yang
    //  sedang dicari. Satu MPP yang belum terpenuhi dibuka lagi di program
    //  berikutnya — dan di panel kiri lowongan yang SAMA muncul dua kali, di
    //  bawah dua nama program yang berbeda, masing-masing membawa sebagian
    //  pelamarnya. Untuk menjawab "sudah sampai mana pencarian STAFF ACCOUNTING
    //  EMI?" rekruter harus membuka dua papan lalu menjumlahkannya sendiri.
    //
    //  Berbasis MPP, lowongan itu satu baris dengan seluruh pelamarnya —
    //  lintas program, lintas batch.
    //
    //  Keduanya dipertahankan karena keduanya menjawab pertanyaan yang berbeda:
    //  program menjawab "bagaimana gelombang rekrutmen bulan ini berjalan",
    //  job vacancy menjawab "bagaimana pencarian posisi ini berjalan". Yang
    //  memilih adalah orang yang sedang bekerja, bukan kode ini.

    /** Berapa job vacancy per halaman di panel kiri (paginasi muncul bila lebih). */
    private const LOKER_PER_HALAMAN = 10;

    /**
     * GET panel kiri — daftar JOB VACANCY (MPP) yang sedang berjalan.
     */
    public function worklistLoker(Request $request)
    {
        return ResponseHelper::success(
            $this->daftarJobVacancy(
                max(1, (int) $request->query('page', 1)),
                self::LOKER_PER_HALAMAN,
                trim((string) $request->query('q', '')),
                (string) $request->query('jenis', '')
            ),
            'Daftar job vacancy'
        );
    }

    /**
     * GET panel kanan — papan seleksi satu job vacancy.
     *
     * Kuncinya lewat QUERY, bukan segmen URL: nomor MPP boleh memuat garis
     * miring (lihat pola di DetailMppLoker), dan garis miring di tengah path
     * akan dibaca router sebagai pemisah segmen — permintaannya tidak pernah
     * sampai ke sini, dan yang terlihat di layar cuma 404 tanpa sebab.
     */
    public function worklistLokerDetail(Request $request)
    {
        $kunci = trim((string) $request->query('kunci', ''));
        if ($kunci === '') {
            return ResponseHelper::error('Job vacancy tidak valid.', 422);
        }

        return ResponseHelper::success(
            $this->detailLoker($kunci, (string) $request->query('alur', ''), self::kartuDiminta()),
            'Detail job vacancy'
        );
    }

    /**
     * Kunci satu JOB VACANCY dari satu baris loker.
     *
     * Nomor MPP-nya bila ada. Loker tanpa nomor MPP TIDAK dibuang melainkan
     * berdiri sebagai job vacancy-nya sendiri: lowongan yang tidak terlihat
     * tidak akan pernah dikerjakan siapa pun, dan itu kegagalan yang jauh
     * lebih mahal daripada satu baris yang tampak sendirian di daftar.
     */
    public static function kunciLoker(object $x): string
    {
        $mpp = trim((string) ($x->Mpp_Ref ?? ''));

        return $mpp !== '' ? $mpp : 'POS-'.$x->Id_Program_Posisi;
    }

    /**
     * PANEL KIRI — job vacancy berjalan, paginasi.
     *
     * Dikelompokkan DI PHP, bukan lewat GROUP BY.
     *
     * Kuncinya bukan satu kolom melainkan "nomor MPP, atau id loker bila nomor
     * MPP kosong" — dan mengungkapkannya sebagai ekspresi SQL berarti ekspresi
     * yang sama harus diulang di setiap agregat, di setiap whereIn, dan di
     * klausa ORDER BY paginasinya. Himpunan yang dikelompokkan di sini adalah
     * "loker aktif pada program yang sedang berjalan" — puluhan baris, bukan
     * puluhan ribu — jadi harga membacanya sekaligus jauh lebih murah daripada
     * harga menduplikasi definisi kunci di lima tempat.
     *
     * @return array{data:array, page:int, perPage:int, total:int, totalPage:int}
     */
    private function daftarJobVacancy(int $page, int $perPage, string $q, string $jenis): array
    {
        $base = DB::table('N_WEB_CAREERS_Program_Posisi as x')
            ->join('N_WEB_CAREERS_Program as p', 'p.Id_Program', '=', 'x.Program_Id')
            ->where('p.Status', 'BERJALAN')
            ->where('x.Flag_Aktif', 'Y');

        // BATAS KATEGORI — dipasang di kueri, sama seperti daftarProgram().
        // Chip hanya rupa; `?jenis=` tetap bisa dikarang sendiri.
        AksesService::saringKategori($base, self::PAGE, 'p.Kategori');

        // Dicari di tiga kolom sekaligus. Yang diingat orang tentang sebuah
        // lowongan berbeda-beda: nama posisinya, nomor MPP-nya yang tertera di
        // persetujuan, atau departemen yang memintanya.
        if ($q !== '') {
            $base->where(fn ($w) => $w
                ->where('x.Posisi', 'like', "%{$q}%")
                ->orWhere('x.Mpp_Ref', 'like', "%{$q}%")
                ->orWhere('x.Departemen', 'like', "%{$q}%"));
        }

        $jenis = AksesService::kategoriDiminta(self::PAGE, $jenis);
        if ($jenis !== '') {
            $base->where('p.Kategori', $jenis);
        }

        // TERBARU DI ATAS — id loker menurun. Karena baris sudah urut, kemunculan
        // PERTAMA tiap kunci adalah lokernya yang paling baru, dan urutan
        // penyisipan ke $grup langsung jadi urutan tampil yang benar.
        $rows = $base->orderByDesc('x.Id_Program_Posisi')
            ->select('x.*', 'p.Nama as ProgramNama', 'p.Kategori', 'p.Warna')
            ->get();

        // NULL = lingkup SEMUA, tanpa batas. Larik KOSONG berbeda artinya:
        // "dibatasi, dan tidak ada yang cocok".
        $picBoleh = AksesService::picDiizinkan(self::PAGE);
        $milikSaya = fn ($x) => $picBoleh === null
            || in_array((string) $x->Pic_Kode_Karyawan, $picBoleh, true);

        // Pelamar per LOKER. Disaring belakangan di PHP menurut loker mana yang
        // memang di tangan akun ini — supaya angka di kartu tidak pernah lebih
        // besar daripada isi papan yang akan dibukanya.
        $hitung = $rows->isEmpty() ? collect() : DB::table('N_WEB_CAREERS_Lamaran')
            ->whereIn('Program_Posisi_Id', $rows->pluck('Id_Program_Posisi')->all())
            ->select('Program_Posisi_Id',
                DB::raw('COUNT(*) as total'),
                DB::raw("SUM(CASE WHEN Status NOT IN ('GUGUR','TALENT_POOL') THEN 1 ELSE 0 END) as aktif"),
                DB::raw("SUM(CASE WHEN Status = 'LULUS' THEN 1 ELSE 0 END) as lolos"))
            ->groupBy('Program_Posisi_Id')
            ->get()
            ->keyBy('Program_Posisi_Id');

        $grup = [];
        foreach ($rows as $x) {
            $kunci = self::kunciLoker($x);
            $punya = $milikSaya($x);
            $h = $hitung[$x->Id_Program_Posisi] ?? null;

            $grup[$kunci] ??= [
                'id' => $kunci,
                'kunci' => $kunci,
                'mppRef' => trim((string) ($x->Mpp_Ref ?? '')) ?: null,
                'posisi' => $x->Posisi,
                'departemen' => $x->Departemen,
                'lokasi' => $x->Lokasi,
                'level' => $x->Level,
                'kategori' => $x->Kategori,
                'warna' => $x->Warna,
                'kuota' => 0,
                'terisi' => 0,
                'program' => [],
                'lokerSaya' => 0,
                'lokerTotal' => 0,
                'pelamar' => 0,
                'aktif' => 0,
                'lolos' => 0,
            ];

            $grup[$kunci]['kuota'] += (int) ($x->Kuota ?? 0);
            $grup[$kunci]['terisi'] += (int) ($x->Terisi ?? 0);
            $grup[$kunci]['lokerTotal']++;
            if (! in_array($x->ProgramNama, $grup[$kunci]['program'], true)) {
                $grup[$kunci]['program'][] = $x->ProgramNama;
            }

            if (! $punya) {
                continue;
            }

            $grup[$kunci]['lokerSaya']++;
            $grup[$kunci]['pelamar'] += (int) ($h->total ?? 0);
            $grup[$kunci]['aktif'] += (int) ($h->aktif ?? 0);
            $grup[$kunci]['lolos'] += (int) ($h->lolos ?? 0);
        }

        $semua = array_values($grup);
        $total = count($semua);

        return [
            'data' => array_slice($semua, ($page - 1) * $perPage, $perPage),
            'page' => $page,
            'perPage' => $perPage,
            'total' => $total,
            'totalPage' => (int) ceil($total / max(1, $perPage)),
        ];
    }

    /**
     * PANEL KANAN — papan seleksi satu job vacancy, lintas program.
     *
     * ── KENAPA ALUR JADI PENYARING, BUKAN GABUNGAN ────────────────────────
     *
     * Satu MPP yang dibuka ulang di program berikutnya hampir selalu memakai
     * alur yang sudah diperbarui. Menggabungkan seluruh alurnya jadi satu papan
     * — yang dilakukan mode program, dan memang benar di sana — menghasilkan
     * deretan kolom milik dua rombongan yang tidak pernah bertemu: rekruter
     * membaca papan berisi belasan kolom yang separuhnya selalu kosong, dan
     * tidak satu pun kolom menjawab "gelombang yang sekarang sampai di mana".
     *
     * Karena itu di sini alur dipilih SATU, bawaannya yang terbaru, dan sisanya
     * tetap bisa dibuka lewat penyaring di sebelah pemilih tampilan. Gabungan
     * seluruh alur tetap tersedia sebagai pilihan — kadang memang itu yang
     * dicari — tapi ia bukan bawaan, karena bukan pertanyaan sehari-hari.
     *
     * @param  string  $kunci      nomor MPP, atau 'POS-{id}' untuk loker tanpa MPP
     * @param  string  $alurMinta  id alur, 'SEMUA', atau '' (pakai bawaan)
     */
    private function detailLoker(string $kunci, string $alurMinta, ?int $hanya = null): array
    {
        $kosong = ['program' => null, 'loker' => null, 'alurOpsi' => [], 'alurAktif' => null, 'posisi' => [], 'kolom' => [], 'pelamar' => []];

        $base = DB::table('N_WEB_CAREERS_Program_Posisi as x')
            ->join('N_WEB_CAREERS_Program as p', 'p.Id_Program', '=', 'x.Program_Id')
            ->where('p.Status', 'BERJALAN')
            ->where('x.Flag_Aktif', 'Y');

        if (str_starts_with($kunci, 'POS-')) {
            $base->where('x.Id_Program_Posisi', (int) substr($kunci, 4));
        } else {
            $base->where('x.Mpp_Ref', $kunci);
        }

        $lokers = $base
            ->orderByDesc('x.Id_Program_Posisi')
            ->select('x.*', 'p.Id_Program', 'p.Nama as ProgramNama', 'p.Kategori', 'p.Alur_Kode')
            ->get();

        if ($lokers->isEmpty()) {
            return $kosong;
        }

        // Menyaring daftar di panel kiri saja belum menutup apa pun: kunci job
        // vacancy ada di URL, dan panel kanan inilah yang memuat seluruh pelamar
        // berikut nilainya. Di luar jatah kategori dijawab sama seperti yang
        // tidak ada — tidak membocorkan bahwa ia ada.
        $izin = AksesService::kategoriDiizinkan(self::PAGE);
        if ($izin) {
            $lokers = $lokers->filter(fn ($x) => in_array($x->Kategori, $izin, true))->values();
            if ($lokers->isEmpty()) {
                Log::channel('web_career')->warning(
                    'Akses ditolak: user #'.session('career_auth.id')." membuka job vacancy {$kunci} di luar jatah kategorinya."
                );

                return $kosong;
            }
        }

        $lokerIds = $lokers->pluck('Id_Program_Posisi')->all();
        $wakil = $lokers->first();

        // ── ALUR MANA SAJA YANG HIDUP DI LOWONGAN INI ───────────────────────
        //
        // Dua sumber, dan keduanya perlu:
        //   · alur yang BENAR-BENAR dijalani pelamarnya (dibekukan per lamaran)
        //   · alur yang sekarang menempel di programnya — supaya lowongan yang
        //     belum punya satu pelamar pun tetap menggambarkan tahapannya, bukan
        //     papan tanpa kolom yang terbaca seperti alurnya belum disetel.
        $alurPelamar = DB::table('N_WEB_CAREERS_Lamaran')
            ->whereIn('Program_Posisi_Id', $lokerIds)
            ->whereNotNull('Master_Alur_Id')
            ->select('Master_Alur_Id', DB::raw('COUNT(*) as J'))
            ->groupBy('Master_Alur_Id')
            ->pluck('J', 'Master_Alur_Id');

        $alurProgram = DB::table('N_WEB_CAREERS_Master_Alur')
            ->whereIn('Kode', $lokers->pluck('Alur_Kode')->filter()->unique()->all() ?: ['__tidak_ada__'])
            ->pluck('Id_Master_Alur');

        $semuaAlurId = collect($alurPelamar->keys())
            ->merge($alurProgram)
            ->map(fn ($v) => (int) $v)
            ->unique()
            ->values();

        // TERBARU DI ATAS — dan itu pula yang jadi bawaan.
        //
        // NOMOR VERSI ikut disebut. Menyunting alur yang sedang dipakai
        // melahirkan versi baru yang MEWARISI NAMANYA — jadi tanpa nomor itu
        // penyaring ini menawarkan dua baris berbunyi persis sama, dan yang
        // memilih tidak punya cara tahu mana rombongan yang mana.
        $punyaVersi = \App\Support\Career\VersiAlur::siap();

        $alurOpsi = $semuaAlurId->isEmpty() ? [] : DB::table('N_WEB_CAREERS_Master_Alur')
            ->whereIn('Id_Master_Alur', $semuaAlurId->all())
            ->orderByDesc('Created_At')
            ->orderByDesc('Id_Master_Alur')
            ->get(array_merge(['Id_Master_Alur', 'Kode', 'Nama'], $punyaVersi ? ['Versi', 'Induk_Id'] : []))
            ->map(fn ($a) => [
                'id' => (int) $a->Id_Master_Alur,
                'kode' => $a->Kode,
                'nama' => $a->Nama,
                'versi' => $punyaVersi ? (int) ($a->Versi ?: 1) : null,
                'pelamar' => (int) ($alurPelamar[$a->Id_Master_Alur] ?? 0),
            ])->values()->all();

        // Nomor versi hanya BERARTI bila memang ada lebih dari satu versi dari
        // keluarga yang sama di papan ini. Menempelkan "v1" pada alur yang tidak
        // pernah diversikan cuma menambah istilah tanpa menjawab apa pun.
        $adaBeberapaVersi = collect($alurOpsi)->pluck('versi')->filter()->unique()->count() > 1;
        if (! $adaBeberapaVersi) {
            $alurOpsi = array_map(fn ($a) => ['versi' => null] + $a, $alurOpsi);
        }

        // ── ALUR YANG DIPAKAI PAPAN ─────────────────────────────────────────
        //
        // Bawaannya alur terbaru YANG SUDAH PUNYA PELAMAR, bukan sekadar yang
        // paling baru dibuat. Program yang baru diarahkan ke alur baru belum
        // punya satu kandidat pun di sana; membuka papannya di alur itu berarti
        // menyambut rekruter dengan papan kosong padahal pekerjaannya menumpuk
        // satu pilihan di sebelahnya. Bila memang belum ada pelamar sama sekali,
        // barulah alur terbaru dipakai — di situ papan kosong memang jujur.
        $alurAktif = null;
        if ($alurMinta !== 'SEMUA') {
            $diminta = (int) $alurMinta;
            $sah = collect($alurOpsi)->firstWhere('id', $diminta);
            $alurAktif = $sah
                ? $diminta
                : (collect($alurOpsi)->first(fn ($a) => $a['pelamar'] > 0)['id'] ?? ($alurOpsi[0]['id'] ?? null));
        }

        $kolom = $alurAktif
            ? AlurKolom::susun([$alurAktif], $alurAktif)
            : AlurKolom::susun($semuaAlurId->all());

        // ── GERBANG PIC LOKER ───────────────────────────────────────────────
        // Sama seperti detailProgram: kandidat menempel ke LOKER, bukan ke
        // wadah yang menaunginya.
        $picBoleh = AksesService::picDiizinkan(self::PAGE);

        $lamaran = DB::table('N_WEB_CAREERS_Lamaran as l')
            ->leftJoin('N_WEB_CAREERS_Users as u', 'u.Id_Users', '=', 'l.Id_Users')
            ->leftJoin('N_WEB_CAREERS_Program_Posisi as x', 'x.Id_Program_Posisi', '=', 'l.Program_Posisi_Id')
            ->when($picBoleh !== null, fn ($w) => $w->whereIn('x.Pic_Kode_Karyawan', $picBoleh ?: ['__tidak_ada__']))
            ->whereIn('l.Program_Posisi_Id', $lokerIds)
            // Penyaring alur. Tanpa ini papan satu alur akan tetap memuat
            // kandidat alur lain, lalu melemparkan mereka ke kolom cadangan
            // "di luar alur" — terbaca seperti data rusak, padahal cuma
            // rombongan sebelumnya yang memang tidak sedang dilihat.
            ->when($alurAktif !== null, fn ($w) => $w->where('l.Master_Alur_Id', $alurAktif))
            ->when($hanya !== null, fn ($w) => $w->where('l.Id_Lamaran', $hanya))
            ->orderByDesc('l.Id_Lamaran')
            ->select('l.*', 'u.Nama as Pelamar', 'u.Email as Email', 'u.No_Hp as NoHp',
                'x.Posisi', 'x.Departemen', 'x.Lokasi', 'x.Level', 'x.Mpp_Ref')
            ->get();

        $papan = $this->papanPelamar($lamaran, $kolom);
        $pelamar = $papan['pelamar'];

        // Satu kartu saja — lihat kartuDiminta().
        if ($hanya !== null) {
            return ['pelamar' => $pelamar, 'kampusBendera' => $this->benderaKampus($papan['kampusPer']->values()->all())];
        }

        // Loker penyusun job vacancy ini + jumlah pelamarnya. Label programnya
        // ikut disebut: di sini dua baris bisa sama-sama bernama "STAFF
        // ACCOUNTING EMI", dan yang membedakannya justru program tempat ia
        // dibuka — persis hal yang membuat mode program membingungkan, dan yang
        // harus tetap bisa dilihat begitu seseorang perlu memisahkannya lagi.
        $rekap = collect($pelamar)->groupBy('posisiId');

        // ── SLA MPP UNTUK LOKER YANG TAMPIL ───────────────────────────────
        //
        // Diambil SEKALI untuk seluruh loker, bukan per baris: satu papan bisa
        // memuat belasan loker, dan menanyakannya satu per satu berarti
        // belasan kueri untuk data yang sama.
        //
        // Rekruter yang membuka worklist perlu tahu sisa waktunya DI SITU —
        // tenggat yang hanya terlihat di halaman MPP tidak menolong orang yang
        // sedang memutuskan kandidat.
        $slaMpp = self::slaPerMpp($lokers->pluck('Mpp_Ref')->filter()->unique()->values()->all());

        $posisi = $lokers->map(function ($x) use ($rekap, $slaMpp) {
            $isi = $rekap->get(Hashids::encode($x->Id_Program_Posisi), collect());

            return [
                'id' => Hashids::encode($x->Id_Program_Posisi),
                'posisi' => $x->Posisi.' · '.$x->ProgramNama,
                'level' => $x->Level ?? null,
                'departemen' => $x->Departemen ?? null,
                'lokasi' => $x->Lokasi ?? null,
                'mppRef' => $x->Mpp_Ref ?? null,
                'kuota' => (int) ($x->Kuota ?? 0),
                'status' => $x->Status ?? null,
                'pelamar' => $isi->count(),
                'berjalan' => $isi->where('statusLamaran', 'BERJALAN')->count(),
                'lolos' => $isi->where('statusLamaran', 'LULUS')->count(),
                'gugur' => $isi->where('statusLamaran', 'GUGUR')->count(),
                // Tenggat pemenuhan MPP + sisa hari kerjanya. null bila loker
                // ini tidak terikat MPP, atau MPP-nya belum punya SLA.
                'sla' => $slaMpp[trim((string) ($x->Mpp_Ref ?? ''))] ?? null,
            ];
        })->values();

        $namaAlurAktif = $alurAktif
            ? (collect($alurOpsi)->firstWhere('id', $alurAktif)['nama'] ?? null)
            : 'Semua alur';

        return [
            // Bentuk `program` DIPERTAHANKAN supaya seluruh papan, drawer, dan
            // penyaring di layar tetap membaca kunci yang sama seperti mode
            // program. Yang berubah cuma apa yang mengisinya: di sini judulnya
            // pekerjaan yang dicari, bukan gelombang penyelenggaraannya.
            'program' => [
                'id' => $kunci,
                'nama' => $wakil->Posisi,
                'kategori' => $wakil->Kategori,
                'alur' => $namaAlurAktif,
            ],
            // Rincian job vacancy — dipakai kepala panel kanan.
            'loker' => [
                'kunci' => $kunci,
                'mppRef' => trim((string) ($wakil->Mpp_Ref ?? '')) ?: null,
                'posisi' => $wakil->Posisi,
                'departemen' => $wakil->Departemen,
                'lokasi' => $wakil->Lokasi,
                'level' => $wakil->Level,
                'kuota' => (int) $lokers->sum(fn ($x) => (int) ($x->Kuota ?? 0)),
                'terisi' => (int) $lokers->sum(fn ($x) => (int) ($x->Terisi ?? 0)),
                'program' => $lokers->pluck('ProgramNama')->unique()->values()->all(),
                // Tenggat pemenuhan MPP + sisa hari kerjanya. Ditaruh di kepala
                // panel supaya rekruter melihatnya SEBELUM memutuskan kandidat,
                // bukan sesudah — tenggat yang cuma ada di halaman MPP tidak
                // menolong orang yang sedang bekerja di worklist.
                'sla' => $slaMpp[trim((string) ($wakil->Mpp_Ref ?? ''))] ?? null,
            ],
            'alurOpsi' => $alurOpsi,
            'alurAktif' => $alurAktif,
            'posisi' => $posisi,
            'kolom' => $papan['kolom'],
            'pelamar' => $pelamar,
            'kampusBendera' => $this->benderaKampus($papan['kampusPer']->values()->all()),
        ];
    }

    /** Tab filter jenis = Master Talent Acquisition aktif. */
    private function talentTabs(): array
    {
        // Hanya kategori yang memang boleh dibuka pengguna ini. Chip untuk
        // kategori yang tak ia pegang bukan sekadar mubazir — ia menjanjikan
        // isi yang, begitu ditekan, tidak pernah ada.
        return AksesService::tabKategori(self::PAGE);
    }

    /**
     * PANEL KIRI — program berjalan, paginasi.
     *
     * @return array{data:array, page:int, perPage:int, total:int, totalPage:int}
     */
    private function daftarProgram(int $page, int $perPage, string $q, string $jenis): array
    {
        $base = DB::table('N_WEB_CAREERS_Program as p')
            ->leftJoin('N_WEB_CAREERS_Master_Alur as a', 'a.Kode', '=', 'p.Alur_Kode')
            ->where('p.Status', 'BERJALAN');

        // BATAS KATEGORI — dipasang di kueri, bukan cuma di chip.
        // Chip hanya rupa; `?jenis=` tetap bisa dikarang sendiri, dan tanpa baris
        // ini admin yang dijatah satu kategori tetap bisa menarik daftar program
        // kategori lain — berikut seluruh pelamarnya lewat panel kanan.
        AksesService::saringKategori($base, self::PAGE, 'p.Kategori');

        if ($q !== '') {
            $base->where('p.Nama', 'like', "%{$q}%");
        }
        // Yang diminta disaring dulu terhadap izin: di luar jatahnya, permintaan
        // itu jatuh kembali ke "semua yang boleh", bukan jadi daftar kosong yang
        // membingungkan.
        $jenis = AksesService::kategoriDiminta(self::PAGE, $jenis);
        if ($jenis !== '') {
            $base->where('p.Kategori', $jenis);
        }

        $total = (clone $base)->count();

        // TERBARU DI ATAS.
        //
        // Diurutkan menurut nama, panel kiri worklist menaruh program yang
        // dibuka tahun lalu di atas program yang dibuka pekan ini semata-mata
        // karena namanya berawalan 'A'. Yang dikerjakan admin tiap hari justru
        // program terbaru, dan ia harus memaginasinya dulu untuk sampai ke
        // sana. Id_Program jadi pemutus supaya dua program yang dibuat pada
        // detik yang sama tidak bertukar tempat tiap kali halaman dimuat —
        // urutan yang goyah membuat paginasi melewatkan atau menggandakan baris.
        $rows = $base->orderByDesc('p.Created_At')->orderByDesc('p.Id_Program')
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->select('p.*', 'a.Nama as AlurNama')
            ->get();

        // ── HITUNGAN KARTU IKUT GERBANG PIC ─────────────────────────────
        //
        // Dulu baris ini menghitung SELURUH pelamar program, sementara papan
        // di sebelah kanan menyaringnya menurut loker yang dipegang akun ini.
        // Akibatnya kartu berbunyi "2 aktif · 2 total" lalu papannya kosong —
        // dan yang membaca mengira papannya gagal memuat, bukan mengira
        // lokernya memang bukan miliknya lagi.
        //
        // Aturannya kini satu, sama persis dengan worklistDetail(): NULL =
        // tanpa batas; larik kosong = dibatasi dan tidak ada yang cocok, jadi
        // memang harus nol.
        $picBoleh = AksesService::picDiizinkan(self::PAGE);

        $hitung = DB::table('N_WEB_CAREERS_Lamaran as l')
            ->leftJoin('N_WEB_CAREERS_Program_Posisi as x', 'x.Id_Program_Posisi', '=', 'l.Program_Posisi_Id')
            ->when($picBoleh !== null, fn ($w) => $w->whereIn('x.Pic_Kode_Karyawan', $picBoleh ?: ['__tidak_ada__']))
            ->select('l.Program_Id',
                DB::raw('COUNT(*) as total'),
                DB::raw("SUM(CASE WHEN l.Status NOT IN ('GUGUR','TALENT_POOL') THEN 1 ELSE 0 END) as aktif"),
                DB::raw("SUM(CASE WHEN l.Status = 'LULUS' THEN 1 ELSE 0 END) as lolos"))
            ->groupBy('l.Program_Id')
            ->get()
            ->keyBy('Program_Id');

        // ── LOKER YANG MEMANG DI TANGAN AKUN INI ────────────────────────
        //
        // Dipakai kartu untuk membedakan dua keadaan yang di layar tampak
        // sama-sama nol: "belum ada yang melamar" dan "lokernya sudah
        // diserahterimakan". Yang pertama menunggu; yang kedua tidak akan
        // pernah berubah sampai lokernya dikembalikan.
        $lokerSaya = DB::table('N_WEB_CAREERS_Program_Posisi')
            ->when($picBoleh !== null, fn ($w) => $w->whereIn('Pic_Kode_Karyawan', $picBoleh ?: ['__tidak_ada__']))
            ->select('Program_Id', DB::raw('COUNT(*) as c'))
            ->groupBy('Program_Id')
            ->pluck('c', 'Program_Id');

        // Tanpa batas PIC keduanya kueri yang sama persis — tidak diulang.
        $lokerSemua = $picBoleh === null ? $lokerSaya : DB::table('N_WEB_CAREERS_Program_Posisi')
            ->select('Program_Id', DB::raw('COUNT(*) as c'))
            ->groupBy('Program_Id')
            ->pluck('c', 'Program_Id');

        // Jumlah tahap per alur (untuk keterangan kartu).
        $jmlTahap = DB::table('N_WEB_CAREERS_Master_Alur_Tahap as t')
            ->join('N_WEB_CAREERS_Master_Alur as a', 'a.Id_Master_Alur', '=', 't.Master_Alur_Id')
            ->select('a.Kode', DB::raw('COUNT(*) as c'))
            ->groupBy('a.Kode')
            ->pluck('c', 'Kode');

        $data = $rows->map(fn ($p) => [
            'id' => Hashids::encode($p->Id_Program),
            'kode' => $p->Kode,
            'nama' => $p->Nama,
            'kategori' => $p->Kategori,
            'warna' => $p->Warna,
            'penyelenggara' => $p->Penyelenggara ?: 'EVO Group',
            'alur' => $p->AlurNama,
            'jumlahTahap' => (int) ($jmlTahap[$p->Alur_Kode] ?? 0),
            'pelamar' => (int) ($hitung[$p->Id_Program]->total ?? 0),
            'aktif' => (int) ($hitung[$p->Id_Program]->aktif ?? 0),
            'lolos' => (int) ($hitung[$p->Id_Program]->lolos ?? 0),
            // Berapa loker program ini yang dipegang akun ini, dari berapa
            // seluruhnya. 0 dari sekian = seluruhnya sudah pindah tangan.
            'lokerSaya' => (int) ($lokerSaya[$p->Id_Program] ?? 0),
            'lokerTotal' => (int) ($lokerSemua[$p->Id_Program] ?? 0),
        ])->all();

        return [
            'data' => $data,
            'page' => $page,
            'perPage' => $perPage,
            'total' => $total,
            'totalPage' => (int) ceil($total / $perPage),
        ];
    }

    /**
     * PANEL KANAN — kolom kanban = TAHAP ALUR program ini (bukan tipe generik),
     * dan kartu pelamar ditempatkan pada tahap posisinya:
     *  - BERJALAN → tahap yang sedang berjalan
     *  - GUGUR    → tahap tempat ia gugur (tetap "di loop" tahap itu)
     *  - LULUS    → tahap terakhir
     */
    private function detailProgram(int $programId, ?int $hanya = null): array
    {
        $program = DB::table('N_WEB_CAREERS_Program')->where('Id_Program', $programId)->first();
        if (! $program) {
            return ['program' => null, 'kolom' => [], 'pelamar' => []];
        }

        // Menyaring daftar di panel kiri saja belum menutup apa pun: id program
        // ada di URL, dan panel kanan inilah yang memuat seluruh pelamar berikut
        // nilai serta keputusannya. Program di luar jatah kategori dijawab sama
        // seperti program yang tidak ada — tidak membocorkan bahwa ia ada.
        $izin = AksesService::kategoriDiizinkan(self::PAGE);
        if ($izin && ! in_array($program->Kategori, $izin, true)) {
            Log::channel('web_career')->warning(
                'Akses ditolak: user #'.session('career_auth.id')." membuka program {$program->Kode} "
                ."(kategori {$program->Kategori}) di luar jatahnya."
            );

            return ['program' => null, 'kolom' => [], 'pelamar' => []];
        }

        $alur = DB::table('N_WEB_CAREERS_Master_Alur')->where('Kode', $program->Alur_Kode)->first();
        $alurProgramId = $alur ? (int) $alur->Id_Master_Alur : null;

        // ── KOLOM = ALUR YANG BENAR-BENAR DIPAKAI, bukan penunjuk di program ──
        //
        // Dulu kolom disusun dari alur yang SEKARANG menempel di program, lalu
        // kartu ditempatkan memakai nomor urut tahapnya. Dua-duanya rapuh:
        // mengarahkan program ke alur baru membuat kandidat lama tergambar di
        // kolom yang bukan miliknya, dan menyunting alur di tempat (Id sama,
        // arti berbeda) melakukan hal yang sama tanpa satu galat pun.
        //
        // Papannya yang berbohong, bukan datanya — dan itu lebih berbahaya,
        // karena keputusan diambil dari papan. Lihat App\Support\Career\AlurKolom.
        $kolom = AlurKolom::susun(
            AlurKolom::alurDipakai($programId, $alurProgramId),
            $alurProgramId,
        );

        // ── GERBANG PIC LOKER ───────────────────────────────────────────────
        //
        // Kandidat menempel ke LOKER, bukan ke program. Karena itu penyaringnya
        // di sini, bukan di daftar program: seorang rekruter bisa saja memegang
        // satu dari tiga loker sebuah program — ia berhak melihat programnya,
        // tapi hanya kandidat lokernya sendiri.
        //
        // NULL = lingkup SEMUA, tanpa batas. Larik KOSONG berbeda artinya:
        // "dibatasi, dan tidak ada yang cocok" — dan itu memang harus
        // memulangkan nol baris, bukan seluruhnya.
        $picBoleh = AksesService::picDiizinkan(self::PAGE);

        // Pelamar program ini + tahap-tahapnya.
        $lamaran = DB::table('N_WEB_CAREERS_Lamaran as l')
            ->leftJoin('N_WEB_CAREERS_Users as u', 'u.Id_Users', '=', 'l.Id_Users')
            ->leftJoin('N_WEB_CAREERS_Program_Posisi as x', 'x.Id_Program_Posisi', '=', 'l.Program_Posisi_Id')
            ->when($picBoleh !== null, fn ($w) => $w->whereIn('x.Pic_Kode_Karyawan', $picBoleh ?: ['__tidak_ada__']))
            ->where('l.Program_Id', $programId)
            ->when($hanya !== null, fn ($w) => $w->where('l.Id_Lamaran', $hanya))
            ->orderByDesc('l.Id_Lamaran')
            // Rincian lowongan ikut dibawa: worklist perlu menyaring & menampilkan
            // departemen/lokasi/MPP di kartu, sama seperti di halaman lowongan.
            ->select('l.*', 'u.Nama as Pelamar', 'u.Email as Email', 'u.No_Hp as NoHp',
                'x.Posisi', 'x.Departemen', 'x.Lokasi', 'x.Level', 'x.Mpp_Ref')
            ->get();

        // Sisa penyusunan papan TIDAK bergantung pada dari mana lamaran ini
        // dikumpulkan — lihat papanPelamar().
        $papan = $this->papanPelamar($lamaran, $kolom);
        $kolom = $papan['kolom'];
        $pelamar = $papan['pelamar'];
        $kampusPer = $papan['kampusPer'];

        // Satu kartu saja (lihat kartuDiminta): kolom, jadwal kolom, dan rekap
        // posisi milik SELURUH papan — layar tetap memakai yang sudah ada.
        if ($hanya !== null) {
            return ['pelamar' => $pelamar, 'kampusBendera' => $this->benderaKampus($kampusPer->values()->all())];
        }

        // BATAS PENGISIAN PER KOLOM — hanya di papan PROGRAM: tanggalnya milik
        // (program × kode tahap). Papan per-MPP bisa memuat beberapa program,
        // jadi di sana yang tersedia hanya perubahan per kandidat.
        if (BatasIsi::siap()) {
            $jadwalProgram = DB::table(BatasIsi::T_PROGRAM)->where('Program_Id', $programId)->get(['Tahap_Kode', 'Buka_At', 'Batas_At'])->keyBy('Tahap_Kode');
            // Kolom berisi kandidat yang sedang menjalani / menunggu jadwal —
            // `batas` kartu hanya terisi bila jadwal berlaku baginya. Yang sudah
            // mengirim tidak dihitung (sama dengan BatasIsi::adaKandidatBerjadwal).
            $kolomBerjadwal = collect($pelamar)
                ->filter(fn ($r) => ! empty($r['batas']) && empty($r['batas']['terkirim']))
                ->pluck('kolomKode')->flip();
            foreach ($kolom as &$k) {
                // DITENTUKAN MASTER ALUR, bukan posisi tahap (keputusan user):
                // tahap Formulir mana pun — termasuk tahap 1 — yang diberi jadwal.
                $j = $jadwalProgram->get($k['kode']);
                $k['bolehBatas'] = BatasIsi::kolomBolehDijadwal($k, (bool) $j, $kolomBerjadwal->has($k['kode']));
                $k['bukaProgram'] = $j && $j->Buka_At ? (string) $j->Buka_At : null;
                $k['batasProgram'] = $j ? (string) $j->Batas_At : null;
            }
            unset($k);
        }

        // Lowongan/posisi program ini + jumlah pelamarnya — dipakai penyaring
        // worklist dan kartu ringkas, sepola dengan tampilan di landing page.
        $rekap = collect($pelamar)->groupBy('posisiId');
        $posisi = DB::table('N_WEB_CAREERS_Program_Posisi')
            ->where('Program_Id', $programId)
            ->orderBy('Id_Program_Posisi')
            ->get()
            ->map(function ($x) use ($rekap) {
                $isi = $rekap->get(Hashids::encode($x->Id_Program_Posisi), collect());

                return [
                    'id' => Hashids::encode($x->Id_Program_Posisi),
                    'posisi' => $x->Posisi,
                    'level' => $x->Level ?? null,
                    'departemen' => $x->Departemen ?? null,
                    'lokasi' => $x->Lokasi ?? null,
                    'mppRef' => $x->Mpp_Ref ?? null,
                    'kuota' => (int) ($x->Kuota ?? 0),
                    'status' => $x->Status ?? null,
                    // Angka yang paling sering ditanya HR: berapa yang masih
                    // jalan, berapa diterima, berapa gugur — per lowongan.
                    'pelamar' => $isi->count(),
                    'berjalan' => $isi->where('statusLamaran', 'BERJALAN')->count(),
                    'lolos' => $isi->where('statusLamaran', 'LULUS')->count(),
                    'gugur' => $isi->where('statusLamaran', 'GUGUR')->count(),
                ];
            })->values();

        return [
            'program' => [
                'id' => Hashids::encode($program->Id_Program),
                'nama' => $program->Nama,
                'kategori' => $program->Kategori,
                'alur' => $alur->Nama ?? null,
            ],
            'posisi' => $posisi,
            'kolom' => $kolom,
            'pelamar' => $pelamar,
            // Bendera per NAMA kampus, untuk penyaring "Kampus" di worklist —
            // bentuk yang sama dengan pemilih kampus di formulir pendaftaran.
            'kampusBendera' => $this->benderaKampus($kampusPer->values()->all()),
        ];
    }

    /**
     * BAGIAN PAPAN YANG SAMA, DARI MANA PUN LAMARANNYA DIKUMPULKAN.
     *
     * Papan worklist bisa dikumpulkan dua cara: per PROGRAM (detailProgram)
     * atau per JOB VACANCY/MPP (detailLoker). Yang berbeda hanya cara memilih
     * baris lamaran dan cara menyusun kolomnya; segala sesudah itu — rapor
     * sub-tes, kuota, berkas, badge, penempatan kartu, jaring pengaman kolom
     * cadangan — identik.
     *
     * Dipisah ke sini supaya penambahan cara kedua tidak berarti menyalin 280
     * baris aturan penilaian. Salinan seperti itu tidak pernah tinggal diam:
     * satu sisi diperbaiki, sisi lain tertinggal, dan dua papan yang membaca
     * populasi sama mulai menjawab berbeda — tanpa satu galat pun.
     *
     * @param  \Illuminate\Support\Collection  $lamaran  baris lamaran yang SUDAH tersaring
     * @param  array<int, array<string, mixed>>  $kolom  kolom papan
     * @return array{kolom: array, pelamar: array, kampusPer: \Illuminate\Support\Collection}
     */
    private function papanPelamar(\Illuminate\Support\Collection $lamaran, array $kolom): array
    {
        // BERPOTONG — lihat MetrikRekrutmen::potongIn(). Satu program bisa
        // berisi ribuan pelamar, dan whereIn menerbitkan satu parameter per id.
        //
        // DUA LANGKAH. Seluruh tahap setiap kandidat dibaca dengan KOLOM RINGAN
        // saja — cukup untuk memilih tahap tampil & tahap aktif
        // (PipelineProgress::tahapKini/tahapAktif: Status, Hasil) dan untuk
        // kolom cadangan (AlurKolom::cocok/kolomCadangan: Kode, Urutan, Label).
        // Baris LENGKAP (69 kolom) hanya dibaca untuk tahap yang dipakai kartu.
        // Dulu `select *` untuk ±2.000 baris tahap memakan ±1,1 detik per papan.
        $tahapPer = \App\Support\Career\MetrikRekrutmen::potongIn(
            fn () => DB::table('N_WEB_CAREERS_Lamaran_Tahap')->orderBy('Urutan')
                ->select(['Id_Lamaran_Tahap', 'Lamaran_Id', 'Urutan', 'Status', 'Hasil', 'Kode', 'Label']),
            'Lamaran_Id',
            $lamaran->pluck('Id_Lamaran')->all(),
        )->groupBy('Lamaran_Id');

        // TAHAP YANG BENAR-BENAR DIPAKAI KARTU — tahap tampil (tahapKini) dan
        // tahap aktif (tahapAktif), paling banyak dua per kandidat. Aktivitas,
        // berkas, jumlah berkas, dan lama hold hanya dibaca untuk tahap-tahap
        // ini. Dulu dibaca untuk SELURUH tahap setiap kandidat (6–9 per orang),
        // dan papan 360 kandidat menyeret ±2.000 baris tahap beserta seluruh
        // aktivitas & berkasnya lewat jaringan — hanya untuk dibuang lagi.
        $tahapDipakai = [];
        foreach ($lamaran as $l) {
            $daftar = collect($tahapPer->get($l->Id_Lamaran, []));
            foreach ([PipelineProgress::tahapKini($l, $daftar), PipelineProgress::tahapAktif($l, $daftar)] as $t) {
                if ($t) {
                    $tahapDipakai[(int) $t->Id_Lamaran_Tahap] = true;
                }
            }
        }
        $tahapDipakai = array_keys($tahapDipakai);

        // Baris lengkap untuk tahap yang dipakai, lalu disisipkan kembali ke
        // daftar tiap kandidat — pemilihan tahap di kartu memberi hasil yang
        // sama persis, kini dengan seluruh kolomnya.
        $tahapLengkap = $tahapDipakai
            ? \App\Support\Career\MetrikRekrutmen::potongIn(
                fn () => DB::table('N_WEB_CAREERS_Lamaran_Tahap'),
                'Id_Lamaran_Tahap',
                $tahapDipakai,
            )->keyBy(fn ($t) => (int) $t->Id_Lamaran_Tahap)
            : collect();
        $tahapPer = $tahapPer->map(
            fn ($daftar) => collect($daftar)->map(fn ($t) => $tahapLengkap->get((int) $t->Id_Lamaran_Tahap) ?? $t)->values()
        );

        // Rapor sub-tes per tahap (baterai multi-tes) — dasar admin memutuskan.
        // Lewat helper, bukan kueri sendiri: baris aktivitasnya ikut membawa
        // `Token_Terbit`, dan tanpa itu PipelineReadModel menyimpulkan "menunggu
        // hasil" untuk ujian yang tokennya belum terbit — berselisih dengan
        // portal kandidat yang membaca hal yang sama dari sumber yang sama.
        $subPer = \App\Support\Career\MetrikRekrutmen::aktivitasDenganTokenPer($tahapDipakai);

        // Kuota loker + kursi TERISI — untuk tombol sadar-kuota.
        //
        // Status mana yang memotong kuota dibaca dari MASTER, sama dengan
        // gerbang di LamaranService dan pembukuan di KursiPosisi. Ditulis
        // 'LULUS' di sini, layar akan menampilkan angka yang berbeda dari yang
        // dipakai gerbangnya sendiri begitu ada satu hasil lain dicentang.
        $potongKuota = \App\Support\Career\HasilKeputusan::kodePotongKuota() ?: ['LULUS'];

        $posisiIds = $lamaran->pluck('Program_Posisi_Id')->filter()->unique()->all();
        $kuotaPosisi = $posisiIds ? DB::table('N_WEB_CAREERS_Program_Posisi')->whereIn('Id_Program_Posisi', $posisiIds)->pluck('Kuota', 'Id_Program_Posisi') : collect();
        $terisiKuota = $posisiIds
            ? DB::table('N_WEB_CAREERS_Lamaran')->whereIn('Program_Posisi_Id', $posisiIds)->whereIn('Status', $potongKuota)
                ->select('Program_Posisi_Id', DB::raw('COUNT(*) as J'))->groupBy('Program_Posisi_Id')->pluck('J', 'Program_Posisi_Id')
            : collect();

        // ── BUKU KURSI MPP, LINTAS PROGRAM ──────────────────────────────────
        //
        // Kuota loker hanya menjawab "jatah program ini". Yang menutup
        // penerimaan sesungguhnya adalah rencana MPP — dan itu menyeberang
        // program. Dibaca SEKALI untuk seluruh papan, bukan per kandidat.
        // DISEGARKAN, bukan sekadar dibaca. Baris ledger hanya ditulis ulang
        // pada peristiwa yang menyentuhnya (penerimaan & jalur baliknya);
        // membuka program baru atas MPP yang sama atau menonaktifkan program
        // tidak melewati jalur itu. Membacanya apa adanya membuat kartu
        // menyebut angka yang tidak dipakai gerbang mana pun.
        $mppKursi = \App\Support\Career\KursiMpp::segarkanBanyak(
            $lamaran->pluck('Mpp_Ref')->filter()->unique()->all()
        );

        // Jumlah berkas hasil (MCU/Interview) per tahap — untuk gate wajib-upload.
        $berkasCount = \App\Support\Career\MetrikRekrutmen::potongIn(
            fn () => DB::table('N_WEB_CAREERS_Lamaran_Tahap_Berkas')
                ->whereNull('Ulang_Id')
                ->select('Lamaran_Tahap_Id', DB::raw('COUNT(*) as J'))
                ->groupBy('Lamaran_Tahap_Id'),
            'Lamaran_Tahap_Id',
            $tahapDipakai,
        )->pluck('J', 'Lamaran_Tahap_Id');

        // Berkas yang melekat pada SATU AKTIVITAS (form wawancara terpindai,
        // lembar jawaban tes offline). Diambil sekali untuk seluruh program —
        // memuatnya per aktivitas saat drawer dibuka berarti satu kueri per
        // baris rapor, dan drawer terasa tersendat justru saat paling dipakai.
        $subIdsAll = $subPer->flatten(1)->pluck('Id_Lamaran_Tahap_Tes')->all();
        // Snapshot konfirmasi (CRM + permintaan terbuka) untuk seluruh papan —
        // dua kueri berpotong, bukan dua kueri per kartu.
        KonfirmasiJadwal::muatBanyak($subIdsAll);
        $berkasSub = \App\Support\Career\MetrikRekrutmen::potongIn(
            fn () => DB::table('N_WEB_CAREERS_Lamaran_Tahap_Berkas')->whereNull('Ulang_Id')->orderByDesc('Id_Lamaran_Tahap_Berkas'),
            'Lamaran_Tahap_Tes_Id',
            $subIdsAll,
        )->groupBy('Lamaran_Tahap_Tes_Id');

        // Berkas yang DIUNGGAH KANDIDAT (lembar jawaban terpindai, sertifikat).
        //
        // Sampai sekarang berkas ini hanya pernah terlihat di portal kandidat.
        // Akibatnya seluruh setelan "kandidat wajib mengunggah" di Master Alur
        // tidak berguna: berkasnya masuk, tetapi tim yang harus menilainya tidak
        // punya satu pun layar yang menampilkannya.
        $berkasKandidat = \App\Support\Career\MetrikRekrutmen::potongIn(
            fn () => DB::table('N_WEB_CAREERS_Lamaran_Tes_Berkas')->whereNull('Ulang_Id')->orderBy('Id_Lamaran_Tes_Berkas'),
            'Lamaran_Tahap_Tes_Id',
            $subIdsAll,
        )->groupBy('Lamaran_Tahap_Tes_Id');

        // Master alasan HOLD — dimuat SEKALI untuk seluruh daftar, bukan per
        // kandidat. PipelineProgress sengaja tidak menyentuh database sendiri.
        $alasanHold = self::masterAlasanHold()->map(fn ($a) => $a->Nama)->all();

        // TOTAL HARI KERJA TERTAHAN per tahap — SATU kueri berkelompok untuk
        // seluruh papan. Dulu dihitung per kartu (totalHariTertahan()), satu
        // kueri per kandidat: papan 360 kandidat menembak 360 kueri SUM dan
        // menghabiskan ±22 detik hanya untuk angka ini.
        $holdTotalPer = self::siapHitungHold()
            ? \App\Support\Career\MetrikRekrutmen::potongIn(
                fn () => DB::table('N_WEB_CAREERS_Lamaran_Tahap_Hold')
                    ->whereNotNull('Hari_Kerja_Tertahan')
                    ->select('Lamaran_Tahap_Id', DB::raw('SUM(Hari_Kerja_Tertahan) as J'))
                    ->groupBy('Lamaran_Tahap_Id'),
                'Lamaran_Tahap_Id',
                $tahapDipakai,
            )->pluck('J', 'Lamaran_Tahap_Id')
            : collect();

        // Jawaban formulir seluruh kandidat — SEKALI, lalu dipakai nama resmi
        // dan penyaring kampus di bawah. Dulu keduanya membaca Jawaban_Json
        // yang sama persis, masing-masing sekali.
        $pengisianPer = LamaranService::pengisianPerLamaran($lamaran->pluck('Id_Lamaran')->all());

        // Nama resmi dari formulir — tanpa kueri jawaban tambahan.
        $namaResmi = self::namaResmiPerLamaran($lamaran->pluck('Id_Lamaran')->all(), $pengisianPer);

        // KAMPUS — penyaring worklist, dan tidak pernah jadi kolom tabel: ia
        // jawaban formulir pendaftaran. Diambil sekali untuk seluruh daftar
        // (lihat LamaranService::identitasPerLamaran); memanggilnya per kandidat
        // berarti dua kueri kali jumlah pelamar.
        //
        // Daftar pilihan penyaringnya dibangun layar dari nilai-nilai INI —
        // "kampus yang memang terdaftar di program ini", bukan 328 ribu baris
        // Master Kampus yang 99,9%-nya tidak punya satu pun pelamar di sini.
        $kampusPer = LamaranService::identitasPerLamaran($lamaran->pluck('Id_Lamaran')->all(), 'KAMPUS', $pengisianPer);

        // BERKAS FORMULIR per kandidat — ijazah, KTP, CV, sertifikat: yang
        // diunggah kandidat sendiri saat mengisi formulir.
        //
        // Dipakai kolom "Berkas" di mode List. Tanpa angka ini, satu-satunya cara
        // tahu siapa yang belum melampirkan apa pun adalah membuka modal tiap
        // orang satu per satu — pekerjaan yang justru paling sering dilakukan
        // berjajar, saat memverifikasi satu angkatan sekaligus.
        //
        // Satu kueri untuk SELURUH daftar, sejalan dengan $kampusPer di atas.
        // BERPOTONG — lihat MetrikRekrutmen::potongIn().
        $berkasFormulir = \App\Support\Career\MetrikRekrutmen::potongIn(
            fn () => DB::table('N_WEB_CAREERS_Formulir_Berkas as fb')
                ->join('N_WEB_CAREERS_Formulir_Pengisian as fp', 'fp.Id_Formulir_Pengisian', '=', 'fb.Formulir_Pengisian_Id')
                ->select('fp.Lamaran_Id', DB::raw('COUNT(*) as J'))
                ->groupBy('fp.Lamaran_Id'),
            'fp.Lamaran_Id',
            $lamaran->pluck('Id_Lamaran')->all(),
        )->pluck('J', 'Lamaran_Id');

        $pelamar = $lamaran->map(function ($l) use ($tahapPer, $subPer, $kuotaPosisi, $terisiKuota, $mppKursi, $berkasCount, $berkasSub, $berkasKandidat, $alasanHold, $kolom, $namaResmi, $kampusPer, $berkasFormulir, $holdTotalPer) { // NOSONAR
            $tahapList = collect($tahapPer->get($l->Id_Lamaran, []));

            // Aturan penempatan + badge + kuota dipusatkan di PipelineProgress
            // (dipakai juga oleh halaman Monitoring Rekrutmen).
            $tk = PipelineProgress::tahapKini($l, $tahapList);
            $tAktif = PipelineProgress::tahapAktif($l, $tahapList);
            // Sub-tes tahap aktif ikut dikirim: mode keputusan & kesiapan tahap
            // dinilai dari sana (lihat PipelineProgress::state).
            $st = PipelineProgress::state($l, $tAktif, $tk, $subPer->get($tAktif->Id_Lamaran_Tahap ?? 0, []), $alasanHold);
            $skor = $st['skor'];
            $siap = $st['siap'];
            $nungguSistem = $st['nungguSistem'];
            $butuhKeputusan = $st['butuhKeputusan'];

            $ku = PipelineProgress::infoKuota(
                (int) ($kuotaPosisi[$l->Program_Posisi_Id] ?? 0),
                (int) ($terisiKuota[$l->Program_Posisi_Id] ?? 0),
                (int) ($tAktif->Urutan ?? 0),
                (int) $l->Total_Tahap,
                // Batas kedua: rencana MPP, lintas program. Null bila lokernya
                // memang tidak menempel MPP mana pun — gerbangnya lalu diam,
                // persis seperti sebelum fitur ini ada.
                $mppKursi[trim((string) $l->Mpp_Ref)] ?? null,
            );

            $badge = PipelineProgress::badge($l, $st, $tAktif);

            return [
                'id' => Hashids::encode($l->Id_Lamaran),
                'tahapId' => $tAktif ? Hashids::encode($tAktif->Id_Lamaran_Tahap) : null,
                'lamaranKode' => $l->Kode,
                // NAMA RESMI dari formulir lebih dulu; nama akun jadi cadangan.
                // Nama akun diketik saat mendaftar dan kerap seadanya — kartu,
                // undangan, dan berkas lalu menyebut orang yang sama dengan tiga
                // nama berbeda, dan rekruter mencarinya dengan nama yang tidak
                // pernah ia tulis sendiri.
                'pelamar' => $namaResmi->get($l->Id_Lamaran) ?: ($l->Pelamar ?: $l->Created_By),
                // Nama akun tetap dibawa: saat keduanya berbeda, itu fakta yang
                // perlu terlihat — bukan disembunyikan.
                'pelamarAkun' => $l->Pelamar ?: null,
                'email' => $l->Email ?? null,
                'hp' => $l->NoHp ?? null,
                'posisi' => $l->Posisi ?: $l->Kategori,
                // Identitas + rincian lowongan — dasar penyaring & isi kartu.
                'posisiId' => $l->Program_Posisi_Id ? Hashids::encode($l->Program_Posisi_Id) : null,
                'departemen' => $l->Departemen ?? null,
                'lokasi' => $l->Lokasi ?? null,
                'level' => $l->Level ?? null,
                'mppRef' => $l->Mpp_Ref ?? null,
                // Asal kampus/sekolah — dari jawaban formulir, dipakai penyaring
                // "Kampus" di worklist dan ikut terbaca di baris mode List.
                'kampus' => $kampusPer->get($l->Id_Lamaran),
                // Jumlah lampiran formulir kandidat — kolom "Berkas" di mode List.
                'jmlBerkasForm' => (int) ($berkasFormulir[$l->Id_Lamaran] ?? 0),
                'waktuLamar' => $l->Waktu_Lamar,
                'kategori' => $l->Kategori,
                'statusLamaran' => $l->Status,
                // PENEMPATAN KARTU — memakai KODE tahap, bukan nomor urutnya.
                //
                // Nomor urut hanya benar selama alur tak pernah berubah. Begitu
                // alur disunting (baris dipakai ulang per urutan) atau program
                // diarahkan ke alur lain, "tahap ke-3" milik kandidat dan
                // "kolom ke-3" di papan bisa dua hal yang sama sekali berbeda.
                'kolomKode' => AlurKolom::cocok($kolom, $tk),
                // Nomor tetap dikirim untuk urutan & indikator progres — tapi
                // bukan lagi dasar penempatan.
                'kolomUrutan' => (int) ($tk->Urutan ?? $l->Urutan_Tahap),
                'tahap' => $tk->Label ?? '—',
                'urutan' => (int) ($tk->Urutan ?? $l->Urutan_Tahap),
                // Info kuota (untuk tombol adaptif & indikator "sisa kursi").
                'kuota' => $ku['kuota'],
                'terisiKuota' => $ku['terisiKuota'],
                'sisaKuota' => $ku['sisaKuota'],
                'kuotaPenuh' => $ku['kuotaPenuh'],
                // Batas mana yang menutup — 'LOKER' atau 'MPP'. Keduanya
                // menuntut tindakan yang berbeda, jadi layar harus menyebutnya.
                'kuotaSebab' => $ku['kuotaSebab'],
                'mppKuota' => $ku['mppKuota'],
                'mppTerisi' => $ku['mppTerisi'],
                'mppSisa' => $ku['mppSisa'],
                'diTahapAkhir' => $ku['diTahapAkhir'],
                // Berkas hasil pada tahap aktif (untuk unggah/preview + gate wajib).
                'jmlBerkas' => (int) ($tAktif ? ($berkasCount[$tAktif->Id_Lamaran_Tahap] ?? 0) : 0),
                'totalTahap' => (int) $l->Total_Tahap,
                'badge' => $badge,
                'butuhKeputusan' => (bool) $butuhKeputusan,
                'nungguSistem' => (bool) $nungguSistem,
                'siapDiputus' => (bool) $siap,
                // Mode keputusan tahap aktif + kenapa tombolnya dikunci —
                // dipakai worklist menyembunyikan / menonaktifkan tombol.
                'modeKeputusan' => $st['modeKeputusan'],
                'otomatis' => (bool) $st['otomatis'],
                'aktivitasBelumTercatat' => (int) $st['aktivitasBelumTercatat'],
                'alasanKunci' => $st['alasanKunci'],
                // TAHAP INI TERMASUK CUT-OFF TALENT POOL?
                //
                // Menentukan apakah pilihan "simpan ke Talent Pool" ditawarkan
                // saat kandidat mundur. Di tahap awal — sebelum ada satu pun
                // penilaian — menawarkannya berarti mengisi Talent Pool dengan
                // orang yang belum pernah dinilai siapa pun, dan daftar seperti
                // itu berhenti dipercaya lalu berhenti dipakai.
                'bolehTalentPool' => ($tAktif->Flag_Talent_Pool ?? 'T') === 'Y',
                // ── PERILAKU TAHAP MILIK KANDIDAT INI SENDIRI ──
                //
                // Dulu layar menyimpulkannya dengan mencari kolom master yang
                // nomor urutnya sama, lalu membaca flag di sana. Artinya
                // menyunting alur langsung mengubah syarat kelulusan orang yang
                // sedang menjalaninya — yang paling merugikan: menyalakan
                // "wajib unggah berkas" mengunci kandidat yang tahapnya sudah
                // selesai dinilai, menuntut dokumen yang dulu tidak diminta.
                //
                // Sekarang jawabannya dibawa kandidat, dari salinan tahapnya.
                //
                // Dari $tk (tahap yang DITAMPILKAN), bukan $tAktif: kandidat
                // yang sudah LULUS/GUGUR tidak punya tahap aktif — $tAktif null
                // — dan perilakunya akan diam-diam jatuh kembali ke master,
                // persis kebocoran yang sedang ditutup. $tk selalu ada, dan ia
                // memang tahap yang kartunya sedang berdiri di situ.
                'perilaku' => AlurKolom::perilaku(
                    $tk ?? $tAktif,
                    collect($kolom)->firstWhere('kode', AlurKolom::cocok($kolom, $tk)),
                ),
                // DITAHAN (hold) — berikut alasannya, siapa yang menahan, dan
                // sejak kapan. Kandidat tetap di bucket tahapnya; yang berubah
                // hanya bahwa keputusannya sengaja ditunda.
                'hold' => ($tAktif && ($tAktif->Hold_Flag ?? 'T') === 'Y') ? [
                    'alasanKode' => $tAktif->Hold_Alasan_Kode,
                    'alasanNama' => $alasanHold[$tAktif->Hold_Alasan_Kode ?? ''] ?? null,
                    'catatan' => $tAktif->Hold_Alasan,
                    'catatanHtml' => $tAktif->Hold_Alasan_Html,
                    'sejak' => (string) ($tAktif->Hold_At ?: ''),
                    'olehSiapa' => $tAktif->Hold_By,
                    // Sudah berapa hari KERJA tertahan sampai hari ini. Dihitung
                    // hidup karena penahanannya memang masih berjalan.
                    'hariKerja' => $tAktif->Hold_At
                        ? \App\Support\Career\SlaMpp::selisihHariKerja(
                            (string) $tAktif->Hold_At,
                            now()->toDateTimeString(),
                        )
                        : 0,
                ] : null,
                // BATAS PENGISIAN FORMULIR tahap aktif — chip kartu, saringan
                // "Lewat batas", dan panel di drawer. Null bila tak berlaku.
                'batas' => BatasIsi::status($tAktif),
                // ── TOTAL TERTAHAN SEPANJANG TAHAP INI ────────────────────
                //
                // Dijumlahkan dari SELURUH penahanan yang pernah terjadi, bukan
                // hanya yang terakhir: satu tahap bisa ditahan-dilepas
                // berkali-kali, dan yang menjelaskan lamanya proses adalah
                // totalnya.
                //
                // Ditampilkan sebagai angka pendamping SLA — tenggat MPP tidak
                // bergeser karenanya (lihat catatan di kirim hold), tapi
                // rekruter bisa menunjukkan berapa lama proses berhenti di luar
                // kendalinya.
                'holdTotalHariKerja' => $tAktif ? (int) ($holdTotalPer[$tAktif->Id_Lamaran_Tahap] ?? 0) : 0,
                // Kesimpulan MESIN atas hasil tes tahap ini (LULUS/GAGAL/SEBAGIAN).
                // Admin melihatnya sebelum mengetuk palu — tanpa ini layar hanya
                // bilang "Siap Diputus" dan hasil tesnya harus ditebak sendiri.
                'hasilData' => $st['hasilData'],
                'ringkasHasil' => $st['ringkasHasil'],
                'skor' => $skor,
                // PENAWARAN SUDAH BENAR-BENAR DIAJUKAN?
                //
                // Bukan sekadar "tahapnya bernama Offering". Selama aktivitas
                // penawarannya belum dicatat, belum ada apa pun yang bisa ditolak
                // atau diundurkan — menawarkan tombol "Kandidat Menolak" di situ
                // sama saja mempersilakan mencatat jawaban atas surat yang belum
                // pernah dikirim.
                'penawaranDiajukan' => self::penawaranDiajukan(
                    $subPer->get($tk->Id_Lamaran_Tahap ?? 0, []),
                    (int) ($tAktif ? ($berkasCount[$tAktif->Id_Lamaran_Tahap] ?? 0) : 0),
                ),
                // JAWABAN KANDIDAT atas penawaran. Admin tidak boleh menebak
                // dari diamnya kandidat: "belum menjawab" dan "sudah menerima"
                // menuntut tindakan yang sama sekali berbeda.
                'tanggapan' => ($tAktif->Tanggapan_Kandidat ?? null) ? [
                    'jawab' => $tAktif->Tanggapan_Kandidat,
                    'catatan' => $tAktif->Tanggapan_Catatan,
                    'waktu' => (string) ($tAktif->Tanggapan_At ?: ''),
                ] : null,
                'rekomendasi' => $tAktif->Rekomendasi ?? null,
                'alasan' => $tAktif->Rekomendasi_Alasan ?? $l->Alasan_Gugur,
                'pengisianId' => ($tAktif && $tAktif->Formulir_Pengisian_Id) ? Hashids::encode($tAktif->Formulir_Pengisian_Id) : null,
                // RAPOR sub-tes tahap yang ditampilkan (baterai multi-tes): admin
                // melihat semua skor — termasuk tes informatif — sebelum memutus.
                'tests' => self::rapotTahap(
                    $subPer->get($tk->Id_Lamaran_Tahap ?? 0, []),
                    $tk->Urutan_Aktivitas ?? 'PARALEL',
                    $berkasSub,
                    $berkasKandidat,
                ),
                // Aturan urutan tahap yang ditampilkan — dipakai layar untuk
                // menjelaskan KENAPA sebagian tombol tidak ada.
                'urutanAktivitas' => $tk->Urutan_Aktivitas ?? 'PARALEL',
                // ── APA YANG MASIH DITUNGGU DARI TAHAP AKTIF ────────────────
                // Daftar "aktivitas — sebabnya", dipakai layar untuk mengunci
                // tombol yang memajukan kandidat DAN menyebutkan apa yang
                // kurang. Dihitung dari tahap AKTIF, bukan tahap yang sedang
                // dilihat: keputusan selalu menyangkut tahap yang berjalan.
                'belumTuntas' => self::belumTuntasTahap(
                    $subPer->get($tAktif->Id_Lamaran_Tahap ?? 0, []),
                    $tAktif->Urutan_Aktivitas ?? 'PARALEL',
                ),
            ];
        })->all();

        // ── JARING PENGAMAN: tidak ada kartu yang boleh hilang dari papan ──
        //
        // Kandidat yang tahapnya tak cocok kolom mana pun — alur lama yang
        // tahapnya sudah dihapus, atau data pra-mesin tanpa Kode — akan lenyap
        // dari layar kalau dibiarkan. Dan kandidat yang tak terlihat tidak akan
        // pernah dikerjakan siapa pun; itu kegagalan yang jauh lebih mahal
        // daripada satu kolom tambahan yang terlihat asing.
        if (collect($pelamar)->contains('kolomKode', AlurKolom::KODE_LAINNYA)) {
            $tersesat = $tahapPer->flatten(1)->filter(
                fn ($t) => AlurKolom::cocok($kolom, $t) === AlurKolom::KODE_LAINNYA,
            );

            if ($cadangan = AlurKolom::kolomCadangan($tersesat)) {
                $kolom[] = $cadangan;
            }
        }

        return ['kolom' => $kolom, 'pelamar' => $pelamar, 'kampusPer' => $kampusPer];
    }

    /**
     * Kode negara ISO alfa-2 per NAMA kampus — bahan bendera di penyaring.
     *
     * Dicocokkan lewat nama karena itulah satu-satunya yang tersimpan di jawaban
     * formulir: kandidat boleh mengetik sendiri kampusnya, dan yang diketik itu
     * yang dipakai merekrut. Nama yang tidak ada di master tidak dipaksakan —
     * ia cukup tidak muncul di peta ini, lalu layar menggambar bola dunia.
     *
     * Satu nama bisa punya beberapa baris di master (impor `world` dan PDDIKTI
     * bertemu di tabel yang sama, lihat ReferensiController::lengkapiNegara).
     * Yang diambil kode negara PERTAMA yang benar-benar terisi — baris kembar
     * yang negaranya kosong tidak boleh menghapus bendera yang sudah benar.
     */
    private function benderaKampus(array $namaKampus): array
    {
        $nama = collect($namaKampus)
            ->map(fn ($n) => trim((string) $n))
            ->filter()
            ->unique()
            ->values();

        if ($nama->isEmpty()) {
            return [];
        }

        $peta = [];
        // Dipotong: satu program dengan ribuan kampus berbeda tidak pernah
        // terjadi, tapi klausa IN tanpa batas adalah kueri yang menunggu
        // giliran untuk meledak.
        foreach ($nama->chunk(500) as $bagian) {
            DB::table('N_WEB_CAREERS_Master_Kampus')
                ->whereIn('Nama', $bagian->all())
                ->select('Nama', 'Negara_Kode')
                ->get()
                ->each(function ($r) use (&$peta) {
                    $kode = strtolower(trim((string) $r->Negara_Kode));
                    if (preg_match('/^[a-z]{2}$/', $kode) !== 1 || isset($peta[$r->Nama])) {
                        return;
                    }
                    $peta[$r->Nama] = $kode;
                });
        }

        return $peta;
    }

    /**
     * Penawaran pada tahap ini SUDAH sampai ke kandidat?
     *
     * Inilah gerbang yang menentukan kapan kandidat boleh menjawab (terima /
     * mundur) — di portalnya maupun sebagai catatan admin. Sebelum gerbang ini
     * terbuka, kandidat belum memegang apa pun: memintanya menjawab sama saja
     * menanyakan pendapat atas surat yang belum pernah dikirim.
     *
     * TIGA tanda yang dihitung, semuanya berarti "kandidat sudah tahu":
     *
     *   1. Aktivitas penawaran berjadwal SUDAH DIJADWALKAN. Menjadwalkan
     *      negosiasi otomatis mengirim undangan ke kandidat — sejak email itu
     *      terkirim ia memang sudah ditawari pembicaraan.
     *   2. Aktivitas penawaran sudah final (hadir/tidak hadir tercatat).
     *   3. Berkas hasil tahap sudah ada — surat penawarannya sendiri terunggah.
     *      Ini yang berlaku pada tahap yang LANGSUNG surat penawaran, tanpa
     *      negosiasi: tak ada jadwal yang bisa jadi penanda.
     */
    private static function penawaranDiajukan(iterable $subTes, int $jmlBerkasTahap = 0): bool
    {
        $tipe = self::masterTipeTahap();
        $adaPenawaran = false;

        foreach ($subTes as $s) {
            if (($tipe[$s->Tipe_Tahap_Kode ?? '']->Flag_Penawaran ?? 'T') !== 'Y') {
                continue;
            }

            $adaPenawaran = true;
            if (! empty($s->Jadwal_Mulai) || ($s->Flag_Selesai ?? 'N') === 'Y') {
                return true;
            }
        }

        return $adaPenawaran && $jmlBerkasTahap > 0;
    }

    /**
     * Satu baris RAPOR aktivitas tahap.
     *
     * `dapatDicatat` menentukan munculnya tombol "Catat Hasil" / "Tidak hadir".
     * SIAPA YANG MENCATAT HASIL — mengikuti cara aktivitas itu dijalankan:
     *
     *  UJIAN ONLINE (CAT).  Hasilnya datang sendiri dari HCLearn. "Catat Hasil"
     *      TIDAK ditawarkan: admin tak punya angka untuk diisi, dan mengisinya
     *      manual justru menimpa nilai resmi yang sebentar lagi masuk. Yang
     *      masuk akal hanya "Tidak hadir" — itu fakta yang cuma diketahui tim,
     *      dan sekaligus jalan keluar bila kandidat tak pernah mengerjakan.
     *
     *  DIKERJAKAN TIM (wawancara, tes offline, FGD).  Tak ada sistem lain yang
     *      mengirim hasilnya, jadi admin yang mencatat.
     *
     * Kecuali: tahap ber-aktivitas TUNGGAL yang ditangani tim (mis. Seleksi
     * Administrasi) hasilnya ADALAH keputusan Loloskan/Tidak Lolos itu sendiri —
     * menyediakan "Catat Hasil" di situ hanya menduplikasi keputusan yang sama.
     */
    /**
     * Rapor SELURUH aktivitas satu tahap — termasuk menghitung mana yang masih
     * terkunci bila tahapnya dikerjakan BERURUTAN.
     *
     * Dihitung di sini, sekali per tahap, bukan di dalam rapotTes(): tiap baris
     * perlu tahu keadaan baris SEBELUMNYA, dan menanyakannya ke database per
     * aktivitas berarti satu kueri per baris rapor hanya untuk menjawab
     * pertanyaan yang jawabannya sudah ada di tangan.
     */
    /**
     * Aktivitas PENENTU tahap ini yang belum tuntas — [{label, sebab}, ...].
     *
     * Dihitung ULANG lewat rapotTahap() supaya aturannya tak pernah bercabang:
     * apa yang membuat tombol mati di layar dan apa yang membuat server menolak
     * harus satu hal yang sama. Menghitungnya terpisah berarti cepat atau lambat
     * tombolnya mati padahal server mengizinkan — atau sebaliknya, dan yang
     * kedua itu celah, bukan sekadar layar yang aneh.
     *
     * INFORMATIF IKUT MENGUNCI — dan ini SENGAJA BERBEDA dari
     * PipelineProgress::state, yang hanya menghitung aktivitas PENENTU.
     *
     * Keduanya menjawab pertanyaan yang berlainan:
     *
     *   state()   "sudah cukup bahan untuk MENYIMPULKAN?" — hanya hasil
     *             aktivitas penentu yang bisa menyimpulkan lulus/gagal.
     *   di sini   "pekerjaan tahap ini sudah SELESAI DIKERJAKAN?"
     *
     * `Peran` menyatakan apakah HASILNYA menentukan kelulusan. Ia tidak pernah
     * berarti aktivitasnya boleh dilewati. Wawancara Manajemen yang berperan
     * informatif tetap wawancara yang harus benar-benar terjadi — dan kalau
     * INFORMATIF dikecualikan di sini, tahap yang seluruh aktivitasnya
     * informatif tidak akan pernah terkunci sama sekali. Justru bentuk tahap
     * itulah yang muncul di laporan: satu wawancara, belum dijadwalkan, tombol
     * "Loloskan" tetap hidup.
     */
    private static function belumTuntasTahap(iterable $subs, ?string $urutanAktivitas): array
    {
        return collect(self::rapotTahap($subs, $urutanAktivitas, collect(), collect()))
            ->filter(fn ($t) => ! ($t['tuntas'] ?? true))
            ->map(fn ($t) => ['label' => $t['label'], 'sebab' => $t['alasanBelumTuntas']])
            ->values()
            ->all();
    }

    private static function rapotTahap(
        iterable $subs,
        ?string $urutanAktivitas,
        $berkasSub,
        $berkasKandidat,
    ): array {
        $berurutan = self::modeUrutanMengunci($urutanAktivitas);
        // Aktivitas terdepan yang belum final — pemegang giliran. Semua yang
        // urutannya di belakangnya ikut terkunci.
        $penghalang = null;

        if ($berurutan) {
            foreach (collect($subs)->sortBy('Urutan') as $s) {
                if (($s->Flag_Selesai ?? 'N') !== 'Y') {
                    $penghalang = $s;
                    break;
                }
            }
        }

        $jumlah = collect($subs)->count();

        // Konfirmasi seluruh aktivitas tahap ini dalam SATU kueri (papan sudah
        // memuat semuanya di muka — panggilan ini lalu tidak menyentuh basis data).
        KonfirmasiJadwal::muatBanyak(collect($subs)->pluck('Id_Lamaran_Tahap_Tes')->all());

        return collect($subs)
            ->map(fn ($x) => self::rapotTes(
                $x,
                $jumlah,
                self::bentukBerkasAktivitas($berkasSub->get($x->Id_Lamaran_Tahap_Tes, [])),
                self::bentukBerkasKandidat($berkasKandidat->get($x->Id_Lamaran_Tahap_Tes, [])),
                // Yang MEMEGANG giliran tidak terkunci oleh dirinya sendiri.
                $penghalang && $penghalang->Id_Lamaran_Tahap_Tes !== $x->Id_Lamaran_Tahap_Tes
                    ? $penghalang->Label
                    : null,
            ))
            ->values()
            ->all();
    }

    /** Master mode penilaian, di-cache per permintaan. */
    private static ?\Illuminate\Support\Collection $modePenilaianCache = null;

    private static function masterModePenilaian(): \Illuminate\Support\Collection
    {
        return self::$modePenilaianCache ??= DB::table('N_WEB_CAREERS_Master_Mode_Penilaian')
            ->get()
            ->keyBy('Kode');
    }

    /**
     * Setelan penilaian satu aktivitas → bentuk siap pakai untuk layar.
     *
     * `tipe` yang menentukan bidang apa yang dirender — BUKAN kodenya. Menambah
     * mode baru lewat master karena itu tidak menuntut layar ikut diubah,
     * selama perilakunya salah satu dari NONE/ANGKA/TEKS.
     */
    private static function bentukPenilaian(object $x): ?array
    {
        $def = self::masterModePenilaian()->get((string) ($x->Penilaian_Mode ?? ''));
        if (! $def) {
            return null;
        }

        return [
            'mode' => $def->Kode,
            'nama' => $def->Nama,
            'tipe' => $def->Tipe_Nilai,
            'maks' => $x->Nilai_Maks !== null ? (float) $x->Nilai_Maks : null,
            'opsi' => array_values(array_filter(array_map(
                'trim',
                explode(',', (string) ($x->Penilaian_Opsi ?? '')),
            ))),
        ];
    }

    private static function rapotTes(
        object $x,
        int $jumlahAktivitasTahap,
        array $berkas = [],
        array $berkasKandidat = [],
        ?string $menungguAktivitas = null,
    ): array {
        $final = in_array($x->Status, ['SELESAI', 'TIDAK_HADIR'], true);
        // Tahap BERURUTAN: aktivitas ini masih menunggu gilirannya. Seluruh
        // tombolnya ditutup — menawarkan "Atur Jadwal" untuk wawancara yang
        // belum boleh dijalankan hanya mengundang undangan yang salah terkirim.
        $terkunci = ! $final && $menungguAktivitas !== null;
        $tipe = self::masterTipeTahap()[$x->Tipe_Tahap_Kode ?? ''] ?? null;
        $online = ($tipe->Perilaku_Kode ?? null) === 'CAT' || ($x->Provider ?? '') === 'THIRD_PARTY';
        $isMcu = ($x->Tipe_Tahap_Kode ?? '') === 'MCU';

        /* PEMERIKSAAN (background / reference check) PUNYA GERBANG SENDIRI.
         *
         * Sebelum kandidat ditanya bersedia atau tidak, tidak ada yang boleh
         * dikerjakan: tidak mencatat hasil, tidak menandai apa pun. Menampilkan
         * "Catat Hasil" di keadaan itu mengundang petugas mengisi temuan atas
         * pemeriksaan yang belum boleh dijalankan. */
        $periksa = Pemeriksaan::bentuk($x);

        /* PHONE SCREENING — ringkasan saja di daftar aktivitas.
         *
         * Bentuk ringkas (tanpa argumen kedua): kode template, keadaan sesi,
         * skor. Pertanyaan dan jawabannya TIDAK ikut di sini — satu template
         * bisa berisi 50 pertanyaan, dan menyertakannya untuk setiap aktivitas
         * di setiap baris worklist berarti mengangkut ribuan baris yang tidak
         * satu pun digambar sampai panelnya dibuka.
         *
         * Isi penuhnya diambil layar lewat endpoint sub-tes/{id}/skrining. */
        $skrining = \App\Support\Career\Skrining::bentuk($x);
        $butuhSetuju = $periksa !== null
            && ! (($periksa['persetujuan']['ada'] ?? false) || ($periksa['persetujuan']['ditolak'] ?? false));

        // ── AKTIVITAS PENAWARAN ────────────────────────────────────────────
        // Dua bentuk, dibedakan oleh flag tipenya sendiri — bukan oleh kodenya:
        //
        //   BERJADWAL (Flag_Jadwal='Y', mis. Negosiasi, Tanda Tangan Kontrak)
        //       Yang dikerjakan tim adalah MENGATUR PERTEMUANNYA lalu menandai
        //       kandidat datang atau tidak. Tak ada "hasil" terpisah untuk
        //       dicatat: nilai negosiasi bukan angka, dan kesimpulannya ada
        //       pada keputusan tahap.
        //
        //   BERDOKUMEN (Flag_Upload_Hasil='Y', mis. Surat Penawaran)
        //       Tak menuntut tindakan apa pun di rapor. Suratnya diunggah di
        //       jendela keputusan, bersama keputusannya sendiri.
        $isPenawaran = ($tipe->Flag_Penawaran ?? 'T') === 'Y';
        $tipeBerjadwal = ($tipe->Flag_Jadwal ?? 'T') === 'Y';
        // Punya hasil sendiri yang harus dicatat tim: aktivitas manual pada tahap
        // multi-aktivitas (di tahap tunggal, keputusan tahap sudah mewakilinya).
        //
        // MCU DIKECUALIKAN — tidak pernah punya "Catat Hasil" sendiri. Hasil
        // pemeriksaan kesehatan tidak berdiri terpisah dari nasib tahapnya:
        // begitu hasilnya keluar, admin meloloskan atau tidak. Karena itu status
        // kesehatan, penyedia, dan tanggalnya dicatat di JENDELA KEPUTUSAN
        // (lihat putus()) bersama berkas dari kliniknya — satu peristiwa, satu
        // jendela, bukan dua yang salah satunya kerap terlewat.
        // MCU SELALU punya hasilnya sendiri untuk dicatat, berapa pun jumlah
        // aktivitas tahapnya. Dulu ia dikecualikan karena hasilnya diisi di
        // jendela keputusan; sejak status kesehatan pindah ke langkah kehadiran
        // (satu peristiwa: kandidat diperiksa, inilah hasilnya), pengecualian
        // itu justru membuat modal kehadiran tidak menampilkan bidang apa pun.
        $dicatatTim = ! $online && ($jumlahAktivitasTahap > 1 || $isMcu) && ! $isPenawaran;

        // Apa yang masih ditunggu dari aktivitas ini — null = tuntas. Dihitung
        // SEKALI, dipakai dua kali di bawah.
        $belumTuntas = self::aktivitasTuntas($x, $final, $terkunci, $tipe, $online, $dicatatTim, $isPenawaran, $tipeBerjadwal);

        // ── UJIAN ONLINE YANG KEPUTUSANNYA MILIK ADMIN ──────────────────────
        //
        // Nilainya sudah masuk dari HCLearn, tapi alat tesnya memang tidak
        // berbunyi lulus/gagal (PAPI Kostick, DISC, Kraeplin). Yang tersisa
        // hanya satu hal: penilai menyatakan lulus atau tidak.
        //
        // Dihitung SEKALI di sini karena tiga tombol bergantung padanya —
        // dan ketiganya harus sepakat. Sebelumnya keadaan ini menyalakan
        // "Catat Hasil" (jendela berisi bidang nilai & catatan yang tak satu
        // pun perlu diisi) dan membiarkan "Sinkronkan" ikut tampil, padahal
        // yang ditunggu bukan HCLearn melainkan orang di kantor ini.
        $butuhKeputusan = $online
            && ($x->Peran ?? '') === 'INFORMATIF'
            && ($x->Status ?? '') === 'MENUNGGU_KEPUTUSAN';

        return [
            'id' => Hashids::encode($x->Id_Lamaran_Tahap_Tes),
            'label' => $x->Label,
            // Tipe MILIK AKTIVITAS INI — dalam satu tahap bisa bercampur ujian
            // online, tes manual, dan wawancara; admin harus bisa membedakannya.
            'tipe' => $x->Tipe_Tahap_Kode ?? null,
            'tipeNama' => $tipe->Nama ?? null,
            'tipeIkon' => $tipe->Ikon ?? null,
            'provider' => $x->Provider,
            'peran' => $x->Peran,
            'wajib' => $x->Wajib === 'Y',
            'status' => $x->Status,
            'hasil' => $x->Hasil,
            'nilai' => $x->Nilai !== null ? (float) $x->Nilai : null,
            'catatan' => $x->Catatan ?? null,
            // Catatan penilaian LENGKAP (berformat). Hanya untuk mata admin —
            // portal kandidat tidak pernah menerimanya; lihat portalDetail().
            'catatanHtml' => $x->Catatan_Html ?? null,
            // Berkas hasil yang melekat pada aktivitas INI (form wawancara yang
            // dipindai, lembar jawaban tes offline) — diunggah TIM.
            'berkas' => $berkas,
            // Berkas yang diserahkan KANDIDAT untuk aktivitas ini, berikut
            // aturan yang berlaku baginya. Aturannya ikut dikirim supaya layar
            // bisa membedakan "belum mengunggah padahal wajib" dari "aktivitas
            // ini memang tidak meminta apa-apa" — dua keadaan yang menuntut
            // tindakan berbeda dari tim.
            //
            // KANDIDAT SUDAH MENYATAKAN LENGKAP (`terkirim`)? Pembeda antara
            // "masih mengunggah" dan "menunggu dinilai". Menilai pekerjaan yang
            // belum dinyatakan selesai adalah keputusan yang tak bisa ditarik —
            // tim perlu melihat bedanya sebelum menekan apa pun. Aturannya dari
            // alur ATAU dari mode jadwal (MCU mandiri), sama persis dengan yang
            // dibaca portal — lihat UndanganJadwal::aturanUnggah().
            'unggahKandidat' => ($aturanUnggah = UndanganJadwal::aturanUnggah($x))
                ? $aturanUnggah + ['terkirimOleh' => $x->Unggah_Kirim_By ?? null]
                : null,
            'berkasKandidat' => $berkasKandidat,
            // Tipe yang menuntut waktu & tempat (wawancara, MCU, tes offline).
            // Dibaca dari Master Tipe Tahap — BUKAN daftar kode di dalam kode
            // program, supaya tipe baru cukup ditambahkan lewat master.
            // JADWAL ULANG BERHENTI setelah kehadiran ditetapkan. Menggeser
            // jadwal untuk orang yang sudah datang (atau sudah dinyatakan tidak
            // datang) tidak berarti apa-apa — yang tersisa hanyalah tombol yang
            // mengundang salah tekan, dan sekali ditekan undangannya terkirim.
            'butuhJadwal' => ($tipe->Flag_Jadwal ?? 'T') === 'Y' && ! $final && ! $terkunci && empty($x->Jadwal_Hadir),
            // JADWAL YANG TIDAK DIUMUMKAN KE KANDIDAT (negosiasi penawaran).
            //
            // Layar WAJIB tahu ini, bukan menyimpulkannya dari kode tipe.
            // Tanpa penanda ini tombolnya berbunyi "kandidat diundang lewat
            // email" dan kaki modal berbunyi "Simpan & Undang Kandidat" untuk
            // jadwal yang justru tidak mengirim apa-apa — rekruter menekannya,
            // percaya kandidatnya sudah dikabari, lalu menunggu jawaban yang
            // tidak akan pernah datang.
            'jadwalPrivat' => JadwalPrivat::untuk($x->Tipe_Tahap_Kode ?? null),
            // ── URUTAN & VISIBILITAS ────────────────────────────────────────
            // Terkunci = tahapnya BERURUTAN dan giliran aktivitas ini belum
            // tiba. `menunggu` menyebut aktivitas mana yang ditunggu, supaya
            // admin tidak perlu menebak dari nomor urut.
            'terkunci' => $terkunci,
            'menunggu' => $terkunci ? $menungguAktivitas : null,
            // Aktivitas internal — dicatat tim, tidak pernah tampil di portal
            // kandidat. Ditandai di sini agar admin tahu bahwa yang ia lihat
            // memang tidak dilihat kandidat, dan tidak menunggu kandidat
            // melakukan apa pun.
            // Dibaca lewat JadwalPrivat::terlihat(), bukan langsung dari kolomnya:
            // aktivitas internal yang TELANJUR dijadwalkan tetap tampil di portal,
            // dan lencana "internal" di sini akan meyakinkan rekruter bahwa
            // kandidatnya tidak melihat apa yang sebenarnya ia lihat.
            'internal' => ! JadwalPrivat::terlihat($x),
            // Tipe yang MUSTAHIL daring (MCU, tes offline, tanda tangan kontrak).
            // Aturannya melekat di master, bukan ditebak dari nama tipe di layar.
            'wajibLuring' => ($tipe->Flag_Wajib_Luring ?? 'T') === 'Y',
            // Bentuk jadwal yang PALING MASUK AKAL untuk tipe ini — dari master.
            // Negosiasi gaji hampir selalu lewat telepon; tanpa bawaan, modal
            // membuka pada "Daring" dan admin harus ingat memindahkannya tiap
            // kali. Yang lupa akan mengirim undangan bertautan Meet untuk
            // percakapan yang sebenarnya cuma panggilan telepon.
            'modeJadwalBawaan' => $tipe->Mode_Jadwal_Bawaan ?? null,
            // Bentuk yang BOLEH untuk tipe ini (null = semua) — FGD tanpa
            // telepon. Jendela jadwal menyaring tombolnya dari sini.
            'modeIzin' => self::modeIzin($tipe),
            // TIAP TIPE MENYEBUT BERKAS & CATATANNYA SENDIRI.
            //
            // Dulu kotak lampiran selalu berbunyi "Form hasil wawancara /
            // berkas penilaian" untuk semua tipe — sehingga petugas MCU diminta
            // melampirkan form wawancara untuk hasil dari klinik. Yang paling
            // merugikan: orang yang membaca teliti justru ragu, mengira ia
            // membuka jendela yang salah, lalu menutupnya tanpa melampirkan
            // apa pun. Kosong → kalimat umum yang netral, bukan milik tipe lain.
            // Aktivitas ini punya kotak lampiran atau tidak — dari master.
            // Negosiasi gaji berlangsung lewat telepon dan tidak menghasilkan
            // dokumen; kotak kosong bertuliskan "Belum ada berkas dilampirkan"
            // membuat penilai yang teliti berhenti, mengira ada berkas yang
            // seharusnya ia punya.
            'berkasAktivitas' => ($tipe->Flag_Berkas_Aktivitas ?? 'Y') === 'Y',
            'labelBerkas' => $tipe->Label_Berkas ?? null,
            'petunjukBerkas' => $tipe->Petunjuk_Berkas ?? null,
            'labelCatatan' => $tipe->Label_Catatan ?? null,
            // Penanda agar modal "Catat Hasil" menampilkan bidang khusus MCU.
            'isMcu' => $isMcu,
            // Aktivitas ini MEMBAWA PENAWARAN (negosiasi, surat penawaran, kontrak).
            'penawaran' => $isPenawaran,
            // Penawaran BERDOKUMEN: tak ada yang perlu dikerjakan di rapor.
            // Barisnya keterangan belaka — memberinya lencana "Menunggu" membuat
            // admin mencari tombol yang memang tidak ada, dan surat penawaran
            // memang tidak menunggu apa-apa: ia diunggah saat keputusan diambil.
            'infoSaja' => $isPenawaran && ! $tipeBerjadwal,
            'selesai' => $final,
            // Kehadiran: NULL belum dicek, Y hadir, T tidak hadir.
            'hadir' => $x->Jadwal_Hadir ?? null,
            // Sudah dijadwalkan tapi kehadirannya belum dicatat -> tim harus
            // menetapkan itu dulu sebelum boleh mencatat hasil.
            'butuhKehadiran' => ! $final && ! empty($x->Jadwal_Mulai) && empty($x->Jadwal_Hadir),
            'mcu' => ($x->Mcu_Status ?? null) ? [
                'status' => $x->Mcu_Status,
                'penyedia' => $x->Mcu_Penyedia,
                'tanggal' => (string) ($x->Mcu_Tanggal ?: ''),
                'catatan' => $x->Mcu_Catatan,
            ] : null,
            'jadwal' => ($x->Jadwal_Mulai ?? null) ? [
                'mode' => $x->Jadwal_Mode,
                'daring' => strtoupper((string) $x->Jadwal_Mode) === 'DARING',
                'mulai' => (string) $x->Jadwal_Mulai,
                'selesai' => (string) ($x->Jadwal_Selesai ?: ''),
                'link' => $x->Jadwal_Link,
                'lokasi' => $x->Jadwal_Lokasi,
                // Tempat di luar master memakai penanda yang sama seperti di
                // layar, sehingga membuka kembali jendela jadwal langsung
                // menemukan pilihan "Lainnya" beserta isiannya.
                'lokasiId' => $x->Jadwal_Lokasi_Id
                    ? Hashids::encode($x->Jadwal_Lokasi_Id)
                    : (($x->Jadwal_Lokasi_Nama ?? null) ? MasterLokasiController::LAINNYA : null),
                'lokasiNama' => $x->Jadwal_Lokasi_Nama ?? null,
                'lokasiAlamat' => $x->Jadwal_Lokasi_Alamat ?? null,
                // Tempat LENGKAP berikut petanya — dipakai kartu lokasi di
                // rapor. Bentuknya sama untuk lokasi master maupun yang diketik.
                'tempat' => self::tempatJadwal($x),
                'catatan' => $x->Jadwal_Catatan,
                'olehSiapa' => $x->Jadwal_By,
                'kontak' => $x->Jadwal_Kontak ?? null,
                // Isian mentah VENDOR untuk jendela "Ubah jadwal": catatan
                // cabang (berformat) dan tautan peta yang ditempel tim.
                'lokasiHtml' => $x->Jadwal_Lokasi_Html ?? null,
                'mapsUrl' => $x->Jadwal_Maps_Url ?? null,
                // Surel jadwal terakhir berangkat — tombol "Kirim ulang"
                // menahan diri beberapa menit sesudahnya.
                'emailAt' => ! empty($x->Jadwal_Email_At) ? (string) $x->Jadwal_Email_At : null,
            ] + self::jadwalTambahan($x, $tipe, '/api/v1/karir/lamaran/sub-tes/'.Hashids::encode($x->Id_Lamaran_Tahap_Tes).'/surat') : null,
            // Ketentuan biaya (MCU) — dan begitu hasilnya dicatat, kesimpulannya:
            // diganti atau tidak. Tak ada isian; lihat BiayaAktivitas.
            'biaya' => BiayaAktivitas::status($x, $tipe),
            // KONFIRMASI KEHADIRAN — jawaban kandidat atas versi jadwal ini
            // (status efektif, batas, hitungan surel, permintaan jadwal lain),
            // dan aturannya untuk jendela Atur Jadwal (null = tidak diminta).
            'konfirmasi' => KonfirmasiJadwal::ringkas($x),
            'konfirmasiAturan' => KonfirmasiJadwal::aturanLayar($x, $tipe),
            // Peruntukan lokasi yang boleh dipilih untuk tipe aktivitas ini —
            // null = tidak dibatasi. Jendela jadwal menyaring dropdown-nya dari
            // sini, dan server memeriksa hal yang sama persis.
            'lokasiPeruntukan' => $tipe->Lokasi_Peruntukan_Kode ?? null,
            // ── "CATAT HASIL" HANYA UNTUK YANG TIDAK BISA DIJADWALKAN ────────
            //
            // Aktivitas yang PUNYA jadwal punya alurnya sendiri yang utuh:
            // Atur Jadwal → Hadir (berikut hasilnya) / Tidak Hadir. Menyisakan
            // "Catat Hasil" di sampingnya berarti dua jalan menuju keadaan yang
            // sama — sebelum dijadwalkan ia menawarkan mencatat hasil sesi yang
            // belum tentu terjadi, dan sesudah Hadir ia menawarkan mencatat
            // ulang sesuatu yang barusan dicatat.
            //
            // Yang tersisa memakainya: tipe yang memang TIDAK berjadwal
            // (Flag_Jadwal='T') — tanpa tombol ini, aktivitas itu tak punya
            // satu pun cara diselesaikan.
            // Ujian online ber-peran INFORMATIF TIDAK ikut di sini. Ia memang
            // menunggu verdict penilai, tapi jendela ini menawarkan nilai,
            // catatan, dan lampiran — tak satu pun yang perlu diisi, sebab
            // nilainya sudah datang dari HCLearn. Yang tersisa cuma "lulus atau
            // tidak", dan itu dua tombol, bukan sebuah formulir: lihat
            // `butuhKeputusan` di bawah.
            'dapatDicatat' => ($dicatatTim && ! $tipeBerjadwal) && ! $final && ! $terkunci
                && ! $butuhSetuju,
            // HASILNYA DINILAI TIM — lepas dari tombol mana yang tampil.
            // Dipakai jendela "Hadir" untuk tahu perlu-tidaknya menampilkan
            // bidang hasil. `dapatDicatat` di atas hanya mengatur tombol
            // terpisahnya; menyatukan keduanya membuat bidang hasil ikut hilang
            // begitu tombolnya disembunyikan.
            'dinilaiTim' => $dicatatTim && ! $final,
            // CARA AKTIVITAS INI DINILAI — dari Master Alur, dibekukan saat
            // lamaran dibuat. Layar memakainya untuk menampilkan bidang yang
            // BENAR: kotak angka untuk tes tertulis, daftar predikat untuk
            // DISC/FGD, dan tak ada bidang nilai sama sekali untuk wawancara.
            // Memaksakan angka pada tes berpredikat membuat penilai mengarang
            // angka, dan angka karangan itu terbaca seolah hasil ukur.
            'penilaian' => self::bentukPenilaian($x),
            'nilaiTeks' => $x->Nilai_Teks ?? null,
            // ── PEMERIKSAAN ────────────────────────────────────────────
            //
            // Null untuk aktivitas biasa, sehingga layar cukup memeriksa satu
            // kunci untuk tahu perlu menampilkan panel temuan atau tidak.
            //
            // Dua query tambahan per aktivitas pemeriksaan — dan hanya untuk
            // aktivitas itu. Satu lamaran umumnya punya nol atau satu; memuat
            // seluruhnya di muka lalu mengedarkannya lewat empat lapis
            // pemanggil menambah lebih banyak yang bisa salah daripada yang
            // dihemat.
            'pemeriksaan' => $periksa,
            // Null untuk aktivitas biasa — sepola 'pemeriksaan' di atas, layar
            // cukup memeriksa satu kunci untuk tahu perlu menggambar panel
            // skrining atau tidak.
            'skrining' => $skrining,
            // MENUNGGU JAWABAN KANDIDAT.
            //
            // Selama belum dijawab, barisnya hanya menawarkan Setuju / Tidak
            // setuju — bukan "Catat Hasil", dan tidak pernah "Tidak hadir".
            'butuhPersetujuan' => $butuhSetuju,
            // AKTIVITAS INI MENAHAN YANG BERIKUTNYA?
            //
            // Sudah selesai, ber-mode MANUAL, dan belum ditekan "Lanjutkan".
            // Ditandai supaya tombolnya muncul tepat di aktivitas yang menahan
            // — bukan di aktivitas yang tertahan, tempat admin tak bisa
            // berbuat apa-apa.
            'perluLanjut' => $final
                && self::lanjutButuhTrigger($x->Lanjut_Mode ?? null)
                && empty($x->Lanjut_At),
            'sudahLanjut' => ! empty($x->Lanjut_At),
            // Ujian online yang sudah dijadwalkan boleh ditarik hasilnya kapan
            // pun — jaring pengaman saat webhook CAT tidak sampai. TIDAK ikut
            // dikunci urutan: bila hasilnya sudah ada di HCLearn, menahannya di
            // sini hanya membuat data yang sudah sah tidak bisa masuk.
            //
            // TIDAK saat keputusan yang ditunggu: hasilnya sudah sampai, dan
            // "Sinkronkan" di sebelah "Lulus / Tidak Lulus" membuat penilai
            // mengira masih ada yang harus ditarik dulu sebelum boleh memutus.
            'dapatSinkron' => $online && ! $final && ! $butuhKeputusan && ! empty($x->Penjadwalan_Tahap_Id),
            // "Tidak hadir" berlaku untuk keduanya — hanya tim yang tahu, dan
            // untuk ujian online inilah jalan keluar bila kandidat tak mengerjakan.
            // Penawaran BERJADWAL ikut: ia tak punya "Catat Hasil", jadi tanpa ini
            // kandidat yang tidak datang negosiasi tak bisa ditandai sama sekali.
            // "Tidak hadir" adalah ESCAPE HATCH untuk aktivitas yang kehadirannya
            // BELUM ditetapkan. Setelah ditetapkan — apa pun jawabannya — tombol
            // ini hanya menggandakan keputusan yang sudah diambil lewat pasangan
            // Hadir/Tidak Hadir, dan dua jalan menuju keadaan yang sama membuat
            // admin menebak mana yang benar.
            // PEMERIKSAAN TIDAK PUNYA KEHADIRAN.
            //
            // Kandidat tidak datang ke mana pun dan tidak diminta hadir di mana
            // pun — tim yang menelepon kampus dan mantan atasannya. "Tidak hadir"
            // di barisnya adalah tombol yang tidak pernah punya arti, dan sekali
            // tertekan ia menutup aktivitas dengan alasan yang tidak pernah terjadi.
            'dapatTidakHadir' => $periksa === null
                && ($online || $dicatatTim || ($isPenawaran && $tipeBerjadwal))
                && ! $final && ! $terkunci && empty($x->Jadwal_Hadir),
            // Penanda UI: aktivitas ini menunggu hasil dari sistem lain.
            'online' => $online,
            // Layar memakainya untuk MENAMPILKAN sepasang tombol Lulus/Tidak
            // Lulus.
            'butuhKeputusan' => $butuhKeputusan,
            // ── ANGKANYA BERARTI, ATAU TIDAK? ────────────────────────────────
            //
            // Alat tes online ber-peran INFORMATIF (PAPI Kostick, DISC,
            // Kraeplin) mengeluarkan PROFIL, bukan nilai kelulusan. "78" di
            // sebelah lencana Selesai terbaca sebagai skor — padahal ia tidak
            // punya ambang batas, tidak bisa dibandingkan antar-alat, dan sama
            // sekali bukan dasar keputusan yang barusan diambil penilai.
            //
            // Berlaku SEBELUM maupun SESUDAH diputuskan: angka yang tak berarti
            // tidak berubah jadi berarti hanya karena verdict-nya sudah ada.
            //
            // Yang TETAP tampil: nilai ujian PENENTU (objektif, berambang
            // batas) dan nilai yang diketik tim sendiri pada aktivitas manual —
            // di sana angkanya memang sengaja ditulis seseorang.
            'skorBermakna' => ! ($online && ($x->Peran ?? '') === 'INFORMATIF'),
            // Sepasang tombolnya boleh ditekan — dipisah dari keadaan di atas
            // supaya aktivitas yang masih TERKUNCI URUTAN tetap terbaca "sudah
            // dites, tunggu keputusan" tanpa menawarkan tombol yang akan
            // ditolak server.
            'dapatPutusTes' => $butuhKeputusan && ! $final && ! $terkunci,
            // ── MASIH ADA YANG DITUNGGU DARI AKTIVITAS INI? ──────────────────
            //
            // Dipakai gerbang keputusan: "Lolos" dan "Talent Pool" tidak boleh
            // ditekan selama masih ada aktivitas penentu yang belum tuntas.
            //
            // Dulu gerbangnya cuma `butuhKehadiran`, dan itu MENSYARATKAN
            // jadwalnya sudah ada. Aktivitas yang belum dijadwalkan sama sekali
            // menghasilkan butuhKehadiran=false — bukan karena tidak ada yang
            // kurang, melainkan karena kehadiran memang belum mungkin
            // ditetapkan. Gerbangnya diam, dan kandidat yang wawancaranya belum
            // pernah dijadwalkan bisa diloloskan.
            'tuntas' => $belumTuntas === null,
            'alasanBelumTuntas' => $belumTuntas,
        ];
    }

    /**
     * Apa yang MASIH DITUNGGU dari satu aktivitas — null bila sudah tuntas.
     *
     * Satu tempat, satu aturan. Layar memakainya untuk menyebut apa yang kurang,
     * dan putus() memakainya untuk menolak keputusan yang tak berdasar; kalau
     * keduanya menghitung sendiri-sendiri, cepat atau lambat tombolnya mati
     * padahal server mengizinkan — atau sebaliknya, dan yang kedua itu celah.
     *
     * "TIDAK HADIR" DIHITUNG TUNTAS. Kandidat yang tidak datang sudah
     * menyelesaikan pertanyaannya: tak ada lagi yang perlu ditunggu darinya.
     */
    private static function aktivitasTuntas(
        object $x,
        bool $final,
        bool $terkunci,
        ?object $tipe,
        bool $online,
        bool $dicatatTim,
        bool $isPenawaran,
        bool $tipeBerjadwal
    ): ?string {
        // Sudah final, atau sudah dinyatakan tidak hadir → tak ada yang ditunggu.
        if ($final || ($x->Jadwal_Hadir ?? null) === 'T') {
            return null;
        }

        // Penawaran BERDOKUMEN tidak menuntut apa pun di rapor — suratnya
        // diunggah bersama keputusannya. Menghitungnya "belum tuntas" berarti
        // mengunci keputusan pada berkas yang justru baru bisa diunggah DI
        // DALAM jendela keputusan itu: kunci yang anak kuncinya ada di dalam.
        if ($isPenawaran && ! $tipeBerjadwal) {
            return null;
        }

        if ($terkunci) {
            return 'menunggu giliran aktivitas sebelumnya';
        }

        // Belum dijadwalkan padahal tipenya menuntut waktu & tempat — inilah
        // lubang yang dulu tak terlihat.
        if (($tipe->Flag_Jadwal ?? 'T') === 'Y' && empty($x->Jadwal_Mulai)) {
            return 'belum dijadwalkan';
        }

        if (! empty($x->Jadwal_Mulai) && empty($x->Jadwal_Hadir)) {
            // BERRENTANG TANGGAL (MCU vendor / mandiri): tidak ada "kehadiran"
            // di satu jam tertentu. Yang ditunggu bergantung pada SIAPA yang
            // memegang hasilnya — dibaca dari flag mode, bukan nama mode:
            //   kandidat mengunggah (MANDIRI) → berkasnya, sebelum batas;
            //   vendor (VENDOR)               → hasil dari vendor, dicatat tim.
            $modeX = UndanganJadwal::mode($x->Jadwal_Mode ?? null);
            if (UndanganJadwal::berbatasWaktu($modeX)) {
                if ($aturan = UndanganJadwal::aturanUnggah($x)) {
                    if ($aturan['terkirim']) {
                        return 'berkas kandidat sudah masuk — hasil belum dicatat';
                    }

                    // Batas UNGGAH (bisa diperpanjang), bukan akhir rentang pemeriksaan.
                    $batasUnggah = UndanganJadwal::tanggalPendek($aturan['batas']);

                    return $aturan['tertutup']
                        ? 'lewat batas unggah '.$batasUnggah.' — berkas kandidat belum masuk'
                        : 'menunggu kandidat memeriksakan diri & mengunggah hasil (paling lambat '.$batasUnggah
                            .($aturan['diperpanjang'] ? ', diperpanjang' : '').')';
                }

                $batas = ! empty($x->Jadwal_Selesai) ? \Illuminate\Support\Carbon::parse($x->Jadwal_Selesai) : null;
                $lewat = $batas && now()->gt($batas);
                $rentang = UndanganJadwal::rentangPendek($x->Jadwal_Mulai ?? null, $batas);

                return $lewat
                    ? 'rentang '.$rentang.' sudah lewat — hasil belum dicatat'
                    : 'menunggu hasil dari '.mb_strtolower($modeX->Nama ?? 'vendor').' ('.$rentang.')';
            }

            return 'kehadiran belum ditetapkan';
        }

        if ($online) {
            // Nilainya SUDAH masuk, yang ditunggu keputusan penilainya.
            // Dibedakan karena tindakannya berbeda: yang pertama menunggu
            // kandidat/HCLearn, yang kedua menunggu ORANG DI KANTOR INI.
            return ($x->Status ?? '') === 'MENUNGGU_KEPUTUSAN'
                ? 'sudah dites — menunggu keputusan penilai'
                : 'hasil ujian belum masuk';
        }

        // MCU: yang ditunggu adalah STATUS KESEHATANNYA, bukan verdict lulus/gagal
        // — verdict-nya diturunkan dari status itu (lihat Flag_Lolos di master).
        if (($x->Tipe_Tahap_Kode ?? '') === 'MCU') {
            return empty($x->Mcu_Status) ? 'hasil MCU belum dicatat' : null;
        }

        // SKRINING: kuesionernya HARUS terisi sebelum aktivitas ini dianggap
        // beres. Diperiksa sebelum $dicatatTim karena alasannya lebih tepat —
        // "hasil belum dicatat" tidak memberi tahu bahwa yang kurang adalah
        // kuesioner telepon, dan rekruter akan mencarinya di kotak catatan.
        if ($sisaSkrining = \App\Support\Career\Skrining::belumTuntas($x)) {
            return $sisaSkrining;
        }

        if ($dicatatTim) {
            return 'hasil belum dicatat';
        }

        return null;
    }

    /**
     * PATCH /api/v1/lamaran/tahap/{id}/putus — admin ketuk palu.
     *
     * Boleh membawa HASIL MCU sekaligus. Pemeriksaan kesehatan tidak punya
     * keputusan sendiri yang lepas dari nasib tahapnya, jadi keduanya dikirim
     * dalam satu permintaan — mustahil ada keputusan tanpa hasil kesehatannya.
     */
    /**
     * TAHAP-TAHAP INI MILIK LOKER YANG BOLEH SAYA KERJAKAN? — galat, atau null.
     *
     * Menerima id LAMARAN_TAHAP (bukan lamaran), karena itulah yang dibawa
     * seluruh titik tulis worklist: putus, hold, ulang, unggah berkas.
     *
     * ── KENAPA GERBANG INI ADA, PADAHAL DAFTARNYA SUDAH DISARING ────────────
     *
     * Worklist memang hanya menampilkan kandidat loker sendiri. Tapi daftar
     * yang tersaring bukan gerbang: id-nya bisa datang dari tab yang sudah
     * terbuka sebelum lokernya diserahterimakan, dari tautan yang dibagikan
     * rekan, atau langsung tanpa lewat layar sama sekali. Yang menentukan
     * boleh-tidaknya sebuah keputusan diketuk adalah baris ini.
     *
     * Pemegang LINTAS_PIC dilewatkan — itulah gunanya aksi itu ada — dan
     * terobosannya dicatat, bukan didiamkan.
     *
     * @param  array<int, int>|int  $tahapIds
     */
    private function galatPicTahap($tahapIds)
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', (array) $tahapIds))));
        if (! $ids) {
            return null;
        }

        $boleh = AksesService::picDiizinkan(self::PAGE);
        if ($boleh === null) {
            return null;   // lingkup SEMUA
        }

        $luar = DB::table('N_WEB_CAREERS_Lamaran_Tahap as t')
            ->join('N_WEB_CAREERS_Lamaran as l', 'l.Id_Lamaran', '=', 't.Lamaran_Id')
            ->leftJoin('N_WEB_CAREERS_Program_Posisi as x', 'x.Id_Program_Posisi', '=', 'l.Program_Posisi_Id')
            ->whereIn('t.Id_Lamaran_Tahap', $ids)
            ->where(function ($w) use ($boleh) {
                $w->whereNull('x.Pic_Kode_Karyawan')
                    ->orWhereNotIn('x.Pic_Kode_Karyawan', $boleh ?: ['__tidak_ada__']);
            })
            ->distinct()
            ->pluck('l.Kode');

        if ($luar->isEmpty()) {
            return null;
        }

        if (AksesService::boleh(self::PAGE, 'LINTAS_PIC')) {
            Log::channel('web_career')->info(sprintf(
                '[LINTAS_PIC] %s mengerjakan kandidat di luar lokernya: %s',
                session('career_auth.nama', 'ADMIN'),
                $luar->take(10)->implode(', ')
            ));

            return null;
        }

        return ResponseHelper::error(
            'Kandidat berikut berada di loker yang bukan tanggung jawab Anda: '
            .$luar->take(5)->implode(', ')
            .($luar->count() > 5 ? ' (dan '.($luar->count() - 5).' lainnya)' : '')
            .'. Minta pemegang lokernya, atau minta serah terima ke admin.',
            403
        );
    }

    public function putus(Request $request, string $id)
    {
        $realId = Hashids::decode($id)[0] ?? null;
        if (! $realId) {
            return ResponseHelper::error('Tahap tidak valid.', 422);
        }

        if ($galat = $this->galatPicTahap($realId)) {
            return $galat;
        }

        $data = $request->validate([
            // Daftar hasil dibaca dari MASTER, bukan ditulis mati — menambah
            // hasil baru cukup satu baris di Master Hasil Keputusan.
            'hasil' => ['required', Rule::in(\App\Support\Career\LamaranService::masterHasilKeputusan()->keys()->all())],
            // ── TANPA BATAS PANJANG, DAN ITU DISENGAJA ───────────────────────
            //
            // Kolomnya VARCHAR(MAX). Batas di sini dulu 500 dan 200.000; yang
            // pertama tercapai oleh catatan wawancara biasa, yang kedua oleh
            // catatan berisi pindaian lembar penilaian. Keduanya menolak SETELAH
            // penilai selesai menulis — isinya hilang, dan tak ada cara
            // memperpendeknya tanpa membuang penilaian yang memang perlu ada.
            //
            // Menggeser angkanya hanya memindahkan tanggal kejadiannya. Yang
            // membatasi sekarang adalah ukuran badan permintaan (post_max_size),
            // satu tempat, dan itu memang batas yang benar.
            'catatan' => 'nullable|string',
            // Alasan berformat — keputusan yang menutup lamaran orang layak
            // ditulis selengkap catatan wawancara, bukan satu baris.
            'catatanHtml' => 'nullable|string',
            // Catatan UNTUK KANDIDAT (eksternal) — terpisah dari catatan tim di
            // atas. Lihat CatatanEksternal.
            'catatanEksternalHtml' => 'nullable|string',
            // Kandidat ini disimpan di Talent Pool atau tidak. HANYA dipakai
            // untuk hasil ber-Flag_Pilih_Talent_Pool='Y' (pengunduran diri &
            // penolakan penawaran); hasil lain tetap mengikuti masternya.
            'talentPool' => 'nullable|boolean',
            // Tanggal kandidat MENYATAKAN mundur/menolak — beda dari kapan admin
            // mencatatnya. Kandidat kerap mengabari lewat telepon beberapa hari
            // sebelum tercatat, dan selisih itu menentukan sejak kapan kursinya
            // sebenarnya kosong.
            'tanggalKonfirmasi' => 'nullable|date',
        ]);

        try {
            // Keputusan yang datang DARI KANDIDAT menuntut alasan yang benar-benar
            // ditulis. Batas 10 karakter menyaring isian asal seperti "-" atau
            // "ok" yang tak berguna saat ditinjau berbulan-bulan kemudian.
            $defHasil = \App\Support\Career\LamaranService::masterHasilKeputusan()->get($data['hasil']);

            // Alasannya dihitung dari catatan berformat bila itu yang diisi —
            // penilai yang menulis di editor tidak menyentuh field polos sama
            // sekali, dan tanpa ini ia ditolak "alasan wajib diisi" padahal
            // baru saja menulis tiga paragraf.
            [$htmlPutus, $catatanPutus] = self::catatanKaya($data['catatanHtml'] ?? null, $data['catatan'] ?? null);

            // Catatan untuk kandidat: gambar dibuang (rute gambar catatan hanya
            // untuk admin), dan hanya disimpan bila kolomnya sudah ada.
            $eksternal = CatatanEksternal::saring($data['catatanEksternalHtml'] ?? null);
            $eksternalSiap = CatatanEksternal::siap();

            if (($defHasil->Flag_Oleh_Kandidat ?? 'T') === 'Y'
                && mb_strlen(trim((string) $catatanPutus)) < 10) {
                return ResponseHelper::error('Alasan wajib diisi, minimal 10 karakter.', 422);
            }

            // ── GERBANG KETUNTASAN ──────────────────────────────────────────
            //
            // Hasil yang MEMAJUKAN kandidat (Lolos, Talent Pool) menyatakan
            // "orang ini cukup baik". Pernyataan itu tidak boleh dibuat selama
            // masih ada aktivitas penentu yang belum dijadwalkan, belum
            // ditetapkan kehadirannya, atau belum dicatat hasilnya.
            //
            // DITEGAKKAN DI SINI, bukan cukup dengan mematikan tombolnya. Layar
            // bisa basi (jadwal baru saja dihapus di tab lain), dan pintu ini
            // tetap bisa diketuk langsung tanpa lewat layar sama sekali.
            //
            // Sebabnya DISEBUTKAN satu per satu. "Tidak bisa diloloskan" tanpa
            // keterangan cuma memindahkan tebakan ke orang berikutnya.
            if (($defHasil->Flag_Butuh_Tuntas ?? 'T') === 'Y') {
                $sisa = self::belumTuntasTahap(
                    DB::table('N_WEB_CAREERS_Lamaran_Tahap_Tes')
                        ->where('Lamaran_Tahap_Id', $realId)->orderBy('Urutan')->get(),
                    DB::table('N_WEB_CAREERS_Lamaran_Tahap')
                        ->where('Id_Lamaran_Tahap', $realId)->value('Urutan_Aktivitas'),
                );

                if ($sisa) {
                    $rinci = collect($sisa)->map(fn ($s) => "{$s['label']} ({$s['sebab']})")->implode(', ');

                    return ResponseHelper::error(
                        "\"{$defHasil->Nama}\" belum bisa diambil — masih ada aktivitas yang belum tuntas: {$rinci}. "
                        .'Selesaikan dulu, atau gunakan Tidak Lolos / Tahan Dulu bila memang harus ditutup sekarang.',
                        422,
                    );
                }
            }

            // Pilihan Talent Pool hanya berlaku bila masternya memang menyerahkan
            // keputusan itu ke admin. Untuk hasil lain, apa pun yang dikirim
            // layar diabaikan — arti LULUS/GUGUR tidak boleh bisa digeser dari
            // sisi klien.
            $talentPool = ($defHasil->Flag_Pilih_Talent_Pool ?? 'T') === 'Y'
                ? (bool) ($data['talentPool'] ?? (($defHasil->Flag_Talent_Pool ?? 'T') === 'Y'))
                : null;

            // GERBANG CUT-OFF. Tahap yang berada SEBELUM titik cut-off Talent
            // Pool tidak boleh menyimpan siapa pun — apa pun yang dikirim layar.
            //
            // Ditegakkan di server, bukan cukup dengan menyembunyikan pilihannya:
            // layar bisa basi (cut-off alur baru saja diubah) atau dilewati lewat
            // DevTools, dan akibatnya kandidat yang belum pernah dinilai masuk
            // Talent Pool tanpa ada yang menyadarinya.
            $tahapCutoff = DB::table('N_WEB_CAREERS_Lamaran_Tahap')
                ->where('Id_Lamaran_Tahap', $realId)
                ->value('Flag_Talent_Pool');

            if ($talentPool && ($tahapCutoff ?? 'T') !== 'Y') {
                $talentPool = false;
            }

            $tahap = DB::table('N_WEB_CAREERS_Lamaran_Tahap')->where('Id_Lamaran_Tahap', $realId)->first(['Lamaran_Id']);

            // ── SATU TRANSAKSI: CATATANNYA IKUT BATAL BILA PALUNYA DITOLAK ───
            //
            // Dua pembaruan di bawah dulu berdiri sendiri, dijalankan SEBELUM
            // gerbang-gerbang di ketukPalu() sempat berkata "tidak". Akibatnya
            // keputusan yang DITOLAK tetap meninggalkan jejaknya: tanggal
            // konfirmasi kandidat dan catatan keputusan sudah tertulis di tahap
            // yang sebenarnya tidak jadi diputus. Orang berikutnya membaca
            // tahap berstatus BERJALAN yang memuat alasan penolakan lengkap —
            // dan tidak punya cara tahu bahwa keputusan itu tak pernah terjadi.
            //
            // Sekarang keduanya sehidup-semati dengan palunya.
            $hasil = DB::transaction(function () use ($realId, $data, $htmlPutus, $catatanPutus, $talentPool, $eksternal, $eksternalSiap) {
                if (! empty($data['tanggalKonfirmasi'])) {
                    DB::table('N_WEB_CAREERS_Lamaran_Tahap')
                        ->where('Id_Lamaran_Tahap', $realId)
                        ->update(['Tanggal_Konfirmasi_Kandidat' => $data['tanggalKonfirmasi']]);
                }

                // Versi berformatnya disimpan terpisah: ketukPalu() hanya menerima
                // teks polos karena ia juga dipanggil mesin (auto-gugur) yang tak
                // pernah punya HTML.
                if ($htmlPutus !== null) {
                    DB::table('N_WEB_CAREERS_Lamaran_Tahap')
                        ->where('Id_Lamaran_Tahap', $realId)
                        ->update(['Catatan_Html' => $htmlPutus]);
                }

                // Catatan UNTUK KANDIDAT — sehidup-semati dengan palunya seperti
                // catatan tim di atas: keputusan yang ditolak tidak boleh
                // meninggalkan pesan yang kelak terbaca kandidat.
                if ($eksternal !== null && $eksternalSiap) {
                    DB::table('N_WEB_CAREERS_Lamaran_Tahap')
                        ->where('Id_Lamaran_Tahap', $realId)
                        ->update([CatatanEksternal::KOLOM => $eksternal]);
                }

                // HASIL MCU TIDAK LAGI DITULIS DI SINI.
                //
                // Status kesehatan dicatat saat KEHADIRAN ditetapkan — satu
                // peristiwa: kandidat datang ke klinik, diperiksa, inilah hasilnya.
                // Membiarkan pintu kedua di jendela keputusan berarti dua tempat
                // bisa menulis kolom yang sama dan berselisih diam-diam: "Unfit"
                // dari klinik lalu ditimpa "Fit" oleh orang yang mengetuk palu.
                // Lihat subTesKehadiran().

                $r = $this->svc->ketukPalu((int) $realId, $data['hasil'], $catatanPutus, (int) session('career_auth.id'), $talentPool);

                // ketukPalu() MENOLAK lewat nilai balik, bukan lemparan — dan
                // nilai balik tidak membatalkan transaksi. Ditinggikan jadi
                // lemparan supaya penolakannya benar-benar menggulung balik dua
                // pembaruan di atas. DomainException dipilih karena artinya
                // memang itu: aturan bisnis menolak, bukan sistem yang rusak —
                // dan penangkapnya di bawah menerjemahkannya jadi 422, bukan 500.
                if (! $r['ok']) {
                    throw new \DomainException($r['pesan']);
                }

                return $r;
            });

            // KABARI KANDIDAT. Keputusan admin sebelumnya tidak mengirim email
            // sama sekali — kandidat baru tahu kalau kebetulan membuka portal.
            //
            // TALENT_POOL sengaja TIDAK dikabari: ia bukan hasil seleksi yang
            // perlu diumumkan, melainkan catatan internal bahwa kandidat
            // disimpan untuk kesempatan lain. Mengirim email untuk itu justru
            // membingungkan — kandidat merasa diterima padahal tidak.
            // SIAPA YANG DIKABARI DIBACA DARI MASTER, bukan dari daftar mati.
            //
            // Master_Hasil_Keputusan sudah lama punya Flag_Kirim_Email —
            // dan kode ini tidak pernah membacanya. Yang berlaku adalah daftar
            // ['LULUS','GUGUR'] yang ditulis di sini. Akibatnya dua hal yang
            // sama-sama sunyi: admin yang mematikan flag itu di master tidak
            // mengubah apa pun, dan hasil keputusan BARU — mis. "Ditangguhkan" —
            // lahir tanpa email meski masternya menyalakannya.
            //
            // TALENT_POOL, DITOLAK_KANDIDAT, dan MENGUNDURKAN_DIRI tetap diam,
            // persis seperti sebelumnya: ketiganya sudah ber-Flag_Kirim_Email='T'
            // di master. Kandidat yang disimpan di Talent Pool memang tidak layak
            // dikirimi surat "belum dapat kami lanjutkan" — itu bukan penolakan
            // dan bukan kelulusan; dan yang menutup lamarannya sendiri tidak
            // perlu diberi tahu keputusan yang ia buat sendiri.
            //
            // Lulus-atau-tidaknya pun dari Flag_Lolos, sebab itulah yang
            // menentukan surat mana yang dikirim.
            // ── EMAIL PENOLAKAN: REKRUTMEN YA, MT TIDAK ──────────────────
            //
            // Master menyalakan email untuk GUGUR, dan itu benar untuk
            // Rekrutmen: pelamar posisi biasa melamar ke banyak tempat dan
            // berhak tahu kursinya sudah tertutup.
            //
            // Management Trainee berbeda. Pesertanya seangkatan, hasilnya
            // diumumkan serentak oleh HC lewat kanal resmi program. Surat
            // otomatis per orang mendahului pengumuman itu — peserta tahu
            // dirinya gugur sebelum angkatannya diberi tahu apa pun, dan HC
            // kehilangan kendali atas kabar yang seharusnya ia sampaikan.
            //
            // Kategorinya dibaca dari program, bukan dari layar: pintu ini
            // bisa diketuk langsung tanpa lewat Worklist sama sekali.
            if ($tahap && ($defHasil->Flag_Kirim_Email ?? 'T') === 'Y') {
                $this->kirimEmailHasilTahap((int) $tahap->Lamaran_Id, ($defHasil->Flag_Lolos ?? 'T') === 'Y');
            }

            // Kolom belum dibuat (skrip SQL belum dijalankan) — keputusannya tetap
            // sah, tapi admin harus tahu pesannya TIDAK sampai ke kandidat.
            $pesan = $hasil['pesan'];
            if ($eksternal !== null && ! $eksternalSiap) {
                $pesan .= ' Catatan untuk kandidat belum bisa disimpan — kolomnya belum dibuat di basis data.';
            }

            return ResponseHelper::success(null, $pesan);
        } catch (\DomainException $e) {
            // Penolakan aturan bisnis dari ketukPalu() — transaksinya sudah
            // digulung balik, jadi tidak ada satu pun kolom yang berubah.
            return ResponseHelper::error($e->getMessage(), 422);
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('Gagal ketuk palu: '.$e->getMessage());

            return ResponseHelper::error('Gagal memproses keputusan.', 500);
        }
    }

    /**
     * PATCH /api/v1/karir/lamaran/tahap/putus-massal — KETUK PALU BANYAK SEKALIGUS.
     *
     * Satu angkatan diputus dalam satu peristiwa: sesudah rapat panel, sesudah
     * hasil psikotes turun, sesudah MCU seangkatan keluar. Membuka drawer
     * kandidat satu per satu untuk itu berarti mengulang pekerjaan yang sama
     * puluhan kali — dan yang terlewat di tengah daftar tidak meninggalkan
     * jejak apa pun.
     *
     * TIAP ITEM DIPUTUS LEWAT putus() YANG SAMA, bukan lewat salinan aturannya.
     * Keputusan seleksi menyimpan terlalu banyak gerbang untuk digandakan:
     * ketuntasan aktivitas, cut-off Talent Pool, alasan wajib bagi keputusan
     * dari kandidat, dan pengiriman email yang dibaca dari master. Dua salinan
     * pasti berselisih, dan selisihnya berarti sebagian kandidat diputus dengan
     * aturan yang berbeda dari yang lain — tanpa satu pun galat muncul.
     *
     * TIAP ITEM PUNYA TRANSAKSINYA SENDIRI di dalam putus(). Satu kandidat yang
     * gerbangnya menolak tidak boleh membatalkan sembilan belas keputusan yang
     * sudah benar.
     */
    /**
     * ISI HASIL AKTIVITAS YANG BELUM TERCATAT, MENGIKUTI KEPUTUSAN.
     *
     * Gerbang ketuntasan menuntut tiap aktivitas punya hasil sebelum tahap
     * boleh diputus. Untuk satu orang itu wajar; untuk lima puluh orang yang
     * hasilnya sama, admin harus menekan "Tidak Lulus" lima puluh kali dulu
     * SEBELUM bisa menekan keputusan massal — pekerjaan yang sama dikerjakan
     * dua putaran, dan putaran pertamanya tidak menambah satu pun informasi
     * yang tak sudah terkandung di keputusan massalnya.
     *
     * YANG DIISI hanya yang jawabannya memang sudah terkandung dalam
     * keputusan itu: verdict lulus/gagal, dan kehadiran pada aktivitas yang
     * jadwalnya sudah ada.
     *
     * YANG SENGAJA DILEWATI — dan disebut namanya kepada admin:
     *
     *   bernilai angka/kategori  Angkanya hasil pengukuran. Menuliskan
     *                            "tepat di ambang batas" berarti sistem
     *                            mengarang skor yang kelak dibaca sebagai
     *                            hasil ukur sungguhan.
     *   ujian online             Nilainya milik HCLearn. Yang belum masuk
     *                            tidak bisa disimpulkan dari sini.
     *   MCU                      Status kesehatan orang tidak boleh
     *                            diturunkan dari keputusan rekrutmen.
     *   belum dijadwalkan        Tidak ada peristiwa yang bisa dinilai.
     *
     * KEHADIRAN SELALU DITANDAI HADIR, tidak pernah "tidak hadir" — bahkan
     * saat keputusannya tidak lolos. "Tidak hadir" adalah pernyataan tentang
     * perbuatan kandidat, bukan tentang nilainya; orang yang datang lalu
     * gagal akan tercatat mangkir, dan catatan itu ikut terbawa ke lamaran
     * berikutnya.
     *
     * TIDAK MEMANGGIL evaluasiTahap(). Yang menyimpulkan tahap ini adalah
     * putus() sesudahnya. Bila evaluasi ikut dijalankan di sini, tahap
     * bermode otomatis akan menyimpulkan sendiri lebih dulu — lalu putus()
     * menolak "tahap sudah diputus", dan admin melihat kegagalan atas
     * keputusan yang justru berhasil.
     *
     * @return array{terisi:int, dilewati:array<int,string>}
     */
    private function catatAktivitasIkutKeputusan(int $tahapId, bool $lolos): array
    {
        $nama = session('career_auth.nama', 'ADMIN');
        $mode = self::masterModePenilaian();
        $terisi = 0;
        $dilewati = [];

        // MENAIK — pada tahap berurutan, aktivitas berikutnya baru terbuka
        // setelah yang di depannya final. Diproses dari belakang, seluruhnya
        // tertahan giliran dan tak satu pun terisi.
        $subs = DB::table('N_WEB_CAREERS_Lamaran_Tahap_Tes')
            ->where('Lamaran_Tahap_Id', $tahapId)
            ->orderBy('Urutan')
            ->get();

        foreach ($subs as $x) {
            // Sudah final, atau sudah dinyatakan tidak hadir — tak ada yang kurang.
            if (($x->Flag_Selesai ?? 'N') === 'Y' || ($x->Jadwal_Hadir ?? null) === 'T') {
                continue;
            }

            $tipe = self::masterTipeTahap()[$x->Tipe_Tahap_Kode ?? ''] ?? null;
            $online = ($tipe->Perilaku_Kode ?? null) === 'CAT' || ($x->Provider ?? '') === 'THIRD_PARTY';
            $isMcu = ($x->Tipe_Tahap_Kode ?? '') === 'MCU';
            $tipeNilai = $mode->get((string) ($x->Penilaian_Mode ?: 'TANPA_NILAI'))->Tipe_Nilai ?? 'NONE';

            // Penawaran berdokumen memang tidak menuntut apa pun di rapor —
            // suratnya diunggah di jendela keputusan. Dibiarkan apa adanya.
            if (($tipe->Flag_Penawaran ?? 'T') === 'Y' && ($tipe->Flag_Jadwal ?? 'T') !== 'Y') {
                continue;
            }

            if (($tipe->Flag_Jadwal ?? 'T') === 'Y' && empty($x->Jadwal_Mulai)) {
                $dilewati[] = "{$x->Label} (belum dijadwalkan)";

                continue;
            }
            if ($isMcu) {
                $dilewati[] = "{$x->Label} (status kesehatan harus dicatat sendiri)";

                continue;
            }
            if ($tipeNilai !== 'NONE') {
                $dilewati[] = "{$x->Label} (bernilai angka — isi sendiri)";

                continue;
            }
            // Ujian online: hanya yang nilainya SUDAH masuk dan tinggal menunggu
            // keputusan penilai. Yang hasilnya belum datang tidak bisa disimpulkan.
            if ($online && ! (($x->Peran ?? '') === 'INFORMATIF' && ($x->Status ?? '') === 'MENUNGGU_KEPUTUSAN')) {
                $dilewati[] = "{$x->Label} (hasil ujian belum masuk)";

                continue;
            }

            $isi = [
                'Status' => 'SELESAI',
                // INFORMATIF non-ujian memang tak menyimpan verdict — perannya
                // bahan pertimbangan, bukan penentu.
                'Hasil' => (($x->Peran ?? '') === 'INFORMATIF' && ! $online) ? null : ($lolos ? 'LULUS' : 'GAGAL'),
                'Flag_Selesai' => 'Y',
                'Waktu_Selesai' => now(),
                'Updated_At' => now(),
                'Updated_By' => $nama,
                'Updated_By_Id' => session('career_auth.id'),
            ];

            // Kehadiran hanya ditetapkan bila memang ada jadwalnya.
            if (! empty($x->Jadwal_Mulai) && empty($x->Jadwal_Hadir)) {
                $isi['Jadwal_Hadir'] = 'Y';
                $isi['Jadwal_Hadir_At'] = now();
                $isi['Jadwal_Hadir_By'] = $nama;
            }

            // JEJAKNYA DISEBUT. Catatan kosong pada aktivitas yang hasilnya
            // muncul entah dari mana membuat peninjau berikutnya mengira ada
            // penilai yang menuliskannya.
            if (empty($x->Catatan)) {
                $isi['Catatan'] = 'Terisi mengikuti keputusan massal tahap ini.';
            }

            DB::table('N_WEB_CAREERS_Lamaran_Tahap_Tes')
                ->where('Id_Lamaran_Tahap_Tes', $x->Id_Lamaran_Tahap_Tes)
                ->update($isi);
            $terisi++;
        }

        return ['terisi' => $terisi, 'dilewati' => $dilewati];
    }

    /**
     * PATCH /api/v1/karir/lamaran/tahap/putus-massal — ANTREKAN keputusan.
     *
     * TIDAK LAGI MEMUTUS DI DALAM PERMINTAAN INI. Satu keputusan bukan satu
     * baris UPDATE: ia menjalankan mesin syarat, menulis riwayat tahap, membuka
     * tahap berikutnya, dan menerbitkan tugas email. Lima puluh kali pekerjaan
     * itu melewati batas waktu permintaan — dan yang terjadi bukan "gagal
     * semua", melainkan yang lebih buruk: sebagian orang pertama benar-benar
     * diputus, sisanya tidak, lalu layar memuntahkan satu galat tanpa menyebut
     * siapa yang sudah selesai. Admin menekan ulang, dan yang terlanjur diputus
     * ditolak "tahap sudah diputus". Pada 150 kandidat, jalur itu tidak pernah
     * bisa tuntas.
     *
     * Sekarang tiap keputusan dikirim ke antrean `wc-putusmassal` sebagai satu
     * job, dan permintaan ini selesai dalam sekejap.
     *
     * ── TANPA TABEL BARU ────────────────────────────────────────────────────
     *
     * Seluruh keadaan gelombang sudah punya tempatnya masing-masing:
     *
     *   kiriman keputusan  payload job di antrean (N_WEB_CAREERS_Jobs untuk
     *                      driver database; Cloud Tasks di produksi);
     *   sudah diputus      N_WEB_CAREERS_Lamaran_Tahap.Diputus_At — kebenaran
     *                      yang sesungguhnya, bukan salinan status;
     *   gagal + sebabnya   N_WEB_CAREERS_Failed_Jobs (Jenis, Payload,
     *                      Exception) lewat CatatGagalWebCareers.
     *
     * Tabel keempat hanya akan menyalin ketiganya, dan salinan itu yang kelak
     * berselisih dengan aslinya.
     *
     * `gelombang` adalah kode yang MENGIKAT ketiganya — dibuat di sini, ikut di
     * payload, dan dipakai panel untuk bertanya "gelombang ini sudah sampai
     * mana".
     */
    public function putusMassal(Request $request)
    {
        // BATASNYA LIMA, BUKAN 200 — LIHAT self::BATAS_PUTUS_MASSAL.
        //
        // Hold & penjadwalan massal boleh 200 karena keduanya tidak menerbitkan
        // satu email per orang dengan badan surat yang sama. Keputusan
        // menerbitkannya, dan 200 email serempak dari satu pengirim adalah pola
        // yang membuat penyedia surat menangguhkan akunnya.
        //
        // Pesannya ditulis sendiri: bawaan Laravel berbunyi "item tidak boleh
        // lebih dari 5 item", yang tidak menyebut sebabnya sama sekali.
        $data = $request->validate([
            'item' => 'required|array|min:1|max:'.self::BATAS_PUTUS_MASSAL,
            'item.*.tahapId' => 'required|string|max:64',
            'item.*.hasil' => ['required', Rule::in(\App\Support\Career\LamaranService::masterHasilKeputusan()->keys()->all())],
            'item.*.catatan' => 'nullable|string',
            'item.*.catatanHtml' => 'nullable|string',
            'item.*.catatanEksternalHtml' => 'nullable|string',
            'item.*.talentPool' => 'nullable|boolean',
            'item.*.tanggalKonfirmasi' => 'nullable|date',
            // SEKALIAN CATAT HASIL AKTIVITAS YANG BELUM TERCATAT.
            //
            // Dikirim layar sebagai satu setelan untuk seluruh gelombang, bukan
            // per orang: yang dinyatakan admin adalah "hasil aktivitas mereka
            // sama dengan keputusan ini", dan pernyataan itu berlaku sama untuk
            // semua yang ia pilih.
            'catatAktivitas' => 'nullable|boolean',
        ], [
            'item.max' => 'Maksimal '.self::BATAS_PUTUS_MASSAL.' kandidat sekali kirim — pembatas ini menjaga alamat pengirim kami tidak ditangguhkan penyedia email. Kirim sisanya pada gelombang berikutnya.',
        ]);

        // Gerbang PIC untuk SELURUH gelombang sekaligus, sebelum satu pun
        // diproses. Memeriksanya per baris di dalam gelung akan meloloskan
        // sebagian lalu menolak sisanya — dan gelombang keputusan yang separuh
        // jalan adalah keadaan yang paling sulit dibereskan.
        if ($galat = $this->galatPicTahap(
            collect($data['item'])->map(fn ($it) => Hashids::decode($it['tahapId'])[0] ?? null)->filter()->all()
        )) {
            return $galat;
        }

        $catatAktivitas = (bool) ($data['catatAktivitas'] ?? false);

        // Kode gelombang: waktu + acak pendek. Waktunya di depan supaya kode
        // yang lebih baru selalu terbaca lebih besar saat diurutkan sebagai teks.
        $gelombang = 'KPM-'.now()->format('ymdHis').'-'.strtoupper(Str::random(4));

        $admin = [
            'id' => (int) session('career_auth.id'),
            'nama' => session('career_auth.nama', 'ADMIN'),
        ];

        $antre = [];
        $tolak = [];

        foreach ($data['item'] as $it) {
            $realId = Hashids::decode($it['tahapId'])[0] ?? null;

            // Nama diambil DI SINI, selagi permintaan masih membawa sesi dan
            // satu kueri melayani seluruh daftar. Laporan gagal yang cuma
            // menyebut id tahap tidak dikenali siapa pun yang membacanya.
            //
            // AKUNNYA LEFT JOIN — dan itu bukan kerapian, itu perbaikan bug.
            //
            // Dengan INNER JOIN, kandidat yang barisan akunnya sudah tidak ada
            // menghasilkan nol baris, dan SELURUH gelombang ditolak "Tahap tidak
            // ditemukan" — padahal tahapnya jelas ada dan kartunya sedang
            // terpampang di papan. Nama kandidat bukan syarat sah-tidaknya sebuah
            // keputusan; ia cuma label pada laporan.
            $t = $realId
                ? DB::table('N_WEB_CAREERS_Lamaran_Tahap as t')
                    ->join('N_WEB_CAREERS_Lamaran as l', 'l.Id_Lamaran', '=', 't.Lamaran_Id')
                    ->leftJoin('N_WEB_CAREERS_Users as u', 'u.Id_Users', '=', 'l.Id_Users')
                    ->where('t.Id_Lamaran_Tahap', $realId)
                    ->first(['u.Nama', 't.Label', 't.Diputus_At', 'l.Program_Id', 'l.Id_Lamaran', 'l.Created_By'])
                : null;

            if (! $t) {
                $tolak[] = ['tahapId' => $it['tahapId'], 'nama' => null, 'pesan' => 'Tahap tidak ditemukan.'];

                continue;
            }

            // Nama resmi dari formulir dipakai bila akunnya sudah tidak ada —
            // laporan yang berbunyi "(?, ?, ?)" tidak menolong siapa pun.
            $namaKandidat = $t->Nama
                ?: (self::namaResmiPerLamaran([(int) $t->Id_Lamaran])->get((int) $t->Id_Lamaran)
                    ?: ($t->Created_By ?: 'Kandidat'));

            // SUDAH DIPUTUS — ditolak DI SINI, bukan setelah mengantre.
            // Gelombang yang isinya sebagian sudah selesai akan menampilkan
            // kegagalan yang menakutkan ("tahap sudah diputus") untuk orang yang
            // justru tidak kurang apa pun.
            if ($t->Diputus_At) {
                $tolak[] = ['tahapId' => $it['tahapId'], 'nama' => $namaKandidat, 'pesan' => 'Tahap ini sudah diputus sebelumnya.'];

                continue;
            }

            $muatan = [
                'gelombang' => $gelombang,
                'tahapId' => $it['tahapId'],
                'tahapRealId' => (int) $realId,
                'nama' => $namaKandidat,
                'tahapLabel' => $t->Label,
                'programId' => (int) $t->Program_Id,
                'hasil' => $it['hasil'],
                'catatan' => $it['catatan'] ?? null,
                'catatanHtml' => $it['catatanHtml'] ?? null,
                'catatanEksternalHtml' => $it['catatanEksternalHtml'] ?? null,
                'talentPool' => $it['talentPool'] ?? null,
                'tanggalKonfirmasi' => $it['tanggalKonfirmasi'] ?? null,
                'catatAktivitas' => $catatAktivitas,
                'adminId' => $admin['id'],
                'adminNama' => $admin['nama'],
            ];

            \App\Jobs\Career\WcPutusMassalJob::dispatch($muatan);

            $antre[] = ['tahapId' => $it['tahapId'], 'nama' => $namaKandidat];
        }

        Log::channel('web_career')->info(sprintf(
            '[PUTUS MASSAL] gelombang %s diantrekan: %d keputusan, %d ditolak di muka, oleh %s.',
            $gelombang, count($antre), count($tolak), $admin['nama']
        ));

        if (! $antre) {
            return response()->json([
                'success' => false,
                'status' => 422,
                'message' => 'Tidak ada kandidat yang bisa diantrekan. '.($tolak[0]['pesan'] ?? ''),
                'result' => ['gelombang' => $gelombang, 'antre' => [], 'tolak' => $tolak],
            ], 422);
        }

        return ResponseHelper::success(
            ['gelombang' => $gelombang, 'antre' => $antre, 'tolak' => $tolak, 'total' => count($antre)],
            count($antre).' keputusan diantrekan — kemajuannya muncul di panel.',
        );
    }

    /**
     * SATU KEPUTUSAN, DIJALANKAN APA ADANYA. Dipanggil WcPutusMassalJob.
     *
     * Publik karena job-lah pemakainya, dan job bukan bagian dari controller
     * ini. Isinya persis jalur yang dulu dijalankan di dalam perulangan
     * putusMassal(): hasil aktivitas dulu (bila diminta), lalu putus() yang
     * memvalidasi ulang seluruh kirimannya — gerbangnya tetap berlaku utuh,
     * bukan dilewati karena panggilannya datang dari dalam.
     *
     * @return array{ok:bool, pesan:?string, dilewati:array<int,string>}
     */
    public function prosesKeputusanSatu(int $tahapRealId, array $it, bool $catatAktivitas): array
    {
        $dilewati = [];

        if ($catatAktivitas) {
            // Hasil aktivitas dulu, keputusan tahap sesudahnya — urutannya tidak
            // boleh terbalik: gerbang ketuntasan di putus() membaca keadaan
            // aktivitas SAAT ITU JUGA.
            $def = \App\Support\Career\LamaranService::masterHasilKeputusan()->get($it['hasil']);
            $isi = $this->catatAktivitasIkutKeputusan($tahapRealId, ($def->Flag_Lolos ?? 'T') === 'Y');
            $dilewati = $isi['dilewati'];
        }

        // Request buatan sendiri berisi field yang sama dengan yang dikirim
        // drawer satuan. putus() memvalidasinya lagi — itu disengaja.
        $sub = new Request;
        $sub->replace(array_filter([
            'hasil' => $it['hasil'],
            'catatan' => $it['catatan'] ?? null,
            'catatanHtml' => $it['catatanHtml'] ?? null,
            'catatanEksternalHtml' => $it['catatanEksternalHtml'] ?? null,
            'talentPool' => $it['talentPool'] ?? null,
            'tanggalKonfirmasi' => $it['tanggalKonfirmasi'] ?? null,
        ], fn ($v) => $v !== null));

        try {
            $resp = $this->putus($sub, Hashids::encode($tahapRealId));
            $isi = json_decode($resp->getContent(), true) ?: [];

            return $resp->getStatusCode() < 400 && ($isi['success'] ?? false)
                ? ['ok' => true, 'pesan' => null, 'dilewati' => $dilewati]
                : ['ok' => false, 'pesan' => $isi['message'] ?? 'Keputusan ditolak.', 'dilewati' => $dilewati];
        } catch (\Illuminate\Validation\ValidationException $e) {
            // putus() memakai $request->validate(), yang MELEMPAR saat dipanggil
            // langsung (bukan lewat router).
            return [
                'ok' => false,
                'pesan' => collect($e->errors())->flatten()->first() ?? 'Data tidak valid.',
                'dilewati' => $dilewati,
            ];
        }
    }

    /**
     * GET /api/v1/karir/lamaran/putus-massal/progres?gelombang=..&ids=..
     *
     * KEMAJUAN DIHITUNG DARI KENYATAAN, BUKAN DARI CATATAN STATUS.
     *
     * "Selesai" berarti tahapnya benar-benar ber-Diputus_At — bukan "job-nya
     * pernah dilaporkan sukses". Keduanya bisa berselisih, dan yang dipercaya
     * harus yang menentukan nasib kandidat. Pola yang sama dipakai panel antrean
     * penjadwalan, dan alasannya sama: tak ada salinan status yang perlu dijaga
     * tetap jujur.
     *
     * Yang gagal dibaca dari N_WEB_CAREERS_Failed_Jobs — tabel yang memang sudah
     * menampung kegagalan job modul ini, lengkap dengan payload dan pesannya.
     *
     * `ids` datang dari layar karena hanya layar yang tahu isi gelombang ini
     * sebelum satu pun job-nya berjalan. Ia disimpan di sisi peramban, jadi
     * panelnya tetap hidup melewati muat ulang halaman.
     */
    public function progresMassal(Request $request)
    {
        $gelombang = trim((string) $request->query('gelombang', ''));
        if ($gelombang === '') {
            return ResponseHelper::error('Gelombang tidak disebut.', 422);
        }

        $ids = collect(explode(',', (string) $request->query('ids', '')))
            ->map(fn ($h) => Hashids::decode(trim($h))[0] ?? null)
            ->filter()
            ->map(fn ($x) => (int) $x)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return ResponseHelper::error('Daftar tahap kosong.', 422);
        }

        // AKUN LEFT JOIN. Dengan INNER JOIN, kandidat yang barisan akunnya
        // sudah tidak ada membuat kuerinya pulang kosong — dan panel berbunyi
        // "0 dari 0 kandidat" untuk gelombang yang jelas-jelas sedang berjalan.
        $tahap = DB::table('N_WEB_CAREERS_Lamaran_Tahap as t')
            ->join('N_WEB_CAREERS_Lamaran as l', 'l.Id_Lamaran', '=', 't.Lamaran_Id')
            ->leftJoin('N_WEB_CAREERS_Users as u', 'u.Id_Users', '=', 'l.Id_Users')
            ->leftJoin('N_WEB_CAREERS_Program as p', 'p.Id_Program', '=', 'l.Program_Id')
            ->whereIn('t.Id_Lamaran_Tahap', $ids->all())
            ->get(['t.Id_Lamaran_Tahap', 't.Label', 't.Hasil', 't.Diputus_At', 'u.Nama', 'l.Id_Lamaran', 'l.Created_By', 'p.Nama as ProgramNama']);

        // Nama resmi dari formulir untuk yang akunnya sudah tidak ada — satu
        // kueri untuk seluruh gelombang, bukan satu per baris.
        $namaResmi = self::namaResmiPerLamaran($tahap->pluck('Id_Lamaran')->all());

        // Kegagalan gelombang ini. Payload-nya JSON yang kita tulis sendiri di
        // job, jadi id tahapnya bisa dibaca kembali tanpa menebak.
        $gagalRow = DB::table('N_WEB_CAREERS_Failed_Jobs')
            ->where('Jenis', 'PUTUSMASSAL:'.$gelombang)
            ->orderByDesc('Id')
            ->limit(300)
            ->get(['Payload', 'Exception', 'Failed_At']);

        $gagal = [];
        foreach ($gagalRow as $g) {
            $p = json_decode((string) $g->Payload, true) ?: [];
            $kunci = $p['tahapId'] ?? null;
            if ($kunci && ! isset($gagal[$kunci])) {
                $gagal[$kunci] = [
                    'nama' => $p['nama'] ?? null,
                    'pesan' => $g->Exception,
                    'waktu' => (string) $g->Failed_At,
                ];
            }
        }

        $baris = $tahap->map(function ($t) use ($gagal, $namaResmi) {
            $kunci = Hashids::encode($t->Id_Lamaran_Tahap);
            $selesai = (bool) $t->Diputus_At;

            return [
                'tahapId' => $kunci,
                'nama' => $t->Nama ?: ($namaResmi->get((int) $t->Id_Lamaran) ?: ($t->Created_By ?: 'Kandidat')),
                'tahap' => $t->Label,
                'program' => $t->ProgramNama,
                // Yang sudah diputus TIDAK pernah dilaporkan gagal, walau
                // percobaan sebelumnya sempat mencatat kegagalan: kenyataannya
                // ia sudah selesai, dan baris merah untuk orang yang tidak
                // kurang apa pun cuma mengundang keputusan kedua.
                'keadaan' => $selesai ? 'selesai' : (isset($gagal[$kunci]) ? 'gagal' : 'menunggu'),
                'hasil' => $selesai ? $t->Hasil : null,
                'pesan' => $selesai ? null : ($gagal[$kunci]['pesan'] ?? null),
            ];
        })->values();

        return ResponseHelper::success([
            'gelombang' => $gelombang,
            'program' => $baris->first()['program'] ?? null,
            'tahap' => $baris->first()['tahap'] ?? null,
            'total' => $baris->count(),
            'selesai' => $baris->where('keadaan', 'selesai')->count(),
            'gagal' => $baris->where('keadaan', 'gagal')->count(),
            'menunggu' => $baris->where('keadaan', 'menunggu')->count(),
            'baris' => $baris->all(),
        ], 'Kemajuan gelombang keputusan');
    }

    /**
     * POST /api/v1/karir/lamaran/putus-massal/ulang — ANTREKAN ULANG yang gagal.
     *
     * Kirimannya diambil kembali dari payload kegagalan, bukan diminta ulang ke
     * admin: alasan yang sudah ia tulis tidak boleh hilang hanya karena server
     * surat sedang tersendat. Baris kegagalannya dihapus supaya panel tidak
     * memampang kegagalan lama di sebelah percobaan yang sedang berjalan.
     */
    public function ulangMassal(Request $request)
    {
        $data = $request->validate([
            'gelombang' => 'required|string|max:64',
            'tahapId' => 'nullable|array|max:'.self::BATAS_PUTUS_MASSAL,
            'tahapId.*' => 'string|max:64',
        ]);

        $rows = DB::table('N_WEB_CAREERS_Failed_Jobs')
            ->where('Jenis', 'PUTUSMASSAL:'.$data['gelombang'])
            ->orderByDesc('Id')
            ->limit(300)
            ->get(['Id', 'Payload']);

        $pilih = collect($data['tahapId'] ?? []);
        $sudah = [];
        $ulang = 0;

        foreach ($rows as $r) {
            $p = json_decode((string) $r->Payload, true) ?: [];
            $kunci = $p['tahapId'] ?? null;

            if (! $kunci || isset($sudah[$kunci])) {
                continue;
            }
            if ($pilih->isNotEmpty() && ! $pilih->contains($kunci)) {
                continue;
            }

            // Sudah terlanjur berhasil di percobaan lain — jangan pernah
            // mengetuk palu untuk kedua kalinya.
            $diputus = DB::table('N_WEB_CAREERS_Lamaran_Tahap')
                ->where('Id_Lamaran_Tahap', (int) ($p['tahapRealId'] ?? 0))
                ->value('Diputus_At');

            $sudah[$kunci] = true;
            DB::table('N_WEB_CAREERS_Failed_Jobs')->where('Id', $r->Id)->delete();

            if ($diputus) {
                continue;
            }

            \App\Jobs\Career\WcPutusMassalJob::dispatch($p);
            $ulang++;

            // BATAS YANG SAMA BERLAKU DI SINI. Satu gelombang memang tak pernah
            // lebih dari BATAS_PUTUS_MASSAL orang, tapi baris kegagalan
            // menumpuk: percobaan yang gagal berkali-kali meninggalkan beberapa
            // baris untuk kode gelombang yang sama. Tanpa penjaga ini, satu
            // ketukan "Coba Lagi" bisa menerbitkan lebih banyak email daripada
            // yang pernah diizinkan gelombang aslinya — persis yang dijaga
            // batas itu.
            if ($ulang >= self::BATAS_PUTUS_MASSAL) {
                break;
            }
        }

        Log::channel('web_career')->info(
            "[PUTUS MASSAL] gelombang {$data['gelombang']}: {$ulang} keputusan diantrekan ulang oleh ".session('career_auth.nama', 'ADMIN').'.'
        );

        return $ulang
            ? ResponseHelper::success(['ulang' => $ulang], $ulang.' keputusan diantrekan ulang.')
            : ResponseHelper::error('Tidak ada yang perlu diulang.', 422);
    }

    /**
     * PATCH /api/v1/karir/lamaran/tahap/{id}/hold — TAHAN atau LEPASKAN kandidat.
     *
     * Keadaan ketiga yang selama ini tidak ada. Worklist hanya punya dua jalan:
     * putuskan sekarang, atau biarkan menggantung tanpa keterangan. Yang kedua
     * yang selalu dipakai — dan akibatnya tak ada yang bisa membedakan kandidat
     * yang sedang ditunggu dari kandidat yang terlupakan.
     *
     * Kandidat TETAP di bucket tahapnya: tidak dipindah, tidak digugurkan,
     * tidak kehilangan apa pun. Yang bertambah hanya penanda + alasannya.
     *
     * TIDAK MENGIRIM EMAIL / WA — disengaja, dan bukan karena belum sempat.
     * Hold adalah keadaan internal; mengabari kandidat "lamaran Anda ditahan"
     * tidak menjawab apa pun baginya dan hanya menimbulkan kecemasan atas
     * sesuatu yang tak bisa ia pengaruhi. Kabar yang berarti adalah keputusan,
     * dan itu tetap dikirim seperti biasa saat hold dilepas lalu diputus.
     */
    public function hold(Request $request, string $id)
    {
        $realId = Hashids::decode($id)[0] ?? null;
        if ($realId && ($galat = $this->galatPicTahap($realId))) {
            return $galat;
        }
        if (! $realId) {
            return ResponseHelper::error('Tahap tidak valid.', 422);
        }

        $data = $request->validate([
            'hold' => 'required|boolean',
            // Alasan DARI MASTER, bukan ketikan bebas: inilah yang dihitung
            // saat menjelaskan kenapa satu lowongan lama terisi, dan tiga
            // ketikan berbeda untuk sebab yang sama tak akan pernah
            // terkelompokkan.
            'alasanKode' => ['nullable', 'string', 'max:40'],
            'catatan' => 'nullable|string',
            'catatanHtml' => 'nullable|string',
        ]);

        $r = $this->terapkanHold(
            (int) $realId,
            (bool) $data['hold'],
            $data['alasanKode'] ?? null,
            $data['catatanHtml'] ?? null,
            $data['catatan'] ?? null,
        );

        return $r['ok']
            ? ResponseHelper::success(array_key_exists('outcome', $r) ? ['outcome' => $r['outcome']] : null, $r['pesan'])
            : ResponseHelper::error($r['pesan'], $r['status']);
    }

    /**
     * GET .../lamaran/{id}/ulang/tahap — DAFTAR TAHAP YANG BISA DIULANG.
     *
     * Dimuat saat dialog "Ulangi Tahap" dibuka, bukan ikut menempel di muatan
     * worklist. Worklist mengirim satu halaman berisi puluhan kandidat; ikut
     * membawa daftar tahap tiap orang berarti memperbesar SETIAP pemuatan
     * halaman demi dialog yang mungkin tidak pernah dibuka sama sekali.
     *
     * Yang dikembalikan hanya tahap yang SUDAH ATAU SEDANG dijalani. Tahap yang
     * masih MENUNGGU tidak bisa "diulang" — tidak ada yang pernah terjadi di
     * sana untuk diulang.
     */
    public function ulangDaftarTahap(string $id)
    {
        $realId = Hashids::decode($id)[0] ?? null;
        if (! $realId) {
            return ResponseHelper::error('Lamaran tidak valid.', 422);
        }

        $lamaran = DB::table('N_WEB_CAREERS_Lamaran')->where('Id_Lamaran', $realId)->first();
        if (! $lamaran) {
            return ResponseHelper::error('Lamaran tidak ditemukan.', 404);
        }

        $rows = DB::table('N_WEB_CAREERS_Lamaran_Tahap')
            ->where('Lamaran_Id', $realId)
            // Status hanya pernah bernilai MENUNGGU / BELUM / BERJALAN / SELESAI.
            // LULUS, GUGUR, dan DITOLAK adalah nilai kolom HASIL, bukan Status —
            // mencantumkannya di sini hanya membuat penyaring ini terlihat lebih
            // longgar daripada yang sebenarnya.
            ->whereIn('Status', ['BERJALAN', 'SELESAI'])
            ->orderBy('Urutan')
            ->get(['Id_Lamaran_Tahap', 'Urutan', 'Label', 'Status', 'Hasil', 'Diputus_At']);

        $tahap = $rows->map(fn ($t) => [
            'id' => Hashids::encode($t->Id_Lamaran_Tahap),
            'urutan' => (int) $t->Urutan,
            'label' => $t->Label,
            'status' => $t->Status,
            'hasil' => $t->Hasil,
            'diputusAt' => $t->Diputus_At,
        ])->values();

        // Riwayat pengulangan sebelumnya — supaya admin tahu kandidat ini sudah
        // pernah diulang, sebelum ia mengulanginya lagi.
        $riwayat = DB::table('N_WEB_CAREERS_Lamaran_Ulang')
            ->where('Lamaran_Id', $realId)
            ->orderByDesc('Id_Lamaran_Ulang')
            ->limit(20)
            ->get(['Putaran', 'Cakupan', 'Dari_Urutan', 'Sampai_Urutan', 'Alasan', 'Flag_Email', 'Created_At', 'Created_By'])
            ->map(fn ($r) => [
                'putaran' => (int) $r->Putaran,
                'cakupan' => $r->Cakupan,
                'dariUrutan' => (int) $r->Dari_Urutan,
                'sampaiUrutan' => (int) $r->Sampai_Urutan,
                'alasan' => $r->Alasan,
                'email' => $r->Flag_Email === 'Y',
                'at' => $r->Created_At,
                'oleh' => $r->Created_By,
            ]);

        return ResponseHelper::success([
            'tahap' => $tahap,
            'putaran' => (int) ($lamaran->Putaran ?? 1),
            'urutanSekarang' => (int) $lamaran->Urutan_Tahap,
            'riwayat' => $riwayat,
        ]);
    }

    /**
     * PATCH .../lamaran/tahap/{id}/ulang — ULANGI TAHAP.
     *
     * `{id}` adalah tahap TUJUAN: tempat kandidat akan berdiri lagi.
     *
     * == KENAPA ALASAN WAJIB ==
     *
     * Pengulangan membatalkan keputusan yang sudah pernah diketuk seseorang.
     * Tanpa alasan tertulis, yang tersisa di arsip cuma "tahap 5 pernah diulang
     * pada 21 Agustus" — dan pertanyaan yang sesungguhnya, kenapa, baru datang
     * berbulan-bulan kemudian dari orang yang tidak ada di ruangan waktu itu.
     *
     * == KENAPA EMAIL BAWAANNYA MATI ==
     *
     * Sebagian besar pengulangan adalah KOREKSI INTERNAL — jadwal salah ketik,
     * hasil tertukar, token gagal terbit. Mengabari kandidat setiap kali berarti
     * memberitahunya bahwa ada yang keliru di pihak kami, untuk hal yang sering
     * kali sudah dibetulkan sebelum ia sempat membuka portalnya. Yang benar-benar
     * perlu dikabari adalah pengulangan yang mengubah apa yang harus ia kerjakan
     * — dan itu keputusan manusia, bukan bawaan sistem.
     */
    public function ulangTahap(Request $request, string $id)
    {
        $realId = Hashids::decode($id)[0] ?? null;
        if (! $realId) {
            return ResponseHelper::error('Tahap tidak valid.', 422);
        }

        $data = $request->validate([
            'cakupan' => ['required', Rule::in([UlangTahap::CAKUPAN_TAHAP, UlangTahap::CAKUPAN_RANGKAIAN])],
            'alasan' => ['required', 'string', 'min:10', 'max:2000'],
            'alasanHtml' => ['nullable', 'string'],
            'kirimEmail' => ['nullable', 'boolean'],
        ], [
            'alasan.required' => 'Alasan pengulangan wajib diisi.',
            'alasan.min' => 'Alasan terlalu pendek — tuliskan apa yang membuat tahap ini perlu diulang.',
        ]);

        $tahap = DB::table('N_WEB_CAREERS_Lamaran_Tahap')
            ->where('Id_Lamaran_Tahap', (int) $realId)
            ->first(['Id_Lamaran_Tahap', 'Lamaran_Id', 'Urutan', 'Label', 'Status']);

        if (! $tahap) {
            return ResponseHelper::error('Tahap tidak ditemukan.', 404);
        }

        // Gerbang PIC: mengulang tahap membatalkan keputusan yang sudah
        // diketuk, jadi ia menuntut kepemilikan loker yang sama dengan
        // mengetuknya. Lihat galatPicTahap().
        if ($galat = $this->galatPicTahap((int) $realId)) {
            return $galat;
        }

        // MENUNGGU dan BELUM sama-sama berarti "belum pernah dijalani". Keduanya
        // harus ditolak: mengulang tahap yang tak pernah terjadi akan mengarsipkan
        // baris kosong lalu menggeser kandidat MUNDUR ke tahap yang belum ia capai.
        if (! in_array($tahap->Status, ['BERJALAN', 'SELESAI'], true)) {
            return ResponseHelper::error(
                'Tahap ini belum pernah dijalani, jadi tidak ada yang bisa diulang.',
                422
            );
        }

        // Kategori program ikut dijaga, sepola dengan aksi worklist lainnya:
        // admin yang jatahnya hanya REKRUTMEN tidak boleh mengulang tahap
        // kandidat Management Trainee.
        $izin = AksesService::kategoriDiizinkan(self::PAGE);
        if ($izin) {
            $kategori = DB::table('N_WEB_CAREERS_Lamaran')
                ->where('Id_Lamaran', $tahap->Lamaran_Id)
                ->value('Kategori');

            if (! in_array($kategori, $izin, true)) {
                return ResponseHelper::error('Kategori program ini di luar jatah akses Anda.', 403);
            }
        }

        $admin = [
            'id' => session('career_user.id') ?? auth()->id(),
            'nama' => session('career_user.nama') ?? 'SISTEM',
        ];

        try {
            $hasil = UlangTahap::jalankan(
                lamaranId: (int) $tahap->Lamaran_Id,
                urutan: (int) $tahap->Urutan,
                cakupan: $data['cakupan'],
                alasan: $data['alasan'],
                admin: $admin,
                alasanHtml: $data['alasanHtml'] ?? null,
                kirimEmail: (bool) ($data['kirimEmail'] ?? false),
            );
        } catch (\Throwable $e) {
            Log::channel('web_career')->error(
                '[ULANG] lamaran #'.$tahap->Lamaran_Id.' tahap '.$tahap->Urutan.' GAGAL: '.$e->getMessage()
            );

            return ResponseHelper::error('Gagal mengulang tahap: '.$e->getMessage(), 500);
        }

        Log::channel('web_career')->info(sprintf(
            '[ULANG] lamaran #%d kembali ke tahap %d (%s) — %s, %d tahap / %d aktivitas / %d berkas ditandai riwayat. Oleh %s.',
            $tahap->Lamaran_Id,
            $tahap->Urutan,
            $tahap->Label,
            $hasil['cakupan'],
            $hasil['jmlTahap'],
            $hasil['jmlAktivitas'],
            $hasil['jmlBerkas'],
            $admin['nama']
        ));

        $pesan = $hasil['cakupan'] === UlangTahap::CAKUPAN_RANGKAIAN
            ? sprintf('Kandidat dikembalikan ke tahap %d (%s). Tahap %d-%d direset.',
                $hasil['dariUrutan'], $tahap->Label, $hasil['dariUrutan'], $hasil['sampaiUrutan'])
            : sprintf('Tahap %d (%s) diulang.', $hasil['dariUrutan'], $tahap->Label);

        return ResponseHelper::success($hasil, $pesan);
    }

    /**
     * PATCH /api/v1/karir/lamaran/tahap/hold-massal — TAHAN / LEPAS BANYAK.
     *
     * ══ KENAPA PER KANDIDAT, BUKAN SATU ALASAN UNTUK SEMUA ══
     *
     * Penahanan massal lahir dari satu peristiwa ("MPP belum turun"), tapi
     * tidak selalu berakhir begitu: dalam satu angkatan bisa ada tiga orang
     * tertahan kuota, dua menunggu user department, dan satu menunggu kandidat
     * itu sendiri menjawab. Memaksakan satu alasan membuat lima dari enam
     * penahanan itu tercatat SALAH — dan justru laporan "kenapa lowongan ini
     * lama terisi" yang jadi korbannya.
     *
     * Karena itu tiap item membawa alasan & keterangannya sendiri. Layar boleh
     * menawarkan mode seragam sebagai jalan cepat, tapi yang dikirim ke sini
     * tetap daftar per kandidat — server tidak perlu tahu mode mana yang
     * dipakai admin, dan tidak ada aturan kedua yang harus dijaga tetap sama.
     *
     * ══ SATU GAGAL TIDAK MEMBATALKAN SISANYA ══
     *
     * Tiap item punya transaksinya sendiri. Membungkus semuanya dalam satu
     * transaksi berarti satu kandidat yang tahapnya kebetulan sudah diputus
     * rekan sebelah membatalkan 19 penahanan yang sudah benar — dan admin
     * harus mengulang seluruh pemilihan tanpa tahu yang mana penyebabnya.
     * Yang gagal dilaporkan satu per satu berikut sebabnya.
     */
    public function holdMassal(Request $request)
    {
        $data = $request->validate([
            'hold' => 'required|boolean',
            // Batas 200 mengikuti penjadwalan massal — satu angkatan rekrutmen
            // tidak pernah sebesar itu, dan batasnya menjaga permintaan tunggal
            // tidak berubah jadi pekerjaan menit-menitan.
            'item' => 'required|array|min:1|max:200',
            'item.*.tahapId' => 'required|string|max:64',
            'item.*.alasanKode' => 'nullable|string|max:40',
            'item.*.catatan' => 'nullable|string',
            'item.*.catatanHtml' => 'nullable|string',
        ]);

        // Gerbang PIC untuk SELURUH gelombang sekaligus, sebelum satu pun
        // diproses. Memeriksanya per baris di dalam gelung akan meloloskan
        // sebagian lalu menolak sisanya — dan gelombang yang separuh diputus
        // adalah keadaan yang paling sulit dibereskan.
        if ($galat = $this->galatPicTahap(
            collect($data['item'])->map(fn ($it) => Hashids::decode($it['tahapId'])[0] ?? null)->filter()->all()
        )) {
            return $galat;
        }

        $menahan = (bool) $data['hold'];
        $berhasil = [];
        $gagal = [];

        foreach ($data['item'] as $it) {
            $realId = Hashids::decode($it['tahapId'])[0] ?? null;
            if (! $realId) {
                $gagal[] = ['tahapId' => $it['tahapId'], 'nama' => null, 'pesan' => 'Tahap tidak valid.'];

                continue;
            }

            $r = $this->terapkanHold(
                (int) $realId,
                $menahan,
                $it['alasanKode'] ?? null,
                $it['catatanHtml'] ?? null,
                $it['catatan'] ?? null,
            );

            // Nama kandidat ikut dibawa supaya laporan kegagalan menyebut ORANG,
            // bukan nomor tahap yang tidak dikenali siapa pun di layar.
            $nama = DB::table('N_WEB_CAREERS_Lamaran_Tahap as t')
                ->join('N_WEB_CAREERS_Lamaran as l', 'l.Id_Lamaran', '=', 't.Lamaran_Id')
                ->join('N_WEB_CAREERS_Users as u', 'u.Id_Users', '=', 'l.Id_Users')
                ->where('t.Id_Lamaran_Tahap', $realId)
                ->value('u.Nama');

            if ($r['ok']) {
                $berhasil[] = ['tahapId' => $it['tahapId'], 'nama' => $nama, 'outcome' => $r['outcome'] ?? null];
            } else {
                $gagal[] = ['tahapId' => $it['tahapId'], 'nama' => $nama, 'pesan' => $r['pesan']];
            }
        }

        $kata = $menahan ? 'ditahan' : 'dilanjutkan';
        Log::channel('web_career')->info(sprintf(
            '[HOLD MASSAL] %s: %d berhasil, %d gagal, oleh %s.',
            $menahan ? 'TAHAN' : 'LEPAS', count($berhasil), count($gagal), session('career_auth.nama', 'ADMIN')
        ));

        // Seluruhnya gagal dibalas 422: dari sisi admin tidak ada yang terjadi,
        // dan membalasnya 200 membuat layar menampilkan keberhasilan palsu.
        //
        // Dibentuk langsung, bukan lewat ResponseHelper::error() — helper itu
        // hanya membawa pesan, sedangkan layar perlu tahu SIAPA saja yang
        // dilewati dan kenapa. Tanpa daftarnya, admin cuma dapat satu kalimat
        // untuk dua puluh kandidat.
        if (! $berhasil) {
            return response()->json([
                'success' => false,
                'status' => 422,
                'message' => 'Tidak ada kandidat yang berhasil '.$kata.'. '.($gagal[0]['pesan'] ?? ''),
                'result' => ['berhasil' => [], 'gagal' => $gagal],
            ], 422);
        }

        $pesan = count($gagal) === 0
            ? count($berhasil).' kandidat '.$kata.'.'
            : count($berhasil).' kandidat '.$kata.', '.count($gagal).' dilewati.';

        return ResponseHelper::success(['berhasil' => $berhasil, 'gagal' => $gagal], $pesan);
    }

    /**
     * Terapkan penahanan / pelepasan pada SATU tahap.
     *
     * Inti aturannya dipusatkan di sini karena dipakai DUA pintu: penahanan
     * satuan dari drawer kandidat, dan penahanan massal dari papan. Dua salinan
     * aturan pasti berselisih — yang satu diperbaiki, yang lain tertinggal,
     * dan selisihnya baru ketahuan saat ada yang membandingkan hasil keduanya.
     *
     * @return array{ok: bool, pesan: string, status: int, outcome?: string|null}
     */
    private function terapkanHold(int $realId, bool $menahan, ?string $alasanKode, ?string $catatanHtml, ?string $catatan): array
    {
        $gagal = fn (string $pesan, int $status) => ['ok' => false, 'pesan' => $pesan, 'status' => $status];

        $tahap = DB::table('N_WEB_CAREERS_Lamaran_Tahap')->where('Id_Lamaran_Tahap', $realId)->first();
        if (! $tahap) {
            return $gagal('Tahap tidak ditemukan.', 404);
        }
        if ($tahap->Status === 'SELESAI') {
            return $gagal('Tahap ini sudah diputus — tidak bisa ditahan lagi.', 409);
        }

        [$html, $ringkas] = self::catatanKaya($catatanHtml, $catatan);
        $now = now();
        $nama = session('career_auth.nama', 'ADMIN');
        $adminId = session('career_auth.id');

        if ($menahan) {
            if (($tahap->Hold_Flag ?? 'T') === 'Y') {
                return $gagal('Kandidat ini sudah ditahan.', 409);
            }

            $alasan = self::masterAlasanHold()->get((string) ($alasanKode ?? ''));
            if (! $alasan) {
                return $gagal('Pilih alasan penahanan.', 422);
            }
            // Alasan ber-Butuh_Catatan wajib dijelaskan. "Lainnya" tanpa
            // keterangan tidak menjelaskan apa pun saat ditinjau berbulan-bulan
            // kemudian — sama saja tidak memilih alasan.
            if (($alasan->Butuh_Catatan ?? 'T') === 'Y' && trim((string) $ringkas) === '') {
                return $gagal("Alasan \"{$alasan->Nama}\" menuntut keterangan tambahan.", 422);
            }
        } elseif (($tahap->Hold_Flag ?? 'T') !== 'Y') {
            return $gagal('Kandidat ini tidak sedang ditahan.', 409);
        }

        $data = ['alasanKode' => $alasanKode];

        DB::transaction(function () use ($realId, $tahap, $menahan, $data, $html, $ringkas, $now, $nama, $adminId) {
            DB::table('N_WEB_CAREERS_Lamaran_Tahap')->where('Id_Lamaran_Tahap', $realId)->update($menahan ? [
                'Hold_Flag' => 'Y',
                'Hold_Alasan_Kode' => $data['alasanKode'],
                'Hold_Alasan' => $ringkas,
                'Hold_Alasan_Html' => $html,
                'Hold_At' => $now,
                'Hold_By' => $nama,
                'Hold_By_Id' => $adminId,
                // Jejak pelepasan sebelumnya dibersihkan supaya tidak terbaca
                // sebagai "sudah dilepas" pada penahanan yang baru ini.
                'Hold_Lepas_At' => null,
                'Hold_Lepas_By' => null,
                'Hold_Lepas_By_Id' => null,
                'Updated_At' => $now, 'Updated_By' => $nama, 'Updated_By_Id' => $adminId,
            ] : [
                'Hold_Flag' => 'T',
                'Hold_Lepas_At' => $now,
                'Hold_Lepas_By' => $nama,
                'Hold_Lepas_By_Id' => $adminId,
                // Alasan penahanannya TIDAK dihapus: itu bagian dari riwayat
                // tahap ini, dan menghapusnya membuat "kenapa dulu tertahan"
                // hilang begitu saja.
                'Updated_At' => $now, 'Updated_By' => $nama, 'Updated_By_Id' => $adminId,
            ]);

            // Riwayat terpisah: satu kandidat bisa ditahan-dilepas berkali-kali,
            // dan kolom di atas hanya menyimpan yang terakhir.
            DB::table('N_WEB_CAREERS_Lamaran_Tahap_Hold')->insert([
                'Lamaran_Tahap_Id' => $realId,
                'Lamaran_Id' => $tahap->Lamaran_Id,
                'Aksi' => $menahan ? 'TAHAN' : 'LEPAS',
                'Alasan_Kode' => $menahan ? $data['alasanKode'] : null,
                'Alasan' => $ringkas,
                'Alasan_Html' => $html,
                'Created_At' => $now,
                'Created_By' => $nama,
                'Created_By_Id' => $adminId,
            ]);

            // ── LAMA TERTAHAN DIHITUNG SAAT DILEPAS ───────────────────────
            //
            // Barisan TAHAN yang menganga ditutup di sini: Lepas_At diisi, dan
            // lamanya dihitung dalam HARI KERJA memakai kalender yang sama
            // dengan SLA (Senin–Sabtu, dikurangi libur HCIS). Menghitungnya
            // dalam hari kalender akan menyebut hold yang jatuh di akhir pekan
            // panjang sebagai 3 hari padahal kantor cuma tutup 1 hari kerja.
            //
            // Dihitung SEKARANG, bukan saat laporan dibuka: kalender bisa
            // berubah (cuti bersama baru ditetapkan), dan angka yang sudah
            // disepakati tidak boleh bergeser diam-diam setelahnya.
            //
            // Tenggat MPP-nya sendiri TIDAK digeser — satu MPP dipakai bersama
            // banyak kandidat, dan menghentikan jamnya karena satu orang
            // ditahan akan membekukan yang lain. Angka ini pendamping, bukan
            // pengurang.
            if (! $menahan && self::siapHitungHold()) {
                $mulai = DB::table('N_WEB_CAREERS_Lamaran_Tahap_Hold')
                    ->where('Lamaran_Tahap_Id', $realId)
                    ->where('Aksi', 'TAHAN')
                    ->whereNull('Lepas_At')
                    ->orderByDesc('Id_Lamaran_Tahap_Hold')
                    ->first(['Id_Lamaran_Tahap_Hold', 'Created_At']);

                if ($mulai) {
                    DB::table('N_WEB_CAREERS_Lamaran_Tahap_Hold')
                        ->where('Id_Lamaran_Tahap_Hold', $mulai->Id_Lamaran_Tahap_Hold)
                        ->update([
                            'Lepas_At' => $now,
                            'Hari_Kerja_Tertahan' => \App\Support\Career\SlaMpp::selisihHariKerja(
                                (string) $mulai->Created_At,
                                (string) $now,
                            ),
                        ]);
                }
            }

            // BATAS PENGISIAN IKUT BERHENTI selama ditahan: kandidat tidak salah
            // apa-apa saat tim sengaja menunda tahapnya. Digeser sepanjang lama
            // hold-nya (berbeda dari tenggat MPP, yang dipakai bersama).
            if (! $menahan) {
                BatasIsi::geserKarenaHold($tahap, $now, $nama, $adminId);
            }
        });

        Log::channel('web_career')->info(
            'Tahap #'.$realId.' '.($menahan ? 'DITAHAN' : 'DILEPAS dari tahan')." oleh {$nama}."
        );

        // MENYUSUL KETINGGALAN. Selama ditahan, mesin keputusan sengaja tidak
        // menyimpulkan apa pun (lihat evaluasiTahap). Hasil tes yang masuk di
        // tengah penahanan karena itu belum pernah dinilai — dan tanpa evaluasi
        // ulang di sini, tahap yang seharusnya sudah otomatis maju akan diam
        // selamanya menunggu peristiwa yang tidak akan datang lagi.
        if (! $menahan) {
            $eval = $this->svc->evaluasiTahap((int) $realId, (int) $adminId);
            $outcome = $eval['outcome'] ?? null;

            // Kesimpulan otomatis yang menyusul TETAP dikabarkan — kandidat
            // tidak boleh dirugikan hanya karena keputusannya sempat tertunda
            // oleh urusan internal.
            if (in_array($outcome, ['LANJUT', 'GUGUR'], true)) {
                $this->kirimEmailHasilTahap((int) $tahap->Lamaran_Id, $outcome === 'LANJUT');
            }

            return [
                'ok' => true,
                'status' => 200,
                'outcome' => $outcome,
                'pesan' => 'Penahanan dilepas — proses bisa dilanjutkan.',
            ];
        }

        return [
            'ok' => true,
            'status' => 200,
            'pesan' => 'Kandidat ditahan. Tidak ada pemberitahuan yang dikirim.',
        ];
    }

    /**
     * Pesan "berkas kelewat besar" yang benar-benar menolong.
     *
     * Menyebut DUA angka: batasnya, dan ukuran berkas yang barusan dipilih.
     * Tanpa yang kedua, orang yang berkasnya 2,1 MB dan yang berkasnya 40 MB
     * membaca kalimat yang sama persis — padahal yang satu cukup dikompres
     * sedikit, yang lain harus mengganti berkasnya sama sekali.
     */
    private static function pesanUkuran(Request $request, int $maksKb): string
    {
        $mb = fn (float $kb) => rtrim(rtrim(number_format($kb / 1024, 1, ',', '.'), '0'), ',');
        $file = $request->file('file');
        $batas = 'Ukuran berkas melebihi batas '.$mb($maksKb).' MB.';

        if (! $file || ! $file->isValid()) {
            // Berkas yang GAGAL diunggah (mis. melebihi upload_max_filesize PHP)
            // tidak punya ukuran yang bisa dibaca — menyebut "0 MB" di situ
            // justru menyesatkan.
            return $batas.' Perkecil dulu berkasnya, lalu unggah ulang.';
        }

        return $batas.' Berkas yang dipilih berukuran '.$mb($file->getSize() / 1024).' MB — perkecil dulu, lalu unggah ulang.';
    }

    /**
     * SLA beberapa MPP sekaligus, berkunci nomor MPP.
     *
     * SATU kueri untuk seluruh loker di papan. Menanyakannya per loker berarti
     * belasan kueri untuk data yang sama, dan papan pelamar sudah cukup berat.
     *
     * Sisa harinya dihitung SlaMpp::keadaan() — sumber yang sama dengan kartu
     * MPP dan dashboard, supaya tiga layar tidak pernah menyebut angka berbeda
     * untuk MPP yang sama.
     *
     * @param  list<string>  $noMpp
     * @return array<string, array>
     */
    private static function slaPerMpp(array $noMpp): array
    {
        if (! $noMpp || ! \App\Support\Career\SlaMpp::siapSnapshot()) {
            return [];
        }

        $rows = DB::table('N_WEB_CAREERS_Detail_MPP')
            ->whereIn('No_Transaksi_MPP', $noMpp)
            ->whereNotNull('Sla_Batas')
            ->get([
                'No_Transaksi_MPP',
                DB::raw('CONVERT(varchar(10), Sla_Batas, 23) as batas'),
                DB::raw('CONVERT(varchar(10), Sla_Batas_Awal, 23) as batasAwal'),
                'Sla_Hari_Kerja',
                'Sla_Perpanjangan_Ke',
            ]);

        $hasil = [];

        foreach ($rows as $r) {
            $keadaan = \App\Support\Career\SlaMpp::keadaan($r->batas);

            if (! $keadaan) {
                continue;
            }

            $hasil[trim((string) $r->No_Transaksi_MPP)] = [
                'batas' => $r->batas,
                'batasAwal' => $r->batasAwal ?: $r->batas,
                'hari' => (int) ($r->Sla_Hari_Kerja ?? 0),
                'perpanjanganKe' => (int) ($r->Sla_Perpanjangan_Ke ?? 0),
                'sisa' => $keadaan['sisa'],
                'lewat' => $keadaan['lewat'],
                'nada' => $keadaan['nada'],
                'label' => $keadaan['label'],
            ];
        }

        return $hasil;
    }

    /**
     * Kolom pencatat lama hold sudah ada di basis data ini?
     *
     * Skema dijalankan admin lewat SSMS (docs/02-09-2026), bukan oleh kode.
     * Selama .sql-nya belum dijalankan, penahanan tetap berfungsi seperti
     * sebelumnya — yang tidak ada hanya angka lamanya. Tanpa penjagaan ini,
     * setiap pelepasan hold akan gagal dengan "invalid column name" dan
     * merusak fitur yang tadinya jalan.
     */
    private static function siapHitungHold(): bool
    {
        static $siap = null;

        return $siap ??= \App\Support\Career\Skema::adaKolom(
            'N_WEB_CAREERS_Lamaran_Tahap_Hold',
            'Hari_Kerja_Tertahan',
        );
    }

    /** Master alasan HOLD yang aktif, di-cache per permintaan. */
    private static ?\Illuminate\Support\Collection $alasanHoldCache = null;

    private static function masterAlasanHold(): \Illuminate\Support\Collection
    {
        return self::$alasanHoldCache ??= DB::table('N_WEB_CAREERS_Master_Alasan_Hold')
            ->where('Flag_Aktif', 'Y')
            ->orderBy('Urutan')
            ->get()
            ->keyBy('Kode');
    }

    /** Upload berkas hasil tahap (MCU/Interview) — PDF/JPG, oleh admin/requester. */
    public function unggahBerkasTahap(Request $request, string $id)
    {
        $realId = Hashids::decode($id)[0] ?? null;
        if (! $realId) {
            return ResponseHelper::error('Tahap tidak valid.', 422);
        }
        $tahap = DB::table('N_WEB_CAREERS_Lamaran_Tahap')->where('Id_Lamaran_Tahap', $realId)->first();
        if (! $tahap) {
            return ResponseHelper::error('Tahap tidak ditemukan.', 404);
        }

        $request->validate(['file' => 'required|file|mimes:pdf,jpg,jpeg|max:2048']);

        $lam = DB::table('N_WEB_CAREERS_Lamaran as l')
            ->leftJoin('N_WEB_CAREERS_Users as u', 'u.Id_Users', '=', 'l.Id_Users')
            ->where('l.Id_Lamaran', $tahap->Lamaran_Id)->select('l.Id_Lamaran', 'u.Nama')->first();

        $gcs = app(GcsBerkas::class);
        $now = now();
        $file = $request->file('file');
        $ext = $file->getClientOriginalExtension();
        $konten = $file->get();

        try {
            $gcs->validasi($file->getClientOriginalName(), $ext, strlen($konten));
            // Akar `hasil-tahap/` + ruang per tipe tahap — lihat penjelasan
            // konsepnya di GcsBerkas::folderHasilTahap().
            $folder = $gcs->folderHasilTahap(
                $now->format('Y'),
                $now->format('m'),
                $now->format('d'),
                $lam->Nama ?? 'kandidat',
                $tahap->Tipe_Tahap_Kode,
            );
            $label = 'hasil-'.strtolower($tahap->Tipe_Tahap_Kode ?? 'tahap').'-'.\Illuminate\Support\Str::lower(\Illuminate\Support\Str::random(6));
            $path = $gcs->unggah($folder, $label, $ext, $konten);
        } catch (\Throwable $e) {
            return ResponseHelper::error($e->getMessage(), 422);
        }

        DB::table('N_WEB_CAREERS_Lamaran_Tahap_Berkas')->insert([
            'Lamaran_Tahap_Id' => $realId,
            'Lamaran_Id' => $tahap->Lamaran_Id,
            'Jenis' => $tahap->Tipe_Tahap_Kode,
            'Nama_File' => $file->getClientOriginalName(),
            'Path_File' => $path,
            'Mime' => $file->getClientMimeType(),
            'Ukuran' => strlen($konten),
            'Ext' => $gcs->normalkanExt($ext),
            'Created_At' => $now, 'Created_By' => session('career_auth.nama', 'ADMIN'), 'Created_By_Id' => session('career_auth.id'),
            'Updated_At' => $now,
        ]);

        Log::channel('web_career')->info("Berkas hasil tahap #{$realId} diunggah ({$file->getClientOriginalName()}).");

        return ResponseHelper::success(null, 'Berkas terunggah.');
    }

    /** Daftar berkas hasil sebuah tahap. */
    public function berkasTahap(string $id)
    {
        $realId = Hashids::decode($id)[0] ?? null;
        $rows = DB::table('N_WEB_CAREERS_Lamaran_Tahap_Berkas')->where('Lamaran_Tahap_Id', $realId)->whereNull('Ulang_Id')->orderByDesc('Id_Lamaran_Tahap_Berkas')->get();

        return ResponseHelper::success($rows->map(fn ($b) => [
            'id' => Hashids::encode($b->Id_Lamaran_Tahap_Berkas),
            'nama' => $b->Nama_File,
            'ext' => $b->Ext,
            'ukuran' => (int) $b->Ukuran,
            'url' => route('career.api.lamaran.tahap.berkas.file', Hashids::encode($b->Id_Lamaran_Tahap_Berkas)),
            'createdAt' => $b->Created_At,
        ])->values(), 'Berkas tahap');
    }

    /** Serve berkas hasil tahap (signed URL GCS 15 menit). */
    public function berkasTahapFile(string $id)
    {
        $realId = Hashids::decode($id)[0] ?? null;
        $b = DB::table('N_WEB_CAREERS_Lamaran_Tahap_Berkas')->where('Id_Lamaran_Tahap_Berkas', $realId)->first();
        if (! $b) {
            abort(404);
        }
        try {
            $gcs = Storage::disk(GcsBerkas::DISK);
            if ($gcs->exists($b->Path_File)) {
                return redirect()->away($gcs->temporaryUrl($b->Path_File, now()->addMinutes(15)));
            }
        } catch (\Throwable $e) {
            Log::channel('web_career')->warning('Signed URL berkas tahap gagal: '.$e->getMessage());
        }
        abort(404, 'File tidak ditemukan.');
    }

    /** Hapus berkas hasil tahap. */
    public function hapusBerkasTahap(string $id)
    {
        $realId = Hashids::decode($id)[0] ?? null;
        $b = DB::table('N_WEB_CAREERS_Lamaran_Tahap_Berkas')->where('Id_Lamaran_Tahap_Berkas', $realId)->first();
        if (! $b) {
            return ResponseHelper::error('Tidak ditemukan', 404);
        }
        // URUTANNYA: BASIS DATA DULU, GCS BELAKANGAN.
        //
        // Dulu terbalik. Bila objeknya sudah dihapus dari GCS lalu penghapusan
        // barisnya gagal, yang tersisa adalah lampiran yang tetap terdaftar di
        // layar tetapi tidak bisa dibuka siapa pun — dan tak ada cara
        // menghapusnya lagi selain lewat basis data langsung. Terbalik seperti
        // sekarang, kegagalan paling buruk hanya menyisakan objek yatim di GCS:
        // tidak terlihat, tidak mengganggu, dan bisa disapu belakangan.
        DB::table('N_WEB_CAREERS_Lamaran_Tahap_Berkas')->where('Id_Lamaran_Tahap_Berkas', $realId)->delete();

        try {
            app(GcsBerkas::class)->hapus([$b->Path_File]);
        } catch (\Throwable $e) {
            // Dicatat, tidak ditelan diam-diam: objek yatim yang tak pernah
            // dilaporkan adalah tagihan penyimpanan yang tak pernah dijelaskan.
            Log::channel('web_career')->warning('[BERKAS-TAHAP] sisa GCS gagal dihapus: '.$e->getMessage());
        }

        return ResponseHelper::success(null, 'Berkas dihapus.');
    }

    // ═══ EXPORT STUDIO — BERKAS SELEKSI KANDIDAT ═══

    /**
     * Aturan muatan Export Studio — dipakai pratinjau maupun cetak.
     *
     * URUTAN `kunci` ADALAH BAGIAN DARI MUATAN, bukan sekadar daftar: layar
     * mengirimkannya sesuai susunan yang tampak di panel kiri (yang bisa
     * digeser admin), dan perakit mencetak mengikutinya. Karena itu ia array
     * berindeks, bukan himpunan.
     *
     * `atur` berisi penyesuaian per kunci — judul yang ditulis ulang, bentuk
     * tampilan bagian berulang, dan jarak atas. Semuanya opsional; kunci tanpa
     * penyesuaian tidak muncul di sana sama sekali.
     *
     * `bab` adalah urutan bab yang digeser admin. Opsional: permintaan lama
     * (dan pemanggil mana pun yang tidak mengirimnya) jatuh ke urutan bawaan.
     */
    private const ATURAN_BERKAS = [
        'kunci' => 'required|array|min:1|max:400',
        'kunci.*' => 'string|max:120',
        'atur' => 'nullable|array|max:400',
        'atur.*.gaya' => 'nullable|string|in:timeline,tabel,kartu',
        // Bentuk isian pendek di halaman formulir — lihat formulir.blade.php.
        'atur.*.bentuk' => 'nullable|string|in:kisi,kartu,baris',
        // Penempatan bagian CV pada halaman dua kolom. Bawaannya sudah
        // direkomendasikan per peran (SusunCv::BAGIAN); ini untuk admin
        // yang ingin memindahkannya — mis. menaikkan Kemampuan ke kolom
        // utama pada posisi yang menuntut keahlian teknis.
        'atur.*.lajur' => 'nullable|string|in:kiri,utama,penuh',
        'atur.*.judul' => 'nullable|string|max:120',
        // Dibatasi 60px: lebih dari itu satu bagian bisa mendorong sisanya
        // keluar dari kanvas halaman yang tingginya tetap.
        'atur.*.jarakAtas' => 'nullable|integer|min:0|max:60',
        // ── TATA LETAK PER HALAMAN ───────────────────────────────────────
        //
        // `atur.hal.<kunci halaman>` — kerapatan, sela, ukuran teks, geser.
        // Batasnya dijaga DI SINI juga, bukan hanya di TataLetakHalaman:
        // yang menjepit di sana melindungi tata letak, yang menolak di sini
        // melindungi endpoint dari muatan yang mengada-ada (sela 99999
        // membuat dompdf merender kanvas raksasa sebelum sempat dijepit).
        // Tema warna halaman penutup. Hanya dua yang sah: navy (bawaan) &
        // emas. Seluruh warna lain di halaman itu diturunkan dari pilihan
        // ini, jadi tidak ada kombinasi yang bisa menghasilkan teks yang
        // tak terbaca di atas latarnya sendiri.
        'atur.penutup.tema' => 'nullable|string|in:navy,emas',
        'atur.hal' => 'nullable|array|max:120',
        'atur.hal.*.kerapatan' => 'nullable|string|in:rapat,seimbang,penuh',
        'atur.hal.*.sela' => 'nullable|integer|min:10|max:72',
        'atur.hal.*.teks' => 'nullable|integer|min:9|max:13',
        // Boleh NEGATIF: menaikkan isi merapatkannya ke kop supaya ruang
        // kosongnya berkumpul di kaki, bukan terbelah atas-bawah. Batas -30
        // mengikuti TataLetakHalaman::NAIK_MAKS -- lebih dari itu judul
        // halaman menabrak garis bawah kop.
        'atur.hal.*.geser' => 'nullable|integer|min:-30|max:400',
        // Paksa halaman ini berdiri sendiri walau isinya muat disambung ke
        // halaman sebelumnya. "Muat" tidak sama dengan "pantas": satu
        // formulir yang secara resmi harus mulai di lembar baru tetap berhak
        // begitu, dan itu penilaian yang tidak bisa dihitung sistem.
        'atur.hal.*.sendiri' => 'nullable|boolean',
        'bab' => 'nullable|array|max:12',
        'bab.*' => 'string|max:40',
    ];

    /**
     * GET /api/v1/karir/lamaran/{id}/berkas-seleksi/opsi — isi apa saja yang
     * bisa dicetak untuk kandidat ini.
     *
     * SENGAJA RINGAN: tidak memanggil CAT dan tidak mengunduh satu pun berkas.
     * Layar hanya perlu tahu ada seksi apa saja; membuka modal tidak boleh
     * menunggu belasan panggilan jaringan. Isinya baru diambil saat merender.
     */
    public function berkasSeleksiOpsi(string $id)
    {
        $realId = Hashids::decode($id)[0] ?? null;

        if (! $realId) {
            return ResponseHelper::error('Lamaran tidak valid.', 422);
        }

        $daftar = BerkasSeleksi::daftar((int) $realId);

        if (! $daftar) {
            return ResponseHelper::error('Lamaran tidak ditemukan.', 404);
        }

        return ResponseHelper::success($daftar, 'Opsi berkas seleksi');
    }

    /**
     * POST /api/v1/karir/lamaran/{id}/berkas-seleksi/halaman — daftar halaman
     * yang tata letaknya bisa disetel, beserta hitungan sistem untuk tiap
     * halaman (sisa ruang, sela, padat/tidak).
     *
     * TERPISAH DARI PRATINJAU karena pratinjau memulangkan PDF mentah —
     * tidak ada tempat menyelipkan JSON di badannya. Layar memanggil keduanya
     * berbarengan, jadi tidak ada tambahan waktu tunggu yang terasa.
     *
     * Yang membuatnya murah: petaHalaman() melewati hasil seleksi & lampiran,
     * dua bagian yang memanggil CAT dan mengunduh berkas — dan satu pun
     * halamannya tidak bisa disetel.
     */
    public function berkasSeleksiHalaman(Request $request, string $id)
    {
        $realId = Hashids::decode($id)[0] ?? null;

        if (! $realId) {
            return ResponseHelper::error('Lamaran tidak valid.', 422);
        }

        $data = $request->validate(self::ATURAN_BERKAS);

        try {
            $peta = RakitBerkasSeleksi::petaHalaman(
                (int) $realId,
                $data['kunci'],
                $data['atur'] ?? [],
                $data['bab'] ?? [],
            );
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('[BERKAS-SELEKSI] peta halaman gagal: '.$e->getMessage());

            // Bukan galat yang perlu menggagalkan layar: tanpa peta, panel
            // kanan hanya kehilangan pengaturan tata letak — pratinjau dan
            // unduhan tetap berjalan. Daftar kosong lebih baik daripada modal
            // yang menolak terbuka.
            return ResponseHelper::success(['halaman' => []], 'Peta halaman kosong');
        }

        return ResponseHelper::success(['halaman' => $peta], 'Peta halaman');
    }

    /**
     * POST /api/v1/karir/lamaran/{id}/berkas-seleksi/pratinjau — antrekan
     * pratinjau, kembalikan id untuk dipantau.
     *
     * ── KENAPA LEWAT ANTREAN, BUKAN DIRENDER DI SINI ──────────────────────
     *
     * Dulu PDF-nya dirakit langsung di dalam permintaan ini. Di produksi itu
     * berakhir 503: perakitannya makan ~56 detik — 44 detik di antaranya untuk
     * mengambil dan merender laporan psikotes dari CAT — sementara Cloud Run
     * memutus permintaan pada 60 detik. Yang dilihat admin bukan pratinjau,
     * melainkan "Pratinjau gagal dibuat".
     *
     * Menaikkan batas Cloud Run hanya memindahkan masalahnya: admin tetap
     * menatap layar diam hampir semenit, dan tiap pratinjau menahan satu
     * instance selama itu — sepuluh admin mencetak bersamaan berarti sepuluh
     * instance tertahan.
     *
     * Jalur antrean SUDAH ADA untuk unduhan, lengkap dengan pemantauan status
     * dan penyajian berkasnya. Pratinjau memakai jalur yang sama persis,
     * hanya dengan `pratinjau: true` — jadi tidak ada mesin kedua yang harus
     * dijaga tetap sepakat dengan yang pertama.
     *
     * Yang membedakan isinya dari unduhan tetap satu hal saja: lampiran
     * formulir diganti halaman penanda. Berkas penilaian FGD/wawancara,
     * laporan psikotes, dan seluruh tata letaknya identik — itulah yang
     * membuat pratinjau layak dipercaya sebagai gambaran hasil akhirnya.
     */
    public function berkasSeleksiPratinjau(Request $request, string $id)
    {
        $realId = Hashids::decode($id)[0] ?? null;

        if (! $realId) {
            return ResponseHelper::error('Lamaran tidak valid.', 422);
        }

        $data = $request->validate(self::ATURAN_BERKAS);

        $lamaran = DB::table('N_WEB_CAREERS_Lamaran as l')
            ->leftJoin('N_WEB_CAREERS_Users as u', 'u.Id_Users', '=', 'l.Id_Users')
            ->where('l.Id_Lamaran', $realId)
            ->select('l.Kode', 'u.Nama')
            ->first();

        if (! $lamaran) {
            return ResponseHelper::error('Lamaran tidak ditemukan.', 404);
        }

        $exportId = DB::table('N_WEB_CAREERS_Export_Log')->insertGetId([
            // Tipe TERPISAH dari BERKAS_SELEKSI. Layar riwayat unduhan
            // menyaring berdasarkan kolom ini, dan pratinjau tidak boleh
            // muncul di sana: isinya dokumen setengah jadi yang terlihat
            // resmi, dan yang tercantum di riwayat cepat atau lambat dikirim
            // ke luar oleh seseorang.
            'Export_Type' => 'BERKAS_SELEKSI_PRATINJAU',
            'Id_Users' => session('career_auth.id'),
            'Keterangan' => 'Pratinjau — '.($lamaran->Nama ?: 'Kandidat'),
            'Filters_Json' => json_encode([
                'lamaranId' => (int) $realId,
                'kodeLamaran' => $lamaran->Kode,
                'kunci' => $data['kunci'],
                'atur' => $data['atur'] ?? [],
                'bab' => $data['bab'] ?? [],
                'pratinjau' => true,
            ]),
            'Status_Export' => 'DIPROSES',
            'Progress_Chunk' => 0,
            'Progress_Total' => 1,
            'Flag_Cancellation' => 'T',
            'Created_At' => now(),
        ], 'Id_Export');

        WcBerkasSeleksiJob::dispatch(
            (int) $exportId,
            (int) $realId,
            $data['kunci'],
            (int) session('career_auth.id') ?: null,
            $data['atur'] ?? [],
            $data['bab'] ?? [],
            pratinjau: true,
        );

        return ResponseHelper::success(
            ['id' => Hashids::encode($exportId)],
            'Pratinjau sedang disiapkan.',
        );
    }

    /**
     * POST /api/v1/karir/lamaran/{id}/berkas-seleksi — minta cetak berkas.
     *
     * SELALU LEWAT ANTREAN. Berkas seleksi bisa memuat belasan halaman
     * rancangan, beberapa laporan psikotes yang ditarik dari CAT lewat
     * jaringan, dan puluhan lampiran yang harus diunduh lalu digabungkan.
     * Dikerjakan di dalam permintaan ini, admin menatap layar membeku lalu
     * menekan tombolnya lagi — dan lahir dua berkas untuk satu permintaan.
     */
    public function berkasSeleksiBuat(Request $request, string $id)
    {
        $realId = Hashids::decode($id)[0] ?? null;

        if (! $realId) {
            return ResponseHelper::error('Lamaran tidak valid.', 422);
        }

        $data = $request->validate(self::ATURAN_BERKAS);

        $lamaran = DB::table('N_WEB_CAREERS_Lamaran as l')
            ->leftJoin('N_WEB_CAREERS_Users as u', 'u.Id_Users', '=', 'l.Id_Users')
            ->leftJoin('N_WEB_CAREERS_Program as p', 'p.Id_Program', '=', 'l.Program_Id')
            ->where('l.Id_Lamaran', $realId)
            ->select('l.Kode', 'u.Nama', 'p.Nama as ProgramNama')
            ->first();

        if (! $lamaran) {
            return ResponseHelper::error('Lamaran tidak ditemukan.', 404);
        }

        $exportId = DB::table('N_WEB_CAREERS_Export_Log')->insertGetId([
            'Export_Type' => 'BERKAS_SELEKSI',
            'Id_Users' => session('career_auth.id'),
            'Keterangan' => trim(($lamaran->Nama ?: 'Kandidat').' — '.($lamaran->ProgramNama ?: '')),
            'Filters_Json' => json_encode([
                'lamaranId' => (int) $realId,
                'kodeLamaran' => $lamaran->Kode,
                'kunci' => $data['kunci'],
                'atur' => $data['atur'] ?? [],
                'bab' => $data['bab'] ?? [],
            ]),
            'Status_Export' => 'DIPROSES',
            'Progress_Chunk' => 0,
            'Progress_Total' => 1,
            'Flag_Cancellation' => 'T',
            'Created_At' => now(),
        ], 'Id_Export');

        WcBerkasSeleksiJob::dispatch(
            (int) $exportId,
            (int) $realId,
            $data['kunci'],
            (int) session('career_auth.id') ?: null,
            $data['atur'] ?? [],
            $data['bab'] ?? [],
        );

        return ResponseHelper::success(
            ['id' => Hashids::encode($exportId)],
            'Berkas seleksi sedang disiapkan. Tautan unduhnya muncul begitu selesai.',
        );
    }

    /**
     * GET /api/v1/karir/lamaran/{id}/laporan/opsi — formulir apa saja yang bisa
     * dicetak untuk kandidat ini.
     *
     * Satu lamaran bisa punya beberapa pengisian: MT mengumpulkan data DUA KALI
     * (pendaftaran, lalu kelengkapan data diri di tahap berikutnya). Karena itu
     * admin diberi PILIHAN, bukan ditebakkan: yang terbaru ditandai `utama` dan
     * tercentang lebih dulu, tapi laporan gabungan tetap mungkin bila memang
     * perlu membandingkan jawaban lama dan baru.
     */
    public function laporanOpsi(string $id)
    {
        $realId = Hashids::decode($id)[0] ?? null;
        if (! $realId) {
            return ResponseHelper::error('Lamaran tidak valid.', 422);
        }

        $formulir = \App\Support\Career\LaporanKandidat::daftarFormulir((int) $realId);

        return ResponseHelper::success([
            'formulir' => $formulir,
            // Layar memakai ini untuk memutuskan perlu-tidaknya menampilkan
            // pilihan sama sekali: satu formulir = tak ada yang perlu dipilih.
            'perluPilih' => count($formulir) > 1,
        ], 'Opsi laporan');
    }

    /**
     * POST /api/v1/karir/lamaran/{id}/laporan — minta cetak laporan kandidat.
     *
     * Selalu lewat ANTREAN. Merender PDF berisi foto tertanam + seluruh
     * perjalanan tahap memakan beberapa detik; dikerjakan di dalam permintaan
     * ini, admin menatap layar membeku lalu menekan tombolnya lagi dan lahir
     * dua berkas untuk satu permintaan.
     */
    public function laporanBuat(Request $request, string $id)
    {
        $realId = Hashids::decode($id)[0] ?? null;
        if (! $realId) {
            return ResponseHelper::error('Lamaran tidak valid.', 422);
        }

        $data = $request->validate([
            'format' => 'required|in:PDF,XLSX',
            'formulir' => 'nullable|array|max:20',
            'formulir.*' => 'string|max:64',
        ]);

        $lamaran = DB::table('N_WEB_CAREERS_Lamaran as l')
            ->leftJoin('N_WEB_CAREERS_Users as u', 'u.Id_Users', '=', 'l.Id_Users')
            ->leftJoin('N_WEB_CAREERS_Program as p', 'p.Id_Program', '=', 'l.Program_Id')
            ->where('l.Id_Lamaran', $realId)
            ->select('l.Kode', 'u.Nama', 'p.Nama as ProgramNama')
            ->first();

        if (! $lamaran) {
            return ResponseHelper::error('Lamaran tidak ditemukan.', 404);
        }

        // Id formulir diterjemahkan DI SINI, bukan di dalam job: job berjalan
        // tanpa konteks permintaan, dan hashid yang tidak sah lebih baik
        // ditolak sekarang selagi ada yang menunggu jawabannya.
        $pengisianIds = collect($data['formulir'] ?? [])
            ->map(fn ($h) => Hashids::decode($h)[0] ?? null)
            ->filter()
            ->values()
            ->all();

        $exportId = DB::table('N_WEB_CAREERS_Export_Log')->insertGetId([
            'Export_Type' => 'LAPORAN_KANDIDAT',
            'Id_Users' => session('career_auth.id'),
            'Keterangan' => trim(($lamaran->Nama ?: 'Kandidat').' — '.($lamaran->ProgramNama ?: '')),
            'Filters_Json' => json_encode([
                'lamaranId' => (int) $realId,
                'kodeLamaran' => $lamaran->Kode,
                'format' => $data['format'],
                'formulir' => $pengisianIds,
            ]),
            'Status_Export' => 'DIPROSES',
            'Progress_Chunk' => 0,
            'Progress_Total' => 1,
            'Flag_Cancellation' => 'T',
            'Created_At' => now(),
        ], 'Id_Export');

        WcLaporanKandidatJob::dispatch((int) $exportId, (int) $realId, $pengisianIds, $data['format']);

        return ResponseHelper::success(
            ['id' => Hashids::encode($exportId)],
            'Laporan sedang disiapkan. Tautan unduhnya muncul begitu selesai.',
        );
    }

    /**
     * GET /api/v1/karir/lamaran/laporan/{id} — status satu permintaan cetak.
     *
     * Dipakai layar untuk menanyakan "sudah jadi belum" tanpa menahan admin
     * menunggu di depan tombol yang membeku.
     */
    /**
     * Baca kolom Keterangan Export_Log sebagai status sambungan HCLearn.
     *
     * Kolomnya dipakai bersama jenis ekspor lain yang menulis teks biasa di
     * sana, jadi apa pun yang bukan JSON berisi kunci 'hclearn' diabaikan —
     * bukan dianggap galat.
     */
    private static function bacaKeterangan(?string $ket): ?array
    {
        if (! $ket) {
            return null;
        }

        $data = json_decode($ket, true);

        return is_array($data) && isset($data['hclearn']) && is_array($data['hclearn'])
            ? $data['hclearn']
            : null;
    }

    public function laporanStatus(string $id)
    {
        $realId = Hashids::decode($id)[0] ?? null;
        $row = $realId ? DB::table('N_WEB_CAREERS_Export_Log')->where('Id_Export', $realId)->first() : null;

        if (! $row) {
            return ResponseHelper::error('Permintaan cetak tidak ditemukan.', 404);
        }

        return ResponseHelper::success([
            'id' => Hashids::encode($row->Id_Export),
            'status' => $row->Status_Export,
            'selesai' => $row->Status_Export === 'SELESAI',
            'gagal' => $row->Status_Export === 'GAGAL',
            'pesan' => $row->Error_Message,
            // ── STATUS SAMBUNGAN HCLEARN ──────────────────────────────────
            //
            // Render bisa "berhasil" sementara seluruh hasil psikotesnya gagal
            // diambil — dan berkasnya lalu terlihat sama persis dengan berkas
            // kandidat yang memang belum tes. Admin harus diberitahu, kalau
            // tidak ia memutus kelulusan dari dokumen yang tidak lengkap.
            'hclearn' => self::bacaKeterangan($row->Keterangan ?? null),
            // URL unduh lewat rute KITA, bukan URL bucket: berkasnya berisi data
            // pribadi kandidat, dan tautan bucket yang bocor bisa dibuka siapa pun.
            'url' => $row->Status_Export === 'SELESAI'
                ? route('career.api.lamaran.laporan.unduh', Hashids::encode($row->Id_Export))
                : null,
        ], 'Status laporan');
    }

    /**
     * GET /api/v1/karir/berkas-seleksi/pratinjau/{id} — sajikan PDF pratinjau
     * INLINE untuk ditampilkan di dalam bingkai.
     *
     * Terpisah dari laporanUnduh() karena dua hal yang tidak bisa didamaikan:
     *
     *   • laporanUnduh() memakai streamDownload(), yang memasang
     *     Content-Disposition: attachment — peramban menyimpannya alih-alih
     *     menampilkannya, dan bingkai pratinjau berakhir kosong;
     *   • pratinjau memuat halaman penanda alih-alih lampiran sungguhan, jadi
     *     ia dokumen setengah jadi yang terlihat resmi. Menyajikannya lewat
     *     rute unduhan berarti ia bisa disimpan orang dan beredar sebagai
     *     berkas seleksi yang sebenarnya.
     *
     * Hanya menerima Export_Type pratinjau. Berkas unduhan yang sah tetap
     * harus lewat laporanUnduh(), dengan seluruh perlakuannya sendiri.
     */
    public function berkasSeleksiLihat(string $id)
    {
        $realId = Hashids::decode($id)[0] ?? null;

        $row = $realId
            ? DB::table('N_WEB_CAREERS_Export_Log')
                ->where('Id_Export', $realId)
                ->where('Export_Type', 'BERKAS_SELEKSI_PRATINJAU')
                ->first()
            : null;

        if (! $row || $row->Status_Export !== 'SELESAI' || ! $row->File_Path) {
            abort(404);
        }

        try {
            $disk = Storage::disk(GcsBerkas::DISK);

            if (! $disk->exists($row->File_Path)) {
                abort(404, 'Pratinjau tidak ditemukan.');
            }

            $isi = $disk->get($row->File_Path);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::channel('web_career')->warning('[BERKAS-SELEKSI] pratinjau gagal disajikan: '.$e->getMessage());

            abort(404, 'Pratinjau tidak ditemukan.');
        }

        return response($isi, 200, [
            'Content-Type' => 'application/pdf',
            // INLINE, dan namanya sengaja menyebut dirinya pratinjau: berkas
            // ini memuat halaman penanda alih-alih lampiran sungguhan, jadi
            // yang tersimpan dari sini adalah dokumen setengah jadi yang
            // terlihat resmi. Unduhan yang sah hanya lewat antrean.
            'Content-Disposition' => 'inline; filename="pratinjau-berkas-seleksi.pdf"',
            // Jangan diindeks maupun disimpan perantara — isinya data pribadi.
            'X-Robots-Tag' => 'noindex, nofollow, noarchive',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /** GET unduh berkas laporan (URL bertanda tangan 15 menit dari GCS). */
    public function laporanUnduh(string $id)
    {
        $realId = Hashids::decode($id)[0] ?? null;
        $row = $realId ? DB::table('N_WEB_CAREERS_Export_Log')->where('Id_Export', $realId)->first() : null;

        if (! $row || $row->Status_Export !== 'SELESAI' || ! $row->File_Path) {
            abort(404);
        }

        // DIALIRKAN LEWAT ORIGIN KITA, BUKAN DIALIHKAN KE URL BERTANDA TANGAN.
        //
        // Pengalihan ke bucket memang lebih hemat, tetapi menutup satu-satunya
        // cara peramban mengetahui KEMAJUAN unduhan: permintaan lintas-origin ke
        // GCS ditolak sebelum satu byte pun terbaca, sehingga indikator progres
        // tak pernah bergerak dan berkasnya tak bisa disimpan otomatis.
        //
        // Berkas laporan satu kandidat berukuran ~1 MB dan dialirkan, bukan
        // dibaca utuh ke memori — biayanya sepadan dengan unduhan yang bisa
        // dipantau dan tersimpan sendiri begitu selesai.
        try {
            $disk = Storage::disk(GcsBerkas::DISK);
            if (! $disk->exists($row->File_Path)) {
                abort(404, 'Berkas laporan tidak ditemukan.');
            }

            $namaFile = basename($row->File_Path);
            $ext = strtolower(pathinfo($namaFile, PATHINFO_EXTENSION));

            return response()->streamDownload(
                function () use ($disk, $row) {
                    $aliran = $disk->readStream($row->File_Path);
                    if ($aliran) {
                        fpassthru($aliran);
                        fclose($aliran);
                    }
                },
                $namaFile,
                [
                    'Content-Type' => $ext === 'xlsx'
                        ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
                        : 'application/pdf',
                    // Panjangnya disebut supaya bilah kemajuan tahu totalnya;
                    // tanpa ini peramban hanya bisa melaporkan byte terunduh,
                    // dan persentasenya mustahil dihitung.
                    'Content-Length' => (string) $disk->size($row->File_Path),
                    'Cache-Control' => 'private, no-store',
                ],
            );
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::channel('web_career')->warning('[LAPORAN] aliran unduhan gagal: '.$e->getMessage());
        }

        abort(404, 'Berkas laporan tidak ditemukan.');
    }

    /**
     * GET /api/v1/karir/lamaran/sub-tes/{id}/berkas — berkas milik SATU aktivitas.
     *
     * Dipisah dari berkasTahap() yang mengembalikan berkas seluruh tahap. Pada
     * tahap campuran (Psikotes 2 + DISC + Wawancara HR), daftar setingkat tahap
     * mencampur lembar jawaban DISC dengan form wawancara tanpa penanda mana
     * milik mana — dan penilai wawancara jadi tidak punya cara memastikan
     * berkas yang ia unggah barusan sudah masuk.
     */
    public function subTesBerkas(string $id)
    {
        $realId = Hashids::decode($id)[0] ?? null;
        if (! $realId) {
            return ResponseHelper::error('Aktivitas tidak valid.', 422);
        }

        return ResponseHelper::success(self::daftarBerkasAktivitas((int) $realId), 'Berkas aktivitas');
    }

    /**
     * Berkas satu aktivitas dalam bentuk yang siap dikirim ke layar.
     *
     * Dipakai dua tempat — endpoint daftarnya sendiri dan rapor tes di worklist
     * — sehingga bentuk datanya mustahil berbeda antara "dimuat saat drawer
     * dibuka" dan "dimuat ulang setelah mengunggah".
     */
    private static function daftarBerkasAktivitas(int $subTesId): array
    {
        return self::bentukBerkasAktivitas(
            DB::table('N_WEB_CAREERS_Lamaran_Tahap_Berkas')
                ->where('Lamaran_Tahap_Tes_Id', $subTesId)
                ->orderByDesc('Id_Lamaran_Tahap_Berkas')
                ->get()
        );
    }

    /**
     * GET /api/v1/karir/lamaran/sub-tes/berkas-kandidat/{id} — buka berkas yang
     * DIUNGGAH KANDIDAT, dari sisi admin.
     *
     * Endpoint portalnya (tesBerkasFile) memeriksa kepemilikan lewat Id_Users,
     * jadi mustahil dipakai admin: berkas milik kandidat lain selalu 404. Tanpa
     * pintu ini, seluruh aturan "kandidat wajib mengunggah" di Master Alur tidak
     * ada gunanya — berkasnya masuk tapi tak seorang pun di tim bisa membacanya.
     */
    public function berkasKandidatFile(string $id)
    {
        $realId = Hashids::decode($id)[0] ?? null;
        $b = $realId ? DB::table('N_WEB_CAREERS_Lamaran_Tes_Berkas')->where('Id_Lamaran_Tes_Berkas', $realId)->first() : null;

        if (! $b || ! $b->Path_File) {
            abort(404);
        }

        try {
            $gcs = Storage::disk(GcsBerkas::DISK);
            if ($gcs->exists($b->Path_File)) {
                return redirect()->away($gcs->temporaryUrl($b->Path_File, now()->addMinutes(15)));
            }
        } catch (\Throwable $e) {
            Log::channel('web_career')->warning('[BERKAS-KANDIDAT] signed URL gagal: '.$e->getMessage());
        }

        abort(404);
    }

    /**
     * Berkas UNGGAHAN KANDIDAT sebuah aktivitas, dibentuk untuk layar ADMIN.
     *
     * Tautannya menunjuk rute admin, bukan rute portal — lihat alasannya di
     * berkasKandidatFile().
     */
    private static function bentukBerkasKandidat(iterable $rows): array
    {
        return collect($rows)
            ->map(function ($b) {
                $ext = strtolower($b->Ext ?: pathinfo($b->Nama_File, PATHINFO_EXTENSION));

                return [
                    'id' => Hashids::encode($b->Id_Lamaran_Tes_Berkas),
                    'nama' => $b->Nama_File,
                    'ext' => $ext,
                    'ukuran' => (int) $b->Ukuran,
                    'isImage' => in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true),
                    'url' => route('career.api.lamaran.berkas.kandidat', ['id' => Hashids::encode($b->Id_Lamaran_Tes_Berkas)]),
                    'createdAt' => $b->Created_At,
                    // Kapan berkas INI ikut diserahkan — kosong berarti SUSULAN
                    // yang datang setelah pernyataan lengkap terakhir. Penilai
                    // perlu bisa membedakannya: berkas susulan belum tentu sudah
                    // dimaksudkan kandidat sebagai bagian dari yang dinilai.
                    'terkirim' => ($b->Terkirim_At ?? null) ? (string) $b->Terkirim_At : null,
                ];
            })
            ->values()
            ->all();
    }

    /** Baris berkas → bentuk untuk layar. Satu tempat, dipakai semua pemanggil. */
    private static function bentukBerkasAktivitas(iterable $rows): array
    {
        return collect($rows)
            ->map(fn ($b) => [
                'id' => Hashids::encode($b->Id_Lamaran_Tahap_Berkas),
                'nama' => $b->Nama_File,
                'ext' => $b->Ext,
                'ukuran' => (int) $b->Ukuran,
                'isImage' => in_array(strtolower((string) $b->Ext), ['jpg', 'jpeg', 'png', 'webp'], true),
                'url' => route('career.api.lamaran.tahap.berkas.file', Hashids::encode($b->Id_Lamaran_Tahap_Berkas)),
                'olehSiapa' => $b->Created_By,
                'createdAt' => $b->Created_At,
                // Komponen pemeriksaan yang menaunginya — null untuk berkas biasa.
                'verifikasiId' => isset($b->Verifikasi_Latar_Id) ? (int) $b->Verifikasi_Latar_Id ?: null : null,
            ])
            ->values()
            ->all();
    }

    /**
     * POST /api/v1/karir/lamaran/sub-tes/{id}/berkas — unggah hasil satu aktivitas.
     *
     * Inilah tempat form wawancara yang dipindai berlabuh: melekat pada sesi
     * wawancara yang benar-benar dinilai, bukan pada tahapnya. OPSIONAL — tidak
     * semua wawancara memakai lembar penilaian cetak, dan mewajibkannya hanya
     * membuat penilai mengunggah berkas asal-asalan supaya bisa lanjut.
     *
     * Aktivitas yang SUDAH FINAL ditolak: berkas yang masuk setelah hasilnya
     * dicatat tidak pernah ikut menjadi dasar keputusan, sehingga membiarkannya
     * masuk hanya menciptakan bukti yang tampak mendukung padahal tidak dibaca
     * siapa pun saat memutus.
     */
    public function subTesBerkasUnggah(Request $request, string $id)
    {
        $realId = Hashids::decode($id)[0] ?? null;
        if (! $realId) {
            return ResponseHelper::error('Aktivitas tidak valid.', 422);
        }

        $sub = DB::table('N_WEB_CAREERS_Lamaran_Tahap_Tes as t')
            ->join('N_WEB_CAREERS_Lamaran_Tahap as h', 'h.Id_Lamaran_Tahap', '=', 't.Lamaran_Tahap_Id')
            ->where('t.Id_Lamaran_Tahap_Tes', $realId)
            // `Urutan` ikut diambil: gerbang urutan aktivitas membacanya untuk
            // tahu aktivitas mana yang berada di depan yang ini.
            ->select('t.Id_Lamaran_Tahap_Tes', 't.Lamaran_Tahap_Id', 't.Urutan', 't.Label', 't.Tipe_Tahap_Kode', 't.Flag_Selesai',
                't.Jadwal_Mode', 't.Jadwal_Mulai', 'h.Lamaran_Id')
            ->first();

        if (! $sub) {
            return ResponseHelper::error('Aktivitas tidak ditemukan.', 404);
        }
        if ($sub->Flag_Selesai === 'Y') {
            return ResponseHelper::error('Aktivitas ini sudah final — berkasnya tidak bisa ditambah lagi.', 409);
        }
        // HASIL YANG DIUNGGAH KANDIDAT SENDIRI (MCU mandiri): kandidatlah yang
        // memegang hasil dan kwitansinya, dan berkasnya masuk lewat portal.
        // Tim tidak mengunggah di sini — berkas kedua dari tim akan terbaca
        // sebagai hasil pengganti. Vendor tetap diunggah tim.
        if (! empty($sub->Jadwal_Mulai) && UndanganJadwal::unggahKandidat(UndanganJadwal::mode($sub->Jadwal_Mode ?? null))) {
            return ResponseHelper::error('Hasil aktivitas ini diunggah kandidat sendiri lewat portal ('
                .(UndanganJadwal::mode($sub->Jadwal_Mode)->Nama ?? 'Mandiri').') — tim tidak mengunggah atau menggantinya.', 409);
        }

        if ($kunci = self::kunciUrutan($sub)) {
            return ResponseHelper::error($kunci, 409);
        }

        $request->validate(['file' => 'required|file|mimes:pdf,jpg,jpeg|max:2048'], [
            'file.required' => 'Tidak ada berkas yang dipilih.',
            'file.mimes' => 'Format berkas harus PDF atau JPG.',
            'file.max' => self::pesanUkuran($request, 2048),
        ]);

        $lam = DB::table('N_WEB_CAREERS_Lamaran as l')
            ->leftJoin('N_WEB_CAREERS_Users as u', 'u.Id_Users', '=', 'l.Id_Users')
            ->where('l.Id_Lamaran', $sub->Lamaran_Id)
            ->select('u.Nama')
            ->first();

        $gcs = app(GcsBerkas::class);
        $now = now();
        $file = $request->file('file');
        $ext = $file->getClientOriginalExtension();
        $konten = $file->get();

        try {
            $gcs->validasi($file->getClientOriginalName(), $ext, strlen($konten));
            $folder = $gcs->folderHasilTahap(
                $now->format('Y'),
                $now->format('m'),
                $now->format('d'),
                $lam->Nama ?? 'kandidat',
                $sub->Tipe_Tahap_Kode,
            );
            // Nama file mengandung potongan acak: satu aktivitas boleh menerima
            // beberapa lembar, dan nama deterministik akan menimpa yang lama.
            $label = 'hasil-'.strtolower($sub->Tipe_Tahap_Kode ?: 'aktivitas').'-'.Str::lower(Str::random(6));
            $path = $gcs->unggah($folder, $label, $ext, $konten);
        } catch (\Throwable $e) {
            return ResponseHelper::error($e->getMessage(), 422);
        }

        /* BERKAS BISA MENEMPEL KE SATU KOMPONEN PEMERIKSAAN.
         *
         * Hasil verifikasi dari kampus menempel ke baris PENDIDIKAN, bukan
         * mengambang di aktivitas. Tanpa itu, sepuluh lampiran background check
         * berakhir sebagai sepuluh berkas tak bernama yang harus dibuka satu per
         * satu untuk tahu mana milik komponen mana.
         *
         * Idnya diperiksa terhadap aktivitas ini juga: id dari luar tidak boleh
         * menempelkan berkas ke pemeriksaan milik lamaran orang lain. */
        $verifikasiId = (int) $request->input('verifikasiId', 0) ?: null;

        if ($verifikasiId) {
            $sah = DB::table('N_WEB_CAREERS_Verifikasi_Latar')
                ->where('Id_Verifikasi_Latar', $verifikasiId)
                ->where('Lamaran_Tahap_Tes_Id', $sub->Id_Lamaran_Tahap_Tes)
                ->exists();

            if (! $sah) {
                $verifikasiId = null;
            }
        }

        DB::table('N_WEB_CAREERS_Lamaran_Tahap_Berkas')->insert([
            'Lamaran_Tahap_Id' => $sub->Lamaran_Tahap_Id,
            'Lamaran_Tahap_Tes_Id' => $sub->Id_Lamaran_Tahap_Tes,
            'Lamaran_Id' => $sub->Lamaran_Id,
            'Jenis' => $sub->Tipe_Tahap_Kode,
            'Nama_File' => $file->getClientOriginalName(),
            'Path_File' => $path,
            'Mime' => $file->getClientMimeType(),
            'Ukuran' => strlen($konten),
            'Ext' => $gcs->normalkanExt($ext),
            'Verifikasi_Latar_Id' => $verifikasiId,
            'Created_At' => $now,
            'Created_By' => session('career_auth.nama', 'ADMIN'),
            'Created_By_Id' => session('career_auth.id'),
            'Updated_At' => $now,
        ]);

        Log::channel('web_career')->info("Berkas aktivitas #{$realId} ({$sub->Label}) diunggah: {$file->getClientOriginalName()}");

        return ResponseHelper::success(self::daftarBerkasAktivitas((int) $realId), 'Berkas terunggah.');
    }

    /**
     * POST /api/v1/karir/lamaran/catatan/gambar — unggah gambar untuk DITANAM
     * di dalam catatan berformat (Quill).
     *
     * Yang kembali hanyalah TAUTAN ke rute penyaji, bukan berkasnya sendiri dan
     * bukan pula data URI. Alasannya ada di App\Support\Career\HtmlBersih:
     * hanya bentuk tautan inilah yang lolos penyaring saat catatannya disimpan.
     *
     * Gambar diunggah SEBELUM catatannya disimpan — itu sifat editor teks. Maka
     * baris di sini boleh yatim untuk sementara (catatannya batal ditulis).
     * Itu disengaja: memaksa keduanya satu transaksi berarti gambar baru bisa
     * tampil setelah catatan disimpan, dan penilai menulis sambil melihat
     * kotak kosong.
     */
    public function catatanGambarUnggah(Request $request)
    {
        // Pesan DALAM BAHASA INDONESIA dan MENYEBUT ANGKANYA.
        //
        // Bawaan Laravel berbunyi "The file field must not be greater than 2048
        // kilobytes." — bahasa asing, satuan yang tak lazim dibaca orang, dan
        // tidak menyebut berkas yang barusan dipilih sebesar apa. Yang membaca
        // jadi tidak tahu harus memperkecil sampai berapa.
        $request->validate([
            'file' => 'required|file|mimes:jpg,jpeg,png,webp|max:2048',
        ], [
            'file.required' => 'Tidak ada berkas yang dipilih.',
            'file.mimes' => 'Format gambar harus JPG, PNG, atau WEBP.',
            'file.max' => self::pesanUkuran($request, 2048),
            // Konteksnya ikut bila diketahui — editor selalu dibuka dari sebuah
            // aktivitas atau tahap, jadi asal gambarnya tidak perlu ditebak
            // belakangan lewat mencocokkan tautan di dalam HTML.
            'subTesId' => 'nullable|string|max:64',
            'tahapId' => 'nullable|string|max:64',
        ]);

        $subTesId = $request->filled('subTesId') ? (Hashids::decode($request->input('subTesId'))[0] ?? null) : null;
        $tahapId = $request->filled('tahapId') ? (Hashids::decode($request->input('tahapId'))[0] ?? null) : null;

        // Lamaran ditelusuri dari konteksnya supaya gambar tersimpan di folder
        // kandidat yang benar — bucket yang bisa ditelusuri per orang jauh lebih
        // berguna daripada satu tumpukan bernama tanggal.
        $lamaranId = null;
        if ($subTesId) {
            $lamaranId = (int) DB::table('N_WEB_CAREERS_Lamaran_Tahap_Tes as t')
                ->join('N_WEB_CAREERS_Lamaran_Tahap as h', 'h.Id_Lamaran_Tahap', '=', 't.Lamaran_Tahap_Id')
                ->where('t.Id_Lamaran_Tahap_Tes', $subTesId)
                ->value('h.Lamaran_Id');
        } elseif ($tahapId) {
            $lamaranId = (int) DB::table('N_WEB_CAREERS_Lamaran_Tahap')->where('Id_Lamaran_Tahap', $tahapId)->value('Lamaran_Id');
        }

        $nama = $lamaranId
            ? DB::table('N_WEB_CAREERS_Lamaran as l')
                ->leftJoin('N_WEB_CAREERS_Users as u', 'u.Id_Users', '=', 'l.Id_Users')
                ->where('l.Id_Lamaran', $lamaranId)
                ->value('u.Nama')
            : null;

        $gcs = app(GcsBerkas::class);
        $now = now();
        $file = $request->file('file');
        $ext = $file->getClientOriginalExtension();
        $konten = $file->get();

        try {
            $gcs->validasiGambar($file->getClientOriginalName(), $ext, strlen($konten));
            $path = $gcs->unggahGambarCatatan(
                $gcs->folderCatatan($now->format('Y'), $now->format('m'), $now->format('d'), $nama ?: 'kandidat'),
                $ext,
                $konten,
            );
        } catch (\Throwable $e) {
            return ResponseHelper::error($e->getMessage(), 422);
        }

        $id = DB::table('N_WEB_CAREERS_Catatan_Gambar')->insertGetId([
            'Konteks' => $subTesId ? 'LAMARAN_TAHAP_TES' : ($tahapId ? 'LAMARAN_TAHAP' : 'LEPAS'),
            'Ref_Id' => $subTesId ?: $tahapId,
            'Lamaran_Id' => $lamaranId ?: null,
            'Nama_File' => $file->getClientOriginalName(),
            'Path_File' => $path,
            'Mime' => $file->getClientMimeType(),
            'Ext' => $gcs->normalkanExt($ext),
            'Ukuran' => strlen($konten),
            'Created_At' => $now,
            'Created_By' => session('career_auth.nama', 'ADMIN'),
            'Created_By_Id' => session('career_auth.id'),
        ]);

        return ResponseHelper::success([
            'id' => Hashids::encode($id),
            // Path relatif, bukan URL absolut: inilah yang disimpan ke dalam
            // HTML, dan menyimpan nama host di sana membuat seluruh gambar mati
            // begitu domainnya berganti (dev → staging → production).
            'url' => '/api/v1/karir/lamaran/catatan/gambar/'.Hashids::encode($id),
        ], 'Gambar terunggah.');
    }

    /**
     * GET /karir/laporan/{lamaran}/berkas/{berkas} — dokumen yang ditautkan
     * dari dalam PDF laporan. BERTANDA TANGAN, tanpa sesi.
     *
     * Pembaca PDF tidak membawa cookie; tautan ke rute admin biasa akan selalu
     * mendarat di halaman login. Yang menggantikan sesi di sini adalah tanda
     * tangan HMAC pada URL-nya (middleware `signed`) berikut kedaluwarsanya.
     *
     * DUA GERBANG, bukan satu:
     *   1. Tanda tangan sah & belum kedaluwarsa — dijaga middleware.
     *   2. Berkasnya BENAR milik lamaran yang disebut di URL — dijaga di sini.
     *      Tanpa nomor 2, satu tautan sah bisa dipelintir nomor berkasnya dan
     *      berubah jadi kunci ke dokumen kandidat lain.
     */
    public function laporanBerkas(string $lamaran, string $berkas)
    {
        $lamaranId = Hashids::decode($lamaran)[0] ?? null;
        $berkasId = Hashids::decode($berkas)[0] ?? null;

        if (! $lamaranId || ! $berkasId) {
            abort(404);
        }

        $b = DB::table('N_WEB_CAREERS_Formulir_Berkas as fb')
            ->join('N_WEB_CAREERS_Formulir_Pengisian as fp', 'fp.Id_Formulir_Pengisian', '=', 'fb.Formulir_Pengisian_Id')
            ->where('fb.Id_Formulir_Berkas', $berkasId)
            ->where('fp.Lamaran_Id', $lamaranId)
            ->select('fb.Path_File', 'fb.Nama_Asli')
            ->first();

        if (! $b || ! $b->Path_File) {
            abort(404);
        }

        // Dicatat: tautan ini hidup di luar sesi, jadi jejaknya satu-satunya
        // cara mengetahui dokumen siapa yang dibuka dari salinan PDF yang mana.
        Log::channel('web_career')->info(
            "[LAPORAN-BERKAS] lamaran #{$lamaranId} berkas #{$berkasId} dibuka dari tautan bertanda tangan"
        );

        try {
            $disk = Storage::disk(GcsBerkas::DISK);
            if ($disk->exists($b->Path_File)) {
                return redirect()->away($disk->temporaryUrl($b->Path_File, now()->addMinutes(15)));
            }
        } catch (\Throwable $e) {
            Log::channel('web_career')->warning('[LAPORAN-BERKAS] signed URL gagal: '.$e->getMessage());
        }

        abort(404, 'Berkas tidak ditemukan.');
    }

    /**
     * GET /api/v1/karir/lamaran/catatan/gambar/{id} — sajikan gambar catatan.
     *
     * Lewat rute berwenang, bukan tautan publik GCS: isi catatan penilaian
     * adalah bahan internal, dan tautan bucket yang bisa dibuka siapa saja akan
     * membocorkannya begitu satu potongan HTML tersalin keluar.
     */
    public function catatanGambar(string $id)
    {
        $realId = Hashids::decode($id)[0] ?? null;
        $g = $realId ? DB::table('N_WEB_CAREERS_Catatan_Gambar')->where('Id_Catatan_Gambar', $realId)->first() : null;
        if (! $g) {
            abort(404);
        }

        try {
            $disk = Storage::disk(GcsBerkas::DISK);
            if ($disk->exists($g->Path_File)) {
                return redirect()->away($disk->temporaryUrl($g->Path_File, now()->addMinutes(15)));
            }
        } catch (\Throwable $e) {
            Log::channel('web_career')->warning('Signed URL gambar catatan gagal: '.$e->getMessage());
        }

        abort(404, 'Gambar tidak ditemukan.');
    }

    /**
     * Rapikan catatan berformat jadi sepasang nilai yang disimpan bersama:
     * HTML lengkapnya + ringkasan teks polos untuk kolom `Catatan`.
     *
     * Ringkasannya DITURUNKAN, bukan diketik terpisah. Dua kolom yang diisi
     * manusia secara terpisah pasti berselisih suatu saat, dan yang tampil di
     * daftar worklist adalah yang polos — begitu ia basi, admin membaca
     * kesimpulan lama untuk catatan yang sudah diperbarui.
     *
     * @return array{0: ?string, 1: ?string} [html, ringkasPolos]
     */
    private static function catatanKaya(?string $html, ?string $polos = null): array
    {
        $bersih = HtmlBersih::saring($html);

        if ($bersih === null) {
            $polos = trim((string) $polos);

            return [null, $polos !== '' ? $polos : null];
        }

        $teks = \App\Support\Career\HtmlBersih::keTeks($bersih);
        // Catatan yang isinya hanya gambar tetap perlu ringkasan yang berarti —
        // baris kosong di worklist terbaca "belum dicatat", padahal sudah.
        if ($teks === '') {
            $teks = '(catatan berupa gambar)';
        }

        return [$bersih, Str::limit($teks, 480)];
    }

    /**
     * POST /api/v1/lamaran/sub-tes/{id}/sinkron — TARIK HASIL DARI HCLEARN.
     *
     * Pengganti "catat manual" untuk ujian online. Kalau webhook CAT tak sampai
     * (jaringan putus, aplikasi restart, secret salah), dulu satu-satunya jalan
     * adalah admin mengetik nilai sendiri — menebak angka yang bukan miliknya.
     * Sekarang admin cukup menarik: nilainya dibaca LANGSUNG dari tabel CAT
     * (`HRIS_KANDIDAT_Ujian_Token` + `..._Ujian_Nilai_Akhir`, satu database),
     * lalu masuk lewat pintu yang sama dengan webhook. Yang tersimpan tetap
     * angka resmi penyedia, bukan ketikan orang.
     *
     * Aman diulang: bila hasilnya sudah tercatat, tahap tidak diproses dua kali.
     */
    public function subTesSinkron(string $id)
    {
        $realId = Hashids::decode($id)[0] ?? null;
        if (! $realId) {
            return ResponseHelper::error('Aktivitas tidak valid.', 422);
        }

        try {
            $sub = DB::table('N_WEB_CAREERS_Lamaran_Tahap_Tes')->where('Id_Lamaran_Tahap_Tes', $realId)->first();
            if (! $sub) {
                return ResponseHelper::error('Aktivitas tidak ditemukan.', 404);
            }
            if ($sub->Flag_Selesai === 'Y') {
                return ResponseHelper::error('Hasil aktivitas ini sudah final — tidak perlu disinkronkan.', 422);
            }

            $tipeSub = self::masterTipeTahap()[$sub->Tipe_Tahap_Kode ?? ''] ?? null;
            $online = ($tipeSub->Perilaku_Kode ?? null) === 'CAT' || ($sub->Provider ?? '') === 'THIRD_PARTY';
            if (! $online) {
                return ResponseHelper::error("\"{$sub->Label}\" dikerjakan tim — hasilnya dicatat lewat tombol Catat Hasil, bukan ditarik dari HCLearn.", 422);
            }
            if (! $sub->Penjadwalan_Tahap_Id) {
                return ResponseHelper::error("\"{$sub->Label}\" belum dijadwalkan — belum ada sesi ujian yang bisa ditarik hasilnya.", 422);
            }

            $lamaranId = (int) DB::table('N_WEB_CAREERS_Lamaran_Tahap')
                ->where('Id_Lamaran_Tahap', $sub->Lamaran_Tahap_Id)->value('Lamaran_Id');

            $peserta = DB::table('N_WEB_CAREERS_Penjadwalan_Peserta')
                ->where('Penjadwalan_Tahap_Id', $sub->Penjadwalan_Tahap_Id)
                ->where('Lamaran_Id', $lamaranId)
                ->first();
            if (! $peserta) {
                return ResponseHelper::error('Peserta ujian untuk aktivitas ini tidak ditemukan.', 404);
            }

            // ── TARIK LEWAT API, BUKAN BACA TABEL CAT ───────────────────────
            //
            // Dulu tabel token & nilai CAT dibaca langsung. Itu hanya bekerja
            // saat CAT berjalan lokal dan sedatabase; di staging/production CAT
            // punya databasenya sendiri sehingga pembacaan itu selalu kosong dan
            // sinkron selalu gagal "sesi tidak ditemukan". Sekarang lewat
            // GET penjadwalan/{Id_Ujian_Token} yang memang disediakan CAT.
            if (! $peserta->Ref_Ujian_Token) {
                return ResponseHelper::error(
                    "\"{$sub->Label}\" dijadwalkan sebelum penautan sesi ada, jadi hasilnya tak bisa ditarik. Batalkan penjadwalannya lalu buat ulang.",
                    409
                );
            }

            $balas = app(\App\Services\WebCareers\HclClient::class)->get(
                "penjadwalan/{$peserta->Ref_Ujian_Token}",
                [],
                ['Jenis_Event' => 'SINKRON_HASIL', 'Penjadwalan_Peserta_Id' => $peserta->Id_Penjadwalan_Peserta]
            );

            if (! $balas['sukses']) {
                return ResponseHelper::error("HCLearn: {$balas['message']}", (int) ($balas['status'] ?: 422));
            }

            $sesi = $balas['result'] ?? [];
            $statusKerja = $sesi['Status_Pengerjaan'] ?? null;
            $nilai = $sesi['Hasil'] ?? [];

            // Belum ada nilai → jangan mengarang. Sampaikan apa adanya, sekaligus
            // segarkan status pengerjaan supaya admin melihat perkembangan nyata.
            if (empty($nilai['Id_Ujian_Nilai_Akhir'])) {
                DB::table('N_WEB_CAREERS_Penjadwalan_Peserta')
                    ->where('Id_Penjadwalan_Peserta', $peserta->Id_Penjadwalan_Peserta)
                    ->update(['Status_Pengerjaan' => $statusKerja, 'Updated_At' => now()]);

                $status = $statusKerja ?: 'belum dimulai';

                return ResponseHelper::error("Belum ada nilai di HCLearn untuk \"{$sub->Label}\" — status pengerjaan saat ini: {$status}. Coba lagi setelah kandidat menyelesaikan tesnya.", 422);
            }

            $hasil = $this->prosesHasilUjian($peserta, [
                'Status_Kelulusan' => $nilai['Status_Kelulusan'] ?? null,
                'Total_Nilai' => $nilai['Total_Nilai'] ?? null,
                'Total_Soal' => $nilai['Total_Soal'] ?? null,
                'Ambang_Batas_Nilai' => $nilai['Ambang_Batas_Nilai'] ?? null,
                'Status_Pengerjaan' => $statusKerja ?: 'selesai',
            ], 'SINKRON');

            if (! $hasil['diproses']) {
                return ResponseHelper::success($hasil, 'Hasil sudah tercatat sebelumnya — tidak ada yang berubah.');
            }

            return ResponseHelper::success(
                $hasil,
                "Hasil \"{$sub->Label}\" ditarik dari HCLearn: ".($nilai['Status_Kelulusan'] ?? '-').' (nilai '.($nilai['Total_Nilai'] ?? '-').').'
            );
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal sinkron hasil sub-tes #{$id}: ".$e->getMessage());

            return ResponseHelper::error('Gagal menarik hasil dari HCLearn.', 500);
        }
    }

    /**
     * PATCH /api/v1/lamaran/sub-tes/{id}/tidak-hadir — ESCAPE HATCH admin.
     * Sub-tes yang kandidatnya tidak hadir / token hangus ditandai TIDAK_HADIR
     * supaya tahap tidak menggantung menunggu hasil yang tak akan datang; mesin
     * langsung dievaluasi ulang (bisa berujung SIAP_DIPUTUS / GUGUR sesuai mode).
     *
     * Catatan OPSIONAL — sama seperti pencatatan kehadiran. Alasannya sering
     * perlu direkam ("sakit, minta jadwal ulang"), tapi mewajibkannya hanya
     * menahan tim pada kasus yang tidak butuh penjelasan apa pun.
     */
    public function subTesTidakHadir(Request $request, string $id)
    {
        $realId = Hashids::decode($id)[0] ?? null;
        if (! $realId) {
            return ResponseHelper::error('Sub-tes tidak valid.', 422);
        }

        $data = $request->validate([
            'catatan' => 'nullable|string',
        ]);

        try {
            $sub = DB::table('N_WEB_CAREERS_Lamaran_Tahap_Tes')->where('Id_Lamaran_Tahap_Tes', $realId)->first();
            if (! $sub) {
                return ResponseHelper::error('Sub-tes tidak ditemukan.', 404);
            }
            if ($sub->Flag_Selesai === 'Y') {
                return ResponseHelper::error('Sub-tes ini sudah final.', 422);
            }

            if ($kunci = self::kunciUrutan($sub)) {
                return ResponseHelper::error($kunci, 409);
            }

            // SATU TRANSAKSI: menandai tidak hadir dan menyimpulkan tahapnya
            // adalah satu tindakan. Bila evaluasinya gagal setelah aktivitas
            // ditandai final, pintu ini menolak percobaan ulang ("sudah final")
            // dan tahapnya menggantung selamanya — persis keadaan yang justru
            // hendak dilepaskan oleh tombol ini.
            $outcome = DB::transaction(function () use ($realId, $sub, $data) {
                DB::table('N_WEB_CAREERS_Lamaran_Tahap_Tes')->where('Id_Lamaran_Tahap_Tes', $realId)->update([
                    'Status' => 'TIDAK_HADIR',
                    // PENENTU yang tak hadir dinilai GAGAL (mode Auto_Gugur akan
                    // menggugurkan); INFORMATIF cukup ditandai selesai tanpa hasil.
                    'Hasil' => $sub->Peran === 'PENENTU' ? 'GAGAL' : null,
                    'Flag_Selesai' => 'Y',
                    // Kehadiran ikut ditetapkan: layar membaca Jadwal_Hadir untuk
                    // tahu kehadiran sudah diputuskan. Tanpa ini, aktivitas
                    // berjadwal yang ditandai lewat jalur ini tetap terlihat
                    // "belum ditetapkan" dan menahan tombol keputusan.
                    'Jadwal_Hadir' => $sub->Jadwal_Mulai ? 'T' : $sub->Jadwal_Hadir,
                    'Jadwal_Hadir_At' => $sub->Jadwal_Mulai ? now() : $sub->Jadwal_Hadir_At,
                    'Jadwal_Hadir_By' => $sub->Jadwal_Mulai ? session('career_auth.nama') : $sub->Jadwal_Hadir_By,
                    'Catatan' => ($data['catatan'] ?? null) ?: $sub->Catatan,
                    'Waktu_Selesai' => now(),
                    'Updated_At' => now(),
                ]);

                $eval = $this->svc->evaluasiTahap((int) $sub->Lamaran_Tahap_Id, (int) session('career_auth.id'));

                return $eval['outcome'] ?? null;
            });

            Log::channel('web_career')->info("Sub-tes #{$realId} ditandai TIDAK_HADIR → evaluasi: ".($outcome ?? '-'));

            if ($sub->Jadwal_Mulai) {
                KonfirmasiJadwal::catatKehadiran((int) $realId, 'T', (string) session('career_auth.nama', 'ADMIN'), session('career_auth.id') ? (int) session('career_auth.id') : null);
            }

            return ResponseHelper::success(['outcome' => $outcome], 'Sub-tes ditandai tidak hadir.');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal tandai sub-tes #{$id}: ".$e->getMessage());

            return ResponseHelper::error('Gagal memproses.', 500);
        }
    }

    /**
     * PATCH /api/v1/lamaran/sub-tes/{id}/catat-hasil — admin merekam hasil
     * SUB-TES MANUAL (wawancara/FGD) di tahap campuran. Hasilnya masuk ke mesin
     * keputusan yang SAMA dengan callback HCLearn, sehingga tahap "psikotes 3×
     * + wawancara" bisa menyimpulkan (auto-maju / Siap Diputus) tanpa dipaksa.
     * Sub-tes pihak ke-3 ditolak — hasilnya hanya boleh datang dari HCLearn.
     */
    /**
     * PATCH /api/v1/karir/lamaran/sub-tes/{id}/jadwal — tetapkan jadwal wawancara
     * atau tes tatap muka, lalu undang kandidat lewat email.
     *
     * DARING wajib tautan pertemuan; LURING wajib lokasi. Keduanya wajib tanggal
     * & waktu — tanpa itu undangannya tidak berarti apa-apa. Validasinya
     * bersyarat (required_if) supaya rekruter tidak bisa mengirim undangan daring
     * tanpa tautan, yang justru membuat kandidat tidak tahu harus ke mana.
     *
     * Aktivitas ONLINE (ujian CAT) tidak lewat sini — jadwalnya sudah ditangani
     * modul Penjadwalan beserta token & OTP-nya.
     */
    public function subTesJadwal(Request $request, string $id)
    {
        $realId = Hashids::decode($id)[0] ?? null;
        if (! $realId) {
            return ResponseHelper::error('Aktivitas tidak valid.', 422);
        }

        $data = $request->validate([
            // Daftar mode dari MASTER — bentuk jadwal baru cukup satu baris data.
            'mode' => ['required', Rule::in(self::masterModeJadwal()->keys()->all())],
            // Wajib-tidaknya MULAI dan arti SELESAI ditentukan flag mode-nya —
            // lihat waktuJadwal(). Pada mode berbatas waktu (MCU mandiri) yang
            // diisi hanya batas akhirnya.
            'mulai' => 'nullable|date',
            'selesai' => 'nullable|date',
            // `required_if` sengaja TIDAK dipakai lagi: ia menuntut nama mode
            // ditulis di sini, dan itu persis yang membuat mode baru harus
            // menyunting validator. Syaratnya ditegakkan setelah ini, dari flag.
            'link' => 'nullable|url|max:500',
            'kontak' => 'nullable|string|max:40',
            // LURING: pilih dari Master Lokasi (berikut petanya). `lokasi` tetap
            // ada sebagai DETAIL — "Gedung B lantai 3, temui resepsionis" — persis
            // seperti catatan alamat pada aplikasi pesan-antar: titik petanya dari
            // master, patokan rincinya diketik.
            'lokasiId' => 'nullable|string|max:64',
            'lokasi' => 'nullable|string|max:300',
            // Tempat yang BELUM terdaftar (lokasiId = "__LAINNYA__"): RS dadakan
            // untuk kandidat luar kota. Wajib-tidaknya ditegakkan dari master
            // peruntukan, bukan dari `required_if` di sini.
            'lokasiNama' => 'nullable|string|max:200',
            'lokasiAlamat' => 'nullable|string|max:500',
            // VENDOR: tautan Google Maps yang DITEMPEL tim (opsional — tidak
            // pernah dikarang sistem) dan catatan cabang/lokasi berformat.
            'mapsUrl' => 'nullable|string|max:500',
            'lokasiHtml' => 'nullable|string|max:30000',
            // Surat pengantar yang BARU diunggah (ref dari /lamaran/jadwal/surat),
            // dan urutan surat LAMA yang dipertahankan. Tanpa `suratTetap`,
            // seluruh surat lama dipertahankan. Batasnya jumlah ukuran — lihat
            // susunSurat().
            'suratRef' => 'nullable|array|max:'.SuratJadwal::MAKS_BERKAS,
            'suratRef.*' => 'string|max:4000',
            'suratTetap' => 'nullable|array|max:'.SuratJadwal::MAKS_BERKAS,
            'suratTetap.*' => 'integer|min:0|max:100',
            // Informasi biaya ikut ke kandidat? Kosong = bawaan Master Alur.
            'tampilBiaya' => 'nullable|boolean',
            // Kalimat biaya yang dikirim — bisa disunting per jadwal; kosong =
            // kalimat alur, lalu kalimat tipe.
            'kalimatBiaya' => 'nullable|string|max:1000',
            'catatan' => 'nullable|string',
            // Instruksi berformat (daftar pemeriksaan, persiapan) — disaring
            // sebelum disimpan; teks polosnya diturunkan dari sini.
            'catatanHtml' => 'nullable|string|max:30000',
            // Kenapa jadwal yang SUDAH dikirim diubah — wajib, lihat di bawah.
            'alasan' => 'nullable|string|max:500',
        ], [
            'link.required_if' => 'Tautan pertemuan wajib diisi untuk wawancara daring.',
            'lokasiId.required_if' => 'Pilih lokasi untuk kegiatan tatap muka.',
        ]);

        $sub = DB::table('N_WEB_CAREERS_Lamaran_Tahap_Tes as t')
            ->join('N_WEB_CAREERS_Lamaran_Tahap as h', 'h.Id_Lamaran_Tahap', '=', 't.Lamaran_Tahap_Id')
            ->where('t.Id_Lamaran_Tahap_Tes', $realId)
            ->select('t.*', 'h.Lamaran_Id', 'h.Label as TahapLabel', 'h.Urutan as TahapUrutan', 'h.Tipe_Tahap_Kode as TahapTipe')
            ->first();

        if (! $sub) {
            return ResponseHelper::error('Aktivitas tidak ditemukan.', 404);
        }

        if ($sub->Flag_Selesai === 'Y') {
            return ResponseHelper::error('Aktivitas ini sudah selesai — jadwalnya tidak bisa diubah.', 409);
        }

        if ($kunci = self::kunciUrutan($sub)) {
            return ResponseHelper::error($kunci, 409);
        }

        // Sebagian aktivitas MUSTAHIL daring: MCU itu pemeriksaan fisik, tanda
        // tangan kontrak butuh kehadiran. Dijaga di SERVER juga — layar bisa
        // dilewati lewat DevTools, dan undangan daring untuk MCU akan membuat
        // kandidat datang ke tautan yang tidak akan pernah ada orangnya.
        $tipeSub = self::masterTipeTahap()[$sub->Tipe_Tahap_Kode ?? ''] ?? null;

        if ($galat = self::periksaBidangJadwal($data, $tipeSub)) {
            return ResponseHelper::error($galat, 422);
        }

        if (($tipeSub->Flag_Wajib_Luring ?? 'T') === 'Y'
            && (self::masterModeJadwal()->get($data['mode'])->Flag_Luring ?? 'T') !== 'Y') {
            return ResponseHelper::error(
                ($tipeSub->Nama ?? 'Aktivitas ini').' hanya bisa dijadwalkan LURING (tatap muka).',
                422,
            );
        }

        if ($galat = self::modeDitolak($tipeSub, $data['mode'])) {
            return ResponseHelper::error($galat, 422);
        }

        $modeDef = self::masterModeJadwal()->get($data['mode']);
        [$mulai, $selesai, $galat] = self::waktuJadwal($data, $modeDef, $sub->Jadwal_Mulai ?? null);
        if ($galat) {
            return ResponseHelper::error($galat, 422);
        }

        // ── JADWAL YANG SUDAH DIKIRIM DIUBAH → ALASANNYA WAJIB ─────────────
        // Kandidat sudah memegang undangan yang lama. Mengubahnya — pindah RS,
        // pindah ke klinik pilihan sendiri, geser tanggal — adalah pengecualian
        // yang harus bisa dijelaskan belakangan, dan satu kalimat sekarang jauh
        // lebih murah daripada menebaknya dari ingatan bulan depan. Hanya bila
        // tabel jejaknya ada: alasan yang tak punya tempat disimpan tidak
        // pantas dituntut.
        if (! empty($sub->Jadwal_Mulai) && JejakJadwal::siap()
            && mb_strlen(trim((string) ($data['alasan'] ?? ''))) < JejakJadwal::ALASAN_MIN) {
            return ResponseHelper::error(
                'Tuliskan alasan perubahan jadwal (minimal '.JejakJadwal::ALASAN_MIN.' karakter) — kandidat sudah menerima jadwal yang lama.',
                422,
            );
        }

        // ── SURAT PENGANTAR (mode ber-Flag_Butuh_Surat) ────────────────────
        // Daftar FINAL: surat lama yang dipertahankan + yang baru diunggah.
        // Mengubah tanggal tidak memaksa mengunggah ulang; tanpa satu surat
        // pun, jadwalnya tidak boleh terbit — kandidat yang datang tanpa surat
        // pengantar ditolak di loket.
        if (UndanganJadwal::butuhSurat($modeDef)) {
            [$data['_surat'], $galat] = self::susunSurat($sub, $data, $modeDef);
            if ($galat) {
                return ResponseHelper::error($galat, 422);
            }
        }

        [$data['catatanHtml'], $data['catatan']] = UndanganJadwal::saringInstruksi($data['catatanHtml'] ?? null, $data['catatan'] ?? null);
        // Catatan cabang vendor disaring sama seperti instruksi: gambar dibuang
        // (tak bisa dibuka kandidat, dan surel tidak memuatnya).
        $data['lokasiHtml'] = CatatanEksternal::saring($data['lokasiHtml'] ?? null);

        $aksi = JejakJadwal::aksi($sub);

        // Jadwal & konfirmasinya SATU transaksi: versi yang naik tanpa baris
        // konfirmasinya akan membuat tautan di surel menunjuk undangan kosong.
        // Batas konfirmasi = WAKTU MULAI jadwal ini (KonfirmasiJadwal::batasDari).
        DB::transaction(function () use ($realId, $data, $mulai, $selesai, $sub) {
            $this->terapkanJadwal((int) $realId, $data, $mulai, $selesai);
            KonfirmasiJadwal::terbitkan(
                (int) $realId,
                $sub,
                (string) session('career_auth.nama', 'ADMIN'),
                session('career_auth.id') ? (int) session('career_auth.id') : null,
            );
        });

        $undangan = $this->kirimUndanganJadwal((int) $realId, (int) $sub->Lamaran_Id);

        JejakJadwal::catat((int) $realId, $aksi, $data['alasan'] ?? null, $undangan);

        // Tiga keadaan, tiga kalimat. "Privat" bukan kegagalan: menyuruh admin
        // memeriksa log untuk sesuatu yang berjalan sebagaimana mestinya hanya
        // mengajarinya mengabaikan peringatan.
        $pesan = [
            'terkirim' => 'Jadwal disimpan dan undangan dikirim ke kandidat.',
            'privat' => 'Jadwal disimpan. '.($tipeSub->Nama ?? 'Aktivitas ini')
                .' bersifat internal — kandidat tidak menerima undangan dan tidak melihatnya di portal.',
            'gagal' => 'Jadwal disimpan. Undangan email gagal dikirim — periksa log.',
        ][$undangan];

        return ResponseHelper::success(
            ['emailTerkirim' => $undangan === 'terkirim', 'undangan' => $undangan],
            $pesan,
        );
    }

    /**
     * GET /api/v1/karir/lamaran/sub-tes/{id}/jadwal-info — bahan jendela Atur
     * Jadwal yang tidak ikut di muatan papan.
     *
     *   instruksiAlur  instruksi yang ditulis SEKALI di Master Alur untuk
     *                  aktivitas ini — mengisi editornya, jadi rekruter cukup
     *                  menyimpan. Tidak ikut di muatan papan: ratusan kartu kali
     *                  satu blok HTML untuk jendela yang dibuka satu per satu.
     *   jejak          riwayat jadwal aktivitas ini (dibuat / diubah /
     *                  diperpanjang / diingatkan) berikut alasannya.
     */
    public function subTesJadwalInfo(string $id)
    {
        $realId = Hashids::decode($id)[0] ?? null;
        if (! $realId) {
            return ResponseHelper::error('Aktivitas tidak valid.', 422);
        }

        $lamaranId = DB::table('N_WEB_CAREERS_Lamaran_Tahap_Tes as t')
            ->join('N_WEB_CAREERS_Lamaran_Tahap as h', 'h.Id_Lamaran_Tahap', '=', 't.Lamaran_Tahap_Id')
            ->where('t.Id_Lamaran_Tahap_Tes', $realId)
            ->value('h.Lamaran_Id');
        $tipe = $lamaranId ? self::tipeAktivitas((int) $realId) : null;

        // Lingkup yang sama dengan papan: riwayat jadwal & instruksinya milik
        // kandidat yang memang boleh dilihat akun ini.
        if (! $lamaranId || ! $this->lamaranDalamLingkup((int) $lamaranId)) {
            return ResponseHelper::error('Aktivitas tidak ditemukan.', 404);
        }

        $setelan = UndanganJadwal::setelanAlur((int) $realId);

        return ResponseHelper::success([
            'instruksiAlur' => $setelan['instruksi'],
            // Informasi biaya ditampilkan ke kandidat? — bawaan Master Alur —
            // berikut kalimatnya (kalimat alur, lalu kalimat tipe).
            'tampilBiayaAlur' => $setelan['tampilBiaya'],
            'kalimatBiayaAlur' => $setelan['kalimatBiaya'] ?: (trim((string) ($tipe->Kalimat_Biaya ?? '')) ?: null),
            'jejak' => JejakJadwal::daftar((int) $realId),
        ], 'Info jadwal');
    }

    /** Jeda minimum antara dua surel jadwal ke kandidat yang sama (menit). */
    private const JEDA_KIRIM_ULANG_MENIT = 10;

    /**
     * POST /api/v1/karir/lamaran/jadwal/surat — unggah SURAT PENGANTAR jadwal.
     *
     * Langkah pertama dari dua (lihat SuratJadwal): berkasnya disimpan dulu dan
     * dijawab dengan ref terenkripsi, yang lalu dibawa permintaan jadwal satuan
     * maupun massal. Belum terikat ke kandidat mana pun sampai jadwalnya
     * benar-benar disimpan.
     */
    public function jadwalSurat(Request $request)
    {
        if (! UndanganJadwal::siapVendor()) {
            return ResponseHelper::error('Surat pengantar belum aktif — skrip docs/30-09-2026/01 belum dijalankan.', 409);
        }

        $data = $request->validate([
            // Hanya PDF. Satu berkas tak mungkin melampaui batas TOTAL; jumlah
            // seluruh surat satu jadwal diperiksa saat jadwalnya disimpan.
            'berkas' => 'required|file|mimes:'.implode(',', SuratJadwal::FORMAT).'|mimetypes:application/pdf|max:'.(SuratJadwal::MAKS_TOTAL_MB * 1024),
        ], [
            'berkas.required' => 'Pilih berkas surat pengantarnya.',
            'berkas.mimes' => 'Surat pengantar hanya menerima PDF.',
            'berkas.mimetypes' => 'Surat pengantar hanya menerima PDF.',
            'berkas.max' => 'Ukuran surat melebihi '.SuratJadwal::MAKS_TOTAL_MB.' MB — batas itu untuk SELURUH surat satu jadwal.',
        ]);

        try {
            $hasil = SuratJadwal::simpan($data['berkas']);
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('[SURAT-JADWAL] unggah gagal: '.$e->getMessage());

            return ResponseHelper::error('Surat gagal diunggah: '.Str::limit($e->getMessage(), 140), 500);
        }

        return ResponseHelper::success($hasil, 'Surat pengantar terunggah.');
    }

    /**
     * GET /api/v1/karir/lamaran/sub-tes/{id}/surat/{urutan?} — surat pengantar,
     * dibuka dari worklist. `?unduh=1` = unduh sebagai berkas (lihat SuratJadwal::layani).
     */
    public function subTesSurat(Request $request, string $id, int $urutan = 0)
    {
        $realId = Hashids::decode($id)[0] ?? null;
        $s = $realId && UndanganJadwal::siapVendor()
            ? DB::table('N_WEB_CAREERS_Lamaran_Tahap_Tes as t')
                ->join('N_WEB_CAREERS_Lamaran_Tahap as h', 'h.Id_Lamaran_Tahap', '=', 't.Lamaran_Tahap_Id')
                ->where('t.Id_Lamaran_Tahap_Tes', $realId)
                ->first(['h.Lamaran_Id', 't.Jadwal_Surat_Json'])
            : null;

        // Lingkup yang sama dengan papan: surat milik kandidat yang memang
        // boleh dilihat akun ini.
        if (! $s || ! $this->lamaranDalamLingkup((int) $s->Lamaran_Id)) {
            abort(404);
        }

        return SuratJadwal::layani(SuratJadwal::path($s, $urutan), SuratJadwal::nama($s, $urutan), $request->boolean('unduh'));
    }

    /**
     * GET /kandidat/lamaran/tes/{id}/surat/{urutan?} — surat pengantar milik
     * kandidat yang login. Bawaannya tampil di peramban (tombol "Lihat");
     * `?unduh=1` mengunduhnya dengan nama aslinya; `?isi=1` menyajikan isinya
     * langsung untuk panel pratinjau di kartu MCU (lihat SuratJadwal::sajikanIsi).
     */
    public function tesSurat(Request $request, string $id, int $urutan = 0)
    {
        $tes = UndanganJadwal::siapVendor() ? $this->tesMilikSaya($id) : null;

        if ($request->boolean('isi')) {
            return SuratJadwal::sajikanIsi(
                $tes ? SuratJadwal::path($tes, $urutan) : null,
                $tes ? SuratJadwal::nama($tes, $urutan) : null,
            );
        }

        return SuratJadwal::layani(
            $tes ? SuratJadwal::path($tes, $urutan) : null,
            $tes ? SuratJadwal::nama($tes, $urutan) : null,
            $request->boolean('unduh'),
        );
    }

    /**
     * GET /karir/surat-jadwal/{id} — surat pengantar dari tautan SUREL.
     *
     * Tanpa sesi: surel dibuka di aplikasi surat yang tidak membawa cookie
     * portal. Rutenya bertanda tangan (`signed`, lihat SuratJadwal::tautanEmail)
     * sehingga tidak bisa ditebak maupun disunting ke aktivitas lain, dan mati
     * dengan sendirinya. Yang disajikan selalu surat TERBARU aktivitasnya.
     */
    public function suratJadwalPublik(string $id, int $urutan = 0)
    {
        $realId = Hashids::decode($id)[0] ?? null;
        $s = $realId && UndanganJadwal::siapVendor()
            ? DB::table('N_WEB_CAREERS_Lamaran_Tahap_Tes')->where('Id_Lamaran_Tahap_Tes', $realId)->first(['Jadwal_Surat_Json'])
            : null;
        $path = $s ? SuratJadwal::path($s, $urutan) : null;

        // Dicatat: tautan ini hidup di luar sesi, jadi jejaknya satu-satunya
        // cara mengetahui surat siapa yang dibuka.
        Log::channel('web_career')->info("[SURAT-JADWAL] surat #{$urutan} aktivitas #{$realId} dibuka dari tautan surel");

        // Selalu tampil (inline): tanda tangan tautan mencakup query-nya, jadi
        // "?unduh=1" yang ditempelkan akan membatalkan tautannya sendiri.
        return SuratJadwal::layani($path, $s ? SuratJadwal::nama($s, $urutan) : null);
    }

    /**
     * PATCH /api/v1/karir/lamaran/sub-tes/{id}/perpanjang — mundurkan BATAS
     * UNGGAH hasil pada jadwal yang kandidatnya mengunggah sendiri (MCU mandiri).
     *
     * Yang paling sering terjadi: hasil lab kandidat belum keluar. Rentang
     * pemeriksaan TIDAK ikut bergeser — ia informasi kapan memeriksakan diri,
     * sedangkan yang ditunggu tim adalah berkasnya. Hanya
     * Jadwal_Batas_Unggah yang diisi; tempat, surat, instruksi, dan rentangnya
     * tetap. Kotak unggah yang sudah tertutup terbuka lagi dengan sendirinya
     * (aturanUnggah membaca batas yang baru), dan kandidat dikabari lewat surel.
     *
     * Mode tanpa unggahan kandidat (VENDOR) tidak punya batas unggah: rentang
     * vendor diubah lewat "Ubah Jadwal", dengan alasan dan undangan baru.
     */
    public function subTesPerpanjang(Request $request, string $id)
    {
        $realId = Hashids::decode($id)[0] ?? null;
        if (! $realId) {
            return ResponseHelper::error('Aktivitas tidak valid.', 422);
        }

        $data = $request->validate([
            'selesai' => 'required|date',
            'alasan' => 'required|string|max:500',
        ], [
            'selesai.required' => 'Pilih tanggal batas unggah yang baru.',
            'alasan.required' => 'Tuliskan alasan perpanjangannya.',
        ]);

        $sub = self::subTesBerjadwal((int) $realId);
        if (! $sub) {
            return ResponseHelper::error('Aktivitas tidak ditemukan.', 404);
        }
        if ($galat = $this->galatPicTahap((int) $sub->Lamaran_Tahap_Id)) {
            return $galat;
        }
        if ($galat = self::galatJadwalTerbuka($sub)) {
            return ResponseHelper::error($galat, 409);
        }
        $mode = UndanganJadwal::mode($sub->Jadwal_Mode);
        if (! UndanganJadwal::berbatasWaktu($mode) || ! UndanganJadwal::unggahKandidat($mode)) {
            return ResponseHelper::error(
                'Perpanjang hanya untuk batas unggah hasil yang diunggah kandidat sendiri (mis. MCU mandiri). '
                    .'Untuk mengubah rentang pemeriksaan, gunakan "Ubah Jadwal".',
                409,
            );
        }
        if (! UndanganJadwal::siapBatasUnggah()) {
            return ResponseHelper::error('Perpanjangan batas unggah belum aktif — skrip docs/30-09-2026/01 belum dijalankan.', 409);
        }
        if ((UndanganJadwal::aturanUnggah($sub, $mode)['terkirim'] ?? null)) {
            return ResponseHelper::error('Kandidat sudah mengirim berkasnya — tidak ada batas unggah yang perlu diperpanjang.', 409);
        }
        if (mb_strlen(trim($data['alasan'])) < JejakJadwal::ALASAN_MIN) {
            return ResponseHelper::error('Alasan perpanjangan minimal '.JejakJadwal::ALASAN_MIN.' karakter.', 422);
        }

        $baru = UndanganJadwal::akhirHari((string) $data['selesai']);
        $lama = UndanganJadwal::batasUnggah($sub);
        if ($lama && $baru->lte($lama)) {
            return ResponseHelper::error('Tanggal baru harus sesudah batas unggah sekarang ('.UndanganJadwal::teksBatas($lama).').', 422);
        }
        if ($baru->lte(now())) {
            return ResponseHelper::error('Tanggal baru harus hari ini atau sesudahnya.', 422);
        }

        $nama = session('career_auth.nama');
        DB::table('N_WEB_CAREERS_Lamaran_Tahap_Tes')->where('Id_Lamaran_Tahap_Tes', $realId)->update([
            // Hanya batas unggah — rentang pemeriksaan (Jadwal_Mulai/Selesai) tetap.
            'Jadwal_Batas_Unggah' => $baru->format('Y-m-d H:i:s'),
            // Jadwal_At ikut maju: pengingat menjelang batas BARU boleh terkirim
            // lagi (satu pengingat per jadwal tersimpan — karir:pengingat-jadwal).
            'Jadwal_At' => now(),
            'Jadwal_By' => $nama,
            'Jadwal_By_Id' => session('career_auth.id'),
            'Updated_At' => now(),
            'Updated_By' => $nama,
        ]);

        $undangan = $this->kirimUndanganJadwal((int) $realId, (int) $sub->Lamaran_Id, ['perpanjang' => true]);
        JejakJadwal::catat((int) $realId, JejakJadwal::PERPANJANG, $data['alasan'], $undangan);

        $sampai = 'Batas unggah diperpanjang sampai '.UndanganJadwal::teksBatas($baru).' (tanggal pemeriksaan tetap)';

        return ResponseHelper::success(
            ['undangan' => $undangan, 'batas' => $baru->format('Y-m-d H:i:s')],
            [
                'terkirim' => $sampai.' — kandidat dikabari lewat email.',
                'privat' => $sampai.'.',
                'gagal' => $sampai.'. Email pemberitahuan gagal dikirim — periksa log.',
            ][$undangan],
        );
    }

    /**
     * POST /api/v1/karir/lamaran/sub-tes/{id}/kirim-ulang — kirim ulang surel
     * jadwal ke kandidat ("resend information").
     *
     * Isinya dirakit ulang dari jadwal SAAT INI (UndanganJadwal::muatan) — bukan
     * salinan surel lama — jadi yang terkirim selalu tempat, rentang, surat
     * pengantar, dan instruksi yang berlaku sekarang. Pada MCU mandiri yang
     * berkasnya belum masuk, suratnya berbunyi ajakan untuk mengunggah.
     */
    public function subTesKirimUlang(string $id)
    {
        $realId = Hashids::decode($id)[0] ?? null;
        $sub = $realId ? self::subTesBerjadwal((int) $realId) : null;
        if (! $sub) {
            return ResponseHelper::error('Aktivitas tidak ditemukan.', 404);
        }
        if ($galat = $this->galatPicTahap((int) $sub->Lamaran_Tahap_Id)) {
            return $galat;
        }

        [$galat, $kode, $undangan] = $this->kirimUlangSatu($sub);
        if ($galat) {
            return ResponseHelper::error($galat, $kode);
        }

        return ResponseHelper::success(['undangan' => $undangan], 'Email jadwal dikirim ulang ke kandidat.');
    }

    /**
     * POST /api/v1/karir/lamaran/sub-tes/kirim-ulang-massal — kirim ulang surel
     * jadwal ke beberapa kandidat sekaligus.
     *
     * Dibatasi BATAS_PUTUS_MASSAL per kiriman, alasan yang sama dengan jadwal
     * massal: puluhan surel serempak dari satu alamat dibaca penyedia surat
     * sebagai pengiriman massal mendadak. Tiap kandidat dilaporkan sendiri —
     * yang dilewati berikut alasannya.
     */
    public function kirimUlangMassal(Request $request)
    {
        $data = $request->validate([
            'subTesIds' => 'required|array|min:1|max:'.self::BATAS_PUTUS_MASSAL,
            'subTesIds.*' => 'required|string|max:64',
        ], [
            'subTesIds.max' => 'Maksimal '.self::BATAS_PUTUS_MASSAL.' kandidat sekali kirim ulang — tiap kiriman adalah satu email.',
        ]);

        $berhasil = [];
        $gagal = [];
        foreach (array_values(array_unique($data['subTesIds'])) as $hash) {
            $id = Hashids::decode($hash)[0] ?? null;
            $sub = $id ? self::subTesBerjadwal((int) $id) : null;
            $nama = $sub->Pelamar ?? ('#'.$hash);

            if (! $sub) {
                $gagal[] = ['nama' => $nama, 'alasan' => 'Aktivitas tidak ditemukan.'];

                continue;
            }
            if ($this->galatPicTahap((int) $sub->Lamaran_Tahap_Id)) {
                $gagal[] = ['nama' => $nama, 'alasan' => 'Di luar lingkup lowongan yang Anda pegang.'];

                continue;
            }

            [$galat] = $this->kirimUlangSatu($sub);
            if ($galat) {
                $gagal[] = ['nama' => $nama, 'alasan' => $galat];

                continue;
            }
            $berhasil[] = ['nama' => $nama];
        }

        Log::channel('web_career')->info(
            '[JADWAL-KIRIM-ULANG] '.count($berhasil).' terkirim, '.count($gagal).' dilewati — oleh '.session('career_auth.nama', 'ADMIN')
        );

        return ResponseHelper::success(
            ['berhasil' => $berhasil, 'gagal' => $gagal],
            count($gagal)
                ? count($berhasil).' email dikirim ulang, '.count($gagal).' dilewati.'
                : count($berhasil).' email jadwal dikirim ulang.',
        );
    }

    /**
     * Kirim ulang surel satu aktivitas — dipakai jalur satuan & massal.
     *
     * @return array{0: ?string, 1: int, 2: ?string} [galat, kode HTTP, hasil undangan]
     */
    private function kirimUlangSatu(object $sub): array
    {
        if ($galat = self::galatJadwalTerbuka($sub)) {
            return [$galat, 409, null];
        }
        if (JadwalPrivat::untuk($sub->Tipe_Tahap_Kode ?: ($sub->TahapTipe ?? null))) {
            return ['Jadwal ini internal — kandidat memang tidak dikirimi email.', 409, null];
        }

        // Surel "silakan unggah" untuk orang yang sudah mengirim — atau yang
        // kotak unggahnya sudah tertutup — hanya membingungkan.
        $aturan = UndanganJadwal::aturanUnggah($sub);
        if ($aturan && $aturan['terkirim']) {
            return ['Kandidat sudah mengirim berkasnya — tidak ada yang perlu diingatkan.', 409, null];
        }
        if ($aturan && $aturan['tertutup']) {
            return ['Batas unggahnya sudah lewat — perpanjang dulu supaya kandidat bisa mengunggah.', 409, null];
        }

        // Dua tekan beruntun (atau dua rekruter di kandidat yang sama) tidak
        // boleh menjadi dua surel identik di kotak masuk kandidat.
        if (! empty($sub->Jadwal_Email_At)) {
            $menit = (int) floor(\Illuminate\Support\Carbon::parse($sub->Jadwal_Email_At)->diffInMinutes(now()));
            if ($menit < self::JEDA_KIRIM_ULANG_MENIT) {
                return [
                    'Email jadwal baru dikirim '.($menit < 1 ? 'kurang dari semenit' : "{$menit} menit").' lalu — tunggu '
                        .self::JEDA_KIRIM_ULANG_MENIT.' menit sebelum mengirim ulang.',
                    429,
                    null,
                ];
            }
        }

        $batas = ! empty($aturan['batas']) ? \Illuminate\Support\Carbon::parse($aturan['batas']) : null;
        $undangan = $this->kirimUndanganJadwal(
            (int) $sub->Id_Lamaran_Tahap_Tes,
            (int) $sub->Lamaran_Id,
            ['kirim_ulang' => true] + ($batas ? ['sisa_teks' => UndanganJadwal::sisaTeks(now(), $batas)] : []),
        );
        JejakJadwal::catat((int) $sub->Id_Lamaran_Tahap_Tes, JejakJadwal::KIRIM_ULANG, null, $undangan);
        if ($undangan === 'terkirim') {
            // Kiriman tangan admin DIHITUNG & DITANDAI di snapshot CRM.
            KonfirmasiJadwal::tandaiKirimUlang((int) $sub->Id_Lamaran_Tahap_Tes, (string) session('career_auth.nama', 'ADMIN'));
        }

        return $undangan === 'terkirim'
            ? [null, 200, $undangan]
            : ['Email gagal diantrekan — periksa log.', 500, $undangan];
    }

    /** Satu aktivitas berikut status lamaran & tahapnya — bahan gerbang perpanjang / kirim ulang. */
    private static function subTesBerjadwal(int $subTesId): ?object
    {
        return DB::table('N_WEB_CAREERS_Lamaran_Tahap_Tes as t')
            ->join('N_WEB_CAREERS_Lamaran_Tahap as h', 'h.Id_Lamaran_Tahap', '=', 't.Lamaran_Tahap_Id')
            ->join('N_WEB_CAREERS_Lamaran as l', 'l.Id_Lamaran', '=', 'h.Lamaran_Id')
            ->leftJoin('N_WEB_CAREERS_Users as u', 'u.Id_Users', '=', 'l.Id_Users')
            ->where('t.Id_Lamaran_Tahap_Tes', $subTesId)
            ->select('t.*', 'h.Lamaran_Id', 'h.Tipe_Tahap_Kode as TahapTipe', 'l.Status as StatusLamaran', 'u.Nama as Pelamar')
            ->first();
    }

    /** Jadwal ini masih bisa diperpanjang / dikirim ulang? Pesan galat, atau null. */
    private static function galatJadwalTerbuka(object $sub): ?string
    {
        return match (true) {
            ($sub->StatusLamaran ?? '') !== 'BERJALAN' => 'Lamaran sudah tidak berjalan.',
            ($sub->Flag_Selesai ?? 'N') === 'Y' => 'Aktivitas ini sudah selesai.',
            ! empty($sub->Jadwal_Hadir) => 'Hasil aktivitas ini sudah dicatat.',
            empty($sub->Jadwal_Mulai) => 'Aktivitas ini belum dijadwalkan.',
            default => null,
        };
    }

    /**
     * Tulis jadwal ke satu aktivitas.
     *
     * Dipisah dari subTesJadwal() supaya penjadwalan SATUAN dan MASSAL menulis
     * kolom yang sama persis. Kalau keduanya punya salinan kodenya sendiri,
     * perbedaan sekecil apa pun — satu kolom yang lupa dikosongkan — hanya akan
     * muncul pada salah satu jalur, dan itu jenis selisih yang paling sulit
     * ditemukan karena keduanya "sama-sama bekerja".
     *
     * `$mulai`/`$selesai` dikirim terpisah dari `$data` karena penjadwalan
     * massal menghitung waktunya sendiri per kandidat (sesi bergiliran).
     */
    /**
     * Master bentuk pelaksanaan jadwal (daring / tatap muka / telepon), by Kode.
     *
     * Tiap baris menyatakan sendiri field apa yang WAJIB diisi untuknya. Dulu
     * 'DARING' dan 'LURING' tertulis mati di sekitar sepuluh tempat; bentuk
     * ketiga — telepon, yang justru paling lazim untuk penawaran gaji — menuntut
     * kesepuluhnya diubah serempak. Sekarang cukup satu baris data.
     */
    /**
     * Master status MCU (Fit / Fit with Note / Temporary Unfit / Unfit), by Kode.
     *
     * Dulu ketiganya array JavaScript di dalam Pelamar.vue. Status MCU
     * menentukan NASIB ORANG — Flag_Lolos menerjemahkannya langsung jadi
     * LULUS/GAGAL — dan aturan sebesar itu tidak boleh hanya hidup di berkas
     * layar, tempat ia tak bisa ditinjau maupun diaudit.
     */
    /**
     * Master jawaban kandidat atas penawaran (setuju / menolak / mundur).
     *
     * Dicatat TIM saat menandai kehadiran negosiasi — bukan ditekan kandidat di
     * portal; tombol itu sudah dicabut. Tiap jawaban membawa akibatnya sendiri,
     * termasuk hasil keputusan yang menutup lamaran bila memang menutup.
     */
    private static function masterJawabanPenawaran()
    {
        static $cache = null;

        return $cache ??= DB::table('N_WEB_CAREERS_Master_Jawaban_Penawaran')
            ->where('Flag_Aktif', 'Y')->orderBy('Urutan')->get()->keyBy('Kode');
    }

    private static function masterMcuStatus()
    {
        static $cache = null;

        return $cache ??= DB::table('N_WEB_CAREERS_Master_Mcu_Status')
            ->where('Flag_Aktif', 'Y')->orderBy('Urutan')->get()->keyBy('Kode');
    }

    private static function masterModeJadwal()
    {
        static $cache = null;

        return $cache ??= DB::table('N_WEB_CAREERS_Master_Mode_Jadwal')
            ->where('Flag_Aktif', 'Y')->orderBy('Urutan')->get()->keyBy('Kode');
    }

    /**
     * Bentuk jadwal yang BOLEH dipakai sebuah tipe — Master_Tipe_Tahap.Mode_Jadwal_Izin.
     *
     * Kode dipisah koma ("DARING,LURING"); kosong = semua bentuk aktif. FGD
     * alasannya: diskusi kelompok lewat telepon tidak masuk akal. Pilihan itu
     * disembunyikan di jendela jadwal DAN ditolak di server — layar bisa
     * dilewati lewat DevTools. Sebelum kolomnya ada, hasilnya null (tak dibatasi).
     *
     * @return string[]|null null = tidak dibatasi
     */
    private static function modeIzin(?object $tipe): ?array
    {
        $izin = collect(explode(',', strtoupper((string) ($tipe->Mode_Jadwal_Izin ?? ''))))
            ->map(fn ($k) => trim($k))
            ->filter()
            ->values()
            ->all();

        if ($izin) {
            return $izin;
        }

        // TANPA DAFTAR = semua bentuk aktif — KECUALI yang menuntut izin
        // eksplisit (Flag_Wajib_Izin). MCU mandiri tidak masuk akal untuk
        // wawancara; tanpa pengecualian ini ia ikut ditawarkan ke setiap tipe
        // yang daftarnya kosong. Selama tak ada bentuk seperti itu, hasilnya
        // tetap null (tak dibatasi) — perilaku lama tidak berubah.
        $semua = self::masterModeJadwal();
        $wajibIzin = $semua->filter(fn ($m) => ($m->Flag_Wajib_Izin ?? 'T') === 'Y');

        return $wajibIzin->isEmpty()
            ? null
            : $semua->reject(fn ($m) => ($m->Flag_Wajib_Izin ?? 'T') === 'Y')->keys()->values()->all();
    }

    /** Pesan galat bila bentuk jadwal di luar izin tipenya; null bila boleh. */
    private static function modeDitolak(?object $tipe, string $mode): ?string
    {
        $izin = self::modeIzin($tipe);
        if ($izin === null || in_array(strtoupper($mode), $izin, true)) {
            return null;
        }

        $nama = self::masterModeJadwal()->get($mode)->Nama ?? $mode;

        return ($tipe->Nama ?? 'Aktivitas ini')." tidak bisa dijadwalkan lewat {$nama}.";
    }

    /**
     * Field wajib menurut FLAG mode terpilih — pengganti `required_if` yang
     * dulu menuntut nama mode ditulis di dalam validator.
     *
     * @return string|null pesan galat, null bila lengkap
     */
    private static function periksaBidangJadwal(array $data, ?object $tipe = null): ?string
    {
        $m = self::masterModeJadwal()->get($data['mode'] ?? '');
        if (! $m) {
            return 'Bentuk pelaksanaan tidak dikenali.';
        }
        if (($m->Flag_Butuh_Tautan ?? 'T') === 'Y' && empty($data['link'])) {
            return "Bentuk \"{$m->Nama}\" wajib menyertakan tautan pertemuan.";
        }
        if (($m->Flag_Butuh_Lokasi ?? 'T') === 'Y' && empty($data['lokasiId'])) {
            return "Bentuk \"{$m->Nama}\" wajib memilih lokasi.";
        }
        if (($m->Flag_Butuh_Lokasi ?? 'T') === 'Y'
            && ($galat = self::periksaPeruntukanLokasi($data, $tipe))) {
            return $galat;
        }
        // Nomor tidak boleh diambil diam-diam dari profil: yang dijanjikan ke
        // kandidat harus yang benar-benar tercatat, bukan yang kebetulan ada.
        if (($m->Flag_Butuh_Kontak ?? 'T') === 'Y' && empty($data['kontak'])) {
            return "Bentuk \"{$m->Nama}\" wajib mencantumkan nomor yang akan dihubungi.";
        }
        // VENDOR: tempatnya diketik tim. Namanya wajib; tautan peta opsional,
        // tetapi bila diisi HARUS tautan Google Maps — ia dikirim apa adanya
        // lewat surel resmi, dan tautan sembarang di sana adalah celah penipuan.
        if (UndanganJadwal::butuhTempat($m)) {
            if (trim((string) ($data['lokasiNama'] ?? '')) === '') {
                return (($m->Label_Tempat ?? null) ?: 'Nama tempat').' wajib diisi.';
            }
            if (UndanganJadwal::normalkanMaps($data['mapsUrl'] ?? null) === false) {
                return 'Tautan peta harus tautan Google Maps (mis. https://maps.app.goo.gl/...) — salin lewat tombol "Bagikan" di Google Maps.';
            }
        }

        return null;
    }

    /**
     * LOKASI YANG DIPILIH HARUS COCOK DENGAN PERUNTUKAN TIPE AKTIVITASNYA.
     *
     * MCU hanya boleh ke rumah sakit. Layar memang sudah menyaring dropdown-nya,
     * tetapi layar bisa dilewati — dan akibat lolosnya bukan galat yang terlihat
     * melainkan undangan yang benar-benar terkirim: kandidat berangkat ke kantor
     * untuk pemeriksaan kesehatan, dan baru tahu di tempat bahwa tak ada yang
     * memeriksanya.
     *
     * Tipe TANPA peruntukan (phone screen, negosiasi) tidak dibatasi sama sekali
     * — membatasi sesuatu yang memang bisa di mana saja hanya membuat rekruter
     * buntu tanpa sebab.
     *
     * @return string|null pesan galat, null bila sah
     */
    /**
     * Nama kandidat dari jawaban formulir — null bila tidak ada.
     *
     * Aturannya dipusatkan di IdentitasKandidat supaya kartu worklist, drawer,
     * undangan, dan PDF biodata menyebut orang yang sama dengan nama yang sama.
     */
    private static function namaDariJawaban(?array $jawaban, ?string $snapshotJson = null): ?string
    {
        return \App\Support\Career\IdentitasKandidat::nama($jawaban ?: [], $snapshotJson);
    }

    /**
     * Nama resmi tiap lamaran, diambil dari FORMULIR — [Lamaran_Id => nama].
     *
     * Satu kueri untuk seluruh daftar, bukan satu per kartu. Pengisian dibaca
     * urut dari yang PALING AWAL: formulir lamaran diisi lebih dulu dan itulah
     * yang memuat identitas; formulir tahap lanjutan umumnya tidak menanyakan
     * nama lagi.
     *
     * `$pengisian`: hasil LamaranService::pengisianPerLamaran() yang sudah
     * dibaca pemanggil — papan memakainya juga untuk penyaring kampus.
     */
    private static function namaResmiPerLamaran(array $lamaranIds, ?\Illuminate\Support\Collection $pengisian = null): \Illuminate\Support\Collection
    {
        if (! $lamaranIds) {
            return collect();
        }

        // Snapshot skema memberi tahu field mana yang MENANYAKAN nama, tanpa
        // perlu kuncinya terdaftar di master lebih dulu.
        $adaSnapshot = \App\Support\Career\FormulirSchema::punyaKolomPengisianSnapshot();
        $adaVersi = $adaSnapshot && \App\Support\Career\Skema::adaKolom('N_WEB_CAREERS_Formulir_Pengisian', 'Master_Formulir_Versi_Id');

        // DUA LANGKAH. Jawaban dibaca TANPA snapshot skema lebih dulu — nama()
        // memang memeriksa kunci master sebelum snapshot, dan kebanyakan
        // kandidat selesai di situ. Snapshot (5–22 KB per pengisian) dulu ikut
        // terbaca untuk SETIAP pengisian setiap kandidat; papan 360 kandidat
        // menghabiskan ±0,65 detik hanya untuk itu.
        //
        // Pemuatnya bersama (berpotong, lihat LamaranService). Urutannya di
        // sana lama → baru menurut Waktu_Kirim; di sini yang berlaku urutan
        // Id — formulir PERTAMA yang menyebut nama, seperti sebelumnya.
        $perLamaran = ($pengisian ?? LamaranService::pengisianPerLamaran($lamaranIds))
            ->map(fn ($g) => collect($g)->sortBy(fn ($fp) => (int) $fp->Id_Formulir_Pengisian)->values());

        $hasil = [];
        $sisa = [];
        foreach ($perLamaran as $lid => $g) {
            foreach ($g as $fp) {
                if ($nama = \App\Support\Career\IdentitasKandidat::namaDariMaster($fp->jawaban)) {
                    $hasil[$lid] = $nama;

                    continue 2;
                }
            }
            $sisa[$lid] = $g;
        }

        // Langkah kedua — hanya kandidat yang belum ketemu namanya. Snapshot
        // dibaca SEKALI per versi formulir: isinya identik untuk seluruh
        // pengisian versi yang sama (skema versi terbit tidak berubah).
        $snapshot = collect();
        $wakilVersi = [];
        if ($sisa && $adaSnapshot) {
            $ambil = [];
            foreach ($sisa as $g) {
                foreach ($g as $fp) {
                    $v = $adaVersi ? ($fp->Master_Formulir_Versi_Id ?? null) : null;
                    if ($v) {
                        $wakilVersi[$v] = max($wakilVersi[$v] ?? 0, (int) $fp->Id_Formulir_Pengisian);
                    } else {
                        $ambil[] = (int) $fp->Id_Formulir_Pengisian;
                    }
                }
            }
            $ambil = array_values(array_unique(array_merge($ambil, array_values($wakilVersi))));
            $snapshot = $ambil
                ? \App\Support\Career\MetrikRekrutmen::potongIn(
                    fn () => DB::table('N_WEB_CAREERS_Formulir_Pengisian')->select(['Id_Formulir_Pengisian', 'Schema_Snapshot_Json']),
                    'Id_Formulir_Pengisian',
                    $ambil,
                )->pluck('Schema_Snapshot_Json', 'Id_Formulir_Pengisian')
                : collect();
        }

        foreach ($sisa as $lid => $g) {
            foreach ($g as $fp) {
                $v = $adaVersi ? ($fp->Master_Formulir_Versi_Id ?? null) : null;
                $nama = self::namaDariJawaban($fp->jawaban, $snapshot->get($v ? $wakilVersi[$v] : (int) $fp->Id_Formulir_Pengisian));
                if ($nama) {
                    $hasil[$lid] = $nama;

                    break;
                }
            }
        }

        return collect($hasil);
    }

    /**
     * TEMPAT sebuah jadwal — dari master ATAU yang diketik sendiri.
     *
     * Satu pintu untuk undangan email, portal kandidat, dan rapor admin. Dulu
     * masing-masing membaca kolomnya sendiri, dan begitu tempat "Lainnya"
     * ditambahkan, ketiganya akan menampilkan hal berbeda untuk jadwal yang
     * sama — yang paling merugikan justru undangan, karena ia sudah terkirim
     * sebelum siapa pun sempat melihat selisihnya.
     *
     * @param  object  $s  baris N_WEB_CAREERS_Lamaran_Tahap_Tes
     */
    public static function tempatJadwal(object $s, ?object $master = null): ?array
    {
        // VENDOR: tempat DIKETIK tim — nama vendor, alamat (opsional), catatan
        // cabang yang melayani, dan tautan Google Maps HANYA bila ditempel tim.
        // Petanya tidak pernah dikarang dari nama: vendor berjaringan punya
        // puluhan cabang, dan pin hasil tebakan tampil sama meyakinkannya
        // dengan pin yang benar.
        if (UndanganJadwal::butuhTempat(UndanganJadwal::mode($s->Jadwal_Mode ?? null))) {
            $lepas = MasterLokasiController::lokasiLepas($s->Jadwal_Lokasi_Nama ?? null, $s->Jadwal_Lokasi_Alamat ?? null);

            return $lepas ? [
                'vendor' => true,
                'mapsUrl' => ($s->Jadwal_Maps_Url ?? null) ?: null,
                'catatanHtml' => ($s->Jadwal_Lokasi_Html ?? null) ?: null,
            ] + $lepas : null;
        }

        if ($s->Jadwal_Lokasi_Id ?? null) {
            $id = (int) $s->Jadwal_Lokasi_Id;

            // SEKALI PER LOKASI PER PERMINTAAN. Rapor papan memanggil ini untuk
            // setiap aktivitas terjadwal — dulu dua kueri per aktivitas (N+1):
            // kolom FGD berisi ratusan kandidat tatap muka menembak ratusan
            // kueri untuk tiga-empat lokasi yang sama. Tidak diingat di proses
            // konsol (queue worker berumur panjang): undangan harus membaca
            // alamat terbaru, bukan yang diingat sejak worker dinyalakan.
            static $ingat = [];
            $boleh = ! $master && ! app()->runningInConsole();
            if ($boleh && array_key_exists($id, $ingat)) {
                return $ingat[$id];
            }

            $tempat = MasterLokasiController::bentukLokasi(
                $master ?: DB::table('N_WEB_CAREERS_Master_Lokasi')->where('Id_Master_Lokasi', $id)->first(),
                MasterLokasiController::petaPeruntukan([$id])->get($id, []),
            );

            return $boleh ? ($ingat[$id] = $tempat) : $tempat;
        }

        return MasterLokasiController::lokasiLepas(
            $s->Jadwal_Lokasi_Nama ?? null,
            $s->Jadwal_Lokasi_Alamat ?? null,
        );
    }

    private static function periksaPeruntukanLokasi(array $data, ?object $tipe): ?string
    {
        $butuh = $tipe->Lokasi_Peruntukan_Kode ?? null;
        $lepas = ($data['lokasiId'] ?? null) === MasterLokasiController::LAINNYA;

        // ── Tempat yang diketik sendiri ────────────────────────────────────
        if ($lepas) {
            $p = $butuh ? MasterLokasiController::masterPeruntukan()->get($butuh) : null;

            // Tanpa peruntukan, tak ada master yang menyatakan "Lainnya" boleh.
            // Menutupnya adalah pilihan yang aman: yang terlanjur dibuka tak
            // bisa ditarik, sedangkan yang tertutup cukup dibuka lewat master.
            if (! $p || ($p->Flag_Izinkan_Lainnya ?? 'T') !== 'Y') {
                return 'Tempat di luar daftar tidak diizinkan untuk aktivitas ini — pilih dari daftar lokasi.';
            }
            if (empty(trim((string) ($data['lokasiNama'] ?? '')))) {
                return ($p->Label_Nama_Lainnya ?: 'Nama tempat').' wajib diisi.';
            }
            if (($p->Flag_Wajib_Alamat_Lainnya ?? 'Y') === 'Y'
                && empty(trim((string) ($data['lokasiAlamat'] ?? '')))) {
                return ($p->Label_Alamat_Lainnya ?: 'Alamat').' wajib diisi — undangan tanpa alamat membuat kandidat tidak tahu harus datang ke mana.';
            }

            return null;
        }

        if (! $butuh) {
            return null;
        }

        $id = Hashids::decode($data['lokasiId'] ?? '')[0] ?? null;
        if (! $id) {
            return 'Lokasi tidak valid.';
        }

        $cocok = DB::table('N_WEB_CAREERS_Master_Lokasi_Peruntukan_Map')
            ->where('Id_Master_Lokasi', $id)
            ->where('Peruntukan_Kode', $butuh)
            ->exists();

        if (! $cocok) {
            $nama = DB::table('N_WEB_CAREERS_Master_Lokasi')->where('Id_Master_Lokasi', $id)->value('Nama') ?: 'Lokasi itu';
            $p = MasterLokasiController::masterPeruntukan()->get($butuh);

            return "\"{$nama}\" bukan ".mb_strtolower($p->Nama ?? $butuh)
                .'. '.($p->Label_Pilih ?? 'Pilih lokasi yang sesuai').'.';
        }

        return null;
    }

    /**
     * Rincian jadwal di luar kolom lamanya — satu bentuk untuk portal kandidat
     * DAN rapor worklist, supaya "lewat batas" di satu layar tidak berbunyi
     * lain di layar sebelahnya.
     *
     *   batasWaktu     waktunya RENTANG TANGGAL (mode ber-Flag_Batas_Waktu)
     *   batas          akhir rentang pemeriksaan, bila berrentang
     *   rentangTeks    "30 September – 07 Oktober 2026" — TETAP walau batas
     *                  unggahnya diperpanjang
     *   rentangPendek  "30 Sep – 07 Okt 2026" (baris ringkas worklist)
     *   batasUnggah    batas unggah hasil yang berlaku (mode yang kandidatnya
     *                  mengunggah sendiri): akhir rentang, atau tanggal
     *                  perpanjangan dari tim; null pada mode lain — tanpa ini,
     *                  tombol Perpanjang tidak muncul
     *   batasUnggahTeks, batasDiperpanjang  teksnya, dan apakah sudah dimundurkan
     *   modeNama       nama bentuknya dari master ("Vendor", "Mandiri")
     *   vendor         tempat diketik tim (nama vendor + catatan cabang)
     *   tempatKalimat  "tempat" MANDIRI: klinik/RS pilihan kandidat
     *   surat          surat pengantar: [{nama, ukuran, url}] (null bila tak ada);
     *                  url dirakit dari `$awalanSurat` + urutan — rute portal
     *                  dan rute admin berbeda
     *   suratLabel     nama suratnya ("Surat pengantar MCU")
     *   lewat          batasnya sudah lewat dan aktivitasnya belum ditutup
     *   catatanHtml    instruksi berformat yang diterima kandidat
     *   biaya          kalimat biaya yang dibaca kandidat (null bila disembunyikan
     *                  atau tipenya tanpa ketentuan biaya)
     *   tampilBiaya    informasi biaya ikut ke kandidat pada jadwal ini
     *   kalimatBiaya   kalimat biaya jadwal ini untuk disunting ulang (terisi
     *                  walau disembunyikan)
     */
    private static function jadwalTambahan(object $s, ?object $tipe, ?string $awalanSurat = null): array
    {
        $mode = UndanganJadwal::mode($s->Jadwal_Mode ?? null);
        $berbatas = UndanganJadwal::berbatasWaktu($mode);
        $vendor = UndanganJadwal::butuhTempat($mode);
        $terbuka = ($s->Flag_Selesai ?? 'N') !== 'Y' && empty($s->Jadwal_Hadir);
        $surat = [];
        foreach (SuratJadwal::daftar($s) as $urutan => $i) {
            $surat[] = ['nama' => $i['nama'], 'ukuran' => $i['ukuran'], 'url' => $awalanSurat ? $awalanSurat.'/'.$urutan : null];
        }
        // Batas yang berlaku: batas unggah (bisa diperpanjang) pada mode yang
        // kandidatnya mengunggah sendiri, akhir rentang pada mode lain.
        $batasUnggah = $berbatas && UndanganJadwal::unggahKandidat($mode) ? UndanganJadwal::batasUnggah($s) : null;
        $batasBerlaku = $batasUnggah ?? ($berbatas && ! empty($s->Jadwal_Selesai)
            ? \Illuminate\Support\Carbon::parse($s->Jadwal_Selesai)
            : null);

        return [
            'batasWaktu' => $berbatas,
            'batas' => $berbatas && ! empty($s->Jadwal_Selesai) ? (string) $s->Jadwal_Selesai : null,
            'batasTeks' => $berbatas ? UndanganJadwal::teksBatas($s->Jadwal_Selesai ?? null) : null,
            'batasUnggah' => $batasUnggah?->format('Y-m-d H:i:s'),
            'batasUnggahTeks' => $batasUnggah ? UndanganJadwal::teksBatas($batasUnggah) : null,
            'batasDiperpanjang' => $batasUnggah !== null && UndanganJadwal::batasDiperpanjang($s),
            'rentangTeks' => $berbatas ? UndanganJadwal::teksRentang($s->Jadwal_Mulai ?? null, $s->Jadwal_Selesai ?? null) : null,
            'rentangPendek' => $berbatas ? UndanganJadwal::rentangPendek($s->Jadwal_Mulai ?? null, $s->Jadwal_Selesai ?? null) : null,
            'modeNama' => $mode->Nama ?? $s->Jadwal_Mode,
            'vendor' => $vendor,
            'tempatKalimat' => $berbatas && ! $vendor ? ($mode->Kalimat_Undangan ?? null) : null,
            'surat' => $surat ?: null,
            'suratLabel' => $surat ? UndanganJadwal::labelSurat($mode) : null,
            'lewat' => $terbuka && $batasBerlaku !== null && now()->gt($batasBerlaku),
            'catatanHtml' => $s->Jadwal_Catatan_Html ?? null,
            // Kalimat biaya hanya bila admin menampilkannya untuk jadwal ini.
            'biaya' => UndanganJadwal::kalimatBiaya($s, $tipe),
            'tampilBiaya' => UndanganJadwal::tampilBiaya($s),
            'kalimatBiaya' => trim((string) ($s->Jadwal_Kalimat_Biaya ?? '')) ?: (trim((string) ($tipe->Kalimat_Biaya ?? '')) ?: null),
        ];
    }

    /**
     * [mulai, selesai, galat] sebuah jadwal — menurut FLAG mode-nya.
     *
     * JANJI TEMU (bawaan): mulai wajib, selesai opsional dan harus sesudahnya.
     *
     * BERRENTANG TANGGAL (Flag_Batas_Waktu, MCU vendor / mandiri): yang
     * ditanyakan RENTANGNYA — tanggal pertama dan terakhir pemeriksaan, keduanya
     * wajib. Tidak ada jam janji temu: awal dibaca pukul 00.00 hari pertama,
     * akhir pukul 23.59 hari terakhir ("sampai Senin" = sepanjang hari Senin).
     * Akhir rentang itulah BATASNYA — batas unggah MANDIRI, dan patokan
     * pengingat.
     *
     * Dipakai jalur satuan DAN massal, supaya keduanya menegakkan aturan yang
     * sama persis.
     *
     * @param  ?string  $mulaiLama  Jadwal yang sudah ada (Atur Jadwal satuan). Waktu
     *                              mulai yang TIDAK diubah boleh tetap di masa lalu —
     *                              jadwal yang sedang berjalan masih bisa disunting
     *                              (mis. membetulkan tautan). Jadwal massal: null.
     * @return array{0: ?string, 1: ?string, 2: ?string}
     */
    private static function waktuJadwal(array $data, ?object $mode, ?string $mulaiLama = null): array
    {
        if (UndanganJadwal::berbatasWaktu($mode)) {
            if (empty($data['mulai']) || empty($data['selesai'])) {
                return [null, null, 'Tentukan rentang tanggalnya — tanggal pertama dan terakhir pemeriksaan.'];
            }

            $awal = UndanganJadwal::awalHari((string) $data['mulai']);
            $batas = UndanganJadwal::akhirHari((string) $data['selesai']);
            if ($batas->lt($awal)) {
                return [null, null, 'Tanggal terakhir tidak boleh sebelum tanggal pertama.'];
            }
            if ($batas->lte(now())) {
                return [null, null, 'Rentang tanggalnya sudah lewat — tanggal terakhir harus hari ini atau sesudahnya.'];
            }
            // HARI YANG SUDAH LEWAT tidak bisa dipilih (masukan user 2 Okt 2026) —
            // sama dengan kalendernya (sebelumHariIni di Pelamar.vue).
            if ($awal->lt(now()->startOfDay()) && ! self::waktuTetap($mulaiLama, $awal, 'Y-m-d')) {
                return [null, null, 'Tanggal pertama sudah lewat — pilih hari ini atau sesudahnya.'];
            }

            return [$awal->format('Y-m-d H:i:s'), $batas->format('Y-m-d H:i:s'), null];
        }

        if (empty($data['mulai'])) {
            return [null, null, 'Waktu mulai wajib diisi.'];
        }

        // WAKTU MULAI YANG SUDAH LEWAT tidak bisa dipilih (masukan user 2 Okt
        // 2026). Ditolak di sini juga, bukan hanya di kalendernya: layar basi &
        // DevTools tetap bisa mengirimnya. Sama dengan jadwalMulaiLewat di layar.
        $mulaiBaru = \Illuminate\Support\Carbon::parse($data['mulai']);
        if ($mulaiBaru->lt(now()->startOfMinute()) && ! self::waktuTetap($mulaiLama, $mulaiBaru, 'Y-m-d H:i')) {
            return [null, null, 'Waktu mulai itu sudah lewat — pilih tanggal & jam yang akan datang.'];
        }

        if (! empty($data['selesai'])
            && \Illuminate\Support\Carbon::parse($data['selesai'])->lte(\Illuminate\Support\Carbon::parse($data['mulai']))) {
            return [null, null, 'Waktu selesai harus setelah waktu mulai.'];
        }

        return [(string) $data['mulai'], ($data['selesai'] ?? null) ?: null, null];
    }

    /** Waktu lama & baru sama pada ketelitian `$format` (menit / hari). */
    private static function waktuTetap(?string $lama, \Illuminate\Support\Carbon $baru, string $format): bool
    {
        if (! $lama) {
            return false;
        }
        try {
            return \Illuminate\Support\Carbon::parse($lama)->format($format) === $baru->format($format);
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function terapkanJadwal(int $subTesId, array $data, string $mulai, ?string $selesai): void
    {
        $mode = self::masterModeJadwal()->get($data['mode']);
        $now = now();
        $nama = session('career_auth.nama');
        $pakaiLokasi = ($mode->Flag_Butuh_Lokasi ?? 'T') === 'Y';
        // Tempat di luar master — sudah divalidasi di periksaPeruntukanLokasi().
        $lepas = $pakaiLokasi && ($data['lokasiId'] ?? null) === MasterLokasiController::LAINNYA;
        // VENDOR: nama & alamat vendor memakai kolom tempat-diketik yang sama.
        $vendor = UndanganJadwal::butuhTempat($mode);

        DB::table('N_WEB_CAREERS_Lamaran_Tahap_Tes')
            ->where('Id_Lamaran_Tahap_Tes', $subTesId)
            ->update([
                'Jadwal_Mode' => $data['mode'],
                'Jadwal_Mulai' => $mulai,
                'Jadwal_Selesai' => $selesai,
                // Kolom yang tidak dipakai mode terpilih DIKOSONGKAN, bukan
                // dibiarkan berisi nilai lama — sisa tautan pada jadwal luring
                // membuat kandidat mengira wawancaranya tetap daring.
                // Kolom mana yang terisi ditentukan FLAG MODE-nya, bukan
                // perbandingan dengan nama mode. Bentuk jadwal baru yang
                // ditambahkan lewat master langsung ikut aturan ini tanpa satu
                // baris pun disentuh di sini.
                'Jadwal_Link' => ($mode->Flag_Butuh_Tautan ?? 'T') === 'Y' ? ($data['link'] ?? null) : null,
                'Jadwal_Lokasi' => $pakaiLokasi ? ($data['lokasi'] ?? null) : null,
                'Jadwal_Lokasi_Id' => ($pakaiLokasi && ! $lepas && ! empty($data['lokasiId']))
                    ? (Hashids::decode($data['lokasiId'])[0] ?? null)
                    : null,
                // TEMPAT YANG DIKETIK SENDIRI — kolomnya terpisah dari patokan.
                // Keduanya dikosongkan saat lokasi terdaftar yang dipilih,
                // supaya sisa isian percobaan sebelumnya tidak ikut terbaca
                // sebagai tempat kedua di undangan yang sama.
                'Jadwal_Lokasi_Nama' => $lepas || $vendor ? trim((string) ($data['lokasiNama'] ?? '')) : null,
                'Jadwal_Lokasi_Alamat' => $lepas || $vendor ? (trim((string) ($data['lokasiAlamat'] ?? '')) ?: null) : null,
                // Nomor yang akan dihubungi — bagian dari JANJINYA, bukan
                // salinan profil. Lihat penjelasan panjang di .sql-nya.
                'Jadwal_Kontak' => ($mode->Flag_Butuh_Kontak ?? 'T') === 'Y' ? ($data['kontak'] ?? null) : null,
                // Teks polos TURUNAN instruksi berformat di bawahnya (lihat
                // UndanganJadwal::saringInstruksi) — dipakai surel & layar lama.
                'Jadwal_Catatan' => $data['catatan'] ?? null,
                'Jadwal_At' => $now,
                'Jadwal_By' => $nama,
                'Jadwal_By_Id' => session('career_auth.id'),
                'Status' => 'DIJADWALKAN',
                'Updated_At' => $now,
                'Updated_By' => $nama,
            ] + (UndanganJadwal::siapInstruksi()
                // SALINAN instruksi yang diterima kandidat. Selalu ditulis —
                // termasuk null — supaya instruksi berformat dari jadwal lama
                // tidak tertinggal di samping teks polos yang baru.
                ? ['Jadwal_Catatan_Html' => $data['catatanHtml'] ?? null]
                : []) + self::kolomVendorSurat($data, $mode) + self::kolomBiaya($subTesId, $data)
                + self::kolomBatasUnggah($subTesId, $mode, $selesai)
                // VERSI JADWAL — naik di SETIAP simpan, tidak pernah direset.
                // Jawaban konfirmasi dan tautan di surel menempel pada versi:
                // tautan lama tidak bisa mengonfirmasi jam yang sudah diganti.
                + (\App\Support\Career\Skema::adaKolom('N_WEB_CAREERS_Lamaran_Tahap_Tes', 'Jadwal_Versi')
                    ? ['Jadwal_Versi' => DB::raw('ISNULL(Jadwal_Versi, 0) + 1')]
                    : []));
    }

    /**
     * Perpanjangan BATAS UNGGAH pada jadwal yang disimpan ulang.
     *
     * Dipertahankan hanya bila modenya masih diunggah kandidat dan tanggalnya
     * masih sesudah akhir rentang yang baru: perpanjangan yang sudah diberikan
     * tidak dicabut diam-diam oleh suntingan lain (nama vendor, instruksi,
     * surat). Rentang baru yang lebih lambat menggantikannya, jadi kolomnya
     * dikosongkan — label "diperpanjang" tidak boleh tertinggal tanpa arti.
     */
    private static function kolomBatasUnggah(int $subTesId, ?object $mode, ?string $selesai): array
    {
        if (! UndanganJadwal::siapBatasUnggah()) {
            return [];
        }

        $lama = DB::table('N_WEB_CAREERS_Lamaran_Tahap_Tes')->where('Id_Lamaran_Tahap_Tes', $subTesId)
            ->first(['Jadwal_Mulai', 'Jadwal_Batas_Unggah']);
        $tetap = ! empty($lama->Jadwal_Mulai) && ! empty($lama->Jadwal_Batas_Unggah) && $selesai
            && UndanganJadwal::berbatasWaktu($mode) && UndanganJadwal::unggahKandidat($mode)
            && \Illuminate\Support\Carbon::parse($lama->Jadwal_Batas_Unggah)->gt(\Illuminate\Support\Carbon::parse($selesai));

        return ['Jadwal_Batas_Unggah' => $tetap ? $lama->Jadwal_Batas_Unggah : null];
    }

    /**
     * INFORMASI BIAYA pada jadwal ini — ditampilkan atau tidak, dan kalimatnya.
     *
     * Dibekukan saat jadwal disimpan: yang diterima kandidat tidak berubah bila
     * setelan alurnya diganti besok. Tanpa pilihan dari layar (klien lama,
     * jalur lain), bawaan Master Alur yang berlaku. Kalimat kosong = kalimat
     * alur, lalu kalimat tipe — dan yang tersimpan selalu kalimat JADINYA,
     * supaya jejaknya menyebut apa yang benar-benar dibaca kandidat.
     */
    private static function kolomBiaya(int $subTesId, array $data): array
    {
        if (! UndanganJadwal::siapTampilBiaya()) {
            return [];
        }

        $alur = UndanganJadwal::setelanAlur($subTesId);
        $tampil = array_key_exists('tampilBiaya', $data) && $data['tampilBiaya'] !== null
            ? filter_var($data['tampilBiaya'], FILTER_VALIDATE_BOOLEAN)
            : $alur['tampilBiaya'];
        $kolom = ['Jadwal_Tampil_Biaya' => $tampil ? 'Y' : 'T'];

        if (\App\Support\Career\Skema::adaKolom('N_WEB_CAREERS_Lamaran_Tahap_Tes', 'Jadwal_Kalimat_Biaya')) {
            $kalimat = trim((string) ($data['kalimatBiaya'] ?? '')) ?: ($alur['kalimatBiaya'] ?? null)
                ?: (trim((string) (self::tipeAktivitas($subTesId)->Kalimat_Biaya ?? '')) ?: null);
            $kolom['Jadwal_Kalimat_Biaya'] = $kalimat ? mb_substr($kalimat, 0, 1000) : null;
        }

        return $kolom;
    }

    /** Definisi tipe sebuah aktivitas — tipe aktivitasnya, jatuh ke tipe tahapnya. */
    private static function tipeAktivitas(int $subTesId): ?object
    {
        $r = DB::table('N_WEB_CAREERS_Lamaran_Tahap_Tes as t')
            ->join('N_WEB_CAREERS_Lamaran_Tahap as h', 'h.Id_Lamaran_Tahap', '=', 't.Lamaran_Tahap_Id')
            ->where('t.Id_Lamaran_Tahap_Tes', $subTesId)
            ->first(['t.Tipe_Tahap_Kode as Sub', 'h.Tipe_Tahap_Kode as Induk']);

        return $r ? (self::masterTipeTahap()[$r->Sub ?: $r->Induk] ?? null) : null;
    }

    /**
     * Daftar surat pengantar FINAL sebuah jadwal — [daftar, galat].
     *
     * Surat lama (pada jadwal yang SUDAH terbit) dipertahankan sesuai
     * `suratTetap` (urutan yang dipertahankan; kunci tak dikirim = semua), lalu
     * yang baru diunggah (`suratRef`) menyusul di belakangnya. Jadwal yang BARU
     * terbit tidak "memakai lagi" apa pun — sisa surat percobaan lama bukan
     * surat untuk jadwal ini. Batasnya satu: JUMLAH ukuran seluruhnya.
     *
     * @return array{0: ?array, 1: ?string}
     */
    private static function susunSurat(object $sub, array $data, ?object $mode): array
    {
        $lama = ! empty($sub->Jadwal_Mulai) ? SuratJadwal::daftar($sub) : [];
        if (array_key_exists('suratTetap', $data) && is_array($data['suratTetap'])) {
            $pilih = array_flip(array_map('intval', $data['suratTetap']));
            $lama = array_values(array_filter($lama, fn ($i, $k) => isset($pilih[$k]), ARRAY_FILTER_USE_BOTH));
        }

        $baru = [];
        foreach ((array) ($data['suratRef'] ?? []) as $ref) {
            $x = SuratJadwal::baca(is_string($ref) ? $ref : null);
            if (! $x) {
                return [null, 'Unggahan surat pengantar sudah kedaluwarsa atau tidak sah — unggah ulang suratnya.'];
            }
            $baru[] = $x;
        }

        $semua = array_merge($lama, $baru);
        if (! $semua) {
            return [null, UndanganJadwal::labelSurat($mode).' wajib dilampirkan.'];
        }

        return ($galat = SuratJadwal::galatBatas($semua)) ? [null, $galat] : [$semua, null];
    }

    /**
     * Kolom VENDOR & SURAT PENGANTAR sebuah jadwal (bila kolomnya sudah ada).
     *
     * Sama seperti kolom lain di terapkanJadwal(): yang tidak dipakai mode
     * terpilih DIKOSONGKAN — catatan cabang vendor yang tertinggal pada jadwal
     * mandiri akan terbaca sebagai tempat yang harus didatangi. Daftar surat
     * FINAL disusun pemanggil (susunSurat / gelung massal) — tanpa daftar itu,
     * surat yang ada dibiarkan.
     */
    private static function kolomVendorSurat(array $data, ?object $mode): array
    {
        if (! UndanganJadwal::siapVendor()) {
            return [];
        }

        $vendor = UndanganJadwal::butuhTempat($mode);
        $kolom = [
            'Jadwal_Lokasi_Html' => $vendor ? ($data['lokasiHtml'] ?? null) : null,
            'Jadwal_Maps_Url' => $vendor ? (UndanganJadwal::normalkanMaps($data['mapsUrl'] ?? null) ?: null) : null,
        ];

        if (! UndanganJadwal::butuhSurat($mode)) {
            return $kolom + ['Jadwal_Surat_Json' => null];
        }

        return isset($data['_surat']) && is_array($data['_surat'])
            ? $kolom + ['Jadwal_Surat_Json' => SuratJadwal::json($data['_surat'])]
            : $kolom;
    }

    /**
     * POST /api/v1/karir/lamaran/sub-tes/jadwal-massal — jadwalkan BANYAK
     * kandidat sekaligus.
     *
     * KENAPA INI WAJIB ADA
     * Menjadwalkan wawancara satu per satu masih masuk akal untuk lima orang.
     * Untuk seratus, ia bukan sekadar lambat — ia berbahaya: seratus jendela
     * yang dibuka berturut-turut adalah seratus kesempatan salah ketik tanggal,
     * salah pilih lokasi, atau terlewat satu orang tanpa ada yang menyadarinya.
     * Kesalahan itu baru ketahuan saat kandidat datang di hari yang salah.
     *
     * DUA POLA WAKTU, karena keduanya nyata di lapangan:
     *   SERENTAK   semua di jam yang sama — FGD, tes tertulis massal, briefing.
     *   BERGILIR   satu per satu dengan durasi & jeda tetap — wawancara panel.
     *              Kandidat ke-N mulai di `mulai + N × (durasi + jeda)`.
     *
     * TIDAK ADA "SEBAGIAN GAGAL DIAM-DIAM". Tiap kandidat dilaporkan sendiri:
     * yang berhasil dan yang ditolak berikut alasannya. Penjadwalan massal yang
     * hanya menjawab "berhasil" untuk 97 dari 100 orang adalah cara terbaik
     * kehilangan tiga orang tanpa jejak.
     */
    public function jadwalMassal(Request $request)
    {
        $data = $request->validate([
            'subTesIds' => 'required|array|min:1|max:300',
            'subTesIds.*' => 'required|string|max:64',
            // Daftar mode dari MASTER — bentuk jadwal baru cukup satu baris data.
            'mode' => ['required', Rule::in(self::masterModeJadwal()->keys()->all())],
            // Pola jam tidak berarti apa-apa pada mode berbatas waktu — semua
            // kandidat mendapat batas yang sama. Karena itu tidak diwajibkan di
            // sini; waktuJadwal() yang menentukan apa yang wajib.
            'pola' => 'nullable|in:SERENTAK,BERGILIR',
            'mulai' => 'nullable|date',
            'selesai' => 'nullable|date',
            // Hanya dipakai pola BERGILIR. Batas atas menjaga dari salah ketik
            // yang melempar sesi terakhir ke tahun depan.
            'durasiMenit' => 'nullable|integer|min:5|max:480',
            'jedaMenit' => 'nullable|integer|min:0|max:240',
            // `required_if` sengaja TIDAK dipakai lagi: ia menuntut nama mode
            // ditulis di sini, dan itu persis yang membuat mode baru harus
            // menyunting validator. Syaratnya ditegakkan setelah ini, dari flag.
            'link' => 'nullable|url|max:500',
            'kontak' => 'nullable|string|max:40',
            'lokasiId' => 'nullable|string|max:64',
            'lokasi' => 'nullable|string|max:300',
            'lokasiNama' => 'nullable|string|max:200',
            'lokasiAlamat' => 'nullable|string|max:500',
            'mapsUrl' => 'nullable|string|max:500',
            'lokasiHtml' => 'nullable|string|max:30000',
            // SATU set surat untuk seluruh sasaran (surat kolektif). Kosong =
            // tiap kandidat memakai surat yang sudah melekat pada jadwalnya.
            'suratRef' => 'nullable|array|max:'.SuratJadwal::MAKS_BERKAS,
            'suratRef.*' => 'string|max:4000',
            'tampilBiaya' => 'nullable|boolean',
            'kalimatBiaya' => 'nullable|string|max:1000',
            'catatan' => 'nullable|string',
            'catatanHtml' => 'nullable|string|max:30000',
            // Dipakai untuk kandidat yang SUDAH punya jadwal — lihat gerbang di
            // dalam gelung.
            'alasan' => 'nullable|string|max:500',
        ], [
            'link.required_if' => 'Tautan pertemuan wajib diisi untuk kegiatan daring.',
            'lokasiId.required_if' => 'Pilih lokasi untuk kegiatan tatap muka.',
        ]);

        $modeDef = self::masterModeJadwal()->get($data['mode']);
        $berbatas = UndanganJadwal::berbatasWaktu($modeDef);

        // Waktu dihitung SEKALI dengan aturan yang sama seperti jalur satuan.
        // Pola bergilir menggeser jam per kandidat di dalam gelung; batas waktu
        // sama untuk semua.
        [$mulaiDasar, $selesaiBatas, $galatWaktu] = self::waktuJadwal($data, $modeDef);
        if ($galatWaktu) {
            return ResponseHelper::error($galatWaktu, 422);
        }

        $bergilir = ! $berbatas && ($data['pola'] ?? 'SERENTAK') === 'BERGILIR';
        $durasi = (int) ($data['durasiMenit'] ?? 30);
        $jeda = (int) ($data['jedaMenit'] ?? 0);
        $mulaiAwal = \Illuminate\Support\Carbon::parse($mulaiDasar);

        // Instruksi disaring SEKALI — isinya sama untuk seluruh kandidat.
        [$data['catatanHtml'], $data['catatan']] = UndanganJadwal::saringInstruksi($data['catatanHtml'] ?? null, $data['catatan'] ?? null);
        $data['lokasiHtml'] = CatatanEksternal::saring($data['lokasiHtml'] ?? null);
        $alasanMassal = trim((string) ($data['alasan'] ?? ''));

        // Surat kolektif dibaca SEKALI; kandidat tanpa surat baru maupun lama
        // dilewati di dalam gelung (bukan menggagalkan seluruh kiriman).
        $butuhSurat = UndanganJadwal::butuhSurat($modeDef);
        $suratMassal = null;
        if ($butuhSurat && ! empty($data['suratRef'])) {
            [$suratMassal, $galatSurat] = self::susunSurat((object) ['Jadwal_Mulai' => null], ['suratRef' => $data['suratRef']], $modeDef);
            if ($galatSurat) {
                return ResponseHelper::error($galatSurat, 422);
            }
        }

        $tipeSemua = self::masterTipeTahap();

        // ══ BATAS SEKALI KIRIM — DIPERIKSA SEBELUM SATU BARIS PUN DITULIS ══
        //
        // Tiap penjadwalan menerbitkan satu undangan email ke kandidat, dan
        // puluhan email serempak dari satu alamat pengirim adalah pola yang
        // dibaca penyedia surat sebagai pengiriman massal mendadak — hukumannya
        // penangguhan yang ikut mematikan email hasil seleksi & setel ulang
        // kata sandi, bukan cuma gelombang ini. Lihat self::BATAS_PUTUS_MASSAL.
        //
        // DI MUKA, bukan di tengah gelung: berhenti di tengah meninggalkan
        // sebagian kandidat sudah berjadwal dan sudah diundang, sisanya tidak —
        // keadaan yang harus dibereskan tangan satu per satu.
        //
        // YANG PRIVAT DIKECUALIKAN. Tipe berjadwal privat (lihat JadwalPrivat)
        // tidak mengirim satu undangan pun, jadi batas laju email tidak
        // menjaga apa-apa di sana — dan menahannya berarti mengunci penjadwalan
        // internal tanpa sebab. Privat-tidaknya ditentukan tipe tahap, dan satu
        // kiriman selalu satu nama aktivitas, jadi cukup diperiksa sekali.
        if (count($data['subTesIds']) > self::BATAS_PUTUS_MASSAL) {
            // Tipe diambil dari sub-tesnya, JATUH KE TAHAP INDUK bila kosong —
            // pola yang sama dipakai di seluruh berkas ini (lihat baris ~683).
            // Tanpa jatuhan itu, aktivitas yang tipenya diwarisi induk terbaca
            // sebagai tipe kosong, yang bukan privat — batasnya lalu berlaku
            // untuk penjadwalan internal yang seharusnya bebas.
            $kodeTipe = DB::table('N_WEB_CAREERS_Lamaran_Tahap_Tes as t')
                ->join('N_WEB_CAREERS_Lamaran_Tahap as h', 'h.Id_Lamaran_Tahap', '=', 't.Lamaran_Tahap_Id')
                ->whereIn('t.Id_Lamaran_Tahap_Tes', collect($data['subTesIds'])
                    ->map(fn ($h) => Hashids::decode($h)[0] ?? null)->filter()->all())
                ->get(['t.Tipe_Tahap_Kode as Sub', 'h.Tipe_Tahap_Kode as Induk'])
                ->map(fn ($x) => $x->Sub ?: $x->Induk)
                ->unique();

            // Semua privat → tidak ada email sama sekali → tidak dibatasi.
            // Campuran dianggap TIDAK privat: bila satu saja mengirim undangan,
            // batasnya harus berlaku.
            $semuaPrivat = $kodeTipe->isNotEmpty()
                && $kodeTipe->every(fn ($k) => JadwalPrivat::untuk($k));

            if (! $semuaPrivat) {
                return ResponseHelper::error(
                    'Maksimal '.self::BATAS_PUTUS_MASSAL.' kandidat sekali kirim — dipilih '.count($data['subTesIds'])
                    .'. Tiap undangan adalah satu email; mengirimnya serempak membuat penyedia email menangguhkan '
                    .'alamat pengirim kami. Jadwalkan '.self::BATAS_PUTUS_MASSAL.' orang dulu, lalu ulangi untuk sisanya.',
                    422,
                );
            }
        }

        $berhasil = [];
        $gagal = [];
        $urutanSesi = 0;

        foreach ($data['subTesIds'] as $hash) {
            $id = Hashids::decode($hash)[0] ?? null;

            $sub = $id ? DB::table('N_WEB_CAREERS_Lamaran_Tahap_Tes as t')
                ->join('N_WEB_CAREERS_Lamaran_Tahap as h', 'h.Id_Lamaran_Tahap', '=', 't.Lamaran_Tahap_Id')
                ->join('N_WEB_CAREERS_Lamaran as l', 'l.Id_Lamaran', '=', 'h.Lamaran_Id')
                ->leftJoin('N_WEB_CAREERS_Users as u', 'u.Id_Users', '=', 'l.Id_Users')
                ->where('t.Id_Lamaran_Tahap_Tes', $id)
                ->select('t.*', 'h.Lamaran_Id', 'h.Tipe_Tahap_Kode as TahapTipe', 'l.Status as StatusLamaran', 'u.Nama as Pelamar')
                ->first() : null;

            $nama = $sub->Pelamar ?? ('#'.$hash);

            // ── Gerbang per kandidat ────────────────────────────────────────
            // Sengaja diperiksa ulang di server untuk SETIAP baris, bukan
            // sekali di awal: daftar yang dikirim layar bisa sudah basi —
            // rekan sebelah mungkin baru saja menggugurkan salah satunya.
            $tolak = match (true) {
                ! $sub => 'Aktivitas tidak ditemukan.',
                $sub->StatusLamaran !== 'BERJALAN' => 'Lamaran sudah tidak berjalan.',
                $sub->Flag_Selesai === 'Y' => 'Aktivitas sudah selesai.',
                default => self::kunciUrutan($sub),
            };

            if ($tolak) {
                $gagal[] = ['nama' => $nama, 'alasan' => $tolak];

                continue;
            }

            // MCU & tanda tangan kontrak mustahil daring — dijaga sama seperti
            // pada penjadwalan satuan.
            $tipeSub = $tipeSemua[$sub->Tipe_Tahap_Kode ?? ''] ?? null;
            // Peruntukan lokasi ikut diperiksa — kalau tidak, penjadwalan MASSAL
            // jadi pintu belakang yang mengirim seratus orang MCU ke kantor
            // sekaligus, persis kesalahan yang dijaga di jalur satuan.
            if ($galat = self::periksaBidangJadwal($data, $tipeSub)) {
                return ResponseHelper::error($galat, 422);
            }

            if (($tipeSub->Flag_Wajib_Luring ?? 'T') === 'Y'
                && (self::masterModeJadwal()->get($data['mode'])->Flag_Luring ?? 'T') !== 'Y') {
                $gagal[] = ['nama' => $nama, 'alasan' => ($tipeSub->Nama ?? 'Aktivitas ini').' hanya bisa LURING.'];

                continue;
            }

            if ($galat = self::modeDitolak($tipeSub, $data['mode'])) {
                $gagal[] = ['nama' => $nama, 'alasan' => $galat];

                continue;
            }

            // Jadwal yang SUDAH dikirim hanya boleh diubah beserta alasannya —
            // aturan yang sama dengan jalur satuan. Yang belum pernah
            // dijadwalkan tidak menuntut apa pun.
            if (! empty($sub->Jadwal_Mulai) && JejakJadwal::siap() && mb_strlen($alasanMassal) < JejakJadwal::ALASAN_MIN) {
                $gagal[] = ['nama' => $nama, 'alasan' => 'Sudah punya jadwal — isi alasan perubahan untuk menggantinya.'];

                continue;
            }

            // Surat kolektif menggantikan surat masing-masing; tanpanya tiap
            // kandidat memakai suratnya sendiri — yang belum punya dilewati.
            if ($butuhSurat) {
                $data['_surat'] = $suratMassal ?? (! empty($sub->Jadwal_Mulai) ? SuratJadwal::daftar($sub) : []);
                if (! $data['_surat']) {
                    $gagal[] = ['nama' => $nama, 'alasan' => UndanganJadwal::labelSurat($modeDef).' belum ada — unggah suratnya di jendela ini.'];

                    continue;
                }
            }

            // Nomor sesi dihitung dari yang BENAR-BENAR dijadwalkan, bukan dari
            // posisi di daftar kiriman. Kalau dari posisi, satu kandidat yang
            // ditolak akan meninggalkan lubang jam kosong di tengah rangkaian.
            $mulai = $bergilir
                ? $mulaiAwal->copy()->addMinutes($urutanSesi * ($durasi + $jeda))
                : $mulaiAwal->copy();
            $selesai = $bergilir
                ? $mulai->copy()->addMinutes($durasi)->format('Y-m-d H:i:s')
                : $selesaiBatas;

            $aksi = JejakJadwal::aksi($sub);

            // Batas konfirmasi tiap kandidat = waktu mulai SESINYA SENDIRI
            // (pola bergilir: tiap orang beda jam, tiap orang beda batas).
            DB::transaction(function () use ($sub, $data, $mulai, $selesai) {
                $this->terapkanJadwal(
                    (int) $sub->Id_Lamaran_Tahap_Tes,
                    $data,
                    $mulai->format('Y-m-d H:i:s'),
                    $selesai,
                );
                KonfirmasiJadwal::terbitkan(
                    (int) $sub->Id_Lamaran_Tahap_Tes,
                    $sub,
                    (string) session('career_auth.nama', 'ADMIN'),
                    session('career_auth.id') ? (int) session('career_auth.id') : null,
                );
            });

            $undangan = $this->kirimUndanganJadwal((int) $sub->Id_Lamaran_Tahap_Tes, (int) $sub->Lamaran_Id);

            JejakJadwal::catat((int) $sub->Id_Lamaran_Tahap_Tes, $aksi, $alasanMassal ?: null, $undangan);

            $berhasil[] = [
                'nama' => $nama,
                'mulai' => $mulai->format('Y-m-d H:i'),
                'emailTerkirim' => $undangan === 'terkirim',
                'undangan' => $undangan,
            ];
            $urutanSesi++;
        }

        Log::channel('web_career')->info(
            '[JADWAL-MASSAL] '.count($berhasil).' berhasil, '.count($gagal).' gagal — oleh '.session('career_auth.nama', 'ADMIN')
        );

        // Kalimatnya mengikuti apa yang BENAR-BENAR terjadi. "Diundang lewat
        // email" untuk aktivitas privat adalah laporan yang keliru, dan admin
        // yang mempercayainya akan menunggu balasan kandidat yang tak pernah
        // diberi tahu.
        $adaPrivat = collect($berhasil)->contains('undangan', 'privat');
        $kabar = $adaPrivat ? 'dijadwalkan (internal — kandidat tidak diberi tahu)' : 'dijadwalkan dan diundang lewat email';

        return ResponseHelper::success(
            ['berhasil' => $berhasil, 'gagal' => $gagal],
            count($gagal)
                ? count($berhasil).' kandidat dijadwalkan, '.count($gagal).' dilewati — periksa rinciannya.'
                : count($berhasil).' kandidat '.$kabar.'.',
        );
    }

    /**
     * Kirim undangan jadwal ke kandidat.
     *
     * Kegagalan email TIDAK membatalkan jadwalnya: jadwal sudah tersimpan dan
     * terlihat di portal kandidat, jadi menggagalkan seluruh operasi hanya
     * karena SMTP sedang bermasalah justru merugikan.
     *
     * Mengembalikan TIGA keadaan, bukan ya/tidak: 'terkirim', 'gagal', dan
     * 'privat'. "Tidak dikirim karena memang tidak boleh" harus terbaca berbeda
     * dari "gagal dikirim" — kalau disamakan, layar memberi tahu admin bahwa
     * emailnya bermasalah dan menyuruhnya memeriksa log setiap kali ia
     * menjadwalkan negosiasi, untuk sesuatu yang berjalan persis sebagaimana
     * mestinya.
     *
     * @return 'terkirim'|'gagal'|'privat'
     */
    private function kirimUndanganJadwal(int $subTesId, int $lamaranId, array $bunyi = []): string
    {
        try {
            // Isi suratnya disusun UndanganJadwal — sumber yang sama dengan
            // pengingat menjelang batas (karir:pengingat-jadwal), supaya
            // keduanya mustahil menyebut tempat, batas, atau biaya yang berbeda.
            $muatan = UndanganJadwal::muatan($subTesId);
            $sub = $muatan['sub'] ?? null;

            if (! $sub || ! $sub->Email) {
                return 'gagal';
            }

            // ══ JADWAL YANG TIDAK DIUMUMKAN ══
            //
            // HANYA tipe yang ditandai privat di master. Negosiasi dijadwalkan
            // tim untuk dirinya sendiri: undangan "Negosiasi Penawaran, Selasa
            // 10.00" memberitahu kandidat bahwa angkanya sedang dirundingkan,
            // dan sejak saat itu tiap hari tanpa kabar terbaca sebagai penolakan
            // yang tertunda. Yang perlu ia terima adalah HASILNYA.
            //
            // ⚠ SENGAJA TIDAK memakai Tampil_Kandidat sebagai syarat.
            //
            // Menggodanya jelas: aktivitas yang disembunyikan dari portal tapi
            // tetap mengirim undangan terlihat seperti kebocoran yang perlu
            // ditutup. Tapi menutupnya justru membuat keadaannya lebih buruk.
            // Aktivitas yang disembunyikan DAN dijadwalkan hampir selalu salah
            // setelan, bukan rahasia — MCU yang tersembunyi, misalnya. Kandidat
            // tetap harus datang ke pemeriksaan itu, dan email adalah SATU-
            // SATUNYA kabar yang tersisa untuknya karena portalnya sudah
            // ditutup. Membungkam email berarti ia tidak diberi tahu sama sekali
            // lalu dianggap mangkir.
            //
            // Jadi diamnya hanya untuk yang MEMANG diniatkan diam. Sisanya tetap
            // diundang, dan salah setelannya dicatat supaya terlihat orang —
            // bukan diperbaiki diam-diam dengan cara yang merugikan kandidat.
            $tipeSub = self::masterTipeTahap()[$sub->Tipe_Tahap_Kode ?? ''] ?? null;

            if (JadwalPrivat::untuk($sub->Tipe_Tahap_Kode ?? null)) {
                Log::channel('web_career')->info(
                    "[JADWAL] Undangan TIDAK dikirim untuk '{$sub->Label}' (lamaran {$sub->Kode}) — tipe berjadwal privat."
                );

                return 'privat';
            }

            if (($sub->Tampil_Kandidat ?? 'Y') !== 'Y') {
                Log::channel('web_career')->warning(
                    "[JADWAL] Aktivitas '{$sub->Label}' (lamaran {$sub->Kode}) ditandai INTERNAL di alurnya tetapi "
                    .'dijadwalkan. Undangan dikirim dan portal tetap menampilkannya (JadwalPrivat::terlihat), '
                    .'sebab kandidat harus datang. Perbaiki setelan alurnya supaya keduanya tidak lagi berselisih.'
                );
            }

            // TEMPATNYA ikut, bukan hanya patokan yang diketik rekruter — nama
            // resmi, alamat, peta, dan patokan rincinya (lihat
            // UndanganJadwal::muatan). Pada mode berbatas waktu, tempatnya
            // pilihan kandidat sendiri dan suratnya menyebut batas akhirnya.
            // `$bunyi`: penanda maksud surat yang sama isinya — dikirim ulang
            // oleh tim, atau batasnya diperpanjang (lihat templat di EVO Mail).
            WcJadwalEmailJob::dispatch($muatan['userId'], array_merge($muatan['data'], $bunyi));

            DB::table('N_WEB_CAREERS_Lamaran_Tahap_Tes')
                ->where('Id_Lamaran_Tahap_Tes', $subTesId)
                ->update(['Jadwal_Email_At' => now()]);

            return 'terkirim';
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('[JADWAL] undangan gagal diantrekan: '.$e->getMessage());

            return 'gagal';
        }
    }

    /**
     * PATCH /api/v1/karir/lamaran/sub-tes/{id}/kehadiran — catat hadir/tidak.
     *
     * Untuk aktivitas berjadwal (MCU, wawancara), kehadiran adalah GERBANG
     * sebelum hasil bisa dicatat: tim tidak bisa melampirkan hasil MCU untuk
     * orang yang tidak datang, dan tidak masuk akal meloloskannya.
     *
     * TIDAK HADIR langsung menggugurkan aktivitas lalu tahapnya dievaluasi
     * ulang — kandidat yang tidak datang tanpa kabar memang berhenti di situ.
     */
    public function subTesKehadiran(Request $request, string $id)
    {
        $realId = Hashids::decode($id)[0] ?? null;
        if (! $realId) {
            return ResponseHelper::error('Aktivitas tidak valid.', 422);
        }

        $data = $request->validate([
            'hadir' => 'required|in:Y,T',
            // HASIL IKUT DI SINI — satu tindakan, bukan dua.
            //
            // Dulu "Hadir" dan "Catat Hasil" adalah dua tombol dengan dua
            // jendela, padahal keduanya menjelaskan satu peristiwa yang sama:
            // kandidat datang, dan inilah hasilnya. Memisahkannya membuat
            // penilai mengisi catatan di jendela pertama lalu diminta mengisi
            // lagi di jendela kedua — dan yang paling sering terjadi, jendela
            // kedua tidak pernah dibuka sehingga hasilnya tak pernah tercatat.
            'hasil' => 'nullable|in:LULUS,GAGAL',
            'nilai' => 'nullable|numeric|min:0|max:1000',
            // Hasil mode KATEGORI (mis. "Dominance", "Sangat Baik").
            'nilaiTeks' => 'nullable|string|max:100',
            'catatan' => 'nullable|string',
            // Catatan penilaian yang sesungguhnya — HTML dari editor berformat.
            // Batasnya jauh lebih longgar dari `catatan`: hasil wawancara ditulis
            // per kompetensi dan kerap memuat kutipan jawaban kandidat, dan
            // memotongnya di 500 karakter berarti penilai menyingkat sampai
            // catatannya tak lagi bisa dipakai orang lain.
            'catatanHtml' => 'nullable|string',
            // ── HASIL MCU IKUT DI LANGKAH INI ────────────────────────────────
            // Kehadiran dan hasil pemeriksaan adalah SATU peristiwa: kandidat
            // datang ke klinik, diperiksa, dan inilah hasilnya. Dulu statusnya
            // diisi di jendela KEPUTUSAN — sehingga petugas yang menerima hasil
            // dari klinik tidak punya tempat mencatatnya, dan orang yang
            // menekan "Loloskan" disodori formulir medis yang bukan urusannya.
            //
            // Daftar statusnya dari MASTER, bukan `in:` yang ditulis di sini.
            'mcuStatus' => ['nullable', Rule::in(self::masterMcuStatus()->keys()->all())],
            'mcuPenyedia' => 'nullable|string|max:200',
            'mcuCatatan' => 'nullable|string',
            'mcuTanggal' => 'nullable|date',
            // JAWABAN KANDIDAT ATAS PENAWARAN — hanya untuk aktivitas
            // berpenawaran. Daftarnya dari master, bukan `in:` di sini.
            'jawabanPenawaran' => ['nullable', Rule::in(self::masterJawabanPenawaran()->keys()->all())],
        ]);

        $sub = DB::table('N_WEB_CAREERS_Lamaran_Tahap_Tes')->where('Id_Lamaran_Tahap_Tes', $realId)->first();
        if (! $sub) {
            return ResponseHelper::error('Aktivitas tidak ditemukan.', 404);
        }

        if (! $sub->Jadwal_Mulai) {
            return ResponseHelper::error('Aktivitas ini belum dijadwalkan.', 409);
        }

        // ── SYARAT KHUSUS MCU ────────────────────────────────────────────────
        // Hanya berlaku bila kandidat HADIR: orang yang tidak datang tidak
        // punya hasil pemeriksaan, dan menuntutnya berarti tak ada cara menutup
        // MCU yang batal.
        // ── SYARAT KHUSUS AKTIVITAS BERPENAWARAN ─────────────────────────────
        // Kehadiran pada negosiasi tanpa menyebut jawabannya adalah catatan yang
        // tidak menjawab apa pun: seluruh proses berakhir pada satu pertanyaan —
        // kandidat mengambil penawaran ini atau tidak.
        $tipeSubAwal = self::masterTipeTahap()[$sub->Tipe_Tahap_Kode ?? ''] ?? null;
        $isPenawaranSub = ($tipeSubAwal->Flag_Penawaran ?? 'T') === 'Y';
        $defJawab = $isPenawaranSub ? self::masterJawabanPenawaran()->get($data['jawabanPenawaran'] ?? '') : null;

        if ($isPenawaranSub && $data['hadir'] === 'Y') {
            if (! $defJawab) {
                return ResponseHelper::error('Jawaban kandidat atas penawaran wajib dipilih.', 422);
            }
            // Jawaban yang MENUTUP lamaran menuntut alasan — itulah satu-satunya
            // umpan balik kenapa penawaran ini tidak jadi, dan tanpanya rekap
            // "kenapa kandidat lepas" tak pernah bisa dipercaya.
            if (($defJawab->Flag_Butuh_Alasan ?? 'T') === 'Y'
                && mb_strlen(trim(strip_tags((string) ($data['catatanHtml'] ?? $data['catatan'] ?? '')))) < 10) {
                return ResponseHelper::error(
                    "\"{$defJawab->Label_Panjang}\" wajib disertai alasan, minimal 10 karakter.",
                    422,
                );
            }
        }

        $isMcuSub = ($sub->Tipe_Tahap_Kode ?? '') === 'MCU';
        $defMcu = $isMcuSub ? self::masterMcuStatus()->get($data['mcuStatus'] ?? '') : null;

        if ($isMcuSub && $data['hadir'] === 'Y') {
            if (! $defMcu) {
                return ResponseHelper::error('Status hasil MCU wajib dipilih.', 422);
            }
            // Pembatasan kerja tanpa penjelasan tidak bisa ditindaklanjuti siapa
            // pun, dan "belum layak sementara" tanpa keterangan kapan diperiksa
            // ulang sama saja menggantung orang tanpa batas waktu.
            if (($defMcu->Flag_Butuh_Catatan ?? 'T') === 'Y' && trim((string) ($data['mcuCatatan'] ?? '')) === '') {
                return ResponseHelper::error(
                    "\"{$defMcu->Label_Panjang}\" wajib disertai keterangan — tuliskan temuan / pembatasannya.",
                    422,
                );
            }
        }

        if ($kunci = self::kunciUrutan($sub)) {
            return ResponseHelper::error($kunci, 409);
        }

        $now = now();
        $nama = session('career_auth.nama');

        [$html, $ringkas] = self::catatanKaya($data['catatanHtml'] ?? null, $data['catatan'] ?? null);

        // ── SATU PERISTIWA, SATU TRANSAKSI ───────────────────────────────────
        //
        // Sampai di sini pencatatan kehadiran bisa menyentuh EMPAT hal:
        // kehadiran + rincian MCU pada aktivitasnya, tanggapan kandidat pada
        // tahapnya, penutupan aktivitasnya, dan — bila jawabannya menolak atau
        // mundur — penutupan seluruh lamaran lewat ketukPalu(). Dulu keempatnya
        // berdiri sendiri-sendiri.
        //
        // Yang paling merugikan bukan kegagalan teknis, melainkan penolakan
        // yang WAJAR: kandidat sedang DITAHAN, atau tahapnya sudah diputus
        // rekan sebelah. ketukPalu() menolak, tetapi tiga tulisan sebelumnya
        // sudah telanjur masuk — aktivitasnya tertutup GAGAL dan tahapnya
        // menyandang "kandidat mengundurkan diri", sementara lamarannya masih
        // BERJALAN. Jawabannya balasannya pun 200: "jawaban dicatat, tetapi
        // lamaran gagal ditutup". Tidak ada satu pun cara memperbaiki keadaan
        // itu lewat layar, karena aktivitasnya sudah final.
        //
        // Sekarang seluruhnya jadi atau seluruhnya batal, dan penolakan wajar
        // dibalas 422 dengan alasannya — keadaan basis data persis seperti
        // sebelum tombol ditekan, jadi tim bisa melepas hold lalu mengulang.
        try {
            $aksi = DB::transaction(function () use ($realId, $sub, $data, $now, $nama, $html, $ringkas, $isMcuSub, $defMcu, $defJawab) {
                // TIDAK HADIR = aktivitas selesai dengan hasil GAGAL; mesin keputusan
                // yang menentukan nasib tahapnya (bisa gugur, bisa menunggu aktivitas
                // lain di tahap yang sama). Implementasinya SATU, dipakai juga
                // pernyataan "tidak melanjutkan seleksi" (KonfirmasiJadwal::jawab).
                if ($data['hadir'] === 'T') {
                    $eval = $this->svc->catatTidakHadir($sub, $html, $ringkas, $nama, (int) session('career_auth.id'));

                    return ['mode' => 'TIDAK_HADIR', 'outcome' => $eval['outcome'] ?? null];
                }

                DB::table('N_WEB_CAREERS_Lamaran_Tahap_Tes')->where('Id_Lamaran_Tahap_Tes', $realId)->update([
                    'Jadwal_Hadir' => $data['hadir'],
                    'Jadwal_Hadir_At' => $now,
                    'Jadwal_Hadir_By' => $nama,
                    'Catatan' => $ringkas ?: $sub->Catatan,
                    // Catatan lama TIDAK ditimpa dengan kosong: menandai kehadiran
                    // sering dilakukan dua kali (salah klik, lalu dibetulkan), dan
                    // pembetulan kedua yang catatannya kosong akan menghapus penilaian
                    // yang sudah ditulis di percobaan pertama.
                    'Catatan_Html' => $html ?: $sub->Catatan_Html,
                    'Updated_At' => $now,
                    'Updated_By' => $nama,
                ] + ($isMcuSub ? [
                    'Mcu_Status' => $data['mcuStatus'] ?? $sub->Mcu_Status,
                    'Mcu_Penyedia' => $data['mcuPenyedia'] ?? $sub->Mcu_Penyedia,
                    'Mcu_Tanggal' => $data['mcuTanggal'] ?? $sub->Mcu_Tanggal,
                    'Mcu_Catatan' => $data['mcuCatatan'] ?? $sub->Mcu_Catatan,
                ] : []));

                // ── HADIR + HASIL SEKALIGUS ─────────────────────────────────
                // Bila hasilnya ikut dikirim, aktivitas langsung DITUTUP di permintaan
                // yang sama. Satu peristiwa ("kandidat datang, hasilnya begini") =
                // satu tindakan; memisahkannya jadi dua jendela membuat yang kedua
                // kerap tak pernah dibuka, dan hasilnya tak pernah tercatat.
                //
                // Ujian online dikecualikan: nilainya datang sendiri dari HCLearn, dan
                // mengetiknya di sini berarti menimpa angka resmi dengan tebakan.
                $tipeSub = self::masterTipeTahap()[$sub->Tipe_Tahap_Kode ?? ''] ?? null;
                $online = ($tipeSub->Perilaku_Kode ?? null) === 'CAT' || ($sub->Provider ?? '') === 'THIRD_PARTY';
                // VERDICT MCU DITURUNKAN DARI STATUSNYA, tidak ditanyakan dua kali.
                // Flag_Lolos di master sudah menyatakan apakah status itu berarti
                // memenuhi syarat; meminta penilai memilih LULUS/GAGAL lagi hanya
                // membuka peluang keduanya berselisih — "Unfit" tapi ditandai lulus.
                if ($defMcu) {
                    $data['hasil'] = ($defMcu->Flag_Lolos ?? 'T') === 'Y' ? 'LULUS' : 'GAGAL';
                }

                // Jawaban penawaran menentukan verdict aktivitasnya, dan DICATAT sebagai
                // tanggapan kandidat pada tahapnya. Kolom Tanggapan_* itu dulu ditulis
                // dari portal; sejak tombolnya dicabut, TIM yang menuliskannya — dan
                // worklist tetap membaca kolom yang sama, jadi tampilannya tak berubah.
                if ($defJawab) {
                    $data['hasil'] = ($defJawab->Flag_Lolos ?? 'T') === 'Y' ? 'LULUS' : 'GAGAL';

                    DB::table('N_WEB_CAREERS_Lamaran_Tahap')
                        ->where('Id_Lamaran_Tahap', $sub->Lamaran_Tahap_Id)
                        ->update([
                            'Tanggapan_Kandidat' => $defJawab->Kode === 'SETUJU' ? 'TERIMA' : 'MUNDUR',
                            'Tanggapan_Catatan' => $ringkas ?: $defJawab->Nama,
                            'Tanggapan_At' => $now,
                            'Updated_At' => $now,
                            'Updated_By' => $nama,
                        ]);
                }

                $bolehTutup = ! $online && ($data['hasil'] !== null || $sub->Peran === 'INFORMATIF');

                if (! $bolehTutup) {
                    return ['mode' => 'HADIR_SAJA'];
                }

                // NILAI DISIMPAN SESUAI MODENYA.
                //
                // Mode KATEGORI menolak nilai di luar daftar pilihannya: predikat asing
                // yang lolos masuk membuat rekap "berapa yang Dominance" tak pernah bisa
                // dipercaya, dan itu satu-satunya alasan kategori dipakai.
                $nilaiAngka = $sub->Nilai;
                $nilaiTeks = $sub->Nilai_Teks;
                $penilaian = self::bentukPenilaian($sub);

                if (($penilaian['tipe'] ?? 'NONE') === 'ANGKA') {
                    $nilaiAngka = $data['nilai'] ?? $sub->Nilai;
                } elseif (($penilaian['tipe'] ?? 'NONE') === 'TEKS' && ! empty($data['nilaiTeks'])) {
                    if ($penilaian['opsi'] && ! in_array($data['nilaiTeks'], $penilaian['opsi'], true)) {
                        // Ditolak SETELAH kehadiran sempat ditulis — maka harus
                        // berupa lemparan, bukan nilai balik: hanya lemparan yang
                        // menggulung balik tulisan itu. Dulu ia `return`, dan
                        // predikat salah ketik meninggalkan aktivitas yang
                        // kehadirannya tercatat tapi tak pernah ditutup.
                        throw new \DomainException(
                            'Pilihan hasil tidak dikenali untuk aktivitas ini: '.implode(' / ', $penilaian['opsi']).'.'
                        );
                    }
                    $nilaiTeks = $data['nilaiTeks'];
                }

                DB::table('N_WEB_CAREERS_Lamaran_Tahap_Tes')->where('Id_Lamaran_Tahap_Tes', $realId)->update([
                    'Status' => 'SELESAI',
                    // Aktivitas INFORMATIF tidak diberi verdict — ia memang bukan penentu.
                    'Hasil' => $sub->Peran === 'INFORMATIF' ? null : $data['hasil'],
                    'Nilai' => $nilaiAngka,
                    'Nilai_Teks' => $nilaiTeks,
                    'Flag_Selesai' => 'Y',
                    'Waktu_Selesai' => $now,
                    'Updated_By_Id' => session('career_auth.id'),
                ]);

                // ── JAWABAN YANG MENUTUP LAMARAN ────────────────────────────
                //
                // "Menolak penawaran" dan "mengundurkan diri" tidak menunggu palu siapa
                // pun: kandidatnya sudah pergi. Ditutup DI SINI, lewat ketukPalu() yang
                // sama persis dengan tombol tahap — supaya seluruh akibat yang
                // menyertainya (email, kuota, pilihan Talent Pool, jejak keputusan)
                // berjalan identik. Jalur kedua yang menulis sendiri ke tabel akan
                // diam-diam berbeda perilakunya, dan selisih itu baru ketahuan
                // berbulan-bulan kemudian lewat laporan yang tidak cocok.
                if ($defJawab && $defJawab->Hasil_Keputusan_Kode) {
                    $hasilTutup = $this->svc->ketukPalu(
                        (int) $sub->Lamaran_Tahap_Id,
                        $defJawab->Hasil_Keputusan_Kode,
                        $ringkas ?: $defJawab->Nama,
                        (int) session('career_auth.id'),
                        null,
                    );

                    if (! $hasilTutup['ok']) {
                        throw new \DomainException($hasilTutup['pesan']);
                    }

                    return ['mode' => 'PENAWARAN_TUTUP', 'outcome' => $defJawab->Hasil_Keputusan_Kode];
                }

                $eval = $this->svc->evaluasiTahap((int) $sub->Lamaran_Tahap_Id, (int) session('career_auth.id'));

                return ['mode' => 'SELESAI', 'outcome' => $eval['outcome'] ?? null, 'hasil' => $data['hasil'] ?? null];
            });
        } catch (\DomainException $e) {
            // Penolakan aturan bisnis — transaksinya sudah digulung balik, jadi
            // kehadiran pun tidak jadi tercatat. Itu memang yang diinginkan:
            // separuh peristiwa lebih menyesatkan daripada tidak ada sama sekali.
            return ResponseHelper::error($e->getMessage(), 422);
        }

        // ── SESUDAH COMMIT ───────────────────────────────────────────────────
        // Email BARU diantrekan di sini, di luar transaksi. Antrean proyek ini
        // berjalan dengan `after_commit` mati, sehingga job yang didaftarkan di
        // dalam transaksi bisa dijemput worker sebelum transaksinya selesai —
        // kandidat menerima surat "Selamat, Anda lolos" atas keputusan yang
        // sedetik kemudian digulung balik, dan surat itu tak bisa ditarik lagi.

        // Kehadiran = FAKTA hari H. Undangan & permintaan jadwal lain yang masih
        // terbuka selesai di sini — tidak ada lagi surel konfirmasi untuknya.
        KonfirmasiJadwal::catatKehadiran((int) $realId, $data['hadir'], (string) ($nama ?: 'ADMIN'), session('career_auth.id') ? (int) session('career_auth.id') : null);

        if ($aksi['mode'] === 'TIDAK_HADIR') {
            return ResponseHelper::success(['outcome' => $aksi['outcome']], 'Ditandai TIDAK HADIR — tahap dievaluasi ulang.');
        }

        if ($aksi['mode'] === 'HADIR_SAJA') {
            return ResponseHelper::success(null, 'Kehadiran dicatat.');
        }

        if ($aksi['mode'] === 'PENAWARAN_TUTUP') {
            Log::channel('web_career')->info(
                "Penawaran dijawab {$defJawab->Kode} pada sub-tes #{$realId} → lamaran ditutup {$aksi['outcome']}."
            );

            return ResponseHelper::success(
                ['outcome' => $aksi['outcome']],
                "Jawaban dicatat: {$defJawab->Label_Panjang}. Lamaran ditutup.",
            );
        }

        $outcome = $aksi['outcome'];

        // Tahap yang menyimpulkan sendiri (mode otomatis) tetap mengabari
        // kandidat — sama seperti jalur "Catat Hasil" sebelumnya.
        if (in_array($outcome, ['LANJUT', 'GUGUR'], true)) {
            $lamaranId = (int) DB::table('N_WEB_CAREERS_Lamaran_Tahap')
                ->where('Id_Lamaran_Tahap', $sub->Lamaran_Tahap_Id)
                ->value('Lamaran_Id');
            if ($lamaranId) {
                $this->kirimEmailHasilTahap($lamaranId, $outcome === 'LANJUT');
            }
        }

        Log::channel('web_career')->info(
            "Aktivitas #{$realId} ditandai HADIR + hasil ".($aksi['hasil'] ?? 'INFORMATIF').' → evaluasi: '.($outcome ?? '-')
        );

        return ResponseHelper::success(['outcome' => $outcome], 'Kehadiran & hasil tersimpan.');
    }

    public function subTesCatatHasil(Request $request, string $id)
    {
        $realId = Hashids::decode($id)[0] ?? null;
        if (! $realId) {
            return ResponseHelper::error('Sub-tes tidak valid.', 422);
        }

        $data = $request->validate([
            'hasil' => 'nullable|in:LULUS,GAGAL',
            'nilai' => 'nullable|numeric|min:0|max:1000',
            // Predikat (mode KATEGORI). Pintu kehadiran sudah menerimanya sejak
            // lama; pintu ini belum — sehingga aktivitas ber-mode KATEGORI yang
            // dicatat lewat "Catat Hasil" kehilangan predikatnya tanpa satu pun
            // galat, dan rekap "berapa yang Dominance" diam-diam kurang.
            'nilaiTeks' => 'nullable|string|max:100',
            // Kesimpulan pemeriksaan (background / reference check). Daftar
            // nilainya berbeda per jenis, jadi kesahihannya diperiksa setelah
            // aktivitasnya diketahui — bukan di sini.
            'adjudikasi' => 'nullable|string|max:30',
            'catatan' => 'nullable|string',
            // Isi editor berformat — tanpa batas panjang, lihat alasannya di putus().
            'catatanHtml' => 'nullable|string',
            // Field khusus MCU. Menumpang endpoint ini, BUKAN endpoint sendiri:
            // hasil MCU tetap melewati mesin keputusan yang sama seperti hasil
            // aktivitas lain, hanya membawa rincian medis tambahan.
            //
            // Daftarnya DARI MASTER. Dulu ketiganya ditulis mati di sini, dan
            // saat status keempat — TEMPORARY_UNFIT — ditambahkan lewat master,
            // baris ini tidak ikut berubah: pintu ini menolaknya "tidak sah"
            // sementara pintu sebelah (subTesKehadiran) menerimanya. Satu daftar
            // yang hidup di dua tempat pasti berselisih, dan yang kalah selalu
            // yang lupa disunting.
            'mcuStatus' => ['nullable', Rule::in(self::masterMcuStatus()->keys()->all())],
            'mcuPenyedia' => 'nullable|string|max:200',
            'mcuCatatan' => 'nullable|string',
            'mcuTanggal' => 'nullable|date',
        ]);

        try {
            $sub = DB::table('N_WEB_CAREERS_Lamaran_Tahap_Tes')->where('Id_Lamaran_Tahap_Tes', $realId)->first();
            if (! $sub) {
                return ResponseHelper::error('Sub-tes tidak ditemukan.', 404);
            }
            if ($sub->Flag_Selesai === 'Y') {
                return ResponseHelper::error('Sub-tes ini sudah final.', 422);
            }

            if ($kunci = self::kunciUrutan($sub)) {
                return ResponseHelper::error($kunci, 409);
            }

            // UJIAN ONLINE TIDAK DICATAT MANUAL.
            //
            // Nilainya dihitung dan dilaporkan HCLearn; mengetiknya sendiri di
            // sini berarti menimpa angka resmi dengan tebakan, dan admin tak
            // punya sumber angka itu. Kalau kandidat memang tak mengerjakan,
            // yang benar adalah menandainya TIDAK HADIR — itu fakta yang cuma
            // diketahui tim, dan tetap melepas tahap dari status menunggu.
            //
            // Aktivitas yang dikerjakan tim (wawancara, tes offline, FGD) justru
            // sebaliknya: tak ada sistem lain yang mengirim hasilnya.
            $tipeSub = self::masterTipeTahap()[$sub->Tipe_Tahap_Kode ?? ''] ?? null;
            $online = ($tipeSub->Perilaku_Kode ?? null) === 'CAT' || ($sub->Provider ?? '') === 'THIRD_PARTY';

            // UJIAN ONLINE BER-PERAN INFORMATIF JUSTRU MENUNGGU ADMIN.
            //
            // Alat tes seperti PAPI Kostick, DISC, dan Kraeplin tidak berbunyi
            // lulus/gagal — keluarannya profil, bukan angka kelulusan. Yang
            // menyatakan layak-tidaknya memang penilai, bukan mesinnya.
            //
            // Yang tetap ditolak hanya ujian PENENTU: nilainya objektif dan
            // sudah punya ambang batas, jadi mengetiknya sendiri di sini berarti
            // menimpa angka resmi dengan tebakan.
            //
            // STATUS-nya ikut disyaratkan. Tanpa itu, ujian yang baru
            // DIJADWALKAN — kandidatnya belum menyentuh soal — sudah bisa
            // dinyatakan lulus lewat pintu ini. Layar memang tidak menawarkan
            // tombolnya, tapi pintu belakang tidak boleh lebih longgar daripada
            // layar depan. Bila hasilnya tak kunjung datang, yang benar adalah
            // "Tidak hadir".
            $adminYangMemutuskan = $online
                && $sub->Peran === 'INFORMATIF'
                && ($sub->Status ?? '') === 'MENUNGGU_KEPUTUSAN';

            if ($online && ! $adminYangMemutuskan) {
                // Dua sebab, dua kalimat. Menyamakannya membuat admin yang
                // sekadar terlalu cepat mengira alat tesnya salah dipasang.
                return ResponseHelper::error(
                    $sub->Peran === 'INFORMATIF'
                        ? "Hasil \"{$sub->Label}\" belum masuk dari HCLearn — keputusannya baru bisa diberikan setelah kandidat mengerjakannya. "
                            .'Bila ia memang tidak mengerjakan, tandai "Tidak hadir".'
                        : "\"{$sub->Label}\" adalah ujian online penentu — hasilnya masuk sendiri dari HCLearn dan tidak dicatat manual. "
                            .'Bila kandidat tidak mengerjakannya, tandai "Tidak hadir".',
                    422
                );
            }

            // ── GERBANG KUESIONER SKRINING ──────────────────────────────
            //
            // Aktivitas skrining yang terikat kuesioner TIDAK BISA ditutup
            // sebelum kuesionernya diselesaikan. Inilah inti fiturnya: tanpa
            // gerbang ini, "Catat Hasil" tetap bisa ditekan dan seluruh
            // pertanyaan yang sudah disusun di Master Template tidak pernah
            // benar-benar ditanyakan — sementara datanya terlihat lengkap.
            //
            // Ditegakkan DI SINI, bukan cukup dengan mematikan tombolnya:
            // layar bisa basi, dan pintu ini bisa diketuk langsung.
            //
            // "Tidak hadir" tetap jadi jalan keluarnya — kandidat yang tidak
            // mengangkat telepon memang tidak punya jawaban untuk diisi, dan
            // itu ditempuh lewat pintu kehadiran, bukan pintu ini.
            if ($sisaSkrining = \App\Support\Career\Skrining::belumTuntas($sub)) {
                return ResponseHelper::error(
                    "\"{$sub->Label}\" belum bisa ditutup — {$sisaSkrining}. "
                    .'Buka kuesionernya di baris aktivitas ini, isi jawabannya, lalu tekan Selesaikan. '
                    .'Bila kandidat tidak bisa dihubungi sama sekali, tandai "Tidak hadir".',
                    422
                );
            }

            // Nilainya milik HCLearn; admin hanya menyatakan lulus/tidaknya.
            if ($adminYangMemutuskan && empty($data['hasil'])) {
                return ResponseHelper::error(
                    "Pilih Lulus atau Tidak Lulus untuk \"{$sub->Label}\" — nilainya sudah masuk, keputusannya yang ditunggu.",
                    422
                );
            }

            // Sub-tes PENENTU wajib membawa verdict; INFORMATIF cukup selesai + nilai.
            if ($sub->Peran === 'PENENTU' && empty($data['hasil'])) {
                return ResponseHelper::error('Pilih hasil (Lulus / Gagal) untuk aktivitas penentu.', 422);
            }

            $nama = session('career_auth.nama', 'ADMIN');
            [$html, $catatan] = self::catatanKaya($data['catatanHtml'] ?? null, $data['catatan'] ?? null);

            // RINCIAN MCU HANYA DISENTUH BILA AKTIVITASNYA MEMANG MCU.
            //
            // Dulu keempat kolomnya ditulis tanpa syarat dengan `?? null`, dan
            // itu bukan "membiarkan kosong" melainkan MENGHAPUS: setiap kali
            // hasil dicatat lewat pintu ini, status kesehatan yang sudah dicatat
            // saat kehadiran ikut ternol. Layar tidak lagi mengirim field ini —
            // MCU pindah ke jendela Hadir — sehingga penghapusannya berlangsung
            // tanpa satu pun tanda di layar maupun di log.
            //
            // Nilai lamanya dipertahankan bila field-nya tidak dikirim; hanya
            // yang benar-benar disertakan yang menimpa.
            $mcu = ($sub->Tipe_Tahap_Kode ?? '') === 'MCU' ? [
                'Mcu_Status' => $data['mcuStatus'] ?? $sub->Mcu_Status,
                'Mcu_Penyedia' => $data['mcuPenyedia'] ?? $sub->Mcu_Penyedia,
                'Mcu_Catatan' => $data['mcuCatatan'] ?? $sub->Mcu_Catatan,
                'Mcu_Tanggal' => $data['mcuTanggal'] ?? $sub->Mcu_Tanggal,
            ] : [];

            // SATU TRANSAKSI: "hasilnya begini" dan "maka tahapnya begini" adalah
            // satu keputusan. Bila evaluasi gagal setelah aktivitasnya ditandai
            // final, tak ada jalan mengulang — pintu ini menolak aktivitas yang
            // sudah final, dan tahapnya diam menunggu kesimpulan yang tak datang.
            // NILAI DISIMPAN SESUAI MODENYA — aturan yang sama persis dengan
            // pintu kehadiran (subTesKehadiran).
            //
            // Sebelumnya pintu ini selalu menulis ke kolom ANGKA, apa pun mode
            // aktivitasnya. Akibatnya dua arah: predikat KATEGORI hilang, dan
            // pemeriksaan (reference/background check) yang memang TANPA_NILAI
            // tetap punya kotak angka di layar — angka yang lalu tersimpan
            // sebagai kalau hasil ukur.
            // KESIMPULAN PEMERIKSAAN — nilainya harus milik jenis ini.
            //
            // "BERSIH" pada reference check dan "DIREKOMENDASIKAN" pada background
            // check sama-sama tidak berarti apa-apa; menerimanya membuat laporan
            // memuat kategori yang tak pernah didefinisikan untuk aktivitas itu.
            $adjudikasi = null;
            $pilihanAdj = Pemeriksaan::ADJUDIKASI[$sub->Tipe_Tahap_Kode ?? ''] ?? [];

            if (! empty($data['adjudikasi'])) {
                if (! in_array($data['adjudikasi'], $pilihanAdj, true)) {
                    return ResponseHelper::error('Kesimpulan pemeriksaan tidak dikenali untuk aktivitas ini.', 422);
                }
                $adjudikasi = $data['adjudikasi'];
            }

            // ── DUA GERBANG KEPATUHAN, HANYA UNTUK PEMERIKSAAN ───────────────────
            if (Pemeriksaan::untuk($sub->Tipe_Tahap_Kode ?? null)) {
                // 1. TANPA PERSETUJUAN, TIDAK ADA PEMERIKSAAN.
                //
                // Memeriksa latar belakang seseorang tanpa persetujuannya adalah
                // pelanggaran UU PDP 27/2022, bukan kelalaian administratif. Yang
                // dicari lebih dulu pernyataan `consent` di formulir lamaran yang
                // sudah dikirim; kalau formulirnya memang belum memuatnya, admin
                // menyatakan sendiri di mana persetujuan tertulisnya berada.
                $setuju = Pemeriksaan::persetujuanAktivitas($sub);
                $ditolak = (bool) ($setuju['ditolak'] ?? false);

                // Yang dituntut KEPUTUSAN DI AKTIVITAS INI, bukan pernyataan umum di
                // formulir lamaran: pertanyaan "bersediakah diperiksa" memang baru
                // diajukan saat tahap ini dimulai.
                if (($setuju['keputusan'] ?? null) === null) {
                    return ResponseHelper::error(
                        'Kandidat belum ditanya bersedia atau tidak diperiksa. '
                        .'Tekan Setuju atau Tidak setuju pada aktivitas ini lebih dulu.',
                        422,
                    );
                }

                // KANDIDAT MENOLAK → TIDAK ADA YANG DIPERIKSA.
                //
                // Aktivitasnya tetap boleh ditutup — penolakan adalah kenyataan yang
                // harus bisa diselesaikan, bukan jalan buntu. Yang dilarang
                // menyimpulkannya BERSIH atau DIREKOMENDASIKAN: tidak ada seorang pun
                // yang benar-benar diperiksa, jadi tidak ada dasar menyatakannya aman.
                $positif = ['BERSIH', 'DIREKOMENDASIKAN'];

                if ($ditolak && in_array($adjudikasi, $positif, true)) {
                    return ResponseHelper::error(
                        'Kandidat menolak pemeriksaan, jadi hasilnya tidak bisa disimpulkan '
                        .'bersih atau direkomendasikan — tidak ada yang sempat diperiksa.',
                        422,
                    );
                }

                // 2. HAK MENANGGAPI sebelum digugurkan karena temuan.
                //
                // "Tidak memenuhi" hampir selalu berasal dari satu komponen yang
                // bermasalah — dan yang paling sering bermasalah bukan kandidatnya,
                // melainkan kampus yang tak membalas surat verifikasi. Kandidat
                // berhak tahu dan berhak menjawab lebih dulu.
                $negatif = ['TIDAK_MEMENUHI', 'TIDAK_REKOMENDASI'];

                if (in_array($adjudikasi, $negatif, true) && empty($sub->Tanggapan_Diminta_At)) {
                    return ResponseHelper::error(
                        'Kesimpulan ini menggugurkan kandidat karena temuan. Catat dulu bahwa kandidat '
                        .'sudah diberi kesempatan menanggapi — ada di panel Pemeriksaan.',
                        422,
                    );
                }
            }

            $penilaian = self::bentukPenilaian($sub);
            $tipeNilai = $penilaian["tipe"] ?? 'NONE';
            $nilaiAngka = $sub->Nilai;
            $nilaiTeks = $sub->Nilai_Teks;

            if ($adminYangMemutuskan) {
                // Ujian online: angkanya milik HCLearn — jangan ditimpa kosong.
                $nilaiAngka = $sub->Nilai;
            } elseif ($tipeNilai === 'ANGKA') {
                $nilaiAngka = $data['nilai'] ?? null;
            } elseif ($tipeNilai === 'TEKS' && ! empty($data['nilaiTeks'])) {
                if (! empty($penilaian['opsi']) && ! in_array($data['nilaiTeks'], $penilaian['opsi'], true)) {
                    return ResponseHelper::error(
                        'Pilihan hasil tidak dikenali untuk aktivitas ini: '.implode(' / ', $penilaian['opsi']).'.',
                        422,
                    );
                }
                $nilaiTeks = $data['nilaiTeks'];
            }

            $outcome = DB::transaction(function () use ($realId, $sub, $data, $catatan, $html, $mcu, $nama, $adminYangMemutuskan, $nilaiAngka, $nilaiTeks, $adjudikasi) {
                DB::table('N_WEB_CAREERS_Lamaran_Tahap_Tes')->where('Id_Lamaran_Tahap_Tes', $realId)->update([
                    'Status' => 'SELESAI',
                    // INFORMATIF pada UJIAN ONLINE tetap menyimpan verdict-nya:
                    // itulah satu-satunya alasan admin menekan Lulus/Tidak Lulus.
                    // Yang tidak diberi verdict hanya INFORMATIF non-ujian
                    // (wawancara pendamping, catatan) — di sana perannya memang
                    // sekadar bahan pertimbangan.
                    'Hasil' => ($sub->Peran === 'INFORMATIF' && ! $adminYangMemutuskan) ? null : $data['hasil'],
                    'Nilai' => $nilaiAngka,
                    'Nilai_Teks' => $nilaiTeks,
                    'Catatan' => $catatan ?: $sub->Catatan,
                    'Catatan_Html' => $html ?: $sub->Catatan_Html,
                ] + ($adjudikasi ? [
                    // Siapa & kapan ikut disimpan: kesimpulan pemeriksaan adalah
                    // penilaian atas seseorang, dan penilaian tanpa nama penilainya
                    // tidak bisa dipertanggungjawabkan siapa pun.
                    'Adjudikasi' => $adjudikasi,
                    'Adjudikasi_At' => now(),
                    'Adjudikasi_By' => $nama,
                    'Adjudikasi_By_Id' => session('career_auth.id'),
                ] : []) + [
                    'Flag_Selesai' => 'Y',
                    'Waktu_Selesai' => now(),
                    'Updated_At' => now(),
                    'Updated_By' => $nama,
                    'Updated_By_Id' => session('career_auth.id'),
                ] + $mcu);

                $eval = $this->svc->evaluasiTahap((int) $sub->Lamaran_Tahap_Id, (int) session('career_auth.id'));

                return $eval['outcome'] ?? null;
            });

            // Tahap menyimpulkan otomatis (mode auto) → kabari kandidat via email,
            // konsisten dengan jalur callback. SIAP_DIPUTUS tidak berkirim email.
            //
            // DI LUAR TRANSAKSI — lihat alasannya di subTesKehadiran().
            if (in_array($outcome, ['LANJUT', 'GUGUR'], true)) {
                $lamaranId = (int) DB::table('N_WEB_CAREERS_Lamaran_Tahap')->where('Id_Lamaran_Tahap', $sub->Lamaran_Tahap_Id)->value('Lamaran_Id');
                if ($lamaranId) {
                    $this->kirimEmailHasilTahap($lamaranId, $outcome === 'LANJUT');
                }
            }

            Log::channel('web_career')->info("Sub-tes #{$realId} dicatat ".($data['hasil'] ?? 'SELESAI').' → evaluasi: '.($outcome ?? '-'));

            return ResponseHelper::success(['outcome' => $outcome], 'Hasil dicatat.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal catat hasil sub-tes #{$id}: ".$e->getMessage());

            return ResponseHelper::error('Gagal memproses.', 500);
        }
    }

    /**
     * GET berkas — PROFIL LENGKAP kandidat untuk offcanvas: seluruh FORMULIR yang
     * sudah diisi (di-loop, urut tahap) + berkas/PDF tiap formulir + biodata.
     *
     * "Looping": formulir yang tampil = pengisian yang benar-benar ADA. Kandidat di
     * tahap 4 yang mengisi formulir di tahap 1 & 3 otomatis menampilkan keduanya;
     * yang baru di tahap 2 hanya menampilkan formulir tahap 1.
     */
    public function worklistBerkas(string $id)
    {
        $realId = Hashids::decode($id)[0] ?? null;
        $lamaran = DB::table('N_WEB_CAREERS_Lamaran as l')
            ->join('N_WEB_CAREERS_Program as p', 'p.Id_Program', '=', 'l.Program_Id')
            ->leftJoin('N_WEB_CAREERS_Users as u', 'u.Id_Users', '=', 'l.Id_Users')
            ->leftJoin('N_WEB_CAREERS_Program_Posisi as x', 'x.Id_Program_Posisi', '=', 'l.Program_Posisi_Id')
            ->where('l.Id_Lamaran', $realId)
            // Lingkup yang sama dengan papan (kategori program & PIC loker) —
            // langsung di kueri ini, bukan kueri terpisah: respons ini kini
            // juga membawa hasil & berkas setiap tahap.
            ->when($this->lingkupKategori(), fn ($w, $izin) => $w->whereIn('p.Kategori', $izin))
            ->when(AksesService::picDiizinkan(self::PAGE) !== null,
                fn ($w) => $w->whereIn('x.Pic_Kode_Karyawan', AksesService::picDiizinkan(self::PAGE) ?: ['__tidak_ada__']))
            ->select('l.*', 'p.Nama as ProgramNama', 'u.Nama as Pelamar', 'u.Email as EmailAkun', 'x.Posisi')
            ->first();

        if (! $lamaran) {
            return ResponseHelper::error('Lamaran tidak ditemukan.', 404);
        }

        // Seluruh tahap lamaran ini — SEKALI, dipakai ringkasan tahap dan
        // catatan untuk kandidat di bawah.
        $tahapSemua = DB::table('N_WEB_CAREERS_Lamaran_Tahap')->where('Lamaran_Id', $realId)->orderBy('Urutan')->get();

        $pengisian = DB::table('N_WEB_CAREERS_Formulir_Pengisian as fp')
            ->leftJoin('N_WEB_CAREERS_Lamaran_Tahap as t', 't.Id_Lamaran_Tahap', '=', 'fp.Lamaran_Tahap_Id')
            ->where('fp.Lamaran_Id', $realId)
            ->orderBy('t.Urutan')
            ->select('fp.*', 't.Urutan as TahapUrutan', 't.Label as TahapLabel')
            ->get();

        $berkasPer = DB::table('N_WEB_CAREERS_Formulir_Berkas')
            ->whereIn('Formulir_Pengisian_Id', $pengisian->pluck('Id_Formulir_Pengisian')->all() ?: [0])
            ->orderBy('Urutan')
            ->get()
            ->groupBy('Formulir_Pengisian_Id');

        // Lihat catatan panjang di formulirTerkirim(): skema beku inilah yang
        // tahu label asli, tipe, kelompok, dan URUTAN tiap pertanyaan.
        $adaSnapshot = FormulirSchema::punyaKolomPengisianSnapshot();

        $formulir = $pengisian->values()->map(function ($fp, $i) use ($berkasPer, $adaSnapshot) {
            $jawaban = json_decode($fp->Jawaban_Json ?: '{}', true) ?: [];
            $skema = $adaSnapshot && ! empty($fp->Schema_Snapshot_Json)
                ? (json_decode($fp->Schema_Snapshot_Json, true) ?: null)
                : null;

            $berkas = collect($berkasPer->get($fp->Id_Formulir_Pengisian, []))->map(function ($b) {
                $ext = strtolower($b->Ekstensi ?: pathinfo($b->Nama_Asli, PATHINFO_EXTENSION));

                return [
                    'field' => $b->Field_Key,
                    // Posisi baris dibawa apa adanya supaya pencocokan berkas ke
                    // kartu baris tidak perlu menebak dari nama berkasnya.
                    'bagian' => $b->Bagian_Key,
                    'baris' => $b->Baris_Index !== null ? (int) $b->Baris_Index : null,
                    'nomor' => $b->Baris_Index !== null ? ((int) $b->Baris_Index) + 1 : null,
                    'nama' => $b->Nama_Asli,
                    'url' => url('/api/v1/karir/lamaran/berkas/file/'.Hashids::encode($b->Id_Formulir_Berkas)),
                    'ext' => $ext,
                    'mime' => $b->Mime,
                    'isPdf' => $ext === 'pdf' || $b->Mime === 'application/pdf',
                    'isImage' => in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true),
                    'ukuran' => (int) $b->Ukuran_Byte,
                    'status' => $b->Status_Verifikasi,
                    // Waktu unggah — dasar urutan "terbaru dulu" di panel berkas.
                    // Urutan kolom `Urutan` menata lembaran MENURUT FORMULIRNYA;
                    // yang dicari peninjau justru sebaliknya: apa yang paling
                    // baru masuk, tak peduli dari formulir mana.
                    'waktu' => (string) ($b->Waktu_Unggah ?: $b->Created_At ?: ''),
                    // Diunggah ADMIN atas nama kandidat (panel Pemulihan Berkas),
                    // bukan kiriman kandidat sendiri. Layar menandainya — peninjau
                    // berhak tahu lembar mana yang datang lewat jalur belakang,
                    // oleh siapa, dan dengan alasan apa. Hanya jalur admin ini
                    // yang membawa nama & alasannya; portal kandidat tidak.
                    'olehAdmin' => PemulihanBerkas::olehAdmin($b),
                    'oleh' => PemulihanBerkas::olehAdmin($b) ? $b->Created_By : null,
                    'catatan' => PemulihanBerkas::olehAdmin($b) ? $b->Catatan : null,
                ];
            })->values();

            return [
                'no' => $i + 1,
                'urutan' => (int) ($fp->TahapUrutan ?? 0),
                'label' => $fp->TahapLabel ?: ($fp->Sumber === 'PENDAFTARAN' ? 'Formulir Pendaftaran' : 'Formulir Tahap'),
                'sumber' => $fp->Sumber,
                // Kode komponen skema (FORMULIR_1/2/…) — dipakai layar untuk
                // mencari LABEL ASLI tiap isian. Tanpa ini label cuma bisa
                // ditebak dari nama kuncinya, dan `v_nama`/`v_wa` terbaca
                // "V Nama"/"V Wa": bahasa mesin, bukan bahasa manusia.
                'komponen' => $fp->Komponen_Kode,
                // Skema beku (langkah → bagian → field): label, tipe, kelompok,
                // dan urutan baca. Kode komponen di atas cuma cadangan untuk
                // formulir bawaan lama yang memang tak punya snapshot.
                'skema' => $skema,
                // String mentah SQL Server — optional()->__toString() di atasnya
                // menghasilkan NULL, membuat formulir terkirim dianggap belum.
                'waktuKirim' => (string) ($fp->Waktu_Kirim ?: ''),
                // Isian yang ternyata BERKAS dibawa berikut url-nya, supaya admin
                // bisa membuka dokumennya langsung dari daftar isian dan tidak
                // hanya melihat nama berkas sebagai teks mati.
                'jawaban' => collect($jawaban)->map(function ($v, $k) use ($berkas) {
                    // SELURUH berkas isian ini, bukan yang pertama saja. Satu
                    // pertanyaan unggahan boleh menerima beberapa lembar; bentuk
                    // lama menampilkan satu dan sisanya lenyap tanpa jejak.
                    $lampiran = $berkas->where('field', $k)->map(fn ($b) => self::berkasRingkas($b))->values()->all();
                    // Aturan yang SAMA PERSIS dengan layar kandidat — satu
                    // sumber, bukan dua salinan. Lihat nilaiIsian().
                    // $k di sini ADALAH kunci bagian berulangnya (mis.
                    // "riwayat_sertifikasi") — itulah yang dicocokkan ke
                    // Bagian_Key di tabel berkas.
                    $isi = self::nilaiIsian($v, 0, self::pencariBerkas($berkas, $k));

                    return [
                        'key' => $k,
                        'label' => ucwords(str_replace(['_', '-'], ' ', $k)),
                        'nilai' => $isi['nilai'],
                        'baris' => $isi['baris'],
                        // Butir daftar dibawa apa adanya — lihat nilaiIsian().
                        'daftar' => $isi['daftar'],
                        'berkas' => $lampiran[0] ?? null,
                        'berkasList' => $lampiran,
                    ];
                })->values(),
                'berkas' => $berkas,
            ];
        });

        return ResponseHelper::success([
            'lamaran' => [
                'kode' => $lamaran->Kode,
                // Sumber yang sama dengan kartu worklist — kalau berbeda, satu
                // orang punya dua nama di dua layar yang saling bersebelahan.
                'pelamar' => self::namaResmiPerLamaran([(int) $realId])->get((int) $realId)
                    ?: ($lamaran->Pelamar ?: $lamaran->Created_By),
                'pelamarAkun' => $lamaran->Pelamar ?: null,
                'email' => $lamaran->EmailAkun,
                'posisi' => $lamaran->Posisi ?: $lamaran->Kategori,
                'program' => $lamaran->ProgramNama,
                'kategori' => $lamaran->Kategori,
                'status' => $lamaran->Status,
                'urutan' => (int) $lamaran->Urutan_Tahap,
                'totalTahap' => (int) $lamaran->Total_Tahap,
            ],
            'formulir' => $formulir,
            // SELURUH tahap kandidat ini, beserta hasil & berkasnya — dasar
            // "Progres Seleksi" yang bisa diklik dan "Hasil tahap sebelumnya".
            'tahap' => $this->ringkasTahapKandidat($tahapSemua),
            // Catatan UNTUK KANDIDAT per tahap — apa yang sudah (atau akan)
            // dibaca kandidat di portalnya, berikut sudah-tidaknya diumumkan.
            // Admin perlu melihatnya di tempat yang sama dengan catatan timnya.
            'catatanKandidat' => CatatanEksternal::siap()
                ? $tahapSemua
                    ->filter(fn ($t) => ($t->{CatatanEksternal::KOLOM} ?? null) !== null)
                    ->map(fn ($t) => [
                        'urutan' => (int) $t->Urutan,
                        'label' => $t->Label,
                        'html' => $t->Catatan_Eksternal_Html,
                        'at' => (string) ($t->Diputus_At ?? ''),
                        'oleh' => $t->Diputus_By,
                        'terbit' => (bool) $t->Hasil && self::terbitKeKandidat($t),
                    ])
                    ->values()
                : [],
        ], 'Profil kandidat');
    }

    /**
     * GET /api/v1/karir/lamaran/tahap/{id}/detail — isi SATU tahap kandidat,
     * baca-saja: keputusan & catatannya, rapor aktivitas (bentuk yang sama
     * persis dengan rapor tahap aktif — rapotTahap), dan berkas hasil tahap.
     *
     * Dipakai "Progres Seleksi" yang bisa diklik: admin kembali ke tahap mana
     * pun untuk membaca hasilnya dan membuka/mengunduh berkasnya lagi.
     */
    public function tahapDetail(string $id)
    {
        $realId = Hashids::decode($id)[0] ?? null;
        $t = $realId ? DB::table('N_WEB_CAREERS_Lamaran_Tahap')->where('Id_Lamaran_Tahap', $realId)->first() : null;
        if (! $t || ! $this->lamaranDalamLingkup((int) $t->Lamaran_Id)) {
            return ResponseHelper::error('Tahap tidak ditemukan.', 404);
        }

        // Sama dengan papanPelamar(), hanya untuk satu tahap: aktivitas (ikut
        // Token_Terbit), berkas tim & berkas kandidat per aktivitas — tanpa
        // arsip putaran yang diulang.
        $subs = \App\Support\Career\MetrikRekrutmen::aktivitasDenganTokenPer([(int) $realId])->get((int) $realId, collect());
        $subIds = collect($subs)->pluck('Id_Lamaran_Tahap_Tes')->all();
        $berkasSub = $subIds
            ? DB::table('N_WEB_CAREERS_Lamaran_Tahap_Berkas')->whereIn('Lamaran_Tahap_Tes_Id', $subIds)->whereNull('Ulang_Id')
                ->orderByDesc('Id_Lamaran_Tahap_Berkas')->get()->groupBy('Lamaran_Tahap_Tes_Id')
            : collect();
        $berkasKandidat = $subIds
            ? DB::table('N_WEB_CAREERS_Lamaran_Tes_Berkas')->whereIn('Lamaran_Tahap_Tes_Id', $subIds)->whereNull('Ulang_Id')
                ->orderBy('Id_Lamaran_Tes_Berkas')->get()->groupBy('Lamaran_Tahap_Tes_Id')
            : collect();

        // Berkas hasil TAHAP (bukan milik satu aktivitas) — mis. surat penawaran, hasil MCU.
        $berkasTahap = DB::table('N_WEB_CAREERS_Lamaran_Tahap_Berkas')
            ->where('Lamaran_Tahap_Id', $realId)
            ->whereNull('Lamaran_Tahap_Tes_Id')
            ->whereNull('Ulang_Id')
            ->orderByDesc('Id_Lamaran_Tahap_Berkas')
            ->get();

        $waktu = fn ($v) => $v ? (string) $v : null;

        return ResponseHelper::success([
            'tahap' => [
                'id' => Hashids::encode($t->Id_Lamaran_Tahap),
                'urutan' => (int) $t->Urutan,
                'label' => $t->Label,
                'tipe' => $t->Tipe_Tahap_Kode,
                'status' => $t->Status,
                'hasil' => $t->Hasil,
                'skor' => $t->Skor !== null ? (float) $t->Skor : null,
                'bypass' => ($t->Flag_Bypass ?? 'T') === 'Y',
                'waktuMulai' => $waktu($t->Waktu_Mulai ?? null),
                'waktuSelesai' => $waktu($t->Waktu_Selesai ?? null),
                'diputusBy' => $t->Diputus_By ?? null,
                'diputusAt' => $waktu($t->Diputus_At ?? null),
                // Catatan INTERNAL keputusan (tim saja) dan catatan UNTUK KANDIDAT.
                'catatan' => $t->Catatan ?? null,
                'catatanHtml' => $t->Catatan_Html ?? null,
                'catatanEksternal' => CatatanEksternal::siap() ? ($t->{CatatanEksternal::KOLOM} ?? null) : null,
                'alasan' => ($t->Rekomendasi_Alasan ?? null) ?: ($t->Alasan_Gugur ?? null),
            ],
            'tests' => self::rapotTahap($subs, $t->Urutan_Aktivitas ?? null, $berkasSub, $berkasKandidat),
            'berkas' => self::bentukBerkasAktivitas($berkasTahap),
        ], 'Detail tahap');
    }

    /**
     * Seluruh tahap SATU lamaran, berikut hasil & berkasnya — untuk "Progres
     * Seleksi" yang bisa diklik dan "Hasil tahap sebelumnya" di drawer.
     *
     * Berkas: unggahan TIM (Lamaran_Tahap_Berkas — per tahap maupun per
     * aktivitas) dan unggahan KANDIDAT per aktivitas (Lamaran_Tes_Berkas),
     * tanpa arsip putaran yang diulang (Ulang_Id). Berkas formulir tidak di
     * sini — sudah dibawa `formulir`.
     *
     * @return array<int, array<string, mixed>>
     */
    private function ringkasTahapKandidat(\Illuminate\Support\Collection $tahap): array
    {
        if ($tahap->isEmpty()) {
            return [];
        }
        $tahapIds = $tahap->pluck('Id_Lamaran_Tahap')->all();

        $subs = DB::table('N_WEB_CAREERS_Lamaran_Tahap_Tes')->whereIn('Lamaran_Tahap_Id', $tahapIds)
            ->get(['Id_Lamaran_Tahap_Tes', 'Lamaran_Tahap_Id', 'Label']);
        $labelSub = $subs->pluck('Label', 'Id_Lamaran_Tahap_Tes');
        $tahapSub = $subs->pluck('Lamaran_Tahap_Id', 'Id_Lamaran_Tahap_Tes');

        $berkasPer = [];
        $tim = DB::table('N_WEB_CAREERS_Lamaran_Tahap_Berkas')->whereIn('Lamaran_Tahap_Id', $tahapIds)->whereNull('Ulang_Id')
            ->orderBy('Id_Lamaran_Tahap_Berkas')->get();
        foreach ($tim as $b) {
            $berkasPer[(int) $b->Lamaran_Tahap_Id][] = self::bentukBerkasAktivitas([$b])[0] + [
                'sumber' => 'TIM',
                'aktivitas' => $b->Lamaran_Tahap_Tes_Id ? ($labelSub[$b->Lamaran_Tahap_Tes_Id] ?? null) : null,
            ];
        }
        if ($subs->isNotEmpty()) {
            $dariKandidat = DB::table('N_WEB_CAREERS_Lamaran_Tes_Berkas')->whereIn('Lamaran_Tahap_Tes_Id', $subs->pluck('Id_Lamaran_Tahap_Tes')->all())
                ->whereNull('Ulang_Id')->orderBy('Id_Lamaran_Tes_Berkas')->get();
            foreach ($dariKandidat as $b) {
                $berkasPer[(int) ($tahapSub[$b->Lamaran_Tahap_Tes_Id] ?? 0)][] = self::bentukBerkasKandidat([$b])[0] + [
                    'sumber' => 'KANDIDAT',
                    'aktivitas' => $labelSub[$b->Lamaran_Tahap_Tes_Id] ?? null,
                ];
            }
        }

        $waktu = fn ($v) => $v ? (string) $v : null;

        return $tahap->map(fn ($t) => [
            'id' => Hashids::encode($t->Id_Lamaran_Tahap),
            'urutan' => (int) $t->Urutan,
            'kode' => $t->Kode,
            'label' => $t->Label,
            'tipe' => $t->Tipe_Tahap_Kode,
            'status' => $t->Status,
            'hasil' => $t->Hasil,
            'skor' => $t->Skor !== null ? (float) $t->Skor : null,
            'bypass' => ($t->Flag_Bypass ?? 'T') === 'Y',
            'waktuMulai' => $waktu($t->Waktu_Mulai ?? null),
            'waktuSelesai' => $waktu($t->Waktu_Selesai ?? null),
            'diputusBy' => $t->Diputus_By ?? null,
            'diputusAt' => $waktu($t->Diputus_At ?? null),
            'berkas' => $berkasPer[(int) $t->Id_Lamaran_Tahap] ?? [],
        ])->values()->all();
    }

    /** Kategori yang boleh dilihat pengguna ini; null/kosong = semua. */
    private function lingkupKategori(): ?array
    {
        $izin = AksesService::kategoriDiizinkan(self::PAGE);

        return $izin ?: null;
    }

    /**
     * Lamaran ini dalam lingkup pengguna? Aturan yang sama dengan papan
     * worklist (detailProgram / detailLoker): kategori program & PIC loker.
     */
    private function lamaranDalamLingkup(int $lamaranId): bool
    {
        $izin = AksesService::kategoriDiizinkan(self::PAGE);
        $pic = AksesService::picDiizinkan(self::PAGE);

        return DB::table('N_WEB_CAREERS_Lamaran as l')
            ->join('N_WEB_CAREERS_Program as p', 'p.Id_Program', '=', 'l.Program_Id')
            ->leftJoin('N_WEB_CAREERS_Program_Posisi as x', 'x.Id_Program_Posisi', '=', 'l.Program_Posisi_Id')
            ->where('l.Id_Lamaran', $lamaranId)
            ->when($izin, fn ($w) => $w->whereIn('p.Kategori', $izin))
            ->when($pic !== null, fn ($w) => $w->whereIn('x.Pic_Kode_Karyawan', $pic ?: ['__tidak_ada__']))
            ->exists();
    }

    /** GET pratinjau 1 berkas (PDF/gambar/dll) untuk offcanvas/lightbox. */
    public function berkasFile(string $id)
    {
        $realId = Hashids::decode($id)[0] ?? null;
        $b = DB::table('N_WEB_CAREERS_Formulir_Berkas')->where('Id_Formulir_Berkas', $realId)->first();
        if (! $b) {
            abort(404);
        }

        // Berkas apply-form tersimpan di GCS (bucket PRIVAT) — akses lewat
        // SIGNED URL berumur pendek (redirect 302), bukan baca file lokal.
        if ($b->Path_File) {
            try {
                $gcs = Storage::disk(GcsBerkas::DISK);
                if ($gcs->exists($b->Path_File)) {
                    return redirect()->away($gcs->temporaryUrl($b->Path_File, now()->addMinutes(15)));
                }
            } catch (\Throwable $e) {
                Log::channel('web_career')->warning('Signed URL GCS gagal untuk berkas '.$b->Id_Formulir_Berkas.': '.$e->getMessage());
            }
        }

        // Fallback lokal (data lama / lingkungan dev tanpa GCS).
        foreach ([storage_path('app/'.$b->Path_File), public_path($b->Path_File), $b->Path_File] as $kandidat) {
            if ($kandidat && is_file($kandidat)) {
                return response()->file($kandidat, ['Content-Type' => $b->Mime ?: 'application/octet-stream']);
            }
        }

        abort(404, 'File tidak ditemukan di penyimpanan.');
    }

    /** GET /api/v1/lamaran/pengisian/{id} — admin melihat jawaban kandidat. */
    public function lihatPengisian(string $id)
    {
        $realId = Hashids::decode($id)[0] ?? null;
        $row = DB::table('N_WEB_CAREERS_Formulir_Pengisian')->where('Id_Formulir_Pengisian', $realId)->first();
        if (! $row) {
            return ResponseHelper::error('Pengisian tidak ditemukan.', 404);
        }

        return ResponseHelper::success([
            'komponen' => $row->Komponen_Kode,
            'jawaban' => json_decode($row->Jawaban_Json ?: '{}', true),
            'waktuKirim' => $row->Waktu_Kirim,
        ], 'Jawaban kandidat');
    }
}
