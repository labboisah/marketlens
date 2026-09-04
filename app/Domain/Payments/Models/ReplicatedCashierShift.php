<?php

namespace App\Domain\Payments\Models;

use App\Domain\Devices\Models\Device;
use App\Domain\Synchronization\Models\OperationalUserProfile;
use App\Domain\Synchronization\Models\SyncBatch;
use App\Models\Concerns\BelongsToBranchTenant;
use App\Models\Concerns\BelongsToBusinessTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReplicatedCashierShift extends Model
{
    use BelongsToBranchTenant;
    use BelongsToBusinessTenant;

    protected $fillable = [
        'business_id',
        'branch_id',
        'device_id',
        'sync_batch_id',
        'uuid',
        'cashier_uuid',
        'cashier_name',
        'status',
        'opened_at',
        'closed_at',
        'duration_minutes',
        'last_activity_at',
        'source_version',
        'source_idempotency_key',
        'source_occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
            'last_activity_at' => 'datetime',
            'source_occurred_at' => 'datetime',
        ];
    }

    public function cashierProfile(): BelongsTo
    {
        return $this->belongsTo(OperationalUserProfile::class, 'cashier_uuid', 'uuid');
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
