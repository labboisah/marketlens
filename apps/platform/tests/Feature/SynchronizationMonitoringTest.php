<?php

use App\Domain\Billing\Models\Plan;
use App\Domain\Billing\Models\Subscription;
use App\Domain\Devices\Models\Device;
use App\Domain\Devices\Models\DeviceHeartbeatLog;
use App\Domain\Devices\Services\ShopAppActivationService;
use App\Domain\Synchronization\Jobs\ProcessSyncBatch;
use App\Domain\Synchronization\Models\SyncBatch;
use App\Domain\Synchronization\Models\SyncRecord;
use App\Domain\Synchronization\Services\SynchronizationMonitoringService;
use App\Domain\Tenancy\Models\Branch;
use App\Domain\Tenancy\Models\Business;
use App\Enums\AccountStatus;
use App\Enums\BranchStatus;
use App\Enums\BusinessStatus;
use App\Enums\DeviceStatus;
use App\Enums\SubscriptionStatus;
use App\Enums\SyncBatchStatus;
use App\Livewire\Platform\Synchronization\SynchronizationMonitoringPage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function syncMonitoringFixture(): array
{
    $user = User::factory()->create([
        'account_status' => AccountStatus::Active,
        'email_verified_at' => now(),
    ]);
    $plan = Plan::query()->create([
        'name' => 'Sync Plan',
        'slug' => 'sync-plan-'.Str::random(6),
        'billing_interval' => 'monthly',
        'price_minor' => 15000,
        'currency' => 'NGN',
        'trial_days' => 14,
        'sort_order' => 1,
        'is_active' => true,
    ]);
    foreach (['Sales', 'Inventory', 'Remote dashboard', 'More Client App devices'] as $index => $feature) {
        $plan->features()->create(['name' => $feature, 'sort_order' => $index + 1]);
    }

    $business = Business::query()->create([
        'name' => 'Sync Stores',
        'status' => BusinessStatus::Active,
        'onboarding_status' => 'completed',
    ]);
    $branch = Branch::query()->create([
        'business_id' => $business->id,
        'name' => 'Main Branch',
        'code' => 'MAIN',
        'status' => BranchStatus::Active,
        'is_head_office' => true,
        'last_synchronized_at' => now()->subMinutes(12),
    ]);
    $user->ownedBusinesses()->attach($business->id, ['role' => 'owner']);

    Subscription::query()->create([
        'business_id' => $business->id,
        'plan_id' => $plan->id,
        'status' => SubscriptionStatus::Active,
        'starts_at' => now(),
        'ends_at' => now()->addMonth(),
    ]);

    $device = Device::query()->create([
        'business_id' => $business->id,
        'branch_id' => $branch->id,
        'name' => 'Main Shop App',
        'type' => 'shop_app',
        'status' => DeviceStatus::Active,
        'app_version' => '1.0.0',
        'last_seen_at' => now()->subMinutes(2),
        'last_synchronized_at' => now()->subMinutes(12),
    ]);

    return [$user, $business, $branch, $device];
}

function syncMonitoringBatch(Business $business, Branch $branch, Device $device, SyncBatchStatus $status = SyncBatchStatus::Accepted, array $overrides = []): SyncBatch
{
    return SyncBatch::query()->create([
        'business_id' => $business->id,
        'branch_id' => $branch->id,
        'device_id' => $device->id,
        'uuid' => $overrides['uuid'] ?? (string) Str::uuid(),
        'protocol_version' => $overrides['protocol_version'] ?? '1.0',
        'status' => $status,
        'records_count' => $overrides['records_count'] ?? 1,
        'trace_id' => $overrides['trace_id'] ?? (string) Str::uuid(),
        'accepted_records' => $overrides['accepted_records'] ?? [],
        'rejected_records' => $overrides['rejected_records'] ?? [],
        'conflicting_records' => $overrides['conflicting_records'] ?? [],
        'processing_errors' => $overrides['processing_errors'] ?? [],
        'received_at' => $overrides['received_at'] ?? now()->subMinutes(5),
        'processed_at' => $overrides['processed_at'] ?? now(),
    ]);
}

function syncMonitoringRecord(Business $business, Branch $branch, Device $device, SyncBatch $batch, string $status = 'accepted', array $overrides = []): SyncRecord
{
    $recordUuid = $overrides['record_uuid'] ?? (string) Str::uuid();

    return SyncRecord::query()->create([
        'sync_batch_id' => $batch->id,
        'business_id' => $business->id,
        'branch_id' => $branch->id,
        'device_id' => $device->id,
        'record_uuid' => $recordUuid,
        'entity' => $overrides['entity'] ?? 'sale',
        'operation' => $overrides['operation'] ?? 'create',
        'version' => $overrides['version'] ?? 1,
        'idempotency_key' => $overrides['idempotency_key'] ?? 'sync-record-'.$recordUuid,
        'payload_hash' => hash('sha256', $recordUuid),
        'status' => $status,
        'error_code' => $overrides['error_code'] ?? null,
        'error_message' => $overrides['error_message'] ?? null,
        'retryable' => $overrides['retryable'] ?? false,
        'payload' => $overrides['payload'] ?? ['redacted_test_value' => true],
        'occurred_at' => $overrides['occurred_at'] ?? now(),
    ]);
}

