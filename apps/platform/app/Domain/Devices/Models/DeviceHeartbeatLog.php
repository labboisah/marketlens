<?php

namespace App\Domain\Devices\Models;

use App\Domain\Tenancy\Models\Branch;
use App\Domain\Tenancy\Models\Business;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeviceHeartbeatLog extends Model
{
    protected $fillable = [
        'business_id',
        'branch_id',
        'device_id',
        'device_uuid',
        'status',
        'app_version',
        'operating_system',
        'synchronization_errors',
        'last_synchronized_at',
        'reported_at',
    ];

    protected function casts(): array
    {
        return [
            'operating_system' => 'array',
            'synchronization_errors' => 'array',
            'last_synchronized_at' => 'datetime',
            'reported_at' => 'datetime',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }
}
