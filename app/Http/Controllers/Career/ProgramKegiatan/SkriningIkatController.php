<?php

namespace App\Http\Controllers\Career\ProgramKegiatan;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Support\Career\AksesService;
use App\Support\Career\Skrining;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Vinkla\Hashids\Facades\Hashids;

/**
 * WEB CAREER — PENGIKATAN TEMPLATE SKRINING KE LOKER.
 *
 * Menjawab satu pertanyaan: "loker ini pakai template pertanyaan yang mana?"
 *
 * ── KENAPA CONTROLLER SENDIRI ───────────────────────────────────────────────
 *
 * ProgramKegiatanController sudah lebih dari seribu baris dan mengurus hal yang
 * berbeda: program, posisi, batch, serah terima. Pengikatan skrining menumpang
 * izin yang sama (programPage) tapi punya aturan sendiri yang tidak ada
 * hubungannya dengan sisanya.
 *
 * ── DUA TINGKAT PENGIKATAN ──────────────────────────────────────────────────
 *
 *   Program_Posisi_Id NULL  → bawaan seluruh loker di program ini
 *   Program_Posisi_Id terisi → penimpaan untuk satu loker
 *
 * Itulah yang membuat program berisi 50 loker tidak perlu diatur 50 kali.
 * Rekruter mengatur sekali di tingkat program, lalu menimpa hanya loker yang
 * memang berbeda — dan layar menunjukkan mana yang ikut bawaan, mana yang
 * ditimpa.
 *
 * ── MENGUBAH PENGIKATAN TIDAK MENGGESER KANDIDAT ────────────────────────────
 *
 * Tabel ini dibaca SEKALI, saat aktivitas kandidat dibuat. Sesudah itu yang
 * berlaku adalah Skrining_Kode + Skrining_Versi yang sudah dibekukan di
 * Lamaran_Tahap_Tes. Layar menyebutkan ini terang-terangan supaya tidak ada
 * yang mengira menukar template akan memperbaiki sesi yang sudah telanjur.
 */
class SkriningIkatController extends Controller
{
    /**
     * Halaman yang izin kategorinya berlaku di sini.
     *
     * programPage, bukan masterSkriningPage: yang dikerjakan layar ini adalah
     * memasang kuesioner PADA PROGRAM. Rekruter yang boleh membuka program
     * REKRUTMEN tapi tidak punya akses halaman Master Template sama sekali
     * tetap harus bisa memasang template yang sudah dibuat orang lain.
     */
    private const PAGE = 'programPage';

    /**
     * Aktivitas skrining di alur program + pengikatan yang berlaku + pilihan template.
     *
     * Satu panggilan, bukan tiga: layar tidak bisa menggambar apa pun sebelum
     * ketiganya ada, dan memecahnya hanya menghasilkan tiga keadaan setengah
     * jadi yang harus dijaga.
     */
    public function index(Request $request, string $id)
    {
        if (! Skrining::siap()) {
            return ResponseHelper::success(
                ['siap' => false, 'aktivitas' => [], 'template' => [], 'ikatan' => []],
                'Skema skrining belum dijalankan.'
            );
        }

        $program = $this->program($id);
        if (! $program) {
            return ResponseHelper::error('Program tidak ditemukan.', 404);
        }

        try {
            $aktivitas = $this->aktivitasSkrining($program->Alur_Kode);

            // Tidak ada tahap skrining di alurnya sama sekali. Bukan galat —
            // memang tidak setiap alur memakai phone screening. Layar memakai
            // ini untuk menjelaskan kenapa panelnya kosong, bukan menampilkan
            // daftar kosong tanpa sebab.
            if (! $aktivitas) {
                return ResponseHelper::success([
                    'siap' => true,
                    'alur' => $program->Alur_Kode,
                    'aktivitas' => [],
                    'template' => [],
                    'ikatan' => [],
                    'posisi' => [],
                ], 'Alur program ini tidak memuat tahap skrining.');
            }

            $posisi = DB::table('N_WEB_CAREERS_Program_Posisi')
                ->where('Program_Id', $program->Id_Program)
                ->orderBy('Id_Program_Posisi')
                ->get();

            $ikatan = DB::table(Skrining::T_IKAT)
                ->where('Program_Id', $program->Id_Program)
                ->where('Flag_Aktif', 'Y')
                ->get()
                ->map(fn ($r) => [
                    'id' => (int) $r->Id_Program_Skrining,
                    'posisiId' => $r->Program_Posisi_Id !== null ? (int) $r->Program_Posisi_Id : null,
                    'aktivitasId' => (int) $r->Master_Alur_Tahap_Tes_Id,
                    'kode' => $r->Skrining_Kode,
                    'versi' => $r->Skrining_Versi !== null ? (int) $r->Skrining_Versi : null,
                ])
                ->values();

            return ResponseHelper::success([
                'siap' => true,
                'alur' => $program->Alur_Kode,
                'aktivitas' => $aktivitas,
                'template' => $this->template($program->Kategori ?? null),
                'ikatan' => $ikatan,
                'posisi' => $posisi->map(fn ($p) => [
                    'id' => (int) $p->Id_Program_Posisi,
                    'posisi' => $p->Posisi,
                    'mppRef' => $p->Mpp_Ref,
                    'departemen' => $p->Departemen,
                    'aktif' => ($p->Flag_Aktif ?? 'Y') === 'Y',
                ])->values(),
            ], 'Pengikatan skrining dimuat.');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('Gagal memuat pengikatan skrining: '.$e->getMessage());

            return ResponseHelper::error('Gagal memuat data.', 500);
        }
    }

