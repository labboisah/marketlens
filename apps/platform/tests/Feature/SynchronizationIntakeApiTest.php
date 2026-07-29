<?php

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Billing\Models\Plan;
use App\Domain\Billing\Models\Subscription;
use App\Domain\Devices\Models\Device;
use App\Domain\Devices\Services\ShopAppActivationService;
use App\Domain\Synchronization\Jobs\ProcessSyncBatch;
use App\Domain\Synchronization\Models\SyncBatch;
use App\Domain\Synchronization\Models\SyncRecord;
use App\Domain\Tenancy\Models\Branch;
use App\Domain\Tenancy\Models\Business;
use App\Enums\BranchStatus;
use App\Enums\BusinessStatus;
use App\Enums\DeviceStatus;
use App\Enums\SubscriptionStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

uses(RefreshDatabase::class);

function syncIntakeFixture(SubscriptionStatus $subscriptionStatus = SubscriptionStatus::Active, BranchStatus $branchStatus = BranchStatus::Active): array
{
    $user = User::factory()->create();
    $plan = Plan::query()->create([
        'name' => 'Sync Plan',
        'slug' => 'sync-plan',
        'billing_interval' => 'monthly',
        'price_minor' => 10000,
        'currency' => 'NGN',
        'trial_days' => 14,
        'sort_order' => 1,
        'is_active' => true,
    ]);
    $plan->features()->create(['name' => 'More Client App devices']);

    $business = Business::query()->create(['name' => 'Sync Stores', 'status' => BusinessStatus::Active]);
    $branch = Branch::query()->create([
        'business_id' => $business->id,
        'name' => 'Main Branch',
        'code' => 'MAIN',
        'status' => $branchStatus,
    ]);

    Subscription::query()->create([
        'business_id' => $business->id,
        'plan_id' => $plan->id,
        'status' => $subscriptionStatus,
        'starts_at' => now(),
        'ends_at' => now()->addDays(14),
    ]);

    $activation = app(ShopAppActivationService::class)->createActivation($business, $branch, $user, [
        'device_label' => 'Main Shop App',
        'device_type' => 'shop_app',
        'expires_at' => now()->addMinutes(30),
        'application_version_id' => null,
    ]);

    $credential = app(ShopAppActivationService::class)->activate([
        'activation_code' => $activation['code'],
        'device_identity' => 'sync-machine',
        'app_version' => '1.0.0',
    ])['credential'];

    return [$business->refresh(), $branch->refresh(), Device::query()->where('business_id', $business->id)->firstOrFail(), $credential];
}

function syncEnvelope(Business $business, Branch $branch, Device $device, array $records = [], array $overrides = []): array
{
    return [
        'protocol_version' => '1.0',
        'batch_uuid' => (string) Str::uuid(),
        'business_uuid' => $business->uuid,
        'branch_uuid' => $branch->uuid,
        'device_uuid' => $device->uuid,
        'created_at' => now()->toISOString(),
        'records' => $records ?: [syncRecord('sale')],
        ...$overrides,
    ];
}

function syncRecord(string $entity, array $overrides = []): array
{
    $recordUuid = (string) Str::uuid();

    return [
        'entity' => $entity,
        'record_uuid' => $recordUuid,
        'operation' => 'create',
        'version' => 1,
        'idempotency_key' => 'idem-'.$recordUuid,
        'occurred_at' => now()->toISOString(),
        'payload' => ['record_uuid' => $recordUuid, 'amount_minor' => 1000],
        ...$overrides,
    ];
}

function signedSyncRequest(TestCase $test, string $method, string $uri, string $credential, string $body = '', array $headers = []): TestResponse
{
    $timestamp = now()->toISOString();
    $signature = hash_hmac('sha256', $timestamp.'.'.$body, $credential);

    return $test->call($method, $uri, [], [], [], [
        'HTTP_ACCEPT' => 'application/json',
        'CONTENT_TYPE' => 'application/json',
        'HTTP_AUTHORIZATION' => 'Bearer '.$credential,
        'HTTP_X_ML_TIMESTAMP' => $timestamp,
        'HTTP_X_ML_SIGNATURE' => $signature,
        'HTTP_X_ML_TRACE_ID' => (string) Str::uuid(),
        ...$headers,
    ], $body);
}

