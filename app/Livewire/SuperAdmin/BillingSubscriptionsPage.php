<?php

namespace App\Livewire\SuperAdmin;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Billing\Models\Plan;
use App\Domain\Billing\Models\Subscription;
use App\Domain\Billing\Models\SubscriptionChangeRequest;
use App\Domain\Billing\Services\SubscriptionManagementService;
use App\Enums\SubscriptionStatus;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.super-admin')]
class BillingSubscriptionsPage extends Component
{
    use AuthorizesRequests;

    public ?int $editingSubscriptionId = null;

    public ?int $planId = null;

    public string $status = 'active';

    public ?string $startsAt = null;

    public ?string $endsAt = null;

    public function render(): View
    {
        $this->assertSuperAdmin();

        return view('livewire.super-admin.billing-subscriptions-page', [
            'requests' => SubscriptionChangeRequest::query()
                ->with(['business', 'requestedPlan', 'requester', 'subscription.plan'])
                ->latest('requested_at')
                ->limit(50)
                ->get(),
            'subscriptions' => Subscription::query()
                ->with(['business', 'plan'])
                ->latest()
                ->limit(100)
                ->get(),
            'plans' => Plan::query()
                ->active()
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
            'statuses' => SubscriptionStatus::cases(),
        ])->title('Billing subscription requests');
    }

    public function approveRequest(int $requestId, SubscriptionManagementService $subscriptions): void
    {
        $this->assertSuperAdmin();

        $request = SubscriptionChangeRequest::query()->findOrFail($requestId);
        $subscriptions->approveRequest($request, auth()->user());

        $this->dispatch('notify', tone: 'success', title: 'Request approved', message: 'The subscription request was approved.');
    }

    public function rejectRequest(int $requestId, SubscriptionManagementService $subscriptions): void
    {
        $this->assertSuperAdmin();

        $request = SubscriptionChangeRequest::query()->findOrFail($requestId);
        $subscriptions->rejectRequest($request, auth()->user());

        $this->dispatch('notify', tone: 'danger', title: 'Request rejected', message: 'The subscription request was rejected.');
    }

    public function editSubscription(int $subscriptionId): void
    {
        $this->assertSuperAdmin();
        $this->resetErrorBag();

        $subscription = Subscription::query()->findOrFail($subscriptionId);

        $this->editingSubscriptionId = $subscription->id;
        $this->planId = $subscription->plan_id;
        $this->status = $subscription->status->value;
        $this->startsAt = $subscription->starts_at?->toDateString();
        $this->endsAt = $subscription->ends_at?->toDateString();
    }

    public function saveSubscription(): void
    {
        $this->assertSuperAdmin();

        $validated = $this->validate([
            'editingSubscriptionId' => ['required', 'integer', 'exists:subscriptions,id'],
            'planId' => ['required', 'integer', 'exists:plans,id'],
            'status' => ['required', 'string', Rule::in($this->statusValues())],
            'startsAt' => ['nullable', 'date'],
            'endsAt' => ['nullable', 'date', 'after_or_equal:startsAt'],
        ]);

        $subscription = Subscription::query()->with('business')->findOrFail($validated['editingSubscriptionId']);
        $before = [
            'plan_id' => $subscription->plan_id,
            'status' => $subscription->status->value,
            'starts_at' => $subscription->starts_at?->toDateString(),
            'ends_at' => $subscription->ends_at?->toDateString(),
        ];

        $subscription->forceFill([
            'plan_id' => $validated['planId'],
            'status' => $validated['status'],
            'starts_at' => $validated['startsAt'] ? Carbon::parse($validated['startsAt'])->startOfDay() : null,
            'ends_at' => $validated['endsAt'] ? Carbon::parse($validated['endsAt'])->endOfDay() : null,
        ])->save();

        $this->auditSubscriptionChange($subscription->refresh(), 'subscription.admin_updated', $before);
        $this->dispatch('notify', tone: 'success', title: 'Subscription updated', message: 'The subscription record was updated.');
    }

    public function setSubscriptionStatus(int $subscriptionId, string $status): void
    {
        $this->assertSuperAdmin();
        $this->resetErrorBag();

        if (! in_array($status, $this->statusValues(), true)) {
            $this->addError('subscriptionAction', 'Unsupported subscription status.');

            return;
        }

        $subscription = Subscription::query()->with('business')->findOrFail($subscriptionId);
        $before = ['status' => $subscription->status->value, 'ends_at' => $subscription->ends_at?->toDateString()];

        $updates = ['status' => $status];

        if ($status === SubscriptionStatus::Cancelled->value) {
            $updates['ends_at'] = now();
        } elseif (in_array($status, [SubscriptionStatus::Active->value, SubscriptionStatus::Trialing->value], true) && $subscription->ends_at?->isPast()) {
            $updates['ends_at'] = now()->addMonth();
        }

        $subscription->forceFill($updates)->save();

        if ($this->editingSubscriptionId === $subscription->id) {
            $this->editSubscription($subscription->id);
        }

        $this->auditSubscriptionChange($subscription->refresh(), 'subscription.admin_status_updated', $before);
        $this->dispatch('notify', tone: 'success', title: 'Subscription status updated', message: 'The subscription status was updated.');
    }

    public function cancelEdit(): void
    {
        $this->reset(['editingSubscriptionId', 'planId', 'startsAt', 'endsAt']);
        $this->status = SubscriptionStatus::Active->value;
        $this->resetErrorBag();
    }

    private function assertSuperAdmin(): void
    {
        abort_unless(auth()->user()?->isSuperAdmin(), 403);
    }

    /**
     * @return array<int, string>
     */
    private function statusValues(): array
    {
        return array_map(fn (SubscriptionStatus $status): string => $status->value, SubscriptionStatus::cases());
    }

    /**
     * @param  array<string, mixed>  $before
     */
    private function auditSubscriptionChange(Subscription $subscription, string $action, array $before): void
    {
        AuditLog::query()->create([
            'business_id' => $subscription->business_id,
            'actor_id' => auth()->id(),
            'action' => $action,
            'subject_type' => Subscription::class,
            'subject_id' => $subscription->id,
            'metadata' => [
                'before' => $before,
                'after' => [
                    'plan_id' => $subscription->plan_id,
                    'status' => $subscription->status->value,
                    'starts_at' => $subscription->starts_at?->toDateString(),
                    'ends_at' => $subscription->ends_at?->toDateString(),
                ],
            ],
        ]);
    }
}
