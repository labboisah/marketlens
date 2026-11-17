<?php

namespace App\Domain\Licensing\Services;

use Illuminate\Validation\ValidationException;

class KernelBridgeActivationPolicy
{
    public function activationModel(): string
    {
        return 'platform_account_link_then_scoped_installation_code';
    }

    public function accountLinkRequestCountsAgainstInstallationLimit(): bool
    {
        return false;
    }

    public function serverInstallationCountsAgainstLimit(): bool
    {
        return true;
    }

    public function installationLimitKey(): string
    {
        return 'max_server_installations';
    }

    public function accountLinkRequestEndpoint(): string
    {
        return '/api/v1/platform/account-links/requests';
    }

    public function accountLinkRequestIsIdempotent(): bool
    {
        return true;
    }

    public function installationActivationCodeEndpoint(): string
    {
        return '/api/v1/platform/account-links/{link}/activation-codes';
    }

    public function assertAccountLinkRequestAllowed(int $licensedServerInstallations, int $activeServerInstallations): void
    {
        $this->validateCounts($licensedServerInstallations, $activeServerInstallations);
    }

    public function assertServerInstallationAllowed(int $licensedServerInstallations, int $activeServerInstallations): void
    {
        $this->validateCounts($licensedServerInstallations, $activeServerInstallations);

        if ($licensedServerInstallations <= 0) {
            throw ValidationException::withMessages([
                'license' => 'This business is not licensed for any Business Server installations.',
            ]);
        }

        if ($activeServerInstallations >= $licensedServerInstallations) {
            throw ValidationException::withMessages([
                'license' => 'The Business Server installation limit has been reached for this license.',
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        return [
            'activation_model' => $this->activationModel(),
            'account_link_request_counts_against_installation_limit' => $this->accountLinkRequestCountsAgainstInstallationLimit(),
            'account_link_request_endpoint' => $this->accountLinkRequestEndpoint(),
            'account_link_request_is_idempotent' => $this->accountLinkRequestIsIdempotent(),
            'server_installation_counts_against_installation_limit' => $this->serverInstallationCountsAgainstLimit(),
            'installation_activation_code_endpoint' => $this->installationActivationCodeEndpoint(),
            'installation_limit_key' => $this->installationLimitKey(),
            'statement' => 'The Platform links an active KernelBridge license to one MarketLens business without consuming an installation slot. No separate license-owner approval is required; each active server installation consumes one max_server_installations slot.',
        ];
    }

    private function validateCounts(int $licensedServerInstallations, int $activeServerInstallations): void
    {
        if ($licensedServerInstallations < 0 || $activeServerInstallations < 0) {
            throw ValidationException::withMessages([
                'license' => 'Activation counts must be non-negative integers.',
            ]);
        }
    }
}
