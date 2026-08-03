<?php

namespace App\Domain\Billing\Models;

use App\Casts\MoneyCast;
use App\Domain\Tenancy\Models\Business;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class BillingAccount extends Model
{
    use HasUuid;

    protected $fillable = [
        'business_id',
        'uuid',
        'balance_minor',
        'currency',
        'status',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'balance_minor' => MoneyCast::class,
            'metadata' => 'array',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function adjustments(): HasMany
    {
        return $this->hasMany(BillingAccountAdjustment::class)->latest('recorded_at');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'business_id', 'business_id');
    }
}
