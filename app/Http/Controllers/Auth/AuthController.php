<?php

namespace App\Http\Controllers\Auth;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Support\Career\AksesService;
use App\Support\Sinkron\Outbox;
use App\Support\Sinkron\RahasiaSinkron;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;

/**
 * WEB CAREER — AUTH kandidat (tabel milik N_WEB_CAREERS_Users).
 * Controller WEB, dipanggil via axios ke route web ber-prefix api/v1 → balas JSON.
 *
 * DUA ZONA. Akun hidup di database publik; zona dalam menerima salinannya
 * (tanpa kata sandi) lewat peristiwa Outbox. SUREL TIDAK DIKIRIM DARI SINI:
 * tautan verifikasi dan kode reset dicatat sebagai `Akun.KodeDiminta` —
 * kodenya dibungkus RahasiaSinkron — lalu zona dalam yang mengirim surelnya.
 * Yang disimpan di tabel akun selalu HASH-nya saja.
 */
class AuthController extends Controller
{
    private string $table = 'N_WEB_CAREERS_Users';

    private string $klasTable = 'N_WEB_CAREERS_Klasifikasi_Akun';

    private string $auditTable = 'N_WEB_CAREERS_Reset_Audit';

    /** Masa berlaku tautan verifikasi email (menit) — sesuai desain: 30 menit, sekali pakai. */
    private const VERIF_BERLAKU_MENIT = 30;

    /** Jeda minimal kirim-ulang email verifikasi (menit) — anti spam. */
    private const VERIF_THROTTLE_MENIT = 2;

    /** Masa berlaku OTP reset kata sandi (menit) — sekali pakai. */
    private const RESET_OTP_BERLAKU_MENIT = 10;

    /** Jeda minimal antar-permintaan OTP reset (menit) — anti spam. */
    private const RESET_OTP_THROTTLE_MENIT = 2;

    /** Batas percobaan OTP salah — pada percobaan ke-N OTP dihanguskan. */
    private const RESET_OTP_MAX_ATTEMPT = 3;

    /** Batas jumlah permintaan OTP dalam satu window. */
    private const RESET_OTP_MAX_KIRIM = 5;

    /** Panjang window rate-limit permintaan OTP (menit). */
    private const RESET_OTP_WINDOW_MENIT = 60;

    /**
     * Bypass verifikasi hanya boleh hidup di mesin development/test.
     * Guard environment sengaja tetap ada walaupun flag .env salah disetel
     * agar akun production tidak pernah terverifikasi otomatis.
     */
    private function autoVerifikasiEmailAktif(): bool
    {
        return app()->environment(['local', 'testing']) && (bool) config('career_auth.auto_verify_email', false);
    }

    /** Nilai kolom verifikasi untuk akun yang di-auto-verify saat development. */
    private function dataAutoVerifikasiEmail(Carbon $now): array
    {
        return [
            'Flag_Email_Verified' => 'Y',
            'Email_Verified_At' => $now,
            'Email_Verif_Token' => null,
            'Email_Verif_Expired_At' => null,
        ];
    }

    /** Profil dasar akun untuk zona dalam — tanpa kata sandi, hash, maupun kode apa pun. */
    private function profilAkun(int $userId): array
    {
        $r = DB::table($this->table)->where('Id_Users', $userId)->first();

        return [
            'id_publik' => (int) $r->Id_Users,
            'nama' => $r->Nama,
            'email' => $r->Email,
            'no_hp' => $r->No_Hp,
            'nik' => $r->NIK,
            'role' => $r->Role,
            'klasifikasi' => $r->Klasifikasi,
            'status' => $r->Status,
            'mulai_berlaku' => $r->Mulai_Berlaku ? (string) $r->Mulai_Berlaku : null,
            'valid_until' => $r->Valid_Until ? (string) $r->Valid_Until : null,
            'email_terverifikasi' => ($r->Flag_Email_Verified ?? 'T') === 'Y',
        ];
    }

    /**
     * Siapkan tautan verifikasi: HASH token disimpan di akun, tokennya sendiri
     * ikut peristiwa `Akun.KodeDiminta` (terbungkus) supaya zona dalam bisa
     * mengirim surelnya. Wajib dipanggil di dalam transaksi akunnya.
     */
    private function mintaSurelVerifikasi(int $userId): void
    {
        $token = Str::random(64);
        $now = Carbon::now();

        DB::table($this->table)
            ->where('Id_Users', $userId)
            ->update([
                'Flag_Email_Verified' => 'T',
                'Email_Verified_At' => null,
                'Email_Verif_Token' => hash('sha256', $token),
                'Email_Verif_Expired_At' => $now->copy()->addMinutes(self::VERIF_BERLAKU_MENIT),
                'Email_Verif_Sent_At' => $now,
                'Email_Verif_Attempt' => DB::raw('ISNULL(Email_Verif_Attempt, 0) + 1'),
                'Updated_At' => $now,
            ]);

        $akun = DB::table($this->table)->where('Id_Users', $userId)->first(['Email', 'Nama']);
        Outbox::tulis(Outbox::AKUN_KODE_DIMINTA, 'Akun.KodeDiminta:'.Str::uuid(), $userId, [
            'id_publik' => $userId,
            'email' => $akun->Email,
            'nama' => $akun->Nama,
            'jenis' => 'VERIFIKASI',
            'berlaku_menit' => self::VERIF_BERLAKU_MENIT,
            'rahasia' => RahasiaSinkron::bungkus(['token' => $token]),
        ], $akun->Email);
    }