    /**
     * Aktivitas skrining + daftar template untuk SEBUAH ALUR, tanpa program.
     *
     * Dipakai wizard pembuatan program: di sana pengikatan harus sudah bisa
     * ditentukan padahal programnya belum tersimpan, jadi tidak ada Id apa pun
     * yang bisa dipakai — baik Id program maupun Id loker.
     *
     * Karena itu jawabannya tidak memuat `posisi` maupun `ikatan`: keduanya
     * memang belum ada. Yang mengikat loker ke template nanti adalah Mpp_Ref,
     * satu-satunya penciri loker yang sudah dipegang layar sebelum disimpan.
     */
    public function alur(Request $request)
    {
        if (! Skrining::siap()) {
            return ResponseHelper::success(['siap' => false, 'aktivitas' => [], 'template' => []]);
        }

        $data = $request->validate([
            'alur' => 'nullable|string|max:60',
            // Kategori datang dari borang — programnya belum tersimpan, jadi
            // tidak ada baris yang bisa ditanyai. Hak akses akun tetap
            // diperiksa server, jadi kategori palsu tidak membuka apa pun yang
            // memang tidak boleh dilihat.
            'kategori' => 'nullable|string|max:20',
        ]);

        try {
            $aktivitas = $this->aktivitasSkrining($data['alur'] ?? null);

            return ResponseHelper::success([
                'siap' => true,
                'alur' => $data['alur'] ?? null,
                'aktivitas' => $aktivitas,
                // Template tetap dikirim walau aktivitasnya kosong: layar
                // memakainya untuk membedakan "alur ini memang tanpa skrining"
                // dari "template belum ada satu pun".
                'template' => $this->template($data['kategori'] ?? null),
            ]);
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('Gagal memuat skrining alur: '.$e->getMessage());

            return ResponseHelper::error('Gagal memuat data.', 500);
        }
    }

