<?php

namespace App\Domain\Billing\Models;

use App\Domain\Tenancy\Models\Business;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillingProfile extends Model
{
    protected $fillable = [
        'business_id',
        'billing_name',
        'billing_email',
        'billing_phone',
        'billing_address',
        'tax_number',
        'currency',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }
}
