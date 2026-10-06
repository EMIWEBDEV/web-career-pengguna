<?php

namespace Tests\Unit;

use App\Http\Controllers\Career\Dashboard\DashboardController;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class DashboardCalendarRiskTest extends TestCase
{
    private DashboardController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->controller = new DashboardController;
        Carbon::setTestNow('2026-08-01 09:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function invoke(string $method, array $arguments = [])
    {
        $reflection = new ReflectionMethod($this->controller, $method);
        $reflection->setAccessible(true);

        return $reflection->invokeArgs($this->controller, $arguments);
    }

    public function test_only_overlapping_timed_tests_in_same_program_are_marked_as_conflicts(): void
    {
        $events = [
            ['jenis' => 'TES', 'programId' => 'A', 'mulai' => '2026-08-02 09:00:00', 'akhir' => '2026-08-02 11:00:00', 'konflik' => false],
            ['jenis' => 'TES', 'programId' => 'A', 'mulai' => '2026-08-02 10:30:00', 'akhir' => '2026-08-02 12:00:00', 'konflik' => false],
            ['jenis' => 'TES', 'programId' => 'B', 'mulai' => '2026-08-02 10:30:00', 'akhir' => '2026-08-02 12:00:00', 'konflik' => false],
            ['jenis' => 'AGENDA', 'programId' => 'A', 'mulai' => '2026-08-02', 'akhir' => '2026-08-03', 'konflik' => false],
        ];

        $arguments = [&$events];
        $pairs = $this->invoke('tandaiKonflik', $arguments);

        $this->assertSame(1, $pairs);
        $this->assertTrue($events[0]['konflik']);
        $this->assertTrue($events[1]['konflik']);
        $this->assertFalse($events[2]['konflik']);
        $this->assertFalse($events[3]['konflik']);
    }

    public function test_dense_days_and_operational_summary_are_counted(): void
    {
        $events = [
            ['jenis' => 'TES', 'mulai' => '2026-08-01 10:00:00', 'kesiapan' => 'PERLU_PERHATIAN', 'konflik' => true, 'hariPadat' => false],
            ['jenis' => 'AGENDA', 'mulai' => '2026-08-01', 'kesiapan' => null, 'konflik' => false, 'hariPadat' => false],
            ['jenis' => 'TUTUP', 'mulai' => '2026-08-01 23:59:00', 'program' => 'MT 2026', 'kesiapan' => null, 'konflik' => false, 'hariPadat' => false],
            ['jenis' => 'TES', 'mulai' => '2026-08-03 10:00:00', 'kesiapan' => 'SIAP', 'konflik' => false, 'hariPadat' => false],
        ];

        $arguments = [&$events];
        $this->invoke('tandaiHariPadat', $arguments);
        $summary = $this->invoke('ringkasanKalender', [$events]);

        $this->assertTrue($events[0]['hariPadat']);
        $this->assertTrue($events[1]['hariPadat']);
        $this->assertTrue($events[2]['hariPadat']);
        $this->assertFalse($events[3]['hariPadat']);
        $this->assertSame(3, $summary['hariIni']);
        $this->assertSame(1, $summary['perluPerhatian']);
        $this->assertSame(1, $summary['bentrok']);
        $this->assertSame(1, $summary['hariPadat']);
        $this->assertSame('MT 2026', $summary['deadlineTerdekat']['program']);
    }
}
