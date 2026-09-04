<?php

namespace App\Domain\Purchases\Models;

use App\Domain\Devices\Models\Device;
use App\Domain\Synchronization\Models\SyncBatch;
use App\Models\Concerns\BelongsToBranchTenant;
use App\Models\Concerns\BelongsToBusinessTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReplicatedPurchase extends Model
{
    use BelongsToBranchTenant;
    use BelongsToBusinessTenant;

    protected $fillable = [
        'business_id', 'branch_id', 'device_id', 'sync_batch_id', 'uuid', 'purchase_number', 'supplier_uuid', 'supplier_name',
        'purchased_at', 'status', 'payment_status', 'subtotal_minor', 'transport_cost_minor', 'additional_cost_minor',
        'total_minor', 'amount_paid_minor', 'balance_minor', 'source_version', 'source_idempotency_key', 'source_occurred_at',
    ];

    protected function casts(): array
    {
        return ['purchased_at' => 'datetime', 'source_occurred_at' => 'datetime'];
    }

    public function items(): HasMany
    {
        return $this->hasMany(ReplicatedPurchaseItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(ReplicatedPurchasePayment::class);
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
