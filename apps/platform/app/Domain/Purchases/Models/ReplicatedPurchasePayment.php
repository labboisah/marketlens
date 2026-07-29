<?php

namespace App\Domain\Purchases\Models;

use App\Models\Concerns\BelongsToBranchTenant;
use App\Models\Concerns\BelongsToBusinessTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReplicatedPurchasePayment extends Model
{
    use BelongsToBranchTenant;
    use BelongsToBusinessTenant;

    protected $fillable = [
        'business_id', 'branch_id', 'replicated_purchase_id', 'uuid', 'purchase_uuid', 'supplier_uuid', 'supplier_name',
        'method', 'status', 'amount_minor', 'reference', 'paid_at', 'source_version', 'source_idempotency_key', 'source_occurred_at',
    ];

    protected function casts(): array
    {
        return ['paid_at' => 'datetime', 'source_occurred_at' => 'datetime'];
    }

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(ReplicatedPurchase::class, 'replicated_purchase_id');
    }
}
