<?php

namespace Tests\Feature\Controllers\Career;

use App\Http\Controllers\Career\ProgramKegiatan\ProgramKegiatanController;
use Illuminate\Http\Request;
use Tests\TestCase;

class ProgramKegiatanDivisiGuardFlagTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Isolate from whatever real DB connection happens to be configured
        // in .env (this repo has no dedicated testing DB) — point the
        // default connection at an empty in-memory sqlite DB so tests never
        // depend on, or are confounded by, real production-like data.
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);
    }

    public function test_cek_info_divisi_skips_db_and_returns_lengkap_when_flag_disabled(): void
    {
        config(['career_divisi_guard.enabled' => false]);

        // No DB connection/tables are configured for HRIS_Transaksi_GForm,
        // HRIS_Divisi, N_WEB_CAREERS_Division_Informations, etc. If the
        // controller queried the DB despite the flag being off, this call
        // would throw a query/connection exception instead of returning.
        $request = Request::create('/program-kegiatan/cek-info-divisi', 'POST', [
            'mppRefs' => ['MPP-0001', 'MPP-0002'],
        ]);

        $response = (new ProgramKegiatanController)->cekInfoDivisi($request);

        $payload = json_decode($response->getContent(), true);

        $this->assertTrue($payload['success']);
        $this->assertSame([], $payload['result']['belumLengkap']);
        $this->assertSame('Cek info divisi (nonaktif)', $payload['message']);
    }

    public function test_cek_info_divisi_does_not_short_circuit_when_flag_enabled(): void
    {
        config(['career_divisi_guard.enabled' => true]);

        // No DB connection/tables are configured for HRIS_Transaksi_GForm,
        // HRIS_Divisi, N_WEB_CAREERS_Division_Informations, etc. With the
        // flag on, the new short-circuit must not fire, so the controller
        // falls through to its pre-existing DB logic, which throws and is
        // swallowed by its own exception-fallback path.
        $request = Request::create('/program-kegiatan/cek-info-divisi', 'POST', [
            'mppRefs' => ['MPP-0001', 'MPP-0002'],
        ]);

        $response = (new ProgramKegiatanController)->cekInfoDivisi($request);

        $payload = json_decode($response->getContent(), true);

        $this->assertNotSame('Cek info divisi (nonaktif)', $payload['message']);
        $this->assertSame('Cek info divisi (fallback)', $payload['message']);
    }
}
