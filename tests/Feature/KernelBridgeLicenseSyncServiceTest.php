<?php

use App\Domain\Devices\Models\Device;
use App\Domain\Licensing\Models\BusinessLicenseAssociation;
use App\Domain\Licensing\Services\KernelBridgeLicenseSyncService;
use App\Domain\Tenancy\Models\Branch;
use App\Domain\Tenancy\Models\Business;
use App\Enums\BusinessStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config(['kernelbridge.api_token' => 'test-api-credential']);
});

it('requests a KernelBridge account link from an activation code and stores the active projection', function (): void {
    Http::fake([
        '*/platform/account-links/requests' => Http::response([
            'success' => true,
            'data' => [
                'uuid' => 'link_abc',
                'product_code' => 'MARKETLENS',
                'license_uuid' => 'lic_abc',
                'subscription_uuid' => 'sub_abc',
                'business_uuid' => '11111111-1111-4111-8111-111111111111',
                'status' => 'approved',
                'license_status' => 'active',
                'expires_at' => now()->addMonth()->toDateTimeString(),
                'grace_expires_at' => now()->addWeeks(2)->toDateTimeString(),
                'entitlement_revision' => 3,
                'entitlements' => [
                    ['key' => 'mode_sales', 'enabled' => true, 'limit' => null],
                    ['key' => 'max_server_installations', 'enabled' => true, 'limit' => 3],
                ],
                'provider_event_id' => 'event_123',
            ],
            'meta' => ['request_id' => 'request-123'],
        ], 200),
    ]);

    $business = Business::query()->create([
        'name' => 'Redeem Demo',
        'status' => BusinessStatus::Active,
        'onboarding_status' => 'completed',
        'uuid' => '11111111-1111-4111-8111-111111111111',
        'email' => 'redeem@example.test',
        'phone' => '08011111111',
    ]);

    $service = app(KernelBridgeLicenseSyncService::class);
    $projection = $service->redeemActivationCode($business, 'KB-TEST-123');

    expect($projection->business_id)->toBe($business->id)
        ->and($projection->provider)->toBe('kernelbridge')
        ->and($projection->product_code)->toBe('MARKETLENS')
        ->and($projection->license_uuid)->toBe('lic_abc')
        ->and($projection->subscription_uuid)->toBe('sub_abc')
        ->and($projection->status)->toBe('active')
        ->and($projection->provider_metadata['account_link_uuid'])->toBe('link_abc')
        ->and($projection->provider_metadata['account_link_status'])->toBe('approved')
        ->and($projection->entitlements)->toMatchArray(['mode_sales' => true, 'max_server_installations' => 3])
        ->and(Http::assertSent(fn ($request) => str_contains((string) $request->url(), '/platform/account-links/requests')
            && $request['activation_code'] === 'KB-TEST-123'));
});

it('completes an existing pending account link when the activation code is resubmitted', function (): void {
    Http::fake([
        '*/platform/account-links/requests' => Http::sequence()
            ->push([
                'success' => true,
                'data' => [
                    'uuid' => 'link_retry',
                    'product_code' => 'MARKETLENS',
                    'license_uuid' => 'lic_retry',
                    'subscription_uuid' => 'sub_retry',
                    'business_uuid' => '33333333-3333-4333-8333-333333333333',
                    'status' => 'pending',
                    'license_status' => 'active',
                    'entitlements' => [],
                ],
            ])
            ->push([
                'success' => true,
                'data' => [
                    'uuid' => 'link_retry',
                    'product_code' => 'MARKETLENS',
                    'license_uuid' => 'lic_retry',
                    'subscription_uuid' => 'sub_retry',
                    'business_uuid' => '33333333-3333-4333-8333-333333333333',
                    'status' => 'approved',
                    'license_status' => 'active',
                    'entitlements' => [],
                ],
            ]),
    ]);

    $business = Business::query()->create([
        'name' => 'Pending Link Demo',
        'status' => BusinessStatus::Active,
        'onboarding_status' => 'completed',
        'uuid' => '33333333-3333-4333-8333-333333333333',
        'email' => 'pending-link@example.test',
        'phone' => '08011111117',
    ]);
    $service = app(KernelBridgeLicenseSyncService::class);

    $pending = $service->redeemActivationCode($business, 'KB-TEST-123');
    $completed = $service->redeemActivationCode($business, 'KB-TEST-123');

    expect($pending->status)->toBe('pending')
        ->and($completed->status)->toBe('active')
        ->and($completed->provider_metadata['account_link_status'])->toBe('approved')
        ->and($business->kernelbridgeLicense()->count())->toBe(1);
    Http::assertSentCount(2);
});

