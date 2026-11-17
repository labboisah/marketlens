<?php

namespace App\Livewire\Platform;

use App\Domain\Licensing\Models\BusinessLicenseAssociation;
use App\Domain\Licensing\Services\KernelBridgeLicenseSyncService;
use App\Domain\Tenancy\Models\Business;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class AccountLicenseActivationPage extends Component
{
    public string $activationCode = '';

    public ?Business $business = null;

    public function mount(): void
    {
        $user = Auth::user();

        if (! $user) {
            abort(403);
        }

        $this->business = $this->ownedBusinessForCurrentContext($user);
    }

    public function activate(KernelBridgeLicenseSyncService $syncService): void
    {
        $user = Auth::user();

        if (! $user) {
            abort(403);
        }

        $business = $this->ownedBusinessForCurrentContext($user);

        if (! $business) {
            abort(403);
        }

        try {
            $license = $syncService->redeemActivationCode($business, $this->activationCode);
            $this->activationCode = '';
            $this->business = $business->refresh();

            if ($this->redirectIfAccountLinkApproved($license)) {
                return;
            }

            session()->flash('status', 'License linked to this business. Product installations can now activate.');
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $field => $messages) {
                $this->addError($field, $messages[0]);
            }
        }
    }

    public function refreshAccountLinkStatus(KernelBridgeLicenseSyncService $syncService): void
    {
        $business = $this->business;
        if (! $business) {
            abort(403);
        }

        try {
            $license = $syncService->refreshAccountLink($business);
            $this->business = $business->refresh();

            if ($this->redirectIfAccountLinkApproved($license)) {
                return;
            }

            session()->flash('status', 'KernelBridge account-link status refreshed.');
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $field => $messages) {
                $this->addError($field, $messages[0]);
            }
        }
    }

    public function requestAccountLinkAgain(KernelBridgeLicenseSyncService $syncService): void
    {
        $business = $this->business;
        if (! $business) {
            abort(403);
        }

        try {
            $license = $syncService->requestAccountLink($business);
            $this->business = $business->refresh();

            if ($this->redirectIfAccountLinkApproved($license)) {
                return;
            }

            session()->flash('status', 'A new KernelBridge account-link request was sent.');
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $field => $messages) {
                $this->addError($field, $messages[0]);
            }
        }
    }

    public function unlinkAccount(KernelBridgeLicenseSyncService $syncService): void
    {
        $user = Auth::user();

        if (! $user) {
            abort(403);
        }

        $business = $this->ownedBusinessForCurrentContext($user);

        if (! $business) {
            abort(403);
        }

        try {
            $syncService->unlinkAccount($business);
            $this->business = $business->refresh();
            session()->flash('status', 'KernelBridge account unlinked. You can now connect a different license. Existing Business Server installations and unused installation codes were revoked.');
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $field => $messages) {
                $this->addError($field, $messages[0]);
            }
        }
    }

    public function render()
    {
        $license = $this->business?->kernelbridgeLicense()->first();
        $productName = match (strtoupper((string) config('kernelbridge.product_code', 'MARKETLENS'))) {
            'MARKETLENS' => 'MarketLens',
            default => strtoupper((string) config('kernelbridge.product_code', 'Product')),
        };

        return view('platform.account-license-activation', [
            'businessName' => $this->business?->name,
            'license' => $license,
            'productName' => $productName,
            'canManageLicense' => $this->business !== null,
            'initialAccountLink' => $license
                && is_string(data_get($license->provider_metadata, 'account_link_uuid'))
                && in_array(data_get($license->provider_metadata, 'account_link_status'), ['pending', 'approved', 'rejected', 'revoked'], true)
                    ? [
                        'linkId' => data_get($license->provider_metadata, 'account_link_uuid'),
                        'status' => data_get($license->provider_metadata, 'account_link_status'),
                    ]
                    : null,
        ])->layout('layouts.kernelbridge-activation');
    }

    private function ownedBusinessForCurrentContext(User $user): ?Business
    {
        $selectedBusinessId = (int) session('platform.business_id');

        return $user->ownedBusinesses()->whereKey($selectedBusinessId)->first()
            ?? $user->ownedBusinesses()->first();
    }

    private function redirectIfAccountLinkApproved(BusinessLicenseAssociation $license): bool
    {
        if (data_get($license->provider_metadata, 'account_link_status') !== 'approved') {
            return false;
        }

        $route = config(
            'kernelbridge.platform_link_redirect_route',
            config('kernelbridge-licensing.redirects.after_platform_link'),
        );

        if (! is_string($route) || $route === '') {
            return false;
        }

        $this->redirectRoute($route, navigate: true);

        return true;
    }
}