it('accepts a valid signed batch and dispatches processing to the sync queue', function (): void {
    Queue::fake();
    [$business, $branch, $device, $credential] = syncIntakeFixture();
    $body = json_encode(syncEnvelope($business, $branch, $device), JSON_THROW_ON_ERROR);

    signedSyncRequest($this, 'POST', route('api.v1.sync.batches.store'), $credential, $body)
        ->assertOk()
        ->assertJsonPath('data.status', 'accepted')
        ->assertJsonCount(1, 'data.accepted')
        ->assertJsonPath('data.rejected', []);

    expect(SyncBatch::query()->count())->toBe(1)
        ->and(SyncRecord::query()->where('entity', 'sale')->where('status', 'accepted')->exists())->toBeTrue();

    Queue::assertPushed(ProcessSyncBatch::class);
});

it('returns the same receipt for duplicate batches without duplicating records', function (): void {
    Queue::fake();
    [$business, $branch, $device, $credential] = syncIntakeFixture();
    $body = json_encode(syncEnvelope($business, $branch, $device), JSON_THROW_ON_ERROR);

    signedSyncRequest($this, 'POST', route('api.v1.sync.batches.store'), $credential, $body)->assertOk();
    signedSyncRequest($this, 'POST', route('api.v1.sync.batches.store'), $credential, $body)
        ->assertOk()
        ->assertJsonPath('data.status', 'accepted');

    expect(SyncBatch::query()->count())->toBe(1)
        ->and(SyncRecord::query()->count())->toBe(1);
});

it('rejects invalid signatures and audits the failure', function (): void {
    [$business, $branch, $device, $credential] = syncIntakeFixture();
    $body = json_encode(syncEnvelope($business, $branch, $device), JSON_THROW_ON_ERROR);
    $timestamp = now()->toISOString();

    $this->call('POST', route('api.v1.sync.batches.store'), [], [], [], [
        'HTTP_ACCEPT' => 'application/json',
        'HTTP_AUTHORIZATION' => 'Bearer '.$credential,
        'HTTP_X_ML_TIMESTAMP' => $timestamp,
        'HTTP_X_ML_SIGNATURE' => 'bad-signature',
    ], $body)->assertForbidden();

    expect(AuditLog::query()->where('action', 'sync.authentication_failed')->exists())->toBeTrue();
});

it('rejects revoked devices', function (): void {
    [$business, $branch, $device, $credential] = syncIntakeFixture();
    $device->forceFill(['status' => DeviceStatus::Revoked, 'revoked_at' => now()])->save();
    $body = json_encode(syncEnvelope($business, $branch, $device), JSON_THROW_ON_ERROR);

    signedSyncRequest($this, 'POST', route('api.v1.sync.batches.store'), $credential, $body)
        ->assertUnauthorized();
});

it('rejects expired or inactive subscriptions', function (): void {
    [$business, $branch, $device, $credential] = syncIntakeFixture();
    Subscription::query()->where('business_id', $business->id)->update(['status' => SubscriptionStatus::Cancelled]);
    $body = json_encode(syncEnvelope($business, $branch, $device), JSON_THROW_ON_ERROR);

    signedSyncRequest($this, 'POST', route('api.v1.sync.batches.store'), $credential, $body)
        ->assertForbidden();
});

it('rejects wrong business and wrong branch scopes', function (): void {
    [$business, $branch, $device, $credential] = syncIntakeFixture();
    $otherBusiness = Business::query()->create(['name' => 'Other Stores', 'status' => BusinessStatus::Active]);
    $otherBranch = Branch::query()->create([
        'business_id' => $business->id,
        'name' => 'Other Branch',
        'status' => BranchStatus::Active,
    ]);

    signedSyncRequest($this, 'POST', route('api.v1.sync.batches.store'), $credential, json_encode(syncEnvelope($business, $branch, $device, overrides: [
        'business_uuid' => $otherBusiness->uuid,
    ]), JSON_THROW_ON_ERROR))->assertForbidden();

    signedSyncRequest($this, 'POST', route('api.v1.sync.batches.store'), $credential, json_encode(syncEnvelope($business, $branch, $device, overrides: [
        'branch_uuid' => $otherBranch->uuid,
    ]), JSON_THROW_ON_ERROR))->assertForbidden();
});

