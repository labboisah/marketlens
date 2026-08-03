<?php

namespace App\Livewire\SuperAdmin;

use App\Domain\Billing\Models\SubscriptionChangeRequest;
use App\Domain\Billing\Services\SubscriptionManagementService;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.super-admin')]
class BillingSubscriptionsPage extends Component
{
    use AuthorizesRequests;

    public function render(): View
    {
        abort_unless(auth()->user()?->isSuperAdmin(), 403);

        return view('livewire.super-admin.billing-subscriptions-page', [
            'requests' => SubscriptionChangeRequest::query()
                ->with(['business', 'requestedPlan', 'requester', 'subscription.plan'])
                ->latest('requested_at')
                ->limit(50)
                ->get(),
        ])->title('Billing subscription requests');
    }

    public function approveRequest(int $requestId, SubscriptionManagementService $subscriptions): void
    {
        abort_unless(auth()->user()?->isSuperAdmin(), 403);

        $request = SubscriptionChangeRequest::query()->findOrFail($requestId);
        $subscriptions->approveRequest($request, auth()->user());

        $this->dispatch('notify', tone: 'success', title: 'Request approved', message: 'The subscription request was approved.');
    }

    public function rejectRequest(int $requestId, SubscriptionManagementService $subscriptions): void
    {
        abort_unless(auth()->user()?->isSuperAdmin(), 403);

        $request = SubscriptionChangeRequest::query()->findOrFail($requestId);
        $subscriptions->rejectRequest($request, auth()->user());

        $this->dispatch('notify', tone: 'danger', title: 'Request rejected', message: 'The subscription request was rejected.');
    }
}