it('unlinks a KernelBridge account before allowing a different license to be connected', function (): void {
    Http::fake([
        '*/platform/account-links/link_to_replace' => Http::response([
            'success' => true,
            'data' => [
                'uuid' => 'link_to_replace',
                'license_uuid' => 'lic_old',
                'business_uuid' => '55555555-5555-4555-8555-555555555555',
                'status' => 'revoked',
                'installations_revoked' => 1,
                'activation_codes_revoked' => 1,
            ],
        ]),
        '*/platform/account-links/requests' => Http::response([
            'success' => true,
            'data' => [
                'uuid' => 'link_replacement',
                'product_code' => 'MARKETLENS',
                'license_uuid' => 'lic_replacement',
                'subscription_uuid' => 'sub_replacement',
                'business_uuid' => '55555555-5555-4555-8555-555555555555',
                'status' => 'approved',
                'license_status' => 'active',
                'entitlements' => [['key' => 'mode_sales', 'enabled' => true]],
            ],
        ]),
    ]);

    $business = Business::query()->create([
        'name' => 'License Switch Demo',
        'status' => BusinessStatus::Active,
        'onboarding_status' => 'completed',
        'uuid' => '55555555-5555-4555-8555-555555555555',
        'email' => 'license-switch@example.test',
        'phone' => '08011111118',
    ]);
    $branch = Branch::query()->create([
        'business_id' => $business->id,
        'name' => 'Main',
        'code' => 'MAIN',
        'status' => 'active',
    ]);
    $oldLicense = BusinessLicenseAssociation::query()->create([
        'business_id' => $business->id,
        'provider' => 'kernelbridge',
        'product_code' => 'MARKETLENS',
        'license_uuid' => 'lic_old',
        'subscription_uuid' => 'sub_old',
        'status' => 'active',
        'provider_metadata' => [
            'account_link_uuid' => 'link_to_replace',
            'account_link_status' => 'approved',
        ],
    ]);
    $device = Device::query()->create([
        'business_id' => $business->id,
        'branch_id' => $branch->id,
        'name' => 'Existing server',
        'type' => 'shop_app',
        'status' => 'active',
        'kernelbridge_license_uuid' => 'lic_old',
        'kernelbridge_grant_status' => 'active',
    ]);
    $service = app(KernelBridgeLicenseSyncService::class);

    $service->unlinkAccount($business);
    $replacement = $service->redeemActivationCode($business, 'KB-NEW-LICENSE');

    expect($oldLicense->fresh())->toBeNull()
        ->and($device->fresh()->kernelbridge_grant_status)->toBe('revoked')
        ->and($replacement->license_uuid)->toBe('lic_replacement')
        ->and($replacement->provider_metadata['account_link_uuid'])->toBe('link_replacement');

    Http::assertSent(fn ($request) => $request->method() === 'DELETE'
        && str_ends_with((string) $request->url(), '/platform/account-links/link_to_replace'));
});

