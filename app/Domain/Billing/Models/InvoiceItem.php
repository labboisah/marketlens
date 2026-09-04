<?php

namespace App\Domain\Billing\Models;

use App\Casts\MoneyCast;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceItem extends Model
{
    use HasUuid;

    protected $fillable = [
        'invoice_id',
        'description',
        'quantity',
        'unit_price_minor',
        'total_minor',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price_minor' => MoneyCast::class,
            'total_minor' => MoneyCast::class,
            'metadata' => 'array',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
