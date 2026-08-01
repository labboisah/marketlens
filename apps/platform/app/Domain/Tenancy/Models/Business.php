<?php

namespace App\Domain\Tenancy\Models;

use App\Domain\Access\Models\BusinessMonitoringUser;
use App\Domain\Access\Models\MonitoringUserInvitation;
use App\Domain\Billing\Models\BillingProfile;
use App\Domain\Billing\Models\Subscription;
use App\Domain\Billing\Models\SubscriptionChangeRequest;
use App\Domain\Devices\Models\Device;
use App\Domain\Settings\Models\BusinessDataExport;
use App\Domain\Settings\Models\BusinessTrustedDevice;
use App\Enums\BusinessStatus;
use App\Models\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Business extends Model
{
    use HasUuid;
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'name',
        'legal_name',
        'business_type',
        'registration_number',
        'tax_number',
        'phone',
        'alternative_phone',
        'email',
        'address',
        'state',
        'local_government_area',
        'default_currency',
        'timezone',
        'preferred_language',
        'logo_path',
        'status',
        'onboarding_status',
        'monitoring_preferences',
        'default_branch_id',
        'account_closure_requested_at',
        'account_closure_requested_by',
        'account_closure_reason',
        'onboarded_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => BusinessStatus::class,
            'monitoring_preferences' => 'array',
            'account_closure_requested_at' => 'datetime',
            'onboarded_at' => 'datetime',
        ];
    }

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    public function devices(): HasMany
    {
        return $this->hasMany(Device::class);
    }

    public function defaultBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'default_branch_id');
    }

    public function owners(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'business_owners')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function currentSubscription(): HasOne
    {
        return $this->hasOne(Subscription::class)->latestOfMany();
    }

    public function billingProfile(): HasOne
    {
        return $this->hasOne(BillingProfile::class);
    }

    public function subscriptionChangeRequests(): HasMany
    {
        return $this->hasMany(SubscriptionChangeRequest::class)->latest();
    }

    public function dataExports(): HasMany
    {
        return $this->hasMany(BusinessDataExport::class)->latest('requested_at');
    }

    public function trustedDevices(): HasMany
    {
        return $this->hasMany(BusinessTrustedDevice::class)->latest('trusted_at');
    }

    public function monitoringUsers(): HasMany
    {
        return $this->hasMany(BusinessMonitoringUser::class);
    }

    public function monitoringInvitations(): HasMany
    {
        return $this->hasMany(MonitoringUserInvitation::class);
    }
}
