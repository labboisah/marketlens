<?php

namespace App\Domain\Onboarding\Services;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Billing\Models\Plan;
use App\Domain\Billing\Models\Subscription;
use App\Domain\Onboarding\Models\OwnerOnboardingDraft;
use App\Domain\Owners\Models\BusinessOwner;
use App\Domain\Tenancy\Models\Branch;
use App\Domain\Tenancy\Models\Business;
use App\Enums\BranchStatus;
use App\Enums\BusinessStatus;
use App\Enums\SubscriptionStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CompleteOwnerOnboarding
{
    public function handle(User $user, OwnerOnboardingDraft $draft): Business
    {
        $data = $draft->data ?? [];

        return DB::transaction(function () use ($user, $draft, $data): Business {
            if (BusinessOwner::query()->where('user_id', $user->id)->exists()) {
                throw ValidationException::withMessages([
                    'business' => 'This account already owns a business.',
                ]);
            }

            $plan = Plan::query()
                ->active()
                ->whereKey(data_get($data, 'plan.plan_id'))
                ->first();

            if (! $plan) {
                throw ValidationException::withMessages([
                    'plan.plan_id' => 'Select an active plan before creating the business.',
                ]);
            }

            $business = Business::query()->create([
                'name' => data_get($data, 'business.name'),
                'legal_name' => data_get($data, 'business.legal_name'),
                'business_type' => data_get($data, 'business.business_type'),
                'registration_number' => data_get($data, 'business.registration_number'),
                'tax_number' => data_get($data, 'business.tax_number'),
                'phone' => data_get($data, 'business.phone'),
                'alternative_phone' => data_get($data, 'business.alternative_phone'),
                'email' => data_get($data, 'business.email'),
                'address' => data_get($data, 'business.address'),
                'state' => data_get($data, 'business.state'),
                'local_government_area' => data_get($data, 'business.local_government_area'),
                'default_currency' => data_get($data, 'business.default_currency', 'NGN'),
                'timezone' => data_get($data, 'business.timezone', 'Africa/Lagos'),
                'preferred_language' => data_get($data, 'business.preferred_language', 'en'),
                'logo_path' => data_get($data, 'business.logo_path'),
                'status' => BusinessStatus::Active,
                'onboarding_status' => 'completed',
                'monitoring_preferences' => data_get($data, 'monitoring', []),
                'onboarded_at' => now(),
            ]);

            BusinessOwner::query()->create([
                'business_id' => $business->id,
                'user_id' => $user->id,
                'role' => 'owner',
            ]);

            Branch::query()->create([
                'business_id' => $business->id,
                'name' => data_get($data, 'branch.name'),
                'code' => data_get($data, 'branch.code'),
                'phone' => data_get($data, 'branch.phone'),
                'email' => data_get($data, 'branch.email'),
                'address' => data_get($data, 'branch.address'),
                'state' => data_get($data, 'branch.state'),
                'local_government_area' => data_get($data, 'branch.local_government_area'),
                'is_head_office' => (bool) data_get($data, 'branch.is_head_office', true),
                'status' => BranchStatus::Active,
            ]);

            Subscription::query()->create([
                'business_id' => $business->id,
                'plan_id' => $plan->id,
                'status' => SubscriptionStatus::Trialing,
                'starts_at' => now(),
                'ends_at' => now()->addDays((int) $plan->trial_days),
            ]);

            $draft->forceFill([
                'current_step' => 5,
                'completed_at' => now(),
            ])->save();

            AuditLog::query()->create([
                'business_id' => $business->id,
                'actor_id' => $user->id,
                'action' => 'business_owner.onboarding_completed',
                'subject_type' => Business::class,
                'subject_id' => $business->id,
                'metadata' => ['plan_id' => $plan->id],
            ]);

            return $business;
        });
    }
}
