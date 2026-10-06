<?php

namespace App\Http\Controllers\Career\Lamaran;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Career\CareerLandingController;
use App\Http\Controllers\Controller;
use App\Support\Career\BerkasBaris;
use App\Support\Career\BerkasFormulir;
use App\Support\Career\FormulirSchema;
use App\Support\Career\GcsBerkas;
use App\Support\Career\KatalogPrefill;
use App\Support\Career\KelayakanLamaran;
use App\Support\Career\LamaranTargetValidator;
use App\Support\CareerShell;
use App\Support\Portal\DokumenPublik;
use App\Support\Portal\PenilaiWaktu;
use App\Support\Portal\Potret;
use App\Support\Portal\SajianBerkas;
use App\Support\Sinkron\Outbox;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Vinkla\Hashids\Facades\Hashids;

/**
 * WEB CAREER — Lamaran kandidat (zona luar).
 *
 * Yang DITULIS di sini hanya tabel MILIK kandidat — lamaran, isian formulir,
 * berkas di bucket karantina — beserta peristiwanya di Outbox, dalam SATU
 * transaksi. Seluruh keputusan seleksi (tahap, jadwal, kuota MPP, hasil)
 * lahir di zona dalam dan sampai ke sini sebagai POTRET (Support\Portal\Potret)
 * dan DOKUMEN (Support\Portal\DokumenPublik) yang hanya dibaca.
 */
class LamaranController extends Controller
{
    /**
     * Batas ukuran berkas lamaran bila isiannya tidak ditemukan di skema
     * (lihat BerkasFormulir::aturanUnggah).
     */
    private const MAKS_MB_APPLY = 2;

    public function __construct(private LamaranTargetValidator $targetValidator) {}

    /**
     * 422 yang MEMBAWA rincian berkas yang kurang — supaya layar bisa
     * mengosongkan tepat isian yang bermasalah.
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

    private static function akun(): int
    {
        return (int) session('career_auth.id');
    }

    private static function idDari(string $hash): ?int
    {
        $id = Hashids::decode($hash)[0] ?? null;

        return $id ? (int) $id : null;
    }

    /**
     * Label & nada status untuk layar. Potret membawa kata dari master zona
     * dalam; lamaran yang belum diproses (belum berpotret) disebut apa adanya.
     *
     * @return array{label: string, nada: string}
     */
    private static function statusTampil(string $status, ?array $potret): array
    {
        if ($potret && ! empty($potret['lamaran']['statusLabel'])) {
            return ['label' => (string) $potret['lamaran']['statusLabel'], 'nada' => (string) ($potret['lamaran']['statusNada'] ?? 'berjalan')];
        }

        return match (strtoupper($status)) {
            'BERJALAN' => ['label' => $potret ? 'Berjalan' : 'Sedang diproses', 'nada' => 'berjalan'],
            'LULUS' => ['label' => 'Diterima', 'nada' => 'lolos'],
            'GUGUR' => ['label' => 'Tidak lolos', 'nada' => 'gagal'],
            'MUNDUR' => ['label' => 'Mengundurkan diri', 'nada' => 'netral'],
            default => ['label' => $status ?: '—', 'nada' => 'berjalan'],
        };
    }