    /**
     * Pasang / ganti / lepas satu pengikatan.
     *
     * `kode` kosong berarti LEPAS. Disatukan dengan pemasangan, bukan endpoint
     * DELETE terpisah, karena di layar keduanya adalah satu kotak pilihan yang
     * sama — mengosongkannya adalah cara orang melepas, dan memaksanya jadi dua
     * panggilan berbeda hanya memindahkan percabangan itu ke sisi klien.
     */
    public function simpan(Request $request, string $id)
    {
        if (! Skrining::siap()) {
            return ResponseHelper::error('Skema skrining belum dijalankan.', 409);
        }

        $program = $this->program($id);
        if (! $program) {
            return ResponseHelper::error('Program tidak ditemukan.', 404);
        }

        $data = $request->validate([
            'aktivitasId' => 'required|integer',
            'posisiId' => 'nullable|integer',
            'kode' => 'nullable|string|max:30',
            // Kosong = ikut versi terbit terbaru. Terisi = dipaku ke versi itu.
            'versi' => 'nullable|integer|min:1',
        ]);

        $aktivitas = $this->aktivitasSkrining($program->Alur_Kode);
        $sah = collect($aktivitas)->firstWhere('id', (int) $data['aktivitasId']);
        if (! $sah) {
            return ResponseHelper::error('Aktivitas itu bukan tahap skrining di alur program ini.', 422);
        }

        // Posisi HARUS milik program ini. Tanpa pemeriksaan ini, id mana pun
        // yang ditempelkan ke payload akan mengikat loker milik program lain —
        // termasuk milik kategori yang pengirimnya tidak berhak menyentuhnya.
        if (! empty($data['posisiId'])) {
            $milik = DB::table('N_WEB_CAREERS_Program_Posisi')
                ->where('Program_Id', $program->Id_Program)
                ->where('Id_Program_Posisi', (int) $data['posisiId'])
                ->exists();

            if (! $milik) {
                return ResponseHelper::error('Loker itu bukan milik program ini.', 422);
            }
        }

        $posisiId = ! empty($data['posisiId']) ? (int) $data['posisiId'] : null;
        $kode = $data['kode'] ?? null;

        if ($galat = $this->galatKategori($kode, $program->Kategori ?? null)) {
            return ResponseHelper::error($galat, 422);
        }

        $lama = DB::table(Skrining::T_IKAT)
            ->where('Program_Id', $program->Id_Program)
            ->where('Master_Alur_Tahap_Tes_Id', (int) $data['aktivitasId'])
            ->when($posisiId === null,
                fn ($q) => $q->whereNull('Program_Posisi_Id'),
                fn ($q) => $q->where('Program_Posisi_Id', $posisiId))
            ->first();

        try {
            // ── LEPAS ──
            if (! $kode) {
                if ($lama) {
                    DB::table(Skrining::T_IKAT)->where('Id_Program_Skrining', $lama->Id_Program_Skrining)->delete();
                }

                return ResponseHelper::success(
                    ['dilepas' => true],
                    $posisiId
                        ? 'Penimpaan dilepas — loker ini kembali ikut bawaan program.'
                        : 'Bawaan program dilepas.'
                );
            }

            $pakai = Skrining::versiTerpakai($kode, $data['versi'] ?? null);
            if (! $pakai) {
                return ResponseHelper::error(
                    ($data['versi'] ?? null)
                        ? 'Versi '.$data['versi'].' tidak ada pada template itu.'
                        : 'Template itu belum punya versi terbit. Terbitkan dulu di Master Phone Screening.',
                    422
                );
            }

            $isi = [
                'Program_Id' => $program->Id_Program,
                'Program_Posisi_Id' => $posisiId,
                'Master_Alur_Tahap_Tes_Id' => (int) $data['aktivitasId'],
                'Skrining_Kode' => $kode,
                'Skrining_Versi' => $data['versi'] ?? null,
                'Flag_Aktif' => 'Y',
            ];

            if ($lama) {
                DB::table(Skrining::T_IKAT)
                    ->where('Id_Program_Skrining', $lama->Id_Program_Skrining)
                    ->update($isi + $this->capUbah());
            } else {
                DB::table(Skrining::T_IKAT)->insert($isi + $this->capBuat());
            }

            Log::channel('web_career')->info(sprintf(
                '[SKRINING] pengikatan disimpan: program #%d %s → %s v%d, oleh %s.',
                $program->Id_Program,
                $posisiId ? "loker #{$posisiId}" : 'seluruh loker',
                $kode, $pakai['versi'], session('career_auth.nama', 'ADMIN')
            ));

            return ResponseHelper::success(
                ['versi' => $pakai['versi']],
                sprintf(
                    '%s memakai %s (v%d%s). Sesi yang sudah berjalan tidak berubah.',
                    $posisiId ? 'Loker ini' : 'Seluruh loker program',
                    $pakai['nama'],
                    $pakai['versi'],
                    ($data['versi'] ?? null) ? ', dipaku' : ', ikut versi terbit'
                )
            );
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('Gagal menyimpan pengikatan skrining: '.$e->getMessage());

            return ResponseHelper::error('Gagal menyimpan.', 500);
        }
    }