it('renders synchronization dashboard metrics and sidebar link', function (): void {
    [$user, $business, $branch, $device] = syncMonitoringFixture();
    syncMonitoringBatch($business, $branch, $device, SyncBatchStatus::Accepted);
    syncMonitoringBatch($business, $branch, $device, SyncBatchStatus::Failed);
    $conflictBatch = syncMonitoringBatch($business, $branch, $device, SyncBatchStatus::Partial);
    syncMonitoringRecord($business, $branch, $device, $conflictBatch, 'conflict', [
        'error_code' => 'PRODUCT_MASTER_CONFLICT',
        'error_message' => 'Product master data requires review.',
    ]);

    $this->actingAs($user)
        ->withSession(['platform.business_id' => $business->id, 'platform.branch_id' => $branch->id])
        ->get(route('platform.synchronization'))
        ->assertOk()
        ->assertSee('Synchronization monitoring')
        ->assertSee('Active Shop App devices')
        ->assertSee('Failed batches')
        ->assertSee('Conflicting records')
        ->assertSee('Last successful synchronization by branch')
        ->assertDontSee('Rewrite sale');

    $this->actingAs($user)
        ->withSession(['platform.business_id' => $business->id, 'platform.branch_id' => $branch->id])
        ->get(route('platform.dashboard'))
        ->assertOk()
        ->assertSee(route('platform.synchronization'));
});

it('shows device batches failed records conflicts heartbeats protocol and version views', function (): void {
    [$user, $business, $branch, $device] = syncMonitoringFixture();
    $failedBatch = syncMonitoringBatch($business, $branch, $device, SyncBatchStatus::Rejected, ['protocol_version' => '1.0']);
    syncMonitoringRecord($business, $branch, $device, $failedBatch, 'rejected', [
        'error_code' => 'VALIDATION_FAILED',
        'error_message' => 'The payload failed validation.',
        'retryable' => true,
    ]);
    syncMonitoringRecord($business, $branch, $device, $failedBatch, 'conflict', [
        'entity' => 'product',
        'error_code' => 'MASTER_DATA_CONFLICT',
    ]);
    DeviceHeartbeatLog::query()->create([
        'business_id' => $business->id,
        'branch_id' => $branch->id,
        'device_id' => $device->id,
        'device_uuid' => $device->uuid,
        'status' => 'active',
        'app_version' => '1.0.0',
        'last_synchronized_at' => now(),
        'reported_at' => now(),
    ]);

    Livewire::actingAs($user)
        ->withQueryParams(['view' => 'devices'])
        ->test(SynchronizationMonitoringPage::class)
        ->assertSee('Device synchronization overview')
        ->assertSee('Main Shop App')
        ->assertSee('Online');

    Livewire::actingAs($user)
        ->withQueryParams(['view' => 'batches'])
        ->test(SynchronizationMonitoringPage::class)
        ->assertSee('Sync batch history')
        ->assertSee($failedBatch->uuid)
        ->assertSee('Retry eligible batch');

    Livewire::actingAs($user)
        ->withQueryParams(['view' => 'failed-records'])
        ->test(SynchronizationMonitoringPage::class)
        ->assertSee('Failed records')
        ->assertSee('VALIDATION_FAILED')
        ->assertSee('Download redacted error report');

    Livewire::actingAs($user)
        ->withQueryParams(['view' => 'conflicts'])
        ->test(SynchronizationMonitoringPage::class)
        ->assertSee('Conflict records')
        ->assertSee('MASTER_DATA_CONFLICT')
        ->assertSee('Mark reviewed');

    Livewire::actingAs($user)
        ->withQueryParams(['view' => 'heartbeats'])
        ->test(SynchronizationMonitoringPage::class)
        ->assertSee('Device heartbeat history')
        ->assertSee('Main Shop App');

    Livewire::actingAs($user)
        ->withQueryParams(['view' => 'protocols'])
        ->test(SynchronizationMonitoringPage::class)
        ->assertSee('Protocol-version usage')
        ->assertSee('1.0');

    Livewire::actingAs($user)
        ->withQueryParams(['view' => 'versions'])
        ->test(SynchronizationMonitoringPage::class)
        ->assertSee('Application-version distribution')
        ->assertSee('1.0.0');
});

