<?php

namespace App\Http\Controllers\Career\MasterAkun;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Support\Career\AksesService;
use App\Support\Career\PenerimaSerahTerima;
use App\Support\Career\SerahTerimaPic;
use App\Support\CareerShell;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Vinkla\Hashids\Facades\Hashids;

/**
 * WEB CAREER — MASTER AKUN (Users: pengguna/pelamar + admin/superadmin).
 * SPA + WEB. CRUD Query Builder + ResponseHelper + Log channel. Password di-Hash, id di-Hashids.
 */
class MasterAkunController extends Controller
{
    /** Umur tautan verifikasi. Disamakan dengan AuthController::VERIF_BERLAKU_MENIT. */
    private const VERIF_BERLAKU_MENIT = 30;

    /**
     * Perusahaan yang dilayani modul Web Careers.
     *
     * Tabel Karyawan berkunci komposit (Kode_Perusahaan + Kode_Karyawan), jadi
     * mencarinya tanpa ini bisa memulangkan karyawan perusahaan lain yang
     * kebetulan berkode sama — dan akun admin akan tertaut ke orang yang keliru.
     * Nilainya sama dengan yang dipakai MasterMppController.
     */
    private const KODE_PERUSAHAAN = '001';

    public function index()
    {
        return Inertia::render('Career/admin/master-akun/masterAkun', CareerShell::props('/master-akun', 'Master Akun'));
    }

    public function list()
    {
        try {
            $rows = DB::table('N_WEB_CAREERS_Users as u')
                ->leftJoin('N_WEB_CAREERS_Users as c', 'c.Id_Users', '=', 'u.Created_By_Id')
                // Nama karyawannya ikut dibaca, bukan hanya kodenya. 'A1' tidak
                // memberi tahu siapa pun apa-apa; yang perlu terbaca di layar
                // adalah "Frans Bachtiar (A1)" — dan mengambilnya di sini jauh
                // lebih murah daripada satu permintaan tambahan per baris.
                ->leftJoin('Karyawan as k', function ($j) {
                    $j->on('k.Kode_Karyawan', '=', 'u.Kode_Karyawan')
                        ->where('k.Kode_Perusahaan', '=', self::KODE_PERUSAHAAN);
                })
                ->orderByDesc('u.Id_Users')
                ->select('u.*', 'c.Nama as Pembuat', 'k.Nama as KaryawanNama')
                ->get()
                ->map(fn ($r) => [
                    'id' => Hashids::encode($r->Id_Users),
                    'nama' => $r->Nama,
                    'email' => $r->Email,
                    'phone' => $r->No_Hp,
                    // Jembatan akun -> karyawan. Inilah yang membuat "MPP siapa
                    // yang boleh saya buka" punya jawaban; tanpa terisi, akun ini
                    // tidak bisa dikenali sebagai penanggung jawab MPP mana pun.
                    'kodeKaryawan' => $r->Kode_Karyawan,
                    'karyawanNama' => $r->KaryawanNama,
                    'role' => $r->Role,
                    'klasifikasi' => $r->Klasifikasi,
                    'status' => $r->Status,
                    'mulai_berlaku' => $r->Mulai_Berlaku,
                    'valid_until' => $r->Valid_Until,
                    'last_login_at' => $r->Last_Login_At ?? null,
                    // ── KEADAAN VERIFIKASI EMAIL ────────────────────────────
                    //
                    // `verifSentAt` bukan sekadar hiasan: kolom itu HANYA ditulis
                    // setelah server surat menjawab TERKIRIM (WcSyncEmailJob).
                    // Jadi `verifAttempt > 0` dengan `verifSentAt` kosong berarti
                    // "sudah dicoba sekian kali, tidak satu pun pernah keluar" —
                    // keadaan yang selama ini hanya terbaca di log job, dan tidak
                    // pernah sampai ke orang yang bisa menindaklanjutinya.
                    'emailVerified' => ($r->Flag_Email_Verified ?? 'T') === 'Y',
                    'emailVerifiedAt' => $r->Email_Verified_At ?? null,
                    'verifSentAt' => $r->Email_Verif_Sent_At ?? null,
                    'verifExpiredAt' => $r->Email_Verif_Expired_At ?? null,
                    'verifAttempt' => (int) ($r->Email_Verif_Attempt ?? 0),
                    'createdBy' => $r->Pembuat ?: $r->Created_By,
                    'createdAt' => $r->Created_At,
                ])
                ->values();

            return ResponseHelper::success($rows, 'Data akun dimuat');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('Gagal memuat akun: ' . $e->getMessage());

            return ResponseHelper::error('Gagal memuat data akun', 500);
        }
    }

