<?php

namespace App\Domain\Synchronization\Models;

use App\Models\Concerns\BelongsToBranchTenant;
use App\Models\Concerns\BelongsToBusinessTenant;
use Illuminate\Database\Eloquent\Model;

class SyncCheckpoint extends Model
{
    use BelongsToBranchTenant;
    use BelongsToBusinessTenant;

    protected $fillable = [
        'business_id',
        'branch_id',
        'device_id',
        'last_batch_uuid',
        'last_successful_sync_at',
        'accepted_records_count',
        'rejected_records_count',
    ];

    protected function casts(): array
    {
        return [
            'last_successful_sync_at' => 'datetime',
        ];
    }
}
