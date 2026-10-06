<?php

namespace App\Http\Controllers\Career\MasterSkrining;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Support\Career\AksesService;
use App\Support\Career\BankPertanyaan;
use App\Support\Career\KodeOtomatis;
use App\Support\Career\MesinSyarat;
use App\Support\Career\Skrining;
use App\Support\CareerShell;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

/**
 * WEB CAREER — MASTER PHONE SCREENING: pustaka template pertanyaan.
 *
 * Satu template = satu daftar pertanyaan yang dipakai rekruter saat menskrining
 * kandidat. Templatenya BERVERSI, dan itu bukan kemewahan: sesi yang sudah
 * tercatat harus tetap terbaca dengan pertanyaan yang benar-benar ditanyakan
 * saat itu, bukan dengan pertanyaan hasil revisi tahun berikutnya.
 *
 * ── SATU ATURAN YANG MENJELASKAN SELURUH BERKAS INI ─────────────────────────
 *
 * VERSI PUBLISHED TIDAK PERNAH DISUNTING. Titik.
 *
 * Menyunting template yang sudah terbit selalu berarti: klon seluruh
 * pertanyaannya ke versi DRAFT baru, sunting di sana, lalu terbitkan. Versi
 * lama jadi ARCHIVED — tidak dihapus, karena ia satu-satunya cara membaca ulang
 * sesi yang memakainya.
 *
 * Konsekuensinya template 50 pertanyaan yang direvisi 10 kali meninggalkan 500
 * baris. Itu murah, dan imbalannya besar.
 *
 * ── KENAPA PERTANYAANNYA RELASIONAL, BUKAN Schema_Json ──────────────────────
 *
 * Master Formulir menyimpan skemanya sebagai JSON dan itu tepat di sana:
 * formulir kandidat punya logika tampil-sembunyi bertingkat yang tidak akan
 * pernah diagregasi. Skrining sebaliknya — pertanyaan "berapa gaji harapan"
 * justru ADA untuk dijumlahkan lintas kandidat. Sistem ini sudah pernah
 * menempuh jalan JSON dan harus menambalnya dengan Formulir_Jawaban_Index;
 * langsung relasional menghindari tabel indeks kedua.
 */
class MasterSkriningController extends Controller
{
    private const PAGE = 'masterSkriningPage';

    public function index()
    {
        return Inertia::render(
            'Career/admin/master-skrining/masterSkrining',
            CareerShell::props('/master-skrining', 'Master Template Skrining')
        );
    }

    // ═══════════════════════════ BACA ═══════════════════════════

    /**
     * Daftar template + ringkasan versinya + berapa kali sudah dipakai.
     *
     * Angka pemakaian dikirim bersama daftarnya karena ia yang menjawab
     * pertanyaan yang muncul tepat sebelum tombol hapus ditekan: "kalau saya
     * buang ini, ada berapa sesi yang kehilangan artinya?"
     */
    public function list()
    {
        if (! Skrining::siap()) {
            return ResponseHelper::success(
                ['rows' => [], 'siap' => false],
                'Skema skrining belum dijalankan.'
            );
        }

        try {
            $versi = DB::table(Skrining::T_VERSI)
                ->select('Master_Skrining_Id', 'Versi', 'Status', 'Published_At', 'Id_Master_Skrining_Versi')
                ->orderBy('Master_Skrining_Id')
                ->orderBy('Versi')
                ->get();

            $jmlTanya = DB::table(Skrining::T_TANYA)
                ->select('Master_Skrining_Versi_Id', DB::raw('COUNT(*) as n'))
                ->groupBy('Master_Skrining_Versi_Id')
                ->pluck('n', 'Master_Skrining_Versi_Id');

            $dipakai = DB::table(Skrining::T_SESI)
                ->select('Skrining_Kode', DB::raw('COUNT(*) as n'))
                ->groupBy('Skrining_Kode')
                ->pluck('n', 'Skrining_Kode');

            $terikat = DB::table(Skrining::T_IKAT)
                ->where('Flag_Aktif', 'Y')
                ->select('Skrining_Kode', DB::raw('COUNT(*) as n'))
                ->groupBy('Skrining_Kode')
                ->pluck('n', 'Skrining_Kode');

            $perTemplate = $versi->groupBy('Master_Skrining_Id');

            // ── KATEGORI IKUT HAK AKSES ───────────────────────────────
            //
            // Admin MT tidak boleh melihat template REKRUTMEN, sepola seluruh
            // halaman lain yang peduli kategori. Tanpa penyaringan ini ia
            // melihat template yang tidak pernah bisa ia pakai — dan lebih
            // buruk, bisa menyuntingnya.
            //
            // null = tidak dibatasi. Template lama yang kategorinya belum diisi
            // ikut tampil apa pun izinnya: mengeluarkannya berarti template
            // yang sudah dipakai mendadak hilang dari layar pemiliknya.
            $izinKategori = AksesService::kategoriDiizinkan(self::PAGE);

            $rows = DB::table(Skrining::T_MASTER)
                ->when($izinKategori, fn ($q) => $q->where(fn ($w) => $w
                    ->whereIn('Kategori', $izinKategori)->orWhereNull('Kategori')))
                // Terbaru lebih dulu; panel kiri menyediakan urutan lain bila
                // yang dicari memang berdasarkan nama.
                ->orderByDesc('Created_At')
                ->orderByDesc('Id_Master_Skrining')
                ->get()
                ->map(function ($r) use ($perTemplate, $jmlTanya, $dipakai, $terikat) {
                    $vs = $perTemplate[$r->Id_Master_Skrining] ?? collect();
                    $terbit = $vs->firstWhere('Status', 'PUBLISHED');
                    $draf = $vs->firstWhere('Status', 'DRAFT');

                    return [
                        'id' => (int) $r->Id_Master_Skrining,
                        'kode' => $r->Kode,
                        'nama' => $r->Nama,
                        'deskripsi' => $r->Deskripsi,
                        'petunjuk' => $r->Petunjuk,
                        'kategori' => $r->Kategori,
                        'aktif' => ($r->Flag_Aktif ?? 'Y') === 'Y',
                        'versi' => $vs->map(fn ($v) => [
                            'id' => (int) $v->Id_Master_Skrining_Versi,
                            'versi' => (int) $v->Versi,
                            'status' => $v->Status,
                            'jmlPertanyaan' => (int) ($jmlTanya[$v->Id_Master_Skrining_Versi] ?? 0),
                            'terbitPada' => $v->Published_At ? (string) $v->Published_At : null,
                        ])->values()->all(),
                        'versiTerbit' => $terbit ? (int) $terbit->Versi : null,
                        'versiDraf' => $draf ? (int) $draf->Versi : null,
                        // Berapa banyak loker/program yang MENUNJUK template ini,
                        // dan berapa sesi yang sudah memakainya. Dua angka berbeda:
                        // yang pertama bisa dicabut, yang kedua tidak bisa lagi.
                        'terikat' => (int) ($terikat[$r->Kode] ?? 0),
                        'dipakai' => (int) ($dipakai[$r->Kode] ?? 0),
                        'diperbaruiOleh' => $r->Updated_By ?: $r->Created_By,
                        'diperbaruiPada' => (string) ($r->Updated_At ?: $r->Created_At),
                        // Dipakai penyaring tanggal di panel kiri. Dikirim mentah
                        // (ISO) supaya perbandingannya di layar tidak bergantung
                        // pada bentuk tampilan yang bisa berubah.
                        'dibuatPada' => (string) $r->Created_At,
                        'dibuatOleh' => $r->Created_By,
                    ];
                })
                ->values();

            return ResponseHelper::success([
                'rows' => $rows,
                'siap' => true,
                'tipe' => Skrining::TIPE,
                'tipeBeropsi' => Skrining::TIPE_BEROPSI,
                'tipeBerskala' => Skrining::TIPE_BERSKALA,
                'tipeBerskor' => Skrining::TIPE_BERSKOR,
                'operator' => MesinSyarat::OPERATOR,
                // ── TAG SAJA, BUKAN ISI BANKNYA ────────────────────────────
                //
                // Dulu seluruh isi bank ikut di sini. Sesudah banknya berisi
                // seribu lebih pertanyaan — masing-masing membawa rubrik
                // penilaian, penanda risiko, dan pertanyaan lanjutan — itu
                // berarti memuat beberapa megabita setiap kali halaman dibuka,
                // untuk daftar yang akan disaring jadi belasan baris.
                //
                // Yang dikirim sekarang cuma kosakata penyaringnya. Isinya
                // diambil layar lewat `cariBank` saat pemilih dibuka.
                'tag' => BankPertanyaan::tag(),
                'jenisPertanyaan' => BankPertanyaan::JENIS,
                'prioritasPertanyaan' => BankPertanyaan::PRIORITAS,
                // Kategori yang BOLEH dipakai akun ini. Satu pilihan berarti
                // layar tidak menggambar kotak pilihannya sama sekali — lihat
                // catatan di masterSkrining.vue.
                'kategoriOpsi' => AksesService::tabKategori(self::PAGE),
            ], 'Master phone screening');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('Gagal memuat master skrining: '.$e->getMessage());

            return ResponseHelper::error('Gagal memuat data.', 500);
        }
    }

