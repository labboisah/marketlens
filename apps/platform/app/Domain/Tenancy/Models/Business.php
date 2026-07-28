<?php

namespace App\Domain\Tenancy\Models;

use App\Domain\Billing\Models\Subscription;
use App\Enums\BusinessStatus;
use App\Models\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
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
        'onboarded_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => BusinessStatus::class,
            'monitoring_preferences' => 'array',
            'onboarded_at' => 'datetime',
        ];
    }

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
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
}
