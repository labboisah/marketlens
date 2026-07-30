<?php

namespace App\Domain\Billing\Contracts;

use App\Domain\Billing\Models\Plan;
use App\Domain\Billing\Models\Subscription;
use App\Domain\Tenancy\Models\Business;
use App\Models\User;

interface PaymentGateway
{
    /**
     * @return array<string, mixed>
     */
    public function checkoutForSubscriptionChange(Business $business, User $actor, Plan $plan, ?Subscription $subscription): array;

    /**
     * @return array<string, mixed>
     */
    public function cancelSubscription(Business $business, User $actor, ?Subscription $subscription, ?string $reason): array;

    /**
     * @return array<string, mixed>
     */
    public function billingPortal(Business $business, User $actor): array;
}