    /** Pertanyaan satu versi. */
    public function pertanyaan(int $versiId)
    {
        if (! Skrining::siap()) {
            return ResponseHelper::error('Skema skrining belum dijalankan.', 409);
        }

        $versi = DB::table(Skrining::T_VERSI)->where('Id_Master_Skrining_Versi', $versiId)->first();
        if (! $versi) {
            return ResponseHelper::error('Versi tidak ditemukan.', 404);
        }

        return ResponseHelper::success([
            'versi' => (int) $versi->Versi,
            'status' => $versi->Status,
            'catatan' => $versi->Catatan,
            'terbitPada' => $versi->Published_At ? (string) $versi->Published_At : null,
            // true = ikut membawa bankId; penyusun mengirimnya balik saat
            // menyimpan supaya "Segarkan dari pustaka" tahu asal tiap baris.
            'pertanyaan' => Skrining::pertanyaan($versiId, true),
        ], 'Pertanyaan versi '.$versi->Versi);
    }

    // ═══════════════════════════ TEMPLATE ═══════════════════════════

    /**
     * Kategori ini boleh dipakai akun yang sedang masuk?
     *
     * Diperiksa di SERVER, bukan cukup disembunyikan di layar. Kotak yang tidak
     * digambar tidak menghalangi siapa pun mengirim payload sendiri, dan
     * kategori adalah pembatas yang menentukan siapa melihat template siapa.
     */
    private function kategoriBoleh(?string $kategori): bool
    {
        if (! $kategori) {
            return true;
        }

        $izin = AksesService::kategoriDiizinkan(self::PAGE);

        return $izin === null || in_array($kategori, $izin, true);
    }

    /**
     * Kategori bawaan bila akunnya cuma berhak satu.
     *
     * Layar memang sudah memasangnya otomatis, tapi payload yang datang tanpa
     * kategori — dari layar lama yang belum dimuat ulang, misalnya — tetap
     * harus menghasilkan template yang benar, bukan template tanpa kategori
     * yang kemudian terlihat oleh semua orang.
     */
    private function kategoriBawaan(): ?string
    {
        $izin = AksesService::kategoriDiizinkan(self::PAGE);

        return ($izin !== null && count($izin) === 1) ? $izin[0] : null;
    }

    private function aturanHeader(Request $request): array
    {
        return $request->validate([
            // Kosong = dibuatkan sistem dari namanya. Tetap diterima bila
            // diisi, karena kode template yang sudah beredar di pengikatan
            // loker harus bisa dipertahankan apa adanya.
            'kode' => 'nullable|string|max:30|regex:/^[A-Z0-9_-]+$/',
            'nama' => 'required|string|max:120',
            'deskripsi' => 'nullable|string|max:500',
            'petunjuk' => 'nullable|string|max:1000',
            'kategori' => 'nullable|string|max:20',
        ], [
            'kode.regex' => 'Kode hanya boleh huruf besar, angka, garis bawah, dan strip (mis. SKR-HR-UMUM).',
        ]);
    }