    // ═══════════════════════ LOWONGAN ═══════════════════════

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
     * Loker = PEMBUKAAN yang terbit & dalam masa berlaku, dikalikan posisi
     * program-nya. Satu kartu = satu (pembukaan × posisi). Semua dari salinan.
     */
    private function daftarLoker(): array
    {
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

        // Loker yang dimatikan (Flag_Aktif) tidak boleh tampil di katalog
        // portal, sama seperti di beranda.
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

    // ═══════════════════════ MELAMAR ═══════════════════════

    /**
     * POST /api/v1/lamaran — kandidat melamar. MULTIPART (field + berkas + foto).
     *
     * Validasi → berkas naik ke bucket KARANTINA → SATU transaksi: lamaran,
     * isian pendaftaran, daftar berkas, dan peristiwa Lamaran.Dikirim. Tahap
     * seleksi, penilaian syarat, kuota, dan surel dikerjakan zona dalam saat
     * memproses peristiwa itu; hasilnya kembali sebagai potret portal.
     */
    public function lamar(Request $request)
    {
        $userId = self::akun();
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
                // Format & ukuran tiap berkas diperiksa terhadap SKEMA isiannya.
                'berkas' => 'nullable|array|max:40',
                'berkas.*' => 'file',
                // Berkas milik BARIS bagian berulang (mis. sertifikat).
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

        $pembukaanId = self::idDari($data['pembukaanId']);
        $posisiId = self::idDari($data['posisiId']);
        if (! $pembukaanId || ! $posisiId) {
            return ResponseHelper::error('Data lamaran tidak valid.', 422);
        }

        // Pasangan pembukaan-posisi yang tidak berhubungan ditolak SEBELUM
        // berkas diunggah. Zona dalam memvalidasi ulang saat memproses.
        $target = $this->targetValidator->validasi($pembukaanId, $posisiId);
        if (! $target['ok']) {
            return ResponseHelper::error($target['pesan'], 422);
        }
        $program = $target['program'];
        $posisi = $target['posisi'];
        $pembukaan = $target['pembukaan'];

        $sudah = DB::table('N_WEB_CAREERS_Lamaran')
            ->where('Id_Users', $userId)
            ->where('Program_Id', $program->Id_Program)
            ->where('Program_Posisi_Id', $posisiId)
            ->exists();
        if ($sudah) {
            return ResponseHelper::error('Anda sudah melamar posisi ini.', 422);
        }

        // KELAYAKAN JALUR (aturan MT/REKRUTMEN + jeda, salinan master) —
        // umpan balik instan sebelum berkas diunggah. Penilaian akhir tetap di
        // zona dalam, yang juga mengenal riwayat sebelum dua zona.
        $kelayakan = (new KelayakanLamaran)->cek($userId, $program->Kategori);
        if (! $kelayakan['boleh']) {
            return ResponseHelper::error($kelayakan['alasan'], 422);
        }

        // jawaban dikirim sebagai JSON string di multipart.
        $jawaban = $request->input('jawaban');
        if (is_string($jawaban)) {
            $jawaban = json_decode($jawaban, true) ?: [];
        }
        $jawaban = is_array($jawaban) ? $jawaban : [];
        $gugurAlasan = filter_var($request->input('gugur'), FILTER_VALIDATE_BOOLEAN) ? ($data['alasan'] ?? 'Tidak memenuhi syarat wajib.') : null;

        // ── BERKAS DIPERIKSA SEBELUM SATU PUN NAIK KE BUCKET ───────────────
        // Skemanya dari sumber yang SAMA dengan halaman lamar, jadi yang
        // dituntut server persis yang tampil di layar kandidat.
        $form = FormulirSchema::pendaftaranProgram($program->Alur_Kode ?? null, (string) ($program->Kategori ?? ''));
        $skema = $form['schema'] ?? null;

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
        // jawaban — HARUS ikut terkirim.
        $kurang = BerkasFormulir::kurang(
            $skema,
            $jawaban,
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

        $nama = (string) session('career_auth.nama', 'Kandidat');
        $now = now();
        $gcs = app(GcsBerkas::class);
        $folder = $gcs->folderKandidat($now->format('Y'), $now->format('m'), $now->format('d'), $nama);
        $terunggah = [];
        $berkasMeta = [];

        try {
            foreach ($masuk as $m) {
                $file = $m['file'];
                $ext = $gcs->normalkanExt(strtolower($file->getClientOriginalExtension() ?: $file->extension()));
                $konten = $file->getContent(); // getContent(), bukan getRealPath() yang bisa kosong di Apache
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
                    'bagian' => null,
                    'baris' => null,
                    'nama' => 'verifikasi.jpg',
                    'path' => $path,
                    'ext' => 'jpg',
                    'mime' => $f->getMimeType() ?: 'image/jpeg',
                    'ukuran' => strlen($konten),
                    'hash' => hash('sha256', $konten),
                ];
            }
        } catch (\Throwable $e) {
            $gcs->hapus($terunggah);
            Log::error('[APPLY] unggah berkas gagal: '.$e->getMessage());

            return ResponseHelper::error('Gagal mengunggah berkas: '.Str::limit($e->getMessage(), 140), 422);
        }

        $alur = $program->Alur_Kode
            ? DB::table('N_WEB_CAREERS_Master_Alur')->where('Kode', $program->Alur_Kode)->first(['Id_Master_Alur'])
            : null;
        $tahapAlur = $alur
            ? DB::table('N_WEB_CAREERS_Master_Alur_Tahap')->where('Master_Alur_Id', $alur->Id_Master_Alur)->orderBy('Urutan')->get(['Id_Master_Alur_Tahap'])
            : collect();

        $kode = 'LMR-'.strtoupper(Str::random(8));

        try {
            DB::transaction(function () use ($kode, $userId, $nama, $now, $program, $posisi, $pembukaan, $alur, $tahapAlur, $form, $jawaban, $gugurAlasan, $berkasMeta, $request, $pembukaanId, $posisiId) {
                $lamaranId = (int) DB::table('N_WEB_CAREERS_Lamaran')->insertGetId([
                    'Kode' => $kode,
                    'Id_Users' => $userId,
                    'Kategori' => $program->Kategori,
                    'Program_Id' => $program->Id_Program,
                    'Program_Posisi_Id' => $posisi->Id_Program_Posisi,
                    'Mpp_Ref' => $posisi->Mpp_Ref ?? null,
                    'Pembukaan_Id' => $pembukaan->Id_Pembukaan,
                    'Program_Batch_Id' => $pembukaan->Program_Batch_Id ?? null,
                    'Master_Alur_Id' => $alur->Id_Master_Alur ?? null,
                    'Urutan_Tahap' => 1,
                    'Total_Tahap' => $tahapAlur->count() ?: null,
                    // BERJALAN sejak dikirim: aturan "satu lamaran aktif" langsung
                    // berlaku. Zona dalam yang memutus kelanjutannya.
                    'Status' => 'BERJALAN',
                    'Waktu_Lamar' => $now,
                    'Created_At' => $now, 'Created_By' => $nama, 'Created_By_Id' => $userId,
                    'Updated_At' => $now, 'Updated_By' => $nama, 'Updated_By_Id' => $userId,
                ], 'Id_Lamaran');

                $kodeIsian = 'FLL-'.strtoupper(Str::random(8));
                $pengisianId = (int) DB::table('N_WEB_CAREERS_Formulir_Pengisian')->insertGetId([
                    'Kode' => $kodeIsian,
                    'Id_Users' => $userId,
                    'Lamaran_Id' => $lamaranId,
                    'Master_Formulir_Id' => (int) ($form['realId'] ?? 0),
                    'Komponen_Kode' => $form['komponen'] ?? null,
                    'Sumber' => 'PENDAFTARAN',
                    'Master_Alur_Tahap_Id' => $tahapAlur->first()->Id_Master_Alur_Tahap ?? null,
                    'Program_Id' => $program->Id_Program,
                    'Program_Batch_Id' => $pembukaan->Program_Batch_Id ?? null,
                    'Jawaban_Json' => json_encode($jawaban ?: (object) [], JSON_UNESCAPED_UNICODE),
                    'Langkah_Terakhir' => 0,
                    'Status' => 'TERKIRIM',
                    'Waktu_Kirim' => $now,
                    'Ip_Pengirim' => Str::limit((string) $request->ip(), 45, ''),
                    'Created_At' => $now, 'Created_By' => $nama, 'Created_By_Id' => $userId,
                    'Updated_At' => $now, 'Updated_By' => $nama, 'Updated_By_Id' => $userId,
                ] + FormulirSchema::kolomSnapshotInsert($form), 'Id_Formulir_Pengisian');

                foreach ($berkasMeta as $i => $b) {
                    DB::table('N_WEB_CAREERS_Formulir_Berkas')->insert([
                        'Formulir_Pengisian_Id' => $pengisianId,
                        'Id_Users' => $userId,
                        'Field_Key' => $b['field'],
                        'Bagian_Key' => $b['bagian'],
                        'Baris_Index' => $b['baris'],
                        'Urutan' => $i + 1,
                        'Nama_Asli' => Str::limit((string) $b['nama'], 250, ''),
                        'Path_File' => $b['path'],
                        'Ukuran_Byte' => $b['ukuran'],
                        'Mime' => $b['mime'],
                        'Ekstensi' => $b['ext'],
                        'Hash_File' => $b['hash'],
                        'Status_Verifikasi' => 'BELUM',
                        'Waktu_Unggah' => $now,
                        'Created_At' => $now, 'Created_By' => $nama, 'Created_By_Id' => $userId,
                        'Updated_At' => $now, 'Updated_By' => $nama, 'Updated_By_Id' => $userId,
                    ]);
                }

                Outbox::tulis(Outbox::LAMARAN_DIKIRIM, 'Lamaran.Dikirim:'.$kode, $userId, [
                    'kode' => $kode,
                    'akun' => ['id_publik' => $userId, 'nama' => $nama, 'email' => session('career_auth.email')],
                    'pembukaan_id' => $pembukaanId,
                    'program_id' => (int) $program->Id_Program,
                    'posisi_id' => $posisiId,
                    'kategori' => $program->Kategori,
                    'formulir' => [
                        'pengisian_kode' => $kodeIsian,
                        'formulir_kode' => $form['kode'] ?? null,
                        'versi' => $form['versi'] ?? null,
                    ],
                    'jawaban' => $jawaban ?: (object) [],
                    'gugur_alasan' => $gugurAlasan,
                    'berkas' => $berkasMeta,
                    'waktu_lamar' => $now->toIso8601String(),
                    'ip' => $request->ip(),
                ], $nama);
            });
        } catch (\Throwable $e) {
            // Lamaran tidak tercatat → berkas yang sempat naik dibuang (tidak ada yatim).
            $gcs->hapus($terunggah);
            Log::error('[APPLY] gagal mencatat lamaran: '.$e->getMessage());

            return ResponseHelper::error('Gagal memproses lamaran.', 500);
        }

        Log::info("[APPLY] {$kode} tercatat — ".count($berkasMeta).' berkas.');

        // processId = kode lamaran: apply-status membaca status peristiwanya.
        return ResponseHelper::success([
            'processId' => $kode,
            'mode' => 'ANTRIAN',
        ], 'Lamaran diterima & sedang diproses.', 202);
    }

    /**
     * GET /api/v1/lamaran/apply-status/{processId} — dipoll halaman lamar.
     *
     *   MENUNGGU  peristiwa Lamaran.Dikirim belum diproses zona dalam
     *   SELESAI   sudah diproses — status lamaran ikut dikirim
     *   GAGAL     ditolak zona dalam (Hasil_Keterangan = alasannya)
     *
     * Layar berhenti mem-poll sesudah 45 detik dan mengarahkan kandidat ke
     * "Lamaran Saya" — lamarannya sudah aman tercatat.
     */
    public function applyStatus(string $processId)
    {
        $userId = self::akun();
        $l = DB::table('N_WEB_CAREERS_Lamaran')->where('Kode', $processId)->where('Id_Users', $userId)->first();
        if (! $l) {
            return ResponseHelper::error('Proses tidak ditemukan.', 404);
        }

        $ev = Outbox::status('Lamaran.Dikirim:'.$l->Kode);
        $status = match ($ev->Status ?? null) {
            'DIPROSES' => 'SELESAI',
            'DITOLAK' => 'GAGAL',
            default => 'MENUNGGU',
        };

        $out = [
            'status' => $status,
            'pesan' => $status === 'GAGAL' ? (($ev->Hasil_Keterangan ?? null) ?: 'Lamaran tidak dapat diproses.') : null,
        ];

        if ($status === 'SELESAI') {
            $potret = Potret::lamaran($l->Kode, $userId);
            $tahapan = collect($potret['tahapan'] ?? []);
            $kini = $tahapan->firstWhere('urutan', (int) $l->Urutan_Tahap);

            $out['lamaran'] = [
                'id' => Hashids::encode($l->Id_Lamaran),
                'kode' => $l->Kode,
                'status' => $l->Status,
                'hasilAkhir' => $l->Hasil_Akhir,
                'gugurDi' => $potret['lamaran']['gugurDi'] ?? $l->Gugur_Di_Tahap,
                'alasanGugur' => $potret['lamaran']['alasanGugur'] ?? $l->Alasan_Gugur,
                'tahap' => (int) $l->Urutan_Tahap,
                'totalTahap' => (int) $l->Total_Tahap,
                'tahapLabel' => $kini['label'] ?? null,
                // true → belum ada tahap yang diputus lolos; jangan ucapkan selamat.
                'menungguKeputusan' => $l->Status === 'BERJALAN' && ! $tahapan->contains(fn ($t) => ($t['hasil'] ?? null) === 'LULUS'),
            ];
        }

        return ResponseHelper::success($out, 'Status lamaran');
    }

    /**
     * DELETE /api/v1/lamaran/{id} — kandidat membatalkan lamarannya sendiri.
     *
     * Peristiwa Lamaran.Dibatalkan dicatat bersama penghapusan barisnya di sini;
     * zona dalam membatalkan salinannya (dan melepas kuota) saat memprosesnya.
     */
    public function batalkan(string $id)
    {
        $userId = self::akun();
        if (! $userId) {
            return ResponseHelper::error('Sesi tidak sah.', 401);
        }

        $realId = self::idDari($id);
        $lamaran = $realId
            ? DB::table('N_WEB_CAREERS_Lamaran')->where('Id_Lamaran', $realId)->where('Id_Users', $userId)->first()
            : null;
        if (! $lamaran) {
            return ResponseHelper::error('Lamaran tidak ditemukan.', 404);
        }
        if ($lamaran->Status !== 'BERJALAN') {
            return ResponseHelper::error('Lamaran yang sudah selesai tidak bisa dibatalkan.', 409);
        }

        try {
            DB::transaction(function () use ($lamaran, $userId) {
                Outbox::tulis(Outbox::LAMARAN_DIBATALKAN, 'Lamaran.Dibatalkan:'.$lamaran->Kode, $userId, [
                    'kode' => $lamaran->Kode,
                    'akun' => ['id_publik' => $userId],
                ], session('career_auth.nama'));

                $pengisianIds = DB::table('N_WEB_CAREERS_Formulir_Pengisian')
                    ->where('Lamaran_Id', $lamaran->Id_Lamaran)->pluck('Id_Formulir_Pengisian');
                if ($pengisianIds->isNotEmpty()) {
                    DB::table('N_WEB_CAREERS_Formulir_Berkas')->whereIn('Formulir_Pengisian_Id', $pengisianIds)->delete();
                }
                DB::table('N_WEB_CAREERS_Formulir_Jawaban_Index')->where('Lamaran_Id', $lamaran->Id_Lamaran)->delete();
                DB::table('N_WEB_CAREERS_Formulir_Pengisian')->where('Lamaran_Id', $lamaran->Id_Lamaran)->delete();
                DB::table('N_WEB_CAREERS_Formulir_Draf')->where('Lamaran_Id', $lamaran->Id_Lamaran)->delete();
                DB::table('N_WEB_CAREERS_Lamaran_Tes_Berkas')->where('Lamaran_Id', $lamaran->Id_Lamaran)->delete();
                DB::table('N_WEB_CAREERS_Lamaran')->where('Id_Lamaran', $lamaran->Id_Lamaran)->delete();
            });

            Log::info("[LAMARAN] {$lamaran->Kode} dibatalkan oleh kandidat #{$userId}");

            return ResponseHelper::success(null, 'Lamaran dibatalkan.');
        } catch (\Throwable $e) {
            Log::error('Gagal membatalkan lamaran: '.$e->getMessage());

            return ResponseHelper::error('Gagal membatalkan lamaran.', 500);
        }
    }

    // ═══════════════════════ PORTAL ═══════════════════════

    /** /kandidat/portal — lamaran milik kandidat yang sedang login. */
    public function portalIndex()
    {
        $lamaran = $this->lamaranSaya();

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
        $userId = self::akun();

        $rows = DB::table('N_WEB_CAREERS_Lamaran as l')
            ->join('N_WEB_CAREERS_Program as p', 'p.Id_Program', '=', 'l.Program_Id')
            ->leftJoin('N_WEB_CAREERS_Program_Posisi as x', 'x.Id_Program_Posisi', '=', 'l.Program_Posisi_Id')
            ->where('l.Id_Users', $userId)
            ->orderByDesc('l.Id_Lamaran')
            ->select('l.*', 'p.Nama as ProgramNama', 'p.Warna', 'x.Posisi', 'x.Lokasi')
            ->get();

        $potret = $rows->isNotEmpty() ? Potret::milikAkun($userId) : [];

        // KARTU sama persis dengan landing — dibangun dari salinan yang sama.
        $landing = app(CareerLandingController::class);
        $petaMt = collect($landing->dbMtCards())->keyBy('pembukaanId');
        $petaRek = collect($landing->dbLowonganCards())->keyBy(fn ($c) => ($c['pembukaanId'] ?? '').'|'.($c['posisiId'] ?? ''));

        return $rows->map(function ($l) use ($petaMt, $petaRek, $potret) {
            $pbHash = $l->Pembukaan_Id ? Hashids::encode($l->Pembukaan_Id) : null;
            $posHash = $l->Program_Posisi_Id ? Hashids::encode($l->Program_Posisi_Id) : null;
            $p = $potret[$l->Kode] ?? null;
            $tampil = self::statusTampil((string) $l->Status, $p);

            return [
                'id' => Hashids::encode($l->Id_Lamaran),
                'kode' => $l->Kode,
                'program' => $l->ProgramNama,
                'posisi' => $l->Posisi,
                'lokasi' => $l->Lokasi,
                'warna' => $l->Warna,
                'kategori' => $l->Kategori,
                'status' => $l->Status,
                'statusLabel' => $tampil['label'],
                'statusNada' => $tampil['nada'],
                'hasilAkhir' => $l->Hasil_Akhir,
                'urutanTahap' => (int) $l->Urutan_Tahap,
                'totalTahap' => (int) $l->Total_Tahap,
                'gugurDi' => $p['lamaran']['gugurDi'] ?? $l->Gugur_Di_Tahap,
                'waktuLamar' => $l->Waktu_Lamar,
                // Stepper "PROGRES SELEKSI" — dari potret; kosong selama lamaran
                // belum diproses zona dalam.
                'tahapan' => array_values($p['tahapan'] ?? []),
                'menungguPotret' => $p === null,
                'kartu' => $l->Kategori === 'MT' ? ($petaMt[$pbHash] ?? null) : ($petaRek[$pbHash.'|'.$posHash] ?? null),
            ];
        })->values()->all();
    }

    /**
     * /kandidat/lamaran/kode/{kode} — tautan surel dari zona dalam, yang hanya
     * mengenal KODE lamaran (bukan id publiknya). Diteruskan ke detail bila
     * lamaran itu milik akun yang masuk; selain itu ke daftar lamaran.
     */
    public function bukaKode(Request $request, string $kode)
    {
        $id = DB::table('N_WEB_CAREERS_Lamaran')->where('Kode', $kode)->where('Id_Users', self::akun())->value('Id_Lamaran');
        $dari = (string) $request->query('dari', '');

        return redirect(($id ? '/kandidat/lamaran/'.Hashids::encode($id) : '/kandidat/portal')
            .(preg_match('/^[a-z]{1,20}$/', $dari) ? '?dari='.$dari : ''));
    }

    /** /kandidat/lamaran/{id} — detail satu lamaran (dari potret portal). */
    public function portalDetail(string $id)
    {
        $userId = self::akun();
        $realId = self::idDari($id);

        $lamaran = $realId ? DB::table('N_WEB_CAREERS_Lamaran as l')
            ->join('N_WEB_CAREERS_Program as p', 'p.Id_Program', '=', 'l.Program_Id')
            ->leftJoin('N_WEB_CAREERS_Program_Posisi as x', 'x.Id_Program_Posisi', '=', 'l.Program_Posisi_Id')
            ->leftJoin('N_WEB_CAREERS_Program_Batch as b', 'b.Id_Program_Batch', '=', 'l.Program_Batch_Id')
            ->leftJoin('N_WEB_CAREERS_Pembukaan as pb', 'pb.Id_Pembukaan', '=', 'l.Pembukaan_Id')
            ->where('l.Id_Lamaran', $realId)
            ->where('l.Id_Users', $userId)
            ->select('l.*', 'p.Nama as ProgramNama', 'p.Kategori as ProgramKategori', 'p.Penyelenggara',
                'x.Posisi', 'x.Lokasi', 'x.Departemen', 'b.Nama as BatchNama', 'pb.Kode as PembukaanKode')
            ->first() : null;

        if (! $lamaran) {
            abort(404);
        }

        $potret = Potret::lamaran($lamaran->Kode, $userId);
        // Bagian yang bergantung waktu (jendela ujian, batas) dihitung SEKARANG.
        $detail = $potret ? PenilaiWaktu::segarkan($potret['detail'] ?? []) : [];
        $ring = $potret['lamaran'] ?? [];
        $tampil = self::statusTampil((string) $lamaran->Status, $potret);

        // URL detail lowongan/program publik (tombol "Lihat Detail Lowongan").
        $lowonganUrl = null;
        if ($lamaran->PembukaanKode) {
            $lowonganUrl = $lamaran->ProgramKategori === 'MT'
                ? '/karir/landing-page/mt/PB-'.$lamaran->PembukaanKode
                : '/karir/landing-page/lowongan/PB-'.$lamaran->PembukaanKode.'-'.$lamaran->Program_Posisi_Id;
        }

        // KARTU lowongan — sumber sama dengan landing (salinan).
        $kartu = null;
        try {
            $landing = app(CareerLandingController::class);
            $pbHash = $lamaran->Pembukaan_Id ? Hashids::encode($lamaran->Pembukaan_Id) : null;
            if ($lamaran->ProgramKategori === 'MT') {
                $kartu = collect($landing->dbMtCards())->firstWhere('pembukaanId', $pbHash);
            } else {
                $posHash = $lamaran->Program_Posisi_Id ? Hashids::encode($lamaran->Program_Posisi_Id) : null;
                $kartu = collect($landing->dbLowonganCards())->first(fn ($c) => ($c['pembukaanId'] ?? null) === $pbHash && ($c['posisiId'] ?? null) === $posHash);
            }
        } catch (\Throwable $e) {
            $kartu = null;
        }

        $akun = DB::table('N_WEB_CAREERS_Users')->where('Id_Users', $userId)->first(['Nama', 'Email', 'No_Hp', 'NIK']);

        return Inertia::render('Career/portal/LamaranDetail', CareerShell::props('/kandidat/portal', 'Detail Lamaran', [
            'lamaran' => [
                'id' => $id,
                'kode' => $lamaran->Kode,
                'program' => $lamaran->ProgramNama,
                'kategori' => $lamaran->ProgramKategori,
                'penyelenggara' => $ring['penyelenggara'] ?? $lamaran->Penyelenggara,
                'posisi' => $lamaran->Posisi,
                'lokasi' => $lamaran->Lokasi,
                'departemen' => $lamaran->Departemen,
                'batch' => $ring['batch'] ?? $lamaran->BatchNama,
                'status' => $lamaran->Status,
                'statusLabel' => $tampil['label'],
                'statusNada' => $tampil['nada'],
                'urutanTahap' => (int) $lamaran->Urutan_Tahap,
                'totalTahap' => (int) $lamaran->Total_Tahap,
                'hasilAkhir' => $lamaran->Hasil_Akhir,
                'gugurDi' => $ring['gugurDi'] ?? $lamaran->Gugur_Di_Tahap,
                'alasanGugur' => $ring['alasanGugur'] ?? $lamaran->Alasan_Gugur,
                'waktuLamar' => $lamaran->Waktu_Lamar ? (string) $lamaran->Waktu_Lamar : null,
                // Lamaran sudah tercatat tapi belum diproses zona dalam — layar
                // menampilkan "sedang diproses", bukan tahapan kosong.
                'menungguPotret' => $potret === null,
            ],
            'tahap' => array_values($detail['tahap'] ?? []),
            'tugas' => $detail['tugas'] ?? null,
            // Objek, bukan array: prop `konteks` di Vue bertipe Object.
            'konteks' => (object) ($detail['konteks'] ?? []),
            'formulir' => $this->formulirTerkirim((int) $lamaran->Id_Lamaran, '/kandidat/lamaran/berkas/file/', (array) ($potret['tahapFormulir'] ?? [])),
            'lowonganUrl' => $lowonganUrl,
            'kartu' => $kartu,
            // Prefill field "terisi otomatis": akun dari tabel akun; data
            // pendidikan dari formulir pendaftaran lamaran ini (dihitung zona
            // dalam, dibawa potret). Disaring KatalogPrefill — satu daftar kunci.
            'profil' => KatalogPrefill::saring(KatalogPrefill::TAHAP, [
                'nama' => $akun->Nama ?? session('career_auth.nama'),
                'email' => $akun->Email ?? session('career_auth.email'),
                'hp' => $akun->No_Hp ?? null,
                'nik' => $akun->NIK ?? null,
                'posisi' => $lamaran->Posisi ?? null,
                ...array_intersect_key(
                    (array) ($potret['prefill'] ?? []),
                    array_flip(['kampus', 'tahunLulus', 'tglLahir', 'jkel', 'jurusan', 'jenjang', 'ipk', 'statusStudi', 'semester']),
                ),
            ]),
        ]));
    }

    // ═══════════════════════ BERKAS AKTIVITAS (tes, MCU mandiri) ═══════════════════════

    /**
     * Aktivitas milik kandidat yang login — dibuktikan potretnya.
     *
     * @return array{id: int, kode: string, lamaranId: int, isi: array, putaran: int}|null
     */
    private function aktivitasMilik(string $hash): ?array
    {
        $userId = self::akun();
        $realId = self::idDari($hash);
        $temu = $realId && $userId ? Potret::cari($userId, 'aktivitas', $realId) : null;
        if (! $temu) {
            return null;
        }

        $lamaranId = DB::table('N_WEB_CAREERS_Lamaran')->where('Kode', $temu['kode'])->where('Id_Users', $userId)->value('Id_Lamaran');

        return $lamaranId ? [
            'id' => $realId,
            'kode' => $temu['kode'],
            'lamaranId' => (int) $lamaranId,
            'isi' => $temu['isi'],
            'putaran' => max(1, (int) ($temu['isi']['putaran'] ?? 1)),
        ] : null;
    }

    /** Pesan penolak unggah/kirim/hapus, atau null bila aktivitasnya masih menerima berkas. */
    private static function galatAktivitas(array $akt): ?string
    {
        $isi = $akt['isi'];
        if (empty($isi['unggah'])) {
            return 'Aktivitas ini tidak meminta unggahan berkas.';
        }
        if (empty($isi['terbuka'])) {
            return ($isi['alasanTutup'] ?? null) ?: 'Aktivitas ini sudah selesai — berkas tidak bisa diubah lagi.';
        }
        if (PenilaiWaktu::lewat($isi['unggah']['batas'] ?? null)) {
            return 'Batas unggah berkas aktivitas ini sudah lewat. Hubungi tim rekrutmen bila perlu diperpanjang.';
        }

        return null;
    }

    private function barisTes(array $akt)
    {
        return DB::table('N_WEB_CAREERS_Lamaran_Tes_Berkas')
            ->where('Lamaran_Tahap_Tes_Id', $akt['id'])
            ->where('Id_Users', self::akun())
            ->where('Putaran', $akt['putaran'])
            ->whereNull('Ulang_Id');
    }

    /** Bentuk satu berkas kandidat untuk dikirim ke layar. */
    private static function bentukTesBerkas(object $b): array
    {
        $ext = strtolower((string) ($b->Ext ?: pathinfo((string) $b->Nama_File, PATHINFO_EXTENSION)));

        return [
            'id' => Hashids::encode($b->Id_Lamaran_Tes_Berkas),
            'nama' => $b->Nama_File,
            'ext' => $ext,
            'ukuran' => (int) $b->Ukuran,
            'isPdf' => $ext === 'pdf' || $b->Mime === 'application/pdf',
            'isImage' => in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true),
            'url' => route('career.portal.tes.berkas.file', ['id' => Hashids::encode($b->Id_Lamaran_Tes_Berkas)]),
            // Sudah ikut dinyatakan lengkap → tombol hapus disembunyikan tepat
            // pada berkas itu, bukan seluruh daftar.
            'terkunci' => ! empty($b->Terkirim_At),
            'terkirim' => ($b->Terkirim_At ?? null) ? (string) $b->Terkirim_At : null,
        ];
    }

