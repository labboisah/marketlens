<?php

namespace App\Domain\Sales\Models;

use App\Models\Concerns\BelongsToBranchTenant;
use App\Models\Concerns\BelongsToBusinessTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReplicatedSaleItem extends Model
{
    use BelongsToBranchTenant;
    use BelongsToBusinessTenant;

    protected $fillable = [
        'business_id',
        'branch_id',
        'replicated_sale_id',
        'sync_batch_id',
        'uuid',
        'sale_uuid',
        'product_uuid',
        'product_name',
        'sku',
        'category_uuid',
        'category_name',
        'quantity',
        'unit',
        'unit_price_minor',
        'discount_minor',
        'tax_minor',
        'line_total_minor',
        'cost_minor',
        'gross_profit_minor',
        'source_version',
        'source_idempotency_key',
        'source_occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'source_occurred_at' => 'datetime',
        ];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(ReplicatedSale::class, 'replicated_sale_id');
    }
}
