<?php

namespace Tests\Feature\Controllers\Auth;

use App\Http\Controllers\Auth\AuthController;
use App\Http\Middleware\VerifyCsrfToken;
use App\Jobs\Career\WcSyncEmailJob;
use App\Services\WebCareers\HclClient;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery\MockInterface;
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
        Bus::fake();

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
            $table->string('Kode_Calon')->nullable();
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

        $this->mock(HclClient::class, function (MockInterface $mock) {
            $mock->shouldReceive('post')->zeroOrMoreTimes()->andReturn([
                'sukses' => true,
                'status' => 201,
                'message' => 'Berhasil',
                'result' => ['Kode_Calon' => 'CK-TEST-001'],
            ]);
        });
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
        $this->assertSame('CK-TEST-001', $user->Kode_Calon);
        Bus::assertNotDispatched(WcSyncEmailJob::class);
    }

    public function test_auto_verifikasi_tetap_nonaktif_di_production(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        $method = new ReflectionMethod(AuthController::class, 'autoVerifikasiEmailAktif');

        $this->assertFalse($method->invoke(new AuthController));
    }
}