    /**
     * Terapkan satu template ke SELURUH loker sekaligus.
     *
     * Ini bukan kemudahan tambahan — ini alasan tabel pengikatannya dirancang
     * dengan Program_Posisi_Id yang boleh NULL. Program berisi 50 loker yang
     * harus diatur satu per satu adalah persis keluhan "rekruter capek klik-klik"
     * yang sudah pernah diperbaiki di halaman ini.
     *
     * Yang dilakukan: pasang bawaan program, lalu BUANG seluruh penimpaan per
     * loker untuk aktivitas itu. Menyisakan penimpaan lama membuat "terapkan ke
     * semua" berbohong — sebagian loker tetap memakai template lamanya.
     */
    public function terapkanSemua(Request $request, string $id)
    {
        if (! Skrining::siap()) {
            return ResponseHelper::error('Skema skrining belum dijalankan.', 409);
        }

        $program = $this->program($id);
        if (! $program) {
            return ResponseHelper::error('Program tidak ditemukan.', 404);
        }

        $data = $request->validate([
            'aktivitasId' => 'required|integer',
            'kode' => 'required|string|max:30',
            'versi' => 'nullable|integer|min:1',
        ]);

        $sah = collect($this->aktivitasSkrining($program->Alur_Kode))->firstWhere('id', (int) $data['aktivitasId']);
        if (! $sah) {
            return ResponseHelper::error('Aktivitas itu bukan tahap skrining di alur program ini.', 422);
        }

        if ($galat = $this->galatKategori($data['kode'], $program->Kategori ?? null)) {
            return ResponseHelper::error($galat, 422);
        }

        $pakai = Skrining::versiTerpakai($data['kode'], $data['versi'] ?? null);
        if (! $pakai) {
            return ResponseHelper::error('Template itu belum punya versi terbit.', 422);
        }

        try {
            $dibuang = DB::transaction(function () use ($program, $data) {
                $dibuang = DB::table(Skrining::T_IKAT)
                    ->where('Program_Id', $program->Id_Program)
                    ->where('Master_Alur_Tahap_Tes_Id', (int) $data['aktivitasId'])
                    ->whereNotNull('Program_Posisi_Id')
                    ->delete();

                DB::table(Skrining::T_IKAT)
                    ->where('Program_Id', $program->Id_Program)
                    ->where('Master_Alur_Tahap_Tes_Id', (int) $data['aktivitasId'])
                    ->whereNull('Program_Posisi_Id')
                    ->delete();

                DB::table(Skrining::T_IKAT)->insert([
                    'Program_Id' => $program->Id_Program,
                    'Program_Posisi_Id' => null,
                    'Master_Alur_Tahap_Tes_Id' => (int) $data['aktivitasId'],
                    'Skrining_Kode' => $data['kode'],
                    'Skrining_Versi' => $data['versi'] ?? null,
                    'Flag_Aktif' => 'Y',
                ] + $this->capBuat());

                return $dibuang;
            });

            Log::channel('web_career')->info(sprintf(
                '[SKRINING] diterapkan ke seluruh loker: program #%d → %s, %d penimpaan dibuang, oleh %s.',
                $program->Id_Program, $data['kode'], $dibuang, session('career_auth.nama', 'ADMIN')
            ));

            return ResponseHelper::success(
                ['dibuang' => $dibuang],
                $dibuang > 0
                    ? "Seluruh loker memakai {$pakai['nama']}. {$dibuang} penimpaan per loker ikut dibuang."
                    : "Seluruh loker memakai {$pakai['nama']}."
            );
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('Gagal menerapkan skrining ke semua loker: '.$e->getMessage());

            return ResponseHelper::error('Gagal menerapkan.', 500);
        }
    }