    /**
     * Template baru — langsung dengan versi 1 berstatus DRAFT.
     *
     * Template tanpa versi adalah keadaan yang tidak berguna: ia tidak bisa
     * diisi pertanyaan, tidak bisa diterbitkan, dan tidak bisa diikat ke loker.
     * Membuatnya sekalian di sini menghapus satu keadaan setengah jadi yang
     * harus dijaga di sepanjang sisa modul.
     */
    public function store(Request $request)
    {
        if (! Skrining::siap()) {
            return ResponseHelper::error('Skema skrining belum dijalankan.', 409);
        }

        $data = $this->aturanHeader($request);
        $data['kategori'] = $data['kategori'] ?? $this->kategoriBawaan();

        if (! $this->kategoriBoleh($data['kategori'] ?? null)) {
            return ResponseHelper::error('Anda tidak berhak membuat template untuk kategori itu.', 403);
        }

        $otomatis = empty($data['kode']);

        if (! $otomatis && DB::table(Skrining::T_MASTER)->where('Kode', $data['kode'])->exists()) {
            return ResponseHelper::error('Kode "'.$data['kode'].'" sudah dipakai template lain.', 422);
        }

        if ($otomatis) {
            // Ditebak dari satu pembacaan indeks, tanpa kunci apa pun. Wasit
            // sesungguhnya adalah indeks unik pada kolom Kode; kalau ia menolak,
            // yang gagal cuma satu penyimpanan, bukan seluruh tabel yang tertahan.
            $data['kode'] = KodeOtomatis::template($data['nama'], $data['kategori'] ?? null);
        }

        try {
            $id = DB::transaction(function () use ($data) {
                $id = DB::table(Skrining::T_MASTER)->insertGetId([
                    'Kode' => $data['kode'],
                    'Nama' => $data['nama'],
                    'Deskripsi' => $data['deskripsi'] ?? null,
                    'Petunjuk' => $data['petunjuk'] ?? null,
                    'Kategori' => $data['kategori'] ?? null,
                    'Flag_Aktif' => 'Y',
                ] + $this->capBuat(), 'Id_Master_Skrining');

                DB::table(Skrining::T_VERSI)->insert([
                    'Master_Skrining_Id' => $id,
                    'Versi' => 1,
                    'Status' => 'DRAFT',
                ] + $this->capBuat());

                return $id;
            });

            $this->catat('ditambahkan', $data['nama'].' ('.$data['kode'].')');

            return ResponseHelper::success(['id' => $id], 'Template dibuat. Versi 1 siap diisi pertanyaan.');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('Gagal menambah template skrining: '.$e->getMessage());

            return ResponseHelper::error('Gagal menyimpan.', 500);
        }
    }

    /**
     * Sunting kepala template.
     *
     * Kode BOLEH diubah selama template belum pernah dipakai sesi mana pun.
     * Sesudah dipakai, kode itulah yang menyambungkan sesi tercatat dengan
     * masternya — menggantinya memutus tautan tanpa jejak. Namanya tetap boleh
     * diperbaiki kapan saja: yang dibekukan di sesi memang salinannya.
     */
    public function update(Request $request, int $id)
    {
        if (! Skrining::siap()) {
            return ResponseHelper::error('Skema skrining belum dijalankan.', 409);
        }

        $baris = DB::table(Skrining::T_MASTER)->where('Id_Master_Skrining', $id)->first();
        if (! $baris) {
            return ResponseHelper::error('Template tidak ditemukan.', 404);
        }

        $data = $this->aturanHeader($request);
        $data['kategori'] = $data['kategori'] ?? $this->kategoriBawaan();

        // Dua-duanya diperiksa: kategori LAMA (boleh menyentuh template ini?)
        // dan kategori BARU (boleh memindahkannya ke sana?). Memeriksa yang
        // baru saja membuat template kategori lain bisa dibajak dengan
        // memindahkannya ke kategori yang memang dimiliki pengirim.
        if (! $this->kategoriBoleh($baris->Kategori)) {
            return ResponseHelper::error('Template itu bukan kategori yang Anda kelola.', 403);
        }

        if (! $this->kategoriBoleh($data['kategori'] ?? null)) {
            return ResponseHelper::error('Anda tidak berhak memindahkannya ke kategori itu.', 403);
        }

        // Formulir sunting tidak lagi mengirim kode; kosong berarti tetap.
        // Kode template menyambungkan pengikatan loker dan sesi yang sudah
        // berjalan — menggantinya diam-diam memutus keduanya.
        if (empty($data['kode'])) {
            $data['kode'] = $baris->Kode;
        }

        if ($data['kode'] !== $baris->Kode) {
            $dipakai = DB::table(Skrining::T_SESI)->where('Skrining_Kode', $baris->Kode)->count();
            if ($dipakai > 0) {
                return ResponseHelper::error(
                    'Kode tidak bisa diubah — sudah dipakai '.$dipakai.' sesi skrining. Namanya boleh.',
                    422
                );
            }

            if (DB::table(Skrining::T_MASTER)->where('Kode', $data['kode'])->where('Id_Master_Skrining', '<>', $id)->exists()) {
                return ResponseHelper::error('Kode "'.$data['kode'].'" sudah dipakai template lain.', 422);
            }
        }

        try {
            DB::table(Skrining::T_MASTER)->where('Id_Master_Skrining', $id)->update([
                'Kode' => $data['kode'],
                'Nama' => $data['nama'],
                'Deskripsi' => $data['deskripsi'] ?? null,
                'Petunjuk' => $data['petunjuk'] ?? null,
                'Kategori' => $data['kategori'] ?? null,
            ] + $this->capUbah());

            // Pengikatan menunjuk lewat KODE, bukan Id — jadi kode yang berganti
            // harus ikut diperbarui di sana, kalau tidak lokernya kehilangan
            // template tanpa satu pun pesan.
            if ($data['kode'] !== $baris->Kode) {
                DB::table(Skrining::T_IKAT)
                    ->where('Skrining_Kode', $baris->Kode)
                    ->update(['Skrining_Kode' => $data['kode']] + $this->capUbah());
            }

            $this->catat('disunting', $data['nama'].' ('.$data['kode'].')');

            return ResponseHelper::success(null, 'Template diperbarui.');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('Gagal menyunting template skrining: '.$e->getMessage());

            return ResponseHelper::error('Gagal menyimpan.', 500);
        }
    }

    /** Aktif ⇄ nonaktif. Yang nonaktif tak lagi ditawarkan untuk pengikatan baru. */
    public function toggle(int $id)
    {
        if (! Skrining::siap()) {
            return ResponseHelper::error('Skema skrining belum dijalankan.', 409);
        }

        $baris = DB::table(Skrining::T_MASTER)->where('Id_Master_Skrining', $id)->first();
        if (! $baris) {
            return ResponseHelper::error('Template tidak ditemukan.', 404);
        }

        $baru = ($baris->Flag_Aktif ?? 'Y') === 'Y' ? 'N' : 'Y';

        DB::table(Skrining::T_MASTER)->where('Id_Master_Skrining', $id)
            ->update(['Flag_Aktif' => $baru] + $this->capUbah());

        $this->catat($baru === 'Y' ? 'diaktifkan' : 'dinonaktifkan', $baris->Nama);

        return ResponseHelper::success(
            ['aktif' => $baru === 'Y'],
            $baru === 'Y'
                ? 'Template diaktifkan — kembali bisa diikat ke loker.'
                : 'Template dinonaktifkan. Sesi yang sedang berjalan tidak berubah.'
        );
    }

