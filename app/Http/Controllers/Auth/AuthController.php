<?php

namespace App\Http\Controllers\Auth;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Jobs\Career\WcSyncEmailJob;
use App\Services\WebCareers\HclClient;
use App\Support\Career\AksesService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;

/**
 * WEB CAREER — AUTH (REAL, DB N_WEB_CAREERS_Users) memakai QUERY BUILDER.
 * Controller WEB, dipanggil via axios ke route web ber-prefix api/v1 → balas JSON.
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

    /**
     * Siapkan verifikasi email: simpan HASH token di DB (token asli tidak
     * pernah disimpan), lalu antrekan pengiriman via queue 'wc-syncemailjob'.
     * Gagal kirim email TIDAK boleh menggagalkan registrasi → dibungkus try.
     */
    private function kirimEmailVerifikasi(int $userId): void
    {
        try {
            $token = Str::random(64); // dikirim utuh di magic link
            $now = Carbon::now();

            DB::table($this->table)
                ->where('Id_Users', $userId)
                ->update([
                    'Flag_Email_Verified' => 'T',
                    'Email_Verified_At' => null,
                    'Email_Verif_Token' => hash('sha256', $token),
                    'Email_Verif_Expired_At' => $now->copy()->addMinutes(self::VERIF_BERLAKU_MENIT),
                    'Email_Verif_Attempt' => DB::raw('ISNULL(Email_Verif_Attempt, 0) + 1'),
                    'Updated_At' => $now,
                ]);

            WcSyncEmailJob::kirim(WcSyncEmailJob::JENIS_VERIFIKASI, $userId, ['token' => $token]);

            $conn = env('QUEUE_CONNECTION');
            $queue = $conn === 'cloudtasks' ? WcSyncEmailJob::QUEUE : 'default';
            Log::info("[EMAIL] job verifikasi DI-ANTRE user #{$userId} (conn={$conn}, queue={$queue}).");
        } catch (\Throwable $e) {
            Log::error("[EMAIL] gagal antre verifikasi user #{$userId}: " . $e->getMessage());
            Log::channel('web_career')->error("[EMAIL] gagal antre verifikasi user #{$userId}: " . $e->getMessage());
        }
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
                'Nomor KTP ini sudah terdaftar pada akun dengan email ' .
                    $this->maskEmail($pemilik->Email) .
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
                //
                // Ditegakkan DI SERVER, bukan cukup di layar. Aturan yang hanya hidup
                // di browser bukan aturan: pintu ini bisa diketuk langsung, dan huruf
                // yang lolos masuk akan mendarat di HRIS Rekrutmen lewat sinkron
                // biodata — lalu ada yang mencoba menelepon nomor yang tak bisa
                // ditelepon siapa pun.
                //
                // Awalan '62' SENGAJA tidak dituntut: negaranya dipilih kandidat,
                // dan memaksa +62 menutup pintu bagi pelamar luar negeri.
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
        //    Bila NIK sudah terpakai akun lain, tolak & tunjukkan emailnya (mask). ──
        $pemilikNik = DB::table($this->table)
            ->where('NIK', $data['nik'])
            ->where('Email', '!=', $data['email'])
            ->first();
        if ($pemilikNik) {
            return ResponseHelper::error(
                'Nomor KTP ini sudah terdaftar pada akun dengan email ' .
                    $this->maskEmail($pemilikNik->Email) .
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
            // Masa berlaku pendaftaran mengikuti KLASIFIKASI akun (mis. trial
            // 6 bulan → Valid_Until = tanggal daftar + Durasi_Hari klasifikasi).
            $masihBerlaku =
                $existing->Status === 'AKTIF' &&
                ($existing->Valid_Until === null ||
                    Carbon::parse($existing->Valid_Until)
                        ->startOfDay()
                        ->greaterThanOrEqualTo($now->copy()->startOfDay()));

            // Ditolak HANYA bila akun terverifikasi DAN masih dalam masa berlaku.
            if ($terverifikasi && $masihBerlaku) {
                $sampai = $existing->Valid_Until
                    ? ' hingga ' . Carbon::parse($existing->Valid_Until)->format('d M Y')
                    : '';

                return ResponseHelper::error(
                    "Email sudah terdaftar dan masih aktif{$sampai}. Silakan langsung masuk.",
                    422,
                );
            }

            // Dua kasus yang BOLEH daftar ulang (akun di-klaim ulang):
            //  1. Belum pernah verifikasi email — kepemilikan email belum
            //     terbukti, jadi pendaftar sekarang berhak mengambil alih.
            //     Tanpa ini, orang yang telat verifikasi akan selamanya
            //     mentok "email sudah terdaftar" tanpa pernah dapat email.
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

            DB::table($this->table)->where('Id_Users', $existing->Id_Users)->update($perubahan);

            // Sinkron identitas ke HCLearn via API (buat/lengkapi calon).
            $this->daftarkanHrisRekrutmen(
                (int) $existing->Id_Users,
                $data['nama'],
                $data['email'],
                $data['phone'] ?? $existing->No_Hp,
                $data['nik'],
            );

            if ($autoVerifikasi) {
                Log::info("[EMAIL] auto-verifikasi development untuk user #{$existing->Id_Users} ({$data['email']}).");
            } else {
                $this->kirimEmailVerifikasi((int) $existing->Id_Users);
            }

            if ($autoVerifikasi) {
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

        $id = DB::table($this->table)->insertGetId($akunBaru, 'Id_Users');

        // Daftarkan ke HCLearn via API + set Kode_Calon (identitas HCLearn, prefix CK).
        $this->daftarkanHrisRekrutmen((int) $id, $data['nama'], $data['email'], $data['phone'] ?? null, $data['nik']);

        if ($autoVerifikasi) {
            Log::info("[EMAIL] auto-verifikasi development untuk user #{$id} ({$data['email']}).");

            return ResponseHelper::success(
                ['email' => $data['email'], 'perlu_verifikasi' => false],
                'Registrasi berhasil. Email otomatis terverifikasi dalam mode development; silakan masuk.',
                201,
            );
        }

        $this->kirimEmailVerifikasi((int) $id);

        // TIDAK auto-login: kandidat wajib verifikasi email dulu baru bisa masuk.
        return ResponseHelper::success(
            ['email' => $data['email'], 'perlu_verifikasi' => true],
            'Registrasi berhasil! Kami telah mengirim tautan verifikasi ke email kamu (berlaku ' .
                self::VERIF_BERLAKU_MENIT .
                ' menit). Setelah verifikasi, silakan masuk.',
            201,
        );
    }

    /**
     * Daftarkan kandidat ke HCLearn LEWAT API (POST api/v1/web-careers/kandidat)
     * — TIDAK lagi insert langsung ke HRIS_Rekrutmen_Karyawan. Kode_Calon dibuat
     * oleh CAT memakai aturan internalnya (prefix 'CK', sama dengan insert data
     * calon), lalu disimpan ke Users.Kode_Calon.
     *
     * Idempoten di sisi CAT (kunci Id_WC_Users): registrasi ulang mengembalikan
     * Kode_Calon lama. Best-effort — kegagalan API tidak menggagalkan registrasi;
     * Kode_Calon menyusul (kandidat tanpa kode tertahan saat penjadwalan tes,
     * dengan pesan yang sudah ada di modul Penjadwalan).
     */
    private function daftarkanHrisRekrutmen(int $idUsers, string $nama, string $email, ?string $hp, string $nik): void
    {
        try {
            $hasil = app(HclClient::class)->post(
                'kandidat',
                [
                    'Id_WC_Users' => $idUsers,
                    'Nama' => $nama,
                    'Email' => $email,
                    'HP' => $hp,
                    'NIK' => $nik,
                ],
                [
                    'Jenis_Event' => 'REGISTRASI_KANDIDAT',
                ],
            );

            $kodeCalon = $hasil['result']['Kode_Calon'] ?? null;
            if (!$hasil['sukses'] || !$kodeCalon) {
                Log::channel('web_career')->error(
                    "Registrasi kandidat #{$idUsers} ke HCLearn gagal: " . ($hasil['message'] ?? 'tanpa pesan'),
                );

                return;
            }

            DB::table($this->table)
                ->where('Id_Users', $idUsers)
                ->update(['Kode_Calon' => $kodeCalon]);
            Log::channel('web_career')->info("Kandidat #{$idUsers} terdaftar di HCLearn: {$kodeCalon}");
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal daftarkan kandidat #{$idUsers} ke HCLearn: " . $e->getMessage());
        }
    }

    /** Samarkan email untuk pesan publik: fransbachtiar4@gmail.com → f***********4@gmail.com */
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
            return $lokal[0] . '***' . $domain;
        }

        return $lokal[0] . str_repeat('*', max(3, $len - 2)) . $lokal[$len - 1] . $domain;
    }

    /**
     * Magic link dari email: GET /verifikasi-email?email=...&token=...
     * Merender halaman hasil (Career/VerifikasiEmail) dengan status:
     *  - sukses      : baru saja terverifikasi (countdown ke /login)
     *  - sudah       : sudah pernah terverifikasi (idempoten — link diklik 2x)
     *  - kadaluarsa  : token benar tapi lewat masa berlaku → tombol kirim ulang
     *  - invalid     : token salah/kosong → form tempel tautan/token manual
     * Token dibandingkan sebagai HASH SHA-256 (constant-time) dan SEKALI PAKAI
     * (dihapus setelah sukses). Bila email tak dikirim (kandidat hanya menempel
     * token), pencarian jatuh ke hash token.
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
        if (!$row) {
            return ResponseHelper::error('Email tidak terdaftar.', 404);
        }

        return ResponseHelper::success(['verified' => ($row->Flag_Email_Verified ?? 'T') === 'Y']);
    }

    /**
     * Inti verifikasi token, dipakai bersama magic-link (GET) & tempel-token (POST).
     * Mengembalikan ['status' => sukses|sudah|kadaluarsa|invalid, 'email' => ?string].
     * Token dibandingkan sebagai HASH SHA-256 (constant-time) dan SEKALI PAKAI
     * (dihapus setelah sukses). Bila email tak diketahui (hanya token), pencarian
     * jatuh ke hash token karena kolomnya unik per akun.
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
        if (!$row) {
            $row = DB::table($this->table)->where('Email_Verif_Token', hash('sha256', $token))->first();
        }

        if (!$row) {
            return ['status' => 'invalid', 'email' => null];
        }

        // Sudah terverifikasi (mis. tautan diklik dua kali) → idempoten.
        if (($row->Flag_Email_Verified ?? 'T') === 'Y') {
            return ['status' => 'sudah', 'email' => $row->Email];
        }

        if (!$row->Email_Verif_Token || !hash_equals($row->Email_Verif_Token, hash('sha256', $token))) {
            return ['status' => 'invalid', 'email' => null];
        }

        if ($row->Email_Verif_Expired_At && Carbon::parse($row->Email_Verif_Expired_At)->isPast()) {
            return ['status' => 'kadaluarsa', 'email' => $row->Email];
        }

        // Sukses → tandai verified + hapus token (sekali pakai).
        DB::table($this->table)
            ->where('Id_Users', $row->Id_Users)
            ->update([
                'Flag_Email_Verified' => 'Y',
                'Email_Verified_At' => Carbon::now(),
                'Email_Verif_Token' => null,
                'Email_Verif_Expired_At' => null,
                'Updated_At' => Carbon::now(),
            ]);

        Log::channel('web_career')->info("[EMAIL] user #{$row->Id_Users} ({$row->Email}) terverifikasi.");

        return ['status' => 'sukses', 'email' => $row->Email];
    }

    /** Kirim ulang email verifikasi (POST api/v1/kirim-verifikasi, body: email). */
    public function kirimUlangVerifikasi(Request $request)
    {
        $data = $request->validate(['email' => 'required|email']);

        $row = DB::table($this->table)->where('Email', $data['email'])->first();
        if (!$row) {
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

        $this->kirimEmailVerifikasi((int) $row->Id_Users);

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
        if (!empty($secret)) {
            $token = (string) $request->input('turnstile_token', '');
            if ($token === '' || !$this->verifyTurnstile($secret, $token, $request->ip())) {
                return ResponseHelper::error('Verifikasi keamanan gagal. Selesaikan captcha lalu coba lagi.', 400);
            }
        }

        $row = DB::table($this->table)->where('Email', $data['email'])->first();

        // if (!$row || !Hash::check($data['password'], $row->Password)) {
        //     return ResponseHelper::error('Email atau kata sandi salah.', 401);
        // }

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
                'Masa berlaku akun Anda telah berakhir pada ' . Carbon::parse($row->Valid_Until)->format('d M Y') . '.',
                403,
            );
        }

        DB::table($this->table)
            ->where('Id_Users', $row->Id_Users)
            ->update(['Last_Login_At' => Carbon::now()]);

        $user = [
            'id' => $row->Id_Users,
            'nama' => $row->Nama,
            'email' => $row->Email,
            'role' => $row->Role,
            'klasifikasi' => $row->Klasifikasi,
            'valid_until' => $row->Valid_Until,
            'pwd_epoch' => $row->Pwd_Changed_At,
            // Jembatan ke MPP: penanggung jawabnya disimpan sebagai kode karyawan,
            // bukan sebagai akun. Dibawa di sesi supaya penyaring lingkup PIC
            // tidak perlu menanyakannya ke database pada setiap permintaan.
            //
            // Ikut kedaluwarsa bersama sesinya: kode yang diubah admin baru
            // berlaku setelah pemiliknya masuk lagi — sama seperti perannya.
            'kode_karyawan' => $row->Kode_Karyawan ?? null,
        ];
        $request->session()->put('career_auth', $user);

        // Paket hak akses (permissions / label menu / kategori) — pola cat-evo.
        // Kandidat yang belum punya baris akses di-provision otomatis dari cetakan
        // klasifikasinya di dalam AksesService, jadi tidak perlu disentuh admin.
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
            Log::channel('web_career')->error("[RESET] gagal audit '{$event}': " . $e->getMessage());
        }
    }

    /**
     * Siapkan OTP reset kata sandi: simpan HASH OTP di DB (OTP asli tidak pernah
     * disimpan), setel masa berlaku, reset counter percobaan, dan hitung ulang
     * window rate-limit di PHP (hindari fungsi khusus SQL Server). Pengiriman
     * email dilakukan asinkron lewat WcSyncEmailJob; `Reset_Otp_Sent_At` diset di
     * job SETELAH email benar-benar terkirim (mulai hitung cooldown).
     */
    private function kirimOtpReset(int $userId): void
    {
        try {
            $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT); // 6 digit, CSPRNG
            $now = Carbon::now();

            $row = DB::table($this->table)->where('Id_Users', $userId)->first();

            // Window rate-limit dihitung di PHP (tanpa ISNULL/T-SQL) agar portabel.
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
                    'Reset_Otp_Kirim_Count' => $kirimCount,
                    'Reset_Otp_Window_At' => $windowAt,
                    'Updated_At' => $now,
                ]);

            WcSyncEmailJob::kirim(WcSyncEmailJob::JENIS_RESET_OTP, $userId, [
                'otp' => $otp,
                'menit' => self::RESET_OTP_BERLAKU_MENIT,
            ]);
        } catch (\Throwable $e) {
            Log::error("[RESET] gagal antre OTP user #{$userId}: " . $e->getMessage());
            Log::channel('web_career')->error("[RESET] gagal antre OTP user #{$userId}: " . $e->getMessage());
        }
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
            // kirim-ulang saat kode lama masih hidup). Bila OTP sudah hangus
            // (terkunci karena salah berkali-kali / sudah terpakai), user berhak
            // langsung minta kode baru — cukup dibatasi kuota per jam.
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
                $this->kirimOtpReset((int) $row->Id_Users);
                $this->catatAuditReset($request, 'REQUEST', (int) $row->Id_Users, $row->Email);
            }
        } else {
            // Email tidak terdaftar / non-aktif / belum verifikasi → tidak kirim.
            $this->catatAuditReset($request, 'REQUEST', null, $data['email'], 'tidak memenuhi syarat');
        }

        return ResponseHelper::success(
            ['email' => $data['email']],
            'Jika email terdaftar, kami telah mengirim kode OTP 6 digit (berlaku ' .
                self::RESET_OTP_BERLAKU_MENIT .
                ' menit). Silakan cek kotak masuk atau folder spam.',
        );
    }

    /**
     * Inti pemeriksaan OTP terhadap baris user — dipakai bersama oleh verifikasiOtp
     * (cek dulu) dan gantiSandi (submit final). Efek samping: menaikkan counter
     * salah & menghanguskan OTP saat kedaluwarsa/terkunci. TIDAK mengganti password
     * dan TIDAK mengonsumsi OTP saat cocok — supaya OTP yang sudah diverifikasi
     * masih valid untuk langkah simpan kata sandi.
     * Return: 'ok' | 'invalid' | 'expired' | 'locked'.
     */
    private function statusOtp(Request $request, ?object $row, string $otp, ?string $email = null): string
    {
        $emailAudit = $row?->Email ?? $email;

        // Tidak ada akun / tidak ada OTP aktif → generik "invalid" (anti-enumerasi).
        if (!$row || !$row->Reset_Otp_Hash) {
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

        // Salah → tambah counter (di PHP); bila mencapai batas, hanguskan OTP.
        if (!hash_equals($row->Reset_Otp_Hash, hash('sha256', $otp))) {
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
                "attempt {$percobaan}/" . self::RESET_OTP_MAX_ATTEMPT,
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
     * TIDAK dikonsumsi di sini (masih dipakai saat submit gantiSandi), namun batas
     * percobaan & kedaluwarsa tetap diberlakukan (anti brute-force).
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
     * OTP diperiksa ulang via statusOtp (constant-time, batas percobaan, kedaluwarsa)
     * lalu SEKALI PAKAI (dihapus setelah sukses). Setelah sukses: semua sesi aktif
     * user diinvalidasi (Pwd_Changed_At di-bump, dibaca ulang di CareerAuth), email
     * pemberitahuan dikirim, dan peristiwa dicatat ke audit.
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

        // OTP benar → ganti kata sandi, OTP SEKALI PAKAI (dihapus), dan bump
        // Pwd_Changed_At sebagai "session epoch" → seluruh sesi aktif otomatis
        // keluar pada permintaan berikutnya (dibaca ulang di CareerAuth).
        $now = Carbon::now();
        DB::table($this->table)
            ->where('Id_Users', $row->Id_Users)
            ->update([
                'Password' => Hash::make($data['password']),
                'Reset_Otp_Hash' => null,
                'Reset_Otp_Expired_At' => null,
                'Reset_Otp_Attempt' => 0,
                'Pwd_Changed_At' => $now,
                'Updated_At' => $now,
                'Updated_By' => $row->Email,
            ]);

        WcSyncEmailJob::kirim(WcSyncEmailJob::JENIS_RESET_SELESAI, (int) $row->Id_Users);

        $this->catatAuditReset($request, 'SUCCESS', (int) $row->Id_Users, $row->Email);
        Log::channel('web_career')->info("[RESET] user #{$row->Id_Users} ({$row->Email}) berhasil reset kata sandi.");

        // Bersihkan sesi tab yang melakukan reset.
        $request->session()->forget(['career_auth', 'career_akses']);

        return ResponseHelper::success(null, 'Kata sandi berhasil diperbarui. Silakan masuk dengan kata sandi baru.');
    }
}
