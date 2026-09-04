<?php

namespace App\Domain\Billing\Services;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Billing\Contracts\PaymentGateway;
use App\Domain\Billing\Models\BillingProfile;
use App\Domain\Billing\Models\Plan;
use App\Domain\Billing\Models\Subscription;
use App\Domain\Billing\Models\SubscriptionChangeRequest;
use App\Domain\Devices\Models\Device;
use App\Domain\Reporting\Models\ReportExport;
use App\Domain\Tenancy\Models\Business;
use App\Enums\DeviceStatus;
use App\Enums\SubscriptionStatus;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SubscriptionManagementService
{
    public function __construct(private readonly PaymentGateway $gateway) {}

    /**
     * @return array<string, mixed>
     */
    public function overview(Business $business): array
    {
        $business->loadMissing(['currentSubscription.plan.features', 'subscriptions.plan', 'branches']);
        $subscription = $business->currentSubscription;
        $plan = $subscription?->plan;
        $usage = $this->usage($business);
        $limits = $this->limitsForPlan($plan);

        return [
            'subscription' => $subscription,
            'plan' => $plan,
            'features' => $plan?->features ?? collect(),
            'usage' => $usage,
            'limits' => $limits,
            'usageRows' => $this->usageRows($usage, $limits),
            'trial' => $this->trialStatus($subscription),
            'expiration' => $this->expirationStatus($subscription),
            'graceDays' => (int) config('marketlens.billing.grace_days', 7),
            'retentionDays' => (int) config('marketlens.billing.retention_days', 365),
            'operationalAccess' => $this->allowsOperationalAccess($subscription),
        ];
    }

    public function allowsOperationalAccess(?Subscription $subscription): bool
    {
        if (! $subscription) {
            return false;
        }

        if (in_array($subscription->status, [SubscriptionStatus::Trialing, SubscriptionStatus::Active], true)) {
            return ! $subscription->ends_at || $subscription->ends_at->endOfDay()->gte(now());
        }

        if ($subscription->status === SubscriptionStatus::PastDue && $subscription->ends_at) {
            return $subscription->ends_at->copy()->addDays((int) config('marketlens.billing.grace_days', 7))->endOfDay()->gte(now());
        }

        return false;
    }

    public function canUseFeature(Business $business, string $feature): bool
    {
        $business->loadMissing('currentSubscription.plan.features');

        if (! $this->allowsOperationalAccess($business->currentSubscription)) {
            return false;
        }

        $feature = strtolower($feature);

        return $business->currentSubscription?->plan?->features
            ->contains(fn ($planFeature): bool => str_contains(strtolower((string) $planFeature->name), $feature)) ?? false;
    }

    public function maxDevices(Business $business, string $deviceType): int
    {
        $business->loadMissing('currentSubscription.plan.features');
        $limits = $this->limitsForPlan($business->currentSubscription?->plan);

        return $deviceType === 'client_app'
            ? $limits['client_app_devices']
            : $limits['shop_app_devices'];
    }

    /**
     * @return array<string, int>
     */
    public function usage(Business $business): array
    {
        return [
            'branches' => $business->branches()->count(),
            'shop_app_devices' => $this->deviceCount($business, 'shop_app'),
            'client_app_devices' => $this->deviceCount($business, 'client_app'),
            'monitoring_users' => $business->owners()->count(),
            'storage_mb' => 0,
            'report_exports_per_month' => class_exists(ReportExport::class)
                ? ReportExport::query()->where('business_id', $business->id)->where('created_at', '>=', now()->startOfMonth())->count()
                : 0,
        ];
    }

    /**
     * @return array<string, int>
     */
    public function limitsForPlan(?Plan $plan): array
    {
        $limits = config('marketlens.billing.default_limits', []);
        $limits = [
            'branches' => (int) ($limits['branches'] ?? 1),
            'shop_app_devices' => (int) ($limits['shop_app_devices'] ?? 1),
            'client_app_devices' => (int) ($limits['client_app_devices'] ?? 0),
            'monitoring_users' => (int) ($limits['monitoring_users'] ?? 1),
            'storage_mb' => (int) ($limits['storage_mb'] ?? 0),
            'report_exports_per_month' => (int) ($limits['report_exports_per_month'] ?? 10),
        ];

        if (! $plan) {
            return $limits;
        }

        foreach ($plan->features()->pluck('name') as $featureName) {
            $feature = strtolower($featureName);

            $this->applyNumericFeatureLimit($limits, $feature, 'branches', 'branches?');
            $this->applyNumericFeatureLimit($limits, $feature, 'shop_app_devices', 'business server devices?');
            $this->applyNumericFeatureLimit($limits, $feature, 'client_app_devices', 'business app devices?');
            $this->applyNumericFeatureLimit($limits, $feature, 'monitoring_users', 'monitoring users?');
            $this->applyNumericFeatureLimit($limits, $feature, 'report_exports_per_month', 'reports?|report exports?');

            if (preg_match('/(\d+)\s*gb\s*storage/', $feature, $matches) === 1) {
                $limits['storage_mb'] = max($limits['storage_mb'], ((int) $matches[1]) * 1024);
            }

            if (str_contains($feature, 'multi-branch')) {
                $limits['branches'] = max($limits['branches'], 5);
            }

            if (str_contains($feature, 'more business app devices')) {
                $limits['shop_app_devices'] = max($limits['shop_app_devices'], 5);
                $limits['client_app_devices'] = max($limits['client_app_devices'], 5);
            }

            if (str_contains($feature, 'more monitoring users and devices')) {
                $limits['shop_app_devices'] = max($limits['shop_app_devices'], 25);
                $limits['client_app_devices'] = max($limits['client_app_devices'], 25);
                $limits['monitoring_users'] = max($limits['monitoring_users'], 25);
            }

            if (str_contains($feature, 'reports')) {
                $limits['report_exports_per_month'] = max($limits['report_exports_per_month'], 100);
            }

            foreach (array_keys($limits) as $key) {
                $label = str_replace('_', ' ', $key);
                if (str_contains($feature, 'unlimited '.$label)) {
                    $limits[$key] = PHP_INT_MAX;
                }
            }
        }

        return $limits;
    }

    /**
     * @return array<int, array{key: string, label: string, used: int, limit: int, exceeded: bool}>
     */
    public function usageRows(array $usage, array $limits): array
    {
        $labels = [
            'branches' => 'Branches',
            'shop_app_devices' => 'Business Server devices',
            'client_app_devices' => 'Business App devices',
            'monitoring_users' => 'Monitoring users',
            'storage_mb' => 'Storage',
            'report_exports_per_month' => 'Report exports this month',
        ];

        return collect($labels)->map(fn (string $label, string $key): array => [
            'key' => $key,
            'label' => $label,
            'used' => (int) ($usage[$key] ?? 0),
            'limit' => (int) ($limits[$key] ?? 0),
            'exceeded' => (int) ($usage[$key] ?? 0) > (int) ($limits[$key] ?? 0),
        ])->values()->all();
    }

    /**
     * @return array{status: string, days_remaining: ?int}
     */
    public function trialStatus(?Subscription $subscription): array
    {
        if (! $subscription || $subscription->status !== SubscriptionStatus::Trialing) {
            return ['status' => 'not_trialing', 'days_remaining' => null];
        }

        return [
            'status' => $subscription->ends_at?->isPast() ? 'expired' : 'trialing',
            'days_remaining' => $subscription->ends_at ? max(0, now()->diffInDays($subscription->ends_at, false)) : null,
        ];
    }

    /**
     * @return array{status: string, date: ?Carbon}
     */
    public function expirationStatus(?Subscription $subscription): array
    {
        if (! $subscription?->ends_at) {
            return ['status' => 'none', 'date' => null];
        }

        if ($subscription->ends_at->isPast()) {
            return ['status' => 'expired', 'date' => $subscription->ends_at];
        }

        return ['status' => 'expires', 'date' => $subscription->ends_at];
    }

    /**
     * @return array<int, string>
     */
    public function downgradeWarnings(Business $business, Plan $targetPlan): array
    {
        $usage = $this->usage($business);
        $limits = $this->limitsForPlan($targetPlan);
        $warnings = [];

        foreach ($this->usageRows($usage, $limits) as $row) {
            if ($row['exceeded']) {
                $warnings[] = $row['label'].' usage is '.$row['used'].' against a target plan limit of '.$this->formatLimit($row['limit']).'.';
            }
        }

        return $warnings;
    }

    public function requestPlanChange(Business $business, User $actor, int $planId): SubscriptionChangeRequest
    {
        $business->loadMissing('currentSubscription.plan.features');
        $targetPlan = Plan::query()->with('features')->active()->findOrFail($planId);
        $currentPlan = $business->currentSubscription?->plan;
        $type = $currentPlan && $this->rawPrice($targetPlan) < $this->rawPrice($currentPlan) ? 'downgrade' : 'upgrade';
        $warnings = $type === 'downgrade' ? $this->downgradeWarnings($business, $targetPlan) : [];
        $gatewayPayload = $this->gateway->checkoutForSubscriptionChange($business, $actor, $targetPlan, $business->currentSubscription);

        return DB::transaction(function () use ($business, $actor, $targetPlan, $type, $warnings, $gatewayPayload): SubscriptionChangeRequest {
            $request = SubscriptionChangeRequest::query()->create([
                'business_id' => $business->id,
                'subscription_id' => $business->currentSubscription?->id,
                'requested_plan_id' => $targetPlan->id,
                'requested_by' => $actor->id,
                'type' => $type,
                'status' => 'pending',
                'warnings' => $warnings,
                'usage_snapshot' => $this->usage($business),
                'gateway_payload' => $gatewayPayload,
                'requested_at' => now(),
            ]);

            $this->audit($business, $actor, 'subscription.'.$type.'_requested', $request->id, [
                'requested_plan_id' => $targetPlan->id,
                'warnings' => $warnings,
            ]);

            return $request;
        });
    }

    public function requestCancellation(Business $business, User $actor, ?string $reason = null): SubscriptionChangeRequest
    {
        $business->loadMissing('currentSubscription');
        $gatewayPayload = $this->gateway->cancelSubscription($business, $actor, $business->currentSubscription, $reason);

        return DB::transaction(function () use ($business, $actor, $reason, $gatewayPayload): SubscriptionChangeRequest {
            $request = SubscriptionChangeRequest::query()->create([
                'business_id' => $business->id,
                'subscription_id' => $business->currentSubscription?->id,
                'requested_by' => $actor->id,
                'type' => 'cancellation',
                'status' => 'pending',
                'warnings' => ['Synchronized data is retained according to the configured retention policy; it is not immediately deleted.'],
                'usage_snapshot' => $this->usage($business),
                'gateway_payload' => $gatewayPayload,
                'reason' => $reason,
                'requested_at' => now(),
            ]);

            $this->audit($business, $actor, 'subscription.cancellation_requested', $request->id);

            return $request;
        });
    }

    public function approveRequest(SubscriptionChangeRequest $request, User $actor): SubscriptionChangeRequest
    {
        if ($request->status !== 'pending') {
            throw ValidationException::withMessages(['request' => 'Only pending requests can be approved.']);
        }

        return DB::transaction(function () use ($request, $actor): SubscriptionChangeRequest {
            if ($request->type === 'cancellation') {
                $subscription = $request->subscription;

                if ($subscription) {
                    $subscription->forceFill([
                        'status' => SubscriptionStatus::Cancelled->value,
                        'ends_at' => now(),
                    ])->save();
                }

                $this->audit($request->business, $actor, 'subscription.cancellation_approved', $request->id);
            } else {
                $subscription = $request->subscription;

                if (! $subscription || ! $request->requestedPlan) {
                    throw ValidationException::withMessages(['request' => 'Unable to approve request because subscription or requested plan is missing.']);
                }

                $subscription->forceFill(['plan_id' => $request->requestedPlan->id])->save();
                $this->audit($request->business, $actor, 'subscription.change_approved', $request->id, [
                    'requested_plan_id' => $request->requestedPlan->id,
                ]);
            }

            $request->forceFill(['status' => 'approved', 'resolved_at' => now()])->save();

            return $request;
        });
    }

    public function rejectRequest(SubscriptionChangeRequest $request, User $actor): SubscriptionChangeRequest
    {
        if ($request->status !== 'pending') {
            throw ValidationException::withMessages(['request' => 'Only pending requests can be rejected.']);
        }

        return DB::transaction(function () use ($request, $actor): SubscriptionChangeRequest {
            $request->forceFill(['status' => 'rejected', 'resolved_at' => now()])->save();
            $this->audit($request->business, $actor, 'subscription.change_rejected', $request->id);

            return $request;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateBillingProfile(Business $business, User $actor, array $data): BillingProfile
    {
        $profile = BillingProfile::query()->updateOrCreate(
            ['business_id' => $business->id],
            [
                'billing_name' => $data['billing_name'],
                'billing_email' => $data['billing_email'],
                'billing_phone' => $data['billing_phone'] ?? null,
                'billing_address' => $data['billing_address'] ?? null,
                'tax_number' => $data['tax_number'] ?? null,
                'currency' => $data['currency'] ?? (string) config('marketlens.currency.default', 'NGN'),
            ]
        );

        $this->audit($business, $actor, 'subscription.billing_profile_updated', $profile->id);

        return $profile;
    }

    /**
     * @return Collection<int, Plan>
     */
    public function availablePlans(): Collection
    {
        return Plan::query()->with('features')->active()->orderBy('sort_order')->orderBy('price_minor')->get();
    }

    private function deviceCount(Business $business, string $type): int
    {
        return Device::query()
            ->where('business_id', $business->id)
            ->where('type', $type)
            ->whereIn('status', [DeviceStatus::Pending, DeviceStatus::Active])
            ->count();
    }

    /**
     * @param  array<string, int>  $limits
     */
    private function applyNumericFeatureLimit(array &$limits, string $feature, string $key, string $labelPattern): void
    {
        if (preg_match('/(\d+)\s+'.$labelPattern.'/', $feature, $matches) === 1) {
            $limits[$key] = max($limits[$key], (int) $matches[1]);
        }
    }

    private function rawPrice(Plan $plan): int
    {
        return (int) $plan->getRawOriginal('price_minor');
    }

    private function formatLimit(int $limit): string
    {
        return $limit === PHP_INT_MAX ? 'unlimited' : number_format($limit);
    }

    private function audit(Business $business, User $actor, string $action, ?int $subjectId = null, array $metadata = []): void
    {
        AuditLog::query()->create([
            'business_id' => $business->id,
            'actor_id' => $actor->id,
            'action' => $action,
            'subject_type' => SubscriptionChangeRequest::class,
            'subject_id' => $subjectId,
            'metadata' => $metadata,
        ]);
    }
}