    /**
     * Hapus template.
     *
     * DITOLAK begitu ada satu sesi yang memakainya. Sesi menyimpan salinan
     * pertanyaannya sendiri, jadi datanya memang tidak hilang — tapi jejak
     * "template mana yang dipakai" hilang, dan itu yang dibutuhkan saat
     * membandingkan hasil antar-periode.
     */
    public function destroy(int $id)
    {
        if (! Skrining::siap()) {
            return ResponseHelper::error('Skema skrining belum dijalankan.', 409);
        }

        $baris = DB::table(Skrining::T_MASTER)->where('Id_Master_Skrining', $id)->first();
        if (! $baris) {
            return ResponseHelper::error('Template tidak ditemukan.', 404);
        }

        $dipakai = DB::table(Skrining::T_SESI)->where('Skrining_Kode', $baris->Kode)->count();
        if ($dipakai > 0) {
            return ResponseHelper::error(
                'Template sudah dipakai '.$dipakai.' sesi skrining — nonaktifkan saja, jangan dihapus.',
                422
            );
        }

        $terikat = DB::table(Skrining::T_IKAT)->where('Skrining_Kode', $baris->Kode)->count();
        if ($terikat > 0) {
            return ResponseHelper::error(
                'Template masih terpasang di '.$terikat.' loker/program. Lepaskan dulu pengikatannya.',
                422
            );
        }

        try {
            DB::transaction(function () use ($id) {
                $versiIds = DB::table(Skrining::T_VERSI)
                    ->where('Master_Skrining_Id', $id)
                    ->pluck('Id_Master_Skrining_Versi');

                DB::table(Skrining::T_TANYA)->whereIn('Master_Skrining_Versi_Id', $versiIds)->delete();
                DB::table(Skrining::T_VERSI)->where('Master_Skrining_Id', $id)->delete();
                DB::table(Skrining::T_MASTER)->where('Id_Master_Skrining', $id)->delete();
            });

            $this->catat('dihapus', $baris->Nama.' ('.$baris->Kode.')');

            return ResponseHelper::success(null, 'Template dihapus.');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('Gagal menghapus template skrining: '.$e->getMessage());

            return ResponseHelper::error('Gagal menghapus.', 500);
        }
    }

    /** Salin seluruh template beserta versi terbitnya jadi template baru. */
    public function duplikat(Request $request, int $id)
    {
        if (! Skrining::siap()) {
            return ResponseHelper::error('Skema skrining belum dijalankan.', 409);
        }

        $asal = DB::table(Skrining::T_MASTER)->where('Id_Master_Skrining', $id)->first();
        if (! $asal) {
            return ResponseHelper::error('Template tidak ditemukan.', 404);
        }

        $data = $request->validate([
            'kode' => 'nullable|string|max:30|regex:/^[A-Z0-9_-]+$/',
            'nama' => 'required|string|max:120',
        ]);

        if (empty($data['kode'])) {
            $data['kode'] = KodeOtomatis::template($data['nama'], $asal->Kategori ?? null);
        } elseif (DB::table(Skrining::T_MASTER)->where('Kode', $data['kode'])->exists()) {
            return ResponseHelper::error('Kode "'.$data['kode'].'" sudah dipakai template lain.', 422);
        }

        // Yang disalin adalah versi TERBIT; kalau belum ada yang terbit, drafnya.
        // Menyalin draf orang lain yang sedang setengah jadi bukan titik awal
        // yang baik, tapi lebih baik daripada template kosong tanpa penjelasan.
        $sumber = DB::table(Skrining::T_VERSI)->where('Master_Skrining_Id', $id)
            ->where('Status', 'PUBLISHED')->first()
            ?: DB::table(Skrining::T_VERSI)->where('Master_Skrining_Id', $id)
                ->orderByDesc('Versi')->first();

        try {
            $baruId = DB::transaction(function () use ($asal, $data, $sumber) {
                $baruId = DB::table(Skrining::T_MASTER)->insertGetId([
                    'Kode' => $data['kode'],
                    'Nama' => $data['nama'],
                    'Deskripsi' => $asal->Deskripsi,
                    'Petunjuk' => $asal->Petunjuk,
                    'Kategori' => $asal->Kategori,
                    'Flag_Aktif' => 'Y',
                ] + $this->capBuat(), 'Id_Master_Skrining');

                $versiId = DB::table(Skrining::T_VERSI)->insertGetId([
                    'Master_Skrining_Id' => $baruId,
                    'Versi' => 1,
                    'Status' => 'DRAFT',
                    'Catatan' => 'Disalin dari '.$asal->Kode.($sumber ? ' v'.$sumber->Versi : ''),
                ] + $this->capBuat(), 'Id_Master_Skrining_Versi');

                if ($sumber) {
                    $this->klonPertanyaan((int) $sumber->Id_Master_Skrining_Versi, $versiId);
                }

                return $baruId;
            });

            $this->catat('diduplikat', $asal->Kode.' → '.$data['kode']);

            return ResponseHelper::success(['id' => $baruId], 'Template disalin sebagai draf baru.');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('Gagal menduplikat template skrining: '.$e->getMessage());

            return ResponseHelper::error('Gagal menyalin.', 500);
        }
    }

    // ═══════════════════════════ VERSI ═══════════════════════════