    /** Hitung Valid_Until dari klasifikasi (Durasi_Hari NULL = permanen). */
    private function hitungValidUntil(string $kode, Carbon $mulai): ?string
    {
        $klas = DB::table('N_WEB_CAREERS_Klasifikasi_Akun')->where('Kode', $kode)->first();
        if ($klas && $klas->Durasi_Hari !== null) {
            return $mulai->copy()->addDays((int) $klas->Durasi_Hari)->toDateString();
        }

        return null;
    }

    /** '' dan '   ' sama-sama berarti KOSONG, dan kosong ditulis NULL. */
    private function kodeKaryawanBersih(?string $kode): ?string
    {
        $kode = trim((string) $kode);

        return $kode === '' ? null : $kode;
    }

    /**
     * GET .../master-akun/{id}/pekerjaan — LOKER YANG SEDANG DIPEGANG AKUN INI.
     *
     * Inilah jawaban atas "orang ini masuk rumah sakit — ia memegang apa saja?".
     * Dibuka dari akunnya, bukan dari salah satu programnya, karena yang jadi
     * titik tolak adalah ORANGNYA.
     */
    public function pekerjaan(string $id)
    {
        $realId = Hashids::decode($id)[0] ?? null;
        if (! $realId) {
            return ResponseHelper::error('Akun tidak valid.', 422);
        }

        $u = DB::table('N_WEB_CAREERS_Users')
            ->where('Id_Users', $realId)
            ->first(['Id_Users', 'Nama', 'Kode_Karyawan']);

        if (! $u) {
            return ResponseHelper::error('Akun tidak ditemukan.', 404);
        }

        // Akun tanpa kode karyawan tidak bisa memegang loker apa pun — bukan
        // kekurangan data yang perlu ditutupi, melainkan jawaban yang benar.
        return ResponseHelper::success([
            'nama' => $u->Nama,
            'kodeKaryawan' => $u->Kode_Karyawan,
            'loker' => $u->Kode_Karyawan ? SerahTerimaPic::pekerjaan($u->Kode_Karyawan) : [],
        ]);
    }

    /**
     * GET .../master-akun/opsi/penerima — CALON PENERIMA SERAH TERIMA.
     *
     * Kuerinya ada di App\Support\Career\PenerimaSerahTerima, dipakai bersama
     * halaman Program Kegiatan lewat rutenya sendiri. Yang tinggal di sini
     * hanya pembacaan parameter dan gerbangnya.
     */
    public function opsiPenerima(Request $request)
    {
        $untukPenerima = $request->query('untuk') === 'penerima';

        $page = in_array($request->query('page'), ['programPage', 'masterAkunPage'], true)
            ? $request->query('page')
            : 'masterAkunPage';

        return ResponseHelper::success(
            PenerimaSerahTerima::daftar(
                $request->query('q'),
                $untukPenerima,
                PenerimaSerahTerima::batas($page, $untukPenerima),
            ),
            'Opsi penerima',
        );
    }

