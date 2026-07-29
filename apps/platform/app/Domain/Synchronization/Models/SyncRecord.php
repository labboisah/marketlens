<?php

namespace App\Domain\Synchronization\Models;

use App\Models\Concerns\BelongsToBranchTenant;
use App\Models\Concerns\BelongsToBusinessTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SyncRecord extends Model
{
    use BelongsToBranchTenant;
    use BelongsToBusinessTenant;

    protected $fillable = [
        'sync_batch_id',
        'business_id',
        'branch_id',
        'device_id',
        'record_uuid',
        'entity',
        'operation',
        'version',
        'idempotency_key',
        'payload_hash',
        'status',
        'error_code',
        'error_message',
        'retryable',
        'payload',
        'occurred_at',
        'acknowledged_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'retryable' => 'boolean',
            'occurred_at' => 'datetime',
            'acknowledged_at' => 'datetime',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(SyncBatch::class, 'sync_batch_id');
    }
}