    /**
     * Simpan pertanyaan ke versi DRAFT — ganti seluruhnya, bukan tambal.
     *
     * Penyunting di layar mengirim daftar utuh setiap kali disimpan, dan itu
     * disengaja: menyusun ulang urutan, menghapus di tengah, dan menyisipkan
     * baris baru dalam satu simpanan mustahil dinyatakan sebagai beda-per-baris
     * tanpa mengirim id bayangan yang harus dijaga di kedua sisi.
     *
     * DITOLAK untuk versi PUBLISHED. Itu aturan yang tidak punya pengecualian
     * di modul ini.
     */
    public function simpanPertanyaan(Request $request, int $versiId)
    {
        if (! Skrining::siap()) {
            return ResponseHelper::error('Skema skrining belum dijalankan.', 409);
        }

        $versi = DB::table(Skrining::T_VERSI)->where('Id_Master_Skrining_Versi', $versiId)->first();
        if (! $versi) {
            return ResponseHelper::error('Versi tidak ditemukan.', 404);
        }

        if ($versi->Status !== 'DRAFT') {
            return ResponseHelper::error(
                'Versi '.$versi->Versi.' berstatus '.$versi->Status.' dan tidak bisa disunting. '
                .'Buat draf baru untuk mengubah pertanyaannya.',
                422
            );
        }

        $data = $request->validate([
            'catatan' => 'nullable|string|max:2000',
            'pertanyaan' => 'present|array',
            'pertanyaan.*.kode' => 'required|string|max:40|regex:/^[a-z0-9_]+$/',
            'pertanyaan.*.tipe' => 'required|string|in:'.implode(',', Skrining::TIPE),
            'pertanyaan.*.label' => 'required|string|max:500',
            'pertanyaan.*.seksi' => 'nullable|string|max:120',
            'pertanyaan.*.bantuan' => 'nullable|string|max:500',
            'pertanyaan.*.opsi' => 'nullable|array',
            'pertanyaan.*.opsi.*.nilai' => 'required_with:pertanyaan.*.opsi|string|max:120',
            'pertanyaan.*.opsi.*.label' => 'nullable|string|max:200',
            'pertanyaan.*.opsi.*.skor' => 'nullable|numeric|min:-999|max:999',
            'pertanyaan.*.skalaMin' => 'nullable|integer|min:0|max:100',
            'pertanyaan.*.skalaMax' => 'nullable|integer|min:1|max:100',
            'pertanyaan.*.labelMin' => 'nullable|string|max:100',
            'pertanyaan.*.labelMax' => 'nullable|string|max:100',
            'pertanyaan.*.wajib' => 'nullable|boolean',
            'pertanyaan.*.bobot' => 'nullable|numeric|min:0|max:9999',
            'pertanyaan.*.knockout' => 'nullable|boolean',
            'pertanyaan.*.knockoutOperator' => 'nullable|string|in:'.implode(',', MesinSyarat::OPERATOR),
            'pertanyaan.*.knockoutNilai' => 'nullable|string|max:500',
            'pertanyaan.*.knockoutPesan' => 'nullable|string|max:500',
            'pertanyaan.*.catatan' => 'nullable|boolean',
            'pertanyaan.*.tampilJika' => 'nullable|array',
            // Asal-usul bank. Dikirim balik apa adanya oleh layar supaya
            // pertanyaan yang ditarik dari pustaka tidak kehilangan jejaknya
            // begitu drafnya disimpan ulang.
            'pertanyaan.*.bankId' => 'nullable|integer',
            'pertanyaan.*.bankKode' => 'nullable|string|max:40',
        ], [
            'pertanyaan.*.kode.regex' => 'Kode pertanyaan hanya boleh huruf kecil, angka, dan garis bawah (mis. gaji_harapan).',
        ]);

        $salah = $this->periksaPertanyaan($data['pertanyaan']);
        if ($salah) {
            return ResponseHelper::error($salah, 422);
        }

        try {
            DB::transaction(function () use ($versiId, $data) {
                DB::table(Skrining::T_TANYA)->where('Master_Skrining_Versi_Id', $versiId)->delete();

                $urut = 0;
                foreach ($data['pertanyaan'] as $p) {
                    DB::table(Skrining::T_TANYA)->insert(
                        $this->asalBank($this->isiPertanyaan($p, $versiId, ++$urut), $p) + $this->capBuat()
                    );
                }

                DB::table(Skrining::T_VERSI)->where('Id_Master_Skrining_Versi', $versiId)
                    ->update(['Catatan' => $data['catatan'] ?? null] + $this->capUbah());
            });

            return ResponseHelper::success(
                ['jml' => count($data['pertanyaan'])],
                count($data['pertanyaan']).' pertanyaan tersimpan di draf.'
            );
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('Gagal menyimpan pertanyaan skrining: '.$e->getMessage());

            return ResponseHelper::error('Gagal menyimpan.', 500);
        }
    }

    /**
     * Terbitkan draf: DRAFT → PUBLISHED, dan yang lama → ARCHIVED.
     *
     * Urutannya penting. Indeks unik bersaring UX_NWC_SkrVersi_Terbit hanya
     * mengizinkan SATU versi PUBLISHED per template, jadi yang lama harus
     * diarsipkan LEBIH DULU — kalau terbalik, basis data menolak dan seluruh
     * transaksi batal.
     */
    public function terbitkan(int $versiId)
    {
        if (! Skrining::siap()) {
            return ResponseHelper::error('Skema skrining belum dijalankan.', 409);
        }

        $versi = DB::table(Skrining::T_VERSI)->where('Id_Master_Skrining_Versi', $versiId)->first();
        if (! $versi) {
            return ResponseHelper::error('Versi tidak ditemukan.', 404);
        }

        if ($versi->Status !== 'DRAFT') {
            return ResponseHelper::error('Hanya draf yang bisa diterbitkan.', 422);
        }

        $jml = DB::table(Skrining::T_TANYA)->where('Master_Skrining_Versi_Id', $versiId)->count();
        if ($jml === 0) {
            return ResponseHelper::error('Draf belum berisi pertanyaan. Isi dulu sebelum diterbitkan.', 422);
        }

        try {
            DB::transaction(function () use ($versi, $versiId) {
                DB::table(Skrining::T_VERSI)
                    ->where('Master_Skrining_Id', $versi->Master_Skrining_Id)
                    ->where('Status', 'PUBLISHED')
                    ->update(['Status' => 'ARCHIVED'] + $this->capUbah());

                DB::table(Skrining::T_VERSI)->where('Id_Master_Skrining_Versi', $versiId)->update([
                    'Status' => 'PUBLISHED',
                    'Published_At' => now(),
                ] + $this->capUbah());
            });

            $this->catat('versi diterbitkan', 'template #'.$versi->Master_Skrining_Id.' v'.$versi->Versi." ({$jml} pertanyaan)");

            return ResponseHelper::success(
                null,
                'Versi '.$versi->Versi.' terbit. Sesi baru mulai memakainya; sesi yang sudah berjalan tidak berubah.'
            );
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('Gagal menerbitkan versi skrining: '.$e->getMessage());

            return ResponseHelper::error('Gagal menerbitkan.', 500);
        }
    }

    /**
     * Buat draf baru dengan mengklon versi terbit.
     *
     * Inilah satu-satunya jalan menyunting template yang sudah dipakai — dan
     * karena itu ia harus terasa mudah, bukan seperti hukuman.
     */
    public function drafBaru(int $id)
    {
        if (! Skrining::siap()) {
            return ResponseHelper::error('Skema skrining belum dijalankan.', 409);
        }

        $master = DB::table(Skrining::T_MASTER)->where('Id_Master_Skrining', $id)->first();
        if (! $master) {
            return ResponseHelper::error('Template tidak ditemukan.', 404);
        }

        $adaDraf = DB::table(Skrining::T_VERSI)
            ->where('Master_Skrining_Id', $id)->where('Status', 'DRAFT')->first();

        if ($adaDraf) {
            return ResponseHelper::error(
                'Sudah ada draf v'.$adaDraf->Versi.' yang belum diterbitkan. Selesaikan atau buang dulu.',
                422
            );
        }

        $sumber = DB::table(Skrining::T_VERSI)
            ->where('Master_Skrining_Id', $id)->where('Status', 'PUBLISHED')->first();

        $tertinggi = (int) DB::table(Skrining::T_VERSI)->where('Master_Skrining_Id', $id)->max('Versi');

        try {
            $versiId = DB::transaction(function () use ($id, $sumber, $tertinggi) {
                $versiId = DB::table(Skrining::T_VERSI)->insertGetId([
                    'Master_Skrining_Id' => $id,
                    'Versi' => $tertinggi + 1,
                    'Status' => 'DRAFT',
                    'Catatan' => $sumber ? 'Disalin dari v'.$sumber->Versi : null,
                ] + $this->capBuat(), 'Id_Master_Skrining_Versi');

                if ($sumber) {
                    $this->klonPertanyaan((int) $sumber->Id_Master_Skrining_Versi, $versiId);
                }

                return $versiId;
            });

            return ResponseHelper::success(
                ['versiId' => $versiId, 'versi' => $tertinggi + 1],
                'Draf v'.($tertinggi + 1).' dibuat'.($sumber ? ' dari salinan v'.$sumber->Versi.'.' : '.')
            );
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('Gagal membuat draf skrining: '.$e->getMessage());

            return ResponseHelper::error('Gagal membuat draf.', 500);
        }
    }

