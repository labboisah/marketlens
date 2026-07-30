<?php

namespace App\Domain\Billing\Gateways;

use App\Domain\Billing\Contracts\PaymentGateway;
use App\Domain\Billing\Models\Plan;
use App\Domain\Billing\Models\Subscription;
use App\Domain\Tenancy\Models\Business;
use App\Models\User;

class NullPaymentGateway implements PaymentGateway
{
    public function checkoutForSubscriptionChange(Business $business, User $actor, Plan $plan, ?Subscription $subscription): array
    {
        return [
            'provider' => 'manual',
            'status' => 'pending_review',
            'message' => 'Subscription changes are recorded for manual billing or a future gateway adapter.',
        ];
    }

    public function cancelSubscription(Business $business, User $actor, ?Subscription $subscription, ?string $reason): array
    {
        return [
            'provider' => 'manual',
            'status' => 'pending_review',
            'message' => 'Cancellation requests are reviewed before access changes are applied.',
        ];
    }

    public function billingPortal(Business $business, User $actor): array
    {
        return [
            'provider' => 'manual',
            'status' => 'unavailable',
        ];
    }
}
