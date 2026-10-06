<?php

namespace App\Http\Controllers\Career\Lamaran;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Support\Career\BerkasBaris;
use App\Support\Career\BerkasFormulir;
use App\Support\Career\FormulirSchema;
use App\Support\Career\GcsBerkas;
use App\Support\Portal\PenilaiWaktu;
use App\Support\Portal\Potret;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Vinkla\Hashids\Facades\Hashids;

/**
 * WEB CAREER — SIMPAN SEMENTARA (DRAF) FORMULIR TAHAP.
 *
 * Kandidat mengisi formulir bertahap yang panjang. Tanpa draf, satu kali
 * refresh atau sesi putus menghapus seluruh ketikan — justru pada formulir
 * terpanjang.
 *
 *   - Draf disimpan di SERVER (N_WEB_CAREERS_Formulir_Draf, tabel milik), satu
 *     baris per (tahap, kandidat), ditimpa setiap kali disimpan.
 *   - Berkas draf naik ke bucket KARANTINA lewat endpoint ini, dan hanya bisa
 *     dibuka lewat pratinjau yang menerbitkan signed URL berumur pendek.
 *
 * KEPEMILIKAN dibuktikan oleh POTRET akun ini: id tahap yang tidak tercantum
 * di potret lamaran milik kandidat yang login tidak bisa dipakai. Status tahap
 * (terbuka/tertutup, batas isi) juga dibaca dari potret — tahapnya sendiri
 * hidup di zona dalam.
 */
class FormulirDrafController extends Controller
{
    /**
     * Batas ukuran bila isiannya tidak ditemukan di skema (lihat
     * BerkasFormulir::aturanUnggah()).
     */
    private const MAKS_MB_BAWAAN = 5;

    public const PESAN_TERTUTUP = 'Formulir tahap ini sudah terkirim atau tidak lagi dalam pengisian — isian dan berkasnya tidak bisa diubah lagi.';

    /**
     * Tahap ber-formulir milik kandidat yang login.
     *
     * @return array{id: int, hash: string, kode: string, lamaranId: int, isi: array}|null
     */
    public static function tahapMilik(int $userId, string $hash): ?array
    {
        $realId = Hashids::decode($hash)[0] ?? null;
        if (! $realId || ! $userId) {
            return null;
        }

        $temu = Potret::cari($userId, 'tahapFormulir', (int) $realId);
        if (! $temu) {
            return null;
        }

        $lamaran = DB::table('N_WEB_CAREERS_Lamaran')
            ->where('Kode', $temu['kode'])
            ->where('Id_Users', $userId)
            ->first(['Id_Lamaran', 'Kategori']);

        return $lamaran ? [
            'id' => (int) $realId,
            'hash' => $hash,
            'kode' => $temu['kode'],
            'lamaranId' => (int) $lamaran->Id_Lamaran,
            // Kategori lamaran = cadangan pemilih skema bila potret tidak
            // menyebut kode formulir tahapnya.
            'isi' => $temu['isi'] + ['kategori' => $lamaran->Kategori],
        ] : null;
    }

    /** Skema yang DIBEKUKAN untuk tahap ini (salinan Master Formulir). */
    public static function skema(array $tahap): ?array
    {
        $isi = $tahap['isi'];

        return (! empty($isi['formulirKode'])
            ? FormulirSchema::byKodeDanVersi($isi['formulirKode'], isset($isi['formulirVersi']) ? (int) $isi['formulirVersi'] : null)
            : FormulirSchema::pendaftaranUntukKategori((string) ($isi['kategori'] ?? '')))['schema'] ?? null;
    }

    /**
     * Pesan penolak bila formulir tahap ini sudah tidak boleh disentuh: tahap
     * ditutup zona dalam, batas pengisian lewat, atau formulirnya sudah
     * terkirim dari sini.
     */
    public static function galatTutup(array $tahap, int $userId, bool $kunci = false): ?string
    {
        $isi = $tahap['isi'];
        if (empty($isi['terbuka'])) {
            return ($isi['alasanTutup'] ?? null) ?: self::PESAN_TERTUTUP;
        }
        if (PenilaiWaktu::lewat($isi['batasIsi'] ?? null)) {
            return 'Batas pengisian formulir tahap ini sudah lewat — isiannya tidak bisa dikirim lagi.';
        }

        $q = DB::table('N_WEB_CAREERS_Formulir_Pengisian')
            ->where('Lamaran_Tahap_Id', $tahap['id'])
            ->where('Id_Users', $userId);
        if ($kunci) {
            $q->lockForUpdate();
        }

        return $q->exists() ? self::PESAN_TERTUTUP : null;
    }