    /** GET daftar berkas yang SUDAH diunggah kandidat untuk satu aktivitas. */
    public function tesBerkas(string $id)
    {
        $akt = $this->aktivitasMilik($id);
        if (! $akt) {
            return ResponseHelper::error('Aktivitas tidak ditemukan.', 404);
        }

        $rows = $this->barisTes($akt)->orderBy('Id_Lamaran_Tes_Berkas')->get();

        return ResponseHelper::success($rows->map(fn ($b) => self::bentukTesBerkas($b))->all(), 'Berkas aktivitas');
    }

    /**
     * POST unggah berkas kandidat untuk sebuah aktivitas.
     *
     * Format & ukuran dari aturan aktivitas ini (potret), divalidasi di server —
     * layar bisa dilewati.
     */
    public function tesBerkasUnggah(Request $request, string $id)
    {
        $akt = $this->aktivitasMilik($id);
        if (! $akt) {
            return ResponseHelper::error('Aktivitas tidak ditemukan.', 404);
        }
        if ($galat = self::galatAktivitas($akt)) {
            return ResponseHelper::error($galat, 409);
        }

        // SUDAH DIKIRIM → UNGGAH DITUTUP: yang dinilai tim harus persis yang
        // dinyatakan lengkap oleh kandidat.
        if ($this->barisTes($akt)->whereNotNull('Terkirim_At')->exists()) {
            return ResponseHelper::error(
                'Berkas untuk aktivitas ini sudah kamu kirim dan sedang dinilai tim — '
                .'tidak bisa ditambah atau diubah lagi. Hubungi tim rekrutmen bila ada yang perlu diperbaiki.',
                409,
            );
        }

        $aturan = $akt['isi']['unggah'];
        $format = array_values(array_filter(array_map('strtolower', (array) ($aturan['format'] ?? ['pdf']))));
        $maksMb = max(1, (int) ($aturan['maksMb'] ?? 5));

        $data = $request->validate([
            'berkas' => 'required|file|mimes:'.implode(',', $format).'|max:'.($maksMb * 1024),
        ], [
            'berkas.mimes' => 'Hanya menerima berkas '.implode(', ', $format).'.',
            'berkas.max' => "Ukuran berkas melebihi {$maksMb} MB.",
        ]);

        $file = $data['berkas'];
        $gcs = app(GcsBerkas::class);
        $now = now();
        $nama = (string) session('career_auth.nama');

        try {
            $konten = $file->getContent();
            $ext = strtolower($file->getClientOriginalExtension() ?: $file->extension());
            $folder = $gcs->folderTahap($now->format('Y'), $now->format('m'), $now->format('d'), $nama ?: 'kandidat');
            $path = $gcs->unggahUnik($folder, (string) ($akt['isi']['label'] ?? 'aktivitas'), $ext, $konten);
        } catch (\Throwable $e) {
            Log::error('[TES-BERKAS] unggah gagal: '.$e->getMessage());

            return ResponseHelper::error('Berkas gagal diunggah: '.Str::limit($e->getMessage(), 140), 500);
        }

        $baruId = DB::table('N_WEB_CAREERS_Lamaran_Tes_Berkas')->insertGetId([
            'Lamaran_Tahap_Tes_Id' => $akt['id'],
            'Lamaran_Id' => $akt['lamaranId'],
            'Id_Users' => self::akun(),
            'Nama_File' => Str::limit((string) $file->getClientOriginalName(), 290, ''),
            'Path_File' => $path,
            'Mime' => $file->getMimeType(),
            'Ext' => $ext,
            'Ukuran' => strlen($konten),
            'Putaran' => $akt['putaran'],
            'Created_At' => $now,
            'Created_By' => $nama,
            'Created_By_Id' => self::akun(),
        ], 'Id_Lamaran_Tes_Berkas');

        $baris = DB::table('N_WEB_CAREERS_Lamaran_Tes_Berkas')->where('Id_Lamaran_Tes_Berkas', $baruId)->first();

        return ResponseHelper::success(self::bentukTesBerkas($baris), 'Berkas terunggah.');
    }

