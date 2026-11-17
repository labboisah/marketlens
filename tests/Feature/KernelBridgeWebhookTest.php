<?php

use App\Domain\Licensing\Models\BusinessLicenseAssociation;
use App\Domain\Tenancy\Models\Business;
use App\Enums\BusinessStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

const WEBHOOK_SECRET = 'test-webhook-secret';

beforeEach(function (): void {
    config(['kernelbridge.api_token' => 'test-api-credential', 'kernelbridge.webhook_secret' => WEBHOOK_SECRET]);
});

function webhookLicense(string $subscriptionUuid = 'sub_hook'): BusinessLicenseAssociation
{
    $business = Business::query()->create([
        'name' => 'Webhook Stores',
        'status' => BusinessStatus::Active,
        'onboarding_status' => 'completed',
        'email' => 'hook-'.$subscriptionUuid.'@example.test',
        'phone' => '08022222222',
    ]);

    return BusinessLicenseAssociation::query()->create([
        'business_id' => $business->id,
        'provider' => 'kernelbridge',
        'product_code' => 'MARKETLENS',
        'license_uuid' => 'lic_'.$subscriptionUuid,
        'subscription_uuid' => $subscriptionUuid,
        'status' => 'active',
        'entitlements' => ['mode_sales' => true, 'mode_production' => true],
    ]);
}

function fakeKernelBridgeSalesOnly(string $subscriptionUuid): void
{
    Http::fake([
        '*/subscriptions/status*' => Http::response(['success' => true, 'data' => [
            'uuid' => $subscriptionUuid, 'status' => 'active', 'plan' => ['name' => 'MarketLens Sales'], 'ends_at' => now()->addYear()->toISOString(),
        ]]),
        '*/entitlements*' => Http::response(['success' => true, 'data' => [
            'subscription_uuid' => $subscriptionUuid, 'status' => 'active',
            'entitlements' => [['key' => 'mode_sales', 'enabled' => true, 'limit' => null], ['key' => 'mode_production', 'enabled' => false, 'limit' => null]],
        ]]),
    ]);
}

/** @return array{0: string, 1: array<string, string>} */
function signedWebhook(array $event, ?int $timestamp = null, string $secret = WEBHOOK_SECRET): array
{
    $body = json_encode($event);
    $timestamp ??= time();

    return [$body, ['X-KernelBridge-Signature' => 't='.$timestamp.',v1='.hash_hmac('sha256', $timestamp.'.'.$body, $secret), 'Content-Type' => 'application/json']];
}

it('refreshes the license projection when a signed webhook arrives', function (): void {
    $license = webhookLicense();
    fakeKernelBridgeSalesOnly('sub_hook');
    [$body, $headers] = signedWebhook(['id' => 'evt-1', 'type' => 'subscription.updated', 'data' => ['subscription_uuid' => 'sub_hook']]);

    $this->call('POST', '/api/v1/webhooks/kernelbridge', [], [], [], $this->transformHeadersToServerVars($headers), $body)
        ->assertOk()->assertJsonPath('code', 'processed');

    expect($license->fresh()->entitlements)->toMatchArray(['mode_sales' => true, 'mode_production' => false])
        ->and($license->fresh()->provider_metadata['plan_name'])->toBe('MarketLens Sales');
});

it('rejects bad signatures and stale timestamps without refreshing', function (): void {
    webhookLicense();
    Http::fake();
    $event = ['id' => 'evt-2', 'type' => 'subscription.updated', 'data' => ['subscription_uuid' => 'sub_hook']];

    [$body, $headers] = signedWebhook($event, null, 'wrong-secret');
    $this->call('POST', '/api/v1/webhooks/kernelbridge', [], [], [], $this->transformHeadersToServerVars($headers), $body)
        ->assertUnauthorized()->assertJsonPath('code', 'invalid_signature');

    [$body, $headers] = signedWebhook($event, time() - 3600);
    $this->call('POST', '/api/v1/webhooks/kernelbridge', [], [], [], $this->transformHeadersToServerVars($headers), $body)
        ->assertUnauthorized();

    Http::assertNothingSent();
});

it('ignores a replayed webhook event', function (): void {
    webhookLicense();
    fakeKernelBridgeSalesOnly('sub_hook');
    [$body, $headers] = signedWebhook(['id' => 'evt-3', 'type' => 'subscription.updated', 'data' => ['subscription_uuid' => 'sub_hook']]);
    $server = $this->transformHeadersToServerVars($headers);

    $this->call('POST', '/api/v1/webhooks/kernelbridge', [], [], [], $server, $body)->assertJsonPath('code', 'processed');
    $this->call('POST', '/api/v1/webhooks/kernelbridge', [], [], [], $server, $body)->assertJsonPath('code', 'duplicate_event');

    Http::assertSentCount(2);
});

it('reconciles every linked license and records failures', function (): void {
    $good = webhookLicense('sub_good');
    $bad = webhookLicense('sub_bad');
    Http::fake([
        '*subscription_uuid=sub_good*' => Http::sequence()
            ->push(['success' => true, 'data' => ['uuid' => 'sub_good', 'status' => 'active', 'plan' => ['name' => 'MarketLens Sales'], 'ends_at' => now()->addYear()->toISOString()]])
            ->push(['success' => true, 'data' => ['subscription_uuid' => 'sub_good', 'entitlements' => [['key' => 'mode_sales', 'enabled' => true, 'limit' => null]]]]),
        '*subscription_uuid=sub_bad*' => Http::response(['error' => ['message' => 'Provider unavailable']], 500),
    ]);

    $this->artisan('kernelbridge:reconcile-licenses')->assertFailed();

    expect($good->fresh()->entitlements)->toBe(['mode_sales' => true])
        ->and($bad->fresh()->provider_metadata)->toHaveKey('last_reconcile_error');
});
