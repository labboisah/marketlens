<?php

namespace App\Livewire\Platform\Subscriptions;

use App\Domain\Billing\Models\BillingProfile;
use App\Domain\Billing\Models\SubscriptionChangeRequest;
use App\Domain\Billing\Models\SubscriptionPayment;
use App\Domain\Billing\Services\SubscriptionManagementService;
use App\Domain\Tenancy\Models\Business;
use App\Domain\Tenancy\Services\BusinessContext;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class SubscriptionManagementPage extends Component
{
    use AuthorizesRequests;

    public string $billingName = '';

    public string $billingEmail = '';

    public string $billingPhone = '';

    public string $billingAddress = '';

    public string $taxNumber = '';

    public string $currency = 'NGN';

    public string $cancellationReason = '';

    public ?int $selectedPlanId = null;

    public function mount(BusinessContext $businessContext): void
    {
        $business = $this->currentBusiness($businessContext);
        $profile = $business->billingProfile;

        $this->billingName = $profile?->billing_name ?? $business->legal_name ?? $business->name;
        $this->billingEmail = $profile?->billing_email ?? $business->email ?? auth()->user()->email;
        $this->billingPhone = $profile?->billing_phone ?? $business->phone ?? '';
        $this->billingAddress = $profile?->billing_address ?? $business->address ?? '';
        $this->taxNumber = $profile?->tax_number ?? $business->tax_number ?? '';
        $this->currency = $profile?->currency ?? $business->default_currency ?? (string) config('marketlens.currency.default', 'NGN');
    }

    public function render(BusinessContext $businessContext, SubscriptionManagementService $subscriptions): View
    {
        $business = $this->currentBusiness($businessContext);
        $this->authorize('view', $business);

        $overview = $subscriptions->overview($business);
        $plans = $subscriptions->availablePlans();
        $selectedPlan = $this->selectedPlanId ? $plans->firstWhere('id', $this->selectedPlanId) : null;

        return view('livewire.platform.subscriptions.subscription-management-page', [
            'business' => $business,
            'overview' => $overview,
            'plans' => $plans,
            'selectedPlan' => $selectedPlan,
            'selectedWarnings' => $selectedPlan ? $subscriptions->downgradeWarnings($business, $selectedPlan) : [],
            'billingProfile' => BillingProfile::query()->where('business_id', $business->id)->first(),
            'payments' => SubscriptionPayment::query()
                ->whereHas('subscription', fn ($query) => $query->where('business_id', $business->id))
                ->with('subscription.plan')
                ->latest('paid_at')
                ->latest()
                ->limit(12)
                ->get(),
            'changeRequests' => SubscriptionChangeRequest::query()
                ->with('requestedPlan')
                ->where('business_id', $business->id)
                ->latest('requested_at')
                ->limit(12)
                ->get(),
            'renewals' => $business->subscriptions()
                ->with('plan')
                ->latest('starts_at')
                ->limit(12)
                ->get(),
        ])->title('Subscription management');
    }

    public function saveBillingProfile(BusinessContext $businessContext, SubscriptionManagementService $subscriptions): void
    {
        $business = $this->currentBusiness($businessContext);
        $this->authorize('manage', $business);

        $validated = $this->validate([
            'billingName' => ['required', 'string', 'max:160'],
            'billingEmail' => ['required', 'email', 'max:160'],
            'billingPhone' => ['nullable', 'string', 'max:80'],
            'billingAddress' => ['nullable', 'string', 'max:500'],
            'taxNumber' => ['nullable', 'string', 'max:120'],
            'currency' => ['required', 'string', 'size:3'],
        ]);

        $subscriptions->updateBillingProfile($business, auth()->user(), [
            'billing_name' => $validated['billingName'],
            'billing_email' => $validated['billingEmail'],
            'billing_phone' => $validated['billingPhone'] ?: null,
            'billing_address' => $validated['billingAddress'] ?: null,
            'tax_number' => $validated['taxNumber'] ?: null,
            'currency' => strtoupper($validated['currency']),
        ]);

        $this->dispatch('notify', tone: 'success', title: 'Billing profile saved', message: 'Billing details were updated safely.');
    }

    public function requestPlanChange(BusinessContext $businessContext, SubscriptionManagementService $subscriptions, int $planId): void
    {
        $business = $this->currentBusiness($businessContext);
        $this->authorize('manage', $business);

        $request = $subscriptions->requestPlanChange($business, auth()->user(), $planId);
        $this->selectedPlanId = null;

        $this->dispatch('notify', tone: $request->type === 'downgrade' ? 'warning' : 'success', title: ucfirst($request->type).' requested', message: 'The subscription request was recorded for billing review.');
    }

    public function requestCancellation(BusinessContext $businessContext, SubscriptionManagementService $subscriptions): void
    {
        $business = $this->currentBusiness($businessContext);
        $this->authorize('manage', $business);

        $this->validate(['cancellationReason' => ['nullable', 'string', 'max:500']]);
        $subscriptions->requestCancellation($business, auth()->user(), $this->cancellationReason ?: null);
        $this->cancellationReason = '';

        $this->dispatch('notify', tone: 'warning', title: 'Cancellation requested', message: 'Access is not removed immediately; retention and grace policies still apply.');
    }

    private function currentBusiness(BusinessContext $businessContext): Business
    {
        return auth()->user()->ownedBusinesses()
            ->with(['currentSubscription.plan.features', 'subscriptions.plan', 'branches', 'billingProfile'])
            ->whereKey($businessContext->id() ?: auth()->user()->ownedBusinesses()->value('businesses.id'))
            ->firstOrFail();
    }
}