    /** Buang draf yang belum diterbitkan. Versi terbit & arsip tidak bisa dibuang. */
    public function buangDraf(int $versiId)
    {
        if (! Skrining::siap()) {
            return ResponseHelper::error('Skema skrining belum dijalankan.', 409);
        }

        $versi = DB::table(Skrining::T_VERSI)->where('Id_Master_Skrining_Versi', $versiId)->first();
        if (! $versi) {
            return ResponseHelper::error('Versi tidak ditemukan.', 404);
        }

        if ($versi->Status !== 'DRAFT') {
            return ResponseHelper::error('Hanya draf yang bisa dibuang.', 422);
        }

        // Versi 1 yang masih draf berarti template ini belum pernah terbit sama
        // sekali. Membuangnya menyisakan template tanpa versi — keadaan setengah
        // jadi yang sengaja dihindari sejak `store()`.
        if ((int) $versi->Versi === 1) {
            return ResponseHelper::error(
                'Draf pertama tidak bisa dibuang — hapus templatenya kalau memang tidak jadi dipakai.',
                422
            );
        }

        try {
            DB::transaction(function () use ($versiId) {
                DB::table(Skrining::T_TANYA)->where('Master_Skrining_Versi_Id', $versiId)->delete();
                DB::table(Skrining::T_VERSI)->where('Id_Master_Skrining_Versi', $versiId)->delete();
            });

            return ResponseHelper::success(null, 'Draf v'.$versi->Versi.' dibuang.');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('Gagal membuang draf skrining: '.$e->getMessage());

            return ResponseHelper::error('Gagal membuang draf.', 500);
        }
    }

    // ═══════════════════════════ BANK PERTANYAAN ═══════════════════════════

    /**
     * Cari pertanyaan di bank untuk pemilih di penyunting template.
     *
     * Berizin masterSkriningPage VIEW, bukan masterPertanyaanPage: orang yang
     * menyusun template harus bisa MELIHAT pustaka tanpa berhak MENYUNTINGnya.
     * Memaksanya lewat endpoint bank berarti tiap penyusun template diberi izin
     * merawat pustaka — persis jenis izin berlebih yang menghasilkan 403 di
     * tempat tak terduga.
     */
    public function cariBank(Request $request)
    {
        if (! BankPertanyaan::siap()) {
            return ResponseHelper::error('Skema bank pertanyaan belum dijalankan.', 409);
        }

        $data = $request->validate([
            'q' => 'nullable|string|max:120',
            'jf' => 'nullable|array', 'jf.*' => 'string|max:40',
            'level' => 'nullable|array', 'level.*' => 'string|max:40',
            'ct' => 'nullable|array', 'ct.*' => 'string|max:40',
            'komp' => 'nullable|array', 'komp.*' => 'string|max:40',
            'fase' => 'nullable|array', 'fase.*' => 'string|max:40',
            'jenis' => 'nullable|array', 'jenis.*' => 'string|max:24',
            'prioritas' => 'nullable|array', 'prioritas.*' => 'string|max:16',
            'kecuali' => 'nullable|array', 'kecuali.*' => 'string|max:40',
            'halaman' => 'nullable|integer|min:1',
        ]);

        $hasil = BankPertanyaan::cari($data, (int) ($data['halaman'] ?? 1), 40);

        return ResponseHelper::success($hasil, $hasil['total'].' pertanyaan cocok.');
    }

    /**
     * Tarik pertanyaan dari bank ke sebuah draf.
     *
     * MENYALIN, bukan menautkan. Sesudah panggilan ini, templatelah yang
     * memiliki pertanyaannya; menyunting bank besok tidak menyentuh draf ini,
     * apalagi sesi kandidat yang sudah berjalan.
     *
     * Yang benar-benar didapat dari bank adalah kemudahan menulis DAN kode yang
     * seragam: "gaji_harapan" di template HR Umum dan di template MT jadi kode
     * yang sama, sehingga hasilnya bisa diagregasi lintas program. Tanpa bank,
     * keduanya jadi dua pertanyaan berbeda yang kebetulan mirip.
     *
     * Kode yang sudah ada di draf DILEWATI, bukan ditolak: menambahkan sepuluh
     * pertanyaan lalu gagal seluruhnya karena satu di antaranya sudah ada adalah
     * cara paling cepat membuat orang berhenti memakai tombolnya.
     */
    public function dariBank(Request $request, int $versiId)
    {
        if (! Skrining::siap() || ! BankPertanyaan::siap()) {
            return ResponseHelper::error('Skema bank pertanyaan belum dijalankan.', 409);
        }

        $versi = DB::table(Skrining::T_VERSI)->where('Id_Master_Skrining_Versi', $versiId)->first();
        if (! $versi) {
            return ResponseHelper::error('Versi tidak ditemukan.', 404);
        }

        if ($versi->Status !== 'DRAFT') {
            return ResponseHelper::error(
                'Versi '.$versi->Versi.' berstatus '.$versi->Status.' dan tidak bisa disunting. '
                .'Buat draf baru untuk menambah pertanyaan.',
                422
            );
        }

        $data = $request->validate([
            'kode' => 'required|array|min:1',
            'kode.*' => 'required|string|max:40',
        ]);

        $bank = DB::table(BankPertanyaan::TABEL)
            ->where('Konteks', 'SKRINING')
            ->whereIn('Kode', $data['kode'])
            ->get()
            ->keyBy('Kode');

        if ($bank->isEmpty()) {
            return ResponseHelper::error('Tidak ada pertanyaan bank yang cocok.', 422);
        }

        $sudahAda = DB::table(Skrining::T_TANYA)
            ->where('Master_Skrining_Versi_Id', $versiId)
            ->pluck('Kode')
            ->all();

        $urut = (int) DB::table(Skrining::T_TANYA)->where('Master_Skrining_Versi_Id', $versiId)->max('Urutan');

        $masuk = 0;
        $lewat = [];

        try {
            DB::transaction(function () use ($data, $bank, $sudahAda, $versiId, &$urut, &$masuk, &$lewat) {
                // Urutannya mengikuti urutan PILIHAN orang, bukan urutan bank —
                // yang ia centang lebih dulu itulah yang ia maksud lebih dulu.
                foreach ($data['kode'] as $kode) {
                    $b = $bank[$kode] ?? null;
                    if (! $b) {
                        continue;
                    }

                    if (in_array($kode, $sudahAda, true)) {
                        $lewat[] = $kode;

                        continue;
                    }

                    DB::table(Skrining::T_TANYA)->insert(
                        BankPertanyaan::keSalinan($b, $versiId, ++$urut) + $this->capBuat()
                    );
                    $masuk++;
                }
            });
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('Gagal menarik pertanyaan bank: '.$e->getMessage());

            return ResponseHelper::error('Gagal menambahkan.', 500);
        }

        return ResponseHelper::success(
            ['masuk' => $masuk, 'lewat' => $lewat],
            $lewat
                ? $masuk.' pertanyaan ditambahkan. '.count($lewat).' dilewati karena kodenya sudah ada di draf ini.'
                : $masuk.' pertanyaan ditambahkan dari bank.'
        );
    }