    /**
     * POST .../master-akun/serah-terima — PINDAHKAN LOKER KE ORANG LAIN.
     *
     * Dijaga aksi SERAH_TERIMA, bukan EDIT: menyunting akun dan memindahkan
     * beban kerja orang adalah dua kewenangan yang berbeda, dan tidak semua
     * yang boleh melakukan yang pertama boleh melakukan yang kedua.
     */
    public function serahTerima(Request $request)
    {
        try {
            $data = $request->validate([
                'posisiIds' => 'required|array|min:1|max:200',
                'posisiIds.*' => 'required|integer',
                'keKode' => 'required|string|max:20',
                'alasan' => 'required|string|min:5|max:500',
            ], [
                'posisiIds.required' => 'Pilih dulu loker yang akan diserahkan.',
                'keKode.required' => 'Pilih penerima serah terima.',
                'alasan.required' => 'Alasan serah terima wajib diisi.',
                'alasan.min' => 'Alasan terlalu pendek — tuliskan sebabnya (mis. cuti sakit s/d 5 Sept).',
            ]);

            // Penerima harus punya AKUN AKTIF di sini — bukan sekadar kode yang
            // benar. Loker yang diserahkan ke kode tanpa akun tidak pernah bisa
            // dikerjakan siapa pun, dan kandidatnya menggantung tanpa ada yang
            // merasa memegangnya.
            $punyaAkun = DB::table('N_WEB_CAREERS_Users')
                ->where('Kode_Karyawan', $data['keKode'])
                ->whereIn('Role', ['ADMIN', 'SUPERADMIN'])
                ->where('Status', 'AKTIF')
                ->exists();

            if (! $punyaAkun) {
                return ResponseHelper::error(
                    "Tidak ada akun internal aktif dengan kode karyawan \"{$data['keKode']}\". "
                    .'Penerima serah terima harus punya akun yang bisa masuk.',
                    422
                );
            }

            $hasil = SerahTerimaPic::jalankan(
                posisiIds: $data['posisiIds'],
                keKode: $data['keKode'],
                alasan: $data['alasan'],
                admin: [
                    'id' => session('career_auth.id'),
                    'nama' => session('career_auth.nama', 'ADMIN'),
                ],
                sumber: SerahTerimaPic::SUMBER_ADMIN,
            );

            if (! $hasil['jml']) {
                return ResponseHelper::error(
                    $hasil['dilewati']
                        ? 'Seluruh loker yang dipilih sudah dipegang orang itu.'
                        : 'Tidak ada loker yang berpindah.',
                    422
                );
            }

            return ResponseHelper::success($hasil, sprintf(
                '%d loker (%d kandidat) berpindah.%s',
                $hasil['jml'], $hasil['kandidat'],
                $hasil['dilewati'] ? " {$hasil['dilewati']} dilewati karena sudah dipegang orang itu." : ''
            ));
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ResponseHelper::error(collect($e->errors())->flatten()->first() ?? 'Data tidak valid', 422);
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('Gagal serah terima: '.$e->getMessage());

            return ResponseHelper::error('Gagal melakukan serah terima', 500);
        }
    }

    private function rules(bool $create = true): array
    {
        return [
            'nama' => 'required|string|max:150',
            'email' => 'required|email|max:150',
            'phone' => 'nullable|string|max:30',
            'password' => ($create ? 'required' : 'nullable') . '|string|min:6',
            'role' => 'required|in:KANDIDAT,ADMIN,SUPERADMIN',
            'klasifikasi' => 'required|string|max:40',
            'status' => 'required|in:AKTIF,NONAKTIF',
            // Boleh kosong, dan itu bukan kelonggaran: 395 dari 400 akun adalah
            // KANDIDAT, yang memang bukan karyawan. Yang wajib justru sebaliknya —
            // bila diisi, kodenya harus benar-benar ada dan belum dipakai akun
            // lain; keduanya diperiksa galatKodeKaryawan().
            'kodeKaryawan' => 'nullable|string|max:20',
        ];
    }