    /**
     * Kunci baris draf (dan rentangnya bila belum ada) — wajib di dalam
     * transaksi. Unggah, simpan, dan kirim formulir melewati kunci yang sama,
     * jadi dua unggahan yang berbarengan tidak saling menghapus entri berkas,
     * dan unggahan yang tiba sesudah formulir terkirim ditolak.
     *
     * @throws \DomainException bila formulirnya sudah tidak boleh disentuh
     */
    public static function kunciDraf(array $tahap, int $userId): ?object
    {
        if ($pesan = self::galatTutup($tahap, $userId, kunci: true)) {
            throw new \DomainException($pesan);
        }

        return DB::table('N_WEB_CAREERS_Formulir_Draf')
            ->where('Lamaran_Tahap_Id', $tahap['id'])
            ->where('Id_Users', $userId)
            ->lockForUpdate()
            ->first();
    }

    /**
     * GET /kandidat/lamaran/tahap/{id}/draf — pulihkan isian yang tertunda.
     * Selalu 200; belum pernah menyimpan draf bukan kesalahan.
     */
    public function ambil(string $id)
    {
        $userId = (int) session('career_auth.id');
        $tahap = self::tahapMilik($userId, $id);
        if (! $tahap) {
            return ResponseHelper::error('Tahap tidak ditemukan.', 404);
        }

        $d = DB::table('N_WEB_CAREERS_Formulir_Draf')
            ->where('Lamaran_Tahap_Id', $tahap['id'])
            ->where('Id_Users', $userId)
            ->first();

        if (! $d) {
            return ResponseHelper::success(['draf' => null], 'Belum ada simpanan sementara.');
        }

        // Path bucket sengaja TIDAK ikut keluar — browser cukup menerima URL
        // pratinjau milik aplikasi ini.
        $berkas = collect(BerkasBaris::daftar($d->Berkas_Json ?? null))
            ->map(fn ($b) => [
                'bagian' => $b['bagian'] ?? null,
                'baris' => $b['baris'] ?? null,
                'field' => $b['field'] ?? null,
                'nama' => $b['nama'] ?? null,
                'ukuran' => (int) ($b['ukuran'] ?? 0),
                'mime' => $b['mime'] ?? null,
                'url' => route('career.portal.draf.berkas', array_filter([
                    'id' => $id,
                    'field' => $b['field'] ?? null,
                    'bagian' => $b['bagian'] ?? null,
                    'baris' => $b['baris'] ?? null,
                ], fn ($v) => $v !== null)),
            ])
            ->values();

        return ResponseHelper::success([
            'draf' => [
                'jawaban' => json_decode($d->Jawaban_Json ?: '{}', true) ?: (object) [],
                'langkah' => (int) $d->Langkah_Terakhir,
                'berkas' => $berkas,
                'disimpanAt' => (string) ($d->Updated_At ?: $d->Created_At),
            ],
        ], 'Simpanan sementara ditemukan.');
    }

    /**
     * POST /kandidat/lamaran/tahap/{id}/draf — simpan setiap kali "Lanjut".
     * Sinkron: kandidat berhak tahu detik itu juga bahwa isiannya aman.
     */
    public function simpan(Request $request, string $id)
    {
        $userId = (int) session('career_auth.id');
        $tahap = self::tahapMilik($userId, $id);
        if (! $tahap) {
            return ResponseHelper::error('Tahap tidak ditemukan.', 404);
        }
        if ($pesan = self::galatTutup($tahap, $userId)) {
            return ResponseHelper::error($pesan, 409);
        }

        // `present`, BUKAN `required`: array kosong adalah draf yang sah
        // (langkah yang seluruh isiannya opsional).
        $data = $request->validate([
            'jawaban' => 'present|array',
            'langkah' => 'nullable|integer|min:0|max:50',
            'komponen' => 'nullable|string|max:60',
            'schema' => 'nullable|array',
        ]);

        $nama = session('career_auth.nama');
        $now = now();

        $isi = [
            'Lamaran_Id' => $tahap['lamaranId'],
            'Lamaran_Tahap_Id' => $tahap['id'],
            'Id_Users' => $userId,
            'Formulir_Kode' => $tahap['isi']['formulirKode'] ?? null,
            'Komponen_Kode' => $data['komponen'] ?? null,
            'Langkah_Terakhir' => (int) ($data['langkah'] ?? 0),
            'Jawaban_Json' => json_encode($data['jawaban'], JSON_UNESCAPED_UNICODE),
            'Updated_At' => $now,
            'Updated_By' => $nama,
            'Updated_By_Id' => $userId,
        ];

        if (! empty($data['schema'])) {
            $isi['Schema_Snapshot_Json'] = json_encode($data['schema'], JSON_UNESCAPED_UNICODE);
        }

        try {
            DB::transaction(function () use ($tahap, $userId, $isi, $now, $nama) {
                self::kunciDraf($tahap, $userId);

                DB::table('N_WEB_CAREERS_Formulir_Draf')->updateOrInsert(
                    ['Lamaran_Tahap_Id' => $tahap['id'], 'Id_Users' => $userId],
                    $isi + ['Created_At' => $now, 'Created_By' => $nama, 'Created_By_Id' => $userId],
                );
            });
        } catch (\DomainException $e) {
            return ResponseHelper::error($e->getMessage(), 409);
        }

        return ResponseHelper::success(['disimpanAt' => $now->toIso8601String()], 'Tersimpan sementara.');
    }

