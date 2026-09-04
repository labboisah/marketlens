<?php

namespace App\Domain\Devices\Models;

use App\Domain\Tenancy\Models\Branch;
use App\Domain\Tenancy\Models\Business;
use App\Enums\DeviceStatus;
use App\Models\Concerns\BelongsToBranchTenant;
use App\Models\Concerns\BelongsToBusinessTenant;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Device extends Model
{
    use BelongsToBranchTenant;
    use BelongsToBusinessTenant;
    use HasUuid;
    use SoftDeletes;

    protected $fillable = [
        'business_id',
        'branch_id',
        'application_version_id',
        'uuid',
        'name',
        'type',
        'status',
        'credential_hash',
        'credential_preview',
        'identity_hash',
        'app_version',
        'operating_system',
        'synchronization_errors',
        'last_seen_at',
        'activated_at',
        'last_synchronized_at',
        'revoked_at',
        'requires_update_at',
        'required_version',
        'update_required_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => DeviceStatus::class,
            'operating_system' => 'array',
            'synchronization_errors' => 'array',
            'last_seen_at' => 'datetime',
            'activated_at' => 'datetime',
            'last_synchronized_at' => 'datetime',
            'revoked_at' => 'datetime',
            'requires_update_at' => 'datetime',
        ];
    }

    public function activations(): HasMany
    {
        return $this->hasMany(DeviceActivation::class);
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function heartbeatLogs(): HasMany
    {
        return $this->hasMany(DeviceHeartbeatLog::class);
    }
}
