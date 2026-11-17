<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class StatusTest extends TestCase
{
    /** @return array<string, array{?string, ?string, string}> */
    public static function unauthorizedRequests(): array
    {
        return [
            'missing header' => ['test-status-key', null, ''],
            'incorrect key' => ['test-status-key', 'Bearer incorrect', ''],
            'key only in query' => ['test-status-key', null, '?key=test-status-key'],
            'basic authentication' => ['test-status-key', 'Basic test-status-key', ''],
            'unconfigured key' => [null, 'Bearer test-status-key', ''],
            'empty key' => ['', 'Bearer ', ''],
        ];
    }

    #[DataProvider('unauthorizedRequests')]
    public function test_returns_401_without_a_configured_matching_bearer_key(?string $key, ?string $header, string $query): void
    {
        config(['status.check_key' => $key]);
        if ($header !== null) {
            $this->withHeader('Authorization', $header);
        }

        $this->getJson('/api/status'.$query)
            ->assertUnauthorized()
            ->assertExactJson(['message' => 'Unauthorized.'])
            ->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_returns_200_with_private_health_results_and_removes_its_storage_probe(): void
    {
        config(['status.check_key' => 'test-status-key']);
        Storage::fake('local');
        $this->freezeTime();

        $this->withToken('test-status-key')->getJson('/api/status')
            ->assertOk()
            ->assertExactJson([
                'status' => 'ok',
                'checks' => ['application' => 'ok', 'database' => 'ok', 'storage' => 'ok'],
                'checked_at' => now()->toIso8601String(),
            ])
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertHeader('Vary', 'Authorization');

        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_returns_503_without_connection_details_when_the_database_is_unavailable(): void
    {
        config(['status.check_key' => 'test-status-key']);
        Storage::fake('local');
        config(['database.connections.sqlite.database' => Storage::disk('local')->path('absent.sqlite')]);
        DB::purge('sqlite');
        $this->freezeTime();

        $this->withToken('test-status-key')->getJson('/api/status')
            ->assertStatus(503)
            ->assertExactJson([
                'status' => 'degraded',
                'checks' => ['application' => 'ok', 'database' => 'error', 'storage' => 'ok'],
                'checked_at' => now()->toIso8601String(),
            ])
            ->assertHeader('Cache-Control', 'no-store, private');

        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_returns_503_without_paths_when_storage_is_unavailable(): void
    {
        config(['status.check_key' => 'test-status-key']);
        Storage::fake('local');
        Storage::disk('local')->put('blocked-root', 'not a directory');
        $root = Storage::disk('local')->path('blocked-root');
        config(['filesystems.disks.local.root' => $root]);
        Storage::forgetDisk('local');
        $this->freezeTime();

        $this->withToken('test-status-key')->getJson('/api/status')
            ->assertStatus(503)
            ->assertExactJson([
                'status' => 'degraded',
                'checks' => ['application' => 'ok', 'database' => 'ok', 'storage' => 'error'],
                'checked_at' => now()->toIso8601String(),
            ])
            ->assertHeader('Cache-Control', 'no-store, private');
    }
}