it('requires a configured KernelBridge API credential before making a request', function (): void {
    config(['kernelbridge.api_token' => '']);
    $business = Business::query()->create([
        'name' => 'Missing Credential Demo',
        'status' => BusinessStatus::Active,
        'onboarding_status' => 'completed',
        'email' => 'missing-credential@example.test',
        'phone' => '08011111113',
    ]);

    $exception = null;

    try {
        app(KernelBridgeLicenseSyncService::class)->redeemActivationCode($business, 'KB-TEST-123');
    } catch (ValidationException $caught) {
        $exception = $caught;
    }

    expect($exception)->toBeInstanceOf(ValidationException::class)
        ->and($exception->errors()['activation_code'][0])->toContain('KERNELBRIDGE_API_TOKEN');
    Http::assertNothingSent();
});

it('rejects account-link responses for a different business without creating a projection', function (): void {
    Http::fake([
        '*/platform/account-links/requests' => Http::response([
            'success' => true,
            'data' => [
                'uuid' => 'link_wrong_business',
                'product_code' => 'MARKETLENS',
                'license_uuid' => 'lic_abc',
                'subscription_uuid' => 'sub_abc',
                'business_uuid' => 'another-business',
                'status' => 'pending',
                'license_status' => 'active',
                'entitlements' => [],
            ],
        ]),
    ]);

    $business = Business::query()->create([
        'name' => 'Mismatch Demo',
        'status' => BusinessStatus::Active,
        'onboarding_status' => 'completed',
        'email' => 'mismatch@example.test',
        'phone' => '08011111115',
    ]);

    expect(fn () => app(KernelBridgeLicenseSyncService::class)->redeemActivationCode($business, 'KB-TEST-123'))
        ->toThrow(ValidationException::class, 'incomplete or mismatched account-link request');
    expect($business->kernelbridgeLicense()->exists())->toBeFalse();
});

it('refreshes an older pending link when KernelBridge reports it as active', function (): void {
    Http::fake([
        '*/platform/account-links/link_approved' => Http::response([
            'success' => true,
            'data' => [
                'uuid' => 'link_approved',
                'product_code' => 'MARKETLENS',
                'license_uuid' => 'lic_approved',
                'subscription_uuid' => 'sub_approved',
                'business_uuid' => '22222222-2222-4222-8222-222222222222',
                'status' => 'approved',
                'license_status' => 'active',
                'entitlements' => [
                    ['key' => 'mode_sales', 'enabled' => true, 'limit' => null],
                ],
            ],
        ]),
    ]);

    $business = Business::query()->create([
        'name' => 'Approved Demo',
        'status' => BusinessStatus::Active,
        'onboarding_status' => 'completed',
        'uuid' => '22222222-2222-4222-8222-222222222222',
        'email' => 'approved@example.test',
        'phone' => '08011111116',
    ]);
    $license = BusinessLicenseAssociation::query()->create([
        'business_id' => $business->id,
        'provider' => 'kernelbridge',
        'product_code' => 'MARKETLENS',
        'license_uuid' => 'lic_approved',
        'subscription_uuid' => 'sub_approved',
        'status' => 'pending',
        'entitlements' => ['mode_sales' => true],
        'provider_metadata' => [
            'account_link_uuid' => 'link_approved',
            'account_link_status' => 'pending',
            'api_base_url' => 'https://kernelbridge.test/api/v1',
        ],
    ]);

    $refreshed = app(KernelBridgeLicenseSyncService::class)->refreshAccountLink($business);

    expect($refreshed->id)->toBe($license->id)
        ->and($refreshed->status)->toBe('active')
        ->and($refreshed->provider_metadata['account_link_status'])->toBe('approved')
        ->and($refreshed->entitlements)->toBe(['mode_sales' => true]);
});