    /**
     * PATCH kandidat menyatakan berkasnya SUDAH LENGKAP untuk aktivitas ini.
     *
     * Barulah di sini berkasnya menyeberang: peristiwa Berkas.Diunggah membawa
     * daftar berkas yang dinyatakan lengkap; zona dalam memindainya dari bucket
     * karantina sebelum menilainya.
     */
    public function tesBerkasKirim(Request $request, string $id)
    {
        $akt = $this->aktivitasMilik($id);
        if (! $akt) {
            return ResponseHelper::error('Aktivitas tidak ditemukan.', 404);
        }
        if ($galat = self::galatAktivitas($akt)) {
            return ResponseHelper::error($galat, 409);
        }

        $userId = self::akun();
        $now = now();

        try {
            $jumlah = DB::transaction(function () use ($akt, $userId, $now, $request) {
                $rows = $this->barisTes($akt)->lockForUpdate()->orderBy('Id_Lamaran_Tes_Berkas')->get();
                if ($rows->isEmpty()) {
                    throw new \DomainException('Belum ada berkas yang diunggah. Unggah dulu, baru tekan kirim.');
                }
                if ($rows->contains(fn ($b) => ! empty($b->Terkirim_At))) {
                    throw new \DomainException('Berkas aktivitas ini sudah kamu kirim.');
                }

                DB::table('N_WEB_CAREERS_Lamaran_Tes_Berkas')
                    ->whereIn('Id_Lamaran_Tes_Berkas', $rows->pluck('Id_Lamaran_Tes_Berkas'))
                    ->update(['Terkirim_At' => $now]);

                Outbox::tulis(Outbox::BERKAS_DIUNGGAH, "Berkas.Diunggah:{$akt['id']}:{$akt['putaran']}:{$userId}", $userId, [
                    'kode' => $akt['kode'],
                    'akun' => ['id_publik' => $userId],
                    'aktivitas_id' => $akt['id'],
                    'putaran' => $akt['putaran'],
                    'dikirim_at' => $now->toIso8601String(),
                    'ip' => $request->ip(),
                    'berkas' => $rows->map(fn ($b) => [
                        'id_publik' => (int) $b->Id_Lamaran_Tes_Berkas,
                        'nama' => $b->Nama_File,
                        'path' => $b->Path_File,
                        'mime' => $b->Mime,
                        'ext' => $b->Ext,
                        'ukuran' => (int) $b->Ukuran,
                    ])->values()->all(),
                ], session('career_auth.nama'));

                return $rows->count();
            });
        } catch (\DomainException $e) {
            return ResponseHelper::error($e->getMessage(), $e->getMessage() === 'Berkas aktivitas ini sudah kamu kirim.' ? 409 : 422);
        }

        return ResponseHelper::success(
            ['waktu' => $now->toDateTimeString(), 'jumlah' => $jumlah],
            "Berkas kamu sudah dikirim ({$jumlah} berkas). Tim rekrutmen akan menilainya."
        );
    }

