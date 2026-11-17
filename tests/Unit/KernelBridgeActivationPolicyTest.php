<?php

use App\Domain\Licensing\Services\KernelBridgeActivationPolicy;

it('resolves the activation model as a platform account link followed by scoped installation code', function (): void {
    $policy = app(KernelBridgeActivationPolicy::class);

    expect($policy->activationModel())->toBe('platform_account_link_then_scoped_installation_code')
        ->and($policy->accountLinkRequestCountsAgainstInstallationLimit())->toBeFalse()
        ->and($policy->serverInstallationCountsAgainstLimit())->toBeTrue()
        ->and($policy->installationLimitKey())->toBe('max_server_installations')
        ->and($policy->accountLinkRequestEndpoint())->toBe('/api/v1/platform/account-links/requests')
        ->and($policy->accountLinkRequestIsIdempotent())->toBeTrue()
        ->and($policy->installationActivationCodeEndpoint())->toBe('/api/v1/platform/account-links/{link}/activation-codes');
});

it('allows an account-link request without consuming a slot and enforces the server limit separately', function (): void {
    $policy = app(KernelBridgeActivationPolicy::class);

    expect(fn () => $policy->assertAccountLinkRequestAllowed(3, 0))->not->toThrow(\Throwable::class)
        ->and(fn () => $policy->assertServerInstallationAllowed(3, 2))->not->toThrow(\Throwable::class)
        ->and(fn () => $policy->assertServerInstallationAllowed(3, 3))->toThrow(\Illuminate\Validation\ValidationException::class);
});
