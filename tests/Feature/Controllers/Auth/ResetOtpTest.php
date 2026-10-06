<?php

namespace Tests\Feature\Controllers\Auth;

use App\Http\Middleware\VerifyCsrfToken;
use App\Jobs\Career\WcSyncEmailJob;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * WEB CAREER — Reset kata sandi via OTP.
 *
 * Mengikuti house-style HomepageControllerTest: SQLite in-memory yang dibuat di
 * setUp(), user dibangun manual (tabel N_WEB_CAREERS_Users dikelola eksternal
 * sehingga UserFactory tidak dipakai). CSRF dimatikan tapi StartSession tetap
 * jalan (controller memakai $request->session()). Job di-fake — dispatch cukup
 * diverifikasi tanpa menjalankan handle().
 */
class ResetOtpTest extends TestCase
{
    private int $userId;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);

        // Route reset ada di grup web → matikan HANYA CSRF, biarkan StartSession
        // hidup agar $request->session() tersedia di controller.
        $this->withoutMiddleware(VerifyCsrfToken::class);
        Bus::fake();

        Schema::create('N_WEB_CAREERS_Users', function (Blueprint $table) {
            $table->increments('Id_Users');
            $table->string('Nama')->nullable();
            $table->string('Email')->nullable();
            $table->string('Password')->nullable();
            $table->string('Status')->nullable();
            $table->string('Flag_Email_Verified')->nullable();
            $table->dateTime('Valid_Until')->nullable();
            $table->string('Reset_Otp_Hash', 64)->nullable();
            $table->dateTime('Reset_Otp_Expired_At')->nullable();
            $table->integer('Reset_Otp_Attempt')->nullable();
            $table->dateTime('Reset_Otp_Sent_At')->nullable();
            $table->integer('Reset_Otp_Kirim_Count')->nullable();
            $table->dateTime('Reset_Otp_Window_At')->nullable();
            $table->dateTime('Pwd_Changed_At')->nullable();
            $table->dateTime('Updated_At')->nullable();
            $table->string('Updated_By')->nullable();
        });

        Schema::create('N_WEB_CAREERS_Reset_Audit', function (Blueprint $table) {
            $table->bigIncrements('Id_Audit');
            $table->integer('Id_Users')->nullable();
            $table->string('Email')->nullable();
            $table->string('Event');
            $table->string('Ip_Address')->nullable();
            $table->string('User_Agent')->nullable();
            $table->string('Keterangan')->nullable();
            $table->dateTime('Created_At');
        });

        $this->userId = $this->buatUser();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        Schema::dropIfExists('N_WEB_CAREERS_Users');
        Schema::dropIfExists('N_WEB_CAREERS_Reset_Audit');

        parent::tearDown();
    }

    /** Buat satu user aktif + terverifikasi. */
    private function buatUser(array $overrides = []): int
    {
        return DB::table('N_WEB_CAREERS_Users')->insertGetId(array_merge([
            'Nama' => 'Kandidat Uji',
            'Email' => 'kandidat@contoh.test',
            'Password' => Hash::make('oldpass'),
            'Status' => 'AKTIF',
            'Flag_Email_Verified' => 'Y',
        ], $overrides), 'Id_Users');
    }

    /** Set OTP aktif pada user (hash dari kode diketahui). */
    private function setOtp(string $kode, ?Carbon $expired = null, int $attempt = 0): void
    {
        DB::table('N_WEB_CAREERS_Users')->where('Id_Users', $this->userId)->update([
            'Reset_Otp_Hash' => hash('sha256', $kode),
            'Reset_Otp_Expired_At' => $expired ?? Carbon::now()->addMinutes(5),
            'Reset_Otp_Attempt' => $attempt,
        ]);
    }

    private function passwordUser(): string
    {
        return DB::table('N_WEB_CAREERS_Users')->where('Id_Users', $this->userId)->value('Password');
    }

    /** SKENARIO 1: OTP kedaluwarsa → 410, kata sandi tidak berubah. */
    public function test_otp_kedaluwarsa_ditolak(): void
    {
        $this->setOtp('123456', Carbon::now()->subMinute());

        $res = $this->postJson('/api/v1/ganti-sandi', [
            'email' => 'kandidat@contoh.test',
            'otp' => '123456',
            'password' => 'newpass123',
        ]);

        $res->assertStatus(410)->assertJsonPath('code', 'OTP_EXPIRED');
        $this->assertTrue(Hash::check('oldpass', $this->passwordUser()), 'Kata sandi tidak boleh berubah saat OTP kedaluwarsa.');
        $this->assertDatabaseHas('N_WEB_CAREERS_Reset_Audit', ['Event' => 'EXPIRED', 'Id_Users' => $this->userId]);
    }

    /** SKENARIO 2: OTP salah berkali-kali → terkunci pada percobaan ke-5. */
    public function test_otp_salah_berkali_kali_terkunci(): void
    {
        $this->setOtp('123456');

        // Dua percobaan pertama → OTP_INVALID.
        for ($i = 1; $i <= 2; $i++) {
            $this->postJson('/api/v1/ganti-sandi', [
                'email' => 'kandidat@contoh.test',
                'otp' => '000000',
                'password' => 'newpass123',
            ])->assertStatus(422)->assertJsonPath('code', 'OTP_INVALID');
        }

        // Percobaan ke-3 → terkunci & OTP dihanguskan.
        $this->postJson('/api/v1/ganti-sandi', [
            'email' => 'kandidat@contoh.test',
            'otp' => '000000',
            'password' => 'newpass123',
        ])->assertStatus(429)->assertJsonPath('code', 'OTP_LOCKED');

        $row = DB::table('N_WEB_CAREERS_Users')->where('Id_Users', $this->userId)->first();
        $this->assertNull($row->Reset_Otp_Hash, 'OTP harus hangus setelah mencapai batas percobaan.');
        $this->assertTrue(Hash::check('oldpass', $row->Password), 'Kata sandi tidak boleh berubah.');
        $this->assertDatabaseHas('N_WEB_CAREERS_Reset_Audit', ['Event' => 'LOCKED', 'Id_Users' => $this->userId]);
    }

    /** SKENARIO 3: OTP hanya bisa dipakai sekali (single-use). */
    public function test_otp_tidak_bisa_dipakai_dua_kali(): void
    {
        $this->setOtp('123456');

        // Pemakaian pertama → sukses.
        $this->postJson('/api/v1/ganti-sandi', [
            'email' => 'kandidat@contoh.test',
            'otp' => '123456',
            'password' => 'newpass123',
        ])->assertOk();

        // Pemakaian kedua dengan OTP sama → ditolak, kata sandi tetap yang baru.
        $this->postJson('/api/v1/ganti-sandi', [
            'email' => 'kandidat@contoh.test',
            'otp' => '123456',
            'password' => 'passlain456',
        ])->assertStatus(422)->assertJsonPath('code', 'OTP_INVALID');

        $this->assertTrue(Hash::check('newpass123', $this->passwordUser()), 'Kata sandi harus tetap hasil reset pertama.');
    }

    /** SKENARIO 4: reset berhasil → password baru, OTP hangus, epoch di-set, job & audit. */
    public function test_reset_berhasil(): void
    {
        $this->setOtp('123456');

        $this->postJson('/api/v1/ganti-sandi', [
            'email' => 'kandidat@contoh.test',
            'otp' => '123456',
            'password' => 'newpass123',
        ])->assertOk();

        $row = DB::table('N_WEB_CAREERS_Users')->where('Id_Users', $this->userId)->first();
        $this->assertTrue(Hash::check('newpass123', $row->Password), 'Kata sandi harus berganti.');
        $this->assertNull($row->Reset_Otp_Hash, 'OTP harus dihapus setelah sukses (sekali pakai).');
        $this->assertNotNull($row->Pwd_Changed_At, 'Pwd_Changed_At harus di-set untuk invalidasi sesi.');

        Bus::assertDispatched(WcSyncEmailJob::class); // notifikasi RESET_SELESAI
        $this->assertDatabaseHas('N_WEB_CAREERS_Reset_Audit', ['Event' => 'SUCCESS', 'Id_Users' => $this->userId]);
    }

    /** ANTI-ENUMERASI: respons identik untuk email tak terdaftar vs terdaftar; kirim hanya utk yang terdaftar. */
    public function test_minta_otp_tidak_membocorkan_keberadaan_email(): void
    {
        $takTerdaftar = $this->postJson('/api/v1/lupa-sandi', ['email' => 'entah@contoh.test']);
        $terdaftar = $this->postJson('/api/v1/lupa-sandi', ['email' => 'kandidat@contoh.test']);

        // Status + pesan identik → tidak membedakan email terdaftar/tidak.
        $this->assertSame($takTerdaftar->getStatusCode(), $terdaftar->getStatusCode());
        $this->assertSame(
            $takTerdaftar->json('message'),
            $terdaftar->json('message'),
        );

        // OTP hanya di-dispatch untuk email yang terdaftar (sekali).
        Bus::assertDispatchedTimes(WcSyncEmailJob::class, 1);
        $this->assertDatabaseHas('N_WEB_CAREERS_Reset_Audit', ['Event' => 'REQUEST', 'Id_Users' => $this->userId]);
        $this->assertDatabaseHas('N_WEB_CAREERS_Reset_Audit', ['Event' => 'REQUEST', 'Email' => 'entah@contoh.test']);
    }

    /** VERIFIKASI: OTP salah ditolak, TIDAK dikonsumsi (hash tetap ada), counter naik. */
    public function test_verifikasi_otp_salah_ditolak_tanpa_konsumsi(): void
    {
        $this->setOtp('123456');

        $this->postJson('/api/v1/verifikasi-otp', [
            'email' => 'kandidat@contoh.test',
            'otp' => '000000',
        ])->assertStatus(422)->assertJsonPath('code', 'OTP_INVALID');

        $row = DB::table('N_WEB_CAREERS_Users')->where('Id_Users', $this->userId)->first();
        $this->assertNotNull($row->Reset_Otp_Hash, 'OTP tidak boleh dikonsumsi saat verifikasi gagal.');
        $this->assertSame(1, (int) $row->Reset_Otp_Attempt, 'Percobaan salah harus tercatat.');
    }

    /** VERIFIKASI: OTP benar → 200 & TIDAK dikonsumsi; lalu ganti-sandi tetap berhasil. */
    public function test_verifikasi_otp_benar_lalu_reset_dua_langkah(): void
    {
        $this->setOtp('123456');

        // Langkah cek OTP dulu → sukses, password belum berubah, OTP masih ada.
        $this->postJson('/api/v1/verifikasi-otp', [
            'email' => 'kandidat@contoh.test',
            'otp' => '123456',
        ])->assertOk();

        $row = DB::table('N_WEB_CAREERS_Users')->where('Id_Users', $this->userId)->first();
        $this->assertNotNull($row->Reset_Otp_Hash, 'Verifikasi tidak boleh mengonsumsi OTP.');
        $this->assertTrue(Hash::check('oldpass', $row->Password), 'Password belum boleh berubah setelah verifikasi.');
        $this->assertDatabaseHas('N_WEB_CAREERS_Reset_Audit', ['Event' => 'VERIFIED', 'Id_Users' => $this->userId]);

        // Langkah simpan password → sukses, OTP baru dikonsumsi di sini.
        $this->postJson('/api/v1/ganti-sandi', [
            'email' => 'kandidat@contoh.test',
            'otp' => '123456',
            'password' => 'newpass123',
        ])->assertOk();

        $this->assertTrue(Hash::check('newpass123', $this->passwordUser()), 'Password harus berganti setelah langkah kedua.');
    }

    /** COOLDOWN: masih ada OTP aktif + baru dikirim → minta ulang diblokir (tidak kirim). */
    public function test_minta_ulang_diblokir_cooldown_saat_otp_masih_aktif(): void
    {
        $this->setOtp('123456'); // OTP masih hidup
        DB::table('N_WEB_CAREERS_Users')->where('Id_Users', $this->userId)->update(['Reset_Otp_Sent_At' => now()]);

        $this->postJson('/api/v1/lupa-sandi', ['email' => 'kandidat@contoh.test'])->assertOk();

        Bus::assertNotDispatched(WcSyncEmailJob::class); // cooldown → tidak kirim
        $this->assertDatabaseHas('N_WEB_CAREERS_Reset_Audit', ['Event' => 'REQUEST', 'Keterangan' => 'cooldown']);
    }

    /** COOLDOWN: OTP sudah hangus (terkunci/terpakai) → minta ulang TETAP terkirim. */
    public function test_minta_ulang_setelah_otp_hangus_tetap_terkirim(): void
    {
        // Simulasi habis terkunci: OTP hangus tapi baru saja pernah dikirim.
        DB::table('N_WEB_CAREERS_Users')->where('Id_Users', $this->userId)->update([
            'Reset_Otp_Hash' => null,
            'Reset_Otp_Expired_At' => null,
            'Reset_Otp_Sent_At' => now(),
        ]);

        $this->postJson('/api/v1/lupa-sandi', ['email' => 'kandidat@contoh.test'])->assertOk();

        Bus::assertDispatched(WcSyncEmailJob::class); // tidak kena cooldown → kirim
        $row = DB::table('N_WEB_CAREERS_Users')->where('Id_Users', $this->userId)->first();
        $this->assertNotNull($row->Reset_Otp_Hash, 'OTP baru harus dibuat.');
    }
}
