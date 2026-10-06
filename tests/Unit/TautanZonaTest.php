<?php

namespace Tests\Unit;

use App\Support\Portal\TautanFeedback;
use App\Support\Sinkron\PenjagaZona;
use App\Support\Sinkron\RahasiaSinkron;
use Illuminate\Encryption\Encrypter;
use RuntimeException;
use Tests\TestCase;

/** Tautan bertanda tangan, pembungkus rahasia, dan penjaga zona. */
class TautanZonaTest extends TestCase
{
    /** @return array{0: string, 1: string, 2: string} [kode, hashids, signature] dari sebuah tautan */
    private static function pecah(string $url): array
    {
        [, $jalur] = explode('/feedback/', $url);

        return explode('/', $jalur);
    }

    public function test_tautan_feedback_bolak_balik(): void
    {
        config(['sinkron.kunci_tautan' => 'kunci-uji-tautan']);

        [$kode, $hash, $tanda] = self::pecah(TautanFeedback::url(41, 'LMR-AB12CD34'));

        $this->assertSame('LMR-AB12CD34', $kode);
        $this->assertSame([41, 'LMR-AB12CD34'], TautanFeedback::urai($kode, $hash, $tanda));
    }

    public function test_tautan_feedback_yang_disunting_ditolak(): void
    {
        config(['sinkron.kunci_tautan' => 'kunci-uji-tautan']);
        [$kode, $hash, $tanda] = self::pecah(TautanFeedback::url(41, 'LMR-AB12CD34'));

        $this->assertNull(TautanFeedback::urai($kode, $hash, str_repeat('0', 32)));
        $this->assertNull(TautanFeedback::urai('LMR-ZZ99ZZ99', $hash, $tanda), 'tanda tangan terikat kode lamarannya');
        [, $hashLain] = self::pecah(TautanFeedback::url(42, 'LMR-AB12CD34'));
        $this->assertNull(TautanFeedback::urai($kode, $hashLain, $tanda), 'tanda tangan terikat id feedback-nya');
    }

    public function test_tanpa_kunci_tautan_semua_ditolak(): void
    {
        config(['sinkron.kunci_tautan' => 'kunci-uji-tautan']);
        [$kode, $hash, $tanda] = self::pecah(TautanFeedback::url(41, 'LMR-AB12CD34'));

        config(['sinkron.kunci_tautan' => '']);
        $this->assertNull(TautanFeedback::urai($kode, $hash, $tanda));
    }

    public function test_rahasia_hanya_terbuka_dengan_kunci_yang_sama(): void
    {
        $kunci = random_bytes(32);
        config(['sinkron.kunci_rahasia' => 'base64:'.base64_encode($kunci)]);

        $bungkus = RahasiaSinkron::bungkus(['otp' => '123456']);

        $this->assertStringNotContainsString('123456', $bungkus);
        $this->assertSame(['otp' => '123456'], json_decode((new Encrypter($kunci, 'AES-256-CBC'))->decryptString($bungkus), true));
    }

    public function test_rahasia_menolak_bila_kunci_belum_diatur(): void
    {
        config(['sinkron.kunci_rahasia' => null]);

        $this->expectException(RuntimeException::class);
        RahasiaSinkron::bungkus(['token' => 'x']);
    }

    public function test_penjaga_zona_menolak_database_admin(): void
    {
        config([
            'sinkron.database_terlarang' => ['Web_HRIS'],
            'database.connections.selundupan' => ['driver' => 'sqlsrv', 'database' => 'web_hris'],
        ]);

        $this->expectException(RuntimeException::class);
        PenjagaZona::periksa();
    }

    public function test_penjaga_zona_meloloskan_database_publik(): void
    {
        config([
            'sinkron.database_terlarang' => ['Web_HRIS'],
            'database.connections' => ['sqlsrv' => ['driver' => 'sqlsrv', 'database' => 'emi_tm_demo']],
        ]);

        PenjagaZona::periksa();
        $this->assertTrue(true);
    }
}
