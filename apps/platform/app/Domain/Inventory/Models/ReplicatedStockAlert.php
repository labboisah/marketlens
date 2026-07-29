<?php

namespace App\Domain\Inventory\Models;

use App\Models\Concerns\BelongsToBranchTenant;
use App\Models\Concerns\BelongsToBusinessTenant;
use Illuminate\Database\Eloquent\Model;

class ReplicatedStockAlert extends Model
{
    use BelongsToBranchTenant;
    use BelongsToBusinessTenant;

    protected $fillable = [
        'business_id', 'branch_id', 'device_id', 'sync_batch_id', 'uuid', 'product_uuid', 'product_name',
        'alert_type', 'severity', 'status', 'message', 'occurred_at', 'source_version', 'source_idempotency_key', 'source_occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'source_occurred_at' => 'datetime',
        ];
    }
}
