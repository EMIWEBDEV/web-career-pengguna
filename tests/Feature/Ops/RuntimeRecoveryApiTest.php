<?php

namespace Tests\Feature\Ops;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

class RuntimeRecoveryApiTest extends TestCase
{
    private const OPS_TOKEN = 'ops-token-for-test';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.project_config.OPS_API_BEARER_TOKEN', self::OPS_TOKEN);
    }

    public function test_db_health_returns_403_without_bearer_token(): void
    {
        $response = $this->getJson('/api/ops/runtime/db-health');

        $response->assertStatus(403)->assertJson([
            'status' => 'forbidden',
            'message' => 'Unauthorized',
        ]);
    }

    public function test_db_health_returns_403_with_invalid_bearer_token(): void
    {
        $response = $this
            ->withHeaders($this->authHeaders('invalid-token'))
            ->getJson('/api/ops/runtime/db-health');

        $response->assertStatus(403)->assertJson([
            'status' => 'forbidden',
            'message' => 'Unauthorized',
        ]);
    }

    public function test_db_health_returns_ok_for_healthy_connection(): void
    {
        $connection = Mockery::mock();
        $connection->shouldReceive('getPdo')->once()->andReturn(new \stdClass());

        DB::shouldReceive('getDefaultConnection')->once()->andReturn('sqlsrv');
        DB::shouldReceive('connection')->once()->with('sqlsrv')->andReturn($connection);

        $response = $this
            ->withHeaders($this->authHeaders())
            ->getJson('/api/ops/runtime/db-health');

        $response->assertStatus(200)->assertJson([
            'status' => 'ok',
            'connection' => 'sqlsrv',
        ]);
    }

    public function test_db_health_returns_503_when_connection_fails(): void
    {
        $connection = Mockery::mock();
        $connection->shouldReceive('getPdo')->once()->andThrow(new \RuntimeException('Login timeout expired'));

        DB::shouldReceive('getDefaultConnection')->once()->andReturn('sqlsrv');
        DB::shouldReceive('connection')->once()->with('sqlsrv')->andReturn($connection);

        $response = $this
            ->withHeaders($this->authHeaders())
            ->getJson('/api/ops/runtime/db-health');

        $response->assertStatus(503)->assertJson([
            'status' => 'error',
            'connection' => 'sqlsrv',
        ]);
    }

    public function test_db_reconnect_returns_ok_after_purge_and_reconnect(): void
    {
        $connection = Mockery::mock();
        $connection->shouldReceive('getPdo')->once()->andReturn(new \stdClass());

        DB::shouldReceive('getDefaultConnection')->once()->andReturn('sqlsrv');
        DB::shouldReceive('purge')->once()->with('sqlsrv');
        DB::shouldReceive('reconnect')->once()->with('sqlsrv')->andReturn($connection);

        $response = $this
            ->withHeaders($this->authHeaders())
            ->postJson('/api/ops/runtime/db-reconnect');

        $response->assertStatus(200)->assertJson([
            'status' => 'ok',
            'connection' => 'sqlsrv',
        ]);
    }

    public function test_laravel_refresh_calls_optimize_clear(): void
    {
        Artisan::shouldReceive('call')->once()->with('optimize:clear')->andReturn(0);
        Artisan::shouldReceive('output')->once()->andReturn('Caches cleared successfully.');

        $response = $this
            ->withHeaders($this->authHeaders())
            ->postJson('/api/ops/runtime/laravel-refresh');

        $response->assertStatus(200)->assertJson([
            'status' => 'ok',
            'command' => 'optimize:clear',
        ]);
    }

    private function authHeaders(?string $token = null): array
    {
        return [
            'Authorization' => 'Bearer ' . ($token ?? self::OPS_TOKEN),
        ];
    }
}