    /**
     * Periksa triplet (bagian, baris, field) terhadap SKEMA YANG DIBEKUKAN.
     * Skema yang tidak terbaca TIDAK menolak apa pun.
     *
     * @return string|null pesan galat, atau null bila sah
     */
    public static function periksaBaris(?array $schema, ?string $bagian, ?int $baris, string $field): ?string
    {
        // Tanpa bagian/baris = berkas biasa di luar bagian berulang.
        if ($bagian === null || $baris === null || ! $schema) {
            return null;
        }

        foreach (($schema['langkah'] ?? []) as $langkah) {
            foreach (array_values((array) ($langkah['bagian'] ?? [])) as $iB => $b) {
                if (! is_array($b) || BerkasBaris::kunciBagian($b, $iB) !== $bagian) {
                    continue;
                }

                if (empty($b['berulang'])) {
                    return "Bagian \"{$bagian}\" tidak menerima baris berulang.";
                }

                $maks = (int) ($b['maks_baris'] ?? 5);
                if ($baris < 0 || $baris >= $maks) {
                    $judul = $b['judul'] ?? $bagian;

                    return "{$judul} hanya menerima {$maks} baris.";
                }

                foreach (($b['field'] ?? []) as $f) {
                    if (! is_array($f) || BerkasFormulir::kunciField($f) !== $field) {
                        continue;
                    }

                    return strtolower((string) ($f['tipe'] ?? '')) === 'file'
                        ? null
                        : "Field \"{$field}\" bukan field berkas.";
                }

                return "Field \"{$field}\" tidak ada di bagian \"{$bagian}\".";
            }
        }

        return "Bagian \"{$bagian}\" tidak ada di formulir ini.";
    }

