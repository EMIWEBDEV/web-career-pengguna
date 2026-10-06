<?php

namespace App\Http\Controllers\Career\Akses;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Support\Career\AksesService;
use App\Support\CareerShell;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Vinkla\Hashids\Facades\Hashids;

/**
 * WEB CAREERS — PENYUSUN MENU PER AKUN (gaya WordPress → Tampilan > Menu).
 *
 * Panel kiri  : halaman yang tersedia (dari master menu) — belum dipegang akun.
 * Panel kanan : struktur menu akun tersebut — grup (header) berisi item, dua-duanya
 *               bisa digeser (drag & drop). Label, ikon, dan sub-header boleh
 *               ditimpa khusus akun ini.
 *
 * ══════════════════════════════════════════════════════════════════════════════
 * ⚠ ATURAN PALING PENTING DI BERKAS INI: SIMPAN = UPDATE DI TEMPAT.
 * ══════════════════════════════════════════════════════════════════════════════
 * Id_Page_Access adalah INDUK dari Role_Menu_Access (centang aksi) dan
 * Role_Konten_Access (centang kategori). Kalau menyimpan susunan dilakukan
 * dengan "hapus semua baris akun ini lalu sisipkan ulang" — cara yang paling
 * menggoda karena ringkas — maka Id_Page_Access berganti dan SELURUH centang
 * aksi & kategori pengguna itu ikut hilang tanpa jejak. Hanya karena menggeser
 * urutan menu.
 *
 * Karena itu penyimpanan di sini bersifat SELISIH (diff):
 *   - baris lama yang masih ada  → UPDATE, itu pun hanya bila nilainya berubah
 *   - halaman baru dari panel kiri → INSERT (+ aksi VIEW sebagai bekal awal)
 *   - penghapusan                → HANYA untuk id yang disebut eksplisit di
 *                                  daftar "hapus", tidak pernah disimpulkan
 *                                  dari "tidak ada di payload"
 *
 * Konsekuensinya payload yang terpotong/parsial paling buruk hanya membuat
 * sebagian menu tidak tergeser — tidak pernah menghapus hak akses.
 */
class SusunMenuController extends Controller
{
    private string $tPage = 'N_WEB_CAREERS_Page_Access';

    private string $tMenu = 'N_WEB_CAREERS_Menu';

    private string $tRma = 'N_WEB_CAREERS_Role_Menu_Access';

    private string $tRka = 'N_WEB_CAREERS_Role_Konten_Access';

    /** Kolom timpaan per akun. NULL = ikut master menu. */
    private const KOLOM_TIMPA = [
        'Nama_Header_Custom',
        'Nama_Grup_Custom',
        'Sub_Header_Custom',
        'Nama_Menu_Custom',
        'Icon_Menu_Custom',
    ];

    /** Halaman penyusun menu (route sendiri, bukan modal — butuh ruang). */
    public function index(string $userId)
    {
        $idUsers = Hashids::decode($userId)[0] ?? null;
        $u = $idUsers ? DB::table('N_WEB_CAREERS_Users')->where('Id_Users', $idUsers)->first() : null;
        abort_if(! $u, 404);

        // activeUrl sengaja '/hak-akses' supaya sidebar tetap menyorot induknya.
        return Inertia::render('Career/admin/akses/susunMenu', CareerShell::props('/hak-akses', 'Susun Menu', [
            'targetUser' => [
                'id' => $userId,
                'nama' => $u->Nama,
                'email' => $u->Email,
                'role' => $u->Role,
                'klasifikasi' => $u->Klasifikasi,
            ],
        ]));
    }