it('shows the KernelBridge API error message when an account-link request fails', function (): void {
    Http::fake([
        '*/platform/account-links/requests' => Http::response([
            'success' => false,
            'error' => [
                'code' => 'unauthenticated',
                'message' => 'A valid bearer token is required.',
                'details' => (object) [],
            ],
            'meta' => ['request_id' => 'request-123'],
        ], 401),
    ]);

    $business = Business::query()->create([
        'name' => 'Activation Error Demo',
        'status' => BusinessStatus::Active,
        'onboarding_status' => 'completed',
        'email' => 'activation-error@example.test',
        'phone' => '08011111112',
    ]);

    $exception = null;

    try {
        app(KernelBridgeLicenseSyncService::class)->redeemActivationCode($business, 'KB-TEST-123');
    } catch (ValidationException $caught) {
        $exception = $caught;
    }

    expect($exception)->toBeInstanceOf(ValidationException::class)
        ->and($exception->errors()['activation_code'][0])->toBe('A valid bearer token is required.');
});

it('explains the required account-link scope when the configured credential is under-scoped', function (): void {
    Http::fake([
        '*/platform/account-links/requests' => Http::response([
            'success' => false,
            'error' => [
                'code' => 'insufficient_scope',
                'message' => 'This API credential is not allowed to perform this operation.',
                'details' => ['required_scopes' => ['licensing:account-link']],
            ],
        ], 403),
    ]);

    $business = Business::query()->create([
        'name' => 'Under-scoped Credential Demo',
        'status' => BusinessStatus::Active,
        'onboarding_status' => 'completed',
        'email' => 'under-scoped@example.test',
        'phone' => '08011111117',
    ]);

    $exception = null;

    try {
        app(KernelBridgeLicenseSyncService::class)->redeemActivationCode($business, 'KB-TEST-123');
    } catch (ValidationException $caught) {
        $exception = $caught;
    }

    expect($exception)->toBeInstanceOf(ValidationException::class)
        ->and($exception->errors()['activation_code'][0])
        ->toContain('licensing:account-link')
        ->toContain('KERNELBRIDGE_API_TOKEN');
});

it('refreshes entitlements on the existing business license projection', function (): void {
    Http::fake([
        '*/subscriptions/status*' => Http::response([
            'success' => true,
            'data' => [
                'uuid' => 'sub_refresh',
                'status' => 'active',
                'plan' => ['name' => 'MarketLens Sales'],
                'ends_at' => now()->addYear()->toISOString(),
                'grace_ends_at' => null,
            ],
        ]),
        '*/entitlements*' => Http::response([
            'success' => true,
            'data' => [
                'subscription_uuid' => 'sub_refresh',
                'status' => 'active',
                'entitlements' => [
                    ['key' => 'max_server_installations', 'enabled' => true, 'limit' => 2],
                ],
            ],
        ]),
    ]);

    $business = Business::query()->create([
        'name' => 'Entitlement Refresh Demo',
        'status' => BusinessStatus::Active,
        'onboarding_status' => 'completed',
        'email' => 'refresh@example.test',
        'phone' => '08011111114',
    ]);
    $license = BusinessLicenseAssociation::query()->create([
        'business_id' => $business->id,
        'provider' => 'kernelbridge',
        'product_code' => 'MARKETLENS',
        'license_uuid' => 'lic_refresh',
        'subscription_uuid' => 'sub_refresh',
        'status' => 'active',
        'entitlements' => ['max_server_installations' => 1],
    ]);

    $refreshed = app(KernelBridgeLicenseSyncService::class)->refreshEntitlements($business);

    expect($refreshed->id)->toBe($license->id)
        ->and($refreshed->entitlements)->toBe(['max_server_installations' => 2])
        ->and($refreshed->provider_metadata['plan_name'])->toBe('MarketLens Sales')
        ->and($refreshed->last_verified_at)->not->toBeNull();

    Http::assertSent(fn ($request) => $request->method() === 'GET'
        && parse_url($request->url(), PHP_URL_PATH) === '/api/v1/entitlements'
        && str_contains((string) parse_url($request->url(), PHP_URL_QUERY), 'subscription_uuid=sub_refresh'));
});