    /**
     * Kode karyawan yang dikirim sah? — balasan galat, atau null.
     *
     * ── KEBERADAANNYA TIDAK LAGI DITUNTUT ───────────────────────────────────
     *
     * Dulu kode diperiksa harus ada di tabel Karyawan. Pemeriksaan itu dicabut:
     * sumber data kepegawaian yang dipakai berbeda dari tabel tersebut, jadi
     * menuntut kecocokan berarti menolak kode yang justru benar — dan admin
     * tidak punya cara membuktikan sistemnya yang keliru.
     *
     * Yang TETAP dijaga cuma satu, dan itu yang benar-benar berbahaya:
     *
     *   BELUM DIPAKAI AKUN LAIN. Dua akun yang mengaku karyawan yang sama
     *   membuat "loker ini milik siapa" punya dua jawaban. Indeks unik di
     *   database menahannya juga, tapi sebagai galat SQL yang tidak bisa dibaca
     *   admin; di sini ia jadi kalimat yang menyebut akun mana yang memakainya.
     *
     * Salah ketik karena itu TIDAK tertangkap di sini — akibatnya pemiliknya
     * melihat daftar loker kosong. Itu keadaan yang terlihat dan bisa
     * diperbaiki, jauh lebih ringan daripada menolak kode yang sah.
     */
    private function galatKodeKaryawan(?string $kode, ?int $kecualiId = null)
    {
        $kode = trim((string) $kode);
        if ($kode === '') {
            return null;
        }

        $dipakai = DB::table('N_WEB_CAREERS_Users')
            ->where('Kode_Karyawan', $kode)
            ->when($kecualiId, fn ($w) => $w->where('Id_Users', '!=', $kecualiId))
            ->value('Nama');

        if ($dipakai) {
            return ResponseHelper::error("Kode karyawan \"{$kode}\" sudah dipakai akun {$dipakai}.", 422);
        }

        return null;
    }