    /** Struktur menu akun + daftar halaman yang masih tersedia. */
    public function data(string $userId)
    {
        try {
            $idUsers = Hashids::decode($userId)[0] ?? null;
            $u = $idUsers ? DB::table('N_WEB_CAREERS_Users')->where('Id_Users', $idUsers)->first() : null;
            if (! $u) {
                return ResponseHelper::error('Pengguna tidak ditemukan.', 404);
            }

            $rows = DB::table($this->tPage . ' as pa')
                ->leftJoin($this->tMenu . ' as m', 'm.Jenis_Page', '=', 'pa.Jenis_Page')
                ->where('pa.Id_Users', $idUsers)
                ->orderBy('pa.Urutan_Menu')
                ->orderBy('pa.Id_Page_Access')
                ->get([
                    'pa.Id_Page_Access', 'pa.Jenis_Page', 'pa.Urutan_Menu',
                    'pa.Nama_Header_Custom', 'pa.Sub_Header_Custom', 'pa.Nama_Menu_Custom', 'pa.Icon_Menu_Custom',
                    'pa.Nama_Grup_Custom',
                    'm.Nama_Menu', 'm.Nama_Header', 'm.Sub_Header', 'm.Icon_Menu', 'm.Url_Menu', 'm.Untuk_Role', 'm.Flag_Aktif',
                    'm.Nama_Grup',
                ]);

            $pageIds = $rows->pluck('Id_Page_Access')->all() ?: [0];
            $jumlahAksi = DB::table($this->tRma)->whereIn('Id_Page_Access', $pageIds)->where('Flag_Diizinkan', 'Y')
                ->select('Id_Page_Access', DB::raw('COUNT(*) as n'))->groupBy('Id_Page_Access')->pluck('n', 'Id_Page_Access');
            $jumlahKonten = DB::table($this->tRka)->whereIn('Id_Page_Access', $pageIds)->where('Flag_Diizinkan', 'Y')
                ->select('Id_Page_Access', DB::raw('COUNT(*) as n'))->groupBy('Id_Page_Access')->pluck('n', 'Id_Page_Access');
            $punyaView = DB::table($this->tRma . ' as rma')
                ->join('N_WEB_CAREERS_Aksi as a', 'a.Id_Aksi', '=', 'rma.Id_Aksi')
                ->whereIn('rma.Id_Page_Access', $pageIds)
                ->where('rma.Flag_Diizinkan', 'Y')->where('a.Nama_Aksi', 'VIEW')
                ->pluck('rma.Id_Page_Access')->flip();

            $items = $rows->map(function ($r) use ($jumlahAksi, $jumlahKonten, $punyaView) {
                $headerMaster = $r->Nama_Header ?: 'Menu';

                return [
                    'idPageAccess' => Hashids::encode($r->Id_Page_Access),
                    'jenisPage' => $r->Jenis_Page,
                    'urutan' => (int) $r->Urutan_Menu,

                    'header' => $r->Nama_Header_Custom ?: $headerMaster,
                    'headerMaster' => $headerMaster,

                    'label' => $r->Nama_Menu_Custom ?: ($r->Nama_Menu ?: $r->Jenis_Page),
                    'labelMaster' => $r->Nama_Menu ?: $r->Jenis_Page,
                    'labelCustom' => $r->Nama_Menu_Custom,

                    'ikon' => $r->Icon_Menu_Custom ?: ($r->Icon_Menu ?: 'bi bi-dot'),
                    'ikonMaster' => $r->Icon_Menu ?: 'bi bi-dot',
                    'ikonCustom' => $r->Icon_Menu_Custom,

                    'grup' => $r->Nama_Grup_Custom ?: $r->Nama_Grup,
                    'grupMaster' => $r->Nama_Grup,
                    'grupCustom' => $r->Nama_Grup_Custom,

                    'subHeader' => $r->Sub_Header_Custom ?: $r->Sub_Header,
                    'subHeaderMaster' => $r->Sub_Header,
                    'subHeaderCustom' => $r->Sub_Header_Custom,

                    'url' => $r->Url_Menu,
                    'role' => $r->Untuk_Role,
                    // Menu yatim = baris akses ada, tapi master menunya sudah dihapus.
                    'yatim' => $r->Nama_Menu === null,
                    'nonaktifMaster' => $r->Flag_Aktif !== null && $r->Flag_Aktif !== 'Y',
                    'jumlahAksi' => (int) ($jumlahAksi[$r->Id_Page_Access] ?? 0),
                    'jumlahKategori' => (int) ($jumlahKonten[$r->Id_Page_Access] ?? 0),
                    'punyaView' => $punyaView->has($r->Id_Page_Access),
                ];
            })->values();

            // Kelompokkan jadi grup, urut mengikuti item pertama tiap grup.
            $struktur = [];
            foreach ($items as $it) {
                $struktur[$it['header']] ??= [];
                $struktur[$it['header']][] = $it;
            }
            $struktur = array_map(fn ($judul, $isi) => ['judul' => $judul, 'items' => $isi], array_keys($struktur), $struktur);

            // Panel kiri: master menu aktif untuk peran akun ini, minus yang sudah dipegang.
            $sudah = $rows->pluck('Jenis_Page')->all();
            $peran = $u->Role === 'KANDIDAT' ? 'KANDIDAT' : 'ADMIN';
            $tersedia = DB::table($this->tMenu)
                ->where('Flag_Aktif', 'Y')
                ->where('Untuk_Role', $peran)
                ->whereNotIn('Jenis_Page', $sudah ?: [''])
                ->orderBy('Nama_Header')->orderBy('Urutan')
                ->get(['Jenis_Page', 'Nama_Menu', 'Nama_Header', 'Sub_Header', 'Icon_Menu', 'Url_Menu', 'Untuk_Role', 'Nama_Grup'])
                ->map(fn ($m) => [
                    'jenisPage' => $m->Jenis_Page,
                    'label' => $m->Nama_Menu,
                    'labelMaster' => $m->Nama_Menu,
                    'header' => $m->Nama_Header ?: 'Menu',
                    'headerMaster' => $m->Nama_Header ?: 'Menu',
                    'subHeaderMaster' => $m->Sub_Header,
                    'grup' => $m->Nama_Grup,
                    'grupMaster' => $m->Nama_Grup,
                    'ikon' => $m->Icon_Menu ?: 'bi bi-dot',
                    'ikonMaster' => $m->Icon_Menu ?: 'bi bi-dot',
                    'url' => $m->Url_Menu,
                    'role' => $m->Untuk_Role,
                ])->values();

            return ResponseHelper::success([
                'pengguna' => [
                    'id' => $userId,
                    'nama' => $u->Nama,
                    'email' => $u->Email,
                    'role' => $u->Role,
                    'klasifikasi' => $u->Klasifikasi,
                ],
                'struktur' => $struktur,
                'tersedia' => $tersedia,
                'diriSendiri' => (int) $idUsers === (int) session('career_auth.id'),
            ], 'Susunan menu dimuat');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal memuat susunan menu #{$userId}: " . $e->getMessage());

            return ResponseHelper::error('Gagal memuat susunan menu', 500);
        }
    }

