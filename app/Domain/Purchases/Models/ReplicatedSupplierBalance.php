<?php

namespace App\Domain\Purchases\Models;

use App\Domain\Devices\Models\Device;
use App\Domain\Synchronization\Models\SyncBatch;
use App\Models\Concerns\BelongsToBranchTenant;
use App\Models\Concerns\BelongsToBusinessTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReplicatedSupplierBalance extends Model
{
    use BelongsToBranchTenant;
    use BelongsToBusinessTenant;

    protected $fillable = [
        'business_id', 'branch_id', 'device_id', 'sync_batch_id', 'uuid', 'supplier_uuid', 'supplier_name', 'phone',
        'current_balance_minor', 'overdue_balance_minor', 'last_purchase_at', 'last_payment_at', 'status', 'last_synchronized_at',
        'source_version', 'source_idempotency_key', 'source_occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'last_purchase_at' => 'datetime',
            'last_payment_at' => 'datetime',
            'last_synchronized_at' => 'datetime',
            'source_occurred_at' => 'datetime',
        ];
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function syncBatch(): BelongsTo
    {
        return $this->belongsTo(SyncBatch::class);
    }
}