    /** POST /kandidat/lamaran/tahap/{id}/draf/berkas — unggah berkas draf ke karantina. */
    public function unggahBerkas(Request $request, string $id)
    {
        $userId = (int) session('career_auth.id');
        $tahap = self::tahapMilik($userId, $id);
        if (! $tahap) {
            return ResponseHelper::error('Tahap tidak ditemukan.', 404);
        }

        // Ditolak sebelum satu byte pun naik ke bucket; diperiksa sekali lagi
        // di bawah kunci (kunciDraf).
        if ($pesan = self::galatTutup($tahap, $userId)) {
            return ResponseHelper::error($pesan, 409);
        }

        // post_max_size lebih kecil dari berkasnya → $_FILES kosong; beri pesan
        // yang jujur, bukan "berkas wajib diisi".
        if ($request->file('berkas') === null && $request->server('CONTENT_LENGTH') > 0) {
            return ResponseHelper::error(
                'Berkas terlalu besar untuk diterima server. Perkecil ukurannya lalu coba lagi.',
                413,
            );
        }

        $data = $request->validate([
            'field' => 'required|string|max:60',
            'bagian' => 'nullable|string|max:60',
            'baris' => 'nullable|integer|min:0|max:99',
            'berkas' => 'required|file',
        ]);

        $bagian = $data['bagian'] ?? null;
        $barisIdx = isset($data['baris']) ? (int) $data['baris'] : null;
        $skema = self::skema($tahap);

        if ($galat = self::periksaBaris($skema, $bagian, $barisIdx, $data['field'])) {
            return ResponseHelper::error($galat, 422);
        }

        // Format & ukuran mengikuti SKEMA isian ini (maks_mb / accept).
        $file = $request->file('berkas');
        $aturan = BerkasFormulir::aturanUnggah($skema, $bagian, $data['field'], self::MAKS_MB_BAWAAN);
        if ($galat = BerkasFormulir::periksaBerkas($file, $aturan)) {
            return ResponseHelper::error($galat, 422);
        }

        $gcs = app(GcsBerkas::class);
        $now = now();
        $folder = $gcs->folderTahap($now->format('Y'), $now->format('m'), $now->format('d'), (string) (session('career_auth.nama') ?: 'kandidat'));
        $ext = strtolower($file->getClientOriginalExtension() ?: $file->extension());

        try {
            // getContent(), BUKAN getRealPath(): di bawah Apache getRealPath()
            // bisa kosong untuk berkas unggahan sementara.
            $path = $gcs->unggahUnik($folder, $data['field'], $ext, $file->getContent());
        } catch (\Throwable $e) {
            Log::error('[DRAF] unggah berkas gagal: '.$e->getMessage(), [
                'di' => $e->getFile().':'.$e->getLine(),
                'field' => $data['field'],
                'ext' => $ext,
                'ukuran' => $file->getSize(),
            ]);

            return ResponseHelper::error('Berkas gagal diunggah: '.Str::limit($e->getMessage(), 160), 500);
        }

        $meta = [
            'bagian' => $bagian,
            'baris' => $barisIdx,
            'field' => $data['field'],
            'nama' => $file->getClientOriginalName(),
            'path' => $path,
            'ukuran' => $file->getSize(),
            'mime' => $file->getMimeType(),
        ];
        $kunci = ['Lamaran_Tahap_Id' => $tahap['id'], 'Id_Users' => $userId];

        try {
            $lama = DB::transaction(function () use ($tahap, $userId, $kunci, $meta) {
                $baris = self::kunciDraf($tahap, $userId);

                // DAFTAR, bukan peta berkunci field: tiga sertifikat di bagian
                // berulang memakai field yang sama. Yang diganti hanya entri
                // dengan triplet yang sama persis.
                $lama = null;
                $sisa = [];
                foreach (BerkasBaris::daftar($baris->Berkas_Json ?? null) as $e) {
                    if (BerkasBaris::cocok($e, $meta['bagian'], $meta['baris'], $meta['field'])) {
                        $lama = $e['path'] ?? null;

                        continue;
                    }
                    $sisa[] = $e;
                }
                $sisa[] = $meta;

                DB::table('N_WEB_CAREERS_Formulir_Draf')->updateOrInsert($kunci, [
                    'Lamaran_Id' => $tahap['lamaranId'],
                    'Formulir_Kode' => $tahap['isi']['formulirKode'] ?? null,
                    'Berkas_Json' => json_encode(array_values($sisa), JSON_UNESCAPED_UNICODE),
                    'Updated_At' => now(),
                    'Updated_By' => session('career_auth.nama'),
                    'Updated_By_Id' => $userId,
                    'Created_At' => $baris->Created_At ?? now(),
                    'Created_By' => $baris->Created_By ?? session('career_auth.nama'),
                    'Created_By_Id' => $baris->Created_By_Id ?? $userId,
                ]);

                return $lama;
            });
        } catch (\Throwable $e) {
            // Objek yang baru naik tidak tercatat di mana pun — dibuang.
            $gcs->hapus([$path]);

            if ($e instanceof \DomainException) {
                return ResponseHelper::error($e->getMessage(), 409);
            }

            Log::error('[DRAF] berkas gagal dicatat: '.$e->getMessage(), ['tahap' => $tahap['id'], 'field' => $data['field']]);

            return ResponseHelper::error('Berkas belum tercatat di server. Coba unggah ulang.', 500);
        }

        // Berkas lama triplet yang sama dibuang SESUDAH transaksi: kalau
        // pencatatannya batal, objek lama masih dipakai draf.
        if ($lama && $lama !== $path) {
            $gcs->hapus([$lama]);
        }

        return ResponseHelper::success([
            'bagian' => $bagian,
            'baris' => $barisIdx,
            'field' => $data['field'],
            'nama' => $meta['nama'],
            'ukuran' => $meta['ukuran'],
            'mime' => $meta['mime'],
            'url' => route('career.portal.draf.berkas', array_filter([
                'id' => $id,
                'field' => $data['field'],
                'bagian' => $bagian,
                'baris' => $barisIdx,
            ], fn ($v) => $v !== null)),
        ], 'Berkas tersimpan sementara.');
    }