    /**
     * LANGKAH 1 REGISTER — cek ketersediaan KTP sebelum form identitas dibuka.
     * KTP wajib 16 digit; bila sudah dipakai akun lain → tolak + email ter-mask.
     */
    public function cekKtp(Request $request)
    {
        $data = $request->validate(
            [
                'nik' => 'required|digits:16',
            ],
            [
                'nik.required' => 'Nomor KTP (NIK) wajib diisi.',
                'nik.digits' => 'Nomor KTP harus tepat 16 digit angka.',
            ],
        );

        $pemilik = DB::table($this->table)->where('NIK', $data['nik'])->first();
        if ($pemilik) {
            return ResponseHelper::error(
                'Nomor KTP ini sudah terdaftar pada akun dengan email '.
                    $this->maskEmail($pemilik->Email).
                    '. Satu KTP hanya untuk satu akun — silakan masuk memakai akun tersebut.',
                422,
            );
        }

        return ResponseHelper::success(['tersedia' => true], 'KTP tersedia — silakan lengkapi data diri.');
    }

    public function register(Request $request)
    {
        $data = $request->validate(
            [
                'nama' => 'required|string|max:150',
                'email' => 'required|email|max:150',
                // NOMOR HP: ANGKA SAJA, 8–15 digit termasuk kode negara (batas E.164).
                // Ditegakkan DI SERVER, bukan cukup di layar: pintu ini bisa
                // diketuk langsung. Awalan '62' SENGAJA tidak dituntut — negaranya
                // dipilih kandidat, dan memaksa +62 menutup pintu pelamar luar negeri.
                'phone' => ['nullable', 'string', 'regex:/^[0-9]{8,15}$/'],
                // KTP/NIK: WAJIB tepat 16 digit (tidak boleh kurang / lebih).
                'nik' => 'required|digits:16',
                'password' => 'required|string|min:6',
            ],
            [
                'phone.regex' => 'No. HP hanya boleh angka (8–15 digit termasuk kode negara).',
                'nik.required' => 'Nomor KTP (NIK) wajib diisi.',
                'nik.digits' => 'Nomor KTP harus tepat 16 digit angka.',
            ],
        );

        $now = Carbon::now();
        $autoVerifikasi = $this->autoVerifikasiEmailAktif();

        // ── CEK KTP (ulang, defense-in-depth): satu NIK hanya SATU akun kandidat.
        $pemilikNik = DB::table($this->table)
            ->where('NIK', $data['nik'])
            ->where('Email', '!=', $data['email'])
            ->first();
        if ($pemilikNik) {
            return ResponseHelper::error(
                'Nomor KTP ini sudah terdaftar pada akun dengan email '.
                    $this->maskEmail($pemilikNik->Email).
                    '. Satu KTP hanya untuk satu akun — silakan masuk memakai akun tersebut.',
                422,
            );
        }

        // Masa berlaku registrasi diambil dari tabel config (TIDAK hardcode).
        $klas = DB::table($this->klasTable)->where('Is_Default_Register', 'Y')->where('Flag_Aktif', 'Y')->first();
        $kode = $klas->Kode ?? 'PERMANEN';
        $validUntil =
            $klas && $klas->Durasi_Hari !== null
                ? $now->copy()->addDays((int) $klas->Durasi_Hari)->toDateString()
                : null;

        $existing = DB::table($this->table)->where('Email', $data['email'])->first();

        if ($existing) {
            $terverifikasi = ($existing->Flag_Email_Verified ?? 'T') === 'Y';
            $masihBerlaku =
                $existing->Status === 'AKTIF' &&
                ($existing->Valid_Until === null ||
                    Carbon::parse($existing->Valid_Until)
                        ->startOfDay()
                        ->greaterThanOrEqualTo($now->copy()->startOfDay()));

            // Ditolak HANYA bila akun terverifikasi DAN masih dalam masa berlaku.
            if ($terverifikasi && $masihBerlaku) {
                $sampai = $existing->Valid_Until
                    ? ' hingga '.Carbon::parse($existing->Valid_Until)->format('d M Y')
                    : '';

                return ResponseHelper::error(
                    "Email sudah terdaftar dan masih aktif{$sampai}. Silakan langsung masuk.",
                    422,
                );
            }

            // Dua kasus yang BOLEH daftar ulang (akun di-klaim ulang):
            //  1. Belum pernah verifikasi email — kepemilikan email belum
            //     terbukti, jadi pendaftar sekarang berhak mengambil alih.
            //  2. Sudah terverifikasi tapi masa berlaku klasifikasinya habis
            //     (mis. trial 6 bulan lewat) / akun nonaktif.
            $perubahan = [
                'Nama' => $data['nama'],
                'No_Hp' => $data['phone'] ?? $existing->No_Hp,
                'NIK' => $data['nik'],
                'Password' => Hash::make($data['password']),
                'Klasifikasi' => $kode,
                'Status' => 'AKTIF',
                'Mulai_Berlaku' => $now->toDateString(),
                'Valid_Until' => $validUntil,
                'Updated_At' => $now,
                'Updated_By' => $data['email'],
            ];

            if ($autoVerifikasi) {
                $perubahan = array_merge($perubahan, $this->dataAutoVerifikasiEmail($now));
            }

            $userId = (int) $existing->Id_Users;
            DB::transaction(function () use ($userId, $perubahan, $autoVerifikasi, $data) {
                DB::table($this->table)->where('Id_Users', $userId)->update($perubahan);

                Outbox::tulis(Outbox::AKUN_DIPERBARUI, 'Akun.Diperbarui:'.Str::uuid(), $userId, [
                    'akun' => $this->profilAkun($userId),
                    'perubahan' => ['daftar_ulang'],
                ], $data['email']);

                if (! $autoVerifikasi) {
                    $this->mintaSurelVerifikasi($userId);
                }
            });

            if ($autoVerifikasi) {
                Log::info("[AUTH] auto-verifikasi development untuk user #{$userId} ({$data['email']}).");

                return ResponseHelper::success(
                    ['email' => $data['email'], 'perlu_verifikasi' => false],
                    'Pendaftaran berhasil. Email otomatis terverifikasi dalam mode development; silakan masuk.',
                );
            }

            $pesan = $terverifikasi
                ? 'Pendaftaran ulang berhasil (akun sebelumnya telah kedaluwarsa). Cek email kamu untuk verifikasi.'
                : 'Email ini pernah didaftarkan namun belum diverifikasi. Data kamu diperbarui — cek email untuk tautan verifikasi yang baru.';

            // TIDAK auto-login: sesi baru dibuat setelah email terverifikasi + login.
            return ResponseHelper::success(['email' => $data['email'], 'perlu_verifikasi' => true], $pesan);
        }

        $akunBaru = [
            'Nama' => $data['nama'],
            'Email' => $data['email'],
            'No_Hp' => $data['phone'] ?? null,
            'NIK' => $data['nik'],
            'Password' => Hash::make($data['password']),
            'Role' => 'KANDIDAT',
            'Klasifikasi' => $kode,
            'Status' => 'AKTIF',
            'Mulai_Berlaku' => $now->toDateString(),
            'Valid_Until' => $validUntil,
            'Created_At' => $now,
            'Created_By' => $data['email'],
            'Updated_At' => $now,
            'Updated_By' => $data['email'],
        ];

        if ($autoVerifikasi) {
            $akunBaru = array_merge($akunBaru, $this->dataAutoVerifikasiEmail($now));
        }

        // Akun + peristiwanya SATU transaksi. Zona dalam membuat salinan akun
        // (tanpa sandi) dan mendaftarkan calon ke HCLearn dari Akun.Terdaftar —
        // aplikasi ini tidak menghubungi layanan dalam mana pun.
        $id = DB::transaction(function () use ($akunBaru, $autoVerifikasi, $data) {
            $id = (int) DB::table($this->table)->insertGetId($akunBaru, 'Id_Users');

            Outbox::tulis(Outbox::AKUN_TERDAFTAR, 'Akun.Terdaftar:'.$id, $id, [
                'akun' => $this->profilAkun($id),
            ], $data['email']);

            if (! $autoVerifikasi) {
                $this->mintaSurelVerifikasi($id);
            }

            return $id;
        });

        if ($autoVerifikasi) {
            Log::info("[AUTH] auto-verifikasi development untuk user #{$id} ({$data['email']}).");

            return ResponseHelper::success(
                ['email' => $data['email'], 'perlu_verifikasi' => false],
                'Registrasi berhasil. Email otomatis terverifikasi dalam mode development; silakan masuk.',
                201,
            );
        }

        // TIDAK auto-login: kandidat wajib verifikasi email dulu baru bisa masuk.
        return ResponseHelper::success(
            ['email' => $data['email'], 'perlu_verifikasi' => true],
            'Registrasi berhasil! Kami telah mengirim tautan verifikasi ke email kamu (berlaku '.
                self::VERIF_BERLAKU_MENIT.
                ' menit). Setelah verifikasi, silakan masuk.',
            201,
        );
    }

