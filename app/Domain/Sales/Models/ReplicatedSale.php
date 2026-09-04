<?php

namespace App\Domain\Sales\Models;

use App\Domain\Devices\Models\Device;
use App\Domain\Synchronization\Models\OperationalUserProfile;
use App\Domain\Synchronization\Models\SyncBatch;
use App\Models\Concerns\BelongsToBranchTenant;
use App\Models\Concerns\BelongsToBusinessTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReplicatedSale extends Model
{
    use BelongsToBranchTenant;
    use BelongsToBusinessTenant;

    protected $fillable = [
        'business_id',
        'branch_id',
        'device_id',
        'sync_batch_id',
        'uuid',
        'sale_number',
        'transaction_number',
        'cashier_uuid',
        'cashier_name',
        'customer_uuid',
        'customer_name',
        'sale_type',
        'status',
        'payment_status',
        'sold_at',
        'subtotal_minor',
        'discount_minor',
        'tax_minor',
        'total_minor',
        'cost_minor',
        'gross_profit_minor',
        'cancellation',
        'return_summary',
        'source_version',
        'source_idempotency_key',
        'source_occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'sold_at' => 'datetime',
            'cancellation' => 'array',
            'return_summary' => 'array',
            'source_occurred_at' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(ReplicatedSaleItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(ReplicatedSalePayment::class);
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function syncBatch(): BelongsTo
    {
        return $this->belongsTo(SyncBatch::class);
    }

    public function cashierProfile(): BelongsTo
    {
        return $this->belongsTo(OperationalUserProfile::class, 'cashier_uuid', 'uuid');
    }
}