    /** DELETE berkas kandidat — selama aktivitasnya masih menerima & belum dikirim. */
    public function tesBerkasHapus(string $id)
    {
        $userId = self::akun();
        $realId = self::idDari($id);
        $b = $realId
            ? DB::table('N_WEB_CAREERS_Lamaran_Tes_Berkas')->where('Id_Lamaran_Tes_Berkas', $realId)->where('Id_Users', $userId)->first()
            : null;
        if (! $b) {
            return ResponseHelper::error('Berkas tidak ditemukan.', 404);
        }

        // Sudah ikut dinyatakan lengkap → bahan penilaian, tidak bisa ditarik.
        if ($b->Terkirim_At) {
            return ResponseHelper::error(
                'Berkas ini sudah kamu kirim dan sedang dinilai tim — tidak bisa dihapus lagi. '
                .'Hubungi tim rekrutmen bila ada yang perlu diperbaiki.',
                409,
            );
        }

        $akt = $this->aktivitasMilik(Hashids::encode($b->Lamaran_Tahap_Tes_Id));
        if ($akt && ($galat = self::galatAktivitas($akt))) {
            return ResponseHelper::error($galat, 409);
        }

        // Basis data dulu, bucket belakangan: berkas terdaftar tanpa isi lebih
        // merugikan daripada objek yatim di karantina (yang terhapus otomatis).
        DB::table('N_WEB_CAREERS_Lamaran_Tes_Berkas')->where('Id_Lamaran_Tes_Berkas', $realId)->delete();
        app(GcsBerkas::class)->hapus([$b->Path_File]);

        return ResponseHelper::success(null, 'Berkas dihapus.');
    }