    /**
     * Segarkan draf dari bank — tarik ulang redaksi terbaru.
     *
     * Yang DILEWATI dengan sengaja:
     *
     *   · salinan bertanda `Flag_Ubahan = 'Y'`, yaitu yang sudah dirombak di
     *     template ini. Ambang knockout gaji memang harus berbeda antara loker
     *     staf dan loker manajer; menyegarkannya balik ke bawaan bank akan
     *     menghapus pekerjaan orang tanpa memberitahunya.
     *
     *   · pertanyaan yang diketik langsung tanpa lewat bank (Bank_Kode kosong).
     *     Tidak ada sumber untuk menyegarkannya.
     *
     * Urutan dan kelompok di dalam template TIDAK ikut berubah: yang disegarkan
     * adalah isi pertanyaannya, bukan susunan kuesionernya.
     */
    public function segarkanBank(int $versiId)
    {
        if (! Skrining::siap() || ! BankPertanyaan::siap()) {
            return ResponseHelper::error('Skema bank pertanyaan belum dijalankan.', 409);
        }

        $versi = DB::table(Skrining::T_VERSI)->where('Id_Master_Skrining_Versi', $versiId)->first();
        if (! $versi) {
            return ResponseHelper::error('Versi tidak ditemukan.', 404);
        }

        if ($versi->Status !== 'DRAFT') {
            return ResponseHelper::error('Hanya draf yang bisa disegarkan dari bank.', 422);
        }

        $baris = DB::table(Skrining::T_TANYA)
            ->where('Master_Skrining_Versi_Id', $versiId)
            ->whereNotNull('Bank_Kode')
            ->where('Flag_Ubahan', 'T')
            ->get();

        if ($baris->isEmpty()) {
            return ResponseHelper::success(
                ['segar' => 0],
                'Tidak ada yang perlu disegarkan — seluruh pertanyaan di draf ini sudah dirombak sendiri atau tidak berasal dari bank.'
            );
        }

        $bank = DB::table(BankPertanyaan::TABEL)
            ->where('Konteks', 'SKRINING')
            ->whereIn('Kode', $baris->pluck('Bank_Kode')->unique()->all())
            ->get()
            ->keyBy('Kode');

        $segar = 0;

        try {
            DB::transaction(function () use ($baris, $bank, $versiId, &$segar) {
                foreach ($baris as $r) {
                    $b = $bank[$r->Bank_Kode] ?? null;
                    if (! $b) {
                        continue;
                    }

                    $isi = BankPertanyaan::keSalinan($b, $versiId, (int) $r->Urutan);

                    // Kelompok & kode yang sudah disesuaikan di template
                    // dipertahankan: memindahkan pertanyaan ke seksi lain bukan
                    // "merombak isinya", dan mengembalikannya diam-diam akan
                    // mengacak susunan kuesioner yang barusan dirapikan orang.
                    $isi['Seksi'] = $r->Seksi;
                    $isi['Kode'] = $r->Kode;

                    DB::table(Skrining::T_TANYA)
                        ->where('Id_Master_Skrining_Pertanyaan', $r->Id_Master_Skrining_Pertanyaan)
                        ->update($isi + $this->capUbah());
                    $segar++;
                }
            });
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('Gagal menyegarkan draf dari bank: '.$e->getMessage());

            return ResponseHelper::error('Gagal menyegarkan.', 500);
        }

        return ResponseHelper::success(
            ['segar' => $segar],
            $segar.' pertanyaan disegarkan dari bank.'
        );
    }

    // ═══════════════════════════ PENOLONG ═══════════════════════════

    /**
     * Periksa hal yang tidak tertangkap aturan validasi baris-per-baris.
     *
     * @return string|null pesan galat, atau null bila bersih
     */
    private function periksaPertanyaan(array $daftar): ?string
    {
        $kode = [];

        foreach ($daftar as $i => $p) {
            $no = $i + 1;
            $label = '"'.mb_substr($p['label'] ?? '', 0, 40).'"';

            if (isset($kode[$p['kode']])) {
                return 'Kode "'.$p['kode'].'" dipakai dua kali (pertanyaan '.$kode[$p['kode']].' dan '.$no.'). '
                    .'Kode harus unik — ia yang dipakai aturan auto-gugur dan analitik.';
            }
            $kode[$p['kode']] = $no;

            $tipe = $p['tipe'];
            $opsi = $p['opsi'] ?? [];

            if (in_array($tipe, Skrining::TIPE_BEROPSI, true) && count($opsi) < 2) {
                return 'Pertanyaan '.$no.' '.$label.' bertipe '.$tipe.' tapi opsinya kurang dari dua. '
                    .'Pilihan tunggal bukan pertanyaan.';
            }

            if (in_array($tipe, Skrining::TIPE_BERSKALA, true)) {
                $min = $p['skalaMin'] ?? 0;
                $max = $p['skalaMax'] ?? null;

                if ($max === null || $max <= $min) {
                    return 'Pertanyaan '.$no.' '.$label.' berskala, tapi batas atasnya tidak lebih besar dari batas bawah.';
                }
            }

            // Bobot tanpa tipe berskor = angka yang tidak akan pernah terpakai.
            // Lebih baik ditolak sekarang daripada membuat orang mengira
            // pertanyaan esainya ikut dihitung.
            if (($p['bobot'] ?? null) !== null && ! in_array($tipe, Skrining::TIPE_BERSKOR, true)) {
                return 'Pertanyaan '.$no.' '.$label.' bertipe '.$tipe.' dan tidak bisa diberi bobot skor. '
                    .'Jawaban teks bebas tidak dinilai angka oleh sistem.';
            }

            if (! empty($p['knockout'])) {
                if (empty($p['knockoutOperator']) || ($p['knockoutNilai'] ?? '') === '') {
                    return 'Pertanyaan '.$no.' '.$label.' ditandai auto-gugur tapi syaratnya belum lengkap. '
                        .'Isi operator dan nilainya, atau matikan penandanya.';
                }
            }
        }

        return null;
    }

