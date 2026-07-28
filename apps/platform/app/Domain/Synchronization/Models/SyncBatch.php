<?php

namespace App\Domain\Synchronization\Models;

use App\Domain\Devices\Models\Device;
use App\Enums\SyncBatchStatus;
use App\Models\Concerns\BelongsToBranchTenant;
use App\Models\Concerns\BelongsToBusinessTenant;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SyncBatch extends Model
{
    use BelongsToBranchTenant;
    use BelongsToBusinessTenant;
    use HasUuid;

    protected $fillable = [
        'business_id',
        'branch_id',
        'device_id',
        'uuid',
        'status',
        'records_count',
        'received_at',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => SyncBatchStatus::class,
            'received_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }
}
