<?php

namespace App\Domain\Billing\Models;

use App\Enums\InvoiceStatus;
use App\Models\Concerns\HasUuid;
use App\Domain\Tenancy\Models\Business;
use App\Domain\Billing\Models\Subscription;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    use HasUuid;

    protected $fillable = [
        'business_id',
        'subscription_id',
        'uuid',
        'invoice_number',
        'status',
        'currency',
        'subtotal_minor',
        'total_minor',
        'description',
        'issued_at',
        'due_at',
        'paid_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'status' => InvoiceStatus::class,
            'subtotal_minor' => \App\Casts\MoneyCast::class,
            'total_minor' => \App\Casts\MoneyCast::class,
            'issued_at' => 'datetime',
            'due_at' => 'datetime',
            'paid_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(InvoicePayment::class);
    }
}