    /** Samarkan email untuk pesan publik: nama@contoh.com → n**a@contoh.com */
    private function maskEmail(?string $email): string
    {
        $email = (string) $email;
        $at = strpos($email, '@');
        if ($at === false) {
            return '***';
        }
        $lokal = substr($email, 0, $at);
        $domain = substr($email, $at);
        $len = strlen($lokal);
        if ($len <= 2) {
            return $lokal[0].'***'.$domain;
        }

        return $lokal[0].str_repeat('*', max(3, $len - 2)).$lokal[$len - 1].$domain;
    }

    /**
     * Magic link dari email: GET /verifikasi-email?email=...&token=...
     * Merender halaman hasil (Career/VerifikasiEmail) dengan status:
     *  - sukses      : baru saja terverifikasi (countdown ke /login)
     *  - sudah       : sudah pernah terverifikasi (idempoten — link diklik 2x)
     *  - kadaluarsa  : token benar tapi lewat masa berlaku → tombol kirim ulang
     *  - invalid     : token salah/kosong → form tempel tautan/token manual
     */
    public function verifikasiEmail(Request $request)
    {
        $hasil = $this->terapkanVerifikasiToken(
            trim((string) $request->query('email', '')),
            trim((string) $request->query('token', '')),
        );

        return Inertia::render('Career/VerifikasiEmail', [
            'status' => $hasil['status'],
            'email' => $hasil['email'],
        ]);
    }