    /**
     * DELETE /kandidat/lamaran/tahap/{id}/draf/berkas — buang berkas SATU BARIS
     * bagian berulang, lalu turunkan indeks entri di atasnya. Penggeseran hidup
     * HANYA di sini, bukan dihitung ulang di Vue.
     */
    public function hapusBerkas(Request $request, string $id)
    {
        $userId = (int) session('career_auth.id');
        $tahap = self::tahapMilik($userId, $id);
        if (! $tahap) {
            return ResponseHelper::error('Tahap tidak ditemukan.', 404);
        }

        $data = $request->validate([
            'bagian' => 'required|string|max:60',
            'baris' => 'required|integer|min:0|max:99',
        ]);

        $kunci = ['Lamaran_Tahap_Id' => $tahap['id'], 'Id_Users' => $userId];
        $bagian = $data['bagian'];
        $idx = (int) $data['baris'];

        try {
            $buang = DB::transaction(function () use ($tahap, $userId, $kunci, $bagian, $idx) {
                $baris = self::kunciDraf($tahap, $userId);
                if (! $baris) {
                    return null;
                }

                $semua = BerkasBaris::daftar($baris->Berkas_Json ?? null);

                // Path yang akan yatim dicatat SEBELUM digeser.
                $buang = [];
                foreach ($semua as $e) {
                    if (($e['bagian'] ?? null) === $bagian && ($e['baris'] ?? null) === $idx && ! empty($e['path'])) {
                        $buang[] = $e['path'];
                    }
                }

                DB::table('N_WEB_CAREERS_Formulir_Draf')->where($kunci)->update([
                    'Berkas_Json' => json_encode(array_values(BerkasBaris::geser($semua, $bagian, $idx)), JSON_UNESCAPED_UNICODE),
                    'Updated_At' => now(),
                    'Updated_By' => session('career_auth.nama'),
                    'Updated_By_Id' => $userId,
                ]);

                return $buang;
            });
        } catch (\DomainException $e) {
            return ResponseHelper::error($e->getMessage(), 409);
        }

        if ($buang === null) {
            return ResponseHelper::success([], 'Tidak ada berkas yang perlu dibuang.');
        }

        app(GcsBerkas::class)->hapus($buang);

        return ResponseHelper::success(['dibuang' => count($buang)], 'Berkas baris dibuang.');
    }

    /**
     * GET /kandidat/lamaran/tahap/{id}/draf/berkas/{field} — pratinjau berkas
     * draf lewat signed URL 15 menit.
     */
    public function berkas(Request $request, string $id, string $field)
    {
        $userId = (int) session('career_auth.id');
        $tahap = self::tahapMilik($userId, $id);
        if (! $tahap) {
            abort(404);
        }

        $d = DB::table('N_WEB_CAREERS_Formulir_Draf')
            ->where('Lamaran_Tahap_Id', $tahap['id'])
            ->where('Id_Users', $userId)
            ->value('Berkas_Json');

        // Tautan lama tanpa bagian/baris SAH — artinya berkas biasa.
        $bagian = $request->query('bagian');
        $barisQ = $request->query('baris');
        $baris = ($barisQ === null || $barisQ === '') ? null : (int) $barisQ;

        foreach (BerkasBaris::daftar($d) as $b) {
            if (BerkasBaris::cocok($b, $bagian, $baris, $field) && ! empty($b['path'])) {
                try {
                    $disk = Storage::disk(GcsBerkas::DISK);
                    if ($disk->exists($b['path'])) {
                        return redirect()->away($disk->temporaryUrl($b['path'], now()->addMinutes(15)));
                    }
                } catch (\Throwable $e) {
                    Log::warning('[DRAF] signed URL gagal: '.$e->getMessage());
                }
                break;
            }
        }

        abort(404);
    }

