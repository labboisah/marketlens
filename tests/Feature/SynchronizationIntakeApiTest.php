<?php

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Billing\Models\Plan;
use App\Domain\Billing\Models\Subscription;
use App\Domain\Devices\Models\Device;
use App\Domain\Devices\Services\ShopAppActivationService;
use App\Domain\Reporting\Models\DashboardCashierException;
use App\Domain\Reporting\Models\StaffActivitySummary;
use App\Domain\Synchronization\Jobs\ProcessSyncBatch;
use App\Domain\Synchronization\Models\OperationalUserProfile;
use App\Domain\Synchronization\Models\SyncBatch;
use App\Domain\Synchronization\Models\SyncRecord;
use App\Domain\Synchronization\Services\SynchronizedOperationalData;
use App\Domain\Tenancy\Models\Branch;
use App\Domain\Tenancy\Models\Business;
use App\Enums\BranchStatus;
use App\Enums\BusinessStatus;
use App\Enums\DeviceStatus;
use App\Enums\SubscriptionStatus;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
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
    $plan->features()->create(['name' => 'More Business App devices']);

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
        'device_label' => 'Main Business Server',
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
    $defaultPayload = ['record_uuid' => $recordUuid, 'amount_minor' => 1000];

    if ($entity === 'sale') {
        $defaultPayload = [
            'sale_number' => 'SYNC-SALE-'.$recordUuid,
            'transaction_number' => 'SYNC-TXN-'.$recordUuid,
            'cashier_name' => 'Sync Cashier',
            'customer_name' => 'Walk In',
            'sale_type' => 'retail',
            'status' => 'completed',
            'payment_status' => 'paid',
            'sold_at' => now()->toISOString(),
            'subtotal_minor' => 1000,
            'discount_minor' => 0,
            'tax_minor' => 0,
            'total_minor' => 1000,
            'cost_minor' => 600,
            'gross_profit_minor' => 400,
        ];
    }

    if ($entity === 'sale' && array_key_exists('payload', $overrides) && is_array($overrides['payload'])) {
        $overrides['payload'] = [
            ...$defaultPayload,
            ...$overrides['payload'],
        ];
    }

    return [
        'entity' => $entity,
        'record_uuid' => $recordUuid,
        'operation' => 'create',
        'version' => 1,
        'idempotency_key' => 'idem-'.$recordUuid,
        'occurred_at' => now()->toISOString(),
        'payload' => $defaultPayload,
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

it('accepts retained operational service ownership stock and workstation entities', function (): void {
    Queue::fake();
    [$business, $branch, $device, $credential] = syncIntakeFixture();
    $entities = [
        'product', 'sale_stock_allocation', 'service_order_item', 'service_order',
        'service_payment', 'service_revenue_allocation', 'service_revenue_share_rule',
        'service_staff_assignment', 'service_work_bill_receipt', 'service_workstation_receipt',
        'service_workstation_request', 'share_class', 'share_ownership_transaction',
        'shareholder', 'staff_earning_transaction', 'stock_reconciliation',
        'inventory_stock', 'supplier', 'workstation_bill', 'workstation_checkout',
    ];
    $records = array_map(fn (string $entity): array => syncRecord($entity), $entities);
    $body = json_encode(syncEnvelope($business, $branch, $device, $records), JSON_THROW_ON_ERROR);

    signedSyncRequest($this, 'POST', route('api.v1.sync.batches.store'), $credential, $body)
        ->assertOk()
        ->assertJsonPath('data.status', 'accepted')
        ->assertJsonCount(count($entities), 'data.accepted')
        ->assertJsonPath('data.rejected', []);

    expect(SyncRecord::query()->whereIn('entity', $entities)->count())->toBe(count($entities));
});

it('rejects sensitive fields and mismatched uuids in generic operational records', function (): void {
    Queue::fake();
    [$business, $branch, $device, $credential] = syncIntakeFixture();
    $records = [
        syncRecord('supplier', ['payload' => ['password' => 'must-not-sync']]),
        syncRecord('service_order', ['payload' => ['uuid' => (string) Str::uuid()]]),
    ];
    $body = json_encode(syncEnvelope($business, $branch, $device, $records), JSON_THROW_ON_ERROR);

    signedSyncRequest($this, 'POST', route('api.v1.sync.batches.store'), $credential, $body)
        ->assertOk()
        ->assertJsonPath('data.status', 'rejected')
        ->assertJsonPath('data.rejected.0.code', 'SENSITIVE_FIELD_REJECTED')
        ->assertJsonPath('data.rejected.1.code', 'UUID_MISMATCH');

    expect(SyncRecord::query()->whereIn('record_uuid', array_column($records, 'record_uuid'))->exists())->toBeFalse();
});
it('accepts the same source uuid for different operational entity types', function (): void {
    Queue::fake();
    [$business, $branch, $device, $credential] = syncIntakeFixture();
    $sharedUuid = (string) Str::uuid();
    $records = [
        syncRecord('service_workstation_receipt', ['record_uuid' => $sharedUuid]),
        syncRecord('workstation_checkout', ['record_uuid' => $sharedUuid]),
    ];
    $body = json_encode(syncEnvelope($business, $branch, $device, $records), JSON_THROW_ON_ERROR);

    signedSyncRequest($this, 'POST', route('api.v1.sync.batches.store'), $credential, $body)
        ->assertOk()
        ->assertJsonPath('data.status', 'accepted')
        ->assertJsonCount(2, 'data.accepted');

    expect(SyncRecord::query()->where('record_uuid', $sharedUuid)->count())->toBe(2);
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

it('replicates operational-user profiles with only safe monitoring fields', function (): void {
    Queue::fake();
    [$business, $branch, $device, $credential] = syncIntakeFixture();
    $userUuid = (string) Str::uuid();

    $body = json_encode(syncEnvelope($business, $branch, $device, [
        syncRecord('operational_user_profile', [
            'record_uuid' => $userUuid,
            'payload' => [
                'employee_number' => 'EMP-001',
                'display_name' => 'Ada Cashier',
                'username' => 'ada.cashier',
                'staff_code' => 'CASH-01',
                'role_name' => 'Cashier',
                'role_code' => 'cashier',
                'status' => 'active',
                'created_at' => now()->subMonth()->toISOString(),
                'updated_at' => now()->toISOString(),
            ],
        ]),
    ]), JSON_THROW_ON_ERROR);

    signedSyncRequest($this, 'POST', route('api.v1.sync.batches.store'), $credential, $body)
        ->assertOk()
        ->assertJsonPath('data.status', 'accepted');

    $profile = OperationalUserProfile::query()->where('uuid', $userUuid)->firstOrFail();
    $syncRecord = SyncRecord::query()->where('record_uuid', $userUuid)->firstOrFail();

    expect($profile->business_id)->toBe($business->id)
        ->and($profile->branch_id)->toBe($branch->id)
        ->and($profile->employee_number)->toBe('EMP-001')
        ->and($profile->display_name)->toBe('Ada Cashier')
        ->and($profile->username)->toBe('ada.cashier')
        ->and($profile->staff_code)->toBe('CASH-01')
        ->and($profile->role_name)->toBe('Cashier')
        ->and($profile->role_code)->toBe('cashier')
        ->and($profile->status)->toBe('active')
        ->and($profile->is_active)->toBeTrue()
        ->and($profile->statusLabel())->toBe('Active')
        ->and($profile)->not->toBeInstanceOf(Authenticatable::class)
        ->and($profile->getAttributes())->not->toHaveKeys(['password', 'password_hash', 'pin', 'access_token', 'refresh_token'])
        ->and($syncRecord->payload)->not->toHaveKeys(['password_hash', 'pin', 'access_token', 'refresh_token']);
});

it('rejects operational-user profiles that contain sensitive authentication fields', function (): void {
    Queue::fake();
    [$business, $branch, $device, $credential] = syncIntakeFixture();
    $userUuid = (string) Str::uuid();

    $body = json_encode(syncEnvelope($business, $branch, $device, [
        syncRecord('operational_user_profile', [
            'record_uuid' => $userUuid,
            'payload' => [
                'display_name' => 'Ada Cashier',
                'role_name' => 'Cashier',
                'role_code' => 'cashier',
                'status' => 'active',
                'created_at' => now()->subMonth()->toISOString(),
                'updated_at' => now()->toISOString(),
                'password_hash' => '$2y$unsafe',
                'pin' => '1234',
                'access_token' => 'local-access-token',
                'refresh_token' => 'local-refresh-token',
                'session_id' => 'local-session',
            ],
        ]),
    ]), JSON_THROW_ON_ERROR);

    signedSyncRequest($this, 'POST', route('api.v1.sync.batches.store'), $credential, $body)
        ->assertOk()
        ->assertJsonPath('data.status', 'rejected')
        ->assertJsonPath('data.rejected.0.code', 'SENSITIVE_FIELD_REJECTED');

    expect(OperationalUserProfile::query()->where('uuid', $userUuid)->exists())->toBeFalse()
        ->and(SyncRecord::query()->where('record_uuid', $userUuid)->exists())->toBeFalse();
});

it('connects replicated staff profiles to cashier and staff reporting summaries', function (): void {
    [$business, $branch] = syncIntakeFixture();
    $userUuid = (string) Str::uuid();

    $profile = OperationalUserProfile::query()->create([
        'business_id' => $business->id,
        'branch_id' => $branch->id,
        'uuid' => $userUuid,
        'display_name' => 'Musa Storekeeper',
        'role_name' => 'Storekeeper',
        'role_code' => 'storekeeper',
        'status' => 'suspended',
        'is_active' => false,
        'replicated_created_at' => now()->subMonths(2),
        'replicated_updated_at' => now(),
    ]);

    DashboardCashierException::query()->create([
        'business_id' => $business->id,
        'branch_id' => $branch->id,
        'summary_date' => today(),
        'cashier_uuid' => $userUuid,
        'cashier_name' => 'Musa Storekeeper',
        'variance_minor' => -500,
        'status' => 'open',
    ]);

    StaffActivitySummary::query()->create([
        'business_id' => $business->id,
        'branch_id' => $branch->id,
        'operational_user_uuid' => $userUuid,
        'summary_date' => today(),
        'display_name' => 'Musa Storekeeper',
        'role_name' => 'Storekeeper',
        'sales_count' => 3,
        'gross_sales_minor' => 25000,
        'last_activity_at' => now(),
    ]);

    expect($profile->statusLabel())->toBe('Suspended')
        ->and($profile->cashierExceptions()->first()?->variance_minor)->toBe(-500)
        ->and($profile->activitySummaries()->first()?->sales_count)->toBe(3)
        ->and(DashboardCashierException::query()->first()?->operationalUserProfile?->uuid)->toBe($userUuid)
        ->and(StaffActivitySummary::query()->first()?->operationalUserProfile?->uuid)->toBe($userUuid);
});
it('projects synchronized service and ownership records into owner monitoring data', function (): void {
    Queue::fake();
    [$business, $branch, $device, $credential] = syncIntakeFixture();

    $record = function (string $entity, array $payload): array {
        $record = syncRecord($entity);
        $record['payload'] = ['uuid' => $record['record_uuid'], ...$payload];

        return $record;
    };

    $serviceUuid = (string) Str::uuid();
    $shareholderUuid = (string) Str::uuid();
    $shareClassUuid = (string) Str::uuid();
    $orderUuid = (string) Str::uuid();

    $records = [
        syncRecord('service', [
            'record_uuid' => $serviceUuid,
            'payload' => ['uuid' => $serviceUuid, 'name' => 'Consultation', 'code' => 'CONSULT', 'active' => '1'],
        ]),
        $record('service_order', ['order_number' => 'SVC-OWNER-1', 'status' => 'completed']),
        $record('service_payment', ['order_uuid' => $orderUuid, 'amount' => '5000', 'payment_method' => 'cash']),
        syncRecord('shareholder', [
            'record_uuid' => $shareholderUuid,
            'payload' => ['uuid' => $shareholderUuid, 'name' => 'Owner One', 'status' => 'active'],
        ]),
        syncRecord('share_class', [
            'record_uuid' => $shareClassUuid,
            'payload' => ['uuid' => $shareClassUuid, 'name' => 'Ordinary'],
        ]),
        $record('share_ownership_transaction', [
            'transaction_type' => 'issue',
            'share_class_uuid' => $shareClassUuid,
            'to_shareholder_uuid' => $shareholderUuid,
            'units' => '25',
        ]),
    ];

    $body = json_encode(syncEnvelope($business, $branch, $device, $records), JSON_THROW_ON_ERROR);
    signedSyncRequest($this, 'POST', route('api.v1.sync.batches.store'), $credential, $body)
        ->assertOk()
        ->assertJsonCount(count($records), 'data.accepted');

    $projection = app(SynchronizedOperationalData::class);

    expect($projection->services($business->id, $branch->id)['summary']['services_count'])->toBe(1)
        ->and($projection->shareholders($business->id, $branch->id)['summary']['shareholders_count'])->toBe(1)
        ->and($projection->shareholders($business->id, $branch->id)['summary']['units_held'])->toBe(25.0);
});

it('projects actual Business Server worker payloads into the linked monitoring screens', function (): void {
    [$business, $branch, $device, $credential] = syncIntakeFixture();
    $fixture = json_decode(file_get_contents(base_path('tests/Fixtures/business-server-sync.json')), true, flags: JSON_THROW_ON_ERROR);

    foreach ([...$fixture['batches'], $fixture['batches'][0]] as $batch) {
        $body = json_encode(syncEnvelope($business, $branch, $device, $batch['records']), JSON_THROW_ON_ERROR);
        $response = signedSyncRequest($this, 'POST', '/api/v1/sync/batches', $credential, $body);
        $response->assertOk()->assertJsonPath('data.rejected', [])->assertJsonPath('data.conflicts', []);
        expect($response->json('data.accepted'))->toHaveCount(count($batch['records']));
        $receipt = $response->json('data');
        signedSyncRequest($this, 'POST', '/api/v1/sync/acknowledgements', $credential, json_encode([
            'batch_uuid' => $receipt['batch_uuid'],
            'accepted' => $receipt['accepted'],
        ], JSON_THROW_ON_ERROR))->assertOk();
    }

    $this->assertDatabaseHas('replicated_expenses', ['business_id' => $business->id, 'description' => 'Sync fixture expense', 'status' => 'approved', 'amount_minor' => 1250]);
    $this->assertDatabaseHas('replicated_inventory_stocks', ['business_id' => $business->id, 'product_name' => 'Sync fixture product', 'available_quantity' => 10]);
    $this->assertDatabaseHas('replicated_customer_debt_summaries', ['business_id' => $business->id, 'customer_name' => 'Sync fixture customer', 'current_balance_minor' => 2000]);
    $this->assertDatabaseHas('replicated_supplier_balances', ['business_id' => $business->id, 'supplier_name' => 'Sync fixture supplier', 'current_balance_minor' => 3000]);
    $this->assertDatabaseHas('replicated_sales', ['business_id' => $business->id, 'sale_number' => 'SYNC-SALE', 'total_minor' => 2500]);
    $data = app(SynchronizedOperationalData::class)->services($business->id, $branch->id);
    expect($data['services']->pluck('name')->all())->toContain('Updated service')->not->toContain('Initial service');

    signedSyncRequest($this, 'GET', '/api/v1/sync/checkpoint', $credential)
        ->assertOk()->assertJsonPath('data.business_uuid', $business->uuid)->assertJsonPath('data.branch_uuid', $branch->uuid);
    expect($branch->refresh()->last_synchronized_at)->not->toBeNull();
});
