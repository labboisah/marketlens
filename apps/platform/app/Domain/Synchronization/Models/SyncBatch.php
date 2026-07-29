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
        'protocol_version',
        'status',
        'records_count',
        'trace_id',
        'payload_hash',
        'accepted_records',
        'rejected_records',
        'conflicting_records',
        'processing_errors',
        'invalid_payload_excerpt',
        'received_at',
        'processed_at',
        'last_acknowledged_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => SyncBatchStatus::class,
            'accepted_records' => 'array',
            'rejected_records' => 'array',
            'conflicting_records' => 'array',
            'processing_errors' => 'array',
            'received_at' => 'datetime',
            'processed_at' => 'datetime',
            'last_acknowledged_at' => 'datetime',
        ];
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }
}
