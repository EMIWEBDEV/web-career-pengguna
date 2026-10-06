<?php

namespace App\Http\Controllers\Career\Gembok;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

/**
 * GEMBOK — halaman bypass operasional untuk penjadwalan tes.
 *
 * ══ APA INI ══
 *
 * Satu halaman berisi beberapa kartu aksi yang mengerjakan hal-hal yang di
 * panel admin normal TIDAK bisa dikerjakan, atau bisa tapi harus lewat banyak
 * layar: memeriksa penjadwalan seorang kandidat dari Id_Users-nya, membatalkan
 * penjadwalan satu orang, dan menghapus penjadwalan satu program secara massal.
 *
 * ══ KENAPA TIDAK DI PANEL ADMIN SAJA ══
 *
 * Karena yang memakainya adalah orang yang sedang MEMPERBAIKI panel admin.
 * Pembatalan penjadwalan selama ini dikerjakan dengan menempelkan skrip SQL ke
 * basis data produksi satu per satu — lihat
 * docs/Batalkan_JDW-0010_Agar_Bisa_Dijadwalkan_Ulang_2026-08-12.sql, 200 baris
 * SQL untuk membatalkan SATU penjadwalan. Urutan langkahnya rumit dan tidak
 * dijaga foreign key apa pun (tidak ada satu pun FK di tabel penjadwalan), jadi
 * salah urutan tidak menimbulkan galat — ia hanya meninggalkan baris yatim yang
 * tidak terlihat siapa pun sampai berbulan-bulan kemudian.
 *
 * Halaman ini menjalankan urutan yang sama, tapi dari kode yang sudah diperiksa
 * sekali dan dipakai berkali-kali.
 *
 * ══ URUTAN PEMBATALAN — JANGAN DIUBAH TANPA MEMBACA INI ══
 *
 *   1. Lamaran_Tahap.Penjadwalan_Tahap_Id      → NULL
 *   2. Lamaran_Tahap_Tes.Penjadwalan_Tahap_Id  → NULL, Status → 'BELUM'
 *      (HANYA yang Flag_Selesai='N')
 *   3. DELETE Penjadwalan_Peserta
 *   4. DELETE Penjadwalan_Tahap   (hanya yang sudah tak berpeserta)
 *   5. DELETE Penjadwalan         (hanya yang sudah tak bertahap)
 *
 * LANGKAH 1 & 2 HARUS DULUAN. Begitu Penjadwalan_Tahap terhapus, tidak ada
 * lagi cara mengetahui lamaran mana yang tadinya terikat padanya — dan lamaran
 * itu akan selamanya memegang id tahap yang sudah tidak ada.
 *
 * STATUS Lamaran_Tahap SENGAJA TIDAK DISENTUH — biarkan tetap 'BERJALAN'.
 * Daftar "kandidat yang bisa dijadwalkan" di PenjadwalanController::kandidat()
 * menyaring dengan `t.Status='BERJALAN' AND st.Penjadwalan_Tahap_Id IS NULL`.
 * Mengubahnya ke 'MENUNGGU' justru MENGHILANGKAN kandidatnya dari daftar
 * pilihan — kebalikan dari yang diinginkan. Yang menandai "belum terjadwal"
 * adalah Penjadwalan_Tahap_Id yang NULL, bukan statusnya.
 *
 * ══ YANG TIDAK BISA DIKERJAKAN HALAMAN INI ══
 *
 * Token di HCLearn TIDAK ikut terhapus. Itu database lain, sistem lain, tanpa
 * foreign key di antaranya. Siapa pun yang sudah memegang link/OTP-nya masih
 * bisa mengerjakan tes, dan nilainya tidak akan sampai ke lamaran mana pun.
 *
 * Karena itu setiap penghapusan mengembalikan DAFTAR OTP yang baru saja
 * dilepas, dirangkai jadi kueri SQL siap tempel untuk dijalankan di sisi
 * HCLearn. Itu bukan hiasan — itu satu-satunya jejak yang tersisa setelah
 * barisnya hilang dari sini.
 */
class GembokController extends Controller
{
    /** Status pengerjaan yang berarti "ujiannya sudah tersentuh". */
    private const SUDAH_JALAN = ['mengerjakan', 'selesai', 'timeout'];