    /**
     * PINDAHKAN berkas draf jadi berkas PERMANEN milik pengisian.
     *
     * Objek di bucket TIDAK disalin — hanya kepemilikannya yang berpindah ke
     * Formulir_Berkas. Pemanggil WAJIB memakai bersihkan(..., hapusBerkas: false)
     * sesudahnya supaya berkas yang baru diadopsi tidak ikut terhapus.
     *
     * @return list<array> berkas yang dicatat (dibawa ke peristiwa Formulir.Dikirim)
     */
    public static function jadikanPermanen(int $tahapId, int $userId, int $pengisianId, array $jawaban = []): array
    {
        $draf = DB::table('N_WEB_CAREERS_Formulir_Draf')
            ->where('Lamaran_Tahap_Id', $tahapId)
            ->where('Id_Users', $userId)
            ->first();

        $semua = $draf ? BerkasBaris::daftar($draf->Berkas_Json ?? null) : [];
        if (! $semua) {
            return [];
        }

        $nama = session('career_auth.nama');
        $now = now();
        $urutan = 0;
        $tercatat = [];

        foreach ($semua as $b) {
            if (empty($b['path']) || empty($b['field'])) {
                continue;
            }

            $bagian = $b['bagian'] ?? null;
            $idx = $b['baris'] ?? null;

            // BARIS YATIM: kandidat mengunggah lalu menghapus barisnya sebelum
            // mengirim — tidak disahkan.
            if ($bagian !== null && $idx !== null) {
                $barisJawaban = $jawaban[$bagian] ?? null;
                if (! is_array($barisJawaban) || ! array_key_exists($idx, $barisJawaban)) {
                    continue;
                }
            }

            $urutan++;

            // Idempoten per TRIPLET (bagian, baris, field): pengesahan ulang
            // untuk pengisian yang sama tidak menggandakan — tiga sertifikat di
            // bagian berulang memakai field yang sama persis.
            $sudah = DB::table('N_WEB_CAREERS_Formulir_Berkas')
                ->where('Formulir_Pengisian_Id', $pengisianId)
                ->where('Field_Key', $b['field'])
                ->when($bagian === null, fn ($q) => $q->whereNull('Bagian_Key'))
                ->when($bagian !== null, fn ($q) => $q->where('Bagian_Key', $bagian))
                ->when($idx === null, fn ($q) => $q->whereNull('Baris_Index'))
                ->when($idx !== null, fn ($q) => $q->where('Baris_Index', $idx))
                ->exists();
            if ($sudah) {
                continue;
            }

            $ext = strtolower(pathinfo($b['path'], PATHINFO_EXTENSION) ?: 'pdf');

            DB::table('N_WEB_CAREERS_Formulir_Berkas')->insert([
                'Formulir_Pengisian_Id' => $pengisianId,
                'Id_Users' => $userId,
                'Bagian_Key' => $bagian,
                'Baris_Index' => $idx,
                'Field_Key' => $b['field'],
                'Urutan' => $urutan,
                'Nama_Asli' => $b['nama'] ?? ('berkas.'.$ext),
                'Path_File' => $b['path'],
                'Ukuran_Byte' => (int) ($b['ukuran'] ?? 0),
                'Mime' => $b['mime'] ?? null,
                'Ekstensi' => $ext,
                'Status_Verifikasi' => 'BELUM',
                'Waktu_Unggah' => $now,
                'Created_At' => $now,
                'Created_By' => $nama,
                'Created_By_Id' => $userId,
                'Updated_At' => $now,
                'Updated_By' => $nama,
                'Updated_By_Id' => $userId,
            ]);

            $tercatat[] = [
                'field' => $b['field'],
                'bagian' => $bagian,
                'baris' => $idx,
                'nama' => $b['nama'] ?? ('berkas.'.$ext),
                'path' => $b['path'],
                'ext' => $ext,
                'mime' => $b['mime'] ?? null,
                'ukuran' => (int) ($b['ukuran'] ?? 0),
            ];
        }

        return $tercatat;
    }

    /** Buang draf sebuah tahap (dan berkasnya, bila diminta). Aman walau draf tidak ada. */
    public static function bersihkan(int $tahapId, int $userId, bool $hapusBerkas = true): void
    {
        $baris = DB::table('N_WEB_CAREERS_Formulir_Draf')
            ->where('Lamaran_Tahap_Id', $tahapId)
            ->where('Id_Users', $userId)
            ->first();

        if (! $baris) {
            return;
        }

        if ($hapusBerkas) {
            app(GcsBerkas::class)->hapus(array_column(BerkasBaris::daftar($baris->Berkas_Json ?? null), 'path'));
        }

        DB::table('N_WEB_CAREERS_Formulir_Draf')
            ->where('Id_Formulir_Draf', $baris->Id_Formulir_Draf)
            ->delete();
    }
}