    // ═══════════════════════════ PENOLONG ═══════════════════════════

    /**
     * Program dari hashid, TERSARING lingkup PIC.
     *
     * Lingkupnya diperiksa di sini juga, bukan cuma di daftar program: id yang
     * ditempelkan langsung ke URL tidak lewat daftar sama sekali.
     */
    private function program(string $id): ?object
    {
        $realId = Hashids::decode($id)[0] ?? null;
        if (! $realId) {
            return null;
        }

        $program = DB::table('N_WEB_CAREERS_Program')->where('Id_Program', $realId)->first();
        if (! $program) {
            return null;
        }

        $bolehPic = AksesService::picDiizinkan('programPage');
        if ($bolehPic !== null) {
            $sayaId = session('career_auth.id');
            $milik = in_array($program->Pic_Kode_Karyawan, $bolehPic, true)
                || (int) $program->Created_By_Id === (int) $sayaId
                || DB::table('N_WEB_CAREERS_Program_Posisi')
                    ->where('Program_Id', $realId)
                    ->whereIn('Pic_Kode_Karyawan', $bolehPic ?: ['__tidak_ada__'])
                    ->exists();

            if (! $milik) {
                return null;
            }
        }

        return $program;
    }

    /**
     * Aktivitas bertipe skrining di sebuah alur.
     *
     * Dua tempat harus diperiksa karena Master_Alur_Tahap_Tes.Tipe_Tahap_Kode
     * boleh kosong: kalau kosong, aktivitas itu mewarisi tipe tahap induknya.
     * Memeriksa satu saja akan melewatkan separuh alur yang ada.
     *
     * Yang menentukan "ini skrining" tetap penanda master (Flag_Skrining),
     * bukan daftar kode di dalam program.
     */
    private function aktivitasSkrining(?string $alurKode): array
    {
        if (! $alurKode) {
            return [];
        }

        $tipeSkrining = DB::table('N_WEB_CAREERS_Master_Tipe_Tahap')
            ->where('Flag_Skrining', 'Y')
            ->pluck('Kode')
            ->all();

        if (! $tipeSkrining) {
            return [];
        }

        return DB::table('N_WEB_CAREERS_Master_Alur_Tahap_Tes as x')
            ->join('N_WEB_CAREERS_Master_Alur_Tahap as t', 't.Id_Master_Alur_Tahap', '=', 'x.Master_Alur_Tahap_Id')
            ->join('N_WEB_CAREERS_Master_Alur as a', 'a.Id_Master_Alur', '=', 't.Master_Alur_Id')
            ->where('a.Kode', $alurKode)
            ->where(function ($q) use ($tipeSkrining) {
                $q->whereIn('x.Tipe_Tahap_Kode', $tipeSkrining)
                    ->orWhere(function ($w) use ($tipeSkrining) {
                        $w->whereNull('x.Tipe_Tahap_Kode')->whereIn('t.Tipe_Tahap_Kode', $tipeSkrining);
                    });
            })
            ->orderBy('t.Urutan')
            ->orderBy('x.Urutan')
            ->get([
                'x.Id_Master_Alur_Tahap_Tes as id',
                'x.Label as label',
                'x.Urutan as urutanTes',
                'x.Skrining_Kode as bawaanAlur',
                't.Urutan as urutanTahap',
                't.Label as tahapLabel',
                't.Kode as tahapKode',
            ])
            ->map(fn ($r) => [
                'id' => (int) $r->id,
                'label' => $r->label,
                'tahap' => $r->tahapLabel,
                'tahapKode' => $r->tahapKode,
                'urutan' => (int) $r->urutanTahap,
                'urutanTes' => (int) $r->urutanTes,
                'bawaanAlur' => $r->bawaanAlur,
            ])
            ->values()
            ->all();
    }

