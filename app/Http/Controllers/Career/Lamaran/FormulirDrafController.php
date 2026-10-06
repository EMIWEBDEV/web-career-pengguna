<?php

namespace App\Http\Controllers\Career\Lamaran;

use App\Http\Controllers\Controller;
use App\Support\Career\BatasIsi;
use App\Support\Career\BerkasBaris;
use App\Support\Career\BerkasFormulir;
use App\Support\Career\GcsBerkas;
use App\Support\Career\FormulirSchema;
use App\Helpers\ResponseHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Vinkla\Hashids\Facades\Hashids;

/**
 * WEB CAREER — SIMPAN SEMENTARA (DRAF) FORMULIR TAHAP.
 *
 * Kandidat mengisi formulir bertahap yang panjang (Kelengkapan Data Diri: 4
 * langkah + 4 unggahan). Tanpa draf, satu kali refresh atau sesi putus
 * menghapus seluruh ketikan — dan itu terjadi justru pada formulir terpanjang.
 *
 * KEPUTUSAN PENTING
 *   - Draf disimpan di SERVER (N_WEB_CAREERS_Formulir_Draf), bukan localStorage.
 *     Draf yang hidup di browser hilang saat kandidat pindah perangkat, dan tim
 *     rekrutmen tidak punya cara melihat pengisian yang tertahan.
 *   - Satu baris per (tahap, kandidat); disimpan dengan cara ditimpa. Yang
 *     berguna hanya keadaan terakhir, bukan riwayat ketikan.
 *   - Berkas draf naik ke GCS lewat endpoint ini, dan HANYA bisa dibuka lewat
 *     endpoint pratinjau di bawah yang menerbitkan signed URL berumur pendek.
 *     Front-end tidak pernah menyentuh API storage secara langsung, sehingga
 *     kredensial bucket tidak pernah meninggalkan server.
 *
 * KEPEMILIKAN diperiksa di SETIAP endpoint lewat join ke Lamaran.Id_Users —
 * mengetahui id tahap milik orang lain tidak cukup untuk membaca drafnya.
 */
class FormulirDrafController extends Controller
{
    /**
     * Batas ukuran bila isiannya tidak ditemukan di skema — perilaku sebelum
     * batas mengikuti skema (lihat BerkasFormulir::aturanUnggah()).
     */
    private const MAKS_MB_BAWAAN = 5;

    public const PESAN_TERTUTUP = 'Formulir tahap ini sudah terkirim atau tidak lagi dalam pengisian — isian dan berkasnya tidak bisa diubah lagi.';

    /**
     * Kunci baris TAHAP lalu baris DRAF — wajib dipanggil di dalam transaksi.
     *
     * Berkas_Json diubah dengan pola baca-ubah-tulis. Tanpa kunci, dua unggahan
     * yang berjalan bersamaan (KTP lalu KK dipilih beruntun) membaca daftar
     * lama yang sama, dan yang menulis belakangan MENGHAPUS entri milik yang
     * lain: berkasnya sudah di GCS, tapi tak pernah ikut dipindah saat formulir
     * dikirim. Itulah pola "sebagian berkas hilang dalam satu kiriman" yang
     * ditemukan di produksi (26 Sep 2026).
     *
     * Urutan kuncinya SAMA di semua pemakai — tahap dulu, baru draf — termasuk
     * LamaranController::kirimFormulir(), supaya dua transaksi tidak pernah
     * saling menunggu. HOLDLOCK ikut mengunci rentang kunci saat barisnya belum
     * ada, jadi dua unggahan pertama pun tidak bisa sama-sama menyisipkan.
     *
     * Status tahap diperiksa ULANG di bawah kunci: pemeriksaan sebelum
     * transaksi bisa basi karena formulirnya terkirim di sela-selanya — dan
     * unggahan yang lolos saat itu melahirkan draf baru yang tak pernah diadopsi.
     *
     * @throws \DomainException bila formulirnya sudah terkirim / tahapnya tertutup
     */
    public static function kunciDraf(int $tahapId, int $userId): ?object
    {
        $tahap = DB::table('N_WEB_CAREERS_Lamaran_Tahap')
            ->where('Id_Lamaran_Tahap', $tahapId)
            ->lockForUpdate()
            ->first(['Status', 'Formulir_Pengisian_Id']);

        if (! $tahap || self::tertutup($tahap)) {
            throw new \DomainException(self::PESAN_TERTUTUP);
        }

        // BATAS PENGISIAN LEWAT (mode KUNCI) — diperiksa di bawah kunci yang
        // sama, jadi kiriman yang tiba sedetik sesudah batas tidak lolos.
        if ($pesan = BatasIsi::pesanKunci($tahapId)) {
            throw new \DomainException($pesan);
        }

        return DB::table('N_WEB_CAREERS_Formulir_Draf')
            ->where('Lamaran_Tahap_Id', $tahapId)
            ->where('Id_Users', $userId)
            ->lockForUpdate()
            ->first();
    }

