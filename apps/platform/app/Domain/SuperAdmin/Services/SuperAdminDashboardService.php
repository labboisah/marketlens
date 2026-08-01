<?php

namespace App\Domain\SuperAdmin\Services;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Billing\Models\Plan;
use App\Domain\Billing\Models\Subscription;
use App\Domain\Billing\Models\SubscriptionPayment;
use App\Domain\Devices\Models\Device;
use App\Domain\Notifications\Models\PlatformNotification;
use App\Domain\Support\Models\SupportTicket;
use App\Domain\SuperAdmin\Models\SuperAdminActionConfirmation;
use App\Domain\SuperAdmin\Models\SupportAccessRequest;
use App\Domain\SuperAdmin\Models\SystemAnnouncement;
use App\Domain\Synchronization\Models\SyncBatch;
use App\Domain\Tenancy\Models\Business;
use App\Domain\Versions\Models\ApplicationVersion;
use App\Enums\BusinessStatus;
use App\Enums\DeviceStatus;
use App\Enums\SubscriptionStatus;
use App\Enums\SupportTicketStatus;
use App\Enums\SyncBatchStatus;
use App\Models\User;
use Illuminate\Support\Collection;

class SuperAdminDashboardService
{
    /** @return array<string, mixed> */
    public function ownerAccountMetrics(): array
    {
        $ownerAccounts = User::query()->whereHas('ownedBusinesses');

        return [
            'owner_accounts' => (clone $ownerAccounts)->count(),
            'active_owner_accounts' => (clone $ownerAccounts)->where('account_status', \App\Enums\AccountStatus::Active)->count(),
            'accounts_requiring_attention' => (clone $ownerAccounts)
                ->whereIn('account_status', [\App\Enums\AccountStatus::PendingVerification, \App\Enums\AccountStatus::Suspended, \App\Enums\AccountStatus::Disabled])
                ->count(),
            'businesses_under_management' => \DB::table('business_owners')->distinct('business_id')->count('business_id'),
        ];
    }

    /** @return Collection<int, array<string, mixed>> */
    public function businessOwnerAccountSummaries(): Collection
    {
        return User::query()
            ->whereHas('ownedBusinesses')
            ->with(['ownedBusinesses' => fn ($query) => $query->with(['currentSubscription.plan'])->withCount(['branches', 'devices'])->orderBy('businesses.name')])
            ->withCount('ownedBusinesses')
            ->latest()
            ->limit(15)
            ->get()
            ->map(function (User $owner): array {
                $primaryBusiness = $owner->ownedBusinesses->first();

                return [
                    'owner' => $owner,
                    'account_status' => $owner->account_status?->value ?? $owner->account_status,
                    'verified_at' => $owner->email_verified_at,
                    'business_count' => $owner->owned_businesses_count,
                    'primary_business' => $primaryBusiness,
                    'primary_plan' => $primaryBusiness?->currentSubscription?->plan?->name ?? 'No plan',
                    'primary_subscription_status' => $primaryBusiness?->currentSubscription?->status?->value ?? $primaryBusiness?->currentSubscription?->status ?? 'none',
                    'branch_count' => $primaryBusiness?->branches_count ?? 0,
                    'device_count' => $primaryBusiness?->devices_count ?? 0,
                ];
            });
    }
    /** @return array<string, mixed> */
    public function metrics(): array
    {
        $activeSubscriptions = Subscription::query()->where('status', SubscriptionStatus::Active)->count();
        $trialSubscriptions = Subscription::query()->where('status', SubscriptionStatus::Trialing)->count();

        return [
            'total_businesses' => Business::query()->count(),
            'active_businesses' => Business::query()->where('status', BusinessStatus::Active)->count(),
            'trial_businesses' => $trialSubscriptions,
            'suspended_businesses' => Business::query()->where('status', BusinessStatus::Suspended)->count(),
            'active_subscriptions' => $activeSubscriptions,
            'expired_subscriptions' => Subscription::query()
                ->where(fn ($query) => $query->where('status', SubscriptionStatus::Cancelled)->orWhere('ends_at', '<', now()))
                ->count(),
            'monthly_recurring_revenue_minor' => $this->monthlyRecurringRevenueMinor(),
            'active_shop_app_devices' => Device::query()->where('type', 'shop_app')->where('status', DeviceStatus::Active)->count(),
            'delayed_devices' => Device::query()->where('status', DeviceStatus::Active)->where('last_synchronized_at', '<', now()->subMinutes((int) config('synchronization.monitoring.delayed_minutes', 60)))->count(),
            'failed_sync_batches' => SyncBatch::query()->whereIn('status', [SyncBatchStatus::Failed, SyncBatchStatus::Rejected])->count(),
            'application_versions_in_use' => Device::query()->whereNotNull('app_version')->distinct('app_version')->count('app_version'),
            'open_support_tickets' => SupportTicket::query()->whereIn('status', [SupportTicketStatus::Open, SupportTicketStatus::Pending])->count(),
            'security_alerts' => PlatformNotification::query()->where('category', 'security')->whereNull('resolved_at')->count(),
        ];
    }

