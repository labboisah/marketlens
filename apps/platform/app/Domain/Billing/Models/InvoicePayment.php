<?php

namespace App\Domain\Billing\Models;

use App\Casts\MoneyCast;
use App\Enums\PaymentStatus;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoicePayment extends Model
{
    use HasUuid;

    protected $fillable = [
        'invoice_id',
        'uuid',
        'amount_minor',
        'currency',
        'status',
        'method',
        'reference',
        'paid_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'amount_minor' => MoneyCast::class,
            'status' => PaymentStatus::class,
            'paid_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
