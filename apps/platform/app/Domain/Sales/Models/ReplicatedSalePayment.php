<?php

namespace App\Domain\Sales\Models;

use App\Models\Concerns\BelongsToBranchTenant;
use App\Models\Concerns\BelongsToBusinessTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReplicatedSalePayment extends Model
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
        'method',
        'status',
        'amount_minor',
        'reference',
        'paid_at',
        'source_version',
        'source_idempotency_key',
        'source_occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'paid_at' => 'datetime',
            'source_occurred_at' => 'datetime',
        ];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(ReplicatedSale::class, 'replicated_sale_id');
    }
}
