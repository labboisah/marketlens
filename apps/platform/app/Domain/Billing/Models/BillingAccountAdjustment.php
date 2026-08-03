<?php

namespace App\Domain\Billing\Models;

use App\Casts\MoneyCast;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillingAccountAdjustment extends Model
{
    use HasUuid;

    protected $fillable = [
        'billing_account_id',
        'uuid',
        'type',
        'amount_minor',
        'currency',
        'description',
        'metadata',
        'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'amount_minor' => MoneyCast::class,
            'metadata' => 'array',
            'recorded_at' => 'datetime',
        ];
    }

    public function billingAccount(): BelongsTo
    {
        return $this->belongsTo(BillingAccount::class);
    }
}