    /** Satu pertanyaan dari layar → kolom tabel. */
    private function isiPertanyaan(array $p, int $versiId, int $urutan): array
    {
        $tipe = $p['tipe'];
        $beropsi = in_array($tipe, Skrining::TIPE_BEROPSI, true);
        $berskala = in_array($tipe, Skrining::TIPE_BERSKALA, true);
        $knockout = ! empty($p['knockout']);

        return [
            'Master_Skrining_Versi_Id' => $versiId,
            'Seksi' => $p['seksi'] ?? null,
            'Urutan' => $urutan,
            'Kode' => $p['kode'],
            'Tipe' => $tipe,
            'Label' => $p['label'],
            'Bantuan' => $p['bantuan'] ?? null,
            // Opsi hanya disimpan untuk tipe yang memakainya. Menyimpan sisa
            // opsi dari tipe sebelumnya membuat penyunting menampilkan pilihan
            // hantu begitu tipenya diganti bolak-balik.
            'Opsi' => $beropsi && ! empty($p['opsi']) ? json_encode(array_values($p['opsi'])) : null,
            'Skala_Min' => $berskala ? ($p['skalaMin'] ?? 0) : null,
            'Skala_Max' => $berskala ? ($p['skalaMax'] ?? null) : null,
            'Label_Min' => $berskala ? ($p['labelMin'] ?? null) : null,
            'Label_Max' => $berskala ? ($p['labelMax'] ?? null) : null,
            'Flag_Wajib' => ! empty($p['wajib']) ? 'Y' : 'T',
            'Bobot' => $p['bobot'] ?? null,
            'Flag_Knockout' => $knockout ? 'Y' : 'T',
            'Knockout_Operator' => $knockout ? ($p['knockoutOperator'] ?? null) : null,
            'Knockout_Nilai' => $knockout ? ($p['knockoutNilai'] ?? null) : null,
            'Knockout_Pesan' => $knockout ? ($p['knockoutPesan'] ?? null) : null,
            'Flag_Catatan' => ! empty($p['catatan']) ? 'Y' : 'T',
            'Tampil_Jika' => ! empty($p['tampilJika']) ? json_encode($p['tampilJika']) : null,
        ];
    }

    /**
     * Tambahkan asal-usul bank ke sebuah salinan pertanyaan.
     *
     * `Flag_Ubahan` dihitung DI SINI, bukan dikirim layar: yang menentukan
     * sebuah salinan sudah menyimpang atau belum adalah perbandingan isinya
     * dengan bank, dan penanda yang dikirim klien bisa salah — atau dikarang.
     * Penanda inilah yang memutuskan baris ini ikut tersegarkan atau tidak,
     * jadi ia tidak boleh berasal dari luar.
     */
    private function asalBank(array $isi, array $p): array
    {
        if (! BankPertanyaan::siap()) {
            return $isi;
        }

        $kode = $p['bankKode'] ?? null;
        if (! $kode) {
            return $isi + ['Bank_Id' => null, 'Bank_Kode' => null, 'Flag_Ubahan' => 'T'];
        }

        $bank = DB::table(BankPertanyaan::TABEL)
            ->where('Konteks', 'SKRINING')
            ->where('Kode', $kode)
            ->first();

        // Pertanyaan bank yang sudah dihapus: jejaknya tetap disimpan lewat
        // Bank_Kode, tapi ditandai ubahan supaya tidak ada yang mencoba
        // menyegarkannya dari sumber yang tidak ada lagi.
        if (! $bank) {
            return $isi + ['Bank_Id' => null, 'Bank_Kode' => $kode, 'Flag_Ubahan' => 'Y'];
        }

        return $isi + [
            'Bank_Id' => (int) $bank->Id_Master_Pertanyaan,
            'Bank_Kode' => $bank->Kode,
            'Flag_Ubahan' => BankPertanyaan::samaDenganBank($isi, $bank) ? 'T' : 'Y',
        ];
    }

    /** Salin seluruh pertanyaan satu versi ke versi lain. */
    private function klonPertanyaan(int $dariVersiId, int $keVersiId): void
    {
        $rows = DB::table(Skrining::T_TANYA)
            ->where('Master_Skrining_Versi_Id', $dariVersiId)
            ->orderBy('Urutan')
            ->get();

        foreach ($rows as $r) {
            $isi = (array) $r;

            // Kunci audit dibuang DULU, bukan ditimpa lewat `+`: operator itu
            // mempertahankan kunci di operan kiri, jadi salinannya akan membawa
            // cap waktu versi asalnya dan menyesatkan siapa pun yang kelak
            // bertanya kapan draf ini sebenarnya dibuat.
            unset(
                $isi['Id_Master_Skrining_Pertanyaan'],
                $isi['Created_At'], $isi['Created_By'], $isi['Created_By_Id'],
                $isi['Updated_At'], $isi['Updated_By'], $isi['Updated_By_Id'],
            );

            $isi['Master_Skrining_Versi_Id'] = $keVersiId;

            DB::table(Skrining::T_TANYA)->insert($isi + $this->capBuat());
        }
    }

    private function capBuat(): array
    {
        return [
            'Created_At' => now(),
            'Created_By' => session('career_auth.nama', 'ADMIN'),
            'Created_By_Id' => session('career_auth.id'),
            'Updated_At' => now(),
            'Updated_By' => session('career_auth.nama', 'ADMIN'),
            'Updated_By_Id' => session('career_auth.id'),
        ];
    }

    private function capUbah(): array
    {
        return [
            'Updated_At' => now(),
            'Updated_By' => session('career_auth.nama', 'ADMIN'),
            'Updated_By_Id' => session('career_auth.id'),
        ];
    }

    private function catat(string $aksi, string $apa): void
    {
        Log::channel('web_career')->info(sprintf(
            '[SKRINING] %s: %s, oleh %s.',
            $aksi, $apa, session('career_auth.nama', 'ADMIN')
        ));
    }
}
