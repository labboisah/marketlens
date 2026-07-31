<?php

namespace App\Models;

use App\Domain\Access\Models\BusinessMonitoringUser;
use App\Domain\Legal\Models\LegalAcceptance;
use App\Domain\Tenancy\Models\Business;
use App\Enums\AccountStatus;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'phone', 'password', 'account_status', 'is_super_admin', 'super_admin_confirmed_at', 'terms_accepted_at', 'privacy_accepted_at'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'account_status' => AccountStatus::class,
            'is_super_admin' => 'boolean',
            'super_admin_confirmed_at' => 'datetime',
            'terms_accepted_at' => 'datetime',
            'privacy_accepted_at' => 'datetime',
        ];
    }

    public function hasActiveAccount(): bool
    {
        return $this->account_status === AccountStatus::Active;
    }

    public function hasDisabledAccount(): bool
    {
        return in_array($this->account_status, [AccountStatus::Suspended, AccountStatus::Disabled], true);
    }

    public function isSuperAdmin(): bool
    {
        return (bool) $this->is_super_admin && $this->hasActiveAccount();
    }

    public function ownedBusinesses(): BelongsToMany
    {
        return $this->belongsToMany(Business::class, 'business_owners')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function monitoringAssignments(): HasMany
    {
        return $this->hasMany(BusinessMonitoringUser::class);
    }

    public function legalAcceptances(): HasMany
    {
        return $this->hasMany(LegalAcceptance::class);
    }

    public function monitoringBusinesses(): BelongsToMany
    {
        return $this->belongsToMany(Business::class, 'business_monitoring_users')
            ->withPivot(['monitoring_role_id', 'status', 'suspended_at', 'revoked_at', 'module_permissions'])
            ->withTimestamps();
    }

    /** @return Collection<int, Business> */
    public function accessibleBusinesses(): Collection
    {
        $owned = $this->ownedBusinesses()->with(['branches', 'currentSubscription.plan.features'])->get();
        $monitored = $this->monitoringBusinesses()
            ->with(['branches', 'currentSubscription.plan.features'])
            ->wherePivot('status', 'active')
            ->wherePivotNull('suspended_at')
            ->wherePivotNull('revoked_at')
            ->get();

        return $owned->concat($monitored)->unique('id')->sortBy('name')->values();
    }

    public function canAccessBusiness(Business|int $business): bool
    {
        $businessId = $business instanceof Business ? $business->id : $business;

        return $this->ownedBusinesses()->whereKey($businessId)->exists()
            || $this->monitoringAssignments()->where('business_id', $businessId)->where('status', 'active')->whereNull('suspended_at')->whereNull('revoked_at')->exists();
    }

    public function isBusinessOwner(Business|int $business): bool
    {
        $businessId = $business instanceof Business ? $business->id : $business;

        return $this->ownedBusinesses()->whereKey($businessId)->exists();
    }

    public function hasBusinessPermission(Business|int $business, string $permission): bool
    {
        $businessId = $business instanceof Business ? $business->id : $business;

        if ($this->isBusinessOwner($businessId)) {
            return true;
        }

        $assignment = $this->monitoringAssignments()
            ->with('role.permissions')
            ->where('business_id', $businessId)
            ->where('status', 'active')
            ->whereNull('suspended_at')
            ->whereNull('revoked_at')
            ->first();

        if (! $assignment) {
            return false;
        }

        $roleAllows = $assignment->role?->permissions->contains('slug', $permission) ?? false;
        $directAllows = collect($assignment->module_permissions ?? [])->contains($permission);

        return $roleAllows || $directAllows;
    }

    public function canAccessBranch(int $businessId, ?int $branchId): bool
    {
        if (! $branchId || $this->isBusinessOwner($businessId)) {
            return true;
        }

        $assignment = $this->monitoringAssignments()
            ->where('business_id', $businessId)
            ->where('status', 'active')
            ->whereNull('suspended_at')
            ->whereNull('revoked_at')
            ->first();

        if (! $assignment) {
            return false;
        }

        return ! $assignment->branches()->exists() || $assignment->branches()->whereKey($branchId)->exists();
    }
}