    /** Template yang layak ditawarkan: aktif DAN sudah punya versi terbit. */
    /**
     * Template yang boleh ditawarkan untuk sebuah program.
     *
     * ── DUA LAPIS, DAN KEDUANYA PERLU ──────────────────────────────────────
     *
     *   1. KATEGORI PROGRAM. Memasang template MT pada program REKRUTMEN
     *      bukan sekadar melanggar izin — ia salah. Pertanyaan yang disusun
     *      untuk pelamar Management Trainee akan dibacakan kepada pelamar
     *      staf, dan hasilnya diagregasi seolah keduanya satu populasi.
     *
     *   2. HAK AKSES AKUN. Rekruter yang cuma berhak REKRUTMEN tidak boleh
     *      melihat isi kategori lain, walau kebetulan programnya cocok.
     *
     * Lapis pertama sudah cukup untuk kasus yang terlihat di layar; lapis
     * kedua menjaga jalur yang tidak lewat layar sama sekali.
     *
     * ── KENAPA KATEGORI KOSONG TETAP DITAWARKAN ────────────────────────────
     *
     * Template lama yang kategorinya belum diisi berlaku untuk semuanya.
     * Menyingkirkannya berarti template yang sudah dipakai bertahun-tahun
     * mendadak hilang dari layar pemiliknya — sepola perlakuan yang sama di
     * halaman Master Template Skrining.
     */
    /**
     * Template ini boleh dipasang di program itu? — null bila boleh.
     *
     * Ditegakkan terpisah dari daftar pilihan karena keduanya menjawab
     * pertanyaan yang berbeda: yang satu "apa yang pantas ditawarkan", yang
     * satu lagi "apa yang boleh tersimpan". Permintaan bisa datang dari tab
     * yang dibuka sebelum hak aksesnya diubah, atau tanpa lewat layar sama
     * sekali.
     */
    private function galatKategori(?string $kode, ?string $kategoriProgram): ?string
    {
        if (! $kode) {
            return null;
        }

        $t = DB::table(Skrining::T_MASTER)->where('Kode', $kode)->first();
        if (! $t) {
            return 'Template "'.$kode.'" tidak ditemukan.';
        }

        // Kategori kosong = berlaku untuk semua.
        $kat = $t->Kategori ?: null;
        if ($kat === null) {
            return null;
        }

        if ($kategoriProgram && $kat !== $kategoriProgram) {
            return 'Template "'.$t->Nama.'" milik kategori '.$kat
                .', sementara program ini '.$kategoriProgram
                .'. Pertanyaannya disusun untuk pelamar yang berbeda.';
        }

        $izin = AksesService::kategoriDiizinkan(self::PAGE);
        if ($izin !== null && ! in_array($kat, $izin, true)) {
            return 'Anda tidak berhak memakai template kategori '.$kat.'.';
        }

        return null;
    }

    private function template(?string $kategoriProgram = null): array
    {
        $terbit = DB::table(Skrining::T_VERSI)
            ->where('Status', 'PUBLISHED')
            ->pluck('Versi', 'Master_Skrining_Id');

        // null = tidak dibatasi.
        $izin = AksesService::kategoriDiizinkan(self::PAGE);

        return DB::table(Skrining::T_MASTER)
            ->where('Flag_Aktif', 'Y')
            ->when($kategoriProgram, fn ($q) => $q->where(fn ($w) => $w
                ->where('Kategori', $kategoriProgram)->orWhereNull('Kategori')))
            ->when($izin, fn ($q) => $q->where(fn ($w) => $w
                ->whereIn('Kategori', $izin)->orWhereNull('Kategori')))
            ->orderBy('Nama')
            ->get()
            ->map(function ($r) use ($terbit) {
                $v = $terbit[$r->Id_Master_Skrining] ?? null;

                return [
                    'kode' => $r->Kode,
                    'nama' => $r->Nama,
                    'kategori' => $r->Kategori,
                    'deskripsi' => $r->Deskripsi,
                    'versiTerbit' => $v !== null ? (int) $v : null,
                    // Template tanpa versi terbit tetap dikirim, tapi ditandai —
                    // menyembunyikannya membuat orang mencari template yang jelas
                    // ia baru saja buat, tanpa satu pun petunjuk kenapa hilang.
                    'siap' => $v !== null,
                ];
            })
            ->values()
            ->all();
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
}