    /** GET pratinjau berkas aktivitas milik kandidat (signed URL 15 menit). */
    public function tesBerkasFile(string $id)
    {
        $realId = self::idDari($id);
        $b = $realId
            ? DB::table('N_WEB_CAREERS_Lamaran_Tes_Berkas')->where('Id_Lamaran_Tes_Berkas', $realId)->where('Id_Users', self::akun())->first()
            : null;
        if (! $b) {
            abort(404);
        }

        return $this->layaniBerkasKandidat($b->Path_File, $b->Nama_File, 'tes-berkas:'.$b->Id_Lamaran_Tes_Berkas);
    }

    /**
     * Berkas unggahan kandidat sendiri: dari bucket karantina selama masih ada;
     * sesudah dibersihkan, dari salinan bersih yang didorong zona dalam ke
     * bucket publik (Pub_Dokumen berkunci $kunciDokumen).
     */
    private function layaniBerkasKandidat(?string $path, ?string $nama, string $kunciDokumen)
    {
        try {
            if ($path && Storage::disk(GcsBerkas::DISK)->exists($path)) {
                return SajianBerkas::layani(GcsBerkas::DISK, $path, $nama);
            }
        } catch (\Throwable $e) {
            Log::warning('[BERKAS] karantina tidak terbaca: '.$e->getMessage());
        }

        $d = DokumenPublik::milik($kunciDokumen, self::akun());
        if ($d) {
            return DokumenPublik::layani($d);
        }

        abort(404, 'Berkas tidak ditemukan.');
    }