    /**
     * Formulir tahap sudah tidak boleh disentuh: tahapnya tidak berjalan, atau
     * formulirnya SUDAH terkirim. Portal pun berhenti menampilkan formulir
     * begitu Formulir_Pengisian_Id terisi (portalDetail), jadi setiap tulis
     * sesudahnya hanya bisa datang dari permintaan yang terlambat tiba.
     */
    private static function tertutup(object $tahap): bool
    {
        return $tahap->Status !== 'BERJALAN' || ! empty($tahap->Formulir_Pengisian_Id);
    }

    /**
     * Tahap milik kandidat yang sedang login, atau null.
     *
     * Dikembalikan berikut Lamaran_Id supaya pemanggil tidak perlu query lagi.
     */
    private function tahapMilikSaya(string $id): ?object
    {
        $userId = (int) session('career_auth.id');
        $realId = Hashids::decode($id)[0] ?? null;
        if (! $realId || ! $userId) {
            return null;
        }

        return DB::table('N_WEB_CAREERS_Lamaran_Tahap as t')
            ->join('N_WEB_CAREERS_Lamaran as l', 'l.Id_Lamaran', '=', 't.Lamaran_Id')
            ->where('t.Id_Lamaran_Tahap', $realId)
            ->where('l.Id_Users', $userId)
            ->select('t.Id_Lamaran_Tahap', 't.Lamaran_Id', 't.Formulir_Kode', 't.Formulir_Versi',
                't.Status', 't.Formulir_Pengisian_Id')
            ->first();
    }

