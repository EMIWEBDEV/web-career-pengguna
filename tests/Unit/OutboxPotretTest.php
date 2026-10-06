<?php

namespace Tests\Unit;

use App\Support\Portal\Potret;
use App\Support\Sinkron\Outbox;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

/** Penulis Outbox (mode palsu) dan pembaca potret portal. */
class OutboxPotretTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);
        Outbox::palsukan();

        Schema::create(Potret::TABEL, function (Blueprint $t) {
            $t->string('Kode_Lamaran')->primary();
            $t->integer('Id_Users');
            $t->bigInteger('Versi');
            $t->text('Muatan');
            $t->dateTime('Diperbarui_At')->nullable();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists(Potret::TABEL);
        parent::tearDown();
    }

    public function test_peristiwa_wajib_di_dalam_transaksi(): void
    {
        $this->expectException(RuntimeException::class);
        Outbox::tulis(Outbox::LAMARAN_DIBATALKAN, 'Lamaran.Dibatalkan:LMR-X', 5, ['kode' => 'LMR-X']);
    }

    public function test_aksi_yang_sama_tercatat_sekali_dan_berurut_per_akun(): void
    {
        $a = DB::transaction(fn () => Outbox::tulis(Outbox::LAMARAN_DIKIRIM, 'Lamaran.Dikirim:LMR-1', 9, ['kode' => 'LMR-1']));
        $b = DB::transaction(fn () => Outbox::tulis(Outbox::LAMARAN_DIKIRIM, 'Lamaran.Dikirim:LMR-1', 9, ['kode' => 'LMR-1']));

        $this->assertSame('BARU', $a['hasil']);
        $this->assertSame('DUPLIKAT', $b['hasil']);
        $this->assertSame($a['eventId'], $b['eventId']);

        $e = Outbox::tercatat(Outbox::LAMARAN_DIKIRIM);
        $this->assertCount(1, $e);
        $this->assertSame('akun:9', $e[0]['kunciUrut']);
        $this->assertSame(1, $e[0]['muatan']['kontrak']);
        $this->assertArrayHasKey('terjadi_at', $e[0]['muatan']);
        $this->assertSame('MENUNGGU', Outbox::status('Lamaran.Dikirim:LMR-1')->Status);
    }

    public function test_potret_hanya_untuk_pemiliknya_dan_kontrak_yang_dikenal(): void
    {
        DB::table(Potret::TABEL)->insert([
            ['Kode_Lamaran' => 'LMR-A1', 'Id_Users' => 3, 'Versi' => 1, 'Muatan' => json_encode([
                'kontrak' => 1,
                'aktivitas' => ['77' => ['label' => 'Tes Menggambar', 'terbuka' => true]],
            ])],
            ['Kode_Lamaran' => 'LMR-A2', 'Id_Users' => 3, 'Versi' => 1, 'Muatan' => json_encode(['kontrak' => 99])],
        ]);

        $this->assertNotNull(Potret::lamaran('LMR-A1', 3));
        $this->assertNull(Potret::lamaran('LMR-A1', 4), 'bukan pemiliknya');
        $this->assertNull(Potret::lamaran('LMR-A2', 3), 'kontrak tak dikenal = belum ada potret');

        $temu = Potret::cari(3, 'aktivitas', 77);
        $this->assertSame('LMR-A1', $temu['kode']);
        $this->assertSame('Tes Menggambar', $temu['isi']['label']);
        $this->assertNull(Potret::cari(4, 'aktivitas', 77), 'id aktivitas orang lain tidak bisa dipakai');
    }
}