it('rejects unsupported protocol versions', function (): void {
    [$business, $branch, $device, $credential] = syncIntakeFixture();
    $body = json_encode(syncEnvelope($business, $branch, $device, overrides: ['protocol_version' => '2.0']), JSON_THROW_ON_ERROR);

    signedSyncRequest($this, 'POST', route('api.v1.sync.batches.store'), $credential, $body)
        ->assertUnprocessable();
});

it('supports partial success when one record is invalid', function (): void {
    Queue::fake();
    [$business, $branch, $device, $credential] = syncIntakeFixture();
    $records = [
        syncRecord('sale'),
        syncRecord('unknown_entity'),
    ];
    $body = json_encode(syncEnvelope($business, $branch, $device, $records), JSON_THROW_ON_ERROR);

    signedSyncRequest($this, 'POST', route('api.v1.sync.batches.store'), $credential, $body)
        ->assertOk()
        ->assertJsonPath('data.status', 'partial')
        ->assertJsonCount(1, 'data.accepted')
        ->assertJsonCount(1, 'data.rejected');
});

it('prevents idempotency key reuse with different payloads', function (): void {
    Queue::fake();
    [$business, $branch, $device, $credential] = syncIntakeFixture();
    $idempotencyKey = 'same-operation-key';

    signedSyncRequest($this, 'POST', route('api.v1.sync.batches.store'), $credential, json_encode(syncEnvelope($business, $branch, $device, [
        syncRecord('sale', ['idempotency_key' => $idempotencyKey, 'payload' => ['amount_minor' => 1000]]),
    ]), JSON_THROW_ON_ERROR))->assertOk();

    signedSyncRequest($this, 'POST', route('api.v1.sync.batches.store'), $credential, json_encode(syncEnvelope($business, $branch, $device, [
        syncRecord('sale', ['idempotency_key' => $idempotencyKey, 'payload' => ['amount_minor' => 2000]]),
    ]), JSON_THROW_ON_ERROR))
        ->assertOk()
        ->assertJsonPath('data.status', 'rejected')
        ->assertJsonPath('data.rejected.0.code', 'IDEMPOTENCY_CONFLICT');
});

it('accepts gzip compressed synchronization requests', function (): void {
    Queue::fake();
    [$business, $branch, $device, $credential] = syncIntakeFixture();
    $body = gzencode(json_encode(syncEnvelope($business, $branch, $device), JSON_THROW_ON_ERROR));

    signedSyncRequest($this, 'POST', route('api.v1.sync.batches.store'), $credential, $body, [
        'HTTP_CONTENT_ENCODING' => 'gzip',
    ])
        ->assertOk()
        ->assertJsonPath('data.status', 'accepted');
});

it('returns batch details acknowledgements and checkpoint for the device', function (): void {
    Queue::fake();
    [$business, $branch, $device, $credential] = syncIntakeFixture();
    $envelope = syncEnvelope($business, $branch, $device);
    $body = json_encode($envelope, JSON_THROW_ON_ERROR);

    signedSyncRequest($this, 'POST', route('api.v1.sync.batches.store'), $credential, $body)->assertOk();

    signedSyncRequest($this, 'GET', route('api.v1.sync.batches.show', ['batchUuid' => $envelope['batch_uuid']]), $credential)
        ->assertOk()
        ->assertJsonPath('data.batch_uuid', $envelope['batch_uuid']);

    signedSyncRequest($this, 'POST', route('api.v1.sync.acknowledgements.store'), $credential, json_encode([
        'batch_uuid' => $envelope['batch_uuid'],
        'accepted' => [[
            'record_uuid' => $envelope['records'][0]['record_uuid'],
            'idempotency_key' => $envelope['records'][0]['idempotency_key'],
        ]],
    ], JSON_THROW_ON_ERROR))
        ->assertOk()
        ->assertJsonPath('data.batch_uuid', $envelope['batch_uuid']);

    signedSyncRequest($this, 'GET', route('api.v1.sync.checkpoint.show'), $credential)
        ->assertOk()
        ->assertJsonPath('data.device_uuid', $device->uuid)
        ->assertJsonPath('data.accepted_records_count', 1);
});