    /** @return array<int, array{label: string, route: string, count: int, description: string}> */
    public function modules(): array
    {
        return [
            ['label' => 'Businesses', 'route' => 'businesses', 'count' => Business::query()->count(), 'description' => 'Tenant business records and status.'],
            ['label' => 'Business owners', 'route' => 'business-owners', 'count' => User::query()->whereHas('ownedBusinesses')->count(), 'description' => 'Platform business-owner accounts.'],
            ['label' => 'Plans', 'route' => 'plans', 'count' => Plan::query()->count(), 'description' => 'Subscription plan catalogue.'],
            ['label' => 'Plan features', 'route' => 'plan-features', 'count' => \DB::table('plan_features')->count(), 'description' => 'Feature and limit definitions.'],
            ['label' => 'Subscriptions', 'route' => 'subscriptions', 'count' => Subscription::query()->count(), 'description' => 'Business subscription periods.'],
            ['label' => 'Subscription payments', 'route' => 'subscription-payments', 'count' => SubscriptionPayment::query()->count(), 'description' => 'Payment history records.'],
            ['label' => 'Devices', 'route' => 'devices', 'count' => Device::query()->count(), 'description' => 'Activated and pending devices.'],
            ['label' => 'Device activations', 'route' => 'device-activations', 'count' => \DB::table('device_activations')->count(), 'description' => 'Activation-code lifecycle records.'],
            ['label' => 'Synchronization batches', 'route' => 'sync-batches', 'count' => SyncBatch::query()->count(), 'description' => 'Cloud sync batch intake.'],
            ['label' => 'Application versions', 'route' => 'application-versions', 'count' => ApplicationVersion::query()->count(), 'description' => 'Published application releases.'],
            ['label' => 'Notifications', 'route' => 'notifications', 'count' => PlatformNotification::query()->count(), 'description' => 'Alert and notification records.'],
            ['label' => 'Support tickets', 'route' => 'support-tickets', 'count' => SupportTicket::query()->count(), 'description' => 'Customer support conversations.'],
            ['label' => 'Legal documents', 'route' => 'legal-documents', 'count' => \DB::table('legal_documents')->count(), 'description' => 'Versioned legal document records.'],
            ['label' => 'System announcements', 'route' => 'system-announcements', 'count' => SystemAnnouncement::query()->count(), 'description' => 'Platform-wide announcement foundation.'],
            ['label' => 'Audit logs', 'route' => 'audit-logs', 'count' => AuditLog::query()->count(), 'description' => 'Full administrative audit trail.'],
        ];
    }

    /** @return Collection<int, array<string, mixed>> */
    public function businessAccountSummaries(): Collection
    {
        return Business::query()
            ->with(['owners', 'currentSubscription.plan', 'billingProfile'])
            ->withCount(['branches', 'devices', 'monitoringUsers'])
            ->withMax('branches as latest_branch_sync_at', 'last_synchronized_at')
            ->latest()
            ->limit(8)
            ->get()
            ->map(fn (Business $business): array => [
                'business' => $business,
                'owners' => $business->owners->pluck('email')->filter()->values(),
                'plan' => $business->currentSubscription?->plan?->name ?? 'No plan',
                'subscription_status' => $business->currentSubscription?->status?->value ?? $business->currentSubscription?->status ?? 'none',
                'billing_email' => $business->billingProfile?->billing_email ?? $business->email,
                'branch_count' => $business->branches_count,
                'device_count' => $business->devices_count,
                'monitoring_user_count' => $business->monitoring_users_count,
                'latest_sync_at' => $business->latest_branch_sync_at,
            ]);
    }
    public function tableRows(string $module): Collection
    {
        return match ($module) {
            'businesses' => Business::query()
                ->with(['owners', 'currentSubscription.plan', 'defaultBranch'])
                ->withCount(['branches', 'devices'])
                ->withMax('branches as latest_branch_sync_at', 'last_synchronized_at')
                ->latest()
                ->limit(25)
                ->get(),
            'business-owners' => User::query()->whereHas('ownedBusinesses')->latest()->limit(25)->get(['id', 'name', 'email', 'account_status', 'created_at']),
            'plans' => Plan::query()->latest()->limit(25)->get(['id', 'name', 'billing_interval', 'price_minor', 'currency', 'is_active']),
            'subscriptions' => Subscription::query()->with(['business', 'plan'])->latest()->limit(25)->get(),
            'devices' => Device::query()->with(['business', 'branch'])->latest()->limit(25)->get(),
            'sync-batches' => SyncBatch::query()->with(['business', 'branch', 'device'])->latest()->limit(25)->get(),
            'application-versions' => ApplicationVersion::query()->latest()->limit(25)->get(),
            'notifications' => PlatformNotification::query()->with('business')->latest()->limit(25)->get(),
            'support-tickets' => SupportTicket::query()->with('business')->latest()->limit(25)->get(),
            'system-announcements' => SystemAnnouncement::query()->latest()->limit(25)->get(),
            'audit-logs' => AuditLog::query()->with('business')->latest('created_at')->limit(25)->get(),
            default => collect(),
        };
    }

    public function recordConfirmation(User $actor, string $action, string $phrase, ?string $ip, ?string $userAgent): SuperAdminActionConfirmation
    {
        return SuperAdminActionConfirmation::query()->create([
            'user_id' => $actor->id,
            'action' => $action,
            'confirmation_phrase' => $phrase,
            'confirmed_at' => now(),
            'ip_address' => $ip,
            'user_agent' => $userAgent ? substr($userAgent, 0, 1000) : null,
        ]);
    }

    private function monthlyRecurringRevenueMinor(): int
    {
        return Subscription::query()
            ->join('plans', 'plans.id', '=', 'subscriptions.plan_id')
            ->where('subscriptions.status', SubscriptionStatus::Active->value)
            ->sum(\DB::raw("case when plans.billing_interval = 'annual' then plans.price_minor / 12 else plans.price_minor end"));
    }
}