    public function store(Request $request)
    {
        try {
            $data = $request->validate($this->rules(true));
            if (DB::table('N_WEB_CAREERS_Users')->where('Email', $data['email'])->exists()) {
                return ResponseHelper::error('Email sudah digunakan akun lain.', 422);
            }
            if ($galat = $this->galatKodeKaryawan($data['kodeKaryawan'] ?? null)) {
                return $galat;
            }
            $now = now();
            $userId = session('career_auth.id');
            $userName = session('career_auth.nama', 'ADMIN');

            DB::table('N_WEB_CAREERS_Users')->insert([
                'Nama' => $data['nama'],
                'Email' => $data['email'],
                'No_Hp' => $data['phone'] ?? null,
                'Password' => Hash::make($data['password']),
                'Role' => $data['role'],
                'Klasifikasi' => $data['klasifikasi'],
                'Status' => $data['status'],
                // Kosong ditulis NULL, bukan string kosong: indeks uniknya
                // tersaring pada IS NOT NULL, dan '' adalah nilai — akun kedua
                // yang kosong akan ditolak database.
                'Kode_Karyawan' => $this->kodeKaryawanBersih($data['kodeKaryawan'] ?? null),
                'Mulai_Berlaku' => $now->toDateString(),
                'Valid_Until' => $this->hitungValidUntil($data['klasifikasi'], $now),
                'Created_At' => $now, 'Created_By' => $userName, 'Created_By_Id' => $userId,
                'Updated_At' => $now, 'Updated_By' => $userName, 'Updated_By_Id' => $userId,
            ]);

            Log::channel('web_career')->info("Akun dibuat ({$data['role']}) {$data['email']} oleh {$userName}");

            return ResponseHelper::success(null, 'Akun berhasil dibuat', 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ResponseHelper::error(collect($e->errors())->flatten()->first() ?? 'Data tidak valid', 422);
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('Gagal buat akun: ' . $e->getMessage());

            return ResponseHelper::error('Gagal menyimpan akun', 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $realId = Hashids::decode($id)[0] ?? null;
            $row = DB::table('N_WEB_CAREERS_Users')->where('Id_Users', $realId)->first();
            if (! $row) {
                return ResponseHelper::error('Akun tidak ditemukan.', 404);
            }
            $data = $request->validate($this->rules(false));
            if (DB::table('N_WEB_CAREERS_Users')->where('Email', $data['email'])->where('Id_Users', '!=', $realId)->exists()) {
                return ResponseHelper::error('Email sudah digunakan akun lain.', 422);
            }
            if ($galat = $this->galatKodeKaryawan($data['kodeKaryawan'] ?? null, (int) $realId)) {
                return $galat;
            }
            $now = now();
            $mulai = $row->Mulai_Berlaku ? Carbon::parse($row->Mulai_Berlaku) : $now;
            $update = [
                'Nama' => $data['nama'],
                'Email' => $data['email'],
                'No_Hp' => $data['phone'] ?? null,
                'Role' => $data['role'],
                'Klasifikasi' => $data['klasifikasi'],
                'Status' => $data['status'],
                'Kode_Karyawan' => $this->kodeKaryawanBersih($data['kodeKaryawan'] ?? null),
                'Valid_Until' => $this->hitungValidUntil($data['klasifikasi'], $mulai),
                'Updated_At' => $now, 'Updated_By' => session('career_auth.nama', 'ADMIN'), 'Updated_By_Id' => session('career_auth.id'),
            ];
            if (! empty($data['password'])) {
                $update['Password'] = Hash::make($data['password']);
            }
            DB::table('N_WEB_CAREERS_Users')->where('Id_Users', $realId)->update($update);
            Log::channel('web_career')->info("Akun diperbarui #{$realId} ({$data['email']})");

            return ResponseHelper::success(null, 'Akun diperbarui');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ResponseHelper::error(collect($e->errors())->flatten()->first() ?? 'Data tidak valid', 422);
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal update akun #{$id}: " . $e->getMessage());

            return ResponseHelper::error('Gagal memperbarui akun', 500);
        }
    }

    public function toggle(Request $request, $id)
    {
        try {
            $realId = Hashids::decode($id)[0] ?? null;
            $aktif = $request->boolean('aktif');
            $terpengaruh = DB::table('N_WEB_CAREERS_Users')->where('Id_Users', $realId)->update([
                'Status' => $aktif ? 'AKTIF' : 'NONAKTIF',
                'Updated_At' => now(), 'Updated_By' => session('career_auth.nama', 'ADMIN'), 'Updated_By_Id' => session('career_auth.id'),
            ]);
            if (! $terpengaruh) {
                return ResponseHelper::error('Akun tidak ditemukan.', 404);
            }
            Log::channel('web_career')->info("Akun #{$realId} status " . ($aktif ? 'AKTIF' : 'NONAKTIF'));

            return ResponseHelper::success(null, 'Status akun diperbarui');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal toggle akun #{$id}: " . $e->getMessage());

            return ResponseHelper::error('Gagal mengubah status', 500);
        }
    }

    /**
     * PATCH master-akun/{id}/kirim-verifikasi — kirim ULANG tautan verifikasi.
     *
     * ══ KENAPA DIKIRIM LANGSUNG, BUKAN LEWAT ANTREAN ══
     *
     * Registrasi memakai antrean, dan itu benar: kandidat tidak boleh menunggu
     * SMTP yang lambat, dan kegagalannya tidak boleh menggagalkan pendaftaran.
     * Tapi konsekuensinya, alasan gagalnya hanya mendarat di log job — dan
     * akun seperti yang memicu pintu ini justru mencatat `Email_Verif_Attempt`
     * sampai enam kali dengan `Email_Verif_Sent_At` yang tak pernah terisi:
     * enam kali dicoba, tidak sekali pun keluar, tanpa sepatah kata pun sampai
     * ke orang yang bisa membetulkannya.
     *
     * Di sini keadaannya terbalik. Yang menekan tombol adalah admin yang SEDANG
     * menyelidiki kegagalan itu; ia sanggup menunggu dua detik, dan yang paling
     * ia butuhkan justru kalimat galat aslinya — "Connection could not be
     * established", "535 Authentication failed" — bukan "sedang diproses".
     * Karena itu `dispatchSync`: job yang sama, logika yang sama, tapi
     * lemparannya sampai ke layar alih-alih tenggelam di log.
     *
     * TANPA THROTTLE, berbeda dari kirim-ulang publik yang menahan 2 menit.
     * Penahanan itu mencegah penyalahgunaan oleh orang asing; di sini ia justru
     * menghalangi satu-satunya orang yang sedang berusaha memperbaiki keadaan.
     */
    public function kirimVerifikasi($id)
    {
        $realId = Hashids::decode($id)[0] ?? null;
        $row = DB::table('N_WEB_CAREERS_Users')->where('Id_Users', $realId)->first();

        if (! $row) {
            return ResponseHelper::error('Akun tidak ditemukan.', 404);
        }

        if (($row->Flag_Email_Verified ?? 'T') === 'Y') {
            return ResponseHelper::error('Email akun ini sudah terverifikasi.', 422);
        }

        $adminNama = session('career_auth.nama', 'ADMIN');
        $adminId = session('career_auth.id');

        // Token BARU tiap kali dikirim. Yang lama ikut hangus begitu barisnya
        // ditimpa — tautan basi di kotak masuk tidak boleh tetap berlaku.
        $token = Str::random(64);
        $now = Carbon::now();

        try {
            DB::table('N_WEB_CAREERS_Users')->where('Id_Users', $realId)->update([
                'Email_Verif_Token' => hash('sha256', $token),
                'Email_Verif_Expired_At' => $now->copy()->addMinutes(self::VERIF_BERLAKU_MENIT),
                'Email_Verif_Attempt' => DB::raw('ISNULL(Email_Verif_Attempt, 0) + 1'),
                'Updated_At' => $now, 'Updated_By' => $adminNama, 'Updated_By_Id' => $adminId,
            ]);

            // Job yang sama dengan jalur registrasi — termasuk penulisan
            // Email_Verif_Sent_At setelah SMTP benar-benar menerima.
            \App\Jobs\Career\WcSyncEmailJob::dispatchSync(
                \App\Jobs\Career\WcSyncEmailJob::JENIS_VERIFIKASI,
                (int) $realId,
                ['token' => $token],
            );
        } catch (\Throwable $e) {
            Log::channel('web_career')->error(
                "[VERIF-ULANG] gagal ke {$row->Email} (akun #{$realId}, oleh {$adminNama}): " . $e->getMessage()
            );

            // Kalimat galat ASLI diteruskan apa adanya. Menggantinya dengan
            // "terjadi kesalahan" menghapus satu-satunya petunjuk yang dipunyai
            // admin, dan ia tidak punya akses ke log server.
            return ResponseHelper::error('Email gagal dikirim: ' . $e->getMessage(), 502);
        }

        // Dibaca ULANG, bukan diasumsikan: job sengaja berhenti diam-diam pada
        // beberapa keadaan (akun keburu terverifikasi, token kosong). Bila
        // kolomnya tidak bergerak, emailnya memang tidak keluar — dan itu harus
        // dikatakan, bukan dirayakan sebagai keberhasilan.
        $sesudah = DB::table('N_WEB_CAREERS_Users')->where('Id_Users', $realId)->value('Email_Verif_Sent_At');

        if (! $sesudah || Carbon::parse($sesudah)->lt($now)) {
            Log::channel('web_career')->warning("[VERIF-ULANG] akun #{$realId} ({$row->Email}) — job selesai tanpa mengirim.");

            return ResponseHelper::error(
                'Pengiriman tidak jadi dijalankan. Periksa log server: kemungkinan akun sudah terverifikasi di sela permintaan, atau konfigurasi email belum lengkap.',
                502
            );
        }

        Log::channel('web_career')->info("[VERIF-ULANG] terkirim ke {$row->Email} (akun #{$realId}, oleh {$adminNama}).");

        return ResponseHelper::success(
            ['verifSentAt' => $sesudah, 'berlakuMenit' => self::VERIF_BERLAKU_MENIT],
            "Tautan verifikasi terkirim ke {$row->Email}. Berlaku " . self::VERIF_BERLAKU_MENIT . ' menit.'
        );
    }

    public function destroy($id)
    {
        try {
            $realId = Hashids::decode($id)[0] ?? null;
            $row = DB::table('N_WEB_CAREERS_Users')->where('Id_Users', $realId)->first();
            if (! $row) {
                return ResponseHelper::error('Akun tidak ditemukan.', 404);
            }
            if ($row->Role === 'SUPERADMIN' && DB::table('N_WEB_CAREERS_Users')->where('Role', 'SUPERADMIN')->count() <= 1) {
                return ResponseHelper::error('Tidak dapat menghapus Superadmin terakhir.', 422);
            }
            DB::table('N_WEB_CAREERS_Users')->where('Id_Users', $realId)->delete();
            Log::channel('web_career')->info("Akun dihapus #{$realId} ({$row->Email})");

            return ResponseHelper::success(null, 'Akun dihapus');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal hapus akun #{$id}: " . $e->getMessage());

            return ResponseHelper::error('Gagal menghapus akun', 500);
        }
    }
}
