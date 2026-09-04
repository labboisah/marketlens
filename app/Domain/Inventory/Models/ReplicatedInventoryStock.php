<?php

namespace App\Domain\Inventory\Models;

use App\Models\Concerns\BelongsToBranchTenant;
use App\Models\Concerns\BelongsToBusinessTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReplicatedInventoryStock extends Model
{
    use BelongsToBranchTenant;
    use BelongsToBusinessTenant;

    protected $fillable = [
        'business_id', 'branch_id', 'device_id', 'sync_batch_id', 'uuid', 'product_uuid', 'product_name', 'sku', 'barcode',
        'base_unit', 'warehouse_uuid', 'warehouse_name', 'category_uuid', 'category_name', 'brand_uuid', 'brand_name',
        'available_quantity', 'reserved_quantity', 'stock_value_minor', 'reorder_level', 'overstock_level', 'stock_status',
        'expires_on', 'last_movement_at', 'last_synchronized_at', 'source_version', 'source_idempotency_key', 'source_occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'available_quantity' => 'decimal:4',
            'reserved_quantity' => 'decimal:4',
            'reorder_level' => 'decimal:4',
            'overstock_level' => 'decimal:4',
            'expires_on' => 'date',
            'last_movement_at' => 'datetime',
            'last_synchronized_at' => 'datetime',
            'source_occurred_at' => 'datetime',
        ];
    }

    public function movements(): HasMany
    {
        return $this->hasMany(ReplicatedInventoryMovement::class, 'product_uuid', 'product_uuid');
    }
}