    /**
     * Halaman utama — kartu-kartu aksi.
     *
     * Sengaja TIDAK memuat data apa pun di muka. Halaman ini bisa saja dibuka
     * di produksi oleh orang yang sekadar memastikan gerbangnya masih terkunci;
     * memuatkan daftar program dan ribuan penjadwalan untuk itu adalah beban
     * yang tak seorang pun minta. Tiap kartu menarik datanya sendiri saat
     * dipakai.
     */
    public function index()
    {
        return Inertia::render('Career/bypass/Gembok', [
            'lindungiTerpakai' => (bool) config('gembok.lindungi_terpakai', false),
            'appEnv' => (string) config('app.env'),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | KARTU 1 — PERIKSA PENJADWALAN MILIK SATU KANDIDAT
    |--------------------------------------------------------------------------
    */

    /**
     * GET /ui/bypass/gembok/api/kandidat?idUser=123
     *
     * Dicari dengan Id_Users karena itu yang unik dan itu yang dipegang orang
     * yang sedang menelusuri gangguan. Email diterima juga — saat mengetik dari
     * keluhan kandidat, yang ada di tangan biasanya alamat emailnya, bukan
     * angka id-nya.
     */
    public function kandidat(Request $request)
    {
        $idUser = (int) $request->query('idUser', 0);
        $email = trim((string) $request->query('email', ''));

        if (! $idUser && $email === '') {
            return ResponseHelper::error('Masukkan Id Users atau email.', 422);
        }

        try {
            $user = DB::table('N_WEB_CAREERS_Users')
                ->when($idUser, fn ($q) => $q->where('Id_Users', $idUser))
                ->when(! $idUser && $email !== '', fn ($q) => $q->where('Email', $email))
                ->first(['Id_Users', 'Nama', 'Email', 'No_Hp', 'Role', 'Status', 'Created_At']);

            if (! $user) {
                return ResponseHelper::error('Kandidat tidak ditemukan.', 404);
            }

            $idUser = (int) $user->Id_Users;

            // Seluruh baris penjadwalan milik orang ini, lintas program dan
            // lintas gelombang. Dirangkai dari Peserta ke atas — bukan dari
            // Penjadwalan ke bawah — karena yang ditanya adalah "orang ini ada
            // di jadwal mana saja", bukan "jadwal ini isinya siapa saja".
            $baris = DB::table('N_WEB_CAREERS_Penjadwalan_Peserta as pp')
                ->leftJoin('N_WEB_CAREERS_Penjadwalan as pj', 'pj.Id_Penjadwalan', '=', 'pp.Penjadwalan_Id')
                ->leftJoin('N_WEB_CAREERS_Penjadwalan_Tahap as pt', 'pt.Id_Penjadwalan_Tahap', '=', 'pp.Penjadwalan_Tahap_Id')
                ->leftJoin('N_WEB_CAREERS_Program as prog', 'prog.Id_Program', '=', 'pj.Program_Id')
                ->where('pp.Users_Id', $idUser)
                ->orderByDesc('pp.Id_Penjadwalan_Peserta')
                ->get([
                    'pp.Id_Penjadwalan_Peserta as idPeserta',
                    'pp.Penjadwalan_Id as idPenjadwalan',
                    'pp.Penjadwalan_Tahap_Id as idTahap',
                    'pp.Lamaran_Id as idLamaran',
                    'pp.Kode_Peserta as kodePeserta',
                    'pp.Nama as nama',
                    'pp.Email as email',
                    'pp.Posisi_Dilamar as posisi',
                    'pp.Short_Token as token',
                    'pp.Akses_OTP as otp',
                    'pp.Link_Ujian as link',
                    'pp.Id_Ujian_Token as idUjianToken',
                    'pp.Status_Kirim as statusKirim',
                    'pp.Status_Pengerjaan as statusKerja',
                    'pp.Flag_Selesai as flagSelesai',
                    'pp.Total_Nilai as nilai',
                    'pp.Status_Kelulusan as kelulusan',
                    'pp.Waktu_Mulai_Akses as mulaiAkses',
                    'pp.Waktu_Selesai_Akses as selesaiAkses',
                    'pp.Pesan_Error as pesanError',
                    'pj.Kode as kodeJadwal',
                    'pj.Nama as namaJadwal',
                    'pj.Status as statusJadwal',
                    'pj.Tanggal_Mulai as tanggalMulai',
                    'pj.Tanggal_Selesai as tanggalSelesai',
                    'pt.Urutan as urutanTahap',
                    'pt.Label as labelTahap',
                    'pt.Kode as kodeTahap',
                    'pt.Provider as provider',
                    'pt.Nama_Ujian as namaUjian',
                    'pt.Waktu_Mulai as waktuMulai',
                    'pt.Waktu_Akhir as waktuAkhir',
                    'pt.Status as statusTahap',
                    'pt.Pesan_Error as tahapError',
                    'prog.Nama as namaProgram',
                    'prog.Kode as kodeProgram',
                ]);

            // Lamaran orang ini — konteks yang menjelaskan KENAPA sebuah
            // penjadwalan ada (atau kenapa tidak ada). Tanpa ini, "kandidat
            // tidak punya jadwal" tidak bisa dibedakan dari "kandidat memang
            // belum pernah melamar".
            $lamaran = DB::table('N_WEB_CAREERS_Lamaran as l')
                ->leftJoin('N_WEB_CAREERS_Program as prog', 'prog.Id_Program', '=', 'l.Program_Id')
                ->where('l.Id_Users', $idUser)
                ->orderByDesc('l.Id_Lamaran')
                ->get([
                    'l.Id_Lamaran as idLamaran',
                    'l.Program_Id as idProgram',
                    'l.Status as status',
                    'prog.Nama as namaProgram',
                    'prog.Kode as kodeProgram',
                ]);

            // Tahap tiap lamaran, berikut apakah ia sedang menunjuk sebuah
            // penjadwalan. Kolom idTahapJadwal inilah jawaban sebenarnya atas
            // "kenapa orang ini tidak muncul di daftar bisa-dijadwalkan".
            $idLamaran = $lamaran->pluck('idLamaran')->all();

            $tahapLamaran = $idLamaran
                ? DB::table('N_WEB_CAREERS_Lamaran_Tahap as lt')
                    ->whereIn('lt.Lamaran_Id', $idLamaran)
                    ->orderBy('lt.Lamaran_Id')
                    ->orderBy('lt.Urutan')
                    ->get([
                        'lt.Id_Lamaran_Tahap as id',
                        'lt.Lamaran_Id as idLamaran',
                        'lt.Urutan as urutan',
                        'lt.Label as label',
                        'lt.Status as status',
                        // Kolomnya `Hasil`, BUKAN `Keputusan`. Skema di
                        // docs/N_WEB_CAREERS_Admin_Schema_2026-07-16.sql sudah
                        // tidak sesuai tabel yang sebenarnya — yang berlaku
                        // adalah tabelnya.
                        'lt.Hasil as hasil',
                        'lt.Penjadwalan_Tahap_Id as idTahapJadwal',
                    ])
                : collect();

            return ResponseHelper::success([
                'user' => [
                    'id' => (int) $user->Id_Users,
                    'nama' => $user->Nama,
                    'email' => $user->Email,
                    'noHp' => $user->No_Hp,
                    'role' => $user->Role,
                    'status' => $user->Status,
                    'terdaftar' => $user->Created_At,
                ],
                'penjadwalan' => $baris,
                'lamaran' => $lamaran,
                'tahapLamaran' => $tahapLamaran,
                'ringkas' => [
                    'totalJadwal' => $baris->count(),
                    'sudahJalan' => $baris->filter(fn ($b) => $this->sudahJalan($b->statusKerja, $b->flagSelesai))->count(),
                    'punyaToken' => $baris->filter(fn ($b) => ! empty($b->token))->count(),
                ],
            ], 'Data kandidat ditemukan.');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('GEMBOK cek kandidat gagal: '.$e->getMessage());

            return ResponseHelper::error('Gagal membaca data kandidat: '.$e->getMessage(), 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | KARTU 2 — BATALKAN PENJADWALAN SATU ORANG
    |--------------------------------------------------------------------------
    */

    /**
     * POST /ui/bypass/gembok/api/batal-peserta
     *
     * body: { idUser, idPeserta?: [], paksa?: bool }
     *
     * Menghapus baris PESERTA-nya saja, bukan seluruh penjadwalan: yang lain
     * di gelombang yang sama tidak boleh ikut terbawa. Penjadwalan induknya
     * ikut terhapus HANYA bila setelah ini ia benar-benar kosong — jadwal tanpa
     * satu peserta pun tidak berguna bagi siapa pun, dan ia akan terus muncul
     * di layar daftar sebagai baris yang membingungkan.
     */
    public function batalPeserta(Request $request)
    {
        $idUser = (int) $request->input('idUser', 0);
        $pilih = array_values(array_filter(array_map('intval', (array) $request->input('idPeserta', []))));
        $paksa = $request->boolean('paksa');

        if (! $idUser) {
            return ResponseHelper::error('Id Users wajib diisi.', 422);
        }

        try {
            $peserta = DB::table('N_WEB_CAREERS_Penjadwalan_Peserta')
                ->where('Users_Id', $idUser)
                // Kosong = seluruh jadwal milik orang ini. Terisi = hanya yang
                // dicentang — dan tetap disaring Users_Id, supaya id peserta
                // milik orang lain tidak bisa diselipkan lewat body permintaan.
                ->when($pilih, fn ($q) => $q->whereIn('Id_Penjadwalan_Peserta', $pilih))
                ->get();

            if ($peserta->isEmpty()) {
                return ResponseHelper::error('Tidak ada penjadwalan milik kandidat ini yang cocok.', 404);
            }

            $terpakai = $peserta->filter(fn ($p) => $this->sudahJalan($p->Status_Pengerjaan, $p->Flag_Selesai));

            $tolak = $this->periksaTerpakai($terpakai->count(), $peserta->count(), $paksa);
            if ($tolak) {
                return $tolak;
            }

            $hasil = $this->jalankanHapus($peserta, 'GEMBOK(batal-peserta:'.$idUser.')');

            Log::channel('web_career')->warning(
                'GEMBOK membatalkan '.$hasil['peserta'].' penjadwalan milik Users_Id '.$idUser
                .($terpakai->isNotEmpty() ? ' — TERMASUK '.$terpakai->count().' yang sudah dikerjakan (dipaksa)' : '')
            );

            return ResponseHelper::success($hasil, 'Penjadwalan kandidat dibatalkan.');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('GEMBOK batal peserta gagal: '.$e->getMessage());

            return ResponseHelper::error('Gagal membatalkan: '.$e->getMessage(), 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | KARTU 3 — HAPUS TOTAL PER PROGRAM
    |--------------------------------------------------------------------------
    */

    /**
     * GET /ui/bypass/gembok/api/penjadwalan
     *
     * Daftar SELURUH penjadwalan — inilah `select * from
     * N_WEB_CAREERS_Penjadwalan` yang diminta, tapi sudah dirangkai dengan nama
     * program dan hitungan pesertanya, karena yang menentukan boleh-tidaknya
     * sebuah baris dihapus adalah isinya, bukan barisnya sendiri.
     */
    public function penjadwalan(Request $request)
    {
        try {
            $idProgram = (int) $request->query('idProgram', 0);
            $tanggal = trim((string) $request->query('tanggal', ''));

            // TANGGALNYA DIPERIKSA DI SINI, bukan diserahkan ke basis data.
            // Kolom Tanggal_Mulai bertipe date; teks yang bukan tanggal membuat
            // SQL Server melempar "Conversion failed", dan yang sampai ke layar
            // adalah galat 500 berisi seluruh kueri — bukan jawaban atas apa
            // yang salah. Kotaknya kini <input type="date"> yang isinya bisa
            // diketik bebas di sebagian peramban, jadi ini bukan hal mustahil.
            // Diperiksa dua lapis: BENTUKNYA lewat pola, lalu KEBERADAANNYA di
            // kalender. Pola saja meloloskan 2026-02-30 — bentuknya sempurna,
            // harinya tidak pernah ada — dan itu tetap berakhir sebagai galat
            // konversi dari basis data.
            if ($tanggal !== '') {
                $d = \DateTime::createFromFormat('Y-m-d', $tanggal);

                if (! $d || $d->format('Y-m-d') !== $tanggal) {
                    return ResponseHelper::error('Tanggal tidak sah — pakai format YYYY-MM-DD.', 422);
                }
            }

            $rows = DB::table('N_WEB_CAREERS_Penjadwalan as pj')
                ->leftJoin('N_WEB_CAREERS_Program as prog', 'prog.Id_Program', '=', 'pj.Program_Id')
                ->when($idProgram, fn ($q) => $q->where('pj.Program_Id', $idProgram))
                ->when($tanggal !== '', fn ($q) => $q->whereDate('pj.Tanggal_Mulai', $tanggal))
                ->orderByDesc('pj.Id_Penjadwalan')
                ->get([
                    'pj.Id_Penjadwalan as id',
                    'pj.Kode as kode',
                    'pj.Nama as nama',
                    'pj.Program_Id as idProgram',
                    'pj.Status as status',
                    'pj.Tanggal_Mulai as tanggalMulai',
                    'pj.Tanggal_Selesai as tanggalSelesai',
                    'pj.Jumlah_Tahap as jumlahTahap',
                    'pj.Jumlah_Peserta as jumlahPeserta',
                    'pj.Created_At as dibuat',
                    'pj.Created_By as dibuatOleh',
                    'prog.Nama as namaProgram',
                    'prog.Kode as kodeProgram',
                ]);

            // Hitungan SEBENARNYA, bukan kolom Jumlah_Peserta. Kolom itu diisi
            // saat penjadwalan dibuat dan tidak selalu ikut turun ketika
            // pesertanya berkurang — angka basi di layar konfirmasi hapus
            // adalah cara yang bagus untuk menghapus lebih banyak dari dikira.
            $ids = $rows->pluck('id')->all();

            // COUNT(*) DIBERI NAMA. Tanpa alias, pluck() mencari properti
            // bernama "COUNT(*)" pada baris hasil dan tidak pernah
            // menemukannya: hitungannya diam-diam jadi 0 untuk semua baris —
            // tanpa galat, hanya angka nol di layar konfirmasi hapus.
            $nyata = $ids
                ? DB::table('N_WEB_CAREERS_Penjadwalan_Peserta')
                    ->whereIn('Penjadwalan_Id', $ids)
                    ->groupBy('Penjadwalan_Id')
                    ->pluck(DB::raw('COUNT(*) as jml'), 'Penjadwalan_Id')
                : collect();

            $terpakai = $ids
                ? DB::table('N_WEB_CAREERS_Penjadwalan_Peserta')
                    ->whereIn('Penjadwalan_Id', $ids)
                    ->where(fn ($q) => $q->whereIn('Status_Pengerjaan', self::SUDAH_JALAN)->orWhere('Flag_Selesai', 'Y'))
                    ->groupBy('Penjadwalan_Id')
                    ->pluck(DB::raw('COUNT(*) as jml'), 'Penjadwalan_Id')
                : collect();

            $rows->transform(function ($r) use ($nyata, $terpakai) {
                $r->pesertaNyata = (int) ($nyata[$r->id] ?? 0);
                $r->pesertaTerpakai = (int) ($terpakai[$r->id] ?? 0);

                return $r;
            });

            // Program yang BENAR-BENAR punya penjadwalan. Dropdown yang memuat
            // seluruh master program membuat sebagian besar pilihannya berakhir
            // di layar kosong.
            $program = DB::table('N_WEB_CAREERS_Program as prog')
                ->join('N_WEB_CAREERS_Penjadwalan as pj', 'pj.Program_Id', '=', 'prog.Id_Program')
                ->groupBy('prog.Id_Program', 'prog.Nama', 'prog.Kode', 'prog.Kategori')
                ->orderBy('prog.Nama')
                ->get([
                    'prog.Id_Program as id',
                    'prog.Nama as nama',
                    'prog.Kode as kode',
                    'prog.Kategori as kategori',
                    DB::raw('COUNT(pj.Id_Penjadwalan) as jumlahJadwal'),
                ]);

            // Hari yang benar-benar ada isinya — dipakai sebagai pilihan
            // "pilih hari" pada penghapusan massal.
            $hari = DB::table('N_WEB_CAREERS_Penjadwalan')
                ->when($idProgram, fn ($q) => $q->where('Program_Id', $idProgram))
                ->whereNotNull('Tanggal_Mulai')
                ->groupBy('Tanggal_Mulai')
                ->orderByDesc('Tanggal_Mulai')
                ->get(['Tanggal_Mulai as tanggal', DB::raw('COUNT(*) as jumlah')]);

            return ResponseHelper::success([
                'penjadwalan' => $rows,
                'program' => $program,
                'hari' => $hari,
            ], 'Daftar penjadwalan.');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('GEMBOK daftar penjadwalan gagal: '.$e->getMessage());

            return ResponseHelper::error('Gagal membaca daftar penjadwalan: '.$e->getMessage(), 500);
        }
    }

    /**
     * POST /ui/bypass/gembok/api/pratinjau
     *
     * body: { idPenjadwalan: [] }
     *
     * BERAPA YANG AKAN TERHAPUS — dijawab SEBELUM apa pun dihapus.
     *
     * Ini permintaan yang paling penting di halaman ini. "Yakin hapus?" yang
     * tidak menyebut angka adalah pertanyaan yang selalu dijawab "ya", dan
     * penghapusan massal yang salah sasaran baru ketahuan ketika kandidatnya
     * menelepon.
     */
    public function pratinjau(Request $request)
    {
        $ids = array_values(array_filter(array_map('intval', (array) $request->input('idPenjadwalan', []))));

        if (! $ids) {
            return ResponseHelper::error('Pilih dulu penjadwalan yang mau dihapus.', 422);
        }

        try {
            $jadwal = DB::table('N_WEB_CAREERS_Penjadwalan as pj')
                ->leftJoin('N_WEB_CAREERS_Program as prog', 'prog.Id_Program', '=', 'pj.Program_Id')
                ->whereIn('pj.Id_Penjadwalan', $ids)
                ->orderByDesc('pj.Id_Penjadwalan')
                ->get([
                    'pj.Id_Penjadwalan as id',
                    'pj.Kode as kode',
                    'pj.Nama as nama',
                    'pj.Tanggal_Mulai as tanggalMulai',
                    'prog.Nama as namaProgram',
                ]);

            $peserta = DB::table('N_WEB_CAREERS_Penjadwalan_Peserta')
                ->whereIn('Penjadwalan_Id', $ids)
                ->get([
                    'Id_Penjadwalan_Peserta as idPeserta',
                    'Penjadwalan_Id as idPenjadwalan',
                    'Users_Id as idUser',
                    'Nama as nama',
                    'Email as email',
                    'Akses_OTP as otp',
                    'Short_Token as token',
                    'Status_Pengerjaan as statusKerja',
                    'Flag_Selesai as flagSelesai',
                ]);

            $tahapIds = DB::table('N_WEB_CAREERS_Penjadwalan_Tahap')
                ->whereIn('Penjadwalan_Id', $ids)
                ->pluck('Id_Penjadwalan_Tahap')->all();

            $terpakai = $peserta->filter(fn ($p) => $this->sudahJalan($p->statusKerja, $p->flagSelesai));

            return ResponseHelper::success([
                'jadwal' => $jadwal,
                'jumlah' => [
                    'penjadwalan' => $jadwal->count(),
                    'tahap' => count($tahapIds),
                    'peserta' => $peserta->count(),
                    'berToken' => $peserta->filter(fn ($p) => ! empty($p->token))->count(),
                    'berOtp' => $peserta->filter(fn ($p) => ! empty($p->otp))->count(),
                    'sudahDikerjakan' => $terpakai->count(),
                    // Lamaran yang akan dilepas ikatannya. Angka inilah yang
                    // sebenarnya berarti "berapa kandidat kembali bisa
                    // dijadwalkan setelah ini".
                    'lamaranTahap' => $tahapIds
                        ? DB::table('N_WEB_CAREERS_Lamaran_Tahap')->whereIn('Penjadwalan_Tahap_Id', $tahapIds)->count()
                        : 0,
                    'lamaranTahapTes' => $tahapIds
                        ? DB::table('N_WEB_CAREERS_Lamaran_Tahap_Tes')->whereIn('Penjadwalan_Tahap_Id', $tahapIds)->where('Flag_Selesai', 'N')->count()
                        : 0,
                ],
                'contohPeserta' => $peserta->take(50)->values(),
                'sudahDikerjakan' => $terpakai->take(50)->values(),
            ], 'Pratinjau penghapusan.');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('GEMBOK pratinjau gagal: '.$e->getMessage());

            return ResponseHelper::error('Gagal menghitung pratinjau: '.$e->getMessage(), 500);
        }
    }

    /**
     * POST /ui/bypass/gembok/api/hapus-total
     *
     * body: { idPenjadwalan: [], paksa?: bool, konfirmasi: 'HAPUS' }
     */
    public function hapusTotal(Request $request)
    {
        $ids = array_values(array_filter(array_map('intval', (array) $request->input('idPenjadwalan', []))));
        $paksa = $request->boolean('paksa');

        if (! $ids) {
            return ResponseHelper::error('Pilih dulu penjadwalan yang mau dihapus.', 422);
        }

        // Ketikan konfirmasi. Bukan formalitas: penghapusan massal adalah satu
        // klik yang akibatnya tidak terlihat di layar mana pun sampai ada yang
        // mencari jadwalnya. Mengetik memaksa berhenti sejenak.
        if (strtoupper(trim((string) $request->input('konfirmasi'))) !== 'HAPUS') {
            return ResponseHelper::error('Ketik HAPUS pada kotak konfirmasi untuk melanjutkan.', 422);
        }

        try {
            $peserta = DB::table('N_WEB_CAREERS_Penjadwalan_Peserta')
                ->whereIn('Penjadwalan_Id', $ids)
                ->get();

            $terpakai = $peserta->filter(fn ($p) => $this->sudahJalan($p->Status_Pengerjaan, $p->Flag_Selesai));

            $tolak = $this->periksaTerpakai($terpakai->count(), $peserta->count(), $paksa);
            if ($tolak) {
                return $tolak;
            }

            $hasil = $this->jalankanHapus($peserta, 'GEMBOK(hapus-total)', $ids);

            Log::channel('web_career')->warning(
                'GEMBOK MENGHAPUS TOTAL penjadwalan ['.implode(',', $ids).'] — '
                .$hasil['peserta'].' peserta, '.$hasil['tahap'].' tahap, '.$hasil['penjadwalan'].' penjadwalan'
                .($terpakai->isNotEmpty() ? ' — TERMASUK '.$terpakai->count().' yang sudah dikerjakan (dipaksa)' : '')
            );

            return ResponseHelper::success($hasil, 'Penjadwalan dihapus.');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('GEMBOK hapus total gagal: '.$e->getMessage());

            return ResponseHelper::error('Gagal menghapus: '.$e->getMessage(), 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | KARTU 4 — PERIKSA BARIS YATIM
    |--------------------------------------------------------------------------
    */

    /**
     * GET /ui/bypass/gembok/api/kesehatan
     *
     * Tidak ada satu pun foreign key di tabel penjadwalan. Artinya penghapusan
     * dengan urutan yang salah — termasuk yang dikerjakan lewat SQL manual
     * bertahun-tahun sebelum halaman ini ada — tidak menimbulkan galat apa pun.
     * Ia hanya meninggalkan lamaran yang memegang id tahap penjadwalan yang
     * sudah tidak ada.
     *
     * Gejalanya baru muncul jauh belakangan: kandidatnya tidak bisa dijadwalkan
     * ulang, dan tidak ada satu layar pun yang menjelaskan kenapa. Kartu ini
     * yang menjelaskan.
     */
    public function kesehatan()
    {
        try {
            // Baris yang MENUNJUK tahap yang sudah tidak ada. Dibuat sebagai
            // pabrik kueri, bukan kueri jadi: sebuah query builder yang sudah
            // dipakai sekali membawa sisa klausa dari pemakaian sebelumnya.
            $yatim = fn (string $tabel) => DB::table($tabel.' as x')
                ->whereNotNull('x.Penjadwalan_Tahap_Id')
                ->whereNotExists(fn ($q) => $q->select(DB::raw(1))
                    ->from('N_WEB_CAREERS_Penjadwalan_Tahap as pt')
                    ->whereColumn('pt.Id_Penjadwalan_Tahap', 'x.Penjadwalan_Tahap_Id'));

            $yatimLamaranTahap = $yatim('N_WEB_CAREERS_Lamaran_Tahap')->count();
            $yatimLamaranTahapTes = $yatim('N_WEB_CAREERS_Lamaran_Tahap_Tes')->count();

            // Peserta yang induknya sudah hilang — sisa penghapusan yang
            // berhenti di tengah jalan.
            $pesertaYatim = DB::table('N_WEB_CAREERS_Penjadwalan_Peserta as pp')
                ->whereNotExists(fn ($q) => $q->select(DB::raw(1))
                    ->from('N_WEB_CAREERS_Penjadwalan as pj')
                    ->whereColumn('pj.Id_Penjadwalan', 'pp.Penjadwalan_Id'))
                ->count();

            $tahapYatim = DB::table('N_WEB_CAREERS_Penjadwalan_Tahap as pt')
                ->whereNotExists(fn ($q) => $q->select(DB::raw(1))
                    ->from('N_WEB_CAREERS_Penjadwalan as pj')
                    ->whereColumn('pj.Id_Penjadwalan', 'pt.Penjadwalan_Id'))
                ->count();

            // Penjadwalan tanpa satu peserta pun. Bukan kerusakan — tapi hampir
            // selalu sisa percobaan yang gagal, dan ia memenuhi layar daftar
            // tanpa guna.
            $jadwalKosong = DB::table('N_WEB_CAREERS_Penjadwalan as pj')
                ->whereNotExists(fn ($q) => $q->select(DB::raw(1))
                    ->from('N_WEB_CAREERS_Penjadwalan_Peserta as pp')
                    ->whereColumn('pp.Penjadwalan_Id', 'pj.Id_Penjadwalan'))
                ->orderByDesc('pj.Id_Penjadwalan')
                ->get([
                    'pj.Id_Penjadwalan as id',
                    'pj.Kode as kode',
                    'pj.Nama as nama',
                    'pj.Status as status',
                    'pj.Created_At as dibuat',
                ]);

            // Contoh baris yatimnya, supaya yang membaca tahu ini menyangkut
            // SIAPA — bukan cuma sebuah angka.
            $contoh = $yatim('N_WEB_CAREERS_Lamaran_Tahap')
                ->leftJoin('N_WEB_CAREERS_Lamaran as l', 'l.Id_Lamaran', '=', 'x.Lamaran_Id')
                ->leftJoin('N_WEB_CAREERS_Users as u', 'u.Id_Users', '=', 'l.Id_Users')
                ->limit(50)
                ->get([
                    'x.Id_Lamaran_Tahap as id',
                    'x.Lamaran_Id as idLamaran',
                    'x.Penjadwalan_Tahap_Id as idTahapHilang',
                    'x.Label as label',
                    'x.Status as status',
                    'u.Id_Users as idUser',
                    'u.Nama as nama',
                    'u.Email as email',
                ]);

            return ResponseHelper::success([
                'yatim' => [
                    'lamaranTahap' => $yatimLamaranTahap,
                    'lamaranTahapTes' => $yatimLamaranTahapTes,
                    'peserta' => $pesertaYatim,
                    'tahap' => $tahapYatim,
                ],
                'jadwalKosong' => $jadwalKosong,
                'contoh' => $contoh,
                'sehat' => ($yatimLamaranTahap + $yatimLamaranTahapTes + $pesertaYatim + $tahapYatim) === 0,
            ], 'Pemeriksaan selesai.');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('GEMBOK cek kesehatan gagal: '.$e->getMessage());

            return ResponseHelper::error('Gagal memeriksa: '.$e->getMessage(), 500);
        }
    }

    /**
     * POST /ui/bypass/gembok/api/bersihkan-yatim
     *
     * Melepas rujukan yang menunjuk tahap yang sudah tidak ada. Hanya
     * meng-NULL-kan — tidak ada baris yang dihapus di sini, karena yang rusak
     * adalah TAUTANNYA, bukan lamarannya.
     */
    public function bersihkanYatim(Request $request)
    {
        if (strtoupper(trim((string) $request->input('konfirmasi'))) !== 'BERSIHKAN') {
            return ResponseHelper::error('Ketik BERSIHKAN untuk melanjutkan.', 422);
        }

        try {
            $hasil = DB::transaction(function () {
                $kini = now();
                $oleh = 'GEMBOK(bersih-yatim)';

                $tanpaInduk = fn ($q) => $q->select(DB::raw(1))
                    ->from('N_WEB_CAREERS_Penjadwalan_Tahap as pt')
                    ->whereColumn('pt.Id_Penjadwalan_Tahap', 'Penjadwalan_Tahap_Id');

                $a = DB::table('N_WEB_CAREERS_Lamaran_Tahap')
                    ->whereNotNull('Penjadwalan_Tahap_Id')
                    ->whereNotExists($tanpaInduk)
                    ->update(['Penjadwalan_Tahap_Id' => null, 'Updated_At' => $kini, 'Updated_By' => $oleh]);

                // Statusnya TIDAK disentuh di sini. Berbeda dari pembatalan
                // biasa, yang ini menambal tautan yang rusak entah sejak kapan —
                // dan sebagian di antaranya sudah dikerjakan orangnya. Yang
                // rusak cuma tautannya.
                $b = DB::table('N_WEB_CAREERS_Lamaran_Tahap_Tes')
                    ->whereNotNull('Penjadwalan_Tahap_Id')
                    ->whereNotExists($tanpaInduk)
                    ->update(['Penjadwalan_Tahap_Id' => null, 'Updated_At' => $kini, 'Updated_By' => $oleh]);

                return ['lamaranTahap' => $a, 'lamaranTahapTes' => $b];
            });

            Log::channel('web_career')->warning(
                'GEMBOK membersihkan baris yatim: '.$hasil['lamaranTahap'].' Lamaran_Tahap, '
                .$hasil['lamaranTahapTes'].' Lamaran_Tahap_Tes'
            );

            return ResponseHelper::success($hasil, 'Baris yatim dibersihkan.');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('GEMBOK bersih yatim gagal: '.$e->getMessage());

            return ResponseHelper::error('Gagal membersihkan: '.$e->getMessage(), 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | MESIN PENGHAPUS — dipakai bersama kartu 2 & 3
    |--------------------------------------------------------------------------
    */

    /** Apakah sebuah baris peserta berarti "ujiannya sudah tersentuh". */
    private function sudahJalan(?string $statusKerja, ?string $flagSelesai): bool
    {
        return in_array(strtolower((string) $statusKerja), self::SUDAH_JALAN, true)
            || $flagSelesai === 'Y';
    }

    /**
     * Gerbang bersama untuk jadwal yang ujiannya sudah dikerjakan.
     *
     * Mengembalikan respons penolakan bila tidak boleh lanjut, atau null bila
     * boleh. Menghapus jadwal yang sudah dikerjakan menghilangkan jejak
     * hasilnya, dan orangnya berpeluang dites dua kali. Di sini ia BISA
     * ditembus — itu gunanya halaman ini — tapi tidak diam-diam: penembusannya
     * harus disebut dari layar.
     */
    private function periksaTerpakai(int $terpakai, int $total, bool $paksa)
    {
        if ($terpakai === 0) {
            return null;
        }

        if (config('gembok.lindungi_terpakai', false)) {
            return ResponseHelper::error(
                $terpakai.' peserta sudah mengerjakan ujiannya. Pengaman GEMBOK_LINDUNGI_TERPAKAI sedang menyala, jadi ini tidak bisa dihapus dari sini.',
                409
            );
        }

        if (! $paksa) {
            return ResponseHelper::error(
                $terpakai.' dari '.$total.' peserta SUDAH MENGERJAKAN ujiannya. Menghapusnya menghilangkan jejak hasil mereka. Centang konfirmasi paksa bila memang itu yang dimaksud.',
                409
            );
        }

        return null;
    }

    /**
     * Menjalankan runtun pembatalan untuk sekumpulan baris peserta.
     *
     * OTP DIPANEN DULU, SEBELUM APA PUN DIHAPUS. Setelah barisnya hilang tidak
     * ada lagi cara mengetahui OTP mana yang perlu dicabut di sisi HCLearn —
     * dan itulah satu-satunya jejak yang tersisa dari token yang masih hidup di
     * sana.
     *
     * @param  \Illuminate\Support\Collection  $peserta  baris Penjadwalan_Peserta
     * @param  array<int>  $paksaPenjadwalan  id penjadwalan yang wajib ikut ditinjau
     *                                        untuk dihapus (dipakai hapus-total,
     *                                        supaya jadwal yang memang sudah
     *                                        kosong tetap hilang)
     */
    private function jalankanHapus($peserta, string $oleh, array $paksaPenjadwalan = []): array
    {
        // ── PANEN DULU ──────────────────────────────────────────────────────
        $otp = $peserta->pluck('Akses_OTP')->filter(fn ($v) => $v !== null && $v !== '')->unique()->values();
        $token = $peserta->pluck('Short_Token')->filter(fn ($v) => $v !== null && $v !== '')->unique()->values();
        $idUjianToken = $peserta->pluck('Id_Ujian_Token')->filter()->unique()->values();

        $idPeserta = $peserta->pluck('Id_Penjadwalan_Peserta')->all();
        $idJadwal = $peserta->pluck('Penjadwalan_Id')->filter()->unique()->values()->all();
        $idJadwal = array_values(array_unique(array_merge($idJadwal, $paksaPenjadwalan)));

        // Rincian per orang — dipakai layar untuk menampilkan siapa saja yang
        // barusan dilepas, dan sebagai bahan salinan bagi tim HCLearn.
        $rincian = $peserta->map(fn ($p) => [
            'idPeserta' => (int) $p->Id_Penjadwalan_Peserta,
            'idUser' => $p->Users_Id ? (int) $p->Users_Id : null,
            'nama' => $p->Nama,
            'email' => $p->Email,
            'otp' => $p->Akses_OTP,
            'token' => $p->Short_Token,
            'idUjianToken' => $p->Id_Ujian_Token ? (int) $p->Id_Ujian_Token : null,
            'idPenjadwalan' => (int) $p->Penjadwalan_Id,
            'statusKerja' => $p->Status_Pengerjaan,
        ])->values();

        $hasil = DB::transaction(function () use ($idPeserta, $idJadwal, $oleh) {
            $kini = now();

            // Tahap yang tersentuh, DIBACA SEBELUM pesertanya dihapus — sesudah
            // itu tautannya sudah tidak ada.
            $tahapIds = DB::table('N_WEB_CAREERS_Penjadwalan_Peserta')
                ->whereIn('Id_Penjadwalan_Peserta', $idPeserta)
                ->pluck('Penjadwalan_Tahap_Id')->filter()->unique()->values()->all();

            // Lamaran milik ORANG-ORANG INI saja. Melepas seluruh lamaran yang
            // menunjuk tahap tsb akan ikut membatalkan peserta lain di
            // gelombang yang sama — yang tidak diminta siapa pun.
            $lamaranIds = DB::table('N_WEB_CAREERS_Penjadwalan_Peserta')
                ->whereIn('Id_Penjadwalan_Peserta', $idPeserta)
                ->pluck('Lamaran_Id')->filter()->unique()->values()->all();

            $lepasA = 0;
            $lepasB = 0;

            // ── 1 & 2. LEPAS RUJUKAN — HARUS SEBELUM DELETE ────────────────
            if ($tahapIds && $lamaranIds) {
                // Status Lamaran_Tahap SENGAJA TIDAK DIUBAH — lihat catatan
                // panjang di kepala kelas ini.
                $lepasA = DB::table('N_WEB_CAREERS_Lamaran_Tahap')
                    ->whereIn('Penjadwalan_Tahap_Id', $tahapIds)
                    ->whereIn('Lamaran_Id', $lamaranIds)
                    ->update(['Penjadwalan_Tahap_Id' => null, 'Updated_At' => $kini, 'Updated_By' => $oleh]);

                $lepasB = DB::table('N_WEB_CAREERS_Lamaran_Tahap_Tes')
                    ->whereIn('Penjadwalan_Tahap_Id', $tahapIds)
                    // Yang sudah dikerjakan jangan diusik: nilainya akan hilang
                    // dari jejak dan orangnya berpeluang dites dua kali.
                    ->where('Flag_Selesai', 'N')
                    ->whereIn('Lamaran_Tahap_Id', function ($q) use ($lamaranIds) {
                        $q->select('Id_Lamaran_Tahap')
                            ->from('N_WEB_CAREERS_Lamaran_Tahap')
                            ->whereIn('Lamaran_Id', $lamaranIds);
                    })
                    ->update(['Penjadwalan_Tahap_Id' => null, 'Status' => 'BELUM', 'Updated_At' => $kini, 'Updated_By' => $oleh]);
            }

            // ── 3. PESERTA ─────────────────────────────────────────────────
            $hapusPeserta = DB::table('N_WEB_CAREERS_Penjadwalan_Peserta')
                ->whereIn('Id_Penjadwalan_Peserta', $idPeserta)
                ->delete();

            // ── 4 & 5. TAHAP DAN INDUKNYA — HANYA YANG SUDAH KOSONG ────────
            //
            // Sebuah tahap boleh hilang hanya kalau tidak ada lagi peserta yang
            // memakainya. Menghapusnya lebih cepat dari itu akan menyeret
            // peserta lain di gelombang yang sama.
            $hapusTahap = 0;
            $hapusJadwal = 0;

            if ($tahapIds) {
                $masihDipakai = DB::table('N_WEB_CAREERS_Penjadwalan_Peserta')
                    ->whereIn('Penjadwalan_Tahap_Id', $tahapIds)
                    ->pluck('Penjadwalan_Tahap_Id')->unique()->all();

                $bolehHapus = array_values(array_diff($tahapIds, $masihDipakai));

                if ($bolehHapus) {
                    $hapusTahap = DB::table('N_WEB_CAREERS_Penjadwalan_Tahap')
                        ->whereIn('Id_Penjadwalan_Tahap', $bolehHapus)
                        ->delete();
                }
            }

            foreach ($idJadwal as $pj) {
                $sisaPeserta = DB::table('N_WEB_CAREERS_Penjadwalan_Peserta')->where('Penjadwalan_Id', $pj)->count();
                $sisaTahap = DB::table('N_WEB_CAREERS_Penjadwalan_Tahap')->where('Penjadwalan_Id', $pj)->count();

                if ($sisaPeserta === 0) {
                    // Tahap yatim milik penjadwalan yang sudah tak berpeserta
                    // ikut dibersihkan — kalau tidak, ia jadi baris yang tidak
                    // dimiliki layar mana pun.
                    if ($sisaTahap > 0) {
                        $hapusTahap += DB::table('N_WEB_CAREERS_Penjadwalan_Tahap')->where('Penjadwalan_Id', $pj)->delete();
                    }

                    $hapusJadwal += DB::table('N_WEB_CAREERS_Penjadwalan')->where('Id_Penjadwalan', $pj)->delete();
                } else {
                    // Masih ada isinya: angkanya disegarkan supaya layar daftar
                    // tidak menampilkan jumlah peserta yang sudah basi.
                    DB::table('N_WEB_CAREERS_Penjadwalan')->where('Id_Penjadwalan', $pj)->update([
                        'Jumlah_Peserta' => $sisaPeserta,
                        'Jumlah_Tahap' => $sisaTahap,
                        'Updated_At' => $kini,
                        'Updated_By' => $oleh,
                    ]);
                }
            }

            return [
                'peserta' => $hapusPeserta,
                'tahap' => $hapusTahap,
                'penjadwalan' => $hapusJadwal,
                'lamaranTahapDilepas' => $lepasA,
                'lamaranTahapTesDilepas' => $lepasB,
            ];
        });

        // ── KUERI SIAP TEMPEL UNTUK SISI HCLEARN ────────────────────────────
        //
        // Bukan sekadar kemudahan. Token di HCLearn tidak ikut terhapus, dan
        // sesudah baris di sini hilang, daftar OTP inilah SATU-SATUNYA cara
        // menemukannya lagi.
        $hasil['otp'] = $otp->all();
        $hasil['token'] = $token->all();
        $hasil['idUjianToken'] = $idUjianToken->all();
        $hasil['rincian'] = $rincian;
        $hasil['sql'] = $this->kueriHclearn($otp->all(), $idUjianToken->all());
        $hasil['waktu'] = now()->toDateTimeString();

        return $hasil;
    }

    /**
     * Merangkai kueri pemeriksaan token HCLearn dari OTP yang baru dilepas.
     *
     * Yang dikembalikan sengaja SELECT, bukan DELETE. Yang membacanya sedang
     * memegang daftar OTP milik orang sungguhan; langkah pertamanya harus
     * MELIHAT, bukan menghapus. Kueri hapusnya ikut disertakan sebagai komentar
     * supaya tidak perlu disusun dari ingatan, tapi ia harus dibuka sendiri.
     */
    private function kueriHclearn(array $otp, array $idUjianToken): string
    {
        if (! $otp && ! $idUjianToken) {
            return "-- Tidak ada satu pun OTP/token yang terbit untuk penjadwalan ini.\n"
                .'-- Berarti belum ada undangan yang beredar — tidak ada yang perlu dibersihkan di HCLearn.';
        }

        // Dikutip satu per satu, dengan kutip tunggal digandakan. OTP-nya
        // memang selalu angka, tapi merangkai SQL dengan asumsi begitu adalah
        // kebiasaan yang cepat sekali menular ke tempat yang asumsinya salah.
        $daftarOtp = implode(', ', array_map(
            fn ($o) => "'".str_replace("'", "''", (string) $o)."'",
            $otp
        ));

        $daftarId = implode(', ', array_map('intval', $idUjianToken));

        $sql = "-- OTP yang penjadwalannya BARU SAJA dihapus di Web Careers.\n"
            ."-- Token di HCLearn TIDAK ikut terhapus: beda database, tanpa foreign key.\n"
            ."-- Jalankan di database HCLearn, bukan di web_career_hr.\n\n";

        if ($daftarOtp !== '') {
            $sql .= "select * from HRIS_KANDIDAT_Ujian_Token\n"
                ."Where Akses_OTP IN (".$daftarOtp.");\n";
        }

        if ($daftarId !== '') {
            $sql .= "\n-- Cara kedua, lewat id tokennya — lebih tepat bila ada OTP kembar:\n"
                ."select * from HRIS_KANDIDAT_Ujian_Token\n"
                ."Where Id_Ujian_Token IN (".$daftarId.");\n";
        }

        $sql .= "\n-- Setelah dipastikan benar, barulah:\n"
            ."-- DELETE FROM HRIS_KANDIDAT_Ujian_Token\n"
            ."-- Where Akses_OTP IN (".($daftarOtp !== '' ? $daftarOtp : '...').");";

        return $sql;
    }
}
