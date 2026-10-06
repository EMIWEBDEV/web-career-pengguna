<?php

namespace Tests\Feature\Controllers\Auth;

use App\Http\Controllers\Auth\AuthController;
use App\Http\Middleware\VerifyCsrfToken;
use App\Support\Sinkron\Outbox;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ReflectionMethod;
use Tests\TestCase;

class RegistrationAutoVerifyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'career_auth.auto_verify_email' => true,
        ]);

        $this->withoutMiddleware(VerifyCsrfToken::class);
        Outbox::palsukan();

        Schema::create('N_WEB_CAREERS_Users', function (Blueprint $table) {
            $table->increments('Id_Users');
            $table->string('Nama')->nullable();
            $table->string('Email')->nullable();
            $table->string('No_Hp')->nullable();
            $table->string('NIK')->nullable();
            $table->string('Password')->nullable();
            $table->string('Role')->nullable();
            $table->string('Klasifikasi')->nullable();
            $table->string('Status')->nullable();
            $table->date('Mulai_Berlaku')->nullable();
            $table->date('Valid_Until')->nullable();
            $table->string('Flag_Email_Verified')->nullable();
            $table->dateTime('Email_Verified_At')->nullable();
            $table->string('Email_Verif_Token')->nullable();
            $table->dateTime('Email_Verif_Expired_At')->nullable();
            $table->dateTime('Email_Verif_Sent_At')->nullable();
            $table->integer('Email_Verif_Attempt')->default(0);
            $table->dateTime('Created_At')->nullable();
            $table->string('Created_By')->nullable();
            $table->dateTime('Updated_At')->nullable();
            $table->string('Updated_By')->nullable();
        });

        Schema::create('N_WEB_CAREERS_Klasifikasi_Akun', function (Blueprint $table) {
            $table->increments('Id_Klasifikasi');
            $table->string('Kode');
            $table->string('Is_Default_Register');
            $table->string('Flag_Aktif');
            $table->integer('Durasi_Hari')->nullable();
        });

        DB::table('N_WEB_CAREERS_Klasifikasi_Akun')->insert([
            'Kode' => 'PERMANEN',
            'Is_Default_Register' => 'Y',
            'Flag_Aktif' => 'Y',
            'Durasi_Hari' => null,
        ]);
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('N_WEB_CAREERS_Klasifikasi_Akun');
        Schema::dropIfExists('N_WEB_CAREERS_Users');

        parent::tearDown();
    }

    public function test_registrasi_local_langsung_terverifikasi_tanpa_mengirim_email(): void
    {
        $response = $this->postJson('/api/v1/register', [
            'nama' => 'Kandidat Development',
            'email' => 'dev@example.test',
            'phone' => '6281234567890',
            'nik' => '1234567890123456',
            'password' => 'rahasia123',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('result.perlu_verifikasi', false);

        $user = DB::table('N_WEB_CAREERS_Users')->where('Email', 'dev@example.test')->first();

        $this->assertSame('Y', $user->Flag_Email_Verified);
        $this->assertNotNull($user->Email_Verified_At);
        $this->assertNull($user->Email_Verif_Token);

        // Akun baru dikabarkan ke zona dalam (tanpa sandi); tidak ada surel
        // verifikasi yang diminta karena akunnya sudah terverifikasi otomatis.
        $terdaftar = Outbox::tercatat(Outbox::AKUN_TERDAFTAR);
        $this->assertCount(1, $terdaftar);
        $this->assertSame('dev@example.test', $terdaftar[0]['muatan']['akun']['email']);
        $this->assertArrayNotHasKey('password', $terdaftar[0]['muatan']['akun']);
        $this->assertTrue($terdaftar[0]['muatan']['akun']['email_terverifikasi']);
        $this->assertCount(0, Outbox::tercatat(Outbox::AKUN_KODE_DIMINTA));
    }

    public function test_auto_verifikasi_tetap_nonaktif_di_production(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        $method = new ReflectionMethod(AuthController::class, 'autoVerifikasiEmailAktif');

        $this->assertFalse($method->invoke(new AuthController));
    }
}
