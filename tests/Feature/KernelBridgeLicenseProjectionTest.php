<?php

use App\Domain\Licensing\Services\KernelBridgeLicenseProjectionService;
use App\Domain\Tenancy\Models\Business;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

it('links a business to a KernelBridge license projection without storing the raw activation code', function (): void {
    $business = Business::query()->create([
        'name' => 'Acme Market',
        'status' => \App\Enums\BusinessStatus::Active,
        'onboarding_status' => 'completed',
        'email' => 'acme@example.test',
        'phone' => '08000000000',
    ]);

    $service = app(KernelBridgeLicenseProjectionService::class);

    $projection = $service->linkBusinessLicense(
        $business,
        'KB-ACT-12345',
        [
            'product_code' => 'MARKETLENS',
            'license_uuid' => 'lic_123',
            'subscription_uuid' => 'sub_123',
            'status' => 'active',
            'entitlements' => [
                'mode_sales' => true,
                'max_server_installations' => 3,
            ],
            'expires_at' => now()->addMonth()->toDateTimeString(),
            'grace_expires_at' => now()->addMonths(2)->toDateTimeString(),
            'provider_event_id' => 'evt_123',
        ],
    );

    expect($projection->business_id)->toBe($business->id)
        ->and($projection->provider)->toBe('kernelbridge')
        ->and($projection->product_code)->toBe('MARKETLENS')
        ->and($projection->license_uuid)->toBe('lic_123')
        ->and($projection->subscription_uuid)->toBe('sub_123')
        ->and($projection->status)->toBe('active')
        ->and($projection->entitlements)->toMatchArray([
            'mode_sales' => true,
            'max_server_installations' => 3,
        ])
        ->and($projection->provider_metadata)->toBeArray()
        ->and(str_contains((string) $projection->provider_metadata['provider_event_id'] ?? '', 'evt_123'))->toBeTrue()
        ->and($projection->rawActivationCode)->toBeNull();
});

it('prevents a second MarketLens business license projection for the same business', function (): void {
    $business = Business::query()->create([
        'name' => 'Duplicate Market',
        'status' => \App\Enums\BusinessStatus::Active,
        'onboarding_status' => 'completed',
        'email' => 'dup@example.test',
        'phone' => '08000000001',
    ]);

    $service = app(KernelBridgeLicenseProjectionService::class);

    $service->linkBusinessLicense($business, 'KB-ACT-1', [
        'product_code' => 'MARKETLENS',
        'license_uuid' => 'lic_1',
        'subscription_uuid' => 'sub_1',
        'status' => 'active',
        'entitlements' => ['mode_sales' => true],
    ]);

    expect(fn () => $service->linkBusinessLicense($business, 'KB-ACT-2', [
        'product_code' => 'MARKETLENS',
        'license_uuid' => 'lic_2',
        'subscription_uuid' => 'sub_2',
        'status' => 'active',
        'entitlements' => ['mode_sales' => true],
    ]))->toThrow(ValidationException::class);
});