    /**
     * Simpan susunan — SELISIH, bukan tulis ulang.
     *
     * Payload:
     *   grup : [{ judul, items: [{ idPageAccess|null, jenisPage, labelCustom,
     *                              ikonCustom, subHeaderCustom }] }]
     *   hapus: [idPageAccess, ...]   ← satu-satunya sumber penghapusan
     */
    public function simpan(Request $request, string $userId)
    {
        try {
            $idUsers = Hashids::decode($userId)[0] ?? null;
            $u = $idUsers ? DB::table('N_WEB_CAREERS_Users')->where('Id_Users', $idUsers)->first() : null;
            if (! $u) {
                return ResponseHelper::error('Pengguna tidak ditemukan.', 404);
            }

            $data = $request->validate([
                'grup' => 'present|array|max:100',
                'grup.*.judul' => 'required|string|max:100',
                'grup.*.items' => 'present|array|max:300',
                'grup.*.items.*.idPageAccess' => 'nullable|string|max:60',
                'grup.*.items.*.jenisPage' => 'required|string|max:60',
                'grup.*.items.*.labelCustom' => 'nullable|string|max:150',
                'grup.*.items.*.ikonCustom' => 'nullable|string|max:80',
                'grup.*.items.*.subHeaderCustom' => 'nullable|string|max:100',
                'grup.*.items.*.grupCustom' => 'nullable|string|max:60',
                'hapus' => 'nullable|array|max:300',
                'hapus.*' => 'string|max:60',
            ]);

            // ── Kondisi awal: apa yang sekarang dipegang akun ini ──
            $lama = DB::table($this->tPage)->where('Id_Users', $idUsers)->get()
                ->keyBy('Id_Page_Access');
            $lamaPerPage = $lama->keyBy('Jenis_Page');
            $master = DB::table($this->tMenu)->get()->keyBy('Jenis_Page');

            // ── Ratakan payload jadi daftar item + validasi menyeluruh DULU ──
            // Semua penolakan terjadi sebelum satu baris pun disentuh, supaya
            // tidak pernah ada simpan setengah jalan.
            $rencana = [];
            $terpakai = [];
            $urut = 0;

            foreach ($data['grup'] as $g) {
                $judul = trim($g['judul']) ?: 'Menu';

                foreach ($g['items'] as $it) {
                    $jenisPage = $it['jenisPage'];
                    $m = $master->get($jenisPage);
                    if (! $m) {
                        return ResponseHelper::error("Halaman \"{$jenisPage}\" tidak ada di Master Menu.", 422);
                    }
                    if (isset($terpakai[$jenisPage])) {
                        return ResponseHelper::error("Halaman \"{$m->Nama_Menu}\" muncul dua kali dalam susunan.", 422);
                    }
                    $terpakai[$jenisPage] = true;

                    // Id boleh datang dari klien, tapi kepemilikannya diperiksa di sini.
                    $pageId = null;
                    if (! empty($it['idPageAccess'])) {
                        $pageId = Hashids::decode($it['idPageAccess'])[0] ?? null;
                        if (! $pageId || ! $lama->has($pageId)) {
                            return ResponseHelper::error('Ada baris akses yang bukan milik pengguna ini — susunan tidak disimpan.', 422);
                        }
                        if ($lama[$pageId]->Jenis_Page !== $jenisPage) {
                            return ResponseHelper::error('Data susunan tidak sinkron dengan basis data. Muat ulang halaman lalu susun kembali.', 409);
                        }
                    } elseif ($lamaPerPage->has($jenisPage)) {
                        // Halaman sudah dipegang (mis. dua tab terbuka) → pakai
                        // baris yang ada. Jangan pernah menyisipkan kembar.
                        $pageId = $lamaPerPage[$jenisPage]->Id_Page_Access;
                    }

                    $urut += 10;
                    $rencana[] = [
                        'pageId' => $pageId,
                        'jenisPage' => $jenisPage,
                        'urutan' => $urut,
                        // NULL = ikut master. Nilai yang sama dengan master ikut
                        // dinormalkan jadi NULL supaya perubahan master tetap menular.
                        'Nama_Header_Custom' => $this->timpa($judul, $m->Nama_Header ?: 'Menu'),
                        'Nama_Grup_Custom' => $this->timpa($it['grupCustom'] ?? null, $m->Nama_Grup),
                        'Sub_Header_Custom' => $this->timpa($it['subHeaderCustom'] ?? null, $m->Sub_Header),
                        'Nama_Menu_Custom' => $this->timpa($it['labelCustom'] ?? null, $m->Nama_Menu),
                        'Icon_Menu_Custom' => $this->timpa($it['ikonCustom'] ?? null, $m->Icon_Menu),
                    ];
                }
            }

            // ── Penghapusan: hanya yang disebut eksplisit, milik akun ini, dan
            //    tidak ikut muncul di susunan ──
            $dibuang = [];
            foreach ($data['hapus'] ?? [] as $hash) {
                $pid = Hashids::decode($hash)[0] ?? null;
                if (! $pid || ! $lama->has($pid)) {
                    continue; // sudah hilang / bukan miliknya → abaikan, jangan gagalkan
                }
                if (in_array($pid, array_column($rencana, 'pageId'), true)) {
                    return ResponseHelper::error('Ada halaman yang sekaligus disusun dan dihapus — susunan tidak disimpan.', 422);
                }
                $dibuang[$pid] = $lama[$pid];
            }

            // Jangan biarkan admin mengunci dirinya sendiri keluar dari modul ini.
            if ((int) $idUsers === (int) session('career_auth.id')) {
                foreach ($dibuang as $row) {
                    if ($row->Jenis_Page === 'hakAksesPage') {
                        return ResponseHelper::error('Anda tidak bisa mencabut halaman Manajemen Hak Akses dari akun Anda sendiri.', 422);
                    }
                }
            }

            $nama = session('career_auth.nama', 'ADMIN');
            $hasil = ['ditambah' => 0, 'diubah' => 0, 'dihapus' => 0, 'tetap' => 0];

            DB::transaction(function () use ($rencana, $dibuang, $idUsers, $lama, $nama, &$hasil) {
                $idView = DB::table('N_WEB_CAREERS_Aksi')->where('Nama_Aksi', 'VIEW')->value('Id_Aksi');

                foreach ($rencana as $r) {
                    $isi = [
                        'Urutan_Menu' => $r['urutan'],
                        'Nama_Header_Custom' => $r['Nama_Header_Custom'],
                        'Nama_Grup_Custom' => $r['Nama_Grup_Custom'],
                        'Sub_Header_Custom' => $r['Sub_Header_Custom'],
                        'Nama_Menu_Custom' => $r['Nama_Menu_Custom'],
                        'Icon_Menu_Custom' => $r['Icon_Menu_Custom'],
                    ];

                    // ── BARIS LAMA → UPDATE DI TEMPAT ──
                    // Id_Page_Access dipertahankan, jadi centang aksi & kategori
                    // yang menggantung padanya tetap utuh.
                    if ($r['pageId']) {
                        $row = $lama[$r['pageId']];
                        $berubah = ((int) $row->Urutan_Menu !== $isi['Urutan_Menu']);
                        foreach (self::KOLOM_TIMPA as $k) {
                            $berubah = $berubah || (($row->{$k} ?? null) !== $isi[$k]);
                        }
                        if (! $berubah) {
                            $hasil['tetap']++;

                            continue; // tidak ada yang berubah → tidak usah menulis
                        }

                        DB::table($this->tPage)->where('Id_Page_Access', $r['pageId'])->update($isi + [
                            'Updated_At' => now(), 'Updated_By' => $nama, 'Updated_By_Id' => session('career_auth.id'),
                        ]);
                        $hasil['diubah']++;

                        continue;
                    }

                    // ── HALAMAN BARU → INSERT + bekal aksi VIEW ──
                    $pageId = DB::table($this->tPage)->insertGetId($isi + [
                        'Id_Users' => $idUsers,
                        'Jenis_Page' => $r['jenisPage'],
                        'Created_At' => now(), 'Created_By' => $nama, 'Created_By_Id' => session('career_auth.id'),
                        'Updated_At' => now(), 'Updated_By' => $nama, 'Updated_By_Id' => session('career_auth.id'),
                    ], 'Id_Page_Access');

                    if ($idView) {
                        DB::table($this->tRma)->insert([
                            'Id_Page_Access' => $pageId, 'Id_Aksi' => $idView, 'Flag_Diizinkan' => 'Y',
                            'Created_At' => now(), 'Created_By' => $nama, 'Updated_At' => now(), 'Updated_By' => $nama,
                        ]);
                    }
                    $hasil['ditambah']++;
                }

                // ── PENGHAPUSAN EKSPLISIT (beserta anak-anaknya) ──
                foreach ($dibuang as $pid => $row) {
                    DB::table($this->tRma)->where('Id_Page_Access', $pid)->delete();
                    DB::table($this->tRka)->where('Id_Page_Access', $pid)->delete();
                    DB::table($this->tPage)->where('Id_Page_Access', $pid)->delete();
                    $hasil['dihapus']++;
                }
            });

            AksesService::lupakan((int) $idUsers);
            Log::channel('web_career')->info(
                "Susun menu user #{$idUsers}: +{$hasil['ditambah']} ~{$hasil['diubah']} -{$hasil['dihapus']} (tetap {$hasil['tetap']}) oleh {$nama}"
            );

            return ResponseHelper::success($hasil, $this->ringkasan($hasil));
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ResponseHelper::error(collect($e->errors())->flatten()->first() ?? 'Data tidak valid', 422);
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal menyimpan susunan menu #{$userId}: " . $e->getMessage());

            return ResponseHelper::error('Gagal menyimpan susunan menu', 500);
        }
    }

    /** Nilai timpaan: kosong ATAU sama dengan master → NULL (ikut master). */
    private function timpa(?string $nilai, ?string $master): ?string
    {
        $nilai = trim((string) $nilai);

        return ($nilai === '' || $nilai === trim((string) $master)) ? null : $nilai;
    }

    private function ringkasan(array $h): string
    {
        $bagian = [];
        if ($h['ditambah']) {
            $bagian[] = "{$h['ditambah']} ditambah";
        }
        if ($h['diubah']) {
            $bagian[] = "{$h['diubah']} diperbarui";
        }
        if ($h['dihapus']) {
            $bagian[] = "{$h['dihapus']} dicabut";
        }

        return $bagian ? 'Susunan disimpan — ' . implode(', ', $bagian) . '.' : 'Tidak ada perubahan untuk disimpan.';
    }
}
