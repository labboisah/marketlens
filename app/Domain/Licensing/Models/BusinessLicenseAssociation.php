<?php

namespace App\Domain\Licensing\Models;

use App\Domain\Tenancy\Models\Business;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessLicenseAssociation extends Model
{
    use HasUuid;

    protected $table = 'business_license_associations';

    protected $fillable = [
        'uuid',
        'business_id',
        'provider',
        'product_code',
        'license_uuid',
        'subscription_uuid',
        'status',
        'expires_at',
        'grace_expires_at',
        'last_verified_at',
        'entitlements',
        'entitlement_revision',
        'provider_metadata',
        'provider_event_id',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'grace_expires_at' => 'datetime',
        'last_verified_at' => 'datetime',
        'entitlements' => 'array',
        'provider_metadata' => 'array',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function getRawActivationCodeAttribute(): ?string
    {
        return null;
    }
}
