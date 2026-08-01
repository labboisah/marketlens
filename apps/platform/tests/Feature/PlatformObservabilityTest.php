<?php

use App\Domain\Legal\Services\LegalDocumentService;
use App\Domain\Observability\Services\PlatformHealthService;
use App\Enums\AccountStatus;
use App\Models\User;
use App\Support\Observability\SensitiveDataSanitizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

function observabilitySuperAdmin(): User
{
    $user = User::factory()->create([
        'account_status' => AccountStatus::Active,
        'email_verified_at' => now(),
        'is_super_admin' => true,
        'terms_accepted_at' => now(),
        'privacy_accepted_at' => now(),
    ]);

    $legal = app(LegalDocumentService::class);
    $legal->acceptVersions($user, $legal->requiredVersionsFor($user)->pluck('id')->all());

    return $user;
}

function fakeHealthService(string $status = 'healthy'): PlatformHealthService
{
    return new class($status) extends PlatformHealthService {
        public function __construct(private readonly string $status) {}

        public function snapshot(): array
        {
            return [
                'status' => $this->status,
                'checked_at' => now()->toIso8601String(),
                'checks' => [
                    'database' => ['status' => 'healthy', 'message' => 'Database responded.', 'metrics' => ['connection' => 'sqlite', 'latency_ms' => 1]],
                    'redis' => ['status' => 'healthy', 'message' => 'Redis responded.', 'metrics' => ['connection' => 'default', 'latency_ms' => 1]],
                    'security_event_logging' => ['status' => 'healthy', 'message' => 'Security logs configured.', 'metrics' => ['channel' => 'security']],
                ],
            ];
        }

        public function publicReadiness(): array
        {
            return [
                'status' => $this->status,
                'checked_at' => now()->toIso8601String(),
                'components' => ['database' => 'healthy', 'redis' => 'healthy'],
            ];
        }

        public function liveness(): array
        {
            return ['status' => 'healthy', 'checked_at' => now()->toIso8601String()];
        }
    };
}

it('adds trace ids to infrastructure responses and records api latency samples', function (): void {
    Cache::forget(config('observability.api_latency_key'));

    $this->withHeader('X-Trace-Id', 'trace-test-123')
        ->get('/health/live')
        ->assertOk()
        ->assertHeader('X-Trace-Id', 'trace-test-123')
        ->assertJsonPath('status', 'healthy');

    $this->withHeader('Accept', 'application/json')
        ->get('/api/v1/auth/user')
        ->assertHeader('X-Trace-Id');

    expect(Cache::get(config('observability.api_latency_key')))
        ->toHaveKey('count')
        ->and(Cache::get(config('observability.api_latency_key'))['count'])->toBeGreaterThanOrEqual(1);
});

it('exposes safe liveness and readiness payloads for infrastructure monitoring', function (): void {
    config([
        'database.connections.pgsql.password' => 'super-secret-db-password',
        'database.redis.default.password' => 'super-secret-redis-password',
    ]);

    app()->instance(PlatformHealthService::class, fakeHealthService());

    $this->get(route('health.ready'))
        ->assertOk()
        ->assertJsonPath('status', 'healthy')
        ->assertJsonMissing(['super-secret-db-password'])
        ->assertJsonMissing(['super-secret-redis-password'])
        ->assertJsonMissing(['authorization' => 'Bearer raw-token']);
});

it('restricts the system health page to super admins and renders safe component status', function (): void {
    app()->instance(PlatformHealthService::class, fakeHealthService('degraded'));
    $owner = User::factory()->create(['account_status' => AccountStatus::Active, 'email_verified_at' => now()]);
    $superAdmin = observabilitySuperAdmin();

    $this->actingAs($owner)
        ->get(route('super-admin.system-health'))
        ->assertRedirect(route('legal.acceptance'));

    $this->actingAs($superAdmin)
        ->get(route('super-admin.system-health'))
        ->assertOk()
        ->assertSee('System Health')
        ->assertSee('Platform observability')
        ->assertSee('Database')
        ->assertSee('Security Event Logging')
        ->assertDontSee('super-secret')
        ->assertDontSee('Authorization')
        ->assertDontSee('raw-token')
        ->assertDontSee('device-secret');
});

it('redacts credentials and raw authorization metadata before logging', function (): void {
    $sanitized = app(SensitiveDataSanitizer::class)->sanitize([
        'authorization' => 'Bearer raw-token',
        'headers' => ['cookie' => 'session=value'],
        'device_credential' => 'device-secret',
        'database_password' => 'db-secret',
        'safe_count' => 3,
    ]);

    expect($sanitized['authorization'])->toBe('[redacted]')
        ->and($sanitized['headers']['cookie'])->toBe('[redacted]')
        ->and($sanitized['device_credential'])->toBe('[redacted]')
        ->and($sanitized['database_password'])->toBe('[redacted]')
        ->and($sanitized['safe_count'])->toBe(3);
});