    /**
     * Verifikasi via tempel token (POST api/v1/verifikasi-token). Dipakai halaman
     * "menunggu verifikasi" agar kandidat bisa menempel magic link jika tautan di
     * email bermasalah — hasilnya JSON, verifikasi tetap di TAB yang sama.
     */
    public function verifikasiToken(Request $request)
    {
        $data = $request->validate([
            'token' => 'required|string',
            'email' => 'nullable|email',
        ]);

        $hasil = $this->terapkanVerifikasiToken(trim((string) ($data['email'] ?? '')), trim($data['token']));

        return match ($hasil['status']) {
            'sukses' => ResponseHelper::success(
                ['status' => 'sukses', 'email' => $hasil['email']],
                'Email berhasil diverifikasi.',
            ),
            'sudah' => ResponseHelper::success(
                ['status' => 'sudah', 'email' => $hasil['email']],
                'Email kamu memang sudah terverifikasi.',
            ),
            'kadaluarsa' => response()->json(
                [
                    'success' => false,
                    'status' => 410,
                    'code' => 'KADALUARSA',
                    'message' => 'Tautan sudah kedaluwarsa. Silakan kirim ulang email verifikasi.',
                    'result' => ['email' => $hasil['email']],
                ],
                410,
            ),
            default => ResponseHelper::error(
                'Tautan / token tidak dikenali. Salin utuh tautan dari email terbaru kamu.',
                422,
            ),
        };
    }

    /** Cek status verifikasi (GET api/v1/status-verifikasi?email=) untuk polling halaman tunggu. */
    public function statusVerifikasi(Request $request)
    {
        $data = $request->validate(['email' => 'required|email']);

        $row = DB::table($this->table)->where('Email', $data['email'])->first();
        if (! $row) {
            return ResponseHelper::error('Email tidak terdaftar.', 404);
        }

        return ResponseHelper::success(['verified' => ($row->Flag_Email_Verified ?? 'T') === 'Y']);
    }

    /**
     * Inti verifikasi token, dipakai bersama magic-link (GET) & tempel-token (POST).
     * Mengembalikan ['status' => sukses|sudah|kadaluarsa|invalid, 'email' => ?string].
     * Token dibandingkan sebagai HASH SHA-256 (constant-time) dan SEKALI PAKAI.
     */
    private function terapkanVerifikasiToken(string $email, string $token): array
    {
        if ($token === '') {
            return ['status' => 'invalid', 'email' => null];
        }

        $row = null;
        if ($email !== '') {
            $row = DB::table($this->table)->where('Email', $email)->first();
        }
        if (! $row) {
            $row = DB::table($this->table)->where('Email_Verif_Token', hash('sha256', $token))->first();
        }

        if (! $row) {
            return ['status' => 'invalid', 'email' => null];
        }

        // Sudah terverifikasi (mis. tautan diklik dua kali) → idempoten.
        if (($row->Flag_Email_Verified ?? 'T') === 'Y') {
            return ['status' => 'sudah', 'email' => $row->Email];
        }

        if (! $row->Email_Verif_Token || ! hash_equals($row->Email_Verif_Token, hash('sha256', $token))) {
            return ['status' => 'invalid', 'email' => null];
        }

        if ($row->Email_Verif_Expired_At && Carbon::parse($row->Email_Verif_Expired_At)->isPast()) {
            return ['status' => 'kadaluarsa', 'email' => $row->Email];
        }

        // Sukses → tandai verified + hapus token (sekali pakai), kabarkan ke dalam.
        $userId = (int) $row->Id_Users;
        DB::transaction(function () use ($userId, $row) {
            DB::table($this->table)
                ->where('Id_Users', $userId)
                ->update([
                    'Flag_Email_Verified' => 'Y',
                    'Email_Verified_At' => Carbon::now(),
                    'Email_Verif_Token' => null,
                    'Email_Verif_Expired_At' => null,
                    'Updated_At' => Carbon::now(),
                ]);

            Outbox::tulis(Outbox::AKUN_DIPERBARUI, 'Akun.Diperbarui:'.Str::uuid(), $userId, [
                'akun' => $this->profilAkun($userId),
                'perubahan' => ['email_terverifikasi'],
            ], $row->Email);
        });

        Log::info("[AUTH] user #{$userId} terverifikasi.");

        return ['status' => 'sukses', 'email' => $row->Email];
    }