    /**
     * GET /api/v1/lamaran/tahap/{id}/draf — pulihkan isian yang tertunda.
     *
     * Selalu 200. Belum pernah menyimpan draf bukan kesalahan — front-end cukup
     * menerima `draf: null` lalu memulai dari isian kosong seperti biasa.
     */
    public function ambil(string $id)
    {
        $tahap = $this->tahapMilikSaya($id);
        if (! $tahap) {
            return ResponseHelper::error('Tahap tidak ditemukan.', 404);
        }

        $d = DB::table('N_WEB_CAREERS_Formulir_Draf')
            ->where('Lamaran_Tahap_Id', $tahap->Id_Lamaran_Tahap)
            ->where('Id_Users', (int) session('career_auth.id'))
            ->first();

        if (! $d) {
            return ResponseHelper::success(['draf' => null], 'Belum ada simpanan sementara.');
        }

        // Berkas dikembalikan sebagai metadata + URL endpoint kita sendiri.
        // Path GCS-nya sengaja TIDAK ikut keluar: browser tidak butuh, dan
        // membocorkannya memberi petunjuk susunan bucket tanpa guna.
        $berkas = collect(BerkasBaris::daftar($d->Berkas_Json ?? null))
            ->map(fn ($b) => [
                'bagian' => $b['bagian'] ?? null,
                'baris' => $b['baris'] ?? null,
                'field' => $b['field'] ?? null,
                'nama' => $b['nama'] ?? null,
                'ukuran' => (int) ($b['ukuran'] ?? 0),
                'mime' => $b['mime'] ?? null,
                // bagian/baris ikut sebagai query string, BUKAN segmen rute:
                // tautan pratinjau yang sudah beredar di draf lama tidak
                // membawa keduanya, dan harus tetap sah.
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
                // Nilai mentah dari SQL Server sudah berupa string; membungkusnya
                // dengan optional()->__toString() justru menghasilkan null.
                'disimpanAt' => (string) ($d->Updated_At ?: $d->Created_At),
            ],
        ], 'Simpanan sementara ditemukan.');
    }

    /**
     * POST /api/v1/lamaran/tahap/{id}/draf — simpan setiap kali "Lanjut".
     *
     * Sengaja SINKRON, tidak lewat antrean: kandidat berhak tahu detik itu juga
     * bahwa isiannya aman. Menaruhnya di antrean berarti menampilkan "tersimpan"
     * sebelum benar-benar tersimpan — janji yang belum tentu ditepati.
     */
    public function simpan(Request $request, string $id)
    {
        $tahap = $this->tahapMilikSaya($id);
        if (! $tahap) {
            return ResponseHelper::error('Tahap tidak ditemukan.', 404);
        }

        // Tahap yang sudah diputus — atau formulirnya sudah terkirim — tidak
        // boleh menerima draf baru: isinya sudah jadi bagian keputusan seleksi,
        // dan draf yang lahir sesudah kiriman tak pernah dibaca siapa pun.
        if (self::tertutup($tahap)) {
            return ResponseHelper::error(self::PESAN_TERTUTUP, 409);
        }
        if ($pesan = BatasIsi::pesanKunci((int) $tahap->Id_Lamaran_Tahap)) {
            return ResponseHelper::error($pesan, 409);
        }

        // `present`, BUKAN `required`.
        //
        // Laravel menganggap array kosong sebagai "kosong", sehingga `required`
        // menolak draf yang jawabannya memang belum ada isinya — padahal itu
        // keadaan paling wajar: kandidat menekan Lanjut di langkah yang seluruh
        // isiannya opsional, atau langkah pertama belum sempat diketik. Yang
        // benar-benar wajib adalah kuncinya HADIR, bukan berisi.
        $data = $request->validate([
            'jawaban' => 'present|array',
            'langkah' => 'nullable|integer|min:0|max:50',
            'komponen' => 'nullable|string|max:60',
            'schema' => 'nullable|array',
        ]);

        $userId = (int) session('career_auth.id');
        $nama = session('career_auth.nama');
        $now = now();

        $isi = [
            'Lamaran_Id' => $tahap->Lamaran_Id,
            'Lamaran_Tahap_Id' => $tahap->Id_Lamaran_Tahap,
            'Id_Users' => $userId,
            'Formulir_Kode' => $tahap->Formulir_Kode,
            'Komponen_Kode' => $data['komponen'] ?? null,
            'Langkah_Terakhir' => (int) ($data['langkah'] ?? 0),
            'Jawaban_Json' => json_encode($data['jawaban'], JSON_UNESCAPED_UNICODE),
            'Updated_At' => $now,
            'Updated_By' => $nama,
            'Updated_By_Id' => $userId,
        ];

        if (! empty($data['schema']) && FormulirSchema::punyaKolomDrafSnapshot()) {
            $isi['Schema_Snapshot_Json'] = json_encode($data['schema'], JSON_UNESCAPED_UNICODE);
        }

        // Lewat kunci yang sama dengan unggahan berkas: tanpa itu, simpan pertama
        // yang berbarengan dengan unggahan pertama sama-sama mencoba menyisipkan
        // baris draf, dan salah satunya jatuh di indeks unik.
        try {
            DB::transaction(function () use ($tahap, $userId, $isi, $now, $nama) {
                self::kunciDraf((int) $tahap->Id_Lamaran_Tahap, $userId);

                DB::table('N_WEB_CAREERS_Formulir_Draf')->updateOrInsert(
                    ['Lamaran_Tahap_Id' => $tahap->Id_Lamaran_Tahap, 'Id_Users' => $userId],
                    $isi + ['Created_At' => $now, 'Created_By' => $nama, 'Created_By_Id' => $userId],
                );
            });
        } catch (\DomainException $e) {
            return ResponseHelper::error($e->getMessage(), 409);
        }

        return ResponseHelper::success(['disimpanAt' => $now->toIso8601String()], 'Tersimpan sementara.');
    }

    /**
     * POST /api/v1/lamaran/tahap/{id}/draf/berkas — unggah berkas draf ke GCS.
     *
     * Berkas naik lewat SERVER, tidak lewat signed upload URL langsung dari
     * browser: dengan begini ukuran, tipe, dan kepemilikan diperiksa sebelum
     * satu byte pun mendarat di bucket.
     */
    /**
     * Periksa triplet (bagian, baris, field) terhadap SKEMA YANG DIBEKUKAN.
     *
     * Sebelum ini `field` diterima sebagai string bebas dan `maks_baris` hanya
     * ditegakkan di browser (BagianRenderer.vue:48). Selama satu field berarti
     * satu berkas, longgarnya tidak terasa; begitu tiap baris bisa membawa
     * berkas 5 MB, endpoint ini bisa dibanjiri tanpa batas.
     *
     * Skema yang tidak terbaca TIDAK menolak apa pun. Formulir lama dibuat
     * sebelum mekanisme pembekuan versi ada, dan menolak unggahan karena
     * skemanya tak ketemu berarti menghukum kandidat atas riwayat kode kita.
     *
     * @return string|null pesan galat, atau null bila sah
     */
    public static function periksaBaris(?array $schema, ?string $bagian, ?int $baris, string $field): ?string
    {
        // Tanpa bagian/baris = berkas biasa di luar bagian berulang.
        if ($bagian === null || $baris === null) {
            return null;
        }

        if (! $schema) {
            return null;
        }

        foreach (($schema['langkah'] ?? []) as $langkah) {
            foreach (array_values((array) ($langkah['bagian'] ?? [])) as $iB => $b) {
                // Dicocokkan lewat BerkasBaris::kunciBagian(), bukan `key`
                // mentah: bagian tanpa `key` adalah bentuk yang sah, dan
                // browser menurunkan kuncinya dari judul — atau dari urutannya
                // ("Bagian N") bila judulnya pun tak diisi. Mencocokkan `key`
                // saja akan menolak unggahan yang benar-benar sah.
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

    public function unggahBerkas(Request $request, string $id)
    {
        $tahap = $this->tahapMilikSaya($id);
        if (! $tahap) {
            return ResponseHelper::error('Tahap tidak ditemukan.', 404);
        }

        // Unggahan yang tiba SETELAH formulir terkirim dulu tetap diterima dan
        // melahirkan draf baru yang tak pernah diadopsi — berkasnya menetap di
        // bucket tanpa pemilik. Ditolak sebelum satu byte pun naik ke GCS;
        // diperiksa sekali lagi di bawah kunci (kunciDraf).
        if (self::tertutup($tahap)) {
            return ResponseHelper::error(self::PESAN_TERTUTUP, 409);
        }
        // Ditolak SEBELUM satu byte naik ke GCS — sama alasannya dengan di atas.
        if ($pesan = BatasIsi::pesanKunci((int) $tahap->Id_Lamaran_Tahap)) {
            return ResponseHelper::error($pesan, 409);
        }

        // Batas ukuran DAN batas PHP dua-duanya berlaku. Bila post_max_size lebih
        // kecil, berkas besar tidak pernah sampai ke validator — $_FILES kosong
        // dan pesannya jadi membingungkan. Karena itu diperiksa lebih dulu.
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

        // Ditegakkan terhadap skema yang DIBEKUKAN saat tahap dibuat — jalan yang
        // sama dengan yang dipakai saat merender formulirnya.
        $skema = FormulirSchema::byKodeDanVersi(
            $tahap->Formulir_Kode,
            $tahap->Formulir_Versi !== null ? (int) $tahap->Formulir_Versi : null,
        )['schema'] ?? null;

        if ($galat = self::periksaBaris($skema, $bagian, $barisIdx, $data['field'])) {
            return ResponseHelper::error($galat, 422);
        }

        $file = $request->file('berkas');

        // Format & ukuran mengikuti SKEMA isian ini, bukan angka tetap. Dulu
        // server memakai 5 MB & pdf/jpg/png untuk semua isian, sementara layar
        // menuruti `maks_mb`/`accept` dari Master Formulir — berkas yang di
        // layar dinyatakan boleh ditolak server, dan penolakan itu cuma
        // muncul sebagai notifikasi sekilas sementara nama berkasnya tetap
        // tercatat di jawaban.
        $aturan = BerkasFormulir::aturanUnggah($skema, $bagian, $data['field'], self::MAKS_MB_BAWAAN);
        if ($galat = BerkasFormulir::periksaBerkas($file, $aturan)) {
            return ResponseHelper::error($galat, 422);
        }

        $userId = (int) session('career_auth.id');

        // Struktur folder MENGIKUTI apply-form: tahun/bulan/tanggal/nama —
        // lihat GcsBerkas::folderTahap(). Menelusuri berkas seorang kandidat di
        // bucket jadi tidak menuntut hafal dua pola yang berbeda.
        $gcs = app(GcsBerkas::class);
        $now = now();
        $folder = $gcs->folderTahap(
            $now->format('Y'),
            $now->format('m'),
            $now->format('d'),
            (string) (session('career_auth.nama') ?: 'kandidat'),
        );

        // Ekstensi: pola SAMA dengan apply — jatuh ke extension() bila nama
        // berkas tidak membawa ekstensi.
        $ext = strtolower($file->getClientOriginalExtension() ?: $file->extension());

        try {
            // Unggah lewat GcsBerkas — jalur yang SAMA dengan formulir apply dan
            // sudah terbukti jalan di server. Dua jalur lain (putFileAs dan
            // writeStream) sempat dicoba dan dua-duanya melempar
            // "Path cannot be empty" pada adapter GCS proyek ini; jangan diulang
            // tanpa mengganti adapternya lebih dulu.
            //
            // Memori aman: periksaBerkas() di atas sudah memotong berkas yang
            // melebihi batas isiannya (paling besar 20 MB) sebelum isinya dibaca.
            // getContent(), BUKAN file_get_contents(getRealPath()).
            //
            // INI PENYEBAB "Path cannot be empty" selama ini. Di bawah Apache,
            // getRealPath() bisa mengembalikan string KOSONG untuk berkas unggahan
            // sementara, sehingga isinya gagal dibaca dan path yang terbentuk ikut
            // kosong. Di CLI getRealPath() selalu benar — itulah kenapa setiap uji
            // baris perintah lolos sementara browser selalu gagal.
            //
            // Jebakan yang sama sudah didokumentasikan di jalur apply
            // (LamaranController::apply): "andal (getPathname), bukan getRealPath
            // yang bisa kosong di Apache". Sekarang keduanya memakai cara yang sama.
            $konten = $file->getContent();

            // CATATAN: GcsBerkas::validasi() TIDAK dipanggil di sini — aturannya
            // milik skema isian ini dan sudah diperiksa periksaBerkas() di atas.
            //
            // Nama objek SELALU unik (unggahUnik). Dulu berkas biasa memakai
            // path deterministik — tanggal/nama-kandidat/field — sehingga dua
            // kandidat bernama sama yang mengunggah CV di hari yang sama menulis
            // ke objek yang sama, dan dua unggahan beruntun untuk isian yang sama
            // bisa berakhir dengan isi berkas A tapi metadata berkas B. Sampah
            // tidak menumpuk: objek lama triplet ini dihapus setelah yang baru
            // tercatat (lihat di bawah).
            $path = $gcs->unggahUnik($folder, $data['field'], $ext, $konten);
            unset($konten);
        } catch (\Throwable $e) {
            // Jejak LENGKAP. Pesan "Path cannot be empty" saja tidak menyebut
            // baris mana yang melemparnya, dan tiga percobaan perbaikan sempat
            // salah sasaran karena itu. Nilai folder/berkas ikut dicatat supaya
            // ketahuan bagian mana yang kosong tanpa perlu menebak.
            Log::channel('web_career')->error('[DRAF] unggah GCS gagal: ' . $e->getMessage(), [
                'kelas' => get_class($e),
                'di' => $e->getFile() . ':' . $e->getLine(),
                'folder' => $folder,
                'field' => $data['field'],
                'ext' => $ext,
                'nama_asli' => $file->getClientOriginalName(),
                'ukuran' => $file->getSize(),
                'nama_sesi' => session('career_auth.nama'),
                'disk' => GcsBerkas::DISK,
                'bucket' => config('filesystems.disks.' . GcsBerkas::DISK . '.bucket'),
                'jejak' => collect($e->getTrace())->take(5)
                    ->map(fn ($t) => ($t['class'] ?? '') . ($t['type'] ?? '') . ($t['function'] ?? '')
                        . ' @ ' . basename($t['file'] ?? '?') . ':' . ($t['line'] ?? '?'))
                    ->all(),
            ]);

            return ResponseHelper::error(
                'Berkas gagal diunggah: ' . Str::limit($e->getMessage(), 160),
                500,
            );
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

        // Baris draf mungkin belum ada bila kandidat mengunggah sebelum menekan
        // "Lanjut" sekali pun — jadi dibuat di sini bila perlu. Seluruh baca-
        // ubah-tulisnya di bawah kunci kunciDraf(): tanpa itu dua unggahan yang
        // berbarengan saling menghapus entri (lihat kunciDraf()).
        $kunci = ['Lamaran_Tahap_Id' => $tahap->Id_Lamaran_Tahap, 'Id_Users' => $userId];

        try {
            $lama = DB::transaction(function () use ($tahap, $userId, $kunci, $meta) {
                $baris = self::kunciDraf((int) $tahap->Id_Lamaran_Tahap, $userId);

                // DAFTAR, bukan peta berkunci field. Peta hanya sanggup memuat
                // satu entri per field, dan itulah yang meruntuhkan tiga
                // sertifikat jadi satu.
                $semua = BerkasBaris::daftar($baris->Berkas_Json ?? null);

                // Yang diganti HANYA entri dengan triplet yang sama persis. Versi
                // sebelumnya membuang semua entri sefield — termasuk milik baris lain.
                $lama = null;
                $sisa = [];
                foreach ($semua as $e) {
                    if (BerkasBaris::cocok($e, $meta['bagian'], $meta['baris'], $meta['field'])) {
                        $lama = $e['path'] ?? null;
                        continue;
                    }
                    $sisa[] = $e;
                }
                $sisa[] = $meta;

                DB::table('N_WEB_CAREERS_Formulir_Draf')->updateOrInsert($kunci, [
                    'Lamaran_Id' => $tahap->Lamaran_Id,
                    'Formulir_Kode' => $tahap->Formulir_Kode,
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
            // Objek yang BARU naik tidak dicatat di mana pun — dibuang, supaya
            // tidak menetap di bucket tanpa pemilik.
            $gcs->hapus([$path]);

            if ($e instanceof \DomainException) {
                return ResponseHelper::error($e->getMessage(), 409);
            }

            Log::channel('web_career')->error('[DRAF] berkas gagal dicatat: ' . $e->getMessage(), [
                'tahap' => $tahap->Id_Lamaran_Tahap,
                'field' => $data['field'],
            ]);

            return ResponseHelper::error('Berkas belum tercatat di server. Coba unggah ulang.', 500);
        }

        // Berkas lama untuk TRIPLET yang sama dibuang — draf hanya menyimpan
        // versi terakhir tiap baris, dan menyisakannya berarti bucket menumpuk
        // sampah diam-diam. Baris lain di bagian yang sama tidak tersentuh.
        // Dikerjakan SESUDAH transaksi: kalau pencatatannya batal, objek lama
        // masih dipakai draf dan tidak boleh ikut hilang.
        if ($lama && $lama !== $path) {
            try {
                Storage::disk(GcsBerkas::DISK)->delete($lama);
            } catch (\Throwable $e) {
                Log::channel('web_career')->warning('[DRAF] berkas lama gagal dihapus: ' . $e->getMessage());
            }
        }

        return ResponseHelper::success([
            'bagian' => $bagian,
            'baris' => $barisIdx,
            'field' => $data['field'],
            'nama' => $meta['nama'],
            'ukuran' => $meta['ukuran'],
            'mime' => $meta['mime'],
            // bagian/baris ikut, sama seperti ambil(): tanpa keduanya pratinjau
            // berkas baris berulang yang baru diunggah menunjuk isian biasa
            // bernama sama — dan tidak ketemu.
            'url' => route('career.portal.draf.berkas', array_filter([
                'id' => $id,
                'field' => $data['field'],
                'bagian' => $bagian,
                'baris' => $barisIdx,
            ], fn ($v) => $v !== null)),
        ], 'Berkas tersimpan sementara.');
    }

    /**
     * DELETE /lamaran/tahap/{id}/draf/berkas — buang berkas SATU BARIS.
     *
     * Dipanggil saat kandidat menghapus baris di bagian berulang. Dua hal
     * dikerjakan sekaligus dan sengaja tidak dipisah: entri barisnya dibuang
     * (berikut objeknya di GCS, yang tanpa ini menetap di bucket selamanya),
     * lalu indeks entri di atasnya diturunkan satu.
     *
     * Penggeseran hidup HANYA di sini. Menghitungnya ulang di sisi Vue berarti
     * dua sumber kebenaran untuk satu urutan — dan yang satu pasti menyimpang.
     */
    public function hapusBerkas(Request $request, string $id)
    {
        $tahap = $this->tahapMilikSaya($id);
        if (! $tahap) {
            return ResponseHelper::error('Tahap tidak ditemukan.', 404);
        }

        $data = $request->validate([
            'bagian' => 'required|string|max:60',
            'baris' => 'required|integer|min:0|max:99',
        ]);

        $userId = (int) session('career_auth.id');
        $kunci = ['Lamaran_Tahap_Id' => $tahap->Id_Lamaran_Tahap, 'Id_Users' => $userId];
        $bagian = $data['bagian'];
        $idx = (int) $data['baris'];

        // Baca-ubah-tulis daftar yang SAMA dengan unggahBerkas(), jadi lewat
        // kunci yang sama pula — penggeseran indeks yang berbarengan dengan
        // unggahan baris lain tidak boleh saling menghapus.
        try {
            $buang = DB::transaction(function () use ($tahap, $userId, $kunci, $bagian, $idx) {
                $baris = self::kunciDraf((int) $tahap->Id_Lamaran_Tahap, $userId);
                if (! $baris) {
                    return null;
                }

                $semua = BerkasBaris::daftar($baris->Berkas_Json ?? null);

                // Path yang akan yatim dicatat SEBELUM digeser — sesudahnya entri
                // itu sudah tidak ada dan objeknya tidak akan pernah ketemu lagi.
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

        // Best-effort, persis seperti pembersihan berkas lama di unggahBerkas():
        // gagal menyapu satu objek tidak pantas menggagalkan penghapusan baris
        // yang di basis data sudah tuntas.
        foreach ($buang as $p) {
            try {
                Storage::disk(GcsBerkas::DISK)->delete($p);
            } catch (\Throwable $e) {
                Log::channel('web_career')->warning('[DRAF] berkas baris gagal dihapus: ' . $e->getMessage());
            }
        }

        return ResponseHelper::success(['dibuang' => count($buang)], 'Berkas baris dibuang.');
    }

    /**
     * GET /api/v1/lamaran/tahap/{id}/draf/berkas/{field} — pratinjau berkas draf.
     *
     * Menerbitkan signed URL GCS berumur 15 menit lalu mengalihkan ke sana.
     * Tautannya tidak pernah disimpan di mana pun dan kedaluwarsa sendiri, jadi
     * kalaupun tersalin ke luar, umurnya pendek.
     */
    public function berkas(Request $request, string $id, string $field)
    {
        $tahap = $this->tahapMilikSaya($id);
        if (! $tahap) {
            abort(404);
        }

        $d = DB::table('N_WEB_CAREERS_Formulir_Draf')
            ->where('Lamaran_Tahap_Id', $tahap->Id_Lamaran_Tahap)
            ->where('Id_Users', (int) session('career_auth.id'))
            ->value('Berkas_Json');

        // Tautan lama tidak membawa bagian/baris dan itu SAH — artinya berkas
        // biasa di luar bagian berulang. Rute yang sudah beredar di draf lama
        // karena itu tidak patah.
        $bagian = $request->query('bagian');
        $barisQ = $request->query('baris');
        $baris = ($barisQ === null || $barisQ === '') ? null : (int) $barisQ;

        $path = null;
        foreach (BerkasBaris::daftar($d) as $b) {
            if (BerkasBaris::cocok($b, $bagian, $baris, $field)) {
                $path = $b['path'] ?? null;
                break;
            }
        }

        if (! $path) {
            abort(404);
        }

        try {
            $gcs = Storage::disk(GcsBerkas::DISK);
            if ($gcs->exists($path)) {
                return redirect()->away($gcs->temporaryUrl($path, now()->addMinutes(15)));
            }
        } catch (\Throwable $e) {
            Log::channel('web_career')->warning('[DRAF] signed URL gagal: ' . $e->getMessage());
        }

        abort(404);
    }

    /**
     * PINDAHKAN berkas draf jadi berkas PERMANEN milik pengisian.
     *
     * Ini yang selama ini hilang. Alurnya dulu: berkas naik ke GCS sebagai draf,
     * formulir dikirim, lalu bersihkan() MENGHAPUS draf berikut berkasnya —
     * sehingga yang tersisa di pengisian hanya NAMA berkas sebagai teks jawaban,
     * sementara isinya lenyap dari bucket. Kandidat melihat nama berkas yang
     * tidak bisa dibuka, dan tim rekrutmen kehilangan dokumennya.
     *
     * Berkasnya TIDAK disalin ulang di GCS — objek yang sama dipakai, hanya
     * kepemilikannya yang berpindah ke Formulir_Berkas. Karena itu pemanggil
     * WAJIB memakai bersihkan(..., hapusBerkas: false) sesudah ini, kalau tidak
     * berkas yang baru saja diadopsi ikut terhapus.
     *
     * @return int jumlah berkas yang berhasil dicatat
     */
    public static function jadikanPermanen(int $tahapId, int $userId, int $pengisianId, array $jawaban = []): int
    {
        $draf = DB::table('N_WEB_CAREERS_Formulir_Draf')
            ->where('Lamaran_Tahap_Id', $tahapId)
            ->where('Id_Users', $userId)
            ->first();

        if (! $draf) {
            return 0;
        }

        // Menerima dua bentuk: daftar baru, dan peta berkunci field milik draf
        // yang dibuat sebelum berkas baris berulang ada.
        $semua = BerkasBaris::daftar($draf->Berkas_Json ?? null);
        if (! $semua) {
            return 0;
        }

        $nama = session('career_auth.nama');
        $now = now();
        $urutan = 0;
        $jumlah = 0;

        foreach ($semua as $b) {
            if (empty($b['path']) || empty($b['field'])) {
                continue;
            }

            $bagian = $b['bagian'] ?? null;
            $idx = $b['baris'] ?? null;

            // BARIS YATIM: kandidat mengunggah lalu menghapus barisnya sebelum
            // mengirim. Mengesahkannya membuat laporan memuat sertifikat yang
            // barisnya sudah tidak ada di jawaban mana pun.
            if ($bagian !== null && $idx !== null) {
                $barisJawaban = $jawaban[$bagian] ?? null;
                if (! is_array($barisJawaban) || ! array_key_exists($idx, $barisJawaban)) {
                    continue;
                }
            }

            $urutan++;

            // Idempoten: kirim ulang untuk pengisian yang sama tidak menggandakan.
            //
            // Penjaganya memakai TRIPLET. Versi sebelumnya hanya (pengisian,
            // field) — dan karena tiap baris berulang memakai field yang sama
            // persis, dua sertifikat berikutnya dianggap duplikat lalu dibuang.
            // Itulah yang membuat pengisian 61 mencatat tiga nama berkas tapi
            // hanya menyimpan satu.
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
                'Nama_Asli' => $b['nama'] ?? ('berkas.' . $ext),
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

            $jumlah++;
        }

        return $jumlah;
    }

    /**
     * Buang draf sebuah tahap berikut berkasnya di GCS.
     *
     * Dipanggil setelah formulir BERHASIL dikirim: menyisakan draf membuat
     * kandidat yang membuka halaman lagi melihat isian lama seolah belum
     * terkirim. Aman dipanggil walau draf tidak ada.
     */
    public static function bersihkan(int $tahapId, int $userId, bool $hapusBerkas = true): void
    {
        $baris = DB::table('N_WEB_CAREERS_Formulir_Draf')
            ->where('Lamaran_Tahap_Id', $tahapId)
            ->where('Id_Users', $userId)
            ->first();

        if (! $baris) {
            return;
        }

        foreach (($hapusBerkas ? (json_decode($baris->Berkas_Json ?: '{}', true) ?: []) : []) as $b) {
            if (! empty($b['path'])) {
                try {
                    Storage::disk(GcsBerkas::DISK)->delete($b['path']);
                } catch (\Throwable $e) {
                    Log::channel('web_career')->warning('[DRAF] sisa berkas gagal dihapus: ' . $e->getMessage());
                }
            }
        }

        DB::table('N_WEB_CAREERS_Formulir_Draf')
            ->where('Id_Formulir_Draf', $baris->Id_Formulir_Draf)
            ->delete();
    }
}
