<?php

namespace Tests\Unit\Services;

use App\Services\Homepage\HomepageLmsService;
use App\Services\LMS\TrainingExecutionService;
use App\Services\LMS\TrainingScheduleQueryService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Mockery;
use Tests\TestCase;

class HomepageLmsServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-04-29 09:00:00', 'Asia/Jakarta'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        Mockery::close();

        parent::tearDown();
    }

    public function test_get_summary_uses_active_schedules_and_execution_enrichment(): void
    {
        $scheduleService = Mockery::mock(TrainingScheduleQueryService::class);
        $scheduleService->shouldReceive('getSchedulesByKodeKaryawans')
            ->once()
            ->with(['EMP001'], true)
            ->andReturn(collect([
                (object) [
                    'Kode_Karyawan' => 'EMP001',
                    'Id_Penjadwalan' => 10,
                    'Id_Master_Paket' => 3,
                    'Flag_Selesai' => 'Y',
                    'Flag_Cabut_Akses' => null,
                    'Waktu_Enroll' => '2026-04-10 08:00:00',
                    'Nama_Paket' => 'Training Safety',
                ],
                (object) [
                    'Kode_Karyawan' => 'EMP001',
                    'Id_Penjadwalan' => 11,
                    'Id_Master_Paket' => 4,
                    'Flag_Selesai' => 'T',
                    'Flag_Cabut_Akses' => null,
                    'Waktu_Enroll' => '2026-04-20 08:00:00',
                    'Nama_Paket' => 'Training SOP',
                ],
            ]));
        $scheduleService->shouldReceive('getSchedulesByKodeKaryawans')
            ->with(['EMP001'], false)
            ->andReturn(collect());
        $scheduleService->shouldReceive('getProgressSnapshotsByPenjadwalanIds')
            ->once()
            ->with([10, 11])
            ->andReturn(collect([
                10 => (object) ['Id_Penjadwalan' => 10, 'total_items' => 4, 'completed_items' => 4],
                11 => (object) ['Id_Penjadwalan' => 11, 'total_items' => 4, 'completed_items' => 1],
            ]));

        $executionService = Mockery::mock(TrainingExecutionService::class);
        $executionService->shouldReceive('getExecutionSummariesByPenjadwalanIds')
            ->once()
            ->with([10, 11])
            ->andReturn(collect([
                10 => (object) [
                    'Id_Penjadwalan' => 10,
                    'Total_Score' => 91.5,
                    'Score_Learning_Gain' => 4.2,
                ],
            ]));

        $service = new HomepageLmsService($scheduleService, $executionService);
        $summary = $service->getSummary('EMP001');

        $this->assertTrue($summary['has_data']);
        $this->assertSame(2, $summary['enrolled_courses']);
        $this->assertSame(1, $summary['completed_courses']);
        $this->assertSame(100, $summary['trainings'][0]['status'] === 'complete' ? 100 : 0);
        $this->assertSame(91.5, $summary['average_score']);
        $this->assertSame(4.2, $summary['learning_gain']);
        $this->assertSame('Training SOP', $summary['next_session']);
        $this->assertCount(2, $summary['learning_progress']);
        $this->assertCount(12, $summary['completed_monthly']);
    }

    public function test_get_summary_falls_back_to_inactive_schedules_when_active_empty(): void
    {
        $scheduleService = Mockery::mock(TrainingScheduleQueryService::class);
        $scheduleService->shouldReceive('getSchedulesByKodeKaryawans')
            ->once()
            ->with(['EMP001'], true)
            ->andReturn(collect());
        $scheduleService->shouldReceive('getSchedulesByKodeKaryawans')
            ->once()
            ->with(['EMP001'], false)
            ->andReturn(collect([
                (object) [
                    'Kode_Karyawan' => 'EMP001',
                    'Id_Penjadwalan' => 99,
                    'Id_Master_Paket' => null,
                    'Flag_Selesai' => 'T',
                    'Flag_Cabut_Akses' => 'Y',
                    'Waktu_Enroll' => '2026-04-15 08:00:00',
                    'Nama_Paket' => null,
                ],
            ]));
        $scheduleService->shouldReceive('getProgressSnapshotsByPenjadwalanIds')
            ->once()
            ->with([99])
            ->andReturn(collect([
                99 => (object) ['Id_Penjadwalan' => 99, 'total_items' => 2, 'completed_items' => 1],
            ]));

        $executionService = Mockery::mock(TrainingExecutionService::class);
        $executionService->shouldReceive('getExecutionSummariesByPenjadwalanIds')
            ->once()
            ->with([99])
            ->andReturn(collect());

        $service = new HomepageLmsService($scheduleService, $executionService);
        $summary = $service->getSummary('EMP001');

        $this->assertTrue($summary['has_data']);
        $this->assertSame(1, $summary['enrolled_courses']);
        $this->assertSame('Training', $summary['trainings'][0]['title']);
        $this->assertSame('Training', $summary['next_session']);
    }
}