    /** Kirim ulang email verifikasi (POST api/v1/kirim-verifikasi, body: email). */
    public function kirimUlangVerifikasi(Request $request)
    {
        $data = $request->validate(['email' => 'required|email']);

        $row = DB::table($this->table)->where('Email', $data['email'])->first();
        if (! $row) {
            return ResponseHelper::error('Email tidak terdaftar.', 404);
        }

        if (($row->Flag_Email_Verified ?? 'T') === 'Y') {
            return ResponseHelper::error('Email sudah terverifikasi. Silakan langsung masuk.', 422);
        }

        if (
            $row->Email_Verif_Sent_At &&
            Carbon::parse($row->Email_Verif_Sent_At)->diffInMinutes(Carbon::now()) < self::VERIF_THROTTLE_MENIT
        ) {
            return ResponseHelper::error(
                'Email verifikasi baru saja dikirim. Mohon tunggu beberapa menit lalu cek kotak masuk/spam.',
                429,
            );
        }

        DB::transaction(fn () => $this->mintaSurelVerifikasi((int) $row->Id_Users));

        return ResponseHelper::success(
            null,
            'Email verifikasi telah dikirim ulang. Silakan cek kotak masuk atau folder spam.',
        );
    }

    /** Verifikasi token Cloudflare Turnstile ke server siteverify. Return true bila valid. */
    private function verifyTurnstile(string $secret, string $token, ?string $ip): bool
    {
        try {
            $res = Http::asForm()
                ->timeout(6)
                ->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
                    'secret' => $secret,
                    'response' => $token,
                    'remoteip' => $ip,
                ]);

            return $res->ok() && $res->json('success') === true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        // Turnstile (opsional): bila secret dikonfigurasi, token WAJIB & harus lolos verifikasi.
        $secret = config('services.cloudflare.turnstile_secret');
        if (! empty($secret)) {
            $token = (string) $request->input('turnstile_token', '');
            if ($token === '' || ! $this->verifyTurnstile($secret, $token, $request->ip())) {
                return ResponseHelper::error('Verifikasi keamanan gagal. Selesaikan captcha lalu coba lagi.', 400);
            }
        }

        $row = DB::table($this->table)->where('Email', $data['email'])->first();

        // Satu pesan untuk email tak dikenal maupun sandi salah — tidak
        // membocorkan email mana yang terdaftar.
        if (! $row || ! Hash::check($data['password'], (string) $row->Password)) {
            return ResponseHelper::error('Email atau kata sandi salah.', 401);
        }

        if ($row->Status !== 'AKTIF') {
            return ResponseHelper::error('Akun Anda dinonaktifkan. Silakan hubungi tim rekrutmen EVO Group.', 403);
        }

        // Wajib verifikasi email dulu sebelum bisa masuk. Kirim `code` khusus
        // supaya frontend bisa menampilkan tombol "kirim ulang verifikasi".
        if (($row->Flag_Email_Verified ?? 'T') !== 'Y') {
            return response()->json(
                [
                    'success' => false,
                    'status' => 403,
                    'code' => 'BELUM_VERIFIKASI',
                    'message' =>
                        'Email kamu belum diverifikasi. Silakan cek kotak masuk/spam, atau kirim ulang tautan verifikasi.',
                ],
                403,
            );
        }

        if (
            $row->Valid_Until !== null &&
            Carbon::parse($row->Valid_Until)
                ->startOfDay()
                ->lessThan(Carbon::now()->startOfDay())
        ) {
            return ResponseHelper::error(
                'Masa berlaku akun Anda telah berakhir pada '.Carbon::parse($row->Valid_Until)->format('d M Y').'.',
                403,
            );
        }

        DB::table($this->table)
            ->where('Id_Users', $row->Id_Users)
            ->update(['Last_Login_At' => Carbon::now()]);

        // Sesi baru untuk login baru — id sesi lama tidak terbawa (session fixation).
        $request->session()->regenerate();