    /**
     * GET dokumen HASIL TAHAP milik kandidat login (MCU, hasil wawancara).
     * {id} = kunci dokumen di Pub_Dokumen; kepemilikan lewat lamarannya.
     */
    public function portalBerkasTahap(string $id)
    {
        return DokumenPublik::layani(DokumenPublik::milik($id, self::akun()) ?: abort(404));
    }

    /** GET pratinjau berkas formulir MILIK kandidat login. */
    public function portalBerkasFile(string $id)
    {
        $realId = self::idDari($id);
        $b = $realId ? DB::table('N_WEB_CAREERS_Formulir_Berkas as fb')
            ->join('N_WEB_CAREERS_Formulir_Pengisian as fp', 'fp.Id_Formulir_Pengisian', '=', 'fb.Formulir_Pengisian_Id')
            ->join('N_WEB_CAREERS_Lamaran as l', 'l.Id_Lamaran', '=', 'fp.Lamaran_Id')
            ->where('fb.Id_Formulir_Berkas', $realId)
            ->where('l.Id_Users', self::akun())
            ->select('fb.Id_Formulir_Berkas', 'fb.Path_File', 'fb.Nama_Asli')
            ->first() : null;
        if (! $b) {
            abort(404);
        }

        return $this->layaniBerkasKandidat($b->Path_File, $b->Nama_Asli, 'formulir-berkas:'.$b->Id_Formulir_Berkas);
    }

    /**
     * GET /kandidat/lamaran/tes/{id}/surat/{urutan?} — surat pengantar milik
     * kandidat yang login. Bawaannya tampil di peramban; `?unduh=1` mengunduh;
     * `?isi=1` menyajikan isinya langsung untuk panel pratinjau pdf.js.
     */
    public function tesSurat(Request $request, string $id, int $urutan = 0)
    {
        $akt = $this->aktivitasMilik($id);
        $surat = $akt ? collect($akt['isi']['surat'] ?? [])->firstWhere('urutan', $urutan) : null;
        $dok = $surat ? DokumenPublik::milik((string) ($surat['dokumen'] ?? ''), self::akun()) : null;
        if (! $dok) {
            abort(404, 'Surat tidak ditemukan.');
        }

        return $request->boolean('isi') ? DokumenPublik::sajikanIsi($dok) : DokumenPublik::layani($dok, $request->boolean('unduh'));
    }

    /**
     * GET /karir/surat-jadwal/{id}/{urutan?}?l={kode lamaran} — surat pengantar
     * dari tautan SUREL, tanpa sesi. Rutenya bertanda tangan (kunci TAUTAN_KUNCI,
     * dibuat zona dalam): tidak bisa ditebak, disunting, atau dipakai untuk
     * lamaran lain, dan mati dengan sendirinya.
     */
    public function suratJadwalPublik(Request $request, string $id, int $urutan = 0)
    {
        $kode = (string) $request->query('l', '');
        $realId = self::idDari($id);
        $potret = $kode !== '' ? Potret::lewatTautan($kode) : null;
        $surat = $potret && $realId ? collect($potret['aktivitas'][(string) $realId]['surat'] ?? [])->firstWhere('urutan', $urutan) : null;
        $dok = $surat ? DokumenPublik::untukLamaran((string) ($surat['dokumen'] ?? ''), $kode) : null;

        Log::info("[SURAT-JADWAL] surat #{$urutan} aktivitas #{$realId} ({$kode}) dibuka dari tautan surel");

        if (! $dok) {
            abort(404, 'Surat tidak ditemukan.');
        }

        // Selalu tampil (inline): tanda tangan tautan mencakup query-nya.
        return DokumenPublik::layani($dok);
    }

    // ═══════════════════════ FORMULIR TAHAP ═══════════════════════

