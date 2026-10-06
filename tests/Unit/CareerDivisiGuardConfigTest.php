<?php

namespace Tests\Unit;

use Tests\TestCase;

class CareerDivisiGuardConfigTest extends TestCase
{
    public function test_config_is_wired_to_the_env_flag(): void
    {
        $this->assertSame((bool) env('CAREER_DIVISI_INFO_ENABLED', true), config('career_divisi_guard.enabled'));
        $this->assertIsBool(config('career_divisi_guard.enabled'));
    }

    public function test_config_reads_env_override(): void
    {
        config(['career_divisi_guard.enabled' => false]);

        $this->assertFalse(config('career_divisi_guard.enabled'));
    }
}
