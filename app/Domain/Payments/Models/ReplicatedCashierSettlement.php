<?php

namespace App\Domain\Payments\Models;

use App\Domain\Devices\Models\Device;
use App\Domain\Synchronization\Models\OperationalUserProfile;
use App\Domain\Synchronization\Models\SyncBatch;
use App\Models\Concerns\BelongsToBranchTenant;
use App\Models\Concerns\BelongsToBusinessTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReplicatedCashierSettlement extends Model
{
    use BelongsToBranchTenant;
    use BelongsToBusinessTenant;

    protected $fillable = [
        'business_id',
        'branch_id',
        'device_id',
        'sync_batch_id',
        'uuid',
        'shift_uuid',
        'cashier_uuid',
        'cashier_name',
        'expected_cash_minor',
        'actual_cash_minor',
        'shortage_minor',
        'surplus_minor',
        'cash_expenses_minor',
        'refunds_minor',
        'unverified_transfer_minor',
        'payment_mismatch_count',
        'reversal_count',
        'status',
        'settled_at',
        'source_version',
        'source_idempotency_key',
        'source_occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'settled_at' => 'datetime',
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

    public function cashierProfile(): BelongsTo
    {
        return $this->belongsTo(OperationalUserProfile::class, 'cashier_uuid', 'uuid');
    }
}