    /**
     * POST /api/v1/lamaran/tahap/{id}/kirim — kandidat mengirim formulir tahap.
     *
     * SATU TRANSAKSI: isian tersimpan, berkas draf berpindah kepemilikan ke
     * isian, draf dibuang, dan peristiwa Formulir.Dikirim tercatat — atau
     * tidak sama sekali. Penilaian syarat dan kelanjutan tahap dikerjakan
     * zona dalam saat memproses peristiwanya.
     */
    public function kirimFormulir(Request $request, string $id)
    {
        $userId = self::akun();
        $tahap = FormulirDrafController::tahapMilik($userId, $id);
        if (! $tahap) {
            return ResponseHelper::error('Tahap tidak ditemukan.', 404);
        }

        $data = $request->validate([
            'jawaban' => 'required|array',
        ]);

        $isi = $tahap['isi'];
        $form = ! empty($isi['formulirKode'])
            ? FormulirSchema::byKodeDanVersi($isi['formulirKode'], isset($isi['formulirVersi']) ? (int) $isi['formulirVersi'] : null)
            : FormulirSchema::pendaftaranUntukKategori((string) ($isi['kategori'] ?? ''));
        $skema = $form['schema'] ?? null;
        $nama = (string) session('career_auth.nama');

        try {
            $hasil = DB::transaction(function () use ($tahap, $userId, $data, $request, $skema, $form, $nama) {
                // Kunci yang sama dengan unggahan draf: unggahan yang masih
                // berjalan menunggu kiriman ini selesai, lalu ditolak karena
                // formulirnya sudah terkirim; kiriman ganda berhenti di sini.
                $draf = FormulirDrafController::kunciDraf($tahap, $userId);

                // Berkas yang disebut jawaban HARUS benar-benar ada di draf.
                $kurang = BerkasFormulir::kurang(
                    $skema,
                    $data['jawaban'],
                    BerkasFormulir::adaDiDaftar(BerkasBaris::daftar($draf->Berkas_Json ?? null)),
                );
                if ($kurang) {
                    return ['berkasKurang' => $kurang];
                }

                $now = now();
                $kodeIsian = 'FLL-'.strtoupper(Str::random(8));
                $pengisianId = (int) DB::table('N_WEB_CAREERS_Formulir_Pengisian')->insertGetId([
                    'Kode' => $kodeIsian,
                    'Id_Users' => $userId,
                    'Lamaran_Id' => $tahap['lamaranId'],
                    'Lamaran_Tahap_Id' => $tahap['id'],
                    'Master_Formulir_Id' => (int) ($form['realId'] ?? 0),
                    'Komponen_Kode' => $form['komponen'] ?? null,
                    'Sumber' => 'TAHAP',
                    'Jawaban_Json' => json_encode($data['jawaban'], JSON_UNESCAPED_UNICODE),
                    'Langkah_Terakhir' => 0,
                    'Status' => 'TERKIRIM',
                    'Waktu_Kirim' => $now,
                    'Ip_Pengirim' => Str::limit((string) $request->ip(), 45, ''),
                    'Created_At' => $now, 'Created_By' => $nama, 'Created_By_Id' => $userId,
                    'Updated_At' => $now, 'Updated_By' => $nama, 'Updated_By_Id' => $userId,
                ] + FormulirSchema::kolomSnapshotInsert($form), 'Id_Formulir_Pengisian');

                // Objek di bucket TIDAK disalin — hanya kepemilikannya berpindah.
                $berkas = FormulirDrafController::jadikanPermanen($tahap['id'], $userId, $pengisianId, $data['jawaban']);
                FormulirDrafController::bersihkan($tahap['id'], $userId, hapusBerkas: false);

                Outbox::tulis(Outbox::FORMULIR_DIKIRIM, 'Formulir.Dikirim:'.$kodeIsian, $userId, [
                    'kode' => $tahap['kode'],
                    'akun' => ['id_publik' => $userId],
                    'tahap_id' => $tahap['id'],
                    'formulir' => [
                        'pengisian_kode' => $kodeIsian,
                        'formulir_kode' => $form['kode'] ?? null,
                        'versi' => $form['versi'] ?? null,
                    ],
                    'jawaban' => $data['jawaban'] ?: (object) [],
                    'berkas' => $berkas,
                    'dikirim_at' => $now->toIso8601String(),
                    'ip' => $request->ip(),
                ], $nama);

                return ['ok' => true];
            });
        } catch (\DomainException $e) {
            // Formulir sudah terkirim / tahap tertutup: 409, supaya layar tahu
            // yang dibutuhkan adalah memuat ulang, bukan memperbaiki isian.
            return ResponseHelper::error($e->getMessage(), 409);
        } catch (\Throwable $e) {
            Log::error('Gagal simpan pengisian: '.$e->getMessage());

            return ResponseHelper::error('Gagal menyimpan formulir.', 500);
        }

        if (isset($hasil['berkasKurang'])) {
            return $this->galatBerkasKurang($hasil['berkasKurang']);
        }

        return ResponseHelper::success(['rekomendasi' => null], 'Formulir terkirim. Tim rekrutmen akan meninjaunya.');
    }

    // ═══════════════════════ TAMPILAN FORMULIR TERKIRIM ═══════════════════════


    /**
     * Bentuk daftar formulir yang SUDAH dikirim untuk sebuah lamaran (jawaban +
     * berkas) — dari tabel milik kandidat sendiri. $urlBerkasPrefix menentukan
     * basis URL berkas.
     *
     * Label & urutan tahap datang dari POTRET (`tahapFormulir`): tahapnya hidup
     * di zona dalam. Formulir pendaftaran selalu urutan pertama.
     *
     * @param  array<string, array>  $tahapPotret  potret.tahapFormulir
     */
    private function formulirTerkirim(int $lamaranId, string $urlBerkasPrefix, array $tahapPotret = []): array
    {
        $pengisian = DB::table('N_WEB_CAREERS_Formulir_Pengisian as fp')
            ->where('fp.Lamaran_Id', $lamaranId)
            ->orderBy('fp.Id_Formulir_Pengisian')
            ->get()
            ->map(function ($fp) use ($tahapPotret) {
                $t = $fp->Lamaran_Tahap_Id ? ($tahapPotret[(string) $fp->Lamaran_Tahap_Id] ?? null) : null;
                $fp->TahapUrutan = $t['urutan'] ?? ($fp->Sumber === 'PENDAFTARAN' ? 1 : 0);
                $fp->TahapLabel = $t['label'] ?? null;

                return $fp;
            })
            ->sortBy('TahapUrutan')
            ->values();

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
     * SATU TEMPAT, bukan dua salinan: setiap layar kandidat yang menampilkan
     * jawaban memakai aturan yang sama, dan dua salinan aturan pasti berselisih —
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
}