        $user = [
            'id' => $row->Id_Users,
            'nama' => $row->Nama,
            'email' => $row->Email,
            'role' => $row->Role,
            'klasifikasi' => $row->Klasifikasi,
            'valid_until' => $row->Valid_Until,
            'pwd_epoch' => $row->Pwd_Changed_At,
        ];
        $request->session()->put('career_auth', $user);

        // Paket hak akses (permissions / label menu / kategori). Kandidat yang
        // belum punya baris akses di-provision otomatis dari cetakan
        // klasifikasinya di dalam AksesService.
        $request
            ->session()
            ->put('career_akses', AksesService::paket((int) $row->Id_Users, (string) $row->Role, $row->Klasifikasi));

        return ResponseHelper::success($user, 'Login berhasil.');
    }

    public function logout(Request $request)
    {
        $request->session()->forget(['career_auth', 'career_akses']);

        return ResponseHelper::success(null, 'Logout berhasil.');
    }

    /**
     * Catat satu peristiwa reset kata sandi ke tabel audit (queryable).
     * Sengaja dibungkus try/catch: kegagalan audit TIDAK boleh menggagalkan
     * alur reset. `Keterangan` TIDAK PERNAH memuat OTP atau kata sandi.
     */
    private function catatAuditReset(
        Request $request,
        string $event,
        ?int $userId,
        ?string $email,
        ?string $ket = null,
    ): void {
        try {
            DB::table($this->auditTable)->insert([
                'Id_Users' => $userId,
                'Email' => $email !== null ? mb_substr($email, 0, 150) : null,
                'Event' => $event,
                'Ip_Address' => $request->ip(),
                'User_Agent' => mb_substr((string) $request->userAgent(), 0, 255),
                'Keterangan' => $ket !== null ? mb_substr($ket, 0, 255) : null,
                'Created_At' => Carbon::now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning("[RESET] gagal audit '{$event}': ".$e->getMessage());
        }
    }

    /**
     * Siapkan OTP reset kata sandi: simpan HASH OTP di akun, setel masa
     * berlaku, reset counter percobaan, hitung ulang window rate-limit, lalu
     * catat `Akun.KodeDiminta` (OTP terbungkus) — surelnya dikirim zona dalam.
     */
    private function catatOtpReset(int $userId): void
    {
        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT); // 6 digit, CSPRNG
        $now = Carbon::now();

        DB::transaction(function () use ($userId, $otp, $now) {
            $row = DB::table($this->table)->where('Id_Users', $userId)->first();

            $windowMasihBerlaku =
                $row &&
                $row->Reset_Otp_Window_At &&
                Carbon::parse($row->Reset_Otp_Window_At)->diffInMinutes($now) < self::RESET_OTP_WINDOW_MENIT;
            $kirimCount = $windowMasihBerlaku ? (int) ($row->Reset_Otp_Kirim_Count ?? 0) + 1 : 1;
            $windowAt = $windowMasihBerlaku ? $row->Reset_Otp_Window_At : $now;

            DB::table($this->table)
                ->where('Id_Users', $userId)
                ->update([
                    'Reset_Otp_Hash' => hash('sha256', $otp),
                    'Reset_Otp_Expired_At' => $now->copy()->addMinutes(self::RESET_OTP_BERLAKU_MENIT),
                    'Reset_Otp_Attempt' => 0,
                    'Reset_Otp_Sent_At' => $now,
                    'Reset_Otp_Kirim_Count' => $kirimCount,
                    'Reset_Otp_Window_At' => $windowAt,
                    'Updated_At' => $now,
                ]);

            Outbox::tulis(Outbox::AKUN_KODE_DIMINTA, 'Akun.KodeDiminta:'.Str::uuid(), $userId, [
                'id_publik' => $userId,
                'email' => $row->Email,
                'nama' => $row->Nama,
                'jenis' => 'RESET',
                'berlaku_menit' => self::RESET_OTP_BERLAKU_MENIT,
                'rahasia' => RahasiaSinkron::bungkus(['otp' => $otp]),
            ], $row->Email);
        });
    }

    /**
     * Minta OTP reset kata sandi (POST api/v1/lupa-sandi, body: email).
     *
     * ANTI-ENUMERASI: respons SELALU identik (status, pesan, bentuk) terlepas
     * dari apakah email terdaftar, aktif, atau sedang dalam masa cooldown —
     * supaya tidak membocorkan keberadaan sebuah akun. OTP hanya dikirim untuk
     * akun AKTIF + terverifikasi yang tidak melanggar cooldown/kuota window.
     */
    public function mintaOtpReset(Request $request)
    {
        $data = $request->validate(['email' => 'required|email']);

        $row = DB::table($this->table)->where('Email', $data['email'])->first();

        if ($row && $row->Status === 'AKTIF' && ($row->Flag_Email_Verified ?? 'T') === 'Y') {
            $now = Carbon::now();

            // Cooldown HANYA berlaku selama masih ada OTP aktif (mencegah spam
            // kirim-ulang saat kode lama masih hidup). Bila OTP sudah hangus,
            // user berhak langsung minta kode baru — cukup dibatasi kuota per jam.
            $kenaCooldown =
                $row->Reset_Otp_Hash &&
                $row->Reset_Otp_Sent_At &&
                Carbon::parse($row->Reset_Otp_Sent_At)->diffInMinutes($now) < self::RESET_OTP_THROTTLE_MENIT;

            $windowMasihBerlaku =
                $row->Reset_Otp_Window_At &&
                Carbon::parse($row->Reset_Otp_Window_At)->diffInMinutes($now) < self::RESET_OTP_WINDOW_MENIT;
            $kenaKuota = $windowMasihBerlaku && (int) ($row->Reset_Otp_Kirim_Count ?? 0) >= self::RESET_OTP_MAX_KIRIM;

            if ($kenaCooldown || $kenaKuota) {
                // Diam-diam tidak mengirim ulang (tetap balas generik) — jangan
                // munculkan 429 yang bisa dipakai membedakan email terdaftar.
                $this->catatAuditReset(
                    $request,
                    'REQUEST',
                    (int) $row->Id_Users,
                    $row->Email,
                    $kenaCooldown ? 'cooldown' : 'kuota window',
                );
            } else {
                try {
                    $this->catatOtpReset((int) $row->Id_Users);
                    $this->catatAuditReset($request, 'REQUEST', (int) $row->Id_Users, $row->Email);
                } catch (\Throwable $e) {
                    Log::error("[RESET] gagal mencatat permintaan OTP user #{$row->Id_Users}: ".$e->getMessage());
                }
            }
        } else {
            // Email tidak terdaftar / non-aktif / belum verifikasi → tidak kirim.
            $this->catatAuditReset($request, 'REQUEST', null, $data['email'], 'tidak memenuhi syarat');
        }

        return ResponseHelper::success(
            ['email' => $data['email']],
            'Jika email terdaftar, kami telah mengirim kode OTP 6 digit (berlaku '.
                self::RESET_OTP_BERLAKU_MENIT.
                ' menit). Silakan cek kotak masuk atau folder spam.',
        );
    }

    /**
     * Inti pemeriksaan OTP terhadap baris user — dipakai bersama oleh verifikasiOtp
     * (cek dulu) dan gantiSandi (submit final). Efek samping: menaikkan counter
     * salah & menghanguskan OTP saat kedaluwarsa/terkunci. TIDAK mengganti password
     * dan TIDAK mengonsumsi OTP saat cocok.
     * Return: 'ok' | 'invalid' | 'expired' | 'locked'.
     */
    private function statusOtp(Request $request, ?object $row, string $otp, ?string $email = null): string
    {
        $emailAudit = $row?->Email ?? $email;

        // Tidak ada akun / tidak ada OTP aktif → generik "invalid" (anti-enumerasi).
        if (! $row || ! $row->Reset_Otp_Hash) {
            $this->catatAuditReset(
                $request,
                'WRONG',
                $row ? (int) $row->Id_Users : null,
                $emailAudit,
                'tanpa OTP aktif',
            );

            return 'invalid';
        }

        // Kedaluwarsa → hanguskan.
        if ($row->Reset_Otp_Expired_At && Carbon::parse($row->Reset_Otp_Expired_At)->isPast()) {
            DB::table($this->table)
                ->where('Id_Users', $row->Id_Users)
                ->update([
                    'Reset_Otp_Hash' => null,
                    'Reset_Otp_Expired_At' => null,
                    'Updated_At' => Carbon::now(),
                ]);
            $this->catatAuditReset($request, 'EXPIRED', (int) $row->Id_Users, $row->Email);

            return 'expired';
        }

        // Sudah mencapai batas percobaan → terkunci.
        if ((int) ($row->Reset_Otp_Attempt ?? 0) >= self::RESET_OTP_MAX_ATTEMPT) {
            DB::table($this->table)
                ->where('Id_Users', $row->Id_Users)
                ->update([
                    'Reset_Otp_Hash' => null,
                    'Reset_Otp_Expired_At' => null,
                    'Updated_At' => Carbon::now(),
                ]);
            $this->catatAuditReset($request, 'LOCKED', (int) $row->Id_Users, $row->Email);

            return 'locked';
        }

        // Salah → tambah counter; bila mencapai batas, hanguskan OTP.
        if (! hash_equals($row->Reset_Otp_Hash, hash('sha256', $otp))) {
            $percobaan = (int) ($row->Reset_Otp_Attempt ?? 0) + 1;
            $mencapaiBatas = $percobaan >= self::RESET_OTP_MAX_ATTEMPT;

            DB::table($this->table)
                ->where('Id_Users', $row->Id_Users)
                ->update([
                    'Reset_Otp_Attempt' => $percobaan,
                    'Reset_Otp_Hash' => $mencapaiBatas ? null : $row->Reset_Otp_Hash,
                    'Reset_Otp_Expired_At' => $mencapaiBatas ? null : $row->Reset_Otp_Expired_At,
                    'Updated_At' => Carbon::now(),
                ]);
            $this->catatAuditReset(
                $request,
                $mencapaiBatas ? 'LOCKED' : 'WRONG',
                (int) $row->Id_Users,
                $row->Email,
                "attempt {$percobaan}/".self::RESET_OTP_MAX_ATTEMPT,
            );

            return $mencapaiBatas ? 'locked' : 'invalid';
        }

        return 'ok';
    }

    /** Ubah status OTP non-'ok' menjadi respons JSON ber-`code` (dibaca frontend). */
    private function responsOtpGagal(string $status)
    {
        return match ($status) {
            'expired' => response()->json(
                [
                    'success' => false,
                    'status' => 410,
                    'code' => 'OTP_EXPIRED',
                    'message' => 'Kode OTP sudah kedaluwarsa. Silakan minta kode baru.',
                ],
                410,
            ),
            'locked' => response()->json(
                [
                    'success' => false,
                    'status' => 429,
                    'code' => 'OTP_LOCKED',
                    'message' => 'Terlalu banyak percobaan. Silakan minta kode OTP baru.',
                ],
                429,
            ),
            default => response()->json(
                [
                    'success' => false,
                    'status' => 422,
                    'code' => 'OTP_INVALID',
                    'message' => 'Kode OTP salah atau sudah tidak berlaku.',
                ],
                422,
            ),
        };
    }

    /**
     * Verifikasi OTP SAJA (POST api/v1/verifikasi-otp, body: email+otp) — dipakai
     * frontend untuk mengecek kode SEBELUM menampilkan form kata sandi baru. OTP
     * TIDAK dikonsumsi di sini, namun batas percobaan & kedaluwarsa tetap berlaku.
     */
    public function verifikasiOtp(Request $request)
    {
        $data = $request->validate([
            'email' => 'required|email',
            'otp' => 'required|string',
        ]);

        $row = DB::table($this->table)->where('Email', $data['email'])->first();
        $status = $this->statusOtp($request, $row, $data['otp'], $data['email']);

        if ($status !== 'ok') {
            return $this->responsOtpGagal($status);
        }

        $this->catatAuditReset($request, 'VERIFIED', (int) $row->Id_Users, $row->Email);

        return ResponseHelper::success(['email' => $data['email']], 'Kode OTP benar. Silakan buat kata sandi baru.');
    }

    /**
     * Reset kata sandi dengan OTP (POST api/v1/ganti-sandi, body: email+otp+password).
     *
     * OTP diperiksa ulang via statusOtp lalu SEKALI PAKAI. Setelah sukses: semua
     * sesi aktif user diinvalidasi (Pwd_Changed_At di-bump, dibaca ulang di
     * CareerAuth), surel pemberitahuan diminta ke zona dalam, dan dicatat ke audit.
     */
    public function gantiSandi(Request $request)
    {
        $data = $request->validate([
            'email' => 'required|email',
            'otp' => 'required|string',
            'password' => 'required|string|min:6',
        ]);

        $row = DB::table($this->table)->where('Email', $data['email'])->first();
        $status = $this->statusOtp($request, $row, $data['otp'], $data['email']);

        if ($status !== 'ok') {
            return $this->responsOtpGagal($status);
        }

        $now = Carbon::now();
        $userId = (int) $row->Id_Users;
        DB::transaction(function () use ($userId, $data, $now, $row) {
            DB::table($this->table)
                ->where('Id_Users', $userId)
                ->update([
                    'Password' => Hash::make($data['password']),
                    'Reset_Otp_Hash' => null,
                    'Reset_Otp_Expired_At' => null,
                    'Reset_Otp_Attempt' => 0,
                    'Pwd_Changed_At' => $now,
                    'Updated_At' => $now,
                    'Updated_By' => $row->Email,
                ]);

            // Zona dalam mengirim surel "kata sandimu baru saja diganti".
            Outbox::tulis(Outbox::AKUN_DIPERBARUI, 'Akun.Diperbarui:'.Str::uuid(), $userId, [
                'akun' => $this->profilAkun($userId),
                'perubahan' => ['sandi_diganti'],
            ], $row->Email);
        });

        $this->catatAuditReset($request, 'SUCCESS', $userId, $row->Email);
        Log::info("[RESET] user #{$userId} berhasil reset kata sandi.");

        // Bersihkan sesi tab yang melakukan reset.
        $request->session()->forget(['career_auth', 'career_akses']);

        return ResponseHelper::success(null, 'Kata sandi berhasil diperbarui. Silakan masuk dengan kata sandi baru.');
    }
}
