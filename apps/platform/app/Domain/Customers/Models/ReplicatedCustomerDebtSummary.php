<?php

namespace App\Domain\Customers\Models;

use App\Domain\Devices\Models\Device;
use App\Domain\Synchronization\Models\SyncBatch;
use App\Models\Concerns\BelongsToBranchTenant;
use App\Models\Concerns\BelongsToBusinessTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReplicatedCustomerDebtSummary extends Model
{
    use BelongsToBranchTenant;
    use BelongsToBusinessTenant;

    protected $fillable = [
        'business_id',
        'branch_id',
        'device_id',
        'sync_batch_id',
        'uuid',
        'customer_uuid',
        'customer_name',
        'phone',
        'credit_limit_minor',
        'current_balance_minor',
        'credit_sales_minor',
        'overdue_amount_minor',
        'debt_status',
        'last_payment_at',
        'last_payment_minor',
        'last_payment_method',
        'oldest_unpaid_sale_at',
        'oldest_unpaid_sale_number',
        'age_current_minor',
        'age_1_30_minor',
        'age_31_60_minor',
        'age_61_90_minor',
        'age_over_90_minor',
        'last_synchronized_at',
        'source_version',
        'source_idempotency_key',
        'source_occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'last_payment_at' => 'datetime',
            'oldest_unpaid_sale_at' => 'datetime',
            'last_synchronized_at' => 'datetime',
            'source_occurred_at' => 'datetime',
        ];
    }

    public function payments(): HasMany
    {
        return $this->hasMany(ReplicatedCustomerDebtPayment::class, 'customer_debt_summary_id');
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