it('records heartbeat history from the device heartbeat endpoint', function (): void {
    [$user, $business, $branch] = syncMonitoringFixture();
    $activation = app(ShopAppActivationService::class)->createActivation($business, $branch, $user, [
        'device_label' => 'Heartbeat Shop App',
        'device_type' => 'shop_app',
        'expires_at' => now()->addMinutes(30),
        'application_version_id' => null,
    ]);
    $credential = app(ShopAppActivationService::class)->activate([
        'activation_code' => $activation['code'],
        'device_identity' => 'heartbeat-machine',
        'app_version' => '1.0.0',
    ])['credential'];

    $this->withToken($credential)
        ->postJson(route('api.v1.device.heartbeat'), [
            'app_version' => '1.1.0',
            'last_synchronized_at' => now()->toISOString(),
            'operating_system' => ['name' => 'Windows'],
        ])
        ->assertOk()
        ->assertJsonPath('data.shop_app_version', '1.1.0');

    expect(DeviceHeartbeatLog::query()->where('business_id', $business->id)->where('app_version', '1.1.0')->exists())->toBeTrue();
});

it('performs safe administrative synchronization actions through the queue', function (): void {
    Queue::fake();
    [$user, $business, $branch, $device] = syncMonitoringFixture();
    $failedBatch = syncMonitoringBatch($business, $branch, $device, SyncBatchStatus::Failed);
    $conflict = syncMonitoringRecord($business, $branch, $device, $failedBatch, 'conflict', [
        'error_code' => 'MASTER_DATA_CONFLICT',
    ]);

    Livewire::actingAs($user)
        ->test(SynchronizationMonitoringPage::class)
        ->call('retryBatch', $failedBatch->uuid)
        ->assertDispatched('notify')
        ->call('markConflictReviewed', $conflict->id)
        ->assertDispatched('notify')
        ->call('requireApplicationUpdate', $device->id)
        ->assertDispatched('notify')
        ->call('revokeDevice', $device->id)
        ->assertDispatched('notify');

    Queue::assertPushed(ProcessSyncBatch::class);
    expect($failedBatch->refresh()->status)->toBe(SyncBatchStatus::Queued)
        ->and($failedBatch->retry_requested_at)->not->toBeNull()
        ->and($conflict->refresh()->reviewed_at)->not->toBeNull()
        ->and($device->refresh()->requires_update_at)->not->toBeNull()
        ->and($device->status)->toBe(DeviceStatus::Revoked)
        ->and($device->credential_hash)->toBeNull();
});

it('keeps synchronization monitoring tenant isolated', function (): void {
    [$user, $business, $branch, $device] = syncMonitoringFixture();
    syncMonitoringBatch($business, $branch, $device, SyncBatchStatus::Accepted);

    $otherBusiness = Business::query()->create(['name' => 'Hidden Sync Business', 'status' => BusinessStatus::Active]);
    $otherBranch = Branch::query()->create(['business_id' => $otherBusiness->id, 'name' => 'Hidden Branch', 'status' => BranchStatus::Active]);
    $otherDevice = Device::query()->create([
        'business_id' => $otherBusiness->id,
        'branch_id' => $otherBranch->id,
        'name' => 'Hidden Shop App',
        'type' => 'shop_app',
        'status' => DeviceStatus::Active,
    ]);
    $otherBatch = syncMonitoringBatch($otherBusiness, $otherBranch, $otherDevice, SyncBatchStatus::Failed);

    Livewire::actingAs($user)
        ->withQueryParams(['view' => 'devices'])
        ->test(SynchronizationMonitoringPage::class)
        ->assertSee('Main Shop App')
        ->assertDontSee('Hidden Shop App');

    Livewire::actingAs($user)
        ->test(SynchronizationMonitoringPage::class)
        ->call('retryBatch', $otherBatch->uuid)
        ->assertHasErrors(['batch']);
});

it('does not place raw payload data in the redacted error report', function (): void {
    [$user, $business, $branch, $device] = syncMonitoringFixture();
    $batch = syncMonitoringBatch($business, $branch, $device, SyncBatchStatus::Rejected);
    syncMonitoringRecord($business, $branch, $device, $batch, 'rejected', [
        'error_code' => 'VALIDATION_FAILED',
        'error_message' => 'Password hash was rejected.',
        'payload' => ['password_hash' => 'secret-hash-value', 'access_token' => 'secret-token-value'],
    ]);

    $report = app(SynchronizationMonitoringService::class)->redactedErrorReport($business->id);

    expect($report)->toContain('VALIDATION_FAILED')
        ->not->toContain('secret-hash-value')
        ->not->toContain('secret-token-value');

    Livewire::actingAs($user)
        ->test(SynchronizationMonitoringPage::class)
        ->call('downloadErrorReport')
        ->assertFileDownloaded('marketlens-sync-error-report.csv');
});
